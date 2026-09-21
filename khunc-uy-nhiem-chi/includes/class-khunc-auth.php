<?php
/**
 * ĐĂNG NHẬP BẰNG PIN + THẺ PHIÊN + SSO từ trang tổng K&H.
 *
 * Vai trò: Admin · Kế toán · Xem.
 *   - Admin, Kế toán  → vai app 'ketoan' (nhập Excel, đánh dấu đã đi tiền; Admin thêm quản lý người dùng)
 *   - Xem             → vai app 'xem'    (chỉ tra cứu và in mẫu biểu, không sửa được gì)
 *
 * PIN lưu dạng BĂM (password_hash) — bảng bị lộ cũng không đọc ra PIN. Đăng nhập chỉ bằng PIN
 * (không tên) như app cũ, nên PIN phải là duy nhất; khi đặt PIN có kiểm trùng.
 * Thẻ phiên: 64 ký tự hex ngẫu nhiên, sống 30 ngày, gia hạn lăn khi còn dưới 7 ngày.
 * Không bao giờ lưu PIN ở máy khách.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_Auth {

	const TTL         = 2592000; // 30 ngày
	const TTL_GIA_HAN = 604800;  // 7 ngày
	const VAI = array( 'Admin', 'Kế toán', 'Xem' );

	/** Người đang gọi trong request này (đặt bởi KHUNC_API::handle). */
	private static $nguoi = null;

	public static function dat_nguoi( $u ) { self::$nguoi = $u; }
	public static function nguoi() { return self::$nguoi; }
	public static function ten() { return self::$nguoi ? (string) self::$nguoi['ten'] : ''; }
	public static function vai() { return self::$nguoi ? (string) self::$nguoi['vai'] : ''; }
	/** 'ketoan' (nhập Excel, đánh dấu) | 'xem' (chỉ tra cứu và in) | '' (chưa đăng nhập) */
	public static function vai_app( $vai = null ) {
		$v = $vai === null ? self::vai() : (string) $vai;
		if ( $v === 'Admin' || $v === 'Kế toán' ) { return 'ketoan'; }
		return $v === '' ? '' : 'xem';
	}
	public static function la_admin() { return self::vai() === 'Admin'; }

	// ---------------------------------------------------------------- người dùng

	public static function user_row( $id ) {
		global $wpdb;
		$t = KHUNC_DB::t( 'nguoi_dung' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function out_user( $r, $token = '' ) {
		if ( ! $r ) { return null; }
		$o = array(
			'id'      => (int) $r['id'],
			'ten'     => (string) $r['ten'],
			'vai'     => (string) $r['vai'],
			'vaiApp'  => self::vai_app( $r['vai'] ),
			'boPhan'  => (string) $r['bo_phan'],
			'email'   => (string) $r['email'],
			'hoatDong' => (int) $r['hoat_dong'] === 1,
		);
		if ( $token !== '' ) { $o['token'] = $token; }
		return $o;
	}

	/** Tìm người dùng đang hoạt động có PIN khớp. */
	private static function user_by_pin( $pin ) {
		global $wpdb;
		$t    = KHUNC_DB::t( 'nguoi_dung' );
		$rows = $wpdb->get_results( "SELECT * FROM $t WHERE hoat_dong=1", ARRAY_A );
		foreach ( (array) $rows as $r ) {
			if ( $r['pin_hash'] !== '' && password_verify( $pin, $r['pin_hash'] ) ) { return $r; }
		}
		return null;
	}

	/** PIN này đã có ai (khác $tru_id) dùng chưa. */
	public static function pin_trung( $pin, $tru_id = 0 ) {
		global $wpdb;
		$t    = KHUNC_DB::t( 'nguoi_dung' );
		$rows = $wpdb->get_results( "SELECT id, pin_hash FROM $t", ARRAY_A );
		foreach ( (array) $rows as $r ) {
			if ( (int) $r['id'] === (int) $tru_id ) { continue; }
			if ( $r['pin_hash'] !== '' && password_verify( $pin, $r['pin_hash'] ) ) { return true; }
		}
		return false;
	}

	// ---------------------------------------------------------------- đăng nhập

	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) { return KHUNC_Util::err( 'PIN phải gồm 4–8 chữ số' ); }
		if ( self::is_locked() ) { return KHUNC_Util::err( 'Nhập sai quá nhiều lần — thử lại sau 10 phút' ); }
		$u = self::user_by_pin( $pin );
		if ( ! $u ) {
			self::bump_fails();
			return KHUNC_Util::err( 'PIN không đúng hoặc chưa được cấp' );
		}
		self::clear_fails();
		$tok = self::issue_token( $u );
		KHUNC_Store::log_as( $u['ten'], $u['vai'], 'login', 'Đăng nhập bằng PIN' );
		return KHUNC_Util::ok( array( 'user' => self::out_user( $u, $tok ) ) );
	}

	public static function ai_dang_dang_nhap( $token = '' ) {
		global $wpdb;
		$u = self::user_by_token( $token );
		if ( ! $u ) { return KHUNC_Util::err( 'Phiên đã hết hạn', array( 'code' => 'no_session' ) ); }
		$t   = KHUNC_DB::t( 'phien' );
		$hh  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT het_han FROM $t WHERE token=%s", (string) $token ) );
		$con = $hh !== '' ? ( strtotime( $hh . ' UTC' ) - time() ) : 0;
		if ( $con < self::TTL_GIA_HAN ) {
			$wpdb->update( $t, array( 'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ) ), array( 'token' => (string) $token ) );
		}
		return KHUNC_Util::ok( array( 'user' => self::out_user( $u ) ) );
	}

	public static function logout( $token = '' ) {
		global $wpdb;
		$tok = (string) $token;
		if ( $tok === '' && self::$nguoi && isset( self::$nguoi['_token'] ) ) { $tok = self::$nguoi['_token']; }
		if ( $tok !== '' ) { $wpdb->delete( KHUNC_DB::t( 'phien' ), array( 'token' => $tok ) ); }
		return KHUNC_Util::ok();
	}

	public static function change_pin( $old, $new ) {
		global $wpdb;
		$u = self::$nguoi;
		if ( ! $u || empty( $u['id'] ) ) { return KHUNC_Util::err( 'Chưa đăng nhập' ); }
		$old = trim( (string) $old );
		$new = trim( (string) $new );
		if ( ! preg_match( '/^\d{4,8}$/', $new ) ) { return KHUNC_Util::err( 'PIN mới phải gồm 4–8 chữ số' ); }
		$row = self::user_row( $u['id'] );
		if ( ! $row || ! password_verify( $old, $row['pin_hash'] ) ) { return KHUNC_Util::err( 'PIN hiện tại không đúng' ); }
		if ( self::pin_trung( $new, $row['id'] ) ) { return KHUNC_Util::err( 'PIN này đã có người dùng khác — chọn PIN khác' ); }
		$wpdb->update( KHUNC_DB::t( 'nguoi_dung' ), array( 'pin_hash' => password_hash( $new, PASSWORD_DEFAULT ), 'sua_luc' => KHUNC_Util::now_sql() ), array( 'id' => (int) $row['id'] ) );
		KHUNC_Store::log( 'changePin', 'Đổi PIN của chính mình' );
		return KHUNC_Util::ok();
	}

	// ---------------------------------------------------------------- phiên

	public static function issue_token( $u ) {
		global $wpdb;
		self::gc( (int) $u['id'] );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( KHUNC_DB::t( 'phien' ), array(
			'token'   => $tok,
			'user_id' => (int) $u['id'],
			'ten'     => (string) $u['ten'],
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
		) );
		return $tok;
	}

	/** Người dùng của token (null nếu sai/hết hạn/đã khoá). Vai đọc lại từ bảng người dùng mỗi lượt. */
	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = KHUNC_DB::t( 'phien' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token ), ARRAY_A );
		if ( ! $r ) { return null; }
		$u = self::user_row( $r['user_id'] );
		if ( ! $u || (int) $u['hoat_dong'] !== 1 ) { return null; }
		$u['_token'] = $token;
		return $u;
	}

	private static function gc( $user_id = 0 ) {
		global $wpdb;
		$t = KHUNC_DB::t( 'phien' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		if ( $user_id ) {
			// SSO phát token mỗi lần tải trang → giữ tối đa 20 phiên/người.
			$keep = $wpdb->get_col( $wpdb->prepare( "SELECT token FROM $t WHERE user_id=%d ORDER BY het_han DESC LIMIT 20", $user_id ) );
			if ( is_array( $keep ) && count( $keep ) >= 20 ) {
				$in = implode( ',', array_map( function ( $x ) { global $wpdb; return $wpdb->prepare( '%s', $x ); }, $keep ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE user_id=%d AND token NOT IN ($in)", $user_id ) );
			}
		}
	}

	// ---------------------------------------------------------------- chống dò PIN

	private static function fail_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'x';
		return 'khunc_fail_' . md5( $ip );
	}
	private static function is_locked() { return (int) get_transient( self::fail_key() ) >= 8; }
	private static function bump_fails() {
		$n = (int) get_transient( self::fail_key() );
		set_transient( self::fail_key(), $n + 1, 600 );
	}
	private static function clear_fails() { delete_transient( self::fail_key() ); }

	// ---------------------------------------------------------------- SSO từ trang tổng K&H

	/** Bí mật SSO: của plugin này, hoặc dùng chung với plugin Vận Hành Chi Phí nếu đã khai bên đó. */
	public static function sso_secret() {
		$s = trim( (string) get_option( 'khunc_sso_secret', '' ) );
		if ( $s === '' && class_exists( 'VHCP_Auth' ) && method_exists( 'VHCP_Auth', 'sso_secret' ) ) {
			$s = (string) VHCP_Auth::sso_secret();
		}
		return $s;
	}

	/** base64url(payload).base64url(HMAC-SHA256) — cùng thuật toán với trang tổng K&H. */
	public static function verify_sso_token( $token ) {
		$secret = self::sso_secret();
		if ( ! $secret || ! $token ) { return null; }
		$parts = explode( '.', (string) $token );
		if ( count( $parts ) !== 2 ) { return null; }
		list( $p, $sig ) = $parts;
		$expect = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $p, $secret, true ) ), '+/', '-_' ), '=' );
		if ( ! hash_equals( $expect, $sig ) ) { return null; }
		$p .= str_repeat( '=', ( 4 - strlen( $p ) % 4 ) % 4 );
		$obj = json_decode( base64_decode( strtr( $p, '-_', '+/' ) ), true );
		if ( ! is_array( $obj ) || empty( $obj['x'] ) ) { return null; }
		if ( ( time() * 1000 ) > (float) $obj['x'] ) { return null; }
		return $obj;
	}

	/**
	 * Danh tính SSO → người dùng của plugin. Khớp theo email, rồi theo tên; chưa có thì TẠO
	 * với vai suy từ vai trang tổng (ADMIN→Admin, KE_TOAN→Kế toán, còn lại→Xem) và không PIN
	 * (chỉ vào được bằng SSO cho tới khi Admin cấp PIN).
	 */
	public static function resolve_sso_user( $ident ) {
		global $wpdb;
		$t     = KHUNC_DB::t( 'nguoi_dung' );
		$email = strtolower( trim( (string) ( isset( $ident['e'] ) ? $ident['e'] : '' ) ) );
		$ten   = trim( (string) ( isset( $ident['n'] ) ? $ident['n'] : '' ) );
		$hub   = (string) ( isset( $ident['r'] ) ? $ident['r'] : '' );
		$vai   = $hub === 'ADMIN' ? 'Admin' : ( $hub === 'KE_TOAN' ? 'Kế toán' : 'Xem' );
		$u = null;
		if ( $email !== '' ) { $u = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email=%s AND hoat_dong=1 LIMIT 1", $email ), ARRAY_A ); }
		if ( ! $u && $ten !== '' ) { $u = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ten=%s AND hoat_dong=1 LIMIT 1", $ten ), ARRAY_A ); }
		if ( ! $u ) {
			if ( $ten === '' ) { $ten = $email !== '' ? $email : 'Người dùng SSO'; }
			$wpdb->insert( $t, array( 'ten' => $ten, 'pin_hash' => '', 'vai' => $vai, 'email' => $email, 'hoat_dong' => 1, 'tao_luc' => KHUNC_Util::now_sql(), 'sua_luc' => KHUNC_Util::now_sql() ) );
			$u = self::user_row( $wpdb->insert_id );
			KHUNC_Store::log_as( $ten, $vai, 'sso_tao', 'Tạo người dùng từ SSO trang tổng' );
		}
		return $u;
	}
}
