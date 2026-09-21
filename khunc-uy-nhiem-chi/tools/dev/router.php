<?php
/**
 * Router cho `php -S` — chạy plugin không cần WordPress (xem wp-stub.php).
 *
 *   php -S 127.0.0.1:8088 khunc-uy-nhiem-chi/tools/dev/router.php
 *
 * Đường:
 *   /uy-nhiem-chi/                                 → trang app
 *   /wp-json/khunc/v1/call                          → REST
 *   /wp-admin/admin-ajax.php (action=khunc_call)    → cổng dự phòng
 *   /wp-content/plugins/khunc-uy-nhiem-chi/…     → tệp tĩnh của plugin
 *   /uploads/…                                     → tệp đã tải lên
 *   /__dev/reset                                   → xoá dữ liệu (chỉ bản giả lập)
 */
require_once __DIR__ . '/wp-stub.php';
$PLUGIN = dirname( dirname( __DIR__ ) );
require_once $PLUGIN . '/khunc-uy-nhiem-chi.php';

if ( get_option( 'khunc_db_version' ) !== KHUNC_DB::SCHEMA_VERSION ) { KHUNC_DB::install(); }
update_option( 'permalink_structure', '/%postname%/' );
KHUNC_App::init();

$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

// tệp tĩnh
$static_prefix = '/wp-content/plugins/khunc-uy-nhiem-chi/';
if ( strpos( $path, $static_prefix ) === 0 ) {
	$f = realpath( $PLUGIN . '/' . substr( $path, strlen( $static_prefix ) ) );
	if ( $f && strpos( $f, realpath( $PLUGIN ) ) === 0 && is_file( $f ) ) {
		$ext = strtolower( pathinfo( $f, PATHINFO_EXTENSION ) );
		$mime = array( 'js' => 'application/javascript', 'css' => 'text/css', 'html' => 'text/html', 'png' => 'image/png', 'svg' => 'image/svg+xml' );
		header( 'Content-Type: ' . ( isset( $mime[ $ext ] ) ? $mime[ $ext ] : 'application/octet-stream' ) . '; charset=utf-8' );
		readfile( $f );
		exit;
	}
	http_response_code( 404 ); echo 'không có'; exit;
}
if ( strpos( $path, '/uploads/' ) === 0 ) {
	$f = realpath( DEV_DATA . $path );
	if ( $f && is_file( $f ) ) { header( 'Content-Type: application/octet-stream' ); readfile( $f ); exit; }
	http_response_code( 404 ); exit;
}
if ( $path === '/__dev/reset' ) {
	global $wpdb;
	foreach ( array( 'kho', 'theo_doi', 'nhat_ky', 'phien' ) as $t ) { $wpdb->query( 'DELETE FROM ' . KHUNC_DB::t( $t ) ); }
	$wpdb->query( 'DELETE FROM ' . KHUNC_DB::t( 'nguoi_dung' ) );
	KHUNC_DB::seed_admin();
	// thêm 1 kế toán PIN 2222 và 1 nhân viên PIN 3333 cho kiểm thử
	$wpdb->insert( KHUNC_DB::t( 'nguoi_dung' ), array( 'ten' => 'Thắng', 'pin_hash' => password_hash( '2222', PASSWORD_DEFAULT ), 'vai' => 'Kế toán', 'hoat_dong' => 1, 'tao_luc' => KHUNC_Util::now_sql(), 'sua_luc' => KHUNC_Util::now_sql() ) );
	$wpdb->insert( KHUNC_DB::t( 'nguoi_dung' ), array( 'ten' => 'Lan', 'pin_hash' => password_hash( '3333', PASSWORD_DEFAULT ), 'vai' => 'Xem', 'bo_phan' => 'Funzone', 'hoat_dong' => 1, 'tao_luc' => KHUNC_Util::now_sql(), 'sua_luc' => KHUNC_Util::now_sql() ) );
	header( 'Content-Type: application/json' ); echo '{"ok":true}'; exit;
}
if ( $path === '/__dev/dump' ) {
	global $wpdb;
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( array(
		'users' => $wpdb->get_results( 'SELECT id,ten,vai,hoat_dong FROM ' . KHUNC_DB::t( 'nguoi_dung' ), ARRAY_A ),
		'kho'     => $wpdb->get_results( 'SELECT khoa,phien_ban,nguoi_cap_nhat,length(du_lieu) AS len FROM ' . KHUNC_DB::t( 'kho' ), ARRAY_A ),
		'theoDoi' => $wpdb->get_results( 'SELECT id,trang_thai,ngay_di,so_unc,nguoi_cap_nhat FROM ' . KHUNC_DB::t( 'theo_doi' ) . ' LIMIT 20', ARRAY_A ),
		'soTheoDoi' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHUNC_DB::t( 'theo_doi' ) ),
		'log'   => $wpdb->get_results( 'SELECT nguoi,vai,hanh_dong FROM ' . KHUNC_DB::t( 'nhat_ky' ) . ' ORDER BY id DESC LIMIT 10', ARRAY_A ),
	) );
	exit;
}
if ( $path === '/wp-json/khunc/v1/call' ) {
	$body = json_decode( file_get_contents( 'php://input' ), true );
	$req  = new WP_REST_Request();
	$req->set_param( 'fn', isset( $body['fn'] ) ? $body['fn'] : '' );
	$req->set_param( 'args', isset( $body['args'] ) ? $body['args'] : array() );
	$req->set_param( 'token', isset( $body['token'] ) ? $body['token'] : '' );
	if ( isset( $_SERVER['HTTP_X_KHUNC_TOKEN'] ) ) { $req->set_header( 'x_khunc_token', $_SERVER['HTTP_X_KHUNC_TOKEN'] ); }
	$res = KHUNC_API::handle( $req );
	http_response_code( $res->get_status() );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $res->get_data() );
	exit;
}
if ( $path === '/wp-admin/admin-ajax.php' ) { KHUNC_API::ajax(); exit; }

// trang app
$slug = KHUNC_App::slug();
if ( preg_match( '#^/' . preg_quote( $slug, '#' ) . '/?$#', $path ) ) { $GLOBALS['dev_qv']['khunc_app'] = 'app'; }
KHUNC_App::maybe_render();

http_response_code( 404 );
echo '<p>Bản giả lập: mở <a href="/' . $slug . '/">/' . $slug . '/</a></p>';
