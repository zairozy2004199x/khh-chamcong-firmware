<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LỌC MÃ TÀI KHOẢN ĂN CẢ CÂY CON — chọn 641 thì cộng luôn 6411 · 6412 · 6413.
 *
 * Anh Thắng 12/09/2026: *"Sau này gộp chi phí như 6416, 6415 thành 641 tính ra được chi phí 641
 * từ chi phí nào nó dễ"*, rồi nói rõ cơ chế: *"cần tìm mã 641 bao nhiêu, thì hệ thống sẽ cộng
 * 6411, 6412, 6413. Cơ chế nó vậy"*.
 *
 * =============================================================================================
 * 🔴 KHÔNG DỰNG BẢNG CHA–CON. Hệ tài khoản Việt Nam đã mã hoá quan hệ ấy vào chính con số:
 *    641 › 6415 › 64151. "Thuộc cây 641" chỉ là "bắt đầu bằng 641". Một bảng khai cha–con là
 *    thêm một nơi phải khai đúng, và khai lệch thì tiền cộng sai mà không ai nhìn ra.
 *
 * 🔴 MÃ CHA KHÔNG CÓ DÒNG NÀO GHI NÓ. Trong sổ chỉ có 6411/6412; "641" phải được SUY RA thì
 *    người dùng mới có cái để chọn trong ô xổ. Đó là việc của `tk_cha_ds()`.
 *
 * 🔴 CHỖ DỄ SAI NHẤT LÀ ĐUÔI ".0". Bảng tính trả "141.0" cho mã 141. So thẳng chuỗi thì
 *    "141.0" vẫn bắt đầu bằng "141" nên có vẻ đúng — nhưng "1410" (một tài khoản khác) cũng
 *    bắt đầu bằng "141", mà "141.0" thì KHÔNG được coi là con của "1410". Phải dọn trước khi
 *    so, và phần dưới canh đúng chỗ đó.
 *
 * Chạy: php tools/test/kiem-loc-ma-tk-theo-cay.php
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

/* ═══ 1. QUAN HỆ CHA–CON ═══════════════════════════════════════════════════════════ */
t( '🔴 6411 thuộc cây 641', VHCP_Cfg::tk_thuoc_cay( '6411', '641' ) );
t( '🔴 6412 thuộc cây 641', VHCP_Cfg::tk_thuoc_cay( '6412', '641' ) );
t( '🔴 6415 thuộc cây 641', VHCP_Cfg::tk_thuoc_cay( '6415', '641' ) );
t( '   64151 (cấp 3) cũng thuộc cây 641', VHCP_Cfg::tk_thuoc_cay( '64151', '641' ) );
t( '   và thuộc cả cây 6415',             VHCP_Cfg::tk_thuoc_cay( '64151', '6415' ) );
t( '🔴 chính nó thuộc chính nó',          VHCP_Cfg::tk_thuoc_cay( '641', '641' ) );
t( '🔴 642 KHÔNG thuộc cây 641',        ! VHCP_Cfg::tk_thuoc_cay( '642', '641' ) );
t( '🔴 6411 KHÔNG thuộc cây 6412',      ! VHCP_Cfg::tk_thuoc_cay( '6411', '6412' ) );
/* Chiều ngược: cha KHÔNG thuộc cây con. Nhầm chiều là chọn 6411 mà cộng luôn cả 641. */
t( '🔴 641 KHÔNG thuộc cây 6411 (đúng chiều, không đảo)', ! VHCP_Cfg::tk_thuoc_cay( '641', '6411' ) );

/* ═══ 2. ĐUÔI ".0" CỦA BẢNG TÍNH ═══════════════════════════════════════════════════ */
t( '🔴 "141.0" vẫn là mã 141, thuộc cây 141', VHCP_Cfg::tk_thuoc_cay( '141.0', '141' ) );
t( '   và lọc "141.0" cũng ăn dòng 1411',     VHCP_Cfg::tk_thuoc_cay( '1411', '141.0' ) );
/* 🔴 Chỗ dễ sai nhất: nếu KHÔNG dọn đuôi, chuỗi "141.0" bắt đầu bằng "141" nên có vẻ ổn — mà
   "1410" cũng bắt đầu bằng "141". Dọn rồi thì "141" và "1410" là hai mã khác hẳn. */
t( '🔴 "141.0" KHÔNG thuộc cây 1410',       ! VHCP_Cfg::tk_thuoc_cay( '141.0', '1410' ) );
t( '   1410 thì có thuộc cây 141',            VHCP_Cfg::tk_thuoc_cay( '1410', '141' ) );

/* ═══ 3. Ô TRỐNG KHÔNG ĐƯỢC KHỚP BỪA ══════════════════════════════════════════════
 * `strpos( $a, '' )` trả 0 trong PHP, tức "mọi mã đều bắt đầu bằng chuỗi rỗng" — để lọt là ô
 * lọc để trống biến thành khớp tất cả, và dòng KHÔNG có mã TK cũng lọt vào mọi nhóm. */
t( '🔴 mã rỗng không thuộc cây nào',      ! VHCP_Cfg::tk_thuoc_cay( '', '641' ) );
t( '🔴 lọc bằng chuỗi rỗng không ăn gì',  ! VHCP_Cfg::tk_thuoc_cay( '6411', '' ) );
t( '   cả hai rỗng cũng không',           ! VHCP_Cfg::tk_thuoc_cay( '', '' ) );

/* ═══ 4. SUY RA MÃ CHA CHO Ô XỔ ═══════════════════════════════════════════════════ */
teq( '🔴 từ 6411 + 6412 suy ra cha 641', array( '641' ), VHCP_Cfg::tk_cha_ds( array( '6411', '6412' ) ) );
teq( '   cấp 3 sinh ra cả hai tầng cha', array( '641', '6415' ), VHCP_Cfg::tk_cha_ds( array( '64151' ) ) );
/* ⚠️ KHÔNG cắt ngắn hơn 3 chữ số: hệ Việt Nam không có tài khoản "64" hay "6", bịa ra là ô xổ
   bày những mã không tồn tại. */
t( '🔴 KHÔNG sinh ra "64" hay "6"',
	! in_array( '64', VHCP_Cfg::tk_cha_ds( array( '6411' ) ), true )
	&& ! in_array( '6', VHCP_Cfg::tk_cha_ds( array( '6411' ) ), true ),
	VHCP_Cfg::tk_cha_ds( array( '6411' ) ) );
teq( 'mã 3 chữ số không sinh cha nào',   array(), VHCP_Cfg::tk_cha_ds( array( '641' ) ) );
/* Không trả lại mã ĐÃ CÓ trong rổ — nơi gọi cần phân biệt mã thật với mã suy ra. */
teq( '🔴 641 đã có sẵn thì không trả lại', array(), VHCP_Cfg::tk_cha_ds( array( '641', '6411' ) ) );
teq( 'mã có CHỮ thì bỏ qua, không phải cây số', array(), VHCP_Cfg::tk_cha_ds( array( 'NV001', 'TK-A' ) ) );
teq( 'rổ rỗng thì không có cha nào',     array(), VHCP_Cfg::tk_cha_ds( array() ) );

/* ═══ 5. CHẠY THẬT TRÊN SỔ CHI PHÍ ════════════════════════════════════════════════ */
VHCP_Cfg::seed();
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'TÀU TẤN PHÚ', 'TTP', 'KVC MN', '', '', '' ) );
VHCP_Cfg::clear_cache();
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp', '' );

global $wpdb;
$t_sc = VHCP_DB::t( 'so_chi' );
$them = function ( $id, $tk, $tien ) use ( $wpdb, $t_sc ) {
	$wpdb->insert( $t_sc, array( 'id' => $id, 'ky' => 'T9', 'coso' => 'TÀU TẤN PHÚ',
		'loai' => 'Chi phí cơ sở', 'tk_no' => $tk, 'so_tien' => $tien ) );
};
$them( 'C1', '6411', 1000000 );
$them( 'C2', '6412', 2000000 );
$them( 'C3', '6415',  500000 );
$them( 'C4', '642',   9000000 );   // cây khác, không được cộng vào

function tong_tk( $ma ) {
	$r = VHCP_SoChi::list_chi( array( 'tkNo' => $ma ) );
	return (float) $r['tong'];
}
teq( '🔴 lọc 641 cộng cả 6411 + 6412 + 6415', 3500000.0, tong_tk( '641' ) );
teq( '   lọc 6411 chỉ ra chính nó',           1000000.0, tong_tk( '6411' ) );
teq( '🔴 và KHÔNG dính 642 của cây khác',     9000000.0, tong_tk( '642' ) );
teq( 'không lọc thì ra tổng tất cả',         12500000.0, (float) VHCP_SoChi::list_chi( array() )['tong'] );

/* Ô xổ phải bày được "641" — trong sổ không dòng nào ghi nó. */
$r  = VHCP_SoChi::list_chi( array() );
$ds = (array) $r['tkNoList'];
t( '🔴 ô lọc bày cả mã cha 641 (sổ không có dòng nào ghi 641)', in_array( '641', $ds, true ), $ds );
t( '   và vẫn bày các mã thật',
	in_array( '6411', $ds, true ) && in_array( '6412', $ds, true ), $ds );
t( '🔴 nói rõ 641 là mã SUY RA, để màn ghi chú "gồm cả cây con"',
	in_array( '641', (array) $r['tkChaList'], true ), $r['tkChaList'] );
t( '   còn 6411 thì không nằm trong danh sách ấy',
	! in_array( '6411', (array) $r['tkChaList'], true ), $r['tkChaList'] );

/* ⚠️ 642 đứng một mình nên KHÔNG sinh cha — cắt nó thành "64" là bịa ra một mã không có thật. */
t( '🔴 không bịa ra mã "64" trong ô xổ', ! in_array( '64', $ds, true ), $ds );

/* ─────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo '❌ TRƯỢT ' . count( $TRUOT ) . ' / ' . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — lọc mã TK ăn cả cây con, mã cha suy ra cho ô xổ\n";
