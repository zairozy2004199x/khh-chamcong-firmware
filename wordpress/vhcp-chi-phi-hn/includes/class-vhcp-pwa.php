<?php
/**
 * LỚP VỎ APP ĐIỆN THOẠI (PWA) CHO TRANG CHI PHÍ.
 *
 * Anh Thắng 22/09/2026: *"Xong chuyển vào app điện thoại để chạy giao diện điện thoại nhé"*.
 *
 * =================================================================================================
 * 🔴 KHÔNG DỰNG ĐƯỜNG DẪN THỨ HAI
 * =================================================================================================
 * App Chấm Công phải mở một địa chỉ riêng (`/cc/`) vì nó là plugin RỜI, khoác vỏ cho trang của
 * plugin khác. Ở đây thì trang chi phí là của chính bộ này — nên lớp vỏ gắn thẳng vào địa chỉ
 * đang chạy (`/chi-phi-…/`), không đẻ thêm một đường nữa.
 *
 * Đẻ thêm một đường là đẻ thêm một trang trùng nội dung: link cũ và link app cùng sống, người ta
 * gửi cho nhau cái nào cũng được, rồi một hôm sửa cái này quên cái kia. Và tệ hơn: thợ nền chỉ
 * quản được những trang NẰM DƯỚI thư mục chứa chính nó, nên hai đường là hai phạm vi.
 *
 * Hai địa chỉ phụ, cùng nằm dưới đường của trang:
 *   /<slug>/manifest.json   tờ khai để máy biết đây là app (tên, biểu tượng, màu, toàn màn hình)
 *   /<slug>/sw.js           thợ nền: giữ vỏ app để mở được lúc mạng chập chờn
 *
 * 🔴 `sw.js` PHẢI ĐƯỢC PHỤC VỤ TỪ `/<slug>/`, KHÔNG PHẢI TỪ `wp-content/plugins/…`. Thợ nền chỉ
 *    quản được đường nằm dưới thư mục chứa nó. Để ở `/wp-content/plugins/vhcp-chi-phi-hn/assets/js/`
 *    thì phạm vi của nó là thư mục ấy — đăng ký vẫn "thành công", thợ nền vẫn chạy, mà không bao
 *    giờ đỡ được lượt tải nào. Hỏng hoàn toàn im lặng: công cụ lập trình báo xanh, ngoại tuyến
 *    thì trắng trang. Nên tệp JS để trong `assets/js/` cho dễ soát cú pháp, còn ĐỊA CHỈ phục vụ
 *    nó là `/<slug>/sw.js`.
 *
 * ⚠️ BỐN BẢN, BỐN APP RIÊNG. `id`/`scope`/`start_url` đều dựng từ slug, mà `tools/tach-ban-vung.sh`
 *    đổi slug cho từng bản vùng — nên cài cả hai lên một máy là hai biểu tượng riêng, không cái
 *    nào đè cái nào. Tên bày dưới biểu tượng lấy từ `VHCPHN_App::ten_trang()`, thứ script ấy cũng
 *    đã đổi sẵn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPHN_Pwa {

	/** Màu thanh trạng thái + màn chờ. Cùng mã với chữ nhấn trong `templates/app.html`. */
	const MAU_NHAN = '#2545ff';
	const MAU_NEN  = '#ffffff';

	public static function init() {
		foreach ( VHCPHN_App::cac_slug() as $s ) {
			/* Hai luật riêng, không gộp thành một luật bắt tất rồi phân loại trong PHP: gộp thì
			   `/<slug>/bat-ky-thu-gi` cũng rơi vào đây, tức site tự đẻ ra vô số địa chỉ. */
			add_rewrite_rule( '^' . $s . '/manifest\.json$', 'index.php?vhcphn_pwa=manifest', 'top' );
			add_rewrite_rule( '^' . $s . '/sw\.js$', 'index.php?vhcphn_pwa=sw', 'top' );
		}
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		/* Ưu tiên 4 — TRƯỚC `VHCPHN_App::maybe_render()` (mặc định 10). Nó nhận cả `?vhcphn=app`,
		   mà đường lui của hai tệp này cũng đi qua tham số truy vấn; để sau là trang app trả về
		   thay cho tờ khai, và trình duyệt báo "manifest không đọc được" mà không nói vì sao. */
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 4 );
	}

	public static function query_vars( $v ) { $v[] = 'vhcphn_pwa'; return $v; }

	public static function maybe_render() {
		$viec = (string) get_query_var( 'vhcphn_pwa' );
		/* Đường lui khi hosting chưa bật đường dẫn đẹp, hoặc bảng luật chưa được nạp lại. */
		if ( '' === $viec && isset( $_GET['vhcphn_pwa'] ) ) {
			$viec = sanitize_key( wp_unslash( $_GET['vhcphn_pwa'] ) );
		}
		if ( 'manifest' === $viec ) { self::ra_manifest(); }
		if ( 'sw' === $viec )       { self::ra_sw(); }
	}

	/** Địa chỉ gốc của app — chính là trang chi phí. */
	public static function goc() { return trailingslashit( VHCPHN_App::app_url() ); }

	/** Địa chỉ một tệp phụ, có đường lui khi chưa bật đường dẫn đẹp. */
	public static function url_phu( $viec ) {
		if ( get_option( 'permalink_structure' ) ) {
			return self::goc() . ( 'manifest' === $viec ? 'manifest.json' : 'sw.js' );
		}
		return add_query_arg( 'vhcphn_pwa', $viec, home_url( '/' ) );
	}

	// ============================================================================== tờ khai app

	public static function ra_manifest() {
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		$goc = self::goc();
		$ten = VHCPHN_App::ten_trang();
		$m   = array(
			/* `id` cố định theo đường dẫn: đổi tên trang về sau thì máy vẫn coi là CÙNG một app,
			   không mọc thêm một biểu tượng thứ hai bên cạnh cái người ta đã cài. */
			'id'               => $goc,
			'name'             => $ten,
			/* iOS cắt còn khoảng 12 ký tự dưới biểu tượng — đừng để tên dài bị cắt giữa chừng. */
			'short_name'       => mb_substr( $ten, 0, 12 ),
			'description'      => 'Lập và duyệt đơn chi phí của K&H ngay trên điện thoại.',
			'lang'             => 'vi',
			'dir'              => 'ltr',
			'start_url'        => $goc,
			'scope'            => $goc,
			'display'          => 'standalone',
			'display_override' => array( 'standalone', 'minimal-ui' ),
			/* ⚠️ KHÔNG khoá `portrait`. Bảng mã TK Nợ và bảng loại chi phí đều rộng; kế toán mở
			   trên máy tính bảng xoay ngang là cách duy nhất xem được cả bảng. App Chấm Công khoá
			   dọc vì ở đó chỉ có nút chấm và ảnh — khác việc, khác luật. */
			'theme_color'      => self::MAU_NHAN,
			'background_color' => self::MAU_NEN,
			'icons'            => array(
				array(
					'src'     => VHCPHN_URL . 'assets/img/app/bieu-tuong-192.png',
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				array(
					'src'     => VHCPHN_URL . 'assets/img/app/bieu-tuong-512.png',
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				/* ⚠️ `maskable` để RIÊNG một mục. Gộp `"purpose": "any maskable"` vào một tấm là
				   Android vừa dùng nó để cắt theo hình máy (cụt mất viền) vừa dùng nguyên tấm ở
				   chỗ khác — một tấm không thể vừa chừa lề rộng vừa kín khung. */
				array(
					'src'     => VHCPHN_URL . 'assets/img/app/bieu-tuong-512-maskable.png',
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
		$tep = VHCPHN_DIR . 'assets/js/sw.js';
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		/* Cho phép quản cả site, phòng khi mai này app dời sang đường dẫn khác. Thừa quyền một
		   chút thì không sao; thiếu thì thợ nền không quản được trang nào. */
		header( 'Service-Worker-Allowed: /' );

		if ( ! is_readable( $tep ) ) {
			/* Không im lặng trả rỗng: một thợ nền rỗng vẫn "đăng ký thành công", và từ đó app
			   không bao giờ mở được khi mất mạng mà chẳng chỗ nào báo gì. */
			echo "// thiếu assets/js/sw.js trong bản cài\nthrow new Error('vhcp-chi-phi-hn: thiếu assets/js/sw.js');\n";
			exit;
		}

		$js = (string) file_get_contents( $tep );
		/* Số bản đi THẲNG VÀO NỘI DUNG tệp, không đi qua `?v=` trên địa chỉ.
		   Trình duyệt nhận ra thợ nền mới bằng cách so TỪNG BYTE tệp cũ với tệp mới; đổi địa chỉ
		   thì thành một thợ nền KHÁC, và thợ cũ vẫn nằm đó quản trang như thường. */
		$js = str_replace( '__BAN__', VHCPHN_VERSION, $js );
		/* Thư mục tệp tĩnh của CHÍNH bản này. Thợ nền so địa chỉ với nó để biết tệp nào của mình
		   mà nhớ hẳn — xem chốt 🔴 ở `laTinh()` trong `sw.js`: dò bằng khuôn chữ là ba bản vùng
		   hỏng im lặng, vì script tách đổi mọi chuỗi `vhcphn` trong tệp ấy. */
		$js = str_replace( '__GOC_TINH__', VHCPHN_URL, $js );
		echo $js; // phpcs:ignore WordPress.Security.EscapeOutput -- JavaScript, không phải HTML
		exit;
	}

	// ============================================================================ thẻ trong head

	/**
	 * Mấy thẻ biến trang web thành app trên máy.
	 *
	 * ⚠️ KHÔNG khai `viewport` ở đây — `templates/app.html` khai rồi. Khai lần hai là hai luật cho
	 *    cùng một thứ, và ngày nào bên kia sửa thì không ai đoán được cái nào đang thắng.
	 */
	public static function khoi_head() {
		$ten = VHCPHN_App::ten_trang();
		$h   = "\n<!-- vhcphn-pwa " . esc_html( VHCPHN_VERSION ) . " -->\n";
		$h  .= '<link rel="manifest" href="' . esc_url( self::url_phu( 'manifest' ) ) . "\">\n";
		$h  .= '<meta name="theme-color" content="' . esc_attr( self::MAU_NHAN ) . "\">\n";

		/* ── iOS ────────────────────────────────────────────────────────────────────────────
		 * 🔴 iPhone KHÔNG ĐỌC `icons` trong manifest.json để lấy biểu tượng màn hình chính. Nó
		 *    chỉ đọc thẻ `apple-touch-icon` dưới đây. Thiếu thẻ này thì manifest có đẹp mấy iOS
		 *    cũng chụp đại màn hình lúc ấy làm biểu tượng — và muốn sửa thì phải xoá app rồi cài
		 *    lại, không có nút làm mới nào cả.
		 *
		 * ⚠️ `status-bar-style` để `black`, CỐ Ý KHÔNG dùng `black-translucent`. Bản translucent
		 *    đẹp hơn nhưng nó đẩy nội dung chui lên dưới thanh giờ/pin, và muốn không bị che thì
		 *    phải chêm `env(safe-area-inset-top)` vào bố cục của cả trang. `black` thì nội dung
		 *    bắt đầu NGAY DƯỚI thanh trạng thái, không phải đụng gì.
		 * ────────────────────────────────────────────────────────────────────────────────── */
		$h .= '<link rel="apple-touch-icon" href="' . esc_url( VHCPHN_URL . 'assets/img/app/bieu-tuong-180.png' ) . "\">\n";
		$h .= '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		$h .= '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		$h .= '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
		$h .= '<meta name="apple-mobile-web-app-title" content="' . esc_attr( mb_substr( $ten, 0, 12 ) ) . "\">\n";

		$h .= '<script src="' . esc_url( VHCPHN_URL . 'assets/js/pwa.js?ver=' . rawurlencode( VHCPHN_VERSION ) )
			. '" data-sw="' . esc_url( self::url_phu( 'sw' ) )
			. '" data-pham-vi="' . esc_url( self::goc() )
			. "\" defer></script>\n";

		return $h;
	}
}
