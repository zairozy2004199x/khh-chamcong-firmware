<?php
/**
 * Plugin Name:       Sao Kê Ngân Hàng K&H (SePay)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Sao kê & đối soát dòng tiền ngân hàng qua SePay (webhook + Open API) + đối chiếu nộp tiền theo điểm + sao kê cổng Việt QR/MoMo/VNPAY + tổng hợp doanh thu cơ sở. Trang [posh_saoke] bảo vệ bằng PIN. ĐỘC LẬP với plugin vé/ghế.
 * Version:           0.2.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ⚠️ Repo CÔNG KHAI: KHÔNG hardcode PIN/khoá/token vào mã. PIN + Webhook Key + SePay API Token
 *    khai trong WP Admin → "Sao Kê SePay" (lưu ở options/DB), không nhét chuỗi cụ thể vào source.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'SAOKE_App' ) ) :

class SAOKE_App {

	const NS      = 'saoke/v1';
	const VER_TBL = '2';

	/* 3 cổng thanh toán + tên hiển thị. Việt QR về bank 1:1; MoMo/VNPAY gộp cục N:1. */
	private static function cong_ds() { return array( 'vietqr', 'momo', 'vnpay' ); }
	private static function cong_ten() { return array( 'vietqr' => 'Việt QR', 'momo' => 'MoMo', 'vnpay' => 'VNPAY' ); }
	private static function cong_tukhoa_mac_dinh() { return array( 'vietqr' => 'VQR', 'momo' => 'MOMO', 'vnpay' => 'VNPAY' ); }
	/* Mã nộp tiền tự mã hoá 4 chiều: KH705/989 · MTD/KVC · MB/MN · số thứ tự điểm. */
	const RE_MA_NOP = '/KH\s*(705|989)\s*(MTD|KVC)\s*(MB|MN)\s*(\d{1,5})/i';

	// ───────────────────────────── Bootstrap ─────────────────────────────
	public static function init() {
		self::bao_dam_bang();
		self::bao_dam_trang();
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_shortcode( 'posh_saoke', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'wp', array( __CLASS__, 'an_admin_bar' ) );
	}

	public static function tbl() { global $wpdb; return $wpdb->prefix . 'saoke_gd'; }
	public static function tbl_cong() { global $wpdb; return $wpdb->prefix . 'saoke_cong'; }
	public static function tbl_congfile() { global $wpdb; return $wpdb->prefix . 'saoke_congfile'; }

	public static function bao_dam_bang() {
		if ( get_option( 'saoke_tbl' ) === self::VER_TBL ) { return; }
		global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$col = $wpdb->get_charset_collate();
		$tbl = self::tbl();
		dbDelta( "CREATE TABLE $tbl (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sepay_id VARCHAR(40) NOT NULL DEFAULT '',
			ngay_gd DATETIME NULL,
			so_tk VARCHAR(40) NOT NULL DEFAULT '',
			ngan_hang VARCHAR(60) NOT NULL DEFAULT '',
			loai VARCHAR(4) NOT NULL DEFAULT 'in',
			tien BIGINT NOT NULL DEFAULT 0,
			luy_ke BIGINT NULL,
			noi_dung TEXT NULL,
			ma_gd VARCHAR(60) NOT NULL DEFAULT '',
			nhan VARCHAR(60) NOT NULL DEFAULT '',
			nguon VARCHAR(10) NOT NULL DEFAULT 'webhook',
			tao_luc DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY sepay_id (sepay_id),
			KEY so_tk (so_tk), KEY ngay_gd (ngay_gd)
		) $col;" );

		/* Giao dịch từ CỔNG (Việt QR webhook / nạp bù file) — chi tiết từng lần quét.
		   Tách khỏi saoke_gd vì trộn 2 nguồn là đếm tiền hai lần. khoa = chống trùng. */
		$tc = self::tbl_cong();
		dbDelta( "CREATE TABLE $tc (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			nguon VARCHAR(10) NOT NULL DEFAULT '',
			khoa VARCHAR(120) NOT NULL DEFAULT '',
			ma_gd VARCHAR(80) NOT NULL DEFAULT '',
			ref VARCHAR(80) NOT NULL DEFAULT '',
			thoi_diem DATETIME NULL,
			so_tien BIGINT NOT NULL DEFAULT 0,
			huong VARCHAR(8) NOT NULL DEFAULT '',
			trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			so_tk VARCHAR(60) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			diem_ban VARCHAR(120) NOT NULL DEFAULT '',
			doc_duoc TINYINT NOT NULL DEFAULT 0,
			raw TEXT NULL,
			nhan_luc DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY khoa (khoa),
			KEY nguon (nguon), KEY thoi_diem (thoi_diem)
		) $col;" );

		/* Đối soát MoMo/VNPAY bằng file kết xuất — gộp theo ngày × cửa hàng (1 dòng/ngày/CH). */
		$tf = self::tbl_congfile();
		dbDelta( "CREATE TABLE $tf (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			nguon VARCHAR(10) NOT NULL DEFAULT '',
			khoa VARCHAR(160) NOT NULL DEFAULT '',
			thang VARCHAR(7) NOT NULL DEFAULT '',
			ngay VARCHAR(10) NOT NULL DEFAULT '',
			ch_file VARCHAR(160) NOT NULL DEFAULT '',
			ch_chuan VARCHAR(160) NOT NULL DEFAULT '',
			ma_bank VARCHAR(40) NOT NULL DEFAULT '',
			so_tien BIGINT NOT NULL DEFAULT 0,
			so_dong INT NOT NULL DEFAULT 0,
			ten_file VARCHAR(200) NOT NULL DEFAULT '',
			tai_luc DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY khoa (khoa),
			KEY nguon (nguon), KEY thang (thang)
		) $col;" );

		update_option( 'saoke_tbl', self::VER_TBL );
	}

	/* Tự tạo trang chứa [posh_saoke] (slug sao-ke). */
	public static function bao_dam_trang() {
		$pid = (int) get_option( 'saoke_page_id' );
		if ( $pid && ( $p = get_post( $pid ) ) && 'trash' !== $p->post_status ) { return; }
		global $wpdb;
		$co = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status IN ('publish','draft') AND post_content LIKE '%[posh_saoke]%' ORDER BY ID ASC LIMIT 1" );
		if ( $co ) { update_option( 'saoke_page_id', $co ); return; }
		$moi = wp_insert_post( array( 'post_title' => 'Sao Kê Ngân Hàng', 'post_name' => 'sao-ke',
			'post_content' => '[posh_saoke]', 'post_status' => 'publish', 'post_type' => 'page' ) );
		if ( $moi && ! is_wp_error( $moi ) ) { update_option( 'saoke_page_id', (int) $moi ); }
	}

	public static function an_admin_bar() {
		if ( ! is_singular() ) { return; }
		$p = get_post();
		if ( $p && has_shortcode( (string) $p->post_content, 'posh_saoke' ) ) { add_filter( 'show_admin_bar', '__return_false' ); }
	}

	public static function cors( $served, $result, $request, $server ) {
		if ( $request && 0 === strpos( (string) $request->get_route(), '/' . self::NS ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
		}
		return $served;
	}

	// ───────────────────────────── PIN ─────────────────────────────
	private static function pin_ok( $req ) {
		$luu = (string) get_option( 'saoke_pin', '' );
		if ( '' === $luu ) { return false; }
		return hash_equals( $luu, (string) $req->get_param( 'pin' ) );
	}
	private static function loi_pin() { return new WP_Error( 'pin', 'Sai mã PIN hoặc chưa đặt PIN (WP Admin → Sao Kê SePay).', array( 'status' => 401 ) ); }

	// ───────────────────────────── REST ─────────────────────────────
	public static function routes() {
		$pub = array( 'permission_callback' => '__return_true' );
		register_rest_route( self::NS, '/webhook', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_webhook' ) ) + $pub ) );
		register_rest_route( self::NS, '/login',   array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_login' ) ) + $pub ) );
		register_rest_route( self::NS, '/config',  array( array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'r_config' ) ) + $pub ) );
		register_rest_route( self::NS, '/dashboard', array( array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_dashboard' ) ) + $pub ) );
		register_rest_route( self::NS, '/giaodich', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_giaodich' ) ) + $pub ) );
		register_rest_route( self::NS, '/nhan',     array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_nhan' ) ) + $pub ) );
		register_rest_route( self::NS, '/taikhoan', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_taikhoan' ) ) + $pub ) );
		register_rest_route( self::NS, '/taikhoan-xoa', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_taikhoan_xoa' ) ) + $pub ) );
		register_rest_route( self::NS, '/danhmuc',  array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_danhmuc' ) ) + $pub ) );
		register_rest_route( self::NS, '/danhmuc-xoa', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_danhmuc_xoa' ) ) + $pub ) );
		register_rest_route( self::NS, '/cauhinh',  array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_cauhinh' ) ) + $pub ) );
		register_rest_route( self::NS, '/sync',     array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_sync' ) ) + $pub ) );
		register_rest_route( self::NS, '/doipin',   array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_doipin' ) ) + $pub ) );
		// ── v0.2: đối soát ──
		register_rest_route( self::NS, '/diem',      array( array( 'methods' => 'GET',  'callback' => array( __CLASS__, 'r_diem_ds' ) ) + $pub ) );
		register_rest_route( self::NS, '/diem-nhap', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_diem_nhap' ) ) + $pub ) );
		register_rest_route( self::NS, '/diem-xoa',  array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_diem_xoa' ) ) + $pub ) );
		register_rest_route( self::NS, '/doichieu',  array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_doichieu' ) ) + $pub ) );
		register_rest_route( self::NS, '/saoke-cong',array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_saoke_cong' ) ) + $pub ) );
		register_rest_route( self::NS, '/cong-tukhoa', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_cong_tukhoa' ) ) + $pub ) );
		register_rest_route( self::NS, '/anhxa',     array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_anhxa_luu' ) ) + $pub ) );
		register_rest_route( self::NS, '/anhxa-xoa', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_anhxa_xoa' ) ) + $pub ) );
		register_rest_route( self::NS, '/nap-file-cong', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_nap_file_cong' ) ) + $pub ) );
		register_rest_route( self::NS, '/tonghop-coso', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_tonghop_coso' ) ) + $pub ) );
	}

	/* ── Webhook: SePay (mặc định) hoặc cổng qua ?src=vietqr|momo|vnpay ── */
	public static function r_webhook( $req ) {
		$key = (string) get_option( 'saoke_webhook_key', '' );
		if ( '' === $key || ! hash_equals( $key, (string) $req->get_param( 'key' ) ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'sai key' ), 401 );
		}
		$src = strtolower( trim( (string) $req->get_param( 'src' ) ) );
		if ( '' === $src ) { $src = 'sepay'; }
		if ( in_array( $src, self::cong_ds(), true ) ) {
			$kq = self::cong_nhan_webhook( $src, $req );
			return new WP_REST_Response( array( 'success' => true, 'src' => $src ) + $kq, 200 );
		}
		if ( 'sepay' !== $src ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'src không hợp lệ: ' . $src ), 400 );
		}
		$p = $req->get_json_params(); if ( ! is_array( $p ) ) { $p = $req->get_params(); }
		$g = function ( $k, $d = '' ) use ( $p ) { return isset( $p[ $k ] ) ? $p[ $k ] : $d; };
		$sid = (string) ( $g( 'id' ) !== '' ? $g( 'id' ) : $g( 'referenceCode' ) );
		if ( '' === $sid ) { return new WP_REST_Response( array( 'success' => false, 'message' => 'thiếu id' ), 400 ); }
		$loai = ( 'out' === strtolower( (string) $g( 'transferType' ) ) ) ? 'out' : 'in';
		$ok = self::luu_gd( array(
			'sepay_id'  => $sid,
			'ngay_gd'   => self::chuan_ngay( (string) $g( 'transactionDate' ) ),
			'so_tk'     => (string) $g( 'accountNumber' ),
			'ngan_hang' => (string) $g( 'gateway' ),
			'loai'      => $loai,
			'tien'      => (int) round( (float) $g( 'transferAmount', 0 ) ),
			'luy_ke'    => ( '' !== (string) $g( 'accumulated' ) ) ? (int) round( (float) $g( 'accumulated' ) ) : null,
			'noi_dung'  => (string) $g( 'content' ),
			'ma_gd'     => (string) ( $g( 'referenceCode' ) !== '' ? $g( 'referenceCode' ) : $g( 'code' ) ),
			'nguon'     => 'webhook',
		) );
		return new WP_REST_Response( array( 'success' => true, 'moi' => $ok ? 1 : 0 ), 200 );
	}

	private static function luu_gd( $d ) {
		global $wpdb; $tbl = self::tbl();
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tbl WHERE sepay_id=%s", $d['sepay_id'] ) ) ) { return false; }
		$wpdb->insert( $tbl, array(
			'sepay_id' => mb_substr( $d['sepay_id'], 0, 40 ),
			'ngay_gd'  => $d['ngay_gd'] ? $d['ngay_gd'] : current_time( 'mysql' ),
			'so_tk'    => mb_substr( $d['so_tk'], 0, 40 ),
			'ngan_hang'=> mb_substr( $d['ngan_hang'], 0, 60 ),
			'loai'     => $d['loai'],
			'tien'     => (int) $d['tien'],
			'luy_ke'   => is_null( $d['luy_ke'] ) ? null : (int) $d['luy_ke'],
			'noi_dung' => (string) $d['noi_dung'],
			'ma_gd'    => mb_substr( (string) $d['ma_gd'], 0, 60 ),
			'nguon'    => $d['nguon'],
			'tao_luc'  => current_time( 'mysql' ),
		) );
		return true;
	}

	/* SePay gửi ngày "YYYY-MM-DD HH:MM:SS". Chuẩn hoá về MySQL datetime. */
	private static function chuan_ngay( $s ) {
		$s = trim( $s ); if ( '' === $s ) { return ''; }
		$t = strtotime( $s ); return $t ? gmdate( 'Y-m-d H:i:s', $t ) : '';
	}

	// ═══════════════ CỔNG THANH TOÁN: webhook + parse ═══════════════
	private static function num( $v ) { $s = preg_replace( '/[.,](?=\d{3}\b)/', '', (string) $v ); $s = str_replace( ',', '.', $s ); return is_numeric( $s ) ? (float) $s : 0; }

	/* Ngày 'dd/MM/yyyy [HH:mm[:ss]]' hợp lệ (năm > 2000) -> 'dd/MM/yyyy HH:mm:ss' hoặc 'dd/MM/yyyy'. */
	private static function cong_ngay( $s ) {
		if ( ! preg_match( '#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})([ T](\d{1,2}):(\d{2})(:(\d{2}))?)?$#', trim( (string) $s ), $m ) ) { return ''; }
		if ( (int) $m[3] <= 2000 ) { return ''; }
		$d = sprintf( '%02d/%02d/%s', (int) $m[1], (int) $m[2], $m[3] );
		return isset( $m[4] ) && '' !== $m[4] ? $d . ' ' . sprintf( '%02d', (int) $m[5] ) . ':' . $m[6] . ':' . ( isset( $m[8] ) && '' !== $m[8] ? $m[8] : '00' ) : $d;
	}
	private static function cong_ngay_iso( $s ) {
		$s = trim( (string) $s );
		if ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(:(\d{2}))?#', $s, $m ) ) { return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . ( isset( $m[7] ) && '' !== $m[7] ? $m[7] : '00' ); }
		if ( preg_match( '#^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$#', $s, $m ) ) { return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6]; }
		return '';
	}
	private static function cong_ngay_mysql( $s ) { $t = self::moc( $s ); return $t ? gmdate( 'Y-m-d H:i:s', $t ) : null; }

	/* Payload -> danh sách giao dịch. Hai định dạng: Tingo {values:[[...]]} (Việt QR) hoặc JSON tên trường. */
	private static function cong_doc_payload( $raw ) {
		$body = json_decode( (string) $raw, true );
		if ( ! is_array( $body ) ) { return array(); }
		$out = array();
		if ( isset( $body['values'] ) && is_array( $body['values'] ) && isset( $body['values'][0] ) && is_array( $body['values'][0] ) ) {
			foreach ( $body['values'] as $row ) { $tx = self::cong_doc_hang( $row ); if ( $tx ) { $out[] = $tx; } }
			return $out;
		}
		$o = self::cong_doc_obj( $body );
		return $o ? array( $o ) : array();
	}
	/* Tingo: mảng cột không tên. Đọc theo ĐẶC ĐIỂM ô (thứ tự cột phụ thuộc cấu hình Tingo). */
	private static function cong_doc_hang( $row ) {
		if ( ! is_array( $row ) || ! count( $row ) ) { return null; }
		$o = array( 'soTien' => 0, 'maGD' => '', 'ref' => '', 'thoiDiem' => '', 'noiDung' => '', 'soTK' => '', 'huong' => '', 'trangThai' => '', 'diemBan' => '' );
		foreach ( $row as $cell ) {
			$c = trim( (string) $cell );
			if ( '' === $c || '-' === $c ) { continue; }
			$kd = self::kd( $c );
			if ( preg_match( '/giao dich den|tien vao/', $kd ) ) { $o['huong'] = 'Đến'; continue; }
			if ( preg_match( '/giao dich di|chuyen tien di|tien ra/', $kd ) ) { $o['huong'] = 'Đi'; continue; }
			if ( preg_match( '/^(thanh cong|that bai|dang xu ly|huy)$/', $kd ) ) { $o['trangThai'] = $c; continue; }
			$ng = self::cong_ngay( $c );
			if ( $ng ) { if ( '' === $o['thoiDiem'] || $ng > $o['thoiDiem'] ) { $o['thoiDiem'] = $ng; } continue; }
			if ( preg_match( '#^\d{1,2}[/-]\d{1,2}[/-]\d{2,4}#', $c ) || preg_match( '#^\d{4}-\d{1,2}-\d{1,2}#', $c ) ) { continue; } // ngày rác 1970 -> bỏ
			if ( preg_match( '/^\d{6,}\s*-\s*\S+/', $c ) ) { if ( '' === $o['soTK'] ) { $o['soTK'] = $c; } continue; }
			if ( preg_match( '/^[0-9][0-9.,]*$/', $c ) ) { $n = self::num( $c ); if ( $n >= 1000 && $n > $o['soTien'] ) { $o['soTien'] = $n; } continue; }
			if ( false === strpos( $c, ' ' ) && false === strpos( $c, '-' ) && preg_match( '/[A-Za-z]/', $c ) && preg_match( '/[0-9]/', $c ) ) { if ( '' === $o['maGD'] ) { $o['maGD'] = $c; } continue; }
			if ( false === strpos( $c, ' ' ) && false !== strpos( $c, '-' ) && preg_match( '/[A-Za-z]/', $c ) && preg_match( '/[0-9]/', $c ) ) { if ( '' === $o['ref'] ) { $o['ref'] = $c; } continue; }
			if ( false !== strpos( $c, ' ' ) && strlen( $c ) > strlen( $o['noiDung'] ) ) { $o['noiDung'] = $c; }
		}
		if ( $o['soTien'] <= 0 && '' === $o['maGD'] && '' === $o['ref'] ) { return null; }
		$o['docDuoc'] = ( $o['soTien'] > 0 && '' !== $o['thoiDiem'] );
		return $o;
	}
	private static function cong_lay( $srcs, $keys ) {
		foreach ( $srcs as $o ) { if ( ! is_array( $o ) ) { continue; } foreach ( $keys as $k ) { if ( isset( $o[ $k ] ) && '' !== $o[ $k ] && ! is_array( $o[ $k ] ) ) { return $o[ $k ]; } } }
		return '';
	}
	private static function cong_doc_obj( $body ) {
		if ( ! is_array( $body ) ) { return null; }
		$g = function ( $k, $d = array() ) use ( $body ) { return isset( $body[ $k ] ) && is_array( $body[ $k ] ) ? $body[ $k ] : $d; };
		$srcs = array( $body, $g( 'data' ), $g( 'transaction' ), $g( 'payload' ), $g( 'result' ), $g( 'body' ) );
		$o = array(
			'soTien' => self::num( self::cong_lay( $srcs, array( 'amount', 'transferAmount', 'transAmount', 'vnp_Amount', 'amountIn', 'creditAmount', 'totalAmount', 'payAmount', 'orderAmount', 'soTien' ) ) ),
			'maGD' => trim( (string) self::cong_lay( $srcs, array( 'transactionId', 'transId', 'vnp_TransactionNo', 'transactionCode', 'tid', 'id', 'gatewayTransId' ) ) ),
			'ref' => trim( (string) self::cong_lay( $srcs, array( 'referenceCode', 'reference', 'orderId', 'vnp_TxnRef', 'requestId', 'orderCode', 'partnerRefId', 'refId', 'maThamChieu' ) ) ),
			'noiDung' => trim( (string) self::cong_lay( $srcs, array( 'content', 'description', 'orderInfo', 'vnp_OrderInfo', 'addInfo', 'comment', 'noiDung' ) ) ),
			'soTK' => trim( (string) self::cong_lay( $srcs, array( 'accountNumber', 'accountNo', 'soTK', 'creditAccount' ) ) ),
			'trangThai' => trim( (string) self::cong_lay( $srcs, array( 'status', 'resultCode', 'vnp_ResponseCode', 'trangThai' ) ) ),
			'diemBan' => trim( (string) self::cong_lay( $srcs, array( 'storeId', 'storeName', 'terminalId', 'terminalName', 'posId', 'merchantName', 'storeLabel', 'diemBan' ) ) ),
			'huong' => 'Đến',
		);
		$tho = self::cong_lay( $srcs, array( 'transactionDate', 'transTime', 'payDate', 'vnp_PayDate', 'createdAt', 'created_at', 'time', 'thoiDiem' ) );
		$o['thoiDiem'] = self::cong_ngay( $tho ); if ( '' === $o['thoiDiem'] ) { $o['thoiDiem'] = self::cong_ngay_iso( $tho ); }
		if ( '' !== (string) self::cong_lay( $srcs, array( 'vnp_Amount' ) ) && $o['soTien'] > 0 ) { $o['soTien'] = $o['soTien'] / 100; } // VNPAY đơn vị ×100
		$o['docDuoc'] = ( $o['soTien'] > 0 && '' !== $o['thoiDiem'] );
		return ( $o['soTien'] > 0 || '' !== $o['maGD'] || '' !== $o['ref'] ) ? $o : null;
	}
	private static function cong_khoa( $nguon, $tx, $raw ) {
		$ma = '' !== $tx['maGD'] ? $tx['maGD'] : $tx['ref'];
		return $nguon . '|' . ( '' !== $ma ? $ma : ( 'RAW-' . substr( md5( (string) $raw ), 0, 16 ) ) );
	}
	private static function luu_cong( $row ) {
		global $wpdb; $tbl = self::tbl_cong();
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tbl WHERE khoa=%s", $row['khoa'] ) ) ) { return false; }
		$wpdb->insert( $tbl, array(
			'nguon' => $row['nguon'], 'khoa' => mb_substr( $row['khoa'], 0, 120 ),
			'ma_gd' => mb_substr( (string) $row['maGD'], 0, 80 ), 'ref' => mb_substr( (string) $row['ref'], 0, 80 ),
			'thoi_diem' => self::cong_ngay_mysql( $row['thoiDiem'] ), 'so_tien' => (int) round( $row['soTien'] ),
			'huong' => $row['huong'], 'trang_thai' => mb_substr( (string) $row['trangThai'], 0, 30 ),
			'so_tk' => mb_substr( (string) $row['soTK'], 0, 60 ), 'noi_dung' => (string) $row['noiDung'],
			'diem_ban' => mb_substr( (string) $row['diemBan'], 0, 120 ), 'doc_duoc' => $row['docDuoc'] ? 1 : 0,
			'raw' => mb_substr( (string) $row['raw'], 0, 2000 ), 'nhan_luc' => current_time( 'mysql' ),
		) );
		return true;
	}
	private static function cong_nhan_webhook( $nguon, $req ) {
		$raw = $req->get_body(); if ( '' === trim( (string) $raw ) ) { $raw = wp_json_encode( $req->get_json_params() ); }
		$list = self::cong_doc_payload( $raw );
		if ( ! count( $list ) ) { return array( 'moi' => 0, 'message' => 'không đọc được giao dịch từ payload' ); }
		$moi = 0; $trung = 0; $kho = 0;
		foreach ( $list as $tx ) {
			$tx['nguon'] = $nguon; $tx['khoa'] = self::cong_khoa( $nguon, $tx, $raw ); $tx['raw'] = $raw;
			if ( empty( $tx['docDuoc'] ) ) { $kho++; }
			if ( self::luu_cong( $tx ) ) { $moi++; } else { $trung++; }
		}
		return array( 'moi' => $moi, 'trung' => $trung, 'chuaDoc' => $kho );
	}

	public static function r_login( $req ) {
		return self::pin_ok( $req ) ? array( 'ok' => true ) : self::loi_pin();
	}

	private static function ds_tk() { $v = get_option( 'saoke_taikhoan' ); return is_array( $v ) ? $v : array(); }
	private static function ds_dm() { $v = get_option( 'saoke_danhmuc' ); return is_array( $v ) ? $v : array(); }
	private static function ds_pn() {
		$pn = array(); foreach ( self::ds_tk() as $t ) { $x = trim( (string) ( isset( $t['phapNhan'] ) ? $t['phapNhan'] : '' ) ); if ( '' !== $x && ! in_array( $x, $pn, true ) ) { $pn[] = $x; } }
		return $pn;
	}

	public static function r_config( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$key = (string) get_option( 'saoke_webhook_key', '' );
		$url = esc_url_raw( rest_url( self::NS . '/webhook' ) ) . ( $key ? ( '?key=' . rawurlencode( $key ) ) : '' );
		return array( 'ok' => true,
			'taiKhoan' => self::ds_tk(), 'danhMuc' => self::ds_dm(), 'phapNhanOpts' => self::ds_pn(),
			'webhookUrl' => $key ? $url : '', 'hasWebhookKey' => $key !== '', 'hasApiToken' => get_option( 'saoke_api_token', '' ) !== '',
		);
	}

	// ───────────────────────────── Dashboard ─────────────────────────────
	public static function r_dashboard( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$tks = self::ds_tk(); $ten_pn = array(); $dauKy = array();
		foreach ( $tks as $t ) { $ten_pn[ $t['soTK'] ] = isset( $t['phapNhan'] ) ? $t['phapNhan'] : ''; $dauKy[ $t['soTK'] ] = (int) ( isset( $t['soDuDauKy'] ) ? $t['soDuDauKy'] : 0 ); }

		// Tổng thu/chi toàn bộ
		$tongThu = (int) $wpdb->get_var( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE loai='in'" );
		$tongChi = (int) $wpdb->get_var( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE loai='out'" );

		// Từng tài khoản: số dư tính toán vs thực tế (luỹ kế mới nhất)
		$taiKhoan = array(); $tongSoDu = 0; $theoPN = array();
		foreach ( $tks as $t ) {
			$stk = $t['soTK'];
			$thu = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE so_tk=%s AND loai='in'", $stk ) );
			$chi = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE so_tk=%s AND loai='out'", $stk ) );
			$tinh = (int) ( isset( $dauKy[ $stk ] ) ? $dauKy[ $stk ] : 0 ) + $thu - $chi;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT luy_ke FROM $tbl WHERE so_tk=%s AND luy_ke IS NOT NULL ORDER BY ngay_gd DESC, id DESC LIMIT 1", $stk ), ARRAY_A );
			$coTT = $row && $row['luy_ke'] !== null; $thucTe = $coTT ? (int) $row['luy_ke'] : 0;
			$taiKhoan[] = array( 'soTK' => $stk, 'nganHang' => $t['nganHang'], 'chuTK' => $t['chuTK'], 'phapNhan' => $t['phapNhan'],
				'soDuDauKy' => (int) $dauKy[ $stk ], 'soDu' => $thucTe, 'soDuTinhToan' => $tinh,
				'coSoDuThucTe' => $coTT, 'chenhLech' => $coTT ? ( $thucTe - $tinh ) : 0 );
			$tongSoDu += $tinh;
			$pn = $t['phapNhan'] ? $t['phapNhan'] : '(chưa gán)'; $theoPN[ $pn ] = ( isset( $theoPN[ $pn ] ) ? $theoPN[ $pn ] : 0 ) + $tinh;
		}
		$phapNhanRows = array(); foreach ( $theoPN as $k => $v ) { $phapNhanRows[] = array( 'phapNhan' => $k, 'soDu' => $v ); }

		// 12 tháng gần nhất
		$theoThang = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$thang = gmdate( 'Y-m', strtotime( "-$i month", (int) current_time( 'timestamp' ) ) );
			$thu = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE loai='in' AND DATE_FORMAT(ngay_gd,'%%Y-%%m')=%s", $thang ) );
			$chi = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE loai='out' AND DATE_FORMAT(ngay_gd,'%%Y-%%m')=%s", $thang ) );
			$theoThang[] = array( 'thang' => $thang, 'thu' => $thu, 'thuBank' => $thu, 'thuCong' => 0, 'chi' => $chi );
		}

		// Chi theo nhóm nhãn (loai=Chi)
		$nhomChi = array();
		foreach ( self::ds_dm() as $dm ) {
			if ( 'Chi' !== ( isset( $dm['loai'] ) ? $dm['loai'] : 'Chi' ) ) { continue; }
			$t = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(tien),0) FROM $tbl WHERE loai='out' AND nhan=%s", $dm['ten'] ) );
			if ( $t > 0 ) { $nhomChi[] = array( 'nhom' => $dm['ten'], 'tong' => $t ); }
		}

		return array( 'ok' => true, 'tongSoDu' => $tongSoDu, 'tongThuBank' => $tongThu, 'tongThuCong' => 0,
			'congTheoNguon' => array(), 'taiKhoan' => $taiKhoan, 'theoPhapNhan' => $phapNhanRows,
			'theoThang' => $theoThang, 'nhomChi' => $nhomChi );
	}

	// ───────────────────────────── Sao kê (list + lọc + tổng) ─────────────────────────────
	public static function r_giaodich( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$f = $req->get_param( 'filters' ); if ( ! is_array( $f ) ) { $f = array(); }
		$where = array( '1=1' ); $args = array();
		$gv = function ( $k ) use ( $f ) { return isset( $f[ $k ] ) ? trim( (string) $f[ $k ] ) : ''; };
		if ( '' !== $gv( 'soTK' ) )     { $where[] = 'so_tk=%s'; $args[] = $gv( 'soTK' ); }
		if ( '' !== $gv( 'loai' ) )     { $where[] = 'loai=%s'; $args[] = ( 'Thu' === $gv( 'loai' ) ? 'in' : 'out' ); }
		if ( 'CHUA_PHAN_LOAI' === $gv( 'nhan' ) ) { $where[] = "nhan=''"; }
		elseif ( '' !== $gv( 'nhan' ) ) { $where[] = 'nhan=%s'; $args[] = $gv( 'nhan' ); }
		$tu = self::vn2ymd( $gv( 'tuNgay' ) ); $den = self::vn2ymd( $gv( 'denNgay' ) );
		if ( $tu )  { $where[] = 'DATE(ngay_gd)>=%s'; $args[] = $tu; }
		if ( $den ) { $where[] = 'DATE(ngay_gd)<=%s'; $args[] = $den; }
		if ( '' !== $gv( 'tuKhoa' ) )   { $where[] = 'noi_dung LIKE %s'; $args[] = '%' . $wpdb->esc_like( $gv( 'tuKhoa' ) ) . '%'; }
		if ( '' !== $gv( 'phapNhan' ) ) {
			$stks = array(); foreach ( self::ds_tk() as $t ) { if ( ( isset( $t['phapNhan'] ) ? $t['phapNhan'] : '' ) === $gv( 'phapNhan' ) ) { $stks[] = $t['soTK']; } }
			if ( $stks ) { $where[] = 'so_tk IN (' . implode( ',', array_fill( 0, count( $stks ), '%s' ) ) . ')'; $args = array_merge( $args, $stks ); }
			else { $where[] = '1=0'; }
		}
		$W = implode( ' AND ', $where );
		$sql = "SELECT sepay_id, ngay_gd, so_tk, ngan_hang, loai, tien, luy_ke, noi_dung, ma_gd, nhan, nguon FROM $tbl WHERE $W ORDER BY ngay_gd DESC, id DESC LIMIT 3000";
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$pn_map = array(); foreach ( self::ds_tk() as $t ) { $pn_map[ $t['soTK'] ] = isset( $t['phapNhan'] ) ? $t['phapNhan'] : ''; }
		$ds = array(); $tongVao = 0; $tongRa = 0;
		foreach ( (array) $rows as $r ) {
			$vao = 'in' === $r['loai'] ? (int) $r['tien'] : 0; $ra = 'out' === $r['loai'] ? (int) $r['tien'] : 0;
			$tongVao += $vao; $tongRa += $ra;
			$ds[] = array( 'sepayId' => $r['sepay_id'], 'ngayGD' => self::ymd2vn( $r['ngay_gd'] ), 'nganHang' => $r['ngan_hang'],
				'soTK' => $r['so_tk'], 'phapNhan' => isset( $pn_map[ $r['so_tk'] ] ) ? $pn_map[ $r['so_tk'] ] : '',
				'tenNguonTien' => '', 'laCong' => false, 'noiDung' => $r['noi_dung'], 'maGD' => $r['ma_gd'],
				'vao' => $vao, 'ra' => $ra, 'soDu' => is_null( $r['luy_ke'] ) ? null : (int) $r['luy_ke'],
				'nhan' => $r['nhan'], 'nguon' => $r['nguon'] );
		}
		return array( 'ok' => true, 'rows' => $ds, 'soDong' => count( $ds ),
			'tongVao' => $tongVao, 'tongVaoBank' => $tongVao, 'tongVaoCong' => 0,
			'tongRa' => $tongRa, 'congTheoNguon' => array(), 'tuKhoaCong' => array() );
	}

	public static function r_nhan( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		global $wpdb;
		$wpdb->update( self::tbl(), array( 'nhan' => sanitize_text_field( (string) $req->get_param( 'nhan' ) ) ),
			array( 'sepay_id' => (string) $req->get_param( 'sepayId' ) ) );
		return array( 'ok' => true );
	}

	// ───────────────────────────── Tài khoản / Danh mục / Cấu hình ─────────────────────────────
	public static function r_taikhoan( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$so = preg_replace( '/\s+/', '', (string) $req->get_param( 'soTK' ) );
		if ( '' === $so ) { return new WP_Error( 'so', 'Thiếu số TK.', array( 'status' => 400 ) ); }
		$moi = array( 'soTK' => $so, 'nganHang' => sanitize_text_field( (string) $req->get_param( 'nganHang' ) ),
			'chuTK' => sanitize_text_field( (string) $req->get_param( 'chuTK' ) ), 'ghiChu' => sanitize_text_field( (string) $req->get_param( 'ghiChu' ) ),
			'phapNhan' => sanitize_text_field( (string) $req->get_param( 'phapNhan' ) ),
			'soDuDauKy' => (int) preg_replace( '/\D+/', '', (string) $req->get_param( 'soDuDauKy' ) ),
			'ngayDauKy' => sanitize_text_field( (string) $req->get_param( 'ngayDauKy' ) ) );
		$ds = self::ds_tk(); $thay = false;
		foreach ( $ds as $k => $v ) { if ( $v['soTK'] === $so ) { $ds[ $k ] = $moi; $thay = true; break; } }
		if ( ! $thay ) { $ds[] = $moi; }
		update_option( 'saoke_taikhoan', array_values( $ds ) );
		return array( 'ok' => true );
	}
	public static function r_taikhoan_xoa( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$so = (string) $req->get_param( 'soTK' ); $ra = array();
		foreach ( self::ds_tk() as $v ) { if ( $v['soTK'] !== $so ) { $ra[] = $v; } }
		update_option( 'saoke_taikhoan', array_values( $ra ) );
		return array( 'ok' => true );
	}
	public static function r_danhmuc( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$ten = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		if ( '' === $ten ) { return new WP_Error( 'ten', 'Thiếu tên nhóm.', array( 'status' => 400 ) ); }
		$moi = array( 'ten' => $ten, 'loai' => ( 'Thu' === (string) $req->get_param( 'loai' ) ? 'Thu' : 'Chi' ),
			'mau' => sanitize_hex_color( (string) $req->get_param( 'mau' ) ) ? sanitize_hex_color( (string) $req->get_param( 'mau' ) ) : '#3b82f6' );
		$ds = self::ds_dm(); $thay = false;
		foreach ( $ds as $k => $v ) { if ( $v['ten'] === $ten ) { $ds[ $k ] = $moi; $thay = true; break; } }
		if ( ! $thay ) { $ds[] = $moi; }
		update_option( 'saoke_danhmuc', array_values( $ds ) );
		return array( 'ok' => true );
	}
	public static function r_danhmuc_xoa( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$ten = (string) $req->get_param( 'ten' ); $ra = array();
		foreach ( self::ds_dm() as $v ) { if ( $v['ten'] !== $ten ) { $ra[] = $v; } }
		update_option( 'saoke_danhmuc', array_values( $ra ) );
		return array( 'ok' => true );
	}
	public static function r_cauhinh( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$tok = trim( (string) $req->get_param( 'token' ) ); $key = trim( (string) $req->get_param( 'key' ) );
		if ( '' !== $tok ) { update_option( 'saoke_api_token', $tok ); }
		if ( '' !== $key ) { update_option( 'saoke_webhook_key', $key ); }
		return array( 'ok' => true );
	}
	public static function r_doipin( $req ) {
		$cu = (string) $req->get_param( 'cu' ); $moi = preg_replace( '/\D+/', '', (string) $req->get_param( 'moi' ) );
		if ( ! hash_equals( (string) get_option( 'saoke_pin', '' ), $cu ) ) { return new WP_Error( 'pin', 'PIN hiện tại không đúng.', array( 'status' => 401 ) ); }
		if ( strlen( $moi ) < 4 || strlen( $moi ) > 8 ) { return new WP_Error( 'pin', 'PIN mới phải 4-8 số.', array( 'status' => 400 ) ); }
		update_option( 'saoke_pin', $moi );
		return array( 'ok' => true );
	}

	/* ── Đồng bộ lịch sử qua SePay Open API ── */
	public static function r_sync( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$token = (string) get_option( 'saoke_api_token', '' );
		if ( '' === $token ) { return new WP_Error( 'token', 'Chưa đặt SePay API Token.', array( 'status' => 409 ) ); }
		$tu = self::vn2ymd( (string) $req->get_param( 'tu' ) ); $den = self::vn2ymd( (string) $req->get_param( 'den' ) );
		$tk = preg_replace( '/\s+/', '', (string) $req->get_param( 'tk' ) );
		$url = add_query_arg( array_filter( array(
			'limit' => 5000, 'transaction_date_min' => $tu ? $tu . ' 00:00:00' : '',
			'transaction_date_max' => $den ? $den . ' 23:59:59' : '', 'account_number' => $tk ?: '',
		) ), 'https://my.sepay.vn/userapi/transactions/list' );
		$res = wp_remote_get( $url, array( 'timeout' => 40, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
		if ( is_wp_error( $res ) ) { return new WP_Error( 'kn', 'Không gọi được SePay: ' . $res->get_error_message(), array( 'status' => 502 ) ); }
		$d = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $d ) || ! isset( $d['transactions'] ) ) {
			$msg = is_array( $d ) && isset( $d['messages'] ) ? wp_json_encode( $d['messages'] ) : 'SePay trả về không hợp lệ.';
			return new WP_Error( 'sepay', $msg, array( 'status' => 502 ) );
		}
		$moi = 0; $trung = 0;
		foreach ( (array) $d['transactions'] as $t ) {
			$vao = (float) ( isset( $t['amount_in'] ) ? $t['amount_in'] : 0 );
			$ra  = (float) ( isset( $t['amount_out'] ) ? $t['amount_out'] : 0 );
			$loai = $ra > 0 ? 'out' : 'in'; $tien = $ra > 0 ? $ra : $vao;
			$ok = self::luu_gd( array(
				'sepay_id'  => (string) ( isset( $t['id'] ) ? $t['id'] : ( isset( $t['reference_number'] ) ? $t['reference_number'] : '' ) ),
				'ngay_gd'   => self::chuan_ngay( (string) ( isset( $t['transaction_date'] ) ? $t['transaction_date'] : '' ) ),
				'so_tk'     => (string) ( isset( $t['account_number'] ) ? $t['account_number'] : '' ),
				'ngan_hang' => (string) ( isset( $t['bank_brand_name'] ) ? $t['bank_brand_name'] : ( isset( $t['bank_name'] ) ? $t['bank_name'] : '' ) ),
				'loai'      => $loai, 'tien' => (int) round( $tien ),
				'luy_ke'    => ( '' !== (string) ( isset( $t['accumulated'] ) ? $t['accumulated'] : '' ) ) ? (int) round( (float) $t['accumulated'] ) : null,
				'noi_dung'  => (string) ( isset( $t['transaction_content'] ) ? $t['transaction_content'] : '' ),
				'ma_gd'     => (string) ( isset( $t['reference_number'] ) ? $t['reference_number'] : '' ),
				'nguon'     => 'api',
			) );
			if ( $ok ) { $moi++; } else { $trung++; }
		}
		return array( 'ok' => true, 'moi' => $moi, 'trung' => $trung );
	}

	// ═══════════════ v0.2: ĐIỂM NỘP ═══════════════
	public static function r_diem_ds( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		return array( 'ok' => true, 'ds' => self::ds_diem(), 'tukhoaCong' => self::tukhoa_cong_ra() );
	}
	private static function tukhoa_cong_ra() {
		$ten = self::cong_ten(); $out = array();
		foreach ( self::cong_ds() as $n ) { $out[] = array( 'nguon' => $n, 'ten' => $ten[ $n ], 'tuKhoa' => self::cong_tukhoa( $n ) ); }
		return $out;
	}
	/* Nhập danh sách điểm: mỗi dòng có 1 mã nộp + tên. thay=1 -> thay toàn bộ, 0 -> gộp thêm. */
	public static function r_diem_nhap( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$txt = (string) $req->get_param( 'text' );
		$thay = (int) $req->get_param( 'thay' ) === 1;
		$cu = $thay ? array() : self::ds_diem();
		$diem = array(); $order = array();
		foreach ( $cu as $d ) { if ( ! empty( $d['ma'] ) ) { $diem[ $d['ma'] ] = $d; $order[] = $d['ma']; } }
		$them = 0; $thieuMa = array(); $trung = array();
		$lines = preg_split( '/\r\n|\r|\n/', $txt );
		foreach ( $lines as $ln ) {
			$ln = trim( $ln ); if ( '' === $ln ) { continue; }
			$p = self::tach_ma_nop( $ln );
			if ( ! $p ) { $thieuMa[] = mb_substr( $ln, 0, 80 ); continue; }
			// Tên = phần còn lại sau khi bỏ mã nộp (và các dấu phân cách đầu/cuối).
			$ten = trim( preg_replace( self::RE_MA_NOP, ' ', $ln ) );
			$ten = trim( $ten, " \t,;|-" );
			$ten = preg_replace( '/\s+/', ' ', $ten );
			if ( isset( $diem[ $p['ma'] ] ) ) {
				$rec = $diem[ $p['ma'] ];
				if ( '' !== $ten && $ten !== ( isset( $rec['ten'] ) ? $rec['ten'] : '' ) ) { $trung[] = $p['ma']; }
			} else {
				$diem[ $p['ma'] ] = array( 'ma' => $p['ma'], 'phapNhan' => $p['phapNhan'], 'heThong' => $p['heThong'],
					'mien' => $p['mien'], 'so' => $p['so'], 'ten' => $ten );
				$order[] = $p['ma']; $them++;
			}
		}
		$ds = array(); foreach ( $order as $ma ) { if ( isset( $diem[ $ma ] ) ) { $ds[] = $diem[ $ma ]; unset( $diem[ $ma ] ); } }
		update_option( 'saoke_diem', $ds );
		return array( 'ok' => true, 'tong' => count( $ds ), 'them' => $them,
			'thieuMa' => array_slice( $thieuMa, 0, 30 ), 'soThieuMa' => count( $thieuMa ),
			'trung' => array_slice( array_unique( $trung ), 0, 30 ) );
	}
	public static function r_diem_xoa( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$ma = trim( (string) $req->get_param( 'ma' ) );
		if ( 'ALL' === $ma ) { update_option( 'saoke_diem', array() ); return array( 'ok' => true, 'tong' => 0 ); }
		$ra = array(); foreach ( self::ds_diem() as $d ) { if ( ( isset( $d['ma'] ) ? $d['ma'] : '' ) !== $ma ) { $ra[] = $d; } }
		update_option( 'saoke_diem', array_values( $ra ) );
		return array( 'ok' => true, 'tong' => count( $ra ) );
	}

	// ═══════════════ v0.2: ĐỐI CHIẾU NỘP TIỀN THEO ĐIỂM ═══════════════
	public static function r_doichieu( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$tu = self::vn2ymd( (string) $req->get_param( 'tuNgay' ) ); $den = self::vn2ymd( (string) $req->get_param( 'denNgay' ) );
		$where = array( "loai='in'" ); $args = array();
		if ( $tu )  { $where[] = 'DATE(ngay_gd)>=%s'; $args[] = $tu; }
		if ( $den ) { $where[] = 'DATE(ngay_gd)<=%s'; $args[] = $den; }
		$W = implode( ' AND ', $where );
		$sql = "SELECT ngay_gd, so_tk, tien, noi_dung FROM $tbl WHERE $W ORDER BY ngay_gd DESC, id DESC LIMIT 20000";
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$thu = array(); $khongRo = array(); $tongVao = 0; $soGdCoMa = 0;
		foreach ( (array) $rows as $r ) {
			$vao = (int) $r['tien']; if ( $vao <= 0 ) { continue; }
			$tongVao += $vao; $ngay = self::ymd2vn( $r['ngay_gd'] );
			$p = self::tach_ma_nop( $r['noi_dung'] );
			if ( ! $p ) { if ( count( $khongRo ) < 300 ) { $khongRo[] = array( 'ngayGD' => $ngay, 'soTK' => $r['so_tk'], 'noiDung' => $r['noi_dung'], 'vao' => $vao ); } continue; }
			$soGdCoMa++;
			if ( ! isset( $thu[ $p['ma'] ] ) ) { $thu[ $p['ma'] ] = array( 'soTien' => 0, 'soLan' => 0, 'lanCuoi' => '' ); }
			$thu[ $p['ma'] ]['soTien'] += $vao; $thu[ $p['ma'] ]['soLan']++;
			if ( '' === $thu[ $p['ma'] ]['lanCuoi'] || self::moc( $ngay ) >= self::moc( $thu[ $p['ma'] ]['lanCuoi'] ) ) { $thu[ $p['ma'] ]['lanCuoi'] = $ngay; }
		}
		$grp = self::nhom_rong();
		$coTrongDs = array();
		foreach ( self::ds_diem() as $dm ) {
			$k = ( isset( $dm['heThong'] ) ? $dm['heThong'] : '' ) . ( isset( $dm['mien'] ) ? $dm['mien'] : '' );
			if ( ! isset( $grp[ $k ] ) ) { continue; }
			$coTrongDs[ $dm['ma'] ] = 1;
			$t = isset( $thu[ $dm['ma'] ] ) ? $thu[ $dm['ma'] ] : null;
			$o = array( 'ma' => $dm['ma'], 'ten' => isset( $dm['ten'] ) ? $dm['ten'] : '', 'phapNhan' => $dm['phapNhan'],
				'tenPhapNhan' => self::ten_phap_nhan( $dm['phapNhan'] ), 'daNop' => (bool) $t,
				'soTien' => $t ? $t['soTien'] : 0, 'soLan' => $t ? $t['soLan'] : 0, 'lanCuoi' => $t ? $t['lanCuoi'] : '' );
			$grp[ $k ]['diem'][] = $o; $grp[ $k ]['tongDiem']++;
			if ( $o['daNop'] ) { $grp[ $k ]['soDaNop']++; $grp[ $k ]['tienDaNop'] += $o['soTien']; } else { $grp[ $k ]['soChuaNop']++; }
		}
		$maLa = array();
		foreach ( $thu as $ma => $t ) { if ( empty( $coTrongDs[ $ma ] ) ) { $maLa[] = array( 'ma' => $ma, 'soTien' => $t['soTien'], 'soLan' => $t['soLan'], 'lanCuoi' => $t['lanCuoi'] ); } }
		$nhom = array_values( $grp );
		return array( 'ok' => true, 'tuNgay' => (string) $req->get_param( 'tuNgay' ), 'denNgay' => (string) $req->get_param( 'denNgay' ),
			'nhom' => $nhom, 'tongDiem' => count( self::ds_diem() ),
			'tongDaNop' => array_sum( array_map( function ( $g ) { return $g['soDaNop']; }, $nhom ) ),
			'tongChuaNop' => array_sum( array_map( function ( $g ) { return $g['soChuaNop']; }, $nhom ) ),
			'tongTienNop' => array_sum( array_map( function ( $g ) { return $g['tienDaNop']; }, $nhom ) ),
			'tongVaoTrongKy' => $tongVao, 'soGdCoMa' => $soGdCoMa,
			'khongRo' => $khongRo, 'maLa' => $maLa );
	}
	/* 4 nhóm cố định: MTD/KVC × MB/MN. */
	private static function nhom_rong() {
		$g = array();
		foreach ( array( 'MTD', 'KVC' ) as $ht ) {
			foreach ( array( 'MB', 'MN' ) as $mi ) {
				$g[ $ht . $mi ] = array( 'khoa' => $ht . $mi, 'heThong' => $ht, 'mien' => $mi,
					'ten' => $ht . ' · ' . self::ten_mien( $mi ), 'diem' => array(),
					'tongDiem' => 0, 'soDaNop' => 0, 'soChuaNop' => 0, 'tienDaNop' => 0 );
			}
		}
		return $g;
	}

	// ═══════════════ v0.2: SAO KÊ CỔNG ═══════════════
	public static function r_saoke_cong( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		global $wpdb;
		$nguon = strtolower( trim( (string) $req->get_param( 'nguon' ) ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return new WP_Error( 'nguon', 'Nguồn không hợp lệ.', array( 'status' => 400 ) ); }
		$tu = self::vn2ymd( (string) $req->get_param( 'tuNgay' ) ); $den = self::vn2ymd( (string) $req->get_param( 'denNgay' ) );
		// (A) Từ cổng — chi tiết từng giao dịch
		$tc = self::tbl_cong();
		$wa = array( 'nguon=%s' ); $aa = array( $nguon );
		if ( $tu )  { $wa[] = 'DATE(thoi_diem)>=%s'; $aa[] = $tu; }
		if ( $den ) { $wa[] = 'DATE(thoi_diem)<=%s'; $aa[] = $den; }
		$rowsC = $wpdb->get_results( $wpdb->prepare( "SELECT thoi_diem, so_tien, ma_gd, ref, huong, trang_thai, so_tk, noi_dung, diem_ban, doc_duoc FROM $tc WHERE " . implode( ' AND ', $wa ) . " ORDER BY thoi_diem DESC, id DESC LIMIT 3000", $aa ), ARRAY_A );
		$cong = array(); $congTien = 0; $congKho = 0;
		foreach ( (array) $rowsC as $r ) {
			if ( (int) $r['doc_duoc'] !== 1 ) { $congKho++; continue; }
			if ( 'Đi' === $r['huong'] ) { continue; }
			$tenMay = self::cong_ten_may( $r['noi_dung'] ); if ( '' === $tenMay ) { $tenMay = strtoupper( (string) $r['diem_ban'] ); }
			$cong[] = array( 'thoiDiem' => self::ymd2vn( $r['thoi_diem'] ), 'soTien' => (int) $r['so_tien'],
				'maGD' => $r['ma_gd'], 'ref' => $r['ref'], 'trangThai' => $r['trang_thai'], 'soTK' => $r['so_tk'],
				'noiDung' => $r['noi_dung'], 'tenMay' => $tenMay, 'coSo' => self::cong_coso( $tenMay ) );
			$congTien += (int) $r['so_tien'];
		}
		// (B) Từ sao kê ngân hàng — dòng tiền vào khớp từ khoá cổng
		$tuKhoa = self::cong_tukhoa( $nguon ); $kw = self::kd( $tuKhoa );
		$tbl = self::tbl();
		$wb = array( "loai='in'" ); $ab = array();
		if ( $tu )  { $wb[] = 'DATE(ngay_gd)>=%s'; $ab[] = $tu; }
		if ( $den ) { $wb[] = 'DATE(ngay_gd)<=%s'; $ab[] = $den; }
		if ( '' !== $tuKhoa ) { $wb[] = 'noi_dung LIKE %s'; $ab[] = '%' . $wpdb->esc_like( $tuKhoa ) . '%'; }
		$sqlB = "SELECT ngay_gd, ngan_hang, so_tk, tien, noi_dung, ma_gd FROM $tbl WHERE " . implode( ' AND ', $wb ) . " ORDER BY ngay_gd DESC, id DESC LIMIT 3000";
		$rowsB = $ab ? $wpdb->get_results( $wpdb->prepare( $sqlB, $ab ), ARRAY_A ) : $wpdb->get_results( $sqlB, ARRAY_A );
		$bank = array(); $bankTien = 0;
		foreach ( (array) $rowsB as $r ) {
			if ( '' !== $kw && false === strpos( self::kd( $r['noi_dung'] ), $kw ) ) { continue; }
			$bank[] = array( 'ngayGD' => self::ymd2vn( $r['ngay_gd'] ), 'nganHang' => $r['ngan_hang'], 'soTK' => $r['so_tk'],
				'vao' => (int) $r['tien'], 'noiDung' => $r['noi_dung'], 'maGD' => $r['ma_gd'] );
			$bankTien += (int) $r['tien'];
		}
		$key = (string) get_option( 'saoke_webhook_key', '' );
		$url = $key ? ( esc_url_raw( rest_url( self::NS . '/webhook' ) ) . '?key=' . rawurlencode( $key ) . '&src=' . $nguon ) : '';
		$ten = self::cong_ten();
		return array( 'ok' => true, 'nguon' => $nguon, 'ten' => $ten[ $nguon ], 'tuKhoa' => $tuKhoa,
			'tuKhoaMacDinh' => self::cong_tukhoa_mac_dinh()[ $nguon ], 'webhookUrl' => $url, 'thieuKey' => '' === $key,
			'cong' => $cong, 'congTien' => $congTien, 'congDong' => count( $cong ), 'congKho' => $congKho,
			'bank' => $bank, 'bankTien' => $bankTien, 'bankDong' => count( $bank ),
			'chenh' => $congTien - $bankTien, 'kieuDoiSoat' => 'vietqr' === $nguon ? '1:1' : 'N:1' );
	}
	public static function r_cong_tukhoa( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$nguon = strtolower( trim( (string) $req->get_param( 'nguon' ) ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return new WP_Error( 'nguon', 'Nguồn không hợp lệ.', array( 'status' => 400 ) ); }
		$tk = trim( (string) $req->get_param( 'tuKhoa' ) );
		if ( '' === $tk ) { return new WP_Error( 'tk', 'Từ khoá rỗng thì không lọc được dòng nào.', array( 'status' => 400 ) ); }
		$o = get_option( 'saoke_cong_tukhoa' ); $o = is_array( $o ) ? $o : array(); $o[ $nguon ] = $tk;
		update_option( 'saoke_cong_tukhoa', $o );
		return array( 'ok' => true, 'nguon' => $nguon, 'tuKhoa' => $tk );
	}

	// ═══════════════ v0.2: ÁNH XẠ CỬA HÀNG ═══════════════
	public static function r_anhxa_luu( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$nguon = strtolower( trim( (string) $req->get_param( 'nguon' ) ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return new WP_Error( 'nguon', 'Nguồn không hợp lệ.', array( 'status' => 400 ) ); }
		$tf = trim( (string) $req->get_param( 'tenFile' ) );
		if ( '' === $tf ) { return new WP_Error( 'tf', 'Thiếu tên cửa hàng ở file cổng.', array( 'status' => 400 ) ); }
		$tc = trim( (string) $req->get_param( 'tenChuan' ) ); if ( '' === $tc ) { $tc = $tf; }
		$mb = trim( (string) $req->get_param( 'maBank' ) );
		$tu = self::vn2ymd_soft( (string) $req->get_param( 'tuNgay' ) ); $den = self::vn2ymd_soft( (string) $req->get_param( 'denNgay' ) );
		$tuVN = $tu ? self::ymd2vn_ngay( $tu ) : ''; $denVN = $den ? self::ymd2vn_ngay( $den ) : '';
		// Suy mã nộp; không ra được thì không lưu dòng vô dụng.
		$suy = self::ax_ma_nop( array( 'maBank' => $mb, 'tenChuan' => $tc ), self::map_ten_diem() );
		if ( '' === $suy['ma'] ) { return new WP_Error( 'ma', 'Chưa xác định được mã nộp tiền: ' . $suy['vi'] . '. Chọn Cửa hàng chuẩn đúng tên trong danh sách điểm, hoặc điền thẳng Mã bank.', array( 'status' => 400 ) ); }
		$mb = $suy['ma'];
		$all = get_option( 'saoke_anhxa' ); $all = is_array( $all ) ? $all : array();
		$k = self::chuan_ch( $tf ); $thay = false;
		foreach ( $all as &$r ) {
			if ( strtolower( (string) ( isset( $r['nguon'] ) ? $r['nguon'] : '' ) ) === $nguon && self::chuan_ch( isset( $r['tenFile'] ) ? $r['tenFile'] : '' ) === $k
				&& (string) ( isset( $r['tuNgay'] ) ? $r['tuNgay'] : '' ) === $tuVN && (string) ( isset( $r['denNgay'] ) ? $r['denNgay'] : '' ) === $denVN ) {
				$r = array( 'nguon' => $nguon, 'tenFile' => $tf, 'tenChuan' => $tc, 'maBank' => $mb, 'tuNgay' => $tuVN, 'denNgay' => $denVN ); $thay = true; break;
			}
		}
		unset( $r );
		if ( ! $thay ) { $all[] = array( 'nguon' => $nguon, 'tenFile' => $tf, 'tenChuan' => $tc, 'maBank' => $mb, 'tuNgay' => $tuVN, 'denNgay' => $denVN ); }
		update_option( 'saoke_anhxa', array_values( $all ) );
		// Vá dòng file đã nạp của cửa hàng này trong khoảng ngày.
		global $wpdb; $tf_tbl = self::tbl_congfile();
		$va = 0; $frows = $wpdb->get_results( $wpdb->prepare( "SELECT id, ngay, ch_file FROM $tf_tbl WHERE nguon=%s", $nguon ), ARRAY_A );
		foreach ( (array) $frows as $fr ) {
			if ( self::chuan_ch( $fr['ch_file'] ) !== $k ) { continue; }
			$m = self::moc( $fr['ngay'] );
			if ( $tuVN && $m < self::moc( $tuVN ) ) { continue; }
			if ( $denVN && $m > self::moc( $denVN ) + 86399 ) { continue; }
			$wpdb->update( $tf_tbl, array( 'ch_chuan' => $tc, 'ma_bank' => $mb ), array( 'id' => (int) $fr['id'] ) ); $va++;
		}
		return array( 'ok' => true, 'nguon' => $nguon, 'tenFile' => $tf, 'tenChuan' => $tc, 'maBank' => $mb, 'tuNgay' => $tuVN, 'denNgay' => $denVN, 'daVa' => $va, 'laSua' => $thay );
	}
	public static function r_anhxa_xoa( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$nguon = strtolower( trim( (string) $req->get_param( 'nguon' ) ) );
		$tf = trim( (string) $req->get_param( 'tenFile' ) );
		$tu = trim( (string) $req->get_param( 'tuNgay' ) ); $den = trim( (string) $req->get_param( 'denNgay' ) );
		$k = self::chuan_ch( $tf );
		$all = get_option( 'saoke_anhxa' ); $all = is_array( $all ) ? $all : array(); $ra = array(); $xoa = 0;
		foreach ( $all as $r ) {
			$match = strtolower( (string) ( isset( $r['nguon'] ) ? $r['nguon'] : '' ) ) === $nguon && self::chuan_ch( isset( $r['tenFile'] ) ? $r['tenFile'] : '' ) === $k
				&& (string) ( isset( $r['tuNgay'] ) ? $r['tuNgay'] : '' ) === $tu && (string) ( isset( $r['denNgay'] ) ? $r['denNgay'] : '' ) === $den;
			if ( $match && ! $xoa ) { $xoa++; continue; }
			$ra[] = $r;
		}
		update_option( 'saoke_anhxa', array_values( $ra ) );
		return array( 'ok' => true, 'daXoa' => $xoa );
	}

	// ═══════════════ v0.2: NẠP FILE CỔNG (MoMo/VNPAY) ═══════════════
	/* rows = [[ngay, tien, cuaHang, maCH], ...] client cắt sẵn (ngày là chuỗi). ghiDe=1 -> đè ngày trùng. */
	public static function r_nap_file_cong( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$nguon = strtolower( trim( (string) $req->get_param( 'nguon' ) ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return new WP_Error( 'nguon', 'Nguồn không hợp lệ.', array( 'status' => 400 ) ); }
		$rows = $req->get_param( 'rows' ); if ( ! is_array( $rows ) || ! count( $rows ) ) { return new WP_Error( 'rows', 'File không có dòng dữ liệu.', array( 'status' => 400 ) ); }
		if ( count( $rows ) > 50000 ) { return new WP_Error( 'rows', 'File quá lớn, tách nhỏ giúp em.', array( 'status' => 400 ) ); }
		$ghiDe = (int) $req->get_param( 'ghiDe' ) === 1;
		$tenFile = sanitize_text_field( (string) $req->get_param( 'tenFile' ) );
		$anhXa = self::ds_anhxa( $nguon ); $mapTen = self::map_ten_diem();
		$gom = array(); $khongNgay = 0; $khongTien = 0;
		foreach ( $rows as $r ) {
			$r = (array) $r;
			$ngay = self::file_ngay( isset( $r[0] ) ? $r[0] : '' );
			$tien = self::num( isset( $r[1] ) ? $r[1] : 0 );
			$ch = trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) );
			$maCH = trim( (string) ( isset( $r[3] ) ? $r[3] : '' ) );
			if ( '' === $ch && '' !== $maCH ) { $ch = '#' . $maCH; }
			if ( '' === $ngay ) { $khongNgay++; continue; }
			if ( $tien <= 0 ) { $khongTien++; continue; }
			$k = $nguon . '|' . $ngay . '|' . ( self::chuan_ch( $ch ) ?: 'khongro' );
			if ( ! isset( $gom[ $k ] ) ) { $gom[ $k ] = array( 'khoa' => $k, 'ngay' => $ngay, 'thang' => substr( $ngay, 6, 4 ) . '-' . substr( $ngay, 3, 2 ), 'cuaHang' => $ch, 'soTien' => 0, 'soDong' => 0 ); }
			$gom[ $k ]['soTien'] += $tien; $gom[ $k ]['soDong']++;
		}
		if ( ! count( $gom ) ) { return new WP_Error( 'rows', 'Không đọc được dòng nào đủ ngày + số tiền (thiếu ngày: ' . $khongNgay . ', thiếu tiền: ' . $khongTien . '). Kiểm lại cột đã chọn.', array( 'status' => 400 ) ); }
		global $wpdb; $tbl = self::tbl_congfile(); $luc = current_time( 'mysql' );
		$themMoi = 0; $daGhiDe = 0; $trung = 0; $tongThem = 0; $chMoi = array();
		foreach ( $gom as $g ) {
			$ax = self::ax_theo_ngay( isset( $anhXa[ self::chuan_ch( $g['cuaHang'] ) ] ) ? $anhXa[ self::chuan_ch( $g['cuaHang'] ) ] : null, $g['ngay'] );
			$suy = self::ax_ma_nop( $ax, $mapTen ); $maAx = $suy['ma'];
			if ( '' === $maAx && '' !== $g['cuaHang'] ) { $chMoi[ $g['cuaHang'] ] = 1; }
			$data = array( 'nguon' => $nguon, 'khoa' => mb_substr( $g['khoa'], 0, 160 ), 'thang' => $g['thang'], 'ngay' => $g['ngay'],
				'ch_file' => mb_substr( $g['cuaHang'], 0, 160 ), 'ch_chuan' => $ax ? mb_substr( (string) $ax['tenChuan'], 0, 160 ) : '',
				'ma_bank' => $maAx, 'so_tien' => (int) round( $g['soTien'] ), 'so_dong' => (int) $g['soDong'], 'ten_file' => mb_substr( $tenFile, 0, 200 ), 'tai_luc' => $luc );
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $tbl WHERE khoa=%s", $g['khoa'] ) );
			if ( $id ) {
				if ( ! $ghiDe ) { $trung++; continue; }
				$wpdb->update( $tbl, $data, array( 'id' => (int) $id ) ); $daGhiDe++; $tongThem += $g['soTien'];
			} else {
				$wpdb->insert( $tbl, $data ); $themMoi++; $tongThem += $g['soTien'];
			}
		}
		return array( 'ok' => true, 'nguon' => $nguon, 'tenFile' => $tenFile, 'soDongFile' => count( $rows ),
			'themMoi' => $themMoi, 'daGhiDe' => $daGhiDe, 'trungBoQua' => $trung, 'tongTienThem' => $tongThem,
			'khongNgay' => $khongNgay, 'khongTien' => $khongTien, 'cuaHangMoi' => array_slice( array_keys( $chMoi ), 0, 50 ) );
	}

	// ═══════════════ v0.2: TỔNG HỢP DOANH THU CƠ SỞ ═══════════════
	public static function r_tonghop_coso( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		global $wpdb;
		$tu = self::vn2ymd( (string) $req->get_param( 'tuNgay' ) ); $den = self::vn2ymd( (string) $req->get_param( 'denNgay' ) );
		$mapTen = self::map_ten_diem();
		// (1) Nộp trực tiếp: bank tiền vào có mã, KHÔNG phải cục cổng
		$tbl = self::tbl();
		$wb = array( "loai='in'" ); $ab = array();
		if ( $tu )  { $wb[] = 'DATE(ngay_gd)>=%s'; $ab[] = $tu; }
		if ( $den ) { $wb[] = 'DATE(ngay_gd)<=%s'; $ab[] = $den; }
		$sqlB = "SELECT ngay_gd, so_tk, tien, noi_dung FROM $tbl WHERE " . implode( ' AND ', $wb ) . " ORDER BY id DESC LIMIT 30000";
		$rowsB = $ab ? $wpdb->get_results( $wpdb->prepare( $sqlB, $ab ), ARRAY_A ) : $wpdb->get_results( $sqlB, ARRAY_A );
		$nop = array(); $khongRoMaNop = array(); $tongNop = 0;
		foreach ( (array) $rowsB as $r ) {
			$vao = (int) $r['tien']; if ( $vao <= 0 ) { continue; }
			if ( '' !== self::nguon_tien_dong( $r['noi_dung'] ) ) { continue; } // cục cổng -> để cột cổng
			$tongNop += $vao;
			$p = self::tach_ma_nop( $r['noi_dung'] );
			if ( ! $p ) { if ( count( $khongRoMaNop ) < 200 ) { $khongRoMaNop[] = array( 'ngayGD' => self::ymd2vn( $r['ngay_gd'] ), 'soTK' => $r['so_tk'], 'noiDung' => $r['noi_dung'], 'vao' => $vao ); } continue; }
			if ( ! isset( $nop[ $p['ma'] ] ) ) { $nop[ $p['ma'] ] = 0; }
			$nop[ $p['ma'] ] += $vao;
		}
		// (2) Từng cổng theo mã
		$cong = array(); $congChuaGan = array(); $tongTheoCong = array();
		foreach ( self::cong_ds() as $n ) {
			$res = self::tien_cong_theo_ma( $n, $tu, $den, $mapTen );
			$cong[ $n ] = $res['theoMa']; $tongTheoCong[ $n ] = $res['tong'];
			if ( count( $res['chuaGan'] ) || $res['chuaRoMay'] ) { $congChuaGan[] = array( 'nguon' => $n, 'ten' => self::cong_ten()[ $n ], 'ds' => $res['chuaGan'], 'chuaRoMay' => $res['chuaRoMay'], 'chuaRoTien' => $res['chuaRoTien'] ); }
		}
		// (3) 4 nhóm
		$grp = array();
		foreach ( array( 'MTD', 'KVC' ) as $ht ) { foreach ( array( 'MB', 'MN' ) as $mi ) {
			$grp[ $ht . $mi ] = array( 'khoa' => $ht . $mi, 'heThong' => $ht, 'mien' => $mi, 'ten' => $ht . ' · ' . self::ten_mien( $mi ),
				'diem' => array(), 'tongDiem' => 0, 'soCoTien' => 0, 'soKhongTien' => 0, 'tong' => 0, 'tongNop' => 0, 'tongVietqr' => 0, 'tongMomo' => 0, 'tongVnpay' => 0 );
		} }
		$coTrongDs = array();
		foreach ( self::ds_diem() as $dm ) {
			$k = ( isset( $dm['heThong'] ) ? $dm['heThong'] : '' ) . ( isset( $dm['mien'] ) ? $dm['mien'] : '' );
			if ( ! isset( $grp[ $k ] ) ) { continue; }
			$coTrongDs[ $dm['ma'] ] = 1;
			$o = array( 'ma' => $dm['ma'], 'ten' => isset( $dm['ten'] ) ? $dm['ten'] : '', 'phapNhan' => $dm['phapNhan'], 'tenPhapNhan' => self::ten_phap_nhan( $dm['phapNhan'] ),
				'nop' => isset( $nop[ $dm['ma'] ] ) ? $nop[ $dm['ma'] ] : 0, 'vietqr' => 0, 'momo' => 0, 'vnpay' => 0, 'tong' => 0 );
			foreach ( self::cong_ds() as $n ) { if ( isset( $cong[ $n ][ $dm['ma'] ] ) ) { $o[ $n ] = $cong[ $n ][ $dm['ma'] ]; } }
			$o['tong'] = $o['nop'] + $o['vietqr'] + $o['momo'] + $o['vnpay'];
			$o['coTien'] = $o['tong'] > 0;
			$grp[ $k ]['diem'][] = $o; $grp[ $k ]['tongDiem']++;
			$grp[ $k ]['tong'] += $o['tong']; $grp[ $k ]['tongNop'] += $o['nop'];
			$grp[ $k ]['tongVietqr'] += $o['vietqr']; $grp[ $k ]['tongMomo'] += $o['momo']; $grp[ $k ]['tongVnpay'] += $o['vnpay'];
			if ( $o['coTien'] ) { $grp[ $k ]['soCoTien']++; } else { $grp[ $k ]['soKhongTien']++; }
		}
		foreach ( $grp as &$g ) { usort( $g['diem'], function ( $a, $b ) { if ( $a['coTien'] !== $b['coTien'] ) { return $a['coTien'] ? -1 : 1; } if ( $b['tong'] !== $a['tong'] ) { return $b['tong'] - $a['tong']; } return strcmp( $a['ma'], $b['ma'] ); } ); }
		unset( $g );
		$maLa = array();
		foreach ( $nop as $mm => $v ) { if ( empty( $coTrongDs[ $mm ] ) ) { $maLa[ $mm ] = ( isset( $maLa[ $mm ] ) ? $maLa[ $mm ] : 0 ) + $v; } }
		foreach ( self::cong_ds() as $n ) { foreach ( $cong[ $n ] as $mm => $v ) { if ( empty( $coTrongDs[ $mm ] ) ) { $maLa[ $mm ] = ( isset( $maLa[ $mm ] ) ? $maLa[ $mm ] : 0 ) + $v; } } }
		$maLaOut = array(); foreach ( $maLa as $mm => $v ) { $maLaOut[] = array( 'ma' => $mm, 'soTien' => $v ); }
		$nhom = array_values( $grp );
		return array( 'ok' => true, 'tuNgay' => (string) $req->get_param( 'tuNgay' ), 'denNgay' => (string) $req->get_param( 'denNgay' ),
			'nhom' => $nhom, 'tongDiem' => count( self::ds_diem() ), 'tenCong' => self::cong_ten(),
			'soCoTien' => array_sum( array_map( function ( $g ) { return $g['soCoTien']; }, $nhom ) ),
			'soKhongTien' => array_sum( array_map( function ( $g ) { return $g['soKhongTien']; }, $nhom ) ),
			'tongGanDuoc' => array_sum( array_map( function ( $g ) { return $g['tong']; }, $nhom ) ),
			'tongNopTrucTiep' => $tongNop, 'tongTheoCong' => $tongTheoCong,
			'khongRoMaNop' => $khongRoMaNop, 'tongKhongRoMaNop' => array_sum( array_map( function ( $x ) { return $x['vao']; }, $khongRoMaNop ) ),
			'congChuaGanMa' => $congChuaGan, 'maLa' => $maLaOut );
	}
	/* Tiền 1 cổng gom theo mã nộp: đọc CongThanhToan (webhook) + DoiSoatFileCong (file). */
	private static function tien_cong_theo_ma( $nguon, $tu, $den, $mapTen ) {
		global $wpdb; $anhXa = self::ds_anhxa( $nguon );
		$theoMa = array(); $tong = 0; $chuaGan = array(); $viChuaGan = array(); $chuaRoMay = 0; $chuaRoTien = 0;
		// (a) CongThanhToan
		$tc = self::tbl_cong();
		$wa = array( 'nguon=%s', 'doc_duoc=1', "huong<>'Đi'" ); $aa = array( $nguon );
		if ( $tu )  { $wa[] = 'DATE(thoi_diem)>=%s'; $aa[] = $tu; }
		if ( $den ) { $wa[] = 'DATE(thoi_diem)<=%s'; $aa[] = $den; }
		$rc = $wpdb->get_results( $wpdb->prepare( "SELECT thoi_diem, so_tien, noi_dung, diem_ban FROM $tc WHERE " . implode( ' AND ', $wa ), $aa ), ARRAY_A );
		foreach ( (array) $rc as $r ) {
			$tien = (int) $r['so_tien']; if ( $tien <= 0 ) { continue; } $tong += $tien;
			$ngay = self::ymd2vn( $r['thoi_diem'] );
			$tenMay = self::cong_ten_may( $r['noi_dung'] ); if ( '' === $tenMay ) { $tenMay = strtoupper( (string) $r['diem_ban'] ); }
			if ( '' === $tenMay ) { $chuaRoMay++; $chuaRoTien += $tien; continue; }
			$ax = self::ax_theo_ngay( isset( $anhXa[ self::chuan_ch( $tenMay ) ] ) ? $anhXa[ self::chuan_ch( $tenMay ) ] : ( isset( $anhXa[ self::chuan_ch( self::cong_coso( $tenMay ) ) ] ) ? $anhXa[ self::chuan_ch( self::cong_coso( $tenMay ) ) ] : null ), $ngay );
			$suy = self::ax_ma_nop( $ax, $mapTen );
			if ( '' === $suy['ma'] ) { $c = self::cong_coso( $tenMay ); $chuaGan[ $c ] = ( isset( $chuaGan[ $c ] ) ? $chuaGan[ $c ] : 0 ) + $tien; $viChuaGan[ $c ] = $suy['vi']; continue; }
			$theoMa[ $suy['ma'] ] = ( isset( $theoMa[ $suy['ma'] ] ) ? $theoMa[ $suy['ma'] ] : 0 ) + $tien;
		}
		// (b) DoiSoatFileCong
		$tf = self::tbl_congfile();
		$wf = array( 'nguon=%s' ); $af = array( $nguon );
		if ( $tu )  { $wf[] = "STR_TO_DATE(ngay,'%%d/%%m/%%Y')>=%s"; $af[] = $tu; }
		if ( $den ) { $wf[] = "STR_TO_DATE(ngay,'%%d/%%m/%%Y')<=%s"; $af[] = $den; }
		$rf = $wpdb->get_results( $wpdb->prepare( "SELECT ngay, ch_file, ma_bank, so_tien FROM $tf WHERE " . implode( ' AND ', $wf ), $af ), ARRAY_A );
		foreach ( (array) $rf as $r ) {
			$tien = (int) $r['so_tien']; if ( $tien <= 0 ) { continue; } $tong += $tien;
			$ma = ''; $p = '' !== trim( (string) $r['ma_bank'] ) ? self::tach_ma_nop( $r['ma_bank'] ) : null;
			if ( $p ) { $ma = $p['ma']; } else {
				$ax = self::ax_theo_ngay( isset( $anhXa[ self::chuan_ch( $r['ch_file'] ) ] ) ? $anhXa[ self::chuan_ch( $r['ch_file'] ) ] : null, $r['ngay'] );
				$suy = self::ax_ma_nop( $ax, $mapTen ); $ma = $suy['ma']; $vi = $suy['vi'];
			}
			if ( '' === $ma ) { $c = '' !== $r['ch_file'] ? $r['ch_file'] : '(file không có tên cửa hàng)'; $chuaGan[ $c ] = ( isset( $chuaGan[ $c ] ) ? $chuaGan[ $c ] : 0 ) + $tien; $viChuaGan[ $c ] = '' !== $r['ch_file'] ? ( isset( $vi ) ? $vi : '' ) : 'dòng file không có tên cửa hàng — sửa cột đã chọn rồi nạp lại'; continue; }
			$theoMa[ $ma ] = ( isset( $theoMa[ $ma ] ) ? $theoMa[ $ma ] : 0 ) + $tien;
		}
		$chuaGanOut = array(); foreach ( $chuaGan as $t => $v ) { $chuaGanOut[] = array( 'ten' => $t, 'soTien' => $v, 'vi' => isset( $viChuaGan[ $t ] ) ? $viChuaGan[ $t ] : '' ); }
		return array( 'theoMa' => $theoMa, 'tong' => $tong, 'chuaGan' => $chuaGanOut, 'chuaRoMay' => $chuaRoMay, 'chuaRoTien' => $chuaRoTien );
	}

	// ───────────────────────────── Helpers ngày ─────────────────────────────
	private static function vn2ymd_soft( $s ) { $s = trim( $s ); if ( '' === $s ) { return ''; } if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m ) ) { return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ); } $t = strtotime( $s ); return $t ? gmdate( 'Y-m-d', $t ) : ''; }
	private static function ymd2vn_ngay( $ymd ) { $t = strtotime( $ymd ); return $t ? gmdate( 'd/m/Y', $t ) : ''; }
	private static function file_ngay( $s ) {
		$s = trim( (string) $s ); if ( '' === $s ) { return ''; }
		if ( preg_match( '#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})#', $s, $m ) ) { if ( (int) $m[3] <= 2000 ) { return ''; } return sprintf( '%02d/%02d/%s', (int) $m[1], (int) $m[2], $m[3] ); }
		if ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})#', $s, $m ) ) { if ( (int) $m[1] <= 2000 ) { return ''; } return $m[3] . '/' . $m[2] . '/' . $m[1]; }
		return '';
	}
	private static function vn2ymd( $s ) { $s = trim( $s ); if ( ! preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m ) ) { return ''; } return $m[3] . '-' . $m[2] . '-' . $m[1]; }
	private static function ymd2vn( $s ) { $s = trim( (string) $s ); if ( '' === $s ) { return ''; } $t = strtotime( $s ); return $t ? gmdate( 'd/m/Y H:i:s', $t ) : $s; }

	// ───────────────────────────── Helpers đối soát ─────────────────────────────
	/* Bỏ dấu tiếng Việt + thường hoá + gộp khoảng trắng — so tên/nội dung cho ổn định. */
	public static function kd( $s ) {
		$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
		$map = array(
			'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
			'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
			'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
			'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
			'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
			'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
		);
		$s = strtr( $s, $map );
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}
	/* Khoá so tên cửa hàng: bỏ dấu + bỏ mọi ký tự không phải chữ/số. */
	public static function chuan_ch( $s ) { return preg_replace( '/[^a-z0-9]/', '', self::kd( $s ) ); }

	public static function ma_chuan( $pn, $ht, $mien, $so ) {
		return 'KH' . $pn . strtoupper( $ht ) . strtoupper( $mien ) . substr( '0000' . (int) $so, -4 );
	}
	/* Tách mã nộp khỏi một đoạn chữ. Trả null nếu không có / số thứ tự = 0. */
	public static function tach_ma_nop( $s ) {
		if ( ! preg_match( self::RE_MA_NOP, (string) $s, $m ) ) { return null; }
		$so = (int) $m[4];
		if ( $so <= 0 ) { return null; }
		return array( 'ma' => self::ma_chuan( $m[1], $m[2], $m[3], $so ),
			'phapNhan' => $m[1], 'heThong' => strtoupper( $m[2] ), 'mien' => strtoupper( $m[3] ), 'so' => $so );
	}
	public static function ten_phap_nhan( $pn ) { return '705' === (string) $pn ? 'K&H mới (705)' : 'K&H cũ (989)'; }
	public static function ten_mien( $m ) { return 'MB' === $m ? 'Miền Bắc' : 'Miền Nam'; }

	private static function cong_tukhoa( $nguon ) {
		$o = get_option( 'saoke_cong_tukhoa' ); $o = is_array( $o ) ? $o : array();
		$md = self::cong_tukhoa_mac_dinh();
		$v = isset( $o[ $nguon ] ) ? trim( (string) $o[ $nguon ] ) : '';
		return '' !== $v ? $v : ( isset( $md[ $nguon ] ) ? $md[ $nguon ] : '' );
	}
	/* '' nếu là tiền ngân hàng (nộp trực tiếp); mã cổng nếu nội dung khớp từ khoá cổng. */
	private static function nguon_tien_dong( $noi_dung ) {
		$nd = self::kd( $noi_dung ); if ( '' === $nd ) { return ''; }
		foreach ( self::cong_ds() as $n ) {
			$kw = self::kd( self::cong_tukhoa( $n ) );
			if ( '' !== $kw && false !== strpos( $nd, $kw ) ) { return $n; }
		}
		return '';
	}
	/* Tên máy trong nội dung Việt QR: "VQR... AMTP 12" -> "AMTP 12". */
	private static function cong_ten_may( $noi_dung ) {
		$s = trim( preg_replace( '/^VQR\S*\s+/i', '', trim( (string) $noi_dung ) ) );
		if ( '' === $s || preg_match( '/^payment\s*for\s*order$/i', $s ) ) { return ''; }
		return preg_replace( '/\s+/', ' ', strtoupper( $s ) );
	}
	/* Cơ sở = tên máy bỏ số máy cuối: "AMTP 12" -> "AMTP". */
	private static function cong_coso( $ten_may ) {
		$s = trim( (string) $ten_may );
		return preg_match( '/^(.*?)\s*[0-9]+$/', $s, $m ) && '' !== trim( $m[1] ) ? trim( $m[1] ) : $s;
	}

	private static function ds_diem() { $v = get_option( 'saoke_diem' ); return is_array( $v ) ? $v : array(); }

	/* Ánh xạ cửa hàng cổng -> mã nộp, gom theo chuan_ch(tenFile), mỗi khoá là mảng dòng
	   (nhiều dòng = chuyển gian theo ngày), sắp theo Từ ngày. */
	private static function ds_anhxa( $nguon ) {
		$all = get_option( 'saoke_anhxa' ); $all = is_array( $all ) ? $all : array();
		$out = array();
		foreach ( $all as $r ) {
			if ( strtolower( (string) ( isset( $r['nguon'] ) ? $r['nguon'] : '' ) ) !== $nguon ) { continue; }
			$k = self::chuan_ch( isset( $r['tenFile'] ) ? $r['tenFile'] : '' );
			if ( '' === $k ) { continue; }
			if ( ! isset( $out[ $k ] ) ) { $out[ $k ] = array(); }
			$out[ $k ][] = array(
				'tenFile' => (string) ( isset( $r['tenFile'] ) ? $r['tenFile'] : '' ),
				'tenChuan' => (string) ( isset( $r['tenChuan'] ) ? $r['tenChuan'] : '' ),
				'maBank' => (string) ( isset( $r['maBank'] ) ? $r['maBank'] : '' ),
				'tuNgay' => (string) ( isset( $r['tuNgay'] ) ? $r['tuNgay'] : '' ),
				'denNgay' => (string) ( isset( $r['denNgay'] ) ? $r['denNgay'] : '' ),
			);
		}
		foreach ( $out as &$ds ) {
			usort( $ds, function ( $a, $b ) { return self::moc( $a['tuNgay'] ) - self::moc( $b['tuNgay'] ); } );
		}
		unset( $ds );
		return $out;
	}
	/* mốc thời gian (giây) từ 'dd/mm/yyyy [HH:MM[:SS]]'. 0 nếu rỗng. */
	private static function moc( $s ) {
		$s = trim( (string) $s ); if ( '' === $s ) { return 0; }
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?#', $s, $m ) ) {
			return (int) mktime( (int) ( isset( $m[4] ) ? $m[4] : 0 ), (int) ( isset( $m[5] ) ? $m[5] : 0 ), (int) ( isset( $m[6] ) ? $m[6] : 0 ), (int) $m[2], (int) $m[1], (int) $m[3] );
		}
		$t = strtotime( $s ); return $t ? $t : 0;
	}
	/* Chọn dòng ánh xạ có hiệu lực cho NGÀY (dd/mm/yyyy). Trống Từ/Đến = vô hạn phía đó.
	   Không có ngày -> lấy dòng mới nhất. Có ngày mà không khoảng nào phủ -> null. */
	private static function ax_theo_ngay( $ds, $ngay ) {
		if ( ! $ds || ! count( $ds ) ) { return null; }
		$m = $ngay ? self::moc( $ngay ) : 0;
		if ( $m ) {
			for ( $i = count( $ds ) - 1; $i >= 0; $i-- ) {
				$x = $ds[ $i ];
				if ( '' !== $x['tuNgay'] && $m < self::moc( $x['tuNgay'] ) ) { continue; }
				if ( '' !== $x['denNgay'] && $m > self::moc( $x['denNgay'] ) + 86399 ) { continue; }
				return $x;
			}
			return null;
		}
		return $ds[ count( $ds ) - 1 ];
	}
	/* Mã nộp của 1 dòng ánh xạ: ưu tiên ô Mã bank; nếu rỗng, suy từ tên chuẩn qua danh sách điểm.
	   Trả ['ma'=>..,'vi'=>lý do khi rỗng]. */
	private static function ax_ma_nop( $ax, $map_ten ) {
		if ( ! $ax ) { return array( 'ma' => '', 'vi' => 'chưa có dòng ánh xạ cho tên này' ); }
		$p = '' !== trim( (string) $ax['maBank'] ) ? self::tach_ma_nop( $ax['maBank'] ) : null;
		if ( $p ) { return array( 'ma' => $p['ma'], 'vi' => '' ); }
		if ( '' !== trim( (string) $ax['maBank'] ) ) { return array( 'ma' => '', 'vi' => 'ô Mã bank "' . $ax['maBank'] . '" không đúng dạng (vd KH705MTDMN0002)' ); }
		$tc = trim( (string) $ax['tenChuan'] );
		if ( '' === $tc ) { return array( 'ma' => '', 'vi' => 'chưa điền Cửa hàng chuẩn, cũng chưa điền Mã bank' ); }
		$k = self::chuan_ch( $tc );
		if ( ! isset( $map_ten[ $k ] ) ) { return array( 'ma' => '', 'vi' => 'tên "' . $tc . '" không có trong danh sách điểm' ); }
		if ( ! empty( $map_ten[ $k ]['trung'] ) ) { return array( 'ma' => '', 'vi' => 'tên "' . $tc . '" bị nhiều điểm dùng chung — phải điền Mã bank' ); }
		return array( 'ma' => $map_ten[ $k ]['ma'], 'vi' => '' );
	}
	/* map chuan_ch(tên điểm) -> ['ma'=>..,'trung'=>bool] để suy mã từ tên chuẩn. */
	private static function map_ten_diem() {
		$map = array();
		foreach ( self::ds_diem() as $d ) {
			$ten = isset( $d['ten'] ) ? $d['ten'] : ''; $ma = isset( $d['ma'] ) ? $d['ma'] : '';
			$k = self::chuan_ch( $ten );
			if ( '' === $k || '' === $ma ) { continue; }
			if ( isset( $map[ $k ] ) ) { if ( $map[ $k ]['ma'] !== $ma ) { $map[ $k ]['trung'] = true; } continue; }
			$map[ $k ] = array( 'ma' => $ma, 'trung' => false );
		}
		return $map;
	}

	// ───────────────────────────── WP Admin (đặt PIN/khoá) ─────────────────────────────
	public static function admin_menu() {
		add_menu_page( 'Sao Kê SePay', 'Sao Kê SePay', 'manage_options', 'saoke-sepay', array( __CLASS__, 'trang_admin' ), 'dashicons-bank', 28 );
	}
	public static function trang_admin() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_POST['saoke_luu'] ) && check_admin_referer( 'saoke_cfg' ) ) {
			$pin = preg_replace( '/\D+/', '', (string) $_POST['pin'] );
			if ( '' !== $pin && ( strlen( $pin ) < 4 || strlen( $pin ) > 8 ) ) { echo '<div class="notice notice-error"><p>PIN phải 4-8 số.</p></div>'; }
			else {
				if ( '' !== $pin ) { update_option( 'saoke_pin', $pin ); }
				update_option( 'saoke_webhook_key', sanitize_text_field( wp_unslash( $_POST['key'] ) ) );
				update_option( 'saoke_api_token', sanitize_text_field( wp_unslash( $_POST['token'] ) ) );
				echo '<div class="notice notice-success"><p>Đã lưu.</p></div>';
			}
		}
		$key = (string) get_option( 'saoke_webhook_key', '' );
		$url = esc_url_raw( rest_url( self::NS . '/webhook' ) ) . ( $key ? ( '?key=' . rawurlencode( $key ) ) : '' );
		$page = get_option( 'saoke_page_id' ) ? get_permalink( (int) get_option( 'saoke_page_id' ) ) : '';
		echo '<div class="wrap"><h1>Sao Kê Ngân Hàng (SePay)</h1>';
		if ( $page ) { echo '<p><b>Trang sao kê:</b> <a href="' . esc_url( $page ) . '" target="_blank">' . esc_html( $page ) . '</a> — gửi link này + PIN cho kế toán.</p>'; }
		echo '<form method="post"><table class="form-table">';
		wp_nonce_field( 'saoke_cfg' );
		echo '<tr><th>Mã PIN (4-8 số)</th><td><input name="pin" class="regular-text" placeholder="' . ( get_option( 'saoke_pin', '' ) ? 'đã đặt — nhập để đổi' : 'chưa đặt' ) . '"> <span class="description">Cửa vào trang sao kê. Không nhập = giữ nguyên.</span></td></tr>';
		echo '<tr><th>Webhook API Key</th><td><input name="key" class="regular-text code" value="' . esc_attr( $key ) . '" placeholder="đặt key bí mật"></td></tr>';
		echo '<tr><th>SePay Open API Token</th><td><input name="token" class="regular-text code" value="' . esc_attr( get_option( 'saoke_api_token', '' ) ) . '" placeholder="token từ SePay"></td></tr>';
		echo '<tr><th>Webhook URL (khai bên SePay)</th><td><code>' . esc_html( $key ? $url : '(đặt Webhook API Key rồi lưu)' ) . '</code><br><span class="description">SePay → Webhooks → Endpoint URL. Authentication chọn <b>No Authentication</b> (key đã nằm trong URL).</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="saoke_luu" value="1">Lưu</button></p></form>';
		echo '<p class="description">⚠️ Repo công khai — PIN/khoá lưu trong DB, không nằm trong mã nguồn.</p></div>';
	}

	// ───────────────────────────── Frontend SPA ─────────────────────────────
	public static function shortcode() {
		$rest = esc_url_raw( rest_url( self::NS ) );
		ob_start(); ?>
<style id="skp-an-theme">
/* Ẩn header/footer/tiêu đề của theme để trang sao kê chiếm trọn màn */
.wp-site-blocks > header.wp-block-template-part, .wp-site-blocks > footer.wp-block-template-part,
header.wp-block-template-part, footer.wp-block-template-part,
#masthead, #colophon, .site-header, .site-footer, .wp-block-site-title,
.wp-block-post-title, .entry-header, header.entry-header { display:none !important; }
#wpadminbar { display:none !important; }
html { margin-top:0 !important; }
* html body { margin-top:0 !important; }
body { margin:0 !important; padding:0 !important; background:#0b1220 !important; }
/* Ép nội dung tràn full màn — bỏ giới hạn chiều rộng, lề & padding của theme */
.wp-site-blocks, .wp-site-blocks > *, .entry-content, .wp-block-group, main, .site-main, .content-area,
.wp-block-post-content, article, .page, .type-page, .hentry,
.is-layout-constrained, .is-layout-flow, .is-layout-constrained > *,
.wp-block-post-content > *, .entry-content > *, .alignwide, .alignfull {
	max-width:none !important; width:auto !important;
	margin-left:0 !important; margin-right:0 !important;
	padding-left:0 !important; padding-right:0 !important;
	margin-top:0 !important; padding-top:0 !important;
}
html, body, .wp-site-blocks, .entry-content, .wp-block-post-content, main, article { overflow-x:clip !important; }
/* Full-bleed: phá khung ra sát mép dù cha căn giữa */
.skp { position:relative !important; left:50% !important; right:50% !important;
	margin-left:-50vw !important; margin-right:-50vw !important; width:100vw !important; max-width:100vw !important; }
.skp .sk-main { max-width:none !important; }
</style>
<div class="skp" data-rest="<?php echo esc_attr( $rest ); ?>">
	<div id="skPin" class="sk-pinbox">
		<h2>🔒 Sao Kê Ngân Hàng K&amp;H</h2>
		<p class="sk-mut">Dữ liệu tài chính — nhập mã PIN</p>
		<input id="skPinIn" type="password" inputmode="numeric" placeholder="••••••" maxlength="8">
		<button class="sk-btn" style="width:100%" onclick="skLogin()">Mở</button>
		<div id="skPinMsg" class="sk-err"></div>
	</div>
	<div id="skApp" class="sk-wrap" hidden>
		<aside class="sk-side">
			<div class="sk-brand">🏦 Sao Kê Ngân Hàng</div>
			<div class="sk-brandphu">K&amp;H COM.,LTD</div>
			<button class="sk-ni on" data-v="dash" onclick="skNav('dash')">📊 Tổng quan</button>
			<button class="sk-ni" data-v="sk" onclick="skNav('sk')">🏦 Sao Kê Ngân Hàng</button>
			<button class="sk-ni" data-v="dc" onclick="skNav('dc')">✅ Đối chiếu nộp tiền</button>
			<button class="sk-ni" data-v="cong" onclick="skNav('cong')">💳 Sao kê cổng QR</button>
			<button class="sk-ni" data-v="cs" onclick="skNav('cs')">🏬 Doanh thu cơ sở</button>
			<button class="sk-ni" data-v="diem" onclick="skNav('diem')">📍 Điểm nộp</button>
			<button class="sk-ni" data-v="tk" onclick="skNav('tk')">🏛️ Tài khoản</button>
			<button class="sk-ni" data-v="dm" onclick="skNav('dm')">🏷️ Danh mục chi phí</button>
			<button class="sk-ni" data-v="cfg" onclick="skNav('cfg')">⚙️ Cấu hình</button>
			<button class="sk-ni sk-khoa" onclick="skKhoa()">🔒 Khoá lại</button>
		</aside>
		<main class="sk-main">
			<!-- Tổng quan -->
			<section class="sk-view on" data-view="dash">
				<h2>Tổng quan nhiều tài khoản <button class="sk-btn sk-gray" onclick="skDash()">🔄 Làm mới</button></h2>
				<div class="sk-cards" id="skDashCards"></div>
				<div class="sk-panel"><h3>Số dư từng tài khoản</h3>
					<div class="sk-scroll"><table><thead><tr><th>Số TK</th><th>Ngân hàng</th><th>Chủ TK</th><th>Pháp nhân</th><th>Đầu kỳ</th><th>Số dư thực tế</th><th>Số dư tính toán</th><th>Chênh lệch</th></tr></thead><tbody id="skDashTK"></tbody></table></div></div>
				<div class="sk-panel"><h3>Thu / Chi 12 tháng</h3>
					<div class="sk-scroll"><table><thead><tr><th>Tháng</th><th>Tổng thu</th><th>Tổng chi</th><th>Chênh lệch</th></tr></thead><tbody id="skDashThang"></tbody></table></div></div>
				<div class="sk-panel"><h3>Chi phí theo nhóm nhãn</h3>
					<table><thead><tr><th>Nhóm</th><th>Tổng chi</th></tr></thead><tbody id="skDashNhom"></tbody></table></div>
			</section>
			<!-- Sao kê -->
			<section class="sk-view" data-view="sk">
				<h2>Sao Kê Ngân Hàng</h2>
				<div class="sk-panel sk-row">
					<div class="sk-fld"><label>Tài khoản</label><select id="fTK"><option value="">Tất cả</option></select></div>
					<div class="sk-fld"><label>Pháp nhân</label><select id="fPN"><option value="">Tất cả</option></select></div>
					<div class="sk-fld"><label>Từ ngày</label><input type="date" id="fTu"></div>
					<div class="sk-fld"><label>Đến ngày</label><input type="date" id="fDen"></div>
					<div class="sk-fld"><label>Loại</label><select id="fLoai"><option value="">Tất cả</option><option value="Thu">Tiền vào</option><option value="Chi">Tiền ra</option></select></div>
					<div class="sk-fld"><label>Nhãn</label><select id="fNhan"><option value="">Tất cả</option><option value="CHUA_PHAN_LOAI">Chưa phân loại</option></select></div>
					<div class="sk-fld"><label>Từ khoá</label><input type="text" id="fKw" placeholder="nội dung..."></div>
					<div class="sk-fld"><button class="sk-btn" onclick="skTai()">🔍 Lọc</button></div>
					<div class="sk-fld"><button class="sk-btn sk-gray" onclick="skLamMoi()">🔄 Làm mới</button></div>
					<div class="sk-fld" style="align-self:flex-end"><span class="sk-mut" id="skCapNhat"></span></div>
				</div>
				<div class="sk-cards" style="grid-template-columns:repeat(4,1fr)">
					<div class="sk-card"><div class="sk-lbl">Tổng tiền vào</div><div class="sk-val sk-in" id="skVao">0</div></div>
					<div class="sk-card"><div class="sk-lbl">Tổng tiền ra</div><div class="sk-val sk-out" id="skRa">0</div></div>
					<div class="sk-card"><div class="sk-lbl">Chênh lệch</div><div class="sk-val" id="skChenh">0</div></div>
					<div class="sk-card"><div class="sk-lbl">Số dòng</div><div class="sk-val" id="skDong">0</div></div>
				</div>
				<div class="sk-panel sk-scroll">
					<table><thead><tr><th>Ngày GD</th><th>Ngân hàng</th><th>Số TK</th><th>Pháp nhân</th><th>Nội dung</th><th>Mã GD</th><th>Tiền vào</th><th>Tiền ra</th><th>Số dư</th><th>Nhãn</th><th>Nguồn</th></tr></thead><tbody id="skBody"></tbody></table>
				</div>
				<div class="sk-row" style="justify-content:center;align-items:center">
					<button class="sk-btn sk-gray" onclick="skTrang(-1)">‹ Trước</button>
					<span id="skPageLbl" class="sk-mut" style="margin:0 10px">Trang 1</span>
					<button class="sk-btn sk-gray" onclick="skTrang(1)">Sau ›</button>
				</div>
			</section>
			<!-- Tài khoản -->
			<section class="sk-view" data-view="tk">
				<h2>Danh mục tài khoản ngân hàng</h2>
				<div class="sk-panel sk-row">
					<div class="sk-fld"><label>Số TK</label><input id="tkSo"></div>
					<div class="sk-fld"><label>Ngân hàng</label><input id="tkNH"></div>
					<div class="sk-fld"><label>Chủ TK</label><input id="tkChu"></div>
					<div class="sk-fld"><label>Ghi chú</label><input id="tkGhi"></div>
					<div class="sk-fld"><label>Pháp nhân</label><input id="tkPN"></div>
					<div class="sk-fld"><label>Số dư đầu kỳ</label><input id="tkDK" type="number"></div>
					<div class="sk-fld"><label>Ngày đầu kỳ</label><input id="tkNgayDK" type="date"></div>
					<div class="sk-fld"><button class="sk-btn" onclick="skLuuTK()">+ Lưu</button></div>
				</div>
				<div class="sk-panel sk-scroll"><table><thead><tr><th>Số TK</th><th>Ngân hàng</th><th>Chủ TK</th><th>Ghi chú</th><th>Pháp nhân</th><th>Đầu kỳ</th><th></th></tr></thead><tbody id="tkBody"></tbody></table></div>
			</section>
			<!-- Danh mục -->
			<section class="sk-view" data-view="dm">
				<h2>Danh mục nhãn phân loại</h2>
				<div class="sk-panel sk-row">
					<div class="sk-fld"><label>Tên nhóm</label><input id="dmTen"></div>
					<div class="sk-fld"><label>Loại</label><select id="dmLoai"><option>Chi</option><option>Thu</option></select></div>
					<div class="sk-fld"><label>Màu</label><input id="dmMau" type="color" value="#3b82f6"></div>
					<div class="sk-fld"><button class="sk-btn" onclick="skLuuDM()">+ Lưu</button></div>
				</div>
				<div class="sk-panel"><table><thead><tr><th>Nhóm</th><th>Loại</th><th>Màu</th><th></th></tr></thead><tbody id="dmBody"></tbody></table></div>
			</section>
			<!-- Cấu hình -->
			<section class="sk-view" data-view="cfg">
				<h2>Cấu hình SePay</h2>
				<div class="sk-panel">
					<h3>Webhook thời gian thực</h3>
					<div class="sk-hint">Dán URL dưới vào SePay → Webhooks → Endpoint URL, Authentication = <b>No Authentication</b>. Đổi Webhook API Key ở WP Admin → Sao Kê SePay.</div>
					<div>URL: <code id="cfgUrl">(chưa có)</code></div>
					<div class="sk-row" style="margin-top:10px">
						<div class="sk-fld" style="flex:1"><label>Webhook API Key</label><input id="cfgKey"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skLuuCfg()">Lưu key</button></div>
					</div>
				</div>
				<div class="sk-panel">
					<h3>Kéo lịch sử qua SePay Open API</h3>
					<div class="sk-row">
						<div class="sk-fld" style="flex:1"><label>SePay API Token</label><input id="cfgToken"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skLuuCfg()">Lưu token</button></div>
					</div>
					<div class="sk-row" style="margin-top:10px">
						<div class="sk-fld"><label>Từ ngày</label><input type="date" id="syncTu"></div>
						<div class="sk-fld"><label>Đến ngày</label><input type="date" id="syncDen"></div>
						<div class="sk-fld"><label>Số TK (trống=tất cả)</label><input id="syncTK"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skSync()">⬇️ Đồng bộ</button></div>
					</div>
				</div>
				<div class="sk-panel">
					<h3>Đổi PIN</h3>
					<div class="sk-row">
						<div class="sk-fld"><label>PIN hiện tại</label><input id="pinCu" type="password"></div>
						<div class="sk-fld"><label>PIN mới</label><input id="pinMoi" type="password"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skDoiPin()">Đổi PIN</button></div>
					</div>
				</div>
			</section>
				<!-- Đối chiếu nộp tiền -->
				<section class="sk-view" data-view="dc">
					<h2>Đối chiếu nộp tiền theo điểm</h2>
					<div class="sk-panel sk-row">
						<div class="sk-fld"><label>Từ ngày</label><input type="date" id="dcTu"></div>
						<div class="sk-fld"><label>Đến ngày</label><input type="date" id="dcDen"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skDC()">🔍 Đối chiếu</button></div>
						<div class="sk-fld" style="align-self:flex-end"><span class="sk-mut">Điểm nào có tiền vào mang mã của mình = đã nộp.</span></div>
					</div>
					<div class="sk-cards" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))" id="dcCards"></div>
					<div id="dcNhom"></div>
					<div class="sk-panel" id="dcCanhBao" hidden></div>
				</section>
				<!-- Sao kê cổng -->
				<section class="sk-view" data-view="cong">
					<h2>Sao kê cổng thanh toán</h2>
					<div class="sk-panel sk-row">
						<div class="sk-fld"><label>Cổng</label><select id="cgNguon" onchange="skCong()"><option value="vietqr">Việt QR</option><option value="momo">MoMo</option><option value="vnpay">VNPAY</option></select></div>
						<div class="sk-fld"><label>Từ ngày</label><input type="date" id="cgTu"></div>
						<div class="sk-fld"><label>Đến ngày</label><input type="date" id="cgDen"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skCong()">🔍 Xem</button></div>
					</div>
					<div class="sk-hint" id="cgHint"></div>
					<div class="sk-cards" style="grid-template-columns:repeat(4,1fr)">
						<div class="sk-card"><div class="sk-lbl">Tổng từ cổng</div><div class="sk-val sk-in" id="cgCong">0</div></div>
						<div class="sk-card"><div class="sk-lbl">Cục về bank (khớp từ khoá)</div><div class="sk-val" id="cgBank">0</div></div>
						<div class="sk-card"><div class="sk-lbl">Chênh lệch</div><div class="sk-val" id="cgChenh">0</div></div>
						<div class="sk-card"><div class="sk-lbl">Kiểu đối soát</div><div class="sk-val" id="cgKieu">–</div></div>
					</div>
					<div class="sk-panel sk-row" style="align-items:flex-end">
						<div class="sk-fld" style="flex:1"><label>Từ khoá nhận diện cục về bank</label><input id="cgKw" placeholder="VQR / MOMO / VNPAY"></div>
						<div class="sk-fld"><button class="sk-btn sk-gray" onclick="skLuuTuKhoa()">Lưu từ khoá</button></div>
					</div>
					<div class="sk-panel" id="cgFileBox" hidden>
						<h3>Nạp file kết xuất (MoMo/VNPAY không có webhook)</h3>
						<div class="sk-hint">MoMo/VNPAY không bắn webhook — tải file Excel kết xuất lên. Chọn đúng cột Ngày · Số tiền · Cửa hàng (và Mã cửa hàng nếu có). Cộng dồn theo ngày × cửa hàng, tải lại không nhân đôi.</div>
						<div class="sk-row" style="align-items:flex-end">
							<div class="sk-fld"><label>File .xlsx / .csv</label><input type="file" id="cgFile" accept=".xlsx,.xls,.csv"></div>
							<div class="sk-fld"><label>Cột Ngày</label><select id="cgCotNgay"></select></div>
							<div class="sk-fld"><label>Cột Số tiền</label><select id="cgCotTien"></select></div>
							<div class="sk-fld"><label>Cột Cửa hàng</label><select id="cgCotCH"></select></div>
							<div class="sk-fld"><label>Cột Mã CH (tuỳ)</label><select id="cgCotMa"></select></div>
							<div class="sk-fld"><label><input type="checkbox" id="cgGhiDe"> Ghi đè ngày trùng</label></div>
							<div class="sk-fld"><button class="sk-btn" onclick="skNapFile()">⬆️ Nạp</button></div>
						</div>
						<div class="sk-mut" id="cgFileMsg" style="margin-top:8px"></div>
					</div>
					<div class="sk-panel">
						<h3>Ánh xạ cửa hàng → mã nộp <span class="sk-mut">(cần cho MoMo/VNPAY để gom về cơ sở)</span></h3>
						<div class="sk-row" style="align-items:flex-end">
							<div class="sk-fld"><label>Tên ở file/nội dung cổng</label><input id="axTen"></div>
							<div class="sk-fld"><label>Cửa hàng chuẩn (tên điểm)</label><input id="axChuan" list="axDsDiem"></div>
							<div class="sk-fld"><label>hoặc Mã nộp</label><input id="axMa" placeholder="KH705MTDMN0002"></div>
							<div class="sk-fld"><label>Từ ngày (tuỳ)</label><input type="date" id="axTu"></div>
							<div class="sk-fld"><label>Đến ngày (tuỳ)</label><input type="date" id="axDen"></div>
							<div class="sk-fld"><button class="sk-btn" onclick="skLuuAnhXa()">+ Lưu ánh xạ</button></div>
						</div>
						<datalist id="axDsDiem"></datalist>
					</div>
					<div class="sk-panel sk-scroll">
						<h3>Chi tiết giao dịch cổng</h3>
						<table><thead><tr><th>Thời điểm</th><th>Số tiền</th><th>Mã GD</th><th>Tham chiếu</th><th>Máy/Cơ sở</th><th>Nội dung</th></tr></thead><tbody id="cgBody"></tbody></table>
					</div>
				</section>
				<!-- Doanh thu cơ sở -->
				<section class="sk-view" data-view="cs">
					<h2>Tổng hợp doanh thu cơ sở</h2>
					<div class="sk-panel sk-row">
						<div class="sk-fld"><label>Từ ngày</label><input type="date" id="csTu"></div>
						<div class="sk-fld"><label>Đến ngày</label><input type="date" id="csDen"></div>
						<div class="sk-fld"><button class="sk-btn" onclick="skCS()">🔍 Tổng hợp</button></div>
						<div class="sk-fld" style="align-self:flex-end"><span class="sk-mut">Nộp trực tiếp + Việt QR + MoMo + VNPAY theo từng điểm.</span></div>
					</div>
					<div class="sk-cards" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))" id="csCards"></div>
					<div id="csNhom"></div>
					<div class="sk-panel" id="csCanhBao" hidden></div>
				</section>
				<!-- Điểm nộp -->
				<section class="sk-view" data-view="diem">
					<h2>Danh sách điểm nộp <span class="sk-mut" id="diemDem"></span></h2>
					<div class="sk-panel">
						<h3>Nhập danh sách điểm</h3>
						<div class="sk-hint">Dán mỗi dòng gồm <b>mã nộp</b> (KH705MTDMN0002…) và <b>tên điểm</b>. Ví dụ: <code>KH705MTDMN0002 Funzone Aeon Long Biên</code>. Mã tự mã hoá pháp nhân/hệ thống/miền/số nên không cần khai gì thêm.</div>
						<textarea id="diemTxt" rows="7" style="width:100%;background:var(--oNhap);border:1px solid var(--line);color:var(--txt);border-radius:6px;padding:10px;font-size:13px" placeholder="KH705MTDMN0002 Funzone Aeon Long Biên&#10;KH989KVCMN0010 KVC Cần Thơ"></textarea>
						<div class="sk-row" style="margin-top:10px">
							<label class="sk-mut"><input type="checkbox" id="diemThay" checked> Thay toàn bộ danh sách (bỏ chọn = gộp thêm)</label>
							<button class="sk-btn" onclick="skNhapDiem()">⬆️ Nhập</button>
							<button class="sk-btn sk-gray" onclick="skXoaHetDiem()">🗑️ Xoá hết</button>
						</div>
						<div class="sk-mut" id="diemMsg" style="margin-top:8px"></div>
					</div>
					<div class="sk-panel sk-scroll"><table><thead><tr><th>Mã nộp</th><th>Tên điểm</th><th>Pháp nhân</th><th>Hệ thống</th><th>Miền</th><th></th></tr></thead><tbody id="diemBody"></tbody></table></div>
				</section>
			</main>
	</div>
	<div class="sk-toast" id="skToast"></div>
</div>
<style>
.skp{--bg:#0b1220;--panel:#111c33;--panel2:#0f172a;--line:#1e293b;--txt:#e2e8f0;--mut:#94a3b8;--accent:#3b82f6;--green:#22c55e;--red:#ef4444;--gold:#d9ab24;--oNhap:#0d1526;--hover:#0f1a30;--niBg:#16213c;}
.skp{width:100vw;margin-left:calc(50% - 50vw);min-height:100vh;background:var(--bg);color:var(--txt);font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;box-sizing:border-box;}
.skp *{box-sizing:border-box;}
.sk-pinbox{max-width:320px;margin:14vh auto;text-align:center;padding:0 16px;}
.sk-pinbox h2{color:var(--gold);}
.sk-pinbox input{width:100%;text-align:center;font-size:22px;letter-spacing:6px;margin:14px 0;background:var(--oNhap);border:1px solid var(--line);color:var(--txt);border-radius:8px;padding:10px;}
.sk-wrap{display:flex;min-height:100vh;}
.sk-side{width:230px;flex-shrink:0;background:var(--panel2);border-right:1px solid var(--line);padding:14px 10px;}
.sk-brand{color:var(--gold);font-weight:800;font-size:15px;padding:0 6px;}
.sk-brandphu{color:var(--mut);font-size:11px;letter-spacing:1.3px;text-transform:uppercase;padding:0 6px;margin:2px 0 12px;}
.sk-ni{display:block;width:100%;text-align:left;border:none;background:transparent;color:var(--mut);padding:10px 12px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;}
.sk-ni:hover{background:var(--niBg);color:var(--txt);}
.sk-ni.on{background:var(--niBg);color:var(--accent);}
.sk-khoa{margin-top:18px;color:#f0a0a0;}
.sk-main{flex:1;padding:20px 26px;max-width:1400px;overflow-x:auto;}
.sk-view{display:none;} .sk-view.on{display:block;}
.sk-main h2{font-size:20px;margin:0 0 16px;}
.sk-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px;}
.sk-card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:14px;}
.sk-lbl{color:var(--mut);font-size:12px;margin-bottom:6px;}
.sk-val{font-size:21px;font-weight:800;} .sk-in{color:var(--green);} .sk-out{color:var(--red);}
.sk-panel{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:14px;}
.sk-panel h3{margin:0 0 10px;font-size:15px;}
.sk-scroll{overflow-x:auto;}
.sk-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.sk-fld{display:flex;flex-direction:column;gap:4px;} .sk-fld label{font-size:12px;color:var(--mut);}
.skp input,.skp select{background:var(--oNhap);border:1px solid var(--line);color:var(--txt);border-radius:6px;padding:8px 10px;font-size:13px;}
.sk-btn{cursor:pointer;background:var(--accent);border:1px solid var(--accent);color:#fff;font-weight:700;border-radius:6px;padding:9px 14px;font-size:13px;}
.sk-btn.sk-gray{background:#334155;border-color:#334155;}
.sk-btn:hover{opacity:.9;}
.skp table{width:100%;border-collapse:collapse;font-size:13px;}
.skp th,.skp td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap;}
.skp th{color:var(--mut);font-weight:600;}
.skp tr:hover td{background:var(--hover);}
.sk-mut{color:var(--mut);} .sk-err{color:#f0a0a0;font-size:13px;margin-top:8px;}
.sk-hint{background:#132140;border:1px dashed #2c3e63;border-radius:8px;padding:10px 12px;font-size:12px;color:var(--mut);margin-bottom:10px;}
.skp code{background:var(--oNhap);padding:2px 6px;border-radius:4px;color:#93c5fd;word-break:break-all;}
.sk-toast{position:fixed;bottom:20px;right:20px;background:var(--panel);border:1px solid var(--line);padding:12px 18px;border-radius:8px;display:none;z-index:999;}
.sk-toast.err{border-color:var(--red);color:#f0a0a0;} .sk-toast.ok{border-color:var(--green);color:var(--green);}
@media(max-width:820px){ .sk-wrap{flex-direction:column;} .sk-side{width:100%;display:flex;gap:6px;overflow-x:auto;border-right:none;border-bottom:1px solid var(--line);}
	.sk-brand,.sk-brandphu{display:none;} .sk-ni{width:auto;white-space:nowrap;flex:0 0 auto;} .sk-khoa{margin-top:0;} .sk-main{padding:14px 12px;} }
</style>
<script>
(function(){
	var root=document.querySelector('.skp'); if(!root) return;
	var REST=root.getAttribute('data-rest'), PIN='';
	var $=function(s){return root.querySelector(s);};
	var KEY='saoke_pin_sess';
	function fmt(n){ n=Math.round(Number(n)||0); return String(n).replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
	function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
	function toast(m,e){ var t=$('#skToast'); t.textContent=m; t.className='sk-toast '+(e?'err':'ok'); t.style.display='block'; setTimeout(function(){t.style.display='none';},3200); }
	function vn(d){ if(!d) return ''; var p=d.split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):d; }
	function post(path,body){ body=body||{}; body.pin=PIN; return fetch(REST+path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(rj); }
	function get(path){ var u=new URL(REST+path); u.searchParams.set('pin',PIN); return fetch(u.toString()).then(rj); }
	function rj(r){ return r.json().then(function(d){ if(!r.ok||d.ok===false) throw new Error(d&&(d.message||d.code)||'Lỗi'); return d; }); }

	window.skLogin=function(){ var v=($('#skPinIn').value||'').trim(); if(!v)return; $('#skPinMsg').textContent='Đang kiểm tra…'; PIN=v;
		post('/login',{}).then(function(){ try{sessionStorage.setItem(KEY,v);}catch(e){} vaoApp(); })
			.catch(function(e){ PIN=''; $('#skPinMsg').textContent=String(e.message||e); }); };
	$('#skPinIn').addEventListener('keydown',function(e){ if(e.key==='Enter') skLogin(); });
	window.skKhoa=function(){ try{sessionStorage.removeItem(KEY);}catch(e){} location.reload(); };

	function vaoApp(){ $('#skPin').hidden=true; $('#skPin').style.display='none'; $('#skApp').hidden=false; napCfg(function(){ skDash(); }); }
	(function(){ var p=''; try{p=sessionStorage.getItem(KEY)||'';}catch(e){} if(!p)return; PIN=p;
		post('/login',{}).then(vaoApp).catch(function(){ PIN=''; try{sessionStorage.removeItem(KEY);}catch(e){} }); })();

	window.skNav=function(v){
		root.querySelectorAll('.sk-view').forEach(function(x){x.classList.toggle('on',x.getAttribute('data-view')===v);});
		root.querySelectorAll('.sk-ni').forEach(function(x){x.classList.toggle('on',x.getAttribute('data-v')===v);});
		if(v==='dash')skDash(); if(v==='sk')skTai(); if(v==='tk')veTK(); if(v==='dm')veDM(); if(v==='cfg')veCfg();
		if(v==='dc')skDC(); if(v==='cong'){veTuKhoaFile();skCong();} if(v==='cs')skCS(); if(v==='diem')veDiem();
	};

	var CFG={taiKhoan:[],danhMuc:[]};
	function napCfg(cb){ get('/config').then(function(d){ CFG=d; fillSelects(); if(cb)cb(); }).catch(function(e){ toast(e.message||e,true); }); }
	function fillSelects(){
		var t=$('#fTK'); t.innerHTML='<option value="">Tất cả</option>'+(CFG.taiKhoan||[]).map(function(x){ var l=[x.nganHang,x.chuTK,x.ghiChu].filter(Boolean).join(' - ')||x.soTK; return '<option value="'+esc(x.soTK)+'">'+esc(l)+' ('+esc(x.soTK)+')</option>'; }).join('');
		var n=$('#fNhan'); n.innerHTML='<option value="">Tất cả</option><option value="CHUA_PHAN_LOAI">Chưa phân loại</option>'+(CFG.danhMuc||[]).map(function(c){return '<option value="'+esc(c.ten)+'">'+esc(c.ten)+'</option>';}).join('');
		var pn=(CFG.phapNhanOpts||[]).map(function(p){return '<option value="'+esc(p)+'">'+esc(p)+'</option>';}).join('');
		$('#fPN').innerHTML='<option value="">Tất cả</option>'+pn;
	}

	// Dashboard
	window.skDash=function(){ get('/dashboard').then(function(d){
		$('#skDashCards').innerHTML='<div class="sk-card"><div class="sk-lbl">Tổng số dư tính toán</div><div class="sk-val">'+fmt(d.tongSoDu)+'</div></div>'
			+'<div class="sk-card"><div class="sk-lbl">Số tài khoản</div><div class="sk-val">'+(d.taiKhoan||[]).length+'</div></div>'
			+'<div class="sk-card"><div class="sk-lbl">Tổng tiền vào</div><div class="sk-val sk-in">'+fmt(d.tongThuBank)+'</div></div>'
			+'<div class="sk-card"><div class="sk-lbl">Tổng tiền ra (chi)</div><div class="sk-val sk-out">'+fmt(d.theoThang&&d.theoThang.reduce(function(s,m){return s+m.chi;},0)||0)+'</div></div>';
		$('#skDashTK').innerHTML=(d.taiKhoan||[]).map(function(t){
			var oTT=t.coSoDuThucTe?fmt(t.soDu):'<span class="sk-mut" title="SePay không gửi số dư cho TK này">—</span>';
			var oL=t.coSoDuThucTe?(t.chenhLech?fmt(t.chenhLech):'0'):'<span class="sk-mut">—</span>';
			var cl=t.coSoDuThucTe&&t.chenhLech?(t.chenhLech>0?'sk-in':'sk-out'):'';
			return '<tr><td>'+esc(t.soTK)+'</td><td>'+esc(t.nganHang)+'</td><td>'+esc(t.chuTK)+'</td><td>'+esc(t.phapNhan||'—')+'</td><td>'+(t.soDuDauKy?fmt(t.soDuDauKy):'—')+'</td><td>'+oTT+'</td><td>'+fmt(t.soDuTinhToan)+'</td><td class="'+cl+'">'+oL+'</td></tr>';
		}).join('')||'<tr><td colspan=8 class="sk-mut">Chưa có tài khoản. Thêm ở tab Tài khoản.</td></tr>';
		$('#skDashThang').innerHTML=(d.theoThang||[]).map(function(m){ var cl=m.chi>m.thu?'sk-out':'sk-in'; return '<tr><td>'+esc(m.thang)+'</td><td class="sk-in">'+fmt(m.thu)+'</td><td class="sk-out">'+fmt(m.chi)+'</td><td class="'+cl+'">'+fmt(m.thu-m.chi)+'</td></tr>'; }).join('');
		$('#skDashNhom').innerHTML=(d.nhomChi||[]).map(function(n){return '<tr><td>'+esc(n.nhom)+'</td><td class="sk-out">'+fmt(n.tong)+'</td></tr>';}).join('')||'<tr><td colspan=2 class="sk-mut">Chưa có</td></tr>';
	}).catch(function(e){ toast(e.message||e,true); }); };

	// Sao kê
	var ROWS=[], PAGE=1, SIZE=20;
	window.skTai=function(){
		var f={ soTK:$('#fTK').value, phapNhan:$('#fPN').value, tuNgay:vn($('#fTu').value), denNgay:vn($('#fDen').value),
			loai:$('#fLoai').value, nhan:$('#fNhan').value, tuKhoa:$('#fKw').value };
		$('#skBody').innerHTML='<tr><td colspan=11 class="sk-mut">Đang tải…</td></tr>';
		post('/giaodich',{filters:f}).then(function(d){
			$('#skVao').textContent=fmt(d.tongVao); $('#skRa').textContent=fmt(d.tongRa);
			$('#skChenh').textContent=fmt(d.tongVao-d.tongRa); $('#skDong').textContent=d.soDong;
			ROWS=d.rows||[]; PAGE=1; veTrang();
			$('#skCapNhat').textContent='Cập nhật '+new Date().toLocaleTimeString('vi-VN');
		}).catch(function(e){ $('#skBody').innerHTML='<tr><td colspan=11 style="color:#f0a0a0">❌ '+esc(e.message||e)+'</td></tr>'; }); };
	function veTrang(){
		var cat=(CFG.danhMuc||[]).map(function(c){return '<option value="'+esc(c.ten)+'">'+esc(c.ten)+'</option>';}).join('');
		var tp=Math.max(1,Math.ceil(ROWS.length/SIZE)); if(PAGE>tp)PAGE=tp;
		var st=(PAGE-1)*SIZE, pg=ROWS.slice(st,st+SIZE);
		$('#skBody').innerHTML=pg.map(function(r){
			var sel=cat.replace('value="'+esc(r.nhan)+'"','value="'+esc(r.nhan)+'" selected');
			return '<tr><td>'+esc(r.ngayGD)+'</td><td>'+esc(r.nganHang)+'</td><td>'+esc(r.soTK)+'</td><td>'+esc(r.phapNhan||'—')+'</td>'
				+'<td style="white-space:normal;max-width:280px">'+esc(r.noiDung)+'</td><td>'+esc(r.maGD)+'</td>'
				+'<td class="sk-in">'+(r.vao?fmt(r.vao):'')+'</td><td class="sk-out">'+(r.ra?fmt(r.ra):'')+'</td>'
				+'<td>'+(r.soDu==null?'<span class="sk-mut">—</span>':fmt(r.soDu))+'</td>'
				+'<td><select onchange="skGanNhan(\''+esc(r.sepayId)+'\',this.value)"><option value="">-- chọn --</option>'+sel+'</select></td>'
				+'<td class="sk-mut">'+esc(r.nguon)+'</td></tr>';
		}).join('')||'<tr><td colspan=11 class="sk-mut">Không có giao dịch khớp bộ lọc.</td></tr>';
		$('#skPageLbl').textContent='Trang '+PAGE+' / '+tp+' ('+ROWS.length+' GD)';
	}
	window.skTrang=function(d){ var tp=Math.max(1,Math.ceil(ROWS.length/SIZE)); PAGE=Math.min(tp,Math.max(1,PAGE+d)); veTrang(); };
	window.skLamMoi=function(){ ['fTK','fPN','fTu','fDen','fLoai','fNhan','fKw'].forEach(function(id){var e=$('#'+id);if(e)e.value='';}); skTai(); };
	window.skGanNhan=function(id,n){ post('/nhan',{sepayId:id,nhan:n}).then(function(){toast('Đã gắn nhãn');}).catch(function(e){toast(e.message||e,true);}); };

	// Tài khoản
	function veTK(){ $('#tkBody').innerHTML=(CFG.taiKhoan||[]).map(function(t){
		return '<tr><td>'+esc(t.soTK)+'</td><td>'+esc(t.nganHang)+'</td><td>'+esc(t.chuTK)+'</td><td>'+esc(t.ghiChu)+'</td><td>'+esc(t.phapNhan||'—')+'</td><td>'+(t.soDuDauKy?fmt(t.soDuDauKy):'—')+'</td>'
			+'<td><button class="sk-btn sk-gray" onclick="skXoaTK(\''+esc(t.soTK)+'\')">Xoá</button></td></tr>';
	}).join('')||'<tr><td colspan=7 class="sk-mut">Chưa có tài khoản.</td></tr>'; }
	window.skLuuTK=function(){ post('/taikhoan',{ soTK:$('#tkSo').value, nganHang:$('#tkNH').value, chuTK:$('#tkChu').value, ghiChu:$('#tkGhi').value, phapNhan:$('#tkPN').value, soDuDauKy:$('#tkDK').value, ngayDauKy:vn($('#tkNgayDK').value) })
		.then(function(){ toast('Đã lưu'); ['tkSo','tkNH','tkChu','tkGhi','tkPN','tkDK','tkNgayDK'].forEach(function(id){$('#'+id).value='';}); napCfg(veTK); }).catch(function(e){toast(e.message||e,true);}); };
	window.skXoaTK=function(so){ if(!confirm('Xoá TK '+so+'?'))return; post('/taikhoan-xoa',{soTK:so}).then(function(){toast('Đã xoá'); napCfg(veTK);}).catch(function(e){toast(e.message||e,true);}); };

	// Danh mục
	function veDM(){ $('#dmBody').innerHTML=(CFG.danhMuc||[]).map(function(c){
		return '<tr><td>'+esc(c.ten)+'</td><td>'+esc(c.loai)+'</td><td><span style="display:inline-block;width:40px;height:14px;border-radius:3px;background:'+esc(c.mau)+'"></span> '+esc(c.mau)+'</td>'
			+'<td><button class="sk-btn sk-gray" onclick="skXoaDM(\''+esc(c.ten)+'\')">Xoá</button></td></tr>';
	}).join('')||'<tr><td colspan=4 class="sk-mut">Chưa có nhóm.</td></tr>'; }
	window.skLuuDM=function(){ post('/danhmuc',{ ten:$('#dmTen').value, loai:$('#dmLoai').value, mau:$('#dmMau').value })
		.then(function(){ toast('Đã lưu'); $('#dmTen').value=''; napCfg(veDM); }).catch(function(e){toast(e.message||e,true);}); };
	window.skXoaDM=function(t){ if(!confirm('Xoá nhóm '+t+'?'))return; post('/danhmuc-xoa',{ten:t}).then(function(){toast('Đã xoá'); napCfg(veDM);}).catch(function(e){toast(e.message||e,true);}); };

	// Cấu hình
	function veCfg(){ $('#cfgUrl').textContent=CFG.webhookUrl||'(đặt Webhook API Key rồi lưu)';
		$('#cfgKey').placeholder=CFG.hasWebhookKey?'(đã đặt — nhập để đổi)':'chưa đặt';
		$('#cfgToken').placeholder=CFG.hasApiToken?'(đã đặt — nhập để đổi)':'chưa đặt'; }
	window.skLuuCfg=function(){ post('/cauhinh',{ token:$('#cfgToken').value, key:$('#cfgKey').value })
		.then(function(){ toast('Đã lưu cấu hình'); $('#cfgKey').value=''; $('#cfgToken').value=''; napCfg(veCfg); }).catch(function(e){toast(e.message||e,true);}); };
	window.skSync=function(){ if(!$('#syncTu').value||!$('#syncDen').value){toast('Chọn khoảng ngày',true);return;} toast('Đang đồng bộ…');
		post('/sync',{ tu:vn($('#syncTu').value), den:vn($('#syncDen').value), tk:$('#syncTK').value })
			.then(function(r){ toast('Xong: '+r.moi+' mới, '+r.trung+' đã có'); skDash(); }).catch(function(e){toast(e.message||e,true);}); };
	window.skDoiPin=function(){ post('/doipin',{ cu:$('#pinCu').value, moi:$('#pinMoi').value })
		.then(function(){ PIN=$('#pinMoi').value; try{sessionStorage.setItem(KEY,PIN);}catch(e){} toast('Đã đổi PIN'); $('#pinCu').value='';$('#pinMoi').value=''; }).catch(function(e){toast(e.message||e,true);}); };

	// ═══════ v0.2 ═══════
	var DIEM=[];
	// Đối chiếu nộp tiền
	window.skDC=function(){
		$('#dcNhom').innerHTML='<div class="sk-panel sk-mut">Đang đối chiếu…</div>';
		post('/doichieu',{ tuNgay:vn($('#dcTu').value), denNgay:vn($('#dcDen').value) }).then(function(d){
			$('#dcCards').innerHTML=''
				+card('Tổng điểm',d.tongDiem)+card('Đã nộp',d.tongDaNop,'sk-in')+card('Chưa nộp',d.tongChuaNop,'sk-out')
				+card('Tiền đã nộp',fmt(d.tongTienNop),'sk-in')+card('Tiền vào trong kỳ',fmt(d.tongVaoTrongKy));
			$('#dcNhom').innerHTML=(d.nhom||[]).map(function(g){
				var rows=(g.diem||[]).slice().sort(function(a,b){ if(a.daNop!==b.daNop)return a.daNop?-1:1; return b.soTien-a.soTien; }).map(function(o){
					return '<tr><td>'+esc(o.ma)+'</td><td style="white-space:normal">'+esc(o.ten||'—')+'</td><td>'+esc(o.tenPhapNhan)+'</td>'
						+'<td>'+(o.daNop?'<span class="sk-in">✔ Đã nộp</span>':'<span class="sk-out">✘ Chưa</span>')+'</td>'
						+'<td class="sk-in">'+(o.soTien?fmt(o.soTien):'')+'</td><td>'+(o.lanCuoi||'')+'</td></tr>';
				}).join('');
				return '<div class="sk-panel"><h3>'+esc(g.ten)+' <span class="sk-mut">('+g.soDaNop+'/'+g.tongDiem+' đã nộp · '+fmt(g.tienDaNop)+')</span></h3>'
					+'<div class="sk-scroll"><table><thead><tr><th>Mã nộp</th><th>Điểm</th><th>Pháp nhân</th><th>Trạng thái</th><th>Số tiền</th><th>Lần cuối</th></tr></thead><tbody>'
					+(rows||'<tr><td colspan=6 class="sk-mut">Nhóm này chưa có điểm nào (khai ở tab Điểm nộp).</td></tr>')+'</tbody></table></div></div>';
			}).join('');
			var cb=''; if((d.khongRo||[]).length) cb+='<h3>Tiền vào KHÔNG có mã nộp ('+d.khongRo.length+')</h3><div class="sk-scroll"><table><thead><tr><th>Ngày</th><th>Số TK</th><th>Nội dung</th><th>Tiền vào</th></tr></thead><tbody>'+d.khongRo.slice(0,100).map(function(x){return '<tr><td>'+esc(x.ngayGD)+'</td><td>'+esc(x.soTK)+'</td><td style="white-space:normal">'+esc(x.noiDung)+'</td><td class="sk-in">'+fmt(x.vao)+'</td></tr>';}).join('')+'</tbody></table></div>';
			if((d.maLa||[]).length) cb+='<h3 style="margin-top:12px">Mã lạ (không có trong danh sách điểm)</h3><div class="sk-scroll"><table><thead><tr><th>Mã</th><th>Số tiền</th><th>Số lần</th></tr></thead><tbody>'+d.maLa.map(function(x){return '<tr><td>'+esc(x.ma)+'</td><td>'+fmt(x.soTien)+'</td><td>'+x.soLan+'</td></tr>';}).join('')+'</tbody></table></div>';
			$('#dcCanhBao').innerHTML=cb; $('#dcCanhBao').hidden=!cb;
		}).catch(function(e){ $('#dcNhom').innerHTML='<div class="sk-panel" style="color:#f0a0a0">❌ '+esc(e.message||e)+'</div>'; });
	};
	function card(l,v,cl){ return '<div class="sk-card"><div class="sk-lbl">'+esc(l)+'</div><div class="sk-val '+(cl||'')+'">'+v+'</div></div>'; }

	// Sao kê cổng
	function veTuKhoaFile(){ var n=$('#cgNguon').value; $('#cgFileBox').hidden=(n==='vietqr'); }
	window.skCong=function(){
		var n=$('#cgNguon').value; veTuKhoaFile();
		$('#cgBody').innerHTML='<tr><td colspan=6 class="sk-mut">Đang tải…</td></tr>';
		post('/saoke-cong',{ nguon:n, tuNgay:vn($('#cgTu').value), denNgay:vn($('#cgDen').value) }).then(function(d){
			$('#cgCong').textContent=fmt(d.congTien); $('#cgBank').textContent=fmt(d.bankTien);
			$('#cgChenh').textContent=fmt(d.chenh); $('#cgChenh').className='sk-val '+(d.chenh?(d.chenh>0?'sk-in':'sk-out'):'');
			$('#cgKieu').textContent=d.kieuDoiSoat; $('#cgKw').value=d.tuKhoa||'';
			var h='Từ khoá cục về bank: <b>'+esc(d.tuKhoa||'(chưa đặt)')+'</b>. ';
			if(n==='vietqr'){ h+=d.thieuKey?'⚠️ Chưa đặt Webhook Key (ở WP Admin) nên chưa có URL webhook Việt QR.':'Webhook Việt QR: <code>'+esc(d.webhookUrl)+'</code> — dán vào Tingo/cổng, Authentication = No Authentication.'; }
			else { h+=n.toUpperCase()+' không có webhook — dùng khung "Nạp file kết xuất" phía dưới + ánh xạ cửa hàng.'; }
			$('#cgHint').innerHTML=h;
			$('#cgBody').innerHTML=(d.cong||[]).map(function(o){
				return '<tr><td>'+esc(o.thoiDiem)+'</td><td class="sk-in">'+fmt(o.soTien)+'</td><td>'+esc(o.maGD)+'</td><td>'+esc(o.ref)+'</td><td>'+esc(o.tenMay||o.coSo||'—')+'</td><td style="white-space:normal;max-width:280px">'+esc(o.noiDung)+'</td></tr>';
			}).join('')||'<tr><td colspan=6 class="sk-mut">Chưa có giao dịch cổng'+(d.congKho?(' ('+d.congKho+' dòng chưa đọc được đủ trường)'):'')+'.</td></tr>';
		}).catch(function(e){ $('#cgBody').innerHTML='<tr><td colspan=6 style="color:#f0a0a0">❌ '+esc(e.message||e)+'</td></tr>'; });
	};
	window.skLuuTuKhoa=function(){ post('/cong-tukhoa',{ nguon:$('#cgNguon').value, tuKhoa:$('#cgKw').value }).then(function(){toast('Đã lưu từ khoá'); skCong();}).catch(function(e){toast(e.message||e,true);}); };
	window.skLuuAnhXa=function(){ post('/anhxa',{ nguon:$('#cgNguon').value, tenFile:$('#axTen').value, tenChuan:$('#axChuan').value, maBank:$('#axMa').value, tuNgay:vn($('#axTu').value), denNgay:vn($('#axDen').value) })
		.then(function(r){ toast('Đã lưu ánh xạ → '+r.maBank+(r.daVa?(' · vá '+r.daVa+' dòng file'):'')); ['axTen','axChuan','axMa','axTu','axDen'].forEach(function(id){$('#'+id).value='';}); }).catch(function(e){toast(e.message||e,true);}); };

	// Nạp file MoMo/VNPAY qua SheetJS
	var XLSXrows=null;
	function napXLSX(cb){ if(window.XLSX){cb();return;} var s=document.createElement('script'); s.src='https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js'; s.onload=cb; s.onerror=function(){toast('Không tải được thư viện đọc Excel',true);}; document.head.appendChild(s); }
	$('#cgFile')&&$('#cgFile').addEventListener('change',function(ev){
		var f=ev.target.files[0]; if(!f)return;
		napXLSX(function(){ var rd=new FileReader(); rd.onload=function(e){
			try{ var wb=XLSX.read(e.target.result,{type:'array',cellDates:false}); var ws=wb.Sheets[wb.SheetNames[0]];
				XLSXrows=XLSX.utils.sheet_to_json(ws,{header:1,raw:false,defval:''});
				var head=XLSXrows[0]||[]; var opt='<option value="-1">—</option>'+head.map(function(h,i){return '<option value="'+i+'">'+esc((i+1)+'. '+(h||'cột '+(i+1)))+'</option>';}).join('');
				['cgCotNgay','cgCotTien','cgCotCH','cgCotMa'].forEach(function(id){$('#'+id).innerHTML=opt;});
				$('#cgFileMsg').textContent='Đọc '+ (XLSXrows.length-1) +' dòng. Chọn cột rồi bấm Nạp.';
			}catch(err){ toast('Lỗi đọc file: '+err.message,true); }
		}; rd.readAsArrayBuffer(f); });
	});
	window.skNapFile=function(){
		if(!XLSXrows||XLSXrows.length<2){toast('Chọn file trước',true);return;}
		var ci=function(id){return parseInt($('#'+id).value,10);};
		var cN=ci('cgCotNgay'),cT=ci('cgCotTien'),cH=ci('cgCotCH'),cM=ci('cgCotMa');
		if(cN<0||cT<0||cH<0){toast('Chọn đủ cột Ngày, Số tiền, Cửa hàng',true);return;}
		var rows=[]; for(var i=1;i<XLSXrows.length;i++){ var r=XLSXrows[i]||[]; rows.push([r[cN]||'',r[cT]||'',r[cH]||'',cM>=0?(r[cM]||''):'']); }
		toast('Đang nạp '+rows.length+' dòng…');
		post('/nap-file-cong',{ nguon:$('#cgNguon').value, rows:rows, tenFile:($('#cgFile').files[0]||{}).name||'', ghiDe:$('#cgGhiDe').checked?1:0 })
			.then(function(r){ $('#cgFileMsg').innerHTML='✅ Thêm '+r.themMoi+', ghi đè '+r.daGhiDe+', trùng bỏ '+r.trungBoQua+' · tiền +'+fmt(r.tongTienThem)+(r.cuaHangMoi.length?(' · <b>chưa ánh xạ:</b> '+r.cuaHangMoi.map(esc).join(', ')):''); skCong(); })
			.catch(function(e){toast(e.message||e,true);});
	};

	// Doanh thu cơ sở
	window.skCS=function(){
		$('#csNhom').innerHTML='<div class="sk-panel sk-mut">Đang tổng hợp…</div>';
		post('/tonghop-coso',{ tuNgay:vn($('#csTu').value), denNgay:vn($('#csDen').value) }).then(function(d){
			var tc=d.tongTheoCong||{};
			$('#csCards').innerHTML=card('Nộp trực tiếp',fmt(d.tongNopTrucTiep),'sk-in')+card('Việt QR',fmt(tc.vietqr||0))+card('MoMo',fmt(tc.momo||0))+card('VNPAY',fmt(tc.vnpay||0))+card('Gán được về cơ sở',fmt(d.tongGanDuoc),'sk-in');
			$('#csNhom').innerHTML=(d.nhom||[]).map(function(g){
				var rows=(g.diem||[]).map(function(o){
					return '<tr><td>'+esc(o.ma)+'</td><td style="white-space:normal">'+esc(o.ten||'—')+'</td>'
						+'<td class="sk-in">'+(o.nop?fmt(o.nop):'')+'</td><td>'+(o.vietqr?fmt(o.vietqr):'')+'</td><td>'+(o.momo?fmt(o.momo):'')+'</td><td>'+(o.vnpay?fmt(o.vnpay):'')+'</td>'
						+'<td><b>'+(o.tong?fmt(o.tong):'0')+'</b></td></tr>';
				}).join('');
				return '<div class="sk-panel"><h3>'+esc(g.ten)+' <span class="sk-mut">('+g.soCoTien+'/'+g.tongDiem+' có tiền · '+fmt(g.tong)+')</span></h3>'
					+'<div class="sk-scroll"><table><thead><tr><th>Mã</th><th>Điểm</th><th>Nộp TT</th><th>Việt QR</th><th>MoMo</th><th>VNPAY</th><th>Tổng</th></tr></thead><tbody>'
					+(rows||'<tr><td colspan=7 class="sk-mut">Chưa có điểm.</td></tr>')+'</tbody></table></div></div>';
			}).join('');
			var cb='';
			(d.congChuaGanMa||[]).forEach(function(c){ if(c.ds.length||c.chuaRoMay){ cb+='<h3>'+esc(c.ten)+' — chưa gán về cơ sở'+(c.chuaRoMay?(' · '+c.chuaRoMay+' GD không rõ máy = '+fmt(c.chuaRoTien)):'')+'</h3>'+(c.ds.length?'<div class="sk-scroll"><table><thead><tr><th>Tên</th><th>Số tiền</th><th>Lý do</th></tr></thead><tbody>'+c.ds.map(function(x){return '<tr><td>'+esc(x.ten)+'</td><td>'+fmt(x.soTien)+'</td><td style="white-space:normal">'+esc(x.vi)+'</td></tr>';}).join('')+'</tbody></table></div>':''); } });
			if(d.tongKhongRoMaNop) cb+='<p class="sk-mut">Nộp trực tiếp không đọc ra mã nộp: '+fmt(d.tongKhongRoMaNop)+' ('+(d.khongRoMaNop||[]).length+' GD).</p>';
			$('#csCanhBao').innerHTML=cb; $('#csCanhBao').hidden=!cb;
		}).catch(function(e){ $('#csNhom').innerHTML='<div class="sk-panel" style="color:#f0a0a0">❌ '+esc(e.message||e)+'</div>'; });
	};

	// Điểm nộp
	function veDiem(){ get('/diem').then(function(d){ DIEM=d.ds||[];
		$('#diemDem').textContent='('+DIEM.length+' điểm)';
		$('#diemBody').innerHTML=DIEM.map(function(o){
			return '<tr><td>'+esc(o.ma)+'</td><td style="white-space:normal">'+esc(o.ten||'—')+'</td><td>'+esc(o.phapNhan)+'</td><td>'+esc(o.heThong)+'</td><td>'+esc(o.mien)+'</td>'
				+'<td><button class="sk-btn sk-gray" onclick="skXoaDiem(\''+esc(o.ma)+'\')">Xoá</button></td></tr>';
		}).join('')||'<tr><td colspan=6 class="sk-mut">Chưa có điểm. Dán danh sách ở trên.</td></tr>';
		$('#axDsDiem').innerHTML=DIEM.map(function(o){return '<option value="'+esc(o.ten||o.ma)+'">';}).join('');
	}).catch(function(e){toast(e.message||e,true);}); }
	window.skNhapDiem=function(){ var t=$('#diemTxt').value; if(!t.trim()){toast('Dán danh sách trước',true);return;}
		post('/diem-nhap',{ text:t, thay:$('#diemThay').checked?1:0 }).then(function(r){
			var m='✅ Tổng '+r.tong+' điểm (+'+r.them+' mới).'; if(r.soThieuMa)m+=' ⚠️ '+r.soThieuMa+' dòng không có mã nộp hợp lệ.'; if((r.trung||[]).length)m+=' Trùng mã: '+r.trung.length+'.';
			$('#diemMsg').textContent=m; veDiem();
		}).catch(function(e){toast(e.message||e,true);}); };
	window.skXoaDiem=function(ma){ if(!confirm('Xoá điểm '+ma+'?'))return; post('/diem-xoa',{ma:ma}).then(function(){toast('Đã xoá');veDiem();}).catch(function(e){toast(e.message||e,true);}); };
	window.skXoaHetDiem=function(){ if(!confirm('Xoá HẾT danh sách điểm?'))return; post('/diem-xoa',{ma:'ALL'}).then(function(){toast('Đã xoá hết');veDiem();}).catch(function(e){toast(e.message||e,true);}); };
})();
</script>
<?php
		return ob_get_clean();
	}
}

register_activation_hook( __FILE__, function () { SAOKE_App::bao_dam_bang(); SAOKE_App::bao_dam_trang(); flush_rewrite_rules(); } );
add_action( 'init', array( 'SAOKE_App', 'init' ), 6 );

endif; // class_exists SAOKE_App
