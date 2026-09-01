<?php
/**
 * Plugin Name:       Vận Hành Chi Phí (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       App Chi Phí Cơ Sở / Vận Hành Chi Phí dựng lại trên WordPress — đơn tạm ứng theo tuần, chi phí kỹ thuật, marketing, công tác/setup, quyết toán thừa/thiếu và xuất MISA. Dữ liệu nằm trong bảng MySQL riêng (không phụ thuộc Google Sheet).
 * Version:           1.53.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhcp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🔴 SỐ NÀY PHẢI BẰNG ĐÚNG "Version:" Ở ĐẦU TỆP.
 *
 * Nó không phải chỗ ghi chú: `vhcp_ver` so với nó để biết có phải chạy bước nâng cấp không, và
 * nó đi vào ?ver= của CSS/JS để trình duyệt bỏ bộ nhớ đệm. Header đã lên 1.35.0 trong khi số
 * này còn đứng ở 1.31.0 — nghĩa là suốt từ đó tới giờ, cài đè KHÔNG chạy bước nâng cấp nào và
 * trình duyệt vẫn dùng CSS/JS cũ. Có phép thử chốt hai số bằng nhau: tools/test/kiem-phien-ban.py
 */
define( 'VHCP_VERSION', '1.53.0' );
define( 'VHCP_FILE', __FILE__ );
define( 'VHCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHCP_URL', plugin_dir_url( __FILE__ ) );

require_once VHCP_DIR . 'includes/class-vhcp-util.php';
require_once VHCP_DIR . 'includes/class-vhcp-db.php';
require_once VHCP_DIR . 'includes/class-vhcp-meta.php';
require_once VHCP_DIR . 'includes/class-vhcp-cfg.php';
require_once VHCP_DIR . 'includes/class-vhcp-auth.php';
require_once VHCP_DIR . 'includes/class-vhcp-log.php';
require_once VHCP_DIR . 'includes/class-vhcp-donvi.php';
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
	// Lên bản mới có thể thêm trang mới (VD /hop-dong/ của bản 1.16.0). Đường dẫn tĩnh chỉ
	// chạy sau khi WordPress nạp lại bảng rewrite, nên cứ đổi phiên bản là đặt cờ nạp lại —
	// không thì trang mới trả 404 cho tới khi có ai bấm Lưu ở Cài đặt.
	if ( get_option( 'vhcp_ver' ) !== VHCP_VERSION ) {
		update_option( 'vhcp_ver', VHCP_VERSION );
		update_option( 'vhcp_flush_rewrite', 1 );
	}
	/* 🔴 BẢN VÁ PHÂN QUYỀN PHẢI ĐỨNG NGOÀI CHỐT PHIÊN BẢN CSDL.
	   Anh Thắng 28/08/2026 cài b1.50.1 xong vẫn báo *"anh chưa thấy nút duyệt"*. Lý do: bản vá
	   được đặt trong `VHCP_DB::install()`, mà hàm ấy chỉ chạy khi `vhcp_db_version` KHÁC
	   `SCHEMA_VERSION` — bản trước không đổi sơ đồ bảng nên số ấy y nguyên, và bản vá không
	   bao giờ được gọi. Cài đè xong trông như đã sửa mà thực ra chưa chạy một dòng nào.
	   Đặt ở đây thì nó chạy đúng một lần cho MỌI site, dù sơ đồ bảng có đổi hay không —
	   `va_quyen_quyet_toan()` tự giữ cờ, nên lần nạp thứ hai trở đi chỉ tốn một `get_option`. */
	if ( method_exists( 'VHCP_Cfg', 'va_quyen_quyet_toan' ) ) {
		VHCP_Cfg::va_quyen_quyet_toan();
	}
}

/**
 * Nạp lại bảng đường dẫn — chạy ở ưu tiên muộn để CẢ HAI trang đã khai đường dẫn xong.
 * Nếu nạp lại sớm hơn thì đường dẫn khai sau bị bỏ khỏi bảng và trả 404.
 */
function vhcp_flush_rewrite() {
	if ( ! get_option( 'vhcp_flush_rewrite' ) ) { return; }
	delete_option( 'vhcp_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'rest_api_init', array( 'VHCP_API', 'register_routes' ) );
// Cổng dự phòng: hosting nào chặn /wp-json/ thì giao diện tự chuyển sang admin-ajax.php
add_action( 'wp_ajax_vhcp_call', array( 'VHCP_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_vhcp_call', array( 'VHCP_API', 'ajax' ) );
add_action( 'init', array( 'VHCP_App', 'init' ), 5 );
add_action( 'init', 'vhcp_flush_rewrite', 99 );
add_action( 'admin_menu', array( 'VHCP_Admin', 'menu' ) );
add_action( 'admin_init', array( 'VHCP_Admin', 'handle_post' ) );
add_shortcode( 'vhcp_app', array( 'VHCP_App', 'shortcode' ) );
