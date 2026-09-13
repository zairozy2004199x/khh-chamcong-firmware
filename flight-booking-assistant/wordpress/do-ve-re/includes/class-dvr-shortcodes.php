<?php
/** Hai shortcode đặt lên trang WordPress. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Shortcodes {

	public static function khoi_dong() {
		add_shortcode( 'do_ve_re', array( __CLASS__, 'bang_gia' ) );
		add_shortcode( 'do_ve_re_dat_ve', array( __CLASS__, 'dat_ve' ) );
		add_action( 'wp', array( __CLASS__, 'an_thanh_quan_tri' ) );
		add_filter( 'template_include', array( __CLASS__, 'khung_rieng' ) );
	}

	/** Trang này có phải trang bán vé của plugin không. */
	public static function la_trang_cua_minh() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post ) {
			return false;
		}
		$trang = (array) get_option( 'dovere_pages', array() );
		if ( in_array( (int) $post->ID, array_map( 'intval', $trang ), true ) ) {
			return true;
		}
		return has_shortcode( $post->post_content, 'do_ve_re' )
			|| has_shortcode( $post->post_content, 'do_ve_re_dat_ve' );
	}

	/** Trang bán vé dựng khung riêng: bỏ header/footer của theme, giãn hết màn hình. */
	public static function khung_rieng( $khung ) {
		if ( self::la_trang_cua_minh() && dvr_cai_dat( 'toan_man', 1 ) ) {
			return DVR_DIR . 'templates/trang-day-du.php';
		}
		return $khung;
	}

	/**
	 * Thanh quản trị đen của WordPress chỉ hiện với người đã đăng nhập, khách
	 * không thấy — nhưng nó che mất đầu trang lúc mình tự xem, nên ẩn đi trên
	 * đúng hai trang bán vé. Tắt được trong Cài đặt nếu muốn giữ.
	 */
	public static function an_thanh_quan_tri() {
		if ( ! self::la_trang_cua_minh() || ! dvr_cai_dat( 'an_thanh', 1 ) ) {
			return;
		}
		add_filter( 'show_admin_bar', '__return_false' );
		// chặn thêm bằng CSS: hook trên chạy sớm hay muộn tuỳ theme, dòng này thì luôn ăn
		add_action( 'wp_head', function () {
			echo '<style>#wpadminbar{display:none !important}html{margin-top:0 !important}'
				. '* html body{margin-top:0 !important}</style>';
		}, 99 );
	}

	private static function cau_hinh_js() {
		$cd = dvr_cai_dat();
		return array(
			'rest'      => esc_url_raw( rest_url( DVR_Rest::NS ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'orderPage' => $cd['order_page'] ? get_permalink( $cd['order_page'] ) : '',
			'shopName'  => $cd['shop_name'],
			'coGiaThat' => (bool) $cd['amadeus_id'],
			'coBan'     => (bool) ( $cd['order_page'] && $cd['bank_account'] ),
			'fee'       => array(
				'pct'  => (float) $cd['fee_pct'],
				'flat' => (int) $cd['fee_flat'],
				'min'  => (int) $cd['fee_min'],
			),
			'hold'      => (int) $cd['hold_minutes'],
		);
	}

	private static function nap( $ten, $phu_thuoc = array() ) {
		wp_enqueue_style( 'dovere', DVR_URL . 'assets/dovere.css', array(), DVR_VERSION );
		wp_enqueue_script( 'dovere-' . $ten, DVR_URL . 'assets/' . $ten . '.js', $phu_thuoc, DVR_VERSION, true );
		wp_localize_script( 'dovere-' . $ten, 'DVR', self::cau_hinh_js() );
	}

	public static function bang_gia() {
		self::nap( 'bang-gia' );
		ob_start();
		include DVR_DIR . 'templates/bang-gia.php';
		return ob_get_clean();
	}

	public static function dat_ve() {
		self::nap( 'dat-ve' );
		ob_start();
		include DVR_DIR . 'templates/dat-ve.php';
		return ob_get_clean();
	}
}
