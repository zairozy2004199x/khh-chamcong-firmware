<?php
/**
 * ĐỌC DOANH THU TỪ PLUGIN "DOANH THU FABi" (khmatrix.com/doanh-thu-hcm).
 *
 * Anh Thắng 18/09/2026: *"Anh muốn lấy đẩy doanh thu từ doanh thu hcm sang báo cáo tổng"*.
 *
 * =================================================================================================
 * 🔴 ĐỌC THẲNG BẢNG, KHÔNG GỌI QUA HTTP.
 *
 * Hai plugin nằm trên CÙNG một WordPress, cùng một cơ sở dữ liệu. Gọi vòng qua REST của chính
 * mình thì phải mang theo phiên đăng nhập của người đang ngồi trước máy (PIN của Báo cáo chi phí
 * KHÔNG phải tài khoản WordPress, cũng không phải PIN của Doanh thu FABi), tức là hoặc lưu thêm
 * một khoá ở đâu đó, hoặc lượt gọi hỏng vì 401. Đọc thẳng bảng thì không có khoá nào để lộ.
 *
 * ⚠️ CHỈ ĐỌC. Lớp này KHÔNG bao giờ ghi vào bảng của Doanh thu FABi — số liệu ở đó là của họ, và
 *    một lượt ghi nhầm từ đây thì bên ấy không có cách nào biết ai đã sửa.
 *
 * =================================================================================================
 * ⚠️ PLUGIN KIA CÓ THỂ KHÔNG CÀI. Site nào không có Doanh thu FABi thì bảng không tồn tại —
 *    phải nói ra thành câu tiếng Việt, đừng để câu truy vấn nổ thành lỗi 500 trắng màn hình.
 *
 * Bảng `{prefix}khh_dt_ngay`: mỗi dòng là MỘT NGÀY của MỘT CỬA HÀNG.
 *    ngay (date) · cua_hang (varchar 190) · thanh_tien (double) — cộng theo kỳ là ra doanh thu.
 *
 * ⚠️ Lấy `thanh_tien` chứ KHÔNG lấy `doanh_thu`: bảng có cả hai, `doanh_thu` là số TRƯỚC chiết
 *    khấu. Trang Doanh thu FABi bày `thanh_tien`, nên lấy cột khác là hai màn hình nói hai số
 *    khác nhau cho cùng một cơ sở, mà chẳng bên nào sai rõ ràng để ai đó đi tìm.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_FABi {

	/** Tên bảng của plugin Doanh thu FABi. */
	public static function bang() {
		global $wpdb;
		return $wpdb->prefix . 'khh_dt_ngay';
	}

	/**
	 * Bảng có thật không — plugin kia có thể chưa cài trên site này.
	 *
	 * ⚠️ HAI LỐI HỎI, CỐ Ý. `SHOW TABLES` là cú pháp của MySQL (máy chủ thật). Bộ giả lập để phát
	 *    triển chạy trên SQLite, ở đó câu ấy trả về rỗng dù bảng có thật — chỉ dựa vào nó thì mọi
	 *    phép thử tại chỗ đều báo "chưa cài plugin", và không ai kiểm được đường này trước khi
	 *    đẩy lên máy chủ. Nên hỏi thêm một câu đếm; đếm chạy được nghĩa là bảng có.
	 */
	public static function co_bang() {
		global $wpdb;
		$b = self::bang();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $b ) ) ) { return true; }
		$dem = $wpdb->get_var( "SELECT COUNT(*) FROM $b" );
		return null !== $dem;
	}

	/**
	 * Cộng doanh thu theo cửa hàng trong một khoảng ngày.
	 *
	 * @param string $tu  'YYYY-MM-DD'
	 * @param string $den 'YYYY-MM-DD'
	 * @return array {ok, ds:[{cua_hang, thanh_tien, so_ngay}], tu, den, tong}
	 */
	public static function theo_cua_hang( $tu, $den ) {
		global $wpdb;
		$tu  = self::ngay( $tu );
		$den = self::ngay( $den );
		if ( ! $tu || ! $den ) {
			return array( 'ok' => false, 'error' => 'Khoảng ngày không hợp lệ.' );
		}
		if ( ! self::co_bang() ) {
			return array( 'ok' => false, 'error' => 'Site này chưa cài plugin "Doanh thu FABi" (không thấy bảng ' . self::bang() . ').' );
		}
		$b = self::bang();
		/* Gộp ngay trong câu truy vấn: một kỳ có thể là 31 ngày × vài chục cửa hàng, kéo hết từng
		   dòng về PHP rồi mới cộng là chuyện không cần làm. */
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cua_hang, SUM(thanh_tien) AS tien, COUNT(*) AS so_ngay
				   FROM $b WHERE ngay >= %s AND ngay <= %s
				  GROUP BY cua_hang ORDER BY cua_hang ASC",
				$tu,
				$den
			),
			ARRAY_A
		);
		$ds = array();
		$tong = 0.0;
		foreach ( (array) $rows as $r ) {
			$t = (float) $r['tien'];
			$tong += $t;
			$ds[] = array(
				'cua_hang'   => (string) $r['cua_hang'],
				'thanh_tien' => $t,
				'so_ngay'    => (int) $r['so_ngay'],
			);
		}
		return array( 'ok' => true, 'ds' => $ds, 'tu' => $tu, 'den' => $den,
			'tong' => $tong, 'so_cua_hang' => count( $ds ) );
	}

	/**
	 * Doanh thu của MỘT KỲ (tháng/năm) — bọc lại theo_cua_hang() cho khỏi ai phải tự tính ngày
	 * cuối tháng ở phía giao diện (tháng 2 năm nhuận là chỗ dễ sai nhất).
	 */
	public static function theo_ky( $thang, $nam ) {
		$m = max( 1, min( 12, (int) $thang ) );
		$y = (int) $nam;
		if ( $y < 2000 || $y > 2100 ) {
			return array( 'ok' => false, 'error' => 'Năm không hợp lệ.' );
		}
		$cuoi = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
		return self::theo_cua_hang(
			sprintf( '%04d-%02d-01', $y, $m ),
			sprintf( '%04d-%02d-%02d', $y, $m, $cuoi )
		);
	}

	private static function ngay( $s ) {
		$s = trim( (string) $s );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}
}
