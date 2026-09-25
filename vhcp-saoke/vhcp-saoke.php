<?php
/**
 * Plugin Name:       Sao Kê Ngân Hàng K&H (SePay)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Sao kê & đối soát dòng tiền ngân hàng qua SePay (webhook + Open API) + đối chiếu nộp tiền theo điểm + sao kê cổng Việt QR/MoMo/VNPAY + tổng hợp doanh thu cơ sở. Trang [posh_saoke] bảo vệ bằng PIN. ĐỘC LẬP với plugin vé/ghế.
 * Version:           0.55.0
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
	const VER_TBL = '6';   // 0.54.0: thêm KEY ma_gd cho saoke_cong (dò trùng theo mã GD từng quét cả bảng)
	/* 🔴 SỐ BẢN ĐỌC THẲNG TỪ MÃ, và trang IN NÓ RA. Anh Thắng 12/09/2026: *"Anh chưa thấy chỗ
	   thêm file"* — câu đầu tiên phải trả lời là "bản đang chạy có khối ấy chưa", mà trang thì
	   không in số bản ở đâu cả, nên không ai đáp được ngoài cách đi mở wp-admin. Ghi ở đây, hiện
	   ở góc cột trái. ⚠️ PHẢI BẰNG số ở header `Version:` phía trên — hai chỗ, một giá trị. */
	const VER = '0.55.0';

	/* 🔴 BỘ NHỚ ĐỆM TRONG MỘT LƯỢT cho bản đồ cửa hàng VietQR (option `saoke_vqr_ch`) — anh Thắng
	   25/09/2026: Báo cáo tổng bên Ghế "không nối được tới máy chủ" khi chọn 01→25/09, còn 12→25 thì
	   được. Đo thật: mỗi dòng cổng gọi vqr_may_theo_ma() HAI lần (cong_may_dong + cong_coso_dong), mỗi
	   lần get_option() giải tuần tự cả bản đồ (74KB / 400 cửa hàng), trượt khoá chính thì còn duyệt lại
	   toàn bộ bằng regex (vqr_ma_tat_ca) — 20.000 dòng mất 2,2s (trúng) tới 9,4s (trượt); nhân đôi, nhân
	   25 ngày là quá 30s PHP cho phép, PHP bị ngắt → trình duyệt thấy status 0. Nay đọc MỘT lần, nhớ theo
	   mã; ghi option thì quên (vqr_ch_quen_) để cùng lượt vẫn thấy bản mới. */
	private static $vqr_ch_cache = null, $vqr_ma_tat_ca_cache = null, $vqr_may_memo = array(), $vqr_diem_cache = null;
	private static function vqr_ch_quen_() { self::$vqr_ch_cache = null; self::$vqr_ma_tat_ca_cache = null; self::$vqr_may_memo = array(); self::$vqr_diem_cache = null; }

	/* 3 cổng thanh toán + tên hiển thị. Việt QR về bank 1:1; MoMo/VNPAY gộp cục N:1. */
	private static function cong_ds() { return array( 'vietqr', 'momo', 'vnpay' ); }
	private static function cong_ten() { return array( 'vietqr' => 'Việt QR', 'momo' => 'MoMo', 'vnpay' => 'VNPAY' ); }
	private static function cong_tukhoa_mac_dinh() { return array( 'vietqr' => 'VQR', 'momo' => 'MOMO', 'vnpay' => 'VNPAY' ); }
	/* Mã nộp tiền tự mã hoá 4 chiều: KH705/989 · MTD/KVC · MB/MN · số thứ tự điểm. */
	const RE_MA_NOP = '/KH\s*(705|989)\s*(MTD|KVC)\s*(MB|MN)\s*(\d{1,5})/i';

	// ───────────────────────────── Bootstrap ─────────────────────────────
	public static function init() {
		/* Cửa đọc giao dịch cổng cho plugin khác — xem khối 🔴 ở gd_cong_ds(). */
		add_filter( 'saoke_gd_cong', array( __CLASS__, 'loc_gd_cong' ), 10, 4 );
		self::bao_dam_bang();
		self::bao_dam_trang();
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_shortcode( 'posh_saoke', array( __CLASS__, 'shortcode' ) );
		/* Nhận vé từ trang Ghế TRƯỚC khi trang vẽ ra — xem nhan_ve_(). */
		add_action( 'template_redirect', array( __CLASS__, 'nhan_ve_' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'wp', array( __CLASS__, 'an_admin_bar' ) );
		add_action( 'saoke_cron_sync', array( __CLASS__, 'cron_sync' ) );
		add_action( 'saoke_cron_vqr', array( __CLASS__, 'cron_vqr_sheet' ) );
		add_action( 'saoke_cron_conglog', array( __CLASS__, 'cron_cong_log' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'them_lich' ) );
		// Bảo đảm lịch tồn tại nếu đã bật auto-sync (WP-Cron kích khi có traffic).
		if ( '1' === (string) get_option( 'saoke_autosync', '0' ) && ! wp_next_scheduled( 'saoke_cron_sync' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'saoke_cron_sync' );
		}
		if ( '' !== trim( (string) get_option( 'saoke_vqr_sheet', '' ) ) && ! wp_next_scheduled( 'saoke_cron_vqr' ) ) {
			wp_schedule_event( time() + 120, 'saoke_5phut', 'saoke_cron_vqr' );
		}
		if ( '1' === (string) get_option( 'saoke_cong_log_auto', '0' ) && '' !== trim( (string) get_option( 'saoke_cong_log_sheet', '' ) ) && ! wp_next_scheduled( 'saoke_cron_conglog' ) ) {
			wp_schedule_event( time() + 60, self::conglog_lich(), 'saoke_cron_conglog' );
		}
	}
	public static function them_lich( $s ) { $s['saoke_5phut'] = array( 'interval' => 300, 'display' => 'Mỗi 5 phút (Sao Kê)' ); $s['saoke_1phut'] = array( 'interval' => 60, 'display' => 'Mỗi 1 phút (Sao Kê)' ); return $s; }
	private static function conglog_lich() { return '1' === (string) get_option( 'saoke_cong_log_freq', '5' ) ? 'saoke_1phut' : 'saoke_5phut'; }

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
			ma_ch VARCHAR(40) NOT NULL DEFAULT '',
			may_tay VARCHAR(60) NOT NULL DEFAULT '',
			doc_duoc TINYINT NOT NULL DEFAULT 0,
			raw TEXT NULL,
			nhan_luc DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY khoa (khoa),
			KEY nguon (nguon), KEY thoi_diem (thoi_diem), KEY ma_ch (ma_ch), KEY ref (ref), KEY ma_gd (ma_gd)
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

		/* 🔴 THÊM TAY chỉ mục ma_gd (0.54.0) — anh Thắng 25/09/2026, nạp bù 24.261 dòng đổ HTTP 500 ở đợt toàn dòng MỚI: mỗi dòng
		   mới dò trùng bằng `… AND ( ref=%s OR ma_gd=%s )`, cột ma_gd không có chỉ mục nên MySQL quét cả bảng, 800 dòng × 2 câu
		   là quá giờ PHP. dbDelta thường tự thêm KEY mới, nhưng đây là bảng ĐANG SỐNG — "chắc chắn nó CÓ" hơn là tin. */
		if ( ! $wpdb->get_var( "SHOW INDEX FROM $tc WHERE Key_name='ma_gd'" ) ) { $wpdb->query( "ALTER TABLE $tc ADD INDEX ma_gd (ma_gd)" ); }
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
		/* 🔴 PHIÊN TỪ VÉ CỦA TRANG GHẾ ĐƯỢC TÍNH LÀ ĐÃ QUA CỬA. Anh Thắng 15/09/2026: kế toán bấm
		   link Sao Kê bên Ghế thì vào thẳng, không hỏi PIN nữa. Quyền lấy từ HỒ SƠ NHÂN SỰ (ai có
		   quyền Chốt doanh số) chứ không phải tài khoản WordPress — kế toán bên này chỉ dùng PIN.
		   ⚠️ KHÔNG BỎ PIN. Đây là THÊM một đường vào, PIN vẫn nguyên cho người gõ thẳng địa chỉ. */
		if ( self::phien_ok_() ) { return true; }
		$luu = (string) get_option( 'saoke_pin', '' );
		if ( '' === $luu ) { return false; }
		return hash_equals( $luu, (string) $req->get_param( 'pin' ) );
	}

	/** Cookie phiên có còn sống không. Khoá lưu dạng BĂM — lộ bảng options cũng không dựng lại được cookie. */
	private static function phien_ok_() {
		$sid = isset( $_COOKIE['saoke_ses'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $_COOKIE['saoke_ses'] ) : '';
		if ( 32 !== strlen( $sid ) ) { return false; }
		return is_array( get_transient( 'saoke_ses_' . hash( 'sha256', $sid ) ) );
	}

	/**
	 * 🔴 NHẬN VÉ MỘT LẦN TỪ TRANG GHẾ (?sk=…) → đặt phiên 8 tiếng → XOÁ vé khỏi đường dẫn.
	 *
	 * Vé do `VHG_Trang::saoke_ve_()` cấp, chỉ cấp cho người đã qua cổng `kt_` bên Ghế (đã có token
	 * hợp lệ VÀ có quyền Chốt doanh số / Quản trị). Hai plugin dùng chung một CSDL (CLAUDE.md §5)
	 * nên bên này chỉ cần đọc đúng khoá đã thoả thuận — KHÔNG gọi lớp nào của Ghế, gỡ Ghế ra thì
	 * chỗ này lặng lẽ không làm gì, PIN vẫn chạy.
	 *
	 * Bốn chốt an toàn:
	 *   · vé 128 bit ngẫu nhiên, lưu dạng BĂM, sống 2 phút;
	 *   · DÙNG MỘT LẦN — đọc xong xoá ngay, nên vé còn trong lịch sử trình duyệt cũng vô dụng;
	 *   · chuyển hướng bỏ `?sk=` ngay lập tức, để vé không nằm lại trên thanh địa chỉ / referrer;
	 *   · cookie httponly + samesite, JS không đọc được.
	 */
	public static function nhan_ve_() {
		if ( ! isset( $_GET['sk'] ) ) { return; }
		$ve   = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['sk'] ) );
		$sach = remove_query_arg( 'sk' );
		if ( 32 === strlen( $ve ) ) {
			$k = 'vhg_sk_ve_' . hash( 'sha256', $ve );
			$d = get_transient( $k );
			if ( is_array( $d ) ) {
				delete_transient( $k );   // DÙNG MỘT LẦN
				$sid = bin2hex( random_bytes( 16 ) );
				set_transient( 'saoke_ses_' . hash( 'sha256', $sid ),
					array( 'ten' => (string) ( isset( $d['ten'] ) ? $d['ten'] : '' ), 'luc' => time() ),
					8 * HOUR_IN_SECONDS );
				$het = time() + 8 * HOUR_IN_SECONDS;
				/* setcookie() nhận mảng tuỳ chọn từ PHP 7.3; plugin khai Requires PHP 7.2 nên phải
				   có nhánh lùi, không thì đúng host PHP 7.2 là lỗi trắng trang. */
				if ( PHP_VERSION_ID >= 70300 ) {
					setcookie( 'saoke_ses', $sid, array( 'expires' => $het, 'path' => '/',
						'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
				} else {
					setcookie( 'saoke_ses', $sid, $het, '/; samesite=Lax', '', is_ssl(), true );
				}
			}
		}
		wp_safe_redirect( $sach );
		exit;
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
		/* 🔴 NHẬN KEY Ở CẢ BA CHỖ. Trong bảng cấu hình webhook của SePay có ba kiểu xác thực:
		 * không xác thực (khoá nằm trong URL, `?key=`), **API Key** (gửi header
		 * `Authorization: Apikey <khoá>`), và Basic Auth. Bản trước CHỈ đọc `?key=` — chọn kiểu
		 * API Key bên SePay là mọi lượt bắn về đều bị chặn 401, Nhật ký ghi "SAI KEY", trong khi
		 * bên SePay nhìn vẫn thấy "đã gửi". Tiền vào tài khoản mà vé không bao giờ tự xác nhận,
		 * đúng chuyện anh Thắng gặp — mà hai đầu đều tưởng mình đúng.
		 * Nhận cả ba để khai kiểu nào cũng chạy, khỏi phải nhớ đã chọn kiểu gì.
		 */
		$key_gui = (string) $req->get_param( 'key' );
		if ( '' === $key_gui ) {
			$h = self::auth_header( $req );
			if ( preg_match( '/^\s*(apikey|bearer|token)\s+(.+)$/i', $h, $m ) ) { $key_gui = trim( $m[2] ); }
			elseif ( 0 === stripos( $h, 'basic ' ) ) {
				$dec = base64_decode( trim( substr( $h, 6 ) ) );
				/* Basic Auth: khoá có thể nằm ở phần user hoặc phần password, tuỳ người khai. */
				if ( false !== strpos( $dec, ':' ) ) {
					list( $bu, $bp ) = explode( ':', $dec, 2 );
					$key_gui = self::key_khop( $bp, $key ) ? $bp : $bu;
				}
			}
		}
		if ( '' === $key_gui ) { $key_gui = (string) $req->get_header( 'x-api-key' ); }
		if ( '' === $key || ! self::key_khop( $key_gui, $key ) ) {
			/* Nói rõ khoá tới bằng đường nào: "sai key" chung chung thì không biết nên sửa ở ô
			   URL hay ở ô API Key bên SePay. */
			$duong = ( '' !== (string) $req->get_param( 'key' ) ) ? 'trong URL (?key=)'
				: ( '' !== self::auth_header( $req ) ? 'ở header Authorization' : 'KHÔNG gửi khoá nào' );
			self::ghi_log( $src, '✖ SAI KEY (bị chặn) — khoá ' . $duong, $raw );
			return new WP_REST_Response( array( 'success' => false, 'message' => 'sai key (' . $duong . ')' ), 401 );
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
		/* src=vietqr/momo/vnpay thì giao dịch này là của MỘT CỔNG -> ghi thêm vào bảng cổng để màn
		   hình cổng thấy ngay, không phải đợi nạp file. src=sepay (tiền về thẳng bank) thì không. */
		$themCong = '';
		if ( in_array( $nguon, self::cong_ds(), true ) ) {
			$kqCong = self::cong_nhan_webhook( $nguon, $req );
			$themCong = ( isset( $kqCong['moi'] ) && $kqCong['moi'] > 0 ) ? ( ' · bảng cổng +' . (int) $kqCong['moi'] ) : ' · bảng cổng: đã có';
		}
		self::ghi_log( $src, ( $ok ? ( '✔ đã lưu [' . $nguon . '] ' . number_format( $tien ) . 'đ' ) : 'trùng, bỏ qua' ) . $themCong, $raw );
		return new WP_REST_Response( array( 'success' => true, 'nguon' => $nguon, 'moi' => $ok ? 1 : 0 ), 200 );
	}

	/**
	 * CỬA NHẬN CHO PLUGIN KHÁC ĐẨY GIAO DỊCH SANG (cầu nối từ cổng /ghe-tien của plugin Ghế).
	 *
	 * 🔴 VÌ SAO CẦN: site này có HAI cổng nhận tiền. SePay thường chỉ được khai MỘT — cổng của
	 * Ghế (`/ghe-tien`). Cổng ấy ghi vào sổ thu của Ghế, còn sổ sao kê ngân hàng thì trống; mà
	 * plugin vé đối soát bằng SỔ SAO KÊ. Hậu quả: tiền về đúng tài khoản, Ghế vẫn nhận gói, mà
	 * vé chờ mãi — không bên nào sai cả nên không ai tìm ra.
	 *
	 * Nay cổng nào nhận cũng đổ về đây, một cuốn sổ duy nhất, khỏi phải nhớ khai hai webhook.
	 *
	 * ⚠️ CHỐNG TRÙNG LÀ BẮT BUỘC, không phải tuỳ chọn. Khai cả hai webhook bên SePay là cùng một
	 * giao dịch tới hai đường. `luu_gd()` bỏ qua khi trùng `sepay_id`, nên hai đường phải quy về
	 * CÙNG một `sepay_id`: cùng tiền tố nguồn + cùng mã giao dịch của ngân hàng. Sai chỗ này là
	 * doanh thu đếm gấp đôi, mà đếm gấp đôi thì khó thấy hơn hẳn thiếu.
	 *
	 * @return bool true = đã ghi dòng mới · false = trùng, hoặc dữ liệu không dùng được.
	 */
	public static function nhan_gd( $d ) {
		if ( ! is_array( $d ) ) { return false; }
		$tien = (int) round( self::num( isset( $d['tien'] ) ? $d['tien'] : 0 ) );
		$sid  = trim( (string) ( isset( $d['ma_gd'] ) ? $d['ma_gd'] : '' ) );
		if ( $tien <= 0 && '' === $sid ) { return false; }
		$nguon = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) ( isset( $d['nguon'] ) ? $d['nguon'] : 'sepay' ) ) );
		if ( '' === $nguon ) { $nguon = 'sepay'; }
		if ( '' === $sid ) { $sid = substr( md5( $nguon . '|' . $tien . '|' . ( isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ) . '|' . ( isset( $d['ngay_gd'] ) ? $d['ngay_gd'] : '' ) ), 0, 20 ); }
		return self::luu_gd( array(
			'sepay_id'  => $nguon . '-' . $sid,
			'ngay_gd'   => (string) ( isset( $d['ngay_gd'] ) ? $d['ngay_gd'] : '' ),
			'so_tk'     => (string) ( isset( $d['so_tk'] ) ? $d['so_tk'] : '' ),
			'ngan_hang' => (string) ( isset( $d['ngan_hang'] ) && '' !== $d['ngan_hang'] ? $d['ngan_hang'] : strtoupper( $nguon ) ),
			'loai'      => ( isset( $d['loai'] ) && 'out' === $d['loai'] ) ? 'out' : 'in',
			'tien'      => $tien,
			'luy_ke'    => null,
			'noi_dung'  => (string) ( isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ),
			'ma_gd'     => (string) ( isset( $d['ma_gd'] ) ? $d['ma_gd'] : '' ),
			'nguon'     => mb_substr( $nguon, 0, 10 ),
		) );
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
		/* 🔴 CỬA CUỐI — DÒ MÃ MÀ KHÔNG CẦN BIẾT TÊN TRƯỜNG.
		 *
		 * Dạng Tingo `{"values":[[…]]}` là MẢNG CỘT KHÔNG TÊN, và bộ đọc ô (`cong_doc_hang`) phân
		 * loại từng ô theo hình dạng: có chữ + có số + không khoảng trắng -> mã giao dịch; có
		 * khoảng trắng -> nội dung hoặc điểm bán… Ô `RJFSHCSXE9` (mã cửa hàng thật của Việt QR)
		 * TOÀN CHỮ, không số, không khoảng trắng — rơi khỏi mọi nhánh và bị VỨT IM LẶNG. Dữ liệu
		 * cửa hàng có trong gói webhook mà không bao giờ tới được bảng. Đây chính là chỗ mất.
		 *
		 * ⚠️ DÒ THEO TỪNG DÒNG, KHÔNG DÒ TRÊN CẢ GÓI. Một gói `values` có thể chứa nhiều giao dịch
		 *    của nhiều cửa hàng; dò trên cả gói là gán mã của cửa hàng đầu tiên cho tất cả — tiền
		 *    của máy này chui sang máy khác, mà bảng nhìn vẫn "đầy đủ" nên không ai nghi. */
		$laValues = isset( $body['values'] ) && is_array( $body['values'] ) && isset( $body['values'][0] ) && is_array( $body['values'][0] );
		if ( $laValues ) {
			foreach ( $body['values'] as $row ) {
				$tx = self::cong_doc_hang( $row );
				if ( ! $tx ) { continue; }
				if ( '' === trim( (string) $tx['maCH'] ) ) { $tx['maCH'] = self::vqr_ma_tu_payload( $row ); }
				$out[] = $tx;
			}
			return $out;
		}
		$o = self::cong_doc_obj( $body );
		if ( ! $o ) { return $out; }
		if ( '' === trim( (string) ( isset( $o['maCH'] ) ? $o['maCH'] : '' ) ) ) { $o['maCH'] = self::vqr_ma_tu_payload( $body ); }
		$out[] = $o;
		return $out;
	}

	/**
	 * MỌI MÃ ĐÃ BIẾT -> TÊN MÁY. Gộp cả mã cửa hàng (khoá của bản đồ) lẫn mã điểm bán.
	 *
	 * Cổng gửi về khi thì mã cửa hàng, khi thì mã điểm bán, tuỳ loại giao dịch. Bản đồ anh Thắng
	 * nạp từ `store_export` có cả hai cột nên nhận được cả hai, khỏi phải đoán cổng gửi cái nào.
	 */
	private static function vqr_ma_tat_ca() {
		if ( null !== self::$vqr_ma_tat_ca_cache ) { return self::$vqr_ma_tat_ca_cache; }
		$ban = array();
		foreach ( self::vqr_ds_ch() as $ma => $v ) {
			$ten = isset( $v['ten'] ) ? trim( (string) $v['ten'] ) : '';
			if ( '' === $ten ) { continue; }
			$k = self::vqr_ma_ch( $ma );
			if ( '' !== $k ) { $ban[ $k ] = $ten; }
			$kd = self::vqr_ma_ch( isset( $v['maDiem'] ) ? $v['maDiem'] : '' );
			/* Mã cửa hàng thắng mã điểm khi trùng: nó là khoá chính của bản đồ. */
			if ( '' !== $kd && ! isset( $ban[ $kd ] ) ) { $ban[ $kd ] = $ten; }
		}
		self::$vqr_ma_tat_ca_cache = $ban;
		return $ban;
	}

	/** Gom mọi giá trị vô hướng trong payload (đệ quy) — để dò mã mà không cần biết tên trường. */
	private static function payload_gia_tri( $p, &$ra, $sau = 0 ) {
		if ( $sau > 6 || ! is_array( $p ) ) { return; }
		foreach ( $p as $v ) {
			if ( count( $ra ) > 500 ) { return; }   // payload dị dạng không được kéo cả trang đứng
			if ( is_array( $v ) ) { self::payload_gia_tri( $v, $ra, $sau + 1 ); }
			elseif ( is_scalar( $v ) ) { $ra[] = (string) $v; }
		}
	}

	/**
	 * TÌM MÃ CỬA HÀNG TRONG PAYLOAD MÀ **KHÔNG CẦN BIẾT CỔNG ĐẶT TÊN TRƯỜNG LÀ GÌ**.
	 *
	 * 🔴 VÌ SAO PHẢI LÀM KIỂU NÀY. Danh sách khoá ở `cong_doc_obj()` là phỏng đoán: cổng đổi tên
	 *    trường, hay dùng một tên chưa ai nghĩ ra, là lại "chưa rõ máy" và lại phải đợi người ta
	 *    gửi cho một gói payload thật mới biết mà vá. Đường này không đoán: nó lấy MỌI giá trị
	 *    trong payload rồi hỏi bản đồ "giá trị này có phải một mã tôi đã biết không". Trúng thì
	 *    chắc chắn trúng, vì chỉ nhận đúng những mã anh Thắng đã nạp từ `store_export`.
	 *
	 * ⚠️ HAI CHỐT CHỐNG NHẬN BỪA, cả hai đều có bài thử canh:
	 *     · Phải DÀI ÍT NHẤT 4 ký tự — mã hai ba ký tự thì một con số vu vơ cũng khớp.
	 *     · Phải CÓ CHỮ CÁI — nếu không, một SỐ TIỀN hay mã giao dịch toàn số trùng với một mã
	 *       toàn số trong bản đồ là gán nhầm tiền sang máy khác. Đúng loại lỗi câm mà
	 *       `may_hop_le()` đã bị một lần qua cửa đoán cột.
	 *
	 * @return string mã cửa hàng (đã chuẩn hoá), hoặc '' nếu không chắc. Trả MÃ chứ không trả tên
	 *         máy: tên máy vẫn do `cong_may_dong()` một mình quyết, giữ đúng một luật một chỗ.
	 */
	private static function vqr_ma_tu_payload( $body ) {
		if ( ! is_array( $body ) ) { return ''; }
		$ban = self::vqr_ma_tat_ca();
		if ( ! count( $ban ) ) { return ''; }
		$vals = array(); self::payload_gia_tri( $body, $vals );
		$chinh = self::vqr_ds_ch();
		/* HAI LƯỢT, ưu tiên MÃ CỬA HÀNG. Một dòng kết xuất có cả `Mã điểm bán` lẫn `Mã cửa hàng`,
		   và mã điểm thường đứng trước. Lấy cái gặp trước là ghi mã điểm vào ô mã cửa hàng: vẫn
		   ra đúng máy hôm nay, nhưng ô dữ liệu sai nghĩa — và mã điểm có thể dùng chung giữa vài
		   cửa hàng, nên một ngày nào đó nó ra đúng cái máy khác. Mã cửa hàng là khoá chính. */
		foreach ( array( true, false ) as $chiKhoaChinh ) {
			foreach ( $vals as $v ) {
				$k = self::vqr_ma_ch( $v );
				if ( mb_strlen( $k, 'UTF-8' ) < 4 ) { continue; }
				if ( ! preg_match( '/\p{L}/u', $k ) ) { continue; }
				if ( $chiKhoaChinh ) { if ( isset( $chinh[ $k ] ) ) { return $k; } }
				elseif ( isset( $ban[ $k ] ) ) { return $k; }
			}
		}
		return '';
	}
	/* Tingo: mảng cột không tên. Đọc theo ĐẶC ĐIỂM ô (thứ tự cột phụ thuộc cấu hình Tingo). */
	private static function cong_doc_hang( $row ) {
		if ( ! is_array( $row ) || ! count( $row ) ) { return null; }
		$o = array( 'soTien' => 0, 'maGD' => '', 'ref' => '', 'thoiDiem' => '', 'noiDung' => '', 'soTK' => '', 'huong' => '', 'trangThai' => '', 'diemBan' => '', 'maCH' => '' );
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
			if ( false !== strpos( $c, ' ' ) ) {
				// Ô có khoảng trắng: nội dung cổng ("VQR… PaymentForOrder") vào noiDung; TÊN MÁY/CỬA HÀNG
				// ở cột riêng ("GLX QT 02", "AMTP 24") vào diemBan để tự nhận cơ sở, khỏi gõ ánh xạ tay.
				$laVqr = (bool) preg_match( '/^VQR/i', $c ) || preg_match( '/payment\s*for\s*order/i', $c );
				if ( $laVqr || preg_match( '/^[0-9]/', $c ) ) { if ( strlen( $c ) > strlen( $o['noiDung'] ) ) { $o['noiDung'] = $c; } }
				elseif ( '' === $o['diemBan'] && preg_match( '/[A-Za-z]/', $c ) ) { $o['diemBan'] = $c; }
			}
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
			/* 🔴 Ô NÀY TRƯỚC 0.20.0 KHÔNG HỀ ĐƯỢC ĐỌC. Cả bộ đọc payload không có lấy một khoá nào
			   cho MÃ CỬA HÀNG, nên cổng có gửi mã về thì cũng bị vứt ngay tại cửa — rồi màn hình
			   báo "chưa rõ máy" và người ta đi tải file kết xuất về nạp bù, cho đúng cái dữ liệu
			   vừa ném đi. Danh sách khoá để rộng: mỗi cổng gọi một kiểu.
			   ⚠️ KHÔNG cướp `storeId`/`storeName` của `diemBan` ở trên — hai ô khác nghĩa, và
			      `diemBan` là đường lùi đã chạy đúng lâu nay. */
			'maCH' => trim( (string) self::cong_lay( $srcs, array( 'storeCode', 'store_code', 'storeCd', 'shopCode', 'shop_code',
				'merchantCode', 'merchant_code', 'merchantId', 'merchant_id', 'posCode', 'pos_code',
				'terminalCode', 'terminal_code', 'terminalCd', 'maCH', 'maCuaHang', 'ma_cua_hang' ) ) ),
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
	/* Dòng đã có theo KHOÁ, hỏi MỘT câu cho cả lô (≤ 500 khoá/câu) — 0.52.0, nạp bù theo đợt. Trả [ khoa => [id, diem_ban, ma_ch] ].
	   Khoá cắt 120 ký tự như lúc ghi (luu_cong), nếu không thì khoá dài không bao giờ khớp và bị chèn lại. */
	private static function cong_cu_theo_khoa_( $khoas ) {
		global $wpdb; $tbl = self::tbl_cong(); $ra = array(); $ds = array();
		foreach ( (array) $khoas as $k ) { $k = mb_substr( (string) $k, 0, 120 ); if ( '' !== $k ) { $ds[ $k ] = 1; } }
		foreach ( array_chunk( array_keys( $ds ), 500 ) as $lo ) {
			$ph = implode( ',', array_fill( 0, count( $lo ), '%s' ) );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, khoa, diem_ban, ma_ch FROM $tbl WHERE khoa IN ($ph)", ...$lo ), ARRAY_A ) as $r ) { $ra[ (string) $r['khoa'] ] = $r; }
		}
		return $ra;
	}
	/* Vá theo LÔ (0.53.0): mỗi ô (ma_ch / diem_ban) MỘT câu `UPDATE … SET ô = CASE id WHEN … END WHERE id IN (…)` cho cả đợt —
	   thay cho 400 câu UPDATE rời ở lượt nạp bù đầu tiên (webhook không gửi mã cửa hàng nên dòng nào cũng phải vá). ≤ 400 id/câu. */
	private static function cong_va_lo_( $va_lo ) {
		global $wpdb; $tbl = self::tbl_cong(); $n = 0;
		foreach ( array( 'ma_ch', 'diem_ban' ) as $cot ) {
			$cap = array();
			foreach ( (array) $va_lo as $id => $va ) { if ( isset( $va[ $cot ] ) && '' !== (string) $va[ $cot ] ) { $cap[ (int) $id ] = (string) $va[ $cot ]; } }
			foreach ( array_chunk( $cap, 400, true ) as $lo ) {
				$sql = "UPDATE $tbl SET $cot = CASE id"; $args = array();
				foreach ( $lo as $id => $v ) { $sql .= ' WHEN %d THEN %s'; $args[] = (int) $id; $args[] = $v; }
				$sql .= " ELSE $cot END WHERE id IN (" . implode( ',', array_map( 'intval', array_keys( $lo ) ) ) . ')';
				$n += (int) $wpdb->query( $wpdb->prepare( $sql, ...$args ) );
			}
		}
		return $n;
	}
	/* Dòng đã có theo MÃ THAM CHIẾU / MÃ GD, hỏi theo LÔ cho những dòng khoá chưa có (0.54.0). Cùng luật với cong_dong_trung():
	   cùng nguồn, cùng số tiền, giá trị (ref trước, rồi maGD) khớp cột ref HOẶC ma_gd. Hai câu IN (…) cho cả đợt thay cho
	   800 × 2 câu quét bảng. Trả [ khoa(120) => dòng đã có ]. */
	private static function cong_cu_theo_ref_( $dsTx ) {
		global $wpdb; $tbl = self::tbl_cong(); $ra = array(); $gia = array();
		foreach ( (array) $dsTx as $d ) { foreach ( array( 'ref', 'maGD' ) as $f ) { $v = mb_substr( trim( (string) $d['tx'][ $f ] ), 0, 80 ); if ( '' !== $v ) { $gia[ $v ] = 1; } } }
		if ( ! $gia ) { return $ra; }
		$ung = array();
		foreach ( array( 'ref', 'ma_gd' ) as $cot ) {
			foreach ( array_chunk( array_keys( $gia ), 500 ) as $lo ) {
				$ph = implode( ',', array_fill( 0, count( $lo ), '%s' ) );
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, nguon, so_tien, ref, ma_gd, diem_ban, ma_ch FROM $tbl WHERE $cot IN ($ph)", ...$lo ), ARRAY_A ) as $r ) {
					foreach ( array( (string) $r['ref'], (string) $r['ma_gd'] ) as $k ) { if ( '' !== $k ) { $ung[ $k ][ (int) $r['id'] ] = $r; } }
				}
			}
		}
		foreach ( (array) $dsTx as $d ) {
			$tx = $d['tx']; $tien = (int) round( $tx['soTien'] ); if ( $tien <= 0 ) { continue; }
			foreach ( array( 'ref', 'maGD' ) as $f ) {
				$v = mb_substr( trim( (string) $tx[ $f ] ), 0, 80 ); if ( '' === $v || ! isset( $ung[ $v ] ) ) { continue; }
				foreach ( $ung[ $v ] as $r ) {
					if ( (string) $r['nguon'] === (string) $tx['nguon'] && (int) $r['so_tien'] === $tien ) { $ra[ mb_substr( $tx['khoa'], 0, 120 ) ] = $r; continue 3; }
				}
			}
		}
		return $ra;
	}
	/**
	 * @param array  $row dòng giao dịch cổng.
	 * @param string $kq  (ra) `moi` · `va` (đã có, vừa vá thêm ô trống) · `trung` (đã có, không đổi gì).
	 * @param mixed  $cu_biet (0.52.0) dòng đã có do nạp bù dò theo lô; false = chưa có theo khoá (còn dò ref); 'moi' (0.54.0) = chắc chắn mới, chèn thẳng; null = tự hỏi.
	 * @param array  $va_lo   (0.53.0) nếu là mảng: KHÔNG UPDATE ngay mà gom [id => ô cần vá] để nơi gọi vá MỘT câu cho cả lô (cong_va_lo_).
	 * @return bool true CHỈ khi thêm dòng mới — nơi gọi đếm tiền dựa vào đúng điều đó.
	 */
	/**
	 * Dòng đã có của CÙNG giao dịch nhưng mang khoá khác (webhook vs file kết xuất).
	 *
	 * ⚠️ BA ĐIỀU KIỆN CÙNG LÚC, không được nới: cùng nguồn, cùng SỐ TIỀN, và trùng một trong hai
	 *    mã (`ref` hoặc `ma_gd`). Chỉ so mã thôi thì hai giao dịch khác nhau vô tình mang mã giống
	 *    nhau sẽ bị gộp làm một — mất hẳn một khoản tiền, còn tệ hơn đếm đúp.
	 *
	 * @return array|null dòng cũ (id, diem_ban, ma_ch) hoặc null.
	 */
	private static function cong_dong_trung( $row ) {
		global $wpdb; $tbl = self::tbl_cong();
		$tien = (int) round( isset( $row['soTien'] ) ? $row['soTien'] : 0 );
		if ( $tien <= 0 ) { return null; }
		$nguon = (string) ( isset( $row['nguon'] ) ? $row['nguon'] : '' );
		if ( '' === $nguon ) { return null; }
		foreach ( array( 'ref', 'maGD' ) as $f ) {
			$v = isset( $row[ $f ] ) ? trim( (string) $row[ $f ] ) : '';
			if ( '' === $v ) { continue; }
			$r = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, diem_ban, ma_ch FROM $tbl WHERE nguon=%s AND so_tien=%d AND ( ref=%s OR ma_gd=%s ) LIMIT 1",
				$nguon, $tien, $v, $v ), ARRAY_A );
			if ( $r ) { return $r; }
		}
		return null;
	}
	private static function luu_cong( $row, &$kq = null, $cu_biet = null, &$va_lo = null ) {
		global $wpdb; $tbl = self::tbl_cong();
		$kq = 'trung';
		/* 0.52.0: nạp bù đã dò theo LÔ (cong_cu_theo_khoa_) — mảng = dòng đã có, false = chắc chắn chưa có theo khoá, null = tự hỏi. */
		$cu = is_array( $cu_biet ) ? $cu_biet : ( ( false === $cu_biet || 'moi' === $cu_biet ) ? null : $wpdb->get_row( $wpdb->prepare( "SELECT id, diem_ban, ma_ch FROM $tbl WHERE khoa=%s", $row['khoa'] ), ARRAY_A ) );
		/* 🔴 CÙNG MỘT GIAO DỊCH, HAI ĐƯỜNG VỀ, HAI CÁI KHOÁ KHÁC NHAU.
		 *    Khoá dựng từ `maGD` (rồi mới tới `ref`). Webhook sống lấy `maGD` = mã giao dịch của
		 *    ngân hàng; file kết xuất lấy từ cột "Mã đơn hàng". Hai giá trị ấy KHÔNG chắc bằng
		 *    nhau — mà khoá khác nhau thì `luu_cong()` coi là hai giao dịch và cộng tiền HAI LẦN.
		 *    Đếm thiếu thì ai cũng thấy; đếm gấp đôi thì không ai thấy. Nên trước khi chèn mới,
		 *    dò thêm một vòng theo MÃ THAM CHIẾU của cùng nguồn, cùng số tiền. */
		if ( ! $cu && 'moi' !== $cu_biet ) { $cu = self::cong_dong_trung( $row ); }   // 'moi' (0.54.0): nạp bù đã dò lô cả khoá lẫn ref/mã GD → chèn thẳng
		if ( $cu ) {
			/* Dòng đã có: nếu trước đây chưa vớt được tên máy mà nay parser có -> vá vào, khỏi xoá làm lại.
			 *
			 * 🔴 VÀ ĐÂY LÀ CẢ ĐIỂM CỦA VIỆC NẠP SAO KÊ VIỆT QR (anh Thắng 12/09/2026): *"một số giao
			 *    dịch nội dung không rõ ràng, mà webhook gửi về thì thiếu thông tin… tải thẳng sao kê
			 *    của bên VietQR, nó có đủ các trường để xác định giao dịch đó là của máy nào"*.
			 *    Dòng `PaymentForOrder` webhook đã ghi từ lâu — nạp lại file KHÔNG thêm dòng nào (đúng,
			 *    kẻo đếm tiền hai lần) nhưng PHẢI vá `ma_ch` vào dòng cũ. Không vá thì file có đủ dữ
			 *    liệu mà màn hình vẫn "chưa rõ máy", và người nạp không hiểu vì sao nạp xong y như cũ.
			 *
			 * ⚠️ CHỈ VÁ Ô ĐANG TRỐNG, không đè ô đã có. File nạp lại lần hai, hay một file cũ hơn, thì
			 *    không được phép đổi máy của một dòng đã xác định — đó là sửa lịch sử tiền. */
			$va = array();
			if ( '' === trim( (string) $cu['diem_ban'] ) && '' !== trim( (string) $row['diemBan'] ) ) {
				$va['diem_ban'] = mb_substr( (string) $row['diemBan'], 0, 120 );
			}
			$ma_ch_moi = isset( $row['maCH'] ) ? trim( (string) $row['maCH'] ) : '';
			if ( '' === trim( (string) $cu['ma_ch'] ) && '' !== $ma_ch_moi ) {
				$va['ma_ch'] = mb_substr( $ma_ch_moi, 0, 40 );
			}
			if ( $va ) { if ( is_array( $va_lo ) ) { $va_lo[ (int) $cu['id'] ] = $va; } else { $wpdb->update( $tbl, $va, array( 'id' => (int) $cu['id'] ) ); } $kq = 'va'; }
			return false;
		}
		$kq = 'moi';
		$wpdb->insert( $tbl, array(
			'nguon' => $row['nguon'], 'khoa' => mb_substr( $row['khoa'], 0, 120 ),
			'ma_gd' => mb_substr( (string) $row['maGD'], 0, 80 ), 'ref' => mb_substr( (string) $row['ref'], 0, 80 ),
			'thoi_diem' => self::cong_ngay_mysql( $row['thoiDiem'] ), 'so_tien' => (int) round( $row['soTien'] ),
			'huong' => $row['huong'], 'trang_thai' => mb_substr( (string) $row['trangThai'], 0, 30 ),
			'so_tk' => mb_substr( (string) $row['soTK'], 0, 60 ), 'noi_dung' => (string) $row['noiDung'],
			'diem_ban' => mb_substr( (string) $row['diemBan'], 0, 120 ),
			'ma_ch' => mb_substr( (string) ( isset( $row['maCH'] ) ? $row['maCH'] : '' ), 0, 40 ),
			'doc_duoc' => $row['docDuoc'] ? 1 : 0,
			'raw' => mb_substr( (string) $row['raw'], 0, 2000 ), 'nhan_luc' => current_time( 'mysql' ),
		) );
		return true;
	}
	private static function cong_nhan_webhook( $nguon, $req, $macDinh = array() ) {
		$raw = $req->get_body(); if ( '' === trim( (string) $raw ) ) { $raw = wp_json_encode( $req->get_json_params() ); }
		$list = self::cong_doc_payload( $raw );
		if ( ! count( $list ) ) { return array( 'moi' => 0, 'trung' => 0, 'chuaDoc' => 0, 'message' => 'không đọc được giao dịch từ payload' ); }
		$moi = 0; $trung = 0; $kho = 0;
		foreach ( $list as $tx ) {
			$tx['nguon'] = $nguon; $tx['khoa'] = self::cong_khoa( $nguon, $tx, $raw ); $tx['raw'] = $raw;
			/* 0.49.0: payload không mang số TK → gắn số TK của tài khoản VietQR mà token thuộc về (tách hai tài khoản). */
			if ( '' === trim( (string) ( isset( $tx['soTK'] ) ? $tx['soTK'] : '' ) ) && ! empty( $macDinh['soTK'] ) ) { $tx['soTK'] = (string) $macDinh['soTK']; }
			if ( empty( $tx['docDuoc'] ) ) { $kho++; }
			if ( self::luu_cong( $tx ) ) { $moi++; self::day_ghe_dong_( $tx ); } else { $trung++; }
		}
		return array( 'moi' => $moi, 'trung' => $trung, 'chuaDoc' => $kho );
	}

	// ═══════════════ VietQR CHÍNH THỨC (token_generate + transaction-callback) ═══════════════
	/**
	 * DANH SÁCH TÀI KHOẢN VIETQR CHÍNH THỨC (0.49.0) — anh Thắng 25/09/2026: *"anh muốn thêm tài khoản
	 * thứ 2 của VietQR"*. Mỗi tài khoản = một cặp username/password mà cổng dùng gọi Token URL + số TK
	 * ngân hàng nhận tiền của tài khoản ấy. Lưu option `saoke_vqr_tk` (mảng). Cặp cũ
	 * `saoke_vqr_user/pass` được coi là tài khoản #1 khi danh sách trống — cài đè không phải khai lại.
	 * Token cấp cho tài khoản nào thì ghi `tk=<id>` trong payload và ký bằng mật khẩu của chính tài
	 * khoản ấy: giao dịch về theo token nào là biết của tài khoản nào, dù payload cổng thiếu số TK.
	 */
	private static function vqr_tk_ds() {
		$ds = get_option( 'saoke_vqr_tk' ); $ds = is_array( $ds ) ? array_values( $ds ) : array();
		$ra = array();
		foreach ( $ds as $t ) {
			if ( ! is_array( $t ) ) { continue; }
			$u = trim( (string) ( isset( $t['user'] ) ? $t['user'] : '' ) ); if ( '' === $u ) { continue; }
			$ra[] = array( 'id' => (string) ( isset( $t['id'] ) ? $t['id'] : '' ), 'nhan' => trim( (string) ( isset( $t['nhan'] ) ? $t['nhan'] : '' ) ),
				'user' => $u, 'pass' => (string) ( isset( $t['pass'] ) ? $t['pass'] : '' ),
				'so_tk' => preg_replace( '/\s+/', '', (string) ( isset( $t['so_tk'] ) ? $t['so_tk'] : '' ) ),
				'ngan_hang' => trim( (string) ( isset( $t['ngan_hang'] ) ? $t['ngan_hang'] : '' ) ) );
		}
		if ( ! $ra ) {
			$u = (string) get_option( 'saoke_vqr_user', '' ); $p = (string) get_option( 'saoke_vqr_pass', '' );
			if ( '' !== $u && '' !== $p ) { $ra[] = array( 'id' => 'tk1', 'nhan' => 'Tài khoản 1', 'user' => $u, 'pass' => $p, 'so_tk' => '', 'ngan_hang' => '' ); }
		}
		return $ra;
	}
	private static function vqr_tk_theo_id_( $id ) { foreach ( self::vqr_tk_ds() as $t ) { if ( (string) $t['id'] === (string) $id ) { return $t; } } return null; }
	/* Bí mật ký token. Có tài khoản → ký bằng mật khẩu của tài khoản đó; không → bí mật cũ (token cấp trước 0.49.0). */
	private static function vqr_secret( $tk = null ) {
		if ( is_array( $tk ) && '' !== (string) $tk['pass'] ) { return (string) $tk['pass'] . '|' . wp_salt( 'auth' ); }
		$p = (string) get_option( 'saoke_vqr_pass', '' ); return '' !== $p ? ( $p . '|' . wp_salt( 'auth' ) ) : wp_salt( 'auth' );
	}
	private static function vqr_make_token( $exp, $tk = null ) {
		$p = 'exp=' . $exp . ( is_array( $tk ) && '' !== (string) $tk['id'] ? ( ';tk=' . $tk['id'] ) : '' );
		return rtrim( strtr( base64_encode( $p ), '+/', '-_' ), '=' ) . '.' . hash_hmac( 'sha256', $p, self::vqr_secret( $tk ) );
	}
	/** Trả TÀI KHOẢN (mảng) mà token thuộc về, hoặc false. Token cũ không có `tk=` → tài khoản #1 (bí mật cũ). */
	private static function vqr_check_token( $tok ) {
		$tok = trim( (string) $tok ); $parts = explode( '.', $tok );
		if ( count( $parts ) !== 2 ) { return false; }
		$p = base64_decode( strtr( $parts[0], '-_', '+/' ) );
		if ( ! preg_match( '/exp=(\d+)/', (string) $p, $m ) ) { return false; }
		$tk = null;
		if ( preg_match( '/tk=([A-Za-z0-9_-]+)/', (string) $p, $k ) ) { $tk = self::vqr_tk_theo_id_( $k[1] ); if ( ! $tk ) { return false; } }
		if ( ! hash_equals( hash_hmac( 'sha256', $p, self::vqr_secret( $tk ) ), $parts[1] ) ) { return false; }
		if ( time() > (int) $m[1] ) { return false; }
		if ( ! $tk ) { $ds = self::vqr_tk_ds(); $tk = $ds ? $ds[0] : array( 'id' => '', 'nhan' => '', 'user' => '', 'pass' => '', 'so_tk' => '', 'ngan_hang' => '' ); }
		return $tk;
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
		$ds = self::vqr_tk_ds();
		if ( ! $ds ) { return new WP_REST_Response( array( 'error' => true, 'errorReason' => 'chưa khai tài khoản VietQR (user/pass)' ), 401 ); }
		$u = ''; $p = '';
		$h = self::auth_header( $req );
		if ( 0 === stripos( $h, 'basic ' ) ) { $dec = base64_decode( trim( substr( $h, 6 ) ) ); if ( false !== strpos( $dec, ':' ) ) { list( $u, $p ) = explode( ':', $dec, 2 ); } }
		if ( '' === $u && isset( $_SERVER['PHP_AUTH_USER'] ) ) { $u = (string) $_SERVER['PHP_AUTH_USER']; $p = isset( $_SERVER['PHP_AUTH_PW'] ) ? (string) $_SERVER['PHP_AUTH_PW'] : ''; }
		// Cũng nhận user/pass trong body (một số cấu hình VietQR gửi kèm).
		if ( '' === $u ) { $b = $req->get_json_params(); if ( is_array( $b ) ) { $u = (string) ( isset( $b['username'] ) ? $b['username'] : '' ); $p = (string) ( isset( $b['password'] ) ? $b['password'] : '' ); } }
		$tk = null;
		foreach ( $ds as $t ) { if ( '' !== $t['pass'] && hash_equals( $t['user'], $u ) && hash_equals( $t['pass'], $p ) ) { $tk = $t; break; } }
		if ( ! $tk ) { return new WP_REST_Response( array( 'error' => true, 'errorReason' => 'sai username/password' ), 401 ); }
		$exp = time() + 12 * 3600;
		self::ghi_log( 'vietqr-official', '🔑 cấp token cho tài khoản "' . ( '' !== $tk['nhan'] ? $tk['nhan'] : $tk['user'] ) . '"', '' );
		return new WP_REST_Response( array( 'access_token' => self::vqr_make_token( $exp, $tk ), 'token_type' => 'Bearer', 'expires_in' => 12 * 3600 ), 200 );
	}
	/* VietQR gọi mỗi khi có biến động. VietQR ở TÀI KHOẢN KHÁC với SePay → đây là tiền THẬT
	   trên tài khoản đó, SePay không thấy → lưu thẳng vào SAO KÊ NGÂN HÀNG (saoke_gd), gộp chung.
	   Không sợ trùng: khác tài khoản + mã GD riêng (chống trùng theo sepay_id 'vqr-<mã>'). */
	public static function r_vqr_callback( $req ) {
		$h = self::auth_header( $req ); $bearer = 0 === stripos( $h, 'bearer ' ) ? trim( substr( $h, 7 ) ) : '';
		$tkVqr = '' !== $bearer ? self::vqr_check_token( $bearer ) : false;
		if ( ! $tkVqr ) {
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
			/* 0.49.0: payload thiếu số TK / ngân hàng thì lấy của TÀI KHOẢN mà token thuộc về — đây là chỗ hai tài khoản tách nhau ra. */
			'so_tk'     => (string) $g( array( 'bankaccount', 'bankAccount', 'accountNumber', 'account_number' ), (string) $tkVqr['so_tk'] ),
			'ngan_hang' => (string) $g( array( 'bankName', 'bank_name', 'bankCode' ), '' !== (string) $tkVqr['ngan_hang'] ? (string) $tkVqr['ngan_hang'] : 'VietQR' ),
			'loai'      => $loai,
			'tien'      => (int) round( self::num( $g( array( 'amount', 'transferAmount', 'amountIn', 'creditAmount' ), 0 ) ) ),
			'luy_ke'    => null,
			'noi_dung'  => (string) $g( array( 'content', 'description', 'orderInfo', 'addInfo' ) ),
			'ma_gd'     => '' !== $ref ? $ref : $maGD,
			'nguon'     => 'vietqr',
		) );
		/* 🔴 TRƯỚC 0.20.0 CỬA NÀY CHỈ GHI VÀO SAO KÊ NGÂN HÀNG. Bảng CỔNG (`saoke_cong`) — cái vẽ
		 *    ra màn hình "Sao Kê Việt QR", cái chia tiền theo từng máy — KHÔNG hề nhận dòng nào từ
		 *    webhook sống: hàm `cong_nhan_webhook()` có mà chưa một nơi nào gọi. Nó chỉ đầy lên khi
		 *    nạp file kết xuất hoặc kéo từ Sheet. Nghĩa là sau lần nạp file gần nhất, mọi giao dịch
		 *    mới KHÔNG phải "chưa rõ máy" — mà KHÔNG CÓ trên màn hình. Nay tiền về là vào thẳng.
		 *
		 * ⚠️ Chèn đúp là không thể: `luu_cong()` dò trùng theo khoá, và từ 0.20.0 dò thêm theo mã
		 *    tham chiếu + số tiền, nên nạp lại file kết xuất của cùng kỳ vẫn ra "0 dòng mới". */
		$kqCong = self::cong_nhan_webhook( 'vietqr', $req, array( 'soTK' => (string) $tkVqr['so_tk'] ) );
		$ghiCong = ( isset( $kqCong['moi'] ) && $kqCong['moi'] > 0 )
			? ( ' · bảng cổng +' . (int) $kqCong['moi'] ) : ' · bảng cổng: đã có';
		self::ghi_log( 'vietqr-official', ( $moi ? ( '✔ đã lưu vào Sao kê NH ' . ( '' !== $maGD ? $maGD : $ref ) ) : 'trùng, bỏ qua' ) . $ghiCong
			. ( '' !== (string) $tkVqr['nhan'] ? ( ' · TK "' . $tkVqr['nhan'] . '"' ) : '' ), $raw );
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
	/* Danh sách tài khoản VietQR để hiện ra màn — KHÔNG kèm mật khẩu (§4). */
	private static function vqr_tk_cong_khai_() {
		$ra = array();
		foreach ( self::vqr_tk_ds() as $t ) { $ra[] = array( 'id' => $t['id'], 'nhan' => $t['nhan'], 'user' => $t['user'], 'soTK' => $t['so_tk'], 'nganHang' => $t['ngan_hang'], 'coPass' => '' !== $t['pass'] ); }
		return $ra;
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
		return array( 'ok' => true, 'ver' => self::VER,
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
			'vqrTk' => self::vqr_tk_cong_khai_(),
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

	/* TẠM TRƯỚC khi VietQR API duyệt: kéo giao dịch cổng từ sheet NHẬT KÝ của app cũ.
	   Cột "Chi tiết" chứa payload thô Tingo {"values":[[...]]} — đúng thứ webhook nhận, nên
	   cong_doc_payload() đọc y hệt. Nạp vào bảng CỔNG (saoke_cong), dedup theo khoá -> không
	   đếm trùng dù kéo lại nhiều lần hay app cũ vẫn đang nhận thêm. */
	public static function keo_cong_log_sheet() {
		$url = trim( (string) get_option( 'saoke_cong_log_sheet', '' ) );
		if ( '' === $url ) { return new WP_Error( 'url', 'Chưa đặt link CSV Nhật ký (app cũ).', array( 'status' => 409 ) ); }
		$res = wp_remote_get( $url, array( 'timeout' => 60, 'redirection' => 5, 'headers' => array( 'Accept' => 'text/csv' ) ) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( 200 !== $code || false !== stripos( substr( $body, 0, 200 ), '<html' ) ) {
			return new WP_Error( 'sheet', 'Không đọc được CSV (HTTP ' . $code . '). Sheet Nhật ký phải "Xuất bản lên web" dạng CSV.', array( 'status' => 502 ) );
		}
		$lines = preg_split( '/\r\n|\r|\n/', $body );
		$rows = array();
		foreach ( $lines as $ln ) { if ( '' === trim( $ln ) ) { continue; } $rows[] = str_getcsv( $ln ); }
		if ( count( $rows ) < 2 ) { return array( 'moi' => 0, 'trung' => 0, 'message' => 'sheet chưa có dữ liệu' ); }
		$cChi = -1; $cKq = -1;
		$col = array( 'nguon' => -1, 'thoiDiem' => -1, 'soTien' => -1, 'maGD' => -1, 'ref' => -1, 'huong' => -1, 'trangThai' => -1, 'soTK' => -1, 'noiDung' => -1, 'diemBan' => -1, 'docDuoc' => -1 );
		foreach ( (array) $rows[0] as $i => $h ) {
			$x = self::kd( $h ); $i = (int) $i;
			if ( $cChi < 0 && preg_match( '/chi tiet|payload|^raw|payload tho/', $x ) ) { $cChi = $i; }
			if ( $cKq < 0 && preg_match( '/ket qua|^result/', $x ) ) { $cKq = $i; }
			if ( $col['nguon'] < 0 && preg_match( '/^nguon$/', $x ) ) { $col['nguon'] = $i; }
			if ( $col['thoiDiem'] < 0 && preg_match( '/thoi diem/', $x ) ) { $col['thoiDiem'] = $i; }
			if ( $col['soTien'] < 0 && preg_match( '/so tien/', $x ) ) { $col['soTien'] = $i; }
			if ( $col['maGD'] < 0 && preg_match( '/ma giao dich|ma gd/', $x ) ) { $col['maGD'] = $i; }
			if ( $col['ref'] < 0 && preg_match( '/tham chieu|^ref/', $x ) ) { $col['ref'] = $i; }
			if ( $col['huong'] < 0 && preg_match( '/^huong/', $x ) ) { $col['huong'] = $i; }
			if ( $col['trangThai'] < 0 && preg_match( '/trang thai/', $x ) ) { $col['trangThai'] = $i; }
			if ( $col['soTK'] < 0 && preg_match( '/so tk|tai khoan/', $x ) ) { $col['soTK'] = $i; }
			if ( $col['noiDung'] < 0 && preg_match( '/noi dung/', $x ) ) { $col['noiDung'] = $i; }
			if ( $col['diemBan'] < 0 && preg_match( '/diem ban|terminal/', $x ) ) { $col['diemBan'] = $i; }
			if ( $col['docDuoc'] < 0 && preg_match( '/doc duoc/', $x ) ) { $col['docDuoc'] = $i; }
		}
		$moi = 0; $trung = 0; $kho = 0;
		// ── CHẾ ĐỘ CỘT RỜI (tab CongThanhToan) — đủ lịch sử mọi ngày, đọc trực tiếp, không cần parse payload ──
		if ( $col['thoiDiem'] >= 0 && $col['soTien'] >= 0 && ( $col['maGD'] >= 0 || $col['ref'] >= 0 ) ) {
			for ( $i = 1; $i < count( $rows ); $i++ ) {
				$r = $rows[ $i ];
				$g = function ( $k ) use ( $r, $col ) { return ( $col[ $k ] >= 0 && isset( $r[ $col[ $k ] ] ) ) ? trim( (string) $r[ $col[ $k ] ] ) : ''; };
				$nguon = strtolower( $g( 'nguon' ) ); if ( ! in_array( $nguon, self::cong_ds(), true ) ) { $nguon = 'vietqr'; }
				$thoiDiem = self::cong_ngay( $g( 'thoiDiem' ) ); $soTien = self::num( $g( 'soTien' ) );
				$maGD = $g( 'maGD' ); $ref = $g( 'ref' );
				if ( '' === $thoiDiem || $soTien <= 0 || ( '' === $maGD && '' === $ref ) ) { continue; }
				$huong = $g( 'huong' ); if ( '' === $huong ) { $huong = 'Đến'; }
				$tx = array( 'nguon' => $nguon, 'maGD' => $maGD, 'ref' => $ref, 'thoiDiem' => $thoiDiem, 'soTien' => $soTien, 'huong' => $huong,
					'trangThai' => $g( 'trangThai' ), 'soTK' => $g( 'soTK' ), 'noiDung' => $g( 'noiDung' ), 'diemBan' => $g( 'diemBan' ),
					'docDuoc' => ( $col['docDuoc'] >= 0 ? ( 'x' === strtolower( $g( 'docDuoc' ) ) ) : true ) );
				$tx['khoa'] = self::cong_khoa( $nguon, $tx, $maGD . '|' . $ref ); $tx['raw'] = $g( 'noiDung' );
				if ( self::luu_cong( $tx ) ) { $moi++; } else { $trung++; }
				self::ghe_dau_ngay_( self::cong_ngay_mysql( $tx['thoiDiem'] ) );
			}
			self::day_ghe_ngay_don_();
			update_option( 'saoke_cong_log_last', array( 'luc' => current_time( 'mysql' ), 'kq' => 'CongThanhToan: mới ' . $moi . ', trùng ' . $trung ) );
			return array( 'moi' => $moi, 'trung' => $trung, 'kho' => 0 );
		}
		// ── CHẾ ĐỘ PAYLOAD (tab WebhookLog — cột Chi tiết) ──
		if ( $cChi < 0 ) { foreach ( (array) $rows[1] as $i => $v ) { if ( false !== strpos( (string) $v, '"values"' ) ) { $cChi = (int) $i; break; } } }
		if ( $cChi < 0 ) { return new WP_Error( 'col', 'Sheet không có cột rời (Thời điểm/Số tiền/Mã GD) lẫn cột "Chi tiết" (payload). Publish tab CongThanhToan hoặc WebhookLog.', array( 'status' => 400 ) ); }
		for ( $i = 1; $i < count( $rows ); $i++ ) {
			$raw = isset( $rows[ $i ][ $cChi ] ) ? (string) $rows[ $i ][ $cChi ] : '';
			if ( '' === trim( $raw ) || false === strpos( $raw, '"values"' ) ) { continue; }
			$nguon = 'vietqr';
			if ( $cKq >= 0 && isset( $rows[ $i ][ $cKq ] ) && preg_match( '/\[([a-z0-9]+)\]/i', (string) $rows[ $i ][ $cKq ], $mm ) ) { $gg = strtolower( $mm[1] ); if ( in_array( $gg, self::cong_ds(), true ) ) { $nguon = $gg; } }
			$list = self::cong_doc_payload( $raw );
			if ( ! count( $list ) ) { $kho++; continue; }
			foreach ( $list as $tx ) {
				$tx['nguon'] = $nguon; $tx['khoa'] = self::cong_khoa( $nguon, $tx, $raw ); $tx['raw'] = $raw;
				if ( self::luu_cong( $tx ) ) { $moi++; } else { $trung++; }
				self::ghe_dau_ngay_( self::cong_ngay_mysql( $tx['thoiDiem'] ) );
			}
		}
		self::day_ghe_ngay_don_();
		update_option( 'saoke_cong_log_last', array( 'luc' => current_time( 'mysql' ), 'kq' => 'WebhookLog: mới ' . $moi . ', trùng ' . $trung . ( $kho ? ', không đọc ' . $kho : '' ) ) );
		return array( 'moi' => $moi, 'trung' => $trung, 'kho' => $kho );
	}
	public static function cron_cong_log() {
		if ( '' === trim( (string) get_option( 'saoke_cong_log_sheet', '' ) ) ) { return; }
		$r = self::keo_cong_log_sheet();
		$log = is_wp_error( $r ) ? ( 'lỗi: ' . $r->get_error_message() ) : ( 'mới ' . $r['moi'] . ', trùng ' . $r['trung'] );
		update_option( 'saoke_cong_log_last', array( 'luc' => current_time( 'mysql' ), 'kq' => $log ) );
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
		if ( $tu )  { $wa[] = 'thoi_diem>=%s'; $aa[] = self::moc_tu_( $tu ); }
		if ( $den ) { $wa[] = 'thoi_diem<%s'; $aa[] = self::moc_den_( $den ); }
		$rowsC = $wpdb->get_results( $wpdb->prepare( "SELECT thoi_diem, so_tien, ma_gd, ref, huong, trang_thai, so_tk, noi_dung, diem_ban, ma_ch, may_tay, doc_duoc FROM $tc WHERE " . implode( ' AND ', $wa ) . " ORDER BY thoi_diem DESC, id DESC LIMIT 3000", $aa ), ARRAY_A );
		$cong = array(); $congTien = 0; $congKho = 0;
		foreach ( (array) $rowsC as $r ) {
			if ( (int) $r['doc_duoc'] !== 1 ) { $congKho++; continue; }
			if ( 'Đi' === $r['huong'] ) { continue; }
			$tenMay = self::cong_may_dong( $r['noi_dung'], isset( $r['ma_ch'] ) ? $r['ma_ch'] : '', $r['diem_ban'], isset( $r['may_tay'] ) ? $r['may_tay'] : '' );
			$cong[] = array( 'thoiDiem' => self::ymd2vn( $r['thoi_diem'] ), 'soTien' => (int) $r['so_tien'],
				'maGD' => $r['ma_gd'], 'ref' => $r['ref'], 'trangThai' => $r['trang_thai'], 'soTK' => $r['so_tk'],
				'noiDung' => $r['noi_dung'], 'maCH' => isset( $r['ma_ch'] ) ? (string) $r['ma_ch'] : '',
				'tenMay' => $tenMay, 'coSo' => self::cong_coso( $tenMay ) );
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
		// POSH: Cửa hàng chuẩn là ĐỊA ĐIỂM bên trang Ghế -> gán thẳng, KHÔNG cần mã nộp KH.
		// Nếu có nhập "Mã nộp tiền" thì lưu làm MÃ NỘP RIÊNG của cơ sở đó (để lọc sao kê ngân hàng
		// xem cơ sở đã nộp tiền mặt chưa). Bỏ trống = xoá mã của cơ sở.
		if ( self::ghe_co() && ( self::ghe_la_coso( $tc ) || '1' === (string) $req->get_param( 'gheMoi' ) ) ) {   // gheMoi: cơ sở Ghế vừa tạo trong lượt này (rpc_taoCoSoGhe), bộ đệm tên chưa có
			$cm = self::coso_ma_map(); $ck = self::chuan_ch( $tc );
			if ( '' !== $mb ) { $cm[ $ck ] = mb_substr( $mb, 0, 60 ); } else { unset( $cm[ $ck ] ); }
			update_option( 'saoke_coso_ma', $cm );
			$mb = '';
		} else {
			$suy = self::ax_ma_nop( array( 'maBank' => $mb, 'tenChuan' => $tc ), self::map_ten_diem() );
			if ( '' === $suy['ma'] ) { return new WP_Error( 'ma', 'Chưa xác định được mã nộp tiền: ' . $suy['vi'] . '. Chọn Cửa hàng chuẩn đúng tên địa điểm (trang Ghế) hoặc điểm nộp, hoặc điền thẳng Mã bank.', array( 'status' => 400 ) ); }
			$mb = $suy['ma'];
		}
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
		update_option( 'saoke_anhxa', array_values( $all ) ); self::day_ghe_gan_day_( 7 );
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
		update_option( 'saoke_anhxa', array_values( $ra ) ); self::day_ghe_gan_day_( 7 );
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
		if ( $tu )  { $wa[] = 'thoi_diem>=%s'; $aa[] = self::moc_tu_( $tu ); }
		if ( $den ) { $wa[] = 'thoi_diem<%s'; $aa[] = self::moc_den_( $den ); }
		$rc = $wpdb->get_results( $wpdb->prepare( "SELECT thoi_diem, so_tien, noi_dung, diem_ban, ma_ch, may_tay FROM $tc WHERE " . implode( ' AND ', $wa ), $aa ), ARRAY_A );
		foreach ( (array) $rc as $r ) {
			$tien = (int) $r['so_tien']; if ( $tien <= 0 ) { continue; } $tong += $tien;
			$ngay = self::ymd2vn( $r['thoi_diem'] );
			$tenMay = self::cong_may_dong( $r['noi_dung'], isset( $r['ma_ch'] ) ? $r['ma_ch'] : '', $r['diem_ban'], isset( $r['may_tay'] ) ? $r['may_tay'] : '' );
			if ( '' === $tenMay ) { $chuaRoMay++; $chuaRoTien += $tien; continue; }
			$ax = self::ax_theo_ngay( self::ax_cua_may_( $anhXa, $tenMay, isset( $r['ma_ch'] ) ? $r['ma_ch'] : '' ), $ngay );
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
	/* Mốc so THẲNG vào cột thoi_diem (DATETIME) thay cho DATE(thoi_diem)>=… / <=…: bọc hàm lên cột là
	   MySQL bỏ chỉ mục `thoi_diem`, quét cả bảng saoke_cong mỗi lượt. 'Y-m-d' → 'Y-m-d 00:00:00'; mốc ĐẾN là
	   00:00:00 của NGÀY SAU, dùng với `<` để lấy trọn ngày cuối. Không phải Y-m-d thì trả nguyên (0.50.0). */
	private static function moc_tu_( $ymd ) { return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ymd ) ? $ymd . ' 00:00:00' : (string) $ymd; }
	private static function moc_den_( $ymd ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m ) ) { return (string) $ymd; }
		return gmdate( 'Y-m-d 00:00:00', gmmktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] ) + 86400 );
	}
	private static function khoang_thoi_diem_( $tu, $den ) { return array( self::moc_tu_( $tu ), self::moc_den_( $den ) ); }
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
	/* Khoá LỎNG để khớp tên "gần đúng" — anh Thắng 12/09/2026: nội dung CK ghi thêm phần trong
	   NGOẶC và ĐUÔI sau gạch, vd "CGV VINCOM XUÂN KHÁNH ( Cần Thơ ) — Cần Thơ", nên chuan_ch() không
	   khớp điểm "CGV VINCOM XUÂN KHÁNH ( Cần Thơ )". Bỏ (…) và đuôi sau " — "/" – "/" - " rồi mới
	   chuẩn hoá. Dùng làm ĐƯỜNG LUI, KHÔNG thay chuan_ch() (khoá exact vẫn thắng — xem ax_ma_nop). */
	/* 🔴 CHỮ HIỆU "POSH" KHÔNG PHẢI TÊN MÁY — anh Thắng 25/09/2026, file store_export tài khoản VietQR thứ hai: 989/1666
	   cửa hàng đặt tên "AE Huế 04 Posh", "AEHP 01 Posh"; điểm bán ghi "POSH Aeon Mall Huế". Đuôi ấy làm cong_coso() không cắt
	   được số máy (mỗi máy thành một "cơ sở" — 473 dòng "chưa gán mã") và chuan_may() ra `aehp01posh` ≠ `aehp1` của ghế
	   "AEHP-1" → tiền rơi vào "không khớp". Bỏ chữ hiệu ở ĐẦU/CUỐI trước khi so; giữa tên thì giữ ("OCP POSH 01" là tên). */
	public static function bo_duoi_hieu_( $s ) { return trim( preg_replace( '/[\s.\-–—]*\bposh\s*$/iu', '', trim( (string) $s ) ) ); }
	public static function bo_hieu_( $s ) { return trim( preg_replace( '/^\s*posh\b[\s.:\-–—]*/iu', '', self::bo_duoi_hieu_( $s ) ) ); }
	public static function chuan_ch_long( $s ) {
		$s = (string) $s;
		$s = preg_replace( '/\([^)]*\)/u', ' ', $s );        // bỏ mọi phần trong ngoặc
		$p = preg_split( '/\s[—–\-]\s/u', $s );              // cắt ở " — " / " – " / " - "
		if ( is_array( $p ) && count( $p ) ) { $s = $p[0]; }
		return self::chuan_ch( $s );
	}

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
		return self::may_hop_le( $s );
	}

	/* 🔴 CHỈ NHẬN CHUỖI TRÔNG NHƯ MÃ MÁY, KHÔNG NHẬN CẢ ĐOẠN NỘI DUNG.
	 *
	 * Anh Thắng 10/09/2026: *"trang sao kê nó bị lệch dữ liệu"*. Bản trước lấy NGUYÊN nội dung
	 * làm tên máy hễ nó không mở đầu bằng "VQR", nên một dòng chuyển khoản ngân hàng
	 * `REM TFR AC:1770260769 O@L_080005_…/CONG TY TNHH GIAI TRI K&H_…` thành một cái "máy" dài
	 * 175 ký tự. Bảng để `white-space:nowrap` nên ô Máy phình ra đẩy hết cột sau ra khỏi màn —
	 * đó là chỗ "lệch" người ta thấy. Nhưng hại hơn phần nhìn: `cong_coso()` cắt số cuối chuỗi
	 * đó ra một "cửa hàng" bịa, rồi tiền của dòng ấy nằm trong danh sách "chưa gán mã" như một
	 * cơ sở thật — kế toán đi tìm một cửa hàng không tồn tại.
	 *
	 * Mã máy thật đều ngắn: `AMTP08`, `AMBT 11`, `GO AC 02`, `VC GP 07`. Cho phép chữ có dấu vì
	 * đường lùi `diem_ban` mang tên cửa hàng tiếng Việt ("AEON MALL BÌNH TÂN"), nhưng chặn dấu
	 * câu (`: @ / _#` …) và chuỗi quá dài/quá nhiều từ — dấu hiệu của một câu, không phải một mã.
	 *
	 * Không khớp thì trả '' để dòng đó hiện "chưa rõ máy": nói KHÔNG BIẾT là đúng, bịa ra một
	 * cái máy mới là sai. Tiền vẫn vào tổng cổng và vào `chuaRoTien` để có người soi, không mất
	 * đồng nào.
	 */
	const MAY_DAI_MAX = 40;   // dài hơn thế là một câu, không phải mã máy
	const MAY_TU_MAX  = 6;    // "LOTTE MART NAM SÀI GÒN" đã là 5 từ
	private static function may_hop_le( $s ) {
		$s = preg_replace( '/\s+/', ' ', trim( mb_strtoupper( (string) $s, 'UTF-8' ) ) );
		if ( '' === $s || mb_strlen( $s, 'UTF-8' ) > self::MAY_DAI_MAX ) { return ''; }
		if ( ! preg_match( '/^[\p{L}\p{N}]+(?:[ _\-][\p{L}\p{N}]+)*$/u', $s ) ) { return ''; }
		if ( count( explode( ' ', $s ) ) > self::MAY_TU_MAX ) { return ''; }
		return $s;
	}
	/* ════════════════════════════════════════════════════════════════════════════════════════
	 * BẢN ĐỒ CỬA HÀNG VIỆT QR — "mã cửa hàng" của cổng là thứ DUY NHẤT luôn có.
	 * ════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 Anh Thắng 12/09/2026, hai ảnh cạnh nhau — bảng của mình đầy dòng *"chưa rõ máy"* với nội
	 *    dung `VQR2637642208V7L PaymentForOrder`, còn cổng Việt QR xuất ra thì mỗi giao dịch có
	 *    thêm **Mã cửa hàng** và **Mã điểm bán**: *"một số giao dịch nội dung không rõ ràng, mà
	 *    webhook gửi về thì thiếu thông tin, vậy để xác nhận giao dịch đó của ai, thì mình sẽ tải
	 *    thẳng sao kê của bên VietQR. Nó có đủ các trường để xác định giao dịch đó là của máy này."*
	 *
	 * 🔴 VÌ SAO KHÔNG SỬA ĐƯỢC BẰNG CÁCH ĐỌC KHÉO HƠN. Nội dung `PaymentForOrder` là chuỗi do CỔNG
	 *    tự đặt khi khách quét QR tĩnh — trong đó KHÔNG có tên máy, không có gì để vớt. Mọi cách
	 *    "đoán thông minh" ở đây đều là bịa. Dữ liệu thật nằm ở một trường mà webhook không gửi.
	 *
	 * 🔴 HAI BẢNG, MỘT KHOÁ NỐI. Cổng xuất ra hai bảng khác nhau:
	 *      · *Giao dịch thanh toán* — mỗi dòng có `Mã đơn hàng` (VPB…, chính là `ma_gd` bên mình),
	 *        `Mã cửa hàng` (10 ký tự) và `Mã điểm bán`;
	 *      · *Danh sách cửa hàng*   — `Mã cửa hàng` → `Tên cửa hàng` (ĐÚNG LÀ TÊN MÁY: "LM-NSG 01")
	 *        và `Mã điểm bán` → `Tên điểm bán` (cơ sở: "Posh Lotte Nam Sài Gòn").
	 *    Khoá nối là **Mã cửa hàng**. ⚠️ KHÔNG phải "Mã điểm bán": hai bảng ghi mã điểm bán theo
	 *    hai kiểu khác nhau (`VVB635365` ở bảng giao dịch, `MC1754018340421` ở bảng cửa hàng), nối
	 *    theo nó là nối trượt mà không báo gì.
	 *
	 * ⚠️ LƯU BẰNG OPTION, KHÔNG DỰNG BẢNG. Đây là một bản đồ vài trăm dòng, đọc mỗi lần vẽ màn và
	 *    gần như không bao giờ đổi — cùng hình dạng với `saoke_anhxa` / `saoke_diem` đang có. Dựng
	 *    thêm một bảng là thêm một thứ phải nâng cấp, phải sao lưu, phải nhớ.
	 */
	private static function vqr_ds_ch() {
		if ( null === self::$vqr_ch_cache ) { $v = get_option( 'saoke_vqr_ch' ); self::$vqr_ch_cache = is_array( $v ) ? $v : array(); }
		return self::$vqr_ch_cache;
	}

	/** Chuẩn hoá mã cửa hàng của cổng: hoa, bỏ khoảng trắng. Mã là chuỗi máy sinh, so phải khít. */
	private static function vqr_ma_ch( $s ) {
		$k = preg_replace( '/\s+/', '', mb_strtoupper( trim( (string) $s ), 'UTF-8' ) );
		/* ⚠️ Ô RỖNG CỦA FILE KẾT XUẤT LÀ DẤU GẠCH NGANG, không phải ô trắng — trong file thật
		   của anh Thắng, dòng "Vãng lai" có `Mã cửa hàng` = `-`. Không chặn thì `-` thành một
		   "mã" hợp lệ: nó nằm trong danh sách "mã chưa có trong bản đồ" đời đời, và người đọc đi
		   tìm một cửa hàng tên `-`. Không có lấy một chữ hay số thì coi như KHÔNG CÓ mã. */
		return preg_match( '/[\p{L}\p{N}]/u', $k ) ? $k : '';
	}

	/**
	 * Trạng thái trong file kết xuất có nghĩa XẤU không (thất bại · huỷ · hoàn · chờ).
	 *
	 * ⚠️ BẮT NGHĨA XẤU, KHÔNG BẮT NGHĨA TỐT. Cổng đổi "Thành công" sang "Success" (hay thêm dấu,
	 *    đổi hoa thường) là chuyện của họ; nếu em đòi khớp đúng chữ tốt thì một hôm cổng đổi chữ
	 *    là CẢ FILE bị bỏ sạch, và màn hình chỉ nói "0 dòng nạp được". Bắt nghĩa xấu thì sai sót
	 *    tệ nhất là nhận thừa một dòng — thấy được ở ô chênh lệch, sửa được.
	 */
	private static function cong_tt_hong( $tt ) {
		$k = self::kd( $tt );   // bỏ dấu + chữ thường
		foreach ( array( 'that bai', 'khong thanh cong', 'huy', 'tu choi', 'hoan tien', 'hoan tra',
			'cho xu ly', 'dang xu ly', 'fail', 'cancel', 'refund', 'reject', 'pending', 'error' ) as $x ) {
			if ( false !== strpos( $k, $x ) ) { return true; }
		}
		return false;
	}

	/** Mã cửa hàng -> TÊN MÁY (tên cửa hàng bên cổng). '' nếu chưa có trong bản đồ. */
	/* TÊN ĐIỂM BÁN của một mã cửa hàng (bản đồ store_export, cột "Tên điểm bán") — đó mới là cấp CƠ SỞ của cổng
	   (182 điểm bán / 1666 cửa hàng trong file 25/09/2026). Tra theo mã cửa hàng rồi mã điểm bán; "-"/rỗng → ''. */
	private static function vqr_diem_theo_ma_( $ma_ch ) {
		$k = self::vqr_ma_ch( $ma_ch ); if ( '' === $k ) { return ''; }
		if ( null === self::$vqr_diem_cache ) {
			self::$vqr_diem_cache = array();
			foreach ( self::vqr_ds_ch() as $ma => $v ) {
				$td = trim( (string) ( isset( $v['tenDiem'] ) ? $v['tenDiem'] : '' ) );
				if ( '' === $td || '-' === $td ) { continue; }
				$km = self::vqr_ma_ch( $ma ); if ( '' !== $km && ! isset( self::$vqr_diem_cache[ $km ] ) ) { self::$vqr_diem_cache[ $km ] = $td; }
				$kd = self::vqr_ma_ch( isset( $v['maDiem'] ) ? $v['maDiem'] : '' ); if ( '' !== $kd && ! isset( self::$vqr_diem_cache[ $kd ] ) ) { self::$vqr_diem_cache[ $kd ] = $td; }
			}
		}
		return isset( self::$vqr_diem_cache[ $k ] ) ? self::$vqr_diem_cache[ $k ] : '';
	}
	/* Dòng ánh xạ của một dòng cổng — thử lần lượt: tên máy · cơ sở suy từ tên máy · TÊN ĐIỂM BÁN của mã cửa hàng.
	   0.51.0: MỘT chỗ tra cho bốn màn (trước đây bốn bản chép cùng một biểu thức); nút "Gán vào cơ sở có sẵn" ghi ánh xạ
	   theo nhãn điểm bán nên tra phải biết khoá ấy. null = không có dòng nào. */
	private static function ax_cua_may_( $anhXa, $tenMay, $ma_ch = '' ) {
		foreach ( array( $tenMay, self::cong_coso( $tenMay ), self::vqr_diem_theo_ma_( $ma_ch ) ) as $t ) {
			$k = self::chuan_ch( $t ); if ( '' !== $k && isset( $anhXa[ $k ] ) ) { return $anhXa[ $k ]; }
		}
		return null;
	}
	private static function vqr_may_theo_ma( $ma_ch ) {
		$k = self::vqr_ma_ch( $ma_ch );
		if ( '' === $k ) { return ''; }
		if ( isset( self::$vqr_may_memo[ $k ] ) ) { return self::$vqr_may_memo[ $k ]; }
		$ds = self::vqr_ds_ch();
		if ( isset( $ds[ $k ]['ten'] ) ) { return self::$vqr_may_memo[ $k ] = (string) $ds[ $k ]['ten']; }
		/* 🔴 CỔNG KHÔNG PHẢI LÚC NÀO CŨNG GỬI MÃ CỬA HÀNG. Bản kết xuất giao dịch có HAI cột mã —
		   `Mã cửa hàng` (vd `RJFSHCSXE9`) và `Mã điểm bán` (vd `VVB851980`) — và tuỳ giao dịch,
		   cái có mặt là cái nào thì không đoán trước được. Bản đồ `store_export` có cả hai cột,
		   nên tra hụt ở khoá chính thì còn một đường nữa; không có đường này thì dò ra mã điểm
		   rồi vẫn trả về "chưa rõ máy" — công cốc. */
		$ban = self::vqr_ma_tat_ca();
		return self::$vqr_may_memo[ $k ] = ( isset( $ban[ $k ] ) ? (string) $ban[ $k ] : '' );
	}

	/**
	 * TÊN MÁY CỦA MỘT DÒNG CỔNG — MỘT chỗ duy nhất quyết định, ba màn cùng gọi.
	 *
	 * ⚠️ Trước 0.17.0 luật này được CHÉP ở hai nơi (bảng Sao Kê cổng và phép gom tiền theo mã nộp).
	 *    Hai bản chép của một luật thì sớm muộn lệch nhau, và lệch ở đây nghĩa là cùng một giao
	 *    dịch, màn này tính cho máy A còn màn kia bỏ vào "chưa rõ".
	 *
	 * Thứ tự có chủ ý:
	 *   1. TÊN TRONG NỘI DUNG — khách quét QR của đúng máy ấy, chắc nhất.
	 *   2. BẢN ĐỒ theo mã cửa hàng — do cổng xuất ra, cũng là dữ liệu của cổng, chỉ thiếu ở webhook.
	 *   3. `diem_ban` — đường lùi cũ, thường là tên cửa hàng tiếng Việt.
	 *   4. '' = "chưa rõ máy". Nói KHÔNG BIẾT vẫn đúng hơn bịa ra một cái máy.
	 */
	private static function cong_may_dong( $noi_dung, $ma_ch = '', $diem_ban = '', $may_tay = '' ) {
		/* 0. GÁN TAY THẮNG MỌI SUY ĐOÁN — anh Thắng 12/09/2026: "một số giao dịch dò không ra, muốn
		   gán thủ công". Giao dịch QR tĩnh (PaymentForOrder) không có tên máy trong nội dung LẪN mã
		   cửa hàng, không cách nào suy ra; admin chỉ định tay thì phải THẮNG. Số đã được làm sạch ở
		   rpc_ganMayTay() lúc ghi nên trả thẳng, không qua may_hop_le() (giữ đúng "may_hop_le gọi 2
		   chỗ"); admin có quyền đặt tên không theo khuôn máy tự động. */
		$mt = trim( (string) $may_tay );
		if ( '' !== $mt ) { return $mt; }
		$ten = self::cong_ten_may( $noi_dung );
		if ( '' !== $ten ) { return $ten; }
		$ten = self::vqr_may_theo_ma( $ma_ch );
		if ( '' !== $ten ) { return $ten; }
		return self::may_hop_le( (string) $diem_ban );
	}

	/* Cơ sở = tên máy bỏ số máy cuối: "AMTP 12" -> "AMTP". */
	private static function cong_coso( $ten_may ) {
		$s = self::bo_duoi_hieu_( $ten_may );   // 0.51.0: "AE HUẾ 04 POSH" → "AE HUẾ"
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
		/* Không khớp exact thì thử KHOÁ LỎNG (bỏ ngoặc/đuôi) — vd "CGV VINCOM XUÂN KHÁNH ( Cần Thơ )
		   — Cần Thơ" khớp điểm "CGV VINCOM XUÂN KHÁNH ( Cần Thơ )". Exact vẫn ưu tiên. */
		if ( ! isset( $map_ten[ $k ] ) ) { $kl = self::chuan_ch_long( $tc ); if ( isset( $map_ten[ $kl ] ) ) { $k = $kl; } }
		/* Câu báo phải nói đủ CẢ BA chỗ đã dò, không thì người dùng khai mã ở tab Cấu hình xong vẫn
		   tưởng mình khai sai chỗ (0.37.0 mới nối hai sổ ấy vào đây). */
		if ( ! isset( $map_ten[ $k ] ) ) { return array( 'ma' => '', 'vi' => 'tên "' . $tc . '" không có trong danh sách điểm, cũng chưa có mã ở tab Cấu hình (bảng Ghế lẫn Khu vui chơi)' ); }
		if ( ! empty( $map_ten[ $k ]['trung'] ) ) { return array( 'ma' => '', 'vi' => 'tên "' . $tc . '" bị nhiều điểm dùng chung — phải điền Mã bank' ); }
		return array( 'ma' => $map_ten[ $k ]['ma'], 'vi' => '' );
	}

	// ═══════════════ CẦU NỐI PLUGIN GHẾ (POSH) — VietQR = doanh thu ghế theo địa điểm ═══════════════
	// Hai plugin chung 1 WordPress/DB nên đọc thẳng bảng của Ghế, không export.
	private static function ghe_tbl( $n ) { global $wpdb; return $wpdb->prefix . 'vhg_' . $n; }
	private static function ghe_co() {
		global $wpdb; static $co = null; if ( null !== $co ) { return $co; }
		$t = self::ghe_tbl( 'coso' ); $co = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t );
		return $co;
	}
	/* Danh sách địa điểm (cơ sở) bên Ghế: [['ten'=>..,'tinh'=>..,'maKh'=>..], ...]. */
	private static function ghe_ds_coso() {
		if ( ! self::ghe_co() ) { return array(); }
		global $wpdb; static $ds = null; if ( null !== $ds ) { return $ds; }
		/* `SELECT *` chứ không kể cột: `bi_danh` có từ Ghế 2.137.0, Ghế cũ hơn thì không có cột ấy —
		   kể tên cột là câu SELECT hỏng và CẢ danh sách cơ sở về rỗng, không riêng bí danh. */
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::ghe_tbl( 'coso' ) . ' ORDER BY ten ASC', ARRAY_A );
		$ds = array(); foreach ( (array) $rows as $r ) { $ds[] = array( 'ten' => (string) $r['ten'], 'tinh' => (string) ( isset( $r['tinh'] ) ? $r['tinh'] : '' ), 'maKh' => (string) ( isset( $r['ma_kh'] ) ? $r['ma_kh'] : '' ), 'biDanh' => (string) ( isset( $r['bi_danh'] ) ? $r['bi_danh'] : '' ) ); }
		return $ds;
	}
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * NHẬN THÊM CƠ SỞ KHU VUI CHƠI TỪ PLUGIN CHI PHÍ (0.35.0)
	 *
	 * Anh Thắng 16/09/2026: *"nhận thêm cơ sở khu vui chơi từ trang chi phí KVC"*.
	 *
	 * Tới nay Sao Kê chỉ biết cơ sở bên Ghế — tức chỉ mảng ghế massage (POSH). Mảng khu vui chơi
	 * không có ghế nào nên không bao giờ xuất hiện, và hệ quả là mọi màn đối chiếu nộp tiền đều
	 * lặng lẽ bỏ sót nó: không có dòng thì không ai thấy thiếu.
	 *
	 * Bên Chi Phí, KVC là một ĐƠN VỊ (cạnh POSH, dưới nhà mẹ K&H — xem `VHCP_DonVi`). Cơ sở của
	 * nó nằm trong danh mục `CH_CoSo` với cột Đơn vị = KVC.
	 *
	 * 🔴 GỌI QUA LỚP CỦA PLUGIN KIA, KHÔNG ĐỌC THẲNG BẢNG. Chính `VHCP_Cfg::hut_coso_ghe()` đã
	 *    ghi luật ấy cho chiều ngược lại. Đọc thẳng bảng của người ta là ngày họ đổi cột thì bên
	 *    này hỏng mà không ai báo. `cfg_static()` là cửa công khai của họ, lại có sẵn bộ nhớ đệm
	 *    5 phút nên gọi nhiều lượt cũng không nặng.
	 *
	 * 🔴 TÊN ĐƠN VỊ KHAI Ở OPTION, KHÔNG GÕ CỨNG. Đổi tên đơn vị bên kia là chuyện của người
	 *    dùng, không phải chuyện phải sửa mã. Mặc định 'KVC'.
	 *
	 * ⚠️ KHỬ TRÙNG THEO `chuan_ch()`. Một cơ sở có thể có mặt ở CẢ HAI bên (Chi Phí tự hút cơ sở
	 *    từ Ghế sang — xem `hut_coso_ghe()`). Không khử là mỗi cơ sở ấy ra hai dòng, và ở màn đối
	 *    chiếu nộp tiền thì tiền của nó bị đếm hai lần. Đếm thiếu ai cũng thấy; đếm gấp đôi
	 *    không ai thấy (CLAUDE.md §8).
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Đơn vị bên Chi Phí mà mình nhận cơ sở về. */
	private static function dv_chi_phi() {
		$v = trim( (string) get_option( 'saoke_dv_chi_phi', '' ) );
		return '' !== $v ? $v : 'KVC';
	}

	/** Plugin Chi Phí có mặt và mở đúng cửa công khai mình cần không. */
	private static function chiphi_co() {
		return class_exists( 'VHCP_Cfg' ) && method_exists( 'VHCP_Cfg', 'cfg_static' )
			&& class_exists( 'VHCP_DonVi' ) && method_exists( 'VHCP_DonVi', 'bang' );
	}

	/* Cơ sở thuộc đơn vị KVC bên Chi Phí, trả về CÙNG HÌNH DẠNG với ghe_ds_coso(). */
	private static function chiphi_ds_coso() {
		if ( ! self::chiphi_co() ) { return array(); }
		static $ds = null; if ( null !== $ds ) { return $ds; }
		$dv = self::dv_chi_phi(); $ds = array(); $thay = array();
		$cfg = VHCP_Cfg::cfg_static();
		foreach ( (array) ( isset( $cfg['coso'] ) ? $cfg['coso'] : array() ) as $c ) {
			$ten = trim( (string) ( isset( $c['ten'] ) ? $c['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			/* 🔴 KHỬ TRÙNG NGAY TRONG SỔ NÀY, đừng trông vào hàm gộp ở ngoài. Sổ mã khoá theo
			   `chuan_ch(tên)`, nên hai dòng "AEON TÂN PHÚ" và "Aeon Tân Phú" trong cùng danh mục
			   Chi Phí sẽ ra HAI hàng nhập mà chung MỘT ô lưu: gõ hàng dưới là mất mã hàng trên,
			   và không có gì báo. (Bên Chi Phí chặn trùng lúc thêm bằng so chữ thường, nhưng dữ
			   liệu cũ nạp từ bảng tính thì không qua cửa ấy.) */
			$k = self::chuan_ch( $ten );
			if ( '' === $k || isset( $thay[ $k ] ) ) { continue; }
			$thay[ $k ] = 1;
			if ( ! VHCP_DonVi::bang( isset( $c['donVi'] ) ? $c['donVi'] : '', $dv ) ) { continue; }
			/* ⚠️ Cơ sở ĐÃ ĐÓNG CỬA thì bỏ — để lại là mỗi kỳ đối chiếu lại có một dòng "chưa nộp"
			   vĩnh viễn đỏ, và một dòng đỏ không bao giờ xanh được là dòng người ta thôi nhìn. */
			if ( '' !== trim( (string) ( isset( $c['dongCua'] ) ? $c['dongCua'] : '' ) ) ) { continue; }
			$ds[] = array( 'ten' => $ten, 'tinh' => (string) ( isset( $c['tinh'] ) ? $c['tinh'] : '' ),
				'maKh' => '', 'nguon' => 'chiphi' );
		}
		return $ds;
	}

	/**
	 * GỘP TÊN HAI NGUỒN — CHỈ DÙNG CHO CÂU HỎI "TÊN NÀY CÓ PHẢI MỘT CƠ SỞ KHÔNG".
	 *
	 * ⚠️ ĐỪNG DÙNG CHO BẤT CỨ MÀN NÀO DÍNH TỚI TIỀN. Hàm này khử trùng theo tên, nên "AEON MALL
	 *    TÂN PHÚ" của ghế và của khu vui chơi gộp làm một dòng — mất đường khai mã cho một bên
	 *    (anh Thắng 16/09/2026). Màn nào cần từng sổ riêng thì gọi `ds_coso_ghe()` /
	 *    `chiphi_ds_coso()`, xem khối 🔴 ở `coso_ma_map_kvc()`.
	 *
	 * Trước bản này, bảy chỗ trong tệp gọi thẳng `ghe_ds_coso()`. Thêm một nguồn mà chỉ vá vài
	 * chỗ là KVC hiện ở màn này, vắng ở màn kia — đúng bài học 0.18.1 (§6): luật đúng, một bản
	 * sao không được vá, bộ thử vẫn xanh, màn hình vẫn sai. Nay cả bảy đi qua đây, và
	 * `ghe_ds_coso()` chỉ còn ĐÚNG MỘT chỗ gọi: ngay bên dưới.
	 */
	private static function ds_coso_all() {
		static $ds = null; if ( null !== $ds ) { return $ds; }
		$ds = array(); $thay = array();
		foreach ( self::ghe_ds_coso() as $c ) {
			$k = self::chuan_ch( $c['ten'] ); if ( '' === $k || isset( $thay[ $k ] ) ) { continue; }
			$thay[ $k ] = 1; $c['nguon'] = 'ghe'; $ds[] = $c;
		}
		foreach ( self::chiphi_ds_coso() as $c ) {
			$k = self::chuan_ch( $c['ten'] ); if ( '' === $k || isset( $thay[ $k ] ) ) { continue; }
			$thay[ $k ] = 1; $ds[] = $c;
		}
		usort( $ds, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		return $ds;
	}

	/** Có nguồn cơ sở nào không — Ghế HOẶC Chi Phí. */
	private static function coso_co() { return self::ghe_co() || self::chiphi_co(); }

	/* Bản đồ tên máy -> địa điểm ghế. Khoá = chuan_ch(mã máy) và chuan_ch(tên khai). */
	private static function ghe_map_may() {
		if ( ! self::ghe_co() ) { return array(); }
		global $wpdb; static $map = null; if ( null !== $map ) { return $map; }
		$sql = 'SELECT m.ma, m.ten_khai, c.ten AS coso, c.ma_kh, c.tinh FROM ' . self::ghe_tbl( 'may' ) . ' m'
			. ' LEFT JOIN ' . self::ghe_tbl( 'coso' ) . ' c ON c.id = m.coso_id WHERE m.coso_id > 0';
		$rows = $wpdb->get_results( $sql, ARRAY_A ); $map = array();
		foreach ( (array) $rows as $r ) {
			$coso = trim( (string) $r['coso'] ); if ( '' === $coso ) { continue; }
			$v = array( 'coso' => $coso, 'maKh' => (string) $r['ma_kh'], 'tinh' => (string) $r['tinh'], 'ma' => (string) $r['ma'] );
			foreach ( array( $r['ma'], $r['ten_khai'] ) as $nm ) { $k = self::chuan_ch( (string) $nm ); if ( '' !== $k && ! isset( $map[ $k ] ) ) { $map[ $k ] = $v; } }
		}
		return $map;
	}
	/* $ten có phải tên 1 địa điểm bên Ghế không (so bỏ dấu/khoảng trắng). */
	/**
	 * TÊN CHUẨN của một địa điểm Ghế từ tên hoặc BÍ DANH — '' nếu không phải địa điểm Ghế.
	 *
	 * 🔴 BÍ DANH (Ghế 2.137.0, `coso.bi_danh`) — anh Thắng 24/09/2026: tên cửa hàng bên cổng "POSH MN CGV
	 *    VINCOM LANDMARK" đang là một cơ sở rỗng bên Ghế, còn điểm thật là "CGV LANDMARK 81". Gộp bên Ghế
	 *    xong, tên cũ nằm trong bí danh của đích; ở đây tra bí danh → trả TÊN ĐÍCH, để tiền VietQR mang tên
	 *    cũ quy về đúng cơ sở mới thay vì rơi thành "không khớp". Tên thật thắng bí danh khi trùng.
	 */
	private static function ghe_coso_chuan( $ten ) {
		if ( ! self::ghe_co() || '' === trim( (string) $ten ) ) { return ''; }
		static $map = null;
		if ( null === $map ) {
			/* TÊN THẬT lấy từ ds_coso_all() — danh sách gộp Ghế + Chi Phí đã khử trùng theo tên, dùng
			   chung cho mọi câu "tên này có phải cơ sở không" (xem chú thích KVC ở trên). Không dựng lại
			   phép gộp ấy ở đây: hai bản của một luật thì sớm muộn lệch nhau. */
			$map = array(); $bd = array();
			foreach ( self::ds_coso_all() as $c ) {
				$k = self::chuan_ch( $c['ten'] ); if ( '' !== $k && ! isset( $map[ $k ] ) ) { $map[ $k ] = (string) $c['ten']; }
			}
			/* BÍ DANH chỉ có ở cơ sở Ghế — overlay lên, và không bao giờ đè tên thật. */
			foreach ( self::ghe_ds_coso() as $c ) {
				foreach ( preg_split( '/[\r\n;|]+/', (string) ( isset( $c['biDanh'] ) ? $c['biDanh'] : '' ) ) as $b ) {
					$kb = self::chuan_ch( $b ); if ( '' !== $kb && ! isset( $bd[ $kb ] ) ) { $bd[ $kb ] = (string) $c['ten']; }
				}
			}
			foreach ( $bd as $kb => $t ) { if ( ! isset( $map[ $kb ] ) ) { $map[ $kb ] = $t; } }   // tên thật thắng bí danh
		}
		$k = self::chuan_ch( $ten );
		if ( isset( $map[ $k ] ) ) { return $map[ $k ]; }
		/* Khoá LỎNG làm đường lui: tên ánh xạ hay mang đuôi tỉnh "GO BẾN TRE — Bến Tre" hoặc phần
		   trong ngoặc; bỏ chúng rồi so lại. Khoá khít vẫn thắng — đây chỉ chạy khi khít đã hụt. */
		$kl = self::chuan_ch_long( $ten );
		if ( '' !== $kl && isset( $map[ $kl ] ) ) { return $map[ $kl ]; }
		/* 0.51.0: bỏ chữ hiệu POSH đầu/cuối rồi so lại — điểm bán ở cổng "POSH Aeon Mall Huế", Ghế khai "AEON MALL HUẾ". */
		$kh = self::chuan_ch( self::bo_hieu_( $ten ) );
		if ( '' !== $kh && isset( $map[ $kh ] ) ) { return $map[ $kh ]; }
		$khl = self::chuan_ch_long( self::bo_hieu_( $ten ) );
		return ( '' !== $khl && isset( $map[ $khl ] ) ) ? $map[ $khl ] : '';
	}
	/**
	 * CƠ SỞ GHẾ CỦA MỘT DÒNG CỔNG — một chỗ quyết định, ba màn cùng gọi (hai báo cáo VietQR cho Ghế
	 * và bảng Sao Kê cổng). Trả ['coso','nguon','xungDot','coSoKhac'].
	 *
	 * 🔴 ĐỐI CHIẾU HAI NHÂN CHỨNG — anh Thắng 24/09/2026: *"bóc sai địa điểm mã cửa hàng của anh rồi"*:
	 *    dòng "GO AC 03" bị quy về GO TRƯỜNG CHINH, trong khi danh sách cửa hàng của cổng nói mã
	 *    VCD7HWKFAM = "GO ÂU CƠ 03". Trước đây suy cơ sở chỉ đi từ TÊN MÁY TRONG NỘI DUNG (rồi ánh xạ
	 *    tay), MÃ CỬA HÀNG không được hỏi lại lần nào. Nay hỏi cả hai:
	 *      · nhân chứng 1 — nội dung: tên máy → ghế (ghe-may) → cơ sở; hụt thì ánh xạ tay (anh-xa).
	 *      · nhân chứng 2 — mã cửa hàng: mã → tên cửa hàng bên cổng → ghế / tên cơ sở (ma-ch).
	 *    Hai bên KHÁC NHAU thì không im: cờ `xungDot`, kể tên bên thua. Bên thắng theo độ chắc:
	 *      ghe-may (tên máy khớp thẳng một ghế) > ma-ch (sổ đăng ký của cổng, khớp tên cơ sở) >
	 *      anh-xa (một dòng người gõ tay, có thể đã sai từ đầu — đúng ca GO AC 03).
	 *    Gán máy tay (`may_tay`) là quyết định của người, không bị mã cửa hàng đè.
	 */
	private static function cong_coso_dong( $ten_may, $ma_ch = '', $ax = null, $may_tay = '' ) {
		$ra = array( 'coso' => '', 'nguon' => '', 'xungDot' => 0, 'coSoKhac' => '' );
		$g = self::ghe_coso_cua_may( $ten_may );
		if ( $g ) { $ra['coso'] = (string) $g['coso']; $ra['nguon'] = 'ghe-may'; }
		elseif ( $ax && '' !== trim( (string) $ax['tenChuan'] ) ) {
			$c = self::ghe_coso_chuan( $ax['tenChuan'] );
			if ( '' !== $c ) { $ra['coso'] = $c; $ra['nguon'] = 'anh-xa'; }
		}
		if ( '' !== trim( (string) $may_tay ) ) { if ( '' !== $ra['coso'] ) { $ra['nguon'] = 'tay'; } return $ra; }
		$csCH = '';
		$tenCH = self::vqr_may_theo_ma( $ma_ch );
		if ( '' !== $tenCH ) {
			$g2 = self::ghe_coso_cua_may( $tenCH );
			$csCH = $g2 ? (string) $g2['coso'] : self::ghe_coso_chuan( self::cong_coso( $tenCH ) );
		}
		/* 0.51.0 — nhân chứng 2b: TÊN ĐIỂM BÁN của mã cửa hàng (cấp cơ sở của cổng) khi tên cửa hàng không ra ghế/cơ sở. */
		if ( '' === $csCH ) { $td = self::vqr_diem_theo_ma_( $ma_ch ); if ( '' !== $td ) { $csCH = self::ghe_coso_chuan( $td ); } }
		if ( '' === $ra['coso'] ) { if ( '' !== $csCH ) { $ra['coso'] = $csCH; $ra['nguon'] = 'ma-ch'; } return $ra; }
		if ( '' === $csCH || self::chuan_ch( $csCH ) === self::chuan_ch( $ra['coso'] ) ) { return $ra; }
		$ra['xungDot'] = 1;
		if ( 'anh-xa' === $ra['nguon'] ) { $ra['coSoKhac'] = $ra['coso']; $ra['coso'] = $csCH; $ra['nguon'] = 'ma-ch'; }
		else { $ra['coSoKhac'] = $csCH; }
		return $ra;
	}
	/* $ten có phải tên (hoặc bí danh) 1 địa điểm bên Ghế không. */
	private static function ghe_la_coso( $ten ) { return '' !== self::ghe_coso_chuan( $ten ); }
	/* Mã nộp tiền RIÊNG theo cơ sở (do người dùng chỉnh) — dùng để lọc sao kê ngân hàng xem cơ sở
	   đó đã nộp tiền mặt chưa. Lưu option saoke_coso_ma: [ chuan_ch(tên cơ sở) => mã ]. */
	private static function coso_ma_map() { $o = get_option( 'saoke_coso_ma' ); return is_array( $o ) ? $o : array(); }

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 KVC CÓ SỔ MÃ RIÊNG — VÌ TÊN CƠ SỞ TRÙNG NHAU LÀ CHUYỆN BÌNH THƯỜNG.
	 *
	 * Anh Thắng 16/09/2026: *"cơ sở trùng tên không thể thêm bên ngoài được"*.
	 *
	 * Khu vui chơi và ghế massage cùng nằm trong MỘT trung tâm thương mại: "AEON MALL TÂN PHÚ" là
	 * tên của cả hai. Nhưng đó là HAI sổ tiền khác nhau, hai mã nộp khác nhau (`KH705MTD…` với
	 * `KH705KVC…` — xem RE_MA_NOP). Dồn chung một bảng, khoá theo `chuan_ch(tên)`, là cái nào gõ
	 * sau ĐÈ cái trước và một trong hai bên vĩnh viễn không khai được mã.
	 *
	 * 0.35.0 còn khử trùng theo tên khi gộp hai nguồn — đúng cho việc "đừng đếm tiền hai lần của
	 * CÙNG một nơi", nhưng sai ở đây: hai nơi khác hẳn nhau tình cờ trùng tên. Nay hai danh sách
	 * và hai sổ mã đi riêng; chỗ nào chỉ cần "tên này có phải cơ sở không" thì mới gộp.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function coso_ma_map_kvc() { $o = get_option( 'saoke_coso_ma_kvc' ); return is_array( $o ) ? $o : array(); }
	private static function coso_ma( $coso ) { $m = self::coso_ma_map(); $k = self::chuan_ch( $coso ); return isset( $m[ $k ] ) ? (string) $m[ $k ] : ''; }
	/**
	 * VietQR THỰC theo CƠ SỞ × NGÀY — cho báo cáo bên Ghế gọi sang.
	 *
	 * 🔴 DÙNG CHÍNH luật gán của Sao Kê: cong_may_dong() (noi_dung + MÃ CỬA HÀNG `ma_ch` + bản đồ
	 *    cửa hàng + gán máy tay) rồi ghe_coso_cua_may() để ra cơ sở của Ghế. Trước đây bên Ghế tự
	 *    dò lại chỉ bằng noi_dung/diem_ban nên BỎ SÓT dòng "PaymentForOrder" (không có tên máy
	 *    trong nội dung, chỉ gán được nhờ `ma_ch`) — số VietQR trên báo cáo Ghế thiếu so với màn
	 *    Sao Kê (anh Thắng 14/09/2026: AEON Bình Dương 01/09). Bài học §6: một luật, một chỗ.
	 *
	 * @param string $tu  'Y-m-d'
	 * @param string $den 'Y-m-d'
	 * @return array [ 'co'=>bool, 'vq'=>[ tênCơSởGhế => [ 'Y-m-d' => tiền ] ], 'khongKhop'=>int ]
	 */
	public static function vietqr_theo_coso_ngay( $tu, $den ) {
		/* 0.50.0: SUY từ bản theo máy — trước đây là một vòng lặp thứ hai chép cùng luật gán; hai bản của
		   một luật thì sớm muộn lệch nhau (§6). vq[cs][ngày] = mọi máy của cs + phần "chưa rõ máy" của cs. */
		$m = self::vietqr_theo_may_ngay( $tu, $den );
		if ( empty( $m['co'] ) ) { return array( 'co' => false, 'vq' => array(), 'khongKhop' => 0 ); }
		$vq = array();
		foreach ( (array) $m['vq'] as $cs => $theoMay ) {
			foreach ( (array) $theoMay as $ma => $theoNgay ) {
				foreach ( (array) $theoNgay as $ng => $tien ) {
					if ( ! isset( $vq[ $cs ] ) ) { $vq[ $cs ] = array(); }
					$vq[ $cs ][ $ng ] = ( isset( $vq[ $cs ][ $ng ] ) ? $vq[ $cs ][ $ng ] : 0 ) + (int) $tien;
				}
			}
		}
		foreach ( (array) $m['chuaMay'] as $cs => $theoNgay ) {
			foreach ( (array) $theoNgay as $ng => $tien ) {
				if ( ! isset( $vq[ $cs ] ) ) { $vq[ $cs ] = array(); }
				$vq[ $cs ][ $ng ] = ( isset( $vq[ $cs ][ $ng ] ) ? $vq[ $cs ][ $ng ] : 0 ) + (int) $tien;
			}
		}
		return array( 'co' => true, 'vq' => $vq, 'khongKhop' => (int) $m['khongKhop'] );
	}

	/* Khoá so TÊN MÁY với TÊN GHẾ: chuan_ch() rồi bỏ số 0 dẫn đầu của cụm số CUỐI — cổng đánh
	   "LM-NSG 01" / "AMTP 02", bên Ghế khai "LM-NSG-1" / "AMTP-2"; chuan_ch() ra `lmnsg01` với
	   `lmnsg1`, không khớp, dù ai nhìn cũng thấy là một máy. Chỉ đụng cụm số cuối: "GO 02 HCM"
	   giữ nguyên số giữa. */
	public static function chuan_may( $s ) {
		return preg_replace( '/0*([0-9]+)$/', '$1', self::chuan_ch( self::bo_duoi_hieu_( $s ) ) );   // 0.51.0: "AEHP 01 Posh" ≡ "AEHP-1"
	}
	/* Bản đồ tên máy -> MỘT GHẾ CỤ THỂ: chuan_may(mã) và chuan_may(tên khai) -> ['ma','coso'].
	   Hai ghế ra cùng khoá (trùng mã/tên giữa hai cơ sở) -> đánh 'trung', KHÔNG đoán bừa. */
	private static function ghe_map_may_ma() {
		if ( ! self::ghe_co() ) { return array(); }
		global $wpdb; static $map = null; if ( null !== $map ) { return $map; }
		$rows = $wpdb->get_results( 'SELECT m.ma, m.ten_khai, c.ten AS coso FROM ' . self::ghe_tbl( 'may' ) . ' m'
			. ' LEFT JOIN ' . self::ghe_tbl( 'coso' ) . ' c ON c.id = m.coso_id WHERE m.coso_id > 0', ARRAY_A );
		$map = array();
		foreach ( (array) $rows as $r ) {
			$coso = trim( (string) $r['coso'] ); if ( '' === $coso ) { continue; }
			foreach ( array( $r['ma'], $r['ten_khai'] ) as $nm ) {
				$k = self::chuan_may( (string) $nm ); if ( '' === $k ) { continue; }
				if ( isset( $map[ $k ] ) ) { if ( $map[ $k ]['ma'] !== (string) $r['ma'] ) { $map[ $k ]['trung'] = true; } continue; }
				$map[ $k ] = array( 'ma' => (string) $r['ma'], 'coso' => $coso, 'trung' => false );
			}
		}
		return $map;
	}
	/**
	 * VietQR THỰC theo TỪNG MÁY × NGÀY — anh Thắng 23/09/2026: *"trong VietQR cũng có đánh từng
	 * máy 1,2,3,4… trên ghế cũng đánh 1,2,3,4… hai bên đối chiếu lại máy nào lệch không"*.
	 *
	 * 🔴 CÙNG LUẬT GÁN VỚI vietqr_theo_coso_ngay() — cong_may_dong() ra TÊN MÁY ("AMTP 12") rồi
	 *    mới quy ra ghế/cơ sở. Không tự dò lại từ noi_dung: một luật, một chỗ (§6).
	 * 🔴 BA RỔ, KHÔNG ĐỒNG NÀO RƠI: `vq[cơ sở][mã ghế][ngày]` = quy được tới MÁY;
	 *    `chuaMay[cơ sở][ngày]` = quy được cơ sở nhưng KHÔNG ra máy (QR tĩnh PaymentForOrder, tên
	 *    máy không có số, hoặc số trùng hai ghế); `khongKhop` = không ra cả cơ sở. Bên Ghế in rổ
	 *    hai thành dòng riêng "(chưa rõ máy)" của cơ sở ấy — tiền về thật, không bảng nào được giấu.
	 *
	 * @return array [ 'co'=>bool, 'vq'=>[coso=>[ma=>[ymd=>tiền]]], 'chuaMay'=>[coso=>[ymd=>tiền]], 'khongKhop'=>int ]
	 */
	public static function vietqr_theo_may_ngay( $tu, $den ) {
		global $wpdb;
		$tc = self::tbl_cong();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tc ) ) !== $tc ) {
			return array( 'co' => false, 'vq' => array(), 'chuaMay' => array(), 'khongKhop' => 0 );
		}
		$anhXa = self::ds_anhxa( 'vietqr' );
		$mapMa = self::ghe_map_may_ma();
		$kt = self::khoang_thoi_diem_( $tu, $den );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT so_tien, DATE(thoi_diem) d, noi_dung, diem_ban, ma_ch, may_tay FROM $tc"
			. " WHERE nguon='vietqr' AND doc_duoc=1 AND huong<>%s AND thoi_diem >= %s AND thoi_diem < %s",
			'Đi', $kt[0], $kt[1] ), ARRAY_A );
		$vq = array(); $chua = array(); $khong = 0;
		foreach ( (array) $rows as $r ) {
			$q = self::vietqr_quy_dong_( $r, $anhXa, $mapMa );
			if ( $q ) { self::vietqr_gom_( $vq, $chua, $khong, $q ); }
		}
		return array( 'co' => true, 'vq' => $vq, 'chuaMay' => $chua, 'khongKhop' => $khong );
	}
	/* Quy MỘT dòng cổng ra [ngày, cơ sở Ghế, mã ghế, tiền] — LUẬT DUY NHẤT, dùng cho báo cáo theo khoảng
	   lẫn đẩy từng giao dịch sang kho Ghế lúc webhook về (0.50.0). null = tiền ≤ 0 (bỏ).
	   Máy: chỉ nhận khi tên máy trỏ ĐÚNG MỘT ghế của ĐÚNG cơ sở ấy — trỏ ghế cơ sở khác là dấu trùng mã
	   liên cơ sở → 'ma' rỗng (chưa rõ máy), không gán chéo. */
	public static function vietqr_quy_dong_( $r, $anhXa, $mapMa ) {
		$tien = (int) $r['so_tien']; if ( $tien <= 0 ) { return null; }
		$ng = (string) $r['d'];
		$tenMay = self::cong_may_dong( (string) $r['noi_dung'], (string) $r['ma_ch'], (string) $r['diem_ban'], (string) $r['may_tay'] );
		$ax = self::ax_theo_ngay( self::ax_cua_may_( $anhXa, $tenMay, (string) $r['ma_ch'] ), self::ymd2vn( $ng ) );
		$qd = self::cong_coso_dong( $tenMay, (string) $r['ma_ch'], $ax, (string) $r['may_tay'] );
		$cs = (string) $qd['coso']; $m = '';
		if ( '' !== $cs ) {
			$k = self::chuan_may( $tenMay );
			$m = ( '' !== $k && isset( $mapMa[ $k ] ) && empty( $mapMa[ $k ]['trung'] ) && $mapMa[ $k ]['coso'] === $cs ) ? (string) $mapMa[ $k ]['ma'] : '';
			if ( '' === $m ) { $m = self::ghe_may_theo_so_( $cs, $tenMay ); }   // 0.55.0: cùng cơ sở, cùng SỐ máy
		}
		return array( 'ng' => $ng, 'coso' => $cs, 'ma' => $m, 'tien' => $tien );
	}
	/* 🔴 CÙNG CƠ SỞ, CÙNG SỐ MÁY — anh Thắng 23/09/2026: *"trong VietQR cũng có đánh từng máy 1,2,3,4… trên ghế cũng đánh
	   1,2,3,4… hai bên đối chiếu lại"* và 25/09/2026 *"nó đang có 1 mã không tên"*: cổng đặt "GLX QT 01", Ghế khai "GA QT-1" —
	   chữ khác nhau nên khớp tên trượt, tiền rơi vào "(chưa rõ máy)" dù cơ sở đã đúng và số máy đã trùng. Khi cơ sở ĐÃ quy
	   được mà tên máy không ra ghế: lấy cụm số CUỐI của tên máy, tìm ghế của chính cơ sở ấy có tên khai / mã kết thúc bằng
	   đúng số ấy — đúng MỘT ghế thì nhận, hai ghế cùng số thì thôi (không đoán). Mã ghế thuần số (80814) không tính là số máy. */
	private static function ghe_map_so_may_() {
		static $map = null; if ( null !== $map ) { return $map; }
		$map = array();
		foreach ( self::ghe_map_may_ma() as $k => $v ) {
			if ( ! empty( $v['trung'] ) || ctype_digit( (string) $k ) || ! preg_match( '/([0-9]+)$/', (string) $k, $m ) ) { continue; }
			$cs = self::chuan_ch( $v['coso'] ); $so = (string) (int) $m[1]; $ma = (string) $v['ma'];
			if ( ! isset( $map[ $cs ][ $so ] ) ) { $map[ $cs ][ $so ] = $ma; }
			elseif ( $map[ $cs ][ $so ] !== $ma ) { $map[ $cs ][ $so ] = 'trung'; }
		}
		return $map;
	}
	private static function ghe_may_theo_so_( $coso, $ten_may ) {
		$k = self::chuan_may( $ten_may );
		if ( '' === $k || ctype_digit( $k ) || ! preg_match( '/([0-9]+)$/', $k, $m ) ) { return ''; }
		$map = self::ghe_map_so_may_(); $cs = self::chuan_ch( $coso ); $so = (string) (int) $m[1];
		return ( isset( $map[ $cs ][ $so ] ) && 'trung' !== $map[ $cs ][ $so ] ) ? $map[ $cs ][ $so ] : '';
	}
	/* Bỏ một dòng đã quy vào đúng MỘT trong ba rổ: vq[cs][ma][ng] · chuaMay[cs][ng] · khongKhop. */
	private static function vietqr_gom_( &$vq, &$chua, &$khong, $q ) {
		$cs = (string) $q['coso']; $ng = (string) $q['ng']; $tien = (int) $q['tien'];
		if ( '' === $cs ) { $khong += $tien; return; }
		if ( '' === (string) $q['ma'] ) {
			if ( ! isset( $chua[ $cs ] ) ) { $chua[ $cs ] = array(); }
			$chua[ $cs ][ $ng ] = ( isset( $chua[ $cs ][ $ng ] ) ? $chua[ $cs ][ $ng ] : 0 ) + $tien;
			return;
		}
		$m = (string) $q['ma'];
		if ( ! isset( $vq[ $cs ] ) ) { $vq[ $cs ] = array(); }
		if ( ! isset( $vq[ $cs ][ $m ] ) ) { $vq[ $cs ][ $m ] = array(); }
		$vq[ $cs ][ $m ][ $ng ] = ( isset( $vq[ $cs ][ $m ][ $ng ] ) ? $vq[ $cs ][ $m ][ $ng ] : 0 ) + $tien;
	}

	/* ═══════════════ 0.50.0: ĐẨY SỐ VIETQR SANG KHO CỦA GHẾ (bảng vhg_bc_vqr) ═══════════════
	 * Anh Thắng 25/09/2026: *"khi có dữ liệu thêm thì ghi vào máy, để cần đọc ngay, chứ sao kê nó đang quá
	 * tải mà cứ gọi qua là lúc được lúc không"*. Trước đây Báo cáo tổng bên Ghế gọi sang vietqr_theo_coso_ngay()
	 * tính lại từ đầu MỖI LẦN bấm Xem — 25 ngày × hàng chục nghìn dòng là quá 30s. Nay:
	 *   · webhook về → cộng ngay giao dịch ấy vào kho Ghế (VHG_VietQR::cong_gd — một câu UPSERT);
	 *   · gán máy tay / nạp file / đổi bản đồ cửa hàng / đổi ánh xạ → tính lại NGÀY bị đụng, ghi đè (nhan_ngay).
	 * Ghế đọc kho, không gọi sang. Kho là số SUY RA từ saoke_cong (nguồn thật vẫn ở đây) — ghi đè kho không
	 * mất tiền. Mọi lỗi bên Ghế nuốt tại chỗ + error_log: webhook của cổng phải luôn được trả lời. */
	private static function ghe_kho_co_() { return class_exists( 'VHG_VietQR' ) && method_exists( 'VHG_VietQR', 'nhan_ngay' ); }
	/* Một giao dịch MỚI vừa ghi (webhook) → cộng thẳng vào kho Ghế. */
	private static function day_ghe_dong_( $tx ) {
		if ( ! self::ghe_kho_co_() ) { return; }
		try {
			if ( 'vietqr' !== (string) $tx['nguon'] || empty( $tx['docDuoc'] ) || 'Đi' === (string) $tx['huong'] ) { return; }
			$ngay = substr( (string) self::cong_ngay_mysql( $tx['thoiDiem'] ), 0, 10 );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { return; }
			$r = array( 'so_tien' => (int) round( $tx['soTien'] ), 'd' => $ngay, 'noi_dung' => (string) $tx['noiDung'],
				'diem_ban' => (string) ( isset( $tx['diemBan'] ) ? $tx['diemBan'] : '' ), 'ma_ch' => (string) ( isset( $tx['maCH'] ) ? $tx['maCH'] : '' ), 'may_tay' => '' );
			$q = self::vietqr_quy_dong_( $r, self::ds_anhxa( 'vietqr' ), self::ghe_map_may_ma() );
			if ( $q ) { VHG_VietQR::cong_gd( $q['ng'], $q['coso'], $q['ma'], $q['tien'] ); }
		} catch ( \Throwable $e ) { error_log( 'saoke→ghe day_ghe_dong_: ' . $e->getMessage() ); }
	}
	/* Tính lại trọn MỘT ngày rồi ghi đè kho Ghế (gán tay, nạp file, đổi bản đồ / ánh xạ). */
	public static function day_ghe_ngay_( $ngay ) {
		if ( ! self::ghe_kho_co_() ) { return false; }
		$ngay = substr( (string) $ngay, 0, 10 );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { return false; }
		try { VHG_VietQR::nhan_ngay( $ngay, self::vietqr_theo_may_ngay( $ngay, $ngay ) ); return true; }
		catch ( \Throwable $e ) { error_log( 'saoke→ghe day_ghe_ngay_: ' . $e->getMessage() ); return false; }
	}
	/* Các lượt NẠP FILE đi qua nhiều ngày: ghi dấu từng ngày đụng tới rồi đẩy MỘT lần cuối lượt. */
	private static $ngay_dung_ = array();
	private static function ghe_dau_ngay_( $mysql ) { if ( $mysql && strlen( (string) $mysql ) >= 10 ) { self::$ngay_dung_[ substr( (string) $mysql, 0, 10 ) ] = 1; } }
	/* 0.53.0 — anh Thắng *"chậm quá"*: nạp file / đổi bản đồ / đổi ánh xạ KHÔNG tính lại ngay nữa mà ĐÁNH DẤU ngày cũ bên
	   kho Ghế (VHG_VietQR::quen_ngay — một câu xoá/ngày); Ghế tự kéo lại khi có người bấm Xem. Tính lại tại chỗ từng
	   ngày (~1.000 giao dịch, hàng trăm câu ghi) sau MỖI ĐỢT 400 dòng chính là thứ làm nạp bù 24.261 dòng lê thê.
	   Ghế cũ chưa có quen_ngay → lùi về tính lại như trước. */
	private static function day_ghe_danh_dau_( $ds ) {
		if ( ! self::ghe_kho_co_() ) { return; }
		$ds = array_values( array_unique( array_filter( (array) $ds ) ) ); if ( ! $ds ) { return; }
		if ( method_exists( 'VHG_VietQR', 'quen_ngay' ) ) {
			try { VHG_VietQR::quen_ngay( $ds ); } catch ( \Throwable $e ) { error_log( 'saoke→ghe quen_ngay: ' . $e->getMessage() ); }
			return;
		}
		foreach ( $ds as $n ) { self::day_ghe_ngay_( $n ); }
	}
	private static function day_ghe_ngay_don_() { $ds = array_keys( self::$ngay_dung_ ); self::$ngay_dung_ = array(); self::day_ghe_danh_dau_( $ds ); }
	private static function day_ghe_gan_day_( $so_ngay ) {
		if ( ! self::ghe_kho_co_() ) { return; }
		$t = strtotime( current_time( 'Y-m-d' ) ); $ds = array();
		for ( $i = 0; $i < (int) $so_ngay; $i++ ) { $ds[] = gmdate( 'Y-m-d', $t - $i * 86400 ); }
		self::day_ghe_danh_dau_( $ds );
	}

	/* Địa điểm ghế của 1 tên máy VietQR ("AMTP 02"): khớp máy trước, rồi thử cơ sở (bỏ số). null nếu chưa có. */
	private static function ghe_coso_cua_may( $ten_may ) {
		if ( '' === trim( (string) $ten_may ) ) { return null; }
		$ten_may = self::bo_duoi_hieu_( $ten_may );   // 0.51.0
		/* 🔴 KHOÁ MÁY THA SỐ 0 ĐỆM ĐI TRƯỚC — anh Thắng 24/09/2026, GO BẾN TRE: cổng ghi "GO BT 08",
		   ghế khai "GO-BT-8". chuan_ch() ra `gobt08` ≠ `gobt8` → không ra ghế → lùi về tên ánh xạ
		   "GO BẾN TRE — Bến Tre" (có đuôi tỉnh) → cũng không ra cơ sở → bên Ghế báo "VietQR –" trong
		   khi bảng Sao Kê vẫn HIỆN tên nên trông như đã khớp. ghe_map_may_ma() (0.44.0) đã có khoá
		   chuan_may() đúng cho việc này; dùng nó ở đây, không chỉ ở báo cáo theo máy. Khoá trùng
		   hai ghế thì bỏ qua, rơi xuống các đường cũ — không đoán bừa. */
		$km = self::chuan_may( $ten_may );
		if ( '' !== $km ) {
			$mm = self::ghe_map_may_ma();
			if ( isset( $mm[ $km ] ) && empty( $mm[ $km ]['trung'] ) ) { return array( 'coso' => (string) $mm[ $km ]['coso'], 'ma' => (string) $mm[ $km ]['ma'], 'maKh' => '', 'tinh' => '' ); }
		}
		$map = self::ghe_map_may();
		$k = self::chuan_ch( $ten_may );
		if ( isset( $map[ $k ] ) ) { return $map[ $k ]; }
		$kc = self::chuan_ch( self::cong_coso( $ten_may ) );
		return isset( $map[ $kc ] ) ? $map[ $kc ] : null;
	}
	/* map chuan_ch(tên điểm) -> ['ma'=>..,'trung'=>bool] để suy mã từ tên chuẩn. */
	private static function map_ten_diem() {
		$map = array(); $ds = self::ds_diem();
		foreach ( $ds as $d ) {
			$ten = isset( $d['ten'] ) ? $d['ten'] : ''; $ma = isset( $d['ma'] ) ? $d['ma'] : '';
			$k = self::chuan_ch( $ten );
			if ( '' === $k || '' === $ma ) { continue; }
			if ( isset( $map[ $k ] ) ) { if ( $map[ $k ]['ma'] !== $ma ) { $map[ $k ]['trung'] = true; } continue; }
			$map[ $k ] = array( 'ma' => $ma, 'trung' => false );
		}
		/* Lượt 2: thêm KHOÁ LỎNG (bỏ ngoặc + đuôi) làm đường lui — KHÔNG đè khoá exact đã có; nếu hai
		   điểm ra cùng khoá lỏng với mã khác nhau thì đánh 'trung' để BUỘC gán tay (không đoán bừa). */
		$loose = array();
		foreach ( $ds as $d ) {
			$ten = isset( $d['ten'] ) ? $d['ten'] : ''; $ma = isset( $d['ma'] ) ? $d['ma'] : '';
			$kl = self::chuan_ch_long( $ten );
			if ( '' === $kl || '' === $ma || isset( $map[ $kl ] ) ) { continue; }
			if ( ! isset( $loose[ $kl ] ) ) { $loose[ $kl ] = array( 'ma' => $ma, 'trung' => false ); }
			elseif ( $loose[ $kl ]['ma'] !== $ma ) { $loose[ $kl ]['trung'] = true; }
		}
		foreach ( $loose as $kl => $v ) { if ( ! isset( $map[ $kl ] ) ) { $map[ $kl ] = $v; } }

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 TRA THÊM HAI SỔ MÃ Ở TAB CẤU HÌNH (0.37.0).
		 *
		 * Anh Thắng 16/09/2026: *"trong cấu hình đã gán cơ sở theo mã nộp tiền rồi, thì chọn bên
		 * momo cơ sở là nó tự chuyển qua chứ"*. Đúng — và tới 0.36.0 thì KHÔNG.
		 *
		 * Hàm này chỉ tra `ds_diem()` (danh sách điểm nộp). Hai sổ "Mã nộp tiền theo cơ sở" mà kế
		 * toán gõ ở tab Cấu hình — `saoke_coso_ma` (Ghế) và `saoke_coso_ma_kvc` (Khu vui chơi) —
		 * nằm ngoài. Nên khai xong `FARM PHAN THIẾT = KH705KVCMN0002` mà màn cổng vẫn báo *tên
		 * "FARM PHAN THIẾT" không có trong danh sách điểm*: người dùng đã làm đúng việc được yêu
		 * cầu, hệ thống vẫn nói chưa làm. Loại lỗi làm người ta mất niềm tin vào cả màn hình.
		 *
		 * ⚠️ XẾP SAU khoá exact của `ds_diem()`, TRƯỚC khoá lỏng — khớp đúng tên ở sổ mã là bằng
		 *    chứng mạnh hơn khớp lỏng (bỏ ngoặc/đuôi) ở danh sách điểm.
		 * ⚠️ TRÙNG TÊN MÀ KHÁC MÃ THÌ ĐÁNH `trung`, KHÔNG ĐOÁN BỪA. Một cái tên có mặt ở cả danh
		 *    sách điểm lẫn sổ KVC với hai mã khác nhau là hai nơi thu tiền khác nhau — chọn đại
		 *    một bên là tiền chạy sang sổ của người khác mà không có gì báo. Buộc điền Mã bank,
		 *    đúng như luật sẵn có cho ca "nhiều điểm dùng chung".
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		foreach ( self::so_ma_cau_hinh() as $so ) {
			foreach ( $so as $ten => $ma ) {
				$ma = trim( (string) $ma ); if ( '' === $ma ) { continue; }
				foreach ( array( self::chuan_ch( $ten ), self::chuan_ch_long( $ten ) ) as $kk ) {
					if ( '' === $kk ) { continue; }
					if ( ! isset( $map[ $kk ] ) ) { $map[ $kk ] = array( 'ma' => $ma, 'trung' => false ); }
					elseif ( $map[ $kk ]['ma'] !== $ma ) { $map[ $kk ]['trung'] = true; }
				}
			}
		}
		return $map;
	}

	/**
	 * HAI SỔ MÃ GÕ Ở TAB CẤU HÌNH, trả về [ [tên cơ sở => mã], … ].
	 *
	 * Sổ lưu khoá theo `chuan_ch(tên)` chứ không giữ tên gốc, nên phải đi ngược từ DANH SÁCH cơ
	 * sở của từng bên để lấy lại tên — cần tên gốc mới dựng được khoá lỏng.
	 */
	private static function so_ma_cau_hinh() {
		$ra = array();
		foreach ( array( array( self::ghe_ds_coso(), self::coso_ma_map() ),
		                 array( self::chiphi_ds_coso(), self::coso_ma_map_kvc() ) ) as $cap ) {
			$so = array();
			foreach ( $cap[0] as $c ) {
				$k = self::chuan_ch( $c['ten'] );
				if ( isset( $cap[1][ $k ] ) && '' !== trim( (string) $cap[1][ $k ] ) ) { $so[ $c['ten'] ] = $cap[1][ $k ]; }
			}
			if ( $so ) { $ra[] = $so; }
		}
		return $ra;
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
				update_option( 'saoke_cong_log_sheet', esc_url_raw( wp_unslash( $_POST['conglog'] ) ) );
				update_option( 'saoke_cong_log_auto', isset( $_POST['conglog_auto'] ) ? '1' : '0' );
				update_option( 'saoke_cong_log_freq', '1' === (string) ( isset( $_POST['conglog_freq'] ) ? $_POST['conglog_freq'] : '5' ) ? '1' : '5' );
				wp_clear_scheduled_hook( 'saoke_cron_conglog' );
				if ( isset( $_POST['conglog_auto'] ) && '' !== trim( (string) get_option( 'saoke_cong_log_sheet', '' ) ) ) { wp_schedule_event( time() + 60, self::conglog_lich(), 'saoke_cron_conglog' ); }
				echo '<div class="notice notice-success"><p>Đã lưu.</p></div>';
			}
		}
		if ( isset( $_POST['saoke_keo_conglog'] ) && check_admin_referer( 'saoke_cfg' ) ) {
			update_option( 'saoke_cong_log_sheet', esc_url_raw( wp_unslash( $_POST['conglog'] ) ) );
			$r = self::keo_cong_log_sheet();
			if ( is_wp_error( $r ) ) { echo '<div class="notice notice-error"><p>Kéo lỗi: ' . esc_html( $r->get_error_message() ) . '</p></div>'; }
			else { echo '<div class="notice notice-success"><p>Đã kéo cổng từ Nhật ký: <b>' . (int) $r['moi'] . '</b> mới, ' . (int) $r['trung'] . ' trùng' . ( ! empty( $r['kho'] ) ? ( ', ' . (int) $r['kho'] . ' không đọc được' ) : '' ) . '.</p></div>'; }
		}
		if ( isset( $_POST['saoke_luu_cosoma'] ) && check_admin_referer( 'saoke_cfg' ) ) {
			$in = isset( $_POST['cosoma'] ) && is_array( $_POST['cosoma'] ) ? wp_unslash( $_POST['cosoma'] ) : array();
			$map = array();
			foreach ( $in as $k => $v ) { $v = trim( sanitize_text_field( (string) $v ) ); $k = sanitize_key( (string) $k ); if ( '' !== $v && '' !== $k ) { $map[ $k ] = mb_substr( $v, 0, 60 ); } }
			update_option( 'saoke_coso_ma', $map );
			echo '<div class="notice notice-success"><p>Đã lưu mã nộp theo cơ sở (' . count( $map ) . ' cơ sở có mã).</p></div>';
		}
		if ( isset( $_POST['saoke_luu_vqr'] ) && check_admin_referer( 'saoke_cfg' ) ) {
			/* 0.49.0: NHIỀU tài khoản VietQR — mỗi dòng một cặp user/pass + số TK. Mật khẩu chỉ ghi đè khi
			   nhập mới (trống = giữ mật khẩu cũ của dòng đó); dòng tích Xoá thì bỏ. Dòng đầu tiên đồng thời
			   chép sang cặp cũ `saoke_vqr_user/pass` để chỗ nào còn đọc cặp cũ vẫn đúng. */
			$cu = array(); foreach ( self::vqr_tk_ds() as $t ) { $cu[ $t['id'] ] = $t; }
			$vao = isset( $_POST['vqr_tk'] ) && is_array( $_POST['vqr_tk'] ) ? wp_unslash( $_POST['vqr_tk'] ) : array();
			$moi = array(); $n = 0;
			foreach ( $vao as $r ) {
				if ( ! is_array( $r ) ) { continue; }
				$u = trim( sanitize_text_field( isset( $r['user'] ) ? $r['user'] : '' ) );
				if ( '' === $u || ! empty( $r['xoa'] ) ) { continue; }
				$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( isset( $r['id'] ) ? $r['id'] : '' ) );
				if ( '' === $id ) { $id = 'tk' . substr( md5( $u . microtime( true ) . ++$n ), 0, 8 ); }
				$p = (string) ( isset( $r['pass'] ) ? $r['pass'] : '' );
				if ( '' === trim( $p ) ) { $p = isset( $cu[ $id ] ) ? (string) $cu[ $id ]['pass'] : ''; }
				$moi[] = array( 'id' => $id, 'nhan' => trim( sanitize_text_field( isset( $r['nhan'] ) ? $r['nhan'] : '' ) ), 'user' => $u, 'pass' => $p,
					'so_tk' => preg_replace( '/\s+/', '', sanitize_text_field( isset( $r['so_tk'] ) ? $r['so_tk'] : '' ) ),
					'ngan_hang' => trim( sanitize_text_field( isset( $r['ngan_hang'] ) ? $r['ngan_hang'] : '' ) ) );
			}
			update_option( 'saoke_vqr_tk', $moi, false );
			if ( $moi ) { update_option( 'saoke_vqr_user', $moi[0]['user'] ); if ( '' !== $moi[0]['pass'] ) { update_option( 'saoke_vqr_pass', $moi[0]['pass'] ); } }
			else { update_option( 'saoke_vqr_user', '' ); update_option( 'saoke_vqr_pass', '' ); }
			$thieuPass = 0; foreach ( $moi as $t ) { if ( '' === $t['pass'] ) { $thieuPass++; } }
			echo '<div class="notice notice-success"><p>Đã lưu ' . count( $moi ) . ' tài khoản VietQR chính thức.' . ( $thieuPass ? ( ' ⚠ ' . $thieuPass . ' tài khoản CHƯA có mật khẩu — cổng sẽ không lấy được token cho tài khoản ấy.' ) : '' ) . '</p></div>';
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
		$clog = (string) get_option( 'saoke_cong_log_sheet', '' );
		$clast = (array) get_option( 'saoke_cong_log_last', array() );
		echo '<tr><th>VietQR tạm — link CSV Nhật ký (app cũ)</th><td><input name="conglog" class="regular-text code" value="' . esc_attr( $clog ) . '" placeholder="https://docs.google.com/.../pub?gid=...&single=true&output=csv" style="width:520px">'
			. ' <label style="margin-left:6px"><input type="checkbox" name="conglog_auto" ' . checked( '1', (string) get_option( 'saoke_cong_log_auto', '0' ), false ) . '> tự kéo</label>'
			. ' <select name="conglog_freq"><option value="5" ' . selected( '5', (string) get_option( 'saoke_cong_log_freq', '5' ), false ) . '>mỗi 5 phút</option><option value="1" ' . selected( '1', (string) get_option( 'saoke_cong_log_freq', '5' ), false ) . '>mỗi 1 phút</option></select>'
			. '<br><span class="description">Kéo giao dịch cổng từ sheet <b>Nhật ký</b> của app cũ (cột “Chi tiết” = payload thô Tingo) vào bảng cổng, đối soát với sao kê ngân hàng. Dùng tạm khi VietQR API chưa duyệt. Chống trùng theo mã GD nên kéo lại bao nhiêu lần cũng không cộng đôi.'
			. '<br><b>Lưu ý băng thông:</b> mỗi lần kéo tải lại TOÀN BỘ CSV (không chỉ dòng mới), nên “mỗi 1 phút” tốn băng thông ~5× so với 5 phút và tăng dần khi sheet to lên. Nên để 1 phút lúc cần bắt kịp, xong hạ về 5 phút. WP-Cron chỉ chạy khi trang có lượt truy cập — muốn đúng nhịp cần cron thật của host.'
			. ( $clast ? ( '<br><b>Lần kéo cuối:</b> ' . esc_html( ( isset( $clast['luc'] ) ? $clast['luc'] : '' ) . ' — ' . ( isset( $clast['kq'] ) ? $clast['kq'] : '' ) ) ) : '' )
			. '</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="saoke_luu" value="1">Lưu</button> <button class="button" name="saoke_keo_conglog" value="1">Kéo VietQR từ Nhật ký ngay</button></p></form>';
		// ── Mã nộp tiền theo cơ sở (POSH) — để lọc sao kê ngân hàng xem cơ sở đã nộp tiền mặt chưa ──
		if ( self::coso_co() ) {
			$cm = self::coso_ma_map(); $dscs = self::ghe_ds_coso();
			echo '<hr><h2>Mã nộp tiền theo cơ sở</h2>';
			echo '<p class="description">Mỗi cơ sở một mã (chuỗi nhân viên/kế toán ghi trong nội dung khi <b>nộp tiền mặt</b> vào ngân hàng). Dùng để dò trong Sao kê ngân hàng biết cơ sở đó <b>đã nộp tiền mặt chưa</b>. Sửa được, bỏ trống = chưa dùng. Danh sách cơ sở gộp từ trang Ghế và đơn vị ' . esc_html( self::dv_chi_phi() ) . ' bên Chi Phí (' . count( $dscs ) . ' cơ sở).</p>';
			echo '<form method="post">'; wp_nonce_field( 'saoke_cfg' );
			echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th style="width:45%">Cơ sở</th><th>Tỉnh</th><th>Mã nộp tiền</th></tr></thead><tbody>';
			foreach ( (array) $dscs as $c ) {
				$k = self::chuan_ch( $c['ten'] ); $v = isset( $cm[ $k ] ) ? (string) $cm[ $k ] : '';
				echo '<tr><td><b>' . esc_html( $c['ten'] ) . '</b></td><td class="description">' . esc_html( $c['tinh'] ) . '</td>'
					. '<td><input name="cosoma[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '" class="code" style="width:240px" placeholder="mã nộp (nếu có)"></td></tr>';
			}
			echo '</tbody></table><p><button class="button button-primary" name="saoke_luu_cosoma" value="1">Lưu mã theo cơ sở</button></p></form>';
		}
		// ── VietQR CHÍNH THỨC (API Service của cổng — token_generate + transaction-callback) ──
		$tokUrl  = esc_url_raw( rest_url( self::NS . '/vqr/api/token_generate' ) );
		$cbUrl   = esc_url_raw( rest_url( self::NS . '/vqr/bank/api/transaction-callback' ) );
		$cbTest  = esc_url_raw( rest_url( self::NS . '/vqr/bank/api/test/transaction-callback' ) );
		$dsTk = self::vqr_tk_ds();
		echo '<hr><h2>VietQR chính thức (API Service) — ' . count( $dsTk ) . ' tài khoản</h2>';
		echo '<p class="description">Cổng gọi <b>VÀO</b> server mình: trước tiên lấy token qua <b>Token URL</b> (Basic Auth bằng username/password dưới đây), sau đó bắn giao dịch về <b>Callback URL</b> kèm <code>Authorization: Bearer &lt;token&gt;</code>. Giao dịch nhận được lưu thẳng vào Sao kê ngân hàng (nguồn <code>vietqr</code>), chống trùng theo mã GD. Dùng cho môi trường UAT lẫn thật.</p>';
		echo '<form method="post">'; wp_nonce_field( 'saoke_cfg' );
		/* 0.49.0: mỗi dòng một tài khoản; dòng cuối để trống là dòng THÊM MỚI. Cổng gọi Token URL bằng
		   user/pass của tài khoản nào thì giao dịch về được gắn số TK của tài khoản ấy. */
		echo '<p class="description">Mỗi tài khoản VietQR (mỗi pháp nhân / mỗi tài khoản ngân hàng nhận tiền) một dòng. Khai <b>cùng Token URL và Callback URL</b> bên dưới cho mọi tài khoản ở cổng, chỉ khác <b>username/password</b>. Số TK nhận tiền dùng để tách giao dịch theo tài khoản khi payload cổng không kèm số TK.</p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:16%">Nhãn</th><th style="width:20%">Username (khai với cổng)</th><th style="width:20%">Password</th><th style="width:18%">Số TK nhận tiền</th><th style="width:16%">Ngân hàng</th><th>Xoá</th></tr></thead><tbody>';
		$i = 0;
		foreach ( array_merge( $dsTk, array( array( 'id' => '', 'nhan' => '', 'user' => '', 'pass' => '', 'so_tk' => '', 'ngan_hang' => '' ) ) ) as $t ) {
			$la_moi = ( '' === (string) $t['id'] );
			echo '<tr' . ( $la_moi ? ' style="background:#f6fff6"' : '' ) . '>'
				. '<td><input type="hidden" name="vqr_tk[' . $i . '][id]" value="' . esc_attr( $t['id'] ) . '"><input name="vqr_tk[' . $i . '][nhan]" value="' . esc_attr( $t['nhan'] ) . '" placeholder="' . ( $la_moi ? '+ tài khoản mới' : 'vd K&H 705' ) . '" style="width:100%"></td>'
				. '<td><input name="vqr_tk[' . $i . '][user]" class="code" value="' . esc_attr( $t['user'] ) . '" placeholder="username" style="width:100%"></td>'
				. '<td><input type="password" name="vqr_tk[' . $i . '][pass]" class="code" autocomplete="new-password" placeholder="' . ( '' !== $t['pass'] ? 'đã đặt — nhập để đổi' : 'chưa đặt' ) . '" style="width:100%"></td>'
				. '<td><input name="vqr_tk[' . $i . '][so_tk]" class="code" value="' . esc_attr( $t['so_tk'] ) . '" placeholder="số TK nhận" style="width:100%"></td>'
				. '<td><input name="vqr_tk[' . $i . '][ngan_hang]" value="' . esc_attr( $t['ngan_hang'] ) . '" placeholder="vd Vietcombank" style="width:100%"></td>'
				. '<td style="text-align:center">' . ( $la_moi ? '' : '<input type="checkbox" name="vqr_tk[' . $i . '][xoa]" value="1">' ) . '</td></tr>';
			$i++;
		}
		echo '</tbody></table><table class="form-table">';
		echo '<tr><th>Mật khẩu</th><td><span class="description">Bỏ trống = giữ nguyên. Không hiện lại ra màn hình.</span></td></tr>';
		echo '<tr><th>Token URL</th><td><code>' . esc_html( $tokUrl ) . '</code><br><span class="description">POST · Basic Auth = username/password ở trên · trả <code>access_token</code> (Bearer, hạn 12h).</span></td></tr>';
		echo '<tr><th>Callback URL (thật)</th><td><code>' . esc_html( $cbUrl ) . '</code></td></tr>';
		echo '<tr><th>Callback URL (test/UAT)</th><td><code>' . esc_html( $cbTest ) . '</code><br><span class="description">Dán URL này (hoặc URL thật) vào cổng rồi bấm <b>Test Callback</b>. Cùng một trình xử lý.</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="saoke_luu_vqr" value="1">Lưu VietQR chính thức</button></p></form>';
		echo '<p class="description">Sau khi Test Callback: mở trang Sao kê → mục <b>Nhật ký</b>/log <code>vietqr-official</code> để xem cổng đã gọi tới chưa và giao dịch đã vào <b>Sao kê ngân hàng</b> chưa.</p>';
		echo '<p class="description">⚠️ Repo công khai — PIN/khoá lưu trong DB, không nằm trong mã nguồn.</p></div>';
	}

	// ═══════════════════════════════════════════════════════════════════════
	//  CẦU RPC — port 1:1 các hàm Apps Script (Code.gs) sang PHP, đúng shape.
	//  Frontend gọi google.script.run.fn(pin, ...args) -> shim POST /rpc {fn,args}.
	// ═══════════════════════════════════════════════════════════════════════
	private static function pn_opts() { return array( 'K&H cũ (989)', 'K&H mới (705)', 'Smarttrade' ); }
	private static function loi( $msg ) { throw new Exception( $msg ); }
	/**
	 * 🔴 PIN HỢP LỆ = CÓ PHIÊN VÉ TỪ GHẾ *HOẶC* PIN GÕ ĐÚNG — một luật cho cả cầu RPC lẫn REST.
	 *
	 * Anh Thắng 25/09/2026, ảnh Tổng quan: *"Không tải được tổng quan: Sai mã PIN"* ngay sau khi vào
	 * bằng vé từ Ghế; *"khả năng sao kê chưa phân quyền"*. Đúng: `pin_ok()` (REST) đã nhận phiên vé từ
	 * 0.3x, nhưng `can_pin()` — cửa của CẢ 35 hàm RPC mà app.html thật sự gọi — chỉ so PIN trong
	 * args, mà đường vé thì PIN = '' (app không hề biết PIN). Trước 0.47.0 lỗi này bị che vì đường vé
	 * gãy script sớm hơn; sửa xong chỗ ấy thì chỗ này lộ ra. Nay ba cửa (can_pin, checkPin, getConfig)
	 * cùng hỏi một hàm này. Cookie phiên là httponly, JS không đọc được, nên "có phiên" không thể giả từ
	 * trình duyệt; PIN vẫn nguyên cho người gõ thẳng địa chỉ.
	 */
	private static function pin_hop_le_( $pin ) {
		if ( self::phien_ok_() ) { return true; }
		$luu = (string) get_option( 'saoke_pin', '' );
		return '' !== $luu && hash_equals( $luu, (string) $pin );
	}
	private static function can_pin( $args ) { $pin = isset( $args[0] ) ? (string) $args[0] : ''; if ( ! self::pin_hop_le_( $pin ) ) { self::loi( 'Sai mã PIN' ); } }
	/* "🔒 Khoá lại" trên app: huỷ luôn phiên vé (không thì tải lại trang là tự vào lại bằng vé). */
	public static function rpc_khoaPhien( $a ) {
		$sid = isset( $_COOKIE['saoke_ses'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $_COOKIE['saoke_ses'] ) : '';
		if ( 32 === strlen( $sid ) ) { delete_transient( 'saoke_ses_' . hash( 'sha256', $sid ) ); }
		if ( ! headers_sent() ) {
			if ( PHP_VERSION_ID >= 70300 ) { setcookie( 'saoke_ses', '', array( 'expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) ); }
			else { setcookie( 'saoke_ses', '', time() - 3600, '/; samesite=Lax', '', is_ssl(), true ); }
		}
		return array( 'ok' => true );
	}

	public static function r_rpc( $req ) {
		$fn   = (string) $req->get_param( 'fn' );
		$args = $req->get_param( 'args' ); if ( ! is_array( $args ) ) { $args = array(); }
		$map  = array(
			'checkPin', 'getConfig', 'saveCauHinh', 'doiPin', 'getDashboard', 'getGiaoDich', 'setNhan',
			'saveTaiKhoan', 'xoaTaiKhoan', 'saveDanhMuc', 'xoaDanhMuc', 'testWebhookSample', 'syncSepayHistory',
			'getDoiChieuNop', 'napLaiDanhSachDiem', 'getTongHopCoSo', 'getSaoKeCong', 'getDoiSoatFile',
			'napFileCong', 'napFileCongTx', 'luuAnhXaCuaHang', 'chuyenGianCuaHang', 'xoaAnhXaCuaHang', 'taoCoSoGhe',
			'xoaNgayFileCong', 'dsCuaHangChuan', 'luuTuKhoaCong', 'testWebhookCong', 'luuCotFileCong', 'luuCotTxCong',
			'getCosoMa', 'saveCosoMa',
			/* ⚠️ Hàm mới PHẢI khai vào danh sách này — quên là màn hình báo "Hàm không hợp lệ",
			   mà lỗi ấy chỉ lộ ra lúc bấm, không có gì đỏ lúc dựng. */
			'getCosoMaKvc', 'saveCosoMaKvc',
			'napDsCuaHangVqr', 'getDsCuaHangVqr', 'xoaDsCuaHangVqr',
			'getNopTienMat', 'ganMayTay',
			'khoaPhien',
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
	public static function rpc_checkPin( $a ) { $pin = isset( $a[0] ) ? (string) $a[0] : ''; return array( 'ok' => self::pin_hop_le_( $pin ) ); }
	public static function rpc_getConfig( $a ) {
		$pin = isset( $a[0] ) ? (string) $a[0] : '';
		$authed = self::pin_hop_le_( $pin );   // 0.48.0: nhận cả phiên vé từ Ghế — xem pin_hop_le_()
		$cfg = array( 'ok' => true, 'authed' => $authed, 'today' => gmdate( 'd/m/Y', current_time( 'timestamp' ) ) );
		if ( ! $authed ) { return $cfg; }
		$key = (string) get_option( 'saoke_webhook_key', '' );
		$base = esc_url_raw( rest_url( self::NS . '/webhook' ) );
		$cfg['taiKhoan'] = self::ds_tk();
		$cfg['danhMuc']  = self::ds_dm();
		$cfg['phapNhanOpts'] = self::pn_opts();
		// Cơ sở (ghế) — để nhãn phân loại giao dịch chọn được cơ sở doanh thu, không chỉ danh mục chi phí.
		$cfg['gheCoso'] = array(); foreach ( self::ds_coso_all() as $c ) { $cfg['gheCoso'][] = $c['ten']; }
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
		/* Từ khoá nhận dòng từng cổng — v0.27.0 dời ô sửa từ khoá + thử payload từ màn đối soát
		   sang tab Cấu hình (ẩn khỏi màn vận hành). Trả kèm để tab Cấu hình điền sẵn. */
		$cfg['tukhoaCong'] = self::tukhoa_cong_ra();
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
		// POSH: ưu tiên danh sách địa điểm bên trang Ghế (VietQR = doanh thu ghế theo địa điểm).
		if ( self::ghe_co() ) {
			foreach ( self::ds_coso_all() as $c ) { $ds[] = array( 'ma' => $c['ten'], 'ten' => $c['ten'] . ( '' !== $c['tinh'] ? ( ' — ' . $c['tinh'] ) : '' ), 'maDiem' => $c['maKh'] ); }
			if ( count( $ds ) ) { return array( 'ok' => true, 'ds' => $ds, 'nguon' => 'ghe' ); }
		}
		foreach ( self::ds_diem() as $d ) { $ds[] = array( 'ma' => $d['ma'], 'ten' => '' !== ( isset( $d['ten'] ) ? $d['ten'] : '' ) ? $d['ten'] : $d['ma'], 'maDiem' => isset( $d['maDiem'] ) ? $d['maDiem'] : '' ); }
		return array( 'ok' => true, 'ds' => $ds );
	}
	// ── Mã nộp tiền theo cơ sở (kế toán tự nhập ở tab Cấu hình) ──
	public static function rpc_getCosoMa( $a ) {
		self::can_pin( $a ); $cm = self::coso_ma_map(); $ds = array();
		foreach ( self::ghe_ds_coso() as $c ) { $k = self::chuan_ch( $c['ten'] ); $ds[] = array( 'coso' => $c['ten'], 'tinh' => $c['tinh'], 'key' => $k, 'ma' => isset( $cm[ $k ] ) ? (string) $cm[ $k ] : '' ); }
		return array( 'ok' => true, 'ds' => $ds, 'coGhe' => self::ghe_co() );
	}
	public static function rpc_saveCosoMa( $a ) {
		self::can_pin( $a ); $in = isset( $a[1] ) && is_array( $a[1] ) ? $a[1] : array(); $map = array();
		foreach ( $in as $k => $v ) { $k = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $k ) ); $v = trim( sanitize_text_field( (string) $v ) ); if ( '' !== $k && '' !== $v ) { $map[ $k ] = mb_substr( $v, 0, 60 ); } }
		update_option( 'saoke_coso_ma', $map );
		return array( 'ok' => true, 'so' => count( $map ) );
	}

	/* ── Mã nộp tiền theo cơ sở KHU VUI CHƠI — sổ RIÊNG, xem khối 🔴 ở coso_ma_map_kvc() ────── */
	public static function rpc_getCosoMaKvc( $a ) {
		self::can_pin( $a ); $cm = self::coso_ma_map_kvc(); $ds = array();
		foreach ( self::chiphi_ds_coso() as $c ) { $k = self::chuan_ch( $c['ten'] ); $ds[] = array( 'coso' => $c['ten'], 'tinh' => $c['tinh'], 'key' => $k, 'ma' => isset( $cm[ $k ] ) ? (string) $cm[ $k ] : '' ); }
		return array( 'ok' => true, 'ds' => $ds, 'coChiPhi' => self::chiphi_co(), 'donVi' => self::dv_chi_phi() );
	}
	public static function rpc_saveCosoMaKvc( $a ) {
		self::can_pin( $a ); $in = isset( $a[1] ) && is_array( $a[1] ) ? $a[1] : array(); $map = array();
		foreach ( $in as $k => $v ) { $k = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $k ) ); $v = trim( sanitize_text_field( (string) $v ) ); if ( '' !== $k && '' !== $v ) { $map[ $k ] = mb_substr( $v, 0, 60 ); } }
		update_option( 'saoke_coso_ma_kvc', $map );
		return array( 'ok' => true, 'so' => count( $map ) );
	}
	/* ── GÁN MÁY THỦ CÔNG cho MỘT giao dịch cổng ──────────────────────────────────────────────
	 * Anh Thắng 12/09/2026: *"một số giao dịch dò không ra, muốn gán thủ công thì sao"*. Giao dịch
	 * QR tĩnh (PaymentForOrder) không mang tên máy trong nội dung lẫn mã cửa hàng → không suy được;
	 * admin gõ tên máy tay, ghi vào cột `may_tay` của ĐÚNG dòng (khoá theo `khoa` duy nhất).
	 * `cong_may_dong()` đọc `may_tay` TRƯỚC mọi suy đoán nên dòng ra máy ngay, và tiền theo về đúng
	 * cơ sở ở phép gom. Gõ trống = BỎ gán (trở lại "chưa rõ máy"). args: [pin, khoa, tenMay].
	 */
	public static function rpc_ganMayTay( $a ) {
		self::can_pin( $a ); global $wpdb;
		$khoa = isset( $a[1] ) ? trim( (string) $a[1] ) : '';
		$ten  = isset( $a[2] ) ? trim( sanitize_text_field( (string) $a[2] ) ) : '';
		if ( '' === $khoa ) { self::loi( 'Thiếu khoá giao dịch.' ); }
		$ten = mb_substr( preg_replace( '/\s+/', ' ', $ten ), 0, 60 );
		$tc  = self::tbl_cong();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, DATE(thoi_diem) d FROM $tc WHERE khoa=%s", $khoa ), ARRAY_A );
		if ( ! $row ) { self::loi( 'Không thấy giao dịch (khoá không khớp).' ); }
		$wpdb->update( $tc, array( 'may_tay' => $ten ), array( 'id' => (int) $row['id'] ) );
		self::day_ghe_ngay_( (string) $row['d'] );   // gán tay đổi cơ sở/máy của giao dịch → kho Ghế ngày ấy tính lại
		return array( 'ok' => true, 'mayTay' => $ten );
	}
	// ── Nộp tiền mặt theo cơ sở: dò mã của cơ sở trong nội dung sao kê ngân hàng ──
	public static function rpc_getNopTienMat( $a ) {
		self::can_pin( $a ); global $wpdb;
		$tu = self::vn2ymd( isset( $a[1] ) ? (string) $a[1] : '' ); $den = self::vn2ymd( isset( $a[2] ) ? (string) $a[2] : '' );
		$tbl = self::tbl();
		$rows = array(); $soDaNop = 0; $soChuaNop = 0; $soChuaMa = 0; $tongNop = 0;
		/* 🔴 HAI SỔ, ĐI RIÊNG. Ghế và Khu vui chơi có thể CÙNG TÊN ("AEON MALL TÂN PHÚ") mà là hai
		   nơi thu tiền khác nhau, hai mã nộp khác nhau. Gộp một danh sách rồi tra một sổ là một
		   trong hai bên vĩnh viễn hiện "chưa đặt mã" — mà dòng đỏ không bao giờ xanh được là dòng
		   người ta thôi nhìn. Cột `he` cho màn hình phân biệt hai dòng trùng tên. */
		$nguon = array(
			array( 'he' => 'POSH', 'ds' => self::ghe_ds_coso(),    'cm' => self::coso_ma_map() ),
			array( 'he' => 'KVC',  'ds' => self::chiphi_ds_coso(), 'cm' => self::coso_ma_map_kvc() ),
		);
		foreach ( $nguon as $ng ) {
		$cm = $ng['cm'];
		foreach ( $ng['ds'] as $c ) {
			$ma = isset( $cm[ self::chuan_ch( $c['ten'] ) ] ) ? trim( (string) $cm[ self::chuan_ch( $c['ten'] ) ] ) : '';
			$o = array( 'coso' => $c['ten'], 'he' => $ng['he'], 'tinh' => $c['tinh'], 'ma' => $ma, 'coMa' => '' !== $ma, 'daNop' => false, 'tong' => 0, 'soLan' => 0, 'lanCuoi' => '' );
			if ( '' !== $ma ) {
				$w = array( "loai='in'", 'noi_dung LIKE %s' ); $ar = array( '%' . $wpdb->esc_like( $ma ) . '%' );
				if ( $tu )  { $w[] = 'DATE(ngay_gd)>=%s'; $ar[] = $tu; }
				if ( $den ) { $w[] = 'DATE(ngay_gd)<=%s'; $ar[] = $den; }
				$gd = $wpdb->get_results( $wpdb->prepare( "SELECT ngay_gd, tien FROM $tbl WHERE " . implode( ' AND ', $w ) . " ORDER BY ngay_gd DESC, id DESC LIMIT 500", $ar ), ARRAY_A );
				foreach ( (array) $gd as $g ) {
					$o['tong'] += (int) $g['tien']; $o['soLan']++;
					$vn = self::ymd2vn( $g['ngay_gd'] ); if ( '' === $o['lanCuoi'] || self::moc( $vn ) > self::moc( $o['lanCuoi'] ) ) { $o['lanCuoi'] = $vn; }
				}
				$o['daNop'] = $o['soLan'] > 0;
			}
			if ( '' === $ma ) { $soChuaMa++; } elseif ( $o['daNop'] ) { $soDaNop++; $tongNop += $o['tong']; } else { $soChuaNop++; }
			$rows[] = $o;
		}
		}
		// Chưa nộp (có mã) lên đầu để soi, rồi đã nộp, cuối là chưa đặt mã.
		usort( $rows, function ( $x, $y ) {
			$rank = function ( $r ) { if ( ! $r['coMa'] ) { return 2; } return $r['daNop'] ? 1 : 0; };
			$rx = $rank( $x ); $ry = $rank( $y ); if ( $rx !== $ry ) { return $rx - $ry; }
			return strcmp( $x['coso'], $y['coso'] );
		} );
		return array( 'ok' => true, 'rows' => $rows, 'soDaNop' => $soDaNop, 'soChuaNop' => $soChuaNop, 'soChuaMa' => $soChuaMa, 'tongNop' => $tongNop );
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
		// TỰ PHÂN LOẠI theo mã nộp của cơ sở: nội dung chứa mã nào -> nhãn tự = cơ sở đó.
		$maToCoso = array(); $maRe = '';
		if ( self::coso_co() ) {
			/* Gộp CẢ HAI sổ mã: mã nộp đã tự mang hệ trong chuỗi (KH705MTD… / KH705KVC…) nên
			   không lẫn được, và bỏ sót một sổ là giao dịch của hệ ấy mất nhãn tự động. */
			$cmMap = self::coso_ma_map() + self::coso_ma_map_kvc(); $tenByKey = array();
			foreach ( self::ds_coso_all() as $c ) { $tenByKey[ self::chuan_ch( $c['ten'] ) ] = $c['ten']; }
			$maList = array();
			foreach ( $cmMap as $k => $ma ) { $ma = trim( (string) $ma ); if ( '' !== $ma && isset( $tenByKey[ $k ] ) ) { $maToCoso[ mb_strtoupper( $ma ) ] = $tenByKey[ $k ]; $maList[] = preg_quote( $ma, '/' ); } }
			if ( $maList ) { $maRe = '/(' . implode( '|', $maList ) . ')/i'; }
		}
		$rows = array(); $tongVao = 0; $tongRa = 0; $tongVaoCong = 0; $tongVaoBank = 0; $congTheoNguon = array();
		$gd = self::gd_all();
		for ( $i = count( $gd ) - 1; $i >= 0; $i-- ) {
			$o = $gd[ $i ]; $o['phapNhan'] = isset( $pn[ $o['soTK'] ] ) ? $pn[ $o['soTK'] ] : '';
			$o['nguonTien'] = $o['vao'] > 0 ? self::nguon_tien_dong( $o['noiDung'] ) : '';
			$o['laCong'] = '' !== $o['nguonTien'];
			$ten = self::cong_ten();
			$o['tenNguonTien'] = $o['vao'] <= 0 ? '' : ( $o['nguonTien'] ? ( 'Cổng ' . ( isset( $ten[ $o['nguonTien'] ] ) ? $ten[ $o['nguonTien'] ] : $o['nguonTien'] ) ) : 'Nộp trực tiếp' );
			$o['coSoMa'] = '';
			if ( '' !== $maRe && preg_match( $maRe, (string) $o['noiDung'], $mm ) ) { $ku = mb_strtoupper( $mm[1] ); if ( isset( $maToCoso[ $ku ] ) ) { $o['coSoMa'] = $maToCoso[ $ku ]; } }
			if ( '' !== $gv( 'soTK' ) && $o['soTK'] !== $gv( 'soTK' ) ) { continue; }
			if ( '' !== $gv( 'loai' ) && $o['loai'] !== $gv( 'loai' ) ) { continue; }
			if ( '' !== $gv( 'phapNhan' ) && $o['phapNhan'] !== $gv( 'phapNhan' ) ) { continue; }
			if ( 'CHUA_PHAN_LOAI' === $gv( 'nhan' ) && ( '' !== $o['nhan'] || '' !== $o['coSoMa'] ) ) { continue; }
			if ( '' !== $gv( 'nhan' ) && 'CHUA_PHAN_LOAI' !== $gv( 'nhan' ) && $o['nhan'] !== $gv( 'nhan' ) && $o['coSoMa'] !== $gv( 'nhan' ) ) { continue; }
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
		self::can_pin( $a ); global $wpdb; @set_time_limit( 120 );
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { self::loi( 'Nguồn không hợp lệ: ' . $nguon ); }
		$tu = self::vn2ymd( isset( $a[2] ) ? (string) $a[2] : '' ); $den = self::vn2ymd( isset( $a[3] ) ? (string) $a[3] : '' );
		/* 0.49.0: lọc theo TÀI KHOẢN VietQR (số TK nhận) — anh Thắng 25/09/2026 thêm tài khoản thứ 2. Rỗng = tất cả. */
		$locTk = preg_replace( '/\s+/', '', (string) ( isset( $a[4] ) ? $a[4] : '' ) );
		/* 🔴 0.55.0: LỌC CƠ SỞ Ở MÁY CHỦ (tham số 6) — anh Thắng 25/09/2026: *"bên ghế và sao kê đang đọc khác nhau"*: màn này
		   cắt 5.000 dòng mới nhất RỒI mới lọc ở trình duyệt; hai tài khoản ~1.200 giao dịch/ngày thì 5.000 dòng chỉ phủ vài
		   ngày cuối — chọn GALAXY QUANG TRUNG cả tháng còn 5 dòng / 130.000đ trong khi Ghế (đọc kho đủ khoảng) 4.950.000đ.
		   Nay đọc CẢ khoảng (không mang `raw` của dòng đọc được — nặng vô ích), quy cơ sở từng dòng, lọc, rồi mới giới hạn
		   2.000 dòng hiện; tổng cả khoảng và tổng theo cơ sở tính trên mọi dòng, màn hình nói rõ khi bị cắt. */
		$locCoSo = trim( (string) ( isset( $a[5] ) ? $a[5] : '' ) );
		$theoTk = array();
		foreach ( self::vqr_tk_ds() as $t ) { if ( '' !== $t['so_tk'] ) { $theoTk[ $t['so_tk'] ] = array( 'soTK' => $t['so_tk'], 'nhan' => $t['nhan'], 'nganHang' => $t['ngan_hang'], 'tien' => 0, 'dong' => 0 ); } }
		$tuKhoa = self::cong_tukhoa( $nguon ); $anhXa = self::ds_anhxa( $nguon ); $mapTen = self::map_ten_diem();
		$tc = self::tbl_cong();
		// Lọc NGÀY bằng SQL trên thoi_diem (đã là Y-m-d H:i:s) — KHÔNG đổi qua dd/mm rồi strtotime
		// (dd/mm bị đọc nhầm thành mm/dd, làm lệch ngày -> "ngày cũ không có").
		$wc = array( 'nguon=%s' ); $ac = array( $nguon );
		if ( $tu )  { $wc[] = 'thoi_diem>=%s'; $ac[] = self::moc_tu_( $tu ); }
		if ( $den ) { $wc[] = 'thoi_diem<%s'; $ac[] = self::moc_den_( $den ); }
		/* Cộng theo tài khoản TRƯỚC khi lọc — ô xổ phải kể đủ mọi tài khoản dù đang xem một cái. 0.55.0: một câu GROUP BY
		   trên CẢ khoảng (trước cộng trên 5.000 dòng mới nhất → thiếu khi khoảng rộng). */
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT so_tk, SUM(so_tien) AS so_tien, COUNT(*) AS dong FROM $tc WHERE " . implode( ' AND ', $wc ) . " AND huong<>%s AND doc_duoc=1 GROUP BY so_tk", array_merge( $ac, array( 'Đi' ) ) ), ARRAY_A ) as $r ) {
			$stk = preg_replace( '/\s+/', '', (string) $r['so_tk'] ); if ( '' === $stk ) { $stk = '(không có số TK)'; }
			if ( ! isset( $theoTk[ $stk ] ) ) { $theoTk[ $stk ] = array( 'soTK' => $stk, 'nhan' => '', 'nganHang' => '', 'tien' => 0, 'dong' => 0 ); }
			$theoTk[ $stk ]['tien'] += (int) $r['so_tien']; $theoTk[ $stk ]['dong'] += (int) $r['dong'];
		}
		if ( '' !== $locTk ) { $wc[] = "REPLACE(so_tk,' ','')=%s"; $ac[] = $locTk; }   // lọc tài khoản trong SQL, không sau khi cắt
		/* $wc[0] là 'nguon=%s' (dựng ở trên) — mọi câu trên bảng cổng đều khoá theo nguồn, kẻo gom tiền MoMo/VNPAY vào VietQR. */
		$rowsC = $wpdb->get_results( $wpdb->prepare( "SELECT khoa, ma_gd, ref, thoi_diem, so_tien, huong, trang_thai, so_tk, noi_dung, diem_ban, ma_ch, may_tay, doc_duoc, IF(doc_duoc=1,'',raw) AS raw, nhan_luc FROM $tc WHERE " . implode( ' AND ', $wc ) . " ORDER BY thoi_diem DESC, id DESC LIMIT 60000", $ac ), ARRAY_A );
		$quaNhieu = count( (array) $rowsC ) >= 60000;
		$cong = array(); $congTien = 0; $congKho = 0; $khoRows = array(); $tongMoiNguon = 0; $payloadCuoi = '';
		$theoCoSo = array(); $congDongKhoang = 0; $congTienKhoang = 0; $congTuNgay = '';
		$chuaAnhXa = array(); $chuaRoMay = 0; $chuaRoTien = 0;
		/* Mốc "lần nạp file gần nhất phủ tới thời điểm nào" — xem `rpc_napFileCongTx()`. */
		$nap = get_option( 'saoke_cong_nap_' . $nguon ); $nap = is_array( $nap ) ? $nap : array();
		$napDen = isset( $nap['den'] ) ? (string) $nap['den'] : '';
		$chuaRoMoi = 0;
		foreach ( (array) $rowsC as $r ) {
			$tongMoiNguon++;
			$thoiDiem = self::ymd2vn( $r['thoi_diem'] );
			if ( (int) $r['doc_duoc'] !== 1 ) { $congKho++; if ( count( $khoRows ) < 20 ) { $khoRows[] = array( 'khoa' => $r['khoa'], 'nhanLuc' => self::ymd2vn( $r['nhan_luc'] ), 'raw' => mb_substr( (string) $r['raw'], 0, 400 ) ); } continue; }
			if ( 'Đi' === $r['huong'] ) { continue; }
			/* 🔴 BẢN SAO THỨ BA CỦA LUẬT SUY RA MÁY — và là bản MÀN HÌNH THẬT SỰ ĐỌC.
			   0.17.0 gom luật vào `cong_may_dong()` và sửa hai nơi, nhưng SÓT đúng chỗ này: hàm
			   `r_saoke_cong()` (đường REST) và `rpc_getSaoKeCong()` (đường app gọi) là HAI bản
			   viết riêng cho cùng một màn. Sửa bản không ai gọi thì bộ thử xanh, file nạp đúng,
			   cột `ma_ch` có dữ liệu — mà màn hình vẫn "chưa rõ máy". Anh Thắng: *"vẫn chưa lọc
			   hết"*, và anh đúng: không dòng nào lọc được cả.
			   ⚠️ Bài học ghi lại cho người sau: đếm "luật chỉ còn một chỗ" bằng cách đếm MỘT
			      chuỗi là đếm hụt. Bộ thử nay đếm MỌI lời gọi `cong_ten_may()` ngoài thân
			      `cong_may_dong()` và bắt nó phải bằng 0. */
			$tenMay = self::cong_may_dong( $r['noi_dung'], isset( $r['ma_ch'] ) ? $r['ma_ch'] : '', $r['diem_ban'], isset( $r['may_tay'] ) ? $r['may_tay'] : '' );
			$coSo = self::cong_coso( $tenMay );
			$ax = self::ax_theo_ngay( self::ax_cua_may_( $anhXa, $tenMay, isset( $r['ma_ch'] ) ? $r['ma_ch'] : '' ), $thoiDiem );
			$suy = self::ax_ma_nop( $ax, $mapTen ); $soTien = (int) $r['so_tien'];
			// POSH: tự lấy địa điểm từ trang Ghế theo tên máy; nếu trượt mà anh đã gán tay tới 1 địa điểm ghế thì dùng nó.
			$qd = self::cong_coso_dong( $tenMay, isset( $r['ma_ch'] ) ? $r['ma_ch'] : '', $ax, isset( $r['may_tay'] ) ? $r['may_tay'] : '' );
			$ghe = '' !== $qd['coso'] ? array( 'coso' => $qd['coso'], 'maKh' => '', 'tinh' => '' ) : null;
			/* Hiện TÊN CƠ SỞ GHẾ khi quy được; chỉ khi không quy được mới hiện tên ánh xạ/tên cửa hàng — và
			   khi ấy đánh dấu để không trông như đã khớp (ca GO BẾN TRE: bảng hiện tên mà Ghế báo "–"). */
			$cuaHang = $ghe ? $ghe['coso'] : ( '⚠ ' . ( $ax ? ( '' !== $ax['tenChuan'] ? $ax['tenChuan'] : $coSo ) : $coSo ) . ' (chưa quy được cơ sở Ghế)' );
			if ( ! empty( $qd['xungDot'] ) ) {
				$cuaHang .= ( 'ma-ch' === $qd['nguon'] )
					? ' ⚠ mâu thuẫn: ánh xạ tay nói "' . $qd['coSoKhac'] . '" — lấy theo MÃ CỬA HÀNG'
					: ' ⚠ mâu thuẫn: mã cửa hàng nói "' . $qd['coSoKhac'] . '" — lấy theo máy trong nội dung';
			}
			$daAnhXa = $ghe ? true : ( '' !== $suy['ma'] );
			if ( '' === $tenMay ) {
				$chuaRoMay++; $chuaRoTien += $soTien;
				if ( '' !== $napDen && (string) $r['thoi_diem'] > $napDen ) { $chuaRoMoi++; }
			}
			elseif ( ! $daAnhXa ) { $k = self::chuan_ch( $coSo ); if ( ! isset( $chuaAnhXa[ $k ] ) ) { $chuaAnhXa[ $k ] = array( 'ten' => $coSo, 'soTien' => 0, 'soLan' => 0, 'may' => array(), 'vi' => self::ghe_co() ? 'máy chưa có trong trang Ghế — thêm/gắn máy bên Ghế là tự nhận' : $suy['vi'] ); } $chuaAnhXa[ $k ]['soTien'] += $soTien; $chuaAnhXa[ $k ]['soLan']++; $chuaAnhXa[ $k ]['may'][ $tenMay ] = 1; }
			/* 0.55.0: nhãn lọc cơ sở = cơ sở Ghế đã quy; chưa quy được → nhãn cảnh báo; không rõ máy → __chuaro__. Tổng theo cơ sở
			   và tổng cả khoảng cộng TRƯỚC khi cắt 2.000 dòng hiện. */
			$nhanLoc = '' !== $qd['coso'] ? $qd['coso'] : ( '' === $tenMay ? '__chuaro__' : $cuaHang );
			if ( ! isset( $theoCoSo[ $nhanLoc ] ) ) { $theoCoSo[ $nhanLoc ] = array( 'ten' => $nhanLoc, 'tien' => 0, 'dong' => 0 ); }
			$theoCoSo[ $nhanLoc ]['tien'] += $soTien; $theoCoSo[ $nhanLoc ]['dong']++;
			if ( '' !== $locCoSo && $nhanLoc !== $locCoSo ) { continue; }
			$congDongKhoang++; $congTienKhoang += $soTien;
			if ( count( $cong ) >= 2000 ) { continue; }   // vẫn đếm tiếp cho tổng cả khoảng, chỉ thôi đưa vào bảng
			$cong[] = array( 'khoa' => $r['khoa'], 'maGD' => $r['ma_gd'], 'ref' => $r['ref'], 'thoiDiem' => $thoiDiem, 'soTien' => $soTien,
				'huong' => $r['huong'], 'trangThai' => $r['trang_thai'], 'soTK' => $r['so_tk'], 'noiDung' => $r['noi_dung'], 'diemBan' => $r['diem_ban'],
				'docDuoc' => true, 'nhanLuc' => self::ymd2vn( $r['nhan_luc'] ), 'tenMay' => $tenMay, 'coSo' => $coSo,
				'mayTay' => isset( $r['may_tay'] ) ? (string) $r['may_tay'] : '',
				'maCH' => isset( $r['ma_ch'] ) ? (string) $r['ma_ch'] : '',
				'gheCoSo' => $qd['coso'], 'nguonCoSo' => $qd['nguon'], 'xungDot' => (int) $qd['xungDot'], 'coSoKhac' => $qd['coSoKhac'],
				/* Dòng phát sinh SAU lần nạp file gần nhất thì "chưa rõ máy" là chuyện đương
				   nhiên — file không thể chứa nó. Nói ra để khỏi tưởng bản vá hỏng. */
				'moiHonNap' => ( '' !== $napDen && (string) $r['thoi_diem'] > $napDen ),
				'cuaHangChuan' => $cuaHang, 'maBank' => $ghe ? ( '' !== self::coso_ma( $ghe['coso'] ) ? self::coso_ma( $ghe['coso'] ) : $ghe['maKh'] ) : $suy['ma'], 'daAnhXa' => $daAnhXa, 'tinh' => $ghe ? $ghe['tinh'] : '' );
			$congTien += $soTien; $congTuNgay = $thoiDiem;
		}
		$payloadCuoi = (string) $wpdb->get_var( $wpdb->prepare( "SELECT raw FROM $tc WHERE nguon=%s ORDER BY id DESC LIMIT 1", $nguon ) );
		$dsTheoCoSo = array_values( $theoCoSo ); usort( $dsTheoCoSo, function ( $x, $y ) { return $y['tien'] - $x['tien']; } );
		$dsChuaAnhXa = array();
		foreach ( $chuaAnhXa as $g ) { $may = array_keys( $g['may'] ); sort( $may ); $dsChuaAnhXa[] = array( 'ten' => $g['ten'], 'soTien' => $g['soTien'], 'soLan' => $g['soLan'], 'may' => $may, 'vi' => $g['vi'] ); }
		usort( $dsChuaAnhXa, function ( $x, $y ) { return $y['soTien'] - $x['soTien']; } );
		$tbl = self::tbl(); $kw = self::kd( $tuKhoa );
		$wb = array( "loai='in'" ); $ab = array();
		if ( $tu )  { $wb[] = 'DATE(ngay_gd)>=%s'; $ab[] = $tu; }
		if ( $den ) { $wb[] = 'DATE(ngay_gd)<=%s'; $ab[] = $den; }
		if ( '' !== $tuKhoa ) { $wb[] = 'noi_dung LIKE %s'; $ab[] = '%' . $wpdb->esc_like( $tuKhoa ) . '%'; }
		if ( '' !== $locTk ) { $wb[] = 'so_tk=%s'; $ab[] = $locTk; }   // 0.49.0: bên bank cũng lọc theo cùng tài khoản
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
			'chuaRoMay' => $chuaRoMay, 'chuaRoTien' => $chuaRoTien, 'chuaRoMoi' => $chuaRoMoi,
			'napLan' => $nap,
			'taiKhoan' => array_values( $theoTk ), 'locTk' => $locTk,
			'theoCoSo' => $dsTheoCoSo, 'locCoSo' => $locCoSo, 'congDongKhoang' => $congDongKhoang, 'congTienKhoang' => $congTienKhoang,
			'biCat' => $congDongKhoang > count( $cong ), 'congTuNgay' => $congTuNgay, 'quaNhieu' => $quaNhieu,
			'log' => array(), 'bank' => $bank, 'bankTien' => $bankTien, 'bankDong' => count( $bank ),
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
		/* 🔴 DOANH THU MỘT CỬA HÀNG THEO TỪNG NGÀY (0.41.0) — anh Thắng 16/09/2026: *"muốn xem
		   doanh thu momo của 1 cửa hàng theo ngày"*. Bảng gốc đã là từng (ngày × cửa hàng) nên
		   không phải truy vấn gì thêm, chỉ là trước nay gom mất một chiều: `theoNgay` cộng hết
		   cửa hàng lại, `theoCuaHang` cộng hết ngày lại — cái chéo giữa hai bảng thì không ai
		   gửi ra. */
		$theoNgayCH = array();
		$khongTenCH = array( 'soTien' => 0, 'soDong' => 0, 'ngay' => array() );
		foreach ( (array) $rows as $r ) {
			$ngay = (string) $r['ngay']; $chFile = (string) $r['ch_file']; $tien = (int) $r['so_tien'];
			$theoNgay[ $ngay ] = ( isset( $theoNgay[ $ngay ] ) ? $theoNgay[ $ngay ] : 0 ) + $tien;
			$soNgayCo[ $ngay ] = ( isset( $soNgayCo[ $ngay ] ) ? $soNgayCo[ $ngay ] : 0 ) + 1;
			/* ⚠️ GOM Ở ĐÂY, TRƯỚC cửa `continue` lọc một ngày bên dưới. Gom sau cửa ấy thì hễ
			   người dùng bấm "xem riêng" một ngày là bảng theo-ngày của cửa hàng rút còn đúng
			   một dòng — trông y như cửa hàng đó cả tháng chỉ bán một hôm. */
			if ( '' !== $chFile ) {
				if ( ! isset( $theoNgayCH[ $chFile ] ) ) { $theoNgayCH[ $chFile ] = array(); }
				$theoNgayCH[ $chFile ][ $ngay ] = ( isset( $theoNgayCH[ $chFile ][ $ngay ] ) ? $theoNgayCH[ $chFile ][ $ngay ] : 0 ) + $tien;
			}
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
			'theoNgayCH' => $theoNgayCH,
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
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * CỬA ĐỌC GIAO DỊCH CỔNG CHO PLUGIN KHÁC CÙNG SITE (0.43.0)
	 *
	 * Anh Thắng 16/09/2026: *"có bên khác muốn lấy dữ liệu momo, nhưng chỉ bóc theo ngày, không
	 * lấy theo giao dịch lẻ được"*.
	 *
	 * Bảng đối soát `saoke_congfile` gộp theo (ngày × cửa hàng) — cố ý, cả màn đối soát sống bằng
	 * nó. Còn từng giao dịch thì nằm ở bảng `saoke_cong`, và từ bản này MoMo cũng đổ vào đó nếu
	 * người nạp có chọn cột Mã giao dịch.
	 *
	 * 🔴 CHỈ ĐỌC. Không có đường ghi nào ở đây: plugin khác cần sửa dữ liệu thì đi qua màn của
	 *    Sao Kê, để mọi thay đổi còn lại dấu vết ở một chỗ.
	 * ⚠️ TRẢ MẢNG THUẦN, KHÔNG TRẢ ĐỐI TƯỢNG $wpdb. Bên kia lỡ giữ tham chiếu là giữ luôn cả
	 *    trạng thái kết nối; mảng thì họ muốn làm gì cũng không đụng tới mình.
	 * ⚠️ `$gioi_han` có trần cứng 20000 — một lượt đọc lỡ tay không được phép kéo sập trang.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	public static function gd_cong_ds( $nguon = 'momo', $tu = '', $den = '', $gioi_han = 5000 ) {
		global $wpdb;
		$nguon = strtolower( trim( (string) $nguon ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array(); }
		$tc = self::tbl_cong();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tc ) ) !== $tc ) { return array(); }
		$gioi_han = max( 1, min( 20000, (int) $gioi_han ) );
		$w = array( 'nguon=%s', 'doc_duoc=1' ); $ar = array( $nguon );
		if ( '' !== $tu )  { $w[] = 'thoi_diem>=%s'; $ar[] = self::moc_tu_( $tu ); }
		if ( '' !== $den ) { $w[] = 'thoi_diem<%s'; $ar[] = self::moc_den_( $den ); }
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ma_gd, ref, thoi_diem, so_tien, noi_dung, ma_ch, diem_ban, trang_thai, may_tay"
			. " FROM $tc WHERE " . implode( ' AND ', $w ) . " ORDER BY thoi_diem ASC, id ASC LIMIT " . $gioi_han,
			$ar ), ARRAY_A );
		$ra = array();
		foreach ( (array) $rows as $r ) {
			$ra[] = array(
				'maGD'      => (string) $r['ma_gd'],
				'ref'       => (string) $r['ref'],
				'thoiDiem'  => (string) $r['thoi_diem'],
				'soTien'    => (int) $r['so_tien'],
				'cuaHang'   => (string) $r['noi_dung'],   // tên cửa hàng ở file cổng
				'maCH'      => (string) $r['ma_ch'],
				'diemBan'   => (string) $r['diem_ban'],
				'trangThai' => (string) $r['trang_thai'],
				'may'       => self::cong_may_dong( (string) $r['noi_dung'], (string) $r['ma_ch'], (string) $r['diem_ban'], (string) $r['may_tay'] ),
			);
		}
		return $ra;
	}

	/** Cho bên nào thích dùng bộ lọc hơn gọi thẳng lớp. Cùng dữ liệu, cùng luật. */
	public static function loc_gd_cong( $mac_dinh, $nguon = 'momo', $tu = '', $den = '' ) {
		return self::gd_cong_ds( $nguon, $tu, $den );
	}

	/**
	 * NẠP BÙ GIAO DỊCH TỪ FILE KẾT XUẤT — MỘT ĐỢT. Màn hình chia file thành các đợt ~400 dòng rồi gộp kết quả (0.52.0).
	 *
	 * 🔴 VÌ SAO CHIA ĐỢT — anh Thắng 25/09/2026: *"nạp file bù rất lâu và hay lỗi"* (7.816 dòng → hosting trả trang HTML
	 *    "Unexpected token '<'"; 24.261 dòng → "File quá lớn"). Một yêu cầu ôm cả file: mỗi dòng 2–3 câu SQL dò trùng
	 *    (khoá, rồi mã tham chiếu) → ~20.000 câu, quá giờ máy chủ. Nay:
	 *      (1) mỗi đợt ≤ 2.000 dòng (màn hình gửi 800), kết quả từng đợt cộng dồn ở màn hình (cgGopKqTx);
	 *      (0.53.0) vá mã cửa hàng theo lô một câu (cong_va_lo_) và KHÔNG tính lại kho Ghế tại chỗ — chỉ đánh dấu ngày cũ.
	 *      (2) dò trùng theo LÔ: một câu `SELECT … WHERE khoa IN (…)` cho cả đợt — dòng đã có (đa số: webhook đã ghi)
	 *          không hỏi lại từng dòng; chỉ dòng thật sự mới đi đường luu_cong() đầy đủ (có dò theo mã tham chiếu).
	 *    Nạp lại cùng file bao nhiêu lần cũng an toàn (khoá chống trùng): đợt lỗi giữa chừng thì bấm lại, không đếm hai lần.
	 */
	public static function rpc_napFileCongTx( $a ) {
		self::can_pin( $a ); @set_time_limit( 120 );
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : '' ) );
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		$rows = isset( $a[2] ) && is_array( $a[2] ) ? $a[2] : array();
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'error' => 'File không có dòng dữ liệu nào' ); }
		if ( count( $rows ) > 2000 ) { return array( 'ok' => false, 'error' => 'Mỗi đợt tối đa 2.000 dòng (đang gửi ' . count( $rows ) . ') — màn hình bản mới tự chia đợt: tải lại trang (Ctrl+F5) rồi nạp lại.' ); }
		$tenFile = sanitize_text_field( isset( $a[3] ) ? (string) $a[3] : '' ); $anhXa = self::ds_anhxa( $nguon );
		$moi = 0; $trung = 0; $boQua = 0; $khongNgay = 0; $khongTien = 0; $khongMa = 0; $tongMoi = 0; $chMoi = array(); $chuaRoMay = 0;
		$vaMay = 0; $maChFile = array(); $khongThanhCong = 0; $denNhat = '';
		/* VÒNG 1 — đọc cột, lọc dòng bỏ, dựng $tx + khoá. Chưa đụng DB. */
		$dsTx = array(); $thayKhoa = array();
		foreach ( $rows as $rr ) { $r = (array) $rr;
			$thoiDiem = self::cong_ngay( isset( $r[0] ) ? $r[0] : '' ); $soTien = self::num( isset( $r[1] ) ? $r[1] : 0 );
			$maGD = trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) ); $ref = trim( (string) ( isset( $r[3] ) ? $r[3] : '' ) ); $noiDung = trim( (string) ( isset( $r[4] ) ? $r[4] : '' ) );
			/* Hai cột của bản kết xuất Việt QR mà webhook KHÔNG gửi — xem `vqr_ds_ch()`. Cột cũ
			   (5 cột) vẫn nạp được y như trước: thiếu thì là chuỗi rỗng, không ai phải chọn lại. */
			$maCH = self::vqr_ma_ch( isset( $r[5] ) ? $r[5] : '' );
			$maDiem = trim( (string) ( isset( $r[6] ) ? $r[6] : '' ) );
			$ttFile = trim( (string) ( isset( $r[7] ) ? $r[7] : '' ) );
			/* 🔴 DÒNG KHÔNG THÀNH CÔNG KHÔNG PHẢI LÀ TIỀN. Bản kết xuất có cột Trạng thái, mà
			   cửa nạp trước đây không đọc nó: một file có dòng hỏng/hoàn là ghi thẳng vào bảng
			   như doanh thu, rồi nó nằm im trong tổng "Từ cổng" — chỉ lộ ra ở ô "Chênh lệch
			   (cổng − bank)" dưới dạng một con số không ai giải thích nổi.
			   ⚠️ Cột này KHÔNG bắt buộc: để "(không có)" thì giữ nguyên nếp cũ, nhận hết. */
			if ( '' !== $ttFile && self::cong_tt_hong( $ttFile ) ) { $khongThanhCong++; $boQua++; continue; }
			if ( '' === $thoiDiem ) { $khongNgay++; $boQua++; continue; }
			if ( $soTien <= 0 ) { $khongTien++; $boQua++; continue; }
			if ( '' === $maGD && '' === $ref ) { $khongMa++; $boQua++; continue; }
			$tx = array( 'nguon' => $nguon, 'maGD' => $maGD, 'ref' => $ref, 'thoiDiem' => $thoiDiem, 'soTien' => $soTien, 'huong' => 'Đến', 'trangThai' => mb_substr( $ttFile, 0, 30 ), 'soTK' => '', 'noiDung' => $noiDung, 'diemBan' => '', 'maCH' => $maCH, 'docDuoc' => true );
			$tx['khoa'] = self::cong_khoa( $nguon, $tx, wp_json_encode( $r ) ); $tx['raw'] = 'FILE ' . $tenFile . ' · ' . mb_substr( (string) wp_json_encode( $r ), 0, 1500 );
			/* Cùng khoá xuất hiện hai lần TRONG MỘT ĐỢT (file xuất trùng dòng): dòng sau là trùng — trước đây SELECT từng dòng bắt được, nay dò lô phải tự bắt. */
			$k120 = mb_substr( $tx['khoa'], 0, 120 ); if ( isset( $thayKhoa[ $k120 ] ) ) { $trung++; continue; } $thayKhoa[ $k120 ] = 1;
			$dsTx[] = array( 'tx' => $tx, 'maCH' => $maCH, 'noiDung' => $noiDung, 'thoiDiem' => $thoiDiem, 'soTien' => $soTien );
		}
		/* VÒNG 2 — dò trùng theo LÔ (một câu cho cả đợt), rồi ghi từng dòng. */
		$khoas = array(); foreach ( $dsTx as $d ) { $khoas[] = $d['tx']['khoa']; }
		$daCo = self::cong_cu_theo_khoa_( $khoas ); $vaLo = array();
		/* 0.54.0: dòng khoá chưa có → dò tiếp theo ref / mã GD cũng theo LÔ (trước: 2 câu quét bảng MỖI dòng mới → HTTP 500 ở đợt toàn dòng mới). */
		$chuaKhoa = array(); foreach ( $dsTx as $d ) { if ( ! isset( $daCo[ mb_substr( $d['tx']['khoa'], 0, 120 ) ] ) ) { $chuaKhoa[] = $d; } }
		$daCoRef = $chuaKhoa ? self::cong_cu_theo_ref_( $chuaKhoa ) : array();
		foreach ( $dsTx as $d ) {
			$tx = $d['tx']; $maCH = $d['maCH']; $noiDung = $d['noiDung']; $thoiDiem = $d['thoiDiem']; $soTien = $d['soTien'];
			if ( '' !== $maCH ) { $maChFile[ $maCH ] = ( isset( $maChFile[ $maCH ] ) ? $maChFile[ $maCH ] : 0 ) + 1; }
			$tenMay = self::cong_may_dong( $noiDung, $maCH, '' );
			if ( '' === $tenMay ) { $chuaRoMay++; }
			else {
				/* 🔴 "CHƯA GÁN" = KHÔNG QUY ĐƯỢC VỀ CƠ SỞ GHẾ BẰNG BẤT KỲ NHÂN CHỨNG NÀO (0.51.0). Trước đây chỉ hỏi "có dòng
				   ánh xạ chưa" nên kể cả máy đã khớp thẳng ghế vẫn bị liệt kê — 473 dòng, không ai gán nổi. Nay hỏi đúng
				   cong_coso_dong() (máy → ghế · ánh xạ · mã cửa hàng · tên điểm bán); nhãn gom theo TÊN ĐIỂM BÁN của cổng
				   (cấp cơ sở), hụt mới lấy cơ sở suy từ tên máy. Hai nút bên màn hình làm việc trên nhãn ấy. */
				$axD = self::ax_theo_ngay( self::ax_cua_may_( $anhXa, $tenMay, $maCH ), $thoiDiem );
				$qdD = self::cong_coso_dong( $tenMay, $maCH, $axD, '' );
				if ( '' === $qdD['coso'] ) {
					$nhan = self::vqr_diem_theo_ma_( $maCH ); if ( '' === $nhan ) { $nhan = self::cong_coso( $tenMay ); }
					if ( ! isset( $chMoi[ $nhan ] ) ) { $chMoi[ $nhan ] = array( 'ten' => $nhan, 'soGd' => 0, 'tien' => 0, 'maCH' => $maCH, 'tenMay' => $tenMay ); }
					$chMoi[ $nhan ]['soGd']++; $chMoi[ $nhan ]['tien'] += $soTien;
				}
			}
			$mysql = self::cong_ngay_mysql( $thoiDiem );
			if ( $mysql && $mysql > $denNhat ) { $denNhat = $mysql; }
			if ( 'vietqr' === $nguon ) { self::ghe_dau_ngay_( $mysql ); }   // nạp file có thể VÁ ma_ch vào dòng cũ → ngày ấy phải tính lại
			$kqLuu = ''; $k120 = mb_substr( $tx['khoa'], 0, 120 );
			$cuBiet = isset( $daCo[ $k120 ] ) ? $daCo[ $k120 ] : ( isset( $daCoRef[ $k120 ] ) ? $daCoRef[ $k120 ] : 'moi' );
			if ( self::luu_cong( $tx, $kqLuu, $cuBiet, $vaLo ) ) { $moi++; $tongMoi += $soTien; } else { $trung++; if ( 'va' === $kqLuu ) { $vaMay++; } }
		}
		self::cong_va_lo_( $vaLo );
		self::day_ghe_ngay_don_();
		/* 🔴 GHI LẠI LẦN NẠP NÀY PHỦ TỚI ĐÂU. Anh Thắng 12/09/2026: *"vẫn chưa lọc hết"* — chỉ vào
		   hai dòng 15:28 và 15:29, trong khi file anh xuất lúc 15:26 dừng ở 15:26:09. File không
		   thể chứa chúng, nên không có gì để vá; nhưng màn hình thì không nói điều đó ra, và
		   nhìn vào chỉ thấy "vẫn còn". Nhớ mốc này để mỗi dòng "chưa rõ máy" tự phân biệt được
		   "chưa nạp tới" với "nạp rồi mà vẫn không có mã". */
		if ( '' !== $denNhat ) {
			/* ⚠️ CHỈ TIẾN, KHÔNG LÙI. Nạp một file CŨ hơn (bù tháng trước) mà kéo mốc lùi thì mọi
			   dòng mới lại mang nhãn "mới hơn lần nạp" — sai, và sai theo hướng làm người ta đi
			   xuất lại file một cách vô ích. */
			$cu_nap = get_option( 'saoke_cong_nap_' . $nguon );
			$cu_den = ( is_array( $cu_nap ) && isset( $cu_nap['den'] ) ) ? (string) $cu_nap['den'] : '';
			if ( $denNhat > $cu_den ) {
				update_option( 'saoke_cong_nap_' . $nguon, array(
					'luc' => current_time( 'mysql' ), 'den' => $denNhat,
					'tenFile' => $tenFile, 'soDong' => count( $rows ) ), false );
			}
		}
		$cm = array_keys( $chMoi ); sort( $cm );
		$chuaGan = array_values( $chMoi ); usort( $chuaGan, function ( $x, $y ) { return $y['tien'] - $x['tien']; } ); $chuaGan = array_slice( $chuaGan, 0, 80 );
		/* 🔴 MÃ CỬA HÀNG CÓ TRONG FILE MÀ CHƯA CÓ TRONG BẢN ĐỒ thì phải kể tên ra. Nạp xong thấy
		   "đã vá 0 dòng" mà không biết vì sao là bỏ cuộc; biết là "12 mã chưa có trong bản đồ" thì
		   đi nạp bảng Danh sách cửa hàng là xong. */
		$dsCH = self::vqr_ds_ch(); $thieuBD = array();
		foreach ( array_keys( $maChFile ) as $mc ) { if ( ! isset( $dsCH[ $mc ] ) ) { $thieuBD[] = $mc; } }
		sort( $thieuBD );
		return array( 'ok' => true, 'nguon' => $nguon, 'tenFile' => $tenFile, 'soDongFile' => count( $rows ), 'themMoi' => $moi, 'trungBoQua' => $trung,
			'tongTienThem' => $tongMoi, 'boQuaDong' => $boQua, 'khongNgay' => $khongNgay, 'khongTien' => $khongTien, 'khongMa' => $khongMa, 'chuaRoMay' => $chuaRoMay, 'cuaHangMoi' => $cm, 'chuaGan' => $chuaGan, 'soChuaGan' => count( $chMoi ),
			'vaMay' => $vaMay, 'soMaCH' => count( $maChFile ), 'dsMaCH' => array_keys( $maChFile ), 'thieuBanDo' => $thieuBD, 'soThieuBanDo' => count( $thieuBD ),
			'khongThanhCong' => $khongThanhCong );
	}

	/**
	 * ── NẠP "DANH SÁCH CỬA HÀNG" CỦA VIỆT QR (napDsCuaHangVqr) ──
	 *
	 * Bảng cổng xuất ra: `Mã cửa hàng` · `Tên cửa hàng` · `Mã điểm bán` · `Tên điểm bán`.
	 * "Tên cửa hàng" bên cổng CHÍNH LÀ tên máy bên mình ("LM-NSG 01"), "Tên điểm bán" là cơ sở.
	 *
	 * ⚠️ NẠP LẠI LÀ GỘP, KHÔNG PHẢI XOÁ HẾT RỒI GHI. Cổng cho tải từng trang, và người ta hay nạp
	 *    làm nhiều lượt; xoá hết mỗi lượt là lượt sau đá mất lượt trước mà không câu nào báo.
	 *    Muốn dọn sạch thì có nút Xoá riêng, một hành động cố ý.
	 */
	public static function rpc_napDsCuaHangVqr( $a ) {
		self::can_pin( $a );
		$rows = isset( $a[1] ) && is_array( $a[1] ) ? $a[1] : array();
		if ( ! count( $rows ) ) { return array( 'ok' => false, 'error' => 'File không có dòng dữ liệu nào' ); }
		if ( count( $rows ) > 20000 ) { return array( 'ok' => false, 'error' => 'File quá lớn (' . count( $rows ) . ' dòng), tách nhỏ giúp em' ); }
		$tenFile = sanitize_text_field( isset( $a[2] ) ? (string) $a[2] : '' );
		$ds = self::vqr_ds_ch();
		$moi = 0; $sua = 0; $yNguyen = 0; $boQua = 0; $luc = current_time( 'mysql' );
		foreach ( $rows as $rr ) {
			$r = (array) $rr;
			$ma = self::vqr_ma_ch( isset( $r[0] ) ? $r[0] : '' );
			$ten = trim( preg_replace( '/\s+/', ' ', (string) ( isset( $r[1] ) ? $r[1] : '' ) ) );
			$maD = trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) );
			$tenD = trim( preg_replace( '/\s+/', ' ', (string) ( isset( $r[3] ) ? $r[3] : '' ) ) );
			/* Thiếu MÃ hoặc thiếu TÊN thì dòng ấy vô dụng: bản đồ này chỉ có một việc là mã -> tên. */
			if ( '' === $ma || '' === $ten ) { $boQua++; continue; }
			$cu = isset( $ds[ $ma ] ) ? $ds[ $ma ] : null;
			$moiDong = array( 'ten' => mb_substr( $ten, 0, 120 ), 'maDiem' => mb_substr( $maD, 0, 60 ),
				'tenDiem' => mb_substr( $tenD, 0, 160 ), 'luc' => $luc, 'file' => mb_substr( $tenFile, 0, 120 ) );
			if ( ! $cu ) { $ds[ $ma ] = $moiDong; $moi++; continue; }
			if ( (string) $cu['ten'] === $moiDong['ten']
				&& (string) ( isset( $cu['tenDiem'] ) ? $cu['tenDiem'] : '' ) === $moiDong['tenDiem'] ) { $yNguyen++; continue; }
			$ds[ $ma ] = $moiDong; $sua++;
		}
		update_option( 'saoke_vqr_ch', $ds, false );
		self::vqr_ch_quen_();
		self::day_ghe_gan_day_( 7 );   // bản đồ đổi → số gán lại → kho Ghế 7 ngày gần phải tính lại
		return array( 'ok' => true, 'tenFile' => $tenFile, 'soDongFile' => count( $rows ),
			'themMoi' => $moi, 'daSua' => $sua, 'yNguyen' => $yNguyen, 'boQua' => $boQua, 'tong' => count( $ds ) );
	}

	/** Bản đồ đang có, dạng mảng phẳng để vẽ bảng. */
	public static function rpc_getDsCuaHangVqr( $a ) {
		self::can_pin( $a );
		$ds = self::vqr_ds_ch(); $out = array();
		foreach ( $ds as $ma => $v ) {
			$out[] = array( 'maCH' => (string) $ma, 'ten' => (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ),
				'maDiem' => (string) ( isset( $v['maDiem'] ) ? $v['maDiem'] : '' ),
				'tenDiem' => (string) ( isset( $v['tenDiem'] ) ? $v['tenDiem'] : '' ),
				'luc' => (string) ( isset( $v['luc'] ) ? $v['luc'] : '' ) );
		}
		usort( $out, function ( $x, $y ) { return strcmp( $x['ten'], $y['ten'] ); } );
		return array( 'ok' => true, 'rows' => $out, 'tong' => count( $out ) );
	}

	/** Xoá SẠCH bản đồ — hành động cố ý, không dính vào lượt nạp. */
	public static function rpc_xoaDsCuaHangVqr( $a ) {
		self::can_pin( $a );
		$n = count( self::vqr_ds_ch() );
		update_option( 'saoke_vqr_ch', array(), false );
		self::vqr_ch_quen_();
		self::day_ghe_gan_day_( 7 );
		return array( 'ok' => true, 'daXoa' => $n );
	}

	// ── Ánh xạ cửa hàng (luuAnhXaCuaHang) ──
	public static function rpc_luuAnhXaCuaHang( $a ) {
		self::can_pin( $a );
		return self::soft_err( self::r_anhxa_luu( self::req( array( 'pin' => $a[0], 'nguon' => isset( $a[1] ) ? $a[1] : '', 'tenFile' => isset( $a[2] ) ? $a[2] : '',
			'tenChuan' => isset( $a[3] ) ? $a[3] : '', 'maBank' => isset( $a[4] ) ? $a[4] : '', 'tuNgay' => isset( $a[5] ) ? $a[5] : '', 'denNgay' => isset( $a[6] ) ? $a[6] : '' ) ) ) );
	}

	/**
	 * TẠO CƠ SỞ MỚI BÊN GHẾ từ một nhãn cổng chưa quy được (nút "Tạo cơ sở mới bên Ghế", 0.51.0) — anh Thắng 25/09/2026:
	 * *"Nếu chưa gán anh tạo cửa hàng mới được không"* → *"Làm luôn 2 nút đó đi em"*. Tạo bằng chính VHG_May::luu_coso()
	 * của Ghế (chặn tên gần trùng, báo móc cho Chi Phí) rồi ghi ánh xạ nhãn → tên cơ sở để dòng cổng mang nhãn ấy quy về
	 * đây kể cả khi anh sửa tên lúc tạo. Ghế cũ chưa có lớp thì nói thẳng. $a = [PIN, nguon, nhãn cổng, tên cơ sở mới].
	 */
	public static function rpc_taoCoSoGhe( $a ) {
		self::can_pin( $a );
		$nguon = strtolower( trim( isset( $a[1] ) ? (string) $a[1] : 'vietqr' ) );
		$nhan = trim( sanitize_text_field( isset( $a[2] ) ? (string) $a[2] : '' ) );
		$ten  = trim( sanitize_text_field( isset( $a[3] ) ? (string) $a[3] : '' ) ); if ( '' === $ten ) { $ten = $nhan; }
		$ten = mb_substr( preg_replace( '/\s+/', ' ', $ten ), 0, 120 );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		if ( ! in_array( $nguon, self::cong_ds(), true ) ) { return array( 'ok' => false, 'error' => 'Nguồn không hợp lệ: ' . $nguon ); }
		if ( ! class_exists( 'VHG_May' ) || ! method_exists( 'VHG_May', 'luu_coso' ) ) { return array( 'ok' => false, 'error' => 'Chưa cài plugin Ghế (hoặc bản cũ) — không tạo cơ sở được từ đây.' ); }
		$r = VHG_May::luu_coso( 0, $ten, null, null );
		if ( ! is_array( $r ) || empty( $r['ok'] ) ) { return array( 'ok' => false, 'error' => is_array( $r ) && ! empty( $r['error'] ) ? (string) $r['error'] : 'Ghế không tạo được cơ sở.' ); }
		$ax = null;
		if ( '' !== $nhan ) {
			$ax = self::soft_err( self::r_anhxa_luu( self::req( array( 'pin' => $a[0], 'nguon' => $nguon, 'tenFile' => $nhan, 'tenChuan' => $ten, 'maBank' => '', 'tuNgay' => '', 'denNgay' => '', 'gheMoi' => '1' ) ) ) );
		}
		if ( class_exists( 'VHG_Nhat_Ky' ) && method_exists( 'VHG_Nhat_Ky', 'ghi' ) ) {
			VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' => 'Sao Kê tạo cơ sở "' . $ten . '" từ cửa hàng cổng "' . $nhan . '" (' . $nguon . ')' ) );
		}
		return array( 'ok' => true, 'id' => (int) ( isset( $r['id'] ) ? $r['id'] : 0 ), 'ten' => $ten, 'nhan' => $nhan,
			'daCo' => ( isset( $r['thong_bao'] ) && false !== strpos( (string) $r['thong_bao'], 'đã có' ) ) ? 1 : 0,
			'anhXa' => ( is_array( $ax ) && ! empty( $ax['ok'] ) ) ? 1 : 0, 'thongBao' => isset( $r['thong_bao'] ) ? (string) $r['thong_bao'] : '' );
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
		unset( $r ); update_option( 'saoke_anhxa', array_values( $all ) ); self::day_ghe_gan_day_( 7 );
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
		/* Báo cho app biết đã có phiên (vào bằng vé từ Ghế) để khỏi hiện màn hỏi PIN. Cờ này chỉ
		   nói 'đã qua cửa', KHÔNG mang PIN — PIN không bao giờ được in ra HTML (§4). */
		$shim = '<script>window.SAOKE_VE_OK=' . ( self::phien_ok_() ? 'true' : 'false' ) . ';</script>'
			. '<script>(function(){var REST=' . wp_json_encode( $rest ) . ';'
			. 'function post(fn,args,s,f,u){fetch(REST+"/rpc",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({fn:fn,args:args})})'
			/* 0.52.0: máy chủ trả trang HTML (quá giờ, 5xx, tường lửa hosting) thì nói thẳng kèm mã HTTP — anh Thắng 25/09/2026
			   chỉ thấy "Unexpected token '<', \"<html><hea\"… is not valid JSON" và không biết là do đâu. */
			. '.then(function(r){return r.text().then(function(t){try{return JSON.parse(t);}catch(e){throw new Error("Máy chủ trả về trang HTML thay vì dữ liệu (HTTP "+r.status+") — thường là quá giờ xử lý hoặc tường lửa hosting chặn. Thử lại; nếu đang nạp bù thì bản này đã chia đợt nhỏ.");}});})'
			. '.then(function(d){if(d&&typeof d==="object"&&d.__err!==undefined){if(f)f(new Error(d.__err),u);return;}if(s)s(d,u);})'
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
html, body { overflow:hidden !important; }
</style>
<div id="skpMount"></div>
<script>
/* Tạo iframe THẲNG trong <body> rồi mới nạp nội dung 1 lần. KHÔNG dời phần tử đã tải — dời iframe
   đã render là trình duyệt NẠP LẠI srcdoc (đúng cái gây "tự F5" liên tục). Tạo sẵn trong body cũng
   thoát luôn ancestor có transform/filter của theme (fix app co ~640px). */
(function(){
  if ( window.__skpMounted ) { return; } window.__skpMounted = true;
  var doc = <?php echo wp_json_encode( $html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
  var f = document.createElement('iframe');
  f.title = 'Sao Kê Ngân Hàng K&H';
  f.setAttribute('style','position:fixed;inset:0;width:100vw;height:100dvh;border:0;margin:0;z-index:2147483000;background:#0b1220;');
  document.body.appendChild(f);   // iframe RỖNG, chưa có nội dung
  f.srcdoc = doc;                 // nạp 1 lần, đã nằm sẵn trong body
  try { document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden'; } catch(e){}
})();
</script>
<?php
		return ob_get_clean();
	}
}

register_activation_hook( __FILE__, function () { SAOKE_App::bao_dam_bang(); SAOKE_App::bao_dam_trang(); flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'saoke_cron_sync' ); wp_clear_scheduled_hook( 'saoke_cron_vqr' ); wp_clear_scheduled_hook( 'saoke_cron_conglog' ); } );
/* Tự cập nhật từ GitHub — hiện nút "Cập nhật" ở màn Plugin, CHỈ nâng bản, không lùi.
   Anh Thắng 15/09/2026: "tránh up lên nhiều plugin". Nạp SAU khi lớp SAOKE_App đã khai xong vì
   bộ này đọc SAOKE_App::VER để biết bản đang chạy. */
require_once __DIR__ . '/tu-cap-nhat.php';
SAOKE_TuCapNhat::init();

add_action( 'init', array( 'SAOKE_App', 'init' ), 6 );

endif; // class_exists SAOKE_App
