<?php
/**
 * CỔNG PIN — dùng chung tài khoản với plugin Vận hành chi phí.
 *
 * NGUỒN NGƯỜI DÙNG (quyết định một lần, hiện rõ trong Cài đặt, không tự đổi ngầm):
 *   'chung' — đọc bảng cấu hình của plugin Vận hành chi phí (`{prefix}vhcp_cfg`, hàng
 *             `CH_NguoiDung`). Thêm/sửa/xoá nhân sự vẫn làm ở tab ⚙️ Cấu hình bên đó, khai
 *             một lần dùng cho cả hai hệ thống.
 *   'rieng' — plugin này tự giữ danh sách trong option `vhd_nguoidung` (dùng khi cài một
 *             mình trên site không có plugin chi phí).
 *
 * Chọn 'chung' mà bảng kia không có thì BÁO LỖI RÕ, không âm thầm rơi về 'rieng': đổi nguồn
 * danh tính trong im lặng là kiểu lỗi tệ nhất của một cổng đăng nhập.
 *
 * PHIÊN: token riêng của plugin này (bảng vhd_session), KHÔNG dùng lại token của app chi phí —
 * hai hệ thống riêng thì thu hồi phiên bên này không được kéo bên kia xuống theo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_Auth {

	const TTL = 2592000;   // 30 ngày

	/** Vai trò được vào thư viện hợp đồng. Nhân viên KHÔNG được — hợp đồng mang giá và điều khoản. */
	const VAI_TRO_VAO = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' );

	public static function nguon() {
		$n = get_option( 'vhd_nguon_nguoidung' );
		return ( $n === 'rieng' ) ? 'rieng' : 'chung';
	}

	/** Bảng cấu hình của plugin Vận hành chi phí có tồn tại không? */
	public static function co_bang_chung() {
		global $wpdb;
		$t = $wpdb->prefix . 'vhcp_cfg';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * Danh sách người dùng: [ ['ten','pin','vaiTro','coso'], … ]
	 *
	 * @return array|WP_Error
	 */
	public static function users() {
		global $wpdb;
		if ( self::nguon() === 'rieng' ) {
			$ds  = get_option( 'vhd_nguoidung' );
			$out = array();
			foreach ( (array) $ds as $u ) {
				$u = (array) $u;
				if ( trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) ) === '' ) { continue; }
				$out[] = array(
					'ten'    => (string) $u['ten'],
					'pin'    => (string) ( isset( $u['pin'] ) ? $u['pin'] : '' ),
					'vaiTro' => (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : 'Kế toán cá nhân' ),
					'coso'   => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
				);
			}
			return $out;
		}

		if ( ! self::co_bang_chung() ) {
			return new WP_Error( 'thieu_bang', 'Đang đặt nguồn người dùng là "dùng chung với Vận hành chi phí" '
				. 'nhưng không thấy bảng ' . $wpdb->prefix . 'vhcp_cfg. Vào Cài đặt → chuyển sang '
				. '"danh sách riêng của plugin này", hoặc cài lại plugin Vận hành chi phí.' );
		}

		// Bảng cfg lưu mỗi hàng sheet thành 1 dòng JSON: [Tên, PIN, Vai trò, Cơ sở, …]
		$t    = $wpdb->prefix . 'vhcp_cfg';
		$rows = VHD_DB::rows( $wpdb->prepare(
			"SELECT cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", 'CH_NguoiDung'
		) );
		$out = array();
		foreach ( $rows as $r ) {
			$a = json_decode( (string) $r['cols'], true );
			if ( ! is_array( $a ) ) { continue; }
			$ten = isset( $a[0] ) ? trim( (string) $a[0] ) : '';
			if ( $ten === '' ) { continue; }
			$out[] = array(
				'ten'    => $ten,
				'pin'    => isset( $a[1] ) ? trim( (string) $a[1] ) : '',
				'vaiTro' => isset( $a[2] ) ? trim( (string) $a[2] ) : '',
				'coso'   => isset( $a[3] ) ? trim( (string) $a[3] ) : '',
			);
		}
		return $out;
	}

	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số' );
		}
		if ( self::bi_khoa() ) {
			return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút' );
		}

		$users = self::users();
		if ( is_wp_error( $users ) ) {
			return array( 'ok' => false, 'error' => $users->get_error_message() );
		}

		foreach ( $users as $u ) {
			if ( $u['pin'] === '' || $u['pin'] !== $pin ) { continue; }
			self::xoa_dem_sai();
			$role = $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên';
			if ( ! in_array( $role, self::VAI_TRO_VAO, true ) ) {
				// Nói rõ là "không đủ quyền", không nói "PIN sai": PIN đúng mà báo sai thì người
				// dùng gõ lại mười lần rồi tự khoá mình.
				return array(
					'ok'    => false,
					'error' => 'Tài khoản ' . $u['ten'] . ' (' . $role . ') không được xem thư viện hợp đồng. '
						. 'Chỉ Kế toán, Quản lý và Admin vào được.',
				);
			}
			return array(
				'ok'    => true,
				'name'  => $u['ten'],
				'role'  => $role,
				'coso'  => $u['coso'],
				'token' => self::phat_token( $u['ten'], $role, $u['coso'] ),
			);
		}

		self::dem_sai();
		return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp' );
	}

	public static function phat_token( $ten, $role, $coso ) {
		global $wpdb;
		$t = VHD_DB::t( 'session' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( $t, array(
			'token'   => $tok,
			'ten'     => (string) $ten,
			'vai_tro' => (string) $role,
			'coso'    => (string) $coso,
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
		) );
		return $tok;
	}

	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = VHD_DB::t( 'session' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token
		), ARRAY_A );
		if ( ! $r ) { return null; }
		if ( ! in_array( (string) $r['vai_tro'], self::VAI_TRO_VAO, true ) ) { return null; }
		return array( 'name' => $r['ten'], 'role' => $r['vai_tro'], 'coso' => $r['coso'] );
	}

	public static function logout( $token ) {
		global $wpdb;
		$wpdb->delete( VHD_DB::t( 'session' ), array( 'token' => (string) $token ) );
		return array( 'success' => true );
	}

	// ------------------------------------------------------------ hãm thử PIN
	private static function khoa_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhd_fail_' . md5( $ip );
	}
	private static function bi_khoa()      { return (int) get_transient( self::khoa_key() ) >= 10; }
	private static function dem_sai()      { $k = self::khoa_key(); set_transient( $k, (int) get_transient( $k ) + 1, 600 ); }
	private static function xoa_dem_sai()  { delete_transient( self::khoa_key() ); }
	public static function mo_khoa()       { delete_transient( self::khoa_key() ); }
}
