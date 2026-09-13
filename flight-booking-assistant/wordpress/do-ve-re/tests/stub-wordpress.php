<?php
/**
 * Bộ giả lập vài hàm WordPress, chỉ đủ để chạy test logic thuần PHP của plugin
 * mà không cần cài cả WordPress. Không dùng khi chạy thật.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['dvr_options'] = array();
$GLOBALS['dvr_mails']   = array();

function get_option( $k, $mac = false ) {
	return array_key_exists( $k, $GLOBALS['dvr_options'] ) ? $GLOBALS['dvr_options'][ $k ] : $mac;
}
function update_option( $k, $v, $auto = true ) {
	$GLOBALS['dvr_options'][ $k ] = $v;
	return true;
}
function wp_parse_args( $a, $mac = array() ) {
	return array_merge( $mac, (array) $a );
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_email( $s ) {
	return trim( (string) $s );
}
function is_email( $s ) {
	return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL );
}
function current_time( $type = 'mysql' ) {
	return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' );
}
function wp_json_encode( $v ) {
	return json_encode( $v, JSON_UNESCAPED_UNICODE );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function esc_url( $s ) {
	return (string) $s;
}
function get_bloginfo( $x = '' ) {
	return 'Vé K&H';
}
function get_permalink( $id ) {
	return 'https://ve.knh.vn/dat-ve/';
}
function date_i18n( $f, $t ) {
	return gmdate( $f, $t );
}
function add_query_arg( ...$a ) {
	// WordPress nhận cả add_query_arg( 'k', 'v', $url ) lẫn add_query_arg( array(...), $url )
	if ( count( $a ) >= 3 ) {
		$args = array( $a[0] => $a[1] );
		$url  = $a[2];
	} else {
		$args = (array) $a[0];
		$url  = $a[1];
	}
	return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args );
}
function wp_mail( $to, $subject, $html ) {
	$GLOBALS['dvr_mails'][] = compact( 'to', 'subject', 'html' );
	return true;
}
function add_filter( ...$a ) {}
function remove_filter( ...$a ) {}
function add_action( ...$a ) {}
function apply_filters( $tag, $v ) {
	return $v;
}
function get_transient( $k ) {
	return false;
}
function set_transient( $k, $v, $t = 0 ) {
	return true;
}

class WP_Error {
	private $code;
	private $msg;
	public function __construct( $code = '', $msg = '' ) {
		$this->code = $code;
		$this->msg  = $msg;
	}
	public function get_error_message() {
		return $this->msg;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
