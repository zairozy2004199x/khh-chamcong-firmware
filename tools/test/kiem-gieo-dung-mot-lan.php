<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HẠT GIỐNG DANH MỤC GIEO ĐÚNG MỘT LẦN — KỂ CẢ TRÊN SITE ĐÃ CÀI TỪ TRƯỚC.
 *
 * Anh Thắng 14/09/2026, sau khi đã nhận bản vá hôm trước: *"Tiếp tục không xóa đưuọcd"* — kèm ảnh
 * /chi-phi-vp vẫn nguyên 14 gian hàng khu vui chơi.
 *
 * =============================================================================================
 * 🔴 BẢN VÁ TRƯỚC CHỈ CỨU ĐƯỢC MÁY CÀI MỚI TINH.
 *
 * Nó đặt dấu "đã gieo rồi" Ở TRONG thân nhánh gieo:
 *
 *       if ( bảng rỗng && chưa có dấu ) { gieo…; đặt dấu; }
 *
 * Trên site đã cài từ trước, bảng KHÔNG rỗng -> nhánh không chạy -> dấu KHÔNG BAO GIỜ ĐƯỢC ĐẶT.
 * Rồi người dùng dọn sạch bảng: rỗng ✓, chưa có dấu ✓ -> gieo lại nguyên danh mục. Đúng cảnh
 * anh Thắng gặp, hai ngày liền, trên đúng những bản đang đau.
 *
 * Trớ trêu: lượt dọn thứ HAI mới ăn, vì chính lần hoàn tác ấy có đặt dấu. Người dùng phải xoá
 * hai lần mới thắng — không ai đoán ra luật đó, nên nó hiện ra y như "xoá không được".
 *
 * ⚠️ NAY: thấy bảng CÓ DỮ LIỆU là đóng dấu ngay, không đợi tới lượt gieo.
 *
 * 🔴 BÀI NÀY CANH ĐÚNG CẢNH ẤY: bảng đầy (như site thật) -> dọn sạch -> phải Ở LẠI SẠCH ngay
 *    lượt đầu. Mất nó thì mỗi lần ai sửa lại khối gieo là anh Thắng lại ngồi xoá hai lần.
 *
 * Chạy: php tools/test/kiem-gieo-dung-mot-lan.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$GOC = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $GOC . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

function dem( $bang ) {
	$n = 0;
	foreach ( VHCP_Cfg::read( $bang ) as $r ) { if ( trim( (string) $r[0] ) !== '' ) { $n++; } }
	return $n;
}
/** Giả một lượt tải trang: xoá cache rồi đọc cấu hình (đây là nơi seed chạy). */
function tai_trang() { VHCP_Cfg::clear_cache(); VHCP_Cfg::cfg_static(); }

$BANG = array(
	'Cơ sở'          => array( VHCP_Cfg::COSO, 'seeded_coso_v1' ),
	'Nhóm mặt hàng'  => array( VHCP_Cfg::NHOM, 'seeded_nhom_v1' ),
	'Loại chi phí'   => array( VHCP_Cfg::LOAI, 'seeded_loai_v1' ),
);

/* ═══ 1. SITE CÀI MỚI: gieo đúng một lần ═══════════════════════════════════════════ */
foreach ( $BANG as $ten => $x ) { VHCP_Cfg::write( $x[0], array() ); VHCP_Meta::set( $x[1], '' ); }
tai_trang();
foreach ( $BANG as $ten => $x ) {
	t( 'cài mới: ' . $ten . ' được gieo mồi', dem( $x[0] ) > 0, dem( $x[0] ) );
	t( 'cài mới: ' . $ten . ' đóng dấu «' . $x[1] . '»', (bool) VHCP_Meta::get( $x[1] ), '' );
}

/* ═══ 2. DỌN SẠCH -> Ở LẠI SẠCH NGAY LƯỢT ĐẦU ═════════════════════════════════════ */
foreach ( $BANG as $ten => $x ) { VHCP_Cfg::write( $x[0], array() ); }
tai_trang();
foreach ( $BANG as $ten => $x ) {
	teq( '🔴 dọn sạch ' . $ten . ' rồi tải lại -> vẫn sạch', 0, dem( $x[0] ) );
}

/* ═══ 3. 🔴 SITE ĐÃ CÀI TỪ TRƯỚC — ĐÚNG CẢNH ANH THẮNG GẶP ════════════════════════
 *
 * Bảng ĐẦY như site thật, nhưng dấu CHƯA CÓ (vì bản cũ chỉ đặt dấu lúc gieo). Đây là chỗ bản vá
 * trước thủng: lượt dọn sạch đầu tiên bị hoàn tác.
 * ════════════════════════════════════════════════════════════════════════════════════ */
foreach ( $BANG as $ten => $x ) {
	VHCP_Cfg::write( $x[0], array( array( 'DÒNG CŨ CỦA SITE', '', '', '', '', '' ) ) );
	VHCP_Meta::set( $x[1], '' );            // dấu chưa từng được đặt — y như site nâng cấp lên
}
tai_trang();
foreach ( $BANG as $ten => $x ) {
	teq( 'site cũ: ' . $ten . ' không bị gieo đè', 1, dem( $x[0] ) );
	t( '🔴 site cũ: ' . $ten . ' được đóng dấu NGAY dù không gieo', (bool) VHCP_Meta::get( $x[1] ), '' );
}

// … rồi người dùng dọn sạch. Đây là lượt dọn ĐẦU TIÊN, và nó phải ăn.
foreach ( $BANG as $ten => $x ) { VHCP_Cfg::write( $x[0], array() ); }
tai_trang();
foreach ( $BANG as $ten => $x ) {
	teq( '🔴🔴 site cũ: dọn sạch ' . $ten . ' LƯỢT ĐẦU đã ăn', 0, dem( $x[0] ) );
}
// và tải thêm vài lượt nữa vẫn sạch (không có nhánh nào âm thầm dựng lại)
tai_trang(); tai_trang();
foreach ( $BANG as $ten => $x ) {
	teq( 'tải thêm hai lượt nữa, ' . $ten . ' vẫn sạch', 0, dem( $x[0] ) );
}

/* ═══ 4. BẢNG NGƯỜI DÙNG CỐ Ý ĐỨNG NGOÀI LUẬT NÀY ═════════════════════════════════
 *
 * 🔴 Xoá sạch người dùng là tự khoá mình ngoài cửa VĨNH VIỄN — không PIN nào vào được nữa, và
 *    không có màn nào để sửa vì muốn vào màn ấy thì phải đăng nhập. Bảng này PHẢI gieo lại.
 * ════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::USER, array() );
tai_trang();
t( '🔴 xoá sạch người dùng thì vẫn gieo lại (không tự khoá ngoài cửa)', dem( VHCP_Cfg::USER ) > 0, dem( VHCP_Cfg::USER ) );

/* ═══ 5. CANH MÃ NGUỒN: mọi nhánh gieo đi qua đúng MỘT cửa ════════════════════════ */
$cfg_ma = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
foreach ( array( 'COSO' => 'seeded_coso_v1', 'NHOM' => 'seeded_nhom_v1', 'LOAI' => 'seeded_loai_v1' ) as $b => $dau ) {
	t( '🔴 ' . $b . ' gieo qua gieo_mot_lan(«' . $dau . '»)',
		(bool) preg_match( '#gieo_mot_lan\( \$all, self::' . $b . ", '" . $dau . "'#", $cfg_ma ), '' );
}
t( '🔴 gieo_mot_lan() đóng dấu TRƯỚC khi hỏi bảng có rỗng không',
	(bool) preg_match( '#VHCP_Meta::set\( \$dau, .1. \);\s*//[^\n]*\n\s*if \( count\( self::rows_of#', $cfg_ma ), '' );

/* ═══ 6. BẢN MẢNG RIÊNG KHÔNG ĐẺ DANH MỤC KHU VUI CHƠI ════════════════════════════
 *
 * Anh Thắng: *"rõ ràng các trang chi phí là không dùng dữ liệu của nhau, nhỉ là đẩy sang chi phí
 * tổng thôi"* — đúng, bảng tách hoàn toàn. 14 gian hàng khu vui chơi hiện bên /chi-phi-vp không
 * kéo từ bản kia sang: chúng là hạt giống gõ cứng, chép sang bản nào thì bản ấy tự đẻ y hệt.
 * ════════════════════════════════════════════════════════════════════════════════════ */
foreach ( array( 'mtd', 'vp' ) as $ma ) {
	$d = $GOC . '/wordpress/vhcp-chi-phi-' . $ma;
	if ( ! is_dir( $d ) ) { continue; }
	$m = file_get_contents( $d . '/includes/class-vhcp-cfg.php' );
	foreach ( array( 'default_coso', 'default_nhom' ) as $h ) {
		t( '🔴 bản «' . $ma . '»: ' . $h . '() trả danh mục TRẮNG',
			(bool) preg_match( '#function ' . $h . '\(\) \{\s*\n\s*return array\(\);#', $m ), '' );
	}
	t( 'bản «' . $ma . '»: không còn tên gian hàng khu vui chơi trong hạt giống',
		false === strpos( $m, 'FUNZONE ADVENTURE' ), '' );
}
// …còn bản gốc khu vui chơi thì GIỮ NGUYÊN hạt giống của nó.
t( '🔴 bản gốc vẫn giữ 14 gian hàng của mình', false !== strpos( $cfg_ma, 'FUNZONE ADVENTURE' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ ĐẠT: $DAT phép thử — hạt giống gieo đúng một lần, dọn sạch là ở lại sạch.\n";
