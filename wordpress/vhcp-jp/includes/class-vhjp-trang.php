<?php
/**
 * TRANG NGOÀI — `/jp` cho nhân viên, `/jp-ke-toan` cho kế toán.
 *
 * =============================================================================================
 * ⚠️ BA ĐƯỜNG GỌI MÁY CHỦ, KHÔNG PHẢI MỘT.
 * =============================================================================================
 * Nhiều hosting (LiteSpeed/ModSecurity) và Cloudflare chặn THEO ĐƯỜNG DẪN: `/wp-json/` và
 * `/wp-admin/admin-ajax.php` trả 403 kèm một trang HTML, trong khi trang app vẫn mở bình
 * thường. Bộ Chi Phí đã gặp thật. Nên nhận lệnh trên cả ba đường, kể cả trên CHÍNH đường dẫn
 * của trang — người dùng vừa tải được trang này thì tường lửa chắc chắn cho đường ấy đi qua.
 *
 * ⚠️ ĐỔI SLUG THÌ GIỮ LUÔN ĐƯỜNG CŨ. Đường dẫn đã nằm trong tin nhắn, trong mã QR đã in, trong
 *    dấu trang của nhân viên. Bỏ đường cũ là tất cả những thứ ấy trả 404 cùng một lúc.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Trang {

	const SLUG_NV = 'jp';
	const SLUG_KT = 'jp-ke-toan';

	public static function slug_nv() {
		$s = get_option( 'vhjp_slug_nv' );
		return $s ? sanitize_title( $s ) : self::SLUG_NV;
	}
	public static function slug_kt() {
		$s = get_option( 'vhjp_slug_kt' );
		return $s ? sanitize_title( $s ) : self::SLUG_KT;
	}
	public static function dia_chi( $kt = false ) {
		$s = $kt ? self::slug_kt() : self::slug_nv();
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . $s . '/' ); }
		return home_url( '/?vhjp_app=' . ( $kt ? 'kt' : 'nv' ) );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'them_duong' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'co_the_dung' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'khai_rest' ) );
		add_action( 'wp_ajax_vhjp_call', array( __CLASS__, 'qua_ajax' ) );
		add_action( 'wp_ajax_nopriv_vhjp_call', array( __CLASS__, 'qua_ajax' ) );
	}

	/** Khai CẢ đường hiện tại LẪN đường mặc định — xem khối ⚠️ thứ hai ở đầu tệp. */
	public static function them_duong() {
		foreach ( array_unique( array( self::slug_nv(), self::SLUG_NV ) ) as $s ) {
			add_rewrite_rule( '^' . $s . '/?$', 'index.php?vhjp_app=nv', 'top' );
		}
		foreach ( array_unique( array( self::slug_kt(), self::SLUG_KT ) ) as $s ) {
			add_rewrite_rule( '^' . $s . '/?$', 'index.php?vhjp_app=kt', 'top' );
		}
	}

	public static function query_vars( $v ) { $v[] = 'vhjp_app'; return $v; }

	public static function co_the_dung() {
		$man = (string) get_query_var( 'vhjp_app' );
		if ( '' === $man && isset( $_GET['vhjp_app'] ) ) {
			$man = sanitize_key( wp_unslash( $_GET['vhjp_app'] ) );
		}
		if ( 'nv' !== $man && 'kt' !== $man ) { return; }

		/* Đường gọi thứ ba — ngay trên đường dẫn của trang. */
		if ( isset( $_GET['vhjp_api'] ) ) { self::qua_trang(); exit; }

		self::ve( 'kt' === $man );
		exit;
	}

	/* ═══════════════════════ BA ĐƯỜNG NHẬN LỆNH ═══════════════════════ */

	public static function khai_rest() {
		register_rest_route( 'vhjp/v1', '/call', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',   // cổng tự gác bằng thẻ phiên
			'callback'            => array( __CLASS__, 'qua_rest' ),
		) );
	}

	public static function qua_rest( $req ) {
		$kq = VHJP_Cong::goi( $req->get_param( 'fn' ), $req->get_param( 'args' ) );
		return new WP_REST_Response( $kq['than'], (int) $kq['ma'] );
	}

	public static function qua_ajax() { self::tra( self::tu_than() ); }
	public static function qua_trang() { self::tra( self::tu_than() ); }

	/** Đọc lệnh từ thân yêu cầu — nhận cả JSON lẫn form. */
	private static function tu_than() {
		$tho = file_get_contents( 'php://input' );
		$d   = json_decode( (string) $tho, true );
		if ( ! is_array( $d ) ) {
			$d = array(
				'fn'   => isset( $_POST['fn'] ) ? wp_unslash( $_POST['fn'] ) : '',
				'args' => isset( $_POST['args'] ) ? json_decode( wp_unslash( $_POST['args'] ), true ) : array(),
			);
		}
		return VHJP_Cong::goi(
			isset( $d['fn'] ) ? $d['fn'] : '',
			isset( $d['args'] ) ? $d['args'] : array() );
	}

	private static function tra( $kq ) {
		if ( ! headers_sent() ) {
			status_header( (int) $kq['ma'] );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $kq['than'], JSON_UNESCAPED_UNICODE );
		if ( ! defined( 'VHJP_TEST' ) ) { exit; }
	}

	/* ═══════════════════════ DỰNG TRANG ═══════════════════════ */

	/**
	 * Dựng trang.
	 *
	 * ⚠️ Giao diện thật của JP là 11 tệp HTML/JS trong `goc/jp-capsule-v2/` — chúng CHƯA được
	 *    mang sang đây. Chừng nào chưa mang, trang này nói thẳng là đang dựng dở và cho xem
	 *    tiến độ, chứ KHÔNG bày một màn trắng: màn trắng thì người mở ra tưởng hỏng, và sẽ
	 *    bấm lại mấy lần rồi đi hỏi.
	 */
	public static function ve( $kt = false ) {
		$tep = VHJP_DIR . 'templates/' . ( $kt ? 'kt.html' : 'app.html' );
		if ( ! is_file( $tep ) ) { $tep = VHJP_DIR . 'templates/dang-dung.html'; }

		$cfg = array(
			'rest'    => esc_url_raw( rest_url( 'vhjp/v1/call' ) ),
			'ajax'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'trang'   => esc_url_raw( add_query_arg( 'vhjp_api', '1', self::dia_chi( $kt ) ) ),
			'man'     => $kt ? 'kt' : 'nv',
			'ver'     => VHJP_VERSION,
			'daChuyen' => count( VHJP_Cong::map() ),
			'tong'     => count( VHJP_Cong::map() ) + count( VHJP_Cong::chua_lam() ),
		);

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		$html = file_get_contents( $tep );
		$html = str_replace( '<?VHJP_CFG?>', wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ), $html );
		$html = str_replace( '<?VHJP_SHIM?>', esc_url( VHJP_URL . 'assets/js/gas-shim.js' ), $html );
		echo $html;
	}
}
