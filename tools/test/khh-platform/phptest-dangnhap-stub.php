<?php
/* Kiểm thử màn đăng nhập ngay trên nền tảng: đúng/sai mật khẩu, khoá khi dò, và lỗi xoá mã PIN */
define( 'ABSPATH', '/tmp/fakewp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );

$GLOBALS['_trans']    = array();
$GLOBALS['_opts']     = array();
$GLOBALS['_users']    = array();
$GLOBALS['_logged']   = 0;
$GLOBALS['_redirect'] = '';
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

function add_action() {}
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_user( $u, $s = false ) { return preg_replace( '/[^a-zA-Z0-9._\-]/', '', (string) $u ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) ); }
function wp_unslash( $v ) { return $v; }
function esc_url_raw( $u ) { return (string) $u; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function home_url( $p = '' ) { return 'https://site.test' . $p; }
function wp_login_url( $r = '' ) { return 'https://site.test/wp-login.php'; }
function get_bloginfo( $x = '' ) { return 'Công ty K&H'; }
function bloginfo( $x = '' ) { echo 'utf-8'; }
function language_attributes() { echo 'lang="vi"'; }
function nocache_headers() {}
function is_ssl() { return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $d = false ) { return isset( $GLOBALS['_opts'][ $k ] ) ? $GLOBALS['_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['_opts'][ $k ] = $v; return true; }
function get_transient( $k ) { return isset( $GLOBALS['_trans'][ $k ] ) ? $GLOBALS['_trans'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['_trans'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['_trans'][ $k ] ); return true; }
function wp_verify_nonce( $n, $a ) { return 'nonce-dung' === $n ? 1 : false; }
function wp_nonce_field( $a = '', $n = '' ) { echo '<input type="hidden" name="' . $n . '" value="nonce-dung">'; }
function check_admin_referer( $a = '' ) { return true; }
function wp_set_current_user( $id ) { $GLOBALS['_logged'] = $id; }
function wp_logout() { $GLOBALS['_logged'] = 0; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_Error {
	public $c, $m;
	function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	function get_error_message() { return $this->m; }
}
function wp_signon( $c, $ssl = false ) {
	foreach ( $GLOBALS['_users'] as $u ) {
		if ( $u->user_login === $c['user_login'] && $u->pass === $c['user_password'] ) {
			return $u;
		}
	}
	return new WP_Error( 'sai', 'Sai.' );
}
function wp_safe_redirect( $u ) {
	/* Bắt chước hàng thật: chỉ cho chuyển trong cùng tên miền, ngoài ra rơi về wp-admin. */
	$host = wp_parse_url( $u, PHP_URL_HOST );
	if ( $host && 'site.test' !== $host ) {
		$u = 'https://site.test/wp-admin/';
	}
	$GLOBALS['_redirect'] = $u;
	throw new Exception( 'REDIRECT' );
}
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function khh_settings() {
	$d = array( 'pin_enabled' => 0, 'pins' => array(), 'logo' => '', 'default_role' => 'staff' );
	return wp_parse_args( get_option( 'khh_settings', array() ), $d );
}

require_once KHH_DIR . 'dang-nhap.php';
