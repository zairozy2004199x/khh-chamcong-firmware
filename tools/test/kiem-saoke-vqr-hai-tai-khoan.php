<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.49.0 — NHIỀU TÀI KHOẢN VIETQR CHÍNH THỨC (anh Thắng 25/09/2026: "thêm tài khoản thứ 2")
 *
 * Mỗi tài khoản một cặp user/pass cổng gọi Token URL + số TK nhận. Token ghi tk=<id>, ký bằng mật khẩu
 * của chính tài khoản → callback biết giao dịch của tài khoản nào dù payload thiếu số TK. Cặp cũ
 * saoke_vqr_user/pass = tài khoản #1 khi danh sách trống; token cấp trước 0.49.0 vẫn dùng được.
 *
 * Chạy: php tools/test/kiem-saoke-vqr-hai-tai-khoan.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$OPT = array(); $LOG = array();
function get_option( $k, $d = false ) { global $OPT; return array_key_exists( $k, $OPT ) ? $OPT[ $k ] : $d; }
function wp_salt( $s ) { return 'muoi-thu'; }
class WP_REST_Response { public $data; public $status; public function __construct( $d, $s = 200 ) { $this->data = $d; $this->status = $s; } }
class ReqGia { public $h = array(); public $b = array(); public function get_header( $k ) { return isset( $this->h[ $k ] ) ? $this->h[ $k ] : ''; } public function get_json_params() { return $this->b; } public function get_body() { return json_encode( $this->b ); } public function get_params() { return $this->b; } }

$src = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$fs = ''; foreach ( array( 'private static function vqr_tk_ds(', 'private static function vqr_tk_theo_id_(', 'private static function vqr_secret(', 'private static function vqr_make_token(', 'private static function vqr_check_token(', 'private static function auth_header(', 'public static function r_vqr_token(', 'private static function vqr_tk_cong_khai_(' ) as $mo ) { $f = boc( $src, $mo ); t( 'bốc ' . trim( str_replace( array( 'private static function ', 'public static function ', '(' ), '', $mo ) ), '' !== $f ); $fs .= "\n" . $f; }
eval( 'class SAOKE_App { private static function ghi_log( $a, $b, $c ) { global $LOG; $LOG[] = $b; }
	public static function ds() { return self::vqr_tk_ds(); } public static function kt( $t ) { return self::vqr_check_token( $t ); }
	public static function tao( $exp, $tk ) { return self::vqr_make_token( $exp, $tk ); } public static function ck() { return self::vqr_tk_cong_khai_(); }
	' . $fs . ' }' );
function req_basic( $u, $p ) { $r = new ReqGia(); $r->h['authorization'] = 'Basic ' . base64_encode( $u . ':' . $p ); return $r; }

echo "── 1. Cặp cũ = tài khoản #1 ────────────────────────────────────────\n";
$OPT = array( 'saoke_vqr_user' => 'kh_cu', 'saoke_vqr_pass' => 'matkhaucu' );
$ds = SAOKE_App::ds();
t( 'danh sách trống → cặp cũ thành tài khoản tk1 (không phải khai lại)', 1 === count( $ds ) && 'tk1' === $ds[0]['id'] && 'kh_cu' === $ds[0]['user'] );
$r = SAOKE_App::r_vqr_token( req_basic( 'kh_cu', 'matkhaucu' ) );
t( 'cổng lấy token bằng cặp cũ → 200', 200 === $r->status && ! empty( $r->data['access_token'] ) );
$tk = SAOKE_App::kt( $r->data['access_token'] );
t( 'token ấy thuộc tk1', is_array( $tk ) && 'tk1' === $tk['id'] );
$tokCu = rtrim( strtr( base64_encode( 'exp=' . ( time() + 3600 ) ), '+/', '-_' ), '=' ); $tokCu .= '.' . hash_hmac( 'sha256', 'exp=' . ( time() + 3600 ), 'matkhaucu|muoi-thu' );
$tk = SAOKE_App::kt( $tokCu );
t( '🔴 token cấp TRƯỚC 0.49.0 (không có tk=) vẫn hợp lệ, gán tài khoản #1', is_array( $tk ) && 'tk1' === $tk['id'] );

echo "── 2. Hai tài khoản ────────────────────────────────────────────────\n";
$OPT = array( 'saoke_vqr_user' => 'u705', 'saoke_vqr_pass' => 'p705', 'saoke_vqr_tk' => array(
	array( 'id' => 'tkA', 'nhan' => 'K&H 705', 'user' => 'u705', 'pass' => 'p705', 'so_tk' => '0123 456 789', 'ngan_hang' => 'Vietcombank' ),
	array( 'id' => 'tkB', 'nhan' => 'K&H 989', 'user' => 'u989', 'pass' => 'p989', 'so_tk' => '9876543210', 'ngan_hang' => 'BIDV' ),
	array( 'id' => 'tkC', 'nhan' => 'rỗng', 'user' => '', 'pass' => 'x', 'so_tk' => '', 'ngan_hang' => '' ) ) );
$ds = SAOKE_App::ds();
t( 'đọc 2 tài khoản (bỏ dòng không user); số TK bỏ khoảng trắng', 2 === count( $ds ) && '0123456789' === $ds[0]['so_tk'] && 'tkB' === $ds[1]['id'], $ds );
$rB = SAOKE_App::r_vqr_token( req_basic( 'u989', 'p989' ) );
t( '🔴 cổng gọi bằng user/pass tài khoản 2 → 200, log ghi tên tài khoản', 200 === $rB->status && (bool) preg_grep( '/K&H 989/', $LOG ) );
$tkB = SAOKE_App::kt( $rB->data['access_token'] );
t( '🔴 token của tài khoản 2 → check trả đúng tkB, kèm số TK 9876543210', is_array( $tkB ) && 'tkB' === $tkB['id'] && '9876543210' === $tkB['so_tk'] && 'BIDV' === $tkB['ngan_hang'] );
$rA = SAOKE_App::r_vqr_token( req_basic( 'u705', 'p705' ) );
$tkA = SAOKE_App::kt( $rA->data['access_token'] );
t( 'token tài khoản 1 → tkA, số TK 0123456789', is_array( $tkA ) && 'tkA' === $tkA['id'] && '0123456789' === $tkA['so_tk'] );
$r = SAOKE_App::r_vqr_token( req_basic( 'u989', 'p705' ) );
t( 'user tài khoản 2 + pass tài khoản 1 → 401 (không cho lẫn)', 401 === $r->status );
/* giả mạo: lấy token A, sửa tk=tkB trong payload */
$parts = explode( '.', $rA->data['access_token'] ); $pl = base64_decode( strtr( $parts[0], '-_', '+/' ) ); $pl2 = str_replace( 'tk=tkA', 'tk=tkB', $pl );
$gia = rtrim( strtr( base64_encode( $pl2 ), '+/', '-_' ), '=' ) . '.' . $parts[1];
t( '🔴 đổi tk= trong token của A thành B → chữ ký (mật khẩu của B) không khớp → chối', false === SAOKE_App::kt( $gia ) );
t( 'token trỏ tài khoản không tồn tại → chối', false === SAOKE_App::kt( SAOKE_App::tao( time() + 100, array( 'id' => 'tkZ', 'pass' => 'zz' ) ) ) );
t( 'token hết hạn → chối', false === SAOKE_App::kt( SAOKE_App::tao( time() - 10, $ds[1] ) ) );
$ck = SAOKE_App::ck();
t( 'danh sách công khai cho màn: có nhãn/user/số TK/coPass, KHÔNG có mật khẩu', 2 === count( $ck ) && ! isset( $ck[0]['pass'] ) && true === $ck[1]['coPass'] && 'K&H 989' === $ck[1]['nhan'] );
$body = new ReqGia(); $body->b = array( 'username' => 'u989', 'password' => 'p989' );
t( 'user/pass trong body (một số cấu hình cổng) cũng nhận', 200 === SAOKE_App::r_vqr_token( $body )->status );

echo "── 3. Nối dây ─────────────────────────────────────────────────────\n";
$cb = boc( $src, 'public static function r_vqr_callback(' );
t( '🔴 callback: biết tài khoản từ token; số TK / ngân hàng lấy của tài khoản khi payload thiếu', false !== strpos( $cb, '$tkVqr = \'\' !== $bearer ? self::vqr_check_token( $bearer ) : false;' ) && false !== strpos( $cb, "(string) \$tkVqr['so_tk'] )" ) && false !== strpos( $cb, "self::cong_nhan_webhook( 'vietqr', \$req, array( 'soTK' => (string) \$tkVqr['so_tk'] ) )" ) );
t( 'cong_nhan_webhook nhận số TK mặc định, chỉ điền khi payload trống', false !== strpos( $src, 'private static function cong_nhan_webhook( $nguon, $req, $macDinh = array() )' ) && false !== strpos( $src, "&& ! empty( \$macDinh['soTK'] ) ) { \$tx['soTK'] = (string) \$macDinh['soTK']; }" ) );
$gs = boc( $src, 'public static function rpc_getSaoKeCong(' );
t( '🔴 getSaoKeCong: tham số thứ 5 = tài khoản; cộng theo tài khoản TRƯỚC khi lọc; bank cũng lọc; trả taiKhoan/locTk', false !== strpos( $gs, "isset( \$a[4] ) ? \$a[4] : ''" ) && false !== strpos( $gs, "\$theoTk[ \$stk ]['tien'] += (int) \$r['so_tien']" ) && false !== strpos( $gs, "if ( '' !== \$locTk ) { \$wb[] = 'so_tk=%s'; \$ab[] = \$locTk; }" ) && false !== strpos( $gs, "'taiKhoan' => array_values( \$theoTk ), 'locTk' => \$locTk" ) );
t( 'cấu hình trả danh sách tài khoản công khai (vqrTk)', false !== strpos( $src, "'vqrTk' => self::vqr_tk_cong_khai_()," ) );
$ad = boc( $src, "if ( isset( \$_POST['saoke_luu_vqr'] ) && check_admin_referer( 'saoke_cfg' ) )" );
t( 'WP admin: lưu nhiều dòng vqr_tk[], mật khẩu trống giữ cũ, dòng đầu chép sang cặp cũ', false !== strpos( $ad, "\$_POST['vqr_tk']" ) && false !== strpos( $ad, "\$p = isset( \$cu[ \$id ] ) ? (string) \$cu[ \$id ]['pass'] : '';" ) && false !== strpos( $ad, "update_option( 'saoke_vqr_user', \$moi[0]['user'] )" ) );
t( 'WP admin: bảng có dòng trống để thêm tài khoản mới + ô Xoá', false !== strpos( $src, "'+ tài khoản mới'" ) && false !== strpos( $src, "name=\"vqr_tk[' . \$i . '][xoa]\"" ) );
$app = file_get_contents( __DIR__ . '/../../vhcp-saoke/app.html' );
t( 'app: ô chọn tài khoản chỉ ở tab Việt QR, gửi làm tham số thứ 5, vẽ lại từ d.taiKhoan giữ lựa chọn', false !== strpos( $app, "nguon === 'vietqr'\n          ? '<div class=\"fld\"><label>🏦 Tài khoản VietQR</label>" ) && false !== strpos( $app, '.getSaoKeCong(PIN, nguon, tu, den, tk);' ) && false !== strpos( $app, 'var oTk = cgEl(nguon,\'tk\'), dsTk = d.taiKhoan || [];' ) );
t( 'guard: 36 hàm RPC qua can_pin (0.49.0 không thêm; 0.51.0 thêm taoCoSoGhe)', 36 === substr_count( $src, 'self::can_pin(' ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
