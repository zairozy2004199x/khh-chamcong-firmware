<?php
/** Tiện ích chung của plugin Ủy Nhiệm Chi. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_Util {

	public static function tz() {
		$tz = get_option( 'khunc_timezone' );
		if ( ! $tz ) { $tz = 'Asia/Bangkok'; }
		try { return new DateTimeZone( $tz ); } catch ( Exception $e ) { return new DateTimeZone( 'Asia/Bangkok' ); }
	}

	/** 'Y-m-d H:i:s' theo giờ app. */
	public static function now_sql() {
		return ( new DateTime( 'now', self::tz() ) )->format( 'Y-m-d H:i:s' );
	}

	/** ISO 8601 để giao diện parse bằng Date(). */
	public static function iso( $sql ) {
		if ( ! $sql || $sql === '0000-00-00 00:00:00' ) { return ''; }
		try {
			$d = new DateTime( (string) $sql, self::tz() );
			return $d->format( DATE_ATOM );
		} catch ( Exception $e ) { return (string) $sql; }
	}

	public static function s( $v, $max = 1000 ) {
		if ( $v === null ) { return ''; }
		if ( is_array( $v ) || is_object( $v ) ) { $v = wp_json_encode( $v ); }
		$s = trim( (string) $v );
		return mb_substr( $s, 0, $max );
	}

	public static function num( $v ) {
		if ( $v === null || $v === '' ) { return 0.0; }
		if ( is_numeric( $v ) ) { return (float) $v; }
		$s = preg_replace( '/[^0-9,.\-]/', '', (string) $v );
		$s = str_replace( '.', '', $s );
		$s = str_replace( ',', '.', $s );
		return is_numeric( $s ) ? (float) $s : 0.0;
	}

	/** Kỳ 'yyyy-mm' từ chuỗi hoặc {month, year}. */
	public static function ky( $p ) {
		if ( is_array( $p ) && isset( $p['year'] ) ) {
			return sprintf( '%04d-%02d', (int) $p['year'], (int) ( isset( $p['month'] ) ? $p['month'] : 1 ) );
		}
		$s = trim( (string) $p );
		return preg_match( '/^\d{4}-\d{2}$/', $s ) ? $s : '';
	}

	public static function json_decode_arr( $s, $default = array() ) {
		if ( $s === null || $s === '' ) { return $default; }
		$j = json_decode( (string) $s, true );
		return is_array( $j ) ? $j : $default;
	}

	public static function ok( $extra = array() ) {
		return array_merge( array( 'ok' => true ), (array) $extra );
	}

	public static function err( $msg, $extra = array() ) {
		return array_merge( array( 'ok' => false, 'error' => (string) $msg ), (array) $extra );
	}

	public static function uid( $prefix = 'ci' ) {
		return $prefix . base_convert( (string) time(), 10, 36 ) . wp_generate_password( 6, false, false );
	}
}
