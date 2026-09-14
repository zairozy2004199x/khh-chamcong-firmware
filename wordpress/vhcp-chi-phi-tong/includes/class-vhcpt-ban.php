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
			);
		}
		ksort( $ra );
		self::$memo = $ra;
		return $ra;
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

	/** Tiền tố bảng của một bản: 'wp_vhcpmtd_'. */
	public static function tien_to_bang( $khoa ) {
		global $wpdb;
		$b = self::mot( $khoa );
		if ( ! $b ) { return ''; }
		return $wpdb->prefix . 'vhcp' . strtolower( $b['tienTo'] ) . '_';
	}
}
