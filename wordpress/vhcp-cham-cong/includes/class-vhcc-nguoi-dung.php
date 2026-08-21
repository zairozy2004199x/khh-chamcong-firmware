<?php
/**
 * DANH SÁCH NGƯỜI DÙNG RIÊNG của plugin chấm công.
 *
 * Anh Thắng: *"anh để chỉ plugin này thôi mà"* — plugin phải chạy được MỘT MÌNH, không bắt cài
 * kèm plugin Vận Hành Chi Phí chỉ để có chỗ khai người dùng.
 *
 * Trước bản này, chọn "Danh sách riêng" là tắc: option `vhcc_nguoidung` chỉ được ĐỌC, không màn
 * nào ghi vào. Nghĩa là chọn xong thì không ai đăng nhập được, và màn hình không hề nói ra. Đây
 * là lớp bù chỗ đó.
 *
 * 🔴 KHÔNG BAO GIỜ IN PIN RA MÀN HÌNH. Chỉ hiện số chữ số. Ảnh màn hình đi khắp nơi — trong
 *    chính việc này đã mất một khoá cầu nối vì một ảnh gửi qua chat. Sửa PIN thì gõ lại, không
 *    hiện PIN cũ ra để sửa.
 *
 * 🔴 KHÔNG XOÁ ĐƯỢC NGƯỜI CUỐI CÙNG CÒN VÀO ĐƯỢC. Xoá là không ai đăng nhập nổi nữa, mà đường
 *    lùi duy nhất là sửa thẳng cơ sở dữ liệu. Cùng luật với "không xoá ADMIN cuối cùng".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NguoiDung {

	const O = 'vhcc_nguoidung';

	/**
	 * PIN bị chặn. Dùng lại ĐÚNG danh sách của màn Phân quyền — hai bản danh sách PIN yếu thì
	 * sớm muộn lệch nhau, và bên lỏng hơn thành cửa vào.
	 */
	public static function pin_bi_cam() { return VHCC_Quyen::PIN_CAM; }

	/** Danh sách đã chuẩn hoá: [ ['id','ten','pin','vaiTro','coso'], … ] */
	public static function ds() {
		$tho = get_option( self::O );
		$ra  = array();
		foreach ( (array) $tho as $u ) {
			$u   = (array) $u;
			$ten = trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ra[] = array(
				'id'     => (string) ( isset( $u['id'] ) ? $u['id'] : md5( $ten ) ),
				'ten'    => $ten,
				'pin'    => (string) ( isset( $u['pin'] ) ? $u['pin'] : '' ),
				'vaiTro' => (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : 'Kế toán cá nhân' ),
				'coso'   => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
			);
		}
		return $ra;
	}

	/** Bao nhiêu người trong danh sách này VÀO ĐƯỢC (vai trò nằm trong danh sách cho vào). */
	public static function so_vao_duoc( $ds = null ) {
		$ds  = ( null === $ds ) ? self::ds() : $ds;
		$cho = VHCC_Auth::vai_tro_vao();
		$n   = 0;
		foreach ( $ds as $u ) { if ( in_array( $u['vaiTro'], $cho, true ) ) { $n++; } }
		return $n;
	}

	/**
	 * Thêm hoặc sửa một người.
	 *
	 * @param string $id     rỗng = thêm mới.
	 * @param string $pin    rỗng KHI SỬA = giữ PIN cũ (vì màn hình không hiện PIN cũ ra).
	 */
	public static function luu( $id, $ten, $pin, $vai_tro, $coso ) {
		$id      = trim( (string) $id );
		$ten     = trim( (string) $ten );
		$pin     = trim( (string) $pin );
		$vai_tro = trim( (string) $vai_tro );
		$coso    = trim( (string) $coso );

		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }
		if ( ! in_array( $vai_tro, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) {
			return array( 'ok' => false, 'error' => 'Vai trò không hợp lệ.' );
		}

		$ds  = self::ds();
		$cu  = null;
		foreach ( $ds as $u ) { if ( '' !== $id && $u['id'] === $id ) { $cu = $u; break; } }
		if ( '' !== $id && null === $cu ) {
			return array( 'ok' => false, 'error' => 'Không thấy người cần sửa (đã bị xoá?).' );
		}

		/* Sửa mà để trống ô PIN = giữ PIN cũ. Bắt gõ lại PIN mỗi lần đổi tên là mời người ta
		   đặt một PIN dễ nhớ hơn cho đỡ phiền. */
		if ( '' === $pin && null !== $cu ) { $pin = $cu['pin']; }

		$loi = self::pin_hop_le( $pin );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		foreach ( $ds as $u ) {
			if ( $u['pin'] === $pin && ( null === $cu || $u['id'] !== $cu['id'] ) ) {
				return array( 'ok' => false, 'error' => 'PIN này đã cấp cho ' . $u['ten']
					. '. Hai người cùng PIN thì nhật ký không phân biệt được ai làm việc gì.' );
			}
		}

		if ( null === $cu ) {
			$ds[] = array( 'id' => bin2hex( random_bytes( 8 ) ), 'ten' => $ten, 'pin' => $pin,
				'vaiTro' => $vai_tro, 'coso' => $coso );
		} else {
			foreach ( $ds as $i => $u ) {
				if ( $u['id'] === $cu['id'] ) {
					$ds[ $i ] = array( 'id' => $cu['id'], 'ten' => $ten, 'pin' => $pin,
						'vaiTro' => $vai_tro, 'coso' => $coso );
				}
			}
		}
		update_option( self::O, $ds, false );
		return array( 'ok' => true, 'thong_bao' => ( null === $cu ? 'Đã thêm ' : 'Đã sửa ' ) . $ten . '.' );
	}

	/** PIN dùng cho cổng đăng nhập trang chấm công: 4–8 chữ số (đúng khuôn `VHCC_Auth::login`). */
	public static function pin_hop_le( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return 'PIN phải là 4–8 CHỮ SỐ. (Số 0 ở đầu vẫn tính — nhưng nếu chép từ Google Sheets '
				. 'thì kiểm lại, Sheets coi 0123 là số nên lưu thành 123.)';
		}
		if ( in_array( $pin, self::pin_bi_cam(), true ) ) {
			return 'PIN này nằm trong danh sách bị chặn: hoặc quá dễ đoán, hoặc đã bị lộ.';
		}
		if ( preg_match( '/^(\d)\1+$/', $pin ) ) {
			return 'PIN không được là một chữ số lặp lại (1111, 222222…).';
		}
		if ( false !== strpos( '012345678901234567890', $pin )
			|| false !== strpos( '098765432109876543210', $pin ) ) {
			return 'PIN không được là dãy liên tiếp (1234, 654321…).';
		}
		return '';
	}

	/**
	 * Xoá một người.
	 *
	 * ⚠️ Chặn khi đó là NGƯỜI CUỐI CÙNG còn vào được — xoá xong thì không ai đăng nhập nổi, và
	 *    đường lùi duy nhất là sửa thẳng cơ sở dữ liệu.
	 */
	public static function xoa( $id ) {
		$id = trim( (string) $id );
		$ds = self::ds();
		$ai = null;
		foreach ( $ds as $u ) { if ( $u['id'] === $id ) { $ai = $u; break; } }
		if ( null === $ai ) { return array( 'ok' => false, 'error' => 'Không thấy dòng cần xoá.' ); }

		$cho = VHCC_Auth::vai_tro_vao();
		if ( in_array( $ai['vaiTro'], $cho, true ) && self::so_vao_duoc( $ds ) <= 1 ) {
			return array( 'ok' => false, 'error' => 'Đây là người CUỐI CÙNG còn vào được hệ thống. '
				. 'Xoá là không ai đăng nhập nổi nữa, mà đường lùi duy nhất là sửa thẳng cơ sở dữ liệu. '
				. 'Thêm người khác trước đã.' );
		}

		$moi = array();
		foreach ( $ds as $u ) { if ( $u['id'] !== $id ) { $moi[] = $u; } }
		update_option( self::O, $moi, false );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá ' . $ai['ten'] . '.' );
	}
}
