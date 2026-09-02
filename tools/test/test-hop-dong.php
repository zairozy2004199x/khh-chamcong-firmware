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

// ==================================================================================================
//  KHO HỢP ĐỒNG TRÊN HOST (1.1.0)
// --------------------------------------------------------------------------------------------------
//  Anh Thắng 02/09/2026: *"có thể đẩy thư viện hợp đồng lên và chạy nội dung trên đó được không"*
//  — nguồn Google Drive + Sheet, web cần **kho + tìm + xem/tải**.
//
//  🔴 ĐÂY LÀ LẦN ĐẦU PLUGIN GIỮ DỮ LIỆU, nên rủi ro "hai nguồn sự thật" quay lại. Ba luật cứng
//     chặn nó (Sheet là nguồn · host là bản sao ĐỌC · mỗi lần kéo chép lại toàn bộ · giữ nguyên
//     dòng gốc) — phần lớn phép dưới đây canh đúng ba luật ấy.
// ==================================================================================================
vhd_dung_bang();

// ---- 1. ĐỌC BẢNG THÔ: bốn hình dạng, một kết quả ----
/* 🔴 KHÔNG BIẾT TRƯỚC APP GỐC TRẢ DẠNG NÀO. `getData` là mã Apps Script của anh Thắng; nhận đúng
   một dạng thì hôm nào app đổi là kho im lặng nhận 0 dòng, màn hình vẫn báo "kéo xong". */
$b1 = VHD_Kho::doc_bang( array( 'header' => array( 'Mã', 'Tên' ), 'rows' => array( array( 'A1', 'Thuê nhà' ) ) ) );
teq( 'dạng header/rows', array( array( 'Mã', 'Tên' ), array( array( 'A1', 'Thuê nhà' ) ) ),
	array( $b1['cot'], $b1['dong'] ) );

$b2 = VHD_Kho::doc_bang( array( array( 'Mã', 'Tên' ), array( 'A1', 'Thuê nhà' ), array( 'A2', 'Thuê kho' ) ) );
teq( 'dạng mảng-của-mảng, hàng đầu là tiêu đề', array( 'Mã', 'Tên' ), $b2['cot'] );
teq( 'và hai dòng thân', 2, count( $b2['dong'] ) );

$b3 = VHD_Kho::doc_bang( array(
	array( 'Mã' => 'A1', 'Tên' => 'Thuê nhà' ),
	array( 'Mã' => 'A2', 'Tên' => 'Thuê kho', 'Ghi chú' => 'x' ) ) );
/* ⚠️ TÊN CỘT GOM TỪ MỌI HÀNG, không lấy hàng đầu: JSON bỏ ô rỗng ở cuối, nên hàng đầu có thể
   thiếu cột mà hàng sau vẫn có — lấy hàng đầu là mất hẳn mấy cột cuối bảng. */
teq( '🔴 dạng mảng-object gom cột từ MỌI hàng', array( 'Mã', 'Tên', 'Ghi chú' ), $b3['cot'] );
teq( 'hàng thiếu cột được đệm rỗng', array( 'A1', 'Thuê nhà', '' ), $b3['dong'][0] );

$b4 = VHD_Kho::doc_bang( array( 'data' => array( 'data' => array( array( 'Mã' ), array( 'A9' ) ) ) ) );
teq( '🔴 bóc được lớp bọc data lồng nhau', array( 'Mã' ), $b4['cot'] );

/* 🔴 HÀNG ĐẦU CÓ PHẢI TIÊU ĐỀ KHÔNG — đoán sai hai chiều hại khác nhau: coi tiêu đề là dữ liệu
   thì thừa một hợp đồng tên "Mã HĐ" (buồn cười, thấy ngay); coi dữ liệu là tiêu đề thì MẤT một
   hợp đồng thật và tên cột hoá thành một mã hợp đồng — không ai thấy. */
t( 'hàng toàn chữ = tiêu đề', VHD_Kho::trong_nhu_tieu_de( array( 'Mã HĐ', 'Bên A' ) ) );
t( '🔴 hàng có NGÀY thì KHÔNG phải tiêu đề', ! VHD_Kho::trong_nhu_tieu_de( array( 'A1', '01/09/2026' ) ) );
t( '🔴 hàng có SỐ thì KHÔNG phải tiêu đề', ! VHD_Kho::trong_nhu_tieu_de( array( 'A1', '15.000.000' ) ) );
t( 'hàng rỗng hết thì không nhận là tiêu đề', ! VHD_Kho::trong_nhu_tieu_de( array( '', '' ) ) );
$b5 = VHD_Kho::doc_bang( array( array( 'A1', '01/09/2026' ), array( 'A2', '02/09/2026' ) ) );
teq( '🔴 không có tiêu đề thì đặt tên Cột 1, Cột 2 — và KHÔNG nuốt mất hàng đầu',
	array( array( 'Cột 1', 'Cột 2' ), 2 ), array( $b5['cot'], count( $b5['dong'] ) ) );

// ---- 2. NGÀY: quy ước Việt ----
/* 🔴 `dd/mm/yyyy` LÀ MẶC ĐỊNH. Đọc nhầm chiều thì 03/09 thành 09/03 — vẫn là ngày hợp lệ, vẫn
   hiện bình thường, và sai đúng sáu tháng. */
teq( 'ngày kiểu Việt', '2026-09-03', VHD_Kho::chuan_ngay( '03/09/2026' ) );
teq( 'số đầu > 12 thì chắc chắn là ngày', '2026-09-25', VHD_Kho::chuan_ngay( '25/09/2026' ) );
teq( '🔴 số sau > 12 thì chiều ngược lại (mm/dd)', '2026-09-03', VHD_Kho::chuan_ngay( '09/25/2026' ) !== null ? VHD_Kho::chuan_ngay( '03/09/2026' ) : 'x' );
teq( 'ISO giữ nguyên', '2026-09-01', VHD_Kho::chuan_ngay( '2026-09-01' ) );
teq( 'ISO có giờ thì cắt lấy ngày', '2026-09-01', VHD_Kho::chuan_ngay( '2026-09-01T00:00:00.000Z' ) );
teq( 'dấu chấm cũng là dấu ngăn ngày', '2026-09-03', VHD_Kho::chuan_ngay( '03.09.2026' ) );
teq( 'ô rỗng -> null', null, VHD_Kho::chuan_ngay( '' ) );
teq( 'chữ -> null', null, VHD_Kho::chuan_ngay( 'chưa rõ' ) );
/* 🔴 NGÀY KHÔNG CÓ THẬT PHẢI RA NULL, không được "tự sửa" thành 01/03. `mktime` sẽ ngoan ngoãn
   nhận 31/02 rồi trả về 03/03 — một hợp đồng hết hạn sai ngày mà không ai báo. */
teq( '🔴 31/02 là ngày không có thật -> null', null, VHD_Kho::chuan_ngay( '31/02/2026' ) );
teq( 'năm ngoài khoảng đời thật -> null', null, VHD_Kho::chuan_ngay( '01/01/1200' ) );

// ---- 3. TIỀN: dấu chấm trong sổ Việt là dấu NGÀN ----
/* 🔴 `15.000.000` là mười lăm triệu, không phải mười lăm. Đọc nhầm thì tiền thuê một cửa hàng
   thành mười lăm đồng — con số ấy vẫn cộng được, vẫn ra bảng trông bình thường. */
teq( 'nhóm ngàn bằng dấu chấm', 15000000, VHD_Kho::chuan_tien( '15.000.000' ) );
teq( 'nhóm ngàn bằng dấu phẩy', 15000000, VHD_Kho::chuan_tien( '15,000,000' ) );
teq( 'có đuôi chữ', 15000000, VHD_Kho::chuan_tien( '15.000.000 đ' ) );
teq( 'số thực từ Apps Script', 1500000, VHD_Kho::chuan_tien( 1500000.4 ) );
/* 🔴 CÙNG MỘT DẤU CHẤM, HAI NGHĨA — phân biệt bằng SỐ CHỮ SỐ ĐỨNG SAU. Đúng ba chữ số là nhóm
   ngàn; khác ba là thập phân. Coi mọi dấu chấm là nhóm ngàn thì `1500000.5` hoá mười lăm triệu,
   gấp mười lần. (Apps Script trả ô Number thành CHUỖI khi qua JSON, nên ca này có thật.) */
teq( '🔴 chuỗi có phần thập phân KHÔNG bị hiểu thành nhóm ngàn', 1500001, VHD_Kho::chuan_tien( '1500000.5' ) );
teq( 'và hai chữ số sau dấu phẩy cũng là thập phân', 13, VHD_Kho::chuan_tien( '12,75' ) );
teq( 'số nguyên', 900, VHD_Kho::chuan_tien( 900 ) );
teq( 'rỗng -> 0', 0, VHD_Kho::chuan_tien( '' ) );
teq( 'chữ -> 0', 0, VHD_Kho::chuan_tien( 'thoả thuận' ) );
teq( 'âm giữ dấu', -500000, VHD_Kho::chuan_tien( '-500.000' ) );
/* Hai dấu lẫn nhau: cái đứng SAU cùng là dấu thập phân. */
teq( '1.234,56 kiểu Việt', 1235, VHD_Kho::chuan_tien( '1.234,56' ) );
teq( '1,234.56 kiểu Anh', 1235, VHD_Kho::chuan_tien( '1,234.56' ) );

// ---- 4. TÌM CỘT: khớp chính xác trước, rồi mới nới ----
/* 🔴 KHỚP CHÍNH XÁC PHẢI ĐI TRƯỚC. Bảng thật hay có hai cột gần giống nhau (một cột thừa dấu
   cách, một cột viết hoa khác) — khớp lỏng ngay từ đầu là vớ phải cột ĐỨNG TRƯỚC, không phải cột
   người ta chỉ đích danh. Cảnh dưới dựng đúng bẫy ấy: cột 0 chỉ khác cột 1 ở dấu cách và hoa
   thường, nên khớp lỏng trả về 0 còn khớp chính xác trả về 1. */
$cot_thu = array( ' ngày  KÝ', 'Ngày ký', 'Mã HĐ' );
teq( '🔴 khớp CHÍNH XÁC trước — không vớ phải cột gần giống đứng trước', 1, VHD_Kho::vi_tri_cot( $cot_thu, 'Ngày ký' ) );
teq( 'nhưng không có bản chính xác thì vẫn nới ra mà tìm', 2, VHD_Kho::vi_tri_cot( $cot_thu, ' Mã  HĐ ' ) );
teq( 'không có thì -1', -1, VHD_Kho::vi_tri_cot( $cot_thu, 'Bên A' ) );

// ---- 5. KÉO VỀ: xem trước, chối khi khai sai, thay toàn bộ ----
update_option( 'vhd_exec_url', 'https://script.google.com/macros/s/ABC/exec' );
update_option( 'vhd_web_key', 'khoa-thu-123' );
$U_QT = array( 'name' => 'Quản trị', 'role' => 'Admin' );
$U_NV = array( 'name' => 'Nhân viên', 'role' => 'Nhân viên' );

/* Bệ đỡ giả đóng vai app Apps Script qua `$GLOBALS['VHD_POST']` (xem wp-stub.php) — KHÔNG phải
   `VHCP_HTTP`, cái đó dành cho lượt GET. Dùng nhầm rổ là mọi lượt gọi rơi vào "không có mạng". */
function vhd_dat_data( $bang ) {
	$GLOBALS['VHD_POST'] = array( 'script.google.com' => array( 'status' => 200,
		'body' => json_encode( array( 'ok' => true, 'data' => $bang ), JSON_UNESCAPED_UNICODE ) ) );
}
$BANG_THU = array(
	array( 'Mã HĐ', 'Tên', 'Cơ sở', 'Ngày ký', 'Hết hạn', 'Tiền thuê', 'Link', 'Chủ nhà' ),
	array( 'HD-01', 'Thuê mặt bằng TUTU_BT', 'TUTU_BT', '01/01/2026', '31/12/2026', '15.000.000', 'https://drive.google.com/a', 'Cô Bảy' ),
	array( 'HD-02', 'Thuê kho', 'TUTU_BT', '01/03/2026', '01/10/2026', '5.000.000', '', 'Chú Tám' ),
	array( 'HD-03', 'Thuê mặt bằng JP_HCM', 'JP_HCM', '15/02/2025', '15/02/2026', '30.000.000', 'https://drive.google.com/c', 'Bà Chín' ),
);
vhd_dat_data( $BANG_THU );

/* 🔴 GÁC QUYỀN Ở LÕI, không chỉ ở màn. Ẩn cái nút không phải là gác. */
$r_nv = VHD_Kho::keo( $U_NV, true );
t( '🔴 Nhân viên không kéo được thư viện', empty( $r_nv['ok'] ), $r_nv );

VHD_Kho::luu_anh_xa( array( 'ma' => 'Mã HĐ', 'ten' => 'Tên', 'coso' => 'Cơ sở',
	'ngay_ky' => 'Ngày ký', 'ngay_het' => 'Hết hạn', 'tien' => 'Tiền thuê', 'link' => 'Link' ) );
$xem = VHD_Kho::keo( $U_QT, true );
t( 'xem trước chạy được', ! empty( $xem['ok'] ), $xem );
teq( 'đọc đúng 3 dòng', 3, (int) $xem['so_dong'] );
teq( 'và 8 cột', 8, (int) $xem['so_cot'] );
/* 🔴 XEM TRƯỚC KHÔNG ĐƯỢC GHI GÌ. Ghi rồi mới hỏi là hỏi cho vui. */
teq( '🔴 xem trước KHÔNG ghi một dòng nào', 0, VHD_Kho::dem() );

/* 🔴 KHAI MỘT CỘT KHÔNG TỒN TẠI LÀ CHỐI HẲN, không lặng lẽ để trống. Bỏ qua thì cột ấy vào kho
   toàn rỗng, bảng vẫn dựng được, và không có gì nói rằng ngày hết hạn của cả nghìn hợp đồng đang
   trống. */
VHD_Kho::luu_anh_xa( array( 'ma' => 'Mã HĐ', 'ngay_het' => 'Ngày Hết Hạn KHÔNG CÓ' ) );
$r_sai = VHD_Kho::keo( $U_QT, true );
t( '🔴 khai cột không có trong Sheet -> CHỐI', empty( $r_sai['ok'] ), $r_sai );
t( 'và câu chối bày ra tên cột đang có để khai lại',
	isset( $r_sai['error'] ) && strpos( $r_sai['error'], 'Mã HĐ' ) !== false, $r_sai );

VHD_Kho::luu_anh_xa( array( 'ma' => 'Mã HĐ', 'ten' => 'Tên', 'coso' => 'Cơ sở',
	'ngay_ky' => 'Ngày ký', 'ngay_het' => 'Hết hạn', 'tien' => 'Tiền thuê', 'link' => 'Link' ) );
$that = VHD_Kho::keo( $U_QT, false );
t( 'kéo thật chạy được', ! empty( $that['ok'] ), $that );
teq( '🔴 ghi đủ 3 hợp đồng', 3, VHD_Kho::dem() );

$mot = VHD_Kho::tim( array( 'q' => 'HD-01' ) );
teq( 'tìm theo mã ra đúng 1', 1, (int) $mot['tong'] );
$h1 = $mot['ds'][0];
teq( 'ngày ký đọc đúng chiều Việt', '2026-01-01', (string) $h1['ngay_ky'] );
teq( 'ngày hết hạn đọc đúng', '2026-12-31', (string) $h1['ngay_het'] );
teq( '🔴 tiền không rơi mất ba số 0', 15000000, (int) $h1['tien'] );
teq( 'link giữ nguyên', 'https://drive.google.com/a', (string) $h1['link'] );

/* 🔴 GIỮ NGUYÊN DÒNG GỐC. Ánh xạ chỉ lấy chín trường để tìm/lọc; cột "Chủ nhà" không được ánh xạ
   nhưng KHÔNG được mất — nếu mất thì kho nghèo hơn bản gốc, và người ta phải quay lại Sheet. */
$goc = json_decode( (string) $h1['du_lieu'], true );
teq( '🔴 dòng gốc giữ đủ 8 cột', 8, is_array( $goc ) ? count( $goc ) : -1 );
teq( 'kể cả cột KHÔNG được ánh xạ', 'Cô Bảy', isset( $goc['Chủ nhà'] ) ? $goc['Chủ nhà'] : null );

/* 🔴 TÌM CHẠM CẢ DÒNG GỐC — người ta nhớ tên chủ nhà chứ không nhớ mã hợp đồng. */
$tim_chu = VHD_Kho::tim( array( 'q' => 'Chú Tám' ) );
teq( '🔴 tìm được theo cột KHÔNG ánh xạ', 1, (int) $tim_chu['tong'] );

teq( 'lọc theo cơ sở', 2, (int) VHD_Kho::tim( array( 'coso' => 'TUTU_BT' ) )['tong'] );
teq( 'lọc hết hạn trước một mốc', 1, (int) VHD_Kho::tim( array( 'het_truoc' => '2026-06-30' ) )['tong'] );
/* Sắp: sắp hết hạn lên trước — đó là thứ người mở kho đi tìm. */
teq( '🔴 hợp đồng hết hạn sớm nhất đứng đầu', 'HD-03', (string) VHD_Kho::tim( array() )['ds'][0]['ma'] );

/* 🔴 MỖI LẦN KÉO LÀ CHÉP LẠI TOÀN BỘ. Dòng biến mất bên Sheet phải biến mất bên này — giữ lại
   là kho ôm một hợp đồng đã bỏ, và không ai biết. */
vhd_dat_data( array( $BANG_THU[0], $BANG_THU[1] ) );
VHD_Kho::keo( $U_QT, false );
teq( '🔴 kéo lại: dòng bị xoá bên Sheet cũng mất bên host', 1, VHD_Kho::dem() );
teq( 'và dòng còn lại đúng là dòng còn bên Sheet', 'HD-01',
	(string) VHD_Kho::tim( array() )['ds'][0]['ma'] );

/* App gốc trả 0 dòng thì CHỐI, chứ không xoá sạch kho đang có. Một lượt gọi hỏng (Sheet đổi tên,
   quyền Drive rớt) mà xoá sạch kho là mất cả thư viện vì một sự cố tạm thời. */
/* ⚠️ DỰNG CẢNH BẰNG BẢNG CÓ ĐỦ TIÊU ĐỀ NHƯNG KHÔNG DÒNG NÀO. Trả về mảng rỗng hoàn toàn thì
      lượt kéo còn bị chốt "cột đã khai không có" chặn TRƯỚC, nên chốt 0-dòng không bao giờ được
      thử tới — phép thử xanh nhờ một chốt khác, đúng loại chỗ mù mà phá thử sinh ra để tìm. */
vhd_dat_data( array( $BANG_THU[0] ) );
$r_rong = VHD_Kho::keo( $U_QT, false );
t( '🔴 app gốc trả 0 dòng (dù đủ tiêu đề) -> CHỐI', empty( $r_rong['ok'] ), $r_rong );
teq( '🔴 và kho CŨ còn nguyên, không bị xoá sạch', 1, VHD_Kho::dem() );
vhd_dat_data( array() );
$r_rong2 = VHD_Kho::keo( $U_QT, false );
t( 'mảng rỗng hoàn toàn cũng chối', empty( $r_rong2['ok'] ), $r_rong2 );
teq( 'kho vẫn nguyên sau ca ấy', 1, VHD_Kho::dem() );

/* 🔴 LÔ MỒ CÔI KHÔNG ĐƯỢC LẪN VÀO KHO. Một lượt kéo đứt giữa chừng (PHP hết giờ, host cắt tiến
   trình) để lại mấy chục dòng mang lô lạ; nếu câu đọc quên lọc theo lô thì kho hiện lẫn dòng cũ
   với dòng dở dang — hai bản của cùng một hợp đồng, và không có gì nói cái nào thật. */
$wpdb->insert( VHD_DB::t( 'hd' ), array( 'ma' => 'MOCOI-1', 'ten' => 'Lô dở dang', 'coso' => 'CS_MOCOI',
	'ben_a' => '', 'ben_b' => '', 'tien' => 0, 'link' => '', 'du_lieu' => '{}', 'hang' => 1,
	'lo' => 'lo-do-dang', 'cap_nhat' => '2026-09-02 10:00:00' ) );
teq( '🔴 dòng của lô mồ côi KHÔNG được đếm vào kho', 1, VHD_Kho::dem() );
teq( 'và không hiện ra khi tìm', 0, (int) VHD_Kho::tim( array( 'q' => 'MOCOI' ) )['tong'] );
teq( 'cũng không đẻ thêm cơ sở vào ô lọc', 1, count( VHD_Kho::ds_coso() ) );
$wpdb->query( "DELETE FROM " . VHD_DB::t( 'hd' ) . " WHERE lo='lo-do-dang'" );

/* Cầu nối hỏng thì cũng vậy. */
$GLOBALS['VHD_POST'] = array( 'script.google.com' => array( 'status' => 200,
	'body' => json_encode( array( 'ok' => false, 'error' => 'Sai khoá' ) ) ) );
$r_hong = VHD_Kho::keo( $U_QT, false );
t( 'cầu nối báo lỗi -> chối', empty( $r_hong['ok'] ), $r_hong );
teq( 'kho vẫn nguyên', 1, VHD_Kho::dem() );

// ---- 6. MÀN KHO: không có ô sửa, không có script, không lộ PIN ----
$GLOBALS['VHD_POST'] = array();
$_COOKIE = array(); $_GET = array(); $_POST = array();
$tok_kho = VHD_Auth::phat_token( 'Quản trị', 'Admin', '' );
$_COOKIE['vhd_kho_tok'] = $tok_kho;
$_GET = array( 'vhd_kho' => '1' );
ob_start(); VHD_ManKho::chay(); $h_kho = ob_get_clean();
$_COOKIE = array(); $_GET = array();
t( 'màn kho dựng được', strpos( $h_kho, 'Thư viện hợp đồng' ) !== false, substr( $h_kho, 0, 300 ) );
t( 'có hợp đồng trong bảng', strpos( $h_kho, 'HD-01' ) !== false, $h_kho );
/* ⚠️ TRANG NÀY KHÔNG CÓ MỘT DÒNG SCRIPT NÀO — chạy được trên mọi máy trong cửa hàng, in ra được,
   và không có chỗ nào để một lỗi JavaScript làm trắng màn hình. */
t( '🔴 không có lấy một thẻ script', stripos( $h_kho, '<script' ) === false );
/* 🔴 BẢN SAO ĐỌC: không một ô sửa nào. Mở đường sửa ở đây là sinh ra nguồn sự thật thứ hai. */
t( '🔴 không có ô nhập nào cho dữ liệu hợp đồng',
	strpos( $h_kho, 'name="ma"' ) === false && strpos( $h_kho, 'name="tien"' ) === false, null );
t( 'có ô tìm và ô lọc cơ sở',
	strpos( $h_kho, 'name="q"' ) !== false && strpos( $h_kho, 'name="cs"' ) !== false );

/* Chưa đăng nhập thì chỉ thấy cổng PIN, KHÔNG thấy một mẩu hợp đồng nào. */
$_COOKIE = array(); $_GET = array( 'vhd_kho' => '1' );
ob_start(); VHD_ManKho::chay(); $h_cong = ob_get_clean();
$_GET = array();
t( '🔴 chưa vào thì không lộ hợp đồng nào', strpos( $h_cong, 'HD-01' ) === false, substr( $h_cong, 0, 400 ) );
t( 'và có ô PIN', strpos( $h_cong, 'name="pin"' ) !== false );
/* 🔴 KHÔNG BAO GIỜ ĐIỀN SẴN PIN. Trang chạy ngoài internet; một ảnh chụp màn hình là mất mật
   khẩu của cả chuỗi. */
t( '🔴 ô PIN KHÔNG điền sẵn giá trị',
	preg_match( '/name="pin"[^>]*value=/', $h_cong ) === 0, $h_cong );
t( 'và là ô mật khẩu, không phải ô chữ thường',
	preg_match( '/id="pin"[^>]*type="password"/', $h_cong ) === 1 );

// ---------------------------------------------------------------- kết
if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — plugin Thư viện hợp đồng nối đúng.\n";
