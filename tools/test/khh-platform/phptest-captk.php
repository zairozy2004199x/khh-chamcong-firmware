<?php
/* Kiểm thử cấp tài khoản bằng tên đăng nhập + mật khẩu */
define( 'ABSPATH', '/tmp/fakewp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );

$GLOBALS['_users']  = array();
$GLOBALS['_meta']   = array();
$GLOBALS['_trans']  = array();
$GLOBALS['_docs']   = array( 'staff' => array() );
$GLOBALS['_nextid'] = 10;

function add_action() {}
function add_submenu_page() {}
function wp_die( $m ) { die( $m ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t ); }
function esc_url( $u ) { return $u; }
function wp_login_url( $r = '' ) { return 'https://site.test/wp-login.php'; }
function home_url( $p = '' ) { return 'https://site.test' . $p; }
function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . $p; }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^a-zA-Z0-9._\-]/', '', (string) $u ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_rand( $a, $b ) { return random_int( $a, $b ); }
function current_user_can( $c ) { return true; }
function get_current_user_id() { return 1; }
function check_admin_referer() { return true; }
function wp_nonce_field() {}
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['_trans'][ $k ] = $v; return true; }
function get_transient( $k ) { return isset( $GLOBALS['_trans'][ $k ] ) ? $GLOBALS['_trans'][ $k ] : false; }
function delete_transient( $k ) { unset( $GLOBALS['_trans'][ $k ] ); return true; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['_meta'][ $id ][ $k ] = $v; return true; }
function get_user_meta( $id, $k, $single = true ) { return isset( $GLOBALS['_meta'][ $id ][ $k ] ) ? $GLOBALS['_meta'][ $id ][ $k ] : ''; }
function username_exists( $l ) {
	foreach ( $GLOBALS['_users'] as $u ) { if ( $u->user_login === $l ) { return $u->ID; } }
	return false;
}
function email_exists( $e ) {
	foreach ( $GLOBALS['_users'] as $u ) { if ( $u->user_email && strtolower( $u->user_email ) === strtolower( $e ) ) { return $u->ID; } }
	return false;
}
function get_user_by( $f, $v ) {
	foreach ( $GLOBALS['_users'] as $u ) {
		if ( 'id' === $f && (int) $u->ID === (int) $v ) { return $u; }
		if ( 'login' === $f && $u->user_login === $v ) { return $u; }
	}
	return false;
}
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['_users'] as $u ) {
		if ( isset( $args['meta_key'] ) ) {
			if ( get_user_meta( $u->ID, $args['meta_key'] ) !== $args['meta_value'] ) { continue; }
		}
		$out[] = $u;
		if ( isset( $args['number'] ) && count( $out ) >= $args['number'] ) { break; }
	}
	return $out;
}
function wp_insert_user( $a ) {
	if ( username_exists( $a['user_login'] ) ) { return new WP_Error( 'existing_user_login', 'Trùng tên đăng nhập.' ); }
	$u               = new stdClass();
	$u->ID           = $GLOBALS['_nextid']++;
	$u->user_login   = $a['user_login'];
	$u->user_email   = isset( $a['user_email'] ) ? $a['user_email'] : '';
	$u->display_name = isset( $a['display_name'] ) ? $a['display_name'] : '';
	$u->roles        = array( $a['role'] );
	$u->pass         = $a['user_pass'];
	$GLOBALS['_users'][] = $u;
	return $u->ID;
}
function wp_set_password( $p, $id ) { $u = get_user_by( 'id', $id ); if ( $u ) { $u->pass = $p; } }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_Error {
	public $c, $m;
	function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	function get_error_message() { return $this->m; }
}
function khh_get_coll( $c ) { return isset( $GLOBALS['_docs'][ $c ] ) ? $GLOBALS['_docs'][ $c ] : array(); }
function khh_put_doc( $c, $i, $b ) { $GLOBALS['_docs'][ $c ][ $i ] = $b; return true; }
function khh_wp_role_for( $r ) {
	$m = array( 'owner' => 'administrator', 'admin' => 'khh_admin', 'manager' => 'khh_manager', 'staff' => 'khh_staff' );
	return isset( $m[ $r ] ) ? $m[ $r ] : 'khh_staff';
}
function khh_user_role( $id = 0 ) { return get_user_meta( $id, 'khh_role' ) ?: 'staff'; }
function khh_slug_vi( $s ) {
	$s   = trim( mb_strtolower( (string) $s, 'UTF-8' ) );
	$map = array(
		'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
		'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
	);
	foreach ( $map as $to => $from ) {
		$s = preg_replace( '/[' . $from . ']/u', $to, $s );
	}
	return preg_replace( '/[^a-z0-9]+/', '', $s );
}

require_once KHH_DIR . 'cap-tai-khoan.php';

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-58s got=%s\n", $ok ? 'PASS' : 'FAIL', $name, is_array( $got ) ? json_encode( $got, JSON_UNESCAPED_UNICODE ) : var_export( $got, true ) );
}

/* --- mật khẩu sinh ra --- */
$p = khh_gen_pass();
t( 'mật khẩu dài 10 ký tự', strlen( $p ), 10 );
t( 'không có chữ dễ đọc nhầm (0 O 1 l I)', (bool) preg_match( '/[0O1lI]/', $p ), false );
$khac = array();
for ( $i = 0; $i < 200; $i++ ) { $khac[ khh_gen_pass() ] = 1; }
t( '200 lần sinh ra 200 mật khẩu khác nhau', count( $khac ), 200 );

/* --- tên đăng nhập gợi ý --- */
t( 'lấy mã nhân sự làm tên đăng nhập', khh_suggest_login( array( 'code' => 'MNNV2KVC0001', 'name' => 'Nguyễn Thị Bảo Mai' ) ), 'mnnv2kvc0001' );
t( 'không có mã thì bỏ dấu từ họ tên', khh_suggest_login( array( 'name' => 'Nguyễn Thị Bảo Mai' ) ), 'nguyenthibaomai' );
t( 'chữ đ đổi thành d', khh_suggest_login( array( 'name' => 'Đặng Văn Đức' ) ), 'dangvanduc' );
t( 'hồ sơ trống vẫn ra tên dùng được', khh_suggest_login( array() ), 'nv' );

/* --- cấp tài khoản --- */
$GLOBALS['_docs']['staff'] = array(
	's1' => array( 'name' => 'Nguyễn Thị Bảo Mai', 'code' => 'MNNV2KVC0001', 'role' => 'staff', 'status' => 'active', 'dept' => 'Cơ sở' ),
	's2' => array( 'name' => 'Khánh Duy', 'code' => 'MNV-05', 'role' => 'manager', 'status' => 'active' ),
	's3' => array( 'name' => 'Người Đã Nghỉ', 'code' => 'MNV-09', 'status' => 'left' ),
	's4' => array( 'name' => 'Không Mã', 'role' => 'staff', 'status' => 'active', 'email' => 'khongma@khh.vn' ),
);

$r = khh_create_login( 's1' );
t( 'cấp được tài khoản', is_wp_error( $r ), false );
t( '  tên đăng nhập lấy từ mã', $r['login'], 'mnnv2kvc0001' );
t( '  mật khẩu trả về để giao tận tay', strlen( $r['pass'] ), 10 );
t( '  gắn đúng hồ sơ nhân sự', get_user_meta( $r['uid'], 'khh_staff_id' ), 's1' );
t( '  vai trò nền tảng đúng', get_user_meta( $r['uid'], 'khh_role' ), 'staff' );
t( '  vai trò WordPress đúng', get_user_by( 'id', $r['uid'] )->roles[0], 'khh_staff' );
t( '  tên hiển thị là họ tên thật', get_user_by( 'id', $r['uid'] )->display_name, 'Nguyễn Thị Bảo Mai' );
t( '  không có email thì để trống, vẫn tạo được', get_user_by( 'id', $r['uid'] )->user_email, '' );
t( '  mật khẩu lưu vào tài khoản đúng bản vừa giao', get_user_by( 'id', $r['uid'] )->pass, $r['pass'] );

$r2 = khh_create_login( 's2' );
t( 'Quản lý được cấp vai trò WordPress riêng', get_user_by( 'id', $r2['uid'] )->roles[0], 'khh_manager' );

$r4 = khh_create_login( 's4' );
t( 'có email trong hồ sơ thì gắn vào tài khoản', get_user_by( 'id', $r4['uid'] )->user_email, 'khongma@khh.vn' );
t( 'không mã thì tên đăng nhập lấy từ họ tên', $r4['login'], 'khongma' );

/* --- không cấp trùng --- */
$lai = khh_create_login( 's1' );
t( 'cấp lại cho người đã có thì bị chặn', is_wp_error( $lai ), true );
t( '  báo rõ đã có tài khoản nào', strpos( $lai->get_error_message(), 'mnnv2kvc0001' ) !== false, true );
t( 'không sinh thêm tài khoản', count( $GLOBALS['_users'] ), 3 );

/* --- tên đăng nhập trùng thì tự thêm số --- */
$GLOBALS['_docs']['staff']['s5'] = array( 'name' => 'Nguyễn Thị Bảo Mai', 'role' => 'staff', 'status' => 'active' );
$GLOBALS['_docs']['staff']['s6'] = array( 'name' => 'Nguyễn Thị Bảo Mai', 'role' => 'staff', 'status' => 'active' );
$r5 = khh_create_login( 's5' );
$r6 = khh_create_login( 's6' );
t( 'hai người trùng tên: người đầu lấy tên gốc', $r5['login'], 'nguyenthibaomai' );
t( 'người sau tự thêm số', $r6['login'], 'nguyenthibaomai2' );

/* --- tự đặt tên và mật khẩu --- */
$GLOBALS['_docs']['staff']['s7'] = array( 'name' => 'Tự Đặt', 'role' => 'staff', 'status' => 'active' );
$r7 = khh_create_login( 's7', 'baomai.kt', 'MatKhauDai123' );
t( 'tự đặt tên đăng nhập', $r7['login'], 'baomai.kt' );
t( 'tự đặt mật khẩu', $r7['pass'], 'MatKhauDai123' );

$GLOBALS['_docs']['staff']['s8'] = array( 'name' => 'Ngắn Quá', 'role' => 'staff', 'status' => 'active' );
$ng = khh_create_login( 's8', '', 'abc123' );
t( 'mật khẩu dưới 8 ký tự bị từ chối', is_wp_error( $ng ), true );
t( '  không tạo tài khoản nào', username_exists( 'nganqua' ), false );

$trung = khh_create_login( 's8', 'baomai.kt' );
t( 'đặt tên trùng người khác bị từ chối', is_wp_error( $trung ), true );

t( 'hồ sơ không có thật bị từ chối', is_wp_error( khh_create_login( 'khong_co' ) ), true );

/* --- mật khẩu chỉ hiện một lần --- */
khh_stash_logins( array( array( 'login' => 'x', 'pass' => 'y' ) ) );
$lan1 = khh_take_logins();
$lan2 = khh_take_logins();
t( 'lần đầu lấy được danh sách', count( $lan1 ), 1 );
t( 'lần hai đã xoá sạch', $lan2, array() );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
