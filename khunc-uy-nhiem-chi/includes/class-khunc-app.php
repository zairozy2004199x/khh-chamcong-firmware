<?php
/**
 * TRANG APP — xuất trang giao diện ở đường dẫn riêng để CSS của theme không chen vào,
 * và vẫn nhúng iframe được vào trang tổng K&H.
 *
 *   https://<tên miền>/uy-nhiem-chi/     trang ủy nhiệm chi & công nợ
 *   https://<tên miền>/?khunc=app        khi permalink còn ở dạng ?p=
 *   …?sso=<token>                        đăng nhập một lần từ trang tổng
 *
 * Chỉ có MỘT trang: kế toán nhập Excel và đánh dấu, vai "Xem" vào cùng đường dẫn đó
 * nhưng giao diện tự ẩn hết nút ghi (body.chi-xem).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_App {

	const SLUG_MAC_DINH = 'uy-nhiem-chi';
	const TEN_MAC_DINH  = 'Ủy nhiệm chi & Công nợ';

	public static function slug() {
		$s = get_option( 'khunc_slug' );
		$s = $s ? sanitize_title( $s ) : '';
		return $s !== '' ? $s : self::SLUG_MAC_DINH;
	}
	public static function ten_trang() {
		$t = trim( (string) get_option( 'khunc_ten_trang', '' ) );
		return $t !== '' ? $t : self::TEN_MAC_DINH;
	}
	public static function app_url( $trang = 'app' ) {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . self::slug() . '/' );
		}
		return add_query_arg( 'khunc', 'app', home_url( '/' ) );
	}

	public static function init() {
		$s = self::slug();
		add_rewrite_rule( '^' . $s . '/?$', 'index.php?khunc_app=app', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}
	public static function query_vars( $vars ) { $vars[] = 'khunc_app'; return $vars; }

	public static function maybe_render() {
		$trang = (string) get_query_var( 'khunc_app' );
		if ( $trang === '' && isset( $_GET['khunc'] ) ) { $trang = sanitize_key( wp_unslash( $_GET['khunc'] ) ); } // phpcs:ignore
		if ( $trang !== 'app' ) { return; }
		if ( isset( $_GET['khunc_api'] ) ) { KHUNC_API::trang(); exit; } // phpcs:ignore
		self::render( $trang );
		exit;
	}

	/** ?sso=<token> → xác thực HMAC → tìm/tạo người dùng → phát thẻ phiên. */
	public static function sso_token() {
		if ( empty( $_GET['sso'] ) ) { return ''; } // phpcs:ignore
		$ident = KHUNC_Auth::verify_sso_token( sanitize_text_field( wp_unslash( $_GET['sso'] ) ) ); // phpcs:ignore
		if ( ! $ident ) { return ''; }
		$u = KHUNC_Auth::resolve_sso_user( $ident );
		return $u ? KHUNC_Auth::issue_token( $u ) : '';
	}

	public static function cfg( $trang ) {
		return array(
			'mode'     => 'wp',
			'endpoint' => esc_url_raw( rest_url( 'khunc/v1/call' ) ),
			'ajax'     => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'trang'    => esc_url_raw( add_query_arg( 'khunc_api', '1', self::app_url( $trang ) ) ),
			'ver'      => KHUNC_VERSION,
			'tenTrang' => self::ten_trang(),
			'appUrl'   => esc_url_raw( self::app_url( 'app' ) ),
		);
	}

	public static function head_block( $trang ) {
		$sso = self::sso_token();
		$out  = '<title>' . esc_html( self::ten_trang() ) . '</title>' . "\n";
		$out .= '<link rel="stylesheet" href="' . esc_url( KHUNC_URL . 'assets/css/app.css' ) . '?ver=' . rawurlencode( KHUNC_VERSION ) . '">' . "\n";
		$out .= '<script>window.KHUNC_CFG=' . wp_json_encode( self::cfg( $trang ) ) . ';';
		if ( $sso !== '' ) { $out .= 'try{localStorage.setItem("khunc_token",' . wp_json_encode( $sso ) . ');}catch(e){}'; }
		$out .= '</script>' . "\n";
		return $out;
	}

	public static function scripts_block( $trang ) {
		$v    = '?ver=' . rawurlencode( KHUNC_VERSION );
		/* ⚠️ THỨ TỰ LÀ MỘT PHẦN CỦA HỢP ĐỒNG, không phải cho gọn mắt. Mỗi tệp nhận lấy tệp
		   trước nó qua biến toàn cục (`saoke.js` cần `UNCEngine`, `app.js` cần tất cả), nên
		   xếp sai một dòng là một `undefined` ngay lúc tải — và trên hosting thì nó hiện ra
		   thành một trang trắng không có lấy một dòng lỗi nào người dùng đọc được. */
		$list = array(
			'vendor/xlsx.full.min.js', 'engine.js', 'api.js', 'wp-ui.js',
			'importer.js', 'saoke.js', 'exporter.js', 'mau.js', 'sample-data.js', 'app.js',
		);
		$out = '';
		foreach ( $list as $f ) {
			$out .= '<script src="' . esc_url( KHUNC_URL . 'assets/js/' . $f ) . $v . '"></script>' . "\n";
		}
		return $out;
	}

	public static function render( $trang ) {
		$file = KHUNC_DIR . 'templates/' . ( $trang === 'nhap' ? 'nhap.html' : 'app.html' );
		if ( ! is_readable( $file ) ) {
			status_header( 500 );
			echo 'Thiếu file templates của plugin Ủy Nhiệm Chi.';
			return;
		}
		$html = file_get_contents( $file );
		$html = str_replace( '<!--KHUNC_HEAD-->', self::head_block( $trang ), $html );
		$html = str_replace( '<!--KHUNC_SCRIPTS-->', self::scripts_block( $trang ), $html );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header_remove( 'X-Frame-Options' );
		echo $html; // phpcs:ignore
	}

	/** [khunc_app] hoặc [khunc_app height="900px"] — nhúng iframe vào bài viết. */
	public static function shortcode( $atts ) {
		$a   = shortcode_atts( array( 'height' => '900px' ), $atts );
		$url = self::app_url( 'app' );
		return '<iframe src="' . esc_url( $url ) . '" style="width:100%;height:' . esc_attr( $a['height'] ) . ';border:0;border-radius:12px" loading="lazy"></iframe>';
	}
}
