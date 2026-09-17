<?php
/* Giả lập WordPress để chạy thử luồng "nhân viên tự đổi mật khẩu" (tai-khoan-cua-toi.php).
   Không cần WordPress, không cần MySQL — chạy được sau mỗi lần sửa. */

define( 'ABSPATH', '/tmp/fakewp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );

$GLOBALS['_opts']   = array();
$GLOBALS['_trans']  = array();
$GLOBALS['_cookie'] = null;
$GLOBALS['_me']     = 0;

function add_action() {}
function register_rest_route() {}
function get_option( $k, $d = false ) { return isset( $GLOBALS['_opts'][ $k ] ) ? $GLOBALS['_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['_opts'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function checked() {}

function get_transient( $k ) {
	if ( ! isset( $GLOBALS['_trans'][ $k ] ) ) { return false; }
	list( $v, $exp ) = $GLOBALS['_trans'][ $k ];
	if ( $exp && $exp < time() ) { unset( $GLOBALS['_trans'][ $k ] ); return false; }
	return $v; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['_trans'][ $k ] = array( $v, $ttl ? time() + $ttl : 0 ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['_trans'][ $k ] ); return true; }

/* Băm giả nhưng giữ đúng tính chất quan trọng: chuỗi vào khác nhau một ký tự
   (kể cả dấu cách ở hai đầu) là ra băm khác nhau. */
function wp_hash_password( $p ) { return 'H:' . hash( 'sha256', 'muoi' . $p ); }
function wp_check_password( $p, $h, $id = 0 ) { return hash_equals( (string) $h, wp_hash_password( $p ) ); }

class TK_User {
	public $ID = 7;
	public $user_pass;
	public $user_login = 'quangthang';
	public function __construct( $mk ) { $this->user_pass = wp_hash_password( $mk ); }
}
$GLOBALS['_user'] = new TK_User( 'matkhaucu1' );

function wp_get_current_user() { return $GLOBALS['_me'] ? $GLOBALS['_user'] : null; }
function is_user_logged_in() { return (bool) $GLOBALS['_me']; }
function wp_set_current_user( $id ) { $GLOBALS['_me'] = $id; }
function wp_set_password( $mk, $id ) { $GLOBALS['_user']->user_pass = wp_hash_password( $mk ); }
function wp_set_auth_cookie( $id, $nho = false ) { $GLOBALS['_cookie'] = array( $id, $nho ); }
function rest_ensure_response( $r ) { return $r; }

class WP_Error {
	public $code;
	public $msg;
	public $data;
	public function __construct( $c, $m = '', $d = array() ) { $this->code = $c; $this->msg = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->msg; }
	public function get_error_data() { return $this->data; }
}

/** Yêu cầu REST giả, chỉ cần get_param. */
class TK_Req {
	private $p;
	public function __construct( $p ) { $this->p = $p; }
	public function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
}

/* khh_settings() thật nằm trong khh-platform.php (tệp to, kéo theo cả WordPress);
   ở đây chỉ cần đúng hành vi: giá trị đã lưu đè lên mặc định của phần này. */
function khh_settings() {
	return wp_parse_args( get_option( 'khh_settings', array() ), khh_tk_mac_dinh() );
}

require_once KHH_DIR . 'tai-khoan-cua-toi.php';

$fail = 0;
function t( $ten, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-52s got=%s\n", $ok ? 'PASS' : 'FAIL', $ten, var_export( $got, true ) );
}
/** Mã lỗi của kết quả, hoặc 'ok' nếu đổi được. */
function ma( $r ) { return $r instanceof WP_Error ? $r->get_error_code() : 'ok'; }
function doi( $cu, $moi ) { return khh_tk_doi_mat_khau( new TK_Req( array( 'cu' => $cu, 'moi' => $moi ) ) ); }
function dat( $a ) { update_option( 'khh_settings', $a ); }

$GLOBALS['_me'] = 7;

/* 1. Mặc định: bật sẵn, đổi được */
t( 'mặc định cho tự đổi', (int) khh_tk_cfg( 'self_password' ), 1 );
t( 'mặc định cho xem hồ sơ', (int) khh_tk_cfg( 'self_profile' ), 1 );
t( 'mặc định KHÔNG hiện lương', (int) khh_tk_cfg( 'self_profile_pay' ), 0 );
t( 'độ dài tối thiểu mặc định', khh_tk_do_dai_min(), 8 );

/* 2. Độ dài tối thiểu bị kẹp trong khoảng hợp lý */
dat( array( 'self_password_min' => 2 ) );
t( 'khai 2 → kẹp lên 6', khh_tk_do_dai_min(), 6 );
dat( array( 'self_password_min' => 500 ) );
t( 'khai 500 → kẹp xuống 64', khh_tk_do_dai_min(), 64 );

/* 3. Tắt cấu hình thì từ chối, dù mật khẩu đúng */
dat( array( 'self_password' => 0 ) );
t( 'tắt cấu hình → từ chối', ma( doi( 'matkhaucu1', 'matkhaumoi1' ) ), 'khh_tat' );
t( 'mật khẩu chưa bị đổi', wp_check_password( 'matkhaucu1', $GLOBALS['_user']->user_pass ), true );

/* 4. Chưa đăng nhập */
dat( array() );
$GLOBALS['_me'] = 0;
t( 'chưa đăng nhập → từ chối', ma( doi( 'matkhaucu1', 'matkhaumoi1' ) ), 'khh_chua_dang_nhap' );
$GLOBALS['_me'] = 7;

/* 5. Mật khẩu hiện tại sai */
t( 'gõ sai mật khẩu hiện tại', ma( doi( 'sairoi', 'matkhaumoi1' ) ), 'khh_sai_mk' );
t( 'mật khẩu vẫn nguyên', wp_check_password( 'matkhaucu1', $GLOBALS['_user']->user_pass ), true );
t( 'bỏ trống cũng là sai', ma( doi( '', 'matkhaumoi1' ) ), 'khh_sai_mk' );

/* 6. Sai 5 lần thì khoá — và khoá theo TÀI KHOẢN, không theo IP */
$GLOBALS['_trans'] = array();
for ( $i = 0; $i < 5; $i++ ) { doi( 'sairoi', 'matkhaumoi1' ); }
t( 'sai 5 lần → khoá', ma( doi( 'matkhaucu1', 'matkhaumoi1' ) ), 'khh_khoa' );
t( 'khoá đếm theo tài khoản', array_keys( $GLOBALS['_trans'] ), array( 'khh_mk_sai_7' ) );
t( 'đang khoá thì mật khẩu vẫn nguyên', wp_check_password( 'matkhaucu1', $GLOBALS['_user']->user_pass ), true );
$GLOBALS['_trans'] = array();

/* 7. Mật khẩu mới quá ngắn / trùng cũ */
t( 'mật khẩu mới quá ngắn', ma( doi( 'matkhaucu1', 'ngan' ) ), 'khh_ngan' );
t( 'mật khẩu mới trùng cũ', ma( doi( 'matkhaucu1', 'matkhaucu1' ) ), 'khh_trung' );
t( 'hai lỗi trên không tính là gõ sai', (int) get_transient( 'khh_mk_sai_7' ), 0 );

/* 8. Đổi được thật */
$r = doi( 'matkhaucu1', 'matkhaumoi1' );
t( 'đổi được', ma( $r ), 'ok' );
t( 'trả cờ tải lại trang', isset( $r['taiLai'] ) ? (int) $r['taiLai'] : 0, 1 );
t( 'mật khẩu mới ăn', wp_check_password( 'matkhaumoi1', $GLOBALS['_user']->user_pass ), true );
t( 'mật khẩu cũ hết tác dụng', wp_check_password( 'matkhaucu1', $GLOBALS['_user']->user_pass ), false );
t( 'cấp lại cookie cho máy vừa đổi', $GLOBALS['_cookie'], array( 7, false ) );

/* 9. Gõ sai rồi gõ đúng thì bộ đếm phải xoá, không để dồn tới lần sau thành khoá oan */
doi( 'sairoi', 'matkhaumoi2' );
t( 'đếm được 1 lần sai', (int) get_transient( 'khh_mk_sai_7' ), 1 );
doi( 'matkhaumoi1', 'matkhaumoi2' );
t( 'gõ đúng thì xoá bộ đếm', get_transient( 'khh_mk_sai_7' ), false );

/* 10. Dấu cách hai đầu là một phần của mật khẩu, không được cắt */
$GLOBALS['_user'] = new TK_User( '  co khoang trang  ' );
t( 'giữ nguyên dấu cách hai đầu', ma( doi( '  co khoang trang  ', 'matkhaumoi3' ) ), 'ok' );
$GLOBALS['_user'] = new TK_User( '  co khoang trang  ' );
t( 'cắt dấu cách là gõ sai', ma( doi( 'co khoang trang', 'matkhaumoi3' ) ), 'khh_sai_mk' );

/* 11. Đọc cấu hình từ $_POST ở trang quản trị */
$_POST = array( 'self_profile' => '1', 'self_password_min' => '3' );
$c     = khh_tk_nhan_cau_hinh();
t( 'ô không tích → tắt', $c['self_password'], 0 );
t( 'ô có tích → bật', $c['self_profile'], 1 );
t( 'độ dài dưới 6 bị kẹp khi lưu', $c['self_password_min'], 6 );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
