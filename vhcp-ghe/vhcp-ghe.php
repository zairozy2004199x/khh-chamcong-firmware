<?php
/**
 * Plugin Name:       Ghế Massage (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Hệ thống ghế massage QR chạy THẲNG trên host: nhận webhook tiền vào, ghi doanh thu, cho ghế chạy, đối soát theo cơ sở/máy. Không Firebase, không Apps Script.
 * Version:           1.53.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhg
 *
 * ---------------------------------------------------------------------------
 * BẢN GỐC LÀ APPS SCRIPT + FIREBASE. BẢN NÀY BỎ CẢ HAI.
 *
 * Luồng cũ:  ngân hàng -> webhook Apps Script -> Firebase /ghe/pay -> ESP32 thấy -> chạy ghế
 * Luồng nay: ngân hàng -> /ghe-tien trên chính website -> MySQL -> ESP32 hỏi /ghe-may -> chạy
 *
 * 🔴 HAI HẰNG PHẢI KHAI TRONG `wp-config.php` — KHÔNG khai thì cổng ĐÓNG, không phải mở:
 *
 *     define( 'VHG_KHOA_WEBHOOK', '…chuỗi dài ngẫu nhiên…' );   // dán vào ô webhook của bên gửi
 *     define( 'VHG_KHOA_MAY',     '…chuỗi dài ngẫu nhiên KHÁC…' ); // nạp vào ESP32 của ghế
 *
 * Hai khoá KHÁC NHAU, cố ý: khoá webhook đi trên đường dẫn (bên gửi không cho đặt header) nên
 * coi như lộ một phần; khoá máy đi trong header/thân. Dùng chung một chuỗi là lộ cái này kéo
 * theo cái kia.
 *
 * ⚠️ ĐỪNG để PIN hay khoá nào trong mã nguồn. Repo này CÔNG KHAI. Bản Apps Script cũ có
 *    `DASHBOARD_PIN = '246810'` ghi thẳng trong mã — ai đọc được mã là bật/tắt được ghế và xoá
 *    được doanh thu. Ở đây màn hình nằm trong wp-admin nên người xem phải đăng nhập WordPress;
 *    không có PIN thứ hai để lộ.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHG_VERSION', '1.53.1' );
define( 'VHG_FILE', __FILE__ );
define( 'VHG_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHG_URL', plugin_dir_url( __FILE__ ) );

require_once VHG_DIR . 'includes/class-vhg-db.php';
require_once VHG_DIR . 'includes/class-vhg-doc.php';
require_once VHG_DIR . 'includes/class-vhg-may.php';
require_once VHG_DIR . 'includes/class-vhg-thu.php';
require_once VHG_DIR . 'includes/class-vhg-qr.php';
require_once VHG_DIR . 'includes/class-vhg-ma.php';
/* Ví phải nạp SAU class-vhg-ma.php: VHG_Vi gọi VHG_Ma::sdt_sach/bam_pin/... ngay từ
   những hàm đầu tiên. Nạp trước là lỗi "class not found" ở đúng đường tiền vào. */
require_once VHG_DIR . 'includes/class-vhg-vi.php';
/* Quỹ tiền mặt phải nạp SAU class-vhg-thu.php và class-vhg-may.php: VHG_Quy đọc thẳng bảng
   `thu` qua hằng của VHG_Thu, và hỏi VHG_May xem ghế có thật không. */
require_once VHG_DIR . 'includes/class-vhg-quy.php';
/* Báo cáo doanh thu theo cơ sở (port app Apps Script "thu tiền"). Nạp SAU class-vhg-quy.php và
   class-vhg-may.php: VHG_BaoCao dùng VHG_Quy::don_vi() và VHG_May::ds_may(), và đọc chung bảng
   `chot` để lấy chỉ số trước. */
require_once VHG_DIR . 'includes/class-vhg-baocao.php';
/* Trang kế toán (duyệt báo cáo, đối chiếu, công nợ, MISA…). Nạp SAU class-vhg-baocao.php và
   class-vhg-quy.php: VHG_KeToan dùng lại VHG_BaoCao::squash/ngay_/chi_so_truoc và VHG_Quy::don_vi. */
require_once VHG_DIR . 'includes/class-vhg-ketoan.php';
require_once VHG_DIR . 'includes/class-vhg-chan.php';
require_once VHG_DIR . 'includes/class-vhg-qrve.php';
require_once VHG_DIR . 'includes/class-vhg-nhap.php';
require_once VHG_DIR . 'includes/class-vhg-cong.php';
require_once VHG_DIR . 'includes/class-vhg-auth.php';
require_once VHG_DIR . 'includes/class-vhg-trang.php';
require_once VHG_DIR . 'includes/class-vhg-shop.php';
require_once VHG_DIR . 'includes/class-vhg-admin.php';

register_activation_hook( __FILE__, array( 'VHG_DB', 'install' ) );

add_action( 'plugins_loaded', 'vhg_maybe_upgrade' );
function vhg_maybe_upgrade() {
	if ( get_option( 'vhg_ver' ) !== VHG_VERSION ) {
		VHG_DB::install();
		update_option( 'vhg_ver', VHG_VERSION );
		update_option( 'vhg_flush_rewrite', 1 );
	}
}

/* Cổng gài SỚM (ưu tiên 4) — trước lượt nạp lại luật đường dẫn (99). Đường của tiền là đường
   mà một lượt bị chuyển hướng đồng nghĩa MẤT doanh thu; xem class-vhg-cong.php. */
add_action( 'init', array( 'VHG_Cong', 'init' ), 4 );
/* Trang ngoài gài cùng ưu tiên 4: nó cũng khai luật đường dẫn, nên phải xong TRƯỚC lượt nạp
   lại ở 99. Gài sau 99 thì luật vừa khai chưa nằm trong bản đã nạp — trang trả 404 cho tới
   lần lưu Permalinks kế tiếp, mà không ai nghĩ tới việc đi lưu một trang mình không sửa. */
add_action( 'init', array( 'VHG_Trang', 'init' ), 4 );
/* Trang bán mã cho khách — cũng khai luật đường dẫn, nên cũng phải gài ở ưu tiên 4.
   🔴 Quên dòng này là `/mua-ma` trả 404, mà KHÔNG có gì báo lỗi ở đâu cả: lớp vẫn nạp, hàm vẫn
      gọi được từ phép thử, chỉ là WordPress không bao giờ hỏi tới nó. Đúng chuyện đã xảy ra
      23/08/2026. Phép thử `kiem_gai_trang` bên dưới canh chỗ này. */
add_action( 'init', array( 'VHG_Shop', 'init' ), 4 );
add_action( 'init', 'vhg_flush_rewrite', 99 );
function vhg_flush_rewrite() {
	if ( get_option( 'vhg_flush_rewrite' ) ) {
		flush_rewrite_rules( false );
		delete_option( 'vhg_flush_rewrite' );
	}
}

add_action( 'admin_menu', array( 'VHG_Admin', 'menu' ) );
