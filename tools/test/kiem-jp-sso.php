<?php
/**
 * KIỂM ĐĂNG NHẬP MỘT LẦN TỪ CHẤM CÔNG SANG JP (`VHJP_Auth::sso_cham_cong`).
 *
 * =============================================================================================
 * 🔴 ĐÂY LÀ MỘT CỬA ĐĂNG NHẬP. SAI Ở ĐÂY KHÔNG PHẢI MẤT MỘT CON SỐ, MÀ LÀ MẤT CẢ HỆ.
 * =============================================================================================
 * Cửa này mở phiên JP mà KHÔNG hỏi PIN. Nó chỉ an toàn nhờ đúng bốn chốt, và bài này canh cả
 * bốn, cộng hai chốt phụ:
 *
 *   ① Mã nhân viên lấy TỪ PHIÊN chấm công, không lấy từ đâu khác.
 *   ② Mã RỖNG không khớp với ai — nếu không, người đầu tiên chưa có Mã NV đăng nhập thẳng vào
 *     tài khoản JP chưa nối đầu tiên, thường là tài khoản KẾ TOÁN hệ tự cấp lúc cài.
 *   ③ Không tìm thấy thì KHÔNG tự tạo tài khoản.
 *   ④ Vai trò lấy từ `JP_Users`, không lấy từ chấm công.
 *   ⑤ Tài khoản đã TẮT thì không vào được.
 *   ⑥ Còn PIN mặc định thì vẫn phải đổi PIN — SSO không được là đường vòng qua chốt ấy.
 *
 * Chạy: php tools/test/kiem-jp-sso.php
 */

require_once __DIR__ . '/wp-stub.php';

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $d ) { return true; } }
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) { function add_rewrite_rule() {} }
if ( ! function_exists( 'flush_rewrite_rules' ) ) { function flush_rewrite_rules( $x = true ) {} }
if ( ! function_exists( 'nocache_headers' ) ) { function nocache_headers() {} }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route() { return true; } }
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $n = 12, $a = true, $b = false ) { return str_repeat( 'x', $n ); }
}

$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

/**
 * Phiên chấm công GIẢ — đúng hình dạng `VHCC_Auth::user_by_token()` trả về.
 *
 * ⚠️ Dựng lớp giả `VHCC_Phien` là cách DUY NHẤT thử được cửa này mà không cần cả plugin chấm
 *    công. Nhưng nó phải trả ĐÚNG bốn ô thật (`name` · `role` · `coso` · `ma_nv`) — dựng sai
 *    hình dạng là bài kiểm xanh trên một thứ không tồn tại.
 */
class VHCC_Phien {
	public static $gia = null;
	public static function toi() { return self::$gia; }
}
function cc_dang_nhap( $ma_nv, $vai = 'Nhân viên' ) {
	VHCC_Phien::$gia = array( 'name' => 'Người Thử', 'role' => $vai, 'coso' => 'CS01',
		'ma_nv' => $ma_nv );
}
function cc_thoat() { VHCC_Phien::$gia = null; }

vhjp_test_boot( dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp' );

function nen() {
	vhjp_dung_bang();
	cc_thoat();
}
/** Một tài khoản JP. `$pin` rỗng = để PIN mặc định đầu tiên. */
function jp_user( $id, $ten, $vai, $ma_nv, $active = 1, $pin = '999' ) {
	VHJP_Nguon::them( 'JP_Users', array( 'id' => $id, 'username' => strtolower( $id ),
		'hoTen' => $ten, 'role' => $vai, 'maNV' => $ma_nv, 'active' => $active,
		'pin' => VHJP_Auth::bam( $pin ), 'locationIds' => 'CS01' ) );
}

/* ═══════════════════════════════════════ ① MÃ RỖNG KHÔNG KHỚP VỚI AI ═══════════════════ */
/*
 * Chốt quan trọng nhất của cả hàm. Tài khoản JP chưa khai `maNV` để RỖNG, và phiên chấm công
 * của người chưa có Mã NV cũng RỖNG. So hai chuỗi rỗng là người ấy đăng nhập thẳng vào tài
 * khoản chưa nối đầu tiên — thường là tài khoản KẾ TOÁN mà hệ tự cấp lúc cài.
 */
nen();
jp_user( 'U_KT', 'Kế Toán', VHJP_Auth::VAI_KT, '' );   // chưa nối — maNV rỗng
cc_dang_nhap( '' );                                     // người chưa có Mã NV
$r = VHJP_Auth::sso_cham_cong();
t( '🔴 Mã NV rỗng KHÔNG khớp tài khoản chưa nối', empty( $r['ok'] ), $r );
t( 'Và chối bằng đúng lý do CHUA_CO_MA_NV', 'CHUA_CO_MA_NV' === $r['ma'], $r );
t( 'Câu chối nói việc phải làm (nhờ quản lý điền Mã NV)',
	false !== strpos( $r['msg'], 'Mã nhân viên' ), $r['msg'] );

/* Kể cả khi phiên chấm công CÓ mã, tài khoản JP để rỗng vẫn không được khớp. */
nen();
jp_user( 'U_KT', 'Kế Toán', VHJP_Auth::VAI_KT, '' );
cc_dang_nhap( 'NV001' );
$r = VHJP_Auth::sso_cham_cong();
t( '🔴 Tài khoản chưa khai maNV không bị người có mã "nhận vơ"',
	empty( $r['ok'] ) && 'CHUA_NOI' === $r['ma'], $r );
t( 'theo_ma_nv() chối thẳng mã rỗng', null === VHJP_Auth::theo_ma_nv( '' ) );

/* ═════════════════════════════════════════════ ② ĐƯỜNG CHẠY ĐÚNG ═══════════════════════ */
nen();
jp_user( 'U_NV', 'Nhân Viên A', VHJP_Auth::VAI_NV, 'NV001' );
jp_user( 'U_KT', 'Kế Toán', VHJP_Auth::VAI_KT, 'NV009' );
cc_dang_nhap( 'NV001' );
$r = VHJP_Auth::sso_cham_cong();
t( 'Đã nối thì vào được, không hỏi PIN', ! empty( $r['ok'] ), $r );
t( 'Nhận đúng người', 'Nhân Viên A' === $r['user']['hoTen'], $r['user'] );
t( 'Thẻ phiên là chuỗi ngẫu nhiên 64 ký tự hex',
	preg_match( '/^[0-9a-f]{64}$/', $r['token'] ), $r['token'] );
$k = VHJP_Auth::kiem( $r['token'] );
t( 'Thẻ ấy dùng được thật', ! empty( $k['ok'] ) && 'U_NV' === $k['user']['id'], $k );
t( 'Không phải đổi PIN (PIN đã khác mặc định)', empty( $k['user']['phaiDoiPin'] ), $k['user'] );

/* ═══════════════════════════════ ③ VAI LẤY TỪ JP, KHÔNG TỪ CHẤM CÔNG ═══════════════════ */
/*
 * Một Quản lý bên chấm công KHÔNG vì thế mà thành kế toán JP — sổ 632 và giá vốn là chuyện
 * khác hẳn bảng công. Hai hệ hai thang vai; sợi dây nối chỉ nói "ai", không nói "được làm gì".
 */
nen();
jp_user( 'U_NV', 'Nhân Viên A', VHJP_Auth::VAI_NV, 'NV001' );
cc_dang_nhap( 'NV001', 'Admin' );            // vai CAO NHẤT bên chấm công
$r = VHJP_Auth::sso_cham_cong();
t( '🔴 Admin bên chấm công vẫn chỉ là NHÂN VIÊN bên JP',
	VHJP_Auth::VAI_NV === $r['user']['role'], $r['user'] );
t( 'Và KHÔNG mở được cửa kế toán',
	! VHJP_Auth::la_kt( VHJP_Auth::kiem( $r['token'] )['user'] ) );
$g = VHJP_Cong::goi( 'jpKhoTonKho', array( $r['token'], 'TONG' ) );
t( 'Gọi thẳng hàm kho qua cổng cũng bị chối 403', 403 === $g['ma'], $g );

/* ═══════════════════════════════════════ ④ KHÔNG TỰ TẠO TÀI KHOẢN ══════════════════════ */
nen();
jp_user( 'U_KT', 'Kế Toán', VHJP_Auth::VAI_KT, 'NV009' );
cc_dang_nhap( 'NV777' );                      // mã có thật bên chấm công, chưa nối bên JP
$truoc = count( VHJP_Nguon::doc( 'JP_Users' ) );
$r = VHJP_Auth::sso_cham_cong();
t( 'Chưa nối thì chối', empty( $r['ok'] ) && 'CHUA_NOI' === $r['ma'], $r );
t( '🔴 Và KHÔNG tự tạo tài khoản nào (tạo tài khoản là phát quyền)',
	$truoc === count( VHJP_Nguon::doc( 'JP_Users' ) ), count( VHJP_Nguon::doc( 'JP_Users' ) ) );
t( 'Câu chối chỉ đúng chỗ khai', false !== strpos( $r['msg'], 'Nối tài khoản' ), $r['msg'] );

/* ═══════════════════════════════════════════ ⑤ TÀI KHOẢN ĐÃ TẮT ════════════════════════ */
/*
 * Người đã nghỉ vẫn còn phiên chấm công tới lúc nó hết hạn. Tài khoản JP đã tắt mà vẫn vào
 * được là tắt hụt — và tắt hụt thì không ai phát hiện, vì trên màn hình nó vẫn hiện "đã tắt".
 */
nen();
jp_user( 'U_NV', 'Nhân Viên A', VHJP_Auth::VAI_NV, 'NV001', 0 );
cc_dang_nhap( 'NV001' );
$r = VHJP_Auth::sso_cham_cong();
t( '🔴 Tài khoản đã TẮT thì không vào được', empty( $r['ok'] ), $r );
t( 'theo_ma_nv() cũng không trả về người đã tắt', null === VHJP_Auth::theo_ma_nv( 'NV001' ) );

/* ═══════════════════════════════════ ⑥ PIN MẶC ĐỊNH VẪN CHẶN ═══════════════════════════ */
/*
 * `mo_phien()` suy cờ `phaiDoiPin` từ PIN VỪA GÕ, mà đường SSO không gõ gì cả. Không soi PIN
 * đang lưu thì SSO thành đường vòng qua đúng cái chốt "chưa đổi PIN thì chưa dùng được".
 */
nen();
$pin_md = VHJP_Auth::pin_mac_dinh();
jp_user( 'U_NV', 'Nhân Viên A', VHJP_Auth::VAI_NV, 'NV001', 1, $pin_md[0] );
cc_dang_nhap( 'NV001' );
$r = VHJP_Auth::sso_cham_cong();
t( 'Vẫn mở được phiên', ! empty( $r['ok'] ), $r );
t( '🔴 Nhưng bị gắn cờ PHẢI ĐỔI PIN', ! empty( $r['user']['phaiDoiPin'] ), $r['user'] );
$k = VHJP_Auth::kiem( $r['token'] );
t( '🔴 Và mọi cửa thường đều bị chặn tới khi đổi PIN',
	empty( $k['ok'] ) && 'PHAI_DOI_PIN' === $k['ma'], $k );
$g = VHJP_Cong::goi( 'jpKhoTonKho', array( $r['token'], 'TONG' ) );
t( 'Qua cổng cũng chặn, và báo đúng mã PHAI_DOI_PIN',
	401 === $g['ma'] && 'PHAI_DOI_PIN' === $g['than']['error'], $g );
$g = VHJP_Cong::goi( 'jpDoiPin', array( $r['token'], $pin_md[0], '357' ) );
t( 'Nhưng đường ĐỔI PIN vẫn sống (không thì cả hệ kẹt)', 200 === $g['ma'], $g );

/* ══════════════════════════════════ ⑦ KHÔNG CÓ PHIÊN CHẤM CÔNG ═════════════════════════ */
nen();
jp_user( 'U_NV', 'Nhân Viên A', VHJP_Auth::VAI_NV, 'NV001' );
cc_thoat();
$r = VHJP_Auth::sso_cham_cong();
t( 'Chưa đăng nhập chấm công thì chối', empty( $r['ok'] ) && 'CHUA_DANG_NHAP' === $r['ma'], $r );

/* ═══════════════════════════════════════════════════ ⑧ QUA CỔNG ═══════════════════════ */
/*
 * `jpSsoChamCong` là hàm thứ hai gọi được KHÔNG cần thẻ JP. Cửa của nó là cookie chấm công,
 * nên nó KHÔNG được nhận tham số nào — không nhận gì thì không có gì để giả.
 */
nen();
jp_user( 'U_NV', 'Nhân Viên A', VHJP_Auth::VAI_NV, 'NV001' );
cc_dang_nhap( 'NV001' );
$g = VHJP_Cong::goi( 'jpSsoChamCong', array() );
t( 'Cổng gọi được jpSsoChamCong mà không cần thẻ JP',
	200 === $g['ma'] && ! empty( $g['than']['ok'] ), $g );

/* 🔴 Giả mã nhân viên qua tham số PHẢI vô tác dụng. */
cc_dang_nhap( 'NV001' );
$g = VHJP_Cong::goi( 'jpSsoChamCong', array( 'NV009', 'U_KT' ) );
t( '🔴 Nhét tham số vào cũng không đổi được người — mã lấy TỪ PHIÊN',
	200 === $g['ma'] && 'U_NV' === $g['than']['user']['id'], $g['than'] );

cc_thoat();
$g = VHJP_Cong::goi( 'jpSsoChamCong', array( 'NV001' ) );
t( '🔴 Không có phiên chấm công thì nhét mã vào cũng chối',
	empty( $g['than']['ok'] ), $g['than'] );

t( 'jpSsoChamCong nằm trong danh sách gọi công khai',
	in_array( 'jpSsoChamCong', VHJP_Cong::cong_khai(), true ), VHJP_Cong::cong_khai() );
t( 'Và đúng HAI tên, không hơn',
	array( 'jpLoginPin', 'jpSsoChamCong' ) === VHJP_Cong::cong_khai(), VHJP_Cong::cong_khai() );

/* ════════════════════════════════════════ ⑨ KHÔNG CÀI CHẤM CÔNG ═══════════════════════ */
/*
 * Không thể gỡ lớp giả đã khai, nên thử nhánh này bằng cách soi chính phép gác: hàm phải hỏi
 * `class_exists` + `method_exists` chứ không gọi thẳng — JP là plugin cài độc lập.
 */
$than = file_get_contents( dirname( __DIR__, 2 )
	. '/wordpress/vhcp-jp/includes/class-vhjp-auth.php' );
t( '🔴 Gác method_exists TRƯỚC khi gọi sang plugin chấm công',
	false !== strpos( $than, "method_exists( 'VHCC_Phien', 'toi' )" ), '' );
t( 'Và chỉ đọc mã NV từ `VHCC_Phien::toi()`, không từ $_GET/$_POST/$_REQUEST',
	false === strpos( $than, '$_GET' ) && false === strpos( $than, '$_REQUEST' ), '' );

/* ------------------------------------------------------------------ in kết quả */
echo "\n";
if ( $TRUOT ) {
	echo 'ĐỎ — ' . count( $TRUOT ) . " phép trượt:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "\nĐạt {$DAT} · trượt " . count( $TRUOT ) . "\n";
	exit( 1 );
}
echo "XANH — {$DAT} phép đều đạt.\n";
exit( 0 );
