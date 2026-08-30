<?php
/**
 * Plugin Name:       Dự Án & Tiến Độ K&H
 * Description:       Quy trình công việc: nhận hợp đồng → phương án → chốt ngày → bàn giao bộ phận → tiến độ → mở cửa. Nối với hệ chi phí, dùng chung PIN với hệ chấm công.
 * Version:           1.1.0
 * Author:            K&H
 * Requires at least: 5.6
 * Requires PHP:      7.2
 *
 * =============================================================================================
 * 🔴 DÙNG CHUNG PIN VỚI HỆ CHẤM CÔNG, KHÔNG CẤP TÀI KHOẢN WORDPRESS.
 * =============================================================================================
 * 240 người mà cấp tài khoản WordPress là cấp 240 đường vào phần quản trị website. Trang này
 * cắm vào `VHCC_Phien` — lõi phiên dùng chung của cả hệ; xem
 * `docs/TRANG-MOI-DUNG-CHUNG-DANG-NHAP.md`.
 *
 * ⚠️ CÀI ĐỘC LẬP với ba plugin kia, nên KHÔNG được giả định lớp bên nào có mặt:
 *      · thiếu plugin Chấm công  -> nói thẳng "chưa cài", không trắng trang;
 *      · thiếu plugin Chi phí    -> khối tiền nói "chưa gom được", KHÔNG hiện số 0;
 *      · thiếu plugin Ghế        -> mất mấy dòng chân trang, thế thôi.
 *    Mọi lời gọi chéo đều gác `method_exists` CÙNG THÂN HÀM (luật `tools/test/kiem-goi-cheo.php`).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHDA_VERSION', '1.1.0' );
define( 'VHDA_DIR', plugin_dir_path( __FILE__ ) );

require_once VHDA_DIR . 'includes/class-vhda-db.php';
require_once VHDA_DIR . 'includes/class-vhda-luong.php';
require_once VHDA_DIR . 'includes/class-vhda-quyen.php';
require_once VHDA_DIR . 'includes/class-vhda-du-an.php';
require_once VHDA_DIR . 'includes/class-vhda-tien.php';
require_once VHDA_DIR . 'includes/class-vhda-trang.php';

register_activation_hook( __FILE__, 'vhda_kich_hoat' );
function vhda_kich_hoat() {
	VHDA_DB::dung_bang();
	/* Luật đường `/du-an/` chỉ có hiệu lực sau khi WordPress ghi lại bộ luật. Không đánh dấu ở
	   đây thì trang mới cài vào là 404, mà chẳng có gì chỉ ra vì sao. */
	update_option( 'vhda_rw', 1 );
}

add_action( 'plugins_loaded', function () {
	/* Bảng có thể chưa dựng nếu plugin được bật bằng cách chép thư mục (hook kích hoạt không
	   chạy). Dò một lần theo phiên bản, rẻ hơn hẳn gọi dbDelta mỗi lượt tải trang. */
	if ( get_option( 'vhda_db' ) !== VHDA_VERSION ) {
		VHDA_DB::dung_bang();
		update_option( 'vhda_db', VHDA_VERSION );
		update_option( 'vhda_rw', 1 );
	}
} );

add_action( 'init', array( 'VHDA_Trang', 'init' ) );

add_action( 'wp_loaded', function () {
	if ( get_option( 'vhda_rw' ) ) {
		flush_rewrite_rules( false );
		delete_option( 'vhda_rw' );
	}
} );
