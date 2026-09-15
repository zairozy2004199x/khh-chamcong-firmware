<?php
/**
 * Máy chủ giả lập WordPress để chạy thử plugin Dò Vé Rẻ bằng trình duyệt thật.
 * Chạy: php -S 127.0.0.1:8098 mockdvr.php
 * Đặt DVR_ADMIN=0 để xem với tư cách khách.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DVR_STORE', __DIR__ . '/dvr-orders.json' );
$GLOBALS['_admin'] = getenv( 'DVR_ADMIN' ) === '0' ? false : true;

function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return '/assets-root/'; }
function home_url( $p = '' ) { return 'http://127.0.0.1:8098' . $p; }
function admin_url( $p = '' ) { return 'http://127.0.0.1:8098/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'http://127.0.0.1:8098/wp-json/' . $p; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_url_raw( $u ) { return (string) $u; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_rand( $a, $b ) { return random_int( $a, $b ); }
function get_option( $k, $d = false ) { return isset( $GLOBALS['_opts'][ $k ] ) ? $GLOBALS['_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['_opts'][ $k ] = $v; return true; }
function add_option( $k, $v ) { return update_option( $k, $v ); }
function current_user_can( $c ) { return $GLOBALS['_admin']; }
function current_time( $t, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_get_current_user() { return (object) array( 'display_name' => 'Quản trị thử' ); }
function wp_list_pluck( $list, $f ) { return array_map( function ( $x ) use ( $f ) { return isset( $x[ $f ] ) ? $x[ $f ] : ''; }, (array) $list ); }
function number_format_i18n( $n ) { return number_format( $n, 0, ',', '.' ); }
function add_action() {}
function add_shortcode() {}
function add_menu_page() {}
function add_submenu_page() {}
function register_activation_hook() {}
function register_rest_route() {}
function rest_ensure_response( $r ) { return $r; }
function do_action() {}
function status_header() {}
function nocache_headers() {}
function language_attributes() { echo 'lang="vi"'; }
function bloginfo( $x ) { echo 'utf-8'; }
function wp_create_nonce( $a ) { return 'test-nonce'; }
function wp_nonce_field() {}
function check_admin_referer() { return true; }
function wp_die( $m ) { die( $m ); }
function wp_enqueue_style() {}
function wp_register_script() {}
function wp_enqueue_script() {}
function wp_add_inline_style( $h, $css ) { $GLOBALS['_inline'] = $css; }
function wp_localize_script( $h, $name, $data ) { $GLOBALS['_cfg'] = array( $name, $data ); }
function wp_print_styles() {}
function wp_print_footer_scripts() {}
class WP_Error {
	public $c, $m, $d;
	function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	function status() { return isset( $this->d['status'] ) ? $this->d['status'] : 400; }
}
class FakeReq implements ArrayAccess {
	private $p, $j;
	function __construct( $p = array(), $j = null ) { $this->p = $p; $this->j = $j; }
	function get_json_params() { return $this->j; }
	#[\ReturnTypeWillChange] function offsetGet( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
	#[\ReturnTypeWillChange] function offsetExists( $k ) { return isset( $this->p[ $k ] ); }
	#[\ReturnTypeWillChange] function offsetSet( $k, $v ) { $this->p[ $k ] = $v; }
	#[\ReturnTypeWillChange] function offsetUnset( $k ) { unset( $this->p[ $k ] ); }
}

/* kho đơn: file JSON thay cho bảng MySQL */
function dvr_store() {
	$raw = is_readable( DVR_STORE ) ? file_get_contents( DVR_STORE ) : '{}';
	$j   = json_decode( $raw, true );
	return is_array( $j ) ? $j : array();
}
function dvr_store_put( $all ) { file_put_contents( DVR_STORE, json_encode( $all, JSON_UNESCAPED_UNICODE ) ); }
function dvr_table() { return 'wp_dvr_orders'; }
function dvr_get_order( $code ) { $a = dvr_store(); return isset( $a[ $code ] ) ? $a[ $code ] : null; }
function dvr_put_order( $o ) { $a = dvr_store(); $a[ $o['code'] ] = $o; dvr_store_put( $a ); return $o; }
function dvr_new_code() { return 'DVR' . gmdate( 'ym' ) . wp_rand( 1000, 9999 ); }
function dvr_rest_list() {
	$a = array_values( dvr_store() );
	usort( $a, function ( $x, $y ) { return strcmp( $y['createdAt'], $x['createdAt'] ); } );
	return array( 'orders' => $a );
}

/* nạp plugin, bỏ các hàm đã thay ở trên */
$src = file_get_contents( __DIR__ . '/../../../wordpress/do-ve-re/do-ve-re.php' );
$src = preg_replace( '/^<\?php/', '', $src, 1 );
foreach ( array( 'dvr_table', 'dvr_get_order', 'dvr_put_order', 'dvr_new_code', 'dvr_rest_list', 'dvr_activate' ) as $fn ) {
	$src = preg_replace( '/\nfunction ' . $fn . '\s*\([^)]*\)\s*\{.*?\n\}/s', "\n", $src, 1 );
}
eval( $src );

/* ------------------------------- định tuyến ------------------------------- */
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

if ( 0 === strpos( $path, '/assets-root/assets/' ) ) {
	$f = __DIR__ . '/../../../wordpress/do-ve-re/assets/' . basename( $path );
	if ( ! is_readable( $f ) ) { http_response_code( 404 ); exit; }
	header( 'Content-Type: ' . ( substr( $f, -4 ) === '.css' ? 'text/css' : 'application/javascript' ) );
	readfile( $f );
	exit;
}

if ( 0 === strpos( $path, '/wp-json/dvr/v1/orders' ) ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	$rest = substr( $path, strlen( '/wp-json/dvr/v1/orders' ) );
	$code = trim( $rest, '/' );
	$body = json_decode( file_get_contents( 'php://input' ), true );
	$post = 'POST' === $_SERVER['REQUEST_METHOD'];

	if ( '' === $code ) {
		/* giống permission_callback của route: GET danh sách chỉ dành cho quản trị */
		if ( ! $post && ! dvr_is_admin() ) {
			http_response_code( 403 );
			echo json_encode( array( 'message' => 'Không đủ quyền.' ) );
			exit;
		}
		$r = $post ? dvr_rest_create( new FakeReq( array(), $body ) ) : dvr_rest_list();
	} else {
		if ( $post && ! dvr_is_admin() ) {
			http_response_code( 403 );
			echo json_encode( array( 'message' => 'Không đủ quyền.' ) );
			exit;
		}
		$req = new FakeReq( array( 'code' => $code ), $body );
		$r   = $post ? dvr_rest_update( $req ) : dvr_rest_get( $req );
	}
	if ( $r instanceof WP_Error ) {
		http_response_code( $r->status() );
		echo json_encode( array( 'message' => $r->m ) );
		exit;
	}
	echo json_encode( $r, JSON_UNESCAPED_UNICODE );
	exit;
}

/* trang khách */
dvr_assets();
list( $cfgName, $cfg ) = $GLOBALS['_cfg'];
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dò vé rẻ — chạy thử</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets-root/assets/dvr.css">
<style><?php echo $GLOBALS['_inline']; ?></style>
</head><body>
<?php include __DIR__ . '/../../../wordpress/do-ve-re/page.php'; ?>
<script>window.<?php echo $cfgName; ?> = <?php echo json_encode( $cfg, JSON_UNESCAPED_UNICODE ); ?>;</script>
<script src="/assets-root/assets/dvr.js"></script>
</body></html>
