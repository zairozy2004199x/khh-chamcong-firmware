<?php
/**
 * Plugin Name:       Vận Hành Chi Phí (Hà Nội)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       App Chi Phí Cơ Sở / Vận Hành Chi Phí dựng lại trên WordPress — đơn tạm ứng theo tuần, chi phí kỹ thuật, marketing, công tác/setup, quyết toán thừa/thiếu và xuất MISA. Dữ liệu nằm trong bảng MySQL riêng (không phụ thuộc Google Sheet).
 * Version:           1.94.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhcphn
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🔴 SỐ NÀY PHẢI BẰNG ĐÚNG "Version:" Ở ĐẦU TỆP.
 *
 * Nó không phải chỗ ghi chú: `vhcphn_ver` so với nó để biết có phải chạy bước nâng cấp không, và
 * nó đi vào ?ver= của CSS/JS để trình duyệt bỏ bộ nhớ đệm. Header đã lên 1.35.0 trong khi số
 * này còn đứng ở 1.31.0 — nghĩa là suốt từ đó tới giờ, cài đè KHÔNG chạy bước nâng cấp nào và
 * trình duyệt vẫn dùng CSS/JS cũ. Có phép thử chốt hai số bằng nhau: tools/test/kiem-phien-ban.py
 */
define( 'VHCPHN_VERSION', '1.94.1' );
define( 'VHCPHN_FILE', __FILE__ );
define( 'VHCPHN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHCPHN_URL', plugin_dir_url( __FILE__ ) );

require_once VHCPHN_DIR . 'includes/class-vhcp-util.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-db.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-meta.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-cfg.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-auth.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-log.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-donvi.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-don.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-sochi.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-duan.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-mk.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-bp.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-report.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-misa.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-trama.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-upload.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-nap.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-sheet.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-import.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-api.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-app.php';
require_once VHCPHN_DIR . 'includes/class-vhcp-admin.php';

register_activation_hook( __FILE__, array( 'VHCPHN_DB', 'install' ) );

add_action( 'plugins_loaded', 'vhcphn_maybe_upgrade', 20 );   // 20: sau khi plugin Ghế nạp xong lớp VHG_May
function vhcphn_maybe_upgrade() {
	if ( get_option( 'vhcphn_db_version' ) !== VHCPHN_DB::SCHEMA_VERSION ) {
		VHCPHN_DB::install();
	}
	// Lên bản mới có thể thêm trang mới (VD /hop-dong/ của bản 1.16.0). Đường dẫn tĩnh chỉ
	// chạy sau khi WordPress nạp lại bảng rewrite, nên cứ đổi phiên bản là đặt cờ nạp lại —
	// không thì trang mới trả 404 cho tới khi có ai bấm Lưu ở Cài đặt.
	if ( get_option( 'vhcphn_ver' ) !== VHCPHN_VERSION ) {
		update_option( 'vhcphn_ver', VHCPHN_VERSION );
		update_option( 'vhcphn_flush_rewrite', 1 );
	}
	/* 🔴 BẢN VÁ PHÂN QUYỀN PHẢI ĐỨNG NGOÀI CHỐT PHIÊN BẢN CSDL.
	   Anh Thắng 28/08/2026 cài b1.50.1 xong vẫn báo *"anh chưa thấy nút duyệt"*. Lý do: bản vá
	   được đặt trong `VHCPHN_DB::install()`, mà hàm ấy chỉ chạy khi `vhcphn_db_version` KHÁC
	   `SCHEMA_VERSION` — bản trước không đổi sơ đồ bảng nên số ấy y nguyên, và bản vá không
	   bao giờ được gọi. Cài đè xong trông như đã sửa mà thực ra chưa chạy một dòng nào.
	   Đặt ở đây thì nó chạy đúng một lần cho MỌI site, dù sơ đồ bảng có đổi hay không —
	   `va_quyen_quyet_toan()` tự giữ cờ, nên lần nạp thứ hai trở đi chỉ tốn một `get_option`. */
	if ( method_exists( 'VHCPHN_Cfg', 'va_quyen_quyet_toan' ) ) {
		VHCPHN_Cfg::va_quyen_quyet_toan();
	}
	/* 🔴 HÚT CƠ SỞ BÊN GHẾ SANG — LƯỢT ĐẦU.
	   Anh Thắng 08/09/2026: *"tự đẩy lấy dữ liệu qua luôn"*. Móc `vhg_coso_da_luu` dưới đây
	   chỉ bắt được cơ sở tạo TỪ BÂY GIỜ; bên ghế thì đã có sẵn hàng chục địa điểm. Không hút
	   một lượt thì mọi đồng chi cho mấy gian ấy rơi về nhà mặc định — số của POSH nằm trong sổ
	   K&H, không ai thấy để sửa.

	   ⚠️ CHẠY MỘT LẦN CHO MỖI PHIÊN BẢN, không phải mỗi lượt tải trang: hàm hút quét cả danh
	      mục cơ sở cho từng dòng bên ghế, làm ở mọi lượt tải là một khoản phí vô ích trên
	      trang nào cũng phải trả. Cờ theo phiên bản để bản sau còn hút lại được nếu cần.
	   ⚠️ ĐẶT SAU `plugins_loaded` của bên ghế bằng cách gọi ở ưu tiên muộn — lúc này lớp
	      `VHG_May` mới chắc chắn đã nạp. Chưa cài plugin ghế thì hàm tự trả 0. */
	if ( get_option( 'vhcphn_hut_coso_ghe' ) !== VHCPHN_VERSION ) {
		update_option( 'vhcphn_hut_coso_ghe', VHCPHN_VERSION );
		VHCPHN_Cfg::hut_coso_ghe();
	}
}

/* 🔴 CƠ SỞ MỚI BÊN GHẾ -> VÀO THẲNG DANH MỤC CƠ SỞ CỦA CHI PHÍ, gắn sẵn đơn vị POSH.
   Nghe bằng móc chứ không để bên ghế gọi thẳng vào đây: hai plugin cài độc lập, gỡ cái nào
   thì cái kia vẫn phải chạy. Xem `VHCPHN_Cfg::nhan_coso_ngoai()` — nó CHỈ THÊM, không sửa
   không xoá, nên nghe nhiều lượt cùng một tên cũng không sinh dòng thứ hai. */
add_action( 'vhg_coso_da_luu', array( 'VHCPHN_Cfg', 'moc_coso_ghe' ) );

/**
 * Nạp lại bảng đường dẫn — chạy ở ưu tiên muộn để CẢ HAI trang đã khai đường dẫn xong.
 * Nếu nạp lại sớm hơn thì đường dẫn khai sau bị bỏ khỏi bảng và trả 404.
 */
function vhcphn_flush_rewrite() {
	if ( ! get_option( 'vhcphn_flush_rewrite' ) ) { return; }
	delete_option( 'vhcphn_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'rest_api_init', array( 'VHCPHN_API', 'register_routes' ) );
// Cổng dự phòng: hosting nào chặn /wp-json/ thì giao diện tự chuyển sang admin-ajax.php
add_action( 'wp_ajax_vhcphn_call', array( 'VHCPHN_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_vhcphn_call', array( 'VHCPHN_API', 'ajax' ) );
add_action( 'init', array( 'VHCPHN_App', 'init' ), 5 );
add_action( 'init', 'vhcphn_flush_rewrite', 99 );
add_action( 'admin_menu', array( 'VHCPHN_Admin', 'menu' ) );
add_action( 'admin_init', array( 'VHCPHN_Admin', 'handle_post' ) );
add_shortcode( 'vhcphn_app', array( 'VHCPHN_App', 'shortcode' ) );
