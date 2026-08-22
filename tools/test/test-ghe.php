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

define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
define( 'VHG_KHOA_WEBHOOK', 'khoa-webhook-thu-nghiem' );
define( 'VHG_KHOA_MAY', 'khoa-may-thu-nghiem' );
foreach ( array( 'db', 'doc', 'may', 'thu', 'qr', 'nhap', 'cong', 'auth', 'trang' ) as $f ) {
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
teq( 'sơ đồ có đủ 9 bảng', 9, count( $so_do ) );
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
teq( 'ô có dấu phẩy KHÔNG bị vỡ cột khi đã có TAB', 2, count( $b2[1] ) );

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
$r = vhg_web( 'login', array( 'pin' => '446688' ) );
teq( 'Nhân viên KHÔNG vào được (mặc định hẹp)', false, $r['ok'] );
t( '⚠️ và nói "không được xem", KHÔNG nói "PIN sai" — nói sai thì người ta gõ lại tới lúc tự khoá',
	strpos( $r['error'], 'không được xem' ) !== false, $r['error'] );

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
teq( 'lượt đã trả mà ghế chưa nhận hiện ra', 1, count( $r['cho'] ) );
teq( 'giao dịch hiện ra', 1, count( $r['gd'] ) );
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
teq( 'nhịp trả đủ hai gói', 2, count( $n['goi'] ) );
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
t( 'có thanh tab chính', strpos( $html_dk, 'data-tab="dieu-khien"' ) !== false );
t( 'tab điều khiển vẽ THẺ từng ghế, không phải hàng bảng',
	strpos( $html_dk, 'ghe-luoi' ) !== false && strpos( $html_dk, 'function veDieuKhien()' ) !== false );
t( 'có nút khởi động lại', strpos( $html_dk, 'data-kd=' ) !== false );
t( 'và hỏi lại trước khi khởi động', strpos( $html_dk, 'Khởi động lại ghế' ) !== false );
/* ⚠️ Trạng thái ghế khai MỘT chỗ: hai tab cùng hiện nó, khai hai nơi là sớm muộn một tab nói
      "Rảnh" còn tab kia nói "Đang chạy" — và người đọc không biết tin tab nào. */
t( 'trạng thái ghế tính ở đúng một hàm',
	substr_count( $html_dk, 'function trangThai(m)' ) === 1
	&& substr_count( $html_dk, "'p-off','Mất kết nối'" ) === 1, $html_dk ? '' : '' );
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
	preg_match( '/CHUA_GAN \|\| CHAIR_ID\.length\(\)==0\) && millis\(\)-veLai > 5000/', $fw4 ) === 1 );

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
t( 'và nhắc bấm một lần thôi', strpos( $html_c, 'Bấm một lần thôi' ) !== false );
t( 'không cho xác nhận khi chưa nhập tiền',
	strpos( $html_c, 'Chưa nhập số tiền mặt' ) !== false );
/* Khoá chống bấm hai lần vẫn phải bao lấy đường này. */
t( 'vẫn đi qua khoá chống bấm hai lần', strpos( $html_c, "lam('tien_mat'" ) !== false );

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
teq( 'ghế đã khai mà chưa gán cơ sở -> đúng MỘT nhóm', 1, count( $t_cs['theo_coso'] ) );
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
teq( 'trang ngoài thấy ghế đang chờ gán', 1, count( $sl['choGan'] ) );
teq( 'kèm MAC để đối chiếu với nhãn dán trên ghế', 'AA:BB:CC:DD:12:34', $sl['choGan'][0]['mac'] );
t( 'và kèm danh sách cơ sở để chọn ngay tại chỗ', count( $sl['coso'] ) >= 1, $sl['coso'] );
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
t( 'cỡ ô tính theo kích thước thật của mã', strpos( $fw3, 'VUNG / (size + 2)' ) !== false );
t( 'màn QR vẫn in mã lượt để khách gõ tay được',
	strpos( $fw3, '"Noi dung: GHE"' ) !== false );

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

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường tiền không đếm hai lần, không mất gói nào.\n";
