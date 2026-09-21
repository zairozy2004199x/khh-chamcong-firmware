<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LOẠI CHI PHÍ TÍCH THEO VAI TRÒ — AI ĐƯỢC DÙNG LOẠI NÀY.
 *
 * Anh Thắng 21/09/2026: *"bỏ tích bộ phận đi, mà tích theo vai trò"*, và *"cho full danh sách
 * vai trò, để ai làm anh tích vào"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CHUYỂN, KHÔNG PHẢI CHỈ ĐỔI NHÃN
 * =============================================================================================
 * Cột Bộ phận vừa rời khỏi bảng Người dùng (1.232.0), nên `bo_phan_bo()` đọc ra một giá trị
 * KHÔNG AI SỬA ĐƯỢC NỮA. Cổng `xem_duoc_loai()` mà cứ lọc bằng nó thì nó lọc bằng dữ liệu cũ,
 * vô hình, và không có đường vào để chữa — kiểu hỏng tệ nhất.
 *
 * ⚠️ BA CHỐT PHẢI GIỮ, cả ba đều im lặng nếu sai:
 *   1. CHƯA TÍCH VAI NÀO = MỌI VAI. Danh mục dựng từ sổ cũ, gần như mọi dòng còn trống. Hiểu
 *      ngược lại là ngày bản này lên, kế toán mở màn ra thấy gần như trắng.
 *   2. SO BẰNG VAI ĐANG MANG, KHÔNG PHẢI VAI GỐC. Anh Thắng khai vai con rất cụ thể ("Kế Toán
 *      Máy Tự Động"); quy về vai gốc là cả nhánh nhìn thấy sổ của nhau.
 *   3. ADMIN KHÔNG BAO GIỜ BỊ LỌC.
 *
 * 🔴 VÀ CỘT BỘ PHẬN CŨ KHÔNG ĐƯỢC XOÁ khỏi sổ — anh bảo thôi dùng, chưa bảo xoá dữ liệu.
 *
 * Chạy: php tools/test/kiem-loai-theo-vai.php
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

/* ═══ 1. SƠ ĐỒ CỘT ════════════════════════════════════════════════════════════════ */
$hd = VHCP_Cfg::headers( VHCP_Cfg::LOAI );
teq( 'danh mục loại chi phí có 11 cột', 11, count( $hd ) );
teq( '🔴 cột 11 là Vai trò', 'Vai trò', $hd[10] );
teq( '🔴 cột 5 VẪN là Bộ phận — thôi dùng chứ không xoá', 'Bộ phận', $hd[4] );

/* ═══ 2. GHI RỒI ĐỌC LẠI ═════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Kế Toán Máy Tự Động', 'Kế toán cá nhân' ),
	array( 'Kế Toán Khu Vui Chơi', 'Kế toán cá nhân' ),
	array( 'Nhân Viên Cơ Sở Khu Vui Chơi', 'Nhân viên' ),
), false );
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Sửa máy gắp thú', 'tkNo' => '6427', 'khoi' => 'kvc',
	       'boPhan' => 'Máy tự động', 'vaiTro' => 'Kế Toán Máy Tự Động, Nhân Viên Cơ Sở Khu Vui Chơi' ),
	array( 'ten' => 'Chi phí khác', 'tkNo' => '6428', 'khoi' => 'kvc', 'boPhan' => '', 'vaiTro' => '' ),
) ) );
VHCP_Cfg::clear_cache();
$ds = array();
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { $ds[ $x['ten'] ] = $x; }
teq( '🔴 ô tích vai sống sót qua lượt lưu',
	'Kế Toán Máy Tự Động, Nhân Viên Cơ Sở Khu Vui Chơi', $ds['Sửa máy gắp thú']['vaiTro'] );
teq( '🔴 và ô Bộ phận cũ KHÔNG bị xoá', 'Máy tự động', $ds['Sửa máy gắp thú']['boPhan'] );
teq( '   loại không tích vai thì để trống', '', $ds['Chi phí khác']['vaiTro'] );
/* `loai_tk()` là cửa mọi nơi tra vào — nó phải mang theo ô ấy, không thì `loai_thuoc_vai()`
   lúc nào cũng thấy rỗng và mọi vai đều qua. */
teq( '🔴 `loai_tk()` trả về ô vai trò',
	'Kế Toán Máy Tự Động, Nhân Viên Cơ Sở Khu Vui Chơi', VHCP_Cfg::loai_tk( 'Sửa máy gắp thú' )['vaiTro'] );

/* ═══ 3. LUẬT DÙNG ĐƯỢC ══════════════════════════════════════════════════════════ */
t( 'vai có trong danh sách thì dùng được',
	VHCP_Cfg::loai_thuoc_vai( 'Sửa máy gắp thú', 'Kế Toán Máy Tự Động' ) );
t( '   tên thứ hai trong danh sách cũng vậy',
	VHCP_Cfg::loai_thuoc_vai( 'Sửa máy gắp thú', 'Nhân Viên Cơ Sở Khu Vui Chơi' ) );
t( '🔴 vai KHÔNG có trong danh sách thì KHÔNG dùng được',
	! VHCP_Cfg::loai_thuoc_vai( 'Sửa máy gắp thú', 'Kế Toán Khu Vui Chơi' ) );
/* 🔴 CHỐT 2: quy về vai gốc là cả nhánh nhìn thấy sổ của nhau. */
t( '🔴 VAI GỐC của vai đã tích cũng KHÔNG tự động dùng được',
	! VHCP_Cfg::loai_thuoc_vai( 'Sửa máy gắp thú', 'Kế toán cá nhân' ) );
/* 🔴 CHỐT 1 */
t( '🔴 loại CHƯA tích vai nào thì MỌI vai dùng được',
	VHCP_Cfg::loai_thuoc_vai( 'Chi phí khác', 'Kế Toán Khu Vui Chơi' ) );
t( '   loại không có trong danh mục cũng cho qua (đừng giấu tiền)',
	VHCP_Cfg::loai_thuoc_vai( 'Loại lạ hoắc', 'Kế Toán Khu Vui Chơi' ) );
t( 'thừa khoảng trắng quanh tên vẫn nhận',
	VHCP_Cfg::loai_thuoc_vai( 'Sửa máy gắp thú', '  Kế Toán Máy Tự Động ' ) );

/* ═══ 4. CỔNG THẬT — ĐĂNG NHẬP RỒI HỎI ═══════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Kế Toán Máy Tự Động', 'Chị MTĐ' );
t( '🔴 người mang vai đã tích: đọc được', VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
VHCP_Auth::dat_vai_tro( 'Kế Toán Khu Vui Chơi', 'Chị KVC' );
t( '🔴 người mang vai KHÔNG tích: KHÔNG đọc được', ! VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
t( '   nhưng loại chưa tích ai thì vẫn đọc được', VHCP_Auth::xem_duoc_loai( 'Chi phí khác' ) );
/* 🔴 CHỐT 3 */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
t( '🔴 Admin đọc được mọi loại, kể cả loại đã tích cho vai khác',
	VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );

/* 🔴 CỔNG KHÔNG CÒN ĐỌC BỘ PHẬN. Ô ấy nay không ai sửa được — lọc bằng nó là lọc bằng dữ liệu
   cũ, vô hình. Đặt một bộ phận "sai" rồi đòi nó KHÔNG ảnh hưởng gì. */
VHCP_Auth::dat_vai_tro( 'Kế Toán Máy Tự Động', 'Chị MTĐ', '', 'Marketing' );
t( '🔴 ô Bộ phận cũ (Marketing) KHÔNG còn cắt gì nữa', VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
$src = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-auth.php' );
$i0  = mb_strpos( $src, 'public static function xem_duoc_loai(' );
$than = false === $i0 ? '' : mb_substr( $src, $i0, mb_strpos( $src, "\n\t}", $i0 ) - $i0 );
$sach = (string) preg_replace( '#/\*.*?\*/#su', '', $than );
t( '🔴 `xem_duoc_loai()` KHÔNG còn gọi `bo_phan_bo()`',
	false === mb_strpos( $sach, 'bo_phan_bo()' ), $sach );
t( '   mà gọi `loai_thuoc_vai()`', false !== mb_strpos( $sach, 'loai_thuoc_vai' ) );

/* ═══ 5. GÓI KHỞI ĐỘNG MANG Ô TÍCH XUỐNG MÀN ═════════════════════════════════════
 * Ô CHỌN loại chi phí lúc nhập đơn dựng ngay ở màn từ `BOOT.loaiChiPhi`. Không gửi ô này
 * xuống là màn bày đủ mọi loại cho mọi vai trong khi máy chủ thì lọc — người nhập chọn được
 * thứ mà sổ của chính họ không hiện. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
$b = VHCP_Don::get_bootstrap();
$b = isset( $b['data'] ) ? $b['data'] : $b;
$bl = array();
foreach ( (array) $b['loaiChiPhi'] as $x ) { $bl[ $x['ten'] ] = $x; }
t( '🔴 gói khởi động mang ô `vaiTro` của từng loại', isset( $bl['Sửa máy gắp thú']['vaiTro'] ), $bl['Sửa máy gắp thú'] );
teq( '   và đúng giá trị đã khai',
	'Kế Toán Máy Tự Động, Nhân Viên Cơ Sở Khu Vui Chơi', $bl['Sửa máy gắp thú']['vaiTro'] );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: loại chi phí lọc theo vai trò, trống = mọi vai, Admin không bị lọc.\n";
