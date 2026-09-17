<?php
define( 'WP_INSTALLING', true );
require_once '/tmp/wpsite/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
	$r = wp_install( 'K&H Matrix', 'admin', 'admin@khmatrix.test', true, '', 'MatKhau!2026' );
	echo "cài xong: user " . $r['user_id'] . "\n";
} else {
	echo "đã cài rồi\n";
}
/* bật plugin */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$r = activate_plugin( 'khh-platform/khh-platform.php' );
echo is_wp_error( $r ) ? ( 'LỖI BẬT PLUGIN: ' . $r->get_error_message() . "\n" ) : "đã bật plugin\n";
echo 'phiên bản: ' . get_option( 'khh_version' ) . "\n";
global $wpdb;
echo 'bảng dữ liệu: ' . $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}khh_docs'" ) . "\n";
echo 'số bản ghi: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}khh_docs" ) . "\n";
