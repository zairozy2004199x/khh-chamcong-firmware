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
		$goc = VHJP_DIR . 'giao-dien/' . ( $kt ? 'KT_Index.html' : 'Index.html' );
		$tam = VHJP_DIR . 'templates/dang-dung.html';
		$tep = is_file( $goc ) ? $goc : $tam;

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
		$html = self::ghep( $html );
		/* Trang tạm dùng chỗ giữ riêng; giao diện thật thì không có. Thay cả hai cho gọn. */
		$html = str_replace( '<?VHJP_CFG?>', wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ), $html );
		$html = str_replace( '<?VHJP_SHIM?>', esc_url( VHJP_URL . 'assets/js/gas-shim.js' ), $html );
		$html = self::nap_shim( $html, $cfg );
		echo $html;
	}

	/**
	 * Ghép `<?!= include('Ten'); ?>` — cú pháp khuôn DUY NHẤT mà giao diện JP dùng.
	 *
	 * ⚠️ Chỉ nhận tên tệp CÓ THẬT trong `giao-dien/`, và tên phải là chữ cái/số/gạch dưới. Tên
	 *    đi thẳng vào đường dẫn tệp, nên nhận bừa là mở cửa cho `include('../../wp-config')`.
	 *    Ở đây tên nằm trong tệp của chính mình chứ không đến từ trình duyệt — nhưng chốt chặn
	 *    rẻ, mà ngày nào đó có ai cho phép khai tên từ ngoài thì nó đã sẵn ở đấy.
	 *
	 * ⚠️ Ghép LẶP cho tới khi hết: tệp được ghép vào có thể lại chứa `include` khác.
	 */
	public static function ghep( $html, $sau = 0 ) {
		if ( $sau > 5 || false === strpos( $html, 'include(' ) ) { return $html; }
		$moi = preg_replace_callback(
			"/<\?!=\s*include\(\s*'([A-Za-z0-9_]+)'\s*\)\s*;?\s*\?>/",
			function ( $m ) {
				$f = VHJP_DIR . 'giao-dien/' . $m[1] . '.html';
				return is_file( $f ) ? file_get_contents( $f )
					/* Thiếu tệp thì để lại một dấu vết ĐỌC ĐƯỢC trong mã nguồn trang, đừng nuốt
					   im lặng — nuốt thì màn thiếu hẳn một mảng mà không ai biết vì sao. */
					: '<!-- vhjp: thiếu tệp giao diện ' . esc_html( $m[1] ) . '.html -->';
			}, $html );
		return self::ghep( $moi, $sau + 1 );
	}

	/**
	 * Nhét cấu hình và lớp `gas-shim` vào `<head>`.
	 *
	 * 🔴 PHẢI TRƯỚC MỌI ĐOẠN JS CỦA GIAO DIỆN. Giao diện gọi `google.script.run` ngay lúc chạy;
	 *    shim nạp sau là lệnh đầu tiên nổ vì `google` chưa tồn tại — mà lệnh đầu tiên chính là
	 *    lượt dựng màn hình.
	 */
	private static function nap_shim( $html, $cfg ) {
		if ( false !== strpos( $html, 'gas-shim.js' ) ) { return $html; }
		$nhet = '<script>window.VHJP_CFG = '
			. wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) . ';</script>' . "\n"
			. '<script src="' . esc_url( VHJP_URL . 'assets/js/gas-shim.js?v=' . VHJP_VERSION )
			. '"></script>' . "\n";
		$i = stripos( $html, '<head>' );
		if ( false !== $i ) { return substr_replace( $html, "\n" . $nhet, $i + 6, 0 ); }
		return $nhet . $html;
	}
}
