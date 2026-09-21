<?php
/**
 * TRANG NGOÀI CỦA JP — ĐƯỜNG DẪN, MÓC KÍCH HOẠT, BA ĐƯỜNG NHẬN LỆNH
 * =============================================================================================
 *
 * Anh Thắng cài bản 1.0.0 lên site rồi hỏi *"đường dẫn là gì đấy em"* — và lúc ấy CHƯA CÓ:
 * plugin bật lên mà không khai móc kích hoạt nên KHÔNG tạo bảng, cũng không mở đường nào.
 * Bài này canh để chuyện ấy không lặp lại:
 *
 *   1. bật plugin PHẢI tạo đủ 23 bảng — bật xong mà bảng chưa có thì mọi lệnh đều nổ;
 *   2. PHẢI mở lại bảng định tuyến, không thì `/jp` trả 404 dù plugin đã bật, và người ta
 *      tưởng plugin hỏng;
 *   3. cập nhật plugin (đổi số bản) cũng phải dựng lại bảng — móc kích hoạt KHÔNG chạy lúc
 *      cập nhật, nên bảng thêm ở bản sau sẽ không bao giờ được tạo;
 *   4. trang chưa xong thì nói thẳng, KHÔNG bày màn trắng.
 *
 * Chạy: php tools/test/kiem-jp-trang.php
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

define( 'VHJP_TEST', 1 );
/* KHÔNG tự khai VHJP_VERSION / VHJP_DIR / VHJP_URL — để chính tệp plugin khai, y như trên
   hosting. Khai hộ ở đây là bài kiểm chạy trên một bộ hằng KHÁC bản thật. */
$plg = $goc . '/wordpress/vhcp-jp/includes/';
require_once $goc . '/wordpress/vhcp-jp/vhcp-jp.php';
global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
$_SERVER['REMOTE_ADDR'] = '10.0.0.7';

// ============================================================ 1. 🔴 Móc kích hoạt
$ma_chinh = file_get_contents( $goc . '/wordpress/vhcp-jp/vhcp-jp.php' );
$than = preg_replace( '#/\*.*?\*/#s', ' ', $ma_chinh );
$than = preg_replace( '#(?<!:)//[^\n]*#', ' ', $than );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 GỌI THẬT, KHÔNG SOI CHỮ.
 *
 * Bản đầu của mấy phép này chỉ tìm chuỗi `register_activation_hook`, `flush_rewrite_rules`,
 * `vhjp_db_ver` ở bất kỳ đâu trong tệp — và hai lượt đục thử SỐNG SÓT vì cùng chuỗi ấy còn
 * xuất hiện ở dòng khác (móc tắt plugin, và câu `update_option` ở chỗ kích hoạt). Phép soi
 * kiểu đó xanh cho một plugin bật lên chẳng tạo bảng nào — đúng cảnh vừa xảy ra.
 * Nên: gọi đúng hàm ấy, rồi đếm bảng thật trong cơ sở dữ liệu.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ KHÔNG xoá `VHCP_MOC_BAT` ở đây: tệp plugin đã nạp từ đầu bài, và `require_once` lần hai
   không chạy lại. Xoá là xoá đúng thứ vừa muốn đo — bản đầu mắc lỗi ấy và bài chết ngay. */
$moc = isset( $GLOBALS['VHCP_MOC_BAT'] ) ? $GLOBALS['VHCP_MOC_BAT'] : array();
teq( '🔴 plugin CÓ khai móc kích hoạt', 1, count( $moc ) );
t( 'và móc ấy gọi được', is_callable( isset( $moc[0] ) ? $moc[0] : null ), $moc );

/* ⚠️ DỪNG SỚM nếu không có móc. Thiếu câu này thì mọi phép dưới đọc `$moc[0]` và bài CHẾT
   bằng lỗi PHP — người đọc thấy một dấu vết ngăn xếp thay vì câu "plugin không khai móc kích
   hoạt". Trượt phải ra BÁO TRƯỢT, không ra sập. */
if ( ! isset( $moc[0] ) || ! is_callable( $moc[0] ) ) {
	echo "HỎNG: plugin KHÔNG khai móc kích hoạt — bật lên sẽ không tạo bảng nào.\n";
	exit( 1 );
}

/* Bật plugin — phải phát đủ 23 câu dựng bảng.
   ⚠️ Đo bằng câu lệnh PHÁT RA chứ không bằng bảng có thật: `dbDelta` nhận cú pháp MySQL, mà bệ
      đỡ chạy SQLite nên không tự chạy được. Lược đồ dựng được hay không thì
      `kiem-jp-luoc-do.php` đã canh riêng, bằng bản dịch sang SQLite giữ cả khoá. */
$GLOBALS['VHCP_DBDELTA']     = array();
$GLOBALS['VHCP_MO_LAI_DUONG'] = 0;
$GLOBALS['VHCP_LUAT']        = array();
call_user_func( $moc[0] );

teq( '🔴 bật plugin là phát đủ 23 câu dựng bảng', 23, count( $GLOBALS['VHCP_DBDELTA'] ) );
$chu_ddl = implode( "\n", $GLOBALS['VHCP_DBDELTA'] );
foreach ( array( 'vhjp_bao_cao', 'vhjp_dong', 'vhjp_users', 'vhjp_bank_gd' ) as $b ) {
	t( "và có dựng bảng $b", false !== strpos( $chu_ddl, $b ), $b );
}
t( '🔴 và mở lại bảng định tuyến (không thì /jp trả 404 dù plugin đã bật)',
	$GLOBALS['VHCP_MO_LAI_DUONG'] >= 1, $GLOBALS['VHCP_MO_LAI_DUONG'] );
t( '🔴 và ĐƯỜNG đã được khai TRƯỚC lúc mở lại (mở lại một bảng rỗng thì vô ích)',
	count( $GLOBALS['VHCP_LUAT'] ) >= 2, $GLOBALS['VHCP_LUAT'] );

/* Cập nhật plugin thì móc kích hoạt KHÔNG chạy — phải có đường dựng lại theo SỐ BẢN. */
$GLOBALS['VHCP_DBDELTA'] = array();
update_option( 'vhjp_db_ver', 'ban-cu-hon' );
vhjp_co_the_nang();
teq( '🔴 đổi số bản (cập nhật plugin) cũng dựng lại đủ bảng', 23, count( $GLOBALS['VHCP_DBDELTA'] ) );

/* Và số bản KHÔNG đổi thì đừng dựng lại mỗi lượt tải trang — tốn công vô ích trên mọi request. */
$GLOBALS['VHCP_DBDELTA'] = array();
vhjp_co_the_nang();
teq( '🔴 số bản không đổi -> KHÔNG dựng lại gì', 0, count( $GLOBALS['VHCP_DBDELTA'] ) );

t( 'và trang được khai vào WordPress', strpos( $than, 'VHJP_Trang::init' ) !== false );

// ============================================================ 2. Đường dẫn
teq( 'đường nhân viên mặc định', 'jp',         VHJP_Trang::slug_nv() );
teq( 'đường kế toán mặc định',   'jp-ke-toan', VHJP_Trang::slug_kt() );
/* Site có đường dẫn đẹp -> địa chỉ mang slug. */
update_option( 'permalink_structure', '/%postname%/' );
t( 'địa chỉ màn nhân viên mang đường /jp/',
	false !== strpos( VHJP_Trang::dia_chi(), '/jp/' ), VHJP_Trang::dia_chi() );
t( 'và màn kế toán mang /jp-ke-toan/',
	false !== strpos( VHJP_Trang::dia_chi( true ), '/jp-ke-toan/' ), VHJP_Trang::dia_chi( true ) );
/* 🔴 Site CHƯA bật đường dẫn đẹp thì phải lùi sang dạng tham số, không được trả một đường
   chết. Nhiều site mới dựng để mặc định như vậy, và người mở ra chỉ thấy 404. */
delete_option( 'permalink_structure' );
t( '🔴 site chưa bật đường dẫn đẹp -> lùi sang dạng tham số, không trả đường chết',
	false !== strpos( VHJP_Trang::dia_chi(), 'vhjp_app=nv' ), VHJP_Trang::dia_chi() );
t( 'và màn kế toán cũng vậy',
	false !== strpos( VHJP_Trang::dia_chi( true ), 'vhjp_app=kt' ), VHJP_Trang::dia_chi( true ) );
/* Đổi slug được, nhưng đường CŨ phải còn — đường dẫn đã nằm trong tin nhắn và dấu trang. */
update_option( 'vhjp_slug_nv', 'bao-cao-jp' );
teq( 'đổi được đường', 'bao-cao-jp', VHJP_Trang::slug_nv() );
$GLOBALS['VHCP_LUAT'] = array();
VHJP_Trang::them_duong();
$luat = array_keys( $GLOBALS['VHCP_LUAT'] );
/* ⚠️ Soi trên MẢNG, đừng soi trong chuỗi JSON: `json_encode` thoát dấu gạch chéo thành `\/`,
   nên tìm chuỗi con `'^jp/'` trượt cả khi luật ấy có thật. Phép soi sai kiểu đó báo đỏ cho mã
   hoàn toàn đúng, và người ta sẽ đi sửa chỗ đang lành. */
t( '🔴 đổi slug rồi thì đường MỚI có', in_array( '^bao-cao-jp/?$', $luat, true ), $luat );
t( '🔴 và đường CŨ vẫn còn (tin nhắn, mã QR đã in, dấu trang cũ không chết)',
	in_array( '^jp/?$', $luat, true ), $luat );
t( 'đường kế toán cũng được khai', in_array( '^jp-ke-toan/?$', $luat, true ), $luat );
delete_option( 'vhjp_slug_nv' );

// ============================================================ 3. Trang tạm — không màn trắng
$tep = VHJP_DIR . 'templates/dang-dung.html';
t( 'có trang tạm', is_file( $tep ) );
ob_start(); VHJP_Trang::ve( false ); $html = ob_get_clean();

t( '🔴 trang KHÔNG trắng — có nói đang dựng dở',
	false !== mb_strpos( $html, 'Đang dựng dở' ), mb_substr( $html, 0, 200 ) );
t( 'và nạp lớp gas-shim', false !== strpos( $html, 'gas-shim.js' ) );
t( '🔴 KHÔNG còn chỗ giữ nào chưa được thay',
	false === strpos( $html, '<?VHJP_' ), 'còn chỗ giữ!' );

/* Cấu hình đẩy xuống trang phải có đủ ba đường gọi — thiếu một là mất đường lùi khi hosting
   chặn, mà lúc ấy app chết hẳn chứ không chậm. */
preg_match( '/window\.VHJP_CFG = (\{.*?\});/s', $html, $m );
$cfg = json_decode( isset( $m[1] ) ? $m[1] : '{}', true );
foreach ( array( 'rest', 'ajax', 'trang' ) as $d ) {
	t( "🔴 cấu hình có đường '$d'", ! empty( $cfg[ $d ] ), $cfg );
}
teq( 'và biết đang ở màn nào', 'nv', $cfg['man'] );
teq( 'màn kế toán thì khác', 'kt', ( function () {
	ob_start(); VHJP_Trang::ve( true ); $h = ob_get_clean();
	preg_match( '/window\.VHJP_CFG = (\{.*?\});/s', $h, $mm );
	return json_decode( $mm[1], true )['man'];
} )() );

/* Thanh tiến độ phải nói SỐ THẬT, lấy từ chính bảng hàm — không gõ tay. */
teq( 'tiến độ đúng số hàm đã chuyển', count( VHJP_Cong::map() ), $cfg['daChuyen'] );
teq( 'và đúng tổng số hàm',
	count( VHJP_Cong::map() ) + count( VHJP_Cong::chua_lam() ), $cfg['tong'] );

/* 🔴 Trang đi ra internet: không được lộ đường dẫn tệp trên máy chủ. */
t( '🔴 trang KHÔNG lộ đường dẫn tệp trên máy chủ',
	false === strpos( $html, '/home/' ) && false === strpos( $html, VHJP_DIR ), 'có lộ!' );

// ============================================================ 4. Ba đường nhận lệnh
$ma_trang = file_get_contents( $plg . 'class-vhjp-trang.php' );
t( '🔴 có đường REST',       strpos( $ma_trang, 'register_rest_route' ) !== false );
t( '🔴 có đường admin-ajax', strpos( $ma_trang, 'wp_ajax_nopriv_vhjp_call' ) !== false );
t( '🔴 có đường qua chính trang (Cloudflare hay chặn hai đường kia)',
	strpos( $ma_trang, 'vhjp_api' ) !== false );
/* Đường ajax phải mở cho người CHƯA đăng nhập WordPress — nhân viên JP không có tài khoản
   WordPress, họ vào bằng PIN. Quên `nopriv` là cả cửa hàng không gọi được gì. */
t( '🔴 đường ajax mở cho người chưa đăng nhập WordPress (nhân viên vào bằng PIN)',
	strpos( $ma_trang, 'wp_ajax_nopriv_vhjp_call' ) !== false );

// ============================================================ 5. Lệnh đi qua trang
VHJP_Nguon::them( 'JP_Users', array( 'id' => 'U-1', 'hoTen' => 'Chị KT',
	'role' => VHJP_Auth::VAI_KT, 'pin' => VHJP_Auth::bam( '357' ) ) );
$GLOBALS['VHG_THAN'] = wp_json_encode( array( 'fn' => 'jpLoginPin', 'args' => array( '357' ) ) );

/* Gọi thẳng cổng — phần dịch từ thân yêu cầu đã có bài riêng ở `kiem-jp-cong.php`. */
$kq = VHJP_Cong::goi( 'jpLoginPin', array( '357' ) );
teq( 'đăng nhập qua cổng vẫn chạy sau khi thêm tầng trang', 200, $kq['ma'] );

// ============================================================ 6. gas-shim
$js = file_get_contents( VHJP_DIR . 'assets/js/gas-shim.js' );
t( '🔴 shim dựng lại google.script.run', strpos( $js, 'google.script' ) !== false );
t( 'và có cả google.script.host (giao diện gốc có gọi)', strpos( $js, 'script.host' ) !== false );
t( '🔴 shim KHÔNG cất PIN ở máy khách',
	! preg_match( '/localStorage\.setItem\([^)]*pin/i', $js ), 'có cất PIN!' );
t( 'shim biết lùi sang đường khác khi bị chặn', strpos( $js, 'biChan' ) !== false );
t( '🔴 và ném đúng chữ giao diện đang bắt (SESSION_EXPIRED / PHAI_DOI_PIN)',
	strpos( $js, 'than.error' ) !== false, $js );
/* Shim KHÔNG được tự chèn thẻ phiên: giao diện JP tự giữ thẻ và truyền làm tham số đầu. Hai
   nơi cùng quản một thứ là tới ngày chúng lệch nhau, không ai biết bên nào đúng. */
t( '🔴 shim KHÔNG tự chèn thẻ vào lệnh (giao diện JP tự truyền)',
	! preg_match( '/args\.unshift|args\.splice\(\s*0/', $js ), 'có chèn!' );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — bật plugin là có bảng và có đường dẫn, trang không bày màn trắng.\n";
