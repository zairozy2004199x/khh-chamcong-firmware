<?php
/**
 * Kiểm: plugin Báo Cáo Chi Phí có HIỆN LÊN bảng trang IT của plugin Ghế không.
 *
 * 🔴 Đây là phép kiểm LIÊN PLUGIN, và nó nạp HÀM THẬT của cả hai bên — `KHBC_TuCapNhat::khai_ds()`
 *    ở đây, và `VHG_Trang::cn_ds_()` (hàm dựng bảng trang IT) cắt thẳng ra từ plugin Ghế. Viết lại
 *    bảng bằng tay thì bài kiểm xanh cho một giao kèo không ai thật sự tuân theo — mà giao kèo ấy
 *    (`vhcp_tu_cap_nhat_ds`) chính là toàn bộ chỗ hai plugin gặp nhau.
 *
 * Đường dẫn plugin Ghế truyền qua đối số 1, hoặc biến môi trường VHG_DIR. Không thấy thì phần
 * liên plugin được BỎ QUA CÓ BÁO, không lặng lẽ tính là đạt.
 */
error_reporting( E_ALL );

$pass = array(); $fail = array(); $bo_qua = array();
function ok( $t, $c, $ghi = '' ) { global $pass, $fail;
	$d = $t . ( '' !== $ghi ? ' — ' . $ghi : '' );
	if ( $c ) { $pass[] = $d; } else { $fail[] = $d; } }

/* ── Giả lập đúng phần WordPress mà hai lớp này chạm tới ────────────────────────────────────── */
$GLOBALS['__loc'] = array();      // bộ lọc
$GLOBALS['__tam'] = array();      // transient
function add_filter( $ten, $ham, $uu = 10, $so = 1 ) { $GLOBALS['__loc'][ $ten ][] = $ham; }
function apply_filters( $ten, $gt ) {
	foreach ( (array) ( isset( $GLOBALS['__loc'][ $ten ] ) ? $GLOBALS['__loc'][ $ten ] : array() ) as $h ) {
		$gt = call_user_func( $h, $gt );
	}
	return $gt;
}
function get_transient( $k ) { return isset( $GLOBALS['__tam'][ $k ] ) ? $GLOBALS['__tam'][ $k ] : false; }
function set_transient( $k, $v, $l = 0 ) { $GLOBALS['__tam'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['__tam'][ $k ] ); return true; }
function get_option( $k, $m = '' ) { return $m; }
function update_option( $k, $v ) { return true; }
function delete_option( $k ) { return true; }
function plugin_basename( $f ) { return 'khbc-bao-cao-chi-phi/' . basename( $f ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function wp_remote_get( $u, $a = array() ) { return new WP_Error( 'x', 'bài kiểm không ra mạng' ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_remote_retrieve_response_code( $r ) { return 0; }
function wp_remote_retrieve_body( $r ) { return ''; }
class WP_Error { public $m; function __construct( $c = '', $m = '' ) { $this->m = $m; }
	function get_error_message() { return $this->m; } }

define( 'ABSPATH', '/' );
$goc = dirname( dirname( __DIR__ ) );          // …/khbc-bao-cao-chi-phi
define( 'KHBC_DIR', $goc . '/' );
/* Đọc số bản từ CHÍNH tệp plugin, không gõ lại — gõ lại là bài kiểm còn xanh sau khi số đã lệch. */
preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9.]*)/m', file_get_contents( $goc . '/khbc-bao-cao-chi-phi.php' ), $mv );
define( 'KHBC_VERSION', $mv[1] );

require_once $goc . '/includes/class-khbc-tu-cap-nhat.php';
KHBC_TuCapNhat::init();

/* ── 1. Khai đúng hình dạng mà trang IT đọc ─────────────────────────────────────────────────── */
$ds = apply_filters( 'vhcp_tu_cap_nhat_ds', array() );
ok( 'Có khai một dòng vào vhcp_tu_cap_nhat_ds', 1 === count( $ds ), 'đếm được ' . count( $ds ) );
$d = $ds ? $ds[0] : array();
foreach ( array( 'ma', 'ten', 'duong', 'hien', 'lop' ) as $k ) {
	ok( "Dòng khai có khoá `$k`", isset( $d[ $k ] ) && '' !== $d[ $k ], isset( $d[ $k ] ) ? $d[ $k ] : '(thiếu)' );
}
ok( 'Số bản khai = hằng KHBC_VERSION', isset( $d['hien'] ) && $d['hien'] === KHBC_VERSION, KHBC_VERSION );
ok( '`duong` là đường plugin WordPress hiểu được',
	isset( $d['duong'] ) && 'khbc-bao-cao-chi-phi/khbc-bao-cao-chi-phi.php' === $d['duong'],
	isset( $d['duong'] ) ? $d['duong'] : '' );
ok( '`lop` tồn tại và trang IT gọi được ba hàm nó cần',
	isset( $d['lop'] ) && class_exists( $d['lop'] )
	&& method_exists( $d['lop'], 'ban_moi_nho' )
	&& method_exists( $d['lop'], 'ban_moi' )
	&& method_exists( $d['lop'], 'quen_nho' ) );

/* ── 2. ban_moi_nho() KHÔNG ĐƯỢC RA MẠNG, và phân biệt đúng ba hình dạng ô nhớ ───────────────
   wp_remote_get ở trên luôn trả lỗi, nên nếu hàm này lỡ gọi mạng thì nó sẽ không trả về được
   bản mới — chính là cách bài kiểm bắt tại trận. */
$GLOBALS['__tam'] = array();
ok( 'Ô nhớ rỗng -> null (không đi hỏi GitHub)', null === KHBC_TuCapNhat::ban_moi_nho() );
$GLOBALS['__tam']['khbc_gh_ban_moi'] = array( 'khong' => 1, 'ver_xa' => '1.0.0' );
ok( 'Ô nhớ "đã hỏi, không có bản mới" -> null', null === KHBC_TuCapNhat::ban_moi_nho() );
$GLOBALS['__tam']['khbc_gh_ban_moi'] = array( 'loi' => 'mạng hỏng' );
ok( 'Ô nhớ LỖI -> null (không khoe cột trống)', null === KHBC_TuCapNhat::ban_moi_nho() );
$GLOBALS['__tam']['khbc_gh_ban_moi'] = array( 'ver' => '9.9.9', 'zip' => 'z' );
$m = KHBC_TuCapNhat::ban_moi_nho();
ok( 'Ô nhớ CÓ bản mới -> trả về đúng mẩu đó', is_array( $m ) && '9.9.9' === $m['ver'] );

/* ── 3. LIÊN PLUGIN: chạy chính hàm dựng bảng của trang IT ────────────────────────────────────
   Dọn ô nhớ TRƯỚC: mục 2 vừa nhét '9.9.9' vào đó, để sót là mục này kiểm "cột bản mới trống"
   trên một ô nhớ đã bẩn — và cái đỏ lên lại là bài kiểm, không phải mã. */
$GLOBALS['__tam'] = array();
$vhg = isset( $argv[1] ) ? $argv[1] : getenv( 'VHG_DIR' );
$f_trang = $vhg ? rtrim( $vhg, '/' ) . '/includes/class-vhg-trang.php' : '';
if ( ! $f_trang || ! is_file( $f_trang ) ) {
	$bo_qua[] = 'Phần liên plugin: không thấy plugin Ghế (truyền đường dẫn vhcp-ghe làm đối số 1).';
} else {
	/* Cắt đúng hàm cn_ds_() ra khỏi nguồn thật thay vì chép lại. */
	$src = file_get_contents( $f_trang );
	$i = strpos( $src, 'private static function cn_ds_(' );
	if ( false === $i ) {
		$fail[] = 'Không thấy cn_ds_() trong plugin Ghế — trang IT đã đổi cách dựng bảng, đọc lại giao kèo.';
	} else {
		$j = strpos( $src, "\n\t}", $i );
		$than = substr( $src, $i, $j - $i + 3 );
		eval( 'class VHG_Trang_Gia { ' . str_replace( 'private static function cn_ds_(', 'public static function cn_ds_(', $than ) . ' }' );
		$bang = VHG_Trang_Gia::cn_ds_( false );
		$ten = array();
		foreach ( (array) $bang['ds'] as $r ) { $ten[] = $r['ten'] . ' ' . $r['hien'] . ' [' . $r['moi'] . ']'; }
		ok( 'Trang IT dựng bảng thấy Báo Cáo Chi Phí',
			1 === count( $bang['ds'] ) && 'Báo Cáo Chi Phí (K&H)' === $bang['ds'][0]['ten'],
			implode( ' · ', $ten ) );
		ok( 'Cột "bản mới" trống khi ô nhớ chưa có gì',
			'' === $bang['ds'][0]['moi'], '[' . $bang['ds'][0]['moi'] . ']' );
		$GLOBALS['__tam']['khbc_gh_ban_moi'] = array( 'ver' => '1.2.3' );
		$bang = VHG_Trang_Gia::cn_ds_( false );
		ok( 'Có bản mới trong ô nhớ thì bảng khoe đúng số',
			'1.2.3' === $bang['ds'][0]['moi'], '[' . $bang['ds'][0]['moi'] . ']' );
	}
}

echo 'PASS ' . count( $pass ) . ' / FAIL ' . count( $fail ) . ( $bo_qua ? ' / BỎ QUA ' . count( $bo_qua ) : '' ) . "\n";
foreach ( $pass as $t )   { echo "  ✓ $t\n"; }
foreach ( $bo_qua as $t ) { echo "  – $t\n"; }
foreach ( $fail as $t )   { echo "  ✗ $t\n"; }
exit( count( $fail ) ? 1 : 0 );
