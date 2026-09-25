<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * TÁCH BẢNG THEO ĐẦU MỤC — máy chủ tính trục tách cho màn.
 *
 * Anh Thắng 25/09/2026, nhìn bảng Xuất MISA tách theo MẢNG cơ sở ("EVENT FZ MN · 43 dòng"), kèm ảnh
 * bốn đầu mục Chi Phí Vận Hành · Chi Phí Cơ Sở KVC · Chi Phí Cơ Sở MTD · Chi Phí Khác:
 *   *"đã bảo tách 2 bảng riêng biệt, còn bảng POSH MN thuộc loại chi phí. 4 chi phí, 4 bảng riêng
 *    biệt cho anh"*.
 * → Phân loại lớn anh nói là ĐẦU MỤC của loại chi phí (cột `dauMuc` ở danh mục Loại), không phải
 *   mảng (`phanLoaiLon`) của cơ sở. Máy chủ tra từ loại trên từng dòng:
 *   · `VHCP_Misa::export_misa()` → `rowDauMuc[i]` song song `rows[i]`, `dauMucThu` = thứ tự bày.
 *   · `VHCP_Don::list_dons()`   → `dauMuc` của từng đơn (bảng Đã quyết toán tách theo nó).
 *
 * Chạy: php tools/test/kiem-tach-bang-theo-dau-muc.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-25 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

$KY = 'T9/2026 (21/9-27/9/2026)';
/* Hai cơ sở CÙNG mảng "EVENT FZ MN" — nếu còn tách theo mảng thì chỉ ra MỘT bảng; theo đầu mục phải ra nhiều. */
VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'ADV GO AN LAC',  'maDonVi' => 'ADVGAL', 'phanLoaiLon' => 'EVENT FZ MN', 'tenMisa' => 'ADV GO AN LAC' ),
		array( 'ten' => 'FUNZONE AN LAC', 'maDonVi' => 'FZAL',   'phanLoaiLon' => 'EVENT FZ MN', 'tenMisa' => 'FUNZONE AN LAC' ),
	),
	'dauMucDs' => array( 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở MTD', 'Chi Phí Khác' ),
	'loaiChiPhi' => array(
		array( 'ten' => 'Chi phí cơ sở',     'tkNo' => '64196', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'khoi' => 'kvc', 'dauMuc' => 'Chi Phí Cơ Sở KVC' ),
		array( 'ten' => 'Chi phí marketing', 'tkNo' => '64191', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'khoi' => 'kvc', 'dauMuc' => 'Chi Phí Vận Hành' ),
		array( 'ten' => 'Chi phí chưa xếp',  'tkNo' => '64199', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'khoi' => 'kvc', 'dauMuc' => '' ),
	),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Kế Toán A', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'tkCo' => '3341', 'maDt' => 'NV_A' ),
	),
) );

/* ── 0. Tra đầu mục của loại + xếp thứ tự ─────────────────────────────────────────────────── */
teq( '🔴 dau_muc_cua_loai: đúng khối', 'Chi Phí Cơ Sở KVC', VHCP_Cfg::dau_muc_cua_loai( 'Chi phí cơ sở', 'kvc' ) );
teq( '🔴 khối lệch (mtd) → hỏi lại không khối, vẫn ra đầu mục — trục BÀY, không phải trục hạch toán', 'Chi Phí Cơ Sở KVC', VHCP_Cfg::dau_muc_cua_loai( 'Chi phí cơ sở', 'mtd' ) );
teq( '   không phân biệt hoa/thường', 'Chi Phí Vận Hành', VHCP_Cfg::dau_muc_cua_loai( 'CHI PHÍ MARKETING', '' ) );
teq( '   loại lạ → rỗng', '', VHCP_Cfg::dau_muc_cua_loai( 'Loại không có', 'kvc' ) );
teq( '   loại có mà ô Đầu mục trống → rỗng', '', VHCP_Cfg::dau_muc_cua_loai( 'Chi phí chưa xếp', 'kvc' ) );
teq( '🔴 xep_dau_muc: theo bảng Đầu mục, tên lạ sau (chữ cái), "(nhiều đầu mục)", rỗng CUỐI',
	array( 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', 'Abc lạ', 'Zzz lạ', '(nhiều đầu mục)', '' ),
	VHCP_Cfg::xep_dau_muc( array( '', '(nhiều đầu mục)', 'Zzz lạ', 'Chi Phí Cơ Sở KVC', 'Abc lạ', 'Chi Phí Vận Hành' ) ) );
teq( '   dau_muc_don_: mọi dòng một đầu mục → đầu mục ấy', 'Chi Phí Cơ Sở KVC', VHCP_Don::dau_muc_don_( array( 'Chi Phí Cơ Sở KVC' => 1 ) ) );
teq( '🔴 dau_muc_don_: lẫn 2 đầu mục có tên → "(nhiều đầu mục)"', '(nhiều đầu mục)', VHCP_Don::dau_muc_don_( array( 'Chi Phí Cơ Sở KVC' => 1, 'Chi Phí Vận Hành' => 1 ) ) );
teq( '🔴 dau_muc_don_: dòng chưa xếp KHÔNG kéo đơn khỏi đầu mục đã có tên', 'Chi Phí Cơ Sở KVC', VHCP_Don::dau_muc_don_( array( '' => 1, 'Chi Phí Cơ Sở KVC' => 1 ) ) );
teq( '   dau_muc_don_: không dòng nào có tên → rỗng', '', VHCP_Don::dau_muc_don_( array( '' => 1 ) ) );
teq( '   dau_muc_don_: đơn không dòng → rỗng', '', VHCP_Don::dau_muc_don_( array() ) );

/* ── Dựng đơn ĐÃ QUYẾT TOÁN ─────────────────────────────────────────────────────────────────── */
function don_xong( $ky, $coso, $dong ) {
	$d = VHCP_Don::create_don( $ky, 'Kế Toán A' );
	$m = $d['maDon'];
	$tong = 0;
	foreach ( $dong as $x ) {
		VHCP_Don::add_line( $m, array( 'coso' => $coso, 'ngay' => '2026-09-22',
			'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => $x[0], 'noiDung' => $x[1],
			'soLuong' => 1, 'donGia' => $x[2], 'thanhTien' => $x[2] ) );
		$tong += $x[2];
	}
	VHCP_Don::set_tam_ung( $m, $coso, $tong );
	VHCP_Don::gui_duyet_tam_ung( $m );
	VHCP_Don::duyet_tam_ung( $m, 'Kế Toán A', '' );
	VHCP_Don::cap_tam_ung( $m, 'Kế Toán A', 'Tiền mặt' );
	VHCP_Don::gui_quyet_toan( $m );
	VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m ), 'Kế Toán A' );
	return $m;
}
$M_KVC  = don_xong( $KY, 'ADV GO AN LAC',  array( array( 'Chi phí cơ sở', 'gậy chụp ảnh', 277000 ), array( 'Chi phí cơ sở', 'bong bóng', 182000 ) ) );
$M_VH   = don_xong( $KY, 'FUNZONE AN LAC', array( array( 'Chi phí marketing', 'quảng cáo', 300000 ) ) );
$M_LAN  = don_xong( $KY, 'FUNZONE AN LAC', array( array( 'Chi phí cơ sở', 'chanh', 24000 ), array( 'Chi phí marketing', 'in ấn', 50000 ) ) );
$M_TRG  = don_xong( $KY, 'ADV GO AN LAC',  array( array( 'Chi phí chưa xếp', 'lạ', 45000 ) ) );
$M_TRON = don_xong( $KY, 'ADV GO AN LAC',  array( array( 'Chi phí chưa xếp', 'lạ', 30000 ), array( 'Chi phí cơ sở', 'ga', 30000 ) ) );

/* ── 1. Xuất MISA: rowDauMuc song song rows, dauMucThu đúng thứ tự bảng Đầu mục ────────────── */
$x = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all' );
teq( '   đủ 8 dòng của 5 đơn', array( 8, 5 ), array( $x['count'], $x['sodon'] ) );
teq( '🔴 rowDauMuc dài đúng bằng rows', count( $x['rows'] ), count( $x['rowDauMuc'] ) );
$dem = array();
foreach ( $x['rowDauMuc'] as $dm ) { $dem[ $dm ] = isset( $dem[ $dm ] ) ? $dem[ $dm ] + 1 : 1; }
ksort( $dem );
teq( '🔴 đếm theo đầu mục: 4 dòng Cơ Sở KVC · 2 dòng Vận Hành · 2 dòng chưa xếp — DÙ mọi cơ sở cùng một mảng "EVENT FZ MN"',
	array( '' => 2, 'Chi Phí Cơ Sở KVC' => 4, 'Chi Phí Vận Hành' => 2 ), $dem );
/* Dòng nào đầu mục nấy: soi chuỗi diễn giải (cột 5 khi gộp TK) — dòng "quảng cáo"/"in ấn" là marketing. */
foreach ( $x['rows'] as $i => $r ) {
	$dg = $r[5];
	if ( false !== strpos( $dg, 'quảng cáo' ) || false !== strpos( $dg, 'in ấn' ) ) { teq( "   dòng marketing → Vận Hành ($dg)", 'Chi Phí Vận Hành', $x['rowDauMuc'][ $i ] ); }
	if ( false !== strpos( $dg, '_lạ' ) ) { teq( "   dòng loại chưa xếp → rỗng ($dg)", '', $x['rowDauMuc'][ $i ] ); }
}
teq( '🔴 dauMucThu: Vận Hành trước KVC (thứ tự bảng Đầu mục, không phải chữ cái), rỗng CUỐI', array( 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', '' ), $x['dauMucThu'] );
t( '   không còn rowMang', ! isset( $x['rowMang'] ) );
/* Lọc TK Nợ về một mã → chỉ còn một đầu mục → dauMucThu một phần tử (màn sẽ không tách). */
$mot = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', '64191' );
teq( '   lọc TK 64191 (marketing) → dauMucThu chỉ còn Vận Hành', array( 'Chi Phí Vận Hành' ), $mot['dauMucThu'] );

/* ── 2. list_dons: dauMuc của từng đơn ────────────────────────────────────────────────────── */
$theo_ma = array();
foreach ( VHCP_Don::list_dons() as $d ) { $theo_ma[ $d['maDon'] ] = isset( $d['dauMuc'] ) ? $d['dauMuc'] : '(THIẾU KHOÁ)'; }
teq( '🔴 đơn toàn dòng "Chi phí cơ sở" → Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở KVC', $theo_ma[ $M_KVC ] );
teq( '🔴 đơn toàn dòng marketing → Chi Phí Vận Hành', 'Chi Phí Vận Hành', $theo_ma[ $M_VH ] );
teq( '🔴 đơn lẫn cơ sở + marketing → "(nhiều đầu mục)"', '(nhiều đầu mục)', $theo_ma[ $M_LAN ] );
teq( '   đơn toàn loại chưa xếp → rỗng', '', $theo_ma[ $M_TRG ] );
teq( '🔴 đơn có dòng chưa xếp + dòng cơ sở → vẫn Cơ Sở KVC (chưa xếp không kéo đơn ra)', 'Chi Phí Cơ Sở KVC', $theo_ma[ $M_TRON ] );

/* ── 3. Đổi đầu mục ở danh mục → lượt tra sau phải thấy ngay (cache xoá cùng cấu hình) ─────── */
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Chi phí cơ sở',     'tkNo' => '64196', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'khoi' => 'kvc', 'dauMuc' => 'Chi Phí Khác' ),
	array( 'ten' => 'Chi phí marketing', 'tkNo' => '64191', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'khoi' => 'kvc', 'dauMuc' => 'Chi Phí Vận Hành' ),
	array( 'ten' => 'Chi phí chưa xếp',  'tkNo' => '64199', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'khoi' => 'kvc', 'dauMuc' => '' ),
) ) );
teq( '🔴 đổi "Chi phí cơ sở" sang Chi Phí Khác → tra lại ra ngay, không dính bộ nhớ cũ', 'Chi Phí Khác', VHCP_Cfg::dau_muc_cua_loai( 'Chi phí cơ sở', 'kvc' ) );
$theo_ma2 = array();
foreach ( VHCP_Don::list_dons() as $d ) { $theo_ma2[ $d['maDon'] ] = $d['dauMuc']; }
teq( '   list_dons cũng theo đầu mục mới', 'Chi Phí Khác', $theo_ma2[ $M_KVC ] );

if ( $truot ) { echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n"; foreach ( $truot as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: máy chủ tính đầu mục cho từng dòng MISA và từng đơn, thứ tự bày theo bảng Đầu mục.\n";
