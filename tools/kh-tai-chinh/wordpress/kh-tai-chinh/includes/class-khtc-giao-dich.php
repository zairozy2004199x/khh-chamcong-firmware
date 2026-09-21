<?php
/**
 * Giao dịch: thêm tay, dán sao kê hàng loạt, và lọc để hiển thị.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_GiaoDich {

	/** Số dòng mỗi trang. Sao kê thật có 219.000 dòng nên không bao giờ đổ hết một lượt. */
	const MOI_TRANG = 100;

	/**
	 * Đọc số tiền người dùng gõ. Nhận cả "1.500.000", "1,500,000" và "1500000".
	 *
	 * Quy ước: nhóm sau dấu cuối cùng dài đúng 3 chữ số thì đó là phân cách nghìn,
	 * ngược lại là dấu thập phân. Không có quy ước này thì "20.000 ₫" ra 20 đồng —
	 * kiểu sai không ai nhận ra vì con số vẫn hợp lệ.
	 */
	public static function doc_so( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return 0; }
		$am = ( strpos( $s, '-' ) !== false );
		$s  = preg_replace( '/[^0-9.,]/', '', $s );
		if ( '' === $s ) { return 0; }

		$cham = substr_count( $s, '.' );
		$phay = substr_count( $s, ',' );
		if ( $cham && $phay ) {
			$s = ( strrpos( $s, '.' ) > strrpos( $s, ',' ) )
				? str_replace( ',', '', $s )
				: str_replace( ',', '.', str_replace( '.', '', $s ) );
		} elseif ( $cham > 1 ) {
			$s = str_replace( '.', '', $s );
		} elseif ( $phay > 1 ) {
			$s = str_replace( ',', '', $s );
		} elseif ( $cham === 1 || $phay === 1 ) {
			$dau  = $cham ? '.' : ',';
			$phan = explode( $dau, $s );
			$sau  = end( $phan );
			$s    = ( strlen( $sau ) === 3 && ctype_digit( $sau ) )
				? str_replace( $dau, '', $s )
				: str_replace( ',', '.', $s );
		}
		$n = (int) round( (float) $s );
		return $am ? -$n : $n;
	}

	/** Ngày kiểu dd/mm/yyyy, dd-mm-yyyy hay yyyy-mm-dd đều đọc được. */
	public static function doc_ngay( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return ''; }
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m ) ) {
			return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
		}
		if ( preg_match( '#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})#', $s, $m ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		return '';
	}

	public static function them( $d ) {
		global $wpdb;
		$ngay = self::doc_ngay( $d['ngay'] ?? '' );
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Ngày không đọc được. Dạng đúng: 20/07/2026.' );
		}
		if ( empty( $d['ngan_hang_id'] ) ) {
			return new WP_Error( 'nh', 'Chưa chọn tài khoản ngân hàng.' );
		}
		$chan = KHTC_Khoa::chan( $ngay, 'thêm' );
		if ( $chan ) { return $chan; }
		$so_tien = self::doc_so( $d['so_tien'] ?? '' );
		$loai    = ( ( $d['loai'] ?? '' ) === 'chi' ) ? 'chi' : 'thu';
		// Số âm mà không nói rõ thu/chi thì hiểu là chi — giống quy ước dán sao kê.
		if ( $so_tien < 0 ) {
			$loai    = 'chi';
			$so_tien = abs( $so_tien );
		}
		$wpdb->insert(
			KHTC_DB::bang( 'giao_dich' ),
			array(
				'cty'          => KHTC_Cty::dang_chon(),
				'ngan_hang_id' => (int) $d['ngan_hang_id'],
				'ngay'         => $ngay,
				'dien_giai'    => (string) ( $d['dien_giai'] ?? '' ),
				'so_tien'      => $so_tien,
				'loai'         => $loai,
				'ma_gd'        => (string) ( $d['ma_gd'] ?? '' ),
				'tao_luc'      => current_time( 'mysql' ),
				'tao_boi'      => wp_get_current_user()->display_name,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi(
			'them',
			'giao_dich',
			$moi,
			sprintf( '%s %s ngày %s — %s', 'chi' === $loai ? 'Chi' : 'Thu', number_format( $so_tien, 0, ',', '.' ) . ' đ', mysql2date( 'd/m/Y', $ngay ), (string) ( $d['dien_giai'] ?? '' ) )
		);
		return $moi;
	}

	/**
	 * Dán sao kê hàng loạt: mỗi dòng là Ngày · Diễn giải · Số tiền · Thu/Chi · Mã GD.
	 * Cách nhau bằng Tab (copy từ Excel) hoặc dấu phẩy. Cột Thu/Chi bỏ trống thì
	 * số dương là Thu, số âm là Chi.
	 */
	public static function dan_hang_loat( $ngan_hang_id, $text ) {
		$dong  = preg_split( '/\r\n|\r|\n/', (string) $text );
		$them  = 0;
		$loi   = array();
		// Một dòng nhật ký cho cả lô, không phải một dòng cho mỗi giao dịch.
		KHTC_NhatKy::mo_lo();
		foreach ( $dong as $i => $d ) {
			$d = trim( $d );
			if ( '' === $d ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d );
			$o = array_map( 'trim', $o );
			if ( count( $o ) < 3 ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần ít nhất Ngày, Diễn giải, Số tiền.';
				continue;
			}
			$kq = self::them(
				array(
					'ngan_hang_id' => $ngan_hang_id,
					'ngay'         => $o[0],
					'dien_giai'    => $o[1],
					'so_tien'      => $o[2],
					'loai'         => isset( $o[3] ) ? ( mb_stripos( $o[3], 'chi' ) !== false ? 'chi' : 'thu' ) : '',
					// Cột 5 không bắt buộc, nhưng có nó thì đối soát ghép được
					// theo mã giao dịch — lượt ghép chắc chắn nhất.
					'ma_gd'        => isset( $o[4] ) ? $o[4] : '',
				)
			);
			if ( is_wp_error( $kq ) ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message();
			} else {
				$them++;
			}
		}
		KHTC_NhatKy::dong_lo();
		$nh = KHTC_NganHang::mot( $ngan_hang_id );
		KHTC_NhatKy::ghi(
			'nap',
			'giao_dich',
			0,
			sprintf( 'Nạp %d giao dịch vào %s%s', $them, $nh ? $nh->ten : '?', $loi ? ' (' . count( $loi ) . ' dòng lỗi)' : '' ),
			null,
			true
		);
		return array( 'them' => $them, 'loi' => $loi );
	}

	/** Lọc + phân trang. Trả về [rows, tong_dong, tong_thu, tong_chi]. */
	public static function loc( $l = array() ) {
		global $wpdb;
		$b    = KHTC_DB::bang( 'giao_dich' );
		// Mọi điều kiện đều ghi rõ bảng g. Bảng ngân hàng cũng có cột `cty`, nên
		// một điều kiện `cty = ...` trần trong câu có JOIN là lỗi "ambiguous
		// column" — cả trang trắng, không phải một con số sai lặng lẽ.
		$dk   = array( 'g.cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );

		if ( ! empty( $l['ngan_hang_id'] ) ) { $dk[] = 'g.ngan_hang_id = %d'; $args[] = (int) $l['ngan_hang_id']; }
		if ( ! empty( $l['tu'] ) )           { $dk[] = 'g.ngay >= %s';        $args[] = $l['tu']; }
		if ( ! empty( $l['den'] ) )          { $dk[] = 'g.ngay <= %s';        $args[] = $l['den']; }
		if ( ! empty( $l['loai'] ) )         { $dk[] = 'g.loai = %s';         $args[] = $l['loai']; }
		if ( ! empty( $l['tim'] ) ) {
			$dk[]   = 'g.dien_giai LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
		}
		$where = 'WHERE ' . implode( ' AND ', $dk );

		$tong = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS so_dong,
					COALESCE(SUM(CASE WHEN g.loai='thu' THEN g.so_tien ELSE 0 END),0) AS thu,
					COALESCE(SUM(CASE WHEN g.loai='chi' THEN g.so_tien ELSE 0 END),0) AS chi
				FROM $b g $where",
				$args
			)
		);

		$trang = max( 1, (int) ( $l['trang'] ?? 1 ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*, n.ten AS ten_ngan_hang FROM $b g
				 LEFT JOIN " . KHTC_DB::bang( 'ngan_hang' ) . " n ON n.id = g.ngan_hang_id
				 $where ORDER BY g.ngay DESC, g.id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( self::MOI_TRANG, ( $trang - 1 ) * self::MOI_TRANG ) )
			)
		);

		return array(
			'rows'     => $rows,
			'so_dong'  => (int) $tong->so_dong,
			'thu'      => (int) $tong->thu,
			'chi'      => (int) $tong->chi,
			'trang'    => $trang,
			'so_trang' => max( 1, (int) ceil( $tong->so_dong / self::MOI_TRANG ) ),
		);
	}

	public static function xoa( $id ) {
		global $wpdb;
		$g = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE id = %d', (int) $id ) );
		if ( ! $g ) { return false; }
		$chan = KHTC_Khoa::chan( $g->ngay, 'xoá', $g->cty );
		if ( $chan ) { return $chan; }
		KHTC_NhatKy::ghi_xoa(
			'giao_dich',
			$id,
			sprintf( 'Xoá %s %s ngày %s — %s', 'chi' === $g->loai ? 'chi' : 'thu', number_format( $g->so_tien, 0, ',', '.' ) . ' đ', mysql2date( 'd/m/Y', $g->ngay ), (string) $g->dien_giai )
		);
		$wpdb->delete( KHTC_DB::bang( 'giao_dich' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}
}
