<?php
/**
 * Cấp tài khoản bằng TÊN ĐĂNG NHẬP + MẬT KHẨU, giao tận tay cho nhân viên.
 *
 * Khác với trang Tài khoản cũ (gửi thư đặt mật khẩu qua email): nhiều nhân viên cơ sở
 * không có email công ty, và nhiều hosting không gửi được thư. Ở đây quản trị tự đặt
 * hoặc để máy sinh mật khẩu, hệ thống hiện ra ĐÚNG MỘT LẦN để in hoặc chép gửi đi.
 *
 * Mật khẩu không bao giờ được lưu lại dạng đọc được: WordPress chỉ giữ bản băm.
 * Bản rõ nằm trong một transient 10 phút của riêng người vừa bấm, xem xong là xoá.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bỏ các ký tự dễ đọc nhầm khi đọc mật khẩu qua điện thoại. */
const KHH_PASS_CHARS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

function khh_gen_pass( $len = 10 ) {
	$max = strlen( KHH_PASS_CHARS ) - 1;
	$out = '';
	for ( $i = 0; $i < $len; $i++ ) {
		$out .= KHH_PASS_CHARS[ wp_rand( 0, $max ) ];
	}
	return $out;
}

/**
 * Tên đăng nhập gợi ý cho một hồ sơ nhân sự.
 *
 * Ưu tiên mã nhân sự vì nó cố định và không trùng; không có mã thì lấy từ họ tên.
 */
function khh_suggest_login( $staff ) {
	$base = '';
	if ( ! empty( $staff['code'] ) ) {
		$base = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', $staff['code'] ) );
	}
	if ( '' === $base && ! empty( $staff['name'] ) ) {
		$base = preg_replace( '/[^a-z0-9]/', '', khh_slug_vi( $staff['name'] ) );
	}
	if ( '' === $base ) {
		$base = 'nv';
	}
	$base = substr( $base, 0, 30 );
	$try  = $base;
	$n    = 1;
	while ( username_exists( $try ) ) {
		$n++;
		$try = $base . $n;
		if ( $n > 200 ) {
			$try = $base . wp_rand( 1000, 9999 );
			break;
		}
	}
	return $try;
}

/** Hồ sơ nhân sự đã có tài khoản đăng nhập chưa: trả về WP_User hoặc null. */
function khh_user_of_staff( $staff_id ) {
	$found = get_users(
		array(
			'meta_key'   => 'khh_staff_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => $staff_id,      // phpcs:ignore WordPress.DB.SlowDBQuery
			'number'     => 1,
		)
	);
	return $found ? $found[0] : null;
}

/** Cất danh sách tài khoản vừa cấp để hiện đúng một lần cho người vừa bấm. */
function khh_stash_logins( $rows ) {
	set_transient( 'khh_new_logins_' . get_current_user_id(), $rows, 10 * MINUTE_IN_SECONDS );
}
function khh_take_logins() {
	$k   = 'khh_new_logins_' . get_current_user_id();
	$val = get_transient( $k );
	delete_transient( $k );
	return is_array( $val ) ? $val : array();
}

/**
 * Tạo tài khoản cho một hồ sơ nhân sự.
 *
 * @param string $staff_id Mã hồ sơ trong nhóm dữ liệu staff.
 * @param string $login    Tên đăng nhập muốn đặt; để trống thì máy tự gợi ý.
 * @param string $pass     Mật khẩu muốn đặt; để trống thì máy tự sinh.
 * @return array|WP_Error  array( login, pass, name, uid ) khi thành công.
 */
function khh_create_login( $staff_id, $login = '', $pass = '' ) {
	$staffs = khh_get_coll( 'staff' );
	if ( ! isset( $staffs[ $staff_id ] ) ) {
		return new WP_Error( 'khh_no_staff', 'Không tìm thấy hồ sơ nhân sự.' );
	}
	$staff = $staffs[ $staff_id ];

	$co = khh_user_of_staff( $staff_id );
	if ( $co ) {
		return new WP_Error( 'khh_da_co', $staff['name'] . ' đã có tài khoản (' . $co->user_login . ').' );
	}

	$login = $login ? sanitize_user( $login, true ) : khh_suggest_login( $staff );
	if ( '' === $login ) {
		return new WP_Error( 'khh_login_xau', 'Tên đăng nhập không hợp lệ.' );
	}
	if ( username_exists( $login ) ) {
		return new WP_Error( 'khh_login_trung', 'Tên đăng nhập "' . $login . '" đã có người dùng.' );
	}

	$pass = $pass ? $pass : khh_gen_pass();
	if ( strlen( $pass ) < 8 ) {
		return new WP_Error( 'khh_pass_ngan', 'Mật khẩu phải từ 8 ký tự trở lên.' );
	}

	$khh_role = ! empty( $staff['role'] ) ? $staff['role'] : 'staff';
	$args     = array(
		'user_login'   => $login,
		'user_pass'    => $pass,
		'display_name' => $staff['name'],
		'nickname'     => $staff['name'],
		'role'         => khh_wp_role_for( $khh_role ),
	);
	/* Email không bắt buộc — nhân viên cơ sở thường không có. Có thì gắn vào cho tiện quên mật khẩu. */
	if ( ! empty( $staff['email'] ) && is_email( $staff['email'] ) && ! email_exists( $staff['email'] ) ) {
		$args['user_email'] = $staff['email'];
	}

	$uid = wp_insert_user( $args );
	if ( is_wp_error( $uid ) ) {
		return $uid;
	}

	update_user_meta( $uid, 'khh_role', $khh_role );
	update_user_meta( $uid, 'khh_staff_id', $staff_id );
	if ( ! empty( $staff['title'] ) ) {
		update_user_meta( $uid, 'khh_title', $staff['title'] );
	}

	return array(
		'uid'   => $uid,
		'login' => $login,
		'pass'  => $pass,
		'name'  => $staff['name'],
		'code'  => isset( $staff['code'] ) ? $staff['code'] : '',
		'role'  => $khh_role,
	);
}

/* ------------------------------------------------------------------ *
 * Thao tác
 * ------------------------------------------------------------------ */

add_action( 'admin_post_khh_cap_mot', 'khh_cap_mot' );
function khh_cap_mot() {
	if ( ! current_user_can( 'create_users' ) || ! check_admin_referer( 'khh_cap' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$r = khh_create_login(
		isset( $_POST['staff_id'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) : '',
		isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '',
		isset( $_POST['pass'] ) ? (string) wp_unslash( $_POST['pass'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	);
	if ( is_wp_error( $r ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-cap&err=' . rawurlencode( $r->get_error_message() ) ) );
		exit;
	}
	khh_stash_logins( array( $r ) );
	wp_safe_redirect( admin_url( 'admin.php?page=khh-cap&moi=1' ) );
	exit;
}

add_action( 'admin_post_khh_cap_nhieu', 'khh_cap_nhieu' );
function khh_cap_nhieu() {
	if ( ! current_user_can( 'create_users' ) || ! check_admin_referer( 'khh_cap' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$ids  = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore
	$rows = array();
	$loi  = array();
	foreach ( $ids as $id ) {
		$r = khh_create_login( sanitize_text_field( $id ) );
		if ( is_wp_error( $r ) ) {
			$loi[] = $r->get_error_message();
		} else {
			$rows[] = $r;
		}
		if ( count( $rows ) >= 300 ) {
			break;
		}
	}
	khh_stash_logins( $rows );
	$q = 'page=khh-cap&moi=1';
	if ( $loi ) {
		$q .= '&err=' . rawurlencode( implode( ' · ', array_slice( $loi, 0, 5 ) ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?' . $q ) );
	exit;
}

add_action( 'admin_post_khh_doi_mk', 'khh_doi_mk' );
function khh_doi_mk() {
	if ( ! current_user_can( 'edit_users' ) || ! check_admin_referer( 'khh_cap' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$uid  = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$user = $uid ? get_user_by( 'id', $uid ) : null;
	if ( ! $user ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-cap&err=' . rawurlencode( 'Không tìm thấy tài khoản.' ) ) );
		exit;
	}
	/* Không cho đặt lại mật khẩu của người có quyền cao hơn mình. */
	if ( ! current_user_can( 'edit_user', $uid ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-cap&err=' . rawurlencode( 'Không đủ quyền với tài khoản này.' ) ) );
		exit;
	}
	$pass = khh_gen_pass();
	wp_set_password( $pass, $uid );
	khh_stash_logins(
		array(
			array(
				'uid'   => $uid,
				'login' => $user->user_login,
				'pass'  => $pass,
				'name'  => $user->display_name,
				'code'  => '',
				'role'  => khh_user_role( $uid ),
				'dat'   => 1,
			),
		)
	);
	wp_safe_redirect( admin_url( 'admin.php?page=khh-cap&moi=1' ) );
	exit;
}

/* ------------------------------------------------------------------ *
 * Trang
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_cap_menu', 21 );
function khh_cap_menu() {
	add_submenu_page( 'khh-platform', 'Cấp tài khoản', 'Cấp tài khoản', 'create_users', 'khh-cap', 'khh_cap_page' );
}

function khh_cap_page() {
	if ( ! current_user_can( 'create_users' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$roles  = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	$staffs = khh_get_coll( 'staff' );
	$moi    = isset( $_GET['moi'] ) ? khh_take_logins() : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$err    = isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : ''; // phpcs:ignore

	/* chia hai nhóm: đã có tài khoản và chưa */
	$chua = array();
	$roi  = array();
	foreach ( $staffs as $id => $s ) {
		if ( ! empty( $s['status'] ) && 'left' === $s['status'] ) {
			continue;
		}
		$u = khh_user_of_staff( $id );
		if ( $u ) {
			$roi[ $id ] = array( $s, $u );
		} else {
			$chua[ $id ] = $s;
		}
	}
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H — Cấp tài khoản</h1>

		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
		<?php endif; ?>

		<?php if ( $moi ) : ?>
			<div class="notice notice-success">
				<p><strong>Đã cấp <?php echo count( $moi ); ?> tài khoản.</strong>
				Mật khẩu chỉ hiện <b>một lần này</b> — in hoặc chép ra trước khi rời trang.
				Rời trang rồi thì không xem lại được, chỉ đặt lại mật khẩu mới.</p>
			</div>
			<div id="khh-phat" style="background:#fff;border:1px solid #c3c4c7;padding:16px;max-width:820px">
				<h2 style="margin-top:0">Danh sách giao cho nhân viên</h2>
				<p>Đăng nhập tại: <code><?php echo esc_html( wp_login_url() ); ?></code>
					— vào xong mở <code><?php echo esc_html( home_url( '/?khh_app=1' ) ); ?></code></p>
				<table class="widefat striped">
					<thead><tr><th>Nhân sự</th><th>Mã</th><th>Tên đăng nhập</th><th>Mật khẩu</th><th>Quyền</th></tr></thead>
					<tbody>
					<?php foreach ( $moi as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['name'] ); ?>
								<?php echo ! empty( $r['dat'] ) ? '<span class="description"> · đặt lại</span>' : ''; // phpcs:ignore ?></td>
							<td><?php echo esc_html( $r['code'] ); ?></td>
							<td><code style="font-size:14px"><?php echo esc_html( $r['login'] ); ?></code></td>
							<td><code style="font-size:15px;font-weight:600"><?php echo esc_html( $r['pass'] ); ?></code></td>
							<td><?php echo esc_html( isset( $roles[ $r['role'] ] ) ? $roles[ $r['role'] ] : $r['role'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Nhắc nhân viên đổi mật khẩu sau lần đăng nhập đầu: bấm tên mình ở góc phải màn quản trị → Hồ sơ → Đặt mật khẩu mới.</p>
			</div>
			<p><button class="button" onclick="var w=window.open('','_blank');w.document.write('&lt;title&gt;Tài khoản K&amp;H&lt;/title&gt;'+document.getElementById('khh-phat').innerHTML);w.document.close();w.print();">In danh sách này</button></p>
			<hr>
		<?php endif; ?>

		<h2>Chưa có tài khoản — <?php echo count( $chua ); ?> người</h2>
		<?php if ( ! $chua ) : ?>
			<p>Mọi hồ sơ nhân sự đang làm việc đều đã có tài khoản.</p>
		<?php else : ?>
			<p>Tích chọn rồi bấm <b>Cấp tài khoản hàng loạt</b>: máy lấy <b>mã nhân sự</b> làm tên đăng nhập
				và sinh mật khẩu 10 ký tự, bỏ sẵn các chữ dễ đọc nhầm như 0/O, 1/l.
				Mỗi lượt tối đa 300 người.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="khh_cap_nhieu">
				<?php wp_nonce_field( 'khh_cap' ); ?>
				<p>
					<button class="button button-primary">Cấp tài khoản hàng loạt</button>
					<label style="margin-left:14px"><input type="checkbox" id="khh-all"> chọn tất cả</label>
				</p>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th style="width:28px"></th><th>Nhân sự</th><th>Mã</th><th>Bộ phận</th><th>Quyền sẽ cấp</th><th>Tên đăng nhập dự kiến</th></tr></thead>
					<tbody>
					<?php foreach ( $chua as $id => $s ) : ?>
						<tr>
							<td><input type="checkbox" class="khh-ck" name="ids[]" value="<?php echo esc_attr( $id ); ?>"></td>
							<td><strong><?php echo esc_html( $s['name'] ); ?></strong></td>
							<td><?php echo esc_html( isset( $s['code'] ) ? $s['code'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $s['dept'] ) ? $s['dept'] : '' ); ?></td>
							<td><?php
								$r = ! empty( $s['role'] ) ? $s['role'] : 'staff';
								echo esc_html( isset( $roles[ $r ] ) ? $roles[ $r ] : $r );
							?></td>
							<td><code><?php echo esc_html( khh_suggest_login( $s ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</form>
			<script>
			document.getElementById('khh-all').addEventListener('change', function(){
				var on = this.checked;
				document.querySelectorAll('.khh-ck').forEach(function(c){ c.checked = on; });
			});
			</script>

			<h3 style="margin-top:26px">Cấp cho một người, tự đặt tên và mật khẩu</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="khh_cap_mot">
				<?php wp_nonce_field( 'khh_cap' ); ?>
				<table class="form-table" style="max-width:760px">
					<tr><th><label for="staff_id">Nhân sự</label></th>
						<td><select id="staff_id" name="staff_id">
							<?php foreach ( $chua as $id => $s ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>"><?php
									echo esc_html( $s['name'] . ( ! empty( $s['code'] ) ? ' · ' . $s['code'] : '' ) );
								?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<tr><th><label for="login">Tên đăng nhập</label></th>
						<td><input id="login" name="login" class="regular-text" placeholder="để trống = lấy mã nhân sự">
							<p class="description">Chỉ chữ thường, số, dấu chấm, gạch ngang và gạch dưới.</p></td></tr>
					<tr><th><label for="pass">Mật khẩu</label></th>
						<td><input id="pass" name="pass" class="regular-text" placeholder="để trống = máy tự sinh 10 ký tự">
							<p class="description">Tự đặt thì phải từ 8 ký tự trở lên.</p></td></tr>
				</table>
				<p><button class="button button-primary">Cấp tài khoản</button></p>
			</form>
		<?php endif; ?>

		<h2 style="margin-top:30px">Đã có tài khoản — <?php echo count( $roi ); ?> người</h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Nhân sự</th><th>Tên đăng nhập</th><th>Quyền</th><th style="width:200px">Thao tác</th></tr></thead>
			<tbody>
			<?php if ( ! $roi ) : ?>
				<tr><td colspan="4">Chưa ai có tài khoản.</td></tr>
			<?php endif; ?>
			<?php foreach ( $roi as $id => $cap ) : ?>
				<?php list( $s, $u ) = $cap; ?>
				<tr>
					<td><strong><?php echo esc_html( $s['name'] ); ?></strong>
						<span class="description">· <?php echo esc_html( isset( $s['code'] ) ? $s['code'] : '' ); ?></span></td>
					<td><code><?php echo esc_html( $u->user_login ); ?></code></td>
					<td><?php
						$r = khh_user_role( $u->ID );
						echo esc_html( isset( $roles[ $r ] ) ? $roles[ $r ] : $r );
					?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							onsubmit="return confirm('Đặt lại mật khẩu cho <?php echo esc_attr( $s['name'] ); ?>? Mật khẩu cũ sẽ hết dùng được ngay.');">
							<input type="hidden" name="action" value="khh_doi_mk">
							<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
							<?php wp_nonce_field( 'khh_cap' ); ?>
							<button class="button button-small">Đặt lại mật khẩu</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
