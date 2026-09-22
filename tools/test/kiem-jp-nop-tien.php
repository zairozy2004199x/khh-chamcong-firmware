<?php
/**
 * KIỂM NỘP TIỀN & BẢNG BÁO CÁO CỦA JP CAPSULE.
 *
 * =============================================================================================
 * 🔴 HAI CỘT, VÀ CHỖ LỆCH GIỮA CHÚNG MỚI LÀ THÔNG TIN
 * =============================================================================================
 * Nhân viên tự khai đã nộp bao nhiêu; kế toán nói đã nhận bao nhiêu. Gộp một cột thì nhân viên
 * khai bao nhiêu là sổ ghi bấy nhiêu, và tiền thiếu chỉ lộ ra lúc kiểm quỹ cuối tháng.
 *
 * Bài này canh sáu chỗ dễ sai nhất:
 *   1. Nhận QUÁ số còn thiếu ⇒ công nợ âm, và số âm ấy đi thẳng vào sổ 131.
 *   2. Nhận tiền của báo cáo CHƯA hoàn tất ⇒ nhận theo một con số sắp đổi.
 *   3. Nhân viên sửa/xoá lần nộp SAU KHI kế toán đã xác nhận ⇒ đổi chính số họ vừa ký.
 *   4. Nhân viên đụng vào báo cáo của người khác.
 *   5. `lech` của bảng hàng phải là `null` khi CHƯA ĐẾM, không phải 0.
 *   6. Tồn đầu khoá theo "kỳ trước ĐÃ DUYỆT", không theo "có tìm thấy số".
 *
 * Chạy: php tools/test/kiem-jp-nop-tien.php
 */

require_once __DIR__ . '/wp-stub.php';

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); } }
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
	function wp_generate_password( $n = 12, $a = true, $b = false ) {
		$c = 'abcdefghijklmnopqrstuvwxyz0123456789'; $s = '';
		for ( $i = 0; $i < $n; $i++ ) { $s .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; }
		return $s;
	}
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

$KT  = array( 'id' => 'K1', 'hoTen' => 'Kế Toán', 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );
$NV1 = array( 'id' => 'U1', 'hoTen' => 'Nhân Viên A', 'role' => VHJP_Auth::VAI_NV,
	'locationIds' => array( 'CS01' ) );
$NV2 = array( 'id' => 'U2', 'hoTen' => 'Nhân Viên B', 'role' => VHJP_Auth::VAI_NV,
	'locationIds' => array( 'CS02' ) );

function nen() {
	vhjp_dung_bang();
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS01', 'name' => 'JP Bà Rịa', 'code' => 'BR' ) );
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS02', 'name' => 'JP Vũng Tàu', 'code' => 'VT' ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H1', 'misa' => 'M1', 'name' => 'Trứng', 'active' => 1 ) );
}
/** Một báo cáo. `$so` = [revMeter, adjMachine, refundCustomer]. */
function bc( $ma, $uid, $cs, $tu, $den, $so = array(), $tt = null, $dong_ds = array() ) {
	$rev  = isset( $so[0] ) ? $so[0] : 0;
	$adj  = isset( $so[1] ) ? $so[1] : 0;
	$hoan = isset( $so[2] ) ? $so[2] : 0;
	VHJP_Nguon::them( 'JP_Reports', array( 'id' => $ma, 'locationId' => $cs,
		'locationName' => 'CS ' . $cs, 'maKH' => 'KH' . $cs, 'machineType' => 'TIEN',
		'fromDate' => $tu, 'toDate' => $den, 'userId' => $uid,
		'userName' => 'U' === substr( $uid, 0, 1 ) ? 'Nhân Viên ' . substr( $uid, 1 ) : $uid,
		'revMeter' => $rev, 'adjMachine' => $adj, 'refundCustomer' => $hoan,
		'totalSubmit' => $rev + $adj - $hoan,
		'submittedAt' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
		'status' => null === $tt ? VHJP_BaoCao::TT_HOAN_TAT : $tt ) );
	$i = 0;
	foreach ( $dong_ds as $d ) {
		$i++;
		VHJP_Nguon::them( 'JP_Rows', array_merge(
			array( 'id' => $ma . '-R' . $i, 'reportId' => $ma, 'seq' => $i ), $d ) );
	}
}

/* ══════════════════════════════════════════════════ ① ĐƯỜNG NỘP TIỀN CHẠY ĐÚNG ═════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 20000, 30000 ) );
$ds = VHJP_NopTien::chua_nop( $NV1 );
t( 'Báo cáo chưa nộp hiện ra', 1 === count( $ds ), $ds );
t( 'Phải nộp = 500.000 + 20.000 − 30.000 = 490.000', 490000 === $ds[0]['phaiNop'], $ds[0] );
t( 'Chưa nộp thì còn thiếu đúng ngần ấy', 490000 === $ds[0]['conThieu'], $ds[0] );
t( 'Tình trạng CHUA_NOP', VHJP_NopTien::CHUA_NOP === $ds[0]['nvPayStatus'], $ds[0] );

$r = VHJP_NopTien::them( $NV1, array( 'reportId' => 'BC1', 'amount' => 200000,
	'payDate' => '2026-09-16', 'method' => 'TM', 'note' => 'nộp đợt 1' ) );
t( 'Ghi nộp một phần được', ! empty( $r['ok'] ), $r );
t( 'Còn thiếu 290.000', 290000 === $r['conThieu'], $r );
$ds = VHJP_NopTien::chua_nop( $NV1 );
t( 'Tình trạng chuyển sang NOP_MOT_PHAN',
	VHJP_NopTien::MOT_PHAN === $ds[0]['nvPayStatus'], $ds[0] );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( 'Đầu báo cáo được đồng bộ tình trạng',
	VHJP_NopTien::MOT_PHAN === VHJP_Doc::str( $bcx['nvPayStatus'] ), $bcx['nvPayStatus'] );

$r2 = VHJP_NopTien::them( $NV1, array( 'reportId' => 'BC1', 'amount' => 290000,
	'payDate' => '2026-09-18', 'method' => 'CK' ) );
t( 'Nộp nốt thì hết thiếu', 0 === $r2['conThieu'], $r2 );
t( 'Lần thứ hai được đánh dấu BỔ SUNG',
	1 === VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Payments', 'id', $r2['id'] )['isSupplement'] ) );
t( 'Lần đầu KHÔNG phải bổ sung',
	0 === VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Payments', 'id', $r['id'] )['isSupplement'] ) );
t( 'Nộp đủ rồi thì rơi khỏi danh sách chưa nộp', ! VHJP_NopTien::chua_nop( $NV1 ) );

$ls = VHJP_NopTien::lich_su( $NV1, 'BC1' );
t( 'Lịch sử có 2 lần, xếp theo ngày', 2 === count( $ls )
	&& '2026-09-16' === $ls[0]['payDate'], $ls );

/* ═══════════════════════════════════════════════════════ ② BỐN CHỐT CHẶN ═══════════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
$r = ne( function () { global $NV1; return VHJP_NopTien::them( $NV1,
	array( 'reportId' => 'BC1', 'amount' => 150000 ) ); } );
t( '🔴 Nhận QUÁ số còn thiếu thì CHỐI', ! $r['ok'], $r );
t( 'Câu chối nói rõ hậu quả (công nợ âm vào sổ 131)',
	! $r['ok'] && false !== strpos( $r['loi'], '131' ), $r );

bc( 'BC2', 'U1', 'CS01', '2026-09-16', '2026-09-30', array( 100000, 0, 0 ),
	VHJP_BaoCao::TT_CHO_DUYET );
$r = ne( function () { global $NV1; return VHJP_NopTien::them( $NV1,
	array( 'reportId' => 'BC2', 'amount' => 50000 ) ); } );
t( '🔴 Báo cáo CHƯA hoàn tất thì không nhận tiền', ! $r['ok'], $r );
t( 'Vì số phải nộp còn đổi được', ! $r['ok'] && false !== strpos( $r['loi'], 'còn đổi được' ), $r );

bc( 'BC3', 'U2', 'CS02', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
$r = ne( function () { global $NV1; return VHJP_NopTien::them( $NV1,
	array( 'reportId' => 'BC3', 'amount' => 1000 ) ); } );
t( '🔴 Không nộp hộ được báo cáo của người khác', ! $r['ok'], $r );
t( 'Và không xem được lịch sử của người khác',
	! ne( function () { global $NV1; return VHJP_NopTien::lich_su( $NV1, 'BC3' ); } )['ok'] );
t( 'Danh sách chưa nộp chỉ có báo cáo của CHÍNH mình',
	1 === count( VHJP_NopTien::chua_nop( $NV2 ) )
	&& 'BC3' === VHJP_NopTien::chua_nop( $NV2 )[0]['id'] );

$r = ne( function () { global $NV1; return VHJP_NopTien::them( $NV1,
	array( 'reportId' => 'BC1', 'amount' => 0 ) ); } );
t( 'Số tiền 0 thì chối', ! $r['ok'], $r );

/* ═════════════════════════════════════════ ③ KẾ TOÁN XÁC NHẬN RỒI THÌ KHOÁ ═════════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
$p = VHJP_NopTien::them( $NV1, array( 'reportId' => 'BC1', 'amount' => 60000,
	'payDate' => '2026-09-16' ) );
t( 'Chưa xác nhận thì sửa ngày được',
	! empty( VHJP_NopTien::sua_ngay( $NV1, $p['id'], '2026-09-17' )['ok'] ) );

$x = VHJP_NopTien::xac_nhan( $KT, 'BC1', 60000 );
t( 'Kế toán xác nhận được', ! empty( $x['ok'] ) && 60000 === $x['ktXacNhan'], $x );
$r = ne( function () use ( $p ) { global $NV1;
	return VHJP_NopTien::sua_ngay( $NV1, $p['id'], '2026-09-20' ); } );
t( '🔴 Xác nhận rồi thì nhân viên KHÔNG sửa ngày được nữa', ! $r['ok'], $r );
$r = ne( function () use ( $p ) { global $NV1; return VHJP_NopTien::xoa( $NV1, $p['id'] ); } );
t( '🔴 Và cũng không xoá được', ! $r['ok'], $r );
t( 'Chặn ở MÁY CHỦ chứ không chỉ ẩn nút — lần nộp vẫn còn nguyên',
	null !== VHJP_Nguon::tim_mot( 'JP_Payments', 'id', $p['id'] ) );
t( 'Nhưng KẾ TOÁN thì vẫn sửa được (họ là người ký)',
	! empty( VHJP_NopTien::sua_ngay( $KT, $p['id'], '2026-09-20' )['ok'] ) );

$r = ne( function () { global $KT; return VHJP_NopTien::xac_nhan( $KT, 'BC1', 999999 ); } );
t( '🔴 Xác nhận QUÁ số phải nộp thì chối', ! $r['ok'], $r );
$r = ne( function () { global $NV1; return VHJP_NopTien::xac_nhan( $NV1, 'BC1', 100 ); } );
t( 'Nhân viên KHÔNG tự xác nhận được', ! $r['ok'], $r );

/* Xác nhận ĐỦ thì cờ `paid` bật — đó là thứ `jpKiemTraButToan` đọc. */
VHJP_NopTien::xac_nhan( $KT, 'BC1', 100000 );
t( 'Xác nhận đủ thì cờ paid bật',
	1 === VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['paid'] ) );
VHJP_NopTien::xac_nhan( $KT, 'BC1', 60000 );
t( 'Hạ xuống một phần thì cờ paid tắt lại',
	0 === VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['paid'] ) );

/* ══════════════════════════════════════════════ ④ BẢNG NỘP TIỀN & CÔNG NỢ NV ═══════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
bc( 'BC2', 'U2', 'CS02', '2026-09-01', '2026-09-15', array( 200000, 0, 0 ) );
VHJP_NopTien::them( $NV1, array( 'reportId' => 'BC1', 'amount' => 100000, 'method' => 'TM' ) );
VHJP_NopTien::them( $NV2, array( 'reportId' => 'BC2', 'amount' => 80000, 'method' => 'CK' ) );
VHJP_NopTien::xac_nhan( $KT, 'BC1', 100000 );

$b = VHJP_NopTien::bang_nop( $KT, array() );
t( 'Bảng nộp tiền có 2 dòng', 2 === count( $b['rows'] ), $b['rows'] );
$d1 = null; $d2 = null;
foreach ( $b['rows'] as $x ) { if ( 'BC1' === $x['id'] ) { $d1 = $x; } if ( 'BC2' === $x['id'] ) { $d2 = $x; } }
t( 'BC1 khớp: NV báo 100k, KT xác nhận 100k, lệch 0',
	100000 === $d1['nvPaid'] && 100000 === $d1['ktPaid'] && 0 === $d1['lech'], $d1 );
t( '🔴 BC2 LỆCH: NV báo 80k mà KT chưa xác nhận đồng nào',
	80000 === $d2['nvPaid'] && 0 === $d2['ktPaid'] && 80000 === $d2['lech'], $d2 );
t( 'Cột cách nộp đọc được', 'Tiền mặt' === $d1['nvCachThu'], $d1 );
$bl = VHJP_NopTien::bang_nop( $KT, array( 'onlyLech' => true ) );
t( 'Lọc "chỉ dòng lệch" bỏ đúng dòng khớp',
	1 === count( $bl['rows'] ) && 'BC2' === $bl['rows'][0]['id'], $bl['rows'] );
t( 'Nhân viên KHÔNG mở được bảng nộp tiền của mọi người',
	! ne( function () { global $NV1; return VHJP_Cong::goi( 'jpKtPaymentBoard',
		array( 'x', array() ) ); } )['ra']['than']['ok'] );

$cn = VHJP_NopTien::cong_no_nv( $KT, array() );
t( 'Công nợ nhân viên: 2 người', 2 === count( $cn['rows'] ), $cn['rows'] );
$a = null; $bb = null;
foreach ( $cn['rows'] as $x ) {
	if ( 'Nhân Viên 1' === $x['userName'] ) { $a = $x; }
	if ( 'Nhân Viên 2' === $x['userName'] ) { $bb = $x; }
}
t( '🔴 Còn nợ tính theo số KẾ TOÁN XÁC NHẬN, không theo số tự khai',
	0 === $a['conNo'] && 200000 === $bb['conNo'], array( $a, $bb ) );
t( 'Lệch tự báo của người 2 = 80.000', 80000 === $bb['lechTuBao'], $bb );
t( 'Ai nợ nhiều nhất lên đầu', 200000 === $cn['rows'][0]['conNo'], $cn['rows'] );

/* ════════════════════════════════════════════════ ⑤ BẢNG DOANH THU & HÀNG HOÁ ══════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 500000, 10000, 20000 ) );
bc( 'BCN', 'U1', 'CS01', '2026-09-16', '2026-09-20', array( 999000, 0, 0 ), VHJP_BaoCao::TT_NHAP );
VHJP_NopTien::xac_nhan( $KT, 'BC1', 300000 );
$bd = VHJP_Bang::bang_doanh_thu( $KT, array() );
t( 'Bảng doanh thu bỏ báo cáo còn NHÁP', 1 === count( $bd['rows'] ), $bd['rows'] );
t( 'Còn lại = phải nộp − KT xác nhận', 190000 === $bd['rows'][0]['conLai'], $bd['rows'][0] );
t( 'Tổng cộng đúng', 500000 === $bd['sum']['revMeter'] && 300000 === $bd['sum']['paid'], $bd['sum'] );
t( 'Nhân viên KHÔNG mở được bảng doanh thu toàn hệ',
	! ne( function () { global $NV1; return VHJP_Bang::bang_doanh_thu( $NV1 ); } )['ok'] );

nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ), null, array(
	array( 'rowKind' => 'HANG', 'itemCode' => 'H1', 'itemMisa' => 'M1', 'price' => 50000,
		'stockOpen' => 10, 'soldQty' => 3, 'stockLeftCalc' => 7, 'stockActual' => 6 ),
	array( 'rowKind' => 'HANG', 'itemCode' => 'H1', 'itemMisa' => 'M1', 'price' => 50000,
		'stockOpen' => 5, 'soldQty' => 1, 'stockLeftCalc' => 4, 'stockActual' => '' ),
	array( 'rowKind' => 'COIN', 'machineCode' => 'X1' ) ) );
$bh = VHJP_Bang::bang_hang( $KT, array() );
t( 'Bảng hàng bỏ dòng COIN (dòng ấy không giữ hàng)', 2 === count( $bh['rows'] ), $bh['rows'] );
t( 'Đã đếm và lệch −1 thì ra −1', -1 === $bh['rows'][0]['lech'], $bh['rows'][0] );
t( '🔴 CHƯA ĐẾM thì lech là null, KHÔNG phải 0', null === $bh['rows'][1]['lech'], $bh['rows'][1] );
t( 'Tên loại dòng đọc được', 'Mã hàng (tách)' === $bh['rows'][0]['tenLoaiDong'], $bh['rows'][0] );

/* ═══════════════════════════════════════════════════ ⑥ TỒN ĐẦU & ĐỀ NGHỊ SỬA ═══════════ */
nen();
bc( 'BCT', 'U1', 'CS01', '2026-08-01', '2026-08-31', array( 100000, 0, 0 ), VHJP_BaoCao::TT_HOAN_TAT,
	array( array( 'rowKind' => 'HANG', 'itemCode' => 'H1', 'hAfter' => 0, 'stockLeftCalc' => 42 ) ) );
bc( 'BCN', 'U1', 'CS01', '2026-09-01', '2026-09-30', array( 0, 0, 0 ), VHJP_BaoCao::TT_NHAP,
	array( array( 'rowKind' => 'HANG', 'itemCode' => 'H1', 'stockOpen' => 0 ) ) );
$o = VHJP_Bang::ton_dau( $NV1, 'BCN', '', 'H1' );
t( 'Lấy được số cuối kỳ trước', ! empty( $o['found'] ) && 42 === $o['stockOpen'], $o );
t( '🔴 Kỳ trước ĐÃ DUYỆT ⇒ chot = true (giao diện khoá ô)', true === $o['chot'], $o );

VHJP_Nguon::sua( 'JP_Reports', 'BCT', array( 'status' => VHJP_BaoCao::TT_CHO_DUYET ) );
$o = VHJP_Bang::ton_dau( $NV1, 'BCN', '', 'H1' );
t( '🔴 Kỳ trước CHƯA duyệt ⇒ found vẫn true nhưng chot = FALSE',
	! empty( $o['found'] ) && false === $o['chot'], $o );

$r = ne( function () { global $NV1; return VHJP_Bang::gui_de_nghi( $NV1,
	array( 'reportId' => 'BCN', 'rowId' => 'BCN-R1', 'soMoi' => 50, 'lyDo' => '' ) ); } );
t( '🔴 Đề nghị KHÔNG có lý do thì chối', ! $r['ok'], $r );
$dn = VHJP_Bang::gui_de_nghi( $NV1, array( 'reportId' => 'BCN', 'rowId' => 'BCN-R1',
	'soMoi' => 50, 'lyDo' => 'đếm lại thấy 50' ) );
t( 'Gửi đề nghị được', ! empty( $dn['ok'] ), $dn );
$r = ne( function () { global $NV1; return VHJP_Bang::gui_de_nghi( $NV1,
	array( 'reportId' => 'BCN', 'rowId' => 'BCN-R1', 'soMoi' => 60, 'lyDo' => 'lần nữa' ) ); } );
t( 'Đang có đề nghị CHỜ cho dòng ấy thì không gửi thêm', ! $r['ok'], $r );

$ds = VHJP_Bang::ds_de_nghi( $KT, true );
t( 'Kế toán thấy 1 đề nghị đang chờ', 1 === $ds['soCho'] && 1 === count( $ds['rows'] ), $ds );
t( '🔴 Biên bản giữ CẢ số cũ lẫn số mới',
	0 === $ds['rows'][0]['soCu'] && 50 === $ds['rows'][0]['soMoi'], $ds['rows'][0] );

$r = ne( function () use ( $dn ) { global $KT; return VHJP_Bang::xu_ly_de_nghi( $KT,
	array( 'id' => $dn['id'], 'trangThai' => 'TU_CHOI', 'ghiChu' => '' ) ); } );
t( 'Từ chối mà không ghi lý do thì chối', ! $r['ok'], $r );

$xl = VHJP_Bang::xu_ly_de_nghi( $KT, array( 'id' => $dn['id'], 'trangThai' => 'DUYET' ) );
t( 'Duyệt được', ! empty( $xl['ok'] ), $xl );
t( '🔴 Duyệt thì SỐ MỚI ghi thẳng vào ô tồn đầu', 50 === VHJP_Doc::num(
	VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'BCN-R1' )['stockOpen'] ) );
$r = ne( function () use ( $dn ) { global $KT; return VHJP_Bang::xu_ly_de_nghi( $KT,
	array( 'id' => $dn['id'], 'trangThai' => 'DUYET' ) ); } );
t( 'Xử lý lần hai thì chối', ! $r['ok'], $r );
t( 'Nhân viên KHÔNG tự duyệt đề nghị của mình',
	! ne( function () { global $NV1; return VHJP_Bang::ds_de_nghi( $NV1 ); } )['ok'] );

/* ═══════════════════════════════════════════════════ ⑦ MỞ LẠI & ĐỔI KỲ ═════════════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ),
	VHJP_BaoCao::TT_CHO_DUYET );
$r = VHJP_Bang::mo_lai_24h( $NV1, 'BC1' );
t( 'Chưa ai ký thì tự mở lại được trong 24h', ! empty( $r['ok'] )
	&& VHJP_BaoCao::TT_NHAP === $r['status'], $r );

nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ),
	VHJP_BaoCao::TT_CHO_DUYET );
VHJP_Nguon::sua( 'JP_Reports', 'BC1', array( 'apprRevBy' => 'Kế Toán' ) );
$r = ne( function () { global $NV1; return VHJP_Bang::mo_lai_24h( $NV1, 'BC1' ); } );
t( '🔴 Kế toán đã ký MỘT phần thì nhân viên không tự mở lại được', ! $r['ok'], $r );

nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ),
	VHJP_BaoCao::TT_CHO_DUYET );
VHJP_Nguon::sua( 'JP_Reports', 'BC1',
	array( 'submittedAt' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 - 30 * 3600 ) ) );
$r = ne( function () { global $NV1; return VHJP_Bang::mo_lai_24h( $NV1, 'BC1' ); } );
t( 'Quá 24 giờ kể từ lúc NỘP thì chối', ! $r['ok'], $r );

nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
bc( 'BC2', 'U1', 'CS01', '2026-09-16', '2026-09-30', array( 50000, 0, 0 ) );
$r = ne( function () { global $KT; return VHJP_Bang::kt_mo_lai( $KT, 'BC1', '' ); } );
t( 'Kế toán mở lại mà không ghi lý do thì chối', ! $r['ok'], $r );
$r = VHJP_Bang::kt_mo_lai( $KT, 'BC1', 'sai số liệu' );
t( 'Kế toán mở lại được báo cáo hoàn tất', ! empty( $r['ok'] ), $r );
t( 'Hai chữ ký bị huỷ', '' === VHJP_Doc::str(
	VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['apprRevBy'] ) );
t( '🔴 Và ĐẾM báo cáo kỳ SAU đã chốt dựa trên số này', 1 === $r['warnLater'], $r );

/*
 * 🔴 MỞ LẠI PHẢI HOÀN KHO. Báo cáo hoàn tất đã trừ lớp tồn và ghi giá vốn vào 632; mở lại mà
 * không hoàn là sổ 632 còn dòng của một báo cáo đang sửa, VÀ lượt duyệt sau trừ thêm lần nữa —
 * kho âm dần mà không phiếu nào sai.
 */
nen();
VHJP_Kho::nhap( $KT, array( 'ngay' => '2026-09-01', 'nccTen' => 'NCC A',
	'rows' => array( array( 'itemCode' => 'H1', 'qty' => 100, 'unitCost' => 1000 ) ) ) );
VHJP_Kho::xuat( $KT, array( 'ngay' => '2026-09-02', 'loai' => 'XUAT_CS',
	'locationId' => 'CS01', 'rows' => array( array( 'itemCode' => 'H1', 'qty' => 50 ) ) ) );
bc( 'BCK', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ),
	VHJP_BaoCao::TT_CHO_DUYET,
	array( array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 10 ) ) );
VHJP_Duyet::ky( $KT, 'BCK', VHJP_BaoCao::PHAN_HANG, '' );
VHJP_Duyet::ky( $KT, 'BCK', VHJP_BaoCao::PHAN_TIEN, '' );
t( 'Nền: duyệt xong thì cơ sở còn 40', 40 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'],
	VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
t( 'Nền: sổ 632 có dòng của báo cáo ấy',
	1 === count( VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BCK' ) ) );

$r = VHJP_Bang::kt_mo_lai( $KT, 'BCK', 'sai số liệu' );
t( '🔴 Mở lại thì HOÀN KHO: tồn về đúng 50',
	50 === VHJP_Kho::ton_mot( 'CS01', 'H1' )['tonQty'], VHJP_Kho::ton_mot( 'CS01', 'H1' ) );
t( '🔴 Và gỡ sạch dòng 632 của báo cáo ấy',
	! VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', 'BCK' ) );
t( 'Kết quả hoàn kho được kể trong câu trả lời',
	isset( $r['kho']['daGo'] ) && 1 === $r['kho']['daGo'], $r['kho'] );
t( 'Và câu thông báo nói ra việc đã hoàn kho',
	false !== strpos( $r['msg'], 'hoàn kho' ), $r['msg'] );
t( 'Hoàn kho KHÔNG đẻ lớp mới', 1 === count( VHJP_Kho::lop_con( 'CS01', 'H1' ) ),
	VHJP_Kho::lop_con( 'CS01', 'H1' ) );

nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
bc( 'BC2', 'U1', 'CS01', '2026-09-16', '2026-09-30', array( 50000, 0, 0 ), VHJP_BaoCao::TT_NHAP );
$r = ne( function () { global $NV1; return VHJP_Bang::sua_ky( $NV1, 'BC1',
	'2026-09-02', '2026-09-16' ); } );
t( 'Báo cáo ĐÃ DUYỆT thì không đổi kỳ được', ! $r['ok'], $r );
$r = ne( function () { global $NV1; return VHJP_Bang::sua_ky( $NV1, 'BC2',
	'2026-09-10', '2026-09-30' ); } );
t( '🔴 Kỳ CHỒNG kỳ của cùng cơ sở thì chối (tiền tính hai lần)', ! $r['ok'], $r );
t( 'Câu chối gọi tên báo cáo bị chồng',
	! $r['ok'] && false !== strpos( $r['loi'], 'BC1' ), $r );
$r = VHJP_Bang::sua_ky( $NV1, 'BC2', '2026-09-16', '2026-09-25' );
t( 'Kỳ không chồng thì đổi được', ! empty( $r['ok'] ), $r );

/* ══════════════════════════════════════════════ ⑧ DOANH THU TỪNG NGÀY (MISA) ═══════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-05', '2026-09-05', array( 300000, 0, 0 ) );
bc( 'BC2', 'U1', 'CS01', '2026-09-12', '2026-09-12', array( 200000, 0, 0 ) );
bc( 'BC3', 'U2', 'CS02', '2026-09-01', '2026-09-07', array( 700000, 0, 0 ) );
$d = VHJP_Bang::doanh_thu_ngay( $KT, 9, 2026 );
t( 'Doanh thu ngày chạy được', ! empty( $d['ok'] ) && 30 === $d['soNgay'], $d );
t( 'Hai cơ sở', 2 === count( $d['khuVuc'][0]['donVi'] ), $d['khuVuc'][0]['donVi'] );
$br = null;
foreach ( $d['khuVuc'][0]['donVi'] as $x ) { if ( 'BR' === $x['unitId'] ) { $br = $x; } }
t( 'Mã đơn vị lấy MÃ cơ sở', null !== $br, $d['khuVuc'][0]['donVi'] );
t( 'Ngày 5 = 300.000 · ngày 12 = 200.000',
	300000 === $br['ngay'][4] && 200000 === $br['ngay'][11], $br['ngay'] );
t( 'Tuần 1 = 300k · tuần 2 = 200k', 300000 === $br['tuan'][0] && 200000 === $br['tuan'][1], $br['tuan'] );
t( 'Tổng tháng = 1.200.000', 1200000 === $d['tongVND'], $d );
t( '🔴 Báo cáo NHIỀU NGÀY được kể tên', 1 === count( $d['baoCaoNhieuNgay'] )
	&& 'BC3' === $d['baoCaoNhieuNgay'][0]['id'], $d['baoCaoNhieuNgay'] );
t( 'Và cảnh báo nói rõ tiền bị dồn vào ngày cuối',
	false !== strpos( implode( ' ', $d['canhBao'] ), 'dồn vào ngày cuối' ), $d['canhBao'] );
t( 'Chưa khai tỷ giá thì cũng kêu',
	false !== strpos( implode( ' ', $d['canhBao'] ), 'tỷ giá' ), $d['canhBao'] );

/* ═══════════════════════════════════════════════════════════════ ⑨ CỔNG ════════════════ */
nen();
bc( 'BC1', 'U1', 'CS01', '2026-09-01', '2026-09-15', array( 100000, 0, 0 ) );
$ra = VHJP_Cong::nt_thang( array( 'THE', 9, 2026 ), $NV1 );
t( 'Cổng: nt_thang đọc tháng/năm ở $args[1..2]', 9 === $ra['thang'] && 1 === count( $ra['reports'] ), $ra );
$ra = VHJP_Cong::bg_ton_dau( array( 'THE', 'BC1', '', 'H1' ), $NV1 );
t( 'Cổng: bg_ton_dau đọc reportId/machineId/itemCode ở $args[1..3]', ! empty( $ra['ok'] ), $ra );
$ra = VHJP_Cong::bg_dt_ngay( array( 'THE', 9, 2026 ), $KT );
t( 'Cổng: bg_dt_ngay', 9 === $ra['thang'], $ra );

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
