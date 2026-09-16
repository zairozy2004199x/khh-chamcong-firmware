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
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $tu ) ) { $tu = current_time( 'Y-m-01' ); }
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $den ) ) { $den = current_time( 'Y-m-d' ); }
				return self::ra( array(
					'ok'    => true,
					'so'    => VHVH_Tong::so( $u, $tu, $den ),
					'su_co' => VHVH_SuCo::ds( $u, '', 'mo' ),
					'hnay'  => current_time( 'Y-m-d' ),
				) );

			case 'dat_chi_tieu':
				return self::ra( VHVH_Tong::dat_chi_tieu(
					$u,
					isset( $d['coso'] ) ? (string) $d['coso'] : '',
					isset( $d['so'] ) ? $d['so'] : 0
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

			/* ---- checklist ---- */
			case 'cl_doc':
				$coso = isset( $d['coso'] ) ? (string) $d['coso'] : '';
				$ngay = isset( $d['ngay'] ) ? (string) $d['ngay'] : current_time( 'Y-m-d' );
				$buoi = isset( $d['buoi'] ) ? (string) $d['buoi'] : 'dau_ngay';
				if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return self::ra( VHVH_Auth::choi() ); }
				return self::ra( array(
					'ok'  => true,
					'ban' => VHVH_Checklist::doc( $coso, $ngay, $buoi ),
					'dm'  => VHVH_Checklist::danh_muc( $coso ),
				) );

			case 'cl_luu':
				return self::ra( VHVH_Checklist::luu( $u, $d ) );

			/* ---- kiểm kho ---- */
			case 'kho_doc':
				$coso = isset( $d['coso'] ) ? (string) $d['coso'] : '';
				$ngay = isset( $d['ngay'] ) ? (string) $d['ngay'] : current_time( 'Y-m-d' );
				if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return self::ra( VHVH_Auth::choi() ); }
				return self::ra( array(
					'ok'      => true,
					'ban'     => VHVH_Kho::doc( $coso, $ngay ),
					'dm'      => VHVH_Kho::danh_muc( $coso ),
					'tuan_tu' => VHVH_Kho::dau_tuan( $ngay ),
					'so_sanh' => VHVH_Kho::so_sanh( $coso, $ngay ),
				) );

			case 'kho_luu':
				return self::ra( VHVH_Kho::luu( $u, $d ) );

			/* ---- sự cố ---- */
			case 'su_co_ds':
				return self::ra( array( 'ok' => true, 'ds' => VHVH_SuCo::ds(
					$u,
					isset( $d['coso'] ) ? (string) $d['coso'] : '',
					isset( $d['tt'] ) ? (string) $d['tt'] : 'mo'
				) ) );

			case 'su_co_them':
				return self::ra( VHVH_SuCo::them( $u, $d ) );

			/* ---- đánh giá nhân viên ---- */
			case 'dg_ds':
				return self::ra( array( 'ok' => true,
					'ds'  => VHVH_DanhGia::ds( $u,
						isset( $d['ky'] ) ? (string) $d['ky'] : '',
						isset( $d['coso'] ) ? (string) $d['coso'] : '' ),
					'xep' => VHVH_DanhGia::xep_loai( $u,
						isset( $d['ky'] ) ? (string) $d['ky'] : '',
						isset( $d['coso'] ) ? (string) $d['coso'] : '' ),
					'dm'      => VHVH_DanhGia::danh_muc(),
					'dm_khen' => VHVH_DanhGia::khen_mac_dinh(),
					'ky'      => current_time( 'Y-m' ),
				) );

			case 'dg_ghi':
				return self::ra( VHVH_DanhGia::ghi( $u, $d ) );

			case 'dg_xoa':
				return self::ra( VHVH_DanhGia::xoa( $u, isset( $d['id'] ) ? (int) $d['id'] : 0 ) );

			/* ---- tiktok ---- */
			case 'tk_ds':
				return self::ra( array( 'ok' => true,
					'ds'  => VHVH_KD::tk_ds( $u, isset( $d['ky'] ) ? (string) $d['ky'] : '',
						isset( $d['coso'] ) ? (string) $d['coso'] : '' ),
					'bac' => VHVH_KD::bac(),
					'ky'  => current_time( 'Y-m' ),
				) );
			case 'tk_them': return self::ra( VHVH_KD::tk_them( $u, $d ) );
			case 'tk_luot':
				return self::ra( VHVH_KD::tk_luot( $u, isset( $d['id'] ) ? (int) $d['id'] : 0,
					isset( $d['luot'] ) ? $d['luot'] : 0 ) );
			case 'tk_chot':
				return self::ra( VHVH_KD::tk_chot( $u, isset( $d['id'] ) ? (int) $d['id'] : 0,
					! empty( $d['chot'] ) ) );

			/* ---- trích cam ---- */
			case 'tc_ds':
				return self::ra( array( 'ok' => true,
					'ds' => VHVH_KD::tc_ds( $u, isset( $d['ky'] ) ? (string) $d['ky'] : '',
						isset( $d['coso'] ) ? (string) $d['coso'] : '' ),
					'ky' => current_time( 'Y-m' ),
				) );
			case 'tc_them': return self::ra( VHVH_KD::tc_them( $u, $d ) );
			case 'tc_xong':
				return self::ra( VHVH_KD::tc_xong( $u, isset( $d['id'] ) ? (int) $d['id'] : 0,
					! empty( $d['xong'] ) ) );

			/* ---- thông báo ---- */
			case 'tb_ds':   return self::ra( array( 'ok' => true, 'ds' => VHVH_Viec::tb_ds( $u ) ) );
			case 'tb_dang': return self::ra( VHVH_Viec::tb_dang( $u, $d ) );
			case 'tb_xoa':  return self::ra( VHVH_Viec::tb_xoa( $u, isset( $d['id'] ) ? (int) $d['id'] : 0 ) );

			/* ---- giao việc ---- */
			case 'gv_ds':
				return self::ra( array( 'ok' => true, 'ds' => VHVH_Viec::ds( $u,
					isset( $d['coso'] ) ? (string) $d['coso'] : '',
					isset( $d['tt'] ) ? (string) $d['tt'] : 'chua' ) ) );
			case 'gv_giao': return self::ra( VHVH_Viec::giao( $u, $d ) );
			case 'gv_tt':
				return self::ra( VHVH_Viec::doi_tt( $u, isset( $d['id'] ) ? (int) $d['id'] : 0,
					isset( $d['lam'] ) ? (string) $d['lam'] : '' ) );

			/* ---- xuất báo cáo ---- */
			/* ---- cài đặt ---- */
			case 'cai_doc':
				if ( ! VHVH_Auth::du_quyen( $u, 'quan_ly' ) ) { return self::ra( VHVH_Auth::choi() ); }
				return self::ra( array( 'ok' => true,
					'ds_khai'   => VHVH_Tong::ds_coso_khai(),
					'dang_dung' => VHVH_Tong::ds_coso_he(),
					'tu_he'     => ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ds_coso' ) )
						? array_values( (array) VHCC_NhanSu::ds_coso() ) : array(),
				) );

			case 'cai_coso':
				return self::ra( VHVH_Tong::dat_ds_coso( $u,
					isset( $d['ds'] ) ? (array) $d['ds'] : array() ) );

			case 'bc_loai':
				return self::ra( array( 'ok' => true, 'loai' => VHVH_BaoCao::LOAI,
					'ky' => current_time( 'Y-m' ) ) );
			case 'bc_xuat':
				return self::ra( VHVH_BaoCao::xuat( $u, $d ) );

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
			'muc'   => self::ds_muc(),
			'url_cham_cong' => self::url_cham_cong(),
		);
	}

	/** Danh sách cơ sở người này được chọn. Mượn danh mục của plugin chấm công. */
	public static function ds_coso( $u ) {
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) { return array_values( $cho ); }
		return VHVH_Tong::ds_coso_he();
	}

	/**
	 * Màn nào hiện cho vai nào.
	 *
	 * ⚠️ Đây CHỈ để vẽ thanh điều hướng. Nó không phải hàng rào — hàng rào nằm trong từng việc
	 *    ở `cong()`. Sửa bảng này mà quên sửa chốt trong việc là mở cửa mà không biết.
	 */
	public static function man_cua( $u ) {
		$man = array( 'tong_quan', 'su_co' );
		$man[] = 'checklist';
		$man[] = 'kho';
		$man[] = 'thong_bao';
		$man[] = 'giao_viec';
		$man[] = 'tiktok';
		$man[] = 'trich_cam';
		if ( VHVH_Auth::du_quyen( $u, 'thu_ngan' ) ) { $man[] = 'tien'; }
		/* Đánh giá và Xuất báo cáo là chuyện của người quản — nhân viên xem sổ phạt của đồng
		   nghiệp thì cái sổ ấy thành chỗ soi nhau. Chốt thật vẫn nằm trong từng việc ở `cong()`. */
		if ( VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ) {
			$man[] = 'danh_gia';
			$man[] = 'bao_cao';
		}
		if ( VHVH_Auth::du_quyen( $u, 'quan_ly' ) ) { $man[] = 'cai_dat'; }
		return $man;
	}

	/**
	 * Mục nào đã làm xong, mục nào chưa.
	 *
	 * 🔴 CÁC MỤC CHƯA LÀM VẪN HIỆN TRÊN THANH BÊN, và nói thẳng là *chưa làm*. Giấu đi thì người
	 *    dùng tưởng trang này chỉ có bấy nhiêu việc rồi đi mở app cũ để làm phần còn lại — và
	 *    thế là số liệu nằm ở hai nơi. Hiện ra kèm chữ "đang làm" thì họ biết nó sắp về đây.
	 */
	public static function ds_muc() {
		return array(
			array( 'nhom' => 'TỔNG QUAN', 'muc' => array(
				array( 'ma' => 'tong_quan', 'ten' => 'Tổng quan', 'icon' => '🏠', 'xong' => 1 ),
			) ),
			array( 'nhom' => 'SỰ CỐ & CẢNH BÁO', 'muc' => array(
				array( 'ma' => 'su_co', 'ten' => 'Sự cố', 'icon' => '⚠️', 'xong' => 1 ),
			) ),
			array( 'nhom' => 'VẬN HÀNH CƠ SỞ', 'muc' => array(
				array( 'ma' => 'cham_cong', 'ten' => 'Chấm công', 'icon' => '🕒', 'xong' => 0, 'noi' => 'cham_cong' ),
				array( 'ma' => 'checklist', 'ten' => 'Checklist', 'icon' => '📋', 'xong' => 1 ),
				array( 'ma' => 'kho', 'ten' => 'Kiểm tra kho', 'icon' => '📦', 'xong' => 1 ),
				array( 'ma' => 'lich', 'ten' => 'Đăng ký lịch làm', 'icon' => '🗓️', 'xong' => 0, 'noi' => 'cham_cong' ),
				array( 'ma' => 'doi_ca', 'ten' => 'Đổi ca & nhận ca', 'icon' => '🔁', 'xong' => 0, 'noi' => 'cham_cong' ),
				array( 'ma' => 'di_muon', 'ten' => 'Báo cáo đi muộn', 'icon' => '⏰', 'xong' => 0, 'noi' => 'cham_cong' ),
			) ),
			array( 'nhom' => 'KINH DOANH', 'muc' => array(
				array( 'ma' => 'tien', 'ten' => 'Doanh thu & Chi phí', 'icon' => '💰', 'xong' => 1 ),
				array( 'ma' => 'danh_gia', 'ten' => 'Đánh giá nhân viên', 'icon' => '⭐', 'xong' => 1 ),
				array( 'ma' => 'tiktok', 'ten' => 'Thống kê TikTok', 'icon' => '🎬', 'xong' => 1 ),
				array( 'ma' => 'trich_cam', 'ten' => 'Thống kê trích cam', 'icon' => '🧧', 'xong' => 1 ),
			) ),
			array( 'nhom' => 'CÔNG VIỆC', 'muc' => array(
				array( 'ma' => 'thong_bao', 'ten' => 'Thông báo', 'icon' => '📣', 'xong' => 1 ),
				array( 'ma' => 'giao_viec', 'ten' => 'Giao việc', 'icon' => '✅', 'xong' => 1 ),
			) ),
			array( 'nhom' => 'HỆ THỐNG', 'muc' => array(
				array( 'ma' => 'bao_cao', 'ten' => 'Xuất báo cáo', 'icon' => '📊', 'xong' => 1 ),
				array( 'ma' => 'cai_dat', 'ten' => 'Cài đặt', 'icon' => '⚙️', 'xong' => 1 ),
			) ),
		);
	}

	/**
	 * Đường sang trang chấm công — để mấy mục "nối sang chấm công" bấm được luôn.
	 * Không có plugin ấy thì trả rỗng, và màn hình hiện chữ chứ không thành liên kết chết.
	 */
	public static function url_cham_cong() {
		if ( class_exists( 'VHCC_Nhan' ) && defined( 'VHCC_Nhan::DUONG' ) ) {
			return home_url( '/' . VHCC_Nhan::DUONG );
		}
		return '';
	}

	private static function ra( $x ) {
		return new WP_REST_Response( $x, 200 );
	}
}
