<?php
/**
 * Plugin Name:       Chấm Công (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Hệ thống chấm công chạy THẲNG trên host: máy chấm công, hàng đợi lệnh, cập nhật firmware và toàn bộ nghiệp vụ đều nằm trên MySQL của chính website. Không Firebase, không Google Sheet.
 * Version:           2.12.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhcc
 *
 * ---------------------------------------------------------------------------
 * PLUGIN NÀY KHÔNG DỰNG LẠI APP HỢP ĐỒNG.
 *
 * App gốc trên Apps Script có 7 tab, 61 trường, bóc tách PDF bằng AI, dò thư mục Drive,
 * tách smart-chip link, tính tiền thuê theo tháng, học gán gian hàng. Viết lại bằng PHP là
 * vừa mất hàng tuần vừa chắc chắn lệch nghiệp vụ ở những chỗ không ai kịp phát hiện.
 *
 * ⚠️ ĐOẠN TRÊN LÀ LỊCH SỬ, KHÔNG CÒN ĐÚNG VỚI BẢN NÀY. Giữ lại vì nó giải thích vì sao mã
 * còn thư mục `apps-script/`. Nay anh Thắng chốt: *"hệ thống thiết lập độc lập, không chạy qua
 * app script nữa"*. Thực tế hiện giờ:
 *
 *   1. CỔNG PIN — nguồn người dùng chọn được: danh sách RIÊNG của plugin (đứng một mình),
 *      dùng chung với app Vận hành chi phí, hoặc bản sao sổ PhanQuyen của app gốc.
 *   2. NGHIỆP VỤ CHẤM CÔNG nằm trong PHP + MySQL của chính website: chấm công, nhân sự, lịch,
 *      lương, yêu cầu, máy chấm công, hàng đợi lệnh, OTA.
 *   3. MÁY CHẤM CÔNG nói thẳng với /cham-cong-may. Không Firebase.
 *
 * Cầu nối Apps Script CÒN LẠI ĐÚNG MỘT VIỆC: KÉO DỮ LIỆU CŨ TỪ SHEET VỀ (một chiều, xem
 * VHCC_Keo). Không hàm nào ghi ngược lên sheet. Kéo xong hết thì gỡ luôn cũng được.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHCC_VERSION', '2.12.0' );
define( 'VHCC_FILE', __FILE__ );
define( 'VHCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHCC_URL', plugin_dir_url( __FILE__ ) );

require_once VHCC_DIR . 'includes/class-vhcc-db.php';
/* Bảng vai trò & quyền — nạp SỚM, mọi lớp dưới đây hỏi nó để biết ai được làm gì. */
require_once VHCC_DIR . 'includes/class-vhcc-vai.php';
require_once VHCC_DIR . 'includes/class-vhcc-auth.php';
require_once VHCC_DIR . 'includes/class-vhcc-cau-noi.php';
require_once VHCC_DIR . 'includes/class-vhcc-api.php';
require_once VHCC_DIR . 'includes/class-vhcc-luong.php';
require_once VHCC_DIR . 'includes/class-vhcc-pdf.php';
require_once VHCC_DIR . 'includes/class-vhcc-quyen.php';
require_once VHCC_DIR . 'includes/class-vhcc-nhan-su.php';
require_once VHCC_DIR . 'includes/class-vhcc-cham.php';
require_once VHCC_DIR . 'includes/class-vhcc-yeucau.php';
require_once VHCC_DIR . 'includes/class-vhcc-lich.php';
require_once VHCC_DIR . 'includes/class-vhcc-may.php';
require_once VHCC_DIR . 'includes/class-vhcc-may-cong.php';
require_once VHCC_DIR . 'includes/class-vhcc-nhan.php';
require_once VHCC_DIR . 'includes/class-vhcc-online.php';
require_once VHCC_DIR . 'includes/class-vhcc-keo.php';
require_once VHCC_DIR . 'includes/class-vhcc-nguoi-dung.php';
require_once VHCC_DIR . 'includes/class-vhcc-nap-csv.php';
require_once VHCC_DIR . 'includes/class-vhcc-trang.php';
require_once VHCC_DIR . 'includes/class-vhcc-tram.php';
require_once VHCC_DIR . 'includes/class-vhcc-web.php';
require_once VHCC_DIR . 'includes/class-vhcc-admin.php';
require_once VHCC_DIR . 'includes/class-vhcc-man.php';

register_activation_hook( __FILE__, array( 'VHCC_DB', 'install' ) );

add_action( 'plugins_loaded', 'vhcc_maybe_upgrade' );
function vhcc_maybe_upgrade() {
	if ( get_option( 'vhcc_ver' ) !== VHCC_VERSION ) {
		VHCC_DB::install();
		VHCC_NguoiDung::mo_duong_vao();   // cài xong phải có ĐƯỜNG VÀO, không thì đứng ở cổng PIN
		update_option( 'vhcc_ver', VHCC_VERSION );
		update_option( 'vhcc_flush_rewrite', 1 );
	}
}

add_action( 'rest_api_init', array( 'VHCC_API', 'register_routes' ) );
// Cổng dự phòng cho hosting chặn /wp-json/ (Cloudflare hay chặn theo đường dẫn)
add_action( 'wp_ajax_vhcc_call', array( 'VHCC_API', 'ajax' ) );
add_action( 'wp_ajax_nopriv_vhcc_call', array( 'VHCC_API', 'ajax' ) );

add_action( 'init', array( 'VHCC_Trang', 'init' ), 5 );
add_action( 'init', array( 'VHCC_Web', 'init' ), 5 );
/* Trạm chấm công của nhân viên — trang họ mở hàng ngày bằng điện thoại. */
add_action( 'init', array( 'VHCC_Tram', 'init' ), 5 );
/* Cổng nhận chấm công của máy. Gài ở ưu tiên 4 — TRƯỚC trang (5) và trước lượt nạp lại luật
   đường dẫn (99) — để luật đường của máy có mặt sớm nhất. Đường của máy là đường duy nhất trong
   plugin này mà một lượt bị chuyển hướng đồng nghĩa MẤT chấm công, xem class-vhcc-nhan.php. */
add_action( 'init', array( 'VHCC_Nhan', 'init' ), 4 );
add_action( 'init', 'vhcc_flush_rewrite', 99 );
function vhcc_flush_rewrite() {
	if ( ! get_option( 'vhcc_flush_rewrite' ) ) { return; }
	delete_option( 'vhcc_flush_rewrite' );
	flush_rewrite_rules( false );
}

add_action( 'admin_menu', array( 'VHCC_Admin', 'menu' ) );
add_action( 'in_admin_header', array( 'VHCC_Admin', 'dai_ban' ) );
add_action( 'admin_init', array( 'VHCC_Admin', 'handle_post' ) );
add_shortcode( 'vhcc_hop_dong', array( 'VHCC_Trang', 'shortcode' ) );
add_shortcode( 'vhcc_tram', array( 'VHCC_Tram', 'shortcode' ) );
