<?php
/**
 * Router cho `php -S` — chạy plugin không cần WordPress (xem wp-stub.php).
 *
 *   php -S 127.0.0.1:8088 khbc-bao-cao-chi-phi/tools/dev/router.php
 *
 * Đường:
 *   /bao-cao-chi-phi/ , /bao-cao-chi-phi/nhap/     → trang app
 *   /wp-json/khbc/v1/call                          → REST
 *   /wp-admin/admin-ajax.php (action=khbc_call)    → cổng dự phòng
 *   /wp-content/plugins/khbc-bao-cao-chi-phi/…     → tệp tĩnh của plugin
 *   /uploads/…                                     → tệp đã tải lên
 *   /__dev/reset                                   → xoá dữ liệu (chỉ bản giả lập)
 */
require_once __DIR__ . '/wp-stub.php';
$PLUGIN = dirname( dirname( __DIR__ ) );
require_once $PLUGIN . '/khbc-bao-cao-chi-phi.php';

if ( get_option( 'khbc_db_version' ) !== KHBC_DB::SCHEMA_VERSION ) { KHBC_DB::install(); }
update_option( 'permalink_structure', '/%postname%/' );
KHBC_App::init();

$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

// tệp tĩnh
$static_prefix = '/wp-content/plugins/khbc-bao-cao-chi-phi/';
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
	foreach ( array( 'khoan', 'ky', 'nhat_ky', 'phien' ) as $t ) { $wpdb->query( 'DELETE FROM ' . KHBC_DB::t( $t ) ); }
	$wpdb->query( 'DELETE FROM ' . KHBC_DB::t( 'nguoi_dung' ) );
	KHBC_DB::seed_admin();
	// thêm 1 kế toán PIN 2222 và 1 nhân viên PIN 3333 cho kiểm thử
	$wpdb->insert( KHBC_DB::t( 'nguoi_dung' ), array( 'ten' => 'Thắng', 'pin_hash' => password_hash( '2222', PASSWORD_DEFAULT ), 'vai' => 'Kế toán', 'hoat_dong' => 1, 'tao_luc' => KHBC_Util::now_sql(), 'sua_luc' => KHBC_Util::now_sql() ) );
	$wpdb->insert( KHBC_DB::t( 'nguoi_dung' ), array( 'ten' => 'Lan', 'pin_hash' => password_hash( '3333', PASSWORD_DEFAULT ), 'vai' => 'Nhân viên', 'bo_phan' => 'Funzone', 'hoat_dong' => 1, 'tao_luc' => KHBC_Util::now_sql(), 'sua_luc' => KHBC_Util::now_sql() ) );
	header( 'Content-Type: application/json' ); echo '{"ok":true}'; exit;
}
if ( $path === '/__dev/dump' ) {
	global $wpdb;
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( array(
		'users' => $wpdb->get_results( 'SELECT id,ten,vai,hoat_dong FROM ' . KHBC_DB::t( 'nguoi_dung' ), ARRAY_A ),
		'ky'    => $wpdb->get_results( 'SELECT ky,phien_ban,khoa,nguoi_cap_nhat,length(du_lieu) AS len FROM ' . KHBC_DB::t( 'ky' ), ARRAY_A ),
		'khoan' => $wpdb->get_results( 'SELECT id,ky,trang_thai,nguoi_tao,ten,tong,tep FROM ' . KHBC_DB::t( 'khoan' ) . ' WHERE da_xoa=0', ARRAY_A ),
		'log'   => $wpdb->get_results( 'SELECT nguoi,vai,hanh_dong FROM ' . KHBC_DB::t( 'nhat_ky' ) . ' ORDER BY id DESC LIMIT 10', ARRAY_A ),
	) );
	exit;
}
if ( $path === '/wp-json/khbc/v1/call' ) {
	$body = json_decode( file_get_contents( 'php://input' ), true );
	$req  = new WP_REST_Request();
	$req->set_param( 'fn', isset( $body['fn'] ) ? $body['fn'] : '' );
	$req->set_param( 'args', isset( $body['args'] ) ? $body['args'] : array() );
	$req->set_param( 'token', isset( $body['token'] ) ? $body['token'] : '' );
	if ( isset( $_SERVER['HTTP_X_KHBC_TOKEN'] ) ) { $req->set_header( 'x_khbc_token', $_SERVER['HTTP_X_KHBC_TOKEN'] ); }
	$res = KHBC_API::handle( $req );
	http_response_code( $res->get_status() );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $res->get_data() );
	exit;
}
if ( $path === '/wp-admin/admin-ajax.php' ) { KHBC_API::ajax(); exit; }

// trang app
$slug = KHBC_App::slug();
if ( preg_match( '#^/' . preg_quote( $slug, '#' ) . '/nhap/?$#', $path ) ) { $GLOBALS['dev_qv']['khbc_app'] = 'nhap'; }
elseif ( preg_match( '#^/' . preg_quote( $slug, '#' ) . '/?$#', $path ) ) { $GLOBALS['dev_qv']['khbc_app'] = 'app'; }
KHBC_App::maybe_render();

http_response_code( 404 );
echo '<p>Bản giả lập: mở <a href="/' . $slug . '/">/' . $slug . '/</a></p>';
