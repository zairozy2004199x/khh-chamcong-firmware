<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô CHỌN CƠ SỞ KHÔNG ĐƯỢC BÀY GIAN CỦA BÊN KIA.
 *
 * Anh Thắng 11/09/2026: *"Thêm đơn vị KVC để tách ra được không. Vì để bên K&H vẫn thấy bên
 * Posh"*, kèm ảnh ô "Gian / cơ sở" của đơn Kỹ thuật xổ ra cả "POSH MN CGV VINCOM LANDMARK".
 *
 * =============================================================================================
 * 🔴 TÁCH ĐƠN VỊ HỎNG NGAY TẠI Ô NGƯỜI TA GÕ HẰNG NGÀY. `VHCP_DonVi` dựng cả một lớp tách
 *    (nhà của người, tầm nhìn của vai, đơn vị của từng cơ sở) — nhưng ba màn nhập lấy THẲNG
 *    toàn bộ danh mục cơ sở, nên mọi lớp ấy vô nghĩa ở đúng chỗ quan trọng nhất: chọn nhầm
 *    một gian của bên kia là dòng chi rơi sang sổ của họ.
 *
 * 🔴 BA MÀN, KHÔNG PHẢI MỘT. Kỹ thuật · Marketing · Công tác/Setup cùng một khuôn, cùng một
 *    hại. Vá mỗi chỗ anh Thắng chỉ vào là hai chỗ kia vẫn hở.
 *
 * ⚠️ `coso_xem_duoc()` trả `null` = XEM CẢ (Admin · Quản lý · Kế toán). Lúc ấy phải bày ĐỦ,
 *    không phải bày rỗng — hiểu nhầm `null` thành "không có gì" là ô chọn trống trơn với
 *    chính những người phải soát cả hệ.
 *
 * Chạy: php tools/test/kiem-o-gian-theo-don-vi.php
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

VHCP_Cfg::seed();
/* Ba đơn vị, mỗi bên một gian. Cột: ten | maDonVi | phanLoaiLon | tenMisa | dongCua | donVi */
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'KVC AEON TÂN PHÚ', 'KVCATP', 'KVC MN', 'KVC Aeon Tan Phu', '', 'KVC' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'POSH MN CGV VINCOM LANDMARK', 'POSHVCL', 'POSH MN', 'POSH VCL', '', 'POSH' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'FUNZONE VŨNG TÀU', 'FZVT', 'FZ MN', 'Funzone Vung Tau', '', '' ) );   // trống = K&H
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV Kỹ thuật KVC', '2468', 'Nhân viên', '', '', '', 'Kỹ thuật', 'KVC', 'KVC' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV Kỹ thuật K&H', '1234', 'Nhân viên', '', '', '', 'Kỹ thuật', 'K&H', 'K&H' ) );
VHCP_Cfg::clear_cache();

/** Danh sách cơ sở mà một màn trả về cho người đang đăng nhập. */
function cs_cua( $ham ) {
	$r = call_user_func( $ham );
	return (array) ( isset( $r['coso'] ) ? $r['coso'] : array() );
}
function vai( $v, $n ) { VHCP_Auth::dat_vai_tro( $v, $n ); }

$MAN = array(
	'Kỹ thuật (listDuAn)'       => array( 'VHCP_DuAn', 'list_du_an' ),
	'Marketing (listMkDon)'     => array( 'VHCP_MK', 'list_don' ),
	'Công tác/Setup (listBP)'   => array( 'VHCP_BP', 'list_bp' ),
);

/* ═══ 1. NHÂN VIÊN KVC: CHỈ THẤY GIAN KVC ══════════════════════════════════════════════ */
vai( 'Nhân viên', 'NV Kỹ thuật KVC' );
foreach ( $MAN as $ten => $ham ) {
	$cs = cs_cua( $ham );
	t( "🔴 $ten · KVC thấy gian KVC", in_array( 'KVC AEON TÂN PHÚ', $cs, true ), $cs );
	t( "🔴 $ten · KVC KHÔNG thấy gian POSH — đây là cái anh Thắng chỉ vào",
		! in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true ), $cs );
	t( "   $ten · KVC KHÔNG thấy gian K&H", ! in_array( 'FUNZONE VŨNG TÀU', $cs, true ), $cs );
}

/* ═══ 2. NHÂN VIÊN K&H: THẤY GIAN K&H, KHÔNG THẤY POSH LẪN KVC ═════════════════════════ */
vai( 'Nhân viên', 'NV Kỹ thuật K&H' );
foreach ( $MAN as $ten => $ham ) {
	$cs = cs_cua( $ham );
	t( "🔴 $ten · K&H thấy gian của mình", in_array( 'FUNZONE VŨNG TÀU', $cs, true ), $cs );
	t( "🔴 $ten · K&H KHÔNG thấy gian POSH", ! in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true ), $cs );
	t( "   $ten · K&H KHÔNG thấy gian KVC (đã tách)", ! in_array( 'KVC AEON TÂN PHÚ', $cs, true ), $cs );
}

/* ═══ 3. ⚠️ VAI XEM CẢ THÌ PHẢI BÀY ĐỦ, KHÔNG PHẢI BÀY RỖNG ═══════════════════════════ */
foreach ( array( 'Admin', 'Quản lý', 'Kế toán cá nhân' ) as $v ) {
	vai( $v, $v );
	teq( "   vai '$v' xem cả hệ (coso_xem_duoc trả null)", null, VHCP_DonVi::coso_xem_duoc() );
	foreach ( $MAN as $ten => $ham ) {
		$cs = cs_cua( $ham );
		t( "🔴 $ten · '$v' thấy ĐỦ cả ba bên, không bị bày rỗng",
			in_array( 'KVC AEON TÂN PHÚ', $cs, true )
			&& in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true )
			&& in_array( 'FUNZONE VŨNG TÀU', $cs, true ), $cs );
	}
}

/* ═══ 4. TÍCH THÊM "XEM ĐƠN VỊ" THÌ THẤY THÊM — ĐƯỜNG GỠ KHI KỸ THUẬT LÀM CHO CẢ HAI BÊN ═══ */
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV KT hai bên', '9999', 'Nhân viên', '', '', '', 'Kỹ thuật', 'K&H', 'K&H, POSH' ) );
VHCP_Cfg::clear_cache();
vai( 'Nhân viên', 'NV KT hai bên' );
$cs = cs_cua( $MAN['Kỹ thuật (listDuAn)'] );
t( '🔴 tích "Xem đơn vị" cả hai thì thấy gian của cả hai — người kỹ thuật làm cho cả hai bên vẫn chọn được',
	in_array( 'FUNZONE VŨNG TÀU', $cs, true ) && in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true ), $cs );
t( '   nhưng vẫn KHÔNG thấy KVC (không tích)', ! in_array( 'KVC AEON TÂN PHÚ', $cs, true ), $cs );

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( count( $TRUOT ) ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — ô chọn cơ sở lọc theo đơn vị ở cả ba màn\n";
exit( 0 );
