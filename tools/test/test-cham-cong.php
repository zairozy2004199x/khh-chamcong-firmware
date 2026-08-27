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

/* ⚠️ IN BÁO CÁO KỂ CẢ KHI CHẾT GIỮA ĐƯỜNG.
   Đã ba lần trong việc này: một phép phá làm mã ném fatal error, bài kiểm chết ngay đó, và không
   in ra được chỗ nào sai lẫn còn bao nhiêu phép thử chưa chạy — nên trông y như "phép thử không
   bắt được". Nó BẮT ĐƯỢC, chỉ là báo cáo bị chôn cùng. Hàm này in phần đã tích luỹ ra trước khi
   PHP tắt, nên trượt luôn ra trượt, và sập thì nói rõ là sập ở đâu. */
register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		return;                                     // kết thúc bình thường -> phần dưới đã in
	}
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	if ( $truot ) {
		echo 'Trước khi chết đã bắt được ' . count( $truot ) . " chỗ trượt:\n";
		foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	}
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

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
$ham_app = array_values( array_diff( $fns, array( 'login', 'vhccLogout' ) ) );
t( 'vẫn có login', in_array( 'login', $fns, true ) );

/* 🔴 22/08/2026 — DANH SÁCH CHO PHÉP RÚT TỪ 27 XUỐNG 4.
   Cả khối 23 hàm máy + OTA đã chuyển sang chạy thẳng trên host, không đi qua cầu nối nữa. Phép
   thử này ĐỔI THEO, và đổi theo hướng CHẶT HƠN: trước cho phép 27 tên, nay chỉ 4.

   Vì sao đáng canh: `setOtaTarget` đẩy firmware cho CẢ CHUỖI — ai gọi được nó là nạp được phần
   mềm tuỳ ý vào 26 máy. Còn tên nó trong danh sách thì con đường đó vẫn mở dù không ai dùng.

   Bốn hàm còn lại đều CHỈ ĐỌC và đều là việc TẠM: nạp dữ liệu cũ từ Sheet sang. Nạp xong thì
   danh sách này về 0 và cầu nối không còn lý do tồn tại.

   Con số ở đây phải sửa TAY mỗi lần thêm hàm — mỗi hàm khai vào cầu nối là một cửa mới mở ra cho
   web, nên phải là quyết định có ý thức, không phải phép thử tự chạy theo mã. */
$DOC_THEM = array( 'getEmployees', 'ccDsCoSoXuat', 'ccXuatChamCong', 'ccXuatPhanQuyen' );
teq( 'cầu nối chỉ còn khai 4 hàm đọc để nạp dữ liệu cũ', 4, count( $ham_app ) );
sort( $ham_app );
$mong = $DOC_THEM; sort( $mong );
teq( 'và đúng danh sách đó, không thừa không thiếu', $mong, $ham_app );
/* Hàm máy/OTA KHÔNG được còn tên nào trong cầu nối — chúng chạy trên host rồi. */
$MAY_CU = array(
	'getDanhSachMay', 'getMachineStatus', 'getMachineRoster', 'chanDoanMay',
	'getQueueMay', 'getHangDoiTaiLai', 'xemKhoiTest', 'getLuongMayTuDong', 'getGiaMayTuDong',
	'ganMayVaoCuaHang', 'boGanMay', 'luuSimMay', 'requestMachineScan',
	'xoaLenhQueue', 'xoaLenhTaiLai', 'dungTaiLai', 'setGiaMayTuDong', 'donKhoiTest', 'requestBackfill',
	'getFwMoiNhat', 'getOtaTarget', 'setOtaTarget', 'clearOtaTarget' );
$con_sot = array_intersect( $MAY_CU, $ham_app );
t( 'KHÔNG còn hàm máy/OTA nào trong cầu nối (nhất là setOtaTarget)',
	count( $con_sot ) === 0, implode( ', ', $con_sot ) );
teq( 'không khai trùng tên', count( $ham_app ), count( array_unique( $ham_app ) ) );

/* ⚠️ Mấy hàm dưới đây TUYỆT ĐỐI không được khai vào cầu nối. Đây là loại đụng HÀNG LOẠT hoặc
   ghi đè cả sheet — cho gọi qua web là phá dữ liệu chấm công bằng một request, và phá xong thì
   không có bản nào để lùi lại. Danh sách này lấy từ bản kê 111 hàm. */
$CAM = array( 'capPinHangLoat', 'dongBoPinTheoSheet', 'nhapNhanSu', 'xemTruocNhapNhanSu',
	'xoaThongKeDay', 'donSheetChamCong', 'deleteEmployee', 'doiMaNV' );
$lot = array_intersect( $CAM, $fns );
t( 'KHÔNG khai hàm đụng hàng loạt / ghi đè cả sheet vào cầu nối', count( $lot ) === 0,
	implode( ', ', $lot ) );

/* Ba hàm NGUY được khai có chủ ý — phải có ghi chú giải thích ngay cạnh, không thì lần sau ai đọc
   cũng tưởng là khai lỡ. */
t( 'có ghi chú vì sao dám khai hàm đẩy firmware',
	strpos( $gs, 'setOtaTarget' ) !== false && strpos( $gs, 'NGUY' ) !== false
	&& strpos( $gs, 'isAdmin' ) !== false );
t( 'và nói rõ hai lớp gác sẵn có (WEB_KEY + isAdmin)',
	strpos( $gs, 'WEB_KEY' ) !== false && strpos( $gs, 'hai lớp gác' ) !== false );

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

/* 🔴 TỪ 25/08/2026 CỬA KHÔNG CÒN Ở CỔNG. Anh Thắng chốt mô hình năm bậc: ai cũng vào được,
   thấy gì thì do QUYỀN quyết. Trước đó nhân viên gõ PIN đúng vẫn bị chối ngay cửa — mà việc
   họ cần chỉ là chấm công và xem công của chính mình. */
teq( 'Nhân viên VÀO ĐƯỢC cổng', true, in_array( 'Nhân viên', VHCC_Auth::vai_tro_vao(), true ) );
teq( 'Cửa hàng trưởng vào được', true, in_array( 'Cửa hàng trưởng', VHCC_Auth::vai_tro_vao(), true ) );
teq( 'Admin vào được', true, in_array( 'Admin', VHCC_Auth::vai_tro_vao(), true ) );

/* Tuỳ chọn cũ `vhcc_vai_tro_vao` CỐ Ý bị bỏ qua: nó chặn ở đúng chỗ không nên chặn, và một ô
   tích quên bỏ ở đó khoá cửa cả công ty mà không ai ngờ tới nó. */
$GLOBALS['VHCP_OPT']['vhcc_vai_tro_vao'] = array( 'Admin' );
teq( 'ô tích cũ KHÔNG còn khoá được ai ra ngoài', true,
	in_array( 'Nhân viên', VHCC_Auth::vai_tro_vao(), true ) );
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
/* Phiên của Nhân viên nay HỢP LỆ ở cửa API — cửa chuyển sang từng việc. `layBangCong` lọc theo
   cơ sở ở lớp dưới (`co_quyen_coso` trả false cho Nhân viên), nên họ vào mà không thấy gì của
   ai. Đó mới là tách quyền: chối ĐÚNG dữ liệu, không chối cả cánh cửa. */
teq( 'phiên Nhân viên hợp lệ (cửa nằm ở từng việc, không ở cổng)', true,
	null !== VHCC_Auth::user_by_token( $tok_nv ) );
teq( 'nhưng Nhân viên KHÔNG có quyền cơ sở nào', false,
	VHCC_NhanSu::co_quyen_coso( VHCC_Auth::user_by_token( $tok_nv ), 'TUTU_BT' ) );

$GLOBALS['VHD_POST'] = array( '/exec' => array( 'code' => 200,
	'body' => json_encode( array( 'ok' => true, 'data' => array( 1, 2 ) ) ) ) );
$a = goi_cc( 'layBangCong', array(), $tok_cc );
teq( 'kế toán có phiên thì gọi được', 200, $a['status'] );
teq( 'login là hàm công khai', 200, goi_cc( 'login', array( '0000' ) )['status'] );

$json = json_encode( goi_cc( 'layBangCong', array(), $tok_cc ), JSON_UNESCAPED_UNICODE );
t( 'khoá cầu nối không lọt xuống trình duyệt', strpos( $json, $khoa ) === false );

// ============================================================ 6. Firmware: một đường duy nhất
/* 🔴 MỤC NÀY ĐỔI HẲN 22/08/2026. Trước đây nó canh "đừng đụng vào firmware": firmware gọi
   /macros/s/<id>/exec, và KHÔNG được có địa chỉ WordPress nào. Anh Thắng chốt cho cả hệ thống
   chạy thẳng trên host nên luật đó lật ngược — nay canh điều ngược lại, và canh chặt hơn. */
$ino = file_get_contents( $goc . '/esp32_hik_chamcong_full/esp32_hik_chamcong_full.ino' );
/* Chỉ soi MÃ, bỏ hết chú thích: cả tệp đầy lời giải thích "trước kia gọi /exec, nay bỏ" — mà
   mấy lời đó đáng giữ. Cấm cả chữ trong chú thích là ép xoá lịch sử để qua bài kiểm. */
$ino_ma = preg_replace( '#/\*.*?\*/#s', '', $ino );
$ino_ma = preg_replace( '#//[^\n]*#', '', $ino_ma );
t( 'firmware KHÔNG còn gọi Apps Script (/macros/s/…/exec)',
	strpos( $ino_ma, '/macros/s/' ) === false );
/* KHÔNG cấm cả chữ "firebasedatabase": `wpUrlHopLe()` phải NHẮC nó để TỪ CHỐI link Firebase cũ
   bị dán nhầm vào ô website — cấm chữ là ép gỡ đúng cái lưới đang che. Cấm LỜI GỌI. */
t( 'firmware KHÔNG còn đọc/ghi Firebase',
	strpos( $ino_ma, 'FB_HOST' ) === false && strpos( $ino_ma, 'FB_SECRET' ) === false
	&& strpos( $ino_ma, 'fbAuthParam' ) === false && strpos( $ino_ma, '.json?auth' ) === false
	&& stripos( $ino_ma, 'firebaseio' ) === false );
t( 'nhưng VẪN từ chối link Firebase cũ bị dán nhầm vào ô website',
	strpos( $ino_ma, '.firebasedatabase.app' ) !== false );
t( 'và không còn mang token web app',
	strpos( $ino_ma, 'EMP_TOKEN' ) === false && strpos( $ino_ma, 'SEC_EMP_TOKEN' ) === false );
/* Đường của máy phải trỏ đúng cổng nhận, và cổng đó là hằng trong plugin — hai bên lệch nhau
   một chữ là máy đẩy ra 404 mà log trông như lỗi mạng. */
t( 'firmware đẩy vào đúng đường ' . VHCC_Nhan::DUONG,
	strpos( $ino, '/' . VHCC_Nhan::DUONG ) !== false );
/* Soi trong MÃ, không phải trong chú thích: bản đầu của phép thử này soi cả tệp, mà cả hai
   chuỗi đều có mặt trong lời giải thích — nên gỡ sạch header đi bài kiểm vẫn xanh. Đã thử phá
   và bắt được đúng lỗ đó. */
/* Soi TRONG THÂN `wpGoi` — cửa duy nhất ra ngoài. Bản đầu soi cả tệp, mà chuỗi khoá cũng có
   mặt ở `fetchPhotoDecoded`, nên gỡ khoá khỏi `wpGoi` mà bài kiểm vẫn xanh. Đã thử phá và bắt
   được đúng lỗ đó. Gỡ khoá khỏi cửa đó = MỌI lượt chấm công của máy 4G bị trả 401. */
$i_wg  = strpos( $ino_ma, 'String wpGoi(const String& body, bool docThan){' );
$i_wg2 = strpos( $ino_ma, 'String wpViec(', $i_wg );
$than_wg = ( false !== $i_wg && false !== $i_wg2 ) ? substr( $ino_ma, $i_wg, $i_wg2 - $i_wg ) : '';
t( 'tìm được thân hàm wpGoi để soi', strlen( $than_wg ) > 300 );
t( 'firmware gửi khoá máy trong header', strpos( $than_wg, 'X-VHCC-Key' ) !== false );
t( 'và gửi cả trong thân JSON (đường 4G không đặt được header tuỳ ý)',
	strpos( $than_wg, '\\"key\\":' ) !== false && strpos( $than_wg, 'wp_key' ) !== false );
t( 'màn/portal gọi đúng tên hằng VHCC_KHOA_MAY để người đọc biết khai ở đâu',
	strpos( $ino, 'VHCC_KHOA_MAY' ) !== false );
/* Bản .bin do CI build nằm ở chỗ tải công khai — KHÔNG được chứa link hay khoá thật. */
t( 'firmware KHÔNG ghi cứng link hay khoá thật (chỉ placeholder)',
	strpos( $ino, 'khmatrix' ) === false && strpos( $ino, 'AKfycb' ) === false );
$ci_h = file_get_contents( $goc . '/esp32_hik_chamcong_full/ci/secrets.ci.h' );
t( 'secrets của CI vẫn toàn placeholder',
	substr_count( $ci_h, '"__CHUA_CAU_HINH__"' ) === substr_count( $ci_h, '#define SEC_' ) );
t( 'và không còn khai khoá Firebase / token web app nào',
	strpos( $ci_h, 'SEC_FB_SECRET' ) === false && strpos( $ci_h, 'SEC_EMP_TOKEN' ) === false
	&& strpos( $ci_h, 'SEC_EXEC_URL' ) === false && strpos( $ci_h, 'SEC_FB_HOST' ) === false );

// ============================================================ 7. Bản gốc lưu trong repo CÔNG KHAI
/* Hai tệp `goc/` là giao diện thật anh Thắng dán vào. Repo này công khai, nên phép thử dưới đây
   canh đúng một điều: đừng có ngày nào ai dán bản mới kèm khoá vào đây mà không ai thấy. */
$goc_ui = $goc . '/wordpress/vhcp-cham-cong/goc';
foreach ( array( 'Index.html', 'ChamCongOnline.html' ) as $ten_ui ) {
	$ui = file_get_contents( $goc_ui . '/' . $ten_ui );
	t( "$ten_ui có mặt và không rỗng", strlen( $ui ) > 10000 );
	t( "$ten_ui KHÔNG chứa mã triển khai Apps Script (AKfycb…)", strpos( $ui, 'AKfycb' ) === false );
	t( "$ten_ui KHÔNG chứa địa chỉ Firebase", stripos( $ui, 'firebaseio' ) === false
		&& stripos( $ui, 'default-rtdb' ) === false );
	t( "$ten_ui KHÔNG chứa khoá API Google (AIza…)", strpos( $ui, 'AIza' ) === false );
	/* Chỉ bắt liên kết THẬT. Trong tệp có nhắc chữ `/exec` trong lời ghi chú ("KHÔNG ghi cứng
	   link /exec") — bắt cả cái đó thì phép thử báo hỏng ở chỗ không có lỗi. */
	t( "$ten_ui KHÔNG chứa liên kết triển khai thật",
		stripos( $ui, 'script.google.com' ) === false && strpos( $ui, '/macros/s/' ) === false );
	/* Giao diện chỉ dùng `google.script.run` — không `google.script.host`, không `url`. Nghĩa là
	   một lớp thay thế `run` là đủ; đây là điều kiện để port sang PHP mà KHÔNG sửa giao diện. */
	$khac = preg_match_all( '/google\.script\.(?!run\b)(\w+)/', $ui, $mm );
	t( "$ten_ui chỉ gọi google.script.run, không dùng API Apps Script nào khác", $khac === 0,
		$khac ? implode( ', ', $mm[1] ) : null );
}

// ============================================================ 8. Bản kê bề mặt phải port
$ke  = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/ham-giao-dien.txt' );
$ds  = array_values( array_filter( array_map( 'trim', explode( "\n", $ke ) ),
	function ( $d ) { return $d !== '' && $d[0] !== '#'; } ) );
$ds  = array_values( array_filter( $ds, function ( $d ) { return strpos( $d, '## ' ) !== 0; } ) );
teq( 'bản kê không có tên trùng', count( $ds ), count( array_unique( $ds ) ) );
t( 'bản kê có đủ 111 hàm giao diện gọi', count( $ds ) === 111, count( $ds ) );

/* Bản kê phải khớp ĐÚNG với những gì hai tệp giao diện thật sự gọi. Rút lại từ HTML tại đây, để
   sau này anh dán giao diện mới mà thêm hàm thì phép thử này HỎNG NGAY, chứ không âm thầm thiếu. */
$that = array();
foreach ( array( 'Index.html', 'ChamCongOnline.html' ) as $ten_ui ) {
	$ui = file_get_contents( $goc_ui . '/' . $ten_ui );
	$off = 0;
	while ( ( $i = strpos( $ui, 'google.script.run', $off ) ) !== false ) {
		$off = $i + 17;
		$j   = $off;
		// đi qua chuỗi .withXxx(...) rồi lấy tên hàm cuối chuỗi
		while ( preg_match( '/^\s*\.\s*(\w+)\s*\(/', substr( $ui, $j, 200 ), $m ) ) {
			$k = $j + strpos( substr( $ui, $j, 200 ), '(' ) + strlen( $m[1] ) - strlen( $m[1] );
			$k = $j + strpos( substr( $ui, $j ), '(' );
			if ( strpos( $m[1], 'with' ) !== 0 ) { $that[ $m[1] ] = 1; break; }
			$d = 0;
			while ( $k < strlen( $ui ) ) {
				if ( $ui[ $k ] === '(' ) { $d++; } elseif ( $ui[ $k ] === ')' ) { $d--; if ( ! $d ) { break; } }
				$k++;
			}
			$j = $k + 1;
		}
	}
}
$that = array_keys( $that );
$thieu = array_diff( $that, $ds );
$du    = array_diff( $ds, $that );
t( 'bản kê không thiếu hàm nào giao diện đang gọi', count( $thieu ) === 0, implode( ', ', $thieu ) );
t( 'bản kê không kê thừa hàm giao diện không gọi', count( $du ) === 0, implode( ', ', $du ) );


// ============================================================ 9. Code.gs gốc — bản BÔI ĐEN
/* `goc/Code.gs` là não của app chấm công: 425 hàm, ba cách tính lương, bố cục sheet theo tháng.
   Giữ trong repo để lập bản đồ nghiệp vụ khi port sang MySQL — nhưng repo CÔNG KHAI, nên bản
   trong này là bản đã bôi đen. Phép thử canh hai chiều: chỗ giữ chỗ còn nguyên, và không có
   ai dán bản thật (kèm khoá) lên nó. */
$cgs = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/goc/Code.gs' );
t( 'Code.gs có mặt và đủ dài (não của app, không phải bản rút gọn)', strlen( $cgs ) > 600000 );
t( 'Code.gs còn đủ 5 chỗ giữ chỗ',
	substr_count( $cgs, '<<ID_THU_MUC_GOC>>' ) > 0
	&& substr_count( $cgs, '<<ID_THU_MUC_ANH_CHAM_CONG>>' ) > 0
	&& substr_count( $cgs, '<<LINK_FIREBASE_RTDB>>' ) > 0
	&& substr_count( $cgs, '<<ID_BANG_TINH>>' ) > 0
	&& substr_count( $cgs, '<<PIN_ADMIN_MAC_DINH>>' ) > 0 );
t( 'Code.gs KHÔNG chứa mã triển khai Apps Script (AKfycb…)', strpos( $cgs, 'AKfycb' ) === false );
t( 'Code.gs KHÔNG chứa khoá API Google (AIza…)', strpos( $cgs, 'AIza' ) === false );
t( 'Code.gs KHÔNG chứa liên kết triển khai thật',
	stripos( $cgs, 'script.google.com' ) === false && strpos( $cgs, '/macros/s/' ) === false );
/* Không dò chữ 'default-rtdb'/'firebaseio' trần: trong tệp có một biểu thức kiểm dạng FB_HOST
   và một lời nhắc ở đầu tệp — cả hai đúng chỗ. Cái phải bắt là liên kết Firebase THẬT, tức
   `https://<gì đó>.firebasedatabase.app` hoặc `.firebaseio.com` viết cứng trong mã. */
$fb = preg_match_all( '#https://[A-Za-z0-9._-]+\.(?:firebasedatabase\.app|firebaseio\.com)#', $cgs, $mfb );
t( 'Code.gs KHÔNG viết cứng liên kết Firebase thật', $fb === 0,
	$fb ? implode( ', ', $mfb[0] ) : null );
/* ID thư mục Drive / bảng tính: dạng chuỗi dài bắt đầu bằng chữ số. Chỉ còn một chuỗi được
   phép — dòng dữ liệu VÍ DỤ trong hàm dựng sheet mẫu. */
preg_match_all( '/[\'"]([0-9][A-Za-z0-9_-]{24,})[\'"]/', $cgs, $mid );
$id_la = array_values( array_diff( array_unique( $mid[1] ), array( '1AbCdEfGhIjKlMnOpQrStUvWxYz012345' ) ) );
t( 'Code.gs KHÔNG còn ID Drive / bảng tính thật', count( $id_la ) === 0, implode( ', ', $id_la ) );
/* PIN admin mặc định đã thành chỗ giữ chỗ. `888888` vẫn còn — nhưng chỉ được nằm trong
   PIN_CAM, tức danh sách PIN BỊ CHẶN, chứ không phải PIN đang dùng. */
$so_888 = substr_count( $cgs, '888888' );
t( "'888888' chỉ còn trong danh sách PIN bị chặn (thấy $so_888 lần)", $so_888 === 1 );
t( 'PIN admin mặc định KHÔNG còn giá trị thật',
	preg_match( '/ADMIN_PIN_MAC_DINH\s*=\s*[\'"](?!<<)/', $cgs ) === 0 );
/* Bản kê 111 hàm là danh sách đối chiếu khi port. Mọi hàm trong đó phải có thật trong Code.gs —
   nếu không, hoặc là kê sai tên, hoặc là Code.gs dán vào đây bị thiếu phần. */
$thieu_gs = array();
foreach ( $ds as $ten_ham ) {
	if ( ! preg_match( '/\bfunction\s+' . preg_quote( $ten_ham, '/' ) . '\s*\(/', $cgs ) ) {
		$thieu_gs[] = $ten_ham;
	}
}
t( 'mọi hàm trong bản kê đều có thật trong Code.gs', count( $thieu_gs ) === 0,
	implode( ', ', $thieu_gs ) );

// ============================================================ 10. Sơ đồ MySQL app chấm công
/* Đây là chỗ dữ liệu chấm công SẼ Ở (Hostinger, không phải Sheet). Phép thử ở mục này canh hai
   loại lỗi khác nhau:
     a) lỗi CÚ PHÁP — `dbDelta` không báo lỗi bao giờ. Viết sai một dấu phẩy hay thiếu hai dấu
        cách sau `PRIMARY KEY` thì nó lặng lẽ hiểu sai, rồi mỗi lần tải trang lại thử `ALTER`
        cùng một bảng mãi mãi. Không phép thử thì không ai thấy.
     b) lỗi QUYẾT ĐỊNH — mấy chỗ cột được chọn có lý do tính tiền phía sau. Ai đó "dọn dẹp" sau
        này (đổi `vai_tro` thành ENUM, cho `ngay_cong` một mặc định) là sai lương, mà bảng vẫn
        có số nên chẳng ai nghi. Phép thử giữ lại lý do đó. */
$so_do = VHCC_DB::bang();
/* 20 bảng, không phải 19: khối ghi chú số 14 trong class-vhcc-db.php gom hai bảng (lịch công
   việc + xin đổi lịch) vì chúng là một nghiệp vụ. Con số này chốt cứng để ai thêm bảng mới thì
   phải sửa phép thử — tức là phải nghĩ một lần nữa xem bảng đó có thật cần không. */
/* Đếm bằng SỐ THẬT: con số gõ tay ở đây vỡ mỗi lần thêm bảng, vì một lý do chẳng liên quan
   gì tới thứ nó canh. Cái đáng canh là "sơ đồ có bảng và mọi bảng đều có khoá chính". */
t( 'sơ đồ có đủ bảng', count( $so_do ) >= 24, count( $so_do ) );

foreach ( $so_do as $ten => $than ) {
	$dong = array_values( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) );
	/* `dbDelta` bắt buộc HAI dấu cách giữa `PRIMARY KEY` và dấu mở ngoặc. Một dấu cách là nó
	   không nhận ra khoá chính. Đây là cái bẫy nổi tiếng nhất của dbDelta. */
	t( "bảng $ten có PRIMARY KEY đúng dạng dbDelta (hai dấu cách)",
		count( preg_grep( '/^PRIMARY KEY  \(/', $dong ) ) === 1 );
	/* Mỗi cột / mỗi khoá một dòng — dbDelta tách bằng ký tự xuống dòng, gộp hai cột một dòng là
	   nó bỏ mất cột thứ hai. */
	t( "bảng $ten mỗi cột một dòng", count( preg_grep( '/,\s*\S+\s+(?:BIGINT|VARCHAR|INT|CHAR|TEXT|DATE|DATETIME|DECIMAL|TINYINT|LONGTEXT)/i', $dong ) ) === 0 );
	t( "bảng $ten mọi KEY đều có tên", count( preg_grep( '/^(?:UNIQUE )?KEY \(/', $dong ) ) === 0 );
	t( "bảng $ten không dòng nào thừa dấu phẩy cuối", substr( rtrim( $than ), -1 ) !== ',' );
	t( "bảng $ten không dùng ENUM", stripos( $than, 'ENUM(' ) === false );
	/* Tiền không được là FLOAT. FLOAT làm 0.1+0.2 ra 0.30000000000000004; cộng dồn lương cả
	   cơ sở là lệch vài đồng rồi không ai đối được sổ. */
	t( "bảng $ten không dùng FLOAT/DOUBLE cho số",
		stripos( $than, 'FLOAT' ) === false && stripos( $than, 'DOUBLE' ) === false );
}

/* Mọi cột phải khai một kiểu MySQL CÓ THẬT. Phải kiểm bằng danh sách trắng vì SQLite dưới đây
   KHÔNG bắt được: SQLite định kiểu động, `VARCHARR(190)` nó vẫn nhận và vẫn tạo bảng ngon lành,
   còn MySQL thì chết lúc kích hoạt plugin. Thử phá bằng cách gõ sai một kiểu để chắc chỗ này bắt. */
$kieu_ok = array( 'BIGINT', 'INT', 'TINYINT', 'VARCHAR', 'CHAR', 'TEXT', 'LONGTEXT',
	'DATE', 'DATETIME', 'DECIMAL' );
$kieu_la = array();
foreach ( $so_do as $ten => $than ) {
	foreach ( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) as $d ) {
		if ( preg_match( '/^(?:PRIMARY KEY|UNIQUE KEY|KEY)\b/', $d ) ) { continue; }
		if ( ! preg_match( '/^(\S+)\s+([A-Za-z]+)/', $d, $m ) ) { continue; }
		if ( ! in_array( strtoupper( $m[2] ), $kieu_ok, true ) ) { $kieu_la[] = "$ten.$m[1]: $m[2]"; }
	}
}
t( 'mọi cột dùng kiểu MySQL có thật', count( $kieu_la ) === 0, implode( ', ', $kieu_la ) );

/* Bảng có thật, cột có thật — dựng luôn bằng SQLite trong bộ nhớ. `dbDelta` trong phép thử là
   hàm rỗng nên nó KHÔNG chứng minh được gì. Chỗ này bắt được lỗi cấu trúc: thiếu/thừa dấu phẩy,
   dấu ngoặc lệch, khoá trỏ vào cột không tồn tại. Kiểu cột thì do phép thử ngay trên canh. */
$pdo = new PDO( 'sqlite::memory:' );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$loi_ddl = array();
$cot_thuc = array();
foreach ( $so_do as $ten => $than ) {
	$dong  = array_values( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) );
	$cot   = array();
	$chi_muc = array();
	foreach ( $dong as $d ) {
		$d = rtrim( $d, ',' );
		if ( preg_match( '/^PRIMARY KEY\s+\((.+)\)$/', $d, $m ) ) { $cot[] = 'PRIMARY KEY (' . $m[1] . ')'; continue; }
		if ( preg_match( '/^(UNIQUE )?KEY\s+(\S+)\s+\((.+)\)$/', $d, $m ) ) {
			$chi_muc[] = 'CREATE ' . ( $m[1] ? 'UNIQUE ' : '' ) . 'INDEX ix_' . $ten . '_' . $m[2] . ' ON ' . $ten . ' (' . $m[3] . ')';
			continue;
		}
		// SQLite không biết AUTO_INCREMENT của MySQL; đổi sang dạng của nó, phần còn lại giữ nguyên.
		$cot[] = preg_replace( '/BIGINT\(20\) NOT NULL AUTO_INCREMENT/i', 'INTEGER', $d );
	}
	// SQLite: AUTOINCREMENT phải đi cùng INTEGER PRIMARY KEY, nên bỏ dòng PRIMARY KEY (id) rời ra.
	$cot = array_values( array_filter( $cot, function ( $x ) { return $x !== 'PRIMARY KEY (id)'; } ) );
	$cot[0] = preg_replace( '/^id INTEGER$/', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $cot[0] );
	try {
		$pdo->exec( 'CREATE TABLE ' . $ten . " (\n" . implode( ",\n", $cot ) . "\n)" );
		foreach ( $chi_muc as $ix ) { $pdo->exec( $ix ); }
		$r = $pdo->query( 'PRAGMA table_info(' . $ten . ')' )->fetchAll( PDO::FETCH_ASSOC );
		$cot_thuc[ $ten ] = array_column( $r, 'name' );
	} catch ( Exception $e ) {
		$loi_ddl[] = $ten . ': ' . $e->getMessage();
		/* Vẫn phải có khoá rỗng: các phép thử dưới soi $cot_thuc[...]. Không đặt thì bảng nào
		   dựng trượt sẽ làm cả phép thử CHẾT giữa đường bằng fatal error — lúc đó không đọc được
		   bảng nào sai lẫn còn bao nhiêu phép thử chưa chạy. Trượt phải ra báo trượt, không ra sập. */
		$cot_thuc[ $ten ] = array();
	}
}
t( 'mọi bảng dựng được thật (DDL chạy trên SQLite)', count( $loi_ddl ) === 0, implode( ' | ', $loi_ddl ) );

/* ---- Mấy quyết định phía sau các cột, giữ lại bằng phép thử ---- */
$cc = $cot_thuc['cham_cong'];
t( 'cham_cong có khoá duy nhất (coso,ngay,ma_nv,hau_to) — đây là thứ chặn hàng trùng',
	strpos( $so_do['cham_cong'], 'UNIQUE KEY o (coso,ngay,ma_nv,hau_to)' ) !== false );
t( 'cham_cong lưu giờ bằng GIÂY (INT), không phải TIME — ca đêm cần trục phẳng > 86400',
	in_array( 'gio_vao_giay', $cc, true ) && in_array( 'gio_ra_giay', $cc, true )
	&& stripos( $so_do['cham_cong'], ' TIME' ) === false );
/* Lưu PHÚT là mất dữ liệu: `secOf` bên Code.gs so ở mức giây và ô giờ vào/ra của sheet giữ đủ
   HH:mm:ss. Chốt cứng để không ai "gọn hoá" về phút rồi làm bước đối số hàng thành vô nghĩa. */
t( 'cham_cong KHÔNG lưu giờ bằng phút',
	! in_array( 'gio_vao_phut', $cc, true ) && ! in_array( 'gio_ra_phut', $cc, true ) );
t( 'cham_cong giữ hậu tố mã (TT/TG/CD/CT/TC) thành MỘT cột, không bung ra nhiều cờ',
	in_array( 'hau_to', $cc, true ) && ! in_array( 'la_tang_ca', $cc, true ) );
t( 'giờ vào/ra cho phép NULL — chưa chấm KHÁC chấm lúc 00:00',
	preg_match( '/gio_vao_giay INT NULL/', $so_do['cham_cong'] ) === 1
	&& preg_match( '/gio_ra_giay INT NULL/', $so_do['cham_cong'] ) === 1 );
t( 'vp_ngay_cong.ngay_cong cho phép NULL và KHÔNG có mặc định — không được đoán mẫu số quy lương',
	preg_match( '/ngay_cong DECIMAL\(5,2\) NULL/', $so_do['vp_ngay_cong'] ) === 1
	&& stripos( $so_do['vp_ngay_cong'], 'ngay_cong DECIMAL(5,2) NULL DEFAULT' ) === false );
t( 'phan_quyen.vai_tro là VARCHAR (Apps Script ghi chuỗi tự do), không ENUM',
	preg_match( '/vai_tro VARCHAR\(60\)/', $so_do['phan_quyen'] ) === 1 );
t( 'nhan_vien giữ đủ 26 cột nghiệp vụ của NV_HEADERS + vai_tro',
	count( $cot_thuc['nhan_vien'] ) === 28 ); // 26 + id + vai_tro
/* 🔴 `vai_tro` PHẢI LÀ CỘT RIÊNG, không dùng chung `chuc_vu`. `chuc_vu` là công việc ("Khu vui
   chơi", "Máy tự động"); `vai_tro` là quyền trên trang web. Nhập nhèm hai thứ là nạp sổ nhân
   viên xong CẢ SỔ rơi về "Nhân viên" và KHÔNG AI đăng nhập được — đúng thứ anh Thắng gặp. */
t( 'nhan_vien có cột vai_tro RIÊNG, tách khỏi chuc_vu',
	in_array( 'vai_tro', $cot_thuc['nhan_vien'], true )
	&& in_array( 'chuc_vu', $cot_thuc['nhan_vien'], true ) );
t( 'nhan_vien.luong_co_ban là DECIMAL (tiền), không FLOAT',
	preg_match( '/luong_co_ban DECIMAL\(12,0\)/', $so_do['nhan_vien'] ) === 1 );
t( 'nhat_ky_tra_pin KHÔNG có cột nào chứa PIN — nhật ký là chỗ rò rỉ dễ nhất',
	count( preg_grep( '/pin/i', $cot_thuc['nhat_ky_tra_pin'] ) ) === 0 );
t( 'nhat_ky_tra_pin lưu CCCD đã che', in_array( 'cccd_che', $cot_thuc['nhat_ky_tra_pin'], true ) );
t( 'chống dò PIN đếm trong BẢNG, không trong transient (cache bị xoá là hình phạt tự bỏ)',
	isset( $so_do['nhip_do'] ) && strpos( $so_do['nhip_do'], 'so_lan INT' ) !== false );
t( 'cho_gan tồn tại — lượt bấm của máy chưa gán cơ sở thì GIỮ, không bỏ',
	isset( $so_do['cho_gan'] ) && in_array( 'da_chuyen', $cot_thuc['cho_gan'], true )
	&& in_array( 'thoi_diem', $cot_thuc['cho_gan'], true ) );
/* Khoá nghiệp vụ của bảng máy là SERIAL đầu đọc, không phải MAC bo — thay bo thì đầu đọc vẫn
   là đầu đọc đó. Nhưng KHÔNG được UNIQUE: firmware khai lại serial cũ từ NVS là chuyện có thật. */
t( 'may tra theo serial đầu đọc, và serial KHÔNG unique',
	in_array( 'serial', $cot_thuc['may'], true )
	&& strpos( $so_do['may'], 'KEY serial (serial)' ) !== false
	&& strpos( $so_do['may'], 'UNIQUE KEY serial' ) === false );
t( 'ma_song_song khai theo CẶP mã, không suy từ tên',
	strpos( $so_do['ma_song_song'], 'UNIQUE KEY cap (ma_a,ma_b)' ) !== false );

/* ---- Đổi giờ <-> giây phải khứ hồi đúng, kể cả trên trục phẳng ca đêm ---- */
teq( 'giay(08:30:00)', 30600, VHCC_DB::giay( '08:30:00' ) );
teq( 'giay(08:30) thiếu phần giây thì coi là 0 giây', 30600, VHCC_DB::giay( '08:30' ) );
/* Đây là chỗ lưu PHÚT sẽ mất dữ liệu: hai lượt bấm cách nhau 30 giây phải là HAI giá trị khác. */
t( 'giay() phân biệt được hai lượt cách nhau 30 giây',
	VHCC_DB::giay( '08:30:00' ) !== VHCC_DB::giay( '08:30:30' ) );
teq( 'giay(00:00:00) là 0, KHÔNG phải null', 0, VHCC_DB::giay( '00:00:00' ) );
teq( 'giay() của chuỗi rỗng là null (chưa chấm)', null, VHCC_DB::giay( '' ) );
teq( 'giay() của rác là null', null, VHCC_DB::giay( 'test' ) );
teq( 'hhmmss(30600)', '08:30:00', VHCC_DB::hhmmss( 30600 ) );
teq( 'hhmm(30600) cắt còn HH:mm như ô "Thời gian trong ngày"', '08:30', VHCC_DB::hhmm( 30600 ) );
teq( 'hhmmss(0) là 00:00:00, không phải rỗng', '00:00:00', VHCC_DB::hhmmss( 0 ) );
teq( 'hhmmss(null) là rỗng (chưa chấm)', '', VHCC_DB::hhmmss( null ) );
/* Trục phẳng: 01:30 của ca đêm lưu là 86400+5400 để nó nằm SAU 22:00, nhưng hiện ra vẫn 01:30. */
teq( 'hhmmss(91800) trên trục phẳng ca đêm vẫn hiện 01:30:00', '01:30:00', VHCC_DB::hhmmss( 91800 ) );
teq( 'hhmmss(86400) là 00:00:00', '00:00:00', VHCC_DB::hhmmss( 86400 ) );
teq( 'phut() suy từ giây cho ba engine lương', 510, VHCC_DB::phut( 30600 ) );
teq( 'phut(null) là null', null, VHCC_DB::phut( null ) );
/* Đúng phép tính vòng nửa đêm của Code.gs, ở mức phút: ra 01:30, vào 22:00 -> 3.5 tiếng. */
$vaoM = VHCC_DB::phut( VHCC_DB::giay( '22:00:00' ) );
$raM  = VHCC_DB::phut( VHCC_DB::giay( '01:30:00' ) );
teq( 'số phút ca qua nửa đêm (22:00 -> 01:30) là 210', 210,
	( $raM > $vaoM ) ? ( $raM - $vaoM ) : ( $raM + 1440 - $vaoM ) );


// ============================================================ 11. Cổng nhận chấm công từ máy
/* Đây là đường nóng: mỗi lượt nhân viên bấm mặt là một lượt vào đây. Sai không hiện ra ngay —
   hiện ra cuối tháng ở bảng lương, lúc không dựng lại được lượt bấm đã mất. Nên mục này CHẠY THẬT
   cổng đó trên SQLite, không chỉ đọc mã. */
define( 'VHCC_TEST', 1 );
define( 'VHCC_KHOA_MAY', 'khoa-thu-nghiem-123' );

// Dựng bảng thật trong SQLite (dịch DDL MySQL sang SQLite, y như mục 10).
/* `vhcc_dung_bang()` nay nằm trong wp-stub.php — bản gõ tay ở đây vứt mất mọi UNIQUE KEY, xem
   lời giải thích tại chỗ khai mới. */

/** Một lượt máy gửi lên. Trả về [mã HTTP, thân đã giải JSON]. */
function vhcc_may_gui( $goi, $khoa = 'khoa-thu-nghiem-123', $phuong_thuc = 'POST' ) {
	$GLOBALS['VHCC_THAN']         = is_string( $goi ) ? $goi : json_encode( $goi );
	$_SERVER['REQUEST_METHOD']    = $phuong_thuc;
	$_SERVER['REQUEST_URI']       = '/' . VHCC_Nhan::DUONG;
	$_SERVER['HTTP_X_VHCC_KEY']   = $khoa;
	$_SERVER['CONTENT_LENGTH']    = isset( $GLOBALS['VHCC_DAI_KHAI'] )
		? $GLOBALS['VHCC_DAI_KHAI'] : strlen( $GLOBALS['VHCC_THAN'] );
	$GLOBALS['VHCP_QVAR']['vhcc_nhan'] = 1;
	$GLOBALS['VHCP_MA_HTTP']      = 200;
	ob_start();
	VHCC_Nhan::phuc_vu();
	$ra = ob_get_clean();
	return array( $GLOBALS['VHCP_MA_HTTP'], json_decode( $ra, true ), $ra );
}

/** Gói đúng khuôn của firmware — đúng 8 trường .ino dòng 821-830 gửi. */
function vhcc_goi( $ma, $luc, $coso = 'TUTU_BT', $anh = '' ) {
	return array(
		'macAddress' => 'AA:BB:CC:DD:EE:01', 'hikSerial' => 'SN-0001', 'hikModel' => 'DS-K1T341',
		'stationName' => $coso, 'employeeNo' => $ma, 'name' => 'Nguyễn Văn A',
		'time' => $luc, 'image' => $anh,
	);
}

/** Hàng chấm công đang có trong bảng. */
function vhcc_hang( $coso, $ngay, $ma, $hau_to = '' ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s',
		$coso, $ngay, $ma, $hau_to ), ARRAY_A );
}

vhcc_dung_bang();
// Gán cơ sở cho máy SN-0001 để nó không rơi vào nhánh "chờ gán".
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-0001', 'mac' => 'AA:BB:CC:DD:EE:01',
	'cua_hang' => 'TUTU_BT', 'model' => 'DS-K1T341' ) );

/* ---- 11a. Luật số 1 của firmware: THÂN TRẢ VỀ PHẢI CHỨA CHỮ "SUCCESS" ----------------------
   `.ino` dòng 880: `code == 200 && resp.indexOf("SUCCESS") >= 0`. Đây là TÌM CHUỖI CON, nên chỉ
   cần thân không có chữ đó là firmware coi thất bại, thử lại 3 lần rồi BỎ LƯỢT BẤM.
   Bốn ca dưới đây đều KHÔNG ghi được chấm công, nhưng vẫn phải trả SUCCESS — vì bắt firmware
   đẩy lại một gói không bao giờ hợp lệ là đẩy lại vô hạn. */
list( $ma_http, $tt, $tho ) = vhcc_may_gui( vhcc_goi( 'NV001', '2026-08-20 08:00:00' ) );
t( 'lượt hợp lệ: HTTP 200', $ma_http === 200, $ma_http );
t( 'lượt hợp lệ: thân có chữ SUCCESS (firmware tìm chuỗi con)', strpos( $tho, 'SUCCESS' ) !== false, $tho );
teq( 'lượt đầu tiên trong ngày là giờ VÀO', 'vao', $tt['loai'] );

list( $ma_http, $tt, $tho ) = vhcc_may_gui( vhcc_goi( 'TEST4G', 'test' ) );
t( 'gói thử đường 4G: vẫn SUCCESS (không được bắt đẩy lại vô hạn)',
	$ma_http === 200 && strpos( $tho, 'SUCCESS' ) !== false );
t( 'gói thử đường 4G: bỏ qua, KHÔNG ghi chấm công', ! empty( $tt['boQua'] ) );
list( , , $tho ) = vhcc_may_gui( array_merge( vhcc_goi( 'NV009', '2026-08-20 08:00:00' ), array( 'selftest' => true ) ) );
t( 'cờ selftest cũng bị chặn, không chỉ chặn đúng chữ TEST4G', strpos( $tho, '"boQua":true' ) !== false );
t( 'gói thử KHÔNG tạo hàng chấm công nào', vhcc_hang( 'TUTU_BT', '2026-08-20', 'TEST4G' ) === null );

list( , $tt, $tho ) = vhcc_may_gui( vhcc_goi( 'NV002', '2026-08-20 8:5' ) );
t( 'giờ sai khuôn: vẫn SUCCESS nhưng bỏ qua',
	strpos( $tho, 'SUCCESS' ) !== false && ! empty( $tt['boQua'] ) );
/* Ngày không tồn tại: khuôn ĐÚNG mà ngày SAI. Bên Sheet chuyện này tạo ra một khối tháng lạ. */
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NV002', '2026-02-31 08:00:00' ) );
t( 'ngày không có thật (2026-02-31) bị bỏ, dù đúng khuôn', ! empty( $tt['boQua'] ) );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NV002', '2026-13-01 08:00:00' ) );
t( 'tháng 13 bị bỏ', ! empty( $tt['boQua'] ) );
list( , $tt ) = vhcc_may_gui( vhcc_goi( '', '2026-08-20 08:00:00' ) );
t( 'thiếu mã NV thì bỏ, không tạo hàng mã rỗng', ! empty( $tt['boQua'] ) );
list( , , $tho ) = vhcc_may_gui( 'khong-phai-json{{{' );
t( 'JSON hỏng mà thân ĐỦ: vẫn SUCCESS (đẩy lại cũng hỏng y vậy) nhưng bỏ qua',
	strpos( $tho, 'SUCCESS' ) !== false );

/* THÂN BỊ CẮT là ca khác hẳn: ảnh mặt vượt post_max_size thì PHP giao thân ngắn hơn
   Content-Length mà không báo lỗi gì — trông y như gói rác. Trộn hai ca này là BỎ IM LẶNG một
   lượt chấm công thật vì một dòng cấu hình PHP. */
$goi_to = json_encode( vhcc_goi( 'NVTO', '2026-08-20 08:00:00', 'TUTU_BT', str_repeat( 'A', 400 ) ) );
$GLOBALS['VHCC_DAI_KHAI'] = strlen( $goi_to ) + 50000;      // máy khai dài hơn -> PHP đã cắt
list( $ma_http, , $tho ) = vhcc_may_gui( substr( $goi_to, 0, -200 ) );
t( 'thân bị cắt: HTTP 413 và thân KHÔNG có chữ SUCCESS (không bỏ im lặng)',
	413 === $ma_http && strpos( $tho, 'SUCCESS' ) === false, $ma_http . ' ' . $tho );
$nk = get_option( 'vhcc_nhat_ky_may', array() );
t( 'thân bị cắt: nhật ký nói rõ nghi post_max_size, để người ta sửa được',
	! empty( $nk ) && 'THAN_BI_CAT' === $nk[0]['ma'] && stripos( $nk[0]['loi'], 'post_max_size' ) !== false );
unset( $GLOBALS['VHCC_DAI_KHAI'] );

/* ---- 11b. Sai khoá và GET thì KHÔNG được có chữ SUCCESS -------------------------------------
   Hai ca này ngược lại: phải để người gọi biết là hỏng. */
list( $ma_http, , $tho ) = vhcc_may_gui( vhcc_goi( 'NV003', '2026-08-20 08:00:00' ), 'khoa-sai' );
t( 'sai khoá: HTTP 401 và thân KHÔNG có chữ SUCCESS',
	$ma_http === 401 && strpos( $tho, 'SUCCESS' ) === false, $ma_http . ' ' . $tho );
t( 'sai khoá: KHÔNG ghi gì vào bảng', vhcc_hang( 'TUTU_BT', '2026-08-20', 'NV003' ) === null );
list( $ma_http, , $tho ) = vhcc_may_gui( vhcc_goi( 'NV003', '2026-08-20 08:00:00' ), 'khoa-thu-nghiem-123', 'GET' );
/* Luật số 2: firmware KHÔNG theo chuyển hướng — gặp 30x nó gọi lại bằng GET, mất thân POST. Nên
   một lượt GET vào đây gần như luôn là triệu chứng của chuyện đó, và tuyệt đối không được trả
   chữ SUCCESS: trả là firmware báo "ĐỒNG BỘ THÀNH CÔNG" trong khi không có gì được ghi. */
t( 'GET vào cổng máy: HTTP 405 và thân KHÔNG có chữ SUCCESS',
	$ma_http === 405 && strpos( $tho, 'SUCCESS' ) === false, $ma_http . ' ' . $tho );

/* ---- 11c. Máy chưa gán cơ sở: GIỮ lượt bấm, không bỏ, không tự tạo cơ sở ------------------- */
$goi_la = vhcc_goi( 'NV100', '2026-08-20 09:00:00', 'CO_SO_MOI_TINH' );
$goi_la['hikSerial'] = 'SN-LA'; $goi_la['macAddress'] = 'AA:BB:CC:DD:EE:99';
list( $ma_http, $tt, $tho ) = vhcc_may_gui( $goi_la );
t( 'máy chưa gán: vẫn SUCCESS (bỏ là mất công của người thật)',
	$ma_http === 200 && strpos( $tho, 'SUCCESS' ) !== false );
t( 'máy chưa gán: lượt bấm được GIỮ vào bảng chờ gán', ! empty( $tt['choGan'] ) && 'da-giu' === $tt['luu'] );
$cg = $wpdb->get_row( 'SELECT * FROM ' . VHCC_DB::t( 'cho_gan' ) . " WHERE ma_nv='NV100'", ARRAY_A );
t( 'bảng chờ gán giữ đủ mã NV, thời điểm và lời khai của máy',
	$cg && 'NV100' === $cg['ma_nv'] && '2026-08-20 09:00:00' === $cg['thoi_diem']
	&& 'CO_SO_MOI_TINH' === $cg['ten_tu_khai'] );
t( 'máy chưa gán: KHÔNG tạo cơ sở mới từ lời khai của máy',
	vhcc_hang( 'CO_SO_MOI_TINH', '2026-08-20', 'NV100' ) === null );
$may_moi = $wpdb->get_row( 'SELECT * FROM ' . VHCC_DB::t( 'may' ) . " WHERE serial='SN-LA'", ARRAY_A );
t( 'máy lạ được ghi nhận vào bảng máy với cơ sở TRỐNG, chờ người gán',
	$may_moi && '' === trim( (string) $may_moi['cua_hang'] ) );

/* ---- 11d. Cơ sở lấy theo MÃ THIẾT BỊ, không tin tên máy tự khai --------------------------- */
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NV004', '2026-08-20 08:00:00', 'CO_SO_MAY_KHAI_LUNG' ) );
teq( 'máy khai lệch tên: vẫn ghi vào cơ sở ĐÃ GÁN trong bảng, không theo lời khai',
	'TUTU_BT', $tt['coSo'] );
t( 'lượt đó nằm ở TUTU_BT', vhcc_hang( 'TUTU_BT', '2026-08-20', 'NV004' ) !== null );
t( 'KHÔNG có hàng nào ở cơ sở máy tự khai',
	vhcc_hang( 'CO_SO_MAY_KHAI_LUNG', '2026-08-20', 'NV004' ) === null );

/* Đổi phần cứng thì CHỈ GHI DẤU. "Thay bo" và "mang bo sang cửa hàng khác" nhìn từ máy chủ
   giống hệt nhau — tự sửa là chấm công cơ sở mới chảy vào cơ sở cũ, sai người sai lương. */
$goi_doi = vhcc_goi( 'NV005', '2026-08-20 08:00:00' );
$goi_doi['hikSerial'] = 'SN-KHAC-HOAN-TOAN';
vhcc_may_gui( $goi_doi );
$may = $wpdb->get_row( 'SELECT * FROM ' . VHCC_DB::t( 'may' ) . " WHERE mac='AA:BB:CC:DD:EE:01'", ARRAY_A );
t( 'serial đổi: KHÔNG tự ghi đè serial cũ', 'SN-0001' === $may['serial'], $may['serial'] );
t( 'serial đổi: có ghi dấu để người ta đọc ra', strpos( (string) $may['ghi_chu'], 'SERIAL ĐẦU ĐỌC ĐỔI' ) !== false );
t( 'serial đổi: KHÔNG ghi đè cơ sở đã gán', 'TUTU_BT' === $may['cua_hang'] );

/* ---- 11e. LUẬT KHÔNG BAO GIỜ THU HẸP — phần đáng tin cậy nhất phải có ---------------------
   Ô giờ vào / giờ ra là cặp [sớm nhất, muộn nhất] của ngày. Nạp lại cả tháng theo THỨ TỰ NÀO,
   đứt ở đâu, chạy lại bao nhiêu lần cũng phải ra một kết quả. Không có tính chất này thì bước
   "Apps Script ghi song song hai nơi rồi đối số hàng" là vô nghĩa: hai bên nhận cùng tập lượt
   bấm mà thứ tự đến khác nhau sẽ ra hai cặp giờ khác nhau, và không ai biết bên nào đúng.

   Thử BẰNG MỌI HOÁN VỊ của 4 lượt (24 thứ tự) + chạy lặp lại — đúng ca anh Thắng gặp:
   "chạy được 10 lượt thì tự quay lại từ đầu". */
$luot = array( '06:30:00', '10:00:00', '14:20:00', '22:05:00' );
$hoan_vi = array();
foreach ( $luot as $a ) { foreach ( $luot as $b ) { foreach ( $luot as $c ) { foreach ( $luot as $d2 ) {
	if ( count( array_unique( array( $a, $b, $c, $d2 ) ) ) === 4 ) { $hoan_vi[] = array( $a, $b, $c, $d2 ); }
} } } }
teq( 'có đủ 24 hoán vị của 4 lượt bấm', 24, count( $hoan_vi ) );
$lech = array();
foreach ( $hoan_vi as $i => $tt_hv ) {
	$ngay = '2026-09-' . sprintf( '%02d', $i + 1 );
	foreach ( $tt_hv as $g ) { vhcc_may_gui( vhcc_goi( 'NVHV', $ngay . ' ' . $g ) ); }
	$h = vhcc_hang( 'TUTU_BT', $ngay, 'NVHV' );
	$co = VHCC_DB::hhmmss( $h['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h['gio_ra_giay'] );
	if ( '06:30:00|22:05:00' !== $co ) { $lech[] = implode( ',', $tt_hv ) . ' -> ' . $co; }
}
t( 'MỌI thứ tự trong 24 hoán vị đều ra cặp [06:30:00, 22:05:00]', count( $lech ) === 0,
	implode( ' | ', array_slice( $lech, 0, 4 ) ) );

/* Chạy lại toàn bộ, nhiều lần, xen kẽ — kết quả không được nhúc nhích. */
$ngay_l = '2026-10-01';
foreach ( array( '10:00:00', '14:20:00', '22:05:00', '06:30:00' ) as $g ) { vhcc_may_gui( vhcc_goi( 'NVLL', $ngay_l . ' ' . $g ) ); }
$truoc = vhcc_hang( 'TUTU_BT', $ngay_l, 'NVLL' );
for ( $v = 0; $v < 3; $v++ ) {
	foreach ( array( '14:20:00', '06:30:00', '22:05:00', '10:00:00', '10:00:00' ) as $g ) {
		vhcc_may_gui( vhcc_goi( 'NVLL', $ngay_l . ' ' . $g ) );
	}
}
$sau = vhcc_hang( 'TUTU_BT', $ngay_l, 'NVLL' );
t( 'nạp lại 3 vòng nữa: giờ vào/ra KHÔNG nhúc nhích (chạy lại được)',
	$truoc['gio_vao_giay'] === $sau['gio_vao_giay'] && $truoc['gio_ra_giay'] === $sau['gio_ra_giay'],
	VHCC_DB::hhmmss( $sau['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $sau['gio_ra_giay'] ) );
t( 'nạp lại KHÔNG sinh hàng thứ hai cho cùng một ngày',
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' )
		. " WHERE coso='TUTU_BT' AND ngay='$ngay_l' AND ma_nv='NVLL'" ) === 1 );

/* Bốn nhánh của _ghiGioVaoRa, gọi tên đúng như bản gốc. */
$n = '2026-11-01';
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVNH', $n . ' 10:00:00' ) ); teq( 'nhánh vào', 'vao', $tt['loai'] );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVNH', $n . ' 17:00:00' ) ); teq( 'nhánh ra', 'ra', $tt['loai'] );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVNH', $n . ' 12:00:00' ) ); teq( 'nhánh giữa (KHÔNG thu hẹp giờ ra)', 'giua', $tt['loai'] );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVNH', $n . ' 10:00:00' ) ); teq( 'nhánh trùng', 'trung', $tt['loai'] );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVNH', $n . ' 17:00:00' ) ); teq( 'nhánh trùng (ô giờ ra)', 'trung', $tt['loai'] );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVNH', $n . ' 06:00:00' ) ); teq( 'nhánh đảo thứ tự', 'daoThuTu', $tt['loai'] );
$h = vhcc_hang( 'TUTU_BT', $n, 'NVNH' );
t( 'sau lượt giữa: giờ ra vẫn là 17:00:00, KHÔNG bị thu về 12:00:00',
	'17:00:00' === VHCC_DB::hhmmss( $h['gio_ra_giay'] ), VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
t( 'sau lượt đảo thứ tự: giờ vào thành 06:00:00 mà giờ ra 17:00:00 KHÔNG mất',
	'06:00:00' === VHCC_DB::hhmmss( $h['gio_vao_giay'] ) && '17:00:00' === VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
teq( 'ô "Thời gian trong ngày" cắt còn HH:mm như sheet', '06:00 17:00', $h['chuan'] );

/* Ca anh Thắng gặp thật: lượt SỚM NHẤT tới SAU CÙNG, ô giờ ra ĐÃ CÓ -> giờ vào cũ chỉ là lượt ở
   giữa, KHÔNG được tụt xuống ghi đè giờ ra. Trước khi có luật này, 22:05 bị mất. */
$n2 = '2026-11-02';
foreach ( array( '10:00:00', '14:20:00', '22:05:00', '06:30:00' ) as $g ) { vhcc_may_gui( vhcc_goi( 'NVSAU', $n2 . ' ' . $g ) ); }
$h2 = vhcc_hang( 'TUTU_BT', $n2, 'NVSAU' );
t( 'lượt sớm nhất tới sau cùng: KHÔNG xoá mất giờ ra 22:05:00',
	'06:30:00' === VHCC_DB::hhmmss( $h2['gio_vao_giay'] ) && '22:05:00' === VHCC_DB::hhmmss( $h2['gio_ra_giay'] ),
	VHCC_DB::hhmmss( $h2['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h2['gio_ra_giay'] ) );
/* Ngược lại: ô giờ ra CÒN TRỐNG thì giờ vào cũ PHẢI tụt xuống làm giờ ra. */
$n3 = '2026-11-03';
vhcc_may_gui( vhcc_goi( 'NVTUT', $n3 . ' 10:00:00' ) );
vhcc_may_gui( vhcc_goi( 'NVTUT', $n3 . ' 06:30:00' ) );
$h3 = vhcc_hang( 'TUTU_BT', $n3, 'NVTUT' );
t( 'ô giờ ra còn trống: giờ vào cũ tụt xuống làm giờ ra',
	'06:30:00' === VHCC_DB::hhmmss( $h3['gio_vao_giay'] ) && '10:00:00' === VHCC_DB::hhmmss( $h3['gio_ra_giay'] ),
	VHCC_DB::hhmmss( $h3['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h3['gio_ra_giay'] ) );

/* Hai lượt cách nhau 30 GIÂY là hai lượt khác nhau — chỗ lưu-bằng-phút sẽ nhập thành một. */
$n4 = '2026-11-04';
vhcc_may_gui( vhcc_goi( 'NVGIAY', $n4 . ' 08:00:00' ) );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVGIAY', $n4 . ' 08:00:30' ) );
teq( 'lượt cách 30 giây KHÔNG bị coi là trùng', 'ra', $tt['loai'] );
$h4 = vhcc_hang( 'TUTU_BT', $n4, 'NVGIAY' );
teq( 'giữ đủ giây trong ô giờ ra', '08:00:30', VHCC_DB::hhmmss( $h4['gio_ra_giay'] ) );

/* ---- 11f. Hậu tố mã: tách đúng, và KHÔNG cắt hậu tố lạ ------------------------------------ */
teq( 'tách -CD (tăng ca / ca đêm)', array( 'NV001', 'CD' ), VHCC_Nhan::tach_hau_to( 'NV001-CD' ) );
teq( 'tách -TG (trực ghế, tính theo giờ)', array( 'NV001', 'TG' ), VHCC_Nhan::tach_hau_to( 'NV001-TG' ) );
teq( 'tách -tc chữ thường vẫn nhận', array( 'NV001', 'TC' ), VHCC_Nhan::tach_hau_to( 'NV001-tc' ) );
/* Mã `NV-XX` là mã THẬT tên vậy. Cắt bừa hậu tố lạ là gộp công hai người khác nhau. */
teq( 'KHÔNG cắt hậu tố lạ', array( 'NV001-XX', '' ), VHCC_Nhan::tach_hau_to( 'NV001-XX' ) );
teq( 'mã không hậu tố', array( 'NV001', '' ), VHCC_Nhan::tach_hau_to( 'NV001' ) );
/* Hàng chính và hàng -CD là HAI hàng riêng của cùng một người trong cùng một ngày. */
$n5 = '2026-11-05';
vhcc_may_gui( vhcc_goi( 'NVCA', $n5 . ' 08:30:00' ) );
vhcc_may_gui( vhcc_goi( 'NVCA-CD', $n5 . ' 22:00:00' ) );
t( 'hàng chính và hàng -CD là hai hàng riêng, không đè nhau',
	vhcc_hang( 'TUTU_BT', $n5, 'NVCA', '' ) !== null && vhcc_hang( 'TUTU_BT', $n5, 'NVCA', 'CD' ) !== null );
$h5 = vhcc_hang( 'TUTU_BT', $n5, 'NVCA', 'CD' );
teq( 'hàng -CD lưu mã GỐC ở cột mã, hậu tố ở cột riêng', 'NVCA', $h5['ma_nv'] );

/* ---- 11g. Ảnh thiếu là bình thường (đường 4G gửi image:""), không được vì thế mà bỏ giờ --- */
$n6 = '2026-11-06';
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NV4G', $n6 . ' 08:00:00', 'TUTU_BT', '' ) );
teq( 'gói 4G không kèm ảnh: vẫn ghi giờ', 'vao', $tt['loai'] );
teq( 'và ghi rõ là không có ảnh', 'no-img', $tt['img'] );
/* Ảnh base64 hỏng -> VẪN ghi giờ, chỉ mất ảnh. Giờ là tiền, ảnh là bằng chứng phụ. */
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVANH', $n6 . ' 08:00:00', 'TUTU_BT', str_repeat( '!!!!', 60 ) ) );
teq( 'ảnh hỏng: VẪN ghi giờ', 'vao', $tt['loai'] );
t( 'ảnh hỏng: hàng chấm công vẫn có và có giờ vào',
	( $x = vhcc_hang( 'TUTU_BT', $n6, 'NVANH' ) ) && null !== $x['gio_vao_giay'] );
/* Ảnh thật thì lưu được và ghi đường dẫn. */
$jpg = base64_encode( str_repeat( "\xFF\xD8\xFF\xE0", 60 ) );
list( , $tt ) = vhcc_may_gui( vhcc_goi( 'NVOK', $n6 . ' 08:00:00', 'TUTU_BT', $jpg ) );
t( 'ảnh hợp lệ: lưu được và trả về đường dẫn', strpos( (string) $tt['img'], 'ok:' ) === 0, $tt['img'] );

/* ---- 11h. Luật đường dẫn + chặn chuyển hướng ---------------------------------------------- */
$GLOBALS['VHCP_MOC'] = array(); $GLOBALS['VHCP_LUAT'] = array();
VHCC_Nhan::init();
$co_luat = false;
foreach ( $GLOBALS['VHCP_LUAT'] as $mau => $v ) {
	if ( strpos( $mau, VHCC_Nhan::DUONG ) !== false ) { $co_luat = ( 'top' === $v[1] ); }
}
t( 'cổng máy gài luật đường dẫn ở ĐẦU danh sách (top)', $co_luat );
/* Luật số 2 của firmware: nó KHÔNG theo chuyển hướng, gặp 30x là gọi lại bằng GET và mất thân
   POST. Nên cổng này phải tắt chuyển hướng chuẩn hoá của WordPress. */
t( 'cổng máy gài chỗ tắt chuyển hướng chuẩn hoá',
	vhcp_test_uu_tien( 'parse_request', 'VHCC_Nhan::chan_chuyen_huong' ) === 0 );
$goc_nhan = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-nhan.php' );
t( 'có tắt redirect_canonical', strpos( $goc_nhan, "add_filter( 'redirect_canonical', '__return_false'" ) !== false );
t( 'có gỡ luôn hành động redirect_canonical trên template_redirect',
	strpos( $goc_nhan, "remove_action( 'template_redirect', 'redirect_canonical' )" ) !== false );
/* Đường của máy KHÔNG được có dấu gạch chéo cuối trong hằng số: WordPress chuyển hướng để thêm
   dấu đó, và chuyển hướng trên đường này là mất chấm công. */
t( 'hằng số đường của máy không có dấu gạch chéo', strpos( VHCC_Nhan::DUONG, '/' ) === false );
/* So khoá phải dùng hash_equals: so bằng === rò rỉ chỗ khớp qua thời gian đáp. */
t( 'so khoá bằng hash_equals', strpos( $goc_nhan, 'hash_equals(' ) !== false );
/* Khoá RỖNG phải bị chối, dù cổng đã cấu hình khoá thật. Kiểm bằng HÀNH VI, không bằng cách
   đọc mã: nhánh "chưa cấu hình thì đóng" và nhánh "gửi khoá rỗng" đi qua cùng một chỗ. */
list( $ma_http, , $tho ) = vhcc_may_gui( vhcc_goi( 'NVK', '2026-08-20 08:00:00' ), '' );
t( 'khoá rỗng bị chối (401) và thân KHÔNG có chữ SUCCESS',
	401 === $ma_http && strpos( $tho, 'SUCCESS' ) === false, $ma_http . ' ' . $tho );
t( 'khoá rỗng: KHÔNG ghi gì vào bảng', vhcc_hang( 'TUTU_BT', '2026-08-20', 'NVK' ) === null );
/* Và không có nhánh nào coi "chưa cấu hình" là MỞ. Mặc định mở là cả chuỗi hở mà không ai biết.
   Chỗ này phải đọc mã, vì không định nghĩa lại được hằng số đã định nghĩa trong cùng tiến trình. */
t( 'chưa cấu hình khoá thì cổng ĐÓNG, không phải mở',
	preg_match( '/if \( \x27\x27 === \$that \) \{ return false; \}/', $goc_nhan ) === 1 );
/* Khoá phải đọc từ hằng số wp-config, KHÔNG từ bảng `cai_dat` — bảng đó app đọc được, mà app thì
   có màn hình. Bắt lượt ĐỌC BẢNG thật, đừng bắt chữ "cai_dat" trong lời ghi chú (chính lời ghi
   chú ở đầu tệp đang nhắc tên bảng đó để giải thích vì sao không dùng nó). */
t( 'khoá đọc từ hằng số wp-config', strpos( $goc_nhan, "defined( 'VHCC_KHOA_MAY' )" ) !== false );
t( 'cổng KHÔNG đọc khoá từ bảng cai_dat',
	strpos( $goc_nhan, "VHCC_DB::t( 'cai_dat' )" ) === false
	&& strpos( $goc_nhan, 'get_option( \'vhcc_khoa' ) === false );


// ============================================================ 12. Chấm công online (chạy thẳng web)
/* Anh Thắng: chấm công online qua điện thoại + trang chấm công phụ thì chạy trực tiếp trên web,
   không hàng đợi, không Apps Script. Làm được vì bên này không có firmware nào phải nạp lại.
   Nhưng bốn chỗ GÁC dưới đây bỏ chỗ nào cũng thành lỗ: giờ của client, cơ sở của client, nhiệm vụ
   của client, và tài khoản chưa được bật. */
vhcc_dung_bang();
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-1', 'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'VP01', 'ho_ten' => 'Trần B',
	'cua_hang' => 'VP_HCM', 'coso_phu' => '', 'nhiem_vu' => '' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NV10', 'ho_ten' => 'Lê C',
	'cua_hang' => 'TUTU_BT', 'coso_phu' => 'POSH_HCM', 'nhiem_vu' => 'Trực Ghế' ) );
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'VP_HCM', 'bo_phan' => 'Văn phòng' ) );

$u_vp = array( 'pin' => '1111', 'ma_nv' => 'VP01', 'ho_ten' => 'Trần B', 'coso' => 'VP_HCM' );
$u_cs = array( 'pin' => '2222', 'ma_nv' => 'NV10', 'ho_ten' => 'Lê C', 'coso' => 'TUTU_BT' );

/* ---- 12a. Gác 1: GIỜ LẤY Ở MÁY CHỦ ------------------------------------------------------- */
/* Nhận giờ từ điện thoại là ai cũng tự khai mình đến từ 8 giờ sáng. Phép thử: hàm chấm công
   KHÔNG có tham số nào cho client truyền giờ vào. */
$rf = new ReflectionMethod( 'VHCC_Online', 'cham_cong' );
$ten_ts = array();
foreach ( $rf->getParameters() as $ts ) { $ten_ts[] = $ts->getName(); }
t( 'hàm chấm công online KHÔNG nhận tham số giờ từ client',
	count( preg_grep( '/gio|time|luc|ngay/i', $ten_ts ) ) === 0, implode( ', ', $ten_ts ) );
vhcp_test_dat_gio( '2026-08-20 09:15:00' );
$kq = VHCC_Online::cham_cong( $u_cs );
t( 'chấm công online ghi được', ! empty( $kq['ok'] ), isset( $kq['error'] ) ? $kq['error'] : '' );
teq( 'giờ lấy từ máy chủ', '09:15:00', $kq['gio'] );
teq( 'ghi vào cơ sở mặc định của tài khoản', 'TUTU_BT', $kq['coSo'] );
$h = vhcc_hang( 'TUTU_BT', '2026-08-20', 'NV10' );
teq( 'nguồn ghi là online, không phải may', 'online', $h['nguon'] );

/* ---- 12b. Gác 4: tài khoản chưa bật thì không chấm được ---------------------------------- */
$kq = VHCC_Online::cham_cong( array( 'pin' => '9', 'ma_nv' => '', 'ho_ten' => 'X', 'coso' => 'TUTU_BT' ) );
t( 'tài khoản chưa khai mã NV chấm công online: bị từ chối',
	empty( $kq['ok'] ) && stripos( $kq['error'], 'chưa bật' ) !== false, $kq['error'] );

/* ---- 12c. Gác 2: CƠ SỞ đi lên từ client phải đối chiếu ----------------------------------- */
/* Không kiểm thì bất kỳ tài khoản nhân viên nào cũng gửi lên một tên tuỳ ý và ghi giờ vào cơ sở
   KHÁC — tức ghi công vào bảng lương của cửa hàng khác. */
vhcp_test_dat_gio( '2026-08-21 09:00:00' );
$kq = VHCC_Online::cham_cong( $u_cs, '', null, 'CO_SO_KHONG_PHAI_CUA_TOI' );
t( 'chọn cơ sở KHÔNG có trong hồ sơ: bị từ chối',
	empty( $kq['ok'] ) && stripos( $kq['error'], 'không có ở cơ sở' ) !== false, $kq['error'] );
t( 'và KHÔNG ghi hàng nào vào cơ sở đó',
	vhcc_hang( 'CO_SO_KHONG_PHAI_CUA_TOI', '2026-08-21', 'NV10' ) === null );
$kq = VHCC_Online::cham_cong( $u_cs, '', null, 'POSH_HCM' );
t( 'chọn cơ sở PHỤ đã khai trong hồ sơ: được', ! empty( $kq['ok'] ), isset( $kq['error'] ) ? $kq['error'] : '' );
teq( 'và ghi đúng cơ sở phụ đó', 'POSH_HCM', $kq['coSo'] );

/* ---- 12d. Gác 3: NHIỆM VỤ đi lên từ client phải đối chiếu -------------------------------- */
/* Không kiểm thì ai cũng tự gán cho mình việc có đơn giá cao hơn. */
vhcp_test_dat_gio( '2026-08-22 09:00:00' );
$kq = VHCC_Online::cham_cong( $u_cs, '', null, 'TUTU_BT', 'Việc Đơn Giá Cao' );
t( 'khai nhiệm vụ KHÔNG có trong hồ sơ: bị từ chối',
	empty( $kq['ok'] ) && stripos( $kq['error'], 'không được khai nhiệm vụ' ) !== false, $kq['error'] );
$kq = VHCC_Online::cham_cong( $u_cs, '', null, 'TUTU_BT', 'Trực Ghế' );
t( 'khai nhiệm vụ ĐÃ có trong hồ sơ: được', ! empty( $kq['ok'] ), isset( $kq['error'] ) ? $kq['error'] : '' );
teq( 'Trực Ghế ghi sang hàng riêng -TG (đơn giá tính theo giờ)', 'NV10-TG', $kq['ma'] );
t( 'hàng -TG là hàng riêng, không đè hàng chính',
	vhcc_hang( 'TUTU_BT', '2026-08-22', 'NV10', 'TG' ) !== null );

/* ---- 12e. VĂN PHÒNG: ca ngày, ân hạn tan làm, tăng ca, ca đêm ---------------------------- */
/* Bộ phận Văn phòng gần như chỉ chấm bằng điện thoại, nên đây là ca chính của chấm công online.
   Bỏ qua phần định tuyến này là sai lương đúng những người dùng nó nhiều nhất. */
vhcp_test_dat_gio( '2026-09-01 08:30:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'văn phòng 08:30 -> hàng chính (ca ngày)', 'VP01', $kq['ma'] );
vhcp_test_dat_gio( '2026-09-01 17:00:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
/* Biên: lượt ĐÚNG 17:00:00 là TAN LÀM ca ngày, không phải mở hàng 2. Bên Code.gs so `>` chứ
   không `>=` đúng vì chuyện này. */
teq( 'văn phòng đúng 17:00 -> vẫn hàng chính (tan làm, không mở hàng 2)', 'VP01', $kq['ma'] );
$h = vhcc_hang( 'VP_HCM', '2026-09-01', 'VP01' );
teq( 'hàng chính có đủ vào 08:30 và ra 17:00', '08:30:00|17:00:00',
	VHCC_DB::hhmmss( $h['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );

/* ⚠️ Biên 17:00 phải thử với hàng chính CÒN TRỐNG HẲN. Ca trên (đã có giờ vào, chưa có giờ ra)
   rơi vào ÂN HẠN tan làm, nên ân hạn che mất phép so `>` / `>=` — đổi thành `>=` mà phép thử vẫn
   xanh. Người bấm lượt ĐẦU TIÊN của ngày đúng 17:00 thì đó là giờ VÀO ca ngày; `>=` đẩy họ sang
   hàng 2 và ngày đó hàng 1 rỗng -> mất trọn công ngày. */
vhcp_test_dat_gio( '2026-09-05 17:00:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'lượt ĐẦU TIÊN của ngày đúng 17:00 -> hàng chính, không phải hàng 2', 'VP01', $kq['ma'] );
t( 'và hàng chính ngày đó có giờ vào',
	( $x = vhcc_hang( 'VP_HCM', '2026-09-05', 'VP01' ) ) && null !== $x['gio_vao_giay'] );
t( 'KHÔNG tạo hàng 2 nào cho ngày đó', vhcc_hang( 'VP_HCM', '2026-09-05', 'VP01', 'CD' ) === null );

/* ÂN HẠN TAN LÀM: người tan làm bấm 17:05 mà hàng 1 CHƯA có giờ ra thì đó là tan làm, KHÔNG phải
   mở hàng 2. Không có chỗ này thì hàng 1 thiếu giờ ra -> MẤT TRỌN 1 CÔNG NGÀY. */
vhcp_test_dat_gio( '2026-09-02 08:30:00' );
VHCC_Online::cham_cong( $u_vp );
vhcp_test_dat_gio( '2026-09-02 17:05:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'trong ân hạn mà hàng 1 chưa có giờ ra -> vẫn hàng chính (không mất công ngày)', 'VP01', $kq['ma'] );
$h = vhcc_hang( 'VP_HCM', '2026-09-02', 'VP01' );
teq( 'công ngày được chốt đủ vào-ra', '08:30:00|17:05:00',
	VHCC_DB::hhmmss( $h['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
/* Nhưng nếu hàng 1 ĐÃ đủ vào-ra rồi thì lượt 17:05 tiếp theo là tăng ca thật -> hàng 2. */
vhcp_test_dat_gio( '2026-09-02 17:40:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'hàng 1 đã đủ vào-ra: lượt sau đó sang hàng 2 (tăng ca)', 'VP01-CD', $kq['ma'] );

/* TĂNG CA 18:00 — mốc trải phẳng phải là ngayDen (17:00), KHÔNG phải demTu (21:00). Lấy mốc
   21:00 thì lượt 18:00 trả null và BỊ BỎ ÂM THẦM: nhân viên bấm mà không có gì được ghi. */
vhcp_test_dat_gio( '2026-09-03 18:00:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
t( 'tăng ca 18:00 KHÔNG bị bỏ âm thầm', ! empty( $kq['ok'] ), isset( $kq['error'] ) ? $kq['error'] : '' );
teq( 'tăng ca 18:00 vào hàng 2', 'VP01-CD', $kq['ma'] );

/* CA ĐÊM 22:00 -> 01:30 hôm sau. Hai chuyện phải đúng cùng lúc:
   · lượt 01:30 lùi về khối ngày HÔM TRƯỚC, không thì ca đêm bị chẻ đôi giữa hai ngày;
   · trên trục phẳng 01:30 phải nằm SAU 22:00, không thì ca đêm bị ĐẢO thành 16 tiếng ban ngày
     và công đêm mất sạch. */
vhcp_test_dat_gio( '2026-09-10 22:00:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'ca đêm 22:00 vào hàng 2 ngày 10', 'VP01-CD', $kq['ma'] );
teq( 'và ghi vào ngày 2026-09-10', '2026-09-10', $kq['ngay'] );
vhcp_test_dat_gio( '2026-09-11 01:30:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'lượt 01:30 hôm sau LÙI về khối ngày hôm trước', '2026-09-10', $kq['ngay'] );
$h = vhcc_hang( 'VP_HCM', '2026-09-10', 'VP01', 'CD' );
teq( 'ca đêm KHÔNG bị đảo: vào 22:00, ra 01:30', '22:00:00|01:30:00',
	VHCC_DB::hhmmss( $h['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
/* Trên trục phẳng, 01:30 lưu là 86400+5400 để nó nằm SAU 22:00 — đây là lý do cột giờ là SỐ. */
t( 'giờ ra ca đêm lưu trên trục phẳng (> một ngày)', (int) $h['gio_ra_giay'] > 86400,
	$h['gio_ra_giay'] );
t( 'và giờ vào vẫn nằm trong ngày', (int) $h['gio_vao_giay'] < 86400, $h['gio_vao_giay'] );

/* Luật KHÔNG THU HẸP phải đúng cả trên trục đêm: lượt 23:00 nằm giữa 22:00 và 01:30 thì không
   được cắt ngắn ca đêm. */
vhcp_test_dat_gio( '2026-09-10 23:00:00' );
$kq = VHCC_Online::cham_cong( $u_vp );
teq( 'lượt 23:00 nằm giữa -> nhánh giữa, không thu hẹp', 'giua', $kq['loai'] );
$h = vhcc_hang( 'VP_HCM', '2026-09-10', 'VP01', 'CD' );
teq( 'ca đêm vẫn nguyên 22:00 -> 01:30', '22:00:00|01:30:00',
	VHCC_DB::hhmmss( $h['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
/* Và lượt 21:30 (check-in sớm của ca đêm) tới SAU thì thành giờ vào mới, giờ ra 01:30 KHÔNG mất. */
vhcp_test_dat_gio( '2026-09-10 21:30:00' );
VHCC_Online::cham_cong( $u_vp );
$h = vhcc_hang( 'VP_HCM', '2026-09-10', 'VP01', 'CD' );
teq( 'check-in sớm 21:30 tới sau: thành giờ vào, KHÔNG mất giờ ra 01:30', '21:30:00|01:30:00',
	VHCC_DB::hhmmss( $h['gio_vao_giay'] ) . '|' . VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );

/* Cơ sở KHÔNG phải Văn phòng thì không định tuyến gì cả — lượt 22:00 vẫn vào hàng chính, y như
   đường cũ. Định tuyến lan sang cơ sở khác là đổi ngầm cách tính công của họ. */
vhcp_test_dat_gio( '2026-09-15 22:00:00' );
$kq = VHCC_Online::cham_cong( $u_cs );
teq( 'cơ sở không phải Văn phòng: 22:00 vẫn hàng chính', 'NV10', $kq['ma'] );
teq( 'và không lùi ngày', '2026-09-15', $kq['ngay'] );

/* ---- 12f. GPS ghi lại được, và không ghi rác ------------------------------------------- */
vhcp_test_dat_gio( '2026-09-20 09:00:00' );
VHCC_Online::cham_cong( $u_cs, '', array( 'lat' => 10.776, 'lng' => 106.7, 'acc' => 12.4 ) );
$h = vhcc_hang( 'TUTU_BT', '2026-09-20', 'NV10' );
t( 'GPS được ghi lại', strpos( (string) $h['ghi_chu'], 'GPS 10.776,106.7' ) === 0, $h['ghi_chu'] );
t( 'GPS ghi cả độ chính xác', strpos( (string) $h['ghi_chu'], '±12m' ) !== false, $h['ghi_chu'] );
vhcp_test_dat_gio( '2026-09-21 09:00:00' );
VHCC_Online::cham_cong( $u_cs, '', array( 'lat' => 'rác', 'lng' => null ) );
$h = vhcc_hang( 'TUTU_BT', '2026-09-21', 'NV10' );
teq( 'GPS rác thì để trống, không ghi rác vào bảng', '', $h['ghi_chu'] );

/* ---- 12g. Hàng HỖN HỢP: một ngày vừa có lượt máy vừa có lượt online -------------------- */
/* `nguon` là thứ phép đối số hàng dùng để chỉ đếm lượt của MÁY. Ghi đè thành cái đến sau là mất
   dấu; nên hàng có cả hai phải thành 'hon-hop'. */
vhcp_test_dat_gio( '2026-09-25 08:00:00' );
$goi_m = vhcc_goi( 'NVMIX', '2026-09-25 08:00:00' );
$goi_m['hikSerial'] = 'SN-1'; $goi_m['macAddress'] = '';
vhcc_may_gui( $goi_m );
$h = vhcc_hang( 'TUTU_BT', '2026-09-25', 'NVMIX' );
teq( 'lượt máy: nguồn may', 'may', $h['nguon'] );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NVMIX', 'cua_hang' => 'TUTU_BT' ) );
vhcp_test_dat_gio( '2026-09-25 17:30:00' );
VHCC_Online::cham_cong( array( 'pin' => '3', 'ma_nv' => 'NVMIX', 'ho_ten' => 'D', 'coso' => 'TUTU_BT' ) );
$h = vhcc_hang( 'TUTU_BT', '2026-09-25', 'NVMIX' );
teq( 'thêm lượt online vào cùng ngày -> hàng thành hỗn hợp', 'hon-hop', $h['nguon'] );
teq( 'và giờ ra được nới ra 17:30', '17:30:00', VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
/* Lượt online THỨ HAI không được xoá dấu hỗn hợp về lại 'online' — dấu đó là thứ phép đối số
   hàng dùng để loại hàng có lẫn lượt máy ra khỏi phép so. */
vhcp_test_dat_gio( '2026-09-25 18:00:00' );
VHCC_Online::cham_cong( array( 'pin' => '3', 'ma_nv' => 'NVMIX', 'ho_ten' => 'D', 'coso' => 'TUTU_BT' ) );
$h = vhcc_hang( 'TUTU_BT', '2026-09-25', 'NVMIX' );
teq( 'lượt online thứ hai: vẫn là hỗn hợp, không tụt về online', 'hon-hop', $h['nguon'] );
/* Ngược lại: hai lượt online liên tiếp trên hàng chỉ-online thì KHÔNG được thành hỗn hợp. */
vhcp_test_dat_gio( '2026-09-26 08:00:00' );
VHCC_Online::cham_cong( array( 'pin' => '3', 'ma_nv' => 'NVMIX', 'ho_ten' => 'D', 'coso' => 'TUTU_BT' ) );
vhcp_test_dat_gio( '2026-09-26 17:00:00' );
VHCC_Online::cham_cong( array( 'pin' => '3', 'ma_nv' => 'NVMIX', 'ho_ten' => 'D', 'coso' => 'TUTU_BT' ) );
$h = vhcc_hang( 'TUTU_BT', '2026-09-26', 'NVMIX' );
teq( 'hai lượt online liên tiếp: vẫn là online, không thành hỗn hợp', 'online', $h['nguon'] );

/* ---- 12h. Trang chấm công phụ: trạng thái hôm nay + lịch sử của CHÍNH mình ------------- */
$tt = VHCC_Online::hom_nay( 'VP_HCM', 'VP01', '2026-09-10' );
teq( 'trạng thái hôm nay đọc được hàng ca đêm', 1, count( $tt ) );
teq( 'và nói rõ đó là hàng 2', 'CD', $tt[0]['hauTo'] );
$ls = VHCC_Online::lich_su( 'NV10', array( 'TUTU_BT', 'POSH_HCM' ), 50 );
t( 'lịch sử có dòng', count( $ls ) > 0 );
$khac = 0;
foreach ( $ls as $d ) { if ( ! in_array( $d['coSo'], array( 'TUTU_BT', 'POSH_HCM' ), true ) ) { $khac++; } }
teq( 'lịch sử chỉ trả cơ sở người đó có', 0, $khac );
$ls_vp = VHCC_Online::lich_su( 'VP01', array( 'VP_HCM' ), 50 );
$lot = 0;
foreach ( $ls_vp as $d ) { if ( isset( $d['maNV'] ) && 'VP01' !== $d['maNV'] ) { $lot++; } }
teq( 'lịch sử KHÔNG lẫn chấm công của người khác', 0, $lot );
t( 'lịch sử của VP01 không chứa dòng nào của NV10',
	count( VHCC_Online::lich_su( 'VP01', array( 'TUTU_BT' ), 50 ) ) === 0 );
/* Mới nhất trên cùng. */
$ls2 = VHCC_Online::lich_su( 'NV10', array( 'TUTU_BT', 'POSH_HCM' ), 50 );
t( 'lịch sử xếp mới nhất trên cùng', count( $ls2 ) < 2 || $ls2[0]['ngay'] >= $ls2[1]['ngay'],
	isset( $ls2[1] ) ? ( $ls2[0]['ngay'] . ' rồi ' . $ls2[1]['ngay'] ) : '' );
vhcp_test_dat_gio( null );

// ============================================================ 13. Màn lương: định tuyến engine
/* Ba nhánh, và nhánh thứ ba là "KHÔNG có công thức". Bịa công thức cho nhánh đó là tự sinh ra
   tiền, nên phép thử canh cả chiều ngược: cơ sở chưa xếp bộ phận thì coLuong PHẢI là false. */
vhcc_dung_bang();
function vhcc_cai_dat( $khoa, $gia_tri ) {
	global $wpdb;
	$wpdb->insert( VHCC_DB::t( 'cai_dat' ), array( 'khoa' => $khoa,
		'gia_tri' => is_string( $gia_tri ) ? $gia_tri : wp_json_encode( $gia_tri ) ) );
}
function vhcc_bo_phan( $coso, $bp ) {
	global $wpdb;
	$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $coso, 'bo_phan' => $bp ) );
}
/** Ghi thẳng một hàng chấm công (khỏi phải qua cổng máy cho từng ca thử). */
function vhcc_cham( $coso, $ngay, $ma, $hau_to, $vao, $ra, $nguon = 'may' ) {
	global $wpdb;
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'coso' => $coso, 'ngay' => $ngay, 'ma_nv' => $ma, 'hau_to' => $hau_to,
		'ho_ten' => 'Người ' . $ma,
		'gio_vao_giay' => ( null === $vao ? null : VHCC_DB::giay( $vao ) ),
		'gio_ra_giay'  => ( null === $ra ? null : VHCC_DB::giay( $ra ) ),
		'nguon' => $nguon ) );
}
/**
 * Ghi hàng ca đêm ĐÚNG cách cổng online ghi: giờ đi qua `trai_phang`, nên chỉ giờ SAU NỬA ĐÊM mới
 * được cộng thêm một ngày.
 *
 * ⚠️ Bản đầu của hàm này cộng một ngày cho MỌI giờ ra, kể cả 23:30 — sai, và cái sai đó làm ca đêm
 *    1.5 tiếng trông thành 8 tiếng nên phép thử ngưỡng giờ tối thiểu báo hỏng oan cho engine.
 *    Hàm giúp việc trong bài kiểm mà lệch cách ghi thật thì bài kiểm đang thử một thứ khác.
 */
function vhcc_cham_dem( $coso, $ngay, $ma, $vao, $ra ) {
	global $wpdb;
	$cfg = VHCC_Luong::vp_cfg();
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'coso' => $coso, 'ngay' => $ngay, 'ma_nv' => $ma, 'hau_to' => 'CD', 'ho_ten' => 'Người ' . $ma,
		'gio_vao_giay' => VHCC_Online::trai_phang( VHCC_DB::giay( $vao ), $cfg ),
		'gio_ra_giay'  => VHCC_Online::trai_phang( VHCC_DB::giay( $ra ), $cfg ),
		'nguon' => 'online' ) );
}

vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_bo_phan( 'KVC_BT', 'Khu vui chơi' );
vhcc_bo_phan( 'VP_PHU', 'Văn phòng phụ' );     // KHÔNG nằm trong danh sách hợp lệ -> Chưa xếp

teq( 'cơ sở tên POSH_* -> Nhóm Máy Tự Động', true, VHCC_Luong::la_may_tu_dong( 'POSH_HCM' ) );
teq( 'cơ sở tên JP_* -> Nhóm Máy Tự Động', true, VHCC_Luong::la_may_tu_dong( 'JP_BT' ) );
teq( 'cơ sở tên TUTU_* -> KHÔNG phải Máy Tự Động', false, VHCC_Luong::la_may_tu_dong( 'TUTU_BT' ) );
/* Cơ sở được TÍCH "tính theo giờ" thì thuộc Nhóm Máy Tự Động dù tên không phải POSH/JP. Không có
   chỗ này thì đặt tên CS_VE_SINH_GHE là bảng lương từ chối thẳng — tức để CÁCH ĐẶT TÊN quyết định
   cách tính tiền. */
teq( 'chưa tích: VE_SINH_GHE không thuộc nhóm nào', false, VHCC_Luong::la_may_tu_dong( 'VE_SINH_GHE' ) );
vhcc_cai_dat( 'MTD_CO_SO_THEO_GIO', array( 'Ve Sinh Ghe' ) );
teq( 'đã tích: VE_SINH_GHE vào Nhóm Máy Tự Động dù tên không phải POSH/JP',
	true, VHCC_Luong::la_may_tu_dong( 'VE_SINH_GHE' ) );
teq( 'khoá so sánh bỏ dấu: gõ "VE_SINH_GHE" hay "Ve Sinh Ghe" đều trúng',
	true, VHCC_Luong::coso_tinh_theo_gio( 'CS_ve sinh ghe' ) );

/* ⚠️ Bộ phận phải KHỚP ĐÚNG 'Văn phòng'. "Văn phòng phụ" chuẩn hoá thành 'Chưa xếp' -> KHÔNG có
   công thức lương. So kiểu LIKE là áp trọn công thức Văn phòng cho nó, tức tự sinh ra tiền. */
teq( 'VP_HCM là Văn phòng', true, VHCC_Luong::la_van_phong( 'VP_HCM' ) );
teq( '"Văn phòng phụ" KHÔNG phải Văn phòng', false, VHCC_Luong::la_van_phong( 'VP_PHU' ) );
teq( 'và bị chuẩn hoá thành Chưa xếp', 'Chưa xếp', VHCC_Luong::bo_phan_cua( 'VP_PHU' ) );
teq( 'cơ sở không khai bộ phận -> Chưa xếp', 'Chưa xếp', VHCC_Luong::bo_phan_cua( 'LA_HOAC' ) );

vhcc_cham( 'KVC_BT', '2026-08-03', 'KV1', '', '09:00:00', '17:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'KVC_BT', '2026-08' );
t( 'Khu vui chơi: trả bảng THÔ, coLuong = false (chưa có công thức)',
	! empty( $r['ok'] ) && false === $r['coLuong'] && 'tho' === $r['kieu'] );
teq( 'và nói rõ bộ phận', 'Khu vui chơi', $r['boPhan'] );
t( 'bảng thô có giờ vào/ra nhưng KHÔNG có ô tiền nào',
	! isset( $r['tho']['rows'][0]['tien'] ) && '09:00:00' === $r['tho']['rows'][0]['ngay'][0]['vao'] );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_PHU', '2026-08' );
teq( '"Văn phòng phụ" cũng ra bảng thô, không áp công thức Văn phòng', false, $r['coLuong'] );

teq( 'tháng sai khuôn bị từ chối', false,
	VHCC_Luong::bang_cong_va_luong( 'VP_HCM', 'tháng tám' )['ok'] );
teq( 'tháng 13 bị từ chối', false, VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-13' )['ok'] );
teq( 'nhận nhãn "Tháng 08-2026"', '2026-08', VHCC_Luong::tien_to_thang( 'Tháng 08-2026' ) );
teq( 'nhận cả "2026-08"', '2026-08', VHCC_Luong::tien_to_thang( '2026-08' ) );

/* Cổng quyền: chỉ Admin / Kế toán. Nới ra một vai trò là mở lương TOÀN CHUỖI cho từng cửa hàng. */
teq( 'ADMIN vào được màn lương', true, VHCC_Luong::co_quyen( 'ADMIN' ) );
teq( 'KE_TOAN vào được', true, VHCC_Luong::co_quyen( 'KE_TOAN' ) );
teq( 'QUAN_LY KHÔNG vào được', false, VHCC_Luong::co_quyen( 'QUAN_LY' ) );
teq( 'CUA_HANG_TRUONG KHÔNG vào được', false, VHCC_Luong::co_quyen( 'CUA_HANG_TRUONG' ) );
teq( 'NHAN_VIEN KHÔNG vào được', false, VHCC_Luong::co_quyen( 'NHAN_VIEN' ) );
teq( 'vai trò rỗng KHÔNG vào được', false, VHCC_Luong::co_quyen( '' ) );

// ============================================================ 14. Engine Nhóm Máy Tự Động
vhcc_dung_bang();
vhcc_cai_dat( 'MTD_DON_GIA', array( 'congThuong' => 200000, 'congCuoiTuan' => 250000,
	'congLe' => 400000, 'gioThuong' => 30000, 'gioCuoiTuan' => 35000, 'gioLe' => 60000 ) );
vhcc_cai_dat( 'MTD_NGAY_LE', array( '2026-09-02', '01-01' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'P1', 'nhiem_vu' => '' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'P2', 'nhiem_vu' => 'Trực Ghế Posh - JP' ) );

/* 2026-09-01 là thứ Ba (thường) · 09-02 khai lễ · 09-05 thứ Bảy · 09-06 Chủ nhật. */
vhcc_cham( 'POSH_HCM', '2026-09-01', 'P1', '', '08:00:00', '17:00:00' );   // thường, theo CÔNG
vhcc_cham( 'POSH_HCM', '2026-09-02', 'P1', '', '08:00:00', '17:00:00' );   // LỄ
vhcc_cham( 'POSH_HCM', '2026-09-05', 'P1', '', '08:00:00', '17:00:00' );   // thứ Bảy
vhcc_cham( 'POSH_HCM', '2026-09-06', 'P1', '', '08:00:00', '11:30:00' );   // Chủ nhật, chỉ 3.5h
$r = VHCC_Luong::bang_cong_va_luong( 'POSH_HCM', '2026-09' );
t( 'Nhóm Máy Tự Động: có lương', ! empty( $r['ok'] ) && true === $r['coLuong'] && 'mtd' === $r['kieu'] );
$p1 = null;
foreach ( $r['mtd']['rows'] as $x ) { if ( 'P1' === $x['ma'] ) { $p1 = $x; } }
teq( 'thu tiền: đủ vào+ra = 1 công, KHÔNG xét dài ngắn (chủ nhật 3.5h vẫn 1 công)',
	array( 'thuong' => 1, 'cuoiTuan' => 2, 'le' => 1 ), $p1['cong'] );
teq( 'tiền công = 200000 + 250000×2 + 400000', 1100000, $p1['tienCong'] );
teq( 'và không có tiền theo giờ', 0, $p1['tienGio'] );
/* Lễ ĐÈ cuối tuần: lễ rơi vào chủ nhật thì ăn giá LỄ, không phải giá cuối tuần. */
vhcc_cai_dat( 'MTD_NGAY_LE_X', array() );
teq( 'lễ đè cuối tuần', 'le', VHCC_Luong::mtd_loai_ngay( '2026-09-06', array( '2026-09-06' ) ) );
teq( 'ngày lễ lặp hằng năm khai dạng MM-dd', 'le', VHCC_Luong::mtd_loai_ngay( '2027-01-01', array( '01-01' ) ) );
teq( 'thứ Bảy là cuối tuần', 'cuoiTuan', VHCC_Luong::mtd_loai_ngay( '2026-09-05', array() ) );
teq( 'thứ Ba là ngày thường', 'thuong', VHCC_Luong::mtd_loai_ngay( '2026-09-01', array() ) );

/* Trực Ghế -> tính theo GIỜ. Hàng -TG là hàng riêng. */
vhcc_cham( 'POSH_HCM', '2026-09-01', 'P2', 'TG', '08:00:00', '12:30:00' );   // 4.5 giờ thường
$r = VHCC_Luong::bang_cong_va_luong( 'POSH_HCM', '2026-09' );
$p2 = null;
foreach ( $r['mtd']['rows'] as $x ) { if ( 'P2' === $x['ma'] ) { $p2 = $x; } }
teq( 'Trực Ghế tính theo giờ: 4.5 giờ thường', 4.5, $p2['gio']['thuong'] );
teq( 'tiền theo giờ = 4.5 × 30000', 135000, $p2['tienGio'] );
teq( 'và KHÔNG có công nào', array( 'thuong' => 0, 'cuoiTuan' => 0, 'le' => 0 ), $p2['cong'] );

/* Ca qua đêm ở hàng chính (ra < vào): PHẢI cộng trọn một vòng 24h. Không xử thì ra số ÂM và trừ
   thẳng vào lương người ta. */
vhcc_dung_bang();
vhcc_cai_dat( 'MTD_DON_GIA', array( 'gioThuong' => 30000 ) );
vhcc_cai_dat( 'MTD_CO_SO_THEO_GIO', array( 'POSH_DEM' ) );
vhcc_cham( 'POSH_DEM', '2026-09-01', 'D1', '', '22:00:00', '02:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'POSH_DEM', '2026-09' );
teq( 'ca qua đêm ở hàng chính: 4 giờ, KHÔNG âm', 4.0, $r['mtd']['rows'][0]['gio']['thuong'] );
t( 'và tiền không âm', $r['mtd']['rows'][0]['tienGio'] > 0, $r['mtd']['rows'][0]['tienGio'] );

/* Thiếu giờ vào HOẶC giờ ra -> KHÔNG tính. Bản gốc không đoán nửa ngày. */
vhcc_dung_bang();
vhcc_cai_dat( 'MTD_DON_GIA', array( 'congThuong' => 200000 ) );
vhcc_cham( 'POSH_X', '2026-09-01', 'T1', '', '08:00:00', null );
vhcc_cham( 'POSH_X', '2026-09-02', 'T1', '', null, '17:00:00' );
vhcc_cham( 'POSH_X', '2026-09-03', 'T1', '', '08:00:00', '17:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'POSH_X', '2026-09' );
teq( 'chỉ ngày đủ cặp vào-ra mới được tính công', 1, $r['mtd']['tong']['soCong'] );

/* Chưa khai đơn giá -> phải BÁO, không im lặng trả tiền 0. */
vhcc_dung_bang();
vhcc_cham( 'POSH_Y', '2026-09-01', 'K1', '', '08:00:00', '17:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'POSH_Y', '2026-09' );
t( 'chưa khai đơn giá: có cờ báo', true === $r['mtd']['chuaKhaiGia'] );
teq( 'và tiền là 0, không bịa', 0, $r['mtd']['tong']['tong'] );

// ============================================================ 15. Engine Văn phòng
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'V1', 'luong_co_ban' => 13000000 ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'KT1', 'luong_co_ban' => 10000000 ) );
$wpdb->insert( VHCC_DB::t( 'vp_ngay_cong' ), array( 'coso' => 'VP_HCM', 'thang' => '2026-09', 'ngay_cong' => 26 ) );

/* Ca ngày chuẩn 08:30–17:00 = 8.5 tiếng -> 1 công (min 7). */
vhcc_cham( 'VP_HCM', '2026-09-01', 'V1', '', '08:30:00', '17:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
t( 'Văn phòng: có lương', ! empty( $r['ok'] ) && 'vp' === $r['kieu'] );
teq( 'ca ngày chuẩn = 1 công', 1.0, $r['vp']['rows'][0]['congNgay'] );

/* NHÂN TRƯỚC RỒI CHIA, đúng bản gốc round(lcb * tong / nc). Chia trước rồi nhân là làm tròn đơn
   giá một lần rồi nhân lên -> lệch tới cả nghìn đồng mỗi người. 13.000.000 ÷ 26 = 500.000 chẵn
   nên ca này chưa lộ; ca dưới mới lộ. */
teq( 'tiền = 13.000.000 × 1 ÷ 26', 500000, $r['vp']['rows'][0]['tien'] );
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'V9', 'luong_co_ban' => 10000000 ) );
$wpdb->insert( VHCC_DB::t( 'vp_ngay_cong' ), array( 'coso' => 'VP_HCM', 'thang' => '2026-09', 'ngay_cong' => 27 ) );
for ( $i = 1; $i <= 3; $i++ ) {
	vhcc_cham( 'VP_HCM', '2026-09-0' . $i, 'V9', '', '08:30:00', '17:00:00' );
}
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
/* 10.000.000 × 3 ÷ 27 = 1.111.111,1 -> 1.111.111.  Chia trước: round(10.000.000/27)=370.370,
   ×3 = 1.111.110 — LỆCH 1 đồng ở đây, và lệch cả nghìn khi số lớn hơn. */
teq( 'nhân trước chia sau: 10.000.000 × 3 ÷ 27', 1111111, $r['vp']['rows'][0]['tien'] );

/* Chưa khai số ngày công -> tiền 0 + cờ báo, KHÔNG đoán 26. Đoán mẫu số là sai tiền của MỌI
   người cùng lúc, mà bảng vẫn có số nên chẳng ai nghi. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'V2', 'luong_co_ban' => 13000000 ) );
vhcc_cham( 'VP_HCM', '2026-09-01', 'V2', '', '08:30:00', '17:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
t( 'chưa khai số ngày công: có cờ báo', true === $r['vp']['tien']['chuaKhaiNgayCong'] );
teq( 'và tiền là 0, KHÔNG đoán 26', 0, $r['vp']['rows'][0]['tien'] );
teq( 'công vẫn tính đủ (chỉ thiếu mẫu số quy tiền)', 1.0, $r['vp']['rows'][0]['congNgay'] );
/* Gợi ý được lấy từ tháng trước của CHÍNH cơ sở đó, nhưng KHÔNG dùng để tính tiền. */
$wpdb->insert( VHCC_DB::t( 'vp_ngay_cong' ), array( 'coso' => 'VP_HCM', 'thang' => '2026-08', 'ngay_cong' => 25 ) );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
teq( 'có gợi ý số ngày công tháng trước', 25.0, $r['vp']['ncGoiY'] );
teq( 'nhưng tiền VẪN là 0 — gợi ý không được dùng để tính', 0, $r['vp']['rows'][0]['tien'] );

/* BẬC THANG. `bacMot` mặc định là 9 CHỨ KHÔNG PHẢI 8: khung chuẩn 08:30–17:00 dài 8.5 tiếng, với
   mốc 8 thì NGÀY LÀM BÌNH THƯỜNG rơi vào bậc "<12h" = 1.5 công, tức lương CẢ CƠ SỞ tăng 50%. */
$cfg = VHCC_Luong::vp_cfg();
teq( 'bacMot mặc định là 9, không phải 8', 9, $cfg['bacMot'] );
$bt = array_merge( $cfg, array( 'duoiMin' => 'bacthang' ) );
teq( 'bậc thang: ca ngày chuẩn 8.5h -> 1 công (nhờ mốc 9)', 1.0,
	VHCC_Luong::vp_cong_ngay_tu_phut( 510, 7, $bt ) );
$bt8 = array_merge( $bt, array( 'bacMot' => 8 ) );
teq( 'nếu đổi mốc thành 8 thì chính ca chuẩn thành 1.5 công — đây là chỗ tăng 50% cả cơ sở',
	1.5, VHCC_Luong::vp_cong_ngay_tu_phut( 510, 7, $bt8 ) );
teq( 'bậc thang: 3h -> nửa công', 0.5, VHCC_Luong::vp_cong_ngay_tu_phut( 180, 7, $bt ) );
teq( 'bậc thang: 6h -> 1 công', 1.0, VHCC_Luong::vp_cong_ngay_tu_phut( 360, 7, $bt ) );
teq( 'bậc thang: 10h -> 1.5 công', 1.5, VHCC_Luong::vp_cong_ngay_tu_phut( 600, 7, $bt ) );
/* ⚠️ Bậc thang xét TRƯỚC chốt `gio >= min`. Để sau thì người làm 10 tiếng bị chốt kia trả 1 công
   và bậc 1.5 KHÔNG BAO GIỜ chạm tới. */
teq( 'bậc thang: 13h -> trần 1.5, không thưởng thêm', 1.5, VHCC_Luong::vp_cong_ngay_tu_phut( 780, 7, $bt ) );
/* Các kiểu khác của ô "làm thiếu giờ thì tính sao". */
teq( 'tyle: 6h ÷ 8 = 0.75 công', 0.75,
	VHCC_Luong::vp_cong_ngay_tu_phut( 360, 7, array_merge( $cfg, array( 'duoiMin' => 'tyle' ) ) ) );
teq( 'tron: thiếu giờ vẫn tròn 1 công', 1.0,
	VHCC_Luong::vp_cong_ngay_tu_phut( 360, 7, array_merge( $cfg, array( 'duoiMin' => 'tron' ) ) ) );
teq( 'khong: thiếu giờ thì 0 công', 0.0,
	VHCC_Luong::vp_cong_ngay_tu_phut( 360, 7, array_merge( $cfg, array( 'duoiMin' => 'khong' ) ) ) );
teq( 'nua: đủ 4h thì nửa công', 0.5,
	VHCC_Luong::vp_cong_ngay_tu_phut( 300, 7, array_merge( $cfg, array( 'duoiMin' => 'nua' ) ) ) );
teq( 'nua: chưa đủ 4h thì 0', 0.0,
	VHCC_Luong::vp_cong_ngay_tu_phut( 180, 7, array_merge( $cfg, array( 'duoiMin' => 'nua' ) ) ) );

/* TĂNG CA: hàng 2 nằm trọn trong [17:00, 21:00) -> 0.5 công CÙNG NGÀY. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cham( 'VP_HCM', '2026-09-01', 'V3', '', '08:30:00', '17:00:00' );
vhcc_cham( 'VP_HCM', '2026-09-01', 'V3', 'CD', '17:30:00', '20:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
teq( 'tăng ca: 0.5 công cùng ngày', 0.5, $r['vp']['rows'][0]['congTangCa'] );
teq( 'tổng ngày đó = 1 công ngày + 0.5 tăng ca', 1.5, $r['vp']['rows'][0]['tong'] );

/* CA ĐÊM: hàng 2 ngày D cho công vào NGÀY D+1, cộng công BÙ. Đây là lý do phải tính theo THÁNG
   chứ không từng ngày rời. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'V4', 'luong_co_ban' => 13000000 ) );
$wpdb->insert( VHCC_DB::t( 'vp_ngay_cong' ), array( 'coso' => 'VP_HCM', 'thang' => '2026-09', 'ngay_cong' => 26 ) );
vhcc_cham_dem( 'VP_HCM', '2026-09-10', 'V4', '22:00:00', '01:30:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
$v4 = $r['vp']['rows'][0];
teq( 'ca đêm cho 1 công đêm', 1.0, $v4['congDem'] );
teq( 'cộng 1 công bù (nghỉ bù)', 1.0, $v4['congBu'] );
teq( 'tổng 2 công', 2.0, $v4['tong'] );
$ngay_dem = array();
foreach ( $r['vp']['detail'] as $d ) { if ( $d['congDem'] > 0 ) { $ngay_dem[] = $d['ngay']; } }
teq( 'công đêm ghi cho NGÀY HÔM SAU', array( '2026-09-11' ), $ngay_dem );
/* GIỮ lại dòng ngày bắt đầu ca đêm dù nó 0 công — không thì trên bảng chỉ thấy ngày 11 tự nhiên
   có 2 công mà KHÔNG BIẾT TỪ ĐÂU RA. Không soi được là không kiểm được lương. */
$co_dong_10 = false;
foreach ( $r['vp']['detail'] as $d ) {
	if ( '2026-09-10' === $d['ngay'] && '2026-09-11' === $d['demSangNgay'] ) { $co_dong_10 = true; }
}
t( 'giữ dòng ngày bắt đầu ca đêm để soi được công từ đâu ra', $co_dong_10 );

/* NGƯỠNG GIỜ TỐI THIỂU ca đêm. demToiThieuGio = 0 nghĩa là KHÔNG xét, để bật ngưỡng không âm
   thầm cắt công của ai. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cai_dat( 'VP_CONG_CFG', array( 'demToiThieuGio' => 3 ) );
vhcc_cham_dem( 'VP_HCM', '2026-09-10', 'V5', '22:00:00', '23:30:00' );   // chỉ 1.5 giờ đêm
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
teq( 'ca đêm 1.5 giờ < ngưỡng 3 giờ: KHÔNG được công đêm', 0.0, $r['vp']['rows'][0]['congDem'] );
teq( 'và có đếm số ngày bị loại để soi', 1, $r['vp']['rows'][0]['soNgayDemThieuGio'] );
/* ⚠️ CHỈ CÓ MỘT GIỜ (quên chấm ra): KHÔNG được lấy cớ đó để cắt công — cắt ngầm là trừ tiền một
   người vì cái máy chấm công lỗi. Vẫn tính, nhưng đánh dấu. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cai_dat( 'VP_CONG_CFG', array( 'demToiThieuGio' => 3 ) );
vhcc_cham( 'VP_HCM', '2026-09-10', 'V6', 'CD', '22:00:00', null );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
teq( 'quên chấm ra ca đêm: VẪN được công đêm, không cắt ngầm', 1.0, $r['vp']['rows'][0]['congDem'] );
teq( 'nhưng có đánh dấu để soi', 1, $r['vp']['rows'][0]['soNgayDemChuaDuCap'] );

/* Giờ ca NGÀY lọt vào hàng 2 -> 'la', KHÔNG tính thành tăng ca. Tính bừa là tự cộng tiền cho một
   cái sai (sửa tay hoặc chấm sai chỗ). */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cham( 'VP_HCM', '2026-09-01', 'V7', 'CD', '10:00:00', '12:00:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
teq( 'giờ ca ngày lọt hàng 2: KHÔNG tính tăng ca', 0.0, $r['vp']['rows'][0]['congTangCa'] );
teq( 'mà đếm là ca lạ để người ta soi', 1, $r['vp']['rows'][0]['soNgayCaLa'] );

/* KẾ TOÁN: thứ Bảy 08:30–12:00 đủ 3h vẫn 1 công · Chủ nhật là ngày NGHỈ -> 0 công ngày. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cai_dat( 'VP_CONG_CFG', array( 'ktMaNV' => array( 'kt1' ) ) );
vhcc_cham( 'VP_HCM', '2026-09-05', 'KT1', '', '08:30:00', '12:00:00' );  // thứ Bảy, 3.5h
vhcc_cham( 'VP_HCM', '2026-09-06', 'KT1', '', '08:30:00', '17:00:00' );  // Chủ nhật, làm đủ
vhcc_cham( 'VP_HCM', '2026-09-05', 'V8', '', '08:30:00', '12:00:00' );   // không phải kế toán
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
$kt = null; $v8 = null;
foreach ( $r['vp']['rows'] as $x ) { if ( 'KT1' === $x['ma'] ) { $kt = $x; } if ( 'V8' === $x['ma'] ) { $v8 = $x; } }
teq( 'kế toán thứ Bảy 3.5h -> 1 công (khung riêng 08:30–12:00)', 1.0, $kt['congNgay'] );
t( 'kế toán được nhận diện', true === $kt['laKeToan'] );
/* Người KHÔNG phải kế toán làm cùng 3.5h thứ Bảy thì theo khung ca ngày thường (min 7h) -> tính
   theo kiểu 'tyle' mặc định, KHÔNG được 1 công. Đây là chỗ khung riêng của kế toán tạo khác biệt. */
t( 'người không phải kế toán làm 3.5h thứ Bảy KHÔNG được 1 công', $v8['congNgay'] < 1.0, $v8['congNgay'] );
/* Chủ nhật kế toán nghỉ -> 0 công ngày, nhưng VẪN giữ dòng để soi được "đi làm chủ nhật". */
$cn = null;
foreach ( $r['vp']['detail'] as $d ) { if ( 'KT1' === $d['ma'] && '2026-09-06' === $d['ngay'] ) { $cn = $d; } }
t( 'kế toán chủ nhật: 0 công ngày', $cn && 0.0 === $cn['congNgay'], $cn ? $cn['congNgay'] : 'không có dòng' );
t( 'nhưng GIỮ dòng và giữ số phút để soi được là có đi làm', $cn && $cn['phutNgay'] > 0 && $cn['ktCnNghi'] );

/* Ca đêm ngày CUỐI THÁNG đẩy công sang ngày 1 tháng sau -> không được cộng vào tháng này. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cham_dem( 'VP_HCM', '2026-09-30', 'V0', '22:00:00', '01:30:00' );
$r = VHCC_Luong::bang_cong_va_luong( 'VP_HCM', '2026-09' );
teq( 'ca đêm ngày 30/9: công của nó thuộc 01/10, KHÔNG cộng vào tháng 9', 0.0,
	$r['vp']['rows'][0]['congDem'] );
t( 'nhưng vẫn thấy dòng ngày 30/9 để biết có ca đêm', count( $r['vp']['detail'] ) > 0 );

/* Phần giao với khung: khung vắt qua nửa đêm (21:00–06:00) phải cộng một ngày vào mốc cuối, không
   thì phần giao luôn ra 0 và công đêm mất sạch. */
$c = VHCC_Luong::vp_cfg();
teq( 'giao của 22:00–01:30 (trải phẳng) với khung 21:00–06:00 = 210 phút', 210,
	VHCC_Luong::vp_phut_trong_khung( 22 * 60, 24 * 60 + 90, '21:00', '06:00' ) );
teq( 'giao của 08:30–17:00 với khung ca ngày = 510 phút', 510,
	VHCC_Luong::vp_phut_trong_khung( 510, 1020, '08:30', '17:00' ) );
teq( 'ra sớm hơn khung thì phần giao ngắn lại', 300,
	VHCC_Luong::vp_phut_trong_khung( 510, 810, '08:30', '17:00' ) );
teq( 'hoàn toàn ngoài khung thì 0', 0,
	VHCC_Luong::vp_phut_trong_khung( 1200, 1300, '08:30', '17:00' ) );

// ============================================================ 16. Màn hình lương chỉ ĐỌC
/* Xem lương không được phép đổi chấm công, kể cả một ô. Và ba thứ phải hiện ra MẶT chứ không lẫn
   trong bảng số — engine cố ý GIỮ mấy ngày cần soi lại thay vì lặng lẽ bỏ, nên màn hình không
   hiện thì việc giữ lại thành vô nghĩa. */
$ad = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
$i_luong = strpos( $ad, 'public static function trang_luong()' );
$i_het   = strpos( $ad, 'Màn hình của cổng nhận chấm công', $i_luong );
$than_luong = substr( $ad, $i_luong, $i_het - $i_luong );
t( 'màn lương KHÔNG ghi vào bảng chấm công',
	strpos( $than_luong, "insert( VHCC_DB::t( 'cham_cong'" ) === false
	&& strpos( $than_luong, "update( VHCC_DB::t( 'cham_cong'" ) === false
	&& strpos( $than_luong, '$wpdb->query' ) === false );
t( 'màn lương gọi đúng engine, không tự tính lại một công thức thứ hai',
	strpos( $than_luong, 'VHCC_Luong::bang_cong_va_luong' ) !== false );
t( 'cơ sở chưa có công thức: nói THẲNG ra màn hình, không để tưởng 0 là không ai làm',
	strpos( $than_luong, 'CHƯA có công thức lương' ) !== false );
t( 'chưa khai số ngày công: hiện “—” chứ không hiện 0 đồng',
	strpos( $than_luong, 'chuaKhaiNgayCong' ) !== false
	&& strpos( $than_luong, "\$e['tien'] ? number_format( \$e['tien'] ) : '—'" ) !== false );
t( 'chưa khai đơn giá MTD: có báo',
	strpos( $than_luong, 'chuaKhaiGia' ) !== false && strpos( $than_luong, 'Chưa khai đơn giá' ) !== false );
/* Ba dấu cần soi phải ra cột riêng. Engine đếm sẵn; màn hình bỏ qua là bỏ luôn chỗ kiểm. */
foreach ( array( 'soNgayCaLa', 'soNgayDemThieuGio', 'soNgayDemChuaDuCap' ) as $dau ) {
	t( "màn lương hiện dấu cần soi: $dau", strpos( $than_luong, $dau ) !== false );
}
t( 'chi tiết từng ngày hiện được "công đêm từ ngày nào" để soi ngược',
	strpos( $than_luong, 'demTuNgay' ) !== false && strpos( $than_luong, 'demSangNgay' ) !== false );
t( 'màn lương gác quyền trước khi hiện gì',
	strpos( $than_luong, "current_user_can( self::CAP )" ) !== false );

// ============================================================ 17. Tờ in bảng chấm công
/* Bản gốc nhận `tongHop`/`chiTiet` TỪ TRÌNH DUYỆT rồi đổ vào khuôn — số trên tờ giấy chấm công là
   số máy khách gửi lên. Bản này máy chủ tự tính từ MySQL, nên phép thử canh chính chỗ đó. */
vhcc_dung_bang();
vhcc_cham( 'TUTU_BT', '2026-08-03', 'N1', '', '08:00:00', '17:30:00' );
vhcc_cham( 'TUTU_BT', '2026-08-04', 'N1', '', '08:15:00', null );          // QUÊN CHECK-OUT
vhcc_cham( 'TUTU_BT', '2026-08-03', 'N2', '', '09:00:00', '12:00:00' );
vhcc_cham( 'TUTU_BT', '2026-09-01', 'N9', '', '08:00:00', '17:00:00' );    // ngoài khoảng

$d = VHCC_Pdf::gom( 'TUTU_BT', '2026-08-01', '2026-08-31' );
teq( 'chỉ gom hàng trong khoảng ngày', 3, $d['soChiTiet'] );
$t = array();
foreach ( $d['tongHop'] as $r ) { $t[ $r['ma'] ] = $r; }
teq( 'N1: 2 ngày công', 2, $t['N1']['soNgay'] );
teq( 'N1: đếm được 1 ngày thiếu giờ ra', 1, $t['N1']['thieuRa'] );
/* Ngày thiếu giờ ra KHÔNG được cộng giờ — cộng bừa là tự bịa giờ làm. 08:00→17:30 = 9.5h. */
teq( 'N1: tổng giờ chỉ tính ngày đủ cặp (9.5h)', 570, $t['N1']['phut'] );
teq( 'N2: 3 giờ', 180, $t['N2']['phut'] );

$html = VHCC_Pdf::trang_in( 'TUTU_BT', '2026-08-01', '2026-08-31', 'Anh Thắng' );
t( 'tờ in là HTML đứng một mình', strpos( $html, '<!DOCTYPE html>' ) === 0 );
t( 'khổ A4 và lề như bản gốc', strpos( $html, '@page{size:A4;margin:12mm 10mm}' ) !== false );
/* Sang trang mới phải lặp lại dòng tiêu đề, và không cắt một hàng làm hai trang. Thiếu hai dòng
   CSS này thì bảng nhiều trang đọc không ra ai là ai. */
t( 'sang trang lặp lại dòng tiêu đề', strpos( $html, 'thead{display:table-header-group}' ) !== false );
t( 'không cắt một hàng làm hai trang', strpos( $html, 'tr{page-break-inside:avoid}' ) !== false );
t( 'có tên công ty', strpos( $html, 'K&amp;H' ) !== false );
t( 'có người xuất', strpos( $html, 'Anh Thắng' ) !== false );
t( 'ngày viết kiểu Việt dd/MM/yyyy', strpos( $html, '03/08/2026' ) !== false );
t( 'KHÔNG để nguyên khuôn yyyy-MM-dd trên giấy', strpos( $html, '2026-08-03' ) === false );
/* Quên check-out phải hiện chữ THIẾU đỏ, và có dòng giải thích cuối trang. */
t( 'ngày quên check-out hiện chữ THIẾU', strpos( $html, '>THIẾU<' ) !== false );
t( 'có dòng giải thích chữ THIẾU nghĩa là gì', strpos( $html, 'quên check-out' ) !== false );
t( 'có hai ô ký tên', strpos( $html, 'NHÂN VIÊN XÁC NHẬN' ) !== false
	&& strpos( $html, 'CỬA HÀNG TRƯỞNG' ) !== false );
/* Thanh nút chỉ có trên màn hình, KHÔNG in ra giấy. */
t( 'thanh nút bị ẩn khi in', strpos( $html, '@media print{.thanh{display:none}' ) !== false );

/* Ca qua nửa đêm: giờ làm KHÔNG được ra số âm trên tờ giấy chấm công. */
vhcc_dung_bang();
vhcc_cham( 'TUTU_BT', '2026-08-03', 'ND', '', '22:00:00', '02:00:00' );
$d = VHCC_Pdf::gom( 'TUTU_BT', '2026-08-01', '2026-08-31' );
teq( 'ca qua nửa đêm: 4 giờ, không âm', 240, $d['tongHop'][0]['phut'] );
/* Ô "Giờ làm" của dòng CHI TIẾT cũng phải là 4.00h — đây là chỗ trước đây có bản tính thứ hai,
   nên phép thử soi riêng ô đó chứ không soi cả trang (cả trang thì cột tổng hợp che mất). */
teq( 'ô Giờ làm của dòng chi tiết cũng 4.00h', '4.00h', $d['chiTiet'][0]['gio'] );
teq( 'phut_lam là MỘT chỗ tính duy nhất, dùng được trực tiếp', 240,
	VHCC_Pdf::phut_lam( VHCC_DB::giay( '22:00:00' ), VHCC_DB::giay( '02:00:00' ) ) );
teq( 'thiếu giờ ra thì phut_lam trả null, không trả 0', null,
	VHCC_Pdf::phut_lam( VHCC_DB::giay( '08:00:00' ), null ) );
teq( 'và ô Giờ làm để trống', '', VHCC_Pdf::gio_lam( VHCC_DB::giay( '08:00:00' ), null ) );
/* Hàng ca đêm đã trải phẳng thì hiệu đã dương sẵn, không được cộng bù lần nữa. */
vhcc_dung_bang();
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
vhcc_cham_dem( 'VP_HCM', '2026-08-10', 'VD', '22:00:00', '01:30:00' );
$d = VHCC_Pdf::gom( 'VP_HCM', '2026-08-01', '2026-08-31' );
teq( 'hàng ca đêm đã trải phẳng: 3.5 giờ, KHÔNG cộng bù thêm 24h', 210, $d['tongHop'][0]['phut'] );

/* Hàng có hậu tố phải hiện rõ là hàng nào, không gộp lẫn vào hàng chính. */
vhcc_dung_bang();
vhcc_cham( 'POSH_HCM', '2026-08-03', 'P9', '', '08:00:00', '12:00:00' );
vhcc_cham( 'POSH_HCM', '2026-08-03', 'P9', 'TG', '13:00:00', '17:00:00' );
$d = VHCC_Pdf::gom( 'POSH_HCM', '2026-08-01', '2026-08-31' );
teq( 'hàng chính và hàng -TG là hai dòng tổng hợp riêng', 2, count( $d['tongHop'] ) );
$ma = array();
foreach ( $d['tongHop'] as $r ) { $ma[] = $r['ma']; }
sort( $ma );
teq( 'và mã hiện rõ hậu tố', array( 'P9', 'P9-TG' ), $ma );

/* Cắt bớt phải IN HẲN cảnh báo lên giấy. Cắt im lặng là tờ giấy trông đầy đủ trong khi thiếu người. */
teq( 'ngưỡng cắt chi tiết', 4000, VHCC_Pdf::MAX_CHI_TIET );
$than_pdf = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-pdf.php' );
t( 'có in cảnh báo khi bị cắt', strpos( $than_pdf, 'ĐÃ BỊ CẮT BỚT' ) !== false );
t( 'và cảnh báo nằm trong phần dựng giấy, không phải chỉ ghi chú',
	strpos( $than_pdf, "if ( \$d['biCat'] ) {" ) !== false );

/* Tên tệp: không dấu, không khoảng trắng. */
teq( 'tên tệp một ngày', 'BangCong_TUTU_BT_20260803', VHCC_Pdf::ten_tep( 'CS_TUTU_BT', '2026-08-03', '2026-08-03' ) );
teq( 'tên tệp một khoảng', 'BangCong_TUTU_BT_20260801-20260831',
	VHCC_Pdf::ten_tep( 'TUTU_BT', '2026-08-01', '2026-08-31' ) );
teq( 'tên cơ sở có dấu bị thay hết', 'BangCong_C__S__20260801',
	VHCC_Pdf::ten_tep( 'Cơ Sở', '2026-08-01', '2026-08-01' ) );

/* Ô rác dài phải bị cắt, không kéo dài cả trang. Và phải thoát HTML. */
teq( 'ô quá dài bị cắt còn 120 ký tự', 120, mb_strlen( VHCC_Pdf::esc( str_repeat( 'x', 500 ) ) ) );
t( 'ô có thẻ HTML bị thoát', strpos( VHCC_Pdf::esc( '<script>' ), '<script>' ) === false );
teq( 'ngày sai khuôn thì GIỮ NGUYÊN, không tự đoán', 'hôm qua', VHCC_Pdf::ngay_vn( 'hôm qua' ) );

/* Tờ in phải gác quyền trước khi hiện gì. */
$ad2 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
$i_in = strpos( $ad2, 'public static function trang_in()' );
$than_in = substr( $ad2, $i_in, 2000 );
t( 'tờ in gác quyền', strpos( $than_in, 'current_user_can( self::CAP )' ) !== false );
/* Chỉ nhận ngày ĐÚNG KHUÔN mới xuất — không thì tham số rác chạy thẳng vào câu truy vấn ngày. */
t( 'chỉ xuất khi ngày đúng khuôn yyyy-MM-dd', strpos( $than_in, '$hop_le( $tu ) && $hop_le( $den )' ) !== false );
t( 'tờ in KHÔNG ghi gì vào bảng chấm công',
	strpos( $than_pdf, "insert( VHCC_DB::t( 'cham_cong'" ) === false
	&& strpos( $than_pdf, "update( VHCC_DB::t( 'cham_cong'" ) === false );

// ============================================================ 18. Nhân sự: hai bậc quyền
/* Anh Thắng chốt "chọn cht phân quyền theo mức" — ĐÚNG hai cửa, và ranh giới không phải cho gọn.
   Gộp hai bậc lại là cửa hàng trưởng cấp được Mã NV dùng chung cả chuỗi và chuyển người giữa hai
   cửa hàng, tức chuyển cả công và lương. */
vhcc_dung_bang();
$U_AD  = array( 'name' => 'Admin',  'role' => 'ADMIN',           'coso' => '' );
$U_QL  = array( 'name' => 'QuanLy', 'role' => 'QUAN_LY',         'coso' => '' );
$U_CHT = array( 'name' => 'CHT_BT', 'role' => 'CUA_HANG_TRUONG', 'coso' => 'TUTU_BT' );
$U_NV  = array( 'name' => 'NhanVien','role' => 'NHAN_VIEN',      'coso' => 'TUTU_BT' );
$U_KT  = array( 'name' => 'KeToan', 'role' => 'KE_TOAN',         'coso' => '' );

/* 🔴 THANG NĂM BẬC anh Thắng chốt 25/08/2026 — Nhân viên < Cửa hàng trưởng < Quản lý <
   Kế toán < Admin. Kế toán *"full quyền ngoài admin"*, nên hồ sơ nhân sự thuộc về họ, KHÔNG
   thuộc về Quản lý hay Cửa hàng trưởng. Trước đó ba vai giữa tự có quyền hồ sơ, và Kế toán
   thì không — ngược hẳn thang. */
teq( 'Admin sửa được hồ sơ', true, VHCC_NhanSu::co_sua_ho_so( $U_AD ) );
teq( 'Kế toán sửa được hồ sơ (full quyền ngoài admin)', true, VHCC_NhanSu::co_sua_ho_so( $U_KT ) );
teq( 'Quản lý KHÔNG sửa hồ sơ (việc của họ là check công, báo lỗi)', false, VHCC_NhanSu::co_sua_ho_so( $U_QL ) );
teq( 'Cửa hàng trưởng KHÔNG sửa hồ sơ (báo lên trên, không tự sửa)', false, VHCC_NhanSu::co_sua_ho_so( $U_CHT ) );
teq( 'Nhân viên KHÔNG sửa được hồ sơ', false, VHCC_NhanSu::co_sua_ho_so( $U_NV ) );
/* Việc ảnh hưởng NGOÀI phạm vi một cửa hàng: Quản lý trở lên. */
teq( 'Cửa hàng trưởng KHÔNG có quyền quản trị NV', false, VHCC_NhanSu::co_quan_tri_nv( $U_CHT ) );
teq( 'Admin có', true, VHCC_NhanSu::co_quan_tri_nv( $U_AD ) );
teq( 'Quản lý có', true, VHCC_NhanSu::co_quan_tri_nv( $U_QL ) );
teq( 'Kế toán có', true, VHCC_NhanSu::co_quan_tri_nv( $U_KT ) );
/* Ô lương trong hồ sơ: KẾ TOÁN trở lên. Quản lý check công, không đụng tiền. */
teq( 'Cửa hàng trưởng KHÔNG xem được lương', false, VHCC_NhanSu::co_xem_luong( $U_CHT ) );
teq( 'Quản lý KHÔNG xem được lương (không phải việc của họ)', false, VHCC_NhanSu::co_xem_luong( $U_QL ) );
teq( 'Kế toán xem được lương', true, VHCC_NhanSu::co_xem_luong( $U_KT ) );
teq( 'Admin xem được lương', true, VHCC_NhanSu::co_xem_luong( $U_AD ) );

/* ---- Bậc thang phải LIỀN MẠCH: mọi quyền của bậc dưới, bậc trên đều có ----
   Đây là chốt canh chính cái sai cũ: một vai ở giữa thang mà thiếu quyền của vai dưới nó thì
   sơ đồ không còn là thang, và không ai nhìn ra cho tới lúc có người bị chối oan. */
$THANG = array( $U_NV, $U_CHT, $U_QL, $U_KT, $U_AD );
foreach ( array_keys( VHCC_Vai::QUYEN ) as $q_ ) {
	$truoc = true;
	for ( $i_ = 0; $i_ < count( $THANG ); $i_++ ) {
		$co_ = VHCC_Vai::duoc( $THANG[ $i_ ], $q_ );
		if ( $truoc && ! $co_ ) { continue; }        // chưa tới bậc có quyền này
		if ( ! $truoc && ! $co_ ) {
			t( "quyền $q_ liền mạch theo thang", false, 'bậc ' . ( $i_ + 1 ) . ' mất quyền mà bậc dưới có' );
			break;
		}
		$truoc = ! $co_;
	}
}
t( 'mọi quyền đều liền mạch theo thang bậc', true );
teq( 'Nhân viên chỉ có đúng hai quyền: chấm công + xem công của mình', 2,
	count( array_filter( VHCC_Quyen::bang_quyen( $U_NV ) ) ) );
teq( 'Admin có đủ mọi quyền', count( VHCC_Vai::QUYEN ),
	count( array_filter( VHCC_Quyen::bang_quyen( $U_AD ) ) ) );
/* Cơ sở: NHÂN VIÊN trả false LUÔN, kể cả cơ sở ghi trong dòng phân quyền của họ. */
teq( 'Nhân viên KHÔNG có quyền cơ sở nào, kể cả cơ sở của mình', false,
	VHCC_NhanSu::co_quyen_coso( $U_NV, 'TUTU_BT' ) );
teq( 'Cửa hàng trưởng có quyền cơ sở mình', true, VHCC_NhanSu::co_quyen_coso( $U_CHT, 'TUTU_BT' ) );
teq( 'Cửa hàng trưởng KHÔNG có quyền cơ sở khác', false, VHCC_NhanSu::co_quyen_coso( $U_CHT, 'POSH_HCM' ) );
teq( 'Admin có quyền mọi cơ sở', true, VHCC_NhanSu::co_quyen_coso( $U_AD, 'CO_SO_LA' ) );
teq( 'tiền tố CS_ được bỏ khi so cơ sở', true, VHCC_NhanSu::co_quyen_coso( $U_CHT, 'CS_TUTU_BT' ) );

/* ---- Bốn chốt của luu_ho_so, mỗi chốt một lý do ---- */
$hs = array( 'ma_nv' => 'NV001', 'ho_ten' => 'Nguyễn A', 'cua_hang' => 'TUTU_BT', 'sdt' => '0900' );
teq( 'Nhân viên: không lưu được', false, VHCC_NhanSu::luu_ho_so( $U_NV, $hs )['ok'] );
/* TẠO MỚI là cấp Mã NV dùng chung cả chuỗi -> cửa hàng trưởng KHÔNG được, dù đúng cơ sở mình. */
$r = VHCC_NhanSu::luu_ho_so( $U_CHT, $hs );
t( 'Cửa hàng trưởng KHÔNG tạo được hồ sơ MỚI', empty( $r['ok'] ), $r );
t( 'và không có hồ sơ nào được tạo', VHCC_NhanSu::ho_so( 'NV001' ) === null );
$r = VHCC_NhanSu::luu_ho_so( $U_AD, $hs );
t( 'Admin tạo được', ! empty( $r['ok'] ) && true === $r['tao_moi'], isset( $r['error'] ) ? $r['error'] : '' );
/* 🔴 25/08/2026: HỒ SƠ LÊN BẬC KẾ TOÁN. Cửa hàng trưởng KHÔNG còn sửa hồ sơ, kể cả người của
   chính cửa hàng mình — mô hình anh Thắng giao cho họ đúng bốn việc (chấm công bù, check công,
   chấm công online của mình, lên lịch), còn lại *"báo lên admin xử lý"*. */
$r = VHCC_NhanSu::luu_ho_so( $U_CHT, array( 'ma_nv' => 'NV001', 'sdt' => '0911', 'chuc_vu' => 'Thu ngân' ) );
t( 'Cửa hàng trưởng KHÔNG sửa được hồ sơ người của cửa hàng mình', empty( $r['ok'] ), $r );
teq( 'và SĐT không bị đổi', '0900', VHCC_NhanSu::ho_so( 'NV001' )['sdt'] );
$r = VHCC_NhanSu::luu_ho_so( $U_KT, array( 'ma_nv' => 'NV001', 'sdt' => '0911', 'chuc_vu' => 'Thu ngân' ) );
t( 'Kế toán sửa được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'và sửa vào thật', '0911', VHCC_NhanSu::ho_so( 'NV001' )['sdt'] );
/* ĐỔI CỬA HÀNG là chuyển cả công và lương. Kế toán trở lên đổi được. */
$r = VHCC_NhanSu::luu_ho_so( $U_KT, array( 'ma_nv' => 'NV001', 'cua_hang' => 'POSH_HCM' ) );
t( 'Kế toán đổi được cửa hàng', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'và đổi thật', 'POSH_HCM', VHCC_NhanSu::ho_so( 'NV001' )['cua_hang'] );

/* ---- Ô LƯƠNG: bị BỎ khỏi dữ liệu, không phải ẩn trên màn ---- */
VHCC_NhanSu::luu_ho_so( $U_AD, array( 'ma_nv' => 'NV002', 'ho_ten' => 'Trần B',
	'cua_hang' => 'TUTU_BT', 'luong_co_ban' => '13.000.000', 'so_tai_khoan' => '123', 'ngan_hang' => 'VCB' ) );
teq( 'lương ghi được và bỏ dấu chấm phân cách', 13000000.0,
	(float) VHCC_NhanSu::ho_so( 'NV002' )['luong_co_ban'] );
/* ⚠️ Ô tiền gõ tay. Vét sạch dấu chấm rồi thôi là "13.000.000" thành 13 ĐỒNG — mà ô vẫn có số
   nên bảng lương trông bình thường. Phải phân biệt kiểu Việt (chấm = nghìn) và kiểu Anh. */
teq( 'kiểu Việt: 13.000.000', 13000000.0, VHCC_NhanSu::so_tien( '13.000.000' ) );
teq( 'kiểu Anh: 13,000,000', 13000000.0, VHCC_NhanSu::so_tien( '13,000,000' ) );
teq( 'kiểu Anh có phần thập phân', 13000000.5, VHCC_NhanSu::so_tien( '13,000,000.5' ) );
teq( 'phẩy thập phân kiểu Việt: 13,5', 13.5, VHCC_NhanSu::so_tien( '13,5' ) );
teq( 'có chữ đ và khoảng trắng', 8000000.0, VHCC_NhanSu::so_tien( ' 8.000.000 đ ' ) );
teq( 'số trơn', 9000000.0, VHCC_NhanSu::so_tien( '9000000' ) );
teq( 'ô rỗng là 0', 0.0, VHCC_NhanSu::so_tien( '' ) );
teq( 'ô rác là 0, không phải NaN', 0.0, VHCC_NhanSu::so_tien( 'chưa khai' ) );
teq( 'nhận cả số thật', 7000000.0, VHCC_NhanSu::so_tien( 7000000 ) );
$ds_kt = VHCC_NhanSu::ds_nhan_vien( $U_KT, 'TUTU_BT' );
t( 'Kế toán thấy ô lương', isset( $ds_kt[0]['luong_co_ban'] ) );
$ds_ql = VHCC_NhanSu::ds_nhan_vien( $U_QL, 'TUTU_BT' );
t( 'Quản lý KHÔNG thấy ô lương — họ check công, không đụng tiền',
	count( $ds_ql ) > 0 && ! isset( $ds_ql[0]['luong_co_ban'] ) );
$ds_cht = VHCC_NhanSu::ds_nhan_vien( $U_CHT, 'TUTU_BT' );
t( 'Cửa hàng trưởng: ô lương bị BỎ khỏi dữ liệu, không phải ẩn bằng CSS',
	count( $ds_cht ) > 0 && ! isset( $ds_cht[0]['luong_co_ban'] )
	&& ! isset( $ds_cht[0]['so_tai_khoan'] ) && ! isset( $ds_cht[0]['ngan_hang'] ) );
t( 'nhưng vẫn thấy các ô khác', isset( $ds_cht[0]['sdt'] ) && isset( $ds_cht[0]['chuc_vu'] ) );
/* Cửa hàng trưởng KHÔNG ghi được ô lương dù có gửi lên. */
VHCC_NhanSu::luu_ho_so( $U_CHT, array( 'ma_nv' => 'NV002', 'luong_co_ban' => '99000000' ) );
teq( 'Cửa hàng trưởng gửi ô lương lên: BỊ BỎ, lương không đổi', 13000000.0,
	(float) VHCC_NhanSu::ho_so( 'NV002' )['luong_co_ban'] );
/* Nhân viên: danh sách rỗng hẳn. */
teq( 'Nhân viên xem danh sách: rỗng hẳn', 0, count( VHCC_NhanSu::ds_nhan_vien( $U_NV, 'TUTU_BT' ) ) );
/* Cửa hàng trưởng chỉ thấy người cửa hàng mình, kể cả khi không lọc cơ sở. */
VHCC_NhanSu::luu_ho_so( $U_AD, array( 'ma_nv' => 'NV003', 'ho_ten' => 'Lê C', 'cua_hang' => 'POSH_HCM' ) );
$ma_thay = array();
foreach ( VHCC_NhanSu::ds_nhan_vien( $U_CHT ) as $x ) { $ma_thay[] = $x['ma_nv']; }
t( 'Cửa hàng trưởng KHÔNG thấy người cửa hàng khác dù không lọc',
	! in_array( 'NV003', $ma_thay, true ), implode( ',', $ma_thay ) );

/* ---- Cột lạ gửi lên KHÔNG được ghi (danh sách CHO PHÉP, không phải danh sách CHẶN) ---- */
VHCC_NhanSu::luu_ho_so( $U_AD, array( 'ma_nv' => 'NV002', 'photo_file_id' => 'HACK',
	'trang_thai_dong_bo' => 'HACK', 'ho_ten' => 'Trần B2' ) );
$h2 = VHCC_NhanSu::ho_so( 'NV002' );
teq( 'ô ngoài danh sách cho phép: KHÔNG ghi', '', $h2['photo_file_id'] );
teq( 'ô trạng thái đồng bộ máy cũng không ghi được từ màn hồ sơ', '', $h2['trang_thai_dong_bo'] );
teq( 'nhưng ô hợp lệ vẫn ghi', 'Trần B2', $h2['ho_ten'] );
/* Ngày sai khuôn -> NULL, không ghi rác vào cột DATE. */
VHCC_NhanSu::luu_ho_so( $U_AD, array( 'ma_nv' => 'NV002', 'ngay_sinh' => 'hôm qua' ) );
teq( 'ngày sai khuôn thành NULL, không ghi rác', null, VHCC_NhanSu::ho_so( 'NV002' )['ngay_sinh'] );

/* ---- XOÁ hồ sơ: chặn khi còn chấm công ---- */
teq( 'Cửa hàng trưởng không xoá được hồ sơ', false, VHCC_NhanSu::xoa_ho_so( $U_CHT, 'NV003' )['ok'] );
vhcc_cham( 'POSH_HCM', '2026-08-03', 'NV003', '', '08:00:00', '17:00:00' );
$r = VHCC_NhanSu::xoa_ho_so( $U_AD, 'NV003' );
t( 'còn chấm công thì KHÔNG xoá được (bảng lương sẽ có mã không tra ra tên)',
	empty( $r['ok'] ) && stripos( $r['error'], 'lượt chấm công' ) !== false, $r['error'] );
t( 'và lời báo chỉ đường đúng: đổi Trạng thái làm việc',
	stripos( $r['error'], 'Trạng thái làm việc' ) !== false, $r['error'] );
t( 'hồ sơ vẫn còn', VHCC_NhanSu::ho_so( 'NV003' ) !== null );
VHCC_NhanSu::luu_ho_so( $U_AD, array( 'ma_nv' => 'NV009', 'ho_ten' => 'Chưa chấm', 'cua_hang' => 'TUTU_BT' ) );
t( 'chưa có chấm công thì xoá được', ! empty( VHCC_NhanSu::xoa_ho_so( $U_AD, 'NV009' )['ok'] ) );

/* ---- Xếp bộ phận: quyết định CÔNG THỨC LƯƠNG -> chỉ Admin/Quản lý, và chỉ nhận đúng danh sách ---- */
teq( 'Cửa hàng trưởng không xếp được bộ phận', false,
	VHCC_NhanSu::xep_bo_phan( $U_CHT, 'TUTU_BT', 'Văn phòng' )['ok'] );
$r = VHCC_NhanSu::xep_bo_phan( $U_AD, 'VP_X', 'Văn phòng phụ' );
t( 'bộ phận ngoài danh sách: TỪ CHỐI và nói rõ hậu quả',
	empty( $r['ok'] ) && stripos( $r['error'], 'Chưa xếp' ) !== false, $r['error'] );
t( 'Admin xếp đúng danh sách thì được', ! empty( VHCC_NhanSu::xep_bo_phan( $U_AD, 'VP_X', 'Văn phòng' )['ok'] ) );
teq( 'và engine lương nhận ra ngay', true, VHCC_Luong::la_van_phong( 'VP_X' ) );

/* ---- Mã song song: PHẢI KHAI, không suy từ tên ---- */
teq( 'Cửa hàng trưởng không khai được mã song song', false,
	VHCC_NhanSu::khai_ma_song_song( $U_CHT, 'A1', 'A2', 'X', '' )['ok'] );
t( 'Admin khai được', ! empty( VHCC_NhanSu::khai_ma_song_song( $U_AD, 'A1', 'A2', 'Nguyễn A', 'máy cũ' )['ok'] ) );
t( 'khai lại cùng cặp: bị chặn', empty( VHCC_NhanSu::khai_ma_song_song( $U_AD, 'A2', 'A1', 'x', '' )['ok'] ) );
t( 'hai mã giống nhau: bị chặn', empty( VHCC_NhanSu::khai_ma_song_song( $U_AD, 'A1', 'a1', 'x', '' )['ok'] ) );

// ============================================================ 19. Phân lịch + xin đổi lịch
vhcc_dung_bang();
/* KHOÁ một ô lịch là BỐN cột (cơ sở, ngày, mã, CA). Bỏ `ca` ra là người làm hai ca một ngày chỉ
   giữ được ca sau — ca trước bị ghi đè mất, và mất IM LẶNG vì ô vẫn có dữ liệu. */
$r = VHCC_Lich::xep_lich( $U_CHT, 'TUTU_BT', array(
	array( 'ngay' => '2026-08-03', 'ma_nv' => 'NV1', 'ho_ten' => 'A', 'ca' => 'Sáng', 'viec' => 'Thu tiền' ),
	array( 'ngay' => '2026-08-03', 'ma_nv' => 'NV1', 'ho_ten' => 'A', 'ca' => 'Chiều', 'viec' => 'Vệ sinh' ),
) );
teq( 'xếp 2 ca trong cùng một ngày cho cùng một người', 2, $r['so'] );
teq( 'và giữ được CẢ HAI ô (ca là một phần của khoá)', 2,
	count( VHCC_Lich::ds_lich( 'TUTU_BT', '2026-08-01', '2026-08-31' ) ) );
/* Xếp lại cùng khoá thì SỬA, không thêm dòng thứ hai. */
VHCC_Lich::xep_lich( $U_CHT, 'TUTU_BT', array(
	array( 'ngay' => '2026-08-03', 'ma_nv' => 'NV1', 'ho_ten' => 'A', 'ca' => 'Sáng', 'viec' => 'Trực ghế' ) ) );
$ds = VHCC_Lich::ds_lich( 'TUTU_BT', '2026-08-01', '2026-08-31' );
teq( 'xếp lại cùng khoá: SỬA, không thêm dòng', 2, count( $ds ) );
$sang = null;
foreach ( $ds as $x ) { if ( 'Sáng' === $x['ca'] ) { $sang = $x; } }
teq( 'và việc đã đổi', 'Trực ghế', $sang['viec'] );
/* Ngày sai khuôn hay thiếu mã thì BỎ Ô đó, không ghi rác. */
$r = VHCC_Lich::xep_lich( $U_CHT, 'TUTU_BT', array(
	array( 'ngay' => 'hôm qua', 'ma_nv' => 'NV2', 'ca' => 'Sáng' ),
	array( 'ngay' => '2026-08-04', 'ma_nv' => '', 'ca' => 'Sáng' ) ) );
teq( 'ô sai khuôn bị bỏ, không ghi rác', 0, $r['so'] );
/* Quyền: cửa hàng trưởng chỉ xếp cơ sở mình; nhân viên không xếp được. */
teq( 'Cửa hàng trưởng không xếp được cơ sở khác', false,
	VHCC_Lich::xep_lich( $U_CHT, 'POSH_HCM', array( array( 'ngay' => '2026-08-03', 'ma_nv' => 'X' ) ) )['ok'] );
teq( 'Nhân viên không xếp được lịch', false,
	VHCC_Lich::xep_lich( $U_NV, 'TUTU_BT', array( array( 'ngay' => '2026-08-03', 'ma_nv' => 'X' ) ) )['ok'] );

/* ---- PHÂN LỊCH KHÔNG ĐƯỢC GHI VÀO BẢNG CHẤM CÔNG ----
   Lịch là DỰ ĐỊNH, chấm công là THỰC TẾ. Xếp lịch mà chèn hàng vào cham_cong thì bảng lương thấy
   những ngày CÓ HÀNG mà không có giờ — trông y như "đã đi làm mà quên chấm". Trộn hai thứ đó là
   trả tiền theo dự định. */
teq( 'xếp lịch KHÔNG sinh hàng nào trong bảng chấm công', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );
$than_lich = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-lich.php' );
t( 'và mã cũng không có lệnh ghi nào vào bảng chấm công',
	strpos( $than_lich, "insert( VHCC_DB::t( 'cham_cong'" ) === false
	&& strpos( $than_lich, "update( VHCC_DB::t( 'cham_cong'" ) === false );

/* ---- Xin đổi lịch + duyệt ---- */
$r = VHCC_Lich::xin_doi_lich( $U_NV, array( 'coso' => 'TUTU_BT', 'ma_nv' => 'NV1', 'ho_ten' => 'A',
	'ngay' => '2026-08-03', 'ca' => 'Sáng', 'viec_moi' => 'Thu tiền', 'ly_do' => 'việc nhà' ) );
t( 'nhân viên tự xin đổi lịch được (không cần quyền quản lý)', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$yc = $r['maYc'];
teq( 'thiếu mã NV thì từ chối (duyệt xong không biết xếp cho ai)', false,
	VHCC_Lich::xin_doi_lich( $U_NV, array( 'coso' => 'TUTU_BT', 'ngay' => '2026-08-03' ) )['ok'] );
teq( 'ngày sai khuôn thì từ chối', false,
	VHCC_Lich::xin_doi_lich( $U_NV, array( 'coso' => 'TUTU_BT', 'ma_nv' => 'NV1', 'ngay' => 'mai' ) )['ok'] );
/* Danh sách lọc theo cơ sở người xem phụ trách. */
teq( 'Cửa hàng trưởng thấy yêu cầu của cơ sở mình', 1, count( VHCC_Lich::ds_doi_lich( $U_CHT, true ) ) );
teq( 'Nhân viên không thấy danh sách yêu cầu', 0, count( VHCC_Lich::ds_doi_lich( $U_NV, true ) ) );
/* DUYỆT phải GHI THẬT vào lịch, không chỉ đổi trạng thái. */
$r = VHCC_Lich::duyet( $U_CHT, $yc, true );
t( 'duyệt được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$sang2 = null;
foreach ( VHCC_Lich::ds_lich( 'TUTU_BT', '2026-08-01', '2026-08-31' ) as $x ) {
	if ( '2026-08-03' === $x['ngay'] && 'Sáng' === $x['ca'] ) { $sang2 = $x; }
}
teq( 'DUYỆT ghi thật vào lịch, không chỉ đổi trạng thái', 'Thu tiền', $sang2['viec'] );
t( 'duyệt hai lần: bị chặn (duyệt lại là ghi lịch hai lần)',
	empty( VHCC_Lich::duyet( $U_CHT, $yc, true )['ok'] ) );

/* CÓ đổi sang ngày khác thì phải ghi HAI ô: ngày cũ TRỐNG việc, ngày mới nhận việc. Chỉ ghi ngày
   mới là người đó bị xếp CẢ HAI ngày. */
$r2 = VHCC_Lich::xin_doi_lich( $U_NV, array( 'coso' => 'TUTU_BT', 'ma_nv' => 'NV5', 'ho_ten' => 'E',
	'ngay' => '2026-08-10', 'ca' => 'Sáng', 'viec_moi' => 'Trực ghế', 'doi_sang_ngay' => '2026-08-11' ) );
VHCC_Lich::duyet( $U_AD, $r2['maYc'], true );
$c10 = null; $c11 = null;
foreach ( VHCC_Lich::ds_lich( 'TUTU_BT', '2026-08-01', '2026-08-31' ) as $x ) {
	if ( 'NV5' !== $x['ma_nv'] ) { continue; }
	if ( '2026-08-10' === $x['ngay'] ) { $c10 = $x; }
	if ( '2026-08-11' === $x['ngay'] ) { $c11 = $x; }
}
t( 'đổi sang ngày khác: ngày CŨ vẫn có ô nhưng TRỐNG việc', $c10 && '' === $c10['viec'],
	$c10 ? $c10['viec'] : 'không có ô ngày cũ' );
t( 'ngày MỚI nhận việc', $c11 && 'Trực ghế' === $c11['viec'], $c11 ? $c11['viec'] : 'không có ô ngày mới' );
/* Từ chối thì KHÔNG ghi lịch. */
$r3 = VHCC_Lich::xin_doi_lich( $U_NV, array( 'coso' => 'TUTU_BT', 'ma_nv' => 'NV7', 'ho_ten' => 'G',
	'ngay' => '2026-08-20', 'ca' => 'Sáng', 'viec_moi' => 'KHÔNG ĐƯỢC GHI' ) );
VHCC_Lich::duyet( $U_AD, $r3['maYc'], false );
$co7 = false;
foreach ( VHCC_Lich::ds_lich( 'TUTU_BT', '2026-08-01', '2026-08-31' ) as $x ) {
	if ( 'NV7' === $x['ma_nv'] ) { $co7 = true; }
}
t( 'từ chối thì KHÔNG ghi gì vào lịch', ! $co7 );
/* Cửa hàng trưởng cơ sở khác không duyệt được. */
$r4 = VHCC_Lich::xin_doi_lich( $U_NV, array( 'coso' => 'POSH_HCM', 'ma_nv' => 'NV8',
	'ngay' => '2026-08-20', 'ca' => 'Sáng' ) );
teq( 'Cửa hàng trưởng KHÔNG duyệt được yêu cầu của cơ sở khác', false,
	VHCC_Lich::duyet( $U_CHT, $r4['maYc'], true )['ok'] );

// ============================================================ 20. Hai màn hình: chỉ nối, không tự tính
/* Màn hình KHÔNG được có bản luật quyền thứ hai. Hai bản luật quyền là sớm muộn lệch nhau, và lúc
   lệch thì màn hình cho bấm mà lớp dưới chặn (hoặc tệ hơn: màn chặn mà lớp dưới cho). */
$ad3 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
$i_ns = strpos( $ad3, 'public static function trang_nhan_su()' );
$i_l  = strpos( $ad3, 'public static function trang_lich()' );
$i_in3 = strpos( $ad3, 'public static function trang_in()' );
$than_ns = substr( $ad3, $i_ns, $i_l - $i_ns );
$than_lc = substr( $ad3, $i_l, $i_in3 - $i_l );

foreach ( array( 'nhân sự' => $than_ns, 'phân lịch' => $than_lc ) as $ten => $than ) {
	t( "màn $ten gác quyền trước khi hiện gì", strpos( $than, 'current_user_can( self::CAP )' ) !== false );
	/* Mọi lượt GHI phải đi qua lớp nghiệp vụ, không có $wpdb->insert/update/delete trực tiếp trên
	   màn hình — đi tắt là bỏ qua cả bốn chốt quyền. */
	t( "màn $ten KHÔNG ghi thẳng vào bảng, phải qua lớp nghiệp vụ",
		strpos( $than, '$wpdb->insert' ) === false && strpos( $than, '$wpdb->update' ) === false
		&& strpos( $than, '$wpdb->delete' ) === false );
	t( "màn $ten KHÔNG tự viết luật quyền (không so chuỗi vai trò)",
		strpos( $than, "'CUA_HANG_TRUONG'" ) === false && strpos( $than, "'QUAN_LY'" ) === false );
	// Mọi biểu mẫu POST phải có nonce — thiếu là ai gửi được yêu cầu thay người khác.
	t( "màn $ten có nonce cho biểu mẫu", strpos( $than, 'check_admin_referer' ) !== false
		&& strpos( $than, 'wp_nonce_field' ) !== false );
}
t( 'màn nhân sự gọi đúng lớp nghiệp vụ', strpos( $than_ns, 'VHCC_NhanSu::luu_ho_so' ) !== false
	&& strpos( $than_ns, 'VHCC_NhanSu::xoa_ho_so' ) !== false
	&& strpos( $than_ns, 'VHCC_NhanSu::xep_bo_phan' ) !== false );
t( 'màn phân lịch gọi đúng lớp nghiệp vụ', strpos( $than_lc, 'VHCC_Lich::xep_lich' ) !== false
	&& strpos( $than_lc, 'VHCC_Lich::duyet' ) !== false );
/* Ô lương chỉ dựng khi có quyền — dựng rồi ẩn là số vẫn đi xuống trình duyệt. */
t( 'màn nhân sự chỉ dựng ô lương khi có quyền xem lương',
	strpos( $than_ns, 'if ( VHCC_NhanSu::co_xem_luong( $u ) ) {' ) !== false );
/* Danh sách trường nhận từ POST phải là danh sách CHO PHÉP, không quét bừa $_POST. */
t( 'màn nhân sự nhận trường theo danh sách cho phép, không quét bừa $_POST',
	strpos( $than_ns, "foreach ( array( 'ma_nv', 'ho_ten'" ) !== false
	&& strpos( $than_ns, 'foreach ( $_POST' ) === false );
/* Màn phân lịch phải nói rõ lịch không phải chấm công — đây là chỗ dễ hiểu sai nhất. */
t( 'màn phân lịch nói rõ lịch là dự định, chấm công là thực tế',
	strpos( $than_lc, 'dự định' ) !== false && strpos( $than_lc, 'thực tế' ) !== false );
t( 'màn phân lịch giải thích vì sao khoá có CA', strpos( $than_lc, '(cơ sở, ngày, mã NV, ca)' ) !== false );

// ============================================================ 21. Máy chấm công + OTA — CHẠY THẲNG TRÊN HOST
/* 22/08/2026: bỏ Firebase và bỏ Apps Script khỏi đường máy. Bài kiểm ở đây ĐỔI HẲN luật canh:
   trước canh "mọi lượt phải đi qua cầu nối", nay canh ngược lại — KHÔNG được còn lượt nào đi ra
   ngoài. Đây không phải nới lỏng: điều kiện chặt hơn (không gọi ra ngoài) thay cho điều kiện cũ. */
$than_may  = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-may.php' );
$than_mcong = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-may-cong.php' );
t( 'lớp máy KHÔNG gọi Firebase',
	stripos( $than_may, 'firebasedatabase' ) === false && stripos( $than_may, 'firebaseio' ) === false );
t( 'lớp máy KHÔNG gọi ra ngoài (không cầu nối, không HTTP)',
	strpos( $than_may, 'VHCC_CauNoi::goi' ) === false
	&& strpos( $than_may, 'wp_remote_get' ) === false && strpos( $than_may, 'wp_remote_post' ) === false );
t( 'cổng máy cũng KHÔNG gọi ra ngoài',
	strpos( $than_mcong, 'wp_remote' ) === false && strpos( $than_mcong, 'VHCC_CauNoi' ) === false );
t( 'và KHÔNG chứa khoá Firebase nào', stripos( $than_may, 'FB_SECRET' ) === false
	&& stripos( $than_mcong, 'FB_SECRET' ) === false );
/* Hai nơi cùng tính "khoá máy" là sớm muộn lệch nhau -> lệnh đặt một nơi, máy hỏi một nẻo, hàng
   đợi im lặng rỗng mãi mà không ai biết vì sao. Chỉ MỘT định nghĩa. */
t( 'khoá máy chỉ định nghĩa MỘT chỗ', substr_count( $than_may, 'strtolower( trim( (string) $serial ) )' ) === 0
	&& strpos( $than_may, 'VHCC_MayCong::khoa' ) !== false );
teq( 'khoá máy = serial viết thường', 'sn-a', VHCC_MayCong::khoa( 'SN-A', 'AA:BB' ) );
teq( 'không có serial thì mới lấy MAC', 'aa:bb', VHCC_MayCong::khoa( '', 'AA:BB' ) );
teq( 'không có gì thì rỗng (không bịa)', '', VHCC_MayCong::khoa( '  ', '' ) );

/* ---- 21a. LINK OTA: nay là lớp gác DUY NHẤT ----
   Trước còn Apps Script kiểm quyền Admin ở giữa. Module 4G A7680C chết ở ~532 ký tự, mà link
   release GitHub trả 302 rồi chuyển hướng dài ~943 ký tự. Đẩy một link như vậy là MẤT LUÔN đường
   sửa từ xa của cả chuỗi: phải đi 26 cửa hàng cắm USB. */
teq( 'link raw nhánh bin: nhận', '',
	VHCC_May::ota_url_hop_le( 'https://raw.githubusercontent.com/chu/repo/bin/fw.bin' ) );
$e = VHCC_May::ota_url_hop_le( 'https://github.com/chu/repo/releases/download/v1/fw.bin' );
t( 'link RELEASE của GitHub: TỪ CHỐI và nói rõ vì sao (302 -> chuyển hướng 943 ký tự)',
	'' !== $e && stripos( $e, '302' ) !== false && stripos( $e, 'raw' ) !== false, $e );
t( 'http thường: từ chối', '' !== VHCC_May::ota_url_hop_le( 'http://x.test/fw.bin' ) );
t( 'không kết thúc .bin: từ chối', '' !== VHCC_May::ota_url_hop_le( 'https://x.test/fw.zip' ) );
t( 'rỗng: từ chối', '' !== VHCC_May::ota_url_hop_le( '' ) );
$dai = 'https://x.test/' . str_repeat( 'a', 400 ) . '.bin';
$e2 = VHCC_May::ota_url_hop_le( $dai );
t( 'link quá dài: từ chối và nói con số', '' !== $e2 && stripos( $e2, '532' ) !== false, $e2 );

// ---- 21b. Bảng máy, nhịp sống, gán cơ sở ----
vhcc_dung_bang();
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-A', 'mac' => 'AA', 'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-B', 'mac' => 'BB', 'cua_hang' => '' ) );
$id_a = (int) $wpdb->get_var( "SELECT id FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-A'" );
$id_b = (int) $wpdb->get_var( "SELECT id FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-B'" );

$m = VHCC_May::ds_may();
teq( 'danh sách máy đọc thẳng MySQL, không cần PIN', true, ! empty( $m['ok'] ) );
teq( 'máy CHƯA gửi nhịp lần nào VẪN hiện ra (LEFT JOIN)', 2, count( $m['data'] ) );
t( 'và bị đánh dấu là đứt', empty( $m['data'][0]['con_song'] ) && empty( $m['data'][1]['con_song'] ) );

/* Ngưỡng "còn sống" là phép thuần — thử bằng con số, vì báo nhầm thì người ta chạy tới cửa hàng
   vô ích, còn bỏ sót thì cả ngày không ai biết một cơ sở đang không chấm công được. */
$moc = strtotime( '2026-08-22 10:00:00' );
t( 'nhịp 1 phút trước: còn sống', VHCC_May::con_song( '2026-08-22 09:59:00', $moc ) );
t( 'nhịp đúng ngưỡng 5 phút: còn sống', VHCC_May::con_song( '2026-08-22 09:55:00', $moc ) );
t( 'nhịp 6 phút trước: ĐỨT', ! VHCC_May::con_song( '2026-08-22 09:54:00', $moc ) );
t( 'chưa có nhịp nào: ĐỨT (không coi là sống)', ! VHCC_May::con_song( '', $moc ) );

/* Gán cơ sở: lượt bấm đang nằm chờ phải TỰ vào bảng chấm công. Không tự chuyển thì người ta phải
   gõ tay lại từng lượt — và lượt bấm là công của người thật. */
$wpdb->insert( VHCC_DB::t( 'cho_gan' ), array( 'nhan_luc' => '2026-08-20 08:00:00',
	'serial' => 'SN-B', 'mac' => 'BB', 'ma_nv' => 'NV900', 'ho_ten' => 'Chờ Gán',
	'thoi_diem' => '2026-08-20 08:00:00', 'da_chuyen' => '' ) );
$wpdb->insert( VHCC_DB::t( 'cho_gan' ), array( 'nhan_luc' => '2026-08-20 17:30:00',
	'serial' => 'SN-B', 'mac' => 'BB', 'ma_nv' => 'NV900', 'ho_ten' => 'Chờ Gán',
	'thoi_diem' => '2026-08-20 17:30:00', 'da_chuyen' => '' ) );
$r = VHCC_May::gan_may( $id_b, 'POSH_HCM' );
t( 'gán máy: xong', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'bảng máy đã đổi cơ sở', 'POSH_HCM',
	$wpdb->get_var( "SELECT cua_hang FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-B'" ) );
$cc = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='NV900'", ARRAY_A );
t( 'lượt bấm chờ gán đã vào bảng chấm công', null !== $cc );
teq( 'và HAI lượt gộp thành một cặp vào/ra', array( 8 * 3600, 17 * 3600 + 1800 ),
	array( (int) $cc['gio_vao_giay'], (int) $cc['gio_ra_giay'] ) );
teq( 'hàng chờ gán được đánh dấu đã chuyển', 0, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'cho_gan' ) . " WHERE da_chuyen=''" ) );
/* Gán lại lần nữa không được nhân đôi: đi qua đúng `ghi_gio` nên chỉ nới, không đẻ hàng mới. */
VHCC_May::gan_may( $id_b, 'POSH_HCM' );
teq( 'gán lại không đẻ thêm hàng chấm công', 1, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='NV900'" ) );
$r = VHCC_May::gan_may( $id_b, '' );
t( 'gán mà thiếu cơ sở: chặn', empty( $r['ok'] ) );
$r = VHCC_May::gan_may( 99999, 'POSH_HCM' );
t( 'gán máy không có thật: chặn, không tạo bừa', empty( $r['ok'] ) );

// ---- 21c. HÀNG ĐỢI LỆNH nằm trên host ----
$r = VHCC_May::yeu_cau_quet( $id_a );
t( 'đặt được lệnh quét', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$op1 = $r['opId'];
teq( 'máy đó có 1 lệnh đang chờ', 1, VHCC_MayCong::so_lenh_cho( 'sn-a' ) );
teq( 'máy KHÁC không thấy lệnh của nó', 0, VHCC_MayCong::so_lenh_cho( 'sn-b' ) );

$l = VHCC_MayCong::lay_lenh( array( 'hikSerial' => 'SN-A' ) );
teq( 'máy lấy đúng lệnh của mình', $op1, $l['opId'] );
teq( 'và đúng tên việc', 'scan', $l['action'] );
/* Tên trường phải giữ ĐÚNG như firmware đang đọc — đổi là phải OTA cả chuỗi mới chạy lại được. */
foreach ( array( 'opId', 'action', 'employeeNo', 'name', 'pin', 'gender', 'hasPhoto', 'date',
	'time', 'which', 'startTime', 'endTime', 'bfImage' ) as $khoa_fw ) {
	t( 'gói lệnh có trường ' . $khoa_fw . ' đúng tên firmware đang đọc', array_key_exists( $khoa_fw, $l ) );
}
/* 🔴 Đã gửi mà chưa báo xong thì VẪN phải gửi lại: "đã gửi" không có nghĩa là "máy nhận được".
   Đây đúng là ca 4G rớt giữa chừng — trên Firebase máy phải tự xoá và xoá hỏng là lệnh nằm lại
   chặn sạch hàng phía sau. */
$l2 = VHCC_MayCong::lay_lenh( array( 'hikSerial' => 'SN-A' ) );
teq( 'lấy lại lần nữa: VẪN ra lệnh đó (chưa báo xong)', $op1, $l2['opId'] );
$x = VHCC_MayCong::bao_xong( array( 'opId' => $op1, 'ketQua' => 'quét 12 người' ) );
teq( 'báo xong: ghi được', 1, (int) $x['daGhi'] );
$l3 = VHCC_MayCong::lay_lenh( array( 'hikSerial' => 'SN-A' ) );
t( 'sau khi báo xong: hàng đợi rỗng', ! empty( $l3['empty'] ) );
teq( 'lệnh xong KHÔNG bị xoá — hàng đợi cũng là nhật ký', 1, (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'queue' ) . " WHERE op_id=%s", $op1 ) ) );
teq( 'và giữ lại kết quả máy báo về', 'quét 12 người', $wpdb->get_var( $wpdb->prepare(
	"SELECT ket_qua FROM " . VHCC_DB::t( 'queue' ) . " WHERE op_id=%s", $op1 ) ) );
t( 'máy không khai serial/mac: không được phát lệnh của ai cả',
	! empty( VHCC_MayCong::lay_lenh( array() )['empty'] ) );

/* Lệnh xếp theo thứ tự đặt — lệnh cũ nhất ra trước, mỗi lượt MỘT lệnh (gói phải < ~1KB cho 4G). */
$o1 = VHCC_May::dat_lenh( 'sn-a', 'add', array( 'ma_nv' => 'NV1' ) );
$o2 = VHCC_May::dat_lenh( 'sn-a', 'add', array( 'ma_nv' => 'NV2' ) );
$la = VHCC_MayCong::lay_lenh( array( 'hikSerial' => 'SN-A' ) );
teq( 'lệnh cũ nhất ra trước', 'NV1', $la['employeeNo'] );
teq( 'mỗi lượt chỉ MỘT lệnh', 2, VHCC_MayCong::so_lenh_cho( 'sn-a' ) );
/* Mã op sinh ở máy chủ và không được trùng: firmware giữ sổ `opDone` theo chuỗi này, trùng mã là
   lệnh mới bị bỏ vì máy tưởng đã làm rồi. */
t( 'mã op không trùng nhau', $o1['opId'] !== $o2['opId'] );

$r = VHCC_May::xoa_lenh( $o2['opId'] );
t( 'xoá được lệnh CHƯA gửi', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$r = VHCC_May::xoa_lenh( $o1['opId'] );
t( 'lệnh ĐÃ xuống máy: KHÔNG xoá, và nói rõ vì sao',
	empty( $r['ok'] ) && stripos( $r['error'], 'đã xuống máy' ) !== false, $r['error'] );
$r = VHCC_May::xoa_lenh( 'khong-co-that' );
t( 'xoá lệnh không có thật: báo lỗi chứ không im', empty( $r['ok'] ) );

// ---- 21d. Tải lại: chặn khoảng quá rộng ----
$r = VHCC_May::tai_lai( $id_a, '2026-08-01', '2026-08-10' );
t( 'đặt được lệnh tải lại', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$q = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . VHCC_DB::t( 'queue' ) . " WHERE op_id=%s",
	$r['opId'] ), ARRAY_A );
teq( 'khoảng ghi kèm giờ đầu ngày', '2026-08-01 00:00:00', $q['tu_gio'] );
teq( 'và giờ cuối ngày — thiếu là mất trọn ngày cuối', '2026-08-10 23:59:59', $q['den_gio'] );
$r = VHCC_May::tai_lai( $id_a, '2026-01-01', '2026-08-10' );
t( 'khoảng quá rộng: chặn và nói con số ngày',
	empty( $r['ok'] ) && stripos( $r['error'], '31 ngày' ) !== false, $r['error'] );
$r = VHCC_May::tai_lai( $id_a, '2026-08-10', '2026-08-01' );
t( 'ngày kết thúc sớm hơn ngày bắt đầu: chặn', empty( $r['ok'] ) );
$r = VHCC_May::tai_lai( $id_a, '10/08/2026', '2026-08-11' );
t( 'ngày sai khuôn: chặn', empty( $r['ok'] ) );

/* Cờ DỪNG đọc một lần là hết — để lại thì lần tải lại sau bị dừng ngay lúc vừa bắt đầu. */
VHCC_May::dung_tai_lai( $id_a );
teq( 'máy hỏi: có lệnh dừng', 1, VHCC_MayCong::hoi_dung( array( 'hikSerial' => 'SN-A' ) )['dung'] );
teq( 'hỏi lần nữa: cờ đã tiêu', 0, VHCC_MayCong::hoi_dung( array( 'hikSerial' => 'SN-A' ) )['dung'] );

// ---- 21e. NHỊP SỐNG ----
$n = VHCC_MayCong::nhip( array( 'hikSerial' => 'SN-A', 'macAddress' => 'AA', 'stationName' => 'TUTU_BT',
	'fw' => '2026-08-20', 'duong' => '4g', 'song' => '21', 'heap' => 130000, 'soTong' => 4021 ) );
teq( 'nhịp trả về đúng cơ sở của máy', 'TUTU_BT', $n['coSo'] );
teq( 'bảng nhịp có đúng MỘT hàng cho máy đó', 1, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'may_nhip' ) . " WHERE tram='sn-a'" ) );
VHCC_MayCong::nhip( array( 'hikSerial' => 'SN-A', 'macAddress' => 'AA', 'fw' => '2026-08-21' ) );
teq( 'nhịp thứ hai ĐÈ lên, không cộng dồn', 1, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'may_nhip' ) . " WHERE tram='sn-a'" ) );
teq( 'và giữ bản firmware mới nhất', '2026-08-21', $wpdb->get_var(
	"SELECT fw FROM " . VHCC_DB::t( 'may_nhip' ) . " WHERE tram='sn-a'" ) );
teq( 'nhịp báo còn lệnh đang chờ', 1, VHCC_MayCong::nhip( array( 'hikSerial' => 'SN-A' ) )['coLenh'] );
$n2 = VHCC_MayCong::nhip( array( 'hikSerial' => 'SN-LA', 'macAddress' => 'ZZ', 'stationName' => 'CHUA_DAT_TEN' ) );
teq( 'máy lạ: KHÔNG bịa cơ sở', '', $n2['coSo'] );
teq( 'mà báo là chờ gán', 1, $n2['choGan'] );
t( 'máy lạ vẫn tự hiện ra trong bảng máy để còn gán được', null !== $wpdb->get_var(
	"SELECT id FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-LA'" ) );
$n3 = VHCC_MayCong::nhip( array( 'macAddress' => '' ) );
t( 'nhịp không có serial lẫn mac: bỏ qua, không ghi hàng rác',
	! empty( $n3['boQua'] ) );

// ---- 21f. OTA đặt trên host ----
$r = VHCC_May::dat_ota( '2026-08-22', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', '' );
t( 'đẩy CẢ CHUỖI mà thiếu xác nhận: KHÔNG đẩy',
	empty( $r['ok'] ) && stripos( $r['error'], 'DONG Y' ) !== false, $r['error'] );
$r = VHCC_May::dat_ota( '2026-08-22', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', 'dong y' );
t( 'xác nhận sai chữ (chữ thường): KHÔNG đẩy', empty( $r['ok'] ) );
$r = VHCC_May::dat_ota( '2026-08-22', 'https://github.com/c/r/releases/download/v1/fw.bin', 'DONG Y' );
t( 'xác nhận đúng mà link release: VẪN chặn', empty( $r['ok'] ) && stripos( $r['error'], '302' ) !== false );
$r = VHCC_May::dat_ota( '', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', 'DONG Y' );
t( 'thiếu số phiên bản: chặn', empty( $r['ok'] ) );
/* Đặt cho MỘT máy thì KHÔNG đòi gõ xác nhận — bắt gõ là người ta ngại thử rồi đẩy thẳng cả
   chuỗi, đúng thứ cần tránh. Bản hỏng đẩy cả chuỗi thì không còn đường gọi về. */
$r = VHCC_May::dat_ota( '2026-08-22', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', '', $id_a );
t( 'đặt riêng cho một máy: không cần xác nhận', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'máy đó nhận bản riêng', '2026-08-22', VHCC_MayCong::ota_cho( 'sn-a' )['ver'] );
teq( 'máy khác KHÔNG nhận gì', '', VHCC_MayCong::ota_cho( 'sn-b' )['ver'] );
t( 'máy đang chạy đúng bản đó: KHÔNG bảo nạp lại (nếu không là nạp vòng vô tận)',
	'' === VHCC_MayCong::ota_cho( 'sn-a', '2026-08-22' )['ver'] );
VHCC_May::dat_ota( '2026-08-23', 'https://raw.githubusercontent.com/c/r/bin/moi.bin', 'DONG Y' );
teq( 'đặt cả chuỗi: máy chưa có bản riêng thì theo bản chung', '2026-08-23',
	VHCC_MayCong::ota_cho( 'sn-b' )['ver'] );
teq( 'máy CÓ bản riêng thì bản riêng thắng', '2026-08-22', VHCC_MayCong::ota_cho( 'sn-a' )['ver'] );
VHCC_May::go_ota( $id_a );
teq( 'gỡ bản riêng: máy đó quay về bản chung', '2026-08-23', VHCC_MayCong::ota_cho( 'sn-a' )['ver'] );
VHCC_May::go_ota();
teq( 'gỡ bản chung: không còn lệnh nạp nào', '', VHCC_MayCong::ota_cho( 'sn-a' )['ver'] );
/* Nhịp phải chở luôn lệnh OTA — bốn lượt gọi mỗi phút × 26 máy là 4G nghẽn. */
VHCC_May::dat_ota( '2026-08-24', 'https://raw.githubusercontent.com/c/r/bin/x.bin', 'DONG Y' );
$n4 = VHCC_MayCong::nhip( array( 'hikSerial' => 'SN-A', 'fw' => '2026-08-01' ) );
teq( 'nhịp chở luôn bản OTA đang đặt', '2026-08-24', $n4['otaVer'] );
t( 'và chở luôn link', strpos( $n4['otaUrl'], '/bin/x.bin' ) !== false );
VHCC_May::go_ota();

// ---- 21g. Sổ mặt trong máy: chỗ người nghỉ việc vẫn chấm công được ----
VHCC_MayCong::nhan_roster( array( 'hikSerial' => 'SN-A', 'dau' => 1, 'ds' => array(
	array( 'ma' => 'NV1', 'ten' => 'Người Một', 'anh' => true ),
	array( 'ma' => 'NV2', 'ten' => 'Người Hai', 'anh' => true ),
	array( 'ma' => 'NVCU', 'ten' => 'Người Đã Nghỉ', 'anh' => true ),
) ) );
teq( 'nhận đủ sổ mặt', 3, count( VHCC_May::roster( $id_a )['data'] ) );
VHCC_MayCong::nhan_roster( array( 'hikSerial' => 'SN-A', 'dau' => 1, 'ds' => array(
	array( 'ma' => 'NV1', 'ten' => 'Người Một' ) ) ) );
teq( 'quét lại (dau=1) thì XOÁ sổ cũ, không trộn hai lần quét', 1,
	count( VHCC_May::roster( $id_a )['data'] ) );

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NV1', 'ho_ten' => 'Người Một',
	'cua_hang' => 'TUTU_BT', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NVCU', 'ho_ten' => 'Người Đã Nghỉ',
	'cua_hang' => 'TUTU_BT', 'trang_thai_lam_viec' => 'Đã nghỉ việc' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NVMOI', 'ho_ten' => 'Người Mới',
	'cua_hang' => 'TUTU_BT', 'trang_thai_lam_viec' => 'Đang làm' ) );
VHCC_MayCong::nhan_roster( array( 'hikSerial' => 'SN-A', 'dau' => 1, 'ds' => array(
	array( 'ma' => 'NV1', 'ten' => 'Người Một' ),
	array( 'ma' => 'NVCU', 'ten' => 'Người Đã Nghỉ' ),
	array( 'ma' => 'NVLA', 'ten' => 'Không Có Hồ Sơ' ),
) ) );
$dc = VHCC_May::doi_chieu_roster( $id_a );
$ma_thua = array();
foreach ( $dc['thua'] as $x ) { $ma_thua[] = $x['ma']; }
sort( $ma_thua );
teq( 'chỉ ra mặt CÒN trong máy mà web không cho phép nữa', array( 'NVCU', 'NVLA' ), $ma_thua );
teq( 'và người có hồ sơ mà chưa có mặt trong máy', 'NVMOI', $dc['thieu'][0]['ma'] );
t( 'nói rõ vì sao từng người bị coi là thừa',
	stripos( implode( ' ', array_column( $dc['thua'], 'vi_sao' ) ), 'nghỉ' ) !== false );

// ---- 21h. Ảnh máy trích theo yêu cầu ----
VHCC_MayCong::nhan_anh_trich( array( 'hikSerial' => 'SN-A', 'opId' => 'gp-1', 'employeeNo' => 'NV1',
	'date' => '2026-08-20', 'time' => '08:00:00', 'which' => 'vao', 'anh' => 'BASE64...' ) );
teq( 'ảnh trích được giữ lại', 1, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'anh_trich' ) . " WHERE ma_nv='NV1'" ) );
t( 'gói không có ảnh: bỏ qua, không ghi hàng rỗng',
	! empty( VHCC_MayCong::nhan_anh_trich( array( 'hikSerial' => 'SN-A' ) )['boQua'] ) );

// ---- 21i. Ảnh của lệnh thêm mặt đi lượt RIÊNG, không kèm vào gói lệnh ----
$oa = VHCC_May::dat_lenh( 'sn-b', 'add', array( 'ma_nv' => 'NV5', 'co_anh' => 1,
	'anh_b64' => str_repeat( 'x', 5000 ) ) );
$lb = VHCC_MayCong::lay_lenh( array( 'hikSerial' => 'SN-B' ) );
t( 'gói lệnh KHÔNG kèm ảnh (module 4G đọc được ~1KB một lượt)',
	strlen( wp_json_encode( $lb ) ) < 1000 );
teq( 'nhưng có cờ báo là có ảnh', true, $lb['hasPhoto'] );
teq( 'ảnh lấy bằng lượt riêng', 5000, strlen( VHCC_MayCong::anh_cua_lenh( array( 'opId' => $oa['opId'] ) )['anh'] ) );
VHCC_MayCong::bao_xong( array( 'opId' => $oa['opId'] ) );
teq( 'báo xong thì XOÁ ảnh — nặng mà không dùng vào việc gì nữa', '',
	(string) $wpdb->get_var( $wpdb->prepare( "SELECT anh_b64 FROM " . VHCC_DB::t( 'queue' )
		. " WHERE op_id=%s", $oa['opId'] ) ) );

// ---- 21j. GÓI "viec" ĐI QUA CỔNG THẬT ----
/* Đây là chỗ dễ hỏng nhất của việc gộp chung đường: gói `viec` KHÔNG phải lượt chấm công, để nó
   chạy tiếp xuống phần đọc giờ là sinh ra "GIO_SAI_KHUON" đầy nhật ký và che mất lỗi thật. */
vhcc_dung_bang();
delete_option( 'vhcc_nhat_ky_may' );
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-0001', 'mac' => 'AA:BB:CC:DD:EE:01',
	'cua_hang' => 'TUTU_BT' ) );
list( $ma_http, $than ) = vhcc_may_gui( array( 'viec' => 'nhip', 'hikSerial' => 'SN-0001',
	'macAddress' => 'AA:BB:CC:DD:EE:01', 'fw' => '2026-08-22' ) );
teq( 'gói nhịp qua cổng: 200', 200, $ma_http );
teq( 'và trả SUCCESS (firmware chỉ tìm đúng chuỗi đó)', 'SUCCESS', $than['status'] );
teq( 'nhịp được ghi', 1, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'may_nhip' ) . " WHERE tram='sn-0001'" ) );
teq( 'gói viec KHÔNG rơi xuống phần chấm công', 0, (int) $wpdb->get_var(
	'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );
$nk = get_option( 'vhcc_nhat_ky_may', array() );
teq( 'và KHÔNG đẻ dòng nhật ký "giờ sai khuôn"', 0, count( $nk ) );
/* Khoá vẫn phải đúng — gộp chung đường không được kéo theo việc nới cửa. */
list( $ma_sai ) = vhcc_may_gui( array( 'viec' => 'nhip', 'hikSerial' => 'SN-0001' ), 'khoa-bay' );
teq( 'gói viec mà sai khoá: 401, không làm gì cả', 401, $ma_sai );
teq( 'sai khoá thì không ghi nhịp nào thêm', 1, (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . VHCC_DB::t( 'may_nhip' ) . " WHERE tram='sn-0001'" ) );
/* Lượt chấm công thật vẫn phải chạy y như cũ sau khi chèn nhánh `viec` vào giữa. */
list( $ma_cc ) = vhcc_may_gui( vhcc_goi( 'NV77', '2026-08-22 08:00:00' ) );
teq( 'lượt chấm công thật vẫn qua cổng bình thường', 200, $ma_cc );
t( 'và được ghi vào bảng', null !== vhcc_hang( 'TUTU_BT', '2026-08-22', 'NV77' ) );

// ---- 21k. Việc lạ: KHÔNG bắt máy đẩy lại vô hạn ----
t( 'việc máy chủ chưa biết: bỏ qua chứ không báo lỗi (firmware cũ hơn máy chủ là chuyện thường)',
	! empty( VHCC_MayCong::phuc_vu( 'viec-tu-ban-sau', array() )['boQua'] ) );

// ============================================================ 22. Màn máy: mỏng, không tự tính
$ad4 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
$i_m = strpos( $ad4, 'public static function trang_may()' );
$i_m2 = strpos( $ad4, 'public static function trang_in()', $i_m );
$than_tm = substr( $ad4, $i_m, $i_m2 - $i_m );
t( 'màn máy gác quyền', strpos( $than_tm, 'current_user_can( self::CAP )' ) !== false );
t( 'màn máy có nonce', strpos( $than_tm, 'check_admin_referer' ) !== false );
/* Canh LỜI GỌI chứ không canh CHỮ: màn có nhắc Firebase trong câu giải thích "trước kia hàng
   đợi nằm ở đâu", mà câu đó đáng giữ. Cấm cả chữ là ép xoá lời giải thích để qua bài kiểm. */
t( 'màn máy KHÔNG gọi Firebase, không gọi ra ngoài',
	stripos( $than_tm, 'firebaseio' ) === false && stripos( $than_tm, 'firebasedatabase' ) === false
	&& stripos( $than_tm, '.json?auth' ) === false && strpos( $than_tm, 'wp_remote' ) === false
	&& strpos( $than_tm, 'VHCC_CauNoi' ) === false );
t( 'mọi việc đi qua lớp VHCC_May', strpos( $than_tm, 'VHCC_May::' ) !== false );
t( 'màn máy KHÔNG tự viết câu SQL nào', stripos( $than_tm, 'SELECT * FROM ' . VHCC_DB::t( 'queue' ) ) === false
	&& strpos( $than_tm, '$wpdb->update' ) === false && strpos( $than_tm, '$wpdb->insert' ) === false );
/* Máy MẤT NHỊP để TRÊN CÙNG: cửa hàng đó đang không chấm công lên được mà không ai biết. Để nó
   xuống dưới là đúng thứ quan trọng nhất bị cuộn qua. */
$i_dut = strpos( $than_tm, 'không gửi nhịp' );
$i_ds  = strpos( $than_tm, 'Danh sách máy' );
$i_fw  = strpos( $than_tm, 'Cập nhật firmware' );
t( 'phần máy mất nhịp nằm TRƯỚC danh sách máy và trước firmware',
	$i_dut !== false && $i_dut < $i_ds && $i_dut < $i_fw );
t( 'màn máy cảnh báo rõ hậu quả của link release (302 / 943 / 532 ký tự)',
	strpos( $than_tm, '302' ) !== false && strpos( $than_tm, '532' ) !== false );
t( 'ô xác nhận DONG Y có trên màn', strpos( $than_tm, 'DONG Y' ) !== false );
t( 'màn máy mời thử MỘT máy trước khi đẩy cả chuỗi', stripos( $than_tm, 'thử một máy trước' ) !== false );
t( 'màn máy nói rõ lượt bấm chờ gán tự vào bảng khi gán cơ sở',
	stripos( $than_tm, 'tự vào' ) !== false );
/* Màn KHÔNG có nút xoá máy — xoá là mất chỗ gán của máy vừa gửi lượt đầu. */
t( 'màn máy không có nút xoá máy', stripos( $than_tm, 'xoa_may' ) === false );

// ============================================================ 23. Sổ đối chiếu 111 hàm
/* Bản kê nói CÓ GÌ PHẢI PORT; sổ đối chiếu nói ĐÃ PORT TỚI ĐÂU. Không có mục này thì "còn lại mấy
   hàm" là câu không ai trả lời được, và hàm bị bỏ quên sẽ im lặng — đúng loại lỗi cả việc này
   đang tránh. */
$so_raw = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/so-doi-chieu.txt' );
$so = array();
foreach ( explode( "\n", $so_raw ) as $d ) {
	$d = rtrim( $d );
	if ( '' === $d || '#' === $d[0] ) { continue; }
	$p = preg_split( '/\s+/', $d, 2 );
	$so[ $p[0] ] = isset( $p[1] ) ? trim( $p[1] ) : '';
}
$ke = array();
foreach ( explode( "\n", file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/ham-giao-dien.txt' ) ) as $d ) {
	$d = trim( $d );
	if ( '' !== $d && '#' !== $d[0] ) { $ke[] = $d; }
}
teq( 'bản kê vẫn đúng 111 hàm', 111, count( $ke ) );
$thieu_so = array_diff( $ke, array_keys( $so ) );
$du_so    = array_diff( array_keys( $so ), $ke );
t( 'MỌI hàm trong bản kê đều có dòng trong sổ đối chiếu', count( $thieu_so ) === 0,
	implode( ', ', $thieu_so ) );
t( 'sổ không kê hàm nào ngoài bản kê', count( $du_so ) === 0, implode( ', ', $du_so ) );

$loi_tt = array();
$khong_can_thieu_ly_do = array();
$ham_thieu = array();
$cn_lech = array();
foreach ( $so as $ten => $tt ) {
	$p = preg_split( '/\s+/', $tt, 2 );
	$loai = $p[0];
	$them = isset( $p[1] ) ? trim( $p[1] ) : '';
	if ( ! in_array( $loai, array( 'MYSQL', 'CAUNOI', 'KHONGCAN' ), true ) ) {
		$loi_tt[] = "$ten: $loai"; continue;
	}
	if ( 'KHONGCAN' === $loai ) {
		/* KHONGCAN mà không ghi lý do là "chưa làm" đội lốt "không cần". Bắt buộc phải có lý do. */
		if ( strlen( $them ) < 20 ) { $khong_can_thieu_ly_do[] = $ten; }
		continue;
	}
	if ( 'CAUNOI' === $loai ) {
		if ( ! in_array( $ten, $fns, true ) ) { $cn_lech[] = $ten; }
		continue;
	}
	/* MYSQL: lớp và hàm phải TỒN TẠI THẬT. Ghi tên vào sổ mà chưa viết hàm là sổ nói dối. */
	if ( ! preg_match( '/^(VHCC_\w+)::(\w+)$/', $them, $m ) ) { $loi_tt[] = "$ten: '$them'"; continue; }
	if ( ! class_exists( $m[1] ) || ! method_exists( $m[1], $m[2] ) ) { $ham_thieu[] = "$ten -> $them"; }
}
t( 'mọi dòng dùng đúng một trong ba trạng thái', count( $loi_tt ) === 0, implode( ' | ', $loi_tt ) );
t( 'mọi dòng KHONGCAN đều ghi rõ LÝ DO', count( $khong_can_thieu_ly_do ) === 0,
	implode( ', ', $khong_can_thieu_ly_do ) );
t( 'mọi dòng CAUNOI đều có trong danh sách cho phép của cầu nối', count( $cn_lech ) === 0,
	implode( ', ', $cn_lech ) );
t( 'mọi hàm MYSQL trong sổ đều TỒN TẠI THẬT (sổ không nói dối)', count( $ham_thieu ) === 0,
	count( $ham_thieu ) . ' hàm chưa viết: ' . implode( ' | ', array_slice( $ham_thieu, 0, 40 ) ) );

// ============================================================ 24. Quyền · PIN · chống dò
vhcc_dung_bang();
$U_AD  = array( 'name' => 'Admin',  'role' => 'ADMIN',   'coso' => '', 'pin' => '100001' );
$U_QL  = array( 'name' => 'QuanLy', 'role' => 'QUAN_LY', 'coso' => '' );
$U_CHT = array( 'name' => 'CHT',    'role' => 'CUA_HANG_TRUONG', 'coso' => 'TUTU_BT' );
$U_NV  = array( 'name' => 'NV',     'role' => 'NHAN_VIEN', 'coso' => 'TUTU_BT' );

/* ---- Luật PIN: đây là chìa khoá vào toàn bộ chấm công của chuỗi ---- */
teq( 'PIN 6 số bình thường: nhận', '', VHCC_Quyen::pin_hop_le( '481937' ) );
t( '5 số: từ chối', '' !== VHCC_Quyen::pin_hop_le( '48193' ) );
t( 'có chữ: từ chối', '' !== VHCC_Quyen::pin_hop_le( '4819a7' ) );
t( '6 số giống nhau: từ chối', '' !== VHCC_Quyen::pin_hop_le( '444444' ) );
t( 'dãy tăng 123456: từ chối', '' !== VHCC_Quyen::pin_hop_le( '123456' ) );
t( 'dãy tăng 234567: từ chối', '' !== VHCC_Quyen::pin_hop_le( '234567' ) );
t( 'dãy giảm 654321: từ chối', '' !== VHCC_Quyen::pin_hop_le( '654321' ) );
t( 'dãy giảm 543210: từ chối', '' !== VHCC_Quyen::pin_hop_le( '543210' ) );
/* 888888 là PIN admin mặc định của bản gốc — phải nằm trong danh sách chặn. */
t( '888888 bị chặn', '' !== VHCC_Quyen::pin_hop_le( '888888' ) );

/* ---- Bộ đếm nằm trong BẢNG, không trong cache ---- */
$than_q = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-quyen.php' );
t( 'bộ đếm chống dò KHÔNG dùng transient (cache bị xoá là hình phạt tự bỏ)',
	strpos( $than_q, 'set_transient' ) === false && strpos( $than_q, 'get_transient' ) === false );
t( 'bộ đếm dùng bảng nhip_do', strpos( $than_q, "VHCC_DB::t( 'nhip_do' )" ) !== false );
teq( 'đếm lần đầu là 1', 1, VHCC_Quyen::dem( 'thu', 3 )['so'] );
teq( 'đếm cộng dồn', 2, VHCC_Quyen::dem( 'thu', 3 )['so'] );
t( 'chưa quá ngưỡng', ! VHCC_Quyen::dem( 'thu', 3 )['qua'] );
t( 'quá ngưỡng thì báo qua', VHCC_Quyen::dem( 'thu', 3 )['qua'] );
teq( 'đọc đếm KHÔNG cộng thêm', 4, VHCC_Quyen::doc_dem( 'thu' ) );
teq( 'đọc lại vẫn 4', 4, VHCC_Quyen::doc_dem( 'thu' ) );
teq( 'khoá chưa từng đếm thì là 0', 0, VHCC_Quyen::doc_dem( 'chua-co' ) );
/* Cửa sổ hết hạn thì đếm lại từ 1, không cộng dồn vô hạn. */
$wpdb->query( "UPDATE " . VHCC_DB::t( 'nhip_do' ) . " SET cua_so_tu='2020-01-01 00:00:00' WHERE khoa='thu'" );
teq( 'cửa sổ hết hạn: đếm lại từ 1', 1, VHCC_Quyen::dem( 'thu', 3 )['so'] );

/* ---- ĐỔI PIN ---- */
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '111222', 'ho_ten' => 'A', 'vai_tro' => 'NHAN_VIEN' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '333444', 'ho_ten' => 'B', 'vai_tro' => 'NHAN_VIEN' ) );
t( 'hai lần nhập khác nhau: từ chối', empty( VHCC_Quyen::doi_pin( '111222', '555666', '555777' )['ok'] ) );
t( 'trùng PIN đang dùng: từ chối', empty( VHCC_Quyen::doi_pin( '111222', '111222', '111222' )['ok'] ) );
t( 'PIN mới dễ đoán: từ chối', empty( VHCC_Quyen::doi_pin( '111222', '123456', '123456' )['ok'] ) );
$r = VHCC_Quyen::doi_pin( '111222', '333444', '333444' );
t( 'PIN mới đã có người dùng: từ chối và NÓI THẬT lý do',
	empty( $r['ok'] ) && stripos( $r['error'], 'đã có người dùng' ) !== false, $r['error'] );
/* Đụng PIN người khác quá 5 lần / 10 phút thì thôi cho biết gì thêm. */
for ( $i = 0; $i < 5; $i++ ) { VHCC_Quyen::doi_pin( '111222', '333444', '333444' ); }
$r = VHCC_Quyen::doi_pin( '111222', '333444', '333444' );
t( 'quá 5 lần đụng PIN người khác: CHẶN, không cho biết thêm gì',
	empty( $r['ok'] ) && stripos( $r['error'], 'quá nhiều' ) !== false, $r['error'] );
teq( 'hàm thuần: dưới ngưỡng thì còn được', true, VHCC_Quyen::doi_pin_con_duoc( 4 ) );
teq( 'hàm thuần: tới ngưỡng thì hết', false, VHCC_Quyen::doi_pin_con_duoc( 5 ) );
/* Đổi được thì CHỈ đổi cột PIN, không đụng vai trò / cửa hàng. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhip_do' ) );
$wpdb->update( VHCC_DB::t( 'phan_quyen' ),
	array( 'vai_tro' => 'CUA_HANG_TRUONG', 'cua_hang' => 'TUTU_BT' ), array( 'pin' => '111222' ) );
$r = VHCC_Quyen::doi_pin( '111222', '481937', '481937' );
t( 'đổi PIN được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$sau = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'phan_quyen' ) . " WHERE pin='481937'", ARRAY_A );
t( 'chỉ đổi PIN, vai trò và cửa hàng KHÔNG đổi',
	$sau && 'CUA_HANG_TRUONG' === $sau['vai_tro'] && 'TUTU_BT' === $sau['cua_hang'] );
t( 'PIN cũ mất hiệu lực ngay',
	null === $wpdb->get_var( "SELECT id FROM " . VHCC_DB::t( 'phan_quyen' ) . " WHERE pin='111222'" ) );

/* ---- Xoá phân quyền: hai chốt tự khoá mình ra ngoài ---- */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '100001', 'ho_ten' => 'Admin', 'vai_tro' => 'ADMIN' ) );
$r = VHCC_Quyen::xoa_phan_quyen( $U_AD, '100001' );
t( 'không xoá được dòng của CHÍNH MÌNH (xoá là mất quyền, không vào lại được)',
	empty( $r['ok'] ) && stripos( $r['error'], 'chính bạn' ) !== false, $r['error'] );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '200002', 'ho_ten' => 'Ad2', 'vai_tro' => 'ADMIN' ) );
$r = VHCC_Quyen::xoa_phan_quyen( $U_AD, '200002' );
t( 'xoá được admin khác khi còn admin', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '300003', 'ho_ten' => 'Ad3', 'vai_tro' => 'ADMIN' ) );
$U_AD3 = array( 'name' => 'Ad3', 'role' => 'ADMIN', 'coso' => '', 'pin' => '300003' );
$r = VHCC_Quyen::xoa_phan_quyen( $U_AD3, '100001' );
t( 'còn hai admin thì xoá được một', ! empty( $r['ok'] ) );
$r = VHCC_Quyen::xoa_phan_quyen( array( 'name' => 'x', 'role' => 'ADMIN', 'pin' => '999' ), '300003' );
t( 'KHÔNG xoá được admin CUỐI CÙNG (không còn ai cấp lại quyền)',
	empty( $r['ok'] ) && stripos( $r['error'], 'duy nhất' ) !== false, $r['error'] );
teq( 'cửa hàng trưởng không sửa được phân quyền', false,
	VHCC_Quyen::luu_phan_quyen( $U_CHT, array( 'pin' => '777888' ) )['ok'] );

/* ---- Cấp PIN hàng loạt ---- */
vhcc_dung_bang();
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'N1', 'ho_ten' => 'Nguyễn A', 'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'N2', 'ho_ten' => '', 'cua_hang' => 'TUTU_BT' ) );
$r = VHCC_Quyen::cap_pin_hang_loat( $U_AD, array( 'N1', 'N2', 'KHONG_CO' ) );
teq( 'cấp được 2 PIN', 2, count( $r['cap'] ) );
teq( 'mã chưa có hồ sơ thì bỏ và nói lý do', 1, count( $r['boQua'] ) );
$pins = array();
foreach ( $r['cap'] as $x ) { $pins[] = $x['pin']; }
$xau = array();
foreach ( $pins as $p ) { if ( '' !== VHCC_Quyen::pin_hop_le( $p ) ) { $xau[] = $p; } }
teq( 'PIN sinh ra KHÔNG bao giờ là PIN dễ đoán', array(), $xau );
/* ⚠️ Phép thử theo HÀNH VI ở trên gần như không bao giờ bắt được lỗi bỏ chốt này: chỉ có 8 PIN
   trong danh sách cấm trên một triệu khả năng, nên bỏ chốt đi thì 500 lượt sinh vẫn ra PIN sạch.
   Đã thử phá và nó lọt. Nên chỗ này phải soi MÃ NGUỒN — không phải vì thích soi mã, mà vì hành
   vi không phân biệt được. */
t( 'hàm sinh PIN có kiểm pin_hop_le trước khi trả về',
	preg_match( '/for \(.*sinh_pin|private static function sinh_pin/', $than_q ) === 1
	&& strpos( $than_q, "if ( '' !== self::pin_hop_le( \$p ) ) { continue; }" ) !== false );
teq( 'PIN sinh ra không trùng nhau', 2, count( array_unique( $pins ) ) );
/* ⚠️ Hồ sơ bỏ trống TÊN thì để trống, KHÔNG lấy MÃ làm tên — bản gốc từng làm vậy và màn hình
   chào "Xin chào, MNNV2MTD0026". */
$n2 = $wpdb->get_var( "SELECT ho_ten FROM " . VHCC_DB::t( 'phan_quyen' ) . " WHERE ma_cc_online='N2'" );
teq( 'tên trống thì để TRỐNG, không lấy mã làm tên', '', $n2 );
$r2 = VHCC_Quyen::cap_pin_hang_loat( $U_AD, array( 'N1' ) );
teq( 'cấp lại cho người đã có tài khoản: bỏ qua', 0, count( $r2['cap'] ) );
teq( 'cửa hàng trưởng không cấp PIN hàng loạt được', false,
	VHCC_Quyen::cap_pin_hang_loat( $U_CHT, array( 'N1' ) )['ok'] );

/* ---- TRA PIN THEO CCCD: cửa MỞ, nên ba bộ đếm là thứ duy nhất đứng giữa ---- */
vhcc_dung_bang();
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'T1', 'ho_ten' => 'Trần C',
	'cccd' => '079123456789', 'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '481937', 'ho_ten' => 'Trần C',
	'vai_tro' => 'NHAN_VIEN', 'ma_cc_online' => 'T1', 'coso_cc_online' => 'TUTU_BT' ) );
teq( 'che CCCD: chỉ để 3 số đầu và 3 số cuối', '079***789', VHCC_Quyen::che_cccd( '079123456789' ) );
teq( 'CCCD có dấu cách / gạch vẫn chuẩn hoá được', '079123456789',
	VHCC_Quyen::chuan_cccd( '079-123 456.789' ) );
t( 'CCCD quá ngắn: từ chối', empty( VHCC_Quyen::tra_pin_theo_cccd( '1234' )['ok'] ) );
$r = VHCC_Quyen::tra_pin_theo_cccd( '079123456789' );
t( 'tra được PIN', ! empty( $r['ok'] ) && '481937' === $r['pin'], isset( $r['error'] ) ? $r['error'] : '' );
teq( 'và trả cả cơ sở', 'TUTU_BT', $r['coSo'] );
/* Nhật ký ghi CCCD ĐÃ CHE và KHÔNG BAO GIỜ ghi PIN. */
$nk = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhat_ky_tra_pin' ) . ' ORDER BY id DESC' );
t( 'nhật ký ghi CCCD đã che', '079***789' === $nk[0]['cccd_che'] );
$co_pin = false;
foreach ( $nk as $x ) { foreach ( $x as $v ) { if ( false !== strpos( (string) $v, '481937' ) ) { $co_pin = true; } } }
t( 'nhật ký TUYỆT ĐỐI không chứa PIN', ! $co_pin );
/* Khớp TUYỆT ĐỐI, không khớp một phần — khớp một phần là gõ 4 số cũng ra người khác. */
t( 'khớp một phần KHÔNG ra kết quả', empty( VHCC_Quyen::tra_pin_theo_cccd( '079123456' )['ok'] ) );
/* Chặn theo TỪNG SỐ: 5 lượt / 10 phút. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhip_do' ) );
for ( $i = 0; $i < 5; $i++ ) { VHCC_Quyen::tra_pin_theo_cccd( '079123456789' ); }
$r = VHCC_Quyen::tra_pin_theo_cccd( '079123456789' );
t( 'quá 5 lượt cho CÙNG một số: chặn',
	empty( $r['ok'] ) && stripos( $r['error'], 'quá nhiều lần' ) !== false, $r['error'] );
/* ⚠️ Ngưỡng TOÀN HỆ THỐNG chỉ đếm lượt TRƯỢT. Đếm cả lượt đúng thì một buổi sáng đông người quên
   PIN là cả cửa hàng tự khoá nhau — đúng lúc cần tra nhất. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhip_do' ) );
for ( $i = 0; $i < 4; $i++ ) { VHCC_Quyen::tra_pin_theo_cccd( '079123456789' ); }
teq( 'lượt tra ĐÚNG không làm tăng bộ đếm toàn hệ thống', 0, VHCC_Quyen::doc_dem( 'trapin_hong' ) );
for ( $i = 0; $i < 3; $i++ ) { VHCC_Quyen::tra_pin_theo_cccd( '099' . $i . '00000000' ); }
t( 'lượt tra TRƯỢT thì có tăng', VHCC_Quyen::doc_dem( 'trapin_hong' ) > 0 );

/* ---- Gộp tài khoản: không đụng chấm công ---- */
vhcc_dung_bang();
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '111000', 'ma_cc_online' => 'G1' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '222000', 'ma_cc_online' => 'G1' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '333000', 'ma_cc_online' => 'G2' ) );
vhcc_cham( 'TUTU_BT', '2026-08-03', 'G1', '', '08:00:00', '17:00:00' );
teq( 'tìm được PIN trùng mã NV', 1, count( VHCC_Quyen::tim_pin_trung( $U_AD ) ) );
$r = VHCC_Quyen::gop_tai_khoan( $U_AD, '111000', '333000' );
t( 'gộp hai tài khoản KHÁC mã NV: từ chối (gộp là mất một người)',
	empty( $r['ok'] ) && stripos( $r['error'], 'khác nhau' ) !== false, $r['error'] );
t( 'gộp cùng mã NV: được', ! empty( VHCC_Quyen::gop_tai_khoan( $U_AD, '111000', '222000' )['ok'] ) );
teq( 'chấm công KHÔNG bị chạm khi gộp tài khoản', 1,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='G1'" ) );
$than_q2 = $than_q;
t( 'mã gộp tài khoản KHÔNG có lệnh nào ghi bảng chấm công',
	strpos( $than_q2, "cham_cong" ) === false || strpos( $than_q2, "UPDATE ' . VHCC_DB::t( 'cham_cong'" ) === false );

// ============================================================ 25. Chấm công: cờ · tăng cường · quy đổi
vhcc_dung_bang();
$than_c = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-cham.php' );
/* ⚠️ Lớp này CHỈ ĐỌC bảng chấm công. Mở đường sửa giờ ở đây là mở đường sửa lương bằng tay mà
   không có dấu vết — chỉ có hai đường được ghi giờ: cổng nhận từ máy và chấm công online. */
t( 'lớp Chấm KHÔNG có lệnh nào ghi/sửa giờ trong bảng chấm công',
	strpos( $than_c, "insert( VHCC_DB::t( 'cham_cong' )" ) === false
	&& strpos( $than_c, "update( VHCC_DB::t( 'cham_cong' )" ) === false
	&& strpos( $than_c, "DELETE FROM ' . VHCC_DB::t( 'cham_cong'" ) === false );

vhcc_cham( 'TUTU_BT', '2026-08-03', 'C1', '', '08:00:00', '17:00:00' );
vhcc_cham( 'TUTU_BT', '2026-08-04', 'C1', '', '08:10:00', null );        // quên check-out
vhcc_cham( 'POSH_HCM', '2026-08-03', 'C9', '', '08:00:00', '17:00:00' );
$b = VHCC_Cham::bang_cham_cong( $U_CHT, 'TUTU_BT', '2026-08' );
t( 'cửa hàng trưởng xem được bảng cơ sở mình', ! empty( $b['ok'] ) );
teq( 'đúng 2 hàng của tháng đó', 2, count( $b['hang'] ) );
teq( 'cửa hàng trưởng KHÔNG xem được cơ sở khác', false,
	VHCC_Cham::bang_cham_cong( $U_CHT, 'POSH_HCM', '2026-08' )['ok'] );
teq( 'nhân viên không xem được bảng', false, VHCC_Cham::bang_cham_cong( $U_NV, 'TUTU_BT', '2026-08' )['ok'] );

/* Cảnh báo thiếu giờ ra — chỉ CẢNH BÁO, không tự điền. */
$cb = VHCC_Cham::canh_bao_thieu_gio_ra( $U_CHT, 'TUTU_BT', '2026-08' );
teq( 'đúng 1 ngày quên check-out', 1, count( $cb ) );
teq( 'và là ngày 04', '2026-08-04', $cb[0]['ngay'] );
$h = vhcc_hang( 'TUTU_BT', '2026-08-04', 'C1' );
teq( 'KHÔNG tự điền giờ ra (điền là bịa giờ làm)', null, $h['gio_ra_giay'] );

/* Cờ cần kiểm — nằm CẠNH giờ, không đè lên giờ. */
$r = VHCC_Cham::luu_ghi_chu( $U_CHT, array( 'coso' => 'TUTU_BT', 'ngay' => '2026-08-04',
	'ma_nv' => 'C1', 'ghi_chu' => 'quên chấm ra, đã hỏi' ) );
t( 'gắn cờ được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$co = $r['flagId'];
teq( 'cờ rỗng bị từ chối', false, VHCC_Cham::luu_ghi_chu( $U_CHT,
	array( 'coso' => 'TUTU_BT', 'ngay' => '2026-08-04', 'ghi_chu' => '  ' ) )['ok'] );
teq( 'ngày sai khuôn bị từ chối', false, VHCC_Cham::luu_ghi_chu( $U_CHT,
	array( 'coso' => 'TUTU_BT', 'ngay' => 'mai', 'ghi_chu' => 'x' ) )['ok'] );
teq( 'cửa hàng trưởng không gắn cờ cơ sở khác', false, VHCC_Cham::luu_ghi_chu( $U_CHT,
	array( 'coso' => 'POSH_HCM', 'ngay' => '2026-08-03', 'ghi_chu' => 'x' ) )['ok'] );
teq( 'gắn cờ KHÔNG đụng giờ', null, vhcc_hang( 'TUTU_BT', '2026-08-04', 'C1' )['gio_ra_giay'] );
teq( 'bảng chấm công trả kèm cờ', 1, count( VHCC_Cham::bang_cham_cong( $U_CHT, 'TUTU_BT', '2026-08' )['co'] ) );
/* Xử lý cờ: GIỮ nội dung cũ, chỉ thêm kết luận. */
VHCC_Cham::xu_ly_ghi_chu( $U_CHT, $co, 'đã bổ sung tay' );
$g = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE flag_id=%s', $co ), ARRAY_A );
teq( 'trạng thái thành Đã xử lý', 'Đã xử lý', $g['trang_thai'] );
t( 'GIỮ nội dung cờ gốc (lý do gắn cờ là thứ duy nhất giải thích ngày công bất thường)',
	strpos( $g['ghi_chu'], 'quên chấm ra, đã hỏi' ) !== false, $g['ghi_chu'] );
t( 'và có thêm kết luận', strpos( $g['ghi_chu'], 'đã bổ sung tay' ) !== false );

/* ---- Tăng cường: chốt kỳ là không sửa được nữa ---- */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TC1', 'ho_ten' => 'Lê D', 'cua_hang' => 'POSH_HCM' ) );
$r = VHCC_Cham::them_tang_cuong( $U_CHT, array( 'coso_den' => 'TUTU_BT', 'ngay' => '2026-08-05',
	'ma_nv' => 'TC1' ) );
t( 'khai tăng cường được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$tc = VHCC_Cham::ds_tang_cuong( 'TUTU_BT', '2026-08' );
teq( 'và tự lấy cơ sở GỐC từ hồ sơ', 'POSH_HCM', $tc[0]['coso_goc'] );
teq( 'cửa hàng trưởng không khai vào cơ sở khác', false,
	VHCC_Cham::them_tang_cuong( $U_CHT, array( 'coso_den' => 'POSH_HCM', 'ngay' => '2026-08-05',
		'ma_nv' => 'TC1' ) )['ok'] );
teq( 'cửa hàng trưởng không chốt kỳ được', false,
	VHCC_Cham::khoa_tang_cuong( $U_CHT, 'TUTU_BT', '2026-08' )['ok'] );
t( 'Admin chốt kỳ được', ! empty( VHCC_Cham::khoa_tang_cuong( $U_AD, 'TUTU_BT', '2026-08' )['ok'] ) );
$r = VHCC_Cham::them_tang_cuong( $U_CHT, array( 'coso_den' => 'TUTU_BT', 'ngay' => '2026-08-05',
	'ma_nv' => 'TC1', 'ghi_chu' => 'sửa sau khi chốt' ) );
t( 'đã chốt kỳ thì KHÔNG sửa được nữa (sửa là số công đổi sau khi bảng lương đã in)',
	empty( $r['ok'] ) && stripos( $r['error'], 'CHỐT KỲ' ) !== false, $r['error'] );

/* ---- Quy đổi cơ sở: chặn chuỗi hai bước ---- */
t( 'Admin quy đổi được', ! empty( VHCC_Cham::luu_quy_doi_coso( $U_AD, 'TUTU BT', 'TUTU_BT' )['ok'] ) );
teq( 'cửa hàng trưởng không quy đổi được', false,
	VHCC_Cham::luu_quy_doi_coso( $U_CHT, 'X', 'Y' )['ok'] );
teq( 'quy đổi về chính nó: từ chối', false, VHCC_Cham::luu_quy_doi_coso( $U_AD, 'A', 'A' )['ok'] );
/* Bên đọc chỉ tra MỘT bước, nên chuỗi A->B->C là sai IM LẶNG. */
$r = VHCC_Cham::luu_quy_doi_coso( $U_AD, 'TUTU_CU', 'TUTU BT' );
t( 'chuỗi quy đổi hai bước: TỪ CHỐI và chỉ đường đi thẳng',
	empty( $r['ok'] ) && stripos( $r['error'], 'MỘT bước' ) !== false, $r['error'] );

/* ---- Thống kê đẩy + dọn: KHÔNG được xoá chấm công ---- */
$tk = VHCC_Cham::thong_ke_day( $U_AD, '2026-08' );
t( 'thống kê đếm theo cơ sở và nguồn', count( $tk ) >= 1 );
$truoc_cc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) );
$wpdb->insert( VHCC_DB::t( 'cho_gan' ), array( 'nhan_luc' => '2020-01-01 00:00:00',
	'serial' => 'S', 'ma_nv' => 'X', 'da_chuyen' => 'da-gan' ) );
$wpdb->insert( VHCC_DB::t( 'cho_gan' ), array( 'nhan_luc' => '2020-01-01 00:00:00',
	'serial' => 'S2', 'ma_nv' => 'Y', 'da_chuyen' => '' ) );
$r = VHCC_Cham::xoa_thong_ke_day( $U_AD, '2026-01-01' );
teq( 'chỉ dọn lượt chờ gán ĐÃ xử lý', 1, $r['so'] );
teq( 'lượt chờ gán CHƯA xử lý vẫn còn', 1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cho_gan' ) ) );
teq( '⚠️ bảng chấm công KHÔNG bị chạm', $truoc_cc,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );
teq( 'cửa hàng trưởng không dọn được', false, VHCC_Cham::xoa_thong_ke_day( $U_CHT, '2026-01-01' )['ok'] );
teq( 'ngày mốc sai khuôn: từ chối', false, VHCC_Cham::xoa_thong_ke_day( $U_AD, 'hôm qua' )['ok'] );

// ============================================================ 26. Yêu cầu nhân viên
vhcc_dung_bang();
$r = VHCC_YeuCau::gui( $U_CHT, array( 'coso' => 'TUTU_BT', 'loai' => 'Thêm người',
	'ma_nv' => 'M1', 'ho_ten' => 'Phạm E', 'noi_dung' => 'xin thêm 1 bạn thu tiền' ) );
t( 'cửa hàng trưởng gửi yêu cầu được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$yc = $r['maYc'];
teq( 'nhân viên không gửi yêu cầu nhân sự (họ dùng ô tự gửi thông tin)', false,
	VHCC_YeuCau::gui( $U_NV, array( 'coso' => 'TUTU_BT', 'noi_dung' => 'x' ) )['ok'] );
teq( 'yêu cầu rỗng bị từ chối', false,
	VHCC_YeuCau::gui( $U_CHT, array( 'coso' => 'TUTU_BT', 'noi_dung' => ' ' ) )['ok'] );
teq( 'cửa hàng trưởng thấy yêu cầu cơ sở mình', 1, VHCC_YeuCau::dem_cho( $U_CHT ) );
/* ⚠️ DUYỆT là cấp Mã NV cả chuỗi -> cửa hàng trưởng KHÔNG duyệt được yêu cầu của chính mình. Cho
   duyệt thì hai bậc quyền thành vô nghĩa: ai cũng tự cấp mã qua đường yêu cầu. */
$r = VHCC_YeuCau::duyet( $U_CHT, $yc, true );
t( 'cửa hàng trưởng KHÔNG duyệt được yêu cầu của chính mình',
	empty( $r['ok'] ) && stripos( $r['error'], 'Mã NV' ) !== false, $r['error'] );
/* ⚠️ Phải thử CẢ ca `tao_ho_so = false`. Với `true` thì chốt bên trong `luu_ho_so` cũng chặn và
   cũng nói "Mã NV", nên bỏ chốt quyền trong `duyet` đi mà phép thử trên vẫn xanh — phép thử đúng
   nhưng đúng vì lý do khác. Đã thử phá và nó lọt đúng chỗ này. */
$r_kh = VHCC_YeuCau::duyet( $U_CHT, $yc, false );
t( 'cửa hàng trưởng KHÔNG duyệt được dù không tạo hồ sơ', empty( $r_kh['ok'] ),
	isset( $r_kh['error'] ) ? $r_kh['error'] : 'lại cho duyệt!' );
$tt_yc = $wpdb->get_var( $wpdb->prepare( 'SELECT trang_thai FROM ' . VHCC_DB::t( 'yeu_cau_nv' )
	. ' WHERE ma_yc=%s', $yc ) );
teq( 'và yêu cầu vẫn ở trạng thái chờ', 'Chờ duyệt', $tt_yc );
$r = VHCC_YeuCau::duyet( $U_AD, $yc, true, array( 'chuc_vu' => 'Thu ngân' ) );
t( 'Admin duyệt và tạo luôn hồ sơ', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$hs = VHCC_NhanSu::ho_so( 'M1' );
t( 'hồ sơ được tạo thật', $hs && 'Phạm E' === $hs['ho_ten'] && 'Thu ngân' === $hs['chuc_vu'] );
teq( 'và cơ sở lấy từ yêu cầu', 'TUTU_BT', $hs['cua_hang'] );
t( 'duyệt lại lần hai: bị chặn', empty( VHCC_YeuCau::duyet( $U_AD, $yc, true )['ok'] ) );
/* Hồ sơ tạo TRƯỢT thì KHÔNG đánh dấu đã duyệt — không thì yêu cầu hiện "Đã duyệt" mà không có
   hồ sơ nào, và không ai biết phải làm lại. */
$r2 = VHCC_YeuCau::gui( $U_CHT, array( 'coso' => 'TUTU_BT', 'ma_nv' => '', 'noi_dung' => 'thiếu mã' ) );
$r3 = VHCC_YeuCau::duyet( $U_AD, $r2['maYc'], true );
t( 'tạo hồ sơ trượt thì duyệt cũng trượt', empty( $r3['ok'] ) );
$tt = $wpdb->get_var( $wpdb->prepare( 'SELECT trang_thai FROM ' . VHCC_DB::t( 'yeu_cau_nv' )
	. ' WHERE ma_yc=%s', $r2['maYc'] ) );
teq( 'và yêu cầu VẪN ở trạng thái chờ, không hiện "Đã duyệt" giả', 'Chờ duyệt', $tt );
/* Từ chối PHẢI có lý do — không thì người gửi gửi lại y như cũ. */
t( 'từ chối không lý do: bị chặn', empty( VHCC_YeuCau::tu_choi( $U_AD, $r2['maYc'], '' )['ok'] ) );
t( 'từ chối có lý do: được', ! empty( VHCC_YeuCau::tu_choi( $U_AD, $r2['maYc'], 'thiếu mã NV' )['ok'] ) );

/* Ô "tự gửi thông tin" — cửa MỞ, nên phải có nhịp độ và KHÔNG được tạo hồ sơ. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhip_do' ) );
$so_hs = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) );
$r = VHCC_YeuCau::gui_thong_tin_nv( array( 'ho_ten' => 'Người mới', 'sdt' => '0900111222',
	'coso' => 'TUTU_BT', 'cccd' => '079999888777' ) );
t( 'người chưa có hồ sơ tự gửi được (không cần đăng nhập)', ! empty( $r['ok'] ),
	isset( $r['error'] ) ? $r['error'] : '' );
teq( '⚠️ KHÔNG tạo hồ sơ nào', $so_hs,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) ) );
teq( 'thiếu SĐT: từ chối', false,
	VHCC_YeuCau::gui_thong_tin_nv( array( 'ho_ten' => 'X', 'coso' => 'TUTU_BT' ) )['ok'] );
teq( 'thiếu cơ sở: từ chối', false,
	VHCC_YeuCau::gui_thong_tin_nv( array( 'ho_ten' => 'X', 'sdt' => '0900' ) )['ok'] );
for ( $i = 0; $i < 3; $i++ ) {
	VHCC_YeuCau::gui_thong_tin_nv( array( 'ho_ten' => 'Người mới', 'sdt' => '0900111222',
		'coso' => 'TUTU_BT' ) );
}
$r = VHCC_YeuCau::gui_thong_tin_nv( array( 'ho_ten' => 'Người mới', 'sdt' => '0900111222',
	'coso' => 'TUTU_BT' ) );
t( 'gửi lặp quá nhiều lần: chặn theo SỐ ĐIỆN THOẠI (không chặn cả cơ sở)',
	empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
t( 'số khác vẫn gửi được', ! empty( VHCC_YeuCau::gui_thong_tin_nv(
	array( 'ho_ten' => 'Ai khác', 'sdt' => '0977000111', 'coso' => 'TUTU_BT' ) )['ok'] ) );
/* Yêu cầu KHÔNG có cơ sở thì chỉ Admin/Quản lý thấy — không gán bừa cho cửa hàng nào. */
$wpdb->insert( VHCC_DB::t( 'yeu_cau_nv' ), array( 'ma_yc' => 'YCX', 'coso' => '',
	'trang_thai' => 'Chờ duyệt', 'noi_dung' => 'không rõ cơ sở' ) );
$ma_cht = array();
foreach ( VHCC_YeuCau::ds( $U_CHT, true ) as $x ) { $ma_cht[] = $x['ma_yc']; }
t( 'cửa hàng trưởng KHÔNG thấy yêu cầu không rõ cơ sở', ! in_array( 'YCX', $ma_cht, true ) );
$ma_ad = array();
foreach ( VHCC_YeuCau::ds( $U_AD, true ) as $x ) { $ma_ad[] = $x['ma_yc']; }
t( 'Admin thấy', in_array( 'YCX', $ma_ad, true ) );

// ============================================================ 27. Đặt cấu hình lương + bảng đối chiếu
vhcc_dung_bang();
$U_KT = array( 'name' => 'KeToan', 'role' => 'KE_TOAN', 'coso' => '' );
teq( 'đơn giá 0 bị từ chối (mọi ô tiền cả cơ sở thành 0 mà bảng vẫn có số)', false,
	VHCC_Luong::dat_don_gia_gio( $U_AD, array( 'congThuong' => '0' ) )['ok'] );
teq( 'đơn giá âm bị từ chối', false,
	VHCC_Luong::dat_don_gia_gio( $U_AD, array( 'congThuong' => '-5000' ) )['ok'] );
teq( 'cửa hàng trưởng không đặt đơn giá', false,
	VHCC_Luong::dat_don_gia_gio( $U_CHT, array( 'congThuong' => '200000' ) )['ok'] );
t( 'kế toán đặt được', ! empty( VHCC_Luong::dat_don_gia_gio( $U_KT,
	array( 'congThuong' => '200.000', 'gioThuong' => '30000' ) )['ok'] ) );
$g = VHCC_Luong::mtd_gia();
teq( 'đơn giá gõ kiểu Việt vẫn đúng', 200000.0, $g['congThuong'] );
teq( 'và ô không khai thì giữ 0', 0, $g['congLe'] );
/* Đặt tiếp một ô khác thì KHÔNG xoá ô đã khai. */
VHCC_Luong::dat_don_gia_gio( $U_AD, array( 'congLe' => '400000' ) );
$g2 = VHCC_Luong::mtd_gia();
t( 'khai thêm ô mới không xoá ô cũ', 200000.0 === $g2['congThuong'] && 400000.0 === $g2['congLe'] );

/* Số ngày công: theo ĐÚNG cặp (cơ sở, tháng) */
teq( 'số ngày công 0 bị từ chối', false, VHCC_Luong::dat_ngay_cong( $U_AD, 'VP_HCM', '2026-09', '0' )['ok'] );
teq( 'quá 31 bị từ chối', false, VHCC_Luong::dat_ngay_cong( $U_AD, 'VP_HCM', '2026-09', '40' )['ok'] );
t( 'đặt được', ! empty( VHCC_Luong::dat_ngay_cong( $U_AD, 'VP_HCM', '2026-09', '26' )['ok'] ) );
teq( 'và chỉ áp cho ĐÚNG tháng đó', 26.0, VHCC_Luong::vp_nc_lay( 'VP_HCM', '2026-09' ) );
teq( 'tháng khác vẫn CHƯA khai, không mượn số', 0, VHCC_Luong::vp_nc_lay( 'VP_HCM', '2026-10' ) );
teq( 'cơ sở khác cũng vậy', 0, VHCC_Luong::vp_nc_lay( 'VP_SG', '2026-09' ) );

/* ---- BẢNG ĐỐI CHIẾU cách tính: chỉ đọc, và hai bên dùng CÙNG một lần đọc dữ liệu ---- */
vhcc_bo_phan( 'VP_HCM', 'Văn phòng' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'V1', 'luong_co_ban' => 13000000 ) );
/* Số ngày công của (VP_HCM, 2026-09) đã đặt ở mấy dòng trên rồi — ghi thêm một lần nữa là hàng
   THỨ HAI cho cùng một cặp. Bảng có `UNIQUE KEY (coso,thang)` nên chuyện đó không được phép, và
   trước đây bài kiểm không thấy vì bản dựng bảng cũ vứt mất mọi UNIQUE. Chốt ấy khôi phục xong
   là bắt ngay chỗ này. */
for ( $i = 1; $i <= 5; $i++ ) {
	vhcc_cham( 'VP_HCM', '2026-09-0' . $i, 'V1', '', '08:30:00', '13:00:00' );   // 4.5 giờ/ngày
}
$ds = VHCC_Luong::so_sanh_cach_tinh( $U_AD, 'VP_HCM', '2026-09', array( 'duoiMin' => 'bacthang' ) );
t( 'xem được bảng đối chiếu', ! empty( $ds['ok'] ), isset( $ds['error'] ) ? $ds['error'] : '' );
/* 4.5 giờ: 'tyle' cho 4.5/8 = 0.56 công/ngày; 'bacthang' cho 1 công/ngày (>=4h, <9h). Chênh phải
   DƯƠNG và thấy được TRƯỚC khi lưu. */
t( 'bảng đối chiếu chỉ ra chênh công', $ds['chenhCong'] > 0, $ds['chenhCong'] );
t( 'và chênh tiền', $ds['chenhTien'] > 0, $ds['chenhTien'] );
teq( 'có dòng cho từng người', 1, count( $ds['dong'] ) );
/* ⚠️ CHỈ ĐỌC: xem bảng KHÔNG đổi cấu hình đang lưu. */
teq( 'xem bảng đối chiếu KHÔNG đổi cấu hình đang lưu', 'tyle', VHCC_Luong::vp_cfg()['duoiMin'] );
teq( 'cửa hàng trưởng không xem được bảng đối chiếu', false,
	VHCC_Luong::so_sanh_cach_tinh( $U_CHT, 'VP_HCM', '2026-09', array() )['ok'] );
teq( 'cơ sở không phải Văn phòng: từ chối', false,
	VHCC_Luong::so_sanh_cach_tinh( $U_AD, 'TUTU_BT', '2026-09', array() )['ok'] );
/* `caChuan` là con số phải hiện TRƯỚC khi bấm Lưu. */
$cc = VHCC_Luong::ca_chuan( array_merge( VHCC_Luong::vp_cfg(), array( 'duoiMin' => 'bacthang' ) ) );
teq( 'ca chuẩn 08:30-17:00 là 8.5 tiếng', 8.5, $cc['gio'] );
teq( 'và với mốc bậcMột = 9 thì ra 1 công', 1.0, $cc['cong'] );
$cc8 = VHCC_Luong::ca_chuan( array_merge( VHCC_Luong::vp_cfg(),
	array( 'duoiMin' => 'bacthang', 'bacMot' => 8 ) ) );
teq( 'đổi mốc thành 8 thì chính ca chuẩn thành 1.5 công — đây là chỗ tăng 50% cả cơ sở', 1.5, $cc8['cong'] );

/* Đặt cấu hình VP: kiểm giá trị, và trả kèm bảng đối chiếu để thấy trước khi lưu */
teq( 'cách tính lạ bị từ chối', false,
	VHCC_Luong::dat_vp_cfg( $U_AD, array( 'duoiMin' => 'tuỳ ý' ) )['ok'] );
teq( 'giờ sai khuôn bị từ chối', false,
	VHCC_Luong::dat_vp_cfg( $U_AD, array( 'ngayDen' => '17 giờ' ) )['ok'] );
$r = VHCC_Luong::dat_vp_cfg( $U_AD, array( 'duoiMin' => 'bacthang' ), 'VP_HCM', '2026-09' );
t( 'đặt được và TRẢ KÈM bảng đối chiếu', ! empty( $r['ok'] ) && ! empty( $r['doiChieu']['ok'] ) );
teq( 'sau khi lưu thì cấu hình đã đổi', 'bacthang', VHCC_Luong::vp_cfg()['duoiMin'] );
teq( 'cửa hàng trưởng không đặt cấu hình công', false,
	VHCC_Luong::dat_vp_cfg( $U_CHT, array( 'duoiMin' => 'tron' ) )['ok'] );

/* ---- Báo cáo theo GIỜ (engine thứ ba) ---- */
vhcc_dung_bang();
vhcc_cai_dat( 'CA_LAM', array( 'start' => '08:00', 'end' => '17:00',
	'startW' => '09:00', 'endW' => '15:00' ) );
vhcc_cai_dat( 'WAGE_MAP', array( 'TUTU_BT' => array( '*' => 30000 ) ) );
vhcc_cham( 'TUTU_BT', '2026-09-01', 'W1', '', '07:00:00', '18:00:00' );   // thứ Ba, khung 08-17
vhcc_cham( 'TUTU_BT', '2026-09-05', 'W1', '', '07:00:00', '18:00:00' );   // thứ Bảy, khung 09-15
$r = VHCC_Luong::bao_cao_theo_gio( $U_AD, 'TUTU_BT', '2026-09' );
t( 'báo cáo giờ chạy được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
/* Chỉ tính phần GIAO với khung ca: ngày thường 9 giờ, cuối tuần 6 giờ -> 15 giờ. */
teq( 'chỉ tính phần giao với khung ca, và khung cuối tuần KHÁC ngày thường', 15.0, $r['rows'][0]['gio'] );
teq( 'tiền = 15 × 30000', 450000, $r['rows'][0]['tien'] );
teq( 'cửa hàng trưởng không xem báo cáo giờ', false,
	VHCC_Luong::bao_cao_theo_gio( $U_CHT, 'TUTU_BT', '2026-09' )['ok'] );
$r2 = VHCC_Luong::bao_cao_theo_gio( $U_AD, 'CHUA_KHAI_GIA', '2026-09' );
t( 'cơ sở chưa khai đơn giá: có cờ báo', ! empty( $r2['chuaKhaiGiaCoSo'] ) );

// ============================================================ 28. Cấu hình lịch
vhcc_dung_bang();
$c = VHCC_Lich::cau_hinh( $U_AD );
t( 'có danh sách ca mặc định', count( $c['ca'] ) >= 1 );
teq( 'cửa hàng trưởng không bật/tắt lịch theo cơ sở', false,
	VHCC_Lich::dat_coso_bat_lich( $U_CHT, array( 'TUTU_BT' ) )['ok'] );
t( 'Admin bật được', ! empty( VHCC_Lich::dat_coso_bat_lich( $U_AD, array( 'CS_TUTU_BT', 'TUTU_BT', '' ) )['ok'] ) );
teq( 'bỏ tiền tố CS_ và bỏ trùng', array( 'TUTU_BT' ), VHCC_Lich::cau_hinh( $U_AD )['coSoBatLich'] );
/* ⚠️ Tắt lịch KHÔNG xoá ô lịch đã xếp. */
VHCC_Lich::xep_lich( $U_CHT, 'TUTU_BT', array( array( 'ngay' => '2026-09-01', 'ma_nv' => 'L1',
	'ca' => 'Sáng', 'viec' => 'A' ) ) );
VHCC_Lich::dat_coso_bat_lich( $U_AD, array() );
teq( 'tắt lịch KHÔNG xoá ô lịch đã xếp', 1,
	count( VHCC_Lich::ds_lich( 'TUTU_BT', '2026-09-01', '2026-09-30' ) ) );
/* ⚠️ Đổi tên ca thì ô cũ giữ tên cũ — phải BÁO RA số ô mồ côi, không để im. */
/* Danh sách ca dùng CHUNG mọi cơ sở -> Quản lý trở lên. Cửa hàng trưởng xếp lịch cửa hàng
   mình được, nhưng không đặt lại tên ca cho cả chuỗi. */
$r = VHCC_Lich::dat_ca( $U_CHT, array( 'Ca 1', 'Ca 2' ) );
t( 'Cửa hàng trưởng KHÔNG đổi được danh sách ca (dùng chung cả chuỗi)', empty( $r['ok'] ), $r );
$r = VHCC_Lich::dat_ca( $U_QL, array( 'Ca 1', 'Ca 2' ) );
t( 'Quản lý đổi danh sách ca được', ! empty( $r['ok'] ), $r );
teq( 'và BÁO RA ô lịch đang dùng tên ca vừa bị bỏ', 1, $r['oMoCoi']['Sáng'] );
teq( 'danh sách ca rỗng bị từ chối', false, VHCC_Lich::dat_ca( $U_AD, array() )['ok'] );
teq( 'nhân viên không sửa được ca', false, VHCC_Lich::dat_ca( $U_NV, array( 'X' ) )['ok'] );
t( 'Cửa hàng trưởng KHÔNG đặt được loại việc (dùng chung cả chuỗi)',
	empty( VHCC_Lich::dat_loai_viec( $U_CHT, array( 'X' ) )['ok'] ) );
t( 'đặt loại việc được', ! empty( VHCC_Lich::dat_loai_viec( $U_QL, array( 'Thu tiền', 'Vệ sinh', 'Thu tiền' ) )['ok'] ) );
teq( 'loại việc bỏ trùng', 2, count( VHCC_Lich::cau_hinh( $U_AD )['loaiViec'] ) );

// ============================================================ 29. Chấm công online: phần còn lại
vhcc_dung_bang();
$g = VHCC_Online::gio_may_chu();
t( 'giờ máy chủ có ngày và giờ', ! empty( $g['ngay'] ) && ! empty( $g['gio'] ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'O1', 'ho_ten' => 'Ngô F',
	'cua_hang' => 'TUTU_BT', 'coso_phu' => 'POSH_HCM', 'nhiem_vu' => 'Trực Ghế' ) );
$tt = VHCC_Online::thong_tin( array( 'ma_nv' => 'O1', 'ho_ten' => 'Ngô F', 'coso' => 'TUTU_BT' ) );
t( 'bật chấm công online', ! empty( $tt['bat'] ) );
teq( 'trả đủ cơ sở chính + phụ', 2, count( $tt['dsCoSo'] ) );
teq( 'và nhiệm vụ được khai', array( 'Trực Ghế' ), $tt['dsNhiemVu'] );
t( 'kèm giờ máy chủ để trang hiện đồng hồ đúng', ! empty( $tt['gio']['gio'] ) );
$tt2 = VHCC_Online::thong_tin( array( 'ma_nv' => '', 'ho_ten' => 'X', 'coso' => '' ) );
t( 'tài khoản chưa bật thì nói rõ', empty( $tt2['bat'] ) );
/* Ảnh mẫu thẻ: chưa khai thì trả ok:false để trang tự dùng hình vẽ sẵn, KHÔNG trả ảnh rỗng. */
t( 'chưa khai ảnh mẫu: ok:false', empty( VHCC_Online::anh_mau_the()['ok'] ) );
t( 'ảnh không phải data:image bị từ chối',
	empty( VHCC_Online::dat_anh_mau_the( $U_AD, 'http://x/y.jpg' )['ok'] ) );
$r = VHCC_Online::dat_anh_mau_the( $U_AD, 'data:image/jpeg;base64,' . str_repeat( 'A', 300 ) );
t( 'đặt ảnh mẫu được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
t( 'đọc lại được', ! empty( VHCC_Online::anh_mau_the()['ok'] ) );
t( 'và info nói đã khai', ! empty( VHCC_Online::anh_mau_the_info()['daKhai'] ) );
$r = VHCC_Online::dat_anh_mau_the( $U_AD, 'data:image/jpeg;base64,' . str_repeat( 'A', 250000 ) );
t( 'ảnh quá lớn bị từ chối (nó tải kèm MỌI lượt mở trang chấm công)',
	empty( $r['ok'] ) && stripos( $r['error'], 'quá lớn' ) !== false, $r['error'] );
teq( 'cửa hàng trưởng không đặt ảnh mẫu', false,
	VHCC_Online::dat_anh_mau_the( $U_CHT, 'data:image/png;base64,' . str_repeat( 'A', 300 ) )['ok'] );

// ============================================================ 30. Nhân sự: đổi mã · nghỉ việc · nhập loạt
vhcc_dung_bang();
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'D1', 'ho_ten' => 'Đỗ G', 'cua_hang' => 'TUTU_BT' ) );
vhcc_cham( 'TUTU_BT', '2026-08-03', 'D1', '', '08:00:00', '17:00:00' );
vhcc_cham( 'TUTU_BT', '2026-08-04', 'D1', '', '08:00:00', '17:00:00' );
$wpdb->insert( VHCC_DB::t( 'lich_cv' ), array( 'coso' => 'TUTU_BT', 'ngay' => '2026-08-05',
	'ma_nv' => 'D1', 'ca' => 'Sáng' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '481937', 'ma_cc_online' => 'D1' ) );

/* ---- XEM TRƯỚC là bắt buộc: đổi mã là sửa MỌI hàng chấm công, đổi rồi thì không có đường lùi ---- */
$xt = VHCC_NhanSu::xem_truoc_doi_ma( $U_AD, 'D1', 'D2' );
t( 'xem trước chạy được', ! empty( $xt['ok'] ), isset( $xt['error'] ) ? $xt['error'] : '' );
teq( 'đếm đúng số hàng chấm công sẽ bị sửa', 2, $xt['soHangChamCong'] );
teq( 'đếm đúng số ô lịch', 1, $xt['soOLich'] );
teq( 'đếm đúng dòng phân quyền', 1, $xt['soDongPhanQuyen'] );
teq( 'và liệt kê cơ sở liên quan', array( 'TUTU_BT' ), $xt['coSoLienQuan'] );
/* Xem trước là CHỈ ĐỌC — không được sửa gì. */
teq( 'xem trước KHÔNG sửa hàng nào', 2,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='D1'" ) );
/* Mã mới ĐÃ có hồ sơ khác dùng -> chặn, vì đổi vào là GỘP CÔNG HAI NGƯỜI. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'DX', 'ho_ten' => 'Người khác' ) );
$r = VHCC_NhanSu::xem_truoc_doi_ma( $U_AD, 'D1', 'DX' );
t( 'mã mới đã có người dùng: CHẶN (đổi vào là gộp công hai người)',
	empty( $r['ok'] ) && stripos( $r['error'], 'gộp công' ) !== false, $r['error'] );
/* Mã đang khai chạy song song -> cảnh báo trước, đừng để phát hiện sau. */
$wpdb->insert( VHCC_DB::t( 'ma_song_song' ), array( 'ma_a' => 'D1', 'ma_b' => 'DCU' ) );
$xt2 = VHCC_NhanSu::xem_truoc_doi_ma( $U_AD, 'D1', 'D2' );
t( 'cảnh báo mã đang chạy song song', '' !== $xt2['canhBao'], $xt2['canhBao'] );

/* ---- ĐỔI MÃ ---- */
teq( 'cửa hàng trưởng KHÔNG đổi được mã', false, VHCC_NhanSu::doi_ma_nv( $U_CHT, 'D1', 'D2' )['ok'] );
/* ⚠️ Phải thử qua CHÍNH `doi_ma_nv`, không chỉ qua `xem_truoc_doi_ma`: bỏ chốt "xem trước trượt
   thì dừng" trong doi_ma_nv thì phép thử xem-trước vẫn xanh mà hàm thật vẫn đổi bừa. Đã thử phá
   và nó lọt đúng chỗ này. */
$truoc_dx = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='D1'" );
$r_dx = VHCC_NhanSu::doi_ma_nv( $U_AD, 'D1', 'DX' );
t( 'doi_ma_nv TỪ CHỐI khi mã mới đã có hồ sơ khác dùng',
	empty( $r_dx['ok'] ) && stripos( $r_dx['error'], 'gộp công' ) !== false,
	isset( $r_dx['error'] ) ? $r_dx['error'] : 'lại cho đổi!' );
teq( 'và KHÔNG hàng nào bị đổi sang mã đó', 0,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='DX'" ) );
teq( 'hàng cũ còn nguyên', $truoc_dx,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='D1'" ) );
$r = VHCC_NhanSu::doi_ma_nv( $U_AD, 'D1', 'D2' );
t( 'Admin đổi được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'MỌI hàng chấm công đã đổi sang mã mới', 2,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='D2'" ) );
teq( 'không còn hàng nào mang mã cũ', 0,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='D1'" ) );
teq( 'ô lịch cũng đổi', 1,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'lich_cv' ) . " WHERE ma_nv='D2'" ) );
teq( 'dòng phân quyền cũng đổi', 'D2',
	$wpdb->get_var( "SELECT ma_cc_online FROM " . VHCC_DB::t( 'phan_quyen' ) . " WHERE pin='481937'" ) );
teq( 'hồ sơ cũng đổi', 'Đỗ G', VHCC_NhanSu::ho_so( 'D2' )['ho_ten'] );
/* ⚠️ Phải NÓI RÕ là chỉ đổi trên web — người trên MÁY vẫn mang mã cũ. Không nói thì người dùng
   tưởng máy cũng đã đổi rồi chấm công vào mã cũ cả tháng. */
t( 'nói rõ máy chấm công VẪN mang mã cũ',
	stripos( $r['canhBao'], 'MÁY' ) !== false, $r['canhBao'] );
/* Người có mặt ở cơ sở NGOÀI quyền -> chỉ Admin. */
vhcc_cham( 'POSH_HCM', '2026-08-03', 'D2', '', '08:00:00', '17:00:00' );
$U_QL_BT = array( 'name' => 'QL', 'role' => 'QUAN_LY', 'coso' => 'TUTU_BT' );
t( 'Quản lý (không phải Admin) vẫn đổi được vì Quản lý có quyền mọi cơ sở',
	! empty( VHCC_NhanSu::doi_ma_nv( $U_QL_BT, 'D2', 'D3' )['ok'] ) );

/* ---- CHO NGHỈ VIỆC: đường ĐÚNG thay cho xoá ---- */
/* Cho nghỉ việc là việc HỒ SƠ -> Kế toán trở lên (mô hình 25/08/2026). Cửa hàng trưởng báo
   lên, không tự cho nghỉ: một dòng "Đã nghỉ" gõ nhầm là người đó mất chỗ trong bảng lương. */
$r = VHCC_NhanSu::dat_nghi_viec( $U_CHT, 'D3', '2026-08-31', 'chuyển chỗ khác' );
t( 'cửa hàng trưởng KHÔNG cho nghỉ được', empty( $r['ok'] ), $r );
$r = VHCC_NhanSu::dat_nghi_viec( $U_KT, 'D3', '2026-08-31', 'chuyển chỗ khác' );
t( 'Kế toán cho nghỉ được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$hs = VHCC_NhanSu::ho_so( 'D3' );
t( 'trạng thái ghi rõ ngày và lý do',
	stripos( $hs['trang_thai_lam_viec'], 'Đã nghỉ' ) !== false
	&& stripos( $hs['trang_thai_lam_viec'], '2026-08-31' ) !== false );
teq( '⚠️ chấm công GIỮ NGUYÊN (bảng lương tháng cũ vẫn tra ra tên)', 3,
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='D3'" ) );
t( 'hồ sơ vẫn còn', null !== VHCC_NhanSu::ho_so( 'D3' ) );

/* ---- XOÁ NHIỀU: từng cái đi qua đúng chốt của xoa_ho_so ---- */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'Z1', 'cua_hang' => 'TUTU_BT' ) );
$r = VHCC_NhanSu::xoa_nhieu_ho_so( $U_AD, array( 'Z1', 'D3' ) );
teq( 'xoá được người chưa có chấm công', array( 'Z1' ), $r['xong'] );
teq( 'và bỏ người còn chấm công, kèm lý do', 1, count( $r['bo'] ) );
t( 'lý do nói rõ còn lượt chấm công', stripos( $r['bo'][0], 'chấm công' ) !== false, $r['bo'][0] );

/* ---- NHẬP HÀNG LOẠT: xem trước bắt trùng mã TRONG CHÍNH tệp ---- */
vhcc_dung_bang();
$tep = array(
	array( 'ma_nv' => 'B1', 'ho_ten' => 'Một', 'cua_hang' => 'TUTU_BT' ),
	array( 'ma_nv' => 'B2', 'ho_ten' => 'Hai', 'cua_hang' => 'TUTU_BT' ),
	array( 'ma_nv' => 'B1', 'ho_ten' => 'Một lần nữa', 'cua_hang' => 'TUTU_BT' ),
	array( 'ma_nv' => '',   'ho_ten' => 'Không mã' ),
);
$xt = VHCC_NhanSu::xem_truoc_nhap( $U_AD, $tep );
teq( 'xem trước: 2 thêm', 2, $xt['dem']['them'] );
teq( 'và 2 bỏ (trùng mã trong tệp + thiếu mã)', 2, $xt['dem']['bo'] );
$ly_do = '';
foreach ( $xt['dong'] as $d ) { if ( 3 === $d['dong'] ) { $ly_do = $d['vaoSao']; } }
t( 'nói rõ trùng với DÒNG NÀO trong cùng tệp', stripos( $ly_do, 'dòng 1' ) !== false, $ly_do );
teq( 'xem trước KHÔNG ghi hồ sơ nào', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) ) );
/* Xác nhận lệch số -> chặn, vì tệp đã đổi giữa hai bước. */
$r = VHCC_NhanSu::nhap_hang_loat( $U_AD, $tep, 5 );
t( 'số xác nhận lệch: CHẶN (tệp đã đổi giữa hai bước)',
	empty( $r['ok'] ) && stripos( $r['error'], 'đã đổi' ) !== false, $r['error'] );
$r = VHCC_NhanSu::nhap_hang_loat( $U_AD, $tep, 2 );
teq( 'nhập đúng 2 dòng', 2, count( $r['xong'] ) );
teq( 'và bỏ 2 dòng kèm lý do', 2, count( $r['bo'] ) );
teq( 'chỉ có 2 hồ sơ được tạo', 2,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) ) );
teq( 'cửa hàng trưởng không nhập hàng loạt', false,
	VHCC_NhanSu::nhap_hang_loat( $U_CHT, $tep )['ok'] );

/* ---- Nhiệm vụ: chỉ có nghĩa ở Nhóm Máy Tự Động ---- */
$r = VHCC_NhanSu::dat_nhiem_vu( $U_CHT, '2026-08-03', 'TUTU_BT', 'B1', 'Trực Ghế' );
t( 'cơ sở KHÔNG thuộc Nhóm Máy Tự Động: từ chối và nói rõ vì sao',
	empty( $r['ok'] ) && stripos( $r['error'], 'Máy Tự Động' ) !== false, $r['error'] );
$U_CHT_P = array( 'name' => 'CHT_P', 'role' => 'CUA_HANG_TRUONG', 'coso' => 'POSH_HCM' );
t( 'cơ sở POSH thì đặt được',
	! empty( VHCC_NhanSu::dat_nhiem_vu( $U_CHT_P, '2026-08-03', 'POSH_HCM', 'B1', 'Trực Ghế' )['ok'] ) );
VHCC_NhanSu::dat_nhiem_vu( $U_CHT_P, '2026-08-03', 'POSH_HCM', 'B1', 'Thu Tiền' );
teq( 'đặt lại cùng (ngày, cơ sở, mã): GHI ĐÈ, không thêm dòng thứ hai', 1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong_nhiem_vu' ) ) );

/* ---- Mã đã chấm công mà chưa có hồ sơ ---- */
vhcc_cham( 'TUTU_BT', '2026-08-10', 'KHONG_HO_SO', '', '08:00:00', '17:00:00' );
$ds = VHCC_NhanSu::ds_chua_co_ho_so( $U_AD );
$ma = array();
foreach ( $ds as $x ) { $ma[] = $x['ma_nv']; }
t( 'tìm ra mã đã chấm công mà chưa có hồ sơ', in_array( 'KHONG_HO_SO', $ma, true ), implode( ',', $ma ) );
t( 'và KHÔNG kể mã đã có hồ sơ', ! in_array( 'B1', $ma, true ) );

/* ---- Mã song song: bỏ cặp ---- */
VHCC_NhanSu::khai_ma_song_song( $U_AD, 'S1', 'S2', 'X', '' );
teq( 'liệt kê được', 1, count( VHCC_NhanSu::ds_ma_song_song() ) );
teq( 'cửa hàng trưởng không bỏ được', false, VHCC_NhanSu::bo_ma_song_song( $U_CHT, 'S1', 'S2' )['ok'] );
t( 'bỏ được kể cả khi gõ đảo thứ tự hai mã',
	! empty( VHCC_NhanSu::bo_ma_song_song( $U_AD, 'S2', 'S1' )['ok'] ) );
teq( 'đã bỏ thật', 0, count( VHCC_NhanSu::ds_ma_song_song() ) );
t( 'bỏ cặp không tồn tại: báo không thấy',
	empty( VHCC_NhanSu::bo_ma_song_song( $U_AD, 'KHONG', 'CO' )['ok'] ) );

/* ---- Bộ phận + nhóm của mọi cơ sở, một bảng ---- */
vhcc_bo_phan( 'VP_X', 'Văn phòng' );
$bp = VHCC_NhanSu::bo_phan_va_coso();
$thay = null;
foreach ( $bp as $x ) { if ( 'VP_X' === $x['coSo'] ) { $thay = $x; } }
t( 'bảng bộ phận nhận ra Văn phòng', $thay && true === $thay['laVanPhong'] );

// ============================================================ 31. Màn hình: mỏng, và không sót hàm
$ad_all = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' )
	. file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-man.php' );

/* ---- 31a. Mọi màn đều MỎNG ---- */
$man = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-man.php' );
t( 'tệp màn bổ sung KHÔNG ghi thẳng vào bảng',
	strpos( $man, '$wpdb->insert' ) === false && strpos( $man, '$wpdb->update' ) === false
	&& strpos( $man, '$wpdb->delete' ) === false && strpos( $man, '$wpdb->query' ) === false );
/* Luật quyền chỉ được ở lớp nghiệp vụ. Màn so chuỗi vai trò là bản luật thứ hai. */
t( 'tệp màn bổ sung KHÔNG tự viết luật quyền',
	strpos( $man, "'CUA_HANG_TRUONG'" ) === false && strpos( $man, "'QUAN_LY'" ) === false
	&& strpos( $man, "'KE_TOAN'" ) === false );
t( 'tệp màn bổ sung KHÔNG gọi Firebase', stripos( $man, 'firebase' ) === false );
/* Mỗi màn phải gác quyền và có nonce. */
/* ⚠️ Phải cắt ĐÚNG thân từng hàm, không lấy một đoạn dài cố định: đoạn 20.000 ký tự ăn sang cả
   hàm KẾ TIẾP, nên bỏ nonce của một màn mà phép thử vẫn xanh vì thấy nonce của màn sau. Đã thử phá
   và nó lọt đúng chỗ này. Cắt tới chỗ bắt đầu hàm public tiếp theo (hoặc hết tệp). */
$moc_ham = array();
foreach ( array( 'trang_quyen', 'trang_cham', 'trang_yeu_cau', 'trang_cf_luong' ) as $x ) {
	$moc_ham[ $x ] = strpos( $man, 'public static function ' . $x . '()' );
}
foreach ( array( 'trang_quyen', 'trang_cham', 'trang_yeu_cau', 'trang_cf_luong' ) as $ten ) {
	$i = $moc_ham[ $ten ];
	t( "$ten có mặt", false !== $i );
	$het = strlen( $man );
	foreach ( $moc_ham as $j ) { if ( false !== $j && $j > $i && $j < $het ) { $het = $j; } }
	$than = false === $i ? '' : substr( $man, $i, $het - $i );
	t( "$ten gác quyền ngay đầu hàm",
		strpos( substr( $than, 0, 300 ), 'current_user_can( VHCC_Admin::CAP )' ) !== false );
	t( "$ten có nonce cho biểu mẫu", strpos( $than, 'check_admin_referer' ) !== false
		&& strpos( $than, 'wp_nonce_field' ) !== false );
}
/* Dùng chung hai hàm giúp việc của VHCC_Admin thay vì chép lại — một định nghĩa. */
t( 'màn bổ sung dùng chung VHCC_Admin::toi() và ::o()',
	strpos( $man, 'VHCC_Admin::toi()' ) !== false && strpos( $man, 'VHCC_Admin::o(' ) !== false );

/* ---- 31b. KHÔNG SÓT HÀM: mọi hàm nghiệp vụ trong sổ phải có màn nào gọi tới ----
   Đây là phép thử đáng giá nhất của mục này. Viết xong 76 hàm rồi quên dựng màn cho một nhóm là
   chuyện rất dễ xảy ra, và nó im lặng: hàm vẫn có phép thử xanh, chỉ là không ai gọi được. */
$so_raw2 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/so-doi-chieu.txt' );
$ham_mysql = array();
foreach ( explode( "\n", $so_raw2 ) as $d ) {
	if ( preg_match( '/^\S+\s+MYSQL\s+(VHCC_\w+::\w+)\s*$/', rtrim( $d ), $m ) ) {
		$ham_mysql[ $m[1] ] = 1;
	}
}
t( 'sổ có hàm MYSQL để đối chiếu', count( $ham_mysql ) > 60, count( $ham_mysql ) );
/* Mấy hàm dưới đây CỐ Ý không có màn gọi trực tiếp, kèm lý do — không được để danh sách này phình
   ra thành chỗ chứa mọi thứ chưa làm. */
$KHONG_CAN_MAN = array(
	// Hàm nền, được hàm khác gọi — không phải màn nào bấm vào.
	'VHCC_Auth::login'            => 'cổng PIN của trang, không phải màn quản trị',
	'VHCC_Nhan::phuc_vu'          => 'cổng nhận từ máy, máy gọi chứ không ai bấm',
	'VHCC_Online::cham_cong'      => 'nhân viên bấm ở trang chấm công, không phải trang quản trị',
	'VHCC_Online::lich_su'        => 'nhân viên tự xem ở trang chấm công',
	'VHCC_Online::thong_tin'      => 'trang chấm công dựng màn bằng hàm này',
	'VHCC_Online::gio_may_chu'    => 'trang chấm công lấy giờ để hiện đồng hồ',
	'VHCC_Online::anh_mau_the'    => 'trang chấm công đọc để hiện hình mẫu',
	'VHCC_Quyen::tra_pin_theo_cccd' => 'có ở màn Phân quyền, và cả trang chấm công phụ',
	'VHCC_Quyen::quyen_cua'       => 'giao diện đọc để ẩn/hiện, không có nút',
	'VHCC_Quyen::doi_pin'         => 'người dùng tự đổi ở trang của họ, không phải admin đổi hộ',
	'VHCC_YeuCau::gui_thong_tin_nv' => 'ô tự gửi ở trang chấm công phụ, cửa mở',
	'VHCC_Lich::xin_doi_lich'     => 'nhân viên tự xin ở trang của họ',
	'VHCC_Luong::vp_cfg'          => 'hàm đọc cấu hình, dùng khắp nơi',
	'VHCC_Luong::bo_phan_cua'     => 'hàm đọc, dùng khắp nơi',
	'VHCC_Luong::ho_so_nhiem_vu'  => 'hàm đọc, dùng trong engine lương',
	'VHCC_NhanSu::ds_coso'        => 'dùng để dựng ô chọn cơ sở ở mọi màn',
	'VHCC_NhanSu::ho_so'          => 'hàm đọc một hồ sơ, dùng khắp nơi',
	'VHCC_May::doi_chieu'         => 'có ở màn Máy & Firmware',
	'VHCC_Cham::ds_ghi_chu'       => 'có ở màn Bảng chấm công',
	'VHCC_Luong::bao_cao_theo_gio' => 'engine thứ ba, chưa có cơ sở nào dùng WAGE_MAP — xem ghi chú',
	'VHCC_Pdf::trang_in'          => 'có ở màn In bảng chấm công',
	'VHCC_Online::anh_mau_the_info' => 'màn Phân quyền đọc để hiện trạng thái',
	'VHCC_NhanSu::bo_phan_va_coso' => 'màn Nhân sự hiện bảng bộ phận bằng VHCC_Luong::bo_phan_cua',
	'VHCC_NhanSu::xem_truoc_nhap' => 'màn Nhân sự gọi qua nút Xem trước (nhánh xem_nhap)',
	'VHCC_Quyen::ds_bat_cham_cong_online' => 'danh sách này hiện ngay trong bảng phân quyền',
	'VHCC_Cham::thong_ke_day'     => 'có ở màn Bảng chấm công',
	'VHCC_Luong::vp_bang_cong_va_luong' => 'màn Lương gọi qua bang_cong_va_luong, nó tự định tuyến engine',
);
$sot = array();
foreach ( array_keys( $ham_mysql ) as $h ) {
	list( , $ten_ham ) = explode( '::', $h );
	if ( isset( $KHONG_CAN_MAN[ $h ] ) ) { continue; }
	if ( false !== strpos( $ad_all, $h ) ) { continue; }
	/* Chấp cả trường hợp màn gọi qua tên hàm khác của cùng lớp (VD ds_nhan_vien dùng ở nhiều chỗ). */
	if ( false !== strpos( $ad_all, '::' . $ten_ham . '(' ) ) { continue; }
	$sot[] = $h;
}
t( 'MỌI hàm nghiệp vụ đều có màn hình gọi tới (hoặc được khai rõ là không cần)',
	count( $sot ) === 0, count( $sot ) . ' hàm chưa có màn: ' . implode( ' | ', $sot ) );
/* Danh sách "không cần màn" phải có LÝ DO và không được phình ra vô hạn. */
$thieu_ly_do = array();
foreach ( $KHONG_CAN_MAN as $h => $ly ) { if ( strlen( $ly ) < 15 ) { $thieu_ly_do[] = $h; } }
t( 'mọi dòng "không cần màn" đều ghi lý do', count( $thieu_ly_do ) === 0, implode( ', ', $thieu_ly_do ) );
t( 'danh sách "không cần màn" không phình ra quá nửa số hàm',
	count( $KHONG_CAN_MAN ) < count( $ham_mysql ) / 2,
	count( $KHONG_CAN_MAN ) . '/' . count( $ham_mysql ) );

/* ---- 31c. Mấy cảnh báo PHẢI hiện ra mặt ---- */
$phai_co = array(
	'không tự điền'        => 'quên check-out: nói rõ hệ thống không tự điền giờ ra',
	/* ⚠️ Chọn cụm KHÔNG có chữ hoa có dấu: `stripos` so theo BYTE nên 'CHỐT' và 'chốt' là hai chuỗi
	   khác nhau, và phép thử sẽ báo hỏng ở chỗ màn hình vốn đã nói đúng. Mất một lượt vì chuyện này. */
	'không sửa được nữa'   => 'tăng cường: nói rõ chốt kỳ là không sửa được',
	'một bước'             => 'quy đổi cơ sở: nói rõ chỉ tra một bước',
	'không bị chạm'        => 'dọn dữ liệu: nói rõ chấm công không bị chạm',
	'cấp Mã NV'            => 'duyệt yêu cầu: nói rõ là cấp mã cả chuỗi',
	'phải'                 => 'từ chối yêu cầu: nói rõ phải có lý do',
	'số dương'             => 'đơn giá: nói rõ chỉ nhận số dương',
	'không mượn số'        => 'ngày công: nói rõ không mượn số tháng khác',
	'tăng 50%'             => 'bậc thang: nói rõ hậu quả đặt mốc sai',
	'không có đường lùi'   => 'đổi mã: nói rõ không lùi được',
	'vẫn tra ra tên'       => 'cho nghỉ việc: nói rõ vì sao không xoá',
	'trong chính tệp'      => 'nhập loạt: nói rõ bắt trùng mã trong tệp',
	'không xoá'            => 'tắt lịch: nói rõ không xoá ô đã xếp',
	'khoá của ô lịch'      => 'đổi tên ca: nói rõ ca là phần của khoá',
	'đã che'               => 'tra PIN: nói rõ nhật ký che CCCD',
	'dễ đoán'              => 'cấp PIN: nói rõ không sinh PIN dễ đoán',
	'ADMIN cuối cùng'      => 'xoá phân quyền: nói rõ không xoá admin cuối',
	'cùng một'             => 'gộp tài khoản: nói rõ phải cùng một mã NV',
);
$thieu_bao = array();
foreach ( $phai_co as $chu => $vi_sao ) {
	if ( false === stripos( $ad_all, $chu ) ) { $thieu_bao[] = $vi_sao; }
}
t( 'màn hình nói ra đủ những hậu quả người dùng cần biết TRƯỚC khi bấm',
	count( $thieu_bao ) === 0, implode( ' | ', $thieu_bao ) );

/* Bốn màn mới phải nằm trong menu. */
t( 'bốn màn mới đều được đăng ký vào menu',
	strpos( $man, "'vhcc-quyen'" ) !== false && strpos( $man, "'vhcc-cham'" ) !== false
	&& strpos( $man, "'vhcc-yeu-cau'" ) !== false && strpos( $man, "'vhcc-cf-luong'" ) !== false );
t( 'và admin gọi menu_them để đăng ký', strpos( $ad_all, 'VHCC_Man::menu_them(' ) !== false );

// ============================================================ 32. Bản cài lên hosting
/* Bản .zip đi lên hosting là thứ NẰM DƯỚI wp-content/plugins/… và đọc được từ web bằng một địa chỉ
   đoán ra được. Nên mục này canh hai điều: bản cài không mang theo thứ không cần, và không mang
   theo bí mật nào. */
$sh = file_get_contents( $goc . '/tools/build-plugin-zip.sh' );
t( 'bản cài BỎ thư mục goc/ (1,3 MB bản gốc Code.gs + Index.html, không chạy gì)',
	strpos( $sh, '/goc/*' ) !== false );
/* ⚠️ ĐÃ LẬT LẠI. Phép thử này trước đây đòi bản cài BỎ `apps-script/` — tức là nó canh giữ
   đúng cái lỗi: `VHCC_Trang::ds_ham()` đọc `apps-script/cau-noi.gs` lúc chạy, thiếu file thì
   trang chấm công báo "CC_CHO_PHEP còn RỖNG" và chỉ người dùng đi sửa bên Apps Script — nơi
   danh sách vẫn đủ 23 hàm. Một phép thử sai còn tệ hơn không có phép thử, vì nó chặn người
   sửa. Giờ đòi ngược lại, và mục 37 canh cả hai chiều. */
t( 'bản cài GIỮ apps-script/ vì mã đọc nó lúc chạy (xem mục 37)',
	strpos( $sh, "-x \"\$(basename \"\$SRC\")/apps-script/*\"" ) === false );
t( 'và nói rõ VÌ SAO bỏ, không chỉ bỏ',
	strpos( $sh, 'ĐỌC ĐƯỢC TỪ WEB' ) !== false );
t( 'kiểm cú pháp PHP TRƯỚC khi đóng gói (thà báo lỗi ở đây hơn trên hosting)',
	strpos( $sh, 'php -l' ) !== false );

/* Mọi tệp THẬT SỰ đi lên hosting phải sạch bí mật. Quét trực tiếp, không tin ghi chú. */
$mau_cam = array( 'AKfycb', 'AIza', 'default-rtdb', 'firebaseio' );
$loi_zip = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator(
	$goc . '/wordpress/vhcp-cham-cong', FilesystemIterator::SKIP_DOTS ) );
$so_tep = 0;
foreach ( $it as $f ) {
	$duong = str_replace( '\\', '/', $f->getPathname() );
	if ( strpos( $duong, '/goc/' ) !== false || strpos( $duong, '/apps-script/' ) !== false ) { continue; }
	if ( ! $f->isFile() ) { continue; }
	$so_tep++;
	$noi = file_get_contents( $duong );
	foreach ( $mau_cam as $m ) {
		if ( false !== stripos( $noi, $m ) ) { $loi_zip[] = basename( $duong ) . ': ' . $m; }
	}
	/* Liên kết /exec THẬT (có mã triển khai) thì không được nằm trong bản cài — chữ gợi ý trong ô
	   nhập có dạng `https://script.google.com/…/exec` với dấu ba chấm thì vô hại. */
	if ( preg_match( '#/macros/s/[A-Za-z0-9_-]{20,}/exec#', $noi ) ) {
		$loi_zip[] = basename( $duong ) . ': liên kết /exec thật';
	}
}
t( 'quét được đủ tệp của bản cài', $so_tep > 10, $so_tep );
t( 'KHÔNG tệp nào trong bản cài chứa bí mật', count( $loi_zip ) === 0, implode( ' | ', $loi_zip ) );
/* `888888` được phép có mặt — nhưng CHỈ ở chỗ nó là PIN BỊ CHẶN, không phải PIN đang dùng. */
$q_noi = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-quyen.php' );
t( "'888888' trong bản cài chỉ nằm ở danh sách PIN bị chặn",
	strpos( $q_noi, "const PIN_CAM" ) !== false
	&& preg_match( "/PIN_ADMIN|pin_admin\s*=\s*'888888'/", $q_noi ) === 0 );
/* Thư mục nào cũng phải có index.php im lặng — chặn liệt kê thư mục nếu máy chủ bật autoindex. */
foreach ( array( '', '/includes', '/assets', '/assets/js' ) as $d ) {
	t( "thư mục$d có index.php im lặng",
		file_exists( $goc . '/wordpress/vhcp-cham-cong' . $d . '/index.php' ) );
}
// ======================================================= 33. VẼ THẬT TỪNG MÀN HÌNH wp-admin
/* ⚠️ LOẠI LỖI BỘ THỬ NÀY TỪNG MÙ HOÀN TOÀN.
   Trang Cài đặt đã lên hosting với một dòng `VHCC_Auth::VAI_TRO_VAO` — hằng đó KHÔNG tồn tại
   (tên đúng là hàm `vai_tro_vao()`). PHP coi đó là lỗi nghiêm trọng, trang đứt ngay tại dòng
   đó, và vì nút "Lưu cài đặt" nằm PHÍA DƯỚI nên anh Thắng dán xong liên kết mà không có nút
   nào để lưu. 873 phép thử trước đó không thấy gì, vì chúng gọi thẳng hàm nghiệp vụ và KHÔNG
   HỀ VẼ màn hình — mà lỗi này chỉ nổ lúc vẽ.

   Nên mục này vẽ thật từng màn, và lấy danh sách màn từ `VHCC_Admin::menu()` chứ không gõ tay:
   thêm màn mới là tự có phép thử, không phải nhớ bổ sung vào đây. */
vhcc_dung_bang();
$GLOBALS['VHCP_CO_QUYEN'] = true;
$GLOBALS['VHCP_MENU']     = array();
VHCC_Admin::menu();

/* Đếm bằng số THẬT: con số gõ tay ở đây vỡ mỗi lần thêm màn, vì một lý do chẳng liên quan gì
   tới thứ nó canh. Cái đáng canh là "MỌI màn khai trong menu đều vẽ được" — mấy dòng dưới. */
t( 'menu có khai màn', count( $GLOBALS['VHCP_MENU'] ) >= 11, count( $GLOBALS['VHCP_MENU'] ) );
t( 'mục gốc `vhcc` là trang Cài đặt', isset( $GLOBALS['VHCP_MENU']['vhcc'] ) );

foreach ( $GLOBALS['VHCP_MENU'] as $slug => $m ) {
	t( "màn $slug đòi quyền manage_options", $m['cap'] === 'manage_options', $m['cap'] );
	t( "màn $slug có hàm vẽ gọi được", is_callable( $m['cb'] ) );
	if ( ! is_callable( $m['cb'] ) ) { continue; }
	ob_start();
	$loi = '';
	try { call_user_func( $m['cb'] ); } catch ( Throwable $e ) {
		$loi = get_class( $e ) . ': ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	$html = ob_get_clean();
	t( "màn $slug vẽ hết không chết giữa đường", $loi === '', $loi );
	t( "màn $slug vẽ ra nội dung", strlen( $html ) > 200, strlen( $html ) );
	/* Vẽ xong mà thẻ mở nhiều hơn thẻ đóng thì trang bị đứt giữa — đúng triệu chứng của lỗi trên. */
	t( "màn $slug đóng đủ thẻ <div>",
		substr_count( $html, '<div' ) === substr_count( $html, '</div>' ),
		substr_count( $html, '<div' ) . ' mở / ' . substr_count( $html, '</div>' ) . ' đóng' );
	/* Màn nào có <form> thì phải có nút bấm — form không nút là form không dùng được. */
	if ( strpos( $html, '<form' ) !== false ) {
		t( "màn $slug: form nào cũng tới được nút bấm",
			strpos( $html, '<button' ) !== false || strpos( $html, 'type="submit"' ) !== false );
	}
	/* Nonce: soát TỪNG form, và chỉ đòi ở form POST.
	   Form GET ở đây là bộ lọc (chọn cơ sở, chọn khoảng ngày) — chỉ đọc, không đổi gì, đòi
	   nonce là sai. Đòi cả cụm "màn này có _wpnonce ở đâu đó" cũng sai theo chiều ngược lại:
	   một màn có 5 form mà chỉ 1 form có nonce thì vẫn lọt. Nên cắt theo từng form. */
	foreach ( array_slice( explode( '<form', $html ), 1 ) as $khuc ) {
		$het = strpos( $khuc, '</form>' );
		$than = ( false === $het ) ? $khuc : substr( $khuc, 0, $het );
		if ( false === stripos( $than, 'method="post"' ) ) { continue; }
		$dau = trim( substr( $than, 0, 60 ) );
		t( "màn $slug: form POST có nonce (" . $dau . ')', strpos( $than, '_wpnonce' ) !== false );
	}
}

/* Chốt quyền: KHÔNG có manage_options thì mọi màn phải chặn (wp_die), không màn nào lọt. */
$GLOBALS['VHCP_CO_QUYEN'] = false;
$lot = array();
foreach ( $GLOBALS['VHCP_MENU'] as $slug => $m ) {
	ob_start();
	$chan = false;
	try { call_user_func( $m['cb'] ); } catch ( Throwable $e ) {
		$chan = strpos( $e->getMessage(), 'wp_die' ) === 0;
	}
	ob_end_clean();
	if ( ! $chan ) { $lot[] = $slug; }
}
t( 'người không đủ quyền bị chặn ở CẢ 11 màn', count( $lot ) === 0, implode( ', ', $lot ) );
$GLOBALS['VHCP_CO_QUYEN'] = true;

/* LƯỢT VẼ THỨ HAI — CÓ DỮ LIỆU VÀ CÓ CHỌN BỘ LỌC.
   Lượt trên vẽ lúc bảng rỗng, nên hầu hết màn chỉ ra được mỗi cái form chọn cơ sở rồi thôi:
   đúng phần thân bảng — chỗ nhiều mã nhất và nhiều chỗ hỏng nhất — không hề chạy. Lượt này
   gieo một cơ sở, một người, một hàng chấm công, một hàng lịch, một yêu cầu, rồi đặt $_GET
   như lúc anh Thắng bấm "Xem". */
global $wpdb;
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'NV001', 'ho_ten' => 'Trần Văn A', 'cua_hang' => 'TUTU_BT',
	'chuc_vu' => 'Nhân viên', 'sdt' => '0900000001' ) );
vhcc_bo_phan( 'TUTU_BT', 'Bán hàng' );
vhcc_cham( 'TUTU_BT', '2026-08-03', 'NV001', '', '08:00', '17:30' );
vhcc_cham( 'TUTU_BT', '2026-08-04', 'NV001', 'TC', '18:00', '21:00' );
vhcc_cham( 'TUTU_BT', '2026-08-05', 'NV001', '', '08:05', null );   // thiếu giờ ra: nhánh cảnh báo
$wpdb->insert( VHCC_DB::t( 'lich_cv' ), array(
	'coso' => 'TUTU_BT', 'ma_nv' => 'NV001', 'ho_ten' => 'Trần Văn A',
	'ngay' => '2026-08-03', 'ca' => 'Sáng', 'viec' => 'Bán hàng' ) );
$wpdb->insert( VHCC_DB::t( 'yeu_cau_nv' ), array(
	'ma_yc' => 'YC20260801120000123', 'loai' => 'them_nv', 'ho_ten' => 'Lê Thị B',
	'coso' => 'TUTU_BT', 'trang_thai' => 'Chờ duyệt', 'nguoi_xin' => 'CHT',
	'luc_xin' => '2026-08-01 12:00:00' ) );
$wpdb->insert( VHCC_DB::t( 'may' ), array(
	'serial' => 'SN-THU-1', 'mac' => 'AA:BB:CC:DD:EE:01', 'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array(
	'pin' => '123456', 'ho_ten' => 'Trần Văn A', 'vai_tro' => 'NHAN_VIEN', 'cua_hang' => 'TUTU_BT' ) );

$_GET = array( 'coso' => 'TUTU_BT', 'tu' => '2026-08-01', 'den' => '2026-08-31',
	'thang' => '2026-08', 'ma_nv' => 'NV001', 'cach' => 'mtd' );

foreach ( $GLOBALS['VHCP_MENU'] as $slug => $m ) {
	ob_start();
	$loi = '';
	try { call_user_func( $m['cb'] ); } catch ( Throwable $e ) {
		$loi = get_class( $e ) . ': ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	$html = ob_get_clean();
	t( "màn $slug (có dữ liệu) vẽ hết không chết giữa đường", $loi === '', $loi );
	t( "màn $slug (có dữ liệu) đóng đủ thẻ <div>",
		substr_count( $html, '<div' ) === substr_count( $html, '</div>' ),
		substr_count( $html, '<div' ) . ' mở / ' . substr_count( $html, '</div>' ) . ' đóng' );
	t( "màn $slug (có dữ liệu) đóng đủ thẻ <table>",
		substr_count( $html, '<table' ) === substr_count( $html, '</table>' ),
		substr_count( $html, '<table' ) . ' mở / ' . substr_count( $html, '</table>' ) . ' đóng' );
	foreach ( array_slice( explode( '<form', $html ), 1 ) as $khuc ) {
		$het = strpos( $khuc, '</form>' );
		$than = ( false === $het ) ? $khuc : substr( $khuc, 0, $het );
		if ( false === stripos( $than, 'method="post"' ) ) { continue; }
		t( "màn $slug (có dữ liệu): form POST có nonce (" . trim( substr( $than, 0, 60 ) ) . ')',
			strpos( $than, '_wpnonce' ) !== false );
	}
}
/* Vẽ ra dữ liệu thật thì phải THẤY nó — không thì lượt trên chỉ đang vẽ lại cái form rỗng. */
ob_start(); VHCC_Man::trang_cham(); $h_cham = ob_get_clean();
t( 'màn Bảng chấm công thật sự in ra hàng đã gieo', strpos( $h_cham, 'NV001' ) !== false );
t( 'và in ra giờ dạng HH:mm chứ không phải số giây',
	strpos( $h_cham, '08:00' ) !== false && strpos( $h_cham, '28800' ) === false );
$_GET = array();

/* Phép soát tham chiếu tĩnh phải nằm trong bộ thử, không chỉ là tệp rời ai nhớ thì chạy. */
$ma_soat = 0;
exec( 'php ' . escapeshellarg( $goc . '/tools/test/kiem-tham-chieu.php' ) . ' 2>&1', $ra_soat, $ma_soat );
t( 'phép soát tham chiếu tĩnh: không chỗ nào gọi hằng/hàm không tồn tại',
	$ma_soat === 0, implode( "\n      ", $ra_soat ) );

// ================================================ 34. MỘT TÊN DUY NHẤT CHO MỖI THỨ PHẢI GÕ TAY
/* Anh Thắng bắt được: cùng một file cầu nối mà trang Cài đặt bảo đặt tên `CauNoi`, phần đầu
   file .gs bảo `CauNoiChamCong`, còn câu báo lỗi thì nói `CauNoiChamCong.gs`. Ba tên cho một
   thứ, và đó là thứ NGƯỜI DÙNG PHẢI GÕ TAY — gõ tên khác là cầu nối không bao giờ nối được,
   mà lỗi hiện ra lại là "chưa dán file", đúng cái anh vừa làm.

   Mục này canh: mỗi thứ gõ tay chỉ có MỘT tên trong toàn bộ plugin + hướng dẫn. */
$tep_soat = array();
foreach ( array( 'includes/class-vhcc-admin.php', 'includes/class-vhcc-trang.php',
	'includes/class-vhcc-cau-noi.php', 'includes/class-vhcc-api.php',
	'apps-script/cau-noi.gs', 'apps-script/ghi-song-song.gs' ) as $x ) {
	$tep_soat[ $x ] = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/' . $x );
}
$tep_soat['docs/CAI-LEN-HOSTING.md'] = file_get_contents( $goc . '/docs/CAI-LEN-HOSTING.md' );
$het = implode( "\n", $tep_soat );

/* Tên file cầu nối: chỉ `CauNoiChamCong`. `CauNoi` trơ trọi (không có chữ ChamCong theo sau)
   là tên của app HỢP ĐỒNG — lẫn sang đây là sai. Bỏ qua `VHCC_CauNoi` vì đó là tên lớp PHP. */
$le = array();
foreach ( $tep_soat as $ten => $noi ) {
	foreach ( explode( "\n", $noi ) as $i => $d ) {
		$d_sach = str_replace( array( 'VHCC_CauNoi', 'class-vhcc-cau-noi', 'cau-noi.gs' ), '', $d );
		if ( preg_match( '/\bCauNoi(?!ChamCong)/', $d_sach ) ) { $le[] = $ten . ':' . ( $i + 1 ); }
	}
}
t( 'file cầu nối chỉ có MỘT tên: CauNoiChamCong', count( $le ) === 0, implode( ' | ', $le ) );

/* Tên file ghi song song: chỉ `GhiSongSongWP`. */
$le = array();
foreach ( $tep_soat as $ten => $noi ) {
	foreach ( explode( "\n", $noi ) as $i => $d ) {
		$d_sach = str_replace( 'ghi-song-song.gs', '', $d );
		if ( preg_match( '/\bGhiSongSong(?!WP)/', $d_sach ) ) { $le[] = $ten . ':' . ( $i + 1 ); }
	}
}
t( 'file ghi song song chỉ có MỘT tên: GhiSongSongWP', count( $le ) === 0, implode( ' | ', $le ) );

/* Trang Cài đặt và hướng dẫn phải nói ĐÚNG cái tên mà file .gs tự nhận. */
t( 'phần đầu cau-noi.gs dặn đặt tên CauNoiChamCong',
	strpos( $tep_soat['apps-script/cau-noi.gs'], '`CauNoiChamCong`' ) !== false );
t( 'trang Cài đặt dặn ĐÚNG cái tên đó',
	strpos( $tep_soat['includes/class-vhcc-admin.php'], '<code>CauNoiChamCong</code>' ) !== false );
t( 'hướng dẫn cài cũng dặn đúng tên đó',
	strpos( $tep_soat['docs/CAI-LEN-HOSTING.md'], '`CauNoiChamCong`' ) !== false );
t( 'hướng dẫn cài có nhắc dán cau-noi.gs (trước đây thiếu hẳn bước này)',
	strpos( $tep_soat['docs/CAI-LEN-HOSTING.md'], 'apps-script/cau-noi.gs' ) !== false );

/* Plugin chấm công KHÔNG được tự gọi mình là app hợp đồng. Bốn câu báo lỗi đã từng như vậy —
   chép từ plugin hợp đồng sang mà quên đổi tên, nên người đọc đi kiểm sai app. */
$le = array();
foreach ( array( 'includes/class-vhcc-cau-noi.php', 'includes/class-vhcc-trang.php',
	'includes/class-vhcc-api.php' ) as $x ) {
	foreach ( explode( "\n", $tep_soat[ $x ] ) as $i => $d ) {
		/* Chỉ soát chữ đi RA MÀN HÌNH, không soát chú thích so sánh hai app. */
		if ( preg_match( '/^\s*(\*|\/\/)/', $d ) ) { continue; }
		if ( preg_match( '/(App|app|Dữ liệu|dữ liệu)\s+hợp đồng/u', $d ) ) { $le[] = $x . ':' . ( $i + 1 ); }
	}
}
t( 'plugin chấm công không câu nào tự gọi mình là app hợp đồng',
	count( $le ) === 0, implode( ' | ', $le ) );

/* Hai khoá khác nhau, và hướng dẫn phải nói rõ cái nào của file nào — anh Thắng đã một lần
   hiểu WEB_KEY là thứ mình phải tự đặt rồi dán vào WordPress (thực ra là chiều ngược lại). */
$hd = $tep_soat['docs/CAI-LEN-HOSTING.md'];
t( 'hướng dẫn phân biệt rõ WEB_KEY với WP_KEY',
	strpos( $hd, '`WEB_KEY`' ) !== false && strpos( $hd, '`WP_KEY`' ) !== false
	&& strpos( $hd, 'đừng dán lẫn' ) !== false );

// ===================================================== 35. CHUẨN HOÁ ĐỊA CHỈ /exec
/* CA THẬT lúc anh Thắng cài: trình soạn Apps Script của tài khoản Google Workspace hiện địa chỉ
   dạng `script.google.com/a/macros/khmatrix.com/s/<ID>/exec`. Dạng đó buộc người gọi phải đăng
   nhập bằng tài khoản của tên miền, mà WordPress gọi máy-với-máy — Google trả `400 Bad Request`,
   một câu không hề nhắc gì tới đăng nhập. Đọc câu đó xong vẫn tưởng mình dán sai ID hoặc quên
   Deploy, mà cả hai đều đúng cả. */
$ID = 'AKfycbIyzyeX3_CNnc2QF1--8T2d5nzXt9pKqHc1zICI8QBfKXFPv';

$r = VHCC_CauNoi::chuan_hoa_url( 'https://script.google.com/a/macros/khmatrix.com/s/' . $ID . '/exec' );
teq( 'bỏ /a/macros/<tên miền>, giữ nguyên ID bản triển khai',
	'https://script.google.com/macros/s/' . $ID . '/exec', $r['url'] );
t( 'và NÓI RÕ đã sửa gì (sửa ngầm thì lần sau lại dán đúng cái sai đó)',
	count( $r['sua'] ) === 1 && strpos( $r['sua'][0], '/a/macros/khmatrix.com' ) !== false );
t( 'lời giải thích có nhắc 400 Bad Request — đúng câu anh thấy trên màn hình',
	strpos( $r['sua'][0], '400 Bad Request' ) !== false );

/* Địa chỉ đã đúng thì KHÔNG được đụng vào. */
$dung = 'https://script.google.com/macros/s/' . $ID . '/exec';
$r = VHCC_CauNoi::chuan_hoa_url( $dung );
teq( 'địa chỉ đã đúng thì để nguyên', $dung, $r['url'] );
teq( 'và không báo đã sửa gì', array(), $r['sua'] );

/* Dấu / ở cuối. */
$r = VHCC_CauNoi::chuan_hoa_url( $dung . '/' );
teq( 'bỏ dấu / ở cuối', $dung, $r['url'] );
t( 'và nói là đã bỏ', count( $r['sua'] ) === 1 );

/* Cả hai lỗi một lúc — phải sửa cả hai, không phải chỉ cái đầu. */
$r = VHCC_CauNoi::chuan_hoa_url( 'https://script.google.com/a/macros/khmatrix.com/s/' . $ID . '/exec/' );
teq( 'sửa được cả hai lỗi cùng lúc', $dung, $r['url'] );
teq( 'và kể ra cả hai', 2, count( $r['sua'] ) );

/* `/dev` là địa chỉ bản THỬ — nó luôn đòi đăng nhập, gọi từ ngoài không bao giờ được. Chỉ CẢNH
   BÁO chứ không tự đổi: /dev và /exec là hai bản khác nhau, tự đổi là đoán bừa. */
$dev = 'https://script.google.com/macros/s/' . $ID . '/dev';
$r = VHCC_CauNoi::chuan_hoa_url( $dev );
teq( 'địa chỉ /dev KHÔNG bị tự đổi thành /exec (hai bản khác nhau, không đoán)', $dev, $r['url'] );
t( 'nhưng phải cảnh báo /dev luôn đòi đăng nhập',
	count( $r['sua'] ) === 1 && strpos( $r['sua'][0], '/dev' ) !== false );

/* Rỗng và rác không được làm hàm này chết — nó chạy trên đường lưu Cài đặt. */
teq( 'địa chỉ rỗng trả rỗng', '', VHCC_CauNoi::chuan_hoa_url( '' )['url'] );
teq( 'khoảng trắng cũng về rỗng', '', VHCC_CauNoi::chuan_hoa_url( "  \n\t " )['url'] );
$r = VHCC_CauNoi::chuan_hoa_url( 'ba la bla' );
teq( 'chuỗi rác thì để nguyên, không ném lỗi', 'ba la bla', $r['url'] );

/* Tên miền khác `script.google.com` thì đừng cắt gì — không phải Apps Script, đoán là hỏng. */
$la = 'https://vidu.test/a/macros/abc.com/s/XYZ/exec';
teq( 'không cắt địa chỉ ngoài script.google.com', $la, VHCC_CauNoi::chuan_hoa_url( $la )['url'] );

/* ============ DÁN NHẦM LINK BẢNG TÍNH VÀO Ô ĐỊA CHỈ APP
 *
 * 🔴 Anh Thắng ngày 22/08/2026 dán `docs.google.com/spreadsheets/d/…/edit` vào ô "/exec".
 *    Nhầm rất dễ hiểu — trong đầu người dùng thì cả hai đều là "link của app chấm công", mà
 *    ô này không hề nói cái nào sai. Im lặng nhận vào thì trang chấm công gọi đúng cái link
 *    đó, Google trả về một trang HTML, và lỗi hiện ra chẳng liên quan gì tới chuyện dán nhầm.
 *
 * KHÔNG chữa được bằng máy: từ ID bảng tính không suy ra được ID bản triển khai. Nên phép thử
 * này đòi lời nhắn phải chỉ ĐÚNG ĐƯỜNG đi lấy cái đúng, chứ không chỉ nói "sai rồi".
 */
$sheet = 'https://docs.google.com/spreadsheets/d/1rvcvO6ixS8dvGVGs3AhrR7Rk7s/edit?gid=209#gid=209';
$r_sh  = VHCC_CauNoi::chuan_hoa_url( $sheet );
$noi   = implode( ' ', $r_sh['sua'] );
t( 'nhận ra link BẢNG TÍNH và kêu lên', count( $r_sh['sua'] ) > 0, $r_sh );
t( 'nói rõ đây là địa chỉ bảng tính', strpos( $noi, 'BẢNG TÍNH' ) !== false, $noi );
t( 'chỉ đúng đường đi lấy cái đúng (Manage deployments)',
	strpos( $noi, 'Manage deployments' ) !== false && strpos( $noi, 'Apps Script' ) !== false, $noi );
t( 'nói luôn là plugin KHÔNG tự sửa hộ được', strpos( $noi, 'không tự sửa' ) !== false, $noi );
teq( 'nhưng KHÔNG bịa ra địa chỉ khác — giữ nguyên cái đã dán', $sheet, $r_sh['url'] );

/* Tên miền lạ nói chung cũng phải kêu — nhưng bằng câu nhẹ hơn, vì có thể là chỗ khác thật. */
$r_la = VHCC_CauNoi::chuan_hoa_url( 'https://vidu.test/s/XYZ/exec' );
t( 'địa chỉ ngoài script.google.com thì cảnh báo', count( $r_la['sua'] ) > 0, $r_la );
t( 'và câu đó nhắc đúng script.google.com',
	strpos( implode( ' ', $r_la['sua'] ), 'script.google.com' ) !== false );

/* ⚠️ Đừng kêu oan. Ba dạng địa chỉ ĐÚNG dưới đây phải im lặng về chuyện tên miền. */
foreach ( array(
	'địa chỉ đúng'          => $dung,
	'dạng Workspace'        => 'https://script.google.com/a/macros/khmatrix.com/s/' . $ID . '/exec',
	'có dấu / ở cuối'       => $dung . '/',
) as $ten_ca => $u_ok ) {
	$sua_ok = implode( ' ', VHCC_CauNoi::chuan_hoa_url( $u_ok )['sua'] );
	t( "$ten_ca: KHÔNG bị kêu là sai tên miền",
		strpos( $sua_ok, 'BẢNG TÍNH' ) === false
		&& strpos( $sua_ok, 'không nằm trên' ) === false, $sua_ok );
}
teq( 'địa chỉ rỗng thì không kêu gì cả', array(), VHCC_CauNoi::chuan_hoa_url( '' )['sua'] );

/* Trang Cài đặt phải THẬT SỰ gọi hàm này lúc lưu, và phải hiện lời giải thích ra. */
$ad = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
t( 'lúc lưu Cài đặt có chuẩn hoá địa chỉ',
	preg_match( '/VHCC_CauNoi::chuan_hoa_url\s*\(\s*\$url\s*\)/', $ad ) === 1 );
t( 'lưu ĐÚNG địa chỉ đã chuẩn hoá, không lưu chuỗi thô',
	strpos( $ad, "update_option( 'vhcc_exec_url', \$ch['url'] )" ) !== false
	&& preg_match( "/update_option\(\s*'vhcc_exec_url',\s*\\\$url\s*\)/", $ad ) === 0 );
t( 'và hiện lời giải thích ra màn hình',
	strpos( $ad, 'Địa chỉ /exec đã được sửa lại' ) !== false );

/* Hướng dẫn phải chỉ rõ CHÈN VÀO ĐÂU: doPost thật có HAI dòng `try {`, nói "sau try {" là mơ hồ. */
$q_hd = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/ghi-song-song.gs' );
t( 'ghi-song-song.gs nói rõ là `try {` THỨ HAI, không chỉ nói "sau try {"',
	stripos( $q_hd, 'try {` THỨ HAI' ) !== false || stripos( $q_hd, 'try {` thứ hai' ) !== false );
t( 'và mốc nhận dạng được: dòng ngay trên JSON.parse',
	strpos( $q_hd, 'JSON.parse' ) !== false );

/* Dòng phải chèn KHÔNG được có dấu \ thoát — copy vào Apps Script là sai cú pháp ngay.
   Anh Thắng phát hiện chỗ đang đọc tự thêm `\&\&`; file gốc phải sạch để không góp thêm. */
foreach ( array( 'wordpress/vhcp-cham-cong/apps-script/ghi-song-song.gs',
	'docs/CAI-LEN-HOSTING.md' ) as $x ) {
	$noi = file_get_contents( $goc . '/' . $x );
	t( "$x: dòng wpXepHang dùng && sạch, không có dấu \\ thoát",
		strpos( $noi, 'wpXepHang(e && e.postData' ) !== false
		&& strpos( $noi, '\\&\\&' ) === false );
}

/* TỰ CHỮA: địa chỉ hỏng đã nằm trong cơ sở dữ liệu thì url() phải sửa ngay, không đợi bấm Lưu.
   Bản đầu chỉ sửa lúc bấm Lưu — nên trang chấm công vẫn 400 mãi, mà người đọc trang lỗi thì
   không có lý do gì đi bấm Lưu một biểu mẫu họ không sửa gì. */
update_option( 'vhcc_exec_url', 'https://script.google.com/a/macros/khmatrix.com/s/' . $ID . '/exec' );
teq( 'url() tự chữa địa chỉ đã lưu sai, không cần bấm Lưu',
	'https://script.google.com/macros/s/' . $ID . '/exec', VHCC_CauNoi::url() );
teq( 'và GHI LẠI giá trị đã sửa (màn Cài đặt phải hiện đúng cái đang dùng)',
	'https://script.google.com/macros/s/' . $ID . '/exec', get_option( 'vhcc_exec_url' ) );
teq( 'url_tho() đọc đúng giá trị trong cơ sở dữ liệu',
	get_option( 'vhcc_exec_url' ), VHCC_CauNoi::url_tho() );

/* Trang lỗi phải NÓI THẲNG nguyên nhân đã biết, không bắt dò danh sách 4 mục. */
$tr = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang.php' );
t( 'trang lỗi nhận ra dạng /a/macros/ và nói thẳng nguyên nhân',
	strpos( $tr, '/a/macros/' ) !== false && strpos( $tr, 'Đã tìm ra nguyên nhân' ) !== false );
t( 'và bảo tải lại trang, chứ không bảo đi kiểm lại địa chỉ',
	strpos( $tr, 'Tải lại trang này một lần' ) !== false );
t( 'trang lỗi soi CẢ giá trị thô — nếu chỉ soi cái đã chữa thì không bao giờ khớp',
	strpos( $tr, 'VHCC_CauNoi::url_tho()' ) !== false );

/* Kết quả "Thử cầu nối" chỉ được hiện MỘT LẦN. Để nó nằm lại là tải lại trang thấy y nguyên
   hộp đỏ cũ, không mốc giờ, nên trông như lỗi vẫn còn — đã mất một vòng đúng vì chuyện này. */
$ad = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
/* ⚠️ Cắt theo MỐC, không cắt theo số ký tự cố định. Bản đầu cắt 400 ký tự và trượt oan: chú
   thích giải thích chuyện này dài hơn 400 ký tự nên `delete_transient` rơi ra ngoài lát cắt.
   Đây là lần thứ hai cùng một cái bẫy trong bộ thử này. */
$_tu  = strpos( $ad, "if ( \$msg === 'thu' )" );
$_den = strpos( $ad, "echo '<h2>Mở hệ thống chấm công</h2>", $_tu );
$khuc_thu = substr( $ad, $_tu, $_den - $_tu );
t( 'kết quả Thử cầu nối bị xoá ngay sau khi đọc (không hiện lại lần sau)',
	strpos( $khuc_thu, "delete_transient( 'vhcc_thu_'" ) !== false );

/* Chế độ "danh sách riêng" chưa có màn khai người — chọn nó là tắc im lặng, phải nói thẳng. */
/* ⚠️ ĐÃ LẬT LẠI. Ba phép thử ở đây trước kia đòi màn Cài đặt khuyên "đi cài plugin chi phí" —
   tức chúng canh giữ đúng chỗ thiếu: chế độ "danh sách riêng" không có màn khai người dùng.
   Anh Thắng: *"anh để chỉ plugin này thôi mà"*. Giờ có màn thật (VHCC_NguoiDung), nên phép thử
   phải đòi CÓ Ô THÊM NGƯỜI, chứ không đòi lời khuyên đi cài plugin khác. */
t( 'chế độ danh sách riêng có ô thêm người ngay tại màn Cài đặt',
	strpos( $ad, "name=\"vhcc_nd\" value=\"luu\"" ) !== false );
t( 'danh sách rỗng thì vẫn cảnh báo là chưa ai đăng nhập được',
	strpos( $ad, 'chưa ai đăng nhập được' ) !== false );
t( 'KHÔNG còn khuyên đi cài plugin chi phí để có chỗ khai người dùng',
	strpos( $ad, 'vhcp-chi-phi.zip' ) === false );

/* Phiên bản: hai chỗ khai số này, lệch nhau là WordPress hiện một số mà mã chạy một số khác. */
$chinh = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
preg_match( '/^ \* Version:\s+(\S+)$/m', $chinh, $m1 );
preg_match( "/define\(\s*'VHCC_VERSION',\s*'([^']+)'/", $chinh, $m2 );
teq( 'số bản ở đầu tệp khớp với VHCC_VERSION',
	isset( $m1[1] ) ? $m1[1] : 'thiếu', isset( $m2[1] ) ? $m2[1] : 'thiếu' );
t( 'màn Cài đặt hiện bản đang chạy (để trả lời được "cài bản mới chưa")',
	strpos( $ad, 'Bản plugin đang chạy' ) !== false );

// ============================================ 35. CHẨN ĐOÁN ĐỊA CHỈ /exec — ba tình huống thật
/* `400 Bad Request` của Google là lỗi ở CỔNG VÀO, tức là yêu cầu chưa tới script. Nên mọi
   phỏng đoán "chưa dán cầu nối / sai WEB_KEY" đều vô nghĩa — script chưa hề chạy. Phép chẩn
   đoán thử cả hai dạng địa chỉ để phân biệt, và phải KẾT LUẬN chứ không chỉ in số. */
update_option( 'vhcc_exec_url', 'https://script.google.com/a/macros/khmatrix.com/s/' . $ID . '/exec' );

/* Ca 1 — bản triển khai bị giới hạn: dạng tên miền đòi đăng nhập, dạng rút gọn bị 400. */
$GLOBALS['VHCP_HTTP'] = array(
	'/a/macros/khmatrix.com/s/' => array( 'code' => 200, 'body' => '<html>ServiceLogin accounts.google.com</html>' ),
	'/macros/s/'                => array( 'code' => 400, 'body' => 'Error 400 (Bad Request)!!1' ),
);
$cd = VHCC_CauNoi::chan_doan();
t( 'ca giới hạn tên miền: chẩn ra là BẢN TRIỂN KHAI BỊ GIỚI HẠN',
	! empty( $cd['ok'] ) && strpos( $cd['ket_luan'], 'BẢN TRIỂN KHAI BỊ GIỚI HẠN' ) !== false,
	isset( $cd['ket_luan'] ) ? $cd['ket_luan'] : $cd );
t( 'và chỉ đúng việc phải làm: Who has access = Anyone',
	strpos( $cd['ket_luan'], 'Anyone' ) !== false );
t( 'và trấn an rằng máy chấm công không bị ảnh hưởng',
	strpos( $cd['ket_luan'], 'KHÔNG bị ảnh hưởng' ) !== false );
t( 'chẩn đoán thử ĐỦ HAI dạng địa chỉ', count( (array) $cd['thu'] ) === 2, count( (array) $cd['thu'] ) );

/* Ca 2 — địa chỉ tốt: dạng rút gọn trả về trang của app. Lỗi phải được chỉ vào bên trong. */
$GLOBALS['VHCP_HTTP'] = array(
	'/macros/s/' => array( 'code' => 200, 'body' => str_repeat( '<div>giao diện chấm công</div>', 50 ) ),
);
$cd = VHCC_CauNoi::chan_doan();
t( 'ca địa chỉ tốt: chẩn ra là "Địa chỉ TỐT"',
	strpos( $cd['ket_luan'], 'Địa chỉ TỐT' ) !== false, $cd['ket_luan'] );
t( 'và kể đúng ba nguyên nhân bên trong (dán / khoá / deploy)',
	strpos( $cd['ket_luan'], 'CauNoiChamCong' ) !== false
	&& strpos( $cd['ket_luan'], 'WEB_KEY' ) !== false
	&& strpos( $cd['ket_luan'], 'New version' ) !== false );

/* Ca 3 — cả hai dạng đều 400: mã triển khai không tồn tại / không phải Web app. */
$GLOBALS['VHCP_HTTP'] = array(
	'/macros/' => array( 'code' => 400, 'body' => 'Error 400 (Bad Request)!!1' ),
);
$cd = VHCC_CauNoi::chan_doan();
t( 'ca cả hai đều bị chối: chẩn ra là mã triển khai sai / không phải Web app',
	strpos( $cd['ket_luan'], 'không phải bản triển khai' ) !== false, $cd['ket_luan'] );
t( 'và bảo so với địa chỉ máy chấm công đang dùng',
	strpos( $cd['ket_luan'], 'máy chấm công đang dùng' ) !== false );

/* Địa chỉ không có dạng .../s/<mã>/exec thì nói ngay, đừng ra mạng. */
update_option( 'vhcc_exec_url', 'https://khmatrix.com/khong-phai-apps-script' );
$cd = VHCC_CauNoi::chan_doan();
t( 'địa chỉ không đúng khuôn thì chối ngay, không gọi mạng',
	empty( $cd['ok'] ) && strpos( $cd['error'], 'mã triển khai' ) !== false, $cd );

/* Chẩn đoán dùng GET — POST vào /exec là chạm đúng cửa máy chấm công đang đẩy vào. */
$cn_noi = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-cau-noi.php' );
$_k = strpos( $cn_noi, 'public static function chan_doan()' );
$khuc_cd = substr( $cn_noi, $_k, strpos( $cn_noi, 'return array( \'ok\' => true, \'ma_trien_khai\'', $_k ) - $_k );
t( 'chẩn đoán chỉ dùng wp_remote_get, TUYỆT ĐỐI không POST vào /exec',
	strpos( $khuc_cd, 'wp_remote_get' ) !== false && strpos( $khuc_cd, 'wp_remote_post' ) === false );

/* Tên miền Workspace phải được NHỚ LẠI lúc cắt — cắt xong là nó mất khỏi địa chỉ, mà chẩn
   đoán cần nó. Trước khi sửa, chẩn đoán rơi về đoán theo tên miền của website; ở đây hai cái
   tình cờ giống nhau nên sai mà không lộ, nên phép thử dùng tên miền KHÁC hẳn. */
delete_option( 'vhcc_exec_mien' );
update_option( 'vhcc_exec_url', 'https://script.google.com/a/macros/kh-noi-bo.vn/s/' . $ID . '/exec' );
VHCC_CauNoi::url();                                   // tự chữa -> phải nhớ tên miền
teq( 'cắt địa chỉ thì NHỚ LẠI tên miền Workspace', 'kh-noi-bo.vn', get_option( 'vhcc_exec_mien' ) );
$GLOBALS['VHCP_HTTP'] = array(
	'/a/macros/kh-noi-bo.vn/s/' => array( 'code' => 200, 'body' => '<html>ServiceLogin</html>' ),
	'/macros/s/'                => array( 'code' => 400, 'body' => 'Error 400 (Bad Request)!!1' ),
);
$cd = VHCC_CauNoi::chan_doan();
t( 'chẩn đoán dùng tên miền đã nhớ, không dùng tên miền của website',
	strpos( $cd['ket_luan'], 'BẢN TRIỂN KHAI BỊ GIỚI HẠN' ) !== false, $cd['ket_luan'] );
$ten_thu = array();
foreach ( (array) $cd['thu'] as $x ) { $ten_thu[] = $x['ten']; }
t( 'và nói rõ đã thử tên miền nào', strpos( implode( ' | ', $ten_thu ), 'kh-noi-bo.vn' ) !== false,
	implode( ' | ', $ten_thu ) );

$GLOBALS['VHCP_HTTP'] = array();
delete_option( 'vhcc_exec_mien' );
update_option( 'vhcc_exec_url', 'https://script.google.com/macros/s/' . $ID . '/exec' );

// ================================== 36. CHUYỂN HƯỚNG CỦA APPS SCRIPT — POST rồi phải GET
/* 🔴 CA THẬT, tốn cả buổi. `GET` vào /exec trả 200 kèm 570 KB giao diện, mà `POST` trả
   `400 Bad Request`. Cùng địa chỉ, khác phương thức. Nguyên nhân: Apps Script trả 302 sang
   `script.googleusercontent.com/macros/echo?...` — địa chỉ LẤY KẾT QUẢ, chỉ nhận GET. Trình
   duyệt và cURL hạ POST xuống GET khi gặp 302; WordPress thì ĐI THEO MÀ GIỮ NGUYÊN POST, nên
   Google chối. Vậy cầu nối phải tự đi theo chuyển hướng. */
update_option( 'vhcc_exec_url', 'https://script.google.com/macros/s/' . $ID . '/exec' );
update_option( 'vhcc_web_key', 'khoa-cau-noi-thu' );

$DICH = 'https://script.googleusercontent.com/macros/echo?user_content_key=abc123';
$GLOBALS['VHD_DA_GUI'] = array();
$GLOBALS['VHD_POST'] = array(
	'/macros/s/' => array( 'code' => 302, 'headers' => array( 'Location' => $DICH ), 'body' => '' ),
	/* Nếu cầu nối POST sang địa chỉ lấy kết quả thì Google chối — mô phỏng đúng thế. */
	'googleusercontent.com' => array( 'code' => 400, 'body' => 'Error 400 (Bad Request)!!1' ),
);
$GLOBALS['VHCP_HTTP'] = array(
	'googleusercontent.com' => array( 'code' => 200, 'body' => '{"ok":true,"data":{"soHam":23}}' ),
);
$kq = VHCC_CauNoi::goi( '__ping' );
t( 'gặp 302 thì tự GET sang Location và đọc được kết quả',
	! empty( $kq['ok'] ) && isset( $kq['data']['soHam'] ) && 23 === $kq['data']['soHam'], $kq );
t( 'POST đi với redirection = 0 (không nhờ WordPress đi theo)',
	isset( $GLOBALS['VHD_DA_GUI'][0]['redirection'] ) && 0 === $GLOBALS['VHD_DA_GUI'][0]['redirection'],
	isset( $GLOBALS['VHD_DA_GUI'][0]['redirection'] ) ? $GLOBALS['VHD_DA_GUI'][0]['redirection'] : 'không ghi' );
teq( 'chỉ POST ĐÚNG MỘT LẦN — không POST lại vào địa chỉ lấy kết quả',
	1, count( $GLOBALS['VHD_DA_GUI'] ) );

/* 307/308 thì theo chuẩn HTTP là GIỮ POST. Apps Script không dùng, nhưng viết đúng thì rẻ. */
$GLOBALS['VHD_DA_GUI'] = array();
$GLOBALS['VHD_POST'] = array(
	'/macros/s/'            => array( 'code' => 307, 'headers' => array( 'Location' => $DICH ), 'body' => '' ),
	'googleusercontent.com' => array( 'code' => 200, 'body' => '{"ok":true,"data":"giu-post"}' ),
);
$GLOBALS['VHCP_HTTP'] = array();
$kq = VHCC_CauNoi::goi( '__ping' );
teq( '307 thì GIỮ POST (không hạ xuống GET)', 'giu-post', isset( $kq['data'] ) ? $kq['data'] : null );
teq( 'và đúng là đã POST hai lần', 2, count( $GLOBALS['VHD_DA_GUI'] ) );

/* Chuyển hướng mà không có Location thì dừng, đừng lặp vô hạn. */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => array( 'code' => 302, 'body' => 'khong co Location' ) );
$GLOBALS['VHCP_HTTP'] = array();
$kq = VHCC_CauNoi::goi( '__ping' );
t( '302 mà thiếu Location thì dừng và báo lỗi, không treo', empty( $kq['ok'] ) );

/* Vòng chuyển hướng phải có trần. */
$GLOBALS['VHD_POST'] = array(
	'/macros/s/'            => array( 'code' => 302, 'headers' => array( 'Location' => $DICH ), 'body' => '' ),
);
$GLOBALS['VHCP_HTTP'] = array(
	'googleusercontent.com' => array( 'code' => 302, 'headers' => array( 'Location' => $DICH ), 'body' => '' ),
);
$GLOBALS['VHCP_DA_GET'] = array();
$kq = VHCC_CauNoi::goi( '__ping' );
t( 'chuyển hướng lòng vòng thì dừng và báo lỗi', empty( $kq['ok'] ) );
/* Phải đo SỐ LƯỢT, không chỉ đòi "có dừng": 100.000 vòng thì cũng dừng. Phép phá bỏ trần
   không bị bắt cho tới khi thêm phép đếm này. */
t( 'và dừng trong vài lượt, không phải vài vạn lượt',
	count( $GLOBALS['VHCP_DA_GET'] ) <= 6, count( $GLOBALS['VHCP_DA_GET'] ) . ' lượt GET' );

$GLOBALS['VHD_POST'] = array(); $GLOBALS['VHCP_HTTP'] = array(); $GLOBALS['VHD_DA_GUI'] = array();

// ================== 37. MỌI FILE MÃ ĐỌC LÚC CHẠY THÌ PHẢI CÓ TRONG BẢN CÀI
/* 🔴 CA THẬT. Em bỏ `apps-script/` ra khỏi zip cho gọn và cho kín, nhưng KHÔNG rà lại xem mã
   có đọc thư mục đó không. Nó có đọc: `VHCC_Trang::ds_ham()` đọc `apps-script/cau-noi.gs` để
   biết giao diện được gọi những hàm nào, và màn Cài đặt hiện nội dung file đó để copy.
   Hậu quả: trang chấm công báo "CC_CHO_PHEP còn RỖNG" trong khi "Thử cầu nối" báo 23 hàm —
   hai câu không thể cùng đúng — và câu báo lỗi chỉ anh Thắng đi sửa đúng cái đang chạy tốt.

   Phép thử này soi CẢ HAI chiều nên loại lỗi đó không quay lại được:
   danh sách loại trừ của trình đóng gói phải khớp với những gì mã thật sự đọc. */
$zip_ra = array();
exec( 'cd ' . escapeshellarg( $goc ) . ' && bash tools/build-plugin-zip.sh cham-cong 2>&1', $zip_ra, $zip_ma );
t( 'đóng gói được bản cài', $zip_ma === 0, implode( "\n", $zip_ra ) );

$ds_zip = array();
exec( 'unzip -Z1 ' . escapeshellarg( $goc . '/dist/vhcp-cham-cong.zip' ) . ' 2>/dev/null', $ds_zip );
$trong_zip = array();
foreach ( $ds_zip as $d ) { $trong_zip[ preg_replace( '#^vhcp-cham-cong/#', '', trim( $d ) ) ] = 1; }
t( 'đọc được danh sách tệp trong zip', count( $trong_zip ) > 10, count( $trong_zip ) );

/* Mọi đường `VHCC_DIR . '…'` trong mã — đó đúng là danh sách file plugin đọc lúc chạy. */
$can = array();
foreach ( glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ) as $f ) {
	if ( preg_match_all( "/VHCC_DIR\s*\.\s*'([^']+)'/", file_get_contents( $f ), $mm ) ) {
		foreach ( $mm[1] as $x ) { $can[ $x ] = basename( $f ); }
	}
}
t( 'có ít nhất một file được đọc lúc chạy (không thì phép thử này vô nghĩa)', count( $can ) > 0 );
$thieu = array();
foreach ( $can as $duong => $boi ) {
	if ( ! isset( $trong_zip[ $duong ] ) ) { $thieu[] = $duong . ' (đọc ở ' . $boi . ')'; }
}
t( 'KHÔNG file nào mã đọc lúc chạy mà lại thiếu trong bản cài',
	count( $thieu ) === 0, implode( ' | ', $thieu ) );

/* `goc/` thì phải VẮNG — nó là bản gốc Code.gs, không chạy gì mà đọc được từ web. */
$co_goc = 0;
foreach ( array_keys( $trong_zip ) as $d ) { if ( strpos( $d, 'goc/' ) === 0 ) { $co_goc++; } }
teq( 'thư mục goc/ vẫn bị loại khỏi bản cài', 0, $co_goc );

/* Hai câu báo lỗi phải TÁCH RA: thiếu file trong bản cài ≠ chưa khai hàm bên Apps Script. */
$tr2 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang.php' );
t( 'thiếu file .gs thì nói "BẢN CÀI THIẾU FILE", không nói CC_CHO_PHEP rỗng',
	strpos( $tr2, 'BẢN CÀI THIẾU FILE' ) !== false );
t( 'và nói rõ không phải người dùng làm sai', strpos( $tr2, 'KHÔNG phải anh làm' ) !== false );
t( 'vẫn giữ câu cho ca thật sự rỗng', strpos( $tr2, 'CC_CHO_PHEP' ) !== false );

// ============================== 38. MÀN CÀI ĐẶT PHẢI TRẢ LỜI ĐƯỢC "GÕ PIN NÀO"
/* Màn đăng nhập chỉ nói "PIN không đúng hoặc chưa được cấp" — đúng nhưng vô dụng, vì không
   biết PIN nào mới đúng. Màn Cài đặt phải nói được: ai vào được, và PIN dài mấy số.
   ⚠️ KHÔNG được in PIN. Ảnh màn hình đi khắp nơi — đã mất một khoá cầu nối đúng vì một ảnh. */
vhcc_dung_bang();
$GLOBALS['VHCP_CO_QUYEN'] = true;
update_option( 'vhcc_nguon_nguoidung', 'chung' );
global $wpdb;
$bang_cfg = $wpdb->prefix . 'vhcp_cfg';
$wpdb->exec_raw( "DELETE FROM $bang_cfg WHERE bang='CH_NguoiDung'" );
$nguoi = array(
	array( 'Bà Kế Toán',   '654321', 'Kế toán cá nhân', 'TUTU_BT' ),   // PIN 6 số, vào được
	array( 'Anh Quản Lý',  '12345678', 'Quản lý',       '' ),          // PIN 8 số, vào được
	array( 'Chị Số Không', '123',     'Kế toán NCC',    'TUTU_BT' ),   // 0123 bị Sheets xén -> 3 số
	array( 'Em Nhân Viên', '4321',    'Nhân viên',      'TUTU_BT' ),   // đúng khuôn nhưng không đủ quyền
	array( 'Bác Chưa Cấp', '',        'Quản lý',        '' ),          // chưa có PIN
);
$stt = 0;
foreach ( $nguoi as $x ) {
	$wpdb->insert( $bang_cfg, array( 'bang' => 'CH_NguoiDung', 'stt' => ++$stt,
		'cols' => wp_json_encode( $x ) ) );
}

ob_start(); VHCC_Admin::page(); $h_cd = ob_get_clean();

t( 'liệt kê người VÀO ĐƯỢC', strpos( $h_cd, 'Bà Kế Toán' ) !== false
	&& strpos( $h_cd, 'Anh Quản Lý' ) !== false );
/* Bảng nay liệt kê MỌI người có PIN — ai cũng đăng nhập được, khác nhau ở bậc. Giấu nhân
   viên đi là giấu mất câu trả lời cho "PIN của người này dài mấy số". */
t( 'liệt kê CẢ nhân viên (ai cũng vào được, khác nhau ở bậc)',
	strpos( $h_cd, 'Em Nhân Viên' ) !== false, $h_cd );
t( 'nói PIN dài mấy số', strpos( $h_cd, '6 số' ) !== false && strpos( $h_cd, '8 số' ) !== false );

/* 🔴 Phép thử quan trọng nhất của mục này: TUYỆT ĐỐI không in PIN ra màn hình. */
foreach ( array( '654321', '12345678', '4321' ) as $pin_that ) {
	t( "màn Cài đặt KHÔNG in PIN $pin_that ra", strpos( $h_cd, $pin_that ) === false );
}

t( 'bắt được PIN bị xén còn 3 số (số 0 ở đầu)',
	strpos( $h_cd, 'Chị Số Không' ) !== false && strpos( $h_cd, 'KHÔNG DÙNG ĐƯỢC' ) !== false );
t( 'và giải thích đúng cái bẫy Google Sheets xén số 0',
	strpos( $h_cd, 'số 0 ở đầu' ) !== false && strpos( $h_cd, 'Văn bản' ) !== false );
t( 'bắt được người CHƯA CÓ PIN', strpos( $h_cd, 'Bác Chưa Cấp' ) !== false
	&& strpos( $h_cd, 'chưa có PIN' ) !== false );

/* Và cổng đăng nhập thật phải hành xử đúng y như bảng nói. */
$kq = VHCC_Auth::login( '654321' );
t( 'PIN 6 số của Kế toán: vào được', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '123' );
t( 'PIN bị xén còn 3 số: bị chối vì sai khuôn',
	empty( $kq['ok'] ) && strpos( $kq['error'], '4–8' ) !== false, $kq );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '4321' );
t( 'PIN của Nhân viên: VÀO ĐƯỢC, ở bậc đáy', ! empty( $kq['ok'] ), $kq );
teq( 'và đúng bậc Nhân viên', VHCC_Vai::NV, VHCC_Vai::ma( $kq['role'] ) );
VHCC_Auth::mo_khoa();

$wpdb->exec_raw( "DELETE FROM $bang_cfg WHERE bang='CH_NguoiDung'" );

/* ============ 38b. ĐUÔI ".0" CỦA BẢNG TÍNH — LỖI ĐÃ KHOÁ CỬA TOÀN BỘ NGƯỜI DÙNG THẬT
 *
 * 🔴 Ảnh màn Cài đặt trên khmatrix.com ngày 22/08/2026: Admin "8 ký tự — không dùng được",
 *    Kế toán "6 ký tự — không dùng được". KHÔNG AI đăng nhập được trang chấm công, mà nhìn
 *    vào thì vẫn thấy "có PIN".
 *
 *    Vì sao: Google Sheets coi PIN là SỐ, nên `571394` xuất ra thành `"571394.0"` — tám KÝ TỰ
 *    nhưng không phải tám CHỮ SỐ. App chi phí rửa chỗ này từ lâu (VHCP_Util::pin_sach) nhưng
 *    cổng chấm công đọc THẲNG cột JSON của bảng `vhcp_cfg`, đi vòng qua phép rửa đó. Hai nơi
 *    đọc cùng một dữ liệu, một nơi rửa, một nơi không — và nơi không rửa là CỔNG ĐĂNG NHẬP.
 */
$wpdb->exec_raw( "DELETE FROM $bang_cfg WHERE bang='CH_NguoiDung'" );
$stt = 0;
foreach ( array(
	array( 'Anh Đuôi Chấm',  '571394.0', 'Admin',           'TUTU_BT' ),   // 8 ký tự -> 6 số
	array( 'Chị Đuôi Chấm',  '4471.0',   'Kế toán cá nhân', 'TUTU_BT' ),   // 6 ký tự -> 4 số
) as $x ) {
	$wpdb->insert( $bang_cfg, array( 'bang' => 'CH_NguoiDung', 'stt' => ++$stt,
		'cols' => wp_json_encode( $x ) ) );
}

$u_do = VHCC_Auth::users();
$pin_do = array();
foreach ( $u_do as $x ) { $pin_do[ $x['ten'] ] = $x['pin']; }
teq( 'rửa đuôi ".0" lúc ĐỌC người dùng (8 ký tự -> 6 số)', '571394', $pin_do['Anh Đuôi Chấm'] );
teq( 'rửa đuôi ".0" lúc ĐỌC người dùng (6 ký tự -> 4 số)', '4471', $pin_do['Chị Đuôi Chấm'] );

/* ⚠️ BẪY THỨ TỰ. Nếu bỏ ký tự lạ TRƯỚC rồi mới cắt đuôi thì `"571394.0"` thành `"5713940"` —
   BẢY chữ số, vẫn khớp luật 4–8, nên không báo lỗi ở đâu cả, chỉ là không ai gõ trúng. Sai âm
   thầm còn tệ hơn sai ồn ào, nên phép thử này phải nêu đích danh con số sai. */
t( 'KHÔNG được nuốt dấu chấm thành chữ số', $pin_do['Anh Đuôi Chấm'] !== '5713940' );
teq( 'rửa thẳng: cắt đuôi trước, bỏ ký tự lạ sau', '571394', VHCC_Auth::pin_sach( '571394.0' ) );
teq( 'đuôi nhiều số 0 cũng cắt', '4471', VHCC_Auth::pin_sach( '4471.000' ) );
teq( 'giữ nguyên số 0 ĐỨNG ĐẦU — đó là PIN thật của người ta', '0123', VHCC_Auth::pin_sach( '0123' ) );
teq( 'PIN sạch sẵn thì không đụng', '654321', VHCC_Auth::pin_sach( '654321' ) );
teq( 'trống vẫn là trống', '', VHCC_Auth::pin_sach( '' ) );

/* Và cổng thật phải mở — đây mới là thứ anh Thắng cần. */
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '571394' );
t( 'Admin có PIN dính đuôi ".0" VẪN ĐĂNG NHẬP ĐƯỢC', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();

/* Bảng ở màn Cài đặt cũng phải hết đỏ — nếu còn "không dùng được" thì người đọc vẫn tưởng
   mình phải đi sửa tay 21 dòng. */
ob_start(); VHCC_Admin::page(); $h_do = ob_get_clean();
t( 'màn Cài đặt hết báo "không dùng được" cho PIN dính đuôi',
	strpos( $h_do, 'không dùng được' ) === false, $h_do );
t( 'và vẫn KHÔNG in PIN ra', strpos( $h_do, '571394' ) === false );

$wpdb->exec_raw( "DELETE FROM $bang_cfg WHERE bang='CH_NguoiDung'" );

/* Đường KÉO HỒ SƠ từ app gốc dính đúng bẫy đó: `pin_may` đẩy xuống máy chấm công mà mang đuôi
   ".0" là nhân viên gõ mãi không mở được cửa. */
teq( 'kéo hồ sơ cũng rửa PIN máy', '1234', VHCC_Auth::pin_sach( '1234.0' ) );
$src_keo = file_get_contents( VHCC_DIR . 'includes/class-vhcc-keo.php' );
t( 'đường kéo hồ sơ có gọi phép rửa PIN',
	strpos( $src_keo, 'pin_sach' ) !== false && strpos( $src_keo, '\'pin_may\' === $cot' ) !== false );

/* PIN đã bị LỘ thì phải chặn, dù nó không "dễ đoán". `888888` và `859624` đều đã ra ngoài
   trong quá trình làm việc này (một cái là PIN mặc định của app gốc, một cái hiện trong ảnh
   màn hình gửi qua chat). Một mật khẩu đã ra ngoài thì mạnh hay yếu không còn nghĩa gì. */
foreach ( array( '888888', '859624' ) as $pin_lo ) {
	t( "không cho ĐẶT lại PIN đã lộ $pin_lo", VHCC_Quyen::pin_hop_le( $pin_lo ) !== '' );
}
/* Nhưng KHÔNG chặn ở chỗ đăng nhập — khoá người ta ra khỏi hệ thống của chính họ mà không báo
   trước là tệ hơn. Bộ chặn chỉ được gọi lúc đặt/đổi mật khẩu. */
$q_src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-quyen.php' );
$_i = strpos( $q_src, 'function pin_hop_le' );
t( 'phép chặn PIN nằm ở chỗ đặt mật khẩu, không nằm trong đường đăng nhập',
	$_i !== false && strpos( file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-auth.php' ),
		'PIN_CAM' ) === false );
t( 'và giải thích rõ hai nhóm lý do (dễ đoán vs đã bị lộ)',
	strpos( $q_src, 'ĐÃ BỊ LỘ' ) !== false );

// ================================ 39. KÉO DỮ LIỆU CŨ TỪ APP GỐC (đường B)
/* Anh Thắng chọn kéo qua cầu nối thay vì dán tay. Đây là đường dữ liệu THẬT đi qua, nên mục
   này thử kỹ nhất: kéo lại nhiều lần, giờ chỉ nới không thu hẹp, số tiền kiểu Việt, ngày kiểu
   dd/mm/yyyy, và MỘT CHIỀU — không hàm nào ghi lên sheet. */
vhcc_dung_bang();
update_option( 'vhcc_exec_url', 'https://script.google.com/macros/s/' . $ID . '/exec' );
update_option( 'vhcc_web_key', 'khoa-thu' );
VHCC_Keo::xoa_tien_do();

/** Bộ giả lập app gốc: trả theo tên hàm trong thân POST. */
function vhcc_app_goc( $ban ) {
	return function ( $args ) use ( $ban ) {
		$than = json_decode( isset( $args['body'] ) ? $args['body'] : '{}', true );
		$fn   = isset( $than['fn'] ) ? $than['fn'] : '';
		if ( ! array_key_exists( $fn, $ban ) ) {
			return array( 'code' => 200, 'body' => wp_json_encode( array( 'ok' => false,
				'error' => 'Project này không có hàm "' . $fn . '"' ) ) );
		}
		$d = $ban[ $fn ];
		if ( is_callable( $d ) ) { $d = call_user_func( $d, $than ); }
		return array( 'code' => 200, 'body' => wp_json_encode( array( 'ok' => true, 'data' => $d ) ) );
	};
}

/* ---- Nhân sự ---- */
$ho_so_app = array(
	array( 'employeeNo' => 'NV001', 'name' => 'Trần Văn A', 'station' => 'TUTU_BT',
		'phone' => '0900000001', 'dob' => '15/03/1998', 'cccd' => '052123456789',
		'position' => 'Nhân viên', 'startDate' => '2025-01-06', 'baseSalary' => '13.000.000',
		'nhiemVu' => 'Thu Tiền', 'coSoPhu' => 'POSH_HCM' ),
	array( 'employeeNo' => 'NV002', 'name' => 'Lê Thị B', 'station' => 'POSH_HCM',
		'baseSalary' => '7500000', 'startDate' => '' ),
	array( 'employeeNo' => '',      'name' => 'Không mã' ),        // phải bị bỏ
	array( 'employeeNo' => 'NV003', 'name' => '' ),                // phải bị bỏ
);
$GLOBALS['VHD_POST'] = array( '/macros/s/' => vhcc_app_goc( array( 'getEmployees' => $ho_so_app ) ) );

$xem = VHCC_Keo::keo_nhan_su( true );
t( 'xem trước hồ sơ: đọc được', ! empty( $xem['ok'] ), $xem );
teq( 'xem trước: 2 hồ sơ sẽ thêm', 2, $xem['them'] );
teq( 'xem trước: bỏ 2 dòng thiếu mã/tên', 2, count( $xem['bo'] ) );
teq( 'XEM TRƯỚC KHÔNG GHI GÌ VÀO MySQL', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) ) );

$kq = VHCC_Keo::keo_nhan_su( false );
teq( 'kéo thật: thêm 2', 2, $kq['them'] );
$nv1 = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='NV001'", ARRAY_A );
teq( 'họ tên vào đúng', 'Trần Văn A', $nv1['ho_ten'] );
teq( 'ngày sinh dd/mm/yyyy -> yyyy-mm-dd', '1998-03-15', $nv1['ngay_sinh'] );
teq( 'ngày vào làm dạng ISO giữ nguyên', '2025-01-06', $nv1['ngay_vao_lam'] );
/* 🔴 `13.000.000` là mười ba triệu. Đây đúng là cái lỗi đã mắc một lần ở chỗ khác trong việc
   này (đọc thành 13 đồng), nên phải có phép thử ở CẢ đường kéo. */
teq( 'lương 13.000.000 đọc thành mười ba triệu, KHÔNG phải 13', 13000000.0, (float) $nv1['luong_co_ban'] );
teq( 'cơ sở phụ giữ nguyên', 'POSH_HCM', $nv1['coso_phu'] );
$nv2 = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='NV002'", ARRAY_A );
teq( 'lương kiểu số trơn vẫn đúng', 7500000.0, (float) $nv2['luong_co_ban'] );
t( 'ngày rỗng thì để NULL, KHÔNG đoán', null === $nv2['ngay_vao_lam'], $nv2['ngay_vao_lam'] );

/* Kéo LẦN HAI: phải là cập nhật, không nhân đôi. */
$kq = VHCC_Keo::keo_nhan_su( false );
teq( 'kéo lần hai: 0 thêm', 0, $kq['them'] );
teq( 'kéo lần hai: 2 cập nhật', 2, $kq['sua'] );
teq( 'tổng hồ sơ vẫn là 2 — không nhân đôi', 2,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) ) );

/* App gốc trả rỗng -> nói rõ nghi PIN, đừng báo "thành công 0 dòng". */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => vhcc_app_goc( array( 'getEmployees' => array() ) ) );
$kq = VHCC_Keo::keo_nhan_su( true );
t( '0 hồ sơ thì nghi PIN admin, không báo thành công',
	empty( $kq['ok'] ) && strpos( $kq['error'], 'VHCC_PIN_ADMIN' ) !== false, $kq );

/* ---- Chấm công cũ ---- */
$cc_app = array(
	'ccDsCoSoXuat'   => array( 'ok' => true, 'daQuet' => true, 'ds' => array( 'TUTU_BT', 'POSH_HCM' ) ),
	'ccXuatChamCong' => function ( $than ) {
		$cs = isset( $than['args'][1] ) ? $than['args'][1] : '';
		$th = isset( $than['args'][2] ) ? $than['args'][2] : '';
		if ( 'TUTU_BT' !== $cs || '08-2026' !== $th ) {
			return array( 'ok' => true, 'khongCoSheet' => true, 'station' => $cs, 'rows' => array() );
		}
		return array( 'ok' => true, 'station' => $cs, 'thang' => '2026-08', 'rows' => array(
			array( 'ma' => 'NV001', 'ten' => 'Trần Văn A', 'ngay' => array(
				array( 'date' => '2026-08-03', 'vao' => '08:00:00', 'ra' => '17:30:00' ),
				array( 'date' => '2026-08-04', 'vao' => '08:05:00', 'ra' => '' ),
				array( 'date' => '2026-08-05', 'vao' => 'xx:yy',    'ra' => '17:00:00' ),
			) ),
			array( 'ma' => 'NV001-TC', 'ten' => 'Trần Văn A', 'ngay' => array(
				array( 'date' => '2026-08-03', 'vao' => '18:00:00', 'ra' => '21:00:00' ),
			) ),
		) );
	},
);
$GLOBALS['VHD_POST'] = array( '/macros/s/' => vhcc_app_goc( $cc_app ) );

$r = VHCC_Keo::ds_coso();
teq( 'đọc được danh sách cơ sở từ app gốc', 2, count( $r['ds'] ) );

$xem = VHCC_Keo::keo_thang( 'TUTU_BT', '08-2026', true );
teq( 'xem trước: 2 lượt người', 2, $xem['nguoi'] );
/* Đếm tay: 08:00 + 17:30 + 08:05 + 17:00 + 18:00 + 21:00 = 6 giờ đọc được; `xx:yy` bị bỏ.
   (Bản đầu em ghi 5 — em đếm sai, mã đúng.) */
teq( 'xem trước: 6 giờ đọc được (1 giờ rác bị bỏ)', 6, $xem['luot'] );
teq( 'và kể ra giờ rác', 1, count( $xem['bo'] ) );
teq( 'XEM TRƯỚC KHÔNG GHI GÌ', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );

VHCC_Keo::keo_thang( 'TUTU_BT', '08-2026', false );
$h = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE coso='TUTU_BT' AND ngay='2026-08-03' AND ma_nv='NV001' AND hau_to=''", ARRAY_A );
teq( 'giờ vào vào đúng ô', '08:00:00', VHCC_DB::hhmmss( $h['gio_vao_giay'] ) );
teq( 'giờ ra vào đúng ô', '17:30:00', VHCC_DB::hhmmss( $h['gio_ra_giay'] ) );
teq( 'nguồn ghi là "sheet" để phân biệt với lượt máy đẩy trực tiếp', 'sheet', $h['nguon'] );
/* Hậu tố TC phải thành HÀNG RIÊNG, không trộn vào hàng chính — nếu trộn thì tăng cường bị
   cộng vào giờ làm chính và bảng lương sai. */
$tc = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE coso='TUTU_BT' AND ngay='2026-08-03' AND ma_nv='NV001' AND hau_to='TC'", ARRAY_A );
t( 'hậu tố TC tách thành hàng riêng', is_array( $tc ), $tc );
teq( 'giờ của hàng TC đúng', '18:00:00', VHCC_DB::hhmmss( $tc['gio_vao_giay'] ) );
$mot_gio = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE ngay='2026-08-04' AND ma_nv='NV001'", ARRAY_A );
t( 'ngày chỉ có giờ vào thì giờ ra để trống, không tự điền',
	null === $mot_gio['gio_ra_giay'], $mot_gio['gio_ra_giay'] );

/* 🔴 KÉO LẠI LẦN HAI — không sinh dòng, không đổi giờ. */
$truoc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) );
VHCC_Keo::keo_thang( 'TUTU_BT', '08-2026', false );
teq( 'kéo lại lần hai: số hàng không đổi', $truoc,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );
$h2 = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE ngay='2026-08-03' AND ma_nv='NV001' AND hau_to=''", ARRAY_A );
teq( 'kéo lại: giờ vào không đổi', $h['gio_vao_giay'], $h2['gio_vao_giay'] );
teq( 'kéo lại: giờ ra không đổi', $h['gio_ra_giay'], $h2['gio_ra_giay'] );

/* 🔴 KHÔNG THU HẸP: MySQL đã có cặp giờ RỘNG hơn (máy đẩy trực tiếp), sheet chỉ có một nửa —
   kéo về KHÔNG được cắt bớt. Đây là lý do phải dùng lại ghi_gio() chứ không viết UPDATE riêng. */
$wpdb->query( "UPDATE " . VHCC_DB::t( 'cham_cong' )
	. " SET gio_vao_giay=" . VHCC_DB::giay( '07:30:00' ) . ", gio_ra_giay=" . VHCC_DB::giay( '19:00:00' )
	. " WHERE ngay='2026-08-03' AND ma_nv='NV001' AND hau_to=''" );
VHCC_Keo::keo_thang( 'TUTU_BT', '08-2026', false );
$h3 = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE ngay='2026-08-03' AND ma_nv='NV001' AND hau_to=''", ARRAY_A );
teq( 'kéo về KHÔNG thu hẹp giờ vào đã sớm hơn', '07:30:00', VHCC_DB::hhmmss( $h3['gio_vao_giay'] ) );
teq( 'kéo về KHÔNG thu hẹp giờ ra đã muộn hơn', '19:00:00', VHCC_DB::hhmmss( $h3['gio_ra_giay'] ) );

/* Cơ sở không có sheet: không phải lỗi, phải đi tiếp. */
$kq = VHCC_Keo::keo_thang( 'POSH_HCM', '08-2026', false );
t( 'cơ sở không có sheet: coi là bỏ qua, không phải lỗi',
	! empty( $kq['ok'] ) && ! empty( $kq['khong_co_sheet'] ), $kq );

/* Chưa dán bản CauNoiChamCong mới -> nói rõ, đừng để "lỗi không rõ". */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => vhcc_app_goc( array() ) );
$kq = VHCC_Keo::keo_thang( 'TUTU_BT', '08-2026', true );
t( 'chưa dán hàm mới thì nói rõ tên hàm còn thiếu',
	empty( $kq['ok'] ) && strpos( $kq['error'], 'ccXuatChamCong' ) !== false, $kq );

/* ---- Khoảng tháng ---- */
teq( 'ds_thang: một tháng', array( '08-2026' ), VHCC_Keo::ds_thang( '08-2026', '08-2026' ) );
teq( 'ds_thang: bắc qua năm', array( '11-2025', '12-2025', '01-2026' ),
	VHCC_Keo::ds_thang( '11-2025', '01-2026' ) );
teq( 'ds_thang: ngược thứ tự -> rỗng', array(), VHCC_Keo::ds_thang( '03-2026', '01-2026' ) );
teq( 'ds_thang: quá 36 tháng -> rỗng (chặn gõ nhầm năm)', array(),
	VHCC_Keo::ds_thang( '01-2016', '01-2026' ) );
teq( 'ds_thang: sai khuôn -> rỗng', array(), VHCC_Keo::ds_thang( '2026-08', '2026-09' ) );

/* ---- MỘT CHIỀU: cầu nối KHÔNG được khai hàm ghi nhân sự / chấm công nào ---- */
$gs_cn = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/apps-script/cau-noi.gs' );
preg_match( '/CC_CHO_PHEP\s*=\s*\[(.*?)\]/s', $gs_cn, $mcp );
preg_match_all( "/'([A-Za-z_][A-Za-z0-9_]*)'/", $mcp[1], $mds );
$ds_ham_cn = $mds[1];
t( 'cầu nối khai getEmployees (đọc hồ sơ)', in_array( 'getEmployees', $ds_ham_cn, true ) );
t( 'cầu nối khai hai hàm xuất chấm công',
	in_array( 'ccXuatChamCong', $ds_ham_cn, true ) && in_array( 'ccDsCoSoXuat', $ds_ham_cn, true ) );
$ghi_cam = array( 'saveEmployee', 'luuHoSo', 'deleteEmployee', 'xoaHoSo', 'ghiChamCong',
	'setChamCong', 'suaGioChamCong', 'luuNhanSu' );
$lot_ghi = array_intersect( $ghi_cam, $ds_ham_cn );
t( 'cầu nối KHÔNG khai hàm nào GHI nhân sự / chấm công lên sheet (một chiều)',
	count( $lot_ghi ) === 0, implode( ', ', $lot_ghi ) );
/* Hai hàm xuất phải gọi lại `_bangCongTho` của app, không tự đọc sheet lần thứ hai. */
/* Hàm xuất CHẤM CÔNG phải dùng lại `_bangCongTho` — cách đọc sheet CS_ có nhiều bẫy (một cơ sở
   gộp nhiều sheet cho ca đêm, hàng dán ngược), viết vòng đọc thứ hai là sớm muộn hai bên ra hai
   số khác nhau cho cùng một tháng.
   ⚠️ Chỉ soi TRONG THÂN hàm đó, không soi cả tệp: `ccXuatPhanQuyen` thêm sau có đọc sheet trực
      tiếp, và đó là đúng — app gốc không có hàm nào trả về sổ phân quyền để dùng lại
      (`_loginResolve` đọc thẳng trong thân nó). Bản đầu soi cả tệp nên trượt oan. */
$_tu_cc  = strpos( $gs_cn, 'function ccXuatChamCong(' );
$_den_cc = strpos( $gs_cn, "\nfunction ", $_tu_cc + 10 );
$than_cc = substr( $gs_cn, $_tu_cc, ( false === $_den_cc ? strlen( $gs_cn ) : $_den_cc ) - $_tu_cc );
t( 'hàm xuất chấm công dùng lại _bangCongTho, không tự đọc sheet',
	strpos( $than_cc, '_bangCongTho(station, prefix)' ) !== false
	&& strpos( $than_cc, 'getRange' ) === false );
t( 'hàm xuất chốt quyền Admin/Quản lý', substr_count( $gs_cn, "u.role !== ROLE.QUAN_LY" ) >= 2 );

$GLOBALS['VHD_POST'] = array();
VHCC_Keo::xoa_tien_do();

/* Câu "Phiên đăng nhập không hợp lệ" của app gốc phải được DỊCH LẠI. Nguyên văn nó đúng cho
   người ngồi trước app gốc, nhưng ở đây không ai đăng nhập cả — cầu nối gọi máy-với-máy bằng
   PIN admin trong wp-config.php. Ai đọc câu nguyên văn sẽ đi đăng nhập lại wp-admin, việc chẳng
   liên quan gì. Anh Thắng đã mất một vòng đúng vì câu này. */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => array( 'code' => 200, 'body' => wp_json_encode(
	array( 'ok' => false, 'error' => 'Phiên đăng nhập không hợp lệ, hãy đăng nhập lại.' ) ) ) );
/* Tự khai PIN ở đây. Trước kia mục này ăn ké PIN mà mục 21 để lại — mục 21 nay không đụng PIN
   admin nữa (đường máy bỏ Apps Script), và bài kiểm dựa vào thứ tự chạy là bài kiểm hỏng lặng lẽ. */
update_option( 'vhcc_pin_admin', '888888' );
$kq = VHCC_CauNoi::goi( 'getEmployees', array( '888888' ) );
t( 'dịch lỗi phiên đăng nhập thành: app gốc không nhận PIN admin',
	empty( $kq['ok'] ) && strpos( $kq['error'], 'VHCC_PIN_ADMIN' ) !== false, $kq );
t( 'và nói rõ nó nằm ở wp-config.php, phải khớp sheet PhanQuyen',
	strpos( $kq['error'], 'wp-config.php' ) !== false && strpos( $kq['error'], 'PhanQuyen' ) !== false );
t( 'và nói rõ KHÔNG liên quan đăng nhập wp-admin (chống đi sai hướng)',
	strpos( $kq['error'], 'Không liên quan' ) !== false );
t( 'KHÔNG in PIN ra câu lỗi, chỉ nói độ dài',
	strpos( $kq['error'], '888888' ) === false && strpos( $kq['error'], 'ký tự' ) !== false );
/* 🔴 CÂU LỖI PHẢI LÀ VĂN BẢN TRƠN. Chỗ hiện nó dùng esc_html — đúng, vì chuỗi lỗi có thể mang
   nguyên văn lời của app gốc. Nhét thẻ vào đây là màn hình in ra đúng chữ "<code>". Đã xảy ra. */
t( 'câu lỗi không chứa thẻ HTML (chỗ hiện nó thoát HTML)',
	strpos( $kq['error'], '<' ) === false, $kq['error'] );
/* PIN app gốc luôn ĐÚNG 6 chữ số — dài khác 6 thì nói luôn là chắc chắn sai. */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => array( 'code' => 200, 'body' => wp_json_encode(
	array( 'ok' => false, 'error' => 'Phiên đăng nhập không hợp lệ, hãy đăng nhập lại.' ) ) ) );
update_option( 'vhcc_pin_admin', '12345678' );          // 8 ký tự — chắc chắn sai
$kq8 = VHCC_CauNoi::goi( 'getEmployees', array( '12345678' ) );
t( 'PIN dài khác 6 thì nói thẳng là chắc chắn sai',
	strpos( $kq8['error'], 'ĐÚNG 6 CHỮ SỐ' ) !== false, $kq8['error'] );
update_option( 'vhcc_pin_admin', '123457' );            // 6 số — có thể đúng, đừng khẳng định sai
$kq6 = VHCC_CauNoi::goi( 'getEmployees', array( '123457' ) );
t( 'PIN đúng 6 số thì KHÔNG khẳng định sai (chỉ là app gốc không nhận)',
	strpos( $kq6['error'], 'chắc chắn sai' ) === false, $kq6['error'] );
delete_option( 'vhcc_pin_admin' );

/* 🔴 Màn hình phải IN RA câu kết quả của lệnh, không nuốt thành "Đã lưu.".
   Lệnh kéo trả về số liệu (thêm bao nhiêu, còn bao nhiêu cặp phải bấm tiếp) — nuốt mất là
   người bấm không biết lệnh đã làm gì, mà đó là toàn bộ giá trị của nút đó. */
$ad3 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
teq( 'cả BA chỗ hiện kết quả đều in thong_bao khi có (nhân sự · kéo · ve_bao dùng chung)', 3,
	substr_count( $ad3, "! empty( \$b['thong_bao'] ) ? \$b['thong_bao'] : 'Đã lưu.'" ) );
t( 'màn máy dùng chung bộ hiện kết quả, không tự vẽ lại',
	strpos( $ad3, 'self::ve_bao( $bao )' ) !== false );
/* Lỗi khác thì giữ NGUYÊN VĂN — dịch bừa là che mất câu thật của app gốc. */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => array( 'code' => 200, 'body' => wp_json_encode(
	array( 'ok' => false, 'error' => 'Không tìm thấy sheet chấm công cho cơ sở "XYZ".' ) ) ) );
$kq = VHCC_CauNoi::goi( 'ccXuatChamCong', array( '1', 'XYZ', '08-2026' ) );
teq( 'lỗi khác của app gốc thì giữ nguyên văn',
	'Không tìm thấy sheet chấm công cho cơ sở "XYZ".', $kq['error'] );
$GLOBALS['VHD_POST'] = array();

/* Màn Cài đặt phải nói được PIN admin đã khai chưa — nếu không, câu lỗi trên không dẫn về đâu. */
$ad2 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
t( 'màn Cài đặt hiện trạng thái PIN admin', strpos( $ad2, 'PIN admin gọi app gốc' ) !== false );
t( 'và phân biệt rõ với PIN đăng nhập trang chấm công và khoá cầu nối',
	strpos( $ad2, 'KHÔNG phải PIN đăng nhập' ) !== false );
ob_start(); VHCC_Admin::page(); $h_pin = ob_get_clean();
t( 'màn Cài đặt KHÔNG in giá trị PIN admin ra',
	strpos( $h_pin, (string) VHCC_May::pin() ) === false || '' === VHCC_May::pin() );

/* Số bản phải hiện ở MỌI màn của plugin, không riêng màn Cài đặt. Câu hỏi tốn thời gian nhất
   suốt buổi cài là "đang chạy bản nào" — mà đó là câu chỉ trả lời được nếu bỏ màn đang làm để
   sang màn khác xem. Đã có lần cả hai bên nhìn một màn hình cũ và đi tìm lỗi đã sửa xong. */
$chinh_pl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
t( 'dải số bản móc vào in_admin_header (một chỗ, không sửa 11 hàm vẽ)',
	strpos( $chinh_pl, "add_action( 'in_admin_header', array( 'VHCC_Admin', 'dai_ban' ) );" ) !== false );
foreach ( array( 'vhcc', 'vhcc-nhan-su', 'vhcc-luong', 'vhcc-may' ) as $trang_thu ) {
	$_GET['page'] = $trang_thu;
	ob_start(); VHCC_Admin::dai_ban(); $h_ban = ob_get_clean();
	t( "màn $trang_thu hiện số bản", strpos( $h_ban, VHCC_VERSION ) !== false, $h_ban );
}
/* Và KHÔNG chen vào màn của plugin khác — dải này là của plugin chấm công. */
$_GET['page'] = 'vhcp-chi-phi';
ob_start(); VHCC_Admin::dai_ban(); $h_ban = ob_get_clean();
teq( 'không chen dải vào màn của plugin khác', '', $h_ban );
unset( $_GET['page'] );

/* Thứ tự trên màn Nhân sự = thứ tự việc phải làm. Khi MySQL còn trống thì KÉO là việc đầu
   tiên; bản đầu em để nó ở cuối trang dưới tám mục khác, anh Thắng cuộn không tới nên bấm nhầm
   "Xem trước" của ô dán tay (dán rỗng nên ra 0) rồi tưởng lệnh kéo không chạy. */
$ad4 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
$vt_keo  = strpos( $ad4, "echo '<h2>Kéo dữ liệu cũ từ app gốc</h2>'" );
$vt_ds   = strpos( $ad4, "echo '<h2>Danh sách (' . count( \$ds ) . ')</h2>'" );
$vt_dan  = strpos( $ad4, "Nhập nhân sự hàng loạt" );
t( 'khối KÉO nằm TRÊN danh sách hồ sơ', $vt_keo !== false && $vt_ds !== false && $vt_keo < $vt_ds );
t( 'và nằm TRÊN ô dán tay', $vt_keo !== false && $vt_dan !== false && $vt_keo < $vt_dan );

/* Bảng trống thì phải chỉ thẳng việc đầu tiên, không để người dùng tự đoán. */
vhcc_dung_bang();
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHCC_Admin::trang_nhan_su(); $h_ns = ob_get_clean();
t( 'chưa có hồ sơ nào thì chỉ thẳng: bắt đầu bằng Xem trước hồ sơ sẽ kéo',
	strpos( $h_ns, 'Chưa có hồ sơ nào trong cơ sở dữ liệu' ) !== false );
t( 'và nói rõ mọi mục khác đều cần có hồ sơ trước',
	strpos( $h_ns, 'đều cần có hồ sơ trước' ) !== false );

/* Có hồ sơ rồi thì đừng nhắc nữa — nhắc mãi thành tiếng ồn. */
global $wpdb;
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NV900', 'ho_ten' => 'Có rồi',
	'cua_hang' => 'TUTU_BT' ) );
ob_start(); VHCC_Admin::trang_nhan_su(); $h_ns2 = ob_get_clean();
t( 'đã có hồ sơ thì KHÔNG nhắc nữa',
	strpos( $h_ns2, 'Chưa có hồ sơ nào trong cơ sở dữ liệu' ) === false );
$wpdb->query( "DELETE FROM " . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='NV900'" );

/* Dán rỗng: nói thẳng là ô trống, và KHÔNG đưa nút "Nhập thật" cho một tệp rỗng. */
$GLOBALS['VHD_POST'] = array();
$_POST = array( 'vhcc_ns' => 'xem_nhap', 'csv' => '' );
ob_start(); VHCC_Admin::trang_nhan_su(); $h_rong = ob_get_clean();
$_POST = array();
t( 'dán rỗng thì nói "Ô dán đang trống", không vẽ bảng 0/0/0',
	strpos( $h_rong, 'Ô dán đang trống' ) !== false );
t( 'và chỉ sang đường kéo cho khỏi dán tay',
	strpos( $h_rong, 'Kéo dữ liệu cũ từ app gốc' ) !== false );

// ================= 40. BA KHO PIN KHÁC NHAU — MÀN HÌNH PHẢI NÓI RÕ CÁI NÀO DÙNG ĐỂ ĐĂNG NHẬP
/* 🔴 CA THẬT, tốn của anh Thắng một vòng: anh kéo nhân sự về, thêm một dòng PIN ở màn "Phân
   quyền & PIN", rồi thử đăng nhập trang chấm công — không vào được, tưởng dữ liệu chưa đồng bộ.
   Sự thật: KHÔNG có chỗ nào trong plugin xác thực bằng bảng `phan_quyen`, và bảng `nhan_vien`
   cũng không phải tài khoản. Cổng PIN của trang chỉ đọc NGUỒN NGƯỜI DÙNG khai ở màn Cài đặt.

   Phép thử này khoá cả hai đầu: sự thật trong mã, và lời nói trên màn hình. */

/* Đầu 1 — SỰ THẬT: đường đăng nhập không được đụng tới hai bảng kia. */
$auth_src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-auth.php' );
/* ⚠️ ĐÃ SIẾT LẠI, KHÔNG NỚI RA. Trước đây bất biến là "đường đăng nhập không bao giờ đụng
   `phan_quyen`" — để chặn dính nhau NGẦM. Giờ có nguồn 'app' đọc đúng bảng đó, nhưng phải là
   LỰA CHỌN CÓ Ý THỨC: chọn ở màn Cài đặt. Nên bất biến mới mạnh hơn và kiểm bằng HÀNH VI:
   nguồn 'chung'/'rieng' thì PIN trong `phan_quyen` PHẢI bị chối. */
/* ⚠️ SIẾT LẠI LẦN HAI, VẪN KHÔNG NỚI. Trước đây bất biến là "đường đăng nhập không bao giờ đụng
   `nhan_vien`". Nay có nguồn 'ho_so' đọc đúng bảng đó — vì khai PIN trong hồ sơ rồi vẫn phải nhớ
   bấm "Nạp tài khoản" để chép sang một danh sách thứ hai, và anh Thắng vấp đúng chỗ đó nhiều
   lần. Hai bản danh sách cho cùng một việc thì sớm muộn lệch nhau, và cái lệch đó im lặng.

   Nhưng nó phải là LỰA CHỌN CÓ Ý THỨC, y như nguồn 'app'. Nên bất biến chuyển sang kiểm bằng
   HÀNH VI, và mạnh hơn bản cũ: nguồn 'chung'/'rieng' thì PIN trong `nhan_vien` PHẢI bị chối. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'HS_THU', 'ho_ten' => 'Anh Hồ Sơ',
	'pin_dang_nhap' => '135791', 'vai_tro' => 'Admin', 'cua_hang' => 'TUTU_BT' ) );
foreach ( array( 'chung', 'rieng' ) as $ng_thu ) {
	update_option( 'vhcc_nguon_nguoidung', $ng_thu );
	VHCC_Auth::mo_khoa();
	$kq_hs = VHCC_Auth::login( '135791' );
	t( "nguồn '$ng_thu': PIN nằm trong HỒ SƠ NHÂN SỰ thì KHÔNG đăng nhập được",
		empty( $kq_hs['ok'] ), $kq_hs );
	VHCC_Auth::mo_khoa();
}
/* Chỉ khi CHỌN nguồn 'ho_so' thì mới vào được — và vào đúng vai trò khai trong hồ sơ. */
update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq_hs = VHCC_Auth::login( '135791' );
t( "chọn nguồn 'ho_so' thì PIN trong hồ sơ vào được ngay, khỏi bước chép",
	! empty( $kq_hs['ok'] ), $kq_hs );
teq( 'và đúng vai trò khai trong hồ sơ', 'Admin', isset( $kq_hs['role'] ) ? $kq_hs['role'] : null );
teq( 'đúng cơ sở', 'TUTU_BT', isset( $kq_hs['coso'] ) ? $kq_hs['coso'] : null );
VHCC_Auth::mo_khoa();
/* 🔴 Hồ sơ CHƯA khai vai trò -> 'Nhân viên', bậc THẤP NHẤT, KHÔNG đoán lên cao. Đoán nhầm lên
   Admin là mở toàn bộ bảng lương cho một dòng gõ sai chính tả.
   Họ VÀO được (mô hình năm bậc: ai cũng vào) nhưng chỉ ở bậc đáy. */
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'vai_tro' => '' ), array( 'ma_nv' => 'HS_THU' ) );
VHCC_Auth::mo_khoa();
$kq_hs = VHCC_Auth::login( '135791' );
t( 'hồ sơ chưa khai vai trò thì vào được nhưng ở BẬC ĐÁY', ! empty( $kq_hs['ok'] ), $kq_hs );
teq( 'và vai trò là Nhân viên, KHÔNG đoán lên cao', VHCC_Vai::NV,
	VHCC_Vai::cua( array( 'role' => $kq_hs['role'] ) ) );
teq( 'nên không có quyền hồ sơ', false,
	VHCC_Vai::duoc( array( 'role' => $kq_hs['role'] ), 'ho_so' ) );
VHCC_Auth::mo_khoa();
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='HS_THU'" );
update_option( 'vhcc_nguon_nguoidung', 'chung' );
t( 'đường đăng nhập đọc đúng nguồn người dùng (vhcp_cfg / CH_NguoiDung)',
	strpos( $auth_src, 'CH_NguoiDung' ) !== false );

/* Thử thật: có hồ sơ + có dòng phân quyền, nhưng PIN đó vẫn KHÔNG vào được. */
vhcc_dung_bang();
update_option( 'vhcc_nguon_nguoidung', 'chung' );
global $wpdb;
$bang_cfg2 = $wpdb->prefix . 'vhcp_cfg';
$wpdb->exec_raw( "DELETE FROM $bang_cfg2 WHERE bang='CH_NguoiDung'" );
$wpdb->insert( $bang_cfg2, array( 'bang' => 'CH_NguoiDung', 'stt' => 1,
	'cols' => wp_json_encode( array( 'Chị Kế Toán', '246813', 'Kế toán cá nhân', 'TUTU_BT' ) ) ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NV777', 'ho_ten' => 'Anh Có Hồ Sơ',
	'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '975310', 'ho_ten' => 'Anh Có Hồ Sơ',
	'vai_tro' => 'ADMIN', 'cua_hang' => 'TUTU_BT' ) );

VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '975310' );
t( 'PIN ở bảng Phân quyền KHÔNG đăng nhập được trang web (đúng thiết kế)', empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '246813' );
t( 'PIN ở NGUỒN NGƯỜI DÙNG thì vào được', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();

/* Đầu 2 — LỜI NÓI: hai màn dễ hiểu nhầm phải tự nói ra. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHCC_Man::trang_quyen(); $h_q = ob_get_clean();
t( 'màn Phân quyền nói rõ PIN của nó KHÔNG dùng đăng nhập trang chấm công',
	strpos( $h_q, 'KHÔNG dùng để đăng nhập' ) !== false );
t( 'và chỉ đường sang màn Cài đặt để xem PIN nào mới đúng',
	strpos( $h_q, 'page=vhcc' ) !== false && strpos( $h_q, 'Nguồn người dùng' ) !== false );

ob_start(); VHCC_Admin::page(); $h_cd2 = ob_get_clean();
t( 'màn Cài đặt nói rõ chỉ PIN trong bảng đó mới vào được',
	strpos( $h_cd2, 'mới vào được trang chấm công' ) !== false );
t( 'và nói rõ hồ sơ Nhân sự không phải tài khoản đăng nhập',
	strpos( $h_cd2, 'không phải tài khoản đăng nhập' ) !== false );

$wpdb->exec_raw( "DELETE FROM $bang_cfg2 WHERE bang='CH_NguoiDung'" );

// ================ 41. DANH SÁCH NGƯỜI DÙNG RIÊNG — plugin chạy được MỘT MÌNH
/* Anh Thắng: *"anh để chỉ plugin này thôi mà"*. Trước bản này chọn "danh sách riêng" là tắc:
   option chỉ được đọc, không màn nào ghi. Mục này canh cả đường ghi lẫn các chốt an toàn. */
delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
delete_option( 'vhcc_vai_tro_vao' );      // về mặc định: Admin · Quản lý · Kế toán cá nhân · Kế toán NCC

teq( 'ban đầu danh sách rỗng', 0, count( VHCC_NguoiDung::ds() ) );
$r = VHCC_NguoiDung::luu( '', 'Anh Thắng', '246813', 'Admin', '' );
t( 'thêm người đầu tiên', ! empty( $r['ok'] ), $r );
teq( 'danh sách có 1 người', 1, count( VHCC_NguoiDung::ds() ) );
teq( 'và người đó vào được', 1, VHCC_NguoiDung::so_vao_duoc() );

/* Đăng nhập thật bằng PIN vừa khai — đây mới là phép thử đáng giá. */
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '246813' );
t( 'đăng nhập được bằng PIN của danh sách riêng', ! empty( $kq['ok'] ), $kq );
teq( 'và vào đúng tên', 'Anh Thắng', isset( $kq['name'] ) ? $kq['name'] : null );
VHCC_Auth::mo_khoa();

/* ---- Chốt an toàn ---- */
$r = VHCC_NguoiDung::luu( '', 'Người B', '246813', 'Quản lý', '' );
t( 'chối PIN TRÙNG với người khác', empty( $r['ok'] ) && strpos( $r['error'], 'đã cấp cho' ) !== false, $r );
foreach ( array( '123', '123456789', '12a4' ) as $xau ) {
	$r = VHCC_NguoiDung::luu( '', 'Người X', $xau, 'Admin', '' );
	t( "chối PIN sai khuôn ($xau)", empty( $r['ok'] ), $r );
}
$r = VHCC_NguoiDung::luu( '', 'Người X', '1234', 'Admin', '' );
t( 'chối PIN dãy liên tiếp 1234', empty( $r['ok'] ), $r );
$r = VHCC_NguoiDung::luu( '', 'Người X', '4444', 'Admin', '' );
t( 'chối PIN một chữ số lặp 4444', empty( $r['ok'] ), $r );
/* Dùng lại ĐÚNG danh sách PIN cấm của màn Phân quyền — hai bản danh sách sớm muộn lệch nhau,
   và bên lỏng hơn thành cửa vào. */
$r = VHCC_NguoiDung::luu( '', 'Người X', '888888', 'Admin', '' );
t( 'chối PIN đã bị lộ 888888', empty( $r['ok'] ), $r );
$r = VHCC_NguoiDung::luu( '', 'Người X', '859624', 'Admin', '' );
t( 'chối PIN đã bị lộ 859624', empty( $r['ok'] ), $r );
teq( 'danh sách PIN cấm dùng chung với màn Phân quyền, không khai bản thứ hai',
	VHCC_Quyen::PIN_CAM, VHCC_NguoiDung::pin_bi_cam() );
$r = VHCC_NguoiDung::luu( '', 'Người X', '246814', 'Vai trò lạ', '' );
t( 'chối vai trò không có trong hệ thống', empty( $r['ok'] ), $r );
$r = VHCC_NguoiDung::luu( '', '', '246814', 'Admin', '' );
t( 'chối thiếu họ tên', empty( $r['ok'] ), $r );

/* 🔴 KHÔNG XOÁ ĐƯỢC TÀI KHOẢN QUẢN TRỊ CUỐI CÙNG — xoá là không ai mở nổi cài đặt nữa.
   (Từ 25/08/2026 chốt tính theo BẬC ADMIN, không phải "ai đăng nhập được": ai cũng đăng nhập
   được, nên chốt cũ hết ý nghĩa — nó sẽ cho xoá sạch Admin miễn là còn một Nhân viên.) */
$ds1 = VHCC_NguoiDung::ds();
$r = VHCC_NguoiDung::xoa( $ds1[0]['id'] );
t( 'chặn xoá tài khoản QUẢN TRỊ cuối cùng',
	empty( $r['ok'] ) && strpos( $r['error'], 'QUẢN TRỊ cuối cùng' ) !== false, $r );
teq( 'và không xoá thật', 1, count( VHCC_NguoiDung::ds() ) );

/* Thêm một Kế toán KHÔNG gỡ được chốt — kế toán không mở được cài đặt hệ thống. */
$r = VHCC_NguoiDung::luu( '', 'Chị Kế Toán', '357913', 'Kế toán cá nhân', 'TUTU_BT' );
t( 'thêm người thứ hai', ! empty( $r['ok'] ), $r );
$r = VHCC_NguoiDung::xoa( $ds1[0]['id'] );
t( 'thêm Kế toán vẫn KHÔNG xoá được Admin cuối cùng', empty( $r['ok'] ), $r );
/* Thêm một Admin nữa thì mới xoá được. */
VHCC_NguoiDung::luu( '', 'Admin Hai', '135797', 'Admin', '' );
$r = VHCC_NguoiDung::xoa( $ds1[0]['id'] );
t( 'thêm Admin thứ hai thì xoá được người thứ nhất', ! empty( $r['ok'] ), $r );
teq( 'còn lại 2 người', 2, count( VHCC_NguoiDung::ds() ) );

/* Người KHÔNG ở bậc Admin thì xoá thoải mái, không vướng chốt trên. */
VHCC_NguoiDung::luu( '', 'Em Nhân Viên', '468024', 'Nhân viên', '' );
$ds2 = VHCC_NguoiDung::ds();
$id_nv = '';
foreach ( $ds2 as $u ) { if ( 'Nhân viên' === $u['vaiTro'] ) { $id_nv = $u['id']; } }
$r = VHCC_NguoiDung::xoa( $id_nv );
t( 'xoá được người không phải Admin (không vướng chốt người cuối)', ! empty( $r['ok'] ), $r );

/* Sửa: để trống ô PIN = giữ PIN cũ. Bắt gõ lại PIN mỗi lần đổi tên là mời đặt PIN dễ nhớ hơn. */
$ds3 = VHCC_NguoiDung::ds();
$r = VHCC_NguoiDung::luu( $ds3[0]['id'], 'Chị Kế Toán Trưởng', '', 'Kế toán cá nhân', 'TUTU_BT' );
t( 'sửa tên mà để trống PIN thì giữ PIN cũ', ! empty( $r['ok'] ), $r );
$ds4 = VHCC_NguoiDung::ds();
teq( 'tên đã đổi', 'Chị Kế Toán Trưởng', $ds4[0]['ten'] );
teq( 'PIN giữ nguyên', '357913', $ds4[0]['pin'] );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '357913' );
t( 'và vẫn đăng nhập được bằng PIN cũ đó', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();

/* 🔴 MÀN HÌNH KHÔNG ĐƯỢC IN PIN. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHCC_Admin::page(); $h_nd = ob_get_clean();
t( 'màn Cài đặt liệt kê người của danh sách riêng',
	strpos( $h_nd, 'Chị Kế Toán Trưởng' ) !== false );
t( 'nhưng KHÔNG in PIN ra', strpos( $h_nd, '357913' ) === false );
t( 'chỉ in số chữ số', strpos( $h_nd, '6 số' ) !== false );
t( 'có ô thêm người ngay tại chỗ', strpos( $h_nd, 'Thêm người' ) !== false );

/* Sổ chỉ có Nhân viên: nay KHÔNG còn là tình trạng tắc — họ vào được, chỉ thấy công của mình.
   Màn Cài đặt phải in bảng bậc để anh Thắng đối chiếu ai làm được gì. */
delete_option( 'vhcc_nguoidung' );
VHCC_NguoiDung::luu( '', 'Chỉ Nhân Viên', '468024', 'Nhân viên', '' );
ob_start(); VHCC_Admin::page(); $h_nd2 = ob_get_clean();
t( 'màn Cài đặt in bảng năm bậc quyền',
	strpos( $h_nd2, 'Cửa hàng trưởng' ) !== false && strpos( $h_nd2, 'Kế toán' ) !== false
	&& strpos( $h_nd2, 'xem công của mình' ) !== false, $h_nd2 );
t( 'và KHÔNG còn ô tích "vai trò vào được" đã bỏ',
	strpos( $h_nd2, 'vhcc_vai_tro[]' ) === false );

delete_option( 'vhcc_nguoidung' );

/* ============ 41b. KHÔNG MÀN NÀO ĐƯỢC LỒNG <form> TRONG <form>
 *
 * 🔴 Anh Thắng ngày 22/08/2026: *"mỗi lần khai đường link, nó cứ bắt nhập họ tên phía dưới,
 *    nó chả liên quan gì cả"*. Đúng là chả liên quan — ô "Họ tên" của khối THÊM NGƯỜI mang
 *    `required`, nằm trong một <form> lồng bên trong form cài đặt. HTML không cho lồng, và
 *    trình duyệt KHÔNG báo lỗi: nó lặng lẽ vứt thẻ <form> con đi rồi gộp ô nhập vào form cha.
 *    Kết quả là bấm "Lưu cài đặt" thì trình duyệt đòi điền một ô ở tận cuối trang.
 *
 *    Nặng hơn (chưa kịp xảy ra vì `required` chặn trước): ô ẩn `vhcc_nd=xoa` của từng dòng
 *    người dùng cũng bị gộp vào form cài đặt, mà `vhcc_nd` được xử TRƯỚC `vhcc_action` — mỗi
 *    lần Lưu cài đặt là chạy kèm một lượt xoá người dùng.
 *
 * Nên phép thử này KHÔNG chỉ soi màn Cài đặt: nó dựng MỌI màn quản trị rồi đếm độ sâu <form>.
 * Đây là kiểu lỗi mắt thường không thấy và trình duyệt không kêu, phải để máy canh.
 */
function vhcc_do_sau_form( $html ) {
	// Bỏ phần chú thích HTML để chuỗi "<form" trong chú thích không bị tính.
	$html = preg_replace( '/<!--.*?-->/s', '', (string) $html );
	preg_match_all( '/<\s*(\/?)form\b/i', $html, $m );
	$sau = 0; $max = 0;
	foreach ( $m[1] as $dong ) {
		if ( '/' === $dong ) { $sau--; } else { $sau++; if ( $sau > $max ) { $max = $sau; } }
	}
	return array( 'max' => $max, 'con_thua' => $sau );
}

/* Tự thử phép đếm trước đã — một phép kiểm mà sai thì nó ru ngủ chứ không bảo vệ ai. */
teq( 'phép đếm: hai form nối tiếp là sâu 1',
	1, vhcc_do_sau_form( '<form></form><form></form>' )['max'] );
teq( 'phép đếm: form lồng form là sâu 2',
	2, vhcc_do_sau_form( '<form><form></form></form>' )['max'] );
teq( 'phép đếm: bỏ qua chữ "<form" nằm trong chú thích HTML',
	1, vhcc_do_sau_form( '<!-- <form> --><form></form>' )['max'] );
teq( 'phép đếm: thiếu thẻ đóng thì lòi ra', 1, vhcc_do_sau_form( '<form>' )['con_thua'] );

$GLOBALS['VHCP_CO_QUYEN'] = true;
$man_qt = array(
	'Cài đặt'            => array( 'VHCC_Admin', 'page' ),
	'Cổng nhận từ máy'   => array( 'VHCC_Admin', 'trang_cong_may' ),
	'Bảng công & Lương'  => array( 'VHCC_Admin', 'trang_luong' ),
	'In bảng chấm công'  => array( 'VHCC_Admin', 'trang_in' ),
	'Nhân sự'            => array( 'VHCC_Admin', 'trang_nhan_su' ),
	'Phân lịch làm'      => array( 'VHCC_Admin', 'trang_lich' ),
	'Máy & Firmware'     => array( 'VHCC_Admin', 'trang_may' ),
);
foreach ( $man_qt as $ten_man => $goi ) {
	ob_start(); call_user_func( $goi ); $h_man = ob_get_clean();
	$ds_form = vhcc_do_sau_form( $h_man );
	t( "màn $ten_man: KHÔNG lồng <form> trong <form>", $ds_form['max'] <= 1,
		'sâu nhất ' . $ds_form['max'] );
	teq( "màn $ten_man: đóng đủ thẻ </form>", 0, $ds_form['con_thua'] );
}

/* Và soi kỹ đúng ca đã hỏng: màn Cài đặt với danh sách riêng CÓ NGƯỜI (nên có cả nút Xoá từng
   dòng lẫn khối Thêm người) — đây là lúc trước kia có tới ba form lồng nhau. */
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
delete_option( 'vhcc_nguoidung' );
VHCC_NguoiDung::luu( '', 'Chị Một', '357913', 'Kế toán cá nhân', 'TUTU_BT' );
VHCC_NguoiDung::luu( '', 'Chị Hai', '468025', 'Quản lý', 'TUTU_BT' );
ob_start(); VHCC_Admin::page(); $h_lf = ob_get_clean();
$sau_lf = vhcc_do_sau_form( $h_lf );
teq( 'màn Cài đặt có 2 người: vẫn phẳng, không lồng', 1, $sau_lf['max'] );
teq( 'và đóng đủ thẻ', 0, $sau_lf['con_thua'] );

/* Ô `required` PHẢI trỏ về form của chính nó, không thì lại chặn nút Lưu cài đặt. */
t( 'ô Họ tên trỏ về form thêm người',
	preg_match( '/<input[^>]*name="ten"[^>]*form="vhcc-them-nd"/', $h_lf ) === 1
	|| preg_match( '/<input[^>]*form="vhcc-them-nd"[^>]*name="ten"/', $h_lf ) === 1, $h_lf );
t( 'nút Thêm người cũng trỏ về form đó',
	preg_match( '/<button[^>]*form="vhcc-them-nd"[^>]*>Thêm người/', $h_lf ) === 1 );
t( 'mỗi nút Xoá trỏ về form xoá riêng của dòng đó',
	preg_match_all( '/<button[^>]*form="vhcc-xoa-nd-[^"]+"/', $h_lf ) === 2 );

/* 🔴 Luật cốt lõi: KHÔNG ô ẩn nào của việc thêm/xoá người được nằm trong form cài đặt.
   Đây mới là thứ ngăn "bấm Lưu là xoá mất một người". */
$vi_luu   = strpos( $h_lf, 'name="vhcc_action" value="luu"' );
$vi_dong  = strpos( $h_lf, '</form>', $vi_luu );
$than_luu = substr( $h_lf, $vi_luu, $vi_dong - $vi_luu );
/* Ô nào mang `form="…"` thì thuộc về form KIA, dù nằm giữa hai thẻ của form cài đặt — đó chính
   là điều thuộc tính `form` của HTML5 làm. Bỏ chúng ra rồi mới xét phần còn lại: phần còn lại
   mới thật sự là ô của form cài đặt. */
$con_lai = preg_replace( '/<(?:input|button|select|textarea)\b[^>]*\bform="[^"]+"[^>]*>/i', '', $than_luu );
t( 'trong form cài đặt KHÔNG có ô ẩn vhcc_nd', strpos( $con_lai, 'vhcc_nd' ) === false );
t( 'trong form cài đặt KHÔNG còn ô required nào của việc khác',
	strpos( $con_lai, 'required' ) === false, $con_lai );

/* Và mọi `form="…"` phải trỏ tới một <form id="…"> CÓ THẬT. Trỏ hụt thì trình duyệt coi ô đó
   không thuộc form nào — bấm nút không gửi gì cả, im lặng, không báo lỗi. */
preg_match_all( '/\bform="([^"]+)"/', $h_lf, $m_ft );
preg_match_all( '/<form[^>]*\bid="([^"]+)"/', $h_lf, $m_fid );
$thieu_f = array_values( array_diff( array_unique( $m_ft[1] ), $m_fid[1] ) );
teq( 'mọi thuộc tính form="…" đều trỏ tới một <form id> có thật', array(), $thieu_f );

delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'chung' );

// ============= 42. "0 HÀNG" PHẢI NÓI VÌ SAO — chưa kéo, hay kéo rồi mà sheet trống
/* Anh Thắng mở bảng chấm công thấy "(0 hàng)" rồi hỏi "tại sao nó chưa qua". Một con số 0 trơn
   không phân biệt được ba chuyện khác hẳn nhau: chưa kéo tháng đó, đã kéo mà sheet trống, hay
   nhìn nhầm tháng. Sổ tiến độ có sẵn câu trả lời — chỉ là màn hình không tra. */
vhcc_dung_bang();
$GLOBALS['VHCP_CO_QUYEN'] = true;
VHCC_Keo::xoa_tien_do();
$_GET = array( 'coso' => 'VP_KH-HCM', 'thang' => '2026-08' );

ob_start(); VHCC_Man::trang_cham(); $h_0a = ob_get_clean();
t( 'chưa kéo tháng đó thì nói "Chưa kéo tháng này"',
	strpos( $h_0a, 'Chưa kéo tháng này' ) !== false );
t( 'và chỉ đúng đường đi, kèm tên cơ sở để gõ vào ô',
	strpos( $h_0a, 'page=vhcc-nhan-su' ) !== false && strpos( $h_0a, 'VP_KH-HCM' ) !== false );

/* Đã kéo mà 0 giờ -> KHÔNG phải lệnh kéo hỏng. Nói rõ để khỏi đi sửa nhầm chỗ. */
VHCC_Keo::ghi_tien_do( 'VP_KH-HCM', '08-2026', array( 'nguoi' => 0, 'luot' => 0 ) );
ob_start(); VHCC_Man::trang_cham(); $h_0b = ob_get_clean();
t( 'đã kéo rồi thì nói "Đã kéo tháng này rồi"',
	strpos( $h_0b, 'Đã kéo tháng này rồi' ) !== false );
t( 'và nói rõ là sheet không có dữ liệu, KHÔNG phải lệnh kéo hỏng',
	strpos( $h_0b, 'không phải lệnh kéo hỏng' ) !== false );
t( 'không còn nói "Chưa kéo" nữa', strpos( $h_0b, 'Chưa kéo tháng này' ) === false );

/* Khoá tiến độ dùng MM-yyyy còn màn hình dùng yyyy-MM — đổi khuôn sai là bảng luôn nói "chưa
   kéo" dù đã kéo, một lỗi im lặng đúng kiểu khó thấy nhất. */
$td_kt = VHCC_Keo::tien_do();
t( 'khoá sổ tiến độ đúng khuôn <cơ sở>|MM-yyyy', isset( $td_kt['VP_KH-HCM|08-2026'] ),
	implode( ', ', array_keys( $td_kt ) ) );

/* Có dữ liệu thì đừng chen mấy dòng nhắc đó vào. */
vhcc_cham( 'VP_KH-HCM', '2026-08-03', 'NV001', '', '08:00', '17:00' );
ob_start(); VHCC_Man::trang_cham(); $h_0c = ob_get_clean();
t( 'có dữ liệu rồi thì không nhắc gì thêm',
	strpos( $h_0c, 'Chưa kéo tháng này' ) === false
	&& strpos( $h_0c, 'Đã kéo tháng này rồi' ) === false );

/* Màn kéo phải LIỆT KÊ từng cặp, không chỉ đếm — con số tổng không trả lời được "cơ sở này
   tháng này đã kéo chưa". */
$ad5 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
t( 'màn kéo liệt kê từng cặp cơ sở × tháng', strpos( $ad5, 'Lượt người' ) !== false );
t( 'và nói rõ khi kéo được 0 giờ là do sheet trống',
	strpos( $ad5, 'tháng đó sheet không có dữ liệu' ) !== false );

$_GET = array();
VHCC_Keo::xoa_tien_do();

// ========== 43. TRANG ĐĂNG NHẬP PHẢI NÓI ĐÚNG NGUỒN PIN, VÀ NHÃN `sheet` PHẢI ĐƯỢC GIẢI THÍCH
/* Hai câu ghi cứng làm anh Thắng đi tìm sai chỗ hai lần:
   · Ô PIN ghi "Dùng chung mã PIN với app Vận hành chi phí" kể cả khi đã chuyển sang danh sách
     riêng — chỉ người ta đi tìm PIN ở app không liên quan.
   · Bảng chấm công toàn nhãn `sheet` nên trông như chấm công online bị mất, trong khi lượt
     online NẰM TRONG đó (app gốc ghi online vào đúng sheet CS_ bằng cùng một hàm). */
$js_cn = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/assets/js/cau-noi.js' );
t( 'chú thích dưới ô PIN không còn ghi cứng tên app chi phí',
	strpos( $js_cn, "+ '<div style=\"font-size:11px;color:#94a3b8;margin-top:13px\">Dùng chung" ) === false );
t( 'mà lấy theo nguồn đang dùng', strpos( $js_cn, 'chuThichNguon' ) !== false
	&& strpos( $js_cn, "c.nguon === 'rieng'" ) !== false );
t( '0 tài khoản vào được thì nói thẳng, kèm chỗ đi khai',
	strpos( $js_cn, 'Chưa có tài khoản nào đăng nhập được' ) !== false
	&& strpos( $js_cn, 'Chấm Công → Cài đặt' ) !== false );

/* Cấu hình truyền sang trang phải mang đủ ba thứ đó — thiếu thì JS rơi về mặc định và câu sai
   quay lại trong im lặng. */
delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
VHCC_NguoiDung::luu( '', 'Người Vào Được', '246813', 'Admin', '' );
$tr_src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang.php' );
foreach ( array( "'nguon'", "'soVao'", "'vaiTro'" ) as $k_cfg ) {
	t( "cấu hình trang có $k_cfg", strpos( $tr_src, $k_cfg . '    =>' ) !== false
		|| strpos( $tr_src, $k_cfg . '   =>' ) !== false || strpos( $tr_src, $k_cfg . '  =>' ) !== false
		|| strpos( $tr_src, $k_cfg . ' =>' ) !== false );
}
/* ⚠️ TUYỆT ĐỐI không đưa PIN sang trình duyệt — đây là cấu hình chạy trong trang công khai.
   ⚠️ Kiểm bằng cách VẼ THẬT rồi tìm chuỗi PIN, chứ không tìm chữ "pin" trong mã nguồn: bản đầu
   làm thế và trượt oan, vì hàm đếm số tài khoản có ĐỌC `$x['pin']` — đọc để đếm thì không sao,
   ĐƯA RA TRANG mới là hỏng. Phép thử phải soi cái đi ra, không soi cái đọc vào. */
$rf_head = new ReflectionMethod( 'VHCC_Trang', 'khoi_head' );
$rf_head->setAccessible( true );
$html_head = (string) $rf_head->invoke( null );
t( 'cấu hình trang KHÔNG mang chuỗi PIN nào ra trình duyệt',
	strpos( $html_head, '246813' ) === false, $html_head );
t( 'nhưng CÓ mang số tài khoản vào được', strpos( $html_head, '"soVao":1' ) !== false, $html_head );
t( 'và mang đúng nguồn đang dùng', strpos( $html_head, '"nguon":"rieng"' ) !== false );

/* Nhãn `sheet` phải được giải thích ngay tại bảng. */
$man_src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-man.php' );
t( 'chú giải nói rõ nhãn sheet gồm CẢ lượt máy lẫn lượt online',
	strpos( $man_src, 'CÓ CẢ lượt máy lẫn' ) !== false );
t( 'và nói rõ vì sao không tách được',
	strpos( $man_src, 'sheet không ghi lượt nào do máy' ) !== false );

delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'chung' );

// ============== 44. NGUỒN PIN THỨ BA: SỔ PHÂN QUYỀN CỦA APP GỐC (khỏi cấp PIN lần hai)
/* Anh Thắng: *"mỗi nhân viên đều có pin hết, sao không đăng nhập được"*. Đúng — ai cũng có PIN,
   nhưng PIN đó nằm ở sổ `PhanQuyen` của app gốc, còn cổng của plugin lại đọc danh sách khác.
   Kéo sổ đó về rồi đọc thẳng nó là ai đăng nhập được app gốc thì đăng nhập được trang web bằng
   CHÍNH PIN đó. */
vhcc_dung_bang();
update_option( 'vhcc_exec_url', 'https://script.google.com/macros/s/' . $ID . '/exec' );
update_option( 'vhcc_web_key', 'khoa-thu' );
delete_option( 'vhcc_vai_tro_vao' );

$pq_app = array(
	array( 'pin' => '246813', 'hoTen' => 'Anh Thắng',   'vaiTro' => 'ADMIN',           'cuaHang' => '' ),
	array( 'pin' => '357913', 'hoTen' => 'Chị Quản Lý', 'vaiTro' => 'QUAN_LY',         'cuaHang' => 'TUTU_BT' ),
	array( 'pin' => '468024', 'hoTen' => 'Anh CHT',     'vaiTro' => 'CUA_HANG_TRUONG', 'cuaHang' => 'TUTU_BT' ),
	array( 'pin' => '579135', 'hoTen' => 'Em Nhân Viên','vaiTro' => 'NHAN_VIEN',       'cuaHang' => 'TUTU_BT' ),
	array( 'pin' => '13',     'hoTen' => 'PIN Ngắn',    'vaiTro' => 'QUAN_LY',         'cuaHang' => '' ),
	array( 'pin' => '680246', 'hoTen' => 'Vai Trò Lạ',  'vaiTro' => 'GIAM_DOC_MOI',    'cuaHang' => '' ),
);
$GLOBALS['VHD_POST'] = array( '/macros/s/' => vhcc_app_goc( array(
	'ccXuatPhanQuyen' => array( 'ok' => true, 'rows' => $pq_app ) ) ) );

$xem = VHCC_Keo::keo_phan_quyen( true );
t( 'xem trước sổ phân quyền: đọc được', ! empty( $xem['ok'] ), $xem );
teq( 'xem trước: 5 dòng sẽ thêm (bỏ 1 dòng PIN ngắn)', 5, $xem['them'] );
teq( 'và nói rõ dòng bị bỏ vì PIN ngoài khuôn', 1, count( $xem['bo'] ) );
t( 'lý do bỏ nói rõ là PIN mấy ký tự', strpos( $xem['bo'][0], 'ký tự' ) !== false, $xem['bo'] );
global $wpdb;
teq( 'XEM TRƯỚC KHÔNG GHI GÌ', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) ) );

VHCC_Keo::keo_phan_quyen( false );
teq( 'kéo thật: 5 dòng vào bảng', 5,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) ) );
$kq2 = VHCC_Keo::keo_phan_quyen( false );
teq( 'kéo lại: 0 thêm', 0, $kq2['them'] );
teq( 'kéo lại: 5 cập nhật, không nhân đôi', 5, $kq2['sua'] );
teq( 'bảng vẫn 5 dòng', 5,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) ) );

/* ---- Đăng nhập bằng chính PIN của app gốc ---- */
update_option( 'vhcc_nguon_nguoidung', 'app' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '246813' );
t( 'ADMIN của app gốc đăng nhập được', ! empty( $kq['ok'] ), $kq );
teq( 'và vai trò quy đúng về Admin', 'Admin', isset( $kq['role'] ) ? $kq['role'] : null );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '357913' );
t( 'QUAN_LY đăng nhập được', ! empty( $kq['ok'] ), $kq );
teq( 'vai trò quy về Quản lý', 'Quản lý', isset( $kq['role'] ) ? $kq['role'] : null );
VHCC_Auth::mo_khoa();

/* Cửa hàng trưởng và Nhân viên của sổ app gốc: VÀO ĐƯỢC, ở đúng bậc của mình. */
$kq = VHCC_Auth::login( '468024' );
t( 'CỬA HÀNG TRƯỞNG vào được', ! empty( $kq['ok'] ), $kq );
teq( 'và quy đúng về bậc Cửa hàng trưởng', VHCC_Vai::CHT, VHCC_Vai::ma( $kq['role'] ) );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '579135' );
t( 'NHÂN VIÊN vào được', ! empty( $kq['ok'] ), $kq );
teq( 'ở bậc đáy', VHCC_Vai::NV, VHCC_Vai::ma( $kq['role'] ) );
VHCC_Auth::mo_khoa();
/* 🔴 Vai trò LẠ phải rơi về bậc THẤP NHẤT. Đoán nhầm lên Admin là mở toàn bộ bảng lương cho
   một dòng gõ sai chính tả trong sheet. */
$kq = VHCC_Auth::login( '680246' );
t( 'vai trò LẠ vào được nhưng ở bậc đáy', ! empty( $kq['ok'] ), $kq );
teq( 'KHÔNG thành Admin', VHCC_Vai::NV, VHCC_Vai::ma( $kq['role'] ) );
teq( 'nên không đụng được hồ sơ', false,
	VHCC_Vai::duoc( array( 'role' => $kq['role'] ), 'ho_so' ) );
VHCC_Auth::mo_khoa();

/* 🔴 BẤT BIẾN SIẾT LẠI: nguồn 'chung'/'rieng' thì PIN trong `phan_quyen` PHẢI bị chối.
   Nối hai đường đó lại một cách NGẦM là điều duy nhất không được xảy ra. */
foreach ( array( 'chung', 'rieng' ) as $ng_khac ) {
	update_option( 'vhcc_nguon_nguoidung', $ng_khac );
	VHCC_Auth::mo_khoa();
	$kq = VHCC_Auth::login( '246813' );
	t( "nguồn '$ng_khac': PIN của sổ phân quyền bị chối", empty( $kq['ok'] ), $kq );
}
update_option( 'vhcc_nguon_nguoidung', 'app' );
VHCC_Auth::mo_khoa();

/* Màn Cài đặt: có ô chọn nguồn thứ ba, và nói rõ khi chưa kéo / khi không ai vào được. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHCC_Admin::page(); $h_ng = ob_get_clean();
t( 'màn Cài đặt có ô chọn "Phân quyền của app gốc"',
	strpos( $h_ng, 'value="app"' ) !== false && strpos( $h_ng, 'Phân quyền của app gốc' ) !== false );
t( 'và đếm được bao nhiêu người vào được', strpos( $h_ng, 'người vào được' ) !== false );
/* KHÔNG in PIN ra, kể cả ở màn này. */
foreach ( array( '246813', '357913' ) as $pin_that ) {
	t( "màn Cài đặt không in PIN $pin_that", strpos( $h_ng, $pin_that ) === false );
}

/* Chưa dán hàm mới bên Apps Script -> nói rõ tên hàm còn thiếu. */
$GLOBALS['VHD_POST'] = array( '/macros/s/' => vhcc_app_goc( array() ) );
$kq = VHCC_Keo::keo_phan_quyen( true );
t( 'chưa dán ccXuatPhanQuyen thì nói rõ tên hàm',
	empty( $kq['ok'] ) && strpos( $kq['error'], 'ccXuatPhanQuyen' ) !== false, $kq );

$GLOBALS['VHD_POST'] = array();
update_option( 'vhcc_nguon_nguoidung', 'chung' );

// ============ 45. CÀI XONG PHẢI CÓ MỘT ĐƯỜNG VÀO — và PIN phải lấy từ DỮ LIỆU CŨ
/* Anh Thắng cài xong bản 2.0.x: *"chưa đăng nhập được"*. Trang chấm công báo "Chưa có tài khoản
   nào đăng nhập được" và KHÔNG có đường nào tự mở ngoài sửa thẳng database.

   Bản sửa đầu của em bịa ra một PIN mới. Anh Thắng chỉnh ngay: *"pin nằm ở dữ liệu cũ chứ"* —
   đúng, mọi người ĐÃ CÓ PIN, chúng nằm ở sổ Phân quyền của app gốc. Bịa PIN mới là bắt cả chuỗi
   26 cửa hàng cấp lại lần hai một thứ họ đang có. Nên thứ tự phải là: NẠP SỔ CŨ TRƯỚC, bịa PIN
   chỉ là đường cùng. Và *"bên chi phí không liên quan gì chấm công, anh vẫn dùng danh sách
   riêng"* — nên KHÔNG tự kéo người bên plugin chi phí sang. */
vhcc_dung_bang();
$wpdb->exec_raw( "DELETE FROM $bang_cfg2 WHERE bang='CH_NguoiDung'" );
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
function vhcc_don_mo_duong() {
	foreach ( array( 'vhcc_da_mo_duong', 'vhcc_pin_lan_dau', 'vhcc_gieo_doi_nguon',
		'vhcc_mo_duong_nap', 'vhcc_nguoidung', 'vhcc_vai_tro_vao' ) as $o ) { delete_option( $o ); }
	update_option( 'vhcc_nguon_nguoidung', 'chung' );
}

/* ---- 45a. CÓ SỔ PIN CŨ -> NẠP, KHÔNG BỊA PIN MỚI ---- */
vhcc_don_mo_duong();
foreach ( array(
	array( '246813', 'Anh Quản Lý',  'QUAN_LY',         'TUTU_BT' ),
	array( '357913', 'Chị Kế Toán',  'KE_TOAN',         'TUTU_BT' ),
	array( '468024', 'Em Nhân Viên', 'NHAN_VIEN',       'CS_FZ_ADV_AL' ),
	array( '579135', 'Anh CHT',      'CUA_HANG_TRUONG', 'CS_FZ_ADV_AL' ),
	/* Sổ phải có SẴN một Admin thì mới khỏi gieo PIN mới: bậc Admin là bậc duy nhất mở được
	   cài đặt, và không có ai ở bậc đó thì hệ tắc phần quản trị dù ai cũng đăng nhập được. */
	array( '802468', 'Anh Admin Cũ', 'ADMIN',           '' ),
) as $r_pq ) {
	$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => $r_pq[0], 'ho_ten' => $r_pq[1],
		'vai_tro' => $r_pq[2], 'cua_hang' => $r_pq[3] ) );
}
teq( 'có sổ PIN cũ thì NẠP, không bịa PIN mới', 'nap', VHCC_NguoiDung::mo_duong_vao() );
teq( 'KHÔNG hiện PIN lần đầu nào ở wp-admin', '', VHCC_NguoiDung::pin_lan_dau() );
teq( 'nạp đủ 5 người của sổ cũ', 5, count( VHCC_NguoiDung::ds() ) );
$_nap = VHCC_NguoiDung::mo_duong_nap();
teq( 'và kể lại đã nạp bao nhiêu', 5, isset( $_nap['so'] ) ? $_nap['so'] : 0 );

/* 🔴 Phép thử đáng giá nhất: mọi người đăng nhập bằng ĐÚNG PIN CŨ của họ. */
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '246813' );
t( 'anh Quản lý vào được bằng ĐÚNG PIN CŨ', ! empty( $kq['ok'] ), $kq );
teq( 'đúng tên', 'Anh Quản Lý', isset( $kq['name'] ) ? $kq['name'] : null );
teq( 'đúng vai trò (QUAN_LY -> Quản lý)', 'Quản lý', isset( $kq['role'] ) ? $kq['role'] : null );
teq( 'đúng cơ sở', 'TUTU_BT', isset( $kq['coso'] ) ? $kq['coso'] : null );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '357913' );
t( 'chị Kế toán cũng vào được bằng PIN cũ', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();
/* Nhân viên / Cửa hàng trưởng vẫn NẠP (để có sẵn, chỉ cần tích vai trò là vào) nhưng CHƯA vào
   được — chấm công là căn cứ tính lương, không mở rộng quyền thay anh Thắng. */
$kq = VHCC_Auth::login( '468024' );
t( 'nhân viên nạp về VÀO ĐƯỢC luôn (mô hình năm bậc)', ! empty( $kq['ok'] ), $kq );
teq( 'ở bậc đáy', VHCC_Vai::NV, VHCC_Vai::ma( $kq['role'] ) );
VHCC_Auth::mo_khoa();
teq( 'nguồn chuyển sang danh sách riêng', 'rieng', VHCC_Auth::nguon() );
teq( 'và ghi lại nguồn cũ để màn Cài đặt nói ra', 'chung', VHCC_NguoiDung::gieo_doi_nguon() );

/* ---- 45b. KHÔNG CÓ SỔ CŨ -> mới bịa PIN, và đó là ĐƯỜNG CÙNG ---- */
vhcc_don_mo_duong();
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
teq( 'không có sổ cũ thì mới khai tài khoản mới', 'gieo', VHCC_NguoiDung::mo_duong_vao() );
$pin_ld = VHCC_NguoiDung::pin_lan_dau();
t( 'có PIN lần đầu để quản trị đọc', preg_match( '/^\d{6}$/', $pin_ld ) === 1, $pin_ld );
teq( 'PIN đó tự nó hợp lệ (không rơi vào danh sách bị chặn)', '', VHCC_Quyen::pin_hop_le( $pin_ld ) );
t( 'PIN KHÔNG nằm trong danh sách PIN đã lộ/dễ đoán',
	! in_array( $pin_ld, VHCC_Quyen::PIN_CAM, true ), $pin_ld );
VHCC_Auth::mo_khoa();
$kq_ld = VHCC_Auth::login( $pin_ld );
t( 'đăng nhập THẬT được bằng PIN lần đầu', ! empty( $kq_ld['ok'] ), $kq_ld );
teq( 'và vào với vai trò Admin', 'Admin', isset( $kq_ld['role'] ) ? $kq_ld['role'] : null );
VHCC_Auth::mo_khoa();
teq( 'chỉ khai ĐÚNG MỘT tài khoản', 1, count( VHCC_NguoiDung::ds() ) );

/* PIN sinh NGẪU NHIÊN, không phải hằng số ai đọc mã cũng biết. */
$_pin_nhieu = array();
for ( $_i = 0; $_i < 8; $_i++ ) { vhcc_don_mo_duong(); VHCC_NguoiDung::mo_duong_vao();
	$_pin_nhieu[] = VHCC_NguoiDung::pin_lan_dau(); }
t( 'PIN lần đầu là ngẫu nhiên, không phải hằng số trong mã',
	count( array_unique( $_pin_nhieu ) ) > 1, $_pin_nhieu );

/* ---- 45c. SỔ CŨ CÓ NGƯỜI NHƯNG KHÔNG AI VÀO ĐƯỢC -> nạp XONG vẫn phải mở một đường ---- */
vhcc_don_mo_duong();
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
foreach ( array( array( '468024', 'Em A', 'NHAN_VIEN' ), array( '579135', 'Anh B', 'CUA_HANG_TRUONG' ) ) as $r_pq ) {
	$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => $r_pq[0], 'ho_ten' => $r_pq[1],
		'vai_tro' => $r_pq[2], 'cua_hang' => 'TUTU_BT' ) );
}
teq( 'sổ cũ toàn vai trò không vào được thì vẫn phải khai một Admin', 'gieo', VHCC_NguoiDung::mo_duong_vao() );
teq( 'nhưng người của sổ cũ VẪN được nạp về (2 + 1 Admin)', 3, count( VHCC_NguoiDung::ds() ) );
$_nap = VHCC_NguoiDung::mo_duong_nap();
teq( 'và vẫn kể lại đã nạp 2 người', 2, isset( $_nap['so'] ) ? $_nap['so'] : 0 );
/* Tích thêm vai trò là 2 người kia vào được ngay, khỏi gõ tay lại. */
update_option( 'vhcc_vai_tro_vao', array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' ) );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '468024' );
t( 'tích thêm vai trò là người của sổ cũ vào được ngay', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();
delete_option( 'vhcc_vai_tro_vao' );

/* ---- 45d. KHÔNG TỰ KÉO NGƯỜI BÊN PLUGIN CHI PHÍ ---- */
/* *"bên chi phí không liên quan gì chấm công"* — tự kéo sang là nối lại hai hệ thống anh đã
   tách. Vẫn nạp được, nhưng phải do người bấm nút. */
vhcc_don_mo_duong();
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
$wpdb->insert( $bang_cfg2, array( 'bang' => 'CH_NguoiDung', 'stt' => 1,
	'cols' => wp_json_encode( array( 'Người Bên Chi Phí', '246813', 'Admin', 'TUTU_BT' ) ) ) );
VHCC_NguoiDung::mo_duong_vao();
$_ten = array();
foreach ( VHCC_NguoiDung::ds() as $u ) { $_ten[] = $u['ten']; }
t( 'KHÔNG tự kéo người bên plugin chi phí sang', ! in_array( 'Người Bên Chi Phí', $_ten, true ), $_ten );
/* Nhưng bấm nút thì nạp được. */
$r = VHCC_NguoiDung::nap_tu_cu( 'chung', false );
t( 'bấm nút thì nạp được từ bảng chi phí', ! empty( $r['ok'] ) && 1 === $r['them'], $r );
$wpdb->exec_raw( "DELETE FROM $bang_cfg2 WHERE bang='CH_NguoiDung'" );

/* ---- 45e. CHẠY ĐÚNG MỘT LẦN, không lật ngược lựa chọn của quản trị ---- */
t( 'nâng cấp lần sau KHÔNG làm gì nữa', '' === VHCC_NguoiDung::mo_duong_vao() );
update_option( 'vhcc_nguon_nguoidung', 'chung' );   // quản trị cố ý chọn lại nguồn chung
VHCC_NguoiDung::mo_duong_vao();
teq( 'nâng cấp sau KHÔNG lật ngược lựa chọn nguồn của quản trị', 'chung', VHCC_Auth::nguon() );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );

/* ---- 45f. NẠP RIÊNG TỪNG CƠ SỞ ---- */
/* Anh Thắng: *"nếu dữ liệu lỗi, cho anh kéo riêng từng cơ sở (bao gồm tên đăng nhập và pin) là
   dữ liệu chấm công cũ cho nhanh"*. */
vhcc_don_mo_duong();
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
update_option( 'vhcc_da_mo_duong', 1 );             // khỏi tự chạy, mục này thử tay
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
foreach ( array(
	array( '246813', 'Anh Quản Lý',  'QUAN_LY', 'TUTU_BT' ),
	array( '357913', 'Chị Kế Toán',  'KE_TOAN', 'tutu bt' ),      // cùng cửa hàng, gõ khác kiểu
	array( '468024', 'Anh Cơ Sở Hai', 'QUAN_LY', 'CS_FZ_ADV_AL' ),
) as $r_pq ) {
	$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => $r_pq[0], 'ho_ten' => $r_pq[1],
		'vai_tro' => $r_pq[2], 'cua_hang' => $r_pq[3] ) );
}
$r = VHCC_NguoiDung::nap_tu_cu( 'app', true, 'TUTU_BT' );
teq( 'xem trước cơ sở TUTU_BT: 2 người', 2, $r['them'] );
teq( 'và bỏ qua 1 người của cơ sở khác', 1, $r['lech'] );
teq( 'XEM TRƯỚC thì KHÔNG ghi gì', 0, count( VHCC_NguoiDung::ds() ) );
/* Tên cơ sở gõ khác kiểu vẫn phải khớp — sổ cũ gõ tay nên `TUTU_BT`, `tutu bt`, `TuTu-BT` là
   cùng một cửa hàng. So bằng === thì kéo cơ sở nào cũng ra 0 người mà chỉ báo "không có ai". */
t( 'so tên cơ sở bỏ qua hoa thường, gạch, khoảng trắng',
	VHCC_NguoiDung::cung_coso( 'TuTu-BT', 'tutu bt' ) && VHCC_NguoiDung::cung_coso( ' TUTU_BT ', 'tutubt' ) );
t( 'nhưng KHÔNG gộp hai cơ sở khác nhau', ! VHCC_NguoiDung::cung_coso( 'TUTU_BT', 'CS_FZ_ADV_AL' ) );
t( 'và cơ sở rỗng không khớp bừa với ai', ! VHCC_NguoiDung::cung_coso( '', '' ) );

$r = VHCC_NguoiDung::nap_tu_cu( 'app', false, 'TUTU_BT' );
teq( 'nạp thật cơ sở TUTU_BT', 2, $r['them'] );
teq( 'danh sách có đúng 2 người', 2, count( VHCC_NguoiDung::ds() ) );
$r = VHCC_NguoiDung::nap_tu_cu( 'app', false, 'TUTU_BT' );
teq( 'nạp lại lần hai KHÔNG nhân đôi', 0, $r['them'] );
teq( 'vẫn 2 người', 2, count( VHCC_NguoiDung::ds() ) );
$r = VHCC_NguoiDung::nap_tu_cu( 'app', false, 'CS_FZ_ADV_AL' );
teq( 'nạp tiếp cơ sở thứ hai', 1, $r['them'] );
teq( 'giờ đủ 3 người', 3, count( VHCC_NguoiDung::ds() ) );

/* Bảng cơ sở để đổ vào ô chọn ở màn Cài đặt. */
$_cs = VHCC_NguoiDung::ds_coso_cu( 'app' );
teq( 'liệt kê được các cơ sở của sổ cũ', 3, count( $_cs ) );
teq( 'đếm đúng số người của một cơ sở', 1, isset( $_cs['CS_FZ_ADV_AL']['co'] ) ? $_cs['CS_FZ_ADV_AL']['co'] : 0 );

/* ---- 45g. DÁN THẲNG TỪ GOOGLE SHEETS — không cần cầu nối Apps Script ---- */
delete_option( 'vhcc_nguoidung' );
$_tab = "Họ và Tên\tPIN\tVai trò\tCửa hàng\n"
	. "Nguyễn Văn A\t246813\tQUAN_LY\tTUTU_BT\n"
	. "Trần Thị B\t357913\tKế toán cá nhân\tTUTU_BT\n"
	. "Lê Văn C\t468024\tNHAN_VIEN\tCS_FZ_ADV_AL";
$r = VHCC_NguoiDung::nap_dan( $_tab, true );
teq( 'dán có tiêu đề: đọc được 3 người', 3, $r['them'] );
t( 'nhận ra dòng tiêu đề', ! empty( $r['tieude'] ) );
teq( 'XEM TRƯỚC không ghi gì', 0, count( VHCC_NguoiDung::ds() ) );
$r = VHCC_NguoiDung::nap_dan( $_tab, false );
teq( 'nạp thật 3 người', 3, count( VHCC_NguoiDung::ds() ) );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '246813' );
t( 'người dán từ Sheet đăng nhập được ngay bằng PIN đó', ! empty( $kq['ok'] ), $kq );
teq( 'và đúng vai trò đọc từ mã app gốc', 'Quản lý', isset( $kq['role'] ) ? $kq['role'] : null );
VHCC_Auth::mo_khoa();
$r = VHCC_NguoiDung::nap_dan( $_tab, false );
teq( 'dán lại y hệt thì KHÔNG nhân đôi', 0, $r['them'] );

/* Sổ Phân quyền để PIN ở CỘT ĐẦU, tên ở cột hai — thứ tự ngược với ví dụ trên. Bắt người ta sắp
   lại cột trước khi dán là mời gõ tay lại từ đầu, nên phải tự đoán theo NỘI DUNG. */
delete_option( 'vhcc_nguoidung' );
$r = VHCC_NguoiDung::nap_dan( "246813\tNguyễn Văn A\tQUAN_LY\tTUTU_BT\n357913\tTrần Thị B\tKE_TOAN\tTUTU_BT", false );
teq( 'PIN đứng TRƯỚC tên (kiểu sổ Phân quyền) vẫn đọc đúng', 2, $r['them'] );
$_ten = array();
foreach ( VHCC_NguoiDung::ds() as $u ) { $_ten[] = $u['ten']; }
t( 'lấy đúng cột họ tên, không lấy nhầm cột PIN', in_array( 'Nguyễn Văn A', $_ten, true ), $_ten );

/* Google Sheets coi PIN là SỐ: 246813 xuất ra "246813.0". Đây là lỗi ĐÃ làm không ai đăng nhập
   được một lần rồi — cắt đuôi ngay lúc nhận. */
delete_option( 'vhcc_nguoidung' );
$r = VHCC_NguoiDung::nap_dan( "Nguyễn Văn A\t246813.0\tQUAN_LY\nTrần Thị B\t357913.00\tKE_TOAN", false );
teq( 'PIN mang đuôi .0 của Sheets vẫn nạp được', 2, $r['them'] );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '246813' );
t( 'và đăng nhập được bằng PIN đã cắt đuôi', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();

/* Dấu phẩy (người gõ tay) và lọc cơ sở khi dán. */
delete_option( 'vhcc_nguoidung' );
$r = VHCC_NguoiDung::nap_dan( "Nguyễn Văn A,246813,QUAN_LY,TUTU_BT\nLê Văn C,468024,QUAN_LY,CS_FZ_ADV_AL", false, 'tutu bt' );
teq( 'dán bằng dấu phẩy, lọc riêng một cơ sở', 1, $r['them'] );
teq( 'và bỏ qua cơ sở khác', 1, $r['lech'] );

/* 🔴 DÒNG HỎNG PHẢI KÊU ĐÍCH DANH. Nạp 26 cửa hàng mà im lặng bỏ 4 người PIN hỏng thì cuối
   tháng 4 người đó không có công, và không ai dựng lại được vì màn hình đã báo "xong". */
delete_option( 'vhcc_nguoidung' );
$r = VHCC_NguoiDung::nap_dan(
	"Họ và Tên\tPIN\n"
	. "Người Tốt\t246813\n"
	. "Người PIN Ngắn\t123\n"
	. "Người Không PIN\t\n"
	. "Người PIN Trùng\t246813\n"
	. "Người PIN Lộ\t859624", false );
teq( 'chỉ nạp được người hợp lệ', 2, $r['them'] );   // Người Tốt + Người PIN Lộ
teq( 'và kêu đích danh 3 dòng hỏng', 3, count( $r['bo'] ) );
t( 'nói rõ ai PIN sai khuôn', strpos( implode( ' | ', $r['bo'] ), 'Người PIN Ngắn' ) !== false, $r['bo'] );
t( 'nói rõ ai chưa có PIN', strpos( implode( ' | ', $r['bo'] ), 'Người Không PIN' ) !== false, $r['bo'] );
t( 'nói rõ ai trùng PIN với người khác', strpos( implode( ' | ', $r['bo'] ), 'Người PIN Trùng' ) !== false, $r['bo'] );
/* PIN đã lộ thì VẪN NẠP — chặn lúc nạp là khoá đúng người đang dùng nó ra khỏi hệ thống, mà màn
   hình chỉ báo "bỏ qua N dòng". Nạp rồi KÊU TÊN để đi đổi. */
t( 'PIN đã lộ vẫn nạp (không khoá người ta ra ngoài)',
	strpos( implode( ' | ', $r['bo'] ), 'Người PIN Lộ' ) === false, $r['bo'] );
t( 'nhưng kêu tên ra để đổi sớm', in_array( 'Người PIN Lộ', (array) $r['yeu'], true ), $r['yeu'] );

/* Dán bậy thì nói rõ phải làm gì, đừng nuốt im lặng. */
$r = VHCC_NguoiDung::nap_dan( '', true );
t( 'dán rỗng thì chối và nói rõ', empty( $r['ok'] ) && strpos( $r['error'], 'Chưa dán' ) !== false, $r );
$r = VHCC_NguoiDung::nap_dan( "Nguyễn Văn A\nTrần Thị B", true );
t( 'thiếu cột PIN thì chỉ cách bôi đen cả hai cột',
	empty( $r['ok'] ) && strpos( $r['error'], '2 cột' ) !== false, $r );
$r = VHCC_NguoiDung::nap_dan( "Họ và Tên\tGhi chú\nNguyễn Văn A\txin nghỉ", true );
t( 'không có cột nào là PIN thì nói rõ, và nhắc bẫy số 0 đầu của Sheets',
	empty( $r['ok'] ) && strpos( $r['error'], 'PIN' ) !== false
	&& strpos( $r['error'], '0123' ) !== false, $r );

/* Vai trò lạ -> bậc THẤP NHẤT. Đoán nhầm lên Admin là mở toàn bộ bảng lương cho một dòng gõ sai. */
teq( 'vai trò lạ về Nhân viên, KHÔNG đoán lên cao', 'Nhân viên', VHCC_NguoiDung::doc_vai_tro( 'Sếp Tổng' ) );
teq( 'nhận mã hoa của app gốc', 'Quản lý', VHCC_NguoiDung::doc_vai_tro( 'QUAN_LY' ) );
teq( 'nhận cả chữ tiếng Việt có dấu', 'Quản lý', VHCC_NguoiDung::doc_vai_tro( 'Quản lý' ) );
teq( 'và viết liền không dấu', 'Cửa hàng trưởng', VHCC_NguoiDung::doc_vai_tro( 'cuahangtruong' ) );

/* ---- 45h. NHỮNG CHỐT KHÔNG ĐƯỢC LỎNG ---- */
/* 🔴 PIN CHỈ ĐƯỢC HIỆN TRONG wp-admin. Ảnh chụp trang công khai đi khắp nơi — chính dự án này
   đã mất một khoá cầu nối vì một ảnh gửi qua chat. */
$_ro_ri = array();
foreach ( array_merge(
	glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ),
	glob( $goc . '/wordpress/vhcp-cham-cong/assets/js/*.js' ),
	glob( $goc . '/wordpress/vhcp-cham-cong/templates/*' ) ) as $_f ) {
	if ( 'class-vhcc-admin.php' === basename( $_f ) )      { continue; }   // wp-admin: được phép
	if ( 'class-vhcc-nguoi-dung.php' === basename( $_f ) ) { continue; }   // nơi khai ra nó
	if ( ! is_file( $_f ) ) { continue; }
	if ( strpos( file_get_contents( $_f ), 'pin_lan_dau' ) !== false ) { $_ro_ri[] = basename( $_f ); }
}
t( 'PIN lần đầu không lọt sang trang công khai', count( $_ro_ri ) === 0, $_ro_ri );

/* Màn Cài đặt: PIN + nút xoá + kể rõ đã đổi nguồn; và hộp nạp có đủ hai đường. */
vhcc_don_mo_duong();
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );
VHCC_NguoiDung::mo_duong_vao();
$pin_ld2 = VHCC_NguoiDung::pin_lan_dau();
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHCC_Admin::page(); $h_ld = ob_get_clean();
t( 'màn Cài đặt hiện PIN lần đầu', strpos( $h_ld, $pin_ld2 ) !== false );
t( 'kèm nút "tôi đã ghi lại" để xoá đi', strpos( $h_ld, 'quen_pin_lan_dau' ) !== false );
t( 'và chỉ sang chỗ nạp sổ PIN cũ', strpos( $h_ld, 'nạp sổ PIN cũ' ) !== false );
t( 'nói thẳng là plugin đã đổi nguồn người dùng', strpos( $h_ld, 'Nguồn người dùng</b> từ' ) !== false );
t( 'và trấn an rằng danh sách cũ không mất', strpos( $h_ld, 'không mất gì' ) !== false );
t( 'có hộp nạp từ dữ liệu cũ', strpos( $h_ld, 'Nạp người dùng từ dữ liệu cũ' ) !== false );
t( 'có ô dán thẳng từ Google Sheets', strpos( $h_ld, 'name="dan"' ) !== false
	&& strpos( $h_ld, 'dán thẳng từ Google Sheets' ) !== false );
t( 'có nút xem trước cho ô dán', strpos( $h_ld, 'value="xem_dan"' ) !== false );
/* Kho rỗng thì KHÔNG vẽ nút nạp — mời người ta bấm vào một cái trống rồi báo lỗi là vô ích. */
t( 'kho cũ rỗng thì không mời bấm nút nạp', strpos( $h_ld, 'value="xem_cu"' ) === false );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array( 'pin' => '246813', 'ho_ten' => 'Anh Quản Lý',
	'vai_tro' => 'QUAN_LY', 'cua_hang' => 'TUTU_BT' ) );
ob_start(); VHCC_Admin::page(); $h_ld2 = ob_get_clean();
t( 'kho cũ CÓ người thì hiện nút xem trước + nạp', strpos( $h_ld2, 'value="xem_cu"' ) !== false
	&& strpos( $h_ld2, 'value="nap_cu"' ) !== false );
t( 'và có ô chọn riêng từng cơ sở', strpos( $h_ld2, 'TUTU_BT' ) !== false
	&& strpos( $h_ld2, 'cả chuỗi' ) !== false );
t( 'ô đó nói luôn cơ sở đó có bao nhiêu PIN dùng được', strpos( $h_ld2, 'có PIN dùng được' ) !== false );
t( 'và nhắc bẫy số 0 đầu / đuôi .0 của Sheets ngay tại ô dán', strpos( $h_ld, '246813.0' ) !== false );

/* Nút "đã ghi lại" phải ĂN THẬT, và chỉ ăn với người có quyền quản trị. */
$_POST = array( 'vhcc_viec' => 'quen_pin_lan_dau' );
$GLOBALS['VHCP_CO_QUYEN'] = false;
VHCC_Admin::handle_post();
t( 'người KHÔNG có quyền quản trị bấm thì không ăn gì', '' !== VHCC_NguoiDung::pin_lan_dau() );
$GLOBALS['VHCP_CO_QUYEN'] = true;
VHCC_Admin::handle_post();
teq( 'quản trị bấm thì PIN bị xoá hẳn', '', VHCC_NguoiDung::pin_lan_dau() );
$_POST = array();

/* Van cứu phải ĐƯỢC GỌI lúc nâng cấp, không thì viết ra để đó. */
$_boot = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
t( 'nâng cấp phiên bản có gọi mo_duong_vao',
	preg_match( '/vhcc_maybe_upgrade.*mo_duong_vao/s', $_boot ) === 1 );

/* ⚠️ Và nó KHÔNG được nằm trong tệp đường đăng nhập — xem mục 40: tệp đó không được mang danh
   sách PIN cấm, mà mo_duong_vao thì phải gọi tới danh sách đó. */
t( 'van cứu nằm ngoài đường đăng nhập',
	strpos( file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-auth.php' ),
		'mo_duong_vao' ) === false );

/* Dọn về trạng thái cũ cho các mục sau. */
vhcc_don_mo_duong();
$wpdb->exec_raw( 'DELETE FROM ' . VHCC_DB::t( 'phan_quyen' ) );

// ============ 46. NẠP .CSV SỔ NHÂN VIÊN — ĐỦ MỌI CỘT, KHÔNG QUA CẦU NỐI
/* Anh Thắng gửi ảnh sổ nhân viên: *"cấu trúc trang nhân viên nó như này mà, nạp bằng .csv được
   không"* → *"lấy đủ luôn nhé, các cột"*.

   Sổ có: Mã NV · Họ tên · Cửa hàng · Trạng thái đồng bộ · Cập nhật · CCCD · Chức vụ · Nhiệm vụ ·
   Cơ sở phụ · PIN đăng nhập — và MẤY NHÓM CỘT ĐANG THU GỌN. Bảng `nhan_vien` đã có đủ chỗ.

   Mục này canh bốn chỗ sai là hỏng dữ liệu thật, cả bốn đều đã gặp trong dự án này. */
vhcc_dung_bang();

/* Đúng khuôn sổ của anh Thắng, kể cả cột trống của mấy nhóm đang thu gọn, và một cột lạ. */
$csv_nv = "Mã NV,Họ tên,Cửa hàng,,,Trạng thái đồng bộ,Cập nhật,,CCCD,Chức vụ,Nhiệm vụ,Cơ sở phụ,PIN đăng nhập,Ghi chú nội bộ\n"
	. "MNNV2MTD0001,Nguyễn Thu Hiền,JP_HCM,,,,2026-08-08 04:29:48,,049304007231,Máy tự động,JP Aeon Mall Tân Phú,POSH_HCM,170412,abc\n"
	. "MNNV2MTD0003,Đinh Bùi Xuân Chiến,POSH_HCM,,,,2026-08-08 04:29:48,,051206000139,Máy tự động,,,782006,\n"
	. "MNNV2MTD0006,Nguyễn Văn Bin,POSH_HCM,,,synced,2026-08-10 19:48:15,,060203003678,Máy tự động,,FARM_PT,635359,\n"
	. "MNNV2MTD0025,NGUYỄN THI MAI ANH,POSH_HCM,,,,2026-08-08 04:29:48,,077192002977,Máy tự động,,\"FARM_PT, FZ_LTVT\",351604,\n";

$r = VHCC_NapCsv::nap( $csv_nv, true );
t( 'đọc được file .csv sổ nhân viên', ! empty( $r['ok'] ), $r );
teq( 'xem trước: 4 người mới', 4, $r['them'] );
teq( 'chưa ai đang có nên không sửa ai', 0, $r['sua'] );
teq( 'XEM TRƯỚC thì KHÔNG ghi gì', 0, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );

/* "Lấy đủ luôn các cột" — phải nhận đủ 10 cột có tên, và KỂ TÊN cột không nhận ra. */
foreach ( array( 'ma_nv', 'ho_ten', 'cua_hang', 'trang_thai_dong_bo', 'cccd',
	'chuc_vu', 'nhiem_vu', 'coso_phu', 'pin_dang_nhap' ) as $c_mong ) {
	t( "nhận cột $c_mong", in_array( $c_mong, (array) $r['cot'], true ), $r['cot'] );
}
t( 'và KỂ TÊN cột không nhận ra, không im lặng bỏ',
	in_array( 'Ghi chú nội bộ', (array) $r['cot_la'], true ), $r['cot_la'] );

$r = VHCC_NapCsv::nap( $csv_nv, false );
teq( 'nạp thật 4 hồ sơ', 4, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
$hs = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MNNV2MTD0001'" );
$hs = $hs[0];
teq( 'họ tên có dấu vào đúng', 'Nguyễn Thu Hiền', $hs['ho_ten'] );
teq( 'cửa hàng đúng', 'JP_HCM', $hs['cua_hang'] );
teq( 'CCCD đúng', '049304007231', $hs['cccd'] );
teq( 'chức vụ đúng', 'Máy tự động', $hs['chuc_vu'] );
teq( 'nhiệm vụ đúng', 'JP Aeon Mall Tân Phú', $hs['nhiem_vu'] );
teq( 'cơ sở phụ đúng', 'POSH_HCM', $hs['coso_phu'] );
teq( 'PIN đăng nhập đúng', '170412', $hs['pin_dang_nhap'] );
/* 🔴 CỘT "Cập nhật" CỦA SHEET KHÔNG ĐƯỢC NẠP VÀO. Nó là "sheet đồng bộ lần cuối lúc nào", còn
   cột `cap_nhat` trên host phải trả lời "hồ sơ này sửa lần cuối lúc nào TRÊN HOST" — nạp vào là
   biến nó thành một câu nói dối.

   Và nó phá luôn bảng xem trước: anh Thắng nạp thử ra 240/240 dòng "đổi", mà đổi duy nhất một ô
   `cap_nhat` — 240 dòng nhiễu che sạch những ô đổi THẬT, đúng thứ bảng đó sinh ra để cho thấy. */
t( 'KHÔNG nạp cột Cập nhật của sheet', ! in_array( 'cap_nhat', (array) $r['cot'], true ), $r['cot'] );
t( 'nhưng nói rõ là bỏ có chủ ý, không im lặng',
	in_array( 'Cập nhật', (array) $r['cot_co_y'], true ), $r['cot_co_y'] );
t( 'và đóng dấu giờ của HOST vào hồ sơ vừa ghi', '' !== trim( (string) $hs['cap_nhat'] ), $hs['cap_nhat'] );
t( 'giờ đó KHÔNG phải giờ trong sheet', '2026-08-08 04:29:48' !== $hs['cap_nhat'], $hs['cap_nhat'] );

/* Mấy tên cột sổ thật của anh Thắng mà bản trước chưa nhận ra. */
$r_cot = VHCC_NapCsv::nap( "Mã NV,Họ tên,SĐT khẩn cấp,Ảnh CCCD (fileId),Người liên hệ khẩn cấp\n"
	. "CT1,Người Cột,0909111222,file-abc,Chị Hai\n", true );
teq( 'nhận cột "SĐT khẩn cấp"', true, in_array( 'sdt_khan', (array) $r_cot['cot'], true ) );
teq( 'nhận cột "Ảnh CCCD (fileId)"', true, in_array( 'cccd_file_id', (array) $r_cot['cot'], true ) );
teq( 'nhận cột "Người liên hệ khẩn cấp"', true, in_array( 'nguoi_lien_he_khan', (array) $r_cot['cot'], true ) );
teq( 'và không còn cột nào lạ', 0, count( (array) $r_cot['cot_la'] ) );

/* 🔴 1. DẤU PHẨY TRONG Ô. `"FARM_PT, FZ_LTVT"` mà cắt bằng explode(',') là dòng đó lệch hết cột
   từ đó trở đi — PIN của người này rơi vào cột CCCD của người kia. */
$hs = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MNNV2MTD0025'" );
$hs = $hs[0];
teq( 'ô có DẤU PHẨY bên trong dấu nháy không làm lệch cột', 'FARM_PT, FZ_LTVT', $hs['coso_phu'] );
teq( 'và PIN của dòng đó vẫn đúng', '351604', $hs['pin_dang_nhap'] );
teq( 'CCCD cũng không bị đẩy sang cột khác', '077192002977', $hs['cccd'] );

/* 🔴 2. Ô RỖNG KHÔNG ĐƯỢC GHI ĐÈ. Sheet của anh đang thu gọn nhiều nhóm cột; xuất ra một file
   thiếu cột rồi nạp đè là xoá trắng CCCD, lương, số tài khoản mà màn hình vẫn báo "cập nhật". */
global $wpdb;
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'so_tai_khoan' => '0123456789',
	'luong_co_ban' => 8500000, 'sdt' => '0909123456' ), array( 'ma_nv' => 'MNNV2MTD0001' ) );
$csv_thieu = "Mã NV,Họ tên,Cửa hàng,CCCD,PIN đăng nhập\n"
	. "MNNV2MTD0001,Nguyễn Thu Hiền,JP_HCM,,170412\n";
$r = VHCC_NapCsv::nap( $csv_thieu, false );
$hs = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MNNV2MTD0001'" );
$hs = $hs[0];
teq( 'nạp file THIẾU CỘT không xoá số tài khoản đang có', '0123456789', $hs['so_tai_khoan'] );
teq( 'không xoá lương đang có', 8500000, (int) $hs['luong_co_ban'] );
teq( 'không xoá số điện thoại đang có', '0909123456', $hs['sdt'] );
teq( 'ô CCCD BỎ TRỐNG trong file cũng không xoá CCCD đang có', '049304007231', $hs['cccd'] );
teq( 'và là CẬP NHẬT chứ không thêm người thứ hai', 4,
	count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
/* Nạp lại y hệt thì KHÔNG tính là "cập nhật". Con số "cập nhật 240" mà thật ra không ô nào đổi
   là con số vô nghĩa, và nó che mất lượt nạp thật sự đổi gì. */
teq( 'nạp lại y hệt thì KHÔNG tính là sửa', 0, $r['sua'] );
teq( 'không thêm ai', 0, $r['them'] );

/* 🔴 XEM TRƯỚC PHẢI CHỈ RÕ TỪNG Ô ĐỔI GÌ. Anh Thắng: "nạp bên trong này sai hết dữ liệu" —
   một con số "cập nhật 240" không cho biết nó sắp làm gì. Có `cũ -> mới` thì sai bản đồ cột
   lộ ra NGAY Ở BƯỚC XEM TRƯỚC, chứ không phải sau khi đã ghi đè. */
$r = VHCC_NapCsv::nap( "Mã NV,Họ tên,Cửa hàng,PIN đăng nhập\nMNNV2MTD0001,Nguyễn Thu Hiền,POSH_HCM,999888\n", true );
teq( 'xem trước: có 1 hồ sơ đổi', 1, $r['sua'] );
t( 'và chỉ rõ ô nào, cũ gì mới gì',
	isset( $r['doi']['MNNV2MTD0001']['o']['cua_hang'] )
	&& 'JP_HCM' === $r['doi']['MNNV2MTD0001']['o']['cua_hang']['cu']
	&& 'POSH_HCM' === $r['doi']['MNNV2MTD0001']['o']['cua_hang']['moi'], $r['doi'] );
t( 'ô KHÔNG đổi thì không kể vào',
	! isset( $r['doi']['MNNV2MTD0001']['o']['ho_ten'] ), $r['doi'] );

/* 🔴 HOÀN TÁC. Ghi đè 240 hồ sơ mà không có đường lùi thì một lần bấm nhầm là mất dữ liệu thật. */
VHCC_NapCsv::nap( "Mã NV,Họ tên,Cửa hàng,PIN đăng nhập\nMNNV2MTD0001,Nguyễn Thu Hiền,POSH_HCM,999888\nZZ9,Người Mới Toanh,FARM_PT,111222\n", false );
$hs = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MNNV2MTD0001'" );
teq( 'nạp thật thì cửa hàng đã đổi', 'POSH_HCM', $hs[0]['cua_hang'] );
teq( 'và có thêm người mới', 5, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
t( 'có lượt nạp để hoàn tác', ! empty( VHCC_NapCsv::co_lui() ) );
$r = VHCC_NapCsv::lui();
t( 'hoàn tác chạy được', ! empty( $r['ok'] ), $r );
$hs = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MNNV2MTD0001'" );
teq( 'cửa hàng trả về giá trị CŨ', 'JP_HCM', $hs[0]['cua_hang'] );
teq( 'PIN cũng trả về giá trị CŨ', '170412', $hs[0]['pin_dang_nhap'] );
teq( 'người mới thêm bị xoá đi', 4, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
/* 🔴 Hoàn tác chỉ trả lại NHỮNG Ô lượt nạp đó động vào — ô người ta sửa tay sau đó phải còn. */
teq( 'ô KHÔNG thuộc lượt nạp thì giữ nguyên, không bị chép đè', '0909123456', $hs[0]['sdt'] );
t( 'chỉ lùi được MỘT bước, lùi tiếp thì nói thẳng',
	empty( VHCC_NapCsv::lui()['ok'] ) );

/* 🔴 3. SỐ 0 Ở ĐẦU PIN. Sheets coi PIN là SỐ nên `013013` ra `13013`, `246813` ra `246813.0`.
   Đuôi .0 cắt được; số 0 đầu MẤT RỒI thì không dựng lại — chỉ dám CẢNH BÁO, không tự thêm vào. */
vhcc_dung_bang();
$csv_pin = "Mã NV,Họ tên,PIN đăng nhập\n"
	. "A1,Người Một,170412\nA2,Người Hai,250904\nA3,Người Ba,782006\n"
	. "A4,Người Bốn,888999\nA5,Người Năm,150296\nA6,Người Sáu,13013\nA7,Người Bảy,246813.0\n";
$r = VHCC_NapCsv::nap( $csv_pin, false );
$hs = VHCC_DB::rows( 'SELECT ma_nv, pin_dang_nhap FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='A7'" );
teq( 'cắt đuôi .0 của Sheets', '246813', $hs[0]['pin_dang_nhap'] );
$hs = VHCC_DB::rows( 'SELECT ma_nv, pin_dang_nhap FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='A6'" );
teq( 'PIN ngắn giữ NGUYÊN, KHÔNG tự thêm số 0 vào', '13013', $hs[0]['pin_dang_nhap'] );
t( 'nhưng CẢNH BÁO là Sheets có thể đã cắt số 0 đầu',
	strpos( implode( ' | ', (array) $r['canh'] ), 'số 0 ở đầu' ) !== false, $r['canh'] );

/* 🔴 4. CCCD KHÔNG BAO GIỜ ĐƯỢC HIỂU NHẦM LÀ PIN. Nhầm một cái là số căn cước của người ta
   thành mật khẩu đăng nhập. */
vhcc_dung_bang();
VHCC_NapCsv::nap( "Mã NV,Họ tên,CCCD,PIN đăng nhập\nB1,Người Tám,049304007231,170412\n", false );
$hs = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='B1'" );
teq( 'CCCD vào đúng cột CCCD', '049304007231', $hs[0]['cccd'] );
teq( 'và PIN vào đúng cột PIN', '170412', $hs[0]['pin_dang_nhap'] );

/* Ngày kiểu SÊ-RI của bảng tính. Đọc thẳng số đó như năm là ra năm 4623 — đúng lỗi đã làm cả
   loạt đơn chi phí tháng 7 biến mất khỏi bộ lọc. */
teq( 'sê-ri 46232.6543 ra đúng ngày', '2026-07-29', VHCC_NapCsv::ngay( '46232.6543' ) );
teq( 'và kèm đúng giờ', '2026-07-29 15:42:12', VHCC_NapCsv::gio( '46232.6543' ) );
t( 'số ngoài khoảng ngày hợp lý thì KHÔNG đoán bừa', null === VHCC_NapCsv::seri( '99999' ) );
teq( 'ngày dd/mm/yyyy vẫn đọc được', '2026-07-29', VHCC_NapCsv::ngay( '29/07/2026' ) );
t( 'ngày không đọc được thì để TRỐNG, không đoán', null === VHCC_NapCsv::ngay( 'hôm qua' ) );

/* Tiền kiểu Việt. */
teq( 'lương 8.500.000 đọc đúng', 8500000.0, VHCC_NapCsv::tien( '8.500.000' ) );
teq( 'lương "8,500,000 đ" cũng đúng', 8500000.0, VHCC_NapCsv::tien( '8,500,000 đ' ) );

/* Không có cột Mã NV thì CHỐI — không có khoá thì nạp lần hai là nhân đôi cả sổ. */
$r = VHCC_NapCsv::nap( "Họ tên,PIN đăng nhập\nNgười Chín,170412\n", true );
t( 'thiếu cột Mã NV thì chối và nói rõ vì sao',
	empty( $r['ok'] ) && strpos( $r['error'], 'MÃ NV' ) !== false, $r );
/* Dòng lẻ thiếu Mã NV thì kêu đích danh, không nuốt. */
$r = VHCC_NapCsv::nap( "Mã NV,Họ tên,PIN đăng nhập\nC1,Người Mười,170412\n,Người Không Mã,250904\n", true );
teq( 'chỉ nhận dòng có Mã NV', 1, $r['them'] );
t( 'và kêu đích danh dòng thiếu mã',
	strpos( implode( ' | ', (array) $r['bo'] ), 'Người Không Mã' ) !== false, $r['bo'] );

/* Chỉ có tiêu đề, chưa có dòng nào -> chỉ đường tải .csv cho đúng. */
$r = VHCC_NapCsv::nap( "Mã NV,Họ tên,PIN đăng nhập\n", true );
t( 'file chỉ có tiêu đề thì chỉ đường tải .csv',
	empty( $r['ok'] ) && strpos( $r['error'], '.csv' ) !== false, $r );

/* Dán bằng TAB (Ctrl+C từ Sheets) cũng phải chạy, không chỉ dấu phẩy. */
vhcc_dung_bang();
$r = VHCC_NapCsv::nap( "Mã NV\tHọ tên\tPIN đăng nhập\nD1\tNgười TAB\t170412\n", false );
teq( 'dán bằng TAB cũng nạp được', 1, $r['them'] );
/* BOM của Excel không được dính vào tên cột đầu tiên. */
$r = VHCC_NapCsv::nap( "\xEF\xBB\xBFMã NV,Họ tên,PIN đăng nhập\nE1,Người BOM,250904\n", false );
teq( 'file có BOM của Excel vẫn nhận đúng cột đầu', 1, $r['them'] );

/* Lọc riêng từng cơ sở khi nạp .csv. */
vhcc_dung_bang();
$csv_2cs = "Mã NV,Họ tên,Cửa hàng,PIN đăng nhập\n"
	. "F1,Người JP,JP_HCM,170412\nF2,Người POSH,POSH_HCM,250904\nF3,Người POSH Hai,posh hcm,782006\n";
$r = VHCC_NapCsv::nap( $csv_2cs, false, 'POSH_HCM' );
teq( 'nạp riêng cơ sở POSH_HCM (kể cả gõ khác kiểu)', 2, $r['them'] );
teq( 'và bỏ qua cơ sở khác', 1, $r['lech'] );

/* ---- 46b. TỪ HỒ SƠ NHÂN SỰ -> TÀI KHOẢN ĐĂNG NHẬP ---- */
/* Đây là mắt xích cuối: cột "PIN đăng nhập" trong hồ sơ phải thành tài khoản đăng nhập được. */
vhcc_dung_bang();
delete_option( 'vhcc_nguoidung' );
delete_option( 'vhcc_vai_tro_vao' );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
VHCC_NapCsv::nap( $csv_nv, false );

$kho = VHCC_NguoiDung::do_kho_cu();
teq( 'kho "Hồ sơ Nhân sự" đếm đúng số người có PIN', 4, $kho['ho_so']['co'] );
teq( 'nhưng 0 người vào được — sổ ghi "Máy tự động", không phải vai trò', 0, $kho['ho_so']['vao'] );

/* 🔴 Không có ô "vai trò nếu sổ không ghi" thì cả sổ rơi về Nhân viên, nạp xong KHÔNG AI đăng
   nhập được, mà màn hình vẫn báo "đã nạp 4 người". Đúng kiểu hỏng im lặng phải chặn. */
$r = VHCC_NguoiDung::nap_tu_cu( 'ho_so', false, '', 'Quản lý' );
teq( 'nạp 4 tài khoản từ hồ sơ', 4, $r['them'] );
teq( 'và đếm được 4 dòng sổ không ghi vai trò', 4, $r['vt_trong'] );
teq( 'đã đặt theo vai trò mình chọn', 'Quản lý', $r['vt_mac_dinh'] );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '170412' );
t( 'đăng nhập được bằng ĐÚNG PIN trong cột "PIN đăng nhập"', ! empty( $kq['ok'] ), $kq );
teq( 'đúng tên trong hồ sơ', 'Nguyễn Thu Hiền', isset( $kq['name'] ) ? $kq['name'] : null );
teq( 'đúng cơ sở trong hồ sơ', 'JP_HCM', isset( $kq['coso'] ) ? $kq['coso'] : null );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '351604' );
t( 'người ở dòng có dấu phẩy trong ô cũng đăng nhập được', ! empty( $kq['ok'] ), $kq );
VHCC_Auth::mo_khoa();

/* Để mặc thì rơi về Nhân viên -> KHÔNG AI vào được; phải đếm ra để màn hình còn kêu lên. */
delete_option( 'vhcc_nguoidung' );
$r = VHCC_NguoiDung::nap_tu_cu( 'ho_so', false, '' );
teq( 'để mặc vai trò thì rơi về Nhân viên', 'Nhân viên', $r['vt_mac_dinh'] );
teq( 'và nạp xong KHÔNG AI vào được', 0, $r['vao'] );
teq( 'nhưng con số đó được đếm ra để màn hình kêu lên', 4, $r['vt_trong'] );

/* Nạp riêng một cơ sở từ hồ sơ. */
delete_option( 'vhcc_nguoidung' );
$r = VHCC_NguoiDung::nap_tu_cu( 'ho_so', false, 'JP_HCM', 'Quản lý' );
teq( 'nạp riêng cơ sở JP_HCM từ hồ sơ', 1, $r['them'] );
$cs = VHCC_NguoiDung::ds_coso_cu( 'ho_so' );
t( 'liệt kê được cơ sở của hồ sơ để đổ vào ô chọn', isset( $cs['POSH_HCM'] ), array_keys( $cs ) );

/* Màn Cài đặt phải có ô tải .csv, và ô đó KHÔNG được lồng trong form Cài đặt. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHCC_Admin::page(); $h_csv = ob_get_clean();
t( 'màn Cài đặt có ô tải file .csv', strpos( $h_csv, 'name="tep"' ) !== false );
t( 'và chỉ đúng đường xuất .csv trong Google Sheets',
	strpos( $h_csv, 'phân tách bằng dấu phẩy' ) !== false );
t( 'nói rõ ô trống KHÔNG ghi đè', strpos( $h_csv, 'Ô trống KHÔNG ghi đè' ) !== false );
t( 'có ô chọn vai trò cho dòng sổ không ghi', strpos( $h_csv, 'name="vt"' ) !== false );
/* 🔴 Ô required của việc tải file KHÔNG được rơi vào form Cài đặt — rơi vào là anh Thắng không
   bấm Lưu được nữa nếu chưa chọn file. Đây là lỗi đã xảy ra thật lúc làm mục này. */
t( 'ô tải file trỏ về form RIÊNG, không nằm trong form Cài đặt',
	preg_match( '/<input[^>]*name="tep"[^>]*form="vhcc-csv-nd"/', $h_csv ) === 1, $h_csv );
t( 'và form riêng đó có enctype để gửi được file',
	strpos( $h_csv, 'enctype="multipart/form-data" id="vhcc-csv-nd"' ) !== false );

vhcc_dung_bang();
delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'chung' );

// ============ 47. TRANG QUẢN TRỊ NGOÀI WEB — không phải vào wp-admin nữa
/* Anh Thắng: *"cho ra web để dễ thao tác được không"* và *"mọi việc anh thao tác trên web giao
   diện bên ngoài hết, không làm bên trong wp-admin"*.

   wp-admin đòi một tài khoản WordPress. Quản lý cửa hàng không có, và cũng không nên có — tài
   khoản wp-admin mở ra cả website chứ không riêng chấm công. Trang này gác bằng ĐÚNG cổng PIN
   chấm công. Mục này canh cái cổng đó, vì mở nhầm là lộ CCCD và lương của cả chuỗi. */
vhcc_dung_bang();
delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
delete_option( 'vhcc_vai_tro_vao' );
VHCC_NguoiDung::luu( '', 'Anh Admin', '246813', 'Admin', '' );
VHCC_NguoiDung::luu( '', 'Chị Quản Lý', '357913', 'Quản lý', 'TUTU_BT' );
VHCC_NguoiDung::luu( '', 'Chị Kế Toán', '468024', 'Kế toán cá nhân', 'TUTU_BT' );
VHCC_NapCsv::nap( "Mã NV,Họ tên,Cửa hàng,CCCD,PIN đăng nhập\n"
	. "W1,Nguyễn Thu Hiền,JP_HCM,049304007231,170412\n", false );

/**
 * Gọi một phương thức `private static` để thử THẲNG cái chốt, không phải thử qua màn hình.
 *
 * 🔴 Vì sao cần: chốt quyền của `lam_viec` là thứ đứng giữa một tài khoản Kế toán và lệnh xoá
 * sạch sổ nhân sự. Thử nó qua `phuc_vu()` thì lượt POST kết thúc bằng một cú chuyển hướng, và
 * cái mình đọc được chỉ là trang sau đó — chối hay không chối đều ra một màn hình gần giống
 * nhau. Gọi thẳng thì đọc được đúng câu trả lời của chốt.
 */
function vhcc_goi_rieng( $lop, $ham, $args ) {
	$m = new ReflectionMethod( $lop, $ham );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

function vhcc_web( $pin_vai_tro = null, $post = array(), $get = array() ) {
	$_POST = $post; $_GET = $get; $_COOKIE = array();
	if ( null !== $pin_vai_tro ) {
		VHCC_Auth::mo_khoa();
		$kq = VHCC_Auth::login( $pin_vai_tro );
		if ( ! empty( $kq['ok'] ) ) { $_COOKIE[ VHCC_Web::COOKIE ] = $kq['token']; }
		VHCC_Auth::mo_khoa();
	}
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_POST = array(); $_GET = array(); $_COOKIE = array();
	return $h;
}

/* 🔴 CHƯA ĐĂNG NHẬP thì chỉ được thấy ô PIN — không một mẩu hồ sơ nào. */
$h_w = vhcc_web();
t( 'chưa đăng nhập thì hiện ô PIN', strpos( $h_w, 'name="pin"' ) !== false );
t( 'và KHÔNG lộ tên nhân viên nào', strpos( $h_w, 'Nguyễn Thu Hiền' ) === false );
t( 'không lộ CCCD', strpos( $h_w, '049304007231' ) === false );
t( 'không lộ mã nhân viên', strpos( $h_w, 'W1' ) === false );
t( 'trang quản trị chặn công cụ tìm kiếm', strpos( $h_w, 'noindex' ) !== false );

$h_w = vhcc_web( null, array( 'pin' => '999999' ) );
t( 'PIN sai thì vẫn ở màn đăng nhập', strpos( $h_w, 'name="pin"' ) !== false );

/* ===========================================================================
 *  MÀN THEO BẬC QUYỀN  —  đổi 25/08/2026 (mô hình năm bậc anh Thắng chốt)
 * ---------------------------------------------------------------------------
 *  Cửa VÀO rộng: ai có PIN cũng vào được, kể cả Nhân viên — việc họ cần (chấm công của mình,
 *  xem công của mình) nằm ngay trong trang. Cửa TỪNG MÀN hẹp, theo bậc:
 *
 *      Nhân viên        -> chỉ "Công của tôi"
 *      Cửa hàng trưởng  -> thêm "Bảng chấm công" (cơ sở mình)
 *      Kế toán          -> thêm "Hồ sơ & tài khoản"
 *      Admin            -> thêm khối hệ thống (nguồn người dùng, xoá sạch, khai Admin)
 *
 *  🔴 Chỗ nguy hiểm: mọi việc của màn hồ sơ (nạp .csv đè cả sổ nhân sự, cấp PIN, đổi vai trò,
 *     xoá hết) trước kia được giữ bởi CỬA VÀO. Cửa vào đã mở, nên `lam_viec` phải tự gác. Mấy
 *     phép dưới canh đúng chuyện ấy — bằng cách GỬI THẬT một lượt POST, không phải chỉ nhìn
 *     màn hình: màn hình ẩn được cái nút, nhưng người ta dựng form ở đâu cũng gửi lên được.
 * ======================================================================== */
VHCC_NguoiDung::luu( '', 'Anh CHT', '579135', 'Cửa hàng trưởng', 'TUTU_BT' );

$h_w = vhcc_web( '579135' );
t( 'Cửa hàng trưởng mang phiên thật thì VÀO ĐƯỢC',
	strpos( $h_w, 'name="pin"' ) === false, $h_w );
t( 'và thấy màn Bảng chấm công', strpos( $h_w, 'Bảng chấm công' ) !== false );
t( 'nhưng KHÔNG thấy một mẩu hồ sơ nào', strpos( $h_w, 'Nguyễn Thu Hiền' ) === false, $h_w );
t( 'không thấy CCCD', strpos( $h_w, '049304007231' ) === false );
t( 'không có nút vào màn Hồ sơ', strpos( $h_w, 'Hồ sơ &amp; tài khoản' ) === false, $h_w );
/* Ép `man=ho_so` trên thanh địa chỉ cũng KHÔNG mở được — nếu chỉ ẩn cái nút thì đây là cửa hở. */
$h_w = vhcc_web( '579135', array(), array( 'man' => 'ho_so' ) );
t( 'gõ tay ?man=ho_so cũng không mở được hồ sơ',
	strpos( $h_w, 'Nguyễn Thu Hiền' ) === false, $h_w );

/* 🔴 GỬI THẬT lượt POST việc hồ sơ bằng phiên Cửa hàng trưởng. */
$U_W_CHT = array( 'name' => 'Anh CHT', 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT' );
foreach ( array( 'nap_csv', 'xoa_het', 'doi_nguon', 'khai_admin', 'nap_tk', 'sua_hs', 'doi_ma' ) as $v_kt ) {
	$_POST = array( 'viec' => $v_kt );
	$r_kt  = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec', array( $v_kt, $U_W_CHT ) );
	t( 'Cửa hàng trưởng POST việc "' . $v_kt . '" bị chối',
		is_array( $r_kt ) && isset( $r_kt[0]['loi'] )
		&& strpos( $r_kt[0]['loi'], 'Kế toán trở lên' ) !== false, $r_kt );
}

/* NHÂN VIÊN: bậc thấp nhất — vào được, nhưng chỉ có đúng một màn. */
VHCC_NguoiDung::luu( '', 'Em Nhân Viên Web', '680246', 'Nhân viên', 'TUTU_BT' );
$h_w = vhcc_web( '680246' );
t( 'Nhân viên vào được (trước đây bị chối ngay cổng)',
	strpos( $h_w, 'name="pin"' ) === false, $h_w );
t( 'và thấy màn Công của tôi', strpos( $h_w, 'Công của tôi' ) !== false, $h_w );
t( 'KHÔNG có nút Bảng chấm công', strpos( $h_w, '>Bảng chấm công<' ) === false, $h_w );
t( 'KHÔNG thấy hồ sơ ai', strpos( $h_w, 'Nguyễn Thu Hiền' ) === false );
$h_w = vhcc_web( '680246', array(), array( 'man' => 'cham' ) );
t( 'gõ tay ?man=cham cũng không mở được bảng công cơ sở',
	strpos( $h_w, 'chọn cơ sở' ) === false, $h_w );
foreach ( array( 'co', 'xu_ly_co', 'sua_hs' ) as $v_nv ) {
	$_POST = array( 'viec' => $v_nv );
	$r_nv  = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
		array( $v_nv, array( 'name' => 'Em NV', 'role' => 'Nhân viên', 'coso' => 'TUTU_BT' ) ) );
	t( 'Nhân viên POST việc "' . $v_nv . '" không làm gì được',
		is_array( $r_nv ) && ( ! isset( $r_nv[0]['xong'] ) ), $r_nv );
}

/* KẾ TOÁN: "full quyền ngoài admin" — hồ sơ mở, khối hệ thống thì không. */
$h_w = vhcc_web( '468024' );
t( 'Kế toán thấy màn Hồ sơ & tài khoản', strpos( $h_w, 'Hồ sơ &amp; tài khoản' ) !== false, $h_w );
foreach ( array( 'xoa_het', 'doi_nguon', 'khai_admin', 'doi_ma' ) as $v_ad ) {
	$_POST = array( 'viec' => $v_ad );
	$r_ad  = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
		array( $v_ad, array( 'name' => 'Chị KT', 'role' => 'Kế toán cá nhân', 'coso' => '' ) ) );
	t( 'nhưng việc hệ thống "' . $v_ad . '" vẫn chỉ Admin',
		is_array( $r_ad ) && isset( $r_ad[0]['loi'] )
		&& strpos( $r_ad[0]['loi'], 'Chỉ Admin' ) !== false, $r_ad );
}
$_POST = array(); $_COOKIE = array();

/* Admin thì vẫn đủ hai màn, y như trước. */
$h_w = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'Admin thấy thanh chọn màn', strpos( $h_w, 'Hồ sơ &amp; tài khoản' ) !== false, $h_w );
t( 'Admin mặc định vào màn hồ sơ', strpos( $h_w, 'Nguyễn Thu Hiền' ) !== false );
$h_w = vhcc_web( '246813', array(), array( 'man' => 'cham' ) );
t( 'Admin mở được màn Bảng chấm công', strpos( $h_w, 'chỉ đọc</b> giờ chấm công' ) !== false, $h_w );
t( 'màn bảng công KHÔNG có ô nhập giờ nào',
	! preg_match( '/name="(gio_vao|gio_ra|vao|ra)"/', $h_w ), $h_w );
/* ⚠️ TÊN NGƯỜI KHÔNG CÒN LÀ DẤU HIỆU PHÂN BIỆT HAI MÀN. Từ 26/08/2026 lưới cả tháng có kéo
   thêm người từ sổ nhân sự vào (ai cả tháng chưa chấm lần nào vẫn phải có một hàng để bấm bù),
   nên tên người xuất hiện ở CẢ hai màn là đúng. Thứ phải không có ở màn chấm công là các Ô SỬA
   HỒ SƠ — CCCD, số điện thoại, PIN, lương. */
t( 'màn chấm công KHÔNG có ô sửa hồ sơ nào',
	! preg_match( '/name="(cccd|sdt|pin|luong_[a-z_]+|ma_moi)"/', $h_w ), $h_w );
VHCC_Auth::mo_khoa();
$kq_kt = VHCC_Auth::login( '468024' );
VHCC_Auth::mo_khoa();
$_COOKIE[ VHCC_Web::COOKIE ] = $kq_kt['token'];
t( 'phiên của Kế toán KHÔNG mở được trang quản trị', null === VHCC_Web::toi() );
$_COOKIE = array();
/* Nhưng chính token đó VẪN dùng được ở cổng /cham-cong — chốt này chỉ hẹp cho trang quản trị,
   không được siết luôn cả hệ thống. */
$u_kt = VHCC_Auth::user_by_token( $kq_kt['token'] );
t( 'mà token đó vẫn hợp lệ ở cổng chấm công', is_array( $u_kt ) && 'Kế toán cá nhân' === $u_kt['role'], $u_kt );

/* Quản lý và Admin vào được. */
/* Quản lý vào được, nhưng KHÔNG thấy hồ sơ — hồ sơ thuộc bậc Kế toán. */
$h_w = vhcc_web( '357913' );
t( 'Quản lý vào được', strpos( $h_w, 'name="pin"' ) === false, $h_w );
t( 'nhưng Quản lý KHÔNG thấy hồ sơ', strpos( $h_w, 'Nguyễn Thu Hiền' ) === false );
t( 'nhưng KHÔNG thấy nút xoá sạch hồ sơ', strpos( $h_w, 'value="xoa_het"' ) === false );
t( 'và không khai được Admin', strpos( $h_w, 'value="khai_admin"' ) === false );

$h_w = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'Admin vào được', strpos( $h_w, 'Nguyễn Thu Hiền' ) !== false );
t( 'có ô nạp file .csv ngay trên web', strpos( $h_w, 'name="tep"' ) !== false );
t( 'có nút xem trước', strpos( $h_w, 'value="xem_csv"' ) !== false );
t( 'có nút xoá sạch hồ sơ', strpos( $h_w, 'value="xoa_het"' ) !== false );
t( 'có nút khai tài khoản Admin', strpos( $h_w, 'value="khai_admin"' ) !== false );
t( 'có nút nạp tài khoản đăng nhập từ hồ sơ', strpos( $h_w, 'value="nap_tk"' ) !== false );
/* 🔴 KHÔNG IN PIN. Trang này chạy ngoài internet, ảnh chụp đi khắp nơi. */
t( 'KHÔNG in PIN của nhân viên ra', strpos( $h_w, '170412' ) === false, $h_w );
t( 'chỉ cho biết PIN có mấy số', strpos( $h_w, '6 số' ) !== false );
t( 'cũng không in PIN đăng nhập của chính người đang xem', strpos( $h_w, '246813' ) === false );
/* Mã NV không sửa được ngoài web — đổi mã là sửa mọi hàng chấm công đã có của người đó. */
t( 'KHÔNG cho sửa Mã NV', ! in_array( 'ma_nv', VHCC_Web::COT_SUA, true ) );

/* ---- 47d. SỬA HỒ SƠ NGAY TRÊN BẢNG: PIN, VAI TRÒ, VÀ Ô CHO CHỌN ---- */
/* Anh Thắng: *"hồ sơ nhân sự chưa có cột PIN"*, *"cột nhiệm vụ cũng chưa có để chị"*, *"chọn"*,
   và *"Nhiệm vụ: Nhân Viên, Admin, Cửa Hàng Trưởng, Kế Toán"*. */
$h_w = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'có Ô NHẬP PIN ngay tại dòng', strpos( $h_w, 'name="pin_dang_nhap[W1]"' ) !== false );
t( 'nhưng KHÔNG điền sẵn PIN cũ vào ô', strpos( $h_w, 'value="170412"' ) === false, $h_w );
t( 'có ô tích XOÁ PIN cho người đã có PIN', strpos( $h_w, 'name="xoa_pin[W1]"' ) !== false );
t( 'có ô chọn VAI TRÒ ĐĂNG NHẬP riêng', strpos( $h_w, 'name="vai_tro[W1]"' ) !== false );
t( 'vai trò là danh sách ĐÓNG (select), không phải ô gõ tự do',
	preg_match( '/<select[^>]*name="vai_tro\[W1\]"/', $h_w ) === 1 );

/* 🔴 MỘT FORM CHO CẢ BẢNG, MỘT NÚT LƯU. Anh Thắng: *"bấm khai 1 lần và lưu 1 lần được không"*.
   237 người cần khai vai trò; bấm Lưu 237 lần, mỗi lần một vòng tải trang, là việc không ai làm
   xong — và làm dở nửa chừng thì không ai biết đã tới đâu. */
t( 'cả bảng nằm trong MỘT form', strpos( $h_w, 'id="vhcc-bang"' ) !== false );
t( 'mọi ô trỏ về form đó', substr_count( $h_w, 'form="vhcc-bang"' ) >= 7, $h_w );
t( 'có MỘT nút Lưu tất cả', strpos( $h_w, 'Lưu tất cả' ) !== false );
t( 'KHÔNG còn nút Lưu từng dòng', strpos( $h_w, '>Lưu</button>' ) === false, $h_w );
t( 'có nút đặt Vai trò hàng loạt', strpos( $h_w, 'value="vai_tro_hang_loat"' ) !== false );
t( 'và nói rõ phạm vi là các dòng ĐANG HIỆN',
	strpos( $h_w, 'dòng đang hiện' ) !== false && strpos( $h_w, 'không phải cả sổ' ) !== false );
/* Cột vai trò nay in BẬC, không in "(không vào được)" — ai cũng vào được, khác nhau ở bậc. */
t( 'và nói rõ bậc của từng vai trò', strpos( $h_w, 'bậc' ) !== false, $h_w );
/* Bốn ô cho CHỌN, nhưng vẫn gõ được giá trị mới -> datalist, không phải select. */
foreach ( array( 'dl_ch' => 'cua_hang', 'dl_cv' => 'chuc_vu', 'dl_nv' => 'nhiem_vu',
	'dl_cp' => 'coso_phu' ) as $dl => $o_ten ) {
	t( "ô $o_ten xổ ra danh sách đang dùng",
		strpos( $h_w, 'id="' . $dl . '"' ) !== false
		&& preg_match( '/name="' . $o_ten . '\[[^\]]+\]"[^>]*list="' . $dl . '"/', $h_w ) === 1, $h_w );
}
/* 🔴 DANH SÁCH NHIỆM VỤ DO NGƯỜI KHAI, KHÔNG GOM TỪ DỮ LIỆU. Gom tự động thì cột Nhiệm vụ của
   sổ cũ đang lẫn TÊN CƠ SỞ ("JP Aeon Mall Tân Phú", "JP VINCOM 3/2") và chúng trôi hết vào danh
   sách xổ ra — anh Thắng: *"1 cái đầu với 3 cái cuối là nhiệm vụ, còn mấy cái khác không phải"*.
   Một danh sách gợi ý mà 2/3 là rác thì tệ hơn không có: nó mời người ta bấm nhầm. */
foreach ( array( 'Admin', 'Kế Toán', 'Nhân Viên', 'Thu Tiền' ) as $g ) {
	t( "danh sách Nhiệm vụ có \"$g\"",
		strpos( $h_w, '<option value="' . $g . '">' ) !== false, $h_w );
}
$wpdb->query( "UPDATE " . VHCC_DB::t( 'nhan_vien' ) . " SET nhiem_vu='JP Aeon Mall Tân Phú'" );
$h_nv = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'tên cơ sở trong cột Nhiệm vụ KHÔNG trôi vào danh sách xổ ra',
	strpos( $h_nv, '<option value="JP Aeon Mall Tân Phú">' ) === false, $h_nv );
$wpdb->query( "UPDATE " . VHCC_DB::t( 'nhan_vien' ) . " SET nhiem_vu=''" );
/* Và anh Thắng sửa được danh sách đó ngay trên trang, không phải nhờ em sửa mã. */
t( 'có ô sửa danh sách Nhiệm vụ ngay trên trang', strpos( $h_w, 'name="ds_nv"' ) !== false );
update_option( VHCC_Web::O_NHIEM_VU, "Trực Ghế\nThu Tiền\n\nTrực Ghế\n" );
teq( 'sửa xong thì danh sách theo đúng cái đã khai', array( 'Trực Ghế', 'Thu Tiền' ),
	VHCC_Web::ds_nhiem_vu() );
delete_option( VHCC_Web::O_NHIEM_VU );

/* 🔴 XEM PIN — TỪNG NGƯỜI MỘT, KHÔNG BAO GIỜ CẢ BẢNG.
   Anh Thắng: *"sao lưu ok, nhưng chỗ ô pin vẫn trống"*. Anh cần đọc PIN để báo lại cho nhân
   viên — việc thật. Nhưng in cả 240 PIN ra một màn hình thì một ảnh chụp là mất sạch mật khẩu
   cả chuỗi; chính dự án này đã mất một khoá cầu nối vì một ảnh gửi qua chat. Nên: bấm ở ĐÚNG
   dòng cần xem, và chỉ dòng đó hiện. */
t( 'Admin có nút xem PIN từng dòng', strpos( $h_w, '👁' ) !== false, $h_w );

/* 🔴 "AI CÓ PIN, AI CHƯA" phải NHÌN LƯỚT LÀ THẤY và LỌC RA ĐƯỢC. Anh Thắng: *"cần hiện để biết
   ai có pin chưa"*. Soi 240 dòng chữ xám nhỏ để tìm người còn thiếu là việc không ai làm nổi —
   mà bỏ sót một người thì tháng sau người đó không đăng nhập được, và cũng không ai biết vì sao. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv<>'W1'" );
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'vai_tro' => 'Quản lý' ), array( 'ma_nv' => 'W1' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'K1', 'ho_ten' => 'Chưa Có Pin',
	'cua_hang' => 'JP_HCM', 'pin_dang_nhap' => '', 'vai_tro' => 'Quản lý' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'K2', 'ho_ten' => 'Có Pin Chưa Vai Trò',
	'cua_hang' => 'JP_HCM', 'pin_dang_nhap' => '246813', 'vai_tro' => '' ) );

$h_d = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'có bộ đếm ai vào được / ai chưa', strpos( $h_d, '1/3</b> người đăng nhập được' ) !== false, $h_d );
t( 'đếm đúng số người CHƯA có PIN', strpos( $h_d, '<b>1</b> chưa có PIN' ) !== false, $h_d );
t( 'và có đường bấm thẳng sang danh sách người chưa vào được',
	strpos( $h_d, 'xem 2 người chưa vào được' ) !== false, $h_d );
t( 'người có PIN hiện dấu ✔', strpos( $h_d, '✔ có 6 số' ) !== false );
t( 'người CHƯA có PIN hiện dấu ✖ màu đỏ', strpos( $h_d, '✖ chưa có PIN' ) !== false );
t( 'người chưa khai vai trò cũng hiện ✖', strpos( $h_d, '✖ chưa khai' ) !== false );

/* Lọc ra đúng nhóm cần xử. */
$h_l = vhcc_web( '246813', array(), array( 'man' => 'ho_so', 'loc' => 'chua_pin'  ) );
t( 'lọc "chưa có PIN" ra đúng người đó', strpos( $h_l, 'Chưa Có Pin' ) !== false
	&& strpos( $h_l, 'Nguyễn Thu Hiền' ) === false, $h_l );
$h_l = vhcc_web( '246813', array(), array( 'man' => 'ho_so', 'loc' => 'chua_vt'  ) );
t( 'lọc "chưa khai vai trò" ra đúng người đó', strpos( $h_l, 'Có Pin Chưa Vai Trò' ) !== false
	&& strpos( $h_l, 'Chưa Có Pin' ) === false, $h_l );
/* 🔴 Câu hỏi THẬT không phải "có PIN chưa" mà "VÀO ĐƯỢC chưa": thiếu PIN, HOẶC vai trò nằm
   ngoài nhóm được vào — cả hai đều là không đăng nhập được. */
$h_l = vhcc_web( '246813', array(), array( 'man' => 'ho_so', 'loc' => 'chua_vao'  ) );
t( 'lọc "chưa đăng nhập được" gom CẢ hai kiểu thiếu',
	strpos( $h_l, 'Chưa Có Pin' ) !== false && strpos( $h_l, 'Có Pin Chưa Vai Trò' ) !== false
	&& strpos( $h_l, 'Nguyễn Thu Hiền' ) === false, $h_l );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('K1','K2')" );
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'vai_tro' => '' ), array( 'ma_nv' => 'W1' ) );
/* 🔴 PHẢI CÓ HAI NGƯỜI CÓ PIN mới thử được điều đáng thử: bấm xem MỘT người thì người kia
   VẪN PHẢI ẨN. Một người thì "hiện đúng người đó" và "hiện cả bảng" trông y hệt nhau, và phép
   thử không phân biệt được — em đã vấp đúng chỗ này lúc phá mã. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'W8', 'ho_ten' => 'Người Kia',
	'cua_hang' => 'JP_HCM', 'pin_dang_nhap' => '864209', 'vai_tro' => 'Quản lý' ) );
$h_p = vhcc_web( '246813', array(), array( 'man' => 'ho_so', 'pin' => 'W1'  ) );
t( 'bấm xem thì PIN của ĐÚNG người đó hiện ra', strpos( $h_p, '170412' ) !== false, $h_p );
t( '🔴 nhưng PIN người KHÁC vẫn ẩn — không lộ cả bảng',
	strpos( $h_p, '864209' ) === false, $h_p );
t( 'và có đường ẩn lại', strpos( $h_p, '>ẩn</a>' ) !== false );
$h_p2 = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'không bấm gì thì KHÔNG PIN nào hiện',
	strpos( $h_p2, '170412' ) === false && strpos( $h_p2, '864209' ) === false, $h_p2 );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='W8'" );
/* Vẫn KHÔNG có nút "hiện hết" — đó mới là thứ một ảnh chụp lấy được cả chuỗi. */
t( 'KHÔNG có nút hiện tất cả PIN', stripos( $h_w, 'hiện hết' ) === false
	|| strpos( $h_w, 'Cố ý không có nút' ) !== false );
/* Quản lý KHÔNG xem được PIN của người khác, dù gõ thẳng địa chỉ. */
$h_p = vhcc_web( '357913', array(), array( 'man' => 'ho_so', 'pin' => 'W1'  ) );
t( 'Quản lý gõ thẳng địa chỉ cũng KHÔNG xem được PIN', strpos( $h_p, '170412' ) === false, $h_p );
t( 'và Quản lý cũng không thấy nút xem', strpos( $h_p, '👁 xem' ) === false );

/* --- Lưu PIN --- */
function vhcc_luu_hs( $tok, $post ) {
	$_COOKIE[ VHCC_Web::COOKIE ] = $tok;
	$_POST = array_merge( array( 'viec' => 'sua_hs', 'ky' => VHCC_Web::chu_ky( $tok ) ), $post );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_POST = array(); $_COOKIE = array();
	return $h;
}
/** Lưu qua BẢNG — đường thật của màn danh sách: ô tên kiểu `truong[MÃ]`, một lượt cả bảng. */
function vhcc_luu_bang( $tok, $o, $them = array() ) {
	$_COOKIE[ VHCC_Web::COOKIE ] = $tok;
	$_POST = array_merge( array( 'viec' => 'luu_nhieu', 'ky' => VHCC_Web::chu_ky( $tok ) ), $o, $them );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_POST = array(); $_COOKIE = array();
	return $h;
}
function vhcc_hs( $ma ) {
	$r = VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='" . $ma . "'" );
	return $r ? $r[0] : null;
}
VHCC_Auth::mo_khoa();
$tok_ad = VHCC_Auth::login( '246813' )['token'];
VHCC_Auth::mo_khoa();

vhcc_luu_bang( $tok_ad, array( 'pin_dang_nhap' => array( 'W1' => '975310' ) ) );
teq( 'gõ PIN mới thì đổi', '975310', vhcc_hs( 'W1' )['pin_dang_nhap'] );

/* 🔴 Ô PIN TRỐNG = GIỮ NGUYÊN, không phải xoá. Ô này không bao giờ điền sẵn PIN cũ, nên "trống"
   là trạng thái BÌNH THƯỜNG của nó — coi trống là xoá thì mỗi lần sửa tên là một người mất
   đường đăng nhập, mà màn hình vẫn báo "Đã lưu". */
vhcc_luu_bang( $tok_ad, array( 'ho_ten' => array( 'W1' => 'Nguyễn Thu Hiền B' ),
	'pin_dang_nhap' => array( 'W1' => '' ) ) );
teq( 'để trống ô PIN thì GIỮ NGUYÊN PIN cũ', '975310', vhcc_hs( 'W1' )['pin_dang_nhap'] );
teq( 'mà tên vẫn đổi được', 'Nguyễn Thu Hiền B', vhcc_hs( 'W1' )['ho_ten'] );

/* Xoá PIN phải là việc CÓ Ý — tích ô. */
vhcc_luu_bang( $tok_ad, array( 'pin_dang_nhap' => array( 'W1' => '' ),
	'xoa_pin' => array( 'W1' => '1' ) ) );
teq( 'tích ô xoá thì mới bỏ PIN', '', vhcc_hs( 'W1' )['pin_dang_nhap'] );

/* PIN sai khuôn thì CHỐI RIÊNG DÒNG ĐÓ, không lưu nửa vời dòng đó — nhưng cũng KHÔNG được làm
   hỏng cả lượt gửi. Bắt làm lại từ đầu cả trăm dòng vì một PIN gõ nhầm là cách chắc nhất để
   người ta thôi dùng nút này. */
vhcc_luu_bang( $tok_ad, array( 'pin_dang_nhap' => array( 'W1' => '975310' ) ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'W7', 'ho_ten' => 'Người Lành' ) );
$h_w = vhcc_luu_bang( $tok_ad, array(
	'ho_ten'        => array( 'W1' => 'Tên Mới Toanh', 'W7' => 'Người Lành Sửa' ),
	'pin_dang_nhap' => array( 'W1' => '12' ) ) );
t( 'PIN sai khuôn thì báo rõ và kêu tên', strpos( $h_w, '4–8 chữ số' ) !== false
	&& strpos( $h_w, 'Nguyễn Thu Hiền B' ) !== false, $h_w );
teq( 'dòng hỏng KHÔNG lưu nửa vời (tên cũng không đổi)', 'Nguyễn Thu Hiền B', vhcc_hs( 'W1' )['ho_ten'] );
teq( '🔴 nhưng dòng LÀNH trong cùng lượt gửi VẪN được lưu', 'Người Lành Sửa', vhcc_hs( 'W7' )['ho_ten'] );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='W7'" );

/* 🔴 HAI NGƯỜI CÙNG PIN: cổng đăng nhập nhận người gặp trước, và nhật ký ghi tên người đó —
   người kia làm gì cũng mang tên người này. */
VHCC_NapCsv::nap( "Mã NV,Họ tên,PIN đăng nhập\nW9,Người Thứ Hai,468024\n", false );
$h_w = vhcc_luu_bang( $tok_ad, array( 'pin_dang_nhap' => array( 'W9' => '975310' ) ) );
t( 'chối PIN trùng, và nói rõ trùng với AI',
	strpos( $h_w, 'trùng với' ) !== false && strpos( $h_w, 'Nguyễn Thu Hiền B' ) !== false, $h_w );
/* 🔴 Trùng PIN GIỮA HAI DÒNG TRONG CÙNG MỘT LƯỢT GỬI cũng phải bắt — chỉ so với cơ sở dữ liệu
   thì hai dòng mới cùng gõ một PIN sẽ lọt cả hai, và ai đăng nhập cũng ra người gặp trước. */
$h_w = vhcc_luu_bang( $tok_ad, array( 'pin_dang_nhap' => array( 'W1' => '864200', 'W9' => '864200' ) ) );
$so_864 = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' )
	. " WHERE pin_dang_nhap='864200'" );
teq( 'hai dòng cùng lượt gõ trùng PIN thì chỉ MỘT được nhận', 1, $so_864 );
teq( 'và KHÔNG ghi đè PIN của người thứ hai', '468024', vhcc_hs( 'W9' )['pin_dang_nhap'] );

/* 🔴 CHỈ GHI DÒNG THẬT SỰ ĐỔI. Ghi đè cả trăm dòng bằng đúng giá trị cũ thì cột cap_nhat của
   mọi người nhảy về hôm nay, và sau đó không còn cách nào biết hồ sơ nào mới sửa thật. */
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'cap_nhat' => '2020-01-01 00:00:00' ),
	array( 'ma_nv' => 'W1' ) );
$h_w = vhcc_luu_bang( $tok_ad, array( 'ho_ten' => array( 'W1' => vhcc_hs( 'W1' )['ho_ten'] ) ) );
teq( 'gửi lại đúng giá trị cũ thì KHÔNG ghi gì', '2020-01-01 00:00:00', vhcc_hs( 'W1' )['cap_nhat'] );
t( 'và nói rõ là không có dòng nào đổi', strpos( $h_w, 'Đã lưu 0 dòng' ) !== false, $h_w );

/* --- ĐẶT VAI TRÒ HÀNG LOẠT — thứ thật sự cứu 237 dòng --- */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv<>'W1'" );
foreach ( array( array( 'H1', 'JP_HCM' ), array( 'H2', 'JP_HCM' ), array( 'H3', 'POSH_HCM' ) ) as $x_h ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $x_h[0], 'ho_ten' => 'Người ' . $x_h[0],
		'cua_hang' => $x_h[1], 'pin_dang_nhap' => '', 'vai_tro' => '' ) );
}
/* 🔴 PHẢI theo ĐÚNG bộ lọc của lượt xem. Người dùng đang nhìn 2 dòng đã lọc và bấm "áp cho 2
   dòng"; ngầm áp cho cả bảng thì người ngoài bộ lọc bị đổi vai trò mà không ai thấy. */
$_COOKIE[ VHCC_Web::COOKIE ] = $tok_ad;
$_POST = array( 'viec' => 'vai_tro_hang_loat', 'ky' => VHCC_Web::chu_ky( $tok_ad ),
	'vt_hl' => 'Quản lý', 'cs' => 'JP_HCM' );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
$_POST = array(); $_COOKIE = array();
teq( 'áp hàng loạt cho đúng người trong bộ lọc (1)', 'Quản lý', vhcc_hs( 'H1' )['vai_tro'] );
teq( 'áp hàng loạt cho đúng người trong bộ lọc (2)', 'Quản lý', vhcc_hs( 'H2' )['vai_tro'] );
teq( '🔴 người NGOÀI bộ lọc KHÔNG bị đụng', '', vhcc_hs( 'H3' )['vai_tro'] );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('H1','H2','H3')" );

/* 🔴 POST → CHUYỂN HƯỚNG → GET. Anh Thắng: *"cứ bấm F5 là nó reset về ban đầu"*, và trình duyệt
   hiện hộp "Confirm Form Resubmission". Vẽ thẳng kết quả của POST thì F5 là gửi lại nguyên cái
   POST đó — lưu lại lần nữa, hoặc nạp lại cả file .csv — và bộ lọc biến mất vì chúng nằm ở
   query mà POST không mang theo. */
$web_src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
t( 'làm việc xong thì CHUYỂN HƯỚNG, không vẽ thẳng kết quả POST',
	preg_match( '/self::cat_bao\( \$bao \);\s*\n\s*self::ve\( self::url_hien\(\) \);/', $web_src ) === 1 );
$GLOBALS['VHCP_CHUYEN'] = '';
$_GET = array( 'cs' => 'JP_HCM', 'loc' => 'chua_vao' );
vhcc_luu_bang( $tok_ad, array( 'ho_ten' => array( 'W1' => 'Đổi Tên Lần Nữa' ) ),
	array( 'cs' => 'JP_HCM', 'loc' => 'chua_vao' ) );
$_GET = array();
t( '🔴 chuyển hướng GIỮ NGUYÊN bộ lọc đang xem',
	false !== strpos( (string) $GLOBALS['VHCP_CHUYEN'], 'cs=JP_HCM' )
	&& false !== strpos( (string) $GLOBALS['VHCP_CHUYEN'], 'loc=chua_vao' ), $GLOBALS['VHCP_CHUYEN'] );
teq( 'và việc vẫn được làm', 'Đổi Tên Lần Nữa', vhcc_hs( 'W1' )['ho_ten'] );
/* Mọi form POST phải chở bộ lọc theo, không thì chuyển hướng về chẳng biết đường nào mà lần. */
$_GET = array( 'cs' => 'JP_HCM', 'loc' => 'chua_vao' );
$h_lo = vhcc_web( '246813', array(), array( 'man' => 'ho_so', 'cs' => 'JP_HCM', 'loc' => 'chua_vao'  ) );
$_GET = array();
t( 'form trong trang chở theo bộ lọc bằng ô ẩn',
	strpos( $h_lo, '<input type="hidden" name="cs" value="JP_HCM">' ) !== false
	&& strpos( $h_lo, '<input type="hidden" name="loc" value="chua_vao">' ) !== false, $h_lo );
/* Kết quả chỉ hiện MỘT LẦN — không dính lại ở lần tải trang sau. */
$h_l1 = vhcc_web( '246813', array(), array( 'man' => 'ho_so' ) );
t( 'kết quả không dính lại ở lần tải trang sau',
	strpos( $h_l1, 'Đã lưu 1 dòng' ) === false, $h_l1 );

/* --- Vai trò --- */
vhcc_luu_bang( $tok_ad, array( 'vai_tro' => array( 'W1' => 'Quản lý' ) ) );
teq( 'lưu được vai trò đăng nhập', 'Quản lý', vhcc_hs( 'W1' )['vai_tro'] );
vhcc_luu_bang( $tok_ad, array( 'vai_tro' => array( 'W1' => 'Sếp Tổng' ) ) );
teq( 'vai trò LẠ thì bỏ hẳn, không ghi bừa', 'Quản lý', vhcc_hs( 'W1' )['vai_tro'] );
vhcc_luu_bang( $tok_ad, array( 'vai_tro' => array( 'W1' => '' ) ) );
teq( 'nhưng để rỗng được (chưa khai là một trạng thái thật)', '', vhcc_hs( 'W1' )['vai_tro'] );

/* 🔴 MẮT XÍCH CUỐI: khai vai trò trong hồ sơ -> nạp tài khoản -> đăng nhập được bằng PIN đó.
   Trước bản này không có cột vai trò, nên cả sổ rơi về "Nhân viên" và KHÔNG AI đăng nhập được,
   mà màn hình vẫn báo "đã nạp 240 người". */
vhcc_luu_bang( $tok_ad, array( 'vai_tro' => array( 'W1' => 'Quản lý' ),
	'pin_dang_nhap' => array( 'W1' => '975310' ) ) );
delete_option( 'vhcc_nguoidung' );
VHCC_NguoiDung::luu( '', 'Anh Admin', '246813', 'Admin', '' );
$r = VHCC_NguoiDung::nap_tu_cu( 'ho_so', false );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '975310' );
t( 'khai vai trò trong hồ sơ thì nạp xong ĐĂNG NHẬP ĐƯỢC ngay', ! empty( $kq['ok'] ), $kq );
teq( 'và vào đúng vai trò đã khai', 'Quản lý', isset( $kq['role'] ) ? $kq['role'] : null );
VHCC_Auth::mo_khoa();
/* Người CHƯA khai vai trò phải được ĐẾM RA, để màn hình còn hỏi "đặt thành gì" — không thì cả
   sổ lặng lẽ thành Nhân viên và không ai đăng nhập được. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'VT0', 'ho_ten' => 'Chưa Khai Vai Trò',
	'pin_dang_nhap' => '468025', 'vai_tro' => '' ) );
delete_option( 'vhcc_nguoidung' );
VHCC_NguoiDung::luu( '', 'Anh Admin', '246813', 'Admin', '' );
$r = VHCC_NguoiDung::nap_tu_cu( 'ho_so', false );
t( 'người CHƯA khai vai trò thì được đếm ra để màn hình hỏi', $r['vt_trong'] > 0, $r );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='VT0'" );
/* Sổ nhân viên ghi Chức vụ "Khu vui chơi" — đó KHÔNG phải vai trò, không được nhận nhầm. */
teq( 'chức vụ "Khu vui chơi" không bị nhận nhầm thành vai trò', '',
	VHCC_NguoiDung::vai_tro_biet( 'Khu vui chơi' ) );
teq( 'và "Máy tự động" cũng vậy', '', VHCC_NguoiDung::vai_tro_biet( 'Máy tự động' ) );

/* ---- 47e. ĐỔI MÃ NHÂN VIÊN — phải KÉO THEO cả lịch sử ---- */
/* Anh Thắng: *"Admin có quyền sửa luôn mã nhân viên lại cho chuẩn nhé"*. Được, nhưng mã nhân
   viên là thứ NỐI hồ sơ với chấm công, lương, lịch làm, yêu cầu, sổ mặt trong máy. Đổi mỗi ở
   bảng hồ sơ là toàn bộ lịch sử của người đó rơi ra ngoài — bảng công trống trơn, không báo gì. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CU01', 'ho_ten' => 'Anh Đổi Mã',
	'cua_hang' => 'TUTU_BT', 'pin_dang_nhap' => '135791' ) );
foreach ( array( '2026-08-01', '2026-08-02', '2026-08-03' ) as $n_cc ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'TUTU_BT', 'ngay' => $n_cc,
		'ma_nv' => 'CU01', 'hau_to' => '', 'ho_ten' => 'Anh Đổi Mã' ) );
}
$wpdb->insert( VHCC_DB::t( 'ma_song_song' ), array( 'ma_a' => 'CU01', 'ma_b' => 'KHAC01' ) );

/* Bảng nào có cột mã phải được DÒ TỪ SƠ ĐỒ, không gõ tay — gõ tay là sớm muộn thiếu, và bảng
   thiếu đó âm thầm rơi lại ở mã cũ. */
$b_ma = VHCC_NhanSu::bang_theo_ma();
t( 'dò được bảng cham_cong theo mã', isset( $b_ma['cham_cong'] ) );
t( 'dò được cả ma_song_song với hai cột ma_a/ma_b',
	isset( $b_ma['ma_song_song'] ) && in_array( 'ma_a', $b_ma['ma_song_song'], true )
	&& in_array( 'ma_b', $b_ma['ma_song_song'], true ), $b_ma );
t( 'và KHÔNG kể chính bảng nhan_vien vào', ! isset( $b_ma['nhan_vien'] ) );
t( 'dò ra nhiều bảng, không phải một hai cái', count( $b_ma ) >= 8, count( $b_ma ) );

$r = VHCC_NhanSu::doi_ma( 'CU01', 'MOI01' );
t( 'đổi mã chạy được', ! empty( $r['ok'] ), $r );
teq( 'hồ sơ mang mã mới', 1, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MOI01'" ) ) );
teq( 'không còn hồ sơ nào ở mã cũ', 0, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='CU01'" ) ) );
/* 🔴 ĐÂY MỚI LÀ PHÉP THỬ ĐÁNG GIÁ: lịch sử chấm công phải đi theo. */
teq( 'cả 3 hàng chấm công đi theo mã mới', 3, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='MOI01'" ) ) );
teq( 'không hàng chấm công nào rơi lại ở mã cũ', 0, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='CU01'" ) ) );
teq( 'ma_song_song cũng đi theo', 1, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'ma_song_song' ) . " WHERE ma_a='MOI01'" ) ) );
t( 'và báo lại đã kéo theo bảng nào bao nhiêu hàng',
	isset( $r['bang']['cham_cong'] ) && 3 === $r['bang']['cham_cong'], $r['bang'] );

/* 🔴 CHẶN GHI ĐÈ LÊN MÃ ĐANG CÓ NGƯỜI — trộn công hai người vào một, và KHÔNG có đường lùi:
   sau đó không phân biệt được hàng nào vốn của ai. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'MOI02', 'ho_ten' => 'Người Khác' ) );
$r = VHCC_NhanSu::doi_ma( 'MOI01', 'MOI02' );
t( 'chối đổi sang mã ĐANG CÓ NGƯỜI, và nói rõ của ai',
	empty( $r['ok'] ) && strpos( $r['error'], 'Người Khác' ) !== false, $r );
teq( 'và không hàng nào bị động vào', 3, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='MOI01'" ) ) );
$r = VHCC_NhanSu::doi_ma( 'MOI01', 'mã có dấu!' );
t( 'chối mã sai khuôn', empty( $r['ok'] ) && strpos( $r['error'], 'chữ, số' ) !== false, $r );
$r = VHCC_NhanSu::doi_ma( 'KHONG_CO', 'GI_DO' );
t( 'chối mã cũ không tồn tại', empty( $r['ok'] ), $r );

/* Ngoài web: chỉ Admin đổi được mã. */
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
VHCC_NguoiDung::luu( '', 'Chị Quản Lý', '357913', 'Quản lý', 'TUTU_BT' );
VHCC_Auth::mo_khoa();
$kq_ql = VHCC_Auth::login( '357913' );
VHCC_Auth::mo_khoa();
$_COOKIE[ VHCC_Web::COOKIE ] = $kq_ql['token'];
$_POST = array( 'viec' => 'doi_ma', 'ky' => VHCC_Web::chu_ky( $kq_ql['token'] ),
	'ma_cu' => 'MOI01', 'ma_moi' => 'QL_DOI' );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
/* Quản lý bị chối SỚM HƠN — họ không có cả quyền vào màn Hồ sơ (mô hình năm bậc). */
t( 'Quản lý KHÔNG đổi được Mã NV', strpos( $h_w, 'Kế toán trở lên' ) !== false, $h_w );
/* Kế toán VÀO được màn Hồ sơ, nhưng đổi Mã NV vẫn là việc hệ thống -> chỉ Admin. */
$r_ma = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'doi_ma', array( 'name' => 'Chị KT', 'role' => 'Kế toán cá nhân', 'coso' => '' ) ) );
t( 'Kế toán cũng KHÔNG đổi được Mã NV (chỉ Admin)',
	isset( $r_ma[0]['loi'] ) && strpos( $r_ma[0]['loi'], 'Chỉ Admin' ) !== false, $r_ma );
teq( 'và mã vẫn nguyên', 1, count( VHCC_DB::rows(
	'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='MOI01'" ) ) );
$_POST = array(); $_COOKIE = array();

/* Màn Sửa đủ: có ô đổi mã, và nói trước sẽ động vào bao nhiêu hàng. */
$h_w = vhcc_web( '246813', array(), array( 'man' => 'ho_so', 'sua' => 'MOI01'  ) );
t( 'màn Sửa đủ mở được', strpos( $h_w, 'Sửa hồ sơ MOI01' ) !== false, $h_w );
t( 'có ô đổi Mã NV', strpos( $h_w, 'name="ma_moi"' ) !== false );
t( 'và nói TRƯỚC sẽ kéo theo bao nhiêu hàng', strpos( $h_w, 'kéo theo cả 4 hàng' ) !== false, $h_w );
t( 'có đủ mấy nhóm ô của hồ sơ', strpos( $h_w, 'Lương' ) !== false
	&& strpos( $h_w, 'Đăng nhập' ) !== false && strpos( $h_w, 'Cá nhân' ) !== false );
t( 'màn đó cũng KHÔNG in PIN ra', strpos( $h_w, 'value="135791"' ) === false );

/* 🔴 KHÔNG CHO TỰ KHOÁ MÌNH RA NGOÀI. Đổi sang một nguồn không ai vào được thì hết phiên là
   không còn đường nào mở lại ngoài wp-admin — đúng vòng tròn vừa gỡ xong. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE pin_dang_nhap<>''" );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
VHCC_Auth::mo_khoa();
$tok_dn = VHCC_Auth::login( '246813' )['token'];
VHCC_Auth::mo_khoa();
$_COOKIE[ VHCC_Web::COOKIE ] = $tok_dn;
$_POST = array( 'viec' => 'doi_nguon', 'nguon' => 'ho_so', 'ky' => VHCC_Web::chu_ky( $tok_dn ) );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
teq( 'không cho đổi sang nguồn KHÔNG AI vào được', 'rieng', VHCC_Auth::nguon() );
t( 'và nói rõ vì sao: đổi là tự khoá mình ra ngoài',
	strpos( $h_w, 'tự khoá mình ra ngoài' ) !== false, $h_w );
/* Có người vào được thì đổi bình thường. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'DN01', 'ho_ten' => 'Anh Vào Được',
	'pin_dang_nhap' => '135791', 'vai_tro' => 'Admin' ) );
$_POST = array( 'viec' => 'doi_nguon', 'nguon' => 'ho_so', 'ky' => VHCC_Web::chu_ky( $tok_dn ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
teq( 'có người vào được thì đổi nguồn bình thường', 'ho_so', VHCC_Auth::nguon() );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
$_POST = array(); $_COOKIE = array();

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'ma_song_song' ) );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) );
VHCC_NapCsv::nap( "Mã NV,Họ tên,Cửa hàng,CCCD,PIN đăng nhập\nW1,Nguyễn Thu Hiền,JP_HCM,049304007231,170412\n", false );

/* Dọn lại đúng trạng thái mục 47 dựng ra, cho mấy mục sau không đọc nhầm. */
global $wpdb;
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='W9'" );
delete_option( 'vhcc_nguoidung' );
VHCC_NguoiDung::luu( '', 'Anh Admin', '246813', 'Admin', '' );
VHCC_NguoiDung::luu( '', 'Chị Quản Lý', '357913', 'Quản lý', 'TUTU_BT' );
VHCC_NguoiDung::luu( '', 'Chị Kế Toán', '468024', 'Kế toán cá nhân', 'TUTU_BT' );
teq( 'dọn xong còn đúng 1 hồ sơ', 1,
	count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );

/* 🔴 CHỮ KÝ CHỐNG GIẢ MẠO. Không có chữ ký đúng thì không việc gì được chạy — kẻo một trang
   khác dụ anh Thắng bấm vào là xoá sạch hồ sơ của cả chuỗi. */
$h_w = vhcc_web( '246813', array( 'viec' => 'xoa_het', 'xac_nhan' => 'XOA HET' ) );
t( 'POST thiếu chữ ký thì KHÔNG làm gì', strpos( $h_w, 'biểu mẫu không hợp lệ' ) !== false );
teq( 'và hồ sơ vẫn còn nguyên', 1, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
$h_w = vhcc_web( '246813', array( 'viec' => 'xoa_het', 'xac_nhan' => 'XOA HET', 'ky' => 'chu-ky-bia' ) );
t( 'chữ ký bịa cũng bị chối', strpos( $h_w, 'biểu mẫu không hợp lệ' ) !== false );
teq( 'hồ sơ vẫn còn', 1, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );

/* Chữ ký buộc vào ĐÚNG token của phiên đó — token khác thì chữ ký khác. */
t( 'chữ ký khác nhau theo từng phiên',
	VHCC_Web::chu_ky( 'tok-a' ) !== VHCC_Web::chu_ky( 'tok-b' ) );

/* Xoá sạch: phải gõ đúng chữ, và chỉ Admin. */
VHCC_Auth::mo_khoa();
$kq_ad = VHCC_Auth::login( '246813' );
VHCC_Auth::mo_khoa();
$ky_ad = VHCC_Web::chu_ky( $kq_ad['token'] );
$_COOKIE[ VHCC_Web::COOKIE ] = $kq_ad['token'];
$_POST = array( 'viec' => 'xoa_het', 'ky' => $ky_ad, 'xac_nhan' => 'xoa het' );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
t( 'gõ sai chữ xác nhận thì KHÔNG xoá', strpos( $h_w, 'XOA HET' ) !== false );
teq( 'hồ sơ còn nguyên', 1, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
$_POST = array( 'viec' => 'xoa_het', 'ky' => $ky_ad, 'xac_nhan' => 'XOA HET' );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
teq( 'gõ đúng thì xoá sạch hồ sơ', 0, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
t( 'và nói rõ lượt chấm công KHÔNG bị xoá theo',
	strpos( $h_w, 'KHÔNG bị xoá' ) !== false, $h_w );
$_POST = array(); $_COOKIE = array();

/* Quản lý KHÔNG xoá được cả sổ nhân sự của chuỗi. */
VHCC_NapCsv::nap( "Mã NV,Họ tên,PIN đăng nhập\nW2,Người Còn Lại,170412\n", false );
VHCC_Auth::mo_khoa();
$kq_ql = VHCC_Auth::login( '357913' );
VHCC_Auth::mo_khoa();
$_COOKIE[ VHCC_Web::COOKIE ] = $kq_ql['token'];
$_POST = array( 'viec' => 'xoa_het', 'ky' => VHCC_Web::chu_ky( $kq_ql['token'] ), 'xac_nhan' => 'XOA HET' );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
t( 'Quản lý bấm xoá sạch thì bị chối', strpos( $h_w, 'Kế toán trở lên' ) !== false, $h_w );
$r_xh = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'xoa_het', array( 'name' => 'Chị KT', 'role' => 'Kế toán cá nhân', 'coso' => '' ) ) );
t( 'Kế toán cũng bị chối xoá sạch (chỉ Admin)',
	isset( $r_xh[0]['loi'] ) && strpos( $r_xh[0]['loi'], 'Chỉ Admin' ) !== false, $r_xh );
teq( 'và hồ sơ còn nguyên', 1, count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'nhan_vien' ) ) ) );
$_POST = array(); $_COOKIE = array();

/* ---- 47b. KHAI TÀI KHOẢN ADMIN TOÀN QUYỀN ---- */
/* Anh Thắng: *"khai luôn tk admin để toàn quyền"*. */
$so_truoc = count( VHCC_NguoiDung::ds() );
$r = VHCC_NguoiDung::khai_admin( 'Anh Thắng' );
t( 'khai được tài khoản Admin', ! empty( $r['ok'] ), $r );
t( 'PIN 6 số', preg_match( '/^\d{6}$/', $r['pin'] ) === 1, $r['pin'] );
teq( 'PIN đó tự nó hợp lệ', '', VHCC_Quyen::pin_hop_le( $r['pin'] ) );
teq( 'danh sách thêm đúng 1 người', $so_truoc + 1, count( VHCC_NguoiDung::ds() ) );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( $r['pin'] );
t( 'đăng nhập THẬT được bằng PIN vừa khai', ! empty( $kq['ok'] ), $kq );
teq( 'với vai trò Admin', 'Admin', isset( $kq['role'] ) ? $kq['role'] : null );
VHCC_Auth::mo_khoa();

/* 🔴 PIN KHÔNG được lưu lại để "xem sau" — lưu là để một PIN Admin còn dùng được nằm sẵn trong
   cơ sở dữ liệu, ai đọc được database là vào thẳng. */
$do_option = wp_json_encode( $GLOBALS['VHCP_OPT'] );
teq( 'PIN vừa khai KHÔNG nằm trong option nào ngoài danh sách người dùng', 1,
	substr_count( $do_option, $r['pin'] ) );

/* Khai lần hai: PIN khác, và tên tự nối số để nhật ký phân biệt được. */
$r2 = VHCC_NguoiDung::khai_admin( 'Anh Thắng' );
t( 'khai lần hai ra PIN KHÁC', $r2['pin'] !== $r['pin'] );
t( 'và tên tự nối số cho khỏi trùng', $r2['ten'] !== $r['ten'], $r2['ten'] );
/* Trùng PIN với người đang có là không được — nhật ký không phân biệt được ai làm việc gì. */
$pins = array();
foreach ( VHCC_NguoiDung::ds() as $u ) { if ( '' !== $u['pin'] ) { $pins[] = $u['pin']; } }
teq( 'không ai trùng PIN với ai', count( $pins ), count( array_unique( $pins ) ) );

/* Nút khai Admin ngoài web chỉ Admin bấm được. */
$_COOKIE[ VHCC_Web::COOKIE ] = $kq_ql['token'];
$_POST = array( 'viec' => 'khai_admin', 'ky' => VHCC_Web::chu_ky( $kq_ql['token'] ), 'ten' => 'Kẻ Lạ' );
ob_start(); VHCC_Web::phuc_vu(); $h_w = ob_get_clean();
t( 'Quản lý KHÔNG khai được tài khoản Admin', strpos( $h_w, 'Kế toán trở lên' ) !== false, $h_w );
$r_ka = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'khai_admin', array( 'name' => 'Chị KT', 'role' => 'Kế toán cá nhân', 'coso' => '' ) ) );
t( 'Kế toán cũng KHÔNG khai được Admin (chỉ Admin)',
	isset( $r_ka[0]['loi'] ) && strpos( $r_ka[0]['loi'], 'Chỉ Admin' ) !== false, $r_ka );
$_POST = array(); $_COOKIE = array();

/* ---- 47c. ĐƯỜNG VÀO BẰNG QUYỀN QUẢN TRỊ WORDPRESS ---- */
/* 🔴 THẾ BÍ CÓ THẬT, anh Thắng gặp ngay: *"không có pin nào vào được"*. Muốn nạp tài khoản
   đăng nhập thì phải vào trang này; muốn vào trang này thì phải có tài khoản đăng nhập. Vòng
   tròn, không có đường nào tự mở.

   Người đang đăng nhập wp-admin với quyền `manage_options` sửa được cả website, gỡ được chính
   plugin này, đọc thẳng được bảng người dùng trong database — quyền đó ĐÃ CAO HƠN một PIN Admin
   của chấm công. Bắt họ đi vòng qua PIN không thêm lớp an toàn nào, chỉ thêm một vòng tròn. */
delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
$GLOBALS['VHCP_DANG_NHAP_WP'] = false;
$GLOBALS['VHCP_CO_QUYEN']     = false;
$h_w = vhcc_web();
t( 'chưa ai vào được thì NÓI THẲNG ra, đừng để gõ PIN mãi vào danh sách rỗng',
	strpos( $h_w, 'Chưa có tài khoản nào đăng nhập được' ) !== false, $h_w );
t( 'khách lạ KHÔNG thấy nút vào bằng WordPress', strpos( $h_w, 'name="vao_wp"' ) === false );

/* Đăng nhập WordPress nhưng KHÔNG có quyền quản trị -> vẫn không có nút. */
$GLOBALS['VHCP_DANG_NHAP_WP'] = true;
$GLOBALS['VHCP_CO_QUYEN']     = false;
$h_w = vhcc_web();
t( 'đăng nhập WordPress mà không có quyền quản trị thì cũng KHÔNG có nút',
	strpos( $h_w, 'name="vao_wp"' ) === false );

/* Có quyền quản trị -> có nút, và bấm vào là vào được. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
$h_w = vhcc_web();
t( 'quản trị WordPress thấy nút vào thẳng', strpos( $h_w, 'name="vao_wp"' ) !== false );
t( 'và nói rõ vì sao được vào', strpos( $h_w, 'cao hơn một PIN Admin' ) !== false );

$so_phien = count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'session' ) ) );
$_POST = array( 'vao_wp' => '1' ); $_COOKIE = array(); $_GET = array();
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
teq( 'bấm nút là phát một phiên mới', $so_phien + 1,
	count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'session' ) ) ) );
$phien = VHCC_DB::rows( 'SELECT ten, vai_tro FROM ' . VHCC_DB::t( 'session' ) . ' ORDER BY id DESC' );
teq( 'phiên đó là Admin', 'Admin', $phien[0]['vai_tro'] );
t( 'và mang tên tài khoản WordPress để nhật ký truy được', '' !== $phien[0]['ten'], $phien[0] );
$_POST = array();

/* 🔴 KHÔNG có quyền quản trị mà POST thẳng vào thì KHÔNG được phát phiên nào. */
$GLOBALS['VHCP_CO_QUYEN'] = false;
$so_phien = count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'session' ) ) );
$_POST = array( 'vao_wp' => '1' );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
teq( 'POST thẳng mà không có quyền thì KHÔNG phát phiên nào', $so_phien,
	count( VHCC_DB::rows( 'SELECT id FROM ' . VHCC_DB::t( 'session' ) ) ) );
$_POST = array();
$GLOBALS['VHCP_DANG_NHAP_WP'] = false;

/* Có người vào được rồi thì thôi kêu "chưa ai đăng nhập được". */
VHCC_NguoiDung::luu( '', 'Anh Admin', '246813', 'Admin', '' );
$h_w = vhcc_web();
t( 'có người vào được thì thôi kêu nữa',
	strpos( $h_w, 'Chưa có tài khoản nào đăng nhập được' ) === false );

/* Cổng /cham-cong và trang quản trị dùng HAI đường dẫn khác nhau — không đè lên nhau. */
t( 'trang quản trị có đường dẫn riêng', VHCC_Web::slug() !== VHCC_Trang::slug() );

vhcc_dung_bang();
delete_option( 'vhcc_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'chung' );


/* Hướng dẫn cài phải theo kịp thực tế: 22/08/2026 Hostinger sập, chuyển sang Vietnix (cPanel).
   Mấy điều dưới đây là thứ sai một cái là cả chuỗi máy im lặng, nên phải luôn có trong hướng dẫn. */
$hd_vnx = file_get_contents( $goc . '/docs/CAI-LEN-VIETNIX.md' );
t( 'có hướng dẫn riêng cho Vietnix', strlen( $hd_vnx ) > 2000 );
/* Nêu ĐÍCH DANH thứ hosting đang chạy, không nói chung chung "tường lửa": ảnh cPanel của Vietnix
   cho thấy **Imunify360** và **LiteSpeed Web Cache**. Nói chung chung thì người đọc không biết
   phải bấm vào đâu. */
t( 'cảnh báo chặn bot cho đường /cham-cong-may',
	strpos( $hd_vnx, 'Imunify360' ) !== false && strpos( $hd_vnx, '/cham-cong-may' ) !== false );
t( 'và nói rõ vì sao Imunify360 coi máy chấm công là tấn công',
	strpos( $hd_vnx, 'không cookie không JavaScript' ) !== false );
/* Nhớ tạm trang chấm công là người này thấy màn hình người kia — nặng hơn cả chuyện chậm. */
t( 'cấm nhớ tạm hai đường /cham-cong và /cham-cong-may',
	strpos( $hd_vnx, 'LiteSpeed' ) !== false && strpos( $hd_vnx, 'Do Not Cache URIs' ) !== false );
t( 'và nói rõ hậu quả: người này thấy màn hình của người kia',
	strpos( $hd_vnx, 'thấy màn hình của người kia' ) !== false );
t( 'nói rõ firmware KHÔNG báo khi bị chặn — đó là chỗ hỏng im lặng',
	strpos( $hd_vnx, 'vẫn báo thành công' ) !== false );
t( 'đòi HTTPS và nói rõ firmware từ chối http://',
	strpos( $hd_vnx, 'từ chối địa chỉ' ) !== false );
t( 'đòi post_max_size đủ lớn cho ảnh mặt', strpos( $hd_vnx, 'post_max_size' ) !== false );
t( 'dặn cài WordPress ở THƯ MỤC GỐC, không thư mục con',
	strpos( $hd_vnx, 'để trống ô thư mục con' ) !== false );
t( 'liệt kê ĐỦ bốn chỗ phải sửa khi đổi khoá',
	strpos( $hd_vnx, 'cfg/wp/key' ) !== false && strpos( $hd_vnx, 'cfg/wp/url' ) !== false
	&& strpos( $hd_vnx, 'WP_KEY' ) !== false && strpos( $hd_vnx, 'WEB_KEY' ) !== false );
t( 'nói rõ giữ tên miền thì KHÔNG phải nạp lại firmware',
	strpos( $hd_vnx, 'không phải nạp lại firmware' ) !== false );
t( 'bảng kiểm cuối có dòng QUẸT THẺ THẬT — sáu dòng kia chỉ là nhìn màn hình',
	strpos( $hd_vnx, 'quẹt thẻ thật' ) !== false || strpos( $hd_vnx, 'Quẹt thẻ thật' ) !== false );
t( 'dặn lấy bản sao lưu cơ sở dữ liệu trước, và trấn an nếu không lấy được',
	strpos( $hd_vnx, 'Export' ) !== false && strpos( $hd_vnx, 'không mất gì' ) !== false );
/* Tên tệp hướng dẫn chung không còn gắn với một nhà cung cấp. */
t( 'hướng dẫn chung đã đổi tên trung tính',
	file_exists( $goc . '/docs/CAI-LEN-HOSTING.md' )
	&& ! file_exists( $goc . '/docs/CAI-LEN-HOSTINGER.md' ) );
t( 'và trỏ sang phần riêng của Vietnix',
	strpos( file_get_contents( $goc . '/docs/CAI-LEN-HOSTING.md' ), 'CAI-LEN-VIETNIX.md' ) !== false );
/* Mã của plugin không được nhắc đích danh nhà cung cấp nào — đổi nhà là câu đó thành sai. */
$nhac_ncc = array();
foreach ( glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ) as $f ) {
	foreach ( explode( "\n", file_get_contents( $f ) ) as $i => $d ) {
		if ( ! preg_match( '/Hostinger|Vietnix/i', $d ) ) { continue; }
		/* Được phép nhắc tên nhà cung cấp khi đang TRÍCH NGUYÊN VĂN lời anh Thắng hoặc đang kể
		   lại lịch sử quyết định — đó là ghi chép, không phải mã chạy theo nhà cung cấp. Cấm là
		   cấm câu hướng dẫn kiểu "vào hPanel của Hostinger mà chỉnh", vì đổi nhà là câu đó sai. */
		if ( false !== strpos( $d, '*"' ) || false !== strpos( $d, 'chuyển sang' ) ) { continue; }
		$nhac_ncc[] = basename( $f ) . ':' . ( $i + 1 );
	}
}
t( 'mã plugin không có câu HƯỚNG DẪN nào gắn với một nhà cung cấp',
	count( $nhac_ncc ) === 0, implode( ', ', $nhac_ncc ) );

// ====== 48. MÀN QUẢN TRỊ CÔNG CƠ SỞ — dựng theo ĐÚNG tab "Chấm công" của bản Apps Script
/* Anh Thắng 26/08/2026: *"anh đã gửi code và wed appscript em làm y như mẫu đó"*, *"theo mẫu
   giao diện đó đi, sau đó anh sửa sau"*. Nên bộ thử này canh theo BẢN GỐC, không canh theo cái
   lưới người × ngày mà bản WordPress tự nghĩ ra trước đó:

       · bộ lọc năm ô  : Bộ phận · Cơ sở · Tháng · Ngày · Nhân viên
       · bảng chi tiết : Ngày · Mã NV · Họ tên · Hàng · Giờ vào · Giờ ra · Giờ làm · Kiểm tra
       · bảng tổng     : Mã NV · Họ tên · Ngày công · Ngày thiếu giờ ra · Tổng giờ làm

   ⚠️ Chỗ dễ trượt nhất KHÔNG phải cột nào — mà là hai chi tiết lặng lẽ: bảng tổng có bị ô Ngày
      kéo tụt xuống còn một ngày không, và `phut_lam` có tự cộng 24 giờ cho hàng ra < vào không.
      Cả hai đều KHÔNG báo lỗi khi sai, chỉ ra một con số trông hợp lý. */

/* ---- phut_lam: bản dịch `_workMin` ---- */
teq( 'phut_lam 08:00 -> 17:00 = 540 phút', 540,
	VHCC_Cham::phut_lam( VHCC_DB::giay( '08:00:00' ), VHCC_DB::giay( '17:00:00' ) ) );
teq( 'phut_lam làm tròn tới phút (08:00:00 -> 08:00:40 = 1)', 1,
	VHCC_Cham::phut_lam( VHCC_DB::giay( '08:00:00' ), VHCC_DB::giay( '08:00:40' ) ) );
teq( 'thiếu giờ ra -> null', null, VHCC_Cham::phut_lam( VHCC_DB::giay( '08:00:00' ), null ) );
teq( 'thiếu giờ vào -> null', null, VHCC_Cham::phut_lam( null, VHCC_DB::giay( '17:00:00' ) ) );
teq( 'chuỗi rỗng cũng -> null', null, VHCC_Cham::phut_lam( '', '' ) );
/* 🔴 Chốt quan trọng nhất của mục này. Ra < vào ở MÀN SOÁT là dấu hiệu ghi sai (ca đêm đã được
   trải sang hàng -CD từ trước), nên phải để "—" đập vào mắt người soát. Cộng thêm 24 giờ như
   `VHCC_Luong::phut_ca` làm là lặng lẽ chữa lành một con số đáng lẽ phải bị nhìn. */
teq( 'RA SỚM HƠN VÀO -> null, KHÔNG tự cộng 24 giờ', null,
	VHCC_Cham::phut_lam( VHCC_DB::giay( '17:00:00' ), VHCC_DB::giay( '08:00:00' ) ) );
/* ⚠️ `phut_ca` nhận PHÚT (17:00 = 1020), `phut_lam` nhận GIÂY — hai đơn vị khác nhau, nên đừng
   gộp hai hàm này lại "cho gọn": gộp là một trong hai bên nhận sai đơn vị mà vẫn ra số. */
teq( 'và đó là chỗ CỐ Ý khác VHCC_Luong::phut_ca (bên lương vẫn cộng trọn vòng 24h, kẻo trừ tiền người ta)',
	900, VHCC_Luong::phut_ca( 17 * 60, 8 * 60 ) );

/* ---- chu_gio: bản dịch `_fmtHrsTxt` ---- */
teq( 'chu_gio 540 -> "9h"', '9h', VHCC_Cham::chu_gio( 540 ) );
teq( 'chu_gio 510 -> "8h 30m"', '8h 30m', VHCC_Cham::chu_gio( 510 ) );
teq( 'chu_gio 45 -> "0h 45m"', '0h 45m', VHCC_Cham::chu_gio( 45 ) );
teq( 'chu_gio null -> "—" (dấu hiệu sai, không phải "0h")', '—', VHCC_Cham::chu_gio( null ) );

/* ---- gom_tong: bản dịch `ccGomTong` ---- */
$gt_hang = array(
	array( 'ngay' => '2026-07-01', 'maNV' => 'GT1', 'hoTen' => 'Bê', 'hauTo' => '',
		'vao' => '08:00:00', 'ra' => '17:00:00', 'phut' => 540 ),
	/* CÙNG NGÀY, hàng ca đêm -> vẫn phải là MỘT ngày công, nhưng giờ thì cộng dồn. */
	array( 'ngay' => '2026-07-01', 'maNV' => 'GT1', 'hoTen' => 'Bê', 'hauTo' => 'CD',
		'vao' => '18:00:00', 'ra' => '20:00:00', 'phut' => 120 ),
	array( 'ngay' => '2026-07-02', 'maNV' => 'GT1', 'hoTen' => 'Bê', 'hauTo' => '',
		'vao' => '08:00:00', 'ra' => '', 'phut' => null ),
	array( 'ngay' => '2026-07-01', 'maNV' => 'GT2', 'hoTen' => 'A', 'hauTo' => '',
		'vao' => '', 'ra' => '', 'phut' => null ),
);
$gt = VHCC_Cham::gom_tong( $gt_hang );
teq( 'gom_tong gom đúng hai người', 2, count( $gt ) );
teq( 'xếp theo HỌ TÊN chứ không theo thứ tự gặp ("A" trước "Bê")', 'GT2', $gt[0]['maNV'] );
$gt1 = ( 'GT1' === $gt[0]['maNV'] ) ? $gt[0] : $gt[1];
teq( 'ngày công đếm NGÀY RIÊNG BIỆT — hai hàng cùng ngày vẫn là 1', 2, $gt1['ngay'] );
teq( 'tổng phút cộng cả hàng chính lẫn hàng -CD', 660, $gt1['phut'] );
teq( 'đếm đúng 1 ngày thiếu giờ ra', 1, $gt1['thieu'] );
$gt2 = ( 'GT2' === $gt[0]['maNV'] ) ? $gt[0] : $gt[1];
teq( 'hàng TRỐNG HẲN không tính là "quên check-out" (đó là ngày không đi làm)', 0, $gt2['thieu'] );

/* ---- bang_cham_cong: mỗi hàng phải mang sẵn `phut`, và có khoá `tong` ---- */
vhcc_cham( 'TUTU_BT', '2026-07-06', 'QTC1', '', '08:00:00', '17:30:00' );
vhcc_cham( 'TUTU_BT', '2026-07-07', 'QTC1', '', '08:05:00', null );          // quên bấm lúc về
vhcc_cham( 'TUTU_BT', '2026-07-06', 'QTC2', '', '09:00:00', '18:00:00' );
$u_qtc = array( 'name' => 'Admin QTC', 'role' => 'Admin', 'coso' => '' );
$b_qtc = VHCC_Cham::bang_cham_cong( $u_qtc, 'TUTU_BT', '2026-07' );
t( 'bang_cham_cong chạy được', ! empty( $b_qtc['ok'] ), $b_qtc );
t( 'trả về khoá `tong` sẵn — hai bảng đi từ MỘT mảng, không có công thức thứ hai',
	isset( $b_qtc['tong'] ) && is_array( $b_qtc['tong'] ) );
$h_qtc1 = null;
foreach ( $b_qtc['hang'] as $r_qtc ) {
	if ( 'QTC1' === $r_qtc['maNV'] && '2026-07-06' === $r_qtc['ngay'] ) { $h_qtc1 = $r_qtc; }
}
t( 'mỗi hàng mang sẵn số phút đã tính', is_array( $h_qtc1 ) && 570 === $h_qtc1['phut'], $h_qtc1 );
t( 'và mang cả `nguon` để biết giờ từ máy hay từ trạm online',
	is_array( $h_qtc1 ) && isset( $h_qtc1['nguon'] ) );

/* ---- màn hình: bộ lọc năm ô + hai bảng ---- */
/* Mục 47 ở trên vừa thử "đổi nguồn người dùng" nên nguồn đang là `chung`, mà `VHCC_NguoiDung::luu`
   thì ghi vào sổ RIÊNG — khai tài khoản kiểu gì cũng không đăng nhập được. Đặt lại nguồn rồi mới
   khai, kẻo mọi phép dưới đây chỉ đang soi cái màn đăng nhập mà tưởng là soi bảng công. */
update_option( 'vhcc_nguon_nguoidung', 'rieng' );
$r_khai_qtc = VHCC_NguoiDung::luu( '', 'Admin Soát Công', '135791', 'Admin', '' );
t( 'khai được một Admin để soi màn bảng công', ! empty( $r_khai_qtc['ok'] ), $r_khai_qtc );
/* 🔴 Và một NHÂN VIÊN trong CÙNG nguồn người dùng.
   PIN 680246 khai ở mục 47 nằm ở nguồn `chung`, mà mục này đã đổi nguồn sang `rieng` — dùng lại
   nó là rơi vào MÀN ĐĂNG NHẬP. Mấy phép "Nhân viên không thấy X" vì thế xanh nhờ một lý do
   hoàn toàn khác: chúng đang soi cái màn nhập PIN, nơi dĩ nhiên không có gì cả. */
$r_khai_nv = VHCC_NguoiDung::luu( '', 'Em Nhân Viên', '864202', 'Nhân viên', 'TUTU_BT' );
t( 'khai được một Nhân viên cùng nguồn', ! empty( $r_khai_nv['ok'] ), $r_khai_nv );
t( 'và Nhân viên ấy VÀO ĐƯỢC (kẻo mọi phép "không thấy X" chỉ đang soi màn đăng nhập)',
	strpos( vhcc_web( '864202' ), 'name="pin"' ) === false );
$h_vao_qtc = vhcc_web( '135791' );
t( 'Admin vừa khai vào được trang quản trị (kẻo phép dưới soi nhầm màn đăng nhập)',
	strpos( $h_vao_qtc, 'name="pin"' ) === false, $h_vao_qtc );

$g_qtc = array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' );
$h_qtc = vhcc_web( '135791', array(), $g_qtc );
foreach ( array( 'ccs' => 'Cơ sở', 'cth' => 'Tháng',
	'cng' => 'Ngày', 'cnv' => 'Nhân viên' ) as $o_qtc => $nhan_qtc ) {
	t( 'màn có ô lọc ' . $nhan_qtc . ' (name="' . $o_qtc . '")',
		strpos( $h_qtc, 'name="' . $o_qtc . '"' ) !== false, $h_qtc );
}

/* ---- BỘ PHẬN LÀ LIÊN KẾT, KHÔNG PHẢI Ô CHỌN ----
   🔴 Anh Thắng 26/08: *"Lỗi rồi"* — chọn Bộ phận = Văn phòng rồi mở ô Cơ sở ra thì vẫn thấy
   nguyên cả chuỗi cơ sở. Phần lọc trong `the_bang_cham` VẪN đúng, nhưng nó chỉ chạy sau khi
   bấm "Xem": ô Bộ phận nằm CÙNG biểu mẫu với ô Cơ sở nên hai ô cùng gửi lên một lượt, không
   ô nào lọc được ô nào. Một cái lọc chỉ lọc sau một cú bấm nữa thì không phải cái lọc.
   Màn này không được có script (phép "màn quản trị KHÔNG có thẻ <script>"), nên cách duy nhất
   là biến bộ phận thành LIÊN KẾT: bấm là tải lại trang, ô Cơ sở dựng lại đã lọc sẵn.

   ⚠️ CANH BẰNG NỘI DUNG Ô CƠ SỞ, KHÔNG CANH BẰNG "CÓ CHỮ cbp KHÔNG". Bản hỏng CŨNG có chữ
      `cbp` trên màn — nó có nguyên một cái `<select name="cbp">`. Thứ phân biệt hỏng với chạy
      là danh sách cơ sở SAU khi lọc, nên phải soi đúng chỗ đó. */
t( 'màn có dải lọc Bộ phận', strpos( $h_qtc, 'class="loc-bp"' ) !== false, $h_qtc );
t( 'bộ phận là LIÊN KẾT (bấm là lọc ngay), không phải <select name="cbp">',
	strpos( $h_qtc, 'cbp=' ) !== false && ! preg_match( '/<select[^>]*name="cbp"/i', $h_qtc ), $h_qtc );

/* Xếp TUTU_BT vào một bộ phận rồi soi ô Cơ sở ở hai lượt lọc khác nhau. */
VHCC_NhanSu::xep_bo_phan( $u_qtc, 'TUTU_BT', 'Khu vui chơi' );

function vhcc_ds_ccs( $h ) {
	if ( ! preg_match( '/<select id="ccs".*?<\/select>/s', $h, $m ) ) { return array(); }
	preg_match_all( '/<option value="([^"]*)"/', $m[0], $o );
	return array_values( array_filter( $o[1], function ( $x ) { return '' !== $x; } ) );
}

$h_kvc = vhcc_web( '135791', array(), array( 'man' => 'cham', 'cbp' => 'Khu vui chơi', 'cth' => '2026-07' ) );
t( '🔴 lọc bộ phận ĐÚNG thì ô Cơ sở còn cơ sở của bộ phận ấy',
	in_array( 'TUTU_BT', vhcc_ds_ccs( $h_kvc ), true ), vhcc_ds_ccs( $h_kvc ) );
$h_vp = vhcc_web( '135791', array(), array( 'man' => 'cham', 'cbp' => 'Văn phòng', 'cth' => '2026-07' ) );
t( '🔴 lọc bộ phận KHÁC thì ô Cơ sở KHÔNG còn cơ sở ấy — đây là chỗ anh Thắng vấp',
	! in_array( 'TUTU_BT', vhcc_ds_ccs( $h_vp ), true ), vhcc_ds_ccs( $h_vp ) );

/* Đang lọc bộ phận mà bấm "Xem" thì bộ phận phải đi theo — rơi mất là danh sách cơ sở nhảy
   về đầy đủ ngay sau cú bấm, đúng cái lỗi vừa sửa, chỉ chậm một nhịp. */
/* ⚠️ SOI ĐÚNG BIỂU MẪU LỌC, KHÔNG SOI CẢ TRANG. Trang này còn một chỗ khác cũng in ô ẩn
   `cbp`: khối `o_loc` chở bộ lọc qua lượt POST gắn cờ. Quét cả trang thì bỏ hẳn ô ẩn trong
   biểu mẫu lọc mà phép thử vẫn xanh — đúng thứ vừa gặp khi phá thử. */
function vhcc_form_loc( $h ) {
	return preg_match( '/<form method="get" class="hang".*?<\/form>/s', $h, $m ) ? $m[0] : '';
}
$f_kvc = vhcc_form_loc( $h_kvc );
t( 'tách được biểu mẫu lọc ra khỏi trang', '' !== $f_kvc, $h_kvc );
t( 'đang lọc bộ phận thì BIỂU MẪU LỌC chở `cbp` bằng ô ẩn',
	strpos( $f_kvc, '<input type="hidden" name="cbp" value="Khu vui chơi">' ) !== false, $f_kvc );

/* Đếm số cơ sở ngay trên nhãn: bộ phận rỗng thì bấm vào chỉ thấy ô chọn trống, mà "trống"
   trông y hệt "hỏng". Có con số thì nhìn là biết chỗ nào chưa xếp. */
t( 'mỗi bộ phận kèm số cơ sở đang có',
	preg_match( '/class="loc-bp".*?<span class="sl">\d+<\/span>/s', $h_qtc ) === 1, $h_qtc );

/* Đổi bộ phận không phải bắt đầu lại từ đầu: tháng/ngày/mã NV phải đi theo. */
$h_giu = vhcc_web( '135791', array(),
	array( 'man' => 'cham', 'cth' => '2026-07', 'cnv' => 'NV777' ) );
t( 'đổi bộ phận vẫn giữ tháng và mã NV đang lọc',
	preg_match( '/href="[^"]*cbp=[^"]*"/', $h_giu, $m_giu ) === 1
	&& false !== strpos( $m_giu[0], 'cth=2026-07' )
	&& false !== strpos( $m_giu[0], 'cnv=NV777' ), isset( $m_giu[0] ) ? $m_giu[0] : $h_giu );
/* NHƯNG không giữ `ccs`: cơ sở cũ có thể không thuộc bộ phận mới, giữ lại là bảng hiện dữ
   liệu của một chỗ mà ô chọn không hề trỏ tới. */
t( 'đổi bộ phận thì BỎ cơ sở đang chọn, không chở theo',
	preg_match( '/href="[^"]*cbp=[^"]*"/', $h_kvc, $m_bo ) === 1
	&& false === strpos( $m_bo[0], 'ccs=' ), isset( $m_bo[0] ) ? $m_bo[0] : $h_kvc );

VHCC_NhanSu::xep_bo_phan( $u_qtc, 'TUTU_BT', '' );
foreach ( array( 'Ngày', 'Mã NV', 'Họ tên', 'Hàng', 'Giờ vào', 'Giờ ra', 'Giờ làm', 'Kiểm tra' ) as $c_qtc ) {
	t( 'bảng chi tiết có cột "' . $c_qtc . '"', strpos( $h_qtc, '<th>' . $c_qtc . '</th>' ) !== false );
}
foreach ( array( 'Ngày công', 'Ngày thiếu giờ ra', 'Tổng giờ làm' ) as $c_qtc ) {
	t( 'bảng tổng có cột "' . $c_qtc . '"', strpos( $h_qtc, '<th>' . $c_qtc . '</th>' ) !== false );
}
t( 'in ra giờ làm đã quy đổi ("9h 30m") chứ không phải số phút trần',
	strpos( $h_qtc, '9h 30m' ) !== false, $h_qtc );
t( 'hàng thiếu giờ ra được tô cả DÒNG', strpos( $h_qtc, '<tr class="hong">' ) !== false, $h_qtc );
t( 'và cột Giờ ra ghi thẳng chữ "thiếu"', strpos( $h_qtc, '>thiếu</td>' ) !== false );
/* Tô nền bằng class thì phải CÓ luật CSS cho class đó. Thiếu luật là hỏng không kêu tiếng nào:
   thuộc tính có trong HTML, mà màu thì không bao giờ lên. */
t( 'và có LUẬT CSS cho tr.hong (thuộc tính có mà thiếu luật là tô hụt trong im lặng)',
	strpos( $h_qtc, 'tr.hong>td' ) !== false, $h_qtc );
t( 'màn này vẫn KHÔNG có ô nhập giờ nào — chỉ đọc, y như trước',
	! preg_match( '/name="(gio_vao|gio_ra|vao|ra)"/', $h_qtc ), $h_qtc );

/* ---- màn phải GỌN: bảng tổng trước, chi tiết thu sẵn ---- */
/* Anh Thắng 26/08: *"lưới chiều ngang nó gọn, này quá dài"*. Một tháng của 24 người là mấy trăm
   dòng, mà thứ gọn nhất (bảng tổng, một dòng một người) lại nằm dưới đáy. */
$vt_tong = strpos( $h_qtc, 'Tổng giờ làm theo nhân viên' );
$vt_ct   = strpos( $h_qtc, 'Chi tiết từng lượt' );
t( 'bảng TỔNG đứng TRƯỚC bảng chi tiết', $vt_tong !== false && $vt_ct !== false && $vt_tong < $vt_ct,
	'tong@' . var_export( $vt_tong, true ) . ' ct@' . var_export( $vt_ct, true ) );
/* ⚠️ CẮT ĐÚNG KHỐI CHI TIẾT RA RỒI MỚI SOI.
   Trên màn đã gộp còn mấy khối `<details>` khác (bộ phận · khai ca · cách tính · công thức), và
   khối "Cơ sở thuộc bộ phận nào" CỐ Ý tự mở khi còn cơ sở chưa xếp. Quét cả trang thì chốt
   "phải thu sẵn" đỏ oan, mà sửa cho xanh bằng cách bỏ thuộc tính `open` ấy đi là mất đúng thứ
   hữu ích: cái duy nhất làm mọi phép tính công im lặng không chạy. */
$vt_ct_d = strpos( $h_qtc, '<div class="the"><details>' );
$khoi_ct_d = ( false !== $vt_ct_d ) ? substr( $h_qtc, $vt_ct_d, 4000 ) : '';
t( 'bảng chi tiết nằm trong khối thu gọn <details>',
	false !== strpos( $khoi_ct_d, 'Chi tiết từng lượt' ), substr( $khoi_ct_d, 0, 200 ) );
/* 🔴 THU SẴN, không mở sẵn: `<details open>` thì màn vẫn dài y như cũ, mà thẻ vẫn có nên phép
   thử ở trên vẫn xanh. */
t( 'và THU SẴN (không có thuộc tính open)',
	strpos( $khoi_ct_d, '<details open' ) === false, substr( $khoi_ct_d, 0, 200 ) );
/* ⚠️ Cắt ĐÚNG phần <summary> ra rồi mới kiểm. Bản đầu dùng '/<summary>.*?\d+ lượt/s' — với cờ
   /s thì `.*?` vắt được qua cả đoạn "N lượt" nằm DƯỚI bảng, nên bỏ hẳn số ra khỏi nhãn mà phép
   thử vẫn xanh. Đã phá thử để thấy đúng chuyện đó. */
preg_match( '#<summary>(.*?)</summary>#s', $khoi_ct_d, $m_sum );
$sum_qtc = isset( $m_sum[1] ) ? $m_sum[1] : '';
t( 'có nhãn <summary>', '' !== $sum_qtc, $h_qtc );
t( 'nhãn thu gọn nói sẵn có bao nhiêu lượt', preg_match( '/\d+ lượt/', $sum_qtc ) === 1, $sum_qtc );
t( 'và nói sẵn bao nhiêu ngày thiếu giờ ra, khỏi mở ra mới biết',
	strpos( $sum_qtc, 'ngày thiếu giờ ra' ) !== false, $sum_qtc );
/* Thu gọn bằng HTML thuần, không JavaScript — cả màn này vẫn phải không có script nào. */
t( 'thu gọn KHÔNG dùng JavaScript', stripos( $h_qtc, '<script' ) === false, $h_qtc );
t( 'và summary có CSS cho ra dáng bấm được', strpos( $h_qtc, 'summary{cursor:pointer' ) !== false );
/* Thu gọn chứ KHÔNG bỏ: nội dung vẫn phải nằm trong trang để Ctrl+F tìm thấy và để in ra giấy. */
t( 'thu gọn nhưng dữ liệu VẪN nằm trong trang (Ctrl+F vẫn thấy)',
	strpos( $h_qtc, 'QTC1' ) !== false && strpos( $h_qtc, '<th>Giờ vào</th>' ) !== false, $h_qtc );

/* ---- lọc theo NGÀY: kéo bảng chi tiết, KHÔNG kéo bảng tổng ---- */
$g_ng = array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07', 'cng' => '2026-07-06' );
$h_ng = vhcc_web( '135791', array(), $g_ng );
t( 'chọn một ngày thì bảng chi tiết bỏ ngày khác đi',
	substr_count( $h_ng, '2026-07-07' ) < substr_count( $h_qtc, '2026-07-07' ), $h_ng );
/* 🔴 Bảng tổng CỐ Ý không theo ngày — bản gốc `renderTotals` cũng vậy. Để nó tụt xuống một ngày
   thì cột "Ngày công" luôn bằng 1 và cả bảng hết ý nghĩa. */
/* ⚠️ Bám đúng MỘT DÒNG của bảng tổng, không dùng `.*?` bắc cầu: `.*?` với cờ /s vắt được qua
   cả dòng khác, nên phép thử vẫn xanh trong khi bảng tổng đã tụt xuống còn một ngày. Đã thử
   phá thật (cho bảng tổng ăn mảng đã lọc ngày) để chắc phép này đỏ. */
$re_tong_qtc = '/<td>QTC1<\/td><td[^>]*>[^<]*<\/td><td>2<\/td><td[^>]*>1<\/td>/';
t( 'bảng TỔNG (cả tháng, không lọc ngày) đúng: QTC1 có 2 ngày công, 1 ngày thiếu giờ ra',
	preg_match( $re_tong_qtc, $h_qtc ) === 1, $h_qtc );
t( 'chọn một ngày thì bảng TỔNG VẪN THẾ — ô Ngày chỉ kéo bảng chi tiết',
	preg_match( $re_tong_qtc, $h_ng ) === 1, $h_ng );
t( 'và màn nói rõ với người dùng chuyện đó', strpos( $h_ng, 'cả tháng' ) !== false );

/* ---- lọc theo MÃ NV: kéo cả hai bảng ---- */
$h_nv = vhcc_web( '135791', array(),
	array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07', 'cnv' => 'QTC1' ) );
/* ⚠️ Canh vào ĐÚNG bảng chi tiết, không quét cả trang. Từ 26/08/2026 hai tab gộp làm một, nên
   trên cùng màn còn có LƯỚI NGANG — mà lưới cố ý KHÔNG lọc theo mã (lọc thì nó chỉ còn một
   dòng và mất hết ý nghĩa "cả cơ sở trong một màn"). Quét cả trang là chốt này đỏ oan. */
$khoi_ct = preg_match( '/Chi tiết từng lượt(.*?)<\/details>/s', $h_nv, $m_ct ) ? $m_ct[1] : '';
t( 'tìm được bảng chi tiết trong màn đã gộp', '' !== $khoi_ct, substr( $h_nv, 0, 200 ) );
t( 'lọc theo mã NV thì bảng chi tiết chỉ còn người đó',
	strpos( $khoi_ct, 'QTC1' ) !== false && strpos( $khoi_ct, 'QTC2' ) === false, $khoi_ct );
t( 'lọc theo mã NV KHÔNG phân biệt hoa thường',
	strpos( vhcc_web( '135791', array(),
		array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07', 'cnv' => 'qtc1' ) ),
		'QTC1' ) !== false );

/* ---- nút 🚩: điền sẵn bằng ĐƯỜNG LIÊN KẾT, không bằng JavaScript ---- */
/* Cả màn quản trị này không có lấy một dòng script. Thêm một dòng vào đây là mở ra một thứ chỉ
   chạy khi trình duyệt chịu chạy, mà lại KHÔNG thử được bằng bộ thử PHP. */
t( 'màn quản trị KHÔNG có thẻ <script> nào', stripos( $h_qtc, '<script' ) === false, $h_qtc );
/* ⚠️ Danh sách này phải RỘNG, không chỉ mấy cái hay gặp. Em suýt nhét `onfocus="this.select()"`
   vào ô copy đường link cho tiện — `onfocus` không có trong danh sách cũ nên phép thử vẫn xanh.
   Một thuộc tính JS lẻ là cái khe để dòng thứ hai chui vào sau. */
t( 'và không có thuộc tính JS nào trong HTML',
	! preg_match( '/\son[a-z]+\s*=\s*"/i', $h_qtc ), $h_qtc );
t( 'nút 🚩 là đường liên kết chở sẵn ngày', strpos( $h_qtc, 'gnd=2026-07-06' ) !== false, $h_qtc );
t( 'và chở sẵn mã nhân viên', strpos( $h_qtc, 'gma=QTC1' ) !== false );
t( 'và neo xuống đúng khối gắn cờ', strpos( $h_qtc, '#gancoform' ) !== false );
t( 'khối gắn cờ có cái neo ấy để nhảy tới', strpos( $h_qtc, 'id="gancoform"' ) !== false );
$h_dien = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT',
	'cth' => '2026-07', 'gnd' => '2026-07-07', 'gma' => 'QTC1', 'gten' => 'Người QTC1' ) );
t( 'bấm 🚩 thì ô Ngày của khối gắn cờ được điền sẵn',
	strpos( $h_dien, 'id="co_ngay" name="ngay" type="date" required value="2026-07-07"' ) !== false, $h_dien );
t( 'ô Mã NV cũng được điền sẵn', strpos( $h_dien, 'value="QTC1"' ) !== false );
/* Điền sẵn NGÀY và MÃ thôi — lý do thì người ta phải tự gõ. Cờ không có lý do thì người đọc cờ
   chẳng biết phải kiểm cái gì, mà cờ ấy vẫn nằm đó chờ ai đó xử lý. */
t( 'nhưng LÝ DO vẫn để trống, bắt người gắn phải tự gõ',
	preg_match( '/id="co_nd"[^>]*>\s*<\/textarea>/', $h_dien ) === 1, $h_dien );
t( 'và màn nói rõ là còn thiếu lý do', strpos( $h_dien, 'còn thiếu lý do' ) !== false );

/* ---- cờ đã gắn thì cột Kiểm tra phải BIẾT ---- */
/* 🔴 `$b['co']` là hàng đọc thẳng từ bảng `ghi_chu` (khoá gạch dưới: ma_nv, ghi_chu,
   trang_thai), còn mảng `hang` dùng khoá lưng lạc đà. Hai kiểu khoá nằm cạnh nhau trong cùng
   một hàm — gõ nhầm thì cột Kiểm tra im lặng hiện 🚩 cho cả ngày ĐÃ có cờ, không báo gì. */
VHCC_Cham::luu_ghi_chu( $u_qtc, array( 'coso' => 'TUTU_BT', 'ngay' => '2026-07-07',
	'ma_nv' => 'QTC1', 'ho_ten' => 'Người QTC1', 'ghi_chu' => 'quên check-out' ) );
$h_co = vhcc_web( '135791', array(), $g_qtc );
t( 'ngày đã có cờ thì cột Kiểm tra ghi "đã gắn cờ", không mời gắn lần nữa',
	strpos( $h_co, 'đã gắn cờ' ) !== false, $h_co );
t( 'và rê chuột đọc được lý do (đúng khoá gạch dưới của bảng ghi_chu)',
	strpos( $h_co, 'title="quên check-out"' ) !== false, $h_co );
/* Cờ ĐÃ XỬ LÝ thì phải mời gắn cờ MỚI được — nếu không, một ngày sai lần thứ hai sẽ đứng sau
   cái cờ cũ đã đóng, và không ai gắn được cờ mới cho nó. */
$co_ds_qtc = VHCC_Cham::ds_ghi_chu( $u_qtc, 'TUTU_BT', '2026-07' );
VHCC_Cham::xu_ly_ghi_chu( $u_qtc, $co_ds_qtc[0]['flag_id'], 'đã hỏi, quên thật' );
$h_xong = vhcc_web( '135791', array(), $g_qtc );
t( 'cờ đã xử lý xong thì cột Kiểm tra mời gắn cờ MỚI, không kẹt ở cái cờ cũ',
	strpos( $h_xong, 'đã gắn cờ' ) === false
	&& strpos( $h_xong, 'gnd=2026-07-07' ) !== false, $h_xong );

/* ---- bộ lọc phải SỐNG SÓT qua một lượt POST ---- */
/* Gắn cờ xong mà bảng nhảy về cơ sở khác / tháng khác thì người ta phải chọn lại từ đầu cho
   từng cái cờ. Ô ẩn `o_loc` chở bộ lọc qua, nên MỌI tham số lọc phải có tên trong THAM_SO. */
foreach ( array( 'cbp', 'ccs', 'cth', 'cng', 'cnv' ) as $k_ts ) {
	t( 'tham số lọc "' . $k_ts . '" có trong THAM_SO (thiếu là gắn cờ xong mất bộ lọc)',
		in_array( $k_ts, VHCC_Web::THAM_SO, true ) );
}

/* 🔴 KHAI CẤU HÌNH VÀ NẠP DỮ LIỆU ĐÃ DỜI SANG TAB RIÊNG.
   Anh Thắng 26/08: *"cái này cho qua cấu hình đi, vì chỗ này là bảng công"* · *"Cho qua tab
   cấu hình luôn nhé"* · *"Đẩy này qua tab dữ liệu đầu vào đi"*.
   Bảng công là màn mở HẰNG NGÀY chỉ để đọc; khai cấu hình là việc làm một lần mà mỗi lần làm
   là đổi cách tính tiền của cả cơ sở. Để chung một màn thì thao tác hằng ngày cứ lướt ngang
   qua mấy cái nút đổi tiền.
   ⚠️ Mấy phép dưới đây soi HAI MÀN MỚI, không soi `$h_qtc` nữa. */
$g_ch  = array( 'man' => 'cau_hinh', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' );
$h_ch  = vhcc_web( '135791', array(), $g_ch );
$h_dl  = vhcc_web( '135791', array(), array( 'man' => 'du_lieu' ) );

t( 'thanh màn có tab Cấu hình', strpos( $h_qtc, 'man=cau_hinh' ) !== false, $h_qtc );
t( 'thanh màn có tab Dữ liệu đầu vào', strpos( $h_qtc, 'man=du_lieu' ) !== false, $h_qtc );
/* 🔴 Màn Bảng công phải CHỈ ĐƯỜNG sang chỗ mới — người quen tay tìm mãi không thấy rồi tưởng
   mất tính năng, đúng chuyện đã xảy ra với khối nạp công ("không thấy chỗ nạp dữ liệu công"). */
t( 'màn Bảng công chỉ đường sang tab Cấu hình',
	strpos( $h_qtc, 'Cấu hình</b></a>' ) !== false, $h_qtc );
t( 'và chỉ đường sang tab Dữ liệu đầu vào',
	strpos( $h_qtc, 'Dữ liệu đầu vào</b></a>' ) !== false, $h_qtc );
/* Và không còn bày mấy khối ấy trên màn Bảng công nữa. */
t( 'màn Bảng công KHÔNG còn khối xếp bộ phận', strpos( $h_qtc, 'id="bophan"' ) === false, $h_qtc );
t( 'màn Bảng công KHÔNG còn khối nạp .csv', strpos( $h_qtc, 'id="napcong"' ) === false, $h_qtc );

/* ---- sổ nhật ký giờ công · nạp công từ .csv ---- */
/* 🔴 KHỐI "CHẤM CÔNG BÙ" RỜI ĐÃ BỎ (anh Thắng 26/08: *"Vẫn còn"*, sau khi khối "Sửa giờ công"
   rời bị bỏ ở lượt trước). Bù và sửa nay làm NGAY TẠI Ô trong lưới cả tháng.
   Chỗ `id="bucong"` giữ nguyên tên — mọi ô trong lưới đều trỏ tới `#bucong`, đổi tên là mọi
   liên kết trong lưới rơi vào hư không. Nay nó là SỔ NHẬT KÝ, không phải biểu mẫu. */
t( 'màn có khối sổ nhật ký giờ công', strpos( $h_qtc, 'id="bucong"' ) !== false, $h_qtc );
t( 'sổ nói rõ ghi cả bù lẫn sửa và không xoá được',
	strpos( $h_qtc, 'không xoá được' ) !== false, $h_qtc );
t( 'và vẫn nói rõ không tự bù cho mình', strpos( $h_qtc, 'Không tự bù cho mình' ) !== false
	|| strpos( $h_qtc, 'không tự bù' ) !== false, $h_qtc );
/* 🔴 KHÔNG CÒN BIỂU MẪU BÙ RỜI Ở CUỐI MÀN. Để lại là hai biểu mẫu giống hệt nhau trên cùng
   một màn và người dùng phải đoán cái nào đang dùng. */
t( 'cuối màn KHÔNG còn biểu mẫu bù rời (gõ tay ngày + mã)',
	strpos( $h_qtc, 'id="bu_ngay"' ) === false && strpos( $h_qtc, 'id="bu_ma"' ) === false, $h_qtc );

/* 🔴 NGƯỜI CẢ THÁNG CHƯA CHẤM LẦN NÀO VẪN PHẢI CÓ MỘT HÀNG TRONG LƯỚI.
   Đây là việc DUY NHẤT mà khối bù rời làm được còn lưới thì không: lưới cũ dựng danh sách
   người từ CHÍNH các lượt chấm, nên ai chưa bấm lần nào thì không có hàng — không có ô nào để
   bấm — mà đó đúng là người CẦN bù nhất (máy hỏng cả tháng, người mới chưa đăng vân tay).
   Bỏ khối rời mà không vá chỗ này là bỏ mất một việc, chứ không phải dọn màn hình.
   Nên lưới kéo thêm người từ SỔ NHÂN SỰ. Phép dưới đây canh đúng chuyện đó. */
$r_hs_trong = VHCC_NhanSu::luu_ho_so( $u_qtc,
	array( 'ma_nv' => 'QTC9', 'ho_ten' => 'Người Chưa Chấm', 'cua_hang' => 'TUTU_BT' ) );
t( 'khai được một người chưa hề chấm công', ! empty( $r_hs_trong['ok'] ), $r_hs_trong );
$h_trong_luoi = vhcc_web( '135791', array(),
	array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' ) );
t( '🔴 người cả tháng chưa chấm lần nào VẪN có hàng trong lưới',
	strpos( $h_trong_luoi, 'Người Chưa Chấm' ) !== false, $h_trong_luoi );
t( 'và hàng ấy có ô bấm được để bù giờ',
	strpos( $h_trong_luoi, 'gma=QTC9' ) !== false, $h_trong_luoi );
t( 'hàng ấy gắn nhãn "chưa chấm" — kẻo trông như một hàng lỗi',
	preg_match( '/Người Chưa Chấm[^<]*<span class="duoi"[^>]*>chưa chấm<\/span>/', $h_trong_luoi ) === 1,
	$h_trong_luoi );
/* Và bấm vào ô của họ thì mở được hàng bù, y như người đã có giờ. */
$h_bu_trong = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT',
	'cth' => '2026-07', 'gnd' => '2026-07-03', 'gma' => 'QTC9' ) );
t( 'bù được cho người chưa chấm lần nào',
	strpos( $h_bu_trong, '<input type="hidden" name="ma_nv" value="QTC9">' ) !== false, $h_bu_trong );

/* Biểu mẫu bù nay mở NGAY DƯỚI ô vừa bấm: bấm ô trống -> hàng bù, có sẵn ngày và mã. */
$h_o_bu = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT',
	'cth' => '2026-07', 'gnd' => '2026-07-02', 'gma' => 'QTC1' ) );
t( 'bấm một ô trống thì mở hàng bù ngay dưới dòng ấy',
	strpos( $h_o_bu, 'class="hang-sua"' ) !== false, $h_o_bu );
t( 'hàng bù mang sẵn ngày của ô vừa bấm, không phải gõ lại',
	strpos( $h_o_bu, '<input type="hidden" name="ngay" value="2026-07-02">' ) !== false, $h_o_bu );
t( 'và mang sẵn mã của người ở dòng ấy',
	strpos( $h_o_bu, '<input type="hidden" name="ma_nv" value="QTC1">' ) !== false, $h_o_bu );
t( 'hàng bù vẫn đòi lý do', strpos( $h_o_bu, 'name="ly_do"' ) !== false, $h_o_bu );
/* Ngày không còn gõ tay được nên `max=` trên ô ngày hết chỗ dựa — chốt ngày tương lai chuyển
   hẳn về LÕI, chỗ chặn thật. Chốt trên trình duyệt vốn chỉ là lời nhắc. */
$ngay_mai = gmdate( 'Y-m-d', strtotime( (string) current_time( 'Y-m-d' ) ) + 86400 );
t( 'lõi CHỐI bù cho ngày tương lai', '' !== VHCC_Bu::ngay_hop_le( $ngay_mai ),
	VHCC_Bu::ngay_hop_le( $ngay_mai ) );
$r_mai = VHCC_Bu::ghi( $u_qtc, array( 'coso' => 'TUTU_BT', 'ngay' => $ngay_mai,
	'ma_nv' => 'QTC1', 'bu_vao' => '08:00', 'bu_ra' => '', 'ly_do' => 'thử ngày mai' ) );
t( 'và đường ghi thật cũng chối, không chỉ hàm kiểm', empty( $r_mai['ok'] ), $r_mai );

t( 'màn có khối Nạp công từ .csv', strpos( $h_dl, 'id="napcong"' ) !== false, $h_qtc );
/* 🔴 Đây là câu trả lời cho đúng chỗ anh Thắng vấp: nạp 240 hồ sơ xong bảng công vẫn trắng.
   Màn phải TỰ NÓI ra chuyện hai nút "nạp" là hai việc khác nhau. */
t( 'khối nạp công nói rõ nút .csv ở màn Hồ sơ KHÔNG nạp giờ công',
	strpos( $h_dl, 'sổ nhân sự' ) !== false && strpos( $h_dl, 'bảng công vẫn trắng' ) !== false, $h_dl );
t( 'có nút Xem trước', strpos( $h_dl, 'value="xem_cong"' ) !== false, $h_dl );
/* 🔴 Anh Thắng 26/08: *"không thấy chỗ nạp dữ liệu công"*. Bản đầu đặt khối nạp ở CUỐI màn
   bảng công, sau bảng — mà bảng chỉ vẽ khi đã chọn cơ sở VÀ bấm Xem. Tức là đúng lúc bảng công
   còn TRỐNG, lúc người ta cần nạp nhất, thì cái nút nạp là thứ duy nhất không hiện.
   Nay khối nạp ở tab riêng (*"Đẩy này qua tab dữ liệu đầu vào đi"*), nên màn bảng công KHÔNG
   vẽ nó nữa — nhưng phải CHỈ ĐƯỜNG sang đó, kẻo rơi lại đúng cái bẫy cũ dưới hình dạng khác. */
/* Lọc theo một bộ phận KHÔNG có cơ sở nào -> rơi đúng vào đường thoát sớm của `the_bang_cham`.
   (Mở màn trơn không rơi vào đó được: sổ thử chỉ có một cơ sở nên hệ tự chọn giúp.) */
$h_trong = vhcc_web( '135791', array(),
	array( 'man' => 'cham', 'cbp' => 'Bộ phận không tồn tại' ) );
t( 'lọc ra 0 cơ sở thì bảng KHÔNG vẽ (đường thoát sớm)',
	strpos( $h_trong, 'Chi tiết từng lượt' ) === false, $h_trong );
t( '🔴 chưa chọn được cơ sở thì màn CHỈ ĐƯỜNG sang tab Dữ liệu đầu vào',
	strpos( $h_trong, 'man=du_lieu' ) !== false
	&& strpos( $h_trong, 'Dữ liệu đầu vào</b></a>' ) !== false, $h_trong );
/* Còn khối nạp thật thì nằm ở tab kia, và nó có ô chọn cơ sở RIÊNG — không phải chờ bảng nào vẽ. */
t( 'khối nạp có ô chọn cơ sở RIÊNG', strpos( $h_dl, 'id="ncs"' ) !== false, $h_dl );
t( 'ô chọn cơ sở của khối nạp có liệt kê cơ sở thật',
	strpos( $h_dl, '>TUTU_BT<' ) !== false, $h_dl );
t( 'và vẫn nhận được file', strpos( $h_dl, 'enctype="multipart/form-data"' ) !== false );
t( 'khối nạp đứng một mình vẫn nói rõ nút .csv kia là nạp NHÂN SỰ',
	strpos( $h_dl, 'sổ nhân sự' ) !== false, $h_dl );
t( 'và nút Nạp thật',  strpos( $h_dl, 'value="nap_cong"' ) !== false, $h_dl );
t( 'form nạp nhận được file', strpos( $h_dl, 'enctype="multipart/form-data"' ) !== false, $h_dl );
/* 🔴 Ô gõ cơ sở MỚI. Anh Thắng 26/08: *"nếu chưa có cơ sở cũ chỗ này thì sao"* — ô xổ xuống chỉ
   liệt kê cơ sở ĐÃ có dữ liệu, nên cơ sở mới mở không nạp được. Vòng tròn y hệt cái vòng tròn
   PIN hồi đầu: muốn vào thì phải có tài khoản, muốn có tài khoản thì phải vào được. */
t( 'có ô gõ cơ sở MỚI cho nơi chưa có trong danh sách',
	strpos( $h_dl, 'name="ccs_moi"' ) !== false, $h_qtc );
t( 'và nói rõ ô ấy thắng ô xổ xuống', strpos( $h_dl, 'thắng ô xổ xuống' ) !== false, $h_dl );

/* ---- bù mở cho Cửa hàng trưởng, nạp công thì KHÔNG ---- */
/* Hai việc trông giống nhau ("thêm giờ vào bảng") nhưng bù là sửa MỘT ô của một người, còn nạp
   là đổ hàng nghìn lượt vào cả một tháng của cả cơ sở. Khác bậc rủi ro thì phải khác bậc quyền. */
VHCC_NguoiDung::luu( '', 'CHT Soát Công', '357913', 'Cửa hàng trưởng', 'TUTU_BT' );
$h_cht = vhcc_web( '357913', array(), $g_qtc );
t( 'Cửa hàng trưởng vào được màn bảng công', strpos( $h_cht, 'name="pin"' ) === false, $h_cht );
t( 'và THẤY khối Chấm công bù', strpos( $h_cht, 'id="bucong"' ) !== false, $h_cht );
t( 'nhưng KHÔNG thấy khối Nạp công', strpos( $h_cht, 'id="napcong"' ) === false, $h_cht );
/* Ẩn cái khối không phải là gác cửa — gửi thẳng lượt POST cũng phải bị chối. */
$_POST = array( 'viec' => 'nap_cong' );
$r_np  = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'nap_cong', array( 'name' => 'CHT', 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT' ) ) );
t( 'và POST thẳng việc nap_cong cũng KHÔNG lọt', is_array( $r_np ) && ! isset( $r_np[0]['xong'] ), $r_np );
$_POST = array();
/* Nhân viên thì không có cả hai. */
$h_nv2 = vhcc_web( '864202', array(), $g_qtc );
t( 'Nhân viên không thấy khối bù', strpos( $h_nv2, 'id="bucong"' ) === false, $h_nv2 );
$_POST = array( 'viec' => 'bu' );
$r_bu  = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'bu', array( 'name' => 'NV', 'role' => 'Nhân viên', 'coso' => 'TUTU_BT' ) ) );
t( 'và POST thẳng việc bu cũng bị chối', is_array( $r_bu ) && ! isset( $r_bu[0]['xong'] ), $r_bu );
$_POST = array();

/* ---- khối SỬA GIỜ CÔNG: chỉ Admin ----
   Anh Thắng 26/08: *"admin có quyền chỉnh sửa lại giờ công cho nhân viên"*. Đây là cửa DUY NHẤT
   đè được lên giờ máy đã ghi, nên nó phải đứng cao hơn cả bù lẫn nạp công. */
/* 🔴 KHỐI "SỬA GIỜ CÔNG" Ở CUỐI MÀN ĐÃ BỎ (anh Thắng 26/08: *"Loại bỏ chỗ này. Chỗ này đã hiện
   đủ rồi."*). Hàng sửa nội tuyến trong lưới làm đúng việc ấy mà không bắt ai gõ lại ngày và mã.
   Để lại cả hai là hai biểu mẫu giống hệt nhau trên cùng một màn, và người dùng phải đoán cái
   nào mới là cái đang dùng. */
t( 'KHÔNG còn khối Sửa giờ công rời ở cuối màn',
	strpos( $h_qtc, 'id="suagio"' ) === false, $h_qtc );
t( 'và không còn hàm dựng nó trong mã',
	strpos( file_get_contents( VHCC_DIR . 'includes/class-vhcc-web.php' ), 'function the_sua_gio' ) === false );
/* Nhưng khối CHẤM CÔNG BÙ thì GIỮ: người chưa có dòng nào trong tháng thì lưới không vẽ hàng
   của họ, tức là không có ô nào để bấm — bù cho họ phải đi bằng đường khác. */
t( 'khối Chấm công bù vẫn còn', strpos( $h_qtc, 'id="bucong"' ) !== false, $h_qtc );

/* Bảng chi tiết vẫn có cột ✏️, và nay nó neo thẳng vào HÀNG SỬA trong lưới. */
t( 'bảng chi tiết có cột Sửa cho Admin', strpos( $h_qtc, '<th>Sửa</th>' ) !== false, $h_qtc );
t( 'và bấm ✏️ thì điền sẵn ngày + mã',
	preg_match( '/sgn=\d{4}-\d{2}-\d{2}[^"]*sgm=/', $h_qtc ) === 1, $h_qtc );
/* 🔴 Neo phải trỏ vào ĐÚNG hàng sửa (`#suaday`), không còn `#suagio` — khối ấy không tồn tại
   nữa, neo tới nó là bấm xong trang đứng im và người ta tưởng nút hỏng. */
t( 'neo trỏ vào hàng sửa trong lưới, không phải khối đã bỏ',
	strpos( $h_qtc, '#suaday' ) !== false && strpos( $h_qtc, '#suagio' ) === false, $h_qtc );

/* 🔴 CỬA HÀNG TRƯỞNG KHÔNG SỬA ĐƯỢC — kể cả POST thẳng. Ẩn cái nút không phải là gác cửa. */
t( 'và bảng chi tiết của họ KHÔNG có cột Sửa', strpos( $h_cht, '<th>Sửa</th>' ) === false, $h_cht );
$_POST = array( 'viec' => 'sua_gio' );
$r_sg  = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'sua_gio', array( 'name' => 'CHT', 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT',
		'ma_nv' => 'NVCHT' ) ) );
t( 'POST thẳng việc sua_gio cũng KHÔNG lọt',
	is_array( $r_sg ) && ! isset( $r_sg[0]['xong'] ), $r_sg );
$_POST = array();

/* ---- chạy thật một lượt sửa, qua đúng cửa POST của trang ---- */
/* Mã phải có hồ sơ thật — sửa cho một mã không tồn tại là viết công cho một người không có. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ),
	array( 'ma_nv' => 'QTC1', 'ho_ten' => 'Người QTC1', 'cua_hang' => 'TUTU_BT' ) );
$tok_sg = VHCC_Auth::login( '135791' )['token'];
$_COOKIE[ VHCC_Web::COOKIE ] = $tok_sg;
$_POST = array( 'viec' => 'sua_gio', 'ky' => VHCC_Web::chu_ky( $tok_sg ),
	'ccs' => 'TUTU_BT', 'ngay' => '2026-07-07', 'ma_nv' => 'QTC1',
	'sg_vao' => '09:45', 'ly_do' => 'máy lệch đồng hồ, đối chiếu camera' );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array();
$h_sau = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' ) );
t( '🔴 sửa qua trang thật thì bảng hiện giờ MỚI',
	strpos( $h_sau, '09:45' ) !== false, $h_sau );
/* Sổ nhật ký gộp cả bù lẫn sửa, và nói rõ cũ -> mới. */
t( 'sổ nhật ký có cột Giờ cũ', strpos( $h_sau, '<th>Giờ cũ</th>' ) !== false, $h_sau );
t( 'và đánh dấu lượt này là "sửa đè", không phải "bù"',
	strpos( $h_sau, 'sửa đè' ) !== false, $h_sau );

// ====== 48b. XẾP CƠ SỞ VÀO BỘ PHẬN + THỨ TỰ KHỐI CẤU HÌNH
/* Anh Thắng 26/08/2026: *"bổ sung set cơ sở thuộc bộ phận nào"*, *"thêm bộ phận PART TIME"*,
   *"Đưa 3 cái này lên trên"*. */

t( 'danh sách bộ phận có PART TIME', in_array( 'Part time', VHCC_Luong::BP_DS, true ),
	VHCC_Luong::BP_DS );
/* 🔴 Khối nào khai được công thức riêng thì phải đúng bằng danh sách bộ phận — thêm một bộ phận
   mà quên mở khối cho nó là bộ phận ấy vĩnh viễn chạy bản chung, im lặng. */
teq( 'và mỗi bộ phận là một khối khai công thức riêng được',
	array_values( (array) VHCC_Luong::BP_DS ), VHCC_Luong::vp_cfg_ds_khoi() );

$h_bp = vhcc_web( '135791', array(), array( 'man' => 'cau_hinh', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' ) );
t( 'màn có khối xếp bộ phận', strpos( $h_bp, 'id="bophan"' ) !== false, $h_bp );
foreach ( VHCC_Luong::BP_DS as $_b ) {
	t( 'ô chọn có bộ phận "' . $_b . '"',
		strpos( $h_bp, '<option value="' . esc_attr( $_b ) . '"' ) !== false, $h_bp );
}
/* 🔴 Còn cơ sở CHƯA XẾP thì khối tự MỞ. Đó là thứ duy nhất làm mọi phép tính công im lặng không
   chạy — thu gọn nó lại là giấu đúng cái đang hỏng. */
t( 'còn cơ sở chưa xếp thì khối tự mở', strpos( $h_bp, 'id="bophan"><details open>' ) !== false, $h_bp );
t( 'và nhãn nói sẵn còn bao nhiêu cơ sở chưa xếp', strpos( $h_bp, 'chưa xếp</b>' ) !== false, $h_bp );
/* Nói THẲNG hậu quả — "Chưa xếp" nghe như một trạng thái vô hại. */
t( 'nói rõ chưa xếp thì KHÔNG có công thức tính công',
	strpos( $h_bp, 'không có công thức tính công' ) !== false, $h_bp );

/* 🔴 THỨ TỰ TRÊN MÀN CẤU HÌNH: BỘ PHẬN -> CÁCH TÍNH -> (CA hoặc CÔNG THỨC).
   Hai khối đầu là bảng của MỌI cơ sở và chúng quyết định khối thứ ba hiện cái nào — xếp cơ sở
   vào bộ phận rồi chọn cách tính, xong mới tới bộ số của riêng cơ sở ấy. Đảo lại là bắt người
   ta khai một bộ số trước khi biết bộ số đó có được dùng hay không.
   (Trước 26/08/2026 mấy khối này nằm trên màn Bảng công và phép thử canh chúng đứng TRƯỚC bảng
   số. Anh Thắng cho dời sang tab riêng, nên nay canh thứ tự TRONG tab ấy.) */
$_vt_bp  = strpos( $h_bp, 'id="bophan"' );
$_vt_ct  = strpos( $h_bp, 'id="cachtinh"' );
t( 'màn Cấu hình có khối xếp bộ phận', false !== $_vt_bp, substr( $h_bp, 0, 200 ) );
t( 'màn Cấu hình có khối cách tính',   false !== $_vt_ct, substr( $h_bp, 0, 200 ) );
t( 'bộ phận đứng TRƯỚC cách tính', $_vt_bp < $_vt_ct, array( $_vt_bp, $_vt_ct ) );
/* Khối thứ ba là CA hay CÔNG THỨC tuỳ cơ sở — xem khối "chỉ bày đúng một trong hai" ở dưới. */
$_vt_ba = max( (int) strpos( $h_bp . 'x', 'id="khaica"' ), (int) strpos( $h_bp . 'x', 'id="congthuc"' ) );
t( 'và khối của riêng cơ sở đứng SAU cả hai', $_vt_ba > $_vt_ct, $_vt_ba );

/* ============================================================ CỘT "ID" KHÔNG PHẢI MÃ NV */
/* 🔴 Anh Thắng 26/08/2026: *"Hồ sơ nhân sự đang bị lỗi giữa các nhân sự"*.
   Sổ xuất từ Google Sheets gần như luôn có một cột đánh số dòng tên `ID` hoặc `STT`. Bản đồ cột
   cũ nhận `id` làm Mã NV, nên cả sổ mang mã 1, 2, 3… lẫn với mã thật (`MNLX1CTY0001`).
   Mà MÃ LÀ KHOÁ: nạp file thứ hai, dòng 15 của file mới GHI ĐÈ lên người đang mang mã 15 —
   hai người khác nhau, cùng một khoá. Hồ sơ người này lẫn sang người kia, không có gì kêu. */

t( '🔴 "id" KHÔNG còn được nhận làm Mã NV',
	! isset( VHCC_NapCsv::BAN_DO['id'] ) || 'ma_nv' !== VHCC_NapCsv::BAN_DO['id'] );
foreach ( array( 'stt', 'id', 'sothutu', 'index' ) as $_k ) {
	t( 'cột "' . $_k . '" bỏ qua CÓ CHỦ Ý (không kể là cột lạ)',
		isset( VHCC_NapCsv::COT_BO_QUA[ $_k ] ) );
}
/* Mã thật vẫn phải nhận bình thường — sửa cái này không được làm hỏng đường nạp đang chạy. */
foreach ( array( 'manv', 'ma', 'manhanvien', 'employeeno' ) as $_k ) {
	teq( 'cột "' . $_k . '" vẫn là Mã NV', 'ma_nv', VHCC_NapCsv::BAN_DO[ $_k ] );
}

/* Tệp CHỈ có cột ID + Họ tên -> phải DỪNG LẠI, không được nạp bừa theo số dòng. */
$_csv_id = "ID,Họ và tên,Cơ sở\n1,Nguyễn Văn Một,CS_A\n2,Trần Thị Hai,CS_A\n";
$_r_id   = VHCC_NapCsv::nap( $_csv_id, true );
t( '🔴 tệp chỉ có cột ID thì CHỐI, không nạp theo số dòng', empty( $_r_id['ok'] ) );
t( 'và nói rõ là thiếu cột MÃ NV',
	false !== strpos( (string) ( isset( $_r_id['error'] ) ? $_r_id['error'] : '' ), 'MÃ NV' ) );

/* Cột tên là "Mã" nhưng ruột toàn số ngắn -> VẪN nạp được (có nơi đánh mã bằng số thật),
   nhưng phải KÊU LÊN ở bước xem trước. Chối thẳng là khoá cửa của họ. */
$_csv_so = "Mã,Họ và tên\n1,Người Một\n2,Người Hai\n3,Người Ba\n4,Người Bốn\n5,Người Năm\n6,Người Sáu\n";
$_r_so   = VHCC_NapCsv::nap( $_csv_so, true );
t( 'mã toàn số ngắn thì vẫn xem trước được', ! empty( $_r_so['ok'] ) );
$_canh = implode( ' | ', (array) ( isset( $_r_so['canh'] ) ? $_r_so['canh'] : array() ) );
t( '🔴 nhưng KÊU LÊN là trông như số thứ tự',
	false !== strpos( $_canh, 'SỐ THỨ TỰ' ) );
t( 'và nói ra hậu quả: ghi đè lên người khác',
	false !== strpos( $_canh, 'GHI ĐÈ' ) );

/* Mã thật thì KHÔNG kêu — cảnh báo kêu oan là cảnh báo người ta học cách bỏ qua. */
$_csv_that = "Mã NV,Họ và tên\nMNLX1CTY0001,Người Một\nMNKT8CTY0001,Người Hai\n"
	. "KHKT1CTY0001,Người Ba\nMNVH1MTD0001,Người Bốn\nMNKY2MTD0001,Người Năm\nMNMK2KVC0001,Người Sáu\n";
$_r_that = VHCC_NapCsv::nap( $_csv_that, true );
$_c_that = implode( ' | ', (array) ( isset( $_r_that['canh'] ) ? $_r_that['canh'] : array() ) );
t( 'mã thật thì KHÔNG kêu oan', false === strpos( $_c_that, 'SỐ THỨ TỰ' ) );


/* 🔴 MỌI BIỂU MẪU `method="get"` PHẢI TỰ CHỞ LẤY `man` CỦA NÓ.
   Anh Thắng 26/08: *"Bấm gõ tìm kiếm nhân sự nó cứ nhảy sang trang chính"*. Gửi biểu mẫu GET
   là trình duyệt dựng LẠI thanh địa chỉ CHỈ TỪ các ô trong biểu mẫu — mọi tham số đang có trên
   địa chỉ cũ, kể cả `man=…`, biến mất. Không còn `man` thì rơi về màn mặc định.

   ⚠️ CANH BẰNG CÁCH ĐẾM TRÊN MÃ NGUỒN, không canh từng màn một. Canh từng màn thì biểu mẫu GET
      thứ năm viết sau này lại sót, và nó sót im lặng y như lần này. */
$_ma_form = vhcc_bo_chu_thich( file_get_contents( VHCC_DIR . 'includes/class-vhcc-web.php' ) );
$_ds_form = explode( "<form method=\"get\"", $_ma_form );
array_shift( $_ds_form );
t( 'màn quản trị có nhiều biểu mẫu GET để canh', count( $_ds_form ) >= 4, count( $_ds_form ) );
foreach ( $_ds_form as $_i => $_than ) {
	/* Chỉ soi tới chỗ đóng biểu mẫu — ô `man` của biểu mẫu SAU không được tính cho cái này. */
	$_het = strpos( $_than, "</form>" );
	if ( false !== $_het ) { $_than = substr( $_than, 0, $_het ); }
	t( '🔴 biểu mẫu GET #' . ( $_i + 1 ) . ' có chở ô ẩn `man`',
		false !== strpos( $_than, 'name="man"' ), substr( $_than, 0, 220 ) );
}

/* Và canh trên MÀN THẬT: gõ tìm ở Hồ sơ thì biểu mẫu phải giữ người ta ở lại Hồ sơ. */
$_h_hs = vhcc_web( '135791', array(), array( 'man' => 'ho_so' ) );
t( 'màn Hồ sơ vẽ được', strpos( $_h_hs, 'Hồ sơ nhân sự' ) !== false, substr( $_h_hs, 0, 200 ) );
t( '🔴 ô Tìm nhân sự chở `man=ho_so`, không nhảy về Trang chính',
	preg_match( '/<form method="get"[^>]*>(?:(?!<\/form>).)*?name="man" value="ho_so"/s', $_h_hs ) === 1,
	'biểu mẫu Tìm không chở man' );

/* 🔴 PHIÊN BẢN ĐANG CHẠY Ở CUỐI MỖI TRANG.
   Anh Thắng 26/08: *"Cuối mỗi tất cả các trang bổ sung tên phiên bản đang chạy để theo dõi"*.
   Mỗi lần sửa xong là cài đè một tệp .zip lên hosting; không có số trên màn thì không cách nào
   biết mình đang nhìn bản mới hay bản cũ còn trong bộ nhớ đệm trình duyệt — và mọi câu "sửa
   rồi mà vẫn thế" đều bắt đầu từ chỗ ấy.
   ⚠️ Số phải ĐỌC TỪ HẰNG của plugin, không gõ tay: nhãn nói một đằng mã chạy một nẻo là một
      cái đồng hồ chạy sai, tệ hơn không có đồng hồ. */
$h_pb = vhcc_web( '135791' );
t( 'chân trang có nhãn phiên bản', strpos( $h_pb, 'class="cty-pb"' ) !== false, $h_pb );
t( '🔴 và số ấy ĐÚNG BẰNG hằng VHCC_VERSION',
	strpos( $h_pb, 'Chấm công ' . VHCC_VERSION ) !== false, VHCC_VERSION );
t( 'nhãn phiên bản có kiểu chữ thật', strpos( $h_pb, '.cty-pb{' ) !== false );
/* Chân trang dùng chung cho nhiều plugin — plugin nào đang nạp thì số của plugin ấy phải có mặt. */
if ( defined( 'VHCP_VERSION' ) ) {
	t( 'plugin chi phí cùng nạp thì số của nó cũng hiện',
		strpos( $h_pb, 'Chi phí ' . VHCP_VERSION ) !== false, $h_pb );
}

/* 🔴 BẢNG "NGÀY THIẾU GIỜ RA" THU GỌN SẴN.
   Anh Thắng 26/08: *"Cho này gọn lại, khi nào bấm xổ mới xổ ra"*. Số dòng do dữ liệu quyết
   định — sổ thật đang 36 ngày và nó xổ hết ra giữa màn, đẩy mọi thứ phía dưới đi mấy màn hình.
   Người mở màn bảng công phần lớn chỉ cần biết CÓ BAO NHIÊU; con số nằm trên nhãn. */
$h_thieu = vhcc_web( '135791', array(), $g_qtc );
if ( strpos( $h_thieu, 'Ngày thiếu giờ ra' ) !== false ) {
	t( '🔴 bảng "Ngày thiếu giờ ra" gói trong khối gập',
		preg_match( '/<details><summary><b>Ngày thiếu giờ ra<\/b>/', $h_thieu ) === 1, 'không thấy details' );
	t( 'và gập SẴN (không có thuộc tính open)',
		preg_match( '/<details open><summary><b>Ngày thiếu giờ ra/', $h_thieu ) === 0, 'đang mở sẵn' );
	t( 'nhãn nói sẵn bao nhiêu ngày, khỏi phải mở ra đếm',
		preg_match( '/Ngày thiếu giờ ra<\/b> — <span class="chu-hong">\d+ ngày<\/span>/', $h_thieu ) === 1,
		'nhãn không có con số' );
	/* Gập bằng <details> của HTML, không phải JavaScript — cả màn này không có một dòng script. */
	t( 'khối gập KHÔNG dùng JavaScript', stripos( $h_thieu, '<script' ) === false );
}

/* 🔴 CHỈ BÀY ĐÚNG MỘT TRONG HAI: CA (cho cơ sở theo giờ) hoặc CÔNG THỨC (cho Văn phòng).
   Anh Thắng 26/08: *"Cơ sở mới có ca, Bộ Phận VP không có ca"* và *"Bộ phận văn phòng tính
   theo công thức này (tức tính dạng công). Cái trên là tính theo dạng giờ, cho cơ sở."*

   Cơ sở tính THEO GIỜ thì con số là giờ ra trừ giờ vào — bộ bậc thang / ca đêm / công bù không
   hề được dùng tới. Cơ sở tính THEO CÔNG thì không chạy ca gãy, khai ca là khai cho vui.
   ⚠️ Bày nhầm khối còn nguy hiểm hơn thiếu khối: người khai tưởng mình vừa đổi được cái gì đó,
      lưu xong thấy bảng không nhúc nhích, và không có gì giải thích. */
$_CS_2M = 'HAI_MAT_1';
vhcc_bo_phan( $_CS_2M, 'Khu vui chơi' );
$_AD_2M = array( 'name' => 'Admin', 'role' => 'Admin', 'coso' => '' );
VHCC_Luong::dat_cach_tinh( $_AD_2M, array( $_CS_2M => 'gio' ) );
$h_2m_gio = vhcc_web( '135791', array(), array( 'man' => 'cau_hinh', 'ccs' => $_CS_2M ) );
t( '🔴 cơ sở theo GIỜ: có khối Khai ca', strpos( $h_2m_gio, 'id="khaica"' ) !== false, $h_2m_gio );
t( '🔴 cơ sở theo GIỜ: KHÔNG bày khối Công thức tính công',
	strpos( $h_2m_gio, 'id="congthuc"' ) === false, $h_2m_gio );
t( 'và nói ra vì sao không bày', strpos( $h_2m_gio, 'THEO GIỜ' ) !== false, $h_2m_gio );

VHCC_Luong::dat_cach_tinh( $_AD_2M, array( $_CS_2M => 'cong' ) );
$h_2m_cong = vhcc_web( '135791', array(), array( 'man' => 'cau_hinh', 'ccs' => $_CS_2M ) );
t( '🔴 cơ sở theo CÔNG: có khối Công thức tính công',
	strpos( $h_2m_cong, 'id="congthuc"' ) !== false, $h_2m_cong );
t( '🔴 cơ sở theo CÔNG: KHÔNG bày khối Khai ca',
	strpos( $h_2m_cong, 'id="khaica"' ) === false, $h_2m_cong );
t( 'và nói ra vì sao không bày', strpos( $h_2m_cong, 'THEO CÔNG' ) !== false, $h_2m_cong );
/* Hai khối bảng-của-mọi-cơ-sở thì luôn có, không phụ thuộc cơ sở đang chọn. */
foreach ( array( 'gio' => $h_2m_gio, 'cong' => $h_2m_cong ) as $_k2 => $_h2 ) {
	t( 'khối bộ phận luôn có (' . $_k2 . ')',  strpos( $_h2, 'id="bophan"' ) !== false );
	t( 'khối cách tính luôn có (' . $_k2 . ')', strpos( $_h2, 'id="cachtinh"' ) !== false );
}
VHCC_Luong::dat_cach_tinh( $_AD_2M, array( $_CS_2M => '' ) );

/* ---- lưu qua đúng cửa POST ---- */
vhcc_bo_phan( 'BP_THU_1', '' );
teq( 'cơ sở mới chưa xếp bộ phận', VHCC_Luong::BP_CHUA_XEP, VHCC_Luong::bo_phan_cua( 'BP_THU_1' ) );
$tok_bp = VHCC_Auth::login( '135791' )['token'];
$_COOKIE[ VHCC_Web::COOKIE ] = $tok_bp;
$_POST = array( 'viec' => 'bo_phan', 'ky' => VHCC_Web::chu_ky( $tok_bp ),
	'bp' => array( 'BP_THU_1' => 'Part time' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array();
teq( '🔴 xếp được cơ sở vào PART TIME qua trang thật', 'Part time', VHCC_Luong::bo_phan_cua( 'BP_THU_1' ) );

/* Bộ phận lạ -> chối, không lặng lẽ đổi thành "Chưa xếp". */
$r_bp = VHCC_NhanSu::xep_bo_phan( array( 'role' => 'Admin' ), 'BP_THU_1', 'Bộ phận bịa' );
t( 'bộ phận lạ bị chối', empty( $r_bp['ok'] ), $r_bp );
teq( 'và cơ sở giữ nguyên bộ phận cũ', 'Part time', VHCC_Luong::bo_phan_cua( 'BP_THU_1' ) );

/* Nhân viên POST thẳng cũng không lọt — ẩn cái khối không phải là gác. */
$_POST = array( 'viec' => 'bo_phan' );
$r_bp2 = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'bo_phan', array( 'name' => 'NV', 'role' => 'Nhân viên', 'coso' => 'TUTU_BT' ) ) );
t( 'Nhân viên POST thẳng việc bo_phan bị chối',
	is_array( $r_bp2 ) && ! isset( $r_bp2[0]['xong'] ), $r_bp2 );
$_POST = array();

// ====== 48c. CÔNG THỨC TÍNH CÔNG RIÊNG TỪNG KHỐI
/* Anh Thắng 26/08/2026: *"bổ sung cho khối văn phòng phương pháp tính công"* — kèm ảnh màn cấu
   hình đang để **ca ngày 08:30–21:30**. Đó là khung của CỬA HÀNG, nhưng cả hệ chỉ có MỘT bộ số
   nên đúng bộ số ấy cũng đang tính công cho Văn phòng, nơi người ta về lúc 17:00. */

$u_ad_cfg = array( 'role' => 'Admin' );
/* Cơ sở Văn phòng RIÊNG cho mục này. Không mượn `$CFG_CS` của mục 49 phía dưới: mục ấy chạy sau
   nên ở đây biến còn rỗng, mà khai hộ nó thì mục 49 khai lại là vỡ khoá UNIQUE. */
$CFG_CS = 'VP_CAUHINH';
vhcc_bo_phan( $CFG_CS, 'Văn phòng' );
/* Dọn cả hai kho cấu hình về trắng — mấy mục trước có thể đã ghi vào đó. `luu_cai_dat` là hàm
   riêng của lớp nên gọi qua đường chung, không chọc thẳng vào trong. */
$wpdb->query( "DELETE FROM " . VHCC_DB::t( 'cai_dat' )
	. " WHERE khoa IN ('VP_CONG_CFG','" . VHCC_Luong::VP_CFG_BP_O . "')" );

teq( 'chưa khai khối nào thì vp_cfg_khoi trả rỗng', array(), VHCC_Luong::vp_cfg_khoi( 'Văn phòng' ) );
$cfg_goc = VHCC_Luong::vp_cfg();
teq( 'bản chung mặc định: ca ngày đến 17:00', '17:00', $cfg_goc['ngayDen'] );

/* Anh Thắng sửa bản CHUNG thành khung cửa hàng 08:30–21:30. */
VHCC_Luong::dat_vp_cfg( $u_ad_cfg, array( 'ngayDen' => '21:30' ), '', '' );
teq( 'bản chung nay đến 21:30', '21:30', VHCC_Luong::vp_cfg()['ngayDen'] );

/* 🔴 VÀ ĐÓ CHÍNH LÀ CHỖ HỎNG: cơ sở Văn phòng cũng bị kéo theo. */
teq( '🔴 chưa khai riêng thì khối Văn phòng cũng dính khung 21:30 của cửa hàng',
	'21:30', VHCC_Luong::vp_cfg( $CFG_CS )['ngayDen'] );

/* Khai riêng cho khối Văn phòng. */
$r_k = VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng', array( 'ngayDen' => '17:00' ) );
t( 'khai riêng được cho khối', ! empty( $r_k['ok'] ), $r_k );
teq( '🔴 cơ sở Văn phòng nay dùng khung riêng 17:00', '17:00', VHCC_Luong::vp_cfg( $CFG_CS )['ngayDen'] );
teq( 'bản chung KHÔNG bị đụng', '21:30', VHCC_Luong::vp_cfg()['ngayDen'] );
/* ⚠️ Khối riêng chỉ đè ô ĐÃ KHAI, không thay cả bộ — chép cả bộ là ba bản sao của một thứ. */
teq( 'ô không khai riêng vẫn theo bản chung',
	VHCC_Luong::vp_cfg()['demTu'], VHCC_Luong::vp_cfg( $CFG_CS )['demTu'] );
teq( 'và khối chỉ giữ đúng ô đã khai', 1, count( VHCC_Luong::vp_cfg_khoi( 'Văn phòng' ) ) );

/* Cơ sở thuộc khối KHÁC không dính bản riêng của Văn phòng. */
teq( 'cơ sở cửa hàng vẫn theo bản chung', '21:30', VHCC_Luong::vp_cfg( 'TUTU_BT' )['ngayDen'] );

/* 🔴 PHẢI CÓ ĐƯỜNG BỎ KHAI. Không có thì lỡ tay khai một ô là ô ấy dính vĩnh viễn: sửa bản
   chung không ăn thua, mà màn bản chung vẫn hiện con số mới — người sửa tưởng mình sửa rồi. */
$r_k = VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng', array( 'ngayDen' => '' ) );
t( 'bỏ khai được', ! empty( $r_k['ok'] ), $r_k );
teq( 'và khối quay về bản chung hoàn toàn', array(), VHCC_Luong::vp_cfg_khoi( 'Văn phòng' ) );
teq( 'cơ sở Văn phòng lại theo 21:30', '21:30', VHCC_Luong::vp_cfg( $CFG_CS )['ngayDen'] );

/* Khai lại để dùng cho mấy phép dưới. */
VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng', array( 'ngayDen' => '17:00' ) );

/* ---- gác cửa + kiểm dữ liệu ---- */
$r_k = VHCC_Luong::dat_cfg_khoi( array( 'role' => 'Nhân viên' ), 'Văn phòng', array( 'ngayDen' => '10:00' ) );
t( 'Nhân viên KHÔNG đặt được công thức', empty( $r_k['ok'] ), $r_k );
$r_k = VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Khối bịa', array( 'ngayDen' => '10:00' ) );
t( 'khối lạ -> chối', empty( $r_k['ok'] ), $r_k );
$r_k = VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng', array( 'ngayDen' => '25 giờ' ) );
t( 'giờ sai dạng -> chối', empty( $r_k['ok'] ), $r_k );
teq( 'và KHÔNG ghi đè cái đang có', '17:00', VHCC_Luong::vp_cfg( $CFG_CS )['ngayDen'] );
$r_k = VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng', array( 'duoiMin' => 'kiểu lạ' ) );
t( 'cách tính thiếu giờ lạ -> chối', empty( $r_k['ok'] ), $r_k );
$r_k = VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng',
	array( 'ngayDen' => '17:00', 'ô_bịa_ra' => 'x' ) );
t( 'ô lạ thì bỏ qua, không nhét bừa vào kho', ! empty( $r_k['ok'] )
	&& ! array_key_exists( 'ô_bịa_ra', VHCC_Luong::vp_cfg_khoi( 'Văn phòng' ) ), VHCC_Luong::vp_cfg_khoi( 'Văn phòng' ) );

/* 🔴 CHỖ GHI LƯỢT CHẤM VÀ CHỖ TÍNH CÔNG PHẢI ĐỌC CÙNG MỘT BỘ SỐ.
   `VHCC_Online::dinh_tuyen()` dùng `ngayDen` để quyết một lượt chấm rơi vào hàng chính hay hàng
   ca đêm. Nó đọc bản CHUNG trong khi phép tính công đọc bản riêng thì hai chỗ lệch nhau — không
   báo lỗi gì, chỉ có mấy lượt chấm rơi nhầm hàng. */
teq( '🔴 chỗ định tuyến lượt chấm cũng đọc bản riêng của khối',
	'17:00', VHCC_Online::vp_cfg( $CFG_CS )['ngayDen'] );
teq( 'còn cơ sở khác vẫn là bản chung', '21:30', VHCC_Online::vp_cfg( 'TUTU_BT' )['ngayDen'] );

/* ---- màn hình ---- */
$h_ct = vhcc_web( '135791', array(), array( 'man' => 'cau_hinh', 'ccs' => $CFG_CS, 'cth' => '2026-07' ) );
t( 'màn có khối Công thức tính công', strpos( $h_ct, 'id="congthuc"' ) !== false, $h_ct );
t( 'khối ấy thu gọn sẵn', strpos( $h_ct, 'id="congthuc"><details>' ) !== false, $h_ct );
/* 🔴 Con số phải đọc TRƯỚC khi bấm Lưu — mốc bậc thang sai là lương cả khối tăng 50%. */
t( 'hiện sẵn ca chuẩn ra mấy công', strpos( $h_ct, 'Ca chuẩn của khối đang xem' ) !== false, $h_ct );
t( 'có thanh chọn khối', strpos( $h_ct, 'Bản chung' ) !== false
	&& strpos( $h_ct, '>Văn phòng' ) !== false, $h_ct );
t( 'và cho biết khối nào đang khai riêng mấy ô', strpos( $h_ct, 'ô riêng' ) !== false, $h_ct );
/* Ô sinh ra TỪ bảng mô tả, không gõ tay — thêm ô mới thì màn tự có. */
foreach ( array_keys( VHCC_Luong::VP_O ) as $k_o ) {
	t( 'màn có ô "' . $k_o . '"', strpos( $h_ct, 'name="ct[' . $k_o . ']"' ) !== false );
}

$h_ct_vp = vhcc_web( '135791', array(),
	array( 'man' => 'cau_hinh', 'ccs' => $CFG_CS, 'cth' => '2026-07', 'ctk' => 'Văn phòng' ) );
t( 'chọn khối thì màn nói rõ đang sửa riêng cho khối nào',
	strpos( $h_ct_vp, 'Đang sửa riêng cho khối' ) !== false, $h_ct_vp );
/* 🔴 Ô để trống ở bản riêng = theo bản chung, nên phải nói ra, và phải cho biết bản chung đang
   là bao nhiêu. Không nói thì người ta gõ đè cả bộ, và từ đó bản chung hết với tới khối này. */
t( 'nói rõ ô trống = theo bản chung', strpos( $h_ct_vp, 'theo bản chung' ) !== false, $h_ct_vp );
t( 'và hiện bản chung đang là bao nhiêu', strpos( $h_ct_vp, 'Bản chung: <b>' ) !== false, $h_ct_vp );
/* Ô tích phải là BA trạng thái ở bản riêng: có · không · chưa khai. */
t( 'ô có/không ở bản riêng có lựa chọn "theo bản chung"',
	preg_match( '/name="ct\[ktChuNhatNghi\]"[^>]*>\s*<option value="">/', $h_ct_vp ) === 1, $h_ct_vp );

/* ---- lưu qua đúng cửa POST của trang ---- */
$tok_ct = VHCC_Auth::login( '135791' )['token'];
$_COOKIE[ VHCC_Web::COOKIE ] = $tok_ct;
$_POST = array( 'viec' => 'cong_thuc', 'ky' => VHCC_Web::chu_ky( $tok_ct ),
	'ctk' => 'Văn phòng', 'ct' => array( 'ngayDen' => '16:30' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array();
teq( '🔴 lưu qua trang thật thì khối Văn phòng đổi theo',
	'16:30', VHCC_Luong::vp_cfg( $CFG_CS )['ngayDen'] );
teq( 'bản chung vẫn nguyên', '21:30', VHCC_Luong::vp_cfg()['ngayDen'] );

/* Nhân viên POST thẳng cũng không lọt. */
$_POST = array( 'viec' => 'cong_thuc' );
$r_ct = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'cong_thuc', array( 'name' => 'NV', 'role' => 'Nhân viên', 'coso' => 'TUTU_BT' ) ) );
t( 'Nhân viên POST thẳng việc cong_thuc bị chối',
	is_array( $r_ct ) && ! isset( $r_ct[0]['xong'] ), $r_ct );
$_POST = array();

/* Trả lại khung 17:00 cho mấy phép thử lưới phía dưới. */
VHCC_Luong::dat_cfg_khoi( $u_ad_cfg, 'Văn phòng', array( 'ngayDen' => '17:00' ) );
VHCC_Luong::dat_vp_cfg( $u_ad_cfg, array( 'ngayDen' => '17:00' ), '', '' );

// ====== 49. TAB "CÔNG VĂN PHÒNG" — lưới người × ngày, mỗi ô là SỐ CÔNG
/* Anh Thắng 26/08/2026: *"hiện bảng công theo hàng ngang giống này"* kèm ảnh tab Công Văn phòng
   của bản Apps Script. Đây là bản dịch `vpcVeLuoi`, để TAB RIÊNG đúng như bản gốc — hai màn trả
   lời hai câu khác nhau: Bảng chấm công hỏi "bấm máy lúc mấy giờ" (GIỜ, chỉ đọc), Công Văn phòng
   hỏi "được mấy công" (CÔNG, đã qua phép tính).

   🔴 Chốt nặng nhất: ô TRỐNG và ô SỐ 0 phải phân biệt được.
        dấu `·` = ngày KHÔNG có dữ liệu chấm công (nghỉ, hoặc chưa nạp)
        số `0`  = CÓ giờ chấm mà KHÔNG ra công (ca lạ, ca đêm thiếu giờ, kế toán chấm CN)
      Gộp hai thứ này là xoá mất đúng những ngày cần soi, mà bảng vẫn trông đầy đủ. */

$VP_CS = 'VP_LUOI';
vhcc_bo_phan( $VP_CS, 'Văn phòng' );
/* Ngày thường đủ khung 08:30–17:00 -> 1 công. */
vhcc_cham( $VP_CS, '2026-07-01', 'VPA', '', '08:30:00', '17:00:00' );
vhcc_cham( $VP_CS, '2026-07-02', 'VPA', '', '08:30:00', '17:00:00' );
/* Ngày CÓ giờ nhưng quá ngắn. ⚠️ Em tưởng chỗ này ra 0 công — SAI: cấu hình mặc định
   `duoiMin => 'tyle'` nên dưới mức tối thiểu vẫn ăn công theo TỶ LỆ (30 phút -> 0.06). Đây là
   con số lấy từ phép tính thật, không lấy từ trí nhớ. */
vhcc_cham( $VP_CS, '2026-07-03', 'VPA', '', '08:30:00', '09:00:00' );
/* Ngày thật sự KHÔNG ra công: hàng 2 nằm trong ca ngày (`caLa`) -> vp_ca_hang2 trả 'la', không
   tính tăng ca cũng không tính đêm. Đây mới là ô phải hiện SỐ 0 nền đỏ.
   ⚠️ Ghi thẳng hàng `-CD` chứ KHÔNG qua `vhcc_cham_dem`: hàm đó đi qua `trai_phang`, mà
      `trai_phang` cố ý trả null cho giờ ban ngày (không ghi bừa giờ ca ngày vào hàng 2). Dùng nó
      ở đây thì hàng ra NULL cả hai giờ, `caLa` không bật, và phép thử xanh nhờ một lý do khác
      hẳn thứ nó tưởng đang canh. */
vhcc_cham( $VP_CS, '2026-07-08', 'VPC', 'CD', '10:00:00', '11:00:00' );
/* Ca đêm: hàng -CD đêm 06/07, công dồn sang 07/07. */
vhcc_cham( $VP_CS, '2026-07-06', 'VPB', '', '08:30:00', '17:00:00' );
vhcc_cham_dem( $VP_CS, '2026-07-06', 'VPB', '21:30:00', '05:30:00' );
/* 2026-07-04 VPA nghỉ hẳn — không gieo gì, để canh dấu chấm. */

$g_vp = array( 'man' => 'vp', 'ccs' => $VP_CS, 'cth' => '2026-07' );
$h_vp = vhcc_web( '135791', array(), $g_vp );

/* 🔴 HAI TAB GỘP LÀM MỘT (anh Thắng 26/08: *"bản chấm công và bảng công tháng gộp lại, sửa 1
   lần"*). Nên KHÔNG còn tab "Bảng công tháng" nữa — và đường cũ `?man=vp` vẫn phải mở được, vì
   anh đã gửi link kèm tham số ấy cho các bộ phận rồi. */
t( 'KHÔNG còn tab riêng "Bảng công tháng"', strpos( $h_vp, '>Bảng công tháng<' ) === false, $h_vp );
t( 'chỉ còn một tab "Bảng công"', strpos( $h_vp, '>Bảng công<' ) !== false, $h_vp );
t( '🔴 đường cũ ?man=vp vẫn mở đúng màn đã gộp, không rơi về Trang chính',
	strpos( $h_vp, 'Lưới cả tháng' ) !== false, $h_vp );
t( 'và màn ấy có CẢ bảng chi tiết từng lượt', strpos( $h_vp, 'Chi tiết từng lượt' ) !== false, $h_vp );
t( 'tab Bảng công thấy được từ màn Công của tôi',
	strpos( vhcc_web( '135791', array(), array( 'man' => 'cong_toi' ) ), '>Bảng công<' ) !== false );
/* Màn phải TỰ NÓI ra là có lưới ngang, và nói ngay trong phần mở đầu chứ không giấu ở đâu đó.
   Anh Thắng 26/08 đứng ngay màn này và nói *"anh chưa thấy lưới"*. Nay lưới nằm CÙNG MÀN, nên
   câu chỉ đường trỏ xuống khối bên dưới chứ không sang tab khác — và tuyệt đối không được trỏ
   sang `man=vp` nữa, vì tab ấy không còn. */
t( 'màn Bảng công tự chỉ đường xuống lưới ngang',
	strpos( $h_qtc, 'dạng lưới ngang' ) !== false, $h_qtc );
t( 'và trỏ XUỐNG khối cùng màn, không sang tab khác',
	strpos( $h_qtc, 'href="#luoithang"' ) !== false, $h_qtc );
t( 'lưới thật sự có mặt ngay trên màn ấy, khỏi phải chọn cơ sở lần hai',
	strpos( $h_qtc, 'id="luoithang"' ) !== false, $h_qtc );
t( 'lưới vẽ ra được', strpos( $h_vp, 'Nhân viên' ) !== false, $h_vp );
/* Đủ 31 cột ngày cho tháng 7 + cột Nhân viên + cột TỔNG. Đếm số THẬT, không gõ tay con số. */
$sn_vp = (int) gmdate( 't', strtotime( '2026-07-01' ) );
teq( 'đủ một cột cho mỗi ngày trong tháng', $sn_vp, substr_count( $h_vp, '<th class="ng' ) );
t( 'có cột TỔNG', strpos( $h_vp, '<th>TỔNG</th>' ) !== false );
/* Thứ trong tuần phải đúng: 01/07/2026 là thứ Tư -> T4. Sai chỗ này thì cả lưới lệch cột. */
t( 'nhãn thứ đúng với ngày thật (01/07/2026 là T4)',
	strpos( $h_vp, '>1<div style="font-weight:400;opacity:.7">T4</div>' ) !== false, $h_vp );
t( 'cuối tuần được tô khác', strpos( $h_vp, 'class="ng cn"' ) !== false, $h_vp );

/* 🔴 Hai ký hiệu KHÁC NHAU cho hai chuyện khác nhau. */
t( 'ngày nghỉ hẳn -> dấu chấm', strpos( $h_vp, '<td class="o">·</td>' ) !== false, $h_vp );
t( 'ngày CÓ giờ mà không ra công -> số 0 nền đỏ, không phải dấu chấm',
	strpos( $h_vp, '<span class="chu-hong">0</span>' ) !== false, $h_vp );
t( 'và chú thích nói rõ vì sao không tính',
	strpos( $h_vp, 'hàng 2 nằm trong ca ngày' ) !== false, $h_vp );
/* Ngày dưới mức tối thiểu vẫn ăn công theo tỷ lệ -> ô có số lẻ, KHÔNG phải 0. Hai ca này rất
   dễ lẫn: cả hai đều "làm ít", nhưng một cái ra công một cái không. */
t( 'ngày dưới mức tối thiểu ăn công theo tỷ lệ (0.06), không bị làm tròn thành 0',
	strpos( $h_vp, '>0.06<' ) !== false, $h_vp );

/* Chú thích rê chuột phải nói được VÌ SAO ô ra con số đó. */
t( 'ô có chú thích kèm giờ vào → giờ ra',
	strpos( $h_vp, '08:30 → 17:00' ) !== false, $h_vp );
t( 'và nói rõ mấy giờ nằm trong khung', strpos( $h_vp, 'h trong khung' ) !== false, $h_vp );

/* Dòng con ca đêm: ngày LÀM hiện 🌙, ngày ĐƯỢC TÍNH hiện số. */
/* ⚠️ Canh đúng Ô TRONG LƯỚI, không canh chữ chung: cả '↳ ca đêm' lẫn '🌙' đều xuất hiện trong
   phần CHÚ GIẢI dưới lưới, nên tìm chuỗi trần thì bỏ hẳn dòng con đi phép thử vẫn xanh. Đã phá
   thử để thấy đúng chuyện đó. */
t( 'có dòng con ca đêm -CD (ô đầu dòng, không phải chữ trong chú giải)',
	strpos( $h_vp, 'padding-left:20px">↳ ca đêm' ) !== false, $h_vp );
t( 'đêm có làm thì ô hiện mặt trăng', strpos( $h_vp, '>🌙</td>' ) !== false, $h_vp );
t( 'và chú thích nói ca đêm cho công sang ngày nào',
	strpos( $h_vp, 'cho công vào ngày' ) !== false, $h_vp );

/* Phần chú giải: mỗi màu phải có một câu giải thích, kẻo người đọc chỉ thấy "ô này khác màu". */
foreach ( array( 'có tăng ca', 'có công đêm', 'kế toán chấm chủ nhật', 'có giờ nhưng KHÔNG ra công' ) as $k_vp ) {
	t( 'chú giải có giải thích màu "' . $k_vp . '"', strpos( $h_vp, $k_vp ) !== false, $h_vp );
}
t( 'nói rõ dấu chấm nghĩa là không có dữ liệu',
	strpos( $h_vp, 'không có dữ liệu chấm công' ) !== false );

/* 🔴 Ô đối chiếu: lưới tự cộng lại và so với tổng của phép tính. Lệch thì phải KÊU, không im
   lặng in ra hai con số khác nhau ở hai chỗ. */
/* 🔴 Ô ĐỐI CHIẾU. Lưới tự cộng lại rồi so với tổng của phép tính; lệch thì phải KÊU, không
   im lặng in ra hai con số khác nhau ở hai chỗ trên cùng một trang.
   Chuyện này KHÔNG xảy ra khi engine đúng, nên phải gọi thẳng hàm dựng lưới với dữ liệu cố tình
   lệch — chờ nó tự xảy ra thì phép thử chẳng bao giờ chạy tới nhánh đó. */
function vp_o_ngay( $ma, $ngay, $tong ) {
	return array( 'ma' => $ma, 'ten' => 'Người ' . $ma, 'ngay' => $ngay,
		'congNgay' => $tong, 'congTangCa' => 0.0, 'congDem' => 0.0, 'congBu' => 0.0, 'tong' => $tong,
		'phutNgay' => 480, 'gioNgay' => 8.0, 'khung' => '08:30-17:00',
		'kt7' => false, 'ktCnNghi' => false, 'caLa' => false, 'demTuNgay' => '', 'demSangNgay' => '',
		'demThieuGio' => false, 'demChuaDuCap' => false, 'gioDemThuc' => 0.0,
		'vao' => '08:30', 'ra' => '17:00', 'h2vao' => '', 'h2ra' => '' );
}
$b_lech = array( 'month' => '2026-07',
	/* Bảng nói 5 công, mà lưới chỉ có 2 ngày × 1 công = 2. */
	'rows' => array( array( 'ma' => 'ZZ', 'ten' => 'Người ZZ', 'tong' => 5.0, 'laKeToan' => false ) ),
	'detail' => array( vp_o_ngay( 'ZZ', '2026-07-01', 1.0 ), vp_o_ngay( 'ZZ', '2026-07-02', 1.0 ) ) );
ob_start(); vhcc_goi_rieng( 'VHCC_Web', 've_luoi_vp', array( $b_lech ) ); $h_lech = ob_get_clean();
t( 'tổng lưới lệch tổng phép tính -> KÊU LÊN, không im lặng',
	strpos( $h_lech, 'KHÁC tổng của phép' ) !== false, $h_lech );
t( 'và in cả hai con số cạnh nhau để so',
	strpos( $h_lech, '≠ 5' ) !== false, $h_lech );
t( 'còn khi khớp thì nói khớp', strpos( $h_vp, 'Tổng từng người khớp' ) !== false );

/* Tháng không có dữ liệu -> nói rõ, và chỉ đường sang chỗ nạp. */
$h_vp0 = vhcc_web( '135791', array(), array( 'man' => 'vp', 'ccs' => $VP_CS, 'cth' => '2020-01' ) );
t( 'tháng trống thì nói rõ chưa có dữ liệu',
	strpos( $h_vp0, 'chưa có dữ liệu chấm công nào' ) !== false, $h_vp0 );
t( 'và chỉ sang chỗ nạp .csv', strpos( $h_vp0, 'Nạp công từ .csv' ) !== false, $h_vp0 );

/* 🔴 CHÍNH LƯỚI là thứ CHỈ ĐỌC — không có ô nhập giờ nào.
   ⚠️ Canh vào ĐÚNG khối lưới, không quét cả trang: màn đã gộp còn có khối Chấm công bù và khối
   Sửa giờ công, hai khối ấy CÓ ô nhập giờ và có quyền có. Quét cả trang là chốt đỏ oan, mà sửa
   cho xanh bằng cách bỏ chốt thì mất luôn thứ nó đang canh. */
$khoi_luoi = preg_match( '/Lưới cả tháng(.*?)(?=<div class="the" id="bucong")/s', $h_vp, $m_lu )
	? $m_lu[1] : '';
t( 'tìm được khối lưới trong màn đã gộp', '' !== $khoi_luoi, substr( $h_vp, 0, 200 ) );
t( 'lưới KHÔNG có ô nhập giờ nào',
	! preg_match( '/name="(gio_vao|gio_ra|bu_vao|bu_ra|sg_vao|sg_ra)"/', $khoi_luoi ), $khoi_luoi );
/* Và không lộ tiền: khối này nói về CÔNG, tiền là chuyện của màn lương (quyền khác). */
t( 'lưới không in ô tiền nào', strpos( $khoi_luoi, 'đ</td>' ) === false, $khoi_luoi );

/* ---- ĐƠN VỊ CỦA Ô DO BỘ PHẬN QUYẾT, không phải một công thức cho tất cả ---- */
/* Anh Thắng 26/08: *"này là cơ sở mà, nên kiểu chấm khác, tính theo giờ"* — anh mở lưới ở một
   CỬA HÀNG và thấy toàn 0.63 · 0.31 · 0.84, tức công thức Văn phòng (bậc thang theo khung
   08:30–17:00) đem áp lên nơi người ta làm ca gãy. Số vẫn ra, vẫn cộng được, chỉ là vô nghĩa. */
t( 'cơ sở Văn phòng thì ô là SỐ CÔNG', strpos( $h_vp, 'là <b>số công</b>' ) !== false, $h_vp );

$ADMIN_W = array( 'name' => 'Admin Soát Công', 'role' => 'Admin', 'coso' => '' );
$CS_GIO = 'FZ_SC_THU';                                  /* KHÔNG khai bộ phận -> không phải VP */
vhcc_cham( $CS_GIO, '2026-07-01', 'GIO1', '', '08:00:00', '17:30:00' );   /* 9.5h */
vhcc_cham( $CS_GIO, '2026-07-02', 'GIO1', '', '08:00:00', null );         /* thiếu giờ ra */
vhcc_cham( $CS_GIO, '2026-07-01', 'GIO1', 'CD', '21:00:00', '23:00:00' ); /* hàng riêng */
/* 🔴 Hàng ca đêm THẬT: ra 05:30 HÔM SAU, nên `gio_ra_giay` > 86400. Cần đúng ca này để chốt
   rằng phép tách ăn GIÂY THÔ — `vao`/`ra` dạng chữ đã bị `hhmmss` gói về trong một ngày, đọc
   từ đó thì 05:30 hôm sau trông y hệt 05:30 hôm nay và cả ca đêm biến mất. */
vhcc_cham_dem( $CS_GIO, '2026-07-05', 'GIO2', '21:30:00', '05:30:00' );
/* Một lượt nằm GỌN trong một ca — để canh ô chỉ hiện MỘT mã. Mọi lượt khác ở đây đều vắt qua
   hai ca, nên thiếu ca này thì phép thử "một ca thì một mã" không có ô nào để soi. */
vhcc_cham( $CS_GIO, '2026-07-06', 'GIO2', '', '07:00:00', '13:00:00' );
$h_gio = vhcc_web( '135791', array(), array( 'man' => 'vp', 'ccs' => $CS_GIO, 'cth' => '2026-07' ) );

t( 'cơ sở KHÔNG phải Văn phòng thì ô là SỐ GIỜ, không phải số công',
	strpos( $h_gio, 'là <b>số giờ làm</b>' ) !== false, $h_gio );
t( 'và nói rõ vì sao không dùng công thức Văn phòng ở đây',
	strpos( $h_gio, 'ca gãy' ) !== false, $h_gio );
t( 'và chỉ thẳng chỗ đổi cách tính',
	strpos( $h_gio, 'Cách tính công của từng cơ sở' ) !== false, $h_gio );
/* Nói rõ vì sao cơ sở này đang tính kiểu đó — "đã khai thẳng" hay "suy theo bộ phận". Không nói
   thì người ta đổi bộ phận cho gọn báo cáo rồi ngạc nhiên vì bảng công đổi theo. */
t( 'nói rõ vì sao đang tính kiểu đó',
	strpos( $h_gio, 'suy theo bộ phận' ) !== false || strpos( $h_gio, 'đã khai thẳng' ) !== false, $h_gio );
/* 🔴 Số giờ phải là số giờ THẬT, không phải số công lẻ. 08:00 -> 17:30 = 9.5 giờ. */
t( 'ô hiện đúng số giờ làm (9.5)', strpos( $h_gio, '>9.5</b>' ) !== false, $h_gio );
t( 'KHÔNG có ô nào mang số công lẻ kiểu 0.63', strpos( $h_gio, '>0.63<' ) === false, $h_gio );
/* Ba trạng thái, ba ký hiệu — gộp lại là xoá mất đúng ngày cần soi.
   ⚠️ Bóc lớp <a class="o-sua"> ra trước khi soi ô: từ 26/08/2026 mỗi ô là một đường bấm được
   (anh Thắng: *"sửa là sửa trực tiếp trong này luôn nhé"*). Nới chốt thành "có dấu chấm ở đâu
   đó trong trang" thì nó thôi canh KÝ HIỆU CỦA Ô — mà đó mới là thứ đang canh. */
$_boc = function ( $h ) {
	return preg_replace( '#<a class="o-sua"[^>]*>(.*?)</a>#s', '$1', $h );
};
$h_gio_tr = $_boc( $h_gio );
t( 'ngày không đi làm -> dấu chấm', strpos( $h_gio_tr, '<td class="o">·</td>' ) !== false, $h_gio_tr );
t( 'ngày thiếu giờ ra -> dấu hỏi nền đỏ, không phải số 0',
	strpos( $h_gio_tr, '>?</td>' ) !== false, $h_gio_tr );

/* ---- 🔴 BẤM THẲNG VÀO Ô TRONG LƯỚI ĐỂ SỬA ----
   Bắt người ta đọc ô ở dòng 14 cột 22 rồi cuộn xuống gõ lại ngày và mã vào biểu mẫu là bắt
   chép tay một thứ máy đã biết — chép sai một chữ số thì sửa nhầm ngày của người khác, mà màn
   hình vẫn báo "Đã sửa". */
t( 'ô trong lưới là đường bấm được', strpos( $h_gio, 'class="o-sua"' ) !== false, $h_gio );
/* Ô CÓ GIỜ -> khối Sửa giờ (đè lên giờ đã có). Ô TRỐNG -> khối Chấm công bù (điền ô trống).
   Trỏ nhầm đường là bấm vào ngày trống rồi nhận câu "chưa có dòng nào để sửa". */
t( 'ô CÓ GIỜ trỏ sang khối Sửa giờ, mang sẵn ngày + mã',
	preg_match( '/class="o-sua" href="[^"]*sgn=\d{4}-\d{2}-\d{2}[^"]*sgm=[^"]*#suaday"/', $h_gio ) === 1,
	$h_gio );
t( 'ô TRỐNG trỏ sang khối Chấm công bù',
	preg_match( '/class="o-sua" href="[^"]*gnd=\d{4}-\d{2}-\d{2}[^"]*gma=[^"]*#bucong"/', $h_gio ) === 1,
	$h_gio );
t( 'và màn nói cho biết bấm vào ô thì được gì',
	strpos( $h_gio, 'Bấm thẳng vào' ) !== false, $h_gio );
/* ---- 🔴 SỬA NGAY TRONG LƯỚI, KHÔNG NHẢY ĐI ĐÂU ----
   Anh Thắng 26/08: *"mình có sửa trong khu này luôn được không, hay phải bắt buộc nhảy vào ô
   sửa"*. Bấm một ô là biểu mẫu mở ra NGAY DƯỚI dòng của đúng người đó, trong chính cái lưới. */
$h_iv = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07',
	'sgn' => '2026-07-06', 'sgm' => 'QTC1' ) );
t( 'bấm ô có giờ -> mở hàng sửa ngay trong lưới',
	strpos( $h_iv, 'class="hang-sua"' ) !== false, $h_iv );
t( 'hàng sửa nằm TRONG bảng lưới (không phải một khối rời phía dưới)',
	preg_match( '#<table class="cc".*?<tr class="hang-sua"#s', $h_iv ) === 1, $h_iv );
t( 'và mang sẵn ngày + mã, khỏi gõ lại',
	strpos( $h_iv, 'name="ngay" value="2026-07-06"' ) !== false
	&& strpos( $h_iv, 'name="ma_nv" value="QTC1"' ) !== false, $h_iv );
$khoi_iv = preg_match( '#<tr class="hang-sua"[^>]*>(.*?)</tr>#s', $h_iv, $m_iv ) ? $m_iv[1] : '';
t( 'tìm được hàng sửa trong lưới', '' !== $khoi_iv, substr( $h_iv, 0, 200 ) );
t( 'gửi đúng việc sua_gio', strpos( $khoi_iv, 'name="viec" value="sua_gio"' ) !== false, $khoi_iv );
t( 'có ô nhập giờ ngay tại đó', strpos( $khoi_iv, 'name="sg_vao"' ) !== false, $khoi_iv );
t( 'và đòi lý do', strpos( $khoi_iv, 'name="ly_do" required' ) !== false, $khoi_iv );
/* 🔴 Nhắc lại ĐANG SỬA AI, GIỜ ĐANG CÓ BAO NHIÊU — lưới 31 cột thì mắt vẫn lạc, mà sửa nhầm
   người là sửa nhầm lương. */
t( 'nhắc lại giờ đang có', strpos( $h_iv, 'đang có: vào' ) !== false, $h_iv );
/* Ô đang mở phải tìm lại được giữa 600 ô. */
t( 'ô đang sửa được tô viền', strpos( $h_iv, 'dang-sua' ) !== false, $h_iv );
/* 🔴 CHỈ MỘT HÀNG. Vẽ sẵn biểu mẫu cho cả 600 ô là 600 biểu mẫu trong một trang. */
teq( 'chỉ mở ĐÚNG MỘT hàng sửa', 1, substr_count( $h_iv, 'class="hang-sua"' ) );
t( 'và có nút Đóng để trả lưới về như cũ', strpos( $h_iv, '>Đóng</a>' ) !== false, $h_iv );

/* Ô TRỐNG -> biểu mẫu BÙ, không phải biểu mẫu sửa. Hai việc khác nhau. */
$h_ib = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07',
	'gnd' => '2026-07-20', 'gma' => 'QTC1' ) );
t( 'bấm ô trống -> mở hàng BÙ ngay trong lưới',
	strpos( $h_ib, 'class="hang-sua"' ) !== false, $h_ib );
/* ⚠️ Soi ĐÚNG hàng bù, không quét cả trang: khối "Chấm công bù" và khối "Sửa giờ công" ở dưới
   màn cũng có `viec=bu` / ô tích xoá trắng, và có quyền có. Quét cả trang thì bỏ hẳn nhánh phân
   biệt ô-trống / ô-có-giờ đi mà chốt vẫn xanh — đã phá thử để thấy đúng chuyện đó. */
$khoi_ib = preg_match( '#<tr class="hang-sua"[^>]*>(.*?)</tr>#s', $h_ib, $m_ib ) ? $m_ib[1] : '';
t( 'tìm được hàng bù trong lưới', '' !== $khoi_ib, substr( $h_ib, 0, 200 ) );
t( 'và gửi đúng việc bu, không phải sua_gio',
	strpos( $khoi_ib, 'name="viec" value="bu"' ) !== false, $khoi_ib );
t( 'ô bù KHÔNG có tích xoá trắng (chưa có gì để xoá)',
	strpos( $khoi_ib, 'name="sg_xoa_vao"' ) === false, $khoi_ib );

/* 🔴 NGÀY KHÁC THÁNG THÌ KHÔNG MỞ. Bấm một ô ở tháng 7, đổi sang tháng 8, mà hàng sửa vẫn mở
   giữa lưới tháng 8 với một ngày tháng 7 — rồi người ta bấm Lưu. */
/* ⚠️ Phải có dữ liệu tháng 8 cho ĐÚNG người ấy, không thì lưới tháng 8 chẳng vẽ dòng nào của
   QTC1 và chốt xanh vì một lý do khác hẳn (không có dòng thì cũng không có hàng sửa). */
vhcc_cham( 'TUTU_BT', '2026-08-03', 'QTC1', '', '08:00:00', '17:00:00' );
$h_ix = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-08',
	'sgn' => '2026-07-06', 'sgm' => 'QTC1' ) );
t( 'lưới tháng 8 có vẽ dòng của QTC1', strpos( $h_ix, 'Người QTC1' ) !== false, $h_ix );
t( '🔴 ngày lạc tháng thì KHÔNG mở hàng sửa trong lưới',
	strpos( $h_ix, 'class="hang-sua"' ) === false, $h_ix );

/* Cửa hàng trưởng bấm ô CÓ GIỜ: hàng vẫn mở, nhưng nói rõ cần quyền Admin — chứ không im lặng
   bày ra một biểu mẫu bấm Lưu là bị chối. */
VHCC_NguoiDung::luu( '', 'CHT Lưới', '357913', 'Cửa hàng trưởng', 'TUTU_BT' );
$h_ic = vhcc_web( '357913', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07',
	'sgn' => '2026-07-06', 'sgm' => 'QTC1' ) );
t( 'Cửa hàng trưởng bấm ô có giờ -> nói rõ cần quyền Admin',
	strpos( $h_ic, 'cần quyền Admin' ) !== false, $h_ic );
t( 'và KHÔNG bày ra ô nhập giờ để bấm Lưu rồi bị chối',
	strpos( $h_ic, 'name="sg_vao"' ) === false, $h_ic );

/* Hàng -CD là hàng RIÊNG: đường bấm phải mang mã KÈM hậu tố, không thì sửa nhầm sang hàng chính. */
t( '🔴 ô của hàng -CD mang mã KÈM hậu tố',
	preg_match( '/sgm=[A-Za-z0-9_]+-CD/', $h_gio ) === 1
	|| preg_match( '/gma=[A-Za-z0-9_]+-CD/', $h_gio ) === 1, $h_gio );
/* Đường bấm là LIÊN KẾT, không phải script — cả màn này không có lấy một dòng script. */
t( 'ô bấm được KHÔNG dùng JavaScript',
	stripos( $h_gio, '<script' ) === false && ! preg_match( '/\son[a-z]+\s*=\s*"/i', $h_gio ), $h_gio );
t( 'và chú thích nói rõ là quên bấm lúc về',
	strpos( $h_gio, 'quên bấm lúc về' ) !== false, $h_gio );
/* Hàng -CD là hàng RIÊNG, và tổng của dòng chính phải gồm cả nó. */
t( 'hàng -CD hiện thành dòng riêng', strpos( $h_gio, '↳ <code>-CD</code>' ) !== false, $h_gio );
t( 'và nói rõ tổng dòng chính đã gồm hàng dưới',
	strpos( $h_gio, 'gồm cả hàng dưới' ) !== false, $h_gio );
/* 9.5h (hàng chính) + 2h (hàng -CD) = 11h 30m. Lấy CÙNG phép tính với màn Bảng chấm công. */
t( 'tổng của người cộng cả hai hàng (9.5h + 2h = 11h 30m)',
	strpos( $h_gio, '11h 30m' ) !== false, $h_gio );
/* Nói thẳng chuyện giờ làm khác giờ được trả tiền, đừng để lộ ra lúc đối lương. */
t( 'nói rõ đây là giờ LÀM, không phải giờ được trả tiền',
	strpos( $h_gio, 'không phải giờ được trả tiền' ) !== false, $h_gio );

/* ---- TÁCH CA: làm ca nào · ca đó mấy tiếng · từ ca nào đến ca nào ---- */
/* Anh Thắng 26/08: *"bổ sung phần tách ca, để biết bạn đó làm ca nào, ca đó mấy tiếng, từ ca nào
   đến ca nào"*. Lõi tách ca thử riêng ở kiem-ca.php (47 phép); ở đây canh phần MÀN HÌNH. */
t( 'lưới giờ có bảng Tổng giờ theo ca', strpos( $h_gio, 'Tổng giờ theo ca' ) !== false, $h_gio );
t( 'nói rõ đang dùng khung ca nào', strpos( $h_gio, 'Khung ca đang dùng' ) !== false, $h_gio );
t( 'và chưa ai khai thì nói là đang dùng MẶC ĐỊNH',
	strpos( $h_gio, '<b>mặc định</b>' ) !== false, $h_gio );
foreach ( array( 'C1' => 'Ca 1', 'C2' => 'Ca 2', 'C3' => 'Ca 3' ) as $ma_tc => $tc ) {
	t( 'bảng theo ca có cột "' . $tc . '" kèm mã ngắn ' . $ma_tc,
		strpos( $h_gio, '<b>' . $ma_tc . '</b> ' . $tc ) !== false, $h_gio );
}
t( 'và có cột Ngoài ca', strpos( $h_gio, '<th>Ngoài ca</th>' ) !== false, $h_gio );
/* GIO1 ngày 01/07 có HAI hàng: hàng chính 08:00–17:30 và hàng -CD 21:00–23:00.
     Ca 1 (06–14) = 6h        (08:00→14:00 của hàng chính)
     Ca 2 (14–22) = 4h 30m    (14:00→17:30 hàng chính  +  21:00→22:00 hàng -CD)
     Ca 3 (22–06) = 1h        (22:00→23:00 hàng -CD)
   ⚠️ Em gõ 3h 30m cho Ca 2 vì chỉ nhẩm hàng chính — quên rằng hàng -CD cũng chạm Ca 2. Con số
      dưới đây lấy từ phép tính thật, và tổng 11h 30m khớp với cột TỔNG của lưới ở trên. */
t( 'tách đúng giờ vào Ca 1', strpos( $h_gio, '>6h</b>' ) !== false, $h_gio );
t( 'Ca 2 gom cả hàng chính lẫn phần hàng -CD chạm vào nó',
	strpos( $h_gio, '>4h 30m</b>' ) !== false, $h_gio );
t( 'tổng theo ca khớp tổng giờ làm của lưới (11h 30m)',
	substr_count( $h_gio, '11h 30m' ) >= 2, $h_gio );
/* 🔴 MÃ CA IN THẲNG VÀO Ô. Anh Thắng 26/08: *"hiện sẵn bạn ca nào ca nào luôn nhé, đi rà này
   rất khó"* — một tháng của 21 người là hơn 600 ô, rê chuột từng ô thì không ai rà nổi. */
t( 'ô lưới in sẵn mã ca, không bắt rê chuột mới biết',
	strpos( $h_gio, '<div class="mca">' ) !== false, $h_gio );
t( 'ngày vắt hai ca thì ô hiện cả hai mã',
	strpos( $h_gio, '>C1·C2</div>' ) !== false, $h_gio );
t( 'ngày nằm gọn một ca thì ô chỉ hiện MỘT mã',
	strpos( $h_gio, '>C1</div>' ) !== false, $h_gio );
/* Nền tô theo ca ĂN NHIỀU GIỜ NHẤT — để lướt mắt là thấy cả tháng ai chạy ca nào. */
t( 'ô được tô màu theo ca chính', preg_match( '/class="oc ca[1-4]"/', $h_gio ) === 1, $h_gio );
/* 🔴 Tô theo ca ĂN NHIỀU GIỜ NHẤT, không phải ca ĐẦU TIÊN chạm vào. Lượt 21:30 → 05:30 chạm
   Ca 2 đúng 30 phút rồi nằm trong Ca 3 suốt 7h 30m — tô theo ca đầu là cả tháng ca đêm hiện màu
   ca chiều, và người rà bảng đọc ngược hoàn toàn. */
t( 'ca đêm được tô theo Ca 3 (7h 30m), không theo Ca 2 (30 phút chạm đầu)',
	preg_match( '/class="oc ca3" title="[^"]*Ca 3 7h 30m/', $h_gio ) === 1, $h_gio );
/* Mã trong ô là C1/C2/C3 theo VỊ TRÍ, nên bắt buộc phải có bảng quy đổi ngay dưới lưới. */
t( 'có bảng quy đổi mã ca ngay dưới lưới',
	strpos( $h_gio, 'Mã ca trong ô' ) !== false, $h_gio );
t( 'bảng quy đổi nói C1 là ca nào, khung giờ nào',
	strpos( $h_gio, '<b>C1</b> Ca 1 06:00–14:00' ) !== false, $h_gio );
t( 'và giải thích ô hai mã nghĩa là gì',
	strpos( $h_gio, 'vắt qua hai ca' ) !== false, $h_gio );

/* Chú thích ô nói luôn ngày đó chạy từ ca nào đến ca nào. */
t( 'chú thích ô nói từ ca nào đến ca nào', strpos( $h_gio, 'Ca 1 → Ca 2' ) !== false, $h_gio );
t( 'và nói mỗi ca mấy tiếng kèm khung giờ',
	strpos( $h_gio, 'Ca 1 6h (06:00–14:00)' ) !== false, $h_gio );

/* 🔴 Hàng ca đêm: 21:00–23:00 phải rơi vào Ca 3 (22:00–06:00, qua nửa đêm), không được biến
   mất. Ca qua nửa đêm là chỗ mà phép so ngây thơ `tu <= x && x < den` luôn trượt. */
t( 'hàng ca đêm vẫn được tách vào Ca 3', strpos( $h_gio, '>1h</b>' ) !== false, $h_gio );
/* 🔴 GIO2 làm 21:30 → 05:30 HÔM SAU = 8 tiếng, trong đó Ca 3 (22:00–06:00) ăn trọn 7h 30m và
   Ca 2 (14:00–22:00) ăn 30 phút đầu. Đọc từ chuỗi giờ đã gói về trong ngày thì ra ~0 và cả ca
   đêm này biến mất mà tổng vẫn có số — đúng kiểu hỏng không kêu tiếng nào. */
t( 'ca đêm qua hôm sau tách đúng 7h 30m vào Ca 3',
	strpos( $h_gio, '>7h 30m</b>' ) !== false, $h_gio );
t( 'và 30 phút đầu rơi vào Ca 2', strpos( $h_gio, '>0h 30m</b>' ) !== false, $h_gio );

/* Khối khai ca — Cửa hàng trưởng trở lên.
   ⚠️ Khối này nay ở TAB CẤU HÌNH, không còn trên màn bảng công — nên soi màn cấu hình. */
$h_ch_gio = vhcc_web( '135791', array(),
	array( 'man' => 'cau_hinh', 'ccs' => $CS_GIO, 'cth' => '2026-07' ) );
/* Khối khai ca — Cửa hàng trưởng trở lên. */
t( 'có khối Khai ca làm việc', strpos( $h_ch_gio, 'id="khaica"' ) !== false, $h_ch_gio );
t( 'khối khai ca thu gọn sẵn (màn đã dài rồi)',
	preg_match( '/id="khaica"><details>/', $h_ch_gio ) === 1, $h_ch_gio );
t( 'có ô nhập tên ca', strpos( $h_ch_gio, 'name="ca_ten[0]"' ) !== false, $h_ch_gio );
t( 'và ô giờ cuối tuần riêng', strpos( $h_ch_gio, 'name="ca_tuw[0]"' ) !== false, $h_ch_gio );
/* Luôn thừa hai dòng trống để thêm ca mà KHÔNG cần JavaScript — cả màn này không có script. */
t( 'thừa sẵn dòng trống để thêm ca', strpos( $h_ch_gio, 'name="ca_ten[4]"' ) !== false, $h_ch_gio );
t( 'khối khai ca KHÔNG dùng JavaScript', stripos( $h_ch_gio, '<script' ) === false, $h_ch_gio );

/* Lưu ca THẬT rồi xem bảng đổi theo. */
$_POST = array( 'viec' => 'ca', 'ccs' => $CS_GIO,
	'ca_ten'  => array( 'Sáng', 'Chiều' ),
	'ca_tu'   => array( '06:00', '12:00' ),
	'ca_den'  => array( '12:00', '20:00' ),
	'ca_tuw'  => array( '', '' ), 'ca_denw' => array( '', '' ) );
$r_ca = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec', array( 'ca', $ADMIN_W ) );
$_POST = array();
t( 'lưu được ca mới', is_array( $r_ca ) && isset( $r_ca[0]['xong'] ), $r_ca );
$h_ca2 = vhcc_web( '135791', array(), array( 'man' => 'vp', 'ccs' => $CS_GIO, 'cth' => '2026-07' ) );
t( 'bảng đổi theo ca vừa khai', strpos( $h_ca2, '<b>C1</b> Sáng' ) !== false, $h_ca2 );
t( 'và nói là cơ sở này đã có khung ca RIÊNG',
	strpos( $h_ca2, 'khai riêng cho' ) !== false, $h_ca2 );
t( 'ca cũ không còn trong bảng', strpos( $h_ca2, '<b>C1</b> Ca 1' ) === false, $h_ca2 );
/* 08:00–17:30 với ca Sáng 06–12 và Chiều 12–20 -> 4h + 5h 30m. */
t( 'tách lại đúng theo khung ca mới', strpos( $h_ca2, '>4h</b>' ) !== false
	&& strpos( $h_ca2, '>5h 30m</b>' ) !== false, $h_ca2 );
/* 🔴 Hàng ca đêm 21:00–23:00 nay nằm NGOÀI mọi ca -> phải hiện ở cột Ngoài ca, không được nuốt. */
t( 'giờ không thuộc ca nào hiện ở cột Ngoài ca, không bị nuốt',
	strpos( $h_ca2, 'oc vang' ) !== false, $h_ca2 );
t( 'và nói rõ đó là dấu hiệu khung ca khai chưa khớp',
	strpos( $h_ca2, 'khung ca khai chưa khớp' ) !== false, $h_ca2 );

/* ---- CÔNG TẮC: cơ sở nào tính THEO GIỜ, cơ sở nào THEO CÔNG ---- */
/* Anh Thắng 26/08: *"bổ sung phần cấu hình để phân biệt cơ sở nào tính theo giờ, cơ sở nào tính
   theo công"*.

   🔴 Trước đây chuyện này SUY RA TỪ BỘ PHẬN, và đó là chỗ sai: nó buộc hai câu hỏi khác nhau vào
      một ô dữ liệu — "cơ sở thuộc bộ phận nào" (để lọc, gom báo cáo) và "cơ sở trả công thế nào"
      (để ra tiền). Đổi bộ phận cho gọn báo cáo là lặng lẽ đổi cách tính tiền của cả một cơ sở. */

/* Chưa khai gì -> vẫn theo luật cũ, để cơ sở đang chạy không đổi kết quả sau khi cài bản mới. */
t( 'chưa khai thì suy theo bộ phận', ! VHCC_Luong::cach_tinh_da_khai( $CS_GIO ) );
teq( 'cơ sở không phải Văn phòng -> theo giờ', 'gio', VHCC_Luong::cach_tinh( $CS_GIO ) );
teq( 'cơ sở Văn phòng -> theo công', 'cong', VHCC_Luong::cach_tinh( $VP_CS ) );

/* Khai thẳng thì công tắc thắng bộ phận — cả hai chiều. */
$r_ct = VHCC_Luong::dat_cach_tinh( $ADMIN_W, array( $CS_GIO => 'cong', $VP_CS => 'gio' ) );
t( 'lưu được cách tính', ! empty( $r_ct['ok'] ), $r_ct );
teq( 'khai thẳng THEO CÔNG thắng bộ phận', 'cong', VHCC_Luong::cach_tinh( $CS_GIO ) );
teq( 'và khai thẳng THEO GIỜ cũng thắng bộ phận Văn phòng', 'gio', VHCC_Luong::cach_tinh( $VP_CS ) );
t( 'đánh dấu là đã khai thẳng', VHCC_Luong::cach_tinh_da_khai( $CS_GIO ) );
/* Màn đọc theo công tắc, không đọc bộ phận nữa. */
$h_ct = vhcc_web( '135791', array(), array( 'man' => 'cau_hinh', 'ccs' => $CS_GIO, 'cth' => '2026-07' ) );
/* ⚠️ Câu "mỗi ô là số công" nằm ở LƯỚI CẢ THÁNG, tức màn Bảng công — không phải màn Cấu
   hình. Soi nhầm màn thì phép thử đỏ vì lý do chẳng liên quan gì tới cái công tắc. */
$h_ct_bc = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => $CS_GIO, 'cth' => '2026-07' ) );
t( 'bảng đổi sang SỐ CÔNG theo công tắc', strpos( $h_ct_bc, 'là <b>số công</b>' ) !== false, $h_ct_bc );
t( 'và nói rõ là do khai thẳng', strpos( $h_ct_bc, 'đã khai thẳng' ) !== false, $h_ct_bc );

/* Để rỗng = BỎ khai, quay về suy theo bộ phận — KHÔNG phải "cơ sở này không có cách tính nào". */
VHCC_Luong::dat_cach_tinh( $ADMIN_W, array( $CS_GIO => '', $VP_CS => '' ) );
t( 'để rỗng là bỏ khai', ! VHCC_Luong::cach_tinh_da_khai( $CS_GIO ) );
/* Và XOÁ HẲN khoá, không để lại một ô rỗng. Giữ lại thì sổ cấu hình cứ phình ra theo mỗi lượt
   bấm Lưu, đầy những cơ sở "đã khai là không khai gì" — đọc lại chẳng ai hiểu. */
$bd_ct = VHCC_Luong::ban_do_cach_tinh();
t( 'và xoá hẳn khoá khỏi sổ cấu hình, không để lại ô rỗng',
	! array_key_exists( $CS_GIO, $bd_ct ) && ! array_key_exists( $VP_CS, $bd_ct ), $bd_ct );
teq( 'và quay về suy theo bộ phận', 'gio', VHCC_Luong::cach_tinh( $CS_GIO ) );
teq( 'Văn phòng cũng quay về theo công', 'cong', VHCC_Luong::cach_tinh( $VP_CS ) );

/* Khối cấu hình trên màn. */
t( 'có khối Cách tính công của từng cơ sở', strpos( $h_ch_gio, 'id="cachtinh"' ) !== false, $h_ch_gio );
t( 'khối ấy liệt kê CẢ danh sách cơ sở, không chỉ cơ sở đang xem',
	substr_count( $h_ch_gio, 'name="ct[' ) > 1, $h_ch_gio );
t( 'mỗi cơ sở có ba lựa chọn', strpos( $h_ch_gio, '— theo bộ phận —' ) !== false
	&& strpos( $h_ch_gio, '>Theo giờ<' ) !== false && strpos( $h_ch_gio, '>Theo công<' ) !== false, $h_ch_gio );
t( 'có cột Đang dùng cho biết luật hiện ra kết quả gì',
	strpos( $h_ch_gio, '<th>Đang dùng</th>' ) !== false, $h_ch_gio );
/* Nói rõ đổi công tắc KHÔNG sửa giờ chấm — kẻo người ta sợ không dám bấm. */
t( 'nói rõ đổi cách tính không sửa giờ chấm nào',
	strpos( $h_ch_gio, 'không sửa một giờ chấm nào' ) !== false, $h_ch_gio );
/* Và trỏ sang công thức tính công của khối Văn phòng — đó là màn khác, nhiều ô hơn hẳn. */
t( 'trỏ sang công thức tính công của khối Văn phòng',
	strpos( $h_ch_gio, 'công thức tính công của khối Văn phòng' ) !== false
	|| strpos( $h_ch_gio, 'công thức '  ) !== false, $h_ch_gio );

/* Gác cửa: đổi cách tính là đổi con số ra tiền, không phải việc trong phạm vi một cửa hàng. */
foreach ( array( 'Nhân viên', 'Cửa hàng trưởng' ) as $vt_ct ) {
	$r_c = VHCC_Luong::dat_cach_tinh(
		array( 'name' => 'X', 'role' => $vt_ct, 'coso' => $CS_GIO ), array( $CS_GIO => 'cong' ) );
	t( $vt_ct . ' KHÔNG đổi được cách tính', empty( $r_c['ok'] ), $r_c );
}
$h_ct_ch = vhcc_web( '357913', array(), array( 'man' => 'cau_hinh', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' ) );
t( 'Cửa hàng trưởng không thấy khối cấu hình', strpos( $h_ct_ch, 'id="cachtinh"' ) === false, $h_ct_ch );
/* Ẩn khối không phải là gác cửa — POST thẳng cũng phải bị chối. */
$_POST = array( 'viec' => 'cach_tinh', 'ct' => array( $CS_GIO => 'cong' ) );
$r_ct_p = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'cach_tinh', array( 'name' => 'CHT', 'role' => 'Cửa hàng trưởng', 'coso' => $CS_GIO ) ) );
$_POST = array();
t( 'và POST thẳng cũng bị chối', is_array( $r_ct_p ) && ! isset( $r_ct_p[0]['xong'] ), $r_ct_p );
teq( 'cách tính KHÔNG bị đổi', 'gio', VHCC_Luong::cach_tinh( $CS_GIO ) );

/* ---- XUẤT EXCEL: chi tiết ca đó từ mấy giờ đến mấy giờ ---- */
/* Anh Thắng 26/08: *"bổ sung tính năng xuất excel, khi xuất thì nó sẽ chi tiết ca đó từ mấy h
   đến mấy h"*. */
t( 'màn có nút Xuất Excel', strpos( $h_gio, 'Xuất Excel' ) !== false, $h_gio );
t( 'nút mang sẵn cơ sở và tháng đang xem',
	strpos( $h_gio, 'xuat=ca' ) !== false && strpos( $h_gio, 'cth=2026-07' ) !== false, $h_gio );

$b_xuat = VHCC_Cham::bang_cham_cong( $ADMIN_W, $CS_GIO, '2026-07' );
$to_x   = VHCC_Ca::to_xuat( $b_xuat, $CS_GIO );
teq( 'xuất ra ĐÚNG ba trang tính', 3, count( $to_x ) );
teq( 'trang đầu là Chi tiết ca', 'Chi tiết ca', $to_x[0]['ten'] );
teq( 'trang hai là Tổng theo ca', 'Tổng theo ca', $to_x[1]['ten'] );
teq( 'trang ba là Từng lượt chấm', 'Từng lượt chấm', $to_x[2]['ten'] );

/* 🔴 Trang Chi tiết ca: MỖI CA MỘT DÒNG, không phải mỗi ngày một dòng. Gộp lại là mất đúng thứ
   anh Thắng cần — giờ nào thuộc ca nào. */
$dau_x = $to_x[0]['hang'][0];
foreach ( array( 'Ca', 'Ca bắt đầu', 'Ca kết thúc', 'Giờ trong ca' ) as $c_x ) {
	t( 'trang Chi tiết ca có cột "' . $c_x . '"', in_array( $c_x, $dau_x, true ), $dau_x );
}
/* GIO1 ngày 01/07 làm 08:00–17:30, vắt Ca 1 và Ca 2 -> phải ra HAI dòng cho một ngày. */
$dong_gio1 = array();
foreach ( array_slice( $to_x[0]['hang'], 1 ) as $d_x ) {
	if ( '2026-07-01' === $d_x[0] && 'chính' === $d_x[4] ) { $dong_gio1[] = $d_x; }
}
teq( 'một ngày vắt hai ca thì ra HAI dòng', 2, count( $dong_gio1 ) );
/* ⚠️ Ca ở đây là ca ĐANG KHAI cho cơ sở này ("Sáng" 06–12 · "Chiều" 12–20, khai ở mục trên),
   KHÔNG phải bộ mặc định Ca 1/Ca 2. Lượt 08:00–17:30 -> Sáng 4h, Chiều 5h30. Em gõ Ca 1/Ca 2
   theo trí nhớ và trượt — con số dưới đây lấy từ chính phép tính. */
$ca_cua = array();
foreach ( $dong_gio1 as $d_x ) { $ca_cua[ $d_x[8] ] = array( $d_x[9], $d_x[10], $d_x[11] ); }
teq( 'dòng ca đầu ghi rõ khung ca và số giờ trong ca',
	array( '06:00', '12:00', 4.0 ), isset( $ca_cua['Sáng'] ) ? $ca_cua['Sáng'] : $ca_cua );
teq( 'dòng ca sau cũng vậy',
	array( '12:00', '20:00', 5.5 ), isset( $ca_cua['Chiều'] ) ? $ca_cua['Chiều'] : $ca_cua );

/* Ngày thiếu giờ ra vẫn phải có dòng, kèm ghi chú — bỏ đi là bảng xuất ra trông như người ta
   nghỉ hôm đó. */
$co_thieu = false;
foreach ( array_slice( $to_x[0]['hang'], 1 ) as $d_x ) {
	if ( false !== strpos( (string) $d_x[12], 'THIẾU GIỜ RA' ) ) { $co_thieu = true; }
}
t( 'ngày thiếu giờ ra vẫn có dòng, kèm ghi chú', $co_thieu, $to_x[0]['hang'] );

/* Mã NV phải ghi là CHỮ. Mã kiểu `0029` để Excel tự đoán là mất số 0 ở đầu. */
$o_ma = $to_x[0]['hang'][1][2];
t( 'mã NV được bọc thành chữ, không để Excel đoán',
	is_array( $o_ma ) && isset( $o_ma['chu'] ), $o_ma );

/* Dựng tệp thật rồi MỞ LẠI bằng máy — không tin "dựng xong là đúng". */
$noi_x = VHCC_Xuat::xlsx( $to_x );
t( 'dựng được tệp .xlsx', is_string( $noi_x ) && 'PK' === substr( $noi_x, 0, 2 ) );
$tep_x = sys_get_temp_dir() . '/vhcc-xuat-' . getmypid() . '.xlsx';
file_put_contents( $tep_x, $noi_x );
$z_x = new ZipArchive();
t( 'mở lại được tệp vừa dựng', true === $z_x->open( $tep_x ) );
foreach ( array( 1, 2, 3 ) as $i_x ) {
	$xml_x = $z_x->getFromName( 'xl/worksheets/sheet' . $i_x . '.xml' );
	t( 'trang tính ' . $i_x . ' là XML hợp lệ', false !== simplexml_load_string( (string) $xml_x ) );
}
$s1_x = $z_x->getFromName( 'xl/worksheets/sheet1.xml' );
$wb_x = $z_x->getFromName( 'xl/workbook.xml' );
$z_x->close();
@unlink( $tep_x );
t( 'tên ba trang tính nằm trong workbook',
	false !== strpos( $wb_x, 'Chi tiết ca' ) && false !== strpos( $wb_x, 'Tổng theo ca' )
	&& false !== strpos( $wb_x, 'Từng lượt chấm' ), $wb_x );
/* Khung ca của cơ sở này là Sáng 06:00–12:00 · Chiều 12:00–20:00 (khai ở mục trên), không phải
   bộ mặc định — đây chính là thứ anh Thắng hỏi: "chi tiết ca đó từ mấy h đến mấy h". */
t( 'trang Chi tiết ca in ra khung giờ ca thật (06:00 và 12:00)',
	false !== strpos( $s1_x, '>06:00<' ) && false !== strpos( $s1_x, '>12:00<' ), substr( $s1_x, 0, 600 ) );
t( 'và in cả tên ca', false !== strpos( $s1_x, '>Sáng<' ), substr( $s1_x, 0, 600 ) );
t( 'và tên có dấu không bị vỡ', false !== strpos( $s1_x, 'Người GIO1' ), substr( $s1_x, 0, 400 ) );

/* Gác cửa của đường tải tệp — hỏi hàm THUẦN, không gọi `xuat_tep` (hàm đó luôn `exit`, gọi
   trong bộ thử là giết luôn cả lượt chạy và phần gác cửa vĩnh viễn không có phép thử nào). */
teq( 'Admin xuất được', '',
	VHCC_Web::vi_sao_khong_xuat( $ADMIN_W, 'ca', $CS_GIO ) );
foreach ( array( 'Nhân viên' ) as $vt_x ) {
	$c_x = VHCC_Web::vi_sao_khong_xuat( array( 'name' => 'X', 'role' => $vt_x, 'coso' => $CS_GIO ),
		'ca', $CS_GIO );
	t( $vt_x . ' KHÔNG tải được tệp',
		'' !== $c_x && false !== strpos( $c_x, 'Cửa hàng trưởng' ), $c_x );
}
t( 'chưa chọn cơ sở -> chối', '' !== VHCC_Web::vi_sao_khong_xuat( $ADMIN_W, 'ca', '' ) );
t( 'kiểu xuất lạ -> chối', '' !== VHCC_Web::vi_sao_khong_xuat( $ADMIN_W, 'linh_tinh', $CS_GIO ) );
/* Cửa hàng trưởng chỉ tải được cơ sở MÌNH — ẩn cái nút không phải là gác cửa. */
$c_x2 = VHCC_Web::vi_sao_khong_xuat(
	array( 'name' => 'CHT', 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT' ), 'ca', $CS_GIO );
t( 'Cửa hàng trưởng không tải được cơ sở của người khác', '' !== $c_x2, $c_x2 );

/* Nhân viên không khai ca được, kể cả POST thẳng. */
$h_ca_nv = vhcc_web( '864202', array(), array( 'man' => 'vp', 'ccs' => $CS_GIO, 'cth' => '2026-07' ) );
t( 'Nhân viên không thấy khối khai ca', strpos( $h_ca_nv, 'id="khaica"' ) === false, $h_ca_nv );
$_POST = array( 'viec' => 'ca', 'ccs' => $CS_GIO, 'ca_ten' => array( 'Lậu' ),
	'ca_tu' => array( '00:00' ), 'ca_den' => array( '23:00' ) );
$r_ca_nv = vhcc_goi_rieng( 'VHCC_Web', 'lam_viec',
	array( 'ca', array( 'name' => 'NV', 'role' => 'Nhân viên', 'coso' => $CS_GIO ) ) );
$_POST = array();
t( 'và POST thẳng việc khai ca cũng bị chối',
	is_array( $r_ca_nv ) && ! isset( $r_ca_nv[0]['xong'] ), $r_ca_nv );

/* ---- ô ẩn chở bộ lọc KHÔNG được đè lên cơ sở màn đang hiện ---- */
/* `o_loc()` cũng chở `ccs`, nên form có hai ô cùng tên và ô SAU thắng. Chọn một bộ phận mà cơ sở
   cũ rơi ra ngoài thì `$cs` thành rỗng, trong khi `?ccs=` trên thanh địa chỉ vẫn giữ cơ sở cũ —
   để o_loc thắng là bù giờ vào một cơ sở mà màn hình không hề đang hiện. */
function vhcc_ccs_cuoi( $html, $sau ) {
	$i = strpos( $html, $sau );
	if ( false === $i ) { return null; }
	$het = strpos( $html, '</form>', $i );
	$than = ( false === $het ) ? substr( $html, $i ) : substr( $html, $i, $het - $i );
	return preg_match_all( '/name="ccs" value="([^"]*)"/', $than, $m ) ? end( $m[1] ) : null;
}
$h_bu_loc = vhcc_web( '135791', array(), array( 'man' => 'cham', 'ccs' => 'TUTU_BT',
	'cth' => '2026-07', 'cbp' => 'Bộ phận không tồn tại' ) );
/* Bộ lọc gạt hết cơ sở -> màn không vẽ bảng, nên cũng không được vẽ hàng bù mang cơ sở cũ. */
t( 'lọc rỗng thì không vẽ hàng bù mang cơ sở cũ',
	strpos( $h_bu_loc, 'value="bu"' ) === false, $h_bu_loc );
/* Còn ở hàng bù nội tuyến: ô ccs cuối cùng trong form phải là cơ sở ĐANG HIỆN. */
teq( 'hàng bù gửi đúng cơ sở đang hiện', 'TUTU_BT',
	vhcc_ccs_cuoi( $h_o_bu, 'value="bu"' ) );
teq( 'form khai ca gửi đúng cơ sở đang hiện', $CS_GIO,
	vhcc_ccs_cuoi( $h_ch_gio, 'value="ca"' ) );
/* 🔴 Ca phân biệt được "ô nào thắng": địa chỉ mang tiền tố `CS_` (app cũ viết vậy). `$cs` đã
   qua `chuan_coso()` nên là `FZ_SC_THU`, còn `o_loc()` chở nguyên `CS_FZ_SC_THU`. Ô của MÀN phải
   thắng — để o_loc thắng là khai ca cho một chuỗi cơ sở không tồn tại, và bảng công vẫn dùng ca
   mặc định mãi mà không ai hiểu vì sao lưu rồi không ăn. */
$h_tien_to = vhcc_web( '135791', array(),
	array( 'man' => 'cau_hinh', 'ccs' => 'CS_' . $CS_GIO, 'cth' => '2026-07' ) );
teq( 'địa chỉ có tiền tố CS_ thì form khai ca vẫn gửi mã cơ sở ĐÃ CHUẨN HOÁ', $CS_GIO,
	vhcc_ccs_cuoi( $h_tien_to, 'value="ca"' ) );
teq( 'và hàng bù cũng vậy', 'TUTU_BT',
	vhcc_ccs_cuoi( vhcc_web( '135791', array(),
		array( 'man' => 'cham', 'ccs' => 'CS_TUTU_BT', 'cth' => '2026-07',
			'gnd' => '2026-07-02', 'gma' => 'QTC1' ) ), 'value="bu"' ) );

// ====== 51. TRANG CHÀO + LINK GỬI BỘ PHẬN + CHẠY ĐƯỢC TRÊN ĐIỆN THOẠI
/* Anh Thắng 26/08: *"làm lại giao diện web chuẩn để anh gửi các bộ phận"*. Bốn hướng anh chốt:
   trang chào theo vai · phần nhìn gọn · chạy tốt trên điện thoại · link riêng từng bộ phận. */

$h_nha_ad = vhcc_web( '135791' );                       // Admin
$h_nha_nv = vhcc_web( '864202' );                       // Nhân viên
$h_nha_ch = vhcc_web( '357913' );                       // Cửa hàng trưởng

t( 'mở trang ra là vào Trang chính', strpos( $h_nha_ad, 'Việc anh/chị làm được' ) !== false, $h_nha_ad );
t( 'chào đúng tên người đang vào', strpos( $h_nha_ad, 'Chào Admin Soát Công' ) !== false, $h_nha_ad );
t( 'và nói rõ đang vào với vai gì', strpos( $h_nha_ad, 'Admin' ) !== false );
/* 🔴 Thẻ việc dựng theo QUYỀN. Hiện rồi chối là dạy người dùng rằng màn này hay nói dối, và từ
   đó họ không tin cái nút nào nữa. */
t( 'Nhân viên thấy việc chấm công', strpos( $h_nha_nv, 'Chấm công</b>' ) !== false, $h_nha_nv );
t( 'và thấy việc xem công của mình', strpos( $h_nha_nv, 'Công của tôi</b>' ) !== false, $h_nha_nv );
t( 'nhưng KHÔNG thấy việc nạp công', strpos( $h_nha_nv, 'Nạp công từ .csv</b>' ) === false, $h_nha_nv );
t( 'không thấy việc hồ sơ', strpos( $h_nha_nv, 'Hồ sơ &amp; tài khoản</b>' ) === false, $h_nha_nv );
t( 'không thấy việc khai ca', strpos( $h_nha_nv, 'Khai ca làm việc</b>' ) === false, $h_nha_nv );
t( 'Cửa hàng trưởng thấy việc chấm công bù', strpos( $h_nha_ch, 'Chấm công bù</b>' ) !== false, $h_nha_ch );
t( 'và thấy việc khai ca', strpos( $h_nha_ch, 'Khai ca làm việc</b>' ) !== false, $h_nha_ch );
t( 'nhưng KHÔNG thấy việc nạp công (bậc Quản lý)',
	strpos( $h_nha_ch, 'Nạp công từ .csv</b>' ) === false, $h_nha_ch );
t( 'Admin thấy đủ, kể cả hồ sơ', strpos( $h_nha_ad, 'Hồ sơ &amp; tài khoản</b>' ) !== false, $h_nha_ad );
/* Mỗi thẻ phải có một câu nói việc đó ĐỂ LÀM GÌ — chỉ có tên thì vẫn phải đoán. */
t( 'mỗi việc kèm một câu giải thích', substr_count( $h_nha_ad, '<span>' ) >= 5, $h_nha_ad );

/* ---- link gửi bộ phận ---- */
t( 'có khối Đường link gửi cho bộ phận', strpos( $h_nha_ad, 'id="guilink"' ) !== false, $h_nha_ad );
t( 'khối link thu gọn sẵn', strpos( $h_nha_ad, 'id="guilink"><details>' ) !== false, $h_nha_ad );
t( 'link mang sẵn cơ sở', strpos( $h_nha_ad, 'ccs=TUTU_BT' ) !== false, $h_nha_ad );
t( 'và mang sẵn tháng', preg_match( '/cth=\d{4}-\d{2}/', $h_nha_ad ) === 1, $h_nha_ad );
t( 'gom cơ sở theo bộ phận', strpos( $h_nha_ad, '<th>Bộ phận</th>' ) !== false, $h_nha_ad );
/* 🔴 Nói THẲNG rằng link không phải chìa khoá — kẻo người gửi tưởng ai cầm link cũng xem được,
   rồi ngại không dám gửi, hoặc tệ hơn là tưởng đã chia sẻ xong mà người nhận mở ra chẳng thấy gì. */
t( 'nói rõ link KHÔNG phải chìa khoá', strpos( $h_nha_ad, 'không phải chìa khoá' ) !== false, $h_nha_ad );
t( 'Nhân viên KHÔNG có khối link gửi bộ phận', strpos( $h_nha_nv, 'id="guilink"' ) === false, $h_nha_nv );

/* ---- đường sang trang khác của công ty ----
   Anh Thắng 26/08: *"làm 1 trang chủ ghép các trang chấm công chung lại… tạo 1 trang chủ công ty
   K&H để liên kết đến các trang con"*. Trang chào ghép xong phần chấm công; khối này là đường ra.
   🔴 CHƯA CÀI thì KHÔNG được in khung rỗng: một tiêu đề không có gì bên dưới trông y như hỏng. */
t( 'chưa cài trang nội bộ / cổng K&H thì KHÔNG in khung rỗng',
	strpos( $h_nha_ad, 'Trang khác của công ty' ) === false, $h_nha_ad );

/* ⚠️ Khai bằng eval() vì PHP NÂNG mọi khai báo lớp ở cấp cao nhất lên lúc biên dịch tệp — khai
   thẳng thì `class_exists` đã trả true ngay từ dòng đầu và phép thử "chưa cài" ở trên không bao
   giờ đúng được. */
/* 🔴 LỚP CÓ MÀ HÀM KHÔNG. Bốn plugin cài độc lập nên bản có thể lệch nhau, và gọi một hàm không
   tồn tại là Fatal error — TRẮNG CẢ TRANG, không phải một ô hỏng. Vết này đã xảy ra thật ở chân
   trang app chi phí ngày 23/08/2026. Nên dựng đúng cảnh đó: cổng K&H bản cũ, chưa có `url()`. */
eval( 'class VHTC_Trang { public static function ve() { return null; } }' );
$h_nha_cu = vhcc_web( '135791' );
t( 'cổng K&H bản cũ (lớp CÓ, hàm url KHÔNG) -> trang chào vẫn vẽ được, không trắng trang',
	strpos( $h_nha_cu, 'Việc anh/chị làm được' ) !== false, substr( $h_nha_cu, 0, 200 ) );
t( 'và KHÔNG dựng ô trỏ sang cổng K&H', strpos( $h_nha_cu, 'Cổng K&amp;H' ) === false, $h_nha_cu );
t( 'chưa có trang nào dùng được thì vẫn không in khung rỗng',
	strpos( $h_nha_cu, 'Trang khác của công ty' ) === false, $h_nha_cu );

eval( 'class VHNB_Trang { public static function url() { return "https://khmatrix.com/noi-bo/"; } }' );
$h_nha_2 = vhcc_web( '135791' );
t( 'cài rồi thì trang chào có khối Trang khác của công ty',
	strpos( $h_nha_2, 'Trang khác của công ty' ) !== false, $h_nha_2 );
t( 'và trỏ sang bảng tin nội bộ', strpos( $h_nha_2, 'https://khmatrix.com/noi-bo/' ) !== false, $h_nha_2 );
t( 'ô cổng K&H vẫn vắng vì bản kia chưa có url()',
	strpos( $h_nha_2, 'Cổng K&amp;H' ) === false, $h_nha_2 );
/* 🔴 Đường dẫn lấy TỪ CHÍNH plugin kia, không gõ cứng: gõ cứng là hôm nào anh Thắng đổi đường
   dẫn bên ấy, ô này vẫn trỏ về đường cũ — bấm vào ra 404 mà không có gì báo. */
t( 'mã KHÔNG gõ cứng đường dẫn trang nội bộ',
	strpos( file_get_contents( VHCC_DIR . 'includes/class-vhcc-web.php' ), "'noi-bo'" ) === false );

/* ---- chạy được trên điện thoại ---- */
t( 'trang khai khổ màn hình', strpos( $h_nha_ad, 'name="viewport"' ) !== false, $h_nha_ad );
t( 'có luật riêng cho màn nhỏ', strpos( $h_nha_ad, '@media(max-width:640px)' ) !== false, $h_nha_ad );
/* 🔴 Ô nhập dưới 16px thì iPhone TỰ PHÓNG TO cả trang mỗi lần bấm vào ô, và không tự thu lại —
   người dùng phải vuốt ngang suốt phần còn lại. Đây là lỗi ai cũng gặp mà ít ai lần ra. */
t( 'ô nhập trên điện thoại đủ 16px (kẻo iPhone tự phóng to trang)',
	strpos( $h_nha_ad, 'input,select,textarea{font-size:16px}' ) !== false, $h_nha_ad );
t( 'thẻ việc xuống một cột trên màn nhỏ',
	strpos( $h_nha_ad, '.the-viec{grid-template-columns:1fr}' ) !== false, $h_nha_ad );
t( 'ô lọc cũng xuống một cột', strpos( $h_nha_ad, '.luoi{grid-template-columns:1fr}' ) !== false, $h_nha_ad );
/* Bảng ngang vẫn phải cuộn được — 31 cột không có cách nào nhét vừa màn 5 inch. */
t( 'bảng rộng vẫn cuộn ngang được trong khung riêng',
	strpos( $h_nha_ad, '.cuon{overflow-x:auto' ) !== false, $h_nha_ad );

/* ---- phần nhìn ---- */
t( 'đầu trang mang tên K&H', strpos( $h_nha_ad, '<b>K&amp;H</b> Chấm công' ) !== false, $h_nha_ad );
t( 'bấm tên là về trang chính', strpos( $h_nha_ad, 'class="hieu"' ) !== false, $h_nha_ad );
t( 'trang chào KHÔNG dùng JavaScript',
	stripos( $h_nha_ad, '<script' ) === false && ! preg_match( '/\son[a-z]+\s*=\s*"/i', $h_nha_ad ),
	$h_nha_ad );

/* ---- MỌI FORM POST PHẢI MANG THẺ PHIÊN `ky` KHÁC RỖNG ---- */
/* 🔴 Bắt được lỗi thật: tab "Bảng công tháng" gọi `the_cong_vp( $toi )` mà bên trong lại dùng
   `$ky` — biến chưa hề được truyền vào. Form khai ca vì thế mang `ky=""`, và trên trang thật
   MỌI lượt lưu ca đều bị chối bằng câu "Phiên đã hết hoặc biểu mẫu không hợp lệ".
   Phép thử cũ không bắt vì nó gọi thẳng `lam_viec`, đi vòng qua cửa kiểm thẻ. Nên chốt này quét
   TẤT CẢ form của TẤT CẢ màn, thay vì canh từng form một. */
$man_thu = array(
	'cham'     => array( 'man' => 'cham', 'ccs' => 'TUTU_BT', 'cth' => '2026-07' ),
	'vp'       => array( 'man' => 'vp', 'ccs' => $CS_GIO, 'cth' => '2026-07' ),
	'nha'      => array( 'man' => 'nha' ),
	'cong_toi' => array( 'man' => 'cong_toi' ),
	'ho_so'    => array( 'man' => 'ho_so' ),
);
foreach ( $man_thu as $ten_man => $get_man ) {
	$h_man = vhcc_web( '135791', array(), $get_man );
	foreach ( array_slice( explode( '<form', $h_man ), 1 ) as $khuc_man ) {
		$het_man = strpos( $khuc_man, '</form>' );
		$than_man = ( false === $het_man ) ? $khuc_man : substr( $khuc_man, 0, $het_man );
		if ( false === stripos( $than_man, 'method="post"' ) ) { continue; }
		$viec_man = preg_match( '/name="viec" value="([^"]*)"/', $than_man, $m_man ) ? $m_man[1] : '(nút)';
		t( 'màn ' . $ten_man . ': form POST "' . $viec_man . '" mang thẻ phiên khác rỗng',
			preg_match( '/name="ky" value="[^"]+"/', $than_man ) === 1, trim( substr( $than_man, 0, 200 ) ) );
	}
}

/* ================================= 51b. THÔNG TIN CÔNG TY Ở CUỐI TRANG
   Anh Thắng 26/08: *"nhớ bổ sung thông tin công ty ở cuối trang nhé"* — kèm ảnh chính trang này. */

/* 🔴 MỘT CHỖ ĐÓNG TRANG, KHÔNG BẢY CHỖ.
   Trước đây bảy màn tự `echo '</div></body></html>'`. Thêm chân trang kiểu ấy là sửa bảy chỗ,
   quên một chỗ, rồi đúng màn bị quên thì thiếu thông tin công ty mà chẳng ai để ý. Canh thẳng
   vào MÃ: cả tệp chỉ được có đúng một dòng đóng trang. */
/* ⚠️ BỎ CHÚ THÍCH TRƯỚC KHI ĐẾM. Chốt này soi MÃ, không soi lời giải thích về mã: chính dòng
   chú thích kể VỀ cái lỗi cũ ("mỗi màn tự echo '</div></body></html>'") bị đếm thành một chỗ
   sót — phép thử đỏ trong khi mã đúng. Đúng vết đã cắn `kiem-goi-cheo.php` sáng nay. */
function vhcc_bo_chu_thich( $ma ) {
	$ra = '';
	foreach ( token_get_all( $ma ) as $tk ) {
		if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
		$ra .= is_array( $tk ) ? $tk[1] : $tk;
	}
	return $ra;
}
$ma_web = vhcc_bo_chu_thich( file_get_contents( VHCC_DIR . 'includes/class-vhcc-web.php' ) );
teq( '🔴 class-vhcc-web.php chỉ có ĐÚNG MỘT chỗ in </body></html>', 1,
	substr_count( $ma_web, "'</body></html>'" ) );
teq( 'và không còn dòng đóng trang gộp nào sót lại', 0,
	substr_count( $ma_web, "</div></body></html>'" ) );

/* Chưa ai khai công ty ở đâu -> KHÔNG bịa ra tên, địa chỉ, mã số thuế. Bịa còn tệ hơn để trống. */
delete_option( 'vhg_chan' );
teq( 'chưa khai gì thì thong_tin() trả rỗng', array(), VHCC_Cty::thong_tin() );
$_chan_trong = VHCC_Cty::html();
t( '🔴 nhưng KHÔNG bịa ra thông tin công ty nào',
	false === strpos( $_chan_trong, 'cty-ten' ) && false === strpos( $_chan_trong, 'Mã số thuế' ),
	$_chan_trong );
/* ⚠️ VẪN PHẢI CÒN SỐ PHIÊN BẢN. Anh Thắng bảo *"cuối MỌI trang"*, mà thông tin công ty là thứ
   khai được và site mới cài thì chắc chắn trống — để cả chân trang biến mất theo là đúng lúc
   cần số nhất (vừa cài xong, đang muốn biết bản nào đang chạy) thì lại không có. */
t( 'chân trang trống vẫn giữ nhãn phiên bản',
	false !== strpos( $_chan_trong, 'class="cty-pb"' ), $_chan_trong );
t( 'trang vẫn vẽ bình thường khi chưa khai công ty',
	strpos( vhcc_web( '135791' ), 'Việc anh/chị làm được' ) !== false );

/* Khai vào ô cài đặt dùng chung `vhg_chan` — đúng ô mà màn quản trị của plugin Ghế đang ghi. */
update_option( 'vhg_chan', array(
	'ten'        => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H',
	'ten_qt'     => 'K&H SERVICES AND ENTERTAINMENT COMPANY LIMITED',
	'mst'        => '0106924989',
	'dia_chi'    => 'Thôn Mai Nội, Xã Sóc Sơn, Thành phố Hà Nội, Việt Nam',
	'dai_dien'   => 'Nguyễn Văn Kiên',
	'dien_thoai' => '0435961469',
	'email'      => '',
	'ngay_hd'    => '05/08/2015',
	'co_quan'    => 'Thuế cơ sở 18 thành phố Hà Nội',
	'chi_nhanh'  => "Đà Nẵng\nNha Trang",
	'hien'       => 0,
) );

/* 🔴 CHÂN TRANG PHẢI CÓ Ở **MỌI** MÀN, kể cả màn đăng nhập — nhất là màn đăng nhập, vì đó là
   thứ duy nhất người ngoài nhìn thấy. Quét lại đúng danh sách màn ở khối trên, không gõ riêng. */
$h_dnhap = vhcc_web( '000000' );        // PIN sai -> màn đăng nhập
t( 'màn ĐĂNG NHẬP có thông tin công ty',
	strpos( $h_dnhap, '0106924989' ) !== false, substr( $h_dnhap, -400 ) );
/* 🔴 VÀ NHÃN PHIÊN BẢN CŨNG PHẢI CÓ Ở CHÂN TRANG ĐẦY ĐỦ, không chỉ ở chân trang trống.
   Khối phép ở trên soi lúc CHƯA khai công ty, nên nó không với tới nhánh này — bỏ nhãn khỏi
   chân trang đầy đủ mà mọi phép vẫn xanh. Đã phá thử để thấy đúng chuyện đó. */
t( 'chân trang ĐẦY ĐỦ vẫn có nhãn phiên bản',
	strpos( $h_dnhap, 'class="cty-pb"' ) !== false, substr( $h_dnhap, -600 ) );
t( 'và số ấy đúng bằng hằng VHCC_VERSION',
	strpos( $h_dnhap, 'Chấm công ' . VHCC_VERSION ) !== false, VHCC_VERSION );
foreach ( $man_thu as $ten_man => $get_man ) {
	$h_ct = vhcc_web( '135791', array(), $get_man );
	t( 'màn ' . $ten_man . ' có tên công ty ở cuối trang',
		strpos( $h_ct, 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&amp;H' ) !== false );
	t( 'màn ' . $ten_man . ' có mã số thuế', strpos( $h_ct, '0106924989' ) !== false );
	t( 'màn ' . $ten_man . ' có dòng bản quyền', strpos( $h_ct, '© ' . gmdate( 'Y' ) ) !== false );
}

$h_ct = vhcc_web( '135791' );
/* 🔴 CHÂN TRANG PHẢI NẰM TRONG KHUNG `.bo` CỦA TRANG.
   Anh Thắng 26/08: *"bị lệch"* — bản đầu in chân trang SAU khi đã đóng `.bo`, nên nó dính sát
   mép trái màn hình trong khi cả trang còn lại thụt vào. Bộ thử lúc ấy vẫn xanh vì nó chỉ hỏi
   "có chữ đó không", chưa hỏi "chữ đó nằm ở đâu". */
t( '🔴 chân trang nằm TRONG khung .bo, không lọt ra ngoài',
	strpos( $h_ct, '<div class="bo"><footer class="cty">' ) !== false,
	substr( $h_ct, strrpos( $h_ct, 'footer class="cty"' ) - 120, 160 ) );
/* ⚠️ Bỏ điều kiện `@media(max-width:…)` ra trước khi soi: đó là mốc màn hình, không phải bề
   ngang của khối. Bản đầu quét thẳng cả chuỗi nên chốt đỏ oan. */
$css_cty = preg_replace( '/@media\s*\([^)]*\)/', '', VHCC_Cty::css() );
t( 'và kiểu chữ của chân trang KHÔNG tự đặt max-width (kẻo hai khung chồng nhau)',
	strpos( $css_cty, 'max-width' ) === false, $css_cty );
t( 'số điện thoại bấm gọi được', strpos( $h_ct, 'href="tel:0435961469"' ) !== false, $h_ct );
t( 'có chi nhánh, nối bằng dấu chấm giữa',
	strpos( $h_ct, 'Đà Nẵng · Nha Trang' ) !== false, $h_ct );
/* Ô email đang trống -> bỏ hẳn dòng, không in nhãn treo lơ lửng. */
t( 'ô email trống thì KHÔNG in nhãn Email:', strpos( $h_ct, 'Email:' ) === false );
/* 🔴 KHÔNG đọc cờ `hien` của plugin Ghế (đang để 0 ở trên). Cờ ấy nói "có hiện trên trang Ghế
   không"; tắt nó để trang bán mã gọn hơn mà kéo theo trang chấm công mất phần giới thiệu công
   ty thì không ai đoán ra vì sao. */
t( 'tắt cờ `hien` bên plugin Ghế thì trang này VẪN có thông tin công ty',
	strpos( $h_ct, '0106924989' ) !== false );
/* Chân trang là CHỮ, không được kéo theo script hay thuộc tính on*= nào. */
t( 'chân trang không mang script nào',
	stripos( $h_ct, '<script' ) === false && ! preg_match( '/\son[a-z]+\s*=\s*"/i', $h_ct ) );
/* Kiểu chữ của chân trang phải nằm trong khối <style> DUY NHẤT của trang, không phải thẻ thứ hai. */
teq( 'cả trang chỉ có MỘT khối kiểu chữ', 1, substr_count( $h_ct, '<style>' ) );
t( 'và khối ấy có kiểu chữ của chân trang', strpos( $h_ct, '.cty-ten{' ) !== false );

/* Sửa ở ô cài đặt thì trang đổi theo ngay — không phải sửa mã, đóng gói, cài lại. */
$o_cty = get_option( 'vhg_chan' );
$o_cty['dia_chi'] = 'Số 1 Đường Mới, Hà Nội';
$o_cty['email']   = 'lienhe@khmatrix.com';
update_option( 'vhg_chan', $o_cty );
$h_ct2 = vhcc_web( '135791' );
t( 'sửa địa chỉ ở ô cài đặt thì trang đổi theo', strpos( $h_ct2, 'Số 1 Đường Mới' ) !== false );
t( 'và không còn địa chỉ cũ', strpos( $h_ct2, 'Thôn Mai Nội' ) === false );
t( 'khai email rồi thì dòng Email hiện ra',
	strpos( $h_ct2, 'href="mailto:lienhe@khmatrix.com"' ) !== false );

/* ---- bảng mặc định: HỎI PLUGIN KHÁC, không tự khai ----
   Plugin này KHÔNG giữ bản chép nào của thông tin công ty. Chưa ai lưu ô cài đặt thì nó hỏi
   `VHG_Chan` (plugin Ghế) rồi hỏi `VHTC_Trang` (cổng K&H). Không plugin nào có mặt thì thôi —
   đã thử ở đầu khối này. */
delete_option( 'vhg_chan' );
teq( 'lúc này quả thật chưa nạp plugin Ghế', false, class_exists( 'VHG_Chan' ) );
require_once $goc . '/wordpress/vhcp-ghe/includes/class-vhg-chan.php';
$t_md = VHCC_Cty::thong_tin();
t( '🔴 ô cài đặt trống thì lấy bảng mặc định TỪ plugin Ghế',
	isset( $t_md['mst'] ) && '0106924989' === $t_md['mst'], $t_md );
t( 'và trang hiện ra đúng thông tin ấy',
	strpos( vhcc_web( '135791' ), 'Thôn Mai Nội' ) !== false );

/* 🔴 Ô CỐ Ý ĐỂ TRỐNG PHẢI GIỮ ĐƯỢC TRẠNG THÁI TRỐNG.
   Anh Thắng xoá ô "Cơ quan quản lý thuế" đi vì không muốn hiện nữa. Nếu chỗ trộn dùng
   `! empty()` thay vì `array_key_exists()` thì giá trị mặc định nhảy vào lấp lại — xoá xong
   vẫn thấy, sửa mãi không mất, mà chẳng có gì giải thích vì sao. */
update_option( 'vhg_chan', array_merge( VHG_Chan::mac_dinh(), array( 'co_quan' => '' ) ) );
$h_ct3 = vhcc_web( '135791' );
t( 'xoá trắng ô Cơ quan quản lý thuế thì dòng đó BIẾN MẤT',
	strpos( $h_ct3, 'Cơ quan quản lý thuế' ) === false, $h_ct3 );
t( 'nhưng mấy ô khác vẫn còn', strpos( $h_ct3, '0106924989' ) !== false );

delete_option( 'vhg_chan' );

/* ---- màn mặc định phải KHAI THẲNG, không suy từ vị trí trong danh sách ---- */
/* 🔴 Bản trước lấy màn CUỐI danh sách làm mặc định. Thêm tab "Bảng công tháng" (cùng bậc quyền
   với "Bảng chấm công") là Cửa hàng trưởng đăng nhập vào bỗng rơi thẳng vào tab mới, chỉ vì nó
   được khai sau một dòng. Không gì báo, thanh nút trông y hệt. */
/* 🔴 ĐỔI Ý CÓ CHỦ Ý (anh Thắng 26/08: *"làm lại giao diện web chuẩn để anh gửi các bộ phận"*):
   MỌI vai giờ mở ra là vào TRANG CHÀO, không rơi thẳng vào bảng số. Người bộ phận mở link ra mà
   gặp ngay mấy trăm ô thì không biết mình được làm gì và bấm vào đâu. */
foreach ( array( 'Admin', 'Kế toán cá nhân', 'Quản lý', 'Cửa hàng trưởng', 'Nhân viên' ) as $vt_md ) {
	teq( $vt_md . ' mở ra là vào Trang chính', 'nha',
		VHCC_Web::man_mac_dinh( VHCC_Web::man_cua( array( 'role' => $vt_md ) ) ) );
}
/* Và canh cả MÀN THẬT SỰ HIỆN RA, không chỉ canh hàm trả về gì: hàm đúng mà chỗ gọi không dùng
   nó thì mọi phép trên vẫn xanh. Đã phá thử để thấy đúng chuyện đó. */
$h_cht_md = vhcc_web( '357913' );
t( 'Cửa hàng trưởng đăng nhập vào là thấy TRANG CHÀO',
	strpos( $h_cht_md, 'Việc anh/chị làm được' ) !== false, $h_cht_md );
t( 'chứ không rơi thẳng vào bảng số',
	strpos( $h_cht_md, 'Chi tiết từng lượt' ) === false
	&& strpos( $h_cht_md, 'là <b>số giờ làm</b>' ) === false, $h_cht_md );

/* Mọi màn khai được đều phải có tên trong bảng ưu tiên, kẻo thêm màn mới là lại rơi vào nhánh
   đoán mò ở cuối hàm. */
foreach ( array_keys( VHCC_Web::man_cua( array( 'role' => 'Admin' ) ) ) as $k_man ) {
	t( 'màn "' . $k_man . '" có tên trong bảng ưu tiên màn mặc định',
		in_array( $k_man, VHCC_Web::MAN_UU_TIEN, true ), VHCC_Web::MAN_UU_TIEN );
}

/* Nhân viên bậc 1 không mở được tab này. */
$h_vp_nv = vhcc_web( '864202', array(), $g_vp );
t( 'Nhân viên KHÔNG thấy tab Bảng công tháng', strpos( $h_vp_nv, '>Bảng công tháng<' ) === false, $h_vp_nv );
t( 'và gõ tay ?man=vp cũng không ra lưới', strpos( $h_vp_nv, '↳ ca đêm' ) === false, $h_vp_nv );

// ====== 50. MÃ TỰ SINH PHẢI KHÔNG ĐỤNG NHAU — lỗi im lặng làm mất cờ của người khác
/* Bài kiểm này CHẾT NGẪU NHIÊN khoảng 1/5 lượt chạy (UNIQUE constraint failed trên `ma_yc`),
   và lần lần ra thì đó là lỗi thật, có sẵn từ trước: ba nơi cùng sinh mã bằng
   `YmdHis . wp_rand(100, 999)` — cùng một giây chỉ có 900 giá trị.

   🔴 Chỗ nguy nhất KHÔNG phải chỗ làm bài kiểm chết. `VHCC_Cham::luu_ghi_chu` tra `flag_id`
      trước; trùng mã thì nó coi là "sửa cờ cũ" và ĐÈ LÊN cờ của người khác — khác ngày, khác
      người, khác nội dung — rồi vẫn trả `ok:true`. Trên MySQL thật `$wpdb->insert` chỉ trả về
      false chứ không ném lỗi, nên cả hai kiểu hỏng đều IM LẶNG. */

$U_MA = array( 'name' => 'Admin Mã', 'role' => 'Admin', 'coso' => '' );

/* Sinh nhiều mã LIÊN TIẾP trong cùng một giây — đúng cảnh mấy người bấm gửi cùng lúc.
   🔴 PHẢI GHI TỪNG MÃ VÀO BẢNG NGAY SAU KHI SINH, y như đường chạy thật.
   `ma_moi()` chống trùng bằng cách HỎI BẢNG. Sinh 200 mã mà không ghi mã nào vào bảng thì lớp
   chống trùng ấy không có gì để đọc, và cả phép thử tụt xuống thành "200 lần bốc ngẫu nhiên
   trong 900 nghìn giá trị có trùng không" — theo nghịch lý ngày sinh thì khoảng 2% số lần chạy
   sẽ trùng. Tức là bài kiểm ĐỎ NGẪU NHIÊN vài chục lượt một lần, trong khi mã chạy đúng. Đỏ
   ngẫu nhiên là thứ dạy người ta chạy lại bài kiểm cho tới lúc xanh. */
$ds_ma = array();
for ( $i_ma = 0; $i_ma < 200; $i_ma++ ) {
	$ma_x = VHCC_DB::ma_moi( 'YC', 'yeu_cau_nv', 'ma_yc' );
	$ds_ma[] = $ma_x;
	if ( '' !== $ma_x ) {
		$wpdb->insert( VHCC_DB::t( 'yeu_cau_nv' ), array( 'ma_yc' => $ma_x, 'ma_nv' => 'MA' . $i_ma ) );
	}
}
teq( '200 mã sinh liên tiếp thì KHÔNG mã nào rỗng', 0, count( array_filter( $ds_ma, function ( $x ) {
	return '' === $x; } ) ) );
teq( 'và không mã nào trùng mã nào', 200, count( array_unique( $ds_ma ) ) );

/* 🔴 Chốt gắn cờ: hai cờ gắn liên tiếp phải là HAI cờ, không phải một cờ bị đè. */
$co_a = VHCC_Cham::luu_ghi_chu( $U_MA, array( 'coso' => 'TUTU_BT', 'ngay' => '2026-07-11',
	'ma_nv' => 'MA1', 'ho_ten' => 'Người MA1', 'ghi_chu' => 'cờ của người thứ nhất' ) );
$co_b = VHCC_Cham::luu_ghi_chu( $U_MA, array( 'coso' => 'TUTU_BT', 'ngay' => '2026-07-12',
	'ma_nv' => 'MA2', 'ho_ten' => 'Người MA2', 'ghi_chu' => 'cờ của người thứ hai' ) );
t( 'gắn được cờ thứ nhất', ! empty( $co_a['ok'] ), $co_a );
t( 'gắn được cờ thứ hai', ! empty( $co_b['ok'] ), $co_b );
t( 'hai cờ mang HAI mã khác nhau', $co_a['flagId'] !== $co_b['flagId'],
	$co_a['flagId'] . ' vs ' . $co_b['flagId'] );
$ds_co_ma = VHCC_Cham::ds_ghi_chu( $U_MA, 'TUTU_BT', '2026-07' );
$noi_dung_co = array();
foreach ( $ds_co_ma as $c_ma ) { $noi_dung_co[] = $c_ma['ghi_chu']; }
t( 'cờ của người thứ nhất KHÔNG bị cờ thứ hai đè mất',
	in_array( 'cờ của người thứ nhất', $noi_dung_co, true )
	&& in_array( 'cờ của người thứ hai', $noi_dung_co, true ), $noi_dung_co );

/* Truyền sẵn flag_id thì vẫn phải SỬA đúng cờ đó, không đẻ ra cờ mới. */
$co_c = VHCC_Cham::luu_ghi_chu( $U_MA, array( 'flag_id' => $co_a['flagId'], 'coso' => 'TUTU_BT',
	'ngay' => '2026-07-11', 'ma_nv' => 'MA1', 'ho_ten' => 'Người MA1', 'ghi_chu' => 'đã sửa lại' ) );
teq( 'sửa cờ cũ thì giữ nguyên mã', $co_a['flagId'], $co_c['flagId'] );
teq( 'và KHÔNG đẻ thêm cờ', count( $ds_co_ma ),
	count( VHCC_Cham::ds_ghi_chu( $U_MA, 'TUTU_BT', '2026-07' ) ) );

/* Nới số ngẫu nhiên rồi thì cũng đừng ai lặng lẽ hạ nó xuống lại. */
$ma_mau = VHCC_DB::ma_moi( 'YC', 'yeu_cau_nv', 'ma_yc' );
t( 'mã có đủ 6 chữ số ngẫu nhiên ở đuôi (900 giá trị là quá ít cho một giây)',
	preg_match( '/^YC\d{14}\d{6}$/', $ma_mau ) === 1, $ma_mau );
/* Tên cột đi thẳng vào câu SQL -> chữ lạ phải bị chối, không được ghép vào câu lệnh. */
teq( 'tên cột lạ bị chối, không ghép vào SQL', '',
	VHCC_DB::ma_moi( 'YC', 'yeu_cau_nv', 'ma_yc; DROP TABLE x' ) );

/* 🔴 Chốt quét mã: không tệp nào được quay lại kiểu sinh mã cũ. */
$ma_tho = array();
foreach ( glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ) as $f_ma ) {
	foreach ( explode( "\n", file_get_contents( $f_ma ) ) as $i_d => $d_ma ) {
		if ( 0 === strpos( ltrim( $d_ma ), '*' ) || false !== strpos( $d_ma, '//' ) ) { continue; }
		if ( preg_match( "/wp_rand\(\s*100\s*,\s*999\s*\)/", $d_ma ) ) {
			$ma_tho[] = basename( $f_ma ) . ':' . ( $i_d + 1 );
		}
	}
}
t( 'không tệp nào còn tự sinh mã bằng wp_rand(100,999)', 0 === count( $ma_tho ), implode( ', ', $ma_tho ) );

/* ---- lưới người × ngày của bản cũ đã đi hẳn, không để lại hàm mồ côi ---- */
$than_web_qtc = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
t( 've_luoi_cham (lưới cũ) đã bỏ hẳn, không còn nằm chết trong tệp',
	strpos( $than_web_qtc, 've_luoi_cham' ) === false );

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — chấm công chạy thẳng trên host, máy nói chuyện với đúng một nơi.\n";
