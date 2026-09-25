<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * DOANH THU VS CHI PHÍ THEO CƠ SỞ — `VHCP_DoanhThu` (bên plugin Chi phí).
 * Anh Thắng 25/09/2026: *"Em có thể lấy doanh thu cơ sở trên wed doanh-thu-hcm không."* ·
 * *"Để anh đánh giá doanh thu dựa trên chi phí"*.
 * Chốt: khoá đi trong HEADER và không bao giờ xuống màn · khoá rỗng = giữ · chỉ Admin sửa · khớp tên
 * theo cột "Tên bên Doanh thu" rồi mới khớp lỏng · chi phí = thực chi theo ngày dòng trong tháng ·
 * cơ sở có chi phí mà không có doanh thu vẫn bày · 404 ở đường dẫn trang → thử lại ở gốc web.
 * Chạy: php tools/test/kiem-doanh-thu-chi-phi.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-25 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* ── 1. Cấu hình: chưa khai → chối rõ, gói màn không mang khoá ─────────────────────────────── */
$kq = VHCP_DoanhThu::so_sanh( array( 'thang' => '2026-09' ) );
t( '🔴 chưa khai kết nối → chối, câu chỉ đường tới Cấu hình', empty( $kq['success'] ) && false !== mb_strpos( $kq['error'], 'Cấu hình' ), $kq );
$ch = VHCP_DoanhThu::cau_hinh();
teq( '   cau_hinh() chưa khai: san=false', false, $ch['san'] );
t( '🔴 gói màn KHÔNG có khoá (khoá là bí mật)', ! array_key_exists( 'khoa', $ch ), $ch );

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
$kq = VHCP_Cfg::save_config( array( 'doanhThu' => array( 'url' => 'https://khmatrix.com', 'khoa' => 'abcdefghijklmnop1234' ) ) );
t( '🔴 Kế toán không đổi được kết nối', empty( $kq['success'] ) && false !== mb_strpos( $kq['error'], 'Admin' ), $kq );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$kq = VHCP_Cfg::save_config( array( 'doanhThu' => array( 'url' => 'ftp://x', 'khoa' => '' ) ) );
t( '   địa chỉ không phải http(s) → chối', empty( $kq['success'] ), $kq );
$kq = VHCP_Cfg::save_config( array( 'doanhThu' => array( 'url' => 'https://khmatrix.com/doanh-thu-hcm/', 'khoa' => 'KHOA-BI-MAT-1234567890' ) ) );
t( '   Admin lưu địa chỉ + khoá', ! empty( $kq['success'] ), $kq );
$ch = VHCP_DoanhThu::cau_hinh();
teq( '   san=true, khoaCo=true, url (Admin) bỏ dấu / cuối', array( 'san' => true, 'url' => 'https://khmatrix.com/doanh-thu-hcm', 'khoaCo' => true ), $ch );
VHCP_Auth::dat_vai_tro( 'Quản lý', 'Quản Lý B' );
teq( '   Quản lý: không thấy địa chỉ, chỉ biết đã sẵn', '', VHCP_DoanhThu::cau_hinh()['url'] );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
VHCP_Cfg::save_config( array( 'doanhThu' => array( 'url' => 'https://khmatrix.com/doanh-thu-hcm', 'khoa' => '' ) ) );
teq( '🔴 khoá rỗng = GIỮ NGUYÊN, không xoá', 'KHOA-BI-MAT-1234567890', get_option( 'vhcp_dt_khoa' ) );
$nk = VHCP_Log::get_log( 3 );
$co_khoa_trong_log = false;
foreach ( $nk as $l ) { if ( false !== strpos( json_encode( $l, JSON_UNESCAPED_UNICODE ), 'KHOA-BI-MAT' ) ) { $co_khoa_trong_log = true; } }
t( '🔴 nhật ký không chép khoá', ! $co_khoa_trong_log, $nk );

/* ── 2. Gọi sang web: khoá trong header, 404 ở trang → lui về gốc web ────────────────────────── */
$JSON = json_encode( array( 'ok' => true, 'web' => 'Doanh thu FABi', 'cuaHang' => array(
	array( 'ten' => 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 'doanhThu' => 120000000, 'thanhTien' => 118000000, 'soHd' => 900, 'soNgay' => 25 ),
	array( 'ten' => 'TuTu Train Vincom Bà Triệu', 'doanhThu' => 30000000, 'thanhTien' => 30000000, 'soHd' => 300, 'soNgay' => 20 ),
	array( 'ten' => 'NHÀ MA PHAN VĂN TRỊ', 'doanhThu' => 50000000, 'thanhTien' => 50000000, 'soHd' => 200, 'soNgay' => 25 ),
	array( 'ten' => 'QUÁN LẠ CHƯA KHAI', 'doanhThu' => 1000000, 'thanhTien' => 1000000, 'soHd' => 5, 'soNgay' => 2 ),
	array( 'ten' => 'Toàn hệ thống', 'doanhThu' => 0, 'thanhTien' => 0, 'soHd' => 0, 'soNgay' => 0 ),
	array( 'ten' => 'QUÁN ĐÓNG KHÔNG SỐ', 'doanhThu' => 0, 'thanhTien' => 0, 'soHd' => 0, 'soNgay' => 0 ),
) ), JSON_UNESCAPED_UNICODE );
$GLOBALS['VHCP_HTTP'] = array(
	'https://khmatrix.com/doanh-thu-hcm/wp-json/khh-dt/v1/doanh-thu-co-so' => array( 'code' => 404, 'body' => '<!DOCTYPE html>' ),
	'https://khmatrix.com/wp-json/khh-dt/v1/doanh-thu-co-so'               => array( 'code' => 200, 'body' => $JSON ),
);
$GLOBALS['VHCP_DA_GET'] = array(); $GLOBALS['VHCP_DA_GET_ARGS'] = array();
$kq = VHCP_DoanhThu::goi( '2026-09-01', '2026-09-30', true );
t( '🔴 404 ở đường dẫn trang báo cáo → thử lại ở gốc web và được', ! empty( $kq['ok'] ) && 6 === count( $kq['cuaHang'] ), $kq );
teq( '   hai lượt GET', 2, count( $GLOBALS['VHCP_DA_GET'] ) );
t( '🔴 khoá KHÔNG nằm trong địa chỉ', false === strpos( implode( ' ', $GLOBALS['VHCP_DA_GET'] ), 'KHOA-BI-MAT' ), $GLOBALS['VHCP_DA_GET'] );
t( '🔴 khoá đi trong header X-KHH-Khoa', 'KHOA-BI-MAT-1234567890' === $GLOBALS['VHCP_DA_GET_ARGS'][0]['headers']['X-KHH-Khoa'], $GLOBALS['VHCP_DA_GET_ARGS'] );
t( '   địa chỉ mang tu/den', false !== strpos( $GLOBALS['VHCP_DA_GET'][0], 'tu=2026-09-01&den=2026-09-30' ), $GLOBALS['VHCP_DA_GET'][0] );
$GLOBALS['VHCP_DA_GET'] = array();
$kq2 = VHCP_DoanhThu::goi( '2026-09-01', '2026-09-30' );
t( '   lượt sau lấy từ bộ nhớ, không gọi mạng', ! empty( $kq2['nho'] ) && 0 === count( $GLOBALS['VHCP_DA_GET'] ), $GLOBALS['VHCP_DA_GET'] );
$GLOBALS['VHCP_HTTP'] = array( 'khmatrix.com' => array( 'code' => 401, 'body' => '{"code":"khh_dt_khoa","message":"Chưa có khoá"}' ) );
$kq3 = VHCP_DoanhThu::goi( '2026-08-01', '2026-08-31', true );
t( '   web chối khoá (401) → câu bảo kiểm lại khoá ở cả hai web', empty( $kq3['ok'] ) && false !== mb_strpos( $kq3['error'], 'khoá' ), $kq3 );
$GLOBALS['VHCP_HTTP'] = array( 'khmatrix.com' => array( 'code' => 200, 'body' => $JSON ) );
$kt = VHCP_DoanhThu::kiem_tra();
t( '   Kiểm tra kết nối: trả số cửa hàng + tên web', ! empty( $kt['success'] ) && 6 === $kt['soCuaHang'] && 'Doanh thu FABi' === $kt['web'], $kt );

/* ── 3. Khớp tên ─────────────────────────────────────────────────────────────────────────────── */
teq( '   rút gọn: bỏ dấu, "TuTu Train" → "tau"', 'tau vincom ba trieu', VHCP_DoanhThu::rut_gon( 'TuTu Train Vincom Bà Triệu' ) );
teq( '   rút gọn: bỏ đuôi "( Dịch Vụ và Giải Trí K&H )", adventure → adv', 'funzone adv go an lac', VHCP_DoanhThu::rut_gon( 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' ) );
teq( '   rút gọn: ngoặc mang TÊN GIAN thì giữ, ghost bride → nha ma', 'nha ma ba ria co dau am phu', VHCP_DoanhThu::rut_gon( '(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ ( Dịch Vụ và Giải Trí K&H )' ) );
/* Ca thật từ ảnh 25/09/2026 — hai dòng tự khớp SAI ở bản đầu. */
{
	$cs_that = array_map( function ( $t ) { return array( 'ten' => $t ); }, array( 'NHÀ MA AEON TÂN PHÚ', 'AEON MALL TÂN PHÚ', 'TÀU TÂN PHÚ', 'SNOW NHÀ TUYẾT BÌNH DƯƠNG', 'NHÀ MA BÌNH DƯƠNG', 'TÀU BÌNH DƯƠNG',
		'FUNZONE VŨNG TÀU', 'NHÀ MA BÀ RỊA', 'ESTELLA', 'TÀU ESTELLA', 'TÀU TÂN AN', 'VR TÂN AN', 'FUNZONE ADVENTURE', 'ADV GO! AN LẠC', 'TÀU GÒ VẤP', 'SC VIVO', 'FARM PHAN THIẾT' ) );
	$m2 = VHCP_DoanhThu::anh_xa( array( 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )', 'Tutu Train - Bình Dương ( Dịch Vụ và Giải Trí K&H )', 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )',
		'(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ ( Dịch Vụ và Giải Trí K&H )', 'Ngôi Nhà Ma - Aeon Bình Dương ( Dịch Vụ và Giải Trí K&H )', 'SNOW FUN AEON BÌNH DƯƠNG ( Dịch Vụ và Giải Trí K&H )',
		'Tutu Train - Estella ( Dịch vụ K&H )', 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 'VR Fun Aeon Tân An ( Dịch Vụ K&H )', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )',
		'TuTu Train - Lotte Gò Vấp ( Dịch vụ và Giải Trí K&H )', 'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )', 'ECO FARM LOTTE PHAN THIẾT ( Dịch Vụ K&H )', 'Tutu Train - Aeon Bình Tân ( Dịch Vụ và Giải Trí K&H )', 'COFFE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' ), $cs_that );
	$cs_ = function ( $k ) use ( $m2 ) { return isset( $m2[ $k ] ) ? $m2[ $k ]['coso'] : null; };
	teq( '🔴 "TuTu Train - Aeon Tân Phú" → TÀU TÂN PHÚ (bản đầu gán nhầm NHÀ MA AEON TÂN PHÚ)', 'TÀU TÂN PHÚ', $cs_( 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '🔴 "Tutu Train - Bình Dương" → TÀU BÌNH DƯƠNG (bản đầu gán nhầm SNOW NHÀ TUYẾT)', 'TÀU BÌNH DƯƠNG', $cs_( 'Tutu Train - Bình Dương ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '   FUNZONE CITY VŨNG TÀU → FUNZONE VŨNG TÀU', 'FUNZONE VŨNG TÀU', $cs_( 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '   (GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ → NHÀ MA BÀ RỊA', 'NHÀ MA BÀ RỊA', $cs_( '(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '   Ngôi Nhà Ma - Aeon Bình Dương → NHÀ MA BÌNH DƯƠNG', 'NHÀ MA BÌNH DƯƠNG', $cs_( 'Ngôi Nhà Ma - Aeon Bình Dương ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '   SNOW FUN AEON BÌNH DƯƠNG → SNOW NHÀ TUYẾT BÌNH DƯƠNG', 'SNOW NHÀ TUYẾT BÌNH DƯƠNG', $cs_( 'SNOW FUN AEON BÌNH DƯƠNG ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '   Tutu Train - Estella → TÀU ESTELLA (chặt hơn ESTELLA)', 'TÀU ESTELLA', $cs_( 'Tutu Train - Estella ( Dịch vụ K&H )' ) );
	teq( '   Tutu Train - Aeon Tân An → TÀU TÂN AN', 'TÀU TÂN AN', $cs_( 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )' ) );
	teq( '   VR Fun Aeon Tân An → VR TÂN AN', 'VR TÂN AN', $cs_( 'VR Fun Aeon Tân An ( Dịch Vụ K&H )' ) );
	teq( '   TuTu Train - Lotte Gò Vấp → TÀU GÒ VẤP', 'TÀU GÒ VẤP', $cs_( 'TuTu Train - Lotte Gò Vấp ( Dịch vụ và Giải Trí K&H )' ) );
	teq( '   VR FUN - SC Vivo Q7 → SC VIVO', 'SC VIVO', $cs_( 'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )' ) );
	teq( '   ECO FARM LOTTE PHAN THIẾT → FARM PHAN THIẾT', 'FARM PHAN THIẾT', $cs_( 'ECO FARM LOTTE PHAN THIẾT ( Dịch Vụ K&H )' ) );
	$fz2 = $m2['FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )'];
	t( '   FUNZONE ADVENTURE GO AN LẠC: hai cơ sở cùng khớp → lấy nhiều từ hơn, cơ sở kia vào `khac` cho kế toán chốt', 'ADV GO! AN LẠC' === $fz2['coso'] && array( 'FUNZONE ADVENTURE' ) === $fz2['khac'], $fz2 );
	t( '   Aeon Bình Tân / COFFE GO AN LẠC: không có cơ sở → không gán bừa', ! isset( $m2['Tutu Train - Aeon Bình Tân ( Dịch Vụ và Giải Trí K&H )'] ) && ! isset( $m2['COFFE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )'] ), $m2 );
	t( '🔴 KHÔNG so chiều ngược: NHÀ MA AEON TÂN PHÚ / AEON MALL TÂN PHÚ không nuốt cửa hàng Tàu', ! in_array( 'NHÀ MA AEON TÂN PHÚ', array_column( $m2, 'coso' ), true ) && ! in_array( 'AEON MALL TÂN PHÚ', array_column( $m2, 'coso' ), true ), array_column( $m2, 'coso' ) );
}
$coso = array(
	array( 'ten' => 'FUNZONE AN LẠC', 'tenMisa' => 'FZ AN LAC', 'tenDoanhThu' => 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' ),
	array( 'ten' => 'Nhà Ma Phan Văn Trị', 'tenMisa' => '', 'tenDoanhThu' => '' ),
	array( 'ten' => 'VINCOM BÀ TRIỆU', 'tenMisa' => 'TTT VINCOM BA TRIEU', 'tenDoanhThu' => '' ),
);
$mx = VHCP_DoanhThu::anh_xa( array( 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 'NHÀ MA PHAN VĂN TRỊ', 'TuTu Train Vincom Bà Triệu', 'QUÁN LẠ CHƯA KHAI' ), $coso );
teq( '🔴 khai "Tên bên Doanh thu" → khớp chắc (khai)', array( 'coso' => 'FUNZONE AN LẠC', 'khop' => 'khai', 'khac' => array() ), $mx['FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )'] );
teq( '   khác hoa/thường, khác dấu → tự khớp theo tên thường gọi', array( 'coso' => 'Nhà Ma Phan Văn Trị', 'khop' => 'tu', 'khac' => array() ), $mx['NHÀ MA PHAN VĂN TRỊ'] );
teq( '   "TuTu Train Vincom Bà Triệu" → tự khớp VINCOM BÀ TRIỆU', array( 'coso' => 'VINCOM BÀ TRIỆU', 'khop' => 'tu', 'khac' => array() ), $mx['TuTu Train Vincom Bà Triệu'] );
t( '   quán lạ → không gán bừa', ! isset( $mx['QUÁN LẠ CHƯA KHAI'] ), $mx );

/* ── 4. Cột "Tên bên Doanh thu" ở bảng Cơ sở: lưu, đọc, giữ cũ khi không gửi ô ───────────────── */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'FUNZONE AN LẠC', 'maDonVi' => 'FZAL', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ AN LAC', 'tenDoanhThu' => 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' ),
	array( 'ten' => 'Nhà Ma Phan Văn Trị', 'maDonVi' => 'NMPVT', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'NHA MA PVT' ),
	array( 'ten' => 'KHO TỔNG', 'maDonVi' => 'KHO', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'KHO' ),
),
	'loaiChiPhi' => array( array( 'ten' => 'Chi phí cơ sở', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ) ),
	'users' => array( array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ), array( 'ten' => 'Kế Toán A', 'pin' => '2222', 'vaiTro' => 'Kế toán cá nhân' ) ),
) );
VHCP_Cfg::clear_cache();
$cs = VHCP_Cfg::get_config()['coso'];
teq( '   đọc lại: tenDoanhThu đã lưu', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', $cs[0]['tenDoanhThu'] );
teq( '   không khai → rỗng', '', $cs[1]['tenDoanhThu'] );
VHCP_Cfg::save_config( array( 'coso' => array( array( 'ten' => 'FUNZONE AN LẠC', 'maDonVi' => 'FZAL', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ AN LAC' ) ) ) );
VHCP_Cfg::clear_cache();
$cs = VHCP_Cfg::get_config()['coso'];
$fz = null; foreach ( $cs as $x ) { if ( 'FUNZONE AN LẠC' === $x['ten'] ) { $fz = $x; } }
teq( '🔴 giao diện cũ không gửi ô → GIỮ tên bên Doanh thu', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', $fz ? $fz['tenDoanhThu'] : null );
VHCP_Cfg::save_config( array( 'coso' => array( array( 'ten' => 'FUNZONE AN LẠC', 'maDonVi' => 'FZAL', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ AN LAC', 'tenDoanhThu' => '' ) ) ) );
VHCP_Cfg::clear_cache();
$cs = VHCP_Cfg::get_config()['coso'];
$fz = null; foreach ( $cs as $x ) { if ( 'FUNZONE AN LẠC' === $x['ten'] ) { $fz = $x; } }
teq( '   gửi rỗng có chủ ý → xoá', '', $fz ? $fz['tenDoanhThu'] : null );
/* Admin không bị bó đơn vị → Lưu là ghi đè cả bảng, nên trả lại đủ bốn cơ sở trước khi so sánh. */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'FUNZONE AN LẠC', 'maDonVi' => 'FZAL', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ AN LAC', 'tenDoanhThu' => 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' ),
	array( 'ten' => 'Nhà Ma Phan Văn Trị', 'maDonVi' => 'NMPVT', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'NHA MA PVT' ),
	array( 'ten' => 'KHO TỔNG', 'maDonVi' => 'KHO', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'KHO' ),
	array( 'ten' => 'VINCOM BÀ TRIỆU', 'maDonVi' => 'VBT', 'phanLoaiLon' => 'MTĐ MB', 'tenMisa' => 'TTT VINCOM BA TRIEU' ),
) ) );
VHCP_Cfg::clear_cache();

/* ── 5. So sánh cả tháng: thực chi theo ngày dòng, đơn chưa cấp tiền = 0, gian không doanh thu vẫn bày ── */
$mk = function ( $coso, $ngay, $tien, $thuc = null ) {
	$d = VHCP_Don::create_don( 'T9/2026 (21/9-27/9/2026)', 'Kế Toán A' );
	$m = $d['maDon'];
	$l = VHCP_Don::add_line( $m, array( 'coso' => $coso, 'ngay' => $ngay, 'phanLoaiTT' => 'Thanh toán cá nhân',
		'nhom' => 'Chi phí cơ sở', 'noiDung' => 'x', 'soLuong' => 1, 'donGia' => $tien, 'thanhTien' => $tien ) );
	return array( $m, $l );
};
list( $m1 ) = $mk( 'FUNZONE AN LẠC', '2026-09-10', 40000000 );
list( $m2 ) = $mk( 'FUNZONE AN LẠC', '2026-08-31', 9000000 );      // tháng trước → không tính
list( $m3 ) = $mk( 'Nhà Ma Phan Văn Trị', '2026-09-12', 5000000 ); // để Nháp → chưa cấp tiền → 0
list( $m4 ) = $mk( 'KHO TỔNG', '2026-09-15', 7000000 );            // không có doanh thu
global $wpdb;
$t_don = VHCP_DB::t( 'don' );
foreach ( array( $m1, $m2, $m4 ) as $mm ) { $wpdb->query( $wpdb->prepare( "UPDATE $t_don SET trang_thai=%s WHERE ma_don=%s", 'Đã cấp tạm ứng', $mm ) ); }

$kq = VHCP_DoanhThu::so_sanh( array( 'thang' => '2026-09', 'tuoi' => true ) );
t( '   so_sanh chạy', ! empty( $kq['success'] ), $kq );
teq( '   tu/den của tháng', array( '2026-09-01', '2026-09-30' ), array( $kq['tu'], $kq['den'] ) );
$hang = array(); foreach ( $kq['rows'] as $r ) { $hang[ $r['cuaHang'] !== '' ? $r['cuaHang'] : $r['coso'] ] = $r; }
$fz = $hang['FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )'];
teq( '🔴 FUNZONE: chi phí chỉ dòng trong tháng 9 (40tr), không lẫn 31/8', 40000000.0, (float) $fz['chiPhi'] );
teq( '   FUNZONE: doanh thu 120tr, %CP/DT = 33.3', array( 120000000.0, 33.3 ), array( (float) $fz['doanhThu'], $fz['tyLe'] ) );
teq( '   FUNZONE khớp theo cột khai', 'khai', $fz['khop'] );
$nm = $hang['NHÀ MA PHAN VĂN TRỊ'];
teq( '🔴 Nhà Ma: đơn Nháp chưa cấp tiền → chi phí 0', 0, (int) $nm['chiPhi'] );
teq( '   Nhà Ma tự khớp', 'tu', $nm['khop'] );
t( '🔴 KHO TỔNG có chi phí mà không có doanh thu → VẪN BÀY (dt 0, cp 7tr)', isset( $hang['KHO TỔNG'] ) && 7000000.0 === (float) $hang['KHO TỔNG']['chiPhi'] && 0.0 === (float) $hang['KHO TỔNG']['doanhThu'], $kq['rows'] );
teq( '   quán lạ vào danh sách chưa khớp', array( 'QUÁN LẠ CHƯA KHAI' ), $kq['cuaHangChuaKhop'] );
t( '🔴 dòng gộp "Toàn hệ thống" và quán không có lấy một hoá đơn → KHÔNG bày', ! isset( $hang['Toàn hệ thống'] ) && ! isset( $hang['QUÁN ĐÓNG KHÔNG SỐ'] ), array_keys( $hang ) );
t( '   mỗi hàng có `khac` (mảng)', is_array( $fz['khac'] ) && is_array( $hang['KHO TỔNG']['khac'] ), $fz );
teq( '   tổng doanh thu / chi phí', array( 201000000.0, 47000000.0 ), array( (float) $kq['tongDoanhThu'], (float) $kq['tongChiPhi'] ) );
t( '   danh sách cửa hàng đi kèm (cho ô gợi ý ở bảng Cơ sở)', 6 === count( $kq['cuaHangDs'] ), $kq['cuaHangDs'] );
$kq = VHCP_DoanhThu::so_sanh( array( 'thang' => 'rác' ) );
teq( '   tháng sai dạng → tháng hiện tại', '2026-09', $kq['thang'] );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: kết nối web Doanh thu, khớp tên, so sánh doanh thu vs chi phí theo cơ sở.\n";
