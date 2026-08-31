<?php
/**
 * Plugin Name:       Nội Bộ K&H
 * Description:       Trang trao đổi nội bộ: bảng tin, bình luận, thả tim — dùng chung PIN với hệ chấm công.
 * Version:           1.13.0
 * Author:            K&H
 * Requires at least: 5.6
 * Requires PHP:      7.2
 *
 * 🔴 DÙNG CHUNG PIN VỚI HỆ CHẤM CÔNG, KHÔNG CẤP TÀI KHOẢN WORDPRESS.
 *    240 người mà cấp tài khoản WordPress là cấp 240 đường vào phần quản trị website. Ai đăng
 *    nhập được trạm chấm công thì đăng nhập được đây — đúng một mã PIN cho cả hai.
 *
 * ⚠️ Plugin này cài ĐỘC LẬP với plugin chấm công, nên KHÔNG được giả định lớp bên kia có mặt.
 *    Thiếu nó thì trang nói thẳng "chưa cài plugin Chấm Công" chứ không trắng trang.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHNB_VERSION', '1.13.0' );
define( 'VHNB_DIR', plugin_dir_path( __FILE__ ) );

require_once VHNB_DIR . 'includes/class-vhnb-db.php';
require_once VHNB_DIR . 'includes/class-vhnb-quyen.php';
require_once VHNB_DIR . 'includes/class-vhnb-nhom.php';
require_once VHNB_DIR . 'includes/class-vhnb-bao.php';
require_once VHNB_DIR . 'includes/class-vhnb-anh.php';
require_once VHNB_DIR . 'includes/class-vhnb-bai.php';
require_once VHNB_DIR . 'includes/class-vhnb-tin.php';
require_once VHNB_DIR . 'includes/class-vhnb-trang.php';
require_once VHNB_DIR . 'includes/class-vhnb-admin.php';

register_activation_hook( __FILE__, 'vhnb_kich_hoat' );
function vhnb_kich_hoat() {
	VHNB_DB::dung_bang();
	/* Luật đường `/noi-bo/` chỉ có hiệu lực sau khi WordPress ghi lại bộ luật. Không đánh dấu ở
	   đây thì trang mới cài vào là 404, mà chẳng có gì chỉ ra vì sao — phải vào Cài đặt →
	   Đường dẫn tĩnh bấm Lưu một cái thì mới chạy. */
	update_option( 'vhnb_rw', 1 );
}

add_action( 'plugins_loaded', function () {
	/* Bảng có thể chưa dựng nếu plugin được bật bằng cách chép thư mục (hook kích hoạt không
	   chạy). Dò một lần theo phiên bản, rẻ hơn hẳn so với gọi dbDelta mỗi lượt tải trang. */
	if ( get_option( 'vhnb_db' ) !== VHNB_VERSION ) {
		VHNB_DB::dung_bang();
		update_option( 'vhnb_db', VHNB_VERSION );
		update_option( 'vhnb_rw', 1 );
	}
} );

add_action( 'init', array( 'VHNB_Trang', 'init' ) );
add_action( 'init', array( 'VHNB_Admin', 'init' ) );

/**
 * 🔴 CỬA CHO CHAT MINI — `admin-ajax.php`, KHÔNG PHẢI TRANG `/noi-bo/`.
 *
 * Anh Thắng 30/08/2026: *"bổ sung tab chat mini bên dưới để chat với thành viên"* — kiểu tự cập
 * nhật, nên trình duyệt phải hỏi máy chủ nhiều lần một phút mà KHÔNG được tải lại cả trang HTML.
 * `admin-ajax.php` là cửa có sẵn của WordPress cho đúng việc này.
 *
 * ⚠️ CẦN CẢ `wp_ajax_` LẪN `wp_ajax_nopriv_`. 240 người ở đây KHÔNG có tài khoản WordPress (xem
 *    đầu tệp) — với WordPress họ luôn là khách "chưa đăng nhập", dù đã gõ đúng PIN của hệ chấm
 *    công. Thiếu nhánh `nopriv_` thì WordPress tự trả về lỗi trước khi lời gọi tới được
 *    `VHNB_Trang::ajax_tin()`, và chat mini chỉ chạy được cho đúng một tài khoản: admin của
 *    chính website WordPress.
 */
add_action( 'wp_ajax_vhnb_tin', array( 'VHNB_Trang', 'ajax_tin' ) );
add_action( 'wp_ajax_nopriv_vhnb_tin', array( 'VHNB_Trang', 'ajax_tin' ) );

/* Ghi lại bộ luật đường ở ưu tiên 99 — SAU khi luật của trang đã được khai ở ưu tiên mặc định.
   Ghi trước là ghi một bộ luật chưa có `/noi-bo/` trong đó. */
add_action( 'init', 'vhnb_ghi_luat_duong', 99 );
function vhnb_ghi_luat_duong() {
	if ( ! get_option( 'vhnb_rw' ) ) { return; }
	delete_option( 'vhnb_rw' );
	flush_rewrite_rules( false );
}
