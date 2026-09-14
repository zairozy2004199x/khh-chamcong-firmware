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
		/* 🔴 Thẻ phát ra từ bản CŨ mang theo vai và quyền của bản cũ — kể cả khi bản cũ ấy đọc
		   sai. Nâng cấp rồi F5 mà màn vẫn y nguyên là cảnh đã xảy ra thật (xem
		   `VHCPT_Auth::lam_moi_neu_cu()`). Làm mới tại đây, trước mọi việc. */
		$ng = VHCPT_Auth::lam_moi_neu_cu( $the, $ng );
		VHCPT_Auth::dat_toi( $ng );

		if ( 'toi' === $viec )      { return self::ra( self::toi() ); }
		if ( 'ds' === $viec )       { return self::ra( self::ds( $args ) ); }
		if ( 'duyet' === $viec )    { return self::ra( self::duyet( $args ) ); }
		if ( 'cap' === $viec )      { return self::ra( self::cap( $args ) ); }
		if ( 'qtCn' === $viec )     { return self::ra( self::qt_cn( $args ) ); }
		if ( 'qtNcc' === $viec )    { return self::ra( self::qt_ncc( $args ) ); }
		if ( 'misa' === $viec )     { return self::ra( self::misa( $args ) ); }
		if ( 'misaXong' === $viec ) { return self::ra( self::misa_xong( $args ) ); }
		if ( 'tra' === $viec )      { return self::ra( self::tra( $args ) ); }
		if ( 'tongQuan' === $viec ) { return self::ra( self::tong_quan( $args ) ); }
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

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * CỬA VÀO — anh Thắng 14/09/2026: *"đẩy nhân sự kế toán, và quản lý qua để duyệt đơn và
		 * xử lý đơn, nhân viên thì không cần"*.
		 *
		 * 🔴 CHỐI Ở ĐÂY, KHÔNG CHỐI Ở MÀN. Giấu nút đi mà vẫn cho vào là người ta vẫn đọc được
		 *    toàn bộ sổ chi của ba mảng — đúng thứ đang phải tách. Cửa phải đóng ở máy chủ.
		 *
		 * ⚠️ VÀ NÓI RÕ VÌ SAO, KÈM CHỖ SỬA. Câu "không có quyền" trơ trọi là người ta đi hỏi
		 *    vòng quanh; câu này chỉ thẳng vào bảng Phân quyền của trang mảng.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		if ( ! VHCPT_Auth::duoc_vao( $kq['bans'], $kq['ten'] ) ) {
			return array( 'error' => 'Trang tổng dành cho người DUYỆT và XỬ LÝ đơn (quản lý · kế toán). '
				. 'Vai của bạn ở các mảng không được duyệt, cấp tiền, trả lại, quyết toán hay xuất MISA, '
				. 'nên chưa vào được. Lên đơn thì làm ở trang chi phí của mảng mình. '
				. 'Cần vào đây thì khai quyền ở bảng Phân quyền của trang mảng, hoặc nhờ Admin thêm tên '
				. 'vào mục «Người vào trang tổng» ở wp-admin.' );
		}

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
		/* 🔴 MƯỢN PHIÊN TRƯỚC KHI CHẠM VÀO LÕI BẢN KIA. Mọi hàm nghiệp vụ bên ấy hỏi
		   `<Bản>_Auth` xem ai đang gọi — không đặt thì chúng chạy với danh tính RỖNG: lúc chối
		   oan, lúc ghi tên người quyết toán là chuỗi trắng vào chứng từ. Xem khối dài ở
		   `VHCPT_Ban::muon_phien()`. */
		VHCPT_Ban::muon_phien( $khoa );
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
		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * SỐ TIỀN THỪA/THIẾU — anh Thắng 14/09/2026: *"Bổ sung vào để hiện trạng thái đơn khi
		 * bấm xem, và phần thừa thiếu ở cuối trang."*
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 HỎI LÕI CỦA BẢN ẤY, KHÔNG TỰ TRỪ. Luật "tạm ứng − thực chi" nghe đơn giản nhưng có
		 *    mấy chỗ tinh: chưa cấp tiền thì chênh lệch phải là 0 chứ không phải bằng cả cục tạm
		 *    ứng; thực chi lấy ô "thực mua" nếu người ta gõ, không thì lấy thành tiền; phần nhà
		 *    cung cấp tính riêng phần cá nhân. Chép luật ấy sang đây là dựng bản thứ hai cho
		 *    cùng một câu hỏi, rồi hai bản lệch nhau — mà lệch ở con số tiền thì kế toán tin
		 *    con số nào?
		 *
		 * ⚠️ VÀ NÓ ĐÒI PHIÊN: `get_don()` gác theo đơn vị/bộ phận của người đang gọi. Không mượn
		 *    phiên thì nó chối một đơn đang nằm sờ sờ trên màn.
		 *
		 * ⚠️ THIẾU THÌ BỎ QUA, KHÔNG HỎNG CẢ MÀN. Bản mảng đời cũ không có `get_don()` thì phần
		 *    dòng chi vẫn bày bình thường, chỉ khuyết khối tiền.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$tien = null;
		$lop_don = VHCPT_Ban::lop( $khoa, 'Don' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( $lop_don && class_exists( $lop_don ) && method_exists( $lop_don, 'get_don' ) ) {
			VHCPT_Ban::muon_phien( $khoa );
			$g = (array) call_user_func( array( $lop_don, 'get_don' ), $ma, false );
			if ( ! empty( $g['success'] ) ) {
				$cn  = isset( $g['tongCN'] ) ? (array) $g['tongCN'] : array();
				$ncc = isset( $g['tongNCC'] ) ? (array) $g['tongNCC'] : array();
				$tien = array(
					'tamUng'    => (float) ( isset( $cn['tamUng'] ) ? $cn['tamUng'] : 0 ),
					'thucChi'   => (float) ( isset( $cn['thucChi'] ) ? $cn['thucChi'] : 0 ),
					'chenhLech' => (float) ( isset( $cn['chenhLech'] ) ? $cn['chenhLech'] : 0 ),
					'nccChi'    => (float) ( isset( $ncc['thucChi'] ) ? $ncc['thucChi'] : 0 ),
					'daCapTien' => ! empty( $g['daCapTien'] ),
				);
			}
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * MỐC AI LÀM GÌ LÚC NÀO, VÀ LỊCH SỬ CHỈNH ĐƠN — anh Thắng 14/09/2026: *"bên trang tổng
		 * khi bấm xem, thì nó cũng phải đủ 2 phần này trong đơn đó"*.
		 *
		 * 🔴 ĐÂY LÀ THỨ NGƯỜI DUYỆT ĐỌC TRƯỚC KHI BẤM. Một đơn vừa bị trả lại rồi gửi lại, hay một
		 *    đơn có người vừa gỡ số duyệt, nhìn y hệt đơn bình thường — chỉ lịch sử mới nói ra.
		 *    Thiếu nó thì quản lý phải mở trang mảng, đúng thứ đang cố bỏ.
		 *
		 * ⚠️ LỌC THEO ĐÚNG MÃ ĐƠN. `get_log()` trả nhật ký của CẢ bản (800 dòng gần nhất); dội
		 *    nguyên xuống màn là vừa nặng vừa bày việc của đơn khác.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$lich_su = array();
		$lop_log = VHCPT_Ban::lop( $khoa, 'Log' );
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( $lop_log && class_exists( $lop_log ) && method_exists( $lop_log, 'get_log' ) ) {
			$lg = (array) call_user_func( array( $lop_log, 'get_log' ), array( 'q' => $ma, 'limit' => 400 ) );
			$it = isset( $lg['items'] ) ? (array) $lg['items'] : ( isset( $lg['data']['items'] ) ? (array) $lg['data']['items'] : array() );
			foreach ( $it as $x ) {
				$x = (array) $x;
				/* `q` dò trong cả nội dung nên vẫn lọt dòng của đơn khác có nhắc mã này — chốt
				   lại bằng đúng ô Đối tượng. */
				if ( trim( (string) ( isset( $x['doiTuong'] ) ? $x['doiTuong'] : '' ) ) !== $ma ) { continue; }
				$lich_su[] = array(
					'tg'       => (string) ( isset( $x['tg'] ) ? $x['tg'] : '' ),
					'nguoi'    => (string) ( isset( $x['nguoi'] ) ? $x['nguoi'] : '' ),
					'vaiTro'   => (string) ( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' ),
					'hanhDong' => (string) ( isset( $x['hanhDong'] ) ? $x['hanhDong'] : '' ),
					'chiTiet'  => (string) ( isset( $x['chiTiet'] ) ? $x['chiTiet'] : '' ),
				);
				if ( count( $lich_su ) >= 30 ) { break; }
			}
		}

		return array( 'ok' => true, 'don' => array(
			'tien' => $tien,
			'lichSu' => $lich_su,
			'moc' => array(
				'duyet'  => array( 'nguoi' => (string) $d['nguoi_duyet'], 'ngay' => (string) $d['ngay_duyet'] ),
				'cap'    => array( 'nguoi' => (string) ( isset( $d['nguoi_cap'] ) ? $d['nguoi_cap'] : '' ),
				                   'ngay'  => (string) ( isset( $d['ngay_cap'] ) ? $d['ngay_cap'] : '' ) ),
				'qtCn'   => array( 'nguoi' => (string) ( isset( $d['nguoi_qt'] ) ? $d['nguoi_qt'] : '' ),
				                   'ngay'  => (string) ( isset( $d['ngay_qt'] ) ? $d['ngay_qt'] : '' ) ),
				'qtNcc'  => array( 'nguoi' => (string) ( isset( $d['nguoi_qt_ncc'] ) ? $d['nguoi_qt_ncc'] : '' ),
				                   'ngay'  => (string) ( isset( $d['ngay_qt_ncc'] ) ? $d['ngay_qt_ncc'] : '' ) ),
			),
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
	 * XÁC NHẬN QUYẾT TOÁN PHẦN CÁ NHÂN — việc của kế toán, quyền `xacNhanQT`.
	 *
	 * 🔴 GỌI `xac_nhan_qt_cn_nhieu()`, KHÔNG GỌI `xac_nhan_quyet_toan_cn()` THẲNG. Hàm sau đòi
	 *    hai thứ phải QUYẾT ĐỊNH — cách xử lý và số chênh lệch — và chúng đi thẳng vào chứng từ;
	 *    bản 1.1.0 vì thế cố ý không làm việc này ở trang tổng. Nhưng hàm TRƯỚC tự tính cả hai
	 *    từ chính sổ của bản ấy (`list_dons()` -> chênh lệch -> "NV trả lại" / "Kế toán bù" /
	 *    "Khớp"), nên không còn ô trống nào mời người ta gõ bừa.
	 *
	 * ⚠️ VÀ NÓ ĐÒI PHIÊN: `list_dons()` lọc theo đơn vị và bộ phận của người đang gọi. Không
	 *    mượn phiên thì nó trả danh sách rỗng, `$cl_by` không có mã đơn, và hàm báo "Không tìm
	 *    thấy đơn" cho một đơn đang nằm sờ sờ trên màn.
	 */
	private static function qt_cn( $args ) {
		$c = self::chot_ghi( $args, 'qtCn' );
		if ( empty( $c['ok'] ) ) { return $c; }
		if ( ! method_exists( $c['lop'], 'xac_nhan_qt_cn_nhieu' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $c['khoa'] ) . ' quá cũ, chưa có đường quyết toán.' );
		}
		VHCPT_Ban::muon_phien( $c['khoa'] );
		$kq = (array) call_user_func( array( $c['lop'], 'xac_nhan_qt_cn_nhieu' ),
			array( $c['ma'] ), VHCPT_Auth::ten() );
		if ( empty( $kq['success'] ) ) {
			$loi = ( ! empty( $kq['errors'] ) && is_array( $kq['errors'] ) )
				? implode( ' · ', $kq['errors'] ) : 'Bản kia chối, không nói lý do.';
			return array( 'error' => $loi );
		}
		return array( 'ok' => true,
			'message' => 'Đã quyết toán phần cá nhân ' . $c['ma'] . ' (' . VHCPT_Ban::ten( $c['khoa'] ) . ').' );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * TỔNG QUAN — MỌI ĐƠN TỪ TRƯỚC TỚI GIỜ, CHIA THEO BƯỚC
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"thêm giúp anh 1 cái tab đầu tiên (Dashboard) hiện tất cả đơn từ
	 * trước đến giờ, kèm các bộ lọc"* và ngay sau đó *"chỗ này cũng tách ra các bảng: đã tạm ứng,
	 * chưa tạm ứng, chờ tạm ứng — tức các bước để kế toán theo dõi, nhớ ai nhập trước, lên trước"*.
	 *
	 * 🔴 "AI NHẬP TRƯỚC, LÊN TRƯỚC" LÀ MỘT LUẬT CÔNG BẰNG, KHÔNG PHẢI MỘT SỞ THÍCH SẮP XẾP.
	 *    Hàng chờ xếp mới-nhất-trước thì đơn nộp sớm bị đẩy dần xuống đáy mỗi khi có người nộp
	 *    thêm — người nộp sớm nhất chờ lâu nhất, và không ai nhìn thấy điều đó. Nên mọi bảng
	 *    VIỆC-CÒN-PHẢI-LÀM xếp CŨ TRƯỚC.
	 *
	 * ⚠️ Bảng ĐÃ XONG thì ngược lại — mới nhất trước, vì người ta mở ra để xem việc vừa làm.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function tong_quan( $args ) {
		$loc = array(
			'coso'      => isset( $args['coso'] ) ? sanitize_text_field( (string) $args['coso'] ) : '',
			'loai'      => isset( $args['loai'] ) ? sanitize_text_field( (string) $args['loai'] ) : '',
			'nguoi'     => isset( $args['nguoi'] ) ? sanitize_text_field( (string) $args['nguoi'] ) : '',
			'trangThai' => isset( $args['trangThai'] ) ? sanitize_text_field( (string) $args['trangThai'] ) : '',
			'tuNgay'    => isset( $args['tuNgay'] ) ? sanitize_text_field( (string) $args['tuNgay'] ) : '',
			'denNgay'   => isset( $args['denNgay'] ) ? sanitize_text_field( (string) $args['denNgay'] ) : '',
		);
		/* Lọc theo MẢNG = chọn bản nào để hỏi. Bó ngay từ đây chứ không lọc lúc vẽ: hỏi cả bốn
		   bản rồi bỏ ba là ba lượt đọc bảng phí cho mỗi lần đổi bộ lọc. */
		$chon = isset( $args['ban'] ) ? sanitize_key( (string) $args['ban'] ) : '';

		$ra = array(); $cs = array(); $lo = array(); $ng = array();
		$tong = 0; $dong = 0; $cat = 0;
		foreach ( VHCPT_Auth::ban_doc_duoc() as $khoa ) {
			/* Danh mục cho ba hộp chọn lấy từ MỌI mảng, kể cả mảng đang bị lọc ra — nếu không
			   thì chọn một mảng xong là hai hộp kia rỗng và không đổi lại được nữa. */
			$dm = VHCPT_Gom::danh_muc( $khoa );
			foreach ( $dm['coso'] as $x )  { $cs[ $x ] = 1; }
			foreach ( $dm['loai'] as $x )  { $lo[ $x ] = 1; }
			foreach ( $dm['nguoi'] as $x ) { $ng[ $x ] = 1; }
			if ( '' !== $chon && $chon !== $khoa ) { continue; }

			$r = VHCPT_Gom::tat_ca_don( $khoa, $loc );
			$rows = isset( $r['rows'] ) ? (array) $r['rows'] : array();
			$t_ban = 0;
			foreach ( $rows as $x ) { if ( null !== $x['tien'] ) { $t_ban += (float) $x['tien']; } }
			$tong += $t_ban; $dong += (int) $r['tong'];
			if ( (int) $r['tong'] > count( $rows ) ) { $cat++; }
			$ra[] = array(
				'ban'    => $khoa,
				'tenBan' => VHCPT_Ban::ten( $khoa ),
				'url'    => (string) ( isset( VHCPT_Ban::ds()[ $khoa ]['url'] ) ? VHCPT_Ban::ds()[ $khoa ]['url'] : '' ),
				'rows'   => $rows,
				'soDon'  => (int) $r['tong'],
				'tong'   => $t_ban,
			);
		}
		if ( ! $ra && '' === $chon ) {
			return array( 'error' => 'Không đọc được mảng nào.' );
		}
		$k = function ( $m ) { $x = array_keys( $m ); sort( $x ); return $x; };
		return array( 'ok' => true, 'ban' => $ra, 'tong' => $tong, 'soDon' => $dong, 'coCat' => $cat,
			'cosoList' => $k( $cs ), 'loaiList' => $k( $lo ), 'nguoiList' => $k( $ng ),
			'banList'  => array_map( function ( $x ) { return array( 'ban' => $x, 'ten' => VHCPT_Ban::ten( $x ) ); },
				VHCPT_Auth::ban_doc_duoc() ),
			'buoc'     => VHCPT_Gom::BUOC );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * TRA CHI PHÍ — GÕ MỘT LẦN, GOM CẢ BA MẢNG
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"sau này muốn tra chi phí, chọn cửa hàng, chọn loại chi phí là biết
	 * được ngay phải không"* → *"nếu trang tổng khi gõ, nó tự gom 3 trang lại được không"*.
	 *
	 * 🔴 GỌI LÕI TRA CỦA TỪNG BẢN, KHÔNG TỰ ĐỌC BẢNG. `VHCP_TraMa::search()` gom dòng từ BỐN
	 *    nguồn (chi phí, sổ chi, đơn mua, dự án), rửa mã tài khoản, và lọc theo đơn vị của người
	 *    đang xem. Viết lại câu SQL ở đây là bỏ hết mấy lớp ấy — ra một bảng trông giống thật
	 *    nhưng thiếu nguồn và hở phân quyền.
	 *
	 * 🔴 MỖI MẢNG MỘT BẢNG RIÊNG, KHÔNG TRỘN — cùng lý do anh Thắng đã chốt cho màn duyệt: *"tách
	 *    3 bảng riêng, để kế toán biết 3 bộ phận"*, *"vì tên có thể trùng nhau"*. Ba mảng có thể
	 *    cùng có một cửa hàng tên y hệt; trộn vào một bảng rồi cộng một dòng là hai khoản của hai
	 *    bộ phận nằm chung mà không ai tách được nữa.
	 *
	 * ⚠️ CHỈ GOM MẢNG NGƯỜI NÀY CÓ MẶT (`ban_doc_duoc()`). Gom hết rồi lọc lúc vẽ thì con số tổng
	 *    ở đầu màn đã kể cả mảng họ không được nhìn — mà đó là con số người ta đọc trước tiên.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function tra( $args ) {
		$loc = array(
			'coso' => isset( $args['coso'] ) ? sanitize_text_field( (string) $args['coso'] ) : 'all',
			'loai' => isset( $args['loai'] ) ? sanitize_text_field( (string) $args['loai'] ) : 'all',
			'ky'   => isset( $args['ky'] )   ? sanitize_text_field( (string) $args['ky'] )   : 'all',
			'tkNo' => isset( $args['tkNo'] ) ? sanitize_text_field( (string) $args['tkNo'] ) : 'all',
			'q'    => isset( $args['q'] )    ? sanitize_text_field( (string) $args['q'] )    : '',
		);

		$ra = array(); $cu_ds = array(); $lo_ds = array(); $ky_ds = array(); $tong = 0; $dong = 0;
		foreach ( VHCPT_Auth::ban_doc_duoc() as $khoa ) {
			$lop = VHCPT_Ban::lop( $khoa, 'TraMa' );
			/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
			if ( ! $lop || ! class_exists( $lop ) || ! method_exists( $lop, 'search' ) ) { continue; }
			VHCPT_Ban::muon_phien( $khoa );
			$r = (array) call_user_func( array( $lop, 'search' ), $loc );
			if ( empty( $r['success'] ) ) { continue; }

			$items = isset( $r['items'] ) ? (array) $r['items'] : array();
			$t_ban = (float) ( isset( $r['tong'] ) ? $r['tong'] : 0 );
			$tong += $t_ban; $dong += count( $items );
			$ra[] = array(
				'ban'    => $khoa,
				'tenBan' => VHCPT_Ban::ten( $khoa ),
				'url'    => (string) ( isset( VHCPT_Ban::ds()[ $khoa ]['url'] ) ? VHCPT_Ban::ds()[ $khoa ]['url'] : '' ),
				/* Cắt bớt khi quá dài: màn bày 200 dòng đầu, con số cộng thì vẫn của CẢ lát cắt.
				   Cộng một đằng bày một nẻo mà không nói ra là chỗ người đọc tự kết luận sai. */
				'items'  => array_slice( $items, 0, 200 ),
				'soDong' => count( $items ),
				'tong'   => $t_ban,
				'byCoso' => isset( $r['byCoso'] ) ? array_slice( (array) $r['byCoso'], 0, 30 ) : array(),
			);

			/* Gom danh sách cho ba hộp chọn — hợp nhất của cả ba mảng. */
			foreach ( (array) ( isset( $r['cosoList'] ) ? $r['cosoList'] : array() ) as $x ) { $cu_ds[ (string) $x ] = 1; }
			foreach ( (array) ( isset( $r['loaiList'] ) ? $r['loaiList'] : array() ) as $x ) { $lo_ds[ (string) $x ] = 1; }
			foreach ( (array) ( isset( $r['kyList'] )   ? $r['kyList']   : array() ) as $x ) { $ky_ds[ (string) $x ] = 1; }
		}

		if ( ! $ra ) {
			return array( 'error' => 'Không đọc được mảng nào. Bản mảng có thể chưa cài, hoặc quá cũ '
				. '(chưa có màn Tra theo mã).' );
		}

		$cu = array_keys( $cu_ds ); sort( $cu );
		$lo = array_keys( $lo_ds ); sort( $lo );
		$ky = array_keys( $ky_ds );
		rsort( $ky );
		return array( 'ok' => true, 'ban' => $ra, 'tong' => $tong, 'soDong' => $dong,
			'cosoList' => $cu, 'loaiList' => $lo, 'kyList' => $ky );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * XUẤT MISA — GỌI LÕI CỦA TỪNG BẢN, KHÔNG DỰNG BẢNG TÀI KHOẢN THỨ HAI
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"Trang tổng là xem, duyệt, quyết toán, xuất misa"*.
	 *
	 * 🔴 MỖI MẢNG XUẤT MỘT TỆP RIÊNG, KHÔNG GỘP BA MẢNG VÀO MỘT. Bút toán MISA mang mã đơn vị
	 *    của từng cơ sở, và ba mảng có ba danh mục tài khoản riêng — gộp một tệp là kế toán phải
	 *    ngồi tách lại bằng tay, mà tách tay trên bảng bút toán là chỗ dễ lẫn nhất.
	 *
	 * ⚠️ KHÔNG ĐÁNH DẤU "ĐÃ XUẤT" NGAY LÚC XEM. Xem thử rồi đóng màn mà sổ đã ghi "đã xuất" thì
	 *    lượt xuất thật sau đó ra tệp RỖNG — và không ai biết vì sao. Đánh dấu là một nút riêng,
	 *    bấm sau khi đã tải tệp về.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function misa( $args ) {
		$ky   = isset( $args['ky'] ) ? sanitize_text_field( (string) $args['ky'] ) : 'all';
		$mode = isset( $args['mode'] ) ? sanitize_text_field( (string) $args['mode'] ) : 'chuaxuat';
		$pl   = isset( $args['pl'] ) ? sanitize_text_field( (string) $args['pl'] ) : 'all';
		$ra   = array();
		foreach ( VHCPT_Auth::ban_lam_duoc( 'misa' ) as $khoa ) {
			$lop = VHCPT_Ban::lop( $khoa, 'Misa' );
			/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
			if ( ! $lop || ! class_exists( $lop ) || ! method_exists( $lop, 'export_misa' ) ) { continue; }
			VHCPT_Ban::muon_phien( $khoa );
			$r = (array) call_user_func( array( $lop, 'export_misa' ), $ky, $mode, $pl );
			$ra[] = array(
				'ban'    => $khoa,
				'tenBan' => VHCPT_Ban::ten( $khoa ),
				'cols'   => isset( $r['cols'] ) ? $r['cols'] : array(),
				'rows'   => isset( $r['rows'] ) ? $r['rows'] : array(),
				'soDong' => isset( $r['count'] ) ? (int) $r['count'] : 0,
				'soDon'  => isset( $r['sodon'] ) ? (int) $r['sodon'] : 0,
				'canh'   => isset( $r['warn'] ) ? (array) $r['warn'] : array(),
				'maDons' => isset( $r['maDons'] ) ? (array) $r['maDons'] : array(),
			);
		}
		if ( ! $ra ) {
			return array( 'error' => 'Vai của bạn không được xuất MISA ở mảng nào. '
				. 'Quyền khai ở bảng Phân quyền của từng trang mảng.' );
		}
		return array( 'ok' => true, 'ban' => $ra );
	}

	/** Đánh dấu ĐÃ XUẤT cho một mảng — nút riêng, bấm sau khi đã tải tệp về. */
	private static function misa_xong( $args ) {
		$khoa = isset( $args['ban'] ) ? sanitize_key( (string) $args['ban'] ) : '';
		$pl   = isset( $args['pl'] ) ? sanitize_text_field( (string) $args['pl'] ) : 'all';
		$ma_ds = isset( $args['maDons'] ) && is_array( $args['maDons'] ) ? $args['maDons'] : array();
		if ( ! VHCPT_Ban::mot( $khoa ) ) { return array( 'error' => 'Không có mảng "' . $khoa . '".' ); }
		if ( ! VHCPT_Auth::duoc( $khoa, 'misa' ) ) {
			return array( 'error' => 'Vai của bạn ở mảng ' . VHCPT_Ban::ten( $khoa ) . ' không được xuất MISA.' );
		}
		if ( ! $ma_ds ) { return array( 'error' => 'Không có đơn nào để đánh dấu.' ); }
		$sach = array();
		foreach ( $ma_ds as $m ) {
			$m = sanitize_text_field( (string) $m );
			if ( '' !== $m ) { $sach[] = $m; }
		}
		$lop = VHCPT_Ban::lop( $khoa, 'Misa' );
		if ( ! $lop || ! class_exists( $lop ) || ! method_exists( $lop, 'mark_exported' ) ) {
			return array( 'error' => 'Bản ' . VHCPT_Ban::ten( $khoa ) . ' quá cũ, chưa có đường đánh dấu.' );
		}
		VHCPT_Ban::muon_phien( $khoa );
		$kq = call_user_func( array( $lop, 'mark_exported' ), $sach, $pl );
		return self::doi_ket_qua( $kq, 'Đã đánh dấu ' . count( $sach ) . ' đơn của '
			. VHCPT_Ban::ten( $khoa ) . ' là đã xuất MISA.' );
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
