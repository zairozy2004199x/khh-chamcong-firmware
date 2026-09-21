<?php
/**
 * REST API — 1 cửa: POST /wp-json/khunc/v1/call  {fn, args, token}
 * Dự phòng: admin-ajax.php (action=khunc_call) và URL app (…/uy-nhiem-chi/?khunc_api=1)
 * — cùng bộ xử lý, chỉ khác đường vào; giao diện tự đổi đường khi bị tường lửa chặn.
 *
 * Trả về {ok:true, data:{…}}; hàm trả ok:false thì đẩy nguyên (error, code…) ra ngoài.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_API {

	private static $public_fns = array( 'ping', 'login', 'aiDangDangNhap' );

	/** Hàm chỉ cho vai nhất định. Vai "Xem" không được ghi bất cứ thứ gì. */
	private static function required_roles( $fn ) {
		$admin  = array( 'dsNguoiDung', 'luuNguoiDung', 'xoaNguoiDung', 'xoaHet' );
		$ketoan = array( 'datDanhSach', 'datTheoDoi', 'xoaTheoDoi', 'datCaiDat' );
		if ( in_array( $fn, $admin, true ) ) { return array( 'Admin' ); }
		if ( in_array( $fn, $ketoan, true ) ) { return array( 'Admin', 'Kế toán' ); }
		return array();
	}

	public static function map() {
		return array(
			'ping'           => array( __CLASS__, 'ping' ),
			'login'          => array( __CLASS__, 'login' ),
			'aiDangDangNhap' => array( __CLASS__, 'ai_dang_dang_nhap' ),
			'logout'         => array( __CLASS__, 'logout' ),
			'doiPin'         => array( __CLASS__, 'doi_pin' ),
			'layTatCa'       => array( 'KHUNC_Store', 'lay_tat_ca' ),
			'datDanhSach'    => array( __CLASS__, 'dat_danh_sach' ),
			'datTheoDoi'     => array( __CLASS__, 'dat_theo_doi' ),
			'xoaTheoDoi'     => array( __CLASS__, 'xoa_theo_doi' ),
			'datCaiDat'      => array( __CLASS__, 'dat_cai_dat' ),
			'xoaHet'         => array( 'KHUNC_Store', 'xoa_het' ),
			'nhatKy'         => array( 'KHUNC_Store', 'lay_nhat_ky' ),
			'dsNguoiDung'    => array( 'KHUNC_Store', 'ds_nguoi_dung' ),
			'luuNguoiDung'   => array( __CLASS__, 'luu_nguoi_dung' ),
			'xoaNguoiDung'   => array( __CLASS__, 'xoa_nguoi_dung' ),
		);
	}

	private static function p( $a, $k, $d = null ) {
		return ( is_array( $a ) && array_key_exists( $k, $a ) ) ? $a[ $k ] : $d;
	}

	public static function ping( $a ) {
		$u = KHUNC_Auth::nguoi();
		return KHUNC_Util::ok( array(
			'version' => KHUNC_VERSION,
			'role'    => $u ? KHUNC_Auth::vai_app() : null,
			'user'    => $u ? KHUNC_Auth::out_user( $u ) : null,
			'now'     => gmdate( 'c' ),
		) );
	}
	public static function login( $a ) { return KHUNC_Auth::login( self::p( $a, 'pin', '' ) ); }
	public static function ai_dang_dang_nhap( $a ) { return KHUNC_Auth::ai_dang_dang_nhap( self::p( $a, 'token', '' ) ); }
	public static function logout( $a ) { return KHUNC_Auth::logout( self::p( $a, 'token', '' ) ); }
	public static function doi_pin( $a ) { return KHUNC_Auth::change_pin( self::p( $a, 'cu', '' ), self::p( $a, 'moi', '' ) ); }

	public static function dat_danh_sach( $a ) {
		return KHUNC_Store::dat_danh_sach(
			(array) self::p( $a, 'recs', array() ),
			self::p( $a, 'nhatKy', array() ),
			(bool) self::p( $a, 'dau', true ),
			(bool) self::p( $a, 'cuoi', true )
		);
	}
	public static function dat_theo_doi( $a ) { return KHUNC_Store::dat_theo_doi( (array) self::p( $a, 'items', array() ) ); }
	public static function xoa_theo_doi( $a ) { return KHUNC_Store::xoa_theo_doi( (array) self::p( $a, 'ids', array() ) ); }
	public static function dat_cai_dat( $a ) { return KHUNC_Store::dat_cai_dat( self::p( $a, 'congTy', null ), self::p( $a, 'tuyChon', null ) ); }
	public static function luu_nguoi_dung( $a ) { return KHUNC_Store::luu_nguoi_dung( self::p( $a, 'user', array() ) ); }
	public static function xoa_nguoi_dung( $a ) { return KHUNC_Store::xoa_nguoi_dung( self::p( $a, 'id', 0 ) ); }

	/* ---------------------------------------------------------------- cổng vào */

	public static function register_routes() {
		register_rest_route( 'khunc/v1', '/call', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'handle' ),
		) );
	}

	private static function doc_body() {
		$fn = ''; $tok = ''; $args = array();
		$raw = file_get_contents( 'php://input' );
		$j   = ( $raw !== '' && $raw !== false ) ? json_decode( (string) $raw, true ) : null;
		if ( is_array( $j ) && isset( $j['fn'] ) ) {
			$fn  = sanitize_text_field( (string) $j['fn'] );
			$tok = isset( $j['token'] ) ? sanitize_text_field( (string) $j['token'] ) : '';
			if ( isset( $j['args'] ) && is_array( $j['args'] ) ) { $args = $j['args']; }
		} else {
			$src = ! empty( $_POST ) ? $_POST : $_GET; // phpcs:ignore
			$fn  = isset( $src['fn'] ) ? sanitize_text_field( wp_unslash( $src['fn'] ) ) : '';
			$tok = isset( $src['token'] ) ? sanitize_text_field( wp_unslash( $src['token'] ) ) : '';
			if ( isset( $src['args'] ) ) {
				$tmp = json_decode( (string) wp_unslash( $src['args'] ), true );
				if ( is_array( $tmp ) ) { $args = $tmp; }
			}
		}
		return array( $fn, $args, $tok );
	}

	public static function ajax() {
		list( $fn, $args, $tok ) = self::doc_body();
		$req = new WP_REST_Request( 'POST', '/khunc/v1/call' );
		$req->set_param( 'fn', $fn ); $req->set_param( 'args', $args ); $req->set_param( 'token', $tok );
		$res = self::handle( $req );
		status_header( (int) $res->get_status() );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $res->get_data() );
		wp_die();
	}

	/** Nhận lệnh ngay trên URL app (…/uy-nhiem-chi/?khunc_api=1). */
	public static function trang() {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		list( $fn, $args, $tok ) = self::doc_body();
		if ( $fn === '' ) {
			status_header( 200 );
			echo wp_json_encode( array( 'ok' => true, 'data' => array( 'song' => true, 'ver' => KHUNC_VERSION ) ) );
			exit;
		}
		$req = new WP_REST_Request( 'POST', '/khunc/v1/call' );
		$req->set_param( 'fn', $fn ); $req->set_param( 'args', $args ); $req->set_param( 'token', $tok );
		$res = self::handle( $req );
		status_header( (int) $res->get_status() );
		echo wp_json_encode( $res->get_data() );
		exit;
	}

	public static function handle( WP_REST_Request $req ) {
		$fn   = (string) $req->get_param( 'fn' );
		$args = $req->get_param( 'args' );
		if ( ! is_array( $args ) ) { $args = array(); }
		$map = self::map();
		if ( ! isset( $map[ $fn ] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Hàm không tồn tại: ' . $fn ), 400 );
		}

		/*
		 * Request bị hosting cắt vì vượt post_max_size thì PHP giao $_POST RỖNG và KHÔNG báo lỗi
		 * — nếu cứ chạy tiếp, lệnh nhập Excel sẽ ghi một danh sách rỗng đè lên dữ liệu đang có.
		 * Nên: Content-Length lớn mà không đọc ra được gì thì dừng và nói thẳng nguyên nhân.
		 */
		$len = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $len > 0 && $fn === 'datDanhSach' && ! $args ) {
			return new WP_REST_Response( array(
				'ok'    => false,
				'error' => 'Hosting cắt mất dữ liệu gửi lên (' . size_format( $len ) . ' > post_max_size = ' .
					ini_get( 'post_max_size' ) . '). Nhờ bên hosting nâng post_max_size lên 16M.',
				'code'  => 'payload_too_large',
			), 413 );
		}

		KHUNC_Auth::dat_nguoi( null );
		$token = (string) $req->get_param( 'token' );
		if ( $token === '' ) { $token = (string) $req->get_header( 'x_khunc_token' ); }
		$user = KHUNC_Auth::user_by_token( $token );
		if ( ! $user && current_user_can( 'manage_options' ) ) {
			// Quản trị WordPress đang đăng nhập wp-admin: coi như Admin, không cần PIN.
			$wpu  = wp_get_current_user();
			$user = array(
				'id' => 0, 'ten' => $wpu && $wpu->display_name ? $wpu->display_name : 'WP Admin',
				'vai' => 'Admin', 'bo_phan' => '', 'email' => $wpu ? (string) $wpu->user_email : '', 'hoat_dong' => 1,
			);
		}
		if ( $user ) { KHUNC_Auth::dat_nguoi( $user ); }

		if ( ! in_array( $fn, self::$public_fns, true ) ) {
			if ( ! $user ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phiên đã hết — đăng nhập lại bằng PIN', 'code' => 'no_session' ), 401 );
			}
			$need = self::required_roles( $fn );
			if ( $need && ! in_array( KHUNC_Auth::vai(), $need, true ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Vai "' . KHUNC_Auth::vai() . '" không được dùng chức năng này', 'code' => 'forbidden' ), 403 );
			}
		}

		try {
			$out = call_user_func( $map[ $fn ], $args );
		} catch ( Throwable $e ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $e->getMessage() ), 500 );
		}
		if ( is_array( $out ) && isset( $out['ok'] ) && $out['ok'] === false ) {
			return new WP_REST_Response( $out, 200 );
		}
		if ( is_array( $out ) && isset( $out['ok'] ) ) { unset( $out['ok'] ); }
		return new WP_REST_Response( array( 'ok' => true, 'data' => $out ), 200 );
	}
}
