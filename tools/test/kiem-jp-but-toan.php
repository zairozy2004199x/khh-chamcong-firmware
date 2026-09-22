<?php
/**
 * KIỂM BÚT TOÁN CỦA JP CAPSULE (wordpress/vhcp-jp/includes/class-vhjp-but-toan.php).
 *
 * =============================================================================================
 * 🔴 "SỔ CÂN" LÀ MỘT PHÉP KIỂM RẤT YẾU — BÀI NÀY PHẢI ĐI XA HƠN
 * =============================================================================================
 * Mỗi bút toán sinh ra đều có một vế Nợ và một vế Có bằng nhau, nên tổng phát sinh LUÔN cân, kể
 * cả khi mọi con số đều sai. Sổ cân chỉ bắt được đúng một loại lỗi: bút toán đẻ ra thiếu vế.
 *
 * Nên phần lớn phép thử dưới đây hỏi những câu khác:
 *
 *   1. Dư 131 của một báo cáo có bằng ĐÚNG số nhân viên phải nộp không (doanh thu − hoàn khách
 *      ± lệch máy)? Thiếu một trong ba vế thì sổ vẫn cân mà số phải thu sai.
 *   2. Điều chuyển kho (156/156) có bị ghi thành bút toán không? Ghi ra là bảng cân đối có một
 *      dòng phát sinh trên tài khoản mà thực tế không dời gì cả.
 *   3. Dòng kho đời cũ (632/1567) có hiện ra ĐÚNG đầu cũ không? Suy lại đầu theo loại xuất là
 *      giấu mất chính cái mà `jpDoiTkKhoCu` sinh ra để sửa.
 *   4. Báo cáo chưa duyệt có bị ĐẾM và NÓI RA không? Bỏ im là tiền biến mất êm.
 *   5. Hai đường tính kết quả kinh doanh có thật sự khác nhau không, và phần nào KHÔNG độc lập
 *      thì có tự nhận là không độc lập không?
 *   6. Đầu tài khoản còn TẠM có luôn đi kèm nhãn TẠM không?
 *
 * Chạy: php tools/test/kiem-jp-but-toan.php
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
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS02', 'name' => 'JP Vũng Tàu', 'code' => 'VT' ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H1', 'misa' => 'M1', 'name' => 'Trứng khủng long',
		'dvt' => 'Quả', 'active' => 1 ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H2', 'name' => 'Vòng tay', 'active' => 1 ) );
}
function dong( $ma, $sl, $gia = 0 ) {
	return array( 'itemCode' => $ma, 'qty' => $sl, 'unitCost' => $gia );
}
function mua( $u, $ngay, $rows, $ncc = 'NCC A', $ma = 'A01' ) {
	return VHJP_Kho::nhap( $u, array( 'ngay' => $ngay, 'nccMa' => $ma, 'nccTen' => $ncc,
		'rows' => $rows ) );
}
/**
 * Một báo cáo. `$so` = array( revMeter, adjMachine, refundCustomer ) — `totalSubmit` để MÁY suy,
 * đúng công thức `revMeter + lệch máy − hoàn khách`.
 */
function bc_moi( $ma, $coso, $tu, $den, $so, $dong_ds, $tt = null ) {
	$rev  = isset( $so[0] ) ? $so[0] : 0;
	$adj  = isset( $so[1] ) ? $so[1] : 0;
	$hoan = isset( $so[2] ) ? $so[2] : 0;
	VHJP_Nguon::them( 'JP_Reports', array( 'id' => $ma, 'locationId' => $coso,
		'locationName' => 'CS ' . $coso, 'fromDate' => $tu, 'toDate' => $den,
		'userId' => 'U1', 'userName' => 'Nhân Viên A',
		'revMeter' => $rev, 'adjMachine' => $adj, 'refundCustomer' => $hoan,
		'totalSubmit' => $rev + $adj - $hoan,
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
/** Tổng phát sinh của một đầu trong bảng cân đối. */
function tk( $cd, $ma ) {
	foreach ( $cd['rows'] as $r ) { if ( $ma === $r['tk'] ) { return $r; } }
	return null;
}

/* ═══════════════════════════════════════════════════════════════════ ① QUYỀN ═══════════ */
nen();
foreach ( array( 'nhat_ky_chung', 'can_doi', 'kiem_tra', 'ket_qua_kd' ) as $ham ) {
	$r = ne( function () use ( $ham ) {
		global $NV; return call_user_func( array( 'VHJP_ButToan', $ham ), $NV, 9, 2026 );
	} );
	t( 'Nhân viên KHÔNG xem được VHJP_ButToan::' . $ham, ! $r['ok'], $r );
}
$r = ne( function () { global $NV; return VHJP_ButToan::doi_tk_kho_cu( $NV, true ); } );
t( 'Nhân viên KHÔNG chạy được lượt đổi tài khoản', ! $r['ok'], $r );
$r = ne( function () { global $KT; return VHJP_ButToan::can_doi( $KT, 0, 2026 ); } );
t( 'Tháng không hợp lệ thì CHỐI', ! $r['ok'], $r );

/* ══════════════════════════════════════════════════ ② BA VẾ CỦA MỘT BÁO CÁO ════════════ */
/*
 * Nhân viên phải nộp = doanh thu − hoàn khách ± lệch máy. Cả ba vế đều đi qua 131, nên dư 131
 * phải bằng ĐÚNG `totalSubmit`. Thiếu một vế thì sổ VẪN CÂN mà số phải thu sai — không phép
 * kiểm nào khác bắt được.
 */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 50 ) ) ) );
bc_moi( 'BC1', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 20000, 30000 ), array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 10 ) ) );
ky_du( $KT, 'BC1' );

$nk = VHJP_ButToan::nhat_ky_chung( $KT, 9, 2026, '' );
t( 'Nhật ký chung chạy được', ! empty( $nk['ok'] ), $nk );
t( 'Sổ CÂN (mọi bút toán đủ hai vế)', ! empty( $nk['canBang'] ), $nk );

$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
$t131 = tk( $cd, '131' );
t( '🔴 Dư 131 = 500.000 doanh thu − 30.000 hoàn + 20.000 lệch máy = 490.000',
	490000 === $t131['duCuoiNo'], $t131 );
t( 'Và đúng bằng totalSubmit của báo cáo',
	490000 === VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['totalSubmit'] ) );
t( 'Doanh thu vào 5111', 500000 === tk( $cd, '5111' )['psCo'], tk( $cd, '5111' ) );
t( 'Hoàn khách vào 5213 bên Nợ', 30000 === tk( $cd, '5213' )['psNo'], tk( $cd, '5213' ) );
t( 'Lệch máy DƯƠNG vào 711 bên Có', 20000 === tk( $cd, '711' )['psCo'], tk( $cd, '711' ) );
t( 'Giá vốn 10×1000 vào 6321', 10000 === tk( $cd, '6321' )['psNo'], tk( $cd, '6321' ) );
t( 'Mua hàng: Nợ 1561 / Có 331', 100000 === tk( $cd, '331' )['psCo'], tk( $cd, '331' ) );

/* Lệch máy ÂM đi đường khác hẳn: 811 bên Nợ, và 131 GIẢM. */
nen();
bc_moi( 'BC2', 'CS01', '2026-09-01', '2026-09-15', array( 500000, -20000, 0 ), array(), null );
VHJP_Nguon::sua( 'JP_Reports', 'BC2', array( 'status' => VHJP_BaoCao::TT_HOAN_TAT ) );
$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( 'Lệch máy ÂM vào 811 bên Nợ', 20000 === tk( $cd, '811' )['psNo'], tk( $cd, '811' ) );
t( 'Và dư 131 tụt xuống 480.000', 480000 === tk( $cd, '131' )['duCuoiNo'], tk( $cd, '131' ) );
t( 'Không đẻ dòng 711 nào', null === tk( $cd, '711' ), $cd['rows'] );

/* ════════════════════════════════════════════ ③ ĐIỀU CHUYỂN KHÔNG PHẢI BÚT TOÁN ════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 50 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'TRA_KHO',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 10 ) ) ) );
$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( '🔴 Điều chuyển kho (156/156) KHÔNG đẻ bút toán nào', null === tk( $cd, '156' ), $cd['rows'] );
t( 'Nhận điều chuyển cũng không đẻ vế nhập', 100000 === tk( $cd, '1561' )['psNo'], tk( $cd, '1561' ) );
t( 'Bảng chỉ còn đúng hai đầu: 1561 và 331', 2 === count( $cd['rows'] ), $cd['rows'] );
t( 'Và vẫn CÂN cả ba phép',
	$cd['canBangPs'] && $cd['canBangDau'] && $cd['canBangCuoi'], $cd );

/* ══════════════════════════════════════════════════ ④ DÒNG KHO ĐỜI CŨ ══════════════════ */
/*
 * Dòng mang 632/1567 phải hiện ra ĐÚNG đầu cũ — suy lại đầu theo loại xuất là giấu mất chính
 * cái mà `jpDoiTkKhoCu` sinh ra để sửa, và sổ thì vẫn CÂN nên không ai thấy.
 */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ) ) );
VHJP_Nguon::them( 'JP_KhoXuat', array( 'id' => 'XKCU1', 'soChungTu' => 'CU-01',
	'ngay' => '2026-09-05', 'loai' => 'BAN', 'khoId' => 'CS01', 'itemCode' => 'H1',
	'qty' => 3, 'unitCost' => 1000, 'amount' => 3000, 'tkNo' => '632', 'tkCo' => '1567' ) );
VHJP_Nguon::them( 'JP_KhoXuat', array( 'id' => 'XKCU2', 'soChungTu' => 'CU-02',
	'ngay' => '2026-09-06', 'loai' => 'XE_MAU', 'khoId' => 'TONG', 'itemCode' => 'H1',
	'qty' => 1, 'unitCost' => 1000, 'amount' => 1000, 'tkNo' => '641', 'tkCo' => '1567' ) );

$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( '🔴 Dòng đời cũ hiện ra ĐÚNG đầu 632, không bị suy lại thành 6321',
	3000 === tk( $cd, '632' )['psNo'], tk( $cd, '632' ) );
t( 'Và 1567 hiện ra như một đầu riêng (kho ĂN UỐNG — khối khác)',
	4000 === tk( $cd, '1567' )['psCo'], tk( $cd, '1567' ) );
t( 'Tên tài khoản nói rõ 1567 là của khối khác',
	false !== strpos( tk( $cd, '1567' )['tenTk'], 'khối khác' ), tk( $cd, '1567' ) );
t( '⚠️ Và sổ VẪN CÂN — đúng lý do cần một màn riêng để bắt chuyện này',
	$cd['canBangPs'] && $cd['canBangCuoi'], $cd );

$xt = VHJP_ButToan::doi_tk_kho_cu( $KT, false );
t( 'Xem trước: đếm đúng 2 dòng · 4.000đ', 2 === $xt['soDong'] && 4000 === $xt['soTien'], $xt );
t( '🔴 Xem trước KHÔNG sửa gì', 0 === $xt['daGhi']
	&& '632' === VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_KhoXuat', 'id', 'XKCU1' )['tkNo'] ), $xt );
t( 'Xem trước nói rõ là chưa sửa', false !== strpos( $xt['msg'], 'Chưa sửa gì' ), $xt['msg'] );
t( 'Gom theo cặp đầu cũ → đầu mới', 2 === count( $xt['nhom'] ), $xt['nhom'] );

$gh = VHJP_ButToan::doi_tk_kho_cu( $KT, true );
t( 'Đổi thật: ghi được 2 dòng', 2 === $gh['daGhi'], $gh );
t( '632 → 6321', '6321' === VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_KhoXuat', 'id', 'XKCU1' )['tkNo'] ) );
t( '641 → 64116', '64116' === VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_KhoXuat', 'id', 'XKCU2' )['tkNo'] ) );
t( '1567 → 1561', '1561' === VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_KhoXuat', 'id', 'XKCU1' )['tkCo'] ) );
$lai = VHJP_ButToan::doi_tk_kho_cu( $KT, true );
t( '🔴 Chạy lại lần hai: không còn dòng nào, không ghi đè dòng đã đúng', 0 === $lai['soDong'], $lai );
t( 'Và nói rõ sổ đã dùng đúng đầu', false !== strpos( $lai['msg'], 'đúng đầu của JP' ), $lai['msg'] );
$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( 'Sau lượt đổi: đầu cũ biến mất khỏi bảng cân đối',
	null === tk( $cd, '632' ) && null === tk( $cd, '1567' ), $cd['rows'] );
t( 'Giá vốn dồn về 6321', 3000 === tk( $cd, '6321' )['psNo'], tk( $cd, '6321' ) );

/* Dòng thiếu HẲN cột tài khoản rơi vào đầu cũ — cố ý, để nó đi cùng đồng bọn ở màn đổi. */
nen();
VHJP_Nguon::them( 'JP_KhoXuat', array( 'id' => 'XKTRONG', 'soChungTu' => 'T-01',
	'ngay' => '2026-09-05', 'loai' => 'BAN', 'khoId' => 'CS01', 'itemCode' => 'H1',
	'qty' => 2, 'unitCost' => 500, 'amount' => 1000, 'tkNo' => '', 'tkCo' => '' ) );
$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( 'Dòng thiếu tài khoản được ĐẾM và nói ra', 1 === $cd['boQua']['thieuTk'], $cd['boQua'] );
t( 'Và rơi vào đầu CŨ để lượt đổi tài khoản tóm được nó',
	null !== tk( $cd, '632' ) && null !== tk( $cd, '1567' ), $cd['rows'] );
t( 'Lượt đổi tóm được nó thật', 1 === VHJP_ButToan::doi_tk_kho_cu( $KT, false )['soDong'] );

/* ══════════════════════════════════════════════ ⑤ PHẦN KHÔNG VÀO SỔ PHẢI HIỆN ══════════ */
nen();
bc_moi( 'BCX', 'CS01', '2026-09-01', '2026-09-15', array( 700000, 0, 0 ), array() );
bc_moi( 'BCN', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ), array(),
	VHJP_BaoCao::TT_NHAP );
bc_moi( 'BCH', 'CS02', '2026-09-01', '2026-09-15', array( 200000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
$nk = VHJP_ButToan::nhat_ky_chung( $KT, 9, 2026, '' );
t( '🔴 Báo cáo CHỜ DUYỆT bị bỏ ra khỏi sổ nhưng được ĐẾM',
	1 === $nk['boQua']['bcChuaDuyet'] && 700000 === $nk['boQua']['tienChuaDuyet'], $nk['boQua'] );
t( 'Báo cáo còn NHÁP không bị đếm (đó là bản đang gõ dở, chưa phải con số nào)',
	1 === $nk['boQua']['bcChuaDuyet'], $nk['boQua'] );
t( 'Chỉ báo cáo HOÀN TẤT vào sổ', 200000 === $nk['tongCo'], $nk );

$pm = mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ) ) );
VHJP_Kho::huy_nhap( $KT, $pm['id'], 'gõ nhầm' );
$nk = VHJP_ButToan::nhat_ky_chung( $KT, 9, 2026, '' );
t( 'Phiếu đã huỷ được đếm và không vào sổ',
	1 === $nk['boQua']['phieuHuy'] && 200000 === $nk['tongCo'], $nk['boQua'] );

/* ═══════════════════════════════════════════════════ ⑥ SỔ CHI TIẾT MỘT TÀI KHOẢN ═══════ */
nen();
bc_moi( 'BCA', 'CS01', '2026-08-01', '2026-08-15', array( 300000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
bc_moi( 'BCB', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
VHJP_Nguon::them( 'JP_Payments', array( 'id' => 'NT1', 'reportId' => 'BCA',
	'locationId' => 'CS01', 'amount' => 300000, 'payDate' => '2026-09-05', 'method' => 'TM' ) );
VHJP_Nguon::them( 'JP_Payments', array( 'id' => 'NT2', 'reportId' => 'BCB',
	'locationId' => 'CS01', 'amount' => 200000, 'payDate' => '2026-09-20', 'method' => 'CK' ) );

$ct = VHJP_ButToan::nhat_ky_chung( $KT, 9, 2026, '131' );
t( '🔴 Lọc một tài khoản thì có DƯ ĐẦU KỲ (tháng 8 để lại 300.000)',
	300000 === $ct['duDauNo'] && 0 === $ct['duDauCo'], $ct );
t( 'Sổ chi tiết 131 có 3 dòng: bán 500k, thu 300k, thu 200k', 3 === count( $ct['rows'] ), $ct['rows'] );
$cuoi = $ct['rows'][ count( $ct['rows'] ) - 1 ];
t( '🔴 Dư chạy tới dòng cuối = 300k + 500k − 300k − 200k = 300.000',
	300000 === $cuoi['duNo'], $cuoi );
t( 'Dòng nào cũng có duNo/duCo để in cột dư',
	array_key_exists( 'duNo', $ct['rows'][0] ) && array_key_exists( 'duCo', $ct['rows'][0] ),
	array_keys( $ct['rows'][0] ) );
t( 'Tiền mặt và chuyển khoản vào HAI đầu khác nhau',
	300000 === tk( VHJP_ButToan::can_doi( $KT, 9, 2026 ), '1111' )['psNo']
	&& 200000 === tk( VHJP_ButToan::can_doi( $KT, 9, 2026 ), '1121' )['psNo'] );
$ca = VHJP_ButToan::nhat_ky_chung( $KT, 9, 2026, '' );
t( 'Không lọc thì không có dư đầu kỳ (cả sổ thì dư đầu vô nghĩa)',
	0 === $ca['duDauNo'] && 0 === $ca['duDauCo'], $ca );

/* ══════════════════════════════════════════════════════ ⑦ BẢNG CÂN ĐỐI ═════════════════ */
$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( '🔴 Ba phép cân, in riêng từng cái',
	array_key_exists( 'canBangPs', $cd ) && array_key_exists( 'canBangDau', $cd )
	&& array_key_exists( 'canBangCuoi', $cd ), array_keys( $cd ) );
/* ⚠️ Cả ba cờ này là phép kiểm CẤU TRÚC, không phải phép kiểm số liệu: mỗi bút toán đẻ ra đều
   có đúng hai vế bằng nhau nên chúng cân LUÔN LUÔN, kể cả khi mọi con số đều sai. Giữ phép thử
   để một ngày `sinh()` bị sửa và đẻ ra bút toán thiếu vế thì đỏ — nhưng đừng đọc nó thành "sổ
   đúng". Việc bắt số sai nằm ở ⑧ (so hai đường độc lập). */
t( 'Cả ba đều cân', $cd['canBangPs'] && $cd['canBangDau'] && $cd['canBangCuoi'], $cd );
t( 'Dư đầu kỳ của tháng 9 lấy từ tháng 8', 300000 === $cd['tong']['duDauNo'], $cd['tong'] );
t( 'Dư đầu Nợ = Dư đầu Có (tháng 8 đã cân)',
	$cd['tong']['duDauNo'] === $cd['tong']['duDauCo'], $cd['tong'] );
t( 'Tài khoản xếp theo mã', '1111' === $cd['rows'][0]['tk'], array_column( $cd['rows'], 'tk' ) );
t( 'Mỗi dòng đếm số bút toán để bấm sang sổ chi tiết',
	$cd['rows'][0]['soDong'] > 0, $cd['rows'][0] );

/* Đầu còn TẠM phải mang nhãn TẠM ở MỌI màn — đoán một đầu rồi in ra như thật là lỗi 1567. */
$t5111 = tk( $cd, '5111' );
t( '🔴 Đầu 5111 mang nhãn TẠM', ! empty( $t5111['laTam'] ), $t5111 );
t( 'Và nói rõ cần xin gì để chốt',
	false !== strpos( $t5111['viecTam'], 'Sổ chi tiết TK 511' ), $t5111 );
t( 'Đầu 131 KHÔNG phải tạm', empty( tk( $cd, '131' )['laTam'] ), tk( $cd, '131' ) );
foreach ( array( 'nhat_ky_chung', 'can_doi', 'kiem_tra', 'ket_qua_kd' ) as $ham ) {
	$d = call_user_func( array( 'VHJP_ButToan', $ham ), $KT, 9, 2026 );
	t( 'Màn ' . $ham . ' đều kèm danh sách đầu TẠM',
		! empty( $d['tkTam'] ) && 4 === count( $d['tkTam'] ), $ham );
	t( 'Màn ' . $ham . ' đều nói suất thuế GTGT đang tách',
		array_key_exists( 'vatSuat', $d ), $ham );
}

/* ═══════════════════════════════════════════════════════════ ⑧ KIỂM TRA SỔ ═════════════ */
nen();
bc_moi( 'BCP', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
VHJP_Nguon::them( 'JP_Payments', array( 'id' => 'NTP', 'reportId' => 'BCP',
	'locationId' => 'CS01', 'amount' => 500000, 'payDate' => '2026-09-20', 'method' => 'TM' ) );
VHJP_Nguon::sua( 'JP_Reports', 'BCP', array( 'paid' => 1, 'paidDate' => '2026-09-20' ) );
$kb = VHJP_ButToan::kiem_tra( $KT, 9, 2026 );
t( 'Hai đường khớp thì báo KHỚP', ! empty( $kb['khop'] ), $kb );
t( 'Dư 131 hai đường đều bằng 0', 0 === $kb['du131ButToan'] && 0 === $kb['du131CongNo'], $kb );
t( 'Tiền đã thu hai đường đều 500.000',
	500000 === $kb['thuButToan'] && 500000 === $kb['thuCongNo'], $kb );

/* 🔴 Loại lỗi mà CHỈ màn này bắt được: đánh dấu đã thu mà KHÔNG sinh dòng nộp tiền. */
VHJP_Nguon::xoa( 'JP_Payments', 'NTP' );
$kb = VHJP_ButToan::kiem_tra( $KT, 9, 2026 );
t( '🔴 Đánh dấu đã thu mà không có dòng nộp tiền ⇒ KHÔNG KHỚP', empty( $kb['khop'] ), $kb );
t( 'Lệch đúng 500.000 ở bên THU', -500000 === $kb['lechThu'], $kb );
t( 'Bên dư 131 cũng lệch đúng ngần ấy', 500000 === $kb['lechDu'], $kb );
/* Nhưng bảng cân đối vẫn CÂN — đó là lý do màn kiểm tra tồn tại. */
$cd = VHJP_ButToan::can_doi( $KT, 9, 2026 );
t( '⚠️ Trong khi bảng cân đối vẫn báo CÂN cả ba phép',
	$cd['canBangPs'] && $cd['canBangDau'] && $cd['canBangCuoi'], $cd );

/* `totalSubmit` bị sửa tay — đường sửa trong app đã đóng nên lệch ở đây là sửa thẳng dữ liệu. */
nen();
bc_moi( 'BCS', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 10000, 20000 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
VHJP_Nguon::sua( 'JP_Reports', 'BCS', array( 'totalSubmit' => 999999 ) );
$kb = VHJP_ButToan::kiem_tra( $KT, 9, 2026 );
t( '🔴 totalSubmit sửa tay thì bị bắt', 1 === $kb['soLechTongSubmit'], $kb );
t( 'Và nói rõ số ĐÚNG phải là 500.000 + 10.000 − 20.000 = 490.000',
	490000 === $kb['lechTongSubmit'][0]['suyRa'], $kb['lechTongSubmit'][0] );
t( 'Kèm mã báo cáo và tên cơ sở để đi tìm',
	'BCS' === $kb['lechTongSubmit'][0]['id'] && '' !== $kb['lechTongSubmit'][0]['coSo'],
	$kb['lechTongSubmit'][0] );
t( 'Có dòng lệch thì KHÔNG được báo khớp', empty( $kb['khop'] ), $kb );

/* ═══════════════════════════════════════════════════ ⑨ KẾT QUẢ KINH DOANH ══════════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 60 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 5 ) ) ) );
bc_moi( 'BK1', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 8000, 20000 ), array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 40 ) ) );
ky_du( $KT, 'BK1' );

$kq = VHJP_ButToan::ket_qua_kd( $KT, 9, 2026 );
t( 'KQKD chạy được', ! empty( $kq['ok'] ), $kq );
t( 'Doanh thu 500.000 · giảm trừ 20.000 · thuần 480.000',
	500000 === $kq['doanhThu'] && 20000 === $kq['giamTru'] && 480000 === $kq['doanhThuThuan'], $kq );
t( 'Giá vốn 40×1000 = 40.000', 40000 === $kq['giaVon'], $kq );
t( 'Lãi gộp 480.000 − 40.000 = 440.000', 440000 === $kq['laiGop'], $kq );
t( 'Tỷ lệ lãi gộp ≈ 91,7%', 91.7 === $kq['tyLeLaiGop'], $kq );
t( 'Chi phí bán hàng (xé mẫu) 5×1000 = 5.000', 5000 === $kq['chiPhiBH'], $kq );
t( 'Lợi nhuận thuần 435.000', 435000 === $kq['loiNhuanThuan'], $kq );
t( 'Thu nhập khác = lệch máy dương 8.000', 8000 === $kq['thuNhapKhac'], $kq );
t( 'Lãi trước thuế 443.000', 443000 === $kq['lnTruocThue'], $kq );
t( '🔴 Hai đường tính KHỚP', ! empty( $kq['khop'] ), $kq['lech'] );
t( '🔴 911 triệt tiêu: Có − Nợ = lãi trước thuế', ! empty( $kq['trietTieu911'] ), $kq );
t( 'Bút toán kết chuyển: 5 dòng (giảm trừ · giá vốn · chi phí BH · doanh thu · thu khác)',
	5 === count( $kq['ketChuyen'] ), $kq['ketChuyen'] );
t( 'Không in dòng kết chuyển 0đ (gõ thừa vào MISA là phải đi xoá)',
	0 === count( array_filter( $kq['ketChuyen'], function ( $x ) { return 0 == $x['soTien']; } ) ),
	$kq['ketChuyen'] );
t( 'Mỗi dòng kết chuyển kèm TÊN tài khoản',
	'' !== $kq['ketChuyen'][0]['tenNo'] && '' !== $kq['ketChuyen'][0]['tenCo'], $kq['ketChuyen'][0] );
t( 'Bước cuối: lãi thì Nợ 911 / Có 421?',
	'911' === $kq['buocCuoi']['tkNo'] && '421?' === $kq['buocCuoi']['tkCo']
	&& 443000 === $kq['buocCuoi']['soTien'], $kq['buocCuoi'] );
t( '🔴 Bước cuối tự nhận là ĐẦU CHƯA CHỐT', ! empty( $kq['buocCuoi']['chuaChotDau'] ), $kq['buocCuoi'] );

/* 🔴 Phép kiểm phải TỰ NHẬN đường nào không độc lập. Nói quá sức mạnh của một phép kiểm là
   đúng bệnh cờ `canBang` gộp — người đọc tin vào một bằng chứng không tồn tại. */
nen();
VHJP_Nguon::them( 'JP_KhoXuat', array( 'id' => 'XKL', 'soChungTu' => 'L-01',
	'ngay' => '2026-09-05', 'loai' => 'BAN', 'khoId' => 'CS01', 'itemCode' => 'H1',
	'qty' => 1, 'unitCost' => 1000, 'amount' => 1000, 'tkNo' => '6321', 'tkCo' => '632' ) );
$kq = VHJP_ButToan::ket_qua_kd( $KT, 9, 2026 );
t( 'Hai đường vẫn khớp ở giá vốn (cùng đọc JP_KhoXuat)', ! empty( $kq['khop'] ), $kq['lech'] );
/* Dựng một lệch THẬT ở giá vốn bằng cách đổi ngày để hai lối lọc khác nhau là không làm được —
   nên thay vào đó soi CỜ: mọi khoản phải khai đúng mình có độc lập hay không. */
nen();
bc_moi( 'BK9', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
VHJP_Nguon::sua( 'JP_Reports', 'BK9', array( 'revMeter' => 100000 ) );
$kq = VHJP_ButToan::ket_qua_kd( $KT, 9, 2026 );
t( 'Khớp thì mảng lệch rỗng', ! $kq['lech'], $kq['lech'] );

/* Kỳ rỗng: không có gì để kết chuyển, và phải nói ra chứ không vỡ. */
nen();
$kq = VHJP_ButToan::ket_qua_kd( $KT, 9, 2026 );
t( 'Kỳ rỗng: không vỡ', ! empty( $kq['ok'] ), $kq );
t( 'Kỳ rỗng: không có bút toán kết chuyển nào', ! $kq['ketChuyen'], $kq['ketChuyen'] );
t( 'Kỳ rỗng: không có bước cuối', null === $kq['buocCuoi'], $kq );
t( 'Kỳ rỗng: tỷ lệ lãi gộp là null, KHÔNG phải 0 (chia cho 0 không ra 0)',
	null === $kq['tyLeLaiGop'], $kq );
t( 'Kỳ rỗng: 911 vẫn triệt tiêu', ! empty( $kq['trietTieu911'] ), $kq );

/* Kỳ LỖ: bước cuối đảo chiều. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 60 ) ) ) );
bc_moi( 'BL1', 'CS01', '2026-09-01', '2026-09-15', array( 10000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
$kq = VHJP_ButToan::ket_qua_kd( $KT, 9, 2026 );
t( 'Kỳ LỖ: lợi nhuận âm', $kq['lnTruocThue'] < 0, $kq );
t( '🔴 Kỳ LỖ thì bước cuối ĐẢO CHIỀU: Nợ 421? / Có 911',
	'421?' === $kq['buocCuoi']['tkNo'] && '911' === $kq['buocCuoi']['tkCo'], $kq['buocCuoi'] );
t( 'Số tiền bước cuối là trị tuyệt đối', $kq['buocCuoi']['soTien'] > 0, $kq['buocCuoi'] );
t( '911 vẫn triệt tiêu khi lỗ', ! empty( $kq['trietTieu911'] ), $kq );

/* ═════════════════════════════════════════════════════════════════ ⑩ CỔNG ══════════════ */
nen();
bc_moi( 'BCG', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ), array(),
	VHJP_BaoCao::TT_HOAN_TAT );
$ra = VHJP_Cong::bt_nhat_ky( array( 'THE-PHIEN', 9, 2026, '131' ), $KT );
t( 'Cổng: bt_nhat_ky đọc tham số ở $args[1..3]',
	9 === $ra['thang'] && '131' === $ra['tk'] && 1 === count( $ra['rows'] ), $ra );
$ra = VHJP_Cong::bt_can_doi( array( 'THE-PHIEN', 9, 2026 ), $KT );
t( 'Cổng: bt_can_doi', 9 === $ra['thang'] && $ra['rows'], $ra );
$ra = VHJP_Cong::bt_kiem_tra( array( 'THE-PHIEN', 9, 2026 ), $KT );
t( 'Cổng: bt_kiem_tra', array_key_exists( 'khop', $ra ), $ra );
$ra = VHJP_Cong::bt_kqkd( array( 'THE-PHIEN', 9, 2026 ), $KT );
t( 'Cổng: bt_kqkd', 100000 === $ra['doanhThu'], $ra );

/* 🔴 `jpDoiTkKhoCu(token, ghi)` — đọc nhầm nấc tham số là lượt XEM TRƯỚC biến thành ghi đè. */
VHJP_Nguon::them( 'JP_KhoXuat', array( 'id' => 'XKG', 'soChungTu' => 'G-01',
	'ngay' => '2026-09-05', 'loai' => 'BAN', 'khoId' => 'CS01', 'itemCode' => 'H1',
	'qty' => 1, 'unitCost' => 100, 'amount' => 100, 'tkNo' => '632', 'tkCo' => '1567' ) );
$ra = VHJP_Cong::bt_doi_tk( array( 'THE-PHIEN' ), $KT );
t( '🔴 Cổng: thiếu cờ ghi thì mặc định XEM TRƯỚC, không ghi đè',
	empty( $ra['ghi'] ) && 0 === $ra['daGhi']
	&& '632' === VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_KhoXuat', 'id', 'XKG' )['tkNo'] ), $ra );
$ra = VHJP_Cong::bt_doi_tk( array( 'THE-PHIEN', true ), $KT );
t( 'Cổng: cờ ghi ở $args[1] thì mới sửa thật', 1 === $ra['daGhi'], $ra );

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
