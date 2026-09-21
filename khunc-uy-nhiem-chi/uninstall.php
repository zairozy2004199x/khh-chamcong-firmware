<?php
/**
 * GỠ PLUGIN — chỉ chạy khi bấm "Xoá" ở màn Plugin, KHÔNG chạy khi tắt plugin.
 *
 * Mặc định GIỮ NGUYÊN bảng dữ liệu: gỡ nhầm rồi cài lại là còn đủ trạng thái đã đánh dấu.
 * Muốn xoá sạch thì bật "Xoá hết dữ liệu khi gỡ plugin" ở Cài đặt trước khi gỡ.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

if ( ! get_option( 'khunc_xoa_khi_go' ) ) { return; }

global $wpdb;
foreach ( array( 'nguoi_dung', 'phien', 'theo_doi', 'kho', 'nhat_ky' ) as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'khunc_' . $t ); // phpcs:ignore
}
foreach ( array( 'khunc_db_version', 'khunc_ver', 'khunc_slug', 'khunc_ten_trang', 'khunc_timezone',
	'khunc_sso_secret', 'khunc_gh_nhanh', 'khunc_flush_rewrite', 'khunc_xoa_khi_go' ) as $o ) {
	delete_option( $o );
}
delete_transient( 'khunc_gh_ban_moi' );
