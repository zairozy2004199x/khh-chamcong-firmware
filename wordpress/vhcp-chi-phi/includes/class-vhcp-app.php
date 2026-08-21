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
		// Nạp lại đường dẫn: xem vhcp_flush_rewrite() ở file chính — phải chạy SAU khi cả
		// app chi phí và thư viện hợp đồng đều khai xong đường dẫn của mình, không thì lần
		// nạp lại đó ghi thiếu một đường và trang kia trả 404.
	}

	public static function query_vars( $vars ) {
		$vars[] = 'vhcp_app';
		return $vars;
	}

	public static function maybe_render() {
		$is_app = ( (int) get_query_var( 'vhcp_app' ) === 1 );
		if ( ! $is_app && isset( $_GET['vhcp'] ) && $_GET['vhcp'] === 'app' ) { $is_app = true; }
		if ( ! $is_app ) { return; }

		// ĐƯỜNG GỌI THỨ BA — qua chính URL của app.
		//
		// Cloudflare / tường lửa của hosting thường chặn theo ĐƯỜNG DẪN: /wp-json/ và
		// /wp-admin/admin-ajax.php bị trả 403 kèm trang "Checking your browser", trong khi
		// trang app vẫn mở bình thường. Vậy thì nhận luôn lệnh trên đường dẫn đã mở được
		// đó: người dùng vừa tải trang này xong nên tường lửa chắc chắn cho đi qua.
		if ( isset( $_GET['vhcp_api'] ) ) {
			VHCP_API::trang();
			exit;
		}

		self::render();
		exit;
	}

	/** Danh tính SSO từ trang tổng (nếu có ?sso=). */
	public static function sso_user() {
		if ( empty( $_GET['sso'] ) ) { return null; }
		$tok   = sanitize_text_field( wp_unslash( $_GET['sso'] ) );
		$ident = VHCP_Auth::verify_sso_token( $tok );
		if ( ! $ident ) { return null; }
		$u = VHCP_Auth::resolve_sso_user( $ident );
		// SSO không qua cổng PIN nên phát token phiên ngay để API nhận.
		$u['token'] = VHCP_Auth::issue_token( $u['name'], $u['role'], $u['coso'], '' );
		return $u;
	}

	/**
	 * Khối <head> dùng chung cho mọi trang của plugin.
	 *
	 * @param string $tieu_de Tên hiện trên thẻ tiêu đề trình duyệt.
	 * @param string $trang   URL nhận lệnh của ĐƯỜNG GỌI THỨ BA (chính trang đang mở).
	 * @param array  $fns     Danh sách hàm giao diện được phép gọi (null = tất cả).
	 */
	public static function head_block( $tieu_de = 'Vận Hành Chi Phí', $trang = '', $fns = null ) {
		$sso = self::sso_user();
		if ( $trang === '' ) { $trang = add_query_arg( 'vhcp_api', '1', self::app_url() ); }
		if ( $fns === null )  { $fns = array_keys( VHCP_API::map() ); }
		$cfg = array(
			'endpoint' => esc_url_raw( rest_url( 'vhcp/v1/call' ) ),
			// Đường dự phòng khi hosting chặn /wp-json/ (giao diện tự chuyển)
			'ajax'     => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			// Đường dự phòng CUỐI: chính URL của trang này — Cloudflare chặn theo đường dẫn,
			// mà đường dẫn này người dùng vừa mở được nên không thể bị chặn.
			'trang'    => esc_url_raw( $trang ),
			'fns'      => $fns,
			'ssoUser'  => $sso ? array( 'name' => $sso['name'], 'role' => $sso['role'], 'coso' => $sso['coso'] ) : null,
			'ver'      => VHCP_VERSION,
		);

		$out  = '<title>' . esc_html( $tieu_de ) . '</title>' . "\n";
		$out .= '<link rel="stylesheet" href="' . esc_url( VHCP_URL . 'assets/css/vhcp.css' ) . '?ver=' . rawurlencode( VHCP_VERSION ) . '">' . "\n";
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
