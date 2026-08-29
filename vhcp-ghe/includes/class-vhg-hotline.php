<?php
/**
 * BÁO CÁO HỖ TRỢ KHÁCH / KÍCH GHẾ TỪ XA — sổ tay ngày của Hotline.
 *
 * Anh Thắng 28/08/2026: *"Bạn nhân viên hotline sẽ nhập báo cáo đó hằng ngày để biết chỉ số kích
 * thêm và chỉ số hoàn tiền cho khách."*
 *
 * 🔴 KHÁC VỚI NHẬT KÝ `lenh` (VHG_May::dat_lenh) — đừng nhầm hai thứ này làm một.
 *    · `lenh`  = TỰ ĐỘNG, ghi mỗi lần có người bấm nút Bật trên màn. Chính xác, có giờ, có tên
 *      người bấm — nhưng KHÔNG có chỗ nào ghi "đã hoàn cho khách bao nhiêu tiền" (hệ không có
 *      luồng hoàn tiền qua ngân hàng/QR để tự bắt được số này).
 *    · `hotline_bc` (bảng của lớp này) = SỔ TAY, Hotline tự gõ tổng kết cuối ngày. Vừa để đối
 *      chiếu với con số tự động ở trên (VHG_May::dem_luot_kich_coso_ngay), vừa là nơi DUY NHẤT
 *      ghi nhận tiền hoàn khách — khoản này không tồn tại ở bảng nào khác trong hệ.
 *
 * 🔴 1 DÒNG / CƠ SỞ / NGÀY — giống luật báo cáo doanh thu (VHG_BaoCao): gửi lại trong ngày là
 *    GHI ĐÈ, không cộng dồn. Cộng dồn thì không ai biết "đã tính ca sáng chưa" khi gửi lần hai.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Hotline {

	private static function ngay_( $v ) {
		$s = trim( (string) $v );
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $s, $m ) ? $m[0] : '';
	}

	/** Lưu báo cáo một ngày của một cơ sở — có rồi thì GHI ĐÈ (xem khối 🔴 ở đầu tệp). */
	public static function luu( $coso, $ngay, $so_luot, $tien_hoan, $ghi_chu, $nguoi ) {
		global $wpdb;
		$coso = trim( (string) $coso );
		$ngay = self::ngay_( $ngay );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Chọn cơ sở.' ); }
		if ( '' === $ngay ) { return array( 'ok' => false, 'error' => 'Chọn ngày báo cáo.' ); }
		/* Bóc số bằng preg_replace, không (int) thẳng — người gõ hay chấm ngăn cách nghìn
		   ("50.000"), và (int) cắt ở dấu chấm sẽ đọc thành 50 thay vì 50.000 (mất 3 số 0). */
		$so_luot   = max( 0, (int) preg_replace( '/\D+/', '', (string) $so_luot ) );
		$tien_hoan = max( 0, (int) preg_replace( '/\D+/', '', (string) $tien_hoan ) );
		$ghi_chu   = mb_substr( trim( (string) $ghi_chu ), 0, 250 );
		$key = VHG_BaoCao::squash( $coso );
		$t   = VHG_DB::t( 'hotline_bc' );
		$now = current_time( 'mysql' );
		$id  = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $t WHERE coso_key=%s AND ngay=%s LIMIT 1", $key, $ngay ) );
		$data = array( 'coso' => $coso, 'coso_key' => $key, 'ngay' => $ngay,
			'so_luot_kich' => $so_luot, 'tien_hoan' => $tien_hoan, 'ghi_chu' => $ghi_chu,
			'nguoi' => mb_substr( (string) $nguoi, 0, 190 ), 'sua_luc' => $now );
		if ( $id ) {
			$wpdb->update( $t, $data, array( 'id' => (int) $id ) );
		} else {
			$data['tao_luc'] = $now;
			$wpdb->insert( $t, $data );
		}
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu báo cáo hỗ trợ khách ' . $coso . ' ngày ' . $ngay . '.' );
	}

	/** Danh sách, mới nhất trước. $thang dạng "YYYY-MM"; rỗng = 400 dòng gần nhất bất kể tháng. */
	public static function ds( $thang ) {
		global $wpdb;
		$t = VHG_DB::t( 'hotline_bc' );
		$thang = trim( (string) $thang );
		if ( preg_match( '/^\d{4}-\d{2}$/', $thang ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $t WHERE ngay LIKE %s ORDER BY ngay DESC, coso ASC LIMIT 400",
				$thang . '-%' ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY ngay DESC, coso ASC LIMIT 400", ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}
}
