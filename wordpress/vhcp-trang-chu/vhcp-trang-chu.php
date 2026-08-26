<?php
/**
 * Plugin Name:       Trang Vận Hành K&H
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Một trang duy nhất để vào mọi app của K&H — chấm công, chi phí, hợp đồng. Đường dẫn lấy thẳng từ chính mấy app đó nên đổi đường dẫn bên kia thì bên này tự theo.
 * Version:           1.4.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 */

/**
 * 🔴 VÌ SAO LÀ PLUGIN CHỨ KHÔNG PHẢI MỘT TRANG WordPress DÁN TAY
 *
 * Dán tay ba đường dẫn vào một trang thì hôm nào anh Thắng đổi đường dẫn của app chi phí
 * (`Cài đặt → Đường dẫn app`), trang này vẫn trỏ về đường cũ — bấm vào ra 404, mà không có gì
 * báo. Ở đây đường dẫn LẤY THẲNG từ chính app đó (`VHCP_App::app_url()`…), nên không lệch được.
 *
 * Và app nào CHƯA CÀI thì hiện xám kèm chữ "chưa cài", không phải một liên kết chết.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VHTC_VERSION', '1.4.0' );
define( 'VHTC_DIR', plugin_dir_path( __FILE__ ) );

require_once VHTC_DIR . 'includes/class-vhtc-trang.php';
require_once VHTC_DIR . 'includes/class-vhtc-admin.php';

add_action( 'init', array( 'VHTC_Trang', 'init' ), 5 );
add_action( 'template_redirect', array( 'VHTC_Trang', 'co_phai_trang_nay' ) );
add_action( 'admin_menu', array( 'VHTC_Admin', 'menu' ) );
add_action( 'admin_init', array( 'VHTC_Admin', 'handle_post' ) );

register_activation_hook( __FILE__, function () {
	update_option( 'vhtc_flush', 1 );
} );

/* Nạp lại luật đường dẫn SAU khi luật đã được khai (init ưu tiên 5), không phải trước —
   flush trước khi khai là flush một bảng luật còn thiếu, và đường dẫn mới ra 404. */
add_action( 'init', function () {
	if ( ! get_option( 'vhtc_flush' ) ) { return; }
	delete_option( 'vhtc_flush' );
	flush_rewrite_rules();
}, 99 );
