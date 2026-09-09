<?php
/**
 * Plugin Name:       Sao Kê Ngân Hàng K&H (SePay)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Sao kê & đối soát dòng tiền ngân hàng qua SePay (webhook + Open API) + đối chiếu nộp tiền theo điểm + sao kê cổng Việt QR/MoMo/VNPAY + tổng hợp doanh thu cơ sở. Trang [posh_saoke] bảo vệ bằng PIN. ĐỘC LẬP với plugin vé/ghế.
 * Version:           0.6.0
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
		add_action( 'saoke_cron_sync', array( __CLASS__, 'cron_sync' ) );
		add_action( 'saoke_cron_vqr', array( __CLASS__, 'cron_vqr_sheet' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'them_lich' ) );
		// Bảo đảm lịch tồn tại nếu đã bật auto-sync (WP-Cron kích khi có traffic).
		if ( '1' === (string) get_option( 'saoke_autosync', '0' ) && ! wp_next_scheduled( 'saoke_cron_sync' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'saoke_cron_sync' );
		}
		if ( '' !== trim( (string) get_option( 'saoke_vqr_sheet', '' ) ) && ! wp_next_scheduled( 'saoke_cron_vqr' ) ) {
			wp_schedule_event( time() + 120, 'saoke_5phut', 'saoke_cron_vqr' );
		}
	}
	public static function them_lich( $s ) { $s['saoke_5phut'] = array( 'interval' => 300, 'display' => 'Mỗi 5 phút (Sao Kê)' ); return $s; }

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

	/* So khớp Webhook Key chịu được cả dạng mã hoá URL (key có dấu & -> %26) như bản Apps Script. */
	private static function key_khop( $got, $expect ) {
		$g = trim( (string) $got ); $e = trim( (string) $expect );
		if ( '' === $e ) { return false; }
		if ( hash_equals( $e, $g ) ) { return true; }
		if ( hash_equals( $e, rawurldecode( $g ) ) ) { return true; } // cổng gửi %26 thay cho &
		if ( hash_equals( $e, urldecode( $g ) ) ) { return true; }
		if ( hash_equals( rawurlencode( $e ), $g ) ) { return true; }  // cổng mã hoá cả key
		return false;
	}

	// ───────────────────────────── REST ─────────────────────────────
	public static function routes() {
		$pub = array( 'permission_callback' => '__return_true' );
		register_rest_route( self::NS, '/webhook', array( array( 'methods' => 'GET, POST', 'callback' => array( __CLASS__, 'r_webhook' ) ) + $pub ) );
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
		register_rest_route( self::NS, '/sync-ngay', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_sync_ngay' ) ) + $pub ) );
		register_rest_route( self::NS, '/keo-sheet', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_keo_sheet' ) ) + $pub ) );
		register_rest_route( self::NS, '/doipin',   array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_doipin' ) ) + $pub ) );
		// ── Cầu RPC: nhận {fn, args} từ frontend app (shim google.script.run) ──
		register_rest_route( self::NS, '/rpc', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_rpc' ) ) + $pub ) );
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
		// ── VietQR chính thức: token + callback (VietQR gọi VÀO server mình) ──
		register_rest_route( self::NS, '/vqr/api/token_generate', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_vqr_token' ) ) + $pub ) );
		register_rest_route( self::NS, '/vqr/bank/api/transaction-callback', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_vqr_callback' ) ) + $pub ) );
		register_rest_route( self::NS, '/vqr/bank/api/test/transaction-callback', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_vqr_callback' ) ) + $pub ) );
		register_rest_route( self::NS, '/vqr-cfg', array( array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_vqr_cfg' ) ) + $pub ) );
		register_rest_route( self::NS, '/log', array( array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_log' ) ) + $pub ) );
	}
	public static function r_log( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$l = get_option( 'saoke_weblog' );
		return array( 'ok' => true, 'log' => is_array( $l ) ? $l : array() );
	}

	/* Nhật ký webhook: giữ 50 lần gần nhất để anh thấy webhook có nhận được không. */
	private static function ghi_log( $src, $kq, $raw = '' ) {
		$log = get_option( 'saoke_weblog' ); if ( ! is_array( $log ) ) { $log = array(); }
		array_unshift( $log, array(
			'luc' => current_time( 'mysql' ), 'src' => (string) $src, 'kq' => mb_substr( (string) $kq, 0, 160 ),
			'ip'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( (string) $_SERVER['REMOTE_ADDR'] ) : '',
			'raw' => mb_substr( (string) $raw, 0, 400 ),
		) );
		if ( count( $log ) > 50 ) { $log = array_slice( $log, 0, 50 ); }
		update_option( 'saoke_weblog', $log, false );
	}

	/* ── Webhook: SePay (mặc định) hoặc cổng qua ?src=vietqr|momo|vnpay ── */
	public static function r_webhook( $req ) {
		$raw = (string) $req->get_body();
		$src = strtolower( trim( (string) $req->get_param( 'src' ) ) ); if ( '' === $src ) { $src = 'sepay'; }
		if ( 'bank' === $src ) { $src = 'sepay'; }
		$key = (string) get_option( 'saoke_webhook_key', '' );
		if ( '' === $key || ! self::key_khop( (string) $req->get_param( 'key' ), $key ) ) {
			self::ghi_log( $src, '✖ SAI KEY (bị chặn)', $raw );
			return new WP_REST_Response( array( 'success' => false, 'message' => 'sai key' ), 401 );
		}
		// Mở URL bằng trình duyệt (GET) = ping thử: key đúng + tới được web thì hiện dòng này trong Nhật ký.
		if ( 'GET' === $req->get_method() ) {
			self::ghi_log( $src, '🔎 ping thử (GET từ trình duyệt) — key OK, đường tới web bình thường', '' );
			return new WP_REST_Response( array( 'success' => true, 'ping' => true, 'message' => 'OK — webhook tới được web (key đúng). Nhật ký đã ghi.' ), 200 );
		}
		// Mọi nguồn (SePay/VietQR/…) đều vào SAO KÊ NGÂN HÀNG, gắn nhãn nguồn theo src để phân biệt.
		// nguồn = src (vietqr/momo/vnpay/…) hoặc 'sepay' khi không khai src. Đọc field LINH HOẠT.
		$nguon = $src;
		$p = $req->get_json_params(); if ( ! is_array( $p ) ) { $p = $req->get_params(); }
		$vao = self::num( self::pick( $p, array( 'amount_in', 'amountIn', 'creditAmount', 'tienVao', 'credit' ), 0 ) );
		$ra  = self::num( self::pick( $p, array( 'amount_out', 'amountOut', 'debitAmount', 'tienRa', 'debit' ), 0 ) );
		$amt = self::num( self::pick( $p, array( 'transferAmount', 'amount', 'soTien', 'value', 'transAmount', 'money' ), 0 ) );
		$tt  = strtoupper( (string) self::pick( $p, array( 'transferType', 'transType', 'type', 'loai', 'direction' ) ) );
		if ( $vao > 0 ) { $loai = 'in'; $tien = $vao; }
		elseif ( $ra > 0 ) { $loai = 'out'; $tien = $ra; }
		else { $loai = in_array( $tt, array( 'OUT', 'D', 'DEBIT', 'CHI', 'RA', '-' ), true ) ? 'out' : 'in'; $tien = abs( $amt ); }
		$madg = (string) self::pick( $p, array( 'referencenumber', 'referenceNumber', 'reference_number', 'referenceCode', 'code', 'ftCode' ) );
		$sid  = (string) self::pick( $p, array( 'id', 'transactionid', 'transactionId', 'transaction_id', 'tid', 'traceId', 'maGiaoDich', 'ma_gd' ) );
		if ( '' === $sid ) { $sid = $madg; }
		$sotk = (string) self::pick( $p, array( 'accountNumber', 'account_number', 'bankaccount', 'bankAccount', 'accountNo', 'soTK', 'subAccount', 'account' ) );
		$noidung = (string) self::pick( $p, array( 'content', 'description', 'transaction_content', 'addInfo', 'orderInfo', 'noiDung', 'memo', 'remark' ) );
		$nganhang = (string) self::pick( $p, array( 'gateway', 'bankName', 'bank_name', 'bankCode', 'bank', 'nganHang' ), '' );
		$ngay = self::ngay_bd( self::pick( $p, array( 'transactionDate', 'transaction_date', 'transactiontime', 'transactionTime', 'transTime', 'payDate', 'createdAt', 'created_at', 'time', 'ngayGD', 'date' ) ) );
		$luyke = self::pick( $p, array( 'accumulated', 'balance', 'soDu', 'luyKe' ), '' );
		if ( $tien <= 0 && '' === $sid ) {
			self::ghi_log( $src, '⚠ nhận được nhưng thiếu tiền/mã (có thể là gói test)', $raw );
			return new WP_REST_Response( array( 'success' => true, 'moi' => 0, 'message' => 'gói test / thiếu dữ liệu' ), 200 );
		}
		$sid = $nguon . '-' . ( '' !== $sid ? $sid : substr( md5( (string) $raw ), 0, 20 ) ); // tiền tố nguồn tránh đụng mã giữa các nguồn
		$ok = self::luu_gd( array(
			'sepay_id'  => $sid,
			'ngay_gd'   => $ngay,
			'so_tk'     => $sotk,
			'ngan_hang' => '' !== $nganhang ? $nganhang : strtoupper( $nguon ),
			'loai'      => $loai,
			'tien'      => (int) round( $tien ),
			'luy_ke'    => ( '' !== (string) $luyke ) ? (int) round( self::num( $luyke ) ) : null,
			'noi_dung'  => $noidung,
			'ma_gd'     => $madg,
			'nguon'     => mb_substr( $nguon, 0, 10 ),
		) );
		self::ghi_log( $src, $ok ? ( '✔ đã lưu [' . $nguon . '] ' . number_format( $tien ) . 'đ' ) : 'trùng, bỏ qua', $raw );
		return new WP_REST_Response( array( 'success' => true, 'nguon' => $nguon, 'moi' => $ok ? 1 : 0 ), 200 );
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
	/* Lấy giá trị đầu tiên có mặt trong nhiều tên field (SePay/Tingo/VietQR khác tên nhau). */
	private static function pick( $p, $keys, $d = '' ) {
		foreach ( (array) $keys as $k ) { if ( isset( $p[ $k ] ) && '' !== $p[ $k ] && ! is_array( $p[ $k ] ) ) { return $p[ $k ]; } }
		return $d;
	}
	/* Ngày biến động: nhận epoch (giây/mili) hoặc chuỗi 'Y-m-d H:i:s' / 'dd/mm/yyyy…' / ISO. */
	private static function ngay_bd( $v ) {
		$v = trim( (string) $v ); if ( '' === $v ) { return ''; }
		if ( ctype_digit( $v ) ) { $n = (int) $v; if ( strlen( $v ) >= 13 ) { $n = (int) round( $n / 1000 ); } return gmdate( 'Y-m-d H:i:s', $n + 7 * 3600 ); }
		$t = strtotime( $v ); return $t ? gmdate( 'Y-m-d H:i:s', $t ) : '';
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

	// ═══════════════ VietQR CHÍNH THỨC (token_generate + transaction-callback) ═══════════════
	private static function vqr_secret() { $p = (string) get_option( 'saoke_vqr_pass', '' ); return '' !== $p ? ( $p . '|' . wp_salt( 'auth' ) ) : wp_salt( 'auth' ); }
	private static function vqr_make_token( $exp ) { $p = 'exp=' . $exp; return rtrim( strtr( base64_encode( $p ), '+/', '-_' ), '=' ) . '.' . hash_hmac( 'sha256', $p, self::vqr_secret() ); }
	private static function vqr_check_token( $tok ) {
		$tok = trim( (string) $tok ); $parts = explode( '.', $tok );
		if ( count( $parts ) !== 2 ) { return false; }
		$p = base64_decode( strtr( $parts[0], '-_', '+/' ) );
		if ( ! hash_equals( hash_hmac( 'sha256', $p, self::vqr_secret() ), $parts[1] ) ) { return false; }
		if ( ! preg_match( '/exp=(\d+)/', (string) $p, $m ) ) { return false; }
		return time() <= (int) $m[1];
	}
	/* Lấy header Authorization dù server strip mất (một số host cần PHP_AUTH_* / REDIRECT_). */
	private static function auth_header( $req ) {
		$h = (string) $req->get_header( 'authorization' );
		if ( '' === $h && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) { $h = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']; }
		if ( '' === $h && isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) { $h = (string) $_SERVER['HTTP_AUTHORIZATION']; }
		return $h;
	}
	/* VietQR gọi để lấy token — Basic Auth bằng user/pass mình khai. Trả JWT-like access_token. */
	public static function r_vqr_token( $req ) {
		$user = (string) get_option( 'saoke_vqr_user', '' ); $pass = (string) get_option( 'saoke_vqr_pass', '' );
		if ( '' === $user || '' === $pass ) { return new WP_REST_Response( array( 'error' => true, 'errorReason' => 'chưa khai user/pass VietQR' ), 401 ); }
		$u = ''; $p = '';
		$h = self::auth_header( $req );
		if ( 0 === stripos( $h, 'basic ' ) ) { $dec = base64_decode( trim( substr( $h, 6 ) ) ); if ( false !== strpos( $dec, ':' ) ) { list( $u, $p ) = explode( ':', $dec, 2 ); } }
		if ( '' === $u && isset( $_SERVER['PHP_AUTH_USER'] ) ) { $u = (string) $_SERVER['PHP_AUTH_USER']; $p = isset( $_SERVER['PHP_AUTH_PW'] ) ? (string) $_SERVER['PHP_AUTH_PW'] : ''; }
		// Cũng nhận user/pass trong body (một số cấu hình VietQR gửi kèm).
		if ( '' === $u ) { $b = $req->get_json_params(); if ( is_array( $b ) ) { $u = (string) ( isset( $b['username'] ) ? $b['username'] : '' ); $p = (string) ( isset( $b['password'] ) ? $b['password'] : '' ); } }
		if ( ! hash_equals( $user, $u ) || ! hash_equals( $pass, $p ) ) { return new WP_REST_Response( array( 'error' => true, 'errorReason' => 'sai username/password' ), 401 ); }
		$exp = time() + 12 * 3600;
		return new WP_REST_Response( array( 'access_token' => self::vqr_make_token( $exp ), 'token_type' => 'Bearer', 'expires_in' => 12 * 3600 ), 200 );
	}
	/* VietQR gọi mỗi khi có biến động. VietQR ở TÀI KHOẢN KHÁC với SePay → đây là tiền THẬT
	   trên tài khoản đó, SePay không thấy → lưu thẳng vào SAO KÊ NGÂN HÀNG (saoke_gd), gộp chung.
	   Không sợ trùng: khác tài khoản + mã GD riêng (chống trùng theo sepay_id 'vqr-<mã>'). */
	public static function r_vqr_callback( $req ) {
		$h = self::auth_header( $req ); $bearer = 0 === stripos( $h, 'bearer ' ) ? trim( substr( $h, 7 ) ) : '';
		if ( '' === $bearer || ! self::vqr_check_token( $bearer ) ) {
			self::ghi_log( 'vietqr-official', '✖ token không hợp lệ/hết hạn', (string) $req->get_body() );
			return new WP_REST_Response( array( 'error' => true, 'errorReason' => 'token không hợp lệ hoặc hết hạn' ), 401 );
		}
		$b = $req->get_json_params(); if ( ! is_array( $b ) ) { $b = $req->get_params(); }
		$raw = $req->get_body(); if ( '' === trim( (string) $raw ) ) { $raw = wp_json_encode( $b ); }
		$g = function ( $keys, $d = '' ) use ( $b ) { foreach ( (array) $keys as $k ) { if ( isset( $b[ $k ] ) && '' !== $b[ $k ] && ! is_array( $b[ $k ] ) ) { return $b[ $k ]; } } return $d; };
		$maGD = (string) $g( array( 'transactionid', 'transactionId', 'transaction_id', 'ftCode', 'traceId' ) );
		$ref  = (string) $g( array( 'referencenumber', 'referenceNumber', 'reference_number', 'orderId', 'orderid' ) );
		$tt   = strtoupper( (string) $g( array( 'transType', 'transtype', 'type' ), 'C' ) );
		$loai = ( 'D' === $tt || 'DEBIT' === $tt || 'OUT' === $tt ) ? 'out' : 'in';
		$thoiDiem = self::vqr_ngay( $g( array( 'transactiontime', 'transactionTime', 'transaction_time', 'time', 'transactionDate' ) ) );
		$sid = '' !== $maGD ? ( 'vqr-' . $maGD ) : ( 'vqr-' . $ref );
		if ( 'vqr-' === $sid ) { $sid = 'vqr-' . substr( md5( (string) $raw ), 0, 20 ); }
		$moi = self::luu_gd( array(
			'sepay_id'  => $sid,
			'ngay_gd'   => self::cong_ngay_mysql( $thoiDiem ),
			'so_tk'     => (string) $g( array( 'bankaccount', 'bankAccount', 'accountNumber', 'account_number' ) ),
			'ngan_hang' => (string) $g( array( 'bankName', 'bank_name', 'bankCode' ), 'VietQR' ),
			'loai'      => $loai,
			'tien'      => (int) round( self::num( $g( array( 'amount', 'transferAmount', 'amountIn', 'creditAmount' ), 0 ) ) ),
			'luy_ke'    => null,
			'noi_dung'  => (string) $g( array( 'content', 'description', 'orderInfo', 'addInfo' ) ),
			'ma_gd'     => '' !== $ref ? $ref : $maGD,
			'nguon'     => 'vietqr',
		) );
		self::ghi_log( 'vietqr-official', $moi ? ( '✔ đã lưu vào Sao kê NH ' . ( '' !== $maGD ? $maGD : $ref ) ) : 'trùng, bỏ qua', $raw );
		// VietQR chờ đúng envelope này để coi là nhận thành công.
		return new WP_REST_Response( array( 'error' => false, 'errorReason' => '', 'toControllerCode' => '',
			'object' => array( 'reftransactionid' => '' !== $maGD ? $maGD : $ref ) ), 200 );
	}
	/* Thời điểm VietQR: epoch giây/mili, hoặc ISO/'dd/MM/yyyy…' -> 'dd/MM/yyyy HH:mm:ss' (giờ VN). */
	private static function vqr_ngay( $v ) {
		$v = trim( (string) $v ); if ( '' === $v ) { return ''; }
		if ( ctype_digit( $v ) ) { $n = (int) $v; if ( strlen( $v ) >= 13 ) { $n = (int) round( $n / 1000 ); } return gmdate( 'd/m/Y H:i:s', $n + 7 * 3600 ); }
		$c = self::cong_ngay( $v ); if ( '' !== $c ) { return $c; }
		$c = self::cong_ngay_iso( $v ); if ( '' !== $c ) { return $c; }
		$t = strtotime( $v ); return $t ? gmdate( 'd/m/Y H:i:s', $t + 7 * 3600 ) : '';
	}
	public static function r_vqr_cfg( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$u = trim( (string) $req->get_param( 'user' ) ); $p = trim( (string) $req->get_param( 'pass' ) );
		if ( '' !== $u ) { update_option( 'saoke_vqr_user', sanitize_text_field( $u ) ); }
		if ( '' !== $p ) { update_option( 'saoke_vqr_pass', $p ); }
		return array( 'ok' => true );
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
		$last = get_option( 'saoke_sync_last', array() );
		return array( 'ok' => true,
			'taiKhoan' => self::ds_tk(), 'danhMuc' => self::ds_dm(), 'phapNhanOpts' => self::ds_pn(),
			'webhookUrl' => $key ? $url : '', 'hasWebhookKey' => $key !== '', 'hasApiToken' => get_option( 'saoke_api_token', '' ) !== '',
			'autosync' => '1' === (string) get_option( 'saoke_autosync', '0' ),
			'syncNgay' => (int) get_option( 'saoke_sync_ngay', 3 ),
			'syncLast' => is_array( $last ) ? $last : array(),
			'nextSync' => wp_next_scheduled( 'saoke_cron_sync' ) ? gmdate( 'd/m/Y H:i', wp_next_scheduled( 'saoke_cron_sync' ) + 7 * 3600 ) . ' (giờ VN)' : '',
			'vqrSheet' => (string) get_option( 'saoke_vqr_sheet', '' ),
			'vqrAuto'  => '1' === (string) get_option( 'saoke_vqr_auto', '0' ),
			'vqrSheetLast' => (array) get_option( 'saoke_vqr_sheet_last', array() ),
			'vqrNext'  => wp_next_scheduled( 'saoke_cron_vqr' ) ? gmdate( 'd/m/Y H:i', wp_next_scheduled( 'saoke_cron_vqr' ) + 7 * 3600 ) . ' (giờ VN)' : '',
			// VietQR chính thức
			'vqrUser' => (string) get_option( 'saoke_vqr_user', '' ),
			'hasVqrPass' => '' !== (string) get_option( 'saoke_vqr_pass', '' ),
			'vqrTokenUrl' => esc_url_raw( rest_url( self::NS . '/vqr/api/token_generate' ) ),
			'vqrCallbackUrl' => esc_url_raw( rest_url( self::NS . '/vqr/bank/api/transaction-callback' ) ),
			'vqrBaseUrl' => esc_url_raw( rest_url( self::NS ) ),
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
		if ( null !== $req->get_param( 'autosync' ) ) { self::dat_lich( '1' === (string) $req->get_param( 'autosync' ) || 1 === $req->get_param( 'autosync' ) ); }
		$sn = (int) $req->get_param( 'syncNgay' ); if ( $sn >= 1 && $sn <= 31 ) { update_option( 'saoke_sync_ngay', $sn ); }
		return array( 'ok' => true );
	}
	/* Chạy đồng bộ ngay (nút "Đồng bộ ngay" — vá theo lịch tay). */
	public static function r_sync_ngay( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		self::cron_sync();
		return array( 'ok' => true ) + (array) get_option( 'saoke_sync_last', array() );
	}
	/* Kéo VietQR từ Google Sheet ngay + lưu/bật lịch. */
	public static function r_keo_sheet( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$url = trim( (string) $req->get_param( 'url' ) );
		if ( '' !== $url ) { update_option( 'saoke_vqr_sheet', esc_url_raw( $url ) ); }
		if ( null !== $req->get_param( 'auto' ) ) {
			$bat = '1' === (string) $req->get_param( 'auto' ) || 1 === $req->get_param( 'auto' );
			wp_clear_scheduled_hook( 'saoke_cron_vqr' );
			if ( $bat && '' !== trim( (string) get_option( 'saoke_vqr_sheet', '' ) ) ) { wp_schedule_event( time() + 120, 'saoke_5phut', 'saoke_cron_vqr' ); }
			update_option( 'saoke_vqr_auto', $bat ? '1' : '0' );
		}
		$r = self::keo_vqr_sheet();
		if ( is_wp_error( $r ) ) { return $r; }
		return array( 'ok' => true, 'moi' => $r['moi'], 'trung' => $r['trung'] );
	}
	public static function r_doipin( $req ) {
		$cu = (string) $req->get_param( 'cu' ); $moi = preg_replace( '/\D+/', '', (string) $req->get_param( 'moi' ) );
		if ( ! hash_equals( (string) get_option( 'saoke_pin', '' ), $cu ) ) { return new WP_Error( 'pin', 'PIN hiện tại không đúng.', array( 'status' => 401 ) ); }
		if ( strlen( $moi ) < 4 || strlen( $moi ) > 8 ) { return new WP_Error( 'pin', 'PIN mới phải 4-8 số.', array( 'status' => 400 ) ); }
		update_option( 'saoke_pin', $moi );
		return array( 'ok' => true );
	}

	/* ── Đồng bộ lịch sử qua SePay Open API (dùng chung cho nút tay + cron) ── */
	public static function dong_bo( $tu_ymd, $den_ymd, $tk ) {
		$token = (string) get_option( 'saoke_api_token', '' );
		if ( '' === $token ) { return new WP_Error( 'token', 'Chưa đặt SePay API Token.', array( 'status' => 409 ) ); }
		$tk = preg_replace( '/\s+/', '', (string) $tk );
		$url = add_query_arg( array_filter( array(
			'limit' => 5000, 'transaction_date_min' => $tu_ymd ? $tu_ymd . ' 00:00:00' : '',
			'transaction_date_max' => $den_ymd ? $den_ymd . ' 23:59:59' : '', 'account_number' => $tk ?: '',
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
		return array( 'moi' => $moi, 'trung' => $trung );
	}
	public static function r_sync( $req ) {
		if ( ! self::pin_ok( $req ) ) { return self::loi_pin(); }
		$r = self::dong_bo( self::vn2ymd( (string) $req->get_param( 'tu' ) ), self::vn2ymd( (string) $req->get_param( 'den' ) ), (string) $req->get_param( 'tk' ) );
		if ( is_wp_error( $r ) ) { return $r; }
		return array( 'ok' => true, 'moi' => $r['moi'], 'trung' => $r['trung'] );
	}

	/* ── Tự động kéo bù theo lịch (WP-Cron): webhook lo realtime, cron vá phần sót ── */
	public static function cron_sync() {
		if ( '' === (string) get_option( 'saoke_api_token', '' ) ) { return; }
		$ngay = (int) get_option( 'saoke_sync_ngay', 3 ); if ( $ngay < 1 ) { $ngay = 3; }
		$den = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
		$tu  = gmdate( 'Y-m-d', current_time( 'timestamp' ) - $ngay * 86400 );
		$r = self::dong_bo( $tu, $den, '' );
		$log = is_wp_error( $r ) ? ( 'lỗi: ' . $r->get_error_message() ) : ( 'mới ' . $r['moi'] . ', trùng ' . $r['trung'] );
		update_option( 'saoke_sync_last', array( 'luc' => current_time( 'mysql' ), 'kq' => $log ) );
	}
	/* Bật/tắt lịch tự đồng bộ. */
	public static function dat_lich( $bat ) {
		$hook = 'saoke_cron_sync';
		wp_clear_scheduled_hook( $hook );
		if ( $bat ) { wp_schedule_event( time() + 300, 'hourly', $hook ); }
		update_option( 'saoke_autosync', $bat ? '1' : '0' );
	}

	// ═══════════════ VietQR DỰ PHÒNG: đọc Google Sheet (CSV) ═══════════════
	/* VietQR → Google Sheet (Tingo hỗ trợ sẵn) → plugin ĐỌC sheet (link CSV công khai). */
	public static function keo_vqr_sheet() {
		$url = trim( (string) get_option( 'saoke_vqr_sheet', '' ) );
		if ( '' === $url ) { return new WP_Error( 'url', 'Chưa đặt link Google Sheet (CSV).', array( 'status' => 409 ) ); }
		$res = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 5, 'headers' => array( 'Accept' => 'text/csv' ) ) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( 200 !== $code || false !== stripos( $body, '<html' ) ) {
			return new WP_Error( 'sheet', 'Không đọc được CSV (HTTP ' . $code . '). Sheet phải "Xuất bản lên web" hoặc chia sẻ công khai, dùng link export?format=csv.', array( 'status' => 502 ) );
		}
		$lines = preg_split( '/\r\n|\r|\n/', $body );
		$rows = array();
		foreach ( $lines as $ln ) { if ( '' === trim( $ln ) ) { continue; } $rows[] = str_getcsv( $ln ); }
		if ( count( $rows ) < 2 ) { return array( 'moi' => 0, 'trung' => 0, 'message' => 'sheet chưa có dữ liệu' ); }
		$col = self::map_cot_sheet( $rows[0] );
		if ( $col['ngay'] < 0 && $col['vao'] < 0 && $col['ra'] < 0 ) {
			return new WP_Error( 'header', 'Không nhận ra cột từ dòng tiêu đề. Đặt tiêu đề: Ngày · Số TK · Tiền vào · Tiền ra · Số dư · Nội dung · Mã GD.', array( 'status' => 400 ) );
		}
		$moi = 0; $trung = 0;
		for ( $i = 1; $i < count( $rows ); $i++ ) {
			$r = $rows[ $i ];
			$g = function ( $k ) use ( $r, $col ) { return ( $col[ $k ] >= 0 && isset( $r[ $col[ $k ] ] ) ) ? $r[ $col[ $k ] ] : ''; };
			$vao = self::num( $g( 'vao' ) ); $ra = self::num( $g( 'ra' ) );
			if ( $vao <= 0 && $ra <= 0 ) { continue; }
			$loai = $ra > 0 ? 'out' : 'in'; $tien = $ra > 0 ? $ra : $vao;
			$ma = trim( (string) $g( 'ma' ) );
			$sid = 'vqrsheet-' . ( '' !== $ma ? $ma : substr( md5( implode( '|', $r ) . $i ), 0, 20 ) );
			$ok = self::luu_gd( array(
				'sepay_id'  => $sid,
				'ngay_gd'   => self::ngay_bd( $g( 'ngay' ) ),
				'so_tk'     => trim( (string) $g( 'tk' ) ),
				'ngan_hang' => 'VietQR',
				'loai'      => $loai,
				'tien'      => (int) round( $tien ),
				'luy_ke'    => ( $col['sodu'] >= 0 && '' !== (string) $g( 'sodu' ) ) ? (int) round( self::num( $g( 'sodu' ) ) ) : null,
				'noi_dung'  => trim( (string) $g( 'nd' ) ),
				'ma_gd'     => $ma,
				'nguon'     => 'vietqr',
			) );
			if ( $ok ) { $moi++; } else { $trung++; }
		}
		update_option( 'saoke_vqr_sheet_last', array( 'luc' => current_time( 'mysql' ), 'kq' => 'mới ' . $moi . ', trùng ' . $trung ) );
		return array( 'moi' => $moi, 'trung' => $trung );
	}
	private static function map_cot_sheet( $head ) {
		$c = array( 'ngay' => -1, 'vao' => -1, 'ra' => -1, 'sodu' => -1, 'nd' => -1, 'tk' => -1, 'ma' => -1 );
		foreach ( (array) $head as $i => $h ) {
			$x = self::kd( $h );
			if ( $c['ngay'] < 0 && preg_match( '/ngay|thoi gian|date|time/', $x ) ) { $c['ngay'] = $i; }
			elseif ( $c['vao'] < 0 && preg_match( '/vao|ghi co|credit|amount in/', $x ) ) { $c['vao'] = $i; }
			elseif ( $c['ra'] < 0 && preg_match( '/ra|ghi no|debit|amount out/', $x ) ) { $c['ra'] = $i; }
			elseif ( $c['sodu'] < 0 && preg_match( '/so du|luy ke|balance|accumulated/', $x ) ) { $c['sodu'] = $i; }
			elseif ( $c['nd'] < 0 && preg_match( '/noi dung|mo ta|dien giai|content|description/', $x ) ) { $c['nd'] = $i; }
			elseif ( $c['tk'] < 0 && preg_match( '/so tk|tai khoan|account|stk/', $x ) ) { $c['tk'] = $i; }
			elseif ( $c['ma'] < 0 && preg_match( '/ma gd|ma giao dich|reference|ref|tham chieu|^id$|code|ft/', $x ) ) { $c['ma'] = $i; }
		}
		return $c;
	}
	public static function cron_vqr_sheet() {
		if ( '' === trim( (string) get_option( 'saoke_vqr_sheet', '' ) ) ) { return; }
		$r = self::keo_vqr_sheet();
		$log = is_wp_error( $r ) ? ( 'lỗi: ' . $r->get_error_message() ) : ( 'mới ' . $r['moi'] . ', trùng ' . $r['trung'] );
		update_option( 'saoke_vqr_sheet_last', array( 'luc' => current_time( 'mysql' ), 'kq' => $log ) );
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

	// ═══════════════════════════════════════════════════════════════════════
	//  CẦU RPC — port 1:1 các hàm Apps Script (Code.gs) sang PHP, đúng shape.
	//  Frontend gọi google.script.run.fn(pin, ...args) -> shim POST /rpc {fn,args}.
	// ═══════════════════════════════════════════════════════════════════════
	private static function pn_opts() { return array( 'K&H cũ (989)', 'K&H mới (705)', 'Smarttrade' ); }
	private static function loi( $msg ) { throw new Exception( $msg ); }
	private static function can_pin( $args ) { $pin = isset( $args[0] ) ? (string) $args[0] : ''; if ( ! hash_equals( (string) get_option( 'saoke_pin', '' ), $pin ) || '' === (string) get_option( 'saoke_pin', '' ) ) { self::loi( 'Sai mã PIN' ); } }

	public static function r_rpc( $req ) {
		$fn   = (string) $req->get_param( 'fn' );
		$args = $req->get_param( 'args' ); if ( ! is_array( $args ) ) { $args = array(); }
		$map  = array(
			'checkPin', 'getConfig', 'saveCauHinh', 'doiPin', 'getDashboard', 'getGiaoDich', 'setNhan',
			'saveTaiKhoan', 'xoaTaiKhoan', 'saveDanhMuc', 'xoaDanhMuc', 'testWebhookSample', 'syncSepayHistory',
			'getDoiChieuNop', 'napLaiDanhSachDiem', 'getTongHopCoSo', 'getSaoKeCong', 'getDoiSoatFile',
			'napFileCong', 'napFileCongTx', 'luuAnhXaCuaHang', 'chuyenGianCuaHang', 'xoaAnhXaCuaHang',
			'xoaNgayFileCong', 'dsCuaHangChuan', 'luuTuKhoaCong', 'testWebhookCong', 'luuCotFileCong', 'luuCotTxCong',
		);
		if ( ! in_array( $fn, $map, true ) ) { return array( '__err' => 'Hàm không hợp lệ: ' . $fn ); }
		try {
			return call_user_func( array( __CLASS__, 'rpc_' . $fn ), $args );
		} catch ( Exception $e ) {
			return array( '__err' => $e->getMessage() );
		}
	}

	// ── Lấy toàn bộ giao dịch (GiaoDich) dạng mảng assoc, giống Code.gs ──
	private static function gd_all() {
		global $wpdb; $tbl = self::tbl();
		$rows = $wpdb->get_results( "SELECT sepay_id, ngay_gd, so_tk, ngan_hang, loai, tien, luy_ke, noi_dung, ma_gd, nhan, nguon, tao_luc FROM $tbl ORDER BY ngay_gd ASC, id ASC", ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$vao = 'in' === $r['loai'] ? (int) $r['tien'] : 0; $ra = 'out' === $r['loai'] ? (int) $r['tien'] : 0;
			$out[] = array(
				'sepayId' => $r['sepay_id'], 'ngayGD' => self::ymd2vn( $r['ngay_gd'] ), 'nganHang' => $r['ngan_hang'],
				'soTK' => $r['so_tk'], 'vao' => $vao, 'ra' => $ra, 'soDu' => is_null( $r['luy_ke'] ) ? null : (int) $r['luy_ke'],
				'noiDung' => (string) $r['noi_dung'], 'maGD' => $r['ma_gd'], 'loai' => $vao > 0 ? 'Thu' : 'Chi',
				'nhan' => $r['nhan'], 'nguon' => $r['nguon'], 'dongBo' => self::ymd2vn( $r['tao_luc'] ),
			);
		}
		return $out;
	}
	private static function trong_ky( $ng, $tu, $den ) {
		$m = self::moc( $ng );
		if ( $tu && $m < self::moc( $tu ) ) { return false; }
		if ( $den && $m > self::moc( $den ) + 86399 ) { return false; }
		return true;
	}
	private static function pn_by_tk() { $o = array(); foreach ( self::ds_tk() as $t ) { $o[ $t['soTK'] ] = isset( $t['phapNhan'] ) ? $t['phapNhan'] : ''; } return $o; }

	// ── checkPin / getConfig / saveCauHinh / doiPin ──
	public static function rpc_checkPin( $a ) { $pin = isset( $a[0] ) ? (string) $a[0] : ''; return array( 'ok' => '' !== (string) get_option( 'saoke_pin', '' ) && hash_equals( (string) get_option( 'saoke_pin', '' ), $pin ) ); }
	public static function rpc_getConfig( $a ) {
		$pin = isset( $a[0] ) ? (string) $a[0] : '';
		$authed = '' !== (string) get_option( 'saoke_pin', '' ) && hash_equals( (string) get_option( 'saoke_pin', '' ), $pin );
		$cfg = array( 'ok' => true, 'authed' => $authed, 'today' => gmdate( 'd/m/Y', current_time( 'timestamp' ) ) );
		if ( ! $authed ) { return $cfg; }
		$key = (string) get_option( 'saoke_webhook_key', '' );
		$base = esc_url_raw( rest_url( self::NS . '/webhook' ) );
		$cfg['taiKhoan'] = self::ds_tk();
		$cfg['danhMuc']  = self::ds_dm();
		$cfg['phapNhanOpts'] = self::pn_opts();
		$cfg['hasApiToken']  = '' !== (string) get_option( 'saoke_api_token', '' );
		$cfg['hasWebhookKey'] = '' !== $key;
		$cfg['webhookUrl'] = '' !== $key ? ( $base . '?key=' . rawurlencode( $key ) ) : $base;
		$cfg['keyCanMaHoa'] = (bool) preg_match( '/[^A-Za-z0-9._~-]/', $key );
		$cfg['moiLink'] = array();
		if ( '' !== $key ) {
			$cfg['moiLink'][] = array( 'ten' => 'SePay (sao kê ngân hàng)', 'url' => $cfg['webhookUrl'] );
			$ten = self::cong_ten();
			foreach ( self::cong_ds() as $n ) { $cfg['moiLink'][] = array( 'ten' => 'Cổng ' . $ten[ $n ], 'url' => $cfg['webhookUrl'] . '&src=' . $n ); }
		}
		// v0.4 kèm theo (không phá app gốc): VietQR sheet + auto-sync để tab Cấu hình web plugin dùng.
		$cfg['vqrSheet'] = (string) get_option( 'saoke_vqr_sheet', '' );
		return $cfg;
	}
	public static function rpc_saveCauHinh( $a ) {
		self::can_pin( $a );
		$token = isset( $a[1] ) ? trim( (string) $a[1] ) : ''; $key = isset( $a[2] ) ? trim( (string) $a[2] ) : '';
		if ( '' !== $token ) { update_option( 'saoke_api_token', $token ); }
		if ( '' !== $key ) { update_option( 'saoke_webhook_key', $key ); }
		return array( 'ok' => true );
	}
	public static function rpc_doiPin( $a ) {
		$cu = isset( $a[0] ) ? (string) $a[0] : ''; $moi = isset( $a[1] ) ? preg_replace( '/\D+/', '', (string) $a[1] ) : '';
		if ( ! hash_equals( (string) get_option( 'saoke_pin', '' ), $cu ) ) { self::loi( 'Sai mã PIN' ); }
		if ( ! preg_match( '/^\d{4,8}$/', $moi ) ) { self::loi( 'PIN mới phải 4-8 chữ số' ); }
		update_option( 'saoke_pin', $moi );
		return array( 'ok' => true );
	}

	// ── Tài khoản / Danh mục ──
	public static function rpc_saveTaiKhoan( $a ) {
		self::can_pin( $a );
		$so = preg_replace( '/\s+/', '', isset( $a[1] ) ? (string) $a[1] : '' );
		if ( '' === $so ) { self::loi( 'Thiếu số TK' ); }
		$pn = isset( $a[5] ) ? (string) $a[5] : '';
		if ( '' !== $pn && ! in_array( $pn, self::pn_opts(), true ) ) { self::loi( 'Pháp nhân không hợp lệ' ); }
		$moi = array( 'soTK' => $so, 'nganHang' => sanitize_text_field( (string) ( isset( $a[2] ) ? $a[2] : '' ) ),
			'chuTK' => sanitize_text_field( (string) ( isset( $a[3] ) ? $a[3] : '' ) ), 'ghiChu' => sanitize_text_field( (string) ( isset( $a[4] ) ? $a[4] : '' ) ),
			'phapNhan' => $pn, 'soDuDauKy' => (int) round( self::num( isset( $a[6] ) ? $a[6] : 0 ) ), 'ngayDauKy' => sanitize_text_field( (string) ( isset( $a[7] ) ? $a[7] : '' ) ) );
		$ds = self::ds_tk(); $thay = false;
		foreach ( $ds as $k => $v ) { if ( $v['soTK'] === $so ) { $ds[ $k ] = $moi; $thay = true; break; } }
		if ( ! $thay ) { $ds[] = $moi; }
		update_option( 'saoke_taikhoan', array_values( $ds ) );
		return array( 'ok' => true );
	}
	public static function rpc_xoaTaiKhoan( $a ) {
		self::can_pin( $a ); $so = isset( $a[1] ) ? (string) $a[1] : ''; $ra = array();
		foreach ( self::ds_tk() as $v ) { if ( $v['soTK'] !== $so ) { $ra[] = $v; } }
		update_option( 'saoke_taikhoan', array_values( $ra ) );
		return array( 'ok' => true );
	}
	public static function rpc_saveDanhMuc( $a ) {
		self::can_pin( $a );
		$ten = sanitize_text_field( isset( $a[1] ) ? (string) $a[1] : '' );
		if ( '' === $ten ) { self::loi( 'Thiếu tên nhóm' ); }
		$mau = sanitize_hex_color( isset( $a[3] ) ? (string) $a[3] : '' ); if ( ! $mau ) { $mau = '#94a3b8'; }
		$moi = array( 'ten' => $ten, 'loai' => ( 'Thu' === ( isset( $a[2] ) ? $a[2] : '' ) ? 'Thu' : 'Chi' ), 'mau' => $mau );
		$ds = self::ds_dm(); $thay = false;
		foreach ( $ds as $k => $v ) { if ( $v['ten'] === $ten ) { $ds[ $k ] = $moi; $thay = true; break; } }
		if ( ! $thay ) { $ds[] = $moi; }
		update_option( 'saoke_danhmuc', array_values( $ds ) );
		return array( 'ok' => true );
	}
	public static function rpc_xoaDanhMuc( $a ) {
		self::can_pin( $a ); $ten = isset( $a[1] ) ? (string) $a[1] : ''; $ra = array();
		foreach ( self::ds_dm() as $v ) { if ( $v['ten'] !== $ten ) { $ra[] = $v; } }
		update_option( 'saoke_danhmuc', array_values( $ra ) );
		return array( 'ok' => true );
	}
	public static function rpc_setNhan( $a ) {
		self::can_pin( $a ); global $wpdb;
		$wpdb->update( self::tbl(), array( 'nhan' => sanitize_text_field( isset( $a[2] ) ? (string) $a[2] : '' ) ), array( 'sepay_id' => isset( $a[1] ) ? (string) $a[1] : '' ) );
		return array( 'ok' => true );
	}
	public static function rpc_testWebhookSample( $a ) {
		self::can_pin( $a );
		$b = json_decode( isset( $a[1] ) ? (string) $a[1] : '', true );
		if ( ! is_array( $b ) ) { self::loi( 'JSON không hợp lệ' ); }
		$g = function ( $k, $d = '' ) use ( $b ) { return isset( $b[ $k ] ) ? $b[ $k ] : $d; };
		$vao = 0; $ra = 0; $amount = self::num( $g( 'transferAmount' ) );
		if ( 'out' === strtolower( (string) $g( 'transferType', 'in' ) ) ) { $ra = $amount; } else { $vao = $amount; }
		return array( 'ok' => true, 'mapped' => array(
			'sepayId' => (string) $g( 'id' ), 'ngayGD' => (string) $g( 'transactionDate' ), 'nganHang' => (string) $g( 'gateway' ),
			'soTK' => (string) $g( 'accountNumber' ), 'vao' => $vao, 'ra' => $ra, 'soDu' => self::num( $g( 'accumulated' ) ),
			'noiDung' => (string) $g( 'content' ), 'maGD' => (string) ( '' !== (string) $g( 'referenceCode' ) ? $g( 'referenceCode' ) : $g( 'code' ) ), 'nguon' => 'Webhook',
		) );
	}
	public static function rpc_syncSepayHistory( $a ) {
		self::can_pin( $a );
		$r = self::dong_bo( self::vn2ymd( isset( $a[1] ) ? (string) $a[1] : '' ), self::vn2ymd( isset( $a[2] ) ? (string) $a[2] : '' ), isset( $a[3] ) ? (string) $a[3] : '' );
		if ( is_wp_error( $r ) ) { self::loi( $r->get_error_message() ); }
		return array( 'ok' => true, 'moi' => $r['moi'], 'trung' => $r['trung'] );
	}
	public static function rpc_luuTuKhoaCong( $a ) {
		self::can_pin( $a ); $nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) ); $tk = trim( isset( $a[2] ) ? (string) $a[2] : '' );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		if ( '' === $tk ) { return array( 'ok' => false, 'error' => 'Từ khoá rỗng thì không lọc được dòng nào trong sao kê' ); }
		$o = get_option( 'saoke_cong_tukhoa' ); $o = is_array( $o ) ? $o : array(); $o[ $nguon ] = $tk;
		update_option( 'saoke_cong_tukhoa', $o );
		return array( 'ok' => true, 'nguon' => $nguon, 'tuKhoa' => $tk );
	}
	public static function rpc_luuCotFileCong( $a ) {
		self::can_pin( $a ); $nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) ); $cot = isset( $a[2] ) && is_array( $a[2] ) ? $a[2] : array();
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ' ); }
		update_option( 'saoke_cot_' . $nguon, array_map( 'intval', $cot ) );
		return array( 'ok' => true, 'cot' => $cot );
	}
	public static function rpc_luuCotTxCong( $a ) {
		self::can_pin( $a ); $nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) ); $cot = isset( $a[2] ) && is_array( $a[2] ) ? $a[2] : array();
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ' ); }
		update_option( 'saoke_cottx_' . $nguon, array_map( 'intval', $cot ) );
		return array( 'ok' => true, 'cot' => $cot );
	}
	public static function rpc_dsCuaHangChuan( $a ) {
		self::can_pin( $a ); $ds = array();
		foreach ( self::ds_diem() as $d ) { $ds[] = array( 'ma' => $d['ma'], 'ten' => '' !== ( isset( $d['ten'] ) ? $d['ten'] : '' ) ? $d['ten'] : $d['ma'], 'maDiem' => isset( $d['maDiem'] ) ? $d['maDiem'] : '' ); }
		return array( 'ok' => true, 'ds' => $ds );
	}
	public static function rpc_napLaiDanhSachDiem( $a ) {
		self::can_pin( $a ); $ds = self::ds_diem();
		return array( 'ok' => true, 'soDiem' => count( $ds ), 'soHangDoc' => count( $ds ), 'ssId' => '', 'tab' => 'điểm nộp (plugin)', 'nguon' => 'Danh sách điểm trong plugin' );
	}

	// ── Tổng quan (getDashboard) ──
	public static function rpc_getDashboard( $a ) {
		self::can_pin( $a );
		$gd = self::gd_all();
		$soDuTK = array(); $phatSinhTK = array(); $thangMap = array(); $nhomChi = array();
		$tongThuBank = 0; $tongThuCong = 0; $congTheoNguon = array();
		foreach ( $gd as $r ) {
			$soTK = $r['soTK']; $vao = $r['vao']; $ra = $r['ra'];
			$ngTien = $vao > 0 ? self::nguon_tien_dong( $r['noiDung'] ) : '';
			if ( $vao > 0 ) { if ( '' !== $ngTien ) { $tongThuCong += $vao; $congTheoNguon[ $ngTien ] = ( isset( $congTheoNguon[ $ngTien ] ) ? $congTheoNguon[ $ngTien ] : 0 ) + $vao; } else { $tongThuBank += $vao; } }
			if ( '' !== $soTK ) {
				if ( ! is_null( $r['soDu'] ) ) { $soDuTK[ $soTK ] = $r['soDu']; }
				$phatSinhTK[ $soTK ] = ( isset( $phatSinhTK[ $soTK ] ) ? $phatSinhTK[ $soTK ] : 0 ) + $vao - $ra;
			}
			$mm = self::moc( $r['ngayGD'] );
			if ( $mm ) {
				$key = gmdate( 'Y-m', $mm );
				if ( ! isset( $thangMap[ $key ] ) ) { $thangMap[ $key ] = array( 'thang' => $key, 'thu' => 0, 'chi' => 0, 'thuBank' => 0, 'thuCong' => 0 ); }
				$thangMap[ $key ]['thu'] += $vao; $thangMap[ $key ]['chi'] += $ra;
				if ( $vao > 0 ) { if ( '' !== $ngTien ) { $thangMap[ $key ]['thuCong'] += $vao; } else { $thangMap[ $key ]['thuBank'] += $vao; } }
			}
			if ( $ra > 0 ) { $nk = '' !== $r['nhan'] ? $r['nhan'] : 'Chưa phân loại'; $nhomChi[ $nk ] = ( isset( $nhomChi[ $nk ] ) ? $nhomChi[ $nk ] : 0 ) + $ra; }
		}
		$tks = self::ds_tk(); $tk = array(); foreach ( $tks as $t ) { $tk[ $t['soTK'] ] = $t; }
		$soTKs = array();
		foreach ( array_keys( $phatSinhTK ) as $k ) { $soTKs[ $k ] = 1; }
		foreach ( array_keys( $soDuTK ) as $k ) { $soTKs[ $k ] = 1; }
		foreach ( $tks as $t ) { if ( ! empty( $t['soDuDauKy'] ) ) { $soTKs[ $t['soTK'] ] = 1; } }
		$tongHop = array();
		foreach ( array_keys( $soTKs ) as $k ) {
			$t = isset( $tk[ $k ] ) ? $tk[ $k ] : array(); $dauKy = isset( $t['soDuDauKy'] ) ? (int) $t['soDuDauKy'] : 0;
			$coTT = array_key_exists( $k, $soDuTK ); $thucTe = $coTT ? $soDuTK[ $k ] : null;
			$tinh = $dauKy + ( isset( $phatSinhTK[ $k ] ) ? $phatSinhTK[ $k ] : 0 );
			$tongHop[] = array( 'soTK' => $k, 'nganHang' => isset( $t['nganHang'] ) ? $t['nganHang'] : '', 'chuTK' => isset( $t['chuTK'] ) ? $t['chuTK'] : '',
				'phapNhan' => isset( $t['phapNhan'] ) ? $t['phapNhan'] : '', 'soDuDauKy' => $dauKy, 'ngayDauKy' => isset( $t['ngayDauKy'] ) ? $t['ngayDauKy'] : '',
				'soDu' => $coTT ? $thucTe : $tinh, 'soDuTinhToan' => $tinh, 'coSoDuThucTe' => $coTT, 'soDuThucTe' => $thucTe, 'chenhLech' => $coTT ? ( $thucTe - $tinh ) : 0 );
		}
		ksort( $thangMap ); $thangArr = array_values( array_slice( $thangMap, -12 ) );
		$nhomArr = array(); foreach ( $nhomChi as $k => $vv ) { $nhomArr[] = array( 'nhom' => $k, 'tong' => $vv ); }
		usort( $nhomArr, function ( $x, $y ) { return $y['tong'] - $x['tong']; } );
		$tongSoDu = 0; $pnMap = array();
		foreach ( $tongHop as $t ) { $tongSoDu += $t['soDu']; $pk = '' !== $t['phapNhan'] ? $t['phapNhan'] : 'Chưa gán'; $pnMap[ $pk ] = ( isset( $pnMap[ $pk ] ) ? $pnMap[ $pk ] : 0 ) + $t['soDu']; }
		$theoPN = array(); foreach ( $pnMap as $k => $vv ) { $theoPN[] = array( 'phapNhan' => $k, 'soDu' => $vv ); }
		usort( $theoPN, function ( $x, $y ) { return $y['soDu'] - $x['soDu']; } );
		$ten = self::cong_ten(); $ctn = array();
		foreach ( self::cong_ds() as $n ) { if ( ! empty( $congTheoNguon[ $n ] ) ) { $ctn[] = array( 'nguon' => $n, 'ten' => $ten[ $n ], 'soTien' => $congTheoNguon[ $n ] ); } }
		return array( 'ok' => true, 'taiKhoan' => $tongHop, 'tongSoDu' => $tongSoDu, 'theoThang' => $thangArr, 'nhomChi' => $nhomArr,
			'theoPhapNhan' => $theoPN, 'tongThuBank' => $tongThuBank, 'tongThuCong' => $tongThuCong, 'congTheoNguon' => $ctn );
	}

	// ── Sao kê (getGiaoDich) ──
	public static function rpc_getGiaoDich( $a ) {
		self::can_pin( $a );
		$f = isset( $a[1] ) && is_array( $a[1] ) ? $a[1] : array();
		$gv = function ( $k ) use ( $f ) { return isset( $f[ $k ] ) ? trim( (string) $f[ $k ] ) : ''; };
		$pn = self::pn_by_tk();
		$locNguon = $gv( 'nguonTien' ); $tu = $gv( 'tuNgay' ); $den = $gv( 'denNgay' ); $tuKhoa = mb_strtolower( $gv( 'tuKhoa' ) );
		$rows = array(); $tongVao = 0; $tongRa = 0; $tongVaoCong = 0; $tongVaoBank = 0; $congTheoNguon = array();
		$gd = self::gd_all();
		for ( $i = count( $gd ) - 1; $i >= 0; $i-- ) {
			$o = $gd[ $i ]; $o['phapNhan'] = isset( $pn[ $o['soTK'] ] ) ? $pn[ $o['soTK'] ] : '';
			$o['nguonTien'] = $o['vao'] > 0 ? self::nguon_tien_dong( $o['noiDung'] ) : '';
			$o['laCong'] = '' !== $o['nguonTien'];
			$ten = self::cong_ten();
			$o['tenNguonTien'] = $o['vao'] <= 0 ? '' : ( $o['nguonTien'] ? ( 'Cổng ' . ( isset( $ten[ $o['nguonTien'] ] ) ? $ten[ $o['nguonTien'] ] : $o['nguonTien'] ) ) : 'Nộp trực tiếp' );
			if ( '' !== $gv( 'soTK' ) && $o['soTK'] !== $gv( 'soTK' ) ) { continue; }
			if ( '' !== $gv( 'loai' ) && $o['loai'] !== $gv( 'loai' ) ) { continue; }
			if ( '' !== $gv( 'phapNhan' ) && $o['phapNhan'] !== $gv( 'phapNhan' ) ) { continue; }
			if ( 'CHUA_PHAN_LOAI' === $gv( 'nhan' ) && '' !== $o['nhan'] ) { continue; }
			if ( '' !== $gv( 'nhan' ) && 'CHUA_PHAN_LOAI' !== $gv( 'nhan' ) && $o['nhan'] !== $gv( 'nhan' ) ) { continue; }
			if ( '' !== $tuKhoa && false === mb_strpos( mb_strtolower( $o['noiDung'] ), $tuKhoa ) ) { continue; }
			if ( ! self::trong_ky( $o['ngayGD'], $tu ? self::ymd2vn_ngay( self::vn2ymd( $tu ) ) : '', $den ? self::ymd2vn_ngay( self::vn2ymd( $den ) ) : '' ) ) { continue; }
			if ( 'bank' === $locNguon && $o['laCong'] ) { continue; }
			if ( 'cong' === $locNguon && ! $o['laCong'] ) { continue; }
			if ( '' !== $locNguon && 'bank' !== $locNguon && 'cong' !== $locNguon && $o['nguonTien'] !== $locNguon ) { continue; }
			$rows[] = $o; $tongVao += $o['vao']; $tongRa += $o['ra'];
			if ( $o['laCong'] ) { $tongVaoCong += $o['vao']; $congTheoNguon[ $o['nguonTien'] ] = ( isset( $congTheoNguon[ $o['nguonTien'] ] ) ? $congTheoNguon[ $o['nguonTien'] ] : 0 ) + $o['vao']; } else { $tongVaoBank += $o['vao']; }
			if ( count( $rows ) >= 2000 ) { break; }
		}
		$ten = self::cong_ten(); $ctn = array(); $tkc = array();
		foreach ( self::cong_ds() as $n ) { if ( ! empty( $congTheoNguon[ $n ] ) ) { $ctn[] = array( 'nguon' => $n, 'ten' => $ten[ $n ], 'soTien' => $congTheoNguon[ $n ] ); } $tkc[] = array( 'nguon' => $n, 'ten' => $ten[ $n ], 'tuKhoa' => self::cong_tukhoa( $n ) ); }
		return array( 'ok' => true, 'rows' => $rows, 'tongVao' => $tongVao, 'tongRa' => $tongRa, 'soDong' => count( $rows ),
			'tongVaoBank' => $tongVaoBank, 'tongVaoCong' => $tongVaoCong, 'congTheoNguon' => $ctn, 'tuKhoaCong' => $tkc );
	}

	// ── Cầu nối: dựng WP_REST_Request rồi gọi lại handler v0.2 (DRY, đúng shape đã kiểm) ──
	private static function req( $params ) {
		$r = new WP_REST_Request( 'POST', '/' . self::NS . '/rpc' );
		foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); }
		return $r;
	}
	private static function un_err( $res ) { // lỗi -> ném (về {__err}, hợp với withFailureHandler)
		if ( is_wp_error( $res ) ) { self::loi( $res->get_error_message() ); }
		if ( $res instanceof WP_REST_Response ) { $res = $res->get_data(); }
		return $res;
	}
	private static function soft_err( $res ) { // lỗi -> {ok:false,error} (hợp với withSuccessHandler kiểm r.ok)
		if ( is_wp_error( $res ) ) { return array( 'ok' => false, 'error' => $res->get_error_message() ); }
		if ( $res instanceof WP_REST_Response ) { $res = $res->get_data(); }
		return $res;
	}
	private static function cot_file_cong( $nguon ) {
		$o = get_option( 'saoke_cot_' . $nguon ); if ( ! is_array( $o ) ) { $o = array(); }
		return array( 'ngay' => isset( $o['ngay'] ) ? (int) $o['ngay'] : -1, 'soTien' => isset( $o['soTien'] ) ? (int) $o['soTien'] : -1,
			'cuaHang' => isset( $o['cuaHang'] ) ? (int) $o['cuaHang'] : 15, 'maCH' => isset( $o['maCH'] ) ? (int) $o['maCH'] : 14 );
	}

	// ── Đối chiếu nộp tiền (getDoiChieuNop) ──
	public static function rpc_getDoiChieuNop( $a ) {
		self::can_pin( $a );
		$res = self::un_err( self::r_doichieu( self::req( array( 'pin' => $a[0], 'tuNgay' => isset( $a[1] ) ? $a[1] : '', 'denNgay' => isset( $a[2] ) ? $a[2] : '' ) ) ) );
		if ( ! isset( $res['canhBao'] ) ) { $res['canhBao'] = array( 'trungMa' => array(), 'thieuMa' => array() ); }
		$res['ssId'] = '';
		return $res;
	}

	// ── Tổng hợp doanh thu cơ sở (getTongHopCoSo) ──
	public static function rpc_getTongHopCoSo( $a ) {
		self::can_pin( $a );
		$res = self::un_err( self::r_tonghop_coso( self::req( array( 'pin' => $a[0], 'tuNgay' => isset( $a[1] ) ? $a[1] : '', 'denNgay' => isset( $a[2] ) ? $a[2] : '' ) ) ) );
		if ( ! isset( $res['canhBao'] ) ) { $res['canhBao'] = array( 'trungMa' => array(), 'thieuMa' => array() ); }
		return $res;
	}

	// ── Sao kê 1 cổng (getSaoKeCong) — 2 chiều (cổng ↔ bank) + đối soát, port đủ shape ──
	public static function rpc_getSaoKeCong( $a ) {
		self::can_pin( $a ); global $wpdb;
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { self::loi( 'Nguồn không hợp lệ: ' . $nguon ); }
		$tu = self::vn2ymd( isset( $a[2] ) ? (string) $a[2] : '' ); $den = self::vn2ymd( isset( $a[3] ) ? (string) $a[3] : '' );
		$tuKhoa = self::cong_tukhoa( $nguon ); $anhXa = self::ds_anhxa( $nguon ); $mapTen = self::map_ten_diem();
		$tc = self::tbl_cong();
		$rowsC = $wpdb->get_results( $wpdb->prepare( "SELECT khoa, ma_gd, ref, thoi_diem, so_tien, huong, trang_thai, so_tk, noi_dung, diem_ban, doc_duoc, raw, nhan_luc FROM $tc WHERE nguon=%s ORDER BY thoi_diem DESC, id DESC LIMIT 5000", $nguon ), ARRAY_A );
		$cong = array(); $congTien = 0; $congKho = 0; $khoRows = array(); $tongMoiNguon = 0; $payloadCuoi = '';
		$chuaAnhXa = array(); $chuaRoMay = 0; $chuaRoTien = 0;
		foreach ( (array) $rowsC as $r ) {
			$tongMoiNguon++;
			if ( '' === $payloadCuoi ) { $payloadCuoi = (string) $r['raw']; }
			$thoiDiem = self::ymd2vn( $r['thoi_diem'] );
			if ( (int) $r['doc_duoc'] !== 1 ) { $congKho++; if ( count( $khoRows ) < 20 ) { $khoRows[] = array( 'khoa' => $r['khoa'], 'nhanLuc' => self::ymd2vn( $r['nhan_luc'] ), 'raw' => mb_substr( (string) $r['raw'], 0, 400 ) ); } continue; }
			$ngayY = self::vn2ymd_soft( $thoiDiem );
			if ( $tu && $ngayY && $ngayY < $tu ) { continue; }
			if ( $den && $ngayY && $ngayY > $den ) { continue; }
			if ( 'Đi' === $r['huong'] ) { continue; }
			$tenMay = self::cong_ten_may( $r['noi_dung'] ); if ( '' === $tenMay ) { $tenMay = strtoupper( (string) $r['diem_ban'] ); }
			$coSo = self::cong_coso( $tenMay );
			$ax = self::ax_theo_ngay( isset( $anhXa[ self::chuan_ch( $tenMay ) ] ) ? $anhXa[ self::chuan_ch( $tenMay ) ] : ( isset( $anhXa[ self::chuan_ch( $coSo ) ] ) ? $anhXa[ self::chuan_ch( $coSo ) ] : null ), $thoiDiem );
			$suy = self::ax_ma_nop( $ax, $mapTen ); $soTien = (int) $r['so_tien'];
			if ( '' === $tenMay ) { $chuaRoMay++; $chuaRoTien += $soTien; }
			elseif ( '' === $suy['ma'] ) { $k = self::chuan_ch( $coSo ); if ( ! isset( $chuaAnhXa[ $k ] ) ) { $chuaAnhXa[ $k ] = array( 'ten' => $coSo, 'soTien' => 0, 'soLan' => 0, 'may' => array(), 'vi' => $suy['vi'] ); } $chuaAnhXa[ $k ]['soTien'] += $soTien; $chuaAnhXa[ $k ]['soLan']++; $chuaAnhXa[ $k ]['may'][ $tenMay ] = 1; }
			$cong[] = array( 'khoa' => $r['khoa'], 'maGD' => $r['ma_gd'], 'ref' => $r['ref'], 'thoiDiem' => $thoiDiem, 'soTien' => $soTien,
				'huong' => $r['huong'], 'trangThai' => $r['trang_thai'], 'soTK' => $r['so_tk'], 'noiDung' => $r['noi_dung'], 'diemBan' => $r['diem_ban'],
				'docDuoc' => true, 'nhanLuc' => self::ymd2vn( $r['nhan_luc'] ), 'tenMay' => $tenMay, 'coSo' => $coSo,
				'cuaHangChuan' => $ax ? ( '' !== $ax['tenChuan'] ? $ax['tenChuan'] : $coSo ) : $coSo, 'maBank' => $suy['ma'], 'daAnhXa' => '' !== $suy['ma'] );
			$congTien += $soTien;
			if ( count( $cong ) >= 2000 ) { break; }
		}
		$dsChuaAnhXa = array();
		foreach ( $chuaAnhXa as $g ) { $may = array_keys( $g['may'] ); sort( $may ); $dsChuaAnhXa[] = array( 'ten' => $g['ten'], 'soTien' => $g['soTien'], 'soLan' => $g['soLan'], 'may' => $may, 'vi' => $g['vi'] ); }
		usort( $dsChuaAnhXa, function ( $x, $y ) { return $y['soTien'] - $x['soTien']; } );
		$tbl = self::tbl(); $kw = self::kd( $tuKhoa );
		$wb = array( "loai='in'" ); $ab = array();
		if ( $tu )  { $wb[] = 'DATE(ngay_gd)>=%s'; $ab[] = $tu; }
		if ( $den ) { $wb[] = 'DATE(ngay_gd)<=%s'; $ab[] = $den; }
		if ( '' !== $tuKhoa ) { $wb[] = 'noi_dung LIKE %s'; $ab[] = '%' . $wpdb->esc_like( $tuKhoa ) . '%'; }
		$sqlB = "SELECT ngay_gd, ngan_hang, so_tk, tien, noi_dung, ma_gd FROM $tbl WHERE " . implode( ' AND ', $wb ) . " ORDER BY ngay_gd DESC, id DESC LIMIT 3000";
		$rowsB = $ab ? $wpdb->get_results( $wpdb->prepare( $sqlB, $ab ), ARRAY_A ) : $wpdb->get_results( $sqlB, ARRAY_A );
		$pn = self::pn_by_tk(); $bank = array(); $bankTien = 0;
		foreach ( (array) $rowsB as $r ) { if ( '' !== $kw && false === strpos( self::kd( $r['noi_dung'] ), $kw ) ) { continue; }
			$bank[] = array( 'ngayGD' => self::ymd2vn( $r['ngay_gd'] ), 'nganHang' => $r['ngan_hang'], 'soTK' => $r['so_tk'], 'vao' => (int) $r['tien'], 'noiDung' => $r['noi_dung'], 'maGD' => $r['ma_gd'], 'phapNhan' => isset( $pn[ $r['so_tk'] ] ) ? $pn[ $r['so_tk'] ] : '' );
			$bankTien += (int) $r['tien']; }
		$key = (string) get_option( 'saoke_webhook_key', '' );
		$url = $key ? ( esc_url_raw( rest_url( self::NS . '/webhook' ) ) . '?key=' . rawurlencode( $key ) . '&src=' . $nguon ) : '';
		$ten = self::cong_ten();
		return array( 'ok' => true, 'nguon' => $nguon, 'ten' => $ten[ $nguon ], 'tuKhoa' => $tuKhoa, 'tuKhoaMacDinh' => self::cong_tukhoa_mac_dinh()[ $nguon ],
			'webhookUrl' => $url, 'thieuKey' => '' === $key, 'cong' => $cong, 'congTien' => $congTien, 'congDong' => count( $cong ), 'congTongMoiNguon' => $tongMoiNguon,
			'congKho' => $congKho, 'khoRows' => $khoRows, 'payloadCuoi' => $payloadCuoi, 'chuaAnhXa' => $dsChuaAnhXa, 'soAnhXa' => count( $anhXa ),
			'chuaRoMay' => $chuaRoMay, 'chuaRoTien' => $chuaRoTien, 'log' => array(), 'bank' => $bank, 'bankTien' => $bankTien, 'bankDong' => count( $bank ),
			'chenh' => $congTien - $bankTien, 'kieuDoiSoat' => 'vietqr' === $nguon ? '1:1' : 'N:1' );
	}

	// ── Đối soát file cổng theo tháng (getDoiSoatFile) — MoMo/VNPAY ──
	public static function rpc_getDoiSoatFile( $a ) {
		self::can_pin( $a ); global $wpdb;
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { self::loi( 'Nguồn không hợp lệ: ' . $nguon ); }
		$thang = trim( isset( $a[2] ) ? (string) $a[2] : '' ); if ( '' === $thang ) { $thang = gmdate( 'Y-m', current_time( 'timestamp' ) ); }
		$nam = (int) substr( $thang, 0, 4 ); $th = (int) substr( $thang, 5, 2 );
		if ( ! ( $nam > 2000 && $th >= 1 && $th <= 12 ) ) { self::loi( 'Tháng không hợp lệ: ' . $thang ); }
		$ngayIn = trim( isset( $a[3] ) ? (string) $a[3] : '' );
		$ngay1 = '' !== $ngayIn ? self::file_ngay( $ngayIn ) : '';
		if ( '' !== $ngayIn && '' === $ngay1 ) { self::loi( 'Ngày không hợp lệ: ' . $ngayIn ); }
		if ( $ngay1 && ( substr( $ngay1, 6, 4 ) . '-' . substr( $ngay1, 3, 2 ) ) !== $thang ) { self::loi( 'Ngày ' . $ngay1 . ' không thuộc tháng ' . $thang ); }
		$anhXa = self::ds_anhxa( $nguon ); $mapTen = self::map_ten_diem();
		$tf = self::tbl_congfile();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ngay, ch_file, ma_bank, so_tien, so_dong FROM $tf WHERE nguon=%s AND thang=%s", $nguon, $thang ), ARRAY_A );
		$theoCH = array(); $theoNgay = array(); $soNgayCo = array(); $tongFile = 0; $soDongGop = 0; $chuaAnhXa = array();
		$khongTenCH = array( 'soTien' => 0, 'soDong' => 0, 'ngay' => array() );
		foreach ( (array) $rows as $r ) {
			$ngay = (string) $r['ngay']; $chFile = (string) $r['ch_file']; $tien = (int) $r['so_tien'];
			$theoNgay[ $ngay ] = ( isset( $theoNgay[ $ngay ] ) ? $theoNgay[ $ngay ] : 0 ) + $tien;
			$soNgayCo[ $ngay ] = ( isset( $soNgayCo[ $ngay ] ) ? $soNgayCo[ $ngay ] : 0 ) + 1;
			if ( $ngay1 && $ngay !== $ngay1 ) { continue; }
			$ax = self::ax_theo_ngay( isset( $anhXa[ self::chuan_ch( $chFile ) ] ) ? $anhXa[ self::chuan_ch( $chFile ) ] : null, $ngay );
			$tenChuan = $ax ? ( '' !== $ax['tenChuan'] ? $ax['tenChuan'] : $chFile ) : $chFile;
			$suy = self::ax_ma_nop( $ax, $mapTen );
			if ( '' === $chFile ) { $khongTenCH['soTien'] += $tien; $khongTenCH['soDong']++; $khongTenCH['ngay'][ $ngay ] = 1; }
			elseif ( '' === $suy['ma'] ) { if ( ! isset( $chuaAnhXa[ $chFile ] ) ) { $chuaAnhXa[ $chFile ] = array( 'soTien' => 0, 'vi' => $suy['vi'], 'tenChuan' => $ax ? $ax['tenChuan'] : '' ); } $chuaAnhXa[ $chFile ]['soTien'] += $tien; }
			$k = self::chuan_ch( $chFile ); if ( '' === $k ) { $k = 'khongro'; }
			if ( ! isset( $theoCH[ $k ] ) ) { $theoCH[ $k ] = array( 'cuaHangFile' => $chFile, 'cuaHangChuan' => $tenChuan, 'maBank' => $suy['ma'], 'daAnhXa' => '' !== $suy['ma'], 'viChuaGan' => '' !== $suy['ma'] ? '' : $suy['vi'], 'soTien' => 0, 'soNgay' => 0, 'ngayCuoi' => '' ); }
			$theoCH[ $k ]['soTien'] += $tien; $theoCH[ $k ]['soNgay']++;
			if ( $ngay > $theoCH[ $k ]['ngayCuoi'] ) { $theoCH[ $k ]['ngayCuoi'] = $ngay; }
			$tongFile += $tien; $soDongGop += (int) $r['so_dong'];
		}
		$homNay = gmdate( 'Y-m-d', current_time( 'timestamp' ) ); $soNgayThang = (int) gmdate( 't', mktime( 0, 0, 0, $th, 1, $nam ) );
		$denNgay = $soNgayThang; if ( $thang === substr( $homNay, 0, 7 ) ) { $denNgay = (int) substr( $homNay, 8, 2 ); }
		$daCo = array(); $conThieu = array();
		for ( $d = 1; $d <= $denNgay; $d++ ) { $nx = sprintf( '%02d/%02d/%d', $d, $th, $nam ); if ( isset( $theoNgay[ $nx ] ) ) { $daCo[] = array( 'ngay' => $nx, 'soTien' => $theoNgay[ $nx ] ); } else { $conThieu[] = $nx; } }
		$tuKhoa = self::cong_tukhoa( $nguon ); $kw = self::kd( $tuKhoa );
		$tbl = self::tbl(); $pn = self::pn_by_tk(); $bank = array(); $bankTien = 0;
		$firstY = sprintf( '%04d-%02d-01', $nam, $th ); $lastY = sprintf( '%04d-%02d-%02d', $nam, $th, $soNgayThang );
		$wb = array( "loai='in'", 'DATE(ngay_gd)>=%s', 'DATE(ngay_gd)<=%s' ); $ab = array( $firstY, $lastY );
		if ( '' !== $tuKhoa ) { $wb[] = 'noi_dung LIKE %s'; $ab[] = '%' . $wpdb->esc_like( $tuKhoa ) . '%'; }
		$rowsB = $wpdb->get_results( $wpdb->prepare( "SELECT ngay_gd, ngan_hang, so_tk, tien, noi_dung, ma_gd FROM $tbl WHERE " . implode( ' AND ', $wb ) . " ORDER BY ngay_gd DESC, id DESC LIMIT 5000", $ab ), ARRAY_A );
		foreach ( (array) $rowsB as $r ) { if ( '' !== $kw && false === strpos( self::kd( $r['noi_dung'] ), $kw ) ) { continue; }
			$ngayGD = self::ymd2vn( $r['ngay_gd'] ); if ( $ngay1 && substr( $ngayGD, 0, 10 ) !== $ngay1 ) { continue; }
			$bank[] = array( 'ngayGD' => $ngayGD, 'nganHang' => $r['ngan_hang'], 'soTK' => $r['so_tk'], 'vao' => (int) $r['tien'], 'noiDung' => $r['noi_dung'], 'maGD' => $r['ma_gd'], 'phapNhan' => isset( $pn[ $r['so_tk'] ] ) ? $pn[ $r['so_tk'] ] : '' );
			$bankTien += (int) $r['tien']; }
		$dsCH = array_values( $theoCH ); usort( $dsCH, function ( $x, $y ) { return $y['soTien'] - $x['soTien']; } );
		$dsNgay = array(); $truoc = null;
		for ( $dd = 1; $dd <= $denNgay; $dd++ ) { $nx2 = sprintf( '%02d/%02d/%d', $dd, $th, $nam ); $coFile = isset( $theoNgay[ $nx2 ] ); $tienNgay = $coFile ? $theoNgay[ $nx2 ] : 0;
			$dsNgay[] = array( 'ngay' => $nx2, 'coFile' => $coFile, 'soTien' => $tienNgay, 'soCuaHang' => isset( $soNgayCo[ $nx2 ] ) ? $soNgayCo[ $nx2 ] : 0, 'chenhHomTruoc' => ( null === $truoc || ! $coFile ) ? '' : ( $tienNgay - $truoc ) );
			if ( $coFile ) { $truoc = $tienNgay; } }
		$chuaAnhXaOut = array(); ksort( $chuaAnhXa );
		foreach ( $chuaAnhXa as $t => $v ) { $chuaAnhXaOut[] = array( 'ten' => $t, 'soTien' => $v['soTien'], 'vi' => $v['vi'], 'tenChuan' => $v['tenChuan'] ); }
		$dsAnhXaOut = array(); $ks = array_keys( $anhXa ); sort( $ks ); $soAnhXaCoMa = 0;
		foreach ( $ks as $k3 ) { $coMa = false; foreach ( $anhXa[ $k3 ] as $x ) { $sm = self::ax_ma_nop( $x, $mapTen ); if ( '' !== $sm['ma'] ) { $coMa = true; }
			$dsAnhXaOut[] = array( 'tenFile' => $x['tenFile'], 'tenChuan' => $x['tenChuan'], 'maBank' => $sm['ma'], 'maGhi' => $x['maBank'], 'tuNgay' => $x['tuNgay'], 'denNgay' => $x['denNgay'], 'vi' => $sm['vi'] ); } if ( $coMa ) { $soAnhXaCoMa++; } }
		$khongTenNgay = array_keys( $khongTenCH['ngay'] ); sort( $khongTenNgay );
		return array( 'ok' => true, 'nguon' => $nguon, 'ten' => self::cong_ten()[ $nguon ], 'thang' => $thang, 'tuKhoa' => $tuKhoa,
			'cot' => self::cot_file_cong( $nguon ), 'ngayLoc' => $ngay1, 'theoNgay' => $dsNgay, 'tongThang' => array_sum( $theoNgay ),
			'tongFile' => $tongFile, 'soCuaHang' => count( $dsCH ), 'soDongGop' => $soDongGop, 'theoCuaHang' => $dsCH,
			'daCoNgay' => $daCo, 'conThieuNgay' => $conThieu, 'soNgayCanCo' => $denNgay, 'bank' => $bank, 'bankTien' => $bankTien, 'bankDong' => count( $bank ),
			'chenh' => $tongFile - $bankTien, 'chuaAnhXa' => $chuaAnhXaOut,
			'khongTenCH' => array( 'soTien' => $khongTenCH['soTien'], 'soDong' => $khongTenCH['soDong'], 'ngay' => $khongTenNgay ),
			'soAnhXa' => count( $anhXa ), 'soAnhXaCoMa' => $soAnhXaCoMa, 'dsAnhXa' => $dsAnhXaOut );
	}

	// ── Nạp file cổng (napFileCong) — MoMo/VNPAY, gộp theo ngày × cửa hàng ──
	public static function rpc_napFileCong( $a ) {
		self::can_pin( $a );
		$res = self::soft_err( self::r_nap_file_cong( self::req( array( 'pin' => $a[0], 'nguon' => isset( $a[1] ) ? $a[1] : '', 'rows' => isset( $a[2] ) ? $a[2] : array(), 'tenFile' => isset( $a[3] ) ? $a[3] : '', 'ghiDe' => ! empty( $a[4] ) ? 1 : 0 ) ) ) );
		foreach ( array( 'daDon' => 0, 'tienDon' => 0, 'dsDon' => array(), 'ngayMoi' => array(), 'ngayTrung' => array(), 'boQuaDong' => 0 ) as $k => $v ) { if ( ! isset( $res[ $k ] ) ) { $res[ $k ] = $v; } }
		return $res;
	}

	// ── Nạp bù giao dịch cổng từ file (napFileCongTx) — Việt QR, vào CongThanhToan ──
	public static function rpc_napFileCongTx( $a ) {
		self::can_pin( $a );
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		$rows = isset( $a[2] ) && is_array( $a[2] ) ? $a[2] : array();
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'error' => 'File không có dòng dữ liệu nào' ); }
		if ( count( $rows ) > 20000 ) { return array( 'ok' => false, 'error' => 'File quá lớn (' . count( $rows ) . ' dòng), tách nhỏ giúp em' ); }
		$tenFile = sanitize_text_field( isset( $a[3] ) ? (string) $a[3] : '' ); $anhXa = self::ds_anhxa( $nguon );
		$moi = 0; $trung = 0; $boQua = 0; $khongNgay = 0; $khongTien = 0; $khongMa = 0; $tongMoi = 0; $chMoi = array(); $chuaRoMay = 0;
		foreach ( $rows as $rr ) { $r = (array) $rr;
			$thoiDiem = self::cong_ngay( isset( $r[0] ) ? $r[0] : '' ); $soTien = self::num( isset( $r[1] ) ? $r[1] : 0 );
			$maGD = trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) ); $ref = trim( (string) ( isset( $r[3] ) ? $r[3] : '' ) ); $noiDung = trim( (string) ( isset( $r[4] ) ? $r[4] : '' ) );
			if ( '' === $thoiDiem ) { $khongNgay++; $boQua++; continue; }
			if ( $soTien <= 0 ) { $khongTien++; $boQua++; continue; }
			if ( '' === $maGD && '' === $ref ) { $khongMa++; $boQua++; continue; }
			$tx = array( 'nguon' => $nguon, 'maGD' => $maGD, 'ref' => $ref, 'thoiDiem' => $thoiDiem, 'soTien' => $soTien, 'huong' => 'Đến', 'trangThai' => '', 'soTK' => '', 'noiDung' => $noiDung, 'diemBan' => '', 'docDuoc' => true );
			$tx['khoa'] = self::cong_khoa( $nguon, $tx, wp_json_encode( $r ) ); $tx['raw'] = 'FILE ' . $tenFile . ' · ' . mb_substr( (string) wp_json_encode( $r ), 0, 1500 );
			$tenMay = self::cong_ten_may( $noiDung );
			if ( '' === $tenMay ) { $chuaRoMay++; }
			elseif ( ! self::ax_theo_ngay( isset( $anhXa[ self::chuan_ch( $tenMay ) ] ) ? $anhXa[ self::chuan_ch( $tenMay ) ] : ( isset( $anhXa[ self::chuan_ch( self::cong_coso( $tenMay ) ) ] ) ? $anhXa[ self::chuan_ch( self::cong_coso( $tenMay ) ) ] : null ), $thoiDiem ) ) { $chMoi[ self::cong_coso( $tenMay ) ] = 1; }
			if ( self::luu_cong( $tx ) ) { $moi++; $tongMoi += $soTien; } else { $trung++; }
		}
		$cm = array_keys( $chMoi ); sort( $cm );
		return array( 'ok' => true, 'nguon' => $nguon, 'tenFile' => $tenFile, 'soDongFile' => count( $rows ), 'themMoi' => $moi, 'trungBoQua' => $trung,
			'tongTienThem' => $tongMoi, 'boQuaDong' => $boQua, 'khongNgay' => $khongNgay, 'khongTien' => $khongTien, 'khongMa' => $khongMa, 'chuaRoMay' => $chuaRoMay, 'cuaHangMoi' => $cm );
	}

	// ── Ánh xạ cửa hàng (luuAnhXaCuaHang) ──
	public static function rpc_luuAnhXaCuaHang( $a ) {
		self::can_pin( $a );
		return self::soft_err( self::r_anhxa_luu( self::req( array( 'pin' => $a[0], 'nguon' => isset( $a[1] ) ? $a[1] : '', 'tenFile' => isset( $a[2] ) ? $a[2] : '',
			'tenChuan' => isset( $a[3] ) ? $a[3] : '', 'maBank' => isset( $a[4] ) ? $a[4] : '', 'tuNgay' => isset( $a[5] ) ? $a[5] : '', 'denNgay' => isset( $a[6] ) ? $a[6] : '' ) ) ) );
	}

	// ── Xoá ánh xạ (xoaAnhXaCuaHang) ──
	public static function rpc_xoaAnhXaCuaHang( $a ) {
		self::can_pin( $a );
		$res = self::soft_err( self::r_anhxa_xoa( self::req( array( 'pin' => $a[0], 'nguon' => isset( $a[1] ) ? $a[1] : '', 'tenFile' => isset( $a[2] ) ? $a[2] : '', 'tuNgay' => isset( $a[3] ) ? $a[3] : '', 'denNgay' => isset( $a[4] ) ? $a[4] : '' ) ) ) );
		if ( isset( $res['daXoa'] ) && (int) $res['daXoa'] === 0 ) { return array( 'ok' => false, 'error' => 'Không thấy ánh xạ này — cửa hàng có nhiều dòng (chuyển gian) thì phải chỉ rõ khoảng ngày.' ); }
		return $res;
	}

	// ── Chuyển gian sang cơ sở mới (chuyenGianCuaHang) — tự chốt ngày cũ, mở dòng mới ──
	public static function rpc_chuyenGianCuaHang( $a ) {
		self::can_pin( $a );
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		$tf = trim( isset( $a[2] ) ? (string) $a[2] : '' ); if ( '' === $tf ) { return array( 'ok' => false, 'error' => 'Thiếu tên cửa hàng ở file cổng' ); }
		$nc = self::file_ngay( isset( $a[3] ) ? $a[3] : '' ); if ( '' === $nc ) { return array( 'ok' => false, 'error' => 'Ngày chuyển giao không hợp lệ: ' . ( isset( $a[3] ) ? $a[3] : '' ) ); }
		$cu = self::ds_anhxa( $nguon ); $k = self::chuan_ch( $tf ); $ds = isset( $cu[ $k ] ) ? $cu[ $k ] : array();
		if ( ! count( $ds ) ) { return array( 'ok' => false, 'error' => 'Cửa hàng "' . $tf . '" chưa có ánh xạ nào — gán cơ sở hiện tại trước, rồi mới chuyển gian.' ); }
		$truoc = self::ax_theo_ngay( $ds, $nc ); if ( ! $truoc ) { return array( 'ok' => false, 'error' => 'Không có ánh xạ nào phủ ngày ' . $nc . ' — kiểm lại khoảng ngày đang khai.' ); }
		if ( '' !== $truoc['denNgay'] && self::moc( $truoc['denNgay'] ) <= self::moc( $nc ) ) { return array( 'ok' => false, 'error' => 'Ánh xạ hiện hành đã chốt tới ' . $truoc['denNgay'] . ', không cần chuyển ở ngày ' . $nc . '.' ); }
		$tcMoi = trim( isset( $a[4] ) ? (string) $a[4] : '' ); $mbMoi = trim( isset( $a[5] ) ? (string) $a[5] : '' );
		if ( '' === $tcMoi && '' === $mbMoi ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở MỚI (tên chuẩn hoặc mã nộp tiền)' ); }
		$mapTen = self::map_ten_diem();
		$suyMoi = self::ax_ma_nop( array( 'maBank' => $mbMoi, 'tenChuan' => $tcMoi ), $mapTen );
		if ( '' === $suyMoi['ma'] ) { return array( 'ok' => false, 'error' => 'Cơ sở mới chưa xác định được mã nộp tiền: ' . $suyMoi['vi'] ); }
		$maTruoc = self::ax_ma_nop( $truoc, $mapTen ); $maTruoc = $maTruoc['ma'];
		if ( $suyMoi['ma'] === $maTruoc ) { return array( 'ok' => false, 'error' => 'Cơ sở mới trùng cơ sở đang dùng (' . $suyMoi['ma'] . ') — không có gì để chuyển.' ); }
		$all = get_option( 'saoke_anhxa' ); $all = is_array( $all ) ? $all : array();
		foreach ( $all as &$r ) {
			if ( strtolower( (string) ( isset( $r['nguon'] ) ? $r['nguon'] : '' ) ) === $nguon && self::chuan_ch( isset( $r['tenFile'] ) ? $r['tenFile'] : '' ) === $k
				&& (string) ( isset( $r['tuNgay'] ) ? $r['tuNgay'] : '' ) === $truoc['tuNgay'] && (string) ( isset( $r['denNgay'] ) ? $r['denNgay'] : '' ) === $truoc['denNgay'] ) { $r['denNgay'] = $nc; break; }
		}
		unset( $r ); update_option( 'saoke_anhxa', array_values( $all ) );
		$tuMoi = gmdate( 'd/m/Y', self::moc( $nc ) + 86400 );
		$r2 = self::soft_err( self::r_anhxa_luu( self::req( array( 'pin' => $a[0], 'nguon' => $nguon, 'tenFile' => $tf, 'tenChuan' => $tcMoi, 'maBank' => $suyMoi['ma'], 'tuNgay' => $tuMoi, 'denNgay' => $truoc['denNgay'] ) ) ) );
		if ( empty( $r2['ok'] ) ) { return $r2; }
		return array( 'ok' => true, 'tenFile' => $tf, 'ngayChuyen' => $nc, 'cuTen' => $truoc['tenChuan'], 'cuDen' => $nc,
			'moiTen' => '' !== $tcMoi ? $tcMoi : $suyMoi['ma'], 'moiMa' => $suyMoi['ma'], 'moiTu' => $tuMoi, 'daVa' => isset( $r2['daVa'] ) ? $r2['daVa'] : 0 );
	}

	// ── Xoá dữ liệu file đã nạp của 1 ngày (xoaNgayFileCong) ──
	public static function rpc_xoaNgayFileCong( $a ) {
		self::can_pin( $a ); global $wpdb;
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		$nx = self::file_ngay( isset( $a[2] ) ? $a[2] : '' ); if ( '' === $nx ) { return array( 'ok' => false, 'error' => 'Ngày không hợp lệ: ' . ( isset( $a[2] ) ? $a[2] : '' ) ); }
		$tf = self::tbl_congfile();
		$n = $wpdb->query( $wpdb->prepare( "DELETE FROM $tf WHERE nguon=%s AND ngay=%s", $nguon, $nx ) );
		return array( 'ok' => true, 'ngay' => $nx, 'soDongXoa' => (int) $n );
	}

	// ── Gửi thử payload cổng (testWebhookCong) ──
	public static function rpc_testWebhookCong( $a ) {
		self::can_pin( $a );
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		$raw = trim( isset( $a[2] ) ? (string) $a[2] : '' ); if ( '' === $raw ) { return array( 'ok' => false, 'error' => 'Chưa dán payload' ); }
		$doc = self::cong_doc_payload( $raw ); $moi = 0; $trung = 0; $kho = 0;
		foreach ( $doc as $tx ) { $tx['nguon'] = $nguon; $tx['khoa'] = self::cong_khoa( $nguon, $tx, $raw ); $tx['raw'] = $raw;
			if ( empty( $tx['docDuoc'] ) ) { $kho++; } if ( self::luu_cong( $tx ) ) { $moi++; } else { $trung++; } }
		$kq = count( $doc ) ? ( 'đã lưu ' . $moi . ' giao dịch' . ( $trung ? ( ', trùng bỏ qua ' . $trung ) : '' ) . ( $kho ? ( ', ' . $kho . ' dòng CHƯA ĐỌC ĐƯỢC đủ trường' ) : '' ) ) : 'không đọc được giao dịch nào từ payload';
		return array( 'ok' => true, 'ketQua' => $kq, 'doc' => $doc );
	}

	// ───────────────────────────── Frontend SPA ─────────────────────────────
	public static function shortcode() {
		$rest = esc_url_raw( rest_url( self::NS ) );
		$file = plugin_dir_path( __FILE__ ) . 'app.html';
		$html = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		if ( '' === $html ) { return '<p>Thiếu app.html trong plugin.</p>'; }
		// Cầu google.script.run (Apps Script) -> POST /rpc {fn,args}. {__err} -> withFailureHandler.
		$shim = '<script>(function(){var REST=' . wp_json_encode( $rest ) . ';'
			. 'function post(fn,args,s,f,u){fetch(REST+"/rpc",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({fn:fn,args:args})})'
			. '.then(function(r){return r.json();}).then(function(d){if(d&&typeof d==="object"&&d.__err!==undefined){if(f)f(new Error(d.__err),u);return;}if(s)s(d,u);})'
			. '.catch(function(e){if(f)f(e,u);});}'
			. 'function mk(){var st={s:null,f:null,u:undefined};var p=new Proxy({},{get:function(t,k){'
			. 'if(k==="withSuccessHandler")return function(fn){st.s=fn;return p;};'
			. 'if(k==="withFailureHandler")return function(fn){st.f=fn;return p;};'
			. 'if(k==="withUserObject")return function(o){st.u=o;return p;};'
			. 'if(typeof k!=="string")return undefined;'
			. 'return function(){post(k,Array.prototype.slice.call(arguments),st.s,st.f,st.u);};}});return p;}'
			. 'window.google=window.google||{};window.google.script=window.google.script||{};'
			. 'Object.defineProperty(window.google.script,"run",{configurable:true,get:function(){return mk();}});'
			. 'window.google.script.host={close:function(){},setHeight:function(){},setWidth:function(){},editAsText:function(){return this;}};'
			. 'window.google.script.url={getLocation:function(cb){if(cb)cb({parameter:{},hash:""});}};'
			. '})();</script>';
		if ( false !== stripos( $html, '</head>' ) ) { $html = preg_replace( '#</head>#i', $shim . '</head>', $html, 1 ); }
		else { $html = $shim . $html; }
		ob_start(); ?>
<style id="skp-full">
.wp-site-blocks > header.wp-block-template-part, .wp-site-blocks > footer.wp-block-template-part,
header.wp-block-template-part, footer.wp-block-template-part,
#masthead, #colophon, .site-header, .site-footer, .wp-block-site-title,
.wp-block-post-title, .entry-header, header.entry-header { display:none !important; }
#wpadminbar { display:none !important; }
html { margin-top:0 !important; }
body { margin:0 !important; padding:0 !important; }
.wp-site-blocks, .wp-site-blocks > *, .entry-content, .wp-block-group, main, .site-main, .content-area,
.wp-block-post-content, article, .page, .type-page, .hentry, .is-layout-constrained, .is-layout-flow,
.alignwide, .alignfull { max-width:none !important; width:auto !important; margin:0 !important; padding:0 !important; }
html, body { overflow-x:clip !important; }
.skp-frame { position:relative; left:50%; right:50%; margin-left:-50vw; margin-right:-50vw; width:100vw; max-width:100vw; }
.skp-frame iframe { display:block; width:100vw; height:100vh; border:0; }
</style>
<div class="skp-frame"><iframe title="Sao Kê Ngân Hàng K&amp;H" srcdoc="<?php echo esc_attr( $html ); ?>"></iframe></div>
<?php
		return ob_get_clean();
	}
}

register_activation_hook( __FILE__, function () { SAOKE_App::bao_dam_bang(); SAOKE_App::bao_dam_trang(); flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'saoke_cron_sync' ); wp_clear_scheduled_hook( 'saoke_cron_vqr' ); } );
add_action( 'init', array( 'SAOKE_App', 'init' ), 6 );

endif; // class_exists SAOKE_App
