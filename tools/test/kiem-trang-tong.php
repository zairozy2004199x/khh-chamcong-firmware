<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG CHI PHÍ TỔNG — GOM ĐƠN BA MẢNG, KHÔNG CHÉP ĐƠN.
 *
 * Anh Thắng 14/09/2026: *"1 trang chi phí tổng để gom 3 mảng lại"*, và chốt cách làm: *"4 plugin,
 * tạo mã riêng không trùng nhau, vì bộ phận có mã riêng, tk khoản riêng, quản lý riêng, chỉ là
 * kho đơn đẩy nó gom về 1 trang chi phí cho người duyệt"*.
 *
 * =============================================================================================
 * 🔴 BA THỨ BÀI NÀY CANH, VÀ CẢ BA ĐỀU HỎNG LẶNG LẼ NẾU SAI:
 *
 *   1. TRANG TỔNG KHÔNG ĐƯỢC CÓ KHO ĐƠN RIÊNG. Chép đơn sang kho thứ tư là có hai bản sao của
 *      cùng một đơn, tức hai sự thật — và chúng lệch nhau vào đúng lúc cần con số đúng nhất.
 *
 *   2. KHÔNG ĐƯỢC DÙNG CHUNG CHUỖI NÀO VỚI BẢN GỐC. Bốn plugin cài chung một WordPress: trùng
 *      tên lớp là trắng cả site, trùng đường REST là lượt hỏi của bản này rơi vào bản kia (đã
 *      cắn 08/09/2026), trùng khoá cấu hình là đổi cài đặt bên này đổi luôn bên kia.
 *
 *   3. QUYỀN DUYỆT PHẢI HỎI CHÍNH BẢNG PHÂN QUYỀN CỦA BẢN CHỨA ĐƠN. Dựng thang quyền riêng ở
 *      trang tổng là đường thứ hai trả lời cùng một câu hỏi — đã cắn một lần với `VAI_XEM_CA`.
 *
 * ⚠️ CHỖ MÙ, ghi rõ: bài này không chạy WordPress thật, nên không kiểm được `rest_url` hay lượt
 *    gọi HTTP. Nó soi mã nguồn và chạy phần LÕI (sổ bản · đăng nhập · gom) trên lớp giả.
 *
 * Chạy: php tools/test/kiem-trang-tong.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$GOC = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$TONG = $GOC . '/wordpress/vhcp-chi-phi-tong';

/* ═══ 1. CÓ MẶT, VÀ KHÔNG DỰNG BẢNG NÀO ═════════════════════════════════════════ */
t( '🔴 có plugin trang tổng', is_dir( $TONG ), $TONG );
t( 'có tệp gốc plugin', is_file( $TONG . '/vhcp-chi-phi-tong.php' ) );
t( 'có trang app.html', is_file( $TONG . '/templates/app.html' ) );

$ma_tong = '';
foreach ( (array) glob( $TONG . '/includes/*.php' ) as $f ) { $ma_tong .= file_get_contents( $f ); }
$ma_tong .= file_get_contents( $TONG . '/vhcp-chi-phi-tong.php' );
/* Bỏ chú thích: mấy chữ dưới đây nhắc tới rất nhiều trong phần giải thích. */
$ma_sach = preg_replace( '#/\*.*?\*/#s', ' ', $ma_tong );
$ma_sach = preg_replace( '#//[^\n]*#', ' ', $ma_sach );

t( '🔴 KHÔNG dựng bảng nào (không CREATE TABLE)',
	false === stripos( $ma_sach, 'CREATE TABLE' ), '' );
t( '🔴 và không gọi dbDelta', false === strpos( $ma_sach, 'dbDelta' ), '' );
t( '🔴 không INSERT/UPDATE/DELETE thẳng vào bảng nào',
	! preg_match( '/\b(INSERT\s+INTO|UPDATE\s+\$|DELETE\s+FROM)\b/i', $ma_sach ), '' );

/* ═══ 2. KHÔNG DÙNG CHUNG CHUỖI NÀO VỚI BẢN GỐC ═════════════════════════════════ */
t( '🔴 đường REST riêng (vhcpt/v1)', false !== strpos( $ma_sach, "'vhcpt/v1'" ), '' );
t( '🔴 KHÔNG dùng đường REST của bản gốc',
	false === strpos( $ma_sach, "'vhcp/v1'" ), '' );
t( '🔴 khoá GitHub riêng', false !== strpos( $ma_sach, "'vhcpt_gh_token'" ), '' );
t( '🔴 KHÔNG dùng khoá GitHub của bản gốc',
	false === strpos( $ma_sach, "'vhcp_gh_token'" ), '' );
t( '🔴 slug menu wp-admin riêng', false !== strpos( $ma_sach, "TRANG = 'vhcpt'" ), '' );
/* Tên lớp: mọi lớp khai ở đây phải mang tiền tố VHCPT_. Một lớp tên VHCP_… là "Cannot redeclare
   class" ngay lúc nạp — trắng CẢ SITE, kể cả bản đang chở sổ tiền. */
preg_match_all( '/^\s*class\s+([A-Za-z0-9_]+)/m', $ma_tong, $lop );
$lop_xau = array();
foreach ( (array) $lop[1] as $l ) { if ( 0 !== strpos( $l, 'VHCPT_' ) ) { $lop_xau[] = $l; } }
t( '🔴 mọi lớp mang tiền tố VHCPT_', ! $lop_xau, $lop_xau );

/* ═══ 3. ĐỊA CHỈ TRANG ══════════════════════════════════════════════════════════ */
$app_php = file_get_contents( $TONG . '/includes/class-vhcpt-app.php' );
t( "🔴 slug mặc định là 'chi-phi-kh'", false !== strpos( $app_php, "'chi-phi-kh'" ), '' );
/* ⚠️ Anh Thắng viết "/chi-phi-k&h". `sanitize_title()` bỏ dấu &, nên khai như thế là khai một
   đằng chạy một nẻo; và & trong URL mở phần tham số nên link dán vào chat sẽ đứt ở đó. */
t( '🔴 và KHÔNG có dấu & trong slug', false === strpos( "chi-phi-kh", '&' ), '' );
t( "🔴 KHÔNG giành đường của bản gốc ('chi-phi' / 'chi-phi-kvc')",
	false === strpos( $app_php, "'chi-phi'" ) && false === strpos( $app_php, "'chi-phi-kvc'" ), '' );

/* ═══ 4. KHOÁ GITHUB LÀ BÍ MẬT ══════════════════════════════════════════════════ */
$ad_php = file_get_contents( $TONG . '/includes/class-vhcpt-admin.php' );
t( '🔴 ô nhập khoá KHÔNG đổ khoá đang lưu ra value',
	false !== strpos( $ad_php, 'name="vhcpt_gh_token" id="vhcpt_gh_token" value=""' ), '' );
/* ⚠️ PHÉP NÀY PHẢI CHỈ ĐÚNG NHÁNH XỬ LÝ, không chỉ "có chữ ấy ở đâu đó". Bản nháp chỉ tìm chuỗi
   `vhcpt_gh_xoa` trong cả tệp — mà chuỗi ấy còn nằm ở chính cái ô tích trong HTML, nên bỏ hẳn
   nhánh `elseif` mà bài vẫn xanh. Đột biến sống, đã thử. */
t( '🔴 ô trống = GIỮ NGUYÊN: xoá chỉ khi ô tích được tích, còn lại là dán đè',
	(bool) preg_match( '#if\s*\(\s*!\s*empty\(\s*\$_POST\[.vhcpt_gh_xoa.\]\s*\)\s*\)'
		. '[\s\S]{0,200}?xoa_khoa\(\)[\s\S]{0,120}?elseif[\s\S]{0,160}?dat_khoa\(#', $ad_php ), '' );
t( '   và câu chữ nói rõ điều đó cho người khai',
	false !== mb_strpos( $ad_php, 'để trống là giữ nguyên' ), '' );

/* ═══ 5. TRANG KHÔNG GIỮ PIN Ở MÁY KHÁCH ════════════════════════════════════════ */
$html = file_get_contents( $TONG . '/templates/app.html' );
$html_js = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $html );
t( '🔴 KHÔNG lưu PIN vào localStorage/sessionStorage',
	! preg_match( '/(local|session)Storage\.setItem\s*\(\s*[^)]*pin/i', $html_js ), '' );
t( '   thẻ phiên thì có lưu (và chỉ thẻ)',
	false !== strpos( $html_js, 'sessionStorage.setItem' ), '' );
t( '🔴 xoá ô PIN ngay sau khi gửi', false !== strpos( $html_js, "o.value = ''" ), '' );

/* ═══ 6. LÕI CHẠY THẬT — SỔ BẢN · ĐĂNG NHẬP · QUYỀN ═════════════════════════════
 * Dựng hai bản GIẢ bằng lớp mang đúng quy tắc tên, rồi hỏi lõi. Phần này bắt được thứ mà soi
 * chữ không bắt được: đường đi.
 * ═══════════════════════════════════════════════════════════════════════════════ */
if ( ! defined( 'VHCPT_DIR' ) ) { define( 'VHCPT_DIR', $TONG . '/' ); }
if ( ! defined( 'VHCPT_VERSION' ) ) { define( 'VHCPT_VERSION', 'test' ); }

class VHCPAA_Don { public static function don_row( $m ) { return array( 'ma_don' => $m ); }
	public static function tong_xin_hien_tai( $m ) { return 111000; } }
class VHCPAA_Cfg {
	public static function get_users() {
		return array(
			array( 'ten' => 'Chị Duyệt', 'pin' => '4321', 'vai' => 'Quản lý', 'coso' => 'CS1' ),
			array( 'ten' => 'Anh Nhân',  'pin' => '1111', 'vai' => 'Nhân viên', 'coso' => 'CS1' ),
			/* Nửa kia của ca "một PIN ra hai người khác tên" — nửa còn lại ở VHCPCC_Cfg. */
			array( 'ten' => 'Anh Khác',  'pin' => '9999', 'vai' => 'Nhân viên', 'coso' => 'CS1' ),
			/* 🔴 NGƯỜI NÀY LÀ CẢ CÁI CHỐT: Kế toán cá nhân KHÔNG duyệt tạm ứng được, nhưng LÀ
			   NGƯỜI DUY NHẤT cấp được tiền, và cũng trả lại đơn được. Bản 1.0.0 gác mọi việc
			   bằng `duyetTU` nên họ bị khoá ngoài hoàn toàn. */
			array( 'ten' => 'Chị Kế Toán', 'pin' => '2468', 'vai' => 'Kế toán cá nhân', 'coso' => 'CS1' ),
		);
	}
	public static function get_quyen() {
		return array(
			'duyetTU'  => array( 'Quản lý' => true,  'Nhân viên' => false, 'Kế toán cá nhân' => false ),
			'capTU'    => array( 'Quản lý' => false, 'Nhân viên' => false, 'Kế toán cá nhân' => true ),
			'traDon'   => array( 'Quản lý' => true,  'Nhân viên' => false, 'Kế toán cá nhân' => true ),
			'duyetNCC' => array( 'Quản lý' => false, 'Nhân viên' => false, 'Kế toán cá nhân' => false ),
		);
	}
}
class VHCPBB_Don { public static function don_row( $m ) { return array( 'ma_don' => $m ); }
	public static function tong_xin_hien_tai( $m ) { return 222000; } }
class VHCPBB_Cfg {
	public static function get_users() {
		return array( array( 'ten' => 'Chị Duyệt', 'pin' => '4321', 'vai' => 'Kế toán', 'coso' => 'CS9' ) );
	}
	public static function get_quyen() {
		return array( 'duyetTU' => array( 'Kế toán' => false ) );
	}
}

/* 🔴 CA NÀY LÀ CHỐT THẬT CỦA `duoc_duyet()`: vai TÊN LÀ "Quản lý" nhưng bảng phân quyền của bản
   ấy nói KHÔNG. Bảng phân quyền sửa được trên màn và anh Thắng đã sửa nó thật; đoán theo tên vai
   là nói ngược lại thứ người ta vừa khai. Thiếu ca này thì một bản `return 'Quản lý' === $vai`
   vẫn xanh — đã thử, nó sống. */
class VHCPDD_Don { public static function don_row( $m ) { return null; } }
class VHCPDD_Cfg {
	public static function get_users() {
		return array( array( 'ten' => 'Chị Duyệt', 'pin' => '4321', 'vai' => 'Quản lý', 'coso' => 'CS3' ) );
	}
	public static function get_quyen() { return array( 'duyetTU' => array( 'Quản lý' => false ) ); }
}

/* 🔴 TRANG TỔNG KHÔNG ĐƯỢC GOM CHÍNH MÌNH. Nếu về sau nó có lớp tên `VHCPT_Don` thì sổ bản sẽ
   kể cả nó — trang tổng đi đọc bảng của chính trang tổng, một vòng không đáy và một mảng ma tên
   "T" hiện ra trên màn. */
class VHCPT_Don { public static function don_row( $m ) { return null; } }

require_once $TONG . '/includes/class-vhcpt-ban.php';
require_once $TONG . '/includes/class-vhcpt-auth.php';
require_once $TONG . '/includes/class-vhcpt-gom.php';

$ds = VHCPT_Ban::ds();
t( '🔴 sổ bản DÒ được bản giả aa', isset( $ds['aa'] ), array_keys( $ds ) );
t( '   và cả bản giả bb', isset( $ds['bb'] ), array_keys( $ds ) );
teq( '🔴 dựng đúng tên lớp của một bản', 'VHCPAA_Don', VHCPT_Ban::lop( 'aa', 'Don' ) );
t( '⚠️ bản không có thì trả rỗng, không đoán', '' === VHCPT_Ban::lop( 'zz', 'Don' ), VHCPT_Ban::lop( 'zz', 'Don' ) );

/* Tên hiện ra: chưa đặt -> mã viết hoa; đặt rồi -> theo cái đã đặt. */
teq( 'tên mặc định là mã viết hoa', 'AA', VHCPT_Ban::ten( 'aa' ) );
VHCPT_Ban::dat_ten( 'aa', 'Khu Vui Chơi' );
teq( 'đặt tên rồi thì dùng tên ấy', 'Khu Vui Chơi', VHCPT_Ban::ten( 'aa' ) );

/* ─── đăng nhập bằng PIN của các bản ─── */
$ng = VHCPT_Auth::tim_theo_pin( '4321' );
t( '🔴 tìm được người theo PIN', is_array( $ng ) && isset( $ng['ten'] ), $ng );
teq( '   đúng tên', 'Chị Duyệt', $ng['ten'] );
t( '🔴 gom đủ CẢ HAI bản người ấy có mặt',
	isset( $ng['bans']['aa'] ) && isset( $ng['bans']['bb'] ), array_keys( (array) $ng['bans'] ) );

/* 🔴 QUYỀN DUYỆT THEO TỪNG BẢN, KHÔNG PHẢI MỘT CỜ CHUNG. Cùng một người: Quản lý ở bản aa (được
   duyệt), Kế toán ở bản bb (không). Trộn thành một cờ là cho họ duyệt cả đơn của mảng mà bên ấy
   đã cố ý không cho. */
t( '🔴 được duyệt ở bản aa', ! empty( $ng['bans']['aa']['duyet'] ), $ng['bans']['aa'] );
t( '🔴 KHÔNG được duyệt ở bản bb', empty( $ng['bans']['bb']['duyet'] ), $ng['bans']['bb'] );
t( '🔴 vai TÊN LÀ "Quản lý" mà bảng phân quyền nói KHÔNG -> KHÔNG duyệt được',
	empty( $ng['bans']['dd']['duyet'] ), isset( $ng['bans']['dd'] ) ? $ng['bans']['dd'] : null );
t( '   (và bản dd vẫn ĐỌC được — vào để xem, không duyệt)',
	isset( $ng['bans']['dd'] ), array_keys( (array) $ng['bans'] ) );
teq( '🔴 sổ bản KHÔNG kể chính trang tổng ("t")', false, isset( VHCPT_Ban::ds()['t'] ) );

VHCPT_Auth::dat_toi( $ng );
teq( '   ban_duyet_duoc() chỉ kể bản aa', array( 'aa' ), VHCPT_Auth::ban_duyet_duoc() );
$doc = VHCPT_Auth::ban_doc_duoc(); sort( $doc );
teq( '   ban_doc_duoc() kể mọi bản người ấy có mặt', array( 'aa', 'bb', 'dd' ), $doc );

/* PIN của người không có quyền duyệt vẫn vào được — vào để XEM. */
$nv = VHCPT_Auth::tim_theo_pin( '1111' );
t( 'nhân viên thường vẫn đăng nhập được', is_array( $nv ) && isset( $nv['ten'] ), $nv );
t( '🔴 nhưng KHÔNG duyệt được gì', empty( $nv['bans']['aa']['duyet'] ), $nv['bans']['aa'] );

t( '⚠️ PIN sai -> null, không đoán', null === VHCPT_Auth::tim_theo_pin( '0000' ), '' );
t( '⚠️ PIN rỗng -> null', null === VHCPT_Auth::tim_theo_pin( '' ), '' );

/* 🔴 MỘT PIN RA HAI NGƯỜI KHÁC TÊN THÌ CHỐI. Hai bản có thể có hai người khác nhau trùng PIN;
   cho vào là cho một người mang danh người kia đi duyệt tiền. */
class VHCPCC_Don { public static function don_row( $m ) { return null; } }
class VHCPCC_Cfg {
	public static function get_users() {
		return array( array( 'ten' => 'Người Lạ', 'pin' => '9999', 'vai' => 'Quản lý', 'coso' => 'CS7' ) );
	}
	public static function get_quyen() { return array( 'duyetTU' => array( 'Quản lý' => true ) ); }
}
/* ⚠️ DÙNG MỘT PIN RIÊNG ('9999') CHO CA NÀY, đừng mượn lại PIN của ca trên. PHP hoist mọi khai
   báo lớp lên đầu tệp, nên lớp giả viết ở cuối bài vẫn CÓ MẶT từ phép đầu tiên — bản nháp cho
   VHCPCC_Cfg dùng luôn '4321', và thế là mọi phép đăng nhập ở trên đều bị chính cái chốt này
   chối. Bài kiểm đỏ, mã thì đúng: mất một lượt đi tìm lỗi ở đúng chỗ không có lỗi. */
$hai = VHCPT_Auth::tim_theo_pin( '9999' );
t( '🔴 một PIN ra hai người khác tên -> CHỐI, và nói lý do',
	is_array( $hai ) && isset( $hai['loi'] ) && false !== mb_strpos( $hai['loi'], 'hai người khác tên' ), $hai );

/* ─── thẻ phiên KHÔNG mang quyền ─── */
$the = VHCPT_Auth::phat_the( array( 'ten' => 'Chị Duyệt', 'bans' => array( 'aa' => array( 'vai' => 'Quản lý', 'duyet' => true ) ) ) );
t( '🔴 thẻ là chuỗi ngẫu nhiên, KHÔNG chở vai/quyền trong chính nó',
	is_string( $the ) && '' !== $the && false === stripos( $the, 'quan' ) && false === stripos( $the, 'duyet' ), $the );
$lay = VHCPT_Auth::nguoi_cua_the( $the );
teq( '   tra thẻ ra đúng người', 'Chị Duyệt', $lay['ten'] );
VHCPT_Auth::bo_the( $the );
t( '   thu thẻ rồi thì tra không ra', null === VHCPT_Auth::nguoi_cua_the( $the ), '' );

/* ═══ 6b. 🔴 MỖI VIỆC MỘT QUYỀN RIÊNG — không gác chung bằng "duyetTU" ═══════════
 * Anh Thắng 14/09/2026: *"trang tổng là trang do quản lý VÀ KẾ TOÁN duyệt chi phí"*. Kế toán cá
 * nhân không duyệt tạm ứng, nhưng là người DUY NHẤT cấp được tiền — gác chung một hành động là
 * khoá họ ngoài cửa, và trang tổng chỉ làm được nửa việc nó sinh ra để làm.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$kt = VHCPT_Auth::tim_theo_pin( '2468' );
t( 'kế toán đăng nhập được', is_array( $kt ) && isset( $kt['ten'] ), $kt );
VHCPT_Auth::dat_toi( $kt );
t( '🔴 kế toán KHÔNG duyệt tạm ứng được', ! VHCPT_Auth::duoc( 'aa', 'duyet' ), '' );
t( '🔴 nhưng CẤP TIỀN được', VHCPT_Auth::duoc( 'aa', 'cap' ), '' );
t( '🔴 và TRẢ LẠI đơn được', VHCPT_Auth::duoc( 'aa', 'traLai' ), '' );
t( '⚠️ còn xác nhận NCC thì không', ! VHCPT_Auth::duoc( 'aa', 'qtNcc' ), '' );

VHCPT_Auth::dat_toi( $ng );
t( '🔴 quản lý duyệt được', VHCPT_Auth::duoc( 'aa', 'duyet' ), '' );
t( '🔴 nhưng KHÔNG cấp tiền được', ! VHCPT_Auth::duoc( 'aa', 'cap' ), '' );
t( '   và trả lại được', VHCPT_Auth::duoc( 'aa', 'traLai' ), '' );
t( '⚠️ ở mảng bb thì không làm được gì', ! VHCPT_Auth::duoc( 'bb', 'duyet' )
	&& ! VHCPT_Auth::duoc( 'bb', 'cap' ), '' );
t( '⚠️ việc lạ -> chối, không đoán', ! VHCPT_Auth::duoc( 'aa', 'xoaSach' ), '' );
teq( '   ban_lam_duoc("cap") rỗng với quản lý', array(), VHCPT_Auth::ban_lam_duoc( 'cap' ) );

/* 🔴 THẺ CŨ (phát trước bản này, chưa có bảng quyền) KHÔNG ĐƯỢC CHO QUA HẾT. Lui về đúng một
   việc duyệt tạm ứng mà nó có — nới rộng khi thiếu dữ liệu là mở cửa bằng chính chỗ thiếu. */
VHCPT_Auth::dat_toi( array( 'ten' => 'Thẻ Cũ', 'bans' => array( 'aa' => array( 'vai' => 'Quản lý', 'duyet' => true ) ) ) );
t( '🔴 thẻ cũ: duyệt thì vẫn được', VHCPT_Auth::duoc( 'aa', 'duyet' ), '' );
t( '🔴 thẻ cũ: cấp tiền thì KHÔNG', ! VHCPT_Auth::duoc( 'aa', 'cap' ), '' );
VHCPT_Auth::dat_toi( $ng );

/* ═══ 6c. API: MỖI VIỆC GÁC BẰNG HÀNH ĐỘNG CỦA CHÍNH NÓ ════════════════════════ */
$api_php = file_get_contents( $TONG . '/includes/class-vhcpt-api.php' );
foreach ( array(
	array( 'duyet',   'duyet' ),
	array( 'cap',     'cap' ),
	array( 'tra_lai', 'traLai' ),
	array( 'qt_ncc',  'qtNcc' ),
) as $c_api ) {
	t( '🔴 ' . $c_api[0] . '() gác bằng «' . $c_api[1] . '», không gác chung',
		(bool) preg_match( '#function ' . $c_api[0] . '\([^)]*\)\s*\{\s*\$c = self::chot_ghi\( \$args, .'
			. $c_api[1] . '. \);#', $api_php ), '' );
}
/* ⚠️ Và bảng tên hành động phải khớp với `VHCP_Cfg::actions()` của bản gốc — gõ sai một chữ thì
   hàm tra trả "không có quyền", im lặng, nhìn y như người ấy chưa được khai quyền. */
$cfg_goc = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
foreach ( VHCPT_Auth::VIEC as $viec => $hd ) {
	t( '🔴 hành động «' . $hd . '» có thật trong bảng phân quyền của bản gốc',
		false !== strpos( $cfg_goc, "'key' => '" . $hd . "'" ), $hd );
}

/* ═══ 6d. CHI TIẾT ĐƠN ĐỌC THẲNG, KHÔNG QUA get_don() ═══════════════════════════
 * 🔴 `get_don()` gác theo PHIÊN của chính bản ấy (`VHCP_Auth`), mà trang tổng mượn sổ người dùng
 *    chứ không mượn phiên. Gọi vào đấy là hỏi một câu bên kia không có ngữ cảnh để trả lời.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$gom_php = file_get_contents( $TONG . '/includes/class-vhcpt-gom.php' );
t( '🔴 trang tổng KHÔNG gọi get_don() của bản nào',
	false === strpos( preg_replace( '#/\*[\s\S]*?\*/#', ' ', $gom_php . $api_php ), 'get_don' ), '' );
t( '🔴 đọc dòng chi từ bảng «chiphi» (không phải «cp»)',
	false !== strpos( $gom_php, "\$tien_to . 'chiphi'" ), '' );
/* ⚠️ SOI MÃ ĐÃ BỎ CHÚ THÍCH. Bản nháp soi cả tệp và bắt ngay chính câu giải thích *vì sao*
   không xếp theo id — bài đỏ vì đọc trúng lời cảnh báo về đúng cái nó đang canh. Cùng cái bẫy
   đã gặp với `kiem-tach-ban-vung.php` hôm 13/09. */
$gom_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $gom_php );
t( '⚠️ và xếp theo tao_luc, không xếp theo id (id là VARCHAR)',
	false !== strpos( $gom_ma, 'ORDER BY tao_luc' )
		&& false === strpos( $gom_ma, 'ORDER BY id' ), '' );

/* ═══ 7. GIAO KÈO TRẠNG THÁI VỚI CÁC BẢN ════════════════════════════════════════
 * 🔴 Trạng thái là chuỗi tiếng Việt có dấu, và nó là GIAO KÈO giữa bốn plugin. Đổi một chữ ở một
 *    bản là đơn của bản ấy biến mất khỏi trang tổng — không câu lỗi nào, chỉ là bảng ngắn đi.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$don_goc = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
foreach ( array(
	VHCPT_Gom::CHO_DUYET,
	VHCPT_Gom::CHO_CAP,
	VHCPT_Gom::CHO_QT,
) as $tt ) {
	t( '🔴 bản gốc vẫn dùng trạng thái «' . $tt . '»',
		false !== mb_strpos( $don_goc, "'" . $tt . "'" ), $tt );
}

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
