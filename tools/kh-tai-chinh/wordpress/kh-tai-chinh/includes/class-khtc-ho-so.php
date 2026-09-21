<?php
/**
 * Hồ sơ — sổ lưu chứng từ giấy.
 *
 * Trả lời hai câu, và câu thứ hai mới là câu khó:
 *
 *   1. Chứng từ này mình có giữ không, file ở đâu?
 *   2. Bút toán trong sổ kia đã có chứng từ lưu chưa?
 *
 * Bản gốc (routes/ho-so.js, 1.497 dòng) chỉ trả lời được câu 1: nó là một danh
 * sách giấy tờ với ô đánh dấu "đã hạch toán" gõ tay, không nối với sổ sách nào.
 * Ở đây plugin đã có sẵn hoá đơn đầu vào, đầu ra, chi phí và hợp đồng, nên nối
 * được thật — và câu 2 chính là câu kiểm toán sẽ hỏi.
 *
 * KHÔNG TỰ SUY "ĐÃ HẠCH TOÁN" TỪ VIỆC GẮN ĐƯỢC. Giữ hai thứ riêng: cờ đã hạch
 * toán do kế toán bật, và liên kết tới bút toán do máy dò. Chúng có thể khác
 * nhau một cách hợp lệ — sổ hạch toán trên Misa ngoài hệ thống này chẳng hạn —
 * nên màn hình CHỈ RA CHỖ LỆCH thay vì gộp lại thành một con số. Với một sổ
 * đối chiếu giấy với sổ, che mất chỗ lệch là bỏ mất chính công việc.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_HoSo {

	const MOI_TRANG = 100;

	public static function loai() {
		return array(
			'hd_vao'  => 'Hoá đơn đầu vào',
			'hd_ra'   => 'Hoá đơn đầu ra',
			'chi_phi' => 'Chứng từ chi phí',
			'hop_dong' => 'Hợp đồng',
			'uy_nhiem' => 'Uỷ nhiệm chi',
			'bien_ban' => 'Biên bản',
			'khac'     => 'Khác',
		);
	}

	/**
	 * Loại nào nối được sang một sổ thật, và nối bằng cột nào.
	 *
	 * `nguoc` quyết định loại đó có xuất hiện trong bảng chiều ngược ("bút
	 * toán chưa có chứng từ") hay không. Hợp đồng nối được nhưng KHÔNG vào
	 * bảng đó: màn hình Pháp danh đã đếm "chưa có bản đủ dấu" ngay trên chính
	 * hợp đồng. Trả lời cùng một câu ở hai nơi bằng hai cách đo khác nhau thì
	 * sớm muộn hai chỗ nói khác nhau, và không ai biết bên nào đúng.
	 */
	public static function noi_duoc() {
		return array(
			'hd_vao'   => array( 'bang' => 'hd_vao', 'cot_so' => 'so_hd', 'cot_tien' => 'co_vat', 'cot_ten' => 'nha_cung_cap', 'cot_ngay' => 'ngay', 'nguoc' => true ),
			'hd_ra'    => array( 'bang' => 'hd_ra', 'cot_so' => 'so_hd', 'cot_tien' => 'co_vat', 'cot_ten' => 'khach', 'cot_ngay' => 'ngay', 'nguoc' => true ),
			'chi_phi'  => array( 'bang' => 'chi_phi', 'cot_so' => 'so_ct', 'cot_tien' => 'so_tien', 'cot_ten' => 'nha_cung_cap', 'cot_ngay' => 'ngay', 'nguoc' => true ),
			'hop_dong' => array( 'bang' => 'hop_dong', 'cot_so' => 'so_hd', 'cot_tien' => 'gia_tri', 'cot_ten' => 'doi_tac', 'cot_ngay' => 'ngay_ky', 'nguoc' => false ),
		);
	}

	// ------------------------------------------------------------------ ghi

	public static function them( $d ) {
		global $wpdb;
		$loai = isset( self::loai()[ $d['loai'] ?? '' ] ) ? $d['loai'] : 'khac';
		$so   = trim( (string) ( $d['so_ct'] ?? '' ) );
		$ngay = KHTC_GiaoDich::doc_ngay( $d['ngay'] ?? '' );
		if ( '' === $so && '' === trim( (string) ( $d['ten_file'] ?? '' ) ) ) {
			return new WP_Error( 'trong', 'Chứng từ phải có ít nhất số chứng từ hoặc tên file.' );
		}
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Ngày chứng từ không đọc được. Dạng đúng: 20/07/2026.' );
		}
		$chan = KHTC_Khoa::chan( $ngay, 'thêm' );
		if ( $chan ) { return $chan; }

		$wpdb->insert(
			KHTC_DB::bang( 'ho_so' ),
			array(
				'cty'           => KHTC_Cty::dang_chon(),
				'loai'          => $loai,
				'so_ct'         => $so,
				'ngay'          => $ngay,
				'doi_tac'       => trim( (string) ( $d['doi_tac'] ?? '' ) ),
				'so_tien'       => abs( (int) KHTC_GiaoDich::doc_so( $d['so_tien'] ?? 0 ) ),
				'ten_file'      => trim( (string) ( $d['ten_file'] ?? '' ) ),
				'link_file'     => esc_url_raw( (string) ( $d['link_file'] ?? '' ) ),
				'gan_bang'      => '',
				'gan_id'        => 0,
				'da_hach_toan'  => ! empty( $d['da_hach_toan'] ) ? 1 : 0,
				'ghi_chu'       => (string) ( $d['ghi_chu'] ?? '' ),
				'tao_luc'       => current_time( 'mysql' ),
				'tao_boi'       => wp_get_current_user()->display_name,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi(
			'them',
			'ho_so',
			$moi,
			sprintf( 'Hồ sơ %s — %s ngày %s', self::loai()[ $loai ], $so ? $so : ( $d['ten_file'] ?? '' ), mysql2date( 'd/m/Y', $ngay ) )
		);
		return $moi;
	}

	public static function mot( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'ho_so' ) . ' WHERE id = %d AND cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
	}

	public static function xoa( $id ) {
		global $wpdb;
		$h = self::mot( $id );
		if ( ! $h ) { return false; }
		$chan = KHTC_Khoa::chan( $h->ngay, 'xoá' );
		if ( $chan ) { return $chan; }
		KHTC_NhatKy::ghi_xoa( 'ho_so', $id, sprintf( 'Xoá hồ sơ %s ngày %s', $h->so_ct ? $h->so_ct : $h->ten_file, mysql2date( 'd/m/Y', $h->ngay ) ) );
		$wpdb->delete( KHTC_DB::bang( 'ho_so' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	public static function dat_hach_toan( $id, $bat ) {
		global $wpdb;
		$h = self::mot( $id );
		if ( ! $h ) { return false; }
		$chan = KHTC_Khoa::chan( $h->ngay, 'sửa' );
		if ( $chan ) { return $chan; }
		$wpdb->update(
			KHTC_DB::bang( 'ho_so' ),
			array( 'da_hach_toan' => $bat ? 1 : 0 ),
			array( 'id' => (int) $id ),
			array( '%d' ),
			array( '%d' )
		);
		KHTC_NhatKy::ghi( 'sua', 'ho_so', (int) $id, sprintf( 'Hồ sơ %s: %s', $h->so_ct ? $h->so_ct : $h->ten_file, $bat ? 'đánh dấu ĐÃ hạch toán' : 'bỏ đánh dấu hạch toán' ) );
		return true;
	}

	/** Dán bảng: Loại · Số chứng từ · Ngày · Đối tác · Số tiền · Tên file · Link. */
	public static function dan_hang_loat( $text ) {
		$nhan = array();
		foreach ( self::loai() as $k => $v ) { $nhan[ self::chuan( $v ) ] = $k; }

		$them = 0;
		$loi  = array();
		KHTC_NhatKy::mo_lo();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d, ',', '"', '' );
			$o = array_map( 'trim', $o );
			if ( isset( $o[0] ) && 'Loại' === $o[0] ) { continue; }
			if ( count( $o ) < 3 ) {
				$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần ít nhất Loại, Số chứng từ, Ngày.';
				continue;
			}
			$kq = self::them(
				array(
					'loai'      => $nhan[ self::chuan( $o[0] ) ] ?? 'khac',
					'so_ct'     => $o[1],
					'ngay'      => $o[2],
					'doi_tac'   => $o[3] ?? '',
					'so_tien'   => $o[4] ?? 0,
					'ten_file'  => $o[5] ?? '',
					'link_file' => $o[6] ?? '',
				)
			);
			if ( is_wp_error( $kq ) ) { $loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message(); } else { $them++; }
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi( 'nap', 'ho_so', 0, sprintf( 'Nạp %d hồ sơ', $them ), null, true );
		return array( 'them' => $them, 'loi' => $loi );
	}

	public static function chuan( $s ) {
		return mb_strtolower( trim( preg_replace( '/\s+/', ' ', (string) $s ) ), 'UTF-8' );
	}

	// ------------------------------------------------------------- dò nối

	/**
	 * Nối hồ sơ với bút toán trong sổ, theo SỐ CHỨNG TỪ.
	 *
	 * Chỉ nối khi số chứng từ khớp và trong sổ chỉ có ĐÚNG MỘT bút toán mang
	 * số đó. Hai bút toán cùng số thì bỏ qua — nối nhầm còn tệ hơn không nối,
	 * vì màn hình sẽ báo "đã có chứng từ" cho một bút toán thật ra chưa có.
	 *
	 * Không xét số tiền: hồ sơ hay ghi số tròn còn sổ ghi số lẻ sau chiết
	 * khấu, bắt khớp cả tiền thì gần như không nối được gì. Số chứng từ đã đủ
	 * chặt khi nó duy nhất.
	 */
	public static function do_noi( $chi_hs_id = 0 ) {
		global $wpdb;
		$cty = KHTC_Cty::dang_chon();
		$dk  = $chi_hs_id ? $wpdb->prepare( ' AND id = %d', (int) $chi_hs_id ) : '';
		$hs  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'ho_so' ) . " WHERE cty = %s AND gan_id = 0 AND so_ct <> ''" . $dk,
				$cty
			)
		);
		$noi = self::noi_duoc();
		$so  = 0;
		foreach ( $hs as $h ) {
			if ( ! isset( $noi[ $h->loai ] ) ) { continue; }
			$n    = $noi[ $h->loai ];
			$tim  = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id FROM ' . KHTC_DB::bang( $n['bang'] ) . " WHERE cty = %s AND {$n['cot_so']} = %s LIMIT 2",
					$cty,
					$h->so_ct
				)
			);
			if ( 1 !== count( $tim ) ) { continue; }
			$wpdb->update(
				KHTC_DB::bang( 'ho_so' ),
				array( 'gan_bang' => $n['bang'], 'gan_id' => (int) $tim[0]->id ),
				array( 'id' => (int) $h->id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			$so++;
		}
		if ( $so && ! $chi_hs_id ) {
			KHTC_NhatKy::ghi( 'doi_soat', 'ho_so', 0, sprintf( 'Dò nối hồ sơ với sổ — nối được %d chứng từ', $so ) );
		}
		return $so;
	}

	public static function go_noi( $id ) {
		global $wpdb;
		$h = self::mot( $id );
		if ( ! $h ) { return false; }
		$wpdb->update( KHTC_DB::bang( 'ho_so' ), array( 'gan_bang' => '', 'gan_id' => 0 ), array( 'id' => (int) $id ), array( '%s', '%d' ), array( '%d' ) );
		KHTC_NhatKy::ghi( 'sua', 'ho_so', (int) $id, 'Gỡ liên kết hồ sơ với bút toán' );
		return true;
	}

	// ----------------------------------------------------------------- lọc

	public static function loc( $l = array() ) {
		global $wpdb;
		$b    = KHTC_DB::bang( 'ho_so' );
		$dk   = array( 'h.cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );

		if ( ! empty( $l['tu'] ) )   { $dk[] = 'h.ngay >= %s'; $args[] = $l['tu']; }
		if ( ! empty( $l['den'] ) )  { $dk[] = 'h.ngay <= %s'; $args[] = $l['den']; }
		if ( ! empty( $l['loai'] ) ) { $dk[] = 'h.loai = %s';  $args[] = $l['loai']; }
		if ( isset( $l['hach_toan'] ) && '' !== (string) $l['hach_toan'] ) {
			$dk[]   = 'h.da_hach_toan = %d';
			$args[] = (int) $l['hach_toan'];
		}
		if ( isset( $l['da_noi'] ) && '' !== (string) $l['da_noi'] ) {
			$dk[] = (int) $l['da_noi'] ? 'h.gan_id > 0' : 'h.gan_id = 0';
		}
		if ( ! empty( $l['tim'] ) ) {
			$dk[]   = '( h.so_ct LIKE %s OR h.doi_tac LIKE %s OR h.ten_file LIKE %s OR h.ghi_chu LIKE %s )';
			$t      = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
			array_push( $args, $t, $t, $t, $t );
		}
		$where = 'WHERE ' . implode( ' AND ', $dk );

		$tong = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS so_ct,
					COALESCE(SUM(h.so_tien),0) AS tien,
					COALESCE(SUM(CASE WHEN h.da_hach_toan = 1 THEN 1 ELSE 0 END),0) AS da_ht,
					COALESCE(SUM(CASE WHEN h.gan_id > 0 THEN 1 ELSE 0 END),0) AS da_noi,
					COALESCE(SUM(CASE WHEN h.da_hach_toan = 1 AND h.gan_id = 0 THEN 1 ELSE 0 END),0) AS lech
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
			'rows'     => $rows,
			'so_ct'    => (int) $tong->so_ct,
			'tien'     => (int) $tong->tien,
			'da_ht'    => (int) $tong->da_ht,
			'da_noi'   => (int) $tong->da_noi,
			'lech'     => (int) $tong->lech,
			'trang'    => $trang,
			'so_trang' => max( 1, (int) ceil( $tong->so_ct / self::MOI_TRANG ) ),
		);
	}

	// ------------------------------------------------------ thiếu chứng từ

	/**
	 * Bút toán trong sổ mà chưa có hồ sơ nào gắn vào — câu mà kiểm toán hỏi.
	 *
	 * Chiều ngược của bảng chính: bảng kia hỏi "giấy này đã vào sổ chưa", bảng
	 * này hỏi "số này đã có giấy chưa". Bản gốc không trả lời được vì sổ hồ sơ
	 * của nó không nối với sổ sách nào.
	 */
	public static function thieu_chung_tu( $loai, $tu, $den ) {
		global $wpdb;
		$noi = self::noi_duoc();
		if ( ! isset( $noi[ $loai ] ) || empty( $noi[ $loai ]['nguoc'] ) ) { return array(); }
		$n   = $noi[ $loai ];
		$b   = KHTC_DB::bang( $n['bang'] );
		$hs  = KHTC_DB::bang( 'ho_so' );

		$dk = array( 'x.cty = %s', "x.{$n['cot_ngay']} >= %s", "x.{$n['cot_ngay']} <= %s" );
		// Chi tiền mặt lặt vặt thường không có chứng từ riêng; chỉ soi khoản
		// chuyển khoản, đúng phần mà kiểm toán sẽ lần theo sao kê.
		if ( 'chi_phi' === $loai ) { $dk[] = "x.hinh_thuc = 'chuyen_khoan'"; }

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT x.id, x.{$n['cot_ngay']} AS ngay, x.{$n['cot_so']} AS so, x.{$n['cot_ten']} AS ten, x.{$n['cot_tien']} AS tien
				 FROM $b x
				 LEFT JOIN $hs h ON h.gan_bang = %s AND h.gan_id = x.id
				 WHERE " . implode( ' AND ', $dk ) . " AND h.id IS NULL
				 ORDER BY x.{$n['cot_ngay']}, x.id",
				array_merge( array( $n['bang'] ), array( KHTC_Cty::dang_chon(), $tu, $den ) )
			)
		);
	}

	/** Tên đọc được của bút toán đã gắn, để hiện trên bảng. */
	public static function nhan_gan( $h ) {
		global $wpdb;
		if ( ! $h->gan_id || '' === $h->gan_bang ) { return ''; }
		foreach ( self::noi_duoc() as $n ) {
			if ( $n['bang'] !== $h->gan_bang ) { continue; }
			$r = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT {$n['cot_so']} AS so, {$n['cot_ten']} AS ten, {$n['cot_tien']} AS tien FROM " . KHTC_DB::bang( $n['bang'] ) . ' WHERE id = %d',
					(int) $h->gan_id
				)
			);
			if ( ! $r ) { return 'bút toán đã bị xoá'; }
			return trim( $r->so . ' · ' . $r->ten );
		}
		return '';
	}
}
