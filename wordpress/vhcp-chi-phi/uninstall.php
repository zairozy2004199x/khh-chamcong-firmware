<?php
/**
 * Xóa plugin: MẶC ĐỊNH GIỮ NGUYÊN DỮ LIỆU (đơn, chi phí, cấu hình).
 *
 * Chỉ khi nào cố ý muốn dọn sạch thì thêm dòng này vào wp-config.php trước khi xóa:
 *     define( 'VHCP_DELETE_DATA_ON_UNINSTALL', true );
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

if ( ! defined( 'VHCP_DELETE_DATA_ON_UNINSTALL' ) || ! VHCP_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;
$tables = array( 'don', 'tamung', 'chiphi', 'so_chi', 'da_index', 'da_line', 'mk_don', 'mk_line', 'bp_index', 'bp_line', 'hopdong', 'cfg', 'meta', 'log', 'session' );
foreach ( $tables as $t ) {
	$name = $wpdb->prefix . 'vhcp_' . $t;
	$wpdb->query( "DROP TABLE IF EXISTS `$name`" );
}
delete_option( 'vhcp_db_version' );
delete_option( 'vhcp_ver' );
delete_option( 'vhcp_slug' );
delete_option( 'vhcp_slug_hd' );
delete_option( 'vhcp_timezone' );
delete_option( 'vhcp_sso_secret' );
delete_option( 'vhcp_flush_rewrite' );
