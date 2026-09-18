<?php
/**
 * REST API — 1 cửa: POST /wp-json/khbc/v1/call  {fn, args, token}
 * Dự phòng: admin-ajax.php (action=khbc_call) và URL app (…/bao-cao-chi-phi/?khbc_api=1)
 * — cùng bộ xử lý, chỉ khác đường vào; giao diện tự đổi đường khi bị tường lửa chặn.
 *
 * `args` là MỘT object (payload) — cùng hợp đồng với bản Apps Script để dùng chung app.js.
 * Trả về {ok:true, data:{…}}; hàm trả ok:false thì đẩy nguyên (error, conflict, code…) ra ngoài.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_API {

	private static $public_fns = array( 'ping', 'login', 'aiDangDangNhap' );

	/** Hàm chỉ cho vai nhất định. Nhân viên (Nhân viên) chỉ được nhóm 'nhap'. */
	private static function required_roles( $fn ) {
		$admin  = array( 'listUsers', 'saveUser', 'deleteUser', 'lockPeriod' );
		/* `fabiDoanhThu` CHỈ ĐỌC, nhưng vẫn gác ở mức Kế toán: đó là doanh thu toàn chuỗi, không
		   phải thứ để nhân viên nhập chi phí mở ra xem. */
		$ketoan = array( 'saveState', 'setCostStatus', 'setAllStatus', 'listPeriods', 'getLog', 'fabiDoanhThu', 'gheDoanhThu', 'nsLuong' );
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
			'changePin'      => array( __CLASS__, 'change_pin' ),
			'getPeriod'      => array( __CLASS__, 'get_period' ),
			'listPeriods'    => array( __CLASS__, 'list_periods' ),
			'saveState'      => array( __CLASS__, 'save_state' ),
			'upsertCost'     => array( __CLASS__, 'upsert_cost' ),
			'upsertCosts'    => array( __CLASS__, 'upsert_costs' ),
			'deleteCost'     => array( __CLASS__, 'delete_cost' ),
			'setCostStatus'  => array( __CLASS__, 'set_cost_status' ),
			'setAllStatus'   => array( __CLASS__, 'set_all_status' ),
			'lockPeriod'     => array( __CLASS__, 'lock_period' ),
			'uploadFile'     => array( __CLASS__, 'upload_file' ),
			'listUsers'      => array( 'KHBC_Store', 'list_users' ),
			'saveUser'       => array( __CLASS__, 'save_user' ),
			'deleteUser'     => array( __CLASS__, 'delete_user' ),
			'getLog'         => array( 'KHBC_Store', 'get_log' ),
			'fabiDoanhThu'   => array( __CLASS__, 'fabi_doanh_thu' ),
			'gheDoanhThu'    => array( __CLASS__, 'ghe_doanh_thu' ),
			'nsLuong'        => array( __CLASS__, 'ns_luong' ),
		);
	}

	// ---- bọc payload → tham số
	private static function p( $a, $k, $d = null ) { return ( is_array( $a ) && array_key_exists( $k, $a ) ) ? $a[ $k ] : $d; }
	/**
	 * Doanh thu theo cửa hàng, đọc từ plugin Doanh thu FABi — anh Thắng 18/09/2026:
	 * *"lấy đẩy doanh thu từ doanh thu hcm sang báo cáo tổng"*.
	 *
	 * Nhận kỳ (thang/nam) hoặc khoảng ngày (tu/den). CHỈ ĐỌC và CHỈ TRẢ SỐ — việc ghép cửa hàng
	 * nào vào điểm nào là của giao diện, và người dùng phải nhìn thấy trước khi số chạy vào báo
	 * cáo. Tự ghép rồi tự ghi là một ngày nào đó doanh thu nhảy vào nhầm bộ phận mà không ai biết.
	 */
	public static function fabi_doanh_thu( $a ) {
		$a = is_array( $a ) ? $a : array();
		if ( ! empty( $a['tu'] ) || ! empty( $a['den'] ) ) {
			return KHBC_FABi::theo_cua_hang(
				isset( $a['tu'] ) ? (string) $a['tu'] : '',
				isset( $a['den'] ) ? (string) $a['den'] : ''
			);
		}
		return KHBC_FABi::theo_ky(
			isset( $a['thang'] ) ? (int) $a['thang'] : 0,
			isset( $a['nam'] ) ? (int) $a['nam'] : 0
		);
	}

	/** Doanh thu theo cơ sở, đọc từ plugin Ghế Massage. Cùng hình dạng trả về với fabi_doanh_thu()
	 *  để giao diện dùng chung một đường — xem KHBC_Ghe. */
	public static function ghe_doanh_thu( $a ) {
		$a = is_array( $a ) ? $a : array();
		if ( ! empty( $a['tu'] ) || ! empty( $a['den'] ) ) {
			return KHBC_Ghe::theo_co_so(
				isset( $a['tu'] ) ? (string) $a['tu'] : '',
				isset( $a['den'] ) ? (string) $a['den'] : ''
			);
		}
		return KHBC_Ghe::theo_ky(
			isset( $a['thang'] ) ? (int) $a['thang'] : 0,
			isset( $a['nam'] ) ? (int) $a['nam'] : 0
		);
	}

	/** Lương theo cơ sở, đọc từ plugin Chấm công — xem KHBC_NhanSu. */
	public static function ns_luong( $a ) {
		$a = is_array( $a ) ? $a : array();
		return KHBC_NhanSu::theo_ky(
			isset( $a['thang'] ) ? (int) $a['thang'] : 0,
			isset( $a['nam'] ) ? (int) $a['nam'] : 0
		);
	}

	public static function ping( $a ) {
		$u = KHBC_Auth::nguoi();
		return KHBC_Util::ok( array( 'version' => KHBC_VERSION, 'role' => $u ? KHBC_Auth::vai_app() : null, 'user' => $u ? KHBC_Auth::out_user( $u ) : null, 'now' => gmdate( 'c' ) ) );
	}
	public static function login( $a ) { return KHBC_Auth::login( self::p( $a, 'pin', '' ) ); }
	public static function ai_dang_dang_nhap( $a ) { return KHBC_Auth::ai_dang_dang_nhap( self::p( $a, 'token', '' ) ); }
	public static function logout( $a ) { return KHBC_Auth::logout( self::p( $a, 'token', '' ) ); }
	public static function change_pin( $a ) { return KHBC_Auth::change_pin( self::p( $a, 'old', '' ), self::p( $a, 'new', '' ) ); }
	public static function get_period( $a ) { return KHBC_Store::get_period( self::p( $a, 'period', '' ) ); }
	public static function list_periods( $a ) { return KHBC_Util::ok( array( 'periods' => KHBC_Store::list_periods() ) ); }
	public static function save_state( $a ) { return KHBC_Store::save_state( self::p( $a, 'period', '' ), self::p( $a, 'state', array() ), self::p( $a, 'version', null ) ); }
	public static function upsert_cost( $a ) { return KHBC_Store::upsert_costs( array( self::p( $a, 'item', array() ) ) ); }
	public static function upsert_costs( $a ) { return KHBC_Store::upsert_costs( (array) self::p( $a, 'items', array() ) ); }
	public static function delete_cost( $a ) { return KHBC_Store::delete_cost( self::p( $a, 'id', '' ) ); }
	public static function set_cost_status( $a ) { return KHBC_Store::set_cost_status( self::p( $a, 'id', '' ), self::p( $a, 'status', '' ) ); }
	public static function set_all_status( $a ) { return KHBC_Store::set_all_status( self::p( $a, 'period', '' ), self::p( $a, 'status', '' ) ); }
	public static function lock_period( $a ) { return KHBC_Store::lock_period( self::p( $a, 'period', '' ), (bool) self::p( $a, 'locked', true ) ); }
	public static function upload_file( $a ) { return KHBC_Store::upload_file( self::p( $a, 'file', array() ), self::p( $a, 'period', '' ) ); }
	public static function save_user( $a ) { return KHBC_Store::save_user( self::p( $a, 'user', array() ) ); }
	public static function delete_user( $a ) { return KHBC_Store::delete_user( self::p( $a, 'id', 0 ) ); }

	// ---------------------------------------------------------------- cổng

	public static function register_routes() {
		register_rest_route( 'khbc/v1', '/call', array(
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
		$req = new WP_REST_Request( 'POST', '/khbc/v1/call' );
		$req->set_param( 'fn', $fn ); $req->set_param( 'args', $args ); $req->set_param( 'token', $tok );
		$res = self::handle( $req );
		status_header( (int) $res->get_status() );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $res->get_data() );
		wp_die();
	}

	/** Nhận lệnh ngay trên URL app (…/bao-cao-chi-phi/?khbc_api=1). */
	public static function trang() {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		list( $fn, $args, $tok ) = self::doc_body();
		if ( $fn === '' ) {
			status_header( 200 );
			echo wp_json_encode( array( 'ok' => true, 'data' => array( 'song' => true, 'ver' => KHBC_VERSION ) ) );
			exit;
		}
		$req = new WP_REST_Request( 'POST', '/khbc/v1/call' );
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
		KHBC_Auth::dat_nguoi( null );
		$token = (string) $req->get_param( 'token' );
		if ( $token === '' ) { $token = (string) $req->get_header( 'x_khbc_token' ); }
		$user = KHBC_Auth::user_by_token( $token );
		if ( ! $user && current_user_can( 'manage_options' ) ) {
			// Quản trị WordPress đang đăng nhập wp-admin: coi như Admin, không cần PIN.
			$wpu  = wp_get_current_user();
			$user = array( 'id' => 0, 'ten' => $wpu && $wpu->display_name ? $wpu->display_name : 'WP Admin', 'vai' => 'Admin', 'bo_phan' => '', 'email' => $wpu ? (string) $wpu->user_email : '', 'hoat_dong' => 1 );
		}
		if ( $user ) { KHBC_Auth::dat_nguoi( $user ); }
		if ( ! in_array( $fn, self::$public_fns, true ) ) {
			if ( ! $user ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phiên đã hết — đăng nhập lại bằng PIN', 'code' => 'no_session' ), 401 );
			}
			$need = self::required_roles( $fn );
			if ( $need && ! in_array( KHBC_Auth::vai(), $need, true ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Vai "' . KHBC_Auth::vai() . '" không được dùng chức năng này', 'code' => 'forbidden' ), 403 );
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
