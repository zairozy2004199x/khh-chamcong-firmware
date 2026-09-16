<?php
/**
 * Plugin Name:       K&H — Báo cáo doanh thu FABi
 * Plugin URI:        https://khh.vn/
 * Description:       Nạp file "Báo cáo bán hàng" xuất từ máy POS FABi (iPOS) và dựng báo cáo doanh thu theo ngày, cửa hàng, khung giờ, hình thức thanh toán, tại chỗ/mang về và món bán chạy. Có sẵn đường nối API FABi để bật khi iPOS cấp khoá.
 * Version:           1.9.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            K&H
 * Text Domain:       khh-doanh-thu
 *
 * Cài xong vào menu "Doanh thu FABi" trong trang quản trị, và mở được bằng đường
 * link ngoài (mặc định /doanh-thu-hcm), hoặc nhúng vào trang bất kỳ bằng shortcode
 * [khh_doanh_thu]. Nếu đang bật plugin "Nền tảng K&H" thì báo cáo cũng hiện thành
 * một ứng dụng trong thanh bên của nền tảng.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KHH_DT_VERSION', '1.9.0' );
define( 'KHH_DT_FILE', __FILE__ );
define( 'KHH_DT_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHH_DT_URL', plugin_dir_url( __FILE__ ) );

require_once KHH_DT_DIR . 'doc-file.php';
require_once KHH_DT_DIR . 'bao-cao-ngay.php';
require_once KHH_DT_DIR . 'quan-tri.php';
require_once KHH_DT_DIR . 'nguoi.php';
require_once KHH_DT_DIR . 'sao-ke.php';

/** Đường dẫn ngoài của báo cáo, ví dụ khmatrix.com/doanh-thu-hcm */
function khh_dt_slug() {
	$s = defined( 'KHH_DT_SLUG' ) ? KHH_DT_SLUG : get_option( 'khh_dt_slug', 'doanh-thu-hcm' );
	$s = sanitize_title( $s );
	return $s ? $s : 'doanh-thu-hcm';
}

function khh_dt_link() {
	return home_url( '/' . khh_dt_slug() . '/' );
}

add_filter( 'query_vars', 'khh_dt_query_var' );
function khh_dt_query_var( $v ) {
	$v[] = 'khh_doanh_thu';
	return $v;
}

add_action( 'init', 'khh_dt_rewrite' );
function khh_dt_rewrite() {
	add_rewrite_rule( '^' . khh_dt_slug() . '/?$', 'index.php?khh_doanh_thu=1', 'top' );
	// Nạp lại bảng đường dẫn khi đổi phiên bản hoặc đổi slug, để khỏi phải vào
	// Cài đặt → Đường dẫn tĩnh bấm Lưu bằng tay.
	$dau = KHH_DT_VERSION . '|' . khh_dt_slug();
	if ( get_option( 'khh_dt_rw' ) !== $dau ) {
		flush_rewrite_rules( false );
		update_option( 'khh_dt_rw', $dau, false );
	}
}

function khh_dt_bang() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_ngay';
}

/* ------------------------------------------------------------------ *
 * Cài đặt
 * ------------------------------------------------------------------ */

register_activation_hook( __FILE__, 'khh_dt_kich_hoat' );
function khh_dt_kich_hoat() {
	global $wpdb;
	$bang    = khh_dt_bang();
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ngay date NOT NULL,
			cua_hang varchar(190) NOT NULL DEFAULT '',
			pos_id varchar(40) NOT NULL DEFAULT '',
			doanh_thu double NOT NULL DEFAULT 0,
			chiet_khau double NOT NULL DEFAULT 0,
			thanh_tien double NOT NULL DEFAULT 0,
			so_hd int(11) NOT NULL DEFAULT 0,
			so_mon double NOT NULL DEFAULT 0,
			so_ve double NOT NULL DEFAULT 0,
			gio longtext NOT NULL,
			pttt longtext NOT NULL,
			nguon longtext NOT NULL,
			mon longtext NOT NULL,
			nap_luc bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY ngay_ch (ngay,cua_hang(120)),
			KEY ngay (ngay)
		) $charset;"
	);
	khh_dt_tao_bang_bc();
	khh_dt_tao_bang_nguoi();
	khh_dt_tao_bang_sk();
	update_option( 'khh_dt_version', KHH_DT_VERSION );
	khh_dt_rewrite();
	flush_rewrite_rules( false );
}

/** Nâng cấp bảng khi cài đè bản mới mà không kích hoạt lại. */
add_action( 'init', 'khh_dt_nang_cap', 5 );
function khh_dt_nang_cap() {
	if ( get_option( 'khh_dt_version' ) === KHH_DT_VERSION ) {
		return;
	}
	khh_dt_kich_hoat();
}

/** Ai được nạp file POS và xoá kho — chỉ người của văn phòng. */
function khh_dt_duoc_nap() {
	return current_user_can( 'edit_posts' );
}

/**
 * Ai được nhập báo cáo ngày.
 *
 * Cửa hàng trưởng đẩy từ trang nhân sự sang chỉ là tài khoản thường (subscriber),
 * không có edit_posts — nên quyền nhập lấy theo meta khh_dt_quyen, giống cách app
 * Chi Phí cấp quyền.
 */
function khh_dt_duoc_ghi() {
	if ( current_user_can( 'edit_posts' ) ) {
		return true;
	}
	return in_array( khh_dt_quyen_cua(), array( 'nhap', 'duyet' ), true );
}

/**
 * Ai được xem.
 *
 * Hai đường vào: tài khoản WordPress của văn phòng, và PIN chấm công của người được đẩy từ trang
 * nhân sự sang. Không có cửa thứ ba — màn này bày doanh thu.
 */
function khh_dt_duoc_xem() {
	return is_user_logged_in() || (bool) khh_dt_phien_nguoi();
}

/* ------------------------------------------------------------------ *
 * REST
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'khh_dt_rest' );
function khh_dt_rest() {
	register_rest_route(
		'khh-dt/v1',
		'/cau-hinh',
		array(
			'methods'             => 'GET',
			/* Mở cho mọi người gọi, nhưng KHÔNG trả số liệu gì khi chưa đăng nhập — chỉ trả đúng
			   một câu "hỏi PIN đi". Đây là chỗ giao diện biết phải bày màn gõ PIN hay bày báo cáo;
			   khoá luôn route này thì người vào bằng PIN gặp lỗi 401 trước khi kịp gõ PIN. */
			'callback'            => 'khh_dt_rest_cau_hinh',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/bao-cao',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_bao_cao',
			'permission_callback' => 'khh_dt_duoc_xem',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/nap',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_nap',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/kiem-tra',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_kiem_tra',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/nap-mau',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_nap_mau',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/xoa',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_xoa',
			'permission_callback' => 'khh_dt_duoc_nap',
		)
	);
}

/**
 * Bắt lỗi PHP trong lúc chạy REST và trả về JSON.
 *
 * Lỗi PHP chí mạng mặc định in ra trang HTML, giao diện nhận được "<!DOCTYPE"
 * rồi báo "not valid JSON" — người dùng không biết chuyện gì xảy ra và mình
 * cũng không đọc được nhật ký hosting. Ở đây chuyển nó thành một câu tiếng Việt
 * có kèm tên file và số dòng để còn sửa.
 */
function khh_dt_bat_loi() {
	@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet
	register_shutdown_function(
		function () {
			$e = error_get_last();
			if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR ), true ) ) {
				return;
			}
			if ( ! headers_sent() ) {
				status_header( 500 );
				header( 'Content-Type: application/json; charset=utf-8' );
			}
			echo wp_json_encode(
				array(
					'code'    => 'khh_dt_php',
					'message' => 'Lỗi PHP: ' . $e['message'] . ' (' . basename( $e['file'] ) . ' dòng ' . $e['line'] . ')',
				)
			);
		}
	);
}

/** Máy chủ có đủ đồ để đọc file FABi không. */
function khh_dt_rest_kiem_tra() {
	$tam    = trailingslashit( get_temp_dir() );
	$thu    = $tam . 'khh-dt-thu-' . wp_generate_password( 6, false ) . '.tmp';
	$ghi_ok = false;
	$f      = @fopen( $thu, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	if ( $f ) {
		$ghi_ok = (bool) fwrite( $f, 'ok' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $thu );
	}
	return array(
		'php'          => PHP_VERSION,
		'wp'           => get_bloginfo( 'version' ),
		'plugin'       => KHH_DT_VERSION,
		'ziparchive'   => class_exists( 'ZipArchive' ),
		'xmlreader'    => class_exists( 'XMLReader' ),
		'thu_muc_tam'  => $tam,
		'ghi_duoc_tam' => $ghi_ok,
		'bo_nho'       => ini_get( 'memory_limit' ),
		'thoi_gian'    => ini_get( 'max_execution_time' ),
		'tai_len'      => size_format( wp_max_upload_size() ),
		'post_max'     => ini_get( 'post_max_size' ),
		'bang'         => khh_dt_co_bang(),
	);
}

/** Bảng dữ liệu đã tạo chưa. */
function khh_dt_co_bang() {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bang ) );
}

function khh_dt_rest_cau_hinh() {
	global $wpdb;
	if ( ! khh_dt_duoc_xem() ) {
		return array(
			'can_dang_nhap' => true,
			'co_pin'        => true,
			'link_wp'       => esc_url_raw( wp_login_url( khh_dt_link() ) ),
		);
	}
	$bang = khh_dt_bang();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds   = $wpdb->get_results( "SELECT cua_hang, SUM(doanh_thu) dt FROM $bang GROUP BY cua_hang ORDER BY dt DESC", ARRAY_A );
	/* 🔴 CẮT LUÔN DANH SÁCH CỬA HÀNG GỬI XUỐNG. Số liệu đã cắt theo cơ sở rồi, nhưng nếu ô chọn
	   cửa hàng vẫn liệt kê đủ 15 quán thì cửa hàng trưởng chọn quán người ta và nhận về màn trống
	   — trông y như hệ hỏng. Người phụ trách hai quán thì thấy đúng hai. */
	$cua_ds = khh_dt_co_so_ds();
	if ( $cua_ds ) {
		$ds = array_values( array_filter( (array) $ds, function ( $r ) use ( $cua_ds ) {
			return in_array( (string) $r['cua_hang'], $cua_ds, true );
		} ) );
	}
	$bien = $wpdb->get_row( "SELECT MIN(ngay) tu, MAX(ngay) den, COUNT(*) n FROM $bang", ARRAY_A );
	// phpcs:enable
	$meta = get_option( 'khh_dt_meta', array() );

	return array(
		'cua_hang'  => wp_list_pluck( $ds ? $ds : array(), 'cua_hang' ),
		'tu_ngay'   => $bien && $bien['tu'] ? $bien['tu'] : '',
		'den_ngay'  => $bien && $bien['den'] ? $bien['den'] : '',
		'so_ban_ghi' => $bien ? (int) $bien['n'] : 0,
		'nguon'     => isset( $meta['nguon'] ) ? $meta['nguon'] : '',
		'nap_luc'   => isset( $meta['nap_luc'] ) ? $meta['nap_luc'] : '',
		'ky'        => isset( $meta['ky'] ) ? $meta['ky'] : '',
		'duoc_ghi'  => khh_dt_duoc_ghi(),
		'duoc_nap'  => khh_dt_duoc_nap(),
		'quan_tri'  => khh_dt_duoc_quan_tri(),
		'cua_toi'   => khh_dt_co_so_mac_dinh(),
		'cua_toi_ds' => $cua_ds,
		'ten_toi'   => khh_dt_ten_dang_xem(),
		'bang_pin'  => (bool) khh_dt_phien_nguoi(),
		'chua_ghep_co_so' => in_array( KHH_DT_CHUA_GHEP, $cua_ds, true ),
		'co_api'    => (bool) get_option( 'khh_dt_api_token' ),
		'so_sao_ke' => khh_dt_co_sao_ke(),
		'sao_ke_chua_gan' => khh_dt_sk_chua_gan(),
		'gioi_han_tai_len' => size_format( wp_max_upload_size() ),
		'gioi_han_byte'    => (int) wp_max_upload_size(),
	);
}

function khh_dt_rest_bao_cao( $req ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$tu   = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'tu' ) );
	$den  = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'den' ) );

	$sql  = "SELECT * FROM $bang WHERE 1=1";
	$args = array();
	/* 🔴 CẮT THEO CƠ SỞ NGAY Ở CÂU TRUY VẤN, KHÔNG ĐỂ GIAO DIỆN TỰ GIẤU.
	   Cửa hàng trưởng được gán một cơ sở thì gói dữ liệu gửi xuống máy họ chỉ có cơ sở ấy. Giấu
	   bằng JavaScript là số của 14 quán kia vẫn nằm trong trang, mở tab Mạng của trình duyệt ra
	   là đọc được. */
	$cua_ds = khh_dt_co_so_ds();
	if ( $cua_ds ) {
		$sql   .= ' AND cua_hang IN (' . implode( ',', array_fill( 0, count( $cua_ds ), '%s' ) ) . ')';
		$args   = array_merge( $args, $cua_ds );
	}
	if ( $tu ) {
		$sql   .= ' AND ngay >= %s';
		$args[] = $tu;
	}
	if ( $den ) {
		$sql   .= ' AND ngay <= %s';
		$args[] = $den;
	}
	$sql .= ' ORDER BY ngay ASC';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable

	$theo_ngay = array();
	foreach ( (array) $rows as $r ) {
		$d = $r['ngay'];
		if ( ! isset( $theo_ngay[ $d ] ) ) {
			$theo_ngay[ $d ] = array(
				'ngay' => $d,
				'ch'   => array(),
			);
		}
		$theo_ngay[ $d ]['ch'][] = array(
			'n'  => $r['cua_hang'],
			'r'  => (float) $r['doanh_thu'],
			'ck' => (float) $r['chiet_khau'],
			'g'  => (float) $r['thanh_tien'],
			'o'  => (int) $r['so_hd'],
			'q'  => (float) $r['so_mon'],
			'h'  => khh_dt_json( $r['gio'], array_fill( 0, 24, 0 ) ),
			'p'  => khh_dt_json( $r['pttt'], array() ),
			's'  => khh_dt_json( $r['nguon'], array() ),
			'i'  => khh_dt_json( $r['mon'], array() ),
		);
	}
	return array( 'ngay' => array_values( $theo_ngay ) );
}

function khh_dt_json( $raw, $mac_dinh ) {
	$v = json_decode( (string) $raw, true );
	return is_array( $v ) ? $v : $mac_dinh;
}

function khh_dt_rest_nap( $req ) {
	if ( empty( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return new WP_Error( 'khh_dt_file', 'Chưa chọn file.', array( 'status' => 400 ) );
	}
	$loi = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : 0;
	if ( UPLOAD_ERR_OK !== $loi ) {
		$noi = ( UPLOAD_ERR_INI_SIZE === $loi || UPLOAD_ERR_FORM_SIZE === $loi )
			? 'File lớn hơn mức máy chủ cho tải lên (' . size_format( wp_max_upload_size() ) . '). Anh xuất bản CSV, hoặc xuất từng ngày cho nhẹ.'
			: 'Tải file lên không xong (mã ' . $loi . ').';
		return new WP_Error( 'khh_dt_file', $noi, array( 'status' => 400 ) );
	}

	$ten  = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : 'file'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	/* 'pos' = báo cáo bán hàng FABi · 'sao_ke' = sao kê ngân hàng. Hai loại đi chung một đường
	   tải lên (cùng cách cắt mẩu 1 MB để qua giới hạn của hosting), chỉ khác người đọc ở cuối. */
	$loai = 'sao_ke' === (string) $req->get_param( 'loai' ) ? 'sao_ke' : 'pos';
	$duoi = strtolower( pathinfo( $ten, PATHINFO_EXTENSION ) );
	if ( ! in_array( $duoi, array( 'xlsx', 'xlsm', 'csv', 'tsv', 'txt' ), true ) ) {
		return new WP_Error( 'khh_dt_file', 'Chỉ nhận .xlsx, .csv hoặc .tsv. File .xls đời cũ thì anh mở ra lưu lại thành .xlsx giúp em.', array( 'status' => 400 ) );
	}

	$tam = $_FILES['file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! is_uploaded_file( $tam ) ) {
		return new WP_Error( 'khh_dt_file', 'File tải lên không hợp lệ.', array( 'status' => 400 ) );
	}

	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$kq = khh_dt_phan_tich( $tam, $ten );
	if ( is_wp_error( $kq ) ) {
		return $kq;
	}
	$n = khh_dt_ghi_kho( $kq['ngay'] );

	$meta = array(
		'nguon'   => $ten,
		'nap_luc' => current_time( 'mysql' ),
		'ky'      => $kq['tom_tat']['ky'],
		'trang'   => $kq['tom_tat']['trang'],
		'boi'     => wp_get_current_user()->display_name,
	);
	update_option( 'khh_dt_meta', $meta, false );

	$kq['tom_tat']['da_ghi'] = $n;
	return $kq['tom_tat'];
}

/**
 * Nạp file theo từng mẩu nhỏ.
 *
 * Bản xuất một tuần của FABi nặng vài chục MB, trong khi hosting thường chặn
 * tải lên ở 2–32MB — gửi nguyên file là máy chủ cắt ngang và trả về trang HTML
 * báo lỗi. Ở đây trình duyệt cắt file thành mẩu 1MB gửi lần lượt, máy chủ nối
 * lại vào một file tạm NGOÀI thư mục web rồi mới đọc.
 */
function khh_dt_rest_nap_mau( $req ) {
	khh_dt_bat_loi();
	$khoa = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $req->get_param( 'khoa' ) ) );
	$phan = (int) $req->get_param( 'phan' );
	$tong = (int) $req->get_param( 'tong' );
	$ten  = sanitize_file_name( (string) $req->get_param( 'ten' ) );

	if ( strlen( $khoa ) < 8 || strlen( $khoa ) > 40 || $tong < 1 || $phan < 0 || $phan >= $tong ) {
		return new WP_Error( 'khh_dt_mau', 'Tham số mẩu không hợp lệ.', array( 'status' => 400 ) );
	}
	$duoi = strtolower( pathinfo( $ten, PATHINFO_EXTENSION ) );
	if ( ! in_array( $duoi, array( 'xlsx', 'xlsm', 'csv', 'tsv', 'txt' ), true ) ) {
		return new WP_Error( 'khh_dt_file', 'Chỉ nhận .xlsx, .csv hoặc .tsv. File .xls đời cũ thì anh mở ra lưu lại thành .xlsx giúp em.', array( 'status' => 400 ) );
	}
	if ( empty( $_FILES['mau']['tmp_name'] ) || ! is_uploaded_file( $_FILES['mau']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return new WP_Error( 'khh_dt_mau', 'Mẩu dữ liệu rỗng.', array( 'status' => 400 ) );
	}

	khh_dt_don_mau_cu();
	$dich = khh_dt_duong_mau( $khoa );

	if ( 0 === $phan && file_exists( $dich ) ) {
		wp_delete_file( $dich );
	}
	$vao = fopen( $_FILES['mau']['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput
	$ra  = fopen( $dich, 0 === $phan ? 'wb' : 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $vao || ! $ra ) {
		return new WP_Error( 'khh_dt_mau', 'Không ghi được file tạm trên máy chủ.', array( 'status' => 500 ) );
	}
	stream_copy_to_stream( $vao, $ra );
	fclose( $vao ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $ra );  // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( ! khh_dt_co_bang() ) {
		khh_dt_kich_hoat();                    // phòng khi lúc kích hoạt chưa tạo được bảng
	}

	if ( $phan < $tong - 1 ) {
		return array(
			'xong' => false,
			'phan' => $phan,
			'tong' => $tong,
		);
	}

	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	if ( 'sao_ke' === $loai ) {
		try {
			$kq = khh_dt_doc_sao_ke( $dich, $ten );
		} catch ( Throwable $t ) {
			wp_delete_file( $dich );
			return new WP_Error(
				'khh_dt_sk',
				'Đọc sao kê không xong: ' . $t->getMessage() . ' (' . basename( $t->getFile() ) . ' dòng ' . $t->getLine() . ')',
				array( 'status' => 500 )
			);
		}
		wp_delete_file( $dich );
		if ( is_wp_error( $kq ) ) {
			return $kq;
		}
		$n    = khh_dt_ghi_sao_ke( $kq['dong'] );
		$chua = khh_dt_sk_chua_gan();
		update_option(
			'khh_dt_meta_sk',
			array(
				'nguon'   => $ten,
				'nap_luc' => current_time( 'mysql' ),
				'boi'     => khh_dt_ten_ghi_so(),
			),
			false
		);
		return array(
			'xong'      => true,
			'loai'      => 'sao_ke',
			'da_ghi'    => $n,
			'so_dong'   => (int) $kq['so_dong'],
			'tien_ra'   => (int) $kq['tien_ra'],
			'bo_qua'    => (int) $kq['bo_qua'],
			'doan_dau'  => (int) $kq['doan_dau'],
			'chua_gan'  => $chua,
		);
	}

	try {
		$kq = khh_dt_phan_tich( $dich, $ten );
	} catch ( Throwable $t ) {                 // PHP 7+ bắt được cả Error lẫn Exception
		wp_delete_file( $dich );
		return new WP_Error(
			'khh_dt_doc',
			'Đọc file không xong: ' . $t->getMessage() . ' (' . basename( $t->getFile() ) . ' dòng ' . $t->getLine() . ')',
			array( 'status' => 500 )
		);
	}
	wp_delete_file( $dich );
	if ( is_wp_error( $kq ) ) {
		return $kq;
	}
	try {
		$n = khh_dt_ghi_kho( $kq['ngay'] );
	} catch ( Throwable $t ) {
		return new WP_Error(
			'khh_dt_ghi',
			'Ghi vào kho không xong: ' . $t->getMessage() . ' (' . basename( $t->getFile() ) . ' dòng ' . $t->getLine() . ')',
			array( 'status' => 500 )
		);
	}
	update_option(
		'khh_dt_meta',
		array(
			'nguon'   => $ten,
			'nap_luc' => current_time( 'mysql' ),
			'ky'      => $kq['tom_tat']['ky'],
			'trang'   => $kq['tom_tat']['trang'],
			'boi'     => wp_get_current_user()->display_name,
		),
		false
	);
	$kq['tom_tat']['xong']   = true;
	$kq['tom_tat']['da_ghi'] = $n;
	return $kq['tom_tat'];
}

/** File tạm để ngoài thư mục web, không ai tải về được. */
function khh_dt_duong_mau( $khoa ) {
	return trailingslashit( get_temp_dir() ) . 'khh-dt-' . $khoa . '.part';
}

/** Dọn mẩu bỏ dở quá một ngày. */
function khh_dt_don_mau_cu() {
	$ds = glob( trailingslashit( get_temp_dir() ) . 'khh-dt-*.part' );
	if ( ! $ds ) {
		return;
	}
	foreach ( $ds as $f ) {
		if ( filemtime( $f ) < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $f );
		}
	}
}

/** Ghi đè theo (ngày, cửa hàng): nạp lại cùng một ngày thì thay, không cộng dồn. */
function khh_dt_ghi_kho( $ds ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$luc  = time();
	$n    = 0;
	foreach ( $ds as $o ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $bang (ngay,cua_hang,pos_id,doanh_thu,chiet_khau,thanh_tien,so_hd,so_mon,so_ve,gio,pttt,nguon,mon,nap_luc)
				 VALUES (%s,%s,%s,%f,%f,%f,%d,%f,%f,%s,%s,%s,%s,%d)
				 ON DUPLICATE KEY UPDATE pos_id=VALUES(pos_id), doanh_thu=VALUES(doanh_thu),
				   chiet_khau=VALUES(chiet_khau), thanh_tien=VALUES(thanh_tien), so_hd=VALUES(so_hd),
				   so_mon=VALUES(so_mon), so_ve=VALUES(so_ve), gio=VALUES(gio), pttt=VALUES(pttt), nguon=VALUES(nguon),
				   mon=VALUES(mon), nap_luc=VALUES(nap_luc)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$o['ngay'],
				$o['cua_hang'],
				$o['pos_id'],
				$o['doanh_thu'],
				$o['chiet_khau'],
				$o['thanh_tien'],
				$o['so_hd'],
				$o['so_mon'],
				isset( $o['so_ve'] ) ? $o['so_ve'] : 0,
				wp_json_encode( array_values( $o['gio'] ) ),
				wp_json_encode( khh_dt_ds( $o['pttt'] ) ),
				wp_json_encode( khh_dt_ds( $o['nguon'] ) ),
				wp_json_encode( $o['mon'] ),
				$luc
			)
		);
		$n++;
	}
	return $n;
}

/** {'Tiền mặt': 123} → [{n:'Tiền mặt', r:123}] để tên có dấu chấm không thành khoá lạ. */
function khh_dt_ds( $m ) {
	$ra = array();
	foreach ( (array) $m as $k => $v ) {
		$ra[] = array(
			'n' => (string) $k,
			'r' => (float) $v,
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			return ( $b['r'] > $a['r'] ) ? 1 : -1;
		}
	);
	return $ra;
}

function khh_dt_rest_xoa() {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "TRUNCATE TABLE $bang" );
	delete_option( 'khh_dt_meta' );
	return array( 'ok' => true );
}

/* ------------------------------------------------------------------ *
 * Giao diện
 * ------------------------------------------------------------------ */

function khh_dt_cau_hinh_js() {
	/* ⚠️ KHÔNG hỏi phiên PIN ở đây. Hàm này chạy lúc DỰNG TRANG, mà thẻ phiên PIN đi kèm từng lượt
	   gọi REST (header) chứ không nằm trong trang — nên ở đây luôn thấy "chưa đăng nhập" kể cả khi
	   người ta đang mở màn. Ai đang xem thì hỏi `cau-hinh`. */
	return array(
		'rest'  => esc_url_raw( rest_url( 'khh-dt/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'ghi'   => khh_dt_duoc_ghi(),
	);
}

function khh_dt_nap_asset() {
	wp_enqueue_style( 'khh-dt', KHH_DT_URL . 'assets/doanh-thu.css', array(), KHH_DT_VERSION );
	wp_enqueue_script( 'khh-dt', KHH_DT_URL . 'assets/doanh-thu.js', array(), KHH_DT_VERSION, true );
	wp_localize_script( 'khh-dt', 'KHH_DT', khh_dt_cau_hinh_js() );
}

/** Khung HTML của báo cáo. */
function khh_dt_khung() {
	return '<div class="khh-dt" id="khhDt"><div class="khh-dt-tai">Đang tải số liệu…</div></div>';
}

/* Trang quản trị */
add_action( 'admin_menu', 'khh_dt_menu' );
function khh_dt_menu() {
	add_menu_page(
		'Doanh thu FABi',
		'Doanh thu FABi',
		'read',
		'khh-doanh-thu',
		'khh_dt_trang_quan_tri',
		'dashicons-chart-bar',
		4
	);
}

add_action( 'admin_enqueue_scripts', 'khh_dt_admin_asset' );
function khh_dt_admin_asset( $hook ) {
	if ( false !== strpos( $hook, 'khh-doanh-thu' ) ) {
		khh_dt_nap_asset();
	}
}

function khh_dt_trang_quan_tri() {
	echo '<div class="wrap khh-dt-wrap">';
	echo khh_dt_khung(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	khh_dt_o_duong_dan();
	echo '</div>';
}

/** Ô khai đường dẫn ngoài, nằm dưới báo cáo. */
function khh_dt_o_duong_dan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$goc = trailingslashit( home_url() );
	?>
	<div class="khh-dt-link">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="khh_dt_luu_slug">
			<?php wp_nonce_field( 'khh_dt_slug' ); ?>
			<label for="khh_dt_slug"><b>Đường link xem báo cáo</b></label>
			<div class="khh-dt-link-hang">
				<span class="khh-dt-goc"><?php echo esc_html( $goc ); ?></span>
				<input type="text" id="khh_dt_slug" name="slug" value="<?php echo esc_attr( khh_dt_slug() ); ?>"
					class="regular-text" <?php disabled( defined( 'KHH_DT_SLUG' ) ); ?>>
				<button type="submit" class="button">Lưu đường link</button>
				<a class="button button-primary" href="<?php echo esc_url( khh_dt_link() ); ?>" target="_blank" rel="noopener">Mở thử</a>
			</div>
			<p class="description">
				Gửi link này cho người cần xem. Ai mở cũng phải <b>đăng nhập tài khoản trên web</b> mới thấy số —
				đây là doanh thu nên em để vậy. Người có quyền Biên tập trở lên mới nạp và xoá được số liệu.
				<?php if ( defined( 'KHH_DT_SLUG' ) ) : ?>
					<br><em>Đang khai cứng bằng hằng số KHH_DT_SLUG trong wp-config.php nên không sửa ở đây được.</em>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<style>
		.khh-dt-link{margin-top:18px;padding:14px 16px;background:#fff;border:1px solid #dfddd8;border-radius:10px}
		.khh-dt-link-hang{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px}
		.khh-dt-goc{color:#7c8089;font-family:ui-monospace,Menlo,monospace}
		.khh-dt-link .description{margin-top:10px;max-width:760px}
	</style>
	<?php
}

add_action( 'admin_post_khh_dt_luu_slug', 'khh_dt_luu_slug' );
function khh_dt_luu_slug() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	check_admin_referer( 'khh_dt_slug' );
	$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	update_option( 'khh_dt_slug', $slug ? $slug : 'doanh-thu-hcm', false );
	delete_option( 'khh_dt_rw' );          // buộc nạp lại bảng đường dẫn
	wp_safe_redirect( admin_url( 'admin.php?page=khh-doanh-thu&luu=1' ) );
	exit;
}

/* Shortcode [khh_doanh_thu] */
add_shortcode( 'khh_doanh_thu', 'khh_dt_shortcode' );
function khh_dt_shortcode() {
	khh_dt_nap_asset();
	return khh_dt_khung();
}

/* Trang toàn màn hình: /?khh_doanh_thu=1 */
add_action( 'template_redirect', 'khh_dt_trang_rieng' );
function khh_dt_trang_rieng() {
	$bat = get_query_var( 'khh_doanh_thu' );
	if ( ! $bat && ! isset( $_GET['khh_doanh_thu'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	/* 🔴 KHÔNG `auth_redirect()` NỮA. Cửa hàng trưởng được đẩy sang KHÔNG có tài khoản WordPress —
	   đá họ sang wp-login là đá ra một cánh cửa họ không có chìa. Trang cứ mở, và màn tự hỏi PIN;
	   số liệu thì vẫn khoá sau REST, chưa có thẻ phiên là không lấy được gì. */
	nocache_headers();
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Doanh thu FABi — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700&family=Be+Vietnam+Pro:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( KHH_DT_URL . 'assets/doanh-thu.css?v=' . KHH_DT_VERSION ); ?>">
</head>
<body class="khh-dt-body">
<?php echo khh_dt_khung(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<script>window.KHH_DT=<?php echo wp_json_encode( khh_dt_cau_hinh_js() ); ?>;</script>
<script src="<?php echo esc_url( KHH_DT_URL . 'assets/doanh-thu.js?v=' . KHH_DT_VERSION ); ?>"></script>
</body>
</html>
	<?php
	exit;
}

/* Hiện thành một ứng dụng trong Nền tảng K&H, nếu plugin đó đang bật. */
add_action( 'khh_app_footer', 'khh_dt_gan_vao_nen_tang' );
function khh_dt_gan_vao_nen_tang() {
	?>
<link rel="stylesheet" href="<?php echo esc_url( KHH_DT_URL . 'assets/doanh-thu.css?v=' . KHH_DT_VERSION ); ?>">
<script>window.KHH_DT=<?php echo wp_json_encode( khh_dt_cau_hinh_js() ); ?>;</script>
<script src="<?php echo esc_url( KHH_DT_URL . 'assets/doanh-thu.js?v=' . KHH_DT_VERSION ); ?>"></script>
<script src="<?php echo esc_url( KHH_DT_URL . 'assets/gan-nen-tang.js?v=' . KHH_DT_VERSION ); ?>"></script>
	<?php
}

/* Liên kết "Mở báo cáo" ngay ở danh sách plugin. */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'khh_dt_lien_ket' );
function khh_dt_lien_ket( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=khh-doanh-thu' ) ) . '">Mở báo cáo</a>',
		'<a href="' . esc_url( khh_dt_link() ) . '" target="_blank" rel="noopener">Link ngoài</a>'
	);
	return $links;
}

/* ------------------------------------------------------------------ *
 * Đường API FABi — bật khi iPOS cấp Client ID / Secret Key / Token
 * ------------------------------------------------------------------ *
 *
 * Khoá để trong wp-config.php hoặc Cấu hình, KHÔNG viết vào code:
 *   define( 'KHH_DT_API_BASE',  'https://<máy chủ iPOS cấp>' );
 *   define( 'KHH_DT_API_PATH',  '/api/v1/sale/accounting' );
 *   define( 'KHH_DT_CLIENT_ID', '...' );
 *   define( 'KHH_DT_TOKEN',     '...' );
 *
 * Bật đồng bộ mỗi giờ: khh_dt_bat_dong_bo();  — tắt: khh_dt_tat_dong_bo();
 * Khi có tài liệu iPOS, chỗ duy nhất phải sửa là tên tham số ngày và chỗ lấy
 * mảng dòng trong JSON trả về.
 */

function khh_dt_api_cau_hinh() {
	return array(
		'base'   => defined( 'KHH_DT_API_BASE' ) ? KHH_DT_API_BASE : get_option( 'khh_dt_api_base', '' ),
		'path'   => defined( 'KHH_DT_API_PATH' ) ? KHH_DT_API_PATH : get_option( 'khh_dt_api_path', '/api/v1/sale/accounting' ),
		'client' => defined( 'KHH_DT_CLIENT_ID' ) ? KHH_DT_CLIENT_ID : get_option( 'khh_dt_client_id', '' ),
		'token'  => defined( 'KHH_DT_TOKEN' ) ? KHH_DT_TOKEN : get_option( 'khh_dt_api_token', '' ),
	);
}

function khh_dt_dong_bo_api( $tu = '', $den = '' ) {
	$c = khh_dt_api_cau_hinh();
	if ( empty( $c['base'] ) || empty( $c['token'] ) ) {
		return new WP_Error( 'khh_dt_api', 'Chưa có thông tin API FABi. Xin iPOS cấp Client ID / Secret Key / Token rồi khai vào wp-config.php.' );
	}
	$den = $den ? $den : current_time( 'Y-m-d' );
	$tu  = $tu ? $tu : $den;

	$url = rtrim( $c['base'], '/' ) . $c['path'] . '?from_date=' . rawurlencode( $tu ) . '&to_date=' . rawurlencode( $den );
	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $c['token'],
				'X-Client-Id'   => $c['client'],
				'Accept'        => 'application/json',
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$ma = wp_remote_retrieve_response_code( $res );
	if ( 200 !== $ma ) {
		return new WP_Error( 'khh_dt_api', 'API FABi trả mã ' . $ma );
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	$dong = array();
	foreach ( array( 'data', 'items', 'result', 'rows' ) as $k ) {
		if ( ! empty( $data[ $k ] ) && is_array( $data[ $k ] ) ) {
			$dong = $data[ $k ];
			break;
		}
	}
	if ( ! $dong ) {
		return array( 'so_dong' => 0, 'ghi_chu' => 'API không trả dòng nào cho khoảng ngày này.' );
	}

	$cot = array_keys( (array) $dong[0] );
	$map = khh_dt_do_cot( $cot );
	$gop = array( 'ngay' => array(), 'hd' => array(), 'so_dong' => 0, 'bo_qua' => 0, 'ky' => $tu . ' → ' . $den, 'trang' => 'API' );
	foreach ( $dong as $o ) {
		khh_dt_gop_dong( array_values( (array) $o ), $map, $gop );
	}
	$ds = array();
	foreach ( $gop['ngay'] as $ngay => $ds_ch ) {
		foreach ( $ds_ch as $ten => $x ) {
			arsort( $x['mon_r'] );
			$mon = array();
			$i   = 0;
			foreach ( $x['mon_r'] as $tm => $r ) {
				if ( $i++ >= 40 ) {
					break;
				}
				$mon[] = array( 'n' => $tm, 'g' => isset( $x['mon_g'][ $tm ] ) ? $x['mon_g'][ $tm ] : '', 'q' => $x['mon_q'][ $tm ], 'r' => $r );
			}
			$ds[] = array(
				'ngay' => $ngay, 'cua_hang' => $ten, 'pos_id' => $x['pos'],
				'doanh_thu' => $x['dt'], 'chiet_khau' => $x['ck'], 'thanh_tien' => $x['tt'],
				'so_hd' => $x['hd'], 'so_mon' => $x['sl'], 'gio' => $x['gio'],
				'pttt' => $x['pttt'], 'nguon' => $x['nguon'], 'mon' => $mon,
			);
		}
	}
	$n = khh_dt_ghi_kho( $ds );
	update_option(
		'khh_dt_meta',
		array( 'nguon' => 'API FABi', 'nap_luc' => current_time( 'mysql' ), 'ky' => $tu . ' → ' . $den ),
		false
	);
	return array( 'so_dong' => $gop['so_dong'], 'da_ghi' => $n );
}

add_action( 'khh_dt_cron_dong_bo', 'khh_dt_cron_chay' );
function khh_dt_cron_chay() {
	$hom_qua = gmdate( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	khh_dt_dong_bo_api( $hom_qua, current_time( 'Y-m-d' ) );
}

function khh_dt_bat_dong_bo() {
	if ( ! wp_next_scheduled( 'khh_dt_cron_dong_bo' ) ) {
		wp_schedule_event( time() + 60, 'hourly', 'khh_dt_cron_dong_bo' );
	}
	return true;
}

function khh_dt_tat_dong_bo() {
	$ts = wp_next_scheduled( 'khh_dt_cron_dong_bo' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'khh_dt_cron_dong_bo' );
	}
	return true;
}

register_deactivation_hook( __FILE__, 'khh_dt_tat_dong_bo' );
