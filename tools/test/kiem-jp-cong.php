<?php
/**
 * CỔNG JP — VÀ BẢN KIỂM ĐẾM CỦA CẢ CUỘC CHUYỂN
 * =============================================================================================
 *
 * Giao diện JP (11 tệp, ≈11.000 dòng) gọi máy chủ qua đúng một chỗ: `srv(fn, ...)`. Bài này
 * đọc THẲNG mấy tệp ấy trong `goc/jp-capsule-v2/`, bóc ra MỌI tên hàm giao diện thật sự gọi,
 * rồi đòi:
 *
 *        bảng ĐÃ CHUYỂN  +  bảng CHƯA CHUYỂN  =  đúng danh sách ấy
 *
 * không thừa, không thiếu. Nhờ vậy:
 *   · quên chuyển một hàm  -> đỏ, chứ không đợi người dùng bấm vào mới biết;
 *   · chuyển xong quên xoá khỏi bảng "chưa làm" -> đỏ;
 *   · giao diện gọi một tên không ai khai -> đỏ.
 *
 * 🔴 VÀ CANH HAI CHỐT CHẶN CỦA CỔNG:
 *   1. bảng hàm là danh sách CHO PHÉP — tên từ trình duyệt không được chạy hàm ngoài bảng;
 *   2. gác quyền ở MÁY CHỦ — bản Apps Script gác bên trong từng hàm, sót một chỗ là hở một cửa.
 *
 * Chạy: php tools/test/kiem-jp-cong.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

$plg = $goc . '/wordpress/vhcp-jp/includes/';
foreach ( array( 'db', 'doc', 'nguon', 'ma', 'nhat-ky', 'auth', 'cau-hinh', 'cong' ) as $f ) {
	require_once $plg . 'class-vhjp-' . $f . '.php';
}
global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
$_SERVER['REMOTE_ADDR'] = '10.0.0.7';

// ============================================================ 1. 🔴 BẢN KIỂM ĐẾM
$thu_muc = $goc . '/goc/jp-capsule-v2/';
t( '🔴 mã giao diện gốc còn trong kho', is_dir( $thu_muc ), $thu_muc );

$goi = array();
foreach ( glob( $thu_muc . '*.html' ) as $f ) {
	if ( preg_match_all( "/srv\(\s*'([A-Za-z_][A-Za-z0-9_]*)'/", file_get_contents( $f ), $m ) ) {
		foreach ( $m[1] as $x ) { $goi[ $x ] = 1; }
	}
}
$goi = array_keys( $goi );
sort( $goi );
teq( '🔴 giao diện gọi đúng 100 hàm máy chủ', 100, count( $goi ) );

/* Chốt chặn bộ bóc: con số 100 đổi theo mỗi lần giao diện thêm lệnh, nên nó KHÔNG nói được bóc
   có đúng không. Ba tên dưới đây nằm ở ba tệp khác nhau — bóc sai kiểu gì cũng trượt một cái. */
/* Ba tên THẬT, nằm ở ba tệp khác nhau (Js01_Core · KtJs02_Duyet · KtJs05_Kho) — bóc sai kiểu
   gì cũng trượt ít nhất một cái. ⚠️ Phải là tên CÓ THẬT: bản đầu em gõ `jpKhoNXT` cho gọn,
   nó không tồn tại, và phép "chốt chặn bộ bóc" tự nó thành sai. */
foreach ( array( 'jpLoginPin', 'jpKtApprove', 'jpKhoNhapXuatTon' ) as $x ) {
	t( "bóc được $x", in_array( $x, $goi, true ), implode( ', ', array_slice( $goi, 0, 5 ) ) . ' …' );
}

$da  = array_keys( VHJP_Cong::map() );
$chua = VHJP_Cong::chua_lam();
sort( $da ); sort( $chua );

teq( '🔴 không tên nào vừa ĐÃ LÀM vừa CHƯA LÀM', array(), array_values( array_intersect( $da, $chua ) ) );

$khai = $da;
foreach ( $chua as $x ) { $khai[] = $x; }
sort( $khai );

$thieu = array_values( array_diff( $goi, $khai ) );
t( '🔴 KHÔNG hàm nào giao diện gọi mà chưa ai khai', ! $thieu,
	count( $thieu ) . ' hàm bị bỏ quên: ' . implode( ', ', array_slice( $thieu, 0, 10 ) ) );

$thua = array_values( array_diff( $khai, $goi ) );
t( '🔴 và KHÔNG khai thừa hàm giao diện không hề gọi', ! $thua,
	count( $thua ) . ' hàm thừa: ' . implode( ', ', array_slice( $thua, 0, 10 ) ) );

/* Con số còn lại — in ra để mỗi lượt chạy là một lần báo tiến độ. */
echo '   · đã chuyển ' . count( $da ) . ' / ' . count( $goi ) . ' hàm · còn '
	. count( $chua ) . " hàm\n";

// ============================================================ 2. 🔴 Danh sách CHO PHÉP
$r = VHJP_Cong::goi( 'jpKhongHeCo', array() );
teq( 'tên lạ -> chối 400', 400, $r['ma'] );
/* Tên hàm đi vào từ trình duyệt. Mấy chuỗi dưới đây mà chạy được là người ngoài chạy được hàm
   bất kỳ của WordPress. */
foreach ( array( 'wp_delete_user', 'unlink', 'system', 'VHJP_DB::install', 'phpinfo' ) as $doc ) {
	teq( '🔴 chặn tên độc: ' . $doc, 400, VHJP_Cong::goi( $doc, array( '/etc/passwd' ) )['ma'] );
}
/* Hàm chưa chuyển thì nói rõ là CHƯA CHUYỂN — khác hẳn "không có lệnh này". */
teq( '🔴 hàm chưa chuyển -> 501, không phải 400', 501,
	VHJP_Cong::goi( 'jpKhoNhapXuatTon', array( 'x' ) )['ma'] );

// ============================================================ 3. Cổng đòi thẻ phiên
teq( 'không thẻ -> 401', 401, VHJP_Cong::goi( 'jpCfgListLocations', array( '' ) )['ma'] );
teq( 'thẻ bịa -> 401',   401, VHJP_Cong::goi( 'jpCfgListLocations', array( 'the-bia' ) )['ma'] );
/* Giao diện JP bắt lỗi THEO CHUỖI (`Js01_Core.html`): `SESSION_EXPIRED` bày lại ô PIN. Đổi chữ
   ở đây là giao diện im lặng không hiểu và người dùng kẹt ở màn trắng. */
teq( '🔴 giữ nguyên chữ SESSION_EXPIRED cho giao diện bắt được', 'SESSION_EXPIRED',
	VHJP_Cong::goi( 'jpCfgListLocations', array( 'the-bia' ) )['than']['error'] );

VHJP_Nguon::them( 'JP_Users', array( 'id' => 'U-1', 'hoTen' => 'Chị KT',
	'role' => VHJP_Auth::VAI_KT, 'pin' => VHJP_Auth::bam( '357' ) ) );
VHJP_Nguon::them( 'JP_Users', array( 'id' => 'U-2', 'hoTen' => 'Bạn NV',
	'role' => VHJP_Auth::VAI_NV, 'pin' => VHJP_Auth::bam( '468' ) ) );

$kt = VHJP_Cong::goi( 'jpLoginPin', array( '357' ) );
teq( 'đăng nhập qua cổng: 200', 200, $kt['ma'] );
t( 'và có thẻ', ! empty( $kt['than']['token'] ), $kt['than'] );
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 SOI TOÀN BỘ CÂU TRẢ LỜI, NHƯNG PHẢI BỎ THẺ PHIÊN RA TRƯỚC.
 *
 * Thẻ là 64 ký tự thập lục phân ngẫu nhiên, nên nó CHỨA SẴN đủ mọi cụm ba chữ số với xác suất
 * cao. Soi thẳng cả câu trả lời để tìm chuỗi "357" là phép kiểm HÊN XUI: chạy lần này xanh,
 * lần sau đỏ, mà mã không đổi một dòng. Một phép kiểm chập chờn còn tệ hơn không có — người ta
 * sẽ chạy lại cho tới lúc nó xanh rồi đi tiếp.
 *
 * Nên: bỏ thẻ ra, rồi mới soi phần còn lại. Và kiểm thêm theo TÊN TRƯỜNG, thứ không phụ thuộc
 * may rủi chút nào.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
$con_lai = str_replace( $kt['than']['token'], '<THẺ>', wp_json_encode( $kt['than'] ) );
t( '🔴 câu trả lời của cổng KHÔNG chứa PIN', false === strpos( $con_lai, '357' ), $con_lai );
t( '🔴 và KHÔNG có trường nào tên pin/password',
	! preg_match( '/"(pin|password|pass)"\s*:/i', $con_lai ), $con_lai );
t( 'thẻ thì đúng là 64 ký tự thập lục phân',
	(bool) preg_match( '/^[0-9a-f]{64}$/', $kt['than']['token'] ), $kt['than']['token'] );

$the_kt = $kt['than']['token'];
$the_nv = VHJP_Cong::goi( 'jpLoginPin', array( '468' ) )['than']['token'];

teq( 'có thẻ thì đọc được danh mục', 200, VHJP_Cong::goi( 'jpCfgListLocations', array( $the_kt ) )['ma'] );

// ============================================================ 4. 🔴 Gác quyền ở máy chủ
teq( 'kế toán lưu được cơ sở', 200,
	VHJP_Cong::goi( 'jpCfgSaveLocation', array( $the_kt, array( 'name' => 'Aeon' ) ) )['ma'] );
teq( '🔴 nhân viên KHÔNG lưu được cơ sở', 403,
	VHJP_Cong::goi( 'jpCfgSaveLocation', array( $the_nv, array( 'name' => 'Lén' ) ) )['ma'] );
teq( 'và đúng là không có cơ sở nào tên "Lén"', null,
	VHJP_Nguon::tim_mot( 'JP_Locations', 'name', 'Lén' ) );
/* Lượt bị chặn phải vào nhật ký — không thì không ai biết có người thử. */
$nk = VHJP_Nguon::tim( 'JP_Audit', 'action', 'CHAN_QUYEN' );
t( '🔴 lượt bị chặn có vào nhật ký', count( $nk ) >= 1, $nk );

teq( 'nhân viên VẪN đọc được danh mục (chỉ chặn lưu)', 200,
	VHJP_Cong::goi( 'jpCfgListLocations', array( $the_nv ) )['ma'] );

/* 🔴 Mọi hàm đọc/ghi tài khoản phải nằm trong bảng chỉ-kế-toán. Bản Apps Script gác bên trong
   từng hàm nên sót một chỗ là hở; ở đây đếm được. */
foreach ( array( 'jpCfgListUsers', 'jpCfgSaveUser', 'jpPinTheoCoSo', 'jpTaoPinCoSo' ) as $x ) {
	t( "🔴 $x nằm trong bảng chỉ-kế-toán", in_array( $x, VHJP_Cong::chi_ke_toan(), true ) );
}
/* Và mọi tên trong bảng ấy phải là hàm có thật — gác một tên gõ sai là gác vào hư không. */
$la = array_values( array_diff( VHJP_Cong::chi_ke_toan(), $goi ) );
t( '🔴 mọi tên trong bảng gác quyền đều là hàm giao diện thật sự gọi', ! $la, $la );

// ============================================================ 5. PIN mặc định
VHJP_Nguon::sua( 'JP_Users', 'U-2', array( 'pin' => VHJP_Auth::bam( '101' ) ) );
$md = VHJP_Cong::goi( 'jpLoginPin', array( '101' ) );
$the_md = $md['than']['token'];
teq( '🔴 còn PIN mặc định -> cửa thường đóng', 401,
	VHJP_Cong::goi( 'jpCfgListLocations', array( $the_md ) )['ma'] );
teq( 'và báo đúng chữ PHAI_DOI_PIN cho giao diện', 'PHAI_DOI_PIN',
	VHJP_Cong::goi( 'jpCfgListLocations', array( $the_md ) )['than']['error'] );
/* Ba đường đổi PIN phải sống — không thì không ai đổi được PIN, và cả hệ kẹt. */
teq( '🔴 nhưng jpLogout vẫn đi được', 200, VHJP_Cong::goi( 'jpLogout', array( $the_md ) )['ma'] );
foreach ( array( 'jpBootstrap', 'jpDoiPin', 'jpLogout' ) as $x ) {
	t( "$x nằm trong danh sách cho-PIN-mặc-định", in_array( $x, VHJP_Cong::cho_pin_mac_dinh(), true ) );
}
/* Danh sách ấy phải NHỎ: mở thêm hàm nào là mở đúng ngần ấy cửa cho tài khoản chưa đổi PIN. */
teq( '🔴 và danh sách ấy chỉ có 3 hàm', 3, count( VHJP_Cong::cho_pin_mac_dinh() ) );
teq( '🔴 chỉ đúng MỘT hàm gọi được không cần thẻ', 1, count( VHJP_Cong::cong_khai() ) );
teq( 'và đó là lượt đăng nhập', array( 'jpLoginPin' ), VHJP_Cong::cong_khai() );

// ============================================================ 6. Lỗi không rò ra ngoài
/* Hàm nổ giữa chừng thì cổng vẫn phải trả JSON, và KHÔNG kèm đường dẫn tệp hay mẩu câu SQL. */
$wpdb->exec_raw( 'DROP TABLE ' . VHJP_Nguon::bang( 'JP_Locations' ) );
$loi = VHJP_Cong::goi( 'jpCfgListLocations', array( $the_kt ) );
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
t( 'bảng biến mất thì cổng vẫn trả JSON, không nổ', is_array( $loi['than'] ), $loi );
$chu = wp_json_encode( $loi );
t( '🔴 câu trả lời KHÔNG kèm đường dẫn tệp trên máy chủ',
	false === strpos( $chu, '/wordpress/' ) && false === strpos( $chu, '.php' ), $chu );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — bảng hàm đếm đủ 100, tên lạ không chạy được, quyền gác ở máy chủ.\n";
