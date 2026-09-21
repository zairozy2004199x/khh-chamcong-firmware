<?php
/**
 * Hoá đơn đầu vào — VAT được khấu trừ.
 *
 * ĐÂY LÀ CHUYỆN THUẾ, KHÔNG PHẢI SỔ PHẢI TRẢ THỨ HAI. Công nợ phải trả đã dựng
 * trên bảng chi phí; thêm hoá đơn đầu vào làm nguồn nợ nữa là quay lại đúng cái
 * lỗi hai nguồn sự thật vừa bỏ ở bản 0.6. Ở đây hoá đơn đầu vào trả lời một câu
 * khác: tháng này được khấu trừ bao nhiêu VAT, và phải nộp bao nhiêu.
 *
 * VÌ SAO KHÔNG GỘP VÀO BẢNG CHI PHÍ: không phải khoản chi nào cũng có hoá đơn
 * (lương, chi lặt vặt tiền mặt), và không phải hoá đơn đầu vào nào cũng là chi
 * phí (mua tài sản cố định, mua hàng nhập kho). Gộp lại thì một trong hai bảng
 * luôn phải mang những dòng không thuộc về nó.
 *
 * Phép tính VAT dùng LẠI KHTC_HoaDonRa::tinh() — cùng một phép toán, cùng quy
 * tắc "chỉ làm tròn một lần rồi lấy hiệu", và đã có hàng chục phép kiểm đứng
 * sau. Chỗ này dùng lại là đúng, khác với việc mượn phép ghép của đối soát cổng
 * cho công nợ (bài toán khác nhau nên đã hỏng).
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_HoaDonVao {

	const MOI_TRANG = 100;

	/**
	 * Ngưỡng buộc thanh toán không dùng tiền mặt để được khấu trừ VAT.
	 *
	 * Đây là NHẮC, không phải phán quyết: máy đặt sẵn "không khấu trừ" cho hoá
	 * đơn từ mức này trở lên mà trả bằng tiền mặt, kế toán vẫn bật lại được nếu
	 * biết rõ trường hợp của mình. Quy định thuế đổi theo thời kỳ và có ngoại
	 * lệ — phần mềm không nên quyết thay.
	 */
	const NGUONG_TIEN_MAT = 20000000;

	/** Cột của tệp dán vào / xuất ra. Gọn hơn hoá đơn đầu ra vì không phải nộp mẫu nào. */
	public static function cot() {
		return array(
			'STT', 'Ngày HĐ', 'Số HĐ', 'Nhà cung cấp', 'Mã số thuế',
			'Nội dung', 'Chưa VAT', 'VAT', 'Có VAT', 'Thuế suất',
			'Hình thức', 'Khấu trừ', 'Lý do không khấu trừ', 'Ghi chú',
		);
	}

	// ------------------------------------------------------------------ ghi

	/**
	 * Hoá đơn này có nên khấu trừ không, theo mặc định.
	 *
	 * @return array [có khấu trừ, lý do nếu không]
	 */
	public static function mac_dinh_khau_tru( $co_vat, $hinh_thuc ) {
		if ( 'tien_mat' === $hinh_thuc && (int) $co_vat >= self::NGUONG_TIEN_MAT ) {
			return array( false, 'Trả tiền mặt từ ' . number_format( self::NGUONG_TIEN_MAT, 0, ',', '.' ) . ' đ trở lên' );
		}
		return array( true, '' );
	}

	public static function them( $d ) {
		global $wpdb;
		$ngay = KHTC_GiaoDich::doc_ngay( $d['ngay'] ?? '' );
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Ngày hoá đơn không đọc được. Dạng đúng: 20/07/2026.' );
		}
		$so_hd = trim( (string) ( $d['so_hd'] ?? '' ) );
		if ( '' === $so_hd ) {
			return new WP_Error( 'so_hd', 'Chưa có số hoá đơn.' );
		}
		$mst = trim( (string) ( $d['mst'] ?? '' ) );
		if ( self::da_co( $so_hd, $mst ) ) {
			return new WP_Error( 'trung', 'Hoá đơn ' . $so_hd . ( $mst ? ' của MST ' . $mst : '' ) . ' đã có trong sổ.' );
		}
		$chan = KHTC_Khoa::chan( $ngay, 'thêm' );
		if ( $chan ) { return $chan; }

		list( $chua, $vat, $co, $ts ) = KHTC_HoaDonRa::tinh(
			KHTC_GiaoDich::doc_so( $d['chua_vat'] ?? 0 ),
			KHTC_GiaoDich::doc_so( $d['vat'] ?? 0 ),
			KHTC_GiaoDich::doc_so( $d['co_vat'] ?? 0 ),
			(string) ( $d['thue_suat'] ?? '' )
		);
		if ( $co <= 0 ) {
			return new WP_Error( 'tien', 'Hoá đơn phải có tiền: điền Chưa VAT hoặc Có VAT.' );
		}

		$hinh_thuc = ( ( $d['hinh_thuc'] ?? '' ) === 'tien_mat' ) ? 'tien_mat' : 'chuyen_khoan';
		if ( array_key_exists( 'khau_tru', $d ) && '' !== (string) $d['khau_tru'] ) {
			$kt = (int) $d['khau_tru'] ? 1 : 0;
			$ly = $kt ? '' : trim( (string) ( $d['ly_do'] ?? '' ) );
		} else {
			list( $co_kt, $ly ) = self::mac_dinh_khau_tru( $co, $hinh_thuc );
			$kt = $co_kt ? 1 : 0;
		}

		$wpdb->insert(
			KHTC_DB::bang( 'hd_vao' ),
			array(
				'cty'          => KHTC_Cty::dang_chon(),
				'ngay'         => $ngay,
				'so_hd'        => $so_hd,
				'nha_cung_cap' => trim( (string) ( $d['nha_cung_cap'] ?? '' ) ),
				'mst'          => $mst,
				'dia_chi'      => trim( (string) ( $d['dia_chi'] ?? '' ) ),
				'noi_dung'     => (string) ( $d['noi_dung'] ?? '' ),
				'chua_vat'     => $chua,
				'thue_suat'    => $ts,
				'vat'          => $vat,
				'co_vat'       => $co,
				'hinh_thuc'    => $hinh_thuc,
				'khau_tru'     => $kt,
				'ly_do'        => (string) $ly,
				'chi_phi_id'   => (int) ( $d['chi_phi_id'] ?? 0 ),
				'ghi_chu'      => (string) ( $d['ghi_chu'] ?? '' ),
				'tao_luc'      => current_time( 'mysql' ),
				'tao_boi'      => wp_get_current_user()->display_name,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi(
			'them',
			'hd_vao',
			$moi,
			sprintf(
				'Hoá đơn vào %s ngày %s — %s, %s đ (%s)%s',
				$so_hd,
				mysql2date( 'd/m/Y', $ngay ),
				trim( (string) ( $d['nha_cung_cap'] ?? '' ) ),
				number_format( $co, 0, ',', '.' ),
				is_numeric( $ts ) ? $ts . '%' : $ts,
				$kt ? '' : ' — KHÔNG khấu trừ'
			)
		);
		return $moi;
	}

	/**
	 * Trùng tính theo CẶP số hoá đơn + mã số thuế, không theo số hoá đơn không.
	 *
	 * Số hoá đơn do bên bán đánh, nên hai nhà cung cấp khác nhau phát hành cùng
	 * số 00000001 là chuyện bình thường. Chặn theo mình số hoá đơn thì nhà cung
	 * cấp thứ hai không nhập được hoá đơn hợp lệ của họ.
	 */
	public static function da_co( $so_hd, $mst, $cty = null ) {
		global $wpdb;
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'hd_vao' ) . ' WHERE cty = %s AND so_hd = %s AND mst = %s',
				$cty,
				$so_hd,
				(string) $mst
			)
		) > 0;
	}

	public static function xoa( $id ) {
		global $wpdb;
		$h = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'hd_vao' ) . ' WHERE id = %d AND cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
		if ( ! $h ) { return false; }
		$chan = KHTC_Khoa::chan( $h->ngay, 'xoá' );
		if ( $chan ) { return $chan; }
		KHTC_NhatKy::ghi_xoa(
			'hd_vao',
			$id,
			sprintf( 'Xoá hoá đơn vào %s ngày %s — %s, %s đ', $h->so_hd, mysql2date( 'd/m/Y', $h->ngay ), $h->nha_cung_cap, number_format( $h->co_vat, 0, ',', '.' ) )
		);
		$wpdb->delete( KHTC_DB::bang( 'hd_vao' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	public static function dat_khau_tru( $id, $co_khau_tru, $ly_do = '' ) {
		global $wpdb;
		$h = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'hd_vao' ) . ' WHERE id = %d AND cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
		if ( ! $h ) { return false; }
		$chan = KHTC_Khoa::chan( $h->ngay, 'sửa' );
		if ( $chan ) { return $chan; }
		$kt = $co_khau_tru ? 1 : 0;
		$wpdb->update(
			KHTC_DB::bang( 'hd_vao' ),
			array( 'khau_tru' => $kt, 'ly_do' => $kt ? '' : (string) $ly_do ),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		KHTC_NhatKy::ghi(
			'sua',
			'hd_vao',
			(int) $id,
			sprintf( 'Hoá đơn vào %s: %s', $h->so_hd, $kt ? 'chuyển sang ĐƯỢC khấu trừ' : 'chuyển sang KHÔNG khấu trừ' . ( $ly_do ? ' — ' . $ly_do : '' ) )
		);
		return true;
	}

	/** Dán bảng: Ngày · Số HĐ · Nhà cung cấp · MST · Nội dung · Chưa VAT · VAT · Có VAT · Hình thức. */
	public static function dan_hang_loat( $text ) {
		$them  = 0;
		$trung = 0;
		$loi   = array();
		KHTC_NhatKy::mo_lo();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d, ',', '"', '' );
			$o = array_map( 'trim', $o );
			if ( isset( $o[0] ) && ( 'Ngày HĐ' === $o[0] || 'Ngày' === $o[0] ) ) { continue; }
			if ( count( $o ) < 6 ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần ít nhất Ngày, Số HĐ, Nhà cung cấp, MST, Nội dung, Chưa VAT.';
				continue;
			}
			$kq = self::them(
				array(
					'ngay'         => $o[0],
					'so_hd'        => $o[1],
					'nha_cung_cap' => $o[2],
					'mst'          => $o[3],
					'noi_dung'     => $o[4],
					'chua_vat'     => $o[5],
					'vat'          => $o[6] ?? 0,
					'co_vat'       => $o[7] ?? 0,
					'hinh_thuc'    => ( isset( $o[8] ) && mb_stripos( $o[8], 'mặt' ) !== false ) || ( isset( $o[8] ) && mb_stripos( $o[8], 'mat' ) !== false ) ? 'tien_mat' : 'chuyen_khoan',
				)
			);
			if ( is_wp_error( $kq ) ) {
				if ( 'trung' === $kq->get_error_code() ) { $trung++; } else { $loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message(); }
				continue;
			}
			$them++;
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi( 'nap', 'hd_vao', 0, sprintf( 'Nạp %d hoá đơn đầu vào%s', $them, $trung ? ', bỏ ' . $trung . ' trùng' : '' ), null, true );
		return array( 'them' => $them, 'trung' => $trung, 'loi' => $loi );
	}

	// ----------------------------------------------------------------- lọc

	private static function dieu_kien( $l ) {
		global $wpdb;
		$dk   = array( 'h.cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );
		if ( ! empty( $l['tu'] ) )        { $dk[] = 'h.ngay >= %s';     $args[] = $l['tu']; }
		if ( ! empty( $l['den'] ) )       { $dk[] = 'h.ngay <= %s';     $args[] = $l['den']; }
		if ( ! empty( $l['thue_suat'] ) ) { $dk[] = 'h.thue_suat = %s'; $args[] = $l['thue_suat']; }
		if ( isset( $l['khau_tru'] ) && '' !== (string) $l['khau_tru'] ) {
			$dk[]   = 'h.khau_tru = %d';
			$args[] = (int) $l['khau_tru'];
		}
		if ( ! empty( $l['tim'] ) ) {
			$dk[]   = '( h.nha_cung_cap LIKE %s OR h.so_hd LIKE %s OR h.mst LIKE %s OR h.noi_dung LIKE %s )';
			$t      = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
			array_push( $args, $t, $t, $t, $t );
		}
		return array( 'WHERE ' . implode( ' AND ', $dk ), $args );
	}

	public static function loc( $l = array() ) {
		global $wpdb;
		$b = KHTC_DB::bang( 'hd_vao' );
		list( $where, $args ) = self::dieu_kien( $l );

		$tong = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS so_hd,
					COALESCE(SUM(h.chua_vat),0) AS chua_vat,
					COALESCE(SUM(h.vat),0) AS vat,
					COALESCE(SUM(h.co_vat),0) AS co_vat,
					COALESCE(SUM(CASE WHEN h.khau_tru = 1 THEN h.vat ELSE 0 END),0) AS vat_kt
				 FROM $b h $where",
				$args
			)
		);

		$trang = max( 1, (int) ( $l['trang'] ?? 1 ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.* FROM $b h $where ORDER BY h.ngay DESC, h.id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( self::MOI_TRANG, ( $trang - 1 ) * self::MOI_TRANG ) )
			)
		);

		return array(
			'rows'       => $rows,
			'so_hd'      => (int) $tong->so_hd,
			'chua_vat'   => (int) $tong->chua_vat,
			'vat'        => (int) $tong->vat,
			'co_vat'     => (int) $tong->co_vat,
			'vat_kt'     => (int) $tong->vat_kt,
			'vat_khong'  => (int) $tong->vat - (int) $tong->vat_kt,
			'trang'      => $trang,
			'so_trang'   => max( 1, (int) ceil( $tong->so_hd / self::MOI_TRANG ) ),
		);
	}

	public static function gom_theo( $cot, $l = array() ) {
		global $wpdb;
		if ( ! in_array( $cot, array( 'thue_suat', 'nha_cung_cap' ), true ) ) {
			return array();
		}
		$b = KHTC_DB::bang( 'hd_vao' );
		list( $where, $args ) = self::dieu_kien( $l );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.$cot AS nhan, COUNT(*) AS so_hd,
					COALESCE(SUM(h.chua_vat),0) AS chua_vat,
					COALESCE(SUM(h.vat),0) AS vat,
					COALESCE(SUM(h.co_vat),0) AS co_vat,
					COALESCE(SUM(CASE WHEN h.khau_tru = 1 THEN h.vat ELSE 0 END),0) AS vat_kt
				 FROM $b h $where GROUP BY h.$cot ORDER BY SUM(h.co_vat) DESC",
				$args
			)
		);
	}

	// ------------------------------------------------------------ tờ khai

	/**
	 * Số phải nộp trong kỳ: VAT đầu ra − VAT đầu vào ĐƯỢC KHẤU TRỪ.
	 *
	 * Đây là con số cuối cùng của cả hai màn hình hoá đơn, và là lý do làm hoá
	 * đơn đầu vào. Ra số âm nghĩa là được khấu trừ chuyển sang kỳ sau, không
	 * phải nhà nước trả lại — nên gọi đúng tên là "chuyển kỳ sau".
	 */
	public static function to_khai( $tu, $den ) {
		$ra   = KHTC_HoaDonRa::loc( array( 'tu' => $tu, 'den' => $den ) );
		$vao  = self::loc( array( 'tu' => $tu, 'den' => $den ) );
		$hieu = (int) $ra['vat'] - (int) $vao['vat_kt'];
		return array(
			'dt_ra'       => (int) $ra['chua_vat'],
			'vat_ra'      => (int) $ra['vat'],
			'so_hd_ra'    => (int) $ra['so_hd'],
			'mua_vao'     => (int) $vao['chua_vat'],
			'vat_vao'     => (int) $vao['vat'],
			'vat_kt'      => (int) $vao['vat_kt'],
			'vat_khong'   => (int) $vao['vat_khong'],
			'so_hd_vao'   => (int) $vao['so_hd'],
			'phai_nop'    => max( 0, $hieu ),
			'chuyen_ky'   => max( 0, -$hieu ),
		);
	}

	// ------------------------------------------------------------- tải CSV

	public static function hang_csv( $rows ) {
		$ra  = array( self::cot() );
		$stt = 0;
		foreach ( $rows as $h ) {
			$stt++;
			$ra[] = array(
				$stt, mysql2date( 'd/m/Y', $h->ngay ), $h->so_hd, $h->nha_cung_cap, $h->mst,
				$h->noi_dung, $h->chua_vat, $h->vat, $h->co_vat,
				is_numeric( $h->thue_suat ) ? $h->thue_suat . '%' : $h->thue_suat,
				'tien_mat' === $h->hinh_thuc ? 'Tiền mặt' : 'Chuyển khoản',
				$h->khau_tru ? 'Có' : 'Không',
				$h->ly_do,
				$h->ghi_chu,
			);
		}
		return $ra;
	}

	public static function tai_csv() {
		if ( empty( $_GET['khtc_tai_hdv'] ) ) { return; }
		if ( ! is_user_logged_in() || ! current_user_can( KHTC_CAP ) ) { return; }
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'khtc_tai_hdv' ) ) {
			return;
		}
		global $wpdb;
		$l = array(
			'tu'        => sanitize_text_field( wp_unslash( $_GET['tu'] ?? '' ) ),
			'den'       => sanitize_text_field( wp_unslash( $_GET['den'] ?? '' ) ),
			'thue_suat' => sanitize_text_field( wp_unslash( $_GET['ts'] ?? '' ) ),
			'khau_tru'  => isset( $_GET['kt'] ) ? sanitize_text_field( wp_unslash( $_GET['kt'] ) ) : '',
			'tim'       => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
		);
		list( $where, $args ) = self::dieu_kien( $l );
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT h.* FROM ' . KHTC_DB::bang( 'hd_vao' ) . " h $where ORDER BY h.ngay, h.so_hd", $args )
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="hoa-don-dau-vao-' . ( $l['tu'] ? $l['tu'] : 'tat-ca' ) . '_' . ( $l['den'] ? $l['den'] : '' ) . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		fwrite( $f, "\xEF\xBB\xBF" );
		foreach ( self::hang_csv( $rows ) as $hang ) {
			fputcsv( $f, $hang );
		}
		fclose( $f );
		exit;
	}
}
