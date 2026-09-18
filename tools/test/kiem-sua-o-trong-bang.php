<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GÕ THẲNG TRONG Ô CỦA BẢNG DỰ ÁN — MÁY CHỦ.
 *
 * Anh Thắng 18/09/2026: *"thay vì bấm sửa, thì cho sửa thẳng trong từng dòng đơn được không"*.
 * Bấm ✏️ là cả dòng nhảy lên form ở đầu trang; sửa một con số xong phải cuộn lại tìm chỗ cũ —
 * bảng mười mấy dòng thì mỗi lần sửa là một vòng đi về.
 *
 * =============================================================================================
 * 🔴 MỘT CỬA MỚI LÀ MỘT ĐƯỜNG VÒNG QUA MỌI CHỐT CŨ. Đây là chỗ nguy nhất của cả thay đổi: mở
 *    lối ghi thứ hai vào cùng một dòng, mà quên đem theo chốt "hạng mục đã chốt sổ" và chốt
 *    "dự toán đã lên lệnh" thì hai chốt ấy coi như không có — người ta chỉ cần đổi lối vào.
 *
 * 🔴 GHI ĐÚNG MỘT Ô. `update_line()` ghi lại CẢ DÒNG từ những gì màn gửi lên; đi nhờ nó thì gửi
 *    thiếu ô nào là ô ấy bị dọn về rỗng, im lặng. Đã cắn thật hôm nay ở một fixture.
 *
 * Chạy: php tools/test/kiem-sua-o-trong-bang.php
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
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }
function dong( $ma, $ten ) {
	foreach ( VHCP_DuAn::get_du_an( $ma )['lines'] as $l ) { if ( $ten === $l['noiDung'] ) { return $l; } }
	return null;
}

vai( 'Admin', 'KT' );
$ma = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian sửa ô', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Khách sạn', 'duToan' => 500000, 'note' => 'ghi cũ' ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Cáp', 'soLuong' => 2, 'donGia' => 500000 ) );
$rKS = (int) dong( $ma, 'Khách sạn' )['row'];
$rC  = (int) dong( $ma, 'Cáp' )['row'];
t( 'dựng được dữ liệu thử', $rKS > 0 && $rC > 0 );

/* ═══ 1. SỬA ĐƯỢC TỪNG Ô ══════════════════════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'duToan', 900000 );
t( 'sửa ô dự toán ngay trong bảng', ! empty( $x['success'] ), $x );
teq( '   số mới vào sổ', 900000.0, (float) dong( $ma, 'Khách sạn' )['duToan'] );

/* 🔴 CHỈ ĐỔI ĐÚNG Ô ẤY. Đây là cả lý do có hàm riêng: đi nhờ `update_line()` là mấy ô không
   được gửi lên bị dọn về rỗng, im lặng — ghi chú biến mất mà chẳng ai thấy. */
teq( '🔴 và KHÔNG đụng ô nào khác — ghi chú còn nguyên', 'ghi cũ', dong( $ma, 'Khách sạn' )['note'] );
teq( '   tên dòng cũng còn nguyên', 'Khách sạn', dong( $ma, 'Khách sạn' )['noiDung'] );

$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'note', 'ghi mới' );
t( 'sửa ô ghi chú', ! empty( $x['success'] ), $x );
teq( '   ghi chú đổi', 'ghi mới', dong( $ma, 'Khách sạn' )['note'] );
teq( '   mà dự toán không bị kéo theo', 900000.0, (float) dong( $ma, 'Khách sạn' )['duToan'] );

$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'thucTe', 850000 );
t( 'sửa ô chi phí thực tế', ! empty( $x['success'] ), $x );
teq( '   vào sổ', 850000.0, (float) dong( $ma, 'Khách sạn' )['thucTe'] );

/* 🔴 SỐ LƯỢNG / ĐƠN GIÁ ĐỔI THÌ THÀNH TIỀN PHẢI TÍNH LẠI NGAY. Để màn tự tính rồi gửi thêm một
   lượt nữa là có lúc lệch — và lệch ở cột tiền. */
$x = VHCP_DuAn::dat_o_line( $ma, $rC, 'donGia', 700000 );
t( 'sửa ô đơn giá', ! empty( $x['success'] ), $x );
teq( '🔴 thành tiền tính lại ngay trong cùng lượt ghi (2 × 700.000)', 1400000.0, (float) dong( $ma, 'Cáp' )['thanhTien'] );
$x = VHCP_DuAn::dat_o_line( $ma, $rC, 'soLuong', 5 );
teq( '🔴 đổi số lượng cũng vậy (5 × 700.000)', 3500000.0, (float) dong( $ma, 'Cáp' )['thanhTien'] );

/* ═══ 2. 🔴 CỬA MỚI PHẢI MANG THEO MỌI CHỐT CŨ ═══════════════════════════════════════════ */
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'hinhThuc', 'Trực tiếp' );
t( '🔴 ô KHÔNG nằm trong danh sách cho sửa thẳng → chối (hình thức chi kéo theo cả dây: mã tài '
	. 'khoản, đường tạm ứng)', empty( $x['success'] ), $x );
t( '   và chỉ đường: bấm ✏️ mở form', isset( $x['error'] ) && false !== mb_strpos( $x['error'], '✏️' ), $x );
foreach ( array( 'thanhTien', 'capCha', 'noiDung', 'vat', 'loaiCp' ) as $cot ) {
	$x = VHCP_DuAn::dat_o_line( $ma, $rKS, $cot, 'x' );
	t( '   "' . $cot . '" cũng không sửa thẳng được', empty( $x['success'] ), $x );
}

/* 🔴 CHỐT "DỰ TOÁN ĐÃ LÊN LỆNH" PHẢI ÁP CẢ Ở CỬA NÀY. Quên là chốt kia coi như không có —
   người ta chỉ cần bấm vào ô thay vì bấm ✏️. */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $rKS ), array(), '' );
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'duToan', 0 );
t( '🔴 hạng mục đã lên lệnh → gõ thẳng dự toán cũng bị CHỐI', empty( $x['success'] ), $x );
t( '   cùng một câu chối như lối form (một luật, một lời)',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'đã lên lệnh tạm ứng' ), $x );
teq( '   và sổ không đổi', 900000.0, (float) dong( $ma, 'Khách sạn' )['duToan'] );
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'soLuong', 9 );
t( '   số lượng cũng khoá theo', empty( $x['success'] ), $x );
/* Ô THỰC TẾ VẪN MỞ — cầm tiền đi tiêu rồi mới về ghi số thật. */
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'thucTe', 870000 );
t( '🔴 nhưng ô CHI PHÍ THỰC TẾ vẫn gõ được (khoá luôn thì không ai quyết toán được nữa)',
	! empty( $x['success'] ), $x );
teq( '   và vào sổ', 870000.0, (float) dong( $ma, 'Khách sạn' )['thucTe'] );
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'note', 'vẫn ghi được' );
t( '   ghi chú cũng vẫn gõ được', ! empty( $x['success'] ), $x );

/* 🔴 CHỐT "HẠNG MỤC ĐÃ CHỐT SỔ" cũng phải áp. */
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-1' ) );
VHCP_DuAn::dat_hm( $ma, $rKS, 'xong', array( 'hoaDon' => 'https://kho/hd.pdf' ) );
teq( 'hạng mục đã chốt & khoá', 'xong', VHCP_DuAn::hm_cua( $ma, $rKS )['tt'] );
$x = VHCP_DuAn::dat_o_line( $ma, $rKS, 'thucTe', 1 );
t( '🔴 hạng mục ĐÃ CHỐT SỔ → cả ô thực tế cũng khoá, kể cả ở cửa mới', empty( $x['success'] ), $x );
t( '   và nói đúng câu cũ: mở lại thì mới đụng được',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Mở lại' ), $x );
teq( '   sổ không đổi', 870000.0, (float) dong( $ma, 'Khách sạn' )['thucTe'] );

/* ═══ 3. MẤY CA LẶT VẶT ═══════════════════════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
VHCP_DuAn::them_dong_muc_con_cu( $ma, array( 'noiDung' => 'Ốc vít', 'capCha' => 'Cáp', 'thucTe' => 100000 ) );
$rCon = (int) dong( $ma, 'Ốc vít' )['row'];
$x = VHCP_DuAn::dat_o_line( $ma, $rCon, 'duToan', 50000 );
t( '🔴 mục con không có ô dự toán riêng → chối, và nói rõ dự toán nằm ở hạng mục lớn',
	empty( $x['success'] ) && isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'hạng mục lớn' ), $x );
$x = VHCP_DuAn::dat_o_line( $ma, $rCon, 'thucTe', 120000 );
t( '   nhưng thực tế của mục con thì gõ được (tiền của cha cộng từ đây)', ! empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_o_line( $ma, 9999, 'thucTe', 1 );
t( 'dòng không có thật → chối, không nổ', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_o_line( 'DA-KHONG-CO', 1, 'thucTe', 1 );
t( 'dự án không có thật → chối', empty( $x['success'] ), $x );
VHCP_DuAn::close( $ma );
$x = VHCP_DuAn::dat_o_line( $ma, $rC, 'thucTe', 1 );
t( '🔴 dự án ĐÃ ĐÓNG → chối (đóng là chốt sổ, không phải chỉ ẩn đi)', empty( $x['success'] ), $x );

/* ═══ 4. CỬA API ══════════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( "🔴 'datODuAnLine' đã khai vào cửa API (không khai thì màn bấm vào ô là im lặng)",
	false !== strpos( $src, "'datODuAnLine'" )
	&& false !== strpos( $src, "array( 'VHCP_DuAn', 'dat_o_line' )" ) );

VHCP_DuAn::delete( $ma );
/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: gõ thẳng trong bảng, một ô một lượt, và không lọt chốt nào.\n";
