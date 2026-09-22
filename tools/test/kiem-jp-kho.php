<?php
/**
 * KIỂM KHO HAI TẦNG CỦA JP CAPSULE (wordpress/vhcp-jp/includes/class-vhjp-kho.php).
 *
 * =============================================================================================
 * 🔴 BÀI NÀY CANH GIÁ VỐN, KHÔNG PHẢI CANH "HÀM CÓ CHẠY KHÔNG"
 * =============================================================================================
 * Sổ kho đẻ ra con số 632 — thứ đi thẳng vào MISA và vào báo cáo đã ký. Sai ở đây không hiện ra
 * thành một màn lỗi; nó hiện ra thành một con số hơi khác, ba tháng sau, lúc không ai còn nhớ.
 * Nên mọi phép thử dưới đây đều hỏi đúng một kiểu câu: **đồng nào ra đồng ấy chưa**.
 *
 * Bảy chỗ dễ sai nhất, và bài kiểm canh đúng bảy chỗ ấy:
 *
 *   1. FIFO xếp theo NGÀY, không theo mã. Phiếu gõ bù cho tháng trước (mã sinh sau, ngày trước)
 *      phải được ăn TRƯỚC. Xếp theo mã là giá vốn lấy nhầm lớp mới.
 *   2. Ăn một phần lớp thì lớp phải còn lại đúng phần chưa ăn, không bị xoá cả lớp.
 *   3. Thiếu lớp thì VẪN xuất, đơn giá 0, cờ `thieuLop` bật. Im lặng bỏ qua là hàng xuất thật mà
 *      sổ không ghi.
 *   4. `XUAT_CS` và `TRA_KHO` làm ĐỦ HAI VẾ, đúng chiều. Một vế là hàng bốc hơi giữa đường.
 *   5. Huỷ phiếu nhập mà lớp ĐÃ BỊ ĂN thì phải CHỐI. Cho huỷ là rút nền móng của một số đã chốt.
 *   6. `Xuất lại` trả hàng về ĐÚNG LỚP CŨ, không đẻ lớp mới — lớp mới nhảy xuống cuối hàng FIFO
 *      và đổi giá vốn của mọi phiếu sau đó.
 *   7. Bảng N-X-T và thẻ kho phải CÂN, và phải BÁO KHÔNG CÂN khi lớp lệch chứng từ thật.
 *
 * Chạy: php tools/test/kiem-jp-kho.php
 */

require_once __DIR__ . '/wp-stub.php';

/* ---------------------------------------------------- mấy hàm WordPress mà bệ đỡ chưa có */
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $n = 12, $dac_biet = true, $them = false ) {
		$c = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$s = '';
		for ( $i = 0; $i < $n; $i++ ) { $s .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; }
		return $s;
	}
}
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

/* ------------------------------------------------------------------ đếm & in kết quả */
$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
/** Gọi một hàm và bắt lỗi — để phép thử "phải chối" báo ĐỎ chứ không làm sập cả bài. */
function ne( $f ) {
	try { return array( 'ok' => true, 'ra' => $f() ); }
	catch ( Throwable $e ) { return array( 'ok' => false, 'loi' => $e->getMessage() ); }
}

vhjp_test_boot( dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp' );

/* ------------------------------------------------------------------ nền chung */
$KT = array( 'id' => 'K1', 'hoTen' => 'Kế Toán', 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );
$NV = array( 'id' => 'U1', 'hoTen' => 'Nhân Viên A', 'role' => VHJP_Auth::VAI_NV,
	'locationIds' => array( 'CS01' ) );

function nen() {
	vhjp_dung_bang();
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS01', 'name' => 'JP Bà Rịa', 'code' => 'BR' ) );
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS02', 'name' => 'JP Vũng Tàu', 'code' => 'VT' ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H1', 'misa' => 'M1', 'name' => 'Trứng khủng long',
		'dvt' => 'Quả', 'active' => 1 ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H2', 'misa' => '', 'name' => 'Vòng tay',
		'dvt' => 'Cái', 'active' => 1 ) );
}
/** Một phiếu nhập mua nhanh gọn. */
function mua( $u, $ngay, $rows, $ncc = 'NCC A' ) {
	return VHJP_Kho::nhap( $u, array( 'ngay' => $ngay, 'nccTen' => $ncc, 'rows' => $rows ) );
}
function dong( $ma, $sl, $gia = 0 ) {
	return array( 'itemCode' => $ma, 'qty' => $sl, 'unitCost' => $gia );
}

/* ═══════════════════════════════════════════════════════════ ① QUYỀN ═══════════════════ */
nen();
$r = ne( function () { global $NV; return VHJP_Kho::ton_kho( $NV ); } );
t( 'Nhân viên cơ sở KHÔNG đọc được tồn kho', ! $r['ok'], $r );
$r = ne( function () { global $NV; return mua( $NV, '2026-09-01', array( dong( 'H1', 5, 100 ) ) ); } );
t( 'Nhân viên cơ sở KHÔNG ghi được phiếu nhập', ! $r['ok'], $r );
$r = ne( function () { global $KT; return VHJP_Kho::ton_kho( $KT ); } );
t( 'Kế toán đọc được tồn kho', $r['ok'], $r );

/* ⚠️ Gác quyền phải nằm ở BẢNG `chi_ke_toan()` của cổng, không phải chỉ trong từng hàm — thiếu
   một tên ở bảng ấy là hở đúng một cửa mà không ai đếm được. */
$gac = VHJP_Cong::chi_ke_toan();
$map = array_keys( VHJP_Cong::map() );
$thieu_gac = array();
foreach ( $map as $fn ) {
	if ( 0 === strpos( $fn, 'jpKho' ) && ! in_array( $fn, $gac, true ) ) { $thieu_gac[] = $fn; }
}
t( 'Mọi hàm jpKho* đã mapped đều nằm trong chi_ke_toan()', ! $thieu_gac, $thieu_gac );

/* Một tên không được vừa "đã làm" vừa "chưa làm" — bảng đếm mất nghĩa ngay lúc ấy. */
$ca_hai = array_intersect( $map, VHJP_Cong::chua_lam() );
t( 'Không tên nào vừa nằm trong map() vừa nằm trong chua_lam()', ! $ca_hai, $ca_hai );

/* ═══════════════════════════════════════════════════════ ② NHẬP · LỚP ══════════════════ */
nen();
$p1 = mua( $KT, '2026-09-05', array( dong( 'H1', 10, 1000 ), dong( 'H2', 4, 500 ) ) );
t( 'Nhập trả ok', ! empty( $p1['ok'] ), $p1 );
t( 'Nhập ghi đúng 2 dòng', 2 === (int) $p1['soDong'], $p1 );
t( 'Tổng tiền nhập = 10×1000 + 4×500', 12000 === (int) $p1['tongTien'], $p1 );
t( 'Số chứng từ để trống thì lấy chính mã phiếu', $p1['soChungTu'] === $p1['id'], $p1 );

$lop = VHJP_Nguon::tim( 'JP_KhoLop', 'nguon', $p1['id'] );
t( 'Phiếu nhập đẻ đúng 2 lớp', 2 === count( $lop ), count( $lop ) );
t( 'Lớp mang nguồn = MÃ PHIẾU (để huỷ còn tìm lại được)',
	VHJP_Doc::str( $lop[0]['nguon'] ) === $p1['id'], $lop[0] );
t( 'Lớp vào KHO TỔNG, không vào cơ sở nào',
	'TONG' === VHJP_Doc::str( $lop[0]['khoId'] ) && '' === VHJP_Doc::str( $lop[0]['locationId'] ), $lop[0] );

$ton = VHJP_Kho::ton_mot( 'TONG', 'H1' );
t( 'Tồn H1 = 10 cái · 10.000đ', 10 === $ton['tonQty'] && 10000 === $ton['tonTien'], $ton );

$tk = VHJP_Kho::ton_kho( $KT );
t( 'Bảng tồn có dòng tổng', isset( $tk['tong']['tonQty'] ) && 14 === $tk['tong']['tonQty'], $tk['tong'] );
t( 'Bảng tồn gắn cờ laKhoTong', 1 === $tk['rows'][0]['laKhoTong'], $tk['rows'][0] );

/* Nhập luôn vào kho tổng, kể cả khi giao diện gửi kèm khoId khác — đường vào cơ sở phải qua
   XUAT_CS để có vết chuyển. */
$p_ep = VHJP_Kho::nhap( $KT, array( 'ngay' => '2026-09-06', 'khoId' => 'CS01',
	'rows' => array( dong( 'H1', 3, 900 ) ) ) );
$lop_ep = VHJP_Nguon::tim( 'JP_KhoLop', 'nguon', $p_ep['id'] );
t( 'Nhập mua LUÔN vào kho tổng dù gửi kèm khoId cơ sở',
	'TONG' === VHJP_Doc::str( $lop_ep[0]['khoId'] ), $lop_ep[0] );

/* ═══════════════════════════════════════════════════════════ ③ FIFO ════════════════════ */
nen();
/* Phiếu A ghi ngày 10, phiếu B GÕ SAU nhưng ngày 01 — B phải bị ăn TRƯỚC. */
$a = mua( $KT, '2026-09-10', array( dong( 'H1', 5, 2000 ) ) );
$b = mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ) ) );
t( 'Phiếu gõ bù có mã SINH SAU', strcmp( $b['id'], $a['id'] ) > 0, array( $a['id'], $b['id'] ) );

$lc = VHJP_Kho::lop_con( 'TONG', 'H1' );
t( 'FIFO xếp theo NGÀY: lớp ngày 01 đứng trước',
	1000 === VHJP_Doc::num( $lc[0]['unitCost'] ), array_map( function ( $l ) {
		return $l['ngay'] . '/' . $l['unitCost']; }, $lc ) );

$x = VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-15', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 3 ) ) ) );
t( 'Xuất 3 cái ăn lớp RẺ trước ⇒ giá vốn 3.000đ', 3000 === (int) $x['tongTien'], $x );
t( 'Không báo thiếu lớp', ! $x['thieuLop'], $x );

$lc = VHJP_Kho::lop_con( 'TONG', 'H1' );
t( 'Lớp bị ăn một phần CÒN LẠI 2, không bị xoá',
	2 === VHJP_Doc::num( $lc[0]['qtyRemaining'] ) && 1000 === VHJP_Doc::num( $lc[0]['unitCost'] ), $lc[0] );
t( 'qtyInit giữ nguyên 5 (còn soi được đã ăn bao nhiêu)',
	5 === VHJP_Doc::num( $lc[0]['qtyInit'] ), $lc[0] );

/* Ăn vắt qua hai lớp: 4 cái = 2 cái @1000 + 2 cái @2000 = 6.000đ, và phải ra HAI dòng sổ. */
$x2 = VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-16', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 4 ) ) ) );
t( 'Ăn vắt hai lớp: giá vốn 2×1000 + 2×2000 = 6.000đ', 6000 === (int) $x2['tongTien'], $x2 );
t( 'Ăn vắt hai lớp ⇒ HAI dòng sổ, mỗi dòng một đơn giá', 2 === (int) $x2['soDong'], $x2 );

/* ═════════════════════════════════════════════════════════ ④ THIẾU LỚP ═════════════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 2, 1000 ) ) );
$xt = VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-05', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 5 ) ) ) );
t( 'Thiếu lớp VẪN xuất được (không chặn nhân viên giữa ca)', ! empty( $xt['ok'] ), $xt );
t( 'Thiếu lớp: báo đúng mã và đúng số thiếu',
	1 === count( $xt['thieuLop'] ) && 'H1' === $xt['thieuLop'][0]['itemCode']
	&& 3 === $xt['thieuLop'][0]['qty'], $xt['thieuLop'] );
t( 'Thiếu lớp: tổng SL trên sổ vẫn là 5 — không nuốt mất phần thiếu',
	5 === (int) $xt['tongSL'], $xt );
t( 'Thiếu lớp: giá vốn chỉ tính phần có lớp = 2.000đ', 2000 === (int) $xt['tongTien'], $xt );
$ds_x = VHJP_Nguon::doc( 'JP_KhoXuat' );
$co_co = 0;
foreach ( $ds_x as $r ) { if ( VHJP_Doc::num( $r['thieuLop'] ) ) { $co_co++; } }
t( 'Thiếu lớp: có đúng 1 dòng sổ bật cờ thieuLop', 1 === $co_co, $ds_x );
t( 'Câu trả lời nói rõ THIẾU LỚP để giao diện in đỏ',
	false !== strpos( $xt['msg'], 'THIẾU LỚP' ), $xt['msg'] );

/* ═══════════════════════════════════════════════════ ⑤ HAI VẾ CHUYỂN KHO ═══════════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1500 ) ) );
$cs = VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 4 ) ) ) );
t( 'XUAT_CS chạy được', ! empty( $cs['ok'] ), $cs );
t( 'Vế MỘT: kho tổng còn 6', 6 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'TONG', 'H1' ) );
$ton_cs = VHJP_Kho::ton_mot( 'CS01', 'H1' );
t( 'Vế HAI: cơ sở có 4', 4 === $ton_cs['tonQty'], $ton_cs );
t( 'Vế HAI mang ĐÚNG giá vốn vừa ăn (6.000đ, không phải 0)', 6000 === $ton_cs['tonTien'], $ton_cs );

$nhan = VHJP_Kho::lich_su_dau_ky( $KT );
$dc = array();
foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
	if ( 'DC' === VHJP_Doc::str( $p['loaiNhap'] ) ) { $dc[] = $p; }
}
t( 'Vế HAI là một PHIẾU NHẬP thật ở cơ sở (loaiNhap = DC)', 1 === count( $dc ), $dc );
t( 'Phiếu nhận mang đúng số chứng từ của phiếu xuất',
	$dc && VHJP_Doc::str( $dc[0]['soChungTu'] ) === $cs['soChungTu'], $dc );
t( 'Đầu kỳ chưa khai gì thì lịch sử đầu kỳ RỖNG (không lẫn phiếu DC)', ! $nhan, $nhan );

/* TRA_KHO đi NGƯỢC: nguồn là cơ sở, đích là kho tổng. */
$tv = VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-08', 'loai' => 'TRA_KHO',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 1 ) ) ) );
t( 'TRA_KHO chạy được', ! empty( $tv['ok'] ), $tv );
t( 'TRA_KHO trừ ở CƠ SỞ: còn 3', 3 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
t( 'TRA_KHO cộng về KHO TỔNG: 6 + 1 = 7', 7 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'TONG', 'H1' ) );

$r = ne( function () { global $KT; return VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-09',
	'loai' => 'XUAT_CS', 'rows' => array( dong( 'H1', 1 ) ) ) ); } );
t( 'XUAT_CS thiếu cơ sở nhận thì CHỐI', ! $r['ok'], $r );
$r = ne( function () { global $KT; return VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-09',
	'loai' => 'KHONG_CO_LOAI_NAY', 'rows' => array( dong( 'H1', 1 ) ) ) ); } );
t( 'Loại xuất lạ thì CHỐI, không lặng lẽ ghi một dòng vô chủ', ! $r['ok'], $r );

/* Ô chọn loại xuất KHÔNG được có `BAN` và `KIEM_KE` — hai loại ấy phải do máy sinh. */
$ma_loai = array();
foreach ( VHJP_Kho::danh_sach_loai( $KT ) as $l ) { $ma_loai[] = $l['ma']; }
t( 'Ô chọn loại xuất có đủ 5 loại gõ tay', 5 === count( $ma_loai ), $ma_loai );
t( 'Ô chọn KHÔNG có BAN (phải sinh từ lượt duyệt báo cáo)',
	! in_array( 'BAN', $ma_loai, true ), $ma_loai );
t( 'Ô chọn KHÔNG có KIEM_KE (phải sinh từ phiếu kiểm kê)',
	! in_array( 'KIEM_KE', $ma_loai, true ), $ma_loai );

/* ══════════════════════════════════════════════════════════ ⑥ HUỶ NHẬP ═════════════════ */
nen();
$pn = mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ) ) );
$r = ne( function () use ( $pn ) { global $KT; return VHJP_Kho::huy_nhap( $KT, $pn['id'], '' ); } );
t( 'Huỷ mà không ghi lý do thì CHỐI', ! $r['ok'], $r );

$h = VHJP_Kho::huy_nhap( $KT, $pn['id'], 'Gõ nhầm số lượng' );
t( 'Huỷ phiếu chưa ai ăn thì được', ! empty( $h['ok'] ), $h );
t( 'Huỷ xong gỡ sạch lớp: tồn về 0', 0 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'TONG', 'H1' ) );
$r = ne( function () use ( $pn ) { global $KT; return VHJP_Kho::huy_nhap( $KT, $pn['id'], 'lần nữa' ); } );
t( 'Huỷ lần hai thì CHỐI', ! $r['ok'], $r );

$pn2 = mua( $KT, '2026-09-02', array( dong( 'H1', 5, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-03', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 1 ) ) ) );
$r = ne( function () use ( $pn2 ) { global $KT; return VHJP_Kho::huy_nhap( $KT, $pn2['id'], 'thử' ); } );
t( '🔴 Huỷ phiếu mà lớp ĐÃ BỊ ĂN MỘT PHẦN thì CHỐI', ! $r['ok'], $r );
t( 'Câu chối nói rõ mã hàng nào đã bị ăn',
	! $r['ok'] && false !== strpos( $r['loi'], 'H1' ), $r );
t( 'Chối xong tồn KHÔNG bị đụng vào: vẫn còn 4',
	4 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'TONG', 'H1' ) );

/* ═════════════════════════════════════════════════════════ ⑦ XUẤT LẠI ══════════════════ */
nen();
$pl = mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ) );
$lop_goc = VHJP_Nguon::tim( 'JP_KhoLop', 'nguon', $pl['id'] );
$ma_lop  = VHJP_Doc::str( $lop_goc[0]['id'] );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-05', 'loai' => 'XE_MAU', 'reportId' => 'BC9',
	'rows' => array( dong( 'H1', 4 ) ) ) );
t( 'Trước khi gỡ: còn 6', 6 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'TONG', 'H1' ) );

$xl = VHJP_Kho::xuat_lai( $KT, 'BC9' );
t( 'Xuất lại gỡ đúng 1 dòng', 1 === (int) $xl['daGo'], $xl );
t( 'Xuất lại trả tồn về 10', 10 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'TONG', 'H1' ) );
$sau = VHJP_Kho::lop_con( 'TONG', 'H1' );
t( '🔴 Xuất lại trả về ĐÚNG LỚP CŨ, KHÔNG đẻ lớp mới', 1 === count( $sau ), $sau );
t( 'Lớp được trả đúng là lớp gốc', VHJP_Doc::str( $sau[0]['id'] ) === $ma_lop, $sau[0] );
t( 'Dòng sổ cũ đã bị gỡ khỏi JP_KhoXuat', ! VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BC9' ) );

/* ═══════════════════════════════════════════════════════════ ⑧ ĐẦU KỲ ══════════════════ */
nen();
$dk = VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'CS01', 'ngay' => '2026-07-31',
	'rows' => array( dong( 'H1', 20, 800 ), dong( 'H2', 5, 400 ) ) ) );
t( 'Khai đầu kỳ được', ! empty( $dk['ok'] ), $dk );
t( 'Đầu kỳ: 2 mã · 25 cái · 18.000đ',
	2 === (int) $dk['soDong'] && 25 === (int) $dk['soLuong'] && 18000 === (int) $dk['tongTien'], $dk );
t( 'Đầu kỳ vào ĐÚNG kho cơ sở', 20 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'] );

$lai = VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'CS01', 'ngay' => '2026-07-31',
	'rows' => array( dong( 'H1', 99, 800 ) ) ) );
t( '🔴 Khai đầu kỳ LẦN HAI cho cùng kho thì chối', empty( $lai['ok'] ), $lai );
t( 'Chối bằng cờ daCoTruoc (giao diện đọc cờ này để in đỏ)', ! empty( $lai['daCoTruoc'] ), $lai );
t( 'Chối xong tồn KHÔNG bị cộng thêm: vẫn 20',
	20 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'CS01', 'H1' ) );

$dk2 = VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'CS02', 'ngay' => '2026-07-31',
	'rows' => array( dong( 'H1', 7, 800 ) ) ) );
t( 'Kho KHÁC vẫn khai được', ! empty( $dk2['ok'] ), $dk2 );

/* Khai nhầm thì huỷ phiếu rồi khai lại — nếu không thì kho ấy kẹt vĩnh viễn. */
VHJP_Kho::huy_nhap( $KT, $dk['id'], 'Khai nhầm giá' );
$dk3 = VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'CS01', 'ngay' => '2026-07-31',
	'rows' => array( dong( 'H1', 20, 900 ) ) ) );
t( 'Huỷ phiếu đầu kỳ rồi khai lại được (không kẹt vĩnh viễn)', ! empty( $dk3['ok'] ), $dk3 );

$ls = VHJP_Kho::lich_su_dau_ky( $KT );
t( 'Lịch sử đầu kỳ có đủ 3 phiếu (kể cả phiếu đã huỷ)', 3 === count( $ls ), count( $ls ) );
$da_huy = 0;
foreach ( $ls as $p ) { if ( $p['daHuy'] ) { $da_huy++; } }
t( 'Lịch sử đầu kỳ gắn cờ daHuy để giao diện khỏi cộng nhầm', 1 === $da_huy, $ls );

/* ═══════════════════════════════════════════════════════ ⑨ LỊCH SỬ PHIẾU ═══════════════ */
nen();
$pa = mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ), dong( 'H2', 2, 300 ) ), 'NCC A' );
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-08-31',
	'rows' => array( dong( 'H1', 1, 999 ) ) ) );
$ln = VHJP_Kho::lich_su_nhap( $KT, 50 );
t( 'Lịch sử NHẬP chỉ có phiếu MUA, không lẫn phiếu đầu kỳ', 1 === count( $ln ), $ln );
t( 'Lịch sử nhập kèm dong[] để giao diện in chi tiết', 2 === count( $ln[0]['dong'] ), $ln[0] );
t( 'Chi tiết xếp đúng thứ tự đã gõ', 'H1' === $ln[0]['dong'][0]['itemCode'], $ln[0]['dong'] );
t( 'Lịch sử nhập có cờ daHuy', array_key_exists( 'daHuy', $ln[0] ), array_keys( $ln[0] ) );

VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-04', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 2 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-05', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H2', 1 ) ) ) );
$lx = VHJP_Kho::lich_su_xuat( $KT, 50, '' );
t( 'Lịch sử XUẤT trả về PHIẾU, không phải từng dòng sổ', 2 === count( $lx ), $lx );
t( 'Lịch sử xuất kèm tenLoai đọc được', 'Xé mẫu trưng bày' === $lx[0]['tenLoai'], $lx[0] );
t( 'Lịch sử xuất kèm dong[]', 1 === count( $lx[0]['dong'] ), $lx[0] );
t( 'Lịch sử xuất có khoDen để lưới ngày lọc theo cơ sở',
	array_key_exists( 'khoDen', $lx[0] ), array_keys( $lx[0] ) );
$lx2 = VHJP_Kho::lich_su_xuat( $KT, 50, 'XUAT_CS' );
t( 'Lọc theo loại chỉ trả đúng loại ấy',
	1 === count( $lx2 ) && 'XUAT_CS' === VHJP_Doc::str( $lx2[0]['loai'] ), $lx2 );

/* ⚠️ Ô tên là `ma`/`ten`. Màn "Trả tiền NCC" dựng ô chọn bằng `n.ma + '|' + n.ten`; đổi tên ô
   là mọi mục thành `undefined|undefined` — ô vẫn hiện, bấm vẫn được, phiếu ghi ra không có NCC. */
$ncc = VHJP_Kho::danh_sach_ncc( $KT );
t( 'Danh sách NCC gom từ chính phiếu mua', 1 === count( $ncc ) && 'NCC A' === $ncc[0]['ten'], $ncc );
t( 'Danh sách NCC dùng đúng tên ô mà giao diện đọc (ma/ten)',
	array_key_exists( 'ma', $ncc[0] ) && array_key_exists( 'ten', $ncc[0] ), array_keys( $ncc[0] ) );

/* ════════════════════════════════════════════════════════ ⑩ NHẬP–XUẤT–TỒN ══════════════ */
nen();
/* Tháng 8 dựng nền, tháng 9 là kỳ xem. */
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-08-01',
	'rows' => array( dong( 'H1', 100, 1000 ) ) ) );
mua( $KT, '2026-09-02', array( dong( 'H1', 50, 1200 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-10', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 30 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-12', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 5 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-20', 'loai' => 'TRA_KHO',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 10 ) ) ) );
/* Một chuyển động SAU kỳ — bảng tháng 9 phải bỏ nó ra. */
mua( $KT, '2026-10-05', array( dong( 'H1', 7, 1300 ) ) );

$n = VHJP_Kho::nhap_xuat_ton( $KT, 9, 2026, '' );
t( 'N-X-T chạy được', ! empty( $n['ok'] ), $n );
t( 'N-X-T cắt đúng khoảng ngày', '2026-09-01' === $n['tuNgay'] && '2026-09-30' === $n['denNgay'], $n );

$tg = null; $c1 = null;
foreach ( $n['khoi'] as $k ) {
	if ( 'TONG' === $k['khoId'] ) { $tg = $k; }
	if ( 'CS01' === $k['khoId'] ) { $c1 = $k; }
}
t( 'N-X-T tách theo kho: có KHO TỔNG và CS01', $tg && $c1, array_column( $n['khoi'], 'khoId' ) );
t( 'Kho tổng xếp lên đầu', 1 === $n['khoi'][0]['laKhoTong'], $n['khoi'][0]['khoId'] );

$T = $tg['tong'];
t( 'KHO TỔNG · tồn đầu kỳ tháng 9 = 100', 100 === $T['tonDau'], $T );
t( 'KHO TỔNG · mua trong kỳ = 50', 50 === $T['nhapMua'], $T );
t( 'KHO TỔNG · đầu kỳ KHÔNG rơi vào tháng 9', 0 === $T['nhapDauKy'], $T );
t( 'KHO TỔNG · xuất cơ sở = 30', 30 === $T['xuatCoSo'], $T );
t( 'KHO TỔNG · xé mẫu = 5', 5 === $T['xuatXeMau'], $T );
t( 'KHO TỔNG · tổng xuất = 35 (trả về KHÔNG nằm trong đây)', 35 === $T['tongXuat'], $T );
t( 'KHO TỔNG · trả về = +10', 10 === $T['traVe'], $T );
t( 'KHO TỔNG · tồn cuối = 100 + 50 + 10 − 35 = 125', 125 === $T['tonCuoi'], $T );
t( 'KHO TỔNG · chuyển động THÁNG 10 không lọt vào tồn cuối tháng 9', 125 === $T['tonCuoi'], $T );

$C = $c1['tong'];
t( 'CS01 · nhận điều chuyển = 30', 30 === $C['nhapDC'], $C );
t( '🔴 CS01 · trả về mang dấu ÂM (hàng đi ra) = −10', -10 === $C['traVe'], $C );
t( 'CS01 · tồn cuối = 0 + 30 + (−10) − 0 = 20', 20 === $C['tonCuoi'], $C );
t( 'CS01 · tồn đầu = 0', 0 === $C['tonDau'], $C );

t( 'Cộng cả hệ thì trả về triệt tiêu về 0', 0 === $n['tong']['traVe'], $n['tong'] );
t( 'Bảng CÂN: lớp tồn khớp chứng từ', ! empty( $n['canBang'] ), $n );
t( 'Bảng cân thì không có dòng lệch nào', 0 === $n['soDongLech'] && ! $n['viDuLech'], $n );

/* Đẳng thức phải cộng ra được trên TỪNG dòng — giao diện in nguyên đẳng thức ấy ra chữ. */
$le = array();
foreach ( $n['rows'] as $r ) {
	if ( $r['tonDau'] + $r['nhap'] + $r['traVe'] - $r['tongXuat'] !== $r['tonCuoi'] ) { $le[] = $r; }
	$bon = $r['nhapMua'] + $r['nhapDC'] + $r['nhapDauKy'] + $r['nhapKiemKe'];
	if ( $bon !== $r['nhap'] ) { $le[] = $r; }
	$sau_o = $r['xuatCoSo'] + $r['xuatBan'] + $r['xuatXeMau'] + $r['xuatTang']
		+ $r['xuatTinh'] + $r['xuatKiemKe'];
	if ( $sau_o !== $r['tongXuat'] ) { $le[] = $r; }
}
t( '🔴 Mọi dòng đều cộng ra đúng ba đẳng thức mà giao diện in ra', ! $le, $le );
t( 'Dòng N-X-T mang mã MISA cho bản xuất Excel', 'M1' === $n['rows'][0]['misa'], $n['rows'][0] );

/* Bảng phải BÁO KHÔNG CÂN khi lớp lệch chứng từ thật — dựng lệch bằng một phiếu xuất thiếu lớp. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 2, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-05', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 5 ) ) ) );
$nl = VHJP_Kho::nhap_xuat_ton( $KT, 9, 2026, 'TONG' );
t( '🔴 Xuất quá tồn ⇒ bảng BÁO KHÔNG CÂN', empty( $nl['canBang'] ), $nl );
t( 'Báo không cân thì nói rõ lệch bao nhiêu', 3 === $nl['lech'], $nl );
t( 'Báo không cân thì chỉ ra đúng mã nào',
	$nl['viDuLech'] && 'H1' === $nl['viDuLech'][0]['itemCode'], $nl['viDuLech'] );
t( 'Dòng lệch được gắn cờ lechDauKy để giao diện tô đỏ', 1 === $nl['rows'][0]['lechDauKy'], $nl['rows'][0] );

/* ═══════════════════════════════════════════════════════════ ⑪ THẺ KHO ═════════════════ */
nen();
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-08-01',
	'rows' => array( dong( 'H1', 40, 1000 ), dong( 'H2', 3, 500 ) ) ) );
mua( $KT, '2026-09-03', array( dong( 'H1', 10, 1100 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-07', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 6 ) ) ) );

$the = VHJP_Kho::the_kho( $KT, array( 'khoId' => 'TONG', 'itemCode' => 'H1',
	'tuNgay' => '2026-09-01', 'denNgay' => '2026-09-30' ) );
t( 'Thẻ kho một mã chạy được', ! empty( $the['ok'] ), $the );
t( 'Thẻ kho một mã: moiMa = false', empty( $the['moiMa'] ), $the['moiMa'] );
t( 'Thẻ kho · tồn đầu kỳ = 40', 40 === $the['tonDau'], $the );
t( 'Thẻ kho · tổng nhập trong kỳ = 10', 10 === $the['tongNhap'], $the );
t( 'Thẻ kho · tổng xuất = 6', 6 === $the['tongXuat'], $the );
t( 'Thẻ kho · tồn cuối = 44', 44 === $the['tonCuoi'], $the );
t( 'Thẻ kho · CÂN (lớp khớp chứng từ)', ! empty( $the['canBang'] ), $the );
t( 'Thẻ kho · hai chuyển động trong kỳ', 2 === count( $the['rows'] ), $the['rows'] );
t( 'Thẻ kho · cột "Còn lại" chạy đúng: 40+10=50 rồi 50−6=44',
	50 === $the['rows'][0]['conLai'] && 44 === $the['rows'][1]['conLai'], $the['rows'] );
t( 'Thẻ kho · cùng ngày thì NHẬP xếp trước XUẤT',
	0 < $the['rows'][0]['nhap'], $the['rows'][0] );
t( 'Thẻ kho · mỗi dòng có số chứng từ để đi tra ngược',
	'' !== $the['rows'][0]['soChungTu'], $the['rows'][0] );

$the_all = VHJP_Kho::the_kho( $KT, array( 'khoId' => 'TONG', 'itemCode' => '',
	'tuNgay' => '2026-09-01', 'denNgay' => '2026-09-30' ) );
t( 'Thẻ kho CẢ KHO: moiMa = true', ! empty( $the_all['moiMa'] ), $the_all['moiMa'] );
t( 'Thẻ kho cả kho: H2 không phát sinh nhưng CÒN TỒN nên vẫn hiện', 2 === $the_all['soMa'],
	array_column( $the_all['khoi'], 'itemCode' ) );
t( 'Thẻ kho cả kho: tổng tồn cuối = 44 + 3', 47 === $the_all['tong']['tonCuoi'], $the_all['tong'] );
t( 'Thẻ kho cả kho: cân', ! empty( $the_all['canBang'] ), $the_all );

$r = ne( function () { global $KT; return VHJP_Kho::the_kho( $KT, array( 'itemCode' => 'H1' ) ); } );
t( 'Thẻ kho thiếu kho thì CHỐI', ! $r['ok'], $r );

/* Thẻ kho phải BÁO KHÔNG CÂN khi lớp lệch thật. */
nen();
mua( $KT, '2026-08-01', array( dong( 'H1', 2, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-08-10', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 5 ) ) ) );
$tl = VHJP_Kho::the_kho( $KT, array( 'khoId' => 'TONG', 'itemCode' => 'H1',
	'tuNgay' => '2026-09-01', 'denNgay' => '2026-09-30' ) );
t( '🔴 Thẻ kho BÁO KHÔNG CÂN khi có dòng xuất mà lớp chưa trừ đủ', empty( $tl['canBang'] ), $tl );
/* Lớp tồn đã bị ăn sạch (còn 0), còn chứng từ cộng lại ra −3 vì phiếu xuất ghi 5 mà kho chỉ
   có 2. Ba cái chênh ấy CHÍNH LÀ phần "xuất mà lớp chưa trừ đủ" — đúng thứ phép kiểm đi tìm. */
t( 'Thẻ kho · lớp tồn nói 0, chứng từ nói −3, lệch 3',
	0 === $tl['tonDau'] && -3 === $tl['tonDauCT'] && 3 === $tl['lech'], $tl );

/* ═════════════════════════════════════════════════════════════ ⑫ CỔNG ══════════════════ */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ) ) );
/* Cổng đổi thẻ phiên ra `$nguoi` rồi mới gọi — nên tham số nghiệp vụ bắt đầu từ `$args[1]`.
   Đếm nhầm một nấc là hàm nhận THẺ PHIÊN làm mã kho. */
$ra = VHJP_Cong::kho_ton( array( 'THE-PHIEN', 'TONG' ), $KT );
t( 'Cổng: kho_ton đọc mã kho ở $args[1], không phải $args[0]',
	'TONG' === $ra['khoId'] && 1 === count( $ra['rows'] ), $ra );
$ra = VHJP_Cong::kho_nxt( array( 'THE-PHIEN', 9, 2026, 'TONG' ), $KT );
t( 'Cổng: kho_nxt nhận tháng/năm/kho ở $args[1..3]',
	9 === $ra['thang'] && 2026 === $ra['nam'] && 'TONG' === $ra['khoId'], $ra );
$ra = VHJP_Cong::kho_ls_xuat( array( 'THE-PHIEN', 50, '' ), $KT );
t( 'Cổng: kho_ls_xuat chạy', is_array( $ra ), $ra );

/* Cả 22 hàm kho đã chuyển; phép đối chiếu với giao diện nằm ở `kiem-jp-cong.php`. */
$con = array();
foreach ( VHJP_Cong::chua_lam() as $fn ) { if ( 0 === strpos( $fn, 'jpKho' ) ) { $con[] = $fn; } }
$da = array();
foreach ( array_keys( VHJP_Cong::map() ) as $fn ) { if ( 0 === strpos( $fn, 'jpKho' ) ) { $da[] = $fn; } }
t( 'Đã chuyển 22 hàm kho', 22 === count( $da ), $da );
t( 'Không còn hàm kho nào trong chua_lam()', ! $con, $con );

/* ═══════════════════════════════════════════════════════════════════ ⑬ KIỂM KÊ ═════════ */
nen();
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-01',
	'rows' => array( dong( 'H1', 10, 1000 ), dong( 'H2', 4, 500 ) ) ) );

$kt_ton = VHJP_Kho::kiem_ke_ton( $KT, 'TONG', false );
t( 'Mở phiếu kiểm kê: chỉ mã CÓ tồn', 2 === count( $kt_ton['rows'] ), $kt_ton['rows'] );
t( 'Mở phiếu kiểm kê: tồn sổ và giá bình quân đúng',
	10 === $kt_ton['rows'][0]['tonSo'] && 1000 == $kt_ton['rows'][0]['giaBinhQuan'], $kt_ton['rows'][0] );
t( 'Mở phiếu kiểm kê kèm mã MISA', 'M1' === $kt_ton['rows'][0]['misa'], $kt_ton['rows'][0] );
$r = ne( function () { global $KT; return VHJP_Kho::kiem_ke_ton( $KT, '' ); } );
t( 'Kiểm kê không chọn kho thì CHỐI', ! $r['ok'], $r );

/* Mã chưa có tồn chỉ hiện khi tick "hiện cả danh mục" — đó là đường DUY NHẤT phát hiện hàng
   thừa của một mã mà sổ nói đã hết. */
VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H3', 'name' => 'Móc khoá', 'active' => 1 ) );
$het = VHJP_Kho::kiem_ke_ton( $KT, 'TONG', true );
t( 'Tick "hiện cả danh mục" thì mã chưa có tồn cũng hiện', 3 === count( $het['rows'] ), $het['rows'] );

/* THIẾU — hàng mất, ăn FIFO và ghi Nợ 6321 / Có 1561. */
$kk = VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20',
	'cheDoThua' => 'GIAM_XUAT', 'rows' => array(
		array( 'itemCode' => 'H1', 'tonThuc' => 7, 'note' => 'mất 3' ),
		array( 'itemCode' => 'H2', 'tonThuc' => 4 ) ) ) );
t( 'Kiểm kê chạy được', ! empty( $kk['ok'] ), $kk );
t( 'Kiểm kê · thiếu 3 cái · 3.000đ', 3 === $kk['slThieu'] && 3000 === $kk['tienThieu'], $kk );
t( 'Kiểm kê · không có thừa', 0 === $kk['slThua'], $kk );
t( 'Kiểm kê · trừ đúng tồn: H1 còn 7', 7 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'TONG', 'H1' ) );
t( 'Kiểm kê · H2 khớp thì KHÔNG đụng tới', 4 === VHJP_Kho::ton_mot( 'TONG', 'H2' )['tonQty'] );
$dx = VHJP_Nguon::tim( 'JP_KhoXuat', 'loai', 'KIEM_KE' );
t( 'Kiểm kê thiếu đẻ ra dòng sổ xuất KIEM_KE', 1 === count( $dx ), $dx );
t( '🔴 Hàng mất ghi Nợ 6321 / Có 1561',
	'6321' === VHJP_Doc::str( $dx[0]['tkNo'] ) && '1561' === VHJP_Doc::str( $dx[0]['tkCo'] ), $dx[0] );

/* Mã CHƯA ĐẾM phải được giữ nguyên, không bị coi là 0 — coi là 0 là một lượt kiểm kê bỏ dở
   xoá sạch tồn của mọi mã chưa kịp đếm, và nó ghi thẳng vào 6321. */
nen();
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-01',
	'rows' => array( dong( 'H1', 10, 1000 ), dong( 'H2', 4, 500 ) ) ) );
VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20', 'rows' => array(
	array( 'itemCode' => 'H1', 'tonThuc' => 9 ),
	array( 'itemCode' => 'H2', 'tonThuc' => '' ) ) ) );
t( '🔴 Mã CHƯA ĐẾM (ô trống) giữ nguyên tồn, không bị coi là 0',
	4 === VHJP_Kho::ton_mot( 'TONG', 'H2' )['tonQty'], VHJP_Kho::ton_mot( 'TONG', 'H2' ) );
t( 'Ô trống khác số 0: mã đã đếm vẫn bị trừ', 9 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'] );

$r = ne( function () { global $KT; return VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG',
	'ngay' => '2026-09-20', 'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => '' ) ) ) ); } );
t( 'Không mã nào được đếm thì CHỐI', ! $r['ok'], $r );

/* THỪA · chế độ GIẢM XUẤT — giảm chính phiếu xuất trong kỳ, trả lại lớp. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ) );
$px = VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-10', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 6 ) ) ) );
t( 'Trước kiểm kê: tồn 4', 4 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'] );
$kg = VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20',
	'cheDoThua' => 'GIAM_XUAT',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 6 ) ) ) );
t( 'Thừa 2 · giảm xuất 2 cái', 2 === $kg['slGiamXuat'] && 2000 === $kg['tienGiamXuat'], $kg );
t( 'Thừa 2 · KHÔNG ghi tăng cái nào', 0 === $kg['slThua'], $kg );
t( 'Thừa 2 · không có mã nào "không giảm được"', ! $kg['khongGiamDuoc'], $kg );
t( '🔴 Giảm xuất trả lại lớp: tồn về đúng 6',
	6 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'TONG', 'H1' ) );
$con_x = VHJP_Nguon::tim( 'JP_KhoXuat', 'loai', 'XE_MAU' );
t( 'Dòng xuất cũ bị GIẢM chứ không bị xoá: còn 4',
	1 === count( $con_x ) && 4 === VHJP_Doc::num( $con_x[0]['qty'] ), $con_x );
t( 'Giảm xuất KHÔNG đẻ lớp mới', 1 === count( VHJP_Kho::lop_con( 'TONG', 'H1' ) ),
	VHJP_Kho::lop_con( 'TONG', 'H1' ) );

/* THỪA · phiếu xuất nằm NGOÀI kỳ đang mở thì không được đụng — phải ghi tăng và NÓI RA. */
nen();
mua( $KT, '2026-08-01', array( dong( 'H1', 10, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-08-10', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 6 ) ) ) );
$kq = VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20',
	'cheDoThua' => 'GIAM_XUAT',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 6 ) ) ) );
t( '🔴 Phiếu xuất THÁNG TRƯỚC không bị đụng vào (số đã khoá sổ)', 0 === $kq['slGiamXuat'], $kq );
t( 'Phần không giảm được thì ghi tăng 1561/1388', 2 === $kq['slThua'], $kq );
t( 'Và NÓI RA mã nào không giảm được',
	1 === count( $kq['khongGiamDuoc'] ) && 'H1' === $kq['khongGiamDuoc'][0]['itemCode'],
	$kq['khongGiamDuoc'] );
t( 'Câu trả lời cảnh báo rõ ràng', false !== strpos( $kq['msg'], 'KHÔNG giảm xuất' ), $kq['msg'] );
$dx8 = VHJP_Nguon::tim( 'JP_KhoXuat', 'loai', 'XE_MAU' );
t( 'Phiếu tháng trước còn nguyên 6 cái', 6 === VHJP_Doc::num( $dx8[0]['qty'] ), $dx8[0] );
t( 'Ghi tăng làm tồn lên 6', 6 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'] );
$pn_kk = array();
foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
	if ( 'KIEM_KE' === VHJP_Doc::str( $p['loaiNhap'] ) ) { $pn_kk[] = $p; }
}
t( 'Ghi tăng là một PHIẾU NHẬP loaiNhap = KIEM_KE', 1 === count( $pn_kk ), $pn_kk );

/* THỪA · chế độ GHI TĂNG thẳng — không đụng phiếu xuất nào. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 10, 1000 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-10', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 6 ) ) ) );
$kgt = VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20',
	'cheDoThua' => 'GHI_TANG',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 6 ) ) ) );
t( 'GHI_TANG: không giảm phiếu xuất nào', 0 === $kgt['slGiamXuat'], $kgt );
t( 'GHI_TANG: ghi tăng đủ 2 cái', 2 === $kgt['slThua'], $kgt );
t( 'GHI_TANG: phiếu xuất còn nguyên',
	6 === VHJP_Doc::num( VHJP_Nguon::tim( 'JP_KhoXuat', 'loai', 'XE_MAU' )[0]['qty'] ) );

/* Thừa một mã chưa từng nhập bao giờ ⇒ giá vốn 0, và phải NÓI RA. */
nen();
$k0 = VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-20',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 5 ) ) ) );
t( 'Thừa mã chưa từng nhập: vẫn ghi tăng 5 cái', 5 === $k0['slThua'], $k0 );
t( '🔴 Giá vốn 0 thì NÓI RA, không im lặng',
	array( 'H1' ) === $k0['giaVon0'] && false !== strpos( $k0['msg'], 'giá vốn 0' ), $k0 );

$lkk = VHJP_Kho::lich_su_kiem_ke( $KT, 30 );
t( 'Lịch sử kiểm kê có phiếu vừa ghi', 1 === count( $lkk ), $lkk );
t( 'Lịch sử kiểm kê kèm dong[]', 1 === count( $lkk[0]['dong'] ), $lkk[0] );
t( 'Dòng kiểm kê ghi rõ lệch và tài khoản',
	5 === $lkk[0]['dong'][0]['lech'] && '1561' === VHJP_Doc::str( $lkk[0]['dong'][0]['tkNo'] ),
	$lkk[0]['dong'][0] );

/* Kiểm kê thừa/thiếu phải lên đúng cột của bảng N-X-T. */
nen();
VHJP_Kho::so_du_dau_ky( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-01',
	'rows' => array( dong( 'H1', 10, 1000 ) ) ) );
VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-15',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 7 ) ) ) );
$nk = VHJP_Kho::nhap_xuat_ton( $KT, 9, 2026, 'TONG' );
t( 'Kiểm kê thiếu lên cột xuatKiemKe', 3 === $nk['tong']['xuatKiemKe'], $nk['tong'] );
t( 'Bảng vẫn cân sau kiểm kê', ! empty( $nk['canBang'] ), $nk );
t( 'Tồn cuối = 10 − 3', 7 === $nk['tong']['tonCuoi'], $nk['tong'] );

nen();
VHJP_Kho::kiem_ke( $KT, array( 'khoId' => 'TONG', 'ngay' => '2026-09-15',
	'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 4 ) ) ) );
$nk2 = VHJP_Kho::nhap_xuat_ton( $KT, 9, 2026, 'TONG' );
t( 'Kiểm kê thừa lên cột nhapKiemKe', 4 === $nk2['tong']['nhapKiemKe'], $nk2['tong'] );
t( 'Và nằm trong tổng Nhập', 4 === $nk2['tong']['nhap'], $nk2['tong'] );

/* ════════════════════════════════════════════════════════════ ⑭ TRẢ TIỀN NCC ═══════════ */
nen();
$tt = VHJP_Kho::tra_ncc( $KT, array( 'ngay' => '2026-09-10', 'nccTen' => 'NCC A',
	'soTien' => 5000000, 'hinhThuc' => 'CK', 'ghiChu' => 'Trả đợt 1' ) );
t( 'Ghi phiếu trả tiền được', ! empty( $tt['ok'] ), $tt );
t( 'Phiếu trả tiền có số chứng từ', $tt['soChungTu'] === $tt['id'], $tt );

$r = ne( function () { global $KT; return VHJP_Kho::tra_ncc( $KT,
	array( 'nccTen' => 'NCC A', 'soTien' => 0 ) ); } );
t( 'Số tiền ≤ 0 thì CHỐI', ! $r['ok'], $r );
$r = ne( function () { global $KT; return VHJP_Kho::tra_ncc( $KT, array( 'soTien' => 100 ) ); } );
t( 'Không có nhà cung cấp thì CHỐI', ! $r['ok'], $r );

/* Ô hình thức để trống: giao diện đã hứa trước là ghi CK — sổ phải nói đúng câu ấy. */
$tt2 = VHJP_Kho::tra_ncc( $KT, array( 'nccTen' => 'NCC B', 'soTien' => 100 ) );
$row = VHJP_Nguon::tim_mot( 'JP_KhoTraNcc', 'id', $tt2['id'] );
t( 'Hình thức để trống thì mặc định CK, đúng như giao diện đã hứa',
	'CK' === VHJP_Doc::str( $row['hinhThuc'] ), $row );
t( 'Ngày để trống thì lấy hôm nay', '' !== VHJP_Doc::ngay( $row['ngay'] ), $row );

/* Ghi cả lô: dòng hỏng KHÔNG làm đổ cả lô, nhưng phải được đếm và nói ra kèm số dòng. */
$lo = VHJP_Kho::tra_ncc_lo( $KT, array(
	array( 'dong' => 2, 'nccTen' => 'NCC A', 'soTien' => 1000 ),
	array( 'dong' => 3, 'nccTen' => '', 'soTien' => 500 ),
	array( 'dong' => 4, 'nccTen' => 'NCC C', 'soTien' => 2000 ),
	array( 'dong' => 5, 'nccTen' => 'NCC D', 'soTien' => 0 ) ) );
t( 'Ghi lô: 2 phiếu vào được', 2 === $lo['soPhieu'] && 3000 === $lo['tongTien'], $lo );
t( '🔴 Dòng hỏng KHÔNG làm đổ cả lô', 2 === count( $lo['hong'] ), $lo['hong'] );
t( 'Dòng hỏng được nói ra kèm ĐÚNG số dòng trong tệp',
	3 === $lo['hong'][0]['dong'] && 5 === $lo['hong'][1]['dong'], $lo['hong'] );
t( 'Có dòng hỏng thì ok = false để giao diện in đỏ', empty( $lo['ok'] ), $lo );
t( 'Câu trả lời liệt kê dòng nào rớt', false !== strpos( $lo['msg'], 'dòng 3' ), $lo['msg'] );

$ls_tt = VHJP_Kho::lich_su_tra_ncc( $KT, 50 );
t( 'Lịch sử trả tiền có đủ 4 phiếu', 4 === count( $ls_tt ), count( $ls_tt ) );
$h = VHJP_Kho::huy_tra_ncc( $KT, $tt['id'], 'Chuyển nhầm' );
t( 'Huỷ phiếu trả tiền được', ! empty( $h['ok'] ), $h );
$row = VHJP_Nguon::tim_mot( 'JP_KhoTraNcc', 'id', $tt['id'] );
t( '🔴 Huỷ là ĐÁNH DẤU, không xoá dòng (vết kiểm toán phải còn)', ! empty( $row ), $row );
t( 'Dòng đã huỷ mang cờ daHuy', VHJP_Kho::lich_su_tra_ncc( $KT, 50 )[3]['daHuy'] );
$r = ne( function () use ( $tt ) { global $KT; return VHJP_Kho::huy_tra_ncc( $KT, $tt['id'], 'x' ); } );
t( 'Huỷ lần hai thì CHỐI', ! $r['ok'], $r );
$r = ne( function () use ( $tt2 ) { global $KT; return VHJP_Kho::huy_tra_ncc( $KT, $tt2['id'], '' ); } );
t( 'Huỷ mà không ghi lý do thì CHỐI', ! $r['ok'], $r );

/* Công nợ: đã trả của phiếu ĐÃ HUỶ không được tính. */
nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 5, 1000 ) ), 'NCC A' );
$t1 = VHJP_Kho::tra_ncc( $KT, array( 'nccTen' => 'NCC A', 'soTien' => 2000 ) );
$t2 = VHJP_Kho::tra_ncc( $KT, array( 'nccTen' => 'NCC A', 'soTien' => 1000 ) );
VHJP_Kho::huy_tra_ncc( $KT, $t2['id'], 'ghi nhầm' );
$ds_ncc = VHJP_Kho::danh_sach_ncc( $KT );
t( 'Công nợ NCC: đã trả 2.000 (phiếu đã huỷ không tính)',
	5000 === $ds_ncc[0]['tongTien'] && 2000 === $ds_ncc[0]['daTra'], $ds_ncc[0] );

/* ══════════════════════════════════════════════════════════════════ ⑮ BẢNG KÊ ══════════ */
nen();
mua( $KT, '2026-09-05', array( dong( 'H1', 10, 1000 ) ) );
$pm2 = mua( $KT, '2026-09-06', array( dong( 'H2', 2, 300 ) ) );
mua( $KT, '2026-10-01', array( dong( 'H1', 1, 1000 ) ) );
VHJP_Kho::huy_nhap( $KT, $pm2['id'], 'gõ nhầm' );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-08', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 3 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-09', 'loai' => 'XE_MAU',
	'rows' => array( dong( 'H1', 1 ) ) ) );

$bn = VHJP_Kho::bang_ke_nhap( $KT, '2026-09-01', '2026-09-30', '' );
t( 'Bảng kê nhập cắt đúng khoảng ngày (bỏ phiếu tháng 10)', 3 === count( $bn['rows'] ), $bn['rows'] );
t( '🔴 Phiếu ĐÃ HUỶ vẫn liệt kê nhưng KHÔNG cộng vào tổng',
	2 === $bn['tong']['soPhieu'] && 1 === $bn['tong']['soHuy'], $bn['tong'] );
/* 10.000 của phiếu mua + 3.000 của phiếu NHẬN ĐIỀU CHUYỂN mà lượt XUAT_CS đẻ ra ở CS01. Phiếu
   DC là một phiếu nhập thật, nên bảng kê nhập phải thấy nó — giấu đi thì hàng xuống cơ sở không
   có chứng từ nào đứng sau. Phiếu đã huỷ (300đ) không cộng. */
t( 'Bảng kê nhập · tổng tiền bỏ phiếu huỷ = 10.000 mua + 3.000 nhận ĐC',
	13000 === $bn['tong']['tongTien'], $bn['tong'] );
t( 'Bảng kê nhập · tên đường nhập đọc được',
	'Mua hàng nhà cung cấp' === $bn['rows'][0]['tenLoai'], $bn['rows'][0] );
$bn2 = VHJP_Kho::bang_ke_nhap( $KT, '2026-09-01', '2026-09-30', 'DC' );
t( 'Lọc theo đường nhập DC chỉ ra phiếu nhận điều chuyển', 1 === count( $bn2['rows'] ), $bn2['rows'] );

$bx = VHJP_Kho::bang_ke_xuat( $KT, '2026-09-01', '2026-09-30', '' );
t( 'Bảng kê xuất có 2 phiếu', 2 === $bx['tong']['soPhieu'], $bx['tong'] );
t( 'Bảng kê xuất · tổng SL = 4', 4 === $bx['tong']['tongSL'], $bx['tong'] );
t( 'Bảng kê xuất · gộp theo loại', 2 === count( $bx['theoLoai'] ), $bx['theoLoai'] );
$co_tk = false;
foreach ( $bx['theoLoai'] as $l ) { if ( 'XE_MAU' === $l['ma'] && '64116' === $l['tk'] ) { $co_tk = true; } }
t( 'Bảng kê xuất · gộp theo loại kèm tài khoản (xé mẫu = 64116)', $co_tk, $bx['theoLoai'] );
$bx2 = VHJP_Kho::bang_ke_xuat( $KT, '2026-09-01', '2026-09-30', 'XUAT_CS' );
t( 'Lọc theo loại xuất', 1 === count( $bx2['rows'] ), $bx2['rows'] );

/* Cửa quyền của mấy hàm mới. */
foreach ( array(
	'kiem_ke_ton' => array( 'TONG' ),
	'lich_su_kiem_ke' => array( 30 ),
	'lich_su_tra_ncc' => array( 50 ),
) as $ham => $tham ) {
	$r = ne( function () use ( $ham, $tham ) {
		global $NV;
		return call_user_func_array( array( 'VHJP_Kho', $ham ), array_merge( array( $NV ), $tham ) );
	} );
	t( 'Nhân viên KHÔNG gọi được VHJP_Kho::' . $ham, ! $r['ok'], $r );
}
$r = ne( function () { global $NV; return VHJP_Kho::tra_ncc( $NV,
	array( 'nccTen' => 'X', 'soTien' => 1 ) ); } );
t( 'Nhân viên KHÔNG ghi được phiếu trả tiền', ! $r['ok'], $r );
$r = ne( function () { global $NV; return VHJP_Kho::kiem_ke( $NV, array( 'khoId' => 'TONG',
	'ngay' => '2026-09-01', 'rows' => array( array( 'itemCode' => 'H1', 'tonThuc' => 1 ) ) ) ); } );
t( 'Nhân viên KHÔNG ghi được biên bản kiểm kê', ! $r['ok'], $r );

/* ══════════════════════════════════════════════ ⑯ DUYỆT BÁO CÁO → SỔ 632 ══════════════ */
/*
 * Đây là chỗ giá vốn BÁN RA sinh ra. Trước bản 1.12.0 nó không sinh ở đâu cả: báo cáo hoàn tất,
 * doanh thu có, tiền có, ảnh có — và không một đồng giá vốn nào, nên lãi gộp của cả hệ bằng
 * đúng doanh thu. Loại sai không có triệu chứng: mọi màn đều xanh, chỉ con số cuối là sai.
 */
function bc_moi( $ma, $coso, $den, $dong_ds ) {
	VHJP_Nguon::them( 'JP_Reports', array( 'id' => $ma, 'locationId' => $coso,
		'locationName' => 'JP Bà Rịa', 'fromDate' => '2026-09-01', 'toDate' => $den,
		'userId' => 'U1', 'userName' => 'Nhân Viên A',
		'status' => VHJP_BaoCao::TT_CHO_DUYET ) );
	$i = 0;
	foreach ( $dong_ds as $d ) {
		$i++;
		VHJP_Nguon::them( 'JP_Rows', array_merge(
			array( 'id' => $ma . '-R' . $i, 'reportId' => $ma, 'seq' => $i ), $d ) );
	}
}
/** Ký đủ hai phần rồi trả về kết quả lượt ký cuối. */
function ky_du( $u, $ma ) {
	VHJP_Duyet::ky( $u, $ma, VHJP_BaoCao::PHAN_HANG, '' );
	return VHJP_Duyet::ky( $u, $ma, VHJP_BaoCao::PHAN_TIEN, '' );
}

nen();
mua( $KT, '2026-09-01', array( dong( 'H1', 100, 1000 ), dong( 'H2', 50, 400 ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( dong( 'H1', 60 ), dong( 'H2', 20 ) ) ) );
t( 'Nền: cơ sở có 60 H1', 60 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'] );

bc_moi( 'BC1', 'CS01', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 10 ),
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 5 ),
	array( 'rowKind' => 'HANG',  'itemCode' => 'H2', 'soldQty' => 4 ),
	/* Ba loại dòng KHÔNG mang hàng bán ra. `COIN` và `NGOAI` được `VHJP_Tinh` ép `soldQty` về
	   0; `MAY` thì `VHJP_Tinh` KHÔNG đụng tới ô ấy, nên số cũ của đời trước nằm lại — dựng ở
	   đây đúng số rác ấy để xem kho có lọc theo LOẠI DÒNG thật không. */
	array( 'rowKind' => 'COIN',  'itemCode' => 'H1', 'soldQty' => 999 ),
	array( 'rowKind' => 'NGOAI', 'itemCode' => 'H1', 'soldQty' => 777 ),
	array( 'rowKind' => 'MAY',   'itemCode' => 'H1', 'soldQty' => 888 ) ) );

$k1 = VHJP_Duyet::ky( $KT, 'BC1', VHJP_BaoCao::PHAN_HANG, '' );
t( '🔴 Ký MỘT phần thì CHƯA ra sổ kho', empty( $k1['done'] ) && null === $k1['kho'], $k1 );
t( 'Ký một phần: chưa có dòng xuất nào', ! VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BC1' ) );

$k2 = VHJP_Duyet::ky( $KT, 'BC1', VHJP_BaoCao::PHAN_TIEN, '' );
t( 'Ký đủ hai phần thì báo cáo HOÀN TẤT', ! empty( $k2['done'] ), $k2 );
t( '🔴 Hoàn tất thì RA SỔ KHO', ! empty( $k2['kho'] ) && empty( $k2['kho']['loi'] ), $k2['kho'] );
t( 'Gom theo mã: 2 dòng MONEY cùng mã H1 chỉ ăn FIFO một lượt ⇒ 2 dòng sổ (H1, H2)',
	2 === $k2['kho']['soDong'], $k2['kho'] );
t( '🔴 Giá vốn = 15×1000 + 4×400 = 16.600đ', 16600 === $k2['kho']['tongGiaVon'], $k2['kho'] );
t( '🔴 Dòng COIN / NGOAI / MAY KHÔNG bị cộng vào (lọc theo LOẠI DÒNG)',
	45 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
t( 'Trừ đúng ở kho CƠ SỞ, kho tổng không đụng',
	40 === VHJP_Kho::ton_mot( 'TONG', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'TONG', 'H1' ) );
t( 'H2 còn 16', 16 === VHJP_Kho::ton_mot( 'CS01', 'H2' )['tonQty'] );

$dx = VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BC1' );
t( '🔴 Hàng bán ghi Nợ 6321 / Có 1561',
	'6321' === VHJP_Doc::str( $dx[0]['tkNo'] ) && '1561' === VHJP_Doc::str( $dx[0]['tkCo'] ), $dx[0] );
t( 'Dòng sổ mang loại BAN', 'BAN' === VHJP_Doc::str( $dx[0]['loai'] ), $dx[0] );
t( 'Dòng sổ mang ngày CUỐI KỲ của báo cáo',
	'2026-09-15' === VHJP_Doc::ngay( $dx[0]['ngay'] ), $dx[0] );
t( 'Câu thông báo kể luôn giá vốn vừa ghi',
	false !== strpos( $k2['msg'], 'xuất kho' ) && false !== strpos( $k2['msg'], '16.600' ), $k2['msg'] );

/* Bán ra phải lên đúng cột `xuatBan` của bảng N-X-T — cột ấy trước đây luôn bằng 0. */
$nb = VHJP_Kho::nhap_xuat_ton( $KT, 9, 2026, 'CS01' );
t( '🔴 Giá vốn bán ra lên cột xuatBan của bảng N-X-T', 19 === $nb['tong']['xuatBan'], $nb['tong'] );
t( 'Bảng vẫn cân', ! empty( $nb['canBang'] ), $nb );

/* Ký lại một báo cáo đã ra sổ KHÔNG được trừ lần nữa. */
$lai = VHJP_Duyet::ky( $KT, 'BC1', VHJP_BaoCao::PHAN_TIEN, '' );
t( 'Ký lại báo cáo đã hoàn tất: tồn KHÔNG bị trừ lần nữa',
	45 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
$again = VHJP_Kho::xuat_bao_cao( $KT, 'BC1' );
t( '🔴 Gọi xuất_bao_cao lần hai thì DỪNG, báo daCoTruoc', ! empty( $again['daCoTruoc'] ), $again );
t( 'Gọi lần hai không đẻ thêm dòng sổ nào',
	2 === count( VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BC1' ) ) );
t( 'Câu thông báo nói rõ là đã ghi từ trước, không phải "0 dòng"',
	false !== strpos( VHJP_Duyet::kho_msg( $again ), 'từ lượt duyệt trước' ),
	VHJP_Duyet::kho_msg( $again ) );

/* TRẢ VỀ một báo cáo đã hoàn tất thì phải HOÀN KHO. */
$tv = VHJP_Duyet::tra_ve( $KT, 'BC1', 'Sai số liệu' );
t( 'Trả về báo cáo đã hoàn tất thì hoàn kho', ! empty( $tv['kho']['daGo'] ), $tv['kho'] );
t( '🔴 Hoàn kho trả tồn về đúng 60', 60 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
t( 'Hoàn kho gỡ sạch dòng 632 của báo cáo ấy',
	! VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BC1' ) );
t( 'Hoàn kho KHÔNG đẻ lớp mới', 1 === count( VHJP_Kho::lop_con( 'CS01', 'H1' ) ),
	VHJP_Kho::lop_con( 'CS01', 'H1' ) );
t( 'Câu thông báo kể việc hoàn kho', false !== strpos( $tv['msg'], 'hoàn kho' ), $tv['msg'] );

/* Nộp lại rồi duyệt lần hai: trừ lại đúng một lần nữa, không nhân đôi. */
VHJP_Nguon::sua( 'JP_Reports', 'BC1', array( 'status' => VHJP_BaoCao::TT_CHO_DUYET ) );
$k3 = ky_du( $KT, 'BC1' );
t( 'Duyệt lại sau khi sửa: trừ đúng một lần', 45 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
t( 'Duyệt lại: giá vốn vẫn 16.600đ', 16600 === $k3['kho']['tongGiaVon'], $k3['kho'] );

/* Cơ sở CHƯA được chuyển hàng xuống ⇒ không có lớp để ăn. Vẫn ghi, giá vốn 0, và PHẢI cảnh báo. */
nen();
bc_moi( 'BC2', 'CS02', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 7 ) ) );
$k4 = ky_du( $KT, 'BC2' );
t( 'Cơ sở chưa có hàng: vẫn ghi dòng 632', 1 === $k4['kho']['soDong'], $k4['kho'] );
t( 'Cơ sở chưa có hàng: giá vốn 0', 0 === $k4['kho']['tongGiaVon'], $k4['kho'] );
t( '🔴 Và NÓI RA là thiếu lớp tồn, không im lặng',
	1 === count( $k4['kho']['thieuLop'] )
	&& false !== strpos( $k4['msg'], 'THIẾU LỚP TỒN' ), $k4 );

/* Báo cáo không có dòng hàng nào: nói rõ "không có gì để ghi", đừng để trống. */
nen();
bc_moi( 'BC3', 'CS01', '2026-09-15', array(
	array( 'rowKind' => 'COIN', 'itemCode' => '', 'soldQty' => 0 ) ) );
$k5 = ky_du( $KT, 'BC3' );
t( 'Báo cáo không có hàng bán: 0 dòng sổ', 0 === $k5['kho']['soDong'], $k5['kho'] );
t( 'Và nói rõ là không có gì để ghi',
	false !== strpos( $k5['msg'], 'không có gì để ghi' ), $k5['msg'] );

/* 🔴 LỖI CỦA SỔ KHO KHÔNG ĐƯỢC NUỐT MẤT CHỮ KÝ. */
nen();
bc_moi( 'BC4', '', '2026-09-15', array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 3 ) ) );
$k6 = ne( function () { global $KT; return ky_du( $KT, 'BC4' ); } );
t( '🔴 Sổ kho lỗi thì lượt ký KHÔNG ném lỗi ra ngoài', $k6['ok'], $k6 );
t( 'Báo cáo vẫn HOÀN TẤT', $k6['ok'] && ! empty( $k6['ra']['done'] ), $k6 );
t( 'Chữ ký ĐÃ vào sổ thật',
	VHJP_BaoCao::TT_HOAN_TAT === VHJP_Doc::str(
		VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC4' )['status'] ) );
t( 'Và lỗi kho được nói ra kèm việc phải làm',
	$k6['ok'] && ! empty( $k6['ra']['kho']['loi'] )
	&& false !== strpos( $k6['ra']['msg'], 'SỔ KHO CHƯA GHI ĐƯỢC' ), $k6 );

/* ------------------------------------------------------------------ in kết quả */
echo "\n";
if ( $TRUOT ) {
	echo "ĐỎ — " . count( $TRUOT ) . " phép trượt:\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ " . $x . "\n"; }
	echo "\nĐạt {$DAT} · trượt " . count( $TRUOT ) . "\n";
	exit( 1 );
}
echo "XANH — {$DAT} phép đều đạt.\n";
exit( 0 );
