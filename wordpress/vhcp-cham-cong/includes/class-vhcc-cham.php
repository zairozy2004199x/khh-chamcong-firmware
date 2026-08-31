<?php
/**
 * BẢNG CHẤM CÔNG · CỜ CẦN KIỂM · TĂNG CƯỜNG · QUY ĐỔI CƠ SỞ · THỐNG KÊ ĐẨY.
 *
 * ⚠️ Lớp này CHỈ ĐỌC bảng `cham_cong`, không sửa giờ. Sửa giờ chấm công chỉ có đúng hai đường:
 *    cổng nhận từ máy (VHCC_Nhan) và chấm công online (VHCC_Online). Mở thêm đường thứ ba để
 *    "sửa cho nhanh" là mở đường sửa lương bằng tay mà không có dấu vết.
 *    Muốn ghi chú một ngày sai thì dùng CỜ (bảng `ghi_chu`) — nó nằm cạnh, không đè lên giờ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Cham {

	/**
	 * Bảng chấm công của một cơ sở trong một tháng, kèm cờ cần kiểm.
	 * Bản dịch `getSheetDataVaFlags` — trả CẢ HAI trong một lượt vì giao diện hiện chung một bảng;
	 * gọi hai lượt là hai lần đọc cho một màn hình.
	 */
	/**
	 * Số PHÚT làm của một lượt — bản dịch `_workMin` của Index.html.
	 *
	 * 🔴 RA < VÀO THÌ TRẢ NULL, KHÔNG CỘNG 24 GIỜ. Đây là chỗ khác có chủ ý với
	 *    `VHCC_Luong::phut_ca()`, và khác vì hai màn hỏi hai câu khác nhau:
	 *
	 *      · Bảng lương hỏi *"trả bao nhiêu tiền"* — ca đêm là ca thật, phải cộng trọn vòng 24h,
	 *        nếu không thì số âm trừ thẳng vào lương người ta.
	 *      · Bảng quản trị công hỏi *"dòng nào sai"* — mà ca đêm ở đây đã được trải phẳng sang
	 *        hàng `-CD` từ trước, nên ra < vào KHÔNG còn là ca đêm nữa: nó là DẤU HIỆU SAI
	 *        (máy ghi nhầm, bù tay nhầm). Tự cộng 24h ở đây là lặng lẽ chữa lành một con số
	 *        đáng lẽ phải đập vào mắt người đang soát.
	 *
	 *    Nên: null, và màn hình hiện "—" để người ta nhìn thấy mà mở ra xem.
	 */
	public static function phut_lam( $vao_giay, $ra_giay ) {
		if ( null === $vao_giay || '' === $vao_giay || null === $ra_giay || '' === $ra_giay ) { return null; }
		$d = (int) $ra_giay - (int) $vao_giay;
		if ( $d < 0 ) { return null; }
		return (int) round( $d / 60 );
	}

	/**
	 * Gom tổng theo NGƯỜI — bản dịch `ccGomTong`.
	 *
	 * ⚠️ `ngay` đếm theo NGÀY RIÊNG BIỆT, không đếm số dòng: một người có hàng chính và hàng
	 *    `-CD` trong cùng ngày vẫn là MỘT ngày công. Đếm dòng là thổi số ngày công lên.
	 * ⚠️ `thieu` chỉ đếm lượt CÓ VÀO mà không có ra. Dòng trống hẳn (không vào không ra) không
	 *    phải "quên check-out" — nó là ngày không đi làm.
	 */
	public static function gom_tong( $hang ) {
		$by = array();
		foreach ( (array) $hang as $r ) {
			$ma = (string) $r['maNV'];
			if ( ! isset( $by[ $ma ] ) ) {
				$by[ $ma ] = array( 'maNV' => $ma, 'hoTen' => (string) $r['hoTen'],
					'ngayTap' => array(), 'phut' => 0, 'thieu' => 0 );
			}
			if ( '' === $by[ $ma ]['hoTen'] ) { $by[ $ma ]['hoTen'] = (string) $r['hoTen']; }
			$by[ $ma ]['ngayTap'][ (string) $r['ngay'] ] = 1;
			if ( null !== $r['phut'] ) { $by[ $ma ]['phut'] += (int) $r['phut']; }
			elseif ( '' !== $r['vao'] && '' === $r['ra'] ) { $by[ $ma ]['thieu']++; }
		}
		$ra = array();
		foreach ( $by as $o ) {
			$o['ngay'] = count( $o['ngayTap'] );
			unset( $o['ngayTap'] );
			$ra[] = $o;
		}
		usort( $ra, function ( $a, $b ) { return strcasecmp( $a['hoTen'], $b['hoTen'] ); } );
		return $ra;
	}

	/**
	 * LƯỚI CẢ THÁNG -> BẢNG ĐỂ XUẤT RA .xlsx (cơ sở tính THEO GIỜ).
	 *
	 * Anh Thắng 31/08/2026: *"bổ sung xuất bảng công ra"*. Cơ sở theo giờ vốn đã có nút xuất,
	 * nhưng tệp ấy là CHI TIẾT TỪNG CA (ca nào, từ mấy giờ, mấy tiếng) — không phải cái lưới
	 * cả tháng đang bày trên màn. Người ta gửi đi đối chiếu là gửi cái lưới.
	 *
	 * 🔴 HÀM THUẦN — cùng lý do với `VHCC_Luong::to_luoi_vp()`: cùng một `$b` đi vào màn hình
	 *    và đi vào tệp, nên bộ thử đối chiếu được hai bên.
	 *
	 * 🔴 Ô LÀ SỐ GIỜ THẬP PHÂN (7.5), KHÔNG PHẢI CHUỖI "7h30". Màn hình in `7h30` cho dễ đọc,
	 *    nhưng đó là CHỮ: cả cột thành chữ thì Excel thôi cộng, mà cầm bảng công không cộng
	 *    được thì cầm làm gì. Đơn vị nói rõ ngay ở tên trang và ở dòng đầu tiêu đề.
	 *
	 * ⚠️ HÀNG PHỤ (hậu tố `-CD` ca đêm, `-TC` tăng cường) CỘNG VÀO Ô, và liệt kê riêng ở trang
	 *    "Ô cần soi". Bỏ chúng ra khỏi ô là tổng của tệp hụt so với tổng trên màn.
	 *
	 * @param array $b Kết quả `bang_cham_cong()`.
	 * @return array Danh sách trang cho `VHCC_Xuat::xlsx()`.
	 */
	public static function to_luoi_gio( $b ) {
		if ( empty( $b['ok'] ) ) { return array(); }
		$tt  = (string) $b['thang'];
		$moc = strtotime( $tt . '-01 00:00:00 UTC' );
		if ( false === $moc ) { return array(); }
		$so_ngay = (int) gmdate( 't', $moc );
		$thu_vn  = array( 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' );

		/* Gom [mã][ngày] -> phút, và giữ riêng phần hậu tố để trang "Ô cần soi" nói được. */
		$o    = array();
		$ten  = array();
		$soi  = array( array( 'Ngày', 'Thứ', 'Mã NV', 'Họ tên', 'Hàng', 'Giờ vào', 'Giờ ra',
			'Số giờ', 'Cần soi vì' ) );
		foreach ( (array) $b['hang'] as $r ) {
			$ma = (string) $r['maNV'];
			$ng = (int) substr( (string) $r['ngay'], 8, 2 );
			if ( ! isset( $ten[ $ma ] ) || '' === $ten[ $ma ] ) { $ten[ $ma ] = (string) $r['hoTen']; }
			if ( $ng < 1 || $ng > $so_ngay ) { continue; }
			if ( ! isset( $o[ $ma ][ $ng ] ) ) { $o[ $ma ][ $ng ] = 0; }
			if ( null !== $r['phut'] ) { $o[ $ma ][ $ng ] += (int) $r['phut']; }

			$ht  = ( '' !== (string) $r['hauTo'] ) ? (string) $r['hauTo'] : '';
			$vi  = array();
			if ( '' !== $r['vao'] && '' === $r['ra'] ) { $vi[] = 'THIẾU giờ ra — quên bấm lúc về'; }
			elseif ( '' === $r['vao'] && '' !== $r['ra'] ) { $vi[] = 'THIẾU giờ vào — chỉ có lượt bấm ra'; }
			elseif ( null === $r['phut'] && '' !== $r['vao'] && '' !== $r['ra'] ) {
				$vi[] = 'giờ ra SỚM HƠN giờ vào — dấu hiệu ghi sai';
			}
			if ( '' !== $ht ) { $vi[] = 'hàng phụ -' . $ht . ' (đã cộng vào ô của ngày ấy)'; }
			if ( $vi ) {
				$w = (int) gmdate( 'w', strtotime( (string) $r['ngay'] . ' 00:00:00 UTC' ) );
				$soi[] = array( (string) $r['ngay'], $thu_vn[ $w ], VHCC_Xuat::chu( $ma ),
					(string) $r['hoTen'], ( '' !== $ht ? '-' . $ht : 'chính' ),
					(string) $r['vao'], (string) $r['ra'],
					( null === $r['phut'] ? '' : round( $r['phut'] / 60, 2 ) ),
					implode( ' · ', $vi ) );
			}
		}

		$dau = array( 'Mã NV', 'Họ tên' );
		for ( $i = 1; $i <= $so_ngay; $i++ ) {
			$w = (int) gmdate( 'w', strtotime( sprintf( '%s-%02d 00:00:00 UTC', $tt, $i ) ) );
			$dau[] = $i . ' ' . $thu_vn[ $w ];
		}
		$dau[] = 'TỔNG GIỜ';

		/* Thứ tự hàng: theo TÊN, cùng thước với `gom_tong()` — để tệp và màn xếp giống nhau. */
		$ma_ds = array_keys( $ten );
		usort( $ma_ds, function ( $x, $y ) use ( $ten ) {
			return strcasecmp( $ten[ $x ], $ten[ $y ] );
		} );

		$luoi = array( $dau );
		foreach ( $ma_ds as $ma ) {
			$hang = array( VHCC_Xuat::chu( $ma ), $ten[ $ma ] );
			$phut = 0;
			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				if ( ! isset( $o[ $ma ][ $i ] ) ) { $hang[] = ''; continue; }
				$phut  += (int) $o[ $ma ][ $i ];
				$hang[] = round( $o[ $ma ][ $i ] / 60, 2 );
			}
			$hang[] = round( $phut / 60, 2 );
			$luoi[] = $hang;
		}

		if ( 1 === count( $soi ) ) {
			$soi[] = array( '', '', '', '', '', '', '', '', 'Cả tháng không có ô nào cần soi.' );
		}
		return array(
			array( 'ten' => 'Lưới cả tháng (giờ)', 'hang' => $luoi ),
			array( 'ten' => 'Ô cần soi',           'hang' => $soi ),
		);
	}

	/**
	 * Bảng chấm công một cơ sở / một tháng — dữ liệu cho màn quản trị công cơ sở.
	 *
	 * Trả về ĐÚNG những gì tab "Chấm công" của bản Apps Script hiện: từng lượt một (ngày, mã,
	 * tên, hàng, vào, ra, giờ làm) và bảng tổng theo người (ngày công, ngày thiếu giờ ra, tổng
	 * giờ). Hai bảng đi từ CÙNG một mảng `hang` — không có công thức thứ hai, đúng như bản gốc
	 * ghi trong chú thích của nó.
	 */
	public static function bang_cham_cong( $u, $coso, $thang ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		$hang = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			$hang[] = array(
				'ngay' => $r['ngay'], 'maNV' => $r['ma_nv'], 'hauTo' => (string) $r['hau_to'],
				'hoTen' => $r['ho_ten'],
				'vao' => VHCC_DB::hhmmss( $r['gio_vao_giay'] ),
				'ra'  => VHCC_DB::hhmmss( $r['gio_ra_giay'] ),
				/* Giây THÔ đi kèm. `vao`/`ra` ở trên đã bị `hhmmss` gói về trong một ngày
				   (`% 86400`), nên hàng ca đêm ra 06:00 hôm sau trông y hệt 06:00 hôm nay — tách
				   ca từ chuỗi ấy là mất đúng ca đêm. Phép tách phải ăn giây thô. */
				'vaoGiay' => $r['gio_vao_giay'],
				'raGiay'  => $r['gio_ra_giay'],
				'phut' => self::phut_lam( $r['gio_vao_giay'], $r['gio_ra_giay'] ),
				'ghiChu' => isset( $r['ghi_chu'] ) ? (string) $r['ghi_chu'] : '',
				'nguon'  => isset( $r['nguon'] ) ? (string) $r['nguon'] : '',
			);
		}
		return array( 'ok' => true, 'coSo' => $coso, 'thang' => $tt, 'hang' => $hang,
			'tong' => self::gom_tong( $hang ),
			'co' => self::ds_ghi_chu( $u, $coso, $tt ) );
	}

	/**
	 * NGƯỜI NÀY THÁNG NÀY CÒN CHẤM Ở CƠ SỞ NÀO KHÁC KHÔNG.
	 *
	 * Anh Thắng 27/08/2026: *"nhân viên mà làm từ 2 cơ sở trở lên thì cũng nhớ ghép lại giúp
	 * anh"*. Trước đó lưới chỉ đọc đúng một cơ sở, nên một người chạy hai nơi thì ở lưới cơ sở
	 * A những ngày làm tại B là ô TRỐNG — nhìn y hệt ngày nghỉ. Người rà bảng đi tìm "sao hôm
	 * ấy nghỉ mà không xin phép", còn người ấy thì đang đứng ở cơ sở kia.
	 *
	 * 🔴 CHỈ ĐỂ NHÌN, KHÔNG CỘNG VÀO TIỀN. Trả về riêng một mảng, không trộn vào `hang` —
	 *    lương tính THEO CƠ SỞ (đơn giá, khung ca, cách tính công đều của riêng cơ sở đó), nên
	 *    cộng giờ của cơ sở B vào bảng cơ sở A là trả sai tiền cho cả hai nơi. Lưới bày ra để
	 *    biết hôm ấy người ta có đi làm, còn công của ngày ấy thuộc về bảng của cơ sở kia.
	 *
	 * ⚠️ KHÔNG gác quyền ở đây. Hàm chỉ trả về CƠ SỞ và SỐ GIỜ của chính những mã đã có mặt
	 *    trong lưới — nghĩa là người gọi đã qua cửa `co_quyen_coso` cho cơ sở đang xem rồi.
	 *    Đổi lại, người gọi PHẢI truyền danh sách mã lấy từ chính lưới, đừng truyền mã tuỳ ý.
	 *
	 * Trả: [ MÃ ][ số ngày ] => array( 'coso' => …, 'phut' => …, 'vao' => …, 'ra' => … )
	 * Một người một ngày chấm ở hai nơi khác nữa thì giữ nơi có GIỜ NHIỀU NHẤT — ô chỉ đủ chỗ
	 * cho một dòng, và nơi làm nhiều hơn là nơi đáng nói tới.
	 */
	public static function ngay_o_coso_khac( $ds_ma, $coso, $tt ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$ma   = array();
		foreach ( (array) $ds_ma as $x ) {
			$x = trim( (string) $x );
			if ( '' !== $x ) { $ma[ strtoupper( $x ) ] = true; }
		}
		if ( ! $ma || '' === $coso || '' === (string) $tt ) { return array(); }
		/* Dựng chỗ trống cho câu truy vấn theo ĐÚNG số mã, đừng nối chuỗi mã vào câu lệnh. */
		$ds  = array_keys( $ma );
		$cho = implode( ',', array_fill( 0, count( $ds ), '%s' ) );
		/* 🔴 LOẠI CẢ CHÙM, KHÔNG CHỈ MÃ CHÍNH. Cơ sở đã ghép vào bảng này (ca đêm `SETUP_VP` của
		   Văn phòng chẳng hạn) KHÔNG phải "cơ sở khác": công của nó đã nằm trong chính bảng đang
		   xem. Để nó lọt vào đây là ngày ấy vừa được cộng vào TỔNG, vừa hiện thêm một dòng xám
		   nói "chỗ khác" — người đọc thấy hai lần một ngày công và không biết tin cái nào. */
		$chum = method_exists( 'VHCC_Luong', 'chum_cua' )
			? VHCC_Luong::chum_cua( $coso ) : array( $coso );
		if ( ! $chum ) { $chum = array( $coso ); }
		$cho_cs = implode( ',', array_fill( 0, count( $chum ), '%s' ) );
		$sql = 'SELECT ngay, ma_nv, coso, gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE ngay LIKE %s AND coso NOT IN (' . $cho_cs . ') AND hau_to = %s'
			. ' AND UPPER(ma_nv) IN (' . $cho . ') ORDER BY ngay, ma_nv';
		$tham = array_merge( array( $tt . '-%' ), $chum, array( '' ), $ds );
		$out  = array();
		foreach ( VHCC_DB::rows( $wpdb->prepare( $sql, $tham ) ) as $r ) {
			$m = strtoupper( trim( (string) $r['ma_nv'] ) );
			$n = (int) substr( (string) $r['ngay'], 8, 2 );
			$p = self::phut_lam( $r['gio_vao_giay'], $r['gio_ra_giay'] );
			if ( isset( $out[ $m ][ $n ] ) && (int) $out[ $m ][ $n ]['phut'] >= (int) $p ) { continue; }
			$out[ $m ][ $n ] = array(
				'coso' => VHCC_NhanSu::chuan_coso( $r['coso'] ),
				'phut' => $p,
				'vao'  => VHCC_DB::hhmmss( $r['gio_vao_giay'] ),
				'ra'   => VHCC_DB::hhmmss( $r['gio_ra_giay'] ) );
		}
		return $out;
	}

	/**
	 * NGƯỜI NÀY THÁNG NÀY ĐƯỢC BAO NHIÊU Ở MỖI CƠ SỞ KHÁC.
	 *
	 * Anh Thắng 27/08/2026: *"phải hiện rõ cơ sở chính bao nhiêu công, cơ sở thứ 2 bao nhiêu
	 * công"*.
	 *
	 * `ngay_o_coso_khac()` mới trả lời được "hôm ấy người ta ở đâu" — đủ để ô khỏi trông như
	 * ngày nghỉ, nhưng chưa trả lời được câu anh hỏi. Hàm này cộng lại thành CON SỐ CỦA THÁNG.
	 *
	 * 🔴 TÍNH BẰNG CÔNG THỨC CỦA CHÍNH CƠ SỞ KIA, KHÔNG PHẢI CỦA CƠ SỞ ĐANG XEM.
	 *    Mỗi cơ sở có khung ca, mốc bậc thang, cách xử ca đêm riêng — và một cơ sở còn có thể
	 *    tính THEO GIỜ trong khi cơ sở đang xem tính THEO CÔNG. Đem công thức chỗ này áp lên giờ
	 *    chỗ kia là ra một con số trông rất giống công mà không phải công của ai cả; tệ hơn là
	 *    nó cộng được, nên sẽ có người cộng.
	 *
	 * 🔴 CƠ SỞ TÍNH THEO GIỜ THÌ TRẢ `cong = null`, KHÔNG trả 0. Không có "công" để mà nói, và
	 *    số 0 ở ô ấy đọc thành "làm mà không được công" — sai hẳn nghĩa. Màn phải hiện giờ.
	 *
	 * Trả: [ MÃ ][ mã cơ sở ] => array( donVi, cong|null, gio, soNgay )
	 */
	public static function tong_o_coso_khac( $ds_ma, $coso, $tt ) {
		$theo_ngay = self::ngay_o_coso_khac( $ds_ma, $coso, $tt );
		if ( ! $theo_ngay ) { return array(); }

		$cs_ds = array();
		foreach ( $theo_ngay as $dsn ) {
			foreach ( $dsn as $x ) {
				if ( '' !== (string) $x['coso'] ) { $cs_ds[ (string) $x['coso'] ] = true; }
			}
		}
		$out = array();
		foreach ( array_keys( $cs_ds ) as $cs2 ) {
			/* Gọi engine ĐÚNG MỘT LẦN cho mỗi cơ sở kia rồi tra ra, đừng gọi cho từng người:
			   một cơ sở 24 người là 24 lượt đọc cả tháng cho cùng một bảng. */
			$theo_cong = ( 'cong' === VHCC_Luong::cach_tinh( $cs2 ) );
			$bang = array();
			if ( $theo_cong ) {
				$b = VHCC_Luong::vp_bang_cong_va_luong( $cs2, $tt );
				foreach ( (array) $b['rows'] as $r ) {
					$bang[ strtoupper( (string) $r['ma'] ) ] = (float) $r['tong'];
				}
			}
			foreach ( $theo_ngay as $ma => $dsn ) {
				$phut = 0;
				$so_ngay = 0;
				foreach ( $dsn as $x ) {
					if ( (string) $x['coso'] !== $cs2 ) { continue; }
					$phut += (int) $x['phut'];
					$so_ngay++;
				}
				if ( ! $so_ngay ) { continue; }
				$out[ $ma ][ $cs2 ] = array(
					'donVi'  => $theo_cong ? 'cong' : 'gio',
					'cong'   => ( $theo_cong && isset( $bang[ $ma ] ) ) ? $bang[ $ma ] : null,
					'gio'    => round( $phut / 60, 1 ),
					'soNgay' => $so_ngay,
				);
			}
		}
		return $out;
	}

	/** Phút -> "8h 30m". Bản dịch `_fmtHrsTxt`; null -> "—" để dấu hiệu sai lộ ra. */
	public static function chu_gio( $phut ) {
		if ( null === $phut || '' === $phut ) { return '—'; }
		$p = (int) $phut;
		$h = intdiv( $p, 60 );
		$m = $p % 60;
		return $h . 'h' . ( $m ? ' ' . $m . 'm' : '' );
	}

	// ======================================================================= cờ cần kiểm

	public static function ds_ghi_chu( $u, $coso = '', $thang = '' ) {
		global $wpdb;
		$dk = array( '1=1' );
		$tv = array();
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' !== $coso ) { $dk[] = 'LOWER(coso)=LOWER(%s)'; $tv[] = $coso; }
		if ( '' !== $thang ) {
			$tt = VHCC_Luong::tien_to_thang( $thang );
			if ( '' !== $tt ) { $dk[] = 'ngay LIKE %s'; $tv[] = $tt . '-%'; }
		}
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY tao_luc DESC';
		$out = array();
		foreach ( VHCC_DB::rows( $tv ? $wpdb->prepare( $sql, $tv ) : $sql ) as $r ) {
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Gắn một cờ "cần kiểm" lên một ngày của một người.
	 * ⚠️ Cờ KHÔNG đụng vào giờ. Nó là ghi chú nằm CẠNH, để người có quyền xem rồi tự quyết —
	 *    chứ không phải để app tự sửa công.
	 */
	public static function luu_ghi_chu( $u, $dat ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$ngay = trim( isset( $dat['ngay'] ) ? (string) $dat['ngay'] : '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		$noi_dung = trim( isset( $dat['ghi_chu'] ) ? (string) $dat['ghi_chu'] : '' );
		if ( '' === $noi_dung ) {
			return array( 'ok' => false, 'error' => 'Cờ rỗng thì không nói lên điều gì — ghi rõ cần kiểm gì.' );
		}
		/* Mã cờ. Có sẵn = đang SỬA một cờ cũ; trống = cờ MỚI, phải cấp mã chưa ai dùng.
		   🔴 Chỗ này từng là lỗi im lặng nặng nhất của cả hệ: mã tự sinh chỉ có 900 giá trị trong
		      một giây, mà bên dưới lại tra `flag_id` rồi thấy trùng thì coi là "sửa cờ cũ" và ĐÈ
		      LÊN cờ của người khác — khác ngày, khác người, khác nội dung — vẫn trả `ok:true`.
		      Một cờ cần kiểm biến mất mà không ai biết. */
		$id = isset( $dat['flag_id'] ) ? trim( (string) $dat['flag_id'] ) : '';
		if ( '' === $id ) {
			$id = VHCC_DB::ma_moi( 'CO', 'ghi_chu', 'flag_id' );
			if ( '' === $id ) {
				return array( 'ok' => false, 'error' => 'Không cấp được mã cờ, thử lại giúp em.' );
			}
		}
		$ghi = array( 'flag_id' => $id, 'coso' => $coso, 'ngay' => $ngay,
			'ma_nv' => trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' ),
			'ho_ten' => trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' ),
			'ghi_chu' => $noi_dung,
			'nguoi_gan' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'trang_thai' => 'Cần kiểm', 'tao_luc' => current_time( 'mysql' ) );
		$cu = $wpdb->get_row( $wpdb->prepare(
			'SELECT id FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE flag_id=%s', $id ), ARRAY_A );
		if ( $cu ) { $wpdb->update( VHCC_DB::t( 'ghi_chu' ), $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( VHCC_DB::t( 'ghi_chu' ), $ghi ); }
		return array( 'ok' => true, 'flagId' => $id );
	}

	public static function xu_ly_ghi_chu( $u, $flag_id, $ket_luan = '' ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE flag_id=%s', trim( (string) $flag_id ) ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy cờ này.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		/* GIỮ nguyên nội dung cờ, chỉ thêm kết luận. Ghi đè nội dung là mất lý do vì sao nó được
		   gắn — mà đó là thứ duy nhất giải thích được một ngày công bất thường về sau. */
		$wpdb->update( VHCC_DB::t( 'ghi_chu' ), array(
			'trang_thai' => 'Đã xử lý',
			'ghi_chu' => $r['ghi_chu'] . ( '' !== trim( (string) $ket_luan )
				? "\n— kết luận: " . trim( (string) $ket_luan ) : '' ),
			'xu_ly_luc' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return array( 'ok' => true );
	}

	/**
	 * Ngày CÓ giờ vào mà THIẾU giờ ra — quên check-out.
	 * ⚠️ Chỉ CẢNH BÁO, không tự điền giờ ra. Điền là bịa giờ làm cho một ngày mà không ai biết
	 *    người ta làm bao lâu; mà cái đó thành tiền.
	 */
	public static function canh_bao_thieu_gio_ra( $u, $coso, $thang ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) { return array(); }
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array(); }
		$out = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			if ( null === $r['gio_vao_giay'] || '' === $r['gio_vao_giay'] ) { continue; }
			if ( null !== $r['gio_ra_giay'] && '' !== $r['gio_ra_giay'] ) { continue; }
			$out[] = array( 'ngay' => $r['ngay'], 'maNV' => $r['ma_nv'],
				'hauTo' => (string) $r['hau_to'], 'hoTen' => $r['ho_ten'],
				'vao' => VHCC_DB::hhmmss( $r['gio_vao_giay'] ) );
		}
		return $out;
	}

	// ======================================================================= tăng cường

	/**
	 * Người của cơ sở khác sang làm ở cơ sở này.
	 * ⚠️ Ngày đã KHOÁ (chốt kỳ) thì không khai thêm được — khai thêm vào kỳ đã chốt là số công
	 *    đổi sau khi bảng lương đã in ra.
	 */
	public static function them_tang_cuong( $u, $dat ) {
		global $wpdb;
		$den = VHCC_NhanSu::chuan_coso( isset( $dat['coso_den'] ) ? $dat['coso_den'] : '' );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $den ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở nhận người.' );
		}
		$ngay = trim( isset( $dat['ngay'] ) ? (string) $dat['ngay'] : '' );
		$ma   = trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Thiếu ngày hoặc mã NV.' );
		}
		$bang = VHCC_DB::t( 'tang_cuong' );
		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, khoa FROM $bang WHERE ngay=%s AND LOWER(coso_den)=LOWER(%s) AND ma_nv=%s",
			$ngay, $den, $ma ), ARRAY_A );
		if ( $cu && (int) $cu['khoa'] ) {
			return array( 'ok' => false, 'error' => 'Ngày này đã CHỐT KỲ — không sửa được nữa. '
				. 'Sửa sau khi chốt là số công đổi sau khi bảng lương đã in.' );
		}
		$hs = VHCC_NhanSu::ho_so( $ma );
		$ghi = array( 'ngay' => $ngay, 'coso_den' => $den, 'ma_nv' => $ma,
			'coso_goc' => $hs ? VHCC_NhanSu::chuan_coso( $hs['cua_hang'] ) : '',
			'ho_ten' => $hs ? (string) $hs['ho_ten'] : trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' ),
			'ghi_chu' => trim( isset( $dat['ghi_chu'] ) ? (string) $dat['ghi_chu'] : '' ),
			'nguoi_khai' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'tao_luc' => current_time( 'mysql' ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true );
	}

	/** CHỐT KỲ tăng cường của một (cơ sở, tháng). Chỉ Admin/Quản lý — chốt là không sửa được nữa. */
	public static function khoa_tang_cuong( $u, $coso, $thang, $khoa = true ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Chốt kỳ tăng cường — ' . VHCC_NhanSu::LOI_QT );
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		$so = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'tang_cuong' ) . ' SET khoa=%d'
			. ' WHERE LOWER(coso_den)=LOWER(%s) AND ngay LIKE %s',
			$khoa ? 1 : 0, $coso, $tt . '-%' ) );
		return array( 'ok' => true, 'so' => (int) $so, 'khoa' => (bool) $khoa );
	}

	public static function ds_tang_cuong( $coso, $thang ) {
		global $wpdb;
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array(); }
		return VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'tang_cuong' )
			. ' WHERE LOWER(coso_den)=LOWER(%s) AND ngay LIKE %s ORDER BY ngay, ho_ten',
			VHCC_NhanSu::chuan_coso( $coso ), $tt . '-%' ) );
	}

	// ======================================================================= quy đổi cơ sở

	/**
	 * Tên cơ sở máy khai -> tên cơ sở thật.
	 * ⚠️ Chỉ Admin/Quản lý: quy ước này dùng cho CẢ CHUỖI, và khai sai một dòng là chấm công của
	 *    một cơ sở chảy sang cơ sở khác.
	 */
	public static function luu_quy_doi_coso( $u, $tu, $den, $ghi_chu = '' ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Quy đổi cơ sở dùng cho cả chuỗi — ' . VHCC_NhanSu::LOI_QT );
		}
		$tu  = VHCC_NhanSu::chuan_coso( $tu );
		$den = VHCC_NhanSu::chuan_coso( $den );
		if ( '' === $tu ) { return array( 'ok' => false, 'error' => 'Thiếu tên cần quy đổi.' ); }
		if ( strtolower( $tu ) === strtolower( $den ) ) {
			return array( 'ok' => false, 'error' => 'Quy đổi về chính nó thì không có tác dụng gì.' );
		}
		$bang = VHCC_DB::t( 'quy_doi_coso' );
		/* Chặn chuỗi quy đổi A->B->C: bên đọc chỉ tra MỘT bước, nên chuỗi hai bước là im lặng sai. */
		$tiep = $wpdb->get_var( $wpdb->prepare( "SELECT den FROM $bang WHERE LOWER(tu)=LOWER(%s)", $den ) );
		if ( $tiep ) {
			return array( 'ok' => false, 'error' => '"' . $den . '" lại đang được quy đổi sang "'
				. $tiep . '". Quy đổi chỉ tra MỘT bước, nên chuỗi hai bước là sai im lặng — '
				. 'quy đổi "' . $tu . '" thẳng về "' . $tiep . '".' );
		}
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $bang WHERE LOWER(tu)=LOWER(%s)", $tu ), ARRAY_A );
		$ghi = array( 'tu' => $tu, 'den' => $den, 'ghi_chu' => trim( (string) $ghi_chu ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true );
	}

	public static function ds_quy_doi() {
		return VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'quy_doi_coso' ) . ' ORDER BY tu' );
	}

	// ======================================================================= thống kê đẩy

	/**
	 * Thống kê lượt máy đẩy lên: đếm theo cơ sở và theo nguồn.
	 * Đây là chỗ nhìn ra "cơ sở nào tự nhiên im" — mà im lặng chính là kiểu hỏng khó thấy nhất.
	 */
	public static function thong_ke_day( $u, $thang ) {
		global $wpdb;
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array(); }
		$out = array();
		foreach ( VHCC_DB::rows( $wpdb->prepare(
			'SELECT coso, nguon, COUNT(*) so, MIN(ngay) tu_ngay, MAX(ngay) den_ngay FROM '
			. VHCC_DB::t( 'cham_cong' ) . ' WHERE ngay LIKE %s GROUP BY coso, nguon ORDER BY coso',
			$tt . '-%' ) ) as $r ) {
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Dọn thống kê. ⚠️ CHỈ dọn bảng CHỜ GÁN đã xử lý xong — TUYỆT ĐỐI không chạm `cham_cong`.
	 *    Bên Apps Script `xoaThongKeDay` xoá sheet thống kê riêng; ở đây không có sheet đó, và
	 *    thống kê được tính trực tiếp từ chấm công nên "dọn thống kê" mà xoá chấm công là xoá
	 *    tiền lương. Nên hàm này cố ý làm việc KHÁC và hẹp hơn hẳn.
	 */
	public static function xoa_thong_ke_day( $u, $truoc_ngay ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Dọn dữ liệu — ' . VHCC_NhanSu::LOI_QT );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $truoc_ngay ) ) ) {
			return array( 'ok' => false, 'error' => 'Cần một ngày mốc dạng yyyy-MM-dd.' );
		}
		$so = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'cho_gan' ) . " WHERE da_chuyen <> '' AND nhan_luc < %s",
			trim( (string) $truoc_ngay ) . ' 00:00:00' ) );
		return array( 'ok' => true, 'so' => (int) $so,
			'ghiChu' => 'Chỉ dọn lượt CHỜ GÁN đã xử lý xong. Bảng chấm công không bị chạm.' );
	}
}
