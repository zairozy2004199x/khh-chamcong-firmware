<?php
/**
 * Giàn giáo chạy thử KHÔNG CẦN WordPress + MySQL: dựng lại vài hàm WordPress
 * và một $wpdb tối giản chạy trên SQLite, đủ để kiểm nghiệm logic của plugin.
 *
 * Chỉ dùng khi phát triển (tools/test), không nằm trong bản plugin phát hành.
 */

$GLOBALS['VHCP_TMP'] = sys_get_temp_dir() . '/vhcp-test-' . getmypid();
@mkdir( $GLOBALS['VHCP_TMP'] . '/wp-admin/includes', 0777, true );
@mkdir( $GLOBALS['VHCP_TMP'] . '/uploads', 0777, true );
file_put_contents( $GLOBALS['VHCP_TMP'] . '/wp-admin/includes/upgrade.php', "<?php\n" );

define( 'ABSPATH', $GLOBALS['VHCP_TMP'] . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['VHCP_OPT'] = array();
$GLOBALS['VHCP_TR']  = array();

function dbDelta( $sql ) { return array(); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['VHCP_OPT'] ) ? $GLOBALS['VHCP_OPT'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['VHCP_OPT'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['VHCP_OPT'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['VHCP_TR'] ) ? $GLOBALS['VHCP_TR'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['VHCP_TR'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['VHCP_TR'][ $k ] ); return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ); }
function wp_generate_password( $len = 12, $sp = true, $xsp = false ) {
	$c = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$o = '';
	for ( $i = 0; $i < $len; $i++ ) { $o .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; }
	return $o;
}
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['VHCP_TMP'] . '/uploads', 'baseurl' => 'http://example.test/wp-content/uploads' ); }
function wp_mkdir_p( $p ) { return is_dir( $p ) || mkdir( $p, 0777, true ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]+/u', '-', (string) $s ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^A-Za-z0-9-]+/', '-', (string) $s ) ); }
function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }
function current_user_can( $c ) { return false; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function rest_url( $p = '' ) { return 'http://example.test/wp-json/' . ltrim( $p, '/' ); }
function home_url( $p = '/' ) { return 'http://example.test' . $p; }
function add_query_arg() { return ''; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://example.test/wp-content/plugins/vhcp-chi-phi/'; }

class WP_REST_Request {
	private $p;
	private $h;
	public function __construct( $p = array(), $h = array() ) { $this->p = $p; $this->h = $h; }
	public function get_param( $k ) { return array_key_exists( $k, $this->p ) ? $this->p[ $k ] : null; }
	public function get_header( $k ) { return array_key_exists( $k, $this->h ) ? $this->h[ $k ] : ''; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $d = null, $s = 200 ) { $this->data = $d; $this->status = $s; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

/** $wpdb tối giản trên SQLite. */
class VHCP_Test_WPDB {

	public $prefix = 'wp_';
	public $last_error = '';
	public $q_count = 0;      // đếm số lệnh xuống DB — dùng để kiểm "không đọc lặp"
	private $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	public function get_charset_collate() { return ''; }

	public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }

	public function exec_raw( $sql ) { return $this->pdo->exec( $sql ); }

	private function tr( $sql ) {
		$sql = str_ireplace( 'UTC_TIMESTAMP()', "datetime('now')", $sql );
		if ( stripos( $sql, 'ON DUPLICATE KEY UPDATE' ) !== false ) {
			$sql = preg_replace( '/\s+ON DUPLICATE KEY UPDATE.*$/is', '', $sql );
			$sql = preg_replace( '/^\s*INSERT\s+INTO/i', 'INSERT OR REPLACE INTO', $sql );
		}
		return $sql;
	}

	public function prepare( $sql ) {
		$args = func_get_args();
		array_shift( $args );
		if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $args ) {
			$v = array_key_exists( $i, $args ) ? $args[ $i ] : null;
			$i++;
			if ( $m[0] === '%d' ) { return (string) (int) $v; }
			if ( $m[0] === '%f' ) { return (string) (float) $v; }
			return $this->quote( $v );
		}, $sql );
	}

	public function quote( $v ) {
		if ( $v === null ) { return 'NULL'; }
		return $this->pdo->quote( (string) $v );
	}

	public function get_results( $sql, $mode = null ) {
		$this->q_count++;
		$st = $this->pdo->query( $this->tr( $sql ) );
		return $st ? $st->fetchAll( PDO::FETCH_ASSOC ) : array();
	}

	public function get_row( $sql, $mode = null ) {
		$r = $this->get_results( $sql );   // get_results đã đếm
		return count( $r ) ? $r[0] : null;
	}

	public function get_col( $sql ) {
		$out = array();
		foreach ( $this->get_results( $sql ) as $r ) { $v = array_values( $r ); $out[] = $v[0]; }
		return $out;
	}

	public function get_var( $sql ) {
		$r = $this->get_row( $sql );
		if ( ! $r ) { return null; }
		$v = array_values( $r );
		return $v[0];
	}

	public function query( $sql ) { $this->q_count++; return $this->pdo->exec( $this->tr( $sql ) ); }

	public function insert( $table, $data ) {
		$cols = array_keys( $data );
		$vals = array();
		foreach ( $data as $v ) { $vals[] = ( $v === null ) ? 'NULL' : $this->quote( $v ); }
		$sql = 'INSERT INTO ' . $table . ' (' . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ')';
		$this->q_count++;
		return $this->pdo->exec( $sql );
	}

	public function update( $table, $data, $where ) {
		$set = array();
		foreach ( $data as $k => $v ) { $set[] = $k . '=' . ( $v === null ? 'NULL' : $this->quote( $v ) ); }
		$w = array();
		foreach ( $where as $k => $v ) { $w[] = $k . '=' . ( $v === null ? 'NULL' : $this->quote( $v ) ); }
		$this->q_count++;
		return $this->pdo->exec( 'UPDATE ' . $table . ' SET ' . implode( ',', $set ) . ' WHERE ' . implode( ' AND ', $w ) );
	}

	public function delete( $table, $where ) {
		$w = array();
		foreach ( $where as $k => $v ) { $w[] = $k . '=' . ( $v === null ? 'NULL' : $this->quote( $v ) ); }
		$this->q_count++;
		return $this->pdo->exec( 'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $w ) );
	}
}

$GLOBALS['wpdb'] = new VHCP_Test_WPDB();

/** Bảng SQLite tương ứng schema MySQL (khóa chính đổi sang stt để có AUTOINCREMENT). */
function vhcp_test_create_tables() {
	global $wpdb;
	$p = 'wp_vhcp_';
	$q = array(
		"CREATE TABLE {$p}don (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma_don TEXT UNIQUE, ky TEXT DEFAULT '', nguoi_lap TEXT DEFAULT '', ngay_tao TEXT, trang_thai TEXT DEFAULT 'Nháp', ghi_chu TEXT DEFAULT '', nguoi_duyet TEXT DEFAULT '', ngay_duyet TEXT, nguoi_qt TEXT DEFAULT '', ngay_qt TEXT, chenh_lech_qt REAL DEFAULT 0, xu_ly TEXT DEFAULT '', so_tien_thuc_mua REAL, hinh_thuc_tt TEXT DEFAULT '', hoa_don_qt TEXT DEFAULT '', ngay_xuat_cn TEXT, nguoi_qt_ncc TEXT DEFAULT '', ngay_qt_ncc TEXT, ngay_xuat_ncc TEXT, tam_ung_duyet REAL, nguoi_cap TEXT DEFAULT '', ngay_cap TEXT, ht_cap TEXT DEFAULT '', anh_cap TEXT DEFAULT '', tat_toan TEXT DEFAULT '', ngay_tat_toan TEXT, du_phong REAL, bu_tru REAL)",
		"CREATE TABLE {$p}tamung (id INTEGER PRIMARY KEY AUTOINCREMENT, ma_don TEXT, coso TEXT DEFAULT '', so REAL DEFAULT 0, UNIQUE(ma_don,coso))",
		"CREATE TABLE {$p}chiphi (stt INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT UNIQUE, ma_don TEXT, coso TEXT DEFAULT '', ngay TEXT, phan_loai_tt TEXT DEFAULT '', doi_tuong TEXT DEFAULT '', nhom TEXT DEFAULT '', noi_dung TEXT DEFAULT '', dvt TEXT DEFAULT '', so_luong REAL, don_gia REAL, thanh_tien REAL DEFAULT 0, ghi_chu TEXT DEFAULT '', anh TEXT DEFAULT '', tao_luc TEXT, thue_suat REAL, tien_thue REAL, thuc_mua REAL, cn_xu_ly INTEGER DEFAULT 1, phat_sinh INTEGER DEFAULT 0)",
		"CREATE TABLE {$p}da_index (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma_da TEXT UNIQUE, ten TEXT DEFAULT '', loai TEXT DEFAULT '', trang_thai TEXT DEFAULT 'Đang làm', ngay_tao TEXT, nguoi_tao TEXT DEFAULT '')",
		"CREATE TABLE {$p}da_line (id INTEGER PRIMARY KEY AUTOINCREMENT, ma_da TEXT, row_no INTEGER DEFAULT 5, noi_dung TEXT DEFAULT '', du_toan REAL DEFAULT 0, thuc_te REAL DEFAULT 0, so_luong REAL DEFAULT 0, don_gia REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, vat TEXT DEFAULT '', anh TEXT DEFAULT '', gian TEXT DEFAULT '', note TEXT DEFAULT '', cap_cha TEXT DEFAULT '', hinh_thuc TEXT DEFAULT '', ho_so TEXT DEFAULT '', UNIQUE(ma_da,row_no))",
		"CREATE TABLE {$p}mk_don (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma TEXT UNIQUE, coso TEXT DEFAULT '', ten TEXT DEFAULT '', ky TEXT DEFAULT '', kenh TEXT DEFAULT '', trang_thai TEXT DEFAULT 'Đang chạy', ngay_tao TEXT DEFAULT '', nguoi_tao TEXT DEFAULT '')",
		"CREATE TABLE {$p}mk_line (stt INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT UNIQUE, ma_don TEXT, kenh TEXT DEFAULT '', noi_dung TEXT DEFAULT '', du_toan REAL DEFAULT 0, thuc_te REAL DEFAULT 0, hinh_thuc TEXT DEFAULT '', vat TEXT DEFAULT '', ket_qua REAL DEFAULT 0, ngay TEXT DEFAULT '', note TEXT DEFAULT '', ho_so TEXT DEFAULT '')",
		"CREATE TABLE {$p}bp_index (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma TEXT UNIQUE, loai TEXT DEFAULT '', ten TEXT DEFAULT '', nguoi TEXT DEFAULT '', dia_diem TEXT DEFAULT '', ky TEXT DEFAULT '', trang_thai TEXT DEFAULT 'Đang xử lý', ngay_tao TEXT DEFAULT '', nguoi_tao TEXT DEFAULT '')",
		"CREATE TABLE {$p}bp_line (id INTEGER PRIMARY KEY AUTOINCREMENT, ma TEXT, row_no INTEGER DEFAULT 5, noi_dung TEXT DEFAULT '', so_luong REAL DEFAULT 0, don_gia REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, du_toan REAL DEFAULT 0, thuc_te REAL DEFAULT 0, hinh_thuc TEXT DEFAULT '', vat TEXT DEFAULT '', ngay TEXT DEFAULT '', note TEXT DEFAULT '', ho_so TEXT DEFAULT '', UNIQUE(ma,row_no))",
		"CREATE TABLE {$p}cfg (id INTEGER PRIMARY KEY AUTOINCREMENT, bang TEXT, stt INTEGER DEFAULT 0, cols TEXT)",
		"CREATE TABLE {$p}meta (k TEXT PRIMARY KEY, v TEXT)",
		"CREATE TABLE {$p}log (id INTEGER PRIMARY KEY AUTOINCREMENT, tg TEXT, nguoi TEXT DEFAULT '', vai_tro TEXT DEFAULT '', hanh_dong TEXT DEFAULT '', doi_tuong TEXT DEFAULT '', chi_tiet TEXT DEFAULT '')",
		"CREATE TABLE {$p}session (token TEXT PRIMARY KEY, ten TEXT DEFAULT '', vai_tro TEXT DEFAULT '', coso TEXT DEFAULT '', bo_phan TEXT DEFAULT '', het_han TEXT)",
	);
	foreach ( $q as $s ) { $wpdb->exec_raw( $s ); }
}

/** Nạp các lớp của plugin (không nạp file bootstrap để tránh hook WordPress). */
function vhcp_test_boot( $dir ) {
	define( 'VHCP_VERSION', 'test' );
	define( 'VHCP_DIR', $dir . '/' );
	define( 'VHCP_URL', 'http://example.test/plugin/' );
	foreach ( array( 'util', 'db', 'meta', 'cfg', 'auth', 'log', 'don', 'duan', 'mk', 'bp', 'report', 'misa', 'upload', 'import' ) as $c ) {
		require_once $dir . '/includes/class-vhcp-' . $c . '.php';
	}
	vhcp_test_create_tables();
	VHCP_Cfg::seed();
}
