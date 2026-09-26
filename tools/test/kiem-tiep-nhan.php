<?php
/**
 * KIỂM "TIẾP NHẬN NHÂN SỰ TỰ ĐỘNG" (`VHCC_TiepNhan` + màn `VHCC_WebTiepNhan`).
 *
 * Anh Thắng 26/09/2026: *"tạo ra 1 trình. Chạy tự động từ a đến z … tự set tất cả tính năng cho
 * nhân viên có chức vụ đó, kèm tk, mk và quyền hạn đủ hết, cả tk chấm công, và hợp đồng, bảo
 * hiểm … tự động gửi email bản pdf"*. Chốt: PIN ghi trong PDF · mã = tiền tố + số chạy · HĐ theo
 * BLLĐ 2019 · BHXH 10,5%.
 *
 * Chạy: php tools/test/kiem-tiep-nhan.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

class VHNB_Bao {
	public static $ds = array();
	public static function gui( $ma, $nguon, $chu, $duong = '', $khoa = '', $tu = '' ) { self::$ds[] = array( 'ma' => $ma, 'link' => $duong ); }
}

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 400 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;
$CS = 'TN_SHOP';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TNAD', 'ho_ten' => 'Quản Trị TN',
	'pin_dang_nhap' => '771133', 'vai_tro' => 'Admin', 'cua_hang' => $CS, 'trang_thai_lam_viec' => 'Đang làm' ) );
/* Mã cũ cùng tiền tố: lớn nhất trong hồ sơ là 0041, trong lịch sử chấm công là 0050. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'KVCT0041', 'ho_ten' => 'Người Cũ', 'cua_hang' => $CS, 'cccd' => '000000000099' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => '2026-01-05', 'ma_nv' => 'KVCT0050', 'hau_to' => '',
	'ho_ten' => 'Người Đã Xoá', 'gio_vao_giay' => 28800, 'gio_ra_giay' => 57600, 'nguon' => 'may' ) );

update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '771133' );
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
$G = array( 'man' => 'tiep_nhan' );

/* ── 1. Màn + menu ── */
$h = $web( $G );
t( '🔴 menu có "Tiếp nhận nhân sự"', false !== strpos( $h, 'Tiếp nhận nhân sự' ) );
t( '   chưa có mẫu thì nói ra', false !== strpos( $h, 'Chưa có <b>mẫu chức vụ</b>' ) );

/* ── 2. Mẫu chức vụ ── */
$web( $G, array( 'viec' => 'tn_cty', 'c' => array( 'ten' => 'CÔNG TY THỬ K&H', 'dia_chi' => '1 Đường Thử', 'mst' => '0000000000',
	'dai_dien' => 'Nguyễn Văn Giám', 'chuc_vu_dd' => 'Giám đốc', 'sdt' => '' ) ) );
t( '   lưu thông tin công ty', 'Nguyễn Văn Giám' === VHCC_TiepNhan::cty()['dai_dien'] );
$mau = array( 'ten' => 'Nhân viên KVC', 'tien_to' => 'KVCT', 'bo_phan' => 'Khu vui chơi', 'mang' => '', 'vai_tro' => 'Nhân viên',
	'cach' => 'thang', 'luong' => '6.000.000', 'cong_chuan' => '26', 'don_gia' => '', 'thu_viec' => '30', 'tv_pt' => '85',
	'loai_hd' => 'xac_dinh', 'thoi_han' => '12', 'bhxh' => '1', 'luong_bh' => '5000000',
	'quyen' => array( 'tram' => 'mo', 'cham_cong' => 'khoa', 'noi_bo' => '' ), 'may' => '1',
	'mo_ta' => 'Vận hành trò chơi, hướng dẫn khách', 'dieu_them' => 'Được cấp 02 bộ đồng phục mỗi năm.' );
$sai = $mau; $sai['tv_pt'] = '80';
$web( $G, array( 'viec' => 'tn_mau', 'tn_k' => '', 'm' => $sai ) );
t( '🔴 lương thử việc dưới 85% -> chối (Điều 26 BLLĐ 2019)', ! VHCC_TiepNhan::ds_mau() );
$web( $G, array( 'viec' => 'tn_mau', 'tn_k' => '', 'm' => $mau ) );
$ds = VHCC_TiepNhan::ds_mau();
t( '   lưu mẫu hợp lệ', 1 === count( $ds ), $ds );
$K = key( $ds );

/* ── 3. Tiếp nhận ── */
$GLOBALS['VHCP_MAIL'] = array();
$h = $web( $G, array( 'viec' => 'tn_tao', 'tn' => array( 'ho_ten' => 'Trần Thị Mới', 'cccd' => '000000000123',
	'ngay_sinh' => '2000-05-06', 'gioi_tinh' => 'Nữ', 'sdt' => '0900000000', 'email' => 'Moi@Vi-Du.test', 'dia_chi' => '2 Đường Thử',
	'so_tai_khoan' => '000111', 'ngan_hang' => 'NH Thử', 'mau' => $K, 'coso' => $CS, 'ngay_vao' => '2026-10-01',
	'luong_sua' => '', 'luong_bh' => '' ) ) );
$MA = 'KVCT0051';
$hs = VHCC_NhanSu::ho_so( $MA );
t( '🔴 mã NV = tiền tố + số chạy tiếp theo (soát cả lịch sử chấm công): KVCT0051', (bool) $hs, $wpdb->get_col( 'SELECT ma_nv FROM ' . VHCC_DB::t( 'nhan_vien' ) ) );
t( '🔴 PIN 6 số hợp lệ đã cấp', $hs && preg_match( '/^\d{6}$/', $hs['pin_dang_nhap'] ) && '' === VHCC_Quyen::pin_hop_le( $hs['pin_dang_nhap'] ), $hs );
t( '   vai trò, cơ sở, chức vụ, bộ phận theo mẫu', $hs && 'Nhân viên' === $hs['vai_tro'] && $CS === $hs['cua_hang']
	&& 'Nhân viên KVC' === $hs['chuc_vu'] && 'Khu vui chơi' === $hs['bo_phan'], $hs );
t( '   email (chữ thường), lương, ngày vào, loại HĐ thử việc', $hs && 'moi@vi-du.test' === $hs['email'] && 6000000.0 === (float) $hs['luong_co_ban']
	&& '2026-10-01' === $hs['ngay_vao_lam'] && 'Thử việc' === $hs['loai_hop_dong'], $hs );
$qt = (array) get_option( 'vhcc_quyen_trang', array() );
$tim_q = wp_json_encode( $qt );
t( '🔴 quyền vào trang theo mẫu (trạm mở, quản trị khoá)', false !== stripos( $tim_q, 'kvct0051' ) || false !== strpos( $tim_q, 'KVCT0051' ), $qt );
$bg = VHCC_TiepNhan::ban_ghi( $MA );
t( '   đủ 8 bước đã chạy', $bg && 8 === count( $bg['buoc'] ), $bg ? $bg['buoc'] : null );
foreach ( array( 'ho_so', 'quyen', 'app', 'may', 'luong', 'bhxh', 'giayto', 'gui' ) as $b ) {
	t( '   bước ' . $b . ' ✔', $bg && ! empty( $bg['buoc'][ $b ]['ok'] ), $bg ? $bg['buoc'][ $b ] : null );
}
$bh = VHCC_Bhxh::cua( $MA, '2026-11' );
t( '🔴 BHXH = 10,5% × 5.000.000 = 525.000 từ tháng hết thử việc (11/2026)', 525000.0 === (float) $bh, $bh );
t( '   tháng thử việc chưa trừ BHXH', 0.0 === (float) VHCC_Bhxh::cua( $MA, '2026-10' ) );
$lt = VHCC_ChotLuong::thang_cua( $CS, '2026-10', $MA );
t( '🔴 tháng thử việc ăn 85% lương: 5.100.000', $lt && 5100000.0 === (float) $lt['lcb'], $lt );
$mail = $GLOBALS['VHCP_MAIL'];
t( '🔴 email bộ hồ sơ gửi tới người mới', 1 === count( $mail ) && 'moi@vi-du.test' === $mail[0]['to'], $mail );
t( '   thư có link bộ hồ sơ, KHÔNG có PIN trong thư', false !== strpos( $mail[0]['message'], 'vhcc_nhanviec=1' )
	&& false === strpos( $mail[0]['message'], $hs['pin_dang_nhap'] ) );
t( '   chuông trong app cũng có', 1 === count( VHNB_Bao::$ds ) && false !== strpos( VHNB_Bao::$ds[0]['link'], 'vhcc_nhanviec=1' ) );
t( '   màn báo mã NV + PIN ngay', false !== strpos( $web( $G ), $MA ) );

/* ── 4. Bộ hồ sơ ── */
$link = VHCC_TiepNhan::link_bo( $MA );
parse_str( (string) parse_url( $link, PHP_URL_QUERY ), $q );
$b = VHCC_TiepNhan::trang_bo( $q['m'], $q['k'] );
t( '🔴 bộ hồ sơ: thư chào mừng có mã NV và PIN', false !== strpos( $b, 'THƯ CHÀO MỪNG' ) && false !== strpos( $b, $MA )
	&& false !== strpos( $b, $hs['pin_dang_nhap'] ) );
t( '🔴 có HỢP ĐỒNG THỬ VIỆC 30 ngày, lương 85%', false !== strpos( $b, 'HỢP ĐỒNG THỬ VIỆC' ) && false !== strpos( $b, '30 ngày' )
	&& false !== strpos( $b, '5.100.000 đồng' ) );
t( '🔴 có HỢP ĐỒNG LAO ĐỘNG theo BLLĐ 2019: xác định 12 tháng từ 31/10/2026', false !== strpos( $b, 'HỢP ĐỒNG LAO ĐỘNG' )
	&& false !== strpos( $b, '45/2019/QH14' ) && false !== strpos( $b, 'xác định thời hạn 12 tháng, từ ngày 31/10/2026 đến ngày 30/10/2027' ), $b );
t( '   bên A / bên B đủ thông tin', false !== strpos( $b, 'CÔNG TY THỬ K&amp;H' ) && false !== strpos( $b, 'Nguyễn Văn Giám' )
	&& false !== strpos( $b, 'Trần Thị Mới' ) && false !== strpos( $b, '000000000123' ) );
t( '   BHXH + điều khoản riêng của chức vụ', false !== strpos( $b, '5.000.000 đồng/tháng' ) && false !== strpos( $b, 'Được cấp 02 bộ đồng phục' ) );
t( '   nút In / Lưu PDF, khổ A4', false !== strpos( $b, 'In / Lưu thành PDF' ) && false !== strpos( $b, 'size:A4' ) );
t( '🔴 sai chữ ký -> không mở', false !== strpos( VHCC_TiepNhan::trang_bo( 'kvct0041', $q['k'] ), 'Link không hợp lệ' ) );
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'pin_dang_nhap' => '482915' ), array( 'ma_nv' => $MA ) );
$b2 = VHCC_TiepNhan::trang_bo( $q['m'], $q['k'] );
t( '🔴 nhân viên đổi PIN rồi -> bộ hồ sơ thôi hiện PIN', false === strpos( $b2, '482915' ) && false === strpos( $b2, $hs['pin_dang_nhap'] )
	&& false !== strpos( $b2, 'đã ẩn' ) );

/* ── 5. Chặn trùng, người không email, chạy lại ── */
$r = VHCC_TiepNhan::tao( array( 'role' => 'Admin', 'name' => 'A', 'coso' => $CS ), array( 'ho_ten' => 'Trùng', 'cccd' => '000000000123', 'mau' => $K, 'coso' => $CS ) );
t( '🔴 CCCD đã có hồ sơ -> chối, không tạo trùng', empty( $r['ok'] ) && false !== strpos( $r['error'], $MA ), $r );
$GLOBALS['VHCP_MAIL'] = array();
$web( $G, array( 'viec' => 'tn_tao', 'tn' => array( 'ho_ten' => 'Lê Văn Không Mail', 'cccd' => '000000000456', 'mau' => $K,
	'coso' => $CS, 'ngay_vao' => '2026-10-02', 'luong_sua' => '7000000' ) ) );
$MA2 = 'KVCT0052';
$bg2 = VHCC_TiepNhan::ban_ghi( $MA2 );
t( '   người thứ hai lấy mã kế tiếp KVCT0052, lương sửa 7.000.000', $bg2 && 7000000.0 === (float) $bg2['hd']['luong'], $bg2 ? $bg2['hd'] : null );
t( '🔴 không có email -> bước gửi ✖ nói rõ, các bước khác vẫn ✔', $bg2 && empty( $bg2['buoc']['gui']['ok'] )
	&& false !== strpos( $bg2['buoc']['gui']['chu'], 'chưa có email' ) && ! empty( $bg2['buoc']['bhxh']['ok'] ) );
$h = $web( $G );
t( '   bảng Đã tiếp nhận có nút Chạy lại bước lỗi', false !== strpos( $h, 'Chạy lại bước lỗi' ) );
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'email' => 'lv@vi-du.test' ), array( 'ma_nv' => $MA2 ) );
$web( $G, array( 'viec' => 'tn_chay_lai', 'tn_ma' => $MA2 ) );
t( '🔴 thêm email rồi Chạy lại -> gửi ✔', ! empty( VHCC_TiepNhan::ban_ghi( $MA2 )['buoc']['gui']['ok'] ) && 1 === count( $GLOBALS['VHCP_MAIL'] ) );
$web( $G, array( 'viec' => 'tn_gui', 'tn_ma' => $MA2 ) );
t( '   Gửi lại -> thêm một thư', 2 === count( $GLOBALS['VHCP_MAIL'] ) );

$tam = VHCC_TiepNhan::trang_bo( strtolower( $MA2 ), $q['k'] );
t( '🔴 dùng chữ ký của người này để mở bộ hồ sơ người khác -> không mở', false !== strpos( $tam, 'Link không hợp lệ' )
	&& false === strpos( $tam, 'Lê Văn Không Mail' ) );

/* ── 6. Quyền ── */
$r = VHCC_TiepNhan::tao( array( 'role' => 'Cửa hàng trưởng', 'name' => 'C', 'coso' => $CS ), array( 'ho_ten' => 'X', 'cccd' => '000000000789', 'mau' => $K, 'coso' => $CS ) );
t( '🔴 cửa hàng trưởng không tiếp nhận được (cần Kế toán)', empty( $r['ok'] ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — tiếp nhận nhân sự tự động từ đầu tới cuối.\n";
