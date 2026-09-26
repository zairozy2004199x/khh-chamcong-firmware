<?php
/**
 * KIỂM "GỬI EMAIL QUA SMTP" (`VHCC_Thu`) — anh Thắng 26/09/2026: *"Các bước đều ok, chỉ là chưa
 * nhận được mail"*.
 *
 * Chạy: php tools/test/kiem-thu-smtp.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 400 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
class PM_Gia { public $Host = ''; public $Port = 25; public $SMTPSecure = ''; public $SMTPAutoTLS = true; public $SMTPAuth = false;
	public $Username = ''; public $Password = ''; public $Timeout = 300; public $smtp = false; public function isSMTP() { $this->smtp = true; } }
global $wpdb;
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'THAD', 'ho_ten' => 'Quản Trị Thư', 'pin_dang_nhap' => '775533',
	'vai_tro' => 'Admin', 'cua_hang' => 'TH_CS', 'trang_thai_lam_viec' => 'Đang làm' ) );

VHCC_Thu::init();
t( 'khai móc phpmailer_init + wp_mail_failed', ! empty( $GLOBALS['VHCP_MOC']['phpmailer_init'] ) && ! empty( $GLOBALS['VHCP_MOC']['wp_mail_failed'] ) );
$pm = new PM_Gia(); VHCC_Thu::cai( $pm );
t( '🔴 chưa khai SMTP -> không đụng cách gửi đang có', ! $pm->smtp && '' === $pm->Host );
t( '   và không đổi địa chỉ "Từ"', 'wordpress@example.test' === VHCC_Thu::tu_email( 'wordpress@example.test' ) );

$r = VHCC_Thu::dat( array( 'role' => 'Kế toán' ), array( 'host' => 'smtp.gmail.com' ) );
t( '🔴 không phải Admin -> không khai được SMTP', empty( $r['ok'] ) );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '775533' );
$tok = $kq['token'];
$web = function ( $get, $post = array() ) use ( $tok ) {
	$_COOKIE = array( VHCC_Web::COOKIE => $tok );
	$_GET  = $get;
	$_POST = $post ? array_merge( array( 'ky' => VHCC_Web::chu_ky( $tok ) ), $post ) : array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
};
$G = array( 'man' => 'tiep_nhan' );
$h = $web( $G );
t( '🔴 màn Tiếp nhận có khối cấu hình SMTP, nói rõ đang CHƯA KHAI', false !== strpos( $h, 'Cấu hình gửi email (SMTP)' ) && false !== strpos( $h, 'CHƯA KHAI' ) );
$web( $G, array( 'viec' => 'tn_smtp', 's' => array( 'host' => 'smtp.gmail.com', 'port' => '587', 'bao_mat' => 'tls',
	'tk' => 'hethong@vi-du.test', 'mk' => 'abcd efgh ijkl mnop', 'tu_email' => '', 'tu_ten' => '' ) ) );
$pm = new PM_Gia(); VHCC_Thu::cai( $pm );
t( '🔴 đã khai -> gửi qua SMTP đúng máy chủ/cổng/TLS/tài khoản', $pm->smtp && 'smtp.gmail.com' === $pm->Host && 587 === $pm->Port
	&& 'tls' === $pm->SMTPSecure && $pm->SMTPAuth && 'hethong@vi-du.test' === $pm->Username, $pm );
t( '   mật khẩu ứng dụng bỏ dấu cách', 'abcdefghijklmnop' === $pm->Password, $pm->Password );
t( '   "Từ" = tài khoản SMTP khi chưa khai email gửi đi', 'hethong@vi-du.test' === VHCC_Thu::tu_email( 'wordpress@example.test' ) );
t( '   tên người gửi = tên công ty', VHCC_Pdf::ten_cong_ty() === VHCC_Thu::tu_ten( 'WordPress' ) );
$h = $web( $G );
t( '🔴 mật khẩu KHÔNG in lại ra màn', false === strpos( $h, 'abcdefghijklmnop' ) && false !== strpos( $h, 'đã lưu — để trống giữ nguyên' ) );
$web( $G, array( 'viec' => 'tn_smtp', 's' => array( 'host' => 'smtp.gmail.com', 'port' => '587', 'bao_mat' => 'tls',
	'tk' => 'hethong@vi-du.test', 'mk' => '', 'tu_email' => 'nhansu@vi-du.test', 'tu_ten' => 'Phòng Nhân sự' ) ) );
$pm = new PM_Gia(); VHCC_Thu::cai( $pm );
t( '   lưu lại với ô mật khẩu trống -> giữ mật khẩu cũ', 'abcdefghijklmnop' === $pm->Password );
t( '   email gửi đi đã khai thì dùng nó', 'nhansu@vi-du.test' === VHCC_Thu::tu_email( 'x@y.z' ) && 'Phòng Nhân sự' === VHCC_Thu::tu_ten( 'W' ) );

$GLOBALS['VHCP_MAIL'] = array();
$h = $web( $G, array( 'viec' => 'tn_thu', 'thu_to' => 'nhan@vi-du.test' ) );
t( '🔴 Gửi thư thử -> thư đi đúng người', 1 === count( $GLOBALS['VHCP_MAIL'] ) && 'nhan@vi-du.test' === $GLOBALS['VHCP_MAIL'][0]['to'] );
$GLOBALS['VHCP_MAIL_HONG'] = true;
VHCC_Thu::ghi_loi( new class { public function get_error_message() { return 'SMTP Error: Could not authenticate.'; } } );
$r = VHCC_Thu::thu( array( 'role' => 'Admin' ), 'nhan@vi-du.test' );
$GLOBALS['VHCP_MAIL_HONG'] = false;
t( '🔴 gửi hỏng -> báo lỗi, không báo thành công', empty( $r['ok'] ), $r );
VHCC_Thu::ghi_loi( new class { public function get_error_message() { return 'SMTP Error: Could not authenticate.'; } } );
$h = $web( $G );
t( '🔴 lỗi gửi thật hiện trên màn', false !== strpos( $h, 'Could not authenticate' ) );
$web( $G, array( 'viec' => 'tn_smtp', 's' => array( 'host' => '', 'port' => '587' ) ) );
$pm = new PM_Gia(); VHCC_Thu::cai( $pm );
t( '   xoá máy chủ -> thôi dùng SMTP (nhường plugin khác)', ! $pm->smtp );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — gửi email qua SMTP, thư thử, lỗi gửi thật.\n";
