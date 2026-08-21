<?php
/**
 * PHÉP THỬ PLUGIN CHẤM CÔNG (wordpress/vhcp-cham-cong)
 *
 * Điểm khác app hợp đồng, và cũng là chỗ nguy hiểm nhất: project chấm công ĐÃ CÓ `doPost` —
 * đó là cửa cả chuỗi máy ESP32 đang đẩy dữ liệu vào. Nên phần lớn phép thử ở đây canh đúng một
 * điều: cầu nối KHÔNG được chạm vào đường của máy chấm công.
 *
 * Chạy: php tools/test/test-cham-cong.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );      // để có bảng cfg + người dùng đã seed
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
vhd_test_boot( $goc . '/wordpress/vhcp-hop-dong' );      // để đối chiếu: hai app phải RIÊNG phiên

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

// ============================================================ 1. Cầu nối KHÔNG phá đường của máy
$gs = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/cau-noi.gs' );

// Tìm KHAI BÁO THẬT (đầu dòng, cột 0), không tính chữ "function doPost" trong phần hướng dẫn —
// hướng dẫn buộc phải nhắc tên hàm đó để anh biết chèn vào đâu.
t( 'cầu nối chấm công TUYỆT ĐỐI không khai doPost',
	preg_match( '/^function\s+doPost\b/m', $gs ) === 0 );
t( 'cũng không khai doGet (đè giao diện app gốc)',
	preg_match( '/^function\s+doGet\b/m', $gs ) === 0 );
t( 'chỉ khai hàm lồng ccCauNoi', strpos( $gs, 'function ccCauNoi' ) !== false );
t( 'trả null khi không có fn (để doPost cũ chạy tiếp)',
	strpos( $gs, 'if (!fn) return null' ) !== false );
t( 'thân request không phải JSON thì cũng trả null', strpos( $gs, 'return null;                                 // thân không phải JSON' ) !== false );
t( 'hướng dẫn nêu rõ phải CHÈN vào đầu doPost đang có', strpos( $gs, 'CHÈN' ) !== false );
t( 'cảnh báo hai doPost là chết im lặng', strpos( $gs, 'chết im' ) !== false );
t( 'nhắc kiểm lại máy chấm công sau khi deploy', strpos( $gs, 'KIỂM LẠI MÁY CHẤM CÔNG' ) !== false );
t( 'nhắc giữ nguyên "Who has access" (máy gọi ẩn danh)', strpos( $gs, 'Who has access' ) !== false );
t( 'kiểm khoá WEB_KEY', strpos( $gs, 'WEB_KEY' ) !== false );
t( 'có đường lấy giao diện gốc', strpos( $gs, '__giaoDien' ) !== false );
t( 'lấy hàm qua globalThis, không qua this', strpos( $gs, 'globalThis' ) !== false );

// Danh sách hàm còn RỖNG là đúng ở bước này — nhưng phải rỗng một cách CÓ CHỦ Ý, và cầu nối
// phải nói ra chứ không im lặng cho gọi bừa.
t( 'CC_CHO_PHEP tồn tại', strpos( $gs, 'CC_CHO_PHEP' ) !== false );
t( 'danh sách rỗng thì báo rõ, không im lặng',
	strpos( $gs, 'còn RỖNG' ) !== false );

// ============================================================ 2. Trang không phục vụ giao diện chết
$fns = VHCC_Trang::ds_ham();
$ham_app = array_diff( $fns, array( 'login', 'vhccLogout' ) );
teq( 'chưa khai hàm nào của app (đang chờ Index.html)', array(), array_values( $ham_app ) );
t( 'vẫn có login', in_array( 'login', $fns, true ) );

// ============================================================ 3. Cầu nối phía WordPress
teq( 'chưa khai /exec thì báo rõ', false, VHCC_CauNoi::goi( 'x' )['ok'] );
$GLOBALS['VHCP_OPT']['vhcc_exec_url'] = 'https://script.google.com/macros/s/CC/exec';
$khoa = VHCC_CauNoi::bao_dam_khoa();
t( 'khoá sinh tự động, đủ dài', strlen( $khoa ) >= 32, strlen( $khoa ) );

$GLOBALS['VHD_DA_GUI'] = array();
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => json_encode( array( 'ok' => true, 'data' => array( 'song' => true ) ) ) ) );
$r = VHCC_CauNoi::goi( '__ping' );
t( 'gọi được', ! empty( $r['ok'] ), $r );
$da = json_decode( $GLOBALS['VHD_DA_GUI'][0]['body'], true );
teq( 'gửi đúng tên hàm', '__ping', $da['fn'] );
teq( 'gửi kèm khoá', $khoa, $da['key'] );
t( 'KHÔNG gửi trường nào giống dữ liệu máy chấm công',
	! isset( $da['employeeNo'] ) && ! isset( $da['time'] ) && ! isset( $da['stationName'] ), $da );

// Deploy sai quyền -> Google đòi đăng nhập. Với app chấm công đây là lỗi CHẾT NGƯỜI vì máy cũng
// gọi ẩn danh, nên thông báo phải nhắc đúng chỗ đó.
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => '<html>Sign in - accounts.google.com</html>' ) );
$r = VHCC_CauNoi::goi( '__ping' );
teq( 'trang đăng nhập Google -> không ok', false, $r['ok'] );
t( 'chỉ ra phải để Anyone', strpos( $r['error'], 'Anyone' ) !== false, $r['error'] );

// ============================================================ 4. Cổng PIN + vai trò
$users = VHCC_Auth::users();
t( 'đọc được người dùng dùng chung với app chi phí', is_array( $users ) && count( $users ) > 0 );

teq( 'mặc định KHÔNG cho Nhân viên vào', false,
	in_array( 'Nhân viên', VHCC_Auth::vai_tro_vao(), true ) );
teq( 'mặc định cho Admin vào', true, in_array( 'Admin', VHCC_Auth::vai_tro_vao(), true ) );

// Mở rộng bằng CÀI ĐẶT, không sửa code
$GLOBALS['VHCP_OPT']['vhcc_vai_tro_vao'] = array( 'Admin', 'Nhân viên' );
teq( 'mở thêm được vai trò từ Cài đặt', true, in_array( 'Nhân viên', VHCC_Auth::vai_tro_vao(), true ) );
teq( 'không nhận vai trò lạ', false, in_array( 'Giám đốc', VHCC_Auth::vai_tro_vao(), true ) );

// Rỗng thì PHẢI về mặc định — rỗng là không ai vào được, kể cả Admin, không có đường tự mở lại
$GLOBALS['VHCP_OPT']['vhcc_vai_tro_vao'] = array();
teq( 'danh sách rỗng thì về mặc định, không khoá sạch', VHCC_Auth::VAI_TRO_MAC_DINH, VHCC_Auth::vai_tro_vao() );
$GLOBALS['VHCP_OPT']['vhcc_vai_tro_vao'] = array( 'Giám đốc' );   // toàn tên lạ
teq( 'lọc hết còn rỗng thì cũng về mặc định', VHCC_Auth::VAI_TRO_MAC_DINH, VHCC_Auth::vai_tro_vao() );
unset( $GLOBALS['VHCP_OPT']['vhcc_vai_tro_vao'] );

$admin = null;
foreach ( $users as $u ) { if ( $u['vaiTro'] === 'Admin' && $u['pin'] !== '' ) { $admin = $u; } }
t( 'có tài khoản Admin để thử', $admin !== null );
$r = VHCC_Auth::login( $admin['pin'] );
t( 'Admin đăng nhập được', ! empty( $r['ok'] ), $r );
t( 'phát token 64 ký tự', preg_match( '/^[0-9a-f]{64}$/', (string) $r['token'] ) === 1 );
teq( 'token tra ra đúng người', $admin['ten'], VHCC_Auth::user_by_token( $r['token'] )['name'] );
VHCC_Auth::logout( $r['token'] );
teq( 'đăng xuất thì token hết hiệu lực', null, VHCC_Auth::user_by_token( $r['token'] ) );

// Phiên của app chấm công phải RIÊNG với app hợp đồng — hai hệ thống riêng thì thu hồi bên này
// không được kéo bên kia xuống theo.
$tok_cc = VHCC_Auth::phat_token( 'Chị Kế Toán', 'Kế toán cá nhân', '' );
t( 'token chấm công KHÔNG dùng được cho app hợp đồng', VHD_Auth::user_by_token( $tok_cc ) === null );
$tok_hd = VHD_Auth::phat_token( 'Chị Kế Toán', 'Kế toán cá nhân', '' );
t( 'token hợp đồng KHÔNG dùng được cho app chấm công', VHCC_Auth::user_by_token( $tok_hd ) === null );

// ============================================================ 5. Cửa API
function goi_cc( $fn, $args = array(), $token = '' ) {
	$req = new WP_REST_Request( array( 'fn' => $fn, 'args' => $args, 'token' => $token ) );
	$res = VHCC_API::handle( $req );
	return array( 'status' => $res->get_status(), 'body' => $res->get_data() );
}
$a = goi_cc( 'layBangCong' );
teq( 'chưa đăng nhập -> 401', 401, $a['status'] );
teq( 'kèm mã để giao diện mở lại cổng PIN', 'no_session', $a['body']['code'] );

$tok_nv = VHCC_Auth::phat_token( 'NV Cơ Sở', 'Nhân viên', '' );
teq( 'token Nhân viên -> 401 (mặc định không cho vào)', 401, goi_cc( 'layBangCong', array(), $tok_nv )['status'] );

$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => json_encode( array( 'ok' => true, 'data' => array( 1, 2 ) ) ) ) );
$a = goi_cc( 'layBangCong', array(), $tok_cc );
teq( 'kế toán có phiên thì gọi được', 200, $a['status'] );
teq( 'login là hàm công khai', 200, goi_cc( 'login', array( '0000' ) )['status'] );

$json = json_encode( goi_cc( 'layBangCong', array(), $tok_cc ), JSON_UNESCAPED_UNICODE );
t( 'khoá cầu nối không lọt xuống trình duyệt', strpos( $json, $khoa ) === false );

// ============================================================ 6. Không đụng vào firmware
$ino = file_get_contents( $goc . '/esp32_hik_chamcong_full/esp32_hik_chamcong_full.ino' );
t( 'firmware vẫn gọi /macros/s/<id>/exec như cũ', strpos( $ino, '/macros/s/' ) !== false );
t( 'firmware KHÔNG bị thêm địa chỉ WordPress nào', strpos( $ino, 'vhcc' ) === false
	&& stripos( $ino, 'wp-json' ) === false );

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — plugin Chấm Công nối đúng, không chạm đường của máy.\n";
