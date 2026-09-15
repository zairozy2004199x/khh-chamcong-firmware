<?php
/**
 * Plugin Name:       Dò Vé Rẻ
 * Plugin URI:        https://khh.vn/
 * Description:       So giá vé máy bay theo chặng và ngày, mở song song các trang đang bán vé, điền sẵn hồ sơ hành khách và hoá đơn VAT, nhận đơn của khách và theo dõi tới lúc xuất vé.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            K&H
 * Text Domain:       do-ve-re
 *
 * @package do-ve-re
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DVR_VERSION', '1.0.0' );
define( 'DVR_DIR', plugin_dir_path( __FILE__ ) );
define( 'DVR_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------ *
 * Cấu hình
 * ------------------------------------------------------------------ */

function dvr_defaults() {
	return array(
		'brand_name'   => 'K&H COM.,LTD',
		'brand_tag'    => 'Đặt vé máy bay · giá tốt · xuất hoá đơn VAT',
		'brand_logo'   => '',
		'brand_color'  => '#B8860B',
		'bank_name'    => '',
		'bank_account' => '',
		'bank_owner'   => '',
		'fee_pct'      => 3,
		'fee_min'      => 50000,
		'hold_minutes' => 30,
	);
}

function dvr_opts() {
	return wp_parse_args( get_option( 'dvr_settings', array() ), dvr_defaults() );
}

function dvr_opt( $key ) {
	$o = dvr_opts();
	return isset( $o[ $key ] ) ? $o[ $key ] : '';
}

/** Ai được xem danh sách đơn và đổi trạng thái. */
function dvr_is_admin() {
	return current_user_can( 'manage_options' );
}

function dvr_table() {
	global $wpdb;
	return $wpdb->prefix . 'dvr_orders';
}

/* ------------------------------------------------------------------ *
 * Bảng đơn
 * ------------------------------------------------------------------ */

register_activation_hook( __FILE__, 'dvr_activate' );
function dvr_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = dvr_table();

	dbDelta(
		"CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(32) NOT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'cho_thanh_toan',
			payload LONGTEXT NOT NULL,
			total BIGINT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY status (status),
			KEY created_at (created_at)
		) $charset;"
	);

	if ( ! get_option( 'dvr_settings' ) ) {
		add_option( 'dvr_settings', dvr_defaults() );
	}
}

function dvr_row_to_order( $row ) {
	$o = json_decode( $row['payload'], true );
	if ( ! is_array( $o ) ) {
		$o = array();
	}
	$o['code']   = $row['code'];
	$o['status'] = $row['status'];
	return $o;
}

function dvr_get_order( $code ) {
	global $wpdb;
	$row = $wpdb->get_row( // phpcs:ignore
		$wpdb->prepare( 'SELECT * FROM ' . dvr_table() . ' WHERE code = %s', $code ), // phpcs:ignore
		ARRAY_A
	);
	return $row ? dvr_row_to_order( $row ) : null;
}

function dvr_put_order( $order ) {
	global $wpdb;
	$now   = current_time( 'mysql', true );
	$table = dvr_table();
	$exist = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE code = %s", $order['code'] ) ); // phpcs:ignore

	$data = array(
		'code'       => $order['code'],
		'status'     => isset( $order['status'] ) ? $order['status'] : 'cho_thanh_toan',
		'payload'    => wp_json_encode( $order ),
		'total'      => isset( $order['money']['total'] ) ? (int) $order['money']['total'] : 0,
		'updated_at' => $now,
	);

	if ( $exist ) {
		$wpdb->update( $table, $data, array( 'code' => $order['code'] ) ); // phpcs:ignore
	} else {
		$data['created_at'] = $now;
		$wpdb->insert( $table, $data ); // phpcs:ignore
	}
	return $order;
}

function dvr_new_code() {
	global $wpdb;
	$table = dvr_table();
	for ( $i = 0; $i < 20; $i++ ) {
		$code = 'DVR' . gmdate( 'ym' ) . wp_rand( 1000, 9999 );
		$hit  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE code = %s", $code ) ); // phpcs:ignore
		if ( ! $hit ) {
			return $code;
		}
	}
	return 'DVR' . gmdate( 'ymdHis' );
}

/* ------------------------------------------------------------------ *
 * REST
 * ------------------------------------------------------------------ */

add_action( 'rest_api_init', 'dvr_rest_routes' );
function dvr_rest_routes() {
	register_rest_route(
		'dvr/v1',
		'/orders',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'dvr_rest_list',
				'permission_callback' => 'dvr_is_admin',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'dvr_rest_create',
				'permission_callback' => '__return_true',
			),
		)
	);
	register_rest_route(
		'dvr/v1',
		'/orders/(?P<code>[A-Za-z0-9]{4,32})',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'dvr_rest_get',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'dvr_rest_update',
				'permission_callback' => 'dvr_is_admin',
			),
		)
	);
}

/** Khách chỉ được thấy phần của đơn mình, không thấy giá mua vào. */
function dvr_public_view( $order ) {
	if ( dvr_is_admin() ) {
		return $order;
	}
	if ( isset( $order['money']['cost'] ) ) {
		unset( $order['money']['cost'] );
	}
	unset( $order['log'] );
	return $order;
}

function dvr_rest_list() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . dvr_table() . ' ORDER BY created_at DESC LIMIT 500', ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$out[] = dvr_row_to_order( $row );
	}
	return rest_ensure_response( array( 'orders' => $out ) );
}

function dvr_rest_get( $request ) {
	$order = dvr_get_order( (string) $request['code'] );
	if ( ! $order ) {
		return new WP_Error( 'dvr_not_found', 'Không tìm thấy đơn này.', array( 'status' => 404 ) );
	}
	return rest_ensure_response( array( 'order' => dvr_public_view( $order ) ) );
}

function dvr_rest_create( $request ) {
	$in = $request->get_json_params();
	if ( ! is_array( $in ) ) {
		return new WP_Error( 'dvr_bad_request', 'Nội dung đơn không hợp lệ.', array( 'status' => 400 ) );
	}

	$pax = isset( $in['pax'] ) && is_array( $in['pax'] ) ? $in['pax'] : array();
	if ( ! $pax ) {
		return new WP_Error( 'dvr_bad_request', 'Đơn chưa có hành khách nào.', array( 'status' => 400 ) );
	}
	if ( count( $pax ) > 9 ) {
		return new WP_Error( 'dvr_bad_request', 'Mỗi đơn tối đa 9 khách.', array( 'status' => 400 ) );
	}

	$email = isset( $in['contact']['email'] ) ? sanitize_email( $in['contact']['email'] ) : '';
	$phone = isset( $in['contact']['phone'] ) ? preg_replace( '/\D/', '', $in['contact']['phone'] ) : '';
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'dvr_bad_request', 'Email chưa đúng.', array( 'status' => 400 ) );
	}
	if ( ! preg_match( '/^0\d{9}$/', $phone ) ) {
		return new WP_Error( 'dvr_bad_request', 'Số điện thoại phải là 10 số bắt đầu bằng 0.', array( 'status' => 400 ) );
	}

	$o    = dvr_opts();
	$fare = isset( $in['money']['fare'] ) ? max( 0, (int) $in['money']['fare'] ) : 0;
	$fee  = max( (int) round( $fare * (float) $o['fee_pct'] / 100 ), (int) $o['fee_min'] );

	$clean = array();
	foreach ( $pax as $p ) {
		$clean[] = array(
			'full' => sanitize_text_field( isset( $p['full'] ) ? $p['full'] : '' ),
			'dob'  => sanitize_text_field( isset( $p['dob'] ) ? $p['dob'] : '' ),
			'idNo' => sanitize_text_field( isset( $p['idNo'] ) ? $p['idNo'] : '' ),
		);
	}

	$flight = isset( $in['flight'] ) && is_array( $in['flight'] ) ? $in['flight'] : array();
	$keep   = array( 'route', 'date', 'airline', 'hang', 'number', 'dep', 'arr', 'stops', 'bag', 'cabin', 'adt', 'chd', 'inf', 'moiKhach' );
	$fl     = array();
	foreach ( $keep as $k ) {
		if ( isset( $flight[ $k ] ) ) {
			$fl[ $k ] = is_scalar( $flight[ $k ] ) ? sanitize_text_field( (string) $flight[ $k ] ) : '';
		}
	}
	$fl['fare'] = $fare;

	$now   = gmdate( 'c' );
	$order = array(
		'code'      => dvr_new_code(),
		'status'    => 'cho_thanh_toan',
		'createdAt' => $now,
		'expiresAt' => gmdate( 'c', time() + (int) $o['hold_minutes'] * 60 ),
		'flight'    => $fl,
		'pax'       => $clean,
		'contact'   => array(
			'name'  => sanitize_text_field( isset( $in['contact']['name'] ) ? $in['contact']['name'] : '' ),
			'phone' => $phone,
			'email' => $email,
		),
		'invoice'   => array(
			'tax'     => sanitize_text_field( isset( $in['invoice']['tax'] ) ? $in['invoice']['tax'] : '' ),
			'company' => sanitize_text_field( isset( $in['invoice']['company'] ) ? $in['invoice']['company'] : '' ),
		),
		'money'     => array(
			'fare'  => $fare,
			'fee'   => $fee,
			'total' => $fare + $fee,
			'paid'  => 0,
			'cost'  => 0,
		),
		'pnr'       => '',
		'log'       => array(
			array(
				'at'   => $now,
				'what' => 'Khách tạo đơn',
			),
		),
	);

	dvr_put_order( $order );
	do_action( 'dvr_order_created', $order );

	return rest_ensure_response( array( 'order' => dvr_public_view( $order ) ) );
}

function dvr_rest_update( $request ) {
	$order = dvr_get_order( (string) $request['code'] );
	if ( ! $order ) {
		return new WP_Error( 'dvr_not_found', 'Không tìm thấy đơn này.', array( 'status' => 404 ) );
	}
	$in    = $request->get_json_params();
	$patch = isset( $in['patch'] ) && is_array( $in['patch'] ) ? $in['patch'] : array();
	$note  = isset( $in['note'] ) ? sanitize_text_field( $in['note'] ) : 'Cập nhật';

	$states = array( 'cho_thanh_toan', 'da_nhan_tien', 'da_xuat_ve', 'hoan_tien', 'huy' );
	if ( isset( $patch['status'] ) && in_array( $patch['status'], $states, true ) ) {
		$order['status'] = $patch['status'];
	}
	if ( isset( $patch['pnr'] ) ) {
		$order['pnr'] = strtoupper( sanitize_text_field( $patch['pnr'] ) );
	}
	if ( isset( $patch['money'] ) && is_array( $patch['money'] ) ) {
		foreach ( array( 'paid', 'cost' ) as $k ) {
			if ( isset( $patch['money'][ $k ] ) ) {
				$order['money'][ $k ] = max( 0, (int) $patch['money'][ $k ] );
			}
		}
	}
	$order['log']   = array_merge(
		isset( $order['log'] ) ? (array) $order['log'] : array(),
		array(
			array(
				'at'   => gmdate( 'c' ),
				'what' => $note,
				'by'   => wp_get_current_user()->display_name,
			),
		)
	);
	$order['updatedAt'] = gmdate( 'c' );

	dvr_put_order( $order );
	do_action( 'dvr_order_updated', $order );

	return rest_ensure_response( array( 'order' => $order ) );
}

/* ------------------------------------------------------------------ *
 * Hiển thị trang
 * ------------------------------------------------------------------ */

function dvr_brand_bar() {
	$o    = dvr_opts();
	$logo = $o['brand_logo'];
	ob_start();
	?>
	<div class="brand">
		<?php if ( $logo ) : ?>
			<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $o['brand_name'] ); ?>">
		<?php endif; ?>
		<div>
			<div class="bname"><?php echo esc_html( $o['brand_name'] ); ?></div>
			<div class="bsub"><?php echo esc_html( $o['brand_tag'] ); ?></div>
		</div>
		<span class="bspace"></span>
		<nav class="bnav">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang chủ</a>
			<?php if ( dvr_is_admin() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=do-ve-re' ) ); ?>">Quản trị</a>
			<?php endif; ?>
		</nav>
	</div>
	<?php
	return ob_get_clean();
}

function dvr_assets() {
	$o = dvr_opts();
	wp_enqueue_style( 'dvr-fonts', 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap', array(), null ); // phpcs:ignore
	wp_enqueue_style( 'dvr', DVR_URL . 'assets/dvr.css', array( 'dvr-fonts' ), DVR_VERSION );

	$color = preg_match( '/^#[0-9A-Fa-f]{6}$/', $o['brand_color'] ) ? $o['brand_color'] : '#B8860B';
	wp_add_inline_style( 'dvr', ':root{--accent:' . $color . '}' );

	wp_register_script( 'dvr', DVR_URL . 'assets/dvr.js', array(), DVR_VERSION, true );
	wp_localize_script(
		'dvr',
		'DVR_CFG',
		array(
			'rest'        => esc_url_raw( rest_url( 'dvr/v1/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'admin'       => dvr_is_admin() ? 1 : 0,
			'bankName'    => $o['bank_name'],
			'bankAccount' => $o['bank_account'],
			'bankOwner'   => $o['bank_owner'],
			'feePct'      => (float) $o['fee_pct'],
			'feeMin'      => (int) $o['fee_min'],
			'holdMinutes' => (int) $o['hold_minutes'],
		)
	);
	wp_enqueue_script( 'dvr' );
}

add_shortcode( 'do_ve_re', 'dvr_shortcode' );
function dvr_shortcode() {
	dvr_assets();
	ob_start();
	include DVR_DIR . 'page.php';
	return ob_get_clean();
}

/** Trang toàn màn hình: /?dvr=1 */
add_action( 'template_redirect', 'dvr_maybe_render' );
function dvr_maybe_render() {
	if ( ! isset( $_GET['dvr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	dvr_assets();
	status_header( 200 );
	nocache_headers();
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?php echo esc_html( dvr_opt( 'brand_name' ) ); ?> · Dò vé rẻ</title>
	<?php wp_print_styles(); ?>
</head>
<body class="dvr-full">
	<?php include DVR_DIR . 'page.php'; ?>
	<?php wp_print_footer_scripts(); ?>
</body>
</html>
	<?php
	exit;
}

/* ------------------------------------------------------------------ *
 * Trang quản trị
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'dvr_menu' );
function dvr_menu() {
	add_menu_page( 'Dò Vé Rẻ', 'Dò Vé Rẻ', 'manage_options', 'do-ve-re', 'dvr_admin_page', 'dashicons-tickets-alt', 31 );
	add_submenu_page( 'do-ve-re', 'Đơn hàng', 'Đơn hàng', 'manage_options', 'do-ve-re', 'dvr_admin_page' );
	add_submenu_page( 'do-ve-re', 'Cài đặt', 'Cài đặt', 'manage_options', 'dvr-settings', 'dvr_settings_page' );
}

function dvr_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . dvr_table() . ' ORDER BY created_at DESC LIMIT 200', ARRAY_A ); // phpcs:ignore
	$page = home_url( '/?dvr=1' );
	$ten  = array(
		'cho_thanh_toan' => 'Chờ khách chuyển khoản',
		'da_nhan_tien'   => 'Đã nhận tiền, chờ mua vé',
		'da_xuat_ve'     => 'Đã xuất vé',
		'hoan_tien'      => 'Đã hoàn tiền',
		'huy'            => 'Đã huỷ',
	);
	?>
	<div class="wrap">
		<h1>Dò Vé Rẻ — Đơn hàng</h1>
		<p>Màn khách dùng: <a href="<?php echo esc_url( $page ); ?>" target="_blank"><?php echo esc_html( $page ); ?></a>
			· hoặc chèn shortcode <code>[do_ve_re]</code> vào một trang bất kỳ.</p>
		<p>Đổi trạng thái đơn ngay trên màn đó, ở tab <b>Đơn hàng</b> (chỉ quản trị viên thấy tab này).</p>

		<table class="widefat striped">
			<thead><tr><th>Đơn</th><th>Chuyến</th><th>Khách</th><th>Liên hệ</th><th>Tiền</th><th>Trạng thái</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">Chưa có đơn nào.</td></tr>
			<?php endif; ?>
			<?php foreach ( (array) $rows as $row ) : ?>
				<?php $o = dvr_row_to_order( $row ); ?>
				<tr>
					<td><strong><?php echo esc_html( $o['code'] ); ?></strong>
						<div class="description"><?php echo esc_html( $row['created_at'] ); ?></div>
						<?php if ( ! empty( $o['pnr'] ) ) : ?>
							<div><code><?php echo esc_html( $o['pnr'] ); ?></code></div>
						<?php endif; ?></td>
					<td><?php echo esc_html( isset( $o['flight']['route'] ) ? $o['flight']['route'] : '—' ); ?>
						<div class="description">
							<?php echo esc_html( trim( ( isset( $o['flight']['airline'] ) ? $o['flight']['airline'] : '' ) . ' ' . ( isset( $o['flight']['number'] ) ? $o['flight']['number'] : '' ) ) ); ?>
							· <?php echo esc_html( isset( $o['flight']['date'] ) ? $o['flight']['date'] : '' ); ?>
						</div></td>
					<td><?php echo esc_html( implode( ', ', wp_list_pluck( (array) $o['pax'], 'full' ) ) ); ?></td>
					<td><?php echo esc_html( isset( $o['contact']['phone'] ) ? $o['contact']['phone'] : '' ); ?>
						<div class="description"><?php echo esc_html( isset( $o['contact']['email'] ) ? $o['contact']['email'] : '' ); ?></div></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['total'] ) ); ?>đ</td>
					<td><?php echo esc_html( isset( $ten[ $row['status'] ] ) ? $ten[ $row['status'] ] : $row['status'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function dvr_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Không đủ quyền.' );
	}
	$saved = false;
	if ( isset( $_POST['dvr_save'] ) && check_admin_referer( 'dvr_settings' ) ) {
		$in  = wp_unslash( $_POST ); // phpcs:ignore
		$new = array(
			'brand_name'   => sanitize_text_field( $in['brand_name'] ),
			'brand_tag'    => sanitize_text_field( $in['brand_tag'] ),
			'brand_logo'   => esc_url_raw( $in['brand_logo'] ),
			'brand_color'  => preg_match( '/^#[0-9A-Fa-f]{6}$/', $in['brand_color'] ) ? $in['brand_color'] : '#B8860B',
			'bank_name'    => sanitize_text_field( $in['bank_name'] ),
			'bank_account' => sanitize_text_field( $in['bank_account'] ),
			'bank_owner'   => sanitize_text_field( $in['bank_owner'] ),
			'fee_pct'      => max( 0, min( 50, (float) $in['fee_pct'] ) ),
			'fee_min'      => max( 0, (int) $in['fee_min'] ),
			'hold_minutes' => max( 5, min( 1440, (int) $in['hold_minutes'] ) ),
		);
		update_option( 'dvr_settings', $new );
		$saved = true;
	}
	$o = dvr_opts();
	?>
	<div class="wrap">
		<h1>Dò Vé Rẻ — Cài đặt</h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success"><p>Đã lưu.</p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'dvr_settings' ); ?>
			<h2>Nhận diện</h2>
			<table class="form-table">
				<tr><th><label for="brand_name">Tên hiển thị</label></th>
					<td><input id="brand_name" name="brand_name" class="regular-text" value="<?php echo esc_attr( $o['brand_name'] ); ?>"></td></tr>
				<tr><th><label for="brand_tag">Dòng mô tả</label></th>
					<td><input id="brand_tag" name="brand_tag" class="large-text" value="<?php echo esc_attr( $o['brand_tag'] ); ?>"></td></tr>
				<tr><th><label for="brand_logo">Ảnh logo</label></th>
					<td><input id="brand_logo" name="brand_logo" class="large-text" value="<?php echo esc_attr( $o['brand_logo'] ); ?>" placeholder="https://…/logo.png">
						<p class="description">Tải logo lên <b>Thư viện</b> của WordPress rồi dán đường dẫn ảnh vào đây. Để trống thì chỉ hiện tên.</p></td></tr>
				<tr><th><label for="brand_color">Màu thương hiệu</label></th>
					<td><input id="brand_color" name="brand_color" type="color" value="<?php echo esc_attr( $o['brand_color'] ); ?>">
						<p class="description">Màu này dùng cho nút bấm, giá tiền và các điểm nhấn. Mặc định là vàng K&amp;H.</p></td></tr>
			</table>

			<h2>Tài khoản nhận tiền</h2>
			<p class="description">Hiện cho khách sau khi họ tạo đơn. Để trống thì màn trả kết quả không có số tài khoản.</p>
			<table class="form-table">
				<tr><th><label for="bank_name">Ngân hàng</label></th>
					<td><input id="bank_name" name="bank_name" class="regular-text" value="<?php echo esc_attr( $o['bank_name'] ); ?>" placeholder="Vietcombank"></td></tr>
				<tr><th><label for="bank_account">Số tài khoản</label></th>
					<td><input id="bank_account" name="bank_account" class="regular-text" value="<?php echo esc_attr( $o['bank_account'] ); ?>"></td></tr>
				<tr><th><label for="bank_owner">Chủ tài khoản</label></th>
					<td><input id="bank_owner" name="bank_owner" class="regular-text" value="<?php echo esc_attr( $o['bank_owner'] ); ?>"></td></tr>
			</table>

			<h2>Phí dịch vụ &amp; giữ giá</h2>
			<table class="form-table">
				<tr><th><label for="fee_pct">Phí theo %</label></th>
					<td><input id="fee_pct" name="fee_pct" type="number" step="0.1" min="0" max="50" value="<?php echo esc_attr( $o['fee_pct'] ); ?>"> %</td></tr>
				<tr><th><label for="fee_min">Phí tối thiểu</label></th>
					<td><input id="fee_min" name="fee_min" type="number" min="0" step="1000" value="<?php echo esc_attr( $o['fee_min'] ); ?>"> đ</td></tr>
				<tr><th><label for="hold_minutes">Giữ giá</label></th>
					<td><input id="hold_minutes" name="hold_minutes" type="number" min="5" max="1440" value="<?php echo esc_attr( $o['hold_minutes'] ); ?>"> phút</td></tr>
			</table>

			<h2>Nguồn giá</h2>
			<div class="notice notice-warning inline" style="margin:10px 0;padding:10px 12px">
				<p style="margin:0"><b>Bảng giá đang là giá mô phỏng.</b> Máy tự tính theo cự ly, số ngày còn lại tới ngày bay,
				thứ trong tuần và giờ cất cánh — <b>không phải giá thật của hãng</b>, nên không khớp Trip.com hay Traveloka.
				Muốn giá thật thì phải nối API bán vé (Amadeus, hoặc một đại lý có API); phần đó chưa có trong bản này.</p>
			</div>

			<p><button class="button button-primary" name="dvr_save" value="1">Lưu cài đặt</button></p>
		</form>
	</div>
	<?php
}
