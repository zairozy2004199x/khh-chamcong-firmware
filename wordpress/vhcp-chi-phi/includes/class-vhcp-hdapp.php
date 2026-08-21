<?php
/**
 * TRANG THƯ VIỆN HỢP ĐỒNG — hệ thống RIÊNG, đường dẫn RIÊNG, giao diện RIÊNG.
 *
 *   https://<tên miền>/hop-dong/          (đường dẫn tĩnh, đổi được trong Cài đặt)
 *   https://<tên miền>/?vhcp=hopdong      (dùng khi permalink đang để dạng ?p=)
 *
 * Vì sao tách hẳn khỏi app chi phí:
 *   - Hợp đồng mang giá và điều khoản, chỉ Kế toán / Quản lý / Admin được xem. Tách trang
 *     thì người không có quyền không tải cả bộ giao diện chi phí, và cũng không thấy
 *     đường dẫn nào gợi ý là có hệ thống này.
 *   - Hai hệ thống độc lập: sửa app chi phí không ảnh hưởng thư viện hợp đồng.
 *
 * Vẫn dùng CHUNG bảng người dùng và mã PIN của plugin, khỏi phải cấp mật khẩu lần hai.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_HDApp {

	/** Chỉ những hàm này được gọi từ trang hợp đồng — máy chủ còn chặn theo vai trò nữa. */
	const FNS = array(
		'login',
		'changePin',
		'vhcpLogout',
		'listHopDong',
		'getHopDong',
		'saveHopDong',
		'deleteHopDong',
		'uploadHopDongFile',
	);

	public static function slug() {
		$s = get_option( 'vhcp_slug_hd' );
		$s = $s ? sanitize_title( $s ) : 'hop-dong';
		return $s ? $s : 'hop-dong';
	}

	public static function app_url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcp', 'hopdong', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcp_hd=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'vhcp_hd';
		return $vars;
	}

	public static function maybe_render() {
		$is = ( (int) get_query_var( 'vhcp_hd' ) === 1 );
		if ( ! $is && isset( $_GET['vhcp'] ) && $_GET['vhcp'] === 'hopdong' ) { $is = true; }
		if ( ! $is ) { return; }

		// Đường gọi dự phòng cuối: qua chính URL của trang này (xem class-vhcp-app.php).
		if ( isset( $_GET['vhcp_api'] ) ) {
			VHCP_API::trang();
			exit;
		}

		self::render();
		exit;
	}

	public static function render() {
		$file = VHCP_DIR . 'templates/hopdong.html';
		if ( ! is_readable( $file ) ) {
			status_header( 500 );
			echo 'Thiếu file templates/hopdong.html của plugin Vận Hành Chi Phí.';
			return;
		}
		$head = VHCP_App::head_block(
			'Thư Viện Hợp Đồng',
			add_query_arg( 'vhcp_api', '1', self::app_url() ),
			self::FNS
		);
		$html = str_replace( '<!--VHCP_HEAD-->', $head, file_get_contents( $file ) );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header_remove( 'X-Frame-Options' );
		echo $html;
	}

	/** [vhcp_hopdong height="900"] — nhúng thư viện hợp đồng vào 1 trang WordPress. */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'height' => '900' ), $atts, 'vhcp_hopdong' );
		$h = preg_replace( '/[^0-9a-z%]/i', '', (string) $a['height'] );
		if ( $h === '' ) { $h = '900'; }
		if ( is_numeric( $h ) ) { $h .= 'px'; }
		return '<iframe src="' . esc_url( self::app_url() ) . '" style="width:100%;height:' . esc_attr( $h ) . ';border:0;display:block" loading="lazy" title="Thư Viện Hợp Đồng"></iframe>';
	}
}
