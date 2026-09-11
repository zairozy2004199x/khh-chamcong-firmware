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
		/* PIN đã gặp trong CHÍNH lượt kéo này -> mã NV (xem khối soát trùng trong vòng lặp). */
		$pin_keo = array( 'pin_dang_nhap' => array(), 'pin_may' => array() );
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

			/* ===================================================================================
			 *  PIN TRÙNG THÌ KHÔNG KÉO Ô PIN ẤY VỀ
			 * -----------------------------------------------------------------------------------
			 *  🔴 08/09/2026 — anh Thắng: *"chặn trường hợp tạo mã pin trùng nhé"*.
			 *  Sổ ở app gốc là sổ gõ tay, nên có thể đã trùng từ bên đó. Kéo nguyên về là hai
			 *  người cùng PIN đăng nhập: cổng nhận người GẶP TRƯỚC, nhật ký ghi tên người đó.
			 *  ⚠️ Chỉ BỎ Ô PIN, vẫn kéo các ô khác — lượt kéo này để dựng lại cả sổ nhân sự, chối
			 *     cả dòng vì một ô PIN là mất luôn tên/cơ sở/lương của người đó.
			 *  ⚠️ Soát cả trùng TRONG CHÍNH LƯỢT KÉO, không chỉ với sổ đang có: một file nguồn có
			 *     hai dòng cùng PIN thì so với sổ chẳng bắt được dòng nào.
			 *  ⚠️ Cơ sở lấy từ `$ghi` — `$dang_co` ở đường này CHỈ giữ danh sách mã (`ma_nv => 1`),
			 *     không phải cả hàng, nên đừng đọc cột nào từ nó.
			 * =================================================================================== */
			$cs_keo = VHCC_NhanSu::ds_coso_hs( array(
				'cua_hang' => isset( $ghi['cua_hang'] ) ? $ghi['cua_hang'] : '',
				'coso_phu' => isset( $ghi['coso_phu'] ) ? $ghi['coso_phu'] : '',
			) );
			$bo_pin = array();
			foreach ( array( 'pin_dang_nhap' => 'PIN đăng nhập', 'pin_may' => 'PIN máy' ) as $o_p => $nhan_p ) {
				if ( empty( $ghi[ $o_p ] ) ) { continue; }
				$p = (string) $ghi[ $o_p ];
				if ( isset( $pin_keo[ $o_p ][ $p ] ) && $pin_keo[ $o_p ][ $p ] !== $ma ) {
					$k = $pin_keo[ $o_p ][ $p ];
				} elseif ( 'pin_dang_nhap' === $o_p ) {
					$k = VHCC_NhanSu::pin_dang_dung( $p, $ma );
				} else {
					$k = VHCC_NhanSu::pin_may_dang_dung( $p, $ma, $cs_keo );
				}
				if ( '' !== $k ) { $bo_pin[] = $nhan_p . ' trùng ' . $k; unset( $ghi[ $o_p ] ); continue; }
				$pin_keo[ $o_p ][ $p ] = $ma;
			}

			$la_moi = ! isset( $dang_co[ $ma ] );
			if ( $la_moi ) { $them++; } else { $sua++; }
			$dong[] = array( 'ma' => $ma, 'ten' => $ten,
				'coso' => isset( $ghi['cua_hang'] ) ? $ghi['cua_hang'] : '',
				'viec' => ( $la_moi ? 'thêm' : 'cập nhật' )
					. ( $bo_pin ? ' — bỏ ' . implode( ', ', $bo_pin ) : '' ) );

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

	/**
	 * ĐỐI CHIẾU MỘT CƠ SỞ MỘT THÁNG: app gốc (sheet) có gì mà WordPress không có, và ngược lại.
	 *
	 * 🔴 Anh Thắng 11/09/2026, hai ảnh cạnh nhau — Dashboard của app gốc trên script.google.com
	 *    báo *"ĐÃ CHẤM 5/30 NGÀY"*, còn lưới bảng công của web thì hàng người ấy toàn dấu chấm:
	 *    *"Bên trang chấm công lại có, bên bảng anh không thấy"*.
	 *
	 * 🔴 VÌ SAO CHUYỆN NÀY XẢY RA ĐƯỢC — HAI CUỐN SỔ. App gốc ghi vào **Google Sheet**; lưới bảng
	 *    công đọc **MySQL của WordPress**. Lượt chấm chỉ sang được bằng đúng ba đường:
	 *      1. `GhiSongSongWP` — hàng đợi + lịch mỗi phút, và nó chỉ chép lượt nào đi qua `doPost`
	 *         của app gốc (tức là lượt MÁY đẩy lên). Người chấm bằng trang web của app gốc
	 *         KHÔNG đi qua `doPost`, nên không có gì để chép.
	 *      2. Kéo tay theo tháng (hàm `keo_thang()` — wp-admin, và nút ở khối này).
	 *      3. Chấm thẳng trên trạm mới `/cham-cong/` — ghi luôn vào MySQL, không qua sheet.
	 *    Thiếu cả ba thì bên kia có mà bên này không, im lặng, và không màn nào nói ra.
	 *
	 * 🔴 ĐO CHỨ ĐỪNG ĐOÁN. Trước khối này, câu trả lời cho "vì sao thiếu" chỉ có thể là phỏng
	 *    đoán — mà ba nguyên nhân trên cần ba cách sửa khác hẳn nhau. Hàm này hỏi thẳng cả hai
	 *    bên rồi đặt cạnh nhau, kể cả vế ít ai nghĩ tới: MÃ bên app không có hồ sơ bên này (khi
	 *    ấy kéo về xong người đó vẫn không hiện trong lưới, vì lưới dựng hàng theo hồ sơ).
	 *
	 * ⚠️ KHÔNG GHI GÌ CẢ. Đây là phép đo. Ghi là việc của `keo_thang( …, false )`.
	 *
	 * @param string $coso  tên cơ sở (không tiền tố `CS_`).
	 * @param string $thang dạng `yyyy-MM` (khuôn của màn Bảng công; đổi sang `MM-yyyy` khi gọi app).
	 */
	public static function doi_chieu_thang( $coso, $thang ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso || ! preg_match( '/^(\d{4})-(\d{2})$/', (string) $thang, $m_th ) ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở hoặc tháng không đúng khuôn yyyy-MM.' );
		}
		$thang_app = $m_th[2] . '-' . $m_th[1];

		$r = VHCC_CauNoi::goi( 'ccXuatChamCong', array( VHCC_May::pin(), $coso, $thang_app ) );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $r['error'] ) ? $r['error']
				: 'Không gọi được app gốc.' );
		}
		$d = (array) $r['data'];
		if ( empty( $d['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $d['error'] ) ? $d['error']
				: 'App gốc chối: có thể chưa dán bản CauNoiChamCong.gs mới (hàm ccXuatChamCong).' );
		}
		if ( ! empty( $d['khongCoSheet'] ) ) {
			return array( 'ok' => true, 'khong_co_sheet' => true, 'nguoi' => array(),
				'thieu_wp' => 0, 'thieu_app' => 0, 'lech' => 0, 'ma_la' => array() );
		}

		/* ---- bên APP ---- */
		$app = array();   // [ma][ngay] = ['vao'=>giây|null, 'ra'=>giây|null]
		$ten_app = array();
		foreach ( (array) ( isset( $d['rows'] ) ? $d['rows'] : array() ) as $ng ) {
			$ng = (array) $ng;
			list( $ma, $ht_bo ) = VHCC_Nhan::tach_hau_to(
				trim( (string) ( isset( $ng['ma'] ) ? $ng['ma'] : '' ) ) );
			if ( '' === $ma ) { continue; }
			$ten_app[ $ma ] = trim( (string) ( isset( $ng['ten'] ) ? $ng['ten'] : '' ) );
			foreach ( (array) ( isset( $ng['ngay'] ) ? $ng['ngay'] : array() ) as $o ) {
				$o = (array) $o;
				$ngay = trim( (string) ( isset( $o['date'] ) ? $o['date'] : '' ) );
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { continue; }
				if ( 0 !== strpos( $ngay, $thang . '-' ) ) { continue; }
				foreach ( array( 'vao', 'ra' ) as $k ) {
					$gio = trim( (string) ( isset( $o[ $k ] ) ? $o[ $k ] : '' ) );
					if ( '' === $gio ) { continue; }
					$giay = VHCC_DB::giay( $gio );
					if ( null === $giay ) { continue; }
					if ( ! isset( $app[ $ma ][ $ngay ] ) ) {
						$app[ $ma ][ $ngay ] = array( 'vao' => null, 'ra' => null );
					}
					$app[ $ma ][ $ngay ][ $k ] = (int) $giay;
				}
			}
		}

		/* ---- bên WORDPRESS ---- */
		$wp = array();
		$ten_wp = array();
		$rows_wp = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, ho_ten, ngay, gio_vao_giay, gio_ra_giay FROM '
			. VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s AND ngay LIKE %s',
			$coso, $wpdb->esc_like( $thang . '-' ) . '%' ) );
		foreach ( (array) $rows_wp as $x ) {
			$ma = trim( (string) $x['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$ngay = (string) $x['ngay'];
			$ten_wp[ $ma ] = trim( (string) $x['ho_ten'] );
			/* Cùng một ngày có thể có nhiều HÀNG (hậu tố TT/TG/…): gộp lại thành "ngày này có
			   giờ vào / giờ ra hay chưa" — đúng mức mà bên app so được, vì sheet không có hậu tố. */
			if ( ! isset( $wp[ $ma ][ $ngay ] ) ) {
				$wp[ $ma ][ $ngay ] = array( 'vao' => null, 'ra' => null );
			}
			foreach ( array( 'vao' => 'gio_vao_giay', 'ra' => 'gio_ra_giay' ) as $k => $cot ) {
				if ( null === $x[ $cot ] || '' === $x[ $cot ] ) { continue; }
				if ( null === $wp[ $ma ][ $ngay ][ $k ] ) { $wp[ $ma ][ $ngay ][ $k ] = (int) $x[ $cot ]; }
			}
		}

		/* ---- đặt cạnh nhau ---- */
		$nguoi = array();
		$t_wp = 0; $t_app = 0; $t_lech = 0;
		$ma_het = array_unique( array_merge( array_keys( $app ), array_keys( $wp ) ) );
		sort( $ma_het );
		foreach ( $ma_het as $ma ) {
			$hs  = VHCC_NhanSu::ho_so( $ma );
			$mot = array(
				'ma'        => $ma,
				'ten'       => $hs ? trim( (string) $hs['ho_ten'] )
					: ( isset( $ten_app[ $ma ] ) && '' !== $ten_app[ $ma ] ? $ten_app[ $ma ]
						: ( isset( $ten_wp[ $ma ] ) ? $ten_wp[ $ma ] : '' ) ),
				'co_ho_so'  => (bool) $hs,
				'thieu_wp'  => array(),   // app có, WordPress không
				'thieu_app' => array(),   // WordPress có, app không
				'lech'      => array(),   // hai bên cùng có nhưng giờ khác nhau
			);
			$ngay_het = array_unique( array_merge(
				array_keys( isset( $app[ $ma ] ) ? $app[ $ma ] : array() ),
				array_keys( isset( $wp[ $ma ] ) ? $wp[ $ma ] : array() ) ) );
			sort( $ngay_het );
			foreach ( $ngay_het as $ngay ) {
				$a = isset( $app[ $ma ][ $ngay ] ) ? $app[ $ma ][ $ngay ] : null;
				$w = isset( $wp[ $ma ][ $ngay ] ) ? $wp[ $ma ][ $ngay ] : null;
				if ( $a && ! $w ) { $mot['thieu_wp'][] = $ngay; $t_wp++; continue; }
				if ( $w && ! $a ) { $mot['thieu_app'][] = $ngay; $t_app++; continue; }
				if ( ! $a || ! $w ) { continue; }
				/* Cùng ngày: thiếu HẲN một giờ bên này mà bên kia có thì cũng là "thiếu", không
				   phải "lệch" — nó là một nửa ngày công chưa sang, và sửa bằng đúng nút Nạp về. */
				$thieu_nua = ( null !== $a['vao'] && null === $w['vao'] )
					|| ( null !== $a['ra'] && null === $w['ra'] );
				if ( $thieu_nua ) { $mot['thieu_wp'][] = $ngay; $t_wp++; continue; }
				if ( $a['vao'] !== $w['vao'] || $a['ra'] !== $w['ra'] ) {
					$mot['lech'][] = $ngay;
					$t_lech++;
				}
			}
			if ( $mot['thieu_wp'] || $mot['thieu_app'] || $mot['lech'] || ! $mot['co_ho_so'] ) {
				$nguoi[] = $mot;
			}
		}
		/* 🔴 MÃ BÊN APP MÀ BÊN NÀY KHÔNG CÓ HỒ SƠ — kể riêng ra. Kéo về xong người ấy VẪN không
		   hiện trong lưới (lưới dựng hàng theo sổ nhân sự), nên nếu chỉ đếm "đã kéo N lượt" thì
		   nhìn như xong mà màn hình không đổi gì. */
		$ma_la = array();
		foreach ( $nguoi as $x ) {
			if ( ! $x['co_ho_so'] ) { $ma_la[] = $x['ma'] . ( '' !== $x['ten'] ? ' (' . $x['ten'] . ')' : '' ); }
		}
		return array( 'ok' => true, 'nguoi' => $nguoi, 'thieu_wp' => $t_wp,
			'thieu_app' => $t_app, 'lech' => $t_lech, 'ma_la' => $ma_la,
			'so_app' => count( $app ), 'so_wp' => count( $wp ) );
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
