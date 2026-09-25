<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.55.0 — CÙNG CƠ SỞ, CÙNG SỐ MÁY: "GLX QT 01" (cổng) ↔ ghế "GA QT-1" (Ghế) nối được dù chữ khác nhau
 *
 * Anh Thắng 23/09/2026: *"trong VietQR cũng có đánh từng máy 1,2,3,4… trên ghế cũng đánh 1,2,3,4… hai bên đối chiếu lại"*
 * và 25/09/2026 *"nó đang có 1 mã không tên"* — Báo cáo tổng Từng ghế của GALAXY QUANG TRUNG: hai ghế GA QT-1 / GA QT-2
 * VietQR "–", còn 4.950.000đ nằm ở dòng "(chưa rõ máy)": cơ sở đã đúng, số máy đã trùng, chỉ chữ "GLX" ≠ "GA".
 *
 * 🔴 KHÔNG ĐOÁN: chỉ nhận khi cơ sở đã quy được VÀ đúng MỘT ghế của cơ sở ấy mang số ấy. Hai ghế cùng số → thôi.
 * Chạy: php tools/test/kiem-saoke-cung-so-may.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$sk = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$fs = '';
foreach ( array( 'public static function chuan_may(', 'public static function bo_duoi_hieu_(', 'private static function ghe_map_so_may_(', 'private static function ghe_may_theo_so_(', 'public static function vietqr_quy_dong_(', 'private static function vietqr_gom_(', 'private static function ax_cua_may_(', 'private static function cong_coso(' ) as $mo ) {
	$f = boc( $sk, $mo ); t( 'bốc ' . preg_replace( '/.*function /', '', $mo ), '' !== $f ); $fs .= "\n" . $f;
}
eval( 'class SAOKE_App {
	public static $mapMa = array(), $coso = array();
	public static function kd( $s ) { return strtolower( preg_replace( "/[^A-Za-z0-9 ]/", "", (string) $s ) ); }
	public static function chuan_ch( $s ) { return preg_replace( "/[^a-z0-9]/", "", self::kd( $s ) ); }
	private static function ghe_map_may_ma() { return self::$mapMa; }
	private static function cong_may_dong( $nd, $ma_ch = "", $db = "", $mt = "" ) { return trim( preg_replace( "/^VQR\\S*\\s+/i", "", $nd ) ); }
	private static function ax_theo_ngay( $ds, $ng ) { return null; }
	private static function ymd2vn( $d ) { return $d; }
	private static function vqr_diem_theo_ma_( $m ) { return ""; }
	private static function cong_coso_dong( $t, $m = "", $ax = null, $tay = "" ) { $k = self::chuan_ch( self::cong_coso( $t ) ); return array( "coso" => isset( self::$coso[ $k ] ) ? self::$coso[ $k ] : "", "nguon" => "", "xungDot" => 0, "coSoKhac" => "" ); }
	public static function thu_so( $cs, $t ) { return self::ghe_may_theo_so_( $cs, $t ); }
	public static function thu( $nd ) { return self::vietqr_quy_dong_( array( "so_tien" => 20000, "d" => "2026-09-25", "noi_dung" => $nd, "diem_ban" => "", "ma_ch" => "", "may_tay" => "" ), array(), self::$mapMa ); }
	' . $fs . ' }' );
/* Ghế: GALAXY QUANG TRUNG có GA QT-1 (80814), GA QT-2 (80815); ESTELLA có EST-1 (80101) và "PHU-1" (80102) — hai ghế cùng số 1. */
SAOKE_App::$coso = array( 'glxqt' => 'GALAXY QUANG TRUNG', 'estella' => 'ESTELLA', 'gaqt' => 'GALAXY QUANG TRUNG' );
SAOKE_App::$mapMa = array(
	'gaqt1' => array( 'ma' => '80814', 'coso' => 'GALAXY QUANG TRUNG', 'trung' => false ), '80814' => array( 'ma' => '80814', 'coso' => 'GALAXY QUANG TRUNG', 'trung' => false ),
	'gaqt2' => array( 'ma' => '80815', 'coso' => 'GALAXY QUANG TRUNG', 'trung' => false ), '80815' => array( 'ma' => '80815', 'coso' => 'GALAXY QUANG TRUNG', 'trung' => false ),
	'est1'  => array( 'ma' => '80101', 'coso' => 'ESTELLA', 'trung' => false ), 'phu1' => array( 'ma' => '80102', 'coso' => 'ESTELLA', 'trung' => false ),
	'xx3'   => array( 'ma' => '80103', 'coso' => 'ESTELLA', 'trung' => true ),   // khoá trùng liên cơ sở → bỏ
);
echo "── 1. Luật số máy ─────────────────────────────────────────────\n";
t( '🔴 "GLX QT 01" tại GALAXY QUANG TRUNG → ghế 80814 (GA QT-1) — cùng số 1', '80814' === SAOKE_App::thu_so( 'GALAXY QUANG TRUNG', 'GLX QT 01' ), SAOKE_App::thu_so( 'GALAXY QUANG TRUNG', 'GLX QT 01' ) );
t( '"GLX QT 02 Posh" (đuôi hiệu) → 80815', '80815' === SAOKE_App::thu_so( 'GALAXY QUANG TRUNG', 'GLX QT 02 Posh' ) );
t( 'số 3 không có ghế → rỗng, không đoán', '' === SAOKE_App::thu_so( 'GALAXY QUANG TRUNG', 'GLX QT 03' ) );
t( '🔴 ESTELLA có HAI ghế cùng số 1 (EST-1, PHU-1) → rỗng, không đoán bừa', '' === SAOKE_App::thu_so( 'ESTELLA', 'ES 01' ) );
t( 'cơ sở khác không được mượn số của cơ sở này', '' === SAOKE_App::thu_so( 'ESTELLA', 'GLX QT 01' ) && '' === SAOKE_App::thu_so( 'CO SO LA', 'GLX QT 01' ) );
t( 'tên máy không có số cuối / mã thuần số → rỗng', '' === SAOKE_App::thu_so( 'GALAXY QUANG TRUNG', 'GLX QT' ) && '' === SAOKE_App::thu_so( 'GALAXY QUANG TRUNG', '80814' ) );
t( 'khoá ghế trùng liên cơ sở (trung=true) không tham gia lập số', '' === SAOKE_App::thu_so( 'ESTELLA', 'ES 3' ) );
echo "── 2. Đi qua luật quy dòng ────────────────────────────────────\n";
$q = SAOKE_App::thu( 'VQR1 GLX QT 01' );
t( '🔴 vietqr_quy_dong_: GLX QT 01 → cơ sở GALAXY QUANG TRUNG, máy 80814 (trước: chưa rõ máy)', 'GALAXY QUANG TRUNG' === $q['coso'] && '80814' === $q['ma'], $q );
$q = SAOKE_App::thu( 'VQR2 GA QT-2' );
t( 'khớp thẳng tên vẫn đi đường cũ (ghe_map_may_ma) → 80815', '80815' === $q['ma'] );
$q = SAOKE_App::thu( 'VQR3 GLX QT 07' );
t( 'cơ sở đúng nhưng số 7 không có ghế → ma rỗng (vào rổ chưa rõ máy), tiền không rơi', 'GALAXY QUANG TRUNG' === $q['coso'] && '' === $q['ma'] && 20000 === $q['tien'] );
t( 'luật nằm trong vietqr_quy_dong_ (một chỗ cho báo cáo + đẩy kho) — chỉ khi tên máy không ra ghế', 1 === substr_count( $sk, "if ( '' === \$m ) { \$m = self::ghe_may_theo_so_( \$cs, \$tenMay ); }" ) );
echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
