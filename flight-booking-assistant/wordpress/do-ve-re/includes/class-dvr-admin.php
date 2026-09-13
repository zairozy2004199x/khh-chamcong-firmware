<?php
/** Trang quản trị: danh sách đơn và cài đặt. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Admin {

	public static function khoi_dong() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'dang_ky_cai_dat' ) );
	}

	public static function menu() {
		add_menu_page( 'Dò Vé Rẻ', 'Dò Vé Rẻ', 'manage_options', 'dovere', array( __CLASS__, 'trang_don' ), 'dashicons-tickets-alt', 56 );
		add_submenu_page( 'dovere', 'Đơn hàng', 'Đơn hàng', 'manage_options', 'dovere', array( __CLASS__, 'trang_don' ) );
		add_submenu_page( 'dovere', 'Cài đặt Dò Vé Rẻ', 'Cài đặt', 'manage_options', 'dovere-cai-dat', array( __CLASS__, 'trang_cai_dat' ) );
	}

	public static function dang_ky_cai_dat() {
		register_setting( 'dovere', 'dovere_settings', array( 'sanitize_callback' => array( __CLASS__, 'lam_sach' ) ) );
	}

	public static function lam_sach( $v ) {
		$v = (array) $v;
		$ra = array();
		foreach ( array( 'shop_name', 'bank_id', 'bank_account', 'bank_name', 'bank_label', 'amadeus_id', 'amadeus_secret', 'webhook_secret', 'tax_api' ) as $k ) {
			$ra[ $k ] = sanitize_text_field( isset( $v[ $k ] ) ? $v[ $k ] : '' );
		}
		$ra['amadeus_env'] = 'production' === ( isset( $v['amadeus_env'] ) ? $v['amadeus_env'] : '' ) ? 'production' : 'test';
		foreach ( array( 'fee_pct', 'fee_flat', 'fee_min', 'hold_minutes', 'order_page' ) as $k ) {
			$ra[ $k ] = max( 0, (float) ( isset( $v[ $k ] ) ? $v[ $k ] : 0 ) );
		}
		$ra['hold_minutes'] = max( 5, (int) $ra['hold_minutes'] );
		$ra['order_page']   = (int) $ra['order_page'];
		return $ra;
	}

	public static function trang_don() {
		wp_enqueue_style( 'dovere', DVR_URL . 'assets/dovere.css', array(), DVR_VERSION );
		wp_enqueue_script( 'dovere-autofill', DVR_URL . 'assets/autofill.js', array(), DVR_VERSION, true );
		wp_enqueue_script( 'dovere-quan-tri', DVR_URL . 'assets/quan-tri.js', array( 'dovere-autofill' ), DVR_VERSION, true );
		wp_localize_script( 'dovere-quan-tri', 'DVR', array(
			'rest'  => esc_url_raw( rest_url( DVR_Rest::NS ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
		include DVR_DIR . 'templates/quan-tri.php';
	}

	public static function trang_cai_dat() {
		$cd = dvr_cai_dat();
		include DVR_DIR . 'templates/cai-dat.php';
	}
}
