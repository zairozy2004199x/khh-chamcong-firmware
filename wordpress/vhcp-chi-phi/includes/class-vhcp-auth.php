<?php
/**
 * ĐĂNG NHẬP — PIN 4 số như app cũ, thêm 2 thứ Apps Script không có:
 *   1) phiên có token: mọi lệnh gọi API (trừ `login`) phải kèm token còn hạn;
 *   2) hãm thử PIN: quá 10 lần sai trong 10 phút từ 1 IP thì chặn tạm.
 *
 * PIN vẫn lưu nguyên văn trong bảng cấu hình vì tab "⚙️ Cấu hình" của giao diện
 * hiện & sửa PIN từng người (giữ đúng cách vận hành cũ). Xem phần "Bảo mật" trong
 * docs/HUONG-DAN-CAI-DAT-WORDPRESS.md nếu muốn siết thêm.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Auth {

	const TTL = 2592000;   // 30 ngày — giao diện nhớ phiên trong sessionStorage/localStorage

	/** login(pin) */
	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4}$/', $pin ) ) { return array( 'ok' => false, 'error' => 'PIN phải gồm 4 chữ số' ); }
		if ( self::is_locked() ) { return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút' ); }

		foreach ( VHCP_Cfg::get_users() as $u ) {   // get_users() tự seed nếu cấu hình còn trống
			if ( trim( (string) $u['pin'] ) === $pin ) {
				self::clear_fails();
				$tok = self::issue_token( $u['ten'], ( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ), $u['coso'], $u['boPhan'] );
				return array(
					'ok'     => true,
					'name'   => $u['ten'],
					'role'   => ( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ),
					'coso'   => $u['coso'],
					'boPhan' => $u['boPhan'],
					'token'  => $tok,
				);
			}
		}
		self::bump_fails();
		return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp' );
	}

	/** changePin(name, oldPin, newPin) */
	public static function change_pin( $name, $old, $new ) {
		$name = trim( (string) $name );
		$old  = trim( (string) $old );
		$new  = trim( (string) $new );
		if ( ! preg_match( '/^\d{4}$/', $new ) ) { return VHCP_Util::err( 'PIN mới phải gồm 4 chữ số' ); }
		VHCP_Cfg::seed();
		$rows = VHCP_Cfg::read( VHCP_Cfg::USER );
		$my   = -1;
		foreach ( $rows as $i => $r ) {
			if ( trim( (string) $r[0] ) === $name && trim( (string) $r[1] ) === $old ) { $my = $i; break; }
		}
		if ( $my < 0 ) { return VHCP_Util::err( 'PIN hiện tại không đúng' ); }
		foreach ( $rows as $j => $r ) {
			if ( $j !== $my && trim( (string) $r[1] ) === $new ) { return VHCP_Util::err( 'PIN này đã có người dùng khác — chọn PIN khác' ); }
		}
		VHCP_Cfg::set_cell( VHCP_Cfg::USER, $my, 1, $new );
		return VHCP_Util::ok();
	}

	// ---------------------------------------------------------------- phiên

	public static function issue_token( $ten, $role, $coso, $bo_phan ) {
		global $wpdb;
		self::gc( $ten );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( VHCP_DB::t( 'session' ), array(
			'token'   => $tok,
			'ten'     => (string) $ten,
			'vai_tro' => (string) $role,
			'coso'    => (string) $coso,
			'bo_phan' => (string) $bo_phan,
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
		) );
		return $tok;
	}

	/** Người dùng của token (null nếu sai/hết hạn). */
	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = VHCP_DB::t( 'session' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token ), ARRAY_A );
		if ( ! $r ) { return null; }
		return array( 'name' => $r['ten'], 'role' => $r['vai_tro'], 'coso' => $r['coso'], 'boPhan' => $r['bo_phan'] );
	}

	public static function logout( $token ) {
		global $wpdb;
		$wpdb->delete( VHCP_DB::t( 'session' ), array( 'token' => (string) $token ) );
		return VHCP_Util::ok();
	}

	private static function gc( $ten = '' ) {
		global $wpdb;
		$t = VHCP_DB::t( 'session' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		// SSO phát token mỗi lần tải trang -> giữ tối đa 20 phiên còn sống cho mỗi người.
		if ( $ten !== '' ) {
			$keep = $wpdb->get_col( $wpdb->prepare( "SELECT token FROM $t WHERE ten=%s ORDER BY het_han DESC LIMIT 20", (string) $ten ) );
			if ( is_array( $keep ) && count( $keep ) >= 20 ) {
				$in = implode( ',', array_map( array( __CLASS__, 'quote_token' ), $keep ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE ten=%s AND token NOT IN ($in)", (string) $ten ) );
			}
		}
	}

	private static function quote_token( $t ) {
		return "'" . preg_replace( '/[^0-9a-f]/', '', (string) $t ) . "'";
	}

	// ---------------------------------------------------------------- hãm thử PIN

	private static function fail_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcp_fail_' . md5( $ip );
	}

	private static function is_locked() {
		return (int) get_transient( self::fail_key() ) >= 10;
	}

	private static function bump_fails() {
		$k = self::fail_key();
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
	}

	private static function clear_fails() {
		delete_transient( self::fail_key() );
	}

	// ---------------------------------------------------------------- SSO từ trang tổng K&H

	public static function sso_secret() {
		$s = VHCP_Meta::get( 'SSO_SECRET' );
		if ( ! $s ) { $s = get_option( 'vhcp_sso_secret' ); }
		return $s ? $s : '';
	}

	/**
	 * verifySsoToken(): base64url(payload).base64url(HMAC-SHA256) — cùng thuật toán
	 * với app Apps Script cũ nên trang tổng K&H không phải sửa gì.
	 */
	public static function verify_sso_token( $token ) {
		$secret = self::sso_secret();
		if ( ! $secret || ! $token ) { return null; }
		$parts = explode( '.', (string) $token );
		if ( count( $parts ) !== 2 ) { return null; }
		list( $p, $sig ) = $parts;
		$expect = self::b64url( hash_hmac( 'sha256', $p, $secret, true ) );
		if ( ! hash_equals( $expect, $sig ) ) { return null; }
		$json = self::b64url_decode( $p );
		$obj  = json_decode( $json, true );
		if ( ! is_array( $obj ) || empty( $obj['x'] ) ) { return null; }
		if ( ( time() * 1000 ) > (float) $obj['x'] ) { return null; }
		return $obj;
	}

	/** resolveSsoUser(): vai trò trang tổng → vai trò Chi Phí, có bảng override theo email. */
	public static function resolve_sso_user( $ident ) {
		$email    = trim( (string) ( isset( $ident['e'] ) ? $ident['e'] : '' ) );
		$branches = isset( $ident['b'] ) ? $ident['b'] : '';
		$branches = is_array( $branches ) ? implode( ', ', $branches ) : (string) $branches;
		$hub      = (string) ( isset( $ident['r'] ) ? $ident['r'] : '' );

		if ( $hub === 'ADMIN' )                { $role = 'Admin'; }
		elseif ( $hub === 'QUAN_LY' )          { $role = 'Quản lý'; }
		elseif ( $hub === 'CUA_HANG_TRUONG' )  { $role = 'Nhân viên'; }
		elseif ( $hub === 'KE_TOAN' )          { $role = 'Kế toán cá nhân'; }
		else                                   { $role = 'Nhân viên'; }

		$coso = $branches;
		$ov   = self::sso_overrides();
		$k    = strtolower( $email );
		if ( isset( $ov[ $k ] ) ) {
			if ( ! empty( $ov[ $k ]['role'] ) ) { $role = $ov[ $k ]['role']; }
			if ( ! empty( $ov[ $k ]['coso'] ) ) { $coso = $ov[ $k ]['coso']; }
		}
		return array( 'name' => (string) ( isset( $ident['n'] ) ? $ident['n'] : '' ), 'role' => $role, 'coso' => $coso );
	}

	private static function sso_overrides() {
		$hit = get_transient( 'vhcp_ssomap' );
		if ( is_array( $hit ) ) { return $hit; }
		$out = array();
		foreach ( VHCP_Cfg::read( VHCP_Cfg::SSO ) as $r ) {
			$em = strtolower( trim( (string) $r[0] ) );
			if ( $em === '' ) { continue; }
			$out[ $em ] = array( 'role' => trim( (string) $r[1] ), 'coso' => trim( (string) $r[2] ) );
		}
		set_transient( 'vhcp_ssomap', $out, 300 );
		return $out;
	}

	private static function b64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( $s ) {
		$s .= str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 );
		return base64_decode( strtr( $s, '-_', '+/' ) );
	}
}
