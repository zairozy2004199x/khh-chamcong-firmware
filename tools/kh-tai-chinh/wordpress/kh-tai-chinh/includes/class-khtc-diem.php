<?php
/**
 * Danh mục điểm — cầu nối giữa sao kê và hoá đơn.
 *
 * Mỗi dòng QR về ngân hàng mang một MÃ CỬA HÀNG (ví dụ 1W642MMO1S). Tự nó chỉ
 * là một khoản tiền không tên. Bảng này nói mã đó thuộc gian nào, xuất hoá đơn
 * dưới tên điểm nào, mã Misa gì, khu vực và dịch vụ nào. Không có nó thì không
 * gom được sao kê thành hoá đơn — đây là thứ duy nhất nối nửa đầu của quy
 * trình với nửa sau.
 *
 * Điểm đặt cờ "bỏ qua" thì không bao giờ vào hoá đơn: dùng cho mã test, mã
 * vãng lai, gian đã đóng. Cố ý là một CỜ chứ không phải xoá dòng — xoá rồi thì
 * lần nạp danh mục sau nó lại về, và không ai nhớ vì sao trước đó nó bị loại.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Diem {

	/** Cột khi dán hàng loạt, đúng thứ tự. */
	const COT = array( 'ma_cua_hang', 'ten_gian', 'ma_diem_ban', 'ten_diem', 'ma_misa', 'khu_vuc', 'dich_vu', 'so_tk' );

	public static function ds( $l = array() ) {
		global $wpdb;
		$b    = KHTC_DB::bang( 'diem' );
		$dk   = array( 'cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );
		if ( ! empty( $l['tim'] ) ) {
			$dk[] = '( ma_cua_hang LIKE %s OR ten_gian LIKE %s OR ten_diem LIKE %s OR ma_misa LIKE %s )';
			$t    = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
			array_push( $args, $t, $t, $t, $t );
		}
		if ( isset( $l['bo_qua'] ) && '' !== $l['bo_qua'] ) {
			$dk[]   = 'bo_qua = %d';
			$args[] = (int) $l['bo_qua'];
		}
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $b WHERE " . implode( ' AND ', $dk ) . ' ORDER BY ten_diem, ma_cua_hang', $args )
		);
	}

	public static function dem( $cty = null ) {
		global $wpdb;
		$b = KHTC_DB::bang( 'diem' );
		return array(
			'tong'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $b WHERE cty = %s", $cty ?: KHTC_Cty::dang_chon() ) ),
			'bo_qua' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $b WHERE cty = %s AND bo_qua = 1", $cty ?: KHTC_Cty::dang_chon() ) ),
		);
	}

	/**
	 * Bảng tra mã cửa hàng → điểm, cho cả pháp nhân đang chọn.
	 *
	 * Lấy một lần rồi tra trong bộ nhớ: gom một kỳ sao kê là chục nghìn dòng,
	 * hỏi cơ sở dữ liệu từng dòng thì trang không bao giờ chạy xong.
	 */
	public static function bang_tra( $cty = null ) {
		global $wpdb;
		$ra = array();
		foreach ( (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( 'diem' ) . ' WHERE cty = %s', $cty ?: KHTC_Cty::dang_chon() )
		) as $d ) {
			$ra[ (string) $d->ma_cua_hang ] = $d;
		}
		return $ra;
	}

	public static function mot( $id ) {
		global $wpdb;
		// Chỉ trong pháp nhân đang xem — sửa / xoá / bỏ qua đều đi qua đây.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( 'diem' ) . ' WHERE id = %d AND cty = %s', (int) $id, KHTC_Cty::dang_chon() ) );
	}

	public static function them( $d, $ghi_nhat_ky = true ) {
		global $wpdb;
		$ma = trim( (string) ( $d['ma_cua_hang'] ?? '' ) );
		if ( '' === $ma ) {
			return new WP_Error( 'ma', 'Chưa có mã cửa hàng — đây là cột nối với sao kê, thiếu nó thì dòng này vô dụng.' );
		}
		$cty = KHTC_Cty::dang_chon();
		$co  = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang( 'diem' ) . ' WHERE cty = %s AND ma_cua_hang = %s', $cty, $ma )
		);
		if ( $co ) {
			return new WP_Error( 'trung', 'Mã cửa hàng ' . $ma . ' đã có trong danh mục.' );
		}
		$hang = array( 'cty' => $cty, 'ma_cua_hang' => $ma, 'tao_luc' => current_time( 'mysql' ) );
		foreach ( array( 'ten_gian', 'ma_diem_ban', 'ten_diem', 'ma_misa', 'khu_vuc', 'dich_vu', 'so_tk', 'ghi_chu' ) as $c ) {
			$hang[ $c ] = trim( (string) ( $d[ $c ] ?? '' ) );
		}
		$hang['bo_qua'] = empty( $d['bo_qua'] ) ? 0 : 1;
		$wpdb->insert( KHTC_DB::bang( 'diem' ), $hang );
		$id = (int) $wpdb->insert_id;
		if ( $ghi_nhat_ky ) {
			KHTC_NhatKy::ghi( 'them', 'diem', $id, 'Thêm điểm ' . ( $hang['ten_diem'] ?: $ma ) );
		}
		return $id;
	}

	public static function sua( $id, $d ) {
		global $wpdb;
		$cu = self::mot( $id );
		if ( ! $cu ) { return new WP_Error( 'khong_co', 'Không tìm thấy điểm.' ); }
		$hang = array();
		foreach ( array( 'ten_gian', 'ma_diem_ban', 'ten_diem', 'ma_misa', 'khu_vuc', 'dich_vu', 'so_tk', 'ghi_chu' ) as $c ) {
			if ( array_key_exists( $c, $d ) ) { $hang[ $c ] = trim( (string) $d[ $c ] ); }
		}
		if ( array_key_exists( 'bo_qua', $d ) ) { $hang['bo_qua'] = empty( $d['bo_qua'] ) ? 0 : 1; }
		if ( ! $hang ) { return true; }
		$wpdb->update( KHTC_DB::bang( 'diem' ), $hang, array( 'id' => (int) $id ) );
		KHTC_NhatKy::ghi( 'sua', 'diem', (int) $id, 'Sửa điểm ' . ( $cu->ten_diem ?: $cu->ma_cua_hang ), (array) $cu );
		return true;
	}

	public static function doi_bo_qua( $id ) {
		$d = self::mot( $id );
		if ( ! $d ) { return new WP_Error( 'khong_co', 'Không tìm thấy điểm.' ); }
		return self::sua( $id, array( 'bo_qua' => $d->bo_qua ? 0 : 1 ) );
	}

	public static function xoa( $id ) {
		global $wpdb;
		$d = self::mot( $id );
		if ( ! $d ) { return new WP_Error( 'khong_co', 'Không tìm thấy điểm.' ); }
		$wpdb->delete( KHTC_DB::bang( 'diem' ), array( 'id' => (int) $id ), array( '%d' ) );
		KHTC_NhatKy::ghi( 'xoa', 'diem', (int) $id, 'Xoá điểm ' . ( $d->ten_diem ?: $d->ma_cua_hang ), (array) $d );
		return true;
	}

	/**
	 * Dán danh mục hàng loạt. Mã đã có thì CẬP NHẬT, không báo trùng.
	 *
	 * Danh mục điểm là thứ sửa đi sửa lại — đổi tên gian, đổi mã Misa, mở thêm
	 * gian. Bắt người dùng xoá hết rồi dán lại là mời họ đánh mất cờ "bỏ qua"
	 * đã đặt tay. Nên dán là NHẬP ĐÈ theo mã cửa hàng, và cờ bỏ qua giữ nguyên
	 * trừ khi cột đó có mặt trong bảng dán.
	 */
	public static function dan_hang_loat( $text ) {
		global $wpdb;
		$them = 0;
		$sua  = 0;
		$loi  = array();
		$tra  = self::bang_tra();
		KHTC_NhatKy::mo_lo();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d, ',', '"', '' );
			$o = array_map( 'trim', $o );
			// Dán cả dòng tiêu đề là chuyện thường khi bôi đen cả bảng Excel.
			if ( isset( $o[0] ) && in_array( mb_strtolower( $o[0] ), array( 'mã cửa hàng', 'ma cua hang', 'stt' ), true ) ) { continue; }
			$ma = (string) ( $o[0] ?? '' );
			if ( '' === $ma ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': thiếu mã cửa hàng.';
				continue;
			}
			$hang = array();
			foreach ( self::COT as $k => $c ) { $hang[ $c ] = (string) ( $o[ $k ] ?? '' ); }
			if ( isset( $tra[ $ma ] ) ) {
				unset( $hang['ma_cua_hang'] );
				self::sua( (int) $tra[ $ma ]->id, $hang );
				$sua++;
			} else {
				$kq = self::them( $hang, false );
				if ( is_wp_error( $kq ) ) {
					$loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message();
					continue;
				}
				$them++;
			}
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi(
			'nap',
			'diem',
			0,
			sprintf( 'Nạp danh mục điểm: thêm %d, cập nhật %d%s', $them, $sua, $loi ? ', ' . count( $loi ) . ' dòng lỗi' : '' )
		);
		return array( 'them' => $them, 'sua' => $sua, 'loi' => $loi );
	}
}
