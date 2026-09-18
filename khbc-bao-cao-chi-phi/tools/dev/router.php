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
/* Dựng bảng của plugin "Doanh thu FABi" + vài dòng thật, để kiểm đường nạp doanh thu ngay tại
   chỗ mà không cần cài plugin kia. 13 tên cửa hàng lấy đúng trang khmatrix.com/doanh-thu-hcm. */
if ( $path === '/__dev/fabi' ) {
	global $wpdb;
	$b = $wpdb->prefix . 'khh_dt_ngay';
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $b ( id INTEGER PRIMARY KEY AUTOINCREMENT,
		ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', thanh_tien REAL NOT NULL DEFAULT 0 )" );
	$wpdb->query( "DELETE FROM $b" );
	$quan = array(
		array( '(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ ( Dịch Vụ và Giải Trí K&H )', 18225000 ),
		array( 'COFFE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 620000 ),
		array( 'ECO FARM LOTTE PHAN THIẾT ( Dịch Vụ và Giải Trí K&H )', 6935000 ),
		array( 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', 2265000 ),
		array( 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )', 27667000 ),
		array( 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )', 29905000 ),
		array( 'TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )', 10440000 ),
		array( 'Tutu Train - Aeon Tân An ( Dịch vụ K&H )', 3580000 ),
		array( 'Tutu Train - Aeon Bình Tân ( Dịch vụ K&H )', 0 ),
		array( 'Tutu Train - Bình Dương ( Dịch Vụ K&H )', 12045000 ),
		array( 'Tutu Train - Estella ( Dịch vụ K&H )', 20770000 ),
		array( 'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )', 3680000 ),
		array( 'VR Fun Aeon Tân An ( Dịch Vụ K&H )', 3120000 ),
	);
	/* Chia đều ra 2 ngày trong tháng 8/2026 — cốt để phép CỘNG THEO KỲ có việc mà làm; một dòng
	   một cửa hàng thì không kiểm được là nó có cộng hay chỉ lấy dòng cuối. */
	foreach ( $quan as $q ) {
		$wpdb->insert( $b, array( 'ngay' => '2026-08-05', 'cua_hang' => $q[0], 'thanh_tien' => $q[1] * 0.4 ) );
		$wpdb->insert( $b, array( 'ngay' => '2026-08-20', 'cua_hang' => $q[0], 'thanh_tien' => $q[1] * 0.6 ) );
		$wpdb->insert( $b, array( 'ngay' => '2026-07-15', 'cua_hang' => $q[0], 'thanh_tien' => 999000000 ) );  // kỳ khác — KHÔNG được lọt vào
	}
	header( 'Content-Type: application/json' );
	echo wp_json_encode( array( 'ok' => true, 'quan' => count( $quan ) ) );
	exit;
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
