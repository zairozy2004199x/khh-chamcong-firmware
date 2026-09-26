<?php
/**
 * KIỂM "TẢI BẢNG LƯƠNG TẤT CẢ CƠ SỞ TRONG MỘT FILE".
 *
 * Anh Thắng 26/09/2026: *"Kế toán thêm tính năng tải được toàn bộ tất cả cơ sở trong 1 file"*.
 *
 * Chạy: php tools/test/kiem-luong-tai-het.php
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
$TH = '2026-08';
foreach ( array( 'TH_A', 'TH_B', 'TH_C' ) as $i => $cs ) {
	$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $cs, 'bo_phan' => 'Khu vui chơi' ) );
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'THN' . $i, 'ho_ten' => 'Người ' . $cs,
		'vai_tro' => 'Nhân viên', 'cua_hang' => $cs, 'chuc_vu' => 'NV', 'trang_thai_lam_viec' => 'Đang làm' ) );
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $cs, 'ngay' => $TH . '-03', 'ma_nv' => 'THN' . $i,
		'ho_ten' => 'Người ' . $cs, 'gio_vao_giay' => 28800, 'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
}
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'THAD', 'ho_ten' => 'Kế Toán Tổng',
	'pin_dang_nhap' => '662288', 'vai_tro' => 'Admin', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'THCH', 'ho_ten' => 'Trưởng Một Cửa',
	'pin_dang_nhap' => '662299', 'vai_tro' => 'Cửa hàng trưởng', 'cua_hang' => 'TH_A', 'coso_quan' => 'TH_A',
	'trang_thai_lam_viec' => 'Đang làm' ) );
update_option( 'vhcc_nguon_nguoidung', 'ho_so' );

$man = function ( $pin, $get ) {
	VHCC_Auth::mo_khoa();
	$kq = VHCC_Auth::login( $pin );
	$_COOKIE = array( VHCC_Web::COOKIE => $kq['token'] );
	$_GET = $get; $_POST = array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_COOKIE = array();
	return $h;
};

/* Chưa chọn cơ sở nào cũng thấy nút — kế toán mở màn là tải được ngay. */
$h = $man( '662288', array( 'man' => 'luong', 'lth' => $TH ) );
t( '🔴 màn Bảng lương có nút tải TẤT CẢ cơ sở', false !== strpos( $h, 'Tải bảng lương' ) && false !== strpos( $h, 'cơ sở</b> — tháng ' . $TH ), $h );
$url = preg_match( '~href="([^"]*xuat=luong[^"]*cs=[^"]*)"~', $h, $m ) ? html_entity_decode( $m[1] ) : '';
t( '   nút trỏ đường xuất bảng lương kèm danh sách cơ sở', '' !== $url, $h );
$q = array();
parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
$ds = isset( $q['cs'] ) ? explode( ',', $q['cs'] ) : array();
t( '🔴 danh sách gồm đủ cả ba cơ sở', 0 === count( array_diff( array( 'TH_A', 'TH_B', 'TH_C' ), $ds ) ), $ds );
t( '   đúng tháng đang chọn', isset( $q['cth'] ) && $TH === $q['cth'], $q );
/* Và danh sách ấy dựng ra được MỘT tệp (đúng lõi đường xuất dùng). */
$x = VHCC_BangLuong::to_xlsx( $ds, $TH );
t( '🔴 danh sách ấy ra được một tệp .xlsx', ! empty( $x['ok'] ), isset( $x['error'] ) ? $x['error'] : '' );

/* Đi đúng đường tải thật (bấm nút) — ra một tệp .xlsx (zip, mở đầu bằng "PK"). */
$tep = $man( '662288', $q );
t( '🔴 bấm nút -> nhận về MỘT tệp .xlsx', 0 === strpos( $tep, 'PK' ), substr( $tep, 0, 200 ) );

/* Cửa hàng trưởng một cơ sở: không bày nút (không có "tất cả" nào để tải). */
$h2 = $man( '662299', array( 'man' => 'luong', 'lth' => $TH ) );
t( '🔴 người chỉ quản MỘT cơ sở không thấy nút tải tất cả', false === strpos( $h2, 'Tải bảng lương' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — tải bảng lương mọi cơ sở trong một file.\n";
