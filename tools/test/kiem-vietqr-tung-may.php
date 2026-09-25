<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VIETQR THỰC ĐỐI CHIẾU TỚI TỪNG MÁY — BA RỔ KHÔNG RƠI ĐỒNG NÀO, TỔNG THEO GHẾ = TỔNG THEO CƠ SỞ
 *
 * Anh Thắng 23/09/2026: *"trong VietQR cũng có đánh từng máy 1,2,3,4… trên ghế cũng đánh 1,2,3,4…
 * hai bên đối chiếu lại máy nào lệch không"*.
 *
 * 🔴 CHỖ DỄ SAI NHẤT LÀ KHOÁ NỐI. Cổng đánh "LM-NSG 01", Ghế khai "LM-NSG-1". chuan_ch() ra
 *    `lmnsg01` với `lmnsg1` — không khớp, và tiền của máy ấy rơi vào "chưa rõ máy" dù ai nhìn cũng
 *    thấy là một máy. Nhưng nới quá tay (bỏ mọi số 0) thì "GO 02 HCM" thành "GO 2 HCM" và đè lên
 *    máy khác. Chỉ đụng cụm số CUỐI.
 *
 * 🔴 BẤT BIẾN TIỀN: (a) tổng ba rổ vq + chuaMay + khongKhop = tổng cổng; (b) sau khi gắn vào hàng
 *    ghế, tổng cột VietQR theo ghế = tổng theo cơ sở. Vi phạm (b) là hai bảng nói khác nhau về
 *    cùng một khoản tiền — kế toán không biết tin bảng nào.
 *
 * Chạy: php tools/test/kiem-vietqr-tung-may.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
define( 'ARRAY_A', 'ARRAY_A' );

/* ══════ 1. Sao Kê: chuan_may + vietqr_theo_may_ngay ══════ */
echo "── Sao Kê: khoá nối máy ↔ ghế ────────────────────────────────\n";
$sk = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$f_cm = boc( $sk, 'public static function chuan_may(' );
$f_vq = boc( $sk, 'public static function vietqr_theo_may_ngay(' );
t( 'bốc được chuan_may + vietqr_theo_may_ngay', '' !== $f_cm && '' !== $f_vq );
if ( '' === $f_cm || '' === $f_vq ) { echo "✗ dừng\n"; exit( 1 ); }
class WpdbGia { public $rows = array(); public function prepare( $q, ...$a ) { return $q; } public function get_var( $q ) { return 'wp_saoke_cong'; } public function get_results( $q, $o = null ) { return $this->rows; } }
eval( 'class SAOKE_App {
	public static $mapMa = array(); public static $coso = array();
	public static function kd( $s ) { return strtolower( preg_replace( "/[^A-Za-z0-9 ]/", "", (string) $s ) ); }
	public static function chuan_ch( $s ) { return preg_replace( "/[^a-z0-9]/", "", self::kd( $s ) ); }
	private static function tbl_cong() { return "wp_saoke_cong"; }
	private static function ds_anhxa( $n ) { return array(); }
	private static function ax_theo_ngay( $ds, $ng ) { return null; }
	private static function ymd2vn( $d ) { return $d; }
	private static function ghe_la_coso( $t ) { return false; }
	private static function cong_coso( $t ) { return preg_replace( "/\\s*[0-9]+$/", "", $t ); }
	private static function cong_may_dong( $nd, $ma_ch = "", $db = "", $mt = "" ) { return trim( preg_replace( "/^VQR\\S*\\s+/i", "", $nd ) ); }
	private static function ghe_coso_cua_may( $t ) { $k = self::chuan_ch( self::cong_coso( $t ) ); return isset( self::$coso[ $k ] ) ? array( "coso" => self::$coso[ $k ] ) : null; }
	private static function ghe_map_may_ma() { return self::$mapMa; }
	private static function vqr_may_theo_ma( $m ) { return ""; }
	private static function ghe_coso_chuan( $t ) { $k = self::chuan_ch( $t ); return isset( self::$coso[ $k ] ) ? self::$coso[ $k ] : ""; }
	' . boc( $sk, 'private static function cong_coso_dong(' ) . '
	/* 0.50.0: vòng lặp tách thành vietqr_quy_dong_ + vietqr_gom_; lọc ngày qua khoang_thoi_diem_ (moc_tu_/moc_den_). */
	' . boc( $sk, 'public static function vietqr_quy_dong_(' ) . '
	' . boc( $sk, 'private static function vietqr_gom_(' ) . '
	' . boc( $sk, 'private static function moc_tu_(' ) . '
	' . boc( $sk, 'private static function moc_den_(' ) . '
	' . boc( $sk, 'private static function khoang_thoi_diem_(' ) . '
	' . $f_cm . "\n" . $f_vq . '
}' );
t( '🔴 "LM-NSG 01" và "LM-NSG-1" ra CÙNG khoá', SAOKE_App::chuan_may( 'LM-NSG 01' ) === SAOKE_App::chuan_may( 'LM-NSG-1' ), array( SAOKE_App::chuan_may( 'LM-NSG 01' ), SAOKE_App::chuan_may( 'LM-NSG-1' ) ) );
t( '"AMTP 12" ↔ "AMTP-12"', SAOKE_App::chuan_may( 'AMTP 12' ) === SAOKE_App::chuan_may( 'AMTP-12' ) );
t( '🔴 chỉ đụng cụm số CUỐI: "GO 02 HCM" giữ số 02 ở giữa', 'go02hcm' === SAOKE_App::chuan_may( 'GO 02 HCM' ), SAOKE_App::chuan_may( 'GO 02 HCM' ) );
t( 'mã ghế thuần số "80107" không bị cắt', '80107' === SAOKE_App::chuan_may( '80107' ), SAOKE_App::chuan_may( '80107' ) );

global $wpdb; $wpdb = new WpdbGia();
SAOKE_App::$coso = array( 'amtp' => 'AEON MALL TÂN PHÚ', 'vhm' => 'VẠN HẠNH MALL' );
SAOKE_App::$mapMa = array(
	'amtp1'  => array( 'ma' => '80013', 'coso' => 'AEON MALL TÂN PHÚ', 'trung' => false ),
	'amtp2'  => array( 'ma' => '80014', 'coso' => 'AEON MALL TÂN PHÚ', 'trung' => false ),
	'vhm9'   => array( 'ma' => '80038', 'coso' => 'VẠN HẠNH MALL',    'trung' => false ),
	'amtp7'  => array( 'ma' => '80099', 'coso' => 'VẠN HẠNH MALL',    'trung' => false ),  // số máy trỏ ghế CƠ SỞ KHÁC
	'amtp8'  => array( 'ma' => '80100', 'coso' => 'AEON MALL TÂN PHÚ', 'trung' => true ),   // số trùng hai ghế
);
$wpdb->rows = array(
	array( 'so_tien' => 100000, 'd' => '2026-09-01', 'noi_dung' => 'VQR1 AMTP 01', 'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),
	array( 'so_tien' => 50000,  'd' => '2026-09-01', 'noi_dung' => 'VQR2 AMTP 01', 'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),
	array( 'so_tien' => 70000,  'd' => '2026-09-02', 'noi_dung' => 'VQR3 AMTP 2',  'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),
	array( 'so_tien' => 30000,  'd' => '2026-09-01', 'noi_dung' => 'VQR4 AMTP',    'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),  // không số máy
	array( 'so_tien' => 20000,  'd' => '2026-09-01', 'noi_dung' => 'VQR5 AMTP 07', 'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),  // trỏ ghế cơ sở khác
	array( 'so_tien' => 10000,  'd' => '2026-09-01', 'noi_dung' => 'VQR6 AMTP 08', 'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),  // trùng
	array( 'so_tien' => 90000,  'd' => '2026-09-01', 'noi_dung' => 'VQR7 XYZ 3',   'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),  // không ra cơ sở
	array( 'so_tien' => -5000,  'd' => '2026-09-01', 'noi_dung' => 'VQR8 AMTP 01', 'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '' ),  // âm → bỏ
);
$r = SAOKE_App::vietqr_theo_may_ngay( '2026-09-01', '2026-09-02' );
t( '🔴 "AMTP 01" hai giao dịch cộng vào ghế 80013 ngày 01', 150000 === (int) $r['vq']['AEON MALL TÂN PHÚ']['80013']['2026-09-01'], $r['vq'] );
t( '"AMTP 2" (không đệm 0) vào ghế 80014 ngày 02', 70000 === (int) $r['vq']['AEON MALL TÂN PHÚ']['80014']['2026-09-02'] );
t( '🔴 tên máy KHÔNG số → rổ "chưa rõ máy" của đúng cơ sở, không rơi',
	isset( $r['chuaMay']['AEON MALL TÂN PHÚ']['2026-09-01'] ) && 60000 === (int) $r['chuaMay']['AEON MALL TÂN PHÚ']['2026-09-01'], $r['chuaMay'] );
t( '🔴 số máy trỏ ghế CƠ SỞ KHÁC và số TRÙNG hai ghế → cũng vào "chưa rõ máy", KHÔNG gán chéo',
	! isset( $r['vq']['VẠN HẠNH MALL'] ) && ! isset( $r['vq']['AEON MALL TÂN PHÚ']['80099'] ) && ! isset( $r['vq']['AEON MALL TÂN PHÚ']['80100'] ) );
t( 'không ra cơ sở → khongKhop', 90000 === (int) $r['khongKhop'], $r['khongKhop'] );
$tongVq = 0; foreach ( $r['vq'] as $cs ) { foreach ( $cs as $m ) { $tongVq += array_sum( $m ); } }
$tongChua = 0; foreach ( $r['chuaMay'] as $cs ) { $tongChua += array_sum( $cs ); }
t( '🔴 BẤT BIẾN (a): vq + chuaMay + khongKhop = tổng cổng (bỏ dòng âm)', 370000 === $tongVq + $tongChua + (int) $r['khongKhop'], array( $tongVq, $tongChua, $r['khongKhop'] ) );

/* ══════ 2. Ghế: gắn vào hàng ghế ══════ */
echo "── Ghế: gắn lớp VietQR vào từng dòng ghế ────────────────────\n";
$kt = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$f_g = boc( $kt, 'public static function bct_gan_vq_may_(' );
t( 'bốc được bct_gan_vq_may_', '' !== $f_g );
eval( 'class VHG_KeToan { ' . $f_g . ' }' );
$ngay = array( '2026-09-01', '2026-09-02' );
$hang = array(
	array( 'coso' => 'AEON MALL TÂN PHÚ', 'maKH' => 'KH00119', 'tinh' => '', 'soGhe' => 2, 'maGhe' => '80013', 'tenGhe' => 'AMTP-1', 'so' => array( 120000, 0 ), 'tong' => 120000 ),
	array( 'coso' => 'AEON MALL TÂN PHÚ', 'maKH' => 'KH00119', 'tinh' => '', 'soGhe' => 2, 'maGhe' => '80014', 'tenGhe' => 'AMTP-2', 'so' => array( 0, 70000 ), 'tong' => 70000 ),
	array( 'coso' => 'VẠN HẠNH MALL',     'maKH' => 'KH00127', 'tinh' => '', 'soGhe' => 1, 'maGhe' => '80038', 'tenGhe' => 'VHM-9',  'so' => array( 0, 0 ),     'tong' => 0 ),
);
$vqm = array( 'co' => true,
	'vq' => array( 'AEON MALL TÂN PHÚ' => array( '80013' => array( '2026-09-01' => 150000 ), '80014' => array( '2026-09-02' => 70000 ), '80999' => array( '2026-09-01' => 5000 ) ) ),
	'chuaMay' => array( 'AEON MALL TÂN PHÚ' => array( '2026-09-01' => 60000 ) ), 'khongKhop' => 90000 );
$g = VHG_KeToan::bct_gan_vq_may_( $hang, $vqm, $ngay );
$h = $g['hang'];
t( '🔴 dòng ghế 80013 mang đúng VietQR 150.000 (so với nhân viên nhập 120.000 → LỆCH thấy ngay)', array( 150000, 0 ) === $h[0]['vq'] && 150000 === $h[0]['vqTong'], $h[0] );
t( 'ghế khớp đúng (80014) thì vq = số nhập', array( 0, 70000 ) === $h[1]['vq'] );
$ten = array_map( function ( $x ) { return $x['tenGhe']; }, $h );
t( '🔴 cuối cụm AEON có thêm "(không còn trong danh mục)" cho 80999 và "(chưa rõ máy)" cho 60k, TRƯỚC khi sang VẠN HẠNH',
	array( 'AMTP-1', 'AMTP-2', '80999 (không còn trong danh mục)', '(chưa rõ máy)', 'VHM-9' ) === $ten, $ten );
t( 'dòng lẻ: số nhân viên nhập = 0, có cờ vqLe để in nghiêng', array( 0, 0 ) === $h[3]['so'] && 0 === $h[3]['tong'] && ! empty( $h[3]['vqLe'] ) && 60000 === $h[3]['vqTong'] );
t( 'ghế không có tiền cổng → vq toàn 0, không có dòng lẻ dư cho cơ sở ấy', array( 0, 0 ) === $h[4]['vq'] && 5 === count( $h ) );
t( '🔴 BẤT BIẾN (b): tổng cột VietQR theo ghế = tổng cơ sở (150k+70k+5k+60k = 285k)', 285000 === (int) $g['vqTong'] && array( 215000, 70000 ) === $g['vqTongCot'], $g );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . '/' . ( $DAT + count( $TRUOT ) ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
