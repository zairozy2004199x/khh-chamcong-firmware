<?php
/** Trang quản trị: danh sách đơn và cài đặt. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Admin {

	public static function khoi_dong() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'dang_ky_cai_dat' ) );
		add_action( 'admin_post_dvr_tao_trang', array( __CLASS__, 'xu_ly_tao_trang' ) );
		add_action( 'admin_init', array( __CLASS__, 'tu_dung_trang' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'nhac_tao_trang' ) );
	}

	/**
	 * Cài xong hoặc cập nhật đè lên bản cũ thì dựng sẵn trang cho khách — không
	 * phải kích hoạt lại, không phải bấm gì. Chỉ tự chạy một lần cho mỗi phiên bản,
	 * nên ai cố ý xoá trang thì nó không dựng lại sau lưng.
	 */
	public static function tu_dung_trang() {
		if ( get_option( 'dovere_tu_dung' ) === DVR_VERSION ) {
			return;
		}
		update_option( 'dovere_tu_dung', DVR_VERSION, false );
		self::tao_trang();
	}

	/**
	 * Tạo hai trang cho khách nếu chưa có, và ghi nhớ id.
	 * Gọi lúc kích hoạt plugin và khi bấm nút trong trang Cài đặt.
	 */
	public static function tao_trang() {
		$can = array(
			'bang_gia' => array(
				'Vé máy bay giá rẻ',
				've-may-bay-gia-re',
				"Dò giá vé máy bay theo chặng và ngày, chọn chuyến rẻ nhất rồi đặt ngay tại đây.\n\n[do_ve_re]",
			),
			'dat_ve'   => array(
				'Đặt vé',
				'dat-ve',
				"[do_ve_re_dat_ve]",
			),
		);
		$da  = get_option( 'dovere_pages', array() );
		$moi = array();
		foreach ( $can as $khoa => $t ) {
			$id = isset( $da[ $khoa ] ) ? (int) $da[ $khoa ] : 0;
			if ( $id && get_post_status( $id ) && 'trash' !== get_post_status( $id ) ) {
				$moi[ $khoa ] = $id;
				continue;
			}
			$co = get_posts( array(
				'post_type'   => 'page',
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				's'           => $khoa === 'dat_ve' ? '[do_ve_re_dat_ve]' : '[do_ve_re]',
				'numberposts' => 1,
			) );
			if ( $co ) {
				$moi[ $khoa ] = $co[0]->ID;
				continue;
			}
			$moi[ $khoa ] = wp_insert_post( array(
				'post_title'   => $t[0],
				'post_name'    => $t[1],
				'post_content' => $t[2],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
		}
		update_option( 'dovere_pages', $moi );

		// trang đặt vé phải được khai trong cài đặt thì bảng giá mới hiện nút Chọn
		$cd = get_option( 'dovere_settings', array() );
		if ( empty( $cd['order_page'] ) && ! empty( $moi['dat_ve'] ) ) {
			$cd['order_page'] = (int) $moi['dat_ve'];
			update_option( 'dovere_settings', $cd );
		}
		return $moi;
	}

	public static function trang_khach() {
		$p = get_option( 'dovere_pages', array() );
		$ra = array();
		foreach ( array( 'bang_gia', 'dat_ve' ) as $k ) {
			$id = isset( $p[ $k ] ) ? (int) $p[ $k ] : 0;
			$ra[ $k ] = $id && get_post_status( $id ) && 'trash' !== get_post_status( $id ) ? get_permalink( $id ) : '';
		}
		return $ra;
	}

	public static function xu_ly_tao_trang() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'dvr_tao_trang' ) ) {
			wp_die( 'Không đủ quyền.' );
		}
		self::tao_trang();
		wp_safe_redirect( add_query_arg( 'dvr_da_tao', '1', admin_url( 'admin.php?page=dovere-cai-dat' ) ) );
		exit;
	}

	/** Nhắc một lần cho tới khi có trang cho khách. */
	public static function nhac_tao_trang() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$t = self::trang_khach();
		if ( $t['bang_gia'] && $t['dat_ve'] ) {
			return;
		}
		$man = get_current_screen();
		if ( $man && false === strpos( (string) $man->id, 'dovere' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><b>Dò Vé Rẻ:</b> chưa có trang nào cho khách xem. '
			. '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dvr_tao_trang' ), 'dvr_tao_trang' ) ) . '">'
			. 'Tạo hai trang giúp tôi</a> — một trang bảng giá, một trang đặt vé.</p></div>';
	}

	/** Khối link cho khách, hiện ở đầu cả hai trang quản trị. */
	public static function khoi_link() {
		$t = self::trang_khach();
		echo '<div class="notice notice-info inline" style="margin:14px 0;padding:12px 14px">';
		if ( $t['bang_gia'] || $t['dat_ve'] ) {
			echo '<p style="margin:0 0 6px"><b>Link gửi cho khách:</b></p><p style="margin:0">';
			if ( $t['bang_gia'] ) {
				echo 'Bảng giá &nbsp;<a href="' . esc_url( $t['bang_gia'] ) . '" target="_blank"><code>' . esc_html( $t['bang_gia'] ) . '</code></a><br>';
			}
			if ( $t['dat_ve'] ) {
				echo 'Trang đặt vé &nbsp;<a href="' . esc_url( $t['dat_ve'] ) . '" target="_blank"><code>' . esc_html( $t['dat_ve'] ) . '</code></a>';
			}
			echo '</p>';
		} else {
			echo '<p style="margin:0">Chưa có trang cho khách. '
				. '<a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dvr_tao_trang' ), 'dvr_tao_trang' ) ) . '">Tạo hai trang cho tôi</a></p>';
		}
		echo '</div>';
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
		foreach ( array( 'shop_name', 'bank_id', 'bank_account', 'bank_name', 'bank_label', 'amadeus_id', 'amadeus_secret', 'webhook_secret', 'tax_api',
			'duffel_token', 'hero_ten', 'hero_tieu_de', 'hero_phu_de', 'cty_vi', 'cty_en', 'cty_mst', 'cty_dai_dien', 'cty_tu_ngay', 'cty_dia_chi', 'cty_dien_thoai', 'cty_co_quan', 'cty_chi_nhanh' ) as $k ) {
			$ra[ $k ] = sanitize_text_field( isset( $v[ $k ] ) ? $v[ $k ] : '' );
		}
		$mau               = isset( $v['mau_chinh'] ) ? trim( (string) $v['mau_chinh'] ) : '';
		$ra['mau_chinh']   = dvr_mau_rgb( $mau ) ? ( '#' === substr( $mau, 0, 1 ) ? strtoupper( $mau ) : '#' . strtoupper( $mau ) ) : '';
		$ra['logo_url']    = esc_url_raw( isset( $v['logo_url'] ) ? $v['logo_url'] : '' );
		$ng                = isset( $v['nguon'] ) ? $v['nguon'] : 'mo_phong';
		$ra['nguon']       = in_array( $ng, array( 'mo_phong', 'duffel', 'amadeus' ), true ) ? $ng : 'mo_phong';
		$ra['amadeus_env'] = 'production' === ( isset( $v['amadeus_env'] ) ? $v['amadeus_env'] : '' ) ? 'production' : 'test';
		foreach ( array( 'fee_pct', 'fee_flat', 'fee_min', 'hold_minutes', 'order_page' ) as $k ) {
			$ra[ $k ] = max( 0, (float) ( isset( $v[ $k ] ) ? $v[ $k ] : 0 ) );
		}
		$ra['hold_minutes'] = max( 5, (int) $ra['hold_minutes'] );
		$ra['order_page']   = (int) $ra['order_page'];
		$ra['an_thanh']     = empty( $v['an_thanh'] ) ? 0 : 1;
		$ra['toan_man']     = empty( $v['toan_man'] ) ? 0 : 1;
		return $ra;
	}

	public static function trang_don() {
		self::khoi_link();
		DVR_Shortcodes::nap_chu();
		wp_enqueue_style( 'dovere', DVR_URL . 'assets/dovere.css', array( 'dovere-chu' ), DVR_VERSION );
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
