<?php
/**
 * Gỡ plugin: xoá bảng đơn và cài đặt.
 *
 * @package do-ve-re
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$table = $wpdb->prefix . 'dvr_orders';
$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore
delete_option( 'dvr_settings' );
