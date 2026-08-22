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
foreach ( array( 'db', 'doc', 'may', 'thu', 'qr', 'nhap', 'cong' ) as $f ) {
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
teq( 'sơ đồ có đủ 8 bảng', 8, count( $so_do ) );
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
/* Soi MÃ chứ không soi chữ: khối chú thích đầu tệp có nhắc `DASHBOARD_PIN` để nói rõ bản cũ đã
   sai ở đâu — câu đó đáng giữ. Cấm cả chữ là ép xoá lời giải thích để qua bài kiểm. */
$ad_ma = preg_replace( '#/\*.*?\*/#s', '', $ad );
$ad_ma = preg_replace( '#//[^\n]*#', '', $ad_ma );
t( 'màn KHÔNG có PIN riêng trong mã (wp-admin đã gác)',
	stripos( $ad_ma, 'PIN' ) === false, $ad_ma ? '' : '' );
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

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường tiền không đếm hai lần, không mất gói nào.\n";
