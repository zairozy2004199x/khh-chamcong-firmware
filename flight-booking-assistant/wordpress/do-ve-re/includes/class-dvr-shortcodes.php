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
		add_action( 'wp_head', array( __CLASS__, 'mau_thuong_hieu' ), 20 );
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
			'coGiaThat' => ( 'duffel' === $cd['nguon'] && $cd['duffel_token'] )
				|| ( 'amadeus' === $cd['nguon'] && $cd['amadeus_id'] ),
			'coBan'     => (bool) ( $cd['order_page'] && $cd['bank_account'] ),
			'fee'       => array(
				'pct'  => (float) $cd['fee_pct'],
				'flat' => (int) $cd['fee_flat'],
				'min'  => (int) $cd['fee_min'],
			),
			'hold'      => (int) $cd['hold_minutes'],
		);
	}

	/** Bộ chữ Baloo 2 + Be Vietnam Pro; thiếu nó là trang rơi về font hệ thống. */
	/** Màu thương hiệu khai trong Cài đặt sẽ đè lên màu cam mặc định. */
	public static function mau_thuong_hieu() {
		$m = dvr_cai_dat( 'mau_chinh', '' );
		if ( ! $m || ! dvr_mau_rgb( $m ) ) {
			return;
		}
		$rgb  = dvr_mau_rgb( $m );
		$chu  = dvr_chu_tren_nen( $m );   // chọn bên tương phản cao hơn, không đoán bằng mắt
		$bong = 'rgba(' . implode( ',', $rgb ) . ',.55)';
		echo '<style id="dvr-mau">:root{'
			. '--accent:' . esc_attr( $m ) . ';'
			. '--accent-2:' . esc_attr( dvr_lam_nhat( $m, 0.22 ) ) . ';'
			. '--on-accent:' . esc_attr( $chu ) . ';'
			. '--sh-cam:0 10px 22px -10px ' . esc_attr( $bong ) . ';}</style>';
	}

	public static function nap_chu() {
		wp_enqueue_style(
			'dovere-chu',
			'https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap',
			array(),
			null
		);
	}

	private static function nap( $ten, $phu_thuoc = array() ) {
		self::nap_chu();
		wp_enqueue_style( 'dovere', DVR_URL . 'assets/dovere.css', array( 'dovere-chu' ), DVR_VERSION );
		wp_enqueue_script( 'dovere-' . $ten, DVR_URL . 'assets/' . $ten . '.js', $phu_thuoc, DVR_VERSION, true );
		wp_localize_script( 'dovere-' . $ten, 'DVR', self::cau_hinh_js() );
	}

	/**
	 * Khối thông tin công ty ở chân trang. Trả về chuỗi; truyền true để in luôn.
	 * Bỏ trống tên công ty trong Cài đặt là khối này biến mất.
	 */
	public static function chan_trang( $in_luon = false ) {
		$cd = dvr_cai_dat();
		if ( empty( $cd['cty_vi'] ) ) {
			return '';
		}
		$dong = function ( $nhan, $gia_tri, $dam = false ) {
			if ( '' === trim( (string) $gia_tri ) ) {
				return '';
			}
			return '<div class="ct-dong"><span>' . esc_html( $nhan ) . '</span> '
				. ( $dam ? '<b>' . esc_html( $gia_tri ) . '</b>' : esc_html( $gia_tri ) ) . '</div>';
		};
		$chi_nhanh = array_filter( array_map( 'trim', preg_split( '/[,\n]+/', (string) $cd['cty_chi_nhanh'] ) ) );

		$h  = '<div class="dvr"><footer class="cty">';
		$h .= '<div class="cty-luoi">';

		$h .= '<div class="cty-cot"><div class="cty-ten">' . esc_html( $cd['cty_vi'] ) . '</div>';
		if ( ! empty( $cd['cty_en'] ) ) {
			$h .= '<div class="cty-en">' . esc_html( $cd['cty_en'] ) . '</div>';
		}
		$h .= $dong( 'Mã số thuế / Tax code:', $cd['cty_mst'], true );
		$h .= $dong( 'Người đại diện / Legal rep.:', $cd['cty_dai_dien'], true );
		$h .= $dong( 'Hoạt động từ / Since:', $cd['cty_tu_ngay'], true );
		$h .= '</div>';

		$h .= '<div class="cty-cot">';
		$h .= $dong( 'Địa chỉ / Address:', $cd['cty_dia_chi'], true );
		$h .= $dong( 'Điện thoại / Phone:', $cd['cty_dien_thoai'], true );
		$h .= $dong( 'Cơ quan quản lý thuế:', $cd['cty_co_quan'], true );
		$h .= '</div>';

		if ( $chi_nhanh ) {
			$h .= '<div class="cty-cot"><div class="cty-dong"><span>Chi nhánh / Branches:</span></div>'
				. '<div class="cty-nhanh">' . implode( ' · ', array_map( 'esc_html', $chi_nhanh ) ) . '</div></div>';
		}

		$h .= '</div><div class="cty-ban-quyen">© ' . esc_html( gmdate( 'Y' ) ) . ' ' . esc_html( $cd['cty_vi'] )
			. '. Toàn bộ bản quyền thuộc công ty. / All rights reserved.</div>';
		$h .= '</footer></div>';

		if ( $in_luon ) {
			echo $h; // phpcs:ignore WordPress.Security.EscapeOutput -- từng phần đã esc ở trên
		}
		return $h;
	}

	public static function bang_gia() {
		self::nap( 'bang-gia' );
		ob_start();
		include DVR_DIR . 'templates/bang-gia.php';
		// khung riêng đã in chân trang rồi, đừng in hai lần
		return ob_get_clean() . ( dvr_cai_dat( 'toan_man', 1 ) ? '' : self::chan_trang() );
	}

	public static function dat_ve() {
		self::nap( 'dat-ve' );
		ob_start();
		include DVR_DIR . 'templates/dat-ve.php';
		return ob_get_clean() . ( dvr_cai_dat( 'toan_man', 1 ) ? '' : self::chan_trang() );
	}
}
