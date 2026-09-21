<?php
/**
 * Bộ giả lập WordPress tối thiểu, đủ để CHẠY THẬT bốn màn hình của plugin.
 *
 * Sandbox không có MySQL và không tải được WordPress, nên thay vì chỉ `php -l`
 * rồi đoán, ta dựng đúng những hàm WordPress mà plugin gọi tới, cắm $wpdb vào
 * SQLite, rồi dựng cả bốn trang ra HTML. Bắt được: hàm gọi sai tên, biến chưa
 * khai báo, HTML vỡ, và quan trọng nhất là con số cuối cùng có đúng không.
 *
 * KHÔNG THAY ĐƯỢC bản cài thật: SQLite không phải MySQL, dbDelta ở đây bị bỏ
 * qua và bảng được tạo tay. Lỗi riêng của MySQL sẽ lọt qua bộ này.
 */

// ------------------------------------------------------------ hằng số WP
define( 'ABSPATH', __DIR__ . '/wp/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['khtc_option']    = array();
$GLOBALS['khtc_user_meta'] = array();
$GLOBALS['khtc_hook']      = array();

// ------------------------------------------------------------- hook giả
function add_action( $t, $f, $p = 10, $a = 1 ) { $GLOBALS['khtc_hook'][ $t ][] = $f; }
function add_filter( $t, $f, $p = 10, $a = 1 ) { $GLOBALS['khtc_hook'][ $t ][] = $f; }
function do_action( $t ) {}
function apply_filters( $t, $v ) { return $v; }
function register_activation_hook( $f, $cb ) {}
function register_deactivation_hook( $f, $cb ) {}
function add_rewrite_rule( $a, $b, $c = 'bottom' ) {}
function flush_rewrite_rules( $hard = true ) {}
function add_menu_page() {}
function add_submenu_page() {}
function wp_enqueue_style() {}

// ------------------------------------------------------- tuỳ chọn, người dùng
function get_option( $k, $m = false ) { return array_key_exists( $k, $GLOBALS['khtc_option'] ) ? $GLOBALS['khtc_option'][ $k ] : $m; }
function update_option( $k, $v ) { $GLOBALS['khtc_option'][ $k ] = $v; return true; }
function get_current_user_id() { return 1; }
function is_user_logged_in() { return true; }
function current_user_can( $c ) { return true; }
function wp_get_current_user() { return (object) array( 'display_name' => 'Kế toán', 'user_login' => 'ketoan' ); }
function get_user_meta( $u, $k, $single = false ) { return $GLOBALS['khtc_user_meta'][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['khtc_user_meta'][ $k ] = $v; return true; }
function auth_redirect() {}
function wp_safe_redirect( $u ) {}
function status_header( $c ) {}
function nocache_headers() {}
function language_attributes() { echo 'lang="vi"'; }
function bloginfo( $x ) { echo 'UTF-8'; }
function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o | JSON_UNESCAPED_UNICODE ); }
function wp_logout_url( $r = '' ) { return 'https://vi.du/wp-login.php?action=logout'; }

// ---------------------------------------------------------------- nonce
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function wp_verify_nonce( $n, $a = -1 ) { return 1; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { return 1; }
function wp_nonce_url( $u, $a = -1, $n = '_wpnonce' ) { return $u . ( strpos( $u, '?' ) !== false ? '&' : '?' ) . '_wpnonce=test'; }

// ------------------------------------------------------------ escape, lọc
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }
function selected( $a, $b, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }

// ------------------------------------------------------------- ngày, URL
function current_time( $t ) { return 'mysql' === $t ? date( 'Y-m-d H:i:s' ) : date( $t ); }
function mysql2date( $f, $d, $tz = true ) { $t = strtotime( (string) $d ); return $t ? date( $f, $t ) : ''; }
function admin_url( $p = '' ) { return 'https://vi.du/wp-admin/' . ltrim( $p, '/' ); }
function home_url( $p = '' ) { return 'https://vi.du' . ( '' === $p ? '' : '/' . ltrim( $p, '/' ) ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://vi.du/wp-content/plugins/kh-tai-chinh/'; }
function add_query_arg( $args, $url ) {
	$p = parse_url( $url );
	parse_str( $p['query'] ?? '', $q );
	$q = array_merge( $q, $args );
	return ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? 'vi.du' ) . ( $p['path'] ?? '/' ) . ( $q ? '?' . http_build_query( $q ) : '' );
}
function get_query_var( $v, $d = '' ) { return $GLOBALS['khtc_qv'][ $v ] ?? $d; }

// ------------------------------------------------------------- WP_Error
class WP_Error {
	private $c;
	private $m;
	public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

// ------------------------------------------------------------- $wpdb giả
class KHTC_Wpdb_Gia {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	private $pdo;
	public $so_cau = 0;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	public function get_charset_collate() { return ''; }

	/**
	 * MySQL DATE_SUB/DATE_ADD không có trong SQLite. Dịch sang date(x, '-n day').
	 * Chỉ dùng cho bộ giả lập — bản thật chạy thẳng MySQL.
	 */
	private function dich( $sql ) {
		$sql = preg_replace( "/DATE_SUB\\(\\s*('[^']*')\\s*,\\s*INTERVAL\\s+(\\d+)\\s+DAY\\s*\\)/i", "date($1, '-$2 day')", $sql );
		$sql = preg_replace( "/DATE_ADD\\(\\s*('[^']*')\\s*,\\s*INTERVAL\\s+(\\d+)\\s+DAY\\s*\\)/i", "date($1, '+$2 day')", $sql );
		return $sql;
	}

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		$sql = str_replace( array( '%s', '%d', '%f' ), array( '%s', '%d', '%f' ), $sql );
		$i   = 0;
		return preg_replace_callback(
			'/%[sdf]/',
			function ( $m ) use ( &$i, $args ) {
				$v = $args[ $i++ ] ?? '';
				if ( '%d' === $m[0] ) { return (string) (int) $v; }
				if ( '%f' === $m[0] ) { return (string) (float) $v; }
				return "'" . str_replace( "'", "''", (string) $v ) . "'";
			},
			$sql
		);
	}

	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }

	public function query( $sql ) {
		$this->so_cau++;
		return $this->pdo->exec( $this->dich( $sql ) );
	}

	public function get_results( $sql, $kieu = null ) {
		$this->so_cau++;
		$st = $this->pdo->query( $this->dich( $sql ) );
		return ( 'ARRAY_A' === $kieu ) ? $st->fetchAll( PDO::FETCH_ASSOC ) : $st->fetchAll( PDO::FETCH_OBJ );
	}

	public function get_var( $sql ) {
		$this->so_cau++;
		$r = $this->pdo->query( $this->dich( $sql ) )->fetch( PDO::FETCH_NUM );
		return $r ? $r[0] : null;
	}

	public function get_row( $sql ) {
		$r = $this->get_results( $sql );
		return $r ? $r[0] : null;
	}

	public function get_col( $sql ) {
		$this->so_cau++;
		return $this->pdo->query( $this->dich( $sql ) )->fetchAll( PDO::FETCH_COLUMN );
	}

	public function insert( $bang, $data, $fmt = null ) {
		$cot = implode( ',', array_keys( $data ) );
		$gt  = implode( ',', array_map( array( $this, 'q' ), array_values( $data ) ) );
		// $wpdb->insert TRẢ VỀ FALSE khi cơ sở dữ liệu từ chối (ví dụ đụng
		// UNIQUE), không ném ngoại lệ. Bộ giả lập phải cư xử y hệt, nếu không
		// mã xử lý trường hợp đó không bao giờ được chạy qua khi kiểm.
		try {
			$this->query( "INSERT INTO $bang ($cot) VALUES ($gt)" );
		} catch ( PDOException $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}

	public function update( $bang, $data, $where, $f1 = null, $f2 = null ) {
		$dat = array();
		foreach ( $data as $k => $v ) { $dat[] = "$k = " . $this->q( $v ); }
		$dk = array();
		foreach ( $where as $k => $v ) { $dk[] = "$k = " . $this->q( $v ); }
		return $this->query( 'UPDATE ' . $bang . ' SET ' . implode( ',', $dat ) . ' WHERE ' . implode( ' AND ', $dk ) );
	}

	public function delete( $bang, $where, $f = null ) {
		$dk = array();
		foreach ( $where as $k => $v ) { $dk[] = "$k = " . $this->q( $v ); }
		return $this->query( "DELETE FROM $bang WHERE " . implode( ' AND ', $dk ) );
	}

	private function q( $v ) {
		if ( null === $v ) { return 'NULL'; }
		if ( is_int( $v ) ) { return (string) $v; }
		return "'" . str_replace( "'", "''", (string) $v ) . "'";
	}

	/** Bảng cho SQLite — dbDelta chỉ hiểu cú pháp MySQL nên không dùng lại được. */
	public function tao_bang_sqlite() {
		$this->query( 'CREATE TABLE wp_khtc_ngan_hang (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ten TEXT, so_tk TEXT, so_du_dau INTEGER, ngay_dau TEXT, ghi_chu TEXT, tao_luc TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_giao_dich (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngan_hang_id INTEGER, ngay TEXT, dien_giai TEXT, so_tien INTEGER, loai TEXT, ma_gd TEXT, tao_luc TEXT, tao_boi TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_doi_soat (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ten TEXT, kenh TEXT, ngan_hang_id INTEGER, tu TEXT, den TEXT, chay_luc TEXT, tao_luc TEXT, tao_boi TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_chi_phi (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngay TEXT, bo_phan TEXT, khoan_muc TEXT, nha_cung_cap TEXT, dien_giai TEXT, so_tien INTEGER, so_ct TEXT, hinh_thuc TEXT, ngan_hang_id INTEGER DEFAULT 0, giao_dich_id INTEGER DEFAULT 0, kieu_khop TEXT DEFAULT \'\', tao_luc TEXT, tao_boi TEXT)' );
		$this->query( "CREATE TABLE wp_khtc_hd_ra (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngay TEXT, so_hd TEXT, khach TEXT, mst TEXT, dia_chi_kh TEXT, email TEXT, noi_dung TEXT, so_luong TEXT, dvt TEXT, thanh_tien INTEGER, chua_vat INTEGER, thue_suat TEXT, vat INTEGER, co_vat INTEGER, khu_vuc TEXT, dich_vu TEXT, so_hop_dong TEXT, ma_diem TEXT, ma_misa TEXT, ghi_chu TEXT, dia_chi TEXT, tao_luc TEXT, tao_boi TEXT, UNIQUE (cty, so_hd))" );
		$this->query( 'CREATE TABLE wp_khtc_ds_dong (id INTEGER PRIMARY KEY AUTOINCREMENT, dot_id INTEGER, ngay TEXT, ma_gd TEXT, so_tien INTEGER, phi INTEGER, dien_giai TEXT, khop_gd_id INTEGER DEFAULT 0, kieu_khop TEXT DEFAULT \'\')' );
	}
}

$GLOBALS['wpdb'] = new KHTC_Wpdb_Gia();
$GLOBALS['wpdb']->tao_bang_sqlite();

// ------------------------------------------------------------ nạp plugin
$goc = __DIR__ . '/../wordpress/kh-tai-chinh/';
define( 'KHTC_VERSION', '0.4.0' );
define( 'KHTC_DIR', $goc );
define( 'KHTC_URL', 'https://vi.du/wp-content/plugins/kh-tai-chinh/' );
define( 'KHTC_CAP', 'edit_pages' );
foreach ( array( 'db', 'cty', 'ngan-hang', 'giao-dich', 'doi-soat', 'chi-phi', 'hoa-don-ra', 'sao-luu', 'ui', 'trang', 'web', 'admin' ) as $t ) {
	require_once $goc . 'includes/class-khtc-' . $t . '.php';
}
