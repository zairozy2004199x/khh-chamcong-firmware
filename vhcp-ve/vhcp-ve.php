<?php
/**
 * Plugin Name:       POSH · Bán vé (Zalo Mini App)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Bán vé/dịch vụ khu vui chơi trả trước qua Zalo Mini App. Quản lý dịch vụ (ảnh/giá/mô tả), nhận đơn từ Zalo, dựng VietQR. ĐỘC LẬP với plugin ghế massage.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ⚠️ Repo CÔNG KHAI: KHÔNG hardcode số tài khoản/khoá vào mã. Số TK nhận tiền khai trong trang
 *    admin của plugin này (hoặc tự lấy lại từ plugin ghế nếu đã khai ở đó). Số TK vốn công khai
 *    trên QR nên không phải bí mật; nhưng vẫn để ở cấu hình, không nhét chuỗi cụ thể vào source.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class POSH_Ve {

	const NS      = 'posh/v1';
	const VER_TBL = '1';

	const VE_MAC_DINH = array(
		array( 'id' => 1, 'ten' => 'Vé vào cửa', 'gia' => 50000,  'mo_ta' => 'Vé vào cửa 1 người', 'anh' => '', 'thoi_luong' => '', 'hien' => 1 ),
		array( 'id' => 2, 'ten' => 'Vé 1 giờ',   'gia' => 80000,  'mo_ta' => 'Chơi trong 1 giờ',    'anh' => '', 'thoi_luong' => '60 phút', 'hien' => 1 ),
		array( 'id' => 3, 'ten' => 'Vé cả ngày', 'gia' => 150000, 'mo_ta' => 'Chơi không giới hạn trong ngày', 'anh' => '', 'thoi_luong' => 'Cả ngày', 'hien' => 1 ),
	);

	/* Bảng BIN ngân hàng (đủ cho hiển thị tên). Không có thì trả rỗng — thà "không rõ" còn hơn đoán. */
	const NGAN_HANG = array(
		'970418' => 'BIDV', '970436' => 'Vietcombank', '970415' => 'VietinBank', '970422' => 'MB',
		'970407' => 'Techcombank', '970416' => 'ACB', '970448' => 'OCB', '970432' => 'VPBank',
		'970405' => 'Agribank', '970423' => 'TPBank', '970443' => 'SHB', '970441' => 'VIB',
		'970426' => 'MSB', '970403' => 'Sacombank', '970431' => 'Eximbank', '970437' => 'HDBank',
	);

	public static function init() {
		self::bao_dam_bang();
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	// ───────────────────────────── VietQR (tự chứa) ─────────────────────────────
	private static function tlv( $ma, $v ) { $v = (string) $v; return $ma . substr( '0' . strlen( $v ), -2 ) . $v; }
	private static function crc16( $s ) {
		$crc = 0xFFFF; $n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) { $crc ^= ( ord( $s[ $i ] ) & 0xFF ) << 8;
			for ( $b = 0; $b < 8; $b++ ) { $crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 ); $crc &= 0xFFFF; } }
		return substr( '000' . strtoupper( dechex( $crc ) ), -4 );
	}
	public static function vietqr( $bin, $so_tk, $so_tien, $noi_dung ) {
		$s  = self::tlv( '00', '01' ) . self::tlv( '01', $so_tien ? '12' : '11' );
		$ben = self::tlv( '00', (string) $bin ) . self::tlv( '01', (string) $so_tk );
		$s .= self::tlv( '38', self::tlv( '00', 'A000000727' ) . self::tlv( '01', $ben ) . self::tlv( '02', 'QRIBFTTA' ) );
		$s .= self::tlv( '53', '704' );
		if ( $so_tien ) { $s .= self::tlv( '54', (string) (int) $so_tien ); }
		$s .= self::tlv( '58', 'VN' );
		if ( '' !== (string) $noi_dung ) { $s .= self::tlv( '62', self::tlv( '08', (string) $noi_dung ) ); }
		$s .= '6304'; return $s . self::crc16( $s );
	}
	private static function ten_nh( $bin ) {
		$bin = preg_replace( '/\D+/', '', (string) $bin );
		return isset( self::NGAN_HANG[ $bin ] ) ? self::NGAN_HANG[ $bin ] : '';
	}

	/** TK nhận tiền: ưu tiên cấu hình RIÊNG của plugin vé; trống thì lấy lại từ plugin ghế (nếu có). */
	private static function bank() {
		$bin = trim( (string) get_option( 'pve_bin', '' ) );
		$tk  = trim( (string) get_option( 'pve_so_tk', '' ) );
		$ten = trim( (string) get_option( 'pve_ten_tk', '' ) );
		if ( '' === $bin ) { $bin = trim( (string) get_option( 'vhg_bin', '' ) ); }
		if ( '' === $tk )  { $tk  = trim( (string) get_option( 'vhg_so_tk', '' ) ); }
		if ( '' === $ten ) { $ten = trim( (string) get_option( 'vhg_ten_tk', '' ) ); }
		return array( 'bin' => $bin, 'so_tk' => $tk, 'ten_tk' => $ten, 'ten_nh' => self::ten_nh( $bin ) );
	}

	// ───────────────────────────── Danh mục dịch vụ ─────────────────────────────
	private static function chuan_hoa( $v, $auto_id ) {
		return array(
			'id'         => (int) ( ! empty( $v['id'] ) ? $v['id'] : $auto_id ),
			'ten'        => trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ),
			'gia'        => (int) ( isset( $v['gia'] ) ? $v['gia'] : 0 ),
			'mo_ta'      => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ),
			'anh'        => esc_url_raw( (string) ( isset( $v['anh'] ) ? $v['anh'] : '' ) ),
			'thoi_luong' => trim( (string) ( isset( $v['thoi_luong'] ) ? $v['thoi_luong'] : '' ) ),
			'hien'       => empty( $v['hien'] ) ? 0 : 1,
		);
	}
	public static function ds_tatca() {
		$ds = get_option( 'pve_dichvu' );
		if ( ! is_array( $ds ) || ! $ds ) { return self::VE_MAC_DINH; }
		$ra = array(); $auto = 1;
		foreach ( $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		return $ra ? $ra : self::VE_MAC_DINH;
	}
	private static function ds() { $r = array(); foreach ( self::ds_tatca() as $v ) { if ( $v['hien'] ) { $r[] = $v; } } return $r; }
	private static function theo_id( $id ) { foreach ( self::ds() as $v ) { if ( (int) $v['id'] === (int) $id ) { return $v; } } return null; }
	private static function luu_ds( $ds ) {
		$ra = array(); $auto = 1;
		foreach ( (array) $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		update_option( 'pve_dichvu', array_values( $ra ) );
	}

	// ───────────────────────────── Bảng vé ─────────────────────────────
	public static function tbl() { global $wpdb; return $wpdb->prefix . 'pve_ve'; }
	public static function bao_dam_bang() {
		if ( get_option( 'pve_tbl' ) === self::VER_TBL ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tbl = self::tbl(); $col = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $tbl (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ma_ve VARCHAR(24) NOT NULL,
			dv_ten VARCHAR(120) NOT NULL DEFAULT '',
			so_tien INT NOT NULL DEFAULT 0,
			ten_khach VARCHAR(80) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			noi_dung VARCHAR(40) NOT NULL DEFAULT '',
			trang_thai VARCHAR(12) NOT NULL DEFAULT 'cho',
			tao_luc DATETIME NOT NULL,
			tt_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY ma_ve (ma_ve), KEY trang_thai (trang_thai)
		) $col;" );
		update_option( 'pve_tbl', self::VER_TBL );
	}

	// ───────────────────────────── REST ─────────────────────────────
	public static function dang_ky() {
		register_rest_route( self::NS, '/ve/goi', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_goi' ) ) );
		register_rest_route( self::NS, '/ve/dat', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_dat' ) ) );
		register_rest_route( self::NS, '/ve/trangthai', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_trangthai' ) ) );
	}
	public static function cors( $served, $result, $request, $server ) {
		if ( $request && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/ve' ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
		}
		return $served;
	}
	public static function r_goi() {
		$goi = array();
		foreach ( self::ds() as $g ) {
			$goi[] = array( 'ma' => (int) $g['id'], 'ten' => $g['ten'], 'tien' => (int) $g['gia'],
				'mo_ta' => (string) $g['mo_ta'], 'anh' => (string) $g['anh'], 'thoi_luong' => (string) $g['thoi_luong'] );
		}
		$b = self::bank();
		return array( 'ok' => true, 'goi' => $goi, 'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	public static function r_dat( $req ) {
		$id  = (int) $req->get_param( 'id' );
		$ten = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		$goi = self::theo_id( $id );
		if ( ! $goi ) {                                  // tương thích app cũ gửi `tien`
			$t = (int) $req->get_param( 'tien' );
			if ( $t > 0 ) { foreach ( self::ds() as $v ) { if ( (int) $v['gia'] === $t ) { $goi = $v; break; } } }
		}
		if ( ! $goi ) { return new WP_Error( 'goi', 'Vé không hợp lệ.', array( 'status' => 400 ) ); }
		if ( '' === $ten || '' === $sdt ) { return new WP_Error( 'thieu', 'Cần tên và số điện thoại.', array( 'status' => 400 ) ); }
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		if ( ! self::nhip_ok() ) { return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) ); }

		global $wpdb;
		$ma_ve = self::ma_ve_moi(); $tien = (int) $goi['gia']; $noidung = 'VE' . $ma_ve;
		$wpdb->insert( self::tbl(), array(
			'ma_ve' => $ma_ve, 'dv_ten' => $goi['ten'], 'so_tien' => $tien,
			'ten_khach' => mb_substr( $ten, 0, 80 ), 'sdt' => mb_substr( $sdt, 0, 20 ),
			'noi_dung' => $noidung, 'trang_thai' => 'cho', 'tao_luc' => current_time( 'mysql' ),
		) );
		$qr = self::vietqr( $b['bin'], $b['so_tk'], $tien, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tien, 'goi_ten' => $goi['ten'],
			'noi_dung' => $noidung, 'qr' => $qr, 'trang_thai' => 'cho',
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	public static function r_trangthai( $req ) {
		global $wpdb;
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT ma_ve, dv_ten, so_tien, trang_thai, tao_luc, tt_luc FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		return array( 'ok' => true, 'ma_ve' => $r['ma_ve'], 'goi_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
			'trang_thai' => $r['trang_thai'], 'tao_luc' => $r['tao_luc'], 'tt_luc' => $r['tt_luc'] );
	}

	private static function ma_ve_moi() {
		global $wpdb; $bang = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$s = ''; for ( $i = 0; $i < 8; $i++ ) { $s .= $bang[ random_int( 0, strlen( $bang ) - 1 ) ]; }
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE ma_ve=%s', $s ) ) ) { return $s; }
		}
		return 'V' . substr( (string) time(), -7 );
	}
	private static function nhip_ok() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : 'x';
		$k  = 'pve_nhip_' . md5( $ip );
		if ( get_transient( $k ) ) { return false; }
		set_transient( $k, 1, 3 ); return true;
	}

	// ───────────────────────────── Admin (menu top-level riêng) ─────────────────────────────
	public static function admin_menu() {
		add_menu_page( 'Vé khu vui chơi', 'Vé khu vui chơi', 'manage_options', 'posh-ve',
			array( __CLASS__, 'trang_admin' ), 'dashicons-tickets-alt', 27 );
	}
	public static function trang_admin() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb; wp_enqueue_media();

		if ( isset( $_POST['pve_bank'] ) && check_admin_referer( 'pve_bank' ) ) {
			update_option( 'pve_bin', preg_replace( '/\D+/', '', (string) $_POST['bin'] ) );
			update_option( 'pve_so_tk', sanitize_text_field( wp_unslash( $_POST['so_tk'] ) ) );
			update_option( 'pve_ten_tk', sanitize_text_field( wp_unslash( $_POST['ten_tk'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu tài khoản.</p></div>';
		}
		if ( isset( $_POST['pve_luu'] ) && check_admin_referer( 'pve_luu' ) ) {
			$id = (int) $_POST['id'];
			$moi = array( 'id' => $id, 'ten' => sanitize_text_field( wp_unslash( $_POST['ten'] ) ),
				'gia' => (int) preg_replace( '/\D+/', '', (string) $_POST['gia'] ),
				'mo_ta' => sanitize_textarea_field( wp_unslash( $_POST['mo_ta'] ) ),
				'anh' => esc_url_raw( wp_unslash( $_POST['anh'] ) ),
				'thoi_luong' => sanitize_text_field( wp_unslash( $_POST['thoi_luong'] ) ),
				'hien' => empty( $_POST['hien'] ) ? 0 : 1 );
			$ds = self::ds_tatca(); $thay = false;
			if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
			if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }
			self::luu_ds( $ds );
			echo '<div class="notice notice-success"><p>Đã lưu dịch vụ.</p></div>';
		}
		if ( isset( $_POST['pve_xoa'] ) && check_admin_referer( 'pve_xoa' ) ) {
			$id = (int) $_POST['id'];
			self::luu_ds( array_filter( self::ds_tatca(), function ( $v ) use ( $id ) { return (int) $v['id'] !== $id; } ) );
			echo '<div class="notice notice-success"><p>Đã xoá dịch vụ.</p></div>';
		}
		if ( isset( $_POST['pve_tt'] ) && check_admin_referer( 'pve_tt' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $_POST['ma_ve'] ) );
			$tt = 'huy' === $_POST['pve_tt'] ? 'huy' : 'da_tt';
			$wpdb->update( self::tbl(), array( 'trang_thai' => $tt, 'tt_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
			echo '<div class="notice notice-success"><p>Vé ' . esc_html( $ma ) . ' → ' . esc_html( $tt ) . '.</p></div>';
		}

		$b = self::bank(); $ds = self::ds_tatca();
		$url = esc_url( home_url( '/wp-json/' . self::NS . '/ve/goi' ) );
		$sua = null; $sid = isset( $_GET['sua'] ) ? (int) $_GET['sua'] : 0;
		if ( $sid ) { foreach ( $ds as $v ) { if ( (int) $v['id'] === $sid ) { $sua = $v; break; } } }

		echo '<div class="wrap"><h1>Vé khu vui chơi</h1>';
		echo '<p>API cho Zalo Mini App: <code>' . $url . '</code></p>';

		/* Tài khoản nhận tiền */
		echo '<h2>Tài khoản nhận tiền</h2><form method="post"><table class="form-table">';
		wp_nonce_field( 'pve_bank' );
		echo '<tr><th>Số tài khoản</th><td><input name="so_tk" class="regular-text code" value="' . esc_attr( get_option( 'pve_so_tk', '' ) ) . '"></td></tr>';
		echo '<tr><th>BIN ngân hàng</th><td><input name="bin" class="regular-text code" value="' . esc_attr( get_option( 'pve_bin', '' ) ) . '" placeholder="VD 970415 = VietinBank"></td></tr>';
		echo '<tr><th>Chủ tài khoản</th><td><input name="ten_tk" class="regular-text" value="' . esc_attr( get_option( 'pve_ten_tk', '' ) ) . '"></td></tr>';
		echo '</table><p class="description">Để trống cả 3 ô = tự dùng lại tài khoản đã khai ở plugin ghế. Đang dùng: <b>'
			. ( $b['so_tk'] ? esc_html( $b['so_tk'] . ' · ' . $b['ten_nh'] . ' · ' . $b['ten_tk'] ) : 'CHƯA CÓ — khách sẽ không tạo được QR' ) . '</b></p>';
		echo '<p><button class="button button-primary" name="pve_bank" value="1">Lưu tài khoản</button></p></form><hr>';

		/* Bảng dịch vụ */
		echo '<h2>Danh sách dịch vụ</h2><table class="widefat striped"><thead><tr><th style="width:70px">Ảnh</th>'
			. '<th>Tên</th><th>Giá</th><th>Thời lượng</th><th>Hiện</th><th>Thao tác</th></tr></thead><tbody>';
		foreach ( $ds as $v ) {
			echo '<tr><td>' . ( $v['anh'] ? '<img src="' . esc_url( $v['anh'] ) . '" style="width:56px;height:56px;object-fit:cover;border-radius:8px">' : '—' ) . '</td>'
				. '<td><b>' . esc_html( $v['ten'] ) . '</b>' . ( $v['mo_ta'] !== '' ? '<br><small>' . esc_html( $v['mo_ta'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( number_format( $v['gia'], 0, ',', '.' ) ) . 'đ</td>'
				. '<td>' . esc_html( $v['thoi_luong'] ) . '</td><td>' . ( $v['hien'] ? '✅' : '⛔' ) . '</td><td>'
				. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve&sua=' . $v['id'] ) ) . '">Sửa</a> '
				. '<form method="post" style="display:inline" onsubmit="return confirm(\'Xoá?\')">';
			wp_nonce_field( 'pve_xoa' );
			echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '"><button class="button" name="pve_xoa" value="1">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* Form thêm/sửa */
		echo '<h2>' . ( $sua ? 'Sửa dịch vụ' : 'Thêm dịch vụ' ) . '</h2><form method="post">';
		wp_nonce_field( 'pve_luu' );
		echo '<input type="hidden" name="id" value="' . (int) ( $sua ? $sua['id'] : 0 ) . '"><table class="form-table">';
		echo '<tr><th>Tên</th><td><input name="ten" class="regular-text" required value="' . esc_attr( $sua ? $sua['ten'] : '' ) . '"></td></tr>';
		echo '<tr><th>Giá (đ)</th><td><input name="gia" type="number" min="1000" step="1000" required value="' . esc_attr( $sua ? $sua['gia'] : '' ) . '"></td></tr>';
		echo '<tr><th>Thời lượng</th><td><input name="thoi_luong" class="regular-text" placeholder="VD 60 phút / Cả ngày" value="' . esc_attr( $sua ? $sua['thoi_luong'] : '' ) . '"></td></tr>';
		echo '<tr><th>Mô tả</th><td><textarea name="mo_ta" rows="3" class="large-text">' . esc_textarea( $sua ? $sua['mo_ta'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th>Ảnh</th><td><input type="text" name="anh" id="pve-anh" class="large-text code" value="' . esc_attr( $sua ? $sua['anh'] : '' ) . '"><br>'
			. '<button type="button" class="button" id="pve-chon" style="margin-top:6px">Chọn ảnh từ thư viện</button> '
			. '<img id="pve-xem" src="' . esc_url( $sua ? $sua['anh'] : '' ) . '" style="' . ( $sua && $sua['anh'] ? '' : 'display:none;' ) . 'height:80px;border-radius:8px;margin-left:10px;vertical-align:middle"></td></tr>';
		echo '<tr><th>Hiện</th><td><label><input type="checkbox" name="hien" value="1" ' . checked( $sua ? $sua['hien'] : 1, 1, false ) . '> Cho hiện trên app</label></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_luu" value="1">' . ( $sua ? 'Lưu thay đổi' : 'Thêm dịch vụ' ) . '</button>'
			. ( $sua ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Huỷ</a>' : '' ) . '</p></form>';
		echo '<script>jQuery(function($){var f;$("#pve-chon").on("click",function(e){e.preventDefault();'
			. 'if(f){f.open();return;}f=wp.media({title:"Chọn ảnh",multiple:false,library:{type:"image"}});'
			. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#pve-anh").val(a.url);$("#pve-xem").attr("src",a.url).show();});f.open();});});</script>';

		/* Vé đã đặt */
		$rows = $wpdb->get_results( 'SELECT ma_ve, dv_ten, so_tien, ten_khach, sdt, trang_thai, tao_luc FROM ' . self::tbl() . ' ORDER BY id DESC LIMIT 50', ARRAY_A );
		echo '<hr><h2>Vé đã đặt (từ Zalo)</h2>';
		if ( ! $rows ) { echo '<p>Chưa có vé nào.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Mã vé</th><th>Dịch vụ</th><th>Số tiền</th><th>Khách</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
			$nhan = array( 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'huy' => 'Đã huỷ' );
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( $r['tao_luc'] ) . '</td><td><code>' . esc_html( $r['ma_ve'] ) . '</code></td><td>' . esc_html( $r['dv_ten'] ) . '</td>'
					. '<td>' . esc_html( number_format( $r['so_tien'], 0, ',', '.' ) ) . 'đ</td>'
					. '<td>' . esc_html( $r['ten_khach'] ) . '<br><small>' . esc_html( $r['sdt'] ) . '</small></td>'
					. '<td>' . esc_html( isset( $nhan[ $r['trang_thai'] ] ) ? $nhan[ $r['trang_thai'] ] : $r['trang_thai'] ) . '</td><td>';
				if ( 'cho' === $r['trang_thai'] ) {
					echo '<form method="post" style="display:inline">'; wp_nonce_field( 'pve_tt' );
					echo '<input type="hidden" name="ma_ve" value="' . esc_attr( $r['ma_ve'] ) . '"><button class="button button-primary" name="pve_tt" value="da_tt">Đã thanh toán</button> '
						. '<button class="button" name="pve_tt" value="huy">Huỷ</button></form>';
				} else { echo '—'; }
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}

register_activation_hook( __FILE__, function () { POSH_Ve::bao_dam_bang(); flush_rewrite_rules(); } );
add_action( 'init', array( 'POSH_Ve', 'init' ), 6 );
