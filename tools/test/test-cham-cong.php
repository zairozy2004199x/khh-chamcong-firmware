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
if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — plugin Chấm Công nối đúng, không chạm đường của máy.\n";
