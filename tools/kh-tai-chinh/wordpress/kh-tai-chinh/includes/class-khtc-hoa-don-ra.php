<?php
/**
 * Hoá đơn đầu ra.
 *
 * Nối thẳng với công cụ Đối soát VAT đang dùng: bảng dán vào nhận ĐÚNG 22 cột
 * mà file kia sinh ra, theo đúng thứ tự, nên copy từ Excel là dán được, không
 * phải sắp lại cột. Xuất ra cũng đúng 22 cột đó.
 *
 * PHÉP TÍNH VAT LÀ CHỖ DUY NHẤT KHÔNG ĐƯỢC SAI. Quy tắc bất di bất dịch:
 *
 *     chưa VAT + VAT = có VAT, luôn luôn, từng dòng một.
 *
 * Nên hàm tinh() chỉ làm tròn MỘT lần rồi lấy hiệu ra số thứ ba, không bao giờ
 * làm tròn hai số rồi cộng. Làm tròn hai lần thì lệch 1 đồng — số vẫn hợp lệ,
 * bảng vẫn in ra, nhưng Misa từ chối cả tệp và không nói vì sao.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_HoaDonRa {

	const MOI_TRANG = 100;

	/** Bậc thuế dùng nhiều nhất, để sẵn trong ô chọn khi ghi tay. */
	const TS_MAC_DINH = '8';

	/** Cột của file Đối soát VAT, đúng thứ tự. Dùng cho cả dán vào lẫn xuất ra. */
	public static function cot() {
		return array(
			'STT', 'Ngày HĐ', 'Số HĐ', 'Tên khách hàng', 'Mã số thuế khách hàng',
			'Địa chỉ khách hàng', 'Email nhận hóa đơn', 'Nội dung xuất hóa đơn',
			'Số lượng', 'ĐVT', 'Thành tiền', 'Chưa VAT', 'VAT', 'Có VAT',
			'Khu vực', 'Dịch vụ', 'Số hợp đồng (nếu có)', 'Mã điểm nội bộ',
			'Mã điểm misa', 'Ghi chú', '', 'Địa chỉ',
		);
	}

	/**
	 * Thuế suất dùng được. KCT = không chịu thuế, tính như 0% nhưng khai riêng.
	 *
	 * Lưu ý khi duyệt: PHP đổi khoá mảng '0','5','8','10' thành SỐ NGUYÊN, chỉ
	 * 'KCT' còn là chuỗi. So sánh khoá phải ép (string) trước.
	 */
	public static function thue_suat() {
		return array( '0' => '0%', '5' => '5%', '8' => '8%', '10' => '10%', 'KCT' => 'KCT — không chịu thuế' );
	}

	public static function ty_le( $ts ) {
		return is_numeric( $ts ) ? ( (float) $ts / 100 ) : 0.0;
	}

	/**
	 * Điền cho đủ bộ ba chưa VAT / VAT / có VAT sao cho tổng luôn khớp.
	 *
	 * Nhận vào số nào biết số nấy (0 nghĩa là chưa biết), trả về đủ ba số cùng
	 * thuế suất. Luôn chốt MỘT số làm gốc, làm tròn đúng một lần, số còn lại
	 * lấy bằng phép trừ.
	 *
	 * Cờ "có lệch" chỉ bật khi file gốc đưa đủ cả chưa VAT lẫn VAT mà tổng
	 * không bằng có VAT. Trường hợp cột VAT để trống thì không coi là lệch —
	 * không có cách nào phân biệt ô trống với số 0 trong một bảng dán vào.
	 *
	 * @param int    $chua Chưa VAT, 0 nếu chưa biết.
	 * @param int    $vat  Tiền thuế, 0 nếu chưa biết.
	 * @param int    $co   Có VAT, 0 nếu chưa biết.
	 * @param string $ts   Thuế suất: '0','5','8','10','KCT' hoặc '' để tự suy.
	 * @return array [chưa VAT, VAT, có VAT, thuế suất, có lệch với số gốc hay không]
	 */
	public static function tinh( $chua, $vat, $co, $ts = '' ) {
		$chua = (int) $chua;
		$vat  = (int) $vat;
		$co   = (int) $co;
		$lech = false;

		// Thứ tự ưu tiên, chắc chắn giảm dần. Lưu ý: 0 vừa nghĩa là "ô để trống"
		// vừa nghĩa là "thuế đúng bằng 0" — không phân biệt được, nên quy ước
		// VAT = 0 luôn hiểu là CHƯA BIẾT và tính lại theo thuế suất. Hoá đơn
		// 0% vẫn ra đúng vì thuế suất 0 cho lại VAT = 0.
		if ( $chua > 0 && $vat > 0 ) {
			// Có cả gốc lẫn thuế: hai số này là gốc, có VAT chỉ là tổng của chúng.
			$moi = $chua + $vat;
			if ( $co > 0 && $co !== $moi ) { $lech = true; }
			$co = $moi;
		} elseif ( $co > 0 && $vat > 0 ) {
			$chua = $co - $vat;
		} elseif ( $co > 0 && $chua > 0 ) {
			$vat = $co - $chua;
		} elseif ( $co > 0 ) {
			$r    = self::ty_le( $ts );
			$chua = (int) round( $co / ( 1 + $r ) );
			$vat  = $co - $chua;
		} elseif ( $chua > 0 ) {
			$r   = self::ty_le( $ts );
			$vat = (int) round( $chua * $r );
			$co  = $chua + $vat;
		}

		// Thuế suất không ghi thì suy ngược từ số. Làm tròn về mốc quen thuộc
		// để 7,99% do làm tròn không thành một bậc thuế mới trong bảng tờ khai.
		if ( '' === (string) $ts ) {
			$ts = '0';
			if ( $chua > 0 ) {
				$p = $vat * 100 / $chua;
				foreach ( array( '10', '8', '5', '0' ) as $moc ) {
					if ( abs( $p - (float) $moc ) < 0.5 ) { $ts = $moc; break; }
				}
			}
		}

		return array( $chua, $vat, $co, (string) $ts, $lech );
	}

	// ------------------------------------------------------------------ ghi

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
		if ( self::da_co( $so_hd ) ) {
			return new WP_Error( 'trung', 'Số hoá đơn ' . $so_hd . ' đã có trong sổ.' );
		}

		list( $chua, $vat, $co, $ts ) = self::tinh(
			KHTC_GiaoDich::doc_so( $d['chua_vat'] ?? 0 ),
			KHTC_GiaoDich::doc_so( $d['vat'] ?? 0 ),
			KHTC_GiaoDich::doc_so( $d['co_vat'] ?? 0 ),
			(string) ( $d['thue_suat'] ?? '' )
		);
		if ( $co <= 0 ) {
			return new WP_Error( 'tien', 'Hoá đơn phải có tiền: điền Chưa VAT hoặc Có VAT.' );
		}

		$wpdb->insert(
			KHTC_DB::bang( 'hd_ra' ),
			array(
				'cty'          => KHTC_Cty::dang_chon(),
				'ngay'         => $ngay,
				'so_hd'        => $so_hd,
				'khach'        => trim( (string) ( $d['khach'] ?? '' ) ),
				'mst'          => trim( (string) ( $d['mst'] ?? '' ) ),
				'dia_chi_kh'   => trim( (string) ( $d['dia_chi_kh'] ?? '' ) ),
				'email'        => trim( (string) ( $d['email'] ?? '' ) ),
				'noi_dung'     => (string) ( $d['noi_dung'] ?? '' ),
				'so_luong'     => trim( (string) ( $d['so_luong'] ?? '' ) ),
				'dvt'          => trim( (string) ( $d['dvt'] ?? '' ) ),
				'thanh_tien'   => (int) KHTC_GiaoDich::doc_so( $d['thanh_tien'] ?? 0 ),
				'chua_vat'     => $chua,
				'thue_suat'    => $ts,
				'vat'          => $vat,
				'co_vat'       => $co,
				'khu_vuc'      => trim( (string) ( $d['khu_vuc'] ?? '' ) ),
				'dich_vu'      => trim( (string) ( $d['dich_vu'] ?? '' ) ),
				'so_hop_dong'  => trim( (string) ( $d['so_hop_dong'] ?? '' ) ),
				'ma_diem'      => trim( (string) ( $d['ma_diem'] ?? '' ) ),
				'ma_misa'      => trim( (string) ( $d['ma_misa'] ?? '' ) ),
				'ghi_chu'      => (string) ( $d['ghi_chu'] ?? '' ),
				'dia_chi'      => trim( (string) ( $d['dia_chi'] ?? '' ) ),
				'tao_luc'      => current_time( 'mysql' ),
				'tao_boi'      => wp_get_current_user()->display_name,
			),
			array_fill( 0, 24, '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** Số hoá đơn phải duy nhất trong một pháp nhân — xuất trùng số là sai luật. */
	public static function da_co( $so_hd, $cty = null ) {
		global $wpdb;
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'hd_ra' ) . ' WHERE cty = %s AND so_hd = %s',
				$cty,
				$so_hd
			)
		) > 0;
	}

	public static function xoa( $id ) {
		global $wpdb;
		$wpdb->delete(
			KHTC_DB::bang( 'hd_ra' ),
			array( 'id' => (int) $id, 'cty' => KHTC_Cty::dang_chon() ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Dán bảng từ file Đối soát VAT — đúng 22 cột, đúng thứ tự, kể cả cột STT
	 * và cột trống thứ 21. Dòng tiêu đề tự nhận ra và bỏ qua.
	 */
	public static function dan_hang_loat( $text ) {
		$them  = 0;
		$trung = 0;
		$lech  = 0;
		$loi   = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d );
			$o = array_map( 'trim', $o );
			// Dán cả dòng tiêu đề là chuyện thường khi bôi đen cả bảng trong Excel.
			if ( isset( $o[1] ) && ( 'Ngày HĐ' === $o[1] || 'Số HĐ' === ( $o[2] ?? '' ) ) ) { continue; }
			if ( count( $o ) < 14 ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần đủ 14 cột đầu (đến cột Có VAT).';
				continue;
			}

			$kq = self::them(
				array(
					'ngay'        => $o[1],
					'so_hd'       => $o[2],
					'khach'       => $o[3],
					'mst'         => $o[4],
					'dia_chi_kh'  => $o[5],
					'email'       => $o[6],
					'noi_dung'    => $o[7],
					'so_luong'    => $o[8],
					'dvt'         => $o[9],
					'thanh_tien'  => $o[10],
					'chua_vat'    => $o[11],
					'vat'         => $o[12],
					'co_vat'      => $o[13],
					'khu_vuc'     => $o[14] ?? '',
					'dich_vu'     => $o[15] ?? '',
					'so_hop_dong' => $o[16] ?? '',
					'ma_diem'     => $o[17] ?? '',
					'ma_misa'     => $o[18] ?? '',
					'ghi_chu'     => $o[19] ?? '',
					'dia_chi'     => $o[21] ?? '',
				)
			);
			if ( is_wp_error( $kq ) ) {
				if ( 'trung' === $kq->get_error_code() ) { $trung++; } else { $loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message(); }
				continue;
			}
			// Đếm riêng dòng mà chưa VAT + VAT không bằng có VAT trong file gốc.
			$kt = self::tinh(
				KHTC_GiaoDich::doc_so( $o[11] ),
				KHTC_GiaoDich::doc_so( $o[12] ),
				KHTC_GiaoDich::doc_so( $o[13] )
			);
			if ( $kt[4] ) { $lech++; }
			$them++;
		}
		return array( 'them' => $them, 'trung' => $trung, 'lech' => $lech, 'loi' => $loi );
	}

	// ----------------------------------------------------------------- lọc

	private static function dieu_kien( $l ) {
		global $wpdb;
		$dk   = array( 'h.cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );
		if ( ! empty( $l['tu'] ) )        { $dk[] = 'h.ngay >= %s';     $args[] = $l['tu']; }
		if ( ! empty( $l['den'] ) )       { $dk[] = 'h.ngay <= %s';     $args[] = $l['den']; }
		if ( ! empty( $l['khu_vuc'] ) )   { $dk[] = 'h.khu_vuc = %s';   $args[] = $l['khu_vuc']; }
		if ( ! empty( $l['dich_vu'] ) )   { $dk[] = 'h.dich_vu = %s';   $args[] = $l['dich_vu']; }
		if ( ! empty( $l['thue_suat'] ) ) { $dk[] = 'h.thue_suat = %s'; $args[] = $l['thue_suat']; }
		if ( ! empty( $l['tim'] ) ) {
			$dk[]   = '( h.khach LIKE %s OR h.so_hd LIKE %s OR h.mst LIKE %s OR h.noi_dung LIKE %s )';
			$t      = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
			array_push( $args, $t, $t, $t, $t );
		}
		return array( 'WHERE ' . implode( ' AND ', $dk ), $args );
	}

	public static function loc( $l = array() ) {
		global $wpdb;
		$b = KHTC_DB::bang( 'hd_ra' );
		list( $where, $args ) = self::dieu_kien( $l );

		$tong = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS so_hd,
					COALESCE(SUM(h.chua_vat),0) AS chua_vat,
					COALESCE(SUM(h.vat),0) AS vat,
					COALESCE(SUM(h.co_vat),0) AS co_vat
				 FROM $b h $where",
				$args
			)
		);

		$trang = max( 1, (int) ( $l['trang'] ?? 1 ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.* FROM $b h $where ORDER BY h.ngay DESC, h.so_hd DESC, h.id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( self::MOI_TRANG, ( $trang - 1 ) * self::MOI_TRANG ) )
			)
		);

		return array(
			'rows'     => $rows,
			'so_hd'    => (int) $tong->so_hd,
			'chua_vat' => (int) $tong->chua_vat,
			'vat'      => (int) $tong->vat,
			'co_vat'   => (int) $tong->co_vat,
			'trang'    => $trang,
			'so_trang' => max( 1, (int) ceil( $tong->so_hd / self::MOI_TRANG ) ),
		);
	}

	/**
	 * Gom theo một cột bất kỳ — thuế suất, khu vực hay dịch vụ.
	 *
	 * Bảng gom theo THUẾ SUẤT chính là mấy dòng phải điền vào tờ khai GTGT, nên
	 * nó là lý do tồn tại của cả màn hình này: khỏi phải lọc từng bậc thuế rồi
	 * chép số sang tờ khai bằng tay.
	 */
	public static function gom_theo( $cot, $l = array() ) {
		global $wpdb;
		if ( ! in_array( $cot, array( 'thue_suat', 'khu_vuc', 'dich_vu' ), true ) ) {
			return array();
		}
		$b = KHTC_DB::bang( 'hd_ra' );
		list( $where, $args ) = self::dieu_kien( $l );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.$cot AS nhan, COUNT(*) AS so_hd,
					COALESCE(SUM(h.chua_vat),0) AS chua_vat,
					COALESCE(SUM(h.vat),0) AS vat,
					COALESCE(SUM(h.co_vat),0) AS co_vat
				 FROM $b h $where GROUP BY h.$cot ORDER BY SUM(h.co_vat) DESC",
				$args
			)
		);
	}

	/** Giá trị đang có thật trong sổ, để đổ vào ô chọn của bộ lọc. */
	public static function gia_tri_co( $cot ) {
		global $wpdb;
		if ( ! in_array( $cot, array( 'khu_vuc', 'dich_vu' ), true ) ) { return array(); }
		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT ' . $cot . ' FROM ' . KHTC_DB::bang( 'hd_ra' ) . " WHERE cty = %s AND $cot <> '' ORDER BY $cot",
				KHTC_Cty::dang_chon()
			)
		);
	}

	// ------------------------------------------------------------- tải CSV

	/** Xuất đúng 22 cột của file Đối soát VAT để đem đi nộp hoặc nạp vào Misa. */
	public static function tai_csv() {
		if ( empty( $_GET['khtc_tai_hd'] ) ) { return; }
		if ( ! is_user_logged_in() || ! current_user_can( KHTC_CAP ) ) { return; }
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'khtc_tai_hd' ) ) {
			return;
		}
		global $wpdb;
		$l = array(
			'tu'        => sanitize_text_field( wp_unslash( $_GET['tu'] ?? '' ) ),
			'den'       => sanitize_text_field( wp_unslash( $_GET['den'] ?? '' ) ),
			'khu_vuc'   => sanitize_text_field( wp_unslash( $_GET['kv'] ?? '' ) ),
			'dich_vu'   => sanitize_text_field( wp_unslash( $_GET['dv'] ?? '' ) ),
			'thue_suat' => sanitize_text_field( wp_unslash( $_GET['ts'] ?? '' ) ),
			'tim'       => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
		);
		list( $where, $args ) = self::dieu_kien( $l );
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT h.* FROM ' . KHTC_DB::bang( 'hd_ra' ) . " h $where ORDER BY h.ngay, h.so_hd", $args )
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="hoa-don-dau-ra-' . ( $l['tu'] ? $l['tu'] : 'tat-ca' ) . '_' . ( $l['den'] ? $l['den'] : '' ) . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		// BOM UTF-8: thiếu nó Excel bản Việt đọc "Khu vực" thành "Khu vá»±c".
		fwrite( $f, "\xEF\xBB\xBF" );
		foreach ( self::hang_csv( $rows ) as $hang ) {
			fputcsv( $f, $hang );
		}
		fclose( $f );
		exit;
	}

	/**
	 * Dòng của tệp xuất ra, kể cả dòng tiêu đề — tách khỏi tai_csv() để kiểm
	 * được mà không phải gửi header rồi exit.
	 *
	 * Thứ tự ở đây phải khớp ĐÚNG thứ tự mà dan_hang_loat() đọc vào: xuất ra
	 * rồi dán lại phải ra y hệt. Lệch một cột thì dữ liệu vẫn nạp được, chỉ là
	 * mã điểm chui sang ô ghi chú — không có gì báo.
	 */
	public static function hang_csv( $rows ) {
		$ra  = array( self::cot() );
		$stt = 0;
		foreach ( $rows as $h ) {
			$stt++;
			$ra[] = array(
				$stt, mysql2date( 'd/m/Y', $h->ngay ), $h->so_hd, $h->khach, $h->mst,
				$h->dia_chi_kh, $h->email, $h->noi_dung, $h->so_luong, $h->dvt,
				$h->thanh_tien, $h->chua_vat, $h->vat, $h->co_vat,
				$h->khu_vuc, $h->dich_vu, $h->so_hop_dong, $h->ma_diem,
				$h->ma_misa, $h->ghi_chu, '', $h->dia_chi,
			);
		}
		return $ra;
	}
}
