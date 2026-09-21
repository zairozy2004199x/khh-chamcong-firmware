<?php
/**
 * KIỂM "TRANG TỰ NHỚ ĐĂNG NHẬP" (anh Thắng 07/09/2026).
 *
 * =============================================================================================
 * 🔴 ĐẦU BÀI, VÀ VÌ SAO KHÔNG LÀM ĐÚNG NHƯ CHỮ
 * =============================================================================================
 * *"hiện tại trang chưa tự lưu mật khẩu để lần sau. tính năng tự lưu mật khẩu để khỏi đăng
 * nhập lại"*.
 *
 * Làm đúng theo chữ — nhớ PIN trong trình duyệt — là sai. PIN nằm trong `localStorage` thì ai
 * mượn máy cũng đọc được, mà PIN ấy còn mở được cả trang chấm công lẫn trang nội bộ. Trang này
 * chạy ngoài internet.
 *
 * Thứ được nhớ là TOKEN PHIÊN: chuỗi ngẫu nhiên 64 ký tự, chỉ dùng cho đúng phiên ấy, thu hồi
 * được từ máy chủ khi bấm Đăng xuất, và tự chết sau 30 ngày. Kết quả với người dùng giống hệt
 * cái anh muốn — mở trang là vào thẳng.
 *
 * =============================================================================================
 * 🔴 CÁI HỎNG THẬT SỰ (trước bản này)
 * =============================================================================================
 * Token vốn ĐÃ sống 30 ngày trong `localStorage`. Nhưng DANH TÍNH (tên · vai · cơ sở) lại nằm ở
 * `sessionStorage` — mà cái đó chết ngay khi đóng trình duyệt. Nên mở lại là trang không biết
 * mình là ai, bày cổng PIN, dù phiên còn nguyên. Thiếu đúng một câu hỏi về máy chủ.
 *
 * Chạy: php tools/test/kiem-nho-phien.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-07 09:00:00' );
global $wpdb;

VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Anh Thắng',   'pin' => '1111', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Chị Kế Toán', 'pin' => '2222', 'vaiTro' => 'Kế toán cá nhân', 'coso' => 'TÀU TÂN PHÚ' ),
) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 1 — ĐĂNG NHẬP RỒI MỞ LẠI TRANG: VÀO THẲNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$r = VHCP_Auth::login( '2222' );
t( 'đăng nhập bằng PIN: được', ! empty( $r['ok'] ), $r );
$tok = (string) $r['token'];
t( 'có token phiên', $tok !== '', $r );
t( '🔴 token là chuỗi ngẫu nhiên 64 hex, KHÔNG dính dáng gì tới PIN',
	preg_match( '/^[0-9a-f]{64}$/', $tok ) === 1 && false === strpos( $tok, '2222' ), $tok );

/* Mở lại trang = chỉ còn token, không còn gì khác. */
$w = VHCP_Auth::ai_dang_dang_nhap( $tok );
t( '🔴 mở lại trang bằng token: vào thẳng, không hỏi PIN', ! empty( $w['ok'] ), $w );
teq( 'đúng tên người ấy', 'Chị Kế Toán', (string) $w['name'] );
teq( 'đúng vai trò',      'Kế toán cá nhân', (string) $w['role'] );
teq( 'đúng cơ sở',        'TÀU TÂN PHÚ', (string) $w['coso'] );
t( '⚠️ và KHÔNG trả PIN về cho máy khách', false === strpos( json_encode( $w ), '2222' ), array_keys( $w ) );

/* 🔴 DANH TÍNH LẤY TỪ MÁY CHỦ THÌ LUÔN ĐÚNG. Đổi vai cho ai đó là lần mở trang sau họ nhận
   ngay — không phải bắt đăng xuất rồi vào lại, mà "bắt" kiểu ấy thì thực tế không ai làm. */
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Anh Thắng',   'pin' => '1111', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Chị Kế Toán', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'coso' => 'FARM PHAN THIẾT' ),
) ) );
$w2 = VHCP_Auth::ai_dang_dang_nhap( $tok );
t( 'phiên cũ vẫn dùng được sau khi đổi hồ sơ', ! empty( $w2['ok'] ), $w2 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 2 — TOKEN HỎNG / HẾT HẠN / BỊ THU HỒI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

t( '🔴 token bịa: chối', empty( VHCP_Auth::ai_dang_dang_nhap( str_repeat( 'a', 64 ) )['ok'] ) );
t( 'token rỗng: chối',   empty( VHCP_Auth::ai_dang_dang_nhap( '' )['ok'] ) );
t( 'token sai hình dạng: chối', empty( VHCP_Auth::ai_dang_dang_nhap( 'không-phải-hex' )['ok'] ) );
/* ⚠️ Câu chối phải NÓI RÕ là hết phiên, để giao diện biết mà bày ô PIN thay vì báo lỗi lạ. */
$e = VHCP_Auth::ai_dang_dang_nhap( '' );
t( 'và nói rõ vì sao', isset( $e['error'] ) && false !== mb_strpos( $e['error'], 'hết hạn' ), $e );

/* --- 🔴 BẤM ĐĂNG XUẤT LÀ PHẢI THẬT SỰ RA. Đây là chỗ nguy nhất của cả tính năng: nhớ phiên mà
   đăng xuất không thu hồi được thì máy ấy vào lại được mãi. --- */
$r2 = VHCP_Auth::login( '1111' );
$tok_ad = (string) $r2['token'];
t( 'người thứ hai đăng nhập được', ! empty( VHCP_Auth::ai_dang_dang_nhap( $tok_ad )['ok'] ) );
VHCP_Auth::logout( $tok_ad );
t( '🔴 đăng xuất rồi: token chết hẳn', empty( VHCP_Auth::ai_dang_dang_nhap( $tok_ad )['ok'] ) );
t( '⚠️ nhưng KHÔNG đụng tới phiên của người khác', ! empty( VHCP_Auth::ai_dang_dang_nhap( $tok )['ok'] ) );

/* --- HẾT HẠN: đẩy ngày hết hạn về quá khứ --- */
$r3 = VHCP_Auth::login( '2222' );
$tok_cu = (string) $r3['token'];
$wpdb->update( VHCP_DB::t( 'session' ), array( 'het_han' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ),
	array( 'token' => $tok_cu ) );
t( '🔴 phiên quá hạn: chối', empty( VHCP_Auth::ai_dang_dang_nhap( $tok_cu )['ok'] ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 3 — GIA HẠN LĂN
 *
 * Người dùng hằng ngày không bao giờ nên chạm mốc hết hạn; người bỏ trang 30 ngày thì vẫn phải
 * nhập lại. Nếu KHÔNG gia hạn thì cứ đúng 30 ngày sau lần đăng nhập đầu, cả công ty phải nhập
 * PIN lại trong cùng một ngày — và không ai hiểu vì sao.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* Đọc hạn còn lại bằng PHP — `TIMESTAMPDIFF()` là hàm MySQL, bệ đỡ SQLite trả 0 cho nó, mà 0
   thì mọi phép dưới đây xanh vô nghĩa. */
function _con_lai( $tok ) {
	global $wpdb;
	$hh = (string) $wpdb->get_var( $wpdb->prepare(
		'SELECT het_han FROM ' . VHCP_DB::t( 'session' ) . ' WHERE token=%s', $tok ) );
	return $hh !== '' ? ( strtotime( $hh . ' UTC' ) - time() ) : 0;
}

$r4 = VHCP_Auth::login( '2222' );
$tok_gh = (string) $r4['token'];
$t_ss = VHCP_DB::t( 'session' );

/* Còn 3 ngày -> phải được đẩy về đủ 30. */
$wpdb->update( $t_ss, array( 'het_han' => gmdate( 'Y-m-d H:i:s', time() + 3 * 86400 ) ), array( 'token' => $tok_gh ) );
VHCP_Auth::ai_dang_dang_nhap( $tok_gh );
$con = _con_lai( $tok_gh );
t( '🔴 sắp hết hạn thì được gia hạn về ~30 ngày', $con > 29 * 86400, $con );

/* Còn 20 ngày -> KHÔNG đụng vào. Ghi lại mỗi lượt mở trang là mỗi lượt một câu UPDATE, mà
   trang này người ta mở suốt ngày. */
$moc = time() + 20 * 86400;
$wpdb->update( $t_ss, array( 'het_han' => gmdate( 'Y-m-d H:i:s', $moc ) ), array( 'token' => $tok_gh ) );
VHCP_Auth::ai_dang_dang_nhap( $tok_gh );
$con2 = _con_lai( $tok_gh );
t( '⚠️ còn nhiều ngày thì KHÔNG ghi lại (khỏi một câu UPDATE mỗi lượt mở trang)',
	$con2 > 19 * 86400 && $con2 < 21 * 86400, $con2 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 4 — 🔴 SOI MÃ: KHÔNG CHỖ NÀO LƯU PIN Ở MÁY KHÁCH
 *
 * Đây là ràng buộc phải giữ mãi, nên canh bằng mã chứ không bằng trí nhớ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$app = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
$shim = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/assets/js/gas-shim.js' );
/* Bóc chú thích KHỐI trước khi dò — chính mấy khối chú thích ở đây nhắc đi nhắc lại chữ "PIN"
   cạnh chữ "localStorage", nên dò trần trụi là tự ĐỎ. Bẫy đã cắn năm lần ở hai kho này. */
$boc = function ( $x ) { return preg_replace( '#(^|[\s;{(,:])/\*[\s\S]*?\*/#', '$1 ', $x ); };
$app_ma  = $boc( $app );
$shim_ma = $boc( $shim );
t( 'đối chứng: bóc chú thích xong vẫn còn mã để soi',
	false !== strpos( $app_ma, 'function doLogin(){' ) && false !== strpos( $shim_ma, 'TOKEN_KEY' ) );

foreach ( array( 'app.html' => $app_ma, 'gas-shim.js' => $shim_ma ) as $ten => $ma ) {
	/* Không có dòng nào nhét biến `pin` vào localStorage/sessionStorage. */
	t( "🔴 $ten: không lưu PIN vào localStorage",
		preg_match( '#(local|session)Storage\.setItem\(\s*[\'"][^\'"]*pin#i', $ma ) === 0, $ten );
	t( "🔴 $ten: không có khoá lưu trữ nào tên dính 'pin'",
		preg_match( '#[\'"]vhcp_pin[\'"]#i', $ma ) === 0, $ten );
}
/* Ô nhập PIN không được để trình duyệt nhớ hộ — `autocomplete` phải tắt. Trình duyệt nhớ hộ
   thì PIN nằm trong kho mật khẩu của máy, đúng thứ đang tránh. */
t( '⚠️ ô PIN tắt autocomplete', preg_match( '#id="gatePin"[^>]*autocomplete="off"#', $app_ma ) === 1
	|| preg_match( '#autocomplete="off"[^>]*id="gatePin"#', $app_ma ) === 1, 'gatePin' );

/* --- Đường tự vào lại phải có thật, và phải đi qua token --- */
t( '🔴 trang có hỏi máy chủ "tôi là ai" khi mở lại', false !== strpos( $app_ma, '.aiDangDangNhap(' ), '' );
t( '   và lấy token từ localStorage để hỏi',
	preg_match( "#localStorage\.getItem\(\s*'vhcp_token'\s*\)#", $app_ma ) === 1, '' );
t( '🔴 phiên chết thì dọn token, khỏi hỏi lại lần sau',
	false !== strpos( $app_ma, "localStorage.removeItem('vhcp_token')" ), '' );

/* --- Cửa API: hàm kiểm tra phiên phải gọi được KHI CHƯA có phiên --- */
$api_ma = $boc( file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' ) );
if ( preg_match( '#\$public_fns = array\(([^)]*)\)#', $api_ma, $mp ) ) {
	t( "🔴 'aiDangDangNhap' nằm trong danh sách hàm chạy được khi chưa đăng nhập",
		false !== strpos( $mp[1], "'aiDangDangNhap'" ), $mp[1] );
	/* ⚠️ VÀ DANH SÁCH ẤY PHẢI NGẮN. Mỗi cái tên thêm vào đây là một cửa mở toang; canh số
	      lượng thì hôm nào có người nhét thêm là bài kiểm hỏi lại. */
	t( '⚠️ và danh sách ấy vẫn chỉ có hai hàm', substr_count( $mp[1], "'" ) === 4, $mp[1] );
} else {
	t( '🔴 bốc được danh sách hàm công khai', false, '' );
}

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐỔI VAI PHẢI ĂN NGAY, KHÔNG CHỜ NGƯỜI TA ĐĂNG XUẤT
 *
 * Thẻ phiên sống 30 ngày và trước đây nó ghi luôn VAI lúc đăng nhập. Nên đổi vai của một
 * người ở màn Cấu hình không có hiệu lực gì cho tới khi họ tự đăng xuất — mà không ai bảo họ
 * phải làm thế.
 *
 * Đã cắn thật 08/09/2026: anh Thắng gán vai "Kế toán máy tự động" cho một tài khoản, bấm Lưu,
 * rồi hỏi *"đã phân qua kế toán máy tự động, tại sao vẫn nhìn được nội dung của bộ phận khác"*.
 * Màn của người ấy vẫn ghi vai CŨ trên thanh tiêu đề.
 *
 * ⚠️ Chiều nguy hiểm hơn là THU HỒI: hạ một người từ Kế toán xuống Nhân viên mà phiên đang mở
 *    vẫn giữ quyền cũ suốt 30 ngày.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Kế toán máy tự động', 'Kế toán cá nhân', 'Máy tự động' ),
) );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Chị Trinh', '334400', 'Kế toán cá nhân', '', '', '', '', '', '' ),
) );
$tok_t = VHCP_Auth::login( '334400' );
t( 'đăng nhập được', ! empty( $tok_t['ok'] ), $tok_t );
teq( 'lúc đăng nhập mang vai cũ', 'Kế toán cá nhân', $tok_t['role'] );

/* Đổi vai ở màn Cấu hình — KHÔNG đụng gì tới thẻ phiên đang cầm. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Chị Trinh', '334400', 'Kế toán máy tự động', '', '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();
$u_sau = VHCP_Auth::user_by_token( $tok_t['token'] );
teq( '🔴 CÙNG thẻ phiên cũ nhưng vai đã là vai MỚI', 'Kế toán máy tự động', $u_sau['role'] );
teq( 'và vai gốc quy đúng',                          'Kế toán cá nhân',     $u_sau['roleGoc'] );

/* Chiều thu hồi. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Chị Trinh', '334400', 'Nhân viên', '', '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();
teq( '🔴 hạ quyền cũng ăn ngay trên thẻ cũ', 'Nhân viên', VHCP_Auth::user_by_token( $tok_t['token'] )['role'] );

/* Cơ sở và bộ phận cũng đọc lại — không thì siết cơ sở của một người cũng không ăn. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Chị Trinh', '334400', 'Nhân viên', 'FARM NHA TRANG', '', '', 'Kỹ thuật', '', '' ),
) );
VHCP_Cfg::clear_cache();
$u3 = VHCP_Auth::user_by_token( $tok_t['token'] );
teq( 'cơ sở đọc lại từ bảng',   'FARM NHA TRANG', $u3['coso'] );
teq( 'bộ phận đọc lại từ bảng', 'Kỹ thuật',       $u3['boPhan'] );

/* ⚠️ So tên KHÔNG PHÂN BIỆT HOA THƯỜNG và bỏ khoảng trắng thừa. Bảng người dùng do người gõ
   tay ở màn Cấu hình — ảnh anh Thắng gửi 08/09/2026 có sẵn cả "NGUYỄN THỊ MỸ TIÊN" viết hoa
   lẫn "Nguyễn Thị Mỹ Tiên" viết thường. So nguyên văn thì đúng những dòng gõ lệch ấy sẽ không
   khớp, và người đó lặng lẽ giữ vai cũ mãi — hỏng đúng kiểu không ai dò ra. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( '  chị TRINH ', '334400', 'Quản lý', '', '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();
teq( '🔴 tên gõ lệch hoa thường / thừa khoảng trắng vẫn khớp đúng người',
	'Quản lý', VHCP_Auth::user_by_token( $tok_t['token'] )['role'] );

/* ⚠️ Xoá tài khoản khỏi bảng thì DÙNG NGUYÊN THẺ như cũ, không chối thẳng: một lượt đọc bảng
   lỗi mà đá văng mọi người đang đăng nhập thì tệ hơn nhiều. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Người khác', '999999', 'Admin', '', '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();
$u4 = VHCP_Auth::user_by_token( $tok_t['token'] );
t( '⚠️ tên không còn trong bảng thì vẫn giữ phiên', is_array( $u4 ) && 'Chị Trinh' === $u4['name'], $u4 );
teq( 'và giữ nguyên vai ghi trên thẻ', 'Kế toán cá nhân', $u4['role'] );

if ( count( $truot ) ) {
	echo "\n=== NHỚ PHIÊN ===\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat   TRƯỢT: " . count( $truot ) . "\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: trang nhớ PHIÊN bằng token, không nhớ PIN.\n";
