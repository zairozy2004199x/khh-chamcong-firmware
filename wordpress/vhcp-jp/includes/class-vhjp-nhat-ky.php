<?php
/**
 * NHẬT KÝ THAO TÁC — thay `jpAudit_()` của `JP2_01_Core.gs`.
 *
 * 🔴 GHI NHẬT KÝ KHÔNG BAO GIỜ ĐƯỢC LÀM HỎNG NGHIỆP VỤ.
 *    Bản gốc bọc cả thân hàm trong `try/catch` kèm đúng một câu: *"audit không được phép làm
 *    hỏng nghiệp vụ"*. Đúng vậy: bảng nhật ký đầy, hay một trường quá dài, mà làm nhân viên
 *    không nộp được báo cáo thì cái giá lớn hơn hẳn giá trị của dòng nhật ký ấy.
 *
 * ⚠️ NHƯNG CŨNG ĐỪNG NUỐT CHỬNG TRONG IM LẶNG. Nuốt hết thì ngày sổ nhật ký hỏng sẽ không ai
 *    hay, và tới lúc cần lần lại một thao tác thì mới biết là mấy tháng nay chẳng ghi được gì.
 *    Nên vẫn trả về `false` để nơi gọi HỎI ĐƯỢC nếu muốn, chỉ là không ném lỗi ra.
 *
 * 🔴 KHÔNG GHI PIN, KHÔNG GHI CHUỖI BĂM. Nhật ký là thứ người ta mở ra xem nhiều nhất và
 *    xuất ra ngoài dễ nhất. `che_kin()` lọc trước khi ghi.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_NhatKy {

	/** Tên trường nào cũng KHÔNG được vào nhật ký, dù ai truyền vào. */
	public static function truong_cam() {
		return array( 'pin', 'password', 'pass', 'matkhau', 'token', 'the' );
	}

	/**
	 * Bỏ mọi trường nhạy cảm khỏi phần chi tiết, kể cả lồng nhiều tầng.
	 *
	 * ⚠️ Dựng theo lối LOẠI TRỪ ở đây là đúng (ngược với `VHJP_Auth::user_cong_khai`): chi tiết
	 *    nhật ký nhận đủ mọi hình dạng object, không thể liệt kê trước cái gì được phép.
	 */
	public static function che_kin( $v, $sau = 0 ) {
		if ( $sau > 6 || ! is_array( $v ) ) { return $v; }
		$cam = self::truong_cam();
		$ra = array();
		foreach ( $v as $k => $x ) {
			if ( in_array( strtolower( (string) $k ), $cam, true ) ) { continue; }
			$ra[ $k ] = is_array( $x ) ? self::che_kin( $x, $sau + 1 ) : $x;
		}
		return $ra;
	}

	/**
	 * Ghi một dòng nhật ký. Trả `true`/`false`, KHÔNG bao giờ ném lỗi.
	 *
	 * `$nguoi` là mảng phiên (có `hoTen`/`role`) hoặc `null` cho việc của hệ thống.
	 */
	public static function ghi( $nguoi, $viec, $ma_bao_cao = '', $doi_tuong = '', $chi_tiet = '' ) {
		try {
			if ( is_array( $chi_tiet ) ) {
				$chi_tiet = wp_json_encode( self::che_kin( $chi_tiet ), JSON_UNESCAPED_UNICODE );
			}
			$hang = array(
				'at'       => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),   // giờ Việt Nam
				'who'      => is_array( $nguoi )
					? VHJP_Doc::str( isset( $nguoi['hoTen'] ) ? $nguoi['hoTen'] : '' ) : '',
				'role'     => is_array( $nguoi )
					? VHJP_Doc::str( isset( $nguoi['role'] ) ? $nguoi['role'] : '' ) : '',
				'action'   => VHJP_Doc::str( $viec ),
				'reportId' => VHJP_Doc::str( $ma_bao_cao ),
				'target'   => VHJP_Doc::str( $doi_tuong ),
				/* Cắt cho vừa cột. Dài quá thì MySQL chối cả dòng, và mất dòng nhật ký vì một
				   trường chi tiết thì không đáng. */
				'detail'   => mb_substr( (string) $chi_tiet, 0, 480 ),
			);
			return false !== VHJP_Nguon::them( 'JP_Audit', $hang );
		} catch ( Throwable $e ) {
			/* 🔴 `Throwable`, KHÔNG phải `Exception`. Từ PHP 7, lỗi nặng là `Error` — một nhánh
			   RIÊNG, không thừa kế `Exception`. Mà đúng loại hay gặp nhất ở đây lại là `Error`:
			   người gọi truyền vào một đối tượng không đổi sang chuỗi được thì `(string)` ném
			   `Error`, nó chui thẳng qua `catch ( Exception )` và làm gãy cả lượt lưu.
			   Bản đầu viết `Exception` và bài kiểm bắt được — nhưng chỉ sau khi dựng đúng ca
			   THẬT SỰ NÉM; ca "sổ nhật ký hỏng" không đi qua đây, vì tầng dưới trả `false`
			   chứ không ném. */
			return false;
		}
	}
}
