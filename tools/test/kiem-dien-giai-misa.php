<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * DIỄN GIẢI MISA THEO CẤU TRÚC ANH THẮNG CHỐT 24/09/2026:
 *   "Loại Chi Phí_Phân Loại Lớn_Tên Cơ Sở_Tháng T9/2026 hoặc Ngày từ … đến …"
 * (ảnh sổ 641 thật: "Chi phí khác POSH MN AMBD T9/2026_Phí gửi da ghế").
 * Chạy: php tools/test/kiem-dien-giai-misa.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-24 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; } $TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 1. Kỳ viết gọn ═══ */
teq( '🔴 kiểu tháng: "T9/2026 (21/9-27/9/2026)" → "T9/2026"', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (21/9-27/9/2026)' ) );
teq( '🔴 kiểu ngày: → "Ngày từ 21/9 đến 27/9/2026"', 'Ngày từ 21/9 đến 27/9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (21/9-27/9/2026)', 'ngay' ) );
teq( '   tuần bắc tháng giữ đúng hai đầu', 'Ngày từ 31/8 đến 6/9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (31/8-6/9/2026)', 'ngay' ) );
teq( '   kỳ không có khoảng ngày → nguyên văn', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026', 'ngay' ) );
teq( '   kỳ lạ → nguyên văn', 'Đợt 3 Vũng Tàu', VHCP_Misa::ky_dien_giai( 'Đợt 3 Vũng Tàu' ) );
teq( '   kiểu lạ → coi như tháng', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (21/9-27/9/2026)', 'gi-do' ) );
/* ═══ 2. Tên gian bỏ phần lặp mảng ═══ */
teq( '🔴 "POSH MN AEON MALL BÌNH DƯƠNG" với mảng "POSH MN" → "AEON MALL BÌNH DƯƠNG"', 'AEON MALL BÌNH DƯƠNG', VHCP_Misa::ten_coso_gon( 'POSH MN AEON MALL BÌNH DƯƠNG', 'POSH MN' ) );
teq( '   không lặp thì giữ nguyên', 'FUNZONE VUNG TAU', VHCP_Misa::ten_coso_gon( 'FUNZONE VUNG TAU', 'FUNZONE MN' ) );
teq( '   tên trùng hẳn mảng → giữ nguyên (không để trống)', 'POSH MN', VHCP_Misa::ten_coso_gon( 'POSH MN', 'POSH MN' ) );
teq( '   không phân biệt hoa thường', 'Yokid BMT', VHCP_Misa::ten_coso_gon( 'Posh mn Yokid BMT', 'POSH MN' ) );

/* ═══ 3. Xuất thật ═══ */
VHCP_Cfg::save_config( array(
	'coso' => array( array( 'ten' => 'AEON MALL BÌNH DƯƠNG', 'maDonVi' => '51AMBD', 'phanLoaiLon' => 'POSH MN', 'tenMisa' => 'POSH MN AEON MALL BÌNH DƯƠNG' ) ),
	'loaiChiPhi' => array(
		array( 'ten' => 'Chi Phí Vận Hành', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => 'Chi phí khác', 'khoi' => 'mn' ),
		array( 'ten' => 'Chi Phí Thuê Mặt Bằng', 'tkNo' => '', 'tkCo' => '331', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => '', 'khoi' => 'mn' ),
	),
	'tkNoMatrix' => array( array( 'nhom' => 'Chi Phí Vận Hành', 'pll' => 'POSH MN', 'tkNo' => '6418' ), array( 'nhom' => 'Chi Phí Thuê Mặt Bằng', 'pll' => 'POSH MN', 'tkNo' => '6417' ) ),
	'phanloai' => array( array( 'ten' => 'Thanh toán cá nhân', 'tkCo' => '141' ), array( 'ten' => 'Nhà cung cấp', 'tkCo' => '331' ) ),
	'users' => array( array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ), array( 'ten' => 'Nguyễn Mai Anh', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'maDt' => 'NV_MA' ) ),
) );
VHCP_Cfg::clear_cache();
$d = VHCP_Don::create_don( 'T9/2026 (21/9-27/9/2026)', 'Nguyễn Mai Anh' ); $m = $d['maDon'];
VHCP_Don::add_line( $m, array( 'coso' => 'AEON MALL BÌNH DƯƠNG', 'ngay' => '2026-09-22', 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi Phí Vận Hành', 'noiDung' => 'Phí gửi da ghế', 'soLuong' => 1, 'donGia' => 151000, 'thanhTien' => 151000 ) );
VHCP_Don::add_line( $m, array( 'coso' => 'AEON MALL BÌNH DƯƠNG', 'ngay' => '2026-09-22', 'phanLoaiTT' => 'Nhà cung cấp', 'nhom' => 'Chi Phí Thuê Mặt Bằng', 'noiDung' => 'Lần 3/3', 'soLuong' => 1, 'donGia' => 4950000, 'thanhTien' => 4950000 ) );
VHCP_Don::set_tam_ung( $m, 'AEON MALL BÌNH DƯƠNG', 151000 ); VHCP_Don::gui_duyet_tam_ung( $m ); VHCP_Don::duyet_tam_ung( $m, 'Nguyễn Mai Anh', '' );
VHCP_Don::cap_tam_ung( $m, 'Nguyễn Mai Anh', 'Tiền mặt' ); VHCP_Don::gui_quyet_toan( $m ); VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m ), 'Nguyễn Mai Anh' );

$ex = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
teq( '⚠️ ra 2 dòng', 2, count( $ex['rows'] ) );
$vh = null; $mb = null;
foreach ( $ex['rows'] as $r ) { if ( false !== mb_strpos( $r[4], 'da ghế' ) ) { $vh = $r; } if ( false !== mb_strpos( $r[4], 'Lần 3/3' ) ) { $mb = $r; } }
t( '⚠️ tìm được hai dòng', $vh && $mb, $ex['rows'] );
teq( '🔴 Diễn giải (hạch toán) = TênMISA-của-loại · Mảng · Cơ sở (không lặp mảng) · Tháng _ Nội dung',
	'Chi phí khác POSH MN AEON MALL BÌNH DƯƠNG T9/2026_Phí gửi da ghế', $vh[4] );
teq( '🔴 Diễn giải chung = TênMISA-của-loại · Mảng · Tháng _ Người duyệt', 'Chi phí khác POSH MN T9/2026_Nguyễn Mai Anh', $vh[3] );
teq( '   loại không khai Tên theo MISA → dùng tên gốc', 'Chi Phí Thuê Mặt Bằng POSH MN AEON MALL BÌNH DƯƠNG T9/2026_Lần 3/3', $mb[4] );
teq( '   TK Nợ/Có không đổi vì đổi diễn giải', array( '6418', '141' ), array( $vh[5], $vh[6] ) );
$ex2 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'chuan', 'all', 'ngay' );
$vh2 = null; foreach ( $ex2['rows'] as $r ) { if ( false !== mb_strpos( $r[4], 'da ghế' ) ) { $vh2 = $r; } }
teq( '🔴 kiểu ngày: "Ngày từ 21/9 đến 27/9/2026" thay cho tháng, cả hai cột', array( 'Chi phí khác POSH MN Ngày từ 21/9 đến 27/9/2026_Nguyễn Mai Anh', 'Chi phí khác POSH MN AEON MALL BÌNH DƯƠNG Ngày từ 21/9 đến 27/9/2026_Phí gửi da ghế' ), array( $vh2[3], $vh2[4] ) );
/* Mẫu sổ chi tiết cũng đi qua cùng diễn giải */
$ex3 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'thang' );
$vh3 = null; foreach ( $ex3['rows'] as $r ) { if ( false !== mb_strpos( $r[5], 'da ghế' ) ) { $vh3 = $r; } }
t( '   mẫu 13 cột (có cột TK Nợ đầu) cũng mang diễn giải mới', $vh3 && 'Chi phí khác POSH MN AEON MALL BÌNH DƯƠNG T9/2026_Phí gửi da ghế' === $vh3[5], $ex3['rows'] );

/* ═══ 4. Màn Xuất MISA có ô chọn và truyền xuống ═══ */
$app = (string) file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 có ô chọn `xuatKyKieu` với hai kiểu', false !== strpos( $app, 'id="xuatKyKieu"' ) && false !== strpos( $app, '<option value="thang">' ) && false !== strpos( $app, '<option value="ngay">' ) );
t( '🔴 callExport truyền kiểu kỳ làm tham số thứ 6', 1 === preg_match( "/\.exportMisa\(el\('xuatKy'\)\.value, el\('xuatTT'\)\.value, plF,\s*\(el\('xuatMau'\)&&el\('xuatMau'\)\.value\)\|\|'chuan',\s*\(el\('xuatTkNo'\)&&el\('xuatTkNo'\)\.value\)\|\|'all',\s*\(el\('xuatKyKieu'\)&&el\('xuatKyKieu'\)\.value\)\|\|'thang'\);/", $app ) );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: diễn giải MISA = Loại(tên MISA) · Mảng · Cơ sở · Tháng|Ngày từ…đến… _ phần riêng; kiểu kỳ chọn trên màn.\n";
