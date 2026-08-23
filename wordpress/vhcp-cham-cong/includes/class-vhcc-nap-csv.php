<?php
/**
 * NẠP HỒ SƠ NHÂN VIÊN TỪ CSV / DÁN TỪ GOOGLE SHEETS — ĐỦ MỌI CỘT.
 *
 * Anh Thắng: *"cấu trúc trang nhân viên nó như này mà, nạp bằng .csv được không"* → *"lấy đủ
 * luôn nhé, các cột"*.
 *
 * Sheet nhân viên của anh có: Mã NV · Họ tên · Cửa hàng · Trạng thái đồng bộ · Cập nhật · CCCD ·
 * Chức vụ · Nhiệm vụ · Cơ sở phụ · PIN đăng nhập — và mấy nhóm cột đang thu gọn. Bảng `nhan_vien`
 * đã có sẵn đủ chỗ cho tất cả, nên đường này ghi thẳng vào đó, KHÔNG dựng bảng thứ hai.
 *
 * ĐƯỜNG NÀY KHÔNG CẦN CẦU NỐI APPS SCRIPT. Đó là điểm chính: cầu nối còn phụ thuộc app gốc còn
 * sống, còn đúng WEB_KEY, còn đúng bản Deploy. Tải một file .csv lên thì không phụ thuộc gì cả.
 *
 * 🔴 BỐN CHỖ SAI LÀ HỎNG DỮ LIỆU THẬT, cả bốn đều đã gặp trong dự án này:
 *
 *   1. **Ô rỗng KHÔNG được ghi đè lên giá trị đang có.** Sheet của anh Thắng đang thu gọn nhiều
 *      nhóm cột; xuất ra một file thiếu cột rồi nạp đè là xoá trắng số tài khoản, lương, CCCD…
 *      mà màn hình vẫn báo "cập nhật N người". Chỉ ghi ô CÓ giá trị.
 *   2. **Dấu phẩy trong ô.** Cột "Cơ sở phụ" có giá trị `FARM_PT, FZ_LTVT`. Cắt chuỗi bằng
 *      explode(',') là dòng đó lệch hết cột từ đó trở đi. Phải đọc CSV đúng luật dấu nháy.
 *   3. **Số 0 ở đầu PIN.** Sheets coi PIN là SỐ nên `013013` xuất ra `13013`, `246813` ra
 *      `246813.0`. Đuôi `.0` thì cắt được; số 0 đầu MẤT RỒI thì không dựng lại được — chỉ dám
 *      CẢNH BÁO đích danh, không tự đoán thêm số 0 vào PIN của người ta.
 *   4. **CCCD không bao giờ được hiểu nhầm là PIN.** Nhầm một cái là số căn cước của người ta
 *      thành mật khẩu đăng nhập.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NapCsv {

	/**
	 * TIÊU ĐỀ TRONG SHEET -> CỘT BẢNG `nhan_vien`.
	 *
	 * Khoá đã bỏ dấu và bỏ mọi ký tự không phải chữ/số, nên `Họ và Tên`, `HO VA TEN`, `họ_và_tên`
	 * đều ra `hovaten`. Nhiều cách gọi cùng trỏ về một cột — sổ mỗi cơ sở gõ một kiểu.
	 */
	const BAN_DO = array(
		'manv' => 'ma_nv', 'ma' => 'ma_nv', 'manhanvien' => 'ma_nv', 'mann' => 'ma_nv',
		'employeeno' => 'ma_nv', 'id' => 'ma_nv',

		'hoten' => 'ho_ten', 'hovaten' => 'ho_ten', 'ten' => 'ho_ten', 'tennhanvien' => 'ho_ten',
		'name' => 'ho_ten',

		'cuahang' => 'cua_hang', 'coso' => 'cua_hang', 'chinhanh' => 'cua_hang',
		'cosochinh' => 'cua_hang', 'station' => 'cua_hang',

		'pinmay' => 'pin_may', 'pinmaychamcong' => 'pin_may', 'machinepin' => 'pin_may',
		'pindangnhap' => 'pin_dang_nhap', 'pin' => 'pin_dang_nhap', 'matkhau' => 'pin_dang_nhap',

		'trangthaidongbo' => 'trang_thai_dong_bo', 'dongbo' => 'trang_thai_dong_bo',
		'capnhat' => 'cap_nhat', 'lancapnhat' => 'cap_nhat',

		'sdt' => 'sdt', 'sodienthoai' => 'sdt', 'dienthoai' => 'sdt', 'phone' => 'sdt',
		'ngaysinh' => 'ngay_sinh', 'dob' => 'ngay_sinh',
		'gioitinh' => 'gioi_tinh',
		'cccd' => 'cccd', 'cmnd' => 'cccd', 'cccdcmnd' => 'cccd', 'socccd' => 'cccd',
		'diachi' => 'dia_chi',
		'nguoilienhekhan' => 'nguoi_lien_he_khan', 'lienhekhan' => 'nguoi_lien_he_khan',
		'sdtkhan' => 'sdt_khan', 'dienthoaikhan' => 'sdt_khan',

		'chucvu' => 'chuc_vu', 'vaitro' => 'chuc_vu', 'quyen' => 'chuc_vu',
		'nhiemvu' => 'nhiem_vu',
		'cosophu' => 'coso_phu', 'cuahangphu' => 'coso_phu',

		'ngayvaolam' => 'ngay_vao_lam', 'ngaybatdau' => 'ngay_vao_lam',
		'trangthailamviec' => 'trang_thai_lam_viec', 'trangthai' => 'trang_thai_lam_viec',
		'loaihopdong' => 'loai_hop_dong',
		'luongcoban' => 'luong_co_ban', 'luong' => 'luong_co_ban',
		'sotaikhoan' => 'so_tai_khoan', 'stk' => 'so_tai_khoan',
		'nganhang' => 'ngan_hang',
		'cccdfileid' => 'cccd_file_id', 'hopdongfileid' => 'hop_dong_file_id',
		'anh' => 'photo_file_id', 'photofileid' => 'photo_file_id',
	);

	/** Cột kiểu ngày — phải quy về yyyy-mm-dd, không nhận ra thì để TRỐNG chứ không đoán. */
	const COT_NGAY  = array( 'ngay_sinh', 'ngay_vao_lam' );
	/** Cột kiểu ngày-giờ. */
	const COT_GIO   = array( 'cap_nhat' );
	/** Cột kiểu số tiền. */
	const COT_TIEN  = array( 'luong_co_ban' );

	// ======================================================================= đọc file

	/** Bỏ dấu + bỏ ký tự lạ, chỉ để SO TIÊU ĐỀ. */
	public static function khoa( $s ) {
		return preg_replace( '/[^a-z0-9]+/u', '', VHCC_NguoiDung::bo_dau( (string) $s ) );
	}

	/**
	 * Tách văn bản thành bảng ô, ĐÚNG LUẬT DẤU NHÁY của CSV.
	 *
	 * 🔴 Không dùng explode(). Ô `"FARM_PT, FZ_LTVT"` có dấu phẩy BÊN TRONG dấu nháy; explode
	 *    cắt luôn ở đó và dòng ấy lệch hết cột từ đó trở đi — PIN của người này rơi vào cột CCCD
	 *    của người kia. Ô cũng có thể chứa xuống dòng (địa chỉ nhiều dòng), nên phải quét từng
	 *    ký tự chứ không tách theo dòng trước.
	 */
	public static function tach( $noi_dung, $ngan = '' ) {
		$s = (string) $noi_dung;
		if ( 0 === strncmp( $s, "\xEF\xBB\xBF", 3 ) ) { $s = substr( $s, 3 ); }   // BOM của Excel
		if ( '' === $ngan ) { $ngan = self::doan_ngan( $s ); }

		$bang = array(); $dong = array(); $o = ''; $trong_nhay = false;
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = $s[ $i ];
			if ( $trong_nhay ) {
				if ( '"' === $c ) {
					if ( $i + 1 < $n && '"' === $s[ $i + 1 ] ) { $o .= '"'; $i++; }   // "" = một dấu nháy
					else { $trong_nhay = false; }
				} else { $o .= $c; }
				continue;
			}
			if ( '"' === $c && '' === trim( $o ) ) { $trong_nhay = true; $o = ''; continue; }
			if ( $c === $ngan )  { $dong[] = trim( $o ); $o = ''; continue; }
			if ( "\r" === $c )   { continue; }
			if ( "\n" === $c )   { $dong[] = trim( $o ); $o = ''; $bang[] = $dong; $dong = array(); continue; }
			$o .= $c;
		}
		$dong[] = trim( $o );
		$bang[] = $dong;

		$ra = array();
		foreach ( $bang as $h ) {
			foreach ( $h as $v ) { if ( '' !== $v ) { $ra[] = $h; break; } }   // bỏ dòng rỗng hẳn
		}
		return $ra;
	}

	/** Dấu ngăn cột: đếm NGOÀI dấu nháy, nhiều nhất thì thắng. Dán từ Sheets ra TAB. */
	public static function doan_ngan( $s ) {
		$dem = array( "\t" => 0, ',' => 0, ';' => 0 );
		$trong_nhay = false;
		$n = min( strlen( $s ), 20000 );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = $s[ $i ];
			if ( '"' === $c ) { $trong_nhay = ! $trong_nhay; continue; }
			if ( $trong_nhay ) { continue; }
			if ( isset( $dem[ $c ] ) ) { $dem[ $c ]++; }
		}
		arsort( $dem );
		$top = key( $dem );
		return $dem[ $top ] > 0 ? $top : "\t";
	}

	// ======================================================================= rửa từng ô

	/**
	 * SÊ-RI NGÀY CỦA BẢNG TÍNH -> [ngày, giờ].
	 *
	 * 🔴 Google Sheets / Excel lưu ngày là SỐ NGÀY KỂ TỪ 1899-12-30, phần lẻ là giờ. Xuất .csv
	 *    từ một ô chưa định dạng ngày thì ra `46232.6543` chứ không ra `29/07/2026 15:42`. Đọc
	 *    thẳng số đó như năm thì thành năm 4623 — đúng lỗi đã làm cả loạt đơn chi phí tháng 7
	 *    biến mất khỏi bộ lọc, vì chúng rơi vào một năm không ai đi tìm.
	 *
	 * ⚠️ Viết lại ở đây, KHÔNG gọi sang VHCP_Util của plugin chi phí. Chấm công phải chạy được
	 *    khi plugin kia chưa cài hoặc đang ở bản khác — một lời gọi chéo không gác là cả trang
	 *    trắng. Đã xảy ra thật, và có phép soát ghim điều đó.
	 */
	public static function seri( $v ) {
		$s = trim( (string) $v );
		if ( ! preg_match( '#^(\d{5})(?:[.,](\d+))?$#', $s, $m ) ) { return null; }
		$n = (int) $m[1];
		if ( $n < 20000 || $n > 60000 ) { return null; }   // ngoài khoảng 1954–2064: không phải ngày
		$giay = 0;
		if ( isset( $m[2] ) && '' !== $m[2] ) {
			$giay = (int) round( (float) ( '0.' . $m[2] ) * 86400 );
			if ( $giay >= 86400 ) { $giay = 86399; }
		}
		$ts = ( $n - 25569 ) * 86400;                       // 25569 = 1970-01-01 tính theo sê-ri
		return array( gmdate( 'Y-m-d', $ts ), gmdate( 'H:i:s', $giay ) );
	}

	/** Ngày kiểu Sheets: yyyy-mm-dd, dd/mm/yyyy, hoặc sê-ri. Không nhận ra thì null. */
	public static function ngay( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) { return null; }
		$sr = self::seri( $s );
		if ( null !== $sr ) { return $sr[0]; }
		return VHCC_Keo::ngay( $s );
	}

	/** Ngày + giờ -> 'Y-m-d H:i:s', hoặc null. */
	public static function gio( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) { return null; }
		$sr = self::seri( $s );
		if ( null !== $sr ) { return $sr[0] . ' ' . $sr[1]; }
		if ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?#', $s, $m ) ) {
			return $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':'
				. ( isset( $m[6] ) ? $m[6] : '00' );
		}
		$d = VHCC_Keo::ngay( $s );
		return null === $d ? null : $d . ' 00:00:00';
	}

	/** Tiền kiểu Việt: `8.500.000`, `8,500,000`, `8500000 đ`. */
	public static function tien( $v ) {
		$s = preg_replace( '/[^0-9]/', '', (string) $v );
		return '' === $s ? 0 : (float) $s;
	}

	/**
	 * PIN: cắt đuôi `.0` của Sheets. KHÔNG tự thêm số 0 ở đầu.
	 *
	 * Thêm bừa số 0 là đặt cho người ta một mật khẩu họ không hề biết. Ngắn bất thường thì
	 * CẢNH BÁO đích danh, để anh Thắng mở sheet xem lại — đó là việc của người, không phải của
	 * phép đoán.
	 */
	public static function pin( $v ) {
		$s = trim( (string) $v );
		if ( preg_match( '/^(\d+)\.0+$/', $s, $m ) ) { $s = $m[1]; }
		return $s;
	}

	// ======================================================================= nạp

	/**
	 * Đọc nội dung -> hồ sơ đã quy về khuôn bảng `nhan_vien`.
	 *
	 * @param string $noi_dung nội dung file .csv hoặc chuỗi dán từ Sheets.
	 * @param bool   $chi_xem  true = chỉ đếm và soi, không ghi gì.
	 * @param string $coso     rỗng = nhận hết; có tên = CHỈ cơ sở đó.
	 */
	public static function nap( $noi_dung, $chi_xem = true, $coso = '' ) {
		global $wpdb;

		$o = self::tach( $noi_dung );
		if ( count( $o ) < 2 ) {
			return array( 'ok' => false, 'error' => 'File phải có DÒNG TIÊU ĐỀ rồi mới tới dòng dữ liệu. '
				. 'Trong Google Sheets: File → Tải xuống → Giá trị được phân tách bằng dấu phẩy (.csv), '
				. 'hoặc bôi đen cả vùng KỂ CẢ dòng tiêu đề rồi Ctrl+C dán vào ô.' );
		}

		/* Tiêu đề -> cột. Cột nào không nhận ra thì GIỮ TÊN LẠI để kể ra: anh Thắng bảo "lấy đủ
		   luôn các cột", nên phải nói rõ cái nào KHÔNG lấy được, chứ không im lặng bỏ. */
		$cot = array(); $la = array();
		foreach ( $o[0] as $i => $ten_cot ) {
			$k = self::khoa( $ten_cot );
			if ( '' === $k ) { continue; }
			if ( isset( self::BAN_DO[ $k ] ) && ! in_array( self::BAN_DO[ $k ], $cot, true ) ) {
				$cot[ $i ] = self::BAN_DO[ $k ];
			} else {
				$la[] = trim( (string) $ten_cot );
			}
		}
		if ( ! in_array( 'ma_nv', $cot, true ) ) {
			return array( 'ok' => false, 'error' => 'Không thấy cột MÃ NV. Đây là cột khoá — không có nó '
				. 'thì nạp lần hai sẽ nhân đôi mọi người thay vì cập nhật. Tiêu đề đọc được: '
				. implode( ' · ', array_slice( (array) $o[0], 0, 20 ) ) );
		}

		$hien_co = array();
		foreach ( VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) ) as $r ) {
			$hien_co[ (string) $r['ma_nv'] ] = $r;
		}

		$them = 0; $sua = 0; $bo = array(); $canh = array(); $lech = 0;
		$dai_pin = array();
		$coso    = trim( (string) $coso );
		/* TỪNG Ô ĐỔI GÌ — anh Thắng: *"nạp bên trong này sai hết dữ liệu"*. Một con số
		   "cập nhật 240" không cho biết nó sắp làm gì; phải chỉ ra `cũ -> mới` của từng ô thì
		   sai bản đồ cột mới lộ ra NGAY Ở BƯỚC XEM TRƯỚC, chứ không phải sau khi đã ghi đè. */
		$doi = array();
		/* Ảnh chụp giá trị CŨ để hoàn tác. Ghi đè 240 hồ sơ mà không có đường lùi thì một lần
		   bấm nhầm là mất dữ liệu thật. */
		$truoc = array(); $ma_them = array();

		for ( $d = 1; $d < count( $o ); $d++ ) {
			$h   = $o[ $d ];
			$ghi = array();
			foreach ( $cot as $i => $ten ) {
				$v = isset( $h[ $i ] ) ? trim( (string) $h[ $i ] ) : '';
				if ( '' === $v ) { continue; }        // ⬅ ô rỗng: BỎ QUA, không ghi đè
				if ( in_array( $ten, self::COT_NGAY, true ) )      { $x = self::ngay( $v ); }
				elseif ( in_array( $ten, self::COT_GIO, true ) )   { $x = self::gio( $v ); }
				elseif ( in_array( $ten, self::COT_TIEN, true ) )  { $x = self::tien( $v ); }
				elseif ( 'pin_dang_nhap' === $ten || 'pin_may' === $ten ) { $x = self::pin( $v ); }
				else { $x = $v; }
				if ( null === $x ) { continue; }      // ngày không đọc được: để trống, KHÔNG đoán
				$ghi[ $ten ] = $x;
			}

			$ma = isset( $ghi['ma_nv'] ) ? $ghi['ma_nv'] : '';
			if ( '' === $ma ) {
				$ten_d = isset( $ghi['ho_ten'] ) ? $ghi['ho_ten'] : ( 'dòng ' . ( $d + 1 ) );
				$bo[] = $ten_d . ': thiếu Mã NV';
				continue;
			}
			$ten_ng = isset( $ghi['ho_ten'] ) ? $ghi['ho_ten']
				: ( isset( $hien_co[ $ma ]['ho_ten'] ) ? $hien_co[ $ma ]['ho_ten'] : $ma );

			if ( '' !== $coso ) {
				$cs = isset( $ghi['cua_hang'] ) ? $ghi['cua_hang']
					: ( isset( $hien_co[ $ma ]['cua_hang'] ) ? $hien_co[ $ma ]['cua_hang'] : '' );
				if ( ! VHCC_NguoiDung::cung_coso( $cs, $coso ) ) { $lech++; continue; }
			}

			/* PIN ngắn bất thường = Sheets đã ăn mất số 0 ở đầu. Không dựng lại được, chỉ báo. */
			if ( isset( $ghi['pin_dang_nhap'] ) && '' !== $ghi['pin_dang_nhap'] ) {
				$dai_pin[] = strlen( $ghi['pin_dang_nhap'] );
				if ( ! preg_match( '/^\d+$/', $ghi['pin_dang_nhap'] ) ) {
					$canh[] = $ten_ng . ': PIN có ký tự không phải số';
				}
			}

			if ( isset( $hien_co[ $ma ] ) ) {
				/* Chỉ tính là SỬA khi thật sự có ô đổi giá trị. Nạp lại y hệt file cũ mà báo
				   "cập nhật 240" thì con số đó vô nghĩa — và che mất lượt nạp thật sự đổi gì. */
				$khac = array();
				foreach ( $ghi as $c => $v ) {
					if ( 'ma_nv' === $c ) { continue; }
					$cu = isset( $hien_co[ $ma ][ $c ] ) ? (string) $hien_co[ $ma ][ $c ] : '';
					if ( (string) $v === $cu ) { continue; }
					if ( in_array( $c, self::COT_TIEN, true ) && (float) $v === (float) $cu ) { continue; }
					$khac[ $c ] = array( 'cu' => $cu, 'moi' => (string) $v );
				}
				if ( ! $khac ) { continue; }
				$sua++;
				if ( count( $doi ) < 200 ) { $doi[ $ma ] = array( 'ten' => $ten_ng, 'o' => $khac ); }
				if ( $chi_xem ) { continue; }
				$luu_cu = array();
				foreach ( $khac as $c => $x ) { $luu_cu[ $c ] = $x['cu']; }
				$truoc[ $ma ] = $luu_cu;
				$moi_ghi = array();
				foreach ( $khac as $c => $x ) { $moi_ghi[ $c ] = $ghi[ $c ]; }
				$wpdb->update( VHCC_DB::t( 'nhan_vien' ), $moi_ghi, array( 'ma_nv' => $ma ) );
			} else {
				$them++;
				if ( count( $doi ) < 200 ) { $doi[ $ma ] = array( 'ten' => $ten_ng, 'moi' => 1 ); }
				if ( $chi_xem ) { continue; }
				$ma_them[] = $ma;
				$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), $ghi );
			}
		}
		if ( ! $chi_xem && ( $truoc || $ma_them ) ) {
			update_option( self::O_LUI, array( 'luc' => current_time( 'mysql' ),
				'truoc' => $truoc, 'them' => $ma_them ), false );
		}

		/* PIN ngắn hơn phần lớn = mất số 0 đầu. Chỉ so khi có đủ dòng để "phần lớn" có nghĩa. */
		if ( count( $dai_pin ) >= 5 ) {
			$dem = array_count_values( $dai_pin );
			arsort( $dem );
			$chuan = (int) key( $dem );
			$ngan  = 0;
			foreach ( $dai_pin as $l ) { if ( $l < $chuan ) { $ngan++; } }
			if ( $ngan ) {
				$canh[] = $ngan . ' người có PIN ngắn hơn ' . $chuan . ' số — nhiều khả năng Google '
					. 'Sheets đã cắt mất số 0 ở đầu. Định dạng cột PIN thành Văn bản rồi tải lại; '
					. 'hệ thống KHÔNG tự thêm số 0 vì đoán sai là đặt cho người ta một PIN họ không biết.';
			}
		}

		return array( 'ok' => true, 'them' => $them, 'sua' => $sua, 'bo' => $bo, 'canh' => $canh,
			'lech' => $lech, 'coso' => $coso, 'cot' => array_values( $cot ), 'cot_la' => $la,
			'so_dong' => count( $o ) - 1, 'doi' => $doi );
	}

	// ======================================================================= hoàn tác

	const O_LUI = 'vhcc_nap_csv_lui';

	/** Lượt nạp gần nhất còn hoàn tác được: ['luc','truoc','them'] — hoặc [] nếu không có. */
	public static function co_lui() { return (array) get_option( self::O_LUI, array() ); }

	/**
	 * HOÀN TÁC LƯỢT NẠP GẦN NHẤT — trả từng ô về đúng giá trị cũ, xoá người mới thêm.
	 *
	 * 🔴 Chỉ trả lại NHỮNG Ô LƯỢT NẠP ĐÓ ĐỘNG VÀO. Không chép đè cả dòng: giữa lúc nạp và lúc
	 *    hoàn tác có thể có người đã sửa tay ô khác, chép đè cả dòng là xoá luôn công sửa đó.
	 *
	 * ⚠️ CHỈ MỘT BƯỚC LÙI. Hoàn tác xong là xoá ảnh chụp — không có lùi hai lượt, và nói thẳng
	 *    điều đó ra ở màn hình thay vì để người dùng tưởng lùi được mãi.
	 */
	public static function lui() {
		global $wpdb;
		$l = self::co_lui();
		if ( empty( $l['truoc'] ) && empty( $l['them'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có lượt nạp nào để hoàn tác.' );
		}
		$ve = 0; $xoa = 0;
		foreach ( (array) $l['truoc'] as $ma => $o_cu ) {
			if ( ! is_array( $o_cu ) || ! $o_cu ) { continue; }
			$wpdb->update( VHCC_DB::t( 'nhan_vien' ), $o_cu, array( 'ma_nv' => (string) $ma ) );
			$ve++;
		}
		foreach ( (array) $l['them'] as $ma ) {
			$wpdb->delete( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => (string) $ma ) );
			$xoa++;
		}
		delete_option( self::O_LUI );
		return array( 'ok' => true, 've' => $ve, 'xoa' => $xoa, 'luc' => isset( $l['luc'] ) ? $l['luc'] : '' );
	}
}
