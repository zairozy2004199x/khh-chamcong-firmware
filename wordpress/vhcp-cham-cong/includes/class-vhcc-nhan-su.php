<?php
/**
 * NHÂN SỰ: hồ sơ · cơ sở · bộ phận — đọc/ghi trên MySQL.
 *
 * =============================================================================================
 * QUYỀN CHIA HAI BẬC, VÀ ĐỪNG GỘP LẠI
 * =============================================================================================
 * Anh Thắng 07/08/2026: *"quyền quản lý nhân viên cửa hàng anh sẽ bàn giao cho cửa đó luôn"*, rồi
 * *"chọn cht phân quyền theo mức"*. Nên có ĐÚNG hai cửa, và ranh giới không phải cho gọn:
 *
 *   `co_sua_ho_so`   Admin · Quản lý · Cửa hàng trưởng
 *       Việc HÀNG NGÀY trong phạm vi cửa hàng mình: sửa SĐT, địa chỉ, chức vụ, nhiệm vụ, trạng
 *       thái làm việc của người ĐANG ở cửa hàng đó.
 *
 *   `co_quan_tri_nv` Admin · Quản lý
 *       Việc ra NGOÀI phạm vi một cửa hàng:
 *         · TẠO hồ sơ mới      — Mã NV dùng chung CẢ CHUỖI, cấp trùng là gộp công hai người;
 *         · ĐỔI cửa hàng       — chuyển người giữa hai cửa hàng là chuyển cả công và lương;
 *         · XOÁ hồ sơ, mã song song, nhập hàng loạt, duyệt yêu cầu.
 *
 *   `co_xem_luong`   Admin · Quản lý
 *       Ô Lương cơ bản + số tài khoản + ngân hàng. Cửa hàng trưởng KHÔNG thấy — họ sửa hồ sơ
 *       người của mình được, nhưng lương thì không.
 *
 * ⚠️ Cửa hàng trưởng sửa được hồ sơ KHÔNG có nghĩa là sửa được của bất kỳ ai: còn phải qua
 *    `co_quyen_coso`. Thiếu chốt đó thì một cửa hàng trưởng sửa hồ sơ người cửa hàng khác.
 * ⚠️ Vai trò NHÂN VIÊN không xem được hồ sơ ai, kể cả cửa hàng ghi trong dòng phân quyền của họ —
 *    cửa hàng đó chỉ để biết chấm công online ghi vào đâu, KHÔNG phải quyền xem.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NhanSu {

	const R_ADMIN   = 'ADMIN';
	const R_QUAN_LY = 'QUAN_LY';
	const R_CHT     = 'CUA_HANG_TRUONG';
	const R_NV      = 'NHAN_VIEN';
	const R_KE_TOAN = 'KE_TOAN';

	const LOI_QT = 'Việc này chỉ Admin / Quản lý làm được (ảnh hưởng ngoài phạm vi cửa hàng).';

	/** Ô chỉ Admin/Quản lý được thấy và sửa. */
	const O_LUONG = array( 'luong_co_ban', 'so_tai_khoan', 'ngan_hang' );

	/**
	 * ⚠️ CÒN ĐÂY CHỈ ĐỂ MÃ CŨ KHÔNG GÃY. Đừng dùng để phân quyền — dùng `VHCC_Vai::cua()`.
	 *
	 * 🔴 Bản cũ của hàm này là `strtoupper( $u['role'] )` rồi so với 'QUAN_LY' / 'KE_TOAN'.
	 *    Thẻ phiên mang vai trò dạng tên tiếng Việt ('Quản lý'), và `strtoupper` của PHP KHÔNG
	 *    nâng được chữ có dấu — 'Quản lý' thành 'QUảN Lý', không khớp gì cả. Kết quả: mọi vai
	 *    trừ 'Admin' đều bị chối ở mọi cửa hỏi qua tệp này, im lặng, suốt nhiều tháng. Đó chính
	 *    là *"xung đột phân quyền"* anh Thắng gặp.
	 */
	private static function vt( $u ) {
		return VHCC_Vai::cua( $u );
	}

	/**
	 * Sửa được HỒ SƠ nhân sự (thông tin cá nhân, hợp đồng, PIN).
	 * Từ 25/08/2026: **Kế toán trở lên**. Trước đây Cửa hàng trưởng cũng sửa được; mô hình anh
	 * Thắng chốt không giao việc hồ sơ cho cửa hàng — họ chấm công, xem công, lên lịch, báo lỗi.
	 * ⚠️ Đây KHÔNG phải quyền sửa LỊCH LÀM VIỆC. Lịch hỏi `lich_lam` (Cửa hàng trưởng trở lên).
	 */
	public static function co_sua_ho_so( $u ) {
		return VHCC_Vai::duoc( $u, 'ho_so' );
	}

	/** Việc ảnh hưởng NGOÀI phạm vi một cửa hàng: tăng cường, khoá bảng, xoá thống kê. Quản lý+. */
	public static function co_quan_tri_nv( $u ) {
		return VHCC_Vai::duoc( $u, 'ngoai_coso' );
	}

	/** Thấy ô Lương cơ bản / số tài khoản / ngân hàng trong hồ sơ. Kế toán+. */
	public static function co_xem_luong( $u ) {
		return VHCC_Vai::duoc( $u, 'xem_luong_hs' );
	}

	/**
	 * Người này có quyền trên cơ sở đó không.
	 *
	 * ⚠️ NHÂN VIÊN trả false LUÔN — trước cả phép so danh sách cơ sở. Cửa hàng ghi trong dòng
	 *    phân quyền của nhân viên chỉ để biết chấm công ghi vào đâu, KHÔNG phải quyền xem.
	 * ⚠️ Quản lý trở lên: MỌI cơ sở, không cần khai — đúng `cong_tat_ca` trong bảng vai.
	 *    Cửa hàng trưởng: chỉ những cơ sở đã khai cho họ.
	 */
	public static function co_quyen_coso( $u, $coso ) {
		if ( ! VHCC_Vai::duoc( $u, 'cong_coso' ) ) { return false; }   // Nhân viên dừng ở đây
		if ( VHCC_Vai::duoc( $u, 'cong_tat_ca' ) ) { return true; }
		$coso = self::chuan_coso( $coso );
		if ( '' === $coso ) { return false; }
		foreach ( self::ds_coso_cua( $u ) as $x ) {
			if ( strtolower( $x ) === strtolower( $coso ) ) { return true; }
		}
		return false;
	}

	public static function ds_coso_cua( $u ) {
		$ds = array();
		foreach ( explode( ',', isset( $u['coso'] ) ? (string) $u['coso'] : '' ) as $x ) {
			$x = self::chuan_coso( $x );
			if ( '' !== $x ) { $ds[] = $x; }
		}
		return $ds;
	}

	/**
	 * MỘT tên cơ sở, đã chuẩn hoá.
	 *
	 * 🔴 CẮT TẠI DẤU PHẨY ĐẦU TIÊN. Anh Thắng 30/08/2026: *"Nhân viên chỉ có 2 cơ sở, tại sao
	 *    sinh ra 2 hàng chấm công"*. Thẻ phiên của người làm ở hai nơi mang CẢ HAI tên nối
	 *    bằng `', '` — `VHCC_Auth::users_cua()` cố ý nối vậy để `ds_coso_cua()` tách ra được.
	 *    Nhưng hàm này là cửa của những nơi cần MỘT tên, và chúng nhận nguyên chuỗi ghép rồi
	 *    dùng như một cơ sở có thật: bảng chấm công đẻ ra hàng mang tên
	 *    `"POSH_HCM, (PART TIME )_POSH+JP"`, ô xổ cơ sở của màn quản trị (đọc `SELECT DISTINCT
	 *    coso`) hiện luôn cái tên ma ấy, và chọn phải nó thì hàng chính TRỐNG còn toàn bộ công
	 *    thật rơi xuống hàng "cũng làm ở".
	 *
	 * ⚠️ CẮT chứ không phải báo lỗi: dữ liệu cũ trong sổ đã có những tên như vậy rồi, ném lỗi
	 *    ở đây là khoá cửa chấm công của chính những người đang vấp. Cắt thì họ chấm được ngay,
	 *    vào đúng cơ sở chính — còn danh sách ĐỦ cả hai cơ sở là việc của `ds_coso_cua()`.
	 *
	 * ⚠️ Tên cơ sở KHÔNG được chứa dấu phẩy — cả hệ thống đã ngầm định thế từ lâu, vì
	 *    `ds_coso_cua()` và `ds_coso_cua_nv()` đều `explode(',')`. Nên cắt ở đây không làm mất
	 *    một cái tên hợp lệ nào.
	 */
	public static function chuan_coso( $s ) {
		$s = (string) $s;
		$phay = strpos( $s, ',' );
		if ( false !== $phay ) { $s = substr( $s, 0, $phay ); }
		/* 🔴 CẮT KHOẢNG TRẮNG TRƯỚC, GỠ TIỀN TỐ SAU — thứ tự này không đổi được.
		   Làm ngược lại thì `^CS_` không khớp phần tử thứ hai trở đi của chuỗi nối bằng `', '`:
		   `ds_coso_cua()` tách `'CS_A, CS_B'` ra thành `'CS_A'` và `' CS_B'`, cái sau còn
		   nguyên dấu cách đầu nên gỡ hụt tiền tố và ở lại là `'CS_B'`. Trong khi hồ sơ của
		   người bên ấy đã chuẩn hoá thành `'B'`. Hai chuỗi khác nhau -> `co_quyen_coso()` chối,
		   và người làm ở hai nơi CHỈ THẤY MỘT — đúng cái anh Thắng vấp ngày 30/08. Lỗi im
		   lặng: cơ sở đầu tiên vẫn đúng nên nhìn qua tưởng hệ chạy. */
		return trim( preg_replace( '/^CS_/', '', trim( $s ) ) );
	}

	/**
	 * MỌI CƠ SỞ MỘT HỒ SƠ LÀM VIỆC — không phân biệt chính với phụ.
	 *
	 * 🔴 Anh Thắng 31/08/2026: *"việc phân thêm cơ sở phụ nó đang bị lẫn lộn. Thay vì việc cơ sở
	 *    phụ. Thì nhân viên sẽ được tích vào cơ sở nào thì sẽ được có mặt làm việc đầy đủ tại
	 *    chi nhánh đó"*.
	 *
	 *    Trước đó `cua_hang` là cơ sở "thật", còn `coso_phu` chỉ là ghi chú: mọi câu lọc theo cơ
	 *    sở đều hỏi mỗi `cua_hang`, nên người tích cơ sở B làm phụ KHÔNG hiện trong danh sách
	 *    nhân sự của B, cửa hàng trưởng B không thấy họ, và bảng đếm "ai chưa khai lương" của B
	 *    cũng bỏ sót. Tích một ô mà không có mặt ở đâu cả — đúng nghĩa lẫn lộn.
	 *
	 * ⚠️ HAI CỘT VẪN CÒN, vì chúng là hình dạng dữ liệu mà hệ ghế, sổ lương và bản kéo từ sheet
	 *    đang đọc. Nhưng từ đây MỌI câu hỏi "người này làm ở đâu" đi qua hàm này, và nó trả về
	 *    một danh sách BÌNH ĐẲNG. `cua_hang` chỉ còn là "cơ sở đứng đầu danh sách".
	 */
	public static function ds_coso_hs( $hs ) {
		$ds  = array();
		$goc = array(
			isset( $hs['cua_hang'] ) ? (string) $hs['cua_hang'] : '',
			isset( $hs['coso_phu'] ) ? (string) $hs['coso_phu'] : '',
		);
		foreach ( $goc as $chuoi ) {
			foreach ( explode( ',', $chuoi ) as $x ) {
				$x = self::chuan_coso( $x );
				if ( '' === $x ) { continue; }
				$k = self::chu_thuong( $x );
				if ( ! isset( $ds[ $k ] ) ) { $ds[ $k ] = $x; }
			}
		}
		return array_values( $ds );
	}

	/** Hồ sơ này có làm ở cơ sở ấy không — so KHÔNG phân biệt hoa thường. */
	public static function hs_thuoc_coso( $hs, $coso ) {
		$coso = self::chu_thuong( self::chuan_coso( $coso ) );
		if ( '' === $coso ) { return false; }
		foreach ( self::ds_coso_hs( $hs ) as $x ) {
			if ( self::chu_thuong( $x ) === $coso ) { return true; }
		}
		return false;
	}

	/**
	 * Người đang đăng nhập có được xem hồ sơ này không.
	 *
	 * 🔴 ĐỦ MỘT CƠ SỞ TRÙNG LÀ ĐƯỢC. Cửa hàng trưởng của B phải thấy người tích B, dù trong hồ
	 *    sơ họ B đứng thứ hai — đó chính là *"có mặt làm việc đầy đủ tại chi nhánh đó"*.
	 */
	public static function co_quyen_ho_so( $u, $hs ) {
		$ds = self::ds_coso_hs( $hs );
		if ( ! $ds ) { return self::co_quyen_coso( $u, '' ); }
		foreach ( $ds as $x ) {
			if ( self::co_quyen_coso( $u, $x ) ) { return true; }
		}
		return false;
	}

	/**
	 * Mệnh đề SQL "hồ sơ có làm ở cơ sở này" — NỚI RỘNG, lọc lại bằng PHP.
	 *
	 * ⚠️ `coso_phu` là một chuỗi nối bằng dấu phẩy, mà cách so chính xác trong SQL thì mỗi hệ
	 *    quản trị viết một kiểu (`CONCAT` của MySQL, `||` của SQLite). Nên ở đây chỉ NỚI bằng
	 *    `LIKE %X%` — có thể bắt thừa khi tên cơ sở này là một khúc của tên cơ sở khác — rồi
	 *    `hs_thuoc_coso()` lọc lại cho đúng. Nới rồi lọc thì sai một chiều duy nhất là đọc thừa
	 *    vài dòng; hẹp rồi thôi thì sai chiều kia, là MẤT người, và mất im lặng.
	 *
	 * @return array `sql` · `tv` (tham số cho $wpdb->prepare)
	 */
	public static function dk_sql_coso( $coso ) {
		$coso = self::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'sql' => '1=1', 'tv' => array() ); }
		/* 🔴 CỐ Ý KHÔNG `esc_like()`, VÀ ĐÂY LÀ MỘT CÁI BẪY THẬT ĐÃ SẬP.
		   Mã cơ sở của K&H đầy dấu gạch dưới (`POSH_HCM`, `FZ_SC_VIVO_T`). `esc_like()` biến
		   `_` thành `\_`, mà dấu `\` chỉ là ký tự thoát của LIKE trên MySQL — SQLite thì
		   KHÔNG, trừ khi khai `ESCAPE`. Nên cùng một câu cho hai kết quả khác nhau: trên
		   hosting thì khớp, trên máy chạy thử thì không. Loại lệch ấy tệ hơn cả một lỗi thường,
		   vì bộ thử và thật nói ngược nhau mà chẳng bên nào kêu.

		   Bỏ `esc_like()` thì `_` và `%` trong tên cơ sở thành ký tự đại diện, tức mệnh đề bắt
		   THỪA. Đúng ý: mệnh đề này vốn chỉ để NỚI, và `hs_thuoc_coso()` lọc lại cho đúng ngay
		   sau đó. Nới rồi lọc thì sai một chiều duy nhất là đọc thừa vài dòng.

		   ⚠️ NƠI GỌI PHẢI LỌC LẠI. Không lọc thì đây thành lỗ: lọc cơ sở "TA" kéo về cả người
		      của "BETA", tức lộ hồ sơ của chi nhánh khác. Phá thử canh đúng chỗ đó. */
		return array(
			'sql' => '(LOWER(cua_hang)=LOWER(%s) OR LOWER(coso_phu) LIKE LOWER(%s))',
			'tv'  => array( $coso, '%' . $coso . '%' ),
		);
	}

	/**
	 * Hồ sơ này đã nghỉ việc chưa — đọc từ ô "Trạng thái làm việc".
	 *
	 * 🔴 MỘT NƠI DUY NHẤT quyết định câu đó. Ô này người ta gõ tay, nên trong sổ có đủ kiểu:
	 *    "Đã nghỉ", "Nghỉ việc", "đã nghỉ 12/2025", "NGHỈ". Luật là *có chữ "nghỉ"*, và luật
	 *    ấy phải giống nhau ở mọi chỗ hỏi: cổng trạm chấm công, bảng đối chiếu máy, danh sách
	 *    lương. Hai nơi tự viết luật riêng thì có ngày một người nghỉ vẫn chấm công được ở cửa
	 *    này trong khi cửa kia đã chặn — và không ai biết bên nào đúng.
	 *
	 * ⚠️ Ô TRỐNG = ĐANG LÀM. Sổ cũ nhiều dòng bỏ trống ô này; coi trống là nghỉ thì khoá cửa
	 *    của phần lớn công ty ngay lượt cài đặt đầu tiên.
	 */
	public static function da_nghi( $trang_thai ) {
		$t = trim( (string) $trang_thai );
		if ( '' === $t ) { return false; }
		return false !== strpos( self::chu_thuong( $t ), 'nghỉ' );
	}

	/** Chữ thường có dấu — mb_strtolower khi có, strtolower khi không (host cũ thiếu mbstring). */
	public static function chu_thuong( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	}

	/**
	 * Đọc một ô TIỀN người ta gõ tay — bản dịch `_vpSoTien`.
	 *
	 * ⚠️ PHẢI phân biệt kiểu VIỆT và kiểu ANH, không được vét sạch dấu chấm rồi thôi:
	 *      13.000.000  (kiểu Việt: chấm là phân cách nghìn)  -> 13000000
	 *      13,000,000  (kiểu Anh:  phẩy là phân cách nghìn)  -> 13000000
	 *      13,5        (kiểu Việt: phẩy là dấu thập phân)    -> 13.5
	 *    Bản đầu của hàm này em viết `preg_replace('/[^0-9.]/','')` rồi `(float)` — nghĩa là
	 *    "13.000.000" thành **13 đồng**. Lương một người thành 13 đồng, mà ô vẫn có số nên bảng
	 *    lương trông vẫn bình thường. Đây đúng loại sai mà cả việc này phải tránh.
	 */
	public static function so_tien( $v ) {
		if ( is_int( $v ) || is_float( $v ) ) { return is_finite( (float) $v ) ? (float) $v : 0.0; }
		$s = trim( (string) $v );
		if ( '' === $s ) { return 0.0; }
		$s = preg_replace( '/[^\d.,-]/', '', $s );                       // bỏ 'đ', 'VND', khoảng trắng
		if ( preg_match( '/^-?\d{1,3}(\.\d{3})+$/', $s ) ) {
			$s = str_replace( '.', '', $s );                              // 13.000.000  (kiểu VN)
		} elseif ( preg_match( '/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s ) ) {
			$s = str_replace( ',', '', $s );                              // 13,000,000.5 (kiểu Anh)
		} else {
			$s = str_replace( ',', '.', $s );                             // '13,5' -> 13.5
		}
		return is_numeric( $s ) ? (float) $s : 0.0;
	}

	// ======================================================================= đọc

	/** Danh sách hồ sơ. Ô lương bị BỎ HẲN khỏi kết quả khi không có quyền, không chỉ ẩn trên màn. */
	public static function ds_nhan_vien( $u, $coso = '', $tim = '' ) {
		global $wpdb;
		$dk = array( '1=1' );
		$tv = array();
		$coso = self::chuan_coso( $coso );
		/* Lọc theo cơ sở phải tính CẢ cơ sở người ta tích thêm — xem `dk_sql_coso()`. Hỏi mỗi
		   `cua_hang` là danh sách nhân sự của một chi nhánh thiếu đúng những người hay chạy
		   giữa hai chi nhánh, tức là những người cần theo dõi nhất. */
		if ( '' !== $coso ) {
			$dk_cs = self::dk_sql_coso( $coso );
			$dk[]  = $dk_cs['sql'];
			$tv    = array_merge( $tv, $dk_cs['tv'] );
		}
		if ( '' !== trim( (string) $tim ) ) {
			$like = '%' . $wpdb->esc_like( trim( $tim ) ) . '%';
			$dk[] = '(ma_nv LIKE %s OR ho_ten LIKE %s OR sdt LIKE %s OR cccd LIKE %s)';
			$tv = array_merge( $tv, array( $like, $like, $like, $like ) );
		}
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY cua_hang, ho_ten';
		$rows = VHCC_DB::rows( $tv ? $wpdb->prepare( $sql, $tv ) : $sql );

		$xem_luong = self::co_xem_luong( $u );
		$out = array();
		foreach ( $rows as $r ) {
			/* Mệnh đề SQL ở trên NỚI (LIKE), nên lọc lại cho đúng ở đây. */
			if ( '' !== $coso && ! self::hs_thuoc_coso( $r, $coso ) ) { continue; }
			// Cửa hàng trưởng thấy người của MỌI cơ sở mình quản, kể cả người tích thêm.
			if ( ! self::co_quyen_ho_so( $u, $r ) ) { continue; }
			if ( ! $xem_luong ) {
				/* BỎ khỏi dữ liệu, không phải ẩn bằng CSS. Ẩn trên màn thì số vẫn đi xuống trình
				   duyệt và ai mở công cụ nhà phát triển là đọc được. */
				foreach ( self::O_LUONG as $o ) { unset( $r[ $o ] ); }
			}
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * DÒ HỒ SƠ TRÙNG — trùng HỌ TÊN, hoặc trùng MÃ NV sau khi chuẩn hoá.
	 *
	 * =========================================================================================
	 * Anh Thắng 27/08/2026: *"Nhân viên nào trùng tên, trùng Mã NV thì đưa lên đầu nhé"*.
	 * =========================================================================================
	 * 🔴 VÌ SAO ĐÁNG DÒ. Hồ sơ nạp từ nhiều đợt .csv khác nhau, và đã từng có một đợt hiểu nhầm
	 * cột "ID" của Sheets (số thứ tự dòng) thành Mã NV — nên trong sổ còn lẫn mã `1`, `15`, `996`
	 * bên cạnh mã thật. Hậu quả không nằm ở chỗ xấu mắt: MÃ LÀ KHOÁ. Hai hồ sơ của cùng một
	 * người mang hai mã là công của họ bị chẻ đôi, mỗi nửa một bảng lương; hai người khác nhau
	 * lỡ mang cùng một mã là công của người này cộng vào người kia.
	 *
	 * Cả hai kiểu hỏng ấy đều IM LẶNG — không có lỗi nào phát ra, chỉ có con số cuối tháng sai.
	 * Nên trang phải tự chỉ ra, và chỉ lên đầu, chứ không để anh Thắng lật 5 trang đi tìm.
	 *
	 * ⚠️ SO SAU KHI CHUẨN HOÁ, KHÔNG SO THÔ. "Nguyễn Thị A" và "NGUYỄN THỊ  A" là một người mà
	 *    so thô ra hai. Mã "mnnv1" và "MNNV1 " cũng vậy — mà đúng cặp ấy mới nguy hiểm nhất:
	 *    CSDL coi là hai dòng khác nhau nên không hề chặn, còn người đọc thì thấy y hệt nhau.
	 *
	 * @param array $ds Danh sách hồ sơ (mỗi phần tử có `ma_nv`, `ho_ten`).
	 * @return array [ ma_nv => array( 'ten' => bool, 'ma' => bool ) ] — chỉ những hồ sơ CÓ trùng.
	 */
	public static function dau_hieu_trung( $ds ) {
		$dem_ten = array();
		$dem_ma  = array();
		$khoa    = array();

		foreach ( (array) $ds as $r ) {
			$ma  = isset( $r['ma_nv'] ) ? (string) $r['ma_nv'] : '';
			$ten = isset( $r['ho_ten'] ) ? (string) $r['ho_ten'] : '';
			$k_ten = self::khoa_so( $ten );
			/* Mã so theo chữ HOA + bỏ hết khoảng trắng — không bỏ dấu, vì mã không có dấu và
			   một mã lỡ có dấu thì đó là chuyện khác, phải thấy nó là khác. */
			$k_ma  = strtoupper( preg_replace( '/\s+/', '', trim( $ma ) ) );
			$khoa[] = array( 'ma' => $ma, 'k_ten' => $k_ten, 'k_ma' => $k_ma,
				'coso' => isset( $r['cua_hang'] ) ? (string) $r['cua_hang'] : '' );
			if ( '' !== $k_ten ) { $dem_ten[ $k_ten ] = ( isset( $dem_ten[ $k_ten ] ) ? $dem_ten[ $k_ten ] : 0 ) + 1; }
			if ( '' !== $k_ma )  { $dem_ma[ $k_ma ]   = ( isset( $dem_ma[ $k_ma ] ) ? $dem_ma[ $k_ma ] : 0 ) + 1; }
		}

		/* =====================================================================================
		 * 🔴 TRÙNG TÊN CÙNG MỘT CƠ SỞ LÀ CHUYỆN KHÁC HẲN TRÙNG TÊN KHÁC CƠ SỞ.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026: *"1 nhân viên mà làm 2 cơ sở, nên hệ thống báo trùng. có ảnh
		 * hưởng gì không"*.
		 *
		 * Có, và nặng — nhưng chỉ ở MỘT trong hai kiểu, mà nhãn cũ gộp cả hai làm một:
		 *
		 *   • Khác cơ sở  -> rất có thể là hai người thật, tình cờ trùng tên. Bình thường.
		 *   • CÙNG cơ sở  -> gần như chắc là MỘT người bị tạo hai hồ sơ. Với cả hệ, hai mã NV
		 *     là hai người khác nhau: công chia đôi (không mã nào đủ ngày công chuẩn), lương
		 *     tính theo hai nửa, mỗi hồ sơ một PIN nên đăng nhập bằng PIN nào chỉ thấy nửa ấy.
		 *
		 * ⚠️ Gộp hai kiểu vào một nhãn là bắt người đọc tự phân loại 400 hồ sơ. Mà cái gì bắt
		 *    người ta tự phân loại thì họ phân loại một lần rồi thôi đọc.
		 *
		 * ⚠️ CƠ SỞ SO CÙNG THƯỚC VỚI CẢ HỆ: `strtoupper( chuan_coso() )`. Bỏ qua hoa/thường,
		 *    nhưng KHÔNG gộp "POSH_HCM" với "posh hcm" — và đúng ra là không được gộp: bảng
		 *    công, chùm cơ sở, quyền theo cơ sở đều coi hai chuỗi ấy là HAI cơ sở. Nới riêng ở
		 *    đây là màn này nói một đằng, bảng công tính một nẻo.
		 */
		$cs_theo_ten = array();
		/* Nhóm theo (tên, cơ sở) — thêm 29/08/2026 để biết CHÍNH XÁC mã nào đứng cùng nhóm với
		   mã nào, phục vụ nút "Ghép với hồ sơ kia" ngay tại dòng (xem viec_ghep_voi() ở
		   class-vhcc-trang-ns.php) — trước đây hàm chỉ ĐẾM số lượng trong nhóm, không giữ lại
		   DANH SÁCH mã trong nhóm nên không trỏ được sang "hồ sơ kia" là mã nào. */
		$ma_theo_nhom = array();
		foreach ( (array) $ds as $r ) {
			$k_ten = self::khoa_so( isset( $r['ho_ten'] ) ? (string) $r['ho_ten'] : '' );
			if ( '' === $k_ten ) { continue; }
			$cs = strtoupper( self::chuan_coso( isset( $r['cua_hang'] ) ? $r['cua_hang'] : '' ) );
			if ( ! isset( $cs_theo_ten[ $k_ten ] ) ) { $cs_theo_ten[ $k_ten ] = array(); }
			$cs_theo_ten[ $k_ten ][ $cs ] = ( isset( $cs_theo_ten[ $k_ten ][ $cs ] )
				? $cs_theo_ten[ $k_ten ][ $cs ] : 0 ) + 1;
			$nhom_k = $k_ten . '·' . $cs;
			if ( ! isset( $ma_theo_nhom[ $nhom_k ] ) ) { $ma_theo_nhom[ $nhom_k ] = array(); }
			$ma_nv_r = isset( $r['ma_nv'] ) ? (string) $r['ma_nv'] : '';
			if ( '' !== $ma_nv_r ) { $ma_theo_nhom[ $nhom_k ][] = $ma_nv_r; }
		}

		$ra = array();
		foreach ( $khoa as $x ) {
			$t = ( '' !== $x['k_ten'] && $dem_ten[ $x['k_ten'] ] > 1 );
			$m = ( '' !== $x['k_ma'] && $dem_ma[ $x['k_ma'] ] > 1 );
			if ( ! $t && ! $m ) { continue; }
			/* Trùng tên NGAY TRONG cơ sở của chính hồ sơ này — không phải "có ai đó cùng tên ở
			   đâu đó". Hai người cùng tên ở hai cơ sở khác nhau thì mỗi người vẫn đứng một
			   mình ở cơ sở của họ, và đó là chuyện bình thường. */
			$mot_nguoi = false;
			$doi = array();
			if ( $t ) {
				$cs_x = strtoupper( self::chuan_coso( isset( $x['coso'] ) ? $x['coso'] : '' ) );
				$mot_nguoi = ( isset( $cs_theo_ten[ $x['k_ten'] ][ $cs_x ] )
					&& $cs_theo_ten[ $x['k_ten'] ][ $cs_x ] > 1 );
				if ( $mot_nguoi ) {
					$nhom_k = $x['k_ten'] . '·' . $cs_x;
					foreach ( (array) ( isset( $ma_theo_nhom[ $nhom_k ] ) ? $ma_theo_nhom[ $nhom_k ] : array() ) as $ma_doi ) {
						if ( $ma_doi !== $x['ma'] ) { $doi[] = $ma_doi; }
					}
				}
			}
			$ra[ $x['ma'] ] = array( 'ten' => $t, 'ma' => $m, 'motNguoi' => $mot_nguoi, 'doi' => $doi );
		}
		return $ra;
	}

	/**
	 * ĐẾM DỮ LIỆU CHẤM CÔNG CỦA MỘT LOẠT MÃ — MỘT LƯỢT GỌI, không hỏi từng mã.
	 *
	 * Anh Thắng 29/08/2026, đứng trước cặp "một người hai hồ sơ?" (MNNV2KVC0113/0177, cùng tên
	 * Trần Minh Chiến, cùng cơ sở): *"giờ anh muốn xóa, nhưng không biết ai là thật, có ô nào
	 * hiện ra người có dữ liệu và người không có dữ liệu không — dữ liệu chấm công... để biết
	 * người đó có hoạt động"*.
	 *
	 * Nhãn "một người hai hồ sơ?" chỉ NGHI — nó không nói mã nào là mã dùng thật, mã nào là mã
	 * tạo lỡ (bấm nhầm lúc nhập, test, hoặc cơ sở cũ không dùng nữa). Đếm số lượt CHẤM CÔNG THẬT
	 * của mỗi mã trong cặp trả lời đúng câu đó: mã có hàng trăm lượt là mã đang dùng, mã 0 lượt
	 * gần như chắc là hồ sơ rác — xoá mã đó không mất công của ai.
	 *
	 * ⚠️ CHỈ ĐẾM MÃ CẦN, KHÔNG ĐẾM CẢ SỔ. Trang Nhân sự có thể tới hàng trăm hồ sơ; đa số không
	 *    trùng gì cả. Gọi hàm này với đúng danh sách mã đang bị gắn cờ trùng (`array_keys($trung)`
	 *    ở nơi gọi) — một câu SQL `GROUP BY` cho đúng nhóm nhỏ đó, không quét toàn bảng chấm công
	 *    theo từng mã một (N+1) và không đếm luôn cả những mã chẳng ai nghi ngờ.
	 *
	 * @param array $ds_ma Danh sách Mã NV cần đếm.
	 * @return array [ ma_nv => [ 'luot'=>int, 'tu'=>'Y-m-d'|'', 'den'=>'Y-m-d'|'' ] ] — mã nào
	 *   không có dòng nào trong bảng `cham_cong` thì KHÔNG có khoá trong mảng trả về (0 lượt).
	 */
	public static function dem_cham_cong_theo_ma( $ds_ma ) {
		global $wpdb;
		$ds = array();
		foreach ( (array) $ds_ma as $m ) {
			$m = trim( (string) $m );
			if ( '' !== $m ) { $ds[ $m ] = true; }
		}
		$ds = array_keys( $ds );
		if ( ! $ds ) { return array(); }

		$t  = VHCC_DB::t( 'cham_cong' );
		$ph = implode( ',', array_fill( 0, count( $ds ), '%s' ) );
		$sql = $wpdb->prepare(
			"SELECT ma_nv, COUNT(*) AS luot, MIN(ngay) AS tu, MAX(ngay) AS den
			 FROM $t WHERE ma_nv IN ($ph) GROUP BY ma_nv", $ds );

		$ra = array();
		foreach ( VHCC_DB::rows( $sql ) as $r ) {
			$ra[ (string) $r['ma_nv'] ] = array( 'luot' => (int) $r['luot'],
				'tu' => (string) $r['tu'], 'den' => (string) $r['den'] );
		}
		return $ra;
	}

	/**
	 * Khoá so sánh của một họ tên: BỎ DẤU, hạ chữ, gộp khoảng trắng, bỏ ký tự lạ.
	 *
	 * 🔴 PHẢI BỎ DẤU TRƯỚC KHI LỌC KÝ TỰ. `chu_thuong()` chỉ hạ chữ, không bỏ dấu — nên
	 *    `preg_replace('/[^a-z0-9]+/')` chạy ngay sau nó sẽ XOÁ mọi chữ có dấu: "Nguyễn" thành
	 *    "nguy n". Khi ấy "Nguyễn Thị A" và "Nguyệt Thị A" cùng ra "nguy n thi a" — hai người
	 *    khác nhau bị báo là trùng tên, còn hai người trùng tên thật thì vẫn nhận ra được. Sai
	 *    kiểu ấy tệ hơn không dò: nó đẻ ra báo động giả, và báo động giả thì người ta tắt đi.
	 *    Đây đúng là lỗi em vừa mắc ở bản nháp đầu.
	 * ⚠️ Gác `method_exists` cùng hàm với lời gọi — luật `tools/test/kiem-goi-cheo.php`.
	 */
	public static function khoa_so( $s ) {
		$x = trim( (string) $s );
		if ( class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'bo_dau' ) ) {
			$x = VHCC_Vai::bo_dau( $x );          // đã hạ chữ thường luôn
		} else {
			$x = self::chu_thuong( $x );
		}
		$x = preg_replace( '/[^a-z0-9]+/', ' ', $x );
		return trim( preg_replace( '/\s+/', ' ', $x ) );
	}

	/**
	 * Xếp những hồ sơ ĐÁNG NGỜ lên đầu, giữ nguyên thứ tự cũ trong từng nhóm.
	 *
	 * ⚠️ SẮP XẾP TRƯỚC KHI CẮT TRANG. Cắt trang rồi mới xếp thì hồ sơ trùng nằm ở trang 4 vẫn
	 *    ở trang 4 — mà "đưa lên đầu" chính là để khỏi phải lật từng trang đi tìm.
	 *
	 * ⚠️ Giữ thứ tự cũ trong mỗi nhóm (`usort` của PHP 8 đã ổn định, PHP 7 thì không) — nên
	 *    kèm chỉ số làm khoá phụ, đừng phó mặc cho phiên bản PHP của hosting.
	 */
	public static function xep_trung_len_dau( $ds, $trung ) {
		$co = array();
		$khong = array();
		foreach ( (array) $ds as $r ) {
			$ma = isset( $r['ma_nv'] ) ? (string) $r['ma_nv'] : '';
			if ( isset( $trung[ $ma ] ) ) { $co[] = $r; } else { $khong[] = $r; }
		}
		return array_merge( $co, $khong );
	}

	public static function ho_so( $ma_nv ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', trim( (string) $ma_nv ) ), ARRAY_A );
	}

	/**
	 * MÃ TRÊN MÁY -> MÃ NHÂN SỰ THẬT.
	 *
	 * =============================================================================================
	 * Anh Thắng 27/08/2026: *"bắt đầu kết nối máy chấm công để tránh mất dữ liệu"*.
	 * =============================================================================================
	 * 🔴 HAI BỘ MÃ KHÁC HẲN NHAU, VÀ ĐÓ LÀ CHỖ DỮ LIỆU MÁY SẼ RƠI HẾT.
	 *    Máy ZKTeco ở xưởng trả `employeeNo` dạng `20000601` — mã ấy do người dựng máy gõ vào
	 *    từng đầu đọc, không ai đối chiếu với sổ nhân sự. Hồ sơ ở đây dùng mã dạng
	 *    `MNNV1CTY0002`. Đẩy thẳng mã máy vào bảng chấm công thì cả tháng công của cả xưởng nằm
	 *    dưới một dãy số không có hồ sơ nào: bảng đầy số, mà không số nào ra lương của ai.
	 *    Ảnh phần mềm HR V5.2 anh gửi cho thấy đúng chuyện ấy — cột Họ tên trống trơn, mỗi dòng
	 *    ghi "Chưa phân phòng", và có hẳn một nút tên là *"Phân nhân viên vào phòng / ghép mã NV"*.
	 *
	 * 🔴 KHAI, KHÔNG ĐOÁN. Bảng `ma_song_song` sinh ra đúng cho việc này và câu chú thích của nó
	 *    vẫn đúng nguyên: *"Hai mã của cùng một người, PHẢI KHAI chứ không đoán: tên người Việt
	 *    trùng rất nhiều, đoán sai là gộp lương hai người khác nhau"*. Ghép theo tên là ghép
	 *    Nguyễn Văn A này sang Nguyễn Văn A kia, và tiền đi theo.
	 *
	 * ⚠️ KHÔNG CẦN QUY ƯỚC CHIỀU `ma_a` / `ma_b`. Bảng ấy không nói bên nào là mã máy, và bắt
	 *    người khai nhớ đúng chiều là sớm muộn có dòng khai ngược — im lặng. Luật ở đây tự suy
	 *    ra: mã đến mà ĐÃ CÓ hồ sơ thì giữ nguyên; không có hồ sơ thì tìm đầu kia của cặp, và
	 *    chỉ nhận nếu đầu kia CÓ hồ sơ. Khai ngược cũng chạy đúng.
	 *
	 * ⚠️ KHÔNG TÌM THẤY THÌ TRẢ LẠI CHÍNH NÓ, đừng trả rỗng. Lượt ấy phải đi tiếp đúng đường cũ
	 *    — vào bảng với mã máy và được kể ra ở khối "chưa có hồ sơ" — chứ không biến mất. Mất
	 *    một lượt bấm là mất công của người thật.
	 */
	public static function ma_that( $ma_nv ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		/* ⚠️ Chốt mã rỗng là PHÒNG XA, không phải chốt canh hành vi: tra với mã rỗng cũng chẳng
		   khớp dòng nào nên kết quả y hệt — chỉ tốn thêm hai lượt hỏi cơ sở dữ liệu. Đã phá thử
		   và ghi lại để người sau khỏi đi tìm phép thử canh nó: KHÔNG CÓ. */
		if ( '' === $ma ) { return ''; }
		if ( self::ho_so( $ma ) ) { return $ma; }

		$cap = VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_a, ma_b FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE LOWER(ma_a)=LOWER(%s) OR LOWER(ma_b)=LOWER(%s)', $ma, $ma ) );
		foreach ( (array) $cap as $c ) {
			$kia = ( 0 === strcasecmp( (string) $c['ma_a'], $ma ) ) ? (string) $c['ma_b'] : (string) $c['ma_a'];
			$kia = trim( $kia );
			if ( '' !== $kia && 0 !== strcasecmp( $kia, $ma ) && self::ho_so( $kia ) ) { return $kia; }
		}
		return $ma;
	}

	/** Cơ sở đã biết: gộp từ bảng máy, bảng chấm công và hồ sơ — không tự tạo cơ sở nào. */
	/**
	 * MỌI BẢNG ĐANG TRỎ TỚI MÃ NHÂN VIÊN — dò từ CHÍNH sơ đồ bảng, không gõ tay danh sách.
	 *
	 * 🔴 Gõ tay là sớm muộn thiếu. Thêm một bảng mới có cột `ma_nv` mà quên khai ở đây thì đổi
	 *    mã sẽ bỏ sót đúng bảng đó — dữ liệu của người ta rơi ra ngoài, không có gì báo, và chỉ
	 *    lộ ra ở bảng lương cuối tháng. Đọc sơ đồ thì bảng mới tự có mặt.
	 *
	 * `ma_song_song` khai riêng vì nó gọi hai cột là `ma_a`/`ma_b` chứ không phải `ma_nv`.
	 */
	public static function bang_theo_ma() {
		$ra = array();
		foreach ( VHCC_DB::bang() as $ten => $than ) {
			if ( 'nhan_vien' === $ten ) { continue; }          // chính nó, xử riêng
			foreach ( array( 'ma_nv', 'ma_a', 'ma_b' ) as $cot ) {
				if ( preg_match( '/^\s*' . $cot . '\s+VARCHAR/mi', $than ) ) { $ra[ $ten ][] = $cot; }
			}
		}
		return $ra;
	}

	/**
	 * ĐỔI MÃ NHÂN VIÊN — và KÉO THEO mọi hàng đang trỏ tới mã cũ.
	 *
	 * Anh Thắng: *"Admin có quyền sửa luôn mã nhân viên lại cho chuẩn nhé"*. Được, nhưng phải
	 * làm cho tới: mã nhân viên là thứ NỐI hồ sơ với chấm công, lương, lịch làm, yêu cầu, sổ
	 * mặt trong máy. Đổi mỗi ở bảng hồ sơ là toàn bộ lịch sử của người đó rơi ra ngoài — bảng
	 * công trống trơn, mà không có gì báo.
	 *
	 * 🔴 CHẶN GHI ĐÈ LÊN MÃ ĐANG CÓ NGƯỜI. Đổi A -> B khi B đã tồn tại là trộn công của hai
	 *    người vào một, và KHÔNG có đường lùi: sau đó không phân biệt được hàng nào vốn của ai.
	 *    Đây đúng là cảnh báo màn Nhân sự trong wp-admin vẫn in ra, giờ thành một cái chặn thật.
	 *
	 * @return array ['ok', 'bang' => [tên bảng => số hàng đã kéo theo]]
	 */
	public static function doi_ma( $cu, $moi ) {
		global $wpdb;
		$cu  = trim( (string) $cu );
		$moi = trim( (string) $moi );

		if ( '' === $cu || '' === $moi ) {
			return array( 'ok' => false, 'error' => 'Thiếu mã cũ hoặc mã mới.' );
		}
		if ( $cu === $moi ) { return array( 'ok' => true, 'bang' => array(), 'khong_doi' => true ); }
		/* Mã đi vào URL, tên file, và cột khoá của mọi bảng. Giữ khuôn hẹp còn hơn xử lý ký tự
		   lạ ở mười chỗ khác nhau. */
		if ( ! preg_match( '/^[A-Za-z0-9_.\-]{1,40}$/', $moi ) ) {
			return array( 'ok' => false, 'error' => 'Mã mới chỉ được gồm chữ, số, gạch dưới, gạch nối '
				. 'và dấu chấm, tối đa 40 ký tự. Đã nhập: "' . $moi . '".' );
		}
		$bang_nv = VHCC_DB::t( 'nhan_vien' );
		$co_cu   = $wpdb->get_var( $wpdb->prepare( "SELECT ma_nv FROM $bang_nv WHERE ma_nv=%s", $cu ) );
		if ( ! $co_cu ) {
			return array( 'ok' => false, 'error' => 'Không thấy hồ sơ mang mã ' . $cu . '.' );
		}
		$co_moi = $wpdb->get_var( $wpdb->prepare( "SELECT ho_ten FROM $bang_nv WHERE ma_nv=%s", $moi ) );
		if ( null !== $co_moi ) {
			return array( 'ok' => false, 'error' => 'Mã ' . $moi . ' đã là của ' . $co_moi
				. '. Đổi sang mã đang có người là TRỘN công của hai người vào một, và không có '
				. 'đường lùi — sau đó không phân biệt được hàng nào vốn của ai. Chọn mã khác.' );
		}

		/* Kéo các bảng phụ TRƯỚC, hồ sơ SAU. Nửa chừng hỏng thì mấy hàng đã kéo trỏ vào một mã
		   chưa có hồ sơ — vẫn tìm lại được bằng chính mã đó. Làm ngược lại thì hồ sơ mang mã mới
		   còn lịch sử nằm ở mã cũ, và không còn gì nối hai đầu. */
		$dem = array();
		foreach ( self::bang_theo_ma() as $ten => $cot_ds ) {
			$t = VHCC_DB::t( $ten );
			foreach ( $cot_ds as $cot ) {
				$n = $wpdb->query( $wpdb->prepare(
					"UPDATE $t SET $cot=%s WHERE $cot=%s", $moi, $cu ) );
				if ( $n > 0 ) { $dem[ $ten ] = ( isset( $dem[ $ten ] ) ? $dem[ $ten ] : 0 ) + (int) $n; }
			}
		}
		$wpdb->update( $bang_nv, array( 'ma_nv' => $moi, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $cu ) );

		return array( 'ok' => true, 'bang' => $dem, 'cu' => $cu, 'moi' => $moi );
	}

	/**
	 * ĐẶT VAI TRÒ CHO MỘT NGƯỜI.
	 *
	 * =========================================================================================
	 * 🔴 ĐỔI VAI LÀ ĐỔI QUYỀN — VÀ ĐÂY LÀ CHỖ MỘT NGƯỜI TỰ NÂNG MÌNH LÊN ADMIN
	 * =========================================================================================
	 * Cho Kế toán sửa ô vai trò mà không chốt gì thì việc đầu tiên làm được là mở ô của CHÍNH
	 * MÌNH, chọn Admin, bấm Lưu. Xong là có PIN xem được PIN của mọi người, máy chấm công, và
	 * toàn bộ cài đặt hệ thống. Không lỗi nào phát ra, không dòng nhật ký nào.
	 *
	 * Nên ba chốt, và ba chốt này KHÁC NHAU chứ không phải một chốt viết ba lần:
	 *   1. KHÔNG ĐẶT VAI CAO HƠN BẬC CỦA CHÍNH MÌNH — chặn đường nâng người khác lên trên đầu
	 *      mình rồi nhờ họ nâng lại.
	 *   2. KHÔNG ĐỤNG VÀO NGƯỜI ĐANG Ở BẬC CAO HƠN — chặn đường Kế toán hạ Admin xuống Nhân
	 *      viên. Thiếu chốt này thì chốt 1 vô dụng: không nâng được mình lên thì hạ hết người
	 *      trên xuống, kết quả y hệt.
	 *   3. KHÔNG TỰ ĐỔI VAI CỦA CHÍNH MÌNH — kể cả hạ. Admin duy nhất tự hạ xuống Nhân viên là
	 *      cả hệ thống không còn ai vào được màn cài đặt, và không có đường nào gỡ ra ngoài
	 *      việc sửa thẳng CSDL.
	 *
	 * ⚠️ LƯU NGUYÊN CHUỖI NGƯỜI TA CHỌN, KHÔNG QUY VỀ TÊN CHUNG. Sổ đang có cả "Kế toán cá nhân"
	 *    lẫn "Kế toán NCC" — hệ chấm công gộp cả hai vào một bậc, nhưng app Vận hành chi phí
	 *    phân biệt chúng. Ghi đè thành "Kế toán" là bên chi phí mất phân biệt, mà bên này không
	 *    thấy gì khác cả.
	 *
	 * @param array  $u      Người đang khai.
	 * @param string $ma_nv  Mã NV của người bị đổi vai.
	 * @param string $vai    Tên vai, phải nằm trong `VHCC_Vai::ds_ten()`.
	 */
	public static function dat_vai_tro( $u, $ma_nv, $vai ) {
		global $wpdb;
		if ( ! self::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Đổi vai trò cần vai Kế toán trở lên.' );
		}
		$ma  = trim( (string) $ma_nv );
		$vai = trim( (string) $vai );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		$cu = self::ho_so( $ma );
		if ( ! $cu ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }

		/* =====================================================================================
		 * 🔴 "KHÔNG ĐỔI GÌ" PHẢI XÉT TRƯỚC MỌI CHỐT KHÁC, KỂ CẢ DANH SÁCH TRẮNG.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026, ảnh màn nhân sự sau một lần bấm Lưu bảng:
		 *     Đã lưu 2 ô quyền vào trang.
		 *     MNKT4CTY0001: Vai trò "Kế Toán MTD" không có trong hệ.
		 *     Hệ ghế: đã đẩy/gỡ 1 người.
		 * Anh không hề định đổi vai ai. Hồ sơ ấy ĐANG mang chuỗi "Kế Toán MTD" — một vai còn
		 * sót từ dữ liệu cũ, chưa khai vào hệ. Ô xổ cố ý giữ nguyên chuỗi ấy làm lựa chọn đang
		 * chọn (xem `VHCC_TrangNS::o_vai()`), vì thả nó ra là ô tự nhảy về dòng đầu và một cú
		 * bấm Lưu đổi vai cả trang. Nhưng lúc lưu, chuỗi ấy gửi lên và chốt danh sách trắng
		 * chối nó — nên MỖI LẦN bấm Lưu bảng lại đẻ ra một dòng đỏ cho một việc không xảy ra.
		 *
		 * ⚠️ DÒNG ĐỎ KÊU OAN LÀ THỨ DẠY NGƯỜI TA THÔI ĐỌC DÒNG ĐỎ. Trong 400 nhân sự còn nhiều
		 *    vai sót như vậy; để nguyên thì mỗi lần lưu là mấy chục dòng đỏ vô nghĩa, và ngày
		 *    có một dòng đỏ THẬT thì nó nằm lẫn trong đám ấy.
		 *
		 * Danh sách trắng vẫn giữ nguyên hiệu lực cho mọi lần ĐỔI THẬT: gửi lên một chuỗi lạ
		 * KHÁC vai đang có thì vẫn bị chối ở ngay dưới đây.
		 */
		if ( trim( (string) $cu['vai_tro'] ) === $vai ) { return array( 'ok' => true, 'doi' => false ); }

		/* 🔴 DANH SÁCH TRẮNG PHẢI LÀ `VHCC_Vai::ds_ten()`, KHÔNG PHẢI `VHCC_Auth::VAI_TRO_TAT_CA`.
		   Hằng số kia CỐ ĐỊNH, không bao giờ biết tới vai tự tạo — nghĩa là khai một vai mới ở
		   "Bảng vai trò" ("Kế Toán MTD"…) xong thì KHÔNG BAO GIỜ gán được cho ai, vì đúng chỗ
		   gán lại chối nó là "lạ". Anh Thắng 28/08/2026 tạo vai "Kế Toán MTD" xong bấm Lưu bảng
		   vẫn ăn đúng câu "không có trong hệ" — `ds_ten()` gộp cả `VAI_TRO_TAT_CA` lẫn vai tự
		   tạo nên vẫn chối được chuỗi thật sự lạ, chỉ khác là không còn chối nhầm vai vừa khai. */
		if ( ! in_array( $vai, VHCC_Vai::ds_ten(), true ) ) {
			return array( 'ok' => false, 'error' => 'Vai trò "' . $vai . '" không có trong hệ.' );
		}
		if ( ! self::co_quyen_coso( $u, $cu['cua_hang'] ) ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ này không thuộc cơ sở bạn phụ trách.' );
		}

		$bac_toi = VHCC_Vai::bac( $u );
		/* Chốt 3 trước hai chốt kia: câu chối của nó nói đúng chuyện đang xảy ra, còn hai chốt
		   kia sẽ nói nhầm thành "vai cao hơn bậc của bạn" khi người ta tự sửa chính mình. */
		$ma_toi = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' !== $ma_toi && $ma_toi === $ma ) {
			return array( 'ok' => false, 'error' => 'Không tự đổi vai trò của chính mình được — '
				. 'nhờ một Admin khác đổi giúp.' );
		}
		if ( VHCC_Vai::bac( array( 'role' => $vai ) ) > $bac_toi ) {
			return array( 'ok' => false, 'error' => 'Không đặt được vai cao hơn vai của chính mình ('
				. VHCC_Vai::ten( $u ) . ').' );
		}
		if ( VHCC_Vai::bac( array( 'role' => (string) $cu['vai_tro'] ) ) > $bac_toi ) {
			return array( 'ok' => false, 'error' => trim( (string) $cu['ho_ten'] ) . ' đang mang vai '
				. VHCC_Vai::ten( array( 'role' => (string) $cu['vai_tro'] ) )
				. ' — cao hơn vai của bạn, nên không đổi được.' );
		}

		/* ⛔ Chốt "không đổi thì thôi" từng nằm ở ĐÂY. Nay nó đứng trên đầu hàm, trước danh sách
		   trắng — để lại một bản thứ hai ở đây là mã chết: mọi lối tới dòng này đều đã qua bản
		   kia rồi. */
		$ok = $wpdb->update( VHCC_DB::t( 'nhan_vien' ),
			array( 'vai_tro' => $vai, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );
		return ( false === $ok )
			? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
			: array( 'ok' => true, 'doi' => true );
	}

	/**
	 * CHUYỂN MỘT NGƯỜI SANG CƠ SỞ KHÁC — và RESET quyền riêng của họ về mặc định.
	 *
	 * Anh Thắng 27/08/2026: *"Điều chỉnh bạn thuộc cơ sở nào nên bạn chuyển, khi chuyển quyền
	 * hạn sẽ reset lại mặc định"*.
	 *
	 * 🔴 CHUYỂN CƠ SỞ LÀ VIỆC NẶNG, KHÔNG PHẢI SỬA MỘT Ô. Cửa hàng nào trong hồ sơ quyết định
	 * công và lương của người ấy tính về đâu — nên chốt đúng bằng chốt của `luu_ho_so()`:
	 * Quản lý trở lên, và phải phụ trách CẢ HAI cơ sở. Chỉ phụ trách cơ sở đích thôi là đủ để
	 * "hút" người của cơ sở khác về mình.
	 *
	 * ⚠️ RESET NGOẠI LỆ QUYỀN, KHÔNG RESET VAI. Vai là thứ khai có chủ ý và không dính cơ sở;
	 *    ngoại lệ mở/khoá từng trang thì khai theo hoàn cảnh ở cơ sở cũ — xem `VHCC_Cong::xoa_nguoi()`.
	 * ⚠️ Gác `method_exists` cùng hàm với lời gọi (luật `tools/test/kiem-goi-cheo.php`).
	 */
	public static function dat_co_so( $u, $ma_nv, $coso ) {
		global $wpdb;
		/* 🔴 MỘT CHỐT, KHÔNG HAI. Bản đầu gác cả `co_sua_ho_so` (quyền `ho_so`, bậc 4) LẪN
		   `co_quan_tri_nv` (quyền `ngoai_coso`, bậc 3) — trông như phòng thủ hai tầng, nhưng
		   bậc 4 đã CAO HƠN bậc 3, nên không tồn tại vai nào qua được chốt trên mà trượt chốt
		   dưới. Chốt thứ hai là MÃ CHẾT: không bao giờ chạy, và câu lỗi "cần Quản lý" của nó
		   không bao giờ hiện ra cho ai. Phá thử phát hiện đúng chỗ này — bỏ nó đi mà bộ thử
		   vẫn xanh, vì nó vốn chưa từng chặn ai.
		   Giữ đúng chốt CHẶT HƠN (`ho_so`), và có phép thử canh khoảng cách hai bậc ấy: hạ
		   `ho_so` xuống ngang hay thấp hơn `ngoai_coso` là câu chuyện khác, phải biết ngay. */
		if ( ! self::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Chuyển cơ sở là chuyển cả công và lương giữa hai '
				. 'cửa hàng — cần vai Kế toán trở lên.' );
		}
		$ma  = trim( (string) $ma_nv );
		$moi = self::chuan_coso( $coso );
		if ( '' === $ma )  { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		if ( '' === $moi ) { return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở đích.' ); }
		$cu_hs = self::ho_so( $ma );
		if ( ! $cu_hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }

		$cu = self::chuan_coso( $cu_hs['cua_hang'] );
		if ( strtolower( $cu ) === strtolower( $moi ) ) {
			return array( 'ok' => true, 'doi' => false, 'go' => 0 );
		}
		/* Phải phụ trách CẢ HAI. Thiếu vế "cơ sở cũ" là mở đường hút người của cơ sở khác về
		   mình mà cơ sở kia không hay biết. */
		if ( '' !== $cu && ! self::co_quyen_coso( $u, $cu ) ) {
			return array( 'ok' => false, 'error' => 'Người này đang thuộc ' . $cu
				. ' — cơ sở bạn không phụ trách.' );
		}
		if ( ! self::co_quyen_coso( $u, $moi ) ) {
			return array( 'ok' => false, 'error' => 'Bạn không phụ trách cơ sở "' . $moi . '".' );
		}

		$ok = $wpdb->update( VHCC_DB::t( 'nhan_vien' ),
			array( 'cua_hang' => $moi, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );
		if ( false === $ok ) {
			return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error );
		}

		/* 🔴 RESET QUYỀN RIÊNG. Ngoại lệ khai theo hoàn cảnh ở cơ sở CŨ; sang cơ sở mới thì hoàn
		   cảnh ấy hết, nhưng cái ngoại lệ thì ở lại — âm thầm, và không ai ở cơ sở mới biết. */
		$go = 0;
		if ( class_exists( 'VHCC_Cong' ) && method_exists( 'VHCC_Cong', 'xoa_nguoi' ) ) {
			$go = (int) VHCC_Cong::xoa_nguoi( $ma );
		}
		return array( 'ok' => true, 'doi' => true, 'tu' => $cu, 'den' => $moi, 'go' => $go );
	}

	/**
	 * ĐẶT DANH SÁCH CƠ SỞ CHO MỘT NGƯỜI — tích cơ sở nào là làm việc VÀ quản ở đó.
	 *
	 * Anh Thắng 31/08/2026: *"thay vì cửa hàng trưởng làm ở 1 cơ sở đó, tích chọn quản lý các cơ
	 * sở khác, thì có thể quản lý các nhân viên ở các cơ sở khác"*, và chốt: **một ô chung**.
	 *
	 * 🔴 MỘT Ô, HAI NGHĨA, VÀ ĐÓ LÀ Ý ĐỊNH. Cơ sở tích ở đây vừa là nơi người ta LÀM (công của
	 *    họ nằm ở bảng cơ sở ấy) vừa là phạm vi họ QUẢN (nếu vai đủ bậc). Nhân viên tích 2 cơ sở
	 *    thì chỉ là làm ở 2 nơi — họ không có `cong_coso` nên chẳng quản được gì. Cửa hàng
	 *    trưởng tích 3 cơ sở thì quản người ở cả 3. Quyền đi theo VAI, phạm vi đi theo ô này.
	 *
	 * 🔴 PHẢI PHỤ TRÁCH CẢ CƠ SỞ CŨ LẪN CƠ SỞ MỚI. Thiếu vế "cũ" là mở đường hút người của cơ sở
	 *    khác về mình mà bên kia không hay biết; thiếu vế "mới" là đẩy người sang một cơ sở mình
	 *    không có trách nhiệm gì. Kiểm TỪNG cơ sở bị thêm và TỪNG cơ sở bị bỏ, không kiểm cả
	 *    danh sách một lượt — người ta thường chỉ sửa một ô trong năm.
	 *
	 * ⚠️ RESET QUYỀN RIÊNG khi cơ sở đổi, y như `dat_co_so()` cũ: ngoại lệ khai theo hoàn cảnh ở
	 *    cơ sở cũ mà theo người sang cơ sở mới thì không ai ở đó biết nó tồn tại.
	 *
	 * @param array $ds Danh sách cơ sở (đã tích). Rỗng = thôi làm ở đâu cả.
	 * @return array `ok` · `doi`(bool) · `tu` · `den` · `go`, hoặc `error`.
	 */
	public static function dat_ds_coso( $u, $ma_nv, $ds ) {
		global $wpdb;
		if ( ! self::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Đổi cơ sở là chuyển cả công và lương giữa các '
				. 'cửa hàng — cần vai Kế toán trở lên.' );
		}
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		$cu_hs = self::ho_so( $ma );
		if ( ! $cu_hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }

		$moi = array();
		$da  = array();
		foreach ( (array) $ds as $x ) {
			foreach ( explode( ',', (string) $x ) as $m ) {
				$m = self::chuan_coso( $m );
				if ( '' === $m ) { continue; }
				$k = self::chu_thuong( $m );
				if ( isset( $da[ $k ] ) ) { continue; }
				$da[ $k ] = 1;
				$moi[]    = $m;
			}
		}
		$cu = self::ds_coso_hs( $cu_hs );

		/* Không đổi gì thì thôi — so theo TẬP HỢP, không theo thứ tự: kéo lại thứ tự tích không
		   phải là chuyển cơ sở của ai. */
		$sx_cu = array_map( array( __CLASS__, 'chu_thuong' ), $cu );
		$sx_moi = array_map( array( __CLASS__, 'chu_thuong' ), $moi );
		sort( $sx_cu );
		sort( $sx_moi );
		if ( $sx_cu === $sx_moi ) { return array( 'ok' => true, 'doi' => false, 'go' => 0 ); }

		foreach ( array_diff( $sx_moi, $sx_cu ) as $them ) {
			if ( ! self::co_quyen_coso( $u, $them ) ) {
				return array( 'ok' => false,
					'error' => 'Bạn không phụ trách cơ sở "' . $them . '" nên không thêm vào được.' );
			}
		}
		foreach ( array_diff( $sx_cu, $sx_moi ) as $bo ) {
			if ( ! self::co_quyen_coso( $u, $bo ) ) {
				return array( 'ok' => false,
					'error' => 'Người này đang thuộc ' . $bo . ' — cơ sở bạn không phụ trách.' );
			}
		}

		/* Rải vào hai cột y như `VHCC_Web::luu_ho_so()`: cơ sở đầu vào `cua_hang`, còn lại vào
		   `coso_phu`. Một hình dạng lưu duy nhất cho cả hệ. */
		$dat = $moi;
		$ok  = $wpdb->update( VHCC_DB::t( 'nhan_vien' ), array(
			'cua_hang' => $dat ? array_shift( $dat ) : '',
			'coso_phu' => implode( ', ', $dat ),
			'cap_nhat' => current_time( 'mysql' ),
		), array( 'ma_nv' => $ma ) );
		if ( false === $ok ) {
			return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error );
		}

		$go = 0;
		if ( class_exists( 'VHCC_Cong' ) && method_exists( 'VHCC_Cong', 'xoa_nguoi' ) ) {
			$go = (int) VHCC_Cong::xoa_nguoi( $ma );
		}
		return array( 'ok' => true, 'doi' => true, 'tu' => implode( ', ', $cu ),
			'den' => implode( ', ', $moi ), 'go' => $go );
	}

	/**
	 * DANH MỤC CƠ SỞ — những cơ sở ĐÃ ĐƯỢC KHAI, không phải những tên từng xuất hiện đâu đó.
	 *
	 * Anh Thắng 02/09/2026, ảnh hồ sơ Phạm Tường Vi và lưới bảng công có hàng
	 * `(PART TIME )_POSH+JP` cạnh hàng `PART_TIME (POSHJP)`: *"bị sinh cơ sở ảo"*.
	 *
	 * 🔴 TRƯỚC BẢN NÀY DANH SÁCH CƠ SỞ ĐƯỢC SUY RA TỪ DỮ LIỆU CHẢY VÀO — `SELECT DISTINCT` trên
	 *    `cham_cong.coso` và `nhan_vien.cua_hang`. Nghĩa là BẤT KỲ tên nào từng lọt vào một lượt
	 *    chấm công hay một ô gõ tay đều nghiễm nhiên thành một cơ sở của cả hệ, vĩnh viễn, và
	 *    KHÔNG AI PHẢI QUYẾT ĐỊNH GÌ. Nạp .csv thì cơ sở suy ra từ TÊN TỆP
	 *    (`VHCC_NapCong::coso_tu_ten_tep`), nên hai lần nạp hai tên tệp khác nhau của cùng một
	 *    chỗ đẻ ra hai cơ sở; ô "Cơ sở phụ" trong hồ sơ là ô gõ tay, gõ lệch một dấu cách cũng
	 *    đẻ thêm một cơ sở nữa.
	 *
	 *    Hậu quả không dừng ở ô chọn dài thêm mấy dòng: công thật của một người bị XÉ ra nằm rải
	 *    trên hai ba hàng mang ba cái tên, mỗi hàng thiếu giờ, và bảng lương cộng theo hàng.
	 *
	 * 🔴 NAY CƠ SỞ CHỈ CÓ THẬT KHI ĐƯỢC KHAI CÓ Ý — đúng một trong hai:
	 *      · đã khai bộ phận ở màn Cấu hình (`bo_phan_coso`), hoặc
	 *      · đã có máy chấm công gán vào (`may.cua_hang`).
	 *    Cả hai đều là việc một người phải chủ động làm, không phải hệ quả của một lần gõ.
	 *
	 * ⚠️ SIẾT DANH MỤC KHÔNG ĐƯỢC LÀM MẤT CÔNG. Tên nằm ngoài danh mục mà vẫn đang mang lượt
	 *    chấm công thì KHÔNG biến mất im lặng — `ds_coso_la()` gom chúng lại và màn Cấu hình
	 *    hiện thành khối đỏ mở sẵn, kèm số lượt, để người ta gộp về tên đúng hoặc nhận vào danh
	 *    mục. Bỏ khối ấy đi là đổi một lỗi ồn ào (ô chọn thừa dòng) lấy một lỗi câm (công biến
	 *    mất khỏi mọi màn), tức là làm cho tệ hơn.
	 */
	public static function ds_coso() {
		global $wpdb;
		$ds = array();
		foreach ( array( 'bo_phan_coso' => 'coso', 'may' => 'cua_hang' ) as $bang => $cot ) {
			foreach ( (array) $wpdb->get_col( "SELECT DISTINCT $cot FROM " . VHCC_DB::t( $bang ) ) as $x ) {
				$x = self::chuan_coso( $x );
				if ( '' !== $x && ! in_array( $x, $ds, true ) ) { $ds[] = $x; }
			}
		}
		sort( $ds );
		return $ds;
	}

	/**
	 * CƠ SỞ LẠ — tên đang MANG DỮ LIỆU THẬT mà chưa có trong danh mục.
	 *
	 * Đây là mặt kia của `ds_coso()`: siết danh mục thì phải có chỗ nhìn thấy thứ vừa bị siết
	 * ra ngoài, kèm đủ số liệu để quyết — nó là một cơ sở thật chưa kịp khai, hay chỉ là một
	 * tên gõ lệch của cơ sở đã có?
	 *
	 * ⚠️ TÊN TRẢ VỀ LÀ TÊN THÔ, y như trong kho. Chính mấy cái tên gõ lệch nhau mới là thứ cần
	 *    gộp; chuẩn hoá ở đây thì hai kiểu gõ nhập làm một trên màn hình và người ta không chọn
	 *    tách ra được nữa. (Cùng lý do với `VHCC_Nhan::ds_coso_tho()`.)
	 *
	 * @return array array( array( 'coso' => tên thô, 'luot' => số lượt chấm công, 'nguoi' => số hồ sơ ) )
	 *               — nhiều lượt nhất trước.
	 */
	public static function ds_coso_la() {
		global $wpdb;
		$biet = array();
		foreach ( self::ds_coso() as $x ) { $biet[ self::chu_thuong( $x ) ] = 1; }

		$gom = array();
		$them = function ( $ten, $o_dau, $so ) use ( &$gom, $biet ) {
			$ten = trim( (string) $ten );
			if ( '' === $ten ) { return; }
			$chuan = VHCC_NhanSu::chuan_coso( $ten );
			if ( '' === $chuan ) { return; }
			/* So bằng dạng CHUẨN: `CS_POSH_HCM` và `POSH_HCM` là một chỗ, đừng kể thành lạ. */
			if ( isset( $biet[ VHCC_NhanSu::chu_thuong( $chuan ) ] ) ) { return; }
			$k = VHCC_NhanSu::chu_thuong( $ten );
			if ( ! isset( $gom[ $k ] ) ) { $gom[ $k ] = array( 'coso' => $ten, 'luot' => 0, 'nguoi' => 0 ); }
			$gom[ $k ][ $o_dau ] += (int) $so;
		};

		foreach ( (array) $wpdb->get_results(
			'SELECT coso, COUNT(*) so FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE coso<>'' GROUP BY coso", ARRAY_A ) as $r ) {
			$them( $r['coso'], 'luot', $r['so'] );
		}

		/* Hồ sơ: đi qua `ds_coso_hs()` để `coso_phu` (chuỗi nối bằng dấu phẩy) được tách đúng
		   như mọi nơi khác trong hệ — tự `explode` ở đây là nơi thứ hai tách một thứ. */
		foreach ( (array) $wpdb->get_results(
			'SELECT cua_hang, coso_phu FROM ' . VHCC_DB::t( 'nhan_vien' ), ARRAY_A ) as $r ) {
			foreach ( self::ds_coso_hs( $r ) as $x ) { $them( $x, 'nguoi', 1 ); }
		}

		$ra = array_values( $gom );
		usort( $ra, function ( $a, $b ) {
			if ( $a['luot'] !== $b['luot'] ) { return $b['luot'] - $a['luot']; }
			return strcmp( $a['coso'], $b['coso'] );
		} );
		return $ra;
	}

	/* ====================================================================== tên cơ sở */

	/**
	 * TÊN ĐẦY ĐỦ CỦA MỘT MÃ CƠ SỞ.
	 *
	 * =============================================================================================
	 * Mã cơ sở trong sổ là thứ máy đọc: `FARM_PT`, `FF_SC`, `PINPALL_HCM`, `VP_KH-HCM`. Chúng ngắn
	 * vì phải nằm vừa một cột và phải gõ được vào máy chấm công — nhưng người đọc bảng thì phải tự
	 * dịch trong đầu, và người mới thì không dịch nổi. Trên một ô chọn 21 dòng, đoán sai một dòng
	 * là xếp lịch cho cửa hàng khác.
	 *
	 * 🔴 KHÔNG ĐỔI MÃ ĐỂ CHO DỄ ĐỌC. Mã cơ sở là KHOÁ: bảng chấm công, bảng lịch, bảng máy, bảng
	 *    lương đều trỏ vào nó, và máy chấm công ngoài cửa hàng cũng khai bằng chính mã ấy. Đổi mã
	 *    là cắt đứt mọi dòng cũ. Nên bảng này chỉ thêm một lớp TÊN để HIỆN RA, mã giữ nguyên.
	 *
	 * ⚠️ Chưa khai thì trả lại chính MÃ, không trả rỗng. Trả rỗng là ô chọn có mấy dòng trắng
	 *    trơn — người ta không chọn được mà cũng không biết vì sao.
	 */
	const TEN_CS_O = 'COSO_TEN';

	private static $nho_ten_cs = null;

	/** Bảng [ MÃ => tên đầy đủ ], đã lọc sạch. */
	public static function ten_coso_bang() {
		if ( null === self::$nho_ten_cs ) {
			$ra = array();
			foreach ( (array) VHCC_Luong::cai_dat( self::TEN_CS_O, array() ) as $ma => $ten ) {
				$ma  = self::chuan_coso( $ma );
				$ten = trim( (string) ( is_array( $ten ) ? '' : $ten ) );
				if ( '' === $ma || '' === $ten ) { continue; }
				$ra[ $ma ] = $ten;
			}
			self::$nho_ten_cs = $ra;
		}
		return self::$nho_ten_cs;
	}

	public static function quen_ten_coso() { self::$nho_ten_cs = null; }

	/**
	 * Nhãn hiện ra màn hình: `Mã — Tên` nếu có khai, còn không thì chỉ `Mã`.
	 *
	 * 🔴 GIỮ CẢ MÃ, đừng chỉ hiện tên. Người đối chiếu với tệp .csv của máy, với bảng lương cũ,
	 *    với cái nhãn dán trên máy chấm công — họ tra bằng MÃ. Thay hẳn bằng tên là bắt họ dịch
	 *    ngược, và bảng nào cũng phải mở thêm một bảng nữa mới đọc được.
	 */
	public static function ten_coso( $ma ) {
		$m = self::chuan_coso( $ma );
		if ( '' === $m ) { return ''; }
		$b = self::ten_coso_bang();
		return isset( $b[ $m ] ) ? $m . ' — ' . $b[ $m ] : $m;
	}

	/**
	 * Khai / gỡ tên. `$ds` = [ MÃ => tên ]; tên rỗng thì gỡ dòng ấy.
	 *
	 * ⚠️ Bậc Quản lý (`ngoai_coso`): tên cơ sở hiện trên bảng của MỌI cửa hàng, không phải việc
	 *    riêng của một cửa hàng nào.
	 */
	public static function dat_ten_coso( $u, $ds ) {
		if ( ! VHCC_Vai::duoc( $u, 'ngoai_coso' ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, 'ngoai_coso', 'Đặt tên cơ sở' ) );
		}
		$cu   = self::ten_coso_bang();
		$sach = array();
		$doi  = 0;
		foreach ( (array) $ds as $ma => $ten ) {
			$ma  = self::chuan_coso( $ma );
			$ten = trim( (string) ( is_array( $ten ) ? '' : $ten ) );
			if ( '' === $ma ) { continue; }
			if ( mb_strlen( $ten, 'UTF-8' ) > 60 ) { $ten = mb_substr( $ten, 0, 60, 'UTF-8' ); }
			if ( '' !== $ten ) { $sach[ $ma ] = $ten; }
			$truoc = isset( $cu[ $ma ] ) ? $cu[ $ma ] : '';
			if ( $truoc !== $ten ) { $doi++; }
		}
		global $wpdb;
		$bang = VHCC_DB::t( 'cai_dat' );
		$ghi  = array( 'khoa' => self::TEN_CS_O, 'gia_tri' => wp_json_encode( $sach ),
			'cap_nhat' => current_time( 'mysql' ),
			'nguoi_sua' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE khoa=%s", self::TEN_CS_O ) );
		if ( $id ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $id ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		self::quen_ten_coso();
		return array( 'ok' => true, 'so' => count( $sach ), 'doi' => $doi );
	}

	/* ================================================================= quản lý cơ sở */

	/**
	 * BẢNG THỐNG KÊ TỪNG CƠ SỞ TRONG DANH MỤC — mã, tên, bộ phận, và NÓ ĐANG GIỮ GÌ.
	 *
	 * Anh Thắng 02/09/2026: *"thiếu tab quản lý cơ sở (thêm, xoá, sửa cơ sở)"*.
	 *
	 * 🔴 CỘT "ĐANG GIỮ GÌ" LÀ PHẦN QUAN TRỌNG NHẤT, không phải phần trang trí. Xoá một cơ sở
	 *    còn 400 lượt chấm công là xoá công thật của người ta — mà nhìn một dòng chỉ có mã và
	 *    tên thì không cách nào biết. Bày sẵn số máy · số hồ sơ · số lượt ngay cạnh nút Xoá thì
	 *    người bấm biết mình đang bấm cái gì.
	 *
	 * @return array array( array( 'ma','ten','bo_phan','cach_tinh','so_may','so_hs','so_luot' ) )
	 */
	public static function thong_ke_coso() {
		global $wpdb;
		$ds = self::ds_coso();
		if ( ! $ds ) { return array(); }
		$ten = self::ten_coso_bang();

		/* Đếm gộp MỘT LẦN cho cả ba bảng rồi tra bằng khoá chữ thường — hỏi từng cơ sở là 21 cơ
		   sở × 3 truy vấn cho một màn chỉ để đọc. */
		$dem_luot = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT coso, COUNT(*) so FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE coso<>'' GROUP BY coso",
			ARRAY_A ) as $r ) {
			$k = self::chu_thuong( self::chuan_coso( $r['coso'] ) );
			if ( '' === $k ) { continue; }
			$dem_luot[ $k ] = ( isset( $dem_luot[ $k ] ) ? $dem_luot[ $k ] : 0 ) + (int) $r['so'];
		}
		$dem_may = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT cua_hang, COUNT(*) so FROM ' . VHCC_DB::t( 'may' ) . " WHERE cua_hang<>'' GROUP BY cua_hang",
			ARRAY_A ) as $r ) {
			$k = self::chu_thuong( self::chuan_coso( $r['cua_hang'] ) );
			if ( '' === $k ) { continue; }
			$dem_may[ $k ] = ( isset( $dem_may[ $k ] ) ? $dem_may[ $k ] : 0 ) + (int) $r['so'];
		}
		/* Hồ sơ: đếm qua `ds_coso_hs()` vì một người có thể thuộc NHIỀU cơ sở (cột `coso_phu` là
		   chuỗi nối bằng dấu phẩy). Đếm mỗi `cua_hang` là bỏ sót đúng những người chạy nhiều nơi. */
		$dem_hs = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT cua_hang, coso_phu FROM ' . VHCC_DB::t( 'nhan_vien' ), ARRAY_A ) as $r ) {
			foreach ( self::ds_coso_hs( $r ) as $x ) {
				$k = self::chu_thuong( $x );
				$dem_hs[ $k ] = ( isset( $dem_hs[ $k ] ) ? $dem_hs[ $k ] : 0 ) + 1;
			}
		}

		$ra = array();
		foreach ( $ds as $ma ) {
			$k = self::chu_thuong( $ma );
			$ra[] = array(
				'ma'        => $ma,
				'ten'       => isset( $ten[ $ma ] ) ? $ten[ $ma ] : '',
				'bo_phan'   => VHCC_Luong::bo_phan_cua( $ma ),
				'cach_tinh' => VHCC_Luong::cach_tinh( $ma ),
				'so_may'    => isset( $dem_may[ $k ] ) ? $dem_may[ $k ] : 0,
				'so_hs'     => isset( $dem_hs[ $k ] ) ? $dem_hs[ $k ] : 0,
				'so_luot'   => isset( $dem_luot[ $k ] ) ? $dem_luot[ $k ] : 0,
			);
		}
		return $ra;
	}

	/**
	 * THÊM MỘT CƠ SỞ VÀO DANH MỤC.
	 *
	 * ⚠️ Đây là ĐƯỜNG DUY NHẤT sinh ra cơ sở mới bằng tay, và nó cố ý bắt người ta gõ mã rồi
	 *    bấm nút — chứ không phải hệ quả của một lần nạp tệp hay một ô gõ nhầm (xem khối 🔴 ở
	 *    `ds_coso()`).
	 */
	public static function them_coso( $u, $ma, $ten = '', $bo_phan = '' ) {
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Thêm cơ sở — ' . self::LOI_QT );
		}
		$ma = self::chuan_coso( $ma );
		$loi = VHCC_NapCong::ma_coso_hop_le( $ma );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		foreach ( self::ds_coso() as $x ) {
			if ( 0 === strcasecmp( (string) $x, $ma ) ) {
				return array( 'ok' => false, 'error' => 'Cơ sở "' . $x . '" đã có trong danh mục rồi.' );
			}
		}
		$r = self::xep_bo_phan( $u, $ma, $bo_phan );
		if ( empty( $r['ok'] ) ) { return $r; }
		$ten = trim( (string) $ten );
		if ( '' !== $ten ) {
			$bang = self::ten_coso_bang();
			$bang[ $ma ] = $ten;
			self::dat_ten_coso( $u, $bang );
		}
		return array( 'ok' => true, 'ma' => $ma );
	}

	/**
	 * GỠ MỘT TÊN CƠ SỞ KHỎI MỌI HỒ SƠ. Trả về số hồ sơ đã sửa.
	 *
	 * ⚠️ Đi qua `ds_coso_hs()` rồi rải lại vào hai cột `cua_hang`/`coso_phu` — y hệt cách
	 *    `VHCC_Web::luu_ho_so()` và `doi_coso()` ghi. Cắt chuỗi bằng tay ở đây là nơi thứ ba
	 *    hiểu một hình dạng dữ liệu, và nơi thứ ba luôn là nơi hiểu sai.
	 */
	public static function go_coso_ho_so( $ten ) {
		global $wpdb;
		$bo = self::chu_thuong( self::chuan_coso( $ten ) );
		if ( '' === $bo ) { return 0; }
		$so = 0;
		foreach ( (array) $wpdb->get_results(
			'SELECT ma_nv, cua_hang, coso_phu FROM ' . VHCC_DB::t( 'nhan_vien' ), ARRAY_A ) as $r ) {
			$cu  = self::ds_coso_hs( $r );
			$giu = array();
			foreach ( $cu as $x ) {
				if ( self::chu_thuong( $x ) !== $bo ) { $giu[] = $x; }
			}
			if ( count( $giu ) === count( $cu ) ) { continue; }
			$dat = $giu;
			$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array(
				'cua_hang' => $dat ? array_shift( $dat ) : '',
				'coso_phu' => implode( ', ', $dat ),
				'cap_nhat' => current_time( 'mysql' ),
			), array( 'ma_nv' => $r['ma_nv'] ) );
			$so++;
		}
		return $so;
	}

	/**
	 * XOÁ MỘT CƠ SỞ — khỏi danh mục, khỏi tên hiện ra, khỏi mọi hồ sơ.
	 *
	 * 🔴 CÔNG ĐÃ CHẤM KHÔNG BAO GIỜ BỊ XOÁ THEO MỘT CÁCH ÂM THẦM. Cơ sở còn lượt chấm công thì
	 *    hàm này CHỐI, và nói ra con số — muốn dọn thì gộp về cơ sở đúng (giữ nguyên công) hoặc
	 *    bấm lại với `$ca_luot` kèm ĐÚNG số lượt đang thấy trên màn.
	 *
	 * ⚠️ `$mong_luot` là chốt "đúng con số anh vừa nhìn". Người mở màn lúc 9h, đi họp, 11h quay
	 *    lại bấm Xoá — trong khoảng ấy máy chấm công có thể đã đẩy thêm cả trăm lượt. Không so
	 *    lại là xoá mất phần vừa vào mà không ai biết.
	 *
	 * ⚠️ CÒN MÁY GÁN VÀO THÌ CHỐI HẲN, không có đường vòng: máy vẫn cắm ngoài cửa hàng và vẫn
	 *    đẩy công về, nên xoá cơ sở chỉ làm công mới rơi thành "cơ sở lạ" ngay hôm sau. Gỡ gán
	 *    máy ở màn Máy & Firmware trước.
	 */
	public static function xoa_coso( $u, $ma, $ca_luot = false, $mong_luot = null ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Xoá cơ sở — ' . self::LOI_QT );
		}
		$ma = self::chuan_coso( $ma );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu mã cơ sở.' ); }

		$so_may = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'may' ) . ' WHERE LOWER(cua_hang)=LOWER(%s)', $ma ) );
		if ( $so_may > 0 ) {
			return array( 'ok' => false, 'error' => 'Cơ sở "' . $ma . '" còn ' . $so_may
				. ' máy chấm công đang gán vào. Gỡ gán máy ở màn Máy & Firmware trước — máy còn cắm '
				. 'ngoài cửa hàng thì công mới lại chảy về đây ngay hôm sau.' );
		}

		$so_luot = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE LOWER(coso)=LOWER(%s)', $ma ) );
		if ( $so_luot > 0 && ! $ca_luot ) {
			return array( 'ok' => false, 'error' => 'Cơ sở "' . $ma . '" còn ' . $so_luot
				. ' lượt chấm công. Gộp nó về cơ sở đúng để giữ nguyên công, hoặc chọn "xoá cả '
				. $so_luot . ' lượt" nếu chắc chắn phần công này là rác.' );
		}
		if ( $so_luot > 0 && null !== $mong_luot && (int) $mong_luot !== $so_luot ) {
			return array( 'ok' => false, 'error' => 'Số lượt vừa đổi (' . $so_luot . ' chứ không phải '
				. (int) $mong_luot . ') — có ai đó vừa chấm công vào cơ sở này. Xem lại rồi bấm lại.' );
		}

		$xoa_luot = 0;
		if ( $so_luot > 0 ) {
			$xoa_luot = (int) $wpdb->query( $wpdb->prepare(
				'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE LOWER(coso)=LOWER(%s)', $ma ) );
		}
		$so_hs = self::go_coso_ho_so( $ma );
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'bo_phan_coso' ) . ' WHERE LOWER(coso)=LOWER(%s)', $ma ) );
		$bang = self::ten_coso_bang();
		if ( isset( $bang[ $ma ] ) ) { unset( $bang[ $ma ] ); self::dat_ten_coso( $u, $bang ); }

		return array( 'ok' => true, 'ma' => $ma, 'luot' => $xoa_luot, 'ho_so' => $so_hs );
	}

	/**
	 * GỘP TRỌN VẸN MỘT CƠ SỞ VÀO CƠ SỞ KHÁC — công, hồ sơ, MÁY, và cả chỗ đứng trong danh mục.
	 *
	 * Anh Thắng 02/09/2026, sau khi thử dọn `(PART TIME )_POSH+JP` và `PART_TIME (POSHJP)`:
	 * *"Có gì đó xung đột không xoá hay gộp được"*.
	 *
	 * 🔴 GỐC: `VHCC_Nhan::gop_coso()` dời LƯỢT và dọn HỒ SƠ — nhưng KHÔNG chạm ba thứ còn lại:
	 *    dòng trong `bo_phan_coso`, máy đang gán, và tên đầy đủ. Mà từ 3.31.0 chính `bo_phan_coso`
	 *    là thứ quyết định một cơ sở CÓ THẬT hay không. Nên gộp xong:
	 *      · tên cũ VẪN nằm trong danh mục -> vẫn hiện ở mọi ô chọn, lưới cả tháng vẫn vẽ một
	 *        hàng 0h cho nó (cơ sở đã khai thì luôn hiện dù rỗng);
	 *      · vì còn trong danh mục nên nó KHÔNG rơi vào khối "Cơ sở lạ" -> không còn chỗ nào gộp
	 *        hay xoá được nữa. Đúng cảm giác "xung đột": làm gì cũng không mất đi.
	 *      · máy vẫn gán vào tên cũ -> hôm sau công lại chảy về đấy, vòng lặp khép kín.
	 *
	 * ⚠️ MÁY THÌ DỜI, KHÔNG CHỐI. `xoa_coso()` chối khi còn máy, và đúng — xoá là bỏ hẳn một chỗ.
	 *    Nhưng GỘP nghĩa là "hai cái tên này là MỘT chỗ", nên cái máy ấy vẫn đang ở đúng chỗ đó,
	 *    chỉ là khai dưới tên khác. Chối ở đây là bắt người ta đi vòng qua màn Máy rồi quay lại,
	 *    trong khi câu trả lời đã rõ. Dời, và KỂ RA số máy đã dời.
	 *
	 * ⚠️ CÒN LƯỢT KHÔNG DỜI ĐƯỢC THÌ GIỮ TÊN CŨ LẠI TRONG DANH MỤC. `gop_coso()` cố ý không đụng
	 *    hàng đích đã chỉnh tay (nguồn `sua`/`bu`, có thể đã chốt lương). Xoá tên cũ khỏi danh mục
	 *    lúc ấy là đẩy mấy lượt còn lại thành "cơ sở lạ" — nghĩa là vừa dọn xong đã đẻ ra rác mới.
	 */
	public static function gop_coso_day_du( $u, $tu, $den ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Gộp cơ sở — ' . self::LOI_QT );
		}
		$tu  = trim( (string) $tu );
		$den = trim( (string) $den );
		$r   = VHCC_Nhan::gop_coso( $tu, $den, true );
		if ( isset( $r['loi'] ) ) { return array( 'ok' => false, 'error' => $r['loi'] ); }

		/* Dời máy sang tên đích — dùng đúng kiểu gõ mà `gop_coso()` đã chốt (`$r['den']`), không
		   dùng chuỗi người ta gõ vào: hai thứ ấy có thể khác hoa thường. */
		$may = (int) $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'may' ) . ' SET cua_hang=%s WHERE LOWER(cua_hang)=LOWER(%s)',
			$r['den'], $tu ) );

		/* Còn lượt nào mang tên cũ (hàng đã chỉnh tay) thì GIỮ tên cũ trong danh mục. */
		$con = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE LOWER(coso)=LOWER(%s)', $tu ) );
		$go_danh_muc = false;
		if ( 0 === $con ) {
			$wpdb->query( $wpdb->prepare(
				'DELETE FROM ' . VHCC_DB::t( 'bo_phan_coso' ) . ' WHERE LOWER(coso)=LOWER(%s)', $tu ) );
			$bang = self::ten_coso_bang();
			$k_tu = self::chuan_coso( $tu );
			if ( isset( $bang[ $k_tu ] ) ) { unset( $bang[ $k_tu ] ); self::dat_ten_coso( $u, $bang ); }
			self::quen_ten_coso();
			$go_danh_muc = true;
		}

		return array( 'ok' => true, 'tu' => $tu, 'den' => $r['den'],
			'luot' => (int) $r['doi_ten'] + (int) $r['gop'], 'ho_so' => (int) $r['ho_so'],
			'may' => $may, 'con_lai' => $con, 'go_danh_muc' => $go_danh_muc,
			'de_lai' => (array) $r['de_lai'] );
	}

	/**
	 * TRA MỘT TÊN CƠ SỞ — NÓ ĐANG NẰM Ở ĐÂU, VÀ CÓ MẤY BIẾN THỂ.
	 *
	 * Anh Thắng 02/09/2026, sau hai lượt dọn mà hàng vẫn còn: *"vẫn cứ hiện cơ sở giữa lỗi, cần
	 * xoá luôn"*.
	 *
	 * 🔴 MỘT CÁI TÊN NẰM Ở NĂM CHỖ, VÀ DỌN BỐN CHỖ THÌ NÓ VẪN HIỆN. Cơ sở không phải một bản ghi
	 *    — nó chỉ là chữ, rải ở: `cham_cong.coso` · `nhan_vien.cua_hang` · `nhan_vien.coso_phu` ·
	 *    `may.cua_hang` · `bo_phan_coso.coso` (+ bảng tên đầy đủ). Mỗi chỗ có một lối dọn riêng,
	 *    nên "đã bấm gộp rồi mà vẫn thấy" là chuyện bình thường — và không màn nào nói cho biết
	 *    còn sót ở đâu. Hàm này trả lời đúng câu ấy.
	 *
	 * ⚠️ TRẢ CẢ DANH SÁCH BIẾN THỂ. Hai tên chỉ khác một dấu cách (`(PART TIME )_POSH+JP` và
	 *    `(PART TIME)_POSH+JP`) là HAI hàng khác nhau trong kho, mà nhìn trên màn thì gần như
	 *    không phân biệt được. Gộp một cái rồi tưởng xong, cái kia vẫn nguyên.
	 */
	public static function tra_coso( $ten ) {
		global $wpdb;
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { return array(); }
		$chuan = self::chuan_coso( $ten );
		$goc   = self::rut_gon_ten( $ten );

		/* Biến thể: mọi tên trong bảng chấm công mà bỏ dấu cách + gạch + chữ thường thì giống
		   nhau. Đây là phép so LỎNG có chủ ý — chỗ này để TÌM, không phải để xoá. */
		$bien = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT coso, COUNT(*) so FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE coso<>'' GROUP BY coso",
			ARRAY_A ) as $r ) {
			if ( self::rut_gon_ten( $r['coso'] ) !== $goc ) { continue; }
			$bien[] = array( 'ten' => (string) $r['coso'], 'luot' => (int) $r['so'] );
		}
		/* Tên có trong hồ sơ mà không có lượt nào cũng là một biến thể — nó vẫn đẻ ra hàng trên
		   lưới, và đó chính là cái hàng anh Thắng thấy. */
		$ho_so = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT ma_nv, ho_ten, cua_hang, coso_phu FROM ' . VHCC_DB::t( 'nhan_vien' ), ARRAY_A ) as $r ) {
			foreach ( self::ds_coso_hs( $r ) as $x ) {
				if ( self::rut_gon_ten( $x ) !== $goc ) { continue; }
				$ho_so[] = array( 'ma_nv' => (string) $r['ma_nv'], 'ho_ten' => (string) $r['ho_ten'],
					'ten' => $x );
				$co = false;
				foreach ( $bien as $b ) { if ( $b['ten'] === $x ) { $co = true; break; } }
				if ( ! $co ) { $bien[] = array( 'ten' => $x, 'luot' => 0 ); }
			}
		}
		$may = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT serial, mac, cua_hang FROM ' . VHCC_DB::t( 'may' ) . " WHERE cua_hang<>''", ARRAY_A ) as $r ) {
			if ( self::rut_gon_ten( $r['cua_hang'] ) !== $goc ) { continue; }
			$may[] = array( 'serial' => (string) $r['serial'], 'mac' => (string) $r['mac'],
				'ten' => (string) $r['cua_hang'] );
		}
		$bp = array();
		foreach ( (array) $wpdb->get_col( 'SELECT coso FROM ' . VHCC_DB::t( 'bo_phan_coso' ) ) as $x ) {
			if ( self::rut_gon_ten( $x ) === $goc ) { $bp[] = (string) $x; }
		}
		$ten_bang = array();
		foreach ( self::ten_coso_bang() as $ma => $t ) {
			if ( self::rut_gon_ten( $ma ) === $goc ) { $ten_bang[] = $ma; }
		}
		usort( $bien, function ( $a, $b ) { return $b['luot'] - $a['luot']; } );

		return array( 'ten' => $ten, 'chuan' => $chuan, 'bien_the' => $bien,
			'ho_so' => $ho_so, 'may' => $may, 'bo_phan' => $bp, 'ten_day_du' => $ten_bang,
			'trong_danh_muc' => in_array( $chuan, self::ds_coso(), true ) );
	}

	/**
	 * Rút một tên cơ sở về dạng "gần nhau thì bằng nhau": bỏ tiền tố `CS_`, bỏ mọi thứ không
	 * phải chữ/số, hạ chữ thường. `(PART TIME )_POSH+JP` và `PART_TIME (POSHJP)` ra cùng một
	 * chuỗi `parttimeposhjp`.
	 *
	 * ⚠️ CHỈ DÙNG ĐỂ TÌM, KHÔNG BAO GIỜ ĐỂ XOÁ hay để so "cơ sở này là cơ sở kia". Nó cố ý bắt
	 *    LỎNG, nên hai cửa hàng khác nhau mà tên gần giống sẽ rơi vào cùng một rổ — người nhìn
	 *    mới là người quyết, máy chỉ bày ra.
	 */
	public static function rut_gon_ten( $s ) {
		$s = self::chuan_coso( $s );
		$s = preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $s );
		return self::chu_thuong( (string) $s );
	}

	/**
	 * XOÁ SẠCH MỌI DẤU VẾT CỦA MỘT TÊN CƠ SỞ — cả năm chỗ, trong một lượt.
	 *
	 * Anh Thắng 02/09/2026: *"vẫn cứ hiện cơ sở giữa lỗi, cần xoá luôn"*.
	 *
	 * 🔴 KHÁC `xoa_coso()` VÀ `xoa_coso_la()` Ở CHỖ NÓ KHÔNG CHỪA GÌ: gỡ luôn cả máy đang gán
	 *    (đặt về "chưa gán", KHÔNG xoá máy) và dòng bộ phận, kể cả khi tên ấy đang nằm trong
	 *    danh mục. Đây là cái nút cuối cùng cho tên đã bị dọn nửa vời mấy lượt mà vẫn hiện.
	 *
	 * ⚠️ KHỚP THEO TÊN THÔ, CHÍNH XÁC TỪNG KÝ TỰ. `rut_gon_ten()` chỉ dùng để TÌM và bày ra;
	 *    xoá thì phải đúng cái tên người ta nhìn thấy, không thì một dấu cách lệch là xoá nhầm
	 *    cửa hàng bên cạnh.
	 *
	 * ⚠️ `$mong_luot` phải khớp đúng số lượt đang có. Mất công thì mất thật, không lấy lại được.
	 */
	public static function xoa_sach_coso( $u, $ten, $mong_luot ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Xoá sạch cơ sở — ' . self::LOI_QT );
		}
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }

		$so = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $ten ) );
		if ( (int) $mong_luot !== $so ) {
			return array( 'ok' => false, 'error' => 'Số lượt của "' . $ten . '" vừa đổi (' . $so
				. ' chứ không phải ' . (int) $mong_luot . '). Tải lại màn rồi bấm lại.' );
		}
		$luot = 0;
		if ( $so > 0 ) {
			$luot = (int) $wpdb->query( $wpdb->prepare(
				'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $ten ) );
		}
		$hs  = self::go_coso_ho_so( $ten );
		/* Máy thì GỠ GÁN, không xoá: cái máy vẫn là tài sản đang cắm ở đâu đó. Lượt bấm của nó
		   sẽ nằm ở hàng "chờ gán" cho tới khi được gán lại — thấy được, và gán lại được. */
		$may = (int) $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'may' ) . " SET cua_hang='' WHERE cua_hang=%s", $ten ) );
		$bp  = (int) $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'bo_phan_coso' ) . ' WHERE coso=%s', $ten ) );
		$bang = self::ten_coso_bang();
		$k    = self::chuan_coso( $ten );
		$xoa_ten = false;
		if ( isset( $bang[ $k ] ) ) { unset( $bang[ $k ] ); self::dat_ten_coso( $u, $bang ); $xoa_ten = true; }
		self::quen_ten_coso();

		return array( 'ok' => true, 'ten' => $ten, 'luot' => $luot, 'ho_so' => $hs,
			'may' => $may, 'bo_phan' => $bp, 'ten_day_du' => $xoa_ten );
	}

	/**
	 * XOÁ HẲN MỘT TÊN CƠ SỞ LẠ — gỡ khỏi mọi hồ sơ VÀ xoá mọi lượt chấm công mang đúng tên ấy.
	 *
	 * Anh Thắng 02/09/2026, ảnh lưới công của nhân viên có ba hàng cho một người: *"nó đang bị
	 * nhân lên, cách nào xoá luôn"*.
	 *
	 * 🔴 KHÁC `xoa_coso()` Ở ĐÚNG MỘT CHỖ, VÀ CHỖ ẤY QUAN TRỌNG: ở đây khớp theo TÊN THÔ, y
	 *    nguyên như trong kho. `xoa_coso()` chuẩn hoá mã trước khi khớp (`chuan_coso()` gỡ tiền
	 *    tố `CS_`, cắt tại dấu phẩy) — đúng cho cơ sở trong danh mục, nhưng SAI ở đây: tên lạ
	 *    thường lạ chính vì mấy ký tự mà phép chuẩn hoá sẽ cắt mất, và cắt xong thì câu xoá trỏ
	 *    vào một cái tên khác — có thể là một cơ sở đang chạy.
	 *
	 * ⚠️ MẤT LÀ MẤT THẬT. Nên `$mong_luot` bắt buộc phải khớp đúng số lượt màn hình vừa bày ra:
	 *    người mở màn lúc 9h, đi họp, 11h quay lại bấm — trong khoảng ấy máy có thể đã đẩy thêm
	 *    cả trăm lượt. Muốn GIỮ công thì đừng dùng hàm này, dùng `VHCC_Nhan::gop_coso()`.
	 */
	public static function xoa_coso_la( $u, $ten, $mong_luot ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Xoá cơ sở — ' . self::LOI_QT );
		}
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		foreach ( self::ds_coso() as $x ) {
			if ( 0 === strcasecmp( (string) $x, self::chuan_coso( $ten ) ) ) {
				return array( 'ok' => false, 'error' => '"' . $ten . '" là cơ sở ĐANG CÓ trong danh mục — '
					. 'xoá nó ở bảng Danh mục cơ sở, không phải ở khối Cơ sở lạ.' );
			}
		}
		$so = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $ten ) );
		if ( (int) $mong_luot !== $so ) {
			return array( 'ok' => false, 'error' => 'Số lượt của "' . $ten . '" vừa đổi (' . $so
				. ' chứ không phải ' . (int) $mong_luot . ') — có ai đó vừa chấm công vào tên này. '
				. 'Tải lại màn rồi bấm lại.' );
		}
		$xoa = 0;
		if ( $so > 0 ) {
			$xoa = (int) $wpdb->query( $wpdb->prepare(
				'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $ten ) );
		}
		$hs = self::go_coso_ho_so( $ten );
		return array( 'ok' => true, 'ten' => $ten, 'luot' => $xoa, 'ho_so' => $hs );
	}

	// ======================================================================= ghi

	/**
	 * Lưu hồ sơ. Trả array('ok'=>bool, 'error'=>..., 'tao_moi'=>bool).
	 *
	 * ⚠️ Bốn chốt, mỗi chốt một lý do khác nhau — bỏ chốt nào cũng mất một thứ khác nhau.
	 */
	/**
	 * PIN NÀY ĐANG LÀ CỦA AI — trả mã NV, hoặc chuỗi rỗng nếu chưa ai dùng.
	 *
	 * 🔴 HAI NGƯỜI CÙNG MỘT PIN LÀ ĐĂNG NHẬP NHẦM NGƯỜI. `VHCC_Auth::login()` tra theo PIN, nên
	 *    người vào được sẽ là người đầu tiên khớp — và người kia gõ đúng PIN của mình mà vào
	 *    nhầm tài khoản, thấy công của người khác, nộp đơn dưới tên người khác. Đây là loại lỗi
	 *    không ai báo, vì người dùng tưởng mình bấm nhầm.
	 *
	 * ⚠️ KHÔNG trả về PIN, chỉ trả MÃ NV. Nơi gọi cần biết "có đụng ai không", không cần biết số.
	 */
	public static function pin_dang_dung( $pin, $tru_ma = '' ) {
		global $wpdb;
		$pin = trim( (string) $pin );
		if ( '' === $pin ) { return ''; }
		$ma = $wpdb->get_var( $wpdb->prepare(
			'SELECT ma_nv FROM ' . VHCC_DB::t( 'nhan_vien' )
			. ' WHERE pin_dang_nhap=%s AND ma_nv<>%s LIMIT 1', $pin, trim( (string) $tru_ma ) ) );
		return null === $ma ? '' : (string) $ma;
	}

	/** Bốn ô liên lạc cửa hàng được sửa. Khai MỘT chỗ để cửa và màn không bao giờ lệch nhau. */
	const O_CUA_HANG_SUA = array(
		'sdt'                => 'Số điện thoại',
		'dia_chi'            => 'Địa chỉ',
		'nguoi_lien_he_khan' => 'Người liên hệ khẩn',
		'sdt_khan'           => 'SĐT khẩn',
	);

	/**
	 * CỬA SỬA HỒ SƠ DÀNH CHO CỬA HÀNG — hẹp, và hẹp là điểm chính của nó.
	 *
	 * Anh Thắng 31/08/2026: mỗi cơ sở có hệ thống quản lý nhân sự con của mình, *"để tại cửa
	 * hàng trực tiếp quản lý dễ hơn"*; cửa hàng trưởng được sửa thông tin liên lạc và cấp PIN.
	 *
	 * 🔴 DANH SÁCH TRẮNG, KHÔNG PHẢI DANH SÁCH ĐEN. Chỉ bốn ô ở `O_CUA_HANG_SUA` cộng ô PIN đi
	 *    qua được; mọi ô khác bị bỏ lặng lẽ. Viết theo kiểu "chặn mấy ô nhạy cảm" thì thêm một
	 *    cột mới vào bảng là tự động mở cửa cho nó, và không ai nhớ ra để chặn.
	 *
	 * 🔴 KHÔNG ĐỔI ĐƯỢC CƠ SỞ, VAI TRÒ, LƯƠNG, SỐ TÀI KHOẢN, MÃ NV. Đổi cơ sở là chuyển công và
	 *    lương sang cửa hàng khác; đổi vai trò là tự nâng quyền cho mình. Hai việc ấy ở bậc trên.
	 *
	 * ⚠️ NGƯỜI LÀM HAI NƠI THÌ CẢ HAI CỬA HÀNG SỬA ĐƯỢC (anh Thắng chốt 31/08) — nên gác bằng
	 *    `co_quyen_ho_so()` chứ không phải `co_quyen_coso()` trên cơ sở đứng đầu. Đổi lại, mọi
	 *    lượt sửa đều vào sổ `nhat_ky_ho_so` kèm TÊN người sửa và CỬA HÀNG họ đang đứng: hai nơi
	 *    cùng sửa mà không có sổ thì lúc lệch chỉ còn mỗi câu "chắc bên kia sửa".
	 *
	 * @return array `ok` · `doi`(mảng tên ô đã đổi) hoặc `error`.
	 */
	public static function sua_ho_so_coso( $u, $ma, $dat ) {
		global $wpdb;
		$ma = trim( (string) $ma );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		/* ⚠️ PHÁ THỬ KHÔNG BẮT ĐƯỢC VIỆC BỎ DÒNG NÀY, và đó là điều bình thường — đã tìm ra lý do
		   (pha70, 31/08/2026): `co_quyen_ho_so()` ngay dưới hỏi `co_quyen_coso()`, mà hàm ấy
		   chặn ngay ai không có `cong_coso` — cùng bậc Cửa hàng trưởng với `ho_so_coso`. Nên
		   hôm nay hai gác chặn đúng cùng một nhóm người.
		   VẪN GIỮ, vì nó nói ĐÚNG việc đang xin: "sửa hồ sơ" chứ không phải "xem công cơ sở".
		   Ngày ai đó hạ `cong_coso` xuống bậc Nhân viên (chuyện hoàn toàn có thể — nhân viên xem
		   công cơ sở mình là một yêu cầu hợp lý), dòng này là thứ duy nhất còn chặn. */
		if ( ! VHCC_Vai::duoc( $u, 'ho_so_coso' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'ho_so_coso', 'Sửa hồ sơ' ) );
		}
		$cu = self::ho_so( $ma );
		if ( ! $cu ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_quyen_ho_so( $u, $cu ) ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ này không thuộc cơ sở bạn phụ trách.' );
		}

		$ghi = array();
		$sg  = array();
		foreach ( self::O_CUA_HANG_SUA as $o => $ten ) {
			if ( ! isset( $dat[ $o ] ) ) { continue; }
			$moi = trim( (string) $dat[ $o ] );
			if ( $moi === trim( (string) $cu[ $o ] ) ) { continue; }
			$ghi[ $o ] = $moi;
			$sg[]      = array( 'o' => $o, 'cu' => (string) $cu[ $o ], 'moi' => $moi );
		}

		/* PIN: trống = GIỮ NGUYÊN, không phải xoá — cùng luật với mọi ô PIN khác trong hệ. */
		if ( isset( $dat['pin_dang_nhap'] ) ) {
			$pin = VHCC_NapCsv::pin( (string) $dat['pin_dang_nhap'] );
			if ( '' !== $pin ) {
				$dung = self::pin_dang_dung( $pin, $ma );
				if ( '' !== $dung ) {
					return array( 'ok' => false,
						'error' => 'PIN này đang là PIN của một người khác. Chọn số khác.' );
				}
				$ghi['pin_dang_nhap'] = $pin;
				/* 🔴 SỔ KHÔNG BAO GIỜ GHI GIÁ TRỊ PIN — chỉ ghi rằng đã đổi. Sổ này người trong
				   công ty đọc được, mà PIN đọc một lần là dùng được mãi. */
				$sg[] = array( 'o' => 'pin_dang_nhap', 'cu' => '', 'moi' => 'đã đổi' );
			}
		}

		if ( ! $ghi ) { return array( 'ok' => true, 'doi' => array(), 'thong_bao' => 'Không có gì đổi.' ); }

		$ghi['cap_nhat'] = current_time( 'mysql' );
		$wpdb->update( VHCC_DB::t( 'nhan_vien' ), $ghi, array( 'ma_nv' => $ma ) );

		$ai = trim( isset( $u['name'] ) ? (string) $u['name'] : '' );
		$tu = implode( ', ', self::ds_coso_cua( $u ) );
		foreach ( $sg as $x ) {
			$wpdb->insert( VHCC_DB::t( 'nhat_ky_ho_so' ), array(
				'luc'     => current_time( 'mysql' ),
				'ma_nv'   => $ma,
				'ai'      => $ai,
				'tu_coso' => $tu,
				'o'       => $x['o'],
				'cu'      => mb_substr( $x['cu'], 0, 250 ),
				'moi'     => mb_substr( $x['moi'], 0, 250 ),
			) );
		}
		$ten_doi = array();
		foreach ( $sg as $x ) {
			$ten_doi[] = isset( self::O_CUA_HANG_SUA[ $x['o'] ] )
				? self::O_CUA_HANG_SUA[ $x['o'] ] : 'PIN đăng nhập';
		}
		return array( 'ok' => true, 'doi' => $ten_doi,
			'thong_bao' => 'Đã lưu: ' . implode( ', ', $ten_doi ) . '.' );
	}

	/**
	 * XEM SỐ PIN ĐANG DÙNG CỦA MỘT NGƯỜI — và ghi vào sổ rằng đã xem.
	 *
	 * Anh Thắng 31/08/2026, ảnh dãy nút dưới tên người ở trang Quản lý nhân sự: *"Bổ sung xem
	 * PIn được tại vị trí này"*.
	 *
	 * 🔴 TRẢ VỀ MỘT NGƯỜI MỘT LƯỢT, KHÔNG BAO GIỜ CẢ CỘT. Luật của cả hệ này từ đầu là không in
	 *    PIN ra màn — vì trang chạy ngoài internet và ảnh chụp bảng nhân sự đi khắp nơi (chính
	 *    anh Thắng gửi một tấm như thế kèm yêu cầu này). Nên đường xem là: bấm đúng một người,
	 *    tải lại trang, hiện đúng số của người ấy. Ảnh chụp cả bảng vẫn không lộ gì.
	 *
	 * 🔴 XEM LÀ MỘT VIỆC PHẢI VÀO SỔ. Biết PIN của người khác là đăng nhập thay họ được — đọc
	 *    công, nộp đơn, xin phép dưới tên họ — mà màn hình của họ không có gì đổi. Không ghi
	 *    lại thì ngày cần lần ra ai đã làm việc đó, không còn một dấu vết nào.
	 *
	 * ⚠️ SỔ GHI "đã xem", KHÔNG GHI SỐ. Sổ này người trong công ty đọc được.
	 *
	 * @return array `ok` · `pin` · `ho_ten`, hoặc `error`.
	 */
	public static function xem_pin( $u, $ma ) {
		global $wpdb;
		$ma = trim( (string) $ma );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		if ( ! VHCC_Vai::duoc( $u, 'xem_pin' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'xem_pin', 'Xem PIN' ) );
		}
		$hs = self::ho_so( $ma );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_quyen_ho_so( $u, $hs ) ) {
			return array( 'ok' => false, 'error' => 'Hồ sơ này không thuộc cơ sở bạn phụ trách.' );
		}
		$pin = trim( (string) $hs['pin_dang_nhap'] );
		if ( '' === $pin ) {
			return array( 'ok' => true, 'pin' => '', 'ho_ten' => (string) $hs['ho_ten'] );
		}

		$wpdb->insert( VHCC_DB::t( 'nhat_ky_ho_so' ), array(
			'luc'     => current_time( 'mysql' ),
			'ma_nv'   => $ma,
			'ai'      => trim( isset( $u['name'] ) ? (string) $u['name'] : '' ),
			'tu_coso' => implode( ', ', self::ds_coso_cua( $u ) ),
			'o'       => 'xem_pin',
			'cu'      => '',
			'moi'     => 'đã xem',
		) );
		return array( 'ok' => true, 'pin' => $pin, 'ho_ten' => (string) $hs['ho_ten'] );
	}

	/** Tên tiếng Việt của một ô trong sổ nhật ký hồ sơ. Một chỗ, để ba màn không gọi ba tên. */
	public static function ten_o_nhat_ky( $o ) {
		if ( isset( self::O_CUA_HANG_SUA[ $o ] ) ) { return self::O_CUA_HANG_SUA[ $o ]; }
		if ( 'xem_pin' === $o ) { return 'XEM số PIN'; }
		return 'PIN đăng nhập';
	}

	/** Mấy lượt sửa gần nhất của một người — đọc để đối chiếu khi số liệu lệch. */
	public static function nhat_ky_ho_so( $ma, $tran = 20 ) {
		global $wpdb;
		return VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'nhat_ky_ho_so' )
			. ' WHERE ma_nv=%s ORDER BY id DESC LIMIT %d', trim( (string) $ma ), (int) $tran ) );
	}

	public static function luu_ho_so( $u, $dat ) {
		global $wpdb;
		$ma = trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		$cu = self::ho_so( $ma );
		$coso_moi = self::chuan_coso( isset( $dat['cua_hang'] ) ? $dat['cua_hang'] : '' );

		// Chốt 1: bậc dưới cùng.
		if ( ! self::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền sửa hồ sơ nhân sự.' );
		}
		// Chốt 2: TẠO MỚI là cấp Mã NV dùng chung cả chuỗi -> chỉ Admin/Quản lý.
		if ( ! $cu && ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false,
				'error' => 'Tạo hồ sơ mới cần cấp Mã NV — mã dùng chung cả chuỗi nên ' . self::LOI_QT );
		}
		// Chốt 3: ĐỔI cửa hàng là chuyển cả công và lương giữa hai cửa hàng -> chỉ Admin/Quản lý.
		if ( $cu ) {
			$coso_cu = self::chuan_coso( $cu['cua_hang'] );
			if ( '' !== $coso_moi && strtolower( $coso_cu ) !== strtolower( $coso_moi )
				&& ! self::co_quan_tri_nv( $u ) ) {
				return array( 'ok' => false,
					'error' => 'Đổi cửa hàng của một người là chuyển công và lương giữa hai cửa hàng — '
						. self::LOI_QT . ' Cần người này làm thêm ở cửa hàng bạn thì khai vào ô Cơ sở phụ.' );
			}
			/* Chốt 4: chỉ sửa người của cơ sở mình — nhưng tính CẢ cơ sở người ta tích thêm.
			   Anh Thắng 31/08/2026 chốt: người làm hai nơi thì CẢ HAI cửa hàng sửa được. Hỏi mỗi
			   cơ sở đứng đầu là dựng lại đúng cái thứ bậc chính/phụ vừa bỏ. */
			if ( ! self::co_quyen_ho_so( $u, $cu ) ) {
				return array( 'ok' => false, 'error' => 'Hồ sơ này không thuộc cơ sở bạn phụ trách.' );
			}
		} elseif ( '' !== $coso_moi && ! self::co_quyen_coso( $u, $coso_moi ) ) {
			return array( 'ok' => false, 'error' => 'Bạn không phụ trách cơ sở "' . $coso_moi . '".' );
		}

		$cho_phep = array( 'ho_ten', 'cua_hang', 'pin_may', 'sdt', 'ngay_sinh', 'gioi_tinh', 'cccd',
			'dia_chi', 'nguoi_lien_he_khan', 'sdt_khan', 'chuc_vu', 'ngay_vao_lam',
			'trang_thai_lam_viec', 'loai_hop_dong', 'nhiem_vu', 'coso_phu', 'pin_dang_nhap' );
		if ( self::co_xem_luong( $u ) ) {
			$cho_phep = array_merge( $cho_phep, self::O_LUONG );
		}
		/* Danh sách CHO PHÉP, không phải danh sách CHẶN. Với danh sách chặn thì mỗi cột mới thêm
		   vào bảng là một ô người ta ghi được mà không ai nhớ ra phải chặn. */
		$ghi = array();
		foreach ( $cho_phep as $o ) {
			if ( ! array_key_exists( $o, $dat ) ) { continue; }
			$v = $dat[ $o ];
			if ( in_array( $o, array( 'ngay_sinh', 'ngay_vao_lam' ), true ) ) {
				$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $v ) ) ? trim( (string) $v ) : null;
			} elseif ( 'luong_co_ban' === $o ) {
				$v = self::so_tien( $v );
			} else {
				$v = trim( (string) $v );
			}
			$ghi[ $o ] = $v;
		}
		if ( isset( $ghi['cua_hang'] ) ) { $ghi['cua_hang'] = self::chuan_coso( $ghi['cua_hang'] ); }
		$ghi['cap_nhat'] = current_time( 'mysql' );

		if ( $cu ) {
			$ok = $wpdb->update( VHCC_DB::t( 'nhan_vien' ), $ghi, array( 'ma_nv' => $ma ) );
			return ( false === $ok )
				? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
				: array( 'ok' => true, 'tao_moi' => false );
		}
		$ghi['ma_nv'] = $ma;
		$ok = $wpdb->insert( VHCC_DB::t( 'nhan_vien' ), $ghi );
		return ( false === $ok )
			? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
			: array( 'ok' => true, 'tao_moi' => true );
	}

	/**
	 * CỬA HÀNG TRƯỞNG THÊM NGƯỜI MỚI VÀO CƠ SỞ CỦA MÌNH.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO KHÔNG NỚI `luu_ho_so()` RA MÀ LÀM MỘT ĐƯỜNG RIÊNG.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"thêm phần cửa hàng trưởng bổ sung nhân sự cho cửa hàng mình"*, và
	 * trước đó: *"Khi thêm nó sẽ đẩy sang nhân sự và tự tạo mã NV tạm (Admin sẽ sửa lại sau),
	 * để tài khoản có thể dùng được ngay và đẩy lên máy chấm công"*.
	 *
	 * `luu_ho_so()` chặn tạo mới ở bậc Quản lý, và chốt ấy ĐÚNG: mã NV là mã dùng chung cả
	 * chuỗi, cấp bừa thì hai cửa hàng cấp trùng nhau và không ai biết cho tới kỳ lương. Hạ chốt
	 * đó xuống bậc Cửa hàng trưởng là mở luôn cả đường sửa lương, đổi cơ sở, cấp mã chuẩn.
	 *
	 * Nên: một cửa HẸP riêng. Cửa này chỉ làm được đúng một việc — mở hồ sơ TẠM cho người vừa
	 * vào làm ở CƠ SỞ MÌNH — và mã nó cấp mang tiền tố `TAM-` để Admin lọc ra đổi lại sau.
	 *
	 * ⚠️ BẮT BUỘC CĂN CƯỚC, VÀ ĐÓ LÀ ĐIỀU KIỆN ĐỂ "DÙNG ĐƯỢC NGAY".
	 *    Hệ không cấp PIN qua tay ai cả: người mới tự vào trang chấm công → "Quên PIN" → gõ Họ
	 *    tên + Căn cước của chính mình → tự đặt PIN. Thiếu căn cước là đường ấy tắc, và tài
	 *    khoản vừa tạo thành một hồ sơ chết mà cửa hàng trưởng tưởng đã xong.
	 *    Cách này cũng có nghĩa KHÔNG AI phải đọc PIN của ai — kể cả cửa hàng trưởng.
	 *
	 * ⚠️ CĂN CƯỚC TRÙNG THÌ CHỐI, VÀ CHỈ RA NGƯỜI ĐANG CÓ. Một người làm hai cơ sở là chuyện
	 *    thường ở chuỗi này (anh Thắng hỏi 28/08); nhưng đó là MỘT hồ sơ khai thêm Cơ sở phụ,
	 *    không phải hai hồ sơ. Tạo hồ sơ thứ hai là nhân đôi người trên bảng lương.
	 */
	public static function them_nv_cua_hang( $u, $dat ) {
		global $wpdb;
		if ( ! VHCC_Vai::duoc( $u, 'them_nv' ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, 'them_nv', 'Thêm người mới' ) );
		}
		$ten = trim( (string) ( isset( $dat['ho_ten'] ) ? $dat['ho_ten'] : '' ) );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }

		/* Cơ sở: lấy ô người ta chọn, bỏ trống thì hiểu là cơ sở của chính mình. Dù đường nào
		   cũng phải qua `co_quyen_coso` — ô chọn trên màn hình không phải là chốt. */
		$coso = self::chuan_coso( isset( $dat['cua_hang'] ) ? $dat['cua_hang'] : '' );
		if ( '' === $coso ) { $coso = self::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' ); }
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Chưa rõ thêm vào cơ sở nào.' );
		}
		if ( ! self::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Bạn không phụ trách cơ sở "' . $coso . '".' );
		}

		$cccd = preg_replace( '/\D+/', '', (string) ( isset( $dat['cccd'] ) ? $dat['cccd'] : '' ) );
		if ( strlen( $cccd ) < 9 || strlen( $cccd ) > 12 ) {
			return array( 'ok' => false, 'error' => 'Phải có số căn cước (12 số, hoặc 9 số nếu là '
				. 'CMND cũ) — đó là thứ để người mới tự lấy mã PIN ở màn "Quên PIN".' );
		}
		$trung = self::ho_so_theo_cccd( $cccd );
		if ( $trung ) {
			return array( 'ok' => false, 'error' => 'Số căn cước này đã có hồ sơ: '
				. $trung['ho_ten'] . ' (' . $trung['ma_nv'] . ', cơ sở ' . $trung['cua_hang'] . '). '
				. 'Người làm ở hai cơ sở thì khai thêm vào ô "Cơ sở phụ" của hồ sơ ấy, '
				. 'đừng mở hồ sơ thứ hai — bảng lương sẽ tính người đó hai lần.' );
		}

		$ma = self::ma_tam( $coso );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Không cấp được mã tạm — thử lại.' );
		}

		$ghi = array(
			'ma_nv'                => $ma,
			'ho_ten'               => $ten,
			'cua_hang'             => $coso,
			'cccd'                 => $cccd,
			'sdt'                  => trim( (string) ( isset( $dat['sdt'] ) ? $dat['sdt'] : '' ) ),
			'gioi_tinh'            => trim( (string) ( isset( $dat['gioi_tinh'] ) ? $dat['gioi_tinh'] : '' ) ),
			'chuc_vu'              => trim( (string) ( isset( $dat['chuc_vu'] ) ? $dat['chuc_vu'] : '' ) ),
			'ngay_vao_lam'         => current_time( 'Y-m-d' ),
			'trang_thai_lam_viec'  => 'Đang làm',
			/* 🔴 PHẢI ĐẶT VAI, KHÔNG ĐƯỢC ĐỂ TRỐNG.
			   Anh Thắng 28/08/2026: *"cửa hàng đã thêm nhân viên mới, mà bên nhân sự chưa thấy
			   thông tin nhân viên đó"* — kèm ảnh hàng `TAM-FZSCVIVO-001` với cột Vai trò ghi
			   «— chưa khai —». Hồ sơ CÓ sang, nhưng trống vai.
			   Vai rỗng không phải là "chưa quyết": nó là một hồ sơ mà mọi cửa trong hệ đều phải
			   ĐOÁN xem người ấy bậc mấy — và mỗi cửa đoán một kiểu. Người mới vào làm thì vai
			   là Nhân viên; Admin nâng lên sau nếu cần. */
			'vai_tro'              => VHCC_Vai::TEN[ VHCC_Vai::NV ],
			'cap_nhat'             => current_time( 'mysql' ),
		);
		/* ⚠️ ẢNH THẺ KHÔNG BẮT BUỘC — anh Thắng 28/08/2026 chốt *"không ép buộc, nhưng không có
		   là phải đưa ra cảnh báo bù sau"*. Nên thiếu ảnh vẫn tạo hồ sơ xong; chỗ nhắc nằm ở
		   khối "chưa có ảnh thẻ" trên màn Bảng công. */
		$anh = isset( $dat['anh_the'] ) ? trim( (string) $dat['anh_the'] ) : '';
		if ( '' !== $anh ) { $ghi['anh_the'] = $anh; }
		$ok = $wpdb->insert( VHCC_DB::t( 'nhan_vien' ), $ghi );
		if ( false === $ok ) {
			return array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error );
		}

		$day = self::day_len_may( $ma, $ten, $coso, $ghi['gioi_tinh'], $u, $anh );

		/**
		 * 🔴 ẢNH THẺ CŨNG LÀ MẪU ĐỐI CHIẾU KHUÔN MẶT CHO CHẤM CÔNG ONLINE.
		 *
		 * Anh Thắng 29/08/2026: *"nếu chưa có máy thì ảnh chụp đó cũng dùng để xác định face qua
		 * chấm công online"*. Ý này đã được nhắc từ 28/08/2026 ở docblock của
		 * `VHCC_Web::khoi_them_nv()` ("làm mẫu đối chiếu khuôn mặt cho chấm công online") nhưng
		 * chưa có đường nối — trước bản này, mẫu (`VHCC_Mat`) CHỈ tự lấy từ lượt chấm công ONLINE
		 * ĐẦU TIÊN, và phải qua `cho` (chờ duyệt) vì không chắc ngày đầu có đúng người thật đứng
		 * chụp không (xem `VHCC_Mat::soi()`).
		 *
		 * `$dat['vector']` là dãy đặc trưng 128 số TRÌNH DUYỆT đã tính sẵn từ chính tấm ảnh thẻ
		 * lúc chụp — máy chủ không tính lại được (không có thư viện nhận diện, xem đầu
		 * `class-vhcc-mat.php`). Không có ô này (trình duyệt cũ, thư viện chưa tải kịp, ảnh không
		 * thấy mặt) thì bỏ qua — hồ sơ vẫn tạo bình thường, mẫu để dành cho lượt chấm công đầu
		 * tiên như trước giờ.
		 *
		 * ⚠️ SEED THẲNG THÀNH 'duyet', KHÔNG QUA 'cho'. Khác lượt chấm công ẩn danh, ảnh thẻ này
		 *    chụp có Cửa hàng trưởng đứng cạnh xác nhận đúng người — bắt Kế toán duyệt lại là bắt
		 *    xác nhận một việc CHT vừa làm xong trước mặt người đó rồi.
		 *
		 * ⚠️ KHÔNG PHÂN BIỆT CÓ MÁY HAY KHÔNG. Cơ sở có máy vẫn cần chấm công online làm phương
		 *    án dự phòng lúc máy mất mạng hay đứt điện — seed mẫu luôn, không đợi biết cơ sở có
		 *    máy hay chưa.
		 */
		$mau_mat = '';
		if ( '' !== $anh && isset( $dat['vector'] ) && class_exists( 'VHCC_Mat' )
			&& method_exists( 'VHCC_Mat', 'dat_mau_tu_anh_the' )
			&& VHCC_Mat::dat_mau_tu_anh_the( $ma, $dat['vector'] ) ) {
			$mau_mat = ' Đã lấy ảnh thẻ làm mẫu đối chiếu khuôn mặt cho chấm công online.';
		}

		return array( 'ok' => true, 'ma_nv' => $ma, 'coso' => $coso, 'day_may' => $day . $mau_mat,
			'co_anh' => ( '' !== $anh ) );
	}

	/** Ảnh thẻ tối đa sau khi thu nhỏ — cạnh dài, và cỡ chuỗi data URI. */
	const ANH_CANH   = 480;
	const ANH_TOI_DA = 400000;   // ~400 KB chuỗi: quá cỡ này thì máy chấm công cũng nuốt không nổi

	/**
	 * NHẬN MỘT TỆP ẢNH THẺ TỪ BIỂU MẪU, TRẢ VỀ data URI ĐÃ THU NHỎ.
	 *
	 * =========================================================================================
	 * 🔴 THU NHỎ Ở MÁY CHỦ, VÌ MÀN QUẢN TRỊ KHÔNG CÓ MỘT DÒNG SCRIPT NÀO.
	 * =========================================================================================
	 * Bình thường ảnh được thu nhỏ ngay trên trình duyệt trước khi gửi (trạm chấm công làm thế).
	 * Màn quản trị thì cố ý không có JavaScript — nên ảnh từ điện thoại lên đây vẫn là ảnh gốc
	 * 3–5 MB. Không thu nhỏ thì cột `anh_the` phình, và cái ảnh ấy còn phải chui qua hàng đợi
	 * xuống một con ESP32 có vài trăm KB bộ nhớ.
	 *
	 * ⚠️ TRẢ VỀ LỖI CÓ CHỮ, KHÔNG TRẢ VỀ RỖNG. Ảnh là phần KHÔNG BẮT BUỘC, nên một lỗi nuốt im
	 *    sẽ thành "đã thêm người xong" mà ảnh biến mất — và không ai biết để chụp lại.
	 *
	 * @return array [ 'ok' => bool, 'anh' => data URI, 'error' => câu chối ]
	 */
	public static function rua_anh_the( $tep ) {
		$tep = (array) $tep;
		$loi = isset( $tep['error'] ) ? (int) $tep['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $loi ) { return array( 'ok' => true, 'anh' => '' ); }
		if ( UPLOAD_ERR_OK !== $loi ) {
			return array( 'ok' => false, 'anh' => '',
				'error' => 'Ảnh không tải lên được (mã lỗi ' . $loi . ') — thử ảnh nhỏ hơn.' );
		}
		$duong = isset( $tep['tmp_name'] ) ? (string) $tep['tmp_name'] : '';
		if ( '' === $duong || ! is_readable( $duong ) ) {
			return array( 'ok' => false, 'anh' => '', 'error' => 'Không đọc được tệp ảnh vừa gửi.' );
		}
		/* ⚠️ Tin phần đuôi tên tệp là tin người gửi. Hỏi CHÍNH TỆP xem nó là ảnh gì. */
		$co = @getimagesize( $duong );
		if ( ! $co || empty( $co['mime'] ) ) {
			return array( 'ok' => false, 'anh' => '', 'error' => 'Tệp này không phải ảnh.' );
		}
		if ( ! in_array( $co['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return array( 'ok' => false, 'anh' => '',
				'error' => 'Chỉ nhận ảnh JPG, PNG hoặc WEBP — tệp này là ' . $co['mime'] . '.' );
		}

		/* ⚠️ Gác `function_exists` cùng chỗ với lời gọi. Hosting thiếu bộ ảnh của WordPress thì
		   nói thẳng, đừng lưu đại ảnh gốc 5 MB vào cột dữ liệu. */
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return array( 'ok' => false, 'anh' => '',
				'error' => 'Máy chủ chưa có bộ xử lý ảnh — nhờ Admin bật GD hoặc Imagick.' );
		}
		$bt = wp_get_image_editor( $duong );
		if ( is_wp_error( $bt ) ) {
			return array( 'ok' => false, 'anh' => '',
				'error' => 'Không mở được ảnh: ' . $bt->get_error_message() );
		}
		$bt->resize( self::ANH_CANH, self::ANH_CANH, false );   // false = giữ nguyên tỉ lệ
		if ( method_exists( $bt, 'set_quality' ) ) { $bt->set_quality( 82 ); }
		$tam = VHCC_DB::tep_tam( 'vhcc-anh-the' );
		$luu = $bt->save( $tam, 'image/jpeg' );
		if ( is_wp_error( $luu ) || empty( $luu['path'] ) || ! is_readable( $luu['path'] ) ) {
			return array( 'ok' => false, 'anh' => '', 'error' => 'Không lưu được ảnh sau khi thu nhỏ.' );
		}
		$noi = file_get_contents( $luu['path'] );
		@unlink( $luu['path'] );
		if ( $tam !== $luu['path'] ) { @unlink( $tam ); }
		if ( false === $noi || '' === $noi ) {
			return array( 'ok' => false, 'anh' => '', 'error' => 'Ảnh sau khi thu nhỏ bị rỗng.' );
		}
		$uri = 'data:image/jpeg;base64,' . base64_encode( $noi );
		if ( strlen( $uri ) > self::ANH_TOI_DA ) {
			return array( 'ok' => false, 'anh' => '',
				'error' => 'Ảnh vẫn quá nặng sau khi thu nhỏ — chụp lại bằng ảnh thường, đừng dùng ảnh RAW.' );
		}
		return array( 'ok' => true, 'anh' => $uri );
	}

	/**
	 * CHỨC VỤ ĐỂ CHỌN SẴN, TỪ BẬC NGƯỜI ĐANG THÊM ĐỔ XUỐNG.
	 *
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"Thiếu chức vụ, lấy sẵn từ hệ thống (chức vụ lấy từ chức vụ của
	 * cửa hàng trưởng đi xuống)"*.
	 *
	 * Chức vụ trong sổ là chữ tự do — "Nhân viên bán hàng", "Thu ngân", "Ca trưởng"… Không có
	 * thang bậc nào cho nó, nên KHÔNG suy ra được cái nào "cao hơn" cái nào. Cái CÓ thang là
	 * VAI TRÒ. Nên luật ở đây đọc là: bày ra những chức vụ đang có thật ở cơ sở này, TRỪ chức
	 * vụ của những người mang vai CAO HƠN người đang thêm.
	 *
	 * ⚠️ LẤY TỪ CƠ SỞ NÀY, KHÔNG LẤY CẢ CHUỖI. Danh sách cả chuỗi có "Kế toán trưởng", "Giám
	 *    sát vùng" — bày ra là mời người ta chọn nhầm, và chức vụ ấy đi thẳng vào bảng lương.
	 *
	 * ⚠️ VẪN PHẢI CÓ ĐƯỜNG GÕ TAY. Cửa hàng mới mở thì sổ chưa có chức vụ nào; ô chọn rỗng mà
	 *    không gõ được là bế tắc, và người ta bỏ trống luôn ô ấy.
	 */
	/**
	 * Chức vụ khai sẵn — có ngay cả khi sổ chưa có gì để gợi ý.
	 *
	 * 🔴 PHẢI CÓ DANH SÁCH NÀY, KHÔNG CHỈ DỰA VÀO SỔ. Anh Thắng 28/08/2026 nhìn ô gợi ý và nói
	 *    *"Nhầm chức vụ rồi"* — nó bày ra "Khu vui chơi", vốn là một BỘ PHẬN. Đúng dữ liệu: sổ
	 *    thật nạp từ Sheets cũ có cột `chuc_vu` chứa lẫn tên bộ phận. Lấy nguyên xi ra là dạy
	 *    người dùng khai tiếp cái sai ấy cho người mới.
	 */
	const CHUC_VU_SAN = array( 'Nhân viên', 'Nhân viên bán hàng', 'Thu ngân', 'Ca trưởng',
		'Giám sát', 'Kỹ thuật', 'Bảo vệ', 'Tạp vụ', 'Pha chế', 'Phục vụ' );

	public static function chuc_vu_cho( $u, $coso ) {
		$cs = self::chuan_coso( $coso );
		if ( '' === $cs ) { return array(); }
		$bac_toi = class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'bac' )
			? (int) VHCC_Vai::bac( $u ) : 99;
		$ra = array();
		foreach ( (array) VHCC_DB::rows( 'SELECT chuc_vu, cua_hang, vai_tro, trang_thai_lam_viec'
			. ' FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE TRIM(chuc_vu) <> ''" ) as $r ) {
			if ( strtolower( self::chuan_coso( (string) $r['cua_hang'] ) ) !== strtolower( $cs ) ) { continue; }
			if ( self::da_nghi( $r['trang_thai_lam_viec'] ) ) { continue; }
			/* Người mang vai cao hơn mình thì chức vụ của họ cũng không phải thứ mình cấp được. */
			if ( class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'bac' ) ) {
				$bac_ho = (int) VHCC_Vai::bac( array( 'role' => (string) $r['vai_tro'] ) );
				if ( $bac_ho > $bac_toi ) { continue; }
			}
			$cv = trim( (string) $r['chuc_vu'] );
			if ( '' === $cv ) { continue; }
			/* 🔴 BỎ NHỮNG GIÁ TRỊ VỐN LÀ TÊN BỘ PHẬN. Sổ cũ trộn hai thứ vào một cột, và bày
			   "Khu vui chơi" ra ô Chức vụ là mời người ta chép tiếp cái nhầm ấy sang người mới.
			   Lọc ở ĐÂY chứ không đi sửa sổ: sổ là dữ liệu thật của anh Thắng, sửa hàng loạt là
			   việc khác và phải anh quyết. */
			if ( self::la_ten_bo_phan( $cv ) ) { continue; }
			/* ⚠️ GIỮ CÁCH VIẾT GẶP TRƯỚC, không để bản sau đè. Hai hồ sơ ghi "Thu ngân" và
			   "THU NGÂN" là CÙNG một chức vụ — gom làm một dòng gợi ý là đúng, nhưng bày ra bản
			   nào thì phải ổn định. Đè bừa thì danh sách đổi mặt mỗi lần có người mới nhập ẩu. */
			$k_cv = self::chu_thuong( $cv );
			if ( ! isset( $ra[ $k_cv ] ) ) { $ra[ $k_cv ] = $cv; }
		}
		/* Danh sách khai sẵn đứng TRƯỚC, rồi mới tới cái dò từ sổ — người mở ô ra thấy ngay
		   những chức vụ đúng nghĩa, không phải cuộn qua một mớ tên bộ phận cũ. */
		ksort( $ra );
		$dau = array();
		foreach ( self::CHUC_VU_SAN as $x ) {
			$k = self::chu_thuong( $x );
			if ( isset( $ra[ $k ] ) ) { unset( $ra[ $k ] ); }
			$dau[] = $x;
		}
		return array_merge( $dau, array_values( $ra ) );
	}

	/**
	 * Chuỗi này có phải tên một BỘ PHẬN không.
	 *
	 * ⚠️ So bằng `chu_thuong` chứ không `strtolower`: `strtolower` của PHP không hạ được chữ CÓ
	 *    DẤU, nên "KHU VUI CHƠI" viết hoa lọt qua và vẫn hiện ra ô Chức vụ.
	 */
	public static function la_ten_bo_phan( $s ) {
		$k = self::chu_thuong( trim( (string) $s ) );
		if ( '' === $k ) { return false; }
		if ( ! class_exists( 'VHCC_Luong' ) || ! defined( 'VHCC_Luong::BP_DS' ) ) { return false; }
		$ds = (array) constant( 'VHCC_Luong::BP_DS' );
		if ( defined( 'VHCC_Luong::BP_CHUA_XEP' ) ) { $ds[] = constant( 'VHCC_Luong::BP_CHUA_XEP' ); }
		foreach ( $ds as $x ) {
			if ( self::chu_thuong( $x ) === $k ) { return true; }
		}
		return false;
	}

	/** Ai trong cơ sở này CHƯA có ảnh thẻ — để bày ra mà bù sau. */
	public static function thieu_anh_the( $coso ) {
		$cs = self::chuan_coso( $coso );
		if ( '' === $cs ) { return array(); }
		$ra = array();
		foreach ( (array) VHCC_DB::rows( 'SELECT ma_nv, ho_ten, cua_hang, trang_thai_lam_viec,'
			. " anh_the FROM " . VHCC_DB::t( 'nhan_vien' )
			. " WHERE TRIM(ma_nv) <> ''" ) as $r ) {
			if ( strtolower( self::chuan_coso( (string) $r['cua_hang'] ) ) !== strtolower( $cs ) ) { continue; }
			/* Người đã nghỉ thì thôi — nhắc chụp ảnh một người không còn đi làm là nhiễu. */
			if ( self::da_nghi( $r['trang_thai_lam_viec'] ) ) { continue; }
			if ( '' !== trim( (string) $r['anh_the'] ) ) { continue; }
			$ra[] = array( 'ma_nv' => (string) $r['ma_nv'], 'ho_ten' => (string) $r['ho_ten'] );
		}
		return $ra;
	}

	/** Hồ sơ mang số căn cước này — null là chưa ai. So bằng CHỮ SỐ, bỏ mọi dấu cách / gạch. */
	public static function ho_so_theo_cccd( $cccd ) {
		$so = preg_replace( '/\D+/', '', (string) $cccd );
		if ( '' === $so ) { return null; }
		foreach ( (array) VHCC_DB::rows( 'SELECT ma_nv, ho_ten, cua_hang, cccd FROM '
			. VHCC_DB::t( 'nhan_vien' ) . " WHERE TRIM(cccd) <> ''" ) as $r ) {
			if ( preg_replace( '/\D+/', '', (string) $r['cccd'] ) === $so ) { return $r; }
		}
		return null;
	}

	/**
	 * Cấp một mã TẠM chưa ai dùng, dạng `TAM-<CƠ SỞ>-<số>`.
	 *
	 * ⚠️ TIỀN TỐ `TAM-` LÀ CỐ Ý, KHÔNG PHẢI CHO ĐẸP. Mã tạm mà trông giống mã chuẩn thì không ai
	 *    lọc ra được để đổi, và nó nằm lại trong bảng lương vài tháng. Nhìn là biết ngay.
	 *
	 * ⚠️ ĐẾM TỪ SỐ ĐANG CÓ, KHÔNG PHẢI TỪ SỐ HỒ SƠ. Xoá một hồ sơ tạm rồi tạo lại là cấp trùng
	 *    mã vừa xoá — mà lượt chấm công cũ vẫn mang mã ấy, nên công của người cũ chảy sang
	 *    người mới. Nên: dò tới khi gặp một mã chưa ai dùng.
	 */
	public static function ma_tam( $coso ) {
		$cs = strtoupper( preg_replace( '/[^A-Za-z0-9]+/', '', self::chuan_coso( $coso ) ) );
		if ( '' === $cs ) { $cs = 'CS'; }
		$cs = substr( $cs, 0, 8 );
		for ( $i = 1; $i <= 999; $i++ ) {
			$thu = 'TAM-' . $cs . '-' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT );
			if ( ! self::ho_so( $thu ) && ! self::co_cham_cong( $thu ) ) { return $thu; }
		}
		return '';
	}

	/** Mã này đã có lượt chấm công nào chưa — kể cả khi hồ sơ đã bị xoá. */
	public static function co_cham_cong( $ma_nv ) {
		return self::so_luot_cham( $ma_nv ) > 0;
	}

	/**
	 * ĐẾM lượt chấm công của một mã.
	 *
	 * Cùng phép đếm mà `xoa_ho_so()` dùng để chối — cố ý dùng chung một hàm, để màn hỏi trước
	 * khi xoá nói ra ĐÚNG con số hệ sẽ dựa vào. Hai phép đếm viết rời nhau thì có ngày màn báo
	 * "chưa có lượt nào" mà bấm xong lại bị chối, và không ai hiểu vì sao.
	 */
	public static function so_luot_cham( $ma_nv ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $ma ) );
	}

	/**
	 * ĐẶT LỆNH XUỐNG ĐẦU ĐỌC CỦA CƠ SỞ — dùng chung cho cả ba việc add/edit/delete.
	 *
	 * Firmware (`esp32_hik_chamcong_full.ino`) đã nhận đủ cả ba action này từ trước (ISAPI
	 * UserInfo add/edit, UserInfoDetail delete-by-employeeNo) — chỉ là phía web trước đây CHỈ
	 * GỌI action `add` (lúc tạo hồ sơ), chưa có đường gọi `edit`/`delete`. Gộp chung một hàm để
	 * ba việc luôn cùng một luật tìm máy/báo tình trạng, không lệch nhau khi sửa sau này.
	 *
	 * ⚠️ KHÔNG CÓ MÁY THÌ KHÔNG PHẢI LỖI. Cơ sở chưa gắn máy, hay máy đang mất mạng, thì việc
	 *    trên web (tạo/sửa/cho nghỉ) vẫn phải xong — trả về câu nói rõ tình trạng để màn hình bày
	 *    ra, chứ không nuốt lời rồi để người ta tưởng đã xong.
	 *
	 * ⚠️ Máy chỉ nhận TÊN và MÃ (cùng ảnh nếu có). Khuôn mặt vẫn phải lấy tại máy khi KHÔNG có
	 *    ảnh — không đường nào đẩy mặt từ web xuống được nếu thiếu ảnh, và nói khác đi là hứa
	 *    một thứ không có.
	 *
	 * @param string $action 'add' | 'edit' | 'delete'.
	 */
	/**
	 * ⚠️ `$can_duyet=true` GIỮ LỆNH LẠI CHỜ ADMIN, KHÔNG ĐƯA THẲNG CHO MÁY.
	 * Anh Thắng 29/08/2026: *"trước khi đẩy xuống máy, nó sẽ gửi qua admin duyệt 1 lệnh để check
	 * đạt yêu cầu chưa trước khi đẩy"*. Chỉ `day_len_may()` (đường "Cửa hàng trưởng thêm nhanh",
	 * KHÔNG có quyền `ho_so`) truyền `true` — đó là đường DUY NHẤT một vai không có quyền hồ sơ
	 * tự đẩy được xuống máy. `sua_lai_tren_may()`/`xoa_khoi_may()` không truyền, vì cả hai đã đòi
	 * `co_sua_ho_so()` (Kế toán+) rồi — bắt Kế toán duyệt lại việc Kế toán vừa tự bấm là một vòng
	 * không ai canh thêm được gì, chỉ thêm bước bấm.
	 */
	private static function lenh_may_( $ma_nv, $ho_ten, $coso, $gioi_tinh, $u, $anh, $action, $can_duyet = false ) {
		if ( ! class_exists( 'VHCC_May' ) || ! method_exists( 'VHCC_May', 'ds_may' ) ) {
			return 'Chưa cài phần máy chấm công.';
		}
		$ds = VHCC_May::ds_may();
		$cs = strtolower( self::chuan_coso( $coso ) );
		$so = 0;
		foreach ( (array) ( isset( $ds['data'] ) ? $ds['data'] : array() ) as $m ) {
			if ( strtolower( self::chuan_coso( (string) $m['cua_hang'] ) ) !== $cs ) { continue; }
			/* Lệnh xoá chỉ cần mã — máy tự tra rồi bỏ đúng người, không cần tên/ảnh đi kèm. */
			$kem = array( 'ma_nv' => $ma_nv, 'cua_hang' => $coso );
			if ( 'delete' !== $action ) {
				/* Ảnh đi kèm LỆNH chứ không phải đi kèm hồ sơ: firmware hỏi ảnh bằng `opId` ở một
				   lượt gọi RIÊNG (xem `VHCC_MayCong::anh_cua_lenh`), vì con ESP32 không đủ bộ nhớ
				   nhận cả JSON lệnh lẫn ảnh trong một lượt. Cột `anh_b64` bị xoá ngay khi máy báo
				   xong — ảnh gốc vẫn nằm trong hồ sơ, đây chỉ là bản đi đường. */
				$kem['ho_ten']    = $ho_ten;
				$kem['gioi_tinh'] = in_array( $gioi_tinh, array( 'male', 'female' ), true ) ? $gioi_tinh : '';
				$kem['co_anh']    = ( '' !== $anh ) ? 1 : 0;
				if ( '' !== $anh ) { $kem['anh_b64'] = $anh; }
			}
			$kq = VHCC_May::dat_lenh( (string) $m['tram'], $action, $kem,
				isset( $u['name'] ) ? (string) $u['name'] : '', $can_duyet );
			if ( ! empty( $kq['ok'] ) ) { $so++; }
		}
		if ( 0 === $so ) { return 'Cơ sở này chưa gắn máy chấm công nào.'; }
		$viec = array( 'add' => 'ghi tên xuống', 'edit' => 'sửa lại thông tin trên', 'delete' => 'gỡ khỏi' );
		$cau = $can_duyet
			? 'Đã gửi lệnh ' . $viec[ $action ] . ' ' . $so . ' máy tới Admin để duyệt — '
				. 'máy CHƯA nhận được gì cho tới khi có người duyệt.'
			: 'Đã đặt lệnh ' . $viec[ $action ] . ' ' . $so . ' máy (máy nhận trong ~10 giây nếu đang online).';
		if ( 'delete' === $action ) { return $cau; }
		return $cau . ' ' . ( '' !== $anh
			? 'Ảnh thẻ đi kèm — máy tự nhận khuôn mặt, không phải gọi người ra máy chụp lại.'
			: 'CHƯA có ảnh thẻ nên khuôn mặt vẫn phải lấy trực tiếp tại máy.' );
	}

	/**
	 * Đặt lệnh ghi người này xuống đầu đọc của cơ sở — dùng lúc TẠO hồ sơ mới.
	 *
	 * 🔴 QUA ADMIN DUYỆT (`$can_duyet=true`) — đây là đường DUY NHẤT mà một Cửa hàng trưởng (vai
	 *    không có quyền `ho_so`) tự đẩy được một khuôn mặt xuống máy chấm công, qua form "Thêm
	 *    người mới vào cửa hàng" (`VHCC_NhanSu::them_nv_cua_hang()`). Trước 29/08/2026 lệnh này đi
	 *    THẲNG xuống máy — Admin không có cơ hội soi trước khi một khuôn mặt lạ được ghi vào đầu
	 *    đọc. Xem VHCC_May::duyet_lenh()/tu_choi_lenh() và khối "Chờ Admin duyệt" ở the_bang().
	 */
	private static function day_len_may( $ma_nv, $ho_ten, $coso, $gioi_tinh, $u, $anh = '' ) {
		$cau = self::lenh_may_( $ma_nv, $ho_ten, $coso, $gioi_tinh, $u, $anh, 'add', true );
		/* Câu báo lúc TẠO MỚI khác câu chung: nói rõ hồ sơ web đã xong, máy chỉ là phần thêm —
		   không có máy hay máy không nhận được cũng không phải là hồ sơ tạo hỏng. */
		if ( 'Chưa cài phần máy chấm công.' === $cau ) {
			return 'Chưa cài phần máy chấm công — hồ sơ đã tạo, chấm công online dùng được ngay.';
		}
		if ( 'Cơ sở này chưa gắn máy chấm công nào.' === $cau ) {
			return 'Cơ sở này chưa gắn máy chấm công nào — hồ sơ đã tạo, chấm công bằng điện thoại dùng được ngay.';
		}
		return $cau;
	}

	/**
	 * SỬA LẠI THÔNG TIN TRÊN MÁY — cho trường hợp gõ sai lúc tạo (tên, giới tính, ảnh thẻ).
	 *
	 * Anh Thắng 29/08/2026: *"thêm tính năng xóa, sửa trường hợp lỗi hoặc nhân viên nghỉ việc"*.
	 * Firmware đã nhận action `edit` từ trước (ISAPI UserInfo edit) — hàm này chỉ là đường GỌI
	 * nó từ web, đọc lại đúng hồ sơ hiện có (không tin dữ liệu cũ đã gửi lần trước) rồi gửi lại.
	 *
	 * ⚠️ AI ĐƯỢC SỬA THÌ ĐƯỢC GỌI LỆNH NÀY — cùng quyền với sửa hồ sơ (`co_sua_ho_so`), không
	 *    phải một quyền riêng: đây chỉ là đẩy lại đúng cái đã lưu, không phải một việc mới.
	 */
	public static function sua_lai_tren_may( $u, $ma_nv ) {
		$ma = trim( (string) $ma_nv );
		$hs = self::ho_so( $ma );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_sua_ho_so( $u ) || ! self::co_quyen_coso( $u, $hs['cua_hang'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền với hồ sơ này.' );
		}
		$anh = trim( (string) ( isset( $hs['anh_the'] ) ? $hs['anh_the'] : '' ) );
		$cau = self::lenh_may_( $ma, (string) $hs['ho_ten'], (string) $hs['cua_hang'],
			(string) ( isset( $hs['gioi_tinh'] ) ? $hs['gioi_tinh'] : '' ), $u, $anh, 'edit' );
		return array( 'ok' => true, 'thong_bao' => $cau );
	}

	/**
	 * GỠ KHỎI MÁY CHẤM CÔNG — cho trường hợp nhân viên nghỉ việc, không xoá hồ sơ/công đã chấm.
	 *
	 * Anh Thắng 29/08/2026: *"thêm tính năng xóa... trường hợp... nhân viên nghỉ việc"*. Xoá
	 * HỒ SƠ WEB không phải đường đúng khi còn công đã chấm (xem `xoa_ho_so()`) — nhưng người đã
	 * nghỉ thì vẫn cần GỠ KHỎI MÁY để không chấm công được nữa và nhường chỗ lưu khuôn mặt cho
	 * người mới (đầu đọc Hikvision có giới hạn số khuôn mặt lưu được).
	 */
	public static function xoa_khoi_may( $u, $ma_nv ) {
		$ma = trim( (string) $ma_nv );
		$hs = self::ho_so( $ma );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_sua_ho_so( $u ) || ! self::co_quyen_coso( $u, $hs['cua_hang'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền với hồ sơ này.' );
		}
		$cau = self::lenh_may_( $ma, '', (string) $hs['cua_hang'], '', $u, '', 'delete' );
		return array( 'ok' => true, 'thong_bao' => $cau );
	}

	/**
	 * ĐẨY HỒ SƠ VỪA TẠO XUỐNG MÁY — đường "Hồ sơ & tài khoản" (tab Admin), song song với
	 * `them_nv_cua_hang()` (đường Cửa hàng trưởng "Thêm nhanh") vốn đã có việc này từ trước.
	 *
	 * Anh Thắng 29/08/2026: *"hiện tại đẩy xuống chỉ có cửa hàng trưởng mới thấy, bổ sung bên tab
	 * admin cũng bổ sung được cho đợt đầu này"*. Trước bản này, `VHCC_Web::luu_ho_so()` (màn "Hồ
	 * sơ & tài khoản" trong quản trị chấm công) ghi thẳng vào bảng `nhan_vien`, không đụng gì tới
	 * máy chấm công hay mẫu đối chiếu khuôn mặt — hai lối tạo hồ sơ mới ra hai kết quả khác nhau.
	 *
	 * 🔴 KHÔNG QUA ADMIN DUYỆT — khác `day_len_may()`. Đường ấy `$can_duyet=true` vì Cửa hàng
	 *    trưởng KHÔNG có quyền `ho_so`; còn màn "Hồ sơ & tài khoản" chỉ người có quyền `ho_so`
	 *    (Kế toán trở lên — CHÍNH quyền để duyệt lệnh) mới vào tới nơi mà gọi hàm này. Bắt một
	 *    người tự duyệt lại việc mình vừa làm là thêm một cú bấm chứ không thêm một lớp soi nào.
	 *
	 * @param array  $u       người đang thao tác — dùng để chốt quyền và ghi "người đặt lệnh".
	 * @param string $ma_nv   mã hồ sơ VỪA GHI XONG vào bảng (đọc lại từ DB, không tin dữ liệu form).
	 * @param mixed  $vector  dãy đặc trưng khuôn mặt trình duyệt tính từ ảnh thẻ, hoặc rỗng/null
	 *                        nếu không có — xem `VHCC_NhanSu::them_nv_cua_hang()` cho cùng cơ chế.
	 */
	public static function day_ho_so_moi_len_may( $u, $ma_nv, $vector = null ) {
		$ma = trim( (string) $ma_nv );
		$hs = self::ho_so( $ma );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_sua_ho_so( $u ) || ! self::co_quyen_coso( $u, $hs['cua_hang'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền với hồ sơ này.' );
		}
		$anh = trim( (string) ( isset( $hs['anh_the'] ) ? $hs['anh_the'] : '' ) );
		$cau = self::lenh_may_( $ma, (string) $hs['ho_ten'], (string) $hs['cua_hang'],
			(string) ( isset( $hs['gioi_tinh'] ) ? $hs['gioi_tinh'] : '' ), $u, $anh, 'add' );

		/* Cùng cơ chế "ảnh thẻ làm mẫu" của `them_nv_cua_hang()` — xem chú thích đầy đủ ở đó. */
		if ( '' !== $anh && null !== $vector && class_exists( 'VHCC_Mat' )
			&& method_exists( 'VHCC_Mat', 'dat_mau_tu_anh_the' )
			&& VHCC_Mat::dat_mau_tu_anh_the( $ma, $vector ) ) {
			$cau .= ' Đã lấy ảnh thẻ làm mẫu đối chiếu khuôn mặt cho chấm công online.';
		}
		return array( 'ok' => true, 'thong_bao' => $cau );
	}

	/**
	 * XOÁ hồ sơ.
	 * ⚠️ CHẶN khi người đó CÒN chấm công. Xoá hồ sơ mà giữ lại chấm công là bảng lương có mã
	 *    không tra ra được tên — người thật, công thật, mà không biết trả cho ai. Muốn cho nghỉ
	 *    thì đổi "Trạng thái làm việc", đừng xoá.
	 */
	public static function xoa_ho_so( $u, $ma_nv ) {
		global $wpdb;
		/* 🔴 CHỈ ADMIN — cùng bậc với đổi Mã NV, cùng một lý do: cả hai đều làm dữ liệu cũ mất
		   chỗ bám. Trước đây bậc Quản lý. Chốt "còn chấm công thì không xoá" ở dưới VẪN GIỮ —
		   hai tầng, không thay nhau. */
		if ( ! VHCC_Vai::duoc( $u, 'xoa_ho_so' ) ) {
			return array( 'ok' => false, 'error' => 'Xoá hẳn một hồ sơ là bỏ chỗ bám của mọi dữ liệu '
				. 'cũ mang mã ấy — chỉ Admin xoá được. Cho nghỉ việc thì đổi "Trạng thái làm việc".' );
		}
		$ma = trim( (string) $ma_nv );
		$so = self::so_luot_cham( $ma );
		if ( $so > 0 ) {
			return array( 'ok' => false, 'error' => 'Người này còn ' . $so . ' lượt chấm công. '
				. 'Xoá hồ sơ là bảng lương có mã mà không tra ra tên. Muốn cho nghỉ thì đổi '
				. '"Trạng thái làm việc" thành Đã nghỉ.' );
		}
		$wpdb->delete( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma ) );
		return array( 'ok' => true );
	}

	/** Xoá nhiều hồ sơ. Từng cái đi qua ĐÚNG chốt của xoa_ho_so — không có đường tắt hàng loạt. */
	public static function xoa_nhieu_ho_so( $u, $ds_ma ) {
		$xong = array();
		$bo = array();
		foreach ( (array) $ds_ma as $ma ) {
			$r = self::xoa_ho_so( $u, $ma );
			if ( ! empty( $r['ok'] ) ) { $xong[] = trim( (string) $ma ); }
			else { $bo[] = trim( (string) $ma ) . ': ' . $r['error']; }
		}
		return array( 'ok' => true, 'xong' => $xong, 'bo' => $bo );
	}

	/**
	 * CHO NGHỈ VIỆC — đường ĐÚNG thay cho xoá hồ sơ.
	 * Giữ nguyên hồ sơ và toàn bộ chấm công; chỉ đổi trạng thái. Nhờ vậy bảng lương tháng cũ vẫn
	 * tra ra tên, mà người đó không còn hiện trong danh sách đang làm.
	 */
	public static function dat_nghi_viec( $u, $ma_nv, $ngay_nghi = '', $ly_do = '' ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		$cu = self::ho_so( $ma );
		if ( ! $cu ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_sua_ho_so( $u ) || ! self::co_quyen_coso( $u, $cu['cua_hang'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền với hồ sơ này.' );
		}
		$gc = 'Đã nghỉ';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $ngay_nghi ) ) ) {
			$gc .= ' từ ' . trim( (string) $ngay_nghi );
		}
		if ( '' !== trim( (string) $ly_do ) ) { $gc .= ' — ' . trim( (string) $ly_do ); }
		$wpdb->update( VHCC_DB::t( 'nhan_vien' ),
			array( 'trang_thai_lam_viec' => $gc, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );
		return array( 'ok' => true, 'trangThai' => $gc );
	}

	/**
	 * XEM TRƯỚC khi đổi mã NV — hàm CHỈ ĐỌC.
	 *
	 * ⚠️ Phải có bước xem trước vì đổi mã là sửa MỌI hàng chấm công đã có của người đó. Đổi rồi mới
	 *    thấy sai thì không có đường lùi: hàng cũ đã mang mã mới, không phân biệt được với hàng
	 *    vốn thuộc mã mới.
	 */
	public static function xem_truoc_doi_ma( $u, $ma_cu, $ma_moi ) {
		global $wpdb;
		$cu  = trim( (string) $ma_cu );
		$moi = trim( (string) $ma_moi );
		if ( '' === $cu || '' === $moi ) { return array( 'ok' => false, 'error' => 'Thiếu mã cũ hoặc mã mới.' ); }
		if ( strtolower( $cu ) === strtolower( $moi ) ) {
			return array( 'ok' => false, 'error' => 'Hai mã giống nhau.' );
		}
		$hs = self::ho_so( $cu );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ mã ' . $cu . '.' ); }
		if ( self::ho_so( $moi ) ) {
			return array( 'ok' => false, 'error' => 'Mã mới "' . $moi . '" ĐÃ có hồ sơ khác dùng. '
				. 'Đổi vào là gộp công hai người.' );
		}
		$so_cc = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $cu ) );
		$coso_lq = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT coso FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $cu ) );
		$so_lich = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'lich_cv' ) . ' WHERE ma_nv=%s', $cu ) );
		$so_pq = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' WHERE LOWER(ma_cc_online)=LOWER(%s)', $cu ) );
		/* Mã song song: nếu mã cũ đang được khai là chạy song song với mã khác thì đổi mã làm hỏng
		   cặp đó. Nói ra trước, đừng để phát hiện sau. */
		$ss = VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE LOWER(ma_a)=LOWER(%s) OR LOWER(ma_b)=LOWER(%s)', $cu, $cu ) );
		return array( 'ok' => true, 'maCu' => $cu, 'maMoi' => $moi, 'hoTen' => $hs['ho_ten'],
			'soHangChamCong' => $so_cc, 'coSoLienQuan' => array_values( (array) $coso_lq ),
			'soOLich' => $so_lich, 'soDongPhanQuyen' => $so_pq, 'maSongSong' => $ss,
			'canhBao' => $ss ? 'Mã này đang khai chạy song song — đổi mã sẽ làm cặp mã đó trỏ sai.' : '' );
	}

	/**
	 * ĐỔI MÃ NV. Chỉ Admin/Quản lý, và phải có quyền trên MỌI cơ sở người đó có mặt.
	 *
	 * ⚠️ Người làm nhiều cơ sở: đổi mã phải sửa cả những cơ sở kia. Cho người chỉ quản một cơ sở
	 *    làm là họ ghi được vào dữ liệu cơ sở khác.
	 * ⚠️ KHÔNG đụng máy chấm công. Bên Apps Script hàm này còn xoá/tạo lại người trên máy Hikvision
	 *    (nên nó đòi có ảnh trước khi xoá). Ở đây phần máy vẫn do Apps Script + Firebase lo, nên
	 *    hàm này CHỈ đổi dữ liệu web — và phải nói rõ, không thì người dùng tưởng máy cũng đã đổi.
	 */
	public static function doi_ma_nv( $u, $ma_cu, $ma_moi ) {
		global $wpdb;
		/* 🔴 CHỈ ADMIN. Anh Thắng 27/08/2026: *"Mã NV thì cố định chỉ có admin chỉnh được thôi"*.
		   Trước đây `co_quan_tri_nv()` (bậc Quản lý) — nay siết lên bậc Admin qua quyền riêng
		   `doi_ma_nv`. Đây là THU HẸP quyền có chủ ý: Quản lý mất một việc họ từng làm được. */
		if ( ! VHCC_Vai::duoc( $u, 'doi_ma_nv' ) ) {
			return array( 'ok' => false, 'error' => 'Mã NV là khoá của mọi hàng chấm công và mọi dòng '
				. 'lương đã có — chỉ Admin đổi được.' );
		}
		$xt = self::xem_truoc_doi_ma( $u, $ma_cu, $ma_moi );
		if ( empty( $xt['ok'] ) ) { return $xt; }
		$cu  = $xt['maCu'];
		$moi = $xt['maMoi'];
		foreach ( $xt['coSoLienQuan'] as $cs ) {
			if ( ! self::co_quyen_coso( $u, $cs ) ) {
				return array( 'ok' => false, 'error' => 'Người này còn có mặt ở ' . $cs
					. ' — đổi mã phải sửa cả nơi đó, nên chỉ Admin làm được.' );
			}
		}
		foreach ( array( 'nhan_vien' => 'ma_nv', 'cham_cong' => 'ma_nv', 'lich_cv' => 'ma_nv',
			'doi_lich_cv' => 'ma_nv', 'cham_cong_nhiem_vu' => 'ma_nv', 'tang_cuong' => 'ma_nv',
			'ghi_chu' => 'ma_nv' ) as $bang => $cot ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . VHCC_DB::t( $bang ) . " SET $cot=%s WHERE $cot=%s", $moi, $cu ) );
		}
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'phan_quyen' ) . ' SET ma_cc_online=%s WHERE LOWER(ma_cc_online)=LOWER(%s)',
			$moi, $cu ) );
		return array( 'ok' => true, 'maCu' => $cu, 'maMoi' => $moi,
			'daSua' => $xt['soHangChamCong'] . ' hàng chấm công, ' . $xt['soOLich'] . ' ô lịch',
			'canhBao' => 'Chỉ đổi dữ liệu trên web. Người trên MÁY chấm công vẫn mang mã cũ — '
				. 'xoá/tạo lại trên máy làm ở màn "Máy & Firmware".' );
	}

	/**
	 * XEM TRƯỚC nhập nhân sự hàng loạt — CHỈ ĐỌC, không ghi một dòng nào.
	 * Trả từng dòng kèm việc sẽ làm (thêm / cập nhật / bỏ) và lý do bỏ.
	 */
	public static function xem_truoc_nhap( $u, $ds ) {
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Nhập nhân sự toàn chuỗi — ' . self::LOI_QT );
		}
		$ra = array();
		$thay = array();
		foreach ( (array) $ds as $i => $d ) {
			$d = (array) $d;
			$ma = trim( isset( $d['ma_nv'] ) ? (string) $d['ma_nv'] : '' );
			$dong = array( 'dong' => (int) $i + 1, 'maNV' => $ma,
				'hoTen' => trim( isset( $d['ho_ten'] ) ? (string) $d['ho_ten'] : '' ),
				'cuaHang' => self::chuan_coso( isset( $d['cua_hang'] ) ? $d['cua_hang'] : '' ) );
			if ( '' === $ma ) { $dong['viec'] = 'bỏ'; $dong['vaoSao'] = 'thiếu Mã NV'; $ra[] = $dong; continue; }
			/* Trùng mã TRONG CHÍNH tệp nhập: hai dòng cùng mã là một cái ghi đè cái kia mà không
			   ai thấy. Bắt ở bước xem trước, đừng để chạy xong mới biết mất một dòng. */
			if ( isset( $thay[ strtolower( $ma ) ] ) ) {
				$dong['viec'] = 'bỏ';
				$dong['vaoSao'] = 'trùng mã với dòng ' . $thay[ strtolower( $ma ) ] . ' trong cùng tệp';
				$ra[] = $dong; continue;
			}
			$thay[ strtolower( $ma ) ] = (int) $i + 1;
			$dong['viec'] = self::ho_so( $ma ) ? 'cập nhật' : 'thêm';
			$ra[] = $dong;
		}
		$dem = array( 'them' => 0, 'capNhat' => 0, 'bo' => 0 );
		foreach ( $ra as $x ) {
			if ( 'thêm' === $x['viec'] ) { $dem['them']++; }
			elseif ( 'cập nhật' === $x['viec'] ) { $dem['capNhat']++; }
			else { $dem['bo']++; }
		}
		return array( 'ok' => true, 'dong' => $ra, 'dem' => $dem );
	}

	/**
	 * NHẬP NHÂN SỰ HÀNG LOẠT. Từng dòng đi qua ĐÚNG `luu_ho_so` — không có đường ghi tắt.
	 * ⚠️ Đòi chạy `xem_truoc_nhap` sạch trước: `$xac_nhan` phải là số dòng mà bước xem trước đếm
	 *    được. Lệch số nghĩa là tệp đã đổi giữa hai bước, và lúc đó người bấm không biết mình đang
	 *    nhập cái gì.
	 */
	public static function nhap_hang_loat( $u, $ds, $xac_nhan = null ) {
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Nhập nhân sự toàn chuỗi — ' . self::LOI_QT );
		}
		$xt = self::xem_truoc_nhap( $u, $ds );
		if ( empty( $xt['ok'] ) ) { return $xt; }
		$se_ghi = $xt['dem']['them'] + $xt['dem']['capNhat'];
		if ( null !== $xac_nhan && (int) $xac_nhan !== $se_ghi ) {
			return array( 'ok' => false, 'error' => 'Số dòng sẽ ghi (' . $se_ghi . ') khác số đã xem '
				. 'trước (' . (int) $xac_nhan . ') — tệp đã đổi giữa hai bước. Xem lại rồi nhập.' );
		}
		$xong = array();
		$bo = array();
		$thay = array();
		foreach ( (array) $ds as $i => $d ) {
			$d = (array) $d;
			$ma = trim( isset( $d['ma_nv'] ) ? (string) $d['ma_nv'] : '' );
			if ( '' === $ma || isset( $thay[ strtolower( $ma ) ] ) ) {
				$bo[] = 'dòng ' . ( (int) $i + 1 ) . ( '' === $ma ? ': thiếu Mã NV' : ': trùng mã trong tệp' );
				continue;
			}
			$thay[ strtolower( $ma ) ] = 1;
			$r = self::luu_ho_so( $u, $d );
			if ( ! empty( $r['ok'] ) ) { $xong[] = $ma; }
			else { $bo[] = 'dòng ' . ( (int) $i + 1 ) . ' (' . $ma . '): ' . $r['error']; }
		}
		return array( 'ok' => true, 'xong' => $xong, 'bo' => $bo );
	}

	/** Bộ phận + nhóm lương của MỌI cơ sở — một bảng cho màn quản trị. */
	public static function bo_phan_va_coso() {
		$out = array();
		foreach ( self::ds_coso() as $cs ) {
			$nh = VHCC_Luong::nhom_coso( $cs );
			$out[] = array( 'coSo' => $cs, 'boPhan' => VHCC_Luong::bo_phan_cua( $cs ),
				'nhom' => $nh ? $nh['ten'] : '', 'theoGio' => VHCC_Luong::coso_tinh_theo_gio( $cs ),
				'laVanPhong' => VHCC_Luong::la_van_phong( $cs ) );
		}
		return $out;
	}

	/**
	 * Nhiệm vụ của một người TẠI một cơ sở, cho một ngày.
	 * ⚠️ Nhiệm vụ chỉ có nghĩa ở Nhóm Máy Tự Động — cơ sở khác thì từ chối, đừng ghi một giá trị
	 *    không ảnh hưởng gì rồi để người ta tưởng đã khai xong.
	 */
	public static function dat_nhiem_vu( $u, $ngay, $coso, $ma_nv, $nhiem_vu ) {
		global $wpdb;
		$coso = self::chuan_coso( $coso );
		/* Hỏi `lich_lam`, không hỏi quyền HỒ SƠ: khai nhiệm vụ NGÀY là việc vận hành hằng ngày
		   của cửa hàng — cùng loại với xếp lịch — chứ không phải sửa hồ sơ nhân sự. */
		if ( ! VHCC_Vai::duoc( $u, 'lich_lam' ) || ! self::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		if ( ! VHCC_Luong::la_may_tu_dong( $coso ) ) {
			return array( 'ok' => false, 'error' => 'Nhiệm vụ chỉ có nghĩa ở Nhóm Máy Tự Động. '
				. 'Cơ sở "' . $coso . '" không thuộc nhóm đó nên khai vào cũng không đổi cách tính công.' );
		}
		$ngay = trim( (string) $ngay );
		$ma = trim( (string) $ma_nv );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Thiếu ngày hoặc mã NV.' );
		}
		$nv = trim( (string) $nhiem_vu );
		$bang = VHCC_DB::t( 'cham_cong_nhiem_vu' );
		$cu = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $bang WHERE ngay=%s AND LOWER(coso)=LOWER(%s) AND ma_nv=%s", $ngay, $coso, $ma ) );
		$ghi = array( 'ngay' => $ngay, 'coso' => $coso, 'ma_nv' => $ma, 'nhiem_vu' => $nv,
			'ghi_luc' => current_time( 'mysql' ),
			'nguoi_ghi' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		// Ghi ĐÈ, không thêm dòng thứ hai cho cùng (ngày, cơ sở, mã).
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true, 'nhiemVu' => $nv );
	}

	/** Mã đã CHẤM CÔNG mà chưa có hồ sơ — người thật, công thật, mà bảng lương không tra ra tên. */
	public static function ds_chua_co_ho_so( $u ) {
		global $wpdb;
		$out = array();
		foreach ( VHCC_DB::rows(
			'SELECT c.coso, c.ma_nv, MAX(c.ho_ten) ho_ten, COUNT(*) so, MAX(c.ngay) ngay_cuoi FROM '
			. VHCC_DB::t( 'cham_cong' ) . ' c LEFT JOIN ' . VHCC_DB::t( 'nhan_vien' ) . ' n'
			. ' ON n.ma_nv = c.ma_nv WHERE n.id IS NULL GROUP BY c.coso, c.ma_nv ORDER BY so DESC' ) as $r ) {
			if ( ! self::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	public static function ds_ma_song_song() {
		return VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'ma_song_song' ) . ' ORDER BY id DESC' );
	}

	public static function bo_ma_song_song( $u, $ma_a, $ma_b ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Mã song song ảnh hưởng cả chuỗi — ' . self::LOI_QT );
		}
		$so = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))'
			. ' OR (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))',
			$ma_a, $ma_b, $ma_b, $ma_a ) );
		return ( 0 === (int) $so )
			? array( 'ok' => false, 'error' => 'Không thấy cặp mã này.' )
			: array( 'ok' => true );
	}

	/** Xếp bộ phận cho một cơ sở. Bộ phận quyết định công thức lương -> chỉ Admin/Quản lý. */
	public static function xep_bo_phan( $u, $coso, $bo_phan, $theo_gio = null ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false,
				'error' => 'Bộ phận quyết định CÔNG THỨC LƯƠNG của cả cơ sở — ' . self::LOI_QT );
		}
		$coso = self::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$bp = trim( (string) $bo_phan );
		/* Chỉ nhận đúng danh sách. Bộ phận lạ chuẩn hoá thành 'Chưa xếp' = KHÔNG có công thức
		   lương — đó là hành vi bản gốc, và nó đúng: thà không tính còn hơn tính bằng công thức
		   của bộ phận khác. Nhưng ở ĐÂY thì phải nói ra, đừng lặng lẽ đổi thành Chưa xếp. */
		if ( '' !== $bp && ! in_array( $bp, VHCC_Luong::BP_DS, true ) ) {
			return array( 'ok' => false, 'error' => 'Bộ phận "' . $bp . '" không có trong danh sách ('
				. implode( ' · ', VHCC_Luong::BP_DS ) . '). Khai tên khác là cơ sở đó thành "Chưa xếp" '
				. 'và KHÔNG được tính lương.' );
		}
		$cu = $wpdb->get_row( $wpdb->prepare(
			'SELECT id FROM ' . VHCC_DB::t( 'bo_phan_coso' ) . ' WHERE LOWER(coso)=LOWER(%s) LIMIT 1',
			$coso ), ARRAY_A );
		$ghi = array( 'coso' => $coso, 'bo_phan' => $bp );
		if ( null !== $theo_gio ) { $ghi['theo_gio'] = $theo_gio ? 1 : 0; }
		if ( $cu ) { $wpdb->update( VHCC_DB::t( 'bo_phan_coso' ), $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), $ghi ); }
		return array( 'ok' => true );
	}

	/**
	 * DÒ SẴN CÁC CẶP MÃ ĐÁNG NGỜ LÀ MỘT NGƯỜI — theo họ tên.
	 *
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"cách dò tên nhân viên trùng để ghép mã được không: theo họ tên
	 * nhân viên"*. Đúng — 400 nhân sự mà gõ tay từng cặp thì không ai làm nổi.
	 * =========================================================================================
	 *
	 * 🔴 GỢI Ý THÌ ĐƯỢC, TỰ GHÉP THÌ KHÔNG. Tên người Việt trùng rất nhiều; đoán sai là gộp
	 *    lương hai người khác nhau — và gộp xong thì `don_ma()` không đảo lại được. Hàm này chỉ
	 *    dọn sẵn danh sách; người vẫn là bên bấm nút.
	 *
	 * CÁCH DÒ, DỰA ĐÚNG VÀO HÌNH DẠNG CỦA CHỖ HỎNG:
	 *   • Mã MÁY  — có lượt chấm công, KHÔNG có hồ sơ nhân sự (máy mang một dãy số của riêng nó)
	 *   • Mã CÔNG TY — có hồ sơ, thường chưa có lượt nào
	 * Nên đi từ mã lạ trong bảng chấm công, lấy tên mà máy gửi kèm, rồi tìm hồ sơ trùng tên.
	 *
	 * ⚠️ TÊN KHỚP NHIỀU HỒ SƠ THÌ KHÔNG GỢI Ý — nói ra là "khớp N hồ sơ" rồi thôi. Chọn đại một
	 *    cái là đúng kiểu sai mà bình luận ở `khai_ma_song_song()` đã dặn phải tránh.
	 *
	 * ⚠️ BỎ QUA CẶP ĐÃ KHAI. Bày lại một việc vừa làm xong là người ta bấm hai lần rồi ngờ chính
	 *    mình.
	 *
	 * @return array [ ['maMay','maCty','ten','coso','soLuot','soHoSoKhop'], … ]
	 */
	public static function goi_y_ghep_ma( $u, $toi_da = 200 ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) { return array(); }
		$t_cc = VHCC_DB::t( 'cham_cong' );
		$t_nv = VHCC_DB::t( 'nhan_vien' );

		/* Mã có lượt chấm công mà KHÔNG có hồ sơ — gần như chắc là mã của máy. */
		$la = VHCC_DB::rows( "SELECT c.ma_nv, COUNT(*) AS so, MAX(c.ho_ten) AS ho_ten,"
			. " MAX(c.coso) AS coso FROM $t_cc c"
			. " LEFT JOIN $t_nv n ON n.ma_nv = c.ma_nv"
			. " WHERE n.ma_nv IS NULL AND TRIM(c.ma_nv) <> ''"
			. ' GROUP BY c.ma_nv ORDER BY so DESC LIMIT ' . max( 1, (int) $toi_da ) );
		if ( ! $la ) { return array(); }

		/* Sổ nhân sự gom theo khoá tên — một khoá có thể ứng với nhiều hồ sơ, phải giữ cả cụm. */
		$theo_ten = array();
		foreach ( (array) VHCC_DB::rows( "SELECT ma_nv, ho_ten, cua_hang FROM $t_nv"
			. " WHERE TRIM(ma_nv) <> ''" ) as $r ) {
			$k = self::khoa_so( (string) $r['ho_ten'] );
			if ( '' === $k ) { continue; }
			$theo_ten[ $k ][] = $r;
		}

		$da_khai = array();
		foreach ( (array) self::ds_ma_song_song() as $r ) {
			$da_khai[ strtoupper( trim( (string) $r['ma_a'] ) ) . '|' . strtoupper( trim( (string) $r['ma_b'] ) ) ] = 1;
			$da_khai[ strtoupper( trim( (string) $r['ma_b'] ) ) . '|' . strtoupper( trim( (string) $r['ma_a'] ) ) ] = 1;
		}

		$ra = array();
		foreach ( $la as $x ) {
			$ten = trim( (string) $x['ho_ten'] );
			$k   = self::khoa_so( $ten );
			if ( '' === $k || ! isset( $theo_ten[ $k ] ) ) { continue; }
			$khop = $theo_ten[ $k ];
			$mot  = ( 1 === count( $khop ) ) ? $khop[0] : null;
			$ma_cty = $mot ? trim( (string) $mot['ma_nv'] ) : '';
			if ( '' !== $ma_cty && isset( $da_khai[ strtoupper( $ma_cty ) . '|' . strtoupper( (string) $x['ma_nv'] ) ] ) ) {
				continue;
			}
			$ra[] = array(
				'maMay'      => (string) $x['ma_nv'],
				'maCty'      => $ma_cty,
				'ten'        => $ten,
				'tenHoSo'    => $mot ? trim( (string) $mot['ho_ten'] ) : '',
				'coso'       => (string) $x['coso'],
				'cosoHoSo'   => $mot ? trim( (string) $mot['cua_hang'] ) : '',
				'soLuot'     => (int) $x['so'],
				'soHoSoKhop' => count( $khop ),
			);
		}
		return $ra;
	}

	/**
	 * ĐẾM XEM DỒN MÃ PHỤ VỀ MÃ CHÍNH THÌ ĐỘNG VÀO BAO NHIÊU HÀNG.
	 *
	 * Trả [ 'chuyen' => số hàng chỉ việc đổi mã, 'gop' => số hàng phải gộp vì trùng ngày ].
	 */
	public static function dem_don_ma( $ma_chinh, $ma_phu ) {
		global $wpdb;
		$t = VHCC_DB::t( 'cham_cong' );
		$a = trim( (string) $ma_chinh );
		$b = trim( (string) $ma_phu );
		if ( '' === $a || '' === $b || 0 === strcasecmp( $a, $b ) ) {
			return array( 'chuyen' => 0, 'gop' => 0 );
		}
		$gop = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $t p JOIN $t c ON p.coso=c.coso AND p.ngay=c.ngay"
			. ' AND p.hau_to=c.hau_to WHERE p.ma_nv=%s AND c.ma_nv=%s', $b, $a ) );
		$tong = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $t WHERE ma_nv=%s", $b ) );
		return array( 'chuyen' => max( 0, $tong - $gop ), 'gop' => $gop );
	}

	/**
	 * DỒN LƯỢT CHẤM CÔNG CŨ CỦA MÃ PHỤ VỀ MÃ CHÍNH.
	 *
	 * =========================================================================================
	 * 🔴 KHAI CẶP MÃ CHỈ CHỮA TỪ NAY VỀ SAU. DỮ LIỆU CŨ VẪN TÁCH ĐÔI.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026, ảnh lưới cả tháng với mỗi người hiện thành HAI hàng — một hàng có
	 * giờ, một hàng "chưa chấm": *"Trên máy chấm công là 1 mã, trên web là 1 mã (trên máy thì
	 * không sửa được), trên web thì mã chuẩn công ty, nên cần đồng bộ 2 mã chạy song song được
	 * không, chứ nó đang nhân 2 nhân viên ra"*.
	 *
	 * Cơ chế đồng bộ đã có: khai cặp ở `ma_song_song`, rồi `ma_that()` dịch mã máy về mã chính.
	 * Nhưng `ma_that()` CHỈ được gọi ở `VHCC_Nhan::mot_luot()` — tức là lúc GHI một lượt mới.
	 * Hàng đã nằm trong bảng từ trước thì không ai đụng tới, nên vẫn mang mã máy, và lưới vẫn
	 * vẽ ra hai người.
	 *
	 * ⚠️ VIỆC NÀY KHÔNG ĐẢO ĐƯỢC. Bỏ ghép sau đó cũng không tách lại được — hàng đã mang mã
	 *    chính rồi thì không còn dấu vết nó từng thuộc mã nào. Vì thế nó là một NÚT RIÊNG, đứng
	 *    sau việc khai cặp, chứ không chạy kèm: khai cặp là việc nhẹ và bỏ được, dồn thì không.
	 *
	 * 🔴 TRÙNG NGÀY THÌ GỘP, KHÔNG BỎ. Bảng có `UNIQUE KEY o (coso,ngay,ma_nv,hau_to)` nên đổi
	 *    mã thẳng sẽ đụng khoá ở những ngày cả hai mã cùng có giờ. Bỏ hàng phụ đi là mất giờ
	 *    thật. Hai mã là MỘT người, một ngày họ chỉ có một ca — nên gộp: giờ vào lấy SỚM NHẤT,
	 *    giờ ra lấy MUỘN NHẤT. Đó là khoảng người ấy thật sự có mặt.
	 */
	public static function don_ma( $u, $ma_chinh, $ma_phu ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Dồn lượt chấm công ảnh hưởng cả chuỗi — ' . self::LOI_QT );
		}
		$a = trim( (string) $ma_chinh );
		$b = trim( (string) $ma_phu );
		if ( '' === $a || '' === $b ) { return array( 'ok' => false, 'error' => 'Thiếu một trong hai mã.' ); }
		if ( 0 === strcasecmp( $a, $b ) ) {
			return array( 'ok' => false, 'error' => 'Hai mã giống nhau.' );
		}
		/* ⚠️ CHỈ DỒN CẶP ĐÃ KHAI. Cho dồn hai mã bất kỳ là mở đường ném công của người này sang
		   người khác chỉ bằng hai ô gõ tay. */
		$da = $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))'
			. ' OR (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s)) LIMIT 1', $a, $b, $b, $a ) );
		if ( ! $da ) {
			return array( 'ok' => false, 'error' => 'Cặp mã này chưa khai — khai ghép trước rồi mới dồn.' );
		}

		$t   = VHCC_DB::t( 'cham_cong' );
		$phu = VHCC_DB::rows( $wpdb->prepare( "SELECT * FROM $t WHERE ma_nv=%s", $b ) );
		$chuyen = 0;
		$gop    = 0;
		foreach ( (array) $phu as $r ) {
			$cu = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $t WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s LIMIT 1",
				$r['coso'], $r['ngay'], $a, $r['hau_to'] ), ARRAY_A );
			if ( ! $cu ) {
				$wpdb->update( $t, array( 'ma_nv' => $a ), array( 'id' => (int) $r['id'] ) );
				$chuyen++;
				continue;
			}
			/* Gộp: vào SỚM NHẤT, ra MUỘN NHẤT. `null` nghĩa là ô trống — bên nào có thì lấy. */
			$vao = self::nho_hon( $cu['gio_vao_giay'], $r['gio_vao_giay'] );
			$ra  = self::lon_hon( $cu['gio_ra_giay'],  $r['gio_ra_giay'] );
			$ng  = ( (string) $cu['nguon'] !== (string) $r['nguon'] && '' !== trim( (string) $r['nguon'] ) )
				? 'hon-hop' : $cu['nguon'];
			$wpdb->update( $t, array(
				'gio_vao_giay' => $vao, 'gio_ra_giay' => $ra, 'nguon' => $ng,
				'chuan' => trim( VHCC_DB::hhmm( $vao ) . ' ' . VHCC_DB::hhmm( $ra ) ),
			), array( 'id' => (int) $cu['id'] ) );
			$wpdb->delete( $t, array( 'id' => (int) $r['id'] ) );
			$gop++;
		}
		return array( 'ok' => true, 'chuyen' => $chuyen, 'gop' => $gop );
	}

	/** Nhỏ hơn, bỏ qua null. Cả hai null -> null. */
	private static function nho_hon( $x, $y ) {
		if ( null === $x || '' === $x ) { return ( '' === $y ) ? null : $y; }
		if ( null === $y || '' === $y ) { return $x; }
		return min( (int) $x, (int) $y );
	}

	/** Lớn hơn, bỏ qua null. */
	private static function lon_hon( $x, $y ) {
		if ( null === $x || '' === $x ) { return ( '' === $y ) ? null : $y; }
		if ( null === $y || '' === $y ) { return $x; }
		return max( (int) $x, (int) $y );
	}

	/**
	 * Khai MÃ SONG SONG (một người, hai mã: máy cũ chưa nhận lệnh đổi mã).
	 * ⚠️ PHẢI KHAI, hệ thống KHÔNG được tự suy "hai mã này chắc là một người" từ tên — tên người
	 *    Việt trùng rất nhiều, đoán sai là gộp lương hai người khác nhau.
	 */
	public static function khai_ma_song_song( $u, $ma_a, $ma_b, $ho_ten, $ly_do ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Mã song song ảnh hưởng cả chuỗi — ' . self::LOI_QT );
		}
		$a = trim( (string) $ma_a );
		$b = trim( (string) $ma_b );
		if ( '' === $a || '' === $b ) { return array( 'ok' => false, 'error' => 'Thiếu một trong hai mã.' ); }
		if ( strtolower( $a ) === strtolower( $b ) ) {
			return array( 'ok' => false, 'error' => 'Hai mã giống nhau — không phải mã song song.' );
		}
		$da = $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))'
			. ' OR (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s)) LIMIT 1', $a, $b, $b, $a ) );
		if ( $da ) { return array( 'ok' => false, 'error' => 'Cặp mã này đã khai rồi.' ); }
		$wpdb->insert( VHCC_DB::t( 'ma_song_song' ), array(
			'ma_a' => $a, 'ma_b' => $b, 'ho_ten' => trim( (string) $ho_ten ),
			'ly_do' => trim( (string) $ly_do ),
			'nguoi_khai' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'tao_luc' => current_time( 'mysql' ) ) );
		return array( 'ok' => true );
	}
}
