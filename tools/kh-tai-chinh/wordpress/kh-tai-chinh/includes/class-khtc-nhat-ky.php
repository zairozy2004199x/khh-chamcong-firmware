<?php
/**
 * Nhật ký thay đổi — ai làm gì, lúc nào.
 *
 * Mọi phần mềm kế toán nghiêm túc đều có (LedgerSMB gọi là history trên AR/AP,
 * NetSuite và Zoho gọi là audit trail). Ở đây hai kế toán dùng chung một sổ và
 * tiền là tiền thật, nên "ai xoá dòng này" phải trả lời được.
 *
 * GHI THEO LÔ, KHÔNG THEO DÒNG. Dán một bảng sao kê 2.000 dòng mà ghi 2.000
 * dòng nhật ký thì nhật ký to hơn cả sổ và không ai đọc nổi — che mất đúng thứ
 * cần thấy. Nên hàm dán hàng loạt bật cờ theo_lo, các hàm ghi lẻ im lặng, và
 * cuối cùng chỉ một dòng "nạp 2.000 giao dịch" được ghi.
 *
 * XOÁ THÌ GIỮ LẠI CẢ BẢN GHI trong cột du_lieu, nên phục hồi được. Cách này
 * gọn hơn xoá mềm: xoá mềm bắt mọi câu truy vấn trong plugin phải nhớ lọc
 * "chưa xoá", quên một chỗ là số sai. Giữ xác trong nhật ký thì không câu nào
 * phải đổi.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_NhatKy {

	const MOI_TRANG = 60;

	/** Đang trong một lô: các hàm ghi lẻ không tự ghi nhật ký nữa. */
	private static $theo_lo = false;

	public static function mo_lo() { self::$theo_lo = true; }
	public static function dong_lo() { self::$theo_lo = false; }
	public static function trong_lo() { return self::$theo_lo; }

	public static function ten_viec( $v ) {
		$ds = array(
			'them'     => 'Thêm',
			'sua'      => 'Sửa',
			'xoa'      => 'Xoá',
			'nap'      => 'Nạp hàng loạt',
			'doi_soat' => 'Chạy đối soát',
			'khoa'     => 'Khoá sổ',
			'nhap'     => 'Nhập sao lưu',
			'phuc_hoi' => 'Phục hồi',
			'danh_muc' => 'Sửa danh mục',
		);
		return $ds[ $v ] ?? $v;
	}

	public static function ten_bang( $b ) {
		$ds = array(
			'ngan_hang' => 'Tài khoản ngân hàng',
			'giao_dich' => 'Giao dịch',
			'doi_soat'  => 'Đợt đối soát',
			'ds_dong'   => 'Dòng cổng',
			'chi_phi'   => 'Chi phí',
			'hd_ra'     => 'Hoá đơn đầu ra',
			'hd_vao'    => 'Hoá đơn đầu vào',
			'thanh_toan' => 'Thanh toán',
		);
		return $ds[ $b ] ?? $b;
	}

	/**
	 * Ghi một dòng nhật ký.
	 *
	 * @param string     $viec     them|xoa|nap|doi_soat|khoa|nhap|phuc_hoi|danh_muc
	 * @param string     $bang     Tên bảng không có tiền tố, '' nếu không thuộc bảng nào.
	 * @param int        $id       Id bản ghi, 0 nếu không có.
	 * @param string     $tom_tat  Một câu người đọc hiểu được.
	 * @param array|null $du_lieu  Bản ghi đầy đủ — chỉ dùng cho việc xoá, để phục hồi.
	 * @param bool       $du_lo    true thì ghi kể cả đang trong lô (dùng cho dòng tổng kết lô).
	 */
	public static function ghi( $viec, $bang, $id, $tom_tat, $du_lieu = null, $du_lo = false ) {
		global $wpdb;
		if ( self::$theo_lo && ! $du_lo ) { return 0; }
		$u = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$wpdb->insert(
			KHTC_DB::bang( 'nhat_ky' ),
			array(
				'cty'         => KHTC_Cty::dang_chon(),
				'luc'         => current_time( 'mysql' ),
				'ai'          => $u ? (string) $u->display_name : '',
				'viec'        => (string) $viec,
				'bang'        => (string) $bang,
				'ban_ghi_id'  => (int) $id,
				'tom_tat'     => (string) $tom_tat,
				'du_lieu'     => null === $du_lieu ? '' : wp_json_encode( $du_lieu ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** Ghi việc xoá kèm cả bản ghi, để còn phục hồi được. */
	public static function ghi_xoa( $bang, $id, $tom_tat ) {
		global $wpdb;
		$hang = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( $bang ) . ' WHERE id = %d', (int) $id ),
			ARRAY_A
		);
		return self::ghi( 'xoa', $bang, $id, $tom_tat, $hang );
	}

	public static function loc( $l = array() ) {
		global $wpdb;
		$b    = KHTC_DB::bang( 'nhat_ky' );
		$dk   = array( 'cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );
		if ( ! empty( $l['viec'] ) ) { $dk[] = 'viec = %s'; $args[] = $l['viec']; }
		if ( ! empty( $l['bang'] ) ) { $dk[] = 'bang = %s'; $args[] = $l['bang']; }
		$where = 'WHERE ' . implode( ' AND ', $dk );

		$so    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $b $where", $args ) );
		$trang = max( 1, (int) ( $l['trang'] ?? 1 ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $b $where ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( self::MOI_TRANG, ( $trang - 1 ) * self::MOI_TRANG ) )
			)
		);
		return array(
			'rows'     => $rows,
			'so_dong'  => $so,
			'trang'    => $trang,
			'so_trang' => max( 1, (int) ceil( $so / self::MOI_TRANG ) ),
		);
	}

	/**
	 * Đặt lại một bản ghi đã xoá.
	 *
	 * Chèn lại nguyên văn kể cả id cũ: liên kết từ bảng khác (dòng cổng trỏ vào
	 * đợt, chi phí trỏ vào giao dịch) đều đi theo id, phục hồi bằng id mới thì
	 * bản ghi sống lại nhưng mồ côi.
	 */
	public static function phuc_hoi( $log_id ) {
		global $wpdb;
		$g = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'nhat_ky' ) . ' WHERE id = %d AND cty = %s',
				(int) $log_id,
				KHTC_Cty::dang_chon()
			)
		);
		if ( ! $g || 'xoa' !== $g->viec || '' === (string) $g->du_lieu ) {
			return new WP_Error( 'khong_phuc_hoi', 'Dòng nhật ký này không phải một lần xoá có giữ dữ liệu.' );
		}
		$hang = json_decode( $g->du_lieu, true );
		if ( ! is_array( $hang ) || empty( $hang['id'] ) ) {
			return new WP_Error( 'hong', 'Dữ liệu giữ lại không đọc được.' );
		}
		if ( isset( $hang['ngay'] ) ) {
			$chan = KHTC_Khoa::chan( $hang['ngay'], 'phục hồi' );
			if ( $chan ) { return $chan; }
		}
		$b  = KHTC_DB::bang( $g->bang );
		$co = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $b WHERE id = %d", (int) $hang['id'] ) );
		if ( $co ) {
			return new WP_Error( 'da_co', 'Bản ghi id ' . (int) $hang['id'] . ' đã có lại trong sổ rồi.' );
		}
		if ( false === $wpdb->insert( $b, $hang ) ) {
			return new WP_Error( 'chen', 'Không đặt lại được — có thể đã có bản ghi khác trùng khoá (ví dụ cùng số hoá đơn).' );
		}
		self::ghi( 'phuc_hoi', $g->bang, (int) $hang['id'], 'Phục hồi từ nhật ký #' . (int) $log_id . ': ' . $g->tom_tat );
		return (int) $hang['id'];
	}
}
