<?php
/**
 * TRANG APP — xuất nguyên trang giao diện (templates/app.html) tại 1 đường dẫn riêng
 * để CSS của theme không chen vào, và vẫn nhúng iframe được vào trang tổng K&H.
 *
 *   https://<tên miền>/chi-phi/            (đường dẫn tĩnh, đổi được trong Cài đặt)
 *   https://<tên miền>/?vhcp=app           (dùng khi permalink đang để dạng ?p=)
 *   https://<tên miền>/chi-phi/?sso=<token> (đăng nhập một lần từ trang tổng)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_App {

	public static function slug() {
		$s = get_option( 'vhcp_slug' );
		$s = $s ? sanitize_title( $s ) : 'chi-phi';
		return $s ? $s : 'chi-phi';
	}

	public static function app_url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcp', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcp_app=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );

		if ( get_option( 'vhcp_flush_rewrite' ) ) {
			delete_option( 'vhcp_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'vhcp_app';
		return $vars;
	}

	public static function maybe_render() {
		$is_app = ( (int) get_query_var( 'vhcp_app' ) === 1 );
		if ( ! $is_app && isset( $_GET['vhcp'] ) && $_GET['vhcp'] === 'app' ) { $is_app = true; }
		if ( ! $is_app ) { return; }
		self::render();
		exit;
	}

	/** Danh tính SSO từ trang tổng (nếu có ?sso=). */
	private static function sso_user() {
		if ( empty( $_GET['sso'] ) ) { return null; }
		$tok   = sanitize_text_field( wp_unslash( $_GET['sso'] ) );
		$ident = VHCP_Auth::verify_sso_token( $tok );
		if ( ! $ident ) { return null; }
		$u = VHCP_Auth::resolve_sso_user( $ident );
		// SSO không qua cổng PIN nên phát token phiên ngay để API nhận.
		$u['token'] = VHCP_Auth::issue_token( $u['name'], $u['role'], $u['coso'], '' );
		return $u;
	}

	private static function head_block() {
		$sso = self::sso_user();
		$cfg = array(
			'endpoint' => esc_url_raw( rest_url( 'vhcp/v1/call' ) ),
			'fns'      => array_keys( VHCP_API::map() ),
			'ssoUser'  => $sso ? array( 'name' => $sso['name'], 'role' => $sso['role'], 'coso' => $sso['coso'] ) : null,
			'ver'      => VHCP_VERSION,
		);

		$out  = '<title>Vận Hành Chi Phí</title>' . "\n";
		$out .= '<script>window.VHCP_CFG=' . wp_json_encode( $cfg ) . ';';
		if ( $sso && ! empty( $sso['token'] ) ) {
			// Nạp sẵn token cho phiên SSO (ghi đè token cũ của máy này).
			$out .= 'try{localStorage.setItem("vhcp_token",' . wp_json_encode( $sso['token'] ) . ');}catch(e){}';
		}
		$out .= '</script>' . "\n";
		$out .= '<script src="' . esc_url( VHCP_URL . 'assets/js/gas-shim.js' ) . '?ver=' . rawurlencode( VHCP_VERSION ) . '"></script>' . "\n";
		return $out;
	}

	public static function render() {
		$file = VHCP_DIR . 'templates/app.html';
		if ( ! is_readable( $file ) ) {
			status_header( 500 );
			echo 'Thiếu file templates/app.html của plugin Vận Hành Chi Phí.';
			return;
		}
		$html = file_get_contents( $file );
		$html = str_replace( '<!--VHCP_HEAD-->', self::head_block(), $html );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		// Để trang tổng K&H nhúng được bằng iframe: bỏ X-Frame-Options nếu theme/plugin khác đã đặt.
		header_remove( 'X-Frame-Options' );
		echo $html;
	}

	/** [vhcp_app height="900"] — nhúng app vào 1 trang WordPress bằng iframe. */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'height' => '900' ), $atts, 'vhcp_app' );
		$h = preg_replace( '/[^0-9a-z%]/i', '', (string) $a['height'] );
		if ( $h === '' ) { $h = '900'; }
		if ( is_numeric( $h ) ) { $h .= 'px'; }
		return '<iframe src="' . esc_url( self::app_url() ) . '" style="width:100%;height:' . esc_attr( $h ) . ';border:0;display:block" loading="lazy" title="Vận Hành Chi Phí"></iframe>';
	}
}
