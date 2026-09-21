<?php
/**
 * Plugin Name:       Nền tảng K&H
 * Plugin URI:        https://khh.vn/
 * Description:       Nền tảng quản trị nội bộ 16 ứng dụng: dự án & công việc, báo cáo dự án, đề xuất, quy trình, hồ sơ nhân sự, chấm công, bảng công, nghỉ phép, bảng lương (bảo hiểm + thuế TNCN), thông báo, tri thức, họp, trò chuyện, bảng tin, đặt tài nguyên.
 * Version:           1.22.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            K&H
 * Text Domain:       khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KHH_VERSION', '1.22.0' );
define( 'KHH_FILE', __FILE__ );
define( 'KHH_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHH_URL', plugin_dir_url( __FILE__ ) );

/** Đăng nhập nhanh bằng mã PIN — tuỳ chọn, mặc định tắt. */
require_once KHH_DIR . 'pin-login.php';

/** Đăng nhập thử với tư cách người khác — công cụ kiểm thử cho Quản trị viên. */
require_once KHH_DIR . 'switch-user.php';

/** Nhập hồ sơ nhân sự từ hệ thống cũ (CSV) và xuất ra CSV. */
require_once KHH_DIR . 'xlsx.php';
require_once KHH_DIR . 'import.php';

// Cấp tài khoản bằng tên đăng nhập + mật khẩu giao tận tay.
require_once KHH_DIR . 'cap-tai-khoan.php';

// Màn đăng nhập ngay trên nền tảng, không đá sang wp-login.php.
require_once KHH_DIR . 'dang-nhap.php';

// Nhân viên tự xem hồ sơ của mình và tự đổi mật khẩu (bật/tắt ở trang Cấu hình).
require_once KHH_DIR . 'tai-khoan-cua-toi.php';

/* Nhập chấm công từ cơ sở dữ liệu của phần mềm cũ chung máy chủ. */
require_once KHH_DIR . 'nhap-cham-cong.php';

/* Nối thẳng với plugin Chấm Công (K&H) cùng site, không cần khai. */
require_once KHH_DIR . 'noi-vhcc.php';

/* ───────────────────────────────────────────────────────────────────────────────────────────
 * TỰ CẬP NHẬT TỪ GITHUB RELEASES (1.18.0).
 *
 * Cùng một lớp đang chạy ở Chấm Công / Chi Phí, chỉ khác tiền tố tag (`khh-platform-v…`) và ô
 * nhớ. Khoá GitHub dùng CHUNG một Option với các plugin kia, nên khai một lần là cả nhà cùng
 * thấy bản mới; chưa khai thì nó im lặng không làm gì.
 *
 * Và khai thêm một dòng vào bảng của trang khmatrix.com/it — xem `khai_ds()` trong lớp.
 * ─────────────────────────────────────────────────────────────────────────────────────────── */
require_once KHH_DIR . 'includes/class-khh-tu-cap-nhat.php';
KHH_TuCapNhat::init();

/** Các nhóm dữ liệu app dùng. Ngoài danh sách này thì từ chối. */
function khh_collections() {
	return array(
		'projects', 'tasks', 'requests', 'reqtypes', 'flows', 'jobs',
		'staff', 'depts', 'timeoffs', 'attendance', 'payrolls',
		'posts', 'meetings', 'rooms', 'devices',
		'channels', 'messages', 'feed', 'resources', 'bookings', 'settings',
	);
}

/** Nhóm chỉ Quản trị trở lên mới được ghi. */
function khh_restricted_collections() {
	return array( 'staff', 'depts', 'payrolls', 'settings', 'devices' );
}

function khh_table() {
	global $wpdb;
	return $wpdb->prefix . 'khh_docs';
}

/* ------------------------------------------------------------------ *
 * Cài đặt: tạo bảng và nạp dữ liệu ví dụ lần đầu
 * ------------------------------------------------------------------ */

register_activation_hook( __FILE__, 'khh_activate' );
function khh_activate() {
	global $wpdb;
	$table   = khh_table();
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $table (
			coll varchar(40) NOT NULL,
			doc_id varchar(64) NOT NULL,
			payload longtext NOT NULL,
			deleted tinyint(1) NOT NULL DEFAULT 0,
			updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (coll,doc_id),
			KEY updated_at (updated_at)
		) $charset;"
	);

	khh_register_roles();

	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore
	if ( 0 === $count ) {
		khh_seed_demo();
	}
	update_option( 'khh_version', KHH_VERSION );
}

/** Nạp dữ liệu ví dụ từ seed-data.json. */
function khh_seed_demo() {
	global $wpdb;
	$file = KHH_DIR . 'seed-data.json';
	if ( ! file_exists( $file ) ) {
		return 0;
	}
	$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return 0;
	}
	$allowed = khh_collections();
	$now     = (int) round( microtime( true ) * 1000 );
	$n       = 0;
	foreach ( $data as $coll => $docs ) {
		if ( ! in_array( $coll, $allowed, true ) || ! is_array( $docs ) ) {
			continue;
		}
		foreach ( $docs as $doc ) {
			if ( empty( $doc['id'] ) || ! isset( $doc['body'] ) ) {
				continue;
			}
			$wpdb->replace( // phpcs:ignore
				khh_table(),
				array(
					'coll'       => $coll,
					'doc_id'     => (string) $doc['id'],
					'payload'    => wp_json_encode( $doc['body'] ),
					'deleted'    => 0,
					'updated_at' => $now,
					'updated_by' => 0,
				),
				array( '%s', '%s', '%s', '%d', '%d', '%d' )
			);
			$n++;
		}
	}
	return $n;
}

/* ------------------------------------------------------------------ *
 * Vai trò
 * ------------------------------------------------------------------ */

/**
 * Ba vai trò WordPress riêng cho nền tảng. Chỉ có quyền đọc trang và tải tệp
 * (cần cho ảnh chấm công, tệp đính kèm trong chat) — không đụng gì tới website.
 */
function khh_khh_roles() {
	return array(
		'khh_staff'   => 'Nhân viên K&H',
		'khh_manager' => 'Quản lý K&H',
		'khh_admin'   => 'Quản trị K&H',
	);
}

/** Quyền tối thiểu mỗi vai trò nền tảng phải có. */
function khh_role_caps() {
	return array(
		'read'         => true,
		'upload_files' => true,
	);
}

function khh_register_roles() {
	$caps = khh_role_caps();
	foreach ( khh_khh_roles() as $slug => $nhan ) {
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $nhan, $caps );
			continue;
		}
		/* add_role() KHÔNG làm gì khi vai trò đã tồn tại. Bản cài từ trước, khi
		   danh sách quyền còn thiếu, sẽ thiếu mãi — nên bổ sung từng quyền tay. */
		foreach ( array_keys( $caps ) as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}
}

/**
 * Nâng cấp khi thay bản mới: người dùng chép tệp đè lên chứ ít khi tắt rồi bật
 * lại plugin, mà register_activation_hook chỉ chạy lúc bật. Không có chỗ này
 * thì quyền mới thêm vào vai trò sẽ không bao giờ tới được bản đang chạy.
 */
add_action( 'plugins_loaded', 'khh_nang_cap' );
function khh_nang_cap() {
	if ( get_option( 'khh_version' ) === KHH_VERSION ) {
		return;
	}
	khh_register_roles();
	update_option( 'khh_version', KHH_VERSION );
}

/** Vai trò nền tảng → vai trò WordPress khi tạo tài khoản. */
function khh_wp_role_for( $khh_role ) {
	$map = array(
		'owner'   => 'administrator',
		'admin'   => 'khh_admin',
		'manager' => 'khh_manager',
		'staff'   => 'khh_staff',
	);
	return isset( $map[ $khh_role ] ) ? $map[ $khh_role ] : 'khh_staff';
}

/** owner | admin | manager | staff — lấy từ tài khoản WordPress. */
function khh_user_role( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 'staff';
	}
	$override = get_user_meta( $user_id, 'khh_role', true );
	if ( in_array( $override, array( 'owner', 'admin', 'manager', 'staff' ), true ) ) {
		return $override;
	}
	$user = get_userdata( $user_id );
	if ( $user && is_array( $user->roles ) ) {
		if ( in_array( 'khh_admin', $user->roles, true ) ) {
			return 'admin';
		}
		if ( in_array( 'khh_manager', $user->roles, true ) ) {
			return 'manager';
		}
		if ( in_array( 'khh_staff', $user->roles, true ) ) {
			return 'staff';
		}
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		return 'owner';
	}
	if ( user_can( $user_id, 'edit_others_posts' ) ) {
		return 'admin';
	}
	if ( user_can( $user_id, 'publish_posts' ) ) {
		return 'manager';
	}
	return 'staff';
}

function khh_can_write( $coll ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( in_array( $coll, khh_restricted_collections(), true ) ) {
		return in_array( khh_user_role(), array( 'owner', 'admin' ), true );
	}
	return true;
}

/* ------------------------------------------------------------------ *
 * REST API
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'khh_rest_routes' );
function khh_rest_routes() {
	register_rest_route(
		'khh/v1',
		'/state',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_rest_state',
			'permission_callback' => 'is_user_logged_in',
			'args'                => array(
				'since' => array(
					'sanitize_callback' => 'absint',
					'default'           => 0,
				),
			),
		)
	);
	register_rest_route(
		'khh/v1',
		'/doc',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_rest_save',
			'permission_callback' => 'is_user_logged_in',
		)
	);
	register_rest_route(
		'khh/v1',
		'/bulk',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_rest_bulk',
			'permission_callback' => 'is_user_logged_in',
		)
	);
	register_rest_route(
		'khh/v1',
		'/delete',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_rest_delete',
			'permission_callback' => 'is_user_logged_in',
		)
	);
}

function khh_clean_id( $id ) {
	$id = (string) $id;
	return preg_match( '/^[A-Za-z0-9_\-.:@+]{1,64}$/', $id ) ? $id : '';
}

function khh_rest_state( $request ) {
	global $wpdb;
	$since = (int) $request->get_param( 'since' );
	$table = khh_table();

	/* Chấm công đọc thẳng từ phần mềm cũ nếu có khai nguồn — không có bản sao
	   nào trong bảng của nền tảng, nên nhánh này thay hẳn dữ liệu đã lưu. */
	$song = function_exists( 'khh_cc_song' ) ? khh_cc_song() : null;

	$rows = $wpdb->get_results( // phpcs:ignore
		$wpdb->prepare( "SELECT coll, doc_id, payload, deleted FROM $table WHERE updated_at > %d", $since ), // phpcs:ignore
		ARRAY_A
	);

	$docs    = array();
	$deleted = array();
	foreach ( (array) $rows as $row ) {
		if ( (int) $row['deleted'] === 1 ) {
			$deleted[] = array(
				'coll' => $row['coll'],
				'id'   => $row['doc_id'],
			);
			continue;
		}
		$body = json_decode( $row['payload'], true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		/* Bản ghi chấm công đã lưu được gộp sẵn vào bản đọc thẳng bên dưới,
		   gửi thêm ở đây nữa thì đè mất phần đọc từ phần mềm cũ. */
		if ( $song && 'attendance' === $row['coll'] ) {
			continue;
		}
		$body['id'] = $row['doc_id'];
		if ( ! isset( $docs[ $row['coll'] ] ) ) {
			$docs[ $row['coll'] ] = array();
		}
		$docs[ $row['coll'] ][] = $body;
	}

	/* Cơ sở còn trống trong hồ sơ thì lấy theo sổ nhân viên bên Chấm Công (K&H),
	   để Bảng công cơ sở chia đúng mà không bắt ai gán tay hàng trăm người. */
	if ( ! empty( $docs['staff'] ) && function_exists( 'khh_vhcc_dien_coso' ) ) {
		$docs['staff'] = khh_vhcc_dien_coso( $docs['staff'] );
	}

	$now = (int) round( microtime( true ) * 1000 );
	if ( $song && $song['ver'] > $since ) {
		/* Chỉ gửi khi nội dung thật sự đổi — giao diện hỏi mỗi 8 giây, gửi lại
		   cả tháng công mỗi lần thì tốn băng thông vô ích. */
		$docs['attendance'] = $song['docs'];
		$now                = max( $now, (int) $song['ver'] );
	}

	return rest_ensure_response(
		array(
			'now'     => $now,
			'docs'    => (object) $docs,
			'deleted' => $deleted,
		)
	);
}

function khh_rest_save( $request ) {
	global $wpdb;
	$coll = (string) $request->get_param( 'coll' );
	$id   = khh_clean_id( $request->get_param( 'id' ) );
	$data = $request->get_param( 'data' );

	if ( ! in_array( $coll, khh_collections(), true ) || '' === $id ) {
		return new WP_Error( 'khh_bad_request', 'Nhóm dữ liệu hoặc mã bản ghi không hợp lệ.', array( 'status' => 400 ) );
	}
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'khh_bad_request', 'Nội dung bản ghi phải là một object.', array( 'status' => 400 ) );
	}
	if ( ! khh_can_write( $coll ) ) {
		return new WP_Error( 'khh_forbidden', 'Tài khoản của bạn không có quyền ghi vào mục này.', array( 'status' => 403 ) );
	}

	$json = wp_json_encode( $data );
	if ( strlen( $json ) > 900000 ) {
		return new WP_Error( 'khh_too_big', 'Bản ghi quá lớn.', array( 'status' => 413 ) );
	}

	$wpdb->replace( // phpcs:ignore
		khh_table(),
		array(
			'coll'       => $coll,
			'doc_id'     => $id,
			'payload'    => $json,
			'deleted'    => 0,
			'updated_at' => (int) round( microtime( true ) * 1000 ),
			'updated_by' => get_current_user_id(),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%d' )
	);

	/* Sửa tay ngày công thì bỏ bộ nhớ đệm đọc thẳng, để thấy ngay. */
	if ( function_exists( 'khh_cc_quen' ) ) {
		khh_cc_quen( $coll );
	}

	return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * Ghi nhiều bản ghi trong một lượt gọi.
 *
 * Dùng khi đổi tên một bộ phận có hàng trăm nhân sự: thay vì gọi /doc mấy trăm
 * lần, gửi một lô tối đa 200 bản ghi. Quyền ghi vẫn xét đúng như /doc.
 */
function khh_rest_bulk( $request ) {
	global $wpdb;
	$coll = (string) $request->get_param( 'coll' );
	$docs = $request->get_param( 'docs' );

	if ( ! in_array( $coll, khh_collections(), true ) ) {
		return new WP_Error( 'khh_bad_request', 'Nhóm dữ liệu không hợp lệ.', array( 'status' => 400 ) );
	}
	if ( ! is_array( $docs ) || ! $docs ) {
		return new WP_Error( 'khh_bad_request', 'Không có bản ghi nào để lưu.', array( 'status' => 400 ) );
	}
	if ( count( $docs ) > 200 ) {
		return new WP_Error( 'khh_too_many', 'Mỗi lượt chỉ lưu được tối đa 200 bản ghi.', array( 'status' => 413 ) );
	}
	if ( ! khh_can_write( $coll ) ) {
		return new WP_Error( 'khh_forbidden', 'Tài khoản của bạn không có quyền ghi vào mục này.', array( 'status' => 403 ) );
	}

	$now   = (int) round( microtime( true ) * 1000 );
	$me    = get_current_user_id();
	$saved = 0;
	$bad   = array();

	foreach ( $docs as $doc ) {
		$id   = isset( $doc['id'] ) ? khh_clean_id( $doc['id'] ) : '';
		$data = isset( $doc['data'] ) ? $doc['data'] : null;
		if ( '' === $id || ! is_array( $data ) ) {
			$bad[] = $id;
			continue;
		}
		$json = wp_json_encode( $data );
		if ( strlen( $json ) > 900000 ) {
			$bad[] = $id;
			continue;
		}
		$wpdb->replace( // phpcs:ignore
			khh_table(),
			array(
				'coll'       => $coll,
				'doc_id'     => $id,
				'payload'    => $json,
				'deleted'    => 0,
				'updated_at' => $now,
				'updated_by' => $me,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d' )
		);
		$saved++;
	}

	if ( function_exists( 'khh_cc_quen' ) ) {
		khh_cc_quen( $coll );
	}

	return rest_ensure_response(
		array(
			'ok'      => true,
			'saved'   => $saved,
			'skipped' => $bad,
		)
	);
}

function khh_rest_delete( $request ) {
	global $wpdb;
	$coll = (string) $request->get_param( 'coll' );
	$id   = khh_clean_id( $request->get_param( 'id' ) );

	if ( ! in_array( $coll, khh_collections(), true ) || '' === $id ) {
		return new WP_Error( 'khh_bad_request', 'Nhóm dữ liệu hoặc mã bản ghi không hợp lệ.', array( 'status' => 400 ) );
	}
	if ( ! khh_can_write( $coll ) ) {
		return new WP_Error( 'khh_forbidden', 'Tài khoản của bạn không có quyền xoá trong mục này.', array( 'status' => 403 ) );
	}

	$wpdb->update( // phpcs:ignore
		khh_table(),
		array(
			'deleted'    => 1,
			'payload'    => '{}',
			'updated_at' => (int) round( microtime( true ) * 1000 ),
			'updated_by' => get_current_user_id(),
		),
		array(
			'coll'   => $coll,
			'doc_id' => $id,
		),
		array( '%d', '%s', '%d', '%d' ),
		array( '%s', '%s' )
	);

	if ( function_exists( 'khh_cc_quen' ) ) {
		khh_cc_quen( $coll );
	}

	return rest_ensure_response( array( 'ok' => true ) );
}

/* ------------------------------------------------------------------ *
 * Giao diện ứng dụng
 * ------------------------------------------------------------------ */

function khh_scripts() {
	return array(
		'core', 'charts', 'home', 'wework', 'baocao', 'request', 'workflow', 'hrm',
		'attendance', 'leave', 'payroll', 'ketoan', 'info', 'message', 'square', 'booking',
	);
}

/**
 * Ứng dụng kế toán đang cài trên site này — nền tảng nhúng lại, không gọi vào ruột chúng.
 *
 * "Ủy nhiệm chi & Công nợ" và "Báo cáo chi phí" là plugin RIÊNG, có thể có hoặc không.
 * Nhận diện bằng lớp PHP của chúng chứ KHÔNG đoán đường dẫn: người dùng đổi slug trong
 * cài đặt của plugin kia thì nền tảng vẫn trỏ đúng, và site chưa cài thì không khai ứng
 * dụng nào — khỏi bày một ô bấm vào ra 404.
 *
 * Muốn thêm một app kế toán nữa thì chỉ cần thêm một khối như dưới.
 */
function khh_ketoan_ung_dung() {
	$ds = array();

	if ( class_exists( 'KHUNC_App' ) && method_exists( 'KHUNC_App', 'app_url' ) ) {
		$ds[] = array(
			'id'   => 'unc',
			'ten'  => 'Ủy nhiệm chi & Công nợ',
			'mo'   => 'Khoản nào đã đi tiền, công nợ nhà cung cấp',
			'url'  => esc_url_raw( KHUNC_App::app_url() ),
			'mau'  => '#1D5B8F',
			'icon' => 'unc',
		);
	}

	if ( class_exists( 'KHBC_App' ) && method_exists( 'KHBC_App', 'app_url' ) ) {
		$ds[] = array(
			'id'   => 'chiphi',
			'ten'  => 'Báo cáo chi phí',
			'mo'   => 'Phân bổ chi phí ra File tổng báo cáo',
			'url'  => esc_url_raw( KHBC_App::app_url( 'app' ) ),
			'mau'  => '#1F6F5F',
			'icon' => 'report',
		);
	}

	return $ds;
}

function khh_api_config() {
	$user = wp_get_current_user();
	return array(
		'rest'  => esc_url_raw( rest_url( 'khh/v1/' ) ),
		'media' => esc_url_raw( rest_url( 'wp/v2/media' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'me'    => $user ? $user->display_name : '',
		'title' => $user ? ( get_user_meta( $user->ID, 'khh_title', true ) ? get_user_meta( $user->ID, 'khh_title', true ) : ucfirst( implode( ', ', $user->roles ) ) ) : '',
		'role'  => khh_user_role(),
		/* Khối Kế toán — chỉ Chủ sở hữu và Quản trị thấy (khai `vai` ở assets/ketoan.js). */
		'ketoan' => khh_ketoan_ung_dung(),
		'login' => $user ? $user->user_login : '',
		/* Hồ sơ nhân sự gắn với tài khoản này. Có mã thì hộp "Tài khoản của bạn" mở đúng
		   hồ sơ ngay cả khi tên hiển thị bị gõ lệch; không có thì giao diện dò theo tên. */
		'staffId' => $user ? (string) get_user_meta( $user->ID, 'khh_staff_id', true ) : '',
		/* Cấu hình "Tài khoản của nhân viên" — xem tai-khoan-cua-toi.php. */
		'tuXem'   => khh_tk_cfg( 'self_profile' ) ? 1 : 0,
		'xemLuong' => khh_tk_cfg( 'self_profile_pay' ) ? 1 : 0,
		'tuDoiMk' => khh_tk_cfg( 'self_password' ) ? 1 : 0,
		'mkMin'   => khh_tk_do_dai_min(),
		/* Giới hạn tải tệp thật của hosting — giao diện lấy số này để báo trước,
		   khỏi để người dùng chọn tệp xong mới biết là quá nặng. */
		'maxUpload' => (int) wp_max_upload_size(),
		'canUpload' => current_user_can( 'upload_files' ) ? 1 : 0,
		/* Nút Thoát trong hộp "Tài khoản của bạn". Có mã chống giả mạo nên
		   không ai ép người khác đăng xuất bằng một đường dẫn gửi qua chat.
		   KHÔNG dùng wp_nonce_url() ở đây: hàm đó bọc & thành &#038; cho HTML,
		   mà chuỗi này đi thẳng vào JavaScript rồi gán cho location.href —
		   dấu & bị bọc thì tham số thành "amp;_wpnonce" và WordPress báo hết hạn. */
		'out'   => add_query_arg(
			array(
				'khh_thoat' => 1,
				'_wpnonce'  => wp_create_nonce( 'khh_thoat' ),
			),
			home_url( '/' )
		),
	);
}

/** Khung HTML của app (không có thẻ html/head/body). */
function khh_app_markup() {
	ob_start();
	?>
	<div class="scrim" id="scrim"></div>
	<div class="wk" id="wk">
		<nav class="iconrail" id="iconrail" aria-label="Ứng dụng"></nav>
		<div id="appRoot" style="display:contents"></div>
	</div>
	<aside class="notif" id="notif" aria-label="Thông báo">
		<div class="notif-h"><h3>Thông báo</h3>
			<button class="icon-btn" id="notifClose" type="button" aria-label="Đóng">&times;</button></div>
		<div class="notif-b" id="notifBody"></div>
	</aside>
	<datalist id="dl_people"></datalist>
	<?php
	return ob_get_clean();
}

/** Trang app toàn màn hình: /?khh_app=1 */
add_action( 'template_redirect', 'khh_maybe_render_app' );
function khh_maybe_render_app() {
	if ( ! isset( $_GET['khh_app'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! is_user_logged_in() ) {
		$loi = khh_login_xu_ly();          /* gửi form xong mà đúng thì hàm này tự chuyển trang */
		khh_login_screen( $loi );
		exit;
	}
	nocache_headers();
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — Nền tảng K&amp;H</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( KHH_URL . 'assets/app.css?v=' . KHH_VERSION ); ?>">
<style>body{margin:0}img{max-width:100%}[hidden]{display:none!important}</style>
</head>
<body>
<?php echo khh_app_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<script>window.KH_API=<?php echo wp_json_encode( khh_api_config() ); ?>;</script>
<?php foreach ( khh_scripts() as $s ) : ?>
<script src="<?php echo esc_url( KHH_URL . 'assets/' . $s . '.js?v=' . KHH_VERSION ); ?>"></script>
<?php endforeach; ?>
<script>window.APP.boot();</script>
<?php do_action( 'khh_app_footer' ); ?>
</body>
</html>
	<?php
	exit;
}

/** Shortcode [khh_platform height="820px"] để nhúng vào một trang bất kỳ. */
add_shortcode( 'khh_platform', 'khh_shortcode' );
function khh_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'height' => '820px' ), $atts, 'khh_platform' );
	if ( ! is_user_logged_in() ) {
		return '<p>Bạn cần <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">đăng nhập</a> để dùng Nền tảng K&amp;H.</p>';
	}
	return '<iframe src="' . esc_url( home_url( '/?khh_app=1' ) ) . '" title="Nền tảng K&amp;H" '
		. 'style="width:100%;height:' . esc_attr( $atts['height'] ) . ';border:1px solid #e4e8ed;border-radius:4px" '
		. 'allow="camera"></iframe>';
}

/* ------------------------------------------------------------------ *
 * Trang quản trị
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_admin_menu' );
function khh_admin_menu() {
	add_menu_page(
		'Nền tảng K&H',
		'Nền tảng K&H',
		'read',
		'khh-platform',
		'khh_admin_page',
		'dashicons-screenoptions',
		3
	);
}

add_action( 'admin_post_khh_seed', 'khh_admin_seed' );
function khh_admin_seed() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'khh_seed' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$n = khh_seed_demo();
	wp_safe_redirect( admin_url( 'admin.php?page=khh-platform&seeded=' . (int) $n ) );
	exit;
}

function khh_admin_page() {
	global $wpdb;
	$table = khh_table();
	$rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE deleted = 0" ); // phpcs:ignore
	$url   = home_url( '/?khh_app=1' );
	$role  = khh_user_role();
	$names = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H</h1>
		<?php if ( isset( $_GET['capquyen'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p>Đã cấp quyền tải tệp cho ba vai trò nền tảng.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['seeded'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p>Đã nạp <?php echo (int) $_GET['seeded']; // phpcs:ignore ?> bản ghi ví dụ.</p></div>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Mở toàn màn hình</a>
			Bạn đang đăng nhập là <strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong>
			— quyền <strong><?php echo esc_html( $names[ $role ] ); ?></strong>.
		</p>
		<p>
			Dữ liệu: <strong><?php echo (int) $rows; ?></strong> bản ghi trong bảng <code><?php echo esc_html( $table ); ?></code>.
			Nhúng vào trang bất kỳ bằng shortcode <code>[khh_platform]</code>.
		</p>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px">
			<input type="hidden" name="action" value="khh_seed">
			<?php wp_nonce_field( 'khh_seed' ); ?>
			<button class="button">Nạp lại dữ liệu ví dụ</button>
			<span class="description">Ghi đè các bản ghi ví dụ, không đụng dữ liệu bạn tự tạo.</span>
		</form>
		<?php endif; ?>
		<?php khh_chan_doan_tep(); ?>
		<iframe src="<?php echo esc_url( $url ); ?>" title="Nền tảng K&amp;H"
			style="width:100%;height:calc(100vh - 220px);min-height:560px;border:1px solid #dcdcde;border-radius:4px;background:#fff"
			allow="camera"></iframe>
	</div>
	<?php
}

add_action( 'admin_post_khh_cap_quyen_tep', 'khh_cap_quyen_tep' );
function khh_cap_quyen_tep() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'khh_cap_quyen_tep' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	khh_register_roles();
	wp_safe_redirect( admin_url( 'admin.php?page=khh-platform&capquyen=1' ) );
	exit;
}

/**
 * Tự soát khả năng tải tệp lên của máy chủ.
 *
 * Người dùng báo "Không tải được tệp lên" mà không biết vướng ở đâu, nên bày ra
 * đúng ba thứ quyết định việc đó: quyền của tài khoản, thư mục kho tệp có ghi
 * được không, và mức dung lượng tối đa PHP cho phép.
 */
function khh_chan_doan_tep() {
	$quyen = current_user_can( 'upload_files' );
	$dir   = wp_upload_dir();
	$loi   = ! empty( $dir['error'] ) ? $dir['error'] : '';
	$ghi   = '' === $loi && ! empty( $dir['basedir'] ) && wp_is_writable( $dir['basedir'] );
	$muc   = (int) wp_max_upload_size();
	$thieu = array();
	foreach ( khh_khh_roles() as $slug => $nhan ) {
		$r = get_role( $slug );
		if ( ! $r || ! $r->has_cap( 'upload_files' ) ) {
			$thieu[] = $nhan;
		}
	}
	$ok    = $quyen && $ghi && ! $thieu && $muc > 0;
	$dong  = function ( $tot, $nhan, $chi_tiet ) {
		printf(
			'<li style="margin:0 0 6px"><span style="color:%s;font-weight:600">%s</span> %s <span style="color:#666">%s</span></li>',
			$tot ? '#1a7f37' : '#b32d2e',
			$tot ? '&#10003;' : '&#10007;',
			esc_html( $nhan ),
			esc_html( $chi_tiet )
		);
	};
	?>
	<div class="notice <?php echo $ok ? 'notice-success' : 'notice-warning'; ?>" style="padding:10px 14px">
		<p style="margin:0 0 8px"><strong>Đính kèm ảnh / PDF:</strong>
			<?php echo $ok ? 'máy chủ đã sẵn sàng.' : 'còn vướng, xem bên dưới.'; ?></p>
		<ul style="margin:0 0 4px 4px;list-style:none">
			<?php
			$dong(
				$quyen,
				'Quyền tải tệp của tài khoản bạn:',
				$quyen ? 'có' : 'không có — nhờ quản trị nâng vai trò WordPress lên Cộng tác viên trở lên'
			);
			$dong(
				$ghi,
				'Thư mục kho tệp:',
				$ghi ? $dir['basedir'] : ( $loi ? $loi : 'không ghi được vào ' . ( isset( $dir['basedir'] ) ? $dir['basedir'] : '?' ) )
			);
			$dong(
				! $thieu,
				'Quyền tải tệp của vai trò nhân viên:',
				$thieu ? 'thiếu ở ' . implode( ', ', $thieu ) . ' — bấm nút bên dưới để cấp'
					: 'đủ cho cả ba vai trò'
			);
			$dong(
				$muc > 0,
				'Dung lượng tối đa mỗi tệp:',
				size_format( $muc ) . ' (php upload_max_filesize=' . (string) ini_get( 'upload_max_filesize' )
					. ', post_max_size=' . (string) ini_get( 'post_max_size' ) . ')'
			);
			?>
		</ul>
		<?php if ( $thieu && current_user_can( 'manage_options' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:8px 0 0">
				<input type="hidden" name="action" value="khh_cap_quyen_tep">
				<?php wp_nonce_field( 'khh_cap_quyen_tep' ); ?>
				<button class="button">Cấp quyền tải tệp cho các vai trò</button>
			</form>
		<?php endif; ?>
		<?php if ( ! $ghi || $muc <= 0 ) : ?>
			<p style="margin:8px 0 0" class="description">
				Hai mục dưới đây phải sửa ở phía host: bật quyền ghi cho thư mục
				<code>wp-content/uploads</code>, hoặc nâng <code>upload_max_filesize</code> và
				<code>post_max_size</code> trong PHP.
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/* ------------------------------------------------------------------ *
 * Hồ sơ người dùng: vai trò và chức danh trong nền tảng
 * ------------------------------------------------------------------ */

add_action( 'show_user_profile', 'khh_user_fields' );
add_action( 'edit_user_profile', 'khh_user_fields' );
function khh_user_fields( $user ) {
	$role  = get_user_meta( $user->ID, 'khh_role', true );
	$title = get_user_meta( $user->ID, 'khh_title', true );
	$opts  = array(
		''        => 'Theo vai trò WordPress',
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	?>
	<h2>Nền tảng K&amp;H</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="khh_role">Vai trò trong nền tảng</label></th>
			<td><select name="khh_role" id="khh_role">
				<?php foreach ( $opts as $k => $v ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $role, $k ); ?>><?php echo esc_html( $v ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">Quản trị trở lên mới sửa được hồ sơ nhân sự, bảng lương, cấu hình lương và máy chấm công.</p></td>
		</tr>
		<tr>
			<th><label for="khh_title">Chức danh hiển thị</label></th>
			<td><input type="text" name="khh_title" id="khh_title" class="regular-text"
				value="<?php echo esc_attr( $title ); ?>" placeholder="VD: Quản lý dự án"></td>
		</tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'khh_save_user_fields' );
add_action( 'edit_user_profile_update', 'khh_save_user_fields' );
function khh_save_user_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST['khh_role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$role = sanitize_text_field( wp_unslash( $_POST['khh_role'] ) ); // phpcs:ignore
		if ( in_array( $role, array( '', 'owner', 'admin', 'manager', 'staff' ), true ) ) {
			update_user_meta( $user_id, 'khh_role', $role );
		}
	}
	if ( isset( $_POST['khh_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_user_meta( $user_id, 'khh_title', sanitize_text_field( wp_unslash( $_POST['khh_title'] ) ) ); // phpcs:ignore
	}
}

/* ------------------------------------------------------------------ *
 * Đọc / ghi dữ liệu từ phía PHP
 * ------------------------------------------------------------------ */

/** Trả về mảng id => body của một nhóm dữ liệu. */
function khh_get_coll( $coll ) {
	global $wpdb;
	if ( ! in_array( $coll, khh_collections(), true ) ) {
		return array();
	}
	$rows = $wpdb->get_results( // phpcs:ignore
		$wpdb->prepare( 'SELECT doc_id, payload FROM ' . khh_table() . ' WHERE coll = %s AND deleted = 0', $coll ), // phpcs:ignore
		ARRAY_A
	);
	$out = array();
	foreach ( (array) $rows as $r ) {
		$body = json_decode( $r['payload'], true );
		$out[ $r['doc_id'] ] = is_array( $body ) ? $body : array();
	}
	return $out;
}

/** Ghi một bản ghi từ phía PHP (bỏ qua kiểm quyền — chỉ dùng trong admin). */
function khh_put_doc( $coll, $id, $body ) {
	global $wpdb;
	if ( ! in_array( $coll, khh_collections(), true ) ) {
		return false;
	}
	return (bool) $wpdb->replace( // phpcs:ignore
		khh_table(),
		array(
			'coll'       => $coll,
			'doc_id'     => (string) $id,
			'payload'    => wp_json_encode( $body ),
			'deleted'    => 0,
			'updated_at' => (int) round( microtime( true ) * 1000 ),
			'updated_by' => get_current_user_id(),
		),
		array( '%s', '%s', '%s', '%d', '%d', '%d' )
	);
}

/** Tìm tài khoản WordPress ứng với một hồ sơ nhân sự. */
function khh_user_for_staff( $staff_id, $staff ) {
	$users = get_users(
		array(
			'meta_key'   => 'khh_staff_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => $staff_id,      // phpcs:ignore WordPress.DB.SlowDBQuery
			'number'     => 1,
		)
	);
	if ( $users ) {
		return $users[0];
	}
	if ( ! empty( $staff['email'] ) ) {
		$u = get_user_by( 'email', $staff['email'] );
		if ( $u ) {
			return $u;
		}
	}
	if ( ! empty( $staff['name'] ) ) {
		$found = get_users(
			array(
				'search'         => $staff['name'],
				'search_columns' => array( 'display_name' ),
				'number'         => 1,
			)
		);
		if ( $found ) {
			return $found[0];
		}
	}
	return null;
}

/* ------------------------------------------------------------------ *
 * Cấu hình
 * ------------------------------------------------------------------ */

function khh_settings() {
	$d = array(
		'google_client_id' => '',
		'allowed_domains'  => '',
		'only_staff'       => 1,
		'default_role'     => 'staff',
		'pin_enabled'      => 0,
		'pin_hash'         => '',
		'pin_user'         => 0,
		'pin_expires'      => 0,
		'logo'             => '',
	);
	/* Phần "Tài khoản của nhân viên" giữ mặc định ở tai-khoan-cua-toi.php,
	   để mọi khoá của nó nằm cùng một chỗ với đoạn mã dùng chúng. */
	$d = array_merge( $d, khh_tk_mac_dinh() );
	$s = get_option( 'khh_settings', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), $d );
}

/* ------------------------------------------------------------------ *
 * Đăng nhập bằng Google
 * ------------------------------------------------------------------ */

function khh_google_enabled() {
	$s = khh_settings();
	return ! empty( $s['google_client_id'] );
}

function khh_google_button_html() {
	$s   = khh_settings();
	$uri = home_url( '/?khh_google=1' );
	ob_start();
	?>
	<div style="margin:0 0 18px;text-align:center">
		<div id="g_id_onload"
			data-client_id="<?php echo esc_attr( $s['google_client_id'] ); ?>"
			data-login_uri="<?php echo esc_url( $uri ); ?>"
			data-ux_mode="redirect"
			data-auto_prompt="false"></div>
		<div class="g_id_signin" data-type="standard" data-shape="rectangular"
			data-theme="outline" data-text="signin_with" data-size="large"
			data-logo_alignment="left" style="display:flex;justify-content:center"></div>
		<script src="https://accounts.google.com/gsi/client" async defer></script>
	</div>
	<?php
	return ob_get_clean();
}

/** Nút Google trên trang đăng nhập WordPress. */
add_action( 'login_message', 'khh_login_message' );
function khh_login_message( $message ) {
	if ( ! khh_google_enabled() ) {
		return $message;
	}
	return $message . khh_google_button_html();
}

/** Nhận kết quả từ Google: POST tới /?khh_google=1 */
add_action( 'init', 'khh_google_callback', 1 );
function khh_google_callback() {
	if ( ! isset( $_GET['khh_google'] ) || empty( $_POST['credential'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! khh_google_enabled() ) {
		wp_die( 'Chưa khai Google Client ID trong cấu hình Nền tảng K&H.' );
	}

	// Google gửi kèm cặp CSRF: cookie phải khớp trường POST.
	$cookie = isset( $_COOKIE['g_csrf_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['g_csrf_token'] ) ) : '';
	$posted = isset( $_POST['g_csrf_token'] ) ? sanitize_text_field( wp_unslash( $_POST['g_csrf_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $cookie || ! hash_equals( $cookie, $posted ) ) {
		wp_die( 'Phiên đăng nhập Google không hợp lệ. Thử lại.' );
	}

	$credential = sanitize_text_field( wp_unslash( $_POST['credential'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$claims     = khh_verify_google_token( $credential );
	if ( is_wp_error( $claims ) ) {
		wp_die( esc_html( $claims->get_error_message() ) );
	}

	$user = khh_login_or_provision( $claims );
	if ( is_wp_error( $user ) ) {
		wp_die( esc_html( $user->get_error_message() ) );
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );

	wp_safe_redirect( home_url( '/?khh_app=1' ) );
	exit;
}

/** Nhờ Google xác minh chữ ký của ID token rồi kiểm các claim quan trọng. */
function khh_verify_google_token( $credential ) {
	$s   = khh_settings();
	$res = wp_remote_get(
		'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $credential ),
		array( 'timeout' => 15 )
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'khh_google_net', 'Không gọi được tới Google để xác minh: ' . $res->get_error_message() );
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return new WP_Error( 'khh_google_bad', 'Google từ chối xác minh phiên đăng nhập này.' );
	}
	$claims = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $claims ) || empty( $claims['email'] ) ) {
		return new WP_Error( 'khh_google_bad', 'Google trả về dữ liệu không đọc được.' );
	}
	if ( empty( $claims['aud'] ) || ! hash_equals( (string) $s['google_client_id'], (string) $claims['aud'] ) ) {
		return new WP_Error( 'khh_google_aud', 'Token không dành cho ứng dụng này.' );
	}
	if ( isset( $claims['email_verified'] ) && 'false' === (string) $claims['email_verified'] ) {
		return new WP_Error( 'khh_google_unverified', 'Email Google chưa được xác minh.' );
	}
	if ( ! empty( $claims['exp'] ) && (int) $claims['exp'] < time() - 60 ) {
		return new WP_Error( 'khh_google_exp', 'Phiên đăng nhập đã hết hạn, thử lại.' );
	}
	return $claims;
}

/** Đăng nhập tài khoản sẵn có, hoặc tạo mới theo chính sách đã đặt. */
function khh_login_or_provision( $claims ) {
	$s      = khh_settings();
	$email  = sanitize_email( $claims['email'] );
	$name   = ! empty( $claims['name'] ) ? sanitize_text_field( $claims['name'] ) : $email;
	$domain = substr( strrchr( $email, '@' ), 1 );

	$allowed = array_filter( array_map( 'trim', explode( ',', (string) $s['allowed_domains'] ) ) );
	if ( $allowed && ! in_array( strtolower( $domain ), array_map( 'strtolower', $allowed ), true ) ) {
		return new WP_Error( 'khh_domain', 'Email ' . $email . ' không thuộc tên miền được phép (' . implode( ', ', $allowed ) . ').' );
	}

	$user = get_user_by( 'email', $email );
	if ( $user ) {
		return $user;
	}

	// Chưa có tài khoản: tìm hồ sơ nhân sự trùng email.
	$staff_id = '';
	$staff    = null;
	foreach ( khh_get_coll( 'staff' ) as $id => $row ) {
		if ( ! empty( $row['email'] ) && strtolower( $row['email'] ) === strtolower( $email ) ) {
			$staff_id = $id;
			$staff    = $row;
			break;
		}
	}
	if ( ! $staff && ! empty( $s['only_staff'] ) ) {
		return new WP_Error(
			'khh_no_staff',
			'Email ' . $email . ' chưa có trong hồ sơ nhân sự. Nhờ quản trị thêm hồ sơ (đúng email này) rồi đăng nhập lại.'
		);
	}

	$khh_role = $staff && ! empty( $staff['role'] ) ? $staff['role'] : $s['default_role'];
	$login    = sanitize_user( current( explode( '@', $email ) ), true );
	if ( ! $login || username_exists( $login ) ) {
		$login = sanitize_user( $login . '-' . wp_rand( 100, 999 ), true );
	}

	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'display_name' => $staff && ! empty( $staff['name'] ) ? $staff['name'] : $name,
			'first_name'   => ! empty( $claims['given_name'] ) ? sanitize_text_field( $claims['given_name'] ) : '',
			'last_name'    => ! empty( $claims['family_name'] ) ? sanitize_text_field( $claims['family_name'] ) : '',
			'role'         => khh_wp_role_for( $khh_role ),
		)
	);
	if ( is_wp_error( $uid ) ) {
		return $uid;
	}
	update_user_meta( $uid, 'khh_role', $khh_role );
	if ( $staff_id ) {
		update_user_meta( $uid, 'khh_staff_id', $staff_id );
		if ( ! empty( $staff['title'] ) ) {
			update_user_meta( $uid, 'khh_title', $staff['title'] );
		}
	}
	return get_user_by( 'id', $uid );
}

/* ------------------------------------------------------------------ *
 * Trang Tài khoản và Cấu hình
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_admin_submenus', 20 );
function khh_admin_submenus() {
	add_submenu_page( 'khh-platform', 'Tài khoản', 'Tài khoản', 'list_users', 'khh-accounts', 'khh_accounts_page' );
	add_submenu_page( 'khh-platform', 'Cấu hình', 'Cấu hình', 'manage_options', 'khh-settings', 'khh_settings_page' );
}

/* ---- Cấu hình ---- */

add_action( 'admin_post_khh_save_settings', 'khh_save_settings' );
function khh_save_settings() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'khh_settings' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$in = array(
		'google_client_id' => isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '',
		'allowed_domains'  => isset( $_POST['allowed_domains'] ) ? sanitize_text_field( wp_unslash( $_POST['allowed_domains'] ) ) : '',
		'only_staff'       => isset( $_POST['only_staff'] ) ? 1 : 0,
		'default_role'     => isset( $_POST['default_role'] ) ? sanitize_key( wp_unslash( $_POST['default_role'] ) ) : 'staff',
		'logo'             => isset( $_POST['logo'] ) ? esc_url_raw( wp_unslash( $_POST['logo'] ) ) : '',
	);
	if ( ! in_array( $in['default_role'], array( 'owner', 'admin', 'manager', 'staff' ), true ) ) {
		$in['default_role'] = 'staff';
	}
	$in = array_merge( $in, khh_tk_nhan_cau_hinh() );
	/* Gộp vào bản đang có, KHÔNG ghi đè cả ô: mã PIN và các khoá khác nằm chung ô này,
	   ghi đè thẳng là xoá sạch chúng mỗi lần ai đó bấm Lưu ở trang Cấu hình. */
	$cu = get_option( 'khh_settings', array() );
	update_option( 'khh_settings', array_merge( is_array( $cu ) ? $cu : array(), $in ) );
	wp_safe_redirect( admin_url( 'admin.php?page=khh-settings&saved=1' ) );
	exit;
}

function khh_settings_page() {
	$s     = khh_settings();
	$roles = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H — Cấu hình</h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p>Đã lưu cấu hình.</p></div>
		<?php endif; ?>

		<h2>Đăng nhập bằng Google</h2>
		<p>Khai hai giá trị này trong <strong>Google Cloud Console → APIs &amp; Services → Credentials →
		OAuth client ID (Web application)</strong>:</p>
		<table class="widefat striped" style="max-width:860px;margin-bottom:16px">
			<tr><td style="width:240px"><strong>Authorized JavaScript origins</strong></td>
				<td><code><?php echo esc_html( home_url() ); ?></code></td></tr>
			<tr><td><strong>Authorized redirect URIs</strong></td>
				<td><code><?php echo esc_html( home_url( '/?khh_google=1' ) ); ?></code></td></tr>
		</table>
		<p>Google chỉ chấp nhận địa chỉ <strong>https</strong> (trừ <code>http://localhost</code>).</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="khh_save_settings">
			<?php wp_nonce_field( 'khh_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="google_client_id">Google Client ID</label></th>
					<td><input type="text" class="large-text code" id="google_client_id" name="google_client_id"
						value="<?php echo esc_attr( $s['google_client_id'] ); ?>"
						placeholder="1234567890-abcxyz.apps.googleusercontent.com">
					<p class="description">Để trống thì tắt đăng nhập Google, chỉ còn đăng nhập bằng mật khẩu WordPress.</p></td>
				</tr>
				<tr>
					<th><label for="allowed_domains">Tên miền email được phép</label></th>
					<td><input type="text" class="regular-text" id="allowed_domains" name="allowed_domains"
						value="<?php echo esc_attr( $s['allowed_domains'] ); ?>" placeholder="khh.vn, poshvn.com">
					<p class="description">Cách nhau bằng dấu phẩy. Để trống là chấp nhận mọi tên miền — không nên.</p></td>
				</tr>
				<tr>
					<th>Chính sách tạo tài khoản</th>
					<td><label><input type="checkbox" name="only_staff" value="1" <?php checked( $s['only_staff'], 1 ); ?>>
						Chỉ cho đăng nhập khi email đã có trong hồ sơ nhân sự</label>
					<p class="description">Bật (khuyến nghị): quản trị thêm hồ sơ nhân sự kèm email trước, người đó đăng nhập Google là
						tài khoản tự tạo và tự gắn đúng hồ sơ. Tắt: ai có email đúng tên miền cũng vào được.</p></td>
				</tr>
				<tr>
					<th><label for="default_role">Vai trò mặc định cho tài khoản mới</label></th>
					<td><select name="default_role" id="default_role">
						<?php foreach ( $roles as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['default_role'], $k ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Chỉ dùng khi hồ sơ nhân sự không ghi vai trò.</p></td>
				</tr>
				<?php khh_tk_o_cau_hinh( $s ); ?>
				<tr>
					<th><label for="logo">Logo trên màn đăng nhập</label></th>
					<td><input type="text" id="logo" name="logo" class="large-text"
							value="<?php echo esc_attr( isset( $s['logo'] ) ? $s['logo'] : '' ); ?>"
							placeholder="https://…/logo-kh.png">
						<p class="description">Tải logo lên <b>Thư viện</b> của WordPress rồi dán đường dẫn ảnh vào đây.
						Để trống thì màn đăng nhập hiện chữ <b>KH</b>.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Lưu cấu hình' ); ?>
		</form>
	</div>
	<?php
}

/* ---- Tài khoản ---- */

add_action( 'admin_post_khh_make_user', 'khh_make_user' );
function khh_make_user() {
	if ( ! current_user_can( 'create_users' ) || ! check_admin_referer( 'khh_accounts' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$staff_id = isset( $_POST['staff_id'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) : '';
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$staffs   = khh_get_coll( 'staff' );
	if ( ! isset( $staffs[ $staff_id ] ) || ! is_email( $email ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&err=' . rawurlencode( 'Thiếu hồ sơ hoặc email không hợp lệ.' ) ) );
		exit;
	}
	$staff = $staffs[ $staff_id ];

	$existing = get_user_by( 'email', $email );
	if ( $existing ) {
		update_user_meta( $existing->ID, 'khh_staff_id', $staff_id );
		wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&msg=' . rawurlencode( 'Email này đã có tài khoản — đã gắn vào hồ sơ.' ) ) );
		exit;
	}

	$khh_role = ! empty( $staff['role'] ) ? $staff['role'] : 'staff';
	$login    = sanitize_user( current( explode( '@', $email ) ), true );
	if ( ! $login || username_exists( $login ) ) {
		$login = sanitize_user( $login . '-' . wp_rand( 100, 999 ), true );
	}
	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'display_name' => $staff['name'],
			'role'         => khh_wp_role_for( $khh_role ),
		)
	);
	if ( is_wp_error( $uid ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&err=' . rawurlencode( $uid->get_error_message() ) ) );
		exit;
	}
	update_user_meta( $uid, 'khh_role', $khh_role );
	update_user_meta( $uid, 'khh_staff_id', $staff_id );
	if ( ! empty( $staff['title'] ) ) {
		update_user_meta( $uid, 'khh_title', $staff['title'] );
	}
	if ( empty( $staff['email'] ) || strtolower( $staff['email'] ) !== strtolower( $email ) ) {
		$staff['email'] = $email;
		khh_put_doc( 'staff', $staff_id, $staff );
	}
	wp_new_user_notification( $uid, null, 'user' );

	wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&msg=' . rawurlencode( 'Đã tạo tài khoản ' . $login . ' và gửi thư đặt mật khẩu tới ' . $email . '.' ) ) );
	exit;
}

add_action( 'admin_post_khh_make_staff', 'khh_make_staff' );
function khh_make_staff() {
	if ( ! current_user_can( 'list_users' ) || ! check_admin_referer( 'khh_accounts' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$uid  = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$user = $uid ? get_user_by( 'id', $uid ) : null;
	if ( ! $user ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&err=' . rawurlencode( 'Không tìm thấy tài khoản.' ) ) );
		exit;
	}
	$id    = 's_' . $uid;
	$role  = khh_user_role( $uid );
	$staff = array(
		'name'      => $user->display_name,
		'email'     => $user->user_email,
		'title'     => get_user_meta( $uid, 'khh_title', true ),
		'role'      => $role,
		'status'    => 'active',
		'worktime'  => 'Full-time',
		'leaveLeft' => 12,
		'leaveYear' => 12,
	);
	khh_put_doc( 'staff', $id, $staff );
	update_user_meta( $uid, 'khh_staff_id', $id );
	wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&msg=' . rawurlencode( 'Đã tạo hồ sơ nhân sự cho ' . $user->display_name . '.' ) ) );
	exit;
}

add_action( 'admin_post_khh_resend', 'khh_resend' );
function khh_resend() {
	if ( ! current_user_can( 'edit_users' ) || ! check_admin_referer( 'khh_accounts' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$uid = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	if ( $uid ) {
		$user = get_user_by( 'id', $uid );
		retrieve_password( $user->user_login );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=khh-accounts&msg=' . rawurlencode( 'Đã gửi lại thư đặt mật khẩu.' ) ) );
	exit;
}

function khh_accounts_page() {
	$staffs = khh_get_coll( 'staff' );
	$names  = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	$linked = array();
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H — Tài khoản</h1>
		<?php if ( isset( $_GET['msg'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p><?php echo esc_html( wp_unslash( $_GET['msg'] ) ); // phpcs:ignore ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['err'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( $_GET['err'] ) ); // phpcs:ignore ?></p></div>
		<?php endif; ?>

		<p>Mỗi người trong hồ sơ nhân sự cần một tài khoản WordPress để đăng nhập.
		<?php if ( khh_google_enabled() ) : ?>
			Đang bật <strong>đăng nhập Google</strong>: chỉ cần hồ sơ có đúng email, người đó bấm
			“Sign in with Google” là tài khoản tự tạo — không phải làm gì ở đây.
		<?php else : ?>
			Chưa bật đăng nhập Google (<a href="<?php echo esc_url( admin_url( 'admin.php?page=khh-settings' ) ); ?>">khai Client ID</a>),
			nên tạo tài khoản thủ công bên dưới, WordPress sẽ gửi thư đặt mật khẩu.
		<?php endif; ?>
		</p>

		<h2>Hồ sơ nhân sự</h2>
		<table class="widefat striped">
			<thead><tr><th>Nhân sự</th><th>Vai trò</th><th>Tài khoản đăng nhập</th><th style="width:420px">Thao tác</th></tr></thead>
			<tbody>
			<?php if ( ! $staffs ) : ?>
				<tr><td colspan="4">Chưa có hồ sơ nhân sự nào. Thêm trong ứng dụng <strong>Hồ sơ nhân sự</strong>.</td></tr>
			<?php endif; ?>
			<?php foreach ( $staffs as $sid => $st ) : ?>
				<?php
				$user = khh_user_for_staff( $sid, $st );
				if ( $user ) {
					$linked[ $user->ID ] = true;
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( isset( $st['name'] ) ? $st['name'] : $sid ); ?></strong><br>
						<span class="description"><?php echo esc_html( isset( $st['title'] ) ? $st['title'] : '' ); ?>
						<?php echo empty( $st['email'] ) ? '' : ' · ' . esc_html( $st['email'] ); ?></span></td>
					<td><?php echo esc_html( $names[ isset( $st['role'] ) && isset( $names[ $st['role'] ] ) ? $st['role'] : 'staff' ] ); ?></td>
					<td>
						<?php if ( $user ) : ?>
							<span style="color:#1a7f37">✔</span> <code><?php echo esc_html( $user->user_login ); ?></code><br>
							<span class="description"><?php echo esc_html( $user->user_email ); ?>
							<?php echo ( strtolower( $user->display_name ) !== strtolower( isset( $st['name'] ) ? $st['name'] : '' ) )
								? ' · <strong>tên hiển thị khác hồ sơ</strong>' : ''; // phpcs:ignore ?></span>
						<?php else : ?>
							<span style="color:#b32d2e">chưa có</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $user ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="khh_resend">
								<input type="hidden" name="user_id" value="<?php echo (int) $user->ID; ?>">
								<?php wp_nonce_field( 'khh_accounts' ); ?>
								<button class="button button-small">Gửi lại thư đặt mật khẩu</button>
							</form>
							<a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">Sửa tài khoản</a>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px">
								<input type="hidden" name="action" value="khh_make_user">
								<input type="hidden" name="staff_id" value="<?php echo esc_attr( $sid ); ?>">
								<?php wp_nonce_field( 'khh_accounts' ); ?>
								<input type="email" name="email" required style="flex:1"
									value="<?php echo esc_attr( isset( $st['email'] ) ? $st['email'] : '' ); ?>" placeholder="email@khh.vn">
								<button class="button button-primary button-small">Tạo tài khoản</button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:28px">Tài khoản WordPress chưa có hồ sơ nhân sự</h2>
		<table class="widefat striped">
			<thead><tr><th>Tài khoản</th><th>Vai trò nền tảng</th><th style="width:260px">Thao tác</th></tr></thead>
			<tbody>
			<?php
			$others = 0;
			foreach ( get_users( array( 'number' => 200 ) ) as $u ) :
				if ( isset( $linked[ $u->ID ] ) ) {
					continue;
				}
				$others++;
				?>
				<tr>
					<td><strong><?php echo esc_html( $u->display_name ); ?></strong>
						<span class="description">· <?php echo esc_html( $u->user_email ); ?></span></td>
					<td><?php echo esc_html( $names[ khh_user_role( $u->ID ) ] ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="khh_make_staff">
							<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
							<?php wp_nonce_field( 'khh_accounts' ); ?>
							<button class="button button-small">Tạo hồ sơ nhân sự</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $others ) : ?>
				<tr><td colspan="3">Mọi tài khoản đều đã gắn với một hồ sơ nhân sự.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
