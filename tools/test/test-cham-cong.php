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

/* Danh sách cho phép của cầu nối nay có 22 hàm máy + OTA (anh Thắng: Firebase giữ nguyên, chỉ
   đưa MÀN lên web). Chốt cứng cả danh sách: thêm tên vào đây là mở thêm một cửa gọi được từ web,
   nên phải sửa phép thử — tức phải nghĩ một lần nữa. */
$MAY_22 = array(
	'getDanhSachMay', 'getMachineStatus', 'getMachineRoster', 'chanDoanMay',
	'getQueueMay', 'getHangDoiTaiLai', 'xemKhoiTest', 'getLuongMayTuDong', 'getGiaMayTuDong',
	'ganMayVaoCuaHang', 'boGanMay', 'luuSimMay', 'requestMachineScan',
	'xoaLenhQueue', 'xoaLenhTaiLai', 'dungTaiLai', 'setGiaMayTuDong', 'donKhoiTest', 'requestBackfill',
	'getFwMoiNhat', 'getOtaTarget', 'setOtaTarget', 'clearOtaTarget' );
teq( 'cầu nối khai đúng 23 hàm máy + OTA', 23, count( $ham_app ) );
sort( $ham_app );
$mong = $MAY_22; sort( $mong );
teq( 'và đúng danh sách đó, không thừa không thiếu', $mong, $ham_app );
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
t( 'sơ đồ có đủ 20 bảng', count( $so_do ) === 20, count( $so_do ) );

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
t( 'nhan_vien giữ đủ 26 cột nghiệp vụ của NV_HEADERS', count( $cot_thuc['nhan_vien'] ) === 27 ); // 26 + id
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
function vhcc_dung_bang() {
	global $wpdb;
	foreach ( VHCC_DB::bang() as $ten => $than ) {
		$bang = $wpdb->prefix . 'vhcc_' . $ten;
		$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . $bang );
		$cot = array();
		foreach ( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) as $d ) {
			$d = rtrim( $d, ',' );
			if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/', $d ) ) { continue; }
			$cot[] = preg_replace( '/BIGINT\(20\) NOT NULL AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $d );
		}
		$wpdb->exec_raw( 'CREATE TABLE ' . $bang . " (\n" . implode( ",\n", $cot ) . "\n)" );
	}
}

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

teq( 'Admin sửa được hồ sơ', true, VHCC_NhanSu::co_sua_ho_so( $U_AD ) );
teq( 'Quản lý sửa được hồ sơ', true, VHCC_NhanSu::co_sua_ho_so( $U_QL ) );
teq( 'Cửa hàng trưởng sửa được hồ sơ', true, VHCC_NhanSu::co_sua_ho_so( $U_CHT ) );
teq( 'Nhân viên KHÔNG sửa được hồ sơ', false, VHCC_NhanSu::co_sua_ho_so( $U_NV ) );
teq( 'Kế toán KHÔNG sửa được hồ sơ (việc của họ ở app Lương)', false, VHCC_NhanSu::co_sua_ho_so( $U_KT ) );
/* Bậc trên: chỉ Admin/Quản lý. Cửa hàng trưởng KHÔNG được. */
teq( 'Cửa hàng trưởng KHÔNG có quyền quản trị NV', false, VHCC_NhanSu::co_quan_tri_nv( $U_CHT ) );
teq( 'Admin có', true, VHCC_NhanSu::co_quan_tri_nv( $U_AD ) );
teq( 'Quản lý có', true, VHCC_NhanSu::co_quan_tri_nv( $U_QL ) );
/* Lương: cửa hàng trưởng sửa hồ sơ người mình được, nhưng KHÔNG thấy lương. */
teq( 'Cửa hàng trưởng KHÔNG xem được lương', false, VHCC_NhanSu::co_xem_luong( $U_CHT ) );
teq( 'Quản lý xem được lương', true, VHCC_NhanSu::co_xem_luong( $U_QL ) );
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
t( 'Cửa hàng trưởng KHÔNG tạo được hồ sơ MỚI (Mã NV dùng chung cả chuỗi)',
	empty( $r['ok'] ) && stripos( $r['error'], 'Mã NV' ) !== false, $r['error'] );
t( 'và không có hồ sơ nào được tạo', VHCC_NhanSu::ho_so( 'NV001' ) === null );
$r = VHCC_NhanSu::luu_ho_so( $U_AD, $hs );
t( 'Admin tạo được', ! empty( $r['ok'] ) && true === $r['tao_moi'], isset( $r['error'] ) ? $r['error'] : '' );
/* Sửa hồ sơ ĐANG ở cơ sở mình: cửa hàng trưởng ĐƯỢC. */
$r = VHCC_NhanSu::luu_ho_so( $U_CHT, array( 'ma_nv' => 'NV001', 'sdt' => '0911', 'chuc_vu' => 'Thu ngân' ) );
t( 'Cửa hàng trưởng sửa được hồ sơ người của cửa hàng mình', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'và sửa vào thật', '0911', VHCC_NhanSu::ho_so( 'NV001' )['sdt'] );
/* ĐỔI CỬA HÀNG là chuyển cả công và lương -> cửa hàng trưởng KHÔNG được. */
$r = VHCC_NhanSu::luu_ho_so( $U_CHT, array( 'ma_nv' => 'NV001', 'cua_hang' => 'POSH_HCM' ) );
t( 'Cửa hàng trưởng KHÔNG đổi được cửa hàng của một người',
	empty( $r['ok'] ) && stripos( $r['error'], 'Đổi cửa hàng' ) !== false, $r['error'] );
teq( 'cửa hàng KHÔNG bị đổi', 'TUTU_BT', VHCC_NhanSu::ho_so( 'NV001' )['cua_hang'] );
t( 'và lời báo có gợi ý dùng Cơ sở phụ thay vì đổi hẳn',
	stripos( $r['error'], 'Cơ sở phụ' ) !== false, $r['error'] );
$r = VHCC_NhanSu::luu_ho_so( $U_QL, array( 'ma_nv' => 'NV001', 'cua_hang' => 'POSH_HCM' ) );
t( 'Quản lý đổi được', ! empty( $r['ok'] ) );
teq( 'và đổi thật', 'POSH_HCM', VHCC_NhanSu::ho_so( 'NV001' )['cua_hang'] );
/* Sau khi người đó chuyển đi, cửa hàng trưởng cũ KHÔNG sửa được nữa. */
$r = VHCC_NhanSu::luu_ho_so( $U_CHT, array( 'ma_nv' => 'NV001', 'sdt' => '0999' ) );
t( 'người đã chuyển đi: cửa hàng trưởng cũ không sửa được nữa',
	empty( $r['ok'] ) && stripos( $r['error'], 'không thuộc cơ sở' ) !== false, $r['error'] );

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
$ds_ql = VHCC_NhanSu::ds_nhan_vien( $U_QL, 'TUTU_BT' );
t( 'Quản lý thấy ô lương', isset( $ds_ql[0]['luong_co_ban'] ) );
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

// ============================================================ 21. Máy chấm công + OTA
/* Firebase giữ nguyên. Lớp này KHÔNG nói chuyện trực tiếp với Firebase — nó gọi Apps Script qua
   cầu nối, để chỉ MỘT nơi ghi Firebase và khoá Firebase không phải sao thêm một bản. */
$than_may = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-may.php' );
t( 'lớp máy KHÔNG gọi Firebase trực tiếp',
	stripos( $than_may, 'firebasedatabase' ) === false && stripos( $than_may, 'firebaseio' ) === false
	&& strpos( $than_may, 'wp_remote_get' ) === false && strpos( $than_may, 'wp_remote_post' ) === false );
t( 'mọi lượt đi qua cầu nối', strpos( $than_may, 'VHCC_CauNoi::goi' ) !== false );
t( 'và KHÔNG chứa khoá Firebase nào', stripos( $than_may, 'FB_SECRET' ) === false
	&& stripos( $than_may, 'VHCC_FB' ) === false );

/* ---- 21a. LINK OTA: chỗ hai lớp gác kia không che ----
   Cầu nối kiểm khoá, Apps Script kiểm quyền Admin — nhưng không ai kiểm cái link. Module 4G
   A7680C chết ở ~532 ký tự, mà link release GitHub trả 302 rồi chuyển hướng dài ~943 ký tự. Đẩy
   một link như vậy là MẤT LUÔN đường sửa từ xa của cả chuỗi: phải đi 26 cửa hàng cắm USB. */
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

/* Lệnh đẩy firmware cho CẢ CHUỖI phải đòi xác nhận đúng chữ — không ai bấm nhầm được. */
$r = VHCC_May::dat_ota( '2026-08-21', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', '' );
t( 'thiếu xác nhận: KHÔNG đẩy', empty( $r['ok'] ) && stripos( $r['error'], 'DONG Y' ) !== false, $r['error'] );
$r = VHCC_May::dat_ota( '2026-08-21', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', 'dong y' );
t( 'xác nhận sai chữ (chữ thường): KHÔNG đẩy', empty( $r['ok'] ) );
/* Xác nhận ĐÚNG mà link SAI thì vẫn phải chặn — thứ tự kiểm không được để link lọt. */
$r = VHCC_May::dat_ota( '2026-08-21', 'https://github.com/c/r/releases/download/v1/fw.bin', 'DONG Y' );
t( 'xác nhận đúng mà link release: VẪN chặn', empty( $r['ok'] ) && stripos( $r['error'], '302' ) !== false, $r['error'] );
$r = VHCC_May::dat_ota( '', 'https://raw.githubusercontent.com/c/r/bin/fw.bin', 'DONG Y' );
t( 'thiếu số phiên bản: chặn', empty( $r['ok'] ) );
/* Chưa khai PIN admin thì nói rõ, không gọi bừa. */
delete_option( 'vhcc_pin_admin' );
$r = VHCC_May::ds_may();
t( 'chưa khai PIN admin: báo rõ chứ không gọi bừa',
	empty( $r['ok'] ) && stripos( $r['error'], 'PIN admin' ) !== false, $r['error'] );

/* ---- 21b. CHỐNG LỆCH giữa sheet và MySQL ----
   Đây là chỗ nguy do CHÍNH việc này sinh ra: có hai nơi trả lời "máy này thuộc cơ sở nào", và
   trong giai đoạn ghi song song thì MỘT lượt bấm đi qua cả hai. Hai nơi khác nhau = cùng một lần
   bấm rơi vào hai cơ sở, không có gì báo. */
vhcc_dung_bang();
update_option( 'vhcc_pin_admin', '999999' );
update_option( 'vhcc_exec_url', 'https://script.google.com/macros/s/ABC/exec' );
update_option( 'vhcc_web_key', 'k' );
// MySQL đang có 3 máy; sheet sẽ trả về khác đi để lộ đủ ba loại lệch.
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-A', 'mac' => 'AA', 'cua_hang' => 'TUTU_BT' ) );
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-B', 'mac' => 'BB', 'cua_hang' => 'POSH_HCM' ) );
$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => 'SN-CU', 'mac' => 'CC', 'cua_hang' => 'CS_CU' ) );
/* ⚠️ ĐẶT LẠI cả mảng, đừng THÊM khoá: mục 3 đã đăng ký khoá '/exec', mà wp_remote_post trả về
   khoá KHỚP MỘT PHẦN ĐẦU TIÊN nó gặp — thêm khoá mới thì khoá cũ vẫn thắng và mock này không bao
   giờ chạy. Mất một lượt chẩn đoán ở đúng chỗ này. */
$GLOBALS['VHD_POST'] = array( 'script.google.com' => function ( $args ) {
	$y = json_decode( $args['body'], true );
	if ( 'getDanhSachMay' === $y['fn'] ) {
		return array( 'code' => 200, 'body' => wp_json_encode( array( 'ok' => true, 'data' => array(
			array( 'serial' => 'SN-A', 'mac' => 'AA', 'cuaHang' => 'TUTU_BT' ),      // khớp
			array( 'serial' => 'SN-B', 'mac' => 'BB', 'cuaHang' => 'CS_JP_BT' ),     // LỆCH cơ sở
			array( 'serial' => 'SN-MOI', 'mac' => 'DD', 'cuaHang' => 'TUTU_BT' ),    // thiếu ở MySQL
		) ) ) );
	}
	return array( 'code' => 200, 'body' => wp_json_encode( array( 'ok' => true, 'data' => true ) ) );
} );
$d = VHCC_May::doi_chieu();
t( 'đối chiếu chạy được', ! empty( $d['ok'] ), isset( $d['error'] ) ? $d['error'] : '' );
teq( 'tách đúng máy LỆCH cơ sở (ca NGUY)', 1, count( $d['lech'] ) );
teq( 'và nói rõ hai bên đang ghi cơ sở nào', array( 'JP_BT', 'POSH_HCM' ),
	array( $d['lech'][0]['sheet'], $d['lech'][0]['mysql'] ) );
teq( 'tách đúng máy THIẾU ở MySQL (vô hại, cổng nhận giữ vào cho_gan)', 1, count( $d['thieu'] ) );
teq( 'tách đúng máy THỪA ở MySQL', 1, count( $d['du'] ) );
t( 'ba nhóm KHÔNG bị gộp lẫn (ba cách xử khác nhau hẳn)',
	'SN-MOI' === $d['thieu'][0]['serial'] && 'SN-CU' === $d['du'][0]['serial'] );

/* Soi lại: chỉ đi MỘT CHIỀU sheet -> MySQL. */
$k = VHCC_May::soi_lai_mysql();
teq( 'soi lại: sửa 1 máy lệch', 1, $k['sua'] );
teq( 'và thêm 1 máy còn thiếu', 1, $k['them'] );
$sau = $wpdb->get_var( "SELECT cua_hang FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-B'" );
teq( 'MySQL đã theo sheet', 'JP_BT', $sau );
/* Máy THỪA thì KHÔNG xoá: có thể là máy vừa gửi lượt đầu mà sheet chưa kịp có dòng. Xoá là mất
   chỗ gán. */
t( 'máy thừa KHÔNG bị xoá, chỉ báo ra',
	null !== $wpdb->get_var( "SELECT id FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-CU'" ) );
$d2 = VHCC_May::doi_chieu();
teq( 'sau khi soi: hết lệch', 0, count( $d2['lech'] ) );
teq( 'hết thiếu', 0, count( $d2['thieu'] ) );

/* ---- 21c. Gán máy: SHEET TRƯỚC, sheet trượt thì KHÔNG chạm MySQL ---- */
$truoc = $wpdb->get_var( "SELECT cua_hang FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-A'" );
$GLOBALS['VHD_POST'] = array( 'script.google.com' => function ( $args ) {
	$y = json_decode( $args['body'], true );
	if ( 'ganMayVaoCuaHang' === $y['fn'] ) {
		return array( 'code' => 200, 'body' => wp_json_encode( array( 'ok' => false, 'error' => 'sheet bị khoá' ) ) );
	}
	return array( 'code' => 200, 'body' => wp_json_encode( array( 'ok' => true, 'data' => array() ) ) );
} );
$r = VHCC_May::gan_may( 2, 'CO_SO_MOI' );
t( 'sheet ghi trượt: gán trả về thất bại', empty( $r['ok'] ) );
teq( 'và MySQL KHÔNG bị đổi (thà cả hai đều cũ còn hơn hai bên lệch)', $truoc,
	$wpdb->get_var( "SELECT cua_hang FROM " . VHCC_DB::t( 'may' ) . " WHERE serial='SN-A'" ) );
t( 'gán máy KHÔNG có nhánh nào ghi MySQL trước khi sheet xong',
	strpos( $than_may, "if ( empty( \$r['ok'] ) ) { return \$r; }               // sheet trượt -> KHÔNG chạm MySQL" ) !== false
	|| preg_match( "/ganMayVaoCuaHang.*\n.*if \( empty\( \\\$r\['ok'\] \) \) \{ return \\\$r; \}/", $than_may ) === 1 );
/* Và KHÔNG bao giờ ghi ngược MySQL -> sheet. */
t( 'soi lại chỉ đi một chiều sheet -> MySQL, không ghi ngược',
	strpos( $than_may, 'KHÔNG bao giờ ghi ngược MySQL -> sheet' ) !== false );

// ============================================================ 22. Màn máy: mỏng, không tự tính
$ad4 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
$i_m = strpos( $ad4, 'public static function trang_may()' );
$i_m2 = strpos( $ad4, 'public static function trang_in()', $i_m );
$than_tm = substr( $ad4, $i_m, $i_m2 - $i_m );
t( 'màn máy gác quyền', strpos( $than_tm, 'current_user_can( self::CAP )' ) !== false );
t( 'màn máy có nonce', strpos( $than_tm, 'check_admin_referer' ) !== false );
t( 'màn máy KHÔNG gọi Firebase trực tiếp',
	stripos( $than_tm, 'firebase' ) === false && strpos( $than_tm, 'wp_remote' ) === false );
t( 'mọi việc đi qua lớp VHCC_May', strpos( $than_tm, 'VHCC_May::' ) !== false );
/* Đối chiếu phải ở ĐẦU trang: đó là chỗ duy nhất phát hiện ca "một lượt bấm rơi vào hai cơ sở",
   mà ca đó không tự báo và chỉ lộ ở bảng lương cuối tháng. Để xuống dưới là bị cuộn qua. */
$i_doi = strpos( $than_tm, 'Đối chiếu máy' );
$i_ds  = strpos( $than_tm, 'Danh sách máy' );
$i_fw  = strpos( $than_tm, 'Cập nhật firmware' );
t( 'phần Đối chiếu nằm TRƯỚC danh sách máy và trước firmware',
	$i_doi !== false && $i_doi < $i_ds && $i_doi < $i_fw );
t( 'màn máy cảnh báo rõ hậu quả của link release (302 / 943 / 532 ký tự)',
	strpos( $than_tm, '302' ) !== false && strpos( $than_tm, '532' ) !== false );
t( 'ô xác nhận DONG Y có trên màn', strpos( $than_tm, 'DONG Y' ) !== false );
/* Màn KHÔNG tự xoá máy thừa — chỉ báo. Xoá là mất chỗ gán của máy vừa gửi lượt đầu. */
t( 'màn máy không có nút xoá máy thừa', stripos( $than_tm, 'xoa_may' ) === false );

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
$wpdb->insert( VHCC_DB::t( 'vp_ngay_cong' ), array( 'coso' => 'VP_HCM', 'thang' => '2026-09', 'ngay_cong' => 26 ) );
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
$r = VHCC_Lich::dat_ca( $U_CHT, array( 'Ca 1', 'Ca 2' ) );
t( 'đổi danh sách ca được', ! empty( $r['ok'] ) );
teq( 'và BÁO RA ô lịch đang dùng tên ca vừa bị bỏ', 1, $r['oMoCoi']['Sáng'] );
teq( 'danh sách ca rỗng bị từ chối', false, VHCC_Lich::dat_ca( $U_AD, array() )['ok'] );
teq( 'nhân viên không sửa được ca', false, VHCC_Lich::dat_ca( $U_NV, array( 'X' ) )['ok'] );
t( 'đặt loại việc được', ! empty( VHCC_Lich::dat_loai_viec( $U_CHT, array( 'Thu tiền', 'Vệ sinh', 'Thu tiền' ) )['ok'] ) );
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
$r = VHCC_NhanSu::dat_nghi_viec( $U_CHT, 'D3', '2026-08-31', 'chuyển chỗ khác' );
t( 'cửa hàng trưởng cho nghỉ được (người của cửa hàng mình)', ! empty( $r['ok'] ),
	isset( $r['error'] ) ? $r['error'] : '' );
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

t( 'menu khai đủ 11 màn', count( $GLOBALS['VHCP_MENU'] ) === 11, count( $GLOBALS['VHCP_MENU'] ) );
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
$tep_soat['docs/CAI-LEN-HOSTINGER.md'] = file_get_contents( $goc . '/docs/CAI-LEN-HOSTINGER.md' );
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
	strpos( $tep_soat['docs/CAI-LEN-HOSTINGER.md'], '`CauNoiChamCong`' ) !== false );
t( 'hướng dẫn cài có nhắc dán cau-noi.gs (trước đây thiếu hẳn bước này)',
	strpos( $tep_soat['docs/CAI-LEN-HOSTINGER.md'], 'apps-script/cau-noi.gs' ) !== false );

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
$hd = $tep_soat['docs/CAI-LEN-HOSTINGER.md'];
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
	'docs/CAI-LEN-HOSTINGER.md' ) as $x ) {
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
t( 'chọn "danh sách riêng" mà rỗng thì màn Cài đặt CẢNH BÁO, không im lặng',
	strpos( $ad, 'Danh sách riêng đang RỖNG' ) !== false );
t( 'và nói rõ hậu quả: không ai đăng nhập được',
	strpos( $ad, 'không ai đăng nhập được' ) !== false );
t( 'và chỉ ra đường đi được: cài plugin chi phí rồi chọn Dùng chung',
	strpos( $ad, 'vhcp-chi-phi.zip' ) !== false );
/* Cảnh báo này chỉ đúng khi `vhcc_nguoidung` vẫn KHÔNG có chỗ nào ghi vào. Ngày nào làm màn
   khai danh sách riêng thì phép thử này phải đỏ, để nhớ bỏ đoạn cảnh báo đi. */
$co_ghi = 0;
foreach ( glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ) as $f ) {
	if ( preg_match( "/update_option\(\s*'vhcc_nguoidung'/", file_get_contents( $f ) ) ) { $co_ghi++; }
}
t( 'chưa màn nào ghi vhcc_nguoidung — nên cảnh báo trên vẫn còn đúng', $co_ghi === 0, $co_ghi );

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

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — plugin Chấm Công nối đúng, không chạm đường của máy.\n";
