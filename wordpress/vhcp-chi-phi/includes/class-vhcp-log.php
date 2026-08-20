<?php
/** NHẬT KÝ HOẠT ĐỘNG — thay sheet NhatKy. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Log {

	public static function log_action( $rec ) {
		global $wpdb;
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? (string) $rec[ $k ] : ''; };
		$wpdb->insert( VHCP_DB::t( 'log' ), array(
			'tg'        => VHCP_Util::now_sql(),
			'nguoi'     => $g( 'actor' ),
			'vai_tro'   => $g( 'role' ),
			'hanh_dong' => $g( 'action' ),
			'doi_tuong' => $g( 'target' ),
			'chi_tiet'  => $g( 'detail' ),
		) );
		return VHCP_Util::ok();
	}

	public static function get_log( $opts = array() ) {
		global $wpdb;
		$opts  = (array) $opts;
		$limit = isset( $opts['limit'] ) ? (int) $opts['limit'] : 800;
		if ( $limit <= 0 || $limit > 5000 ) { $limit = 800; }
		$t    = VHCP_DB::t( 'log' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'tg'       => VHCP_Util::fmt_dt( $r['tg'] ),
				'nguoi'    => $r['nguoi'],
				'vaiTro'   => $r['vai_tro'],
				'hanhDong' => $r['hanh_dong'],
				'doiTuong' => $r['doi_tuong'],
				'chiTiet'  => (string) $r['chi_tiet'],
			);
		}
		$q = isset( $opts['q'] ) ? mb_strtolower( trim( (string) $opts['q'] ) ) : '';
		if ( $q !== '' ) {
			$items = array_values( array_filter( $items, function ( $x ) use ( $q ) {
				$hay = mb_strtolower( $x['nguoi'] . ' ' . $x['vaiTro'] . ' ' . $x['hanhDong'] . ' ' . $x['doiTuong'] . ' ' . $x['chiTiet'] );
				return mb_strpos( $hay, $q ) !== false;
			} ) );
		}
		return VHCP_Util::ok( array( 'items' => $items ) );
	}
}
