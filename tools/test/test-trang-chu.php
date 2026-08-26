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
/* ⚠️ Đếm từ CHÍNH danh sách, không gõ tay con số: thêm một ô (trạm chấm công) là phép thử đỏ
   oan, và đỏ oan nhiều lần thì người ta sửa con số cho xanh mà không đọc xem có đúng không. */
$SO_APP = count( $ds );
t( 'có liệt kê app', $SO_APP >= 4, $SO_APP );
foreach ( $ds as $a ) {
	t( 'app "' . $a['ten'] . '" chưa cài -> co = false', false === $a['co'], $a );
	teq( 'và KHÔNG dựng đường dẫn đoán cho ' . $a['ten'], '', $a['url'] );
}

ob_start(); VHTC_Trang::ve(); $h = ob_get_clean();
t( 'chưa cài app nào thì trang vẫn vẽ được, không chết', strlen( $h ) > 300 );
t( 'và nói rõ "chưa cài" cho MỌI app', substr_count( $h, 'chưa cài' ) === $SO_APP,
	substr_count( $h, 'chưa cài' ) . ' / ' . $SO_APP );
/* 🔴 Đây là phép thử chính: app chưa cài KHÔNG được là thẻ <a>. Một liên kết chết trông y hệt
   một liên kết sống cho tới lúc bấm vào — và người bấm là nhân viên ngoài cơ sở. */
teq( 'không có thẻ <a> nào khi chưa cài app nào', 0, substr_count( $h, '<a class="the"' ) );

// ------------------------------------------------ app ĐÃ CÀI: đường dẫn phải lấy TỪ CHÍNH app đó
/* ⚠️ KHAI BẰNG eval(), KHÔNG khai thẳng.
   PHP NÂNG mọi khai báo lớp ở cấp cao nhất lên lúc biên dịch tệp — viết `class VHCC_Trang {}` ở
   giữa tệp thì `class_exists('VHCC_Trang')` đã trả về true NGAY TỪ DÒNG ĐẦU. Bản đầu em làm vậy
   và toàn bộ phần "chưa cài" ở trên không bao giờ đúng được: nó báo hỏng trong khi mã đúng.
   `eval` chạy đúng lúc gọi tới, nên mới dựng được cảnh "trước khi cài" rồi "sau khi cài". */
/* 🔴 CÀI NỘI BỘ NHƯNG CHƯA CÓ HỆ CHẤM CÔNG -> VẪN PHẢI XÁM.
   Trang nội bộ không có cửa đăng nhập riêng: nó đọc thẻ phiên của `VHCC_Web`. Thiếu hệ ấy thì
   bấm vào chỉ ra một trang nói "chưa cài plugin Chấm Công".
   ⚠️ Phải thử ở ĐÂY, trước khi khai `VHCC_Web`. Khai rồi thì không dựng lại được cảnh này nữa —
   và nửa điều kiện `&& $co('VHCC_Web','url')` sẽ không có phép thử nào canh. */
eval( 'class VHNB_Trang { public static function url() { return "https://khmatrix.com/noi-bo/"; } }' );
$t_nb = array();
foreach ( VHTC_Trang::ds_app() as $a ) { $t_nb[ $a['ten'] ] = $a; }
teq( 'cài nội bộ nhưng CHƯA có hệ chấm công -> ô vẫn xám', false, $t_nb['Nội Bộ']['co'] );
teq( 'và KHÔNG dựng đường dẫn tới một trang chưa đăng nhập được', '', $t_nb['Nội Bộ']['url'] );

eval( 'class VHCC_Trang { public static function url() { return "https://khmatrix.com/cham-cong/"; } }' );
eval( 'class VHCP_App   { public static function app_url() { return "https://khmatrix.com/chi-phi/"; } }' );
/* Chỉ có app CŨ thì trang chủ đành trỏ về nó — liên kết dẫn tới hệ cũ còn hơn ô xám. */
$theo_cu = array();
foreach ( VHTC_Trang::ds_app() as $a ) { $theo_cu[ $a['ten'] ] = $a; }
teq( 'chưa có hệ mới thì lui về app cũ, không bỏ trống',
	'https://khmatrix.com/cham-cong/', $theo_cu['Chấm Công']['url'] );

/* Có hệ MỚI thì nó phải THẮNG app cũ. */
eval( 'class VHCC_Web  { public static function url() { return "https://khmatrix.com/quan-tri-cham-cong/"; } }' );
eval( 'class VHCC_Tram { public static function url() { return "https://khmatrix.com/tram-cham-cong/"; } }' );

$ds = VHTC_Trang::ds_app();
$theo_ten = array();
foreach ( $ds as $a ) { $theo_ten[ $a['ten'] ] = $a; }
t( 'app đã cài -> co = true', true === $theo_ten['Chấm Công']['co'] );
teq( 'và đường dẫn lấy TỪ CHÍNH app đó',
	'https://khmatrix.com/quan-tri-cham-cong/', $theo_ten['Chấm Công']['url'] );
teq( 'ô trạm chấm công trỏ đúng trạm',
	'https://khmatrix.com/tram-cham-cong/', $theo_ten['Chấm Công Online']['url'] );
teq( 'app chi phí cũng vậy',
	'https://khmatrix.com/chi-phi/', $theo_ten['Vận Hành Chi Phí']['url'] );
/* 🔴 Anh Thắng 26/08 hỏi *"trang này còn dùng không"* về `/cham-cong/` — đó là app Apps Script
   CŨ, số liệu vẫn nằm ở Google Sheets. Hệ MỚI chạy thẳng trên host với MySQL. Hai hệ đọc HAI
   kho khác nhau, nên trang chủ phải trỏ về hệ MỚI; trỏ về hệ cũ là mời người ta vào nhìn số của
   một kho đã ngừng được cập nhật. */
t( 'ô Chấm Công trỏ về HỆ MỚI, không về app Apps Script cũ',
	isset( $theo_ten['Chấm Công'] )
	&& false !== strpos( (string) $theo_ten['Chấm Công']['url'], 'quan-tri-cham-cong' ),
	isset( $theo_ten['Chấm Công'] ) ? $theo_ten['Chấm Công']['url'] : null );
/* Trạm chấm công là ô RIÊNG: nhân viên đứng quầy chỉ cần đúng ô đó, không cần cả hệ quản trị. */
t( 'có ô riêng cho Chấm Công Online', isset( $theo_ten['Chấm Công Online'] ), array_keys( $theo_ten ) );

t( 'app hợp đồng chưa cài thì vẫn xám', false === $theo_ten['Thư Viện Hợp Đồng']['co'] );
t( 'app ghế massage chưa cài thì cũng xám', false === $theo_ten['Ghế Massage']['co'] );

ob_start(); VHTC_Trang::ve(); $h = ob_get_clean();
/* Đếm từ chính danh sách: app nào `co` thì thành thẻ <a>, còn lại thành chữ "chưa cài".
   Tổng hai thứ phải bằng đúng số app — không ô nào biến mất, không ô nào đếm hai lần. */
$so_co = 0;
foreach ( VHTC_Trang::ds_app() as $a ) { if ( $a['co'] ) { $so_co++; } }
teq( 'app đã cài -> đúng ngần ấy thẻ <a>', $so_co, substr_count( $h, '<a class="the"' ) );
teq( 'app chưa cài -> đúng ngần ấy chữ "chưa cài"', $SO_APP - $so_co, substr_count( $h, 'chưa cài' ) );
teq( 'và hai thứ cộng lại đúng bằng số app', $SO_APP,
	substr_count( $h, '<a class="the"' ) + substr_count( $h, 'chưa cài' ) );
t( 'trang chứa đúng đường dẫn của hệ chấm công mới',
	strpos( $h, 'https://khmatrix.com/quan-tri-cham-cong/' ) !== false, $h );
t( 'và đường dẫn trạm chấm công',
	strpos( $h, 'https://khmatrix.com/tram-cham-cong/' ) !== false, $h );

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

/* ============================================ ô Nội Bộ: chỉ đứng khi CÓ hệ chấm công
   🔴 Trang nội bộ không có cửa đăng nhập riêng — nó đọc thẻ phiên của `VHCC_Web`. Thiếu hệ chấm
   công thì bấm vào chỉ ra một trang nói "chưa cài plugin Chấm Công". Nên ô này phải xám khi
   thiếu hệ ấy, chứ không phải xanh vì "plugin nội bộ có cài mà". */
t( 'trang cổng có ô Nội Bộ', isset( $theo_ten['Nội Bộ'] ), array_keys( $theo_ten ) );
$t4 = array();
foreach ( VHTC_Trang::ds_app() as $a ) { $t4[ $a['ten'] ] = $a; }
teq( 'cài nội bộ + có hệ chấm công -> ô sáng', true, $t4['Nội Bộ']['co'] );
teq( 'và trỏ đúng trang nội bộ', 'https://khmatrix.com/noi-bo/', $t4['Nội Bộ']['url'] );

/* =================================================== thông tin công ty ở cuối trang
   Anh Thắng 26/08/2026: *"nhớ bổ sung thông tin công ty cuối trang"*. Đây là trang chủ công ty,
   nên nó là chỗ đáng có nhất — nhưng số liệu thì KHÔNG được gõ lại. */

ob_start(); VHTC_Trang::ve(); $h_cty = ob_get_clean();
t( 'cuối trang có tên công ty đầy đủ',
	strpos( $h_cty, 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&amp;H' ) !== false, $h_cty );
t( 'có mã số thuế', strpos( $h_cty, '0106924989' ) !== false );
t( 'có địa chỉ đăng ký', strpos( $h_cty, 'Thôn Mai Nội' ) !== false );
t( 'có người đại diện', strpos( $h_cty, 'Nguyễn Văn Kiên' ) !== false );
t( 'có dòng bản quyền theo năm hiện tại',
	strpos( $h_cty, '© ' . gmdate( 'Y' ) ) !== false, $h_cty );
/* Số điện thoại BẤM GỌI ĐƯỢC — phần lớn người mở trang này đang cầm điện thoại. */
t( 'số điện thoại là liên kết tel:', strpos( $h_cty, 'href="tel:0435961469"' ) !== false, $h_cty );
t( 'có danh sách chi nhánh', strpos( $h_cty, 'Nha Trang' ) !== false );
/* 🔴 Ô TRỐNG thì BỎ HẲN DÒNG, không in nhãn treo lơ lửng. Mặc định chưa có email, nên nhãn
   "Email:" mà vẫn hiện nghĩa là sau nó không có gì — trông như trang hỏng, chứ không phải như
   công ty chưa khai email. */
t( 'ô email đang trống thì KHÔNG in nhãn Email:', strpos( $h_cty, 'Email:' ) === false, $h_cty );
/* Chân trang chỉ là chữ, KHÔNG được thành một ô app thứ bảy. */
teq( 'thêm chân trang KHÔNG làm đổi số thẻ app', $SO_APP,
	substr_count( $h_cty, '<a class="the"' ) + substr_count( $h_cty, 'chưa cài' ) );

/* 🔴 CHƯA CÀI PLUGIN GHẾ THÌ VẪN PHẢI CÓ. Đây là trang chủ công ty; gỡ plugin Ghế mà mất luôn
   thông tin pháp lý của công ty là buộc hai thứ chẳng liên quan gì vào nhau.
   Mấy phép thử trên chạy TRƯỚC dòng require dưới đây, nên chúng đang đúng là cảnh "chưa cài". */
t( 'lúc này quả thật CHƯA có plugin Ghế', ! class_exists( 'VHG_Chan' ) );

require_once $goc . '/wordpress/vhcp-ghe/includes/class-vhg-chan.php';

/* 🔴 CÓ PLUGIN GHẾ THÌ ĐỌC TỪ ĐÓ, không đọc bản dự phòng của mình.
   Plugin Ghế đã có màn quản trị để sửa địa chỉ / người đại diện / chi nhánh. Đọc bản riêng là
   anh Thắng sửa địa chỉ bên kia mà trang chủ vẫn nói địa chỉ cũ — nói sai đúng chỗ đặt ra để
   tạo tin cậy, và không có gì báo. */
update_option( 'vhg_chan', array_merge( VHG_Chan::mac_dinh(), array(
	'dia_chi' => 'Số 1 Đường Mới, Quận Mới, Hà Nội',
	'email'   => 'lienhe@khmatrix.com',
) ) );
ob_start(); VHTC_Trang::ve(); $h_cty2 = ob_get_clean();
t( '🔴 sửa địa chỉ ở plugin Ghế thì trang chủ đổi theo',
	strpos( $h_cty2, 'Số 1 Đường Mới' ) !== false, $h_cty2 );
t( 'và KHÔNG còn địa chỉ cũ', strpos( $h_cty2, 'Thôn Mai Nội' ) === false, $h_cty2 );
t( 'khai email rồi thì dòng Email hiện ra',
	strpos( $h_cty2, 'href="mailto:lienhe@khmatrix.com"' ) !== false, $h_cty2 );

/* ⚠️ Cờ `hien` của plugin Ghế nói "có hiện chân trang trên trang Ghế không". Tắt nó đi để trang
   bán mã gọn hơn mà kéo theo trang chủ công ty mất luôn phần giới thiệu mình là ai thì không ai
   đoán ra vì sao — nên trang này KHÔNG đọc cờ ấy. */
update_option( 'vhg_chan', array_merge( VHG_Chan::mac_dinh(), array( 'hien' => 0 ) ) );
ob_start(); VHTC_Trang::ve(); $h_cty3 = ob_get_clean();
t( 'tắt chân trang bên plugin Ghế thì trang chủ VẪN có thông tin công ty',
	strpos( $h_cty3, '0106924989' ) !== false, $h_cty3 );
delete_option( 'vhg_chan' );

/* 🔴 HAI BẢNG MẶC ĐỊNH PHẢI KHỚP NHAU.
   Bản dự phòng ở trang chủ là một BẢN CHÉP — thứ mà cả dự án này vẫn tránh. Chép được là vì có
   phép thử ngay đây canh: lệch một ô thì đỏ, chứ không im lặng nói sai. */
$md_ghe = VHG_Chan::mac_dinh();
foreach ( VHTC_Trang::CTY_DU_PHONG as $k => $v ) {
	t( "bản dự phòng khớp plugin Ghế ở ô '$k'",
		isset( $md_ghe[ $k ] ) && (string) $md_ghe[ $k ] === (string) $v,
		'trang-chu: ' . $v . '  ·  ghe: ' . ( isset( $md_ghe[ $k ] ) ? $md_ghe[ $k ] : '(KHÔNG CÓ)' ) );
}

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
	&& strpos( $h_ad, 'https://khmatrix.com/quan-tri-cham-cong/' ) !== false, $h_ad );
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
