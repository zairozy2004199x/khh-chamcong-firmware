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
vai( 'Admin', 'KT' );

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

/* ═══ 3b. 🔴 ĐÃ CHỐT THÌ KHÔNG XOÁ, KHÔNG SỬA DÒNG ════════════════════════════════════
 * Anh Thắng: *"Chốt xong bill quyết toán thì không cho xoá dòng"*.
 * "Đã chốt" nghĩa là hạng mục đã có hoá đơn và đã khoá là CHI THỰC TẾ; con số ấy có thể đã nằm
 * trong một lệnh quyết toán kế toán đã chốt sổ. Xoá một dòng con là tổng tiền tụt xuống sau
 * lưng kế toán, mà lệnh quyết toán vẫn ghi con số cũ — hai chỗ nói hai số cho cùng một khoản.
 *
 * ⚠️ SỬA CHẶN NHƯ XOÁ: để hở nút sửa thì gõ tiền về 0 là xoá trá hình, chỉ khác cái tên.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
VHCP_DuAn::dat_hm( $ma, $cha['row'], 'xong', array( 'hoaDon' => 'https://hd/1' ) );
t( 'hạng mục lớn đang ở trạng thái đã chốt', VHCP_DuAn::hm_khoa( $ma, $cha['row'] ) );

$x = VHCP_DuAn::delete_line( $ma, $con['row'] );
t( '🔴 XOÁ MỤC CON của hạng mục đã chốt → CHỐI (mục con mới là chỗ chứa tiền)',
	empty( $x['success'] ), $x );
t( '   câu chối nói rõ phải làm gì',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Mở lại' ), $x );
t( '   và dòng vẫn còn nguyên', null !== dong( $ma, 'Bóng đèn' ) );
$x = VHCP_DuAn::delete_line( $ma, $cha['row'] );
t( '🔴 xoá chính hạng mục lớn đã chốt → CHỐI', empty( $x['success'] ), $x );
$x = VHCP_DuAn::update_line( $ma, $con['row'], array( 'noiDung' => 'Bóng đèn', 'thucTe' => 0 ) );
t( '🔴 SỬA tiền về 0 → CHỐI (xoá trá hình, chỉ khác cái tên)', empty( $x['success'] ), $x );
teq( '   tiền giữ nguyên', 2000000.0, (float) dong( $ma, 'Bóng đèn' )['thucTe'] );

/* Đã gửi quyết toán thì câu chối nói luôn đợt nào — để người ta biết đi hỏi kế toán về cái gì. */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_quyet_toan_dot( $ma, array( $cha['row'] ) );
$x = VHCP_DuAn::delete_line( $ma, $con['row'] );
t( '🔴 đã gửi quyết toán → câu chối nói rõ đợt nào',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'quyết toán đợt 1' ), $x );

/* Mở lại thì xoá / sửa được — đó là đường chính thức. */
vai( 'Kế toán cá nhân', 'KT' );
VHCP_DuAn::dat_hm( $ma, $cha['row'], 'nhap' );
$x = VHCP_DuAn::update_line( $ma, $con['row'], array( 'noiDung' => 'Bóng đèn', 'thucTe' => 2000000 ) );
t( 'kế toán mở lại thì sửa được', ! empty( $x['success'] ), $x );
$x = VHCP_DuAn::delete_line( $ma, $con['row'] );
t( '   và xoá được', ! empty( $x['success'] ), $x );

/* 🔴 HẠNG MỤC KHÁC KHÔNG BỊ VẠ LÂY. Khoá theo cả dự án là nhân viên đứng hình: một hạng mục
   chốt xong thì mọi hạng mục còn lại cũng hết sửa. */
vai( 'Admin', 'KT' );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Vật tư khác', 'thucTe' => 700000 ) );
$khac = dong( $ma, 'Vật tư khác' );
VHCP_DuAn::dat_hm( $ma, $cha['row'], 'xong', array( 'hoaDon' => 'https://hd/1' ) );
$x = VHCP_DuAn::update_line( $ma, $khac['row'], array( 'noiDung' => 'Vật tư khác', 'thucTe' => 800000 ) );
t( '🔴 hạng mục KHÁC (chưa chốt) vẫn sửa bình thường — khoá không vạ lây cả dự án',
	! empty( $x['success'] ), $x );
$x = VHCP_DuAn::delete_line( $ma, $khac['row'] );
t( '   và xoá được', ! empty( $x['success'] ), $x );

/* ═══ 3c. NHẬT KÝ CỦA DỰ ÁN ══════════════════════════════════════════════════════════
 * Anh Thắng: *"Đầu trang bổ sung tiến trình như này và lịch sử đơn để theo dõi đơn và chỉnh
 * sửa"*.
 * 🔴 VẾT NẰM Ở BỐN KIỂU KHOÁ: `DA_x` (cả dự án) · `DA_x#12` (một dòng) · `DA_x · đợt 2` (một
 *    lệnh) · và TÊN dự án (màn ghi bằng tên). Tra thiếu kiểu nào là nhật ký khuyết đúng loại
 *    việc ấy — mà người ta mở nhật ký ra chính là để tìm cái mình không nhớ.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
VHCP_Log::log_action( array( 'actor' => 'NV', 'role' => 'Nhân viên',
	'action' => 'Ghi từ màn', 'target' => 'Gian thử đính tệp', 'detail' => 'vết theo TÊN' ) );
$lk = VHCP_DuAn::nhat_ky_du_an( $ma );
$viec = array();
foreach ( $lk['items'] as $i ) { $viec[] = $i['hanhDong']; }
t( '🔴 nhật ký gom được vết của CẢ DỰ ÁN (đặt dự toán…)',
	in_array( 'Đặt tổng dự toán dự án', $viec, true ) || count( $viec ) > 0, $viec );
t( '🔴 gom được vết của MỘT DÒNG (`DA_x#12`)',
	in_array( 'Đính ảnh vào dòng dự án', $viec, true ), $viec );
t( '🔴 gom được vết của MỘT LỆNH (`DA_x · đợt 1`)',
	in_array( 'Xin tạm ứng cho dự án', $viec, true ), $viec );
t( '🔴 và vết màn ghi theo TÊN dự án', in_array( 'Ghi từ màn', $viec, true ), $viec );
t( '   mỗi dòng có người, vai, thời gian',
	isset( $lk['items'][0]['nguoi'] ) && isset( $lk['items'][0]['vaiTro'] )
	&& isset( $lk['items'][0]['tg'] ), $lk['items'][0] );
/* 🔴 KHÔNG LẪN VIỆC CỦA DỰ ÁN KHÁC. Mã dự án chứa dấu gạch dưới — dùng LIKE trơn thì `DA_ab`
   khớp cả `DA_abc`, và nhật ký hai dự án trộn vào nhau. */
$r2 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Dự án khác hẳn', 'NV' );
VHCP_DuAn::set_du_toan_da( $r2['maDA'], 111, 'KT' );
/* 🔴 DỰNG HẲN MỘT VẾT CÓ MÃ NÀY LÀM TIỀN TỐ. Mã sinh ngẫu nhiên nên trong bài kiểm không tự
   nhiên có dự án nào là tiền tố của dự án kia — không dựng thì phép dưới xanh oan, và luật
   "chỉ nhận đúng ba kiểu khoá" có bị nới thành `LIKE 'DA_x%'` cũng không ai biết. */
VHCP_Log::log_action( array( 'actor' => 'X', 'role' => 'Admin',
	'action' => 'Việc của MÃ KHÁC', 'target' => $ma . 'ZZZ', 'detail' => 'không được lọt vào' ) );
$lk2 = VHCP_DuAn::nhat_ky_du_an( $ma );
$co_la = false; $co_tien_to = false;
foreach ( $lk2['items'] as $i ) {
	if ( false !== mb_strpos( $i['chiTiet'], '111' ) ) { $co_la = true; }
	if ( 'Việc của MÃ KHÁC' === $i['hanhDong'] ) { $co_tien_to = true; }
}
t( '🔴 nhật ký dự án này KHÔNG lẫn việc của dự án khác', ! $co_la, $lk2['items'] );
t( '🔴 và KHÔNG nhận vết của mã chỉ TRÙNG TIỀN TỐ (DA_x vs DA_xZZZ) — tra tiền tố trơn là hai '
	. 'dự án trộn nhật ký vào nhau', ! $co_tien_to, $lk2['items'] );
teq( 'mã dự án không có thật → trả danh sách rỗng, không nổ', 0,
	count( VHCP_DuAn::nhat_ky_du_an( 'DA-KHONG-CO' )['items'] ) );
teq( 'mã rỗng → cũng rỗng', 0, count( VHCP_DuAn::nhat_ky_du_an( '' )['items'] ) );
t( "🔴 'getDuAnLog' đã khai vào cửa API", false !== strpos(
	file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' ),
	"array( 'VHCP_DuAn', 'nhat_ky_du_an' )" ) );

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
