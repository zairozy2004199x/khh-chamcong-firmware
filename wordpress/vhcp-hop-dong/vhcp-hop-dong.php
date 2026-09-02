<?php
/**
 * Plugin Name:       Thư Viện Hợp Đồng (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Đưa app Thư viện hợp đồng (Apps Script) lên website: giao diện và nghiệp vụ GIỮ NGUYÊN bản gốc, dữ liệu vẫn đọc/ghi trên Google Sheet. WordPress lo cổng PIN và giữ khoá bí mật.
 * Version:           1.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhd
 *
 * ---------------------------------------------------------------------------
 * PLUGIN NÀY KHÔNG DỰNG LẠI APP HỢP ĐỒNG.
 *
 * App gốc trên Apps Script có 7 tab, 61 trường, bóc tách PDF bằng AI, dò thư mục Drive,
 * tách smart-chip link, tính tiền thuê theo tháng, học gán gian hàng. Viết lại bằng PHP là
 * vừa mất hàng tuần vừa chắc chắn lệch nghiệp vụ ở những chỗ không ai kịp phát hiện.
 *
 * Nên plugin chỉ làm 3 việc:
 *   1. CỔNG PIN  — dùng chung tài khoản với app Vận hành chi phí (đọc bảng người dùng của nó).
 *   2. LẤY GIAO DIỆN GỐC từ chính project Apps Script rồi phục vụ tại /hop-dong/.
 *   3. CHUYỂN TIẾP mọi lệnh google.script.run sang /exec của app đó, kèm khoá bí mật —
 *      khoá nằm ở máy chủ, không bao giờ xuống trình duyệt.
 *
 * Mọi logic nghiệp vụ vẫn ở Code.gs. Sửa app: sửa Code.gs rồi Deploy → New version.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHD_VERSION', '1.1.0' );
define( 'VHD_FILE', __FILE__ );
define( 'VHD_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHD_URL', plugin_dir_url( __FILE__ ) );

require_once VHD_DIR . 'includes/class-vhd-db.php';
require_once VHD_DIR . 'includes/class-vhd-auth.php';
require_once VHD_DIR . 'includes/class-vhd-cau-noi.php';
require_once VHD_DIR . 'includes/class-vhd-api.php';
require_once VHD_DIR . 'includes/class-vhd-kho.php';
require_once VHD_DIR . 'includes/class-vhd-man-kho.php';
require_once VHD_DIR . 'includes/class-vhd-trang.php';
require_once VHD_DIR . 'includes/class-vhd-admin.php';

register_activation_hook( __FILE__, array( 'VHD_DB', 'install' ) );

add_action( 'plugins_loaded', 'vhd_maybe_upgrade' );
function vhd_maybe_upgrade() {
	if ( get_option( 'vhd_ver' ) !== VHD_VERSION ) {
		VHD_DB::install();
		update_option( 'vhd_ver', VHD_VERSION );
		update_option( 'vhd_flush_rewrite', 1 );
	}
}

add_action( 'rest_api_init', array( 'VHD_API', 'register_routes' ) );
// Cổng dự phòng cho hosting chặn /wp-json/ (Cloudflare hay chặn theo đường dẫn)
add_action( 'wp_ajax_vhd_call', array( 'VHD_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_vhd_call', array( 'VHD_API', 'ajax' ) );

add_action( 'init', array( 'VHD_Trang', 'init' ), 5 );
add_action( 'init', 'vhd_flush_rewrite', 99 );
function vhd_flush_rewrite() {
	if ( ! get_option( 'vhd_flush_rewrite' ) ) { return; }
	delete_option( 'vhd_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'admin_menu', array( 'VHD_Admin', 'menu' ) );
add_action( 'admin_init', array( 'VHD_Admin', 'handle_post' ) );
add_shortcode( 'vhd_hop_dong', array( 'VHD_Trang', 'shortcode' ) );
