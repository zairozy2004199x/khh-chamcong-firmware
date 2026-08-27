<?php
/**
 * Plugin Name:       Nội Bộ K&H
 * Description:       Trang trao đổi nội bộ: bảng tin, bình luận, thả tim — dùng chung PIN với hệ chấm công.
 * Version:           1.5.0
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

define( 'VHNB_VERSION', '1.5.0' );
define( 'VHNB_DIR', plugin_dir_path( __FILE__ ) );

require_once VHNB_DIR . 'includes/class-vhnb-db.php';
require_once VHNB_DIR . 'includes/class-vhnb-quyen.php';
require_once VHNB_DIR . 'includes/class-vhnb-nhom.php';
require_once VHNB_DIR . 'includes/class-vhnb-bao.php';
require_once VHNB_DIR . 'includes/class-vhnb-anh.php';
require_once VHNB_DIR . 'includes/class-vhnb-bai.php';
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

/* Ghi lại bộ luật đường ở ưu tiên 99 — SAU khi luật của trang đã được khai ở ưu tiên mặc định.
   Ghi trước là ghi một bộ luật chưa có `/noi-bo/` trong đó. */
add_action( 'init', 'vhnb_ghi_luat_duong', 99 );
function vhnb_ghi_luat_duong() {
	if ( ! get_option( 'vhnb_rw' ) ) { return; }
	delete_option( 'vhnb_rw' );
	flush_rewrite_rules( false );
}
