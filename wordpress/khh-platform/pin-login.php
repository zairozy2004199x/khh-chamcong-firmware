<?php
/**
 * Đăng nhập nhanh bằng mã PIN — mỗi người một mã, tiện cho lúc chạy thử nội bộ.
 *
 * Đây là cửa vào phụ, yếu hơn mật khẩu, nên luôn kèm chốt chặn:
 *  - Mặc định không có mã nào. Chỉ Quản trị viên WordPress mới tạo được.
 *  - Mã lưu dạng băm (wp_hash_password), không lưu số gốc ở đâu cả.
 *  - Bắt buộc có hạn dùng (mặc định 8 giờ), hết hạn là tự vô hiệu.
 *  - Sai 5 lần thì khoá 15 phút theo địa chỉ IP.
 *  - Đăng nhập bằng PIN chỉ tạo phiên tạm (đóng trình duyệt là hết).
 * Thử xong nhớ bấm "Xoá hết mã".
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Danh sách mã PIN đang có.
 *
 * Mỗi phần tử: array( 'user' => id, 'hash' => chuỗi băm, 'expires' => timestamp|0, 'created' => timestamp )
 */
function khh_pin_list() {
	$s    = khh_settings();
	$pins = isset( $s['pins'] ) && is_array( $s['pins'] ) ? $s['pins'] : array();

	// Tương thích ngược với bản 1.2.0 chỉ có một mã.
	if ( ! $pins && ! empty( $s['pin_enabled'] ) && ! empty( $s['pin_hash'] ) && ! empty( $s['pin_user'] ) ) {
		$pins[] = array(
			'user'    => (int) $s['pin_user'],
			'hash'    => $s['pin_hash'],
			'expires' => (int) $s['pin_expires'],
			'created' => time(),
		);
	}
	return $pins;
}

/** Chỉ những mã còn hạn và còn tài khoản. */
function khh_pin_valid_list() {
	$out = array();
	foreach ( khh_pin_list() as $i => $p ) {
		if ( ! empty( $p['expires'] ) && (int) $p['expires'] < time() ) {
			continue;
		}
		if ( ! get_user_by( 'id', (int) $p['user'] ) ) {
			continue;
		}
		$out[ $i ] = $p;
	}
	return $out;
}

function khh_pin_active() {
	return (bool) khh_pin_valid_list();
}

function khh_pin_save_list( $pins ) {
	$s              = khh_settings();
	$s['pins']      = array_values( $pins );
	$s['pin_enabled'] = 0; // khoá kiểu cũ lại, từ nay chỉ dùng danh sách.
	$s['pin_hash']    = '';
	$s['pin_user']    = 0;
	$s['pin_expires'] = 0;
	update_option( 'khh_settings', $s );
}

function khh_pin_ip_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return 'khh_pin_fail_' . md5( $ip );
}

/**
 * Thử một mã PIN. Trả về chuỗi rỗng nếu đăng nhập được, ngược lại là câu báo lỗi.
 */
function khh_pin_attempt( $code ) {
	$key = khh_pin_ip_key();

	if ( ! khh_pin_active() ) {
		return 'Chưa có mã PIN nào còn hiệu lực.';
	}

	$fails = (int) get_transient( $key );
	if ( $fails >= 5 ) {
		return 'Sai quá 5 lần. Chờ 15 phút rồi thử lại, hoặc đăng nhập bằng mật khẩu WordPress.';
	}

	$code = preg_replace( '/\D/', '', (string) $code );
	$hit  = null;
	if ( '' !== $code ) {
		foreach ( khh_pin_valid_list() as $p ) {
			if ( wp_check_password( $code, $p['hash'] ) ) {
				$hit = $p;
				break;
			}
		}
	}

	if ( ! $hit ) {
		set_transient( $key, $fails + 1, 15 * MINUTE_IN_SECONDS );
		$left = 4 - $fails;
		return 'Mã PIN không đúng.' . ( $left > 0 ? ' Còn ' . $left . ' lần thử.' : '' );
	}

	$user = get_user_by( 'id', (int) $hit['user'] );
	if ( ! $user ) {
		return 'Tài khoản gắn với mã PIN không còn tồn tại.';
	}

	delete_transient( $key );
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, false );
	do_action( 'wp_login', $user->user_login, $user );
	return '';
}

/* ------------------------------------------------------------------ *
 * Trang nhập mã: /?khh_pin=1
 * ------------------------------------------------------------------ */

add_action( 'init', 'khh_pin_page', 2 );
function khh_pin_page() {
	if ( ! isset( $_GET['khh_pin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/?khh_app=1' ) );
		exit;
	}

	$err = '';
	if ( isset( $_POST['khh_pin_code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$nonce = isset( $_POST['khh_pin_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['khh_pin_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'khh_pin' ) ) {
			$err = 'Phiên nhập mã đã cũ. Tải lại trang rồi nhập lại.';
		} else {
			$err = khh_pin_attempt( wp_unslash( $_POST['khh_pin_code'] ) ); // phpcs:ignore
			if ( '' === $err ) {
				wp_safe_redirect( home_url( '/?khh_app=1' ) );
				exit;
			}
		}
	}

	nocache_headers();
	$count = count( khh_pin_valid_list() );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Đăng nhập nhanh — Nền tảng K&amp;H</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( KHH_URL . 'assets/app.css?v=' . KHH_VERSION ); ?>">
<style>
body{margin:0;min-height:100dvh;display:grid;place-items:center;
	background:linear-gradient(165deg,#0E2036,#17395C 45%,#1C4E72)}
.box{width:min(380px,calc(100vw - 32px));background:var(--panel);border-radius:6px;
	box-shadow:0 18px 50px rgba(8,20,34,.45);padding:26px 24px}
.box h1{font-size:19px;margin-bottom:4px}
.pin{width:100%;font-size:30px;letter-spacing:.4em;text-align:center;padding:12px 10px;
	border:1px solid var(--line-2);border-radius:4px;background:var(--panel-2);color:var(--ink);
	font-variant-numeric:tabular-nums;margin:16px 0 12px}
.err{background:var(--red-weak);color:var(--red);border:1px solid var(--red);
	border-radius:3px;padding:8px 10px;font-size:12.5px;margin-top:14px}
</style>
</head>
<body>
<div class="box">
	<h1>Đăng nhập nhanh</h1>
	<p class="by"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — Nền tảng K&amp;H</p>

	<?php if ( ! $count ) : ?>
		<p class="err">Chưa có mã PIN nào còn hiệu lực.</p>
		<p style="margin-top:14px">
			<a class="btn ghost" style="display:block;text-align:center;padding:10px"
				href="<?php echo esc_url( wp_login_url( home_url( '/?khh_app=1' ) ) ); ?>">Đăng nhập bằng WordPress</a>
		</p>
	<?php else : ?>
		<?php if ( $err ) : ?>
			<p class="err"><?php echo esc_html( $err ); ?></p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'khh_pin', 'khh_pin_nonce' ); ?>
			<input class="pin" type="password" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
				name="khh_pin_code" maxlength="8" required autofocus aria-label="Mã PIN" placeholder="••••">
			<button class="btn" style="width:100%;padding:10px">Vào nền tảng</button>
		</form>
		<p class="by" style="margin-top:14px">Nhập mã của riêng bạn — hệ thống tự biết bạn là ai.</p>
		<p style="margin-top:12px;text-align:center">
			<a class="linkbtn" href="<?php echo esc_url( wp_login_url( home_url( '/?khh_app=1' ) ) ); ?>">Đăng nhập bằng WordPress</a>
		</p>
	<?php endif; ?>
</div>
</body>
</html>
	<?php
	exit;
}

/** Thêm liên kết vào trang đăng nhập WordPress khi có mã đang hiệu lực. */
add_action( 'login_message', 'khh_pin_login_link', 20 );
function khh_pin_login_link( $message ) {
	if ( ! khh_pin_active() ) {
		return $message;
	}
	return $message . '<p style="text-align:center;margin:0 0 16px">'
		. '<a href="' . esc_url( home_url( '/?khh_pin=1' ) ) . '">Đăng nhập nhanh bằng mã PIN</a></p>';
}

/* ------------------------------------------------------------------ *
 * Trang quản trị: quản lý mã PIN
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'khh_pin_menu', 21 );
function khh_pin_menu() {
	add_submenu_page( 'khh-platform', 'Mã PIN đăng nhập', 'Mã PIN đăng nhập', 'manage_options', 'khh-pin', 'khh_pin_admin_page' );
}

add_action( 'admin_post_khh_save_pin', 'khh_save_pin' );
function khh_save_pin() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'khh_pin_cfg' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$pins  = khh_pin_list();
	$hours = isset( $_POST['pin_hours'] ) ? (int) $_POST['pin_hours'] : 8;

	/* Xoá hết */
	if ( isset( $_POST['clear_all'] ) ) {
		khh_pin_save_list( array() );
		delete_transient( khh_pin_ip_key() );
		wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=off' ) );
		exit;
	}

	/* Xoá một mã */
	if ( isset( $_POST['remove'] ) ) {
		$i = (int) $_POST['remove'];
		unset( $pins[ $i ] );
		khh_pin_save_list( $pins );
		wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=removed' ) );
		exit;
	}

	/* Tạo mã ngẫu nhiên cho hàng loạt tài khoản */
	if ( isset( $_POST['bulk'] ) ) {
		$ids   = isset( $_POST['bulk_users'] ) ? array_map( 'intval', (array) $_POST['bulk_users'] ) : array();
		$shown = array();
		foreach ( $ids as $uid ) {
			$user = get_user_by( 'id', $uid );
			if ( ! $user ) {
				continue;
			}
			// Bỏ mã cũ của người này.
			foreach ( $pins as $k => $p ) {
				if ( (int) $p['user'] === $uid ) {
					unset( $pins[ $k ] );
				}
			}
			$code = '';
			do {
				$code = (string) wp_rand( 100000, 999999 );
			} while ( khh_pin_code_taken( $code, $pins ) );

			$pins[] = array(
				'user'    => $uid,
				'hash'    => wp_hash_password( $code ),
				'expires' => $hours > 0 ? time() + $hours * HOUR_IN_SECONDS : 0,
				'created' => time(),
			);
			$shown[] = array(
				'name' => $user->display_name,
				'role' => khh_user_role( $uid ),
				'code' => $code,
			);
		}
		khh_pin_save_list( $pins );
		set_transient( 'khh_pin_shown_' . get_current_user_id(), $shown, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=bulk' ) );
		exit;
	}

	/* Thêm một mã tự đặt */
	$code = isset( $_POST['pin_code'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['pin_code'] ) ) : ''; // phpcs:ignore
	$uid  = isset( $_POST['pin_user'] ) ? (int) $_POST['pin_user'] : 0;

	if ( strlen( $code ) < 4 || strlen( $code ) > 8 ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=short' ) );
		exit;
	}
	if ( ! get_user_by( 'id', $uid ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=nouser' ) );
		exit;
	}
	if ( khh_pin_code_taken( $code, $pins ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=dup' ) );
		exit;
	}
	foreach ( $pins as $k => $p ) {
		if ( (int) $p['user'] === $uid ) {
			unset( $pins[ $k ] );
		}
	}
	$pins[] = array(
		'user'    => $uid,
		'hash'    => wp_hash_password( $code ),
		'expires' => $hours > 0 ? time() + $hours * HOUR_IN_SECONDS : 0,
		'created' => time(),
	);
	khh_pin_save_list( $pins );
	delete_transient( khh_pin_ip_key() );

	wp_safe_redirect( admin_url( 'admin.php?page=khh-pin&pin=on' ) );
	exit;
}

/** Mã này đã dùng cho người khác chưa (hai người trùng mã thì không biết là ai). */
function khh_pin_code_taken( $code, $pins ) {
	foreach ( $pins as $p ) {
		if ( wp_check_password( $code, $p['hash'] ) ) {
			return true;
		}
	}
	return false;
}

function khh_pin_admin_page() {
	$pins   = khh_pin_list();
	$valid  = khh_pin_valid_list();
	$notice = isset( $_GET['pin'] ) ? sanitize_key( wp_unslash( $_GET['pin'] ) ) : ''; // phpcs:ignore
	$shown  = get_transient( 'khh_pin_shown_' . get_current_user_id() );
	$msgs   = array(
		'on'      => array( 'success', 'Đã lưu mã PIN.' ),
		'off'     => array( 'success', 'Đã xoá toàn bộ mã PIN.' ),
		'removed' => array( 'success', 'Đã xoá mã.' ),
		'bulk'    => array( 'success', 'Đã tạo mã cho các tài khoản đã chọn — xem bảng bên dưới và gửi cho từng người.' ),
		'short'   => array( 'error', 'Mã PIN phải từ 4 đến 8 chữ số.' ),
		'nouser'  => array( 'error', 'Không tìm thấy tài khoản đã chọn.' ),
		'dup'     => array( 'error', 'Mã này đã dùng cho người khác. Chọn mã khác.' ),
	);
	$roles = array(
		'owner'   => 'Chủ sở hữu',
		'admin'   => 'Quản trị',
		'manager' => 'Quản lý',
		'staff'   => 'Nhân viên',
	);
	?>
	<div class="wrap">
		<h1>Nền tảng K&amp;H — Mã PIN đăng nhập</h1>

		<?php if ( isset( $msgs[ $notice ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msgs[ $notice ][0] ); ?>">
				<p><?php echo esc_html( $msgs[ $notice ][1] ); ?></p></div>
		<?php endif; ?>

		<div class="notice notice-warning inline" style="margin:14px 0;padding:10px 12px">
			<p style="margin:0"><strong>Mã PIN là cửa vào phụ, chỉ nên dùng khi chạy thử.</strong>
			Ai biết mã là vào được đúng tài khoản đó với đủ quyền của tài khoản đó.
			Thử xong bấm <em>Xoá hết mã</em>, hoặc để mã tự hết hạn.</p>
		</div>

		<?php if ( is_array( $shown ) && $shown ) : ?>
			<h2>Mã vừa tạo — chỉ hiện một lần</h2>
			<p class="description">Chép và gửi cho từng người ngay bây giờ. Rời trang là không xem lại được.</p>
			<table class="widefat striped" style="max-width:560px;margin-bottom:22px">
				<thead><tr><th>Người dùng</th><th>Quyền</th><th style="width:120px">Mã PIN</th></tr></thead>
				<tbody>
				<?php foreach ( $shown as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row['name'] ); ?></strong></td>
						<td><?php echo esc_html( isset( $roles[ $row['role'] ] ) ? $roles[ $row['role'] ] : $row['role'] ); ?></td>
						<td><code style="font-size:17px;letter-spacing:2px"><?php echo esc_html( $row['code'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Mã đang có (<?php echo count( $valid ); ?>)</h2>
		<table class="widefat striped" style="max-width:760px">
			<thead><tr><th>Tài khoản</th><th>Quyền</th><th>Hết hạn</th><th style="width:100px">Thao tác</th></tr></thead>
			<tbody>
			<?php if ( ! $pins ) : ?>
				<tr><td colspan="4">Chưa có mã nào.</td></tr>
			<?php endif; ?>
			<?php foreach ( $pins as $i => $p ) : ?>
				<?php
				$u       = get_user_by( 'id', (int) $p['user'] );
				$expired = ! empty( $p['expires'] ) && (int) $p['expires'] < time();
				?>
				<tr<?php echo $expired ? ' style="opacity:.55"' : ''; ?>>
					<td><strong><?php echo esc_html( $u ? $u->display_name : 'tài khoản đã xoá' ); ?></strong>
						<?php if ( $u ) : ?><span class="description">· <?php echo esc_html( $u->user_login ); ?></span><?php endif; ?></td>
					<td><?php echo esc_html( $u ? $roles[ khh_user_role( $u->ID ) ] : '—' ); ?></td>
					<td><?php
						if ( empty( $p['expires'] ) ) {
							echo 'Không đặt hạn';
						} elseif ( $expired ) {
							echo '<span style="color:#b32d2e">đã hết hạn</span>';
						} else {
							echo esc_html( wp_date( 'H:i d/m/Y', (int) $p['expires'] ) );
						}
					?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="khh_save_pin">
							<input type="hidden" name="remove" value="<?php echo (int) $i; ?>">
							<?php wp_nonce_field( 'khh_pin_cfg' ); ?>
							<button class="button button-small">Xoá</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pins ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
			<input type="hidden" name="action" value="khh_save_pin">
			<input type="hidden" name="clear_all" value="1">
			<?php wp_nonce_field( 'khh_pin_cfg' ); ?>
			<?php submit_button( 'Xoá hết mã', 'delete', 'submit', false ); ?>
		</form>
		<?php endif; ?>

		<hr style="margin:26px 0">

		<h2>Tạo mã cho nhiều người cùng lúc</h2>
		<p class="description">Hệ thống tự sinh mã 6 số cho từng tài khoản đã chọn và hiện một lần để bạn gửi đi.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="khh_save_pin">
			<input type="hidden" name="bulk" value="1">
			<?php wp_nonce_field( 'khh_pin_cfg' ); ?>
			<table class="widefat striped" style="max-width:560px;margin-bottom:10px">
				<thead><tr><th style="width:34px"><input type="checkbox" onclick="document.querySelectorAll('.khh-bulk').forEach(function(c){c.checked=this.checked}.bind(this))"></th>
					<th>Tài khoản</th><th>Quyền trong nền tảng</th></tr></thead>
				<tbody>
				<?php foreach ( get_users( array( 'number' => 100 ) ) as $u ) : ?>
					<tr>
						<td><input class="khh-bulk" type="checkbox" name="bulk_users[]" value="<?php echo (int) $u->ID; ?>"></td>
						<td><strong><?php echo esc_html( $u->display_name ); ?></strong>
							<span class="description">· <?php echo esc_html( $u->user_login ); ?></span></td>
						<td><?php echo esc_html( $roles[ khh_user_role( $u->ID ) ] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<label>Tự hết hạn sau
				<select name="pin_hours">
					<option value="1">1 giờ</option>
					<option value="8" selected>8 giờ</option>
					<option value="24">1 ngày</option>
					<option value="168">7 ngày</option>
					<option value="0">Không đặt hạn (không khuyến nghị)</option>
				</select>
			</label>
			<?php submit_button( 'Tạo mã cho những người đã chọn', 'primary', 'submit', false ); ?>
		</form>

		<hr style="margin:26px 0">

		<h2>Tự đặt mã cho một người</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="khh_save_pin">
			<?php wp_nonce_field( 'khh_pin_cfg' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="pin_user">Tài khoản</label></th>
					<td><select name="pin_user" id="pin_user">
						<?php foreach ( get_users( array( 'number' => 100 ) ) as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>" <?php selected( get_current_user_id(), $u->ID ); ?>>
								<?php echo esc_html( $u->display_name . ' — ' . $roles[ khh_user_role( $u->ID ) ] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Mỗi tài khoản chỉ giữ một mã; đặt mã mới là mã cũ của người đó bị thay.</p></td>
				</tr>
				<tr>
					<th><label for="pin_code">Mã PIN</label></th>
					<td><input type="text" id="pin_code" name="pin_code" inputmode="numeric" pattern="[0-9]{4,8}"
							maxlength="8" required class="regular-text" placeholder="4 đến 8 chữ số" autocomplete="off">
						<p class="description">Lưu dạng băm — sau khi lưu không xem lại được.</p></td>
				</tr>
				<tr>
					<th><label for="pin_hours2">Tự hết hạn sau</label></th>
					<td><select name="pin_hours" id="pin_hours2">
						<option value="1">1 giờ</option>
						<option value="8" selected>8 giờ</option>
						<option value="24">1 ngày</option>
						<option value="168">7 ngày</option>
						<option value="0">Không đặt hạn (không khuyến nghị)</option>
					</select></td>
				</tr>
			</table>
			<?php submit_button( 'Lưu mã này' ); ?>
		</form>

		<p class="description" style="max-width:760px">
			Địa chỉ nhập mã: <code><?php echo esc_html( home_url( '/?khh_pin=1' ) ); ?></code> —
			mỗi người nhập mã của mình, hệ thống tự biết là ai.
			Muốn nhiều tài khoản đăng nhập <em>cùng lúc trên một máy</em> thì mở thêm cửa sổ ẩn danh
			hoặc trình duyệt khác, vì mỗi trình duyệt chỉ giữ được một phiên.
		</p>
	</div>
	<?php
}
