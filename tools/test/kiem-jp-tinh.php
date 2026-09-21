<?php
/**
 * TÍNH TIỀN TỪ CHỈ SỐ MÁY — DÒNG MÁY TIỀN
 * =============================================================================================
 *
 * 🔴 MỌI CON SỐ DOANH THU CỦA JP RA ĐỜI Ở ĐÂY. Sai một ly là sai tiền của 13 cơ sở, và cái
 *    sai ấy KHÔNG làm sổ mất cân — nên không phép kiểm kế toán nào bắt được, chỉ tới lúc kiểm
 *    đếm kho cuối kỳ mới lòi ra, lúc đó không dựng lại được nữa.
 *
 * Bài này KHÔNG so PHP với đáp án chép tay. Nó chạy CHÍNH `JP2_04_TinhToan.gs` bằng node rồi
 * đòi PHP ra y hệt TỪNG TRƯỜNG, trên:
 *   · 14 dòng SỐ LIỆU THẬT — form AM BD ngày 31/07/2026, chính bộ mà bản gốc dùng làm
 *     `jpSelfTest_()`, kèm đáp số người ta đã đối chiếu bằng tay (tổng 2.400.000đ);
 *   · và một loạt ca biên nhắm đúng bốn chỗ KHÔNG ĐỐI XỨNG của hàm này.
 *
 * Chạy: php tools/test/kiem-jp-tinh.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

$plg = $goc . '/wordpress/vhcp-jp/includes/';
foreach ( array( 'db', 'doc', 'nguon', 'cau-hinh', 'tinh' ) as $f ) {
	require_once $plg . 'class-vhjp-' . $f . '.php';
}

$cau_noi = __DIR__ . '/jp-doc-goc.js';
exec( 'node --version 2>/dev/null', $r_, $ma_node );
t( '🔴 có node để chạy mã gốc', 0 === $ma_node );

function goc_chay( $ten, $ca ) {
	global $cau_noi;
	$ra = array(); $ma = 0;
	exec( 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) ) . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
}

/**
 * Đối chiếu một DÒNG đã tính: từng trường một.
 *
 * ⚠️ So từng trường chứ không so cả object bằng một phép: so cả object thì câu báo trượt chỉ
 *    nói "khác nhau", còn người đọc phải tự dò trong hai khối JSON 20 trường xem lệch ở đâu.
 */
function doi_chieu_dong( $nhan, $row ) {
	$g = goc_chay( 'dong_may_tien', array( array( $row ) ) );
	if ( isset( $g['loi'] ) ) { t( "🔴 chạy được mã gốc cho $nhan", false, $g['loi'] ); return null; }
	$mong = $g[0];
	$php  = VHJP_Tinh::dong_may_tien( $row );

	foreach ( array( 'mActual', 'amount', 'collection', 'cash', 'lechTM', 'price',
		'refundAmt', 'refundQty', 'soldQty', 'hLeft' ) as $c ) {
		t( "$nhan · $c", wp_json_encode( $php[ $c ] ) === wp_json_encode( $mong[ $c ] ),
			"gốc " . wp_json_encode( $mong[ $c ] ) . ' · PHP ' . wp_json_encode( $php[ $c ] ) );
	}
	/* Cảnh báo: so SỐ LƯỢNG và DANH SÁCH MÃ. Câu chữ dài và hay đổi, nhưng MÃ thì kế toán và
	   giao diện gọi theo — lệch mã là lệch thứ người ta đọc. */
	$ma_g = array_column( $mong['warns'], 'code' );
	$ma_p = array_column( $php['warns'], 'code' );
	t( "$nhan · đúng bộ mã cảnh báo", $ma_g === $ma_p,
		'gốc ' . implode( ',', $ma_g ) . ' · PHP ' . implode( ',', $ma_p ) );
	return $php;
}

// ============================================================ 1. 🔴 14 DÒNG SỐ LIỆU THẬT
/* Form AM BD ngày 31/07/2026. Cột: [mã, đồng hồ trước, đồng hồ sau, collection chờ, tồn đầu,
   trứng trước, trứng sau, số bán chờ, tồn còn chờ] — chép đúng từ `jpSelfTest_()` của bản gốc,
   kèm đáp số người ta đã đối chiếu bằng tay. */
$that = array(
	array( '100JP111', 1240, 1300, 30, 54,  48,  51,  3, 51 ),
	array( '150JP113',  730,  730,  0, 38,  22,  22,  0, 38 ),
	array( '150JP065', 2276, 2276,  0, 89,  92,  92,  0, 89 ),
	array( '100JP025', 2772, 2812, 20, 83,  87,  89,  2, 81 ),
	array( '100JP046', 1984, 2024, 20, 59,  99, 101,  2, 57 ),
	array( '100JP052', 1182, 1182,  0, 71,  59,  59,  0, 71 ),
	array( '100JP023', 3622, 3682, 30, 68, 191, 194,  3, 65 ),
	array( '100JP026', 2710, 2730, 10, 72,  89,  90,  1, 71 ),
	array( '100JP028', 2526, 2566, 20, 21, 118, 120,  2, 19 ),
	array( '150JP105', 1270, 1270,  0, 72,  42,  42,  0, 72 ),
	array( '200JP079', 2746, 2826, 40, 73,  29,  31,  2, 71 ),
	array( '100JP051', 1086, 1146, 30, 67,  25,  28,  3, 64 ),
	array( '100JP035', 2666, 2746, 40, 36,  65,  69,  4, 32 ),
	array( '100JP125', 1144, 1144,  0, 43,  37,  37,  0, 43 ),
);
$tong_col = 0;
foreach ( $that as $x ) {
	$row = array( 'rowKind' => 'MONEY', 'itemCode' => $x[0],
		'mBefore' => $x[1], 'mAfter' => $x[2],
		'hOpen' => $x[4], 'hBefore' => $x[5], 'hAfter' => $x[6] );
	$php = doi_chieu_dong( 'thật ' . $x[0], $row );
	if ( ! $php ) { continue; }
	$tong_col += $php['collection'];
	/* Và đối chiếu với ĐÁP SỐ NGƯỜI TA ĐÃ TÍNH TAY — không chỉ với mã gốc. Nếu cả hai bản cùng
	   sai một kiểu thì phép so hai bản không phát hiện được; con số tay là mốc thứ ba. */
	teq( 'thật ' . $x[0] . ' · collection khớp form giấy', $x[3], $php['collection'] );
	teq( 'thật ' . $x[0] . ' · số bán khớp form giấy',     $x[7], $php['soldQty'] );
	teq( 'thật ' . $x[0] . ' · tồn còn khớp form giấy',    $x[8], $php['hLeft'] );
}
teq( '🔴 TỔNG doanh thu 14 ô máy = 2.400.000đ (đúng con số form AM BD 31/07/2026)',
	2400000, $tong_col * 10000 );

// ============================================================ 2. Bốn chỗ KHÔNG đối xứng
/* ── Chỗ 1: THỪA tiền thì CỘNG trứng, HỤT tiền thì KHÔNG trừ ───────────────────────────── */
$thua = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001',
	'mBefore' => 0, 'mAfter' => 100, 'cashReal' => 600000 );   // máy báo 500k, đếm được 600k
$p = doi_chieu_dong( 'thừa tiền', $thua );
teq( '🔴 thừa 100.000đ -> bán 6 quả, không phải 5', 6, $p['soldQty'] );
teq( 'nhưng doanh thu VẪN là tiền đồng hồ (không cộng vào amount)', 500000, $p['amount'] );

$hut = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001',
	'mBefore' => 0, 'mAfter' => 100, 'cashReal' => 400000 );   // máy báo 500k, đếm được 400k
$p = doi_chieu_dong( 'hụt tiền', $hut );
teq( '🔴 hụt 100.000đ -> VẪN bán 5 quả, KHÔNG trừ bớt', 5, $p['soldQty'] );

/* ── Chỗ 2: chỉ nhánh suy-từ-tiền mới trừ hoàn khách ───────────────────────────────────── */
$hoan_tien = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001',
	'mBefore' => 0, 'mAfter' => 100, 'refundAmt' => 200000, 'refundRowNote' => 'khách trả lại' );
$p = doi_chieu_dong( 'hoàn · không đồng hồ trứng', $hoan_tien );
teq( '🔴 suy từ tiền thì TRỪ hoàn: 5 − 2 = 3', 3, $p['soldQty'] );

$hoan_dem = $hoan_tien;
$hoan_dem['hBefore'] = 10; $hoan_dem['hAfter'] = 15;
$p = doi_chieu_dong( 'hoàn · CÓ đồng hồ trứng', $hoan_dem );
teq( '🔴 có đồng hồ trứng thì KHÔNG trừ hoàn: vẫn 5', 5, $p['soldQty'] );

/* ── Chỗ 4: ô "Thực thu" TRỐNG là CHƯA ĐẾM, không phải đếm được 0 ──────────────────────── */
$trong = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001',
	'mBefore' => 0, 'mAfter' => 100, 'cashReal' => '' );
$p = doi_chieu_dong( 'thực thu TRỐNG', $trong );
teq( '🔴 ô trống -> lệch = 0, KHÔNG phải −500.000đ', 0, $p['lechTM'] );
teq( 'và số bán vẫn là 5', 5, $p['soldQty'] );
/* Còn đếm được ĐÚNG 0 thì là lệch thật. */
$so_khong = $trong; $so_khong['cashReal'] = 0;
$p = doi_chieu_dong( 'thực thu = 0', $so_khong );
teq( '🔴 đếm được 0 thì LÀ lệch −500.000đ', -500000, $p['lechTM'] );

// ============================================================ 3. Đồng hồ hỏng
$lui = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001', 'mBefore' => 500, 'mAfter' => 100 );
$p = doi_chieu_dong( 'đồng hồ chạy LÙI', $lui );
teq( '🔴 chạy lùi -> 0, KHÔNG ra số âm', 0, $p['mActual'] );
teq( 'và doanh thu 0đ, không âm', 0, $p['amount'] );

$chua = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001', 'mBefore' => 500, 'mAfter' => '' );
$p = doi_chieu_dong( 'chưa nhập chỉ số sau', $chua );
teq( 'chưa nhập -> 0', 0, $p['mActual'] );
t( 'và có nhắc W8', in_array( 'W8', array_column( $p['warns'], 'code' ), true ), $p['warns'] );

/* Cả hai ô đều trống: dòng chưa ai đụng tới -> KHÔNG nhắc, không thì mỗi báo cáo đẻ ra một
   loạt cảnh báo về mấy dòng trắng. */
$trang = array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001', 'mBefore' => '', 'mAfter' => '' );
$p = doi_chieu_dong( 'dòng trắng', $trang );
teq( '🔴 dòng chưa ai đụng -> KHÔNG cảnh báo gì', 0, count( $p['warns'] ) );

// ============================================================ 4. Giá một xung
doi_chieu_dong( 'máy 10.000đ/xung', array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001',
	'mBefore' => 0, 'mAfter' => 100, 'giaXung' => 10000 ) );
$p = VHJP_Tinh::dong_may_tien( array( 'rowKind' => 'MONEY', 'itemCode' => '100JP001',
	'mBefore' => 0, 'mAfter' => 100, 'giaXung' => 10000 ) );
teq( '🔴 collection suy từ amount, không phải xung/2', 100, $p['collection'] );
teq( 'và doanh thu gấp đôi', 1000000, $p['amount'] );
/* Giá lạ thì rơi về mặc định, không nhận bừa — nhận bừa là doanh thu cơ sở đó sai hệ số. */
teq( 'giá xung lạ rơi về 5.000đ', 5000, VHJP_Tinh::gia_xung( 7777 ) );
teq( 'giá xung trống cũng vậy',   5000, VHJP_Tinh::gia_xung( '' ) );
teq( 'nhưng 10.000đ thì nhận',   10000, VHJP_Tinh::gia_xung( 10000 ) );

// ============================================================ 5. Mã không ra giá (W14)
$khong_gia = array( 'rowKind' => 'MONEY', 'itemCode' => 'HANGLA',
	'mBefore' => 0, 'mAfter' => 100, 'hBefore' => 0, 'hAfter' => 5 );
$p = doi_chieu_dong( 'mã không ra giá', $khong_gia );
t( '🔴 có bán mà mã không ra giá -> W14', in_array( 'W14', array_column( $p['warns'], 'code' ), true ), $p['warns'] );
/* Mã MISA được ưu tiên trước mã hàng. */
teq( 'giá lấy theo mã MISA trước', 20000,
	VHJP_Tinh::gia_dong( array( 'itemMisa' => '20JP', 'itemCode' => '100JP001' ) ) );
teq( 'giá đã khai thì thắng cả hai', 7000,
	VHJP_Tinh::gia_dong( array( 'price' => 7000, 'itemMisa' => '20JP' ) ) );

// ============================================================ 6. Cảnh báo được phép gộp
$w = VHJP_Tinh::warn( 'PRICE_REMAINDER', 'x', 12345 );
teq( 'W9 mang cờ gộp',      'DU_TIEN', $w['gop'] );
teq( 'và mang số để cộng dồn', 12345,  $w['so'] );
$w2 = VHJP_Tinh::warn( 'PRICE_NO_RATE', 'x', 999 );
t( '🔴 W14 TUYỆT ĐỐI không được gộp (cảnh báo nặng nhất nhóm)', ! isset( $w2['gop'] ), $w2 );

// ============================================================ 7. MÁY XU — dòng tiền
/* Số liệu THẬT: bảng tiền AM TPHU 31/07/2026, bản gốc dùng làm `jpSelfTest_`.
   Cột: [xu trước, xu sau, tiền trước, tiền sau, tiền mặt, chuyển khoản, doanh thu chờ]. */
function doi_chieu_loai( $ten_ham, $nhan, $row, $cot ) {
	$g = goc_chay( $ten_ham, array( array( $row ) ) );
	if ( isset( $g['loi'] ) ) { t( "🔴 chạy được mã gốc cho $nhan", false, $g['loi'] ); return null; }
	$mong = $g[0];
	$php  = call_user_func( array( 'VHJP_Tinh', $ten_ham ), $row );
	foreach ( $cot as $c ) {
		t( "$nhan · $c", wp_json_encode( $php[ $c ] ) === wp_json_encode( $mong[ $c ] ),
			'gốc ' . wp_json_encode( $mong[ $c ] ) . ' · PHP ' . wp_json_encode( $php[ $c ] ) );
	}
	t( "$nhan · đúng bộ mã cảnh báo",
		array_column( $mong['warns'], 'code' ) === array_column( $php['warns'], 'code' ),
		'gốc ' . implode( ',', array_column( $mong['warns'], 'code' ) )
		. ' · PHP ' . implode( ',', array_column( $php['warns'], 'code' ) ) );
	return $php;
}
$cot_xu = array( 'cActual', 'mActual', 'amount', 'collection', 'soldQty', 'xuTong', 'stockLeftCalc' );
foreach ( array(
	array( 37973, 37979, 189865, 189895, 100000, 200000, 300000 ),
	array( 19128, 19142,  95640,  95710, 250000, 450000, 700000 ),
) as $i => $x ) {
	$p = doi_chieu_loai( 'dong_may_xu', 'xu vị trí ' . ( $i + 1 ), array( 'rowKind' => 'COIN',
		'cBefore' => $x[0], 'cAfter' => $x[1], 'mBefore' => $x[2], 'mAfter' => $x[3],
		'cash' => $x[4], 'bank' => $x[5] ), $cot_xu );
	teq( '🔴 xu vị trí ' . ( $i + 1 ) . ' · doanh thu khớp bảng tiền thật', $x[6], $p['amount'] );
	teq( 'và KHÔNG giữ hàng (dòng COIN chỉ mang tiền)', 0, $p['soldQty'] );
}

/* Hai đồng hồ trên cùng một máy nói khác nhau -> W3. Đó là lý do máy xu có hai đồng hồ. */
$p = doi_chieu_loai( 'dong_may_xu', 'hai đồng hồ lệch', array( 'rowKind' => 'COIN',
	'cBefore' => 0, 'cAfter' => 6, 'mBefore' => 0, 'mAfter' => 20,
	'cash' => 300000, 'bank' => 0 ), $cot_xu );
t( '🔴 hai đồng hồ lệch -> W3', in_array( 'W3', array_column( $p['warns'], 'code' ), true ), $p['warns'] );
/* Thu tiền không khớp đồng hồ -> W4. */
$p = doi_chieu_loai( 'dong_may_xu', 'thu lệch Pay Box', array( 'rowKind' => 'COIN',
	'cBefore' => 0, 'cAfter' => 6, 'mBefore' => 0, 'mAfter' => 30,
	'cash' => 100000, 'bank' => 0 ), $cot_xu );
t( '🔴 thu không khớp đồng hồ -> W4', in_array( 'W4', array_column( $p['warns'], 'code' ), true ), $p['warns'] );

// ============================================================ 8. MÁY XU — dòng hàng tồn
/* Số liệu THẬT: bảng hàng tồn AE-TPHU 27–31/07/2026.
   Cột: [giá xu, tồn đầu, bổ sung 1, bổ sung 2, xu từng ngày, số bán chờ, tồn còn chờ]. */
$cot_ton = array( 'xuTong', 'soldQty', 'stockLeftCalc', 'amount' );
foreach ( array(
	array( 2, 121,  0, 0, array( 10, 0, 2, 0, 0, 0, 0 ),  6, 115 ),
	array( 2,  41,  0, 0, array( 0, 0, 4, 0, 2, 0, 0 ),   3,  38 ),
	array( 2,  44, 34, 0, array( 10, 0, 4, 0, 0, 0, 0 ),  7,  71 ),
	array( 2,  49, 34, 0, array( 18, 0, 2, 0, 0, 0, 0 ), 10,  73 ),
) as $i => $k ) {
	$p = doi_chieu_loai( 'dong_ton_xu', 'tồn xu ' . $i, array( 'rowKind' => 'STOCK',
		'giaXu' => $k[0], 'stockOpen' => $k[1], 'addQty1' => $k[2], 'addQty2' => $k[3],
		'xuDaysJson' => wp_json_encode( $k[4] ) ), $cot_ton );
	teq( '🔴 tồn xu ' . $i . ' · số bán khớp bảng thật', $k[5], $p['soldQty'] );
	teq( 'tồn còn khớp bảng thật',                       $k[6], $p['stockLeftCalc'] );
}
/* Tổng xu không chia hết cho giá xu -> W12, và W12 ĐƯỢC PHÉP gộp. */
$p = doi_chieu_loai( 'dong_ton_xu', 'xu lẻ', array( 'rowKind' => 'STOCK', 'giaXu' => 2,
	'stockOpen' => 100, 'xuDaysJson' => '[7]' ), $cot_ton );
t( '🔴 xu lẻ -> W12', in_array( 'W12', array_column( $p['warns'], 'code' ), true ), $p['warns'] );
teq( 'bán 3 (làm tròn xuống)', 3, $p['soldQty'] );
/* Chuỗi JSON gãy KHÔNG được làm chết lượt tính — chỉ coi như chưa có ngày nào. */
$p = doi_chieu_loai( 'dong_ton_xu', 'JSON gãy', array( 'rowKind' => 'STOCK', 'giaXu' => 2,
	'stockOpen' => 50, 'xuDaysJson' => '[10, kh' ), $cot_ton );
teq( '🔴 JSON gãy -> coi như rỗng, không nổ', 0, $p['xuTong'] );

// ============================================================ 9. Hàng giữ NGOÀI máy
$cot_ngoai = array( 'stockLeftCalc', 'soldQty', 'amount', 'cash', 'bank', 'xuTong' );
$p = doi_chieu_loai( 'dong_kho_ngoai', 'kho ngoài', array( 'rowKind' => 'NGOAI',
	'stockOpen' => 100, 'addQty1' => 50, 'stockOut' => 30, 'returnQty' => 5 ), $cot_ngoai );
teq( 'tồn ngoài = 100 + 50 − 30 − 5', 115, $p['stockLeftCalc'] );
/* 🔴 Dòng NGOAI không sinh tiền. Một dòng ĐỔI LOẠI còn giữ số tiền cũ trong ô, và không ai
   xoá hộ — phải ép về 0 ngay tại đây, không phụ thuộc chỗ khác nhớ lọc. */
$p = doi_chieu_loai( 'dong_kho_ngoai', 'kho ngoài · còn dính tiền cũ', array( 'rowKind' => 'NGOAI',
	'stockOpen' => 10, 'cash' => 999000, 'bank' => 111000, 'amount' => 500000 ), $cot_ngoai );
teq( '🔴 tiền cũ còn dính trong ô -> ép về 0 (amount)', 0, $p['amount'] );
teq( 'và tiền mặt về 0',      0, $p['cash'] );
teq( 'và chuyển khoản về 0',  0, $p['bank'] );
teq( 'và KHÔNG sinh số bán',  0, $p['soldQty'] );

// ============================================================ 10. Mẫu TÁCH — dòng máy
/* 🔴 `may_go_tay` đo bằng `<= 0`, KHÔNG dùng "ô trống". Ca thật 18/08/2026: màn hình tạo ô máy
   mới với mBefore = 0 (số 0 THẬT), nên phép đo bằng "trống" trả false -> hệ đòi chỉ số sau của
   một ô KHÔNG CÒN trên bảng -> SBPQ không nộp được báo cáo nào và không có ô nào để điền. */
teq( '🔴 mBefore = 0 (số 0 thật) VẪN là gõ tay', true,
	VHJP_Tinh::may_go_tay( array( 'mBefore' => 0, 'mAfter' => '' ) ) );
teq( 'ô trống cũng là gõ tay', true,
	VHJP_Tinh::may_go_tay( array( 'mBefore' => '', 'mAfter' => '' ) ) );
teq( 'còn có đồng hồ thật thì KHÔNG phải gõ tay', false,
	VHJP_Tinh::may_go_tay( array( 'mBefore' => 500, 'mAfter' => '' ) ) );

$cot_may = array( 'mActual', 'amount', 'collection', 'cash', 'lechTM', 'price', 'soldQty', 'hLeft' );
$p = doi_chieu_loai( 'dong_may_tach', 'tách · gõ tay', array( 'rowKind' => 'MAY',
	'itemCode' => '100JP001', 'mBefore' => 0, 'mAfter' => '',
	'amount' => 500000, 'cash' => 300000, 'bank' => 200000 ), $cot_may );
teq( 'gõ tay thì GIỮ số nhân viên gõ', 500000, $p['amount'] );
teq( 'và collection vẫn ra đơn vị 10.000đ', 50, $p['collection'] );
teq( '🔴 dòng MÁY ở mẫu tách KHÔNG giữ hàng', 0, $p['soldQty'] );

/* App cộng không khớp -> W10. */
$p = doi_chieu_loai( 'dong_may_tach', 'tách · app cộng lệch', array( 'rowKind' => 'MAY',
	'itemCode' => '100JP001', 'mBefore' => 0, 'mAfter' => '',
	'amount' => 500000, 'cash' => 100000, 'bank' => 200000 ), $cot_may );
t( '🔴 app cộng không khớp -> W10', in_array( 'W10', array_column( $p['warns'], 'code' ), true ), $p['warns'] );

/* Ô CHƯA GÕ phải GIỮ TRỐNG — hoá thành 0 là mất chốt "trống là CHƯA KHAI". */
$p = doi_chieu_loai( 'dong_may_tach', 'tách · chưa gõ tiền', array( 'rowKind' => 'MAY',
	'itemCode' => '100JP001', 'mBefore' => 0, 'mAfter' => '', 'amount' => '', 'cash' => '' ), $cot_may );
teq( '🔴 ô chưa gõ GIỮ TRỐNG, không hoá thành 0', '', $p['amount'] );

/* Còn đồng hồ (báo cáo đời cũ) thì vẫn suy tiền từ nó, cùng giá xung với mẫu chung. */
$p = doi_chieu_loai( 'dong_may_tach', 'tách · còn đồng hồ', array( 'rowKind' => 'MAY',
	'itemCode' => '100JP001', 'mBefore' => 100, 'mAfter' => 200 ), $cot_may );
teq( 'còn đồng hồ thì suy từ đồng hồ', 500000, $p['amount'] );

// ============================================================ 11. Mẫu TÁCH — dòng hàng
$cot_hang = array( 'price', 'soldQty', 'stockLeftCalc', 'refundAmt', 'refundQty',
	'amount', 'cash', 'bank', 'hLeft' );
$p = doi_chieu_loai( 'dong_hang_tach', 'tách · hàng', array( 'rowKind' => 'HANG',
	'itemCode' => '100JP001', 'stockOpen' => 100, 'addQty1' => 20,
	'returnQty' => 5, 'defectQty' => 2, 'soldQty' => 30 ), $cot_hang );
teq( 'tồn cuối = 100 + 20 − 5 − 2 − 30', 83, $p['stockLeftCalc'] );
teq( '🔴 dòng HÀNG ở mẫu tách KHÔNG mang tiền', 0, $p['amount'] );

/* 🔴 Ô "Đã bán" TRỐNG phải GIỮ TRỐNG và có W8 — hoá thành 0 là thôi chặn nộp, mà chưa khai thì
   kỳ sau không có tồn đầu và kho không ra được giá vốn. */
$p = doi_chieu_loai( 'dong_hang_tach', 'tách · chưa khai đã bán', array( 'rowKind' => 'HANG',
	'itemCode' => '100JP001', 'stockOpen' => 100, 'soldQty' => '' ), $cot_hang );
teq( '🔴 chưa khai -> GIỮ TRỐNG, không hoá thành 0', '', $p['soldQty'] );
t( 'và có nhắc W8', in_array( 'W8', array_column( $p['warns'], 'code' ), true ), $p['warns'] );
/* Khai đúng số 0 thì KHÁC — đó là bán được 0, không phải chưa khai. */
$p = doi_chieu_loai( 'dong_hang_tach', 'tách · khai đúng 0', array( 'rowKind' => 'HANG',
	'itemCode' => '100JP001', 'stockOpen' => 100, 'soldQty' => 0 ), $cot_hang );
teq( '🔴 khai đúng số 0 thì GIỮ 0', 0, $p['soldQty'] );
t( 'và KHÔNG nhắc W8', ! in_array( 'W8', array_column( $p['warns'], 'code' ), true ), $p['warns'] );

/* 🔴 `soldQty` là số NHÂN VIÊN GÕ (quả thật đã ra khỏi máy) nên KHÔNG trừ hoàn lần nữa. */
$p = doi_chieu_loai( 'dong_hang_tach', 'tách · hàng có hoàn', array( 'rowKind' => 'HANG',
	'itemCode' => '100JP001', 'stockOpen' => 100, 'soldQty' => 30,
	'refundAmt' => 200000, 'refundRowNote' => 'khách trả' ), $cot_hang );
teq( '🔴 KHÔNG trừ hoàn khỏi số đã gõ', 30, $p['soldQty'] );
teq( 'nhưng vẫn ghi lại số quả hoàn để bảng tổng cân được', 2, $p['refundQty'] );

/* Bán nhiều hơn số có thể bán -> tồn cuối âm, phải báo. */
$p = doi_chieu_loai( 'dong_hang_tach', 'tách · bán quá tay', array( 'rowKind' => 'HANG',
	'itemCode' => '100JP001', 'stockOpen' => 10, 'soldQty' => 50 ), $cot_hang );
t( '🔴 tồn cuối âm -> W2', in_array( 'W2', array_column( $p['warns'], 'code' ), true ), $p['warns'] );

// ============================================================ 12. Bộ điều phối
teq( 'chưa khai loại · cơ sở máy xu -> COIN', 'COIN',
	VHJP_Tinh::dong( array( 'cBefore' => 0, 'cAfter' => 1 ), 'XU' )['rowKind'] );
teq( 'chưa khai loại · cơ sở máy tiền -> MONEY', 'MONEY',
	VHJP_Tinh::dong( array( 'mBefore' => 0, 'mAfter' => 1 ), 'TIEN' )['rowKind'] );
teq( 'không nói loại máy cũng ra MONEY', 'MONEY', VHJP_Tinh::dong( array() )['rowKind'] );
/* Khai loại rồi thì loại máy của cơ sở KHÔNG được ghi đè lên. */
teq( '🔴 đã khai loại thì giữ nguyên, dù cơ sở là máy xu', 'NGOAI',
	VHJP_Tinh::dong( array( 'rowKind' => 'NGOAI' ), 'XU' )['rowKind'] );
/* Loại lạ rơi về cách tính dòng máy tiền — y bản gốc, không nổ. */
teq( 'loại lạ rơi về cách tính máy tiền', 'LOAI_LA',
	VHJP_Tinh::dong( array( 'rowKind' => 'LOAI_LA', 'mBefore' => 0, 'mAfter' => 100 ) )['rowKind'] );

/* Và điều phối phải gọi ĐÚNG hàm: so với mã gốc trên cả sáu loại. */
foreach ( array( 'MONEY', 'COIN', 'STOCK', 'NGOAI', 'MAY', 'HANG' ) as $k ) {
	$row = array( 'rowKind' => $k, 'itemCode' => '100JP001', 'mBefore' => 0, 'mAfter' => 100,
		'cBefore' => 0, 'cAfter' => 6, 'stockOpen' => 50, 'soldQty' => 5, 'giaXu' => 2,
		'xuDaysJson' => '[4]', 'cash' => 100000 );
	$g = goc_chay( 'dong', array( array( $row, 'TIEN' ) ) );
	$php = VHJP_Tinh::dong( $row, 'TIEN' );
	foreach ( array( 'amount', 'soldQty', 'stockLeftCalc' ) as $c ) {
		$mg = isset( $g[0][ $c ] ) ? $g[0][ $c ] : null;
		$mp = isset( $php[ $c ] ) ? $php[ $c ] : null;
		t( "điều phối $k · $c", wp_json_encode( $mp ) === wp_json_encode( $mg ),
			'gốc ' . wp_json_encode( $mg ) . ' · PHP ' . wp_json_encode( $mp ) );
	}
}

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — sáu loại dòng khớp mã gốc, và khớp cả ba bộ số liệu thật.\n";
