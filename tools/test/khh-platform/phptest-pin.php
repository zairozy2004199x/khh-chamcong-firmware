<?php
/* Giả lập WordPress để chạy thử mã PIN nhiều người và chức năng đăng nhập thử */
define( 'ABSPATH', '/tmp/fakewp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );
define( 'KHH_URL', 'https://site.test/wp-content/plugins/khh-platform/' );
define( 'KHH_VERSION', '1.3.0' );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', '' );

$GLOBALS['_opts']      = array();
$GLOBALS['_trans']     = array();
$GLOBALS['_logged_in'] = 0;
$GLOBALS['_users']     = array();

function add_action() {}
function add_submenu_page() {}
function sanitize_text_field( $t ) { return trim( strip_tags( $t ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $k ) ); }
function wp_unslash( $v ) { return $v; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['_opts'][ $k ] ) ? $GLOBALS['_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['_opts'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_transient( $k ) {
	if ( ! isset( $GLOBALS['_trans'][ $k ] ) ) { return false; }
	list( $v, $exp ) = $GLOBALS['_trans'][ $k ];
	if ( $exp && $exp < time() ) { unset( $GLOBALS['_trans'][ $k ] ); return false; }
	return $v; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['_trans'][ $k ] = array( $v, $ttl ? time() + $ttl : 0 ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['_trans'][ $k ] ); return true; }
function wp_hash_password( $p ) { return 'H:' . hash( 'sha256', 'salt' . $p ); }
function wp_check_password( $p, $h ) { return hash_equals( (string) $h, wp_hash_password( $p ) ); }
function wp_hash( $s ) { return hash_hmac( 'sha256', $s, 'secret-key' ); }
function wp_rand( $a, $b ) { return random_int( $a, $b ); }
function get_user_by( $f, $v ) {
	foreach ( $GLOBALS['_users'] as $u ) { if ( 'id' === $f && (int) $u->ID === (int) $v ) { return $u; } }
	return false; }
function get_users( $a = array() ) { return array_values( $GLOBALS['_users'] ); }
function get_current_user_id() { return (int) $GLOBALS['_logged_in']; }
function wp_get_current_user() { return get_user_by( 'id', $GLOBALS['_logged_in'] ); }
function is_user_logged_in() { return (bool) $GLOBALS['_logged_in']; }
function wp_set_current_user( $id ) { $GLOBALS['_logged_in'] = $id; }
function wp_set_auth_cookie( $id, $remember = false ) { $GLOBALS['_cookie'] = array( $id, $remember ); }
function do_action() {}
function current_user_can( $c ) { return true; }
function is_ssl() { return true; }
function home_url( $p = '' ) { return 'https://site.test' . $p; }
function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . $p; }
function wp_login_url( $r = '' ) { return 'https://site.test/wp-login.php'; }
function esc_url( $u ) { return $u; }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t ); }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function khh_user_role( $id = 0 ) { return 'staff'; }

function khh_settings() {
	$d = array(
		'google_client_id' => '', 'allowed_domains' => '', 'only_staff' => 1, 'default_role' => 'staff',
		'pin_enabled' => 0, 'pin_hash' => '', 'pin_user' => 0, 'pin_expires' => 0, 'pins' => array(),
	);
	return wp_parse_args( get_option( 'khh_settings', array() ), $d );
}

/* nạp cả hai file: chỉ các lệnh add_action ở cấp ngoài mới chạy, và chúng đã được giả lập */
foreach ( array( 'pin-login.php', 'switch-user.php' ) as $f ) {
	$src = file_get_contents( KHH_DIR . $f );
	$src = preg_replace( '/^<\?php/', '', $src, 1 );
	eval( $src );
}

function mkuser( $id, $login, $name ) {
	$u = new stdClass();
	$u->ID = $id; $u->user_login = $login; $u->display_name = $name; $u->roles = array( 'khh_staff' );
	$GLOBALS['_users'][] = $u;
	return $u;
}
mkuser( 7, 'admin', 'Quang Thắng' );
mkuser( 8, 'linh', 'Mỹ Linh' );
mkuser( 9, 'phuc', 'Hữu Phúc' );

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-52s got=%s\n", $ok ? 'PASS' : 'FAIL', $name, var_export( $got, true ) );
}
function set_pins( $map, $hours = 8 ) {
	$pins = array();
	foreach ( $map as $uid => $code ) {
		$pins[] = array(
			'user'    => $uid,
			'hash'    => wp_hash_password( (string) $code ),
			'expires' => $hours > 0 ? time() + $hours * 3600 : ( $hours < 0 ? time() + $hours : 0 ),
			'created' => time(),
		);
	}
	update_option( 'khh_settings', array( 'pins' => $pins ) );
	$GLOBALS['_trans']     = array();
	$GLOBALS['_logged_in'] = 0;
}

/* --- mã PIN nhiều người --- */
update_option( 'khh_settings', array() );
t( 'chưa tạo mã nào thì tắt', khh_pin_active(), false );

set_pins( array( 7 => '111111', 8 => '222222', 9 => '333333' ) );
t( 'ba mã đều hiệu lực', count( khh_pin_valid_list() ), 3 );

t( 'mã của admin vào đúng admin', khh_pin_attempt( '111111' ), '' );
t( '  → đăng nhập id 7', $GLOBALS['_logged_in'], 7 );

$GLOBALS['_logged_in'] = 0;
t( 'mã của Mỹ Linh vào đúng Mỹ Linh', khh_pin_attempt( '222222' ), '' );
t( '  → đăng nhập id 8', $GLOBALS['_logged_in'], 8 );

$GLOBALS['_logged_in'] = 0;
t( 'mã không thuộc ai bị từ chối', strpos( khh_pin_attempt( '999999' ), 'không đúng' ) !== false, true );
t( '  → không đăng nhập', $GLOBALS['_logged_in'], 0 );

/* --- trùng mã --- */
$pins = khh_pin_list();
t( 'phát hiện mã đã dùng cho người khác', khh_pin_code_taken( '222222', $pins ), true );
t( 'mã mới thì không trùng', khh_pin_code_taken( '456789', $pins ), false );

/* --- hết hạn riêng từng mã --- */
$pins = khh_pin_list();
$pins[1]['expires'] = time() - 60;           // mã của Mỹ Linh hết hạn
khh_pin_save_list( $pins );
t( 'mã hết hạn bị loại khỏi danh sách', count( khh_pin_valid_list() ), 2 );
$GLOBALS['_logged_in'] = 0;
t( 'mã hết hạn không dùng được', strpos( khh_pin_attempt( '222222' ), 'không đúng' ) !== false, true );
t( 'mã còn hạn vẫn dùng được', khh_pin_attempt( '333333' ), '' );
t( '  → đăng nhập id 9', $GLOBALS['_logged_in'], 9 );

/* --- khoá sau 5 lần sai --- */
set_pins( array( 7 => '111111' ) );
for ( $i = 0; $i < 5; $i++ ) { khh_pin_attempt( '000000' ); }
t( 'sai 5 lần thì khoá cả mã đúng', strpos( khh_pin_attempt( '111111' ), 'Chờ 15 phút' ) !== false, true );
t( '  → vẫn chưa đăng nhập', $GLOBALS['_logged_in'], 0 );

/* --- tương thích bản cũ một mã --- */
$GLOBALS['_trans'] = array(); // gỡ khoá 15 phút do block trên cố tình gây ra
update_option( 'khh_settings', array(
	'pin_enabled' => 1, 'pin_hash' => wp_hash_password( '4321' ), 'pin_user' => 7, 'pin_expires' => time() + 3600,
) );
t( 'đọc được mã của bản 1.2.0', count( khh_pin_valid_list() ), 1 );
$GLOBALS['_logged_in'] = 0;
t( 'mã bản cũ vẫn đăng nhập được', khh_pin_attempt( '4321' ), '' );

/* --- đăng nhập thử: cookie quay về --- */
$exp = time() + 3600;
$_COOKIE['khh_switch_back'] = '7|' . $exp . '|' . khh_switch_sign( 7, $exp );
t( 'đọc đúng người thật từ cookie', khh_switch_origin(), 7 );

$_COOKIE['khh_switch_back'] = '9|' . $exp . '|' . khh_switch_sign( 7, $exp );
t( 'cookie bị sửa id thì vô hiệu', khh_switch_origin(), 0 );

$_COOKIE['khh_switch_back'] = '7|' . $exp . '|chu-ky-gia';
t( 'chữ ký giả thì vô hiệu', khh_switch_origin(), 0 );

$old = time() - 10;
$_COOKIE['khh_switch_back'] = '7|' . $old . '|' . khh_switch_sign( 7, $old );
t( 'cookie hết hạn thì vô hiệu', khh_switch_origin(), 0 );

unset( $_COOKIE['khh_switch_back'] );
t( 'không có cookie thì không phải đang thử', khh_switch_origin(), 0 );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
