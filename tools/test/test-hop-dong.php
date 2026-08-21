<?php
/**
 * PHÉP THỬ PLUGIN THƯ VIỆN HỢP ĐỒNG (wordpress/vhcp-hop-dong)
 *
 * Plugin này không giữ dữ liệu hợp đồng — nó chỉ có 3 việc, và cả 3 đều dễ hỏng im lặng:
 *   1. Cổng PIN đọc bảng người dùng DÙNG CHUNG với app Vận hành chi phí.
 *   2. Chuyển tiếp lệnh sang /exec của Apps Script, kèm khoá bí mật (khoá không được lọt ra).
 *   3. Danh sách hàm cho giao diện phải KHỚP với file CauNoi.gs — lệch là bấm nút không xảy ra gì.
 *
 * Chạy: php tools/test/test-hop-dong.php   (không cần WordPress, dùng SQLite)
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );      // để có bảng cfg + người dùng đã seed
vhd_test_boot( $goc . '/wordpress/vhcp-hop-dong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

// ---------------------------------------------------------------- 1. Danh sách hàm
$fns = VHD_Trang::ds_ham();
t( 'đọc được danh sách hàm từ cau-noi.gs', count( $fns ) > 30, count( $fns ) );
foreach ( array( 'getData', 'addContract', 'updateContract', 'getTienThueThang', 'apiCauHinhAI',
	'apiBocTachNhieuDong', 'goiYGianHang', 'apiNguonDuLieu' ) as $ham ) {
	t( 'hàm ' . $ham . ' có trong danh sách gửi cho giao diện', in_array( $ham, $fns, true ) );
}
t( 'có login', in_array( 'login', $fns, true ) );
t( 'có vhdLogout', in_array( 'vhdLogout', $fns, true ) );

// Giao diện gốc gọi hàm nào thì danh sách phải có đủ — đây là lưới chống "bấm nút không xảy ra gì".
// Quét chính file .gs để chắc CN_CHO_PHEP không bị xóa mất phần nào khi sửa file.
$gs = file_get_contents( $goc . '/wordpress/vhcp-hop-dong/apps-script/cau-noi.gs' );
t( 'CauNoi.gs có doPost', strpos( $gs, 'function doPost' ) !== false );
t( 'CauNoi.gs kiểm khoá WEB_KEY', strpos( $gs, 'WEB_KEY' ) !== false );
t( 'CauNoi.gs có đường lấy giao diện', strpos( $gs, '__giaoDien' ) !== false );
t( 'CauNoi.gs KHÔNG định nghĩa doGet (đè giao diện app gốc)', strpos( $gs, 'function doGet' ) === false );

// Danh sách hàm khai trong CauNoi.gs phải KHỚP hàm có thật trong Code.gs. Gõ sai một chữ là
// một tính năng chết — cầu nối báo lỗi rõ, nhưng chỉ báo lúc ai đó bấm đúng nút đó, có thể mấy
// tuần sau. Đối chiếu ở đây thì biết ngay.
$ds_ham_goc = array();
foreach ( file( $goc . '/wordpress/vhcp-hop-dong/apps-script/ham-code-gs.txt' ) as $dong ) {
	$dong = trim( $dong );
	if ( $dong === '' || $dong[0] === '#' ) { continue; }
	$ds_ham_goc[ $dong ] = 1;
}
t( 'đọc được danh sách hàm của Code.gs', count( $ds_ham_goc ) > 50, count( $ds_ham_goc ) );
$thieu = array();
foreach ( $fns as $ham ) {
	if ( in_array( $ham, array( 'login', 'vhdLogout' ), true ) ) { continue; }   // hàm của plugin
	if ( ! isset( $ds_ham_goc[ $ham ] ) ) { $thieu[] = $ham; }
}
teq( 'mọi hàm khai trong CauNoi.gs đều có thật trong Code.gs', array(), $thieu );

// Chiều ngược: KHÔNG được mở cửa cho hàm phá dữ liệu. Mấy hàm này xoá/ghi đè cả tab, hoặc chạy
// từ menu Sheets với hộp thoại riêng — cho gọi qua web là mở đường xoá sạch dữ liệu bằng một
// request. Danh sách phải KHÔNG chứa chúng.
$cam = array( 'xoaDuLieu3Tab', 'lamMoiTabChuan', 'taoTabDich', 'saveStaging', 'promoteStaging',
	'importHcmFromSheet', 'clearAllCache', 'apiLuuKhoaClaude', 'apiTrangThaiKhoaClaude',
	'apiThuGoiClaude', 'authorizeExtract' );
$lot = array();
foreach ( $cam as $ham ) {
	if ( in_array( $ham, $fns, true ) ) { $lot[] = $ham; }
}
teq( 'hàm xoá/ghi đè cả tab KHÔNG được gọi qua web', array(), $lot );

// ---------------------------------------------------------------- 2. Cầu nối
teq( 'chưa khai /exec thì báo rõ', false, VHD_CauNoi::goi( 'getData' )['ok'] );
t( 'thông báo chỉ đúng chỗ phải khai', strpos( VHD_CauNoi::goi( 'getData' )['error'], '/exec' ) !== false );

$GLOBALS['VHCP_OPT']['vhd_exec_url'] = 'https://script.google.com/macros/s/ABC/exec';
$khoa = VHD_CauNoi::bao_dam_khoa();
t( 'khoá sinh tự động, đủ dài', strlen( $khoa ) >= 32, strlen( $khoa ) );
teq( 'gọi lại không sinh khoá mới', $khoa, VHD_CauNoi::bao_dam_khoa() );

// App trả trang đăng nhập Google -> phải nói ĐÚNG việc cần làm, không nói "lỗi không rõ"
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => '<html><body>Sign in - accounts.google.com</body></html>' ) );
$r = VHD_CauNoi::goi( 'getData' );
teq( 'trang đăng nhập Google -> không ok', false, $r['ok'] );
t( 'chỉ ra phải đặt Who has access = Anyone', strpos( $r['error'], 'Anyone' ) !== false, $r['error'] );

// Chưa dán CauNoi.gs -> Apps Script báo không có hàm
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200, 'body' => 'Script function not found: doPost' ) );
t( 'chưa dán CauNoi.gs thì nói rõ', strpos( VHD_CauNoi::goi( 'getData' )['error'], 'CauNoi.gs' ) !== false );

// App trả lỗi có chủ đích
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => json_encode( array( 'ok' => false, 'error' => 'Sai khoá' ) ) ) );
teq( 'lỗi từ app được trả nguyên văn', 'Sai khoá', VHD_CauNoi::goi( 'getData' )['error'] );

// Chạy được
$GLOBALS['VHD_DA_GUI'] = array();
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => json_encode( array( 'ok' => true, 'data' => array( array( 'so_hd' => 'HD-1' ) ) ) ) ) );
$r = VHD_CauNoi::goi( 'getData', array( true ) );
t( 'gọi được', ! empty( $r['ok'] ), $r );
teq( 'dữ liệu về nguyên dạng', 'HD-1', $r['data'][0]['so_hd'] );

$da = json_decode( $GLOBALS['VHD_DA_GUI'][0]['body'], true );
teq( 'gửi đúng tên hàm', 'getData', $da['fn'] );
teq( 'gửi đúng tham số', array( true ), $da['args'] );
teq( 'gửi kèm khoá', $khoa, $da['key'] );

// ---------------------------------------------------------------- 3. Cổng PIN
// Bảng người dùng dùng chung: seed của app chi phí có Admin + nhân viên
$users = VHD_Auth::users();
t( 'đọc được người dùng từ bảng dùng chung', is_array( $users ) && count( $users ) > 0, $users );

$admin = null; $nv = null;
foreach ( $users as $u ) {
	if ( $u['vaiTro'] === 'Admin' && $u['pin'] !== '' ) { $admin = $u; }
	if ( $u['vaiTro'] === 'Nhân viên' && $u['pin'] !== '' && ! $nv ) { $nv = $u; }
}
t( 'có tài khoản Admin để thử', $admin !== null );

$r = VHD_Auth::login( $admin['pin'] );
t( 'Admin đăng nhập được', ! empty( $r['ok'] ), $r );
teq( 'trả đúng vai trò', 'Admin', $r['role'] );
t( 'phát token 64 ký tự', preg_match( '/^[0-9a-f]{64}$/', $r['token'] ) === 1 );

$u = VHD_Auth::user_by_token( $r['token'] );
teq( 'token tra ra đúng người', $admin['ten'], $u['name'] );
VHD_Auth::logout( $r['token'] );
teq( 'đăng xuất thì token hết hiệu lực', null, VHD_Auth::user_by_token( $r['token'] ) );

teq( 'PIN 3 số bị chặn', false, VHD_Auth::login( '123' )['ok'] );
teq( 'PIN không có trong danh sách bị chặn', false, VHD_Auth::login( '99999999' )['ok'] );

if ( $nv ) {
	$r = VHD_Auth::login( $nv['pin'] );
	teq( 'Nhân viên KHÔNG vào được thư viện hợp đồng', false, $r['ok'] );
	t( 'nói rõ là thiếu quyền, không nói PIN sai', strpos( $r['error'], 'không được xem' ) !== false, $r['error'] );
	t( 'nêu tên vai trò để biết vì sao', strpos( $r['error'], 'Nhân viên' ) !== false, $r['error'] );
}

// Chặn theo vai trò phải đúng cả ở CỔNG ĐĂNG NHẬP, không chỉ ở cửa API. Seed của app chi phí
// không chắc có Nhân viên nào được cấp PIN, nên dựng một người bằng danh sách riêng để chắc chắn
// đường này có phép thử canh.
$GLOBALS['VHCP_OPT']['vhd_nguon_nguoidung'] = 'rieng';
$GLOBALS['VHCP_OPT']['vhd_nguoidung'] = array(
	array( 'ten' => 'NV Cơ Sở', 'pin' => '445566', 'vaiTro' => 'Nhân viên', 'coso' => 'FARM PHAN THIẾT' ),
);
$r = VHD_Auth::login( '445566' );
teq( 'Nhân viên nhập PIN ĐÚNG vẫn không vào được', false, $r['ok'] );
t( 'nói rõ thiếu quyền chứ không nói PIN sai', strpos( $r['error'], 'không được xem' ) !== false, $r['error'] );
t( 'không phát token cho Nhân viên', empty( $r['token'] ) );

// Nguồn 'chung' mà không có bảng -> phải BÁO, không được âm thầm dùng danh sách khác
teq( 'đổi được sang danh sách riêng', 'rieng', VHD_Auth::nguon() );
$GLOBALS['VHCP_OPT']['vhd_nguoidung'] = array(
	array( 'ten' => 'KT Riêng', 'pin' => '778899', 'vaiTro' => 'Kế toán cá nhân', 'coso' => '' ),
);
$r = VHD_Auth::login( '778899' );
t( 'danh sách riêng đăng nhập được', ! empty( $r['ok'] ), $r );
teq( 'đúng tên', 'KT Riêng', $r['name'] );
$GLOBALS['VHCP_OPT']['vhd_nguon_nguoidung'] = 'chung';

// ---------------------------------------------------------------- 4. Cửa API
function goi_api( $fn, $args = array(), $token = '' ) {
	$req = new WP_REST_Request( array( 'fn' => $fn, 'args' => $args, 'token' => $token ) );
	$res = VHD_API::handle( $req );
	return array( 'status' => $res->get_status(), 'body' => $res->get_data() );
}

$a = goi_api( 'getData', array( true ) );
teq( 'chưa đăng nhập -> 401', 401, $a['status'] );
teq( 'kèm mã để giao diện biết mà mở lại cổng PIN', 'no_session', $a['body']['code'] );

$tok = VHD_Auth::phat_token( 'Chị Kế Toán', 'Kế toán cá nhân', '' );
$a = goi_api( 'getData', array( true ), $tok );
teq( 'có phiên thì gọi được', 200, $a['status'] );
t( 'lệnh được chuyển sang app gốc', ! empty( $a['body']['ok'] ), $a['body'] );

// Token của người KHÔNG đủ quyền (nhân viên) không mở được cửa, dù token thật
$tok_nv = VHD_Auth::phat_token( 'NV Xem Trộm', 'Nhân viên', '' );
teq( 'token vai trò Nhân viên -> 401', 401, goi_api( 'getData', array(), $tok_nv )['status'] );

teq( 'login là hàm công khai', 200, goi_api( 'login', array( '0000' ) )['status'] );

// Khoá bí mật KHÔNG được lọt vào thứ trả về trình duyệt
$json = json_encode( goi_api( 'getData', array( true ), $tok ), JSON_UNESCAPED_UNICODE );
t( 'khoá cầu nối không lọt xuống trình duyệt', strpos( $json, $khoa ) === false );
$cfg_json = json_encode( VHD_Trang::ds_ham(), JSON_UNESCAPED_UNICODE );
t( 'cấu hình gửi cho giao diện không chứa khoá', strpos( $cfg_json, $khoa ) === false );

// ---------------------------------------------------------------- 5. Giao diện gốc
VHD_CauNoi::xoa_cache_giao_dien();
$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200, 'body' => json_encode( array(
	'ok' => true, 'data' => '<html><head><title>Thư viện</title></head><body>xin chào</body></html>',
) ) ) );
$g = VHD_CauNoi::giao_dien();
t( 'lấy được giao diện gốc', ! empty( $g['ok'] ), $g );
t( 'là HTML của app gốc', strpos( $g['html'], 'xin chào' ) !== false );
teq( 'lần đầu không phải từ nhớ tạm', false, $g['tuCache'] );
teq( 'lần hai lấy từ nhớ tạm (khỏi ra mạng mỗi lần mở trang)', true, VHD_CauNoi::giao_dien()['tuCache'] );
VHD_CauNoi::xoa_cache_giao_dien();
teq( 'xoá nhớ tạm thì lấy lại từ app', false, VHD_CauNoi::giao_dien()['tuCache'] );

$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => json_encode( array( 'ok' => true, 'data' => '   ' ) ) ) );
VHD_CauNoi::xoa_cache_giao_dien();
teq( 'giao diện rỗng thì báo lỗi, không phục vụ trang trắng', false, VHD_CauNoi::giao_dien()['ok'] );

// ---------------------------------------------------------------- kết
if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — plugin Thư viện hợp đồng nối đúng.\n";
