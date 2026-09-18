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

/* ─────────────────────────────────────────────────────────────────────────────────────────────
 * GIẢ LẬP plugin "Chấm công" để kiểm đường nạp lương mà không cần cài plugin kia.
 *
 * Bắt chước ĐÚNG hình dạng thật của `VHCC_BangLuong::dung()` (đọc từ mã nguồn anh Thắng gửi
 * 18/09/2026): `dong[]` mỗi dòng có `luongChinh` (null = CHƯA KHAI ĐƠN GIÁ, không phải 0),
 * `tongCong`, `tongTru`; kèm `tong.gio` và `thieu.{gia,gio,congChuan}`.
 * Và `VHCC_Luong::ghep_vao()` để kiểm cái bẫy cơ sở phụ bị cộng đôi.
 * ───────────────────────────────────────────────────────────────────────────────────────────── */
class VHCC_Luong {
	/** Cơ sở phụ ghép vào cơ sở chính — '' nếu đứng riêng. */
	public static function ghep_vao( $coso ) {
		$m = get_option( 'dev_ns_ghep', array() );
		return isset( $m[ $coso ] ) ? (string) $m[ $coso ] : '';
	}
	public static function bo_phan_cua( $coso ) {
		$d = get_option( 'dev_ns_luong', array() );
		foreach ( $d as $k => $v ) {
			if ( 0 === strpos( $k, $coso . '|' ) ) { return (string) $v['bp']; }
		}
		return '';
	}
	/**
	 * Bộ PHÂN LOẠI ba lối tính của bên ấy — plugin báo cáo chi phí đi theo đúng cái này:
	 *   mtd = Máy tự động (Posh, JP) · vp = Văn phòng · tho = còn lại (Khu vui chơi).
	 */
	public static function bang_cong_va_luong( $coso, $thang ) {
		$d = get_option( 'dev_ns_luong', array() );
		$k = $coso . '|' . $thang;
		$r = isset( $d[ $k ] ) ? $d[ $k ] : null;
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không có bảng công.' ); }
		$kieu = isset( $r['kieu'] ) ? $r['kieu'] : 'tho';
		if ( 'mtd' === $kieu ) {
			return array( 'ok' => true, 'kieu' => 'mtd', 'coLuong' => true, 'boPhan' => 'Máy tự động',
				'mtd' => array( 'tong' => array( 'tong' => $r['tien'], 'soGio' => $r['gio'] ),
					'chuaKhaiGia' => ( $r['tien'] <= 0 ) ) );
		}
		if ( 'vp' === $kieu ) {
			return array( 'ok' => true, 'kieu' => 'vp', 'coLuong' => true, 'boPhan' => 'Văn phòng',
				'vp' => array( 'tien' => array( 'tongTien' => $r['tien'],
					'chuaKhaiNgayCong' => false, 'thieuLuong' => array() ) ) );
		}
		return array( 'ok' => true, 'kieu' => 'tho', 'coLuong' => false,
			'boPhan' => self::bo_phan_cua( $coso ),
			'tho' => array( 'station' => $coso, 'month' => $thang,
				'rows' => array( array( 'ma' => 'NV1', 'ten' => 'NGUYỄN VĂN A',
					'ngay' => array( array( 'date' => $thang . '-07', 'vao' => '08:00', 'ra' => '17:00' ) ) ) ) ) );
	}
}

class VHCC_BangLuong {
	public static function dung( $coso, $thang ) {
		$ds = get_option( 'dev_ns_luong', array() );
		$k  = $coso . '|' . $thang;
		if ( ! isset( $ds[ $k ] ) ) { return array( 'ok' => false, 'error' => 'Không có bảng công.' ); }
		$r = $ds[ $k ];
		$dong = array();
		if ( $r['tien'] > 0 ) {
			$dong[] = array( 'ten' => 'NGUYỄN VĂN A', 'cccd' => '000000000000', 'laChinh' => true,
				'luongChinh' => $r['tien'], 'tongCong' => $r['cong'], 'tongTru' => $r['tru'], 'thieuGio' => 0 );
		}
		/* Dòng CHƯA KHAI ĐƠN GIÁ: luongChinh = null, không phải 0. */
		for ( $i = 0; $i < (int) $r['thieu_gia']; $i++ ) {
			$dong[] = array( 'ten' => 'TRẦN THỊ B', 'cccd' => '000000000000', 'laChinh' => false,
				'luongChinh' => null, 'tongCong' => 0, 'tongTru' => 0, 'thieuGio' => 0 );
		}
		return array( 'ok' => true, 'coso' => $coso, 'thang' => $thang, 'dong' => $dong,
			'tong'  => array( 'nguoi' => count( $dong ), 'gio' => $r['gio'], 'luongChinh' => $r['tien'] ),
			'thieu' => array( 'gia' => (int) $r['thieu_gia'], 'congChuan' => false, 'gio' => (int) $r['thieu_gio'] ) );
	}
}

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
		/* HAI QUÁN MỚI — chưa có điểm nào bên báo cáo. Đây là cảnh anh Thắng nói: FABi tách cơ sở
		   mới thì bên này phải tự tách điểm, chứ không đứng kẹt ở "chưa ghép". */
		array( 'Ngôi Nhà Ma - Aeon Bình Dương ( Dịch Vụ K&H )', 216545000 ),
		array( 'SNOW FUN AEON BÌNH DƯƠNG ( Dịch Vụ K&H )', 209735000 ),
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
/* Bảng của plugin GHẾ MASSAGE + vài cơ sở thật, để kiểm đường nạp doanh thu Posh ngay tại chỗ.
   Tên cơ sở lấy đúng màn "Báo cáo tổng" ở khmatrix.com/ghe anh Thắng gửi. */
if ( $path === '/__dev/ghe' ) {
	global $wpdb;
	$d = $wpdb->prefix . 'vhg_bc_dong';
	$h = $wpdb->prefix . 'vhg_bc';
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $h ( report_id TEXT PRIMARY KEY, coso TEXT NOT NULL DEFAULT '' )" );
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $d ( id INTEGER PRIMARY KEY AUTOINCREMENT,
		report_id TEXT NOT NULL, ngay TEXT NOT NULL, ma_may TEXT NOT NULL DEFAULT '',
		tong REAL NOT NULL DEFAULT 0, qr REAL NOT NULL DEFAULT 0, tien_mat REAL NOT NULL DEFAULT 0,
		actual REAL NOT NULL DEFAULT 0, chi_so_sau INTEGER )" );
	$wpdb->query( "DELETE FROM $d" ); $wpdb->query( "DELETE FROM $h" );
	$cs = array(
		array( 'AEON MALL BÌNH TÂN', 102120000 ),
		array( 'AEON MALL TÂN PHÚ', 52990000 ),
		array( 'BV UNG BƯỚU', 22580000 ),
		array( 'BỆNH VIỆN 175', 2660000 ),
		array( 'CGV BÌNH DƯƠNG', 5380000 ),
		array( 'CGV LÝ CHÍNH THẮNG', 3220000 ),
		array( 'COOPMART BÌNH DƯƠNG', 2930000 ),
	);
	$i = 0;
	foreach ( $cs as $c ) {
		$i++;
		$rid = 'R' . $i;
		$wpdb->insert( $h, array( 'report_id' => $rid, 'coso' => $c[0] ) );
		/* Hai ngày trong kỳ + một ngày KỲ KHÁC (phải bị loại) + một dòng rác tong=0 và
		   chi_so_sau NULL (phải bị điều kiện lọc gạt ra, y như bên plugin Ghế). */
		$wpdb->insert( $d, array( 'report_id' => $rid, 'ngay' => '2026-08-07', 'tong' => $c[1] * 0.4, 'chi_so_sau' => 1 ) );
		$wpdb->insert( $d, array( 'report_id' => $rid, 'ngay' => '2026-08-19', 'tong' => $c[1] * 0.6, 'chi_so_sau' => 1 ) );
		$wpdb->insert( $d, array( 'report_id' => $rid, 'ngay' => '2026-07-10', 'tong' => 888000000, 'chi_so_sau' => 1 ) );
		$wpdb->insert( $d, array( 'report_id' => $rid, 'ngay' => '2026-08-11', 'tong' => 0, 'actual' => 0, 'chi_so_sau' => null ) );
	}
	header( 'Content-Type: application/json' );
	echo wp_json_encode( array( 'ok' => true, 'coso' => count( $cs ) ) );
	exit;
}
/* Dựng bảng chấm công + số lương giả lập. Cơ sở cuối CỐ Ý là dạng 'tho' (chưa khai giá giờ) —
   đúng trường hợp Khu vui chơi trong ảnh anh Thắng gửi. */
if ( $path === '/__dev/nhansu' ) {
	global $wpdb;
	$b = $wpdb->prefix . 'vhcc_cham_cong';
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $b ( id INTEGER PRIMARY KEY AUTOINCREMENT,
		coso TEXT NOT NULL DEFAULT '', ngay TEXT NOT NULL DEFAULT '', nhan_vien TEXT NOT NULL DEFAULT '' )" );
	$wpdb->query( "DELETE FROM $b" );
	/* [ tên, tiền, bộ phận, cộng, trừ, giờ, số dòng chưa khai giá, lượt thiếu giờ, kiểu ] */
	$cs = array(
		/* Máy tự động: lõi RIÊNG, `VHCC_BangLuong::dung()` không tính được cho nhóm này. */
		array( 'POSH MN AEON MALL BÌNH DƯƠNG', 111649262.0, 'posh',  0,       0, 900.0, 0, 0, 'mtd' ),
		array( 'JP MN AEON MALL BÌNH TÂN',      41435755.0, 'jp',  500000, 300000, 400.0, 1, 2, 'mtd' ),
		array( 'VĂN PHÒNG HCM',                 88000000.0, '',         0,      0, 500.0, 0, 0, 'vp' ),
		/* Đúng cảnh anh Thắng gửi: Khu vui chơi CÓ lương thật. */
		array( 'FZ_SC_VIVO_T4',                 52287040.0, 'funzone',  0,      0, 2011.04, 0, 6 ),
		/* Cơ sở PHỤ ghép vào FZ_SC_VIVO_T4 — phải BỊ BỎ QUA, không cộng đôi. */
		array( 'FZ_SC_VIVO_PHU',                 9000000.0, 'funzone',  0,      0, 100.0, 0, 0 ),
		/* Khu vui chơi có khoản CỘNG / TRỪ do kế toán nhập — chỉ lối 'tho' mới có. */
		array( 'EVENT VR TÂN AN',               10000000.0, 'event', 500000, 300000, 150.0, 0, 0 ),
		/* Chưa ai khai đơn giá: tổng ra 0 -> báo CHƯA CÓ, không ghi 0. */
		array( 'TUTU MN AEON MALL TÂN PHÚ',            0.0, 'tutu',     0,      0, 300.0, 4, 0 ),
	);
	$luong = array();
	foreach ( $cs as $c ) {
		$wpdb->insert( $b, array( 'coso' => $c[0], 'ngay' => '2026-08-07', 'nhan_vien' => 'NV1' ) );
		$wpdb->insert( $b, array( 'coso' => $c[0], 'ngay' => '2026-08-19', 'nhan_vien' => 'NV2' ) );
		$luong[ $c[0] . '|2026-08' ] = array( 'tien' => $c[1], 'bp' => $c[2], 'cong' => $c[3],
			'tru' => $c[4], 'gio' => $c[5], 'thieu_gia' => $c[6], 'thieu_gio' => $c[7],
			'kieu' => isset( $c[8] ) ? $c[8] : 'tho' );
	}
	/* Cơ sở CHỈ có chấm công ở kỳ khác — tháng 8 không được thấy nó. */
	$wpdb->insert( $b, array( 'coso' => 'KHO LẠNH THÁNG 7', 'ngay' => '2026-07-10', 'nhan_vien' => 'NV9' ) );
	update_option( 'dev_ns_ghep', array( 'FZ_SC_VIVO_PHU' => 'FZ_SC_VIVO_T4' ) );
	update_option( 'dev_ns_luong', $luong );
	header( 'Content-Type: application/json' );
	echo wp_json_encode( array( 'ok' => true, 'coso' => count( $cs ) ) );
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
