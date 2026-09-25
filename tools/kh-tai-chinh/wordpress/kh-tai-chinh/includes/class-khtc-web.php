<?php
/**
 * Bản chạy ngoài web: cùng màn hình, nhưng ở địa chỉ công khai của website
 * thay vì trong wp-admin.
 *
 * https://tenmien.vn/tai-chinh/            — Tổng quan
 * https://tenmien.vn/tai-chinh/ngan-hang/  — Ngân hàng
 * https://tenmien.vn/tai-chinh/giao-dich/  — Giao dịch / Sao kê
 * https://tenmien.vn/tai-chinh/doi-soat/   — Đối soát
 *
 * VẪN PHẢI ĐĂNG NHẬP. "Ra web ngoài" ở đây là đổi địa chỉ và bỏ khung wp-admin,
 * không phải mở cho người lạ: chưa đăng nhập thì đá về trang đăng nhập, đăng
 * nhập rồi mà không đủ quyền thì báo thẳng. Số dư ngân hàng của công ty không
 * phải thứ để công khai.
 *
 * Trang tự dựng HTML riêng, KHÔNG gọi get_header() của theme. Theme nào cũng có
 * CSS riêng cho bảng và nút; mượn khung theme là mỗi lần đổi giao diện website
 * thì bảng tài chính lại vỡ một kiểu.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Web {

	const SLUG = 'tai-chinh';

	/**
	 * Menu gom theo nhóm. Mười bốn mục xếp phẳng thì tìm mục nào cũng phải đọc
	 * hết cả hàng; gom lại thì mắt nhảy thẳng tới nhóm rồi mới tới mục.
	 *
	 * Đây CHỈ là cách bày menu. Danh sách màn hình hợp lệ vẫn là man_hinh() —
	 * một chỗ duy nhất quyết định đường dẫn nào chạy được, để đổi cách bày
	 * không vô tình mở hay khoá mất một trang.
	 */
	public static function nhom() {
		return array(
			'Tổng quan' => array( '', 'bao-cao' ),
			'Dòng tiền' => array( 'ngan-hang', 'dan-tho', 'giao-dich', 'danh-muc-diem', 'chi-phi' ),
			'Đối soát'  => array( 'doi-soat', 'doi-soat-chi-phi' ),
			'Hoá đơn'   => array( 'sinh-hoa-don', 'hoa-don-ra', 'hoa-don-vao' ),
			'Sổ'        => array( 'cong-no', 'phap-danh', 'ho-so' ),
			// Người dùng chỉ hiện với quản trị viên: kế toán thấy một mục
			// bấm vào là bị từ chối thì thà đừng hiện.
			'Hệ thống'  => KHTC_NguoiDung::duoc_quan_ly()
				? array( 'nhat-ky', 'sao-luu', 'nguoi-dung' )
				: array( 'nhat-ky', 'sao-luu' ),
		);
	}

	/** Nhãn ngắn trên menu dọc — bỏ phần thừa mà tiêu đề trang đã nói. */
	public static function nhan_ngan() {
		return array(
			''                 => 'Tổng quan',
			'bao-cao'          => 'Báo cáo',
			'ngan-hang'        => 'Ngân hàng',
			'giao-dich'        => 'Giao dịch / Sao kê',
			'chi-phi'          => 'Chi phí',
			'doi-soat'         => 'Cổng thanh toán',
			'doi-soat-chi-phi' => 'Chi phí',
			'dan-tho'          => 'Dán thô',
			'danh-muc-diem'    => 'Danh mục điểm',
			'sinh-hoa-don'     => 'Sinh từ sao kê',
			'hoa-don-ra'       => 'Đầu ra',
			'hoa-don-vao'      => 'Đầu vào',
			'cong-no'          => 'Công nợ',
			'phap-danh'        => 'Pháp danh',
			'ho-so'            => 'Hồ sơ',
			'nhat-ky'          => 'Nhật ký',
			'sao-luu'          => 'Sao lưu',
			'nguoi-dung'       => 'Người dùng',
		);
	}

	/** Màn hình → nhãn đầy đủ. Đây là danh sách đường dẫn hợp lệ. */
	public static function man_hinh() {
		return array(
			''           => 'Tổng quan',
			'ngan-hang'  => 'Ngân hàng',
			'giao-dich'  => 'Giao dịch / Sao kê',
			'doi-soat'   => 'Đối soát',
			'chi-phi'    => 'Chi phí',
			'doi-soat-chi-phi' => 'Đối soát chi phí',
			'dan-tho'       => 'Dán thô',
			'danh-muc-diem' => 'Danh mục điểm',
			'sinh-hoa-don'  => 'Sinh hoá đơn từ sao kê',
			'hoa-don-ra' => 'Hoá đơn đầu ra',
			'hoa-don-vao' => 'Hoá đơn đầu vào',
			'cong-no'    => 'Công nợ',
			'phap-danh'  => 'Pháp danh',
			'ho-so'      => 'Hồ sơ',
			'bao-cao'    => 'Báo cáo',
			'nhat-ky'    => 'Nhật ký',
			'sao-luu'    => 'Sao lưu',
			'nguoi-dung' => 'Người dùng',
		);
	}

	public static function khoi_dong() {
		add_action( 'init', array( __CLASS__, 'them_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'them_bien' ) );
		add_action( 'template_redirect', array( __CLASS__, 'hien' ) );
	}

	public static function them_rule() {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?khtc_man=tong-quan', 'top' );
		add_rewrite_rule( '^' . self::SLUG . '/([a-z0-9-]+)/?$', 'index.php?khtc_man=$matches[1]', 'top' );
	}

	public static function them_bien( $v ) {
		$v[] = 'khtc_man';
		return $v;
	}

	/** Bật/tắt plugin thì nạp lại luật đường dẫn, nếu không /tai-chinh/ ra 404. */
	public static function nap_lai_rule() {
		self::them_rule();
		flush_rewrite_rules();
	}

	/** Đang ở bản web ngoài hay đang trong wp-admin. */
	public static function dang_o_web() {
		return (bool) get_query_var( 'khtc_man' );
	}

	/**
	 * Địa chỉ một màn hình, tự đúng theo nơi đang đứng.
	 *
	 * Không có permalink đẹp thì rewrite rule không chạy, nên rơi về ?khtc_man=
	 * — biến này đã đăng ký ở query_vars nên WordPress vẫn nhận.
	 */
	public static function duong_dan( $man = '' ) {
		$man = $man ? $man : 'tong-quan';
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . self::SLUG . '/' . ( 'tong-quan' === $man ? '' : $man . '/' ) );
		}
		return home_url( '/?khtc_man=' . $man );
	}

	public static function hien() {
		$man = get_query_var( 'khtc_man' );
		if ( ! $man ) { return; }
		$man = ( 'tong-quan' === $man ) ? '' : $man;
		if ( ! array_key_exists( $man, self::man_hinh() ) ) { return; }

		if ( ! is_user_logged_in() ) {
			// Trang đăng nhập RIÊNG của Tài Chính K&H, không đẩy sang wp-login:
			// kế toán không cần biết WordPress là gì, chỉ cần tên và mật khẩu
			// quản trị cấp ở màn hình Người dùng.
			status_header( 200 );
			nocache_headers();
			self::dang_nhap( $man );
			exit;
		}

		status_header( 200 );
		nocache_headers();
		self::khung( $man );
		exit;
	}

	// ------------------------------------------------------------ đăng nhập

	/** Khoá tạm theo IP: 5 lần sai → khoá 15 phút. Nhớ bằng transient, hết hạn tự mở. */
	const SAI_TOI_DA = 5;
	const KHOA_GIAY  = 900;

	/**
	 * Khoá theo cặp (IP, tên đăng nhập). Cả văn phòng thường ra Internet qua
	 * MỘT IP: khoá theo IP thì một người gõ sai năm lần là cả phòng bị khoá.
	 * Theo cặp thì dò mật khẩu một tài khoản vẫn bị chặn, người khác vẫn vào.
	 */
	private static function khoa_ip_khoa( $ten = '' ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return 'khtc_khoa_' . md5( $ip . '|' . strtolower( (string) $ten ) );
	}
	public static function dang_bi_khoa( $ten = '' ) {
		$t = get_transient( self::khoa_ip_khoa( $ten ) );
		return is_array( $t ) && (int) ( $t['sai'] ?? 0 ) >= self::SAI_TOI_DA;
	}
	public static function ghi_sai( $ten = '' ) {
		$k = self::khoa_ip_khoa( $ten );
		$t = get_transient( $k );
		$sai = is_array( $t ) ? (int) ( $t['sai'] ?? 0 ) + 1 : 1;
		set_transient( $k, array( 'sai' => $sai ), self::KHOA_GIAY );
		return $sai;
	}
	public static function xoa_sai( $ten = '' ) { delete_transient( self::khoa_ip_khoa( $ten ) ); }

	/**
	 * Nhận form đăng nhập rồi in trang. Sai thì chỉ nói "sai tên hoặc mật
	 * khẩu" — không nói cái nào sai, không nói tên có tồn tại không.
	 */
	public static function dang_nhap( $man = '' ) {
		$loi = '';
		if ( isset( $_POST['khtc_dang_nhap'] ) ) {
			$ten = sanitize_user( wp_unslash( (string) ( $_POST['ten'] ?? '' ) ), true );
			$mk  = (string) wp_unslash( $_POST['mk'] ?? '' );
			if ( ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'khtc_dang_nhap' ) ) {
				$loi = 'Phiên đã hết hạn, gửi lại.';
			} elseif ( self::dang_bi_khoa( $ten ) ) {
				$loi = 'Sai quá ' . self::SAI_TOI_DA . ' lần. Chờ 15 phút rồi thử lại, hoặc nhờ quản trị đặt lại mật khẩu.';
			} else {
				$u   = ( '' === $ten || '' === $mk ) ? new WP_Error( 'trong', 'trống' ) : wp_signon( array( 'user_login' => $ten, 'user_password' => $mk, 'remember' => ! empty( $_POST['nho'] ) ), is_ssl() );
				if ( is_wp_error( $u ) ) {
					$con = self::SAI_TOI_DA - self::ghi_sai( $ten );
					$loi = 'Sai tên đăng nhập hoặc mật khẩu.' . ( $con > 0 && $con <= 2 ? ' Còn ' . $con . ' lần trước khi khoá tạm.' : '' );
				} elseif ( ! user_can( $u, KHTC_CAP ) ) {
					wp_logout();
					$loi = 'Tài khoản ' . $ten . ' chưa được cấp quyền vào sổ. Nhờ quản trị thêm ở màn hình Người dùng.';
				} else {
					self::xoa_sai( $ten );
					wp_safe_redirect( self::duong_dan( $man ) );
					exit;
				}
			}
		}
		self::trang_dang_nhap( $loi );
	}

	/** Chỉ in trang đăng nhập — tách riêng để kiểm được mà không cần phiên thật. */
	public static function trang_dang_nhap( $loi = '' ) {
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Đăng nhập — Tài Chính K&H</title>
<link rel="stylesheet" href="<?php echo esc_url( KHTC_URL . 'assets/khtc.css?v=' . KHTC_VERSION ); ?>">
</head>
<body class="khtc-web khtc-trang-dn">
<main class="khtc-dang-nhap">
	<div class="khtc-hieu"><span>K&amp;H</span> Tài Chính</div>
	<h1>Đăng nhập</h1>
	<?php if ( $loi ) : ?><p class="khtc-canh-bao"><?php echo esc_html( $loi ); ?></p><?php endif; ?>
	<form method="post" autocomplete="on">
		<?php wp_nonce_field( 'khtc_dang_nhap' ); ?>
		<label>Tên đăng nhập<input type="text" name="ten" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus></label>
		<label>Mật khẩu<input type="password" name="mk" autocomplete="current-password" required></label>
		<label class="khtc-tick-nho"><input type="checkbox" name="nho" value="1"> Ghi nhớ trên máy này</label>
		<button type="submit" name="khtc_dang_nhap" value="1" class="button button-primary">Vào sổ</button>
	</form>
	<p class="khtc-sub">Chưa có tài khoản hay quên mật khẩu: nhờ quản trị vào <strong>Hệ thống → Người dùng</strong> tạo hoặc đặt lại. Mật khẩu chỉ hiện một lần lúc đặt.</p>
</main>
</body>
</html>
<?php
	}

	private static function khung( $man ) {
		// Nút đổi KH Cũ / KH Mới nằm ở khung chung, nên nhận ở đây — trước khi
		// bất kỳ màn hình nào đọc dữ liệu. Từng màn hình tự gọi thì thiếu một
		// màn hình là nút ở đó bấm không có tác dụng (đã xảy ra với Danh mục điểm).
		KHTC_UI::nhan_doi_cty();
		$du_quyen = current_user_can( KHTC_CAP );
		$nhan     = self::man_hinh();
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $nhan[ $man ] . ' — Tài Chính K&H' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( KHTC_URL . 'assets/khtc.css?v=' . KHTC_VERSION ); ?>">
</head>
<body class="khtc-web">
<div class="khtc-khung">
<aside class="khtc-ben">
	<div class="khtc-hieu"><span>K&amp;H</span> Tài Chính</div>
	<nav>
		<?php foreach ( self::nhom() as $ten_nhom => $muc ) : ?>
			<div class="khtc-nhom"><?php echo esc_html( $ten_nhom ); ?></div>
			<?php foreach ( $muc as $k ) : ?>
				<a href="<?php echo esc_url( self::duong_dan( $k ) ); ?>"<?php echo $k === $man ? ' class="dang-o" aria-current="page"' : ''; ?>><?php echo esc_html( self::nhan_ngan()[ $k ] ?? $nhan[ $k ] ); ?></a>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</nav>
	<div class="khtc-ai">
		<span class="khtc-ai-ten"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
		<a href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>
		<a href="<?php echo esc_url( wp_logout_url( self::duong_dan() ) ); ?>">Thoát</a>
	</div>
</aside>
<main class="khtc">
<?php
		if ( ! $du_quyen ) {
			echo '<div class="khtc-panel"><div class="khtc-trong">Tài khoản <strong>'
				. esc_html( wp_get_current_user()->user_login )
				. '</strong> không có quyền mở trang này. Nhờ quản trị nâng vai trò lên Editor trở lên.</div></div>';
		} else {
			KHTC_Trang::hien( $man );
		}
?>
</main>
</div>
</body>
</html>
<?php
	}
}
