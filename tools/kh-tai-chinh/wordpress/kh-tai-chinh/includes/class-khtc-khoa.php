<?php
/**
 * Khoá sổ theo ngày.
 *
 * Mô hình lấy từ cách QuickBooks, Zoho Books và NetSuite làm: đặt một NGÀY
 * KHOÁ cho mỗi pháp nhân; mọi bản ghi mang ngày từ đó trở về trước thì không
 * thêm, không sửa, không xoá được nữa.
 *
 * VÌ SAO CẦN Ở ĐÂY: tờ khai GTGT nộp rồi mà ai đó thêm một hoá đơn lùi ngày
 * vào tháng cũ thì bảng "theo thuế suất" đổi số ngay, trong khi tờ khai đã nộp
 * thì không đổi. Lần sau mở lại, số trên màn hình khác số đã nộp và không ai
 * biết vì sao. Đối soát cổng cũng vậy: chốt xong với Payoo rồi mà kỳ đó còn
 * sửa được thì lần đối chiếu sau ra kết quả khác.
 *
 * CỐ Ý KHÔNG CÓ MẬT KHẨU RIÊNG như QuickBooks. Thêm một mật khẩu là thêm một
 * thứ để quên và để dán lên màn hình. Ở đây ai mở được plugin thì mở được khoá,
 * nhưng mọi lần đổi khoá đều vào nhật ký — biết ai mở, lúc nào, quan trọng hơn
 * là chặn được ai.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Khoa {

	public static function ngay( $cty = null ) {
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		$d   = get_option( 'khtc_khoa_' . $cty, '' );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d ) ? $d : '';
	}

	public static function dat( $ngay, $cty = null ) {
		$cty  = $cty ? $cty : KHTC_Cty::dang_chon();
		$ngay = trim( (string) $ngay );
		if ( '' === $ngay ) {
			$cu = self::ngay( $cty );
			delete_option( 'khtc_khoa_' . $cty );
			KHTC_NhatKy::ghi( 'khoa', '', 0, 'Mở khoá sổ (trước đó khoá đến ' . ( $cu ? $cu : '—' ) . ')' );
			return '';
		}
		$ngay = KHTC_GiaoDich::doc_ngay( $ngay );
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Ngày khoá sổ không đọc được.' );
		}
		update_option( 'khtc_khoa_' . $cty, $ngay );
		KHTC_NhatKy::ghi( 'khoa', '', 0, 'Khoá sổ đến hết ' . mysql2date( 'd/m/Y', $ngay ) );
		return $ngay;
	}

	/** Ngày này đã nằm trong vùng khoá chưa. Ngày rỗng coi như không khoá. */
	public static function bi_khoa( $ngay, $cty = null ) {
		$khoa = self::ngay( $cty );
		if ( '' === $khoa || '' === (string) $ngay ) { return false; }
		return (string) $ngay <= $khoa;
	}

	/**
	 * Trả về WP_Error nếu ngày nằm trong vùng khoá, ngược lại trả về false.
	 * Gọi ở đầu mọi hàm ghi — một dòng, không quên được chỗ nào.
	 */
	public static function chan( $ngay, $viec = 'ghi', $cty = null ) {
		if ( ! self::bi_khoa( $ngay, $cty ) ) { return false; }
		return new WP_Error(
			'khoa',
			'Sổ đã khoá đến hết ' . mysql2date( 'd/m/Y', self::ngay( $cty ) )
				. ' — không ' . $viec . ' được bản ghi ngày ' . mysql2date( 'd/m/Y', $ngay )
				. '. Mở khoá ở mục Nhật ký nếu thật sự cần sửa.'
		);
	}
}
