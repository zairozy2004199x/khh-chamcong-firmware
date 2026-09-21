<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LOẠI "Chi phí cơ sở" PHẢI CHỌN ĐƯỢC Ở ĐƠN CỦA BỘ PHẬN KỸ THUẬT.
 *
 * Anh Thắng 11/09/2026: *"bổ sung loại chi phí ( chi phí cơ sở )"* — ô Loại chi phí trong đơn
 * Kỹ thuật chỉ xổ ra đúng hai dòng tháo dỡ / setup.
 *
 * =============================================================================================
 * Kỹ thuật nay lên được ĐƠN CHI PHÍ CƠ SỞ (một đơn nhiều gian), mà loại chi phí đúng cho nó lại
 * đang khai riêng cho bộ phận khác — nên ô chọn của họ không có dòng nào dùng được, và không có
 * gì trên màn nói vì sao.
 *
 * 🔴 CỘNG THÊM BỘ PHẬN, KHÔNG THAY. Ghi đè cột Bộ phận thành "Kỹ thuật" là cắt loại này khỏi
 *    chính những người đang dùng nó hằng tuần.
 *
 * 🔴 LOẠI CHƯA KHAI BỘ PHẬN NÀO THÌ ĐỂ YÊN. Bỏ trống nghĩa là DÙNG CHUNG cho mọi bộ phận; điền
 *    "Kỹ thuật" vào là BÓ nó lại, đúng ngược điều đang cần.
 *
 * 🔴 SEED CHỈ MỘT LẦN. Chạy lại mỗi lượt nâng cấp là dựng lại thứ anh Thắng vừa cố ý bỏ đi.
 *
 * ⚠️ CHẠY THẬT `VHCP_Cfg::seed()` trên CSDL giả.
 *
 * Chạy: php tools/test/kiem-loai-coso-ky-thuat.php
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

/** Cột Bộ phận của một loại chi phí, đọc thẳng từ bảng cấu hình. */
function bp_cua( $ten ) {
	foreach ( VHCP_Cfg::read( VHCP_Cfg::LOAI ) as $r ) {
		$r = array_values( (array) $r );
		if ( mb_strtolower( trim( (string) $r[0] ) ) === mb_strtolower( $ten ) ) {
			return isset( $r[4] ) ? (string) $r[4] : '';
		}
	}
	return null;
}

/** Chỉ số dòng của một loại chi phí trong bảng cấu hình. */
function dong_cua( $ten ) {
	foreach ( VHCP_Cfg::read( VHCP_Cfg::LOAI ) as $i => $r ) {
		$r = array_values( (array) $r );
		if ( mb_strtolower( trim( (string) $r[0] ) ) === mb_strtolower( $ten ) ) { return $i; }
	}
	return -1;
}

/* ═══ 1. LOẠI ĐÃ KHAI BỘ PHẬN KHÁC -> CỘNG THÊM KỸ THUẬT ════════════════════════════════
 * Dựng đúng hình dạng sổ thật: "Chi phí cơ sở" đã có sẵn trong danh mục và đã khai bộ phận
 * riêng cho khối cơ sở. `seed()` chạy một lượt lúc boot rồi, nên xoá vết ghim để nó chạy lại. */
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();
$_i = dong_cua( 'Chi phí cơ sở' );
t( 'danh mục gốc có sẵn loại "Chi phí cơ sở"', $_i >= 0, $_i );
VHCP_Cfg::set_cell( VHCP_Cfg::LOAI, $_i, 4, 'Cơ sở' );
/* 🔴 DỰNG MỘT LOẠI TÊN GẦN GIỐNG ĐỨNG TRƯỚC. Sổ thật có "Chi phí tháo dỡ", "Chi phí setup…"
   nằm trên; so tên lỏng (kiểu `stripos($ten,'Chi phí')`) là vớ đúng dòng đầu tiên rồi dừng,
   và "Chi phí cơ sở" không bao giờ được mở — mà mọi phép khác vẫn xanh. */
VHCP_Cfg::set_cell( VHCP_Cfg::LOAI, 0, 0, 'Chi phí tháo dỡ' );
VHCP_Cfg::set_cell( VHCP_Cfg::LOAI, 0, 4, 'Kỹ thuật' );
VHCP_Meta::del( 'seeded_coso_kythuat_v1' );
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();

$bp = bp_cua( 'Chi phí cơ sở' );
t( 'loại "Chi phí cơ sở" vẫn còn trong danh mục', null !== $bp, $bp );
$ds = VHCP_Cfg::bo_phan_tach( (string) $bp );
t( '🔴 nay thuộc bộ phận Kỹ thuật', in_array( 'Kỹ thuật', $ds, true ), $ds );
t( '🔴 và KHÔNG mất bộ phận cũ — ghi đè là cắt loại này khỏi người đang dùng nó hằng tuần',
	in_array( 'Cơ sở', $ds, true ), $ds );
teq( '   nhân viên Kỹ thuật chọn được loại này', true, VHCP_Cfg::loai_thuoc_bo_phan( 'Chi phí cơ sở', 'Kỹ thuật' ) );
teq( '   nhân viên Cơ sở vẫn chọn được', true, VHCP_Cfg::loai_thuoc_bo_phan( 'Chi phí cơ sở', 'Cơ sở' ) );
teq( '   bộ phận khác thì không (chốt bộ phận vẫn còn hiệu lực)', false,
	VHCP_Cfg::loai_thuoc_bo_phan( 'Chi phí cơ sở', 'Marketing' ) );
teq( '🔴 loại tên gần giống đứng trước KHÔNG bị đụng tới', 'Kỹ thuật', bp_cua( 'Chi phí tháo dỡ' ) );

/* ═══ 2. 🔴 CHẠY LẠI KHÔNG ĐỔI GÌ NỮA ═══════════════════════════════════════════════════ */
VHCP_Cfg::set_cell( VHCP_Cfg::LOAI, $_i, 4, 'Cơ sở' );   // người dùng cố ý bỏ Kỹ thuật ra
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();
teq( '🔴 seed chạy lại KHÔNG dựng lại thứ người dùng vừa cố ý bỏ', 'Cơ sở', bp_cua( 'Chi phí cơ sở' ) );

/* ═══ 3. 🔴 LOẠI CHƯA KHAI BỘ PHẬN THÌ ĐỂ YÊN ═══════════════════════════════════════════ */
VHCP_Meta::del( 'seeded_coso_kythuat_v1' );
VHCP_Cfg::set_cell( VHCP_Cfg::LOAI, $_i, 4, '' );   // loại dùng chung cho MỌI bộ phận
VHCP_Cfg::clear_cache();
VHCP_Cfg::seed();
VHCP_Cfg::clear_cache();
teq( '🔴 cột Bộ phận để trống = dùng chung -> KHÔNG điền "Kỹ thuật" vào (điền là BÓ nó lại)',
	'', bp_cua( 'Chi phí cơ sở' ) );
teq( '   và mọi bộ phận vẫn chọn được', true, VHCP_Cfg::loai_thuoc_bo_phan( 'Chi phí cơ sở', 'Marketing' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( count( $TRUOT ) ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — loại \"Chi phí cơ sở\" mở thêm cho bộ phận Kỹ thuật\n";
exit( 0 );
