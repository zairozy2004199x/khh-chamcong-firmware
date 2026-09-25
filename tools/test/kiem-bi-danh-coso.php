<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÍ DANH CƠ SỞ — GỘP TÊN CŨ VÀO ĐIỂM MỚI MÀ TIỀN VIETQR KHÔNG RƠI THÀNH "KHÔNG KHỚP"
 *
 * Anh Thắng 24/09/2026: "POSH MN CGV VINCOM LANDMARK" (tên cửa hàng bên cổng, cơ sở rỗng bên Ghế)
 * *"đang dính vào MISA, cần loại bỏ ngay, vì điểm mới đã có"* (CGV LANDMARK 81 · KH00245).
 *
 * 🔴 XOÁ SUÔNG LÀ SAI KIỂU KHÁC: Sao Kê quy tiền về cơ sở Ghế theo TÊN; tên mất → "không khớp" →
 *    tiền biến khỏi báo cáo, không sang điểm mới. Nên gộp phải GIỮ tên cũ làm bí danh của đích, và
 *    Sao Kê phải tra bí danh ra tên đích. Bài này chạy thật cả hai đầu.
 *
 * Chạy: php tools/test/kiem-bi-danh-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
class VHG_BaoCao { public static function squash( $s ) {
	/* Bỏ dấu tiếng Việt như remove_accents() thật, rồi hoa + chỉ giữ chữ/số — đúng luật ghép tên. */
	$b = array( 'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ' );
	$a = array( 'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d' );
	$s = str_replace( $b, $a, mb_strtolower( (string) $s, 'UTF-8' ) );
	return preg_replace( '/[^A-Z0-9]/', '', strtoupper( $s ) ); } }

/* ══════ 1. GHẾ ══════ */
echo "── Ghế: gộp giữ tên cũ làm bí danh; khai bí danh không trùng hai nơi ──\n";
$may = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
$fs = ''; foreach ( array( 'public static function gop_coso(', 'public static function gop_so_coso(', 'private static function gop_so_tom_tat_(', 'public static function bi_danh_tach_(', 'public static function bi_danh_gop_(', 'public static function bi_danh_luu(' ) as $mo ) { $f = boc( $may, $mo ); t( 'bốc ' . trim( str_replace( array( 'public static function ', '(' ), '', $mo ) ), '' !== $f ); $fs .= "\n" . $f; }
class WpdbGia {
	public $sql = array(); public $ten = array(); public $bd = array(); public $khac = array(); public $xoa_ok = true;
	public function prepare( $q, ...$a ) { foreach ( $a as $v ) { $q = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . $v . "'", $q, 1 ); } return $q; }
	public function get_var( $q ) { $this->sql[] = $q;
		if ( preg_match( "/SELECT ten FROM \S+ WHERE id=(\d+)/", $q, $m ) ) { return isset( $this->ten[ (int) $m[1] ] ) ? $this->ten[ (int) $m[1] ] : null; }
		if ( preg_match( "/SELECT bi_danh FROM \S+ WHERE id=(\d+)/", $q, $m ) ) { return isset( $this->bd[ (int) $m[1] ] ) ? $this->bd[ (int) $m[1] ] : ''; }
		return 0; }
	public function get_results( $q, $o = null ) { $this->sql[] = $q; return $this->khac; }
	public function query( $q ) { $this->sql[] = $q; return 3; }
	public function update( $t, $d, $w ) { $this->sql[] = 'UPDATE ' . $t . ' ' . json_encode( $d, JSON_UNESCAPED_UNICODE ) . ' WHERE ' . json_encode( $w ); return 1; }
	public function get_row( $q, $o = null ) { $this->sql[] = $q; return null; }
	public function delete( $t, $w ) { $this->sql[] = 'DELETE ' . $t; return 1; }
}
eval( 'class VHG_May { private static function quen_dem_reset_() {} private static function bao_da_luu_( $t ) {}
	public static $xoa_ok = true;
	public static function xoa_coso( $id ) { global $wpdb; $wpdb->sql[] = "XOA_COSO " . $id; return self::$xoa_ok ? array( "ok" => true ) : array( "ok" => false, "error" => "còn ghế" ); }
	' . $fs . ' }' );
global $wpdb; $wpdb = new WpdbGia();
$wpdb->ten = array( 7 => 'POSH MN CGV VINCOM LANDMARK', 3 => 'CGV LANDMARK 81' );
$wpdb->bd  = array( 7 => "TÊN CŨ HƠN NỮA", 3 => '' );
$r = VHG_May::gop_coso( 7, 3 );
t( 'gộp OK, dời ghế rồi xoá nguồn', ! empty( $r['ok'] ) && 3 === $r['doi'] && in_array( 'XOA_COSO 7', $wpdb->sql, true ), $r );
t( '🔴 tên cũ + bí danh của nó thành BÍ DANH của đích (mỗi dòng một tên)', "POSH MN CGV VINCOM LANDMARK\nTÊN CŨ HƠN NỮA" === $r['bi_danh'], $r['bi_danh'] );
$i_bd = -1; $i_xoa = -1; foreach ( $wpdb->sql as $i => $q ) { if ( 0 === strpos( $q, "SELECT bi_danh FROM wp_vhg_coso WHERE id=7" ) ) { $i_bd = $i; } if ( 'XOA_COSO 7' === $q ) { $i_xoa = $i; } }
t( '🔴 đọc bí danh của NGUỒN TRƯỚC khi xoá nó (xoá rồi thì mất)', $i_bd >= 0 && $i_xoa > $i_bd, array( $i_bd, $i_xoa ) );
t( 'ghi bí danh vào ĐÍCH (id=3)', (bool) preg_grep( '/^UPDATE wp_vhg_coso .*bi_danh.*WHERE \{"id":3\}/', $wpdb->sql ) );
$wpdb = new WpdbGia(); $wpdb->ten = array( 7 => 'A', 3 => 'B' ); VHG_May::$xoa_ok = false;
$r = VHG_May::gop_coso( 7, 3 ); VHG_May::$xoa_ok = true;
t( 'xoá nguồn hụt → KHÔNG ghi bí danh vào đích (đọc thì được, ghi thì không)', empty( $r['ok'] ) && ! preg_grep( '/^UPDATE .*bi_danh/', $wpdb->sql ) );
/* khai bí danh */
$wpdb = new WpdbGia(); $wpdb->ten = array( 3 => 'CGV LANDMARK 81' );
$wpdb->khac = array( array( 'id' => 9, 'ten' => 'Cali Thảo Điền', 'bi_danh' => "POSH MN CALI VINCOM THẢO ĐIỀN" ) );
$r = VHG_May::bi_danh_luu( 3, "POSH MN CGV VINCOM LANDMARK; cgv landmark 81 ;  POSH MN CGV VINCOM LANDMARK" );
t( 'khai bí danh: bỏ trùng, bỏ chính tên thật của cơ sở', ! empty( $r['ok'] ) && array( 'POSH MN CGV VINCOM LANDMARK' ) === $r['ds'], $r );
$r = VHG_May::bi_danh_luu( 3, "POSH MN CALI VINCOM THẢO ĐIỀN" );
t( '🔴 tên đang là bí danh của cơ sở KHÁC → chối, chỉ ra cơ sở ấy', empty( $r['ok'] ) && false !== strpos( $r['error'], 'Cali Thảo Điền' ), $r );
$r = VHG_May::bi_danh_luu( 3, "cali thao dien" );
t( '🔴 trùng TÊN THẬT của cơ sở khác (so bỏ dấu) → chối', empty( $r['ok'] ) );

/* ══════ 2. SAO KÊ ══════ */
echo "── Sao Kê: tra bí danh ra TÊN ĐÍCH ─────────────────────────────\n";
$sk = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$f = boc( $sk, 'private static function ghe_coso_chuan(' ); t( 'bốc ghe_coso_chuan', '' !== $f );
$f2 = boc( $sk, 'public static function chuan_ch_long(' ); t( 'bốc chuan_ch_long (0.46.0: ghe_coso_chuan lùi theo khoá lỏng)', '' !== $f2 );
eval( 'class SAOKE_App { public static $ds = array();
	public static function kd( $s ) { return strtolower( preg_replace( "/[^A-Za-z0-9 ]/", "", (string) $s ) ); }
	public static function chuan_ch( $s ) { return preg_replace( "/[^a-z0-9]/", "", self::kd( $s ) ); }
	private static function ghe_co() { return true; }
	private static function ghe_ds_coso() { return self::$ds; }
	private static function chiphi_ds_coso() { return array(); }
	private static function ds_coso_all() { return self::$ds; }
	/* ghe_coso_chuan() là private — mở một cửa gọi thử, chỉ tồn tại trong lớp giả của bài này. */
	public static function thu( $ten ) { return self::ghe_coso_chuan( $ten ); }
	' . $f . ' ' . $f2 . ' }' );
SAOKE_App::$ds = array(
	array( 'ten' => 'CGV LANDMARK 81', 'tinh' => '', 'maKh' => 'KH00245', 'biDanh' => "POSH MN CGV VINCOM LANDMARK\nLANDMARK CU" ),
	array( 'ten' => 'POSH MN X', 'tinh' => '', 'maKh' => '', 'biDanh' => '' ),
	array( 'ten' => 'Y', 'tinh' => '', 'maKh' => '', 'biDanh' => 'POSH MN X' ),   // bí danh trùng tên thật của cơ sở khác
);
t( '🔴 bí danh "POSH MN CGV VINCOM LANDMARK" → tên đích "CGV LANDMARK 81"', 'CGV LANDMARK 81' === SAOKE_App::thu( 'POSH MN CGV VINCOM LANDMARK' ) );
t( 'so bỏ dấu / hoa-thường / khoảng trắng', 'CGV LANDMARK 81' === SAOKE_App::thu( ' posh mn cgv vincom landmark ' ) );
t( 'tên thật vẫn ra chính nó', 'CGV LANDMARK 81' === SAOKE_App::thu( 'CGV LANDMARK 81' ) );
t( '🔴 tên thật THẮNG bí danh khi trùng ("POSH MN X" là tên thật của một cơ sở)', 'POSH MN X' === SAOKE_App::thu( 'POSH MN X' ) );
t( 'không phải cơ sở → rỗng', '' === SAOKE_App::thu( 'KHONG CO' ) );
t( 'ghe_ds_coso() dùng SELECT * (Ghế cũ chưa có cột bi_danh vẫn chạy)', false !== strpos( $sk, "SELECT * FROM ' . self::ghe_tbl( 'coso' )" ) );
t( '0.46.0: lùi theo tenChuan gom về MỘT chỗ (cong_coso_dong) và vẫn đi qua ghe_coso_chuan', 1 === substr_count( $sk, "self::ghe_coso_chuan( \$ax['tenChuan'] )" ) && 2 === substr_count( $sk, 'self::cong_coso_dong(' ) );   // 0.50.0: còn 2 chỗ gọi (vietqr_quy_dong_ + bảng cổng) — theo cơ sở suy từ theo máy
t( '0.46.0: tên ánh xạ có đuôi tỉnh "GO BẾN TRE — Bến Tre" vẫn ra cơ sở (khoá lỏng)', 'CGV LANDMARK 81' === SAOKE_App::thu( 'CGV LANDMARK 81 — TP.HCM' ) && 'CGV LANDMARK 81' === SAOKE_App::thu( 'CGV LANDMARK 81 (Q. Bình Thạnh)' ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
