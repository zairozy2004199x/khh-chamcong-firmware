<?php
/**
 * VÉ NỐI DANH TÍNH GIỮA TRẠM VÀ MẤY APP KHÁC.
 *
 * =================================================================================================
 * 🔴 LỖI BÀI NÀY SINH RA ĐỂ ĐÓNG — LỖI ĐỔI NGƯỜI, KHÔNG PHẢI LỖI HIỂN THỊ
 * =================================================================================================
 * Anh Thắng 19/09/2026, hai ảnh đặt cạnh nhau: trạm Chấm công đang là *Trần Ngọc Minh Truyền ·
 * TUTU_TP · Cửa hàng trưởng*, bấm sang Vận Hành Chi Phí thì hiện *Nguyễn Văn Bin · Nhân viên ·
 * FARM_PT*. *"Khi vào tk của tôi thì vẫn là mình. Mà truy cập ứng dụng thì đang đăng nhập tk
 * khác. Phải tự link chung 1 tk chứ"*.
 *
 * Ô Ứng dụng vốn chỉ là một đường dẫn TRƠN. Sang tới nơi, app kia không biết ai vừa bấm nên lấy
 * thẻ cũ còn sót trong máy — thẻ của người gần nhất đã gõ PIN trên chiếc điện thoại ấy. Hậu quả
 * thật: người này tạo và duyệt đơn chi phí DƯỚI DANH NGHĨA người kia.
 *
 * Bài này canh ba lớp, vì hỏng một lớp là cả đường nối im lặng quay về lối cũ:
 *   1. vé phát / đổi đúng luật (một lần, hết hạn, không đoán được);
 *   2. trạm GẮN vé vào mọi ô dẫn ra ngoài;
 *   3. app bên kia ĐỔI vé, và — chỗ quan trọng nhất — cất thẻ mới ĐÈ LÊN thẻ cũ.
 *
 * Chạy: php tools/test/kiem-ve-lien-app.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 400 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/* ====================================================================== 1. phát và đổi vé */

echo "— phát và đổi vé —\n";
$TRUYEN = array( 'name' => 'Trần Ngọc Minh Truyền', 'role' => VHCC_Vai::CHT,
	'coso' => 'TUTU_TP', 'ma_nv' => 'MNNV2KVC0166' );

$ve = VHCC_Ve::phat( $TRUYEN );
t( 'phát được vé', '' !== $ve, $ve );
t( '🔴 vé là 64 ký tự hex ngẫu nhiên — đoán trúng là vào thẳng tài khoản người khác',
	1 === preg_match( '/^[0-9a-f]{64}$/', $ve ), $ve );

$d = VHCC_Ve::doi( $ve );
t( 'đổi được vé', is_array( $d ), $d );
teq( '   ra đúng tên', 'Trần Ngọc Minh Truyền', $d['name'] );
teq( '   ra đúng cơ sở', 'TUTU_TP', $d['coso'] );
teq( '   và mang theo mã NV', 'MNNV2KVC0166', $d['ma_nv'] );

/* 🔴 MỘT LẦN, RỒI THÔI. Vé dùng lại được thì một ảnh chụp màn hình có đường dẫn là một lối vào
   tài khoản người khác — mà ảnh chụp màn hình thì anh em gửi cho nhau suốt. */
teq( '🔴 đổi lần thứ hai thì KHÔNG ăn nữa', null, VHCC_Ve::doi( $ve ) );

/* Rác thì chối, đừng đoán. */
teq( 'vé bịa thì chối', null, VHCC_Ve::doi( str_repeat( 'a', 64 ) ) );
teq( 'chuỗi sai dạng thì chối', null, VHCC_Ve::doi( 'abc' ) );
teq( 'rỗng thì chối', null, VHCC_Ve::doi( '' ) );

/* Hai lượt phát phải ra hai vé khác nhau — cùng một vé cho mọi người là không còn vé nào cả. */
t( '🔴 mỗi lượt phát một vé khác nhau', VHCC_Ve::phat( $TRUYEN ) !== VHCC_Ve::phat( $TRUYEN ) );

/* Thiếu tên hay vai thì KHÔNG phát — một vé rỗng đổi ra một danh tính rỗng. */
teq( 'không có tên thì không phát vé', '', VHCC_Ve::phat( array( 'role' => 'Nhân viên' ) ) );
teq( 'không có vai thì không phát vé', '', VHCC_Ve::phat( array( 'name' => 'Ai Đó' ) ) );

/* ====================================================================== 2. quy vai */

echo "— quy vai sang mã của trang tổng —\n";
teq( 'Admin',            'ADMIN',           VHCC_Ve::ma_vai( VHCC_Vai::ADMIN ) );
teq( 'Kế toán',          'KE_TOAN',         VHCC_Ve::ma_vai( VHCC_Vai::KE_TOAN ) );
teq( 'Quản lý',          'QUAN_LY',         VHCC_Ve::ma_vai( VHCC_Vai::QL ) );
teq( 'Cửa hàng trưởng',  'CUA_HANG_TRUONG', VHCC_Ve::ma_vai( VHCC_Vai::CHT ) );
teq( 'Nhân viên',        'NHAN_VIEN',       VHCC_Ve::ma_vai( VHCC_Vai::NV ) );
/* ⚠️ Vai lạ thì về bậc THẤP NHẤT, không phải bậc cao. Đoán lên là mở quyền cho một cái tên
   không ai khai. */
teq( '🔴 vai lạ thì về bậc thấp nhất', 'NHAN_VIEN', VHCC_Ve::ma_vai( 'Vai Chưa Có Bao Giờ' ) );

/* ====================================================================== 3. gắn vào đường dẫn */

echo "— gắn vé vào đường dẫn —\n";
$u1 = VHCC_Ve::gan( 'https://vi.du/chi-phi/', $TRUYEN );
t( '🔴 đường dẫn có mang vé', 1 === preg_match( '/[?&]ccve=[0-9a-f]{64}/', $u1 ), $u1 );
t( '   và giữ nguyên đường gốc', 0 === strpos( $u1, 'https://vi.du/chi-phi/' ), $u1 );
$u2 = VHCC_Ve::gan( 'https://vi.du/chi-phi/?ve_tram=1', $TRUYEN );
t( '   tham số sẵn có không bị mất', false !== strpos( $u2, 've_tram=1' ), $u2 );
/* Không phát được vé thì trả nguyên đường dẫn — thà về lối cũ còn hơn một ô bấm không được. */
teq( 'không phát được vé thì trả nguyên đường dẫn',
	'https://vi.du/x', VHCC_Ve::gan( 'https://vi.du/x', array() ) );

/* 🔴 TRẠM PHẢI GẮN Ở MỘT CHỖ DUY NHẤT. Bốn ô dựng ở bốn khối cách xa nhau; thêm ô thứ năm mà
   quên gắn là ô ấy lặng lẽ quay về lối cũ — và lối cũ không báo lỗi, nó chỉ hiện sai tên. */
$ma_ung = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-ung.php' );
t( '🔴 `ds()` gắn vé cho MỌI ô có url, không gắn lẻ từng ô',
	1 === preg_match( '/foreach \( \$o as &\$o_x \)[\s\S]{0,200}VHCC_Ve::gan/', $ma_ung ), 'không thấy vòng lặp' );
t( '   và không còn ô nào tự gắn riêng',
	1 === substr_count( $ma_ung, 'VHCC_Ve::gan' ), substr_count( $ma_ung, 'VHCC_Ve::gan' ) . ' chỗ' );

/* ====================================================================== 4. bên nhận */

echo "— bên nhận: bốn bản chi phí —\n";
$ban = array(
	'vhcp-chi-phi'     => 'VHCP_',
	'vhcp-chi-phi-hn'  => 'VHCPHN_',
	'vhcp-chi-phi-mtd' => 'VHCPMTD_',
	'vhcp-chi-phi-vp'  => 'VHCPVP_',
);
foreach ( $ban as $thu_muc => $tien_to ) {
	$f = $goc . '/wordpress/' . $thu_muc . '/includes/class-vhcp-app.php';
	$m = file_exists( $f ) ? file_get_contents( $f ) : '';
	t( $thu_muc . ': có đọc vé `ccve`', false !== strpos( $m, "\$_GET['ccve']" ), 'không thấy' );
	/* ⚠️ Gác `class_exists` cùng hàm với lời gọi — plugin chấm công có thể chưa cài. */
	t( $thu_muc . ': gác class_exists trước khi gọi sang',
		false !== strpos( $m, "class_exists( 'VHCC_Ve' )" ), 'không thấy' );
	/* 🔴 VÉ CỦA TRẠM PHẢI THẮNG. Để `?sso=` chạy trước thì một đường dẫn cũ còn `sso=` trong
	   lịch sử sẽ đè lên người vừa bấm ở trạm. */
	$i_ve  = strpos( $m, 've_cham_cong();' );
	$i_sso = strpos( $m, "empty( \$_GET['sso'] )" );
	t( $thu_muc . ': 🔴 vé của trạm xét TRƯỚC `?sso=`',
		false !== $i_ve && false !== $i_sso && $i_ve < $i_sso, "ve=$i_ve sso=$i_sso" );
	t( $thu_muc . ': phát thẻ phiên cho người mới',
		false !== strpos( $m, $tien_to . "Auth::issue_token" ), 'không thấy' );
	/* 🔴 CHỖ QUAN TRỌNG NHẤT. Không đưa thẻ xuống giao diện thì thanh tiêu đề hiện đúng tên
	   mới, mà MỌI lệnh gọi máy chủ vẫn đi kèm thẻ CŨ — ghi sổ sai tên, không gì báo. */
	t( $thu_muc . ': 🔴 đưa thẻ phiên xuống giao diện (`ssoToken`)',
		false !== strpos( $m, "'ssoToken'" ), 'không thấy' );

	$js = $goc . '/wordpress/' . $thu_muc . '/assets/js/gas-shim.js';
	$mj = file_exists( $js ) ? file_get_contents( $js ) : '';
	t( $thu_muc . ': 🔴 shim GHI ĐÈ thẻ cũ bằng `ssoToken`',
		false !== strpos( $mj, 'if ( CFG.ssoToken ) {' )
		&& false !== strpos( $mj, 'setToken( CFG.ssoToken )' ), 'không thấy' );
	/* Bản nhớ danh tính cũ cũng phải đi — nó là thứ màn vẽ ngay lúc mở, trước khi hỏi máy chủ.
	   Để lại là tên người cũ nháy lên một nhịp. */
	t( $thu_muc . ': dọn luôn bản nhớ danh tính cũ',
		1 === preg_match( '/CFG\.ssoToken[\s\S]{0,400}removeItem\( USER_KEY \)/', $mj ), 'không thấy' );
}

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — bấm sang app khác là vẫn đúng người.\n";
