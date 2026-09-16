<?php
/**
 * Gỡ plugin. Mặc định GIỮ NGUYÊN dữ liệu để không mất việc của công ty.
 * Muốn xoá sạch bảng dữ liệu khi gỡ, thêm dòng sau vào wp-config.php:
 *     define( 'KHH_DELETE_DATA', true );
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( defined( 'KHH_DELETE_DATA' ) && KHH_DELETE_DATA ) {
	global $wpdb;
	$table = $wpdb->prefix . 'khh_docs';
	$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore
	delete_option( 'khh_version' );
}
