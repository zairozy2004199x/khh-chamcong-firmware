<?php
/**
 * Pháp danh — sổ hợp đồng.
 *
 * Dựng lại từ bản gốc (routes/phap-danh.js, 2.287 dòng) với ba phần: hợp đồng
 * thuê gian hàng, hợp đồng nhà cung cấp, và doanh thu chia sẻ.
 *
 * MỘT BẢNG CHO CẢ HAI LOẠI HỢP ĐỒNG. Thuê gian hàng và mua của nhà cung cấp
 * khác nhau ở vài cột (gian, tiền thuê tháng, tỷ lệ chia sẻ so với hàng hoá
 * mua, giá trị hợp đồng), nhưng giống nhau ở phần cốt lõi: đối tác, mã số
 * thuế, số hợp đồng, ngày ký, ngày hết hạn, bản scan. Hai bảng gần giống hệt
 * thì mọi việc dùng chung — cảnh báo sắp hết hạn, đếm hợp đồng thiếu dấu, tìm
 * theo đối tác — đều phải viết hai lần và sớm muộn lệch nhau.
 *
 * KHÁC BẢN GỐC MỘT CHỖ QUAN TRỌNG: bản gốc lưu thời hạn hợp đồng thành một ô
 * CHỮ TỰ DO ("thoiHanHopDong"), nên không máy nào biết hợp đồng nào sắp hết.
 * Ở đây ngày bắt đầu và ngày hết hạn là cột NGÀY thật, và có hẳn một bảng
 * "sắp hết hạn" — việc mà sổ hợp đồng sinh ra để làm.
 *
 * KHÔNG dựng lại phần đồng bộ Google Sheet của bản gốc: nó phụ thuộc vào một
 * bảng tính ngoài mà plugin không với tới, và một đường nạp dữ liệu im lặng từ
 * nơi khác là thứ khó dò nhất khi số sai. Thay bằng ô dán bảng như mọi màn
 * hình khác.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_PhapDanh {

	const MOI_TRANG = 100;

	/** Bao nhiêu ngày nữa thì coi là "sắp hết hạn". Đủ để kịp thương lượng lại. */
	const SAP_HET = 60;

	public static function loai() {
		return array(
			'thue' => array(
				'ten'      => 'Thuê gian hàng',
				'doi_tac'  => 'Bên cho thuê',
				'gia_tri'  => 'Tiền thuê / tháng',
			),
			'ncc'  => array(
				'ten'      => 'Nhà cung cấp',
				'doi_tac'  => 'Nhà cung cấp',
				'gia_tri'  => 'Giá trị hợp đồng',
			),
		);
	}

	public static function mot_loai( $l ) {
		$ds = self::loai();
		return $ds[ $l ] ?? $ds['thue'];
	}

	public static function chia_se() {
		return array(
			''             => '— Không chia sẻ —',
			'giu_tien'     => 'Giữ tiền — mình thu, trả lại phần của họ',
			'xuat_hoa_don' => 'Xuất hoá đơn — họ xuất hoá đơn cho mình',
		);
	}

	public static function trang_thai() {
		return array( 'hoat_dong' => 'Đang hoạt động', 'tam_ngung' => 'Tạm ngưng', 'da_dong' => 'Đã đóng' );
	}

	// ------------------------------------------------------------------ ghi

	public static function them( $d ) {
		global $wpdb;
		$loai    = isset( self::loai()[ $d['loai'] ?? '' ] ) ? $d['loai'] : 'thue';
		$doi_tac = trim( (string) ( $d['doi_tac'] ?? '' ) );
		$gian    = trim( (string) ( $d['gian'] ?? '' ) );

		if ( 'thue' === $loai && '' === $gian ) {
			return new WP_Error( 'gian', 'Chưa nhập Gian / mặt bằng.' );
		}
		if ( '' === $doi_tac ) {
			return new WP_Error( 'doi_tac', 'Chưa nhập ' . mb_strtolower( self::mot_loai( $loai )['doi_tac'] ) . '.' );
		}

		$bd  = KHTC_GiaoDich::doc_ngay( $d['ngay_bat_dau'] ?? '' );
		$hh  = KHTC_GiaoDich::doc_ngay( $d['ngay_het_han'] ?? '' );
		if ( '' !== $bd && '' !== $hh && $bd > $hh ) {
			return new WP_Error( 'ngay', 'Ngày bắt đầu đang sau ngày hết hạn.' );
		}

		// Lấy ra biến trước rồi mới kiểm: '' LÀ một khoá hợp lệ của chia_se()
		// ("không chia sẻ"), nên isset( chia_se()[ $d['x'] ?? '' ] ) qua được
		// kể cả khi $d['x'] không tồn tại — rồi dòng sau đọc khoá đó và cảnh
		// báo. Kiểu lỗi chỉ hiện dưới E_ALL, âm thầm trên bản chạy thật.
		$chia = (string) ( $d['loai_chia_se'] ?? '' );
		if ( ! isset( self::chia_se()[ $chia ] ) ) { $chia = ''; }

		$pt = (float) str_replace( ',', '.', (string) ( $d['phan_tram'] ?? 0 ) );
		if ( $pt < 0 || $pt > 100 ) {
			return new WP_Error( 'phan_tram', 'Tỷ lệ chia sẻ phải nằm trong khoảng 0–100%.' );
		}
		// Có tỷ lệ mà chưa chọn hình thức là một trạng thái mâu thuẫn: hợp đồng
		// mang 15% nhưng không bao giờ xuất hiện trong bảng chia sẻ, và không có
		// gì trên màn hình cho thấy vì sao. Mặc định về "giữ tiền", đúng như ô
		// dán bảng vẫn làm — hai đường vào phải cư xử giống nhau.
		if ( $pt > 0 && '' === $chia ) { $chia = 'giu_tien'; }

		$wpdb->insert(
			KHTC_DB::bang( 'hop_dong' ),
			array(
				'cty'           => KHTC_Cty::dang_chon(),
				'loai'          => $loai,
				'doi_tac'       => $doi_tac,
				'mst'           => trim( (string) ( $d['mst'] ?? '' ) ),
				'dai_dien'      => trim( (string) ( $d['dai_dien'] ?? '' ) ),
				'chuc_vu'       => trim( (string) ( $d['chuc_vu'] ?? '' ) ),
				'so_hd'         => trim( (string) ( $d['so_hd'] ?? '' ) ),
				'ngay_ky'       => KHTC_GiaoDich::doc_ngay( $d['ngay_ky'] ?? '' ) ?: null,
				'ngay_bat_dau'  => $bd ?: null,
				'ngay_het_han'  => $hh ?: null,
				'gia_tri'       => abs( (int) KHTC_GiaoDich::doc_so( $d['gia_tri'] ?? 0 ) ),
				'noi_dung'      => (string) ( $d['noi_dung'] ?? '' ),
				'gian'          => $gian,
				'ma_diem'       => trim( (string) ( $d['ma_diem'] ?? '' ) ),
				'ma_misa'       => trim( (string) ( $d['ma_misa'] ?? '' ) ),
				'khu_vuc'       => trim( (string) ( $d['khu_vuc'] ?? '' ) ),
				'hinh_thuc'     => trim( (string) ( $d['hinh_thuc'] ?? '' ) ),
				'trang_thai'    => isset( self::trang_thai()[ $d['trang_thai'] ?? '' ] ) ? $d['trang_thai'] : 'hoat_dong',
				'loai_chia_se'  => $chia,
				'phan_tram'     => $pt,
				'mien'          => ! empty( $d['mien'] ) ? 1 : 0,
				'link_chua_dau' => esc_url_raw( (string) ( $d['link_chua_dau'] ?? '' ) ),
				'link_du_dau'   => esc_url_raw( (string) ( $d['link_du_dau'] ?? '' ) ),
				'ghi_chu'       => (string) ( $d['ghi_chu'] ?? '' ),
				'tao_luc'       => current_time( 'mysql' ),
				'tao_boi'       => wp_get_current_user()->display_name,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$moi = (int) $wpdb->insert_id;
		KHTC_NhatKy::ghi(
			'them',
			'hop_dong',
			$moi,
			sprintf( 'Hợp đồng %s — %s%s', self::mot_loai( $loai )['ten'], $doi_tac, $gian ? ' · ' . $gian : '' )
		);
		return $moi;
	}

	public static function xoa( $id ) {
		global $wpdb;
		$h = self::mot( $id );
		if ( ! $h ) { return false; }
		KHTC_NhatKy::ghi_xoa( 'hop_dong', $id, sprintf( 'Xoá hợp đồng %s%s', $h->doi_tac, $h->gian ? ' · ' . $h->gian : '' ) );
		$wpdb->delete( KHTC_DB::bang( 'hop_dong' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	public static function mot( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'hop_dong' ) . ' WHERE id = %d AND cty = %s',
				(int) $id,
				KHTC_Cty::dang_chon()
			)
		);
	}

	public static function doi_trang_thai( $id, $tt ) {
		global $wpdb;
		$h = self::mot( $id );
		if ( ! $h || ! isset( self::trang_thai()[ $tt ] ) ) { return false; }
		$wpdb->update( KHTC_DB::bang( 'hop_dong' ), array( 'trang_thai' => $tt ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
		KHTC_NhatKy::ghi( 'sua', 'hop_dong', (int) $id, sprintf( 'Hợp đồng %s → %s', $h->doi_tac, self::trang_thai()[ $tt ] ) );
		return true;
	}

	/**
	 * Dán bảng hợp đồng thuê: Gian · Bên cho thuê · MST · Khu vực · Mã điểm ·
	 * Hình thức · Tiền thuê tháng · Bắt đầu · Hết hạn · % chia sẻ.
	 * Dán bảng NCC: Nhà cung cấp · MST · Số HĐ · Nội dung · Giá trị · Ngày ký ·
	 * Hết hạn.
	 */
	public static function dan_hang_loat( $loai, $text ) {
		$them = 0;
		$loi  = array();
		KHTC_NhatKy::mo_lo();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $i => $d ) {
			if ( '' === trim( $d ) ) { continue; }
			$o = ( strpos( $d, "\t" ) !== false ) ? explode( "\t", $d ) : str_getcsv( $d, ',', '"', '' );
			$o = array_map( 'trim', $o );
			if ( isset( $o[0] ) && in_array( $o[0], array( 'Gian', 'Nhà cung cấp', 'Tên NCC' ), true ) ) { continue; }

			if ( 'thue' === $loai ) {
				if ( count( $o ) < 2 ) {
					$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần ít nhất Gian và Bên cho thuê.';
					continue;
				}
				$kq = self::them(
					array(
						'loai'         => 'thue',
						'gian'         => $o[0],
						'doi_tac'      => $o[1],
						'mst'          => $o[2] ?? '',
						'khu_vuc'      => $o[3] ?? '',
						'ma_diem'      => $o[4] ?? '',
						'hinh_thuc'    => $o[5] ?? '',
						'gia_tri'      => $o[6] ?? 0,
						'ngay_bat_dau' => $o[7] ?? '',
						'ngay_het_han' => $o[8] ?? '',
						'phan_tram'    => $o[9] ?? 0,
					)
				);
			} else {
				if ( count( $o ) < 2 ) {
					$loi[] = 'Dòng ' . ( $i + 1 ) . ': cần ít nhất Nhà cung cấp và MST hoặc Số HĐ.';
					continue;
				}
				$kq = self::them(
					array(
						'loai'         => 'ncc',
						'doi_tac'      => $o[0],
						'mst'          => $o[1] ?? '',
						'so_hd'        => $o[2] ?? '',
						'noi_dung'     => $o[3] ?? '',
						'gia_tri'      => $o[4] ?? 0,
						'ngay_ky'      => $o[5] ?? '',
						'ngay_het_han' => $o[6] ?? '',
					)
				);
			}
			if ( is_wp_error( $kq ) ) { $loi[] = 'Dòng ' . ( $i + 1 ) . ': ' . $kq->get_error_message(); } else { $them++; }
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi( 'nap', 'hop_dong', 0, sprintf( 'Nạp %d hợp đồng %s', $them, self::mot_loai( $loai )['ten'] ) );
		return array( 'them' => $them, 'loi' => $loi );
	}

	// ----------------------------------------------------------------- lọc

	public static function loc( $l = array() ) {
		global $wpdb;
		$b    = KHTC_DB::bang( 'hop_dong' );
		$dk   = array( 'h.cty = %s', 'h.loai = %s' );
		$args = array( KHTC_Cty::dang_chon(), isset( self::loai()[ $l['loai'] ?? '' ] ) ? $l['loai'] : 'thue' );

		if ( ! empty( $l['trang_thai'] ) ) { $dk[] = 'h.trang_thai = %s'; $args[] = $l['trang_thai']; }
		if ( ! empty( $l['khu_vuc'] ) )    { $dk[] = 'h.khu_vuc = %s';    $args[] = $l['khu_vuc']; }
		if ( ! empty( $l['hinh_thuc'] ) )  { $dk[] = 'h.hinh_thuc = %s';  $args[] = $l['hinh_thuc']; }
		if ( ! empty( $l['tim'] ) ) {
			$dk[]   = '( h.doi_tac LIKE %s OR h.gian LIKE %s OR h.mst LIKE %s OR h.so_hd LIKE %s OR h.ma_diem LIKE %s )';
			$t      = '%' . $wpdb->esc_like( $l['tim'] ) . '%';
			array_push( $args, $t, $t, $t, $t, $t );
		}
		$where = 'WHERE ' . implode( ' AND ', $dk );

		$tong = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS so_hd,
					COALESCE(SUM(CASE WHEN h.trang_thai = 'hoat_dong' AND h.mien = 0 THEN h.gia_tri ELSE 0 END),0) AS gia_tri,
					COALESCE(SUM(CASE WHEN h.trang_thai = 'hoat_dong' THEN 1 ELSE 0 END),0) AS dang_chay,
					COALESCE(SUM(CASE WHEN h.link_du_dau = '' THEN 1 ELSE 0 END),0) AS thieu_dau
				 FROM $b h $where",
				$args
			)
		);

		$trang = max( 1, (int) ( $l['trang'] ?? 1 ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.* FROM $b h $where ORDER BY h.trang_thai, h.doi_tac, h.gian, h.id LIMIT %d OFFSET %d",
				array_merge( $args, array( self::MOI_TRANG, ( $trang - 1 ) * self::MOI_TRANG ) )
			)
		);

		return array(
			'rows'      => $rows,
			'so_hd'     => (int) $tong->so_hd,
			'gia_tri'   => (int) $tong->gia_tri,
			'dang_chay' => (int) $tong->dang_chay,
			'thieu_dau' => (int) $tong->thieu_dau,
			'trang'     => $trang,
			'so_trang'  => max( 1, (int) ceil( $tong->so_hd / self::MOI_TRANG ) ),
		);
	}

	public static function gia_tri_co( $cot, $loai = 'thue' ) {
		global $wpdb;
		if ( ! in_array( $cot, array( 'khu_vuc', 'hinh_thuc' ), true ) ) { return array(); }
		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT ' . $cot . ' FROM ' . KHTC_DB::bang( 'hop_dong' ) . " WHERE cty = %s AND loai = %s AND $cot <> '' ORDER BY $cot",
				KHTC_Cty::dang_chon(),
				$loai
			)
		);
	}

	// ------------------------------------------------------- sắp hết hạn

	/**
	 * Hợp đồng đang chạy mà sắp hết hạn, hoặc đã hết hạn mà chưa đóng.
	 *
	 * Đây là lý do tồn tại của cả sổ hợp đồng. Bản gốc lưu thời hạn thành chữ
	 * tự do nên không trả lời được câu này — phải mở từng dòng ra đọc.
	 *
	 * @param string $den Mốc tính, mặc định hôm nay. Nhận từ ngoài để báo cáo
	 *                    kỳ cũ vẫn ra đúng số của kỳ đó.
	 */
	public static function sap_het_han( $loai = 'thue', $den = '', $so_ngay = self::SAP_HET ) {
		global $wpdb;
		$den  = $den ? $den : current_time( 'Y-m-d' );
		$moc  = gmdate( 'Y-m-d', strtotime( $den . ' 00:00:00 UTC' ) + $so_ngay * DAY_IN_SECONDS );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'hop_dong' ) . "
				 WHERE cty = %s AND loai = %s AND trang_thai = 'hoat_dong'
				   AND ngay_het_han IS NOT NULL AND ngay_het_han <> '' AND ngay_het_han <= %s
				 ORDER BY ngay_het_han",
				KHTC_Cty::dang_chon(),
				$loai,
				$moc
			)
		);
		foreach ( $rows as $r ) {
			// Số âm nghĩa là đã quá hạn — giữ dấu để màn hình phân biệt được
			// "còn 12 ngày" với "quá hạn 12 ngày".
			$r->_con_ngay = (int) round( ( strtotime( $r->ngay_het_han . ' 00:00:00 UTC' ) - strtotime( $den . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
		}
		return $rows;
	}

	// --------------------------------------------------- doanh thu chia sẻ

	/**
	 * Phần phải chia cho bên cho thuê trong một kỳ.
	 *
	 * Ghép hợp đồng với hoá đơn đầu ra qua MÃ ĐIỂM NỘI BỘ — cột mà cả hai bảng
	 * đều có sẵn. Không tự bịa ra đường nối nào khác: hợp đồng nào chưa điền mã
	 * điểm thì hiện thẳng là "chưa gắn mã điểm" chứ không đoán theo tên gian,
	 * vì đoán sai ở đây là trả nhầm tiền cho người khác.
	 *
	 * Hợp đồng đánh dấu MIỄN thì doanh thu vẫn hiện nhưng phần chia bằng 0.
	 */
	public static function doanh_thu_chia_se( $tu, $den ) {
		global $wpdb;
		$hd = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . KHTC_DB::bang( 'hop_dong' ) . "
				 WHERE cty = %s AND loai = 'thue' AND loai_chia_se <> '' ORDER BY doi_tac, gian",
				KHTC_Cty::dang_chon()
			)
		);
		if ( ! $hd ) { return array( 'rows' => array(), 'tong_dt' => 0, 'tong_chia' => 0, 'chua_gan' => 0 ); }

		$dt_theo_diem = array();
		$o = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ma_diem, COALESCE(SUM(chua_vat),0) AS dt, COUNT(*) AS so_hd
				 FROM ' . KHTC_DB::bang( 'hd_ra' ) . " WHERE cty = %s AND ngay >= %s AND ngay <= %s AND ma_diem <> ''
				 GROUP BY ma_diem",
				KHTC_Cty::dang_chon(),
				$tu,
				$den
			)
		);
		foreach ( $o as $r ) { $dt_theo_diem[ (string) $r->ma_diem ] = array( (int) $r->dt, (int) $r->so_hd ); }

		$rows      = array();
		$tong_dt   = 0;
		$tong_chia = 0;
		$chua_gan  = 0;
		foreach ( $hd as $h ) {
			$ma = (string) $h->ma_diem;
			if ( '' === $ma ) { $chua_gan++; }
			list( $dt, $so ) = $dt_theo_diem[ $ma ] ?? array( 0, 0 );
			$chia = ( $h->mien || '' === $ma ) ? 0 : (int) round( $dt * (float) $h->phan_tram / 100 );
			$rows[] = array(
				'hd'      => $h,
				'dt'      => $dt,
				'so_hd'   => $so,
				'chia'    => $chia,
				'giu_lai' => $dt - $chia,
			);
			$tong_dt   += $dt;
			$tong_chia += $chia;
		}
		return array( 'rows' => $rows, 'tong_dt' => $tong_dt, 'tong_chia' => $tong_chia, 'chua_gan' => $chua_gan );
	}

	// ------------------------------------------------------------- tải CSV

	public static function hang_csv( $loai, $rows ) {
		$c  = self::mot_loai( $loai );
		$tt = self::trang_thai();
		if ( 'thue' === $loai ) {
			$ra = array( array( 'STT', 'Gian / mặt bằng', 'Bên cho thuê', 'MST', 'Khu vực', 'Mã điểm', 'Hình thức hợp tác', 'Tiền thuê / tháng', 'Bắt đầu', 'Hết hạn', 'Trạng thái', 'Chia sẻ', '%', 'Miễn', 'Bản đủ dấu', 'Ghi chú' ) );
		} else {
			$ra = array( array( 'STT', 'Nhà cung cấp', 'MST', 'Số HĐ', 'Nội dung', 'Giá trị hợp đồng', 'Ngày ký', 'Hết hạn', 'Trạng thái', 'Đại diện', 'Chức vụ', 'Bản đủ dấu', 'Ghi chú' ) );
		}
		$stt = 0;
		foreach ( $rows as $h ) {
			$stt++;
			if ( 'thue' === $loai ) {
				$ra[] = array(
					$stt, $h->gian, $h->doi_tac, $h->mst, $h->khu_vuc, $h->ma_diem, $h->hinh_thuc,
					$h->gia_tri,
					$h->ngay_bat_dau ? mysql2date( 'd/m/Y', $h->ngay_bat_dau ) : '',
					$h->ngay_het_han ? mysql2date( 'd/m/Y', $h->ngay_het_han ) : '',
					$tt[ $h->trang_thai ] ?? $h->trang_thai,
					self::chia_se()[ $h->loai_chia_se ] ?? '',
					rtrim( rtrim( number_format( (float) $h->phan_tram, 2, '.', '' ), '0' ), '.' ),
					$h->mien ? 'Có' : '',
					$h->link_du_dau ? 'Có' : 'Chưa',
					$h->ghi_chu,
				);
			} else {
				$ra[] = array(
					$stt, $h->doi_tac, $h->mst, $h->so_hd, $h->noi_dung, $h->gia_tri,
					$h->ngay_ky ? mysql2date( 'd/m/Y', $h->ngay_ky ) : '',
					$h->ngay_het_han ? mysql2date( 'd/m/Y', $h->ngay_het_han ) : '',
					$tt[ $h->trang_thai ] ?? $h->trang_thai,
					$h->dai_dien, $h->chuc_vu,
					$h->link_du_dau ? 'Có' : 'Chưa',
					$h->ghi_chu,
				);
			}
		}
		return $ra;
	}

	public static function tai_csv() {
		if ( empty( $_GET['khtc_tai_pd'] ) ) { return; }
		if ( ! is_user_logged_in() || ! current_user_can( KHTC_CAP ) ) { return; }
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'khtc_tai_pd' ) ) {
			return;
		}
		$loai = sanitize_text_field( wp_unslash( $_GET['loai'] ?? 'thue' ) );
		$loai = isset( self::loai()[ $loai ] ) ? $loai : 'thue';
		$kq   = self::loc(
			array(
				'loai'       => $loai,
				'trang_thai' => sanitize_text_field( wp_unslash( $_GET['tt'] ?? '' ) ),
				'khu_vuc'    => sanitize_text_field( wp_unslash( $_GET['kv'] ?? '' ) ),
				'hinh_thuc'  => sanitize_text_field( wp_unslash( $_GET['ht'] ?? '' ) ),
				'tim'        => sanitize_text_field( wp_unslash( $_GET['tim'] ?? '' ) ),
				'trang'      => 1,
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="hop-dong-' . $loai . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		fwrite( $f, "\xEF\xBB\xBF" );
		foreach ( self::hang_csv( $loai, $kq['rows'] ) as $hang ) {
			fputcsv( $f, $hang );
		}
		fclose( $f );
		exit;
	}
}
