<?php
/**
 * Menu và trang trong wp-admin. Nội dung màn hình nằm ở KHTC_Trang — lớp này
 * chỉ lo việc đăng ký menu và nạp CSS.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Admin {

	public static function menu() {
		add_menu_page( 'Tài Chính K&H', 'Tài Chính K&H', KHTC_CAP, 'khtc', array( __CLASS__, 'tong_quan' ), 'dashicons-bank', 57 );
		add_submenu_page( 'khtc', 'Tổng quan', 'Tổng quan', KHTC_CAP, 'khtc', array( __CLASS__, 'tong_quan' ) );
		add_submenu_page( 'khtc', 'Ngân hàng', 'Ngân hàng', KHTC_CAP, 'khtc-ngan-hang', array( __CLASS__, 'ngan_hang' ) );
		add_submenu_page( 'khtc', 'Giao dịch / Sao kê', 'Giao dịch / Sao kê', KHTC_CAP, 'khtc-giao-dich', array( __CLASS__, 'giao_dich' ) );
		add_submenu_page( 'khtc', 'Đối soát', 'Đối soát', KHTC_CAP, 'khtc-doi-soat', array( __CLASS__, 'doi_soat' ) );
		add_submenu_page( 'khtc', 'Chi phí', 'Chi phí', KHTC_CAP, 'khtc-chi-phi', array( __CLASS__, 'chi_phi' ) );
		add_submenu_page( 'khtc', 'Đối soát chi phí', 'Đối soát chi phí', KHTC_CAP, 'khtc-doi-soat-chi-phi', array( __CLASS__, 'doi_soat_chi_phi' ) );
		add_submenu_page( 'khtc', 'Sao lưu', 'Sao lưu', KHTC_CAP, 'khtc-sao-luu', array( __CLASS__, 'sao_luu' ) );
		add_submenu_page( 'khtc', 'Mở bản web ngoài', 'Mở bản web ngoài ↗', KHTC_CAP, 'khtc-web', array( __CLASS__, 'di_ra_web' ) );
	}

	public static function tong_quan() { KHTC_Trang::tong_quan(); }
	public static function ngan_hang() { KHTC_Trang::ngan_hang(); }
	public static function giao_dich() { KHTC_Trang::giao_dich(); }
	public static function doi_soat()  { KHTC_Trang::doi_soat(); }
	public static function chi_phi()   { KHTC_Trang::chi_phi(); }
	public static function doi_soat_chi_phi() { KHTC_Trang::doi_soat_chi_phi(); }
	public static function sao_luu()   { KHTC_Trang::sao_luu(); }

	/**
	 * Mục menu này chỉ để nhảy sang bản web ngoài. Chuyển hướng phải làm sớm ở
	 * admin_init, chứ đến lúc gọi hàm trang thì HTML đã gửi đi rồi.
	 */
	public static function di_ra_web() {
		printf(
			'<div class="wrap khtc"><div class="khtc-panel"><h2>Bản web ngoài</h2><p><a class="button button-primary" href="%s">Mở %s</a></p></div></div>',
			esc_url( KHTC_Web::duong_dan() ),
			esc_html( KHTC_Web::duong_dan() )
		);
	}

	public static function nhay_ra_web() {
		if ( isset( $_GET['page'] ) && 'khtc-web' === $_GET['page'] && current_user_can( KHTC_CAP ) ) {
			wp_safe_redirect( KHTC_Web::duong_dan() );
			exit;
		}
	}

	public static function nap_style( $hook ) {
		if ( strpos( (string) $hook, 'khtc' ) === false ) { return; }
		wp_enqueue_style( 'khtc', KHTC_URL . 'assets/khtc.css', array(), KHTC_VERSION );
	}
}
