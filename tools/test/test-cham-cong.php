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
t( 'cham_cong lưu giờ bằng PHÚT (INT), không phải TIME — ca đêm cần trục phẳng > 1440',
	in_array( 'gio_vao_phut', $cc, true ) && in_array( 'gio_ra_phut', $cc, true )
	&& stripos( $so_do['cham_cong'], ' TIME' ) === false );
t( 'cham_cong giữ hậu tố mã (TT/TG/CD/CT/TC) thành MỘT cột, không bung ra nhiều cờ',
	in_array( 'hau_to', $cc, true ) && ! in_array( 'la_tang_ca', $cc, true ) );
t( 'giờ vào/ra cho phép NULL — chưa chấm KHÁC chấm lúc 00:00',
	preg_match( '/gio_vao_phut INT NULL/', $so_do['cham_cong'] ) === 1
	&& preg_match( '/gio_ra_phut INT NULL/', $so_do['cham_cong'] ) === 1 );
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
t( 'cho_gan tồn tại — mã máy gửi về mà hồ sơ chưa khai thì GIỮ, không bỏ',
	isset( $so_do['cho_gan'] ) && in_array( 'gan_vao_ma', $cot_thuc['cho_gan'], true ) );
t( 'ma_song_song khai theo CẶP mã, không suy từ tên',
	strpos( $so_do['ma_song_song'], 'UNIQUE KEY cap (ma_a,ma_b)' ) !== false );

/* ---- Đổi giờ <-> phút phải khứ hồi đúng, kể cả trên trục phẳng ca đêm ---- */
teq( 'phut(08:30)', 510, VHCC_DB::phut( '08:30' ) );
teq( 'phut(17:00)', 1020, VHCC_DB::phut( '17:00' ) );
teq( 'phut(00:00) là 0, KHÔNG phải null', 0, VHCC_DB::phut( '00:00' ) );
teq( 'phut() của chuỗi rỗng là null (chưa chấm)', null, VHCC_DB::phut( '' ) );
teq( 'phut() của rác là null', null, VHCC_DB::phut( 'x' ) );
teq( 'hhmm(510)', '08:30', VHCC_DB::hhmm( 510 ) );
teq( 'hhmm(0) là 00:00, không phải rỗng', '00:00', VHCC_DB::hhmm( 0 ) );
teq( 'hhmm(null) là rỗng (chưa chấm)', '', VHCC_DB::hhmm( null ) );
/* Trục phẳng: 01:30 của ca đêm lưu là 1440+90 để nó nằm SAU 22:00, nhưng hiện ra vẫn là 01:30. */
teq( 'hhmm(1530) trên trục phẳng ca đêm vẫn hiện 01:30', '01:30', VHCC_DB::hhmm( 1530 ) );
teq( 'hhmm(1440) là 00:00', '00:00', VHCC_DB::hhmm( 1440 ) );
/* Đúng phép tính vòng nửa đêm của Code.gs: ra 01:30, vào 22:00 -> 3.5 tiếng. */
$vaoM = VHCC_DB::phut( '22:00' ); $raM = VHCC_DB::phut( '01:30' );
teq( 'số phút ca qua nửa đêm (22:00 -> 01:30) là 210', 210,
	( $raM > $vaoM ) ? ( $raM - $vaoM ) : ( $raM + 1440 - $vaoM ) );
if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — plugin Chấm Công nối đúng, không chạm đường của máy.\n";
