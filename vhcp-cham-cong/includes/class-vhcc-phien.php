<?php
/**
 * PHIÊN DÙNG CHUNG CHO MỌI TRANG CỦA HỆ — bộ nối để trang mới cắm vào là chạy.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =============================================================================================
 * Anh Thắng 30/08/2026: *"Đăng nhập 1 trang nội bộ là dùng được tất cả các trang"*, rồi
 * *"nhưng sau trang mới thì dùng chung hết, thiết lập sẵn luôn"*.
 *
 * Trang Nội bộ đã làm được việc ấy, nhưng làm bằng cách tự viết lấy năm hàm trong plugin của
 * nó: đọc cookie, hỏi thẻ, nhận PIN, đóng phiên, ký biểu mẫu. Trang thứ ba mà chép lại năm hàm
 * ấy là có hai bản; trang thứ tư là ba bản. Rồi một hôm đổi tuổi thọ cookie ở đây, ba bản kia
 * không ai đổi theo — và cái sai đó không hiện ra ngay, nó hiện ra vào lúc có người bị đá khỏi
 * phiên giữa ca.
 *
 * Nên: LÕI PHIÊN nằm ở ĐÂY, một bản. Trang mới chỉ gọi.
 *
 * =============================================================================================
 * 🔴 MỘT CỬA PIN NGHĨA LÀ MỘT BỘ CHỐT
 * =============================================================================================
 * `vao()` gọi thẳng `VHCC_Auth::login()` chứ không tự so PIN. Bộ đếm nhập sai và cái khoá 10
 * phút nằm ở đó. Trang nào tự so PIN lấy là hệ có hai bộ đếm rời nhau — kẻ dò PIN bị khoá ở
 * trang này cứ sang trang kia dò tiếp, mà nhìn vào mã trang nào cũng thấy "có khoá".
 *
 * =============================================================================================
 * ⚠️ TRANG MỚI CẮM VÀO THẾ NÀO
 * =============================================================================================
 * Xem `docs/TRANG-MOI-DUNG-CHUNG-DANG-NHAP.md`. Tóm tắt: gác `class_exists`/`method_exists`
 * cùng thân hàm với lời gọi (luật `tools/test/kiem-goi-cheo.php`), rồi
 *
 *     $viec = VHCC_Phien::xu_post( 'vhxx' );      // xử PIN + Thoát, KHÔNG tự chuyển hướng
 *     $toi  = VHCC_Phien::toi();
 *     if ( ! $toi ) { echo VHCC_Phien::o_pin( array( 'loi' => $viec['loi'] ) ); }
 *
 * 🔴 KHÔNG HÀM NÀO Ở ĐÂY `exit` HAY `wp_safe_redirect`. Hàm có `exit` thì bài kiểm gọi nó là
 *    bài kiểm tự chết giữa đường — và phần đáng thử nhất của một cửa đăng nhập chính là phần
 *    quyết định cho vào hay không.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Phien {

	/**
	 * Hệ đã sẵn sàng chưa.
	 *
	 * ⚠️ DÒ TỪNG HÀM, KHÔNG DÒ MỖI TÊN LỚP: lớp CÓ mà hàm KHÔNG là Fatal error, trắng cả trang.
	 */
	public static function co() {
		return class_exists( 'VHCC_Auth' ) && method_exists( 'VHCC_Auth', 'user_by_token' )
			&& class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'mo_phien' );
	}

	/**
	 * Thẻ đang nằm trong cookie, hoặc chuỗi rỗng.
	 *
	 * ⚠️ HÀM NÀY TRẢ RA MỘT THỨ BÍ MẬT. Không in ra trang, không ghi nhật ký, không gửi đi đâu.
	 *    Nó có mặt để trang khác BUỘC CHỮ KÝ BIỂU MẪU vào phiên, chứ không phải để hiện lên.
	 */
	public static function the() {
		if ( ! defined( 'VHCC_Web::COOKIE' ) ) { return ''; }
		$c = constant( 'VHCC_Web::COOKIE' );
		if ( ! $c || empty( $_COOKIE[ $c ] ) ) { return ''; }
		return (string) $_COOKIE[ $c ];
	}

	/**
	 * Người đang đăng nhập, hoặc null.
	 *
	 * Cửa RỘNG: mọi vai mà `VHCC_Auth::vai_tro_vao()` cho vào hệ. Ai được xem MÀN NÀO là việc
	 * của chính trang ấy, không phải của cửa này — xem `VHCC_Web::nguoi_vao()` đã chốt vậy từ
	 * trước: cửa vào rộng, cửa từng màn hẹp.
	 */
	public static function toi() {
		$tok = self::the();
		if ( '' === $tok ) { return null; }
		if ( ! class_exists( 'VHCC_Auth' ) || ! method_exists( 'VHCC_Auth', 'user_by_token' ) ) { return null; }
		return VHCC_Auth::user_by_token( $tok );
	}

	/**
	 * NHẬN PIN, MỞ PHIÊN CHUNG.
	 *
	 * 🔴 KHÔNG BAO GIỜ ĐƯA PIN VÀO CÂU BÁO TRẢ RA. Câu báo hiện lên trang, mà trang chạy ngoài
	 *    internet và ảnh chụp màn hình thì đi khắp nơi.
	 *
	 * @param  string $pin
	 * @return array  ok:bool · loi:string
	 */
	public static function vao( $pin ) {
		if ( ! self::co() ) {
			return array( 'ok' => false, 'loi' => 'Chưa cài plugin Chấm công trên site này, '
				. 'nên chưa đăng nhập được. Nhờ quản trị cài rồi kích hoạt nó.' );
		}
		if ( ! method_exists( 'VHCC_Auth', 'login' ) || ! method_exists( 'VHCC_Web', 'mo_phien' ) ) {
			return array( 'ok' => false, 'loi' => 'Bản plugin Chấm công đang cài chưa mở đường '
				. 'đăng nhập dùng chung. Nhờ quản trị cập nhật plugin Chấm công.' );
		}

		$kq = VHCC_Auth::login( (string) $pin );
		if ( empty( $kq['ok'] ) ) {
			return array( 'ok' => false,
				'loi' => isset( $kq['error'] ) ? (string) $kq['error'] : 'PIN không đúng.' );
		}

		/* 🔴 GÁC BẰNG ĐÚNG DANH SÁCH MÀ `user_by_token` DÙNG. Lệch một cái là người ta gõ PIN
		   đúng, cookie được đặt, rồi lượt sau `toi()` trả null — màn hình quay lại y như cũ,
		   không một câu giải thích, và họ gõ lại mười lần cho tới lúc tự khoá mình. */
		if ( method_exists( 'VHCC_Auth', 'vai_tro_vao' )
			&& ! in_array( (string) $kq['role'], VHCC_Auth::vai_tro_vao(), true ) ) {
			return array( 'ok' => false, 'loi' => 'Tài khoản ' . (string) $kq['name'] . ' ('
				. (string) $kq['role'] . ') chưa được mở vào hệ thống. Vai vào được: '
				. implode( ' · ', VHCC_Auth::vai_tro_vao() ) . '.' );
		}

		if ( ! VHCC_Web::mo_phien( (string) $kq['token'] ) ) {
			return array( 'ok' => false, 'loi' => 'Không mở được phiên. Thử lại một lượt nữa.' );
		}
		return array( 'ok' => true, 'loi' => '' );
	}

	/**
	 * THOÁT — huỷ THẺ trước, xoá COOKIE sau.
	 *
	 * 🔴 Mọi trang dùng chung MỘT thẻ, và trạm chấm công còn giữ chính thẻ ấy trong
	 *    localStorage. Xoá mỗi cookie thì chỉ trình duyệt này quên, thẻ vẫn sống ở chỗ khác —
	 *    người vừa bấm Thoát trên máy chung ở cửa hàng tưởng mình đã ra.
	 */
	public static function ra() {
		$tok = self::the();
		if ( '' === $tok ) { return false; }
		$xong = false;
		if ( class_exists( 'VHCC_Auth' ) && method_exists( 'VHCC_Auth', 'logout' ) ) {
			VHCC_Auth::logout( $tok );
			$xong = true;
		}
		if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'dong_phien' ) ) {
			VHCC_Web::dong_phien();
			$xong = true;
		}
		return $xong;
	}

	/* ==================================================================== chữ ký biểu mẫu */

	/**
	 * Chữ ký chống giả mạo biểu mẫu, buộc vào chính thẻ phiên.
	 *
	 * 🔴 KHÔNG dùng `wp_nonce_field` được: nonce của WordPress buộc vào tài khoản WordPress, mà
	 *    240 người ở đây không có tài khoản WordPress nào cả — nonce sẽ tính theo id 0, ai cũng
	 *    ra một chuỗi giống nhau, tức là chẳng chặn được gì.
	 *
	 * ⚠️ MỖI TRANG MỘT KHÔNG GIAN (`$ns`). Chữ ký của trang Nội bộ không dùng lại được ở trang
	 *    khác — một biểu mẫu cắt từ trang này dán sang trang kia thì chữ ký không khớp.
	 *
	 * @param string $ns  tên ngắn của trang, ví dụ 'vhnb'. Đặt một lần rồi ĐỪNG ĐỔI: đổi là
	 *                    mọi biểu mẫu đang mở trên máy người dùng hỏng hết.
	 */
	public static function chu_ky( $ns, $tok = null ) {
		$tok = ( null === $tok ) ? self::the() : (string) $tok;
		return hash_hmac( 'sha256', (string) $ns . '|' . $tok, wp_salt( 'nonce' ) );
	}

	/** Ô ẩn mang chữ ký — mọi biểu mẫu POST của trang dùng chung phiên đều phải có. */
	public static function o_ky( $ns ) {
		return '<input type="hidden" name="ky" value="' . esc_attr( self::chu_ky( $ns ) ) . '">';
	}

	/** Chữ ký gửi lên có đúng không. Chưa đăng nhập thì luôn SAI — không có thẻ, không có chữ ký. */
	public static function ky_dung( $ns ) {
		$tok = self::the();
		$gui = isset( $_POST['ky'] ) ? (string) wp_unslash( $_POST['ky'] ) : '';
		return ( '' !== $tok && '' !== $gui && hash_equals( self::chu_ky( $ns, $tok ), $gui ) );
	}

	/* ==================================================================== xử một lượt POST */

	/**
	 * XỬ LƯỢT POST ĐĂNG NHẬP / THOÁT — một lời gọi cho cả hai việc.
	 *
	 * 🔴 ĐĂNG NHẬP PHẢI ĐỨNG TRƯỚC CHỐT CHỮ KÝ. `ky_dung()` tính chữ ký theo THẺ PHIÊN, mà lượt
	 *    đăng nhập thì chưa có thẻ nào — gác nhầm thứ tự là ô PIN nằm đó nhìn thấy được nhưng
	 *    không bao giờ vào nổi, và không câu báo nào giải thích.
	 *
	 * 🔴 KHÔNG TỰ CHUYỂN HƯỚNG. Nơi gọi quyết định đi đâu (thường là về chính trang ấy), và
	 *    quan trọng hơn: hàm có `wp_safe_redirect` + `exit` thì bài kiểm không gọi được.
	 *
	 * @param  string $ns không gian chữ ký của trang gọi.
	 * @return array  viec: '' | 'vao' | 'ra' · ok:bool · loi:string
	 */
	public static function xu_post( $ns ) {
		$viec = isset( $_POST['viec'] ) ? sanitize_text_field( wp_unslash( $_POST['viec'] ) ) : '';
		$toi  = self::toi();

		if ( ! $toi && 'dang_nhap' === $viec ) {
			$r = self::vao( isset( $_POST['pin'] ) ? wp_unslash( $_POST['pin'] ) : '' );
			return array( 'viec' => 'vao', 'ok' => ! empty( $r['ok'] ), 'loi' => (string) $r['loi'] );
		}
		if ( $toi && 'thoat' === $viec && self::ky_dung( $ns ) ) {
			self::ra();
			return array( 'viec' => 'ra', 'ok' => true, 'loi' => '' );
		}
		return array( 'viec' => '', 'ok' => false, 'loi' => '' );
	}

	/* ==================================================================== hai mảnh HTML sẵn dùng */

	/**
	 * Ô GÕ PIN — dán vào trang mới là xong, không phải nghĩ lại từ đầu.
	 *
	 * 🔴 KHÔNG BAO GIỜ IN PIN RA. `type="password"`, và gõ sai thì ô về TRỐNG chứ không trả lại
	 *    giá trị vừa gõ. Trang chạy ngoài internet, ảnh chụp màn hình đi khắp nơi.
	 *
	 * ⚠️ Style để INLINE, không phụ thuộc bảng CSS của trang gọi — trang mới nào cũng dùng được
	 *    ngay, kể cả khi nó chưa có lớp `.nut` nào.
	 *
	 * @param array $dat loi · nhan (chữ trên nút) · goi_y (chữ mờ trong ô)
	 */
	public static function o_pin( $dat = array() ) {
		$loi   = isset( $dat['loi'] ) ? (string) $dat['loi'] : '';
		$nhan  = isset( $dat['nhan'] ) ? (string) $dat['nhan'] : 'Đăng nhập';
		$goi_y = isset( $dat['goi_y'] ) ? (string) $dat['goi_y'] : 'Mã PIN chấm công';

		$h = '';
		if ( '' !== $loi ) {
			$h .= '<div class="bao loi" style="background:#fef2f2;border:1px solid #fecaca;'
				. 'border-radius:9px;padding:11px 13px;margin:0 0 12px">' . esc_html( $loi ) . '</div>';
		}
		$h .= '<form method="post" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
			. '<input type="hidden" name="viec" value="dang_nhap">'
			. '<input type="password" name="pin" inputmode="numeric" autocomplete="off" required '
			. 'placeholder="' . esc_attr( $goi_y ) . '" '
			. 'style="flex:1;min-width:190px;padding:10px 12px;border:1px solid #cbd5e1;'
			. 'border-radius:8px;font-size:15px">'
			. '<button type="submit" style="font:inherit;font-weight:600;padding:10px 16px;'
			. 'border-radius:8px;border:1px solid #2563eb;background:#2563eb;color:#fff;cursor:pointer">'
			. esc_html( $nhan ) . '</button></form>';
		return $h;
	}

	/**
	 * NÚT THOÁT.
	 *
	 * 🔴 LÀ MỘT FORM POST CHỨ KHÔNG PHẢI ĐƯỜNG DẪN. Một cái link đăng xuất thì chỉ cần ai đó dán
	 *    địa chỉ ấy vào bảng tin — hoặc nhúng nó làm ảnh trong một bài — là cả phòng bị đá ra.
	 */
	public static function nut_thoat( $ns, $nhan = 'Thoát' ) {
		return '<form method="post" style="margin:0">' . self::o_ky( $ns )
			. '<input type="hidden" name="viec" value="thoat">'
			. '<button type="submit" style="font:inherit;font-weight:600;padding:7px 12px;'
			. 'border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;cursor:pointer">'
			. esc_html( $nhan ) . '</button></form>';
	}
}
