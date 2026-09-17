<?php
/**
 * Plugin Name:       App Chấm Công K&H
 * Plugin URI:        https://khh.vn/
 * Description:       Biến trang chấm công online thành một app cài được lên màn hình chính của iPhone/Android (PWA): chạy toàn màn hình, có biểu tượng riêng, mở được cả khi mạng chập chờn.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            K&H
 * Text Domain:       vhcp-cc-app
 *
 * =================================================================================================
 * VÌ SAO LÀ MỘT PLUGIN RIÊNG, KHÔNG NHÉT VÀO `vhcp-cham-cong`
 * =================================================================================================
 * Anh Thắng 16/09/2026: *"Tạo app chấm công online … App iphone qua PWA"*.
 *
 * Nghiệp vụ chấm công đã xong và đang chạy trong `vhcp-cham-cong` (trang `/cham-cong-online`):
 * đăng nhập PIN, chụp ảnh đóng dấu giờ MÁY CHỦ, GPS, đối chiếu khuôn mặt, bảng công tháng. Bộ này
 * KHÔNG viết lại một dòng nào trong số đó — nó chỉ khoác thêm lớp vỏ để iPhone coi trang ấy là một
 * app thật. Hai lý do tách ra:
 *
 * 1. 🔴 SỐ BẢN. `vhcp-cham-cong` đang được vá song song ở nhánh khác (§3 của CLAUDE.md). Thêm việc
 *    vào đó là hai bên cùng nhắm một con số, và WordPress chỉ so được CON SỐ — cài đè bên nào cũng
 *    mất việc của bên kia. Tên plugin khác thì số bản đi đường riêng, không bao giờ đụng.
 * 2. GỠ RA ĐƯỢC. Tắt plugin này thì `/cham-cong-online` vẫn nguyên vẹn như trước, không sứt mẻ gì.
 *    Lớp vỏ hỏng không bao giờ kéo theo chỗ ghi giờ công.
 *
 * =================================================================================================
 * 🔴 KHÔNG DỰNG ĐƯỜNG GHI THỨ HAI
 * =================================================================================================
 * Trang `/cc` KHÔNG tự vẽ màn chấm công. Nó gọi `VHCC_Tram::render()` — đúng cái hàm mà
 * `/cham-cong-online` gọi — rồi chèn mấy thẻ PWA vào phần <head> của kết quả. Nhờ vậy:
 *
 *   · bốn ràng buộc của bản gốc (giờ máy chủ in lên ảnh · thu ảnh về 720px · hỏi cơ sở đúng lúc
 *     lưu · khoá nút sau khi bấm) tự động còn nguyên, vì vẫn là chính mã ấy chạy;
 *   · bên kia sửa giao diện thì app thấy ngay, không phải chép lại lần nữa.
 *
 * Đây đúng bài học §6/§8 của CLAUDE.md: luật chép sang chỗ thứ hai là sớm muộn hai chỗ lệch nhau ở
 * đúng nơi không ai kịp thấy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCAPP_VERSION', '1.1.0' );
define( 'CCAPP_FILE', __FILE__ );
define( 'CCAPP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCAPP_URL', plugin_dir_url( __FILE__ ) );

require_once CCAPP_DIR . 'includes/class-ccapp-khoa.php';
require_once CCAPP_DIR . 'includes/class-ccapp-nhac.php';
require_once CCAPP_DIR . 'includes/class-ccapp-app.php';
require_once CCAPP_DIR . 'includes/class-ccapp-cai-dat.php';
require_once CCAPP_DIR . 'includes/class-ccapp-tu-cap-nhat.php';

CCAPP_App::init();
CCAPP_Nhac::init();
CCAPP_CaiDat::init();
CCAPP_TuCapNhat::init();

/* ─────────────────────────────────────────────────────────────────────────────────────────────
 * ĐƯỜNG DẪN ĐẸP CẦN MỘT LƯỢT NẠP LẠI LUẬT.
 *
 * ⚠️ `flush_rewrite_rules()` đặt ở đây, trong hook kích hoạt, chứ KHÔNG gọi mỗi lượt tải trang.
 *    Gọi mỗi lượt là ghi lại một ô option nặng trong DB ở mọi request — chậm cả site, và cái giá
 *    ấy không đổi lấy gì cả vì luật có đổi đâu.
 *
 * ⚠️ Gỡ plugin thì cũng phải nạp lại, nếu không `/cc` còn nằm trong bảng luật mà không ai trả lời,
 *    và khách nhận một trang trắng thay vì trang 404 tử tế.
 * ───────────────────────────────────────────────────────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
	CCAPP_App::luat();
	CCAPP_Nhac::dung_bang();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	/* ⚠️ GỠ LỊCH QUÉT KHI TẮT PLUGIN. Không gỡ thì WP-Cron còn giữ một sự kiện trỏ tới một hàm
	   không còn ai khai — mỗi lượt chạy là một dòng lỗi trong nhật ký, và nó không bao giờ tự
	   hết. ⚠️ Bảng máy thì GIỮ NGUYÊN: tắt plugin một lúc rồi bật lại mà mất sổ máy là cả công
	   ty phải cấp quyền thông báo lại từ đầu, mà iOS chỉ hỏi quyền ấy MỘT LẦN — từ chối hay mất
	   là phải vào Cài đặt của máy mới bật lại được. */
	$hen = wp_next_scheduled( CCAPP_Nhac::HOOK );
	if ( $hen ) { wp_unschedule_event( $hen, CCAPP_Nhac::HOOK ); }
	flush_rewrite_rules();
} );
