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

$u = new stdClass();
$u->ID = 7; $u->user_login = 'mnnv2kvc0001'; $u->pass = 'Abc12345xy';
$GLOBALS['_users'][] = $u;

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-58s got=%s\n", $ok ? 'PASS' : 'FAIL', $name, var_export( $got, true ) );
}
function dat( $post ) { $_POST = $post; }
function thu( $post ) {
	dat( $post );
	try {
		return array( 'loi' => khh_login_xu_ly(), 'di' => '' );
	} catch ( Exception $e ) {
		return array( 'loi' => '', 'di' => $GLOBALS['_redirect'] );
	}
}

/* --- không gửi form thì không làm gì --- */
dat( array() );
t( 'không có form thì không báo lỗi', khh_login_xu_ly(), '' );

/* --- thiếu nonce --- */
$r = thu( array( 'khh_dn' => 1, 'log' => 'mnnv2kvc0001', 'pwd' => 'Abc12345xy' ) );
t( 'thiếu mã phiên thì từ chối', strpos( $r['loi'], 'Phiên nhập đã cũ' ) !== false, true );
t( '  không đăng nhập', $GLOBALS['_logged'], 0 );

/* --- bỏ trống ô --- */
$r = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => '', 'pwd' => '' ) );
t( 'bỏ trống thì nhắc nhập đủ', strpos( $r['loi'], 'Nhập đủ' ) !== false, true );

/* --- sai mật khẩu --- */
$r = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'mnnv2kvc0001', 'pwd' => 'saibet' ) );
t( 'sai mật khẩu bị từ chối', strpos( $r['loi'], 'không đúng' ) !== false, true );
t( '  vẫn chưa đăng nhập', $GLOBALS['_logged'], 0 );
t( '  câu báo KHÔNG hé lộ tên đăng nhập có thật hay không',
	strpos( $r['loi'], 'không tồn tại' ) === false && strpos( $r['loi'], 'sai mật khẩu' ) === false, true );

/* --- tên không có thật: báo y hệt --- */
$r2 = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'khongcoai', 'pwd' => 'gicungduoc' ) );
t( 'tên lạ báo đúng câu như sai mật khẩu',
	substr( $r2['loi'], 0, 40 ) === substr( $r['loi'], 0, 40 ), true );

/* --- đúng thì vào --- */
khh_login_go_khoa();
$r = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'mnnv2kvc0001', 'pwd' => 'Abc12345xy' ) );
t( 'đúng mật khẩu thì chuyển vào nền tảng', $r['di'], 'https://site.test/?khh_app=1' );
t( '  đã đăng nhập đúng người', $GLOBALS['_logged'], 7 );
t( '  bộ đếm sai được xoá', khh_login_dem(), 0 );

/* --- khoá sau 8 lần sai --- */
$GLOBALS['_logged'] = 0;
khh_login_go_khoa();
for ( $i = 0; $i < 8; $i++ ) {
	thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'mnnv2kvc0001', 'pwd' => 'sai' . $i ) );
}
t( 'đếm đủ 8 lần sai', khh_login_dem(), 8 );
$r = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'mnnv2kvc0001', 'pwd' => 'Abc12345xy' ) );
t( 'khoá rồi thì mật khẩu ĐÚNG cũng không vào', strpos( $r['loi'], 'Chờ 15 phút' ) !== false, true );
t( '  vẫn chưa đăng nhập', $GLOBALS['_logged'], 0 );
khh_login_go_khoa();
$r = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'mnnv2kvc0001', 'pwd' => 'Abc12345xy' ) );
t( 'hết khoá thì vào lại được', $GLOBALS['_logged'], 7 );

/* --- nhắc số lần còn lại khi sắp bị khoá --- */
$GLOBALS['_logged'] = 0;
khh_login_go_khoa();
for ( $i = 0; $i < 6; $i++ ) {
	$r = thu( array( 'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung', 'log' => 'x', 'pwd' => 'y' ) );
}
t( 'gần bị khoá thì nhắc còn mấy lần', strpos( $r['loi'], 'Còn 2 lần' ) !== false, true );

/* --- chỉ nhận chuyển hướng về chính site --- */
khh_login_go_khoa();
$r = thu( array(
	'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung',
	'log' => 'mnnv2kvc0001', 'pwd' => 'Abc12345xy',
	've' => 'https://trang-la.example/cuop-phien',
) );
t( 'kẻ gian nhét tên miền lạ vào ô "ve" thì KHÔNG bị đưa ra ngoài',
	$r['di'], 'https://site.test/wp-admin/' );

/* đường dẫn trong cùng site thì vẫn đi đúng chỗ */
khh_login_go_khoa();
$GLOBALS['_logged'] = 0;
$r = thu( array(
	'khh_dn' => 1, 'khh_dn_nonce' => 'nonce-dung',
	'log' => 'mnnv2kvc0001', 'pwd' => 'Abc12345xy',
	've' => 'https://site.test/?khh_app=1&app=hrm',
) );
t( 'đường dẫn cùng site thì giữ nguyên', $r['di'], 'https://site.test/?khh_app=1&app=hrm' );

/* --- màn đăng nhập in ra đủ thứ cần --- */
ob_start();
khh_login_screen( 'Thử một câu lỗi' );
$html = ob_get_clean();
t( 'màn có ô tên đăng nhập', strpos( $html, 'name="log"' ) !== false, true );
t( 'màn có ô mật khẩu', strpos( $html, 'type="password"' ) !== false, true );
t( 'màn có mã phiên', strpos( $html, 'khh_dn_nonce' ) !== false, true );
t( 'màn hiện câu lỗi truyền vào', strpos( $html, 'Thử một câu lỗi' ) !== false, true );
t( 'chưa khai logo thì hiện chữ KH', strpos( $html, '>KH<' ) !== false, true );
t( 'form gửi về chính trang nền tảng', strpos( $html, 'action="https://site.test/?khh_app=1"' ) !== false, true );

update_option( 'khh_settings', array( 'logo' => 'https://site.test/logo-kh.png' ) );
ob_start();
khh_login_screen( '' );
$html2 = ob_get_clean();
t( 'khai logo thì hiện ảnh', strpos( $html2, 'logo-kh.png' ) !== false, true );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
