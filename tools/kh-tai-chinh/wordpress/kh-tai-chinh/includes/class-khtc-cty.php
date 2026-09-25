<?php
/**
 * Hai pháp nhân KH Cũ / KH Mới.
 *
 * Mọi bảng đều mang cột `cty`, và mọi truy vấn đều lọc theo công ty đang chọn.
 * Bản gốc có chỗ quên lọc nên số của hai công ty lẫn vào nhau; ở đây gom việc
 * chọn công ty về một chỗ để không lặp lại lỗi đó.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Cty {

	const MAC_DINH = 'kh_cu';

	public static function ds() {
		return array(
			'kh_cu'  => array( 'ten' => 'KH Cũ', 'ma' => 'KH989', 'day_du' => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H' ),
			'kh_moi' => array( 'ten' => 'KH Mới', 'ma' => 'KH705', 'day_du' => 'CÔNG TY TNHH K&H (mới)' ),
		);
	}

	/** Mã ngắn kế toán quen dùng trong tên file, tên đợt: KH989 / KH705. */
	public static function ma( $c = null ) {
		$c = $c ?: self::dang_chon();
		return self::ds()[ $c ]['ma'] ?? strtoupper( $c );
	}

	/**
	 * Công ty đang xem. Lưu theo TỪNG NGƯỜI DÙNG (user meta) chứ không phải một
	 * tuỳ chọn chung: hai kế toán mở cùng lúc, mỗi người xem một công ty, mà đặt
	 * chung một chỗ thì người này đổi là màn hình người kia nhảy theo.
	 */
	/** Pháp nhân máy đang ép tạm (nạp lô ghi sang bên kia) — không ghi vào người dùng. */
	private static $ep = '';

	/**
	 * Người này chỉ được xem một pháp nhân? '' = cả hai. Lưu ở user meta
	 * khtc_chi_cty; đặt ở màn hình Người dùng.
	 */
	public static function chi_duoc( $user_id = 0 ) {
		$c = get_user_meta( $user_id ?: get_current_user_id(), 'khtc_chi_cty', true );
		return isset( self::ds()[ $c ] ) ? $c : '';
	}

	public static function dang_chon() {
		if ( '' !== self::$ep ) { return self::$ep; }
		$chi = self::chi_duoc();
		if ( '' !== $chi ) { return $chi; }   // bị giới hạn thì không có lựa chọn
		$c = get_user_meta( get_current_user_id(), 'khtc_cty', true );
		return isset( self::ds()[ $c ] ) ? $c : self::MAC_DINH;
	}

	/**
	 * Chọn pháp nhân. $ep = true là máy tự chuyển tạm để ghi sang bên kia (nạp
	 * lô), không đụng lựa chọn của người và không bị giới hạn chặn; $ep = ''
	 * để tắt ép. Người bị giới hạn thì chọn tay không có tác dụng.
	 */
	public static function chon( $c, $ep = false ) {
		if ( $ep ) {
			self::$ep = isset( self::ds()[ $c ] ) ? $c : '';
			return;
		}
		if ( '' !== self::chi_duoc() ) { return; }
		if ( isset( self::ds()[ $c ] ) ) {
			update_user_meta( get_current_user_id(), 'khtc_cty', $c );
		}
	}

	/** Tắt ép tạm. */
	public static function thoi_ep() { self::$ep = ''; }

	public static function ten( $c = null ) {
		$c = $c ? $c : self::dang_chon();
		$ds = self::ds();
		return isset( $ds[ $c ] ) ? $ds[ $c ]['ten'] : $c;
	}

	public static function ten_day_du( $c = null ) {
		$c = $c ? $c : self::dang_chon();
		$ds = self::ds();
		return isset( $ds[ $c ] ) ? $ds[ $c ]['day_du'] : $c;
	}
}
