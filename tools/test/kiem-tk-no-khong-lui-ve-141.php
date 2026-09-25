<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TK NỢ KHÔNG BAO GIỜ LÙI VỀ 141 / 331 — DÙ MÃ ẤY NẰM TRONG DANH MỤC HAY MA TRẬN.
 *
 * Anh Thắng 24/09/2026, ảnh bảng xuất MISA đơn "Chi phí cơ sở EVENT FZ MN": mọi dòng ra
 * Nợ 141 · Có 141 — *"Sao lại đổi tk nợ"*, rồi *"tk nợ là theo bảng ma trận chứ"*. Ma trận
 * Miền Nam lúc ấy TRỐNG cột "Chi phí cơ sở" (mã nằm dưới cột "Chi phí chung VP"), nên phép tra
 * rơi xuống cột TK Nợ của DANH MỤC — cột được gieo từ bảng Nhóm cũ, toàn 141.
 *
 * 🔴 CHẠY THẬT trên cấu hình dựng đúng cảnh ấy: tra mã, gắn lên dòng, và xuất MISA. Kỳ vọng:
 *    TRỐNG + BÁO THIẾU đúng loại · đúng mảng, không phải 141.
 *
 * Chạy: php tools/test/kiem-tk-no-khong-lui-ve-141.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-24 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* Cấu hình đúng cảnh trong ảnh. */
VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'ADV Go An Lạc', 'maDonVi' => 'EVFZ', 'phanLoaiLon' => 'EVENT FZ MN', 'tenMisa' => 'EVENT FZ MN ADV Go An Lạc' ),
		array( 'ten' => 'FARM NHA TRANG', 'maDonVi' => 'FARM', 'phanLoaiLon' => 'FARM MN',     'tenMisa' => 'FARM MN' ),
	),
	'loaiChiPhi' => array(
		/* 🔴 cột TK Nợ của danh mục = 141 — di sản gieo từ bảng Nhóm mặt hàng. */
		array( 'ten' => 'Chi phí cơ sở', 'tkNo' => '141', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
		array( 'ten' => 'Chi phí khác',  'tkNo' => '331', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
		/* Loại có mã cố định THẬT, không có ô ma trận — bậc lùi về danh mục vẫn phải sống. */
		array( 'ten' => 'Chi phí lương', 'tkNo' => '6421', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
		array( 'ten' => 'Chi phí chung VP', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
	),
	'tkNoMatrix' => array(
		/* Mã của "Chi phí cơ sở" ở EVENT bị dời sang cột "Chi phí chung VP" — cột "Chi phí cơ sở" TRỐNG ở EVENT. */
		array( 'nhom' => 'Chi phí chung VP', 'pll' => 'EVENT FZ MN', 'tkNo' => '64196' ),
		array( 'nhom' => 'Chi phí cơ sở',    'pll' => 'FARM MN',     'tkNo' => '64166' ),
		/* Ô ma trận mà ai đó gõ 141 vào — cũng không được vào cột Nợ. */
		array( 'nhom' => 'Chi phí khác',     'pll' => 'EVENT FZ MN', 'tkNo' => '141' ),
	),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Nguyễn Thị Phương Hòa', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'maDt' => 'NV_PH' ),
	),
) );
VHCP_Cfg::clear_cache();

/* ═══ 1. Phép tra ═══════════════════════════════════════════════════════════════════════ */
teq( '🔴 ma trận trống ở EVENT, danh mục ghi 141 → tkno_loai trả RỖNG, không phải 141', '', VHCP_Cfg::tkno_loai( 'Chi phí cơ sở', 'ADV Go An Lạc' ) );
teq( '🔴 tkno_xuat (dòng mang 141) → RỖNG để báo thiếu', '', VHCP_Cfg::tkno_xuat( 'Chi phí cơ sở', 'ADV Go An Lạc', '141' ) );
teq( '🔴 ô ma trận ghi 141 → coi như chưa khai (list rỗng)', array(), VHCP_Cfg::tkno_mx_list( 'Chi phí khác', 'ADV Go An Lạc' ) );
teq( '🔴 … và danh mục ghi 331 cũng bị gạt → rỗng', '', VHCP_Cfg::tkno_xuat( 'Chi phí khác', 'ADV Go An Lạc', '' ) );
teq( '   ma trận có mã thật ở FARM → vẫn ra 64166 (ma trận là nguồn)', '64166', VHCP_Cfg::tkno_loai( 'Chi phí cơ sở', 'FARM NHA TRANG' ) );
teq( '   loại có mã cố định THẬT, không ma trận → vẫn lùi về danh mục 6421', '6421', VHCP_Cfg::tkno_loai( 'Chi phí lương', 'ADV Go An Lạc' ) );
teq( '   resolve_tk lúc nhập cũng không gắn 141 lên dòng', '', VHCP_Cfg::resolve_tk( 'Chi phí cơ sở', 'Tạm ứng NV', array(), 'ADV Go An Lạc' )['tk_no'] );
teq( '   tk_of_line của đơn tuần cũng không gắn 141', '', VHCP_Don::tk_of_line( 'Chi phí cơ sở', 'Thanh toán cá nhân', 'ADV Go An Lạc' )['tk_no'] );
teq( '   TK Có vẫn 141 (bên trả tiền — đúng chỗ của nó)', '141', VHCP_Cfg::resolve_tk( 'Chi phí cơ sở', 'Tạm ứng NV', array(), 'ADV Go An Lạc' )['tk_co'] );

/* ═══ 2. Xuất MISA đúng cảnh trong ảnh ═════════════════════════════════════════════════ */
function don_xong( $ky, $coso, $dong ) {
	$d = VHCP_Don::create_don( $ky, 'Nguyễn Thị Phương Hòa' );
	$m = $d['maDon']; $tong = 0;
	foreach ( $dong as $x ) {
		VHCP_Don::add_line( $m, array( 'coso' => $coso, 'ngay' => '2026-09-02', 'phanLoaiTT' => 'Thanh toán cá nhân',
			'nhom' => $x[0], 'noiDung' => $x[1], 'soLuong' => 1, 'donGia' => $x[2], 'thanhTien' => $x[2] ) );
		$tong += $x[2];
	}
	VHCP_Don::set_tam_ung( $m, $coso, $tong );
	VHCP_Don::gui_duyet_tam_ung( $m );
	VHCP_Don::duyet_tam_ung( $m, 'Nguyễn Thị Phương Hòa', '' );
	VHCP_Don::cap_tam_ung( $m, 'Nguyễn Thị Phương Hòa', 'Tiền mặt' );
	VHCP_Don::gui_quyet_toan( $m );
	VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m ), 'Nguyễn Thị Phương Hòa' );
	return $m;
}
$KY = 'T9/2026 (1/9-6/9/2026)';
don_xong( $KY, 'ADV Go An Lạc', array( array( 'Chi phí cơ sở', 'GẬY CHỤP ẢNH', 277000 ), array( 'Chi phí cơ sở', 'BONG BÓNG', 182000 ), array( 'Chi phí khác', 'ship', 47000 ) ) );
don_xong( $KY, 'FARM NHA TRANG', array( array( 'Chi phí cơ sở', 'cỏ', 50000 ) ) );
$ex = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
t( '⚠️ xuất được', is_array( $ex ) && isset( $ex['rows'] ) && count( $ex['rows'] ) === 4, isset( $ex['rows'] ) ? count( $ex['rows'] ) : $ex );
$ben_tra = 0; $no_fz = array(); $no_farm = '';
foreach ( (array) $ex['rows'] as $rw ) {
	if ( VHCP_Cfg::la_tk_ben_tra( (string) $rw[5] ) ) { $ben_tra++; }
	if ( false !== mb_strpos( (string) $rw[4], 'EVENT FZ MN' ) ) { $no_fz[] = (string) $rw[5]; }
	if ( false !== mb_strpos( (string) $rw[4], 'FARM MN' ) )     { $no_farm = (string) $rw[5]; }
}
teq( '🔴 KHÔNG dòng nào mang 141/331 ở cột TK Nợ', 0, $ben_tra );
teq( '🔴 ba dòng EVENT FZ MN: TK Nợ TRỐNG (không phải 141)', array( '', '', '' ), $no_fz );
teq( '   dòng FARM vẫn ra 64166 từ ma trận', '64166', $no_farm );
$w = implode( "\n", (array) $ex['warn'] );
t( '🔴 báo THIẾU đúng loại, đúng mảng: "Chi phí cơ sở (mảng EVENT FZ MN)"', false !== mb_strpos( $w, 'Thiếu TK Nợ cho loại chi phí: Chi phí cơ sở (mảng EVENT FZ MN)' ), $w );
t( '   và cho "Chi phí khác (mảng EVENT FZ MN)" (ô ma trận ghi 141)', false !== mb_strpos( $w, 'Thiếu TK Nợ cho loại chi phí: Chi phí khác (mảng EVENT FZ MN)' ), $w );
t( '   không báo thiếu cho FARM', false === mb_strpos( $w, 'mảng FARM MN' ), $w );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: 141/331 trong danh mục hay ma trận không bao giờ lên cột TK Nợ; ma trận trống thì báo thiếu đúng loại · mảng.\n";
