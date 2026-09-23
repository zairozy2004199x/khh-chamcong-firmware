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
/* 🔴 "K&H" KHÔNG DÙNG LÀM MỘT MẢNG NGANG HÀNG NỮA. Từ 1.138.0 nó là NHÀ MẸ và nhìn cả hệ, nên
   phép "K&H không thấy gian POSH" đỏ không phải vì lọc hỏng mà vì bài chọn nhầm cái tên được
   miễn trừ. Thay bằng POSH — hai mảng con ngang hàng, đúng thứ cần soi. Nhà mẹ có bài riêng:
   `kiem-don-vi-me-xem-ca.php`. */
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV Kỹ thuật KVC',  '2468', 'Nhân viên', '', '', '', 'Kỹ thuật', 'KVC',  '' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV Kỹ thuật POSH', '1234', 'Nhân viên', '', '', '', 'Kỹ thuật', 'POSH', '' ) );
/* Người nhà mẹ — để canh chiều MỞ ở khối 4. */
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV Kỹ thuật K&H',  '5678', 'Nhân viên', '', '', '', 'Kỹ thuật', 'K&H',  '' ) );
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

/* ═══ 2. NHÂN VIÊN POSH: THẤY GIAN POSH, KHÔNG THẤY K&H LẪN KVC ════════════════════════ */
vai( 'Nhân viên', 'NV Kỹ thuật POSH' );
foreach ( $MAN as $ten => $ham ) {
	$cs = cs_cua( $ham );
	t( "🔴 $ten · POSH thấy gian của mình", in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true ), $cs );
	t( "🔴 $ten · POSH KHÔNG thấy gian K&H", ! in_array( 'FUNZONE VŨNG TÀU', $cs, true ), $cs );
	t( "   $ten · POSH KHÔNG thấy gian KVC (đã tách)", ! in_array( 'KVC AEON TÂN PHÚ', $cs, true ), $cs );
}

/* ═══ 3. ⚠️ AI XEM CẢ THÌ PHẢI BÀY ĐỦ, KHÔNG PHẢI BÀY RỖNG ════════════════════════════
   Ba cái tên dưới không có trong bảng người dùng, nên nhà của họ rơi về mặc định K&H — tức
   nhà mẹ, tức xem cả. (Từ 12/09/2026 chính cái NHÀ ấy cho xem cả, không phải cái VAI: hằng
   `VAI_XEM_CA` đã bỏ. Phép này vẫn canh đúng thứ cần canh — `null` phải được hiểu là XEM CẢ
   chứ không phải "không có gì".) */
foreach ( array( 'Admin', 'Quản lý', 'Kế toán cá nhân' ) as $v ) {
	vai( $v, $v );
	teq( "   '$v' (nhà mặc định K&H) xem cả hệ (coso_xem_duoc trả null)", null, VHCP_DonVi::coso_xem_duoc() );
	foreach ( $MAN as $ten => $ham ) {
		$cs = cs_cua( $ham );
		t( "🔴 $ten · '$v' thấy ĐỦ cả ba bên, không bị bày rỗng",
			in_array( 'KVC AEON TÂN PHÚ', $cs, true )
			&& in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true )
			&& in_array( 'FUNZONE VŨNG TÀU', $cs, true ), $cs );
	}
}

/* ═══ 4. ĐƯỜNG GỠ KHI KỸ THUẬT LÀM CHO CẢ HAI BÊN: ĐỂ NHÀ LÀ NHÀ MẸ ════════════════════
   Trước 12/09/2026 đường gỡ là tích thêm vào ô "Xem đơn vị". Ô ấy đã bỏ (anh Thắng: *"Đơn vị
   với xem đơn vị là 1"*), nên nay chỉ còn một cách và cách ấy rõ hơn hẳn: cho người làm cả hệ
   nhà K&H. Phép dưới canh đúng chỗ đó — kèm chiều đóng để "nhà mẹ" không lặng lẽ biến thành
   "ai cũng xem cả". */
vai( 'Nhân viên', 'NV Kỹ thuật K&H' );
$cs = cs_cua( $MAN['Kỹ thuật (listDuAn)'] );
t( '🔴 người nhà mẹ K&H thấy gian của cả ba bên — kỹ thuật làm cho cả hệ vẫn chọn được',
	in_array( 'FUNZONE VŨNG TÀU', $cs, true )
	&& in_array( 'POSH MN CGV VINCOM LANDMARK', $cs, true )
	&& in_array( 'KVC AEON TÂN PHÚ', $cs, true ), $cs );
vai( 'Nhân viên', 'NV Kỹ thuật KVC' );
$cs = cs_cua( $MAN['Kỹ thuật (listDuAn)'] );
t( '🔴 còn người nhà KVC thì vẫn chỉ thấy KVC — nhà mẹ không nới cho người khác',
	! in_array( 'FUNZONE VŨNG TÀU', $cs, true ), $cs );

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( count( $TRUOT ) ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — ô chọn cơ sở lọc theo đơn vị ở cả ba màn\n";
exit( 0 );
