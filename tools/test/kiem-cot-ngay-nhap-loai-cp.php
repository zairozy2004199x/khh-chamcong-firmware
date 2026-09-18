<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CỘT NGÀY NHẬP + CỘT LOẠI CHI PHÍ TRÊN BẢNG DÒNG CHI CỦA ĐƠN TUẦN.
 *
 * Anh Thắng 18/09/2026: *"Cột ngày nhập, cột loại chi phí (kế toán có thể [sửa] loại chi phí
 * nếu nó sai). Còn chưa có thì báo chưa gắn mã"*.
 *
 * =============================================================================================
 * 🔴 LOẠI CHI PHÍ Ở ĐƠN TUẦN CHÍNH LÀ NHÓM MẶT HÀNG — nó suy ra TK Nợ (`tkno_loai`). Trước nay
 *    nó chỉ hiện ở dải gộp nhóm, nên nhìn một hàng lẻ thì không biết nó vào tài khoản nào, và
 *    dòng chưa gắn được mã thì im lặng trôi tới lúc xuất MISA mới lộ.
 *
 * 🔴 GẮN MÃ HẠCH TOÁN LÀ VIỆC CỦA KẾ TOÁN. Người nhập đổi được là con số nhảy tài khoản sau
 *    lưng kế toán — và cái sai chỉ lộ ra lúc đã quá muộn để hỏi lại họ đã mua cái gì.
 *
 * 🔴 NGÀY NHẬP KHÁC NGÀY CHI. Cột `ngay` là ngày chi, người nhập tự khai và sửa lại lúc nào
 *    cũng được; `tao_luc` là mốc máy ghi, không ai gõ được. Khi hai người nhớ khác nhau thì
 *    phải có một mốc để đối chiếu.
 *
 * Chạy: php tools/test/kiem-cot-ngay-nhap-loai-cp.php
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

/* ⚠️ KHAI DANH MỤC LOẠI CHI PHÍ TRƯỚC — một loại CÓ mã, một loại KHÔNG.
   Bệ thử mặc định có danh mục rỗng nên MỌI loại đều ra TK Nợ = '': lúc ấy phép "đổi loại thì
   tính lại mã" xanh oan, vì mã cũ và mã mới đều rỗng nên bằng nhau. Đã cắn thật lúc dựng bài
   này — đột biến "giữ nguyên mã cũ" không làm nó đỏ. Phải có hai loại RA HAI MÃ KHÁC NHAU thì
   phép ấy mới nói được điều gì. Và loại không mã chính là ca "chưa gắn mã" anh Thắng hỏi. */
VHCP_Cfg::seed();
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Chi phí nuôi thú',           '6417', '', '', '', '', '', '' ),
	array( 'Chi phí NVL đồ ăn - Mua lẻ', '6418', '', '', '', '', '', '' ),
	array( 'Chi phí chưa khai mã',       '',     '', '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();
$TK_THU = VHCP_Don::tk_of_line( 'Chi phí nuôi thú', 'Thanh toán cá nhân', 'Aeon Bình Tân' )['tk_no'];
$TK_AN  = VHCP_Don::tk_of_line( 'Chi phí NVL đồ ăn - Mua lẻ', 'Thanh toán cá nhân', 'Aeon Bình Tân' )['tk_no'];
t( '🔴 bệ thử dựng được HAI loại ra HAI mã khác nhau (không thì phép "tính lại mã" xanh oan)',
	'' !== $TK_THU && '' !== $TK_AN && $TK_THU !== $TK_AN, array( $TK_AN, $TK_THU ) );

vai( 'Admin', 'KT' );
$don = VHCP_Don::create_don( 'T9/2026', 'Nguyễn Văn Bin' );
$ma  = isset( $don['maDon'] ) ? (string) $don['maDon'] : '';
t( 'dựng được đơn thử', '' !== $ma, $don );

VHCP_Don::add_line( $ma, array(
	'coso' => 'Aeon Bình Tân', 'ngay' => '13/09/2026', 'phanLoaiTT' => 'Thanh toán cá nhân',
	'nhom' => 'Chi phí NVL đồ ăn - Mua lẻ', 'noiDung' => 'Dưa leo', 'dvt' => 'kg',
	'soLuong' => 1, 'donGia' => 20000 ) );
$d = VHCP_Don::get_don( $ma );
$L = null;
foreach ( $d['lines'] as $x ) { if ( 'Dưa leo' === $x['noiDung'] ) { $L = $x; } }
t( 'dựng được dòng thử', null !== $L, $d['lines'] );

/* ═══ 1. 🔴 NGÀY NHẬP XUỐNG TỚI MÀN ═══════════════════════════════════════════════════════ */
t( '🔴 dòng mang theo NGÀY NHẬP (mốc máy ghi) xuống màn', isset( $L['taoLuc'] ), array_keys( $L ) );
t( '   và nó có thật, không rỗng', '' !== trim( (string) $L['taoLuc'] ), $L['taoLuc'] );
/* ⚠️ HAI Ô KHÁC NHAU. Gộp làm một là mất đúng cái nó sinh ra để giữ: ngày chi sửa được, ngày
   nhập thì không. */
teq( '   ngày CHI vẫn là ngày người nhập khai', '13/09/2026', (string) $L['ngay'] );
VHCP_Don::set_line_ngay( $L['id'], '01/09/2026' );
$d2 = VHCP_Don::get_don( $ma ); $L2 = null;
foreach ( $d2['lines'] as $x ) { if ( 'Dưa leo' === $x['noiDung'] ) { $L2 = $x; } }
teq( '🔴 sửa ngày CHI thì ngày chi đổi', '01/09/2026', (string) $L2['ngay'] );
teq( '🔴 nhưng NGÀY NHẬP KHÔNG đổi theo — nếu đổi theo thì nó vô dụng, chỉ là bản sao ô kia',
	(string) $L['taoLuc'], (string) $L2['taoLuc'] );

/* ═══ 2. 🔴 KẾ TOÁN GẮN LẠI LOẠI CHI PHÍ ══════════════════════════════════════════════════ */
vai( 'Nhân viên', 'Nguyễn Văn Bin' );
$x = VHCP_Don::set_line_nhom( $L['id'], 'Chi phí nuôi thú' );
t( '🔴 NHÂN VIÊN gắn lại loại chi phí → CHỐI (đây là mã hạch toán, không phải nội dung dòng)',
	empty( $x['success'] ), $x );
t( '   và nói rõ vì sao', isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'kế toán' ), $x );

vai( 'Kế toán cá nhân', 'KT' );
$x = VHCP_Don::set_line_nhom( $L['id'], 'Chi phí nuôi thú' );
t( '🔴 kế toán gắn lại được', ! empty( $x['success'] ), $x );
$d3 = VHCP_Don::get_don( $ma ); $L3 = null;
foreach ( $d3['lines'] as $y ) { if ( 'Dưa leo' === $y['noiDung'] ) { $L3 = $y; } }
teq( '   loại mới vào sổ', 'Chi phí nuôi thú', (string) $L3['nhom'] );
/* 🔴 ĐỔI LOẠI PHẢI TÍNH LẠI MÃ. Giữ mã cũ thì màn hình nói một đằng, tệp MISA đi một nẻo. */
teq( '🔴 và MÃ TÀI KHOẢN tính lại theo loại mới, không giữ mã cũ', (string) $TK_THU, (string) $L3['tkNo'] );
t( '   (mã ấy KHÁC mã của loại cũ — nếu bằng nhau thì phép trên chẳng nói được gì)',
	$TK_THU !== $TK_AN, array( $TK_AN, $TK_THU ) );

/* 🔴 CA "CHƯA GẮN MÃ" — chính câu anh Thắng hỏi. Loại có tên nhưng danh mục chưa khai mã cho
   nó: gắn vẫn được (kế toán đang phân loại), nhưng máy chủ trả về mã RỖNG để màn báo đỏ. */
$x = VHCP_Don::set_line_nhom( $L['id'], 'Chi phí chưa khai mã' );
t( '🔴 gắn vào loại CHƯA KHAI MÃ → vẫn cho gắn (kế toán đang phân loại dở)', ! empty( $x['success'] ), $x );
teq( '🔴 nhưng mã trả về RỖNG, để màn báo "chưa gắn mã"', '', (string) $x['tkNo'] );
$d4 = VHCP_Don::get_don( $ma ); $L4 = null;
foreach ( $d4['lines'] as $y ) { if ( 'Dưa leo' === $y['noiDung'] ) { $L4 = $y; } }
teq( '   và sổ cũng để trống mã, không giữ mã cũ cho có', '', (string) $L4['tkNo'] );
/* Gắn về loại có mã thì mã quay lại — chứng tỏ ô rỗng ở trên là do danh mục, không phải do hỏng. */
VHCP_Don::set_line_nhom( $L['id'], 'Chi phí nuôi thú' );
$d5 = VHCP_Don::get_don( $ma ); $L5 = null;
foreach ( $d5['lines'] as $y ) { if ( 'Dưa leo' === $y['noiDung'] ) { $L5 = $y; } }
teq( '   gắn về loại có mã thì mã quay lại', (string) $TK_THU, (string) $L5['tkNo'] );

/* 🔴 CHỈ ĐỔI LOẠI + MÃ, không đụng ô nào khác — đi nhờ `update_line()` là mấy ô không gửi lên
   bị dọn về rỗng, im lặng. */
teq( '🔴 nội dung dòng còn nguyên', 'Dưa leo', (string) $L3['noiDung'] );
teq( '   số lượng còn nguyên', 1.0, (float) $L3['soLuong'] );
teq( '   đơn giá còn nguyên', 20000.0, (float) $L3['donGia'] );
teq( '   ngày chi còn nguyên', '01/09/2026', (string) $L3['ngay'] );

/* ═══ 3. MẤY CA CHỐI ══════════════════════════════════════════════════════════════════════ */
$x = VHCP_Don::set_line_nhom( $L['id'], '   ' );
t( '🔴 gắn loại RỖNG → chối (gắn rỗng là gỡ mã ra khỏi dòng, phải cố ý mới làm được)',
	empty( $x['success'] ), $x );
$x = VHCP_Don::set_line_nhom( 'khong-co-that', 'Chi phí nuôi thú' );
t( 'dòng không có thật → chối, không nổ', empty( $x['success'] ), $x );

/* ═══ 4. CỬA API — CÓ KHAI, VÀ NHÂN VIÊN BỊ CHẶN Ở CỔNG ═══════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( "🔴 'setLineNhom' đã khai vào cửa API (không khai thì màn bấm vào ô là im lặng)",
	false !== strpos( $src, "'setLineNhom'" )
	&& false !== strpos( $src, "array( 'VHCP_Don', 'set_line_nhom' )" ) );
/* Lõi đã gác rồi, nhưng cổng gác thêm một lớp: bảng phân quyền nạp từ bảng tính cũ có thể lệch
   cột, mà đây là chỗ đụng tới mã hạch toán của tiền người khác. */
t( '🔴 và nằm trong danh sách nhân viên KHÔNG được gọi (lớp gác thứ hai ở cổng)',
	false !== strpos( $src, "'setLineNhom'," ) );

/* ═══ 5. MÀN HÌNH ═════════════════════════════════════════════════════════════════════════ */
$HTML = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 đầu bảng có cột "Ngày nhập"', false !== mb_strpos( $HTML, '>Ngày nhập</th>' ) );
t( '🔴 đầu bảng có cột "Loại chi phí"', false !== mb_strpos( $HTML, '>Loại chi phí</th>' ) );
t( '   và mỗi dòng vẽ ô loại chi phí', false !== strpos( $HTML, '_oLoaiCp(l)+' ) );
t( '🔴 dòng chưa có loại → báo ĐỎ "chưa gắn mã", không để ô trắng',
	false !== mb_strpos( $HTML, '⚠ chưa gắn mã' ) );
/* Dùng lại `tkBadge()` — cùng một câu, cùng một màu với bảng dự án. Hai màn nói hai kiểu về
   cùng một chuyện là chỗ người ta phải học hai lần. */
t( '   và có loại nhưng loại ấy CHƯA khai mã thì cũng báo (dùng chung `tkBadge`)',
	false !== strpos( $HTML, 'tkBadge(ten, l.tkNo, l.tkCo)' )
	&& false !== mb_strpos( $HTML, "· chưa gắn mã" ) );
t( '🔴 chỉ kế toán mới thấy ô CHỌN; người khác chỉ đọc',
	false !== strpos( $HTML, 'if(!_laKeToan()){' ) );
t( '   gắn xong mà loại ấy vẫn chưa có mã thì NÓI RA, không im lặng báo xong',
	false !== mb_strpos( $HTML, 'CHƯA khai mã tài khoản' ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: ngày nhập không ai gõ được, loại chi phí kế toán gắn lại được.\n";
