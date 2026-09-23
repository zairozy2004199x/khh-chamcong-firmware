<?php
/**
 * Đăng nhập thử với tư cách người khác — công cụ kiểm thử cho Quản trị viên.
 *
 * Chỉ tài khoản có quyền quản trị (manage_options) mới bắt đầu được. Khi chuyển,
 * plugin đặt một cookie có chữ ký ghi lại "người thật" để quay về; cookie này
 * không giả mạo được vì ký bằng khoá bí mật của WordPress. Trong lúc đang xem
 * thay người khác luôn có thanh nhắc ở đáy màn hình.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KHH_SWITCH_COOKIE', 'khh_switch_back' );

function khh_switch_sign( $uid, $exp ) {
	return wp_hash( 'khh-switch|' . (int) $uid . '|' . (int) $exp );
}

/** Trả về id của "người thật" nếu đang xem thay người khác, ngược lại 0. */
function khh_switch_origin() {
	if ( empty( $_COOKIE[ KHH_SWITCH_COOKIE ] ) ) {
		return 0;
	}
	$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ KHH_SWITCH_COOKIE ] ) );
	$parts = explode( '|', $raw );
	if ( 3 !== count( $parts ) ) {
		return 0;
	}
	list( $uid, $exp, $sig ) = $parts;
	if ( (int) $exp < time() ) {
		return 0;
	}
	if ( ! hash_equals( khh_switch_sign( $uid, $exp ), $sig ) ) {
		return 0;
	}
	return (int) $uid;
}

function khh_switch_set_cookie( $uid ) {
	$exp   = time() + 8 * HOUR_IN_SECONDS;
	$value = $uid ? $uid . '|' . $exp . '|' . khh_switch_sign( $uid, $exp ) : '';
	setcookie(
		KHH_SWITCH_COOKIE,
		$value,
		$uid ? $exp : time() - 3600,
		defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
		defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
		is_ssl(),
		true
	);
	if ( ! $uid ) {
		unset( $_COOKIE[ KHH_SWITCH_COOKIE ] );
	}
}

/* ------------------------------------------------------------------ *
 * Chuyển sang tài khoản khác / quay về
 * ------------------------------------------------------------------ */

add_action( 'admin_post_khh_switch_to', 'khh_switch_to' );
function khh_switch_to() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'khh_switch' ) ) {
		wp_die( 'Chỉ Quản trị viên mới dùng được chức năng này.' );
	}
	$uid    = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$target = $uid ? get_user_by( 'id', $uid ) : null;
	if ( ! $target ) {
		wp_die( 'Không tìm thấy tài khoản.' );
	}

	$me = get_current_user_id();
	if ( $me === $target->ID ) {
		wp_safe_redirect( home_url( '/?khh_app=1' ) );
		exit;
	}

	// Giữ lại người thật để còn quay về (nếu đang thử rồi thì giữ nguyên người gốc).
	$origin = khh_switch_origin();
	khh_switch_set_cookie( $origin ? $origin : $me );

	wp_set_current_user( $target->ID );
	wp_set_auth_cookie( $target->ID, false );
	do_action( 'wp_login', $target->user_login, $target );

	wp_safe_redirect( home_url( '/?khh_app=1' ) );
	exit;
}

add_action( 'init', 'khh_switch_back', 3 );
function khh_switch_back() {
	if ( ! isset( $_GET['khh_switch_back'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$origin = khh_switch_origin();
	khh_switch_set_cookie( 0 );

	if ( $origin && get_user_by( 'id', $origin ) ) {
		$user = get_user_by( 'id', $origin );
		wp_set_current_user( $origin );
		wp_set_auth_cookie( $origin, false );
		do_action( 'wp_login', $user->user_login, $user );
		wp_safe_redirect( admin_url( 'admin.php?page=khh-switch&back=1' ) );
		exit;
	}
	wp_safe_redirect( home_url( '/?khh_app=1' ) );
	exit;
}

/** Dọn cookie khi đăng xuất hẳn. */
add_action( 'wp_logout', 'khh_switch_clear' );
function khh_switch_clear() {
	khh_switch_set_cookie( 0 );
}

/* ------------------------------------------------------------------ *
 * Thanh nhắc khi đang xem thay người khác
 * ------------------------------------------------------------------ */

function khh_switch_banner_html() {
	$origin = khh_switch_origin();
	if ( ! $origin || ! is_user_logged_in() ) {
		return '';
	}
	$me   = wp_get_current_user();
	$orig = get_user_by( 'id', $origin );
	if ( ! $orig || (int) $orig->ID === (int) $me->ID ) {
		return '';
	}
	$roles = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	$role = khh_user_role( $me->ID );
	ob_start();
	?>
	<div style="position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#B45309;color:#fff;
		font:500 13px/1.4 Roboto,-apple-system,'Segoe UI',Arial,sans-serif;
		padding:9px 14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;
		box-shadow:0 -2px 12px rgba(0,0,0,.25)">
		<span>Đang xem thử với tư cách <strong><?php echo esc_html( $me->display_name ); ?></strong>
			— quyền <?php echo esc_html( isset( $roles[ $role ] ) ? $roles[ $role ] : $role ); ?>.</span>
		<span style="flex:1"></span>
		<a href="<?php echo esc_url( home_url( '/?khh_switch_back=1' ) ); ?>"
			style="background:#fff;color:#B45309;padding:5px 12px;border-radius:3px;text-decoration:none;font-weight:500">
			Quay lại <?php echo esc_html( $orig->display_name ); ?></a>
	</div>
	<?php
	return ob_get_clean();
}

/** Trong app toàn màn hình. */
add_action( 'khh_app_footer', 'khh_switch_banner_app' );
function khh_switch_banner_app() {
	echo khh_switch_banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/** Trên giao diện ngoài và trong trang quản trị. */
add_action( 'wp_footer', 'khh_switch_banner_app' );
add_action( 'admin_footer', 'khh_switch_banner_app' );

/* ------------------------------------------------------------------ *
 * Trang chọn tài khoản để thử
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_switch_menu', 22 );
function khh_switch_menu() {
	add_submenu_page( 'khh-platform', 'Đăng nhập thử', 'Đăng nhập thử', 'manage_options', 'khh-switch', 'khh_switch_page' );
}

function khh_switch_page() {
	$roles = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H — Đăng nhập thử</h1>

		<?php if ( isset( $_GET['back'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p>Đã quay lại tài khoản của bạn.</p></div>
		<?php endif; ?>

		<p>Bấm một nút bên dưới là vào thẳng nền tảng với tư cách người đó, để xem họ thấy gì và
		bị chặn ở đâu. Trong lúc thử luôn có thanh màu cam ở đáy màn hình để quay lại tài khoản của bạn.</p>

		<div class="notice notice-warning inline" style="margin:14px 0;padding:10px 12px">
			<p style="margin:0">Chức năng này <strong>không hỏi mật khẩu của người kia</strong> — chỉ Quản trị viên
			dùng được, và mọi thao tác trong lúc thử sẽ được ghi nhận dưới tên người đó.
			Đừng dùng để đọc tin nhắn riêng của nhân viên.</p>
		</div>

		<table class="widefat striped" style="max-width:760px">
			<thead><tr><th>Tài khoản</th><th>Quyền trong nền tảng</th><th style="width:180px">Thao tác</th></tr></thead>
			<tbody>
			<?php foreach ( get_users( array( 'number' => 100 ) ) as $u ) : ?>
				<?php $is_me = get_current_user_id() === $u->ID; ?>
				<tr>
					<td><strong><?php echo esc_html( $u->display_name ); ?></strong>
						<span class="description">· <?php echo esc_html( $u->user_login ); ?>
						<?php echo $is_me ? ' — <em>bạn</em>' : ''; // phpcs:ignore ?></span></td>
					<td><?php echo esc_html( $roles[ khh_user_role( $u->ID ) ] ); ?></td>
					<td>
						<?php if ( ! $is_me ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="khh_switch_to">
							<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
							<?php wp_nonce_field( 'khh_switch' ); ?>
							<button class="button button-primary button-small">Đăng nhập thử</button>
						</form>
						<?php else : ?>
							<span class="description">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:26px">Muốn nhiều người đăng nhập cùng lúc</h2>
		<ul style="list-style:disc;margin-left:18px;max-width:760px">
			<li><strong>Trên cùng một máy:</strong> mỗi trình duyệt chỉ giữ được một phiên. Mở thêm
				cửa sổ ẩn danh, hoặc trình duyệt khác, rồi vào
				<code><?php echo esc_html( home_url( '/?khh_pin=1' ) ); ?></code> nhập mã PIN của người kia.</li>
			<li><strong>Nhiều máy / điện thoại:</strong> vào <em>Mã PIN đăng nhập</em>, chọn nhiều tài khoản,
				bấm <em>Tạo mã cho những người đã chọn</em> — hệ thống sinh mã 6 số cho từng người,
				hiện một lần để bạn gửi đi.</li>
			<li>Dữ liệu là chung, nên hai người mở cùng lúc sẽ thấy thay đổi của nhau sau tối đa 8 giây.</li>
		</ul>
	</div>
	<?php
}
