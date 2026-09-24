<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GỘP SỔ CƠ SỞ — "Nhập 2 điểm này lại thành 1" (anh Thắng 24/09/2026)
 *
 * Báo cáo tổng ra hai dòng cho một chỗ: "POSH MN CGV VINCOM LANDMARK" (1.040.000 tiền mặt theo báo
 * cáo nhân viên) và "CGV LANDMARK 81 · KH00245" (VietQR 870.000). Bí danh (2.137.0) chỉ kéo được
 * tiền VietQR; báo cáo tiền mặt vẫn nằm dưới tên cũ vì `bc` lưu tên cơ sở dạng chữ.
 *
 * 🔴 LUẬT: đổi NHÃN cơ sở trên dòng sổ — KHÔNG xoá dòng tiền, KHÔNG sửa con số. Trùng khoá:
 *    · bc (coso_key, ngay, lan): dòng cũ sang `lan` kế tiếp.
 *    · bc_khoa / bc_ma_misa (không phải tiền): bỏ dòng cũ, kể ra.
 *    · bc_thang_bs / bc_congno_dau (tiền theo tháng): TREO dưới tên cũ, kể ra — người quyết.
 *
 * Chạy: php tools/test/kiem-gop-so-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
class VHG_BaoCao { public static function squash( $s ) {
	$b = array( 'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ' );
	$a = array( 'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d' );
	$s = str_replace( $b, $a, mb_strtolower( (string) $s, 'UTF-8' ) );
	return preg_replace( '/[^A-Z0-9]/', '', strtoupper( $s ) ); } }

/* CSDL giả: bảng = mảng dòng; hiểu đúng các câu SQL mà gop_so_coso() phát ra. */
class WpdbSo {
	public $tb = array(); public $sql = array(); public $xoa = array(); public $xoa_coso_ok = true;
	public function __construct( $tb ) { $this->tb = $tb; }
	public function prepare( $q, ...$a ) { foreach ( $a as $v ) { $q = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . $v . "'", $q, 1 ); } return $q; }
	private function where_( $q ) { $w = array(); if ( preg_match( '/WHERE (.+)$/s', $q, $m ) ) { foreach ( preg_split( '/ AND /', $m[1] ) as $p ) { if ( preg_match( "/^\s*(\w+)\s*(<>|=)\s*'?([^']*)'?\s*$/u", $p, $x ) ) { $w[] = array( $x[1], $x[2], $x[3] ); } } } return $w; }
	private function khop_( $r, $w ) { foreach ( $w as $c ) { $v = isset( $r[ $c[0] ] ) ? (string) $r[ $c[0] ] : ''; if ( '=' === $c[1] ? $v !== $c[2] : $v === $c[2] ) { return false; } } return true; }
	private function bang_( $q ) { return preg_match( '/(?:FROM|UPDATE) (\w+)/', $q, $m ) ? $m[1] : ''; }
	private function loc_( $q ) { $t = $this->bang_( $q ); $w = $this->where_( $q ); $ra = array(); foreach ( (array) ( isset( $this->tb[ $t ] ) ? $this->tb[ $t ] : array() ) as $r ) { if ( $this->khop_( $r, $w ) ) { $ra[] = $r; } } return $ra; }
	public function get_results( $q, $o = null ) { $this->sql[] = $q; return $this->loc_( $q ); }
	public function get_row( $q, $o = null ) { $this->sql[] = $q; $r = $this->loc_( $q ); return $r ? $r[0] : null; }
	public function get_var( $q ) { $this->sql[] = $q;
		if ( preg_match( "/^SHOW TABLES LIKE '(\w+)'/", $q, $m ) ) { return isset( $this->tb[ $m[1] ] ) ? $m[1] : null; }
		$rows = $this->loc_( $q ); if ( ! $rows ) { return null; }
		if ( preg_match( '/^SELECT MAX\((\w+)\)/', $q, $m ) ) { $mx = 0; foreach ( $rows as $r ) { $mx = max( $mx, (int) $r[ $m[1] ] ); } return $mx; }
		if ( preg_match( '/^SELECT (\w+)/', $q, $m ) ) { return isset( $rows[0][ $m[1] ] ) ? $rows[0][ $m[1] ] : null; }
		return null; }
	private function khop_w_( $r, $w ) { foreach ( $w as $k => $v ) { if ( ! isset( $r[ $k ] ) || (string) $r[ $k ] !== (string) $v ) { return false; } } return true; }
	public function update( $t, $d, $w ) { $this->sql[] = 'UPDATE ' . $t . ' ' . json_encode( $d, JSON_UNESCAPED_UNICODE ) . ' WHERE ' . json_encode( $w, JSON_UNESCAPED_UNICODE ); $n = 0;
		foreach ( (array) ( isset( $this->tb[ $t ] ) ? $this->tb[ $t ] : array() ) as $i => $r ) { if ( $this->khop_w_( $r, $w ) ) { foreach ( $d as $k => $v ) { $this->tb[ $t ][ $i ][ $k ] = $v; } $n++; } } return $n; }
	public function delete( $t, $w ) { $this->sql[] = 'DELETE ' . $t . ' WHERE ' . json_encode( $w, JSON_UNESCAPED_UNICODE ); $this->xoa[] = $t;
		foreach ( (array) ( isset( $this->tb[ $t ] ) ? $this->tb[ $t ] : array() ) as $i => $r ) { if ( $this->khop_w_( $r, $w ) ) { unset( $this->tb[ $t ][ $i ] ); } } return 1; }
	public function query( $q ) { $this->sql[] = $q;
		if ( preg_match( "/^UPDATE (\w+) SET (.+?) WHERE (.+)$/su", $q, $m ) ) { $t = $m[1]; $set = array();
			foreach ( preg_split( "/,(?=\s*\w+=)/", $m[2] ) as $p ) { if ( preg_match( "/^\s*(\w+)=(?:'([^']*)'|(\d+))\s*$/u", $p, $x ) ) { $set[ $x[1] ] = isset( $x[3] ) && '' !== $x[3] ? (int) $x[3] : $x[2]; } }
			$w = $this->where_( $q ); $n = 0;
			foreach ( (array) ( isset( $this->tb[ $t ] ) ? $this->tb[ $t ] : array() ) as $i => $r ) { if ( $this->khop_( $r, $w ) ) { foreach ( $set as $k => $v ) { $this->tb[ $t ][ $i ][ $k ] = $v; } $n++; } } return $n; }
		return 0; }
	public function dong( $t, $id, $cot = 'id' ) { foreach ( (array) $this->tb[ $t ] as $r ) { if ( (string) $r[ $cot ] === (string) $id ) { return $r; } } return null; }
}

/* Câu GROUP BY của ten_so_mo_coi(): gom bc theo coso_key, tiền lấy từ bc_dong theo report_id. */
class WpdbMoCoi extends WpdbSo {
	public function get_results( $q, $o = null ) {
		if ( false === strpos( $q, 'GROUP BY h.coso_key' ) ) { return parent::get_results( $q, $o ); }
		$this->sql[] = $q; $g = array();
		foreach ( $this->tb['wp_vhg_bc'] as $h ) { $k = $h['coso_key'];
			if ( ! isset( $g[ $k ] ) ) { $g[ $k ] = array( 'k' => $k, 'ten' => $h['coso'], 'n' => 0, 'tu' => $h['ngay'], 'den' => $h['ngay'], 'tien' => 0 ); }
			$g[ $k ]['n']++; $g[ $k ]['tu'] = min( $g[ $k ]['tu'], $h['ngay'] ); $g[ $k ]['den'] = max( $g[ $k ]['den'], $h['ngay'] );
			foreach ( $this->tb['wp_vhg_bc_dong'] as $d ) { if ( $d['report_id'] === $h['report_id'] ) { $g[ $k ]['tien'] += (int) $d['tong']; } } }
		return array_values( $g );
	}
}
$may = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
$fs = ''; foreach ( array( 'public static function gop_coso(', 'public static function gop_so_coso(', 'private static function gop_so_tom_tat_(', 'public static function gop_so_bi_danh(', 'public static function ten_so_mo_coi(', 'public static function gop_so_ten_cu(', 'private static function dong_bo_nhan_so_(', 'public static function luu_coso(', 'public static function bi_danh_tach_(', 'public static function bi_danh_gop_(' ) as $mo ) { $f = boc( $may, $mo ); t( 'bốc ' . trim( str_replace( array( 'public static function ', 'private static function ', '(' ), '', $mo ) ), '' !== $f ); $fs .= "\n" . $f; }
eval( 'class VHG_May { private static function quen_dem_reset_() {} private static function bao_da_luu_( $t ) {}
	public static function ds_coso() { global $wpdb; return $wpdb->tb["wp_vhg_coso"]; }
	public static function xoa_coso( $id ) { global $wpdb; $wpdb->sql[] = "XOA_COSO " . $id; if ( ! $wpdb->xoa_coso_ok ) { return array( "ok" => false, "error" => "còn ghế" ); } $wpdb->delete( "wp_vhg_coso", array( "id" => (int) $id ) ); return array( "ok" => true ); }
	' . $fs . ' }' );

$CU = 'POSH MN CGV VINCOM LANDMARK'; $KCU = 'POSHMNCGVVINCOMLANDMARK'; $MOI = 'CGV LANDMARK 81'; $KMOI = 'CGVLANDMARK81';
function du_lieu_() { global $CU, $KCU, $MOI, $KMOI; return array(
	'wp_vhg_coso' => array( array( 'id' => 7, 'ten' => $CU, 'bi_danh' => '' ), array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => '' ) ),
	'wp_vhg_may' => array( array( 'ma' => '80001', 'coso_id' => 7 ) ),
	'wp_vhg_bc' => array(
		array( 'id' => 1, 'report_id' => 'r1', 'coso' => $CU,  'coso_key' => $KCU,  'ngay' => '2026-09-03', 'lan' => 1, 'tong' => 600000 ),
		array( 'id' => 2, 'report_id' => 'r2', 'coso' => $CU,  'coso_key' => $KCU,  'ngay' => '2026-09-12', 'lan' => 1, 'tong' => 440000 ),
		array( 'id' => 3, 'report_id' => 'r3', 'coso' => $MOI, 'coso_key' => $KMOI, 'ngay' => '2026-09-12', 'lan' => 1, 'tong' => 0 ),
		array( 'id' => 4, 'report_id' => 'r4', 'coso' => 'GO BẾN TRE', 'coso_key' => 'GOBENTRE', 'ngay' => '2026-09-12', 'lan' => 1, 'tong' => 5 ) ),
	'wp_vhg_bc_dong' => array( array( 'id' => 1, 'report_id' => 'r1', 'ma_may' => '80001', 'tong' => 600000 ), array( 'id' => 2, 'report_id' => 'r2', 'ma_may' => '80001', 'tong' => 440000 ), array( 'id' => 4, 'report_id' => 'r4', 'ma_may' => '80337', 'tong' => 5 ) ),
	'wp_vhg_bc_khoa' => array(
		array( 'id' => 1, 'coso' => $CU, 'coso_key' => $KCU, 'ngay' => '2026-09-03' ),
		array( 'id' => 2, 'coso' => $CU, 'coso_key' => $KCU, 'ngay' => '2026-09-12' ),
		array( 'id' => 3, 'coso' => $MOI, 'coso_key' => $KMOI, 'ngay' => '2026-09-12' ) ),
	'wp_vhg_bc_ma_misa' => array(
		array( 'coso_key' => $KCU, 'coso' => $CU, 'unit_id' => 'U-OLD', 'unit_name' => 'x' ),
		array( 'coso_key' => $KMOI, 'coso' => $MOI, 'unit_id' => 'U-NEW', 'unit_name' => 'y' ) ),
	'wp_vhg_bc_thang_bs' => array(
		array( 'id' => 1, 'coso_key' => $KCU, 'coso' => $CU, 'thang' => '2026-05', 'tong' => 1000 ),
		array( 'id' => 2, 'coso_key' => $KCU, 'coso' => $CU, 'thang' => '2026-06', 'tong' => 2000 ),
		array( 'id' => 3, 'coso_key' => $KMOI, 'coso' => $MOI, 'thang' => '2026-06', 'tong' => 3000 ) ),
	'wp_vhg_bc_congno_dau' => array( array( 'id' => 1, 'coso_key' => $KCU, 'coso' => $CU, 'thang' => '2026-08', 'so_tien' => 7 ) ),
	'wp_vhg_bc_yeucau' => array( array( 'id' => 1, 'coso' => $CU, 'coso_key' => $KCU ) ),
	'wp_vhg_bao_tri' => array(),
	'wp_vhg_bc_ma_nop' => array( array( 'id' => 1, 'coso' => $CU, 'coso_key' => $KCU ) ),
	'wp_vhg_bc_denghi' => array( array( 'id' => 1, 'coso' => $CU ) ),
	'wp_vhg_chot_tien' => array(), 'wp_vhg_phien' => array(),
	'wp_vhg_bc_pin' => array(
		array( 'pin' => '1234', 'coso' => $CU . ', GO BẾN TRE' ),
		array( 'pin' => '5678', 'coso' => $MOI . '; ' . $CU ),
		array( 'pin' => '9999', 'coso' => 'GO BẾN TRE' ) ),
); }

/* ══════ 1. gop_so_coso thẳng (ca đã gộp ở 2.137.0: tên cũ đã là bí danh) ══════ */
echo "── 1. Gộp sổ: đổi nhãn, không xoá tiền ────────────────────────────\n";
global $wpdb; $wpdb = new WpdbSo( du_lieu_() );
$r = VHG_May::gop_so_coso( $MOI, array( $CU, 'cgv landmark 81' ) );
t( 'ok; tên cũ chỉ có POSH… (tên đích gõ khác kiểu KHÔNG bị coi là tên cũ)', ! empty( $r['ok'] ) && array( $CU ) === $r['ten_cu'], $r['ten_cu'] );
$b1 = $wpdb->dong( 'wp_vhg_bc', 1 ); $b2 = $wpdb->dong( 'wp_vhg_bc', 2 ); $b3 = $wpdb->dong( 'wp_vhg_bc', 3 ); $b4 = $wpdb->dong( 'wp_vhg_bc', 4 );
t( '🔴 hai báo cáo tiền mặt của tên cũ mang tên + khoá ĐÍCH', $MOI === $b1['coso'] && $KMOI === $b1['coso_key'] && $MOI === $b2['coso'] && $KMOI === $b2['coso_key'] );
t( '🔴 số tiền KHÔNG đổi (600.000 / 440.000)', 600000 === $b1['tong'] && 440000 === $b2['tong'] );
t( '🔴 trùng (ngày 12/09, lần 1) với đích → dòng cũ sang lần 2, dòng đích giữ lần 1', 2 === $b2['lan'] && 1 === $b1['lan'] && 1 === $b3['lan'], array( $b2['lan'], $b3['lan'] ) );
t( 'cơ sở khác (GO BẾN TRE) không bị chạm', 'GO BẾN TRE' === $b4['coso'] && 'GOBENTRE' === $b4['coso_key'] );
t( '🔴 KHÔNG có DELETE nào trên bc / bc_dong (dòng tiền)', ! in_array( 'wp_vhg_bc', $wpdb->xoa, true ) && ! in_array( 'wp_vhg_bc_dong', $wpdb->xoa, true ), $wpdb->xoa );
t( 'đếm: bc=2, bc_lan=1', 2 === $r['bc'] && 1 === $r['bc_lan'] );
/* khoá ngày */
$k1 = $wpdb->dong( 'wp_vhg_bc_khoa', 1 ); $k2 = $wpdb->dong( 'wp_vhg_bc_khoa', 2 );
t( 'khoá 03/09 (đích chưa khoá) đổi sang đích; khoá 12/09 trùng → bỏ dòng cũ', $k1 && $KMOI === $k1['coso_key'] && null === $k2 && 1 === $r['khoa'] && 1 === $r['khoa_bo'] );
/* Unit MISA */
$m_cu = $wpdb->dong( 'wp_vhg_bc_ma_misa', $KCU, 'coso_key' ); $m_moi = $wpdb->dong( 'wp_vhg_bc_ma_misa', $KMOI, 'coso_key' );
t( '🔴 đích đã có Unit → Unit tên cũ bị bỏ (hết "dính vào MISA"), Unit đích giữ nguyên, kể ra tên + Unit', null === $m_cu && 'U-NEW' === $m_moi['unit_id'] && array( $CU . ' (Unit U-OLD)' ) === $r['misa_bo'], $r['misa_bo'] );
/* doanh thu bổ sung theo tháng */
$s1 = $wpdb->dong( 'wp_vhg_bc_thang_bs', 1 ); $s2 = $wpdb->dong( 'wp_vhg_bc_thang_bs', 2 ); $s3 = $wpdb->dong( 'wp_vhg_bc_thang_bs', 3 );
t( 'tháng 05 (đích chưa có) đổi sang đích', $KMOI === $s1['coso_key'] && 1 === $r['bs'] );
t( '🔴 tháng 06 trùng → TREO dưới tên cũ, KHÔNG cộng, KHÔNG xoá; kể ra', $KCU === $s2['coso_key'] && 2000 === $s2['tong'] && 3000 === $s3['tong'] && array( '2026-06' ) === $r['bs_treo'] && ! in_array( 'wp_vhg_bc_thang_bs', $wpdb->xoa, true ), $r['bs_treo'] );
t( 'dư đầu kỳ công nợ 08/2026 đổi sang đích', $KMOI === $wpdb->dong( 'wp_vhg_bc_congno_dau', 1 )['coso_key'] && 1 === $r['congno'] && array() === $r['congno_treo'] );
/* bảng nhãn */
t( 'yêu cầu / mã nộp / đề nghị đổi nhãn (khac=3)', 3 === $r['khac'] && $KMOI === $wpdb->dong( 'wp_vhg_bc_yeucau', 1 )['coso_key'] && $MOI === $wpdb->dong( 'wp_vhg_bc_denghi', 1 )['coso'], $r['khac'] );
/* phạm vi PIN */
t( '🔴 PIN 1234: tên cũ trong DANH SÁCH phụ trách thay bằng tên đích, tên khác giữ', $MOI . ', GO BẾN TRE' === $wpdb->dong( 'wp_vhg_bc_pin', '1234', 'pin' )['coso'] );
t( 'PIN 5678: đã có tên đích → không nhân đôi', $MOI === $wpdb->dong( 'wp_vhg_bc_pin', '5678', 'pin' )['coso'] );
t( 'PIN 9999 không liên quan → không đụng; đếm pin=2', 'GO BẾN TRE' === $wpdb->dong( 'wp_vhg_bc_pin', '9999', 'pin' )['coso'] && 2 === $r['pin'] );
/* tóm tắt */
t( 'tóm tắt kể "2 báo cáo tiền mặt" và "1 dòng trùng ngày"', false !== strpos( $r['tom_tat'], '2 báo cáo tiền mặt (1 dòng trùng ngày' ) , $r['tom_tat'] );
t( '🔴 tóm tắt cảnh báo "⚠ CHƯA gộp doanh thu bổ sung tháng 2026-06"', false !== strpos( $r['tom_tat'], '⚠ CHƯA gộp doanh thu bổ sung tháng 2026-06' ), $r['tom_tat'] );
t( 'tóm tắt kể Unit bị bỏ', false !== strpos( $r['tom_tat'], 'bỏ Unit MISA của tên cũ vì đích đã có: ' . $CU . ' (Unit U-OLD)' ) );

/* đích chưa có Unit → Unit tên cũ đi theo */
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->tb['wp_vhg_bc_ma_misa'] = array( array( 'coso_key' => $KCU, 'coso' => $CU, 'unit_id' => 'U-OLD', 'unit_name' => 'x' ) );
$r = VHG_May::gop_so_coso( $MOI, array( $CU ) );
$m = $wpdb->dong( 'wp_vhg_bc_ma_misa', $KMOI, 'coso_key' );
t( 'đích chưa có Unit MISA → Unit của tên cũ đổi khoá sang đích (giữ U-OLD)', $m && 'U-OLD' === $m['unit_id'] && $MOI === $m['coso'] && 1 === $r['misa'] && array() === $r['misa_bo'] );
t( 'tên đích rỗng → chối', empty( VHG_May::gop_so_coso( '', array( $CU ) )['ok'] ) );

/* ══════ 2. gop_coso gọi gộp sổ SAU khi xoá nguồn xong ══════ */
echo "── 2. ⇄ gộp cơ sở: ghế → bí danh → xoá nguồn → gộp sổ ───────────\n";
$wpdb = new WpdbSo( du_lieu_() );
$r = VHG_May::gop_coso( 7, 3 );
$i_xoa = array_search( 'XOA_COSO 7', $wpdb->sql, true ); $i_bc = -1;
foreach ( $wpdb->sql as $i => $q ) { if ( 0 === strpos( $q, 'UPDATE wp_vhg_bc {' ) ) { $i_bc = $i; break; } }
t( 'gộp ok, dời 1 ghế, có khối "so"', ! empty( $r['ok'] ) && 1 === $r['doi'] && isset( $r['so']['bc'] ) && 2 === $r['so']['bc'], $r );
t( '🔴 gộp sổ chạy SAU xoá nguồn (xoá hụt thì chưa dời dòng sổ nào)', false !== $i_xoa && $i_bc > $i_xoa, array( $i_xoa, $i_bc ) );
t( 'tên cũ thành bí danh của đích', $CU === $wpdb->dong( 'wp_vhg_coso', 3 )['bi_danh'] );
t( 'thông báo kể cả gộp sổ', false !== strpos( $r['thong_bao'], 'GỘP SỔ về "' . $MOI . '"' ) && false !== strpos( $r['thong_bao'], '2 báo cáo tiền mặt' ), $r['thong_bao'] );
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->xoa_coso_ok = false;
$r = VHG_May::gop_coso( 7, 3 );
t( '🔴 xoá nguồn hụt → KHÔNG dòng sổ nào bị đổi', empty( $r['ok'] ) && $CU === $wpdb->dong( 'wp_vhg_bc', 1 )['coso'] && ! preg_grep( '/^UPDATE wp_vhg_bc /', $wpdb->sql ) );

/* ══════ 3. 📒 gộp sổ theo bí danh (đã gộp ở 2.137.0) ══════ */
echo "── 3. 📒 gộp sổ theo bí danh ───────────────────────────────────────\n";
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->tb['wp_vhg_coso'] = array( array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => $CU . "\nLANDMARK CU" ) );
$r = VHG_May::gop_so_bi_danh( 3 );
t( 'kéo sổ của MỌI bí danh về; trả thong_bao', ! empty( $r['ok'] ) && array( $CU, 'LANDMARK CU' ) === $r['ten_cu'] && 2 === $r['bc'] && $MOI === $wpdb->dong( 'wp_vhg_bc', 2 )['coso'] && ! empty( $r['thong_bao'] ), $r );
$wpdb = new WpdbSo( du_lieu_() );
$r = VHG_May::gop_so_bi_danh( 3 );
t( 'cơ sở chưa có bí danh → chối, chỉ sang ⇄', empty( $r['ok'] ) && false !== strpos( $r['error'], '⇄' ) );
t( 'id rỗng → chối', empty( VHG_May::gop_so_bi_danh( 0 )['ok'] ) );

/* ══════ 4. Trang: cổng + nút ══════ */
echo "── 4. Trang /ghe ───────────────────────────────────────────────────\n";
$tr = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );
$gop = boc( $tr, "if ( 'coso_gop' === \$viec )" ); $gopso = boc( $tr, "if ( 'coso_gopso' === \$viec )" );
t( '🔴 coso_gop và coso_gopso đều CHẶN người không phải Quản trị', '' !== $gop && '' !== $gopso && false !== strpos( $gop, 'la_quan_tri' ) && false !== strpos( $gopso, 'la_quan_tri' ) );
t( 'coso_gopso gọi gop_so_bi_danh và ghi nhật ký', false !== strpos( $gopso, 'VHG_May::gop_so_bi_danh(' ) && false !== strpos( $gopso, 'VHG_Nhat_Ky::ghi(' ) );
t( '🔴 payload cơ sở gửi kèm dong_cua + bi_danh (trước đây thiếu dong_cua nên khối "đã đóng cửa" không bao giờ tách)', preg_match( "/'dong_cua' => \(int\) \( isset\( \\\$c\['dong_cua'\]/", $tr ) && preg_match( "/'bi_danh' => \(string\) \( isset\( \\\$c\['bi_danh'\]/", $tr ) );
t( 'hàng cơ sở có nút ⇄ (data-csgv) và 📒 (data-csgs) chỉ khi QUAN_TRI()', 1 === substr_count( $tr, "'<button data-csgv=\"'" ) && 1 === substr_count( $tr, "'<button data-csgs=\"'" ) && preg_match( "/QUAN_TRI\(\) \? \('<button data-csgv=/", $tr ) );
t( 'nút ⇄ gọi coso_gop với nguon/dich; 📒 gọi coso_gopso', false !== strpos( $tr, "lam('coso_gop', { nguon: id, dich: dich })" ) && false !== strpos( $tr, "lam('coso_gopso', { id: b.getAttribute('data-csgs') })" ) );
t( 'hộp chọn đích loại chính cơ sở đang gộp', false !== strpos( $tr, "filter(function(c){ return String(c.id) !== String(id); })" ) );
t( '🔴 không còn câu "Báo cáo đã nộp dưới tên cũ vẫn giữ nguyên" (đã sai từ 2.138.0)', false === strpos( $tr, 'Báo cáo đã nộp dưới tên cũ vẫn giữ nguyên' ) && false === strpos( $may, 'Báo cáo đã nộp dưới tên cũ vẫn giữ nguyên' ) );
t( 'hàng hiện bí danh (🔁) khi có', false !== strpos( $tr, "(c.bi_danh ? '<div class=\"mut\"" ) );


/* ══════ 5. Tên còn trong sổ, không còn trong danh mục ══════ */
echo "── 5. Sổ mồ côi (cơ sở đã xoá) ─────────────────────────────────────\n";
$wpdb = new WpdbMoCoi( du_lieu_() );
$wpdb->tb['wp_vhg_coso'] = array( array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => 'LANDMARK CU' ) );   // POSH… đã XOÁ khỏi danh mục
$wpdb->tb['wp_vhg_bc'][] = array( 'id' => 5, 'report_id' => 'r5', 'coso' => 'LANDMARK CU', 'coso_key' => 'LANDMARKCU', 'ngay' => '2026-08-01', 'lan' => 1 );
$wpdb->tb['wp_vhg_bc_ma_misa'][] = array( 'coso_key' => 'XYZ', 'coso' => 'XYZ', 'unit_id' => 'U-XYZ', 'unit_name' => '' );
$mc = VHG_May::ten_so_mo_coi(); $theo = array(); foreach ( $mc as $m ) { $theo[ $m['key'] ] = $m; }
t( '🔴 POSH MN CGV VINCOM LANDMARK (đã xoá, còn 2 báo cáo) được kể ra: 2 báo cáo, 03/09→12/09, 1.040.000, Unit U-OLD',
	isset( $theo[ $KCU ] ) && 2 === $theo[ $KCU ]['n'] && '2026-09-03' === $theo[ $KCU ]['tu'] && '2026-09-12' === $theo[ $KCU ]['den'] && 1040000 === $theo[ $KCU ]['tien'] && 'U-OLD' === $theo[ $KCU ]['unit'], $theo );
t( 'tên đang trong danh mục (CGV LANDMARK 81) và BÍ DANH của nó (LANDMARK CU) KHÔNG bị kể', ! isset( $theo[ $KMOI ] ) && ! isset( $theo['LANDMARKCU'] ) );
t( 'GO BẾN TRE không có trong danh mục → kể (1 báo cáo, 5đ)', isset( $theo['GOBENTRE'] ) && 1 === $theo['GOBENTRE']['n'] && 5 === $theo['GOBENTRE']['tien'] );
t( '🔴 dòng Unit MISA của tên đã xoá mà không còn báo cáo (XYZ) vẫn kể — chính cái "dính vào MISA"', isset( $theo['XYZ'] ) && 0 === $theo['XYZ']['n'] && 'U-XYZ' === $theo['XYZ']['unit'] );
t( 'mới nhất lên đầu, không còn báo cáo xuống cuối', 'XYZ' === $mc[ count( $mc ) - 1 ]['key'] && '2026-09-12' === $mc[0]['den'] );

/* ══════ 6. 📒 gộp sổ một tên cũ vào cơ sở đích ══════ */
echo "── 6. Gộp sổ tên cũ vào cơ sở ─────────────────────────────────────\n";
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->tb['wp_vhg_coso'] = array( array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => '' ), array( 'id' => 9, 'ten' => 'GO BẾN TRE', 'bi_danh' => 'GO BT' ) );
$r = VHG_May::gop_so_ten_cu( $CU, 3 );
t( '🔴 sổ POSH… về CGV LANDMARK 81; tên cũ thành bí danh; thông báo kể', ! empty( $r['ok'] ) && 2 === $r['bc'] && $MOI === $wpdb->dong( 'wp_vhg_bc', 1 )['coso'] && $CU === $wpdb->dong( 'wp_vhg_coso', 3 )['bi_danh'] && false !== strpos( $r['thong_bao'], 'nay là bí danh của "' . $MOI . '"' ), $r );
t( 'tên cũ chính là tên đích → chối', empty( VHG_May::gop_so_ten_cu( 'cgv landmark 81', 3 )['ok'] ) );
$r = VHG_May::gop_so_ten_cu( 'GO BT', 3 );
t( '🔴 tên cũ đang là bí danh của cơ sở KHÁC (GO BẾN TRE) → chối, chỉ sang ⇄', empty( $r['ok'] ) && false !== strpos( $r['error'], 'GO BẾN TRE' ) && false !== strpos( $r['error'], '⇄' ), $r );
t( 'thiếu đích → chối', empty( VHG_May::gop_so_ten_cu( $CU, 0 )['ok'] ) && empty( VHG_May::gop_so_ten_cu( $CU, 77 )['ok'] ) );

/* ══════ 7. ✎ đổi tên cơ sở → sổ đi theo ══════ */
echo "── 7. Đổi tên cơ sở: sổ đi theo, tên cũ thành bí danh ─────────────\n";
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->tb['wp_vhg_coso'] = array( array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => '' ), array( 'id' => 9, 'ten' => 'GO BẾN TRE', 'bi_danh' => '' ) );
$r = VHG_May::luu_coso( 3, 'CGV LANDMARK 81 NEW', 'Hồ Chí Minh', 'KH00245' );
$b3 = $wpdb->dong( 'wp_vhg_bc', 3 ); $c3 = $wpdb->dong( 'wp_vhg_coso', 3 );
t( '🔴 đổi tên → báo cáo cũ mang tên + khoá mới; tên cũ thành bí danh; thông báo kể gộp sổ',
	! empty( $r['ok'] ) && 'CGV LANDMARK 81 NEW' === $b3['coso'] && 'CGVLANDMARK81NEW' === $b3['coso_key'] && 'CGV LANDMARK 81 NEW' === $c3['ten'] && $MOI === $c3['bi_danh'] && false !== strpos( $r['thong_bao'], 'GỘP SỔ' ), array( $b3, $c3, $r ) );
t( 'cơ sở khác không bị chạm', 'GO BẾN TRE' === $wpdb->dong( 'wp_vhg_bc', 4 )['coso'] && $CU === $wpdb->dong( 'wp_vhg_bc', 1 )['coso'] );
$r = VHG_May::luu_coso( 3, 'go ben tre' );
t( '🔴 đổi sang tên của cơ sở KHÁC (so bỏ dấu) → chối, chỉ sang ⇄', empty( $r['ok'] ) && false !== strpos( $r['error'], 'GO BẾN TRE' ) && false !== strpos( $r['error'], '⇄' ) && 'CGV LANDMARK 81 NEW' === $wpdb->dong( 'wp_vhg_coso', 3 )['ten'], $r );
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->tb['wp_vhg_coso'] = array( array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => '' ) );
$r = VHG_May::luu_coso( 3, 'Cgv Landmark 81' );
$b3 = $wpdb->dong( 'wp_vhg_bc', 3 );
t( 'chỉ đổi hoa-thường (cùng khoá) → đổi NHÃN trên dòng sổ, khoá giữ, KHÔNG thêm bí danh', ! empty( $r['ok'] ) && 'Cgv Landmark 81' === $b3['coso'] && $KMOI === $b3['coso_key'] && '' === $wpdb->dong( 'wp_vhg_coso', 3 )['bi_danh'] && ! isset( $r['so']['bc'] ), array( $b3, $r ) );
$wpdb = new WpdbSo( du_lieu_() ); $wpdb->tb['wp_vhg_coso'] = array( array( 'id' => 3, 'ten' => $MOI, 'bi_danh' => '' ) );
$r = VHG_May::luu_coso( 3, $MOI, 'Hồ Chí Minh' );
t( 'lưu không đổi tên → không chạm sổ', ! empty( $r['ok'] ) && ! preg_grep( '/^UPDATE wp_vhg_bc /', $wpdb->sql ) && 'Đã lưu cơ sở.' === $r['thong_bao'] );

/* ══════ 8. Trang: khối mồ côi + cổng ══════ */
echo "── 8. Trang /ghe: khối tên cũ còn trong sổ ─────────────────────────\n";
$gst = boc( $tr, "if ( 'coso_gopso_ten' === \$viec )" );
t( '🔴 coso_gopso_ten chặn không phải Quản trị, gọi gop_so_ten_cu, ghi nhật ký', '' !== $gst && false !== strpos( $gst, 'la_quan_tri' ) && false !== strpos( $gst, 'VHG_May::gop_so_ten_cu(' ) && false !== strpos( $gst, 'VHG_Nhat_Ky::ghi(' ) );
t( 'payload gửi cosoMoCoi CHỈ cho Quản trị', false !== strpos( $tr, "'cosoMoCoi' => ! empty( \$q['quan_tri'] ) ? VHG_May::ten_so_mo_coi() : array()" ) );
t( 'khối 📒 chỉ vẽ khi QUAN_TRI() và có D.cosoMoCoi', false !== strpos( $tr, "var _mc = (QUAN_TRI() && D.cosoMoCoi) ? D.cosoMoCoi : [];" ) );
t( 'mỗi dòng có ô chọn đích (data-mcdich) + nút gộp (data-mcgop) gọi coso_gopso_ten', false !== strpos( $tr, "<select data-mcdich=" ) && false !== strpos( $tr, "<button data-mcgop=" ) && false !== strpos( $tr, "lam('coso_gopso_ten', { ten_cu: ten, dich: dich })" ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
