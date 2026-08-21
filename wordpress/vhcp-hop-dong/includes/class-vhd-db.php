<?php
/**
 * Bảng của plugin — chỉ MỘT bảng phiên đăng nhập.
 *
 * Không có bảng hợp đồng: dữ liệu hợp đồng ở Google Sheet, plugin không giữ bản sao nào.
 * Giữ bản sao là sinh ra hai nguồn sự thật, rồi sớm muộn lệch nhau mà không ai biết bên nào đúng.
 *
 * Bảng NGƯỜI DÙNG thì dùng chung với plugin Vận hành chi phí (xem class-vhd-auth.php) để nhân sự
 * chỉ khai một lần — thêm/xoá người ở hai nơi là kiểu lỗ hổng "xoá một nơi quên nơi kia".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_DB {

	const SCHEMA_VERSION = '1.0.0';

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'vhd_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE " . self::t( 'session' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			token CHAR(64) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			het_han DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY het_han (het_han)
		) $c" );

		update_option( 'vhd_db_version', self::SCHEMA_VERSION );
	}

	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
