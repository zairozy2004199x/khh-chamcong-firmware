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

	/**
	 * MỘT TÀI KHOẢN ĐỂ VÀO ĐƯỢC LẦN ĐẦU.
	 *
	 * Cài xong mà chưa khai ai thì trang chấm công đứng ở cổng PIN với dòng "Chưa có tài
	 * khoản nào đăng nhập được" — đúng thứ anh Thắng gặp. Nguồn mặc định là "dùng chung với
	 * Vận Hành Chi Phí", mà bên đó vai trò còn có "Cửa hàng trưởng", "Hotline"… nên có thể
	 * không còn ai lọt vào danh sách được vào. Kết quả: cài đúng, dữ liệu đúng, mà không có
	 * đường vào.
	 *
	 * Chạy ĐÚNG MỘT LẦN (cờ vhcc_da_gieo): quản trị xoá tài khoản này đi là xoá hẳn, không
	 * mọc lại — không thì dọn tài khoản mặc định thành việc làm mãi không xong.
	 *
	 * 🔴 PIN SINH NGẪU NHIÊN, KHÔNG PHẢI 1111, và KHÔNG in ra trang công khai — chỉ hiện
	 *    trong wp-admin → Chấm Công → Cài đặt. Ảnh chụp màn hình đi khắp nơi; trong chính
	 *    dự án này đã mất một khoá cầu nối vì một ảnh gửi qua chat.
	 *
	 * ⚠️ ĐẶT Ở ĐÂY, KHÔNG ĐẶT TRONG class-vhcc-auth.php. Tệp đó là ĐƯỜNG ĐĂNG NHẬP; danh
	 *    sách PIN cấm không được có mặt ở đó, kẻo có ngày ai đó lỡ đem nó ra chặn lúc đăng
	 *    nhập — khoá người ta ra khỏi hệ thống của chính họ bằng một PIN họ đang dùng thật.
	 *    Có phép thử ghim đúng điều này.
	 */
	public static function gieo_lan_dau() {
		if ( get_option( 'vhcc_da_gieo' ) ) { return false; }
		update_option( 'vhcc_da_gieo', 1 );

		// Đã có người vào được rồi thì thôi, đừng mở thêm cửa.
		$dang_co = VHCC_Auth::users();
		if ( ! is_wp_error( $dang_co ) ) {
			$cho = VHCC_Auth::vai_tro_vao();
			foreach ( $dang_co as $u ) {
				if ( '' !== $u['pin'] && in_array( $u['vaiTro'], $cho, true ) ) { return false; }
			}
		}

		$pin = '';
		for ( $i = 0; $i < 6; $i++ ) { $pin .= (string) wp_rand( 0, 9 ); }
		while ( '' !== VHCC_Quyen::pin_hop_le( $pin ) ) {   // trúng PIN cấm thì bốc lại
			$pin = '';
			for ( $i = 0; $i < 6; $i++ ) { $pin .= (string) wp_rand( 0, 9 ); }
		}

		/* Đổi nguồn sang "danh sách riêng" — vì tài khoản vừa khai nằm ở danh sách riêng, để
		   nguồn 'chung' thì cổng vẫn đọc bảng của Vận Hành Chi Phí và PIN mới vẫn không vào
		   được. GHI LẠI nguồn cũ: màn Cài đặt phải nói thẳng là plugin đã đổi, và đổi ngược
		   chỉ là một cái nút radio — danh sách bên Vận Hành Chi Phí KHÔNG mất gì. */
		$nguon_cu = VHCC_Auth::nguon();
		if ( 'rieng' !== $nguon_cu ) {
			update_option( 'vhcc_nguon_nguoidung', 'rieng' );
			update_option( 'vhcc_gieo_doi_nguon', $nguon_cu );
		}

		$ds   = (array) get_option( self::O );
		$ds[] = array( 'id' => md5( 'admin-lan-dau' ), 'ten' => 'Admin', 'pin' => $pin,
			'vaiTro' => 'Admin', 'coso' => '' );
		update_option( self::O, $ds );
		update_option( 'vhcc_pin_lan_dau', $pin );          // để wp-admin hiện một lần
		return true;
	}

	/** Nguồn người dùng TRƯỚC lúc gieo, '' nếu không phải đổi. Chỉ để màn Cài đặt kể lại. */
	public static function gieo_doi_nguon() { return (string) get_option( 'vhcc_gieo_doi_nguon' ); }

	/** PIN lần đầu (chỉ wp-admin đọc); '' khi quản trị đã bấm "tôi ghi lại rồi". */
	public static function pin_lan_dau() { return (string) get_option( 'vhcc_pin_lan_dau' ); }
	public static function quen_pin_lan_dau() {
		delete_option( 'vhcc_pin_lan_dau' );
		delete_option( 'vhcc_gieo_doi_nguon' );
	}

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
