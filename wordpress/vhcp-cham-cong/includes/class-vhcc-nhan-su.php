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

	public static function chuan_coso( $s ) {
		return trim( preg_replace( '/^CS_/', '', (string) $s ) );
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
		if ( '' !== $coso ) { $dk[] = 'LOWER(cua_hang)=LOWER(%s)'; $tv[] = $coso; }
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
			// Cửa hàng trưởng chỉ thấy người của cửa hàng mình.
			if ( ! self::co_quyen_coso( $u, $r['cua_hang'] ) ) { continue; }
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
			$khoa[] = array( 'ma' => $ma, 'k_ten' => $k_ten, 'k_ma' => $k_ma );
			if ( '' !== $k_ten ) { $dem_ten[ $k_ten ] = ( isset( $dem_ten[ $k_ten ] ) ? $dem_ten[ $k_ten ] : 0 ) + 1; }
			if ( '' !== $k_ma )  { $dem_ma[ $k_ma ]   = ( isset( $dem_ma[ $k_ma ] ) ? $dem_ma[ $k_ma ] : 0 ) + 1; }
		}

		$ra = array();
		foreach ( $khoa as $x ) {
			$t = ( '' !== $x['k_ten'] && $dem_ten[ $x['k_ten'] ] > 1 );
			$m = ( '' !== $x['k_ma'] && $dem_ma[ $x['k_ma'] ] > 1 );
			if ( $t || $m ) { $ra[ $x['ma'] ] = array( 'ten' => $t, 'ma' => $m ); }
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
	 * @param string $vai    Tên vai, phải nằm trong `VHCC_Auth::VAI_TRO_TAT_CA`.
	 */
	public static function dat_vai_tro( $u, $ma_nv, $vai ) {
		global $wpdb;
		if ( ! self::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Đổi vai trò cần vai Kế toán trở lên.' );
		}
		$ma  = trim( (string) $ma_nv );
		$vai = trim( (string) $vai );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		/* Danh sách TRẮNG, đọc từ `VHCC_Auth` — nơi duy nhất khai tên vai của cả hệ. Nhận bừa
		   một chuỗi lạ thì `VHCC_Vai::ma()` đẩy nó về Nhân viên, tức là ô vai trò thành một
		   đường HẠ quyền người khác mà không ai gọi tên được việc vừa xảy ra. */
		if ( ! in_array( $vai, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) {
			return array( 'ok' => false, 'error' => 'Vai trò "' . $vai . '" không có trong hệ.' );
		}
		$cu = self::ho_so( $ma );
		if ( ! $cu ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
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

		if ( trim( (string) $cu['vai_tro'] ) === $vai ) { return array( 'ok' => true, 'doi' => false ); }
		$ok = $wpdb->update( VHCC_DB::t( 'nhan_vien' ),
			array( 'vai_tro' => $vai, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );
		return ( false === $ok )
			? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
			: array( 'ok' => true, 'doi' => true );
	}

	public static function ds_coso() {
		global $wpdb;
		$ds = array();
		foreach ( array( 'may' => 'cua_hang', 'cham_cong' => 'coso', 'nhan_vien' => 'cua_hang',
			'bo_phan_coso' => 'coso' ) as $bang => $cot ) {
			foreach ( (array) $wpdb->get_col( "SELECT DISTINCT $cot FROM " . VHCC_DB::t( $bang ) ) as $x ) {
				$x = self::chuan_coso( $x );
				if ( '' !== $x && ! in_array( $x, $ds, true ) ) { $ds[] = $x; }
			}
		}
		sort( $ds );
		return $ds;
	}

	// ======================================================================= ghi

	/**
	 * Lưu hồ sơ. Trả array('ok'=>bool, 'error'=>..., 'tao_moi'=>bool).
	 *
	 * ⚠️ Bốn chốt, mỗi chốt một lý do khác nhau — bỏ chốt nào cũng mất một thứ khác nhau.
	 */
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
			// Chốt 4: cửa hàng trưởng chỉ sửa người ĐANG ở cửa hàng mình.
			if ( ! self::co_quyen_coso( $u, $coso_cu ) ) {
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
		$so = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $ma ) );
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
