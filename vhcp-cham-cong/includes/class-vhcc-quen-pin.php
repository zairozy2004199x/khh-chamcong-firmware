<?php
/**
 * LẤY LẠI PIN BẰNG HỌ TÊN + SỐ CĂN CƯỚC.
 *
 * =============================================================================================
 * Anh Thắng 28/08/2026: *"Thiếu phần lấy lại mã PIN ( Để lấy lại mã PIN nhập Họ Tên và số Căn
 * Cước Công Dân )"*, và chốt phạm vi: MỌI VAI, kể cả Admin.
 * =============================================================================================
 *
 * 🔴 KHÔNG HIỆN PIN CŨ. Luật của trang này từ đầu: không bao giờ in PIN ra màn hình — trang
 *    chạy ngoài internet và ảnh chụp màn hình đi khắp nơi. "Lấy lại" ở đây nghĩa là: khớp danh
 *    tính thì cho ĐẶT PIN MỚI ngay tại chỗ. Cùng kết quả, mà không lộ gì.
 *
 * 🔴 VÀ PHẢI NÓI THẲNG CHỖ YẾU: HỌ TÊN + CCCD LÀ DANH TÍNH, KHÔNG PHẢI BÍ MẬT.
 *    Họ tên thì công khai; số CCCD thì lộ khắp nơi. Ai cầm hai thứ ấy của một người là đặt lại
 *    được PIN của họ — và với Admin thì đó là cả hệ. Anh Thắng biết và chọn vậy (28/08/2026),
 *    nên đây không phải chuyện quên; nhưng vì thế MẤY CHỐT DƯỚI ĐÂY LÀ THỨ DUY NHẤT CÒN LẠI:
 *
 *      • Hãm dò theo IP  — sai 5 lần là khoá 10 phút. Không có nó thì quét CCCD là chuyện của
 *        một buổi chiều.
 *      • Thẻ hai bước    — bước 1 khớp danh tính mới phát thẻ ký HMAC hạn 5 phút; bước 2 đặt
 *        PIN phải mang thẻ. Thiếu nó thì gọi thẳng bước 2 với một Mã NV bất kỳ là xong.
 *      • Nhật ký         — mỗi lần đổi PIN qua đường này ghi lại tên + lúc + IP, để còn soát.
 *      • Hồ sơ CHƯA khai CCCD thì KHÔNG dùng được đường này, và nói rõ vì sao. Nhận hồ sơ
 *        CCCD rỗng là ai gõ tên đúng + để trống ô CCCD cũng qua.
 *
 * ⚠️ ĐỔI PIN Ở `nhan_vien.pin_dang_nhap` — sổ THẬT của hồ sơ nhân sự. Anh Thắng 28/08/2026:
 *    *"vẫn lấy từ 1 nguồn nhân sự hết"*. Đúng: sửa một chỗ thì mọi trang đọc nguồn `ho_so` đổi
 *    theo ngay, khỏi đi cấp lại PIN ở từng hệ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_QuenPin {

	/** Sai bao nhiêu lần thì khoá, và khoá bao lâu. */
	const SAI_TOI_DA = 5;
	const KHOA_GIAY  = 600;

	/** Thẻ bước 2 sống bao lâu. Ngắn: nó là chìa khoá đặt lại PIN của một người. */
	const THE_GIAY = 300;

	const O_NHAT_KY   = 'vhcc_nhat_ky_quen_pin';
	const NHAT_KY_TOI_DA = 200;

	/* ------------------------------------------------------------------ hãm dò */

	private static function khoa_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcc_qpin_' . md5( $ip );
	}
	public static function bi_khoa() {
		return (int) get_transient( self::khoa_key() ) >= self::SAI_TOI_DA;
	}
	private static function dem_sai() {
		$k = self::khoa_key();
		set_transient( $k, (int) get_transient( $k ) + 1, self::KHOA_GIAY );
	}
	public static function mo_khoa() { delete_transient( self::khoa_key() ); }

	/* ------------------------------------------------------------------ thẻ hai bước */

	private static function bi_mat() {
		return wp_salt( 'auth' ) . '|vhcc-quen-pin';
	}

	/**
	 * Thẻ cho bước 2: `<ma_nv>.<hết hạn>.<chữ ký>`.
	 *
	 * ⚠️ KÝ CẢ MÃ LẪN HẠN. Ký mỗi mã thì thẻ sống mãi; ký mỗi hạn thì đổi mã trong thẻ là đặt
	 *    lại PIN của người khác.
	 */
	public static function phat_the( $ma_nv ) {
		$ma  = trim( (string) $ma_nv );
		$het = time() + self::THE_GIAY;
		$p   = $ma . '.' . $het;
		return $p . '.' . hash_hmac( 'sha256', $p, self::bi_mat() );
	}

	/** Thẻ hợp lệ -> trả Mã NV; không thì ''. */
	public static function doc_the( $the ) {
		$x = explode( '.', (string) $the );
		if ( 3 !== count( $x ) ) { return ''; }
		list( $ma, $het, $sig ) = $x;
		$mong = hash_hmac( 'sha256', $ma . '.' . $het, self::bi_mat() );
		if ( ! hash_equals( $mong, (string) $sig ) ) { return ''; }
		if ( time() > (int) $het ) { return ''; }
		return (string) $ma;
	}

	/* ------------------------------------------------------------------ bước 1: tra danh tính */

	/**
	 * Khớp Họ tên + CCCD -> thẻ đặt PIN.
	 *
	 * ⚠️ TÊN SO BẰNG KHOÁ BỎ DẤU (`VHCC_NhanSu::khoa_so`), CCCD so bằng CHỮ SỐ. Bắt gõ đúng
	 *    từng dấu là người ta không bao giờ qua nổi; còn CCCD thì người gõ hay chèn khoảng
	 *    trắng hoặc chấm.
	 *
	 * 🔴 CÂU CHỐI PHẢI GIỐNG NHAU CHO MỌI CA HỎNG. Nói "không có ai tên đó" khác với "tên đúng
	 *    mà CCCD sai" là biến ô này thành máy dò xem một người có trong công ty hay không.
	 */
	public static function tra( $ho_ten, $cccd ) {
		global $wpdb;
		if ( self::bi_khoa() ) {
			return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút.' );
		}
		$ten_k  = VHCC_NhanSu::khoa_so( (string) $ho_ten );
		$cccd_k = preg_replace( '/\D+/', '', (string) $cccd );
		$chung  = 'Không khớp. Kiểm lại họ tên và số căn cước — phải đúng như trong hồ sơ nhân sự.';

		if ( '' === $ten_k || '' === $cccd_k ) {
			return array( 'ok' => false, 'error' => 'Nhập cả họ tên và số căn cước.' );
		}

		/* ⛔ `TRIM(cccd) <> ''` ở đây là lọc CHO GỌN, không phải chốt an toàn: vòng lặp dưới so
		   CCCD bằng chữ số, mà `$cccd_k` đã bị chặn rỗng ở trên — nên hồ sơ CCCD rỗng không
		   khớp nổi dù có lọt vào. Phá thử xác nhận: bỏ nó đi cho ra kết quả y hệt. Giữ vì nó
		   nói ra ý ("chỉ xét hồ sơ đã khai căn cước") và bớt hàng phải duyệt. */
		$rows = VHCC_DB::rows( 'SELECT ma_nv, ho_ten, cccd FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE TRIM(cccd) <> '' AND TRIM(ma_nv) <> ''" );
		$thay = null;
		foreach ( (array) $rows as $r ) {
			if ( VHCC_NhanSu::khoa_so( (string) $r['ho_ten'] ) !== $ten_k ) { continue; }
			if ( preg_replace( '/\D+/', '', (string) $r['cccd'] ) !== $cccd_k ) { continue; }
			$thay = $r;
			break;
		}
		if ( ! $thay ) {
			self::dem_sai();
			return array( 'ok' => false, 'error' => $chung );
		}
		/* ⚠️ KHỚP RỒI THÌ XOÁ ĐẾM SAI. Không xoá thì người gõ nhầm hai lần rồi gõ đúng vẫn bị
		   khoá ở lần sau — hãm dò quay ra hãm chính người có quyền. */
		self::mo_khoa();
		return array( 'ok' => true, 'ma_nv' => (string) $thay['ma_nv'],
			'ho_ten' => (string) $thay['ho_ten'], 'the' => self::phat_the( (string) $thay['ma_nv'] ) );
	}

	/* ------------------------------------------------------------------ bước 2: đặt PIN mới */

	/**
	 * Đặt PIN mới cho người mà thẻ chỉ tới.
	 *
	 * ⚠️ PIN TRÙNG NGƯỜI KHÁC LÀ HAI NGƯỜI CHUNG MỘT CỬA. Cổng đăng nhập tra theo PIN, nên hai
	 *    hồ sơ cùng PIN thì ai gõ vào cũng rơi vào hồ sơ đứng trước — và người kia mất tài
	 *    khoản mà không hiểu vì sao.
	 */
	public static function dat( $the, $pin_moi ) {
		global $wpdb;
		$ma = self::doc_the( $the );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Phiên đặt PIN đã hết hạn (5 phút). Nhập lại họ tên '
				. 'và số căn cước.' );
		}
		$pin = preg_replace( '/\D+/', '', (string) $pin_moi );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số.' );
		}
		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ.' ); }

		$trung = $wpdb->get_var( $wpdb->prepare(
			'SELECT ma_nv FROM ' . VHCC_DB::t( 'nhan_vien' )
			. ' WHERE pin_dang_nhap=%s AND ma_nv<>%s LIMIT 1', $pin, $ma ) );
		if ( $trung ) {
			return array( 'ok' => false, 'error' => 'PIN này đã có người dùng — chọn số khác.' );
		}

		$wpdb->update( VHCC_DB::t( 'nhan_vien' ),
			array( 'pin_dang_nhap' => $pin, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );

		/* Người này đã được đẩy sang hệ ghế thì bản sao bên ấy phải theo — xem `VHCC_DayGhe`. */
		if ( class_exists( 'VHCC_DayGhe' ) && method_exists( 'VHCC_DayGhe', 'dong_bo' ) ) {
			VHCC_DayGhe::dong_bo( $ma );
			/* Bản sao bên Vận hành chi phí cũng phải theo — cùng lý do, cùng lúc. */
			if ( class_exists( 'VHCC_DayChiPhi' ) && method_exists( 'VHCC_DayChiPhi', 'dong_bo' ) ) {
				VHCC_DayChiPhi::dong_bo( $ma );
			}
		}
		self::ghi( $ma, (string) $hs['ho_ten'] );
		return array( 'ok' => true, 'ma_nv' => $ma, 'ho_ten' => (string) $hs['ho_ten'] );
	}

	/* ------------------------------------------------------------------ nhật ký */

	/**
	 * 🔴 GHI LẠI MỖI LẦN ĐỔI PIN QUA ĐƯỜNG NÀY, VÀ KHÔNG GHI PIN.
	 *    Đây là đường vào duy nhất không cần biết PIN cũ. Không có sổ thì ngày ai đó dùng CCCD
	 *    của người khác để chiếm tài khoản, không còn một dấu vết nào để lần.
	 */
	private static function ghi( $ma_nv, $ho_ten ) {
		$ds = get_option( self::O_NHAT_KY );
		if ( ! is_array( $ds ) ) { $ds = array(); }
		array_unshift( $ds, array(
			'luc'    => current_time( 'mysql' ),
			'ma_nv'  => (string) $ma_nv,
			'ho_ten' => (string) $ho_ten,
			'ip'     => isset( $_SERVER['REMOTE_ADDR'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		) );
		update_option( self::O_NHAT_KY, array_slice( $ds, 0, self::NHAT_KY_TOI_DA ), false );
	}

	public static function nhat_ky() {
		$ds = get_option( self::O_NHAT_KY );
		return is_array( $ds ) ? $ds : array();
	}

	/** Có bao nhiêu hồ sơ khai CCCD — dưới ngưỡng thì đường này gần như vô dụng, phải nói ra. */
	public static function so_co_cccd() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE TRIM(cccd) <> '' AND TRIM(ma_nv) <> ''" );
	}
}
