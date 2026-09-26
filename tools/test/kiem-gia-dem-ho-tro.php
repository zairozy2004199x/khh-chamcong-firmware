<?php
/**
 * KIỂM "Ô GIÁ 1 CÔNG ĐÊM NGAY DƯỚI BẢNG LƯƠNG" + "Ô HỖ TRỢ (TIỀN CƠM…) TRONG BẢNG NHẬP".
 *
 * Anh Thắng 26/09/2026: *"Chỗ set giá lương công đêm chỗ nào, không có chỗ nhập, với lương hỗ
 * trợ như giữ xe, hỗ trợ tiền cơm … nếu có thêm ô nhập lương hỗ trợ"* / *"nếu có thì sẵn chỗ
 * nhập để nếu có, kế toán sẽ set"*.
 *
 * Chạy: php tools/test/kiem-gia-dem-ho-tro.php
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

$CHINH = 'GD_VP'; $PHU = 'GD_SETUP'; $TH = '2026-08';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CHINH, 'bo_phan' => 'Văn phòng' ) );
VHCC_Luong::dat_ghep( $u_ad, array( $PHU => $CHINH ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'GDAD', 'ho_ten' => 'Quản Trị GD',
	'pin_dang_nhap' => '773399', 'vai_tro' => 'Admin', 'cua_hang' => 'GD_KHAC', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'GD1', 'ho_ten' => 'Người Ca Đêm',
	'cua_hang' => $CHINH, 'luong_co_ban' => 10000000, 'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array( 'ngayCongThang' => 26 ), $u_ad );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => '2026-08-04',
	'ma_nv' => 'GD1', 'hau_to' => '', 'ho_ten' => 'Người Ca Đêm',
	'gio_vao_giay' => VHCC_DB::giay( '08:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '17:00:00' ), 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-08-05',
	'ma_nv' => 'GD1', 'hau_to' => 'CD', 'ho_ten' => 'Người Ca Đêm',
	'gio_vao_giay' => VHCC_DB::giay( '20:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '04:00:00' ) + VHCC_DB::NGAY_GIAY,
	'nguon' => 'may' ) );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '773399' );
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
$dem_cua = function () use ( $CHINH, $TH ) {
	foreach ( VHCC_BangLuong::dung( $CHINH, $TH )['dong'] as $d ) { if ( 'GD1' === $d['ma'] && ! empty( $d['laDem'] ) ) { return $d; } }
	return null;
};
$chinh_cua = function () use ( $CHINH, $TH ) {
	foreach ( VHCC_BangLuong::dung( $CHINH, $TH )['dong'] as $d ) { if ( 'GD1' === $d['ma'] && ! empty( $d['laChinh'] ) ) { return $d; } }
	return null;
};
$GET = array( 'man' => 'luong', 'lcs' => $CHINH, 'lth' => $TH );

/* ── 1. Ô Giá 1 công đêm đứng ngay dưới bảng lương ── */
$h = $web( $GET );
t( '🔴 màn Bảng lương cơ sở theo công có ô "Giá 1 công đêm"', false !== strpos( $h, 'name="gia_dem"' ) );
t( '   và KHÔNG còn khối "Đơn giá giờ" vô nghĩa', false === strpos( $h, 'Đơn giá giờ — ' ) );
t( '   nói rõ đang CHƯA KHAI', false !== strpos( $h, 'CHƯA KHAI' ) );
$d = $dem_cua();
t( 'dựng cảnh: dòng Ca đêm chưa có tiền', $d && null === $d['luongChinh'], $d );

$web( $GET, array( 'viec' => 'gia_cong_dem', 'ccs' => $CHINH, 'gia_dem' => '150.000' ) );
t( '🔴 bấm Lưu -> giá công đêm vào cấu hình', 150000.0 === (float) VHCC_Luong::vp_cfg( $CHINH )['demGiaCong'],
	VHCC_Luong::vp_cfg( $CHINH )['demGiaCong'] );
$d = $dem_cua();
t( '🔴 dòng Ca đêm ra tiền = công đêm × giá', $d && abs( (float) $d['luongChinh'] - (float) $d['congThuc'] * 150000 ) < 0.01
	&& (float) $d['luongChinh'] > 0, $d );
$h = $web( $GET );
t( '   ô hiện lại đúng số vừa lưu', false !== strpos( $h, 'name="gia_dem" inputmode="numeric" value="150000"' ) );

/* Khối đã khai riêng giá công đêm: lưu phải vào bản riêng, không thì bảng lương không đổi. */
VHCC_Luong::dat_cfg_khoi( $u_ad, 'Văn phòng', array( 'demGiaCong' => 100000 ) );
t( 'dựng cảnh: khối riêng đang thắng', 100000.0 === (float) VHCC_Luong::vp_cfg( $CHINH )['demGiaCong'] );
$web( $GET, array( 'viec' => 'gia_cong_dem', 'ccs' => $CHINH, 'gia_dem' => '200000' ) );
t( '🔴 khối có giá riêng -> lưu vào bản riêng, bảng lương thật sự đổi',
	200000.0 === (float) VHCC_Luong::vp_cfg( $CHINH )['demGiaCong'], VHCC_Luong::vp_cfg( $CHINH )['demGiaCong'] );

/* ── 2. Ô hỗ trợ trong bảng nhập cả cơ sở ── */
t( '🔴 có khoản cộng "HT tiền cơm"', isset( VHCC_ChotLuong::CONG['htCom'] ) );
$h = $web( $GET );
t( '🔴 bảng nhập cả cơ sở có ô HT tiền cơm cho từng người', false !== strpos( $h, 'name="b[GD1][cong][htCom]"' ) );
t( '   và ô HT giữ xe', false !== strpos( $h, 'name="b[GD1][cong][htXe]"' ) );
$web( $GET, array( 'viec' => 'chot_luong_bang', 'ccs' => $CHINH, 'cth' => $TH,
	'b' => array( 'GD1' => array( 'chinh' => '', 'thang' => '1', 'lcb' => '10000000', 'cong_yc' => '26',
		'tien' => '1', 'cong' => array( 'htCom' => '500.000', 'htXe' => '200000' ), 'tru' => array() ) ) ) );
$ti = VHCC_ChotLuong::tien_cua( $CHINH, $TH, 'GD1' );
t( '🔴 Lưu cả bảng -> HT tiền cơm vào sổ', isset( $ti['cong']['htCom'] ) && 500000.0 === (float) $ti['cong']['htCom'], $ti );
$h = $web( $GET );
t( '   mở lại bảng nhập thấy đúng số vừa gõ trong ô', false !== strpos( $h, 'name="b[GD1][cong][htCom]" value="500000"' ) );
$c = $chinh_cua();
t( '   và cộng vào tổng khoản cộng của người ấy', $c && 700000.0 === (float) $c['tongCong'], $c ? $c['tongCong'] : null );

/* Tờ .xlsx: cột S = HT tiền cơm. */
$x = VHCC_BangLuong::to_xlsx( $CHINH, $TH );
$h7 = $x['to'][0]['hang'][7];
t( '🔴 tờ .xlsx có tiêu đề cột "HT tiền cơm" ở cột S', is_array( $h7[18] ) && 'HT tiền cơm' === $h7[18]['v'], $h7[18] );
$co = false;
foreach ( $x['to'][0]['hang'] as $r ) {
	if ( isset( $r[1]['v'] ) && 'Người Ca Đêm' === $r[1]['v'] && isset( $r[18]['v'] ) && 500000.0 === (float) $r[18]['v'] ) { $co = true; }
}
t( '   và số tiền cơm nằm đúng cột S của dòng người ấy', $co );

/* Biểu mẫu cũ không có ô tiền (không dấu `tien`) thì KHÔNG xoá khoản đã gõ. */
$web( $GET, array( 'viec' => 'chot_luong_bang', 'ccs' => $CHINH, 'cth' => $TH,
	'b' => array( 'GD1' => array( 'chinh' => '', 'thang' => '1', 'lcb' => '10000000', 'cong_yc' => '26' ) ) ) );
$ti = VHCC_ChotLuong::tien_cua( $CHINH, $TH, 'GD1' );
t( '   biểu mẫu không chở ô tiền thì không xoá khoản đã gõ', isset( $ti['cong']['htCom'] ), $ti );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — ô giá công đêm dưới bảng lương, ô hỗ trợ trong bảng nhập.\n";
