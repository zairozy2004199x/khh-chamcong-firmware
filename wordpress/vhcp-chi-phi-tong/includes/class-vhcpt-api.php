<?php
/**
 * CỔNG REST CỦA TRANG TỔNG — `vhcpt/v1/call`.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐƯỜNG REST MANG TIỀN TỐ RIÊNG. WordPress cho đường đăng ký SAU đè lên đường trước, và bốn
 *    plugin chi phí cài chung một site. Trùng `vhcp/v1/call` là lượt hỏi của bản này rơi vào
 *    bản kia — 08/09/2026 đã cắn đúng thế: trang mở ra trống trơn, dữ liệu còn nguyên mà nhìn
 *    y như mất sạch.
 *
 * 🔴 DANH SÁCH TRẮNG, MẶC ĐỊNH CHỐI. Không có nhánh `default` nào cho qua. Thêm việc mà quên
 *    khai là nó bị chối — thấy ngay; ngược lại là thêm một cửa không ai gác, trên một cổng
 *    đứng trước sổ tiền của ba mảng.
 *
 * ⚠️ MỌI VIỆC GHI ĐỀU HỎI LẠI QUYỀN Ở ĐÚNG BẢN CHỨA ĐƠN. Thẻ phiên nói người này duyệt được
 *    những bản nào; nhưng mã đơn thì do trình duyệt gửi lên, và bản chứa nó cũng vậy. Tin vào
 *    cái gửi lên là cho một người duyệt được của KVC đi duyệt đơn của MTD.
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPT_Api {

	const NS = 'vhcpt/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
	}

	public static function dang_ky() {
		register_rest_route( self::NS, '/call', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'call' ),
			'permission_callback' => '__return_true',
		) );
	}

	/** Đọc thẻ phiên từ tiêu đề riêng của trang này. */
	private static function the_cua_lenh( $req ) {
		$h = (string) $req->get_header( 'x_vhcpt_token' );
		if ( '' === $h ) { $h = (string) $req->get_header( 'X-VHCPT-Token' ); }
		if ( '' === $h ) {
			$b = $req->get_json_params();
			if ( is_array( $b ) && isset( $b['token'] ) ) { $h = (string) $b['token']; }
		}
		return trim( $h );
	}

	public static function call( $req ) {
		$b    = $req->get_json_params();
		$b    = is_array( $b ) ? $b : array();
		$viec = isset( $b['fn'] ) ? sanitize_text_field( (string) $b['fn'] ) : '';
		$args = isset( $b['args'] ) && is_array( $b['args'] ) ? $b['args'] : array();

		/* Đăng nhập là việc DUY NHẤT không cần thẻ. */
		if ( 'dangNhap' === $viec ) { return self::ra( self::dang_nhap( $args ) ); }

		$the = self::the_cua_lenh( $req );
		$ng  = VHCPT_Auth::nguoi_cua_the( $the );
		if ( ! $ng ) { return self::ra( array( 'error' => 'Phiên đã hết. Đăng nhập lại.' ) ); }
		VHCPT_Auth::dat_toi( $ng );

		if ( 'toi' === $viec )      { return self::ra( self::toi() ); }
		if ( 'ds' === $viec )       { return self::ra( self::ds( $args ) ); }
		if ( 'duyet' === $viec )    { return self::ra( self::duyet( $args ) ); }
		if ( 'cap' === $viec )      { return self::ra( self::cap( $args ) ); }
		if ( 'qtNcc' === $viec )    { return self::ra( self::qt_ncc( $args ) ); }
		if ( 'traLai' === $viec )   { return self::ra( self::tra_lai( $args ) ); }
		if ( 'chiTiet' === $viec )  { return self::ra( self::chi_tiet( $args ) ); }
		if ( 'dangXuat' === $viec ) { VHCPT_Auth::bo_the( $the ); return self::ra( array( 'ok' => true ) ); }
		return self::ra( array( 'error' => 'Không biết việc "' . $viec . '".' ) );
	}

	private static function ra( $data ) {
		return new WP_REST_Response( $data, 200 );
	}

	/* ══════════════════════════════════════════════════════════════════════ VIỆC */

	private static function dang_nhap( $args ) {
		$pin = isset( $args['pin'] ) ? (string) $args['pin'] : '';
		$kq  = VHCPT_Auth::tim_theo_pin( $pin );
		/* ⚠️ KHÔNG NHẮC LẠI PIN TRONG CÂU LỖI, kể cả một phần. Trang chạy ngoài internet và câu
		   lỗi thì đi vào log, vào ảnh chụp màn hình người ta gửi cho nhau. */
		if ( ! $kq ) { return array( 'error' => 'PIN không đúng, hoặc không có tài khoản ở mảng nào.' ); }
		if ( isset( $kq['loi'] ) ) { return array( 'error' => (string) $kq['loi'] ); }
		if ( ! $kq['bans'] ) { return array( 'error' => 'Tài khoản này chưa thuộc mảng nào.' ); }
		VHCPT_Auth::dat_toi( $kq );
		return array(
			'ok'    => true,
			'token' => VHCPT_Auth::phat_the( $kq ),
			'toi'   => self::toi(),
		);
	}

	private static function toi() {
		$bans = array();
		foreach ( (array) VHCPT_Auth::toi()['bans'] as $khoa => $b ) {
			$bans[] = array(
				'ban'    => $khoa,
				'tenBan' => VHCPT_Ban::ten( $khoa ),
				'vai'    => (string) $b['vai'],
				'duyet'  => ! empty( $b['duyet'] ),
				/* Cả bảng quyền, để màn biết vẽ nút nào cho mảng nào. */
				'quyen'  => isset( $b['quyen'] ) ? (array) $b['quyen'] : array(),
			);
		}
		return array(
			'ten'  => VHCPT_Auth::ten(),
			'bans' => $bans,
			'dem'  => VHCPT_Gom::dem(),
			'nhom' => VHCPT_Gom::nhom(),
		);
	}

	private static function ds( $args ) {
		$nhom = isset( $args['nhom'] ) ? sanitize_key( (string) $args['nhom'] ) : 'duyet';
		return array( 'ok' => true, 'rows' => VHCPT_Gom::gom( $nhom ), 'dem' => VHCPT_Gom::dem() );
	}

	/**
	 * Chốt chung cho mọi việc GHI: bản này có thật không, người này có quyền duyệt ở đó không,
	 * và đơn ấy có thật nằm trong bản ấy không.
	 *
	 * 🔴 PHẢI KIỂM CẢ BA. Thiếu cái thứ ba thì một người duyệt được của KVC gửi lên mã đơn của
	 *    MTD kèm khoá bản "kvc": chốt quyền cho qua, rồi lời gọi rơi vào bản KVC và... không tìm
	 *    thấy đơn, nên vô hại. Nhưng đảo lại — gửi khoá bản mình có quyền cho một mã đơn TRÙNG
	 *    tồn tại ở cả hai bản — thì duyệt nhầm đơn của mảng khác. Mã đơn hai bản trùng nhau là
	 *    chuyện thường: chúng đánh số độc lập.
	 */
	private static function chot_ghi( $args, $viec = 'duyet' ) {
		$khoa = isset( $args['ban'] ) ? sanitize_key( (string) $args['ban'] ) : '';
		$ma   = isset( $args['maDon'] ) ? sanitize_text_field( (string) $args['maDon'] ) : '';
		if ( '' === $ma ) { return array( 'error' => 'Thiếu mã đơn.' ); }
		if ( ! VHCPT_Ban::mot( $khoa ) ) { return array( 'error' => 'Không có mảng "' . $khoa . '".' ); }
		/* 🔴 HỎI QUYỀN CỦA ĐÚNG VIỆC ĐANG LÀM, không hỏi chung một cờ "duyệt được". Bản 1.0.0 gác
		   mọi việc bằng `duyetTU`, và thế là Kế toán cá nhân — người DUY NHẤT được cấp tiền —
		   không làm được gì ở đây, dù bên trang mảng họ làm bình thường. */
		if ( ! VHCPT_Auth::duoc( $khoa, $viec ) ) {
			return array( 'error' => 'Vai của bạn ở mảng ' . VHCPT_Ban::ten( $khoa )
				. ' không được làm việc này. Quyền khai ở bảng Phân quyền của chính trang mảng ấy.' );
		}
		$lop = VHCPT_Ban::lop( $khoa, 'Don' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! $lop || ! class_exists( $lop ) || ! method_exists( $lop, 'don_row' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $khoa ) . ' chưa cài, hoặc quá cũ.' );
		}
		$d = call_user_func( array( $lop, 'don_row' ), $ma );
		if ( ! $d ) { return array( 'error' => 'Không tìm thấy đơn ' . $ma . ' ở mảng ' . VHCPT_Ban::ten( $khoa ) . '.' ); }
		return array( 'ok' => true, 'lop' => $lop, 'ma' => $ma, 'khoa' => $khoa, 'don' => $d );
	}

	private static function duyet( $args ) {
		$c = self::chot_ghi( $args, 'duyet' );
		if ( empty( $c['ok'] ) ) { return $c; }
		if ( ! method_exists( $c['lop'], 'duyet_tam_ung' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $c['khoa'] ) . ' quá cũ, chưa có đường duyệt.' );
		}
		/* ⚠️ KHÔNG TRUYỀN SỐ TẠM ỨNG TỪ MÀN NÀY. Anh Thắng 13/09/2026: *"bấm duyệt không cần hỏi
		   cái này nhé"*. Để trống thì bản kia giữ nguyên số người lập đã xin — đúng thứ người
		   duyệt vừa nhìn thấy trên bảng. */
		$kq = call_user_func( array( $c['lop'], 'duyet_tam_ung' ), $c['ma'], VHCPT_Auth::ten(), '' );
		return self::doi_ket_qua( $kq, 'Đã duyệt ' . $c['ma'] . ' (' . VHCPT_Ban::ten( $c['khoa'] ) . ').' );
	}

	private static function tra_lai( $args ) {
		$c = self::chot_ghi( $args, 'traLai' );
		if ( empty( $c['ok'] ) ) { return $c; }
		if ( ! method_exists( $c['lop'], 'tra_lai_don' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $c['khoa'] ) . ' quá cũ, chưa có đường trả lại.' );
		}
		$ly_do = isset( $args['lyDo'] ) ? sanitize_text_field( (string) $args['lyDo'] ) : '';
		$kq = call_user_func( array( $c['lop'], 'tra_lai_don' ), $c['ma'], $ly_do );
		return self::doi_ket_qua( $kq, 'Đã trả lại ' . $c['ma'] . ' (' . VHCPT_Ban::ten( $c['khoa'] ) . ').' );
	}

	/**
	 * CẤP (CHUYỂN) TẠM ỨNG — việc của Kế toán cá nhân, quyền `capTU`.
	 *
	 * ⚠️ HÌNH THỨC CẤP MẶC ĐỊNH "Tiền mặt", và KHÔNG kèm ảnh chứng từ. Trang tổng là màn duyệt
	 *    nhanh; ai cần ghi rõ hình thức hay đính ảnh uỷ nhiệm chi thì làm ở trang mảng, nơi có
	 *    đủ ô. Đặt bừa một hình thức khác ở đây là ghi sai chứng từ mà không ai nhìn thấy.
	 */
	private static function cap( $args ) {
		$c = self::chot_ghi( $args, 'cap' );
		if ( empty( $c['ok'] ) ) { return $c; }
		if ( ! method_exists( $c['lop'], 'cap_tam_ung' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $c['khoa'] ) . ' quá cũ, chưa có đường cấp tạm ứng.' );
		}
		$kq = call_user_func( array( $c['lop'], 'cap_tam_ung' ), $c['ma'], VHCPT_Auth::ten(), 'Tiền mặt', '' );
		return self::doi_ket_qua( $kq, 'Đã cấp tạm ứng ' . $c['ma'] . ' (' . VHCPT_Ban::ten( $c['khoa'] ) . ').' );
	}

	/**
	 * XÁC NHẬN QUYẾT TOÁN PHẦN NHÀ CUNG CẤP — việc của Kế toán NCC, quyền `duyetNCC`.
	 *
	 * 🔴 CỐ Ý KHÔNG LÀM PHẦN CÁ NHÂN Ở ĐÂY. `xac_nhan_quyet_toan_cn()` đòi hai thứ phải QUYẾT
	 *    ĐỊNH — cách xử lý và số chênh lệch — và chúng đi thẳng vào chứng từ. Bày hai ô ấy trên
	 *    một màn duyệt nhanh, không có bảng hạng mục bên cạnh để đối chiếu, là mời người ta gõ
	 *    bừa cho xong. Phần cá nhân làm ở trang mảng, nơi có khối Quyết toán đầy đủ.
	 */
	private static function qt_ncc( $args ) {
		$c = self::chot_ghi( $args, 'qtNcc' );
		if ( empty( $c['ok'] ) ) { return $c; }
		if ( ! method_exists( $c['lop'], 'xac_nhan_quyet_toan_ncc' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $c['khoa'] ) . ' quá cũ, chưa có đường xác nhận NCC.' );
		}
		$kq = call_user_func( array( $c['lop'], 'xac_nhan_quyet_toan_ncc' ), $c['ma'], VHCPT_Auth::ten() );
		return self::doi_ket_qua( $kq, 'Đã xác nhận phần NCC của ' . $c['ma'] . ' (' . VHCPT_Ban::ten( $c['khoa'] ) . ').' );
	}

	/**
	 * XEM CHI TIẾT MỘT ĐƠN — chỉ ĐỌC, nên chốt là "có mặt ở bản ấy", không đòi quyền duyệt.
	 *
	 * 🔴 DUYỆT MÙ LÀ CÁI HẠI CỦA MỘT MÀN GOM. Bảng ngoài chỉ có một con số tổng; bấm Duyệt mà
	 *    không mở được hạng mục ra xem thì người duyệt đang ký vào thứ họ chưa đọc. Đường này là
	 *    cái làm trang tổng dùng được thật, không phải một tiện ích thêm.
	 */
	private static function chi_tiet( $args ) {
		$khoa = isset( $args['ban'] ) ? sanitize_key( (string) $args['ban'] ) : '';
		$ma   = isset( $args['maDon'] ) ? sanitize_text_field( (string) $args['maDon'] ) : '';
		if ( '' === $ma ) { return array( 'error' => 'Thiếu mã đơn.' ); }
		if ( ! VHCPT_Ban::mot( $khoa ) ) { return array( 'error' => 'Không có mảng "' . $khoa . '".' ); }
		if ( ! in_array( $khoa, VHCPT_Auth::ban_doc_duoc(), true ) ) {
			return array( 'error' => 'Bạn không có tài khoản ở mảng ' . VHCPT_Ban::ten( $khoa ) . '.' );
		}
		$d = VHCPT_Gom::mot_don( $khoa, $ma );
		if ( ! $d ) {
			return array( 'error' => 'Không tìm thấy đơn ' . $ma . ' ở mảng ' . VHCPT_Ban::ten( $khoa ) . '.' );
		}
		return array( 'ok' => true, 'don' => array(
			'don' => array(
				'maDon'      => (string) $d['ma_don'],
				'ky'         => (string) $d['ky'],
				'nguoiLap'   => (string) $d['nguoi_lap'],
				'donVi'      => (string) $d['don_vi'],
				'trangThai'  => (string) $d['trang_thai'],
				'ghiChu'     => (string) $d['ghi_chu'],
				'nguoiDuyet' => (string) $d['nguoi_duyet'],
				'ngayDuyet'  => (string) $d['ngay_duyet'],
			),
			'lines' => VHCPT_Gom::dong_chi( $khoa, $ma ),
		) );
	}

	/**
	 * Đổi kết quả của bản kia sang dạng của trang này.
	 *
	 * ⚠️ BÊN KIA TRẢ `['success'=>bool,'message'=>…]` (xem `VHCP_Util::ok`/`err`), bên này dùng
	 *    `['ok'|'error']`. Đọc nhầm khoá là mọi lỗi bên kia thành "thành công" ở đây — người
	 *    duyệt thấy báo xanh rồi đóng máy, trong khi đơn vẫn nằm nguyên chỗ cũ.
	 */
	private static function doi_ket_qua( $kq, $loi_nhan ) {
		$kq = (array) $kq;
		if ( ! empty( $kq['success'] ) ) { return array( 'ok' => true, 'message' => $loi_nhan ); }
		$m = '';
		if ( isset( $kq['message'] ) ) { $m = (string) $kq['message']; }
		elseif ( isset( $kq['error'] ) ) { $m = (string) $kq['error']; }
		return array( 'error' => ( '' !== $m ? $m : 'Bản kia chối, không nói lý do.' ) );
	}
}
