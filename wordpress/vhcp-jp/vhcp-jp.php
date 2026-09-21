<?php
/**
 * Plugin Name:       JP Capsule (K&H)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Báo cáo JP Capsule chạy THẲNG trên host: nhân viên nhập báo cáo từ chỉ số máy, kế toán duyệt hai phần, đối soát ngân hàng, kho hai tầng. Không Apps Script, không Google Sheets.
 * Version:           1.0.0
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
 *        (`class-vhjp-cong.php`).
 * Chưa:  90 / 100 hàm máy chủ — `VHJP_Cong::chua_lam()` khai đủ tên, và
 *        `tools/test/kiem-jp-cong.php` đếm lại mỗi lượt chạy. Nặng nhất còn lại: tính tiền +
 *        17 cảnh báo W1–W17 · báo cáo · duyệt · ảnh · đối soát ngân hàng · kho hai tầng.
 *
 * ⚠️ GIAO DIỆN KHÔNG PHẢI VIẾT LẠI. 11 tệp HTML/JS của JP (≈11.000 dòng) gọi máy chủ qua đúng
 *    một chỗ (`srv()` trong `Js01_Core.html`) dựng trên `google.script.run`. `VHJP_Cong` dựng
 *    lại đúng API ấy, y lối bộ Chi Phí đã đi — anh Thắng gửi bản ấy làm mẫu 21/09/2026.
 *
 * Nên plugin này CỐ Ý chưa khai móc kích hoạt và chưa dựng trang nào: cài nửa vời vào site thật
 * là tạo 23 bảng rỗng rồi để đó, và lần sau không ai nhớ bảng ấy từ đâu ra. Khi nào có tầng đọc
 * ghi thì mở.
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

define( 'VHJP_VERSION', '1.0.0' );
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
require_once VHJP_DIR . 'includes/class-vhjp-cong.php';
require_once VHJP_DIR . 'includes/class-vhjp-tu-cap-nhat.php';

/* Nối bộ tự cập nhật ngay từ bản đầu, dù bộ này chưa dựng trang nào.
   `tools/test/kiem-tu-cap-nhat.php` quét đủ MỌI bộ, nên bộ mới quên nối là bộ thử đỏ — cố ý,
   vì bộ đã lên hosting rồi mới nhớ ra thì phải vào wp-admin cài tay từng lần.
   ⚠️ Tiền tố tag phải bằng đúng tên thư mục (`vhcp-jp-v`) — đó là chỗ các bộ phân biệt bản của
      nhau; chép lớp từ bộ khác mà quên đổi là bộ này đi nhận bản của bộ kia. Đã xảy ra một lần. */
VHJP_TuCapNhat::init();

/* Chưa khai `register_activation_hook` — xem khối "ĐANG DỰNG DỞ" ở trên. */
