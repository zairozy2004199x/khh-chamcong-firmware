<?php
/**
 * Quản trị báo cáo cơ sở — ai được nhập, cơ sở nào, và cổng nhận nhân viên
 * đẩy sang từ trang nhân sự.
 *
 * Làm theo đúng kiểu app Chi Phí: cửa hàng trưởng được cấp quyền ở trang nhân sự
 * rồi "đẩy" sang đây; sang tới nơi là nhập được báo cáo ngày của đúng cơ sở mình,
 * không thấy cơ sở khác và không nạp được file POS.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Khoá để trang nhân sự gọi sang. Chưa có thì sinh một lần. */
function khh_dt_token_day() {
	$t = get_option( 'khh_dt_token_day' );
	if ( ! $t ) {
		$t = wp_generate_password( 32, false );
		update_option( 'khh_dt_token_day', $t, false );
	}
	return $t;
}

/**
 * Quyền nhập của người đang xem: '' | 'nhap' | 'duyet'.
 *
 * Người vào bằng PIN chấm công không có tài khoản WordPress nên không có meta — vai của họ nằm ở
 * hàng trong bảng người (đẩy từ trang nhân sự sang). Xem `nguoi.php`.
 */
function khh_dt_quyen_cua( $uid = 0 ) {
	if ( ! $uid && ! is_user_logged_in() && function_exists( 'khh_dt_phien_nguoi' ) ) {
		$n = khh_dt_phien_nguoi();
		if ( $n ) {
			return in_array( (string) $n['vai'], array( 'nhap', 'duyet' ), true ) ? (string) $n['vai'] : 'nhap';
		}
	}
	$uid = $uid ? $uid : get_current_user_id();
	return (string) get_user_meta( $uid, 'khh_dt_quyen', true );   // '' | 'nhap' | 'duyet'
}

/* ------------------------------------------------------------------ *
 * REST
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'khh_dt_rest_qt' );
function khh_dt_rest_qt() {
	register_rest_route(
		'khh-dt/v1',
		'/nhan-su',
		array(
			'methods'             => 'GET',
			'callback'            => 'khh_dt_rest_ds_nhan_su',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	register_rest_route(
		'khh-dt/v1',
		'/nhan-su',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_dat_quyen',
			'permission_callback' => 'khh_dt_duoc_quan_tri',
		)
	);
	// Cổng cho trang nhân sự đẩy sang — xác thực bằng token, không cần đăng nhập.
	register_rest_route(
		'khh-dt/v1',
		'/day-nhan-vien',
		array(
			'methods'             => 'POST',
			'callback'            => 'khh_dt_rest_nhan_day',
			'permission_callback' => '__return_true',
		)
	);
}

function khh_dt_duoc_quan_tri() {
	return current_user_can( 'list_users' );
}

function khh_dt_rest_ds_nhan_su() {
	$ds  = array();
	$ngd = get_users(
		array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array(
					'key'     => 'khh_dt_quyen',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'khh_dt_co_so',
					'compare' => 'EXISTS',
				),
			),
			'number'     => 500,
		)
	);
	foreach ( $ngd as $u ) {
		$quyen = khh_dt_quyen_cua( $u->ID );
		$co_so = khh_dt_co_so_cua( $u->ID );
		if ( ! $quyen && ! $co_so ) {
			continue;
		}
		$ds[] = array(
			'id'       => $u->ID,
			'ten'      => $u->display_name,
			'tai_khoan' => $u->user_login,
			'email'    => $u->user_email,
			'quyen'    => $quyen,
			'co_so'    => $co_so,
			'vai_wp'   => implode( ', ', $u->roles ),
		);
	}
	usort(
		$ds,
		function ( $a, $b ) {
			return strcmp( $a['co_so'] . $a['ten'], $b['co_so'] . $b['ten'] );
		}
	);
	return array(
		'ds'       => $ds,
		'cua_hang' => khh_dt_ds_cua_hang(),
		'cong'     => array(
			'url'   => esc_url_raw( rest_url( 'khh-dt/v1/day-nhan-vien' ) ),
			'token' => khh_dt_token_day(),
		),
	);
}

function khh_dt_rest_dat_quyen( $req ) {
	$uid = (int) $req->get_param( 'id' );
	if ( ! $uid || ! get_userdata( $uid ) ) {
		return new WP_Error( 'khh_dt_qt', 'Không thấy người dùng này.', array( 'status' => 400 ) );
	}
	$quyen = sanitize_text_field( (string) $req->get_param( 'quyen' ) );
	$co_so = sanitize_text_field( (string) $req->get_param( 'co_so' ) );
	if ( ! in_array( $quyen, array( '', 'nhap', 'duyet' ), true ) ) {
		$quyen = '';
	}
	update_user_meta( $uid, 'khh_dt_quyen', $quyen );
	update_user_meta( $uid, 'khh_dt_co_so', $co_so );
	return array(
		'ok'    => true,
		'id'    => $uid,
		'quyen' => $quyen,
		'co_so' => $co_so,
	);
}

/**
 * Trang nhân sự đẩy một nhân viên sang đây.
 *
 * POST khh-dt/v1/day-nhan-vien
 *   token      bắt buộc — lấy ở tab Quản trị
 *   ho_ten     tên hiển thị
 *   email      hoặc tai_khoan — dùng để tìm/tạo tài khoản
 *   tai_khoan  tên đăng nhập (nếu chưa có tài khoản thì tạo bằng tên này)
 *   co_so      tên cơ sở, phải trùng tên cơ sở trong số liệu POS
 *   quyen      'nhap' (mặc định) | 'duyet' | '' để gỡ
 *   bo         1 = gỡ quyền
 * Trả về: tài khoản, mật khẩu mới (nếu vừa tạo), quyền và cơ sở đã gán.
 */
function khh_dt_rest_nhan_day( $req ) {
	$token = (string) $req->get_param( 'token' );
	if ( ! hash_equals( khh_dt_token_day(), $token ) ) {
		return new WP_Error( 'khh_dt_token', 'Sai token.', array( 'status' => 403 ) );
	}

	$email     = sanitize_email( (string) $req->get_param( 'email' ) );
	$tai_khoan = sanitize_user( (string) $req->get_param( 'tai_khoan' ), true );
	$ho_ten    = sanitize_text_field( (string) $req->get_param( 'ho_ten' ) );
	$co_so     = sanitize_text_field( (string) $req->get_param( 'co_so' ) );
	$quyen     = sanitize_text_field( (string) $req->get_param( 'quyen' ) );
	$bo        = (bool) $req->get_param( 'bo' );

	if ( ! $email && ! $tai_khoan ) {
		return new WP_Error( 'khh_dt_day', 'Cần email hoặc tên đăng nhập.', array( 'status' => 400 ) );
	}
	if ( ! in_array( $quyen, array( 'nhap', 'duyet' ), true ) ) {
		$quyen = 'nhap';
	}

	$u = $email ? get_user_by( 'email', $email ) : false;
	if ( ! $u && $tai_khoan ) {
		$u = get_user_by( 'login', $tai_khoan );
	}

	if ( $bo ) {
		if ( ! $u ) {
			return array(
				'ok'     => true,
				'ghi_chu' => 'Không có tài khoản nào để gỡ.',
			);
		}
		delete_user_meta( $u->ID, 'khh_dt_quyen' );
		delete_user_meta( $u->ID, 'khh_dt_co_so' );
		return array(
			'ok'        => true,
			'da_go'     => true,
			'tai_khoan' => $u->user_login,
		);
	}

	$mat_khau = '';
	if ( ! $u ) {
		$login = $tai_khoan ? $tai_khoan : sanitize_user( current( explode( '@', $email ) ), true );
		$i     = 1;
		$goc   = $login;
		while ( username_exists( $login ) ) {
			$login = $goc . ( ++$i );
		}
		$mat_khau = wp_generate_password( 10, false );
		$uid      = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => $mat_khau,
				'user_email'   => $email ? $email : '',
				'display_name' => $ho_ten ? $ho_ten : $login,
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return $uid;
		}
		$u = get_userdata( $uid );
	} elseif ( $ho_ten && $ho_ten !== $u->display_name ) {
		wp_update_user(
			array(
				'ID'           => $u->ID,
				'display_name' => $ho_ten,
			)
		);
	}

	update_user_meta( $u->ID, 'khh_dt_quyen', $quyen );
	update_user_meta( $u->ID, 'khh_dt_co_so', $co_so );

	return array(
		'ok'        => true,
		'id'        => $u->ID,
		'tai_khoan' => $u->user_login,
		'mat_khau'  => $mat_khau,        // chỉ có khi vừa tạo tài khoản mới
		'quyen'     => $quyen,
		'co_so'     => $co_so,
		'link'      => khh_dt_link(),
	);
}
