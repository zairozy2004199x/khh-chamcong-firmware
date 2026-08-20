<?php
/**
 * Thay cho PropertiesService (Document/Script Properties) của Apps Script:
 * soDuDauKy, da_ndlist_v1, cfg_undo, daPay_<mã>, daApp_<mã>, SSO_SECRET, cờ seed…
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Meta {

	public static function get( $key, $default = null ) {
		global $wpdb;
		$t = VHCP_DB::t( 'meta' );
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT v FROM $t WHERE k=%s", $key ) );
		return ( $v === null ) ? $default : $v;
	}

	public static function set( $key, $val ) {
		global $wpdb;
		$t = VHCP_DB::t( 'meta' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO $t (k,v) VALUES (%s,%s) ON DUPLICATE KEY UPDATE v=VALUES(v)", $key, (string) $val ) );
	}

	public static function del( $key ) {
		global $wpdb;
		$wpdb->delete( VHCP_DB::t( 'meta' ), array( 'k' => $key ) );
	}

	public static function get_json( $key, $default = array() ) {
		$raw = self::get( $key );
		if ( $raw === null || $raw === '' ) { return $default; }
		$o = json_decode( $raw, true );
		return ( json_last_error() === JSON_ERROR_NONE ) ? $o : $default;
	}

	public static function set_json( $key, $val ) {
		self::set( $key, wp_json_encode( $val ) );
	}
}
