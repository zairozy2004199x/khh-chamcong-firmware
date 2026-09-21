<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LOẠI CHI PHÍ CHIA BA BẢNG THEO KHỐI — PHÍA KHO.
 *
 * Anh Thắng 21/09/2026: *"chỗ loại chi phí, chia ra 3 bảng của 3 khối, để tránh dùng chung"*,
 * và *"đơn vị nào sẽ dùng khối của đơn vị đó"*.
 *
 * =============================================================================================
 * 🔴 "TRÁNH DÙNG CHUNG" LÀ YÊU CẦU VỀ HẬU QUẢ, KHÔNG PHẢI VỀ CÁCH BÀY
 * =============================================================================================
 * Trước bản này một dòng "Chi phí khác" là MỘT bản ghi mà cả ba khối cùng trỏ vào: kế toán Văn
 * phòng sửa mã TK Nợ của nó là sổ Khu vui chơi đổi theo, không ai hay. Bày ba bảng mà vẫn chung
 * một bản ghi thì chẳng giải quyết được gì — nên bài này canh đúng chỗ ấy:
 *
 *   1. 🔴 HAI KHỐI ĐƯỢC PHÉP CÙNG CÓ MỘT CÁI TÊN, và cả hai dòng phải sống sót qua lượt ghi.
 *      Mọi chốt chống-trùng-tên cũ đều khoá theo MỖI TÊN; sót một chỗ là dòng của khối thứ hai
 *      lặng lẽ biến mất ngay lượt Lưu đầu tiên.
 *   2. 🔴 Ô KHỐI KHÔNG BAO GIỜ ĐƯỢC RỖNG. Rỗng = loại KHÔNG BẢNG NÀO CHỨA: nó rơi khỏi cả ba
 *      bảng, khỏi ô chọn lúc nhập đơn, khỏi mọi cột mã — trong khi tiền mang tên nó vẫn nằm
 *      trong sổ. Đúng cái bẫy `lap_khoi()` của bảng đơn, lần này ở danh mục.
 *
 * Chạy: php tools/test/kiem-loai-theo-khoi.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 1. SƠ ĐỒ CỘT ════════════════════════════════════════════════════════════════ */
$hd = VHCP_Cfg::headers( VHCP_Cfg::LOAI );
teq( 'danh mục loại chi phí có 10 cột', 10, count( $hd ) );
teq( '   cột 9 là Đơn vị', 'Đơn vị', $hd[8] );
teq( '🔴 cột 10 là Khối', 'Khối', $hd[9] );
/* 🔴 `read()` đệm theo `count(headers())`. Khai thiếu là dòng cũ chỉ được đệm tới 8 ô và
   `$r[9]` không tồn tại — mọi chỗ đọc khối phải rào `isset()`, và quên rào một chỗ là cảnh
   báo PHP ở MỌI lượt nạp cấu hình. */
$COT_KHOI = 9;

/* ═══ 2. GHI RỒI ĐỌC LẠI — HAI KHỐI CÙNG TÊN ĐỀU SỐNG ════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Chi phí khác', '6428', '', '', '', '', '', '', '', 'kvc' ),
	array( 'Chi phí khác', '6417', '', '', '', '', '', '', '', 'mtd' ),
	array( 'Văn phòng phẩm', '6427', '', '', '', '', '', '', '', 'vp' ),
), false );
$rows = VHCP_Cfg::read( VHCP_Cfg::LOAI );
teq( '🔴 ba dòng còn đủ — trùng tên khác khối KHÔNG bị gộp', 3, count( $rows ) );
$theo = array();
foreach ( $rows as $r ) { $theo[ $r[ $COT_KHOI ] ][] = $r[0] . '=' . $r[1]; }
teq( '   KVC giữ mã của KVC', array( 'Chi phí khác=6428' ), $theo['kvc'] );
teq( '🔴 MTĐ giữ mã RIÊNG của MTĐ, không bị KVC đè', array( 'Chi phí khác=6417' ), $theo['mtd'] );
teq( '   VP giữ dòng của mình', array( 'Văn phòng phẩm=6427' ), $theo['vp'] );

/* ═══ 3. BOOT MANG THEO KHỐI ═════════════════════════════════════════════════════ */
VHCP_Cfg::clear_cache();
$cfg = VHCP_Cfg::cfg_static();
$ds  = $cfg['loaiChiPhi'];
teq( 'cấu hình tĩnh trả đủ 3 loại', 3, count( $ds ) );
t( '🔴 mỗi loại mang theo ô `khoi`', ! array_filter( $ds, function ( $x ) { return ! isset( $x['khoi'] ); } ), $ds[0] );
teq( '   và đúng khối của nó', array( 'kvc', 'mtd', 'vp' ),
	array_values( array_map( function ( $x ) { return $x['khoi']; }, $ds ) ) );

/* ═══ 4. 🔴 LẤP KHỐI CHO DÒNG CŨ — KHÔNG ĐỂ SÓT MỘT Ô RỖNG NÀO ═══════════════════
 * Dòng khai trước bản này chỉ có 8–9 ô. `seed()` phải quét lại và lấp, MỖI LƯỢT chứ không
 * phải một lần: một dòng thêm qua đường nạp dữ liệu, hay một lượt khôi phục bảng cũ, là lại
 * có ô rỗng — mà dấu "đã gieo" thì đã đóng. */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Loại cũ không khối', '6421', '', '', '', '', '', '' ),   // đúng 8 ô, như dòng đời đầu
	array( 'Loại đã có khối', '6422', '', '', '', '', '', '', '', 'vp' ),
), false );
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
$rows = VHCP_Cfg::read( VHCP_Cfg::LOAI );
$map  = array();
foreach ( $rows as $r ) { $map[ $r[0] ] = isset( $r[ $COT_KHOI ] ) ? $r[ $COT_KHOI ] : '(thiếu ô)'; }
teq( '🔴 dòng cũ được lấp khối của bản đang chạy', VHCP_DB::khoi(), $map['Loại cũ không khối'] );
teq( '🔴 dòng đã có khối KHÔNG bị ghi đè', 'vp', $map['Loại đã có khối'] );
/* Chạy lần hai: đã lấp rồi thì không được đổi gì nữa (phép này bắt lượt lấp ghi đè mù). */
VHCP_Cfg::seed();
$rows2 = VHCP_Cfg::read( VHCP_Cfg::LOAI );
$map2  = array();
foreach ( $rows2 as $r ) { $map2[ $r[0] ] = $r[ $COT_KHOI ]; }
teq( '   chạy lại lượt lấp không đổi gì', $map, $map2 );

/* ═══ 5. 🔴 CỬA LƯU KHÔNG ĐƯỢC GHI Ô KHỐI RỖNG ═══════════════════════════════════
 * Giao diện luôn gửi khối lên (mỗi bảng một khối), nhưng cửa này còn nhận lượt nạp từ tệp và
 * lượt gọi thẳng API — hai đường không có bảng nào để suy ra khối. */
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Không khai khối', 'tkNo' => '6423' ),
	array( 'ten' => 'Khai khối VP', 'tkNo' => '6424', 'khoi' => 'vp' ),
	array( 'ten' => 'Khai khối rỗng', 'tkNo' => '6425', 'khoi' => '   ' ),
) ) );
$rows = VHCP_Cfg::read( VHCP_Cfg::LOAI );
$map  = array();
foreach ( $rows as $r ) { $map[ $r[0] ] = $r[ $COT_KHOI ]; }
teq( '🔴 thiếu ô khối → lấp bằng khối bản đang chạy', VHCP_DB::khoi(), $map['Không khai khối'] );
teq( '🔴 ô khối toàn khoảng trắng cũng được lấp', VHCP_DB::khoi(), $map['Khai khối rỗng'] );
teq( '   khai rõ thì giữ nguyên', 'vp', $map['Khai khối VP'] );
t( '🔴 KHÔNG còn ô khối nào rỗng sau lượt lưu',
	! array_filter( $map, function ( $v ) { return '' === trim( (string) $v ); } ), $map );

/* ═══ 6. LƯU HAI DÒNG TRÙNG TÊN KHÁC KHỐI QUA CỬA API ════════════════════════════ */
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Chi phí khác', 'tkNo' => '6428', 'khoi' => 'kvc' ),
	array( 'ten' => 'Chi phí khác', 'tkNo' => '6417', 'khoi' => 'mtd' ),
) ) );
VHCP_Cfg::clear_cache();
$ds = VHCP_Cfg::cfg_static()['loaiChiPhi'];
teq( '🔴 cửa lưu giữ CẢ HAI dòng trùng tên khác khối', 2, count( $ds ) );
$ma = array();
foreach ( $ds as $x ) { $ma[ $x['khoi'] ] = $x['tkNo']; }
teq( '   và mã của mỗi khối không lẫn sang nhau', array( 'kvc' => '6428', 'mtd' => '6417' ), $ma );

/* ═══ 7. MÃ KHỐI DÙNG Ở ĐÂY PHẢI LÀ MÃ THẬT ══════════════════════════════════════
 * Gõ 'vanphong' thay vì 'vp' thì loại ấy thuộc một khối không tồn tại — không bảng nào chứa. */
$hop = array_keys( VHCP_DonVi::KHOI_THEO_DON_VI );
t( '🔴 ba mã khối của giao diện khớp `VHCP_DonVi`', array( 'kvc', 'mtd', 'vp' ) === $hop, $hop );
$app = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
if ( preg_match( "/var KHOI_DS=\[(.*?)\];/u", $app, $m ) ) {
	preg_match_all( "/ma:'([a-z]+)'/u", $m[1], $m2 );
	teq( '   và khớp `KHOI_DS` trên giao diện', $hop, $m2[1] );
} else {
	t( 'đọc được `KHOI_DS` trên giao diện', false );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: loại chi phí thuộc đúng một khối, hai khối trùng tên không đè nhau, và không ô khối nào rỗng.\n";
