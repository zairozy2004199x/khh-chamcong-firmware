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

	/** Màn hình → nhãn trên thanh điều hướng. Cũng là danh sách hợp lệ. */
	public static function man_hinh() {
		return array(
			''           => 'Tổng quan',
			'ngan-hang'  => 'Ngân hàng',
			'giao-dich'  => 'Giao dịch / Sao kê',
			'doi-soat'   => 'Đối soát',
			'chi-phi'    => 'Chi phí',
			'doi-soat-chi-phi' => 'Đối soát chi phí',
			'hoa-don-ra' => 'Hoá đơn đầu ra',
			'hoa-don-vao' => 'Hoá đơn đầu vào',
			'cong-no'    => 'Công nợ',
			'phap-danh'  => 'Pháp danh',
			'ho-so'      => 'Hồ sơ',
			'bao-cao'    => 'Báo cáo',
			'nhat-ky'    => 'Nhật ký',
			'sao-luu'    => 'Sao lưu',
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
			auth_redirect();
			exit;
		}

		status_header( 200 );
		nocache_headers();
		self::khung( $man );
		exit;
	}

	private static function khung( $man ) {
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
<header class="khtc-thanh">
	<div class="khtc-hieu">Tài Chính K&amp;H</div>
	<nav>
		<?php foreach ( $nhan as $k => $v ) : ?>
			<a href="<?php echo esc_url( self::duong_dan( $k ) ); ?>"<?php echo $k === $man ? ' class="dang-o"' : ''; ?>><?php echo esc_html( $v ); ?></a>
		<?php endforeach; ?>
	</nav>
	<div class="khtc-ai">
		<span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
		<a href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>
		<a href="<?php echo esc_url( wp_logout_url( self::duong_dan() ) ); ?>">Thoát</a>
	</div>
</header>
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
</body>
</html>
<?php
	}
}
