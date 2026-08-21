<?php
/**
 * Plugin Name:       Vận Hành Chi Phí (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       App Chi Phí Cơ Sở / Vận Hành Chi Phí dựng lại trên WordPress — đơn tạm ứng theo tuần, chi phí kỹ thuật, marketing, công tác/setup, quyết toán thừa/thiếu và xuất MISA. Dữ liệu nằm trong bảng MySQL riêng (không phụ thuộc Google Sheet).
 * Version:           1.3.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhcp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHCP_VERSION', '1.3.0' );
define( 'VHCP_FILE', __FILE__ );
define( 'VHCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHCP_URL', plugin_dir_url( __FILE__ ) );

require_once VHCP_DIR . 'includes/class-vhcp-util.php';
require_once VHCP_DIR . 'includes/class-vhcp-db.php';
require_once VHCP_DIR . 'includes/class-vhcp-meta.php';
require_once VHCP_DIR . 'includes/class-vhcp-cfg.php';
require_once VHCP_DIR . 'includes/class-vhcp-auth.php';
require_once VHCP_DIR . 'includes/class-vhcp-log.php';
require_once VHCP_DIR . 'includes/class-vhcp-don.php';
require_once VHCP_DIR . 'includes/class-vhcp-sochi.php';
require_once VHCP_DIR . 'includes/class-vhcp-duan.php';
require_once VHCP_DIR . 'includes/class-vhcp-mk.php';
require_once VHCP_DIR . 'includes/class-vhcp-bp.php';
require_once VHCP_DIR . 'includes/class-vhcp-report.php';
require_once VHCP_DIR . 'includes/class-vhcp-misa.php';
require_once VHCP_DIR . 'includes/class-vhcp-trama.php';
require_once VHCP_DIR . 'includes/class-vhcp-upload.php';
require_once VHCP_DIR . 'includes/class-vhcp-nap.php';
require_once VHCP_DIR . 'includes/class-vhcp-sheet.php';
require_once VHCP_DIR . 'includes/class-vhcp-import.php';
require_once VHCP_DIR . 'includes/class-vhcp-api.php';
require_once VHCP_DIR . 'includes/class-vhcp-app.php';
require_once VHCP_DIR . 'includes/class-vhcp-admin.php';

register_activation_hook( __FILE__, array( 'VHCP_DB', 'install' ) );

add_action( 'plugins_loaded', 'vhcp_maybe_upgrade' );
function vhcp_maybe_upgrade() {
	if ( get_option( 'vhcp_db_version' ) !== VHCP_DB::SCHEMA_VERSION ) {
		VHCP_DB::install();
	}
}

add_action( 'rest_api_init', array( 'VHCP_API', 'register_routes' ) );
// Cổng dự phòng: hosting nào chặn /wp-json/ thì giao diện tự chuyển sang admin-ajax.php
add_action( 'wp_ajax_vhcp_call', array( 'VHCP_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_vhcp_call', array( 'VHCP_API', 'ajax' ) );
add_action( 'init', array( 'VHCP_App', 'init' ) );
add_action( 'admin_menu', array( 'VHCP_Admin', 'menu' ) );
add_action( 'admin_init', array( 'VHCP_Admin', 'handle_post' ) );
add_shortcode( 'vhcp_app', array( 'VHCP_App', 'shortcode' ) );
