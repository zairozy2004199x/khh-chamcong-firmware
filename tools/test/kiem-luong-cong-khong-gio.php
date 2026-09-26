<?php
/**
 * KIỂM "CƠ SỞ THEO CÔNG — BẢNG NHẬP LƯƠNG KHÔNG CÓ Ô GIỜ, KHÔNG SINH THỪA HÀNG".
 *
 * Anh Thắng 26/09/2026, ảnh bảng lương VP_KH-HCM: *"Sinh thừa hàng, tính theo công, không có
 * tính giờ"* / *"Không có"*. Dòng "Ca đêm" (công đêm tự tính) lọt vào bảng nhập như một việc
 * phụ ăn giờ — ô giờ hiện "1:00", bấm "Lưu cả bảng" là nó thành một việc 1 giờ thật và mọc
 * thêm một hàng "↳ Ca đêm 1:00" trên bảng lương; kèm dòng "＋ thêm việc" ô giờ không có việc.
 *
 * Chạy: php tools/test/kiem-luong-cong-khong-gio.php
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
$u_ad = array( 'role' => 'Admin' );

/* ── dựng cảnh: khối Văn phòng (theo công) + cơ sở phụ ghép, một người có ca đêm ── */
$CHINH = 'KG_VP'; $PHU = 'KG_SETUP'; $TH = '2026-08';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CHINH, 'bo_phan' => 'Văn phòng' ) );
VHCC_Luong::dat_ghep( $u_ad, array( $PHU => $CHINH ) );
t( 'dựng cảnh: cơ sở tính THEO CÔNG', 'cong' === VHCC_Luong::cach_tinh( $CHINH ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'KGAD', 'ho_ten' => 'Quản Trị KG',
	'pin_dang_nhap' => '662288', 'vai_tro' => 'Admin', 'cua_hang' => 'KG_KHAC', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'KG1', 'ho_ten' => 'Người Theo Công',
	'cua_hang' => $CHINH, 'luong_co_ban' => 10000000, 'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array( 'ngayCongThang' => 26 ), $u_ad );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-08-04',
	'ma_nv' => 'KG1', 'hau_to' => '', 'ho_ten' => 'Người Theo Công',
	'gio_vao_giay' => VHCC_DB::giay( '08:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '17:00:00' ), 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-08-05',
	'ma_nv' => 'KG1', 'hau_to' => 'CD', 'ho_ten' => 'Người Theo Công',
	'gio_vao_giay' => VHCC_DB::giay( '20:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '04:00:00' ) + VHCC_DB::NGAY_GIAY,
	'nguon' => 'may' ) );

$dong_cua = function () use ( $CHINH, $TH ) {
	$b = VHCC_BangLuong::dung( $CHINH, $TH );
	$ra = array();
	foreach ( (array) $b['dong'] as $d ) { if ( 'KG1' === $d['ma'] ) { $ra[] = $d; } }
	return $ra;
};
$d0 = $dong_cua();
t( 'dựng cảnh: có dòng chính + dòng Ca đêm', 2 === count( $d0 ) && ! empty( $d0[1]['laDem'] ), $d0 );

/* ── Dữ liệu rác bảng nhập CŨ đã lưu: "Ca đêm 1:00" và một dòng giờ không việc ── */
$gio_tong = (float) $d0[0]['gioTong'];
$r = VHCC_ChotLuong::dat( $u_ad, $CHINH, $TH, 'KG1',
	array( array( 'viec' => 'Ca đêm', 'gio' => '1:00' ) ), $gio_tong, null );
t( 'dựng cảnh: lưu được dòng giờ rác kiểu bảng nhập cũ', ! empty( $r['ok'] ), $r );
t( '   và nó có trong sổ', 1 === count( VHCC_ChotLuong::cua( $CHINH, $TH, 'KG1' ) ) );

$d1 = $dong_cua();
t( '🔴 dòng giờ đã lỡ lưu KHÔNG mọc thành hàng lương thừa (vẫn đúng 2 dòng: chính + Ca đêm)',
	2 === count( $d1 ), array_map( function ( $d ) { return $d['cv'] . '|' . $d['cheDo'] . '|' . $d['gio']; }, $d1 ) );
$gio_dong = 0;
foreach ( $d1 as $d ) { if ( 'gio' === $d['cheDo'] ) { $gio_dong++; } }
t( '   không còn dòng nào tính theo giờ', 0 === $gio_dong );

/* ── Màn Bảng lương: bảng nhập cả cơ sở ── */
update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '662288' );
t( 'dựng cảnh: Admin đăng nhập', ! empty( $kq['ok'] ), $kq );
$tok = $kq['token'];
$web = function ( $get, $post = array() ) use ( $tok ) {
	$_COOKIE = array( VHCC_Web::COOKIE => $tok );
	$_GET  = $get;
	$_POST = $post ? array_merge( array( 'ky' => VHCC_Web::chu_ky( $tok ) ), $post ) : array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
};
$h = $web( array( 'man' => 'luong', 'lcs' => $CHINH, 'lth' => $TH ) );
$i = strpos( $h, '<a id="bangnhap"></a>' );
t( 'dựng cảnh: có bảng nhập lương cả cơ sở', false !== $i, substr( $h, 0, 300 ) );
$nhap = (string) substr( $h, (int) $i, (int) strpos( $h, '</form>', (int) $i ) - (int) $i );
t( '🔴 bảng nhập KHÔNG có ô giờ việc phụ (b[..][dong])', false === strpos( $nhap, '[dong]' ), $nhap );
t( '🔴 bảng nhập KHÔNG có dòng "＋ thêm việc"', false === strpos( $nhap, 'thêm việc' ) );
t( '   Ca đêm hiện dạng chỉ đọc "X công đêm"', false !== strpos( $nhap, '↳ Ca đêm' ) && false !== strpos( $nhap, 'công đêm</span>' ), $nhap );
t( '   cột ghi "Công", không "Giờ làm"', false !== strpos( $nhap, '>Công</th>' ) && false === strpos( $nhap, 'Giờ làm</th>' ) );

/* Lưu cả bảng -> dọn luôn dòng giờ rác, kể cả khi biểu mẫu cũ (tab mở từ trước) còn gửi dòng. */
$web( array( 'man' => 'luong', 'lcs' => $CHINH, 'lth' => $TH ), array( 'viec' => 'chot_luong_bang',
	'ccs' => $CHINH, 'cth' => $TH, 'b' => array( 'KG1' => array( 'chinh' => '', 'thang' => '1',
		'lcb' => '10000000', 'cong_yc' => '26',
		'dong' => array( array( 'viec' => 'Ca đêm', 'gio' => '1:00' ), array( 'viec' => '', 'gio' => '1:00' ) ) ) ) ) );
t( '🔴 bấm "Lưu cả bảng" -> dòng giờ rác bị xoá khỏi sổ, không ghi thêm',
	array() === VHCC_ChotLuong::cua( $CHINH, $TH, 'KG1' ), VHCC_ChotLuong::cua( $CHINH, $TH, 'KG1' ) );

/* ── Khối "nhập ▾" từng người (màn Bảng công) ── */
VHCC_ChotLuong::dat( $u_ad, $CHINH, $TH, 'KG1', array( array( 'viec' => 'Ca đêm', 'gio' => '1:00' ) ), $gio_tong, null );
$h = $web( array( 'man' => 'luong', 'lcs' => $CHINH, 'lth' => $TH, 'clm' => 'KG1' ) );
$i = strpos( $h, 'name="viec" value="chot_luong"' );
t( 'dựng cảnh: khối nhập từng người mở ra', false !== $i );
$kh = (string) substr( $h, (int) $i, (int) strpos( $h, '</form>', (int) $i ) - (int) $i );
t( '🔴 khối nhập từng người KHÔNG có ô "giờ ăn đơn giá khác"', false === strpos( $kh, 'cl_gio[' ), $kh );
t( '   và không có ô "giờ tự tính"', false === strpos( $kh, 'data-clchinh' ) );
t( '   nói ra: tính theo công', false !== strpos( $kh, 'tính <b>theo công</b>' ) );
$web( array( 'man' => 'cham', 'ccs' => $CHINH, 'cth' => $TH, 'clm' => 'KG1' ), array( 'viec' => 'chot_luong',
	'ccs' => $CHINH, 'cth' => $TH, 'cl_ma' => 'KG1', 'cl_thang' => '1', 'cl_lcb' => '10000000',
	'cl_viec' => array( 'Ca đêm' ), 'cl_gio' => array( '1:00' ) ) );
t( '🔴 lưu khối từng người -> dòng giờ bị dọn, không ghi thêm',
	array() === VHCC_ChotLuong::cua( $CHINH, $TH, 'KG1' ), VHCC_ChotLuong::cua( $CHINH, $TH, 'KG1' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — cơ sở theo công: bảng nhập không ô giờ, không sinh thừa hàng.\n";
