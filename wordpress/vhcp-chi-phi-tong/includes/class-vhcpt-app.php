<?php
/**
 * TRANG TỔNG — xuất nguyên trang `templates/app.html` tại /chi-phi-kh.
 *
 * ⚠️ ĐỊA CHỈ LÀ `chi-phi-kh`, KHÔNG PHẢI `chi-phi-k&h`. Anh Thắng 14/09/2026 viết
 *    *"trang tổng hợp là khmatrix.com/chi-phi-k&h"* — nhưng `sanitize_title()` của WordPress bỏ
 *    dấu `&`, nên gõ như thế vào ô Cài đặt thì nó lưu thành `chi-phi-kh` mà không báo gì. Ngoài
 *    ra `&` trong URL là ký tự mở phần tham số, nên link dán vào chat hay Excel sẽ đứt ở đúng
 *    chỗ ấy. Lấy thẳng `chi-phi-kh` cho khớp giữa thứ khai và thứ chạy.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPT_App {

	const SLUG_MAC_DINH = 'chi-phi-kh';

	public static function slug() {
		$s = get_option( 'vhcpt_slug' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MAC_DINH;
		return $s ? $s : self::SLUG_MAC_DINH;
	}

	public static function app_url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcpt', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcpt_app=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'co_thi_ve' ) );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'vhcpt_app';
		return $vars;
	}

	public static function co_thi_ve() {
		$la_app = ( (int) get_query_var( 'vhcpt_app' ) === 1 );
		if ( ! $la_app && isset( $_GET['vhcpt'] ) && 'app' === $_GET['vhcpt'] ) { $la_app = true; }
		if ( ! $la_app ) { return; }

		$tep = VHCPT_DIR . 'templates/app.html';
		if ( ! is_file( $tep ) ) { status_header( 500 ); echo 'Thiếu templates/app.html'; exit; }
		$html = file_get_contents( $tep );

		/* Trang tự biết đường gọi cổng và bản mình đang chạy — KHÔNG gõ cứng trong HTML, vì
		   đường gọi đổi theo cách site khai permalink. */
		$boot = array(
			'api' => esc_url_raw( rest_url( VHCPT_Api::NS . '/call' ) ),
			'ver' => VHCPT_VERSION,
		);
		$html = str_replace( '/*__VHCPT_BOOT__*/', wp_json_encode( $boot ), $html );

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html;
		exit;
	}
}
