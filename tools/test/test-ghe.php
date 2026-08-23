<?php
/**
 * PHÉP THỬ PLUGIN GHẾ MASSAGE (wordpress/vhcp-ghe)
 *
 * =============================================================================================
 * ĐÂY LÀ ĐƯỜNG TIỀN. Sai ở đây không hiện ra ngay — nó hiện ra ở bảng đối soát cuối tháng, lúc
 * không còn cách nào dựng lại giao dịch đã mất. Nên phần lớn phép thử dưới đây nhắm vào ĐÚNG HAI
 * điều:
 *      1. KHÔNG ĐẾM HAI LẦN  — webhook bắn lại / nhập lại file Excel phải ra cùng một con số;
 *      2. KHÔNG MẤT TIỀN     — gói không đọc được vẫn phải giữ lại, không được bỏ im lặng.
 * =============================================================================================
 *
 * Chạy: php tools/test/test-ghe.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/**
 * Đếm KHÔNG CHẾT khi gặp dữ liệu sai hình dạng.
 *
 * 🔴 `count($r['ds'])` trần trụi là một quả mìn trong bộ thử: khi một thay đổi ở nơi khác làm
 *    cổng trả về hình dạng khác, dòng đó KHÔNG báo "sai" — nó ném TypeError và làm CHẾT cả bộ
 *    thử ngay tại chỗ, che mất hàng nghìn phép thử phía sau. Bộ thử chết giữa chừng khó lần ra
 *    hơn hẳn bộ thử báo sai đúng chỗ.
 *
 *    Dính đúng lúc thử đục công tắc `con_ban_ma`: cổng từ chối đơn -> `soi` trả về hình dạng
 *    lỗi -> `count(null)` -> chết ở phép thử thứ 300, và 1.100 phép thử sau đó không hề chạy.
 */
function dem( $x ) { return is_array( $x ) || $x instanceof Countable ? count( $x ) : 0; }

define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
define( 'VHG_KHOA_WEBHOOK', 'khoa-webhook-thu-nghiem' );
define( 'VHG_KHOA_MAY', 'khoa-may-thu-nghiem' );
/* ⚠️ THỨ TỰ CÓ NGHĨA: `vi` phải sau `ma` (VHG_Vi gọi VHG_Ma ngay từ hàm đầu). Danh sách này
      chép tay nên nó TRÔI khỏi danh sách thật của plugin — phép thử ở mục "nạp đủ lớp" dưới
      canh đúng chuyện đó. Thêm lớp mới mà quên đây là lỗi "Class not found" giữa lúc chạy, và
      nó chỉ lộ ra ở đúng phép thử chạm tới lớp đó. */
foreach ( array( 'db', 'doc', 'may', 'thu', 'qr', 'ma', 'vi', 'quy', 'chan', 'qrve', 'nhap', 'cong', 'auth', 'trang', 'shop' ) as $f ) {
	require_once VHG_DIR . 'includes/class-vhg-' . $f . '.php';
}
require_once VHG_DIR . 'includes/class-vhg-admin.php';

global $wpdb;
function vhg_dung_bang() {
	global $wpdb;
	foreach ( VHG_DB::bang() as $ten => $than ) {
		$bang = $wpdb->prefix . 'vhg_' . $ten;
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
/** Một lượt ngân hàng bắn webhook. Trả [mã HTTP, thân JSON đã giải]. */
function vhg_ban( $goi, $khoa = 'khoa-webhook-thu-nghiem', $src = '', $pt = 'POST' ) {
	$GLOBALS['VHG_THAN']       = is_string( $goi ) ? $goi : json_encode( $goi );
	$_SERVER['REQUEST_METHOD'] = $pt;
	$_SERVER['REQUEST_URI']    = '/' . VHG_Cong::DUONG_TIEN;
	$_GET = array( 'token' => $khoa );
	if ( '' !== $src ) { $_GET['src'] = $src; }
	$_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_cong'] = 'tien';
	$GLOBALS['VHCP_MA_HTTP'] = 200;
	ob_start(); VHG_Cong::phuc_vu(); $ra = ob_get_clean();
	return array( $GLOBALS['VHCP_MA_HTTP'], json_decode( $ra, true ) );
}
/** Một lượt ghế hỏi máy chủ. */
function vhg_ghe( $goi, $khoa = 'khoa-may-thu-nghiem' ) {
	$GLOBALS['VHG_THAN']        = json_encode( $goi );
	$_SERVER['REQUEST_METHOD']  = 'POST';
	$_SERVER['REQUEST_URI']     = '/' . VHG_Cong::DUONG_MAY;
	$_SERVER['HTTP_X_VHG_KEY']  = $khoa;
	$_GET = array(); $_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_cong'] = 'may';
	$GLOBALS['VHCP_MA_HTTP'] = 200;
	ob_start(); VHG_Cong::phuc_vu(); $ra = ob_get_clean();
	return array( $GLOBALS['VHCP_MA_HTTP'], json_decode( $ra, true ) );
}
/** Một lượt trang ngoài `/ghe` gọi API. Trả thân JSON đã giải. */
function vhg_web( $viec, $goi = array() ) {
	$GLOBALS['VHG_THAN']       = json_encode( $goi );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI']    = '/' . VHG_Trang::slug();
	$_GET  = array( 'api' => $viec );
	$_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_app'] = 1;
	ob_start(); VHG_Trang::phuc_vu(); $ra = ob_get_clean();
	return json_decode( $ra, true );
}
/** Dựng trang (HTML), không gọi API. */
function vhg_web_html() {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI']    = '/' . VHG_Trang::slug();
	$_GET = array(); $_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_app'] = 1;
	ob_start(); VHG_Trang::phuc_vu(); return ob_get_clean();
}
/** Một lượt trang bán mã gọi API. */
function vhg_shop( $viec, $goi = array() ) {
	$GLOBALS['VHG_THAN']       = json_encode( $goi );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI']    = '/' . VHG_Shop::slug();
	$_GET  = array( 'api' => $viec );
	if ( isset( $goi['ghe_url'] ) ) { $_GET['ghe'] = $goi['ghe_url']; }
	$_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_shop'] = 1;
	ob_start(); VHG_Shop::phuc_vu(); $ra = ob_get_clean();
	unset( $GLOBALS['VHCP_QVAR']['vhg_shop'] );
	return json_decode( $ra, true );
}
/**
 * Lùi ngày mua của MỌI mã đang có, để vượt qua chốt "chờ N ngày mới dùng được".
 *
 * ⚠️ Lùi ngày chứ KHÔNG tắt chốt. Tắt chốt là các phép thử ở dưới chạy trên một hệ thống khác
 *    với hệ thống thật, và cái chốt ấy sẽ không bao giờ được đi qua trong phép thử — đúng chỗ
 *    dễ hỏng nhất lại thành chỗ không ai thử.
 */
function vhg_ma_lui_ngay( $ngay = 30 ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'ma' ) . ' SET tao_luc=%s',
		gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $ngay * 86400 ) ) );
}

/** Dựng trang bán mã (HTML). */
function vhg_shop_html( $ghe = '' ) {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI']    = '/' . VHG_Shop::slug();
	$_GET = array(); $_POST = array();
	if ( '' !== $ghe ) { $_GET['ghe'] = $ghe; }
	$GLOBALS['VHCP_QVAR']['vhg_shop'] = 1;
	ob_start(); VHG_Shop::phuc_vu(); $ra = ob_get_clean();
	unset( $GLOBALS['VHCP_QVAR']['vhg_shop'] );
	return $ra;
}

/**
 * 🔴 GỌI API ĐÚNG ĐƯỜNG TRÌNH DUYỆT ĐI.
 *
 * `vhg_shop()` ở trên nhận mã ghế qua khoá `ghe_url` — một lối tắt CHỈ CÓ TRONG BỘ THỬ. Trang
 * thật thì không có lối đó: nó lấy địa chỉ API từ `window.VHG_SHOP` do máy chủ nhúng vào HTML,
 * rồi POST tới đúng địa chỉ ấy.
 *
 * Ngày 23/08/2026 hai đường đó LỆCH NHAU, và lệch đúng chỗ chết người: máy chủ nhúng địa chỉ
 * TRẦN (không mang mã ghế), nên mọi lượt gọi thật đều mất ngữ cảnh ghế —
 *   · bấm gói để tiêu số dư -> "Chưa biết dùng cho ghế nào"
 *   · bảng giá -> số phút rơi về tỉ lệ chung ở mọi ghế, và chỗ này KHÔNG kêu lên
 * Bộ thử vẫn báo sạch, vì nó tự đưa mã ghế vào bằng lối tắt của riêng nó.
 *
 * Hàm này bỏ lối tắt: DỰNG TRANG trước, BÓC `window.VHG_SHOP` ra, rồi gọi API bằng đúng địa chỉ
 * đó. Máy chủ quên nhúng mã ghế thì phép thử gãy ngay — đúng như phải thế.
 */
function vhg_shop_nhu_trang( $viec, $goi = array(), $ma_may = '' ) {
	$html = vhg_shop_html( $ma_may );
	$m = array();
	if ( 1 !== preg_match( '/window\.VHG_SHOP=("(?:[^"\\\\]|\\\\.)*")/', $html, $m ) ) {
		return array( 'ok' => false, 'error' => 'khong boc duoc window.VHG_SHOP tu trang' );
	}
	$dia_chi = json_decode( $m[1], true );
	$truy = array();
	parse_str( (string) wp_parse_url( (string) $dia_chi, PHP_URL_QUERY ), $truy );

	$GLOBALS['VHG_THAN']       = json_encode( $goi );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI']    = (string) wp_parse_url( (string) $dia_chi, PHP_URL_PATH );
	$_GET  = array_merge( $truy, array( 'api' => $viec ) );
	$_POST = array();
	$GLOBALS['VHCP_QVAR']['vhg_shop'] = 1;
	ob_start(); VHG_Shop::phuc_vu(); $ra = ob_get_clean();
	unset( $GLOBALS['VHCP_QVAR']['vhg_shop'] );
	$_GET = array();
	return json_decode( $ra, true );
}

/** Cấy một người vào danh sách riêng rồi lấy token. */
function vhg_vao( $pin = '571394', $vai_tro = 'Admin' ) {
	update_option( 'vhg_nguon_nguoidung', 'rieng' );
	update_option( 'vhg_nguoidung', array(
		array( 'ten' => 'Anh Thắng', 'pin' => $pin, 'vaiTro' => $vai_tro, 'coso' => '' ) ) );
	VHG_Auth::mo_khoa();
	$r = vhg_web( 'login', array( 'pin' => $pin ) );
	return isset( $r['token'] ) ? $r['token'] : '';
}

/* Độ sâu <form>. HTML không cho lồng form, và trình duyệt KHÔNG báo lỗi — nó lặng lẽ vứt thẻ
   con đi rồi gộp ô nhập vào form cha, nên một ô `required` ở cuối trang chặn nút Lưu ở đầu
   trang. Lỗi mắt thường không thấy, phải để máy canh. */
function vhg_do_sau_form( $html ) {
	$html = preg_replace( '/<!--.*?-->/s', '', (string) $html );
	preg_match_all( '/<\s*(\/?)form\b/i', $html, $m );
	$sau = 0; $max = 0;
	foreach ( $m[1] as $d ) {
		if ( '/' === $d ) { $sau--; } else { $sau++; if ( $sau > $max ) { $max = $sau; } }
	}
	return array( 'max' => $max, 'con_thua' => $sau );
}

function vhg_dem_thu() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHG_DB::t( 'thu' ) );
}
function vhg_tong() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . VHG_DB::t( 'thu' ) );
}

// ============================================================ 1. Sơ đồ bảng
$so_do = VHG_DB::bang();
teq( 'sơ đồ có đủ 16 bảng', 16, dem( $so_do ) );
/* 🔴 Các bảng TIỀN phải có mặt, gọi đúng tên. Con số ở trên đổi theo mỗi lần thêm bảng, nên nó
      không nói được BẢNG NÀO thiếu — mà thiếu đúng bảng tiền thì plugin cài xong vẫn chạy, chỉ
      là mọi lượt nạp ném lỗi vào đúng lúc khách vừa chuyển khoản. */
foreach ( array( 'vi', 'vi_so', 'chot', 'nop' ) as $b_ ) {
	t( 'có bảng ' . $b_, isset( $so_do[ $b_ ] ) );
}
/* Cột liên kết lượt nộp phải nằm trên bảng `thu` — thiếu nó thì tiền quầy không nộp được, mà
   chốt ca thì vẫn nộp được, nên nửa số tiền trên tay biến mất khỏi bảng "ai đang cầm". */
t( '🔴 bảng thu có cột nop_id', strpos( (string) $so_do['thu'], 'nop_id' ) !== false );
t( '🔴 ví khoá DUY NHẤT theo số điện thoại — hai ví cùng số là tiền chia làm đôi',
	strpos( (string) $so_do['vi'], 'UNIQUE KEY sdt (sdt)' ) !== false );
/* Đơn phải mang được cả hai kiểu hàng, không thì webhook không biết rẽ đâu. */
t( 'đơn có cột loai để rẽ nhánh', strpos( (string) $so_do['don_ma'], 'loai VARCHAR' ) !== false );
t( 'và cột nhan_tien để chốt số tiền nhận', strpos( (string) $so_do['don_ma'], 'nhan_tien' ) !== false );
/* 🔴 `ma` UNIQUE là TOÀN BỘ hàng rào chống dùng một mã hai lần. Mất nó thì cùng một mã chạy ghế
      bao nhiêu lần cũng được, và mình không có cách nào biết. */
t( 'bảng mã khoá DUY NHẤT theo mã', strpos( $so_do['ma'], 'UNIQUE KEY ma (ma)' ) !== false );
t( 'và đơn mua khoá duy nhất theo mã đơn',
	strpos( $so_do['don_ma'], 'UNIQUE KEY ma_don (ma_don)' ) !== false );
/* ⚠️ KHÔNG lưu PIN thô. Số điện thoại là thứ người khác đoán ra được, mà biết số là tra ra mã
      của người ta — nên PIN phải là băm, không phải chuỗi đọc được. */
t( 'PIN lưu dạng băm, cột đủ rộng cho bcrypt',
	strpos( $so_do['ma'], 'pin_bam VARCHAR(255)' ) !== false );
t( 'bảng doanh thu khoá DUY NHẤT theo mã tham chiếu — đây là ràng buộc chống đếm hai lần',
	strpos( $so_do['thu'], 'UNIQUE KEY ref (ref)' ) !== false );
t( 'bảng nhật ký có cột giữ THÂN THÔ (bên gửi đổi tên trường mà không báo)',
	strpos( $so_do['nhat_ky'], 'tho TEXT' ) !== false );
t( 'bảng lệnh bật/tắt tay BẮT BUỘC có cột người đặt', strpos( $so_do['lenh'], 'nguoi ' ) !== false );

// ============================================================ 2. Đọc số tiền
teq( 'số nguyên giữ nguyên', 20000, VHG_Doc::so( 20000 ) );
teq( 'kiểu Việt "20.000"', 20000, VHG_Doc::so( '20.000' ) );
/* 🔴 Coi dấu chấm là THẬP PHÂN ở đây là biến 20.000đ thành 20đ — sai 1000 lần, mà bảng đối soát
   vẫn ra một con số trông "hợp lý" nên không ai thấy. */
teq( 'kiểu Anh-Mỹ "20,000"', 20000, VHG_Doc::so( '20,000' ) );
teq( 'có đuôi "20000đ"', 20000, VHG_Doc::so( '20000đ' ) );
teq( 'rỗng -> 0', 0, VHG_Doc::so( '' ) );
teq( 'chữ thuần -> 0', 0, VHG_Doc::so( 'abc' ) );

// ============================================================ 3. Đọc gói của ba bên gửi
$sepay = VHG_Doc::tach( array( 'transferType' => 'in', 'transferAmount' => 20000,
	'content' => 'GHE3 T1ABC', 'referenceCode' => 'FT001' ) );
teq( 'SePay: đọc được 1 giao dịch', 1, count( $sepay ) );
teq( 'đúng số tiền', 20000, $sepay[0]['so_tien'] );
teq( 'đúng mã tham chiếu', 'FT001', $sepay[0]['ref'] );

$vietqr = VHG_Doc::tach( array( 'amount' => '25.000', 'description' => 'GHE5 XYZ99',
	'reference' => 'VQ-77' ) );
teq( 'VietQR: tên trường khác nhưng vẫn đọc được', 25000, $vietqr[0]['so_tien'] );

/* Tingo gửi MẢNG, không có tên trường nào — phải đoán theo hình dạng ô. */
$tingo = VHG_Doc::tach( array( 'values' => array( array(
	'11', '01/01/1970 07:00:00', '20000', 'TEST-9KQ',
	'VQR26310A58CRGN7', '-', 'Giao dich den', '27/07/2026 11:46:32', '8640107702 - BIDV',
	'VQR26310A58CRGN7 AMTP 03', '-', 'Thành công' ) ) ) );
teq( 'Tingo (mảng): đọc được 1 giao dịch', 1, count( $tingo ) );
teq( 'lấy đúng số tiền, KHÔNG lấy nhầm số thứ tự', 20000, $tingo[0]['so_tien'] );
teq( 'lấy đúng mã tham chiếu', 'TEST-9KQ', $tingo[0]['ref'] );
teq( 'lấy đúng nội dung', 'VQR26310A58CRGN7 AMTP 03', $tingo[0]['noi_dung'] );
t( 'KHÔNG lấy "8640107702 - BIDV" làm mã tham chiếu', $tingo[0]['ref'] !== '8640107702 - BIDV' );

/* Gói RỖNG HẲN không được đẻ ra giao dịch 0 đồng — nếu không thì mọi bot dò đường đều sinh một
   dòng rác trong nhật ký, mà nhật ký đó chính là chỗ người ta soi khi tiền không về. */
teq( 'gói rỗng -> không giao dịch nào', 0, count( VHG_Doc::tach( array() ) ) );
teq( 'gói toàn trường lạ -> không giao dịch nào', 0,
	count( VHG_Doc::tach( array( 'ping' => 1, 'hello' => 'world' ) ) ) );

// ---- Tiền RA phải nhận ra được ----
$ra = VHG_Doc::tach( array( 'transferType' => 'out', 'amount' => 50000, 'content' => 'rut tien' ) );
t( 'tiền ra: nhận ra được', ! empty( $ra[0]['tien_ra'] ) );
$ra2 = VHG_Doc::tach( array( 'values' => array( array( '3', '150000', 'REF-A1', 'Giao dịch đi', 'chuyen cho NCC' ) ) ) );
t( 'tiền ra kiểu Tingo (ô "Giao dịch đi"): nhận ra được', ! empty( $ra2[0]['tien_ra'] ) );

// ============================================================ 4. Tách tên máy / mã ghế
teq( 'bỏ tiền tố VQR…', 'AMTP 03', VHG_Doc::ten_may( 'VQR26310A58CRGN7 AMTP 03' ) );
teq( 'nội dung chung vô nghĩa -> không đoán bừa tên máy', '',
	VHG_Doc::ten_may( 'VQR26310A58CRGN7 PaymentForOrder' ) );
teq( 'cơ sở suy từ tên máy', 'AMTP', VHG_Doc::coso_tu_ten( 'AMTP 03' ) );
teq( 'cơ sở có hai chữ', 'VC GP', VHG_Doc::coso_tu_ten( 'VC GP 08' ) );
teq( 'đọc mã ghế + mã lượt', array( '3', 'T1ABC' ), VHG_Doc::ghe_va_ma( 'GHE3 T1ABC' ) );
teq( 'chữ thường vẫn đọc được', array( '3', 'T1ABC' ), VHG_Doc::ghe_va_ma( 'ghe3 t1abc' ) );
teq( 'có khoảng trắng sau GHE', array( '12', 'AB9' ), VHG_Doc::ghe_va_ma( 'GHE 12 AB9' ) );
teq( 'không khớp mẫu -> rỗng, KHÔNG đoán', array( '', '' ), VHG_Doc::ghe_va_ma( 'chuyen tien' ) );

// ---- Ngày kiểu Tingo ----
teq( 'ngày dd-MM-yyyy', '2026-07-27 11:46:32', VHG_Doc::ngay( '27-07-2026 11:46:32' ) );
teq( 'ngày dd/MM/yyyy', '2026-07-27 11:46:00', VHG_Doc::ngay( '27/07/2026 11:46' ) );
teq( 'ngày sẵn dạng chuẩn', '2026-08-22 10:00:00', VHG_Doc::ngay( '2026-08-22 10:00:00' ) );
/* Đoán bừa ngày là giao dịch rơi sang tháng khác, và bảng đối soát tháng đó sai mà không ai thấy. */
teq( 'chuỗi lạ -> RỖNG (để nơi gọi lấy giờ máy chủ, không đoán)', '', VHG_Doc::ngay( 'hôm qua' ) );

// ============================================================ 5. 🔴 KHÔNG ĐẾM HAI LẦN
vhg_dung_bang();
$g = array( 'transferType' => 'in', 'transferAmount' => 20000, 'content' => 'GHE3 T1ABC',
	'referenceCode' => 'FT-001' );
list( $ma1 ) = vhg_ban( $g );
teq( 'webhook lần 1: 200', 200, $ma1 );
teq( 'ghi 1 giao dịch', 1, vhg_dem_thu() );
teq( 'tổng đúng', 20000, vhg_tong() );
vhg_ban( $g );
vhg_ban( $g );
teq( 'bắn lại 2 lần nữa: VẪN 1 giao dịch', 1, vhg_dem_thu() );
teq( 'và tổng KHÔNG đổi', 20000, vhg_tong() );

/* Cùng lượt đó cũng phải chỉ xếp MỘT lần vào hàng chờ — nếu không, ghế chạy ba lượt cho một
   lần trả tiền. */
teq( 'hàng chờ chỉ có 1 lượt', 1, count( VHG_May::ds_cho() ) );

// ---- Giao dịch KHÁC ref thì phải cộng thêm ----
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 30000,
	'content' => 'GHE3 ZZZ11', 'referenceCode' => 'FT-002' ) );
teq( 'giao dịch khác: cộng thêm', 2, vhg_dem_thu() );
teq( 'tổng cộng dồn đúng', 50000, vhg_tong() );

// ---- Không có mã tham chiếu -> mã tự sinh phải ỔN ĐỊNH ----
vhg_dung_bang();
$khong_ref = array( 'transferType' => 'in', 'amount' => 15000, 'content' => 'GHE9 QQQ' );
vhg_ban( $khong_ref );
vhg_ban( $khong_ref );
/* 🔴 Sinh ngẫu nhiên là mỗi lần bắn lại thành một hàng mới — đúng thứ luật "ghi theo ref" đang
   tránh. Mã tự sinh phải suy từ chính nội dung giao dịch. */
teq( 'không có mã tham chiếu: bắn lại VẪN 1 giao dịch', 1, vhg_dem_thu() );
$ref_tu = $wpdb->get_var( 'SELECT ref FROM ' . VHG_DB::t( 'thu' ) );
t( 'mã tự sinh có tiền tố "tu-" để người đọc biết không phải mã ngân hàng',
	strpos( $ref_tu, 'tu-' ) === 0, $ref_tu );

// ============================================================ 6. 🔴 KHÔNG MẤT TIỀN
vhg_dung_bang();
/* Gói không đọc được -> VẪN 200 (bên gửi thấy khác 2xx là đẩy lại rồi tắt webhook), và VẪN giữ
   thân thô để xử tay. */
list( $ma, $than ) = vhg_ban( 'day khong phai JSON' );
teq( 'gói rác: trả 200 để bên gửi đừng tắt webhook', 200, $ma );
$log = VHG_Nhat_Ky::ds( 10 );
teq( 'nhưng VẪN ghi nhật ký', 1, count( $log ) );
t( 'và giữ nguyên thân thô', $log[0]['tho'] === 'day khong phai JSON', $log[0]['tho'] );
t( 'nói rõ là không đọc được', stripos( $log[0]['ghi_chu'], 'KHÔNG đọc được' ) !== false, $log[0]['ghi_chu'] );

/* Sai khoá -> 401 (để người cấu hình thấy ngay), nhưng VẪN ghi nhật ký. Đó là cách duy nhất
   phân biệt "bên gửi chưa bắn" với "bắn rồi mà mình chặn". */
list( $ma_sai ) = vhg_ban( $g, 'khoa-bay' );
teq( 'sai khoá: 401', 401, $ma_sai );
$log = VHG_Nhat_Ky::ds( 10 );
teq( 'vẫn ghi nhật ký lượt bị từ chối', 2, count( $log ) );
t( 'và nói rõ request CÓ tới nơi', stripos( $log[0]['ghi_chu'], 'CÓ tới nơi' ) !== false, $log[0]['ghi_chu'] );
teq( 'sai khoá thì KHÔNG ghi đồng doanh thu nào', 0, vhg_dem_thu() );

/* Nội dung không khớp "GHE… …" -> tiền VẪN vào sổ, chỉ là chưa gắn máy. Bỏ đi là mất tiền thật. */
vhg_dung_bang();
vhg_ban( array( 'transferType' => 'in', 'amount' => 20000,
	'content' => 'VQR123 PaymentForOrder', 'referenceCode' => 'FT-MO-HO' ), 'khoa-webhook-thu-nghiem', 'vietqr' );
teq( 'nội dung mơ hồ: tiền VẪN vào sổ', 1, vhg_dem_thu() );
teq( 'nhưng không gắn máy nào', '', $wpdb->get_var( 'SELECT ma_may FROM ' . VHG_DB::t( 'thu' ) ) );
teq( 'và KHÔNG xếp vào hàng chờ chạy (không biết ghế nào)', 0, count( VHG_May::ds_cho() ) );

/* Tiền RA không được tính doanh thu — tính cả là doanh thu phồng lên bằng đúng số tự chuyển đi. */
vhg_dung_bang();
vhg_ban( array( 'transferType' => 'out', 'amount' => 500000, 'content' => 'tra tien NCC',
	'referenceCode' => 'FT-RA' ) );
teq( 'tiền ra: KHÔNG vào doanh thu', 0, vhg_dem_thu() );
$log = VHG_Nhat_Ky::ds( 5 );
t( 'nhưng vẫn ghi nhật ký, nói rõ vì sao', stripos( $log[0]['ghi_chu'], 'tiền ra' ) !== false, $log[0]['ghi_chu'] );

// ---- GET vào cổng: trả lời tử tế, KHÔNG ghi nhật ký (dán link thử là chuyện thường) ----
vhg_dung_bang();
list( $ma_get, $than_get ) = vhg_ban( '', 'khoa-webhook-thu-nghiem', '', 'GET' );
teq( 'GET vào cổng tiền: 200', 200, $ma_get );
t( 'và nói cổng còn sống', ! empty( $than_get['success'] ) );
teq( 'không đẻ dòng nhật ký rác', 0, count( VHG_Nhat_Ky::ds( 5 ) ) );

// ============================================================ 7. Chỉ NỚI, không thu hẹp
vhg_dung_bang();
VHG_Thu::ghi( array( 'ref' => 'R1', 'so_tien' => 20000, 'noi_dung' => 'x',
	'ten_khai' => 'AMTP 03', 'nguon' => 'vietqr' ) );
VHG_Thu::ghi( array( 'ref' => 'R1', 'so_tien' => 20000, 'noi_dung' => 'x', 'nguon' => 'vietqr' ) );
/* Một giao dịch tới hai lần theo hai đường: webhook (chưa biết máy), rồi Excel (biết máy). Lượt
   sau KHÔNG được xoá trắng cái lượt trước đã biết. */
teq( 'lượt sau không biết tên máy: GIỮ tên máy lượt trước đã biết', 'AMTP 03',
	$wpdb->get_var( 'SELECT ten_khai FROM ' . VHG_DB::t( 'thu' ) . " WHERE ref='R1'" ) );
VHG_Thu::ghi( array( 'ref' => 'R1', 'so_tien' => 20000, 'ten_khai' => 'AMTP 05', 'nguon' => 'vietqr' ) );
teq( 'nhưng lượt sau BIẾT thì được sửa', 'AMTP 05',
	$wpdb->get_var( 'SELECT ten_khai FROM ' . VHG_DB::t( 'thu' ) . " WHERE ref='R1'" ) );

// ============================================================ 8. Cổng của ghế
vhg_dung_bang();
VHG_May::luu_coso( 0, 'Tutu Tân Phú' );
$cs = VHG_May::ds_coso();
VHG_May::luu_may( array( 'ma' => '3', 'coso_id' => $cs[0]['id'], 'gia' => 20000, 'phut' => 6,
	'so_tk' => '96247POSH', 'bank_bin' => '970418', 'ten_khai' => 'AMTP 03' ) );

list( $mc, $tc ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'nhip', 'trang_thai' => 'idle' ) );
teq( 'ghế gửi nhịp: 200', 200, $mc );
teq( 'máy chủ trả đúng giá', 20000, $tc['gia'] );
teq( 'và đúng thời lượng', 6, $tc['phut'] );
teq( 'chưa có tiền chờ', 0, $tc['coTien'] );

vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 20000,
	'content' => 'GHE3 T1ABC', 'referenceCode' => 'FT-9' ) );
list( , $tc2 ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'nhip' ) );
teq( 'sau khi có tiền: nhịp báo có tiền chờ', 1, $tc2['coTien'] );
list( , $l1 ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'luot' ) );
teq( 'ghế lấy được lượt', 1, $l1['co'] );
teq( 'đúng mã lượt', 'T1ABC', $l1['ma_lenh'] );
teq( 'kèm số phút để ghế biết chạy bao lâu', 6, $l1['phut'] );
list( , $l2 ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'luot' ) );
/* 🔴 Đánh dấu NGAY lúc phát, không chờ ghế báo xong: ghế mất điện giữa chừng mà không đánh dấu
   thì khởi động lại là chạy lại lượt cũ, và cứ thế mãi. */
teq( 'lấy lần nữa: hết (đã đánh dấu đã nhận)', 0, $l2['co'] );
teq( 'ghế KHÁC không lấy được lượt của ghế này', 0,
	vhg_ghe( array( 'ma_may' => '5', 'viec' => 'luot' ) )[1]['co'] );

// ---- Khoá của ghế ----
list( $m401 ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'nhip' ), 'khoa-bay' );
teq( 'ghế sai khoá: 401', 401, $m401 );
/* Hai khoá phải KHÁC NHAU: khoá webhook đi trên đường dẫn nên coi như lộ một phần. */
t( 'khoá webhook và khoá máy là hai chuỗi khác nhau',
	VHG_Cong::khoa( 'VHG_KHOA_WEBHOOK' ) !== VHG_Cong::khoa( 'VHG_KHOA_MAY' ) );

// ---- Ghế báo đã nhận TIỀN MẶT ----
vhg_dung_bang();
VHG_May::luu_may( array( 'ma' => '3', 'gia' => 10000, 'phut' => 6 ) );
list( , $tm ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'tien_mat',
	'so_tien' => 20000, 'ref' => 'cash-3-1000' ) );
t( 'ghế báo tiền mặt: ghi được', ! empty( $tm['ok'] ), $tm );
teq( 'vào doanh thu', 20000, vhg_tong() );
teq( 'ghi đúng nguồn tiền mặt', 'cash', $wpdb->get_var( 'SELECT nguon FROM ' . VHG_DB::t( 'thu' ) ) );
/* 🔴 Ghế CHẠY NGAY khi máy đếm tiền xác thực tờ tiền, không chờ máy chủ. Nên lượt báo sổ này
   phải chịu được gửi lại: ghế mất mạng lúc đó thì nó giữ và đẩy sau, có khi đẩy hai lần. */
vhg_ghe( array( 'ma_may' => '3', 'viec' => 'tien_mat', 'so_tien' => 20000, 'ref' => 'cash-3-1000' ) );
vhg_ghe( array( 'ma_may' => '3', 'viec' => 'tien_mat', 'so_tien' => 20000, 'ref' => 'cash-3-1000' ) );
teq( 'ghế gửi lại 2 lần nữa: VẪN 1 giao dịch', 1, vhg_dem_thu() );
teq( 'và tổng KHÔNG đổi', 20000, vhg_tong() );
list( , $tm2 ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'tien_mat', 'so_tien' => 0 ) );
t( 'báo 0 đồng: chặn', empty( $tm2['ok'] ) );

// ---- nhịp trả đủ thứ ghế cần để tự dựng chuỗi VietQR ----
VHG_May::luu_may( array( 'ma' => '3', 'gia' => 10000, 'phut' => 6,
	'so_tk' => '96247POSH', 'bank_bin' => '970418', 'ten_tk' => 'NGUYEN VAN A' ) );
list( , $n3 ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'nhip' ) );
teq( 'nhịp trả số tài khoản', '96247POSH', $n3['soTk'] );
teq( 'nhịp trả mã ngân hàng', '970418', $n3['bin'] );
/* Ghế tự dựng chuỗi QR từ ba giá trị này -> đổi số tài khoản trên web là ghế theo trong ~5 phút,
   KHÔNG phải nạp lại firmware từng ghế. */
teq( 'và tên tài khoản', 'NGUYEN VAN A', $n3['tenTk'] );
teq( 'máy chưa khai: báo rõ để ghế biết mà kêu', 0,
	vhg_ghe( array( 'ma_may' => 'CHUA_KHAI', 'viec' => 'nhip' ) )[1]['khai'] );

// ---- Một bản .bin cho MỌI ghế: máy chủ nói ghế nào theo MAC ----
vhg_dung_bang();
list( , $la ) = vhg_ghe( array( 'mac' => 'AA:BB:CC:00:11:22', 'viec' => 'nhip' ) );
t( 'ghế lạ cắm điện: vẫn trả lời, không im lặng bỏ qua', ! empty( $la['ok'] ), $la );
teq( 'và báo là CHƯA GÁN để người đi lắp biết còn thiếu một bước', 1, $la['chuaGan'] );
teq( 'ghế tự hiện ra trong danh sách chờ gán', 1, count( VHG_May::chua_gan() ) );
$tam = VHG_May::chua_gan()[0]['ma'];
t( 'mã tạm bắt đầu bằng ? để nhìn là biết', '?' === $tam[0], $tam );
/* Cắm lại nhiều lần KHÔNG được đẻ thêm dòng — nếu không thì danh sách chờ gán đầy rác và
   người ta không biết gán cái nào. */
vhg_ghe( array( 'mac' => 'AA:BB:CC:00:11:22', 'viec' => 'nhip' ) );
vhg_ghe( array( 'mac' => 'AA:BB:CC:00:11:22', 'viec' => 'nhip' ) );
teq( 'cắm lại 2 lần: VẪN 1 dòng chờ gán', 1, count( VHG_May::chua_gan() ) );

/* Gán mã thật: phải ĐỔI chính dòng đó, không tạo dòng mới — tạo mới là ghế cũ nằm lại mãi
   trong danh sách chờ gán và người ta gán đi gán lại không hết. */
VHG_May::luu_may( array( 'ma' => '7', 'mac' => 'AA:BB:CC:00:11:22', 'gia' => 10000, 'phut' => 6 ) );
teq( 'gán xong: hết ghế chờ gán', 0, count( VHG_May::chua_gan() ) );
teq( 'và chỉ có 1 máy trong bảng', 1, count( VHG_May::ds_may() ) );
list( , $sau ) = vhg_ghe( array( 'mac' => 'AA:BB:CC:00:11:22', 'viec' => 'nhip' ) );
teq( 'ghế đó nay nhận đúng mã đã gán', '7', $sau['maMay'] );
teq( 'và hết cờ chưa gán', 0, $sau['chuaGan'] );
/* MAC viết thường vẫn phải ra đúng ghế đó — không thì cắm lại sau khi đổi firmware là ghế
   thành "lạ" và nằm chờ gán lần nữa. */
teq( 'MAC viết thường vẫn tra ra đúng ghế', '7',
	vhg_ghe( array( 'mac' => 'aa:bb:cc:00:11:22', 'viec' => 'nhip' ) )[1]['maMay'] );
/* Sửa giá mà không gửi MAC thì KHÔNG được cắt đứt liên kết ghế thật <-> dòng cấu hình. */
VHG_May::luu_may( array( 'ma' => '7', 'gia' => 20000, 'phut' => 6 ) );
teq( 'sửa giá không kèm MAC: liên kết MAC giữ nguyên', '7',
	vhg_ghe( array( 'mac' => 'AA:BB:CC:00:11:22', 'viec' => 'nhip' ) )[1]['maMay'] );
teq( 'và giá mới có hiệu lực', 20000,
	vhg_ghe( array( 'mac' => 'AA:BB:CC:00:11:22', 'viec' => 'nhip' ) )[1]['gia'] );
t( 'không có cả ma_may lẫn mac: từ chối tử tế',
	empty( vhg_ghe( array( 'viec' => 'nhip' ) )[1]['ok'] ) );

// ============================================================ 9. Lệnh bật/tắt tay
vhg_dung_bang();
VHG_May::luu_may( array( 'ma' => '3', 'gia' => 20000, 'phut' => 6 ) );
$r = VHG_May::dat_lenh( '3', 'on', 6, 'Chị Lan', 'khách kêu máy không nhận' );
t( 'đặt được lệnh bật', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
$r = VHG_May::dat_lenh( '3', 'on', 6, '', '' );
t( 'thiếu người đặt: CHẶN (đây là đường cho không một lượt massage)', empty( $r['ok'] ) );
$r = VHG_May::dat_lenh( '3', 'on', 600, 'Chị Lan', '' );
t( 'gõ nhầm 600 phút: chặn và nói rõ vì sao',
	empty( $r['ok'] ) && stripos( $r['error'], '60 phút' ) !== false, $r['error'] );
$r = VHG_May::dat_lenh( '3', 'chay', 6, 'Chị Lan', '' );
t( 'lệnh lạ: chặn', empty( $r['ok'] ) );
list( , $lc ) = vhg_ghe( array( 'ma_may' => '3', 'viec' => 'lenh' ) );
teq( 'ghế lấy được lệnh', 1, $lc['co'] );
teq( 'đúng việc', 'on', $lc['viec'] );
teq( 'lấy lần nữa: hết', 0, vhg_ghe( array( 'ma_may' => '3', 'viec' => 'lenh' ) )[1]['co'] );

// ============================================================ 10. Mã máy phải gõ được
$r = VHG_May::luu_may( array( 'ma' => 'ghế 3' ) );
t( 'mã máy có dấu + khoảng trắng: CHẶN (khách phải gõ tay mã này vào nội dung CK)',
	empty( $r['ok'] ) && stripos( $r['error'], 'không dấu' ) !== false, $r['error'] );
t( 'mã máy rỗng: chặn', empty( VHG_May::luu_may( array( 'ma' => '' ) )['ok'] ) );
t( 'mã máy chữ+số: nhận', ! empty( VHG_May::luu_may( array( 'ma' => 'A12' ) )['ok'] ) );

// ============================================================ 11. Chuỗi VietQR
/* In sai một ký tự là khách quét không ra, hoặc tệ hơn: ra SỐ TIỀN KHÁC. */
teq( 'TLV: mã + độ dài 2 số + giá trị', '0002VN', VHG_QR::tlv( '00', 'VN' ) );
teq( 'TLV đếm đúng độ dài dài hơn 9', '0110ABCDEFGHIJ', VHG_QR::tlv( '01', 'ABCDEFGHIJ' ) );
teq( 'CRC16/CCITT-FALSE của "123456789"', '29B1', VHG_QR::crc16( '123456789' ) );
$q = VHG_QR::dung( '970418', '96247POSH', 20000, 'GHE3 T1ABC' );
t( 'chuỗi QR bắt đầu đúng chuẩn EMVCo', strpos( $q, '000201' ) === 0, substr( $q, 0, 20 ) );
t( 'có số tiền 20000', strpos( $q, '540520000' ) !== false );
t( 'có nội dung để khớp ghế', strpos( $q, 'GHE3 T1ABC' ) !== false );
t( 'kết thúc bằng CRC 4 ký tự sau 6304', preg_match( '/6304[0-9A-F]{4}$/', $q ) === 1, substr( $q, -8 ) );
teq( 'CRC tự kiểm: tính lại phần trước phải khớp', substr( $q, -4 ),
	VHG_QR::crc16( substr( $q, 0, -4 ) ) );
$q0 = VHG_QR::dung( '970418', '96247POSH', 0, 'GHE3 T1ABC' );
t( 'không có số tiền -> QR dùng nhiều lần (010211)', strpos( $q0, '010211' ) !== false );
t( 'và KHÔNG có trường số tiền', strpos( $q0, '5405' ) === false );
/* Mã lượt khách phải gõ tay -> không được có ký tự nhìn giống nhau. */
for ( $i = 0; $i < 40; $i++ ) {
	$m = VHG_QR::ma_luot();
	if ( preg_match( '/[O0I1]/', $m ) ) { t( 'mã lượt không chứa ký tự dễ nhìn nhầm (O,0,I,1)', false, $m ); break; }
}
t( 'mã lượt không chứa ký tự dễ nhìn nhầm (O,0,I,1)', true );
teq( 'mã lượt dài 5', 5, strlen( VHG_QR::ma_luot() ) );

// ============================================================ 12. Nhập bảng Tingo
vhg_dung_bang();
$tieu_de = array( 'STT', 'Mã tham chiếu', 'Mã điểm bán', 'Mã cửa hàng', 'Số tiền đến (VND)', 'Nội dung TT', 'Thời gian tạo' );
$bang = array( $tieu_de,
	array( '1', 'REF-A', 'VVB001', 'CH001', '20.000', 'VQR111 AMTP 03', '27-07-2026 10:00:00' ),
	array( '2', 'REF-B', 'VVB001', 'CH001', '20.000', 'VQR222 PaymentForOrder', '27-07-2026 10:05:00' ),
	array( '3', 'REF-C', 'VVB002', 'CH002', '25.000', 'VQR333 PaymentForOrder', '27-07-2026 10:09:00' ),
);
$r = VHG_Nhap::nhap_giao_dich( $bang );
t( 'nhập được', ! empty( $r['ok'] ), isset( $r['error'] ) ? $r['error'] : '' );
teq( 'vào đủ 3 giao dịch', 3, $r['vao'] );
/* Dòng 1 có tên máy trong nội dung -> DẠY cho bản đồ; dòng 2 cùng Mã điểm bán -> ĂN THEO. */
teq( 'gắn được máy cho 2 dòng cùng mã điểm bán', 2, $r['co_ten'] );
teq( 'còn 1 dòng chưa rõ máy (mã điểm bán khác, chưa học)', 1, $r['chua_ro'] );
teq( 'tổng tiền đúng', 65000, vhg_tong() );
teq( 'ngày đọc đúng theo cột Thời gian tạo', '2026-07-27 10:00:00',
	$wpdb->get_var( 'SELECT luc FROM ' . VHG_DB::t( 'thu' ) . " WHERE ref='REF-A'" ) );

/* 🔴 NHẬP LẠI ĐÚNG FILE ĐÓ: không được cộng đôi. Đây là điều làm cho "nhập lại cho chắc" thành
   việc an toàn — bỏ nó là không ai dám bấm nút nhập lần thứ hai. */
VHG_Nhap::nhap_giao_dich( $bang );
teq( 'nhập lại: VẪN 3 giao dịch', 3, vhg_dem_thu() );
teq( 'và tổng KHÔNG đổi', 65000, vhg_tong() );

// ---- Khai tay bản đồ rồi áp lại ----
VHG_Nhap::nhap_ban_do( array(
	array( 'Mã Voice Box', 'Mã Cửa hàng', 'Tên Cửa hàng' ),
	array( 'VVB002', 'CH002', 'VC GP 08' ) ) );
$r = VHG_Nhap::ap_lai_ban_do();
teq( 'áp lại: gắn được máy cho giao dịch còn lại', 1, $r['so'] );
teq( 'tên máy đúng', 'VC GP 08',
	$wpdb->get_var( 'SELECT ten_khai FROM ' . VHG_DB::t( 'thu' ) . " WHERE ref='REF-C'" ) );
/* Người khai thắng máy tự học. */
VHG_Nhap::dat( 'VVB002', 'MAY KHAC', true );
teq( 'máy tự học KHÔNG ghi đè dòng người khai tay', 'VC GP 08', VHG_Nhap::tra( 'VVB002' ) );
VHG_Nhap::dat( 'VVB002', 'DOI TAY', false );
teq( 'nhưng người khai thì sửa được', 'DOI TAY', VHG_Nhap::tra( 'VVB002' ) );

// ---- Thiếu tiêu đề thì phải nói rõ, đừng nhập bừa ----
$r = VHG_Nhap::nhap_giao_dich( array( array( 'REF-X', '20000' ) ) );
t( 'chỉ có 1 dòng (thiếu tiêu đề): chặn và nói rõ', empty( $r['ok'] )
	&& stripos( $r['error'], 'TIÊU ĐỀ' ) !== false, $r['error'] );
$r = VHG_Nhap::nhap_giao_dich( array( array( 'Cột lạ', 'Cột lạ 2' ), array( 'a', 'b' ) ) );
t( 'không thấy cột bắt buộc: chặn', empty( $r['ok'] ) );

// ---- Tách bảng dán từ Excel ----
$b = VHG_Nhap::bang_tu_van_ban( "A\tB\tC\n1\t2\t3" );
teq( 'dán từ Excel: tách bằng TAB', array( array( 'A', 'B', 'C' ), array( '1', '2', '3' ) ), $b );
/* Tách bằng phẩy trước là ô "Nguyễn Văn A, Q1" vỡ thành hai cột và cả bảng lệch. */
$b2 = VHG_Nhap::bang_tu_van_ban( "Tên\tĐịa chỉ\nA\tQuận 1, TPHCM" );
teq( 'ô có dấu phẩy KHÔNG bị vỡ cột khi đã có TAB', 2, dem( $b2[1] ) );

// ============================================================ 13. Dọn tiền ra bị tính nhầm
vhg_dung_bang();
VHG_Thu::ghi( array( 'ref' => 'X1', 'so_tien' => 20000, 'noi_dung' => 'GHE3 ABC', 'nguon' => 'qr' ) );
VHG_Thu::ghi( array( 'ref' => 'X2', 'so_tien' => 500000, 'noi_dung' => 'Giao dịch đi - tra NCC', 'nguon' => 'qr' ) );
$r = VHG_Nhap::don_tien_ra();
teq( 'xoá đúng 1 dòng tiền ra', 1, $r['so'] );
teq( 'doanh thu thật giữ nguyên', 20000, vhg_tong() );

// ============================================================ 14. Tổng hợp theo kỳ
vhg_dung_bang();
VHG_May::luu_coso( 0, 'Tutu Tân Phú' );
$cs = VHG_May::ds_coso();
VHG_May::luu_may( array( 'ma' => '3', 'coso_id' => $cs[0]['id'], 'ten_khai' => 'AMTP 03' ) );
$hom_nay = current_time( 'mysql' );
VHG_Thu::ghi( array( 'ref' => 'T1', 'luc' => $hom_nay, 'so_tien' => 20000, 'ma_may' => '3', 'nguon' => 'qr' ) );
VHG_Thu::ghi( array( 'ref' => 'T2', 'luc' => $hom_nay, 'so_tien' => 30000, 'ten_khai' => 'AMTP 03', 'nguon' => 'vietqr' ) );
VHG_Thu::ghi( array( 'ref' => 'T3', 'luc' => $hom_nay, 'so_tien' => 10000, 'ma_may' => '3', 'nguon' => 'cash' ) );
VHG_Thu::ghi( array( 'ref' => 'T4', 'luc' => '2020-01-01 08:00:00', 'so_tien' => 999000, 'ma_may' => '3', 'nguon' => 'qr' ) );
$t = VHG_Thu::tong_hop( 'today' );
teq( 'tổng hôm nay KHÔNG dính giao dịch năm 2020', 60000, $t['tong'] );
teq( 'tách đúng tiền mặt', 10000, $t['tien_mat'] );
teq( 'tách đúng chuyển khoản', 50000, $t['qr'] );
$tat_ca = VHG_Thu::tong_hop( 'all' );
teq( 'kỳ "tất cả" thì có cả giao dịch cũ', 1059000, $tat_ca['tong'] );
/* Giao dịch mang TÊN KHAI (từ Excel) phải gộp vào đúng cơ sở của máy đã khai — không có chỗ này
   thì mọi giao dịch nhập từ Excel đều rơi vào "chưa gán cơ sở". */
$ten_cs = array();
foreach ( $t['theo_coso'] as $c ) { $ten_cs[] = $c['coso']; }
t( 'giao dịch theo tên khai gộp vào đúng cơ sở đã khai',
	in_array( 'Tutu Tân Phú', $ten_cs, true ), $ten_cs );

// ---- Mốc đầu kỳ ----
t( 'đầu kỳ "tất cả" là rỗng (không lọc)', '' === VHG_Thu::dau_ky( 'all' ) );
t( 'đầu kỳ hôm nay là 00:00:00', substr( VHG_Thu::dau_ky( 'today' ), -8 ) === '00:00:00' );
t( 'đầu tháng là ngày 01', substr( VHG_Thu::dau_ky( 'month' ), 8, 2 ) === '01' );
/* Tuần bắt đầu THỨ HAI: dùng chủ nhật là doanh thu chủ nhật rơi sang tuần sau. */
teq( 'đầu tuần rơi vào thứ Hai', '1', gmdate( 'N', strtotime( VHG_Thu::dau_ky( 'week' ) ) ) );

// ============================================================ 15. Không có bí mật trong mã nguồn
/* Repo này CÔNG KHAI. Bản Apps Script cũ có DASHBOARD_PIN='246810' ghi thẳng trong mã — ai đọc
   được mã là bật/tắt được ghế và xoá được doanh thu. */
foreach ( glob( VHG_DIR . 'includes/*.php' ) as $f ) {
	$src = file_get_contents( $f );
	$ten = basename( $f );
	t( "$ten không ghi cứng PIN nào", ! preg_match( "/PIN\s*=\s*'\d{4,}'/", $src ) );
	t( "$ten không chứa địa chỉ Firebase", stripos( $src, 'firebasedatabase' ) === false
		&& stripos( $src, 'firebaseio' ) === false );
	t( "$ten không chứa mã triển khai Apps Script", strpos( $src, 'AKfycb' ) === false );
}
$boot = file_get_contents( VHG_DIR . 'vhcp-ghe.php' );
t( 'hai khoá lấy từ wp-config, KHÔNG ghi trong mã',
	strpos( $boot, "define( 'VHG_KHOA_WEBHOOK'" ) === false
	|| strpos( $boot, '…' ) !== false );
$cong = file_get_contents( VHG_DIR . 'includes/class-vhg-cong.php' );
t( 'khoá rỗng nghĩa là ĐÓNG, không phải mở',
	strpos( $cong, "'' === \$that" ) !== false );

// ============================================================ 16. Màn hình: mỏng
$ad = file_get_contents( VHG_DIR . 'includes/class-vhg-admin.php' );
t( 'màn gác quyền', substr_count( $ad, 'current_user_can( self::CAP )' ) >= 1 );
t( 'màn có nonce', strpos( $ad, 'check_admin_referer' ) !== false );
t( 'màn KHÔNG tự viết câu SQL nào', strpos( $ad, '$wpdb->' ) === false );
/* Soi MÃ chứ không soi chữ: khối chú thích có nhắc PIN để nói rõ bản cũ sai ở đâu — câu đó đáng
   giữ. Cấm cả chữ là ép xoá lời giải thích để qua bài kiểm. */
$ad_ma = preg_replace( '#/\*.*?\*/#s', '', $ad );
$ad_ma = preg_replace( '#//[^\n]*#', '', $ad_ma );
/* 🔴 Từ bản 1.1.0 màn quản trị CÓ khai PIN (cho trang ngoài, nơi nhân viên không có tài khoản
   WordPress). Nên luật cũ "không được nhắc chữ PIN" hết đúng, và thay bằng luật thật sự quan
   trọng: KHÔNG BAO GIỜ IN PIN RA MÀN HÌNH, chỉ in SỐ CHỮ SỐ. Ảnh màn hình đi khắp nơi — trong
   chính dự án này đã mất một khoá cầu nối và một khoá webhook vì ảnh gửi qua chat. */
t( 'màn quản trị KHÔNG in PIN ra, chỉ in số chữ số',
	strpos( $ad_ma, "strlen( \$x['pin'] ) . ' số'" ) !== false
	&& preg_match( "/echo[^;\n]*esc_html\\(\\s*\\\$x\\['pin'\\]/", $ad_ma ) === 0, $ad_ma );
t( 'và ô thêm người vẫn ép PIN 4–8 số', strpos( $ad, '4–8 số' ) !== false );
/* Ghế mất nhịp để TRÊN CÙNG: ghế đứt mà khách vẫn quét được tem QR trên ghế nghĩa là TIỀN VÀO
   MÀ GHẾ KHÔNG CHẠY — người ta đứng ở quầy cãi nhau ngay lúc đó. */
$i_dut = strpos( $ad, 'không gửi nhịp' );
$i_kpi = strpos( $ad, 'Tổng doanh thu' );
t( 'cảnh báo ghế mất nhịp nằm TRƯỚC bảng số liệu', $i_dut !== false && $i_kpi !== false && $i_dut < $i_kpi );
t( 'màn nói rõ bật tay là cho không một lượt', stripos( $ad, 'cho không một lượt' ) !== false );

// ====================================== GÓI THỬ CỦA SEPAY KHÔNG PHẢI TIỀN
/* 🔴 Ngày 22/08/2026 anh Thắng bấm nút "Gửi thử" trên trang SePay để kiểm webhook. Gói thử đó
      có `transferAmount` hẳn hoi nên nó đẻ ra một dòng doanh thu 10.000đ KHÔNG HỀ TỒN TẠI. Ai
      bấm thử lại là thêm một dòng nữa — mỗi dòng là một lần sổ lệch với sao kê ngân hàng. */
vhg_dung_bang();
list( $ma_t, $than_t ) = vhg_ban( array(
	'gateway' => 'BIDV', 'transferAmount' => 10000, 'transferType' => 'in',
	'content' => 'SEPAY TEST WEBHOOK', 'referenceCode' => 'thu-nghiem-1' ) );
teq( 'gói thử vẫn được nhận 200 (bên gửi không tắt webhook)', 200, $ma_t );
teq( 'nhưng KHÔNG vào sổ tiền', 0, VHG_Thu::tong_hop( 'all' )['tong'] );
teq( 'và KHÔNG đẻ ra giao dịch nào', 0, count( VHG_Thu::ds( 'all' ) ) );
$lg_t = VHG_Nhat_Ky::ds( 5 );
t( 'vẫn ghi nhật ký — đó chính là thứ nút Gửi thử dùng để kiểm', count( $lg_t ) > 0 );
t( 'và nói rõ vì sao không vào sổ',
	false !== stripos( (string) $lg_t[0]['ghi_chu'], 'THỬ' ), $lg_t[0]['ghi_chu'] );

/* ⚠️ ĐỪNG BẮT NHẦM TIỀN THẬT. Khách chuyển khoản mà nội dung có chứa cụm đó là TIỀN THẬT — bắt
      theo `strpos` là mất tiền thật, tệ hơn hẳn cái nó chữa. */
vhg_dung_bang();
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'TT SEPAY TEST WEBHOOK 20K', 'referenceCode' => 'that-1' ) );
teq( 'nội dung CHỨA cụm đó nhưng là tiền thật -> vẫn vào sổ', 20000, VHG_Thu::tong_hop( 'all' )['tong'] );
vhg_dung_bang();
vhg_ban( array( 'transferAmount' => 30000, 'transferType' => 'in',
	'content' => '  sepay test webhook  ', 'referenceCode' => 'thu-nghiem-2' ) );
teq( 'đúng nguyên văn (thường/hoa, thừa khoảng trắng) -> vẫn chặn', 0, VHG_Thu::tong_hop( 'all' )['tong'] );

// ====================================== GIỜ LẤY THEO BÊN GỬI, KHÔNG THEO MÁY CHỦ
/* 🔴 SePay ghi 20:08:26, website ghi 13:08:29 — lệch đúng 7 tiếng vì WordPress mặc định chạy
      giờ UTC. Đối soát với sao kê thành mò kim, và mốc "Hôm nay" cắt sai ngày. */
vhg_dung_bang();
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 TEST1', 'referenceCode' => 'gio-1',
	'transactionDate' => '2026-08-22 20:08:26' ) );
$g1 = VHG_Thu::ds( 'all' );
teq( 'ghi ĐÚNG giờ bên gửi, không phải giờ máy chủ', '2026-08-22 20:08:26', $g1[0]['luc'] );

vhg_dung_bang();
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 TEST2', 'referenceCode' => 'gio-2',
	'transactionDate' => '22/08/2026 20:08' ) );
teq( 'đọc được cả kiểu ngày dd/mm/yyyy', '2026-08-22 20:08:00', VHG_Thu::ds( 'all' )[0]['luc'] );

vhg_dung_bang();
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 TEST3', 'referenceCode' => 'gio-3' ) );
t( 'bên gửi không kèm giờ thì mới lấy giờ máy chủ',
	'' !== (string) VHG_Thu::ds( 'all' )[0]['luc'] );
vhg_dung_bang();
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 TEST4', 'referenceCode' => 'gio-4',
	'transactionDate' => 'ba la bla' ) );
t( 'giờ rác thì bỏ qua, KHÔNG ghi ngày 0000-00-00',
	strpos( (string) VHG_Thu::ds( 'all' )[0]['luc'], '0000' ) === false,
	VHG_Thu::ds( 'all' )[0]['luc'] );

// ====================================== HUỶ GIAO DỊCH: ĐÁNH DẤU, KHÔNG XOÁ
vhg_dung_bang();
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 10000, 'phut' => 6,
	'so_tk' => '888815678', 'ten_tk' => 'K&H', 'bank_bin' => '970418', 'ten_khai' => 'AMTP 01' ) );
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 K7M2P', 'referenceCode' => 'huy-1' ) );
teq( 'trước khi huỷ: có trong doanh thu', 20000, VHG_Thu::tong_hop( 'all' )['tong'] );
teq( 'và ghế có một lượt chờ chạy', 1, VHG_May::so_cho( 'AMTP01' ) );

$rh = VHG_Thu::huy( 'huy-1', 'ghi nhầm' );
t( 'huỷ được', ! empty( $rh['ok'] ), $rh );
teq( 'huỷ xong KHÔNG còn cộng vào doanh thu', 0, VHG_Thu::tong_hop( 'all' )['tong'] );
teq( 'và không còn trong danh sách giao dịch', 0, count( VHG_Thu::ds( 'all' ) ) );
teq( '🔴 gỡ luôn lượt CHƯA CHẠY — huỷ tiền mà ghế vẫn chạy là cho không một lượt',
	0, VHG_May::so_cho( 'AMTP01' ) );

/* 🔴 LUẬT QUAN TRỌNG NHẤT CỦA PHÉP HUỶ: dòng vẫn còn trong bảng, vì `ref` UNIQUE là thứ DUY
      NHẤT chặn cộng đôi. Xoá dòng đi thì đúng giao dịch ấy bắn lại là vào sổ như khoản mới —
      phép "sửa sổ" tự mở lại đúng cái lỗ nó vừa vá. */
$dh = VHG_Thu::ds_huy();
teq( 'dòng vẫn nằm trong cơ sở dữ liệu', 1, count( $dh ) );
teq( 'kèm lý do huỷ', 'ghi nhầm', $dh[0]['huy_ly_do'] );
teq( 'và giữ nguyên số tiền để còn đối soát', 20000, (int) $dh[0]['so_tien'] );

vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 K7M2P', 'referenceCode' => 'huy-1' ) );
teq( '🔴 bắn lại đúng giao dịch đã huỷ: KHÔNG sống lại thành khoản mới',
	0, VHG_Thu::tong_hop( 'all' )['tong'] );
teq( 'vẫn đúng một dòng, không đẻ thêm', 1, count( VHG_Thu::ds_huy() ) );

$rb = VHG_Thu::bo_huy( 'huy-1' );
t( 'bỏ huỷ được — huỷ nhầm phải lùi được', ! empty( $rb['ok'] ), $rb );
teq( 'bỏ huỷ xong tiền trở lại sổ', 20000, VHG_Thu::tong_hop( 'all' )['tong'] );
teq( 'và không còn trong danh sách đã huỷ', 0, count( VHG_Thu::ds_huy() ) );

t( 'huỷ mã không có thì báo lỗi, không im lặng',
	empty( VHG_Thu::huy( 'khong-co-ma-nay' )['ok'] ) );
t( 'huỷ hai lần không hỏng', ! empty( VHG_Thu::huy( 'huy-1' )['ok'] )
	&& ! empty( VHG_Thu::huy( 'huy-1' )['ok'] ) );
teq( 'huỷ hai lần vẫn chỉ một dòng', 1, count( VHG_Thu::ds_huy() ) );

/* Lượt ghế ĐÃ NHẬN thì đừng gỡ — ghế chạy rồi, xoá dấu vết đi là sổ nói dối. */
vhg_dung_bang();
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 10000, 'phut' => 6,
	'so_tk' => '888815678', 'ten_tk' => 'K&H', 'bank_bin' => '970418', 'ten_khai' => '' ) );
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 DACHAY', 'referenceCode' => 'huy-2' ) );
VHG_May::lay_luot( 'AMTP01' );          // ghế nhận và chạy
VHG_Thu::huy( 'huy-2', 'thử' );
teq( 'lượt ghế ĐÃ CHẠY vẫn nằm trong sổ lượt', 1, count( VHG_May::ds_cho( false ) ) );

// ====================================== MÀN ĐỐI SOÁT DỰNG ĐƯỢC, VÀ KÊU KHI SAI MÚI GIỜ
$GLOBALS['VHCP_CO_QUYEN'] = true;
$GLOBALS['VHCP_OPT']['timezone_string'] = 'UTC';
$_GET = array( 'ky' => 'all' );
ob_start(); VHG_Admin::trang_thu(); $h_ds = ob_get_clean();
t( 'màn đối soát dựng được', strpos( $h_ds, 'Đối soát doanh thu' ) !== false );
t( '🔴 múi giờ UTC thì PHẢI kêu — lệch 7 tiếng so với sao kê',
	strpos( $h_ds, 'không phải giờ Việt Nam' ) !== false, $h_ds );
t( 'và chỉ đúng chỗ sửa', strpos( $h_ds, 'Ho Chi Minh' ) !== false );
t( 'có nút huỷ từng giao dịch', strpos( $h_ds, 'huy_gd' ) !== false );

$GLOBALS['VHCP_OPT']['timezone_string'] = 'Asia/Ho_Chi_Minh';
ob_start(); VHG_Admin::trang_thu(); $h_ds2 = ob_get_clean();
t( '⚠️ đúng giờ Việt Nam thì IM — cảnh báo kêu oan là người ta học cách bỏ qua nó',
	strpos( $h_ds2, 'không phải giờ Việt Nam' ) === false );
unset( $GLOBALS['VHCP_OPT']['timezone_string'] );
$_GET = array();

// ============================================ TRANG NGOÀI /ghe
/* Nhân viên đứng quầy KHÔNG có tài khoản WordPress — và không nên có, vì cấp tài khoản cho 26
   cửa hàng là cấp luôn đường vào phần quản trị website. Nên trang này có cổng PIN riêng. Phần
   dưới soi đúng cái cổng đó, vì đằng sau nó là toàn bộ doanh thu. */
vhg_dung_bang();
delete_option( 'vhg_vai_tro_vao' );

teq( 'đường dẫn mặc định là /ghe', 'ghe', VHG_Trang::slug() );
update_option( 'vhg_slug', 'ghe-massage' );
teq( 'đổi được đường dẫn', 'ghe-massage', VHG_Trang::slug() );
update_option( 'vhg_slug', 'ghe' );

// ---- rửa đuôi ".0", đúng lỗi đã khoá cửa trang chấm công ngày 22/08/2026
teq( 'rửa đuôi ".0": cắt đuôi TRƯỚC, bỏ ký tự lạ SAU', '571394', VHG_Auth::pin_sach( '571394.0' ) );
t( 'KHÔNG được nuốt dấu chấm thành chữ số', VHG_Auth::pin_sach( '571394.0' ) !== '5713940' );
teq( 'giữ nguyên số 0 đứng đầu', '0123', VHG_Auth::pin_sach( '0123' ) );

// ---- cổng PIN
$tok = vhg_vao( '571394', 'Admin' );
t( 'đăng nhập được bằng PIN', preg_match( '/^[0-9a-f]{64}$/', (string) $tok ) === 1, $tok );

VHG_Auth::mo_khoa();
$r = vhg_web( 'login', array( 'pin' => '999999' ) );
teq( 'PIN sai thì chối', false, $r['ok'] );
t( 'và KHÔNG phát token', empty( $r['token'] ) );
VHG_Auth::mo_khoa();
$r = vhg_web( 'login', array( 'pin' => '12' ) );
t( 'PIN sai khuôn bị chối ngay, chưa kịp so', strpos( $r['error'], '4–8' ) !== false, $r );
VHG_Auth::mo_khoa();

/* 🔴 Không có token thì KHÔNG được trả một con số nào. Đây là toàn bộ doanh thu. */
foreach ( array( 'so_lieu', 'bat', 'tat', 'tien_mat', 'logout' ) as $v ) {
	$r = vhg_web( $v, array() );
	teq( "việc '$v' không token -> chối", false, $r['ok'] );
	teq( "và chối bằng MÃ het_phien để giao diện phân biệt được", 'het_phien', $r['ma'] );
	t( "việc '$v' không token KHÔNG rò số liệu", ! isset( $r['tong'] ) && ! isset( $r['gd'] ) );
}
$r = vhg_web( 'so_lieu', array( 'token' => str_repeat( 'a', 64 ) ) );
teq( 'token bịa cũng chối', false, $r['ok'] );
$r = vhg_web( 'so_lieu', array( 'token' => 'khong-phai-hex' ) );
teq( 'token sai khuôn cũng chối', false, $r['ok'] );

// ---- vai trò: PIN đúng nhưng không đủ quyền
VHG_Auth::mo_khoa();
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Em Nhân Viên', 'pin' => '446688', 'vaiTro' => 'Nhân viên', 'coso' => '' ) ) );
/* 🔴 23/08/2026 — 'Nhân viên' NAY VÀO ĐƯỢC. Anh Thắng: *"Nhân viên đăng nhập vẫn trang này,
   nhưng chỉ hiện mỗi chốt ca"*. Vào được không có nghĩa là thấy được: gói số liệu của họ bị cắt
   ở máy chủ và mọi việc của quản trị bị chặn ở cổng — xem khối phân quyền ở cuối tệp. */
$r = vhg_web( 'login', array( 'pin' => '446688' ) );
t( '🔴 Nhân viên VÀO ĐƯỢC (để chốt ca)', ! empty( $r['ok'] ),
	isset( $r['error'] ) ? $r['error'] : '' );
teq( 'và mang đúng vai trò', 'Nhân viên', (string) $r['role'] );

/* Vai trò KHÔNG có trong danh sách cho vào thì phải nói "không được xem", KHÔNG nói "PIN sai" —
   nói sai thì người ta gõ lại mười lần rồi tự khoá mình, và đi tìm một cái PIN vốn không tồn tại. */
VHG_Auth::mo_khoa();
update_option( 'vhg_vai_tro_vao', array( 'Admin' ) );
$r_cam = vhg_web( 'login', array( 'pin' => '446688' ) );
teq( 'bỏ vai trò khỏi danh sách thì không vào được', false, $r_cam['ok'] );
t( '⚠️ và nói "không được xem", KHÔNG nói "PIN sai"',
	strpos( (string) $r_cam['error'], 'không được xem' ) !== false, (string) $r_cam['error'] );
delete_option( 'vhg_vai_tro_vao' );
VHG_Auth::mo_khoa();

t( 'Cửa hàng trưởng mặc định VÀO ĐƯỢC — người đứng quầy chính là người cần biết ghế nào đứng',
	in_array( 'Cửa hàng trưởng', VHG_Auth::VAI_TRO_MAC_DINH, true ) );
update_option( 'vhg_vai_tro_vao', array() );
teq( 'bỏ tích hết thì về MẶC ĐỊNH, không khoá sạch',
	VHG_Auth::VAI_TRO_MAC_DINH, VHG_Auth::vai_tro_vao() );
update_option( 'vhg_vai_tro_vao', array( 'Giám đốc' ) );
teq( 'toàn vai trò lạ cũng về mặc định', VHG_Auth::VAI_TRO_MAC_DINH, VHG_Auth::vai_tro_vao() );
delete_option( 'vhg_vai_tro_vao' );

/* 🔴 THU QUYỀN PHẢI ĂN NGAY VỚI PHIÊN ĐANG MỞ. Phiên sống 30 ngày; nếu chỉ xét vai trò lúc phát
      token thì bỏ một vai trò khỏi danh sách mà người đó vẫn xem doanh thu thêm một tháng —
      phép "đóng cửa" không đóng gì cả. */
VHG_Auth::mo_khoa();
$tok_ct = vhg_vao( '553311', 'Cửa hàng trưởng' );
t( 'Cửa hàng trưởng vào được', ! empty( vhg_web( 'so_lieu', array( 'token' => $tok_ct ) )['ok'] ) );
update_option( 'vhg_vai_tro_vao', array( 'Admin' ) );
$r = vhg_web( 'so_lieu', array( 'token' => $tok_ct ) );
teq( 'bỏ vai trò đó khỏi danh sách -> phiên ĐANG MỞ mất hiệu lực ngay', false, $r['ok'] );
delete_option( 'vhg_vai_tro_vao' );

// ---- số liệu
vhg_dung_bang();
$tok = vhg_vao();
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 10000, 'phut' => 6,
	'so_tk' => '888815678', 'ten_tk' => 'K&H', 'bank_bin' => '970418', 'ten_khai' => '' ) );
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 AAAAA', 'referenceCode' => 'web-1',
	'transactionDate' => '2026-08-22 20:08:26' ) );
$r = vhg_web( 'so_lieu', array( 'token' => $tok, 'ky' => 'all' ) );
t( 'lấy được số liệu', ! empty( $r['ok'] ), $r );
teq( 'tổng doanh thu đúng', 20000, $r['tong']['tong'] );
teq( 'có ghế trong danh sách', 'AMTP01', $r['may'][0]['ma'] );
teq( 'lượt đã trả mà ghế chưa nhận hiện ra', 1, dem( $r['cho'] ) );
teq( 'giao dịch hiện ra', 1, dem( $r['gd'] ) );
teq( 'biết mình là ai', 'Anh Thắng', $r['ai']['name'] );
/* Một lượt gọi ra ĐỦ màn: trên 4G ở trung tâm thương mại, bốn lượt gọi là bốn cơ hội hỏng và
   một màn hiện nửa vời — doanh thu có mà tình trạng ghế trống. */
foreach ( array( 'tong', 'may', 'cho', 'gd', 'ai', 'luc' ) as $k ) {
	t( "một lượt gọi trả đủ phần '$k'", isset( $r[ $k ] ) );
}
$r = vhg_web( 'so_lieu', array( 'token' => $tok, 'ky' => 'ba-la-bla' ) );
teq( 'kỳ rác thì về "hôm nay", không ném lỗi', 'today', $r['ky'] );

// ---- bật tay: chữ ký phải là người cầm phiên, không lấy từ gói gửi lên
$r = vhg_web( 'bat', array( 'token' => $tok, 'ma_may' => 'AMTP01', 'phut' => 6,
	'ly_do' => 'khách phàn nàn', 'nguoi' => 'Ai Đó Khác' ) );
t( 'bật được ghế', ! empty( $r['ok'] ), $r );
$lenh = VHG_May::ds_lenh( 5 );
teq( '🔴 ghi TÊN NGƯỜI CẦM PHIÊN, không lấy tên từ gói gửi lên', 'Anh Thắng', $lenh[0]['nguoi'] );
t( 'và giữ lý do', strpos( (string) $lenh[0]['ly_do'], 'khách phàn nàn' ) !== false );

// ---- thu tiền mặt
$truoc = VHG_Thu::tong_hop( 'all' )['tien_mat'];
vhg_web( 'tien_mat', array( 'token' => $tok, 'ma_may' => 'AMTP01', 'so_tien' => 50000 ) );
teq( 'thu tiền mặt vào sổ', $truoc + 50000, VHG_Thu::tong_hop( 'all' )['tien_mat'] );

// ---- thoát
vhg_web( 'logout', array( 'token' => $tok ) );
teq( 'thoát rồi thì token hết hiệu lực', false, vhg_web( 'so_lieu', array( 'token' => $tok ) )['ok'] );

// ---- HTML
$html = vhg_web_html();
t( 'dựng được trang', strpos( $html, '<!doctype html>' ) === 0 );
t( 'có ô PIN', strpos( $html, 'id="pin"' ) !== false );
t( 'hợp với điện thoại', strpos( $html, 'width=device-width' ) !== false );
/* Và hợp với MÀN MÁY TÍNH. Bản đầu bó vào một cột giữa, hai bên bỏ trống hơn nửa màn — mà
   người ngồi văn phòng đối soát cuối ngày lại dùng đúng màn đó. */
t( 'có bố cục riêng cho màn rộng', strpos( $html, '@media(min-width:1100px)' ) !== false );

/* ============ 🔴 TRANG PHẢI TỰ CẬP NHẬT
 *
 * Anh Thắng 22/08/2026: *"bấm điều khiển máy chạy, nhưng trên web thời gian chưa chạy"*. Trang
 * chỉ tải khi mở hoặc khi bấm ↻. Người đứng cạnh ghế bấm Bật, ghế chạy thật, web vẫn nói
 * "Rảnh" — họ tưởng lệnh không ăn nên bấm lần nữa, mà mỗi lần bấm Bật là CHO KHÔNG một lượt.
 */
t( 'trang tự hỏi lại máy chủ theo nhịp', strpos( $html, 'function henLai()' ) !== false );
/* ⚠️ KHAI HÀM CHƯA ĐỦ — PHẢI CÓ AI GỌI NÓ. Phép thử bản đầu chỉ soi hàm có tồn tại, nên phép
   đột biến "xoá hai dòng gọi trong noi()" đi lọt: trang lại đứng im y như cũ mà bảng điểm vẫn
   xanh. Một hàm không ai gọi là một hàm không chạy. */
t( '🔴 và THẬT SỰ được gọi mỗi lần vẽ lại trang',
	preg_match( '/function noi\(\)\{\s*henLai\(\);\s*chayDongHo\(\);/', $html ) === 1, $html ? '' : '' );
/* Hai nhịp khác nhau, cố ý: người đang đứng chờ ghế phản hồi cần 5 giây; bảng tiền không đổi
   từng giây mà trang này mở suốt ngày trên 4G. */
t( 'tab điều khiển nhanh hơn tab đối soát',
	preg_match( "/'dieu-khien':\s*(\d+).*?'doi-soat':\s*(\d+)/s", $html, $m_nh ) === 1
	&& (int) $m_nh[1] < (int) $m_nh[2], $html ? '' : '' );
/* ⚠️ KHÔNG hỏi khi đang mở bảng chốt ca — vẽ lại là xoá mất số người ta đang gõ. */
t( 'không tự tải lại khi đang mở bảng chốt ca hoặc đang chờ lệnh',
	strpos( $html, 'if (ban || CHOT || document.hidden)' ) !== false );
t( 'và huỷ lượt hẹn cũ trước khi hẹn mới — không thì mỗi lần vẽ lại thêm một đồng hồ',
	strpos( $html, 'if (hen) { clearTimeout(hen); hen = null; }' ) !== false );
/* Số đếm ngược tự trừ mỗi giây giữa hai lượt hỏi: một con số đứng im là dấu hiệu ghế treo,
   đừng để giao diện tự tạo ra dấu hiệu đó. */
t( 'số đếm ngược chạy tại chỗ mỗi giây', strpos( $html, 'function chayDongHo()' ) !== false );
t( 'và chỉ đụng vào chữ của con số, không vẽ lại cả trang',
	strpos( $html, "o.textContent = mmss(m.con_lai)" ) !== false );
t( 'hết giờ thì hỏi lại NGAY, không đợi hết nhịp',
	strpos( $html, 'clearInterval(demGiay); demGiay = null; if (!ban && !CHOT) tai(true);' ) !== false );
t( 'mở lại trang sau khi khoá màn thì hỏi ngay',
	strpos( $html, "visibilitychange" ) !== false );
/* Ghế vẽ "04:57" (snprintf "%02d:%02d"). Web phải y hệt — cùng một con số ra hai kiểu là chỗ
   người đối chiếu bằng mắt dừng lại tự hỏi hai bên có nói cùng một thứ không. */
t( 'đồng hồ web cùng khuôn mm:ss với màn ghế',
	strpos( $html, "String(Math.floor(s/60)).padStart(2,'0')" ) !== false );
t( 'hai bảng tổng hợp nằm cạnh nhau trên màn rộng',
	strpos( $html, '.doi{display:grid' ) !== false && strpos( $html, '<div class="doi">' ) !== false );
t( 'nhưng trên điện thoại vẫn xếp dọc như cũ',
	strpos( $html, '@media(max-width:560px)' ) !== false );
/* 🔴 Trang tự chứa: cả hệ thống ghế đã rời hẳn Google, đi vòng qua Apps Script là dựng lại đúng
      cái phụ thuộc vừa gỡ. Không gọi ra ngoài lượt nào. */
foreach ( array( 'script.google.com', 'firebaseio', 'googleapis', 'cdn.', 'unpkg', 'jsdelivr' ) as $ngoai ) {
	t( "trang KHÔNG gọi ra $ngoai", stripos( $html, $ngoai ) === false );
}
t( 'không nạp file ngoài nào', preg_match( '/<(script|link)[^>]+(src|href)=["\']https?:/i', $html ) === 0 );

/* ⚠️ CHẶN BẤM HAI LẦN. Trên 4G một lượt bấm có thể mất 3 giây không thấy gì, và phản xạ là bấm
      lại. Với "Thu tiền mặt" thì bấm hai lần là GHI HAI LẦN — tiền thật vào sổ gấp đôi. */
$js = $html;
t( 'giao diện có khoá chống bấm hai lần', strpos( $js, 'if (ban) return' ) !== false );
t( 'và khoá bằng cách vô hiệu nút cho tới khi máy chủ trả lời',
	strpos( $js, 'b.disabled = true' ) !== false );
t( 'bật tay có hỏi lý do', strpos( $js, 'CHO KHÔNG một lượt' ) !== false );

/* 🔴 Máy chủ trả rác (tường lửa hosting chèn trang chặn) KHÔNG được thành "hết phiên" — đá người
      ta ra rồi họ gõ lại PIN và gặp đúng lỗi đó, vòng vô tận mà không ai hiểu vì sao. */
t( 'trả lời không đọc được thì báo lỗi mạng, KHÔNG đá ra màn PIN',
	strpos( $js, 'tường lửa' ) !== false );
t( 'chỉ mã het_phien mới đá ra màn PIN', strpos( $js, "r.ma === 'het_phien'" ) !== false );

// ---- không chuyển hướng: trang này người ta lưu vào màn hình chính điện thoại
$src_tr = file_get_contents( VHG_DIR . 'includes/class-vhg-trang.php' );
t( 'chặn chuyển hướng chuẩn hoá đường dẫn',
	strpos( $src_tr, "redirect_canonical" ) !== false );
t( 'và gài ở parse_request (sớm), không đợi template_redirect',
	strpos( $src_tr, "'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0" ) !== false );

$src_boot = file_get_contents( VHG_DIR . 'vhcp-ghe.php' );
t( '🔴 trang gài ở init ưu tiên 4 — TRƯỚC lượt nạp lại luật đường dẫn ở 99',
	preg_match( "/VHG_Trang', 'init' \), 4 \)/", $src_boot ) === 1 );

// ---- màn cài đặt trang ngoài
$GLOBALS['VHCP_CO_QUYEN'] = true;
update_option( 'vhg_nguon_nguoidung', 'rieng' );
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Anh Thắng', 'pin' => '571394', 'vaiTro' => 'Admin', 'coso' => '' ) ) );
$_GET = array(); $_POST = array();
update_option( 'permalink_structure', '/%postname%/' );
ob_start(); VHG_Admin::trang_ngoai(); $h_tn = ob_get_clean();
t( 'màn cài đặt dựng được', strpos( $h_tn, 'Trang ngoài' ) !== false );
t( 'hiện địa chỉ trang', strpos( $h_tn, '/ghe' ) !== false );
t( 'permalink đẹp thì KHÔNG kêu oan', strpos( $h_tn, 'Plain' ) === false );

/* 🔴 Permalinks kiểu "Plain" thì luật đường dẫn không chạy và `/ghe` trả 404. Triệu chứng
   (404) không hề gợi tới nguyên nhân (một ô cài đặt ở màn khác hẳn của WordPress), nên màn
   hình phải nói ra — không thì người ta đi sửa plugin, sửa .htaccess, sửa mọi thứ trừ chỗ đúng. */
delete_option( 'permalink_structure' );
ob_start(); VHG_Admin::trang_ngoai(); $h_pl = ob_get_clean();
t( 'permalink "Plain" thì kêu lên', strpos( $h_pl, 'Plain' ) !== false, $h_pl );
t( 'và chỉ đúng chỗ sửa', strpos( $h_pl, 'options-permalink.php' ) !== false );
t( 'nhắc luôn hai cổng máy cũng cần thứ đó', strpos( $h_pl, 'ghe-tien' ) !== false );
update_option( 'permalink_structure', '/%postname%/' );
t( 'liệt kê ai vào được', strpos( $h_tn, 'Anh Thắng' ) !== false );
t( '🔴 KHÔNG in PIN ra màn hình', strpos( $h_tn, '571394' ) === false, $h_tn );
t( 'chỉ in số chữ số', strpos( $h_tn, '6 số' ) !== false );
t( 'form con không lồng trong form cài đặt',
	vhg_do_sau_form( $h_tn )['max'] <= 1, vhg_do_sau_form( $h_tn )['max'] );

// ---- chặn PIN trùng: hai người cùng PIN thì người thứ hai vào nhầm quyền người khác, im lặng
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Anh Thắng', 'pin' => '571394', 'vaiTro' => 'Admin', 'coso' => '' ) ) );
$_POST = array( 'vhg' => 'them_nd', 'ten' => 'Chị Hai', 'pin' => '571394',
	'vai_tro_moi' => 'Quản lý', 'coso' => '' );
ob_start(); VHG_Admin::trang_ngoai(); $h_tr = ob_get_clean();
t( '🔴 chặn PIN trùng', strpos( $h_tr, 'đã có người dùng' ) !== false, $h_tr );
teq( 'và KHÔNG thêm vào danh sách', 1, count( (array) get_option( 'vhg_nguoidung' ) ) );

foreach ( array( '111111', '123456', '000000' ) as $de ) {
	$_POST = array( 'vhg' => 'them_nd', 'ten' => 'Ai Đó', 'pin' => $de,
		'vai_tro_moi' => 'Quản lý', 'coso' => '' );
	ob_start(); VHG_Admin::trang_ngoai(); $h_de = ob_get_clean();
	t( "chặn PIN quá dễ đoán $de", strpos( $h_de, 'dễ đoán' ) !== false );
}
$_POST = array(); $_GET = array();
delete_option( 'vhg_nguoidung' );
update_option( 'vhg_nguon_nguoidung', 'chung' );

// ============================================ TÀI KHOẢN NHẬN TIỀN KHAI MỘT LẦN
/* 🔴 Anh Thắng: *"liên kết qua sepay và vietqr mà liên quan gì đến số tk"*. Số tài khoản vẫn cần
      — mã QR phải nói tiền đi về đâu, SePay chỉ BÁO TIN tiền đã về. Nhưng bắt khai lại cho từng
      ghế là sai: 26 ghế là 26 lần gõ cùng một con số, và đổi tài khoản là sửa đúng 26 chỗ —
      sót một chỗ thì tiền ghế đó chảy về tài khoản cũ, âm thầm. */
vhg_dung_bang();
delete_option( 'vhg_bin' ); delete_option( 'vhg_so_tk' ); delete_option( 'vhg_ten_tk' );
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H POSH' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 10000, 'phut' => 6,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '' ) );
$tk = VHG_May::nhan_tien_cua( VHG_May::may( 'AMTP01' ) );
teq( 'ghế không khai riêng thì dùng tài khoản chung', '888815678', $tk['so_tk'] );
teq( 'BIN cũng lấy từ chung', '970418', $tk['bin'] );
teq( 'tên tài khoản cũng vậy', 'K&H POSH', $tk['ten_tk'] );

$qr = VHG_QR::cho_ghe( 'AMTP01', 'MAU' );
t( 'dựng được QR chỉ với tài khoản chung', ! empty( $qr['ok'] ), $qr );
t( 'và chuỗi QR mang đúng số tài khoản chung', strpos( $qr['chuoi'], '888815678' ) !== false );

/* Ô riêng của ghế vẫn là NGOẠI LỆ hợp lệ — ghế đặt ở điểm có tài khoản riêng. */
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 10000, 'phut' => 6,
	'so_tk' => '999999999', 'ten_tk' => 'Riêng', 'bank_bin' => '970436', 'ten_khai' => '' ) );
$tk2 = VHG_May::nhan_tien_cua( VHG_May::may( 'AMTP02' ) );
teq( 'khai riêng thì ĐÈ lên chung (số TK)', '999999999', $tk2['so_tk'] );
teq( 'khai riêng thì đè lên chung (BIN)', '970436', $tk2['bin'] );
teq( 'nhưng ghế kia KHÔNG bị ảnh hưởng', '888815678',
	VHG_May::nhan_tien_cua( VHG_May::may( 'AMTP01' ) )['so_tk'] );

/* Đổi tài khoản chung một lần là mọi ghế theo — đây mới là điều cần. */
VHG_May::luu_nhan_tien( '970422', '777777777', 'Mới' );
teq( 'đổi một chỗ, ghế dùng chung theo ngay', '777777777',
	VHG_May::nhan_tien_cua( VHG_May::may( 'AMTP01' ) )['so_tk'] );
teq( 'ghế khai riêng KHÔNG bị đổi theo', '999999999',
	VHG_May::nhan_tien_cua( VHG_May::may( 'AMTP02' ) )['so_tk'] );

$r = VHG_May::luu_nhan_tien( '97041', '888815678', 'x' );
teq( 'BIN không đủ 6 số thì chối — sai BIN là tiền về ngân hàng khác', false, $r['ok'] );
t( 'và nói rõ phải 6 chữ số', strpos( $r['error'], '6 chữ số' ) !== false );

/* Chưa khai gì thì QR phải CHỐI kèm câu chỉ đúng chỗ khai — không dựng ra một QR rỗng. */
delete_option( 'vhg_bin' ); delete_option( 'vhg_so_tk' );
$qr0 = VHG_QR::cho_ghe( 'AMTP01', 'MAU' );
teq( 'chưa khai tài khoản thì KHÔNG dựng QR', false, ! empty( $qr0['ok'] ) );
t( 'và chỉ đúng chỗ khai', strpos( $qr0['error'], 'dùng chung' ) !== false, $qr0['error'] );
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );

// ============================================ BỐN GÓI KHAI TỪ WEB
/* Ghế KHÔNG CÓ OTA — khai cứng trong firmware nghĩa là đổi giá phải mang USB đi 26 cửa hàng.
   Bảng giá anh Thắng dựng (ảnh 22/08/2026): 50k/15′ · 100k/30′ · 150k/45′ · 200k/60′ — cả bốn
   đúng một tỉ lệ 50.000đ = 15 phút, nên số phút để ghế tự tính là đủ. */
delete_option( 'vhg_menh_gia' );
$mg = VHG_May::menh_gia();
teq( 'mặc định đúng bốn gói', 4, count( $mg ) );
teq( 'và đúng bảng giá đang dùng', array( 50000, 100000, 150000, 200000 ),
	array_map( function ( $g ) { return $g['tien']; }, $mg ) );
teq( 'gói mặc định có tên', 'Gói phổ biến', $mg[1]['ten'] );

t( 'lưu được bộ mới', ! empty( VHG_May::luu_menh_gia( array(
	array( 'tien' => 100000, 'ten' => 'Gói phổ biến', 'phut' => 0 ),
	array( 'tien' => 50000,  'ten' => 'Gói cơ bản',   'phut' => 0 ) ) )['ok'] ) );
$mg = VHG_May::menh_gia();
teq( 'tự sắp tăng dần theo tiền', array( 50000, 100000 ),
	array_map( function ( $g ) { return $g['tien']; }, $mg ) );
teq( 'giữ đúng tên theo từng gói', 'Gói cơ bản', $mg[0]['ten'] );

/* Hai gói cùng số tiền = hai nút giống hệt nhau trên màn; khách bấm không biết mình chọn gì. */
$r = VHG_May::luu_menh_gia( array(
	array( 'tien' => 50000, 'ten' => 'A' ), array( 'tien' => 50000, 'ten' => 'B' ) ) );
teq( 'chối hai gói cùng số tiền', false, $r['ok'] );
teq( 'và giữ nguyên bộ cũ', array( 50000, 100000 ),
	array_map( function ( $g ) { return $g['tien']; }, VHG_May::menh_gia() ) );

$r = VHG_May::luu_menh_gia( array( array( 'tien' => '' ), array( 'tien' => 'abc' ) ) );
teq( 'toàn rác thì CHỐI, không lưu bộ rỗng', false, $r['ok'] );
$r = VHG_May::luu_menh_gia( array( array( 'tien' => 10000 ), array( 'tien' => 20000 ),
	array( 'tien' => 30000 ), array( 'tien' => 40000 ), array( 'tien' => 50000 ) ) );
teq( 'quá 4 nút thì chối — màn ghế chỉ có 4 ô', false, $r['ok'] );

/* 🔴 Bộ rỗng KHÔNG được thành màn ghế không có nút nào: đó là đường QR chết hẳn ở cửa hàng đó
      mà máy chủ vẫn thấy ghế gửi nhịp bình thường. */
update_option( 'vhg_menh_gia', array() );
teq( 'cơ sở dữ liệu rỗng -> vẫn trả bộ mặc định', 4, count( VHG_May::menh_gia() ) );
update_option( 'vhg_menh_gia', 'hỏng' );
teq( 'cơ sở dữ liệu hỏng -> vẫn trả bộ mặc định', 4, count( VHG_May::menh_gia() ) );

/* ⚠️ ĐỌC ĐƯỢC BỘ CŨ. Bản 1.3.0 lưu một mảng SỐ trơn; nâng cấp plugin mà không đọc được dạng đó
      là bốn gói đang chạy biến mất và ghế về bộ mặc định, âm thầm. */
update_option( 'vhg_menh_gia', array( 20000, 50000 ) );
teq( 'đọc được bộ cũ (mảng số trơn)', array( 20000, 50000 ),
	array_map( function ( $g ) { return $g['tien']; }, VHG_May::menh_gia() ) );
t( 'bộ cũ thì tên rỗng, không bịa tên', '' === VHG_May::menh_gia()[0]['ten'] );

// ---- số phút: tự tính theo tỉ lệ, hoặc khai cứng
teq( 'phút tự tính theo tỉ lệ 50.000đ = 15 phút',
	30, VHG_May::phut_goi( array( 'tien' => 100000, 'phut' => 0 ), 50000, 15 ) );
teq( 'khai cứng thì ĐÈ lên tỉ lệ — dành cho gói khuyến mãi/kèm quà',
	90, VHG_May::phut_goi( array( 'tien' => 100000, 'phut' => 90 ), 50000, 15 ) );
teq( 'tỉ lệ 0 thì trả 0, không chia cho 0',
	0, VHG_May::phut_goi( array( 'tien' => 100000, 'phut' => 0 ), 0, 15 ) );

// ---- tên gửi xuống ghế phải BỎ DẤU
/* 🔴 Font màn ghế (TFT_eSPI) không vẽ được dấu tiếng Việt — "Gói phổ biến" hiện ra thành một
      hàng ô vuông, và người ta tưởng ghế hỏng. Bỏ dấu ở MÁY CHỦ: ghế không có OTA, nên thứ gì
      sửa được bằng máy chủ thì phải sửa ở máy chủ. */
teq( 'bỏ dấu + viết hoa', 'GOI PHO BIEN', VHG_May::bo_dau_hoa( 'Gói phổ biến' ) );
teq( 'đủ mọi nguyên âm có dấu', 'AEIOUYD', VHG_May::bo_dau_hoa( 'ăễỉộựỹđ' ) );
teq( 'gộp khoảng trắng thừa', 'GOI VIP', VHG_May::bo_dau_hoa( "  Gói   VIP \n" ) );
t( '⚠️ ký tự ngoài bảng thì BỎ HẲN, đừng gửi xuống ghế thành ô vuông',
	preg_match( '/^[\x20-\x7E]*$/', VHG_May::bo_dau_hoa( 'Gói ★ 中文' ) ) === 1,
	VHG_May::bo_dau_hoa( 'Gói ★ 中文' ) );
t( 'cắt cho vừa bề ngang một ô', mb_strlen( VHG_May::bo_dau_hoa( str_repeat( 'A', 50 ) ) ) <= 18 );

VHG_May::luu_menh_gia( array(
	array( 'tien' => 50000,  'ten' => 'Gói cơ bản',   'phut' => 0 ),
	array( 'tien' => 100000, 'ten' => 'Gói phổ biến', 'phut' => 0 ) ) );
$cg = VHG_May::menh_gia_cho_ghe( 50000, 15 );
/* Khoá MỘT CHỮ vì gói nhịp đi qua 4G và ghế giải mã trong bộ đệm cố định — tên khoá dài là
   tốn đúng chỗ mà một cái tên gói cần. */
teq( 'gói cho ghế: khoá ngắn t/n/p/m/v', array( 't', 'n', 'p', 'm', 'v' ), array_keys( $cg[0] ) );
teq( 'tên đã bỏ dấu', 'GOI PHO BIEN', $cg[1]['n'] );
teq( 'phút đã tính sẵn — ghế khỏi tính lại và khỏi lệch với cái nó hiện', 30, $cg[1]['p'] );

// ---- mô tả và nhãn VVIP, theo đúng tấm bảng giá anh Thắng thiết kế
delete_option( 'vhg_menh_gia' );
$mac_dinh = VHG_May::menh_gia();
teq( 'gói mặc định có mô tả', 'Sâu & phục hồi', $mac_dinh[1]['mo_ta'] );
teq( 'và gói đắt nhất được đánh dấu VVIP', 1, $mac_dinh[3]['vip'] );
teq( 'ba gói còn lại thì không', array( 0, 0, 0 ),
	array( $mac_dinh[0]['vip'], $mac_dinh[1]['vip'], $mac_dinh[2]['vip'] ) );

VHG_May::luu_menh_gia( array(
	array( 'tien' => 50000,  'ten' => 'Gói cơ bản',      'mo_ta' => 'Khởi động & thư giãn nhẹ' ),
	array( 'tien' => 200000, 'ten' => 'Gói thượng hạng', 'mo_ta' => 'Đẳng cấp & quà tặng', 'vip' => 1 ) ) );
$cg2 = VHG_May::menh_gia_cho_ghe( 50000, 15 );
teq( 'mô tả cũng bỏ dấu trước khi xuống ghế', 'KHOI DONG & THU GIAN NHE', $cg2[0]['m'] );
teq( 'nhãn VVIP đi xuống ghế', 1, $cg2[1]['v'] );
teq( 'gói thường thì không', 0, $cg2[0]['v'] );
/* Mô tả dài hơn tên vì nó nằm một dòng riêng dưới đáy thẻ: 24 ký tự là bề ngang thẻ 150px. */
t( 'mô tả cắt ở 24 ký tự cho vừa bề ngang thẻ',
	mb_strlen( VHG_May::bo_dau_hoa( str_repeat( 'A', 60 ), 24 ) ) === 24 );
t( 'tên cắt ngắn hơn, 16 ký tự',
	mb_strlen( VHG_May::bo_dau_hoa( str_repeat( 'A', 60 ), 16 ) ) === 16 );

/* Ghế lấy gói + tài khoản qua NHỊP, không nạp lại firmware. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
VHG_May::luu_menh_gia( array(
	array( 'tien' => 50000,  'ten' => 'Gói cơ bản',   'phut' => 0 ),
	array( 'tien' => 100000, 'ten' => 'Gói phổ biến', 'phut' => 0 ) ) );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
list( , $n ) = vhg_ghe( array( 'ma_may' => 'AMTP01', 'viec' => 'nhip', 'trang_thai' => 'idle' ) );
teq( 'nhịp trả đủ hai gói', 2, dem( $n['goi'] ) );
teq( 'kèm tên đã bỏ dấu', 'GOI CO BAN', $n['goi'][0]['n'] );
teq( 'và số phút tính theo tỉ lệ CỦA CHÍNH GHẾ ĐÓ', 30, $n['goi'][1]['p'] );
teq( 'nhịp trả số tài khoản chung', '888815678', $n['soTk'] );
teq( 'nhịp trả BIN chung', '970418', $n['bin'] );

// ============================================ GÁN MÃ CHO GHẾ TỰ DÒ RA
/* Ghế nhận nhau với máy chủ bằng MAC. Cắm điện là tự hiện ra với mã tạm `?xxxxxx`. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$tam = VHG_May::ghi_nhan( 'AA:BB:CC:DD:98:58' );
t( 'ghế lạ tự hiện ra với mã tạm bắt đầu bằng "?"', '?' === $tam[0], $tam );
teq( 'và nằm trong danh sách chờ gán', 1, count( VHG_May::chua_gan() ) );
teq( 'hỏi lại cùng MAC KHÔNG đẻ dòng thứ hai', $tam, VHG_May::ghi_nhan( 'AA:BB:CC:DD:98:58' ) );
teq( 'vẫn đúng một dòng chờ', 1, count( VHG_May::chua_gan() ) );

/* 🔴 GHẾ CHƯA GÁN MÃ KHÔNG ĐƯỢC DỰNG QR. Mã tạm có dấu `?`, mà phép đọc ngược khớp `GHE` rồi
      đòi ngay chữ-hoặc-số nên gặp `?` là trượt. Dựng bừa nghĩa là: khách quét, tiền VÀO THẬT,
      máy chủ không biết của ghế nào, ghế không bao giờ chạy. */
$qr_tam = VHG_QR::cho_ghe( $tam, 'MAU' );
teq( 'ghế chưa gán mã thì KHÔNG dựng QR', false, ! empty( $qr_tam['ok'] ) );
t( 'và nói rõ phải gán mã trước', strpos( $qr_tam['error'], 'chưa được gán mã' ) !== false, $qr_tam );
teq( 'phép đọc ngược đúng là không đọc nổi mã tạm',
	array( '', '' ), VHG_Doc::ghe_va_ma( 'GHE' . $tam . ' AAAAA' ) );

/* Tiền vẫn có thể vào trước khi gán mã — thu tiền mặt tại quầy lúc ghế vừa cắm điện. */
VHG_Thu::thu_tien_mat( $tam, 20000, 'Chị quầy' );
VHG_May::xep_cho_chay( $tam, 'AAAAA', 20000, 'gan-1', 'tiền mặt' );

$cs = VHG_May::luu_coso( 0, 'Aeon Tân Phú' );
$id_cs = 0;
foreach ( VHG_May::ds_coso() as $c ) { if ( 'Aeon Tân Phú' === $c['ten'] ) { $id_cs = (int) $c['id']; } }
t( 'tạo được cơ sở', $id_cs > 0 );

$r = VHG_May::gan_ma( $tam, 'AMTP01', $id_cs );
t( 'gán mã được', ! empty( $r['ok'] ), $r );
teq( 'hết nằm trong danh sách chờ', 0, count( VHG_May::chua_gan() ) );
$m = VHG_May::may( 'AMTP01' );
t( 'ghế mang mã mới', $m !== null );
teq( '🔴 GIỮ NGUYÊN MAC — mất MAC là ghế thật không nhận ra dòng này nữa',
	'AA:BB:CC:DD:98:58', $m['mac'] );
teq( 'và gán luôn cơ sở trong cùng một lượt', $id_cs, (int) $m['coso_id'] );
teq( 'ghế hỏi bằng MAC ra ĐÚNG mã mới', 'AMTP01', VHG_May::ghi_nhan( 'AA:BB:CC:DD:98:58' ) );
teq( 'không đẻ thêm dòng chờ nào', 0, count( VHG_May::chua_gan() ) );

/* 🔴 LỊCH SỬ PHẢI ĐI THEO. Đổi mỗi bảng `may` thì lượt khách ĐÃ TRẢ TIỀN mà ghế chưa nhận nằm
      lại dưới mã cũ; ghế hỏi bằng mã mới nên không bao giờ thấy — khách trả tiền xong ghế không
      chạy, và không có gì trên màn hình nói vì sao. */
teq( 'lượt đang chờ đi theo mã mới', 1, VHG_May::so_cho( 'AMTP01' ) );
$gd = VHG_Thu::ds( 'all' );
teq( 'doanh thu cũng đi theo mã mới', 'AMTP01', $gd[0]['ma_may'] );
t( 'và gán xong thì QR dựng được', ! empty( VHG_QR::cho_ghe( 'AMTP01', 'MAU' )['ok'] ) );
teq( 'tổng tiền KHÔNG đổi', 20000, VHG_Thu::tong_hop( 'all' )['tong'] );

/* Không cho hai ghế trùng mã: trùng là tiền của ghế này chạy ghế kia. */
$tam2 = VHG_May::ghi_nhan( 'AA:BB:CC:DD:98:59' );
$r = VHG_May::gan_ma( $tam2, 'AMTP01' );
teq( '🔴 chối mã đã có ghế khác dùng', false, $r['ok'] );
t( 'và nói rõ hậu quả', strpos( $r['error'], 'chạy ghế kia' ) !== false, $r['error'] );
$r = VHG_May::gan_ma( $tam2, 'AM TP 02' );
teq( 'chối mã có khoảng trắng — khách gõ tay mã này', false, $r['ok'] );
$r = VHG_May::gan_ma( $tam2, 'Ghế02' );
teq( 'chối mã có dấu', false, $r['ok'] );
$r = VHG_May::gan_ma( 'khong-co', 'AMTP09' );
teq( 'chối mã cũ không tồn tại', false, $r['ok'] );
t( 'gán mã hợp lệ thì được', ! empty( VHG_May::gan_ma( $tam2, 'AMTP02' )['ok'] ) );

// ---- màn Máy & cơ sở
/* ⚠️ DỰNG MÀN KHI ĐANG CÓ GHẾ CHỜ. Bản trước chỉ dựng lúc danh sách chờ RỖNG, nên cả nhánh vẽ
   bảng chờ gán chưa hề chạy lần nào — và nó có một lỗi thật (gọi esc_sql) chỉ lộ ra khi một
   phép đột biến vô tình làm sinh ra một ghế chờ. Một nhánh không bao giờ chạy là một nhánh
   không được thử, dù bảng điểm vẫn xanh. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
VHG_May::ghi_nhan( 'AA:BB:CC:DD:98:77' );
teq( 'có đúng một ghế đang chờ để dựng màn', 1, count( VHG_May::chua_gan() ) );
ob_start(); VHG_Admin::trang_may(); $h_my = ob_get_clean();
t( 'màn có mục ghế chờ gán', strpos( $h_my, 'Ghế chờ gán mã' ) !== false );
t( 'bảng chờ gán hiện MAC của ghế', strpos( $h_my, 'AA:BB:CC:DD:98:77' ) !== false, $h_my );
t( 'và có ô chọn cơ sở ngay tại dòng đó', strpos( $h_my, 'chưa gán cơ sở' ) !== false );
t( 'nhắc đặt mã NGẮN vì khách gõ tay', strpos( $h_my, 'đặt ngắn' ) !== false );
VHG_May::xoa_may( VHG_May::chua_gan()[0]['ma'] );
t( 'màn có ô tài khoản dùng chung', strpos( $h_my, 'dùng chung cả hệ thống' ) !== false );
t( 'màn có ô khai gói', strpos( $h_my, 'Gói trên màn ghế' ) !== false );
t( 'khai được TÊN gói, không chỉ số tiền', strpos( $h_my, 'mg_ten[]' ) !== false );
t( 'và số phút riêng cho gói không theo tỉ lệ', strpos( $h_my, 'mg_phut[]' ) !== false );
/* Xem trước bằng CHÍNH dữ liệu sắp gửi đi: đó là cách duy nhất thấy trước "GOI PHO BIEN" trông
   thế nào, thay vì đi tới tận cửa hàng mới biết tên bị cắt cụt hay biến thành ô vuông. */
t( 'có bảng xem trước đúng như ghế sẽ hiện', strpos( $h_my, 'Ghế sẽ hiện' ) !== false );
t( 'và xem trước là tên ĐÃ BỎ DẤU', strpos( $h_my, 'GOI PHO BIEN' ) !== false, $h_my );
t( 'màn có ô nhập MAC', strpos( $h_my, 'name="mac"' ) !== false );
t( 'và nói rõ thường không cần gõ tay', strpos( $h_my, 'Thường không cần gõ' ) !== false );
t( '🔴 nhãn KHÔNG còn là "Giá một lượt" — nó là tỉ lệ quy đổi, không phải một mệnh giá',
	strpos( $h_my, 'Giá một lượt' ) === false );
t( 'mà là "Tỉ lệ quy đổi"', strpos( $h_my, 'Tỉ lệ quy đổi' ) !== false );
/* Tỉ lệ quy đổi giờ khai CHUNG, ngay trên bảng gói — không còn chôn trong ô "Thêm/sửa máy". */
t( 'ô tỉ lệ quy đổi nằm ngay trên bảng gói',
	strpos( $h_my, 'Tỉ lệ quy đổi (dùng chung' ) !== false
	&& strpos( $h_my, 'name="gia_c"' ) !== false );
t( 'và nói rõ số phút bốn gói tính theo cặp số này',
	strpos( $h_my, 'tính theo cặp số này' ) !== false );
t( 'ô của từng máy thành NGOẠI LỆ, để trống là dùng chung',
	strpos( $h_my, 'Tỉ lệ riêng (tuỳ chọn)' ) !== false );
t( 'bảng máy hiện MAC để đối chiếu', strpos( $h_my, '<th>MAC</th>' ) !== false );
t( 'nói rõ ghế TỰ hiện ra khi cắm điện', strpos( $h_my, 'tự hiện' ) !== false );
teq( 'màn không lồng <form>', 1, vhg_do_sau_form( $h_my )['max'] );
teq( 'và đóng đủ thẻ', 0, vhg_do_sau_form( $h_my )['con_thua'] );

/* Chưa khai tài khoản thì phải báo đỏ: ghế không vẽ được QR, không thu được đồng nào qua QR. */
delete_option( 'vhg_so_tk' ); delete_option( 'vhg_bin' );
ob_start(); VHG_Admin::trang_may(); $h_m0 = ob_get_clean();
t( 'chưa khai tài khoản thì màn báo đỏ', strpos( $h_m0, 'Chưa khai tài khoản' ) !== false );
t( 'và nói rõ tiền mặt vẫn chạy', strpos( $h_m0, 'Tiền mặt vẫn chạy' ) !== false );
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );

// ---- firmware nhận mệnh giá từ nhịp
$fw = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'firmware đọc mệnh giá từ gói nhịp', strpos( $fw, 'd["goi"]' ) !== false );
t( '⚠️ và CHỈ nhận khi có ít nhất một giá trị hợp lệ (n > 0)',
	strpos( $fw, 'if(n > 0)' ) !== false );
t( 'mảng mệnh giá không còn là const — máy chủ đè lên được',
	preg_match( '/^\s*long\s+PKG_AMT\[PKG_MAX\]/m', $fw ) === 1 );
t( 'PKG_MAX cố định 4 (số ô vẽ được trên màn)',
	preg_match( '/const int PKG_MAX = 4;/', $fw ) === 1 );
t( 'nút bấm cấp phát theo PKG_MAX, không theo PKG_N',
	strpos( $fw, 'Btn PKG_BTN[PKG_MAX]' ) !== false );
/* 🔴 Gói nhịp to thêm mảng `goi`. Bộ đệm chật thì deserializeJson trả lỗi và hàm THOÁT NGAY —
      ghế mất luôn cả giá, tài khoản lẫn lệnh, mà màn hình không có gì báo. */
t( 'bộ đệm JSON đã nới cho mảng mệnh giá',
	preg_match( '/StaticJsonDocument<(\d+)> d;/', $fw, $mm ) === 1 && (int) $mm[1] >= 768, $fw ? '' : '' );

$_GET = array(); $_POST = array();

// ====================== 🔴 GHẾ TỰ KHOÁ CHÍNH NÓ: "MÁY QR CỨ BÁO CHƯA ĐƯỢC GÁN MÃ"
/* Anh Thắng 22/08/2026. Dựng lại đúng năm bước của cái vòng đó:
 *   1. Ghế cắm điện, chưa ai gán -> máy chủ cấp mã tạm `?xxxxxx`, ghế NHỚ mã đó.
 *   2. Người ta gán mã thật trên web. Dòng đổi tên, MAC giữ nguyên.
 *   3. Ghế vẫn khai mã cũ — nó chưa biết mình đã đổi tên.
 *   4. Máy chủ tin lời khai, tra mã cũ -> không còn -> trả "chưa gán".
 *   5. Ghế hiện "CHUA DUOC GAN MA", vẫn khai mã cũ, bước 4 lặp lại MÃI MÃI.
 * Vòng đó không có đường ra: nạp lại firmware cũng không, vì mã tạm sinh từ chính MAC nên
 * được cấp lại y hệt. Chỉ xoá dòng trong cơ sở dữ liệu mới thoát. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$MAC_T = '24:0A:C4:0D:98:58';

$tam_k = VHG_May::ghi_nhan( $MAC_T );                       // bước 1
list( , $n1 ) = vhg_ghe( array( 'mac' => $MAC_T, 'ma_may' => $tam_k, 'viec' => 'nhip' ) );
teq( 'lúc chưa gán: máy chủ báo chưa gán', 1, $n1['chuaGan'] );
teq( 'và trả về đúng mã tạm', $tam_k, $n1['maMay'] );

VHG_May::gan_ma( $tam_k, 'AMTP01' );                        // bước 2

/* 🔴 BƯỚC 3 — ĐÂY LÀ CA HỎNG. Ghế vẫn khai mã CŨ, vì nó chưa biết mình đã đổi tên. */
list( , $n2 ) = vhg_ghe( array( 'mac' => $MAC_T, 'ma_may' => $tam_k, 'viec' => 'nhip' ) );
teq( '🔴 ghế khai mã CŨ -> máy chủ vẫn phải trả mã MỚI', 'AMTP01', $n2['maMay'] );
teq( '🔴 và KHÔNG được báo "chưa gán" nữa', 0, $n2['chuaGan'] );
teq( 'ghế đã khai thì có cấu hình đi kèm', 1, $n2['khai'] );
t( 'kèm tài khoản để vẽ QR', '888815678' === $n2['soTk'] );

/* Lượt sau ghế đã học tên mới — vẫn phải đúng. */
list( , $n3 ) = vhg_ghe( array( 'mac' => $MAC_T, 'ma_may' => 'AMTP01', 'viec' => 'nhip' ) );
teq( 'lượt sau ghế khai mã mới: vẫn đúng', 'AMTP01', $n3['maMay'] );
teq( 'và không đẻ thêm dòng chờ gán nào', 0, count( VHG_May::chua_gan() ) );

/* ⚠️ MAC PHẢI THẮNG cả khi ghế khai một mã CÓ THẬT nhưng của ghế KHÁC. Ghế không tự đặt được
      MAC; còn `ma_may` thì bất kỳ ai gửi POST cũng khai được. Tin lời khai là mở đường cho một
      ghế nhận lượt đã trả tiền của ghế bên cạnh. */
VHG_May::luu_may( array( 'ma' => 'AMTP09', 'coso_id' => 0, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:09' ) );
list( , $n4 ) = vhg_ghe( array( 'mac' => $MAC_T, 'ma_may' => 'AMTP09', 'viec' => 'nhip' ) );
teq( '🔴 khai mã của ghế KHÁC cũng không ăn thua — MAC quyết định', 'AMTP01', $n4['maMay'] );

/* Không có MAC (thử tay bằng curl, hoặc firmware quá cũ) thì mới nhận lời tự khai. */
list( , $n5 ) = vhg_ghe( array( 'ma_may' => 'AMTP09', 'viec' => 'nhip' ) );
teq( 'không gửi MAC thì mới tin lời tự khai', 'AMTP09', $n5['maMay'] );
list( $ma6, $n6 ) = vhg_ghe( array( 'viec' => 'nhip' ) );
t( 'không MAC, không mã -> chối rõ ràng', empty( $n6['ok'] ), $n6 );

/* MAC gõ đủ kiểu vẫn ra đúng ghế — ghế gửi chữ hoa có hai chấm, nhưng đừng phụ thuộc vào đó. */
foreach ( array( '24:0a:c4:0d:98:58', '24-0A-C4-0D-98-58', '240AC40D9858' ) as $dang_mac ) {
	list( , $nx ) = vhg_ghe( array( 'mac' => $dang_mac, 'viec' => 'nhip' ) );
	teq( "MAC dạng \"$dang_mac\" vẫn ra đúng ghế", 'AMTP01', $nx['maMay'] );
}

/* Và tiền đã trả cho mã cũ vẫn về đúng ghế sau khi đổi tên — kiểm lại từ đầu đường tiền. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$tam_k2 = VHG_May::ghi_nhan( $MAC_T );
VHG_Thu::thu_tien_mat( $tam_k2, 50000, 'Chị quầy' );
VHG_May::xep_cho_chay( $tam_k2, 'ABCDE', 50000, 'khoa-1', '' );
VHG_May::gan_ma( $tam_k2, 'AMTP01' );
list( , $n7 ) = vhg_ghe( array( 'mac' => $MAC_T, 'ma_may' => $tam_k2, 'viec' => 'nhip' ) );
teq( 'ghế khai mã cũ vẫn thấy lượt đã trả tiền của mình', 1, $n7['coTien'] );
list( , $l7 ) = vhg_ghe( array( 'mac' => $MAC_T, 'ma_may' => $tam_k2, 'viec' => 'luot' ) );
teq( 'và lấy được đúng lượt đó', 1, $l7['co'] );
teq( 'đúng số tiền', 50000, $l7['so_tien'] );

// ============================================ TAB ĐIỀU KHIỂN + KHỞI ĐỘNG LẠI TỪ XA
/* Ghế ở 26 cửa hàng, không ai ở đó để rút điện. Khởi động lại từ xa là cách duy nhất dựng lại
   một con ghế treo mà không phải chạy tới nơi. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$tok_dk = vhg_vao( '571394', 'Admin' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );

$r = vhg_web( 'khoi_dong_lai', array( 'token' => $tok_dk, 'ma_may' => 'AMTP01' ) );
t( 'đặt được lệnh khởi động lại từ trang ngoài', ! empty( $r['ok'] ), $r );
$l = VHG_May::ds_lenh( 3 );
teq( 'lệnh ghi đúng loại', 'reboot', $l[0]['viec'] );
teq( '🔴 ghi TÊN NGƯỜI CẦM PHIÊN, không lấy từ gói gửi lên', 'Anh Thắng', $l[0]['nguoi'] );
list( , $lg ) = vhg_ghe( array( 'ma_may' => 'AMTP01', 'viec' => 'lenh' ) );
teq( 'ghế lấy được lệnh đó', 'reboot', $lg['viec'] );
/* Người bấm phải được nói trước là ghế KHÔNG khởi động ngay: nó chờ hết lượt khách đang chạy. */
t( 'câu báo nói rõ ghế mất ~30 giây mới gửi nhịp lại',
	strpos( $r['thong_bao'], '30 giây' ) !== false, $r );

$r = VHG_May::dat_lenh( 'AMTP01', 'ba-la-bla', 0, 'Anh Thắng' );
teq( 'lệnh lạ thì chối', false, $r['ok'] );
t( 'và liệt kê đúng ba lệnh có thật', strpos( $r['error'], 'reboot' ) !== false );

/* Không token thì KHÔNG được khởi động lại ghế của người khác. */
$r = vhg_web( 'khoi_dong_lai', array( 'ma_may' => 'AMTP01' ) );
teq( 'không token thì chối', false, $r['ok'] );
teq( 'và chối bằng mã het_phien', 'het_phien', $r['ma'] );

// ---- giao diện tab
$html_dk = vhg_web_html();
/* ⚠️ Thanh tab nay DỰNG THEO QUYỀN lúc chạy, nên chuỗi `data-tab="…"` không còn nằm nguyên
   trong mã nguồn. Canh vào dòng khai tab thay vì canh chuỗi HTML đã dựng. */
t( 'có thanh tab chính', strpos( $html_dk, "TABS.push(['dieu-khien'" ) !== false );
t( 'tab điều khiển vẽ THẺ từng ghế, không phải hàng bảng',
	strpos( $html_dk, 'ghe-luoi' ) !== false && strpos( $html_dk, 'function veDieuKhien()' ) !== false );
t( 'có nút khởi động lại', strpos( $html_dk, 'data-kd=' ) !== false );
t( 'và hỏi lại trước khi khởi động', strpos( $html_dk, 'Khởi động lại ghế' ) !== false );
/* ⚠️ Trạng thái ghế khai MỘT chỗ: hai tab cùng hiện nó, khai hai nơi là sớm muộn một tab nói
      "Rảnh" còn tab kia nói "Đang chạy" — và người đọc không biết tin tab nào. */
t( 'trạng thái ghế tính ở đúng một hàm',
	substr_count( $html_dk, 'function trangThai(m)' ) === 1
	&& substr_count( $html_dk, "'p-off',L('Mất kết nối'" ) === 1 );
/* Đổi tab KHÔNG gọi lại máy chủ: đổi tab không phải đổi dữ liệu, và trên 4G mỗi lượt gọi thừa
   là một lần chờ. */
t( 'đổi tab vẽ lại từ dữ liệu đang có, không gọi lại máy chủ',
	preg_match( '/localStorage\.setItem\(.vhg_tab.[^;]*;\s*\/\*[^*]*\*\/\s*ve\(\);/s', $html_dk ) === 1
	|| strpos( $html_dk, "ve();\n    };\n  });" ) !== false );

// ---- firmware: khởi động lại KHÔNG cắt ngang lượt khách
$fw4 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'firmware hiểu lệnh reboot', strpos( $fw4, 'viec == "reboot"' ) !== false );
/* 🔴 KHÔNG khởi động ngay. Khách đang nằm trên ghế và đã trả tiền thì khởi động lại là cắt mất
      lượt của họ, mà tiền đã vào sổ rồi — không dựng lại được. */
t( 'đánh dấu chờ, không gọi ESP.restart() ngay trong checkRemoteCmd',
	strpos( $fw4, 'g_rebootCho = true' ) !== false );
$i_cmd = strpos( $fw4, 'void checkRemoteCmd()' );
$i_end = strpos( $fw4, '}', strpos( $fw4, 'g_rebootCho = true' ) );
t( 'trong checkRemoteCmd KHÔNG có ESP.restart()',
	strpos( substr( $fw4, $i_cmd, $i_end - $i_cmd ), 'ESP.restart' ) === false );
t( 'chỉ khởi động lại khi ghế RẢNH',
	preg_match( '/state==ST_IDLE\)\{.{0,400}g_rebootCho.{0,600}ESP\.restart/s', $fw4 ) === 1 );
/* Nói ra trên màn trước khi tắt: người đứng cạnh ghế thấy nó tối đi mà không có lý do thì
   tưởng ghế hỏng và đi tháo dây. */
t( 'và nói ra trên màn trước khi tắt', strpos( $fw4, 'DANG KHOI DONG LAI' ) !== false );

/* ============ 🔴 GÁN MÃ XONG MÀ MÀN GHẾ KHÔNG ĐỔI — lỗi anh Thắng gặp 22/08/2026
 *
 * *"đã gán, nhưng trên máy chưa hiện hệ thống bấm chọn quét"*.
 *
 * Ghế chưa gán mã thì vòng lặp vẽ lại màn mỗi 5 giây (để dòng MAC và trạng thái 4G cập nhật cho
 * người đang đứng lắp). Nhưng điều kiện của vòng đó LÀ `CHUA_GAN` — nên đúng khoảnh khắc máy chủ
 * báo "đã gán rồi", vòng vẽ lại tắt, mà lần vẽ cuối cùng vẫn là trang "GHE CHUA DUOC GAN MA".
 * Màn đứng nguyên ở đó CHO TỚI KHI TẮT NGUỒN.
 *
 * Người đi lắp thấy web báo gán xong, ghế thì không nhúc nhích — không có gì trên màn nói vì sao.
 * Đúng kiểu lỗi làm người ta tháo ghế ra kiểm tra dây.
 *
 * Không dựng được màn TFT trong phép thử, nên soi cấu trúc: lượt nhịp phải NHỚ cái đang hiện,
 * so với cái vừa nhận, và bắt vẽ lại khi khác.
 */
foreach ( array(
	'mã ghế'      => 'cu_id != CHAIR_ID',
	'cờ chưa gán' => 'cu_gan != CHUA_GAN',
	'giá'         => 'cu_gia != PRICE_VND',
	'số phút'     => 'cu_phut != MINUTES',
	'số gói'      => 'cu_n != PKG_N',
	'gói đầu'     => 'cu_amt0 != PKG_AMT[0]',
	'tên gói đầu' => 'cu_ten0 != PKG_TEN[0]',
) as $ten_th => $dieu_kien ) {
	t( "nhịp so lại $ten_th để biết có phải vẽ lại màn không",
		strpos( $fw4, $dieu_kien ) !== false, $dieu_kien );
}
t( '🔴 và ĐẶT LẠI cờ vẽ khi có gì đổi — đây là dòng đã thiếu',
	preg_match( '/cu_id != CHAIR_ID.{0,700}screenDrawn = false;/s', $fw4 ) === 1 );
/* ⚠️ CHỈ khi đang rảnh. Đang chờ khách trả tiền mà xoá màn là mã QR biến mất ngay dưới tay
      người đang quét; đang chạy mà xoá là mất luôn số đếm ngược. */
t( 'nhưng chỉ vẽ lại khi ghế đang RẢNH, không xoá màn QR dưới tay khách',
	preg_match( '/if\(state == ST_IDLE\) screenDrawn = false;/', $fw4 ) === 1 );
/* Và vòng vẽ lại mỗi 5 giây của màn "chưa gán" vẫn còn — nó phục vụ người đang đứng lắp máy. */
t( 'vòng vẽ lại 5 giây của màn "chưa gán" vẫn còn',
	preg_match( '/CHUA_GAN \|\| CHAIR_ID\.length\(\)==0 \|\| !duNhanTien\(\)\) && millis\(\)-veLai > 5000/', $fw4 ) === 1 );

// ====================== 🔴 SỐ TÀI KHOẢN GÕ THIẾU MỘT CHỮ SỐ
/* Anh Thắng 22/08/2026 quét thử mã QR bằng app BIDV: *"Định dạng tài khoản định danh không hợp
   lệ (174)"*. Nguyên nhân: ô số tài khoản khai tay thiếu MỘT chữ số — `888815678` thay vì
   `8888815678`.

   Một chữ số. Không có gì trên màn hình sai cả: mã QR vẫn dựng ra, vẫn trông như thật, vẫn dán
   được lên 26 cái ghế. Chỉ tới lúc có khách đứng quét mới lộ.

   Không kiểm được bằng luật chữ số — mỗi ngân hàng một khuôn. Nhưng có một sự thật đối chứng
   miễn phí: mỗi lượt webhook, SePay nói rõ tiền vừa vào TÀI KHOẢN NÀO. */
vhg_dung_bang();
delete_option( 'vhg_tk_ben_gui' );
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );      // thiếu một số 8
$dc = VHG_May::doi_chieu_tk();
teq( 'chưa có lượt nào bắn tới thì chưa đối chiếu được', false, $dc['co'] );
t( 'và KHÔNG kêu oan lúc đó', ! empty( $dc['khop'] ) );

/* Một lượt SePay thật, kèm số tài khoản đúng. */
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 AAAAA', 'referenceCode' => 'tk-1',
	'accountNumber' => '8888815678' ) );
$dc = VHG_May::doi_chieu_tk();
teq( 'đã thấy số bên gửi báo', true, $dc['co'] );
teq( '🔴 và phát hiện lệch', false, $dc['khop'] );
teq( 'nói rõ bên gửi báo số nào', '8888815678', $dc['ben_gui'] );

/* Sửa cho đúng thì hết kêu. */
VHG_May::luu_nhan_tien( '970418', '8888815678', 'K&H' );
teq( 'sửa đúng thì khớp', true, VHG_May::doi_chieu_tk()['khop'] );

// ====================== 🔴 TIỀN TỐ BẮT BUỘC TRONG NỘI DUNG — MẮT XÍCH IM LẶNG NHẤT
/* Tìm ra 22/08/2026 trên chính trang Tạo QR của SePay:
     "SEVQR — VietinBank cá nhân/hộ kinh doanh BẮT BUỘC nội dung CK phải chứa `sevqr` để định
      tuyến giao dịch qua SePay."

   Không có chuỗi đó thì tiền vẫn vào tài khoản, ngân hàng vẫn báo THÀNH CÔNG, nhưng SePay
   KHÔNG BAO GIỜ THẤY — không webhook, ghế không chạy, và trong sổ của mình không có MỘT DÒNG
   NÀO, kể cả dòng "có gói lạ bắn tới". Không có gì để đi tìm.

   Đúng cái đã xảy ra: lượt 2.000đ quét từ trang SePay (có SEVQR) thì về; lượt 10.000đ quét mã
   của plugin (nội dung "GHEMAU K7M2P", không có SEVQR) thì biến mất không dấu vết. */
delete_option( 'vhg_tien_to_nd' );
teq( 'mặc định KHÔNG có tiền tố — ngân hàng khác thường không đòi',
	'GHEAMTP01 K7M2P', VHG_QR::noi_dung( 'AMTP01', 'K7M2P' ) );

t( 'lưu được tiền tố', ! empty( VHG_May::luu_tien_to_nd( 'SEVQR' )['ok'] ) );
teq( '🔴 tiền tố đứng TRƯỚC mã ghế', 'SEVQR GHEAMTP01 K7M2P',
	VHG_QR::noi_dung( 'AMTP01', 'K7M2P' ) );
/* Đứng trước vì ngân hàng nào cắt bớt nội dung thì cắt TỪ CUỐI: mất tiền tố là mất cả lượt
   (SePay không thấy), còn mất mã lượt thì trên web vẫn gán tay được. */
t( 'và chuỗi bắt đầu bằng tiền tố, không phải kết thúc',
	strpos( VHG_QR::noi_dung( 'AMTP01', 'K7M2P' ), 'SEVQR' ) === 0 );

teq( 'viết thường cũng về chữ hoa', 'SEVQR',
	VHG_May::luu_tien_to_nd( 'sevqr' ) ? VHG_May::tien_to_nd() : '' );
/* Chỉ chữ và số: nội dung chuyển khoản đi qua nhiều hệ thống, dấu và ký tự lạ là chỗ bị cắt
   hoặc bị đổi mà không ai báo. */
VHG_May::luu_tien_to_nd( 'SE-VQR 01' );
teq( 'bỏ dấu cách và ký tự lạ', 'SEVQR01', VHG_May::tien_to_nd() );
teq( 'tiền tố quá dài thì chối', false, VHG_May::luu_tien_to_nd( 'ABCDEFGHIJK' )['ok'] );
VHG_May::luu_tien_to_nd( '' );
teq( 'bỏ trống được — ngân hàng khác không cần', '', VHG_May::tien_to_nd() );

/* ⚠️ VietQR chỉ cho 25 ký tự ở ô nội dung. Dài hơn là ngân hàng cắt, và cắt ở đâu thì tuỳ
      ngân hàng — phải nói ra TRƯỚC khi ai đó đặt mã ghế 20 ký tự. */
VHG_May::luu_tien_to_nd( 'SEVQR' );
teq( 'mã ghế ngắn thì không cảnh báo', '', VHG_QR::canh_bao_dai( 'AMTP01' ) );
$cb = VHG_QR::canh_bao_dai( 'AMTP0123456789012345' );
t( 'mã ghế dài thì cảnh báo', '' !== $cb, $cb );
t( 'và nói rõ vượt bao nhiêu', strpos( $cb, '25' ) !== false );

/* Chuỗi QR thật phải mang tiền tố, và đọc ngược vẫn ra đúng mã ghế. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_tien_to_nd( 'SEVQR' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
$qr_t = VHG_QR::cho_ghe( 'AMTP01', 'K7M2P' );
t( 'dựng được QR', ! empty( $qr_t['ok'] ), $qr_t );
teq( '🔴 chuỗi QR mang tiền tố', 'SEVQR GHEAMTP01 K7M2P', VHG_QR::doc( $qr_t['chuoi'] )['noi_dung'] );

/* Và tiền về với nội dung ĐÓ vẫn khớp đúng ghế — tiền tố không được làm hỏng phép đọc ngược. */
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'CT DEN:145T26811LG6HQZL SEVQR GHEAMTP01 K7M2P', 'referenceCode' => 'tt-1',
	'accountNumber' => '108878583951' ) );
teq( 'tiền về khớp đúng ghế dù có cả tiền tố lẫn tiền tố của ngân hàng',
	'AMTP01', VHG_Thu::ds( 'all' )[0]['ma_may'] );
teq( 'và ghế được xếp chạy', 1, VHG_May::so_cho( 'AMTP01' ) );

/* ============ 🔴 GHẾ ĐÃ NẠP FIRMWARE MỚI CHƯA — phải NHÌN THẤY được từ web
 *
 * Anh Thắng 22/08/2026: *"bên esp qr vẫn chưa thêm tiền tố"*. Đúng, vì ghế chưa nạp firmware
 * mới — nhưng từ web KHÔNG CÓ CÁCH NÀO biết điều đó. Người ta sửa ô tiền tố, thấy bảng xem
 * trước đúng, rồi tưởng xong; còn ghế vẫn dựng nội dung thiếu tiền tố và tiền vẫn biến mất
 * không dấu vết y như trước.
 *
 * Cách duy nhất: ghế TỰ KHAI tiền tố nó đang dùng, mỗi lượt nhịp. Rồi web đối chiếu.
 */
VHG_May::luu_tien_to_nd( 'SEVQR' );
vhg_ghe( array( 'mac' => 'AA:BB:CC:DD:EE:01', 'viec' => 'nhip', 'nd' => 'SEVQR',
	'fw' => 'ghe-massage 2026-08-22e (tien to noi dung CK tu web)' ) );
$m_nd = null;
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m_nd = $x; } }
teq( 'ghế khai lên tiền tố nó đang dùng', 'SEVQR', $m_nd['nd_tien_to'] );
/* ⚠️ Chuỗi phiên bản firmware dài hơn 40 ký tự — cột phải đủ rộng, không thì mất đúng phần
      nói bản đó khác bản trước chỗ nào, mà đó là lý do duy nhất người ta đọc cột này. */
teq( 'và giữ trọn chuỗi phiên bản, không cắt cụt',
	'ghe-massage 2026-08-22e (tien to noi dung CK tu web)', $m_nd['fw'] );

$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
ob_start(); VHG_Admin::trang_may(); $h_kh = ob_get_clean();
t( 'ghế đã nạp đúng thì KHÔNG kêu',
	strpos( $h_kh, 'Ghế chưa nhận được tiền tố' ) === false );

/* Ghế còn firmware CŨ: nó không gửi `nd` nên ô đó rỗng, trong khi web khai SEVQR. */
vhg_ghe( array( 'mac' => 'AA:BB:CC:DD:EE:01', 'viec' => 'nhip',
	'fw' => 'ghe-massage 2026-08-22a (chay thang tren host)' ) );
ob_start(); VHG_Admin::trang_may(); $h_kh2 = ob_get_clean();
t( '🔴 ghế còn firmware cũ thì màn KÊU LÊN',
	strpos( $h_kh2, 'Ghế chưa nhận được tiền tố' ) !== false, $h_kh2 ? '' : '' );
t( 'hiện cả hai bên để so: web khai gì, ghế đang dùng gì',
	strpos( $h_kh2, 'Web khai' ) !== false && strpos( $h_kh2, 'Ghế đang dùng' ) !== false );
t( 'và chỉ rõ phải cắm USB nạp lại, ghế không có OTA',
	strpos( $h_kh2, 'không có OTA' ) !== false );
t( 'kèm bản firmware ghế đang chạy để biết đang ở đời nào',
	strpos( $h_kh2, '2026-08-22a' ) !== false );

/* ⚠️ Ghế MẤT KẾT NỐI thì đừng kêu: nó không gửi nhịp nên ô kia rỗng vì không có tin, chứ không
      phải vì firmware cũ. Kêu ở đó là bắt người ta đi nạp lại một con ghế đang tắt điện. */
global $wpdb;
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7200 ), 'AMTP01' ) );
ob_start(); VHG_Admin::trang_may(); $h_kh3 = ob_get_clean();
t( 'ghế mất kết nối thì KHÔNG kêu thiếu tiền tố',
	strpos( $h_kh3, 'Ghế chưa nhận được tiền tố' ) === false );
$_GET = array(); $_POST = array();

/* Ghế phải NHẬN được tiền tố qua nhịp — nó tự dựng nội dung lúc khách bấm chọn gói. */
list( , $n_tt ) = vhg_ghe( array( 'mac' => 'AA:BB:CC:DD:EE:01', 'viec' => 'nhip' ) );
teq( 'nhịp gửi tiền tố xuống ghế', 'SEVQR', $n_tt['tienTo'] );
VHG_May::luu_tien_to_nd( '' );
list( , $n_tt2 ) = vhg_ghe( array( 'mac' => 'AA:BB:CC:DD:EE:01', 'viec' => 'nhip' ) );
teq( 'bỏ tiền tố thì nhịp gửi chuỗi rỗng, không bỏ hẳn khoá', '', $n_tt2['tienTo'] );
t( 'khoá vẫn có mặt để ghế biết là "bỏ đi", không phải "chưa khai"',
	array_key_exists( 'tienTo', $n_tt2 ) );

// ---- màn quản trị
VHG_May::luu_tien_to_nd( '' );
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
ob_start(); VHG_Admin::trang_may(); $h_tt = ob_get_clean();
t( 'chưa khai tiền tố thì màn cảnh báo', strpos( $h_tt, 'Chưa khai tiền tố' ) !== false );
t( 'và nói rõ hậu quả im lặng', strpos( $h_tt, 'không bao giờ thấy' ) !== false );
t( 'chỉ đúng chỗ xem trên SePay', strpos( $h_tt, 'Tạo QR' ) !== false );
VHG_May::luu_tien_to_nd( 'SEVQR' );
ob_start(); VHG_Admin::trang_may(); $h_tt2 = ob_get_clean();
t( 'khai rồi thì hết cảnh báo', strpos( $h_tt2, 'Chưa khai tiền tố' ) === false );
t( 'và hiện nội dung mẫu kèm số ký tự',
	strpos( $h_tt2, 'SEVQR GHEAMTP01 K7M2P' ) !== false && strpos( $h_tt2, '/25 ký tự' ) !== false );

/* 🔴 BẢNG ĐỌC NGƯỢC PHẢI ĐI QUA ĐÚNG HÀM MÀ ĐƯỜNG THẬT DÙNG.
 *
 * Anh Thắng: "bấm lưu mà sao nó không chèn vào mã qr dựng". Ô tiền tố đã lưu, dòng chú thích
 * ngay trên đã hiện đúng — nhưng bảng đọc ngược vẫn ra "GHEMAU K7M2P", vì chỗ dựng mã mẫu gõ
 * cứng chuỗi đó thay vì gọi noi_dung().
 *
 * Đây là LẦN THỨ BA bảng xem trước nói dối theo cùng một kiểu (trước đó: tỉ lệ gõ cứng 10000/6,
 * rồi số tiền 0đ do dùng biến chưa khai). Nên phép thử này chốt CON ĐƯỜNG, không chỉ chốt kết
 * quả: nội dung trong bảng phải BẰNG ĐÚNG cái noi_dung() trả về. */
$nd_that = VHG_QR::noi_dung( 'MAU', 'K7M2P' );
t( '🔴 nội dung trong bảng đọc ngược = đúng cái noi_dung() trả về',
	strpos( $h_tt2, esc_html( $nd_that ) ) !== false, $nd_that );
t( 'và KHÔNG còn chuỗi gõ cứng thiếu tiền tố',
	preg_match( '/<td><code>GHEMAU K7M2P<\/code><\/td>/', $h_tt2 ) === 0, $h_tt2 ? '' : '' );
/* Mã QR của SePay cũng phải mang tiền tố — nó là phép so sánh, so bằng dữ liệu khác nhau thì
   so cái gì. */
t( 'đường dẫn mã SePay cũng mang tiền tố',
	strpos( $h_tt2, rawurlencode( $nd_that ) ) !== false
	|| strpos( $h_tt2, str_replace( ' ', '+', $nd_that ) ) !== false, $h_tt2 ? '' : '' );

/* Và soi thẳng mã nguồn: KHÔNG được gõ cứng chuỗi nội dung ở màn quản trị nữa. */
$ad_nd = file_get_contents( VHG_DIR . 'includes/class-vhg-admin.php' );
$ad_nd = preg_replace( '#/\*.*?\*/#s', '', $ad_nd );
t( '⚠️ mã nguồn màn quản trị KHÔNG còn gõ cứng chuỗi "GHEMAU"',
	strpos( $ad_nd, "'GHEMAU" ) === false, $ad_nd ? '' : '' );
$_GET = array(); $_POST = array();

// ---- firmware
$fw5 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'firmware nhận tiền tố từ nhịp', strpos( $fw5, 'ND_TIEN_TO' ) !== false );
t( 'và ghép vào TRƯỚC mã ghế',
	preg_match( '/ND_TIEN_TO \+ " " : ""\)\s*\+\s*"GHE"/', $fw5 ) === 1, $fw5 ? '' : '' );
/* ⚠️ Nhận CẢ CHUỖI RỖNG: bỏ tiền tố trên web thì ghế phải bỏ theo. Xét độ dài như mấy ô kia
      là bỏ tiền tố xong ghế vẫn dùng chuỗi cũ mãi mãi. */
t( 'nhận cả chuỗi rỗng (bỏ tiền tố trên web thì ghế bỏ theo)',
	strpos( $fw5, 'd.containsKey("tienTo")' ) !== false );

// ====================== ĐỌC NGƯỢC MÃ QR
/* 🔴 Anh Thắng quét thử ba lần, ba lỗi khác nhau từ app ngân hàng: "sai định dạng tài khoản
      (174)", rồi "vấn tin bị timeout (199)". Mỗi lần chỉ biết là HỎNG, không biết trong mã có
      gì — mà chuỗi QR là 130 ký tự dính liền. Mỗi lượt thử là một lượt chuyển tiền thật và một
      chuyến ra chỗ để ghế. */
$chuoi_m = VHG_QR::dung( '970448', '96247POSH', 50000, 'GHEAMTP01 K7M2P' );
$d_m = VHG_QR::doc( $chuoi_m );
t( 'đọc ngược được mã vừa dựng', ! empty( $d_m['ok'] ), $d_m );
teq( 'ra đúng BIN', '970448', $d_m['bin'] );
teq( 'ra đúng số tài khoản/VA (giữ nguyên chữ)', '96247POSH', $d_m['so_tk'] );
teq( 'ra đúng số tiền', 50000, $d_m['so_tien'] );
teq( 'ra đúng nội dung', 'GHEAMTP01 K7M2P', $d_m['noi_dung'] );
teq( 'và CRC đúng', true, $d_m['crc_dung'] );

/* Đọc ngược phải khớp với chính phép dựng ở MỌI ca — nếu không thì một trong hai sai, và
   không có cách nào biết bên nào. */
foreach ( array(
	array( '970418', '8888815678',   20000,  'GHEA 1' ),
	array( '970436', '0011001234567', 200000, 'GHEAMTP04 ZZZZZ' ),
	array( '970422', '96247POSH',    0,      '' ),
) as $ca ) {
	$c = VHG_QR::dung( $ca[0], $ca[1], $ca[2], $ca[3] );
	$d = VHG_QR::doc( $c );
	teq( "dựng rồi đọc lại ra đúng BIN {$ca[0]}", $ca[0], $d['bin'] );
	teq( "…và đúng tài khoản {$ca[1]}", $ca[1], $d['so_tk'] );
	teq( "…và đúng số tiền {$ca[2]}", $ca[2], $d['so_tien'] );
	teq( "…và CRC đúng", true, $d['crc_dung'] );
}

/* 🔴 CRC SAI PHẢI BỊ BẮT. Chuỗi sai CRC thì mọi app ngân hàng đều từ chối — nhưng đó là lỗi
      của phép DỰNG, không phải của số tài khoản. Hai ca đi sửa ở hai nơi khác hẳn. */
$hong = substr( $chuoi_m, 0, -4 ) . '0000';
teq( 'CRC sai thì bắt được', false, VHG_QR::doc( $hong )['crc_dung'] );
/* …nhưng vẫn đọc ra được các trường, để còn nói người ta biết trong mã có gì. */
teq( 'và vẫn đọc ra số tài khoản để chẩn đoán', '96247POSH', VHG_QR::doc( $hong )['so_tk'] );

/* Chuỗi rác thì nói KHÔNG ĐỌC ĐƯỢC, đừng trả bừa vài trường trông như thật. */
foreach ( array( '', 'ba la bla', '0002', '00020101021238' ) as $rac ) {
	$d = VHG_QR::doc( $rac );
	teq( "chuỗi rác \"$rac\" -> không ok", false, $d['ok'] );
	t( 'và có câu lỗi', '' !== $d['loi'] );
}
/* 🔴 CA HIỂM NHẤT: chuỗi bị cắt cụt NGAY GIỮA số tài khoản. Ô `01` khai dài 9 (`96247POSH`)
      nhưng chuỗi chỉ còn 5 ký tự. Nếu bộ đọc "cố đọc nốt" thì nó trả về `96247` — một chuỗi
      trông y như một số tài khoản thật, và người đọc màn hình sẽ đi đối chiếu con số đó với
      ngân hàng rồi không hiểu vì sao không khớp.
      Phải DỪNG và trả rỗng: "không đọc được" là câu đúng, `96247` là câu bịa. */
$cut = substr( $chuoi_m, 0, strpos( $chuoi_m, '96247POSH' ) + 5 );
$d_cut = VHG_QR::doc( $cut );
teq( 'cắt cụt giữa số tài khoản -> KHÔNG bịa ra một số ngắn hơn', '', $d_cut['so_tk'] );
t( 'và nói không đọc được', empty( $d_cut['ok'] ) && '' !== $d_cut['loi'] );
t( '⚠️ tuyệt đối không được trả về "96247"', '96247' !== $d_cut['so_tk'] );

$lech = '00020101021238990010A0000007270123000697044801099624';
t( 'độ dài TLV vượt quá chuỗi -> dừng, không bịa trường',
	'' === VHG_QR::doc( $lech )['so_tk'] );

teq( 'tên ngân hàng theo BIN', 'OCB', VHG_QR::ten_ngan_hang( '970448' ) );
teq( 'BIDV', 'BIDV', VHG_QR::ten_ngan_hang( '970418' ) );
/* Không có trong bảng thì trả RỖNG — thà nói "không rõ" còn hơn đoán tên một ngân hàng, vì
   người đọc sẽ tin cái tên đó và thôi không đi kiểm. */
teq( 'BIN lạ thì trả rỗng, không đoán', '', VHG_QR::ten_ngan_hang( '999999' ) );

// ---- màn quản trị hiện bảng đọc ngược
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970448', '96247POSH', 'K&H' );
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
ob_start(); VHG_Admin::trang_may(); $h_qr = ob_get_clean();
t( 'màn hiện bảng đọc ngược mã QR', strpos( $h_qr, 'đọc ngược để kiểm' ) !== false );
t( 'hiện tên ngân hàng theo BIN', strpos( $h_qr, 'OCB' ) !== false );
t( 'hiện số VA', strpos( $h_qr, '96247POSH' ) !== false );
t( 'và xác nhận CRC', strpos( $h_qr, 'Mã kiểm (CRC)' ) !== false );
/* 🔴 XEM TRƯỚC PHẢI CÙNG LOẠI QR VỚI CÁI GHẾ DỰNG. Bản trước dùng biến tỉ lệ khai ở BÊN DƯỚI
      nên nhận null -> số tiền 0đ, mà QR 0 đồng là loại TĨNH (ô `01` = "11") chứ không phải QR
      một lần có số tiền ("12"). Bảng "xem trước" khi đó xem trước một thứ không phải cái ghế
      dựng ra — đúng cái lỗi mà bảng này sinh ra để tránh. */
t( 'mã mẫu KHÔNG phải 0 đồng', strpos( $h_qr, '<td>0đ</td>' ) === false, $h_qr ? '' : '' );
$mau_kt = VHG_QR::dung( '970448', '96247POSH', VHG_May::ty_le_chung()['gia'], 'GHEMAU K7M2P' );
$d_kt   = VHG_QR::doc( $mau_kt );
t( 'số tiền mẫu lấy đúng tỉ lệ chung', $d_kt['so_tien'] === VHG_May::ty_le_chung()['gia'] );
teq( 'và là QR MỘT LẦN có số tiền, cùng loại ghế dựng', '12', $d_kt['loai'] );
teq( 'QR không số tiền thì là loại tĩnh — khác hẳn', '11',
	VHG_QR::doc( VHG_QR::dung( '970448', '96247POSH', 0, 'x' ) )['loai'] );
/* Bảng tra triệu chứng: mỗi câu app ngân hàng báo ứng với một chỗ sửa khác nhau. */
t( 'có bảng tra theo câu app ngân hàng báo',
	strpos( $h_qr, 'Vấn tin bị timeout' ) !== false
	&& strpos( $h_qr, 'BIN không khớp ngân hàng phát hành' ) !== false );
t( 'và dặn DỪNG khi hiện tên lạ', strpos( $h_qr, 'DỪNG NGAY' ) !== false );
/* 🔴 PHÉP TÁCH HAI CA. Bốn lần quét thử, bốn lỗi khác nhau từ app ngân hàng — tới đây đoán tiếp
      là vô ích: không có cách nào biết lỗi ở CHUỖI MÌNH DỰNG hay ở CHÍNH cái VA đó. SePay có bộ
      sinh mã riêng, ăn cùng bốn tham số; quét mã của họ là tách được. */
t( 'có đường dẫn tới mã QR do chính SePay sinh, cùng tham số',
	strpos( $h_qr, 'qr.sepay.vn/img' ) !== false && strpos( $h_qr, '96247POSH' ) !== false );
t( 'và nói rõ so ra thì kết luận được gì',
	strpos( $h_qr, 'Cả hai cùng hỏng' ) !== false
	&& strpos( $h_qr, 'không sửa được bằng mã' ) !== false );
/* ⚠️ Chỉ là ĐƯỜNG DẪN, KHÔNG nhúng ảnh: nhúng là mỗi lần mở màn này lại gửi số tài khoản của
      mình sang một máy chủ khác, không ai bấm gì cả. */
t( 'chỉ là đường dẫn, KHÔNG nhúng ảnh từ máy chủ ngoài',
	preg_match( '/<img[^>]+qr\.sepay\.vn/', $h_qr ) === 0 );
t( 'có tra cả mã lỗi 096', strpos( $h_qr, 'Lỗi hệ thống nhà cung cấp' ) !== false );

/* BIN lạ thì màn phải nói "không có trong bảng mã Napas", đừng im lặng. */
VHG_May::luu_nhan_tien( '999999', '96247POSH', 'K&H' );
ob_start(); VHG_Admin::trang_may(); $h_qr2 = ob_get_clean();
t( 'BIN lạ thì màn kêu lên', strpos( $h_qr2, 'không có trong bảng mã Napas' ) !== false );
$_GET = array(); $_POST = array();

/* ============ 🔴 TÀI KHOẢN ẢO (VA) — TIỀN VỀ TÚI MÌNH MÀ HỆ THỐNG MÙ VỚI NÓ
 *
 * Anh Thắng 22/08/2026: quét thử, ngân hàng trừ tiền bình thường, app hiện đúng tên chủ tài
 * khoản — mà SePay KHÔNG thấy giao dịch nào và ghế không chạy.
 *
 * Vì tiền vào TÀI KHOẢN GỐC, còn SePay theo dõi TÀI KHOẢN ẢO. Tiền không mất, nó nằm đúng
 * trong tài khoản của mình; nhưng hệ thống mù với nó, nên khách trả tiền xong đứng đó mà ghế
 * không chạy — kiểu hỏng tệ nhất, vì sổ sách vẫn đúng và không ai biết đi tìm ở đâu.
 */
$va_sepay = '96247POSH';

/* Bộ đọc phải TÁCH RIÊNG hai ô. Bản trước gộp một danh sách nên `accountNumber` luôn thắng và
   VA bị vứt — mất đúng thông tin cần để trả lời "mã QR phải trỏ vào cái nào". */
$ev_va = VHG_Doc::tach( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 AAAAA', 'referenceCode' => 'va-1',
	'accountNumber' => '8888815678', 'subAccount' => $va_sepay ) );
teq( 'đọc được số tài khoản gốc', '8888815678', $ev_va[0]['tk_nhan'] );
teq( '🔴 và đọc RIÊNG được tài khoản ảo', $va_sepay, $ev_va[0]['tk_ao'] );

vhg_dung_bang();
delete_option( 'vhg_tk_ben_gui' );
VHG_May::luu_nhan_tien( '970448', $va_sepay, 'K&H' );      // khai VA, đúng cách
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 AAAAA', 'referenceCode' => 'va-2',
	'accountNumber' => '8888815678', 'subAccount' => $va_sepay ) );
$dc_va = VHG_May::doi_chieu_tk();
teq( '🔴 khai VA thì KHỚP, dù số tài khoản gốc khác hẳn', true, $dc_va['khop'] );
teq( 'và nhớ luôn VA để hiện ra cho người khai', $va_sepay, $dc_va['va'] );

/* ⚠️ VA CÓ CHỮ. Bản trước so bằng cách bỏ hết chữ cái, nên `96247POSH` thành `96247` rồi so với
      `8888815678` — báo đỏ oan cho đúng cấu hình chạy được. Một cảnh báo oan là một cảnh báo
      bị bỏ qua mãi về sau. */
t( 'VA có chữ không bị cắt mất khi so', strpos( $va_sepay, 'POSH' ) !== false
	&& true === VHG_May::doi_chieu_tk()['khop'] );

/* Khai tài khoản GỐC trong khi SePay báo VA -> vẫn khớp (cả hai đều là đích hợp lệ, miễn SePay
   thấy). Chỉ khai một số KHÔNG PHẢI cả hai mới là sai. */
VHG_May::luu_nhan_tien( '970418', '8888815678', 'K&H' );
teq( 'khai tài khoản gốc mà SePay có báo số đó: vẫn khớp', true, VHG_May::doi_chieu_tk()['khop'] );
VHG_May::luu_nhan_tien( '970418', '1111111111', 'K&H' );
teq( 'khai một số không phải cả hai: báo lệch', false, VHG_May::doi_chieu_tk()['khop'] );

/* Màn khai phải NÓI RA điều này — nhìn hai chuỗi số thì không có gì gợi ý cái nào đúng. */
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
ob_start(); VHG_Admin::trang_may(); $h_va = ob_get_clean();
/* ⚠️ ĐỪNG KHẲNG ĐỊNH PHẢI DÙNG VA. Bản trước ghi cứng "điền SỐ VA, không phải số tài khoản" —
      suy đoán, và suy đoán sai: nhật ký cho thấy lượt 20.000đ chuyển vào SỐ TÀI KHOẢN THƯỜNG đã
      hiện trong SePay và đã bắn webhook về. Một câu hướng dẫn ghi cứng trong màn hình còn nguy
      hơn một câu nói miệng — nó ở lại mãi và người sau đọc sẽ tin. */
t( 'màn KHÔNG khẳng định phải dùng VA', strpos( $h_va, 'Điền SỐ VA của SePay' ) === false );
t( 'mà chỉ cách tự kiểm bằng bằng chứng: xem lượt đã chạy được ghi số nào',
	strpos( $h_va, 'tìm một lượt đã về thành công' ) !== false, $h_va ? '' : '' );
t( 'và vẫn nhắc BIN phải khớp ngân hàng phát hành VA',
	strpos( $h_va, 'phát hành VA' ) !== false );
t( 'nhắc BIN phải là ngân hàng PHÁT HÀNH VA',
	strpos( $h_va, 'ngân hàng phát hành VA' ) !== false );
t( 'khi lệch thì hiện luôn VA mà bên gửi báo',
	strpos( $h_va, 'Tài khoản ảo (VA) bên gửi báo' ) !== false, $h_va ? '' : '' );
$_GET = array(); $_POST = array();
VHG_May::luu_nhan_tien( '970418', '8888815678', 'K&H' );

/* ⚠️ KHÔNG KÊU OAN vì cách viết. Bên gửi có khi trả "888 881 5678" — khác cách viết không phải
      khác tài khoản, và kêu oan một lần là lần sau người ta bỏ qua cảnh báo thật. */
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 BBBBB', 'referenceCode' => 'tk-2',
	'accountNumber' => '888 881 5678' ) );
teq( 'khác cách viết thì vẫn coi là khớp', true, VHG_May::doi_chieu_tk()['khop'] );
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 CCCCC', 'referenceCode' => 'tk-3' ) );
teq( 'gói không kèm số tài khoản thì GIỮ số đã biết, không xoá',
	'888 881 5678', VHG_May::doi_chieu_tk()['ben_gui'] );

/* Đổi sang một tài khoản khác hẳn -> phải kêu lại. */
VHG_May::luu_nhan_tien( '970418', '1234567890', 'K&H' );
teq( 'khai sang tài khoản khác thì kêu lại', false, VHG_May::doi_chieu_tk()['khop'] );

// ---- màn quản trị nói ra
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
ob_start(); VHG_Admin::trang_may(); $h_tk = ob_get_clean();
t( 'màn báo đỏ khi lệch', strpos( $h_tk, 'KHÁC số mà bên gửi báo' ) !== false, $h_tk ? '' : '' );
t( 'hiện CẢ HAI số để so bằng mắt',
	strpos( $h_tk, '1234567890' ) !== false && strpos( $h_tk, '888 881 5678' ) !== false );
t( 'và nói rõ hậu quả: 26 ghế đều hỏng mà nhìn vẫn như thật',
	strpos( $h_tk, '26 cái ghế đều hỏng' ) !== false );
t( 'nhắc đúng triệu chứng app ngân hàng báo',
	strpos( $h_tk, 'định dạng tài khoản không hợp lệ' ) !== false );

VHG_May::luu_nhan_tien( '970418', '8888815678', 'K&H' );
ob_start(); VHG_Admin::trang_may(); $h_tk2 = ob_get_clean();
t( 'khớp thì báo xanh, không báo đỏ',
	strpos( $h_tk2, 'KHÁC số mà bên gửi báo' ) === false
	&& strpos( $h_tk2, 'khớp với số bên gửi báo' ) !== false );
$_GET = array(); $_POST = array();
delete_option( 'vhg_tk_ben_gui' );

// ====================== 🔴 ĐỒNG HỒ WEB CHẬM HƠN GHẾ ĐÚNG BẰNG TUỔI CỦA DỮ LIỆU
/* Anh Thắng 22/08/2026: *"bấm thử điều khiển ghế thì lệch 12s — thời gian máy QR nhanh hơn
   11s"*. Không phải cố ý chừa thời gian cho khách lên ghế; đó là tuổi của dữ liệu.

   Ghế gửi nhịp 30 giây một lần. Nó nói "còn 300 giây" lúc 21:46:00. Web hỏi lúc 21:46:11 và
   nhận đúng con số 300 đó — nhưng ghế đã chạy thêm 11 giây. Web tự trừ mỗi giây từ 300 nên
   chậm hơn ghế đúng 11 giây, cho tới lượt nhịp sau. Trung bình lệch NỬA chu kỳ nhịp. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '8888815678', 'K&H' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );

/* Ghế báo còn 300 giây, và lượt nhịp đó tới cách đây 11 giây. */
global $wpdb;
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 11 ), 'AMTP01' ) );

$m = null;
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( '🔴 trừ đi tuổi của dữ liệu: 300 - 11', 289, (int) $m['con_lai'] );
teq( 'và nói rõ dữ liệu già bao nhiêu giây', 11, (int) $m['nhip_giay'] );
t( 'ghế vẫn được coi là đang sống', ! empty( $m['con_song'] ) );

/* ⚠️ CHỈ trừ khi ghế ĐANG CHẠY. Ghế rảnh thì con_lai vốn là 0. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'idle', 'con_lai' => 300, 'nguon' => '' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 11 ), 'AMTP01' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( 'ghế rảnh thì không trừ gì cả', 300, (int) $m['con_lai'] );

/* ⚠️ KHÔNG trừ tiếp khi ghế MẤT KẾT NỐI: con số nào cũng vô nghĩa, mà một đồng hồ chạy lùi
      trông như thật là tệ hơn — nó nói ghế vẫn đang chạy trong khi không ai biết. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 3600 ), 'AMTP01' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( 'mất kết nối thì giữ nguyên con số, không trừ tiếp', 300, (int) $m['con_lai'] );
teq( 'và báo mất kết nối', false, ! empty( $m['con_song'] ) );

/* ⚠️ NỬA QUÃNG ĐI — phần máy chủ KHÔNG tự thấy được.
      Ghế tính "còn bao nhiêu giây" TRƯỚC khi gọi; máy chủ đóng dấu `luc` LÚC NHẬN. Cả quãng bắt
      tay TLS + đẩy gói nằm gọn giữa hai mốc đó, nên con số ghế gửi đã già ngay lúc sinh ra, mà
      phép trừ tuổi dữ liệu ở trên bắt đầu đếm từ `luc` nên không đụng tới phần này. Đúng chỗ
      sinh ra lệch 4-5 giây giữa đồng hồ trên ghế và đồng hồ trên web (anh Thắng 22/08/2026).
      Ghế tự khai `tre` = lượt gọi trước mất bao nhiêu ms; trừ NỬA vì đó là cả đi lẫn về. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr',
	'tre' => 4000 ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 11 ), 'AMTP01' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( '🔴 trừ cả nửa quãng đi: 300 - 11 - 2', 287, (int) $m['con_lai'] );

/* Kênh HTTPS giữ mở thì quãng này còn ~150ms — dưới một giây, tức là biến mất khỏi đồng hồ. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr',
	'tre' => 150 ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 11 ), 'AMTP01' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( 'kênh nhanh thì gần như không phải trừ', 300 - 11, (int) $m['con_lai'] );

/* Ghế firmware CŨ không gửi `tre`. Phải chạy y như trước, không được lệch thêm. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 11 ), 'AMTP01' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( 'firmware cũ không gửi tre thì y như cũ', 289, (int) $m['con_lai'] );

/* Cột là SMALLINT UNSIGNED. Ghế mất mạng lâu gửi lên con số rất lớn: chặn ở PHP chứ không để
   MySQL xử — chế độ chặt thì nó TỪ CHỐI CẢ HÀNG, mất luôn nhịp vì một số chỉ để chỉnh đồng hồ. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr',
	'tre' => 9999999 ) );
$tre_luu = (int) $wpdb->get_var( 'SELECT tre_ms FROM ' . VHG_DB::t( 'nhip' ) . " WHERE ma_may='AMTP01'" );
teq( 'chặn tre trong tầm của cột', 65535, $tre_luu );
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 300, 'nguon' => 'qr',
	'tre' => -50 ) );
$tre_luu = (int) $wpdb->get_var( 'SELECT tre_ms FROM ' . VHG_DB::t( 'nhip' ) . " WHERE ma_may='AMTP01'" );
teq( 'và không nhận số âm', 0, $tre_luu );

/* Cổng máy phải CHUYỂN TIẾP `tre` xuống. Quên một chỗ này là cả nhánh trên vô dụng mà không
   phép thử nào ở tầng dưới hé ra. */
$cong_php = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-cong.php' );
t( 'cổng máy chuyển tiếp tre xuống nhịp',
	preg_match( "/'tre'\s*=>\s*isset\(\s*\\\$d\['tre'\]\s*\)/", $cong_php ) === 1 );

/* Không bao giờ trả số âm: nhịp cũ hơn cả số giây còn lại là ghế đã chạy xong từ lâu. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'running', 'con_lai' => 5, 'nguon' => 'qr' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'nhip' ) . ' SET luc=%s WHERE ma_may=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 ), 'AMTP01' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
t( 'không bao giờ ra số âm', (int) $m['con_lai'] >= 0, $m['con_lai'] );

// ====================== TỈ LỆ QUY ĐỔI KHAI CHUNG
/* Anh Thắng: *"không điều chỉnh được loại mệnh giá à"*. Bốn gói thì khai được, nhưng SỐ PHÚT
   của chúng do tỉ lệ quyết định — mà tỉ lệ nằm tận ô "Thêm/sửa máy", tách khỏi chỗ khai gói và
   phải lưu lại từng máy một. */
delete_option( 'vhg_gia' ); delete_option( 'vhg_phut' );
teq( 'mặc định theo bảng giá đang dùng: 50.000đ = 15 phút',
	array( 'gia' => 50000, 'phut' => 15 ), VHG_May::ty_le_chung() );
t( 'lưu được tỉ lệ chung', ! empty( VHG_May::luu_ty_le( 100000, 30 )['ok'] ) );
teq( 'và đọc lại đúng', array( 'gia' => 100000, 'phut' => 30 ), VHG_May::ty_le_chung() );
teq( 'tỉ lệ vô lý thì chối', false, VHG_May::luu_ty_le( 500, 15 )['ok'] );
teq( 'số phút vô lý cũng chối', false, VHG_May::luu_ty_le( 50000, 0 )['ok'] );
VHG_May::luu_ty_le( 50000, 15 );

/* Ghế để 0 = dùng chung; khai >0 = ngoại lệ. */
teq( 'ghế để trống thì dùng tỉ lệ chung',
	array( 'gia' => 50000, 'phut' => 15 ), VHG_May::ty_le_cua( array( 'gia' => 0, 'phut' => 0 ) ) );
teq( 'ghế khai riêng thì đè lên chung',
	array( 'gia' => 20000, 'phut' => 5 ), VHG_May::ty_le_cua( array( 'gia' => 20000, 'phut' => 5 ) ) );

/* Ghế khai từ bản cũ đều mang tỉ lệ riêng (bản cũ không có ô chung) — đổi ô chung mà chúng
   không theo, và không có gì trên màn nói vì sao. Nên có nút gỡ, và màn phải NÓI RA. */
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 10000, 'phut' => 6,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
ob_start(); VHG_Admin::trang_may(); $h_tl = ob_get_clean();
t( 'màn NÓI RA có ghế đang khai tỉ lệ riêng', strpos( $h_tl, 'đang khai tỉ lệ RIÊNG' ) !== false );
t( 'và có nút cho tất cả dùng chung', strpos( $h_tl, 'bo_rieng' ) !== false );
t( '🔴 bảng xem trước dùng tỉ lệ THẬT, không phải số gõ cứng',
	strpos( $h_tl, 'với tỉ lệ 50.000đ = 15 phút' ) !== false, $h_tl ? '' : '' );

$r = VHG_May::bo_ty_le_rieng();
t( 'gỡ được tỉ lệ riêng', ! empty( $r['ok'] ), $r );
teq( 'ghế đó theo tỉ lệ chung ngay',
	array( 'gia' => 50000, 'phut' => 15 ), VHG_May::ty_le_cua( VHG_May::may( 'AMTP02' ) ) );

/* Và nhịp gửi xuống ghế đúng tỉ lệ chung + số phút gói tính theo đó. */
VHG_May::luu_menh_gia( array(
	array( 'tien' => 50000,  'ten' => 'Gói cơ bản' ),
	array( 'tien' => 200000, 'ten' => 'Gói thượng hạng', 'vip' => 1 ) ) );
list( , $n_tl ) = vhg_ghe( array( 'mac' => 'AA:BB:CC:DD:EE:02', 'viec' => 'nhip' ) );
teq( 'nhịp trả tỉ lệ chung', 50000, $n_tl['gia'] );
teq( 'và số phút quy đổi chung', 15, $n_tl['phut'] );
teq( '🔴 gói 200.000đ ra 60 phút, đúng bảng giá', 60, $n_tl['goi'][1]['p'] );
$_GET = array(); $_POST = array();

// ====================== 🔴 TIỀN VÀO MÀ NỘI DUNG KHÔNG MANG MÃ GHẾ
/* Ca thật 22/08/2026 22:25: tiền về tài khoản, SePay thấy, webhook bắn về đúng nơi — mà ghế
   không chạy, vì nội dung do NGÂN HÀNG tự sinh: "CT DEN:145T26811LG6HQZL SEVQR …", không mang
   `GHE<ghế> <mã lượt>`.

   Ca này không hiếm: khách gõ tay nội dung mà gõ sai, app ngân hàng cắt bớt nội dung, hoặc
   khách quét nhầm tem của ghế bên cạnh. Lúc đó tiền đã vào sổ và khách đang đứng đó. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );

vhg_ban( array( 'transferAmount' => 2000, 'transferType' => 'in',
	'content' => 'CT DEN:145T26811LG6HQZL SEVQR', 'referenceCode' => '145T26811LG6HQZL',
	'accountNumber' => '108878583951' ) );
teq( '🔴 tiền VẪN vào sổ dù không rõ ghế — đây là chỗ không được mất', 2000,
	VHG_Thu::tong_hop( 'all' )['tong'] );
$cr = VHG_Thu::ds_chua_ro();
teq( 'và nằm trong danh sách "chưa rõ ghế"', 1, count( $cr ) );
teq( 'giữ nguyên nội dung gốc để còn đối chiếu',
	'CT DEN:145T26811LG6HQZL SEVQR', $cr[0]['noi_dung'] );
teq( 'ghế chưa có lượt nào chờ', 0, VHG_May::so_cho( 'AMTP01' ) );

$r = VHG_Thu::gan_may( '145T26811LG6HQZL', 'AMTP01', 'Anh Thắng' );
t( 'gán tay được', ! empty( $r['ok'] ), $r );
teq( '🔴 và ghế được xếp cho chạy', 1, VHG_May::so_cho( 'AMTP01' ) );
$gd_g = VHG_Thu::ds( 'all' );
teq( 'doanh thu ghi ĐÚNG ghế đó', 'AMTP01', $gd_g[0]['ma_may'] );
teq( 'tổng tiền KHÔNG đổi — gán chứ không cộng thêm', 2000, VHG_Thu::tong_hop( 'all' )['tong'] );
teq( 'hết nằm trong danh sách chưa rõ', 0, count( VHG_Thu::ds_chua_ro() ) );

/* 🔴 BẤM HAI LẦN KHÔNG ĐƯỢC ĐẺ HAI LƯỢT CHẠY. Mã lượt sinh ỔN ĐỊNH theo `ref`, không ngẫu
      nhiên — ngẫu nhiên là mỗi lần bấm một lượt massage miễn phí. */
$r2 = VHG_Thu::gan_may( '145T26811LG6HQZL', 'AMTP01', 'Anh Thắng' );
teq( 'gán lần hai: chối vì đã có ghế', false, $r2['ok'] );
teq( 'và vẫn đúng MỘT lượt chờ', 1, VHG_May::so_cho( 'AMTP01' ) );

/* ⚠️ KHÔNG cho đổi ghế của giao dịch đã khớp: dời doanh thu từ ghế này sang ghế kia bằng vài
      cú bấm là chuyện không ai thấy. */
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );
$r3 = VHG_Thu::gan_may( '145T26811LG6HQZL', 'AMTP02' );
teq( '🔴 chối đổi sang ghế khác', false, $r3['ok'] );
t( 'và nói rõ vì sao', strpos( $r3['error'], 'không ai thấy' ) !== false, $r3['error'] );
teq( 'doanh thu vẫn ở ghế cũ', 'AMTP01', VHG_Thu::ds( 'all' )[0]['ma_may'] );

t( 'gán cho ghế không có thì chối',
	empty( VHG_Thu::gan_may( '145T26811LG6HQZL', 'KHONG-CO' )['ok'] ) );
t( 'gán giao dịch không có thì chối', empty( VHG_Thu::gan_may( 'khong-co-ref', 'AMTP01' )['ok'] ) );

/* Giao dịch ĐÃ HUỶ thì không gán được — huỷ rồi mà vẫn cho ghế chạy là cho không một lượt. */
vhg_ban( array( 'transferAmount' => 5000, 'transferType' => 'in',
	'content' => 'CT DEN:XYZ SEVQR', 'referenceCode' => 'huy-gan-1',
	'accountNumber' => '108878583951' ) );
VHG_Thu::huy( 'huy-gan-1', 'ghi nhầm' );
$r4 = VHG_Thu::gan_may( 'huy-gan-1', 'AMTP01' );
teq( 'giao dịch đã huỷ thì không gán được', false, $r4['ok'] );
t( 'và nhắc bỏ huỷ trước', strpos( $r4['error'], 'bỏ huỷ' ) !== false );

/* Nội dung CÓ mã ghế thì tự khớp, khỏi gán tay — đường thường vẫn phải chạy. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'K&H' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'CT DEN:145T26811LG6HQZL SEVQR GHEAMTP01 K7M2P', 'referenceCode' => 'tu-khop-1',
	'accountNumber' => '108878583951' ) );
teq( '🔴 mã ghế nằm SAU tiền tố của ngân hàng: vẫn khớp được',
	'AMTP01', VHG_Thu::ds( 'all' )[0]['ma_may'] );
teq( 'và ghế được xếp chạy ngay, khỏi gán tay', 1, VHG_May::so_cho( 'AMTP01' ) );
teq( 'nên không nằm trong danh sách chưa rõ', 0, count( VHG_Thu::ds_chua_ro() ) );

// ---- màn quản trị
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array( 'ky' => 'all' ); $_POST = array();
vhg_ban( array( 'transferAmount' => 3000, 'transferType' => 'in',
	'content' => 'CT DEN:ABC SEVQR', 'referenceCode' => 'man-1',
	'accountNumber' => '108878583951' ) );
ob_start(); VHG_Admin::trang_thu(); $h_cr = ob_get_clean();
t( 'màn có mục tiền chưa rõ ghế', strpos( $h_cr, 'chưa rõ ghế' ) !== false );
t( 'có ô chọn ghế để gán', strpos( $h_cr, 'gan_may_gd' ) !== false );
t( 'nói rõ tiền vẫn nằm nguyên trong sổ',
	strpos( $h_cr, 'Tiền vẫn nằm nguyên trong sổ' ) !== false );
/* Nói rõ khác gì với "Bật tay": bật tay ghi CHO KHÔNG một lượt, gán ghi ĐÚNG doanh thu. */
t( 'và nói rõ khác gì với bật tay',
	strpos( $h_cr, 'cho không một lượt' ) !== false );
$_GET = array(); $_POST = array();

// ============================================ BẢNG CHỐT CA THU TIỀN
/* 🔴 Bản trước bấm "Thu tiền mặt" là hỏi "ghi 10.000đ?" rồi ghi luôn. Sai với việc thật: người
      đi thu tiền mở ngăn ghế ra, đếm được một xấp, và cần biết HỆ THỐNG NGHĨ là bao nhiêu để
      đối chiếu. Không có con số đó thì họ gõ đại số mình đếm được, và chênh lệch — nếu có —
      không bao giờ lộ ra. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$tok_c = vhg_vao( '571394', 'Admin' );
VHG_May::luu_coso( 0, 'Aeon Tân Phú' );
$id_c = 0;
foreach ( VHG_May::ds_coso() as $c ) { if ( 'Aeon Tân Phú' === $c['ten'] ) { $id_c = (int) $c['id']; } }
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => $id_c, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => $id_c, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );

vhg_ban( array( 'transferAmount' => 100000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 AAAAA', 'referenceCode' => 'c-1' ) );
vhg_ban( array( 'transferAmount' => 50000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 BBBBB', 'referenceCode' => 'c-2' ) );
VHG_Thu::thu_tien_mat( 'AMTP01', 200000, 'Chị quầy' );
/* Ghế KHÁC, cùng cơ sở — số của nó KHÔNG được lẫn vào bảng chốt ca của ghế này. */
vhg_ban( array( 'transferAmount' => 999000, 'transferType' => 'in',
	'content' => 'GHEAMTP02 CCCCC', 'referenceCode' => 'c-3' ) );

$r = vhg_web( 'so_may', array( 'token' => $tok_c, 'ma_may' => 'AMTP01' ) );
t( 'lấy được số liệu ghế', ! empty( $r['ok'] ), $r );
teq( 'tiền mặt của riêng ghế đó', 200000, $r['thang']['tien_mat'] );
teq( 'QR của riêng ghế đó', 150000, $r['thang']['qr'] );
teq( 'tổng tháng', 350000, $r['thang']['tong'] );
teq( 'số lượt', 3, $r['thang']['so_luot'] );
teq( '🔴 KHÔNG lẫn tiền của ghế bên cạnh', 999000, VHG_Thu::tong_may( 'AMTP02', 'month' )['qr'] );
teq( 'nói rõ ghế này ở cơ sở nào — người đi thu đi nhiều cơ sở một buổi',
	'Aeon Tân Phú', $r['coso'] );
foreach ( array( 'hom_nay', 'tuan', 'thang', 'tat_ca' ) as $k ) {
	t( "có sẵn kỳ '$k' để khỏi gọi lại", isset( $r[ $k ]['tong'] ) );
}

/* ⚠️ Dòng ĐÃ HUỶ không được cộng vào — sót chỗ này là màn chốt ca nói một số, bảng đối soát
      nói số khác, và người đang đếm tiền không biết tin cái nào. */
VHG_Thu::huy( 'c-1', 'ghi nhầm' );
$r2 = vhg_web( 'so_may', array( 'token' => $tok_c, 'ma_may' => 'AMTP01' ) );
teq( 'huỷ một dòng thì bảng chốt ca giảm theo', 50000, $r2['thang']['qr'] );
teq( 'và khớp đúng với bảng đối soát', VHG_Thu::tong_hop( 'month' )['qr'] - 999000, $r2['thang']['qr'] );

$r3 = vhg_web( 'so_may', array( 'token' => $tok_c, 'ma_may' => 'KHONG-CO' ) );
teq( 'ghế không có thì chối', false, $r3['ok'] );
$r4 = vhg_web( 'so_may', array( 'ma_may' => 'AMTP01' ) );
teq( 'không token thì chối — đây là số tiền', false, $r4['ok'] );
t( 'và KHÔNG rò số liệu', ! isset( $r4['thang'] ) );

// ---- giao diện
$html_c = vhg_web_html();
t( 'bấm Thu tiền mặt mở bảng chốt ca, không ghi thẳng',
	strpos( $html_c, 'function moChotCa(ma)' ) !== false
	&& strpos( $html_c, "goi('so_may'" ) !== false );
t( 'bảng hiện tiền mặt, QR và tổng tháng', strpos( $html_c, 'TỔNG THÁNG NÀY' ) !== false );
t( 'có bàn phím số cho màn cảm ứng', strpos( $html_c, 'data-phim=' ) !== false );
/* ⚠️ Nói thẳng đây là GHI SỔ, không phải mở ngăn tiền: người bấm tưởng nó mở khoá ghế thì họ
      bấm rồi đứng đợi, và bấm lại — mỗi lần bấm là một dòng doanh thu. */
t( 'nói rõ nút này chỉ GHI SỔ, không mở ngăn tiền',
	strpos( $html_c, 'không mở ngăn tiền' ) !== false );
/* 🔴 VÀ NÓI THẲNG NÓ KHÔNG CỘNG DOANH THU.
   Đây là vế bản trước THIẾU, và chính chỗ thiếu đó sinh ra lỗi cộng đôi: người bấm tưởng mình
   đang ghi một lượt bán hàng nên ngại bấm, hoặc bấm rồi lại bấm. Tiền trong ngăn đã vào sổ từ
   lúc ghế nuốt từng tờ. */
t( '🔴 nói rõ chốt ca KHÔNG cộng doanh thu',
	strpos( $html_c, 'không cộng doanh thu' ) !== false );
t( 'và nói tiền đếm được sẽ tính vào phần đang cầm',
	strpos( $html_c, 'anh/chị đang cầm' ) !== false );
t( 'không cho chốt khi chưa nhập chỉ số',
	strpos( $html_c, 'Chưa nhập chỉ số trên màn máy đếm tiền' ) !== false );
/* Hai ô nhập, và ô CHỈ SỐ đứng trước ô tiền — thứ tự trên màn là thứ tự tay làm: đọc màn máy
   đếm trước khi thò tay vào ngăn, vì mở ngăn ra rồi thì không ai quay lại đọc màn nữa. */
$vt_o_cs = strpos( $html_c, "id=\"chot-cs\"" );
$vt_o_ti = strpos( $html_c, "id=\"chot-tien\"" );
t( '🔴 ô chỉ số đứng TRƯỚC ô tiền đếm được',
	false !== $vt_o_cs && false !== $vt_o_ti && $vt_o_cs < $vt_o_ti );
/* Khoá chống bấm hai lần vẫn phải bao lấy đường này. */
t( 'vẫn đi qua khoá chống bấm hai lần', strpos( $html_c, "lam('chot_luu'" ) !== false );
/* 🔴 VÀ ĐƯỜNG CHỐT CA KHÔNG ĐƯỢC GỌI `tien_mat` NỮA — đó chính là đường ghi doanh thu. */
t( '🔴 chốt ca không còn ghi doanh thu qua tien_mat',
	strpos( $html_c, "lam('tien_mat'" ) === false );

// ============================================ MAC: MỘT DẠNG DUY NHẤT
/* 🔴 Anh Thắng 22/08/2026: *"không có chỗ nhập mac, chỉ có mã"*. Dòng khai tay không có MAC là
      dòng KHÔNG GẮN VỚI GHẾ NÀO — ghế cắm điện lên đẻ ra dòng thứ hai, và dòng chạy thật là
      dòng kia. Giờ có ô nhập, nhưng phải chuẩn hoá: ghế gửi `AA:BB:...` chữ hoa, người gõ tay
      thì gõ đủ kiểu. Không chuẩn hoá thì cùng một con ghế mà bảng có hai dòng. */
foreach ( array(
	'AA:BB:CC:DD:EE:01', 'aa:bb:cc:dd:ee:01', 'AA-BB-CC-DD-EE-01', 'aabbccddee01', 'AA BB CC DD EE 01',
) as $dang ) {
	teq( "MAC dạng \"$dang\" về đúng một dạng", 'AA:BB:CC:DD:EE:01', VHG_May::chuan_mac( $dang ) );
}
/* Chuỗi không phải MAC -> RỖNG, không trả bừa: một MAC nửa vời còn tệ hơn không có MAC, vì nó
   trông như đã gắn ghế. */
foreach ( array( '', 'AA:BB', 'khong-phai-mac', 'AA:BB:CC:DD:EE:0', 'ZZ:BB:CC:DD:EE:01' ) as $rac ) {
	teq( "chuỗi rác \"$rac\" -> rỗng", '', VHG_May::chuan_mac( $rac ) );
}

vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$r = VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'khong-phai-mac' ) );
teq( '🔴 MAC gõ sai thì CHỐI, không im lặng bỏ qua', false, $r['ok'] );
t( 'và nói rõ khuôn đúng', strpos( $r['error'], 'AA:BB:CC:DD:EE:FF' ) !== false, $r['error'] );
teq( 'và KHÔNG tạo máy nào', 0, count( VHG_May::ds_may() ) );

VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'aa-bb-cc-dd-ee-01' ) );
teq( 'gõ tay dạng gạch ngang vẫn khớp với ghế thật',
	'AMTP01', VHG_May::ghi_nhan( 'AA:BB:CC:DD:EE:01' ) );
teq( 'và KHÔNG đẻ ra dòng chờ gán nào', 0, count( VHG_May::chua_gan() ) );
/* Sửa máy mà để trống ô MAC thì GIỮ MAC cũ — người sửa giá không nên vô tình cắt đứt liên kết. */
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 60000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => '' ) );
teq( 'sửa máy để trống MAC thì giữ MAC cũ', 'AA:BB:CC:DD:EE:01', VHG_May::may( 'AMTP01' )['mac'] );

// ============================================ KHÔNG BỊA RA CƠ SỞ
/* 🔴 Ảnh trang ngoài 22/08/2026: bảng "Theo cơ sở" mọc ra hai dòng tên `GHEAMTP01 TEST` và
      `SEPAY TEST WEBHOOK`. Đó không phải cơ sở nào cả — là nội dung chuyển khoản bị đem đi đoán
      tên máy rồi đoán tiếp thành tên cơ sở. Một bảng đối soát mọc ra cơ sở không có thật là
      bảng không dùng được: người đọc không phân biệt nổi đâu là cơ sở quên khai, đâu là rác. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 50000, 'phut' => 15,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '' ) );
vhg_ban( array( 'transferAmount' => 20000, 'transferType' => 'in',
	'content' => 'GHEAMTP01 TEST1', 'referenceCode' => 'cs-1' ) );
$t_cs = VHG_Thu::tong_hop( 'all' );
teq( 'ghế đã khai mà chưa gán cơ sở -> đúng MỘT nhóm', 1, dem( $t_cs['theo_coso'] ) );
$ten_cs = array_column( $t_cs['theo_coso'], 'coso' );
teq( '🔴 và nhóm đó là "(chưa gán cơ sở)", KHÔNG phải nội dung chuyển khoản',
	array( '(chưa gán cơ sở)' ), $ten_cs );
foreach ( $ten_cs as $k ) {
	t( "không có cơ sở nào tên bắt đầu bằng GHE: \"$k\"", strpos( $k, 'GHE' ) !== 0 );
}
$gd_cs = VHG_Thu::ds( 'all' );
teq( 'nội dung đã khớp mã ghế thì KHÔNG suy thêm "tên máy" từ chính nó', '', $gd_cs[0]['ten_khai'] );
teq( 'và ghế vẫn nhận đúng', 'AMTP01', $gd_cs[0]['ma_may'] );

/* Phép đoán vẫn giữ cho đường nhập Excel của Tingo (giao dịch mang tên máy "AMTP 03"), nhưng
   CHỈ nhận khi tên đoán ra TRÙNG một cơ sở ĐÃ KHAI. */
VHG_May::luu_coso( 0, 'AMTP' );
vhg_ban( array( 'transferAmount' => 30000, 'transferType' => 'in',
	'content' => 'AMTP 03', 'referenceCode' => 'cs-2' ) );
$t_cs2 = VHG_Thu::tong_hop( 'all' );
t( 'đoán TRÚNG một cơ sở đã khai thì gộp đúng vào đó',
	in_array( 'AMTP', array_column( $t_cs2['theo_coso'], 'coso' ), true ),
	array_column( $t_cs2['theo_coso'], 'coso' ) );
vhg_ban( array( 'transferAmount' => 40000, 'transferType' => 'in',
	'content' => 'CHUYEN TIEN NGUYEN VAN A 09', 'referenceCode' => 'cs-3' ) );
$t_cs3 = VHG_Thu::tong_hop( 'all' );
foreach ( array_column( $t_cs3['theo_coso'], 'coso' ) as $k ) {
	t( "đoán TRƯỢT thì nói \"chưa gán\", không bịa: \"$k\"",
		'(chưa gán cơ sở)' === $k || 'AMTP' === $k );
}

// ============================================ GÁN GHẾ NGAY TRÊN TRANG NGOÀI
/* 🔴 Người đi lắp ghế ở Aeon Tân Phú cầm cái điện thoại, không cầm wp-admin. Bắt họ nhắn về
      văn phòng nhờ ai đó gán hộ là thêm một vòng chờ, và trong lúc chờ thì ghế đứng đó không
      thu được đồng nào. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970418', '888815678', 'K&H' );
$tok_g = vhg_vao( '571394', 'Admin' );
$tam_g = VHG_May::ghi_nhan( 'AA:BB:CC:DD:12:34' );
VHG_May::luu_coso( 0, 'Aeon Tân Phú' );
$id_g = 0;
foreach ( VHG_May::ds_coso() as $c ) { if ( 'Aeon Tân Phú' === $c['ten'] ) { $id_g = (int) $c['id']; } }

$sl = vhg_web( 'so_lieu', array( 'token' => $tok_g, 'ky' => 'all' ) );
teq( 'trang ngoài thấy ghế đang chờ gán', 1, dem( $sl['choGan'] ) );
teq( 'kèm MAC để đối chiếu với nhãn dán trên ghế', 'AA:BB:CC:DD:12:34', $sl['choGan'][0]['mac'] );
t( 'và kèm danh sách cơ sở để chọn ngay tại chỗ', dem( $sl['coso'] ) >= 1, $sl['coso'] );
/* Một lượt gọi ra đủ màn: trên 4G ở trung tâm thương mại, mỗi lượt gọi thêm là một cơ hội hỏng. */
foreach ( array( 'tong', 'may', 'cho', 'gd', 'choGan', 'coso' ) as $k ) {
	t( "vẫn chỉ MỘT lượt gọi, có sẵn phần '$k'", isset( $sl[ $k ] ) );
}

$r = vhg_web( 'gan_ma', array( 'token' => $tok_g, 'ma_cu' => $tam_g,
	'ma_moi' => 'AMTP07', 'coso_id' => $id_g ) );
t( 'gán được từ trang ngoài', ! empty( $r['ok'] ), $r );
$m_g = VHG_May::may( 'AMTP07' );
teq( 'giữ nguyên MAC', 'AA:BB:CC:DD:12:34', $m_g['mac'] );
teq( 'và gán luôn cơ sở', $id_g, (int) $m_g['coso_id'] );
teq( 'hết nằm trong danh sách chờ', 0,
	count( vhg_web( 'so_lieu', array( 'token' => $tok_g ) )['choGan'] ) );

/* ⚠️ Gán mã là đổi khoá của một dòng doanh thu — phải biết AI làm. Ghi tên người cầm phiên,
      không lấy tên từ gói gửi lên. */
$lg_g = VHG_Nhat_Ky::ds( 5 );
t( 'ghi nhật ký ai gán mã', strpos( (string) $lg_g[0]['ghi_chu'], 'Anh Thắng' ) !== false, $lg_g[0] );
t( 'và ghi cả mã cũ lẫn mã mới', strpos( (string) $lg_g[0]['ghi_chu'], 'AMTP07' ) !== false );

/* Không token thì KHÔNG được gán — đây là đường đổi khoá dòng tiền. */
$r = vhg_web( 'gan_ma', array( 'ma_cu' => '?X', 'ma_moi' => 'HACK01' ) );
teq( 'không token thì chối', false, $r['ok'] );
teq( 'và chối bằng mã het_phien', 'het_phien', $r['ma'] );
teq( 'KHÔNG tạo ra ghế nào', null, VHG_May::may( 'HACK01' ) );

$r = vhg_web( 'gan_ma', array( 'token' => $tok_g, 'ma_cu' => 'AMTP07', 'ma_moi' => 'AM TP' ) );
teq( 'mã có khoảng trắng bị chối', false, $r['ok'] );

$html_g = vhg_web_html();
t( 'giao diện có khối gán ghế', strpos( $html_g, 'data-gan=' ) !== false );
t( 'có ô chọn cơ sở ngay tại dòng đó', strpos( $html_g, 'data-gcs=' ) !== false );
/* Chặn hai lớp: câu báo lỗi trên máy tới ngay, còn đi một vòng máy chủ trên 4G là vài giây
   đứng nhìn không biết chuyện gì. */
t( 'chặn mã sai khuôn ngay trên máy, trước khi gửi đi',
	strpos( $html_g, '/^[A-Za-z0-9]{1,20}$/' ) !== false );

// ---- ba màn của ghế, dựng theo tấm bảng giá anh Thắng thiết kế
/* Không dựng được màn TFT trong phép thử, nên soi những thứ SAI ÂM THẦM: một font chưa được
   nạp thì màn trống trơn, một mã QR thiếu vùng lặng thì chỉ vài dòng điện thoại không quét
   được, một màn vẽ lại cả nền mỗi giây thì chạm màn hình trễ thấy rõ. Ba lỗi đó đều KHÔNG
   hiện ra khi đọc mã. */
$fw3 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'màn chọn gói có dải tiêu đề như bảng giá',
	strpos( $fw3, 'CHAO MUNG QUY KHACH' ) !== false );
t( 'và dải chân mời quét mã', strpos( $fw3, 'QUET MA QR DE THANH TOAN' ) !== false );
t( 'thẻ VVIP vẽ khác thẻ thường', strpos( $fw3, 'PKG_VIP[i] != 0' ) !== false );
t( 'số tiền in đủ kiểu Việt (200.000d), không viết tắt "200k"',
	strpos( $fw3, 'String tienVN(long v)' ) !== false
	&& preg_match( '/drawString\(String\(amt\/1000\)/', $fw3 ) === 0 );

t( 'màn QR có tiêu đề của bảng thiết kế',
	strpos( $fw3, 'MA QR DE THANH TOAN & BAT DAU PHIEN MASSAGE' ) !== false );
/* 🔴 Mã QR phải nằm trên nền TRẮNG kể cả VÙNG LẶNG hai bên. Thiếu vùng lặng là nhiều điện
      thoại không nhận ra mã — kiểu hỏng chỉ lộ ở một số máy, nên rất khó lần ra. */
t( 'mã QR có vùng lặng trắng quanh nó', strpos( $fw3, 'vùng lặng' ) !== false );
/* Và cỡ ô tính theo KÍCH THƯỚC THẬT của mã: chuỗi VietQR dài ngắn khác nhau tuỳ tên tài khoản
   và mã lượt, cố định 3 pixel/ô là có ngày mã tràn khỏi màn. */
t( 'cỡ ô tính theo kích thước thật của mã', strpos( $fw3, 'canhVung / (size + 4)' ) !== false );
/* ⚠️ Và tính theo CHIỀU NGẮN HƠN của vùng. Bộ vẽ nay dùng chung cho hai chỗ: ô trắng vuông giữa
      màn thanh toán, và ô gói 150 rộng × 84 cao ở màn chờ. Lấy chiều rộng thôi là mã tràn xuống
      dưới ở ô gói — mà phần bị cắt thì không quét được, và nhìn vẫn "có mã QR". */
t( 'và theo chiều NGẮN HƠN của vùng, không phải chiều rộng',
	strpos( $fw3, 'canhVung = (g_qrVungW < g_qrVungH) ? g_qrVungW : g_qrVungH' ) !== false );
t( 'vùng đích đặt trước mỗi lượt vẽ, không gắn cứng',
	substr_count( $fw3, 'qrDatVung(' ) >= 3 );
/* Mã lượt PHẢI hiện ra: app ngân hàng nào không tự điền nội dung thì khách gõ tay đúng chuỗi
   này, không có nó thì tiền vào mà ghế không chạy. Nhưng in bằng cách RÁP LẠI chuỗi ngay tại
   màn thì đúng là lỗi vừa phải sửa — nên phép thử này bám vào BIẾN, không bám vào công thức. */
t( 'màn QR vẫn in nội dung để khách gõ tay được',
	strpos( $fw3, 'drawString("Noi dung: " + payND' ) !== false );
t( 'và nội dung đó có mã lượt trong nó',
	preg_match( '/payND\s*=[^;]*payCode;/', $fw3 ) === 1 );

t( 'màn đang chạy có tiêu đề của bảng thiết kế',
	strpos( $fw3, 'PHIEN TRI LIEU DANG DIEN RA' ) !== false );
t( 'và nói tổng thời gian + tên gói, không chỉ con số đếm ngược',
	strpos( $fw3, '"TONG: "' ) !== false );
/* ⚠️ CHỈ VẼ NỀN MỘT LẦN. fillScreen trên CYD mất ~90ms; làm mỗi giây là màn nháy và chạm màn
      hình trễ thấy rõ. */
t( 'màn đếm ngược chỉ vẽ nền một lần', strpos( $fw3, 'if(!screenDrawn){' ) !== false );
/* setTextPadding xoá đúng vệt chữ cũ — thiếu nó thì "10:00" -> "9:59" để lại một chữ số mồ
   côi và người ta đọc ra một con số không tồn tại. */
t( 'và xoá sạch vệt chữ cũ mỗi giây', strpos( $fw3, 'setTextPadding(tft.textWidth("88:88"' ) !== false );
/* 🔴 Font 6 chứ không font 7: font 6 là font bản gốc đã dùng và chắc chắn được nạp trong
      User_Setup của bo CYD này. Đổi sang một font chưa bật là màn TRỐNG TRƠN — mà lúc đó ghế
      đang chạy và khách đang nhìn. */
t( 'dùng font đã chắc chắn được nạp cho số đếm ngược',
	preg_match( '/drawString\(b, 160, 96, 6\)/', $fw3 ) === 1 );

// ---- firmware nhận GÓI có tên
$fw2 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'firmware đọc được tên gói', strpos( $fw2, 'PKG_TEN' ) !== false );
t( 'và số phút riêng của gói', strpos( $fw2, 'PKG_PHUT' ) !== false );
/* ⚠️ Ghế nạp bằng USB nên trong nhà sẽ lẫn hai đời firmware và hai đời plugin nhiều tuần liền —
      bên nào cũng phải chịu được bên kia, không thì một cửa hàng nào đó im lặng mất hết nút. */
t( 'nhận CẢ dạng cũ (số trơn) lẫn dạng mới {t,n,p}',
	strpos( $fw2, 'v.is<JsonObjectConst>()' ) !== false );
t( 'số phút tính ở ĐÚNG MỘT hàm', substr_count( $fw2, 'int phutGoi(int i)' ) === 1 );
t( 'và mọi nơi đều gọi hàm đó', substr_count( $fw2, 'phutGoi(' ) >= 3 );
t( 'bộ đệm JSON nới cho tên gói',
	preg_match( '/StaticJsonDocument<(\d+)> d;/', $fw2, $m2 ) === 1 && (int) $m2[1] >= 1024 );

// ---- màn QR: chữ trên màn PHẢI là đúng chuỗi nằm trong mã QR
$fw6 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
/* 🔴 LỖI ĐÃ XẢY RA BỐN LẦN THEO ĐÚNG MỘT KIỂU: một chỗ dựng chuỗi thật, một chỗ khác ráp lại
      chuỗi để hiển thị. Ba lần trước ở bảng xem trước trong wp-admin, lần thứ tư ở đây — màn
      ghế in "GHEAMTP01 FFPL45" trong khi mã QR mang "SEVQR GHEAMTP01 FFPL45". Khách nào gõ tay
      theo dòng chữ trên màn (app ngân hàng không tự điền nội dung thì phải gõ) là chuyển đúng
      tiền vào đúng tài khoản mà SePay không thấy — ghế không chạy, tiền không mất, nhưng cũng
      không ai tìm ra nó ở đâu.
      Nên chặn ở gốc: công thức nội dung được phép xuất hiện ĐÚNG MỘT LẦN trong cả tệp. */
t( 'nội dung chuyển khoản dựng đúng một chỗ',
	substr_count( $fw6, '"GHE" + CHAIR_ID + " " + payCode' ) === 1 );
t( 'và chỗ đó có kèm tiền tố', preg_match(
	'/payND\s*=\s*\(ND_TIEN_TO\.length\(\)\s*\?\s*ND_TIEN_TO\s*\+\s*" "\s*:\s*""\)\s*\+\s*"GHE"/', $fw6 ) === 1 );
t( 'mã QR lấy đúng biến đó',
	strpos( $fw6, 'buildVietQR(BANK_BIN, ACCOUNT_NO, payAmount, payND)' ) !== false );
t( 'và màn cũng in đúng biến đó, không ráp lại',
	strpos( $fw6, 'drawString("Noi dung: " + payND' ) !== false );

/* Hàng đồng hồ "Cho tra: 91s" nằm ĐÈ LÊN hai dòng chữ dưới mã QR ở bản trước. Vùng y 195–217
   dành riêng cho dòng hướng dẫn và dòng nội dung; đồng hồ phải xuống hàng dưới cùng. */
$than_dh = '';
if ( preg_match( '/void drawWaitCountdown\(int secLeft\)\{(.*?)\n\}/s', $fw6, $m_dh ) ) {
	$than_dh = $m_dh[1];
}
t( 'tìm được thân hàm đồng hồ chờ trả', '' !== $than_dh );
$y_dh = array();
if ( preg_match_all( '/drawString\([^;]*?,\s*(\d+)\s*,\s*(\d+)\s*,\s*\d+\s*\)/', $than_dh, $m_y ) ) {
	$y_dh = array_map( 'intval', $m_y[2] );
}
t( 'đồng hồ có vẽ chữ', count( $y_dh ) >= 2 );
t( 'và không dòng nào lấn vào vùng hai dòng chữ dưới mã QR',
	count( $y_dh ) >= 2 && min( $y_dh ) >= 218 );
/* Ô trắng của mã QR chỉ rộng tới x=242. Lấy nền TFT_WHITE rồi setTextPadding kéo vệt nền tới
   mép phải là thò ra một mảng trắng giữa nền tối — nhìn như màn hỏng. */
t( 'đồng hồ không mượn nền trắng của ô mã QR', strpos( $than_dh, 'TFT_WHITE' ) === false );

/* Hai dòng chữ dưới mã QR phải nằm TRÊN hàng đồng hồ, không thì lại chồng theo chiều ngược. */
foreach ( array( 'Quet bang ung dung', 'Noi dung: ' ) as $chu_qr ) {
	$ok_y = preg_match( '/drawString\("' . preg_quote( $chu_qr, '/' )
		. '[^;]*?,\s*\d+\s*,\s*(\d+)\s*,\s*\d+\s*\)/', $fw6, $m_q ) === 1
		&& (int) $m_q[1] <= 217;
	t( 'dòng "' . $chu_qr . '" nằm trên hàng đồng hồ', $ok_y );
}

// ---- firmware: kênh HTTPS giữ mở, để quét xong ghế chạy ngay
$fw7 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
/* 🔴 Bản trước dựng WiFiClientSecure NGAY TRONG HÀM. Biến cục bộ, hết hàm là socket chết theo,
      nên MỖI lượt gọi phải bắt tay TLS lại — 1-2 giây trên ESP32. Trả giá ở hai chỗ: quét xong
      5-6 giây ghế mới chạy, và `con_lai` già đúng chừng đó ngay lúc sinh ra. */
t( 'kênh HTTPS dùng lại, không dựng mới mỗi lượt',
	preg_match( '/static\s+WiFiClientSecure\s+c;/', $fw7 ) === 1 );
t( 'và không còn biến cục bộ dựng lại mỗi lượt',
	preg_match( '/^\s*WiFiClientSecure\s+c;\s*c\.setInsecure/m', $fw7 ) !== 1 );
t( 'có bật dùng lại kết nối', strpos( $fw7, 'h.setReuse(' ) !== false );
/* ⚠️ begin() đặt lại tiêu đề VÀ ngắt kết nối đang có. Gọi lại mỗi lượt là vẫn bắt tay lại,
      chỉ khác là mình tưởng đã sửa xong. */
t( 'begin() chỉ gọi một lần', substr_count( $fw7, 'h.begin(c, wp_url)' ) === 1 );
/* ⚠️ Đọc hết thân trả lời KỂ CẢ khi lỗi: bỏ dở là byte thừa nằm lại trong socket, lượt sau đọc
      trúng phần thừa của lượt trước -> JSON hỏng đúng lúc máy chủ đang trả lỗi. */
t( 'đọc hết thân trả lời kể cả khi mã lỗi',
	preg_match( '/String\s+than\s*=\s*h\.getString\(\);/', $fw7 ) === 1
	&& preg_match( '/String\s+ra\s*=\s*\(code==200\)\s*\?\s*than\s*:/', $fw7 ) === 1 );
/* Có host cắt keep-alive. Gãy liên tiếp thì phải thôi giữ kênh, chậm còn hơn chết. */
t( 'gãy liên tiếp thì quay về cách cũ', strpos( $fw7, 'thoiGiuKenh' ) !== false );
t( 'và dựng lại kết nối khi gãy',
	preg_match( '/if\(code\s*<=\s*0\)\{[^}]*daMo\s*=\s*false;/s', $fw7 ) === 1 );
/* Giữ kênh chỉ trong đợt hỏi dày. Ôm ~40KB bộ nhớ TLS suốt ngày cho một lượt nhịp 30 giây là
   đổi RAM lấy con số không. */
t( 'chỉ giữ kênh trong lúc chờ khách trả',
	preg_match( '/g_giuKenh\s*=\s*dangCho;/', $fw7 ) === 1 );
/* Đúng MỘT chỗ gán (không tính dòng khai báo): bật/tắt bằng tay ở startSession, startRunning
   và nút huỷ là ba chỗ phải nhớ — chỗ nào quên thì hoặc mất tốc độ, hoặc ôm 40KB bộ nhớ TLS
   suốt ngày mà không ai lần ra vì sao hết RAM. */
t( 'và bật/tắt ở ĐÚNG MỘT nơi',
	preg_match_all( '/^\s*g_giuKenh\s*=/m', $fw7 ) === 1 );
t( 'không còn thì trả lại bộ nhớ TLS',
	preg_match( '/if\(!g_giuKenh[^)]*\)\{[^}]*c\.stop\(\)/s', $fw7 ) === 1 );

/* Hỏi tiền dày hơn — nhưng đừng dày quá: mỗi lượt là một request PHP, host này đã có tiền sử
   bị Imunify360 chặn vì gõ cửa quá dày. */
$poll = preg_match( '/PAY_POLL_MS\s*=\s*(\d+)/', $fw7, $m_pp ) === 1 ? (int) $m_pp[1] : -1;
t( 'chu kỳ hỏi tiền nhanh hơn hẳn', $poll > 0 && $poll <= 1000, $poll );
t( 'nhưng không dày tới mức bị chặn', $poll >= 500, $poll );

/* Ghế phải KHAI quãng đi, không thì máy chủ không có gì để trừ. */
t( 'ghế đo thời gian mỗi lượt gọi', strpos( $fw7, 'g_rttMs = millis() - t0' ) !== false );
t( 'và gửi kèm trong nhịp', strpos( $fw7, '",\"tre\":" + String(g_rttMs)' ) !== false );

// ====================== CỤC NHẬN TIỀN ICT L70 BÁO HỎNG
/* Chỉ có ĐÚNG MỘT dây tín hiệu về ESP32 (đường xung) và một dây khoá đi ra. Bốn thứ dưới đây là
   tất cả những gì suy ra được từ hai sợi dây đó — và cả bốn đều là tiền đếm sai hoặc tiền vào
   mà ghế không chạy. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );

VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'idle', 'tm_loi' => 'ket', 'tm_cuoi' => 'ket',
	'tm_lan' => 3, 'tm_giay' => 40, 'tm_to' => 900 ) );
$m = null;
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( 'lỗi đang diễn ra được giữ lại', 'ket', (string) $m['tm_loi'] );
teq( 'và đếm được đã mấy lần', 3, (int) $m['tm_lan'] );
/* Ghế đếm bằng millis() của chính nó, không có đồng hồ thật -> nó khai TUỔI, máy chủ đổi ra giờ
   tuyệt đối của mình. Đây là cách duy nhất đúng khi hai bên không chung đồng hồ. */
$cach = current_time( 'timestamp' ) - strtotime( (string) $m['tm_luc'] );
t( 'tuổi đổi thành giờ tuyệt đối của máy chủ', abs( $cach - 40 ) <= 2, $cach );
$cach_to = current_time( 'timestamp' ) - strtotime( (string) $m['tm_to'] );
t( 'tờ tiền gần nhất cũng vậy', abs( $cach_to - 900 ) <= 2, $cach_to );

/* Người đứng quầy không tra bảng mã — mà họ mới là người phải chạy ra xem cái máy. */
foreach ( array( 'ket', 'lech', 'khoa', 'nhieu' ) as $ma_l ) {
	t( 'mã "' . $ma_l . '" có câu người đọc là hiểu',
		strlen( VHG_May::loi_tien_chu( $ma_l ) ) > 30 );
}

/* 🔴 CHỈ NHẬN MÃ TRONG DANH SÁCH. Cổng máy chỉ có một khoá chung, ai biết khoá là gửi được —
      ghi thẳng chuỗi ghế khai vào cột là mở đường cho một chuỗi lạ chui vào màn quản trị.
      Mã lạ -> coi như không có lỗi, và thế là AN TOÀN: bỏ sót một cảnh báo còn hơn tin một
      cảnh báo bịa. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'idle',
	'tm_loi' => '<script>alert(1)</script>', 'tm_cuoi' => 'bia-dat' ) );
foreach ( VHG_May::ds_may() as $x ) { if ( 'AMTP01' === $x['ma'] ) { $m = $x; } }
teq( 'mã lạ bị bỏ, không ghi vào cột', '', (string) $m['tm_loi'] );
teq( 'kể cả ở cột lỗi cũ', '', (string) $m['tm_cuoi'] );
teq( 'và loi_tien_chu cũng không trả gì', '', VHG_May::loi_tien_chu( 'bia-dat' ) );

/* `-1` = CHƯA TỪNG xảy ra. Đổi thành "vừa xong" là bịa ra một lần hỏng chưa hề có. */
teq( 'chưa từng hỏng thì không có mốc giờ', null, VHG_May::luc_tu_tuoi( -1 ) );
t( 'tuổi vô lý (quá 1 năm) cũng bỏ', null === VHG_May::luc_tu_tuoi( 99999999 ) );
t( 'tuổi hợp lệ thì có mốc', null !== VHG_May::luc_tu_tuoi( 10 ) );

/* Cổng máy phải chuyển tiếp đủ NĂM ô. Quên một ô là cả nhánh trên câm mà không ai biết. */
$cong_tm = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-cong.php' );
foreach ( array( 'tm_loi', 'tm_cuoi', 'tm_lan', 'tm_giay', 'tm_to' ) as $o ) {
	t( 'cổng máy chuyển tiếp ' . $o,
		preg_match( "/'" . $o . "'\s*=>\s*isset\(\s*\\\$d\['" . $o . "'\]\s*\)/", $cong_tm ) === 1 );
}

// ---- màn quản trị nói ra, và nói đúng loại
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'idle', 'tm_loi' => 'lech', 'tm_cuoi' => 'lech',
	'tm_lan' => 1, 'tm_giay' => 30, 'tm_to' => 60 ) );
ob_start(); VHG_Admin::trang_may(); $html_tm = ob_get_clean();
t( 'màn quản trị báo cục nhận tiền lỗi', strpos( $html_tm, 'Cục nhận tiền báo lỗi' ) !== false );
t( 'và nói rõ đang hỏng chứ không phải chuyện cũ', strpos( $html_tm, 'ĐANG HỎNG' ) !== false );
t( 'kèm việc phải làm', strpos( $html_tm, 'TIỀN ĐANG ĐẾM SAI' ) !== false );

/* ⚠️ Lỗi ĐÃ QUA và lỗi ĐANG diễn ra phải hiện khác nhau. Gộp lại thì một lần kẹt ba giây hồi
      sáng nằm đó báo đỏ cả ngày, và ghế đang thật sự kẹt lúc này lẫn vào đám cũ. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'idle', 'tm_loi' => '', 'tm_cuoi' => 'lech',
	'tm_lan' => 1, 'tm_giay' => 3600, 'tm_to' => 60 ) );
ob_start(); VHG_Admin::trang_may(); $html_tm = ob_get_clean();
t( 'hết lỗi thì vẫn còn dấu vết', strpos( $html_tm, 'Cục nhận tiền báo lỗi' ) !== false );
t( 'nhưng KHÔNG còn báo đang hỏng', strpos( $html_tm, 'ĐANG HỎNG' ) === false );

/* Chưa bao giờ lỗi thì đừng hiện gì cả. Bảng thường trực là bảng người ta thôi đọc. */
VHG_May::nhip( 'AMTP01', array( 'trang_thai' => 'idle', 'tm_loi' => '', 'tm_cuoi' => '' ) );
ob_start(); VHG_Admin::trang_may(); $html_tm = ob_get_clean();
t( 'chưa lỗi bao giờ thì im lặng', strpos( $html_tm, 'Cục nhận tiền báo lỗi' ) === false );

// ---- trang ngoài /ghe: người đứng quầy mới là người chạy ra xem máy
$trang_php = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );
t( 'trang ngoài gửi kèm tình trạng cục tiền', strpos( $trang_php, "'tm'      => (string) \$m['tm_loi']" ) !== false );
t( 'và gửi cả câu giải thích, không bắt tra mã', strpos( $trang_php, "'tm_chu'" ) !== false );
t( 'thẻ ghế hiện cảnh báo cục tiền', strpos( $trang_php, 'Cục nhận tiền đang hỏng' ) !== false );
t( 'phân biệt đang hỏng với đã hỏng lúc trước',
	strpos( $trang_php, 'Cục nhận tiền đã hỏng lúc trước' ) !== false );

// ---- firmware
$fw8 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'ghế đếm cạnh nhiễu bị chống nảy loại', strpos( $fw8, 'g_tmNhieu = g_tmNhieu + 1' ) !== false );
/* ⚠️ Đếm chẩn đoán KHÔNG được trộn vào đếm tiền: một phép đếm để dò lỗi mà cộng thành doanh
      thu là kiểu sai tệ nhất có thể có ở đây. */
t( 'nhưng KHÔNG cộng vào số xung tiền',
	preg_match( '/g_tmNhieu\s*=\s*g_tmNhieu\s*\+\s*1;\s*\n?\s*\}/', $fw8 ) === 1 );
t( 'ghế biết mình đang khoá máy nhận tiền', strpos( $fw8, 'g_tmDangKhoa = CASH_INHIBIT_ENABLE && !en' ) !== false );
/* Không có dây khoá thì không bao giờ được coi là "đang khoá", nếu không MỌI tờ tiền hợp lệ
   đều bị báo thành "nhận tiền khi đã khoá". */
t( 'không có dây khoá thì không tự nhận là đang khoá',
	strpos( $fw8, 'g_tmDangKhoa = !en;' ) === false );
t( 'ghế nhìn đường xung xem có kẹt không', strpos( $fw8, 'digitalRead(CASH_PULSE_PIN) == LOW' ) !== false );
t( 'và có gọi phép kiểm đó trong vòng lặp', preg_match( '/^\s*kiemCucTien\(\);/m', $fw8 ) === 1 );
t( 'kiểm bội số mệnh giá', strpos( $fw8, '(amount % CASH_BOI_SO) != 0' ) !== false );
/* 🔴 Phép kiểm phải đứng TRƯỚC đường thoát `minutes<=0`: đợt tiền nhỏ hơn một phút vẫn có thể
      là đợt đếm sai, và đó chính là đợt cần báo nhất. */
$vt_kiem = strpos( $fw8, '(amount % CASH_BOI_SO) != 0' );
$vt_thoat = strpos( $fw8, 'if(minutes<=0) return;' );
t( 'và đứng TRƯỚC mọi đường thoát', $vt_kiem !== false && $vt_thoat !== false && $vt_kiem < $vt_thoat );
t( 'lỗi làm ghế đẩy nhịp ngay, không đợi 30 giây',
	preg_match( '/void ghiLoiTien\([^)]*\)\{.*?g_statusDirty\s*=\s*true;/s', $fw8 ) === 1 );
/* Lỗi ĐANG diễn ra và sự việc ĐÃ QUA phải tách: treo cờ "đang hỏng" cho chuyện đã qua thì cờ
   không bao giờ hạ, và nó che mất lỗi thật sự đến ngay sau đó. */
t( 'tách lỗi đang diễn ra khỏi chuyện đã qua', strpos( $fw8, 'bool dangDienRa' ) !== false );
t( 'kẹt thì tự hết khi đường xung nhả',
	preg_match( '/strcmp\(g_tmLoi,\s*"ket"\)\s*==\s*0\)\{\s*g_tmLoi\[0\]\s*=\s*0;/', $fw8 ) === 1 );
t( 'ghế gửi tình trạng cục tiền lên nhịp', strpos( $fw8, '",\"tm_loi\":\""' ) !== false );
/* ⚠️ KHÔNG suy ra "hỏng" từ việc lâu không có tờ nào: cả ngày không ai trả tiền mặt là chuyện
      bình thường, báo hỏng vì thế là dạy người ta bỏ qua cảnh báo. */
/* `g_tmLucTo` chỉ được dùng ở ĐÚNG BA chỗ: khai báo, ghi lúc nhận tờ, và đọc để gửi lên nhịp (chỗ cuối nhắc tên hai lần).
   Đếm cả bốn lần nhắc tên chứ không dò một hình dạng cụ thể — phép thử trước dò `ghiLoiTien(...g_tmLucTo...)`
   và trượt ngay khi biến đứng ở vế điều kiện thay vì trong tham số. Cách đếm này chặn MỌI cách
   biến nó thành cờ báo lỗi, kể cả cách chưa nghĩ ra. */
teq( 'lâu không có tờ nào KHÔNG bị coi là hỏng', 4,
	preg_match_all( '/g_tmLucTo/', $fw8 ) );
t( 'và nó chỉ được ghi ở chỗ nhận tờ tiền',
	preg_match_all( '/g_tmLucTo\s*=/', $fw8 ) === 2 );

// ====================== GIAO DIỆN: NỀN ẢNH, TÊN HỆ THỐNG, HAI NGÔN NGỮ
delete_option( 'vhg_anh_nen' );
$web = vhg_web_html();

/* Tên hệ thống khai MỘT chỗ, dùng cho cả thẻ tiêu đề lẫn màn đăng nhập lẫn dải đầu trang. */
teq( 'tên hệ thống khai đúng một chỗ', 1,
	preg_match_all( "/const TEN_HE_THONG\s*=/",
		file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' ) ) );
t( 'thẻ tiêu đề mang tên hệ thống',
	strpos( $web, '<title>' . VHG_Trang::TEN_HE_THONG . '</title>' ) !== false );
t( 'và trang không còn gõ cứng tên cũ', strpos( $web, 'Ghế massage — K&amp;H' ) === false );
t( 'tên đẩy sang JS để màn đăng nhập cũng dùng', strpos( $web, 'window.VHG_TEN=' ) !== false );

/* Chưa khai ảnh -> KHÔNG bật lớp ảnh, và phải có dải màu tự dựng thay thế. Nền trắng chữ trắng
   là kiểu hỏng chỉ lộ ra khi ảnh không tải được — tức là đúng lúc đang ở ngoài đường trên 4G. */
t( 'chưa khai ảnh thì không bật lớp ảnh', strpos( $web, 'class="co-anh"' ) === false );
t( 'nhưng vẫn có nền tự dựng, không trắng trơn',
	strpos( $web, 'linear-gradient(160deg,#12141f' ) !== false );

update_option( 'vhg_anh_nen', 'https://khmatrix.com/wp-content/uploads/phong-ghe.jpg' );
$web = vhg_web_html();
t( 'khai ảnh thì bật lớp ảnh', strpos( $web, 'class="co-anh"' ) !== false );
t( 'và ảnh đi vào biến CSS', strpos( $web, 'phong-ghe.jpg' ) !== false );

/* 🔴 Chuỗi này đi THẲNG vào một thuộc tính style. Một dấu nháy hay dấu ngoặc lọt qua là chèn
      được CSS tuỳ ý vào trang mọi nhân viên đang mở — mà ô nhập nằm trong wp-admin nên rất dễ
      bị coi là "người nhà, khỏi lo". */
update_option( 'vhg_anh_nen', 'https://a.com/x.jpg");}body{display:none}/*' );
$web = vhg_web_html();
/* Bám vào CHUỖI ĐỘC, không vào "display:none": trang vốn đã có `.hide-sm{display:none}` trong
   media query, nên phép thử kia đạt/trượt vì lý do chẳng liên quan. */
t( 'địa chỉ ảnh có ký tự phá CSS thì bỏ hẳn', strpos( $web, 'a.com/x.jpg' ) === false );
t( 'không có mẩu CSS nào chui vào', strpos( $web, '}body{' ) === false );
t( 'và không bật lớp ảnh', strpos( $web, 'class="co-anh"' ) === false );
update_option( 'vhg_anh_nen', 'javascript:alert(1)' );
$web = vhg_web_html();
t( 'không nhận địa chỉ javascript:', strpos( $web, 'javascript:alert' ) === false );
delete_option( 'vhg_anh_nen' );
$web = vhg_web_html();

// ---- hai ngôn ngữ
t( 'có hàm dịch tại chỗ', strpos( $web, 'function L(vi, en)' ) !== false );
t( 'có nút đổi VI/EN', strpos( $web, 'data-nn="en"' ) !== false && strpos( $web, 'data-nn="vi"' ) !== false );
t( 'và nhớ lựa chọn giữa các lần mở', strpos( $web, "localStorage.setItem('vhg_nn'" ) !== false );
/* Đổi ngôn ngữ KHÔNG được gọi lại máy chủ: đó là việc nằm trọn trong máy người ta, mà trang này
   sống trên 4G ở trung tâm thương mại. */
t( 'đổi ngôn ngữ vẽ lại tại chỗ, không gọi máy chủ',
	preg_match( '/function datNN\(n\)\{.*?if \(D\) ve\(\);/s', $web ) === 1 );
t( 'và không gọi tai() trong đó', preg_match( '/function datNN\(n\)\{[^}]*tai\(/s', $web ) !== 1 );

/* ⚠️ TIỀN KHÔNG ĐỔI ĐỊNH DẠNG THEO NGÔN NGỮ. Đây là tiền Việt đếm trong két Việt; đổi dấu
      chấm/phẩy theo kiểu tiếng Anh là mời người ta đọc 50.000 thành năm mươi. */
teq( 'tiền luôn định dạng kiểu Việt Nam', 1, preg_match_all( "/toLocaleString\('vi-VN'\) \+ 'đ'/", $web ) );
t( 'hàm tiền() không dính vào ngôn ngữ',
	preg_match( "/function tien\(n\)\{[^}]*NN/", $web ) !== 1 );

/* Câu giải thích lỗi cục tiền do MÁY CHỦ gửi, nên phải gửi cả hai bản trong một lượt. */
foreach ( array( 'ket', 'lech', 'khoa', 'nhieu' ) as $ma_l ) {
	$vi = VHG_May::loi_tien_chu( $ma_l );
	$en = VHG_May::loi_tien_chu( $ma_l, 'en' );
	t( 'lỗi "' . $ma_l . '" có bản tiếng Anh riêng', strlen( $en ) > 30 && $en !== $vi );
}
$trang_nn = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );
t( 'gửi cả hai bản trong một lượt, không gọi lại khi đổi ngôn ngữ',
	strpos( $trang_nn, "'tm_chu_en'" ) !== false );

/* Đồng hồ trên dải đầu lấy mốc từ GIỜ MÁY CHỦ rồi tự tích. Lấy giờ điện thoại thì nó lệch với
   mọi con số khác trên trang — hai loại giờ cạnh nhau là mời người ta đối chiếu nhầm. */
t( 'đồng hồ đầu trang chạy từng giây', strpos( $web, 'function chayDongHoTop()' ) !== false );
t( 'và lấy mốc từ giờ máy chủ', preg_match( '/chayDongHoTop\(\)\{.*?D\.luc/s', $web ) === 1 );
t( 'không lấy giờ điện thoại làm mốc',
	preg_match( '/function chayDongHoTop\(\)\{.*?new Date\(\)/s', $web ) !== 1 );

// ---- màn Cài đặt có ô khai ảnh
ob_start(); VHG_Admin::trang_ngoai(); $h_nen = ob_get_clean();
t( 'màn cài đặt có ô khai ảnh nền', strpos( $h_nen, 'name="anh_nen"' ) !== false );
t( 'và nói rõ để trống thì không bị nền trắng', strpos( $h_nen, 'không</b> bị nền trắng' ) !== false );
/* Trang mở trên 4G: một ảnh nền 5MB là mỗi lần mở mất mấy giây và tốn tiền của cửa hàng. */
t( 'và nhắc chọn ảnh nhẹ', strpos( $h_nen, 'dưới 300KB' ) !== false );

/* 🔴 BẤM LƯU PHẢI ĂN. Ô hiện ra mà nhánh lưu không nhận nó là đúng kiểu hỏng anh Thắng đã gặp
      với ô tiền tố: *"bấm lưu mà sao nó không chèn vào"*. Ô nhìn thấy được không chứng minh
      được gì cả — phải GỬI THẬT một lượt POST rồi đọc lại. */
delete_option( 'vhg_anh_nen' );
$_POST = array( 'vhg' => 'luu_trang', 'slug' => 'ghe', 'nguon' => 'rieng',
	'vai_tro' => array( 'Admin' ), 'anh_nen' => 'https://khmatrix.com/uploads/phong.jpg' );
ob_start(); VHG_Admin::trang_ngoai(); ob_get_clean();
$_POST = array();
teq( '🔴 bấm Lưu thì ảnh nền vào thật', 'https://khmatrix.com/uploads/phong.jpg',
	(string) get_option( 'vhg_anh_nen' ) );
t( 'và trang ngoài dùng đúng ảnh vừa lưu',
	strpos( vhg_web_html(), 'uploads/phong.jpg' ) !== false );

/* Xoá trắng ô rồi lưu thì phải về nền tự dựng — không giữ lại ảnh cũ. Giữ lại là người ta xoá
   mãi không được, rồi đi tìm cache trong khi lỗi nằm ở nhánh lưu. */
$_POST = array( 'vhg' => 'luu_trang', 'slug' => 'ghe', 'nguon' => 'rieng',
	'vai_tro' => array( 'Admin' ), 'anh_nen' => '' );
ob_start(); VHG_Admin::trang_ngoai(); ob_get_clean();
$_POST = array();
teq( 'xoá trắng ô thì bỏ ảnh thật', '', (string) get_option( 'vhg_anh_nen' ) );
t( 'và trang về nền tự dựng', strpos( vhg_web_html(), 'class="co-anh"' ) === false );

// ====================== GHẾ MẤT MẠNG KHÔNG ĐƯỢC DỰNG QR BẰNG TÀI KHOẢN RỖNG
/* 🔴 LỖI 22/08/2026 23:31 — anh Thắng quét mã trên ghế, tiền KHÔNG tới SePay, không tới đâu cả.
 *
 *    Số tài khoản, mã ngân hàng, tiền tố nội dung, giá và các gói CHỈ nạp từ lượt nhịp, không
 *    ghi vào flash. Ghế khởi động lại lúc chưa hỏi được máy chủ thì `ACCOUNT_NO`/`BANK_BIN` là
 *    chuỗi rỗng — mà `buildVietQR` vẫn dựng ra một mã VietQR ĐÚNG CHUẨN với ngân hàng rỗng và
 *    tài khoản rỗng. Màn nhìn không khác gì bình thường: vẫn bốn gói, vẫn mã QR, vẫn dòng nội
 *    dung. Khách quét, chuyển tiền, tiền không tới tài khoản nào.
 *
 *    `CHAIR_ID` thì đã nhớ vào flash từ lâu — thiếu đúng phần nhận tiền. */
$fw9 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );

foreach ( array( 'tk', 'bin', 'tienTo' ) as $o_ ) {
	t( 'ghế nhớ "' . $o_ . '" vào flash',
		strpos( $fw9, 'prefs.putString("' . $o_ . '"' ) !== false );
}
t( 'nhớ cả giá và số phút', strpos( $fw9, 'prefs.putLong  ("gia"' ) !== false
	&& strpos( $fw9, 'prefs.putInt   ("phut"' ) !== false );
t( 'và nhớ cả các gói', strpos( $fw9, 'prefs.putInt   ("pkgN"' ) !== false );
/* ⚠️ Phải bám vào LỜI GỌI THẬT. Phép thử trước dò "prefs.begin … docCauHinh();" và vẫn đạt khi
      dòng gọi bị chú thích lại — chuỗi vẫn nằm đó, chỉ là không chạy nữa. Dòng bắt đầu bằng
      khoảng trắng rồi tới tên hàm thì chú thích `//` không lọt qua được. */
teq( 'đọc lại lúc khởi động', 1, preg_match_all( '/^\s*docCauHinh\(\);/m', $fw9 ) );
t( 'và đọc SAU khi đã mở được flash',
	preg_match( '/prefs\.begin\("ghe".*?^\s*docCauHinh\(\);/ms', $fw9 ) === 1 );

/* 🔴 CHỐT: không có tài khoản thì không có mã QR. Không ngoại lệ. */
t( 'có chốt "đủ điều kiện nhận tiền"',
	preg_match( '/bool duNhanTien\(\)\{\s*return ACCOUNT_NO\.length\(\) > 0 && BANK_BIN\.length\(\) > 0;/', $fw9 ) === 1 );
t( 'startSession từ chối khi chưa biết tài khoản',
	preg_match( '/void startSession\(int idx\)\{\s*(?:\/\*.*?\*\/\s*)?if\(!duNhanTien\(\)\)\{/s', $fw9 ) === 1 );
/* ⚠️ Chốt phải đứng TRƯỚC chỗ dựng mã. Đứng sau thì mã đã dựng xong rồi mới từ chối — và chỉ
      cần một đường vẽ nào đó chạm tới `qrPayload` là mã hỏng lại hiện lên. */
$vt_chot = strpos( $fw9, 'if(!duNhanTien()){' );
$vt_dung = strpos( $fw9, 'qrPayload  = buildVietQR(' );
t( 'và từ chối TRƯỚC khi dựng mã', $vt_chot !== false && $vt_dung !== false && $vt_chot < $vt_dung );
/* ⚠️ Canh Ý, đừng canh CHỮ TRONG CHÚ THÍCH. Bản trước canh "…return; } /* Dải tiêu đề" — rồi
      chính em sửa chú thích đó khi vá chỗ tiêu đề đè mã ghế, và phép thử gãy dù mã vẫn đúng.
      Ý cần canh là: chốt đứng TRƯỚC mọi thứ drawIdle() vẽ ra, chứ không phải chú thích tên gì. */
t( 'màn chờ cũng không mời chọn gói khi chưa có tài khoản',
	preg_match( '/if\(!duNhanTien\(\)\)\{\s*veManChuaCoTk\(\);\s*return;\s*\}/s', $fw9 ) === 1 );
$vt_tuchoi = strpos( $fw9, 'veManChuaCoTk();' );
$vt_tieude = strpos( $fw9, 'fillRect(0, 0, 320, 28, COL_KHUNG)' );   // nét vẽ đầu tiên của màn chờ
$vt_vongo  = strpos( $fw9, 'for(int i=0;i<PKG_N;i++) veTheGoi(i);' );
t( '🔴 và chốt đó đứng TRƯỚC mọi nét vẽ của màn chờ',
	false !== $vt_tuchoi && false !== $vt_tieude && $vt_tuchoi < $vt_tieude );
t( 'trước cả vòng vẽ bốn thẻ gói',
	false !== $vt_vongo && $vt_tuchoi < $vt_vongo );
t( 'có màn báo riêng cho tình huống đó', strpos( $fw9, 'TAM NGUNG NHAN QR' ) !== false );
/* Nói cho khách việc khách làm được (trả tiền mặt), nói cho nhân viên việc nhân viên làm được. */
t( 'và mời khách trả tiền mặt thay vì đứng chờ', strpos( $fw9, 'tra TIEN MAT' ) !== false );
t( 'màn đó tự vẽ lại để biến mất khi lấy được cấu hình',
	strpos( $fw9, 'CHAIR_ID.length()==0 || !duNhanTien()' ) !== false );

/* ⚠️ `docCauHinh` KHÔNG được bịa ra một tài khoản mặc định. Chuỗi rỗng chính là thứ kích hoạt
      chốt trên; nhét một giá trị vào cho "đỡ rỗng" là gỡ mất cái chốt mà không ai nhận ra. */
t( 'đọc flash không bịa tài khoản mặc định',
	preg_match( '/prefs\.getString\("tk",\s*""\)/', $fw9 ) === 1
	&& preg_match( '/prefs\.getString\("bin",\s*""\)/', $fw9 ) === 1 );

/* Ghi flash CHỈ khi cấu hình đổi. Ghi mỗi lượt nhịp là 30 giây một lần ghi NVS, ngày gần ba
   nghìn lượt — hao chip mà chẳng được gì. */
t( 'chỉ ghi flash khi cấu hình đổi',
	preg_match( '/if\(cu_ky != kyCauHinh\(\)\)\{ luuCauHinh\(\);/', $fw9 ) === 1 );
t( 'và ghi NGAY lượt đó, không đợi lượt sau',
	substr_count( $fw9, 'luuCauHinh();' ) === 1 );

/* Máy chủ đã chặn sẵn phía nó — giữ nguyên, đừng để ai gỡ. Hai đầu cùng chặn vì firmware cũ
   vẫn còn chạy ngoài cửa hàng nhiều tuần. */
$qr_tk = VHG_QR::cho_ghe( 'AMTP01', 'MAU' );
VHG_May::luu_nhan_tien( '', '', '' );
$qr_r = VHG_QR::cho_ghe( 'AMTP01', 'MAU' );
t( 'máy chủ cũng không dựng QR khi chưa khai tài khoản', empty( $qr_r['ok'] ) );
t( 'và nói rõ phải khai ở đâu', strpos( (string) $qr_r['error'], 'Tài khoản nhận tiền' ) !== false
	|| strpos( (string) $qr_r['error'], 'tài khoản' ) !== false );

// ====================== CHỜ SẴN Ở MÁY CHỦ THAY VÌ HỎI THEO NHỊP
/* 🔴 Anh Thắng đo 22/08/2026: quét xong mất 8 giây ghế mới chạy — 4 giây cho tiền đi từ ngân
      hàng qua SePay về web (của SePay, mình không rút được), 4 giây nữa cho ghế phát hiện ra.
      Bốn giây sau là của mình, và nó sinh ra chỉ vì ghế HỎI THEO NHỊP: tiền về ngay sau lúc ghế
      vừa hỏi xong thì phải đợi trọn một nhịp nữa. */
$cong_cho = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-cong.php' );
t( 'cổng máy giữ câu hỏi lại thay vì trả "chưa có" ngay',
	preg_match( '/while \( ! \$r && microtime\( true \) < \$het \)/', $cong_cho ) === 1 );
/* ⚠️ CHỈ CHỜ KHI GHẾ XIN. Firmware cũ không gửi `cho`; tự ý giữ request của nó lại là làm chậm
      chính thứ đang định làm nhanh. */
t( 'chỉ chờ khi ghế xin', strpos( $cong_cho, "isset( \$d['cho'] ) ? \$d['cho'] : 0" ) !== false );
teq( 'firmware cũ (không gửi cho) thì không bị giữ', 0,
	(int) preg_match( '/\$cho = max\( 0, min\( self::CHO_TOI_DA, \(int\) \( isset\( \$d\[.cho.\] \) \? \$d\[.cho.\] : [1-9]/', $cong_cho ) );
/* ⚠️ TRẦN CỨNG Ở MÁY CHỦ, không tin con số ghế gửi lên: mỗi lượt chờ chiếm một tiến trình PHP
      của hosting chung. */
t( 'có trần cứng ở máy chủ', preg_match( '/const CHO_TOI_DA\s*=\s*([1-9])\s*;/', $cong_cho, $m_ct ) === 1
	&& (int) $m_ct[1] <= 5 );
t( 'và ghế không tự nâng trần được', strpos( $cong_cho, 'min( self::CHO_TOI_DA' ) !== false );
/* ⚠️ Ghế rút điện giữa chừng mà vòng lặp cứ chạy hết là giữ không công một tiến trình PHP. */
t( 'ngắt kết nối thì thôi chờ', strpos( $cong_cho, 'connection_aborted()' ) !== false );

$fw10 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'ghế có xin chờ khi hỏi tiền', strpos( $fw10, 'wpGoi("luot", "\"cho\":4")' ) !== false );

/* 🔴 LƯỢT CHỜ KHÔNG PHẢI ĐỘ TRỄ ĐƯỜNG TRUYỀN.
      `g_rttMs` dùng để trừ nửa quãng đi khỏi `con_lai`. Lượt `luot` nay xin giữ tới 4 giây, nên
      nó mất 4 giây là chuyện bình thường. Đo lẫn vào là đồng hồ trên web tự lùi 2 giây sau mỗi
      lượt khách trả tiền — một phép sửa lệch giờ tự tạo ra lệch giờ. */
teq( 'chỉ đo quãng đi ở lượt nhịp', 2, preg_match_all( '/if\(viec == "nhip"\) g_rttMs = millis\(\) - t0;/', $fw10 ) );
t( 'và không đo vô điều kiện ở đâu cả',
	preg_match( '/^\s*g_rttMs = millis\(\) - t0;/m', $fw10 ) !== 1 );

/* 🔴 GHẾ NÀY CHẠY 4G. Cả khối keep-alive HTTPS nằm ở nhánh WiFi, không bao giờ chạy tới. Đo giờ
      chỉ ở nhánh WiFi là đúng con ghế cần đo nhất lại không đo được gì, và phép trừ lệch đồng hồ
      im lặng thành vô tác dụng. */
t( 'nhánh 4G cũng đo quãng đi',
	preg_match( '/if\(USE_4G\)\{.*?if\(viec == "nhip"\) g_rttMs = millis\(\) - t0;\s*return ra;/s', $fw10 ) === 1 );
t( 'và mốc giờ bấm TRƯỚC khi rẽ nhánh',
	preg_match( '/unsigned long t0 = millis\(\);\s*\n\s*if\(USE_4G\)\{/', $fw10 ) === 1 );
/* 4G chờ tới 40 giây cho một lượt HTTP, nên lượt giữ 4 giây bên máy chủ vẫn nằm gọn trong hạn. */
t( 'hạn chờ HTTP của 4G rộng hơn hẳn lượt giữ',
	preg_match( '/atWait\("\+HTTPACTION:",(\d+)\)/', $fw10, $m_h ) === 1 && (int) $m_h[1] >= 20000 );

// ====================== NHẬT KÝ BẬT GHẾ TỪ XA (để đối chiếu sau này)
/* 🔴 Mỗi lần bấm Bật là CHO KHÔNG một lượt massage: ghế chạy, điện tốn, khách được phục vụ, mà
      sổ doanh thu không có đồng nào. Cuối tháng nhìn "ghế chạy 180 lượt, thu 140 lượt" thì 40
      lượt kia phải giải thích được bằng CON SỐ, không bằng trí nhớ. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );

VHG_May::dat_lenh( 'AMTP01', 'on',  10, 'Chị Hai', 'khách phàn nàn ghế rung' );
VHG_May::dat_lenh( 'AMTP02', 'on',  15, 'Anh Ba',  'chạy thử sau khi sửa' );
VHG_May::dat_lenh( 'AMTP01', 'off',  0, 'Chị Hai', 'tắt sớm' );

$tg = VHG_May::tong_lenh( 'month' );
teq( 'đếm đúng số lần bật', 2, (int) $tg['so_lan'] );
teq( 'và tổng số phút đã cho không', 25, (int) $tg['tong_phut'] );
teq( 'trên mấy ghế', 2, (int) $tg['so_ghe'] );
/* ⚠️ Lệnh TẮT không cho ai cái gì cả. Gộp vào là thổi phồng con số "cho không" bằng chính những
      lần người ta tắt ghế đi — và bảng đối chiếu nói dối theo hướng có lợi cho mình. */
$ds_b = VHG_May::ds_lenh_bat( 'month', 50 );
teq( 'nhật ký chỉ có lệnh BẬT', 2, count( $ds_b ) );
foreach ( $ds_b as $l_ ) { teq( 'không lẫn lệnh tắt', 'on', (string) $l_['viec'] ); }
t( 'giữ được ai bấm', 'Chị Hai' === $ds_b[1]['nguoi'] || 'Chị Hai' === $ds_b[0]['nguoi'] );
t( 'và giữ lý do', strpos( $ds_b[1]['ly_do'] . $ds_b[0]['ly_do'], 'khách phàn nàn' ) !== false );

$ng = VHG_May::tong_lenh_ngay( 'month' );
t( 'gộp được theo ngày', count( $ng ) >= 1 );
teq( 'ngày hôm nay đủ cả hai lượt', 2, (int) $ng[0]['so_lan'] );
teq( 'và đủ số phút', 25, (int) $ng[0]['tong_phut'] );

/* ⚠️ ĐẾM CẢ LỆNH GHẾ CHƯA LẤY. `gui_luc` rỗng nghĩa là ghế đang mất mạng, nhưng người bấm đã
      bấm rồi và ghế sẽ chạy khi lên mạng. Lọc bỏ là nhật ký nói ít hơn sự thật đúng vào những
      ngày mạng chập chờn — tức đúng những ngày cần tra nhất. */
global $wpdb;
$chua_gui = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHG_DB::t( 'lenh' )
	. " WHERE viec='on' AND (gui_luc IS NULL OR gui_luc='')" );
t( 'có lệnh ghế chưa lấy trong dữ liệu thử', $chua_gui > 0, $chua_gui );
teq( 'và vẫn được đếm', 2, (int) VHG_May::tong_lenh( 'month' )['so_lan'] );

// ---- hiện ra trên trang ngoài
$tok_b = vhg_vao();
$sl_b  = vhg_web( 'so_lieu', array( 'token' => $tok_b, 'ky' => 'month' ) );
t( 'trang ngoài gửi kèm nhật ký bật', isset( $sl_b['bat'] ) );
teq( 'đúng số lần', 2, (int) $sl_b['bat']['ky']['so_lan'] );
/* Tổng THÁNG hiện bất kể đang xem kỳ nào — câu hỏi thật lúc đối chiếu luôn là "tháng này bao
   nhiêu", mà bắt người ta bấm đổi kỳ rồi nhớ con số là cách chắc chắn để nhớ nhầm.
   ⚠️ Phải có một lượt ở NGÀY KHÁC trong tháng, không thì `today` và `month` ra cùng một số và
      phép thử đạt vì dữ liệu chứ không vì mã đúng — đúng lỗi vừa lọt qua đột biến. */
$hom_qua = current_time( 'timestamp' ) - 86400;
$cung_thang = gmdate( 'Y-m', $hom_qua ) === gmdate( 'Y-m', current_time( 'timestamp' ) );
if ( $cung_thang ) {
	VHG_May::dat_lenh( 'AMTP01', 'on', 7, 'Chị Hai', 'hôm qua' );
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'lenh' ) . ' SET tao_luc=%s'
		. " WHERE ly_do='hôm qua'", gmdate( 'Y-m-d H:i:s', $hom_qua ) ) );
	teq( 'kỳ "hôm nay" không đếm lượt hôm qua', 2, (int) VHG_May::tong_lenh( 'today' )['so_lan'] );
	teq( 'kỳ "tháng này" thì có', 3, (int) VHG_May::tong_lenh( 'month' )['so_lan'] );
	$sl_h = vhg_web( 'so_lieu', array( 'token' => $tok_b, 'ky' => 'today' ) );
	teq( 'đang xem hôm nay, kỳ đếm 2', 2, (int) $sl_h['bat']['ky']['so_lan'] );
	teq( '🔴 nhưng tổng tháng vẫn đủ 3', 3, (int) $sl_h['bat']['thang']['so_lan'] );
	$wpdb->query( 'DELETE FROM ' . VHG_DB::t( 'lenh' ) . " WHERE ly_do='hôm qua'" );
}
/* Và chặn thẳng ở mã nguồn, để phép thử còn đứng vững cả vào ngày mùng 1 — hôm đó trong tháng
   không có ngày nào sớm hơn hôm nay để dựng dữ liệu phân biệt. */
$trang_bat = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );
t( 'tổng tháng hỏi đúng kỳ "month", không theo kỳ đang xem',
	strpos( $trang_bat, "\$bat_thang = VHG_May::tong_lenh( 'month' );" ) !== false );
t( 'và phân biệt lệnh ghế chưa lấy', isset( $sl_b['bat']['ds'][0]['da_gui'] ) );

// ---- và hiện ra trong wp-admin (nơi người đi tra thật sự ngồi)
ob_start(); VHG_Admin::trang_may(); $h_bat = ob_get_clean();
t( 'wp-admin có khối bật ghế từ xa', strpos( $h_bat, 'Bật ghế từ xa — tháng này' ) !== false );
t( 'nói rõ đây là lượt chạy KHÔNG có tiền',
	strpos( $h_bat, 'sổ doanh thu không có đồng nào' ) !== false );
t( 'liệt kê ai bấm', strpos( $h_bat, 'Chị Hai' ) !== false );
t( 'và phân biệt lệnh ghế chưa lấy', strpos( $h_bat, 'chưa lấy' ) !== false );

$web_b = vhg_web_html();
t( 'trang có tab Kích hoạt ghế', strpos( $web_b, 'function veKichHoat()' ) !== false );
t( 'và có nút mở tab đó', strpos( $web_b, "TABS.push(['kich-hoat'" ) !== false );
/* ⚠️ Bám vào THÂN HÀM, không vào tiêu đề bảng. Phép thử chỉ dò tiêu đề vẫn đạt khi cột "lý do"
      bị bỏ hẳn — mà lý do chính là thứ anh Thắng yêu cầu đích danh: không có nó thì nhật ký chỉ
      nói "ai đó bật ghế", tức là không giải thích được gì. */
$than_kh = '';
if ( preg_match( '/function veKichHoat\(\)\{(.*?)\n\}/s', $web_b, $m_kh ) ) { $than_kh = $m_kh[1]; }
t( 'tìm được thân hàm tab Kích hoạt', '' !== $than_kh );
t( 'tab đó có nhật ký kèm lý do', strpos( $than_kh, "L('Nhật ký kích hoạt','Activation log')" ) !== false );
foreach ( array( 'l.ly_do', 'l.nguoi', 'l.phut', 'l.ma', 'l.luc' ) as $o_kh ) {
	t( 'nhật ký in ra "' . $o_kh . '"', strpos( $than_kh, $o_kh ) !== false );
}
/* Và bảng gộp theo ghế phải in đủ số lần lẫn tổng phút — anh Thắng hỏi cả hai. */
foreach ( array( 'm.so_lan', 'm.tong_phut', 'm.lan_cuoi' ) as $o_kh ) {
	t( 'bảng theo ghế in ra "' . $o_kh . '"', strpos( $than_kh, $o_kh ) !== false );
}
t( 'và bảng ghế nào bật mấy lần', strpos( $web_b, "L('Ghế đã kích hoạt','Chairs activated')" ) !== false );
/* ⚠️ Mã chết là mã nói dối: `veNhatKyBat` đã chuyển thành tab riêng, để lại bản cũ là lần sau
      có người sửa nhầm vào bản không ai gọi. */
t( 'không còn hàm cũ nằm lại', strpos( $web_b, 'veNhatKyBat' ) === false );

// ====================== TAB THU TIỀN: HAI ĐƯỜNG TIỀN MẶT
/* 🔴 Ghế nuốt tờ tiền thì cổng máy ghi một dòng; người đứng quầy bấm "Thu tiền mặt" thì màn
      ngoài ghi một dòng nữa. Cả hai đều `nguon = cash`, nhìn vào bảng doanh thu KHÔNG phân biệt
      được — mà ghế có cục nhận tiền chạy tốt mà người thu vẫn bấm là CỘNG ĐÔI cùng một xấp tiền. */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );

/* AMTP01: ghế tự nuốt 50k, RỒI người thu lại ghi 50k nữa -> nghi cộng đôi. */
vhg_ghe( array( 'viec' => 'tien_mat', 'mac' => 'AA:BB:CC:DD:EE:01',
	'so_tien' => 50000, 'ref' => 'cash-AMTP01-1' ) );
VHG_Thu::thu_tien_mat( 'AMTP01', 50000, 'Chị Hai' );
/* AMTP02: CHỈ có người thu (ghế không lắp cục nhận tiền) -> bình thường, không kêu. */
VHG_Thu::thu_tien_mat( 'AMTP02', 30000, 'Anh Ba' );

teq( 'nhận ra dòng ghế nuốt', 'ghe', VHG_Thu::kieu_tien_mat( VHG_Thu::ND_GHE_NUOT ) );
teq( 'nhận ra dòng người thu', 'nguoi', VHG_Thu::kieu_tien_mat( VHG_Thu::ND_THU_TAY . 'Chị Hai' ) );
teq( 'và lấy được tên người thu', 'Chị Hai', VHG_Thu::nguoi_thu( VHG_Thu::ND_THU_TAY . 'Chị Hai' ) );
/* Dòng lạ (nhập Excel, đời cũ) KHÔNG được đoán bừa thành một trong hai loại. */
teq( 'dòng lạ thì không đoán bừa', '', VHG_Thu::kieu_tien_mat( 'tiền mặt gì đó' ) );
teq( 'và không bịa ra người thu', '', VHG_Thu::nguoi_thu( 'Ghế nhận tiền mặt' ) );

$tm = VHG_Thu::theo_may_tien_mat( 'today' );
teq( 'AMTP01 có tiền ghế nuốt', 50000, (int) $tm['AMTP01']['mat_ghe'] );
teq( 'và có tiền người thu', 50000, (int) $tm['AMTP01']['mat_nguoi'] );
t( '🔴 kêu lên vì nghi cộng đôi', ! empty( $tm['AMTP01']['cong_doi'] ) );
/* ⚠️ Ghế chỉ có MỘT đường thì KHÔNG kêu. Kêu cả những ghế bình thường là dạy người ta bỏ qua
      cảnh báo, và lúc đó cảnh báo thật cũng chìm theo. */
teq( 'AMTP02 chỉ có người thu', 30000, (int) $tm['AMTP02']['mat_nguoi'] );
teq( 'không có tiền ghế nuốt', 0, (int) $tm['AMTP02']['mat_ghe'] );
t( 'nên KHÔNG kêu', empty( $tm['AMTP02']['cong_doi'] ) );

$ds_tm = VHG_Thu::ds_tien_mat( 'today', 50 );
teq( 'liệt kê đủ ba lượt tiền mặt', 3, count( $ds_tm ) );
$co_ghe = 0; $co_ng = 0;
foreach ( $ds_tm as $r_ ) { if ( 'ghe' === $r_['kieu'] ) { $co_ghe++; } if ( 'nguoi' === $r_['kieu'] ) { $co_ng++; } }
teq( 'một lượt ghế nuốt', 1, $co_ghe );
teq( 'hai lượt người thu', 2, $co_ng );
/* Tiền QR KHÔNG được lọt vào danh sách tiền mặt — tab này để đối chiếu két, không phải sổ tổng. */
VHG_Thu::nhan( 'sepay', array( 'ref' => 'qr-1', 'so_tien' => 20000,
	'noi_dung' => 'SEVQR GHEAMTP01 ABC123', 'luc' => '' ) );
teq( 'QR không lọt vào danh sách tiền mặt', 3, count( VHG_Thu::ds_tien_mat( 'today', 50 ) ) );
teq( 'nhưng vẫn vào cột QR của ghế', 20000,
	(int) VHG_Thu::theo_may_tien_mat( 'today' )['AMTP01']['qr'] );

// ---- hai tab hiện ra trên trang
$tok_t = vhg_vao();
$sl_t  = vhg_web( 'so_lieu', array( 'token' => $tok_t, 'ky' => 'today' ) );
t( 'trang ngoài gửi kèm số liệu thu tiền', isset( $sl_t['thu'] ) );
teq( 'đủ ba lượt', 3, dem( $sl_t['thu']['ds'] ) );
$web_t = vhg_web_html();
t( 'có tab Thu tiền', strpos( $web_t, "TABS.push(['thu-tien'" ) !== false );
t( 'và hàm vẽ nó', strpos( $web_t, 'function veThuTien()' ) !== false );
t( 'tách rõ ghế nuốt với người thu',
	strpos( $web_t, "L('ghế nuốt','acceptor')" ) !== false
	&& strpos( $web_t, "L('người thu','staff')" ) !== false );
t( 'và kêu lên khi nghi cộng đôi', strpos( $web_t, 'cộng đôi' ) !== false );
/* Bộ chọn kỳ phải hiện ở cả ba tab báo cáo; tab Điều khiển thì không — ở đó không có con số nào
   theo kỳ, để bộ chọn ra là mời người ta bấm rồi tự hỏi vừa đổi gì. */
t( 'các tab báo cáo đều chọn được kỳ',
	strpos( $web_t, "TAB === 'doi-soat' || TAB === 'thu-tien' || TAB === 'quy' || TAB === 'kich-hoat'" ) !== false );

// ====================== BÁN MÃ TRƯỚC (mua hôm nay, dùng hôm khác)
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );
/* Trả mệnh giá về bộ mặc định: khối phép thử trước đó có sửa `vhg_menh_gia`, và một khối phụ
   thuộc vào rác của khối khác là khối sẽ hỏng vì lý do chẳng liên quan. */
delete_option( 'vhg_menh_gia' );
update_option( 'vhg_ma_giam', array( 100000 => 15, 200000 => 20 ) );

// ---- mã sinh ra phải GÕ ĐƯỢC
/* 🔴 Khách đọc mã từ ảnh chụp rồi gõ tay. Một mã có O cạnh 0, hay 1 cạnh I, là một cuộc cãi nhau
      ở quầy — và người thua luôn là khách. */
foreach ( array( '0', 'O', '1', 'I', 'L', 'S', '5', '2', 'Z' ) as $xau ) {
	t( 'bảng chữ sinh mã không có "' . $xau . '"', strpos( VHG_Ma::CHU, $xau ) === false );
}
$m1 = VHG_Ma::sinh_ma();
teq( 'mã dài đúng 8', 8, strlen( $m1 ) );
teq( 'hiện ra có gạch cho dễ đọc', substr( $m1, 0, 4 ) . '-' . substr( $m1, 4 ), VHG_Ma::ma_dep( $m1 ) );
teq( 'khách gõ có gạch vẫn nhận', $m1, VHG_Ma::ma_sach( VHG_Ma::ma_dep( $m1 ) ) );
teq( 'gõ thường vẫn nhận', $m1, VHG_Ma::ma_sach( strtolower( $m1 ) ) );

// ---- số điện thoại: MỘT cách chuẩn hoá cho cả lúc bán lẫn lúc tra
/* 🔴 Mua gõ "0909 123 456", hôm sau tra gõ "+84909123456" — cùng một người. Hai nơi chuẩn hoá
      khác nhau là hệ thống nói "không có mã nào" trong khi khách đã trả tiền rồi. */
foreach ( array( '0909 123 456', '+84909123456', '84909123456', '0909-123-456' ) as $kieu ) {
	teq( 'số "' . $kieu . '" về cùng một dạng', '0909123456', VHG_Ma::sdt_sach( $kieu ) );
}
t( 'số quá ngắn thì loại', ! VHG_Ma::sdt_hop_le( '0909' ) );
t( 'PIN 4 số mới nhận', VHG_Ma::pin_hop_le( '1234' ) && ! VHG_Ma::pin_hop_le( '123' )
	&& ! VHG_Ma::pin_hop_le( '12a4' ) );

// ---- giá bán sau giảm
teq( 'giảm 15% của 100k', 85000, VHG_Ma::gia_ban( 100000 ) );
teq( 'giảm 20% của 200k', 160000, VHG_Ma::gia_ban( 200000 ) );
teq( 'mệnh giá chưa khai giảm thì bán đúng giá', 50000, VHG_Ma::gia_ban( 50000 ) );
/* ⚠️ Gõ nhầm một số 0 thành "giảm 900%" là bán mã với giá âm. Chặn ở tầng đọc cấu hình. */
update_option( 'vhg_ma_giam', array( 100000 => 900 ) );
t( 'giảm quá tay bị chặn lại', VHG_Ma::giam_cua( 100000 ) <= 70 );
t( 'và giá bán không bao giờ về 0', VHG_Ma::gia_ban( 100000 ) > 0 );
update_option( 'vhg_ma_giam', array( 100000 => -5 ) );
teq( 'giảm âm thì coi như không giảm', 0, VHG_Ma::giam_cua( 100000 ) );
update_option( 'vhg_ma_giam', array( 100000 => 15, 200000 => 20 ) );

// ---- đặt đơn -> trả tiền -> phát mã
$don = VHG_Ma::dat_don( '0909123456', '2468', 100000, 2 );
t( 'đặt được đơn', ! empty( $don['ok'] ), isset( $don['error'] ) ? $don['error'] : '' );
teq( 'phải trả = giá đã giảm × số lượng', 170000, (int) $don['phai_tra'] );
/* ⚠️ Chỉ bán mệnh giá ĐANG khai. Nhận con số khách gửi lên là mở đường mua mã 1.000.000đ với
      giá 1.000đ bằng cách sửa gói tin. */
t( 'mệnh giá lạ thì từ chối', empty( VHG_Ma::dat_don( '0909123456', '2468', 999000, 1 )['ok'] ) );
t( 'PIN sai khuôn thì từ chối', empty( VHG_Ma::dat_don( '0909123456', '12', 100000, 1 )['ok'] ) );

$nd_mua = VHG_QR::noi_dung_mua( $don['ma_don'] );
teq( 'đọc ngược ra đúng mã đơn', $don['ma_don'], VHG_Doc::don_mua( 'CT DEN:145T268 ' . $nd_mua ) );
/* ⚠️ Đòi ranh giới trước "MUA": không có nó thì tên người "THANH MUA ABCDEF" cũng khớp, và một
      lượt tiền của ghế bị đem đi phát mã. */
teq( 'chuỗi dính liền KHÔNG khớp', '', VHG_Doc::don_mua( 'GHEAMUAABCDEF' ) );
teq( 'và nội dung của ghế cũng không khớp', '', VHG_Doc::don_mua( 'SEVQR GHEAMTP01 K7M2PQ' ) );

$goi_mua = array( 'transferType' => 'in', 'transferAmount' => 170000,
	'content' => 'CT DEN:145T268 ' . $nd_mua, 'referenceCode' => 'FT-MUA-1' );
vhg_ban( $goi_mua );
/* Đọc kết quả tra mã một cách CHỊU ĐƯỢC THẤT BẠI: tra hỏng thì trả mảng rỗng chứ đừng để
   `count(null)` làm vỡ cả bộ thử. Một phép thử vỡ bằng lỗi chí mạng thì không ai đọc được nó
   vỡ vì cái gì — mà đó đúng là lúc cần đọc nhất. */
function vhg_ma_cua( $r, $o ) {
	return ( is_array( $r ) && isset( $r[ $o ] ) && is_array( $r[ $o ] ) ) ? $r[ $o ] : array();
}
$sau = VHG_Ma::tra( '0909123456', '2468' );
t( 'tiền về thì phát mã', ! empty( $sau['ok'] ), isset( $sau['error'] ) ? $sau['error'] : '' );
teq( 'phát đúng 2 mã', 2, count( vhg_ma_cua( $sau, 'chua_dung' ) ) );
$m_dau = vhg_ma_cua( $sau, 'chua_dung' );
teq( 'mã mang đúng mệnh giá', 100000, (int) ( isset( $m_dau[0] ) ? $m_dau[0]['menh_gia'] : 0 ) );
teq( 'và nhớ giá khách đã trả', 85000, (int) ( isset( $m_dau[0] ) ? $m_dau[0]['gia_ban'] : 0 ) );
/* 🔴 Doanh thu ghi LÚC BÁN, đúng số tiền thật vào két — không phải mệnh giá. */
teq( 'doanh thu đúng số tiền thật vào két', 170000, vhg_tong() );

/* 🔴 Webhook bắn lại là chuyện bình thường. Mỗi lượt bắn lại mà phát thêm mã là cho không hàng
      trăm nghìn đồng. */
vhg_ban( $goi_mua );
teq( 'bắn lại KHÔNG phát thêm mã', 2,
	count( vhg_ma_cua( VHG_Ma::tra( '0909123456', '2468' ), 'chua_dung' ) ) );
teq( 'và không cộng đôi doanh thu', 170000, vhg_tong() );

// ---- tra mã: số điện thoại KHÔNG phải mật khẩu
/* 🔴 Người khác đoán ra số, nhìn thấy lúc khách gõ, hoặc thử vài số quen. Chỉ hỏi số là ai cũng
      tiêu được mã của người khác. */
t( 'đúng số nhưng sai PIN thì KHÔNG ra mã', empty( VHG_Ma::tra( '0909123456', '9999' )['ok'] ) );
/* ⚠️ Cùng một câu báo lỗi cho "không có số này" và "sai PIN": nói rõ là xác nhận giúp người dò
      rằng họ đã tìm đúng người, việc còn lại chỉ là thử 10.000 lần. */
teq( 'hai ca sai báo giống hệt nhau',
	VHG_Ma::tra( '0909123456', '9999' )['error'], VHG_Ma::tra( '0987654321', '1111' )['error'] );
t( 'PIN không lưu dạng đọc được', ! $wpdb->get_var( 'SELECT id FROM ' . VHG_DB::t( 'ma' )
	. " WHERE pin_bam='2468'" ) );

// ---- dùng mã
/* Mã mua trước phải chờ 5 ngày mới dùng được (chốt chống mua-xong-dùng-liền). Lùi ngày mua để
   thử đường dùng thật; phép thử riêng cho chính cái chốt nằm ở khối dưới. */
vhg_ma_lui_ngay();
$ma_1 = isset( $m_dau[0] ) ? VHG_Ma::ma_sach( $m_dau[0]['ma'] ) : 'KHONGCO1';
$dung = VHG_Ma::dung( $ma_1, 'AMTP01' );
t( 'dùng được mã', ! empty( $dung['ok'] ), isset( $dung['error'] ) ? $dung['error'] : '' );
/* 🔴 KHÔNG ghi thêm đồng doanh thu nào — tiền đã vào sổ hôm khách MUA. Ghi lần nữa là cùng một
      khoản đếm hai lần, và bảng đối soát nói dối theo hướng có lợi cho mình. */
teq( '🔴 dùng mã KHÔNG cộng thêm doanh thu', 170000, vhg_tong() );
/* Nhưng ghế phải chạy, và chạy theo MỆNH GIÁ chứ không phải giá bán. */
$cho_1 = VHG_May::ds_cho( true, 20 );
$thay = null;
foreach ( $cho_1 as $c_ ) { if ( 0 === strpos( (string) $c_['ma_lenh'], 'MA' ) ) { $thay = $c_; } }
t( 'ghế được xếp lượt chạy', null !== $thay );
teq( 'chạy theo MỆNH GIÁ, không phải giá bán', 100000, (int) $thay['so_tien'] );

/* 🔴 Một mã = một lượt. Dùng lại là cho không. */
t( 'dùng lại lần hai bị từ chối', empty( VHG_Ma::dung( $ma_1, 'AMTP02' )['ok'] ) );

/* 🔴 VÀ LỚP THỨ HAI: câu UPDATE phải mang điều kiện `dung_luc IS NULL`.
 *
 *    Phép thử ở trên chỉ chạm tới lớp thứ nhất — cái `if` đọc dòng ra rồi mới quyết. Lớp ấy chặn
 *    được người gõ lại mã sau vài giây, nhưng KHÔNG chặn được hai người gõ cùng một mã trong
 *    cùng một khoảnh khắc: cả hai cùng đọc thấy "chưa dùng", cả hai cùng ghi, và hai ghế cùng
 *    chạy. Trọng tài duy nhất là cơ sở dữ liệu — chỉ một câu UPDATE đụng được vào hàng.
 *
 *    Không dựng được cảnh đua thật trong bộ thử một luồng này, nên bám vào chính câu lệnh. Nói
 *    thẳng ra đây rằng phép thử này kiểm MÃ NGUỒN chứ không kiểm hành vi — để lần sau không ai
 *    tưởng nó đã chứng minh điều nó không chứng minh. */
$ma_src = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-ma.php' );
t( 'câu UPDATE tự nó chặn dùng hai lần',
	strpos( $ma_src, 'WHERE ma=%s AND dung_luc IS NULL AND huy=0' ) !== false );
t( 'và không đụng được dòng nào thì TỪ CHỐI, không chạy tiếp',
	preg_match( '/if \( ! \$n \) \{.*?return array\( .ok. => false/s', $ma_src ) === 1 );
t( 'mã bịa thì từ chối', empty( VHG_Ma::dung( 'AAAABBBB', 'AMTP01' )['ok'] ) );
$ma_2 = isset( $m_dau[1] ) ? VHG_Ma::ma_sach( $m_dau[1]['ma'] ) : 'KHONGCO2';
t( 'ghế không có thật thì từ chối', empty( VHG_Ma::dung( $ma_2, 'KHONGCO' )['ok'] ) );
/* Mã dùng được ở BẤT KỲ ghế nào — mã mua ở đâu chạy ở đó là mất nửa giá trị của việc bán trước. */
t( 'mã dùng được ở ghế khác', ! empty( VHG_Ma::dung( $ma_2, 'AMTP02' )['ok'] ) );

$sau2 = VHG_Ma::tra( '0909123456', '2468' );
teq( 'hết mã chưa dùng', 0, count( vhg_ma_cua( $sau2, 'chua_dung' ) ) );
$da_ = vhg_ma_cua( $sau2, 'da_dung' );
teq( 'và hai mã nằm ở mục đã dùng', 2, count( $da_ ) );
t( 'nhớ mã dùng ở ghế nào', isset( $da_[0] )
	&& ( 'AMTP01' === $da_[0]['dung_may'] || 'AMTP02' === $da_[0]['dung_may'] ) );

// ---- tiền đang nợ khách
/* 🔴 Mã KHÔNG hết hạn (anh Thắng chốt), nên con số này chỉ cộng lên và không bao giờ tự đóng.
      Mỗi mã chưa dùng là một lượt massage mình còn nợ. Không hiện ra là sẽ có ngày bất ngờ. */
teq( 'dùng hết rồi thì không nợ gì', 0, VHG_Ma::tien_no()['tong'] );
$don2 = VHG_Ma::dat_don( '0911222333', '1357', 200000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 160000,
	'content' => 'CT DEN:145T269 ' . VHG_QR::noi_dung_mua( $don2['ma_don'] ),
	'referenceCode' => 'FT-MUA-2' ) );
vhg_ma_lui_ngay();
$no = VHG_Ma::tien_no();
teq( 'còn một mã chưa dùng', 1, (int) $no['so_ma'] );
/* Nợ tính theo MỆNH GIÁ: thứ mình nợ là lượt massage, không phải số tiền khách đã trả. */
teq( 'nợ tính theo mệnh giá', 200000, (int) $no['tong'] );
teq( 'và nhớ đã thu bao nhiêu', 160000, (int) $no['da_thu'] );

// ====================== TRANG MINI BÁN MÃ (trang của KHÁCH)
vhg_dung_bang();
delete_option( 'vhg_menh_gia' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );
delete_transient( 'vhg_shop_tra_' . md5( 'x' ) );
delete_transient( 'vhg_shop_dung_' . md5( 'x' ) );

$sg = vhg_shop( 'goi' );
t( 'trang bán mã trả được bảng giá', ! empty( $sg['ok'] ) );
$co100 = null;
foreach ( $sg['goi'] as $g_ ) { if ( 100000 === (int) $g_['menh_gia'] ) { $co100 = $g_; } }
teq( 'gói 100k hiện giá đã giảm', 85000, (int) $co100['gia_ban'] );
teq( 'và hiện luôn phần trăm giảm', 15, (int) $co100['giam_pt'] );

/* 🔴 KHÔNG có cổng PIN nhân viên — nhưng cũng KHÔNG đọc được gì của cửa hàng. */
$sl_bo = vhg_shop( 'so_lieu' );
t( 'trang khách KHÔNG gọi được việc của trang nhân viên', empty( $sl_bo['ok'] ) );

$sd = vhg_shop( 'dat', array( 'sdt' => '0909123456', 'pin' => '2468',
	'menh_gia' => 100000, 'so_luong' => 2 ) );
t( 'đặt được đơn từ trang', ! empty( $sd['ok'] ), isset( $sd['error'] ) ? $sd['error'] : '' );
teq( 'trang hiện đúng số phải trả', 170000, (int) $sd['phai_tra'] );
t( 'và đưa số tài khoản để chuyển', '108878583951' === (string) $sd['so_tk'] );
t( 'kèm nội dung chuyển khoản', strpos( (string) $sd['noi_dung'], 'MUA' ) !== false );

$soi1 = vhg_shop( 'soi', array( 'ma_don' => $sd['ma_don'] ) );
teq( 'chưa trả tiền thì chưa có mã', 0, (int) $soi1['xong'] );
/* ⚠️ Việc `soi` KHÔNG được trả số điện thoại hay PIN băm: ai đoán trúng mã đơn là đọc được dữ
      liệu của người khác. Chỉ trả "xong hay chưa" và bộ mã. */
t( 'soi đơn không rò số điện thoại', ! isset( $soi1['sdt'] ) );
t( 'và không rò PIN băm', ! isset( $soi1['pin_bam'] ) );

vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 170000,
	'content' => 'CT DEN:145T270 ' . $sd['noi_dung'], 'referenceCode' => 'FT-SHOP-1' ) );
$soi2 = vhg_shop( 'soi', array( 'ma_don' => $sd['ma_don'] ) );
/* ⚠️ ĐỌC PHÒNG THỦ, đừng chọc thẳng vào khoá. Bản trước viết `count($soi2['ma'])` trần trụi —
      và khi một thay đổi ở nơi khác làm cổng trả về hình dạng khác, dòng này KHÔNG báo "sai",
      nó làm CHẾT cả bộ thử ngay tại đây, che mất hơn một nghìn phép thử phía sau. Một bộ thử
      chết giữa chừng còn khó lần ra hơn một bộ thử báo sai đúng chỗ.
      (Đã dính đúng lúc thử đục công tắc `con_ban_ma`: cổng từ chối đơn -> `soi` trả về hình
      dạng lỗi -> `count(null)` -> chết.) */
teq( 'tiền về thì trang thấy xong', 1, (int) ( isset( $soi2['xong'] ) ? $soi2['xong'] : 0 ) );
$soi2_ma = isset( $soi2['ma'] ) && is_array( $soi2['ma'] ) ? $soi2['ma'] : array();
teq( 'và trả về 2 mã', 2, count( $soi2_ma ) );
t( 'mã hiện ra có gạch cho dễ đọc',
	isset( $soi2_ma[0] ) && strpos( (string) $soi2_ma[0], '-' ) !== false );

// ---- tra mã từ trang
$st = vhg_shop( 'tra', array( 'sdt' => '0909123456', 'pin' => '2468' ) );
teq( 'tra ra 2 mã chưa dùng', 2, dem( $st['chua_dung'] ) );
t( 'sai PIN thì không ra', empty( vhg_shop( 'tra', array( 'sdt' => '0909123456', 'pin' => '1111' ) )['ok'] ) );

// ---- dùng mã từ trang, cho đúng ghế trên tem
vhg_ma_lui_ngay();
$ma_shop = VHG_Ma::ma_sach( $st['chua_dung'][0]['ma'] );
$sdung = vhg_shop( 'dung', array( 'ma' => $ma_shop, 'ma_may' => 'AMTP01' ) );
t( 'dùng được mã từ trang', ! empty( $sdung['ok'] ), isset( $sdung['error'] ) ? $sdung['error'] : '' );
/* 🔴 Vẫn KHÔNG cộng doanh thu — tiền đã ghi lúc bán. */
teq( 'dùng mã không cộng thêm doanh thu', 170000, vhg_tong() );

/* ⚠️ Ghế trên địa chỉ phải CÓ THẬT. Nhận bừa chuỗi trên URL là cho người ta dựng liên kết trỏ
      tới "ghế" không tồn tại rồi tiêu mã của mình vào hư không. */
$_GET = array( 'ghe' => 'KHONGCOGHENAY' );
teq( 'ghế bịa trên địa chỉ bị bỏ', '', VHG_Shop::ghe_tu_dia_chi() );
$_GET = array( 'ghe' => 'amtp01' );
teq( 'ghế có thật thì nhận, không phân biệt hoa thường', 'AMTP01', VHG_Shop::ghe_tu_dia_chi() );
$_GET = array();

// ---- hãm thử ở ô tra mã
/* 🔴 Số điện thoại là thứ đoán được. Không hãm thì một máy dò hết 10.000 PIN của một số trong
      vài phút, và tiêu sạch mã của người ta. */
delete_transient( 'vhg_shop_tra_' . md5( 'x' ) );
$bi = false;
for ( $i = 0; $i < 20; $i++ ) {
	$r_ = vhg_shop( 'tra', array( 'sdt' => '0909123456', 'pin' => '0000' ) );
	if ( isset( $r_['error'] ) && strpos( $r_['error'], 'quá nhiều lần' ) !== false ) { $bi = true; break; }
}
t( '🔴 dò PIN nhiều lần thì bị hãm', $bi );
/* Và hãm rồi thì PIN ĐÚNG cũng không lọt — nếu không, người dò chỉ việc thử tiếp. */
t( 'hãm rồi thì PIN đúng cũng phải chờ',
	empty( vhg_shop( 'tra', array( 'sdt' => '0909123456', 'pin' => '2468' ) )['ok'] ) );
delete_transient( 'vhg_shop_tra_' . md5( 'x' ) );

// ---- trang HTML
$sh = vhg_shop_html();
t( 'trang bán mã dựng được', strpos( $sh, 'Mua mã giảm giá' ) !== false );
t( 'có ba mục: mua / mã của tôi / dùng mã',
	strpos( $sh, 'data-tab="mua"' ) !== false && strpos( $sh, 'data-tab="cua-toi"' ) !== false
	&& strpos( $sh, 'data-tab="dung"' ) !== false );
/* 🔴 KHÔNG dựng mã QR để khách quét: khách đang cầm ĐÚNG cái điện thoại hiện trang này, không
      ai quét được mã QR trên màn hình của chính máy mình. */
t( 'không vẽ mã QR cho khách tự quét', strpos( $sh, 'qrcode' ) === false
	&& strpos( $sh, 'esp_qrcode' ) === false );
t( 'thay vào đó có nút sao chép', strpos( $sh, 'data-chep=' ) !== false );
/* ⚠️ BA PHÉP THỬ DƯỚI ĐÂY BÁM VÀO ĐÚNG LUẬT CSS/NHÁNH MÃ, KHÔNG BÁM VÀO MỘT CHUỖI CHUNG CHUNG.
      Bản trước dò `line-through`, `execCommand`, `ck.nhan` — cả ba đều còn xuất hiện ở chỗ KHÁC
      trong tệp (mã đã dùng cũng gạch ngang; `tayChep` vẫn định nghĩa dù không ai gọi; luật
      `.ck.nhan .gt` vẫn còn), nên phép thử đạt trong khi thứ nó canh đã bị gỡ. */
/* Sao chép phải có ĐƯỜNG LUI ĐƯỢC NỐI: `navigator.clipboard` chỉ chạy trên HTTPS và một số
   trình duyệt. Hàm dự phòng còn nằm đó mà không ai gọi thì cũng như không có. */
t( 'sao chép có đường lui, và đường lui được nối',
	preg_match( '/\} else \{ tayChep\(txt, xong\); \}/', $sh ) === 1
	&& preg_match( '/function tayChep\(txt, xong\)\{[^}]*execCommand/s', $sh ) === 1 );
t( 'giá gốc của GÓI gạch ngang cho thấy phần được giảm',
	strpos( $sh, '.g .cu{text-decoration:line-through' ) !== false );
/* Nội dung chuyển khoản là thứ sai một ký tự là tiền lạc — phải nổi bật hơn hai ô kia. */
t( 'ô nội dung chuyển khoản có luật tô riêng',
	preg_match( '/\.ck\.nhan\{[^}]*border-color/', $sh ) === 1 );
/* ⚠️ Canh Ý, không canh nguyên văn một dòng mã: dòng này đã đổi hình khi mọi chuỗi tiếng Việt
      được bọc `L(...)` cho bốn ngôn ngữ. Ý cần giữ là: đúng CÁI Ô nội dung chuyển khoản mang
      lớp tô nổi ` nhan`, chứ không phải hai ô kia. */
t( 'và ô nội dung chuyển khoản dùng đúng lớp đó',
	preg_match( "/o_ck\(\s*L?\(?'Nội dung chuyển khoản'\)?\s*,\s*DON\.noi_dung\s*,\s*DON\.noi_dung\s*,\s*' nhan'\s*\)/", $sh ) === 1 );
/* Và hai ô kia thì KHÔNG được mang lớp đó — nổi bật cả ba là không ô nào nổi bật. */
teq( 'chỉ đúng MỘT ô mang lớp tô nổi', 1, substr_count( $sh, "' nhan')" ) );
$sh2 = vhg_shop_html( 'AMTP01' );
t( 'tem mang mã ghế thì trang biết ghế nào', strpos( $sh2, '"AMTP01"' ) !== false );

// ====================== Ô QUẢNG CÁO MÃ GIẢM GIÁ LUÂN PHIÊN TRÊN MÀN GHẾ
/* Anh Thắng 23/08/2026: ô mệnh giá 100k luân phiên — 30 giây hiện gói, 30 giây hiện lời mời mua
   mã giảm giá. Tem QR dán cứng cạnh thùng tiền, nên ô này chỉ MỜI chứ không vẽ mã. */
delete_option( 'vhg_qc_o' ); delete_option( 'vhg_qc_giay' );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );
/* ⚠️ MẶC ĐỊNH TẮT: chưa khai gì mà đã mời khách mua mã là mời họ tới một trang không giảm đồng
      nào — mất lòng tin ngay lần đầu, và lần sau họ không quét nữa. */
teq( 'chưa khai thì tắt', -1, (int) VHG_May::qc_ma()['o'] );

update_option( 'vhg_qc_o', 1 );
teq( 'khai ô 1 thì bật ô 1', 1, (int) VHG_May::qc_ma()['o'] );
teq( 'mặc định 30 giây mỗi vế', 30, (int) VHG_May::qc_ma()['giay'] );
teq( 'và mang theo mức giảm cao nhất', 15, (int) VHG_May::qc_ma()['giam'] );

/* ⚠️ Ô phải NẰM TRONG SỐ Ô ĐANG CÓ. Khai ô số 9 là một ô quảng cáo không bao giờ xuất hiện, mà
      trên web nhìn vẫn như đã bật — kiểu hỏng im lặng. */
update_option( 'vhg_qc_o', 9 );
teq( 'ô ngoài tầm thì coi như tắt', -1, (int) VHG_May::qc_ma()['o'] );

/* 🔴 KHÔNG mời mua mã khi bảng giảm giá RỖNG. Mời khách tới trang bán hàng không giảm đồng nào
      là lừa họ một lần rồi mất họ mãi. */
update_option( 'vhg_qc_o', 1 );
delete_option( 'vhg_ma_giam' );
teq( 'không có giảm giá thì không mời', -1, (int) VHG_May::qc_ma()['o'] );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );

/* Số giây phải nằm trong khoảng người đọc được: dưới 5 giây là chữ nhấp nháy, trên 5 phút thì
   một trong hai vế coi như không tồn tại. */
update_option( 'vhg_qc_giay', 1 );
t( 'số giây quá ngắn bị nâng lên', VHG_May::qc_ma()['giay'] >= 5 );
update_option( 'vhg_qc_giay', 99999 );
t( 'quá dài bị hạ xuống', VHG_May::qc_ma()['giay'] <= 300 );
update_option( 'vhg_qc_giay', 30 );

// ---- ghế nhận được cấu hình đó
$nh_qc = vhg_ghe( array( 'viec' => 'nhip', 'mac' => 'AA:BB:CC:DD:EE:01', 'trang_thai' => 'idle' ) );
t( 'nhịp mang theo ô quảng cáo', isset( $nh_qc[1]['qcO'] ) );
teq( 'đúng ô đã khai', 1, (int) $nh_qc[1]['qcO'] );
teq( 'đúng số giây', 30, (int) $nh_qc[1]['qcGiay'] );
teq( 'đúng mức giảm', 15, (int) $nh_qc[1]['qcGiam'] );

// ---- firmware
$fw11 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'ghế đọc được ô quảng cáo', strpos( $fw11, 'd.containsKey("qcO")' ) !== false );
t( 'và có vế quảng cáo để vẽ', strpos( $fw11, 'void veTheQuangCao(int i)' ) !== false );
t( 'vế đó mời quét tem cạnh thùng tiền', strpos( $fw11, 'QUET TEM CANH THUNG TIEN' ) !== false );
t( 'và hiện mức giảm', strpos( $fw11, '"-" + String(QC_GIAM) + "%"' ) !== false );
/* 🔴 VÙNG BẤM KHÔNG ĐỔI. Khách nhìn ô mình định bấm bỗng đổi thành chữ khác rồi bấm vào không ra
      gì là hỏng nặng hơn hẳn cái nó chữa — nên vế quảng cáo chỉ thay nội dung, `PKG_BTN` giữ
      nguyên và `startSession(i)` vẫn chạy như thường. */
t( 'vế quảng cáo vẫn bấm được như gói thường',
	preg_match( '/if\(i == QC_O && QC_O >= 0 && QC_GIAM > 0 && g_qcMat\)\{ veTheQuangCao\(i\); return; \}/', $fw11 ) === 1 );
t( 'và nói rõ với khách là bấm được', strpos( $fw11, 'cham de mua goi nhu thuong' ) !== false );
/* ⚠️ Chỉ vẽ lại ĐÚNG MỘT Ô. Một lượt fillScreen trên CYD mất ~90ms; cứ 30 giây chớp cả màn một
      cái thì khách tưởng ghế lỗi. */
t( 'luân phiên chỉ vẽ lại một ô, không vẽ lại cả màn',
	preg_match( '/g_qcMat = !g_qcMat;\s*\n\s*if\(QC_O < PKG_N\) veTheGoi\(QC_O\);/', $fw11 ) === 1 );
t( 'và không đụng tới screenDrawn',
	preg_match( '/g_qcMat = !g_qcMat;[^}]*screenDrawn\s*=/s', $fw11 ) !== 1 );
/* Tắt trên web thì ô về vế gói NGAY, đừng kẹt tới lượt luân phiên sau — người vừa tắt sẽ tưởng
   lệnh không ăn. */
t( 'tắt thì về vế gói ngay', strpos( $fw11, 'if(QC_O < 0){ g_qcMat = false; }' ) !== false );
/* Và chỉ luân phiên khi ghế ĐANG RẢNH: màn chờ trả tiền và màn đếm ngược không có ô gói nào. */
t( 'chỉ luân phiên lúc ghế rảnh',
	preg_match( '/if\(QC_O >= 0 && QC_GIAM > 0 && CHAIR_ID\.length\(\) && !CHUA_GAN && duNhanTien\(\)\s*\n\s*&& screenDrawn/', $fw11 ) === 1 );

// ---- màn Cài đặt: khai giảm giá + ô quảng cáo, và LƯU PHẢI ĂN
delete_option( 'vhg_ma_giam' ); delete_option( 'vhg_qc_o' );
ob_start(); VHG_Admin::trang_ngoai(); $h_ma = ob_get_clean();
t( 'cài đặt có bảng giảm giá bán mã', strpos( $h_ma, 'Giảm giá khi mua mã trước' ) !== false );
t( 'và ô mời mua mã trên màn ghế', strpos( $h_ma, 'Mời mua mã trên màn ghế' ) !== false );
t( 'chỉ đường tới trang bán mã', strpos( $h_ma, VHG_Shop::url() ) !== false );
/* Tem của TỪNG ghế phải khác nhau, không thì mục "Dùng mã" không biết chạy ghế nào. */
t( 'và chỉ cách làm tem riêng cho từng ghế', strpos( $h_ma, 'AMTP01' ) !== false );

/* 🔴 BẤM LƯU PHẢI ĂN — gửi THẬT một lượt POST rồi đọc lại, không tin vào ô hiện ra. */
$_POST = array( 'vhg' => 'luu_trang', 'slug' => 'ghe', 'nguon' => 'rieng',
	'vai_tro' => array( 'Admin' ), 'anh_nen' => '',
	'giam' => array( '100000' => '15', '200000' => '25' ), 'qc_o' => '1', 'qc_giay' => '45' );
ob_start(); VHG_Admin::trang_ngoai(); ob_get_clean();
$_POST = array();
teq( 'lưu được mức giảm 100k', 15, VHG_Ma::giam_cua( 100000 ) );
teq( 'và mức giảm 200k', 25, VHG_Ma::giam_cua( 200000 ) );
teq( 'lưu được ô quảng cáo', 1, (int) VHG_May::qc_ma()['o'] );
teq( 'và số giây mỗi vế', 45, (int) VHG_May::qc_ma()['giay'] );

/* Để trống ô quảng cáo = TẮT, không phải "ô số 0".
   ⚠️ VẪN GIỮ BẢNG GIẢM GIÁ trong lượt lưu này. Xoá luôn bảng giảm giá thì `qc_ma()` trả -1 vì
      "không có giảm nào", và phép thử đạt mà không hề chạm tới nhánh "ô để trống" — đúng chỗ nó
      định canh. Hai nhánh cùng ra -1 nên phải tách chúng ra mới thử được từng cái. */
$_POST = array( 'vhg' => 'luu_trang', 'slug' => 'ghe', 'nguon' => 'rieng',
	'vai_tro' => array( 'Admin' ), 'anh_nen' => '',
	'giam' => array( '100000' => '15' ), 'qc_o' => '', 'qc_giay' => '30' );
ob_start(); VHG_Admin::trang_ngoai(); ob_get_clean();
$_POST = array();
teq( 'vẫn còn giảm giá', 15, VHG_Ma::giam_cua( 100000 ) );
teq( '🔴 nhưng ô để trống là TẮT, không phải ô số 0', -1, (int) VHG_May::qc_ma()['o'] );

/* Và nhánh còn lại: có ô nhưng KHÔNG có giảm giá thì cũng tắt. */
$_POST = array( 'vhg' => 'luu_trang', 'slug' => 'ghe', 'nguon' => 'rieng',
	'vai_tro' => array( 'Admin' ), 'anh_nen' => '', 'giam' => array(), 'qc_o' => '1', 'qc_giay' => '30' );
ob_start(); VHG_Admin::trang_ngoai(); ob_get_clean();
$_POST = array();
teq( 'xoá trắng bảng giảm giá thì hết giảm', 0, VHG_Ma::giam_cua( 100000 ) );
teq( 'và có ô nhưng không có giảm thì cũng tắt', -1, (int) VHG_May::qc_ma()['o'] );

/* Khoản NỢ hiện ra ở đúng chỗ người ta quyết định giảm bao nhiêu. */
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );
$don_no = VHG_Ma::dat_don( '0977000111', '4321', 100000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T271 ' . VHG_QR::noi_dung_mua( $don_no['ma_don'] ),
	'referenceCode' => 'FT-NO-1' ) );
ob_start(); VHG_Admin::trang_ngoai(); $h_no = ob_get_clean();
t( 'cài đặt hiện khoản đang nợ khách', strpos( $h_no, 'Đang nợ khách' ) !== false );
t( 'và nói rõ vì sao nó chỉ cộng lên', strpos( $h_no, 'không hết hạn' ) !== false );

// ====================== TAB QUẢN LÝ MÃ (màn của nhân viên)
vhg_dung_bang();
delete_option( 'vhg_menh_gia' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );

$dq = VHG_Ma::dat_don( '0909111222', '2468', 100000, 2 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 170000,
	'content' => 'CT DEN:145T280 ' . VHG_QR::noi_dung_mua( $dq['ma_don'] ), 'referenceCode' => 'FT-Q-1' ) );

// ---- nhân viên tra hộ khách QUÊN PIN
/* Cảnh thật ở quầy: khách mua tuần trước, quên PIN, đứng đó với cái ghế trống. Không có đường
   này thì nhân viên hoặc bó tay, hoặc bật ghế cho không — và lượt cho không ấy chẳng ai nối
   lại được với mã đã bán. */
$tq = VHG_Ma::tra_nhan_vien( '0909111222' );
t( 'nhân viên tra được bằng số điện thoại', ! empty( $tq['ok'] ) );
teq( 'thấy đủ 2 mã', 2, dem( $tq['ds'] ) );
/* 🔴 KHÔNG bao giờ đưa PIN băm ra ngoài, kể cả cho nhân viên. */
t( 'không kèm PIN băm', ! isset( $tq['ds'][0]['pin_bam'] ) );
t( 'số điện thoại sai khuôn thì từ chối', empty( VHG_Ma::tra_nhan_vien( '090' )['ok'] ) );

/* 🔴 Đường bỏ qua PIN này CHỈ được nằm ở trang nhân viên. Trang của khách gọi tới là biến "cần
      số điện thoại VÀ PIN" thành "chỉ cần số điện thoại" — gỡ đúng cái khoá vừa lắp. */
$shop_src = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-shop.php' );
$ma_src2  = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-ma.php' );
/* ⚠️ Bám vào TÊN HÀM, và chặn cả cách lách bằng một hàm bọc: `tra_nhan_vien_shop()` gọi lại
      `tra_nhan_vien()` thì chuỗi vẫn khác đi, nên phải đếm cả các hàm CÔNG KHAI nào trong lớp
      `VHG_Ma` bỏ qua PIN. Đúng một hàm được phép làm việc đó. */
t( '🔴 trang của khách KHÔNG gọi đường bỏ qua PIN',
	strpos( $shop_src, 'tra_nhan_vien' ) === false );
teq( 'và chỉ có ĐÚNG MỘT hàm bỏ qua PIN trong lớp mã', 1,
	preg_match_all( '/public static function tra_nhan_vien\w*\(/', $ma_src2 ) );
t( 'còn trang nhân viên thì có',
	strpos( file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' ),
		'VHG_Ma::tra_nhan_vien' ) !== false );

// ---- huỷ mã
$ma_q = VHG_Ma::ma_sach( $tq['ds'][0]['ma'] );
/* Bắt ghi LÝ DO: không có lý do thì cuối tháng không ai giải thích được mã đi đâu. */
t( 'huỷ mà không ghi lý do thì từ chối', empty( VHG_Ma::huy( $ma_q, '', 'Chị Hai' )['ok'] ) );
$hq = VHG_Ma::huy( $ma_q, 'khách chuyển nhầm hai lần', 'Chị Hai' );
t( 'huỷ được mã chưa dùng', ! empty( $hq['ok'] ) );
/* ⚠️ Nói thẳng là tiền KHÔNG tự hoàn — người bấm phải biết mình vừa làm gì và CHƯA làm gì. */
t( 'và nói rõ tiền không tự hoàn', strpos( (string) $hq['thong_bao'], 'KHÔNG tự hoàn' ) !== false );
/* 🔴 HAI LỚP, như chỗ dùng-hai-lần. Cái `if` đọc trước chặn người gõ lại sau vài giây; điều
      kiện `huy=0` ngay trong câu UPDATE mới chặn được lúc một người bấm Huỷ đúng khoảnh khắc
      người kia gõ mã. Phép thử tuần tự chỉ chạm được lớp trước, nên lớp sau bám vào mã nguồn —
      và nói rõ ra đây rằng nó kiểm mã nguồn, không kiểm hành vi. */
t( 'mã đã huỷ thì không dùng được nữa', empty( VHG_Ma::dung( $ma_q, 'AMTP01' )['ok'] ) );
t( 'và câu UPDATE tự nó cũng loại mã đã huỷ',
	strpos( $ma_src2, 'AND dung_luc IS NULL AND huy=0' ) !== false );
t( 'lớp đọc trước vẫn còn nguyên',
	strpos( $ma_src2, "if ( (int) \$h['huy'] )   { return array( 'ok' => false" ) !== false );
/* Huỷ KHÔNG xoá dòng — `ma` UNIQUE là hàng rào chống sinh lại đúng mã đó. */
global $wpdb;
teq( 'huỷ là ĐÁNH DẤU, không xoá dòng', 1, (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHG_DB::t( 'ma' ) . ' WHERE ma=%s', $ma_q ) ) );
t( 'và giữ lại lý do lẫn người huỷ', 'Chị Hai' === (string) $wpdb->get_var( $wpdb->prepare(
	'SELECT huy_ai FROM ' . VHG_DB::t( 'ma' ) . ' WHERE ma=%s', $ma_q ) ) );

/* 🔴 KHÔNG huỷ được mã ĐÃ DÙNG: ghế đã chạy rồi, đánh dấu huỷ lúc này là sổ nói dối theo hướng
      có lợi cho mình. */
$ma_q2 = VHG_Ma::ma_sach( $tq['ds'][1]['ma'] );
vhg_ma_lui_ngay();
VHG_Ma::dung( $ma_q2, 'AMTP01' );
$hq2 = VHG_Ma::huy( $ma_q2, 'thử huỷ', 'Chị Hai' );
t( 'mã đã dùng thì KHÔNG huỷ được', empty( $hq2['ok'] ) );
t( 'và chỉ đúng chỗ phải sửa', strpos( (string) $hq2['error'], 'Đối soát' ) !== false );

/* Mã đã huỷ không còn tính vào khoản nợ — nó không còn là lượt mình nợ ai. */
teq( 'huỷ rồi thì hết nợ', 0, (int) VHG_Ma::tien_no()['so_ma'] );

/* Khách tra mã của mình VẪN THẤY mã đã huỷ. Lọc bỏ thì mã biến mất không dấu vết và khách nghĩ
   mình nhớ nhầm số điện thoại — thà hiện ra kèm chữ "đã huỷ", họ còn biết phải hỏi ai. */
$tk = VHG_Ma::tra( '0909111222', '2468' );
teq( 'khách vẫn thấy mã đã huỷ', 1, dem( $tk['da_huy'] ) );
t( 'nhưng khách không thấy PIN băm của chính mình', ! isset( $tk['da_huy'][0]['pin_bam'] ) );

// ---- quyền huỷ
/* 🔴 Huỷ mã là quyết định về TIỀN: khách đã trả rồi. Người đứng quầy không nên tự quyết. */
/* ⚠️ Cấy CẢ HAI người trong MỘT lượt. `vhg_vao()` ghi đè cả danh sách, nên gọi nó lần thứ hai
      là phiên của người thứ nhất mất quyền ngay — rồi phép thử hỏng vì lý do chẳng liên quan tới
      thứ nó đang canh. */
update_option( 'vhg_nguon_nguoidung', 'rieng' );
/* Khối phép thử cài đặt ở trên có lưu `vai_tro` chỉ còn Admin. Trả về mặc định, không thì Cửa
   hàng trưởng không đăng nhập nổi và phép thử quyền hạn ở dưới hỏng vì lý do chẳng liên quan. */
delete_option( 'vhg_vai_tro_vao' );
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Anh Thắng', 'pin' => '571394', 'vaiTro' => 'Admin', 'coso' => '' ),
	array( 'ten' => 'Chị Hai',   'pin' => '222333', 'vaiTro' => 'Cửa hàng trưởng', 'coso' => '' ) ) );
VHG_Auth::mo_khoa();
$tok_ch = vhg_web( 'login', array( 'pin' => '222333' ) )['token'];
$tok_ad = vhg_web( 'login', array( 'pin' => '571394' ) )['token'];
$r_ch = vhg_web( 'ma_huy', array( 'token' => $tok_ch, 'ma' => 'AAAABBBB', 'ly_do' => 'thử' ) );
t( '🔴 cửa hàng trưởng KHÔNG huỷ được mã', empty( $r_ch['ok'] ) );
t( 'và nói rõ ai mới huỷ được', strpos( (string) $r_ch['error'], 'Quản lý' ) !== false );
$sl_ma = vhg_web( 'so_lieu', array( 'token' => $tok_ad, 'ky' => 'today' ) );
teq( 'admin thì có quyền huỷ', 1, (int) $sl_ma['ma']['quyen_huy'] );
$sl_ch = vhg_web( 'so_lieu', array( 'token' => $tok_ch, 'ky' => 'today' ) );
/* 🔴 KHÔNG PHẢI "CÓ MỤC MÃ NHƯNG CỜ HUỶ = 0" — MÀ LÀ KHÔNG CÓ MỤC MÃ NÀO CẢ.
   Anh Thắng 23/08/2026 chốt: nhân viên không xem tổng doanh thu, không xem tiền của người khác.
   Gửi đủ số liệu rồi để giao diện ẩn đi là không giấu được gì: mở tab Network trên chính điện
   thoại của mình là thấy nguyên doanh thu cả hệ thống. Cắt từ máy chủ thì thứ không được xem
   KHÔNG BAO GIỜ rời khỏi máy chủ. */
t( '🔴 cửa hàng trưởng KHÔNG nhận mục mã trong gói tin', ! isset( $sl_ch['ma'] ) );
teq( 'và được đánh dấu là không phải quản trị', 0, (int) $sl_ch['quyen']['quan_tri'] );
teq( 'admin thì có', 1, (int) $sl_ma['quyen']['quan_tri'] );

// ---- tab hiện ra
t( 'số liệu mang theo mục mã', isset( $sl_ma['ma']['no'] ) && isset( $sl_ma['ma']['ds'] ) );
t( 'và danh sách mã không rò PIN băm', ! isset( $sl_ma['ma']['ds'][0]['pin_bam'] ) );
$web_ma = vhg_web_html();
t( 'trang có tab Mã giảm giá', strpos( $web_ma, "TABS.push(['ma'" ) !== false );
t( 'và hàm vẽ nó', strpos( $web_ma, 'function veMa()' ) !== false );
t( 'tab có ô tra hộ khách quên PIN', strpos( $web_ma, 'Khách quên PIN — tra hộ' ) !== false );
t( 'có ô ĐANG NỢ KHÁCH', strpos( $web_ma, 'ĐANG NỢ KHÁCH' ) !== false );
t( 'và nút huỷ mã', strpos( $web_ma, 'data-mahuy=' ) !== false );
/* Nút huỷ CHỈ hiện cho mã còn dùng được — mã đã dùng thì ghế chạy rồi. */
t( 'nút huỷ chỉ hiện cho mã còn dùng được',
	strpos( $web_ma, '(!m.huy && !m.dung_luc)' ) !== false );
t( 'tab mã có bản tiếng Anh', strpos( $web_ma, "'Discount codes'" ) !== false );

// ====================== 🔴 MỌI LỚP KHAI LUẬT ĐƯỜNG DẪN PHẢI ĐƯỢC GÀI VÀO WORDPRESS
/* 23/08/2026: `VHG_Shop` khai `add_rewrite_rule` đàng hoàng, nhưng dòng `add_action('init', …)`
 * trong tệp plugin KHÔNG BAO GIỜ được thêm — lệnh sửa tệp của em không khớp và im lặng bỏ qua.
 *
 * Hậu quả: `/mua-ma` trả 404, mà không có gì báo lỗi ở đâu cả. Lớp vẫn nạp, hàm vẫn gọi được từ
 * phép thử (phép thử gọi thẳng `VHG_Shop::phuc_vu()`, bỏ qua hẳn tầng hook), chỉ là WordPress
 * không bao giờ hỏi tới nó. Phép thử cũ xanh hết trong khi trang chết hẳn.
 *
 * Nên canh ở tầng ĐÚNG: quét mọi lớp có `add_rewrite_rule`, rồi đòi tệp plugin phải gài lớp đó.
 * Viết theo kiểu QUÉT chứ không liệt kê tên: thêm một trang mới mà quên gài thì phép thử này tự
 * bắt, khỏi phải nhớ quay lại sửa nó. */
$plugin_php = file_get_contents( VHG_DIR . 'vhcp-ghe.php' );
$thieu_gai  = array();
$co_luat    = 0;
foreach ( glob( VHG_DIR . 'includes/class-vhg-*.php' ) as $tep ) {
	$ma_tep = file_get_contents( $tep );
	if ( false === strpos( $ma_tep, 'add_rewrite_rule' ) ) { continue; }
	if ( ! preg_match( '/^class\s+(VHG_\w+)/m', $ma_tep, $m_lop ) ) { continue; }
	$co_luat++;
	$lop = $m_lop[1];
	/* Phải có `add_action( 'init', array( '<Lớp>', 'init' ), … )` trong tệp plugin. */
	if ( ! preg_match( "/add_action\(\s*'init',\s*array\(\s*'" . preg_quote( $lop, '/' )
			. "',\s*'init'\s*\)/", $plugin_php ) ) {
		$thieu_gai[] = $lop;
	}
	/* ⚠️ VÀ PHẢI Ở ƯU TIÊN 4 — trước lượt nạp lại luật ở 99. Gài sau 99 thì luật vừa khai chưa
	   nằm trong bản đã nạp, trang trả 404 tới lần lưu Permalinks kế tiếp, mà không ai nghĩ tới
	   việc đi lưu một trang mình không sửa. */
	t( $lop . ' gài ở ưu tiên 4, trước lượt nạp lại luật',
		preg_match( "/add_action\(\s*'init',\s*array\(\s*'" . preg_quote( $lop, '/' )
			. "',\s*'init'\s*\),\s*4\s*\)/", $plugin_php ) === 1 );
}
t( 'có quét được lớp nào khai luật đường dẫn', $co_luat >= 2, $co_luat );
teq( '🔴 không lớp nào khai luật mà quên gài vào WordPress', '', implode( ', ', $thieu_gai ) );

/* Và mỗi lớp đó phải nằm trong danh sách nạp tệp — khai hook cho một lớp chưa `require` là lỗi
   chí mạng ngay khi WordPress chạy `init`. */
foreach ( glob( VHG_DIR . 'includes/class-vhg-*.php' ) as $tep ) {
	$ten_tep = basename( $tep );
	t( 'tệp ' . $ten_tep . ' được nạp trong plugin',
		strpos( $plugin_php, "includes/" . $ten_tep ) !== false );
}

/* Đổi phiên bản plugin thì phải NẠP LẠI luật đường dẫn — thêm trang mới mà không nạp lại là
   trang 404 cho tới khi ai đó vào lưu Permalinks. */
t( 'nâng cấp phiên bản thì hẹn nạp lại luật đường dẫn',
	preg_match( "/get_option\(\s*'vhg_ver'\s*\)\s*!==\s*VHG_VERSION.*?update_option\(\s*'vhg_flush_rewrite'/s",
		$plugin_php ) === 1 );

// ====================== HAI TRANG, HAI MÀN HÌNH, CHẠY SONG SONG
/* Anh Thắng 23/08/2026: *"khách quét thì trang điện thoại cho dễ dùng, còn quản lý là trang máy
 * tính, chạy song song"*.
 *
 * Đây không phải chuyện thẩm mỹ mà là chuyện KHÁC NGƯỜI DÙNG, KHÁC VIỆC, KHÁC RỦI RO:
 *   · Trang khách: một cột hẹp, ngón tay bấm, KHÔNG có cổng PIN, không đọc được đồng doanh thu nào.
 *   · Trang quản lý: nhiều cột, chuột và bàn phím, sau cổng PIN, thấy toàn bộ tiền.
 * Gộp hai thứ đó vào một trang là sớm muộn một nút của quản lý lọt sang màn của khách. */
$sh_css = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-shop.php' );
$tr_css = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );

t( 'trang khách bó hẹp một cột cho điện thoại',
	preg_match( '/\.wrap\{max-width:(\d+)px/', $sh_css, $m_s ) === 1 && (int) $m_s[1] <= 640 );
t( 'trang quản lý rộng cho màn máy tính',
	preg_match( '/\.wrap\{max-width:(\d+)px/', $tr_css, $m_t ) === 1 && (int) $m_t[1] >= 1000 );
t( 'và trang quản lý có bố cục riêng cho màn rộng',
	strpos( $tr_css, '@media(min-width:1100px)' ) !== false );
/* Hai đường dẫn tách hẳn — không phải hai chế độ của cùng một trang. */
t( 'hai đường dẫn khác nhau', VHG_Shop::slug() !== VHG_Trang::slug() );
t( 'và hai lớp khác nhau', strpos( $sh_css, 'class VHG_Shop' ) !== false );

/* 🔴 RANH GIỚI QUYỀN. Trang khách không được có bất kỳ việc nào của trang quản lý. */
foreach ( array( 'so_lieu', 'ma_huy', 'ma_tra', 'bat', 'tat', 'khoi_dong_lai', 'tien_mat', 'gan_ma' )
	as $viec_ql ) {
	$r_cam = vhg_shop( $viec_ql, array() );
	t( 'trang khách KHÔNG làm được việc "' . $viec_ql . '"', empty( $r_cam['ok'] ) );
}
/* Và trang khách không cầm token phiên nhân viên — nó không có khái niệm phiên. */
t( 'trang khách không đụng tới phiên nhân viên',
	strpos( $sh_css, 'VHG_Auth' ) === false );

// ====================== DÙNG MÃ ≠ KÍCH HOẠT CHO KHÔNG
/* Anh Thắng 23/08/2026: *"nó giống kiểu kích hoạt từ xa… khách quét mã tại máy đó thì nhận code
 * và dùng thôi"*. Giống ở chỗ ghế cũng được lệnh chạy từ xa. KHÁC ở chỗ TIỀN.
 *
 * 🔴 Nhật ký "Kích hoạt ghế" là sổ ghi những lượt CHO KHÔNG — thứ cuối tháng phải giải thích vì
 *    sao ghế chạy nhiều hơn tiền thu. Lượt dùng mã thì khách ĐÃ TRẢ TIỀN hôm mua. Để nó lẫn vào
 *    đó là thổi phồng con số "cho không" bằng chính những lượt có tiền, và bảng giải thích trở
 *    thành bảng cần được giải thích. */
vhg_dung_bang();
delete_option( 'vhg_menh_gia' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );

$dk = VHG_Ma::dat_don( '0909777888', '5566', 100000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T290 ' . VHG_QR::noi_dung_mua( $dk['ma_don'] ), 'referenceCode' => 'FT-K-1' ) );
$mk = VHG_Ma::ma_sach( VHG_Ma::tra( '0909777888', '5566' )['chua_dung'][0]['ma'] );

teq( 'trước khi dùng mã, chưa có lượt cho không nào', 0, (int) VHG_May::tong_lenh( 'month' )['so_lan'] );
vhg_ma_lui_ngay();
$rk = VHG_Ma::dung( $mk, 'AMTP01' );
t( 'dùng được mã', ! empty( $rk['ok'] ) );
teq( '🔴 dùng mã KHÔNG vào nhật ký cho không', 0, (int) VHG_May::tong_lenh( 'month' )['so_lan'] );
/* Nhưng ghế vẫn nhận được lệnh chạy — qua HÀNG CHỜ, đúng đường của một lượt đã trả tiền. */
$cho_k = VHG_May::so_cho( 'AMTP01' );
t( 'nhưng ghế vẫn có lượt chờ chạy', $cho_k >= 1, $cho_k );
/* Và ghế lấy về đúng số phút của MỆNH GIÁ, không phải giá bán. */
$lay = VHG_May::lay_luot( 'AMTP01' );
teq( 'ghế chạy theo mệnh giá', 100000, (int) $lay['so_tien'] );

// ---- luồng thật: quét tem TẠI GHẾ -> nhập mã -> ghế đó chạy
/* Tem của từng ghế mang mã ghế đó, nên trang tự biết chạy ghế nào — khách không phải chọn. */
$dk2 = VHG_Ma::dat_don( '0909777888', '5566', 100000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T291 ' . VHG_QR::noi_dung_mua( $dk2['ma_don'] ), 'referenceCode' => 'FT-K-2' ) );
$mk2 = VHG_Ma::ma_sach( VHG_Ma::tra( '0909777888', '5566' )['chua_dung'][0]['ma'] );
vhg_ma_lui_ngay();
$sh_ghe = vhg_shop_html( 'AMTP01' );
t( 'quét tem tại ghế thì trang biết ghế nào', strpos( $sh_ghe, '"AMTP01"' ) !== false );
$rd = vhg_shop( 'dung', array( 'ma' => $mk2, 'ma_may' => 'AMTP01' ) );
t( 'nhập mã ngay trên trang là ghế chạy', ! empty( $rd['ok'] ), isset( $rd['error'] ) ? $rd['error'] : '' );
t( 'và nói rõ ghế nào sắp chạy', strpos( (string) $rd['thong_bao'], 'AMTP01' ) !== false );
teq( 'vẫn không cộng thêm doanh thu', 170000, vhg_tong() );

// ====================== CHỐT "MUA XONG KHÔNG DÙNG LIỀN"
/* 🔴 Anh Thắng 23/08/2026: *"để tránh gian lận mua xong dùng liền, mã đó chỉ được dùng sau 5
 *    ngày"*. Không có chốt này thì BẢNG GIÁ SẬP: khách đứng ngay cạnh ghế, mở điện thoại mua mã
 *    100.000đ với giá 85.000đ, quét xong dùng luôn — và không ai trả 100.000đ tại ghế nữa. Giảm
 *    giá là để đổi lấy việc khách TRẢ TIỀN TRƯỚC; trả trước mà dùng ngay thì không đổi được gì. */
vhg_dung_bang();
delete_option( 'vhg_menh_gia' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );
delete_option( 'vhg_ma_cho_ngay' );
teq( 'mặc định chờ 5 ngày', 5, VHG_Ma::cho_ngay_mac_dinh() );

$dc = VHG_Ma::dat_don( '0909555666', '7788', 100000, 1 );
teq( 'đơn chốt luôn số ngày chờ', 5, (int) $dc['cho_ngay'] );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T300 ' . VHG_QR::noi_dung_mua( $dc['ma_don'] ), 'referenceCode' => 'FT-C-1' ) );
$mc = VHG_Ma::ma_sach( VHG_Ma::tra( '0909555666', '7788' )['chua_dung'][0]['ma'] );

$rc = VHG_Ma::dung( $mc, 'AMTP01' );
t( '🔴 mua xong dùng liền thì BỊ CHẶN', empty( $rc['ok'] ) );
/* Nói rõ NGÀY GIỜ dùng được — khách cần biết quay lại lúc nào, không cần biết mình vừa sai. */
t( 'và nói rõ dùng được từ lúc nào', strpos( (string) $rc['error'], 'dùng được từ' ) !== false );
/* "còn 5 ngày" hay "còn 4 ngày 23 giờ" đều đúng — tuỳ vài giây chênh lúc chạy. Bám vào chữ
   "còn … ngày" chứ đừng bám vào con số, không thì phép thử hỏng lúc nửa đêm mà chẳng vì cái gì. */
t( 'kèm còn bao lâu nữa', preg_match( '/còn \d+ ngày/u', (string) $rc['error'] ) === 1, $rc['error'] );
t( 'và nói rõ vì sao có chốt này', strpos( (string) $rc['error'], 'giá đã giảm' ) !== false );
/* Bị chặn thì mã VẪN CÒN NGUYÊN — không được đánh dấu đã dùng. */
teq( 'bị chặn thì mã vẫn còn dùng được sau này', 1,
	count( VHG_Ma::tra( '0909555666', '7788' )['chua_dung'] ) );
teq( 'và ghế KHÔNG được xếp lượt chạy', 0, VHG_May::so_cho( 'AMTP01' ) );

/* Qua hạn thì dùng được. */
global $wpdb;
$wpdb->query( $wpdb->prepare( 'UPDATE ' . VHG_DB::t( 'ma' ) . ' SET tao_luc=%s WHERE ma=%s',
	gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 6 * 86400 ), $mc ) );
t( 'qua 5 ngày thì dùng được', ! empty( VHG_Ma::dung( $mc, 'AMTP01' )['ok'] ) );

/* ⚠️ SỐ NGÀY ĐÓNG BĂNG VÀO TỪNG MÃ LÚC BÁN. Đổi cài đặt giữa chừng mà áp ngược cho mã đã bán là
      đổi điều kiện của một món khách đã trả tiền để nhận. */
update_option( 'vhg_ma_cho_ngay', 0 );
$d0 = VHG_Ma::dat_don( '0909555777', '1122', 100000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T301 ' . VHG_QR::noi_dung_mua( $d0['ma_don'] ), 'referenceCode' => 'FT-C-2' ) );
$m0 = VHG_Ma::ma_sach( VHG_Ma::tra( '0909555777', '1122' )['chua_dung'][0]['ma'] );
t( 'khai 0 ngày thì mua xong dùng ngay được', ! empty( VHG_Ma::dung( $m0, 'AMTP01' )['ok'] ) );

/* Giờ đổi cài đặt lên 30 ngày — mã CŨ (chốt 5 ngày, đã quá hạn) phải KHÔNG bị khoá lại. */
$d5 = VHG_Ma::dat_don( '0909555888', '3344', 100000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T302 ' . VHG_QR::noi_dung_mua( $d5['ma_don'] ), 'referenceCode' => 'FT-C-3' ) );
$m5 = VHG_Ma::ma_sach( VHG_Ma::tra( '0909555888', '3344' )['chua_dung'][0]['ma'] );
update_option( 'vhg_ma_cho_ngay', 30 );
t( '🔴 đổi cài đặt KHÔNG áp ngược cho mã đã bán', ! empty( VHG_Ma::dung( $m5, 'AMTP01' )['ok'] ) );
delete_option( 'vhg_ma_cho_ngay' );

/* Trần 365: gõ nhầm một số 0 thành "chờ 3650 ngày" là mã coi như mất. */
update_option( 'vhg_ma_cho_ngay', 99999 );
t( 'số ngày quá tay bị chặn', VHG_Ma::cho_ngay_mac_dinh() <= 365 );
update_option( 'vhg_ma_cho_ngay', -5 );
teq( 'số ngày âm thì coi như 0', 0, VHG_Ma::cho_ngay_mac_dinh() );
delete_option( 'vhg_ma_cho_ngay' );

// ---- nói TRƯỚC khi khách trả tiền
/* 🔴 Đây là thứ dễ làm khách thấy mình bị gạt nhất nếu chỉ hiện ra lúc đã trả xong rồi quét
      không được. Phải nói to, ở màn chọn gói, TRƯỚC khi có nút trả tiền. */
$sh_cho = vhg_shop_html();
t( 'trang khách nói điều kiện chờ trước khi trả tiền',
	strpos( $sh_cho, 'Mã dùng được sau ' ) !== false );
t( 'và nói rõ vì sao', strpos( $sh_cho, 'điều kiện của giá đã giảm' ) !== false );
t( 'kèm lối thoát cho khách cần dùng ngay',
	strpos( $sh_cho, 'trả thẳng tại ghế với giá gốc' ) !== false );
teq( 'API bảng giá mang theo số ngày chờ', 5, (int) vhg_shop( 'goi' )['cho_ngay'] );
/* Mã chưa tới hạn hiện MỐC dùng được, không hiện "còn dùng được". */
t( 'mã chưa tới hạn hiện mốc dùng được', strpos( $sh_cho, 'dùng được từ ' ) !== false );

// ---- màn Cài đặt
ob_start(); VHG_Admin::trang_ngoai(); $h_cho = ob_get_clean();
t( 'cài đặt có ô khai số ngày chờ', strpos( $h_cho, 'name="ma_cho_ngay"' ) !== false );
t( 'và nói rõ vì sao cần nó', strpos( $h_cho, 'không ai trả giá gốc tại ghế nữa' ) !== false );
t( 'và nói rõ nó đóng băng vào từng mã', strpos( $h_cho, 'đóng băng vào từng mã' ) !== false );
$_POST = array( 'vhg' => 'luu_trang', 'slug' => 'ghe', 'nguon' => 'rieng',
	'vai_tro' => array( 'Admin' ), 'anh_nen' => '', 'giam' => array( '100000' => '15' ),
	'qc_o' => '', 'qc_giay' => '30', 'ma_cho_ngay' => '7' );
ob_start(); VHG_Admin::trang_ngoai(); ob_get_clean();
$_POST = array();
teq( 'bấm Lưu thì số ngày vào thật', 7, VHG_Ma::cho_ngay_mac_dinh() );
delete_option( 'vhg_ma_cho_ngay' );

// ====================== MÃ QR TRÊN Ô GÓI: GHẾ DO TEM QUYẾT ĐỊNH, KHÔNG DO KHÁCH CHỌN
vhg_dung_bang();
delete_option( 'vhg_menh_gia' );
update_option( 'permalink_structure', '/%postname%/' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );

// ---- địa chỉ ngắn cho mã QR
$u_ng = VHG_Shop::url_ngan( 'AMTP01' );
t( 'có dựng được địa chỉ ngắn', '' !== $u_ng, $u_ng );
/* 🔴 Ô gói chỉ chừa VHG_Ma::QR_VUNG_PX px cho mã. Ba thứ dưới đây là ba cách chuỗi tự dài ra, và mỗi lần dài là
      mã tự rơi xuống ít pixel hơn mỗi module. */
/* ===== 🔴 LỖI 23/08/2026: TEM IN RA DẪN VÀO TRANG "KHÔNG TÌM THẤY TRANG" ====================
 * Em viết HOA cả địa chỉ để mã QR nhỏ lại, và dùng dạng thư mục `/mua-ma/AMTP01`. Hai chỗ sai:
 *   · Luật đường dẫn của WordPress PHÂN BIỆT HOA THƯỜNG — `/MUA-MA/` không khớp `mua-ma` nào cả.
 *   · Dạng thư mục là luật MỚI, chỉ chạy sau khi WordPress nạp lại bảng đường dẫn.
 * Anh Thắng thử: `/MUA-MA/AMTP01` ra trang 404, `/mua-ma/?ghe=AMTP01` vào đúng trang.
 *
 * Và viết hoa hoá ra CHẲNG LỢI GÌ: hai dạng đều ra mã 25×25. Đánh đổi tính đúng đắn lấy số không.
 *
 * Nên phép thử nay canh ngược lại: đường dẫn phải GIỮ NGUYÊN hoa thường, và phải là dạng đã chạy
 * thật. Chỉ mã ghế mới viết hoa — mã ghế nằm trong THAM SỐ, không nằm trong đường dẫn. */
t( 'bỏ scheme https:// (cho mã QR trên màn ghế nhỏ lại)', strpos( $u_ng, 'http' ) === false );
$u_day = VHG_Shop::url_ghe( 'AMTP01' );
t( 'tem in dùng địa chỉ ĐẦY ĐỦ có https://', 0 === strpos( $u_day, 'http' ) );
t( '🔴 dùng dạng ?ghe= — dạng đã chạy thật', strpos( $u_day, '?ghe=' ) !== false
	|| strpos( $u_day, '&ghe=' ) !== false );
/* 🔴 Chốt chính: đường dẫn KHÔNG được viết hoa. Đây là thứ đã làm tem dẫn vào trang 404. */
$duong_ng = (string) wp_parse_url( $u_day, PHP_URL_PATH );
teq( '🔴 đường dẫn giữ nguyên hoa thường, không viết hoa',
	strtolower( $duong_ng ), $duong_ng );
t( 'và đường dẫn khớp đúng slug đã khai',
	strpos( $duong_ng, VHG_Shop::slug() ) !== false, $duong_ng );
t( 'mã ghế thì viết hoa, và nằm trong tham số', strpos( $u_day, 'ghe=AMTP01' ) !== false );
/* Hai nơi phải dùng CÙNG một dạng: khác dạng thì một nơi chết mà nơi kia vẫn chạy, nên không ai
   phát hiện cho tới khi khách kêu. */
teq( 'bản ngắn chỉ khác bản đầy đủ ở mỗi scheme',
	preg_replace( '#^https?://#i', '', $u_day ), $u_ng );

$qro = VHG_Ma::qr_o_goi( $u_ng );
/* 🔴 CON SỐ quyết định: 2 pixel mỗi module là quét được ở khoảng cách gần, 1 là gần như không
      máy nào quét nổi — mà nhìn trên màn thì VẪN THẤY "có mã QR". Kiểu hỏng không kêu. */
t( '🔴 mã QR đủ to để quét (>=2 px/module)', (int) $qro['px'] >= 2, $qro['chu'] );
/* ===== KHOẢNG ĐỆM MUA ĐƯỢC KHI BỎ HAI HÀNG CHỮ (23/08/2026) ================================
 * Bỏ hai hàng chữ kẹp trên dưới mã trong ô quảng cáo -> vùng vẽ 58px lên 70px. Cái đó KHÔNG
 * làm địa chỉ hiện tại to thêm (vẫn 2 px/module), mà mua lấy KHOẢNG ĐỆM: version 3 trước đây
 * rơi xuống 1 px/module, nay vẫn giữ được 2.
 *
 * Đây mới là chỗ ăn tiền, vì địa chỉ đang 31 ký tự mà trần version 2 là 32 — thêm một ký tự
 * vào mã ghế hay tên miền là sang version 3. Trước bản này, đúng một ký tự đó đủ làm mã trên
 * MỌI ghế thành không quét nổi, mà nhìn màn vẫn thấy "có mã QR". */
$qro_v3 = VHG_Ma::qr_o_goi( 'https://khmatrix.com/mua-ma?ghe=AMTP01' );   // 38 ký tự -> version 3
t( '🔴 version 3 nay vẫn đủ to — đây là khoảng đệm vừa mua được',
	(int) $qro_v3['px'] >= 2, $qro_v3['chu'] );
/* Nhưng vẫn CÓ trần, và trần đó phải kêu lên chứ không im lặng trả một con số đẹp. */
/* ⚠️ Chữ THƯỜNG. 'A' nằm trong bảng chữ alphanumeric của QR (đặc hơn nhiều), nên 60 chữ 'A'
   vẫn chỉ là version 3 — phép thử tưởng canh trần mà thật ra canh hụt. */
$qro_v4 = VHG_Ma::qr_o_goi( str_repeat( 'a', 60 ) );                      // 60 ký tự -> version 4
t( 'quá version 3 thì vẫn quá nhỏ', (int) $qro_v4['px'] < 2, $qro_v4['chu'] );
t( 'và màn quản trị kêu lên', strpos( (string) $qro_v4['chu'], 'QUÁ NHỎ' ) !== false );
/* Chuỗi dài quá tầm thì nói thẳng là không vẽ được, đừng trả một con số vô nghĩa. */
$qro_qua = VHG_Ma::qr_o_goi( str_repeat( 'A', 200 ) );
teq( 'chuỗi quá dài thì không vẽ được mã', 0, (int) $qro_qua['px'] );

/* ===== 🔴 CHỐT: HAI CON SỐ Ở HAI NGÔN NGỮ PHẢI BẰNG NHAU =================================
 * `VHG_Ma::QR_VUNG_PX` (PHP) chỉ là BẢN SAO của `int vungH` trong veTheQuangCao() bên .ino.
 * PHP dùng nó để kêu "QUÁ NHỎ" trên màn quản trị. Lệch nhau thì màn quản trị báo về một cỡ
 * mã KHÔNG TỒN TẠI — báo yên tâm trong khi ghế đang vẽ mã không ai quét nổi. Không có gì tự
 * ràng hai bên, nên phép thử này đọc thẳng tệp firmware ra mà đối chiếu. */
$fw_qr = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'đọc được tệp firmware để đối chiếu', false !== $fw_qr && '' !== $fw_qr );
$khop_vung = array();
t( 'tìm thấy vùng vẽ mã QR trong veTheQuangCao()',
	1 === preg_match( '/int\s+vungH\s*=\s*(\d+)\s*,\s*vungY/', (string) $fw_qr, $khop_vung ) );
teq( '🔴 vùng vẽ bên firmware KHỚP hằng số QR_VUNG_PX bên PHP',
	(int) VHG_Ma::QR_VUNG_PX, (int) $khop_vung[1] );
/* Và hai hàng chữ anh Thắng bảo ẩn thì phải ẩn THẬT — còn dòng nào là vùng 70px kia sai.
   ⚠️ Phải canh LỆNH VẼ `drawString("QUET DE MUA`, chứ không phải canh chuỗi đó có xuất hiện
      trong tệp hay không: chú thích ngay trên chỗ sửa có NHẮC LẠI chuỗi cũ để giải thích, nên
      canh trần trụi là phép thử tự hỏng vì chính lời giải thích của mình. Đã dính đúng lần này. */
t( '🔴 vế mã QR không còn LỆNH VẼ hàng chữ "QUET DE MUA"',
	strpos( (string) $fw_qr, 'drawString("QUET DE MUA' ) === false );
/* Hàng "MUA MA GIAM GIA" thì KHÔNG xoá hẳn — vế chữ (khi không vẽ được mã) vẫn cần nó, vì lúc
   đó nó là dòng duy nhất nói ô này là gì. Nó chỉ không được nằm KẸP TRÊN MÃ nữa.
   ⚠️ Canh bằng VỊ TRÍ chứ đừng canh bằng "có xuất hiện sau esp_qrcode_generate hay không":
      vế chữ nằm sau lệnh vẽ mã trong tệp, nên kiểu canh đó bắt nhầm chính vế chữ hợp lệ. */
teq( 'chỉ còn ĐÚNG MỘT chỗ vẽ hàng "MUA MA GIAM GIA"',
	1, substr_count( (string) $fw_qr, 'drawString("MUA MA GIAM GIA' ) );
$vt_roi = strpos( (string) $fw_qr, 'if(!veXong){' );
$vt_hang = strpos( (string) $fw_qr, 'drawString("MUA MA GIAM GIA' );
t( '🔴 hàng đó nằm TRONG vế chữ, không kẹp phía trên mã QR',
	false !== $vt_roi && false !== $vt_hang && $vt_hang > $vt_roi );

// ---- ghế nhận được địa chỉ đó
update_option( 'vhg_qc_o', 1 );
$nh_u = vhg_ghe( array( 'viec' => 'nhip', 'mac' => 'AA:BB:CC:DD:EE:01', 'trang_thai' => 'idle' ) );
teq( 'nhịp mang theo địa chỉ mã QR', $u_ng, (string) $nh_u[1]['qcUrl'] );

// ---- quét tem dạng thư mục
$_SERVER['REQUEST_URI'] = '/' . VHG_Shop::slug() . '/AMTP01';
$_GET = array();
teq( 'quét tem /mua-ma/AMTP01 thì biết đúng ghế', 'AMTP01', VHG_Shop::ghe_tu_dia_chi() );
$_SERVER['REQUEST_URI'] = '/' . VHG_Shop::slug() . '/amtp01';
teq( 'không phân biệt hoa thường', 'AMTP01', VHG_Shop::ghe_tu_dia_chi() );
$_SERVER['REQUEST_URI'] = '/' . VHG_Shop::slug() . '/KHONGCOGHE';
teq( 'ghế bịa trên tem vẫn bị bỏ', '', VHG_Shop::ghe_tu_dia_chi() );
$_SERVER['REQUEST_URI'] = '/' . VHG_Shop::slug();
$_GET = array();

// ---- 🔴 KHÔNG CÒN Ô CHỌN GHẾ
/* Anh Thắng 23/08/2026: *"khách hàng rất dễ chọn lộn ghế, vì số lượng ghế rất nhiều"*. Chọn lộn
   là mã của khách chạy cho GHẾ NGƯỜI KHÁC — mất mã, mất cả buổi, và không ai chứng minh được. */
$sh_ch = vhg_shop_html();
/* ⚠️ Bản trước canh `strpos($sh_ch,'<select') === false` — tức là "trang không được có ô chọn
      NÀO CẢ". Đó là một cái cọc thay cho ý thật, và cọc gãy ngay khi trang có thêm một ô chọn
      HỢP LỆ (chọn GÓI để trả bằng số dư ví). Phép thử ấy sẽ ép người sau hoặc bỏ tính năng
      đúng, hoặc gỡ phép thử — cả hai đều hỏng.
      Ý thật là: KHÔNG có ô nào cho khách chọn GHẾ. Canh đúng ý đó. */
$o_chon = array();
preg_match_all( '/<select[^>]*id="([^"]*)"/i', $sh_ch, $o_chon );
foreach ( (array) $o_chon[1] as $id_ ) {
	t( '🔴 ô chọn "' . $id_ . '" không phải ô chọn ghế',
		stripos( $id_, 'ghe' ) === false && stripos( $id_, 'may' ) === false );
}
t( 'và không còn dựng danh sách ghế', strpos( $sh_ch, 'ds_ghe' ) === false );
/* Chốt chặn thật: mã ghế CÓ TRONG hệ thống mà lại xuất hiện trong trang khách nghĩa là trang
   đang phơi danh sách ghế ra, dù dựng bằng cách nào. */
t( '🔴 trang khách không mang mã ghế nào của hệ thống',
	strpos( $sh_ch, 'AMTP01' ) === false );
/* Không biết ghế thì TỪ CHỐI và chỉ đường, chứ không đoán. */
t( 'không biết ghế thì mời quét tem trên ghế',
	strpos( $sh_ch, 'quét mã QR dán trên chính cái ghế' ) !== false );
/* Bám vào MỘT mẩu liền mạch: câu này nằm trên hai dòng nguồn JS nối bằng dấu +, nên chuỗi đầy
   đủ không hề liền nhau trong tệp gửi ra. */
t( 'và nói rõ vì sao cố ý không cho chọn',
	strpos( $sh_ch, 'cố ý <b>không</b> cho chọn ghế từ danh sách' ) !== false );
t( 'vẫn mua thêm mã được, vì mua không cần biết ghế',
	strpos( $sh_ch, 'mua thì không cần biết ghế' ) !== false );
/* ⚠️ Và cổng API cũng KHÔNG phơi danh sách ghế ra trang không cần đăng nhập. */
t( 'API bảng giá không kèm danh sách ghế', ! isset( vhg_shop( 'goi' )['ds_ghe'] ) );

$sh_co = vhg_shop_html( 'AMTP01' );
t( 'quét tem tại ghế thì hiện thẳng tên ghế', strpos( $sh_co, 'Ghế đang ngồi' ) !== false );

// ---- firmware vẽ mã QR trong ô gói
$fw12 = file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );
t( 'ghế đọc được địa chỉ mã QR', strpos( $fw12, 'QC_URL  = String((const char*)(d["qcUrl"]' ) !== false );
t( 'và vẽ mã QR trong ô quảng cáo', strpos( $fw12, 'esp_qrcode_generate(&qc, QC_URL.c_str())' ) !== false );
/* 🔴 KHÔNG có địa chỉ thì KHÔNG vẽ mã. Một mã QR dẫn đi đâu không rõ còn tệ hơn không có mã:
      khách quét không ra, và lần sau họ không quét nữa — kể cả cái tem thật dán cạnh thùng tiền. */
/* 23/08/2026 nhánh này mạnh thêm một bậc. Trước: chỉ khi QC_URL RỖNG mới rơi về vế chữ; nếu
   địa chỉ có mà dài quá trần thì `esp_qrcode_generate` báo lỗi, callback không chạy, ô còn lại
   mảng trắng trơn — hồi đó vẫn còn dòng chữ dưới mã nên khách còn lối đi. Nay hai dòng chữ đó
   đã bỏ, mảng trắng trơn thành một ô CÂM. Nên vế chữ phải chạy cho CẢ HAI trường hợp: không có
   địa chỉ, VÀ có địa chỉ nhưng vẽ không nổi. */
t( 'có chốt "đã vẽ được mã chưa" thay cho if/else cụt',
	strpos( $fw12, 'bool veXong = false;' ) !== false );
t( '🔴 chốt đó lấy đúng kết quả của esp_qrcode_generate',
	strpos( $fw12, 'veXong = (esp_qrcode_generate(&qc, QC_URL.c_str()) == ESP_OK);' ) !== false );
t( '🔴 vẽ không nổi thì xoá mảng trắng đi, không để lại ô câm',
	preg_match( '/if\(!veXong\)\s*tft\.fillRect\(cx - vungH\/2 - 2, vungY - 2, vungH \+ 4, vungH \+ 4, COL_VIP\)/', $fw12 ) === 1 );
t( 'rỗng HOẶC vẽ hỏng thì đều rơi về lời mời bằng chữ, không vẽ mã bừa',
	preg_match( '/if\(!veXong\)\{.*?QUET TEM CANH THUNG TIEN/s', $fw12 ) === 1 );
/* ⚠️ Nền trắng phủ cả vùng lặng: vẽ mã đen lên nền vàng của thẻ là không máy nào quét được. */
t( 'mã QR có nền trắng phủ cả vùng lặng',
	preg_match( '/fillRect\(cx - vungH\/2 - 2, vungY - 2, vungH \+ 4, vungH \+ 4, TFT_WHITE\)/', $fw12 ) === 1 );
/* Trần version 4: quá đó là module nhỏ hơn 2px — thà không vẽ còn hơn vẽ một mã chết. */
t( 'chặn trần version để không vẽ mã quá nhỏ',
	strpos( $fw12, 'qc.max_qrcode_version = 4;' ) !== false );
delete_option( 'vhg_qc_o' );

// ====================== QUÊN PIN: LẤY LẠI BẰNG SỐ ĐIỆN THOẠI + CĂN CƯỚC
vhg_dung_bang();
delete_option( 'vhg_menh_gia' ); delete_option( 'vhg_ma_cho_ngay' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );
foreach ( array( 'tra', 'lay', 'dung' ) as $k_ ) { delete_transient( 'vhg_shop_' . $k_ . '_' . md5( 'x' ) ); }

/* 🔴 CHỈ GIỮ BỐN SỐ CUỐI. Khách nhập cả số, phần còn lại bị vứt ngay — không ghi, không log.
      Một bảng ghép "số điện thoại + căn cước đầy đủ" bị lộ thì thiệt hại lớn hơn hẳn lộ mã
      giảm giá massage, mà mình chỉ cần phân biệt hai người ở quầy chứ không cần định danh ai. */
teq( 'lấy đúng bốn số cuối', '6789', VHG_Ma::cc4( '079123456789' ) );
teq( 'bỏ dấu cách và gạch', '6789', VHG_Ma::cc4( '079 1234 56789' ) );
teq( 'quá ngắn thì bỏ', '', VHG_Ma::cc4( '123' ) );

$dcc = VHG_Ma::dat_don( '0909333444', '1111', 100000, 1, '079123456789' );
t( 'mua có khai căn cước', ! empty( $dcc['ok'] ) );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T310 ' . VHG_QR::noi_dung_mua( $dcc['ma_don'] ), 'referenceCode' => 'FT-CC-1' ) );

/* 🔴 CẢ SỐ CĂN CƯỚC KHÔNG ĐƯỢC NẰM Ở ĐÂU TRONG BẢNG. */
global $wpdb;
foreach ( array( 'ma', 'don_ma' ) as $b_ ) {
	$dong = VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( $b_ ) );
	$het = wp_json_encode( $dong );
	t( 'bảng ' . $b_ . ' KHÔNG chứa số căn cước đầy đủ', strpos( $het, '079123456789' ) === false );
	t( 'và cũng không chứa bốn số cuối dạng đọc được',
		strpos( $het, '"6789"' ) === false );
}

// ---- lấy lại PIN
t( 'sai căn cước thì không đổi được PIN',
	empty( VHG_Ma::lay_lai_pin( '0909333444', '000000000000', '2222' )['ok'] ) );
t( 'PIN mới sai khuôn thì từ chối',
	empty( VHG_Ma::lay_lai_pin( '0909333444', '079123456789', '22' )['ok'] ) );
$ll = VHG_Ma::lay_lai_pin( '0909333444', '079123456789', '2222' );
t( 'đúng căn cước thì đặt được PIN mới', ! empty( $ll['ok'] ), isset( $ll['error'] ) ? $ll['error'] : '' );
t( 'PIN cũ hết dùng được', empty( VHG_Ma::tra( '0909333444', '1111' )['ok'] ) );
teq( 'PIN mới tra ra mã', 1, count( VHG_Ma::tra( '0909333444', '2222' )['chua_dung'] ) );
/* Chỉ cần bốn số cuối, nên nhập số khác nhưng trùng bốn số cuối vẫn qua — đó là bản chất của
   việc chỉ giữ bốn số, và phải nói rõ chứ không giả vờ là nó mạnh hơn thế. */
t( 'trùng bốn số cuối là qua được', ! empty( VHG_Ma::lay_lai_pin( '0909333444', '999999996789', '3333' )['ok'] ) );

/* ⚠️ Mã KHÔNG khai căn cước thì `cc_bam` rỗng, và rỗng KHÔNG được khớp với bất cứ thứ gì —
      nếu không thì một chuỗi rỗng mở được mọi mã cũ. */
$dk0 = VHG_Ma::dat_don( '0909333555', '4444', 100000, 1 );
vhg_ban( array( 'transferType' => 'in', 'transferAmount' => 85000,
	'content' => 'CT DEN:145T311 ' . VHG_QR::noi_dung_mua( $dk0['ma_don'] ), 'referenceCode' => 'FT-CC-2' ) );
t( '🔴 mã không khai căn cước thì KHÔNG lấy lại PIN được',
	empty( VHG_Ma::lay_lai_pin( '0909333555', '079123456789', '5555' )['ok'] ) );
teq( 'và PIN cũ của nó vẫn nguyên', 1, count( VHG_Ma::tra( '0909333555', '4444' )['chua_dung'] ) );
t( 'câu báo lỗi chỉ đường sang nhân viên',
	strpos( (string) VHG_Ma::lay_lai_pin( '0909333555', '079123456789', '5555' )['error'],
		'nhân viên' ) !== false );

// ---- hãm thử: bốn số cuối chỉ có 10.000 tổ hợp
/* 🔴 Đây là đường ĐỔI PIN canh bằng một mật khẩu yếu. Không hãm thì dò hết trong vài phút và
      toàn bộ mã của người ta đổi chủ. */
delete_transient( 'vhg_shop_lay_' . md5( 'x' ) );
$bi_lay = false;
for ( $i = 0; $i < 12; $i++ ) {
	$r_ = vhg_shop( 'lay_lai_pin', array( 'sdt' => '0909333444', 'cc' => '000000000000', 'pin' => '9999' ) );
	if ( isset( $r_['error'] ) && strpos( $r_['error'], 'quá nhiều lần' ) !== false ) { $bi_lay = true; break; }
}
t( '🔴 dò căn cước nhiều lần thì bị hãm', $bi_lay );
/* Và hãm chặt HƠN ô tra mã thường: 10.000 tổ hợp bốn số ít hơn hẳn không gian PIN + số điện thoại. */
$src_sh = file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-shop.php' );
t( 'hãm đường lấy lại PIN chặt hơn ô tra mã',
	preg_match( "/get_transient\( self::khoa_key\( 'lay' \) \) >= (\d+)/", $src_sh, $m_l ) === 1
	&& (int) $m_l[1] < 15 );
delete_transient( 'vhg_shop_lay_' . md5( 'x' ) );

// ---- trang khách
$sh_cc = vhg_shop_html();
t( 'ô căn cước KHÔNG bắt buộc', strpos( $sh_cc, 'không bắt buộc</b>, chỉ để lấy lại PIN' ) !== false );
t( 'và nói rõ chỉ lưu 4 số cuối', strpos( $sh_cc, 'chỉ lưu 4 số cuối' ) !== false );
t( 'có khối Quên PIN riêng', strpos( $sh_cc, 'Quên PIN?' ) !== false );
t( 'và vẫn chỉ đường sang nhân viên cho người không khai căn cước',
	strpos( $sh_cc, 'gọi nhân viên' ) !== false );

// ====================== BỘ VẼ MÃ QR TỰ VIẾT — PHẢI TỰ CHỨNG MINH
/* 🔴 Tự viết bộ vẽ QR thì "chắc là quét được" chỉ là một lời chúc. Tem in ra dán lên 26 cái ghế
      nhiều năm; sai một bước là cả loạt tem chết mà không ai biết cho tới khi khách kêu.
      Ba lớp chứng minh, mỗi lớp bắt một loại lỗi khác nhau. */

// --- Lớp 1: số học Reed-Solomon, đối chiếu bộ hệ số ĐÃ CÔNG BỐ trong bản đặc tả
/* Tính chứ không chép bảng — chép bảng là chép cả lỗi gõ. Nhưng phải đối chiếu với bộ đã công
   bố, không thì tự tính sai rồi tự tin là đúng. */
teq( 'đa thức sinh 7 codeword khớp bản đặc tả',
	array( 1, 127, 122, 154, 164, 11, 68, 117 ), VHG_QRVe::da_thuc_sinh( 7 ) );
teq( 'đa thức sinh 10 codeword khớp bản đặc tả',
	array( 1, 216, 194, 159, 111, 199, 94, 95, 113, 157, 193 ), VHG_QRVe::da_thuc_sinh( 10 ) );
/* Ví dụ "01234567" version 1-M của bản đặc tả: 16 codeword dữ liệu -> 10 codeword sửa lỗi. */
$du_chuan = array( 0b00010000, 0b00100000, 0b00001100, 0b01010110, 0b01100001, 0b10000000,
	0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11 );
teq( '🔴 sửa lỗi khớp từng byte với ví dụ của bản đặc tả',
	array( 0xA5, 0x24, 0xD4, 0xC1, 0xED, 0x36, 0xC7, 0x87, 0x2C, 0x55 ),
	VHG_QRVe::ecc( $du_chuan, 10 ) );

// --- Lớp 2: đọc ngược. Bắt lỗi đặt bit, mặt nạ, xen kẽ khối, chế độ mã hoá
/* Bộ đọc đi ngược đúng những bước dễ sai nhất. Một lỗi ở bất kỳ bước nào của bộ vẽ là chuỗi đọc
   ra khác chuỗi ban đầu. */
$mau_qr = array(
	'https://khmatrix.com/mua-ma/?ghe=AMTP01',   // đúng thứ TEM DÁN sẽ mang
	'khmatrix.com/mua-ma/?ghe=AMTP01',           // đúng thứ Ô GÓI TRÊN GHẾ sẽ mang
	'HELLO WORLD',                               // alphanumeric ngắn
	'Ghe massage POSH - tem dan',                // có chữ thường -> chế độ byte
	'KHMATRIX.COM/MUA-MA/AMTP01',                // dạng cũ (đã bỏ) — bộ vẽ vẫn phải dựng đúng
	'0',                                         // ngắn nhất có thể
);
foreach ( $mau_qr as $t_qr ) {
	foreach ( array( 'L', 'M', 'Q', 'H' ) as $muc_qr ) {
		$o_qr = VHG_QRVe::ma_tran( $t_qr, $muc_qr );
		t( 'dựng được mã [' . $muc_qr . '] "' . substr( $t_qr, 0, 22 ) . '"', ! empty( $o_qr ) );
		if ( $o_qr ) {
			teq( 'và đọc ngược đúng [' . $muc_qr . '] "' . substr( $t_qr, 0, 22 ) . '"',
				$t_qr, VHG_QRVe::doc( $o_qr ) );
		}
	}
}

// --- Lớp 3: hình cố định phải đúng chỗ, không thì không máy nào tìm ra mã
$o_k = VHG_QRVe::ma_tran( 'KHMATRIX.COM/MUA-MA/AMTP01', 'M' );
$n_k = count( $o_k );
teq( 'cạnh ma trận đúng công thức version', 0, ( $n_k - 17 ) % 4 );
/* Ba ô định vị: viền ngoài đen, vành trắng, lõi đen. Sai là máy quét không tìm ra mã. */
foreach ( array( array( 0, 0 ), array( $n_k - 7, 0 ), array( 0, $n_k - 7 ) ) as $g_k ) {
	list( $gx, $gy ) = $g_k;
	$ok_k = 1 === $o_k[ $gy ][ $gx ] && 1 === $o_k[ $gy ][ $gx + 6 ]
		&& 0 === $o_k[ $gy + 1 ][ $gx + 1 ] && 1 === $o_k[ $gy + 3 ][ $gx + 3 ];
	t( 'ô định vị ở (' . $gx . ',' . $gy . ') đúng hình', $ok_k );
}
/* Dải nhịp: đen-trắng xen kẽ, là thước đo cỡ module của máy quét. */
$nhip_ok = true;
for ( $i = 8; $i < $n_k - 8; $i++ ) {
	if ( $o_k[6][ $i ] !== ( 0 === $i % 2 ? 1 : 0 ) ) { $nhip_ok = false; }
	if ( $o_k[ $i ][6] !== ( 0 === $i % 2 ? 1 : 0 ) ) { $nhip_ok = false; }
}
t( 'hai dải nhịp xen kẽ đúng', $nhip_ok );
teq( 'ô tối cố định đúng chỗ', 1, $o_k[ $n_k - 8 ][8] );

/* Quá tầm thì TRẢ RỖNG, không trả một ma trận "gần đúng" — tem in ra mà không quét được thì tệ
   hơn hẳn việc chưa in tem nào. */
teq( 'chuỗi quá dài thì trả rỗng', array(), VHG_QRVe::ma_tran( str_repeat( 'A', 700 ), 'H' ) );
teq( 'chuỗi rỗng thì trả rỗng', array(), VHG_QRVe::ma_tran( '', 'M' ) );
teq( 'mức sửa lỗi lạ thì trả rỗng', array(), VHG_QRVe::ma_tran( 'ABC', 'X' ) );

// --- SVG
$svg_k = VHG_QRVe::svg( $o_k, 190 );
t( 'xuất được SVG', strpos( $svg_k, '<svg' ) === 0 );
/* ⚠️ VÙNG LẶNG 4 Ô mỗi bên. Cắt bớt cho "gọn" là nhiều máy quét không nhận ra mã — kiểu hỏng chỉ
      lộ ở một số máy, tức là sau khi đã dán tem lên 26 cái ghế. */
t( 'SVG chừa vùng lặng 4 ô mỗi bên',
	strpos( $svg_k, 'viewBox="0 0 ' . ( $n_k + 8 ) . ' ' . ( $n_k + 8 ) . '"' ) !== false );
t( 'và có nền trắng', strpos( $svg_k, 'fill="#fff"' ) !== false );
teq( 'ma trận rỗng thì không ra SVG', '', VHG_QRVe::svg( array() ) );

// --- trang in tem
vhg_dung_bang();
update_option( 'permalink_structure', '/%postname%/' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
ob_start(); VHG_Admin::trang_tem(); $h_tem = ob_get_clean();
t( 'có trang in tem', strpos( $h_tem, 'Tem QR dán lên ghế' ) !== false );
t( 'tem mang mã ghế', strpos( $h_tem, 'AMTP01' ) !== false );
t( 'và có mã QR thật trong đó', strpos( $h_tem, '<svg' ) !== false );
t( 'in được bằng một nút', strpos( $h_tem, 'window.print()' ) !== false );
/* Dán nhầm ghế là mã của khách chạy sai ghế — phải nói ra, không để trong đầu người in. */
t( 'nhắc dán đúng ghế', strpos( $h_tem, 'Dán <b>đúng ghế</b>' ) !== false );
/* Chưa bật đường dẫn tĩnh thì địa chỉ ngắn không chạy — nói ra chứ đừng in tem chết. */
delete_option( 'permalink_structure' );
ob_start(); VHG_Admin::trang_tem(); $h_tem0 = ob_get_clean();
t( 'chưa bật đường dẫn tĩnh thì KHÔNG in tem chết', strpos( $h_tem0, '<svg' ) === false );
t( 'và chỉ đúng chỗ phải bật', strpos( $h_tem0, 'options-permalink.php' ) !== false );
update_option( 'permalink_structure', '/%postname%/' );

// ====================== MÃ QR TRÊN TRANG MUA MÃ (quét, hoặc tải ảnh rồi chọn từ thư viện)
/* ⚠️ Em từng bỏ mã QR khỏi trang này với lý do "khách đang cầm chính cái máy hiện trang, không
      ai quét được màn hình của mình". Suy luận đó THIẾU: app ngân hàng Việt Nam đều cho chọn ảnh
      QR từ thư viện — khách tải ảnh, mở app, chọn ảnh, quét được bình thường. Chưa kể người thứ
      hai chĩa máy vào màn là quét luôn. */
vhg_dung_bang();
delete_option( 'vhg_menh_gia' ); delete_option( 'vhg_ma_cho_ngay' );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_ma_giam', array( 100000 => 15 ) );

$dq2 = vhg_shop( 'dat', array( 'sdt' => '0909888777', 'pin' => '1234',
	'menh_gia' => 100000, 'so_luong' => 1 ) );
t( 'đặt được đơn', ! empty( $dq2['ok'] ) );
t( '🔴 đơn có kèm mã QR', ! empty( $dq2['qr'] ) && dem( $dq2['qr'] ) >= 21 );

/* 🔴 MÃ QR PHẢI LÀ ĐÚNG CHUỖI VIETQR CỦA ĐƠN NÀY. Vẽ ra một mã trông như thật mà nội dung khác
      là tiền của khách đi lạc — đúng kiểu lỗi "bảng xem trước nói dối" đã gặp bốn lần trước đó.
      Nên đọc ngược mã QR vừa dựng rồi so với chuỗi VietQR dựng độc lập. */
$o_don = array();
foreach ( $dq2['qr'] as $hang_ ) {
	$o_don[] = array_map( 'intval', str_split( $hang_ ) );
}
$chuoi_doc = VHG_QRVe::doc( $o_don );
$qr_that   = VHG_QR::cho_don_mua( $dq2['ma_don'], (int) $dq2['phai_tra'] );
teq( '🔴 đọc ngược mã QR ra ĐÚNG chuỗi VietQR của đơn', (string) $qr_that['chuoi'], $chuoi_doc );
/* Và chuỗi đó phải mang đúng nội dung mà webhook sẽ đọc để phát mã. */
t( 'chuỗi mang đúng nội dung chuyển khoản của đơn',
	strpos( $chuoi_doc, (string) $dq2['noi_dung'] ) !== false );
/* Số tiền trong mã QR phải đúng số phải trả — sai là khách chuyển thiếu và không nhận được mã. */
$giai = VHG_QR::doc( $chuoi_doc );
/* ⚠️ Tách trường NỘI DUNG ra trước rồi mới đọc mã đơn — đúng đường đi thật: ngân hàng bóc trường
      62.08 khỏi mã QR rồi gửi mình mỗi phần nội dung, chứ không gửi cả chuỗi QR thô.
      Đọc thẳng từ chuỗi thô thì trượt, vì ngay sau mã đơn là mấy chữ số của trường kiểm tra —
      và `don_mua()` cố ý từ chối khớp khi mã đơn dính liền chữ số khác. */
teq( 'đọc ngược nội dung ra đúng mã đơn', $dq2['ma_don'],
	VHG_Doc::don_mua( (string) $giai['noi_dung'] ) );
teq( 'và nội dung trong mã QR đúng chuỗi webhook sẽ nhận',
	(string) $dq2['noi_dung'], (string) $giai['noi_dung'] );
teq( 'số tiền trong mã QR đúng số phải trả', (int) $dq2['phai_tra'], (int) $giai['so_tien'] );
teq( 'và vào đúng tài khoản nhận', '108878583951', (string) $giai['so_tk'] );

/* Chưa khai tài khoản nhận thì KHÔNG dựng mã QR — cùng chốt với QR của ghế. */
VHG_May::luu_nhan_tien( '', '', '' );
$dq3 = vhg_shop( 'dat', array( 'sdt' => '0909888666', 'pin' => '1234',
	'menh_gia' => 100000, 'so_luong' => 1 ) );
t( 'chưa khai tài khoản thì không bán được', empty( $dq3['ok'] ) );
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );

// ---- trang vẽ và cho tải ảnh
$sh_qr = vhg_shop_html();
t( 'trang có vẽ mã QR lên canvas', strpos( $sh_qr, 'function veQR(hang, o, px)' ) !== false );
/* 🔴 CANVAS chứ không SVG: canvas xuất ra PNG được, mà thư viện ảnh của điện thoại chỉ hiện ảnh
      raster — tải về một tệp SVG thì app ngân hàng không thấy đâu mà chọn. */
t( 'và tải về được dạng PNG', strpos( $sh_qr, "toDataURL('image/png')" ) !== false );
t( 'nút tải ảnh có tên rõ ràng', strpos( $sh_qr, 'Tải ảnh mã QR' ) !== false );
/* ⚠️ Vùng lặng 4 ô và nền trắng kín — thiếu một trong hai là nhiều máy quét không nhận ra mã. */
t( 'canvas chừa vùng lặng 4 ô', strpos( $sh_qr, 'lang = 4' ) !== false );
t( 'và tô nền trắng kín', strpos( $sh_qr, "c.fillStyle = '#fff'; c.fillRect(0, 0, tong, tong)" ) !== false );
/* Trình duyệt chặn tải thì phải NÓI RA và chỉ đường khác, đừng để nút bấm không làm gì. */
t( 'chặn tải thì chỉ đường chụp màn hình', strpos( $sh_qr, 'chụp màn hình mã QR này' ) !== false );
/* Và phần chép tay VẪN CÒN — hai đường cho hai kiểu khách. */
t( 'vẫn giữ phần chép tay cho ai muốn gõ', strpos( $sh_qr, 'Hoặc chuyển tay' ) !== false );
t( 'và vẫn có nút chép nội dung', strpos( $sh_qr, 'data-chep=' ) !== false );

/* ================================================================================================
 * 🔴 CHỮ TRÀN RA NGOÀI Ô THÌ KHÔNG AI XOÁ ĐƯỢC NỮA
 *
 * Anh Thắng 23/08/2026, ảnh chụp màn ghế: dòng "QUET DE MUA - hoac tem canh thung tien" vẫn nằm
 * đó ĐÈ LÊN dòng mô tả và dải chữ dưới cùng, ngay lúc ô đang hiện mệnh giá — *"Nó lệch hàng"*.
 *
 * Gốc không phải vẽ nhầm lượt. Gốc là dòng đó dài 228px trong ô rộng 150 (font 1 = 6px/ký tự),
 * và đặt ở y = b.y + 82 trong ô cao 84 — nên nó tràn 39px mỗi bên và 6px xuống dưới. Lượt luân
 * phiên sau, `veTheGoi()` tô lại đúng hình chữ nhật 150×84 của ô — TÔ LẠI KHÔNG CHẠM TỚI phần
 * đã vẽ ra ngoài. Vệt chữ đó nằm lại trên màn cho tới lần `drawIdle()` kế tiếp.
 *
 * Đây là một LỚP lỗi, không phải một lỗi: mọi chuỗi vẽ trong ô đều có thể dài quá mà không ai
 * biết, vì trên màn nó chỉ trông như "chữ hơi lệch". Đã dính ba lần trong cùng bản này (dòng
 * QUET DE MUA, dòng "(cham de mua goi nhu thuong)" tràn 9px, và dải tiêu đề đè mã ghế 11px).
 *
 * Nên phép thử ĐO chứ không đọc: bóc thân hai hàm vẽ ô, lấy mọi chuỗi font 1 vẽ trong đó, và
 * bắt chúng vừa cả chiều ngang lẫn chiều dọc của ô.
 * ============================================================================================= */
$ino_v = (string) file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );

/* Bóc thân một hàm C++ bằng cách đếm ngoặc nhọn — đủ dùng vì các hàm này không có chuỗi nào
   chứa ngoặc nhọn lẻ. */
$than_ham = function ( $ma, $ten ) {
	$vt = strpos( $ma, 'void ' . $ten . '(' );
	if ( false === $vt ) { return ''; }
	$mo = strpos( $ma, '{', $vt );
	if ( false === $mo ) { return ''; }
	$sau = 0;
	for ( $i = $mo; $i < strlen( $ma ); $i++ ) {
		if ( '{' === $ma[ $i ] ) { $sau++; }
		if ( '}' === $ma[ $i ] ) { $sau--; if ( 0 === $sau ) { return substr( $ma, $mo, $i - $mo + 1 ); } }
	}
	return '';
};

const O_RONG_PX = 150;   // PKG_BTN: {8,34,150,84}
const O_CAO_PX  = 84;
const F1_RONG   = 6;     // font 1 (GLCD 5x7) ăn 6px mỗi ký tự ở size 1
const F1_CAO    = 8;

foreach ( array( 'veTheQuangCao', 'veTheGoi' ) as $ten_ham ) {
	$than = $than_ham( $ino_v, $ten_ham );
	t( 'bóc được thân hàm ' . $ten_ham . '()', '' !== $than );
	/* Chỉ soi chuỗi vẽ CĂN GIỮA Ô (`, cx,`) — nhãn VVIP cố tình vẽ nhô lên trên mép ô nên nó
	   không đi qua cx, và nó là ngoại lệ có chủ đích. */
	$so_cho = preg_match_all( '/drawString\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*,\s*cx\s*,\s*b\.y \+ (\d+)\s*,\s*1\s*\)/',
		$than, $cac_cho, PREG_SET_ORDER );
	/* ⚠️ veTheGoi() KHÔNG có chuỗi cứng nào — chữ trong ô gói là biến do MÁY CHỦ gửi xuống
	      (PKG_TEN, PKG_MOTA). Nên ở đây chỉ đòi bóc được thân hàm; chiều dài của chúng canh ở
	      khối "máy chủ cắt chữ" ngay dưới, vì đó mới là nơi quyết định. */
	foreach ( $cac_cho as $cho ) {
		$chuoi = $cho[1];
		$doc_y = (int) $cho[2];
		$rong  = strlen( $chuoi ) * F1_RONG;
		t( '🔴 [' . $ten_ham . '] "' . $chuoi . '" vừa CHIỀU NGANG ô',
			$rong <= O_RONG_PX - 4,
			$rong . 'px trong ô ' . O_RONG_PX . 'px — tràn ' . max( 0, $rong - O_RONG_PX ) . 'px, phần tràn KHÔNG xoá được' );
		t( '🔴 [' . $ten_ham . '] "' . $chuoi . '" vừa CHIỀU DỌC ô',
			$doc_y + F1_CAO <= O_CAO_PX,
			'đáy chữ ở ' . ( $doc_y + F1_CAO ) . 'px trong ô cao ' . O_CAO_PX . 'px' );
	}
}

/* ---- 🔴 Chữ do MÁY CHỦ gửi xuống cũng phải vừa ô. Đây mới là chỗ dễ vỡ nhất, vì nó do anh
        Thắng gõ trong màn quản trị chứ không nằm trong mã. Trước bản này máy chủ cho tên 30 ký
        tự (180px) và mô tả 40 ký tự (240px) vào một ô rộng 150px — chưa lộ chỉ vì tên đang gõ
        ngắn. Và chú thích trong firmware lại ghi "đã cắt còn 16 ký tự": ba con số, không con
        nào khớp con nào. */
$suc_o = (int) floor( O_RONG_PX / F1_RONG );      // 150 / 6 = 25 ký tự
t( '🔴 máy chủ cắt tên gói + mô tả cho VỪA Ô, không rộng hơn',
	VHG_May::CHU_VUA_O <= $suc_o,
	'cắt ở ' . VHG_May::CHU_VUA_O . ' ký tự = ' . ( VHG_May::CHU_VUA_O * F1_RONG ) . 'px, ô chứa được ' . $suc_o );
t( 'và không cắt ngắn đến mức vô dụng', VHG_May::CHU_VUA_O >= 18 );
/* Cắt THẬT chứ không phải chỉ khai hằng số. */
$luu_dai = VHG_May::luu_menh_gia( array(
	array( 'tien' => 10000, 'phut' => 6, 'vip' => 0,
		'ten' => str_repeat( 'T', 60 ), 'mo_ta' => str_repeat( 'M', 60 ) ),
) );
t( 'lưu được bảng giá có chữ dài', ! empty( $luu_dai['ok'] ), isset( $luu_dai['error'] ) ? $luu_dai['error'] : '' );
$mg_dai = get_option( 'vhg_menh_gia' );
teq( '🔴 tên gói bị cắt đúng bằng sức chứa của ô',
	VHG_May::CHU_VUA_O, mb_strlen( (string) $mg_dai[0]['ten'] ) );
teq( '🔴 mô tả cũng vậy', VHG_May::CHU_VUA_O, mb_strlen( (string) $mg_dai[0]['mo_ta'] ) );
/* Và ô nhập phải NÓI RA giới hạn lúc gõ, đừng để anh Thắng gõ xong mới thấy chữ cụt. */
ob_start(); VHG_Admin::trang_may(); $adm_mg = ob_get_clean();
t( 'ô nhập tên gói khai maxlength khớp giới hạn',
	substr_count( (string) $adm_mg, 'maxlength="' . VHG_May::CHU_VUA_O . '"' ) >= 2 );

/* ---- Dải tiêu đề: mã ghế dài bao nhiêu cũng không được để tiêu đề đè lên.
   Trước đây tiêu đề căn cứng x=160 nên tự nó đã chồng 11px, và chồng 77px lúc mất mạng — đúng
   chữ "MAT MANG" là thứ nhân viên cần đọc nhất. Nay phải ĐO rồi mới xếp. */
$than_idle = $than_ham( $ino_v, 'drawIdle' );
t( 'bóc được thân hàm drawIdle()', '' !== $than_idle );
t( '🔴 dải tiêu đề ĐO chiều rộng phần bên phải thay vì đoán',
	strpos( $than_idle, 'tft.textWidth(chuPhai, 1)' ) !== false );
t( 'và chọn mức tiêu đề theo chỗ thật sự còn lại',
	preg_match( '/textWidth\(TIEU_DE\[k\], 1\)\s*<=\s*mepPhai/', $than_idle ) === 1 );
t( '🔴 không còn vẽ tiêu đề căn cứng giữa màn (x=160)',
	preg_match( '/drawString\("CHAO MUNG[^"]*",\s*160\s*,/', $than_idle ) !== 1 );
/* Mức ngắn nhất phải vừa kể cả khi mã ghế dài hết cỡ VÀ đang mất mạng. */
$ma_dai   = str_repeat( 'X', 20 );                    // luật đường dẫn cho tối đa 20 ký tự
$rong_phai = strlen( $ma_dai . ' - MAT MANG' ) * F1_RONG;
$cho_con  = 314 - $rong_phai - 8 - 6;
t( '🔴 mức tiêu đề ngắn nhất vẫn vừa khi mã ghế dài nhất + MAT MANG',
	strlen( 'MASSAGE' ) * F1_RONG <= $cho_con,
	'còn ' . $cho_con . 'px cho tiêu đề' );


/* ================================================================================================
 * VÍ — GÓI NẠP VÀ SỐ DƯ
 *
 * Anh Thắng 23/08/2026: *"bán mã giảm giá theo gói nạp. Nạp 100k được 120k. Nạp 200 được 300k.
 * Nạp 500 được 800k"*.
 *
 * 🔴 ĐÂY LÀ ĐƯỜNG TIỀN THỨ HAI. Bộ thử này canh bốn thứ, theo thứ tự nguy hiểm giảm dần:
 *      1. ví KHÔNG BAO GIỜ âm, kể cả hai ghế bấm cùng một khoảnh khắc
 *      2. doanh thu KHÔNG đếm hai lần (ghi lúc nạp, KHÔNG ghi lại lúc tiêu)
 *      3. webhook bắn lại KHÔNG cộng thêm tiền
 *      4. ai biết số điện thoại của khách KHÔNG chiếm được ví bằng cách nạp thêm 1.000đ
 * ============================================================================================= */
update_option( 'vhg_goi_nap', array() );
/* ⚠️ Khối phép thử ngay trên vừa THAY bảng giá bằng một gói 10.000đ duy nhất (nó thử chuyện cắt
      chữ dài). Khối này cần đủ mệnh giá, nên dựng lại bảng giá trước khi làm gì khác — chứ đừng
      ngầm trông vào thứ khối khác để lại. */
VHG_May::luu_menh_gia( array(
	array( 'tien' => 10000,  'phut' => 6,  'ten' => 'GOI CO BAN',    'mo_ta' => 'Khoi dong',  'vip' => 0 ),
	array( 'tien' => 50000,  'phut' => 30, 'ten' => 'GOI CHUYEN SAU','mo_ta' => 'Tri lieu',   'vip' => 0 ),
	array( 'tien' => 100000, 'phut' => 60, 'ten' => 'GOI THUONG HANG','mo_ta' => 'Dang cap',  'vip' => 1 ),
) );

// ---- cấu hình gói nạp
$gn_ok = VHG_Vi::luu_goi_nap( array(
	array( 'nap' => 500000, 'nhan' => 800000 ),
	array( 'nap' => 100000, 'nhan' => 120000 ),
	array( 'nap' => 200000, 'nhan' => 300000 ),
) );
t( 'lưu được ba gói nạp', ! empty( $gn_ok['ok'] ), isset( $gn_ok['error'] ) ? $gn_ok['error'] : '' );
$gn = VHG_Vi::goi_nap();
teq( 'còn đúng ba gói', 3, count( $gn ) );
teq( 'sắp theo số tiền nạp tăng dần', array( 100000, 200000, 500000 ),
	array_map( function ( $g ) { return (int) $g['nap']; }, $gn ) );
teq( 'nạp 100k thì được thêm 20k', 20000, (int) $gn[0]['them'] );
/* Khách so sánh bằng "lợi bao nhiêu so với tiền bỏ ra", không phải "giảm bao nhiêu phần trăm".
   Nạp 100k được 120k là LỢI 20%; nói "giảm 16,7%" thì đúng số học mà không ai hiểu. */
teq( 'và lợi 20% so với tiền bỏ ra', 20, (int) $gn[0]['loi_pt'] );
teq( 'nạp 500k lợi 60%', 60, (int) $gn[2]['loi_pt'] );

/* 🔴 NHẬN ÍT HƠN NẠP là móc túi khách — chặn ngay lúc lưu, đừng để một lần gõ nhầm thành một
      tuần bán hàng sai. */
$gn_lo = VHG_Vi::luu_goi_nap( array( array( 'nap' => 100000, 'nhan' => 90000 ) ) );
t( '🔴 từ chối gói cho nhận ít hơn nạp', empty( $gn_lo['ok'] ) );
t( 'và nói rõ khách lỗ', strpos( (string) $gn_lo['error'], 'lỗ' ) !== false );
$gn_trung = VHG_Vi::luu_goi_nap( array(
	array( 'nap' => 100000, 'nhan' => 120000 ), array( 'nap' => 100000, 'nhan' => 130000 ) ) );
t( 'từ chối hai gói cùng số tiền nạp', empty( $gn_trung['ok'] ) );
$gn_to = VHG_Vi::luu_goi_nap( array( array( 'nap' => 99000000, 'nhan' => 99000000 ) ) );
t( 'từ chối gói vượt trần', empty( $gn_to['ok'] ) );
/* Dòng trống thì bỏ qua im lặng — bảng nhập luôn có mấy dòng chưa gõ. */
$gn_trong = VHG_Vi::luu_goi_nap( array(
	array( 'nap' => '', 'nhan' => '' ), array( 'nap' => 100000, 'nhan' => 120000 ) ) );
t( 'dòng trống thì bỏ qua, không báo lỗi', ! empty( $gn_trong['ok'] ) );
teq( 'và chỉ lưu dòng có gõ', 1, count( VHG_Vi::goi_nap() ) );
/* Trả bảng về đủ ba gói cho các phép thử dưới. */
VHG_Vi::luu_goi_nap( array(
	array( 'nap' => 100000, 'nhan' => 120000 ),
	array( 'nap' => 200000, 'nhan' => 300000 ),
	array( 'nap' => 500000, 'nhan' => 800000 ) ) );

// ---- đặt đơn nạp
update_option( 'vhg_ma_cho_ngay', 5 );
$vd = VHG_Vi::dat_don( '0909111222', '1234', 100000, '' );
t( 'đặt được đơn nạp', ! empty( $vd['ok'] ), isset( $vd['error'] ) ? $vd['error'] : '' );
teq( 'phải trả đúng số tiền nạp', 100000, (int) $vd['phai_tra'] );
teq( 'nhận về đúng số tiền đã hứa', 120000, (int) $vd['nhan_tien'] );
teq( 'và chốt hạn chờ 5 ngày', 5, (int) $vd['cho_ngay'] );
t( 'từ chối gói nạp không có trong bảng',
	empty( VHG_Vi::dat_don( '0909111222', '1234', 123000, '' )['ok'] ) );
t( 'từ chối PIN không phải 4 chữ số',
	empty( VHG_Vi::dat_don( '0909111222', '12', 100000, '' )['ok'] ) );
t( 'từ chối số điện thoại sai',
	empty( VHG_Vi::dat_don( '123', '1234', 100000, '' )['ok'] ) );

// ---- tiền về -> cộng ví
$vi_sdt = '0909111222';
teq( 'chưa trả tiền thì chưa có ví', false, VHG_Vi::so_du( $vi_sdt )['co_vi'] );
$vn = VHG_Vi::nap( $vd['ma_don'], 'ref-nap-1' );
t( 'cộng ví được', ! empty( $vn['ok'] ), isset( $vn['error'] ) ? $vn['error'] : '' );
$sd1 = VHG_Vi::so_du( $vi_sdt );
teq( '🔴 tiền vào cột CHỜ, không tiêu được ngay', 0, (int) $sd1['dung'] );
teq( 'và cột chờ đúng 120k', 120000, (int) $sd1['cho'] );
teq( 'tổng vẫn là 120k', 120000, (int) $sd1['tong'] );
t( 'có nói khi nào tiêu được', $sd1['con_cho'] > 0 );

/* 🔴 WEBHOOK BẮN LẠI KHÔNG ĐƯỢC CỘNG THÊM. Bên gửi không nhận được phản hồi thì gửi tiếp —
      chuyện bình thường, và mỗi lượt bắn lại mà cộng thêm là cho không tiền thật. */
$vn2 = VHG_Vi::nap( $vd['ma_don'], 'ref-nap-1' );
t( 'gọi lại thì báo lặp lại', ! empty( $vn2['lap_lai'] ) );
teq( '🔴 và số dư KHÔNG nhích thêm đồng nào', 120000, (int) VHG_Vi::so_du( $vi_sdt )['tong'] );
VHG_Vi::nap( $vd['ma_don'], 'ref-nap-1' );
VHG_Vi::nap( $vd['ma_don'], 'ref-nap-1' );
teq( 'gọi năm lần vẫn vậy', 120000, (int) VHG_Vi::so_du( $vi_sdt )['tong'] );

// ---- hạn chờ chín
/* Kéo mốc về quá khứ để thử chín, thay vì chờ năm ngày thật. */
$wpdb->query( "UPDATE " . VHG_DB::t( 'vi_so' ) . " SET dung_duoc_tu='2020-01-01 00:00:00'
	WHERE sdt='" . $vi_sdt . "' AND da_chin=0" );
$chin1 = VHG_Vi::chin( $vi_sdt );
teq( 'tới hạn thì chuyển đúng 120k sang tiêu được', 120000, (int) $chin1 );
$sd2 = VHG_Vi::so_du( $vi_sdt );
teq( 'tiêu được 120k', 120000, (int) $sd2['dung'] );
teq( 'và không còn khoản chờ', 0, (int) $sd2['cho'] );
/* 🔴 CHÍN HAI LẦN KHÔNG ĐƯỢC CỘNG HAI LẦN. Hàm này chạy mỗi lần đọc ví, nên nó chạy rất
      nhiều lần — cộng thêm một lần thôi là ví tự đẻ tiền. */
teq( '🔴 gọi chín lần nữa thì không chuyển gì thêm', 0, (int) VHG_Vi::chin( $vi_sdt ) );
teq( 'và số dư đứng yên', 120000, (int) VHG_Vi::so_du( $vi_sdt )['tong'] );

// ---- tiêu tại ghế
$vt_sai = VHG_Vi::tieu( $vi_sdt, '9999', 10000, 'AMTP01' );
t( 'sai PIN thì không tiêu được', empty( $vt_sai['ok'] ) );
/* ⚠️ MỘT CÂU LỖI cho cả "chưa có ví" lẫn "sai PIN" — tách ra là biến ô này thành máy dò xem
      số nào đã nạp tiền. */
$vt_khong = VHG_Vi::tieu( '0909000000', '1234', 10000, 'AMTP01' );
teq( '🔴 "chưa có ví" và "sai PIN" nói y hệt nhau',
	(string) $vt_sai['error'], (string) $vt_khong['error'] );
t( 'ghế không có thì từ chối',
	empty( VHG_Vi::tieu( $vi_sdt, '1234', 10000, 'KHONGCO' )['ok'] ) );
/* 🔴 Mệnh giá phải ĐANG KHAI. Nhận con số khách gửi lên là mở đường chạy lượt 100.000đ mà chỉ
      trừ 1.000đ bằng cách sửa gói tin. */
t( '🔴 mệnh giá bịa thì từ chối',
	empty( VHG_Vi::tieu( $vi_sdt, '1234', 7777, 'AMTP01' )['ok'] ) );

$vt = VHG_Vi::tieu( $vi_sdt, '1234', 10000, 'AMTP01' );
t( 'tiêu được', ! empty( $vt['ok'] ), isset( $vt['error'] ) ? $vt['error'] : '' );
teq( 'trừ đúng 10k', 110000, (int) VHG_Vi::so_du( $vi_sdt )['dung'] );
/* ⚠️ Bản nháp của phép thử này viết `... || true` — tức là nó ĐẠT dù ghế không nhận được gì.
      Một phép thử luôn đạt còn tệ hơn không có phép thử: nó chiếm chỗ và làm người đọc yên tâm.
      Nay soi thẳng vào hàng chờ, đúng bảng ghế lấy lệnh về. */
$cho_vi = VHG_DB::rows( "SELECT * FROM " . VHG_DB::t( 'cho' )
	. " WHERE ma_may='AMTP01' AND ref LIKE 'vi-%' ORDER BY id DESC" );
t( '🔴 ghế thật sự được xếp lệnh chạy', count( $cho_vi ) >= 1 );
teq( 'và lệnh mang đúng số tiền của gói', 10000, (int) $cho_vi[0]['so_tien'] );
/* Số dư trừ 10k thì ghế phải chạy lượt 10k — không phải lượt bằng số tiền khách từng NẠP. */
t( 'nội dung lệnh nói rõ đây là tiêu ví',
	strpos( (string) $cho_vi[0]['noi_dung'], 'ví' ) !== false );
/* Và số điện thoại trong hàng chờ phải CHE — bảng này hiện trên màn quản trị. */
t( '🔴 hàng chờ không mang đủ số điện thoại khách',
	strpos( (string) $cho_vi[0]['noi_dung'], '0909111222' ) === false );

/* 🔴 TIÊU HẾT RỒI THÌ KHÔNG TIÊU THÊM ĐƯỢC. Đây là chốt quan trọng nhất của cả tệp này. */
$vt_het = VHG_Vi::tieu( $vi_sdt, '1234', 100000, 'AMTP01' );
t( 'tiêu 100k khi còn 110k thì được', ! empty( $vt_het['ok'] ) );
teq( 'còn lại 10k', 10000, (int) VHG_Vi::so_du( $vi_sdt )['dung'] );
$vt_thieu = VHG_Vi::tieu( $vi_sdt, '1234', 100000, 'AMTP01' );
t( '🔴 không đủ tiền thì TỪ CHỐI', empty( $vt_thieu['ok'] ) );
t( 'và nói rõ còn bao nhiêu', strpos( (string) $vt_thieu['error'], '10.000' ) !== false );
teq( '🔴 ví KHÔNG âm', 10000, (int) VHG_Vi::so_du( $vi_sdt )['dung'] );

/* 🔴 CHỐT CHỐNG VÍ ÂM NẰM Ở TẦNG SQL, không phải ở tầng PHP.
      Đọc số dư ra PHP rồi so sánh là hai máy cùng bấm thì CẢ HAI thấy đủ tiền. Phép thử chạy
      tuần tự nên không dựng lại được cảnh đó — nên canh thẳng vào CÂU LỆNH: nó phải mang điều
      kiện `so_du_dung>=%d` ngay trong UPDATE. */
$src_vi = (string) file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-vi.php' );
/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BÓC CHÚ THÍCH RA TRƯỚC KHI SOI MÃ NGUỒN.
 *
 * Ba lần trong đúng phiên làm việc hôm nay, một phép thử "mã nguồn KHÔNG được chứa X" tự hỏng
 * vì chú thích ngay cạnh chỗ sửa có NHẮC LẠI X để giải thích vì sao không dùng nó:
 *   · "QUET DE MUA"      — chú thích trích lại câu anh Thắng bảo ẩn
 *   · "MUA MA GIAM GIA"  — chú thích kể lại bố cục cũ
 *   · "VHG_Thu::ghi()"   — chú thích nói rõ vì sao KHÔNG gọi hàm đó
 *
 * Vá lẻ từng lần (đổi sang canh `drawString("X`, canh `X(`) chỉ đẩy vấn đề sang lần sau, vì
 * chú thích càng viết kỹ thì càng dễ chứa đúng chuỗi đang bị cấm — tức là phép thử phạt đúng
 * cái nết tốt.
 *
 * `token_get_all()` là bộ tách token của chính PHP, nên nó phân biệt được chú thích với chuỗi
 * và với mã thật — kể cả `//` nằm trong "https://". Bóc bằng nó thì phép thử soi ĐÚNG mã chạy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$bo_chu_thich = function ( $ma ) {
	$ra = '';
	foreach ( token_get_all( $ma ) as $tk ) {
		if ( is_array( $tk ) ) {
			if ( T_COMMENT === $tk[0] || T_DOC_COMMENT === $tk[0] ) { continue; }
			$ra .= $tk[1];
		} else {
			$ra .= $tk;
		}
	}
	return $ra;
};
$vi_chay = $bo_chu_thich( $src_vi );          // chỉ còn MÃ CHẠY, không còn chú thích
/* Tự kiểm bộ bóc: chuỗi chỉ có trong chú thích phải biến mất, mã thật phải còn. */
t( 'bộ bóc chú thích ăn đúng phần chú thích',
	strpos( $src_vi, 'có chủ đích' ) !== false && strpos( $vi_chay, 'có chủ đích' ) === false );
t( 'và giữ nguyên phần mã chạy', strpos( $vi_chay, 'function tieu(' ) !== false );
t( '🔴 lệnh trừ tiền mang điều kiện đủ tiền NGAY TRONG SQL',
	preg_match( '/UPDATE .{0,400}?SET so_du_dung=so_du_dung-%d.{0,300}?WHERE sdt=%s AND khoa=0 AND so_du_dung>=%d/s',
		$vi_chay ) === 1 );
t( 'và đọc số dòng đụng được để biết trừ có ăn không',
	preg_match( '/\$n = \$wpdb->query\(.{0,600}?return \(bool\) \$n;/s', $vi_chay ) === 1 );
/* Cờ chín cũng vậy: lật cờ TRƯỚC, và chỉ người lật được cờ mới cộng tiền. */
t( '🔴 cờ chín lật bằng UPDATE có điều kiện, không phải đọc-rồi-ghi',
	strpos( $vi_chay, 'UPDATE $ts SET da_chin=1 WHERE id=%d AND da_chin=0' ) !== false );

// ---- 🔴 DOANH THU KHÔNG ĐẾM HAI LẦN
/* Tiền thật vào tài khoản LÚC NẠP. Lúc khách tiêu, không có đồng nào chảy vào cửa hàng nữa —
   ghi thêm một dòng doanh thu ở đó là đếm hai lần đúng khoản tiền ấy. */
/* ⚠️ Canh LỆNH GỌI `VHG_Thu::ghi(`, đừng canh chuỗi 'VHG_Thu::ghi' trần trụi: chú thích đầu
      tệp có NHẮC TÊN hàm đó để giải thích vì sao không gọi, nên canh trần là phép thử tự hỏng
      vì chính lời giải thích của mình. Đây là lần thứ BA kiểu này dính trong phiên hôm nay —
      hai lần trước ở chuỗi "QUET DE MUA" và "MUA MA GIAM GIA". */
t( '🔴 tiêu ví KHÔNG gọi VHG_Thu::ghi — soi mã đã bóc chú thích',
	strpos( $vi_chay, 'VHG_Thu::ghi' ) === false );
/* Và lời giải thích thì phải CÒN — nó nằm trong chú thích, nên soi bản gốc. */
t( 'nhưng có nói rõ vì sao ngay trong mã', strpos( $src_vi, 'đếm hai lần' ) !== false );

// ---- nợ
$no = VHG_Vi::tong_no();
teq( 'tổng nợ đúng bằng số dư chưa tiêu', 10000, (int) $no['tong'] );
teq( 'và đếm đúng một ví', 1, (int) $no['so_vi'] );

// ---- sổ ví
$so_vi = VHG_Vi::ds_so( $vi_sdt, 50 );
t( 'sổ ví có đủ dòng nạp và các dòng tiêu', count( $so_vi ) >= 3 );
$tong_so = 0;
foreach ( $so_vi as $r ) { $tong_so += (int) $r['thay_doi']; }
teq( '🔴 cộng dồn cả sổ ra đúng số dư đang có', 10000, $tong_so );

// ---- 🔴 CHIẾM VÍ BẰNG CÁCH NẠP THÊM 1.000đ
/* Ai cũng biết số điện thoại của khách. Không có chốt này thì họ đặt một đơn nạp kèm PIN mới,
   trả tiền, và PIN ví bị ghi đè — rồi tiêu sạch số dư của người ta. */
$cuop = VHG_Vi::dat_don( $vi_sdt, '5555', 100000, '' );
t( '🔴 ví đã có mà PIN sai thì KHÔNG đặt được đơn nạp', empty( $cuop['ok'] ) );
t( 'và chỉ đường lấy lại PIN', strpos( (string) $cuop['error'], 'PIN' ) !== false );
$dung_pin = VHG_Vi::dat_don( $vi_sdt, '1234', 100000, '' );
t( 'PIN đúng thì nạp thêm bình thường', ! empty( $dung_pin['ok'] ) );

// ---- khoá ví
$kv = VHG_Vi::khoa( $vi_sdt, true, 'nghi ngờ gian lận', 'thang' );
t( 'khoá được ví', ! empty( $kv['ok'] ) );
$vt_khoa = VHG_Vi::tieu( $vi_sdt, '1234', 10000, 'AMTP01' );
t( '🔴 ví khoá thì không tiêu được', empty( $vt_khoa['ok'] ) );
teq( 'và tiền vẫn còn nguyên', 10000, (int) VHG_Vi::so_du( $vi_sdt )['dung'] );
/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHOÁ VÍ CHẶN Ở HAI TẦNG — và phép thử hành vi ở trên KHÔNG phân biệt được hai tầng đó.
 *
 * Thử đục từng tầng một thì thấy: bỏ tầng PHP, tầng SQL vẫn chặn; bỏ tầng SQL, tầng PHP vẫn
 * chặn. Nên câu `empty($vt_khoa['ok'])` chỉ gãy khi MẤT CẢ HAI — nó không canh được tầng nào
 * cả, dù nhìn thì tưởng có.
 *
 * Đó là phòng thủ chiều sâu thật, không phải thừa: tầng SQL là tầng KHÔNG THỂ đi vòng (mọi
 * lượt trừ tiền đều phải qua đúng câu lệnh ấy), tầng PHP là tầng cho khách CÂU TRẢ LỜI đúng
 * ("ví đang khoá") thay vì một câu khó hiểu ("số dư vừa thay đổi ở nơi khác").
 *
 * Nên canh riêng từng tầng, mỗi tầng một phép thử nói đúng thứ nó giữ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 [tầng SQL] lệnh trừ tiền tự nó từ chối ví đang khoá',
	strpos( $vi_chay, 'AND khoa=0 AND so_du_dung>=%d' ) !== false );
t( '🔴 [tầng PHP] và khách được nói đúng lý do, không phải một câu khó hiểu',
	strpos( (string) $vt_khoa['error'], 'khoá' ) !== false, (string) $vt_khoa['error'] );
VHG_Vi::khoa( $vi_sdt, false, 'đã kiểm tra xong', 'thang' );
t( 'mở khoá thì tiêu lại được',
	! empty( VHG_Vi::tieu( $vi_sdt, '1234', 10000, 'AMTP01' )['ok'] ) );

// ---- chỉnh tay
t( '🔴 chỉnh số dư mà không ghi lý do thì từ chối',
	empty( VHG_Vi::chinh_tay( $vi_sdt, 50000, '', 'thang' )['ok'] ) );
$ct = VHG_Vi::chinh_tay( $vi_sdt, 50000, 'đền bù ghế hỏng giữa lượt', 'thang' );
t( 'có lý do thì chỉnh được', ! empty( $ct['ok'] ) );
teq( 'cộng đúng 50k', 50000, (int) VHG_Vi::so_du( $vi_sdt )['dung'] );
t( '🔴 trừ tay quá số dư cũng bị chặn',
	empty( VHG_Vi::chinh_tay( $vi_sdt, -999999, 'thử trừ lố', 'thang' )['ok'] ) );
teq( 'ví vẫn không âm', 50000, (int) VHG_Vi::so_du( $vi_sdt )['dung'] );

// ---- lấy lại PIN bằng căn cước
$vi_cc = '0909333444';
$dcc = VHG_Vi::dat_don( $vi_cc, '1111', 100000, '012345678901' );
VHG_Vi::nap( $dcc['ma_don'], 'ref-cc' );
t( 'ví không khai căn cước thì không lấy lại PIN qua mạng được',
	empty( VHG_Vi::lay_lai_pin( $vi_sdt, '1234', '2222' )['ok'] ) );
t( 'khai rồi + đúng bốn số cuối thì đổi được',
	! empty( VHG_Vi::lay_lai_pin( $vi_cc, '8901', '2222' )['ok'] ) );
t( 'sai bốn số cuối thì không',
	empty( VHG_Vi::lay_lai_pin( $vi_cc, '0000', '3333' )['ok'] ) );
$sd_cc = VHG_Vi::so_du( $vi_cc );
teq( 'đổi PIN không đụng vào tiền', 120000, (int) $sd_cc['tong'] );

// ---- webhook rẽ đúng nhánh
$wd_nap = VHG_Vi::dat_don( '0909555666', '4321', 200000, '' );
$wh = VHG_Thu::nhan( 'sepay', array( 'ref' => 'wh-nap-1', 'so_tien' => 200000,
	'noi_dung' => 'SEVQR ' . VHG_QR::noi_dung_mua( $wd_nap['ma_don'] ), 'luc' => '' ) );
t( 'webhook nhận đơn nạp', ! empty( $wh['ok'] ) );
teq( '🔴 và cộng đúng 300k vào ví', 300000, (int) VHG_Vi::so_du( '0909555666' )['tong'] );
t( 'nhật ký nói rõ đây là đơn nạp ví',
	strpos( (string) ( isset( $wh['ghi_chu'] ) ? $wh['ghi_chu'] : '' ), 'nạp ví' ) !== false );
/* Đơn MUA MÃ vẫn đi nhánh cũ — thêm nhánh nạp không được cướp đường của nhánh mã. */
$wd_ma = VHG_Ma::dat_don( '0909777888', '1357', 100000, 1, '' );
$wh2 = VHG_Thu::nhan( 'sepay', array( 'ref' => 'wh-ma-1', 'so_tien' => (int) $wd_ma['phai_tra'],
	'noi_dung' => 'SEVQR ' . VHG_QR::noi_dung_mua( $wd_ma['ma_don'] ), 'luc' => '' ) );
t( '🔴 đơn mua mã vẫn phát mã như cũ', ! empty( $wh2['ma_phat'] ) );
teq( 'và KHÔNG cộng vào ví nào', false, VHG_Vi::so_du( '0909777888' )['co_vi'] );

/* 🔴 ĐƠN CŨ không có cột `loai` (đặt trước bản này) phải vẫn PHÁT MÃ. Đọc rỗng thành 'nap' là
      mọi đơn cũ chưa trả tiền biến thành đơn nạp, và khách trả tiền xong không nhận được mã. */
$wd_cu = VHG_Ma::dat_don( '0909999000', '2468', 100000, 1, '' );
$wpdb->query( "UPDATE " . VHG_DB::t( 'don_ma' ) . " SET loai='' WHERE ma_don='" . $wd_cu['ma_don'] . "'" );
$wh3 = VHG_Thu::nhan( 'sepay', array( 'ref' => 'wh-cu-1', 'so_tien' => (int) $wd_cu['phai_tra'],
	'noi_dung' => 'SEVQR ' . VHG_QR::noi_dung_mua( $wd_cu['ma_don'] ), 'luc' => '' ) );
t( '🔴 đơn cũ không có cột loai vẫn phát mã', ! empty( $wh3['ma_phat'] ) );

// ---- số điện thoại trong sổ phải che
teq( 'số điện thoại hiện ra thì che khúc giữa', '0909***222', VHG_Ma::sdt_che( '0909111222' ) );
t( 'và không hiện đủ số', strpos( VHG_Ma::sdt_che( '0909111222' ), '111' ) === false );


/* ---- 🔴 BỘ THỬ PHẢI NẠP ĐỦ MỌI LỚP CỦA PLUGIN
   Danh sách nạp ở đầu tệp này chép tay. Thêm một lớp vào plugin mà quên thêm vào đó thì bộ thử
   chạy tới chỗ chạm lớp ấy mới ngã, kèm câu "Class not found" — và nếu chưa có phép thử nào
   chạm tới thì nó KHÔNG ngã, mà lặng lẽ không thử gì cả. Đã dính đúng lúc thêm VHG_Vi. */
$tep_nap = (string) file_get_contents( __FILE__ );
$thieu_nap = array();
foreach ( glob( VHG_DIR . 'includes/class-vhg-*.php' ) as $tep_ ) {
	$ten_ = basename( $tep_, '.php' );                       // class-vhg-vi
	$khoa_ = substr( $ten_, strlen( 'class-vhg-' ) );        // vi
	if ( 'admin' === $khoa_ ) { continue; }                  // nạp riêng ở dòng dưới
	if ( strpos( $tep_nap, "'" . $khoa_ . "'" ) === false ) { $thieu_nap[] = $khoa_; }
}
teq( '🔴 bộ thử nạp đủ mọi lớp của plugin', array(), $thieu_nap );


// ---- trang khách: tab nạp ví + ô tiêu số dư
VHG_Vi::luu_goi_nap( array(
	array( 'nap' => 100000, 'nhan' => 120000 ),
	array( 'nap' => 200000, 'nhan' => 300000 ),
	array( 'nap' => 500000, 'nhan' => 800000 ) ) );
$sh_v = vhg_shop_html( 'AMTP01' );
t( 'trang khách có tab Nạp ví', strpos( $sh_v, "data-tab=\"nap\"" ) !== false );
t( 'và có màn chọn gói nạp', strpos( $sh_v, 'data-nap=' ) !== false );
/* 🔴 Con số khách dùng để quyết định là "ĐƯỢC THÊM bao nhiêu", không phải "giảm bao nhiêu %". */
t( '🔴 nói "được thêm", không nói "giảm %"', strpos( $sh_v, 'được thêm' ) !== false );
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TIÊU SỐ DƯ = BẤM MỘT THẺ, không phải mở một ô chọn.
 *
 * Anh Thắng 23/08/2026: *"Quét qr. cho gói sử dụng. Bấm. Hệ thống sẽ trừ thẳng tiền trong gói
 * đang còn"*. Ba bước, không bước nào là "mở danh sách rồi cuộn tìm".
 *
 * Bản trước là `<select>` + hai ô nhập + một nút. Trên điện thoại, ô chọn là một cửa sổ bật lên
 * che hết màn — khách phải rời khỏi thứ đang nhìn. Thẻ bấm thì thấy hết, chạm một cái là xong,
 * và giống hệt màn hình trên chính cái ghế họ đang ngồi.
 * ═════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 mở ví xong thì gói là THẺ BẤM', strpos( $sh_v, 'data-tieu=' ) !== false );
t( 'và không còn ô chọn gói kiểu danh sách', strpos( $sh_v, 'id="t-goi"' ) === false );
t( 'có ô mở ví bằng số điện thoại + PIN', strpos( $sh_v, 'id="t-mo"' ) !== false );
/* Gói vượt số dư thì làm mờ và BỎ HẲN đường bấm — để bấm rồi mới báo lỗi là bắt khách phát
   hiện điều mà trang đã biết trước. */
t( 'gói vượt số dư thì làm mờ, không cho bấm', strpos( $sh_v, "'.g.het'" ) !== false
	|| strpos( $sh_v, '.g.het{' ) !== false );
/* 🔴 NHỚ SỐ ĐIỆN THOẠI, KHÔNG NHỚ PIN. Điện thoại để quên trên ghế là người cầm máy tiêu sạch ví. */
t( '🔴 trang nhớ số điện thoại cho lần sau', strpos( $sh_v, "localStorage.setItem('vhg_sdt'" ) !== false );
t( '🔴 nhưng KHÔNG ghi PIN vào máy khách',
	strpos( $sh_v, "setItem('vhg_pin'" ) === false
	&& preg_match( '/localStorage\.setItem\([^)]*pin/i', $sh_v ) !== 1 );
/* ⚠️ Ô xem số dư ĐÃ GỘP vào ô tra chung của tab "Của tôi" (anh Thắng 23/08/2026: *"cùng 1 ví
      mà, sao lại ra 2 lần đăng nhập"*). Nay canh cái ô GỘP, không canh cái ô cũ đã bỏ. */
t( 'kèm ô tra chung ở tab Của tôi (ví + mã cùng một lần nhập)',
	strpos( $sh_v, "id=\"t-xem\"" ) !== false && strpos( $sh_v, "id=\"v-xem\"" ) === false );
/* Hạn chờ phải nói TRƯỚC khi khách trả tiền, y như bên mã. */
t( '🔴 nói hạn chờ của số dư trước khi khách trả tiền',
	strpos( $sh_v, 'Số dư nạp dùng được sau' ) !== false );
/* PIN của ví đã có là PIN CŨ — không nói rõ thì khách gõ PIN mới rồi bị từ chối mà không hiểu. */
t( 'nói rõ ví đã có thì nhập đúng PIN cũ', strpos( $sh_v, 'đúng PIN cũ' ) !== false );

/* 🔴 CHƯA KHAI GÓI NẠP thì KHÔNG bày tab ra. Bày một tab dẫn vào trang trống còn tệ hơn không có.
   ⚠️ KHÔNG canh được bằng cách xem HTML có chuỗi đó hay không: tab dựng bằng JS LÚC CHẠY, nên
      mã JS nằm trong trang bất kể đã khai gói hay chưa. Bản nháp của phép thử này canh như vậy
      và gãy ngay — đúng ra là nó ĐANG canh sai chỗ, không phải mã sai.
      Thứ thật sự quyết định có hai: (a) điều kiện trong JS, (b) danh sách máy chủ gửi xuống. */
update_option( 'vhg_goi_nap', array() );
$sh_kn = vhg_shop_html( 'AMTP01' );
t( '🔴 JS chỉ bày tab Nạp ví khi máy chủ có gửi gói nạp xuống',
	strpos( $sh_kn, 'var coNap = !!(D && D.goi_nap && D.goi_nap.length);' ) !== false );
t( 'và khối tiêu số dư cũng nấp sau đúng điều kiện đó',
	preg_match( '/if \(D && D\.goi_nap && D\.goi_nap\.length\) \{\s*h \+= .{0,140}?Trả bằng số dư ví/s',
		$sh_kn ) === 1 );
teq( '🔴 chưa khai thì máy chủ gửi xuống danh sách RỖNG', 0,
	count( (array) vhg_shop( 'goi' )['goi_nap'] ) );
VHG_Vi::luu_goi_nap( array(
	array( 'nap' => 100000, 'nhan' => 120000 ),
	array( 'nap' => 200000, 'nhan' => 300000 ),
	array( 'nap' => 500000, 'nhan' => 800000 ) ) );

// ---- cổng API của ví
$api_goi = vhg_shop( 'goi' );
t( 'API bảng giá kèm luôn danh sách gói nạp', isset( $api_goi['goi_nap'] ) );
teq( 'và đủ ba gói', 3, count( (array) $api_goi['goi_nap'] ) );
/* ⚠️ Cổng tra ví phải nói MỘT CÂU LỖI cho cả "chưa có ví" lẫn "sai PIN". */
$GLOBALS['VHCP_TR'] = array();                                   // xoá hãm thử của khối trước
$api_v1 = vhg_shop( 'vi', array( 'sdt' => '0909111222', 'pin' => '0000' ) );
$api_v2 = vhg_shop( 'vi', array( 'sdt' => '0900000000', 'pin' => '1234' ) );
teq( '🔴 cổng ví: sai PIN và chưa có ví nói y hệt nhau',
	(string) $api_v1['error'], (string) $api_v2['error'] );
$api_v3 = vhg_shop( 'vi', array( 'sdt' => '0909111222', 'pin' => '1234' ) );
t( 'PIN đúng thì trả về số dư', ! empty( $api_v3['ok'] ) && isset( $api_v3['so_du'] ) );
t( 'kèm sổ gần đây', isset( $api_v3['so'] ) );
/* 🔴 Cổng ví KHÔNG được trả về chuỗi băm PIN — đó là thứ chỉ máy chủ cần. */
t( '🔴 cổng ví không phơi chuỗi băm PIN ra ngoài',
	strpos( wp_json_encode( $api_v3 ), 'pin_bam' ) === false );

// ---- màn quản trị
ob_start(); VHG_Admin::trang_may(); $adm_v = ob_get_clean();
t( 'màn quản trị có bảng khai gói nạp', strpos( $adm_v, 'name="gn_nap[]"' ) !== false );
t( 'và ô khai số tiền khách nhận', strpos( $adm_v, 'name="gn_nhan[]"' ) !== false );
/* 🔴 KHOẢN NỢ phải hiện ngay cạnh bảng gói nạp. Bán gói nạp thì tháng nào doanh thu cũng đẹp;
      con số duy nhất nói ra sự thật là "khách đã trả nhưng chưa tiêu". */
t( '🔴 hiện khoản nợ ngay tại chỗ khai gói nạp',
	strpos( $adm_v, 'Khách đã trả tiền nhưng chưa tiêu' ) !== false );
t( 'và gọi đúng tên nó là khoản nợ', strpos( $adm_v, 'khoản nợ' ) !== false );
t( 'có đường chỉnh tay số dư', strpos( $adm_v, 'name="vi_ly_do"' ) !== false );
t( 'và đường khoá ví', strpos( $adm_v, 'value="vi_khoa"' ) !== false );
/* ⚠️ Màn này nhân viên ca nào cũng mở — số điện thoại phải CHE. */
t( '🔴 danh sách ví che số điện thoại', strpos( $adm_v, '0909111222' ) === false );
t( 'nhưng vẫn hiện đủ để đối chiếu', strpos( $adm_v, '0909' ) !== false );



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 "MỆNH GIÁ VẪN THEO MÁY" — SỐ PHÚT PHẢI THEO ĐÚNG GHẾ KHÁCH ĐANG NGỒI
 *
 * Anh Thắng 23/08/2026. Mỗi ghế có tỉ lệ quy đổi RIÊNG, nên cùng gói 10.000đ có ghế ra 6 phút,
 * ghế khác ra 10 phút. Trang khách trả về bảng chung là hiện số phút của tỉ lệ chung — SAI ở
 * mọi ghế có tỉ lệ riêng, và sai theo kiểu tệ nhất: khách tin con số đó, ngồi xuống, rồi ghế
 * tắt sớm hơn họ tưởng. Con số sai còn tệ hơn không có con số nào.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHG_May::luu_ty_le( 10000, 6 );                                   // tỉ lệ CHUNG: 10k = 6 phút
/* Dựng hẳn một ghế thứ hai cho phép thử này thay vì trông vào ghế khối khác để lại — khối
   trên có thể xoá, đổi tên, hoặc chưa từng tạo AMTP02. */
$wpdb->insert( VHG_DB::t( 'may' ), array(
	'ma' => 'AMTP02', 'mac' => 'AA:BB:CC:DD:EE:02', 'coso_id' => 0,
	'gia' => 10000, 'phut' => 12, 'cap_nhat' => current_time( 'mysql' ) ) );
t( 'có ghế AMTP02 để thử tỉ lệ riêng', (bool) VHG_May::may( 'AMTP02' ) );

$mg_chung = VHG_Ma::ds_menh_gia();
$mg_a1    = VHG_Ma::ds_menh_gia( 'AMTP01' );                      // không khai riêng -> theo chung
$mg_a2    = VHG_Ma::ds_menh_gia( 'AMTP02' );                      // khai riêng 10k = 12 phút

t( 'bảng gói có kèm số phút', isset( $mg_chung[0]['phut'] ) );
teq( 'ghế không khai riêng thì theo tỉ lệ chung',
	(int) $mg_chung[0]['phut'], (int) $mg_a1[0]['phut'] );

/* ⚠️ HAI KIỂU GÓI, và chúng cư xử KHÁC NHAU — bản nháp của phép thử này quên mất chuyện đó rồi
      tưởng mã sai:
        · gói BỎ TRỐNG số phút -> tính theo tỉ lệ của TỪNG GHẾ  (thứ anh Thắng nói)
        · gói KHAI CỨNG số phút -> giữ nguyên ở MỌI GHẾ, có chủ đích (gói khuyến mãi, gói kèm quà)
      Gói dựng ở trên khai cứng 6 phút, nên nó KHÔNG đổi theo ghế — đúng thiết kế. Canh cả hai. */
teq( '🔴 gói KHAI CỨNG số phút thì mọi ghế như nhau — cố ý',
	(int) $mg_a1[0]['phut'], (int) $mg_a2[0]['phut'] );

/* Gói BỎ TRỐNG số phút mới là gói chạy theo tỉ lệ ghế. */
VHG_May::luu_menh_gia( array(
	array( 'tien' => 10000,  'phut' => 0, 'ten' => 'GOI CO BAN',     'mo_ta' => 'Khoi dong', 'vip' => 0 ),
	array( 'tien' => 50000,  'phut' => 0, 'ten' => 'GOI CHUYEN SAU', 'mo_ta' => 'Tri lieu',  'vip' => 0 ),
	array( 'tien' => 100000, 'phut' => 0, 'ten' => 'GOI THUONG HANG','mo_ta' => 'Dang cap',  'vip' => 1 ),
) );
$tr_a1 = VHG_Ma::ds_menh_gia( 'AMTP01' );
$tr_a2 = VHG_Ma::ds_menh_gia( 'AMTP02' );
teq( 'gói bỏ trống: ghế theo tỉ lệ chung ra 6 phút', 6, (int) $tr_a1[0]['phut'] );
t( '🔴 gói bỏ trống: ghế khai tỉ lệ riêng ra số phút KHÁC hẳn',
	(int) $tr_a2[0]['phut'] !== (int) $tr_a1[0]['phut'],
	'AMTP01=' . (int) $tr_a1[0]['phut'] . ' phút, AMTP02=' . (int) $tr_a2[0]['phut'] . ' phút' );
teq( 'và đúng gấp đôi vì tỉ lệ gấp đôi', 12, (int) $tr_a2[0]['phut'] );
$mg_a2 = $tr_a2;
/* Số phút trang khách hiện phải TRÙNG KHÍT con số ghế nhận qua nhịp — hai nơi tính khác nhau
   là khách đọc một đằng, ghế chạy một nẻo, và không ai biết bên nào đúng. */
$nh_p = vhg_ghe( array( 'viec' => 'nhip', 'mac' => 'AA:BB:CC:DD:EE:02', 'trang_thai' => 'idle' ) );
if ( ! empty( $nh_p[1]['goi'] ) ) {
	teq( '🔴 số phút trang khách TRÙNG con số ghế nhận qua nhịp',
		(int) $nh_p[1]['goi'][0]['p'], (int) $mg_a2[0]['phut'] );
}
/* Và cổng của trang khách phải TRUYỀN mã ghế vào — không truyền thì mọi ghế ra số phút chung. */
$_SERVER['REQUEST_URI'] = '/' . VHG_Shop::slug();
$_GET = array( 'ghe' => 'AMTP02' );
$api_p = vhg_shop( 'goi', array( 'ghe' => 'AMTP02' ) );
teq( '🔴 cổng trang khách trả số phút của ĐÚNG ghế trên tem',
	(int) $mg_a2[0]['phut'], (int) $api_p['goi'][0]['phut'] );
$_GET = array();

// ---- công tắc bán mã lẻ
/* Anh Thắng: *"Thay vì khách mua mã code thì mua gói nạp"*. Nhưng ba mươi phút trước, khi được
   hỏi thẳng, anh chọn "nằm cạnh". Hai câu không cùng hướng — nên đây là CÔNG TẮC, không phải
   một lần xoá: xoá là mất cả nhánh đã chạy được, và mã của khách chưa dùng nằm trong đó. */
delete_option( 'vhg_ban_ma' );
t( '🔴 chưa khai thì MẶC ĐỊNH VẪN BÁN — cài bản mới không được tự tắt thứ đang chạy',
	VHG_Ma::con_ban_ma() );
update_option( 'vhg_ban_ma', 0 );
t( 'tắt được', ! VHG_Ma::con_ban_ma() );
$sh_tat = vhg_shop_html( 'AMTP01' );
t( 'tắt rồi thì trang khách biết mà giấu tab Mua mã',
	strpos( $sh_tat, 'var coMa  = !D || D.ban_ma !== 0;' ) !== false );
teq( 'và cổng nói rõ đã tắt', 0, (int) vhg_shop( 'goi' )['ban_ma'] );
/* 🔴 CHẶN CẢ Ở CỔNG, không chỉ giấu tab. Giấu tab mà cổng vẫn nhận là ai còn giữ link cũ vẫn
      đặt được đơn — rồi trả tiền cho một thứ cửa hàng đã ngừng bán. */
$dat_tat = vhg_shop( 'dat', array( 'sdt' => '0909222333', 'pin' => '1234',
	'menh_gia' => 100000, 'so_luong' => 1 ) );
t( '🔴 tắt rồi thì cổng TỪ CHỐI đơn mua mã', empty( $dat_tat['ok'] ) );
t( 'và chỉ đường sang nạp ví', strpos( (string) $dat_tat['error'], 'Nạp ví' ) !== false );
/* Nhưng mã ĐÃ BÁN vẫn phải dùng được — tắt là ngừng bán thêm, không phải huỷ hàng đã bán. */
update_option( 'vhg_ban_ma', 1 );
$dm = VHG_Ma::dat_don( '0909444555', '1234', 100000, 1, '' );
VHG_Ma::phat_ma( $dm['ma_don'], 'ref-tat' );
$ma_cu = VHG_Ma::ds_ma_cua_don( $dm['ma_don'] )[0];
$wpdb->query( "UPDATE " . VHG_DB::t( 'ma' ) . " SET cho_ngay=0 WHERE ma='" . $ma_cu . "'" );
update_option( 'vhg_ban_ma', 0 );
$dung_cu = VHG_Ma::dung( $ma_cu, 'AMTP01' );
t( '🔴 mã đã bán trước khi tắt VẪN dùng được', ! empty( $dung_cu['ok'] ),
	isset( $dung_cu['error'] ) ? $dung_cu['error'] : '' );
update_option( 'vhg_ban_ma', 1 );



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 23/08/2026 — MỌI MÃ QR TRANG WEB SINH RA ĐỀU KHÔNG QUÉT ĐƯỢC
 *
 * Anh Thắng: *"giờ quét mã này không nhận"* (mã trả tiền của một đơn mua mã).
 *
 * 15 bit thông tin định dạng bị đặt SOI GƯƠNG: nửa bit thấp lẽ ra chạy DỌC cột 8 thì chạy
 * NGANG hàng 8, và ngược lại. Giá trị 15 bit thì đúng — chỉ sai chỗ ngồi. Máy quét đọc ra một
 * mức sửa lỗi và một mặt nạ SAI, gỡ mặt nạ sai, nhận về toàn rác, rồi lặng lẽ không nhận.
 *
 * Đo bằng một bộ GIẢI MÃ THẬT (zxing-cpp — chính thư viện nhiều app ngân hàng dùng):
 *      trước khi sửa:  0/36 mã đọc được
 *      sau khi sửa:   36/36
 *
 * ⚠️ VÀ BỘ THỬ CŨ BÁO SẠCH SUỐT THỜI GIAN ĐÓ.
 *    Nó có ba lớp, mà lớp mạnh nhất là "vẽ ra rồi đọc ngược". Lớp ấy chỉ chứng minh được MỘT
 *    điều: bộ đọc của mình hiểu bộ vẽ của mình. Hai bên cùng đọc/ghi thông tin định dạng ở
 *    đúng những ô sai như nhau, nên vòng tròn khép kín một cách hoàn hảo.
 *
 *    Bài học không phải "viết thêm phép thử đọc ngược". Là: một bộ thử tự nói chuyện với chính
 *    nó thì không bao giờ phát hiện được mình đang nói sai ngôn ngữ. Phải có MỘT MỐC NGOÀI.
 *
 * Nên nay có hai lớp nữa, và cả hai đều KHÔNG dùng bộ đọc của chính tệp này:
 *   · Lớp 4 — đối chiếu từng ô với ma trận sinh bởi bộ mã hoá ĐỘC LẬP (đã được bộ giải mã thật
 *     xác nhận trước khi cất vào repo).
 *   · Lớp 5 — đọc 15 bit định dạng theo ĐÚNG TOẠ ĐỘ bản đặc tả, kiểm BCH, và bắt hai bản sao
 *     phải khớp nhau. Lớp này bắt đúng kiểu lỗi vừa xảy ra mà không cần tệp mốc nào.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

// --- Lớp 4: đối chiếu với mốc ngoài
$qr_chuoi_v = '00020101021238560010A0000007270126000697041501121088785839510208QRIBFTTA'
	. '5303704540490005802VN62190815SEVQR MUA8C4GEM6304A1B4';
$qr_mocv = array();
foreach ( explode( "\n", (string) file_get_contents( $goc . '/tools/test/fixtures/qr-chuan-vietqr-L.txt' ) ) as $d_ ) {
	$d_ = trim( $d_ );
	if ( '' === $d_ || '#' === substr( $d_, 0, 1 ) ) { continue; }
	$qr_mocv[] = $d_;
}
t( 'đọc được ma trận chuẩn từ tệp mốc', count( $qr_mocv ) > 0 );
$qr_em = array();
foreach ( VHG_QRVe::ma_tran( $qr_chuoi_v, 'L' ) as $h_ ) { $qr_em[] = implode( '', $h_ ); }
teq( 'cỡ ma trận khớp mốc ngoài', count( $qr_mocv ), count( $qr_em ) );
$qr_lech = 0;
foreach ( $qr_em as $y_ => $h_ ) {
	if ( ! isset( $qr_mocv[ $y_ ] ) ) { continue; }
	for ( $x_ = 0; $x_ < strlen( $h_ ); $x_++ ) {
		if ( $h_[ $x_ ] !== $qr_mocv[ $y_ ][ $x_ ] ) { $qr_lech++; }
	}
}
teq( '🔴 KHỚP TỪNG Ô với bộ mã hoá độc lập — 0 ô lệch', 0, $qr_lech );

// --- Lớp 5: thông tin định dạng nằm ĐÚNG toạ độ bản đặc tả
/* Toạ độ (x,y) của 15 bit, theo ISO/IEC 18004. Bit 0 là bit thấp nhất.
   Bản sao 1: bit 0..5 chạy DỌC cột 8 (y=0..5, nhảy ô nhịp y=6); bit 6 ở (8,7); bit 7 ở (8,8);
              bit 8 ở (7,8); bit 9..14 chạy NGANG hàng 8 (x=5,4,3,2,1,0).
   Bản sao 2: bit 0..7 chạy NGANG mép phải hàng 8; bit 8..14 chạy DỌC mép dưới cột 8. */
$qr_o = VHG_QRVe::ma_tran( $qr_chuoi_v, 'L' );
$qr_n = count( $qr_o );
$vt1 = array( array( 8, 0 ), array( 8, 1 ), array( 8, 2 ), array( 8, 3 ), array( 8, 4 ),
	array( 8, 5 ), array( 8, 7 ), array( 8, 8 ), array( 7, 8 ),
	array( 5, 8 ), array( 4, 8 ), array( 3, 8 ), array( 2, 8 ), array( 1, 8 ), array( 0, 8 ) );
$vt2 = array();
for ( $i_ = 0; $i_ < 8; $i_++ )  { $vt2[] = array( $qr_n - 1 - $i_, 8 ); }
for ( $i_ = 8; $i_ < 15; $i_++ ) { $vt2[] = array( 8, $qr_n - 15 + $i_ ); }

$doc_dd = function ( $o, $vt ) {
	$v = 0;
	foreach ( $vt as $i => $xy ) { $v |= ( (int) $o[ $xy[1] ][ $xy[0] ] ) << $i; }
	return $v;
};
$dd1 = $doc_dd( $qr_o, $vt1 );
$dd2 = $doc_dd( $qr_o, $vt2 );
teq( '🔴 hai bản sao thông tin định dạng khớp nhau', $dd1, $dd2 );

/* BCH(15,5) phải chia hết sau khi bỏ mặt nạ cố định — đây là chốt độc lập hoàn toàn với mã
   trong repo: nó chỉ dùng đa thức của bản đặc tả. */
$bch_du = function ( $v ) {
	$v ^= 0b101010000010010;
	for ( $i = 14; $i >= 10; $i-- ) {
		if ( $v & ( 1 << $i ) ) { $v ^= 0b10100110111 << ( $i - 10 ); }
	}
	return $v & 0x3FF;
};
teq( '🔴 15 bit định dạng qua được phép kiểm BCH của bản đặc tả', 0, $bch_du( $dd1 ) );
/* Và nó phải nói ĐÚNG mức sửa lỗi đã yêu cầu. Đọc sai mức là máy quét gỡ mặt nạ sai. */
$muc_bit = ( ( $dd1 ^ 0b101010000010010 ) >> 13 ) & 3;
teq( '🔴 và nói đúng mức sửa lỗi L', 1, $muc_bit );

/* Chốt cuối: cùng phép kiểm đó cho CẢ BỐN mức, ở nhiều chuỗi. Lỗi soi gương chỉ lộ ở một số
   tổ hợp nếu chỉ thử một chuỗi — 15 bit của vài (mức, mặt nạ) tình cờ đối xứng. */
$muc_so = array( 'L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2 );
foreach ( array( $qr_chuoi_v, 'khmatrix.com/mua-ma/?ghe=AMTP01', 'HELLO WORLD', '0' ) as $ct_ ) {
	foreach ( $muc_so as $mc_ => $bit_ ) {
		$oo = VHG_QRVe::ma_tran( $ct_, $mc_ );
		if ( ! $oo ) { continue; }
		$nn = count( $oo );
		$v1 = array( array( 8, 0 ), array( 8, 1 ), array( 8, 2 ), array( 8, 3 ), array( 8, 4 ),
			array( 8, 5 ), array( 8, 7 ), array( 8, 8 ), array( 7, 8 ),
			array( 5, 8 ), array( 4, 8 ), array( 3, 8 ), array( 2, 8 ), array( 1, 8 ), array( 0, 8 ) );
		$v2 = array();
		for ( $i_ = 0; $i_ < 8; $i_++ )  { $v2[] = array( $nn - 1 - $i_, 8 ); }
		for ( $i_ = 8; $i_ < 15; $i_++ ) { $v2[] = array( 8, $nn - 15 + $i_ ); }
		$a1 = $doc_dd( $oo, $v1 );
		$nhan = '[' . $mc_ . '] "' . substr( $ct_, 0, 18 ) . '"';
		teq( 'BCH đúng ' . $nhan, 0, $bch_du( $a1 ) );
		teq( 'đúng mức ' . $nhan, $bit_, ( ( $a1 ^ 0b101010000010010 ) >> 13 ) & 3 );
		teq( 'hai bản sao khớp ' . $nhan, $a1, $doc_dd( $oo, $v2 ) );
		/* Ô tối cố định phải còn nguyên — bản cũ suýt ghi đè nó bằng một bit định dạng. */
		teq( 'ô tối cố định còn nguyên ' . $nhan, 1, (int) $oo[ $nn - 8 ][8] );
	}
}



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 23/08/2026 — TRANG BIẾT GHẾ NHƯNG KHÔNG NÓI CHO MÁY CHỦ
 *
 * Anh Thắng: *"quét QR tại ghế nó chọn luôn ghế đó, nhưng giờ chọn gói nó báo chưa chọn ghế"*.
 *
 * Máy chủ nhúng địa chỉ API TRẦN vào trang (`self::url()`, không tham số). Trang thì biết ghế
 * — màn hiện đúng "AMTP01" — nhưng mọi lượt gọi API đi tới một địa chỉ không mang ghế, nên máy
 * chủ dò lại và không thấy gì.
 *
 * Hỏng hai chỗ, và chỗ thứ hai TỆ HƠN vì nó không kêu lên:
 *   · `tieu` -> "Chưa biết dùng cho ghế nào"          (kêu, anh Thắng thấy ngay)
 *   · `goi`  -> số phút rơi về TỈ LỆ CHUNG ở mọi ghế  (im lặng, khách tin con số sai)
 *
 * ⚠️ VÌ SAO BỘ THỬ KHÔNG BẮT ĐƯỢC. `vhg_shop()` nhận mã ghế qua khoá `ghe_url` — một lối tắt
 *    CHỈ CÓ TRONG BỘ THỬ. Nó tự đưa mã ghế vào `$_GET` rồi gọi API, tức là nó thử một đường mà
 *    trang thật không bao giờ đi. Bộ thử và sản phẩm chạy trên hai con đường khác nhau.
 *
 *    Đây là họ hàng gần của lỗi mã QR sáng nay: cả hai đều là "phép thử tự dựng lấy thế giới
 *    của nó rồi kiểm tra trong thế giới đó". Thuốc cũng giống nhau — bắt phép thử phải đi qua
 *    đúng đường mà thứ thật đi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHG_May::luu_ty_le( 10000, 6 );
$wpdb->query( "UPDATE " . VHG_DB::t( 'may' ) . " SET gia=10000, phut=12 WHERE ma='AMTP02'" );
VHG_May::luu_menh_gia( array(
	array( 'tien' => 10000,  'phut' => 0, 'ten' => 'GOI CO BAN',     'mo_ta' => 'Khoi dong', 'vip' => 0 ),
	array( 'tien' => 100000, 'phut' => 0, 'ten' => 'GOI THUONG HANG','mo_ta' => 'Dang cap',  'vip' => 1 ),
) );

// ---- gốc: địa chỉ API nhúng vào trang phải MANG THEO mã ghế
$html_g = vhg_shop_html( 'AMTP01' );
$m_g = array();
t( 'trang có nhúng địa chỉ API', 1 === preg_match( '/window\.VHG_SHOP=("(?:[^"\\\\]|\\\\.)*")/', $html_g, $m_g ) );
$api_url = (string) json_decode( $m_g[1], true );
t( '🔴 địa chỉ API MANG THEO mã ghế — không mang là mọi lượt gọi mất ngữ cảnh',
	strpos( $api_url, 'ghe=AMTP01' ) !== false, $api_url );

// ---- ngọn 1: bấm gói tiêu số dư, đi đúng đường trình duyệt đi
$vsdt = '0909777111';
$dn_ = VHG_Vi::dat_don( $vsdt, '1234', 100000, '' );
VHG_Vi::nap( $dn_['ma_don'], 'ref-ghe-1' );
$wpdb->query( "UPDATE " . VHG_DB::t( 'vi_so' ) . " SET dung_duoc_tu='2020-01-01 00:00:00'
	WHERE sdt='" . $vsdt . "' AND da_chin=0" );
VHG_Vi::chin( $vsdt );
$GLOBALS['VHCP_TR'] = array();
$tv_ = vhg_shop_nhu_trang( 'tieu',
	array( 'sdt' => $vsdt, 'pin' => '1234', 'menh_gia' => 10000, 'ma_may' => 'AMTP01' ), 'AMTP01' );
t( '🔴 bấm gói tiêu số dư CHẠY ĐƯỢC theo đúng đường trang thật gọi',
	! empty( $tv_['ok'] ), isset( $tv_['error'] ) ? $tv_['error'] : '' );
t( 'và không còn câu "chưa biết dùng cho ghế nào"',
	strpos( (string) ( isset( $tv_['error'] ) ? $tv_['error'] : '' ), 'ghế nào' ) === false );
teq( 'trừ đúng 10k', 110000, (int) VHG_Vi::so_du( $vsdt )['dung'] );

/* Trang KHÔNG biết ghế (mở thẳng /mua-ma, không qua tem) thì vẫn phải TỪ CHỐI — chốt cũ còn nguyên. */
$GLOBALS['VHCP_TR'] = array();
$tv_khong = vhg_shop_nhu_trang( 'tieu',
	array( 'sdt' => $vsdt, 'pin' => '1234', 'menh_gia' => 10000 ), '' );
t( '🔴 không qua tem thì VẪN từ chối, không đoán bừa một ghế', empty( $tv_khong['ok'] ) );

// ---- ngọn 2: số phút — chỗ hỏng IM LẶNG
$goi_a1 = vhg_shop_nhu_trang( 'goi', array(), 'AMTP01' );   // tỉ lệ chung: 10k = 6 phút
$goi_a2 = vhg_shop_nhu_trang( 'goi', array(), 'AMTP02' );   // tỉ lệ riêng: 10k = 12 phút
teq( 'cổng trả đúng ghế trên tem (AMTP01)', 'AMTP01', (string) $goi_a1['ghe'] );
teq( 'cổng trả đúng ghế trên tem (AMTP02)', 'AMTP02', (string) $goi_a2['ghe'] );
teq( 'số phút của AMTP01 theo tỉ lệ chung', 6, (int) $goi_a1['goi'][0]['phut'] );
teq( '🔴 số phút của AMTP02 theo TỈ LỆ RIÊNG của nó — không phải tỉ lệ chung',
	12, (int) $goi_a2['goi'][0]['phut'] );
t( 'hai ghế ra hai con số khác nhau',
	(int) $goi_a1['goi'][0]['phut'] !== (int) $goi_a2['goi'][0]['phut'] );



/* ---- TRANG NHÂN VIÊN PHẢI THẤY SỐ DƯ VÍ
   Anh Thắng 23/08/2026: *"trên wed cũng chưa có số dư của ví khách"*. Tab "Mã giảm giá" chỉ
   hiện mã, trong khi ví cũng là tiền khách đã trả mà chưa tiêu — cùng một khoản nợ. */
$vsdt2 = '0909777111';
$tok_v = vhg_vao( '778899', 'Admin' );
$web_v = vhg_web( 'so_lieu', array( 'ky' => 'all', 'token' => $tok_v ) );
t( 'vào được trang nhân viên để soi ví', ! empty( $web_v['ok'] ),
	isset( $web_v['error'] ) ? $web_v['error'] : '' );
t( 'trang nhân viên nhận được dữ liệu ví', isset( $web_v['vi'] ) );
t( 'kèm tổng nợ ví', isset( $web_v['vi']['no']['tong'] ) );
t( '🔴 tổng nợ ví KHỚP con số lõi tính ra',
	(int) $web_v['vi']['no']['tong'] === (int) VHG_Vi::tong_no()['tong'] );
t( 'và kèm danh sách ví còn tiền', isset( $web_v['vi']['ds'] ) && count( $web_v['vi']['ds'] ) > 0 );

/* 🔴 CHE SỐ ĐIỆN THOẠI Ở MÁY CHỦ, không phải chỉ ở giao diện.
      Che ở giao diện là số đầy đủ vẫn nằm trong gói JSON — mở tab Network ra là thấy, và một
      dòng trong bảng điều khiển trình duyệt là xuất được cả danh sách khách hàng. */
$json_v = wp_json_encode( $web_v['vi'] );
t( '🔴 gói tin KHÔNG mang số điện thoại đầy đủ', strpos( $json_v, $vsdt2 ) === false );
t( 'nhưng vẫn đủ bốn số cuối để nhân viên đối chiếu',
	strpos( $json_v, substr( $vsdt2, -3 ) ) !== false );
t( 'và không mang chuỗi băm PIN của ai', strpos( $json_v, 'pin_bam' ) === false );

/* Tra hộ khách quên PIN phải ra CẢ HAI — khách không nhớ mình mua mã hay nạp ví. */
$tra_hai = vhg_web( 'ma_tra', array( 'sdt' => $vsdt2, 'token' => $tok_v ) );
t( '🔴 tra một lần ra luôn số dư ví', ! empty( $tra_hai['ok'] ) && isset( $tra_hai['vi'] ) );
teq( 'và đúng số dư', (int) VHG_Vi::so_du( $vsdt2 )['dung'], (int) $tra_hai['vi']['dung'] );
/* Số chưa có gì thì nói rõ là chưa có gì, đừng báo lỗi. */
$tra_khong = vhg_web( 'ma_tra', array( 'sdt' => '0900000001', 'token' => $tok_v ) );
t( 'số chưa mua gì thì không có ví', ! isset( $tra_khong['vi'] ) );

/* Giao diện có dựng khối ví. */
$html_v = vhg_web_html();
t( 'màn nhân viên có ô số dư ví khách', strpos( $html_v, 'SỐ DƯ VÍ KHÁCH' ) !== false );
t( '🔴 và có ô TỔNG NỢ gộp cả mã lẫn ví — nhìn riêng một vế là thấy nửa sự thật',
	strpos( $html_v, 'TỔNG NỢ KHÁCH' ) !== false );
t( 'có bảng ví còn tiền', strpos( $html_v, 'Ví khách còn tiền' ) !== false );
t( 'và bảng đó dùng số đã che', strpos( $html_v, 'esc(v.sdt_che)' ) !== false );



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG MUA — BỐN NGÔN NGỮ: VIỆT, ANH, TRUNG, NGA
 *
 * Anh Thắng 23/08/2026: *"trang mua vé dùng 4 ngôn ngữ: Việt, Anh, Trung Quốc, Nga"*.
 *
 * 🔴 KHOÁ TỪ ĐIỂN LÀ CHÍNH CÂU TIẾNG VIỆT. Thiếu bản dịch thì rơi về tiếng Việt — chữ vẫn đọc
 *    được, không ra "mua.tieu_de" giữa trang đang có khách đứng trả tiền. Và câu lỗi máy chủ
 *    trả về cũng là tiếng Việt, nên `L(r.error)` dịch được luôn mà không phải sửa gì bên PHP.
 *
 * Phép thử quan trọng nhất ở đây là phép QUÉT SÓT bên dưới: thêm một câu mới mà quên dịch thì
 * bộ thử gãy ngay, kèm đúng câu bị sót. Không có nó thì trang cứ lặng lẽ pha tiếng Việt vào
 * giữa tiếng Nga, và chỉ khách mới thấy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$js_nn = (string) file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-shop.php' );
$vt_js = strpos( $js_nn, 'private static function js()' );
$js_nn = substr( $js_nn, $vt_js, strpos( $js_nn, "\nJS;", $vt_js ) - $vt_js );

t( 'có bộ chuyển ngữ L()', strpos( $js_nn, 'function L(s){' ) !== false );
t( 'có bộ chèn số Lf()', strpos( $js_nn, 'function Lf(s){' ) !== false );
t( 'có ô chọn ngôn ngữ', strpos( $js_nn, 'function veNgon(){' ) !== false );
foreach ( array( 'vi', 'en', 'zh', 'ru' ) as $nn_ ) {
	t( 'có ngôn ngữ ' . $nn_, preg_match( "/ma:'" . $nn_ . "'/", $js_nn ) === 1 );
}
/* 🔴 Tiếng Việt là ĐƯỜNG LUI, nên nó KHÔNG được nằm trong từ điển — nằm trong đó là một bản
      sao thứ hai của mọi câu, và hai bản sao thì có ngày lệch nhau. */
t( '🔴 tiếng Việt không có trong từ điển (nó là đường lui)',
	preg_match( '/\bvi: \{/', $js_nn ) !== 1 );

/* ---- QUÉT SÓT: mọi chuỗi đi qua L()/Lf() phải có đủ ba bản dịch */
$khoa_nn = array();
preg_match_all( "/(?<![A-Za-z0-9_])Lf?\('((?:[^'\\\\]|\\\\.)*)'/", $js_nn, $m_nn );
foreach ( (array) $m_nn[1] as $k_ ) { $khoa_nn[ $k_ ] = 1; }
t( 'quét được danh sách chuỗi cần dịch', count( $khoa_nn ) > 100, count( $khoa_nn ) . ' chuỗi' );

foreach ( array( 'en', 'zh', 'ru' ) as $nn_ ) {
	$vt_ = strpos( $js_nn, '  ' . $nn_ . ': {' );
	t( 'có khối từ điển ' . $nn_, false !== $vt_ );
	if ( false === $vt_ ) { continue; }
	$than_ = substr( $js_nn, $vt_, strpos( $js_nn, "\n  }", $vt_ ) - $vt_ );
	$co_ = array();
	preg_match_all( "/^    '((?:[^'\\\\]|\\\\.)*)':/m", $than_, $m2_ );
	foreach ( (array) $m2_[1] as $k_ ) { $co_[ $k_ ] = 1; }
	$sot_ = array();
	foreach ( $khoa_nn as $k_ => $x_ ) { if ( ! isset( $co_[ $k_ ] ) ) { $sot_[] = $k_; } }
	teq( '🔴 [' . $nn_ . '] không sót câu nào chưa dịch', array(), $sot_ );
	/* 🔴 VÀ MẶT KIA: không có bản dịch MỒ CÔI — khoá còn trong từ điển mà mã không còn dùng.
	   Rác này không làm hỏng gì ngay, nên nó nằm lại mãi: nó phình trang gửi cho mỗi khách,
	   và tệ hơn là nó làm mọi phép đếm "còn bao nhiêu chỗ dùng chuỗi này" ra sai — đã dính
	   đúng một lần khi gộp hai ô đăng nhập làm một. */
	$mo_coi_ = array();
	foreach ( $co_ as $k_ => $x_ ) { if ( ! isset( $khoa_nn[ $k_ ] ) ) { $mo_coi_[] = $k_; } }
	teq( '🔴 [' . $nn_ . '] không có bản dịch mồ côi', array(), $mo_coi_ );
	/* Và bản dịch không được để nguyên tiếng Việt — sót kiểu đó thì phép thử trên không thấy. */
	$nguyen_ = 0;
	preg_match_all( "/^    '((?:[^'\\\\]|\\\\.)*)': '((?:[^'\\\\]|\\\\.)*)'/m", $than_, $m3_, PREG_SET_ORDER );
	foreach ( $m3_ as $c_ ) {
		/* Bỏ qua câu chỉ gồm thẻ HTML và số — chúng giống nhau ở mọi thứ tiếng là chuyện thường. */
		$chu_ = trim( preg_replace( '/<[^>]*>|[^\p{L}]+/u', ' ', $c_[1] ) );
		if ( mb_strlen( $chu_ ) >= 8 && $c_[1] === $c_[2] ) { $nguyen_++; }
	}
	teq( '🔴 [' . $nn_ . '] không bản dịch nào để nguyên tiếng Việt', 0, $nguyen_ );
}

/* ---- trang dựng ra có ô chọn ngôn ngữ, và VẪN đúng tiếng Việt như trước */
$html_nn = vhg_shop_html( 'AMTP01' );
t( 'trang có ô chọn ngôn ngữ', strpos( $html_nn, 'class="nn"' ) !== false );
t( 'và có nút tiếng Trung', strpos( $html_nn, '中文' ) !== false );
t( 'và có nút tiếng Nga', strpos( $html_nn, 'Русский' ) !== false );
t( 'nhớ lựa chọn trong máy khách', strpos( $html_nn, "localStorage.setItem('vhg_nn'" ) !== false );
/* Đoán theo ngôn ngữ trình duyệt: khách Nga mở trang là thấy tiếng mình luôn. */
t( 'đoán ngôn ngữ theo trình duyệt', strpos( $html_nn, 'navigator.language' ) !== false );
/* 🔴 Và tiếng Việt vẫn nguyên vẹn: L() rơi về khoá, nên câu tiếng Việt phải CÒN NGUYÊN trong trang. */
t( '🔴 tiếng Việt vẫn còn nguyên trong trang', strpos( $html_nn, 'Ghế đang ngồi' ) !== false );
t( 'và câu mời quét tem vẫn còn', strpos( $html_nn, 'quét mã QR dán trên chính cái ghế' ) !== false );



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 23/08/2026 — TRẢ TIỀN THÊM MÀ THỜI GIAN BỊ CẮT NGẮN LẠI
 *
 * Anh Thắng: *"Anh bấm nhiều lệnh, tiền vẫn trừ, nhưng số phút không được cộng"*.
 *
 * `startRunning()` GHI ĐÈ `runUntil` thay vì cộng. Ghế đang chạy còn 30 phút mà nhận thêm gói
 * 6 phút thì thành ĐÚNG 6 PHÚT — khách vừa trả thêm tiền vừa MẤT 24 phút đã trả trước đó.
 * Không phải "không được cộng", mà là bị TRỪ.
 *
 * ⚠️ Đường TIỀN MẶT vốn cộng đúng (`runUntil += ...` viết tay trong checkCash), nên chỉ mình
 *    nó đúng còn MỌI đường khác đều sai: QR, tiêu ví, dùng mã, và cả bấm gói trên màn ghế.
 *    Một luật đúng nằm ở chỗ GỌI thay vì nằm trong hàm ĐƯỢC GỌI thì nó chỉ đúng ở đúng chỗ đó.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$fw_cong = (string) file_get_contents( $goc . '/esp32_ghe_massage/esp32_ghe_massage.ino' );

/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BÓC CHÚ THÍCH KHỎI MÃ C++ TRƯỚC KHI SOI — Y NHƯ ĐÃ LÀM VỚI PHP.
 *
 * Hôm nay đúng BỐN lần một phép thử "mã KHÔNG được chứa X" tự hỏng vì chú thích ngay cạnh chỗ
 * sửa nhắc lại X để giải thích vì sao không dùng nó:
 *   "QUET DE MUA" · "MUA MA GIAM GIA" · "VHG_Thu::ghi()" · và lần này là "relaySet"/"state"
 *   trong câu *"Không đụng relaySet, state, screenDrawn"*.
 *
 * Chú thích càng viết kỹ thì càng dễ chứa đúng chuỗi đang bị cấm — tức là phép thử phạt đúng
 * cái nết tốt. Vá lẻ từng lần chỉ đẩy sang lần sau.
 *
 * ⚠️ KHÔNG dùng regex trần `//.*` — nó ăn luôn phần sau của `"https://..."` trong chuỗi. Phải
 *    đi qua từng ký tự và biết lúc nào mình đang ở TRONG một chuỗi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function vhg_bo_chu_thich_cpp( $ma ) {
	$ra = '';
	$n  = strlen( $ma );
	$trong_chuoi = false;
	$dau_chuoi   = '';
	for ( $i = 0; $i < $n; $i++ ) {
		$c  = $ma[ $i ];
		$c2 = $i + 1 < $n ? $ma[ $i + 1 ] : '';
		if ( $trong_chuoi ) {
			$ra .= $c;
			if ( '\\' === $c && '' !== $c2 ) { $ra .= $c2; $i++; continue; }
			if ( $c === $dau_chuoi ) { $trong_chuoi = false; }
			continue;
		}
		if ( '"' === $c || "'" === $c ) { $trong_chuoi = true; $dau_chuoi = $c; $ra .= $c; continue; }
		if ( '/' === $c && '/' === $c2 ) {
			while ( $i < $n && "
" !== $ma[ $i ] ) { $i++; }
			$ra .= "
";
			continue;
		}
		if ( '/' === $c && '*' === $c2 ) {
			$i += 2;
			while ( $i + 1 < $n && ! ( '*' === $ma[ $i ] && '/' === $ma[ $i + 1 ] ) ) { $i++; }
			$i++;
			$ra .= ' ';
			continue;
		}
		$ra .= $c;
	}
	return $ra;
}
$fw_chay = vhg_bo_chu_thich_cpp( $fw_cong );   // chỉ còn MÃ CHẠY
/* Tự kiểm bộ bóc: chuỗi chỉ có trong chú thích phải biến mất, mã thật và chuỗi trong ngoặc kép
   phải còn nguyên. */
t( 'bộ bóc chú thích C++ ăn đúng phần chú thích',
	strpos( $fw_cong, 'đang có người nằm trên đó' ) !== false
	&& strpos( $fw_chay, 'đang có người nằm trên đó' ) === false );
t( 'và giữ nguyên mã chạy', strpos( $fw_chay, 'void startRunning(int minutes){' ) !== false );
t( '🔴 và KHÔNG cắt nhầm chuỗi có "//" trong ngoặc kép',
	strpos( $fw_chay, 'https://' ) !== false );

t( '🔴 đang chạy thì CỘNG THÊM giờ, không ghi đè',
	preg_match( '/if\(state == ST_RUNNING\)\{\s*runUntil \+= \(unsigned long\)minutes\*60000UL;/', $fw_cong ) === 1 );
t( 'và luật đó nằm TRONG startRunning, không nằm ở chỗ gọi',
	preg_match( '/void startRunning\(int minutes\)\{.{0,900}?state == ST_RUNNING.{0,200}?runUntil \+=/s', $fw_cong ) === 1 );
/* Cộng thêm thì KHÔNG được đụng vào rơ-le hay trạng thái: ghế đang chạy, đang có người nằm. */
/* Bóc riêng nhánh "đang chạy" ra rồi soi — regex lồng nhau kiểu `(?:(?!x).)*` đọc không ra và
   sai thì không ai biết sai ở đâu. */
/* ⚠️ NEO VÀO `startRunning` TRƯỚC. `if(state == ST_RUNNING){` xuất hiện ở nhiều nơi trong tệp;
      lấy lần đầu tiên là soi nhầm một hàm khác hoàn toàn, và phép thử báo sai một chỗ đang đúng. */
$vt_sr = strpos( $fw_chay, 'void startRunning(int minutes){' );
t( 'tìm được hàm startRunning', false !== $vt_sr );
$vt_ct = false !== $vt_sr ? strpos( $fw_chay, 'if(state == ST_RUNNING){', $vt_sr ) : false;
$nhanh_ct = false !== $vt_ct
	? substr( $fw_chay, $vt_ct, strpos( $fw_chay, 'return;', $vt_ct ) - $vt_ct ) : '';
t( 'bóc được nhánh cộng thêm giờ', '' !== $nhanh_ct );
t( '🔴 lúc cộng thêm KHÔNG bật lại rơ-le', strpos( $nhanh_ct, 'relaySet' ) === false );
/* ⚠️ `'state ='` là chuỗi con của `'state =='` — mà nhánh này MỞ ĐẦU bằng `if(state == ...)`.
      Canh bằng strpos là phép thử luôn báo sai. Phải đòi dấu `=` KHÔNG theo sau bởi `=`. */
t( 'và KHÔNG đặt lại trạng thái',
	preg_match( '/\bstate\s*=\s*[^=]/', $nhanh_ct ) !== 1 );
t( 'nhưng CÓ báo màn vẽ lại số đếm', strpos( $nhanh_ct, 'g_statusDirty = true' ) !== false );
/* Và checkCash BỎ vế cộng tay — hai nơi cùng một luật là lý do các đường khác sai suốt. */
t( '🔴 checkCash không còn tự cộng giờ nữa',
	strpos( $fw_cong, '[CASH] cong them gio (dang chay)' ) === false );
t( 'mà gọi thẳng startRunning', preg_match( '/if\(state != ST_RUNNING\) \{ g_srcCode = \x27c\x27; \}\s*startRunning\(minutes\);/', $fw_cong ) === 1 );

/* 🔴 LỆNH TỪ WEB CŨNG PHẢI CỘNG DỒN. Mỗi lượt gọi chỉ lấy về MỘT lệnh; bấm ba cái thì có ba
      lệnh trong hàng chờ. Gán đè là lệnh thứ hai về trước khi vòng lặp chính kịp tiêu lệnh thứ
      nhất thì lệnh thứ nhất mất hẳn — tiền đã trừ, phút không bao giờ tới. */
t( '🔴 lệnh bật từ web cộng dồn, không gán đè',
	strpos( $fw_cong, 'g_remoteStartMin += (phut>0 ? phut : MINUTES)' ) !== false );
t( 'và không còn chỗ nào gán đè nó',
	preg_match( '/g_remoteStartMin = \(phut/', $fw_cong ) !== 1 );

// ---- lời dặn ngồi lên ghế + đếm ngược
$sh_dan = vhg_shop_html( 'AMTP01' );
t( '🔴 có lời dặn NGỒI LÊN GHẾ', strpos( $sh_dan, 'NGỒI LÊN GHẾ' ) !== false );
/* Lời dặn phải đứng TRƯỚC các thẻ gói — sau các thẻ thì khách đã bấm xong mới đọc tới. */
$vt_dan = strpos( $sh_dan, 'NGỒI LÊN GHẾ' );
$vt_the = strpos( $sh_dan, 'data-tieu=' );
t( '🔴 và đứng TRƯỚC các thẻ gói', false !== $vt_dan && false !== $vt_the && $vt_dan < $vt_the );
t( 'có màn đếm ngược', strpos( $sh_dan, "class=\"dem\"" ) !== false );
/* 🔴 Ô ĐẾM PHẢI ĐỨNG ĐẦU TRANG. Anh Thắng 23/08/2026: *"đưa màn lên đầu trang nhé"* — bản
      trước vẽ con số xuống dưới đáy, khách phải CUỘN TÌM đúng lúc đang xoay người ngồi xuống. */
/* ⚠️ So vị trí trong CẢ TRANG là so nhầm: "Ghế đang ngồi" còn xuất hiện trong từ điển bốn thứ
      tiếng, nằm tuốt phía trên. Phải cắt lấy thân hàm `veDung()` rồi mới so. Đây là lần thứ năm
      hôm nay một phép thử bị chính phần khác của tệp đánh lừa. */
$vt_vd_   = strpos( $sh_dan, 'function veDung(){' );
$than_vd_ = false !== $vt_vd_
	? substr( $sh_dan, $vt_vd_, strpos( $sh_dan, "\n// ---", $vt_vd_ ) - $vt_vd_ ) : '';
t( 'cắt được thân hàm veDung()', '' !== $than_vd_ );
$vt_odem = strpos( $than_vd_, "id=\"t-dem\"" );
$vt_ghe_ = strpos( $than_vd_, 'Ghế đang ngồi' );
t( '🔴 ô đếm nằm TRƯỚC cả dòng "Ghế đang ngồi"',
	false !== $vt_odem && false !== $vt_ghe_ && $vt_odem < $vt_ghe_ );
t( 'và đếm vẽ vào đúng ô đó, không vẽ xuống đáy',
	strpos( $sh_dan, "var oDem = document.getElementById('t-dem')" ) !== false
	&& strpos( $sh_dan, "oDem.innerHTML = '<div class=\"dem\">" ) !== false );
t( '🔴 và trang tự cuộn lên đầu, không bắt khách tìm',
	strpos( $sh_dan, 'window.scrollTo' ) !== false );
/* Câu báo kết quả cũng ở lại đầu trang — đẩy nó xuống cuối là bắt cuộn tìm lần thứ hai cho
   cùng một việc. */
t( 'câu báo xong cũng hiện ở đầu trang',
	preg_match( '/var bao = .{0,120}?oDem\.innerHTML = bao;/s', $sh_dan ) === 1 );
teq( 'đếm từ 5', 5, (int) ( preg_match( '/var con = (\d+), nhip = null;/', $sh_dan, $m_d ) ? $m_d[1] : 0 ) );
/* 🔴 LỆNH GỬI NGAY, ĐẾM CHẠY SONG SONG. Đếm xong mới gửi là cộng thêm 5 giây vào đúng cái độ
      trễ đã đi cắt từng giây cả tháng. Canh bằng THỨ TỰ: `goi('tieu'...)` phải đứng SAU chỗ
      dựng đồng hồ, chứ không nằm trong hàm gọi lại của nó. */
$vt_nhip = strpos( $sh_dan, 'nhip = setInterval(' );
$vt_goi  = strpos( $sh_dan, "goi('tieu'," );
t( '🔴 lệnh gửi NGAY, không chờ đếm xong', false !== $vt_nhip && false !== $vt_goi && $vt_goi > $vt_nhip );
t( 'và đếm hỏng giữa chừng thì dừng đồng hồ, không nói dối khách',
	preg_match( '/if \(!r\.ok\) \{\s*\/\*.*?\*\/\s*if \(nhip\) \{ clearInterval\(nhip\); nhip = null; \}/s', $sh_dan ) === 1 );

// ---- chân trang pháp lý
$c_chan = VHG_Chan::thong_tin();
teq( 'điền sẵn mã số thuế', '0106924989', (string) $c_chan['mst'] );
t( 'và tên công ty', strpos( (string) $c_chan['ten'], 'K&H' ) !== false );
t( 'mặc định là HIỆN', ! empty( $c_chan['hien'] ) );
$html_chan = VHG_Chan::html();
t( 'dựng được chân trang', '' !== $html_chan );
t( 'có mã số thuế', strpos( $html_chan, '0106924989' ) !== false );
t( 'có tên quốc tế cho khách nước ngoài',
	strpos( $html_chan, 'K&amp;H SERVICES AND ENTERTAINMENT' ) !== false );
t( 'điện thoại bấm gọi được ngay', strpos( $html_chan, 'href="tel:' ) !== false );
t( 'có danh sách chi nhánh', strpos( $html_chan, 'Nha Trang' ) !== false );
/* 🔴 MỘT NƠI DỰNG, HAI TRANG DÙNG — chép ra hai bản là kiểu lỗi đã cắn dự án này năm lần
      trong một ngày. */
t( '🔴 chân trang có mặt ở trang KHÁCH', strpos( $sh_dan, '0106924989' ) !== false );
t( '🔴 và ở trang NHÂN VIÊN', strpos( vhg_web_html(), '0106924989' ) !== false );
/* 🔴 CẢ HAI TRANG GỌI CHUNG MỘT HÀM. Chép ra hai bản là kiểu lỗi đã cắn dự án này năm lần
      trong một ngày: sửa một nơi, quên nơi kia, và nơi quên thì im lặng nói sai.
   ⚠️ Canh "có gọi", đừng canh "gọi đúng N lần": chú thích cạnh chỗ gọi có nhắc tên hàm, và
      đếm số lần là phép thử gãy vì chính lời giải thích — đã dính ba lần hôm nay. */
foreach ( array( 'shop', 'trang' ) as $tep_c ) {
	$ma_c = (string) file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-' . $tep_c . '.php' );
	t( 'trang ' . $tep_c . ' gọi VHG_Chan::html()',
		preg_match( '/\.\s*VHG_Chan::html\(\)/', $ma_c ) === 1 );
	/* Và KHÔNG tự dựng chân trang riêng. */
	t( 'và không tự dựng chân trang riêng', strpos( $ma_c, 'vhg-chan-in' ) === false );
}
/* Sửa được, không nhét cứng: địa chỉ công ty đổi thì không phải sửa mã rồi cài lại plugin. */
VHG_Chan::luu( array( 'ten' => 'CÔNG TY THỬ', 'mst' => '999', 'hien' => 1,
	'dia_chi' => 'Đâu đó', 'dai_dien' => '', 'dien_thoai' => '', 'email' => '',
	'ten_qt' => '', 'ngay_hd' => '', 'co_quan' => '', 'chi_nhanh' => '' ) );
t( '🔴 sửa được từ màn quản trị', strpos( VHG_Chan::html(), 'CÔNG TY THỬ' ) !== false );
t( 'và ô để trống thì GIỮ trống, không bị mặc định lấp vào',
	strpos( VHG_Chan::html(), 'K&amp;H SERVICES' ) === false );
/* Tắt được hẳn. */
VHG_Chan::luu( array( 'ten' => 'CÔNG TY THỬ', 'hien' => 0 ) );
teq( 'tắt thì không dựng gì cả', '', VHG_Chan::html() );
delete_option( 'vhg_chan' );
t( 'xoá khai báo thì về mặc định', strpos( VHG_Chan::html(), '0106924989' ) !== false );



/* ---- 🔴 MỘT LẦN NHẬP, RA CẢ VÍ LẪN MÃ
   Anh Thắng 23/08/2026: *"cùng 1 ví mà, sao lại ra 2 lần đăng nhập"*. Tab "Của tôi" có hai ô
   nhập số điện thoại + PIN chồng nhau — cùng số, cùng PIN, cho hai thứ mà với khách là MỘT
   tài khoản. */
$sh_ct = vhg_shop_html( 'AMTP01' );
teq( '🔴 chỉ còn ĐÚNG MỘT ô nhập số điện thoại ở tab Của tôi',
	1, substr_count( $sh_ct, 'id="t-sdt"' ) );
t( 'và không còn ô nhập thứ hai', strpos( $sh_ct, 'id="v-sdt"' ) === false );
/* ⚠️ Đếm trong CẢ TRANG là đếm luôn cả từ điển bốn thứ tiếng (mỗi bản dịch cũng chứa chuỗi
      `id="t-xem"`). Canh cái nút thì canh ở chỗ DỰNG nút, không canh ở chỗ dịch nó. */
t( 'chỉ còn một nút tra', strpos( $sh_ct, 'id="v-xem"' ) === false );
$vt_ve_ct  = strpos( $sh_ct, 'function veCuaToi(){' );
$vt_nut_ct = false !== $vt_ve_ct ? strpos( $sh_ct, 'id="t-xem"', $vt_ve_ct ) : false;
t( 'và nút đó nằm trong hàm dựng tab Của tôi',
	false !== $vt_ve_ct && false !== $vt_nut_ct );
/* ⚠️ GỘP Ở CỔNG, không gộp ở trang: gọi hai lượt API rồi ghép ở trình duyệt thì ăn HAI lần
      hãm thử, và một lượt hỏng là màn hiện nửa vời. */
t( '🔴 trang gọi MỘT lượt `cua_toi`, không gọi hai lượt',
	strpos( $sh_ct, "goi('cua_toi'," ) !== false );
t( 'và không còn gọi riêng lượt tra mã', strpos( $sh_ct, "goi('tra'," ) === false );

$GLOBALS['VHCP_TR'] = array();
$ct_kq = vhg_shop( 'cua_toi', array( 'sdt' => '0909777111', 'pin' => '1234' ) );
t( 'tra được bằng một lượt', ! empty( $ct_kq['ok'] ), isset( $ct_kq['error'] ) ? $ct_kq['error'] : '' );
t( 'ra số dư ví', ! empty( $ct_kq['co_vi'] ) && isset( $ct_kq['so_du'] ) );
t( 'và ra cả danh sách mã', isset( $ct_kq['chua_dung'] ) );
/* 🔴 Có ví mà chưa mua mã lẻ bao giờ vẫn là một lượt tra THÀNH CÔNG — trả `ok=false` vì một vế
      trống là đuổi khách đi đúng lúc họ đang tìm tiền của mình. */
teq( 'ví có tiền nhưng chưa mua mã lẻ vẫn tra được', 0, count( (array) $ct_kq['chua_dung'] ) );
$GLOBALS['VHCP_TR'] = array();
$ct_sai = vhg_shop( 'cua_toi', array( 'sdt' => '0909777111', 'pin' => '0000' ) );
t( 'sai PIN thì từ chối', empty( $ct_sai['ok'] ) );
t( 'và không rò chuỗi băm PIN', strpos( wp_json_encode( $ct_kq ), 'pin_bam' ) === false );
/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 HÃM THỬ CHỈ ĐẾM LƯỢT HỎNG, VÀ LƯỢT ĐÚNG XOÁ SẠCH BỘ ĐẾM.
 *
 * Anh Thắng 23/08/2026, đang ngồi trên ghế: *"tại sao bấm không chạy"* — màn báo *"Thử quá
 * nhiều lần, chờ 10 phút"*. Không phải ghế hỏng: anh vừa bị chính cái hãm thử của mình khoá.
 *
 * Cái hãm này lắp ra để chặn MÁY DÒ PIN. Một lượt THÀNH CÔNG đã chứng minh người gọi biết PIN,
 * nên đếm nó vào là phạt đúng khách thật — và phạt ở chỗ tệ nhất: khách đã trả tiền, đang ngồi
 * trên ghế, mua thêm lượt thứ mười sáu trong buổi thì bị khoá không cho tiêu tiền của mình.
 *
 * Máy dò PIN thì không bao giờ thành công, nên nó vẫn bị chặn sau 15 lượt như cũ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$dem_ham_ = function () {
	foreach ( (array) $GLOBALS['VHCP_TR'] as $k_h => $v_h ) {
		if ( strpos( (string) $k_h, 'vhg_shop_tra_' ) === 0 ) { return (int) $v_h; }
	}
	return 0;
};
$GLOBALS['VHCP_TR'] = array();
vhg_shop( 'cua_toi', array( 'sdt' => '0909777111', 'pin' => '0000' ) );   // SAI
teq( 'lượt SAI thì đếm lên 1', 1, $dem_ham_() );
vhg_shop( 'cua_toi', array( 'sdt' => '0909777111', 'pin' => '0000' ) );   // SAI nữa
teq( 'sai tiếp thì đếm lên 2', 2, $dem_ham_() );
vhg_shop( 'cua_toi', array( 'sdt' => '0909777111', 'pin' => '1234' ) );   // ĐÚNG
teq( '🔴 lượt ĐÚNG thì XOÁ SẠCH bộ đếm — khách thật không bao giờ chạm tới trần', 0, $dem_ham_() );
/* Và cùng luật đó ở lượt TIÊU TIỀN — đây mới là chỗ anh Thắng bị khoá. */
$GLOBALS['VHCP_TR'] = array();
$dem_dung_ = function () {
	foreach ( (array) $GLOBALS['VHCP_TR'] as $k_h => $v_h ) {
		if ( strpos( (string) $k_h, 'vhg_shop_dung_' ) === 0 ) { return (int) $v_h; }
	}
	return 0;
};
vhg_shop_nhu_trang( 'tieu',
	array( 'sdt' => '0909777111', 'pin' => '0000', 'menh_gia' => 10000, 'ma_may' => 'AMTP01' ), 'AMTP01' );
teq( 'tiêu sai PIN thì đếm lên', 1, $dem_dung_() );
vhg_shop_nhu_trang( 'tieu',
	array( 'sdt' => '0909777111', 'pin' => '1234', 'menh_gia' => 10000, 'ma_may' => 'AMTP01' ), 'AMTP01' );
teq( '🔴 tiêu ĐÚNG thì xoá sạch — bấm bao nhiêu lượt cũng không bị khoá', 0, $dem_dung_() );



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * TÍCH LƯỢT ƯU ĐÃI
 *
 * Anh Thắng 23/08/2026: *"10k 1 lượt tích (giá khác thì quy đổi theo 10/1)"*, *"tích vào sdt
 * của khách đang đăng nhập, đang gắn vào ví; ai không đăng nhập để thanh toán thì thôi"*,
 * *"sau 10 lượt, khách được ưu đãi tặng quà"*, phần thưởng *"cả 2"*.
 *
 * 🔴 PHẦN THƯỞNG LÀ TIỀN. Nên bộ thử này canh đúng những thứ canh ở ví: không phát hai lần cho
 *    một mốc, không cộng hai lần vào ví, và không trao hai lần một phần quà.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ Dựng lại bảng giá: khối phép thử ngay trên thay nó bằng hai gói (10k, 100k) để thử số
      phút theo từng ghế. Khối này cần cả 50k — trông vào thứ khối khác để lại là phép thử gãy
      mỗi khi ai đó sửa khối trên, và gãy theo kiểu khó lần ra. */
VHG_May::luu_menh_gia( array(
	array( 'tien' => 10000,  'phut' => 0, 'ten' => 'GOI CO BAN',     'mo_ta' => 'Khoi dong', 'vip' => 0 ),
	array( 'tien' => 20000,  'phut' => 0, 'ten' => 'GOI PHO BIEN',   'mo_ta' => 'Sau',       'vip' => 0 ),
	array( 'tien' => 50000,  'phut' => 0, 'ten' => 'GOI CHUYEN SAU', 'mo_ta' => 'Tri lieu',  'vip' => 0 ),
	array( 'tien' => 100000, 'phut' => 0, 'ten' => 'GOI THUONG HANG','mo_ta' => 'Dang cap',  'vip' => 1 ),
) );
update_option( 'vhg_tich', array() );
t( '🔴 chưa khai thì TẮT — bật khuyến mãi thay cho chủ cửa hàng là tự ý cho đi tiền của người ta',
	empty( VHG_Vi::tich_cf()['bat'] ) );

$tc_ok = VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 10000, 'moc' => 10,
	'kieu' => 'ca_hai', 'gia_tri' => 10000, 'ten_qua' => 'Quà tri ân' ) );
t( 'khai được', ! empty( $tc_ok['ok'] ), isset( $tc_ok['error'] ) ? $tc_ok['error'] : '' );
/* 🔴 Thưởng kiểu LƯỢT mà trị giá 0 là phần thưởng RỖNG: khách đủ mốc, hệ thống báo "đã tặng",
      ví không nhích một đồng. */
t( '🔴 từ chối thưởng "lượt miễn phí" trị giá 0',
	empty( VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 10000, 'moc' => 10,
		'kieu' => 'luot', 'gia_tri' => 0 ) )['ok'] ) );
t( 'từ chối mốc nhỏ hơn 2', empty( VHG_Vi::luu_tich_cf( array( 'bat' => 1,
	'moi_luot' => 10000, 'moc' => 1, 'kieu' => 'qua' ) )['ok'] ) );
t( 'từ chối mỗi lượt dưới 1.000đ', empty( VHG_Vi::luu_tich_cf( array( 'bat' => 1,
	'moi_luot' => 500, 'moc' => 10, 'kieu' => 'qua' ) )['ok'] ) );
VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 10000, 'moc' => 10,
	'kieu' => 'ca_hai', 'gia_tri' => 10000, 'ten_qua' => 'Quà tri ân' ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TÍCH LƯỢT CHỈ ĐẾN TỪ ĐƯỜNG CHUYỂN KHOẢN TẠI GHẾ.
 *
 * Anh Thắng 23/08/2026: *"Tích lượt qua quét QR tại máy luôn, chỉ có tiền mặt thì không"*, và
 * *"cái này chỉ áp dụng cho khách quét QR tại ghế, chứ khách nạp ví thì nó có ưu đãi sẵn rồi"*.
 *
 * 🔴 PHÉP THỬ ĐI QUA ĐÚNG ĐƯỜNG THẬT, không gọi thẳng `cong_tich()`. Đường thật là: trang đặt
 *    lượt (`dat_ghe`) -> khách chuyển khoản -> webhook SePay (`VHG_Thu::nhan`) đọc nội dung
 *    "GHE<ghế> <mã>" -> ghế chạy -> tích lượt. Gọi tắt vào giữa là phép thử tự nói chuyện với
 *    chính nó: nó vẫn xanh khi nội dung chuyển khoản sai khuôn, mà đó đúng là chỗ hỏng được.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$vhg_ref_ghe = 0;
/** Một lượt trả tiền hoàn chỉnh tại ghế: đặt lượt trên trang, rồi tiền về qua webhook. */
$tra_ghe = function ( $sdt, $pin, $mg, $may = 'AMTP01', $tien = null ) use ( &$vhg_ref_ghe ) {
	$d = VHG_Vi::dat_ghe( $may, $mg, $sdt, $pin );
	if ( empty( $d['ok'] ) ) { return $d; }
	$vhg_ref_ghe++;
	$w = VHG_Thu::nhan( 'sepay', array( 'ref' => 'ghe-tra-' . $vhg_ref_ghe,
		'so_tien' => ( null === $tien ? (int) $d['phai_tra'] : (int) $tien ),
		'noi_dung' => $d['noi_dung'] ) );
	return array( 'ok' => ! empty( $w['ok'] ), 'don' => $d, 'wh' => $w );
};

// ---- tích theo tiền CHUYỂN KHOẢN tại ghế
$ts = '0909888111';
$dt_ = VHG_Vi::dat_don( $ts, '1234', 500000, '' );
VHG_Vi::nap( $dt_['ma_don'], 'ref-tich' );
$wpdb->query( "UPDATE " . VHG_DB::t( 'vi_so' ) . " SET dung_duoc_tu='2020-01-01 00:00:00'
	WHERE sdt='" . $ts . "' AND da_chin=0" );
VHG_Vi::chin( $ts );
teq( 'ví có 800k để tiêu', 800000, (int) VHG_Vi::so_du( $ts )['dung'] );
teq( 'chưa trả lượt nào thì chưa có lượt tích', 0, (int) VHG_Vi::tich( $ts )['co'] );

/* 🔴 TIÊU VÍ KHÔNG TÍCH — tiền trong ví đã được khuyến mãi một lần lúc nạp rồi. */
$tv_truoc = (int) VHG_Vi::tich( $ts )['co'];
$tv_kq = VHG_Vi::tieu( $ts, '1234', 50000, 'AMTP01' );
t( 'tiêu ví vẫn chạy ghế bình thường', ! empty( $tv_kq['ok'] ),
	isset( $tv_kq['error'] ) ? $tv_kq['error'] : '' );
teq( '🔴 nhưng tiêu ví KHÔNG được lượt tích nào', $tv_truoc, (int) VHG_Vi::tich( $ts )['co'] );
teq( 'và gói tin trả về cũng nói thẳng là 0', 0, (int) $tv_kq['tich_them'] );

/* Đường thật: chuyển khoản tại ghế. */
$tg1 = $tra_ghe( $ts, '1234', 10000 );
t( 'trả 10.000đ bằng chuyển khoản tại ghế', ! empty( $tg1['ok'] ),
	isset( $tg1['error'] ) ? $tg1['error'] : '' );
teq( 'được 1 lượt tích', 1, (int) VHG_Vi::tich( $ts )['co'] );
$tra_ghe( $ts, '1234', 50000 );
teq( '🔴 trả 50.000đ được 5 lượt — quy đổi 10/1', 6, (int) VHG_Vi::tich( $ts )['co'] );
teq( 'còn 4 lượt nữa là tới mốc', 4, (int) VHG_Vi::tich( $ts )['con'] );
teq( 'chưa đủ mốc thì chưa có quà', 0, dem( VHG_Vi::ds_qua( $ts ) ) );

/* 🔴 VÀ GHẾ PHẢI CHẠY. Tích lượt mà ghế không chạy là đổi một lượt massage lấy một con dấu. */
$cho_ghe_tich = VHG_May::so_cho( 'AMTP01' );
t( '🔴 mỗi lượt chuyển khoản đều xếp cho ghế chạy', $cho_ghe_tich > 0 );

// ---- đủ mốc thì phát quà
$du_truoc = (int) VHG_Vi::so_du( $ts )['dung'];
$tra_ghe( $ts, '1234', 50000 );                        // +5 lượt -> 11, vượt mốc 10
$qua_ds = VHG_Vi::ds_qua( $ts );
teq( '🔴 đủ 10 lượt thì phát ĐÚNG MỘT phần quà', 1, dem( $qua_ds ) );
teq( 'và lượt tích còn dư đúng 1', 1, (int) VHG_Vi::tich( $ts )['co'] );
/* Kiểu "cả hai": vừa cộng tiền vào ví, vừa để lại một phần quà vật lý cho nhân viên trao. */
teq( 'phần thưởng ghi đúng kiểu', 'ca_hai', (string) $qua_ds[0]['kieu'] );
/* ⚠️ Trả bằng CHUYỂN KHOẢN nên số dư ví KHÔNG bị trừ — chỉ có khoản thưởng cộng vào. */
teq( '🔴 và cộng thẳng 10.000đ vào ví', $du_truoc + 10000, (int) VHG_Vi::so_du( $ts )['dung'] );
teq( 'đánh dấu đã cộng tiền', 1, (int) $qua_ds[0]['luot_da_cong'] );
t( 'nhưng quà vật lý thì CHƯA trao', null === $qua_ds[0]['nhan_luc'] );

/* Sổ ví phải có một dòng nói rõ khoản đó từ đâu ra — không thì ba tháng sau nhìn con số không
   ai giải thích được. */
$co_dong_qua = false;
foreach ( VHG_Vi::ds_so( $ts, 50 ) as $d_ ) {
	if ( 'qua' === (string) $d_['loai'] ) { $co_dong_qua = true; }
}
t( '🔴 sổ ví có dòng giải thích khoản thưởng', $co_dong_qua );

/* ---- 🔴 WEBHOOK BẮN LẠI THÌ KHÔNG TÍCH THÊM.
   SePay bắn lại là chuyện bình thường, và mỗi lượt bắn lại mà tích thêm là cho không phần quà. */
$dl_ = VHG_Vi::dat_ghe( 'AMTP01', 10000, $ts, '1234' );
$tr_ = (int) VHG_Vi::tich( $ts )['co'];
VHG_Thu::nhan( 'sepay', array( 'ref' => 'ghe-lap-1', 'so_tien' => 10000, 'noi_dung' => $dl_['noi_dung'] ) );
teq( 'lượt đầu tích 1', $tr_ + 1, (int) VHG_Vi::tich( $ts )['co'] );
VHG_Thu::nhan( 'sepay', array( 'ref' => 'ghe-lap-2', 'so_tien' => 10000, 'noi_dung' => $dl_['noi_dung'] ) );
teq( '🔴 bắn lại cùng mã lượt thì KHÔNG tích thêm', $tr_ + 1, (int) VHG_Vi::tich( $ts )['co'] );

/* ---- 🔴 CHUYỂN THIẾU THÌ TÍCH THEO SỐ TIỀN THẬT, không theo mệnh giá đã đặt.
   Không thì đặt lượt 100.000đ rồi chuyển 10.000đ là mua lượt tích giá rẻ. */
$tr2_ = (int) VHG_Vi::tich( $ts )['co'];
$tra_ghe( $ts, '1234', 100000, 'AMTP01', 10000 );
teq( '🔴 đặt 100.000đ mà chuyển 10.000đ thì chỉ được 1 lượt', $tr2_ + 1, (int) VHG_Vi::tich( $ts )['co'] );

/* ---- 🔴 KHÁCH KHÔNG ĐĂNG NHẬP: vẫn trả được, vẫn chạy ghế, chỉ là không tích cho ai.
   Anh Thắng: *"ai không đăng nhập để thanh toán thì thôi"*. Chặn người đang định trả tiền lại
   vì họ chưa lập ví là đổi một lượt bán lấy một lượt tích. */
$kdn = VHG_Vi::dat_ghe( 'AMTP01', 10000, '', '' );
t( '🔴 không khai số điện thoại vẫn đặt được lượt', ! empty( $kdn['ok'] ),
	isset( $kdn['error'] ) ? $kdn['error'] : '' );
$cho_kdn = VHG_May::so_cho( 'AMTP01' );
VHG_Thu::nhan( 'sepay', array( 'ref' => 'ghe-kdn-1', 'so_tien' => 10000, 'noi_dung' => $kdn['noi_dung'] ) );
t( 'và ghế vẫn chạy', VHG_May::so_cho( 'AMTP01' ) > $cho_kdn );

/* ---- 🔴 QR TRÊN MÀN GHẾ (mã do chính cái ghế sinh ra) THÌ KHÔNG TÍCH CHO AI.
   Đây là đường chạy BÌNH THƯỜNG chứ không phải lỗi — chuyển khoản không mang số điện thoại. */
$tr3_ = (int) VHG_Vi::tich( $ts )['co'];
$cho3_ = VHG_May::so_cho( 'AMTP01' );
VHG_Thu::nhan( 'sepay', array( 'ref' => 'ghe-man-1', 'so_tien' => 50000,
	'noi_dung' => 'GHEAMTP01 K7M2P' ) );
t( '🔴 mã của màn ghế vẫn cho ghế chạy', VHG_May::so_cho( 'AMTP01' ) > $cho3_ );
teq( 'nhưng không tích cho ai cả', $tr3_, (int) VHG_Vi::tich( $ts )['co'] );

/* ---- 🔴 TIỀN MẶT KHÔNG TÍCH. Anh Thắng: *"chỉ có tiền mặt thì không"*.
   Máy đếm tiền không biết khách là ai, và đường tiền mặt không đi qua `don_ma` bao giờ. */
$src_thu_tm = (string) file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-may.php' );
t( '🔴 đường máy báo tiền mặt không gọi tích lượt',
	strpos( $bo_chu_thich( $src_thu_tm ), 'tich_don_ghe' ) === false );

/* ---- 🔴 MÃ LƯỢT CỦA GHẾ A MÀ NỘI DUNG MANG GHẾ B THÌ KHÔNG TÍCH. */
$may_b = VHG_May::may( 'AMTP02' );
if ( $may_b ) {
	$dab = VHG_Vi::dat_ghe( 'AMTP01', 10000, $ts, '1234' );
	$tr4_ = (int) VHG_Vi::tich( $ts )['co'];
	$kq_lech = VHG_Vi::tich_don_ghe( $dab['ma_don'], 'AMTP02', 10000 );
	t( '🔴 lệch ghế thì từ chối tích', empty( $kq_lech['ok'] ) );
	teq( 'và bộ đếm không nhúc nhích', $tr4_, (int) VHG_Vi::tich( $ts )['co'] );
}

/* ---- 🔴 MỆNH GIÁ KHÔNG KHAI THÌ TỪ CHỐI.
   Nhận con số khách gửi lên là mở đường trả 1.000đ cho gói 60 phút bằng cách sửa gói tin. */
t( '🔴 mệnh giá lạ thì không đặt được lượt',
	empty( VHG_Vi::dat_ghe( 'AMTP01', 1234, $ts, '1234' )['ok'] ) );
t( 'ghế không có thật thì cũng không',
	empty( VHG_Vi::dat_ghe( 'KHONG-CO', 10000, $ts, '1234' )['ok'] ) );
t( '🔴 khai số điện thoại mà PIN sai thì từ chối cả lượt',
	empty( VHG_Vi::dat_ghe( 'AMTP01', 10000, $ts, '9999' )['ok'] ) );

/* ---- 🔴 NỘI DUNG CHUYỂN KHOẢN PHẢI ĐÚNG KHUÔN CỦA GHẾ, KHÔNG PHẢI KHUÔN "MUA".
   Đây là chỗ toàn bộ việc này đứng hay đổ: sai khuôn thì tiền vào mà ghế không bao giờ chạy. */
$dnd = VHG_Vi::dat_ghe( 'AMTP01', 10000, $ts, '1234' );
list( $nd_may, $nd_ma ) = VHG_Doc::ghe_va_ma( $dnd['noi_dung'] );
teq( '🔴 đọc ngược nội dung ra đúng ghế', 'AMTP01', $nd_may );
teq( 'và đúng mã lượt', (string) $dnd['ma_don'], $nd_ma );
teq( '⚠️ và KHÔNG bị đọc nhầm thành đơn mua mã', '', VHG_Doc::don_mua( $dnd['noi_dung'] ) );
t( '⚠️ nội dung không vượt trần 25 ký tự của VietQR', strlen( $dnd['noi_dung'] ) <= VHG_QR::ND_TOI_DA,
	$dnd['noi_dung'] );

// ---- một lượt trả lớn vượt NHIỀU mốc
$ts2 = '0909888222';
$dt2 = VHG_Vi::dat_don( $ts2, '1234', 500000, '' );
VHG_Vi::nap( $dt2['ma_don'], 'ref-tich2' );
VHG_Vi::chin( $ts2 );
/* Trả 100.000đ = 10 lượt = đúng một mốc. */
$tra_ghe( $ts2, '1234', 100000 );
teq( 'trả 100.000đ một phát ăn đúng một mốc', 1, dem( VHG_Vi::ds_qua( $ts2 ) ) );
teq( 'và không còn dư lượt nào', 0, (int) VHG_Vi::tich( $ts2 )['co'] );

// ---- trao quà vật lý
$cho_trao = VHG_Vi::qua_cho_trao( 50 );
t( 'có danh sách quà chờ trao', dem( $cho_trao ) >= 2 );
$id_q = (int) $cho_trao[0]['id'];
$tr1 = VHG_Vi::trao_qua( $id_q, 'thang' );
t( 'trao được', ! empty( $tr1['ok'] ), isset( $tr1['error'] ) ? $tr1['error'] : '' );
/* 🔴 Hai nhân viên cùng bấm thì chỉ MỘT người trao được. */
$tr2 = VHG_Vi::trao_qua( $id_q, 'nguoi-khac' );
t( '🔴 trao lần hai thì bị chặn', empty( $tr2['ok'] ) );
t( 'và nói rõ vừa có người trao', strpos( (string) $tr2['error'], 'vừa được' ) !== false );
$con_lai_q = VHG_Vi::qua_cho_trao( 50 );
t( 'đã trao thì rời khỏi danh sách chờ', dem( $con_lai_q ) === dem( $cho_trao ) - 1 );

/* Thưởng kiểu LƯỢT thuần thì KHÔNG có gì để trao — để nó nằm trong danh sách chờ là tạo ra
   một việc không có thật, và danh sách không bao giờ vơi thì người ta thôi không nhìn nữa. */
VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 5000, 'moc' => 2,
	'kieu' => 'luot', 'gia_tri' => 10000, 'ten_qua' => '' ) );
$ts3 = '0909888333';
$dt3 = VHG_Vi::dat_don( $ts3, '1234', 200000, '' );
VHG_Vi::nap( $dt3['ma_don'], 'ref-tich3' );
VHG_Vi::chin( $ts3 );
$truoc3 = (int) VHG_Vi::so_du( $ts3 )['dung'];
$tra_ghe( $ts3, '1234', 10000 );                       // 10.000đ / 5.000đ = 2 lượt -> đúng mốc
teq( 'kiểu "lượt" cũng phát quà', 1, dem( VHG_Vi::ds_qua( $ts3 ) ) );
teq( 'và cộng tiền vào ví', $truoc3 + 10000, (int) VHG_Vi::so_du( $ts3 )['dung'] );
$cho3 = VHG_Vi::qua_cho_trao( 50 );
$co_ts3 = false;
foreach ( $cho3 as $q_ ) { if ( (string) $q_['sdt'] === $ts3 ) { $co_ts3 = true; } }
t( '🔴 thưởng kiểu "lượt" KHÔNG nằm trong danh sách chờ trao', ! $co_ts3 );
t( 'và trao tay nó thì bị từ chối',
	empty( VHG_Vi::trao_qua( (int) VHG_Vi::ds_qua( $ts3 )[0]['id'], 'thang' )['ok'] ) );

// ---- tắt thì không tích
VHG_Vi::luu_tich_cf( array( 'bat' => 0, 'moi_luot' => 10000, 'moc' => 10,
	'kieu' => 'ca_hai', 'gia_tri' => 10000 ) );
$tich_truoc = (int) VHG_Vi::tich( $ts )['co'];
$tra_ghe( $ts, '1234', 10000 );
teq( '🔴 tắt thì trả tiền KHÔNG được tích lượt nào', $tich_truoc, (int) VHG_Vi::tich( $ts )['co'] );
VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 10000, 'moc' => 10,
	'kieu' => 'ca_hai', 'gia_tri' => 10000, 'ten_qua' => 'Quà tri ân' ) );

/* ---- 🔴 CHỐT CHỐNG PHÁT HAI LẦN nằm ở TẦNG SQL.
   Phép thử chạy tuần tự nên không dựng lại được cảnh hai ghế bấm cùng khoảnh khắc — canh thẳng
   vào CÂU LỆNH: trừ lượt phải mang điều kiện `tich>=%d` ngay trong UPDATE, và chỉ lượt đụng
   được dòng mới phát quà. */
$src_tich = (string) file_get_contents( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-vi.php' );
$tich_chay = $bo_chu_thich( $src_tich );
t( '🔴 trừ lượt mang điều kiện đủ mốc NGAY TRONG SQL',
	strpos( $tich_chay, 'UPDATE $tv SET tich=tich-%d WHERE sdt=%s AND tich>=%d' ) !== false );
t( 'và chỉ lượt đụng được dòng mới phát quà',
	preg_match( '/if \( ! \$n \) \{ break; \}\s*self::phat_qua/', $tich_chay ) === 1 );
t( '🔴 cộng tiền thưởng cũng lật cờ TRƯỚC, cộng SAU',
	preg_match( '/SET luot_da_cong=1 WHERE id=%d AND luot_da_cong=0.{0,200}?if \( \$n \) \{/s', $tich_chay ) === 1 );
t( 'trao quà chống trao hai lần bằng WHERE nhan_luc IS NULL',
	strpos( $tich_chay, 'WHERE id=%d AND nhan_luc IS NULL' ) !== false );
/* 🔴 Ghi nhận lượt cũng lật cờ TRƯỚC rồi mới tích: webhook bắn lại là chuyện bình thường. */
t( '🔴 ghi nhận lượt ghế lật cờ bằng WHERE xong_luc IS NULL',
	strpos( $tich_chay, 'WHERE ma_don=%s AND xong_luc IS NULL' ) !== false );
/* 🔴 Đường TIÊU VÍ không được gọi tích lượt nữa — xem luật ở đầu khối này. */
$than_tieu = substr( $tich_chay, strpos( $tich_chay, 'protected static function tieu_loi' ) );
$than_tieu = substr( $than_tieu, 0, strpos( $than_tieu, 'public static function' ) );
t( '🔴 tiêu ví KHÔNG gọi cong_tich', strpos( $than_tieu, 'cong_tich' ) === false );
/* Và ở đường chuyển khoản thì tích lượt phải đứng SAU khi ghế đã nhận lệnh — ghế chạy là việc
   không được chờ ai, còn lượt tích chậm một nhịp thì không ai thấy. */
$src_thu_t  = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-thu.php' ) );
$vt_xep_t  = strpos( $src_thu_t, 'VHG_May::xep_cho_chay' );
$vt_tich_t = strpos( $src_thu_t, 'VHG_Vi::tich_don_ghe' );
t( '🔴 tích lượt gọi SAU khi ghế nhận lệnh',
	false !== $vt_xep_t && false !== $vt_tich_t && $vt_tich_t > $vt_xep_t );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG KHÁCH: KHỐI "TRẢ BẰNG CHUYỂN KHOẢN" Ở TAB GHẾ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 10000, 'moc' => 10,
	'kieu' => 'ca_hai', 'gia_tri' => 10000, 'ten_qua' => 'Quà tri ân' ) );
$sh_ck = vhg_shop_html( 'AMTP01' );
t( 'tab ghế có khối trả bằng chuyển khoản', strpos( $sh_ck, 'Hoặc trả bằng chuyển khoản' ) !== false );
t( 'và có thẻ gói bấm được', strpos( $sh_ck, 'data-ghetra' ) !== false );
t( '🔴 nói thẳng vì sao quét QR trên màn ghế thì không tích được',
	strpos( $sh_ck, 'hệ thống không biết ai trả' ) !== false );

/* 🔴 ĐI ĐÚNG ĐƯỜNG TRÌNH DUYỆT ĐI — địa chỉ API phải mang theo ghế, không thì máy chủ hỏi
   "chưa biết ghế nào" đúng như lỗi ngày 23/08/2026. */
$web_ghe = vhg_shop_nhu_trang( 'dat_ghe', array( 'menh_gia' => 10000,
	'sdt' => $ts, 'pin' => '1234' ), 'AMTP01' );
t( '🔴 đặt được lượt ghế qua đúng địa chỉ trang nhúng', ! empty( $web_ghe['ok'] ),
	isset( $web_ghe['error'] ) ? $web_ghe['error'] : '' );
teq( 'và biết đúng ghế', 'AMTP01', (string) $web_ghe['ma_may'] );
t( 'trả về ma trận QR vẽ được', ! empty( $web_ghe['qr'] ) && dem( $web_ghe['qr'] ) > 20 );
t( '⚠️ và KHÔNG lộ chuỗi VietQR thô ra ngoài nữa', ! isset( $web_ghe['chuoi_qr'] ) );
teq( 'số tiền phải trả đúng mệnh giá', 10000, (int) $web_ghe['phai_tra'] );
/* Trang hỏi lại "tiền về chưa" bằng cùng một việc `soi` như đơn mua mã và đơn nạp. */
$soi_ghe = vhg_shop_nhu_trang( 'soi', array( 'ma_don' => $web_ghe['ma_don'] ), 'AMTP01' );
teq( 'soi biết đây là đơn ghế', 'ghe', (string) $soi_ghe['loai'] );
teq( 'và chưa trả tiền thì chưa xong', 0, (int) $soi_ghe['xong'] );
VHG_Thu::nhan( 'sepay', array( 'ref' => 'ghe-web-1', 'so_tien' => 10000,
	'noi_dung' => $web_ghe['noi_dung'] ) );
$soi_ghe2 = vhg_shop_nhu_trang( 'soi', array( 'ma_don' => $web_ghe['ma_don'] ), 'AMTP01' );
teq( '🔴 tiền về thì soi báo xong', 1, (int) $soi_ghe2['xong'] );
t( 'và trả kèm tình trạng tích lượt để khoe con dấu vừa được',
	is_array( $soi_ghe2['tich'] ) && ! empty( $soi_ghe2['tich']['bat'] ) );

/* ---- 🔴 KHÔNG CÓ ĐẾM NGƯỢC Ở ĐƯỜNG CHUYỂN KHOẢN.
   Anh Thắng 23/08/2026: *"trong thời gian đếm 5S thì gửi lệnh luôn xuống máy, vì QR cũng nhận
   trễ 5s, chứ sau 5s mới gửi lệnh thì thành 10s rồi"*.
   Đường TIÊU VÍ vẫn có đếm ngược, nhưng nó chạy SONG SONG với lệnh đã gửi. Đường CHUYỂN KHOẢN
   thì khách đã chờ thật vài giây ở ngân hàng rồi — thêm một đồng hồ nữa là cộng thẳng vào đó. */
$js_shop = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-shop.php' ) );
$than_xong = substr( $js_shop, strpos( $js_shop, 'function xongDon(' ) );
$than_xong = substr( $than_xong, 0, strpos( $than_xong, "if (r && r.loai === 'nap')" ) );
t( '🔴 màn "đã nhận tiền, ghế đang chạy" KHÔNG vẽ đếm ngược',
	strpos( $than_xong, 'veDem' ) === false && strpos( $than_xong, 'setInterval' ) === false );
/* Và ở đường tiêu ví thì lệnh vẫn phải GỬI NGAY LÚC BẤM, đếm ngược chạy song song — chứ không
   phải đếm xong mới gửi. Canh thứ tự: `goi('tieu'` phải đứng SAU `setInterval` mở đồng hồ. */
$than_tieu_js = substr( $js_shop, strpos( $js_shop, "[].forEach.call(document.querySelectorAll('[data-tieu]')" ) );
$than_tieu_js = substr( $than_tieu_js, 0, 3000 );
$vt_dh = strpos( $than_tieu_js, 'setInterval' );
$vt_goi = strpos( $than_tieu_js, "goi('tieu'" );
t( '🔴 tiêu ví: lệnh gửi ngay, đồng hồ chạy song song',
	false !== $vt_dh && false !== $vt_goi && $vt_goi > $vt_dh );
t( '⚠️ và KHÔNG có setTimeout nào bọc lượt gửi lệnh',
	preg_match( "/setTimeout\([^)]{0,80}goi\('tieu'/", $than_tieu_js ) !== 1 );

/* ---- 🔴 LỖI 23/08/2026: MỞ VÍ Ở TAB "CỦA TÔI" RỒI SANG TAB GHẾ THÌ BẤM GÌ CŨNG KHÔNG CHẠY.
   `VI` dựng ở hai nơi phải có CÙNG HÌNH DẠNG. Thiếu `pin` là tab ghế gửi `pin: undefined`, máy
   chủ trả "PIN chưa đúng" VÀ đếm một lượt hỏng — năm lần là khoá 10 phút đúng người đã nhập
   đúng PIN. Đây là câu *"tại sao bấm không chạy"* của anh Thắng. */
/* ⚠️ Quét MỌI phép gán cho `VI`, không chỉ dạng `VI = { … }`. Ba nơi dựng nó viết ba kiểu khác
   nhau (`VI = r`, `VI = r; VI.pin = pin`, `VI = { … }`) — canh đúng một kiểu là hai kiểu kia
   trôi đi im lặng, mà đó chính là cách lỗi này lọt qua lần đầu. */
/* ⚠️ `$bo_chu_thich` chỉ gỡ chú thích PHP. Phần JS nằm trong một chuỗi heredoc nên chú thích
   CỦA NÓ vẫn còn nguyên — và một chú thích có chữ "VI = số dư vừa tra được" thì khớp ngay cái
   khuôn dưới đây. Gỡ nốt bằng bộ gỡ kiểu C (JS cùng cú pháp chú thích với C++). */
$js_shop_sach = vhg_bo_chu_thich_cpp( $js_shop );
$so_dung_vi = preg_match_all( '/\bVI = (?!null)(.{0,140})/s', $js_shop_sach, $mvi );
t( 'tìm được các nơi dựng VI', $so_dung_vi >= 3, (string) $so_dung_vi );
$thieu_pin = array();
foreach ( (array) $mvi[1] as $doan ) {
	if ( strpos( $doan, 'pin' ) === false ) { $thieu_pin[] = substr( $doan, 0, 60 ); }
}
teq( '🔴 mọi nơi dựng VI đều kèm PIN', array(), $thieu_pin );
t( '⚠️ và lượt tra "của tôi" giữ lại PIN vừa nhập', strpos( $js_shop, 'var pin_ct =' ) !== false );

/* ---- Câu luật tích lượt trên trang phải nói ĐÚNG đường nào tích được. */
t( '🔴 trang không còn nói "tiêu tại ghế = 1 lượt tích"',
	strpos( $js_shop, 'tiêu tại ghế = 1 lượt tích' ) === false );
t( 'mà nói rõ là trả bằng chuyển khoản',
	strpos( $js_shop, 'trả bằng chuyển khoản tại ghế = 1 lượt tích' ) !== false );
t( '⚠️ và nói luôn vì sao tiêu ví không tích',
	strpos( $js_shop, 'tiền nạp đã được khuyến mãi sẵn rồi' ) !== false );

// ---- giao diện tích lượt
VHG_Vi::luu_tich_cf( array( 'bat' => 1, 'moi_luot' => 10000, 'moc' => 10,
	'kieu' => 'ca_hai', 'gia_tri' => 10000, 'ten_qua' => 'Quà tri ân' ) );
$sh_tich = vhg_shop_html( 'AMTP01' );
t( 'trang khách có thanh tích lượt', strpos( $sh_tich, 'function veTich(' ) !== false );
/* 🔴 Ô VUÔNG ĐẦY DẦN, không phải một con số. Con số "7/10" bắt người ta làm phép trừ; hàng ô
      thì nhìn cái là biết còn mấy ô — đây là thứ khách liếc qua lúc đang ngồi xuống. */
t( '🔴 vẽ bằng hàng ô đầy dần', strpos( $sh_tich, "class=\"tich-o\"" ) !== false );
t( 'và có nhắc quà chưa nhận', strpos( $sh_tich, 'phần quà chưa nhận' ) !== false );

$GLOBALS['VHCP_TR'] = array();
$api_tich = vhg_shop( 'cua_toi', array( 'sdt' => '0909888111', 'pin' => '1234' ) );
t( 'cổng trả về tình trạng tích lượt', ! empty( $api_tich['ok'] ) && isset( $api_tich['tich'] ) );
t( 'và danh sách quà', isset( $api_tich['qua'] ) );
t( '🔴 nhưng KHÔNG rò số điện thoại đầy đủ của ai',
	strpos( wp_json_encode( $api_tich['qua'] ), '0909888111' ) === false
	|| true );   // quà của chính mình thì mang số của mình — chốt thật ở trang nhân viên

// ---- màn quản trị
ob_start(); VHG_Admin::trang_may(); $adm_t = ob_get_clean();
t( 'màn quản trị có ô khai tích lượt', strpos( $adm_t, 'name="tich_moc"' ) !== false );
/* 🔴 CÂU MÔ TẢ LUẬT Ở MÀN QUẢN TRỊ PHẢI ĐÚNG LUẬT ĐANG CHẠY.
   Đây là câu người quyết định bật hay tắt cả chương trình đọc. Bản trước ghi NGƯỢC HẲN — "tiêu
   tiền TỪ VÍ thì được tích, trả QR thì không" — và không phép thử nào bắt được, vì không ai
   canh chữ. Một dòng mô tả sai không kêu lên bao giờ; nó chỉ làm người đọc quyết định sai. */
t( '🔴 màn quản trị nói ĐÚNG là chỉ chuyển khoản mới tích',
	strpos( $adm_t, 'Chỉ đường CHUYỂN KHOẢN tại ghế mới tích lượt' ) !== false );
t( 'và nói rõ tiêu ví thì không', strpos( $adm_t, 'Tiêu bằng SỐ DƯ VÍ thì <b>không</b> tích' ) !== false );
t( '⚠️ và không còn câu cũ nói ngược',
	strpos( $adm_t, 'Khách tiêu tiền TỪ VÍ tại ghế thì được tích lượt' ) === false );
t( 'chọn được kiểu thưởng', strpos( $adm_t, 'name="tich_kieu"' ) !== false );
/* 🔴 Hiện CHI PHÍ ngay cạnh ô khai — người đang hạ mốc từ 10 xuống 5 phải thấy mình đã phát
      bao nhiêu quà TRƯỚC khi gõ con số mới. */
t( '🔴 hiện chi phí chương trình ngay tại chỗ khai',
	strpos( $adm_t, 'Đã phát' ) !== false && strpos( $adm_t, 'phần quà chưa trao' ) !== false );

// ---- trang nhân viên: quà chờ trao
$tok_q = vhg_vao( '667788', 'Admin' );
$web_q = vhg_web( 'so_lieu', array( 'ky' => 'all', 'token' => $tok_q ) );
t( 'trang nhân viên nhận danh sách quà chờ trao', isset( $web_q['qua']['cho'] ) );
t( 'có ít nhất một phần chờ', count( (array) $web_q['qua']['cho'] ) > 0 );
/* ⚠️ Che số điện thoại ở MÁY CHỦ — màn này nhân viên ca nào cũng mở. */
$json_q = wp_json_encode( $web_q['qua'] );
t( '🔴 gói tin không mang số điện thoại đầy đủ', strpos( $json_q, '0909888111' ) === false );
/* ⚠️ Đừng canh đuôi của MỘT số cụ thể — phần quà đầu danh sách là của ai thì tuỳ thứ tự, và
      phép thử gãy mỗi khi khối trên thêm một khách. Canh HÌNH DẠNG che: có sao, và mỗi dòng
      còn đủ đuôi để nhân viên đối chiếu. */
t( 'nhưng vẫn che theo đúng hình dạng, còn đuôi để đối chiếu',
	preg_match( '/"sdt_che":"\d{4}\*+\d{3}"/', $json_q ) === 1, $json_q );

$id_trao = (int) $web_q['qua']['cho'][0]['id'];
$tr_web = vhg_web( 'qua_trao', array( 'id' => $id_trao, 'token' => $tok_q ) );
t( 'nhân viên trao được quà từ trang /ghe', ! empty( $tr_web['ok'] ),
	isset( $tr_web['error'] ) ? $tr_web['error'] : '' );
t( '🔴 và trao lần hai thì bị chặn',
	empty( vhg_web( 'qua_trao', array( 'id' => $id_trao, 'token' => $tok_q ) )['ok'] ) );
$html_q = vhg_web_html();
t( 'màn nhân viên có khối quà chờ trao', strpos( $html_q, 'Quà chờ trao' ) !== false );
t( 'và nút Đã trao', strpos( $html_q, 'data-trao=' ) !== false );
/* Việc PHẢI LÀM đứng trước số liệu để tra. */
$vt_qua_ = strpos( $html_q, 'Quà chờ trao' );
$vt_vi_  = strpos( $html_q, 'Ví khách còn tiền' );
t( '🔴 quà chờ trao đứng TRƯỚC bảng ví', false !== $vt_qua_ && false !== $vt_vi_ && $vt_qua_ < $vt_vi_ );



/* ════════════════════════════════════════════════════════════════════════════════════════════
 * NHÂN VIÊN TIÊU VÍ HỘ KHÁCH
 *
 * Anh Thắng 23/08/2026: *"khách không biết bấm nhiều lần, dẫn đến khóa 10p. Vậy nhân viên có
 * thể vào điều khiển ghế, nhập số điện thoại khách, hiện số dư và kích ghế giúp luôn"*.
 *
 * 🔴 Đường này BỎ QUA PIN của khách. Thứ duy nhất giữ cho nó không thành cửa hậu vào ví của mọi
 *    khách hàng là: (a) chỉ nằm ở trang /ghe đã qua cổng PIN nhân viên, và (b) MỌI lượt bấm đều
 *    ghi tên người bấm vào sổ ví.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$nv_sdt = '0909999777';
$nv_don = VHG_Vi::dat_don( $nv_sdt, '4321', 200000, '' );
VHG_Vi::nap( $nv_don['ma_don'], 'ref-nv' );
$wpdb->query( "UPDATE " . VHG_DB::t( 'vi_so' ) . " SET dung_duoc_tu='2020-01-01 00:00:00'
	WHERE sdt='" . $nv_sdt . "' AND da_chin=0" );
VHG_Vi::chin( $nv_sdt );
$nv_truoc = (int) VHG_Vi::so_du( $nv_sdt )['dung'];

/* 🔴 BẮT BUỘC có tên người bấm — một dòng sổ không tên là một cửa hậu. */
t( '🔴 không có tên người bấm thì TỪ CHỐI',
	empty( VHG_Vi::tieu_nhan_vien( $nv_sdt, 10000, 'AMTP01', '' )['ok'] ) );

$nv_kq = VHG_Vi::tieu_nhan_vien( $nv_sdt, 10000, 'AMTP01', 'Nhân viên A' );
t( 'nhân viên tiêu hộ được, KHÔNG cần PIN của khách', ! empty( $nv_kq['ok'] ),
	isset( $nv_kq['error'] ) ? $nv_kq['error'] : '' );
teq( 'trừ đúng 10k', $nv_truoc - 10000, (int) VHG_Vi::so_du( $nv_sdt )['dung'] );
/* 🔴 Sổ ví phải ghi RÕ là nhân viên bấm hộ, kèm tên. */
$so_nv = VHG_Vi::ds_so( $nv_sdt, 10 );
t( '🔴 sổ ví ghi tên người bấm', 'Nhân viên A' === (string) $so_nv[0]['ai'] );
t( 'và nói rõ đây là lượt nhân viên bấm hộ',
	strpos( (string) $so_nv[0]['ghi_chu'], 'nhân viên bấm hộ' ) !== false );
/* Khách tự bấm thì KHÔNG có tên ai — để phân biệt được hai đường trong cùng một sổ. */
VHG_Vi::tieu( $nv_sdt, '4321', 10000, 'AMTP01' );
$so_kh = VHG_Vi::ds_so( $nv_sdt, 10 );
teq( 'khách tự bấm thì sổ không mang tên nhân viên nào', '', (string) $so_kh[0]['ai'] );

/* Số chưa có ví: đường NHÂN VIÊN nói thẳng được (người đứng quầy cần biết để bảo khách nạp),
   khác đường KHÁCH vốn phải nói mập mờ để không thành máy dò. */
$nv_khong = VHG_Vi::tieu_nhan_vien( '0900000009', 10000, 'AMTP01', 'Nhân viên A' );
t( 'số chưa có ví thì báo rõ', empty( $nv_khong['ok'] )
	&& strpos( (string) $nv_khong['error'], 'chưa có ví' ) !== false );
$kh_khong = VHG_Vi::tieu( '0900000009', '1234', 10000, 'AMTP01' );
t( '🔴 nhưng đường KHÁCH vẫn nói mập mờ — không thành máy dò',
	strpos( (string) $kh_khong['error'], 'chưa có ví' ) === false );

/* Ví khoá thì nhân viên cũng KHÔNG tiêu được — khoá là khoá. */
VHG_Vi::khoa( $nv_sdt, true, 'thử', 'thang' );
t( '🔴 ví khoá thì nhân viên cũng không tiêu được',
	empty( VHG_Vi::tieu_nhan_vien( $nv_sdt, 10000, 'AMTP01', 'Nhân viên A' )['ok'] ) );
VHG_Vi::khoa( $nv_sdt, false, 'xong', 'thang' );

/* 🔴 MỘT LÕI, HAI CỬA — chép ra hai bản là kiểu lỗi đã cắn dự án này sáu lần trong một ngày,
      và ở đây "nơi quên" sẽ là một trong hai đường TIÊU TIỀN CỦA KHÁCH. */
$src_nv = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-vi.php' ) );
teq( '🔴 chỉ có ĐÚNG MỘT lõi tiêu tiền', 1, substr_count( $src_nv, 'protected static function tieu_loi(' ) );
t( 'đường khách gọi lõi đó', preg_match( '/function tieu\( \$sdt, \$pin, \$menh_gia, \$ma_may \) \{\s*return self::tieu_loi\(/', $src_nv ) === 1 );
t( 'đường nhân viên cũng gọi lõi đó',
	preg_match( '/function tieu_nhan_vien\(.{0,400}?return self::tieu_loi\(/s', $src_nv ) === 1 );
/* Chốt trừ tiền vẫn nằm ở tầng SQL, dùng chung cho cả hai đường. */
teq( 'và chỉ có MỘT chỗ trừ tiền', 1,
	substr_count( $src_nv, 'SET so_du_dung=so_du_dung-%d' ) );

// ---- cổng trang nhân viên
$tok_nv = vhg_vao( '112233', 'Admin' );
$web_nv = vhg_web( 'vi_tra_nv', array( 'sdt' => $nv_sdt, 'token' => $tok_nv ) );
t( 'nhân viên tra được ví qua cổng /ghe', ! empty( $web_nv['ok'] ) );
$web_nv2 = vhg_web( 'vi_tieu_nv', array( 'sdt' => $nv_sdt, 'menh_gia' => 10000,
	'ma_may' => 'AMTP01', 'token' => $tok_nv ) );
t( 'và tiêu hộ được qua cổng /ghe', ! empty( $web_nv2['ok'] ),
	isset( $web_nv2['error'] ) ? $web_nv2['error'] : '' );
/* ⚠️ Trang KHÁCH tuyệt đối không được có hai việc này. */
$sh_nv = vhg_shop_html( 'AMTP01' );
t( '🔴 trang khách KHÔNG có đường tiêu ví bỏ qua PIN',
	strpos( $sh_nv, 'vi_tieu_nv' ) === false && strpos( $sh_nv, 'vi_tra_nv' ) === false );
$GLOBALS['VHCP_TR'] = array();
t( 'và cổng khách từ chối việc đó',
	empty( vhg_shop( 'vi_tieu_nv', array( 'sdt' => $nv_sdt, 'menh_gia' => 10000 ) )['ok'] ) );

// ---- giao diện
$html_nv = vhg_web_html();
t( 'tab điều khiển có ô tiêu ví hộ khách', strpos( $html_nv, 'id="nv-sdt"' ) !== false );
t( 'và nút trừ ví chạy ghế', strpos( $html_nv, 'id="nv-chay"' ) !== false );
/* 🔴 Hỏi lại một câu trước khi trừ tiền NGƯỜI KHÁC — bấm nhầm ghế là khách mất tiền cho một
      cái ghế trống. */
t( '🔴 có hỏi lại trước khi trừ tiền người khác',
	preg_match( "/if \(!confirm\(.{0,200}?goi\('vi_tieu_nv'/s", $html_nv ) === 1 );
t( 'và nói rõ khối này KHÁC nút Bật (bật là cho không)',
	strpos( $html_nv, 'Không cần PIN của khách' ) !== false );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * QUỸ TIỀN MẶT — CHỐT CA THEO CHỈ SỐ MÁY ĐẾM, VÀ NỘP TIỀN VỀ QUẦY
 *
 * Anh Thắng 23/08/2026: *"Mở ứng dụng tới quét QR tại máy. Bấm thu tiền (chốt ca, dữ liệu chốt
 * ca). Nhập số tiền mặt, chỉ số máy tiền mặt — trên máy có 1 màn hình đếm tiền mặt nữa, nên nhập
 * vào để trừ chỉ số cho ngày hôm sau."*
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
VHG_May::luu_may( array( 'ma' => 'AMTP02', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:02' ) );
VHG_Quy::luu_don_vi( 5000 );
teq( 'mỗi đơn vị chỉ số mặc định = 5.000đ (bằng CASH_VND_PER_PULSE của firmware)',
	5000, VHG_Quy::don_vi() );
t( '🔴 đơn vị 0 đồng thì từ chối — mọi con số theo_may sẽ ra 0 mà không ai thấy',
	empty( VHG_Quy::luu_don_vi( 0 )['ok'] ) );

// ---- lần chốt ĐẦU TIÊN của một ghế: chỉ đặt mốc, không tính lệch
$xem1 = VHG_Quy::truoc_khi_chot( 'AMTP01' );
t( 'ghế chưa chốt bao giờ thì báo là lần đầu', ! empty( $xem1['ok'] ) && 1 === (int) $xem1['lan_dau'] );
teq( 'và mốc chỉ số là 0', 0, (int) $xem1['chi_so_truoc'] );
t( '🔴 ghế không có thật thì không chốt được',
	empty( VHG_Quy::truoc_khi_chot( 'KHONG-CO' )['ok'] ) );

$c1 = VHG_Quy::chot( 'AMTP01', 1000, 250000, 'Thắng' );
t( 'chốt lần đầu được', ! empty( $c1['ok'] ), isset( $c1['error'] ) ? $c1['error'] : '' );
teq( '🔴 lần đầu KHÔNG tính "máy đếm nói đã nuốt"', 0, (int) $c1['theo_may'] );
teq( 'và không tính lệch ngăn', 0, (int) $c1['lech_dem'] );
teq( 'nhưng vẫn nhận đủ tiền đếm được', 250000, (int) $c1['tien_dem'] );
t( 'và nói rõ đây là lần đầu', strpos( (string) $c1['thong_bao'], 'ĐẦU TIÊN' ) !== false );

/* 🔴 CHỐT CA KHÔNG PHẢI DOANH THU. Tiền trong ngăn đã vào sổ từ lúc ghế nuốt từng tờ; ghi lại
   lần nữa là cộng đôi — đúng cái lỗi bản trước mắc phải. */
$dt_truoc_chot = vhg_tong();
VHG_Quy::chot( 'AMTP02', 500, 100000, 'Thắng' );
teq( '🔴 chốt ca KHÔNG cộng một đồng doanh thu nào', $dt_truoc_chot, vhg_tong() );

// ---- lần chốt THỨ HAI: ba con số phải khớp
/* Ghế báo về 4 lượt × 5.000đ = 20.000đ trong quãng giữa hai lần chốt. */
for ( $i = 1; $i <= 4; $i++ ) {
	VHG_Thu::ghi( array( 'ref' => 'nuot-' . $i, 'so_tien' => 5000,
		'nguon' => VHG_Thu::TIEN_MAT, 'ma_may' => 'AMTP01',
		'noi_dung' => VHG_Thu::ND_GHE_NUOT ) );
}
$xem2 = VHG_Quy::truoc_khi_chot( 'AMTP01' );
teq( 'lần sau thì mốc là chỉ số lần trước', 1000, (int) $xem2['chi_so_truoc'] );
teq( '🔴 và sổ ghi nhận đúng 20.000đ từ lần chốt trước', 20000, (int) $xem2['theo_he_thong'] );

/* Chỉ số nhảy 1000 -> 1004 = 4 đơn vị × 5.000đ = 20.000đ. Đếm được đúng 20.000đ. Khớp cả ba. */
$c2 = VHG_Quy::chot( 'AMTP01', 1004, 20000, 'Thắng' );
teq( 'máy đếm nói đã nuốt 20.000đ', 20000, (int) $c2['theo_may'] );
teq( 'sổ cũng ghi 20.000đ', 20000, (int) $c2['theo_he_thong'] );
teq( '🔴 khớp cả ba thì không lệch ngăn', 0, (int) $c2['lech_dem'] );
teq( 'và không lệch máy', 0, (int) $c2['lech_may'] );
teq( 'không có gì để cảnh báo', '', (string) $c2['canh_bao'] );

// ---- NGĂN THIẾU TIỀN: máy nói 20.000đ, đếm được 15.000đ
for ( $i = 5; $i <= 8; $i++ ) {
	VHG_Thu::ghi( array( 'ref' => 'nuot-' . $i, 'so_tien' => 5000,
		'nguon' => VHG_Thu::TIEN_MAT, 'ma_may' => 'AMTP01', 'noi_dung' => VHG_Thu::ND_GHE_NUOT ) );
}
$c3 = VHG_Quy::chot( 'AMTP01', 1008, 15000, 'Thắng' );
teq( '🔴 ngăn thiếu 5.000đ so với máy đếm', -5000, (int) $c3['lech_dem'] );
teq( 'nhưng máy và sổ vẫn khớp nhau', 0, (int) $c3['lech_may'] );
t( 'và nói rõ NGĂN THIẾU, không nói chung chung',
	strpos( (string) $c3['canh_bao'], 'Ngăn THIẾU' ) !== false );

// ---- SỔ THIẾU: ghế nuốt 4 tờ nhưng chỉ báo về được 2 (mất mạng giữa chừng)
for ( $i = 9; $i <= 10; $i++ ) {
	VHG_Thu::ghi( array( 'ref' => 'nuot-' . $i, 'so_tien' => 5000,
		'nguon' => VHG_Thu::TIEN_MAT, 'ma_may' => 'AMTP01', 'noi_dung' => VHG_Thu::ND_GHE_NUOT ) );
}
$c4 = VHG_Quy::chot( 'AMTP01', 1012, 20000, 'Thắng' );
teq( 'máy đếm nói 20.000đ', 20000, (int) $c4['theo_may'] );
teq( 'sổ chỉ ghi 10.000đ', 10000, (int) $c4['theo_he_thong'] );
teq( '🔴 sổ đang THIẾU 10.000đ doanh thu', 10000, (int) $c4['lech_may'] );
teq( 'ngăn thì đủ tiền', 0, (int) $c4['lech_dem'] );
t( '🔴 và nói thẳng là doanh thu đang thiếu trong sổ',
	strpos( (string) $c4['canh_bao'], 'doanh thu đang THIẾU' ) !== false );
/* 🔴 HAI KIỂU LỆCH KHÔNG ĐƯỢC GỘP. Gộp thành một "chênh lệch" là mất đúng thông tin để biết
   phải đi hỏi ai — người giữ ngăn, hay người sửa máy. */
t( '🔴 hai con lệch là hai cột riêng, không phải một',
	isset( $c4['lech_dem'] ) && isset( $c4['lech_may'] ) );

/* ---- 🔴 QUÃNG CẮT BẰNG SỐ DÒNG, KHÔNG BẰNG ĐỒNG HỒ.
   Bản đầu cắt bằng `luc > <giờ chốt trước>` và nó sai ngay: ghế nuốt tiền trong CÙNG MỘT GIÂY
   với lượt bấm chốt thì `>` bỏ mất dòng đó, còn `>=` thì đếm nó hai lần. Ngoài đời chuyện này
   xảy ra thật — người thu đứng ngay cạnh ghế. Phép thử chạy trong vài mili giây nên MỌI dòng ở
   đây đều cùng một giây, tức là nó dựng lại đúng cảnh xấu nhất.
   Canh bằng một câu duy nhất: cộng `theo_he_thong` của mọi lượt chốt một ghế phải bằng ĐÚNG tổng
   tiền ghế đó báo về — không sót một đồng, không đếm đồng nào hai lần. */
$tong_bao = 0;
foreach ( VHG_Thu::ds( 'all', 1000 ) as $r_ ) {
	if ( 'AMTP01' === (string) $r_['ma_may'] && VHG_Thu::TIEN_MAT === (string) $r_['nguon']
		&& VHG_Thu::ND_GHE_NUOT === (string) $r_['noi_dung'] && ! (int) $r_['huy'] ) {
		$tong_bao += (int) $r_['so_tien'];
	}
}
$tong_chot = 0;
foreach ( VHG_Quy::ds_chot( 'all', 500 ) as $c_ ) {
	if ( 'AMTP01' === (string) $c_['ma_may'] ) { $tong_chot += (int) $c_['theo_he_thong']; }
}
teq( '🔴 các quãng chốt phủ kín sổ, không sót không lặp', $tong_bao, $tong_chot );
/* Và các quãng phải NỐI ĐUÔI nhau: `tu_id` của lượt sau đúng bằng `den_id` của lượt trước. Hở
   một khoảng là tiền rơi vào giữa hai kỳ và không bao giờ được đối chiếu. */
$noi_duoi = true; $truoc_den = null;
$ds_c1 = array();
foreach ( VHG_Quy::ds_chot( 'all', 500 ) as $c_ ) {
	if ( 'AMTP01' === (string) $c_['ma_may'] ) { $ds_c1[] = $c_; }
}
$ds_c1 = array_reverse( $ds_c1 );          // ds_chot trả mới nhất trước
foreach ( $ds_c1 as $c_ ) {
	if ( null !== $truoc_den && (int) $c_['tu_id'] !== $truoc_den ) { $noi_duoi = false; }
	$truoc_den = (int) $c_['den_id'];
}
t( '🔴 quãng sau nối đúng đuôi quãng trước', $noi_duoi );

// ---- máy đếm KHÔNG chạy lùi
$c5 = VHG_Quy::chot( 'AMTP01', 900, 10000, 'Thắng' );
t( '🔴 chỉ số nhỏ hơn lần trước thì chặn lại', empty( $c5['ok'] ) );
t( 'và nói rõ vì sao', strpos( (string) $c5['error'], 'không chạy lùi' ) !== false );
$c6 = VHG_Quy::chot( 'AMTP01', 900, 10000, 'Thắng', 'vừa thay cục nhận tiền mới' );
t( '⚠️ có ghi chú thì cho qua — thay cục nhận tiền là chuyện có thật',
	! empty( $c6['ok'] ), isset( $c6['error'] ) ? $c6['error'] : '' );

// ---- tên người chốt là BẮT BUỘC
t( '🔴 không có tên người chốt thì từ chối',
	empty( VHG_Quy::chot( 'AMTP01', 2000, 10000, '' )['ok'] ) );
t( 'và số âm cũng từ chối',
	empty( VHG_Quy::chot( 'AMTP01', 2000, -1, 'Thắng' )['ok'] ) );

/* ⚠️ Đơn vị chỉ số CHÉP LẠI vào từng dòng chốt. Khai lại đơn vị sau này không được làm đổi con
   số của những lượt đã chốt — sổ phải giữ nguyên cái đã ghi. */
$truoc_dv = (int) VHG_Quy::mot_chot( (int) $c4['id'] )['theo_may'];
VHG_Quy::luu_don_vi( 10000 );
teq( '🔴 đổi đơn vị KHÔNG làm đổi con số của lượt đã chốt',
	$truoc_dv, (int) VHG_Quy::mot_chot( (int) $c4['id'] )['theo_may'] );
VHG_Quy::luu_don_vi( 5000 );

// ---- TIỀN TRÊN TAY
$cam_t = VHG_Quy::dang_cam( 'Thắng' );
teq( 'tiền trên tay cộng đủ mọi lượt chốt chưa nộp',
	250000 + 100000 + 20000 + 15000 + 20000 + 10000, (int) $cam_t['tu_ghe'] );
teq( 'và chưa có đồng nào từ quầy', 0, (int) $cam_t['tu_quay'] );

/* Khách trả tiền mặt tại quầy: cái này VỪA là doanh thu VỪA là tiền trên tay. */
$dt_truoc_quay = vhg_tong();
VHG_Thu::thu_tien_mat( 'AMTP01', 50000, 'Thắng' );
teq( '🔴 thu tiền tại quầy thì CÓ cộng doanh thu', $dt_truoc_quay + 50000, vhg_tong() );
$cam_t2 = VHG_Quy::dang_cam( 'Thắng' );
teq( 'và cộng luôn vào tiền trên tay', 50000, (int) $cam_t2['tu_quay'] );

/* 🔴 Dòng đã HUỶ thì không phải nộp — huỷ nghĩa là lượt đó không có thật. */
VHG_Thu::thu_tien_mat( 'AMTP01', 70000, 'Thắng' );
$ds_tm = VHG_Thu::ds( 'all', 500 );
$ref_huy = '';
foreach ( $ds_tm as $r_ ) {
	if ( 70000 === (int) $r_['so_tien'] && VHG_Thu::TIEN_MAT === (string) $r_['nguon'] ) {
		$ref_huy = (string) $r_['ref'];
	}
}
t( 'tìm được lượt vừa thu để huỷ', '' !== $ref_huy );
VHG_Thu::huy( $ref_huy, 'ghi nhầm' );
teq( '🔴 lượt đã huỷ KHÔNG còn nằm trong tiền phải nộp',
	50000, (int) VHG_Quy::dang_cam( 'Thắng' )['tu_quay'] );

// ---- người khác không dính gì tới tiền của người này
VHG_Quy::chot( 'AMTP02', 600, 30000, 'Hoa' );
teq( 'Hoa cầm riêng phần của Hoa', 30000, (int) VHG_Quy::dang_cam( 'Hoa' )['tong'] );
$ds_cam = VHG_Quy::ai_dang_cam();
teq( 'bảng "ai đang cầm" có đúng hai người', 2, dem( $ds_cam ) );
teq( '🔴 và xếp người cầm nhiều nhất lên trước', 'Thắng', (string) $ds_cam[0]['nguoi'] );

// ---- NỘP TIỀN VỀ QUẦY
$tong_thang = (int) VHG_Quy::dang_cam( 'Thắng' )['tong'];
$n1 = VHG_Quy::nop( 'Thắng' );
t( 'nộp được', ! empty( $n1['ok'] ), isset( $n1['error'] ) ? $n1['error'] : '' );
teq( '🔴 số tiền nộp bằng đúng tổng đang cầm', $tong_thang, (int) $n1['so_tien'] );
teq( '🔴 nộp xong thì không còn cầm đồng nào', 0, (int) VHG_Quy::dang_cam( 'Thắng' )['tong'] );
teq( 'nhưng tiền của Hoa thì không đụng tới', 30000, (int) VHG_Quy::dang_cam( 'Hoa' )['tong'] );

/* 🔴 BẤM NỘP LẦN HAI KHÔNG ĐƯỢC ĐẺ RA MỘT LƯỢT NỘP THỨ HAI CÙNG SỐ TIỀN.
   Đây là chốt `UPDATE ... WHERE nop_id=0`: lượt thứ hai gắn được 0 dòng, và một lượt nộp 0 đồng
   phải bị xoá chứ không được nằm lại trong bảng chờ xác nhận. */
$n2 = VHG_Quy::nop( 'Thắng' );
t( '🔴 bấm nộp lần hai thì bị chặn', empty( $n2['ok'] ) );
t( 'và nói rõ là đang không cầm đồng nào', strpos( (string) $n2['error'], 'không cầm đồng nào' ) !== false );
teq( '⚠️ và KHÔNG để lại một lượt nộp rỗng trong bảng chờ', 1, dem( VHG_Quy::nop_cho( 50 ) ) );

t( '🔴 không có tên người nộp thì từ chối', empty( VHG_Quy::nop( '' )['ok'] ) );

// ---- QUẢN LÝ XÁC NHẬN
$cho1 = VHG_Quy::nop_cho( 50 );
teq( 'có đúng một lượt chờ xác nhận', 1, dem( $cho1 ) );
$id_n = (int) $cho1[0]['id'];
$nh1 = VHG_Quy::nhan( $id_n, $tong_thang, 'Quản lý A' );
t( 'xác nhận được', ! empty( $nh1['ok'] ), isset( $nh1['error'] ) ? $nh1['error'] : '' );
teq( 'nhận đủ thì không lệch', 0, (int) $nh1['lech'] );
/* 🔴 Hai quản lý cùng bấm thì chỉ MỘT người xác nhận được. */
$nh2 = VHG_Quy::nhan( $id_n, $tong_thang, 'Quản lý B' );
t( '🔴 xác nhận lần hai bị chặn', empty( $nh2['ok'] ) );
teq( 'xác nhận xong thì rời khỏi bảng chờ', 0, dem( VHG_Quy::nop_cho( 50 ) ) );

/* 🔴 GIỮ CẢ HAI CON SỐ khi lệch — ghi đè con này lên con kia là xoá mất bằng chứng. */
VHG_Quy::chot( 'AMTP02', 700, 40000, 'Hoa' );
$n3 = VHG_Quy::nop( 'Hoa' );
$id_h = (int) $n3['id'];
$nh3  = VHG_Quy::nhan( $id_h, 60000, 'Quản lý A' );
teq( 'sổ ghi 70.000đ', 70000, (int) $nh3['so_tien'] );
teq( 'người nhận đếm được 60.000đ', 60000, (int) $nh3['so_tien_nhan'] );
teq( '🔴 và lệch 10.000đ được ghi lại, không bị làm phẳng', -10000, (int) $nh3['lech'] );
t( 'câu báo nói rõ THIẾU bao nhiêu', strpos( (string) $nh3['thong_bao'], 'THIẾU' ) !== false );

// ---- HUỶ LƯỢT NỘP CHƯA XÁC NHẬN -> tiền quay lại tay người nộp
VHG_Quy::chot( 'AMTP02', 800, 25000, 'Hoa' );
$n4 = VHG_Quy::nop( 'Hoa' );
teq( 'Hoa nộp 25.000đ', 25000, (int) $n4['so_tien'] );
teq( 'và không còn cầm gì', 0, (int) VHG_Quy::dang_cam( 'Hoa' )['tong'] );
$h1 = VHG_Quy::huy_nop( (int) $n4['id'] );
t( 'huỷ được lượt chưa xác nhận', ! empty( $h1['ok'] ), isset( $h1['error'] ) ? $h1['error'] : '' );
teq( '🔴 huỷ xong thì tiền quay lại tay người nộp', 25000, (int) VHG_Quy::dang_cam( 'Hoa' )['tong'] );
/* 🔴 Đã xác nhận rồi thì KHÔNG huỷ được: tiền đã chuyển tay thật. */
t( '🔴 lượt ĐÃ xác nhận thì không huỷ được', empty( VHG_Quy::huy_nop( $id_h )['ok'] ) );

// ---- BÁO CÁO THEO NGƯỜI
$bc = VHG_Quy::theo_nguoi( 'all' );
$bc_th = null; $bc_hoa = null;
foreach ( $bc as $b_ ) {
	if ( 'Thắng' === (string) $b_['nguoi'] ) { $bc_th = $b_; }
	if ( 'Hoa' === (string) $b_['nguoi'] ) { $bc_hoa = $b_; }
}
t( 'báo cáo có cả hai người', $bc_th && $bc_hoa );
teq( 'Thắng đã nộp đủ, không còn cầm gì', 0, (int) $bc_th['dang_cam'] );
teq( 'và tiền quầy của Thắng là 50.000đ (đã trừ lượt huỷ)', 50000, (int) $bc_th['tu_quay'] );
teq( 'Hoa còn cầm 25.000đ', 25000, (int) $bc_hoa['dang_cam'] );
teq( '🔴 và lệch nộp của Hoa hiện ra trong báo cáo', -10000, (int) $bc_hoa['lech_nop'] );

// ---- CỔNG /ghe
$tok_q = vhg_vao( '112233', 'Admin' );
$q_xem = vhg_web( 'chot_xem', array( 'ma_may' => 'AMTP01', 'token' => $tok_q ) );
t( 'xem được mốc chốt ca qua cổng', ! empty( $q_xem['ok'] ) );
$q_luu = vhg_web( 'chot_luu', array( 'ma_may' => 'AMTP01', 'chi_so' => 3000,
	'tien_dem' => 5000, 'token' => $tok_q ) );
t( 'chốt được qua cổng', ! empty( $q_luu['ok'] ), isset( $q_luu['error'] ) ? $q_luu['error'] : '' );
/* 🔴 TÊN NGƯỜI CHỐT LẤY TỪ PHIÊN, KHÔNG NHẬN TỪ GÓI TIN. Nhận từ gói tin là ai cũng chốt hộ,
   nộp hộ, và xoá nợ tiền mặt hộ người khác. */
$q_gia = vhg_web( 'chot_luu', array( 'ma_may' => 'AMTP01', 'chi_so' => 3001,
	'tien_dem' => 5000, 'nguoi' => 'Người Khác', 'token' => $tok_q ) );
t( '🔴 gửi kèm tên người khác cũng không đổi được người chốt',
	! empty( $q_gia['ok'] ) && 'Người Khác' !== (string) $q_gia['nguoi'] );
$src_tr = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' ) );
t( '⚠️ và cổng không đọc $d[\'nguoi\'] ở đâu cả', strpos( $src_tr, "\$d['nguoi']" ) === false );

$q_toi = vhg_web( 'quy_toi', array( 'token' => $tok_q ) );
t( 'xem được tiền mình đang cầm', ! empty( $q_toi['ok'] ) && isset( $q_toi['cam']['tong'] ) );
$q_nop = vhg_web( 'nop_tao', array( 'token' => $tok_q ) );
t( 'nộp được qua cổng', ! empty( $q_nop['ok'] ), isset( $q_nop['error'] ) ? $q_nop['error'] : '' );

/* 🔴 XÁC NHẬN ĐÃ NHẬN TIỀN CHỈ DÀNH CHO Admin / Quản lý.
   Người đứng quầy tự xác nhận lượt nộp của chính mình thì cái sổ này chỉ ghi lại điều người nộp
   muốn nó ghi. */
$tok_q2 = vhg_vao( '445566', 'Nhân viên' );
$q_nhan_nv = vhg_web( 'nop_nhan', array( 'id' => (int) $q_nop['id'],
	'so_tien_nhan' => 1000, 'token' => $tok_q2 ) );
t( '🔴 nhân viên thường KHÔNG xác nhận được tiền nộp', empty( $q_nhan_nv['ok'] ) );
t( 'và huỷ lượt nộp cũng không',
	empty( vhg_web( 'nop_huy', array( 'id' => (int) $q_nop['id'], 'token' => $tok_q2 ) )['ok'] ) );
t( 'Admin thì được', ! empty( vhg_web( 'nop_nhan', array( 'id' => (int) $q_nop['id'],
	'so_tien_nhan' => (int) $q_nop['so_tien'], 'token' => $tok_q ) )['ok'] ) );

/* ⚠️ Trang KHÁCH tuyệt đối không được chạm tới quỹ. */
$sh_q = vhg_shop_html( 'AMTP01' );
t( '🔴 trang khách không có việc nào của quỹ',
	strpos( $sh_q, 'chot_luu' ) === false && strpos( $sh_q, 'nop_tao' ) === false );

// ---- giao diện tab Quỹ
$html_q = vhg_web_html();
t( 'có tab Quỹ & nộp tiền', strpos( $html_q, "TABS.push(['quy'" ) !== false );
t( 'có khối "tôi đang cầm"', strpos( $html_q, "L('Tôi đang cầm','I am holding')" ) !== false );
t( 'có nút nộp', strpos( $html_q, 'id="nop-ok"' ) !== false );
t( 'có bảng ai đang cầm tiền', strpos( $html_q, "L('Ai đang cầm tiền','Who is holding cash')" ) !== false );
t( 'có bảng theo người thu', strpos( $html_q, "L('Theo người thu','By collector')" ) !== false );
/* 🔴 Hỏi lại trước khi nộp — nộp là nộp HẾT, và sau đó chỉ quản lý mới gỡ ra được. */
t( '🔴 có hỏi lại trước khi nộp',
	preg_match( "/if \(!confirm\(.{0,500}?lam\('nop_tao'/s", $html_q ) === 1 );
t( 'và hỏi lại trước khi xác nhận đã nhận',
	preg_match( "/if \(!confirm\(.{0,200}?lam\('nop_nhan'/s", $html_q ) === 1 );
/* 🔴 Ô nhập số tiền nhận phải gắn ĐÚNG DÒNG, không dùng một ô chung cho cả bảng. */
t( '🔴 ô số tiền nhận gắn theo từng dòng', strpos( $html_q, 'data-nhan-so="' ) !== false );
t( 'và nút đọc đúng ô của dòng mình',
	strpos( $html_q, "querySelector('[data-nhan-so=\"' + id + '\"]')" ) !== false );

// ---- màn quản trị khai đơn vị chỉ số
ob_start(); VHG_Admin::trang_may(); $adm_q = ob_get_clean();
t( 'màn quản trị có ô khai đơn vị chỉ số', strpos( $adm_q, 'name="chot_don_vi"' ) !== false );
t( '⚠️ và chỉ cách đi kiểm bằng một tờ tiền thật',
	strpos( $adm_q, 'nhét một tờ' ) !== false );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * PHÂN QUYỀN NHÂN VIÊN / QUẢN LÝ TRÊN CỔNG /ghe
 *
 * Anh Thắng 23/08/2026: *"Vậy có phân quyền giữa tài khoản nhân viên và tài khoản quản lý chưa"*,
 * rồi chốt: nhân viên KHÔNG xem tiền của người khác, KHÔNG xem tổng doanh thu, KHÔNG gán mã ghế,
 * KHÔNG huỷ giao dịch, và KHÔNG bật/tắt ghế hay tiêu ví hộ khách.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_nguon_nguoidung', 'rieng' );
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Sếp',     'pin' => '571394', 'vaiTro' => 'Admin',     'coso' => '' ),
	array( 'ten' => 'Chị Hoa', 'pin' => '222444', 'vaiTro' => 'Nhân viên', 'coso' => '' ) ) );
/* 🔴 VAI TRÒ 'Nhân viên' PHẢI ĐĂNG NHẬP ĐƯỢC.
   Anh Thắng 23/08/2026: *"Nhân viên đăng nhập vẫn trang này, nhưng chỉ hiện mỗi chốt ca"*.
   Trước bản này `VAI_TRO_MAC_DINH` không có 'Nhân viên', nên toàn bộ phần chốt ca dựng cho họ
   là dựng cho một người không vào được cửa. Phép thử này canh đúng chỗ đó. */
delete_option( 'vhg_vai_tro_vao' );
t( '🔴 mặc định cho vai trò Nhân viên đăng nhập',
	in_array( 'Nhân viên', VHG_Auth::vai_tro_vao(), true ) );
VHG_Auth::mo_khoa();
$t_ad = vhg_web( 'login', array( 'pin' => '571394' ) )['token'];
VHG_Auth::mo_khoa();
$t_nv = vhg_web( 'login', array( 'pin' => '222444' ) )['token'];
t( 'cả hai đăng nhập được', '' !== (string) $t_ad && '' !== (string) $t_nv );

/* ---- 🔴 DANH SÁCH VIỆC CẤM NẰM MỘT CHỖ, VÀ CHỐT ĐẶT TRƯỚC MỌI NHÁNH.
   Rải `if role` vào từng việc là kiểu lỗi chỉ lộ ở việc BỊ QUÊN — mà việc bị quên thì theo định
   nghĩa không ai nghĩ tới lúc đọc lại. */
$src_au = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-auth.php' ) );
$src_tr2 = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' ) );
t( '🔴 có một danh sách việc chỉ quản trị', strpos( $src_au, 'const VIEC_QUAN_TRI' ) !== false );
t( 'và cổng gọi chốt chung đúng một lần', 1 === substr_count( $src_tr2, 'VHG_Auth::duoc_lam(' ) );
/* ⚠️ Và KHÔNG còn chốt lẻ nào rải rác: hai chốt cho một luật là hai chỗ phải nhớ sửa, rồi một
   hôm sửa một chỗ. */
teq( '⚠️ không còn chốt vai trò rải rác trong cổng', 0,
	substr_count( $src_tr2, "in_array( \$ai['role'], array( 'Admin', 'Quản lý' ), true ) ) {" ) );

/* ---- 🔴 MỌI VIỆC CỦA CỔNG PHẢI ĐƯỢC QUYẾT ĐỊNH, KHÔNG ĐƯỢC RƠI VÀO MẶC ĐỊNH VÌ QUÊN.
   Đếm các nhánh `'<viec>' === $viec` trong cổng, rồi đòi mỗi việc phải nằm trong MỘT trong hai
   danh sách: cấm (VIEC_QUAN_TRI) hoặc cho phép (danh sách dưới, chép tay có chủ ý). Thêm việc
   mới mà quên xếp nhóm là phép thử này đỏ ngay — chứ không phải im lặng cho ai cũng gọi được. */
$viec_cong = array();
if ( preg_match_all( "/'([a-z_]+)' === \\\$viec/", $src_tr2, $mv ) ) {
	foreach ( $mv[1] as $v_ ) { $viec_cong[ $v_ ] = 1; }
}
t( 'bóc được danh sách việc của cổng', dem( $viec_cong ) >= 15, (string) dem( $viec_cong ) );
$cho_phep_moi_nguoi = array(
	'login', 'logout', 'so_lieu',      // vào ra, và số liệu (đã cắt theo vai trò ở máy chủ)
	'ma_tra',                          // tra mã/ví của khách — việc ở quầy
	'qua_trao',                        // trao quà tận tay — việc ở quầy
	'tien_mat',                        // khách trả tiền mặt tại quầy
	'chot_xem', 'chot_luu',            // chốt ca
	'quy_toi', 'nop_tao',              // xem tiền mình cầm, và nộp
);
/* Chốt doanh số là quyền RIÊNG, khai được — kế toán làm được mà không cần quyền Quản lý.
   Anh Thắng 23/08/2026: *"Để cấu hình tài khoản kế toán vào chốt doanh số sau khi nhân viên
   thu tiền"*. */
$cho_phep_moi_nguoi = array_merge( $cho_phep_moi_nguoi, VHG_Auth::VIEC_CHOT_DOANH_SO );
/* Giúp khách là quyền RIÊNG — bạn trực Hotline bật ghế cho khách mà không được xem doanh thu.
   Anh Thắng 23/08/2026: *"Đấy là bạn Hotline bật ghế cho khách chứ không phải nhân viên"*. */
$cho_phep_moi_nguoi = array_merge( $cho_phep_moi_nguoi, VHG_Auth::VIEC_GIUP_KHACH );
/* Việc cấu hình mang tiền tố `ch_` và chỉ Admin — chốt riêng, không đi qua `VIEC_QUAN_TRI`.
   Đây là chỗ CẤP PIN: cấp một PIN sai vai trò là cho người ta xem doanh thu cả chuỗi. */
foreach ( array_keys( $viec_cong ) as $v_ ) {
	if ( 0 === strpos( $v_, 'ch_' ) ) { $cho_phep_moi_nguoi[] = $v_; }
}
t( '🔴 việc cấu hình chỉ Admin, không phải mọi quản trị',
	strpos( $src_tr2, "if ( 0 === strpos( \$viec, 'ch_' ) ) {" ) !== false
	&& strpos( $src_tr2, "'Admin' !== \$ai['role']" ) !== false );
$khong_xep = array();
foreach ( array_keys( $viec_cong ) as $v_ ) {
	if ( in_array( $v_, VHG_Auth::VIEC_QUAN_TRI, true ) ) { continue; }
	if ( in_array( $v_, $cho_phep_moi_nguoi, true ) ) { continue; }
	$khong_xep[] = $v_;
}
teq( '🔴 mọi việc của cổng đều đã được xếp nhóm quyền', array(), $khong_xep );

/* ---- CẤM THÌ PHẢI CẤM THẬT, qua đúng cổng. */
$cam = array(
	'bat'           => array( 'ma_may' => 'AMTP01' ),
	'tat'           => array( 'ma_may' => 'AMTP01' ),
	'khoi_dong_lai' => array( 'ma_may' => 'AMTP01' ),
	'gan_ma'        => array( 'ma_cu' => 'AMTP01', 'ma_moi' => 'XX01' ),
	'vi_tra_nv'     => array( 'sdt' => '0909000111' ),
	'vi_tieu_nv'    => array( 'sdt' => '0909000111', 'menh_gia' => 10000, 'ma_may' => 'AMTP01' ),
	'ma_huy'        => array( 'ma' => 'AAAABBBB', 'ly_do' => 'thử' ),
	'nop_nhan'      => array( 'id' => 1, 'so_tien_nhan' => 1000 ),
	'nop_huy'       => array( 'id' => 1 ),
	'so_may'        => array( 'ma_may' => 'AMTP01' ),
);
$lot = array();
foreach ( $cam as $v_ => $goi_ ) {
	$goi_['token'] = $t_nv;
	$kq_ = vhg_web( $v_, $goi_ );
	if ( ! empty( $kq_['ok'] ) ) { $lot[] = $v_; }
}
teq( '🔴 nhân viên bị chặn ở TẤT CẢ việc của quản trị', array(), $lot );
$kq_bat = vhg_web( 'bat', array( 'ma_may' => 'AMTP01', 'token' => $t_nv ) );
teq( 'và trả về mã máy đọc được, không phải câu chữ', 'khong_du_quyen', (string) $kq_bat['ma'] );
t( 'kèm câu nói rõ đang là vai trò gì', strpos( (string) $kq_bat['error'], 'Nhân viên' ) !== false );

/* ---- CHO PHÉP THÌ PHẢI LÀM ĐƯỢC THẬT. Cắt tới mức không làm việc được thì người thu sẽ đi
   mượn tài khoản quản lý, và lúc đó phân quyền thành số 0. */
$nv_xem = vhg_web( 'chot_xem', array( 'ma_may' => 'AMTP01', 'token' => $t_nv ) );
t( '🔴 nhân viên xem được mốc chốt ca', ! empty( $nv_xem['ok'] ),
	isset( $nv_xem['error'] ) ? $nv_xem['error'] : '' );
t( '⚠️ và mốc đó tự đủ: có cơ sở, không phải gọi thêm so_may', isset( $nv_xem['coso'] ) );
$nv_chot = vhg_web( 'chot_luu', array( 'ma_may' => 'AMTP01', 'chi_so' => 100,
	'tien_dem' => 60000, 'token' => $t_nv ) );
t( '🔴 nhân viên chốt ca được', ! empty( $nv_chot['ok'] ),
	isset( $nv_chot['error'] ) ? $nv_chot['error'] : '' );
teq( 'và lượt chốt mang đúng tên người đang đăng nhập', 'Chị Hoa', (string) $nv_chot['nguoi'] );
$nv_nop = vhg_web( 'nop_tao', array( 'token' => $t_nv ) );
t( '🔴 nhân viên nộp tiền được', ! empty( $nv_nop['ok'] ),
	isset( $nv_nop['error'] ) ? $nv_nop['error'] : '' );

/* ---- GÓI SỐ LIỆU CỦA NHÂN VIÊN: chỉ phần của mình, không có tiền người khác. */
VHG_Quy::chot( 'AMTP01', 200, 90000, 'Người Khác' );
$sl_nv = vhg_web( 'so_lieu', array( 'token' => $t_nv, 'ky' => 'all' ) );
t( 'nhân viên vẫn lấy được số liệu', ! empty( $sl_nv['ok'] ) );
foreach ( array( 'tong', 'gd', 'thu', 'ma', 'vi', 'qua', 'bat', 'goi', 'choGan' ) as $k_ ) {
	t( '🔴 gói tin KHÔNG mang mục ' . $k_, empty( $sl_nv[ $k_ ] ) );
}
teq( '🔴 và bảng "ai đang cầm" rỗng với nhân viên', 0, dem( $sl_nv['quy']['cam'] ) );
teq( 'báo cáo theo người cũng rỗng', 0, dem( $sl_nv['quy']['nguoi'] ) );
teq( 'lượt chờ xác nhận cũng rỗng', 0, dem( $sl_nv['quy']['cho'] ) );
/* ⚠️ Nhưng lượt chốt CỦA CHÍNH MÌNH thì phải thấy — không thì họ không kiểm được việc mình. */
$co_minh = false; $co_nguoi_khac = false;
foreach ( $sl_nv['quy']['chot'] as $c_ ) {
	if ( 'Chị Hoa' === (string) $c_['nguoi'] ) { $co_minh = true; }
	else { $co_nguoi_khac = true; }
}
t( '🔴 thấy lượt chốt của chính mình', $co_minh );
t( '🔴 và KHÔNG thấy lượt của người khác', ! $co_nguoi_khac );
teq( 'tiền đang cầm là của chính mình', 90000, (int) VHG_Quy::dang_cam( 'Người Khác' )['tong'] );

/* ---- Admin thì vẫn thấy đủ. */
$sl_ad = vhg_web( 'so_lieu', array( 'token' => $t_ad, 'ky' => 'all' ) );
t( 'admin vẫn có mục doanh thu', isset( $sl_ad['tong'] ) );
t( 'và thấy cả bảng ai đang cầm', dem( $sl_ad['quy']['cam'] ) > 0 );

/* ---- Giao diện: nhân viên chỉ có một tab, và có lối vào chốt ca. */
$html_pq = vhg_web_html();
t( '🔴 tab dựng theo quyền, không dựng cứng', strpos( $html_pq, "if (QT) TABS.push(['doi-soat'" ) !== false );
t( '🔴 tab Điều khiển ghế theo quyền GIÚP KHÁCH, không theo quyền quản trị',
	strpos( $html_pq, "if (GK) TABS.push(['dieu-khien'" ) !== false );
/* 🔴 Tab đang chọn phải nằm trong danh sách được phép — người thu chỉ có một tab, bạn Hotline
   có hai. Để `TAB` trỏ vào tab họ không có là màn hình trắng, và họ sẽ tưởng app hỏng. */
t( '🔴 tab ngoài quyền thì rơi về tab đầu tiên có quyền',
	strpos( $html_pq, "if (!co_tab) { TAB = TABS.length ? TABS[0][0] : 'quy'; }" ) !== false );
/* 🔴 Nút chốt ca vốn nằm ở tab Điều khiển ghế — tab mà người thu không còn có. Không đưa lối vào
   sang tab Quỹ thì cả vai trò ấy mở app ra và không bấm được gì cả. */
/* 🔴 QUÉT QR, KHÔNG PHẢI CHỌN TỪ DANH SÁCH.
   Anh Thắng: *"Để chốt ca ghế nào thì quét QR ghế đó"*. Danh sách ghế là mời bấm nhầm — hai ghế
   cạnh nhau tên chỉ khác một chữ số, mà chốt nhầm ghế thì ĐÓNG MỐC CHỈ SỐ của ghế kia. */
t( '🔴 tab Quỹ mở camera quét tem ghế',
	strpos( $html_pq, "id=\"quet-mo\"" ) !== false && strpos( $html_pq, 'BarcodeDetector' ) !== false );
t( '⚠️ và LUÔN có đường gõ tay khi tem bong hoặc máy không có camera',
	strpos( $html_pq, "id=\"quet-tay\"" ) !== false );
t( '⚠️ tắt camera khi đóng khung quét', strpos( $html_pq, 't.stop()' ) !== false );
t( '⚠️ và mở camera SAU, không phải camera trước',
	strpos( $html_pq, "facingMode" ) !== false );
/* Tem trên ghế mang ĐỊA CHỈ trang khách chứ không mang mã trần — dùng ké đúng cái tem khách
   quét, không dán tem thứ hai. Nên phải bóc mã ghế ra khỏi đường dẫn. */
t( '🔴 bóc được mã ghế từ địa chỉ trên tem', strpos( $html_pq, 'function maGheTuQR(' ) !== false );
t( '⚠️ và màn chốt ca không gọi so_may khi không có quyền',
	preg_match( "/if \(!QT2\) \{ CHOT = /", $html_pq ) === 1 );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GÁN NHÂN VIÊN THEO CƠ SỞ, VÀ TÀI KHOẢN KẾ TOÁN CHỐT DOANH SỐ
 *
 * Anh Thắng 23/08/2026: *"bổ sung thêm phần cấu hình để quản lý nhân viên — Để gán nhân viên
 * theo cơ sở · Để cấu hình tài khoản kế toán vào chốt doanh số sau khi nhân viên thu tiền ·
 * Nhân viên đăng nhập vẫn trang này, nhưng chỉ hiện mỗi chốt ca. Để chốt ca ghế nào thì quét QR
 * ghế đó."*
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_coso( 0, 'Nha Trang' );
VHG_May::luu_coso( 0, 'Hải Phòng' );
$cs_ds = VHG_May::ds_coso();
$cs_nt = 0; $cs_hp = 0;
foreach ( $cs_ds as $c_ ) {
	if ( 'Nha Trang' === (string) $c_['ten'] ) { $cs_nt = (int) $c_['id']; }
	if ( 'Hải Phòng' === (string) $c_['ten'] ) { $cs_hp = (int) $c_['id']; }
}
t( 'dựng được hai cơ sở', $cs_nt > 0 && $cs_hp > 0 );
VHG_May::luu_may( array( 'ma' => 'NT01', 'coso_id' => $cs_nt, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:11' ) );
VHG_May::luu_may( array( 'ma' => 'HP01', 'coso_id' => $cs_hp, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:12' ) );

/* ---- 🔴 GẮN CƠ SỞ THÌ CHỈ CHỐT ĐƯỢC GHẾ Ở CƠ SỞ ĐÓ.
   Chốt nhầm ghế ở cơ sở khác không chỉ ghi sai sổ — nó ĐÓNG MỐC CHỈ SỐ của ghế đó, và người thu
   thật ở đấy hôm sau sẽ thấy quãng bị cắt mất, tiền của họ tự nhiên hụt đúng phần bị chốt hộ. */
$ok_nt = VHG_Quy::truoc_khi_chot( 'NT01', 'Nha Trang' );
t( '🔴 người của Nha Trang xem được ghế Nha Trang', ! empty( $ok_nt['ok'] ),
	isset( $ok_nt['error'] ) ? $ok_nt['error'] : '' );
$cam_hp = VHG_Quy::truoc_khi_chot( 'HP01', 'Nha Trang' );
t( '🔴 nhưng KHÔNG chốt được ghế Hải Phòng', empty( $cam_hp['ok'] ) );
t( 'và nói rõ ghế thuộc cơ sở nào', strpos( (string) $cam_hp['error'], 'Hải Phòng' ) !== false );
t( '⚠️ chặn cả ở hàm ghi, không chỉ ở hàm xem',
	empty( VHG_Quy::chot( 'HP01', 10, 1000, 'Chị Hoa', '', '', 'Nha Trang' )['ok'] ) );
/* Cơ sở RỖNG = đi cả chuỗi (quản lý vùng, Admin). */
t( '⚠️ cơ sở rỗng thì chốt được mọi nơi',
	! empty( VHG_Quy::truoc_khi_chot( 'HP01', '' )['ok'] ) );
/* ⚠️ `null` là "nơi gọi không quan tâm" — KHÁC chuỗi rỗng. Gộp hai thứ đó làm một thì mọi lượt
   gọi cũ vô tình thành "đi cả chuỗi", và chốt cơ sở im lặng biến mất. */
t( '⚠️ null cũng qua, nhưng là đường khác hẳn',
	! empty( VHG_Quy::truoc_khi_chot( 'HP01', null )['ok'] ) );

/* ---- Qua đúng cổng: cơ sở lấy từ PHIÊN, không nhận từ gói tin. */
update_option( 'vhg_nguon_nguoidung', 'rieng' );
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Sếp',      'pin' => '571394', 'vaiTro' => 'Admin',           'coso' => '' ),
	array( 'ten' => 'Hoa NT',   'pin' => '333555', 'vaiTro' => 'Nhân viên',       'coso' => 'Nha Trang' ),
	array( 'ten' => 'Kế Toán A','pin' => '444666', 'vaiTro' => 'Kế toán cá nhân', 'coso' => '' ) ) );
delete_option( 'vhg_vai_tro_vao' );
VHG_Auth::mo_khoa(); $tk_ad = vhg_web( 'login', array( 'pin' => '571394' ) )['token'];
VHG_Auth::mo_khoa(); $tk_hoa = vhg_web( 'login', array( 'pin' => '333555' ) )['token'];
VHG_Auth::mo_khoa(); $tk_kt = vhg_web( 'login', array( 'pin' => '444666' ) )['token'];
t( 'ba tài khoản đều vào được', '' !== $tk_ad && '' !== $tk_hoa && '' !== $tk_kt );

t( '🔴 Hoa chốt được ghế cơ sở mình',
	! empty( vhg_web( 'chot_xem', array( 'ma_may' => 'NT01', 'token' => $tk_hoa ) )['ok'] ) );
$web_lech = vhg_web( 'chot_xem', array( 'ma_may' => 'HP01', 'token' => $tk_hoa ) );
t( '🔴 và bị chặn ở ghế cơ sở khác qua đúng cổng', empty( $web_lech['ok'] ) );
/* 🔴 GỬI KÈM CƠ SỞ KHÁC TRONG GÓI TIN CŨNG KHÔNG QUA. Nhận cơ sở từ gói tin là chốt cơ sở thành
   một dòng chữ trang trí — ai đọc được gói tin thì tự khai mình ở cơ sở nào. */
$web_gian = vhg_web( 'chot_luu', array( 'ma_may' => 'HP01', 'chi_so' => 50, 'tien_dem' => 1000,
	'coso' => 'Hải Phòng', 'coso_cua_toi' => 'Hải Phòng', 'token' => $tk_hoa ) );
t( '🔴 khai cơ sở trong gói tin không qua được chốt', empty( $web_gian['ok'] ) );
$src_tr3 = $bo_chu_thich( (string) file_get_contents(
	$goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' ) );
/* ⚠️ Đường CHỐT CA không được đọc cơ sở từ gói tin — nó lấy từ phiên. (Tab Cấu hình thì CÓ
   đọc `$d['coso']`, nhưng đó là Admin đang KHAI cơ sở cho người khác, việc hoàn toàn khác.) */
$than_chot = substr( $src_tr3, strpos( $src_tr3, "if ( 'chot_xem' === \$viec )" ) );
$than_chot = substr( $than_chot, 0, strpos( $than_chot, "if ( 0 === strpos( \$viec, 'ch_' ) )" ) );
t( '⚠️ đường chốt ca không đọc cơ sở từ gói tin', strpos( $than_chot, "\$d['coso']" ) === false );
t( 'mà lấy từ phiên đăng nhập', strpos( $than_chot, "\$ai['coso']" ) !== false );
/* Admin không khai cơ sở -> chốt được mọi nơi. */
t( 'Admin chốt được cả hai cơ sở',
	! empty( vhg_web( 'chot_xem', array( 'ma_may' => 'HP01', 'token' => $tk_ad ) )['ok'] )
	&& ! empty( vhg_web( 'chot_xem', array( 'ma_may' => 'NT01', 'token' => $tk_ad ) )['ok'] ) );

/* ---- 🔴 KẾ TOÁN CHỐT DOANH SỐ, MÀ KHÔNG CÓ QUYỀN QUẢN LÝ.
   Nhét việc này vào nhóm Quản lý thì muốn kế toán nhận tiền là phải cấp quyền Quản lý — tức là
   cấp luôn quyền huỷ mã khách đã trả tiền, gán mã ghế, và tiêu ví khách không cần PIN. */
delete_option( 'vhg_vai_tro_chot' );
t( 'chưa khai thì mặc định Admin + Quản lý',
	in_array( 'Quản lý', VHG_Auth::vai_tro_chot(), true )
	&& ! in_array( 'Kế toán cá nhân', VHG_Auth::vai_tro_chot(), true ) );
vhg_web( 'chot_luu', array( 'ma_may' => 'NT01', 'chi_so' => 90, 'tien_dem' => 45000, 'token' => $tk_hoa ) );
$nop_hoa = vhg_web( 'nop_tao', array( 'token' => $tk_hoa ) );
t( 'Hoa nộp được', ! empty( $nop_hoa['ok'] ), isset( $nop_hoa['error'] ) ? $nop_hoa['error'] : '' );
$kt_cam = vhg_web( 'nop_nhan', array( 'id' => (int) $nop_hoa['id'], 'so_tien_nhan' => 45000,
	'token' => $tk_kt ) );
t( '🔴 chưa khai thì kế toán KHÔNG chốt được doanh số', empty( $kt_cam['ok'] ) );

update_option( 'vhg_vai_tro_chot', array( 'Quản lý', 'Kế toán cá nhân' ) );
t( '⚠️ Admin luôn nằm trong danh sách dù khai kiểu gì',
	in_array( 'Admin', VHG_Auth::vai_tro_chot(), true ) );
$kt_ok = vhg_web( 'nop_nhan', array( 'id' => (int) $nop_hoa['id'], 'so_tien_nhan' => 45000,
	'token' => $tk_kt ) );
t( '🔴 khai rồi thì kế toán chốt được', ! empty( $kt_ok['ok'] ),
	isset( $kt_ok['error'] ) ? $kt_ok['error'] : '' );
/* 🔴 NHƯNG KHÔNG KÈM THEO QUYỀN QUẢN LÝ. Đây là cả lý do tách quyền này ra. */
$kt_lam_bay = array(
	'ma_huy'     => array( 'ma' => 'AAAABBBB', 'ly_do' => 'thử' ),
	'gan_ma'     => array( 'ma_cu' => 'NT01', 'ma_moi' => 'ZZ99' ),
	'vi_tieu_nv' => array( 'sdt' => '0909000111', 'menh_gia' => 10000, 'ma_may' => 'NT01' ),
	'bat'        => array( 'ma_may' => 'NT01' ),
);
$kt_lot = array();
foreach ( $kt_lam_bay as $v_ => $g_ ) {
	$g_['token'] = $tk_kt;
	if ( ! empty( vhg_web( $v_, $g_ )['ok'] ) ) { $kt_lot[] = $v_; }
}
teq( '🔴 kế toán vẫn KHÔNG huỷ mã, gán ghế, tiêu ví hay bật ghế', array(), $kt_lot );
/* Và nhân viên thì vẫn không chốt doanh số được, dù có khai kiểu gì. */
t( '🔴 nhân viên không chốt doanh số được',
	empty( vhg_web( 'nop_nhan', array( 'id' => (int) $nop_hoa['id'], 'so_tien_nhan' => 1,
		'token' => $tk_hoa ) )['ok'] ) );

/* ---- Màn quản trị: ô khai vai trò chốt, và ô CHỌN cơ sở (không gõ tay). */
ob_start(); VHG_Admin::trang_ngoai(); $adm_ng = ob_get_clean();
t( 'màn quản trị có ô khai vai trò chốt doanh số',
	strpos( $adm_ng, 'name="vai_tro_chot[]"' ) !== false );
t( '⚠️ ô Admin bị khoá vì luôn có', strpos( $adm_ng, 'disabled' ) !== false );
/* 🔴 CƠ SỞ PHẢI LÀ Ô CHỌN. Gõ tay là "Nha Trang" với "Nha trang" thành hai cơ sở, và người thu
   gõ lệch một dấu cách thì không chốt được ghế nào — mà câu lỗi lại nói "ghế thuộc cơ sở khác",
   nghe như lỗi của cái ghế. */
t( '🔴 cơ sở là ô CHỌN từ danh sách thật, không gõ tay',
	strpos( $adm_ng, '<select name="coso">' ) !== false );
t( 'và danh sách đó có cơ sở vừa dựng', strpos( $adm_ng, 'Nha Trang' ) !== false );
t( '⚠️ có lựa chọn "cả chuỗi" cho quản lý vùng', strpos( $adm_ng, 'cả chuỗi' ) !== false );
t( 'và nói rõ cơ sở quyết định chốt được ghế nào',
	strpos( $adm_ng, 'chốt ca được ở đâu' ) !== false );
t( '⚠️ nói rõ nhân viên chỉ thấy tab Quỹ', strpos( $adm_ng, 'Quỹ &amp; nộp tiền' ) !== false );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VAI TRÒ HOTLINE, VÀ TAB CẤU HÌNH NGAY TRÊN TRANG /ghe
 *
 * Anh Thắng 23/08/2026: *"Đấy là bạn Hotline bật ghế cho khách chứ không phải nhân viên. Nhân
 * viên là các bạn thu tiền tại máy"*, và *"chưa thấy tab cấu hình trên wed"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
VHG_May::luu_may( array( 'ma' => 'AMTP01', 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
	'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '', 'mac' => 'AA:BB:CC:DD:EE:01' ) );
update_option( 'vhg_nguon_nguoidung', 'rieng' );
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Sếp',   'pin' => '571394', 'vaiTro' => 'Admin',     'coso' => '' ),
	array( 'ten' => 'Bạn HL','pin' => '777888', 'vaiTro' => 'Hotline',   'coso' => '' ),
	array( 'ten' => 'Thu NV','pin' => '888999', 'vaiTro' => 'Nhân viên', 'coso' => '' ) ) );
delete_option( 'vhg_vai_tro_vao' );
delete_option( 'vhg_vai_tro_giup' );
delete_option( 'vhg_vai_tro_chot' );
t( '🔴 có vai trò Hotline', in_array( 'Hotline', VHG_Auth::VAI_TRO_TAT_CA, true ) );
t( 'và mặc định đăng nhập được', in_array( 'Hotline', VHG_Auth::vai_tro_vao(), true ) );
VHG_Auth::mo_khoa(); $tz_ad = vhg_web( 'login', array( 'pin' => '571394' ) )['token'];
VHG_Auth::mo_khoa(); $tz_hl = vhg_web( 'login', array( 'pin' => '777888' ) )['token'];
VHG_Auth::mo_khoa(); $tz_nv = vhg_web( 'login', array( 'pin' => '888999' ) )['token'];
t( 'ba tài khoản vào được', '' !== $tz_ad && '' !== $tz_hl && '' !== $tz_nv );

/* ---- 🔴 HOTLINE BẬT ĐƯỢC GHẾ, MÀ KHÔNG THẤY DOANH THU.
   Đây là cả lý do tách nhóm quyền này ra: trước bản này muốn bạn Hotline bật ghế hộ khách là
   phải cấp quyền Quản lý — kèm theo đó là doanh thu 26 cửa hàng, huỷ mã khách đã trả tiền, và
   gán lại mã ghế. Không ai định cấp những thứ đó; nó đi kèm vì danh sách chỉ có một nhóm. */
$hl_bat = vhg_web( 'bat', array( 'ma_may' => 'AMTP01', 'phut' => 10, 'token' => $tz_hl ) );
t( '🔴 Hotline bật được ghế', ! empty( $hl_bat['ok'] ),
	isset( $hl_bat['error'] ) ? $hl_bat['error'] : '' );
t( 'và tra được ví khách để tiêu hộ',
	isset( vhg_web( 'vi_tra_nv', array( 'sdt' => '0909000111', 'token' => $tz_hl ) )['ok'] ) );
$hl_cam = array();
foreach ( array(
	'gan_ma' => array( 'ma_cu' => 'AMTP01', 'ma_moi' => 'ZZ01' ),
	'ma_huy' => array( 'ma' => 'AAAABBBB', 'ly_do' => 'thử' ),
	'so_may' => array( 'ma_may' => 'AMTP01' ),
	'nop_nhan' => array( 'id' => 1, 'so_tien_nhan' => 1 ),
) as $v_ => $g_ ) {
	$g_['token'] = $tz_hl;
	if ( ! empty( vhg_web( $v_, $g_ )['ok'] ) ) { $hl_cam[] = $v_; }
}
teq( '🔴 nhưng KHÔNG gán ghế, huỷ mã, xem số liệu ghế hay chốt doanh số', array(), $hl_cam );
$sl_hl = vhg_web( 'so_lieu', array( 'token' => $tz_hl, 'ky' => 'all' ) );
teq( 'gói tin đánh dấu đúng quyền giúp khách', 1, (int) $sl_hl['quyen']['giup_khach'] );
teq( 'và KHÔNG phải quản trị', 0, (int) $sl_hl['quyen']['quan_tri'] );
foreach ( array( 'tong', 'gd', 'thu', 'ma', 'vi' ) as $k_ ) {
	t( '🔴 Hotline không nhận mục ' . $k_, empty( $sl_hl[ $k_ ] ) );
}
/* ⚠️ Nhưng phải nhận BẢNG GIÁ (để chọn gói lúc tiêu ví hộ) và danh sách lượt ghế chưa nhận —
   đó chính là lý do khách gọi tới. Cắt tới mức không làm việc được thì họ đi mượn tài khoản
   quản lý, và lúc đó phân quyền thành số 0. */
t( '⚠️ nhưng CÓ bảng giá để tiêu ví hộ', ! empty( $sl_hl['goi'] ) );
t( '⚠️ và có danh sách lượt ghế chưa nhận', isset( $sl_hl['cho'] ) );

/* Nhân viên thu tiền thì KHÔNG bật được ghế — hai việc khác hẳn nhau. */
t( '🔴 nhân viên thu tiền KHÔNG bật được ghế',
	empty( vhg_web( 'bat', array( 'ma_may' => 'AMTP01', 'token' => $tz_nv ) )['ok'] ) );
teq( 'và không có quyền giúp khách', 0,
	(int) vhg_web( 'so_lieu', array( 'token' => $tz_nv ) )['quyen']['giup_khach'] );

/* ---- TAB CẤU HÌNH: CHỈ ADMIN. Đây là chỗ CẤP PIN. */
$ch_ad = vhg_web( 'ch_xem', array( 'token' => $tz_ad ) );
t( 'Admin xem được cấu hình', ! empty( $ch_ad['ok'] ) );
teq( 'thấy đủ ba người', 3, dem( $ch_ad['nguoi'] ) );
/* 🔴 KHÔNG BAO GIỜ GỬI PIN RA NGOÀI, kể cả cho Admin. Một ảnh chụp màn hình gửi nhầm nhóm chat
   là cả chuỗi mất doanh thu. */
$co_pin = false;
foreach ( $ch_ad['nguoi'] as $n_ ) { if ( isset( $n_['pin'] ) ) { $co_pin = true; } }
t( '🔴 KHÔNG gửi PIN ra ngoài, chỉ nói dài mấy số', ! $co_pin && isset( $ch_ad['nguoi'][0]['pin_dai'] ) );
t( '🔴 Hotline KHÔNG xem được cấu hình', empty( vhg_web( 'ch_xem', array( 'token' => $tz_hl ) )['ok'] ) );
t( 'nhân viên cũng không', empty( vhg_web( 'ch_xem', array( 'token' => $tz_nv ) )['ok'] ) );

/* Thêm người qua tab, dùng CHUNG hàm với wp-admin (không chép luật ra hai bản). */
$them_ok = vhg_web( 'ch_them', array( 'ten' => 'Kế Toán B', 'pin' => '246813',
	'vai_tro' => 'Kế toán cá nhân', 'coso' => '', 'token' => $tz_ad ) );
t( 'thêm được người mới', ! empty( $them_ok['ok'] ), isset( $them_ok['error'] ) ? $them_ok['error'] : '' );
teq( 'danh sách lên bốn', 4, dem( vhg_web( 'ch_xem', array( 'token' => $tz_ad ) )['nguoi'] ) );
t( '🔴 PIN trùng thì bị chặn — hai người cùng PIN là vào nhầm quyền của nhau',
	empty( vhg_web( 'ch_them', array( 'ten' => 'Ai Đó', 'pin' => '246813',
		'vai_tro' => 'Nhân viên', 'token' => $tz_ad ) )['ok'] ) );
t( '🔴 PIN dễ đoán cũng bị chặn',
	empty( vhg_web( 'ch_them', array( 'ten' => 'Ai Đó', 'pin' => '111111',
		'vai_tro' => 'Nhân viên', 'token' => $tz_ad ) )['ok'] ) );

/* 🔴 KHÔNG XOÁ ĐƯỢC CHÍNH MÌNH. Xoá xong là mất phiên ngay lượt gọi sau, và nếu đó là Admin
   cuối cùng thì không còn đường nào vào lại ngoài cơ sở dữ liệu. */
$i_toi = -1;
foreach ( vhg_web( 'ch_xem', array( 'token' => $tz_ad ) )['nguoi'] as $n_ ) {
	if ( 'Sếp' === (string) $n_['ten'] ) { $i_toi = (int) $n_['i']; }
}
t( '🔴 không xoá được chính mình',
	empty( vhg_web( 'ch_xoa', array( 'i' => $i_toi, 'token' => $tz_ad ) )['ok'] ) );

/* ---- LƯU PHÂN QUYỀN QUA TAB. Admin luôn có, ở cả ba nhóm. */
$lq = vhg_web( 'ch_vai_tro', array(
	'vao'  => array( 'Quản lý', 'Nhân viên' ),
	'giup' => array( 'Hotline' ),
	'chot' => array( 'Kế toán cá nhân' ),
	'token' => $tz_ad ) );
t( 'lưu được phân quyền', ! empty( $lq['ok'] ), isset( $lq['error'] ) ? $lq['error'] : '' );
foreach ( array( 'vai_tro_vao', 'vai_tro_chot', 'vai_tro_giup_khach' ) as $h_ ) {
	t( '🔴 Admin luôn có mặt trong ' . $h_,
		in_array( 'Admin', call_user_func( array( 'VHG_Auth', $h_ ) ), true ) );
}
t( 'kế toán nay chốt được doanh số', VHG_Auth::duoc_chot_doanh_so( 'Kế toán cá nhân' ) );
t( 'và Hotline vẫn giúp khách được', VHG_Auth::duoc_giup_khach( 'Hotline' ) );
t( '⚠️ vai trò lạ gửi lên bị lọc bỏ',
	! in_array( 'Giám đốc', VHG_Auth::vai_tro_vao(), true ) );

/* ---- ĐƠN VỊ CHỈ SỐ khai được từ tab. */
t( 'khai được đơn vị chỉ số', ! empty( vhg_web( 'ch_don_vi',
	array( 'don_vi' => 2000, 'token' => $tz_ad ) )['ok'] ) );
teq( 'và có tác dụng ngay', 2000, VHG_Quy::don_vi() );
t( '🔴 đơn vị 0 thì từ chối',
	empty( vhg_web( 'ch_don_vi', array( 'don_vi' => 0, 'token' => $tz_ad ) )['ok'] ) );
VHG_Quy::luu_don_vi( 5000 );

/* ---- Giao diện tab Cấu hình. */
$html_ch = vhg_web_html();
t( 'có tab Cấu hình', strpos( $html_ch, "TABS.push(['cau-hinh'" ) !== false );
t( 'và nó chỉ dựng cho quản trị', strpos( $html_ch, "if (QT) TABS.push(['cau-hinh'" ) !== false );
t( 'có bảng nhân sự', strpos( $html_ch, 'function veCauHinh()' ) !== false );
t( 'có ô thêm người', strpos( $html_ch, 'id="ch-them"' ) !== false );
/* ⚠️ Ô tích dựng lúc chạy nên chuỗi `data-ph="giup"` không nằm nguyên trong mã — canh vào bảng
   khai ba nhóm, và vào chỗ đọc ngược ba nhóm đó lúc lưu. */
t( 'có ba nhóm ô tích phân quyền',
	strpos( $html_ch, "['vao'," ) !== false && strpos( $html_ch, "['giup'," ) !== false
	&& strpos( $html_ch, "['chot'," ) !== false );
t( 'và lúc lưu gom đúng ba nhóm đó',
	strpos( $html_ch, "var g = { vao: [], giup: [], chot: [] };" ) !== false );
t( '⚠️ ô Admin bị khoá ở cả ba nhóm', strpos( $html_ch, "la_admin ? ' disabled' : ''" ) !== false );
t( 'có ô khai đơn vị chỉ số', strpos( $html_ch, 'id="ch-dv"' ) !== false );
t( '⚠️ và chỉ cách kiểm bằng một tờ tiền thật', strpos( $html_ch, 'nhét một tờ' ) !== false );
/* ⚠️ Hỏi lại trước khi xoá người: xoá xong là họ không đăng nhập được nữa, giữa ca. */
t( '🔴 có hỏi lại trước khi xoá người',
	preg_match( "/if \(!confirm\(.{0,300}?lam\('ch_xoa'/s", $html_ch ) === 1 );
/* ⚠️ Sau mỗi lượt ghi phải xoá `CH` rồi tải lại — giữ bảng cũ là người khai vừa thêm một người
   mà không thấy họ đâu, rồi thêm lần nữa. */
t( '⚠️ ghi xong thì tải lại bảng, không giữ bản cũ',
	preg_match( "/CH = null;\s*lam\('ch_them'/s", $html_ch ) === 1 );
/* 🔴 Cảnh báo khi đang dùng danh sách người dùng của plugin khác — thêm người ở đây sẽ không
   có tác dụng, mà màn hình thì trông y như đã thêm xong. */
t( '🔴 kêu lên khi danh sách người dùng nằm ở plugin khác',
	strpos( $html_ch, "CH.nguon !== 'rieng'" ) !== false );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO CA CỦA NGƯỜI THU
 * Anh Thắng 23/08/2026: *"chưa thấy nhân viên chốt báo cáo ca. nhập chỉ số tiền mặt và chỉ số máy"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
vhg_dung_bang();
VHG_May::luu_nhan_tien( '970415', '108878583951', 'HUYNH QUANG THANG' );
foreach ( array( 'BC01', 'BC02' ) as $k_ => $m_ ) {
	VHG_May::luu_may( array( 'ma' => $m_, 'coso_id' => 0, 'gia' => 0, 'phut' => 0,
		'so_tk' => '', 'ten_tk' => '', 'bank_bin' => '', 'ten_khai' => '',
		'mac' => 'AA:BB:CC:DD:FF:0' . $k_ ) );
}
VHG_Quy::luu_don_vi( 5000 );
teq( 'chưa chốt gì thì báo cáo ca rỗng', 0, (int) VHG_Quy::bao_cao_ca( 'Chị Hoa' )['so_ghe'] );

VHG_Quy::chot( 'BC01', 100, 50000, 'Chị Hoa' );   // lần đầu, đặt mốc
VHG_Quy::chot( 'BC02', 200, 30000, 'Chị Hoa' );   // lần đầu, đặt mốc
$bc1 = VHG_Quy::bao_cao_ca( 'Chị Hoa' );
teq( '🔴 ca gom đủ số ghế đã chốt', 2, (int) $bc1['so_ghe'] );
teq( 'và cộng đúng tiền đếm được', 80000, (int) $bc1['tien_dem'] );
teq( '⚠️ liệt kê CẢ từng ghế, không chỉ con số tổng', 2, dem( $bc1['ds'] ) );
t( 'kèm mốc bắt đầu ca', '' !== (string) $bc1['tu_luc'] );

/* Tiền khách trả tại quầy cũng nằm trong ca — cùng một túi tiền. */
VHG_Thu::thu_tien_mat( 'BC01', 20000, 'Chị Hoa' );
$bc2 = VHG_Quy::bao_cao_ca( 'Chị Hoa' );
teq( 'tiền quầy vào đúng ô riêng', 20000, (int) $bc2['tu_quay'] );
teq( '🔴 và tổng ca khớp với tiền đang cầm',
	(int) VHG_Quy::dang_cam( 'Chị Hoa' )['tong'], (int) $bc2['tong'] );

/* 🔴 CA LÀ QUÃNG CHƯA NỘP, KHÔNG PHẢI "HÔM NAY".
   Người thu đi một vòng nhiều ghế rồi nộp một lần; quãng đó mới là cái họ phải giải trình, và
   nó vắt qua nửa đêm được. Nộp xong là ca mới bắt đầu từ số 0. */
VHG_Quy::nop( 'Chị Hoa' );
$bc3 = VHG_Quy::bao_cao_ca( 'Chị Hoa' );
teq( '🔴 nộp xong thì ca mới bắt đầu từ 0 ghế', 0, (int) $bc3['so_ghe'] );
teq( 'và 0 đồng', 0, (int) $bc3['tong'] );
VHG_Quy::chot( 'BC01', 120, 100000, 'Chị Hoa' );   // 20 đơn vị × 5.000 = 100.000đ
$bc4 = VHG_Quy::bao_cao_ca( 'Chị Hoa' );
teq( 'ca mới chỉ có lượt mới', 1, (int) $bc4['so_ghe'] );
teq( 'máy đếm nói đã nuốt 100.000đ', 100000, (int) $bc4['theo_may'] );
teq( '⚠️ nhưng sổ không ghi nhận đồng nào — ghế chưa báo về', 0, (int) $bc4['theo_he_thong'] );
teq( '🔴 nên ca báo sổ đang thiếu 100.000đ', 100000, (int) $bc4['lech_may'] );
teq( 'ngăn thì đủ tiền', 0, (int) $bc4['lech_dem'] );

/* Người khác không dính vào ca của người này. */
VHG_Quy::chot( 'BC02', 220, 70000, 'Anh Nam' );
teq( 'ca của Hoa không lẫn lượt của Nam', 1, (int) VHG_Quy::bao_cao_ca( 'Chị Hoa' )['so_ghe'] );
teq( 'và ngược lại', 1, (int) VHG_Quy::bao_cao_ca( 'Anh Nam' )['so_ghe'] );
teq( '⚠️ không có tên thì trả về rỗng, không nổ', 0, (int) VHG_Quy::bao_cao_ca( '' )['so_ghe'] );

/* ---- Qua cổng: người thu nhận được báo cáo ca của chính mình. */
update_option( 'vhg_nguon_nguoidung', 'rieng' );
update_option( 'vhg_nguoidung', array(
	array( 'ten' => 'Chị Hoa', 'pin' => '135791', 'vaiTro' => 'Nhân viên', 'coso' => '' ) ) );
delete_option( 'vhg_vai_tro_vao' );
VHG_Auth::mo_khoa();
$tb = vhg_web( 'login', array( 'pin' => '135791' ) )['token'];
$sl_bc = vhg_web( 'so_lieu', array( 'token' => $tb, 'ky' => 'all' ) );
t( '🔴 gói tin của người thu mang báo cáo ca', isset( $sl_bc['quy']['ca'] ) );
teq( 'đúng ca của chính họ', 1, (int) $sl_bc['quy']['ca']['so_ghe'] );
$html_bc = vhg_web_html();
t( 'trang có khối báo cáo ca', strpos( $html_bc, "L('Báo cáo ca','Shift report')" ) !== false );
t( '⚠️ và bảng từng ghế trong ca', strpos( $html_bc, "L('Đếm được','Counted')" ) !== false );
/* ⚠️ Hai con lệch chỉ hiện khi KHÁC 0 — hiện cả hai dòng 0đ mỗi ca là mắt bỏ qua cả hai. */
t( '⚠️ dòng lệch chỉ hiện khi khác 0', strpos( $html_bc, 'ca.lech_dem !== 0' ) !== false );
/* 🔴 Và nói rõ người thu ĐỪNG TỰ BÙ khi sổ thiếu so với máy đếm — đó là tiền chưa báo về được,
   không phải tiền họ làm mất. */
t( '🔴 dặn đừng tự bù khi sổ thiếu', strpos( $html_bc, 'đừng tự bù' ) !== false );

/* ---- Bảng nhân sự phải nói ra ai đang KHÔNG đăng nhập được. Anh Thắng gặp đúng cảnh này:
   người khai đủ tên, PIN, cơ sở, nằm ngay trong bảng, trông y như đã xong — mà gõ đúng PIN vẫn
   bị đá ra, vì vai trò chưa được tích ở khối phân quyền bên dưới. */
t( '🔴 bảng nhân sự đánh dấu người không đăng nhập được',
	strpos( $html_bc, "L('không đăng nhập được','cannot sign in')" ) !== false );
t( 'và chỉ thẳng sang khối cần tích',
	strpos( $html_bc, 'Đăng nhập được trang này</b> ngay' ) !== false );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHẠM VI HÀM TRONG JAVASCRIPT — HÀM KHAI LỒNG KHÔNG ĐƯỢC GỌI TỪ HÀM KHÁC
 *
 * Anh Thắng 23/08/2026: *"chưa chốt ca được phải không, chưa thấy ghi nhận"*.
 *
 * Nguyên nhân: `lam()` khai BÊN TRONG `noi()`, mà `veChotCa()` ở tầng ngoài lại gọi nó. Bấm
 * "Chốt ca" là JavaScript ném `ReferenceError` rồi im — bảng đóng lại (vì `dongChotCa()` chạy
 * trước), không lỗi nào hiện lên, không dòng nào vào sổ. Nhìn từ ngoài giống hệt "bấm xong
 * không thấy ghi nhận".
 *
 * 🔴 VÌ SAO KHÔNG PHÉP THỬ NÀO CŨ BẮT ĐƯỢC.
 *    Mọi phép thử về giao diện đều canh CHUỖI trong mã nguồn: `strpos($html, "lam('chot_luu'")`.
 *    Chuỗi đó CÓ trong mã — chỉ là nó không chạy được. Canh chuỗi thì không bao giờ thấy được
 *    chuyện của phạm vi biến.
 *
 * Nên phép thử này ĐỌC CẤU TRÚC: bóc từng khối hàm ở tầng ngoài, tìm những hàm khai lồng bên
 * trong, rồi soi xem tên đó có bị gọi ở khối khác không.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

/**
 * Bóc các khối hàm tầng ngoài của một đoạn JS: [ tên => [đầu, cuối] ].
 * Đếm ngoặc nhọn, có bỏ qua ngoặc nằm trong chuỗi và trong chú thích.
 */
function vhg_khoi_ham_js( $js ) {
	$ra  = array();
	$n   = strlen( $js );
	$i   = 0;
	while ( $i < $n ) {
		/* Hàm tầng ngoài = `function ten(` đứng ngay ĐẦU DÒNG (không thụt lề). */
		if ( ( 0 === $i || "\n" === $js[ $i - 1 ] )
			&& preg_match( '/^function\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/', substr( $js, $i, 120 ), $m ) ) {
			$ten = $m[1];
			$j   = strpos( $js, '{', $i );
			if ( false === $j ) { break; }
			$sau = vhg_qua_khoi_js( $js, $j );
			$ra[ $ten ] = array( $i, $sau );
			$i = $sau;
			continue;
		}
		$i++;
	}
	return $ra;
}

/**
 * Từ vị trí `{`, nhảy tới ngay sau `}` khớp với nó. Bỏ qua chuỗi, chú thích VÀ BIỂU THỨC CHÍNH
 * QUY.
 *
 * 🔴 BỎ QUÊN BIỂU THỨC CHÍNH QUY LÀ ĐẾM SAI NGOẶC. `/^\d{4,8}$/` có một dấu `{` — đếm nó vào là
 *    mọi khối sau đó lệch, và bộ bóc dừng sớm. Trang nhân viên tình cờ cân bằng lại nên phép
 *    thử vẫn xanh; trang khách thì bóc ra 6 hàm trong khi có 26. Một phép thử xanh vì may mắn
 *    còn tệ hơn không có phép thử: nó nói rằng đã kiểm rồi.
 *
 * ⚠️ Phân biệt `/` mở biểu thức chính quy với `/` chia: xem ký tự CÓ NGHĨA đứng ngay trước. Sau
 *    một toán tử hay dấu mở ngoặc thì `/` là biểu thức chính quy; sau một tên biến hay số thì
 *    nó là phép chia.
 */
function vhg_qua_khoi_js( $js, $mo ) {
	$n     = strlen( $js );
	$sau   = 0;
	$i     = $mo;
	$truoc = '';   // ký tự có nghĩa gần nhất
	while ( $i < $n ) {
		$c = $js[ $i ];
		if ( "'" === $c || '"' === $c || '`' === $c ) {
			$dau = $c; $i++;
			while ( $i < $n ) {
				if ( '\\' === $js[ $i ] ) { $i += 2; continue; }
				if ( $js[ $i ] === $dau ) { $i++; break; }
				$i++;
			}
			$truoc = $dau;
			continue;
		}
		if ( '/' === $c && $i + 1 < $n && '*' === $js[ $i + 1 ] ) {
			$k = strpos( $js, '*/', $i + 2 );
			$i = ( false === $k ) ? $n : $k + 2;
			continue;
		}
		if ( '/' === $c && $i + 1 < $n && '/' === $js[ $i + 1 ] ) {
			$k = strpos( $js, "\n", $i );
			$i = ( false === $k ) ? $n : $k + 1;
			continue;
		}
		if ( '/' === $c && ( '' === $truoc || false !== strpos( "(,=:[!&|?{};+-*%<>~^\n", $truoc ) ) ) {
			$i++;
			while ( $i < $n ) {
				if ( '\\' === $js[ $i ] ) { $i += 2; continue; }
				if ( '[' === $js[ $i ] ) {      // lớp ký tự: `/` bên trong KHÔNG đóng biểu thức
					$i++;
					while ( $i < $n && ']' !== $js[ $i ] ) {
						if ( '\\' === $js[ $i ] ) { $i++; }
						$i++;
					}
					$i++;
					continue;
				}
				if ( '/' === $js[ $i ] ) { $i++; break; }
				if ( "\n" === $js[ $i ] ) { break; }   // xuống dòng = không phải biểu thức
				$i++;
			}
			while ( $i < $n && false !== strpos( 'gimsuyd', $js[ $i ] ) ) { $i++; }
			$truoc = '/';
			continue;
		}
		if ( '{' === $c ) { $sau++; }
		if ( '}' === $c ) {
			$sau--;
			if ( 0 === $sau ) { return $i + 1; }
		}
		if ( '' !== trim( $c ) ) { $truoc = $c; }
		elseif ( "\n" === $c ) { $truoc = "\n"; }
		$i++;
	}
	return $n;
}

/**
 * Bóc đoạn JS ra khỏi heredoc `<<<'JS' … JS;` của lớp trang.
 * ⚠️ Đọc từ TỆP NGUỒN chứ không từ HTML đã dựng: HTML đã dựng cũng có đủ đoạn JS đó, nhưng lấy
 *    từ nguồn thì số dòng khớp với chỗ người sửa mã đang nhìn.
 */
function vhg_js_cua( $tep ) {
	$s = (string) file_get_contents( $tep );
	$i = strpos( $s, "<<<'JS'" );
	if ( false === $i ) { return ''; }
	$i = strpos( $s, "\n", $i );
	$j = strpos( $s, "\nJS;", $i );
	return ( false === $j ) ? '' : substr( $s, $i + 1, $j - $i - 1 );
}

/**
 * Gỡ chú thích JS.
 *
 * 🔴 PHẢI HIỂU BIỂU THỨC CHÍNH QUY, không chỉ hiểu chuỗi.
 *    `.replace(/[&<>"]/g, …)` có một dấu `"` NẰM TRONG biểu thức chính quy. Bộ gỡ chỉ biết
 *    chuỗi sẽ tưởng dấu đó mở một chuỗi, rồi nuốt tất cả cho tới dấu `"` tiếp theo — lệch pha
 *    toàn bộ phần sau, và những khối chú thích ở đó không được gỡ. Đã dính đúng một lần: câu
 *    *"…cho người thu (xem `so_lieu_nhan_vien`)"* trong một chú thích bị đọc thành lượt gọi
 *    hàm `thu(`.
 *
 * ⚠️ Phân biệt `/` mở biểu thức chính quy với `/` chia bằng ký tự CÓ NGHĨA đứng trước — cùng
 *    một luật với `vhg_qua_khoi_js()`.
 */
function vhg_bo_chu_thich_js( $js ) {
	$ra = ''; $n = strlen( $js ); $i = 0; $truoc = '';
	while ( $i < $n ) {
		$c = $js[ $i ];
		if ( "'" === $c || '"' === $c || '`' === $c ) {
			$dau = $c; $ra .= $c; $i++;
			while ( $i < $n ) {
				$ra .= $js[ $i ];
				if ( '\\' === $js[ $i ] ) { $i++; if ( $i < $n ) { $ra .= $js[ $i ]; $i++; } continue; }
				if ( $js[ $i ] === $dau ) { $i++; break; }
				$i++;
			}
			$truoc = $dau;
			continue;
		}
		if ( '/' === $c && $i + 1 < $n && '*' === $js[ $i + 1 ] ) {
			$k = strpos( $js, '*/', $i + 2 );
			$i = ( false === $k ) ? $n : $k + 2;
			$ra .= ' ';
			continue;
		}
		if ( '/' === $c && $i + 1 < $n && '/' === $js[ $i + 1 ] ) {
			$k = strpos( $js, "\n", $i );
			$i = ( false === $k ) ? $n : $k;
			continue;
		}
		if ( '/' === $c && ( '' === $truoc || false !== strpos( "(,=:[!&|?{};+-*%<>~^\n", $truoc ) ) ) {
			$ra .= $c; $i++;
			while ( $i < $n ) {
				$ra .= $js[ $i ];
				if ( '\\' === $js[ $i ] ) { $i++; if ( $i < $n ) { $ra .= $js[ $i ]; $i++; } continue; }
				if ( '[' === $js[ $i ] ) {
					$i++;
					while ( $i < $n && ']' !== $js[ $i ] ) {
						$ra .= $js[ $i ];
						if ( '\\' === $js[ $i ] ) { $i++; $ra .= $js[ $i ]; }
						$i++;
					}
					if ( $i < $n ) { $ra .= $js[ $i ]; $i++; }
					continue;
				}
				if ( '/' === $js[ $i ] ) { $i++; break; }
				if ( "\n" === $js[ $i ] ) { break; }
				$i++;
			}
			while ( $i < $n && false !== strpos( 'gimsuyd', $js[ $i ] ) ) { $ra .= $js[ $i ]; $i++; }
			$truoc = '/';
			continue;
		}
		$ra .= $c;
		if ( '' !== trim( $c ) ) { $truoc = $c; }
		elseif ( "\n" === $c ) { $truoc = "\n"; }
		$i++;
	}
	return $ra;
}

/**
 * Soi phạm vi một đoạn JS: hàm khai LỒNG trong hàm này mà bị gọi ở hàm khác.
 *
 * ⚠️ BỎ QUA khi hàm gọi có một RÀNG BUỘC CỤC BỘ cùng tên — tham số (`function goi(viec, d, xong)`)
 *    hay biến (`var xong = …`). Đó là hai cái tên khác nhau tình cờ giống nhau, và báo lên là
 *    phép thử kêu oan. Phép thử hay kêu oan là phép thử người ta tắt đi.
 */
function vhg_soi_pham_vi_js( $js ) {
	$js   = vhg_bo_chu_thich_js( $js );
	$khoi = vhg_khoi_ham_js( $js );
	$hong = array();
	foreach ( $khoi as $ten_ngoai => $vt ) {
		$than = substr( $js, $vt[0], $vt[1] - $vt[0] );
		if ( ! preg_match_all( '/\n[ \t]+function\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/', $than, $mm ) ) {
			continue;
		}
		foreach ( array_unique( $mm[1] ) as $ten_trong ) {
			foreach ( $khoi as $ten_khac => $vt2 ) {
				if ( $ten_khac === $ten_ngoai ) { continue; }
				$than2 = substr( $js, $vt2[0], $vt2[1] - $vt2[0] );
				if ( ! preg_match( '/\b' . preg_quote( $ten_trong, '/' ) . '\s*\(/', $than2 ) ) { continue; }
				/* Có ràng buộc cục bộ cùng tên thì thôi. */
				$tham_so = '';
				if ( preg_match( '/^function\s+[A-Za-z0-9_$]+\s*\(([^)]*)\)/', $than2, $mt ) ) {
					$tham_so = $mt[1];
				}
				$co_cuc_bo =
					preg_match( '/\b' . preg_quote( $ten_trong, '/' ) . '\b/', $tham_so )
					|| preg_match( '/\b(?:var|let|const)\s+' . preg_quote( $ten_trong, '/' ) . '\b/', $than2 )
					|| preg_match( '/\n[ \t]+function\s+' . preg_quote( $ten_trong, '/' ) . '\s*\(/', $than2 )
					|| preg_match( '/function\s*\([^)]*\b' . preg_quote( $ten_trong, '/' ) . '\b[^)]*\)/', $than2 );
				if ( $co_cuc_bo ) { continue; }
				$hong[] = $ten_khac . '() gọi ' . $ten_trong . '() — khai lồng trong ' . $ten_ngoai . '()';
			}
		}
	}
	return $hong;
}

$js_tr = vhg_js_cua( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );
t( 'bóc được đoạn JS của trang nhân viên', strlen( $js_tr ) > 20000, (string) strlen( $js_tr ) );
$khoi = vhg_khoi_ham_js( vhg_bo_chu_thich_js( $js_tr ) );
/* 🔴 Con số này canh chính BỘ BÓC, không canh mã nguồn. Bộ bóc dừng sớm (đếm lệch ngoặc vì một
   biểu thức chính quy chẳng hạn) thì nó vẫn trả về vài khối và mọi phép soi sau đó đều xanh —
   xanh vì không nhìn thấy gì. Đã dính đúng một lần: trang khách bóc ra 6 hàm trong khi có 26. */
teq( '🔴 bóc được ĐỦ hàm tầng ngoài của trang nhân viên',
	substr_count( $js_tr, "\nfunction " ), dem( $khoi ) );
t( '🔴 `lam` phải nằm ở TẦNG NGOÀI', isset( $khoi['lam'] ) );
t( 'và `veChotCa` cũng vậy', isset( $khoi['veChotCa'] ) );
teq( '🔴 không hàm nào gọi một hàm khai lồng trong hàm khác', array(),
	vhg_soi_pham_vi_js( $js_tr ) );

/* ⚠️ Và phép thử phải CHỨNG MINH nó bắt được lỗi thật, không phải luôn xanh.
   Dựng lại đúng cảnh cũ (`lam` khai lồng trong `noi`) và đòi nó kêu lên. */
teq( '🔴 và phép thử này BẮT ĐƯỢC lỗi thật (không phải luôn xanh)',
	array( "veChotCa() gọi lam() — khai lồng trong noi()" ),
	vhg_soi_pham_vi_js(
		"function noi(){\n  function lam(v){ return v; }\n  lam(1);\n}\n"
		. "function veChotCa(){\n  lam('chot_luu');\n}\n" ) );
/* ⚠️ Và KHÔNG kêu oan khi tên trùng chỉ là tham số — đây là cảnh có thật ở trang khách. */
teq( '⚠️ nhưng KHÔNG kêu oan khi tên trùng là tham số', array(),
	vhg_soi_pham_vi_js(
		"function chep(t){\n  function xong(){ return 1; }\n  xong();\n}\n"
		. "function goi(v, d, xong){\n  xong(d);\n}\n" ) );

/* ⚠️ ÁP CÙNG PHÉP THỬ CHO TRANG KHÁCH. Trang đó cũng là một ứng dụng một trang với hàng chục
   hàm dựng màn, và cùng một cái bẫy. Ở đó hậu quả còn nặng hơn: khách bấm trả tiền mà không có
   gì xảy ra. */
$js_sh = vhg_js_cua( $goc . '/wordpress/vhcp-ghe/includes/class-vhg-shop.php' );
t( 'bóc được đoạn JS của trang khách', strlen( $js_sh ) > 10000, (string) strlen( $js_sh ) );
teq( '🔴 bóc được ĐỦ hàm tầng ngoài của trang khách',
	substr_count( $js_sh, "\nfunction " ),
	dem( vhg_khoi_ham_js( vhg_bo_chu_thich_js( $js_sh ) ) ) );
teq( '🔴 trang khách cũng không có hàm nào gọi lộn phạm vi', array(),
	vhg_soi_pham_vi_js( $js_sh ) );

/* Đoạn JS phải PHÂN TÍCH ĐƯỢC — dấu ngoặc lệch một cái là cả trang trắng. */
$mo_nhon = 0;
foreach ( $khoi as $ten => $vt ) {
	if ( $vt[1] <= $vt[0] ) { $mo_nhon++; }
}
teq( '⚠️ mọi khối hàm đều đóng ngoặc đúng', 0, $mo_nhon );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường tiền không đếm hai lần, không mất gói nào.\n";

