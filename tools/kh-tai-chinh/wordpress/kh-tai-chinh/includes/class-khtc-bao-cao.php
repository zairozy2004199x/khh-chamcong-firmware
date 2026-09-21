<?php
/**
 * Báo cáo — gom số từ mọi màn hình khác về một chỗ.
 *
 * CHỈ ĐỌC, KHÔNG CÓ BẢNG RIÊNG. Mọi con số ở đây tính lại từ chứng từ gốc mỗi
 * lần mở. Lưu sẵn kết quả báo cáo thì nhanh hơn, nhưng sửa một hoá đơn tháng
 * trước là báo cáo đã lưu thành sai mà không có gì báo — đúng cái bẫy mà bảng
 * thanh toán ở bản 0.6 đã phải dọn.
 *
 * Kèm theo đó: chạy báo cáo tháng 8 vào tháng 10 phải ra đúng số của tháng 8.
 * Nên không hàm nào ở đây được dùng "hôm nay" làm mốc — mọi thứ nhận ngày từ
 * ngoài vào, kể cả số dư ngân hàng và tuổi nợ.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_BaoCao {

	/** Ngày hôm trước — mốc để lấy số dư đầu kỳ. */
	public static function hom_truoc( $ngay ) {
		return gmdate( 'Y-m-d', strtotime( $ngay . ' 00:00:00 UTC' ) - DAY_IN_SECONDS );
	}

	/** Ngày đầu và cuối của một tháng YYYY-MM. */
	public static function bien_thang( $thang ) {
		$dau = $thang . '-01';
		return array( $dau, gmdate( 'Y-m-t', strtotime( $dau . ' 00:00:00 UTC' ) ) );
	}

	// ---------------------------------------------------------- dòng tiền

	/**
	 * Số dư đầu kỳ, thu, chi, số dư cuối kỳ cho từng tài khoản.
	 *
	 * Số dư cuối kỳ được tính ĐỘC LẬP (từ số dư đầu của tài khoản cộng dồn đến
	 * hết kỳ), không phải lấy đầu kỳ cộng thu trừ chi. Hai đường tính phải ra
	 * cùng một số — và có phép kiểm buộc như vậy. Nếu lệch thì có dòng nào đó
	 * nằm ngoài khoảng ngay_dau mà vẫn bị cộng, tức là số dư đang sai ở đâu đó.
	 */
	public static function dong_tien( $tu, $den ) {
		$truoc = self::hom_truoc( $tu );
		$rows  = array();
		$t     = array( 'dau' => 0, 'thu' => 0, 'chi' => 0, 'cuoi' => 0 );

		foreach ( KHTC_NganHang::ds() as $nh ) {
			$dau  = KHTC_NganHang::so_du_den( $nh, $truoc );
			$cuoi = KHTC_NganHang::so_du_den( $nh, $den );
			$ky   = KHTC_GiaoDich::loc( array( 'ngan_hang_id' => $nh->id, 'tu' => $tu, 'den' => $den ) );

			$r = array(
				'ten'  => $nh->ten,
				'dau'  => $dau,
				'thu'  => (int) $ky['thu'],
				'chi'  => (int) $ky['chi'],
				'cuoi' => $cuoi,
				// Chênh giữa hai đường tính. Khác 0 là có chuyện.
				'lech' => $cuoi - ( $dau + (int) $ky['thu'] - (int) $ky['chi'] ),
			);
			$rows[]    = $r;
			$t['dau']  += $r['dau'];
			$t['thu']  += $r['thu'];
			$t['chi']  += $r['chi'];
			$t['cuoi'] += $r['cuoi'];
		}
		return array( 'rows' => $rows, 'tong' => $t );
	}

	// ------------------------------------------------------------ cả gói

	/** Toàn bộ số của một kỳ. Một lần gọi, một trang báo cáo. */
	public static function ky( $tu, $den ) {
		$ky = array( 'tu' => $tu, 'den' => $den );

		$ra  = KHTC_HoaDonRa::loc( $ky );
		$cp  = KHTC_ChiPhi::loc( $ky );
		$gd  = KHTC_GiaoDich::loc( $ky );

		// Kết quả TẠM TÍNH: doanh thu chưa VAT trừ chi phí đã ghi. Không phải
		// báo cáo kết quả kinh doanh thật — thiếu khấu hao, thiếu phân bổ trước
		// sau, thiếu giá vốn. Gọi đúng tên để không ai đem đi nộp.
		$lai = (int) $ra['chua_vat'] - (int) $cp['tong'];

		return array(
			'tu'          => $tu,
			'den'         => $den,
			'dong_tien'   => self::dong_tien( $tu, $den ),
			'gd_so_dong'  => (int) $gd['so_dong'],
			'doanh_thu'   => array(
				'chua_vat' => (int) $ra['chua_vat'],
				'vat'      => (int) $ra['vat'],
				'co_vat'   => (int) $ra['co_vat'],
				'so_hd'    => (int) $ra['so_hd'],
				'khu_vuc'  => KHTC_HoaDonRa::gom_theo( 'khu_vuc', $ky ),
				'dich_vu'  => KHTC_HoaDonRa::gom_theo( 'dich_vu', $ky ),
			),
			'chi_phi'     => array(
				'tong'     => (int) $cp['tong'],
				'da_tra'   => (int) $cp['da_tra'],
				'chua_tra' => (int) $cp['chua_tra'],
				'so_dong'  => (int) $cp['so_dong'],
				'cheo'     => KHTC_ChiPhi::bang_cheo( $ky ),
			),
			'thue'        => KHTC_HoaDonVao::to_khai( $tu, $den ),
			'cong_no'     => array(
				'thu' => KHTC_CongNo::tong( 'thu', $den ),
				'tra' => KHTC_CongNo::tong( 'tra', $den ),
			),
			'doi_soat'    => self::doi_soat_trong_ky( $tu, $den ),
			'lai_tam'     => $lai,
		);
	}

	/** Các đợt đối soát có kỳ giao với kỳ báo cáo, kèm phần còn lệch. */
	public static function doi_soat_trong_ky( $tu, $den ) {
		$ra = array();
		foreach ( KHTC_DoiSoat::ds_dot() as $d ) {
			if ( $d->den < $tu || $d->tu > $den ) { continue; }
			$kq   = KHTC_DoiSoat::ket_qua( $d->id );
			$ra[] = array(
				'id'    => (int) $d->id,
				'ten'   => $d->ten,
				'kenh'  => KHTC_DoiSoat::ten_kenh( $d->kenh ),
				'thieu' => (int) $kq['tien_thieu'],
				'thua'  => (int) $kq['tien_thua'],
				'lech'  => (int) $kq['tien_lech'],
			);
		}
		return $ra;
	}

	// ------------------------------------------------------ chuỗi 12 tháng

	/**
	 * Số theo từng tháng, tính lùi từ tháng chứa $den.
	 *
	 * Dùng cho bảng xu hướng. Mỗi tháng là một lượt truy vấn gọn; 12 tháng là
	 * 12 lượt chứ không phải kéo cả năm giao dịch về PHP rồi tự gom.
	 */
	public static function chuoi_thang( $den, $so_thang = 12 ) {
		$ra  = array();
		$moc = substr( $den, 0, 7 );
		for ( $i = $so_thang - 1; $i >= 0; $i-- ) {
			$thang = gmdate( 'Y-m', strtotime( $moc . '-01 00:00:00 UTC' . ' -' . $i . ' month' ) );
			list( $t1, $t2 ) = self::bien_thang( $thang );
			$gd  = KHTC_GiaoDich::loc( array( 'tu' => $t1, 'den' => $t2 ) );
			$hd  = KHTC_HoaDonRa::loc( array( 'tu' => $t1, 'den' => $t2 ) );
			$cp  = KHTC_ChiPhi::loc( array( 'tu' => $t1, 'den' => $t2 ) );
			$ra[] = array(
				'thang'     => $thang,
				'thu'       => (int) $gd['thu'],
				'chi'       => (int) $gd['chi'],
				'doanh_thu' => (int) $hd['chua_vat'],
				'chi_phi'   => (int) $cp['tong'],
			);
		}
		return $ra;
	}

	// ------------------------------------------------------------- tải CSV

	public static function hang_csv( $b ) {
		$ra = array();
		$ra[] = array( 'BÁO CÁO KỲ', mysql2date( 'd/m/Y', $b['tu'] ) . ' → ' . mysql2date( 'd/m/Y', $b['den'] ) );
		$ra[] = array( 'Pháp nhân', KHTC_Cty::ten_day_du() );
		$ra[] = array();

		$ra[] = array( 'DÒNG TIỀN', 'Số dư đầu', 'Thu', 'Chi', 'Số dư cuối' );
		foreach ( $b['dong_tien']['rows'] as $r ) {
			$ra[] = array( $r['ten'], $r['dau'], $r['thu'], $r['chi'], $r['cuoi'] );
		}
		$t = $b['dong_tien']['tong'];
		$ra[] = array( 'Tổng', $t['dau'], $t['thu'], $t['chi'], $t['cuoi'] );
		$ra[] = array();

		$ra[] = array( 'DOANH THU', 'Chưa VAT', 'VAT', 'Có VAT', 'Số HĐ' );
		$ra[] = array( 'Tổng', $b['doanh_thu']['chua_vat'], $b['doanh_thu']['vat'], $b['doanh_thu']['co_vat'], $b['doanh_thu']['so_hd'] );
		foreach ( $b['doanh_thu']['khu_vuc'] as $r ) {
			$ra[] = array( 'Khu vực: ' . ( '' === $r->nhan ? '(để trống)' : $r->nhan ), $r->chua_vat, $r->vat, $r->co_vat, $r->so_hd );
		}
		foreach ( $b['doanh_thu']['dich_vu'] as $r ) {
			$ra[] = array( 'Dịch vụ: ' . ( '' === $r->nhan ? '(để trống)' : $r->nhan ), $r->chua_vat, $r->vat, $r->co_vat, $r->so_hd );
		}
		$ra[] = array();

		$c = $b['chi_phi']['cheo'];
		$ra[] = array_merge( array( 'CHI PHÍ' ), $c['bo_phan'], array( 'Tổng' ) );
		foreach ( $c['khoan_muc'] as $km ) {
			$hang = array( $km );
			foreach ( $c['bo_phan'] as $bp ) { $hang[] = $c['o'][ $km ][ $bp ] ?? 0; }
			$hang[] = $c['tong_hang'][ $km ];
			$ra[]   = $hang;
		}
		$hang = array( 'Tổng' );
		foreach ( $c['bo_phan'] as $bp ) { $hang[] = $c['tong_cot'][ $bp ] ?? 0; }
		$hang[] = $c['tong'];
		$ra[]   = $hang;
		$ra[]   = array();

		$th = $b['thue'];
		$ra[] = array( 'THUẾ GTGT' );
		$ra[] = array( 'VAT đầu ra', $th['vat_ra'] );
		$ra[] = array( 'VAT đầu vào được khấu trừ', $th['vat_kt'] );
		$ra[] = array( 'VAT đầu vào không khấu trừ', $th['vat_khong'] );
		$ra[] = array( $th['chuyen_ky'] > 0 ? 'Khấu trừ chuyển kỳ sau' : 'VAT phải nộp', $th['chuyen_ky'] > 0 ? $th['chuyen_ky'] : $th['phai_nop'] );
		$ra[] = array();

		$ra[] = array( 'CÔNG NỢ CUỐI KỲ', 'Tổng', 'Quá hạn', 'Số chứng từ' );
		$ra[] = array( 'Phải thu', $b['cong_no']['thu']['tong'], $b['cong_no']['thu']['qua_han'], $b['cong_no']['thu']['so_ct'] );
		$ra[] = array( 'Phải trả', $b['cong_no']['tra']['tong'], $b['cong_no']['tra']['qua_han'], $b['cong_no']['tra']['so_ct'] );
		$ra[] = array();

		if ( $b['doi_soat'] ) {
			$ra[] = array( 'ĐỐI SOÁT CÒN LỆCH', 'Kênh', 'Thiếu', 'Thừa', 'Lệch tiền' );
			foreach ( $b['doi_soat'] as $d ) {
				$ra[] = array( $d['ten'], $d['kenh'], $d['thieu'], $d['thua'], $d['lech'] );
			}
			$ra[] = array();
		}

		$ra[] = array( 'Kết quả TẠM TÍNH (doanh thu chưa VAT − chi phí)', $b['lai_tam'] );
		$ra[] = array( 'Không phải báo cáo kết quả kinh doanh: thiếu khấu hao, giá vốn, phân bổ trước sau.' );
		return $ra;
	}

	public static function tai_csv() {
		if ( empty( $_GET['khtc_tai_bc'] ) ) { return; }
		if ( ! is_user_logged_in() || ! current_user_can( KHTC_CAP ) ) { return; }
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'khtc_tai_bc' ) ) {
			return;
		}
		$tu  = KHTC_GiaoDich::doc_ngay( wp_unslash( $_GET['tu'] ?? '' ) );
		$den = KHTC_GiaoDich::doc_ngay( wp_unslash( $_GET['den'] ?? '' ) );
		if ( '' === $tu || '' === $den ) { return; }

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="bao-cao-' . $tu . '_' . $den . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		fwrite( $f, "\xEF\xBB\xBF" );
		foreach ( self::hang_csv( self::ky( $tu, $den ) ) as $hang ) {
			fputcsv( $f, $hang );
		}
		fclose( $f );
		exit;
	}
}
