<?php
/**
 * PHÉP THỬ TRANG VẬN HÀNH K&H (wordpress/vhcp-trang-chu)
 *
 * Trang này chỉ có ba đường dẫn, nên phần đáng thử cũng chính là ba đường dẫn đó:
 * chúng phải LẤY TỪ app thật, và app chưa cài thì KHÔNG được dựng liên kết đoán.
 *
 * Chạy: php tools/test/test-trang-chu.php
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

define( 'VHTC_VERSION', 'test' );
define( 'VHTC_DIR', $goc . '/wordpress/vhcp-trang-chu/' );
require_once VHTC_DIR . 'includes/class-vhtc-trang.php';
require_once VHTC_DIR . 'includes/class-vhtc-admin.php';

// ---------------------------------------------------------------- đường dẫn của chính trang này
teq( 'đường dẫn mặc định là van-hanh', 'van-hanh', VHTC_Trang::slug() );
update_option( 'vhtc_slug', 'cong-vao' );
teq( 'đổi được đường dẫn', 'cong-vao', VHTC_Trang::slug() );
update_option( 'vhtc_slug', '' );
teq( 'để trống thì về mặc định, KHÔNG để rỗng (rỗng là trang không có địa chỉ)',
	'van-hanh', VHTC_Trang::slug() );

// ------------------------------------------------------- app CHƯA CÀI: không được đoán đường dẫn
$ds = VHTC_Trang::ds_app();
/* Con số này sửa TAY mỗi lần thêm app — thêm một thẻ vào trang cổng là thêm một chỗ nhân viên
   ngoài cơ sở bấm vào, nên phải là quyết định có ý thức chứ không phải phép thử tự chạy theo mã. */
teq( 'liệt kê đúng 4 app', 4, count( $ds ) );
foreach ( $ds as $a ) {
	t( 'app "' . $a['ten'] . '" chưa cài -> co = false', false === $a['co'], $a );
	teq( 'và KHÔNG dựng đường dẫn đoán cho ' . $a['ten'], '', $a['url'] );
}

ob_start(); VHTC_Trang::ve(); $h = ob_get_clean();
t( 'chưa cài app nào thì trang vẫn vẽ được, không chết', strlen( $h ) > 300 );
t( 'và nói rõ "chưa cài"', substr_count( $h, 'chưa cài' ) === 4, substr_count( $h, 'chưa cài' ) );
/* 🔴 Đây là phép thử chính: app chưa cài KHÔNG được là thẻ <a>. Một liên kết chết trông y hệt
   một liên kết sống cho tới lúc bấm vào — và người bấm là nhân viên ngoài cơ sở. */
teq( 'không có thẻ <a> nào khi chưa cài app nào', 0, substr_count( $h, '<a class="the"' ) );

// ------------------------------------------------ app ĐÃ CÀI: đường dẫn phải lấy TỪ CHÍNH app đó
/* ⚠️ KHAI BẰNG eval(), KHÔNG khai thẳng.
   PHP NÂNG mọi khai báo lớp ở cấp cao nhất lên lúc biên dịch tệp — viết `class VHCC_Trang {}` ở
   giữa tệp thì `class_exists('VHCC_Trang')` đã trả về true NGAY TỪ DÒNG ĐẦU. Bản đầu em làm vậy
   và toàn bộ phần "chưa cài" ở trên không bao giờ đúng được: nó báo hỏng trong khi mã đúng.
   `eval` chạy đúng lúc gọi tới, nên mới dựng được cảnh "trước khi cài" rồi "sau khi cài". */
eval( 'class VHCC_Trang { public static function url() { return "https://khmatrix.com/cham-cong/"; } }' );
eval( 'class VHCP_App   { public static function app_url() { return "https://khmatrix.com/chi-phi/"; } }' );

$ds = VHTC_Trang::ds_app();
$theo_ten = array();
foreach ( $ds as $a ) { $theo_ten[ $a['ten'] ] = $a; }
t( 'app đã cài -> co = true', true === $theo_ten['Chấm Công']['co'] );
teq( 'và đường dẫn lấy TỪ CHÍNH app đó',
	'https://khmatrix.com/cham-cong/', $theo_ten['Chấm Công']['url'] );
teq( 'app chi phí cũng vậy',
	'https://khmatrix.com/chi-phi/', $theo_ten['Vận Hành Chi Phí']['url'] );
t( 'app hợp đồng chưa cài thì vẫn xám', false === $theo_ten['Thư Viện Hợp Đồng']['co'] );
t( 'app ghế massage chưa cài thì cũng xám', false === $theo_ten['Ghế Massage']['co'] );

ob_start(); VHTC_Trang::ve(); $h = ob_get_clean();
teq( 'hai app đã cài -> đúng 2 thẻ <a>', 2, substr_count( $h, '<a class="the"' ) );
teq( 'hai app chưa cài -> đúng 2 chữ "chưa cài"', 2, substr_count( $h, 'chưa cài' ) );
t( 'trang chứa đúng đường dẫn của app chấm công',
	strpos( $h, 'https://khmatrix.com/cham-cong/' ) !== false );

/* Ghế massage: bản < 1.1.0 CHƯA có trang ngoài nên chỉ còn wp-admin. Liên kết dẫn tới màn đăng
   nhập vẫn hơn liên kết chết, nên vẫn trỏ — nhưng ca có trang ngoài mới là ca đúng, thử ngay
   dưới đây.
   ⚠️ Khai SAU khối vẽ ở trên: khai trước là số thẻ <a> đổi và mấy phép thử kia trượt oan. */
eval( 'class VHG_Admin { public static function app_url() { return "https://khmatrix.com/wp-admin/admin.php?page=vhg"; } }' );
$t2 = array();
foreach ( VHTC_Trang::ds_app() as $a ) { $t2[ $a['ten'] ] = $a; }
t( 'ghế massage đã cài -> co = true', true === $t2['Ghế Massage']['co'] );
t( 'bản cũ chưa có trang ngoài thì tạm trỏ wp-admin, không để liên kết chết',
	strpos( $t2['Ghế Massage']['url'], '/wp-admin/' ) !== false, $t2['Ghế Massage']['url'] );

/* 🔴 CÓ TRANG NGOÀI THÌ PHẢI TRỎ VÀO ĐÓ, không trỏ wp-admin.
   Nhân viên đứng quầy không có tài khoản WordPress — và không nên có, vì cấp tài khoản cho 26
   cửa hàng là cấp luôn đường vào phần quản trị website. Trỏ vào wp-admin là đưa họ tới một màn
   đăng nhập họ không bao giờ qua được, mà trông y hệt một liên kết sống. */
eval( 'class VHG_Trang { public static function url() { return "https://khmatrix.com/ghe/"; } }' );
$t3 = array();
foreach ( VHTC_Trang::ds_app() as $a ) { $t3[ $a['ten'] ] = $a; }
teq( 'có trang ngoài thì trỏ vào trang ngoài', 'https://khmatrix.com/ghe/', $t3['Ghế Massage']['url'] );
t( 'và KHÔNG còn trỏ wp-admin', strpos( $t3['Ghế Massage']['url'], '/wp-admin/' ) === false );

/* 🔴 KHÔNG ĐƯỢC GÕ CỨNG đường dẫn nào trong mã. Gõ cứng là hôm nào anh Thắng đổi đường dẫn bên
   app kia, trang cổng vẫn trỏ về đường cũ — bấm vào ra 404 mà không có gì báo. */
$ma = file_get_contents( VHTC_DIR . 'includes/class-vhtc-trang.php' );
foreach ( array( 'cham-cong', 'chi-phi', 'hop-dong' ) as $slug_cung ) {
	t( "mã KHÔNG gõ cứng đường dẫn '$slug_cung'", strpos( $ma, "'" . $slug_cung . "'" ) === false );
}
t( 'mà gọi hàm url() của từng app',
	strpos( $ma, 'VHCC_Trang::url()' ) !== false
	&& strpos( $ma, 'VHCP_App::app_url()' ) !== false
	&& strpos( $ma, 'VHD_Trang::url()' ) !== false );

// -------------------------------------------------------------------------------- trang cho điện thoại
t( 'có thẻ viewport (phần lớn người mở trang này dùng điện thoại)',
	strpos( $h, 'name="viewport"' ) !== false );
t( 'ô bấm đủ cao cho ngón tay (min-height)', strpos( $h, 'min-height:62px' ) !== false );

// ------------------------------------------------------------------- không lộ gì, không đòi gì
t( 'trang KHÔNG có ô nhập mật khẩu / PIN', stripos( $h, 'type="password"' ) === false );
t( 'trang KHÔNG có biểu mẫu nào', stripos( $h, '<form' ) === false );
foreach ( array( 'AKfycb', 'AIza', 'firebaseio', 'default-rtdb' ) as $mau ) {
	t( "trang không chứa '$mau'", stripos( $h, $mau ) === false );
}

// ------------------------------------------------------------------------------- màn Cài đặt
$GLOBALS['VHCP_CO_QUYEN'] = true;
ob_start(); VHTC_Admin::page(); $h_ad = ob_get_clean();
t( 'màn Cài đặt vẽ được', strlen( $h_ad ) > 300 );
t( 'và có bảng cho biết từng app đang trỏ đi đâu',
	strpos( $h_ad, 'App trên trang cổng' ) !== false
	&& strpos( $h_ad, 'https://khmatrix.com/cham-cong/' ) !== false );
t( 'nói rõ app nào chưa cài', strpos( $h_ad, 'chưa cài' ) !== false );
t( 'màn Cài đặt có nonce', strpos( $h_ad, '_wpnonce' ) !== false );
$GLOBALS['VHCP_CO_QUYEN'] = false;
$chan = false;
try { ob_start(); VHTC_Admin::page(); ob_end_clean(); } catch ( Throwable $e ) { ob_end_clean(); $chan = true; }
t( 'người không đủ quyền bị chặn khỏi màn Cài đặt', $chan );

// --------------------------------------------------------- đổi đường dẫn thì phải nạp lại luật
$ad_ma = file_get_contents( VHTC_DIR . 'includes/class-vhtc-admin.php' );
t( 'đổi đường dẫn thì đặt cờ nạp lại luật (không thì đường mới ra 404 mà vẫn báo "Đã lưu")',
	strpos( $ad_ma, "update_option( 'vhtc_flush', 1 )" ) !== false );
$chinh = file_get_contents( VHTC_DIR . 'vhcp-trang-chu.php' );
t( 'nạp lại luật SAU khi luật đã khai (init ưu tiên 99, khai ở 5)',
	strpos( $chinh, "array( 'VHTC_Trang', 'init' ), 5" ) !== false
	&& strpos( $chinh, '}, 99 );' ) !== false );

// ------------------------------------------------------------ dùng làm trang chủ
/* 🔴 Chiếm trang chủ là việc dễ hỏng nhất trong cả plugin này: chiếm quá tay thì MỌI trang của
   site thành trang cổng, người dùng không còn đường đi tiếp, mà lỗi đó không báo gì. Nên phép
   thử ở đây đi theo hai chiều: bật thì phải chiếm ĐÚNG trang chủ, và KHÔNG chiếm gì khác. */

delete_option( 'vhtc_trang_chu' );
$GLOBALS['VHCP_QVAR'] = array();
$_GET = array();
$GLOBALS['VHCP_LA_ADMIN'] = false;

$GLOBALS['VHCP_LA_TRANG_CHU'] = true;
t( 'CHƯA bật: ở trang chủ vẫn KHÔNG chiếm', false === VHTC_Trang::nen_ve() );

update_option( 'vhtc_trang_chu', 1 );
t( 'bật rồi: ở trang chủ thì chiếm', true === VHTC_Trang::nen_ve() );

$GLOBALS['VHCP_LA_TRANG_CHU'] = false;
t( 'nhưng KHÔNG chiếm trang khác (đây là chốt quan trọng nhất)', false === VHTC_Trang::nen_ve() );

/* wp-admin phải luôn còn đường vào — bật nhầm mà khoá mất wp-admin thì không có đường lùi. */
$GLOBALS['VHCP_LA_TRANG_CHU'] = true;
$GLOBALS['VHCP_LA_ADMIN']     = true;
t( 'KHÔNG bao giờ chiếm trang trong wp-admin', false === VHTC_Trang::nen_ve() );
$GLOBALS['VHCP_LA_ADMIN'] = false;

/* Đường dẫn riêng vẫn chạy song song, bật hay tắt cũng vậy. */
$GLOBALS['VHCP_LA_TRANG_CHU'] = false;
$GLOBALS['VHCP_QVAR'] = array( 'vhtc_app' => 1 );
t( 'đường /van-hanh vẫn chạy khi đã bật trang chủ', true === VHTC_Trang::nen_ve() );
delete_option( 'vhtc_trang_chu' );
t( 'và vẫn chạy khi TẮT trang chủ', true === VHTC_Trang::nen_ve() );
$GLOBALS['VHCP_QVAR'] = array();

/* Màn Cài đặt phải nói rõ bật nhầm thì lùi được — sợ không lùi được thì không ai dám bấm. */
$ad_ma2 = file_get_contents( VHTC_DIR . 'includes/class-vhtc-admin.php' );
t( 'màn Cài đặt có ô "Dùng làm trang chủ"', strpos( $ad_ma2, 'Dùng làm trang chủ' ) !== false );
t( 'và trấn an rằng bật nhầm vẫn vào được wp-admin để tắt',
	strpos( $ad_ma2, 'Bật nhầm không sao' ) !== false );

if ( count( $truot ) ) {
	echo 'HỎNG: ' . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — trang cổng trỏ đúng, app chưa cài không thành liên kết chết.\n";
