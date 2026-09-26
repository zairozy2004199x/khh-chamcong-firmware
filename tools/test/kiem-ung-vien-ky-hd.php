<?php
/**
 * KIỂM "LINK ỨNG VIÊN TỰ ĐIỀN" + "KÝ HỢP ĐỒNG ĐIỆN TỬ" (`VHCC_TiepNhan`).
 *
 * Anh Thắng 26/09/2026: *"Xong làm 2 việc này đi em: Link để ứng viên tự điền thông tin · Ký hợp
 * đồng điện tử"*.
 *
 * Chạy: php tools/test/kiem-ung-vien-ky-hd.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

class VHNB_Bao {
	public static $ds = array();
	public static function gui( $ma, $nguon, $chu, $duong = '', $khoa = '', $tu = '' ) { self::$ds[] = array( 'ma' => $ma, 'chu' => $chu ); }
}

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 400 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;
$CS = 'UV_SHOP';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'UVAD', 'ho_ten' => 'Quản Trị UV', 'pin_dang_nhap' => '774422',
	'vai_tro' => 'Admin', 'cua_hang' => $CS, 'trang_thai_lam_viec' => 'Đang làm' ) );
update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '774422' );
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
$web( $G, array( 'viec' => 'tn_cty', 'c' => array( 'ten' => 'CTY THỬ', 'dia_chi' => 'x', 'mst' => '1', 'dai_dien' => 'Ông Giám Đốc', 'chuc_vu_dd' => 'Giám đốc', 'sdt' => '' ) ) );
$web( $G, array( 'viec' => 'tn_mau', 'tn_k' => '', 'm' => array( 'ten' => 'Thu ngân', 'tien_to' => 'UVT', 'vai_tro' => 'Nhân viên',
	'cach' => 'thang', 'luong' => '5000000', 'thu_viec' => '0', 'tv_pt' => '85', 'loai_hd' => 'xac_dinh', 'thoi_han' => '12', 'bhxh' => '1' ) ) );
$K = key( VHCC_TiepNhan::ds_mau() );

/* ══ A. Link mời — ứng viên gửi xong là tự tạo ══ */
$web( $G, array( 'viec' => 'tn_moi', 'mv' => array( 'mau' => $K, 'coso' => $CS, 'ngay_vao' => '2026-10-05', 'luong_sua' => '', 'luong_bh' => '', 'tu_tao' => '1' ) ) );
$ds = VHCC_TiepNhan::ds_moi();
t( '🔴 tạo được link mời', 1 === count( $ds ), $ds );
$T = key( $ds );
$h = $web( $G );
t( '   màn hiện link để sao chép', false !== strpos( $h, 'vhcc_ungvien=1' ) && false !== strpos( $h, 'chờ ứng viên' ) );
$cong = function ( $get, $post = array() ) { $_POST = $post; $r = VHCC_TiepNhan::phuc_vu_cong_khai( $get, $post, '1.2.3.4', 'May Thu' ); $_POST = array(); return $r; };
$gU = array( 'vhcc_ungvien' => '1', 't' => $T );
$p = $cong( $gU );
t( '🔴 ứng viên mở link thấy form + vị trí', false !== strpos( $p, 'name="uv[ho_ten]"' ) && false !== strpos( $p, 'Thu ngân' ) );
$p = $cong( $gU, array( 'uv' => array( 'ho_ten' => 'Phạm Ứng Viên', 'cccd' => '12', 'sdt' => '0900' ) ) );
t( '   CCCD sai -> báo lỗi, vẫn chờ', false !== strpos( $p, 'CCCD phải 9–12' ) && 'cho' === VHCC_TiepNhan::ds_moi()[ $T ]['tt'] );
$p = $cong( $gU, array( 'uv' => array( 'ho_ten' => 'Phạm Ứng Viên', 'cccd' => '000000000777', 'sdt' => '0900111222',
	'email' => 'uv@vi-du.test', 'ngay_sinh' => '1999-01-02', 'dia_chi' => 'Q1' ) ) );
$x = VHCC_TiepNhan::ds_moi()[ $T ];
t( '🔴 gửi hợp lệ -> cảm ơn + TỰ TẠO hồ sơ', false !== strpos( $p, 'Cảm ơn' ) && 'da_tao' === $x['tt'] && 'UVT0001' === $x['ma'], $x );
$hs = VHCC_NhanSu::ho_so( 'UVT0001' );
t( '   hồ sơ đúng thông tin ứng viên điền', $hs && 'Phạm Ứng Viên' === $hs['ho_ten'] && '000000000777' === $hs['cccd'] && 'uv@vi-du.test' === $hs['email'] );
t( '   người tạo link nhận chuông', (bool) array_filter( VHNB_Bao::$ds, function ( $b ) { return 'UVAD' === $b['ma'] && false !== strpos( $b['chu'], 'Phạm Ứng Viên' ); } ) );
$p = $cong( $gU, array( 'uv' => array( 'ho_ten' => 'Lần Hai', 'cccd' => '000000000778', 'sdt' => '1' ) ) );
t( '🔴 link dùng một lần', false !== strpos( $p, 'đã được dùng' ) && ! VHCC_NhanSu::ho_so( 'UVT0002' ) );

/* ══ B. Link chờ duyệt ══ */
$r = VHCC_TiepNhan::tao_moi( array( 'role' => 'Admin', 'name' => 'A', 'coso' => $CS ), array( 'mau' => $K, 'coso' => $CS, 'ngay_vao' => '2026-10-06', 'tu_tao' => '' ) );
$T2 = $r['t'];
$cong( array( 'vhcc_ungvien' => '1', 't' => $T2 ), array( 'uv' => array( 'ho_ten' => 'Võ Chờ Duyệt', 'cccd' => '000000000888', 'sdt' => '0911' ) ) );
t( '🔴 không tự tạo -> chờ duyệt, chưa có hồ sơ', 'da_dien' === VHCC_TiepNhan::ds_moi()[ $T2 ]['tt'] && ! VHCC_NhanSu::ho_so( 'UVT0002' ) );
$h = $web( $G );
t( '   màn có nút Duyệt & tạo + tên ứng viên', false !== strpos( $h, 'tn_duyet_moi' ) && false !== strpos( $h, 'Võ Chờ Duyệt' ) );
$web( $G, array( 'viec' => 'tn_duyet_moi', 'tn_t' => $T2 ) );
t( '🔴 bấm Duyệt -> tạo hồ sơ UVT0002', 'da_tao' === VHCC_TiepNhan::ds_moi()[ $T2 ]['tt'] && VHCC_NhanSu::ho_so( 'UVT0002' ) );

/* ══ C. Hết hạn · huỷ · CCCD trùng ══ */
$r = VHCC_TiepNhan::tao_moi( array( 'role' => 'Admin', 'name' => 'A', 'coso' => $CS ), array( 'mau' => $K, 'coso' => $CS, 'tu_tao' => '1' ) );
$T3 = $r['t'];
$p = $cong( array( 'vhcc_ungvien' => '1', 't' => $T3 ), array( 'uv' => array( 'ho_ten' => 'Trùng', 'cccd' => '000000000777', 'sdt' => '1' ) ) );
t( '🔴 CCCD đã có hồ sơ -> ứng viên được báo, không tạo', false !== strpos( $p, 'đã có hồ sơ' ) );
$d = VHCC_Luong::cai_dat( VHCC_TiepNhan::O_MOI, array() ); $d[ $T3 ]['het'] = '2020-01-01 00:00:00';
VHCC_Luong::dat_cai_dat( VHCC_TiepNhan::O_MOI, $d, array( 'name' => 't' ) );
t( '🔴 link hết hạn -> không dùng được', false !== strpos( $cong( array( 'vhcc_ungvien' => '1', 't' => $T3 ) ), 'hết hạn' ) );
$r = VHCC_TiepNhan::tao_moi( array( 'role' => 'Admin', 'name' => 'A', 'coso' => $CS ), array( 'mau' => $K, 'coso' => $CS ) );
$web( $G, array( 'viec' => 'tn_huy_moi', 'tn_t' => $r['t'] ) );
t( '   huỷ link -> trang báo đã huỷ', false !== strpos( $cong( array( 'vhcc_ungvien' => '1', 't' => $r['t'] ) ), 'đã bị huỷ' ) );
t( '   token sai -> không hợp lệ', false !== strpos( $cong( array( 'vhcc_ungvien' => '1', 't' => 'bia' ) ), 'không hợp lệ' ) );

/* ══ D. Ký hợp đồng điện tử ══ */
$link = VHCC_TiepNhan::link_bo( 'UVT0001' );
parse_str( (string) parse_url( $link, PHP_URL_QUERY ), $q );
$gB = array( 'vhcc_nhanviec' => '1', 'm' => $q['m'], 'k' => $q['k'] );
$b = $cong( $gB );
t( '🔴 bộ hồ sơ có khung ký điện tử + ô vẽ chữ ký', false !== strpos( $b, 'Ký hợp đồng điện tử' ) && false !== strpos( $b, '<canvas id="kv"' ) );
t( '   phía công ty đã ký khi duyệt tiếp nhận', false !== strpos( $b, 'Đã ký điện tử khi duyệt tiếp nhận' ) );
$anh = 'data:image/png;base64,' . base64_encode( 'png-thu' );
$b = $cong( $gB, array( 'ky_hd' => '1', 'ky_ten' => 'Người Khác', 'ky_dong_y' => '1', 'ky_anh' => $anh ) );
t( '🔴 gõ sai tên -> không ký', false !== strpos( $b, 'không khớp' ) && empty( VHCC_TiepNhan::ban_ghi( 'UVT0001' )['nvKy'] ) );
$b = $cong( $gB, array( 'ky_hd' => '1', 'ky_ten' => 'Phạm Ứng Viên', 'ky_anh' => $anh ) );
t( '   chưa tích đồng ý -> không ký', false !== strpos( $b, 'Chưa tích' ) && empty( VHCC_TiepNhan::ban_ghi( 'UVT0001' )['nvKy'] ) );
$GLOBALS['VHCP_MAIL'] = array();
$b = $cong( $gB, array( 'ky_hd' => '1', 'ky_ten' => 'pham  ung vien', 'ky_dong_y' => '1', 'ky_anh' => $anh ) );
$bg = VHCC_TiepNhan::ban_ghi( 'UVT0001' );
t( '🔴 ký hợp lệ (không dấu, hoa thường, khoảng trắng thừa vẫn khớp)', ! empty( $bg['nvKy'] ), $b );
t( '   ghi giờ, IP, thiết bị, ảnh chữ ký, mã băm nội dung', $bg['nvKy']['ip'] === '1.2.3.4' && 'May Thu' === $bg['nvKy']['ua']
	&& $anh === $bg['nvKy']['anh'] && VHCC_TiepNhan::bam_hd( $bg ) === $bg['nvKy']['bam'] );
t( '   hợp đồng hiện chữ ký + "Đã ký điện tử" + mã xác thực, hết khung ký', false !== strpos( $b, 'src="' . $anh . '"' )
	&& false !== strpos( $b, '✔ Đã ký điện tử' ) && false !== strpos( $b, 'mã xác thực' ) && false === strpos( $b, '<canvas' ) );
t( '   email xác nhận đã ký', 1 === count( $GLOBALS['VHCP_MAIL'] ) && false !== strpos( $GLOBALS['VHCP_MAIL'][0]['subject'], 'Xác nhận đã ký' ) );
$b = $cong( $gB, array( 'ky_hd' => '1', 'ky_ten' => 'Phạm Ứng Viên', 'ky_dong_y' => '1' ) );
t( '🔴 không ký lại được (báo lỗi, chữ ký cũ giữ nguyên cả ảnh)', false !== strpos( $b, '✖ Hợp đồng đã được ký lúc' )
	&& $anh === VHCC_TiepNhan::ban_ghi( 'UVT0001' )['nvKy']['anh'] );
$b = $cong( array( 'vhcc_nhanviec' => '1', 'm' => 'uvt0002', 'k' => $q['k'] ), array( 'ky_hd' => '1', 'ky_ten' => 'Võ Chờ Duyệt', 'ky_dong_y' => '1' ) );
t( '🔴 chữ ký link sai -> không ký hộ người khác', empty( VHCC_TiepNhan::ban_ghi( 'UVT0002' )['nvKy'] ) );
$link2 = VHCC_TiepNhan::link_bo( 'UVT0002' ); parse_str( (string) parse_url( $link2, PHP_URL_QUERY ), $q2 );
$cong( array( 'vhcc_nhanviec' => '1', 'm' => $q2['m'], 'k' => $q2['k'] ), array( 'ky_hd' => '1', 'ky_ten' => 'Võ Chờ Duyệt', 'ky_dong_y' => '1', 'ky_anh' => '<script>' ) );
t( '   ảnh chữ ký không phải PNG -> bỏ, vẫn ký bằng tên gõ', '' === VHCC_TiepNhan::ban_ghi( 'UVT0002' )['nvKy']['anh'] );
$h = $web( $G );
t( '   màn Đã tiếp nhận hiện "NV đã ký HĐ"', false !== strpos( $h, 'NV đã ký HĐ' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — link ứng viên tự điền + ký hợp đồng điện tử.\n";
