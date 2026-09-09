<?php
/**
 * Plugin Name:       Sao Kê Ngân Hàng K&H (SePay)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Sao kê & đối soát dòng tiền ngân hàng qua SePay (webhook + Open API). Trang [posh_saoke] bảo vệ bằng PIN. ĐỘC LẬP với plugin vé/ghế.
 * Version:           0.1.0
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
	const VER_TBL = '1';

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

	public static function bao_dam_bang() {
		if ( get_option( 'saoke_tbl' ) === self::VER_TBL ) { return; }
		global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tbl = self::tbl(); $col = $wpdb->get_charset_collate();
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
	}

	/* ── Webhook SePay: nhận 1 giao dịch, chống trùng bằng sepay_id ── */
	public static function r_webhook( $req ) {
		$key = (string) get_option( 'saoke_webhook_key', '' );
		if ( '' === $key || ! hash_equals( $key, (string) $req->get_param( 'key' ) ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'sai key' ), 401 );
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

	// ───────────────────────────── Helpers ngày ─────────────────────────────
	private static function vn2ymd( $s ) { $s = trim( $s ); if ( ! preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m ) ) { return ''; } return $m[3] . '-' . $m[2] . '-' . $m[1]; }
	private static function ymd2vn( $s ) { $s = trim( (string) $s ); if ( '' === $s ) { return ''; } $t = strtotime( $s ); return $t ? gmdate( 'd/m/Y H:i:s', $t ) : $s; }

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
})();
</script>
<?php
		return ob_get_clean();
	}
}

register_activation_hook( __FILE__, function () { SAOKE_App::bao_dam_bang(); SAOKE_App::bao_dam_trang(); flush_rewrite_rules(); } );
add_action( 'init', array( 'SAOKE_App', 'init' ), 6 );

endif; // class_exists SAOKE_App
