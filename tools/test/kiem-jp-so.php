<?php
/**
 * KIỂM SỔ KẾ TOÁN CỦA JP CAPSULE (wordpress/vhcp-jp/includes/class-vhjp-so.php).
 *
 * =============================================================================================
 * 🔴 SỔ LÀ THỨ ĐEM ĐI ĐỐI CHIẾU VỚI MISA — NÓ PHẢI ĐÚNG, HOẶC PHẢI KÊU
 * =============================================================================================
 * Hai sổ ở đây không tự tính ra con số nào cả; chúng xếp lại chứng từ đã có. Nên chỗ sai không
 * phải phép cộng, mà là ba thứ khác:
 *
 *   1. **Lọc hụt** — `=== '632'` không bao giờ khớp dòng `6321`, và sổ giá vốn ra RỖNG mà không
 *      câu nào báo. Bảng trống trông y hệt "tháng này không bán gì".
 *   2. **Gộp nhầm** — bốn đường nhập không sinh công nợ (đầu kỳ · kiểm kê thừa · nhận điều
 *      chuyển · cơ sở trả về) mà bị cộng vào 331 thì khoản phải trả phình lên bằng cả tồn kho.
 *   3. **Im lặng khi dữ liệu hở** — báo cáo đã HOÀN TẤT mà không có dòng giá vốn nào là đường
 *      ghi bị đứt, và lãi gộp bị thổi lên đúng bằng phần thiếu. Sổ mà không kêu thì không ai
 *      biết, vì mọi màn đều vẫn xanh.
 *
 * Chạy: php tools/test/kiem-jp-so.php
 */

require_once __DIR__ . '/wp-stub.php';

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $d ) { return true; } }
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) { function add_rewrite_rule() {} }
if ( ! function_exists( 'flush_rewrite_rules' ) ) { function flush_rewrite_rules( $x = true ) {} }
if ( ! function_exists( 'nocache_headers' ) ) { function nocache_headers() {} }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route() { return true; } }
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $n = 12, $a = true, $b = false ) { return str_repeat( 'x', $n ); }
}

$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
function ne( $f ) {
	try { return array( 'ok' => true, 'ra' => $f() ); }
	catch ( Throwable $e ) { return array( 'ok' => false, 'loi' => $e->getMessage() ); }
}

vhjp_test_boot( dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp' );

$KT = array( 'id' => 'K1', 'hoTen' => 'Kế Toán', 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );
$NV = array( 'id' => 'U1', 'hoTen' => 'Nhân Viên A', 'role' => VHJP_Auth::VAI_NV,
	'locationIds' => array( 'CS01' ) );

function nen() {
	vhjp_dung_bang();
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS01', 'name' => 'JP Bà Rịa', 'code' => 'BR' ) );
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS02', 'name' => 'JP Vũng Tàu', 'code' => '' ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H1', 'misa' => 'M1', 'name' => 'Trứng khủng long',
		'dvt' => 'Quả', 'active' => 1 ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H2', 'misa' => '', 'name' => 'Vòng tay',
		'dvt' => 'Cái', 'active' => 1 ) );
}
function dong( $ma, $sl, $gia = 0 ) {
	return array( 'itemCode' => $ma, 'qty' => $sl, 'unitCost' => $gia );
}
function mua( $u, $ngay, $rows, $ncc = 'NCC A', $ma = '' ) {
	return VHJP_Kho::nhap( $u, array( 'ngay' => $ngay, 'nccMa' => $ma, 'nccTen' => $ncc,
		'rows' => $rows ) );
}
function bc_moi( $ma, $coso, $den, $dong_ds, $tt = null ) {
	VHJP_Nguon::them( 'JP_Reports', array( 'id' => $ma, 'locationId' => $coso,
		'locationName' => 'JP Bà Rịa', 'fromDate' => '2026-09-01', 'toDate' => $den,
		'userId' => 'U1', 'userName' => 'Nhân Viên A',
		'status' => null === $tt ? VHJP_BaoCao::TT_CHO_DUYET : $tt ) );
	$i = 0;
	foreach ( $dong_ds as $d ) {
		$i++;
		VHJP_Nguon::them( 'JP_Rows', array_merge(
			array( 'id' => $ma . '-R' . $i, 'reportId' => $ma, 'seq' => $i ), $d ) );
	}
}
function ky_du( $u, $ma ) {
	VHJP_Duyet::ky( $u, $ma, VHJP_BaoCao::PHAN_HANG, '' );
	return VHJP_Duyet::ky( $u, $ma, VHJP_BaoCao::PHAN_TIEN, '' );
}

/* ═══════════════════════════════════════════════════════════════════ ① QUYỀN ═══════════ */
nen();
$r = ne( function () { global $NV; return VHJP_So::so_632( $NV, 9, 2026, 0, '6321' ); } );
t( 'Nhân viên KHÔNG xem được sổ 632', ! $r['ok'], $r );
$r = ne( function () { global $NV; return VHJP_So::cong_no_ncc( $NV, 9, 2026 ); } );
t( 'Nhân viên KHÔNG xem được công nợ NCC', ! $r['ok'], $r );
$r = ne( function () { global $KT; return VHJP_So::so_632( $KT, 13, 2026 ); } );
t( 'Tháng không hợp lệ thì CHỐI', ! $r['ok'], $r );
$r = ne( function () { global $KT; return VHJP_So::cong_no_ncc( $KT, 9, 1990 ); } );
t( 'Năm không hợp lệ thì CHỐI', ! $r['ok'], $r );

/* ══════════════════════════════════════════════════════════════════ ② SỔ 632 ═══════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ), dong( 'H2', 50, 400 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 60 ), dong( 'H2', 20 ) ) ) );
/* Xé mẫu ở kho tổng -> 64116. Tặng mall -> cũng 64116. */
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 2 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-04', 'loai' => 'TANG_MALL',
	'rows' => array( dong( 'H2', 1 ) ) ) );
/* Bán ra qua lượt duyệt -> 6321. */
bc_moi( 'BC1', 'CS01', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 10 ),
	array( 'rowKind' => 'HANG',  'itemCode' => 'H2', 'soldQty' => 4 ) ) );
ky_du( $KT, 'BC1' );
/* Kiểm kê thiếu ở cơ sở -> 6321. */
VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'CS01', 'ngay' => '2026-09-25',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 48 ) ) ) );

$s = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( 'Sổ 632 chạy được', ! empty( $s['ok'] ), $s );
t( 'Sổ 6321 có 2 dòng: bán 10 H1 + bán 4 H2', 3 === count( $s['rows'] ), $s['rows'] );
t( '🔴 Tổng phát sinh Nợ 6321 = 10×1000 + 4×400 + 2×1000 (kiểm kê thiếu) = 13.600đ',
	13600 === $s['tongPhatSinhNo'], $s );
t( 'Dư cuối kỳ = dư đầu + phát sinh', 13600 === $s['duCuoiKy'], $s );
t( 'Tên tài khoản đọc được', 'Giá vốn hàng bán JP' === $s['tenTaiKhoan'], $s );

/* 🔴 Bẫy lớn nhất: lọc theo `=== '632'` thì dòng 6321 không bao giờ khớp. */
$cu = VHJP_So::so_632( $KT, 9, 2026, 0, '632' );
t( '🔴 Mục "632 — dữ liệu cũ" KHÔNG nuốt dòng 6321 (hai bộ dữ liệu khác nhau)',
	0 === count( $cu['rows'] ), $cu['rows'] );
t( 'Và sổ 6321 vẫn ra đủ dòng — không bị lọc hụt', 3 === count( $s['rows'] ) );

$c4 = VHJP_So::so_632( $KT, 9, 2026, 0, '64116' );
t( 'Sổ 64116 có 2 dòng: xé mẫu + tặng mall', 2 === count( $c4['rows'] ), $c4['rows'] );
t( 'Tổng 64116 = 2×1000 + 1×400 = 2.400đ', 2400 === $c4['tongPhatSinhNo'], $c4 );
t( '🔴 Hàng bán KHÔNG lẫn sang 64116, và xé mẫu KHÔNG lẫn sang 6321',
	13600 === $s['tongPhatSinhNo'] && 2400 === $c4['tongPhatSinhNo'] );

/* Dư đầu kỳ do kế toán GÕ VÀO, và dư Nợ phải cộng dồn theo từng dòng. */
$s2 = VHJP_So::so_632( $KT, 9, 2026, 5000, '6321' );
t( 'Dư đầu kỳ nhận đúng số kế toán gõ', 5000 === $s2['duDauKy'], $s2 );
t( 'Dư cuối = 5.000 + 13.600', 18600 === $s2['duCuoiKy'], $s2 );
t( 'Dòng đầu tiên có dư Nợ = dư đầu + phát sinh của chính nó',
	5000 + $s2['rows'][0]['phatSinhNo'] === $s2['rows'][0]['duNo'], $s2['rows'][0] );
$cuoi = $s2['rows'][ count( $s2['rows'] ) - 1 ];
t( '🔴 Dư Nợ dòng cuối bằng đúng dư cuối kỳ', $cuoi['duNo'] === $s2['duCuoiKy'], $cuoi );

/* Khuôn MISA: mọi ô mà bản xuất .xlsx đọc đều phải có mặt. */
$can = array( 'ngayHachToan', 'ngayChungTu', 'soChungTu', 'dienGiaiChung', 'dienGiai',
	'tkDoiUng', 'phatSinhNo', 'phatSinhCo', 'duNo', 'duCo', 'maDoiTuong', 'maDonVi',
	'tenDonVi', 'maHang', 'soLuong', 'giaVon', 'thieuLop' );
$thieu_o = array_values( array_diff( $can, array_keys( $s['rows'][0] ) ) );
t( '🔴 Dòng sổ có đủ 17 ô mà bản xuất MISA đọc', ! $thieu_o, $thieu_o );
t( 'TK đối ứng của dòng bán là 1561', '1561' === $s['rows'][0]['tkDoiUng'], $s['rows'][0] );
t( 'Mã đơn vị lấy MÃ cơ sở khi có', 'BR' === $s['rows'][0]['maDonVi'], $s['rows'][0] );
t( 'Diễn giải chung nhắc tên cơ sở và mã báo cáo',
	false !== strpos( $s['rows'][0]['dienGiaiChung'], 'JP Bà Rịa' )
	&& false !== strpos( $s['rows'][0]['dienGiaiChung'], 'BC1' ), $s['rows'][0] );
t( 'Mã đối tượng giữ mã báo cáo để tra ngược', 'BC1' === $s['rows'][0]['maDoiTuong'], $s['rows'][0] );

/* Cơ sở chưa khai mã thì lấy mã kho — bỏ trống là dòng không nạp vào MISA được. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS02', 'rows' => array( dong( 'H1', 5 ) ) ) );
bc_moi( 'BC9', 'CS02', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 2 ) ) );
ky_du( $KT, 'BC9' );
$sm = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( 'Cơ sở chưa khai mã thì mã đơn vị lấy mã kho, không bỏ trống',
	'CS02' === $sm['rows'][0]['maDonVi'], $sm['rows'][0] );

/* Cắt đúng khoảng ngày. */
nen();
mua( $KT, '2026-08-01', array( dong( 'H1', 100, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-08-31', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 1 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-01', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 2 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-30', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 3 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-10-01', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 4 ) ) ) );
$sk = VHJP_So::so_632( $KT, 9, 2026, 0, '64116' );
t( 'Sổ cắt đúng tháng: lấy cả ngày 01 và ngày cuối tháng, bỏ hai bên',
	2 === count( $sk['rows'] ) && 5000 === $sk['tongPhatSinhNo'], $sk );
t( 'Dòng xếp theo ngày tăng dần',
	$sk['rows'][0]['ngayHachToan'] < $sk['rows'][1]['ngayHachToan'], $sk['rows'] );

/* 🔴 DÒNG ÂM của lượt điều chỉnh phải ĐỨNG NGUYÊN TRONG SỔ. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-10', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 6 ) ) ) );
VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20',
	'cheDoThua' => 'GIAM_XUAT', 'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 6 ) ) ) );
$sa = VHJP_So::so_632( $KT, 9, 2026, 0, '64116' );
t( '🔴 Dòng điều chỉnh ÂM đứng nguyên trong sổ, không bị lọc ra', 2 === count( $sa['rows'] ), $sa['rows'] );
t( 'Dòng âm mang −2.000đ', -2000 === $sa['rows'][1]['phatSinhNo'], $sa['rows'][1] );
t( 'Diễn giải nói rõ là ĐIỀU CHỈNH GIẢM',
	false !== strpos( $sa['rows'][1]['dienGiai'], 'Điều chỉnh giảm' ), $sa['rows'][1] );
t( '🔴 Tổng phát sinh = 6.000 − 2.000 = 4.000đ (đúng số đã xuất thật)',
	4000 === $sa['tongPhatSinhNo'], $sa );
t( 'Dư Nợ chạy đúng qua dòng âm', 4000 === $sa['rows'][1]['duNo'], $sa['rows'][1] );

/* ═════════════════════════════════════════════════════ ③ SỔ 632 PHẢI BIẾT KÊU ══════════ */
nen();
/* Cơ sở CHƯA được chuyển hàng xuống ⇒ dòng giá vốn 0, cờ thiếu lớp. */
bc_moi( 'BC2', 'CS01', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 5 ) ) );
ky_du( $KT, 'BC2' );
$w = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( 'Thiếu lớp: dòng vẫn có mặt trong sổ', 1 === count( $w['rows'] ), $w['rows'] );
t( 'Thiếu lớp: dòng mang cờ để giao diện tô đỏ', 1 === $w['rows'][0]['thieuLop'], $w['rows'][0] );
t( '🔴 Và sổ KÊU LÊN, kèm việc phải làm',
	$w['canhBao'] && false !== strpos( $w['canhBao'][0], 'THIẾU LỚP TỒN' )
	&& false !== strpos( $w['canhBao'][0], 'Xuất lại' ), $w['canhBao'] );

/* Phiếu nhập gõ giá mua 0 ⇒ có lớp nhưng đơn giá 0. Khác hẳn thiếu lớp, nên câu khác. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 0 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-05', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 2 ) ) ) );
$w0 = VHJP_So::so_632( $KT, 9, 2026, 0, '64116' );
t( 'Đơn giá 0 nhưng CÓ lớp: không bị gọi là thiếu lớp', 0 === $w0['rows'][0]['thieuLop'], $w0['rows'][0] );
t( '🔴 Sổ kêu đúng nguyên nhân: sửa PHIẾU NHẬP GỐC, không sửa sổ',
	$w0['canhBao'] && false !== strpos( $w0['canhBao'][0], 'ĐƠN GIÁ 0đ' )
	&& false !== strpos( $w0['canhBao'][0], 'phiếu nhập gốc' ), $w0['canhBao'] );

/* 🔴🔴 CẢNH BÁO QUAN TRỌNG NHẤT: báo cáo HOÀN TẤT mà không có dòng giá vốn nào.
   Đúng thứ đã xảy ra suốt thời gian móc "duyệt -> sổ kho" chưa được nối: bảng trống trông y hệt
   "tháng này không bán gì", và lãi gộp bị thổi lên đúng bằng phần thiếu. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 50, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 30 ) ) ) );
bc_moi( 'BC3', 'CS01', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 9 ) ),
	VHJP_BaoCao::TT_HOAN_TAT );   // hoàn tất mà KHÔNG đi qua lượt ký -> sổ kho trống
$wb = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( 'Đường ghi đứt: sổ 6321 rỗng', ! $wb['rows'], $wb['rows'] );
t( '🔴 Nhưng sổ KHÔNG im lặng — nó gọi đúng tên báo cáo bị hụt',
	$wb['canhBao'] && false !== strpos( $wb['canhBao'][0], 'BC3' ), $wb['canhBao'] );
t( 'Và nói rõ hậu quả (lãi gộp bị thổi lên) lẫn việc phải làm (ký lại)',
	false !== strpos( $wb['canhBao'][0], 'Lãi gộp' )
	&& false !== strpos( $wb['canhBao'][0], 'ký lại' ), $wb['canhBao'] );

/* Ký lại thì cảnh báo phải TẮT — cảnh báo không tắt được là cảnh báo người ta học cách bỏ qua. */
VHJP_Nguon::sua( 'JP_Reports', 'BC3', array( 'status' => VHJP_BaoCao::TT_CHO_DUYET ) );
ky_du( $KT, 'BC3' );
$wb2 = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( 'Ký lại xong: sổ có dòng', 1 === count( $wb2['rows'] ), $wb2['rows'] );
t( '🔴 Và cảnh báo TẮT hẳn', ! $wb2['canhBao'], $wb2['canhBao'] );

/* Cảnh báo KHÔNG được kêu sói: báo cáo toàn dòng máy xu thì không có giá vốn là ĐÚNG. */
nen();
bc_moi( 'BC4', 'CS01', '2026-09-15', array(
	array( 'rowKind' => 'COIN', 'itemCode' => '', 'soldQty' => 0 ) ),
	VHJP_BaoCao::TT_HOAN_TAT );
$wc = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( '🔴 Báo cáo không có hàng bán thì KHÔNG bị kể tên (cảnh báo kêu sói là cảnh báo chết)',
	! $wc['canhBao'], $wc['canhBao'] );

/* Báo cáo hoàn tất ở THÁNG KHÁC không được lôi vào cảnh báo của tháng này. */
nen();
bc_moi( 'BC5', 'CS01', '2026-08-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 3 ) ),
	VHJP_BaoCao::TT_HOAN_TAT );
$wd = VHJP_So::so_632( $KT, 9, 2026, 0, '6321' );
t( 'Báo cáo tháng 8 không lọt vào cảnh báo của tháng 9', ! $wd['canhBao'], $wd['canhBao'] );
$we = VHJP_So::so_632( $KT, 8, 2026, 0, '6321' );
t( 'Nhưng xem tháng 8 thì nó kêu', $we['canhBao'] && false !== strpos( $we['canhBao'][0], 'BC5' ),
	$we['canhBao'] );
/* Cảnh báo ấy chỉ thuộc sổ 632 — mở sổ 64116 mà kêu "thiếu giá vốn" là nói lạc đề. */
$wf = VHJP_So::so_632( $KT, 8, 2026, 0, '64116' );
t( 'Sổ 64116 không kêu chuyện của sổ giá vốn', ! $wf['canhBao'], $wf['canhBao'] );

/* ═══════════════════════════════════════════════════════ ④ CÔNG NỢ NHÀ CUNG CẤP ════════ */
nen();
mua( $KT, '2026-08-10', array( dong( 'H1', 10, 1000 ) ), 'NCC A', 'A01' );   // trước kỳ
mua( $KT, '2026-09-05', array( dong( 'H1', 20, 1000 ) ), 'NCC A', 'A01' );   // trong kỳ
mua( $KT, '2026-09-06', array( dong( 'H2', 10, 500 ) ),  'NCC B', 'B01' );
VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-08-20', 'nccMa' => 'A01', 'nccTen' => 'NCC A',
	'soTien' => 4000 ) );                                                     // trước kỳ
VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-09-25', 'nccMa' => 'A01', 'nccTen' => 'NCC A',
	'soTien' => 10000 ) );                                                    // trong kỳ

$cn = VHJP_So::cong_no_ncc( $KT, 9, 2026 );
t( 'Công nợ chạy được', ! empty( $cn['ok'] ), $cn );
t( 'Có 2 nhà cung cấp', 2 === count( $cn['rows'] ), $cn['rows'] );
$a = null; $b = null;
foreach ( $cn['rows'] as $r ) {
	if ( 'A01' === $r['nccMa'] ) { $a = $r; }
	if ( 'B01' === $r['nccMa'] ) { $b = $r; }
}
t( 'NCC A · dư đầu kỳ = 10.000 mua − 4.000 đã trả = 6.000', 6000 === $a['duDauKy'], $a );
t( 'NCC A · phát sinh trong kỳ = 20.000', 20000 === $a['phatSinh'], $a );
t( 'NCC A · đã trả trong kỳ = 10.000', 10000 === $a['daTra'], $a );
t( '🔴 NCC A · còn phải trả = 6.000 + 20.000 − 10.000 = 16.000', 16000 === $a['duCuoiKy'], $a );
t( 'NCC A · đếm đúng số phiếu trong kỳ', 1 === $a['soPhieuNhap'] && 1 === $a['soPhieuTra'], $a );
t( 'NCC A · cờ conNo bật', true === $a['conNo'], $a );
t( 'NCC B · chưa trả đồng nào, còn nợ 5.000', 5000 === $b['duCuoiKy'] && 0 === $b['daTra'], $b );
t( 'Ai nợ nhiều nhất lên đầu', 'A01' === $cn['rows'][0]['nccMa'], $cn['rows'] );
t( 'Tổng cộng: 6.000 đầu kỳ + 25.000 phát sinh − 10.000 đã trả = 21.000',
	6000 === $cn['tong']['duDauKy'] && 25000 === $cn['tong']['phatSinh']
	&& 10000 === $cn['tong']['daTra'] && 21000 === $cn['tong']['duCuoiKy'], $cn['tong'] );
t( 'Kỳ sạch thì không cảnh báo gì', ! $cn['canhBao'], $cn['canhBao'] );

/* 🔴 CHỈ PHIẾU MUA SINH CÔNG NỢ. Bốn đường nhập kia không nợ ai. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ), 'NCC A', 'A01' );
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-01',
	'rows' => array( dong( 'H2', 100, 900 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 5 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'TRA_KHO',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 2 ) ) ) );
VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'CS02', 'ngay' => '2026-09-04',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 30 ) ) ) );
$cg = VHJP_So::cong_no_ncc( $KT, 9, 2026 );
t( '🔴 Chỉ phiếu MUA vào công nợ: đúng 10.000, không phình theo tồn kho',
	1 === count( $cg['rows'] ) && 10000 === $cg['tong']['phatSinh'], $cg );
t( 'Đầu kỳ · nhận ĐC · trả kho · kiểm kê thừa KHÔNG nợ ai',
	10000 === $cg['tong']['duCuoiKy'], $cg['tong'] );
/* ⚠️ Phép trên MỘT MÌNH nó không đủ: bốn đường nhập kia vốn không mang tên nhà cung cấp, nên
   nhánh "bỏ phiếu không khai NCC" cũng chặn hộ — gỡ hẳn phép lọc `N_MUA` mà tổng vẫn đúng.
   Chỗ lộ ra là CẢNH BÁO: mất phép lọc thì bốn phiếu ấy bị đếm là "phiếu mua chưa khai NCC",
   và kế toán đi tìm bốn phiếu không hề tồn tại. */
t( '🔴 Và KHÔNG bị nhận nhầm thành "phiếu mua chưa khai nhà cung cấp"',
	! $cg['canhBao'], $cg['canhBao'] );

/* Phiếu huỷ không tính — cả hai bên. */
nen();
$p1 = mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ), 'NCC A', 'A01' );
$p2 = mua( $KT, '2026-09-02', array( dong( 'H1', 5, 1000 ) ), 'NCC A', 'A01' );
VHJP_Kho::huy_nhap( $KT, $p2['id'], 'gõ nhầm' );
$t1 = VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-09-10', 'nccMa' => 'A01',
	'nccTen' => 'NCC A', 'soTien' => 3000 ) );
$t2 = VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-09-11', 'nccMa' => 'A01',
	'nccTen' => 'NCC A', 'soTien' => 7000 ) );
VHJP_Kho::huy_tra_ncc( $KT, $t2['id'], 'chuyển nhầm' );
$ch = VHJP_So::cong_no_ncc( $KT, 9, 2026 );
t( '🔴 Phiếu MUA đã huỷ không tính vào phải trả', 10000 === $ch['tong']['phatSinh'], $ch['tong'] );
t( '🔴 Phiếu TRẢ đã huỷ không trừ công nợ (không thì NCC còn đòi mà sổ nói đã trả)',
	3000 === $ch['tong']['daTra'] && 7000 === $ch['tong']['duCuoiKy'], $ch['tong'] );

/* Ba cảnh báo của công nợ. */
nen();
VHJP_Kho::nhap( $KT, array( 'ngay' => '2026-09-01', 'rows' => array( dong( 'H1', 10, 1000 ) ) ) );
$cw = VHJP_So::cong_no_ncc( $KT, 9, 2026 );
t( '🔴 Phiếu mua không khai NCC thì sổ KÊU (khoản phải trả biến mất)',
	$cw['canhBao'] && false !== strpos( $cw['canhBao'][0], 'KHÔNG khai nhà cung cấp' ), $cw['canhBao'] );
t( 'Và nói rõ giá trị vẫn nằm trong tồn kho',
	false !== strpos( $cw['canhBao'][0], 'tồn kho' ), $cw['canhBao'] );

nen();
VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-09-10', 'nccTen' => 'NCC Lạ', 'soTien' => 5000 ) );
$cl = VHJP_So::cong_no_ncc( $KT, 9, 2026 );
t( 'Trả tiền cho NCC chưa từng mua: vẫn hiện trong bảng', 1 === count( $cl['rows'] ), $cl['rows'] );
t( '🔴 Và sổ kêu, gọi đúng tên', $cl['canhBao']
	&& false !== strpos( implode( ' ', $cl['canhBao'] ), 'NCC Lạ' ), $cl['canhBao'] );
t( 'Trả vượt cũng được kêu riêng',
	false !== strpos( implode( ' ', $cl['canhBao'] ), 'TRẢ VƯỢT' ), $cl['canhBao'] );
t( 'Trả vượt thì dư cuối kỳ ÂM, không bị kẹp về 0', -5000 === $cl['rows'][0]['duCuoiKy'],
	$cl['rows'][0] );

/* Nhà cung cấp đã tất toán từ kỳ trước thì không chiếm chỗ trong bảng. */
nen();
mua( $KT, '2026-07-01', array( dong( 'H1', 10, 1000 ) ), 'NCC Cũ', 'C01' );
VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-07-20', 'nccMa' => 'C01', 'nccTen' => 'NCC Cũ',
	'soTien' => 10000 ) );
mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ) ), 'NCC Mới', 'D01' );
$cz = VHJP_So::cong_no_ncc( $KT, 9, 2026 );
t( 'NCC đã tất toán, kỳ này không phát sinh ⇒ không chiếm chỗ trong bảng',
	1 === count( $cz['rows'] ) && 'D01' === $cz['rows'][0]['nccMa'], $cz['rows'] );

/* ═════════════════════════════════════════════════════════════════ ⑤ CỔNG ══════════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ), 'NCC A', 'A01' );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-05', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 2 ) ) ) );
$ra = VHJP_Cong::so_632( array( 'THE-PHIEN', 9, 2026, 1000, '64116' ), $KT );
t( 'Cổng: so_632 đọc tham số ở $args[1..4], không phải $args[0..3]',
	'64116' === $ra['taiKhoan'] && 9 === $ra['thang'] && 1000 === $ra['duDauKy']
	&& 1 === count( $ra['rows'] ), $ra );
$ra = VHJP_Cong::so_cong_no_ncc( array( 'THE-PHIEN', 9, 2026 ), $KT );
t( 'Cổng: so_cong_no_ncc đọc tháng/năm ở $args[1..2]',
	9 === $ra['thang'] && 2026 === $ra['nam'] && 1 === count( $ra['rows'] ), $ra );
/* Giao diện KHÔNG truyền tk thì phải ra sổ giá vốn, không ra rỗng. */
$ra = VHJP_Cong::so_632( array( 'THE-PHIEN', 9, 2026 ), $KT );
t( 'Thiếu tham số tài khoản thì mặc định 6321, không ra một sổ vô nghĩa',
	'6321' === $ra['taiKhoan'], $ra );

/* ------------------------------------------------------------------ in kết quả */
echo "\n";
if ( $TRUOT ) {
	echo 'ĐỎ — ' . count( $TRUOT ) . " phép trượt:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "\nĐạt {$DAT} · trượt " . count( $TRUOT ) . "\n";
	exit( 1 );
}
echo "XANH — {$DAT} phép đều đạt.\n";
exit( 0 );
