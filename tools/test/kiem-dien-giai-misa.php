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

/* ═══ 1. Kỳ là HAI LOẠI, tự tách theo đơn ═══ */
teq( '🔴 tuần chuẩn 7 ngày "T9/2026 (21/9-27/9/2026)" → "T9/2026"', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (21/9-27/9/2026)' ) );
teq( '🔴 khoảng tự chọn (kỳ thuê) "T9/2026 (26/8-25/9/2026)" → "ngày 26/8-25/9/2026"', 'ngày 26/8-25/9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (26/8-25/9/2026)' ) );
teq( '   khoảng 3 ngày → kiểu ngày', 'ngày 2/9-4/9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (2/9-4/9/2026)' ) );
teq( '   tuần bắc tháng (31/8-6/9) vẫn là tuần chuẩn → theo tháng của nhãn', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (31/8-6/9/2026)' ) );
teq( '   khoảng 8 ngày → kiểu ngày (không phải tuần)', 'ngày 21/9-28/9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (21/9-28/9/2026)' ) );
teq( '   kỳ không có khoảng ngày → nguyên văn', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026' ) );
teq( '   kỳ lạ → nguyên văn', 'Đợt 3 Vũng Tàu', VHCP_Misa::ky_dien_giai( 'Đợt 3 Vũng Tàu' ) );
teq( '   la_tuan_chuan đúng/sai', array( true, false ), array( VHCP_Misa::la_tuan_chuan( 'T9/2026 (21/9-27/9/2026)' ), VHCP_Misa::la_tuan_chuan( 'T9/2026 (26/8-25/9/2026)' ) ) );
/* Tuần cũ bị cắt theo tháng (ảnh anh Thắng 24/09: "ngày 1/9-6/9/2026" — thứ Ba → Chủ nhật) vẫn là tuần. */
teq( '🔴 tuần cũ cắt theo tháng "1/9-6/9/2026" (Ba→CN) → vẫn kiểu tháng', 'T9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (1/9-6/9/2026)' ) );
teq( '   nửa tuần đầu tháng "31/8-31/8/2026" (thứ Hai) → kiểu tháng', 'T8/2026', VHCP_Misa::ky_dien_giai( 'T8/2026 (31/8-31/8/2026)' ) );
teq( '   3 ngày giữa tuần (Tư→Sáu) → vẫn kiểu ngày', 'ngày 2/9-4/9/2026', VHCP_Misa::ky_dien_giai( 'T9/2026 (2/9-4/9/2026)' ) );
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
/* Đơn khoảng tự chọn (kỳ thuê mặt bằng) trong CÙNG lượt xuất → kiểu ngày, đơn tuần bên trên vẫn kiểu tháng */
$d2 = VHCP_Don::create_don( 'T9/2026 (26/8-25/9/2026)', 'Nguyễn Mai Anh' ); $m2 = $d2['maDon'];
VHCP_Don::add_line( $m2, array( 'coso' => 'AEON MALL BÌNH DƯƠNG', 'ngay' => '2026-09-09', 'phanLoaiTT' => 'Nhà cung cấp', 'nhom' => 'Chi Phí Thuê Mặt Bằng', 'noiDung' => 'Kỳ 26/8-25/9', 'soLuong' => 1, 'donGia' => 3300000, 'thanhTien' => 3300000 ) );
VHCP_Don::set_tam_ung( $m2, 'AEON MALL BÌNH DƯƠNG', 0 ); VHCP_Don::gui_duyet_tam_ung( $m2 ); VHCP_Don::duyet_tam_ung( $m2, 'Nguyễn Mai Anh', '' );
VHCP_Don::cap_tam_ung( $m2, 'Nguyễn Mai Anh', 'Tiền mặt' ); VHCP_Don::gui_quyet_toan( $m2 ); VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m2 ), 'Nguyễn Mai Anh' );
$ex2 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$vh2 = null; $mb2 = null; foreach ( $ex2['rows'] as $r ) { if ( false !== mb_strpos( $r[4], 'da ghế' ) ) { $vh2 = $r; } if ( false !== mb_strpos( $r[4], 'Kỳ 26/8-25/9' ) ) { $mb2 = $r; } }
t( '⚠️ có cả hai dòng', $vh2 && $mb2, $ex2['rows'] );
teq( '🔴 cùng một tệp: đơn tuần → "T9/2026", đơn khoảng tự chọn → "ngày 26/8-25/9/2026"',
	array( 'Chi phí khác POSH MN AEON MALL BÌNH DƯƠNG T9/2026_Phí gửi da ghế', 'Chi Phí Thuê Mặt Bằng POSH MN AEON MALL BÌNH DƯƠNG ngày 26/8-25/9/2026_Kỳ 26/8-25/9' ),
	array( $vh2[4], $mb2[4] ) );
teq( '   diễn giải chung của đơn khoảng tự chọn cũng kiểu ngày', 'Chi Phí Thuê Mặt Bằng POSH MN ngày 26/8-25/9/2026_Nguyễn Mai Anh', $mb2[3] );
/* ═══ Tên + mã đối tượng theo NGƯỜI TẠO ĐƠN, không theo người duyệt (anh Thắng 25/09/2026) ═══ */
VHCP_Cfg::save_config( array(
	'coso' => array( array( 'ten' => 'AEON MALL BÌNH DƯƠNG', 'maDonVi' => '51AMBD', 'phanLoaiLon' => 'POSH MN', 'tenMisa' => 'POSH MN AEON MALL BÌNH DƯƠNG' ) ),
	'loaiChiPhi' => array( array( 'ten' => 'Chi Phí Vận Hành', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => 'Chi phí khác', 'khoi' => 'mn' ) ),
	'tkNoMatrix' => array( array( 'nhom' => 'Chi Phí Vận Hành', 'pll' => 'POSH MN', 'tkNo' => '6418' ) ),
	'phanloai' => array( array( 'ten' => 'Thanh toán cá nhân', 'tkCo' => '141' ), array( 'ten' => 'Nhà cung cấp', 'tkCo' => '331' ) ),
	'doiTuong' => array( array( 'ten' => 'Công ty Vận Chuyển X', 'ma' => 'NCC_X', 'loai' => 'NCC' ) ),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Thu Thảo', 'pin' => '2222', 'vaiTro' => 'Nhân viên', 'maDt' => 'NV_THAO' ),
		array( 'ten' => 'Công Trí', 'pin' => '3333', 'vaiTro' => 'Nhân viên', 'maDt' => 'NV_TRI' ),
		array( 'ten' => 'Quản Lý B', 'pin' => '4444', 'vaiTro' => 'Quản lý', 'maDt' => 'NV_QLB' ),
	),
) );
VHCP_Cfg::clear_cache();
$d3 = VHCP_Don::create_don( 'T9/2026 (14/9-20/9/2026)', 'Thu Thảo' ); $m3 = $d3['maDon'];
/* Trí thêm dòng vào đơn của Thảo (đối tượng ghi tên Trí), và một dòng NCC. */
VHCP_Don::add_line( $m3, array( 'coso' => 'AEON MALL BÌNH DƯƠNG', 'ngay' => '2026-09-15', 'phanLoaiTT' => 'Thanh toán cá nhân', 'doiTuong' => 'Công Trí', 'nhom' => 'Chi Phí Vận Hành', 'noiDung' => 'băng keo', 'soLuong' => 1, 'donGia' => 10000, 'thanhTien' => 10000 ) );
VHCP_Don::add_line( $m3, array( 'coso' => 'AEON MALL BÌNH DƯƠNG', 'ngay' => '2026-09-15', 'phanLoaiTT' => 'Nhà cung cấp', 'doiTuong' => 'Công ty Vận Chuyển X', 'nhom' => 'Chi Phí Vận Hành', 'noiDung' => 'cước xe', 'soLuong' => 1, 'donGia' => 20000, 'thanhTien' => 20000 ) );
VHCP_Don::set_tam_ung( $m3, 'AEON MALL BÌNH DƯƠNG', 10000 ); VHCP_Don::gui_duyet_tam_ung( $m3 ); VHCP_Don::duyet_tam_ung( $m3, 'Quản Lý B', '' );
VHCP_Don::cap_tam_ung( $m3, 'Quản Lý B', 'Tiền mặt' ); VHCP_Don::gui_quyet_toan( $m3 ); VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m3 ), 'Quản Lý B' ); VHCP_Don::xac_nhan_quyet_toan_ncc( $m3, 'Quản Lý B' );
$ex4 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$cn = null; $ncc = null; foreach ( $ex4['rows'] as $r ) { if ( false !== mb_strpos( $r[4], 'băng keo' ) ) { $cn = $r; } if ( false !== mb_strpos( $r[4], 'cước xe' ) ) { $ncc = $r; } }
t( '⚠️ có cả hai dòng', $cn && $ncc, $ex4['rows'] );
teq( '🔴 Diễn giải chung đuôi = NGƯỜI TẠO ĐƠN (Thu Thảo), không phải người duyệt (Quản Lý B)', 'Chi phí khác POSH MN T9/2026_Thu Thảo', $cn[3] );
teq( '🔴 Mã đối tượng dòng cá nhân = mã NV của người tạo đơn (dù Trí gõ tên mình ở ô đối tượng)', 'NV_THAO', $cn[8] );
teq( '🔴 Mã đối tượng dòng NCC = mã nhà cung cấp ghi trên dòng', 'NCC_X', $ncc[8] );
teq( '   không còn dấu vết mã người duyệt', 0, substr_count( json_encode( $ex4['rows'] ), 'NV_QLB' ) );

/* Mẫu sổ chi tiết cũng đi qua cùng diễn giải */
$ex3 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all' );
$vh3 = null; foreach ( $ex3['rows'] as $r ) { if ( false !== mb_strpos( $r[5], 'da ghế' ) ) { $vh3 = $r; } }
t( '   mẫu 13 cột (có cột TK Nợ đầu) cũng mang diễn giải mới', $vh3 && 'Chi phí khác POSH MN AEON MALL BÌNH DƯƠNG T9/2026_Phí gửi da ghế' === $vh3[5], $ex3['rows'] );

/* ═══ 4. Màn Xuất MISA KHÔNG còn ô chọn kiểu kỳ (máy chủ tự tách) ═══ */
$app = (string) file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 không còn ô `xuatKyKieu`', false === strpos( $app, 'id="xuatKyKieu"' ) );
/* 25/09/2026: thêm tham số thứ 6 — Mảng kinh doanh (anh Thắng: "Mỗi chi phí sẽ xuất ra 1 bảng misa riêng"), lùi 'all'. */
t( '   callExport gọi exportMisa với 6 tham số (mẫu lùi soct, TK Nợ lùi all, mảng lùi all)', 1 === preg_match( "/\.exportMisa\(el\('xuatKy'\)\.value, el\('xuatTT'\)\.value, plF,\s*\(el\('xuatMau'\)&&el\('xuatMau'\)\.value\)\|\|'soct',\s*\(el\('xuatTkNo'\)&&el\('xuatTkNo'\)\.value\)\|\|'all',\s*\(el\('xuatMang'\)&&el\('xuatMang'\)\.value\)\|\|'all'\);/", $app ) );
/* ═══ 5. Mẫu 13 cột là MẶC ĐỊNH, nhãn không còn "MTĐ · VP" ═══ */
t( '🔴 option soct đứng trước và selected', 1 === preg_match( '/<select id="xuatMau"[^>]*>\s*(<!--[\s\S]*?-->\s*)?<option value="soct" selected>/', $app ) );
t( '🔴 nhãn không còn gắn riêng MTĐ · VP', false === strpos( $app, 'Sổ chi tiết — MTĐ · VP' ) && false !== strpos( $app, 'Sổ chi tiết tài khoản (13 cột)' ) );
t( '   mẫu 10 cột vẫn còn để chọn', false !== strpos( $app, '<option value="chuan">📄 Nhật ký chung (10 cột)' ) );
t( '   máy chủ không đổi mặc định (người gọi cũ không truyền mẫu vẫn nhận 10 cột)', 10 === count( VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' )['cols'] ) );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: diễn giải MISA = Loại(tên MISA) · Mảng · Cơ sở · (T9/2026 | ngày a-b) _ phần riêng; hai loại kỳ tự tách theo đơn.\n";
