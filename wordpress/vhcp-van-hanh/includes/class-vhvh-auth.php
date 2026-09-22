<?php
/**
 * CỬA KHOÁ — nằm ở MÁY CHỦ, không ở trình duyệt.
 *
 * =================================================================================================
 * 🔴 VÌ SAO LỚP NÀY TỒN TẠI
 * =================================================================================================
 * Bản Firebase của app này (`index.html`, 6.451 dòng) khoá cửa bằng JavaScript: tải chuỗi băm mật
 * khẩu về máy khách, băm SHA-256 ngay trong trình duyệt rồi SO TRONG TRÌNH DUYỆT, còn danh tính
 * thì đọc thẳng từ `localStorage` không kiểm lại. Nghĩa là gõ một dòng vào Console là thành Quản
 * lý — không cần mật khẩu, không để lại dấu vết.
 *
 * Ở bản này mọi câu hỏi "anh là ai, được làm gì" đều hỏi MÁY CHỦ. Trình duyệt chỉ giữ một chuỗi
 * thẻ; mất thẻ thì hết hạn là xong, còn sửa biến trong trình duyệt thì không đổi được gì cả.
 *
 * =================================================================================================
 * 🔴 DÙNG CHUNG SỔ NHÂN SỰ VỚI PLUGIN CHẤM CÔNG — KHÔNG DỰNG SỔ THỨ HAI
 * =================================================================================================
 * Đăng nhập, phiên, PIN, vai trò, cơ sở: `vhcp-cham-cong` đã có đủ và đang chạy thật trên
 * khmatrix.com. Dựng thêm một sổ nữa là:
 *   · một người nghỉ việc phải nhớ xoá hai nơi — quên một nơi là người đã nghỉ vẫn vào được;
 *   · đổi PIN ở đây không đổi ở kia, nhân viên gõ PIN mới vào trang cũ thì bị chối mà không hiểu;
 *   · hai danh sách cơ sở lệch tên nhau, báo cáo cộng theo cơ sở ra hai kết quả khác nhau.
 * Nên plugin này KHÔNG có màn đăng nhập riêng. Nó mượn thẻ của chấm công.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_Auth {

	/**
	 * Bốn vai trò của màn hình Vận Hành, xếp từ thấp lên cao. Thứ tự QUAN TRỌNG: `du_quyen()`
	 * so bằng vị trí trong mảng này.
	 */
	const BAC = array( 'nhan_vien', 'thu_ngan', 'cua_hang_truong', 'quan_ly' );

	/**
	 * Vai trò bên chấm công → vai trò ở đây.
	 *
	 * ⚠️ AI KHÔNG CÓ TRONG BẢNG NÀY THÌ LÀ `nhan_vien`, KHÔNG PHẢI `quan_ly`. Quy tắc "không
	 *    biết thì cho quyền thấp nhất": gõ sai chính tả một vai trò bên chấm công mà lại rơi
	 *    xuống nhánh quyền cao là cả doanh thu mở toang, và không có câu báo nào.
	 */
	public static function doi_vai( $vai_cham_cong ) {
		$m = array(
			'Admin'            => 'quan_ly',
			'Quản lý'          => 'quan_ly',
			'Cửa hàng trưởng'  => 'cua_hang_truong',
			'Kế toán cá nhân'  => 'thu_ngan',
			'Kế toán NCC'      => 'thu_ngan',
			'Nhân viên'        => 'nhan_vien',
		);
		$v = (string) $vai_cham_cong;
		return isset( $m[ $v ] ) ? $m[ $v ] : 'nhan_vien';
	}

	/**
	 * Có plugin chấm công để mượn thẻ không.
	 *
	 * ⚠️ Hàm này soi ĐÚNG `user_by_token`. Chỗ nào gọi một hàm KHÁC của `VHCC_Auth` (ví dụ
	 *    `login()` ở màn đăng nhập) phải tự gác lấy hàm ấy, ngay cạnh lời gọi — đừng hỏi ké ở
	 *    đây. Bản chấm công cũ có thể có hàm này mà chưa có hàm kia.
	 */
	public static function co_cham_cong() {
		return class_exists( 'VHCC_Auth' ) && method_exists( 'VHCC_Auth', 'user_by_token' );
	}

	/**
	 * Đọc thẻ trong gói tin REST. Trả về mảng người dùng, hoặc null.
	 *
	 * Thẻ đi trong header `X-VHVH-The` hoặc trường `the` của thân gói. Không nhận thẻ trên đường
	 * dẫn (query string): đường dẫn nằm lại trong nhật ký máy chủ, trong lịch sử trình duyệt, và
	 * trong ô `Referer` gửi sang mọi trang ngoài mà người ta bấm tiếp.
	 */
	public static function ai( $req ) {
		if ( ! self::co_cham_cong() ) { return null; }
		$the = '';
		if ( is_object( $req ) && method_exists( $req, 'get_header' ) ) {
			$the = (string) $req->get_header( 'x_vhvh_the' );
		}
		if ( '' === $the && is_object( $req ) && method_exists( $req, 'get_json_params' ) ) {
			$d = (array) $req->get_json_params();
			if ( isset( $d['the'] ) ) { $the = (string) $d['the']; }
		}
		$the = trim( $the );
		if ( '' === $the ) { return null; }

		/* ⚠️ Gác NGAY CẠNH lời gọi, không ké cái gác trong `co_cham_cong()` ở trên. Luật của
		   `tools/test/kiem-goi-cheo.php`, và luật ấy đúng: ngày nào đó `co_cham_cong()` được sửa
		   sang soi một hàm khác thì chỗ này hụt gác mà không ai đụng vào nó. */
		if ( ! class_exists( 'VHCC_Auth' ) || ! method_exists( 'VHCC_Auth', 'user_by_token' ) ) {
			return null;
		}
		$u = VHCC_Auth::user_by_token( $the );
		if ( ! $u ) { return null; }

		return array(
			'ten'   => isset( $u['name'] ) ? (string) $u['name'] : '',
			'ma_nv' => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '',
			'coso'  => isset( $u['coso'] ) ? (string) $u['coso'] : '',
			'vai'   => self::doi_vai( isset( $u['role'] ) ? $u['role'] : '' ),
			'vai_goc' => isset( $u['role'] ) ? (string) $u['role'] : '',
		);
	}

	/** Vai trò của `$u` có bằng hoặc cao hơn `$can` không. */
	public static function du_quyen( $u, $can ) {
		if ( ! is_array( $u ) || ! isset( $u['vai'] ) ) { return false; }
		$co  = array_search( $u['vai'], self::BAC, true );
		$muc = array_search( (string) $can, self::BAC, true );
		if ( false === $co || false === $muc ) { return false; }
		return $co >= $muc;
	}

	/**
	 * Người này được đụng vào cơ sở nào.
	 *
	 * 🔴 CHỈ `quan_ly` ĐƯỢC XEM MỌI CƠ SỞ. Cửa hàng trưởng của cơ sở A mở được sổ lương và sổ
	 *    phạt của cơ sở B là chuyện không ai muốn giải thích. Mà nếu chặn ấy nằm ở trình duyệt
	 *    (ẩn cái ô chọn cơ sở đi) thì chỉ cần gọi thẳng API là qua — nên nó phải nằm ở đây.
	 *
	 * @return true nếu xem được mọi cơ sở; ngược lại trả về mảng tên cơ sở được phép.
	 */
	public static function coso_duoc( $u ) {
		if ( self::du_quyen( $u, 'quan_ly' ) ) { return true; }
		$c = isset( $u['coso'] ) ? trim( (string) $u['coso'] ) : '';
		return '' === $c ? array() : array( $c );
	}

	/** Chặn nhanh: người này có được đụng vào cơ sở `$coso` không. */
	public static function duoc_coso( $u, $coso ) {
		$d = self::coso_duoc( $u );
		if ( true === $d ) { return true; }
		return in_array( trim( (string) $coso ), $d, true );
	}

	/** Câu chối chuẩn — cùng một câu cho mọi đường, không nói rõ thiếu gì. */
	public static function choi( $chu = '' ) {
		return array( 'ok' => false, 'error' => '' !== $chu ? $chu : 'Không đủ quyền.' );
	}
}
