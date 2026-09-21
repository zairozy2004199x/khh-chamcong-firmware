<?php
/**
 * Công nợ phải thu và phải trả.
 *
 * MỘT SỔ THANH TOÁN DUY NHẤT. Mọi đồng tiền trả cho một chứng từ — dù gõ tay
 * hay do đối soát tự ghép — đều là một dòng trong bảng thanh_toan. Còn nợ luôn
 * bằng:
 *
 *     tổng chứng từ − tổng các dòng thanh toán của nó
 *
 * Không lưu sẵn "đã trả" ở đâu cả. Lưu sẵn thì có hai nguồn sự thật, và hai
 * nguồn sự thật về việc đã trả hay chưa thì sớm muộn lệch nhau — lúc đó không
 * ai biết bên nào đúng.
 *
 * KHÔNG DÙNG LẠI PHÉP GHÉP CỦA ĐỐI SOÁT CỔNG. Phép kia ghép theo ngày gần
 * nhau, dung sai 3 ngày — đúng cho cổng thanh toán chốt T+2, T+3. Công nợ thì
 * ngược hẳn: khách nhận hoá đơn đầu tháng, trả cuối tháng sau, cách nhau 40
 * ngày là bình thường. Ép dùng phép kia thì gần như không ghép được gì.
 *
 * Nên có doan_ghep() riêng, luật khác hẳn: tiền chỉ trả cho chứng từ đã phát
 * sinh TRƯỚC đó, không quan tâm cách bao lâu, và chỉ ghép khi không còn cách
 * hiểu nào khác.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_CongNo {

	/** Các mốc tuổi nợ, đơn vị ngày. Quen thuộc với mọi kế toán. */
	const MOC = array( 30, 60, 90 );

	/** Hai loại sổ: phải thu dựa trên hoá đơn đầu ra, phải trả dựa trên chi phí. */
	public static function loai() {
		return array(
			'thu' => array(
				'ten'      => 'Phải thu',
				'bang'     => 'hd_ra',
				'cot_tien' => 'co_vat',
				'cot_ten'  => 'khach',
				'nhan_ten' => 'Khách hàng',
				'loai_gd'  => 'thu',
			),
			'tra' => array(
				'ten'      => 'Phải trả',
				'bang'     => 'chi_phi',
				'cot_tien' => 'so_tien',
				'cot_ten'  => 'nha_cung_cap',
				'nhan_ten' => 'Nhà cung cấp',
				'loai_gd'  => 'chi',
			),
		);
	}

	public static function mot_loai( $l ) {
		$ds = self::loai();
		return $ds[ $l ] ?? $ds['thu'];
	}

	// ------------------------------------------------------- dòng thanh toán

	/**
	 * Tổng đã trả cho một chứng từ.
	 *
	 * @param string $bang hd_ra hoặc chi_phi.
	 */
	public static function da_tra( $bang, $chung_tu_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'thanh_toan' ) . ' WHERE bang = %s AND chung_tu_id = %d',
				$bang,
				(int) $chung_tu_id
			)
		);
	}

	/** Chứng từ và tổng tiền của nó, hoặc null nếu không thuộc pháp nhân đang xem. */
	public static function chung_tu( $bang, $id ) {
		global $wpdb;
		$c = null;
		foreach ( self::loai() as $l ) {
			if ( $l['bang'] !== $bang ) { continue; }
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . KHTC_DB::bang( $bang ) . ' WHERE id = %d AND cty = %s',
					(int) $id,
					KHTC_Cty::dang_chon()
				)
			);
			if ( ! $row ) { return null; }
			$cot        = $l['cot_tien'];
			$row->_tong = (int) $row->$cot;
			$row->_ten  = (string) $row->{$l['cot_ten']};
			$c          = $row;
		}
		return $c;
	}

	/**
	 * Ghi một lần trả tiền.
	 *
	 * Không cho trả quá số còn nợ. Trả thừa không phải là chuyện không xảy ra,
	 * nhưng nó là một khoản khác (trả thừa, đặt cọc, ghi nhầm) chứ không phải
	 * thanh toán của chứng từ này — cho phép âm thầm thì bảng công nợ ra số
	 * dương giả và không ai dò ra từ đâu.
	 *
	 * @param bool $tu_dong Dòng do đối soát tự tạo, để lần chạy sau dọn lại được.
	 */
	public static function ghi( $bang, $chung_tu_id, $ngay, $so_tien, $giao_dich_id = 0, $ghi_chu = '', $tu_dong = false ) {
		global $wpdb;
		$ct = self::chung_tu( $bang, $chung_tu_id );
		if ( ! $ct ) {
			return new WP_Error( 'chung_tu', 'Không tìm thấy chứng từ.' );
		}
		$ngay = KHTC_GiaoDich::doc_ngay( $ngay );
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Ngày thanh toán không đọc được.' );
		}
		$chan = KHTC_Khoa::chan( $ngay, 'ghi thanh toán' );
		if ( $chan ) { return $chan; }

		$so_tien = abs( (int) KHTC_GiaoDich::doc_so( $so_tien ) );
		if ( $so_tien <= 0 ) {
			return new WP_Error( 'tien', 'Số tiền thanh toán phải lớn hơn 0.' );
		}
		$con = $ct->_tong - self::da_tra( $bang, $chung_tu_id );
		if ( $so_tien > $con ) {
			return new WP_Error(
				'qua',
				'Chứng từ này chỉ còn nợ ' . number_format( $con, 0, ',', '.' ) . ' đ, không ghi được ' . number_format( $so_tien, 0, ',', '.' ) . ' đ.'
			);
		}

		$wpdb->insert(
			KHTC_DB::bang( 'thanh_toan' ),
			array(
				'cty'          => KHTC_Cty::dang_chon(),
				'bang'         => $bang,
				'chung_tu_id'  => (int) $chung_tu_id,
				'giao_dich_id' => (int) $giao_dich_id,
				'ngay'         => $ngay,
				'so_tien'      => $so_tien,
				'ghi_chu'      => (string) $ghi_chu,
				'tu_dong'      => $tu_dong ? 1 : 0,
				'tao_luc'      => current_time( 'mysql' ),
				'tao_boi'      => wp_get_current_user()->display_name,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi(
			'them',
			'thanh_toan',
			$moi,
			sprintf( 'Thanh toán %s đ ngày %s cho %s', number_format( $so_tien, 0, ',', '.' ), mysql2date( 'd/m/Y', $ngay ), self::nhan_chung_tu( $bang, $ct ) )
		);
		return $moi;
	}

	public static function xoa( $id ) {
		global $wpdb;
		$t = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'thanh_toan' ) . ' WHERE id = %d AND cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
		if ( ! $t ) { return false; }
		$chan = KHTC_Khoa::chan( $t->ngay, 'xoá' );
		if ( $chan ) { return $chan; }
		KHTC_NhatKy::ghi_xoa( 'thanh_toan', $id, sprintf( 'Xoá thanh toán %s đ ngày %s', number_format( $t->so_tien, 0, ',', '.' ), mysql2date( 'd/m/Y', $t->ngay ) ) );
		$wpdb->delete( KHTC_DB::bang( 'thanh_toan' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	public static function nhan_chung_tu( $bang, $ct ) {
		if ( 'hd_ra' === $bang ) {
			return 'hoá đơn ' . $ct->so_hd . ( $ct->khach ? ' (' . $ct->khach . ')' : '' );
		}
		return 'khoản chi ' . $ct->khoan_muc . ( $ct->nha_cung_cap ? ' (' . $ct->nha_cung_cap . ')' : '' );
	}

	public static function ds_thanh_toan( $bang, $chung_tu_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'thanh_toan' ) . ' WHERE bang = %s AND chung_tu_id = %d ORDER BY ngay, id',
				$bang,
				(int) $chung_tu_id
			)
		);
	}

	// --------------------------------------------------------- bảng công nợ

	/** Mốc tuổi nợ của một số ngày: 0 = trong hạn đầu tiên, cuối cùng = quá hạn nhất. */
	public static function o_moc( $so_ngay ) {
		foreach ( self::MOC as $i => $m ) {
			if ( $so_ngay <= $m ) { return $i; }
		}
		return count( self::MOC );
	}

	public static function ten_moc() {
		$ra  = array();
		$tru = 0;
		foreach ( self::MOC as $m ) {
			$ra[] = ( $tru + 1 ) . '–' . $m . ' ngày';
			$tru  = $m;
		}
		$ra[] = 'trên ' . $tru . ' ngày';
		return $ra;
	}

	/**
	 * Các chứng từ còn nợ, kèm tuổi nợ.
	 *
	 * Tuổi nợ tính từ HẠN THANH TOÁN nếu có ghi, không có thì tính từ ngày
	 * chứng từ. Hai cách cho ra bảng rất khác nhau: nhà cung cấp cho nợ 30 ngày
	 * mà tính từ ngày hoá đơn thì hôm nộp hàng đã thành "quá hạn 1 ngày".
	 *
	 * @param string $loai thu|tra
	 * @param string $den  Tính tuổi nợ đến ngày này, mặc định hôm nay.
	 */
	public static function con_no( $loai, $den = '', $doi_tac = '' ) {
		global $wpdb;
		$c   = self::mot_loai( $loai );
		$b   = KHTC_DB::bang( $c['bang'] );
		$tt  = KHTC_DB::bang( 'thanh_toan' );
		$den = $den ? $den : current_time( 'Y-m-d' );

		$dk   = array( 'c.cty = %s' );
		$args = array( KHTC_Cty::dang_chon() );
		if ( '' !== $doi_tac ) {
			$dk[]   = 'c.' . $c['cot_ten'] . ' = %s';
			$args[] = $doi_tac;
		}
		// Chi tiền mặt trả ngay tại chỗ thì không phải công nợ với ai.
		if ( 'tra' === $loai ) {
			$dk[] = "c.hinh_thuc = 'chuyen_khoan'";
		}
		$where = 'WHERE ' . implode( ' AND ', $dk );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, COALESCE(t.da,0) AS da_tra
				 FROM $b c
				 LEFT JOIN ( SELECT chung_tu_id, SUM(so_tien) AS da FROM $tt WHERE bang = %s GROUP BY chung_tu_id ) t
					ON t.chung_tu_id = c.id
				 $where ORDER BY c.ngay, c.id",
				array_merge( array( $c['bang'] ), $args )
			)
		);

		$ra = array();
		foreach ( $rows as $r ) {
			$cot = $c['cot_tien'];
			$con = (int) $r->$cot - (int) $r->da_tra;
			if ( $con <= 0 ) { continue; }
			$han       = ! empty( $r->han_tt ) ? $r->han_tt : $r->ngay;
			$r->_tong  = (int) $r->$cot;
			$r->_con   = $con;
			$r->_ten   = (string) $r->{$c['cot_ten']};
			$r->_han   = $han;
			$r->_tuoi  = ( $den > $han ) ? KHTC_DoiSoat::cach_ngay( $den, $han ) : 0;
			$r->_moc   = self::o_moc( $r->_tuoi );
			$ra[]      = $r;
		}
		return $ra;
	}

	/** Gom theo đối tác, mỗi đối tác một hàng, tiền chia vào các mốc tuổi nợ. */
	public static function theo_doi_tac( $loai, $den = '' ) {
		$so_moc = count( self::MOC ) + 1;
		$gom    = array();
		foreach ( self::con_no( $loai, $den ) as $r ) {
			$ten = '' === trim( $r->_ten ) ? '(không ghi tên)' : $r->_ten;
			if ( ! isset( $gom[ $ten ] ) ) {
				$gom[ $ten ] = array( 'ten' => $ten, 'so_ct' => 0, 'tong' => 0, 'moc' => array_fill( 0, $so_moc, 0 ) );
			}
			$gom[ $ten ]['so_ct']++;
			$gom[ $ten ]['tong']              += $r->_con;
			$gom[ $ten ]['moc'][ $r->_moc ]   += $r->_con;
		}
		// Nợ nhiều xếp trên: mở bảng ra là thấy ngay phải gọi ai trước.
		uasort( $gom, function ( $a, $b ) { return $b['tong'] <=> $a['tong']; } );
		return array_values( $gom );
	}

	public static function tong( $loai, $den = '' ) {
		$so_moc = count( self::MOC ) + 1;
		$ra     = array( 'tong' => 0, 'so_ct' => 0, 'qua_han' => 0, 'moc' => array_fill( 0, $so_moc, 0 ) );
		foreach ( self::con_no( $loai, $den ) as $r ) {
			$ra['tong'] += $r->_con;
			$ra['so_ct']++;
			$ra['moc'][ $r->_moc ] += $r->_con;
			if ( $r->_tuoi > 0 ) { $ra['qua_han'] += $r->_con; }
		}
		return $ra;
	}

	/** Bỏ dấu cách và ký tự lạ, viết hoa — để dò số chứng từ trong diễn giải sao kê. */
	public static function chuan( $s ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $s ) );
	}

	/**
	 * Đoán dòng sao kê nào trả cho chứng từ nào. Hàm thuần, kiểm được.
	 *
	 * HAI LUẬT, và luật nào cũng chỉ ghép khi KHÔNG CÒN CÁCH HIỂU NÀO KHÁC:
	 *
	 *   1. Số chứng từ xuất hiện trong mã giao dịch hoặc diễn giải của dòng sao
	 *      kê, và số tiền đúng bằng phần còn nợ.
	 *   2. Số tiền đúng bằng phần còn nợ, và trong toàn bộ danh sách chỉ có
	 *      ĐÚNG MỘT chứng từ mang số tiền đó.
	 *
	 * Luật 2 bắt buộc phải duy nhất. Hai hoá đơn cùng còn nợ 5.400.000 mà đoán
	 * bừa thì đóng nhầm cái này, để hở cái kia — kế toán đi đòi nhầm người, và
	 * không có gì trên màn hình cho thấy đã đoán sai.
	 *
	 * Tiền chỉ trả cho chứng từ phát sinh TRƯỚC hoặc CÙNG ngày. Không có giới
	 * hạn "cách bao nhiêu ngày": khách trả sau ba tháng vẫn là trả cho hoá đơn
	 * đó. Đây là chỗ khác hẳn đối soát cổng.
	 *
	 * @param array $ct Chứng từ: cần id, ngay, so_ct, con (số còn nợ).
	 * @param array $gd Dòng sao kê: cần id, ngay, ma_gd, so_tien, dien_giai.
	 * @return array [chung_tu_id => giao_dich_id]
	 */
	public static function doan_ghep( $ct, $gd ) {
		// Số tiền nào chỉ ứng với đúng một chứng từ — dùng cho luật 2.
		$dem = array();
		foreach ( $ct as $r ) {
			$dem[ (int) $r->con ] = ( $dem[ (int) $r->con ] ?? 0 ) + 1;
		}

		// Chứng từ cũ nhất xử lý trước: nếp quen của kế toán là tiền về thì cấn
		// vào khoản nợ lâu nhất.
		$ds = $ct;
		usort( $ds, function ( $a, $b ) { return array( $a->ngay, (int) $a->id ) <=> array( $b->ngay, (int) $b->id ); } );

		$ghep = array();
		$dung = array();

		foreach ( array( 1, 2 ) as $luat ) {
			foreach ( $ds as $r ) {
				if ( isset( $ghep[ (int) $r->id ] ) ) { continue; }
				$so_ct = self::chuan( $r->so_ct );
				if ( 1 === $luat && '' === $so_ct ) { continue; }
				if ( 2 === $luat && 1 !== ( $dem[ (int) $r->con ] ?? 0 ) ) { continue; }

				foreach ( $gd as $g ) {
					$gid = (int) $g->id;
					if ( isset( $dung[ $gid ] ) ) { continue; }
					if ( (int) $g->so_tien !== (int) $r->con ) { continue; }
					// Không thể trả cho một chứng từ chưa phát sinh.
					if ( $g->ngay < $r->ngay ) { continue; }
					if ( 1 === $luat ) {
						$kho = self::chuan( $g->ma_gd ) . ' ' . self::chuan( $g->dien_giai );
						if ( false === strpos( $kho, $so_ct ) ) { continue; }
					}
					$ghep[ (int) $r->id ] = $gid;
					$dung[ $gid ]         = true;
					break;
				}
			}
		}
		return $ghep;
	}

	// ------------------------------------------------------------- tự ghép

	/**
	 * Đoán các lần trả trọn vẹn bằng phép ghép 1-1 của đối soát, rồi đổ ra
	 * thành dòng thanh toán.
	 *
	 * Chỉ bắt được lần trả ĐÚNG BẰNG số còn nợ của đúng một chứng từ. Khách trả
	 * gộp ba hoá đơn hay trả làm hai đợt thì phải gõ tay — và đó là đúng: đoán
	 * sai một khoản trả gộp còn tệ hơn không đoán, vì nó đóng nhầm một hoá đơn
	 * và để hở một hoá đơn khác.
	 *
	 * Xoá và tạo lại các dòng TỰ ĐỘNG trong kỳ, giữ nguyên dòng gõ tay.
	 */
	public static function tu_ghep( $loai, $tu, $den, $ngan_hang_id = 0 ) {
		global $wpdb;
		$c   = self::mot_loai( $loai );
		$cty = KHTC_Cty::dang_chon();
		$tt  = KHTC_DB::bang( 'thanh_toan' );

		$chan = KHTC_Khoa::chan( $den, 'ghép thanh toán' );
		if ( $chan ) { return $chan; }

		// Dọn dòng tự động cũ trước khi đoán lại, nếu không chạy hai lần là
		// ghi đôi và chứng từ thành "trả thừa".
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $tt WHERE cty = %s AND bang = %s AND tu_dong = 1 AND ngay >= %s AND ngay <= %s",
				$cty,
				$c['bang'],
				$tu,
				$den
			)
		);

		$no = array();
		foreach ( self::con_no( $loai, $den ) as $r ) {
			if ( $r->ngay > $den ) { continue; }
			$no[ (int) $r->id ] = $r;
		}
		if ( ! $no ) {
			return array( 'ghep' => 0, 'tien' => 0 );
		}

		$dk   = array( 'g.cty = %s', 'g.loai = %s', 'g.ngay >= %s', 'g.ngay <= %s' );
		$args = array( $cty, $c['loai_gd'], $tu, $den );
		if ( $ngan_hang_id ) { $dk[] = 'g.ngan_hang_id = %d'; $args[] = (int) $ngan_hang_id; }
		$gd = $wpdb->get_results(
			$wpdb->prepare( 'SELECT g.* FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' g WHERE ' . implode( ' AND ', $dk ) . ' ORDER BY g.ngay, g.id', $args )
		);

		// Đưa vào phép ghép bằng SỐ CÒN NỢ, không phải tổng chứng từ: một hoá
		// đơn đã trả một nửa thì lần chuyển khoản tiếp theo khớp với phần còn
		// lại, không khớp với tổng.
		$dong = array();
		foreach ( $no as $r ) {
			$dong[] = (object) array(
				'id'      => (int) $r->id,
				'ngay'    => $r->ngay,
				'so_ct'   => (string) ( 'hd_ra' === $c['bang'] ? $r->so_hd : $r->so_ct ),
				'con'     => (int) $r->_con,
			);
		}
		$ghep = self::doan_ghep( $dong, $gd );

		$da_dung = array();
		foreach ( $gd as $g ) { $da_dung[ (int) $g->id ] = $g; }

		$so   = 0;
		$tien = 0;
		KHTC_NhatKy::mo_lo();
		foreach ( $ghep as $ct_id => $gd_id ) {
			$g  = $da_dung[ $gd_id ];
			$r  = $no[ $ct_id ];
			$kt = self::ghi( $c['bang'], $ct_id, $g->ngay, $r->_con, (int) $g->id, 'Tự ghép từ sao kê: ' . $g->dien_giai, true );
			if ( is_wp_error( $kt ) ) { continue; }
			$so++;
			$tien += (int) $r->_con;
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi(
			'doi_soat',
			'thanh_toan',
			0,
			sprintf( '%s — tự ghép %d chứng từ, %s đ (%s → %s)', $c['ten'], $so, number_format( $tien, 0, ',', '.' ), mysql2date( 'd/m/Y', $tu ), mysql2date( 'd/m/Y', $den ) ),
			null,
			true
		);
		return array( 'ghep' => $so, 'tien' => $tien );
	}
}
