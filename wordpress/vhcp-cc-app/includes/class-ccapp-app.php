<?php
/**
 * LỚP VỎ APP — ba địa chỉ dưới `/cc/`, không có địa chỉ nào khác.
 *
 *   /cc/                trang app: CHÍNH trang chấm công, cộng mấy thẻ PWA chèn vào <head>
 *   /cc/manifest.json   tờ khai để máy biết đây là app (tên, biểu tượng, màu, chạy toàn màn hình)
 *   /cc/sw.js           thợ nền: giữ vỏ app để mở được lúc mạng chập chờn
 *
 * =================================================================================================
 * 🔴 VÌ SAO `sw.js` PHẢI NẰM Ở `/cc/`, KHÔNG PHẢI TRONG `wp-content/plugins/…`
 * =================================================================================================
 * Thợ nền chỉ quản được những trang NẰM DƯỚI thư mục chứa chính nó. Để tệp ở
 * `/wp-content/plugins/vhcp-cc-app/assets/sw.js` thì phạm vi của nó là `/wp-content/plugins/…/`,
 * tức là nó KHÔNG quản trang `/cc/` — đăng ký vẫn "thành công", thợ nền vẫn chạy, mà không bao giờ
 * đỡ được lượt tải nào. Một cái hỏng hoàn toàn im lặng: DevTools báo xanh, ngoại tuyến thì trắng
 * trang. Nên tệp JS để trong `assets/` cho dễ soát cú pháp, còn ĐỊA CHỈ phục vụ nó là `/cc/sw.js`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCAPP_App {

	/** Đường dẫn mặc định. Đổi được trong Cài đặt → App Chấm Công. */
	const SLUG_MD = 'cc';

	const O_SLUG = 'ccapp_slug';
	const O_TEN  = 'ccapp_ten';

	/** Tên bày dưới biểu tượng ở màn hình chính. iOS cắt còn ~12 ký tự nên đừng dài. */
	const TEN_MD = 'Chấm Công';

	/** Bộ áo của nhà — navy + vàng. Cùng mã màu với bản dựng giao diện anh Thắng gửi 16/09/2026. */
	const MAU_NEN  = '#0B1F3A';
	const MAU_NHAN = '#0B1F3A';

	public static function slug() {
		$s = get_option( self::O_SLUG );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function ten() {
		$t = trim( (string) get_option( self::O_TEN, '' ) );
		return '' !== $t ? $t : self::TEN_MD;
	}

	/** Địa chỉ app. Không có đường dẫn đẹp thì lui về tham số truy vấn. */
	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'ccapp', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'luat' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function luat() {
		$s = self::slug();
		/* Ba luật, không gộp thành một luật bắt tất rồi tự phân loại trong PHP: gộp thì
		   `/cc/bat-ky-thu-gi` cũng rơi vào đây và trả về trang app, tức là site tự đẻ ra vô số
		   địa chỉ trùng nội dung. */
		add_rewrite_rule( '^' . $s . '/?$', 'index.php?ccapp=app', 'top' );
		add_rewrite_rule( '^' . $s . '/manifest\.json$', 'index.php?ccapp=manifest', 'top' );
		add_rewrite_rule( '^' . $s . '/sw\.js$', 'index.php?ccapp=sw', 'top' );
	}

	public static function query_vars( $v ) { $v[] = 'ccapp'; return $v; }

	public static function maybe_render() {
		$viec = (string) get_query_var( 'ccapp' );
		/* Đường lui khi hosting chưa bật đường dẫn đẹp, hoặc bảng luật chưa được nạp lại. */
		if ( '' === $viec && isset( $_GET['ccapp'] ) ) {
			$viec = sanitize_key( wp_unslash( $_GET['ccapp'] ) );
		}
		if ( '' === $viec ) { return; }

		if ( 'manifest' === $viec ) { self::ra_manifest(); }
		if ( 'sw' === $viec )       { self::ra_sw(); }
		if ( 'app' === $viec )      { self::ra_app(); }
	}

	// ============================================================================== tờ khai app

	public static function ra_manifest() {
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );

		$goc = self::url();
		$ten = self::ten();
		$m   = array(
			/* `id` cố định để lần sau đổi đường dẫn thì máy vẫn coi là CÙNG một app, không mọc
			   thêm một biểu tượng thứ hai bên cạnh cái người ta đã cài. */
			'id'               => $goc,
			'name'             => $ten . ' K&H',
			'short_name'       => $ten,
			'description'      => 'Chấm công online của K&H — chấm vào/ra bằng ảnh và định vị.',
			'lang'             => 'vi',
			'dir'              => 'ltr',
			'start_url'        => $goc,
			'scope'            => $goc,
			'display'          => 'standalone',
			'display_override' => array( 'standalone', 'minimal-ui' ),
			'orientation'      => 'portrait',
			'theme_color'      => self::MAU_NHAN,
			'background_color' => self::MAU_NEN,
			'icons'            => array(
				array(
					'src'     => CCAPP_URL . 'assets/bieu-tuong-192.png',
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				array(
					'src'     => CCAPP_URL . 'assets/bieu-tuong-512.png',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				/* ⚠️ `maskable` để RIÊNG một mục. Gộp `"purpose": "any maskable"` vào một tấm là
				   Android vừa dùng nó để cắt theo hình máy (cụt mất viền đồng hồ) vừa dùng nguyên
				   tấm ở chỗ khác — một tấm không thể vừa chừa lề rộng vừa kín khung. */
				array(
					'src'     => CCAPP_URL . 'assets/bieu-tuong-512-maskable.png',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'maskable',
				),
			),
		);
		echo wp_json_encode( $m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	// ================================================================================== thợ nền

	public static function ra_sw() {
		$tep = CCAPP_DIR . 'assets/sw.js';
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		/* Cho phép quản cả site, phòng khi mai này app dời sang đường dẫn khác. Thừa quyền một
		   chút thì không sao; thiếu thì thợ nền không quản được trang nào (xem khối đầu tệp). */
		header( 'Service-Worker-Allowed: /' );

		if ( ! is_readable( $tep ) ) {
			/* Không im lặng trả rỗng: một thợ nền rỗng vẫn "đăng ký thành công" và từ đó app
			   không bao giờ mở được khi mất mạng, mà không chỗ nào báo gì. */
			echo "// thiếu assets/sw.js trong bản cài\nthrow new Error('vhcp-cc-app: thiếu assets/sw.js');\n";
			exit;
		}

		$js = (string) file_get_contents( $tep );
		/* Số bản đi THẲNG VÀO NỘI DUNG tệp, không đi qua `?v=` trên địa chỉ.
		   Trình duyệt nhận ra thợ nền mới bằng cách so TỪNG BYTE của tệp cũ với tệp mới; đổi địa
		   chỉ thì thành một thợ nền KHÁC, và thợ cũ vẫn nằm đó quản trang như thường. */
		$js = str_replace( '__BAN__', CCAPP_VERSION, $js );
		echo $js; // phpcs:ignore WordPress.Security.EscapeOutput -- JavaScript, không phải HTML
		exit;
	}

	// ================================================================================ trang app

	/** Chấm Công có cài không, và có trang trạm không. */
	public static function co_tram() {
		return class_exists( 'VHCC_Tram' ) && method_exists( 'VHCC_Tram', 'render' );
	}

	public static function ra_app() {
		/* 🔴 GÁC NGAY TẠI CHỖ GỌI, không tin một hàm kiểm tra ở xa.
		   Hai plugin cài rời nhau nên bản có thể lệch: `class_exists()` chỉ nói CÓ PLUGIN, không
		   nói bản ấy CÓ HÀM mình định gọi. Gọi hụt một hàm tĩnh là lỗi chết, trắng nguyên trang
		   WordPress — mà đây lại đúng là trang nhân viên bấm để chấm công.
		   `tools/test/kiem-goi-cheo.php` canh đúng luật này cho cả kho. */
		if ( ! class_exists( 'VHCC_Tram' ) || ! method_exists( 'VHCC_Tram', 'render' ) ) {
			self::trang_thieu();
		}

		/* Gọi ĐÚNG hàm mà /cham-cong-online gọi, rồi chèn thẻ PWA vào kết quả — xem khối đầu
		   tệp plugin về việc không dựng đường thứ hai. */
		ob_start();
		VHCC_Tram::render();
		$html = (string) ob_get_clean();

		echo self::chen( $html ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML đã dựng sẵn
		exit;
	}

	/**
	 * Chèn khối <head> của app vào trang trạm.
	 *
	 * 🔴 CHÈN TRƯỚC `</head>`, KHÔNG PHẢI SAU `<head>`. Trang trạm tự khai thẻ `viewport` của nó;
	 *    chèn lên trước thì hai thẻ viewport cùng tồn tại và trình duyệt lấy cái sau — tức là cái
	 *    của trang trạm — nên chèn sau cũng chẳng để làm gì, mà lỡ mai này mình có khai viewport
	 *    thì lại âm thầm bị đè. Chèn ở cuối <head> là thứ tự rõ ràng: cái của mình nói sau cùng.
	 *
	 * ⚠️ KHÔNG khai `viewport` ở đây. Trang trạm khai rồi; khai lần hai là hai luật cho cùng một
	 *    thứ, và ngày nào bên kia sửa thì không ai đoán được cái nào đang thắng.
	 */
	public static function chen( $html ) {
		$them = self::khoi_head();

		$vt = stripos( $html, '</head>' );
		if ( false !== $vt ) {
			return substr( $html, 0, $vt ) . $them . substr( $html, $vt );
		}

		/* Trang trạm đổi cấu trúc tới mức không còn `</head>`. Đường lui: nhét ngay đầu <body>.
		   Thẻ <link rel="manifest"> nằm trong <body> vẫn được các trình duyệt hiện nay đọc, nên
		   app không chết — nhưng đây là ca BẤT THƯỜNG, và `tools/test/kiem-cc-app.php` canh đúng
		   chuyện `templates/tram.php` còn `</head>` để mình biết trước khi người dùng biết. */
		if ( preg_match( '/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			$vt2 = $m[0][1] + strlen( $m[0][0] );
			return substr( $html, 0, $vt2 ) . $them . substr( $html, $vt2 );
		}

		return $them . $html;
	}

	/** Mấy thẻ biến trang web thành app trên máy. */
	public static function khoi_head() {
		$ten = self::ten();
		$h   = "\n<!-- vhcp-cc-app " . esc_html( CCAPP_VERSION ) . " -->\n";

		$h .= '<link rel="manifest" href="' . esc_url( self::url() . 'manifest.json' ) . "\">\n";
		$h .= '<meta name="theme-color" content="' . esc_attr( self::MAU_NHAN ) . "\">\n";

		/* ── iOS ────────────────────────────────────────────────────────────────────────────
		 * 🔴 iPhone KHÔNG ĐỌC `icons` trong manifest.json để lấy biểu tượng màn hình chính. Nó
		 *    chỉ đọc thẻ `apple-touch-icon` dưới đây. Thiếu thẻ này thì manifest có đẹp mấy iOS
		 *    cũng chụp đại màn hình lúc ấy làm biểu tượng — và muốn sửa thì phải xoá app rồi cài
		 *    lại, không có nút làm mới nào cả.
		 *
		 * ⚠️ `status-bar-style` để `black`, CỐ Ý KHÔNG dùng `black-translucent`. Bản translucent
		 *    đẹp hơn nhưng nó đẩy nội dung chui lên dưới thanh giờ/pin, và muốn không bị che thì
		 *    phải chêm `env(safe-area-inset-top)` vào bố cục — mà bố cục ấy là của trang trạm,
		 *    bên kia đang sửa. Đi vá CSS của người khác từ xa là chỗ hỏng lúc nào không hay.
		 *    `black` thì nội dung bắt đầu NGAY DƯỚI thanh trạng thái, không phải đụng gì.
		 * ────────────────────────────────────────────────────────────────────────────────── */
		$h .= '<link rel="apple-touch-icon" href="' . esc_url( CCAPP_URL . 'assets/bieu-tuong-180.png' ) . "\">\n";
		$h .= '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		$h .= '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		$h .= '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
		$h .= '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $ten ) . "\">\n";

		$h .= '<script src="' . esc_url( CCAPP_URL . 'assets/app.js?v=' . CCAPP_VERSION )
			. '" data-sw="' . esc_url( self::url() . 'sw.js' )
			. '" data-pham-vi="' . esc_url( self::url() )
			. "\" defer></script>\n";

		/**
		 * Chỗ móc cho tính năng thêm sau này.
		 *
		 * Anh Thắng 16/09/2026: *"tính năng thêm sẽ tạo trong app đó"*. Bộ nào muốn góp thêm vào
		 * phần <head> của app thì móc vào đây, khỏi phải sửa tệp này.
		 */
		$them = apply_filters( 'ccapp_chen_head', '' );
		if ( is_string( $them ) && '' !== $them ) { $h .= $them . "\n"; }

		return $h;
	}

	/** Chưa cài Chấm Công thì nói thẳng ra, đừng để trang trắng. */
	private static function trang_thieu() {
		nocache_headers();
		status_header( 503 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>Chưa sẵn sàng</title></head>'
			. '<body style="margin:0;background:' . esc_attr( self::MAU_NEN ) . ';color:#fff;'
			. 'font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif">'
			. '<div style="max-width:420px;margin:0 auto;padding:48px 20px">'
			. '<h1 style="font-size:20px;margin:0 0 12px">App chấm công chưa chạy được</h1>'
			. '<p style="opacity:.85;margin:0 0 8px">Bộ này chỉ là lớp vỏ app. Phần chấm công nằm '
			. 'trong plugin <b>Chấm Công</b> — trang chưa cài hoặc đang tắt nó.</p>'
			. '<p style="opacity:.85;margin:0">Cài và kích hoạt <b>Chấm Công</b> rồi mở lại trang này.</p>'
			. '</div></body></html>';
		exit;
	}
}
