<?php
/**
 * Plugin Name:       Chi Phí — Tổng hợp (TONG)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Gom kho đơn của các mảng chi phí (KVC · MTD · VP…) về MỘT trang cho người duyệt. Không có sổ riêng: đọc thẳng bảng của từng bản và duyệt ghi ngược về đúng bản sinh ra đơn.
 * Version:           1.8.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhcpt
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PLUGIN NÀY KHÔNG DỰNG BẢNG NÀO CẢ, và đó là điều quan trọng nhất cần biết về nó.
 *
 *    Anh Thắng 14/09/2026: *"chỉ là kho đơn đẩy nó gom về 1 trang chi phí cho người duyệt"*.
 *    Gom, không phải chép. Chép đơn sang một kho tổng thì có hai bản sao của cùng một đơn, tức
 *    HAI SỰ THẬT — và chúng lệch nhau vào đúng lúc người ta cần con số đúng nhất.
 *
 * 🔴 NẠP SAU CÁC BẢN CHI PHÍ. `VHCPT_Ban::ds()` dò lớp `VHCP<MÃ>_Don` đã được nạp; dò lúc các
 *    bản kia chưa nạp xong thì trang tổng thấy sổ trống và báo "chưa có mảng nào". Nên mọi lượt
 *    dò đều nằm trong `plugins_loaded` với ưu tiên MUỘN, không nằm ở thân tệp này.
 *
 * ⚠️ GỠ PLUGIN NÀY KHÔNG MẤT ĐƠN NÀO. Nó không sở hữu dữ liệu gì ngoài mấy khoá cấu hình của
 *    chính nó (đường dẫn, tên mảng, khoá GitHub).
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHCPT_VERSION', '1.8.0' );
define( 'VHCPT_FILE', __FILE__ );
define( 'VHCPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHCPT_URL', plugin_dir_url( __FILE__ ) );

/* ⚠️ NẠP TỪNG DÒNG MỘT, KHÔNG NẠP BẰNG VÒNG LẶP. Bản nháp dùng `foreach` cho gọn — đọc thì gọn
   thật, nhưng `tools/test/kiem-tu-cap-nhat.php` soi đúng dòng `require_once … tu-cap-nhat.php`
   ở chín plugin để bắt ca "quên nạp lớp tự cập nhật", và vòng lặp thì nó không thấy. Viết cho
   máy soi đọc được quan trọng hơn viết ngắn ba dòng: cái nó canh là một bộ IM LẶNG không bao
   giờ thấy bản mới. */
require_once VHCPT_DIR . 'includes/class-vhcpt-ban.php';
require_once VHCPT_DIR . 'includes/class-vhcpt-auth.php';
require_once VHCPT_DIR . 'includes/class-vhcpt-gom.php';
require_once VHCPT_DIR . 'includes/class-vhcpt-api.php';
require_once VHCPT_DIR . 'includes/class-vhcpt-app.php';
require_once VHCPT_DIR . 'includes/class-vhcpt-admin.php';
require_once VHCPT_DIR . 'includes/class-vhcpt-tu-cap-nhat.php';

/* Ưu tiên 20: sau các plugin chi phí (mặc định 10), để `VHCPT_Ban` dò được lớp của chúng. */
add_action( 'plugins_loaded', function () {
	VHCPT_Api::init();
	VHCPT_TuCapNhat::init();
	if ( is_admin() ) { VHCPT_Admin::init(); }
}, 20 );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐƯỜNG DẪN KHAI Ở `init`, VÀ NẠP LẠI LUẬT THÌ QUA MỘT CỜ — KHÔNG FLUSH LÚC KÍCH HOẠT.
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 14/09/2026: *"trang tổng bị lỗi, vào là sập"*. Đúng, và đây là chỗ hỏng:
 *
 *   Bản đầu gọi `flush_rewrite_rules()` NGAY trong `register_activation_hook`. Lúc ấy WordPress
 *   mới nạp plugin, hook `init` của các plugin KHÁC chưa chạy, nên chúng chưa kịp khai đường của
 *   mình. `flush` ghi lại TOÀN BỘ bảng luật theo đúng những gì đang có — tức ghi lại một bảng
 *   THIẾU. Kết quả: /chi-phi, /cham-cong, /ghe… đồng loạt 404. Không có câu lỗi nào, và nhìn y
 *   như cả site sập.
 *
 * ⚠️ CHÚ THÍCH TRONG `VHCP_App::init()` CỦA BẢN GỐC ĐÃ NÓI ĐÚNG ĐIỀU NÀY — *"phải chạy SAU khi
 *    cả app chi phí và thư viện hợp đồng đều khai xong đường dẫn của mình, không thì lần nạp lại
 *    đó ghi thiếu một đường và trang kia trả 404"*. Bản gốc vì thế chỉ ĐẶT CỜ lúc kích hoạt rồi
 *    flush ở `init` ưu tiên 99. Bản tổng nay đi đúng lối ấy.
 *
 * 🔴 VÀ `add_rewrite_rule` PHẢI GỌI TRONG `init`, KHÔNG PHẢI `plugins_loaded`. Gọi sớm thì luật
 *    nằm ngoài lượt dựng bảng của WordPress — lúc trúng lúc trượt tuỳ bộ nhớ đệm, mà "lúc trúng
 *    lúc trượt" là kiểu hỏng tốn nhiều thời gian nhất để lần ra.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
add_action( 'init', array( 'VHCPT_App', 'init' ), 5 );
add_action( 'init', 'vhcpt_nap_lai_duong', 99 );

function vhcpt_nap_lai_duong() {
	if ( ! get_option( 'vhcpt_flush_rewrite' ) ) { return; }
	delete_option( 'vhcpt_flush_rewrite' );
	flush_rewrite_rules( false );
}

/* Bật plugin -> chỉ ĐẶT CỜ. Lượt tải kế tiếp, `init` ưu tiên 99 mới nạp lại luật — lúc ấy mọi
   plugin đã khai xong đường của mình. */
register_activation_hook( __FILE__, function () {
	update_option( 'vhcpt_flush_rewrite', 1 );
} );
/* Tắt plugin -> cũng chỉ đặt cờ, cùng lý do: flush ngay lúc này là ghi lại bảng luật trong một
   lượt tải mà `init` của các plugin khác chưa chạy. */
register_deactivation_hook( __FILE__, function () {
	update_option( 'vhcpt_flush_rewrite', 1 );
} );
