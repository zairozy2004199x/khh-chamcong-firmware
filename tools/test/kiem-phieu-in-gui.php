<?php
/**
 * KIỂM "PHIẾU LƯƠNG IN A4 (LƯU PDF) + GỬI CẢ HAI KÊNH KHI CÔNG BỐ".
 *
 * Anh Thắng 26/09/2026: *"đến ngày công bố lương, mỗi người sẽ được 1 bản gửi tự động … dạng
 * pdf"* → *"cả 2 kênh"* (thông báo trong app + email).
 *
 * Chạy: php tools/test/kiem-phieu-in-gui.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

/* Hộp thư chuông (plugin Nội bộ) — giả lập: chỉ GHI LẠI tin gửi, để canh "gửi đúng người, có link". */
class VHNB_Bao {
	public static $ds = array();
	public static function gui( $ma, $nguon, $chu, $duong = '', $khoa = '', $tu = '' ) {
		self::$ds[] = array( 'ma' => $ma, 'chu' => $chu, 'link' => $duong );
	}
}

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 400 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;
$u_ad = array( 'role' => 'Admin', 'name' => 'Admin Thử' );

$CS = 'PI_SHOP'; $TH = '2026-08';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PIAD', 'ho_ten' => 'Quản Trị PI',
	'pin_dang_nhap' => '995511', 'vai_tro' => 'Admin', 'cua_hang' => 'PI_KHAC', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PI1', 'ho_ten' => 'Người Có Email',
	'cua_hang' => $CS, 'chuc_vu' => 'NV', 'vai_tro' => 'Nhân viên', 'email' => 'nv1@vi-du.test',
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'PI2', 'ho_ten' => 'Người Không Email',
	'cua_hang' => $CS, 'chuc_vu' => 'NV', 'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
VHCC_GiaGio::dat_coso( $u_ad, $CS, array( 'NV' => 20000 ) );
foreach ( array( 'PI1', 'PI2' ) as $ma ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => '2026-08-10', 'ma_nv' => $ma,
		'hau_to' => '', 'ho_ten' => $ma, 'gio_vao_giay' => VHCC_DB::giay( '08:00:00' ),
		'gio_ra_giay' => VHCC_DB::giay( '16:00:00' ), 'nguon' => 'may' ) );
}
VHCC_ChotLuong::dat_tien( $u_ad, $CS, $TH, 'PI1', array( 'kid' => 400000, 'htCom' => 250000 ), array() );
VHCC_GiuLuong::dat_khoan( $u_ad, array( 'kid' ), $TH );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '995511' );
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
$G = array( 'man' => 'luong', 'lcs' => $CS, 'lth' => $TH );

/* ── 1. Công bố -> gửi cả hai kênh ── */
$GLOBALS['VHCP_MAIL'] = array();
$h = $web( $G, array( 'viec' => 'phieu_cb', 'pl_cs' => $CS, 'pl_th' => $TH ) );
t( 'dựng cảnh: đã công bố', VHCC_PhieuLuong::da_cong_bo( $CS, $TH ) );
$mail = $GLOBALS['VHCP_MAIL'];
t( '🔴 công bố -> gửi email cho đúng người có email (1 thư)', 1 === count( $mail ) && 'nv1@vi-du.test' === $mail[0]['to'], $mail );
t( '   tiêu đề nói tháng', false !== strpos( $mail[0]['subject'], 'Phiếu lương tháng 8/2026' ), $mail[0]['subject'] );
t( '   thư mang link trang in có chữ ký', false !== strpos( $mail[0]['message'], 'vhcc_phieu=1' ) && false !== strpos( $mail[0]['message'], 'k=' ) );
t( '🔴 thư KHÔNG mang con số lương', false === strpos( $mail[0]['message'], '160.000' ) && false === strpos( $mail[0]['message'], '250.000' ) );
$bao = VHNB_Bao::$ds;
t( '🔴 chuông trong app cho CẢ HAI người (kể cả người không có email)', 2 === count( $bao ), $bao );
t( '   tin chuông mang link trang in của đúng người', false !== strpos( $bao[0]['link'], 'vhcc_phieu=1' )
	&& false !== strpos( $bao[0]['link'], 'm=' . strtolower( $bao[0]['ma'] ) ), $bao );
$g = VHCC_GuiPhieu::lan_gui( $CS, $TH );
t( '   nhật ký gửi: 2 thông báo · 1 email · 1 chưa có email', $g && 2 === $g['bao'] && 1 === $g['mail'] && 1 === $g['khongMail'], $g );
$h = $web( $G );
t( '   màn Bảng lương nói ra đã gửi', false !== strpos( $h, 'Đã gửi phiếu: thông báo <b>2</b>' ) );

/* ── 2. Trang in ── */
$link = VHCC_PhieuLuong::link_in( 'PI1', $CS, $TH );
parse_str( (string) parse_url( $link, PHP_URL_QUERY ), $q );
$in = VHCC_PhieuLuong::trang_in( $q['m'], $q['cs'], $q['th'], $q['k'] );
t( '🔴 trang in có tiêu đề phiếu tháng', false !== strpos( $in, 'PHIẾU LƯƠNG THÁNG 8/2026' ), $in );
t( '   đúng họ tên, nút In / Lưu PDF, khổ A4', false !== strpos( $in, 'Người Có Email' ) && false !== strpos( $in, 'In / Lưu thành PDF' )
	&& false !== strpos( $in, 'size:A4' ) );
t( '   KHÔNG có tên người khác', false === strpos( $in, 'Người Không Email' ) );
t( '   có HT tiền cơm và TỔNG NHẬN', false !== strpos( $in, 'HT tiền cơm' ) && false !== strpos( $in, 'TỔNG NHẬN' ) );
t( '🔴 có mục khoản giữ lại (%KID tháng này, chưa trả)', false !== strpos( $in, 'Khoản giữ lại (chưa trả)' ) && false !== strpos( $in, '400.000đ' ) );
t( '   tổng = 8h × 20.000 + 250.000 (KID giữ lại, không cộng)', false !== strpos( $in, '410.000đ' ), $in );

$sai = VHCC_PhieuLuong::trang_in( 'pi2', $q['cs'], $q['th'], $q['k'] );
t( '🔴 đổi mã trong link (giữ chữ ký cũ) -> không mở được', false !== strpos( $sai, 'Link không hợp lệ' ) && false === strpos( $sai, 'Người Không Email' ) );
$web( $G, array( 'viec' => 'phieu_thu', 'pl_cs' => $CS, 'pl_th' => $TH ) );
$thu = VHCC_PhieuLuong::trang_in( $q['m'], $q['cs'], $q['th'], $q['k'] );
t( '🔴 kế toán Thu lại -> link thôi mở', false !== strpos( $thu, 'đã được thu lại' ) && false === strpos( $thu, 'TỔNG NHẬN' ) );
$web( $G, array( 'viec' => 'phieu_cb', 'pl_cs' => $CS, 'pl_th' => $TH ) );

/* ── 3. Phiếu trên trạm có link in ── */
$p = VHCC_PhieuLuong::phieu( array( 'ma_nv' => 'PI1', 'coso' => $CS ), $CS, $TH );
t( '🔴 phiếu trên trạm trả link in của chính người ấy', ! empty( $p['linkIn'] ) && false !== strpos( $p['linkIn'], 'm=pi1' ), $p );
t( '   và khoản giữ lại', isset( $p['giu']['kid'] ), $p );

/* ── 4. Ô email trong hồ sơ ── */
$luu = new ReflectionMethod( 'VHCC_Web', 'luu_ho_so' );
$luu->setAccessible( true );
$_POST = array( 'ma_nv' => 'PI2', 'coso_o' => array( $CS ), 'email' => 'khong-phai-email' );
$r = $luu->invoke( null, $u_ad );
t( '🔴 email sai dạng -> chối, không lưu', empty( $r['ok'] ), $r );
$_POST = array( 'ma_nv' => 'PI2', 'coso_o' => array( $CS ), 'email' => 'NV2@Vi-Du.test' );
$r = $luu->invoke( null, $u_ad );
$_POST = array();
t( '   email đúng -> lưu (chữ thường)', 'nv2@vi-du.test' === $wpdb->get_var( "SELECT email FROM " . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='PI2'" ), $r );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — phiếu in A4 + gửi cả hai kênh khi công bố.\n";
