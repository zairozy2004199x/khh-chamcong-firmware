<?php
/**
 * Bệ đỡ cho bài kiểm cầu đẩy người sang màn Báo cáo doanh thu cơ sở.
 *
 * `wp-stub.php` đã dựng sẵn WordPress giả + `$wpdb` trên SQLite; ở đây chỉ thêm hai bảng riêng
 * của plugin `khh-doanh-thu` và vài hàm bộ thử cần.
 *
 * ⚠️ DDL PHẢI THEO ĐÚNG `khh_dt_tao_bang_nguoi()`. `dbDelta()` của bệ đỡ là hàm rỗng nên bảng
 *    không tự dựng; khai lệch một cột ở đây là bài kiểm chạy trên một cái bảng KHÔNG PHẢI cái
 *    chạy trên hosting, và nó sẽ xanh cho một thứ hỏng.
 */

require_once __DIR__ . '/../wp-stub.php';

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() { return false; }
}

/** Dựng lại hai bảng sạch. */
function khh_dt_test_dung_bang() {
	global $wpdb;
	require_once dirname( __DIR__, 3 ) . '/wordpress/khh-doanh-thu/nguoi.php';
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_nguoi() );
	$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_phien() );
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_nguoi() . " (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ma_nv TEXT NOT NULL DEFAULT '' UNIQUE,
			ho_ten TEXT NOT NULL DEFAULT '',
			pin TEXT NOT NULL DEFAULT '',
			coso_ma TEXT NOT NULL DEFAULT '',
			vai TEXT NOT NULL DEFAULT 'nhap',
			cap_nhat TEXT NULL )"
	);
	$wpdb->exec_raw(
		'CREATE TABLE ' . khh_dt_bang_phien() . " (
			token TEXT PRIMARY KEY,
			ma_nv TEXT NOT NULL DEFAULT '',
			het_han TEXT NOT NULL )"
	);
	khh_dt_test_dat_the( '' );
	delete_option( 'khh_dt_ghep_coso' );
}

/** Giả lập trình duyệt gửi kèm thẻ phiên ở header. */
function khh_dt_test_dat_the( $token ) {
	$_SERVER['HTTP_X_KHH_PHIEN'] = (string) $token;
	khh_dt_phien_quen();
}

/** Quên câu trả lời đã nhớ trong lượt — bộ thử làm nhiều việc trong "một lượt". */
function khh_dt_test_quen_phien() {
	khh_dt_phien_quen();
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $f ) { return @unlink( $f ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $ds, $khoa ) {
		$ra = array();
		foreach ( (array) $ds as $x ) { $ra[] = is_array( $x ) ? $x[ $khoa ] : $x->$khoa; }
		return $ra;
	}
}

/**
 * `khh_dt_ds_cua_hang()` thật đọc bảng số liệu POS. Bài kiểm không dựng cả bảng ấy, nên cho phép
 * đặt sẵn danh sách qua $GLOBALS['KHH_DT_TEST_CH'].
 */
if ( ! function_exists( 'khh_dt_ds_cua_hang' ) ) {
	function khh_dt_ds_cua_hang() {
		return isset( $GLOBALS['KHH_DT_TEST_CH'] ) ? (array) $GLOBALS['KHH_DT_TEST_CH'] : array();
	}
}

if ( ! function_exists( 'get_temp_dir' ) ) {
	function get_temp_dir() { return rtrim( sys_get_temp_dir(), '/' ) . '/'; }
}
