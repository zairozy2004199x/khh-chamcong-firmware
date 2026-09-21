<?php
/**
 * NẠP BẢNG CÔNG CŨ — khuôn "MỖI NGÀY MỘT DÒNG, MỖI NGƯỜI BA CỘT".
 *
 * =================================================================================================
 * Anh Thắng 21/09/2026: *"anh đang có bảng công cũ, làm sao để nạp vào mà không cần copy"*.
 * =================================================================================================
 *
 * 🔴 ĐÂY LÀ KHUÔN NGƯỢC VỚI `VHCC_NapCong`, ĐỪNG NHẦM HAI CÁI.
 *    `VHCC_NapCong` đọc bảng của Sheets cũ: mỗi NGƯỜI một dòng, cột gom theo NGÀY.
 *    Tệp này đọc bảng của anh Thắng: mỗi NGÀY một dòng, cột gom theo NGƯỜI.
 *    Cùng là "bảng chấm công" nhưng xoay 90 độ, nên không dùng chung một bộ đọc được.
 *
 *    dòng 1:  BẢNG CHẤM CÔNG THÁNG 4/2026
 *    dòng 2:  Thời gian/Ngày | Ngân |  |  | K.Oanh (LT) |  |  | K.Oanh | …
 *    dòng 3:                 | Check in | Check out | Số giờ làm | Check in | …
 *    dòng 4+: 1 | 0 | 0 | 00:00 | 09:35 | 13:10 | 03:35 | …
 *
 *    Tên người chỉ ghi ở cột ĐẦU cụm (ô gộp), hai ô sau để trống — y như `VHCC_NapCong` phải
 *    chịu với cụm ngày. Ô trống ghi `0`, không phải rỗng.
 *
 * =================================================================================================
 * 🔴 HAI CA TRONG MỘT NGÀY: NỐI THẲNG HAI ĐẦU LÀ TRẢ DƯ TIỀN
 * =================================================================================================
 * Anh Thắng 21/09/2026 xác nhận cụm `(LT)` và cụm thường là HAI CA của CÙNG một người trong
 * cùng một ngày. Phần lớn là hai ca liền nhau (09:35→13:00 rồi 13:00→17:00), nối lại thành
 * 09:35→17:00 là đúng.
 *
 * NHƯNG KHÔNG PHẢI LÚC NÀO CŨNG LIỀN. Ngay trong tệp anh gửi, ngày 11 của N.Kiệt là
 * `08:30→13:00` và `17:05→22:00`. Nối thẳng hai đầu ra 08:30→22:00 = 13 giờ 30, trong khi người
 * ta làm 4:30 + 4:55 = 9 giờ 25. Dư bốn tiếng, cho MỘT người trong MỘT ngày — nhân với cả bảng
 * thì đó là một khoản tiền thật.
 *
 * Nên khoảng giữa hai ca được ghi vào `nghi_tu_giay`/`nghi_den_giay` — đúng cột mà hệ đã có sẵn
 * cho ca gãy, và mọi phép tính công trong hệ đã biết trừ nó ra.
 *
 * ⚠️ BA CA TRỞ LÊN THÌ CHỐI, KHÔNG ĐOÁN. Một hàng chỉ giữ được MỘT khoảng nghỉ. Gặp ba ca mà
 *    vẫn ghi bừa một khoảng là giấu mất một khoảng khác, và giấu theo hướng trả dư.
 *
 * ⚠️ HÀM `doc()` LÀ HÀM THUẦN — không chạm cơ sở dữ liệu, không cần WordPress. Nhờ vậy thử
 *    được thẳng trên tệp thật của anh Thắng bằng một lượt chạy php, trước khi ghi một dòng nào.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NapDoc {

	/** Nhãn nguồn — cùng nhãn với `VHCC_NapCong`: cả hai đều là bảng cũ nạp vào. */
	const NGUON = 'sheet';

	/** Một cụm của một người dài bao nhiêu cột (Check in · Check out · Số giờ làm). */
	const CUM = 3;

	/**
	 * ĐỌC CẢ BẢNG.
	 *
	 * @param array $dong mảng các dòng, mỗi dòng là mảng ô (đã đọc từ .xlsx hoặc .csv).
	 * @return array ok · thang · nguoi[] · luot[] · canh[]
	 */
	public static function doc( $dong ) {
		$dong = array_values( (array) $dong );
		$ra = array( 'ok' => false, 'thang' => '', 'nguoi' => array(), 'luot' => array(),
			'canh' => array() );

		$thang = self::tim_thang( $dong );
		if ( '' === $thang ) {
			$ra['canh'][] = 'Không thấy tháng trong tệp. Dòng tiêu đề phải có dạng '
				. '"BẢNG CHẤM CÔNG THÁNG 4/2026".';
			return $ra;
		}
		$ra['thang'] = $thang;

		$i_cot = self::tim_dong_cot( $dong );
		if ( null === $i_cot ) {
			$ra['canh'][] = 'Không thấy dòng "Check in / Check out". Tệp này không phải khuôn '
				. 'bảng công theo ngày.';
			return $ra;
		}

		/* Dòng tên người nằm NGAY TRÊN dòng "Check in". Không dò theo số dòng cố định: bảng có
		   thể có một hay hai dòng tiêu đề phía trên, tuỳ người xuất. */
		$i_ten = $i_cot - 1;
		if ( $i_ten < 0 ) {
			$ra['canh'][] = 'Không thấy dòng tên người phía trên dòng "Check in".';
			return $ra;
		}

		$cum = self::tim_cum( $dong[ $i_ten ], $dong[ $i_cot ] );
		if ( ! $cum ) {
			$ra['canh'][] = 'Không thấy cụm cột nào của người nào.';
			return $ra;
		}

		/* Gom theo (tên gốc, ngày) — hai cụm của cùng một người rơi vào cùng một ô. */
		$gom = array();
		$ten_thay = array();
		for ( $i = $i_cot + 1; $i < count( $dong ); $i++ ) {
			$ngay_so = self::so_ngay( isset( $dong[ $i ][0] ) ? $dong[ $i ][0] : '' );
			if ( null === $ngay_so ) { continue; }   // Lễ · Biên Bản · Tổng · dòng trống
			$ngay = $thang . '-' . str_pad( (string) $ngay_so, 2, '0', STR_PAD_LEFT );
			if ( ! self::ngay_that( $ngay ) ) {
				$ra['canh'][] = 'Tháng ' . $thang . ' không có ngày ' . $ngay_so . ' — bỏ dòng ấy.';
				continue;
			}
			foreach ( $cum as $c ) {
				$vao = self::gio( isset( $dong[ $i ][ $c['cot'] ] ) ? $dong[ $i ][ $c['cot'] ] : '' );
				$ra_g = self::gio( isset( $dong[ $i ][ $c['cot'] + 1 ] ) ? $dong[ $i ][ $c['cot'] + 1 ] : '' );
				if ( null === $vao && null === $ra_g ) { continue; }
				$ten_thay[ $c['ten'] ] = true;
				$gom[ $c['ten'] ][ $ngay ][] = array( 'vao' => $vao, 'ra' => $ra_g, 'cum' => $c['nhan'] );
			}
		}

		$ra['nguoi'] = array_keys( $ten_thay );
		sort( $ra['nguoi'] );

		foreach ( $gom as $ten => $theo_ngay ) {
			foreach ( $theo_ngay as $ngay => $ca ) {
				$l = self::gop_ca( $ten, $ngay, $ca );
				if ( isset( $l['canh'] ) ) { $ra['canh'][] = $l['canh']; }
				if ( ! empty( $l['bo'] ) ) { continue; }
				$ra['luot'][] = $l;
			}
		}
		/* Xếp theo ngày rồi tên: nhật ký nạp đọc lại được theo trình tự thời gian. */
		usort( $ra['luot'], function ( $a, $b ) {
			$c = strcmp( $a['ngay'], $b['ngay'] );
			return ( 0 !== $c ) ? $c : strcmp( $a['ten'], $b['ten'] );
		} );

		$ra['ok'] = true;
		return $ra;
	}

	/**
	 * GỘP CÁC CA CỦA MỘT NGƯỜI TRONG MỘT NGÀY.
	 *
	 * @return array ten · ngay · vao · ra · nghiTu · nghiDen · soCa · (canh) · (bo)
	 */
	private static function gop_ca( $ten, $ngay, $ca ) {
		/* Ca thiếu một đầu vẫn giữ — hệ có đường bù giờ cho nó, và bỏ đi là mất luôn dấu vết
		   rằng hôm ấy người này CÓ đi làm. */
		$du = array();
		$thieu = 0;
		foreach ( $ca as $x ) {
			if ( null !== $x['vao'] && null !== $x['ra'] ) { $du[] = $x; } else { $thieu++; }
		}

		$l = array( 'ten' => $ten, 'ngay' => $ngay, 'vao' => null, 'ra' => null,
			'nghiTu' => null, 'nghiDen' => null, 'soCa' => count( $ca ) );

		if ( ! $du ) {
			/* Chỉ có ca thiếu đầu — lấy đầu nào có. */
			foreach ( $ca as $x ) {
				if ( null !== $x['vao'] && null === $l['vao'] ) { $l['vao'] = $x['vao']; }
				if ( null !== $x['ra'] ) { $l['ra'] = $x['ra']; }
			}
			$l['canh'] = $ten . ' ' . $ngay . ': ca thiếu một đầu giờ — nạp vào rồi bù sau.';
			return $l;
		}

		usort( $du, function ( $a, $b ) { return $a['vao'] - $b['vao']; } );
		$l['vao'] = $du[0]['vao'];
		$l['ra']  = $du[ count( $du ) - 1 ]['ra'];

		/* 🔴 BA CA TRỞ LÊN THÌ CHỐI. Một hàng chỉ giữ được MỘT khoảng nghỉ; ghi bừa một khoảng
		   là giấu mất khoảng khác, và giấu theo hướng TRẢ DƯ. */
		if ( count( $du ) > 2 ) {
			$l['bo'] = true;
			$l['canh'] = '🔴 ' . $ten . ' ' . $ngay . ': có ' . count( $du ) . ' ca trong một ngày — '
				. 'một hàng chỉ ghi được MỘT khoảng nghỉ giữa ca, nên bỏ qua ngày này. Nhập tay.';
			return $l;
		}

		if ( 2 === count( $du ) ) {
			$het1 = (int) $du[0]['ra'];
			$dau2 = (int) $du[1]['vao'];
			if ( $dau2 > $het1 ) {
				/* Khoảng giữa hai ca — trừ ra, nếu không thì trả dư đúng bằng khoảng ấy. */
				$l['nghiTu']  = $het1;
				$l['nghiDen'] = $dau2;
			} elseif ( $dau2 < $het1 ) {
				$l['canh'] = '⚠ ' . $ten . ' ' . $ngay . ': hai ca CHỒNG nhau ('
					. VHCC_DB::hhmm( $du[0]['vao'] ) . '–' . VHCC_DB::hhmm( $het1 ) . ' và '
					. VHCC_DB::hhmm( $dau2 ) . '–' . VHCC_DB::hhmm( $du[1]['ra'] )
					. ') — nạp thành một khung liền, xem lại bảng gốc.';
			}
		}
		if ( $thieu > 0 ) {
			$l['canh'] = '⚠ ' . $ten . ' ' . $ngay . ': có thêm ' . $thieu
				. ' ca thiếu một đầu giờ — chỉ nạp ca đủ cặp.';
		}
		return $l;
	}

	/* ============================================================================ ghi vào */

	/**
	 * ĐỌC RỒI GHI VÀO BẢNG CHẤM CÔNG.
	 *
	 * ⚠️ ĐI QUA ĐÚNG `VHCC_Nhan::ghi_gio()` — như `VHCC_NapCong`. Nạp lại bao nhiêu lần cũng
	 *    không sinh trùng, và không bao giờ thu hẹp giờ đã có.
	 *
	 * @param array $map  tên trong tệp => mã NV, do người ta vừa chọn ở màn ghép. Trộn ĐÈ lên
	 *                    sổ ghép đã lưu, rồi lưu lại — anh Thắng 21/09/2026 muốn "nhớ cho lần sau".
	 * @param bool  $chi_xem true = chỉ đếm và kể, KHÔNG ghi một dòng nào. Mặc định true.
	 */
	public static function nap( $u, $coso, $dong, $map = array(), $chi_xem = true ) {
		if ( ! VHCC_Vai::duoc( $u, 'nap_cong' ) ) {
			return array( 'ok' => false, 'error' => 'Nạp dữ liệu công cần quyền Quản lý trở lên.' );
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở để nạp vào.' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}

		$d = self::doc( $dong );
		if ( empty( $d['ok'] ) ) { return $d; }
		$canh = $d['canh'];

		/* Sổ ghép đã lưu + những ô người ta vừa chọn. Ô vừa chọn ĐÈ lên sổ: đó là người sửa lại
		   một phép ghép cũ sai, và phép sửa ấy phải thắng. */
		$so   = self::so_ghep( $coso );
		$moi  = self::loc_map( $map, $d['nguoi'] );
		$ghep = array_merge( $so, $moi );

		/* Tên đã ghép mà mã ấy KHÔNG còn hồ sơ -> coi như chưa ghép. Người nghỉ việc bị xoá hồ
		   sơ thì sổ ghép còn trỏ tới một mã ma, và ghi vào đó là công rơi vào chỗ không ai xem. */
		$ten_hs = array();
		foreach ( $ghep as $t => $ma ) {
			$hs = VHCC_NhanSu::ho_so( $ma );
			if ( ! $hs ) {
				unset( $ghep[ $t ] );
				$canh[] = '⚠ Tên "' . $t . '" từng ghép với mã ' . $ma . ' nhưng mã ấy không còn '
					. 'hồ sơ — chọn lại người.';
				continue;
			}
			$ten_hs[ $ma ] = (string) $hs['ho_ten'];
		}

		/* Lưu NGAY ở bước xem trước luôn, không đợi "Nạp thật": người ta hay xem đi xem lại vài
		   lần, và bắt chọn lại hai chục cái tên ở mỗi lượt xem là thứ khiến không ai dùng màn này. */
		if ( $moi ) { self::luu_ghep( $u, $coso, $moi ); }

		/* Gợi ý cho những tên CHƯA ghép — chỉ gợi, không tự lấy. */
		$ds_nv  = VHCC_NhanSu::ds_nhan_vien( $u, $coso );
		$nguoi  = array();
		$thieu  = 0;
		foreach ( $d['nguoi'] as $t ) {
			$ma = isset( $ghep[ $t ] ) ? $ghep[ $t ] : '';
			$goi = ( '' === $ma ) ? self::goi_y( $t, $ds_nv ) : array();
			if ( '' === $ma ) { $thieu++; }
			$nguoi[] = array(
				'ten'   => $t,
				'ma'    => $ma,
				'tenHS' => ( '' !== $ma && isset( $ten_hs[ $ma ] ) ) ? $ten_hs[ $ma ] : '',
				'tuDau' => ( '' === $ma ) ? '' : ( isset( $moi[ $t ] ) ? 'moi' : 'luu' ),
				'goiY'  => $goi,
			);
		}

		$ghi = 0;
		$bo_luot = 0;
		$nghi_ghi = 0;
		$ngay_co = array();
		foreach ( $d['luot'] as $x ) {
			$ngay_co[ $x['ngay'] ] = 1;
			if ( ! isset( $ghep[ $x['ten'] ] ) ) { $bo_luot++; continue; }
			if ( $chi_xem ) { $ghi++; continue; }

			$ma  = $ghep[ $x['ten'] ];
			$ten = isset( $ten_hs[ $ma ] ) ? $ten_hs[ $ma ] : $x['ten'];
			if ( null !== $x['vao'] ) {
				VHCC_Nhan::ghi_gio( $coso, $x['ngay'], $ma, $ten, (int) $x['vao'], '', self::NGUON );
			}
			if ( null !== $x['ra'] ) {
				VHCC_Nhan::ghi_gio( $coso, $x['ngay'], $ma, $ten, (int) $x['ra'], '', self::NGUON );
			}
			$ghi++;

			/* 🔴 KHOẢNG NGHỈ GHI SAU CÙNG, VÀ PHẢI KIỂM KẾT QUẢ. `dat_nghi_giua()` chối khi hàng
			   trong sổ không bao được khoảng ấy — nghĩa là giờ trong sổ đã khác tệp (người ta sửa
			   tay, hoặc máy đã ghi khác). Ghi bừa một khoảng nghỉ vào một khung giờ khác là TRỪ
			   nhầm của người ta mấy tiếng, và trừ im lặng. Chối thì phải kể ra. */
			if ( null !== $x['nghiTu'] && null !== $x['nghiDen'] ) {
				if ( VHCC_Nhan::dat_nghi_giua( $coso, $x['ngay'], $ma, $x['nghiTu'], $x['nghiDen'] ) ) {
					$nghi_ghi++;
				} else {
					$canh[] = '🔴 ' . $x['ten'] . ' ' . $x['ngay'] . ': không đặt được khoảng nghỉ '
						. VHCC_DB::hhmm( $x['nghiTu'] ) . '–' . VHCC_DB::hhmm( $x['nghiDen'] )
						. ' (giờ trong sổ đã khác tệp) — ngày này công sẽ TÍNH DƯ, xem lại tay.';
				}
			}
		}

		if ( $thieu > 0 ) {
			$canh[] = '⚠ Còn ' . $thieu . ' tên chưa ghép với người nào — ' . $bo_luot
				. ' lượt của họ KHÔNG được nạp. Chọn người cho từng tên rồi bấm lại.';
		}

		return array( 'ok' => true, 'chi_xem' => (bool) $chi_xem, 'coSo' => $coso,
			'thang' => $d['thang'], 'nguoi' => $nguoi, 'so_nguoi' => count( $d['nguoi'] ),
			'so_thieu' => $thieu, 'so_ngay' => count( $ngay_co ), 'so_luot' => count( $d['luot'] ),
			'da_ghi' => $ghi, 'bo_luot' => $bo_luot, 'nghi_ghi' => $nghi_ghi, 'canh' => $canh );
	}

	/* ====================================================================== giữ tệp tạm */

	/**
	 * GIỮ BẢNG VỪA TẢI LÊN ĐỂ BƯỚC GHÉP TÊN DÙNG LẠI.
	 *
	 * 🔴 VÌ SAO PHẢI GIỮ. Màn này có hai bước: xem trước + ghép tên, rồi mới nạp thật. Ô chọn
	 *    tệp của trình duyệt KHÔNG điền lại được bằng mã — bảo mật của trình duyệt cấm. Không
	 *    giữ tệp thì mỗi lần sửa một ô ghép là phải chọn lại tệp, và ai cũng sẽ bỏ giữa chừng
	 *    rồi quay về gõ tay — tức là mất đúng cái mà anh Thắng hỏi ("không cần copy").
	 *
	 * ⚠️ MÃ PHẢI ĐOÁN KHÔNG RA, và chỉ CHÍNH NGƯỜI tải lên mới lấy lại được. Bảng công là dữ
	 *    liệu người thật; một mã đếm tăng dần là ai cũng đọc được bảng của cửa hàng khác.
	 */
	const GIU_GIAY = 3600;

	public static function giu_bang( $u, $hang ) {
		$ma = wp_generate_password( 24, false, false );
		set_transient( 'vhcc_napdoc_' . $ma, array(
			'nv'   => self::ai( $u ),
			'hang' => $hang,
		), self::GIU_GIAY );
		return $ma;
	}

	public static function lay_bang( $u, $ma ) {
		$ma = preg_replace( '/[^A-Za-z0-9]/', '', (string) $ma );
		if ( '' === $ma ) { return null; }
		$d = get_transient( 'vhcc_napdoc_' . $ma );
		if ( ! is_array( $d ) || ! isset( $d['hang'] ) ) { return null; }
		if ( self::ai( $u ) !== ( isset( $d['nv'] ) ? $d['nv'] : '' ) ) { return null; }
		return $d['hang'];
	}

	/** Dấu người dùng để buộc tệp tạm vào đúng một người. */
	private static function ai( $u ) {
		$a = (array) $u;
		$x = isset( $a['ma_nv'] ) ? strtoupper( trim( (string) $a['ma_nv'] ) ) : '';
		if ( '' === $x ) { $x = isset( $a['name'] ) ? (string) $a['name'] : ''; }
		return $x;
	}

	/* ========================================================================== sổ ghép tên */

	/**
	 * SỔ GHÉP TÊN — "N.Kiệt" (trong bảng cũ) => mã NV, nhớ theo từng cơ sở.
	 *
	 * Anh Thắng 21/09/2026 chọn *"Màn ghép tên, nhớ cho lần sau"*.
	 *
	 * ⚠️ NHỚ THEO CƠ SỞ, không nhớ chung. Hai cửa hàng đều có một cô "Ngân" là chuyện thường,
	 *    và một sổ chung sẽ lặng lẽ rót công của cô này sang mã của cô kia.
	 * ⚠️ MỘT KHOÁ CHO TẤT CẢ CƠ SỞ, không mỗi cơ sở một khoá: cột `khoa` chỉ có 120 ký tự, và
	 *    một mã cơ sở dài là khoá bị cắt cụt — hai cơ sở khác nhau dùng chung một dòng.
	 */
	const KHOA_GHEP = 'napdoc_ghep';

	public static function so_ghep( $coso ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$tat = VHCC_Luong::cai_dat( self::KHOA_GHEP, array() );
		if ( ! is_array( $tat ) || ! isset( $tat[ $coso ] ) || ! is_array( $tat[ $coso ] ) ) {
			return array();
		}
		$ra = array();
		foreach ( $tat[ $coso ] as $t => $ma ) {
			$t  = trim( (string) $t );
			$ma = trim( (string) $ma );
			if ( '' !== $t && '' !== $ma ) { $ra[ $t ] = $ma; }
		}
		return $ra;
	}

	public static function luu_ghep( $u, $coso, $map ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return false; }
		$tat = VHCC_Luong::cai_dat( self::KHOA_GHEP, array() );
		if ( ! is_array( $tat ) ) { $tat = array(); }
		$cu = ( isset( $tat[ $coso ] ) && is_array( $tat[ $coso ] ) ) ? $tat[ $coso ] : array();
		foreach ( (array) $map as $t => $ma ) {
			$t  = trim( (string) $t );
			$ma = trim( (string) $ma );
			if ( '' === $t ) { continue; }
			/* Chọn lại ô trống = GỠ phép ghép, không phải giữ nguyên cái cũ. Không gỡ được thì
			   một phép ghép sai nằm lại trong sổ vĩnh viễn, mà màn thì trông như đã sửa xong. */
			if ( '' === $ma ) { unset( $cu[ $t ] ); } else { $cu[ $t ] = $ma; }
		}
		$tat[ $coso ] = $cu;
		VHCC_Luong::dat_cai_dat( self::KHOA_GHEP, $tat, $u );
		return true;
	}

	/** Chỉ giữ những khoá CÓ THẬT trong tệp — ô lạ gửi lên không được chui vào sổ. */
	private static function loc_map( $map, $nguoi ) {
		$ra = array();
		foreach ( (array) $map as $t => $ma ) {
			$t = trim( (string) $t );
			if ( '' === $t || ! in_array( $t, (array) $nguoi, true ) ) { continue; }
			$ma = trim( (string) $ma );
			if ( '' !== $ma ) { $ra[ $t ] = $ma; }
		}
		return $ra;
	}

	/**
	 * GỢI Ý MÃ NV CHO MỘT TÊN VIẾT TẮT.
	 *
	 * Bảng cũ ghi tên gọi, có khi kèm chữ đầu: `Ngân`, `N.Kiệt`, `K.Oanh`. Hồ sơ thì ghi đủ
	 * `Nguyễn Tuấn Kiệt`. Luật khớp: TIẾNG CUỐI phải trùng, và mỗi chữ viết tắt phía trước phải
	 * khớp (theo đúng thứ tự) với một tiếng đứng trước trong họ tên.
	 *
	 * 🔴 CHỈ GỢI Ý, KHÔNG BAO GIỜ TỰ LẤY. Ghép nhầm hai người là công của người này chui vào
	 *    bảng lương người kia — sai im lặng, và sai ra tiền. Vì vậy hàm này trả về CẢ DANH SÁCH
	 *    khớp; ra nhiều hơn một người thì màn để trống ô chọn, bắt người ta tự quyết.
	 *
	 * @return array danh sách mã NV khớp (có thể rỗng, có thể nhiều).
	 */
	public static function goi_y( $ten, $ds_nv ) {
		$phan = preg_split( '/[.\s_·,\-]+/u', trim( (string) $ten ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $phan ) { return array(); }
		$cuoi = VHCC_Luong::bo_chu( $phan[ count( $phan ) - 1 ] );
		if ( '' === $cuoi ) { return array(); }
		$tat = array();
		for ( $i = 0; $i < count( $phan ) - 1; $i++ ) {
			$c = VHCC_Luong::bo_chu( $phan[ $i ] );
			if ( '' !== $c ) { $tat[] = $c; }
		}

		$ra = array();
		foreach ( (array) $ds_nv as $hs ) {
			$ma = isset( $hs['ma_nv'] ) ? trim( (string) $hs['ma_nv'] ) : '';
			if ( '' === $ma ) { continue; }
			/* Tên trong tệp CHÍNH LÀ mã NV -> khớp thẳng, khỏi đoán. */
			if ( 0 === strcasecmp( $ma, trim( (string) $ten ) ) ) { return array( $ma ); }
			$tu = preg_split( '/\s+/u', trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ),
				-1, PREG_SPLIT_NO_EMPTY );
			if ( ! $tu ) { continue; }
			if ( VHCC_Luong::bo_chu( $tu[ count( $tu ) - 1 ] ) !== $cuoi ) { continue; }
			$truoc = array();
			for ( $i = 0; $i < count( $tu ) - 1; $i++ ) { $truoc[] = VHCC_Luong::bo_chu( $tu[ $i ] ); }
			if ( self::khop_tat( $tat, $truoc ) ) { $ra[] = $ma; }
		}
		return $ra;
	}

	/** Mỗi chữ viết tắt khớp một tiếng đứng trước, THEO THỨ TỰ, không dùng lại tiếng đã khớp. */
	private static function khop_tat( $tat, $truoc ) {
		$j = 0;
		foreach ( $tat as $t ) {
			$thay = false;
			while ( $j < count( $truoc ) ) {
				$w = $truoc[ $j ];
				$j++;
				if ( $w === $t || ( 1 === strlen( $t ) && '' !== $w && $w[0] === $t ) ) {
					$thay = true;
					break;
				}
			}
			if ( ! $thay ) { return false; }
		}
		return true;
	}

	/* ============================================================================== đọc ô */

	/** "BẢNG CHẤM CÔNG THÁNG 4/2026" -> "2026-04". '' nếu không thấy. */
	public static function tim_thang( $dong ) {
		foreach ( array_slice( (array) $dong, 0, 6 ) as $d ) {
			foreach ( (array) $d as $o ) {
				$s = trim( (string) $o );
				if ( '' === $s ) { continue; }
				if ( preg_match( '#TH[ÁA]NG\s*(\d{1,2})\s*[/\-]\s*(\d{4})#iu', $s, $m ) ) {
					$t = (int) $m[1];
					if ( $t >= 1 && $t <= 12 ) {
						return $m[2] . '-' . str_pad( (string) $t, 2, '0', STR_PAD_LEFT );
					}
				}
			}
		}
		return '';
	}

	/** Chỉ số dòng có "Check in". null nếu không thấy. */
	private static function tim_dong_cot( $dong ) {
		foreach ( (array) $dong as $i => $d ) {
			foreach ( (array) $d as $o ) {
				if ( 'check in' === strtolower( trim( (string) $o ) ) ) { return $i; }
			}
		}
		return null;
	}

	/**
	 * Các cụm cột của từng người.
	 *
	 * 🔴 DÒ "Check in" TỪ CHÍNH DÒNG CỘT, không cộng +3 từ cụm trước. Bảng của người ta có thể
	 *    chèn thêm một cột ghi chú giữa chừng, và mọi phép cộng cố định lệch hết — lệch kiểu ấy
	 *    không báo lỗi, nó chỉ lấy nhầm ô và cả tháng sai giờ.
	 *
	 * @return array [ ['cot'=>int, 'ten'=>string, 'nhan'=>string] ]
	 */
	private static function tim_cum( $d_ten, $d_cot ) {
		$ra = array();
		$ten_gan = '';
		foreach ( (array) $d_cot as $c => $o ) {
			$t = trim( (string) ( isset( $d_ten[ $c ] ) ? $d_ten[ $c ] : '' ) );
			/* Tên chỉ ghi ở cột đầu cụm (ô gộp) — nhớ lại tên gần nhất cho hai ô sau. */
			if ( '' !== $t && '0' !== $t ) { $ten_gan = $t; }
			if ( 'check in' !== strtolower( trim( (string) $o ) ) ) { continue; }
			if ( '' === $ten_gan ) { continue; }
			$ra[] = array( 'cot' => (int) $c, 'ten' => self::ten_goc( $ten_gan ), 'nhan' => $ten_gan );
		}
		return $ra;
	}

	/**
	 * "K.Oanh (LT)" -> "K.Oanh".
	 *
	 * Anh Thắng 21/09/2026: hai cụm là HAI CA của CÙNG một người. Nên phần trong ngoặc bị bỏ để
	 * hai cụm gom về một tên — và từ đó về một mã NV.
	 *
	 * ⚠️ CHỈ BỎ NGOẶC Ở CUỐI. Bỏ mọi thứ trong ngoặc ở bất kỳ đâu thì "Nguyễn (Bé) Hai" cụt mất
	 *    phần giữa, và hai người khác nhau có thể gom nhầm về một.
	 */
	public static function ten_goc( $s ) {
		$t = trim( (string) $s );
		$t = preg_replace( '/\s*\([^()]*\)\s*$/u', '', $t );
		return trim( $t );
	}

	/** Ô cột đầu -> số ngày, hoặc null (Lễ · Biên Bản · Tổng · rỗng). */
	private static function so_ngay( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s || ! preg_match( '/^\d{1,2}$/', $s ) ) { return null; }
		$n = (int) $s;
		return ( $n >= 1 && $n <= 31 ) ? $n : null;
	}

	/**
	 * Ô giờ -> giây trong ngày, hoặc null.
	 *
	 * 🔴 `0` LÀ Ô TRỐNG, KHÔNG PHẢI NỬA ĐÊM. Bảng của anh Thắng điền `0` vào mọi ô không có ca.
	 *    Đọc nó thành 00:00:00 là mỗi người tự nhiên có một lượt chấm lúc nửa đêm ở mọi ngày
	 *    nghỉ — và bảng công đầy những ngày công 0 giờ mà nhìn thì tưởng có đi làm.
	 */
	private static function gio( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s ) { return null; }

		$giay = null;
		/* Excel có thể trả về ô giờ dưới dạng số thập phân của một ngày (0.5 = 12:00). */
		if ( preg_match( '/^\d*\.\d+$/', $s ) ) {
			$giay = (int) round( (float) $s * 86400 );
		} elseif ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
			$g = (int) $m[1];
			$p = (int) $m[2];
			$y = isset( $m[3] ) ? (int) $m[3] : 0;
			if ( $g > 23 || $p > 59 || $y > 59 ) { return null; }
			$giay = $g * 3600 + $p * 60 + $y;
		} else {
			return null;
		}

		/* 🔴 CHẶN Ở ĐÂY, SAU KHI ĐÃ QUY VỀ GIÂY — không chặn theo mặt chữ.
		   Bản đầu liệt kê mấy dạng chữ (`0`, `00:00`, `00:00:00`) và tưởng thế là đủ. Phá thử
		   cho thấy bỏ hẳn dòng ấy mà bài vẫn XANH: chuỗi `0` trơn không khớp khuôn giờ nên rơi
		   về null hộ. Rồi phép thử mới lộ ra dạng còn sót: ô số `0.0` của Excel — nó KHỚP nhánh
		   thập phân, ra 0 giây, và thành một lượt chấm lúc nửa đêm.
		   Quy về giây rồi mới chặn thì mọi dạng viết của số không đều rơi về một chỗ.

		   ⚠️ CÁI GIÁ: ai đó chấm công đúng 00:00:00 thật thì cũng bị bỏ. Chấp nhận được — trong
		      bảng này `0` nghĩa là KHÔNG CÓ CA, và điều đó đúng với hàng nghìn ô, còn lượt chấm
		      đúng nửa đêm thì chưa từng thấy một ô nào. */
		return ( $giay > 0 ) ? $giay : null;
	}

	private static function ngay_that( $ngay ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ngay ) );
		return checkdate( $m, $d, $y );
	}
}
