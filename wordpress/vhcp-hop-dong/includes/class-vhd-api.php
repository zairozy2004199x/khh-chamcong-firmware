<?php
/**
 * MỘT CỬA cho giao diện: POST /wp-json/vhd/v1/call  {fn, args:[…], token}
 *
 * Mọi lệnh (trừ login) phải kèm token phiên còn hạn và vai trò được vào thư viện hợp đồng.
 * Đây là chỗ DUY NHẤT chặn quyền — không tin giao diện, vì giao diện gốc của app Apps Script
 * vốn không có khái niệm đăng nhập (cả app nằm sau một link /exec).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_API {

	/** Hàm chạy được khi CHƯA đăng nhập. */
	private static $cong_khai = array( 'login' );

	/** Hàm plugin tự xử, không chuyển sang Apps Script. */
	private static $noi_bo = array( 'login', 'vhdLogout' );

	public static function register_routes() {
		register_rest_route( 'vhd/v1', '/call', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'handle' ),
		) );
	}

	public static function ajax() {
		$fn   = isset( $_POST['fn'] ) ? sanitize_text_field( wp_unslash( $_POST['fn'] ) ) : '';
		$tok  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$args = array();
		if ( isset( $_POST['args'] ) ) {
			$tmp = json_decode( (string) wp_unslash( $_POST['args'] ), true );
			if ( is_array( $tmp ) ) { $args = $tmp; }
		}
		$res = self::chay( $fn, $args, $tok );
		status_header( (int) $res['status'] );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $res['body'] );
		wp_die();
	}

	/** Nhận lệnh ngay trên URL của trang (…/hop-dong/?vhd_api=1) — đường dự phòng khi hosting chặn /wp-json/. */
	public static function trang() {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );

		$fn = ''; $tok = ''; $args = array();
		$raw = file_get_contents( 'php://input' );
		$j   = ( $raw !== '' && $raw !== false ) ? json_decode( (string) $raw, true ) : null;
		if ( is_array( $j ) ) {
			$fn  = isset( $j['fn'] ) ? sanitize_text_field( (string) $j['fn'] ) : '';
			$tok = isset( $j['token'] ) ? sanitize_text_field( (string) $j['token'] ) : '';
			if ( isset( $j['args'] ) && is_array( $j['args'] ) ) { $args = $j['args']; }
		} else {
			$fn  = isset( $_POST['fn'] ) ? sanitize_text_field( wp_unslash( $_POST['fn'] ) ) : '';
			$tok = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			if ( isset( $_POST['args'] ) ) {
				$tmp = json_decode( (string) wp_unslash( $_POST['args'] ), true );
				if ( is_array( $tmp ) ) { $args = $tmp; }
			}
		}
		if ( $fn === '' ) {
			status_header( 200 );
			echo wp_json_encode( array( 'ok' => true, 'data' => array( 'song' => true, 'ver' => VHD_VERSION ) ) );
			exit;
		}
		$res = self::chay( $fn, $args, $tok );
		status_header( (int) $res['status'] );
		echo wp_json_encode( $res['body'] );
		exit;
	}

	public static function handle( WP_REST_Request $req ) {
		$args = $req->get_param( 'args' );
		if ( ! is_array( $args ) ) { $args = array(); }
		$tok = (string) $req->get_param( 'token' );
		if ( $tok === '' ) { $tok = (string) $req->get_header( 'x_vhd_token' ); }
		$res = self::chay( (string) $req->get_param( 'fn' ), $args, $tok );
		return new WP_REST_Response( $res['body'], (int) $res['status'] );
	}

	private static function chay( $fn, $args, $token ) {
		$fn   = (string) $fn;
		$args = array_values( (array) $args );

		if ( ! in_array( $fn, self::$cong_khai, true ) ) {
			$user = VHD_Auth::user_by_token( $token );
			if ( ! $user && ! current_user_can( 'manage_options' ) ) {
				return array( 'status' => 401, 'body' => array(
					'ok' => false, 'error' => 'Phiên đã hết — đăng nhập lại bằng PIN', 'code' => 'no_session',
				) );
			}
		}

		if ( $fn === 'login' ) {
			$r = VHD_Auth::login( isset( $args[0] ) ? $args[0] : '' );
			return array( 'status' => 200, 'body' => array( 'ok' => true, 'data' => $r ) );
		}
		if ( $fn === 'vhdLogout' ) {
			return array( 'status' => 200, 'body' => array( 'ok' => true, 'data' => VHD_Auth::logout( $token ) ) );
		}

		// Còn lại: chuyển sang app gốc. Danh sách hàm được phép do CauNoi.gs quyết định — để một
		// chỗ duy nhất, khỏi phải khai hai nơi rồi lệch nhau.
		$r = VHD_CauNoi::goi( $fn, $args );
		if ( empty( $r['ok'] ) ) {
			return array( 'status' => 200, 'body' => array( 'ok' => false, 'error' => $r['error'] ) );
		}
		return array( 'status' => 200, 'body' => array( 'ok' => true, 'data' => $r['data'] ) );
	}
}
