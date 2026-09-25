<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * LỌC "MẢNG KINH DOANH" TRÊN XUẤT MISA — `VHCP_Misa::export_misa( ..., $mang_f )`.
 *
 * Anh Thắng 25/09/2026 (ảnh bảng 🧮 Loại chi phí × Mảng kinh doanh — Chi Phí Vận Hành 9 loại ·
 * Chi Phí Cơ Sở KVC 9 loại · Chi Phí Cơ Sở MTD 3 loại · Chi Phí Khác 0 loại — cạnh ảnh màn Xuất
 * MISA đang gộp 28 đơn · 275 dòng thành MỘT bảng): *"Mỗi chi phí sẽ xuất ra 1 bảng misa riêng"*.
 *
 * Chốt: thêm ô lọc Mảng kinh doanh (Phân loại lớn của cơ sở) cạnh ô lọc TK Nợ đã có — chọn một
 * mảng thì tệp CHỈ còn dòng của mảng đó, xếp cơ sở CHƯA KHAI mảng vào giả "(chưa khai mảng)".
 * `mangDs` đếm TRƯỚC khi lọc (giống `tkDs`) nên ô chọn không tự rớt mất lựa chọn khác.
 *
 * Chạy: php tools/test/kiem-loc-mang-xuat-misa.php
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
VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'ADV GO AN LAC',  'maDonVi' => 'ADVGAL', 'phanLoaiLon' => 'Chi Phí Vận Hành', 'tenMisa' => 'ADV GO AN LAC' ),
		array( 'ten' => 'FUNZONE AN LAC', 'maDonVi' => 'FZAL',   'phanLoaiLon' => 'Chi Phí Cơ Sở KVC', 'tenMisa' => 'FUNZONE AN LAC' ),
		array( 'ten' => 'KHO CHUA KHAI',  'maDonVi' => 'KHO',    'phanLoaiLon' => '',                  'tenMisa' => 'KHO CHUA KHAI' ),
	),
	'tkNoMatrix' => array(
		array( 'nhom' => 'Chi phí cơ sở', 'pll' => 'Chi Phí Vận Hành', 'tkNo' => '64191' ),
		array( 'nhom' => 'Chi phí cơ sở', 'pll' => 'Chi Phí Cơ Sở KVC', 'tkNo' => '64196' ),
		array( 'nhom' => 'Chi phí cơ sở', 'pll' => '', 'tkNo' => '64199' ),
	),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Kế Toán A', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'tkCo' => '3341', 'maDt' => 'NV_A' ),
	),
) );

function don_xong( $ky, $coso, $tien ) {
	$d = VHCP_Don::create_don( $ky, 'Kế Toán A' );
	$m = $d['maDon'];
	VHCP_Don::add_line( $m, array( 'coso' => $coso, 'ngay' => '2026-09-22',
		'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'x',
		'soLuong' => 1, 'donGia' => $tien, 'thanhTien' => $tien ) );
	VHCP_Don::set_tam_ung( $m, $coso, $tien );
	VHCP_Don::gui_duyet_tam_ung( $m );
	VHCP_Don::duyet_tam_ung( $m, 'Kế Toán A', '' );
	VHCP_Don::cap_tam_ung( $m, 'Kế Toán A', 'Tiền mặt' );
	VHCP_Don::gui_quyet_toan( $m );
	VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m ), 'Kế Toán A' );
	return $m;
}

don_xong( $KY, 'ADV GO AN LAC', 100000 );
don_xong( $KY, 'ADV GO AN LAC', 200000 );
don_xong( $KY, 'FUNZONE AN LAC', 300000 );
don_xong( $KY, 'KHO CHUA KHAI', 400000 );

/* ── Không lọc: mọi mảng, cùng luật cũ y nguyên (không truyền $mang_f → mặc định 'all') ─────── */
$all = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all' );
teq( '   không lọc mảng: đủ 4 dòng, mangLoc trả về "all"', array( 4, 'all' ), array( $all['count'], $all['mangLoc'] ) );
teq( '🔴 mangDs liệt kê đủ 3 mảng, mảng có tên trước, "(chưa khai mảng)" luôn CUỐI', array( 'Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', '(chưa khai mảng)' ), $all['mangDs'] );

/* ── Lọc "Chi Phí Vận Hành": CHỈ còn 2 dòng của ADV GO AN LAC ────────────────────────────────── */
$vh = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'Chi Phí Vận Hành' );
teq( '🔴 lọc "Chi Phí Vận Hành": đúng 2 dòng, cả hai đều dòng ADV', 2, $vh['count'] );
/* soct + không lọc TK Nợ -> $gop_tk=true -> TK Nợ được unshift vào đầu mỗi dòng, mọi cột sau lùi 1:
   0=tkNo,1=ngày HT,2=ngày CT,3=Số chứng từ (mã đơn),4=Diễn giải chung,5=Diễn giải (mang cả mảng lẫn cơ sở). */
foreach ( $vh['rows'] as $r ) { t( '   dòng thuộc mảng Vận Hành (diễn giải)', false !== strpos( $r[5], 'Chi Phí Vận Hành' ) && false !== strpos( $r[5], 'ADV GO AN LAC' ), $r ); }
teq( '   mangLoc phản chiếu đúng lựa chọn', 'Chi Phí Vận Hành', $vh['mangLoc'] );
t( '🔴 mangDs KHÔNG rớt hai mảng còn lại — vẫn đủ 3, để đổi lựa chọn được (đếm TRƯỚC khi lọc)', 3 === count( $vh['mangDs'] ), $vh['mangDs'] );

/* ── Lọc "Chi Phí Cơ Sở KVC": đúng 1 dòng ─────────────────────────────────────────────────────── */
$kvc = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'Chi Phí Cơ Sở KVC' );
teq( '   lọc "Chi Phí Cơ Sở KVC": đúng 1 dòng', 1, $kvc['count'] );
teq( '   sodon đúng 1 (không đếm nhầm đơn của mảng khác)', 1, $kvc['sodon'] );

/* ── Lọc "(chưa khai mảng)": đúng dòng KHO CHUA KHAI ─────────────────────────────────────────── */
$trong = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', '(chưa khai mảng)' );
teq( '🔴 lọc "(chưa khai mảng)": đúng 1 dòng — cơ sở chưa khai Phân loại lớn', 1, $trong['count'] );
t( '   dòng đúng là KHO CHUA KHAI', false !== strpos( $trong['rows'][0][5], 'KHO' ), $trong['rows'] );

/* ── Mảng không tồn tại (gõ lệch / cấu hình đã đổi tên) → tệp RỖNG, không âm thầm trả về "mọi mảng" ── */
$la = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'Mảng Không Tồn Tại' );
teq( '🔴 mảng lạ → 0 dòng (không lặng lẽ lùi về không lọc)', 0, $la['count'] );

/* ── Hoà hợp lọc TK Nợ + lọc Mảng cùng lúc ───────────────────────────────────────────────────── */
$ca_hai = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', '64191', 'Chi Phí Vận Hành' );
teq( '   TK Nợ 64191 + mảng Vận Hành cùng lúc: vẫn 2 dòng (không loại trừ nhau)', 2, $ca_hai['count'] );
$khac_tk = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', '64196', 'Chi Phí Vận Hành' );
teq( '   TK Nợ 64196 (của mảng khác) + mảng Vận Hành: 0 dòng — hai bộ lọc CÙNG phải đúng', 0, $khac_tk['count'] );

/* ── Gọi CŨ (5 tham số, không có $mang_f) vẫn chạy y hệt trước — không phá vỡ lời gọi có sẵn ─── */
$cu = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all' );
teq( '🔴 lời gọi CŨ (thiếu tham số mảng) vẫn ra đủ 4 dòng như trước khi thêm tính năng', 4, $cu['count'] );

if ( $truot ) { echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n"; foreach ( $truot as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: lọc Mảng kinh doanh trên Xuất MISA, mỗi mảng xuất thành một bảng riêng.\n";
