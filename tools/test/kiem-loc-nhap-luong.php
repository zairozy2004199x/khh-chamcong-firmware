<?php
/**
 * KIỂM "BẢNG NHẬP LƯƠNG CẢ CƠ SỞ LỌC ĐƯỢC THEO TỪNG NHÂN VIÊN".
 *
 * Anh Thắng 26/09/2026: *"cho bảng lọc theo từng nhân viên"*.
 *
 * Chạy: php tools/test/kiem-loc-nhap-luong.php
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
global $wpdb;
$u_ad = array( 'role' => 'Admin', 'name' => 'A' );
$CS = 'LN_SHOP'; $TH = '2026-08';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'LNAD', 'ho_ten' => 'Quản Trị LN', 'pin_dang_nhap' => '663377',
	'vai_tro' => 'Admin', 'cua_hang' => 'LN_KHAC', 'trang_thai_lam_viec' => 'Đang làm' ) );
foreach ( array( 'LN1' => 'An Thị Một', 'LN2' => 'Bùi Văn Hai' ) as $ma => $ten ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $CS, 'chuc_vu' => 'NV',
		'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => '2026-08-10', 'ma_nv' => $ma, 'hau_to' => '',
		'ho_ten' => $ten, 'gio_vao_giay' => 28800, 'gio_ra_giay' => 57600, 'nguon' => 'may' ) );
}
VHCC_GiaGio::dat_coso( $u_ad, $CS, array( 'NV' => 20000 ) );
VHCC_ChotLuong::dat_tien( $u_ad, $CS, $TH, 'LN1', array( 'htXe' => 150000 ), array() );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '663377' );
$tok = $kq['token'];
$web = function ( $get, $post = array() ) use ( $tok ) {
	$_COOKIE = array( VHCC_Web::COOKIE => $tok );
	$_GET  = $get;
	$_POST = $post ? array_merge( array( 'ky' => VHCC_Web::chu_ky( $tok ) ), $post ) : array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
};
$G = array( 'man' => 'luong', 'lcs' => $CS, 'lth' => $TH );

$h = $web( $G );
t( '🔴 bảng nhập có ô "Lọc nhân viên" kèm từng người', false !== strpos( $h, 'name="lnv"' )
	&& false !== strpos( $h, '<option value="ln1">An Thị Một · LN1</option>' ), substr( $h, (int) strpos( $h, 'name="lnv"' ), 500 ) );
t( '   không lọc thì hiện cả hai người', false !== strpos( $h, 'name="b[LN1][chinh]"' ) && false !== strpos( $h, 'name="b[LN2][chinh]"' ) );

$h = $web( array_merge( $G, array( 'lnv' => 'ln2' ) ) );
t( '🔴 chọn Bùi Văn Hai -> chỉ còn dòng của người ấy', false === strpos( $h, 'name="b[LN1][chinh]"' ) && false !== strpos( $h, 'name="b[LN2][chinh]"' ) );
t( '   ô chọn giữ người đang lọc', false !== strpos( $h, '<option value="ln2" selected=' ) );
t( '   biểu mẫu Lưu giữ bộ lọc (lưu xong vẫn đứng ở người ấy)', (bool) preg_match( '~<form method="post" action="[^"]*lnv=ln2[^"]*#bangnhap"~', $h ) );
t( '   có nút Bỏ lọc', false !== strpos( $h, 'Bỏ lọc' ) );
t( '   ô lọc là form GET chở man/lcs/lth', false !== strpos( $h, '<input type="hidden" name="man" value="luong"><input type="hidden" name="lcs" value="' . $CS . '">' ) );

/* Lưu khi đang lọc: chỉ đụng người đang hiện. */
$web( array_merge( $G, array( 'lnv' => 'ln2' ) ), array( 'viec' => 'chot_luong_bang', 'ccs' => $CS, 'cth' => $TH,
	'b' => array( 'LN2' => array( 'chinh' => 'NV', 'tien' => '1', 'cong' => array( 'htCom' => '200000' ), 'tru' => array() ) ) ) );
$t1 = VHCC_ChotLuong::tien_cua( $CS, $TH, 'LN1' );
$t2 = VHCC_ChotLuong::tien_cua( $CS, $TH, 'LN2' );
t( '🔴 lưu khi đang lọc -> người được lọc đã ghi', isset( $t2['cong']['htCom'] ) && 200000.0 === (float) $t2['cong']['htCom'], $t2 );
t( '🔴 người KHÔNG hiện không bị đụng (khoản cũ còn nguyên)', isset( $t1['cong']['htXe'] ) && 150000.0 === (float) $t1['cong']['htXe'], $t1 );
t( '   lnv có trong THAM_SO (chở qua lượt chuyển hướng sau POST)', in_array( 'lnv', VHCC_Web::THAM_SO, true ) );

$h = $web( array_merge( $G, array( 'lnv' => 'khong_co' ) ) );
t( '   mã lạ -> không lọc, hiện đủ', false !== strpos( $h, 'name="b[LN1][chinh]"' ) && false !== strpos( $h, 'name="b[LN2][chinh]"' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — bảng nhập lương lọc theo từng nhân viên.\n";
