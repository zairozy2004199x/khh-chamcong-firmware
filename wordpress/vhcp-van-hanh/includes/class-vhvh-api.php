<?php
/**
 * CỔNG REST.
 *
 * =================================================================================================
 * 🔴 MỌI ĐƯỜNG ĐỀU HỎI LẠI QUYỀN, KHÔNG ĐƯỜNG NÀO TIN TRANG
 * =================================================================================================
 * `permission_callback` để `__return_true` vì thẻ của mình không phải cookie WordPress — nhưng
 * điều đó KHÔNG có nghĩa là cửa mở: hàm `cong()` đọc thẻ trước, và từng việc bên trong còn hỏi
 * `du_quyen()` một lần nữa. Ẩn nút trên màn hình không phải là chặn; ai cũng gọi thẳng API được.
 *
 * ⚠️ MỘT CỬA DUY NHẤT (`/viec`) thay vì mười lăm đường. Không phải cho gọn: mỗi đường riêng là
 *    một chỗ có thể quên khai `permission_callback` hoặc quên gọi `ai()`. Một cửa thì chốt đọc
 *    thẻ nằm đúng một chỗ, không thể quên ở chỗ thứ hai.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_API {

	const NS = 'vhvh/v1';

	public static function khoi_dong() {
		add_action( 'rest_api_init', array( __CLASS__, 'khai' ) );
	}

	public static function khai() {
		register_rest_route( self::NS, '/viec', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'cong' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function cong( $req ) {
		$d    = (array) $req->get_json_params();
		$viec = isset( $d['viec'] ) ? preg_replace( '/[^a-z_]/', '', (string) $d['viec'] ) : '';

		/* ĐĂNG NHẬP đứng TRƯỚC chốt đọc thẻ — lúc gõ PIN thì đương nhiên chưa có thẻ.
		   🔴 Mượn thẳng `VHCC_Auth::login()`: cùng một sổ PIN, cùng một bảng phiên, cùng một
		      chốt hãm sai 10 lần. Viết lại cửa đăng nhập ở đây là có hai sổ PIN, và đổi PIN
		      một nơi không đổi nơi kia. */
		if ( 'dang_nhap' === $viec ) {
			if ( ! VHVH_Auth::co_cham_cong() ) {
				return self::ra( array( 'ok' => false,
					'error' => 'Chưa cài plugin Chấm Công — trang này mượn cửa đăng nhập của nó.' ) );
			}
			$kq = VHCC_Auth::login( isset( $d['pin'] ) ? (string) $d['pin'] : '' );
			if ( empty( $kq['ok'] ) ) {
				/* Câu chối lấy NGUYÊN của chấm công (kể cả câu "thử lại sau 10 phút"), nhưng
				   không kèm thêm gì — nói rõ "PIN này có nhưng sai cơ sở" là chỉ đường cho
				   người dò PIN. */
				return self::ra( array( 'ok' => false,
					'error' => isset( $kq['error'] ) ? $kq['error'] : 'PIN không đúng.' ) );
			}
			$u2 = array(
				'ten'     => isset( $kq['name'] ) ? (string) $kq['name'] : '',
				'ma_nv'   => isset( $kq['maNV'] ) ? (string) $kq['maNV'] : '',
				'coso'    => isset( $kq['coso'] ) ? (string) $kq['coso'] : '',
				'vai'     => VHVH_Auth::doi_vai( isset( $kq['role'] ) ? $kq['role'] : '' ),
				'vai_goc' => isset( $kq['role'] ) ? (string) $kq['role'] : '',
			);
			return self::ra( array( 'ok' => true, 'the' => $kq['token'], 'toi' => self::ho_so( $u2 ) ) );
		}

		$u = VHVH_Auth::ai( $req );
		if ( ! $u ) {
			return new WP_REST_Response( array(
				'ok'    => false,
				'error' => VHVH_Auth::co_cham_cong()
					? 'Phiên đã hết hạn — đăng nhập lại.'
					: 'Chưa cài plugin Chấm Công, trang này mượn cửa đăng nhập của nó.',
				'het_phien' => true,
			), 200 );
		}

		switch ( $viec ) {
			case 'toi':
				return self::ra( array( 'ok' => true, 'toi' => self::ho_so( $u ) ) );

			case 'tong_quan':
				$tu  = isset( $d['tu'] ) ? (string) $d['tu'] : current_time( 'Y-m-01' );
				$den = isset( $d['den'] ) ? (string) $d['den'] : current_time( 'Y-m-d' );
				return self::ra( array(
					'ok'     => true,
					'so'     => VHVH_Tien::tom_tat( $u, $tu, $den ),
					'su_co'  => VHVH_SuCo::ds( $u, '', 'mo' ),
				) );

			/* ---- doanh thu & chi phí ---- */
			case 'tien_doc':
				$coso = isset( $d['coso'] ) ? (string) $d['coso'] : '';
				$ngay = isset( $d['ngay'] ) ? (string) $d['ngay'] : current_time( 'Y-m-d' );
				if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return self::ra( VHVH_Auth::choi() ); }
				return self::ra( array(
					'ok'    => true,
					'ban'   => VHVH_Tien::doc( $coso, $ngay ),
					'ds_ve' => VHVH_Tien::ds_ve(),
				) );

			case 'tien_luu':
				return self::ra( VHVH_Tien::luu( $u, $d ) );

			case 'tien_tt':
				return self::ra( VHVH_Tien::doi_tt(
					$u,
					isset( $d['coso'] ) ? (string) $d['coso'] : '',
					isset( $d['ngay'] ) ? (string) $d['ngay'] : '',
					isset( $d['lam'] ) ? (string) $d['lam'] : ''
				) );

			case 'tien_ds':
				return self::ra( array( 'ok' => true, 'ds' => VHVH_Tien::ds(
					$u,
					isset( $d['tu'] ) ? (string) $d['tu'] : '',
					isset( $d['den'] ) ? (string) $d['den'] : '',
					isset( $d['coso'] ) ? (string) $d['coso'] : ''
				) ) );

			/* ---- sự cố ---- */
			case 'su_co_ds':
				return self::ra( array( 'ok' => true, 'ds' => VHVH_SuCo::ds(
					$u,
					isset( $d['coso'] ) ? (string) $d['coso'] : '',
					isset( $d['tt'] ) ? (string) $d['tt'] : 'mo'
				) ) );

			case 'su_co_them':
				return self::ra( VHVH_SuCo::them( $u, $d ) );

			case 'su_co_tt':
				return self::ra( VHVH_SuCo::doi_tt(
					$u,
					isset( $d['id'] ) ? (int) $d['id'] : 0,
					isset( $d['lam'] ) ? (string) $d['lam'] : ''
				) );
		}
		return self::ra( array( 'ok' => false, 'error' => 'Việc không hợp lệ.' ) );
	}

	/**
	 * Hồ sơ gửi về trang.
	 *
	 * 🔴 KHÔNG GỬI THẺ NGƯỢC LẠI, KHÔNG GỬI PIN, KHÔNG GỬI DANH SÁCH NGƯỜI KÈM PIN. Màn hình này
	 *    ai mở được là chụp màn hình được, mà ảnh chụp thì đi khắp nơi.
	 */
	private static function ho_so( $u ) {
		return array(
			'ten'   => $u['ten'],
			'vai'   => $u['vai'],
			'coso'  => $u['coso'],
			'ds_coso' => self::ds_coso( $u ),
			'man'   => self::man_cua( $u ),
		);
	}

	/** Danh sách cơ sở người này được chọn. Mượn danh mục của plugin chấm công. */
	public static function ds_coso( $u ) {
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) { return array_values( $cho ); }
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ds_coso' ) ) {
			return array_values( (array) VHCC_NhanSu::ds_coso() );
		}
		return array();
	}

	/**
	 * Màn nào hiện cho vai nào.
	 *
	 * ⚠️ Đây CHỈ để vẽ thanh điều hướng. Nó không phải hàng rào — hàng rào nằm trong từng việc
	 *    ở `cong()`. Sửa bảng này mà quên sửa chốt trong việc là mở cửa mà không biết.
	 */
	public static function man_cua( $u ) {
		$man = array( 'tong_quan', 'su_co' );
		if ( VHVH_Auth::du_quyen( $u, 'thu_ngan' ) ) { $man[] = 'tien'; }
		return $man;
	}

	private static function ra( $x ) {
		return new WP_REST_Response( $x, 200 );
	}
}
