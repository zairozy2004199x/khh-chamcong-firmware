<?php
/**
 * ĐỌC DOANH THU POSH / JP TỪ PLUGIN "GHẾ MASSAGE" (khmatrix.com/ghe).
 *
 * Anh Thắng 18/09/2026: *"Giờ anh đẩy doanh thu Posh qua realtime"* — cùng lối liên kết sống đã
 * làm cho Doanh thu FABi, nhưng nguồn là hệ ghế massage.
 *
 * =================================================================================================
 * NGUỒN: hai bảng của plugin Ghế, ghép lại đúng như màn "Báo cáo tổng" của chính nó
 * (VHG_KeToan::bao_cao_tong):
 *
 *     {prefix}vhg_bc_dong  d   — mỗi dòng một GHẾ trong một lượt chốt: ngay, tong, qr, tien_mat
 *     {prefix}vhg_bc       h   — đầu báo cáo, giữ tên CƠ SỞ
 *
 * ⚠️ ĐIỀU KIỆN LỌC PHẢI GIỐNG HỆT bên ấy: `chi_so_sau IS NOT NULL OR tong<>0 OR actual<>0`.
 *    Bỏ điều kiện này thì những dòng nháp/chưa chốt cũng lọt vào, và con số bên báo cáo chi phí
 *    lệch khỏi con số trên màn Báo cáo tổng mà kế toán vẫn nhìn hằng ngày — hai màn nói hai số
 *    cho cùng một cơ sở là chuyện không ai đi tìm ra nổi.
 *
 * ⚠️ Lấy cột `tong`, tức CẢ tiền mặt lẫn QR. Doanh thu của cơ sở là cả hai; lấy mỗi `qr` hay mỗi
 *    `tien_mat` thì tỷ trọng phân bổ chi phí sai theo một cách rất khó thấy.
 *
 * ⚠️ CHỈ ĐỌC. Không bao giờ ghi sang bảng của plugin Ghế.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_Ghe {

	public static function bang_dong() {
		global $wpdb;
		return $wpdb->prefix . 'vhg_bc_dong';
	}
	public static function bang_dau() {
		global $wpdb;
		return $wpdb->prefix . 'vhg_bc';
	}

	/** Plugin Ghế có trên site này không. Hai lối hỏi — xem KHBC_FABi::co_bang(). */
	public static function co_bang() {
		global $wpdb;
		$d = self::bang_dong();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $d ) ) ) { return true; }
		return null !== $wpdb->get_var( "SELECT COUNT(*) FROM $d" );
	}

	/**
	 * Cộng doanh thu theo CƠ SỞ trong khoảng ngày.
	 * @return array {ok, ds:[{cua_hang, thanh_tien, so_ngay}], tu, den, tong}
	 */
	public static function theo_co_so( $tu, $den ) {
		global $wpdb;
		$tu  = self::ngay( $tu );
		$den = self::ngay( $den );
		if ( ! $tu || ! $den ) { return array( 'ok' => false, 'error' => 'Khoảng ngày không hợp lệ.' ); }
		if ( ! self::co_bang() ) {
			return array( 'ok' => false, 'error' => 'Site này chưa cài plugin "Ghế Massage" (không thấy bảng ' . self::bang_dong() . ').' );
		}
		$d = self::bang_dong();
		$h = self::bang_dau();
		/* `cua_hang` chứ không `coso` — cùng tên khoá với KHBC_FABi để giao diện và lớp ghép tên
		   dùng chung một đường, khỏi phải có hai bộ mã làm đúng một việc. */
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.coso AS cua_hang, SUM(d.tong) AS tien, COUNT(DISTINCT d.ngay) AS so_ngay
				   FROM $d d JOIN $h h ON h.report_id = d.report_id
				  WHERE d.ngay BETWEEN %s AND %s
				    AND ( d.chi_so_sau IS NOT NULL OR d.tong <> 0 OR d.actual <> 0 )
				    AND h.coso <> ''
				  GROUP BY h.coso ORDER BY h.coso ASC",
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

	/** Doanh thu một KỲ — bọc lại cho khỏi ai phải tự tính ngày cuối tháng. */
	public static function theo_ky( $thang, $nam ) {
		$m = max( 1, min( 12, (int) $thang ) );
		$y = (int) $nam;
		if ( $y < 2000 || $y > 2100 ) { return array( 'ok' => false, 'error' => 'Năm không hợp lệ.' ); }
		$cuoi = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
		return self::theo_co_so(
			sprintf( '%04d-%02d-01', $y, $m ),
			sprintf( '%04d-%02d-%02d', $y, $m, $cuoi )
		);
	}

	private static function ngay( $s ) {
		$s = trim( (string) $s );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
	}
}
