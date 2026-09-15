<?php
/**
 * Xoá plugin: MẶC ĐỊNH GIỮ NGUYÊN DỮ LIỆU (khoản chi phí, cấu hình kỳ, người dùng).
 * Muốn dọn sạch thì thêm vào wp-config.php trước khi xoá:
 *     define( 'KHBC_DELETE_DATA_ON_UNINSTALL', true );
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
if ( ! defined( 'KHBC_DELETE_DATA_ON_UNINSTALL' ) || ! KHBC_DELETE_DATA_ON_UNINSTALL ) { return; }

global $wpdb;
foreach ( array( 'nguoi_dung', 'phien', 'khoan', 'ky', 'nhat_ky' ) as $t ) {
	$name = $wpdb->prefix . 'khbc_' . $t;
	$wpdb->query( "DROP TABLE IF EXISTS `$name`" ); // phpcs:ignore
}
foreach ( array( 'khbc_db_version', 'khbc_ver', 'khbc_slug', 'khbc_ten_trang', 'khbc_timezone', 'khbc_sso_secret', 'khbc_flush_rewrite', 'khbc_gh_ban_moi' ) as $o ) {
	delete_option( $o );
}
