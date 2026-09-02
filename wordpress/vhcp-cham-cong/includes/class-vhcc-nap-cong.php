<?php
/**
 * NẠP CÔNG TỪ .CSV BẢNG NGANG — đường đưa lịch sử chấm công của Sheets cũ vào MySQL.
 *
 * Anh Thắng 26/08/2026: *"Sao dữ liệu chấm công chưa vào, anh nạp rồi mà"*, rồi *"bổ sung thêm
 * nạp dữ liệu công nhé"*.
 *
 * 🔴 VÌ SAO ANH NẠP MÀ KHÔNG THẤY GÌ — và đây là chỗ dễ hiểu lầm nhất của cả hệ thống:
 *    Nút "Nạp .csv" đang có ở màn Hồ sơ nạp **SỔ NHÂN SỰ** (ai, mã gì, PIN gì, cơ sở nào). Nó
 *    không nạp một giờ công nào, và trước hôm nay cũng KHÔNG có đường nào nạp giờ công cả:
 *    bảng `cham_cong` chỉ nhận giờ từ máy chấm công và trạm online. Nạp xong 240 hồ sơ mà bảng
 *    công vẫn trắng là ĐÚNG như hệ thống được dựng — chỉ có điều không chỗ nào nói ra.
 *
 * DẠNG TỆP (đúng bản Sheets anh Thắng xuất — "Bảng chạy · Hệ Thống Chấm Công Cơ Sở")
 *
 *    dòng 1:  Họ và Tên | ID | 2026-07-01 |  |  |  |  | 2026-07-02 | …
 *    dòng 2:           |    | Giờ Vào    | Ảnh | Giờ Ra | Ảnh | Thời gian trong ngày | …
 *    dòng 3+: Nguyễn Tiến Đạt | MNLX1CTY0001 | 08:40:47 | | 17:00:24 | | 08:40 17:00 | …
 *
 *    Mỗi NGÀY chiếm một cụm 5 cột, và ngày chỉ ghi ở cột ĐẦU cụm — bốn ô sau để trống. Nên
 *    không đọc được tệp này bằng cách "lấy hàng tiêu đề làm tên cột" như mọi .csv khác.
 *
 * ⚠️ KHÔNG ĐOÁN CỤM 5 CỘT. Bản này dò vị trí "Giờ Vào"/"Giờ Ra" từ CHÍNH dòng 2, trong phạm vi
 *    của từng cụm. Sheets thêm bớt một cột ảnh là mọi phép cộng +2/+4 lệch hết, mà lệch kiểu đó
 *    thì không báo lỗi: nó lấy ô "Ảnh Checkin" (trống) làm giờ ra, và cả tháng thành thiếu giờ.
 *
 * ⚠️ ĐI QUA ĐÚNG `VHCC_Nhan::ghi_gio()`, KHÔNG VIẾT CÂU INSERT RIÊNG. Nhờ vậy nạp lại bao nhiêu
 *    lần cũng ra một kết quả, và luật "chỉ nới, không thu hẹp" (giờ máy đã ghi thì .csv cũ
 *    không xoá bớt được) chỉ có đúng một bản trong cả hệ thống.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NapCong {

	/** Nhãn nguồn — đứng cạnh 'may' / 'online' / 'bu'. Giống hệt đường kéo qua cầu nối. */
	const NGUON = 'sheet';

	/** Cụm của một ngày dài tối đa bao nhiêu cột (Giờ vào · Ảnh · Giờ ra · Ảnh · Thời gian). */
	const CUM_TOI_DA = 12;

	/* ==================================================================== đọc */

	/**
	 * Cả tệp -> danh sách phẳng. Gom mọi KHỐI trong tệp.
	 *
	 * 🔴 MỘT TỆP CÓ THỂ CHỨA NHIỀU BẢNG CHỒNG NHAU. Tệp thật anh Thắng gửi 26/08/2026 có hai:
	 *    tháng 7 ở trên (dòng 1–23), cách hai dòng trống, rồi tháng 8 ở dưới (dòng 26–50).
	 *    Bản đọc đầu tiên của em chỉ lấy hàng tiêu đề ĐẦU TIÊN rồi đọc thẳng tới cuối tệp —
	 *    nên toàn bộ tháng 8 bị dán nhãn tháng 7, mỗi người ra HAI giờ vào cho cùng một ngày,
	 *    và dòng tiêu đề của khối dưới thành một "nhân viên" tên ID. Không có gì báo lỗi cả:
	 *    mọi con số đều hợp lệ, chỉ sai người sai ngày.
	 *
	 * Hàm THUẦN: nhận mảng dòng, trả mảng bản ghi. Không chạm cơ sở dữ liệu, không cần WordPress
	 * — nên thử được thẳng trên tệp thật của anh Thắng bằng một lượt chạy php.
	 *
	 * @return array ok · thang_ds[] · so_khoi · nguoi[] · luot[] · canh[]
	 */
	public static function doc( $dong ) {
		$dong = array_values( (array) $dong );
		/* Sheets luôn kèm BOM ở ô đầu. Không gỡ thì mọi phép so tên cột đầu tiên đều trượt. */
		if ( isset( $dong[0][0] ) ) {
			$dong[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $dong[0][0] );
		}

		$moc_khoi = array();
		foreach ( $dong as $i => $h ) {
			if ( self::la_dong_tieu_de( $h ) ) { $moc_khoi[] = (int) $i; }
		}
		if ( ! $moc_khoi ) {
			return array( 'ok' => false,
				'error' => 'Không thấy dòng tiêu đề nào có "Họ và Tên" và "ID". '
					. 'Đây có phải tệp "Bảng chạy · Hệ Thống Chấm Công Cơ Sở" không?' );
		}

		$nguoi = array();
		$luot  = array();
		$canh  = array();
		$thang_ds = array();
		foreach ( $moc_khoi as $k => $tu ) {
			$den = isset( $moc_khoi[ $k + 1 ] ) ? $moc_khoi[ $k + 1 ] : count( $dong );
			$kq  = self::doc_khoi( array_slice( $dong, $tu, $den - $tu ), $tu );
			if ( empty( $kq['ok'] ) ) {
				$canh[] = 'Khối bắt đầu ở dòng ' . ( $tu + 1 ) . ': ' . $kq['error'];
				continue;
			}
			foreach ( $kq['nguoi'] as $ma => $ten ) { $nguoi[ $ma ] = $ten; }
			foreach ( $kq['luot'] as $x ) { $luot[] = $x; }
			foreach ( $kq['canh'] as $c ) { $canh[] = $c; }
			if ( ! in_array( $kq['thang'], $thang_ds, true ) ) { $thang_ds[] = $kq['thang']; }
		}
		if ( ! $luot && ! $nguoi ) {
			return array( 'ok' => false, 'error' => 'Đọc được tiêu đề nhưng không có dòng dữ liệu nào.',
				'canh' => $canh );
		}
		sort( $thang_ds );

		/* Cùng một TÊN mà hai MÃ khác nhau -> công của một người bị tách làm đôi, và không bảng
		   nào trong hệ thống ghép lại được. Tệp thật có đúng chuyện đó: "Nguyễn Hữu Thọ" mang cả
		   mã 2 lẫn mã 15. Phải kể ra, vì nhìn bảng công thì hai dòng ấy trông như hai người. */
		$theo_ten = array();
		foreach ( $nguoi as $ma => $ten ) {
			$k = self::gon( $ten );
			if ( '' === $k ) { continue; }
			$theo_ten[ $k ][] = $ma;
		}
		foreach ( $theo_ten as $ds_ma ) {
			if ( count( $ds_ma ) < 2 ) { continue; }
			$canh[] = '"' . $nguoi[ $ds_ma[0] ] . '" mang ' . count( $ds_ma ) . ' mã khác nhau ('
				. implode( ' · ', $ds_ma ) . ') — công sẽ nằm ở mấy dòng riêng, không tự gộp.';
		}

		return array( 'ok' => true, 'thang_ds' => $thang_ds, 'so_khoi' => count( $moc_khoi ),
			'nguoi' => $nguoi, 'luot' => $luot, 'canh' => $canh );
	}

	/** Dòng này có phải hàng tiêu đề mở đầu một khối không? */
	public static function la_dong_tieu_de( $h ) {
		$co_ten = false;
		$co_ma  = false;
		foreach ( (array) $h as $o ) {
			$s = self::gon( $o );
			if ( 'ho va ten' === $s || 'ho ten' === $s ) { $co_ten = true; }
			if ( 'id' === $s || 'ma nv' === $s || 'ma nhan vien' === $s ) { $co_ma = true; }
		}
		return ( $co_ten && $co_ma );
	}

	/**
	 * MỘT khối: dòng 0 = ngày, dòng 1 = tên cột, dòng 2+ = dữ liệu.
	 *
	 * @param int $lech số dòng của khối này tính từ đầu tệp — để câu cảnh báo chỉ đúng số dòng
	 *                  trong tệp gốc, chứ không phải số dòng trong khối.
	 */
	public static function doc_khoi( $dong, $lech = 0 ) {
		$dong = array_values( (array) $dong );
		if ( count( $dong ) < 3 ) {
			return array( 'ok' => false, 'error' => 'khối có ít hơn 3 dòng.' );
		}

		$d_ngay = (array) $dong[0];
		$d_cot  = (array) $dong[1];

		/* Cột Họ tên và cột ID — dò theo CHỮ, không đóng đinh 0 và 1: bản xuất khác có thể chèn
		   thêm cột STT ở đầu, mà chèn thêm thì đóng đinh sẽ đọc tên người làm mã nhân viên. */
		$c_ten = -1;
		$c_ma  = -1;
		foreach ( $d_ngay as $i => $o ) {
			$s = self::gon( $o );
			if ( -1 === $c_ten && ( 'ho va ten' === $s || 'ho ten' === $s ) ) { $c_ten = (int) $i; }
			if ( -1 === $c_ma && ( 'id' === $s || 'ma nv' === $s || 'ma nhan vien' === $s ) ) { $c_ma = (int) $i; }
		}
		if ( $c_ten < 0 || $c_ma < 0 ) {
			return array( 'ok' => false,
				'error' => 'Không thấy cột "Họ và Tên" và "ID" ở dòng đầu. '
					. 'Đây có phải tệp "Bảng chạy · Hệ Thống Chấm Công Cơ Sở" không?' );
		}

		/* Mốc đầu mỗi cụm = ô có NGÀY ở dòng 1. */
		$moc = array();
		foreach ( $d_ngay as $i => $o ) {
			$ng = self::ngay( $o );
			if ( '' !== $ng ) { $moc[] = array( 'cot' => (int) $i, 'ngay' => $ng ); }
		}
		if ( ! $moc ) {
			return array( 'ok' => false,
				'error' => 'Dòng đầu không có ngày nào đọc được. Cần dạng 2026-07-01 hoặc 01/07/2026.' );
		}

		/* Trong từng cụm, dò cột Giờ vào / Giờ ra theo tên ở dòng 2. Cụm cuối kéo tới hết dòng,
		   nhưng chặn ở CUM_TOI_DA để một tệp lỗi (thiếu mốc giữa) không nuốt cả trăm cột sau. */
		$canh = array();
		$cum  = array();
		foreach ( $moc as $k => $m ) {
			$het = isset( $moc[ $k + 1 ] ) ? $moc[ $k + 1 ]['cot'] : ( $m['cot'] + self::CUM_TOI_DA );
			$het = min( $het, $m['cot'] + self::CUM_TOI_DA );
			$c_vao = -1;
			$c_ra  = -1;
			for ( $i = $m['cot']; $i < $het; $i++ ) {
				$s = self::gon( isset( $d_cot[ $i ] ) ? $d_cot[ $i ] : '' );
				if ( '' === $s ) { continue; }
				if ( -1 === $c_vao && ( false !== strpos( $s, 'gio vao' ) || false !== strpos( $s, 'checkin' ) ) ) {
					if ( false === strpos( $s, 'anh' ) ) { $c_vao = $i; }
				}
				if ( -1 === $c_ra && ( false !== strpos( $s, 'gio ra' ) || false !== strpos( $s, 'checkout' ) ) ) {
					if ( false === strpos( $s, 'anh' ) ) { $c_ra = $i; }
				}
			}
			if ( $c_vao < 0 && $c_ra < 0 ) {
				$canh[] = 'Ngày ' . $m['ngay'] . ': không thấy cột Giờ vào / Giờ ra — bỏ qua cả ngày.';
				continue;
			}
			$cum[] = array( 'ngay' => $m['ngay'], 'vao' => $c_vao, 'ra' => $c_ra );
		}
		if ( ! $cum ) {
			return array( 'ok' => false, 'error' => 'Không đọc được cụm ngày nào.',
				'canh' => $canh );
		}

		/* Tháng của tệp: lấy theo ngày xuất hiện NHIỀU NHẤT, không lấy ngày đầu. Bảng tháng 7
		   thường có vài cột lẹm sang 30/06 hoặc 01/08, và lấy ngày đầu là gán nhầm cả tệp. */
		$dem = array();
		foreach ( $cum as $c ) {
			$t = substr( $c['ngay'], 0, 7 );
			$dem[ $t ] = isset( $dem[ $t ] ) ? $dem[ $t ] + 1 : 1;
		}
		arsort( $dem );
		$thang = (string) key( $dem );

		$nguoi = array();
		$luot  = array();
		for ( $r = 2; $r < count( $dong ); $r++ ) {
			$h   = (array) $dong[ $r ];
			$ten = trim( (string) ( isset( $h[ $c_ten ] ) ? $h[ $c_ten ] : '' ) );
			$ma  = trim( (string) ( isset( $h[ $c_ma ] ) ? $h[ $c_ma ] : '' ) );
			if ( '' === $ten && '' === $ma ) { continue; }          // dòng trống ngăn hai khối
			if ( '' === $ma ) {
				$canh[] = 'Dòng ' . ( $lech + $r + 1 ) . ' ("' . $ten . '"): không có ID — bỏ qua cả dòng. '
					. 'Không đoán mã theo tên: hai người trùng tên là gộp công của nhau.';
				continue;
			}
			$nguoi[ $ma ] = $ten;

			foreach ( $cum as $c ) {
				foreach ( array( 'vao', 'ra' ) as $o ) {
					if ( $c[ $o ] < 0 ) { continue; }
					$tho = isset( $h[ $c[ $o ] ] ) ? $h[ $c[ $o ] ] : '';
					$gio = self::gio( $tho, $o );
					if ( '' === $gio ) {
						if ( '' !== trim( (string) $tho ) ) {
							$canh[] = $ma . ' ' . $c['ngay'] . ' (' . $o . '): không đọc được giờ "'
								. trim( (string) $tho ) . '".';
						}
						continue;
					}
					$luot[] = array( 'ma' => $ma, 'ten' => $ten, 'ngay' => $c['ngay'],
						'o' => $o, 'gio' => $gio );
				}
			}
		}

		$ds_ngay = array();
		foreach ( $cum as $c ) { $ds_ngay[] = $c['ngay']; }

		return array( 'ok' => true, 'thang' => $thang, 'ngay_ds' => $ds_ngay,
			'nguoi' => $nguoi, 'luot' => $luot, 'canh' => $canh );
	}

	/* ==================================================================== nạp */

	/**
	 * Đọc rồi ghi vào bảng chấm công.
	 *
	 * @param bool $chi_xem true = chỉ đếm và kể, KHÔNG ghi gì. Mặc định là true — nạp đè cả
	 *                      tháng công của một cơ sở mà không cho xem trước là quá nguy.
	 */
	public static function nap( $u, $coso, $dong, $chi_xem = true, $ten_tep = '' ) {
		if ( ! VHCC_Vai::duoc( $u, 'nap_cong' ) ) {
			return array( 'ok' => false, 'error' => 'Nạp dữ liệu công cần quyền Quản lý trở lên.' );
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở để nạp vào.' );
		}
		$loi_ma = self::ma_coso_hop_le( $coso );
		if ( '' !== $loi_ma ) { return array( 'ok' => false, 'error' => $loi_ma ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}

		$d = self::doc( $dong );
		if ( empty( $d['ok'] ) ) { return $d; }

		/* Mã có trong tệp mà KHÔNG có hồ sơ -> vẫn nạp giờ, nhưng phải kể ra. Chặn hẳn thì một
		   người chưa khai hồ sơ làm kẹt cả tháng của cả cơ sở; im lặng thì công của họ nằm trong
		   bảng mà không ai biết là của ai. */
		$la = array();
		foreach ( $d['nguoi'] as $ma => $ten ) {
			if ( ! VHCC_NhanSu::ho_so( $ma ) ) { $la[ $ma ] = $ten; }
		}

		$ghi = 0;
		if ( ! $chi_xem ) {
			foreach ( $d['luot'] as $x ) {
				$giay = VHCC_DB::giay( $x['gio'] );
				if ( null === $giay ) { continue; }
				VHCC_Nhan::ghi_gio( $coso, $x['ngay'], $x['ma'], $x['ten'], $giay, '', self::NGUON );
				$ghi++;
			}
		}

		/* Số NGÀY riêng biệt có ít nhất một lượt — không đếm cột ngày của tiêu đề. Tệp có 31 cột
		   ngày mà cả tháng chỉ 12 ngày có người đi làm thì con số cần nhìn là 12. */
		$ngay_co = array();
		foreach ( $d['luot'] as $x ) { $ngay_co[ $x['ngay'] ] = 1; }

		/* Cơ sở CHƯA có trong hệ thống -> nạp thật là TẠO MỚI. Phải nói ra ở bước xem trước, kẻo
		   một mã gõ sai đẻ ra một cơ sở ma mang cả tháng công, mà nhìn bảng thì trông như thật. */
		$la_moi = ! in_array( $coso, VHCC_NhanSu::ds_coso(), true );

		/* Tên tệp nói cơ sở nào? Lệch với ô đang chọn là dấu hiệu nạp nhầm cửa hàng — cái sai này
		   hoàn toàn im lặng nếu không đối chiếu. */
		$cs_tep = self::coso_tu_ten_tep( $ten_tep );
		$lech_ten = ( '' !== $cs_tep && 0 !== strcasecmp( $cs_tep, $coso ) ) ? $cs_tep : '';

		return array( 'ok' => true, 'chi_xem' => (bool) $chi_xem, 'coSo' => $coso,
			'la_moi' => $la_moi, 'cs_tep' => $cs_tep, 'lech_ten' => $lech_ten,
			'thang' => implode( ' · ', $d['thang_ds'] ), 'thang_ds' => $d['thang_ds'],
			'so_khoi' => $d['so_khoi'], 'so_ngay' => count( $ngay_co ),
			'so_nguoi' => count( $d['nguoi'] ), 'so_luot' => count( $d['luot'] ),
			'da_ghi' => $ghi, 'la' => $la, 'canh' => $d['canh'] );
	}

	/* ==================================================================== phụ */

	/**
	 * ĐOÁN MÃ CƠ SỞ TỪ TÊN TỆP.
	 *
	 * Tên Google Sheets xuất ra có khuôn: `Bảng chạy - Hệ Thống Chấm Công Cơ Sở - CS_JP_SANBAY.csv`
	 * — phần sau dấu gạch cuối cùng chính là mã cơ sở.
	 *
	 * 🔴 Dùng để ĐỐI CHIẾU, không dùng để tự chọn thay người. Với hai chục cơ sở trong ô xổ
	 *    xuống, nạp nhầm cơ sở là chuyện rất dễ và hoàn toàn IM LẶNG: cả tháng công của cửa hàng
	 *    này chui vào sổ của cửa hàng kia, không câu nào báo. Đối chiếu tên tệp với ô đang chọn
	 *    là phép kiểm gần như miễn phí cho đúng cái sai đó.
	 *
	 * ⚠️ Cắt đuôi `_1`, `_2`… vì Google Drive thêm vào khi tải trùng tên. Nhưng đó là PHỎNG ĐOÁN
	 *    — một cơ sở tên thật là `TUTU_1` sẽ bị cắt oan. Nên hàm này chỉ đẻ ra một câu nhắc, và
	 *    người dùng vẫn là người quyết.
	 */
	public static function coso_tu_ten_tep( $ten ) {
		$t = trim( (string) $ten );
		$t = preg_replace( '/\.(csv|tsv|txt)$/i', '', $t );
		if ( '' === $t ) { return ''; }
		$phan = preg_split( '/\s+-\s+/u', $t );
		$t = trim( (string) end( $phan ) );
		$t = preg_replace( '/_\d+$/', '', $t );        // đuôi Google Drive thêm khi trùng tên
		return VHCC_NhanSu::chuan_coso( $t );
	}

	/**
	 * Mã cơ sở người ta gõ tay có dùng được không? '' = được, hoặc câu từ chối.
	 *
	 * 🔴 DANH SÁCH KÝ TỰ NÀY MỞ DẦN THEO SỔ THẬT, KHÔNG THEO TRỰC GIÁC. Mã cơ sở không do ai
	 *    thiết kế — nó là chuỗi mà máy chấm công khai và nằm trong tên tệp .csv, nên nó có gì
	 *    thì đây phải nhận đúng cái đó.
	 *      · `( ) ` và khoảng trắng — sổ có `PART_TIME (POSHJP)`;
	 *      · dấu `+` — sổ có `(PART TIME )_POSH+JP`. Anh Thắng 02/09/2026 vấp đúng chỗ này khi
	 *        khai tên ấy vào danh mục để dọn: *"Mã cơ sở chỉ nhận chữ, số…"*.
	 *
	 * ⚠️ CHẶT Ở ĐÂY MÀ SỔ ĐÃ CÓ RỒI THÌ HỎNG THEO KIỂU TỆ NHẤT: cái tên VẪN nằm trong bảng chấm
	 *    công (nó vào bằng đường khác — nạp .csv, cổng máy), nhưng người dọn KHÔNG khai nó vào
	 *    danh mục được, nên cũng không gộp hay xoá được. Chặn đầu vào không xoá được thứ đã ở
	 *    trong nhà; nó chỉ khoá mất cái chổi.
	 */
	public static function ma_coso_hop_le( $cs ) {
		$cs = trim( (string) $cs );
		if ( '' === $cs ) { return 'Chưa nhập mã cơ sở.'; }
		if ( mb_strlen( $cs, 'UTF-8' ) > 100 ) { return 'Mã cơ sở dài quá (tối đa 100 ký tự).'; }
		if ( ! preg_match( '/^[\p{L}\p{N} _\-().+]+$/u', $cs ) ) {
			return 'Mã cơ sở chỉ nhận chữ, số, khoảng trắng và _ - ( ) . + — không nhận ký tự khác.';
		}
		return '';
	}

	/** Cắt .csv thành mảng dòng. Nhận cả CRLF lẫn LF, cả dấu phẩy lẫn chấm phẩy. */
	public static function tach( $van_ban ) {
		$van_ban = str_replace( array( "\r\n", "\r" ), "\n", (string) $van_ban );
		$ngan    = ( substr_count( $van_ban, ';' ) > substr_count( $van_ban, ',' ) ) ? ';' : ',';
		$ra = array();
		$f  = fopen( 'php://temp', 'r+' );
		fwrite( $f, $van_ban );
		rewind( $f );
		/* 🔴 THAM SỐ `$escape` KHAI RÕ, KHÔNG ĐỂ MẶC ĐỊNH. PHP 8.4 kêu Deprecated ở mọi lời gọi
		   `fgetcsv()`/`fputcsv()` thiếu nó, và PHP 9 sẽ ĐỔI mặc định từ `"\\"` sang `""`. Để mặc
		   định thì hai chuyện cùng xảy ra: hosting bật `display_errors` in chữ vàng chen vào giữa
		   trang (tệ nhất là chen vào chính tệp .csv XUẤT ra, làm hỏng tệp tải về), và tới PHP 9
		   thì cách đọc đổi âm thầm giữa hai lần nạp cùng một tệp.
		   ⚠️ GIỮ `'\\'` — đúng hành vi từ trước tới nay. Đổi sang `''` (chuẩn RFC 4180, và là
		      hướng PHP 9 đi) là đổi cách đọc những ô có dấu `\` — việc đó phải làm riêng, có bảng
		      đối chiếu trước sau, chứ không lặng lẽ kèm vào một bản vá về cơ sở. */
		while ( false !== ( $h = fgetcsv( $f, 0, $ngan, '"', '\\' ) ) ) {
			if ( null === $h || array( null ) === $h ) { continue; }
			$ra[] = $h;
		}
		fclose( $f );
		return $ra;
	}

	/** Chữ về dạng so được: thường, bỏ dấu, gộp khoảng trắng. */
	public static function gon( $s ) {
		$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
		$cap = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $cap as $tron => $co ) {
			$ds = preg_split( '//u', $co, -1, PREG_SPLIT_NO_EMPTY );
			$s  = str_replace( $ds, $tron, $s );
		}
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}

	/** Ô tiêu đề -> 'YYYY-MM-DD', hoặc '' nếu không phải ngày. */
	public static function ngay( $o ) {
		$s = trim( (string) $o );
		if ( '' === $s ) { return ''; }
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m ) ) {
			return self::ghep( $m[1], $m[2], $m[3] );
		}
		/* dd/mm/yyyy — bản xuất theo múi Việt Nam hay ra dạng này. NGÀY TRƯỚC THÁNG: đọc nhầm
		   thành mm/dd là cả tháng nhảy lung tung mà vẫn ra ngày hợp lệ, không có gì báo. */
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m ) ) {
			return self::ghep( $m[3], $m[2], $m[1] );
		}
		return '';
	}

	private static function ghep( $y, $m, $d ) {
		$y = (int) $y; $m = (int) $m; $d = (int) $d;
		if ( $m < 1 || $m > 12 || $d < 1 || $d > 31 ) { return ''; }
		return sprintf( '%04d-%02d-%02d', $y, $m, $d );
	}

	/**
	 * Ô giờ -> 'HH:MM:SS', hoặc '' nếu trống / không đọc được.
	 *
	 * Ba kiểu bẩn có thật trong tệp anh Thắng gửi, không phải phòng xa:
	 *   · `8:30`           giờ một chữ số   (Sheets bỏ số 0 khi ô để dạng chữ)
	 *   · `16:30`          không có giây
	 *   · `08:30 13:23:38` HAI giờ trong MỘT ô — người nhập gõ cả cặp vào ô Giờ Vào
	 *
	 * 🔴 Ô hai giờ: lấy giờ ĐẦU cho ô "vào", giờ CUỐI cho ô "ra". Cứ lấy giờ đầu cho cả hai thì
	 *    ô Giờ Ra dạng `08:30 17:00` biến thành giờ ra 08:30 — ca 8 tiếng tụt còn 0, mà bảng vẫn
	 *    ra một con số trông bình thường.
	 *
	 * @param string $o  nội dung ô.
	 * @param string $vi 'vao' hay 'ra' — quyết định lấy giờ nào khi ô có nhiều giờ.
	 */
	public static function gio( $o, $vi = 'vao' ) {
		$s = trim( (string) $o );
		if ( '' === $s ) { return ''; }
		if ( ! preg_match_all( '/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\b/', $s, $ms, PREG_SET_ORDER ) ) {
			return '';
		}
		$hop = array();
		foreach ( $ms as $m ) {
			$h = (int) $m[1];
			$p = (int) $m[2];
			$g = isset( $m[3] ) ? (int) $m[3] : 0;
			if ( $h > 23 || $p > 59 || $g > 59 ) { continue; }
			$hop[] = sprintf( '%02d:%02d:%02d', $h, $p, $g );
		}
		if ( ! $hop ) { return ''; }
		return ( 'ra' === $vi ) ? $hop[ count( $hop ) - 1 ] : $hop[0];
	}
}
