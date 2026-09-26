<?php
/**
 * NHÃN CƠ SỞ PHẢI ĐƯỢC LƯU NGAY LÚC NẠP, VÀ DÒ LẠI ĐƯỢC CHO DÒNG CŨ.
 *
 * ==============================================================================================
 * Anh Thắng 26/09/2026 — "ngày cũ đâu": Sao Kê tự dò cơ sở theo mã nộp tiền trong nội dung
 * (`coSoMa`), nhưng CHỈ tính lúc MỞ MÀN (`rpc_getGiaoDich()`), KHÔNG BAO GIỜ lưu vào cột `nhan`.
 * Cột `nhan` trước bản này CHỈ được ghi bởi `rpc_setNhan()` — tức phải có người bấm tay từng dòng.
 * Bên khh-doanh-thu lại đọc THẲNG cột `nhan` (không đọc `coSoMa`) để biết ngày nào đã "khai mã nộp
 * tiền" — nên giao dịch cũ (trước khi ai bấm, hoặc trước khi mã KVC được khai) coi như vĩnh viễn
 * "chưa khai mã nộp tiền" dù nội dung đã khớp đúng mã từ lâu. Cùng gốc với *"Sao lúc có lúc
 * không, lưu tk là tự dò luôn chứ"* (đã ghi trong kiem-giaodich-ma-ranh-gioi.php) — badge "tự"
 * không lưu, nên phụ thuộc hoàn toàn vào việc có ai từng bấm hay chưa.
 *
 * Vá bằng HAI đường, dùng CHUNG một quy tắc dò (`coso_ma_re()`, một chỗ — không để ba nơi tự chép
 * ba bản có thể lệch nhau, xem chú thích trong vhcp-saoke.php):
 *   1. `luu_gd()` — nạp giao dịch MỚI thì dò và LƯU LUÔN `nhan`, không đợi ai bấm.
 *   2. `rpc_tuGanNhanCoSo()` — nút "Tự dò nhãn cơ sở" ở app.html, quét lại MỌI dòng CŨ đang
 *      `nhan=''` một lần, để cứu dữ liệu tháng 8-9 đã nằm sẵn trong bảng từ trước bản vá này.
 *
 * Bài này KHÔNG dùng bệ đỡ chung `lib/be-saoke.php` cho phần $wpdb: bệ đỡ đó chỉ hiểu đúng các
 * câu SQL của bảng `saoke_cong` (cổng thanh toán), không phải bảng `saoke_gd` (sao kê ngân hàng)
 * mà `luu_gd()`/`gd_all()`/`rpc_tuGanNhanCoSo()` dùng — dùng nhầm là bệ đỡ âm thầm trả rỗng cho
 * mọi câu, bài xanh giả vì không hề chạy qua đường thật. Vẫn NẠP các hàm/hằng WP giả từ đó (để
 * lớp thật load được), chỉ THAY $wpdb bằng một bản hiểu đúng hình dạng câu của bảng saoke_gd.
 *
 * Chạy: php tools/test/kiem-nhan-coso-tu-luu.php   (chay-het.sh tự gom)
 */
require_once __DIR__ . '/lib/be-saoke.php';   // hàm/hằng WP giả (get_option, ARRAY_A, WP_Error…) + nạp được lớp thật bên dưới

$GLOBALS['DAT'] = 0; $GLOBALS['TRUOT'] = array();
function k2( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}

echo "── Quy tắc dò dùng CHUNG một chỗ ──\n";
$s = (string) file_get_contents( dirname( __DIR__, 2 ) . '/vhcp-saoke/vhcp-saoke.php' );
k2( '🔴 luu_gd() gọi doan_coso_tu_noi_dung() để lưu nhan ngay lúc nạp, không để nhan trống chờ người bấm',
	1 === preg_match( '/function luu_gd\(.*?\n\t\}/s', $s, $mf ) && false !== strpos( $mf[0], "'nhan'     => self::doan_coso_tu_noi_dung(" ) );
k2( 'rpc_getGiaoDich(), rpc_tuGanNhanCoSo() và doan_coso_tu_noi_dung() dùng CHUNG coso_ma_re() (không tự chép quy tắc dò)',
	3 === preg_match_all( '/self::coso_ma_re\(\)/', $s ) );
k2( 'có RPC dò lại hàng loạt cho dòng cũ',
	false !== strpos( $s, 'function rpc_tuGanNhanCoSo(' ) );
k2( '🔴 dò lại hàng loạt CHỈ đụng dòng nhan rỗng, không ghi đè nhãn đã có (kể cả nhãn người bấm tay)',
	1 === preg_match( '/function rpc_tuGanNhanCoSo\(.*?\n\t\}/s', $s, $mr ) && false !== strpos( $mr[0], "WHERE nhan=''" ) );
k2( 'nút "Tự dò nhãn cơ sở" có ở app.html và gọi đúng RPC ấy',
	false !== strpos( (string) file_get_contents( dirname( __DIR__, 2 ) . '/vhcp-saoke/app.html' ), 'tuGanNhanCoSo(PIN)' ) );
/* ⚠️ Quên khai vào r_rpc() là màn hình báo "Hàm không hợp lệ" — chỉ lộ ra lúc bấm, không có gì đỏ
   lúc dựng (đúng bẫy đã ghi trong kiem-saoke-coso-kvc.php). */
k2( '🔴 tuGanNhanCoSo có trong danh sách hàm RPC cho phép (r_rpc), không thì bấm nút ra "Hàm không hợp lệ"',
	(bool) preg_match( "/'tuGanNhanCoSo',/", $s ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHẠY THẬT — nạp lớp thật, tự dựng cấu hình mã nộp + bảng saoke_gd giả, gọi luu_gd()/gd_all()/
 * rpc_tuGanNhanCoSo() thật để bắt ca "viết quy tắc đúng nhưng quên gọi", "quên lưu", hay "ghi đè
 * nhầm nhãn người đã xác nhận tay" — những lỗi soát chữ ở trên không bắt được.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
class VHCP_DonVi {
	const MAC_DINH = 'K&H';
	public static function chuan( $x ) { $x = trim( (string) $x ); return '' === $x ? self::MAC_DINH : $x; }
	public static function bang( $a, $b ) { return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) ); }
	public static function khoi_cua( $don_vi ) { return ''; }
}
class VHCP_Cfg {
	public static $coso = array();
	public static function cfg_static() { return array( 'coso' => self::$coso ); }
}
require_once dirname( __DIR__, 2 ) . '/vhcp-saoke/vhcp-saoke.php';

/* $wpdb RIÊNG cho bảng saoke_gd — bệ đỡ chung (lib/be-saoke.php) không hiểu hình dạng câu của
   bảng này, xem lời giải thích ở đầu tệp. Chỉ hiểu ĐÚNG những câu luu_gd()/gd_all()/
   rpc_tuGanNhanCoSo() thật sự hỏi — hiểu quá rộng là bệ đỡ tự trả lời thay luật đang thử. */
class FakeWpdbGd {
	public $prefix = 'wp_';
	public $hang = array();
	public function get_charset_collate() { return ''; }
	public function prepare( $sql, ...$a ) {
		if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
		foreach ( $a as $v ) {
			$is = strpos( $sql, '%s' ); $id = strpos( $sql, '%d' );
			if ( false === $is && false === $id ) { break; }
			$dungS = ( false !== $is ) && ( false === $id || $is < $id );
			$vi = $dungS ? $is : $id;
			$the = $dungS ? ( "'" . str_replace( "'", "''", (string) $v ) . "'" ) : (string) (int) $v;
			$sql = substr( $sql, 0, $vi ) . $the . substr( $sql, $vi + 2 );
		}
		return $sql;
	}
	public function get_var( $sql ) {
		if ( preg_match( "/SELECT id FROM \\S+ WHERE sepay_id='([^']*)'/", $sql, $m ) ) {
			foreach ( $this->hang as $h ) { if ( (string) $h['sepay_id'] === $m[1] ) { return $h['id']; } }
			return null;
		}
		return null;
	}
	public function get_results( $sql, $out = null ) {
		if ( false !== strpos( $sql, "WHERE nhan=''" ) ) {
			$ra = array();
			foreach ( $this->hang as $h ) { if ( '' === (string) $h['nhan'] ) { $ra[] = array( 'sepay_id' => $h['sepay_id'], 'noi_dung' => $h['noi_dung'] ); } }
			return $ra;
		}
		if ( false !== strpos( $sql, 'ORDER BY ngay_gd ASC, id ASC' ) ) {
			$ra = $this->hang;
			usort( $ra, function ( $x, $y ) { $c = strcmp( (string) $x['ngay_gd'], (string) $y['ngay_gd'] ); return $c ?: ( (int) $x['id'] - (int) $y['id'] ); } );
			return $ra;
		}
		return array();
	}
	public function insert( $tbl, $data ) {
		$data += array( 'id' => count( $this->hang ) + 1, 'nhan' => '', 'luy_ke' => null, 'ma_gd' => '' );
		$this->hang[] = $data; return 1;
	}
	public function update( $tbl, $data, $where ) {
		$n = 0;
		foreach ( $this->hang as $i => $h ) {
			$khop = true;
			foreach ( $where as $k => $v ) { if ( (string) ( isset( $h[ $k ] ) ? $h[ $k ] : '' ) !== (string) $v ) { $khop = false; break; } }
			if ( $khop ) { $this->hang[ $i ] = array_merge( $h, $data ); $n++; }
		}
		return $n;
	}
	public function esc_like( $s ) { return $s; }
}
$GLOBALS['wpdb'] = new FakeWpdbGd();

function goi2( $ten, $args = array() ) {
	$m = new ReflectionMethod( 'SAOKE_App', $ten );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

/* Hai cơ sở KVC thật, mã một cái là TIỀN TỐ của cái kia (đúng ca "TÀU TÂN AN" của anh Thắng lẫn
   lỗi ranh giới đã vá bên kiem-giaodich-ma-ranh-gioi.php) — bài này thử ĐÚNG khâu lưu, bài kia
   thử ranh giới; ở đây chỉ cần hai mã KHÁC HẲN nhau để không lẫn hai chủ đề trong một bài. */
VHCP_Cfg::$coso = array(
	array( 'ten' => 'TÀU TÂN AN', 'donVi' => 'KVC', 'boPhan' => '', 'tinh' => 'Long An', 'dongCua' => '' ),
	array( 'ten' => 'AEON TÂN PHÚ', 'donVi' => 'KVC', 'boPhan' => '', 'tinh' => 'TP HCM', 'dongCua' => '' ),
);
$k = new ReflectionMethod( 'SAOKE_App', 'chuan_ch' ); $k->setAccessible( true );
$GLOBALS['OPT']['saoke_coso_ma_kvc'] = array(
	$k->invoke( null, 'TÀU TÂN AN' )   => 'KH705KVCMN0005',
	$k->invoke( null, 'AEON TÂN PHÚ' ) => 'KH705KVCMN0009',
);

echo "\n── luu_gd(): dò VÀ LƯU ngay lúc nạp giao dịch mới ──\n";
$ok1 = goi2( 'luu_gd', array( array(
	'sepay_id' => 'sepay-1', 'ngay_gd' => '2026-08-19 10:00:00', 'so_tk' => '0011', 'ngan_hang' => 'MB',
	'loai' => 'in', 'tien' => 500000, 'luy_ke' => null,
	'noi_dung' => 'NHAN TU 1 TRACE 1 ND KH705KVCMN0005-190826-10:00:00 6231ASCB',
	'ma_gd' => '', 'nguon' => 'sepay',
) ) );
k2( 'luu_gd() báo đã ghi dòng mới', true === $ok1 );
$rows = goi2( 'gd_all' );
k2( 'có đúng 1 dòng sau khi nạp (đang có ' . count( $rows ) . ')', 1 === count( $rows ) );
k2( '🔴 nhan được LƯU NGAY = đúng cơ sở khớp mã, không phải rỗng chờ người bấm',
	isset( $rows[0]['nhan'] ) && 'TÀU TÂN AN' === $rows[0]['nhan'], isset( $rows[0]['nhan'] ) ? $rows[0]['nhan'] : '(không có)' );

echo "\n── luu_gd(): nội dung không mang mã nào thì nhan vẫn rỗng, không đoán bừa ──\n";
goi2( 'luu_gd', array( array(
	'sepay_id' => 'sepay-2', 'ngay_gd' => '2026-08-20 09:00:00', 'so_tk' => '0011', 'ngan_hang' => 'MB',
	'loai' => 'out', 'tien' => 200000, 'luy_ke' => null,
	'noi_dung' => 'CHUYEN TIEN LUONG NHAN VIEN THANG 8', 'ma_gd' => '', 'nguon' => 'sepay',
) ) );
$r2 = null; foreach ( goi2( 'gd_all' ) as $r ) { if ( 'sepay-2' === $r['sepayId'] ) { $r2 = $r; } }
k2( 'giao dịch không khớp mã nào -> nhan rỗng, không gán bừa', null !== $r2 && '' === $r2['nhan'] );

echo "\n── rpc_tuGanNhanCoSo(): dò lại cho dòng CŨ (mô phỏng dữ liệu tháng 8-9 nạp TRƯỚC bản vá) ──\n";
$GLOBALS['OPT']['saoke_pin'] = '135790';
// Chèn thẳng vào bảng giả — mô phỏng dòng nạp từ TRƯỚC khi luu_gd() biết dò+lưu (nhan rỗng dù nội dung đã khớp mã từ lâu).
$wpdb = $GLOBALS['wpdb'];
$wpdb->hang[] = array( 'id' => 90, 'sepay_id' => 'sepay-cu-1', 'ngay_gd' => '2026-08-05 08:00:00', 'so_tk' => '0011', 'ngan_hang' => 'MB',
	'loai' => 'in', 'tien' => 900000, 'luy_ke' => null, 'noi_dung' => 'ND KH705KVCMN0005-050826-08:00:00', 'ma_gd' => '', 'nhan' => '', 'nguon' => 'sepay', 'tao_luc' => '2026-08-05 08:00:00' );
$wpdb->hang[] = array( 'id' => 91, 'sepay_id' => 'sepay-cu-2', 'ngay_gd' => '2026-09-01 08:00:00', 'so_tk' => '0011', 'ngan_hang' => 'MB',
	'loai' => 'in', 'tien' => 700000, 'luy_ke' => null, 'noi_dung' => 'ND KH705KVCMN0009-010926-08:00:00', 'ma_gd' => '', 'nhan' => '', 'nguon' => 'sepay', 'tao_luc' => '2026-09-01 08:00:00' );
// Dòng đã có người bấm tay xác nhận một nhãn CHỦ Ý SAI — kịch bản gác cửa: không được đụng dòng đã có nhãn dù nội dung khớp mã khác.
$wpdb->hang[] = array( 'id' => 92, 'sepay_id' => 'sepay-da-xac-nhan', 'ngay_gd' => '2026-09-02 08:00:00', 'so_tk' => '0011', 'ngan_hang' => 'MB',
	'loai' => 'in', 'tien' => 300000, 'luy_ke' => null, 'noi_dung' => 'ND KH705KVCMN0005-020926-08:00:00', 'ma_gd' => '', 'nhan' => 'AEON TÂN PHÚ', 'nguon' => 'sepay', 'tao_luc' => '2026-09-02 08:00:00' );

$kq = goi2( 'rpc_tuGanNhanCoSo', array( array( '135790' ) ) );
k2( 'rpc trả ok', isset( $kq['ok'] ) && true === $kq['ok'] );
k2( 'gán được đúng 2 dòng cũ (đang báo ' . ( isset( $kq['so'] ) ? $kq['so'] : '?' ) . ')', isset( $kq['so'] ) && 2 === $kq['so'] );
$byId = array(); foreach ( $wpdb->hang as $h ) { $byId[ $h['sepay_id'] ] = $h; }
k2( '🔴 giao dịch tháng 8 cũ (Tân An) giờ đã có nhãn đúng',
	'TÀU TÂN AN' === $byId['sepay-cu-1']['nhan'], $byId['sepay-cu-1']['nhan'] );
k2( '🔴 giao dịch tháng 9 cũ (Aeon) cũng được gán đúng — không chỉ cứu MỘT ngày',
	'AEON TÂN PHÚ' === $byId['sepay-cu-2']['nhan'], $byId['sepay-cu-2']['nhan'] );
k2( '🔴 dòng ĐÃ có người xác nhận tay thì KHÔNG bị đụng, dù nội dung khớp mã khác',
	'AEON TÂN PHÚ' === $byId['sepay-da-xac-nhan']['nhan'], $byId['sepay-da-xac-nhan']['nhan'] );

echo "\n── Cửa PIN vẫn gác RPC mới, không phải lỗ hổng mới mở ──\n";
try {
	goi2( 'rpc_tuGanNhanCoSo', array( array( 'sai-pin' ) ) );
	k2( 'PIN sai bị chặn (Exception)', false );
} catch ( Exception $e ) {
	k2( 'PIN sai bị chặn (Exception: ' . $e->getMessage() . ')', 'Sai mã PIN' === $e->getMessage() );
}

echo "\n" . ( $GLOBALS['TRUOT'] ? ( 'ĐỎ ' . count( $GLOBALS['TRUOT'] ) . '/' . ( $GLOBALS['DAT'] + count( $GLOBALS['TRUOT'] ) ) . " phép:\n" . implode( "\n", array_map( function ( $t ) { return '  ✗ ' . $t; }, $GLOBALS['TRUOT'] ) ) . "\n" ) : ( "✓ SẠCH — " . $GLOBALS['DAT'] . " phép: nhãn cơ sở lưu ngay lúc nạp, dò lại được cho dòng cũ, không đụng nhãn đã xác nhận.\n" ) );
exit( $GLOBALS['TRUOT'] ? 1 : 0 );
