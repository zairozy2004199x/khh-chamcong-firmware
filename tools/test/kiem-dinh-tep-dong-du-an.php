<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐÍNH ẢNH / HỒ SƠ THẲNG VÀO MỘT DÒNG DỰ ÁN.
 *
 * Anh Thắng: *"cho phép thêm ảnh và đính kèm cả hồ sơ trực tiếp tại trang"*.
 *
 * =============================================================================================
 * 🔴 ĐỔI ĐÚNG MỘT Ô, KHÔNG GHI ĐÈ CẢ DÒNG. Đi qua `update_line` với một bản ghi dựng lại từ màn
 *    là mọi ô khác đi qua đường ghi lại — ô nào màn đọc thiếu thì bị xoá trắng, im lặng. Tiền,
 *    loại chi phí, mã tài khoản đều nằm trong số "ô khác" ấy.
 *
 * 🔴 HỒ SƠ CỘNG THÊM, ẢNH THAY. Một dòng có nhiều hồ sơ (hợp đồng, biên bản, báo giá) nhưng chỉ
 *    một tấm bill; đính hồ sơ thứ hai mà đè mất cái thứ nhất là mất chứng từ.
 *
 * 🔴 HẠNG MỤC ĐÃ CHỐT LÀ KHOÁ. Đổi chứng từ của một khoản kế toán đã hạch toán là đổi thứ đỡ cho
 *    con số ấy, sau lưng họ. Chốt này áp cho cả MỤC CON của hạng mục đã chốt.
 *
 * ⚠️ CHẠY THẬT trên dữ liệu thật.
 *
 * Chạy: php tools/test/kiem-dinh-tep-dong-du-an.php
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
	foreach ( VHCP_DuAn::get_du_an( $ma )['lines'] as $l ) { if ( $l['noiDung'] === $ten ) { return $l; } }
	return null;
}

vai( 'Admin', 'KT' );
$r  = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thử đính tệp', 'NV' );
$ma = $r['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Mua đồ điện', 'duToan' => 0 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Bóng đèn', 'capCha' => 'Mua đồ điện',
	'thucTe' => 2000000, 'soLuong' => 1, 'donGia' => 2000000, 'vat' => 'Có VAT',
	'loaiCp' => 'Chi phí tháo dỡ', 'tkNo' => '64125', 'note' => 'ghi chú' ) );
$con = dong( $ma, 'Bóng đèn' );
$cha = dong( $ma, 'Mua đồ điện' );
t( 'dựng được dữ liệu thử', $con && $cha, array( $con, $cha ) );

/* ═══ 1. ĐÍNH ẢNH ═══════════════════════════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_anh_line( $ma, $con['row'], 'https://kho/bill-1.jpg' );
t( 'đính ảnh được', ! empty( $x['success'] ), $x );
$sau = dong( $ma, 'Bóng đèn' );
teq( '   ảnh vào đúng dòng', 'https://kho/bill-1.jpg', $sau['anh'] );
/* 🔴 ĐÂY LÀ PHÉP QUAN TRỌNG NHẤT. Ghi đè cả dòng thì mấy ô này về rỗng mà không ai báo gì. */
t( '🔴 KHÔNG đụng tới tiền / số lượng / đơn giá',
	2000000 == $sau['thucTe'] && 1 == $sau['soLuong'] && 2000000 == $sau['donGia'], $sau );
t( '🔴 KHÔNG đụng tới loại chi phí và mã tài khoản',
	'Chi phí tháo dỡ' === $sau['loaiCp'] && '64125' === $sau['tkNo'], $sau );
t( '🔴 KHÔNG đụng tới VAT, ghi chú, và quan hệ cha con',
	'Có VAT' === $sau['vat'] && 'ghi chú' === $sau['note'] && 'Mua đồ điện' === $sau['capCha'], $sau );

$x = VHCP_DuAn::dat_anh_line( $ma, $con['row'], 'https://kho/bill-2.jpg' );
teq( '🔴 đính ảnh mới thì THAY ảnh cũ (một dòng một tấm bill)',
	'https://kho/bill-2.jpg', dong( $ma, 'Bóng đèn' )['anh'] );
$x = VHCP_DuAn::go_anh_line( $ma, $con['row'] );
t( 'gỡ ảnh được (đính nhầm thì phải gỡ được)', ! empty( $x['success'] ), $x );
teq( '   ô ảnh về rỗng', '', dong( $ma, 'Bóng đèn' )['anh'] );
teq( '   nhưng tiền vẫn nguyên', 2000000.0, (float) dong( $ma, 'Bóng đèn' )['thucTe'] );

/* ═══ 2. 🔴 HỒ SƠ CỘNG THÊM, KHÔNG ĐÈ ═══════════════════════════════════════════════════ */
VHCP_DuAn::them_ho_so_line( $ma, $con['row'], 'https://kho/hop-dong.pdf' );
VHCP_DuAn::them_ho_so_line( $ma, $con['row'], 'https://kho/bien-ban.pdf' );
$hs = preg_split( '/\s+/u', trim( (string) dong( $ma, 'Bóng đèn' )['hoSo'] ), -1, PREG_SPLIT_NO_EMPTY );
teq( '🔴 đính hai hồ sơ thì GIỮ CẢ HAI (đè là mất chứng từ)', 2, count( $hs ) );
t( '   và giữ đúng cả hai địa chỉ',
	in_array( 'https://kho/hop-dong.pdf', $hs, true ) && in_array( 'https://kho/bien-ban.pdf', $hs, true ), $hs );
VHCP_DuAn::them_ho_so_line( $ma, $con['row'], 'https://kho/hop-dong.pdf' );
$hs2 = preg_split( '/\s+/u', trim( (string) dong( $ma, 'Bóng đèn' )['hoSo'] ), -1, PREG_SPLIT_NO_EMPTY );
teq( '   đính LẠI đúng tệp đã có → không nhân đôi', 2, count( $hs2 ) );

/* ═══ 3. 🔴 ĐÃ CHỐT LÀ KHOÁ ═════════════════════════════════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $cha['row'] ) );
vai( 'Quản lý', 'QL' );  VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KT' ); VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-1' ) );
VHCP_DuAn::dat_hm( $ma, $cha['row'], 'xong', array( 'hoaDon' => 'https://hd/1' ) );
t( 'hạng mục lớn đã chốt', VHCP_DuAn::hm_khoa( $ma, $cha['row'] ) );

$x = VHCP_DuAn::dat_anh_line( $ma, $cha['row'], 'https://kho/khac.jpg' );
t( '🔴 hạng mục ĐÃ CHỐT → không đổi chứng từ được nữa', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_anh_line( $ma, $con['row'], 'https://kho/khac.jpg' );
t( '🔴 MỤC CON của hạng mục đã chốt cũng khoá (tiền của nó nằm trong khoản đã hạch toán)',
	empty( $x['success'] ), $x );
$x = VHCP_DuAn::them_ho_so_line( $ma, $con['row'], 'https://kho/them.pdf' );
t( '   hồ sơ cũng vậy', empty( $x['success'] ), $x );
teq( '   và hồ sơ cũ còn nguyên, không bị đụng nửa vời', 2,
	count( preg_split( '/\s+/u', trim( (string) dong( $ma, 'Bóng đèn' )['hoSo'] ), -1, PREG_SPLIT_NO_EMPTY ) ) );

/* Mở lại thì đính được — đó là đường sửa chính thức. */
VHCP_DuAn::dat_hm( $ma, $cha['row'], 'nhap' );
$x = VHCP_DuAn::dat_anh_line( $ma, $con['row'], 'https://kho/bill-3.jpg' );
t( 'kế toán mở lại hạng mục thì đính được', ! empty( $x['success'] ), $x );

/* ═══ 4. CA LỆCH ═══════════════════════════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_anh_line( $ma, 999, 'https://kho/x.jpg' );
t( 'dòng không có thật → chối, không nổ', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_anh_line( 'DA-KHONG-CO', 1, 'https://kho/x.jpg' );
t( 'dự án không có thật → chối', empty( $x['success'] ), $x );

/* ═══ 5. CỬA API ═══════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
foreach ( array(
	"'datAnhDuAnLine'"   => "array( 'VHCP_DuAn', 'dat_anh_line' )",
	"'goAnhDuAnLine'"    => "array( 'VHCP_DuAn', 'go_anh_line' )",
	"'themHoSoDuAnLine'" => "array( 'VHCP_DuAn', 'them_ho_so_line' )",
) as $k => $v ) {
	t( '🔴 ' . $k . ' đã khai vào cửa API', false !== strpos( $src, $k ) && false !== strpos( $src, $v ) );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: đính tệp đổi đúng một ô, và hạng mục đã chốt thì khoá.\n";
