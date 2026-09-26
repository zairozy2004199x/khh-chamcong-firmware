<?php
/**
 * KIỂM "QUẢN LÝ NHÂN SỰ GỘP VÀO TAB HỒ SƠ & TÀI KHOẢN".
 *
 * Anh Thắng 26/09/2026: *"Gộp lại thành 1 tab và bỏ bớt cái thừa"* — chốt giữ bảng Hồ sơ & tài
 * khoản. Các khối của trang Quản lý nhân sự (`VHCC_TrangNS`) vẽ ngay trong tab ấy; bảng "Hồ sơ
 * nhân sự" trùng của trang kia bỏ; biểu mẫu của khối nhúng vẫn tới đúng trình xử lý cũ.
 *
 * Chạy: php tools/test/kiem-gop-ho-so-ns.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 300 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'GAD', 'ho_ten' => 'Quản Trị Gộp',
	'pin_dang_nhap' => '551177', 'vai_tro' => 'Admin', 'cua_hang' => 'CS_GOP', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'GNV', 'ho_ten' => 'Nhân Viên Gộp',
	'vai_tro' => 'Nhân viên', 'cua_hang' => 'CS_GOP', 'trang_thai_lam_viec' => 'Đang làm' ) );
update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '551177' );
t( 'dựng cảnh: Admin đăng nhập được', ! empty( $kq['ok'] ), $kq );
$tok = $kq['token'];

$web = function ( $get, $post = array() ) use ( $tok ) {
	$_COOKIE = array( VHCC_Web::COOKIE => $tok );
	$_GET  = $get;
	$_POST = $post ? array_merge( array( 'ky' => VHCC_Web::chu_ky( $tok ) ), $post ) : array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
};

/* ── Tab Nhân sự: cả hai phần có mặt, không trùng bảng ── */
$h = $web( array( 'man' => 'ho_so' ) );
t( '🔴 tab Hồ sơ có phần hồ sơ của chính nó (Tạo nhân sự mới)', false !== strpos( $h, '➕ Tạo nhân sự mới' ) );
t( '   và bảng hồ sơ của nó', false !== strpos( $h, 'id="vhcc-bang"' ) );
t( '🔴 có luôn các khối Quản lý nhân sự (Vai trò theo bộ phận)', false !== strpos( $h, 'Vai trò theo bộ phận' ), $h );
t( '🔴 KHÔNG còn khối Bảng vai trò (anh Thắng: "Bỏ này")', false === strpos( $h, 'Bảng vai trò</b>' ) );
t( '   có thanh tab Nhân sự / Quyền vào trang', false !== strpos( $h, 'class="tab-ns"' ) );
t( '🔴 KHÔNG còn bảng "Hồ sơ nhân sự" trùng của trang Quản lý nhân sự',
	1 === substr_count( $h, 'Hồ sơ nhân sự</h2>' ), substr_count( $h, 'Hồ sơ nhân sự</h2>' ) );
t( '🔴 menu trái KHÔNG còn mục "Quản lý nhân sự" riêng', false === strpos( $h, '</span>Quản lý nhân sự</a>' ) );
t( '   liên kết tab trỏ về chính tab Hồ sơ (man=ho_so), không về trang cũ',
	false !== strpos( $h, 'man=ho_so&amp;ntab=quyen' ) || false !== strpos( $h, 'man=ho_so&ntab=quyen' ), $h );

/* Biểu mẫu POST của khối nhúng mang dấu `ns_nhung`; biểu mẫu bảng hồ sơ thì KHÔNG. */
t( '🔴 biểu mẫu của khối nhúng mang dấu ns_nhung', false !== strpos( $h, 'name="ns_nhung" value="1"' ) );
$bang = (string) substr( $h, (int) strpos( $h, 'id="vhcc-bang"' ) );
$bang = (string) substr( $bang, 0, (int) strpos( $bang, '</form>' ) );
t( '   bảng hồ sơ của tab KHÔNG bị gắn dấu ấy', '' !== $bang && false === strpos( $bang, 'ns_nhung' ) );

/* ── Gửi biểu mẫu nhúng: tới đúng trình xử lý của Quản lý nhân sự (việc `them_vai` vẫn có ở máy
      chủ dù màn khai đã bỏ — dùng nó để canh đường chuyển lượt gửi) ── */
$h2 = $web( array( 'man' => 'ho_so' ),
	array( 'ns_nhung' => '1', 'viec' => 'them_vai', 'vai_ten' => 'Kế Toán Gộp Thử', 'vai_goc' => VHCC_Vai::KE_TOAN ) );
t( '🔴 bấm "Thêm vai" trong khối nhúng -> vai được tạo',
	in_array( 'Kế Toán Gộp Thử', VHCC_Vai::ds_ten(), true ), VHCC_Vai::ds_ten() );
t( '   và báo kết quả ngay trong tab', false !== strpos( $h2, 'Đã khai vai' ), $h2 );

/* ── Tab Quyền vào trang: chỉ phần quyền, không lẫn phần hồ sơ ── */
$hq = $web( array( 'man' => 'ho_so', 'ntab' => 'quyen' ) );
t( '🔴 tab Quyền vào trang mở ngay trong tab Hồ sơ', false !== strpos( $hq, 'Ai vào được trang nào</h2>' ), $hq );
t( '   và KHÔNG lẫn phần tạo hồ sơ', false === strpos( $hq, '➕ Tạo nhân sự mới' ) );

/* ── Không có quyền trang Quản lý nhân sự thì chỉ thấy phần hồ sơ như cũ — Admin luôn có,
      nên chỉ canh rằng nhánh ấy không vỡ khi gọi thẳng trang riêng. ── */
$_COOKIE = array( VHCC_Web::COOKIE => $tok );
ob_start(); VHCC_TrangNS::phuc_vu(); $h_rieng = ob_get_clean();
$_COOKIE = array();
t( 'trang riêng cũ vẫn vẽ được (cho ai giữ lượt gửi cũ)', false !== strpos( $h_rieng, 'Quản lý nhân sự' ) );
t( '   và ở đó bảng Hồ sơ nhân sự của chính nó vẫn còn', false !== strpos( $h_rieng, 'Hồ sơ nhân sự</h2>' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — Quản lý nhân sự gộp vào tab Hồ sơ & tài khoản, biểu mẫu vẫn tới đúng chỗ.\n";
