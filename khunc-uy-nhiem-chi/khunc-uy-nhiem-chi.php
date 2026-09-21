<?php
/**
 * Plugin Name:       Ủy Nhiệm Chi & Công Nợ (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Theo dõi ủy nhiệm chi đã đi tiền hay chưa và công nợ phải trả từng nhà cung cấp, đọc thẳng từ file Excel "Đi ủy nhiệm chi". Kèm 7 mẫu biểu in A4 (UNC, đề nghị thanh toán 05-TT, phiếu chi 02-TT, biên bản đối chiếu công nợ, bảng kê, sổ chi tiết 331, kế hoạch chi tiền). Dữ liệu nằm trong bảng MySQL riêng của WordPress, cả bộ phận dùng chung.
 * Version:           1.2.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       khunc
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🔴 SỐ NÀY PHẢI BẰNG ĐÚNG "Version:" Ở ĐẦU TỆP (cùng luật với plugin Báo Cáo Chi Phí).
 * Nó đi vào ?ver= của CSS/JS để trình duyệt bỏ bộ nhớ đệm, và là mốc để bước nâng cấp chạy.
 * Phép thử: tools/kiem-phien-ban.sh
 */
define( 'KHUNC_VERSION', '1.2.0' );
define( 'KHUNC_FILE', __FILE__ );
define( 'KHUNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHUNC_URL', plugin_dir_url( __FILE__ ) );

require_once KHUNC_DIR . 'includes/class-khunc-util.php';
require_once KHUNC_DIR . 'includes/class-khunc-db.php';
require_once KHUNC_DIR . 'includes/class-khunc-auth.php';
require_once KHUNC_DIR . 'includes/class-khunc-store.php';
require_once KHUNC_DIR . 'includes/class-khunc-api.php';
require_once KHUNC_DIR . 'includes/class-khunc-app.php';
require_once KHUNC_DIR . 'includes/class-khunc-admin.php';
require_once KHUNC_DIR . 'includes/class-khunc-tu-cap-nhat.php';

register_activation_hook( __FILE__, array( 'KHUNC_DB', 'install' ) );

KHUNC_TuCapNhat::init();

add_action( 'plugins_loaded', 'khunc_maybe_upgrade', 20 );
function khunc_maybe_upgrade() {
	if ( get_option( 'khunc_db_version' ) !== KHUNC_DB::SCHEMA_VERSION ) {
		KHUNC_DB::install();
	}
	if ( get_option( 'khunc_ver' ) !== KHUNC_VERSION ) {
		update_option( 'khunc_ver', KHUNC_VERSION );
		update_option( 'khunc_flush_rewrite', 1 );
		khunc_xoa_opcache();
	}
}

/**
 * 🔴 BỘ ĐỆM MÃ (OPcache) SAU KHI CẬP NHẬT.
 * Cùng bài học với plugin Báo Cáo Chi Phí: hosting bật opcache mà không kiểm mốc sửa file thì
 * cài bản mới xong PHP vẫn chạy MÃ CŨ. Nên sau mỗi lần cập nhật, xoá bộ đệm cho từng tệp PHP
 * của plugin (và reset toàn bộ nếu hosting cho phép). Không có opcache thì các hàm không tồn
 * tại → bỏ qua, không lỗi.
 */
function khunc_xoa_opcache() {
	if ( function_exists( 'opcache_invalidate' ) ) {
		foreach ( glob( KHUNC_DIR . '*.php' ) as $f ) { @opcache_invalidate( $f, true ); }
		foreach ( glob( KHUNC_DIR . 'includes/*.php' ) as $f ) { @opcache_invalidate( $f, true ); }
	}
	if ( function_exists( 'opcache_reset' ) ) { @opcache_reset(); }
}

add_action( 'upgrader_process_complete', 'khunc_sau_cap_nhat', 10, 2 );
function khunc_sau_cap_nhat( $upgrader, $opts ) {
	if ( ! is_array( $opts ) || ( isset( $opts['type'] ) && $opts['type'] !== 'plugin' ) ) { return; }
	$duong = plugin_basename( KHUNC_FILE );
	$ds    = isset( $opts['plugins'] ) ? (array) $opts['plugins'] : array();
	if ( isset( $opts['plugin'] ) ) { $ds[] = $opts['plugin']; }
	if ( ! in_array( $duong, $ds, true ) ) { return; }
	khunc_xoa_opcache();
	delete_transient( KHUNC_TuCapNhat::O_NHO );
	update_option( 'khunc_flush_rewrite', 1 );
}

function khunc_flush_rewrite() {
	if ( ! get_option( 'khunc_flush_rewrite' ) ) { return; }
	delete_option( 'khunc_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'rest_api_init', array( 'KHUNC_API', 'register_routes' ) );
// Cổng dự phòng: hosting chặn /wp-json/ thì giao diện tự chuyển sang admin-ajax.php rồi URL app
add_action( 'wp_ajax_khunc_call', array( 'KHUNC_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_khunc_call', array( 'KHUNC_API', 'ajax' ) );
add_action( 'init', array( 'KHUNC_App', 'init' ), 5 );
add_action( 'init', 'khunc_flush_rewrite', 99 );
add_action( 'admin_menu', array( 'KHUNC_Admin', 'menu' ) );
add_action( 'admin_init', array( 'KHUNC_Admin', 'handle_post' ) );
add_shortcode( 'khunc_app', array( 'KHUNC_App', 'shortcode' ) );
