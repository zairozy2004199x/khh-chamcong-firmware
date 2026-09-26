<?php
/**
 * KIỂM "KHOẢN GIỮ LẠI, TRẢ SAU" — luồng tích chọn (`VHCC_GiuLuong`).
 *
 * Anh Thắng 26/09/2026: *"có 1 số cột sẽ chỉ trả vào cuối năm hoặc giữa năm, do giám đốc chọn,
 * nhưng mà vẫn set hàng tháng để nhân viên biết, nhưng không trả vào lương liền mà giữ đó"* /
 * *"Tạo luồng tích chọn để sau quản lý quyết định"*.
 *
 * Chạy: php tools/test/kiem-giu-luong.php
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
$u_ad = array( 'role' => 'Admin', 'name' => 'Admin Thử' );

$CS = 'GL_SHOP'; $MA = 'GL1';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'GLAD', 'ho_ten' => 'Quản Trị GL',
	'pin_dang_nhap' => '884411', 'vai_tro' => 'Admin', 'cua_hang' => 'GL_KHAC', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $MA, 'ho_ten' => 'Người Có KID',
	'cua_hang' => $CS, 'chuc_vu' => 'NV', 'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
VHCC_GiaGio::dat_coso( $u_ad, $CS, array( 'NV' => 20000 ) );
foreach ( array( '2026-06', '2026-07', '2026-08' ) as $th ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $th . '-10', 'ma_nv' => $MA,
		'hau_to' => '', 'ho_ten' => 'Người Có KID', 'gio_vao_giay' => VHCC_DB::giay( '08:00:00' ),
		'gio_ra_giay' => VHCC_DB::giay( '16:00:00' ), 'nguon' => 'may' ) );
	VHCC_ChotLuong::dat_tien( $u_ad, $CS, $th, $MA, array( 'kid' => 500000, 'target' => 300000 ), array() );
}
$dong = function ( $th ) use ( $CS, $MA ) {
	foreach ( VHCC_BangLuong::dung( $CS, $th )['dong'] as $d ) { if ( $MA === $d['ma'] && ! empty( $d['laChinh'] ) ) { return $d; } }
	return null;
};
t( 'dựng cảnh: chưa giữ gì -> tháng 6 cộng đủ 800.000', 800000.0 === (float) $dong( '2026-06' )['tongCong'], $dong( '2026-06' ) );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '884411' );
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
$g = function ( $th ) use ( $CS ) { return array( 'man' => 'luong', 'lcs' => $CS, 'lth' => $th ); };

/* ── 1. Màn có khối tích chọn, mặc định không giữ gì ── */
$h = $web( $g( '2026-06' ) );
t( '🔴 màn Bảng lương có khối "Khoản giữ lại — trả sau"', false !== strpos( $h, 'Khoản giữ lại — trả sau' ) );
t( '   ô tích cho từng khoản cộng (có %KID)', false !== strpos( $h, 'name="giu_k[]" value="kid"' ) );
t( '   mặc định chưa tích khoản nào', false === strpos( $h, 'value="kid" checked' ) );

/* ── 2. Tích %KID từ tháng 6 ── */
$web( $g( '2026-06' ), array( 'viec' => 'giu_khoan', 'ccs' => $CS, 'cth' => '2026-06', 'giu_k' => array( 'kid' ) ) );
t( '🔴 tích %KID -> tháng 6, 7, 8 đều giữ', VHCC_GiuLuong::dang_giu( 'kid', '2026-06' )
	&& VHCC_GiuLuong::dang_giu( 'kid', '2026-08' ) && ! VHCC_GiuLuong::dang_giu( 'kid', '2026-05' ) );
$d6 = $dong( '2026-06' );
t( '🔴 tháng 6: %KID KHÔNG cộng vào lương (chỉ còn Target 300.000)', 300000.0 === (float) $d6['tongCong'], $d6['tongCong'] );
t( '   nhưng số vẫn còn đó, ghi là giữ lại', isset( $d6['giu']['kid'] ) && 500000.0 === (float) $d6['giu']['kid'], $d6['giu'] );
t( '   cột %KID tháng 6 trống', empty( $d6['cong']['kid'] ) );
$tl = VHCC_GiuLuong::tich_luy( $CS, $MA, '2026-08' );
t( '🔴 tích luỹ tới tháng 8: đã giữ 1.500.000', 1500000.0 === (float) $tl['kid']['con'], $tl );

/* ── 3. Bỏ tích từ tháng 8: tháng cũ KHÔNG đổi ── */
$web( $g( '2026-08' ), array( 'viec' => 'giu_khoan', 'ccs' => $CS, 'cth' => '2026-08', 'giu_k' => array() ) );
t( '🔴 bỏ tích ở tháng 8 -> tháng 8 thôi giữ', ! VHCC_GiuLuong::dang_giu( 'kid', '2026-08' ) );
t( '   tháng 6, 7 VẪN giữ (quá khứ không bị viết lại)', VHCC_GiuLuong::dang_giu( 'kid', '2026-07' ) );
t( '   tháng 8 cộng đủ lại 800.000', 800000.0 === (float) $dong( '2026-08' )['tongCong'] );
$tl = VHCC_GiuLuong::tich_luy( $CS, $MA, '' );
t( '   tích luỹ còn 1.000.000 (tháng 6 + 7)', 1000000.0 === (float) $tl['kid']['con'], $tl );

/* ── 4. Trả một phần vào tháng 8 ── */
$h = $web( $g( '2026-08' ) );
t( '🔴 bảng tích luỹ có ô tích để trả', false !== strpos( $h, 'name="gt[gl1][kid]"' ), substr( $h, (int) strpos( $h, 'Tích luỹ tới' ), 1500 ) );
t( '   ô số mặc định = còn giữ', false !== strpos( $h, 'name="gs[gl1][kid]" value="1000000"' ) );
$web( $g( '2026-08' ), array( 'viec' => 'giu_tra', 'ccs' => $CS, 'cth' => '2026-08',
	'gt' => array( 'gl1' => array( 'kid' => '1' ) ), 'gs' => array( 'gl1' => array( 'kid' => '600.000' ) ) ) );
$d8 = $dong( '2026-08' );
t( '🔴 trả 600.000 -> cột %KID tháng 8 = 500.000 của tháng + 600.000 trả', 1100000.0 === (float) $d8['cong']['kid'], $d8['cong'] );
t( '   tổng cộng tháng 8 = 1.400.000', 1400000.0 === (float) $d8['tongCong'], $d8['tongCong'] );
t( '   ghi chú nói ra đã trả khoản giữ', false !== strpos( implode( ' ', VHCC_GiuLuong::ghi_chu( $d8 ) ), 'trả khoản đã giữ' ) );
t( '   còn giữ 400.000', 400000.0 === (float) VHCC_GiuLuong::tich_luy( $CS, $MA, '' )['kid']['con'] );
$ds_t = VHCC_GiuLuong::ds_tra( $CS, $MA );
t( '   sổ trả ghi ai bấm', 1 === count( $ds_t ) && '' !== (string) $ds_t[0]['boi'] && '2026-08' === $ds_t[0]['thang'], $ds_t );

/* ── 5. Không trả quá số còn giữ ── */
$r = VHCC_GiuLuong::tra( $u_ad, $CS, '2026-08', array( array( 'ma' => $MA, 'khoan' => 'kid', 'tien' => 500000 ) ) );
t( '🔴 trả 500.000 khi chỉ còn 400.000 -> chối, nói rõ', 0 === $r['xong'] && $r['hong'] && false !== strpos( $r['hong'][0], '400.000' ), $r );
/* Trả ở tháng 6 thì chỉ tính số giữ TỚI tháng 6 (500.000) và không vượt số còn cả sổ (400.000). */
$r = VHCC_GiuLuong::tra( $u_ad, $CS, '2026-06', array( array( 'ma' => $MA, 'khoan' => 'target', 'tien' => 1 ) ) );
t( '   khoản chưa từng giữ -> chối', 0 === $r['xong'], $r );

/* ── 6. Huỷ lần trả ── */
$h = $web( $g( '2026-08' ) );
t( '🔴 danh sách "Đã trả vào lương tháng" có ô huỷ', false !== strpos( $h, 'name="gh[]" value="' . $ds_t[0]['id'] . '"' ) );
$web( $g( '2026-08' ), array( 'viec' => 'giu_huy', 'ccs' => $CS, 'cth' => '2026-08', 'gh' => array( $ds_t[0]['id'] ) ) );
t( '🔴 huỷ -> số quay lại còn giữ 1.000.000', 1000000.0 === (float) VHCC_GiuLuong::tich_luy( $CS, $MA, '' )['kid']['con'] );
t( '   tháng 8 về 800.000', 800000.0 === (float) $dong( '2026-08' )['tongCong'] );

/* ── 7. Tích lại sau khi bỏ: mở khoảng mới, không chồng ── */
VHCC_GiuLuong::dat_khoan( $u_ad, array( 'kid' ), '2026-08' );
$so = VHCC_GiuLuong::so_khoan();
t( '   tích lại -> hai khoảng: 06..07 và từ 08', 2 === count( $so['kid'] ) && '2026-07' === $so['kid'][0]['den']
	&& '2026-08' === $so['kid'][1]['tu'], $so );

/* ── 8. Tờ .xlsx ghi chú giữ lại ── */
$x = VHCC_BangLuong::to_xlsx( $CS, '2026-06' );
$co = false;
foreach ( $x['to'][0]['hang'] as $r_x ) {
	$cuoi = end( $r_x );
	if ( is_array( $cuoi ) && isset( $cuoi['v'] ) && false !== strpos( (string) $cuoi['v'], 'giữ lại tháng này' ) ) { $co = true; }
}
t( '🔴 tờ .xlsx tháng 6: cột NOTES ghi "giữ lại tháng này"', $co );

/* ── 9. Quyền ── */
$r = VHCC_GiuLuong::dat_khoan( array( 'role' => 'Nhân viên' ), array(), '2026-08' );
t( '🔴 nhân viên không đổi được khoản giữ', empty( $r['ok'] ) );
$r = VHCC_GiuLuong::tra( array( 'role' => 'Nhân viên' ), $CS, '2026-08', array() );
t( '   và không trả được', empty( $r['ok'] ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — khoản giữ lại, trả sau: tích chọn, tích luỹ, trả, huỷ.\n";
