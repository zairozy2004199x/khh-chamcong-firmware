<?php
/**
 * VÉ CHUYỂN DANH TÍNH SANG APP KHÁC — một lần, sống 15 phút.
 *
 * =================================================================================================
 * 🔴 LỖI TỆP NÀY SINH RA ĐỂ ĐÓNG
 * =================================================================================================
 * Anh Thắng 19/09/2026, hai ảnh đặt cạnh nhau: trạm Chấm công đang là *Trần Ngọc Minh Truyền ·
 * TUTU_TP · Cửa hàng trưởng*, mà bấm sang Vận Hành Chi Phí thì hiện *Nguyễn Văn Bin · Nhân viên
 * · FARM_PT*. *"Khi vào tk của tôi thì vẫn là mình. Mà truy cập ứng dụng thì đang đăng nhập tk
 * khác. Phải tự link chung 1 tk chứ"*.
 *
 * KHÔNG PHẢI LỖI HIỂN THỊ. Hai app giữ phiên ở hai chỗ rời nhau:
 *   · trạm cất thẻ trong `localStorage` của trạm;
 *   · app chi phí cất thẻ RIÊNG của nó, sống 30 ngày.
 * Ô Ứng dụng chỉ là một đường dẫn trơn. Sang tới nơi, app kia không biết ai vừa bấm nên lấy thẻ
 * cũ còn sót trong máy — thẻ của người gần nhất đã gõ PIN trên chiếc điện thoại ấy.
 *
 * Hậu quả thật: người này tạo và duyệt đơn chi phí DƯỚI DANH NGHĨA người kia, sổ ghi tên người
 * kia, cơ sở người kia. Và chiều ngược lại, ai mượn máy cũng vào thẳng được app kia.
 *
 * =================================================================================================
 * ⚠️ VÌ SAO LÀ VÉ, KHÔNG PHẢI `?sso=` CÓ SẴN
 * =================================================================================================
 * App chi phí vốn nhận `?sso=<token>` ký bằng một chuỗi bí mật dùng chung. Nhưng chuỗi ấy phải
 * khai TAY trong từng plugin chi phí (năm bản, mỗi bản một ô cấu hình), và hiện đang để trống.
 * Chính mã nguồn bên đó đã viết: *"nhớ vào đổi tay là thứ không xảy ra"*.
 *
 * Vé thì không cần khai gì: hai plugin nằm trên CÙNG một site, nên app kia hỏi thẳng plugin này
 * "vé này của ai" và được trả lời. Không có bí mật nào để quên khai, không có bí mật nào để lộ.
 *
 * ⚠️ MỘT LẦN, RỒI THÔI. Đổi xong là vé chết. App kia lúc ấy đã tự phát thẻ của CHÍNH người ấy,
 *    nên lần mở sau không cần vé nữa — và cái thẻ cũ của người lạ đã bị đè mất. Vé dùng lại
 *    được thì một ảnh chụp màn hình có đường dẫn là một lối vào tài khoản người khác.
 * ⚠️ 15 PHÚT. Vé phát lúc trạm dựng lưới Ứng dụng, người ta bấm sau đó vài giây tới vài phút.
 *    Ngắn quá thì vé chết trước khi tay chạm tới ô; dài quá thì một đường dẫn rơi ra ngoài còn
 *    dùng được lâu.
 * ⚠️ VÉ CHẾT KHÔNG PHẢI LÀ THẢM HOẠ: app kia lùi về thẻ của chính nó — mà từ lần đổi đầu tiên,
 *    thẻ ấy đã là thẻ đúng người rồi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Ve {

	/** 15 phút — xem khối chú thích đầu lớp. */
	const SONG = 900;

	private static function khoa( $ve ) { return 'vhcc_ve_' . $ve; }

	/**
	 * Phát một vé cho người đang đăng nhập.
	 *
	 * @param array $u Người dùng ( name · role · coso · ma_nv ).
	 * @return string Chuỗi vé, hoặc '' nếu không đủ dữ kiện.
	 */
	public static function phat( $u ) {
		$ten = trim( (string) ( isset( $u['name'] ) ? $u['name'] : '' ) );
		$vai = trim( (string) ( isset( $u['role'] ) ? $u['role'] : '' ) );
		if ( '' === $ten || '' === $vai ) { return ''; }

		/* 🔴 32 BYTE NGẪU NHIÊN THẬT. `uniqid()` hay `rand()` đoán được từ thời điểm — mà đoán
		   trúng một vé là vào thẳng tài khoản người khác, không qua PIN nào. */
		try {
			$ve = bin2hex( random_bytes( 32 ) );
		} catch ( Exception $e ) {
			return '';                       // không có nguồn ngẫu nhiên thì THÔI, đừng bịa một cái yếu
		}

		set_transient( self::khoa( $ve ), array(
			'name'  => $ten,
			'role'  => $vai,
			'coso'  => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
			'ma_nv' => (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ),
		), self::SONG );
		return $ve;
	}

	/** Gắn vé vào một đường dẫn sang app khác. Không phát được vé thì trả nguyên đường dẫn. */
	public static function gan( $url, $u ) {
		$ve = self::phat( $u );
		if ( '' === $ve ) { return $url; }
		return add_query_arg( 'ccve', $ve, $url );
	}

	/**
	 * ĐỔI VÉ LẤY DANH TÍNH — app khác gọi hàm này.
	 *
	 * ⚠️ XOÁ TRƯỚC KHI TRẢ. Đọc xong mới xoá thì hai lượt gọi cùng lúc đều đọc được cùng một vé.
	 *    Đây là chỗ duy nhất biến vé thành danh tính, nên nó phải là chỗ duy nhất đếm được.
	 *
	 * @return array|null ( name · role · coso · ma_nv ), hoặc `null` nếu vé sai / hết hạn / đã dùng.
	 */
	public static function doi( $ve ) {
		$ve = trim( (string) $ve );
		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/', $ve ) ) { return null; }
		$k = self::khoa( $ve );
		$d = get_transient( $k );
		delete_transient( $k );
		if ( ! is_array( $d ) || empty( $d['name'] ) || empty( $d['role'] ) ) { return null; }
		return $d;
	}

	/**
	 * Vai trò của trạm quy về mã vai của trang tổng — thứ mà mấy app kia đã biết đọc.
	 *
	 * ⚠️ QUY VỀ MÃ CÓ SẴN, ĐỪNG ĐẺ BẢNG VAI THỨ HAI. Mấy app chi phí đã có sẵn lối đọc
	 *    `ADMIN` / `QUAN_LY` / `CUA_HANG_TRUONG` / `KE_TOAN` kèm cả bảng ngoại lệ theo email.
	 *    Dựng một bảng ánh xạ riêng ở đây là hai bộ luật cho cùng một câu hỏi, và chúng sẽ lệch
	 *    nhau đúng vào ngày có người thêm một vai mới.
	 */
	public static function ma_vai( $vai ) {
		if ( ! class_exists( 'VHCC_Vai' ) ) { return 'NHAN_VIEN'; }
		$u = array( 'role' => (string) $vai );
		if ( VHCC_Vai::duoc( $u, 'he_thong' ) )  { return 'ADMIN'; }
		if ( VHCC_Vai::duoc( $u, 'luong' ) )     { return 'KE_TOAN'; }
		if ( VHCC_Vai::duoc( $u, 'ngoai_coso' ) ) { return 'QUAN_LY'; }
		if ( VHCC_Vai::duoc( $u, 'cong_coso' ) ) { return 'CUA_HANG_TRUONG'; }
		return 'NHAN_VIEN';
	}
}
