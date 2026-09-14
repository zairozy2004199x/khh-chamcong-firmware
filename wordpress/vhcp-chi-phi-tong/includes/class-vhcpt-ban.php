<?php
/**
 * SỔ CÁC BẢN CHI PHÍ ĐANG CÀI — trang tổng nói chuyện với chúng qua đây, không chép dữ liệu.
 *
 * Anh Thắng 14/09/2026: *"4 plugin, tạo mã riêng không trùng nhau, vì bộ phận có mã riêng, tk
 * khoản riêng, quản lý riêng, chỉ là kho đơn đẩy nó gom về 1 trang chi phí cho người duyệt"*.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHÔNG CHÉP ĐƠN SANG KHO THỨ TƯ. Trang này KHÔNG có bảng đơn của riêng nó, và đó là quyết
 *    định lớn nhất ở đây. Chép đơn sang một kho tổng thì có hai bản sao của cùng một đơn, tức
 *    HAI SỰ THẬT — và chúng lệch nhau vào đúng lúc người ta cần con số đúng nhất: ai đó sửa đơn
 *    bên mảng sau khi trang tổng đã chép, hoặc lượt chép chạy hụt một đơn và không ai biết.
 *    Nên: đọc thẳng bảng của từng bản, và DUYỆT thì gọi ngược về đúng bản sinh ra đơn.
 *
 * 🔴 DÒ, KHÔNG LIỆT KÊ. Bản nào có mặt trên site thì lớp `VHCP<MÃ>_Don` của nó đã được nạp.
 *    Gõ cứng danh sách mảng ở đây thì mảng thứ tư sinh ra tháng sau không lọt vào trang tổng —
 *    mà "đơn của một mảng không ai duyệt" là thứ chỉ lộ ra khi có người đi đòi tiền.
 *
 * ⚠️ BẢN GỐC MANG MÃ RỖNG. `VHCP_Don` (không hậu tố) là bản Khu Vui Chơi — bản đang chở sổ thật.
 *    Mọi chỗ tính mã bản phải chịu được chuỗi rỗng, nên `khoa()` đổi nó thành 'kvc' để làm khoá
 *    hiện ra màn và đi trong URL, còn `tien_to()` giữ đúng chuỗi rỗng để dựng tên lớp.
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPT_Ban {

	/** Khoá lưu tên hiện ra của từng bản: [ khoá bản => tên ]. */
	const O_TEN = 'vhcpt_ten_ban';

	/** Khoá của bản gốc (lớp VHCP_* không hậu tố). */
	const KHOA_GOC = 'kvc';

	/** Nhớ trong một lượt tải trang — `get_declared_classes()` không rẻ. */
	private static $memo = null;

	/**
	 * Các bản đang cài: [ khoá => [ 'khoa', 'tienTo', 'ten' ] ].
	 *
	 * ⚠️ Xếp theo khoá để bảng hiện ra có thứ tự ổn định giữa hai lượt tải. Thứ tự
	 *    `get_declared_classes()` là thứ tự NẠP, mà thứ tự ấy đổi khi bật/tắt một plugin khác —
	 *    bảng nhảy hàng giữa hai lần F5 thì người duyệt mất dấu đơn mình đang đọc.
	 */
	public static function ds() {
		if ( null !== self::$memo ) { return self::$memo; }
		$ra = array();
		foreach ( get_declared_classes() as $c ) {
			if ( ! preg_match( '/^VHCP([A-Z0-9]*)_Don$/', $c, $m ) ) { continue; }
			$tien_to = (string) $m[1];
			/* 🔴 CHÍNH TRANG TỔNG KHÔNG PHẢI MỘT BẢN CHI PHÍ. Nếu về sau nó có lớp tên
			   `VHCPT_Don` thì nó sẽ tự gom chính mình — vòng không đáy. Loại thẳng ở đây. */
			if ( 'T' === $tien_to ) { continue; }
			$khoa = ( '' === $tien_to ) ? self::KHOA_GOC : strtolower( $tien_to );
			$ra[ $khoa ] = array(
				'khoa'   => $khoa,
				'tienTo' => $tien_to,
				'ten'    => self::ten( $khoa ),
				'url'    => self::url( $tien_to ),
			);
		}
		ksort( $ra );
		self::$memo = $ra;
		return $ra;
	}

	/**
	 * ĐƯỜNG DẪN TRANG CỦA MỘT BẢN — để trang tổng dẫn người ta sang đó mà sửa cấu hình.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"nguyên tắc từng bộ phận chi phí là riêng hết nhé… trang chi phí
	 * tổng nó lấy cấu hình hết bên các trang chi phí con, chứ nó không có cấu hình gì trong đó,
	 * sau này kế toán muốn sửa cả loại thì có link để đẩy vào trang"*.
	 *
	 * 🔴 TRANG TỔNG KHÔNG ĐẺ RA MỘT BẢN CẤU HÌNH THỨ HAI. Danh mục loại chi phí, mã tài khoản,
	 *    cơ sở, phân quyền — tất cả nằm ở trang mảng, và chỉ ở đó. Cho trang tổng sửa được nữa
	 *    là hai nơi khai cùng một thứ, rồi hai nơi lệch nhau; mà lệch ở danh mục tài khoản nghĩa
	 *    là bút toán MISA sai, thứ chỉ lộ khi đối chiếu sổ cuối kỳ.
	 *
	 * ⚠️ HỎI CHÍNH BẢN ẤY ĐƯỜNG DẪN CỦA NÓ, đừng ghép chuỗi "chi-phi-" + mã. Đường dẫn đổi được
	 *    ở màn Cài đặt của từng bản; ghép tay là ngày ai đó đổi thì link này dẫn vào 404.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	public static function url( $tien_to ) {
		$lop = 'VHCP' . $tien_to . '_App';
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! class_exists( $lop ) || ! method_exists( $lop, 'app_url' ) ) { return ''; }
		return (string) call_user_func( array( $lop, 'app_url' ) );
	}

	/** Một bản theo khoá, hoặc null. */
	public static function mot( $khoa ) {
		$ds = self::ds();
		$k  = strtolower( trim( (string) $khoa ) );
		return isset( $ds[ $k ] ) ? $ds[ $k ] : null;
	}

	/** Tên hiện ra của một bản. Chưa đặt thì dùng chính khoá viết hoa. */
	public static function ten( $khoa ) {
		$k = strtolower( trim( (string) $khoa ) );
		$v = get_option( self::O_TEN, array() );
		if ( is_array( $v ) && isset( $v[ $k ] ) && '' !== trim( (string) $v[ $k ] ) ) {
			return trim( (string) $v[ $k ] );
		}
		return strtoupper( $k );
	}

	/** Đặt tên hiện ra. Rỗng = trả về mặc định. */
	public static function dat_ten( $khoa, $ten ) {
		$k = strtolower( trim( (string) $khoa ) );
		if ( '' === $k ) { return false; }
		$v = get_option( self::O_TEN, array() );
		$v = is_array( $v ) ? $v : array();
		$t = trim( (string) $ten );
		if ( '' === $t ) { unset( $v[ $k ] ); } else { $v[ $k ] = $t; }
		update_option( self::O_TEN, $v );
		self::$memo = null;
		return true;
	}

	/**
	 * Tên một lớp của bản: lop('mtd','Don') -> 'VHCPMTD_Don'.
	 *
	 * ⚠️ Trả về tên lớp thôi, KHÔNG kiểm nó có tồn tại không — nơi gọi phải tự gác
	 *    `class_exists`/`method_exists` CÙNG HÀM với lời gọi (luật `tools/test/kiem-goi-cheo.php`).
	 *    Gác ở đây thì người đọc chỗ gọi không thấy được cái gác, mà bốn plugin cài độc lập nên
	 *    bản có thể lệch nhau bất cứ lúc nào.
	 */
	public static function lop( $khoa, $duoi ) {
		$b = self::mot( $khoa );
		if ( ! $b ) { return ''; }
		return 'VHCP' . $b['tienTo'] . '_' . $duoi;
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * MƯỢN PHIÊN CỦA BẢN KIA — chìa khoá để trang tổng dùng LÕI THẬT, không viết lại luật.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"Trang tổng là xem, duyệt, quyết toán, xuất misa, sau này kế toán
	 * và quản lý làm trên trang này, không làm trên trang bộ phận"*.
	 *
	 * 🔴 BA VIỆC ẤY NẰM SÂU TRONG LÕI CỦA TỪNG BẢN, và chúng hỏi `<Bản>_Auth` xem "ai đang gọi":
	 *      · `get_don()`      -> `loi_khong_phai_don_minh()` -> `VHCP_DonVi::vi_sao_khong_dung()`
	 *      · `export_misa()`  -> lọc theo đơn vị và bộ phận của người gọi
	 *      · `xac_nhan_quyet_toan_cn()` -> ghi tên người quyết toán vào chứng từ
	 *    Trang tổng mượn SỔ NGƯỜI DÙNG của các bản nhưng trước nay không mượn PHIÊN, nên mấy
	 *    hàm ấy hỏi một câu mà bên kia không có ngữ cảnh để trả lời.
	 *
	 * 🔴 CHÉP LUẬT SANG ĐÂY LÀ CON ĐƯỜNG SAI. Luật quyết toán có chỗ tinh (chênh lệch, bù trừ
	 *    tuần trước, đơn vừa cá nhân vừa NCC); luật MISA có bảng tài khoản riêng cho từng loại
	 *    chi phí. Dựng bản thứ hai cho cùng một luật là hai bản lệch nhau — và lệch ở đây nghĩa
	 *    là chứng từ xuất ra sai, thứ chỉ lộ khi kế toán đối chiếu sổ cuối kỳ.
	 *
	 * ⚠️ MƯỢN PHIÊN KHÔNG PHẢI NỚI QUYỀN. Vai đặt vào là vai của chính người ấy TRONG SỔ CỦA BẢN
	 *    ẤY — đọc ra lúc đăng nhập, không phải thứ trình duyệt gửi lên. Nên mọi chốt bên kia vẫn
	 *    chạy y như khi họ đăng nhập thẳng vào trang mảng; ta chỉ nói cho bên ấy biết ai đang gõ.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	public static function muon_phien( $khoa ) {
		$b = self::mot( $khoa );
		if ( ! $b ) { return false; }
		$ng = VHCPT_Auth::toi();
		if ( ! $ng || ! isset( $ng['bans'][ $b['khoa'] ] ) ) { return false; }
		$hs = $ng['bans'][ $b['khoa'] ];
		$lop = self::lop( $khoa, 'Auth' );
		/* ⚠️ Gác `class_exists`/`method_exists` CÙNG HÀM với lời gọi — luật `kiem-goi-cheo.php`. */
		if ( ! $lop || ! class_exists( $lop ) || ! method_exists( $lop, 'dat_vai_tro' ) ) { return false; }
		call_user_func(
			array( $lop, 'dat_vai_tro' ),
			isset( $hs['vai'] ) ? $hs['vai'] : '',
			(string) $ng['ten'],
			isset( $hs['coso'] ) ? $hs['coso'] : '',
			isset( $hs['boPhan'] ) ? $hs['boPhan'] : '',
			isset( $hs['maNv'] ) ? $hs['maNv'] : ''
		);
		return true;
	}

	/** Tiền tố bảng của một bản: 'wp_vhcpmtd_'. */
	public static function tien_to_bang( $khoa ) {
		global $wpdb;
		$b = self::mot( $khoa );
		if ( ! $b ) { return ''; }
		return $wpdb->prefix . 'vhcp' . strtolower( $b['tienTo'] ) . '_';
	}
}
