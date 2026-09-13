<?php
/** Hai shortcode đặt lên trang WordPress. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Shortcodes {

	public static function khoi_dong() {
		add_shortcode( 'do_ve_re', array( __CLASS__, 'bang_gia' ) );
		add_shortcode( 'do_ve_re_dat_ve', array( __CLASS__, 'dat_ve' ) );
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
