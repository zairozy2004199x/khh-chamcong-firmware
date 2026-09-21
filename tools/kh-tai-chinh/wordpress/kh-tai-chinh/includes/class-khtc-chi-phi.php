<?php
/**
 * Chi phí: ghi khoản chi theo bộ phận và khoản mục, rồi đối soát với tiền thật
 * đã chi ra khỏi tài khoản.
 *
 * MỘT BẢNG CHO TẤT CẢ BỘ PHẬN, không phải mỗi bộ phận một trang. Bản cũ trên
 * wp-admin đang có năm menu rời (Chi Phí Tổng, KVC, VP, MTĐ, Nội bộ) — năm chỗ
 * nhập, năm chỗ sửa, và muốn biết tổng cả công ty thì phải cộng tay. Ở đây bộ
 * phận chỉ là một CỘT: lọc ra từng bộ phận vẫn xem riêng được, mà cộng ngang
 * dọc thì máy làm.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_ChiPhi {

	const MOI_TRANG = 100;

	/** Bộ phận và khoản mục mặc định — sửa được ngay trên trang. */
	public static function mac_dinh( $loai ) {
		if ( 'bo_phan' === $loai ) {
			return array( 'Khu vui chơi', 'Ghế massage', 'Văn phòng', 'MTĐ', 'Nội bộ K&H' );
		}
		return array(
			'Tiền điện', 'Tiền nước', 'Thuê mặt bằng', 'Lương', 'BHXH',
			'Marketing', 'Vật tư — tiêu hao', 'Sửa chữa — bảo trì', 'Vận chuyển',
			'Phí ngân hàng', 'Thuế — phí nhà nước', 'Khác',
		);
	}

	/**
	 * Danh mục lưu trong wp_options theo từng pháp nhân, không phải bảng riêng.
	 * Vài chục dòng chữ thì một bảng MySQL kèm màn hình quản lý là thừa; đổi ý
	 * sau này cũng chỉ phải chuyển một mảng.
	 */
	public static function danh_muc( $loai, $cty = null ) {
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		$ds  = get_option( 'khtc_dm_' . $loai . '_' . $cty, null );
		if ( ! is_array( $ds ) || ! $ds ) {
			return self::mac_dinh( $loai );
		}
		return $ds;
	}

	public static function luu_danh_muc( $loai, $text, $cty = null ) {
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		$ds  = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d && ! in_array( $d, $ds, true ) ) { $ds[] = $d; }
		}
		if ( ! $ds ) {
			return new WP_Error( 'trong', 'Danh mục không được để trống.' );
		}
		$cu = self::danh_muc( $loai, $cty );
		update_option( 'khtc_dm_' . $loai . '_' . $cty, $ds );
		if ( $cu !== $ds ) {
			KHTC_NhatKy::ghi( 'danh_muc', '', 0, sprintf( 'Sửa danh mục %s: %s', 'bo_phan' === $loai ? 'bộ phận' : 'khoản mục', implode( ' · ', $ds ) ), array( 'truoc' => $cu ) );
		}
		return count( $ds );
	}

	// -------------------------------------------------------------- ghi chi

	public static function them( $d ) {
		global $wpdb;
		$ngay = KHTC_GiaoDich::doc_ngay( $d['ngay'] ?? '' );
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Ngày không đọc được. Dạng đúng: 20/07/2026.' );
		}
		$so_tien = abs( KHTC_GiaoDich::doc_so( $d['so_tien'] ?? '' ) );
		if ( $so_tien <= 0 ) {
			return new WP_Error( 'tien', 'Số tiền phải lớn hơn 0.' );
		}
		$bo_phan = trim( (string) ( $d['bo_phan'] ?? '' ) );
		if ( '' === $bo_phan ) {
			return new WP_Error( 'bo_phan', 'Chưa chọn bộ phận.' );
		}
		$chan = KHTC_Khoa::chan( $ngay, 'thêm' );
		if ( $chan ) { return $chan; }
		$wpdb->insert(
			KHTC_DB::bang( 'chi_phi' ),
			array(
				'cty'          => KHTC_Cty::dang_chon(),
				'ngay'         => $ngay,
				'bo_phan'      => $bo_phan,
				'khoan_muc'    => trim( (string) ( $d['khoan_muc'] ?? 'Khác' ) ),
				'nha_cung_cap' => trim( (string) ( $d['nha_cung_cap'] ?? '' ) ),
				'dien_giai'    => (string) ( $d['dien_giai'] ?? '' ),
				'so_tien'      => $so_tien,
				'so_ct'        => trim( (string) ( $d['so_ct'] ?? '' ) ),
				'hinh_thuc'    => ( ( $d['hinh_thuc'] ?? '' ) === 'tien_mat' ) ? 'tien_mat' : 'chuyen_khoan',
				'ngan_hang_id' => (int) ( $d['ngan_hang_id'] ?? 0 ),
				'giao_dich_id' => 0,
				'kieu_khop'    => '',
				'tao_luc'      => current_time( 'mysql' ),
				'tao_boi'      => wp_get_current_user()->display_name,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi(
			'them',
			'chi_phi',
			$moi,
			sprintf( 'Chi %s ngày %s — %s / %s', number_format( $so_tien, 0, ',', '.' ) . ' đ', mysql2date( 'd/m/Y', $ngay ), $bo_phan, trim( (string) ( $d['khoan_muc'] ?? '' ) ) )
		);
		return $moi;
	}

	public static function xoa( $id ) {
		global $wpdb;
		$c = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'chi_phi' ) . ' WHERE id = %d AND cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
		if ( ! $c ) { return false; }
		$chan = KHTC_Khoa::chan( $c->ngay, 'xoá' );
		if ( $chan ) { return $chan; }
		KHTC_NhatKy::ghi_xoa(
			'chi_phi',
			$id,
			sprintf( 'Xoá chi %s ngày %s — %s / %s', number_format( $c->so_tien, 0, ',', '.' ) . ' đ', mysql2date( 'd/m/Y', $c->ngay ), $c->bo_phan, $c->khoan_muc )
		);
		$wpdb->delete( KHTC_DB::bang( 'chi_phi' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	/**
	 * Dán bảng chi phí: Ngày · Bộ phận · Khoản mục · Nhà cung cấp · Số tiền ·
	 * Số chứng từ · Diễn giải. Cách nhau bằng Tab hoặc dấu phẩy.
	 */
	public static function dan_hang_loat( $text, $ngan_hang_id = 0, $hinh_thuc = 'chuyen_khoan' ) {
		$them = 0;
		$loi  = array();
		KHTC_NhatKy::mo_lo();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			$d = trim( $d );
			if ( '' === $d ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d );
			$o = array_map( 'trim', $o );
			if ( count( $o ) < 5 ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần Ngày, Bộ phận, Khoản mục, Nhà cung cấp, Số tiền.';
				continue;
			}
			$kq = self::them(
				array(
					'ngay'         => $o[0],
					'bo_phan'      => $o[1],
					'khoan_muc'    => $o[2],
					'nha_cung_cap' => $o[3],
					'so_tien'      => $o[4],
					'so_ct'        => $o[5] ?? '',
					'dien_giai'    => $o[6] ?? '',
					'hinh_thuc'    => $hinh_thuc,
					'ngan_hang_id' => $ngan_hang_id,
				)
			);
			if ( is_wp_error( $kq ) ) { $loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message(); } else { $them++; }
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi( 'nap', 'chi_phi', 0, sprintf( 'Nạp %d khoản chi%s', $them, $loi ? ' (' . count( $loi ) . ' dòng lỗi)' : '' ), null, true );
		return array( 'them' => $them, 'loi' => $loi );
	}

	// ----------------------------------------------------------------- lọc

	/** Điều kiện WHERE dùng chung cho cả bảng liệt kê lẫn bảng cộng chéo. */
	private static function dieu_kien( $l ) {
		global $wpdb;
		$dk   = array( 'c.cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );
		if ( ! empty( $l['tu'] ) )        { $dk[] = 'c.ngay >= %s';    $args[] = $l['tu']; }
		if ( ! empty( $l['den'] ) )       { $dk[] = 'c.ngay <= %s';    $args[] = $l['den']; }
		if ( ! empty( $l['bo_phan'] ) )   { $dk[] = 'c.bo_phan = %s';  $args[] = $l['bo_phan']; }
		if ( ! empty( $l['khoan_muc'] ) ) { $dk[] = 'c.khoan_muc = %s'; $args[] = $l['khoan_muc']; }
		if ( isset( $l['da_tra'] ) && '' !== $l['da_tra'] ) {
			$dk[] = ( '1' === (string) $l['da_tra'] ) ? 'c.giao_dich_id > 0' : 'c.giao_dich_id = 0';
		}
		if ( ! empty( $l['tim'] ) ) {
			$dk[]   = '( c.dien_giai LIKE %s OR c.nha_cung_cap LIKE %s )';
			$t      = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
			$args[] = $t;
			$args[] = $t;
		}
		return array( 'WHERE ' . implode( ' AND ', $dk ), $args );
	}

	public static function loc( $l = array() ) {
		global $wpdb;
		$b = KHTC_DB::bang( 'chi_phi' );
		list( $where, $args ) = self::dieu_kien( $l );

		$tong = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS so_dong,
					COALESCE(SUM(c.so_tien),0) AS tong,
					COALESCE(SUM(CASE WHEN c.giao_dich_id > 0 THEN c.so_tien ELSE 0 END),0) AS da_tra
				 FROM $b c $where",
				$args
			)
		);

		$trang = max( 1, (int) ( $l['trang'] ?? 1 ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, n.ten AS ten_ngan_hang, g.ngay AS gd_ngay, g.dien_giai AS gd_dien_giai
				 FROM $b c
				 LEFT JOIN " . KHTC_DB::bang( 'ngan_hang' ) . " n ON n.id = c.ngan_hang_id
				 LEFT JOIN " . KHTC_DB::bang( 'giao_dich' ) . " g ON g.id = c.giao_dich_id
				 $where ORDER BY c.ngay DESC, c.id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( self::MOI_TRANG, ( $trang - 1 ) * self::MOI_TRANG ) )
			)
		);

		return array(
			'rows'     => $rows,
			'so_dong'  => (int) $tong->so_dong,
			'tong'     => (int) $tong->tong,
			'da_tra'   => (int) $tong->da_tra,
			'chua_tra' => (int) $tong->tong - (int) $tong->da_tra,
			'trang'    => $trang,
			'so_trang' => max( 1, (int) ceil( $tong->so_dong / self::MOI_TRANG ) ),
		);
	}

	/**
	 * Bảng cộng chéo bộ phận × khoản mục, kèm tổng hàng và tổng cột.
	 *
	 * Đây mới là bảng kế toán thật sự cần: "tháng này khu vui chơi tốn bao nhiêu
	 * tiền điện" là câu hỏi một ô trả lời, chứ không phải lọc hai lần rồi cộng.
	 * Cộng trong SQL chứ không kéo hết dòng về PHP — một tháng vài nghìn dòng
	 * chi phí thì kéo về vẫn chạy, nhưng một năm thì không.
	 */
	public static function bang_cheo( $l = array() ) {
		global $wpdb;
		$b = KHTC_DB::bang( 'chi_phi' );
		list( $where, $args ) = self::dieu_kien( $l );

		$o = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.bo_phan, c.khoan_muc, SUM(c.so_tien) AS tien
				 FROM $b c $where GROUP BY c.bo_phan, c.khoan_muc",
				$args
			)
		);

		$bo_phan   = array();
		$khoan_muc = array();
		$o_luoi    = array();
		$tong_hang = array();
		$tong_cot  = array();
		$tong      = 0;
		foreach ( $o as $r ) {
			$bp = (string) $r->bo_phan;
			$km = (string) $r->khoan_muc;
			$t  = (int) $r->tien;
			$bo_phan[ $bp ]        = true;
			$khoan_muc[ $km ]      = true;
			$o_luoi[ $km ][ $bp ]  = $t;
			$tong_hang[ $km ]      = ( $tong_hang[ $km ] ?? 0 ) + $t;
			$tong_cot[ $bp ]       = ( $tong_cot[ $bp ] ?? 0 ) + $t;
			$tong                 += $t;
		}
		// Khoản mục tốn nhiều xếp trên: mở bảng ra là thấy ngay tiền đi đâu.
		arsort( $tong_hang );
		ksort( $bo_phan );

		return array(
			'bo_phan'   => array_keys( $bo_phan ),
			'khoan_muc' => array_keys( $tong_hang ),
			'o'         => $o_luoi,
			'tong_hang' => $tong_hang,
			'tong_cot'  => $tong_cot,
			'tong'      => $tong,
		);
	}

	// ------------------------------------------------------- đối soát chi phí

	/**
	 * Ghép chứng từ chi phí với tiền thật đã ra khỏi tài khoản.
	 *
	 * Dùng LẠI đúng KHTC_DoiSoat::ghep() của đối soát cổng thanh toán. Bài toán
	 * giống hệt — hai danh sách ngày/số tiền/mã, ghép một-một, không dòng nào
	 * được nhận hai lần — nên viết lại lần nữa chỉ là thêm một chỗ để sai. Chi
	 * phí không có phí cổng, truyền phi = 0, và lượt trừ phí tự bỏ qua.
	 *
	 * Như đối soát cổng, KHOÁ SỔ KHÔNG CHẶN việc này: nó chỉ ghi cờ đã ghép,
	 * không đổi ngày, số tiền hay bộ phận của khoản chi nào.
	 *
	 * @return array Ba nhóm: khớp, chua_chi (có chứng từ chưa thấy tiền ra),
	 *               thua (ngân hàng chi mà không có chứng từ).
	 */
	public static function doi_soat( $tu, $den, $ngan_hang_id = 0 ) {
		global $wpdb;
		$cty = KHTC_Cty::dang_chon();
		$bc  = KHTC_DB::bang( 'chi_phi' );
		$bg  = KHTC_DB::bang( 'giao_dich' );

		// Tiền mặt không đi qua ngân hàng nên không nằm trong phép ghép này.
		$dk   = array( 'c.cty = %s', 'c.ngay >= %s', 'c.ngay <= %s', "c.hinh_thuc = 'chuyen_khoan'" );
		$args = array( $cty, $tu, $den );
		if ( $ngan_hang_id ) { $dk[] = 'c.ngan_hang_id = %d'; $args[] = (int) $ngan_hang_id; }
		$ct = $wpdb->get_results(
			$wpdb->prepare( "SELECT c.* FROM $bc c WHERE " . implode( ' AND ', $dk ) . ' ORDER BY c.ngay, c.id', $args )
		);

		$dk2   = array( 'g.cty = %s', "g.loai = 'chi'" );
		$args2 = array( $cty );
		$dk2[] = 'g.ngay >= DATE_SUB(%s, INTERVAL %d DAY)';
		$args2[] = $tu;
		$args2[] = KHTC_DoiSoat::DUNG_SAI_NGAY;
		$dk2[] = 'g.ngay <= DATE_ADD(%s, INTERVAL %d DAY)';
		$args2[] = $den;
		$args2[] = KHTC_DoiSoat::DUNG_SAI_NGAY;
		if ( $ngan_hang_id ) { $dk2[] = 'g.ngan_hang_id = %d'; $args2[] = (int) $ngan_hang_id; }
		$gd = $wpdb->get_results(
			$wpdb->prepare( "SELECT g.* FROM $bg g WHERE " . implode( ' AND ', $dk2 ) . ' ORDER BY g.ngay, g.id', $args2 )
		);

		// Số chứng từ đóng vai mã giao dịch; chi phí không có phí cổng.
		$dong = array();
		foreach ( $ct as $c ) {
			$dong[] = (object) array(
				'id'      => (int) $c->id,
				'ngay'    => $c->ngay,
				'ma_gd'   => (string) $c->so_ct,
				'so_tien' => (int) $c->so_tien,
				'phi'     => 0,
			);
		}
		$ghep = KHTC_DoiSoat::ghep( $dong, $gd );

		// Ghi lại để màn hình Chi phí biết khoản nào đã trả thật.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $bc SET giao_dich_id = 0, kieu_khop = ''
				 WHERE cty = %s AND ngay >= %s AND ngay <= %s AND hinh_thuc = 'chuyen_khoan'",
				$cty,
				$tu,
				$den
			)
		);
		$theo_id = array();
		foreach ( $ct as $c ) { $theo_id[ (int) $c->id ] = $c; }
		$khop     = array();
		$chua_chi = array();
		$da_nhan  = array();
		$gd_map   = array();
		foreach ( $gd as $g ) { $gd_map[ (int) $g->id ] = $g; }

		foreach ( $ct as $c ) {
			$id = (int) $c->id;
			if ( isset( $ghep[ $id ] ) ) {
				list( $gid, $kieu ) = $ghep[ $id ];
				$wpdb->update( $bc, array( 'giao_dich_id' => $gid, 'kieu_khop' => $kieu ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) );
				$c->gd            = $gd_map[ $gid ];
				$c->kieu_khop     = $kieu;
				$khop[]           = $c;
				$da_nhan[ $gid ]  = true;
			} else {
				$chua_chi[] = $c;
			}
		}

		// "Thừa" chỉ tính trong ĐÚNG kỳ — dòng ngoài kỳ bị nới ra chỉ để ghép.
		$thua = array();
		foreach ( $gd as $g ) {
			if ( isset( $da_nhan[ (int) $g->id ] ) ) { continue; }
			if ( $g->ngay < $tu || $g->ngay > $den ) { continue; }
			$thua[] = $g;
		}

		KHTC_NhatKy::ghi(
			'doi_soat',
			'chi_phi',
			0,
			sprintf(
				'Đối soát chi phí %s → %s — khớp %d, chưa thấy tiền ra %d, tiền ra không chứng từ %d',
				mysql2date( 'd/m/Y', $tu ),
				mysql2date( 'd/m/Y', $den ),
				count( $khop ),
				count( $chua_chi ),
				count( $thua )
			)
		);

		return array(
			'khop'          => $khop,
			'chua_chi'      => $chua_chi,
			'thua'          => $thua,
			'tien_khop'     => array_sum( array_map( function ( $c ) { return (int) $c->so_tien; }, $khop ) ),
			'tien_chua_chi' => array_sum( array_map( function ( $c ) { return (int) $c->so_tien; }, $chua_chi ) ),
			'tien_thua'     => array_sum( array_map( function ( $g ) { return (int) $g->so_tien; }, $thua ) ),
		);
	}
}
