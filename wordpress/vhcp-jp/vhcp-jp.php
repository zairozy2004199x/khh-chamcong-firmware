<?php
/**
 * Plugin Name:       JP Capsule (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Báo cáo JP Capsule chạy THẲNG trên host: nhân viên nhập báo cáo từ chỉ số máy, kế toán duyệt hai phần, đối soát ngân hàng, kho hai tầng. Không Apps Script, không Google Sheets.
 * Version:           1.4.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 * Text Domain:       vhjp
 *
 * ---------------------------------------------------------------------------
 * BẢN GỐC LÀ GOOGLE APPS SCRIPT + GOOGLE SHEETS. BẢN NÀY BỎ CẢ HAI.
 *
 * Luồng cũ:  nhân viên -> web Apps Script -> Google Sheet "JP KẾ TOÁN - DATA (v2 làm lại)"
 *            kế toán   -> web Apps Script -> cùng Sheet ấy
 * Luồng nay: cả hai    -> trang trên chính website -> MySQL
 *
 * Mã gốc giữ nguyên ở `goc/jp-capsule-v2/` (32 file, 28.735 dòng) để tra cứu nghiệp vụ — nó
 * KHÔNG chạy gì, và không nằm trong bản cài.
 *
 * ---------------------------------------------------------------------------
 * ĐANG DỰNG DỞ — MỚI CÓ TẦNG BẢNG.
 *
 * Xong:  lược đồ 23 bảng (`class-vhjp-db.php`) · lớp đổi giá trị (`class-vhjp-doc.php`, đối
 *        chiếu thẳng với mã JavaScript gốc chạy bằng node) · lớp truy cập dữ liệu DUY NHẤT
 *        (`class-vhjp-nguon.php`) · sinh mã bản ghi (`class-vhjp-ma.php`) · đăng nhập PIN và
 *        phiên làm việc (`class-vhjp-auth.php`) · nhật ký thao tác (`class-vhjp-nhat-ky.php`)
 *        · danh mục (`class-vhjp-cau-hinh.php`) · cổng dịch `google.script.run`
 *        (`class-vhjp-cong.php`) · tính tiền dòng máy tiền (`class-vhjp-tinh.php`).
 * Chưa:  90 / 100 hàm máy chủ — `VHJP_Cong::chua_lam()` khai đủ tên, và
 *        `tools/test/kiem-jp-cong.php` đếm lại mỗi lượt chạy. Nặng nhất còn lại: tính tiền +
 *        17 cảnh báo W1–W17 · báo cáo · duyệt · ảnh · đối soát ngân hàng · kho hai tầng.
 *
 * ⚠️ GIAO DIỆN KHÔNG PHẢI VIẾT LẠI. 11 tệp HTML/JS của JP (≈11.000 dòng) gọi máy chủ qua đúng
 *    một chỗ (`srv()` trong `Js01_Core.html`) dựng trên `google.script.run`. `VHJP_Cong` dựng
 *    lại đúng API ấy, y lối bộ Chi Phí đã đi — anh Thắng gửi bản ấy làm mẫu 21/09/2026.
 *
 * Kích hoạt là dựng 23 bảng và mở hai đường dẫn:
 *      /jp           — màn nhân viên
 *      /jp-ke-toan   — màn kế toán
 * 🔴 GIAO DIỆN THẬT ĐÃ MANG SANG — 13 tệp, 13.712 dòng, chép NGUYÊN VĂN vào `giao-dien/`.
 *    Chúng chạy được vì `assets/js/gas-shim.js` dựng lại đúng API `google.script.run`. Không
 *    sửa một dòng nào: chừng nào hai bản còn chạy song song để so số, giao diện phải giống
 *    hệt — lệch một nút là lệch một thao tác, và không ai biết số khác nhau vì máy chủ tính
 *    khác hay vì người bấm khác.
 *
 * ⚠️ Màn hình bày ra được, nhưng phần lớn nút bấm sẽ báo "chưa chuyển": mới 12/100 lệnh máy
 *    chủ có thật. `VHJP_Cong::chua_lam()` khai đủ tên còn thiếu, và bài kiểm đếm lại mỗi lượt.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ BA NGUYÊN TẮC CỦA BẢN GỐC — GIỮ NGUYÊN KHI CHUYỂN.
 * Chép từ đầu `JP2_00_Config.gs`, và chúng là lý do bản gốc chạy được lâu tới vậy:
 *
 *   · Mọi con số tiền do MÁY CHỦ tính từ chỉ số máy — màn hình không gửi lên tổng.
 *     Tin con số màn hình gửi lên là ai sửa được trình duyệt thì sửa được doanh thu.
 *   · Ghi chỉ đụng đúng ô cần ghi, luôn kèm nhật ký (bảng `vhjp_nhat_ky`).
 *   · Đọc một lần rồi giữ lại, không đọc trong vòng lặp.
 *
 * ⚠️ VÀ MỘT NGUYÊN TẮC NỮA, CỦA CHÍNH KHO NÀY: ĐỪNG ĐỂ KHOÁ HAY PIN TRONG MÃ NGUỒN. Repo công
 *    khai. Bản gốc JP làm đúng chuyện này — ID sheet để ở Script Properties chứ không viết vào
 *    mã, lý do ghi ngay tại chỗ: *"repo này đẩy lên git, ID lọt vào history là vĩnh viễn"*.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHJP_VERSION', '1.4.0' );
define( 'VHJP_FILE', __FILE__ );
define( 'VHJP_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHJP_URL', plugin_dir_url( __FILE__ ) );

require_once VHJP_DIR . 'includes/class-vhjp-db.php';
require_once VHJP_DIR . 'includes/class-vhjp-doc.php';
require_once VHJP_DIR . 'includes/class-vhjp-nguon.php';
require_once VHJP_DIR . 'includes/class-vhjp-ma.php';
require_once VHJP_DIR . 'includes/class-vhjp-nhat-ky.php';
require_once VHJP_DIR . 'includes/class-vhjp-auth.php';
require_once VHJP_DIR . 'includes/class-vhjp-cau-hinh.php';
require_once VHJP_DIR . 'includes/class-vhjp-tinh.php';
require_once VHJP_DIR . 'includes/class-vhjp-cong.php';
require_once VHJP_DIR . 'includes/class-vhjp-trang.php';
require_once VHJP_DIR . 'includes/class-vhjp-admin.php';
require_once VHJP_DIR . 'includes/class-vhjp-tu-cap-nhat.php';

/* Nối bộ tự cập nhật ngay từ bản đầu, dù bộ này chưa dựng trang nào.
   `tools/test/kiem-tu-cap-nhat.php` quét đủ MỌI bộ, nên bộ mới quên nối là bộ thử đỏ — cố ý,
   vì bộ đã lên hosting rồi mới nhớ ra thì phải vào wp-admin cài tay từng lần.
   ⚠️ Tiền tố tag phải bằng đúng tên thư mục (`vhcp-jp-v`) — đó là chỗ các bộ phân biệt bản của
      nhau; chép lớp từ bộ khác mà quên đổi là bộ này đi nhận bản của bộ kia. Đã xảy ra một lần. */
VHJP_TuCapNhat::init();

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KÍCH HOẠT: dựng bảng, và mở lại đường dẫn.
 *
 * ⚠️ `flush_rewrite_rules()` phải chạy SAU khi đã khai đường — không thì đường `/jp` chưa có
 *    trong bảng định tuyến của WordPress và mở ra là 404, dù plugin đã bật. Người ta sẽ tưởng
 *    plugin hỏng.
 * ⚠️ Và dựng lại bảng mỗi lần ĐỔI SỐ BẢN, không chỉ lúc kích hoạt: cập nhật plugin thì móc
 *    kích hoạt KHÔNG chạy, nên bảng mới thêm ở bản sau sẽ không bao giờ được tạo.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
register_activation_hook( __FILE__, 'vhjp_kich_hoat' );
function vhjp_kich_hoat() {
	VHJP_DB::install();
	/* Không có bước này thì bảng người dùng rỗng trơn, mà màn đăng nhập chỉ hỏi PIN — tức
	   KHÔNG AI VÀO ĐƯỢC, kể cả người vừa cài. Xem `cap_tai_khoan_dau()`. */
	VHJP_Auth::cap_tai_khoan_dau();
	VHJP_Trang::them_duong();
	flush_rewrite_rules();
	update_option( 'vhjp_db_ver', VHJP_VERSION );
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

VHJP_Trang::init();
VHJP_Admin::init();

add_action( 'plugins_loaded', 'vhjp_co_the_nang', 20 );
function vhjp_co_the_nang() {
	if ( get_option( 'vhjp_db_ver' ) === VHJP_VERSION ) { return; }
	VHJP_DB::install();
	update_option( 'vhjp_db_ver', VHJP_VERSION );
	/* Đổi bản thì đường dẫn có thể đã khác — mở lại một lượt cho chắc. */
	add_action( 'init', 'flush_rewrite_rules', 99 );
}
