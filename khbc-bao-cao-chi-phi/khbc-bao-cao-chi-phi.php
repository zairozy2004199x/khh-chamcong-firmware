<?php
/**
 * Plugin Name:       Báo Cáo Chi Phí (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Phân bổ chi phí Máy tự động / Khu vui chơi ra "File tổng báo cáo" + phân bổ theo điểm để hạch toán MISA. Nhân viên nhập khoản chi phí, kế toán duyệt, web tự tính. Dữ liệu nằm trong bảng MySQL riêng của WordPress.
 * Version:           1.24.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       khbc
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🔴 SỐ NÀY PHẢI BẰNG ĐÚNG "Version:" Ở ĐẦU TỆP (cùng luật với plugin Vận Hành Chi Phí).
 * Nó đi vào ?ver= của CSS/JS để trình duyệt bỏ bộ nhớ đệm, và là mốc để bước nâng cấp chạy.
 * Phép thử: tools/kiem-phien-ban.sh
 */
define( 'KHBC_VERSION', '1.24.0' );
define( 'KHBC_FILE', __FILE__ );
define( 'KHBC_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHBC_URL', plugin_dir_url( __FILE__ ) );

require_once KHBC_DIR . 'includes/class-khbc-util.php';
require_once KHBC_DIR . 'includes/class-khbc-db.php';
require_once KHBC_DIR . 'includes/class-khbc-auth.php';
require_once KHBC_DIR . 'includes/class-khbc-store.php';
require_once KHBC_DIR . 'includes/class-khbc-api.php';
require_once KHBC_DIR . 'includes/class-khbc-app.php';
require_once KHBC_DIR . 'includes/class-khbc-admin.php';
require_once KHBC_DIR . 'includes/class-khbc-fabi.php';
require_once KHBC_DIR . 'includes/class-khbc-ghe.php';
require_once KHBC_DIR . 'includes/class-khbc-nhansu.php';
require_once KHBC_DIR . 'includes/class-khbc-tu-cap-nhat.php';

register_activation_hook( __FILE__, array( 'KHBC_DB', 'install' ) );

KHBC_TuCapNhat::init();

add_action( 'plugins_loaded', 'khbc_maybe_upgrade', 20 );
function khbc_maybe_upgrade() {
	if ( get_option( 'khbc_db_version' ) !== KHBC_DB::SCHEMA_VERSION ) {
		KHBC_DB::install();
	}
	if ( get_option( 'khbc_ver' ) !== KHBC_VERSION ) {
		update_option( 'khbc_ver', KHBC_VERSION );
		update_option( 'khbc_flush_rewrite', 1 );
		khbc_xoa_opcache();
	}
}

/**
 * 🔴 BỘ ĐỆM MÃ (OPcache) SAU KHI CẬP NHẬT.
 * Plugin Ghế Massage 15/09/2026 cài bản mới xong mà PHP vẫn chạy MÃ CŨ (hosting bật opcache,
 * không kiểm tra mốc sửa file) — màn hình báo "Phản hồi này đến từ MÃ CŨ hoặc BỘ ĐỆM". Nên sau
 * mỗi lần cập nhật plugin này, xoá bộ đệm cho từng tệp PHP của plugin (và reset toàn bộ nếu
 * hosting cho phép). Không có opcache thì các hàm không tồn tại → bỏ qua, không lỗi.
 */
function khbc_xoa_opcache() {
	if ( function_exists( 'opcache_invalidate' ) ) {
		foreach ( glob( KHBC_DIR . '*.php' ) as $f ) { @opcache_invalidate( $f, true ); }
		foreach ( glob( KHBC_DIR . 'includes/*.php' ) as $f ) { @opcache_invalidate( $f, true ); }
	}
	if ( function_exists( 'opcache_reset' ) ) { @opcache_reset(); }
}
add_action( 'upgrader_process_complete', 'khbc_sau_cap_nhat', 10, 2 );
function khbc_sau_cap_nhat( $upgrader, $opts ) {
	if ( ! is_array( $opts ) || ( isset( $opts['type'] ) && $opts['type'] !== 'plugin' ) ) { return; }
	$duong = plugin_basename( KHBC_FILE );
	$ds = isset( $opts['plugins'] ) ? (array) $opts['plugins'] : array();
	if ( isset( $opts['plugin'] ) ) { $ds[] = $opts['plugin']; }
	if ( ! in_array( $duong, $ds, true ) ) { return; }
	khbc_xoa_opcache();
	delete_transient( KHBC_TuCapNhat::O_NHO );
	update_option( 'khbc_flush_rewrite', 1 );
}

function khbc_flush_rewrite() {
	if ( ! get_option( 'khbc_flush_rewrite' ) ) { return; }
	delete_option( 'khbc_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'rest_api_init', array( 'KHBC_API', 'register_routes' ) );
// Cổng dự phòng: hosting chặn /wp-json/ thì giao diện tự chuyển sang admin-ajax.php rồi URL app
add_action( 'wp_ajax_khbc_call', array( 'KHBC_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_khbc_call', array( 'KHBC_API', 'ajax' ) );
add_action( 'init', array( 'KHBC_App', 'init' ), 5 );
add_action( 'init', 'khbc_flush_rewrite', 99 );
add_action( 'admin_menu', array( 'KHBC_Admin', 'menu' ) );
add_action( 'admin_init', array( 'KHBC_Admin', 'handle_post' ) );
add_shortcode( 'khbc_app', array( 'KHBC_App', 'shortcode' ) );
