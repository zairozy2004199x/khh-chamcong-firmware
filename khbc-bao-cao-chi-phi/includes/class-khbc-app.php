<?php
/**
 * TRANG APP — xuất trang giao diện tại đường dẫn riêng để CSS theme không chen vào,
 * và vẫn nhúng iframe được vào trang tổng K&H.
 *
 *   https://<tên miền>/bao-cao-chi-phi/          trang kế toán (templates/app.html)
 *   https://<tên miền>/bao-cao-chi-phi/nhap/     trang nhân viên nhập chi phí (templates/nhap.html)
 *   https://<tên miền>/?khbc=app | ?khbc=nhap    khi permalink dạng ?p=
 *   …?sso=<token>                                đăng nhập một lần từ trang tổng
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_App {

	const SLUG_MAC_DINH = 'bao-cao-chi-phi';
	const TEN_MAC_DINH  = 'Báo cáo chi phí';

	public static function slug() {
		$s = get_option( 'khbc_slug' );
		$s = $s ? sanitize_title( $s ) : '';
		return $s !== '' ? $s : self::SLUG_MAC_DINH;
	}
	public static function ten_trang() {
		$t = trim( (string) get_option( 'khbc_ten_trang', '' ) );
		return $t !== '' ? $t : self::TEN_MAC_DINH;
	}
	public static function app_url( $trang = 'app' ) {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . self::slug() . '/' . ( $trang === 'nhap' ? 'nhap/' : '' ) );
		}
		return add_query_arg( 'khbc', $trang, home_url( '/' ) );
	}

	public static function init() {
		$s = self::slug();
		add_rewrite_rule( '^' . $s . '/nhap/?$', 'index.php?khbc_app=nhap', 'top' );
		add_rewrite_rule( '^' . $s . '/?$', 'index.php?khbc_app=app', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}
	public static function query_vars( $vars ) { $vars[] = 'khbc_app'; return $vars; }

	public static function maybe_render() {
		$trang = (string) get_query_var( 'khbc_app' );
		if ( $trang === '' && isset( $_GET['khbc'] ) ) { $trang = sanitize_key( wp_unslash( $_GET['khbc'] ) ); } // phpcs:ignore
		if ( $trang !== 'app' && $trang !== 'nhap' ) { return; }
		if ( isset( $_GET['khbc_api'] ) ) { KHBC_API::trang(); exit; } // phpcs:ignore
		self::render( $trang );
		exit;
	}

	/** ?sso=<token> → xác thực HMAC → tìm/tạo người dùng → phát thẻ phiên. */
	public static function sso_token() {
		if ( empty( $_GET['sso'] ) ) { return ''; } // phpcs:ignore
		$ident = KHBC_Auth::verify_sso_token( sanitize_text_field( wp_unslash( $_GET['sso'] ) ) ); // phpcs:ignore
		if ( ! $ident ) { return ''; }
		$u = KHBC_Auth::resolve_sso_user( $ident );
		return $u ? KHBC_Auth::issue_token( $u ) : '';
	}

	public static function cfg( $trang ) {
		return array(
			'mode'     => 'wp',
			'endpoint' => esc_url_raw( rest_url( 'khbc/v1/call' ) ),
			'ajax'     => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'trang'    => esc_url_raw( add_query_arg( 'khbc_api', '1', self::app_url( $trang ) ) ),
			'ver'      => KHBC_VERSION,
			'tenTrang' => self::ten_trang(),
			'appUrl'   => esc_url_raw( self::app_url( 'app' ) ),
			'nhapUrl'  => esc_url_raw( self::app_url( 'nhap' ) ),
			'trangHienTai' => $trang,
		);
	}

	public static function head_block( $trang ) {
		$sso = self::sso_token();
		$out  = '<title>' . esc_html( self::ten_trang() ) . ( $trang === 'nhap' ? ' — Nhập chi phí' : '' ) . '</title>' . "\n";
		$out .= '<link rel="stylesheet" href="' . esc_url( KHBC_URL . 'assets/css/app.css' ) . '?ver=' . rawurlencode( KHBC_VERSION ) . '">' . "\n";
		$out .= '<script>window.KHBC_CFG=' . wp_json_encode( self::cfg( $trang ) ) . ';';
		if ( $sso !== '' ) { $out .= 'try{localStorage.setItem("khbc_token",' . wp_json_encode( $sso ) . ');}catch(e){}'; }
		$out .= '</script>' . "\n";
		return $out;
	}

	public static function scripts_block( $trang ) {
		$v    = '?ver=' . rawurlencode( KHBC_VERSION );
		$list = $trang === 'nhap'
			? array( 'engine.js', 'api.js', 'wp-ui.js', 'nhap.js' )
			: array( 'vendor/xlsx.full.min.js', 'engine.js', 'api.js', 'wp-ui.js', 'importer.js', 'exporter.js', 'sample-data.js', 'app.js' );
		$out = '';
		foreach ( $list as $f ) {
			$out .= '<script src="' . esc_url( KHBC_URL . 'assets/js/' . $f ) . $v . '"></script>' . "\n";
		}
		return $out;
	}

	public static function render( $trang ) {
		$file = KHBC_DIR . 'templates/' . ( $trang === 'nhap' ? 'nhap.html' : 'app.html' );
		if ( ! is_readable( $file ) ) {
			status_header( 500 );
			echo 'Thiếu file templates của plugin Báo Cáo Chi Phí.';
			return;
		}
		$html = file_get_contents( $file );
		$html = str_replace( '<!--KHBC_HEAD-->', self::head_block( $trang ), $html );
		$html = str_replace( '<!--KHBC_SCRIPTS-->', self::scripts_block( $trang ), $html );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header_remove( 'X-Frame-Options' );
		echo $html; // phpcs:ignore
	}

	/** [khbc_app] hoặc [khbc_app trang="nhap" height="900px"] — nhúng iframe vào bài viết. */
	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'trang' => 'app', 'height' => '900px' ), $atts );
		$url = self::app_url( $a['trang'] === 'nhap' ? 'nhap' : 'app' );
		return '<iframe src="' . esc_url( $url ) . '" style="width:100%;height:' . esc_attr( $a['height'] ) . ';border:0;border-radius:12px" loading="lazy"></iframe>';
	}
}
