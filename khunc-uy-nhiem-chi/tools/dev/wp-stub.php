<?php
/**
 * GIẢ LẬP WORDPRESS TỐI THIỂU để chạy plugin bằng `php -S` khi phát triển / kiểm thử tự động
 * (không có WordPress thật). Dữ liệu trong SQLite (tools/dev/data/dev.sqlite).
 *
 *   php -S 127.0.0.1:8088 khunc-uy-nhiem-chi/tools/dev/router.php
 *   → http://127.0.0.1:8088/uy-nhiem-chi/   (PIN 1111)
 *
 * Chỉ dựng đúng những hàm plugin dùng. KHÔNG phải WordPress, không dùng cho môi trường thật.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPINC', 'wp-includes' );
define( 'DEV_BASE', 'http://' . ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '127.0.0.1:8088' ) );
define( 'DEV_DATA', __DIR__ . '/data' );
if ( ! is_dir( DEV_DATA ) ) { mkdir( DEV_DATA, 0777, true ); }
if ( ! is_dir( DEV_DATA . '/uploads' ) ) { mkdir( DEV_DATA . '/uploads', 0777, true ); }

// ---------------------------------------------------------------- wpdb trên SQLite
class WP_Error {
	public $errors = array();
	public function __construct( $code = '', $msg = '' ) { if ( $code ) { $this->errors[ $code ] = array( $msg ); } }
	public function get_error_message() { foreach ( $this->errors as $m ) { return $m[0]; } return ''; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

class DevWpdb {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	/** @var PDO */
	public $pdo;
	public function __construct() {
		$this->pdo = new PDO( 'sqlite:' . DEV_DATA . '/dev.sqlite' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec( 'PRAGMA journal_mode=WAL' );
	}
	public function get_charset_collate() { return ''; }
	private function sql( $q ) {
		$q = str_replace( 'UTC_TIMESTAMP()', "strftime('%Y-%m-%d %H:%M:%S','now')", $q );
		return $q;
	}
	public function prepare( $q, ...$args ) {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		return preg_replace_callback( '/%([sdf])/', function ( $m ) use ( &$i, $args ) {
			$v = isset( $args[ $i ] ) ? $args[ $i ] : '';
			$i++;
			if ( $m[1] === 'd' ) { return (string) (int) $v; }
			if ( $m[1] === 'f' ) { return (string) (float) $v; }
			return $this->pdo->quote( (string) $v );
		}, $q );
	}
	public function query( $q ) {
		try { return $this->pdo->exec( $this->sql( $q ) ); } catch ( Exception $e ) { $this->last_error = $e->getMessage(); error_log( 'SQL: ' . $q . ' — ' . $e->getMessage() ); return false; }
	}
	public function get_results( $q, $out = OBJECT ) {
		try { $st = $this->pdo->query( $this->sql( $q ) ); $rows = $st->fetchAll( PDO::FETCH_ASSOC ); }
		catch ( Exception $e ) { $this->last_error = $e->getMessage(); error_log( 'SQL: ' . $q . ' — ' . $e->getMessage() ); return array(); }
		if ( $out === ARRAY_A ) { return $rows; }
		return array_map( function ( $r ) { return (object) $r; }, $rows );
	}
	public function get_row( $q, $out = OBJECT ) { $r = $this->get_results( $q, $out ); return $r ? $r[0] : null; }
	public function get_var( $q ) { $r = $this->get_results( $q, ARRAY_A ); if ( ! $r ) { return null; } $v = array_values( $r[0] ); return $v[0]; }
	public function get_col( $q ) { $r = $this->get_results( $q, ARRAY_A ); return array_map( function ( $x ) { $v = array_values( $x ); return $v[0]; }, $r ); }
	public function insert( $t, $data ) {
		$cols = array_keys( $data );
		$vals = array_map( function ( $v ) { return is_null( $v ) ? 'NULL' : ( is_bool( $v ) ? ( $v ? '1' : '0' ) : $this->pdo->quote( (string) $v ) ); }, array_values( $data ) );
		$ok = $this->query( "INSERT INTO $t (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ')' );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return $ok === false ? false : 1;
	}
	public function replace( $t, $data ) {
		$cols = array_keys( $data );
		$vals = array_map( function ( $v ) { return is_null( $v ) ? 'NULL' : ( is_bool( $v ) ? ( $v ? '1' : '0' ) : $this->pdo->quote( (string) $v ) ); }, array_values( $data ) );
		$ok = $this->query( "INSERT OR REPLACE INTO $t (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ')' );
		return $ok === false ? false : 1;
	}
	public function update( $t, $data, $where ) {
		$set = array(); $w = array();
		foreach ( $data as $k => $v ) { $set[] = "$k=" . ( is_null( $v ) ? 'NULL' : ( is_bool( $v ) ? ( $v ? '1' : '0' ) : $this->pdo->quote( (string) $v ) ) ); }
		foreach ( $where as $k => $v ) { $w[] = "$k=" . $this->pdo->quote( (string) $v ); }
		return $this->query( "UPDATE $t SET " . implode( ',', $set ) . ' WHERE ' . implode( ' AND ', $w ) );
	}
	public function delete( $t, $where ) {
		$w = array();
		foreach ( $where as $k => $v ) { $w[] = "$k=" . $this->pdo->quote( (string) $v ); }
		return $this->query( "DELETE FROM $t WHERE " . implode( ' AND ', $w ) );
	}
}
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['wpdb'] = new DevWpdb();

/** dbDelta: dịch CREATE TABLE MySQL → SQLite (bỏ KEY, AUTO_INCREMENT → AUTOINCREMENT). */
function dbDelta( $q ) {
	global $wpdb;
	if ( ! preg_match( '/CREATE TABLE\s+(\S+)\s*\((.*)\)\s*$/s', trim( $q ), $m ) ) { return; }
	$t = $m[1];
	$exists = $wpdb->get_var( "SELECT name FROM sqlite_master WHERE type='table' AND name=" . $wpdb->pdo->quote( $t ) );
	if ( $exists ) { return; }
	$lines = array_map( 'trim', preg_split( '/,\s*\n/', trim( $m[2] ) ) );
	$cols = array();
	foreach ( $lines as $l ) {
		if ( preg_match( '/^(PRIMARY KEY|KEY|UNIQUE KEY|INDEX)\b/i', $l ) ) {
			if ( preg_match( '/^PRIMARY KEY\s*\((\w+)\)/i', $l, $pk ) ) { $cols['__pk'] = $pk[1]; }
			continue;
		}
		$cols[] = $l;
	}
	$pk = isset( $cols['__pk'] ) ? $cols['__pk'] : '';
	unset( $cols['__pk'] );
	$out = array();
	foreach ( $cols as $c ) {
		if ( preg_match( '/^(\w+)\s+BIGINT\(\d+\)\s+NOT NULL\s+AUTO_INCREMENT/i', $c, $a ) ) { $out[] = $a[1] . ' INTEGER PRIMARY KEY AUTOINCREMENT'; $pk = ''; continue; }
		$c = preg_replace( '/\b(VARCHAR|CHAR)\(\d+\)/i', 'TEXT', $c );
		$c = preg_replace( '/\bDECIMAL\(\d+,\d+\)/i', 'REAL', $c );
		$c = preg_replace( '/\b(BIGINT|INT|TINYINT)\(\d+\)/i', 'INTEGER', $c );
		$c = preg_replace( '/\bLONGTEXT\b|\bDATETIME\b/i', 'TEXT', $c );
		$out[] = $c;
	}
	if ( $pk ) { $out[] = "PRIMARY KEY ($pk)"; }
	$wpdb->query( "CREATE TABLE $t (" . implode( ', ', $out ) . ')' );
}

// ---------------------------------------------------------------- options & transients (bảng riêng)
$GLOBALS['wpdb']->query( 'CREATE TABLE IF NOT EXISTS dev_options (k TEXT PRIMARY KEY, v TEXT)' );
function get_option( $k, $d = false ) { global $wpdb; $v = $wpdb->get_var( 'SELECT v FROM dev_options WHERE k=' . $wpdb->pdo->quote( $k ) ); if ( $v === null ) { return $d; } $u = @unserialize( $v ); return $u === false && $v !== serialize( false ) ? $v : $u; }
function update_option( $k, $v ) { global $wpdb; $wpdb->query( 'INSERT OR REPLACE INTO dev_options (k,v) VALUES (' . $wpdb->pdo->quote( $k ) . ',' . $wpdb->pdo->quote( serialize( $v ) ) . ')' ); return true; }
function delete_option( $k ) { global $wpdb; $wpdb->query( 'DELETE FROM dev_options WHERE k=' . $wpdb->pdo->quote( $k ) ); return true; }
function get_transient( $k ) { $v = get_option( '_t_' . $k, null ); if ( ! is_array( $v ) || ! isset( $v['x'] ) ) { return false; } if ( $v['x'] && $v['x'] < time() ) { delete_option( '_t_' . $k ); return false; } return $v['v']; }
function set_transient( $k, $v, $ttl = 0 ) { return update_option( '_t_' . $k, array( 'v' => $v, 'x' => $ttl ? time() + $ttl : 0 ) ); }
function delete_transient( $k ) { return delete_option( '_t_' . $k ); }

// ---------------------------------------------------------------- hàm tiện ích WP
function plugin_dir_path( $f ) { return rtrim( dirname( $f ), '/' ) . '/'; }
function plugin_dir_url( $f ) { return DEV_BASE . '/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function register_activation_hook( $f, $cb ) {}
$GLOBALS['dev_hooks'] = array();
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['dev_hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['dev_hooks'][ $h ][] = $cb; }
function add_shortcode( $t, $cb ) {}
function add_menu_page() {}
function add_submenu_page() {}
function register_rest_route( $ns, $r, $a ) { $GLOBALS['dev_rest'][ $ns . $r ] = $a; }
function add_rewrite_rule( $re, $q, $pos = 'bottom' ) { $GLOBALS['dev_rewrite'][ $re ] = $q; }
function flush_rewrite_rules( $h = true ) {}
function get_query_var( $k, $d = '' ) { return isset( $GLOBALS['dev_qv'][ $k ] ) ? $GLOBALS['dev_qv'][ $k ] : $d; }
function home_url( $p = '' ) { return DEV_BASE . $p; }
function admin_url( $p = '' ) { return DEV_BASE . '/wp-admin/' . $p; }
function rest_url( $p = '' ) { return DEV_BASE . '/wp-json/' . $p; }
function add_query_arg( $k, $v = null, $url = null ) {
	if ( is_array( $k ) ) { $url = $v; $args = $k; } else { $args = array( $k => $v ); }
	$sep = strpos( $url, '?' ) === false ? '?' : '&';
	return $url . $sep . http_build_query( $args );
}
function esc_url_raw( $u ) { return (string) $u; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { $s = strtolower( trim( (string) $s ) ); $s = preg_replace( '/[^a-z0-9\-]+/', '-', $s ); return trim( $s, '-' ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._\-]+/', '-', (string) $s ); }
function wp_unslash( $s ) { return is_array( $s ) ? array_map( 'wp_unslash', $s ) : stripslashes( (string) $s ); }
function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function current_user_can( $c ) { return false; }
function wp_get_current_user() { return (object) array( 'display_name' => '', 'user_email' => '' ); }
function get_current_user_id() { return 0; }
function wp_generate_password( $n = 12, $sp = true, $ex = false ) { $c = 'abcdefghijklmnopqrstuvwxyz0123456789'; $o = ''; for ( $i = 0; $i < $n; $i++ ) { $o .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; } return $o; }
function wp_upload_dir() { return array( 'basedir' => DEV_DATA . '/uploads', 'baseurl' => DEV_BASE . '/uploads' ); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function nocache_headers() { header( 'Cache-Control: no-cache, must-revalidate, max-age=0' ); }
function status_header( $c ) { http_response_code( (int) $c ); }
function wp_die( $m = '' ) { echo $m; exit; }
function wp_remote_get( $u, $a = array() ) { return new WP_Error( 'dev', 'Không có mạng trong bản giả lập' ); }
function wp_remote_retrieve_response_code( $r ) { return 0; }
function wp_remote_retrieve_body( $r ) { return ''; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function wp_safe_redirect( $u ) { header( 'Location: ' . $u ); }
function check_admin_referer() { return true; }
function wp_nonce_field() {}
function submit_button( $t ) { echo '<button class="button button-primary">' . esc_html( $t ) . '</button>'; }
function wpautop( $s ) { return '<p>' . $s . '</p>'; }
function version_compare_wp() {}

class WP_REST_Request {
	private $p = array(); private $h = array();
	public function __construct( $m = 'POST', $r = '' ) {}
	public function set_param( $k, $v ) { $this->p[ $k ] = $v; }
	public function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
	public function set_header( $k, $v ) { $this->h[ strtolower( $k ) ] = $v; }
	public function get_header( $k ) { $k = strtolower( str_replace( '-', '_', $k ) ); return isset( $this->h[ $k ] ) ? $this->h[ $k ] : ''; }
}
class WP_REST_Response {
	private $d; private $s;
	public function __construct( $d, $s = 200 ) { $this->d = $d; $this->s = $s; }
	public function get_data() { return $this->d; }
	public function get_status() { return $this->s; }
}
