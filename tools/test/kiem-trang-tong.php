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
/* ⚠️ SOI CHUỖI CHỈ CÓ TRONG MÃ, KHÔNG SOI CHỮ CŨNG NẰM Ở CHÚ THÍCH. Đã cắn thật: một phép dò
   "Con số cộng ở trên là của" mà chính khối chú thích giải thích *vì sao* phải nói ra cũng mang
   đúng câu ấy — đục thủng mã mà bài vẫn xanh, tức phép kiểm nói dối. */
$html_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $html );
$html_js = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $html );
t( '🔴 KHÔNG lưu PIN vào localStorage/sessionStorage',
	! preg_match( '/(local|session)Storage\.setItem\s*\(\s*[^)]*pin/i', $html_js ), '' );
t( '   thẻ phiên thì có lưu (và chỉ thẻ)',
	false !== strpos( $html_js, 'sessionStorage.setItem' ), '' );
/* ⚠️ Bám VIỆC, đừng bám khoảng trắng: bản trước tìm nguyên văn `o.value = ''` rồi đỏ ngay lượt
   viết lại giao diện chỉ vì mã mới viết `o.value=''`. Phép đỏ oan là phép người ta học cách bỏ qua. */
t( '🔴 xoá ô PIN ngay sau khi gửi',
	(bool) preg_match( "#\\bo\\.value\\s*=\\s*''#", $html_js ), '' );

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
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ADMIN ĐỨNG NGOÀI MA TRẬN PHÂN QUYỀN — ca đã cắn thật 14/09/2026.
 *
 * `VHCP_Cfg::roles()` của bản thật trả về *Giám đốc · Quản lý · Kế toán cá nhân · Kế toán NCC ·
 * Nhân viên* — KHÔNG có 'Admin'. Bảng phân quyền chỉ có cột cho những vai ấy, nên
 * `$q['duyetTU']['Admin']` không tồn tại và trang tổng đọc ra `false`: Admin đăng nhập vào thấy
 * "vai của bạn không được duyệt ở mảng nào". Anh Thắng sau khi cài: *"chi phí tổng chưa có"*.
 *
 * Bản giả này dựng ĐÚNG hình ấy — `roles()` không kể Admin, và ma trận cũng không có cột Admin.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
class VHCPEE_Don { public static function don_row( $m ) { return null; } }
class VHCPEE_Cfg {
	public static function roles() {
		return array( 'Giám đốc', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' );
	}
	public static function get_users() {
		return array( array( 'ten' => 'Sếp Tổng', 'pin' => '8642', 'vai' => 'Admin', 'coso' => '' ) );
	}
	public static function get_quyen() {
		return array(
			'duyetTU'  => array( 'Quản lý' => true ),
			'capTU'    => array( 'Kế toán cá nhân' => true ),
			'traDon'   => array( 'Quản lý' => true ),
			'duyetNCC' => array( 'Kế toán NCC' => true ),
		);
	}
}

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

/* ═══ 6b-2. 🔴 ADMIN PHẢI LÀM ĐƯỢC MỌI VIỆC ═════════════════════════════════════ */
$ad2 = VHCPT_Auth::tim_theo_pin( '8642' );
t( 'Admin đăng nhập được', is_array( $ad2 ) && isset( $ad2['ten'] ), $ad2 );
VHCPT_Auth::dat_toi( $ad2 );
foreach ( array_keys( VHCPT_Auth::VIEC ) as $v_ad ) {
	t( '🔴 Admin làm được việc «' . $v_ad . '» ở bản ee', VHCPT_Auth::duoc( 'ee', $v_ad ), '' );
}
t( '   và duoc_duyet() cũng nói thế (một đường, không hai)',
	VHCPT_Auth::duoc_duyet( 'ee', 'Admin' ), '' );
/* ⚠️ NHƯNG KHÔNG PHẢI "AI CŨNG QUA". Vai có mặt trong ma trận thì vẫn tra ma trận — nới cho
   Admin là vì bảng ấy không có chỗ trả lời về Admin, không phải vì tên vai nghe to. */
t( '🔴 vai «Nhân viên» ở bản ee vẫn KHÔNG duyệt được',
	! VHCPT_Auth::bang_quyen( 'ee', 'Nhân viên' )['duyet'], '' );
t( '🔴 và «Quản lý» thì duyệt được, nhưng KHÔNG cấp tiền',
	VHCPT_Auth::bang_quyen( 'ee', 'Quản lý' )['duyet']
		&& ! VHCPT_Auth::bang_quyen( 'ee', 'Quản lý' )['cap'], '' );
/* 🔴 ĐỐI CHỨNG: bản nào ĐÃ đưa Admin vào ma trận thì tra bình thường, không nới nữa. Bản aa có
   cột Admin? Không — nhưng roles() của nó cũng không khai, nên nó cùng ca với ee. Phép dưới
   canh đúng cái điều kiện: "không nằm trong roles()", chứ không phải "tên là Admin". */
$auth_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $TONG . '/includes/class-vhcpt-auth.php' ) );
t( '🔴 điều kiện nới là "Admin KHÔNG nằm trong roles()", không phải chỉ so tên',
	false !== strpos( $auth_ma, "in_array( 'Admin', \$vai_ds, true )" ), '' );
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

/* ═══ 6d. DANH SÁCH ĐỌC THẲNG; get_don() CHỈ DÙNG SAU KHI MƯỢN PHIÊN ════════════
 *
 * 🔴 `get_don()` gác theo PHIÊN của chính bản ấy (`VHCP_Auth`). Gọi nó mà chưa mượn phiên là hỏi
 *    một câu bên kia không có ngữ cảnh để trả lời — nó chối một đơn đang nằm sờ sờ trên màn.
 *
 * ⚠️ PHÉP NÀY ĐÃ NỚI 14/09/2026, CÓ ĐIỀU KIỆN. Bản trước cấm tiệt mọi lời gọi `get_don` ở cả hai
 *    tệp. Nay khối thừa/thiếu (anh Thắng: *"phần thừa thiếu ở cuối trang"*) phải hỏi chính lõi
 *    bản mảng — tự trừ "tạm ứng − thực chi" ở đây là dựng bản thứ hai cho một con số tiền, rồi
 *    hai bản lệch nhau. Nên luật mới:
 *      · `class-vhcpt-gom.php` (dựng DANH SÁCH, chạy 200 đơn một lượt): vẫn CẤM TIỆT.
 *      · `class-vhcpt-api.php` (một đơn, khi người ta bấm Xem): cho, nhưng BẮT BUỘC có
 *        `muon_phien()` đứng trước trong cùng hàm.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$gom_php = file_get_contents( $TONG . '/includes/class-vhcpt-gom.php' );
t( '🔴 lượt dựng DANH SÁCH không gọi get_don() (200 đơn, không có phiên)',
	false === strpos( preg_replace( '#/\*[\s\S]*?\*/#', ' ', $gom_php ), 'get_don' ), '' );
$api_sach = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $api_php );
if ( false !== strpos( $api_sach, 'get_don' ) ) {
	t( '🔴 nơi nào gọi get_don() thì phải MƯỢN PHIÊN trước',
		(bool) preg_match( '#muon_phien\( \$khoa \);[\s\S]{0,400}?get_don#', $api_sach ), '' );
	t( '🔴 và chỉ gọi cho MỘT đơn, không gọi trong vòng lặp',
		! preg_match( '#foreach[\s\S]{0,400}?get_don#', $api_sach ), '' );
}
t( '🔴 đọc dòng chi từ bảng «chiphi» (không phải «cp»)',
	false !== strpos( $gom_php, "\$tien_to . 'chiphi'" ), '' );
/* ⚠️ SOI MÃ ĐÃ BỎ CHÚ THÍCH. Bản nháp soi cả tệp và bắt ngay chính câu giải thích *vì sao*
   không xếp theo id — bài đỏ vì đọc trúng lời cảnh báo về đúng cái nó đang canh. Cùng cái bẫy
   đã gặp với `kiem-tach-ban-vung.php` hôm 13/09. */
$gom_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $gom_php );
t( '⚠️ và xếp theo tao_luc, không xếp theo id (id là VARCHAR)',
	false !== strpos( $gom_ma, 'ORDER BY tao_luc' )
		&& false === strpos( $gom_ma, 'ORDER BY id' ), '' );

/* ═══ 6d-2. 🔴 VẼ TRANG THẬT, RỒI ĐÒI JAVASCRIPT CÒN CHẠY ĐƯỢC ══════════════════
 * Anh Thắng 14/09/2026: *"trang tổng đang trắng"*.
 *
 * 🔴 Bản 1.3.0 nhét JSON cấu hình vào chỗ một chú thích nằm GIỮA câu lệnh:
 *        var BOOT = /*…mốc…*​/ null;
 *    Thay xong thành `var BOOT = {"api":"…"} null;` — SyntaxError ngay dòng đầu, nên TOÀN BỘ
 *    script không chạy và trang ra trắng trơn.
 *
 * ⚠️ PHÉP KIỂM CŨ KHÔNG BẮT ĐƯỢC, và đó mới là bài học: nó chỉ soi "cái mốc đã biến mất chưa".
 *    Mốc biến mất thật — chỉ là thứ thay vào chỗ đó không phải JS hợp lệ. Soi dấu vết của một
 *    việc không bằng soi KẾT QUẢ của việc ấy.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$tep_html = $TONG . '/templates/app.html';
$html_goc = file_get_contents( $tep_html );
$so_moc   = substr_count( $html_goc, '__VHCPT_BOOT__' );
teq( '🔴 có ĐÚNG MỘT chỗ cắm cấu hình', 1, $so_moc );
/* Giả lập đúng lượt máy chủ xuất trang. */
$html_ra = str_replace( '__VHCPT_BOOT__',
	json_encode( array( 'api' => 'https://x/wp-json/vhcpt/v1/call', 'ver' => '9.9.9' ) ), $html_goc );
t( '🔴 sau khi thay, không còn mốc nào sót', false === strpos( $html_ra, '__VHCPT_BOOT__' ), '' );

/* 🔴 ĐÒI JS CÒN HỢP LỆ — phép mà bản trước thiếu. Tách đúng khối <script> chở mã rồi nhờ node
   đọc; node không có thì bỏ qua, và NÓI RA là đã bỏ qua (một phép im lặng bỏ qua là một phép
   không tồn tại). */
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "  ⚠️ không có node — bỏ qua phép soi cú pháp JS\n";
} else {
	if ( ! preg_match_all( '#<script(?![^>]*type="application/json")[^>]*>([\s\S]*?)</script>#', $html_ra, $m_js ) ) {
		t( '🔴 bốc được khối script', false, '' );
	} else {
		$js = implode( "\n;\n", $m_js[1] );
		$tam = sys_get_temp_dir() . '/vhcpt-kiem-' . getmypid() . '.js';
		file_put_contents( $tam, $js );
		$ra_node = 0;
		$out = array();
		exec( 'node --check ' . escapeshellarg( $tam ) . ' 2>&1', $out, $ra_node );
		@unlink( $tam );
		t( '🔴 JavaScript của trang ĐÃ XUẤT còn hợp lệ (không SyntaxError)',
			0 === $ra_node, implode( "\n", $out ) );
	}
}

/* ⚠️ VÀ KHÔNG BAO GIỜ ĐỂ TRANG TRẮNG TRƠN. Thiếu cấu hình thì phải nói ra — trắng trơn là kiểu
   hỏng tệ nhất: người dùng không có gì để đọc, không có gì để gửi lại cho người sửa. */
t( '🔴 thiếu cấu hình thì hiện câu báo, không để trắng',
	false !== mb_strpos( $html_goc, 'Chưa nhận được cấu hình' ), '' );
/* ⚠️ Và máy chủ phải BIẾT là nó đã thay được hay chưa — `str_replace` im lặng khi không tìm thấy. */
$app_php2 = file_get_contents( $TONG . '/includes/class-vhcpt-app.php' );
t( '🔴 máy chủ đếm số lần thay và chối khi khác 1',
	(bool) preg_match( '#str_replace\([^;]*\$so_thay \);[\s\S]{0,80}?if \( 1 !== \$so_thay \)#', $app_php2 ), '' );

/* ═══ 6e. 🔴 HOOK VÀ LƯỢT NẠP LẠI LUẬT ĐƯỜNG DẪN ════════════════════════════════
 * Anh Thắng 14/09/2026: *"trang tổng bị lỗi, vào là sập"*.
 *
 * 🔴 Bản đầu gọi `flush_rewrite_rules()` NGAY trong `register_activation_hook`. Lúc ấy hook
 *    `init` của các plugin KHÁC chưa chạy, nên chúng chưa khai đường của mình; `flush` ghi lại
 *    TOÀN BỘ bảng luật theo đúng những gì đang có — tức ghi lại một bảng THIẾU. /chi-phi,
 *    /cham-cong, /ghe… đồng loạt 404, không câu lỗi nào, nhìn y như cả site sập.
 *
 * ⚠️ Chú thích trong `VHCP_App::init()` của bản gốc đã nói đúng điều này từ trước, và bản gốc
 *    vì thế chỉ ĐẶT CỜ lúc kích hoạt rồi flush ở `init` ưu tiên 99.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$boot_tong = file_get_contents( $TONG . '/vhcp-chi-phi-tong.php' );
$boot_ma   = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $boot_tong );

t( '🔴 KHÔNG flush_rewrite_rules() trong register_activation_hook',
	! preg_match( '#register_activation_hook[\s\S]{0,300}?flush_rewrite_rules#', $boot_ma ), '' );
t( '🔴 và cũng không flush trong register_deactivation_hook',
	! preg_match( '#register_deactivation_hook[\s\S]{0,300}?flush_rewrite_rules#', $boot_ma ), '' );
t( '🔴 kích hoạt chỉ ĐẶT CỜ',
	(bool) preg_match( "#register_activation_hook[\s\S]{0,200}?update_option\( 'vhcpt_flush_rewrite'#", $boot_ma ), '' );
t( '🔴 và lượt nạp lại luật nằm ở hook `init` ưu tiên MUỘN (>= 99)',
	(bool) preg_match( "#add_action\( 'init', 'vhcpt_nap_lai_duong', (9[9]|[1-9]\d{2,}) \)#", $boot_ma ), '' );
t( '🔴 đường dẫn khai ở `init`, KHÔNG ở `plugins_loaded`',
	(bool) preg_match( "#add_action\( 'init', array\( 'VHCPT_App', 'init' \)#", $boot_ma )
		&& ! preg_match( "#plugins_loaded[\s\S]{0,200}?VHCPT_App::init#", $boot_ma ), '' );
/* ⚠️ Cờ phải được XOÁ sau khi dùng, không thì mỗi lượt tải trang là một lượt ghi lại cả bảng
   luật — chậm dần và không ai biết vì sao. */
t( '⚠️ dùng cờ xong thì xoá cờ',
	(bool) preg_match( "#delete_option\( 'vhcpt_flush_rewrite' \);[\s\S]{0,120}?flush_rewrite_rules#", $boot_ma ), '' );

/* ═══ 6b. HAI MÀN KẾ TOÁN: QUYẾT TOÁN ĐẦY ĐỦ VÀ XUẤT MISA ═══════════════════════
 *
 * Anh Thắng 14/09/2026: *"Trang tổng là xem, duyệt, quyết toán, xuất misa, sau này kế toán và
 * quản lý làm trên trang này, không làm trên trang bộ phận."*
 *
 * 🔴 TẦNG MÁY CHỦ XONG TRƯỚC GIAO DIỆN MỘT BẢN — `qtCn`, `misa`, `misaXong` đã nhận lời gọi từ
 *    bản 1.4.0, nhưng màn không có nút nào bấm tới. Nghĩa là kế toán vẫn phải mở trang mảng,
 *    đúng thứ câu trên bảo thôi. Bài này canh CẢ HAI ĐẦU cùng có mặt: thiếu một đầu thì tính
 *    năng coi như không tồn tại, mà không đầu nào kêu.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$api_ma = file_get_contents( $TONG . '/includes/class-vhcpt-api.php' );
$trama_ma = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-trama.php' );

t( '🔴 tab Chờ quyết toán có nút Quyết toán cá nhân (qtCn)',
	(bool) preg_match( "#qt:\s*\[[^\]]*viec:'qtCn'#", $html ), '' );
t( 'và vẫn giữ nút Xác nhận NCC bên cạnh',
	(bool) preg_match( "#qt:\s*\[[^\]]*viec:'qtNcc'#", $html ), '' );
t( '🔴 máy chủ nhận đúng hai việc ấy',
	false !== strpos( $api_ma, '\'qtCn\' === $viec' ) && false !== strpos( $api_ma, '\'qtNcc\' === $viec' ), '' );

foreach ( array( 'veMisa', 'docMisa', 'veMisaKq', 'taiMisa', 'xongMisa', 'oCsv', 'nutXong' ) as $h ) {
	t( 'màn MISA có hàm ' . $h . '()', false !== strpos( $html, 'function ' . $h . '(' ), '' );
}
t( '🔴 màn MISA gọi đúng hai cổng máy chủ',
	(bool) preg_match( "#goi\( *'misa' *,#", $html ) && (bool) preg_match( "#goi\( *'misaXong' *,#", $html ), '' );
t( '🔴 và máy chủ có nhận cả hai',
	false !== strpos( $api_ma, '\'misa\' === $viec' ) && false !== strpos( $api_ma, '\'misaXong\' === $viec' ), '' );

/* 🔴 XEM KHÔNG PHẢI LÀ XUẤT. Đánh dấu "đã xuất" ngay lúc bày bảng thì một lượt xem thử làm lượt
      xuất THẬT sau đó ra tệp rỗng — và không ai hiểu vì sao. Nút đánh dấu phải KHOÁ tới khi đã
      tải tệp; chỗ mở khoá duy nhất nằm trong `taiMisa()`. */
t( '🔴 nút Đánh dấu bị khoá cho tới khi đã tải tệp',
	(bool) preg_match( "#MISA\.daTai\[b\.ban\][\s\S]{0,120}?disabled#", $html ), '' );
t( '🔴 và cờ đã-tải chỉ được bật TRONG hàm tải tệp',
	1 === preg_match_all( "#MISA\.daTai\[[^\]]+\] = true#", $html ), '' );
t( '🔴 lượt đọc bút toán KHÔNG tự đánh dấu đã xuất',
	! preg_match( "#function docMisa\(\)[\s\S]{0,600}?goi\( *'misaXong'#", $html ), '' );
t( 'đọc bút toán mặc định lấy nhánh «chưa xuất lần nào»',
	(bool) preg_match( "#mode:'chuaxuat'#", $html ), '' );

/* ⚠️ maDons RỖNG KHÔNG PHẢI LỖI — lõi MISA chỉ trả mã đơn ở nhánh chi phí thường. Bật nút lên
      trong cảnh ấy là người ta bấm rồi nhận câu chối, nghe như hỏng. */
t( '⚠️ mảng không có mã đơn thì nói thẳng, không bày nút bấm hụt',
	(bool) preg_match( "#function nutXong[\s\S]{0,400}?không có mã đơn để đánh dấu#u", $html ), '' );

/* ⚠️ Tệp .csv phải có BOM, không thì Excel mở ra tiếng Việt thành ký tự lạ. */
t( '⚠️ tệp .csv xuất ra có BOM cho Excel',
	false !== strpos( $html, "new Blob(['\xef\xbb\xbf'" ), '' );
t( '⚠️ ô chứa phẩy/nháy/xuống dòng được bọc nháy kép',
	(bool) preg_match( '#function oCsv[\s\S]{0,300}?replace\(/"/g#', $html ), '' );
t( 'mỗi mảng một tệp riêng (tên tệp mang khoá mảng)',
	(bool) preg_match( "#download = 'misa-' \+ b\.ban#", $html ), '' );

/* 🔴 Tab MISA chỉ hiện cho người có quyền xuất ở ít nhất một mảng. */
t( '🔴 tab Xuất MISA chỉ hiện khi có quyền',
	(bool) preg_match( "#if \( coQuyen\('misa'\) \)#", $html ), '' );
t( 'và nó KHÔNG gọi ds() như ba tab kia',
	(bool) preg_match( "#if \(NHOM === TAB_MISA\)\{ veMisa\(\); return; \}#", $html ), '' );

t( 'màn MISA liệt kê mã đơn của lô (anh Thắng: "xuất misa theo đơn")',
	(bool) preg_match( '#Lô này gồm <b>#u', $html ), '' );
t( '⚠️ và nói rõ khi nhánh ấy không chốt theo mã đơn',
	(bool) preg_match( '#không chốt theo mã đơn#u', $html ), '' );

/* ═══ 6c. BẤM XEM: TRẠNG THÁI ĐƠN + THỪA THIẾU ═══════════════════════════════════
 *
 * Anh Thắng 14/09/2026: *"Bổ sung vào để hiện trạng thái đơn khi bấm xem, và phần thừa thiếu ở
 * cuối trang."*
 *
 * 🔴 CON SỐ THỪA/THIẾU PHẢI HỎI LÕI BẢN MẢNG, KHÔNG TỰ TRỪ Ở ĐÂY. Luật "tạm ứng − thực chi" có
 *    mấy chỗ tinh (chưa cấp tiền thì chênh lệch là 0; thực chi lấy ô thực mua nếu có; phần NCC
 *    tính riêng). Chép luật sang trang tổng là dựng bản thứ hai cho cùng một câu hỏi, rồi hai
 *    bản lệch nhau — mà lệch ở con số tiền thì kế toán tin con số nào?
 * ═══════════════════════════════════════════════════════════════════════════════ */
t( '🔴 màn có dải trạng thái đơn', false !== strpos( $html, 'function veDaiTT(' ), '' );
t( '🔴 và dải ấy phủ đủ bảy bước',
	(bool) preg_match( "#TT_LUONG = \['Nháp','Chờ duyệt tạm ứng','Chờ cấp tạm ứng','Đã cấp tạm ứng','Chờ quyết toán','Đã quyết toán','Đã xuất MISA'\]#u", $html ), '' );
/* ⚠️ Trạng thái LẠ (bản mảng đổi chữ) -> không được tô xanh cả dải như thể mọi bước đã xong. */
t( '⚠️ trạng thái lạ thì KHÔNG bước nào là "đã qua"',
	(bool) preg_match( '#daQua = \(i >= 0 && k < i\)#', $html ), '' );
t( '🔴 màn có khối thừa/thiếu', false !== strpos( $html, 'function veThuaThieu(' ), '' );
/* ⚠️ Chưa cấp tiền thì chưa ai đưa đồng nào — không thể thừa. */
/* ⚠️ ĐỔI 14/09/2026 (lượt hai). Bản trước canh "chưa cấp tiền thì KHÔNG vẽ gì". Đúng luật,
   nhưng khoảng trắng ấy làm anh Thắng tưởng màn thiếu mất một khối: *"sao cái này chưa có"*.
   Người dùng không phân biệt được "chưa tới lúc" với "hỏng" khi cả hai đều là khoảng trắng.
   Nay luật là: KHÔNG bày con số thừa/thiếu, nhưng PHẢI nói ra vì sao chưa có. */
t( '🔴 chưa cấp tiền thì KHÔNG bày con số thừa/thiếu',
	(bool) preg_match( '#if \(!t\.daCapTien\)\{#', $html ), '' );
t( '🔴 nhưng NÓI RA vì sao chưa có, không để trắng',
	false !== mb_strpos( $html_ma, 'đơn chưa tới bước' ), '' );
t( '⚠️ bản mảng đời cũ không trả được số thì im hẳn',
	(bool) preg_match( '#if \(!t\) return \x27\x27;#', $html ), '' );
t( 'nói rõ ai trả ai bù, không chỉ ra con số',
	false !== mb_strpos( $html, 'NV trả lại kế toán' ) && false !== mb_strpos( $html, 'kế toán bù cho NV' ), '' );
t( '🔴 máy chủ gửi kèm khối tiền khi bấm Xem',
	(bool) preg_match( '#\'tien\' => [$]tien,#', $api_ma ), '' );
t( '🔴 và nó HỎI get_don() của bản mảng, không tự trừ',
	(bool) preg_match( '#\'get_don\'[\s\S]{0,300}?muon_phien\( [$]khoa \)#', $api_ma ), '' );

t( '⚠️ bản mảng đời cũ thiếu get_don() thì vẫn bày được dòng chi',
	(bool) preg_match( '#[$]tien = null;#', $api_ma ), '' );
/* Giao diện: ô chi tiết là KHỐI riêng, không trông như một dòng nữa của bảng. */
t( '🔴 ô chi tiết có khối riêng .hop', false !== strpos( $html, 'tr.ct .hop{' ), '' );
t( 'nút Xem đổi chữ khi đang mở', false !== mb_strpos( $html, "▾ Đóng" ), '' );

/* ═══ 6e. TRA CHI PHÍ GOM BA MẢNG · MỐC · LỊCH SỬ · VIỀN ════════════════════════
 *
 * Anh Thắng 14/09/2026: *"nếu trang tổng khi gõ, nó tự gom 3 trang lại được không"*,
 * *"bên trang tổng khi bấm xem, thì nó cũng phải đủ 2 phần này trong đơn đó"*,
 * *"Nhớ tạo viền trang thẩm mỹ tí"*.
 * ═══════════════════════════════════════════════════════════════════════════════ */
t( '🔴 có cổng tra gom ba mảng', false !== strpos( $api_ma, '\'tra\' === $viec' ), '' );
t( '🔴 nó gọi LÕI TRA của từng bản, không tự đọc bảng',
	(bool) preg_match( '#lop\( [$]khoa, \'TraMa\' \)#', $api_ma ), '' );
t( '⚠️ và mượn phiên trước khi gọi',
	(bool) preg_match( '#\'TraMa\'[\s\S]{0,400}?muon_phien\( [$]khoa \)#', $api_ma ), '' );
/* 🔴 Chỉ gom mảng người ấy có mặt — gom hết rồi lọc lúc vẽ thì con số tổng ở đầu màn đã kể cả
   mảng họ không được nhìn, mà đó là con số người ta đọc trước tiên. */
t( '🔴 chỉ gom mảng người ấy đọc được',
	(bool) preg_match( '#foreach \( VHCPT_Auth::ban_doc_duoc\(\) as \$khoa \)[\s\S]{0,300}?TraMa#', $api_ma ), '' );
t( '🔴 màn có tab Tra chi phí', false !== strpos( $html, "TAB_TRA" ), '' );
t( 'và hộp chọn cửa hàng + loại chi phí (không bắt gõ tay)',
	false !== strpos( $html, "oChon('tCoso'" ) && false !== strpos( $html, "oChon('tLoai'" ), '' );
/* ⚠️ Bảng cắt 200 dòng mà con số cộng là của cả lát cắt — im lặng thì người đọc tự cộng tay rồi
   kết luận phần mềm tính sai. */
t( '⚠️ nói ra khi bảng bị cắt bớt dòng',
	false !== mb_strpos( $html_ma, 'Con số cộng ở trên là của' ), '' );
t( '🔴 lõi tra nhận bộ lọc theo LOẠI chi phí',
	(bool) preg_match( '#[$]f_loai = mb_strtolower#', $trama_ma ), '' );
t( '⚠️ danh sách loại gom từ DỮ LIỆU THẬT, không từ danh mục',
	(bool) preg_match( '#\$loai_list = array_keys\( \$loai_set \);#', $trama_ma ), '' );

t( '🔴 bấm Xem có mốc ai làm gì lúc nào', false !== strpos( $html, 'function veMoc(' ), '' );
t( '🔴 và có lịch sử chỉnh đơn', false !== strpos( $html, 'function veLichSu(' ), '' );
t( '🔴 máy chủ gửi kèm cả hai', false !== strpos( $api_ma, "'lichSu' => \$lich_su," )
	|| (bool) preg_match( "#'lichSu' =>#", $api_ma ), '' );
/* ⚠️ get_log() trả nhật ký của CẢ bản — phải lọc đúng mã đơn, không dội nguyên xuống màn. */
t( '⚠️ lịch sử lọc đúng mã đơn theo ô Đối tượng',
	(bool) preg_match( '#doiTuong[\s\S]{0,120}?!== [$]ma \) \{ continue; \}#', $api_ma ), '' );
t( 'viền thẻ có thật, không chỉ bóng đổ', false !== strpos( $html, '.card{background:#fff;border:1px solid' ), '' );

/* ═══ 6f. MỌI HÀM CỦA MÀN PHẢI CÒN MẶT ══════════════════════════════════════════
 *
 * 🔴 ĐÃ CẮN THẬT HAI LẦN TRONG MỘT BUỔI, 14/09/2026. Viết lại một khối của `app.html` bằng cách
 *    cắt từ mốc A tới mốc B rồi dán khối mới vào — mà giữa hai mốc ấy còn nằm nguyên màn Xuất
 *    MISA và màn Tra chi phí. Chúng biến mất sạch. Tệp vẫn đúng cú pháp, `node --check` vẫn
 *    xanh, trang vẫn mở ra bình thường — chỉ là bấm vào tab thì không có gì xảy ra.
 *
 * ⚠️ Phép này rẻ và bắt được đúng kiểu hỏng ấy: kê tên MỌI hàm màn đang cần, thiếu một cái là
 *    đỏ. Thêm màn mới thì thêm tên vào đây.
 * ═══════════════════════════════════════════════════════════════════════════════ */
foreach ( array(
	/* cổng PIN + khung màn */      'veVao', 'veChinh', 'napDs', 'veBang', 'coQuyen',
	/* xem một đơn */               'xem', 'veChiTiet', 'veDaiTT', 'veChipKhoa', 'veThuaThieu', 'veMoc', 'veLichSu',
	/* xuất MISA */                 'veMisa', 'docMisa', 'veMisaKq', 'taiMisa', 'xongMisa', 'oCsv', 'nutXong',
	/* tra chi phí ba mảng */       'veTra', 'docTra', 'veTraKq', 'oChon',
	/* tổng quan (Dashboard) */     'veTongQuan', 'docTongQuan', 'veTongQuanKq', 'mauBuoc',
	/* việc trên đơn */             'lam',
) as $ham ) {
	t( '🔴 màn còn hàm ' . $ham . '()', false !== strpos( $html, 'function ' . $ham . '(' ), '' );
}

/* ═══ 6g. BỐ CỤC XEM ĐƠN GIỐNG TRANG MẢNG ═══════════════════════════════════════
 * Anh Thắng 14/09/2026: *"giao diện trực quan như này đi"* — kèm ảnh trang mảng.
 * 🔴 Giống nhau là để ĐỌC ĐƯỢC NGAY, không phải để đẹp: kế toán và quản lý đã quen chỗ nào là
 *    trạng thái, chỗ nào là tiền, chỗ nào là lịch sử. Bố cục khác là bắt họ học lại, và lúc vội
 *    thì đọc nhầm.
 * ═══════════════════════════════════════════════════════════════════════════════ */
t( '🔴 xem đơn chia hai cột (đơn | lịch sử)', false !== strpos( $html, 'class="hai-cot"' ), '' );
t( '⚠️ và xuống một cột ở màn hẹp', false !== strpos( $html, '@media(max-width:980px){.hai-cot{' ), '' );
t( '🔴 có chip còn-sửa-được / đã-khoá', false !== strpos( $html, 'function veChipKhoa(' ), '' );
t( 'đơn đã chốt thì nói rõ vì sao không sửa được',
	false !== mb_strpos( $html_ma, 'Số đã vào sổ nên không sửa được nữa' ), '' );
t( '🔴 khối quyết toán có đủ ba ô Tạm ứng · Thực chi · Còn lại',
	false !== mb_strpos( $html_ma, 'Tạm ứng (số đã duyệt)' )
		&& false !== mb_strpos( $html_ma, 'Thực chi (tổng dòng đã mua)' )
		&& false !== mb_strpos( $html_ma, 'Còn lại (thừa/thiếu)' ), '' );
/* ⚠️ Một đơn có thể rải nhiều gian (Văn phòng / Máy tự động khi không tạm ứng) — bày một cái tên
   như thể đơn chỉ có nó là nói sai về đơn. */
t( '⚠️ đơn nhiều gian thì nói SỐ GIAN, không bày mỗi một tên',
	(bool) preg_match( '#cs\.length > 1 \? cs\.length \+ . cơ sở.#u', $html ), '' );
t( 'lịch sử bày ba dòng mới nhất, phần cũ gập lại',
	(bool) preg_match( '#ds\.slice\(0,3\)#', $html ), '' );

/* ═══ 6h. TAB QUYẾT TOÁN: HAI BẢNG, XẾP THEO TUẦN RỒI NGÀY ══════════════════════
 *
 * Anh Thắng 14/09/2026: *"Tách 2 bảng, đã quyết toán và chưa quyết toán, mỗi mảng 2 bảng như
 * vậy"* và *"Sắp xếp đơn theo tuần và đơn theo ngày"*.
 *
 * 🔴 CHƯA XONG VÀ ĐÃ XONG LÀ HAI LÁT VIỆC, không phải hai màu của một bảng: kế toán mở tab này
 *    để LÀM, nên thứ chưa làm phải nằm gọn một chỗ, đếm được, hết là hết.
 * 🔴 VÀ CON SỐ TRÊN TAB VẪN LÀ VIỆC CÒN PHẢI LÀM. Đếm cả đơn đã xong là ô tròn không bao giờ về
 *    0 — một con số không bao giờ về 0 thì người ta thôi nhìn nó, và hôm có việc thật cũng
 *    không ai để ý. Đó là cách hỏng một cái đồng hồ báo.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$gom_php2 = file_get_contents( $TONG . '/includes/class-vhcpt-gom.php' );
t( '🔴 nhóm quyết toán đọc về CẢ đơn đã quyết toán',
	(bool) preg_match( "#'tt'\s*=> array\( self::CHO_QT, self::DA_QT \)#", $gom_php2 ), '' );
t( '🔴 nhưng ĐẾM chỉ tính việc còn phải làm',
	(bool) preg_match( "#'demTt' => array\( self::CHO_QT \)#", $gom_php2 ), '' );
t( '🔴 và dem() có dùng demTt khi có',
	(bool) preg_match( '#\$dem_tt = isset\( \$n\[.demTt.\] \) \? \$n\[.demTt.\] : \$n\[.tt.\];#', $gom_php2 ), '' );
/* ⚠️ Không gom "Đã xuất MISA": những đơn ấy xong hẳn, gom vào thì bảng phình theo từng tháng
   cho tới khi không mở nổi. */
t( '⚠️ KHÔNG gom đơn đã xuất MISA vào bảng ấy',
	! preg_match( "#'tt'\s*=> array\( self::CHO_QT, self::DA_QT, [^)]*MISA#", $gom_php2 ), '' );

t( '🔴 màn chia hai lát ở tab quyết toán',
	(bool) preg_match( "#khoa:'chua'[\s\S]{0,400}?khoa:'xong'#", $html ), '' );
t( 'và chỉ chia ở tab ấy, tab khác vẫn một bảng',
	(bool) preg_match( "#NHOM === 'qt' \)#", $html ), '' );
t( '🔴 gom đơn theo TUẦN trong mỗi bảng', false !== strpos( $html, 'var theoKy = {}, thuTuKy = [];' ), '' );
/* ⚠️ Kỳ là chuỗi tiếng Việt ("T9/2026 (7/9-13/9/2026)"); sắp bằng phép so chuỗi thì T10 đứng
   trước T9. Thứ tự phải lấy từ thứ tự máy chủ trả về (đã xếp theo ngày giảm dần). */
t( '⚠️ KHÔNG tự sắp chuỗi kỳ (T10 sẽ đứng trước T9)',
	! preg_match( '#thuTuKy\.sort\(#', $html ), '' );
t( 'máy chủ xếp đơn theo ngày giảm dần', false !== strpos( $gom_php2, 'ORDER BY ngay_tao DESC' ), '' );
t( 'dải tiêu đề tuần chỉ hiện khi có từ hai tuần',
	(bool) preg_match( '#if \(thuTuKy\.length > 1\)\{#', $html ), '' );
/* ⚠️ Đơn đã quyết toán không còn việc để bấm — nói "đã xong", đừng để câu "vai của bạn không
   làm được việc này" (đúng chữ nhưng sai ý, nghe như thiếu quyền). */
t( '⚠️ đơn đã xong thì nói ĐÃ XONG, không nói thiếu quyền',
	false !== mb_strpos( $html_ma, 'đã quyết toán xong — còn chờ xuất MISA' ), '' );

/* ═══ 6i. TAB TỔNG QUAN (DASHBOARD) ═════════════════════════════════════════════
 *
 * Anh Thắng 14/09/2026: *"thêm giúp anh 1 cái tab đầu tiên (Dashboard) hiện tất cả đơn từ trước
 * đến giờ, kèm các bộ lọc"* · *"chỗ này cũng tách ra các bảng … tức các bước để kế toán theo
 * dõi, nhớ ai nhập trước, lên trước"* · *"chỗ chờ duyệt hiện các đơn nháp nhân viên đang lên
 * chờ mà chưa gửi"*.
 * ═══════════════════════════════════════════════════════════════════════════════ */
t( '🔴 tab Tổng quan đứng ĐẦU',
	(bool) preg_match( '#tabs = .<button[^;]*TAB_TQ#', $html ), '' );
t( 'và mở trang là vào thẳng nó', (bool) preg_match( "#NHOM = 'tongquan'#", $html ), '' );
t( '🔴 có cổng tongQuan ở máy chủ', false !== strpos( $api_ma, '\'tongQuan\' === $viec' ), '' );
foreach ( array( 'coso', 'loai', 'nguoi', 'tuNgay', 'denNgay' ) as $f ) {
	t( '🔴 lọc theo «' . $f . '»', (bool) preg_match( "#'" . $f . "' *=>#", $api_ma ), '' );
}
t( '🔴 lọc theo MẢNG bó ngay từ máy chủ, không lọc lúc vẽ',
	(bool) preg_match( '#[$]chon && [$]chon !== [$]khoa \) \{ continue; \}#', $api_ma ), '' );

/* 🔴 LỌC TRONG SQL, KHÔNG LỌC SAU KHI ĐÃ LẤY. Có LIMIT: lấy 300 dòng rồi mới bỏ đơn không khớp
   là lọc "cơ sở Aeon Tân Phú" ra 4 đơn trong khi cơ sở ấy có 60. */
$gom3 = file_get_contents( $TONG . '/includes/class-vhcpt-gom.php' );
t( '🔴 cơ sở lọc TRONG SQL (câu con trên bảng chi phí)',
	(bool) preg_match( '#ma_don IN \( SELECT ma_don FROM \$b_cp WHERE coso = %s \)#', $gom3 ), '' );
/* ⚠️ Đơn xin ứng trước chưa có dòng chi nào mà vẫn thuộc một gian — phải hỏi cả bảng tạm ứng. */
t( '⚠️ và hỏi CẢ bảng tạm ứng (đơn xin ứng trước chưa có dòng chi)',
	(bool) preg_match( '#ma_don IN \( SELECT ma_don FROM \$b_tu WHERE coso = %s \)#', $gom3 ), '' );
t( '🔴 loại chi phí cũng lọc trong SQL',
	(bool) preg_match( '#ma_don IN \( SELECT ma_don FROM \$b_cp WHERE nhom = %s \)#', $gom3 ), '' );
/* ⚠️ Cắt ở nửa đêm là mất sạch đơn lập trong chính ngày người ta vừa chọn. */
t( '⚠️ lọc «đến ngày» tính TỚI HẾT ngày ấy',
	false !== strpos( $gom3, "23:59:59" ), '' );
t( 'đếm TRƯỚC trên cả lát cắt, để nói được đang xem bao nhiêu trong bao nhiêu',
	false !== strpos( $gom3, '$sql_dem = "SELECT COUNT(*)' ), '' );

t( '🔴 chia bảng theo BƯỚC, đúng thứ tự quy trình',
	(bool) preg_match( "#const BUOC = array\([\s\S]{0,200}?'Nháp'[\s\S]{0,600}?'Đã xuất MISA'#u", $gom3 ), '' );

/* ═══ AI NỘP TRƯỚC, LÊN TRƯỚC ═══════════════════════════════════════════════════
 * 🔴 Luật CÔNG BẰNG, không phải sở thích sắp xếp: hàng chờ xếp mới-nhất-trước thì đơn nộp sớm
 *    bị đẩy dần xuống đáy mỗi khi có người nộp thêm — người chờ lâu nhất lại bị xử sau cùng,
 *    và không ai nhìn thấy vì màn hình luôn trông gọn.
 * ═══════════════════════════════════════════════════════════════════════════════ */
t( '🔴 bảng việc-cần-làm xếp CŨ TRƯỚC', (bool) preg_match( '#lat\.cho \?#', $html ), '' );
t( '⚠️ bảng đã xong thì mới nhất trước', (bool) preg_match( '#var xong = \( bc\.tt === .Đã quyết toán.#u', $html ), '' );

/* Tab Chờ duyệt bày kèm đơn NHÁP. */
t( '🔴 tab Chờ duyệt có bảng Nháp riêng',
	(bool) preg_match( "#khoa:'nhap'#", $html ), '' );
t( '🔴 nhóm duyet đọc về cả đơn Nháp',
	(bool) preg_match( "#'tt'\s*=> array\( self::CHO_DUYET, self::NHAP \)#", $gom3 ), '' );
t( '🔴 nhưng KHÔNG đếm Nháp vào ô tròn (chưa gửi thì chưa phải việc của ai)',
	(bool) preg_match( "#'demTt' => array\( self::CHO_DUYET \),#", $gom3 ), '' );

/* ═══ 6j. MÀU THEO BƯỚC ═════════════════════════════════════════════════════════
 * Anh Thắng 14/09/2026: *"các ô đơn hiện màu mè tí được không"*.
 *
 * 🔴 MÀU Ở ĐÂY LÀ ĐỂ ĐẾM BẰNG MẮT, không phải trang trí. Bảy khối xám như nhau thì phải đọc
 *    tiêu đề từng khối mới biết đang ở khâu nào; mỗi khâu một tông thì liếc một cái là thấy
 *    "chỗ cam đang dài" — mà chỗ cam chính là chỗ ùn.
 * ⚠️ BA TÔNG THEO NGHĨA, không phải bảy màu cầu vồng: xám = chưa tới lượt ai · cam = ĐANG CHỜ
 *    người nào đó · xanh dương = tiền đã ra khỏi két · xanh lá = xong. Bảy màu rời rạc thì
 *    người đọc phải nhớ bảng màu, tức mất đúng cái lợi vừa nói.
 * ═══════════════════════════════════════════════════════════════════════════════ */
t( '🔴 có bảng màu theo bước', false !== strpos( $html, 'function mauBuoc(' ), '' );
foreach ( array(
	'Nháp'              => '#64748b',
	'Chờ duyệt tạm ứng' => '#b45309',
	'Chờ cấp tạm ứng'   => '#b45309',
	'Chờ quyết toán'    => '#b45309',
	'Đã cấp tạm ứng'    => '#0369a1',
	'Đã quyết toán'     => '#166534',
	'Đã xuất MISA'      => '#166534',
) as $tt => $mau ) {
	t( 'bước «' . $tt . '» mang tông ' . $mau,
		(bool) preg_match( "#'" . preg_quote( $tt, '#' ) . "':\s*\{ chu:'" . preg_quote( $mau, '#' ) . "'#u", $html ), '' );
}
/* ⚠️ Trạng thái LẠ (bản mảng đổi chữ) vẫn phải vẽ ra được, không để cả khối thành "undefined". */
t( '⚠️ trạng thái lạ vẫn có tông để vẽ',
	(bool) preg_match( "#return M\[String\(tt\|\|''\)\] \|\| \{ chu:#", $html ), '' );
/* 🔴 "Ai nộp trước lên trước" chỉ có nghĩa khi NHÌN THẤY được thứ tự. */
t( '🔴 hàng chờ đánh số thứ tự, bảng đã xong thì không',
	(bool) preg_match( '#var stt = xong \? .. :#', $html ), '' );

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
