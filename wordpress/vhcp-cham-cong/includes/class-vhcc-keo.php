<?php
/**
 * KÉO DỮ LIỆU CŨ TỪ APP GỐC SANG MySQL — một chiều, chỉ đọc.
 *
 * Anh Thắng chọn "đường B": thay vì dán tay 21+ hồ sơ và cả năm chấm công từ Google Sheet,
 * bấm một nút kéo qua.
 *
 * 🔴 BA LUẬT KHÔNG ĐƯỢC PHÁ
 *
 *  1. MỘT CHIỀU. Sheet là nguồn thật, MySQL sao lại. Không có hàm nào ở đây ghi lên sheet, và
 *     cầu nối cũng không khai hàm ghi nào cho nhân sự / chấm công. Mở hai chiều là sớm muộn hai
 *     bên đè nhau mà không ai biết bên nào đúng.
 *
 *  2. KÉO LẠI ĐƯỢC BAO NHIÊU LẦN CŨNG KHÔNG SINH RÁC. Mạng đứt giữa mẻ kéo là chuyện thường,
 *     nên phải kéo lại được. Chấm công đi qua đúng `VHCC_Nhan::ghi_gio()` — hàm cổng máy dùng —
 *     nên luật "chỉ nới, không thu hẹp" áp cho cả dữ liệu kéo về. Nhân sự thì khớp theo `ma_nv`.
 *
 *  3. KHÔNG BAO GIỜ THU HẸP GIỜ ĐÃ CÓ. Nếu MySQL đã có lượt bấm do máy đẩy trực tiếp (đường
 *     mới) mà sheet chỉ có một nửa cặp giờ, kéo về KHÔNG được xoá bớt. Đây đúng là lý do phải
 *     dùng lại `ghi_gio()` chứ không viết câu UPDATE riêng ở đây: luật nới-không-thu-hẹp chỉ
 *     nên có MỘT bản.
 *
 * ⚠️ MỘT CƠ SỞ MỘT THÁNG MỖI LƯỢT GỌI. Apps Script có 6 phút mỗi lượt; PHP trên hosting chia sẻ
 *    cũng có giới hạn thời gian. Kéo cả chuỗi cả năm trong một lượt là chết giữa đường, và chết
 *    giữa đường thì không biết đã tới đâu. Nên chia mẻ, và ghi lại tiến độ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Keo {

	/** Khoá lưu tiến độ kéo — để biết mẻ sau đi tiếp từ đâu. */
	const O_TIEN_DO = 'vhcc_keo_tien_do';

	/* ==================================================================== NHÂN SỰ */

	/**
	 * Đọc hồ sơ từ app gốc, quy về khuôn bảng `nhan_vien`.
	 *
	 * `getEmployees` trả về khoá kiểu camelCase của app (`employeeNo`, `phone`, `dob`…).
	 * Bảng MySQL dùng tên cột tiếng Việt không dấu. Bản đồ nằm ở ĐÚNG MỘT CHỖ là hằng dưới đây —
	 * khai hai nơi thì thêm cột mới là lệch.
	 */
	const BAN_DO_NV = array(
		'employeeNo'     => 'ma_nv',
		'name'           => 'ho_ten',
		'station'        => 'cua_hang',
		'machinePin'     => 'pin_may',
		'phone'          => 'sdt',
		'dob'            => 'ngay_sinh',
		'gender'         => 'gioi_tinh',
		'cccd'           => 'cccd',
		'address'        => 'dia_chi',
		'emgName'        => 'nguoi_lien_he_khan',
		'emgPhone'       => 'sdt_khan',
		'position'       => 'chuc_vu',
		'startDate'      => 'ngay_vao_lam',
		'workStatus'     => 'trang_thai_lam_viec',
		'contractType'   => 'loai_hop_dong',
		'baseSalary'     => 'luong_co_ban',
		'bankAccount'    => 'so_tai_khoan',
		'bankName'       => 'ngan_hang',
		'cccdFileId'     => 'cccd_file_id',
		'contractFileId' => 'hop_dong_file_id',
		'nhiemVu'        => 'nhiem_vu',
		'coSoPhu'        => 'coso_phu',
		'pinDangNhap'    => 'pin_dang_nhap',
	);

	/** Ngày của app có thể là `dd/mm/yyyy`, `yyyy-mm-dd`, hoặc chuỗi ISO của Date. */
	public static function ngay( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) { return null; }
		if ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})#', $s, $m ) ) { return $m[1] . '-' . $m[2] . '-' . $m[3]; }
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m ) ) {
			return $m[3] . '-' . sprintf( '%02d', $m[2] ) . '-' . sprintf( '%02d', $m[1] );
		}
		return null;   // không nhận ra thì để trống, KHÔNG đoán
	}

	/**
	 * Kéo nhân sự. `$chi_xem = true` thì chỉ đếm, không ghi gì.
	 *
	 * @return array ['ok'=>bool, 'them'=>int, 'sua'=>int, 'bo'=>array, 'dong'=>array]
	 */
	public static function keo_nhan_su( $chi_xem = true ) {
		global $wpdb;
		$r = VHCC_CauNoi::goi( 'getEmployees', array( VHCC_May::pin() ) );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $r['error'] ) ? $r['error'] : 'Không gọi được app gốc.' );
		}
		$ds = is_array( $r['data'] ) ? $r['data'] : array();
		if ( ! $ds ) {
			return array( 'ok' => false, 'error' => 'App gốc trả về 0 hồ sơ. Kiểm PIN admin trong '
				. 'wp-config.php (VHCC_PIN_ADMIN) — PIN của cửa hàng trưởng chỉ thấy cơ sở của họ.' );
		}

		$bang = VHCC_DB::t( 'nhan_vien' );
		$dang_co = array();
		foreach ( VHCC_DB::rows( "SELECT ma_nv FROM $bang" ) as $x ) { $dang_co[ $x['ma_nv'] ] = 1; }

		$them = 0; $sua = 0; $bo = array(); $dong = array();
		foreach ( $ds as $e ) {
			$e  = (array) $e;
			$ma = trim( (string) ( isset( $e['employeeNo'] ) ? $e['employeeNo'] : '' ) );
			$ten = trim( (string) ( isset( $e['name'] ) ? $e['name'] : '' ) );
			if ( $ma === '' )  { $bo[] = 'một dòng thiếu Mã NV'; continue; }
			if ( $ten === '' ) { $bo[] = $ma . ': thiếu Họ tên'; continue; }

			$ghi = array();
			foreach ( self::BAN_DO_NV as $khoa_app => $cot ) {
				if ( ! array_key_exists( $khoa_app, $e ) ) { continue; }
				$v = $e[ $khoa_app ];
				if ( 'ngay_sinh' === $cot || 'ngay_vao_lam' === $cot ) {
					$v = self::ngay( $v );
				} elseif ( 'luong_co_ban' === $cot ) {
					/* Dùng lại đúng bộ đọc số tiền của nhân sự — `13.000.000` là mười ba triệu,
					   không phải mười ba. Viết lại phép đọc số ở đây là mời đúng cái lỗi đó về. */
					$v = VHCC_NhanSu::so_tien( $v );
				} elseif ( 'pin_may' === $cot || 'pin_dang_nhap' === $cot ) {
					/* Bảng tính coi PIN là SỐ nên `1234` về đây thành `"1234.0"`. PIN máy kiểu đó
					   đẩy xuống máy chấm công là nhân viên gõ mãi không mở được cửa, mà nhìn hồ
					   sơ vẫn thấy PIN nằm đó. Rửa ngay lúc kéo về. */
					$v = VHCC_Auth::pin_sach( $v );
				} else {
					$v = trim( (string) $v );
				}
				$ghi[ $cot ] = $v;
			}
			/* `cua_hang` phải chuẩn hoá y như lúc lưu hồ sơ tay, không thì cùng một cơ sở mà ra
			   hai tên khác nhau và bảng lương chia làm hai. */
			if ( isset( $ghi['cua_hang'] ) ) { $ghi['cua_hang'] = VHCC_NhanSu::chuan_coso( $ghi['cua_hang'] ); }
			unset( $ghi['ma_nv'] );   // khoá, không nằm trong phần cập nhật

			$la_moi = ! isset( $dang_co[ $ma ] );
			if ( $la_moi ) { $them++; } else { $sua++; }
			$dong[] = array( 'ma' => $ma, 'ten' => $ten,
				'coso' => isset( $ghi['cua_hang'] ) ? $ghi['cua_hang'] : '',
				'viec' => $la_moi ? 'thêm' : 'cập nhật' );

			if ( $chi_xem ) { continue; }
			$ghi['cap_nhat'] = current_time( 'mysql' );
			if ( $la_moi ) {
				$ghi['ma_nv'] = $ma;
				$wpdb->insert( $bang, $ghi );
			} else {
				$wpdb->update( $bang, $ghi, array( 'ma_nv' => $ma ) );
			}
		}
		return array( 'ok' => true, 'them' => $them, 'sua' => $sua, 'bo' => $bo, 'dong' => $dong );
	}

	/* ================================================================ CHẤM CÔNG CŨ */

	/** Danh sách cơ sở để kéo — lấy từ app gốc, không đoán từ MySQL (MySQL đang trống). */
	public static function ds_coso() {
		$r = VHCC_CauNoi::goi( 'ccDsCoSoXuat', array( VHCC_May::pin() ) );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $r['error'] ) ? $r['error'] : 'Không gọi được app gốc.' );
		}
		$d = (array) $r['data'];
		if ( empty( $d['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $d['error'] ) ? $d['error']
				: 'App gốc chối: có thể chưa dán bản CauNoiChamCong.gs mới (hàm ccDsCoSoXuat).' );
		}
		return array( 'ok' => true, 'ds' => array_values( (array) ( isset( $d['ds'] ) ? $d['ds'] : array() ) ) );
	}

	/**
	 * Kéo chấm công MỘT cơ sở MỘT tháng.
	 *
	 * @param string $coso  tên cơ sở, không có tiền tố `CS_`.
	 * @param string $thang dạng `MM-yyyy` (đúng khuôn app gốc nhận).
	 */
	public static function keo_thang( $coso, $thang, $chi_xem = true ) {
		$r = VHCC_CauNoi::goi( 'ccXuatChamCong', array( VHCC_May::pin(), $coso, $thang ) );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $r['error'] ) ? $r['error'] : 'Không gọi được app gốc.' );
		}
		$d = (array) $r['data'];
		if ( empty( $d['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $d['error'] ) ? $d['error']
				: 'App gốc chối: có thể chưa dán bản CauNoiChamCong.gs mới (hàm ccXuatChamCong).' );
		}
		if ( ! empty( $d['khongCoSheet'] ) ) {
			return array( 'ok' => true, 'khong_co_sheet' => true, 'nguoi' => 0, 'luot' => 0, 'bo' => array() );
		}

		$nguoi = 0; $luot = 0; $bo = array();
		foreach ( (array) ( isset( $d['rows'] ) ? $d['rows'] : array() ) as $ng ) {
			$ng  = (array) $ng;
			$ma  = trim( (string) ( isset( $ng['ma'] ) ? $ng['ma'] : '' ) );
			$ten = trim( (string) ( isset( $ng['ten'] ) ? $ng['ten'] : '' ) );
			if ( $ma === '' ) { continue; }
			$nguoi++;
			foreach ( (array) ( isset( $ng['ngay'] ) ? $ng['ngay'] : array() ) as $ngay_o ) {
				$ngay_o = (array) $ngay_o;
				$ngay = trim( (string) ( isset( $ngay_o['date'] ) ? $ngay_o['date'] : '' ) );
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { continue; }
				/* Hai giờ, xử RIÊNG từng giờ qua `ghi_gio` — đúng như hai lượt bấm thật.
				   Nhờ vậy luật "chỉ nới, không thu hẹp" áp y nguyên, và kéo lại lần hai thì
				   `ghi_gio` nhận ra giờ trùng và bỏ qua, không sinh thêm gì. */
				foreach ( array( 'vao', 'ra' ) as $o ) {
					$gio = trim( (string) ( isset( $ngay_o[ $o ] ) ? $ngay_o[ $o ] : '' ) );
					if ( $gio === '' ) { continue; }
					$giay = VHCC_DB::giay( $gio );
					if ( null === $giay ) { $bo[] = $ma . ' ' . $ngay . ': giờ không đọc được "' . $gio . '"'; continue; }
					$luot++;
					if ( $chi_xem ) { continue; }
					/* `nguon = 'sheet'` — phân biệt được lượt kéo về với lượt máy đẩy trực tiếp.
					   Cùng một ô mà hai đường cùng ghi thì `ghi_gio` tự nâng lên `hon-hop`. */
					VHCC_Nhan::ghi_gio( $coso, $ngay, $ma, $ten, $giay, '', 'sheet' );
				}
			}
		}
		return array( 'ok' => true, 'nguoi' => $nguoi, 'luot' => $luot, 'bo' => $bo );
	}

	/* ================================================================ SỔ PHÂN QUYỀN */

	/**
	 * Kéo sổ `PhanQuyen` của app gốc về bảng `phan_quyen`.
	 *
	 * 🔴 Vì sao: anh Thắng — *"mỗi nhân viên đều có pin hết, sao không đăng nhập được"*. Đúng,
	 *    ai cũng có PIN, nhưng PIN đó nằm ở sổ PhanQuyen của app gốc. Kéo về rồi chọn nguồn
	 *    người dùng = "Phân quyền của app gốc" là ai đăng nhập được app gốc thì đăng nhập được
	 *    trang web bằng CHÍNH PIN đó — không phải cấp PIN lần thứ hai cho mấy chục người.
	 *
	 * ⚠️ Khớp theo PIN (đó là khoá duy nhất của sổ đó, và cũng là UNIQUE KEY của bảng). Kéo lại
	 *    thì cập nhật, không nhân đôi.
	 */
	public static function keo_phan_quyen( $chi_xem = true ) {
		global $wpdb;
		$r = VHCC_CauNoi::goi( 'ccXuatPhanQuyen', array( VHCC_May::pin() ) );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $r['error'] ) ? $r['error'] : 'Không gọi được app gốc.' );
		}
		$d = (array) $r['data'];
		if ( empty( $d['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $d['error'] ) ? $d['error']
				: 'App gốc chối: có thể chưa dán bản CauNoiChamCong.gs mới (hàm ccXuatPhanQuyen).' );
		}
		$rows = (array) ( isset( $d['rows'] ) ? $d['rows'] : array() );
		if ( ! $rows ) {
			return array( 'ok' => false, 'error' => 'Sổ PhanQuyen của app gốc trả về 0 dòng.' );
		}

		$bang = VHCC_DB::t( 'phan_quyen' );
		$dang_co = array();
		foreach ( VHCC_DB::rows( "SELECT pin FROM $bang" ) as $x ) { $dang_co[ $x['pin'] ] = 1; }

		$them = 0; $sua = 0; $bo = array();
		foreach ( $rows as $x ) {
			$x   = (array) $x;
			$pin = trim( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) );
			if ( '' === $pin ) { continue; }
			/* Cổng đăng nhập của plugin đòi 4–8 chữ số. PIN ngoài khuôn đó có kéo về cũng không
			   đăng nhập được — nói ra ngay chứ đừng để người ta ngồi thử. */
			if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
				$bo[] = trim( (string) ( isset( $x['hoTen'] ) ? $x['hoTen'] : '?' ) )
					. ': PIN ' . strlen( $pin ) . ' ký tự, không phải 4–8 chữ số';
				continue;
			}
			$ghi = array(
				'ho_ten'         => trim( (string) ( isset( $x['hoTen'] ) ? $x['hoTen'] : '' ) ),
				'vai_tro'        => strtoupper( trim( (string) ( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' ) ) ),
				'cua_hang'       => trim( (string) ( isset( $x['cuaHang'] ) ? $x['cuaHang'] : '' ) ),
				'ma_cc_online'   => trim( (string) ( isset( $x['maCcOnline'] ) ? $x['maCcOnline'] : '' ) ),
				'coso_cc_online' => trim( (string) ( isset( $x['coSoCcOnline'] ) ? $x['coSoCcOnline'] : '' ) ),
				'cap_nhat'       => current_time( 'mysql' ),
			);
			if ( isset( $dang_co[ $pin ] ) ) { $sua++; } else { $them++; }
			if ( $chi_xem ) { continue; }
			if ( isset( $dang_co[ $pin ] ) ) {
				$wpdb->update( $bang, $ghi, array( 'pin' => $pin ) );
			} else {
				$ghi['pin'] = $pin;
				$wpdb->insert( $bang, $ghi );
			}
		}
		return array( 'ok' => true, 'them' => $them, 'sua' => $sua, 'bo' => $bo );
	}

	/** Danh sách tháng từ `$tu` tới `$den` (cùng khuôn `MM-yyyy`), cũ trước. */
	public static function ds_thang( $tu, $den ) {
		if ( ! preg_match( '#^(\d{2})-(\d{4})$#', trim( (string) $tu ), $a )
			|| ! preg_match( '#^(\d{2})-(\d{4})$#', trim( (string) $den ), $b ) ) {
			return array();
		}
		$i = (int) $a[2] * 12 + ( (int) $a[1] - 1 );
		$j = (int) $b[2] * 12 + ( (int) $b[1] - 1 );
		if ( $j < $i ) { return array(); }
		/* Trần 36 tháng: gõ nhầm năm (2016 thay vì 2026) là 120 lượt gọi mạng vô ích. */
		if ( $j - $i > 35 ) { return array(); }
		$ra = array();
		for ( ; $i <= $j; $i++ ) {
			$ra[] = sprintf( '%02d-%04d', ( $i % 12 ) + 1, intdiv( $i, 12 ) );
		}
		return $ra;
	}

	/** Ghi nhận đã kéo xong một (cơ sở, tháng) — để mẻ sau biết đi tiếp từ đâu. */
	public static function ghi_tien_do( $coso, $thang, $kq ) {
		$td = get_option( self::O_TIEN_DO, array() );
		if ( ! is_array( $td ) ) { $td = array(); }
		$td[ $coso . '|' . $thang ] = array(
			'luc'   => current_time( 'mysql' ),
			'nguoi' => isset( $kq['nguoi'] ) ? (int) $kq['nguoi'] : 0,
			'luot'  => isset( $kq['luot'] ) ? (int) $kq['luot'] : 0,
		);
		update_option( self::O_TIEN_DO, $td, false );
	}

	public static function tien_do() {
		$td = get_option( self::O_TIEN_DO, array() );
		return is_array( $td ) ? $td : array();
	}

	public static function xoa_tien_do() { delete_option( self::O_TIEN_DO ); }
}
