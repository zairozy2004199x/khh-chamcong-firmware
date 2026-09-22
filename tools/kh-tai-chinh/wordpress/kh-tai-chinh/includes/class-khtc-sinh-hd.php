<?php
/**
 * Sinh hoá đơn đầu ra từ sao kê — nửa đầu của quy trình, phần tốn công nhất.
 *
 * Bản trước plugin chỉ NHẬN danh sách hoá đơn đã làm xong rồi tính tiếp. Việc
 * biến hàng chục nghìn dòng QR thành vài trăm hoá đơn vẫn nằm trong Excel, và
 * đó mới là chỗ mất nhiều giờ nhất mỗi tháng.
 *
 * Luồng: tiền vào tài khoản mang MÃ CỬA HÀNG → danh mục điểm cho biết điểm
 * xuất hoá đơn → cộng theo điểm trong kỳ → mỗi điểm một hoá đơn.
 *
 * BA THỨ MÁY KHÔNG TỰ QUYẾT, vì đoán sai thì ra một danh sách trông rất hợp lý
 * mà sai — loại sai đắt nhất:
 *
 *   1. KỲ, NGÀY HOÁ ĐƠN và ĐỘ MỊN. Người dùng chọn, không suy từ dữ liệu.
 *      Độ mịn: gộp cả kỳ thành một tờ mỗi điểm, hay tách theo từng ngày doanh
 *      thu. Đọc 2.781 hoá đơn thật tháng 8/2026 thì thấy công ty dùng CẢ HAI:
 *      KH705 gần như một tờ cho mỗi (điểm × ngày xuất), còn KH989 dồn 294 tờ
 *      vào một ngày xuất duy nhất — tức là tách theo ngày doanh thu rồi xuất
 *      gộp một đợt. Không có một luật đúng cho cả hai, nên đây phải là lựa
 *      chọn chứ không phải giả định.
 *   2. NGUỒN TIỀN nào vào hoá đơn: sao kê tài khoản nào, và có gộp các đợt
 *      cổng (Payoo/VNPay/MoMo) vào không. Người dùng tick.
 *   3. ĐIỂM NÀO KHÔNG XUẤT: mã test, mã vãng lai, gian đã đóng — đặt cờ
 *      "bỏ qua" trong danh mục điểm.
 *
 * Máy chỉ làm phần cộng và phần tách VAT, là phần nó làm không sai.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_SinhHD {

	/**
	 * Gom tiền trong kỳ theo điểm xuất hoá đơn. KHÔNG ghi gì vào sổ.
	 *
	 * Tách riêng khỏi bước tạo để xem trước được, và để kiểm được mà không cần
	 * ghi. Mọi con số trên màn hình xem trước đều ra từ đúng hàm này.
	 *
	 * @param array $l tu, den, nh (mảng id tài khoản), dot (mảng id đợt cổng),
	 *                tach ('' gộp cả kỳ | 'ngay' mỗi ngày một tờ)
	 * @return array [diem => [...], bo_qua => [...], la => [...], tong...]
	 */
	public static function gom( $l ) {
		global $wpdb;
		$tu  = (string) ( $l['tu'] ?? '' );
		$den = (string) ( $l['den'] ?? '' );
		if ( '' === $tu || '' === $den ) {
			return new WP_Error( 'ky', 'Chưa chọn kỳ.' );
		}
		$cty = KHTC_Cty::dang_chon();
		$tra = KHTC_Diem::bang_tra( $cty );

		$theo_diem = array();   // khoá gom → [ten_diem, ma_misa, khu_vuc, dich_vu, tien, so_dong, ma[]]
		$bo_qua    = array();   // điểm có cờ bỏ qua
		$la        = array();   // mã cửa hàng không có trong danh mục
		$tong      = 0;
		$tong_bo   = 0;
		$tong_la   = 0;

		$tach = ( 'ngay' === ( $l['tach'] ?? '' ) );

		$nhan = function ( $ma_ch, $tien, $nguon, $ngay = '' ) use ( &$theo_diem, &$bo_qua, &$la, &$tong, &$tong_bo, &$tong_la, $tra, $tach ) {
			$ma_ch = trim( (string) $ma_ch );
			if ( '' === $ma_ch || ! isset( $tra[ $ma_ch ] ) ) {
				// Tiền có thật mà không biết của điểm nào. KHÔNG được im lặng bỏ
				// đi: doanh thu hụt mà không ai thấy. Gom riêng, đếm, nói ra.
				$la[ $ma_ch ] = ( $la[ $ma_ch ] ?? 0 ) + $tien;
				$tong_la     += $tien;
				return;
			}
			$d = $tra[ $ma_ch ];
			if ( $d->bo_qua ) {
				$k              = $d->ten_diem ?: $d->ma_cua_hang;
				$bo_qua[ $k ]   = ( $bo_qua[ $k ] ?? 0 ) + $tien;
				$tong_bo       += $tien;
				return;
			}
			// Gom theo ĐIỂM XUẤT HOÁ ĐƠN, không theo mã cửa hàng: một điểm
			// thường có nhiều mã (mỗi máy POS một mã) mà chỉ xuất một hoá đơn.
			$ten = $d->ten_diem ?: $d->ma_cua_hang;
			// Tách theo ngày thì khoá gom mang cả ngày, nên mỗi ngày một tờ.
			// Ngày đứng TRƯỚC trong khoá để danh sách xếp theo ngày.
			$k = $tach ? ( $ngay . '|' . $ten ) : $ten;
			if ( ! isset( $theo_diem[ $k ] ) ) {
				$theo_diem[ $k ] = array(
					'ten_diem' => $ten,
					'ngay'     => $tach ? $ngay : '',
					'ma_misa'  => $d->ma_misa,
					'khu_vuc'  => $d->khu_vuc,
					'dich_vu'  => $d->dich_vu,
					'tien'     => 0,
					'so_dong'  => 0,
					'nguon'    => array(),
				);
			}
			$theo_diem[ $k ]['tien']    += $tien;
			$theo_diem[ $k ]['so_dong']++;
			$theo_diem[ $k ]['nguon'][ $nguon ] = true;
			$tong += $tien;
		};

		// --- nguồn 1: sao kê ngân hàng, mã cửa hàng nằm ở cột mã giao dịch phụ
		$nh = array_filter( array_map( 'intval', (array) ( $l['nh'] ?? array() ) ) );
		if ( $nh ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ma_cua_hang, so_tien, ngay FROM ' . KHTC_DB::bang( 'giao_dich' ) . "
					 WHERE cty = %s AND loai = 'thu' AND ngay >= %s AND ngay <= %s
					   AND ngan_hang_id IN (" . implode( ',', array_fill( 0, count( $nh ), '%d' ) ) . ')',
					array_merge( array( $cty, $tu, $den ), $nh )
				)
			);
			foreach ( $rows as $r ) { $nhan( $r->ma_cua_hang, (int) $r->so_tien, 'sao kê', $r->ngay ); }
		}

		// --- nguồn 2: các đợt cổng đã nạp (Payoo / VNPay / MoMo / Zalo)
		$dot = array_filter( array_map( 'intval', (array) ( $l['dot'] ?? array() ) ) );
		if ( $dot ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT d.ma_cua_hang, d.so_tien, d.ngay, o.ten FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' d
					 JOIN ' . KHTC_DB::bang( 'doi_soat' ) . ' o ON o.id = d.dot_id
					 WHERE d.ngay >= %s AND d.ngay <= %s
					   AND d.dot_id IN (' . implode( ',', array_fill( 0, count( $dot ), '%d' ) ) . ')',
					array_merge( array( $tu, $den ), $dot )
				)
			);
			foreach ( $rows as $r ) { $nhan( $r->ma_cua_hang, (int) $r->so_tien, $r->ten, $r->ngay ); }
		}

		ksort( $theo_diem );
		arsort( $la );
		return array(
			'diem'    => array_values( $theo_diem ),
			'bo_qua'  => $bo_qua,
			'la'      => $la,
			'tong'    => $tong,
			'tong_bo' => $tong_bo,
			'tong_la' => $tong_la,
		);
	}

	/**
	 * Tạo hoá đơn từ kết quả gom.
	 *
	 * Số hoá đơn cấp liên tiếp từ $bat_dau. Trùng số thì DỪNG HẲN chứ không bỏ
	 * qua rồi chạy tiếp: số hoá đơn nhảy cóc giữa chừng là thứ cơ quan thuế hỏi
	 * đầu tiên, và sửa sau tốn hơn nhiều so với chạy lại.
	 *
	 * @return array|WP_Error [tao, so_dau, so_cuoi, tien]
	 */
	public static function tao( $l ) {
		$g = self::gom( $l );
		if ( is_wp_error( $g ) ) { return $g; }
		if ( ! $g['diem'] ) {
			return new WP_Error( 'trong', 'Kỳ này không gom được đồng nào. Kiểm lại kỳ, nguồn tiền, và danh mục điểm.' );
		}
		$ngay = KHTC_GiaoDich::doc_ngay( (string) ( $l['ngay_hd'] ?? '' ) );
		if ( '' === $ngay ) {
			return new WP_Error( 'ngay', 'Chưa chọn ngày hoá đơn.' );
		}
		$so = (int) ( $l['bat_dau'] ?? 0 );
		if ( $so <= 0 ) {
			return new WP_Error( 'so', 'Chưa điền số hoá đơn bắt đầu.' );
		}
		$ts = (string) ( $l['thue_suat'] ?? KHTC_HoaDonRa::TS_MAC_DINH );

		// Kiểm TRƯỚC khi ghi dòng nào: thiếu chỗ này thì một số trùng ở giữa
		// làm sổ dở dang, nửa đã ghi nửa chưa.
		$can = count( $g['diem'] );
		for ( $i = 0; $i < $can; $i++ ) {
			if ( KHTC_HoaDonRa::da_co( (string) ( $so + $i ) ) ) {
				return new WP_Error(
					'trung',
					'Số hoá đơn ' . ( $so + $i ) . ' đã có trong sổ. Cần ' . $can . ' số liên tiếp từ ' . $so . '. Chọn số bắt đầu khác.'
				);
			}
		}

		KHTC_NhatKy::mo_lo();
		$tao  = 0;
		$tien = 0;
		$loi  = array();
		foreach ( $g['diem'] as $d ) {
			$kq = KHTC_HoaDonRa::them(
				array(
					'ngay'       => mysql2date( 'd/m/Y', $ngay ),
					'so_hd'      => (string) $so,
					'khach'      => (string) ( $l['khach'] ?? 'Bán cho người tiêu dùng' ),
					'noi_dung'   => (string) ( $l['noi_dung'] ?? 'Dịch vụ vui chơi giải trí' ),
					'so_luong'   => 1,
					'dvt'        => 'Kỳ',
					'co_vat'     => $d['tien'],
					'thue_suat'  => $ts,
					'khu_vuc'    => $d['khu_vuc'],
					'dich_vu'    => $d['dich_vu'],
					'ma_diem'    => $d['ten_diem'],
					'ma_misa'    => $d['ma_misa'],
					// Tách theo ngày thì ghi rõ NGÀY DOANH THU của tờ này. Ngày
					// hoá đơn là ngày XUẤT, hai thứ khác nhau, và kế toán cần
					// truy lại được tờ này gom doanh thu ngày nào.
					'ghi_chu'    => empty( $d['ngay'] )
						? 'Sinh từ sao kê ' . mysql2date( 'd/m/Y', $l['tu'] ) . '–' . mysql2date( 'd/m/Y', $l['den'] )
						: 'Sinh từ sao kê — doanh thu ngày ' . mysql2date( 'd/m/Y', $d['ngay'] ),
				)
			);
			if ( is_wp_error( $kq ) ) {
				$loi[] = $d['ten_diem'] . ': ' . $kq->get_error_message();
				continue;
			}
			$tao++;
			$tien += $d['tien'];
			$so++;
		}
		KHTC_NhatKy::dong_lo();
		KHTC_NhatKy::ghi(
			'sinh',
			'hd_ra',
			0,
			sprintf(
				'Sinh %d hoá đơn từ sao kê kỳ %s–%s, tổng %s đ',
				$tao,
				mysql2date( 'd/m/Y', $l['tu'] ),
				mysql2date( 'd/m/Y', $l['den'] ),
				number_format( $tien, 0, ',', '.' )
			)
		);
		return array( 'tao' => $tao, 'tien' => $tien, 'so_cuoi' => $so - 1, 'loi' => $loi );
	}
}
