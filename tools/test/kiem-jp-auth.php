<?php
/**
 * ĐĂNG NHẬP PIN CỦA JP — CHỖ YẾU NHẤT CỦA CẢ HỆ, NÊN CANH KỸ NHẤT
 * =============================================================================================
 *
 * Hệ JP đăng nhập CHỈ bằng PIN ba số. Không tên đăng nhập, không mật khẩu. Bài này canh bốn
 * điều, và cả bốn đều là chuyện mất tài khoản chứ không phải chuyện giao diện:
 *
 *   1. PIN KHÔNG BAO GIỜ đi ra ngoài — không trong câu trả lời, không trong phiên trả về;
 *   2. PIN lưu ở dạng BĂM CHẬM, không phải băm trần và càng không phải chữ thô;
 *   3. thẻ phiên phải NGẪU NHIÊN AN TOÀN — đoán được thẻ là vào được phiên người khác mà
 *      chẳng cần biết PIN nào cả;
 *   4. còn PIN mặc định thì mọi cửa ĐÓNG, và chặn ở MỘT chỗ chứ không ở từng cửa.
 *
 * Chạy: php tools/test/kiem-jp-auth.php
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
foreach ( array( 'db', 'doc', 'nguon', 'ma', 'nhat-ky', 'auth' ) as $f ) { require_once $plg . 'class-vhjp-' . $f . '.php'; }

global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
$_SERVER['REMOTE_ADDR'] = '10.0.0.7';

/** Dựng lại sổ người dùng cho mỗi mục, để mục trước không ảnh hưởng mục sau. */
function jp_dung_user() {
	global $wpdb;
	$wpdb->exec_raw( 'DELETE FROM ' . VHJP_Nguon::bang( 'JP_Users' ) );
	delete_option( VHJP_Auth::O_PHIEN );
	delete_option( VHJP_Auth::O_SAI );
	VHJP_Nguon::them( 'JP_Users', array( 'id' => 'U-1', 'username' => 'ketoan',
		'hoTen' => 'Chị Kế Toán', 'role' => VHJP_Auth::VAI_KT,
		'pin' => VHJP_Auth::bam( '357' ), 'locationIds' => 'L-1, L-2' ) );
	VHJP_Nguon::them( 'JP_Users', array( 'id' => 'U-2', 'username' => 'nv',
		'hoTen' => 'Bạn Nhân Viên', 'role' => VHJP_Auth::VAI_NV,
		'pin' => VHJP_Auth::bam( '468' ), 'locationIds' => 'L-3' ) );
}
jp_dung_user();

// ============================================================ 1. Băm PIN
$b = VHJP_Auth::bam( '357' );
t( '🔴 PIN lưu KHÔNG phải chữ thô', '357' !== $b && false === strpos( $b, '357' ), $b );
/* 🔴 SHA-256 trần với 1.000 khả năng là bảng tra dựng xong trong một phần nghìn giây. Phải là
   hàm băm CHẬM (bcrypt/argon2) — chuỗi của chúng bắt đầu bằng `$`. */
t( '🔴 dùng hàm băm CHẬM, không phải SHA-256 trần',
	'$' === substr( $b, 0, 1 ) && strlen( $b ) >= 50, $b );
t( 'và hai lần băm cùng một PIN ra hai chuỗi khác nhau (có muối riêng)',
	VHJP_Auth::bam( '357' ) !== VHJP_Auth::bam( '357' ) );
t( 'nhưng cả hai đều khớp lại được', VHJP_Auth::khop( $b, '357' )['ok'] );
t( 'PIN khác thì KHÔNG khớp',       ! VHJP_Auth::khop( $b, '358' )['ok'] );
t( 'giá trị lưu rỗng thì không khớp gì cả', ! VHJP_Auth::khop( '', '357' )['ok'] );
t( 'và PIN rỗng cũng không khớp ô rỗng',    ! VHJP_Auth::khop( '', '' )['ok'] );

/* PIN thô còn sót từ Google Sheets vẫn phải vào được — và phải bị đánh dấu để băm lại. */
$k = VHJP_Auth::khop( '357', '357' );
t( 'PIN thô từ Sheets vẫn khớp', $k['ok'] );
t( '🔴 và bị đánh dấu là bản cũ, để băm lại', $k['cu'] );
t( 'PIN đã băm thì KHÔNG bị đánh dấu là cũ', ! VHJP_Auth::khop( $b, '357' )['cu'] );

// ============================================================ 2. Chuẩn hoá PIN
teq( 'ba số thì nhận',            '357', VHJP_Auth::chuan_pin( '357' ) );
teq( 'bỏ ký tự thừa',             '357', VHJP_Auth::chuan_pin( ' 3-5-7 ' ) );
teq( 'hai số thì chối',           '',    VHJP_Auth::chuan_pin( '35' ) );
teq( 'bốn số thì chối',           '',    VHJP_Auth::chuan_pin( '3579' ) );
teq( 'rỗng thì chối',             '',    VHJP_Auth::chuan_pin( '' ) );
teq( 'toàn chữ thì chối',         '',    VHJP_Auth::chuan_pin( 'abc' ) );

// ============================================================ 3. 🔴 Đăng nhập
$r = VHJP_Auth::dang_nhap( '357' );
t( 'kế toán vào được', ! empty( $r['ok'] ), $r );
t( 'có thẻ phiên',     ! empty( $r['token'] ), $r );

/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI: soi TOÀN BỘ câu trả lời, không soi từng trường — thêm
   một trường mới vào phiên mà quên che là nó tự chảy ra đây. */
$json = wp_json_encode( $r );
t( '🔴 câu trả lời KHÔNG chứa PIN', false === strpos( $json, '357' ), $json );
t( '🔴 và KHÔNG chứa chuỗi băm',    false === strpos( $json, '$2y$' ) && false === strpos( $json, 'pin' ), $json );
teq( 'nhưng có tên người để hiện lên màn', 'Chị Kế Toán', $r['user']['hoTen'] );
teq( 'và có danh sách cơ sở đã tách', array( 'L-1', 'L-2' ), $r['user']['locationIds'] );

$sai = VHJP_Auth::dang_nhap( '999' );
t( 'PIN không có thì chối', empty( $sai['ok'] ), $sai );
/* Câu báo lỗi CỐ Ý không phân biệt "không có PIN này" với "tài khoản đã tắt" — phân biệt là chỉ
   cho người dò biết số nào có thật, tức tự tay thu hẹp 1.000 khả năng giúp họ. */
VHJP_Nguon::sua( 'JP_Users', 'U-2', array( 'active' => 'N' ) );
$tat = VHJP_Auth::dang_nhap( '468' );
t( 'tài khoản đã tắt thì cũng chối', empty( $tat['ok'] ), $tat );
teq( '🔴 và câu báo lỗi GIỐNG HỆT, không lộ số nào có thật', $sai['msg'], $tat['msg'] );
VHJP_Nguon::sua( 'JP_Users', 'U-2', array( 'active' => '' ) );

teq( 'PIN sai độ dài thì báo khác (đó là lỗi gõ, không phải lỗi dò)',
	'Nhập đúng 3 số', VHJP_Auth::dang_nhap( '35' )['msg'] );

/* PIN thô trong cơ sở dữ liệu: vào được, và được băm lại NGAY. */
jp_dung_user();
VHJP_Nguon::sua( 'JP_Users', 'U-2', array( 'pin' => '468' ) );
$tho = VHJP_Auth::dang_nhap( '468' );
t( 'PIN thô vẫn vào được', ! empty( $tho['ok'] ), $tho );
$sau = VHJP_Nguon::tim_mot( 'JP_Users', 'id', 'U-2' )['pin'];
t( '🔴 và được băm lại ngay tại chỗ, không còn chữ thô trong sổ', '468' !== $sau, $sau );
t( 'băm lại rồi vẫn đăng nhập được', ! empty( VHJP_Auth::dang_nhap( '468' )['ok'] ) );

// ============================================================ 4. 🔴 Thẻ phiên
$the = array();
for ( $i = 0; $i < 30; $i++ ) { $the[] = VHJP_Auth::the_moi(); }
teq( '🔴 30 thẻ là 30 giá trị khác nhau', 30, count( array_unique( $the ) ) );
t( '🔴 thẻ đủ dài để không dò ra (>= 32 ký tự)', strlen( $the[0] ) >= 32, strlen( $the[0] ) );
/* Đoán được thẻ là vào được phiên người khác mà chẳng cần biết PIN nào. `uniqid()` suy ra từ
   thời điểm gọi, nên hai thẻ sinh liền nhau chỉ khác vài ký tự cuối. */
$chung = 0;
$a = $the[0]; $c = $the[1];
for ( $i = 0; $i < min( strlen( $a ), strlen( $c ) ); $i++ ) {
	if ( $a[ $i ] === $c[ $i ] ) { $chung++; }
}
t( '🔴 hai thẻ liền nhau KHÔNG giống nhau phần lớn (không suy ra từ đồng hồ)',
	$chung < strlen( $a ) / 2, "$chung / " . strlen( $a ) . ' ký tự trùng vị trí' );

// ============================================================ 5. Phiên
jp_dung_user();
$r = VHJP_Auth::dang_nhap( '357' );
$ok = VHJP_Auth::kiem( $r['token'] );
t( 'thẻ đúng thì qua cửa', ! empty( $ok['ok'] ), $ok );
teq( 'và nhận ra đúng người', 'U-1', $ok['user']['id'] );

teq( 'thẻ lạ thì chối',  'PHIEN_HET_HAN', VHJP_Auth::kiem( 'the-bia-dat' )['ma'] );
teq( 'thẻ rỗng thì chối', 'PHIEN_HET_HAN', VHJP_Auth::kiem( '' )['ma'] );

VHJP_Auth::thoat( $r['token'] );
teq( '🔴 thoát rồi thì thẻ cũ KHÔNG dùng lại được', 'PHIEN_HET_HAN',
	VHJP_Auth::kiem( $r['token'] )['ma'] );

/* Phiên hết hạn phải bị chối, và bị DỌN khỏi sổ — sổ phiên không được phép phình mãi. */
$r2 = VHJP_Auth::dang_nhap( '357' );
$ds = get_option( VHJP_Auth::O_PHIEN );
$ds[ $r2['token'] ]['het_han'] = time() - 10;
update_option( VHJP_Auth::O_PHIEN, $ds );
teq( '🔴 phiên hết hạn thì chối', 'PHIEN_HET_HAN', VHJP_Auth::kiem( $r2['token'] )['ma'] );
t( 'và bị dọn khỏi sổ phiên', ! isset( get_option( VHJP_Auth::O_PHIEN )[ $r2['token'] ] ) );

// ============================================================ 6. 🔴 PIN mặc định đóng mọi cửa
jp_dung_user();
VHJP_Nguon::sua( 'JP_Users', 'U-1', array( 'pin' => VHJP_Auth::bam( '222' ) ) );
$md = VHJP_Auth::dang_nhap( '222' );
t( 'PIN mặc định vẫn ĐĂNG NHẬP được (phải vào được thì mới đổi được PIN)', ! empty( $md['ok'] ), $md );
t( '🔴 và phiên mang cờ phải đổi PIN', ! empty( $md['user']['phaiDoiPin'] ), $md );

teq( '🔴 nhưng mọi cửa thường ĐÓNG', 'PHAI_DOI_PIN', VHJP_Auth::kiem( $md['token'] )['ma'] );
t( '🔴 chỉ ba đường đổi-PIN mới qua được', ! empty( VHJP_Auth::kiem( $md['token'], true )['ok'] ) );

t( '222 và 101 đều là PIN mặc định',
	VHJP_Auth::la_pin_mac_dinh( '222' ) && VHJP_Auth::la_pin_mac_dinh( '101' ) );
t( 'còn PIN tự đặt thì không', ! VHJP_Auth::la_pin_mac_dinh( '357' ) );

// ============================================================ 7. Làm chậm người dò
jp_dung_user();
$_SERVER['REMOTE_ADDR'] = '10.0.0.9';
for ( $i = 0; $i < 3; $i++ ) { VHJP_Auth::ghi_nhan_sai( false ); }
$so = get_option( VHJP_Auth::O_SAI );
teq( 'đếm đúng số lần gõ sai', 3, $so['10.0.0.9']['n'] );

/* 🔴 ĐẾM RIÊNG THEO ĐỊA CHỈ MÁY. Đếm chung cả hệ thì một người dò làm chậm mọi nhân viên đang
   đăng nhập — biến chốt chặn thành công cụ phá hoại. */
$_SERVER['REMOTE_ADDR'] = '10.0.0.8';
VHJP_Auth::ghi_nhan_sai( false );
$so = get_option( VHJP_Auth::O_SAI );
teq( '🔴 máy khác đếm riêng, không ăn theo', 1, $so['10.0.0.8']['n'] );
teq( 'và máy kia giữ nguyên số của nó',      3, $so['10.0.0.9']['n'] );

/* Đăng nhập ĐÚNG thì xoá sổ đếm — nhân viên gõ nhầm vài lần rồi gõ đúng không bị phạt tiếp. */
$_SERVER['REMOTE_ADDR'] = '10.0.0.9';
VHJP_Auth::dang_nhap( '357' );
$so = get_option( VHJP_Auth::O_SAI );
t( '🔴 vào đúng thì xoá sổ đếm của máy ấy', ! isset( $so['10.0.0.9'] ) , $so );
t( 'nhưng KHÔNG đụng sổ của máy khác',        isset( $so['10.0.0.8'] ), $so );

/* Mục đã nguội phải tự rụng — sổ này không được phép phình mãi. */
$so = get_option( VHJP_Auth::O_SAI );
$so['10.0.0.99'] = array( 'n' => 9, 'luc' => time() - VHJP_Auth::CUA_SO_SAI - 60 );
update_option( VHJP_Auth::O_SAI, $so );
VHJP_Auth::ghi_nhan_sai( false );
t( '🔴 mục đã nguội tự rụng khỏi sổ',
	! isset( get_option( VHJP_Auth::O_SAI )['10.0.0.99'] ), get_option( VHJP_Auth::O_SAI ) );

// ============================================================ 8. PIN phải DUY NHẤT
jp_dung_user();
t( 'PIN đã có người dùng thì tra ra đúng người',
	'U-1' === VHJP_Auth::pin_da_dung( '357' )['id'] );
teq( 'PIN chưa ai dùng thì trả null', null, VHJP_Auth::pin_da_dung( '111' ) );
t( '🔴 bỏ qua chính mình khi sửa hồ sơ của mình',
	null === VHJP_Auth::pin_da_dung( '357', 'U-1' ) );

// ============================================================ 9. Vai trò
$kt = array( 'role' => VHJP_Auth::VAI_KT );
$nv = array( 'role' => VHJP_Auth::VAI_NV );
t( 'nhận ra kế toán', VHJP_Auth::la_kt( $kt ) && ! VHJP_Auth::la_nv( $kt ) );
t( 'nhận ra nhân viên', VHJP_Auth::la_nv( $nv ) && ! VHJP_Auth::la_kt( $nv ) );
/* Hai vai kế toán cũ vẫn phải nhận, không thì tài khoản đã có mất quyền sau lượt chuyển. */
t( '🔴 vẫn nhận hai vai kế toán cũ',
	VHJP_Auth::la_kt( array( 'role' => VHJP_Auth::VAI_KT_DT ) )
	&& VHJP_Auth::la_kt( array( 'role' => VHJP_Auth::VAI_KT_KHO ) ) );
t( 'vai lạ thì không phải ai cả',
	! VHJP_Auth::la_kt( array( 'role' => 'GIAMDOC' ) ) && ! VHJP_Auth::la_nv( array( 'role' => 'GIAMDOC' ) ) );

// ============================================================ 9b. 🔴 Đổi PIN
jp_dung_user();
$r = VHJP_Auth::dang_nhap( '357' );
$the = $r['token'];

teq( 'PIN mới sai độ dài -> chối', false, VHJP_Auth::doi_pin( $the, '357', '35' )['ok'] );
teq( 'PIN mới trùng PIN cũ -> chối', false, VHJP_Auth::doi_pin( $the, '357', '357' )['ok'] );
teq( '🔴 gõ sai PIN hiện tại -> chối', false, VHJP_Auth::doi_pin( $the, '111', '789' )['ok'] );
/* Gõ sai PIN hiện tại phải tính là MỘT LƯỢT SAI — không thì đây thành cửa dò PIN không bị làm
   chậm, chỉ cần một thẻ phiên bất kỳ là dò được PIN của chính mình lẫn của người khác. */
$so = get_option( VHJP_Auth::O_SAI, array() );
t( '🔴 và tính là một lượt gõ sai (đừng để thành cửa dò PIN không bị làm chậm)',
	! empty( $so[ VHJP_Auth::dia_chi() ]['n'] ), $so );

teq( '🔴 PIN mới trùng người khác -> chối', false, VHJP_Auth::doi_pin( $the, '357', '468' )['ok'] );
t( 'đổi được sang PIN hợp lệ', ! empty( VHJP_Auth::doi_pin( $the, '357', '789' )['ok'] ) );
t( '🔴 PIN cũ thôi vào được', empty( VHJP_Auth::dang_nhap( '357' )['ok'] ) );
t( 'và PIN mới vào được',    ! empty( VHJP_Auth::dang_nhap( '789' )['ok'] ) );

/* 🔴 Đổi từ PIN MẶC ĐỊNH thì cờ chặn phải GỠ NGAY trên phiên đang dùng — không gỡ thì đổi
   xong vẫn bị chặn tới lúc hết phiên, người dùng sẽ đổi lại lần nữa rồi đi hỏi. */
jp_dung_user();
VHJP_Nguon::sua( 'JP_Users', 'U-1', array( 'pin' => VHJP_Auth::bam( '222' ) ) );
$md = VHJP_Auth::dang_nhap( '222' );
teq( 'trước khi đổi: mọi cửa đóng', 'PHAI_DOI_PIN', VHJP_Auth::kiem( $md['token'] )['ma'] );
t( 'đổi PIN mặc định sang số mới', ! empty( VHJP_Auth::doi_pin( $md['token'], '222', '789' )['ok'] ) );
t( '🔴 đổi xong là cửa MỞ NGAY trên chính phiên ấy',
	! empty( VHJP_Auth::kiem( $md['token'] )['ok'] ), VHJP_Auth::kiem( $md['token'] ) );

/* Đổi sang một PIN mặc định KHÁC thì vẫn phải bị chặn — không thì đổi 222 sang 101 là lách được. */
jp_dung_user();
VHJP_Nguon::sua( 'JP_Users', 'U-1', array( 'pin' => VHJP_Auth::bam( '222' ) ) );
$md2 = VHJP_Auth::dang_nhap( '222' );
VHJP_Auth::doi_pin( $md2['token'], '222', '101' );
teq( '🔴 đổi 222 sang 101 (cũng là PIN mặc định) -> VẪN chặn', 'PHAI_DOI_PIN',
	VHJP_Auth::kiem( $md2['token'] )['ma'] );

// ============================================================ 9c. Quyền xem cơ sở
$kt_u = array( 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );
$nv_u = array( 'role' => VHJP_Auth::VAI_NV, 'locationIds' => array( 'L-1', 'L-2' ) );
t( '🔴 kế toán thấy mọi cơ sở, kể cả khi danh sách gán rỗng',
	VHJP_Auth::xem_duoc_coso( $kt_u, 'L-9' ) );
t( 'nhân viên thấy cơ sở đã gán',        VHJP_Auth::xem_duoc_coso( $nv_u, 'L-1' ) );
t( '🔴 và KHÔNG thấy cơ sở chưa gán',  ! VHJP_Auth::xem_duoc_coso( $nv_u, 'L-9' ) );
/* So theo CHUỖI: danh sách gán lưu dạng `'L-1, L-2'`, còn nơi gọi hay truyền số. */
$nv_so = array( 'role' => VHJP_Auth::VAI_NV, 'locationIds' => array( '7' ) );
t( 'truyền số 7 vẫn khớp cơ sở lưu "7"', VHJP_Auth::xem_duoc_coso( $nv_so, 7 ) );

// ============================================================ 10. Soi mã: PIN không được in ra
$ma_auth = file_get_contents( $plg . 'class-vhjp-auth.php' );
$than = preg_replace( '#/\*.*?\*/#s', ' ', $ma_auth );
$than = preg_replace( '#(?<!:)//[^\n]*#', ' ', $than );
t( '🔴 mã đăng nhập KHÔNG có lệnh in/ghi nhật ký nào kèm PIN',
	! preg_match( '/\b(echo|print|var_dump|error_log|print_r)\b[^;]*\$?pin/i', $than ), 'có!' );
t( 'bộ tước chú thích trong phép trên có thật',
	strpos( preg_replace( '#/\*.*?\*/#s', ' ', '/* echo $pin */' ), 'echo' ) === false );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — PIN không lọt ra, thẻ phiên không đoán được, PIN mặc định đóng mọi cửa.\n";
