<?php
/**
 * Plugin Name:       Dò Vé Rẻ
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       So giá vé máy bay, nhận đơn của khách qua chuyển khoản VietQR, và gửi email mã đặt chỗ. Dùng hai shortcode [do_ve_re] và [do_ve_re_dat_ve].
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       do-ve-re
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DVR_VERSION', '1.1.0' );
define( 'DVR_FILE', __FILE__ );
define( 'DVR_DIR', plugin_dir_path( __FILE__ ) );
define( 'DVR_URL', plugin_dir_url( __FILE__ ) );

require_once DVR_DIR . 'includes/class-dvr-store.php';
require_once DVR_DIR . 'includes/class-dvr-amadeus.php';
require_once DVR_DIR . 'includes/class-dvr-duffel.php';
require_once DVR_DIR . 'includes/class-dvr-dai-ly.php';
require_once DVR_DIR . 'includes/class-dvr-mail.php';
require_once DVR_DIR . 'includes/class-dvr-rest.php';
require_once DVR_DIR . 'includes/class-dvr-shortcodes.php';
require_once DVR_DIR . 'includes/class-dvr-admin.php';

register_activation_hook( __FILE__, function () {
	DVR_Store::tao_bang();
	DVR_Admin::tao_trang();   // tạo sẵn trang bảng giá và trang đặt vé cho khách
} );

add_action( 'plugins_loaded', function () {
	DVR_Rest::khoi_dong();
	DVR_Shortcodes::khoi_dong();
	if ( is_admin() ) {
		DVR_Admin::khoi_dong();
	}
} );

/** Cấu hình plugin, đã trộn với mặc định. */
function dvr_cai_dat( $khoa = null, $mac_dinh = null ) {
	$mac = array(
		'shop_name'      => get_bloginfo( 'name' ),
		'bank_id'        => '',
		'bank_account'   => '',
		'bank_name'      => '',
		'bank_label'     => '',
		'fee_pct'        => 3,
		'fee_flat'       => 0,
		'fee_min'        => 0,
		'hold_minutes'   => 30,
		'bao_gia_truoc'  => 1,
		'bao_gia_phut'   => 15,
		'nguon'          => 'mo_phong',
		'duffel_token'   => '',
		'dl_ten'         => '',
		'dl_url'         => '',
		'dl_token'       => '',
		'dl_header'      => 'Authorization',
		'dl_body'        => '',
		'dl_map'         => '',
		'amadeus_id'     => '',
		'amadeus_secret' => '',
		'amadeus_env'    => 'test',
		'webhook_secret' => '',
		'tax_api'        => 'https://api.vietqr.io/v2/business/',
		'order_page'     => 0,
		'an_thanh'       => 1,
		'toan_man'       => 1,
		'logo_url'       => '',
		'mau_chinh'      => '',
		'hero_ten'       => 'Dò Vé Rẻ',
		'hero_tieu_de'   => 'Vé máy bay trực tuyến',
		'hero_phu_de'    => 'Dò giá theo chặng và ngày, so nhiều hãng cùng lúc, chọn chuyến rẻ nhất rồi đặt ngay tại đây. Chúng tôi mua vé và gửi mã đặt chỗ vào email của quý khách.',
		'cty_vi'         => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H',
		'cty_en'         => 'K&H SERVICES AND ENTERTAINMENT COMPANY LIMITED',
		'cty_mst'        => '0106924989',
		'cty_dai_dien'   => 'Nguyễn Văn Kiên',
		'cty_tu_ngay'    => '05/08/2015',
		'cty_dia_chi'    => 'Thôn Mai Nội, Xã Sóc Sơn, Thành phố Hà Nội, Việt Nam',
		'cty_dien_thoai' => '0435961469',
		'cty_co_quan'    => 'Thuế cơ sở 18 thành phố Hà Nội',
		'cty_chi_nhanh'  => 'Đà Nẵng, Hải Phòng, Bình Dương, Thành phố Hồ Chí Minh, Sense City Hồ Chí Minh, Nha Trang',
	);
	$cd = wp_parse_args( get_option( 'dovere_settings', array() ), $mac );
	if ( null === $khoa ) {
		return $cd;
	}
	return isset( $cd[ $khoa ] ) && '' !== $cd[ $khoa ] ? $cd[ $khoa ] : $mac_dinh;
}

/** Ảnh mã QR chuyển khoản VietQR — chỉ chứa số tài khoản của mình, số tiền và mã đơn. */
function dvr_qr( $so_tien, $ma_don ) {
	$cd = dvr_cai_dat();
	if ( ! $cd['bank_id'] || ! $cd['bank_account'] ) {
		return '';
	}
	return add_query_arg(
		array(
			'amount'      => (int) round( $so_tien ),
			'addInfo'     => $ma_don,
			'accountName' => $cd['bank_name'],
		),
		'https://img.vietqr.io/image/' . rawurlencode( $cd['bank_id'] ) . '-' . rawurlencode( $cd['bank_account'] ) . '-compact2.png'
	);
}

/** #ABC hoặc #AABBCC -> array(r,g,b); không đọc được thì trả null. */
function dvr_mau_rgb( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9A-Fa-f]{6}$/', $hex ) ) {
		return null;
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/** Độ sáng tương đối theo chuẩn WCAG (0 tối – 1 sáng). */
function dvr_do_sang( $hex ) {
	$c = dvr_mau_rgb( $hex );
	if ( ! $c ) {
		return 1;
	}
	$l = array();
	foreach ( $c as $v ) {
		$v   = $v / 255;
		$l[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

/** Tỉ lệ tương phản giữa hai màu, 1 (trùng nhau) đến 21 (đen trên trắng). */
function dvr_tuong_phan( $a, $b ) {
	$x = dvr_do_sang( $a );
	$y = dvr_do_sang( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}

/**
 * Chữ nên đen hay trắng khi đặt trên nền màu này — chọn bên tương phản cao hơn.
 * Vàng, be, xanh lá nhạt đều rơi vào chữ đen; đỏ, xanh đậm rơi vào chữ trắng.
 */
function dvr_chu_tren_nen( $hex ) {
	$den = '#152242';
	return dvr_tuong_phan( $hex, $den ) >= dvr_tuong_phan( $hex, '#FFFFFF' ) ? $den : '#FFFFFF';
}

/** Trộn màu với trắng theo tỉ lệ $ty (0–1) để lấy sắc nhạt hơn. */
function dvr_lam_nhat( $hex, $ty = 0.25 ) {
	$c = dvr_mau_rgb( $hex );
	if ( ! $c ) {
		return $hex;
	}
	foreach ( $c as $i => $v ) {
		$c[ $i ] = (int) round( $v + ( 255 - $v ) * $ty );
	}
	return sprintf( '#%02X%02X%02X', $c[0], $c[1], $c[2] );
}

/** Định dạng tiền cho người Việt đọc. */
function dvr_tien( $n ) {
	return number_format( (float) $n, 0, ',', '.' ) . 'đ';
}
