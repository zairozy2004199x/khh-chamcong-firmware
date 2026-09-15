<?php
/**
 * Màn đăng nhập ngay trên nền tảng, không đá sang wp-login.php.
 *
 * Nhân viên mở /?khh_app=1 mà chưa đăng nhập thì thấy đúng giao diện K&H và gõ
 * tên đăng nhập + mật khẩu tại chỗ. wp-login.php vẫn dùng được như cũ cho quản trị.
 *
 * Chống dò mật khẩu: đếm số lần sai theo địa chỉ IP, sai 8 lần thì khoá 15 phút.
 * Câu báo lỗi cố tình nói chung chung — không hé lộ tên đăng nhập nào có thật.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KHH_LOGIN_MAX  = 8;
const KHH_LOGIN_KHOA = 15 * MINUTE_IN_SECONDS;

function khh_login_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	return 'khh_dn_' . md5( $ip );
}

function khh_login_dem() {
	$n = get_transient( khh_login_ip() );
	return $n ? (int) $n : 0;
}

function khh_login_ghi_sai() {
	set_transient( khh_login_ip(), khh_login_dem() + 1, KHH_LOGIN_KHOA );
}

function khh_login_go_khoa() {
	delete_transient( khh_login_ip() );
}

/**
 * Xử lý form đăng nhập gửi lên từ màn nền tảng.
 *
 * @return string Câu báo lỗi để hiện lại trên form; chuỗi rỗng nghĩa là không có gì để báo.
 */
function khh_login_xu_ly() {
	if ( ! isset( $_POST['khh_dn'] ) ) {
		return '';
	}
	if ( ! isset( $_POST['khh_dn_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['khh_dn_nonce'] ) ), 'khh_dang_nhap' ) ) {
		return 'Phiên nhập đã cũ. Tải lại trang rồi nhập lại.';
	}
	if ( khh_login_dem() >= KHH_LOGIN_MAX ) {
		return 'Sai quá nhiều lần. Chờ 15 phút rồi thử lại, hoặc nhờ quản trị đặt lại mật khẩu.';
	}

	$ten = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
	$mk  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $ten || '' === $mk ) {
		return 'Nhập đủ tên đăng nhập và mật khẩu.';
	}

	$user = wp_signon(
		array(
			'user_login'    => $ten,
			'user_password' => $mk,
			'remember'      => ! empty( $_POST['nho'] ),
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		khh_login_ghi_sai();
		$con = KHH_LOGIN_MAX - khh_login_dem();
		return 'Tên đăng nhập hoặc mật khẩu không đúng.'
			. ( $con > 0 && $con <= 3 ? ' Còn ' . $con . ' lần trước khi bị khoá 15 phút.' : '' );
	}

	khh_login_go_khoa();
	wp_set_current_user( $user->ID );

	$ve = isset( $_POST['ve'] ) ? esc_url_raw( wp_unslash( $_POST['ve'] ) ) : '';
	wp_safe_redirect( $ve ? $ve : home_url( '/?khh_app=1' ) );
	exit;
}

/** Thoát khỏi nền tảng rồi quay lại chính màn đăng nhập này. */
add_action( 'template_redirect', 'khh_login_thoat', 5 );
function khh_login_thoat() {
	if ( ! isset( $_GET['khh_thoat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$ma = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $ma, 'khh_thoat' ) ) {
		/* Mã cũ hoặc thiếu: đưa về lại nền tảng chứ không bày trang "liên kết hết hạn"
		   của WordPress — người dùng bấm Thoát lần nữa là có mã mới. */
		wp_safe_redirect( home_url( '/?khh_app=1' ) );
		exit;
	}
	wp_logout();
	wp_safe_redirect( home_url( '/?khh_app=1' ) );
	exit;
}

/** In ra màn đăng nhập toàn màn hình. */
function khh_login_screen( $loi = '' ) {
	$ten_site = get_bloginfo( 'name' );
	$logo     = khh_settings();
	$logo     = isset( $logo['logo'] ) ? $logo['logo'] : '';
	$ve       = home_url( '/?khh_app=1' );
	nocache_headers();
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập — Nền tảng K&amp;H</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
  font:400 15px/1.55 Roboto,-apple-system,"Segoe UI",Arial,sans-serif;color:#E8EEF6;
  background:
    radial-gradient(900px 520px at 15% -10%, #16436F 0%, rgba(22,67,111,0) 60%),
    radial-gradient(760px 480px at 88% 8%, #12365C 0%, rgba(18,54,92,0) 62%),
    #0B2036;}
.box{width:100%;max-width:380px}
.kh{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;margin:0 auto 16px;
  background:linear-gradient(160deg,#3AA6E8,#1A8CD8);color:#fff;font-weight:700;font-size:19px;
  letter-spacing:.02em;box-shadow:0 12px 26px -12px rgba(26,140,216,.7)}
.kh img{width:100%;height:100%;object-fit:contain;border-radius:14px}
h1{margin:0 0 4px;text-align:center;font-size:21px;font-weight:500;letter-spacing:-.01em}
.sub{margin:0 0 22px;text-align:center;font-size:13px;color:#9FB4CC}
form{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.10);
  border-radius:14px;padding:22px;backdrop-filter:blur(6px)}
label{display:block;font-size:11.5px;font-weight:500;letter-spacing:.07em;text-transform:uppercase;
  color:#9FB4CC;margin-bottom:6px}
input[type=text],input[type=password]{width:100%;font:inherit;font-size:15px;color:#fff;
  background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.14);border-radius:9px;
  padding:11px 13px;transition:border-color .15s,box-shadow .15s}
input[type=text]:focus,input[type=password]:focus{outline:none;border-color:#3AA6E8;
  box-shadow:0 0 0 4px rgba(58,166,232,.18)}
.fld{margin-bottom:14px}
.row{display:flex;align-items:center;gap:8px;margin:2px 0 18px;font-size:13px;color:#BFD0E3;
  text-transform:none;letter-spacing:0;font-weight:400}
.row input{width:16px;height:16px;accent-color:#1A8CD8;margin:0}
button{width:100%;font:inherit;font-size:16px;font-weight:500;color:#fff;cursor:pointer;
  background:linear-gradient(180deg,#3AA6E8,#1A8CD8);border:0;border-radius:9px;padding:12px;
  box-shadow:0 12px 24px -12px rgba(26,140,216,.8);transition:transform .15s,box-shadow .15s}
button:hover{transform:translateY(-1px);box-shadow:0 16px 30px -12px rgba(26,140,216,.9)}
.loi{background:rgba(216,58,58,.16);border:1px solid rgba(216,58,58,.45);color:#FFC9C9;
  border-radius:9px;padding:10px 13px;font-size:13.5px;margin-bottom:16px}
.chan{margin:18px 0 0;text-align:center;font-size:12.5px;color:#7F94AC;line-height:1.6}
.chan a{color:#8FC4EC;text-decoration:none}
.chan a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="box">
	<div class="kh"><?php
	if ( $logo ) {
		echo '<img src="' . esc_url( $logo ) . '" alt="">';
	} else {
		echo 'KH';
	}
	?></div>
	<h1>Nền tảng K&amp;H</h1>
	<p class="sub"><?php echo esc_html( $ten_site ); ?></p>

	<form method="post" action="<?php echo esc_url( $ve ); ?>">
		<?php if ( $loi ) : ?>
			<div class="loi"><?php echo esc_html( $loi ); ?></div>
		<?php endif; ?>
		<input type="hidden" name="khh_dn" value="1">
		<input type="hidden" name="ve" value="<?php echo esc_url( $ve ); ?>">
		<?php wp_nonce_field( 'khh_dang_nhap', 'khh_dn_nonce' ); ?>
		<div class="fld">
			<label for="log">Tên đăng nhập</label>
			<input id="log" name="log" type="text" autocomplete="username" autocapitalize="none"
				spellcheck="false" autofocus required>
		</div>
		<div class="fld">
			<label for="pwd">Mật khẩu</label>
			<input id="pwd" name="pwd" type="password" autocomplete="current-password" required>
		</div>
		<label class="row"><input type="checkbox" name="nho" value="1" checked> Ghi nhớ trên máy này</label>
		<button type="submit">Đăng nhập</button>
	</form>

	<p class="chan">
		Quên mật khẩu thì nhắn quản trị cấp lại — mật khẩu cũ không tra lại được.<br>
		<a href="<?php echo esc_url( wp_login_url( $ve ) ); ?>">Đăng nhập bằng trang quản trị WordPress</a>
	</p>
</div>
</body>
</html>
	<?php
}
