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

/* ⚠️ `$DAT`/`$TRUOT` PHẢI KHỞI ĐỘNG TRƯỚC PHÉP THỬ ĐẦU TIÊN. Hàm `t()`/`teq()` là khai báo hàm
   toàn cục nên PHP NÂNG nó lên đầu tệp lúc biên dịch — gọi được từ bất cứ đâu trong tệp này,
   kể cả trước dòng khai báo. Nhưng `$DAT = 0; $TRUOT = array();` là CÂU LỆNH GÁN, chạy tuần
   tự — đặt nó SAU vài phép thử là các phép ấy ghi vào biến toàn cục chưa tồn tại (PHP coi là
   null rồi tự tăng), để rồi bị chính dòng gán này XOÁ SẠCH ngay sau đó. Kết quả: phép thử vẫn
   "chạy" nhưng không bao giờ được ĐẾM — bài kiểm luôn báo sạch dù mã có hỏng. Cắn thật khi vá
   `doi_gian()`: bỏ gác `tai_cho()` mà bài kiểm vẫn xanh, chỉ vì phép gác cảnh ấy nằm trước dòng
   khởi tạo này. */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

/* 🔴 CHƯA `tai_cho()` (đúng cảnh site chạy riêng như `vhcp-chi-phi-hn`) → `doi_gian()` KHÔNG được
   đụng gì tới DB doanh thu, và `list_du_an()` phải chạy y như trước khi tính năng này tồn tại.
   Phải gác NGAY ĐÂY — `khh_dt_bang()` chỉ được định nghĩa ở mục 6 bên dưới; PHP không có cách
   "undefine" một hàm toàn cục giữa bài kiểm để giả lập lại cảnh này sau đó. */
t( '🔴 (tiền đề) chưa có khh_dt_bang() → tai_cho() = false', ! VHCP_DoanhThu::tai_cho() );
global $wpdb;
delete_transient( 'vhcp_cfgstatic' );
$q_truoc = $wpdb->q_count;
VHCP_DoanhThu::doi_gian( array( array( 'maDA' => 'X', 'ten' => 'GIAN THỬ Q_COUNT', 'loai' => 'Setup lắp đặt' ) ) );
t( '🔴 chưa tai_cho(): doi_gian() thoát NGAY — không tốn một lệnh DB nào (không dò cơ sở, không đọc gì)',
	$wpdb->q_count === $q_truoc, 'lệch ' . ( $wpdb->q_count - $q_truoc ) . ' lệnh' );
$d_som = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'GIAN THỬ TRƯỚC KHI CÓ DOANH THU', 'KT' );
$lda_som = VHCP_DuAn::list_du_an();
$x_som = null; foreach ( $lda_som['items'] as $y ) { if ( $y['maDA'] === $d_som['maDA'] ) { $x_som = $y; } }
t( '🔴 chưa tai_cho(): list_du_an() vẫn chạy, KHÔNG có trường dtNoi nào bị thêm', $x_som && ! isset( $x_som['dtNoi'] ) && ! isset( $x_som['doanhThu'] ), $x_som );

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
teq( '   san=true, khoaCo=true, url (Admin) bỏ dấu / cuối, chưa đọc thẳng (test không có Doanh thu)',
	array( 'san' => true, 'url' => 'https://khmatrix.com/doanh-thu-hcm', 'khoaCo' => true, 'taiCho' => false ), $ch );
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

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. ĐỌC THẲNG CÙNG WORDPRESS (`tai_cho()`) — Chấm công/Doanh thu/Chi phí chạy chung một site.
 * Anh Thắng 25/09/2026, sau khi 1.326.0 cài xong: *"anh vẫn chưa thấy doanh thu theo cơ sở qua"*.
 * Lý do: đường HTTP cần cài thêm bản Doanh thu (chưa từng đóng gói). Cùng WordPress thì đọc
 * thẳng bảng `khh_dt_ngay`, không cần địa chỉ, không cần khoá, không gọi mạng.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'khh_dt_bang' ) ) { function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt_ngay'; } }
if ( ! function_exists( 'khh_dt_slug' ) ) { function khh_dt_slug() { return 'doanh-thu-hcm'; } }
global $wpdb;
$wpdb->query( "CREATE TABLE {$wpdb->prefix}khh_dt_ngay (id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT, cua_hang TEXT, doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, so_hd INTEGER DEFAULT 0)" );
$them_dt = function ( $ngay, $ch, $dt ) use ( $wpdb ) {
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}khh_dt_ngay (ngay,cua_hang,doanh_thu,thanh_tien,so_hd) VALUES (%s,%s,%f,%f,%d)", $ngay, $ch, $dt, $dt, 1 ) );
};
$them_dt( '2026-06-01', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 10000000 );
$them_dt( '2026-07-01', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 12000000 );
$them_dt( '2026-09-01', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 8000000 );
$them_dt( '2026-05-10', 'TAU ESTELLA', 5000000 );
$them_dt( '2026-08-01', 'TAU ESTELLA', 20000000 );        // SAU ngày đóng 15/07 — phải bị loại
$them_dt( '2026-06-15', 'FUNZONE VUNG TAU POS', 3000000 );
/* 🔴 MỘT DÒNG "CỬA HÀNG RỖNG" THẬT trong sổ — mô phỏng dữ liệu POS lỗi. Cơ sở chưa khai "Tên
   bên Doanh thu" (`tenDoanhThu` rỗng) TUYỆT ĐỐI không được vô tình gộp vào dòng rỗng này. */
$them_dt( '2026-01-01', '', 999999999 );

t( '🔴 tai_cho() = true khi có khh_dt_bang() (cùng WordPress với Doanh thu)', VHCP_DoanhThu::tai_cho() );
$ch_tc = VHCP_DoanhThu::cau_hinh();
t( '🔴 cau_hinh(): taiCho=true và san=true — dù url/khoá đã khai hay chưa', $ch_tc['taiCho'] && $ch_tc['san'], $ch_tc );

$luot_get_truoc = count( $GLOBALS['VHCP_DA_GET'] );
$kq_tc = VHCP_DoanhThu::goi( '2026-01-01', '2026-12-31' );
t( '🔴 goi() đọc thẳng: ok=true và KHÔNG gọi ra mạng (đếm GET không đổi)',
	! empty( $kq_tc['ok'] ) && count( $GLOBALS['VHCP_DA_GET'] ) === $luot_get_truoc, $kq_tc );
teq( '   nguồn = tai_cho', 'tai_cho', $kq_tc['nguon'] );
/* 🔴 `san` KHÔNG ĐƯỢC PHỤ THUỘC url/khoá khi đã tai_cho — xoá địa chỉ đi, san vẫn phải true. */
VHCP_Cfg::save_config( array( 'doanhThu' => array( 'url' => '', 'khoa' => '' ) ) );
$ch_khong_url = VHCP_DoanhThu::cau_hinh();
t( '🔴 tai_cho(): san=true dù KHÔNG có địa chỉ/khoá nào', ! empty( $ch_khong_url['san'] ) && $ch_khong_url['taiCho'], $ch_khong_url );
VHCP_Cfg::save_config( array( 'doanhThu' => array( 'url' => 'https://khmatrix.com/doanh-thu-hcm', 'khoa' => 'KHOA-BI-MAT-1234567890' ) ) );
$fz_kq = null; foreach ( $kq_tc['cuaHang'] as $x ) { if ( 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' === $x['ten'] ) { $fz_kq = $x; } }
teq( '   tổng doanh thu FUNZONE trong khoảng gọi', 30000000.0, $fz_kq ? $fz_kq['doanhThu'] : null );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 7. DOANH THU ĐỜI GIAN CHO KHỐI KỸ THUẬT (`VHCP_DoanhThu::doi_gian()`, gọi từ `list_du_an()`).
 * Anh Thắng: *"Từ ngày Setup đến Tháo dỡ (Khuyến nghị)"* · *"Anh/kế toán chọn một lần"* (cột
 * "Tên bên Doanh thu" đã có, dùng lại — không dựng ô chọn mới).
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'FUNZONE AN LẠC', 'maDonVi' => 'FZAL', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ AN LAC', 'tenDoanhThu' => 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )' ),
	array( 'ten' => 'Nhà Ma Phan Văn Trị', 'maDonVi' => 'NMPVT', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'NHA MA PVT' ),
	array( 'ten' => 'KHO TỔNG', 'maDonVi' => 'KHO', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'KHO' ),
	array( 'ten' => 'VINCOM BÀ TRIỆU', 'maDonVi' => 'VBT', 'phanLoaiLon' => 'MTĐ MB', 'tenMisa' => 'TTT VINCOM BA TRIEU' ),
	/* 🔴 CƠ SỞ TRÙNG RÚT GỌN, ĐỨNG TRƯỚC — "TÀU-ESTELLA" (gạch nối) rút gọn cũng ra "tau estella"
	   y hệt "TÀU ESTELLA" bên dưới, nhưng KHÔNG PHẢI cùng một gian (tên khai khác chữ). Khớp
	   ĐÚNG TÊN phải thắng khớp rút gọn — không thì dự án "TÀU ESTELLA" (khớp đúng, có sẵn) lại
	   bị gán nhầm sang cơ sở đứng trước chỉ vì rút gọn trùng, và tenDoanhThu của nó chưa seed
	   doanh thu nên sẽ lộ ra ngay bằng "chua_noi" sai. */
	array( 'ten' => 'TÀU-ESTELLA', 'maDonVi' => 'TEX', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'TAU-ESTELLA', 'tenDoanhThu' => 'POS_TAU_ESTELLA_SAI' ),
	/* 🔴 GIAN ĐÃ ĐÓNG — doanh thu SAU ngày đóng KHÔNG được tính vào "cả đời gian". */
	array( 'ten' => 'TÀU ESTELLA', 'maDonVi' => 'TE', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'TAU ESTELLA', 'tenDoanhThu' => 'TAU ESTELLA', 'dongCua' => '15/07/2026' ),
	/* Cơ sở CHƯA khai "Tên bên Doanh thu" — dự án khớp tên nhưng doanh thu phải nói "chưa nối". */
	array( 'ten' => 'NHÀ MA CHƯA NỐI', 'maDonVi' => 'NMCN', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'NHA MA CHUA NOI' ),
	/* Tên khác DẤU / HOA-THƯỜNG với dự án — phải khớp qua `rut_gon()`, không khớp ĐÚNG chữ. */
	array( 'ten' => 'FUNZONE VŨNG TÀU', 'maDonVi' => 'FVT', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ VT', 'tenDoanhThu' => 'FUNZONE VUNG TAU POS' ),
) ) );
VHCP_Cfg::clear_cache();

$d_setup_khai   = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'FUNZONE AN LẠC', 'KT' );
$d_thao_dong    = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'TÀU ESTELLA', 'KT' );
$d_chua_noi     = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'NHÀ MA CHƯA NỐI', 'KT' );
$d_khong_coso   = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'GIAN LẠ KHÔNG CÓ TRONG SỔ', 'KT' );
$d_rut_gon      = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Funzone Vung Tau', 'KT' );   // không dấu — rut_gon() mới khớp
$d_trung_1      = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'DUP GIAN', 'KT' );
$d_trung_2      = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'DUP GIAN', 'KT' );

$lda = VHCP_DuAn::list_du_an();
t( '   list_du_an() vẫn chạy', ! empty( $lda['success'] ), $lda );
$theo_ma = array(); foreach ( $lda['items'] as $x ) { $theo_ma[ $x['maDA'] ] = $x; }

$x = $theo_ma[ $d_setup_khai['maDA'] ];
teq( '🔴 gian còn mở: doanh thu = cả đời tới hôm nay (10tr+12tr+8tr)', 30000000.0, $x['doanhThu'] );
teq( '   dtTu = ngày đầu có doanh thu', '2026-06-01', $x['dtTu'] );
teq( '   dtNoi = khai', 'khai', $x['dtNoi'] );
teq( '   dtCuaHang = đúng tên bên POS', 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', $x['dtCuaHang'] );

$x = $theo_ma[ $d_thao_dong['maDA'] ];
teq( '🔴 gian đã đóng: doanh thu CẮT ở ngày đóng — chỉ tính 5tr trước 15/07, bỏ 20tr sau đó', 5000000.0, $x['doanhThu'] );
teq( '   dtDen = đúng ngày đóng', '2026-07-15', $x['dtDen'] );

$x = $theo_ma[ $d_chua_noi['maDA'] ];
teq( '🔴 cơ sở chưa khai "Tên bên Doanh thu" → dtNoi = chua_noi, KHÔNG đoán số', 'chua_noi', $x['dtNoi'] );
t( '   và không có trường doanhThu nào bị bịa ra', ! isset( $x['doanhThu'] ), $x );
t( '🔴 và chắc chắn KHÔNG lấy nhầm dòng "cửa hàng rỗng" (999.999.999) ở sổ',
	! isset( $x['doanhThu'] ) || 999999999.0 !== (float) $x['doanhThu'], $x );

$x = $theo_ma[ $d_khong_coso['maDA'] ];
teq( '🔴 tên gian không khớp cơ sở nào → dtNoi = khong_coso', 'khong_coso', $x['dtNoi'] );

$x = $theo_ma[ $d_rut_gon['maDA'] ];
teq( '🔴 tên khác dấu/hoa-thường khớp qua rut_gon() — không phải đoán mò', 'khai', $x['dtNoi'] );
teq( '   và lấy đúng doanh thu của FUNZONE VUNG TAU POS', 3000000.0, $x['doanhThu'] );

teq( '🔴 hai dự án trùng tên đều được đánh dấu trungTen', array( true, true ),
	array( $theo_ma[ $d_trung_1['maDA'] ]['trungTen'], $theo_ma[ $d_trung_2['maDA'] ]['trungTen'] ) );
t( '   dự án tên riêng không bị đánh dấu trùng', empty( $theo_ma[ $d_setup_khai['maDA'] ]['trungTen'] ), $theo_ma[ $d_setup_khai['maDA'] ] );

/* 🔴 MỘT DÒNG DỮ LIỆU HỎNG KHÔNG ĐƯỢC LÀM SẬP list_du_an() — `doi_gian()` phải NUỐT lỗi.
   Ép một `stdClass` vào `ten` (không có __toString()): ép kiểu `(string)` trong vòng lặp sẽ
   ném `\Error`. Không có `try/catch` thì cả `list_du_an()` — thứ nhiều màn khác đang dùng —
   sẽ trắng trang chỉ vì MỘT dòng doanh thu hỏng. */
$hong = array( array( 'maDA' => 'DA_HONG', 'ten' => new stdClass(), 'loai' => 'Setup lắp đặt' ) );
$ra_hong = null; $nem_ra = false;
try { $ra_hong = VHCP_DoanhThu::doi_gian( $hong ); } catch ( \Throwable $e ) { $nem_ra = true; }
t( '🔴 doi_gian() với dữ liệu hỏng: KHÔNG ném lỗi ra ngoài', ! $nem_ra, $nem_ra );
t( '   và vẫn trả về một mảng (không vỡ list_du_an() đang gọi nó)', is_array( $ra_hong ), $ra_hong );

/* ⚠️ Chi phí cơ sở KHÔNG phải một "gian" để so doanh thu — đi qua nguyên vẹn, không có dtNoi. */
$cpcs = VHCP_DuAn::ensure_co_so_chung( 'KT' );
$lda2 = VHCP_DuAn::list_du_an();
$cpcs_item = null; foreach ( $lda2['items'] as $x ) { if ( 'Chi phí cơ sở' === $x['loai'] ) { $cpcs_item = $x; } }
t( '⚠️ dòng Chi phí cơ sở không có dtNoi (không phải một gian)', $cpcs_item && ! isset( $cpcs_item['dtNoi'] ), $cpcs_item );

/* ⚠️ SITE CHẠY RIÊNG (vd `vhcp-chi-phi-hn`, không cùng WordPress với Doanh thu): `tai_cho()`
   trả `false` NGAY TỪ ĐẦU tệp này (trước khi `khh_dt_bang()` được định nghĩa ở mục 6) — phép
   `teq('   san=true, khoaCo=true, url (Admin) bỏ dấu / cuối, chưa đọc thẳng…', …, 'taiCho'=>false)`
   ở mục 1 CHÍNH LÀ phép gác cảnh đó: `doi_gian()` phải trả nguyên `$items`, không đổ `list_du_an()`,
   và không bịa `dtNoi` cho ai — PHP không cho "undefine" một hàm toàn cục giữa bài kiểm (không có
   runkit ở PHP 8), nên cảnh ấy được gác ở ĐẦU tệp, lúc hàm còn chưa tồn tại, chứ không giả lập lại
   ở đây. */


if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: kết nối web Doanh thu, khớp tên, so sánh doanh thu vs chi phí theo cơ sở, đọc thẳng cùng WordPress, doanh thu đời gian cho khối Kỹ thuật.\n";
