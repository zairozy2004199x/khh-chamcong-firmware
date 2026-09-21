<?php
/**
 * SAO KÊ NGÂN HÀNG — SỔ RIÊNG, CHỈ ĐỂ ĐỐI CHIẾU.
 *
 * =============================================================================================
 * ANH THẮNG CHỐT HAI CÂU, VÀ CÂU THỨ HAI LÀ RÀNG BUỘC LỚN NHẤT CỦA CẢ TỆP NÀY
 * =============================================================================================
 *   *"Đẩy sao kê sang trang sao kê ngân hàng, nó tự lọc chứ"*
 *   *"KHÔNG nên đẩy vào trang ghế bằng file sao kê, sau nó sẽ rối"*
 *
 * 🔴 KHÔNG MỘT DÒNG NÀO Ở ĐÂY CHẢY SANG BẢNG `thu`.
 *    Bảng `thu` là SỔ TIỀN: mỗi dòng là một lượt doanh thu có thật, và nó nuôi mọi báo cáo lẫn
 *    phép tính tiền trên tay người thu. Sao kê ngân hàng thì lẫn cả tiền ra, phí, lãi, tiền của
 *    mảng khác chuyển nhầm. Đổ thẳng vào `thu` là doanh thu ghế phình lên bằng những khoản
 *    không phải của nó — mà `ref` là UNIQUE, nên xoá dòng rác đi rồi thì đúng giao dịch ấy sau
 *    này webhook bắn lại cũng không vào được nữa. Một thao tác hỏng hai lần.
 *
 *    Tệp này vì thế CHỈ GHI vào bảng `sao_ke` và CHỈ ĐỌC bảng `thu`. Không có hàm nào ở đây
 *    gọi `VHG_Thu::ghi()`, và đó là điều `kiem-sao-ke-ngan-hang.php` canh bằng cả phép quét mã.
 *
 * =============================================================================================
 * "TỰ LỌC" NGHĨA LÀ GÌ
 * =============================================================================================
 * Mỗi dòng sao kê được gắn một NHÃN, suy từ chính nội dung chuyển khoản:
 *   · `ghe`   — nội dung mang mã ghế ("GHE3 T1ABC") hoặc tên máy trong bản đồ ("AMBD 12");
 *   · `ma`    — đơn mua mã trước ("MUA<mã đơn>");
 *   · `ra`    — tiền ra (không phải doanh thu);
 *   · `khac`  — còn lại: tiền của mảng khác, phí, lãi, chuyển nội bộ.
 *
 * ⚠️ NHÃN LÀ PHỎNG ĐOÁN, VÀ PHẢI ĐƯỢC ĐỐI XỬ NHƯ PHỎNG ĐOÁN. Nó giúp lọc nhanh, không phải
 *    lệnh hạch toán. Cột quan trọng hơn nhãn là `daCoTrongSo` — dòng này sổ ghế ĐÃ có chưa —
 *    và câu đó tra bằng `ref`, thứ duy nhất chắc chắn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_SaoKe {

	const NHAN = array(
		'ghe'  => '🪑 Ghế',
		'ma'   => '🎟 Mua mã trước',
		'ra'   => '↗ Tiền ra',
		'khac' => '— Khác',
	);

	/**
	 * Gắn nhãn cho một dòng sao kê.
	 *
	 * ⚠️ TIỀN RA XÉT TRƯỚC MỌI THỨ. Một lượt chuyển tiền ĐI có nội dung "GHE3 T1ABC" (hoàn tiền
	 *    cho khách chẳng hạn) mà gắn nhãn ghế là nó nằm lẫn trong danh sách doanh thu, và người
	 *    đối soát cộng nhầm vào.
	 */
	public static function nhan( $noi_dung, $tien_ra ) {
		if ( $tien_ra ) { return 'ra'; }
		$nd = (string) $noi_dung;
		list( $ma_may, $ma_lenh ) = VHG_Doc::ghe_va_ma( $nd );
		if ( '' !== $ma_may ) { return 'ghe'; }
		if ( '' !== VHG_Doc::don_mua( $nd ) ) { return 'ma'; }
		/* 🔴 KHÔNG DÙNG `VHG_Doc::ten_may()` Ở ĐÂY, dù nghe rất hợp lý.
		   Hàm ấy sinh ra cho sao kê CỔNG, nơi nội dung chính là tên máy ("AMBD 12") — nên nó
		   nhận MỌI chuỗi không rỗng làm tên máy. Sao kê NGÂN HÀNG thì nội dung là câu tiếng
		   Việt bất kỳ ("THANH TOAN TIEN DIEN T9"), và đem hàm ấy ra dùng là gắn nhãn "ghế" cho
		   gần như cả sổ — bộ lọc mất sạch ý nghĩa đúng lúc nó là lý do màn này tồn tại.
		   Ở đây chỉ nhận dấu hiệu CHẮC CHẮN: mẫu "GHE<mã> <lượt>" và "MUA<mã đơn>". */
		return 'khac';
	}

	/** Mã ghế đọc được từ nội dung, '' nếu không có. Để màn hiện cột "Máy". */
	public static function may( $noi_dung ) {
		list( $ma_may, $ma_lenh ) = VHG_Doc::ghe_va_ma( (string) $noi_dung );
		return $ma_may;
	}

	/**
	 * Thời điểm của một dòng sao kê, từ ô ngày ở bất cứ dạng nào nhà băng viết ra.
	 *
	 * 🔴 BỐN DẠNG, VÀ DẠNG NÀO CŨNG GẶP THẬT:
	 *   · '11/09/2026 17:55'  — kiểu Việt, có giờ;
	 *   · '2026-09-11 17:55'  — kiểu ISO;
	 *   · '11/09/2026'        — CHỈ NGÀY, không giờ (bản xuất theo ngày);
	 *   · 45911.74            — SỐ NGÀY của Excel, khi cột ấy được định dạng là ngày.
	 *
	 * ⚠️ KHÔNG ĐƯỢC TRẢ RỖNG. Cột `luc` là NOT NULL và là thứ màn sắp xếp theo; để rỗng thì
	 *    MySQL nhét '0000-00-00' và cả dòng rơi xuống tận cùng danh sách, coi như mất. Đọc
	 *    không ra thì lấy giờ nhập — sai vài giờ vẫn hơn mất hẳn một dòng tiền.
	 */
	private static function luc_cua( $tho ) {
		$tho = trim( (string) $tho );
		if ( '' === $tho ) { return current_time( 'mysql' ); }
		$x = VHG_Doc::ngay( $tho );
		if ( '' !== $x ) { return $x; }
		if ( class_exists( 'VHG_Tep' ) ) {
			$x = VHG_Tep::ngay_excel( $tho );
			if ( '' !== $x ) { return $x; }
		}
		if ( preg_match( '/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $tho, $m ) ) {
			return sprintf( '%04d-%02d-%02d 00:00:00', $m[3], $m[2], $m[1] );
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $tho ) ) { return $tho . ' 00:00:00'; }
		return current_time( 'mysql' );
	}

	/**
	 * Nhập một bảng sao kê (dòng đầu là tiêu đề).
	 *
	 * 🔴 CHỈ GHI VÀO `sao_ke`. Xem khối 🔴 đầu tệp.
	 *
	 * @return array ok/error + số dòng vào, trùng, bỏ.
	 */
	public static function nhap( $bang, $ten_tep = '' ) {
		global $wpdb;
		if ( ! is_array( $bang ) || count( $bang ) < 2 ) {
			return array( 'ok' => false, 'error' => 'Cần cả dòng TIÊU ĐỀ và ít nhất một dòng dữ liệu.' );
		}
		$h = $bang[0];
		/* Tên cột của sao kê ngân hàng mỗi nhà mỗi khác — nhận nhiều cách gọi cùng một thứ.
		   ⚠️ KHÔNG đoán bằng vị trí cột. Vị trí là thứ đổi giữa hai lần xuất mà không ai báo. */
		$i_ref  = VHG_Nhap::cot( $h, array( 'mã tham chiếu', 'số tham chiếu', 'tham chiếu', 'reference', 'mã gd', 'mã giao dịch' ) );
		$i_luc  = VHG_Nhap::cot( $h, array( 'ngày giao dịch', 'thời gian', 'ngày ghi sổ', 'ngày' ) );
		$i_vao  = VHG_Nhap::cot( $h, array( 'ghi có', 'tiền vào', 'phát sinh có', 'credit' ) );
		$i_ra   = VHG_Nhap::cot( $h, array( 'ghi nợ', 'tiền ra', 'phát sinh nợ', 'debit' ) );
		$i_tien = VHG_Nhap::cot( $h, array( 'số tiền' ) );
		$i_nd   = VHG_Nhap::cot( $h, array( 'nội dung', 'diễn giải', 'mô tả', 'description' ) );
		$i_du   = VHG_Nhap::cot( $h, array( 'đối ứng', 'người gửi', 'tên người', 'tk đối ứng' ) );
		$i_tk   = VHG_Nhap::cot( $h, array( 'số tài khoản', 'tài khoản' ) );

		if ( $i_ref < 0 ) {
			return array( 'ok' => false, 'error' => 'Không thấy cột "Mã tham chiếu" (hoặc "Số tham chiếu" / '
				. '"Mã giao dịch") trong dòng tiêu đề. Đó là cột chặn nhập trùng, thiếu nó thì mỗi lần đổ lại '
				. 'sẽ cộng đôi — nên chưa nhập được.' );
		}
		if ( $i_vao < 0 && $i_ra < 0 && $i_tien < 0 ) {
			return array( 'ok' => false, 'error' => 'Không thấy cột tiền nào ("Ghi có" / "Ghi nợ" / "Số tiền").' );
		}

		$lay = function ( $d, $i ) { return ( $i >= 0 && isset( $d[ $i ] ) ) ? trim( (string) $d[ $i ] ) : ''; };
		$bang_sk = VHG_DB::t( 'sao_ke' );
		$vao = 0; $trung = 0; $bo = 0; $dem = array( 'ghe' => 0, 'ma' => 0, 'ra' => 0, 'khac' => 0 );

		for ( $r = 1; $r < count( $bang ); $r++ ) {
			$d   = $bang[ $r ];
			$ref = $lay( $d, $i_ref );
			if ( '' === $ref ) { $bo++; continue; }

			/* Hai cột Có / Nợ là cách sao kê ngân hàng hay dùng; một cột "Số tiền" có dấu âm là
			   cách kia. Nhận cả hai, vì đoán sai chiều tiền là cộng tiền ra vào doanh thu. */
			$t_vao = VHG_Doc::so( $lay( $d, $i_vao ) );
			$t_ra  = VHG_Doc::so( $lay( $d, $i_ra ) );
			if ( $i_vao >= 0 || $i_ra >= 0 ) {
				$tien_ra = ( $t_ra > 0 && $t_vao <= 0 );
				$tien    = $tien_ra ? $t_ra : $t_vao;
			} else {
				$tho  = $lay( $d, $i_tien );
				$tien = VHG_Doc::so( $tho );
				$tien_ra = ( $tien < 0 || false !== strpos( $tho, '-' ) );
				$tien = abs( $tien );
			}
			if ( $tien <= 0 ) { $bo++; continue; }

			$nd  = $lay( $d, $i_nd );
			$nhan = self::nhan( $nd, $tien_ra );
			$dem[ $nhan ]++;

			/* ⚠️ KHÔNG dùng `$wpdb->insert` trần: dòng trùng `ref` sẽ ném lỗi SQL ra màn. Sao kê
			   tải về CHỒNG LẤN ngày với lần trước là cách người ta vẫn dùng, không phải sai
			   sót — nên trùng là chuyện BÌNH THƯỜNG, phải im lặng bỏ qua và đếm lại. */
			$co = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $bang_sk WHERE ref=%s", $ref ) );
			if ( $co ) { $trung++; continue; }

			$luc = self::luc_cua( $lay( $d, $i_luc ) );
			$wpdb->insert( $bang_sk, array(
				'ref' => $ref, 'luc' => $luc, 'so_tien' => $tien, 'tien_ra' => $tien_ra ? 1 : 0,
				'noi_dung' => mb_substr( $nd, 0, 255 ), 'doi_ung' => mb_substr( $lay( $d, $i_du ), 0, 190 ),
				'tai_khoan' => mb_substr( $lay( $d, $i_tk ), 0, 60 ), 'nhan' => $nhan,
				'ma_may' => self::may( $nd ), 'ten_tep' => mb_substr( (string) $ten_tep, 0, 190 ),
				'nhap_luc' => current_time( 'mysql' ),
			) );
			$vao++;
		}

		return array( 'ok' => true, 'vao' => $vao, 'trung' => $trung, 'bo' => $bo, 'dem' => $dem,
			'thong_bao' => 'Vào sổ sao kê ' . $vao . ' dòng · bỏ qua ' . $trung . ' dòng đã có'
				. ( $bo > 0 ? ' · bỏ ' . $bo . ' dòng thiếu mã hoặc không có tiền' : '' )
				. '. Trong đó ghế ' . $dem['ghe'] . ' · mua mã ' . $dem['ma'] . ' · tiền ra '
				. $dem['ra'] . ' · khác ' . $dem['khac'] . '. '
				. 'Sổ sao kê là sổ RIÊNG — không dòng nào chảy sang sổ tiền của ghế.' );
	}

	/**
	 * Danh sách để vẽ màn, kèm câu trả lời "sổ ghế đã có dòng này chưa".
	 *
	 * 🔴 TRA MỘT LƯỢT, KHÔNG HỎI TRONG VÒNG LẶP. 500 dòng là 500 câu hỏi CSDL — màn treo vài
	 *    giây mỗi lần mở, đúng màn người ta mở nhiều nhất trong kỳ đối soát.
	 */
	public static function ds( $nhan = '', $tim = '', $gioi_han = 300 ) {
		global $wpdb;
		$t  = VHG_DB::t( 'sao_ke' );
		$ve = array( '1=1' ); $tv = array();
		if ( '' !== $nhan && isset( self::NHAN[ $nhan ] ) ) { $ve[] = 'nhan=%s'; $tv[] = $nhan; }
		if ( '' !== trim( (string) $tim ) ) {
			$ve[] = '(noi_dung LIKE %s OR ref LIKE %s OR doi_ung LIKE %s)';
			$k = '%' . $wpdb->esc_like( trim( (string) $tim ) ) . '%';
			$tv[] = $k; $tv[] = $k; $tv[] = $k;
		}
		$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $ve ) . ' ORDER BY luc DESC, id DESC LIMIT %d';
		$tv[] = max( 1, (int) $gioi_han );
		$ds = VHG_DB::rows( $wpdb->prepare( $sql, $tv ) );
		if ( ! $ds ) { return array(); }

		/* Một câu hỏi cho cả trang: mã nào trong số này đã có ở sổ tiền. */
		$refs = array();
		foreach ( $ds as $r ) { $refs[] = (string) $r['ref']; }
		$co = array();
		if ( $refs ) {
			$cho = implode( ',', array_fill( 0, count( $refs ), '%s' ) );
			$bt  = VHG_DB::t( 'thu' );
			$hit = VHG_DB::rows( $wpdb->prepare( "SELECT ref FROM $bt WHERE ref IN ($cho)", $refs ) );
			foreach ( (array) $hit as $x ) { $co[ (string) $x['ref'] ] = 1; }
		}
		foreach ( $ds as $k => $r ) { $ds[ $k ]['trong_so'] = isset( $co[ (string) $r['ref'] ] ) ? 1 : 0; }
		return $ds;
	}

	/** Đếm theo nhãn — nuôi mấy ô tóm tắt ở đầu màn. */
	public static function tom_tat() {
		global $wpdb;
		$t = VHG_DB::t( 'sao_ke' );
		$ra = array( 'tong' => 0, 'tien' => 0 );
		foreach ( array_keys( self::NHAN ) as $n ) { $ra[ $n ] = 0; }
		$rows = VHG_DB::rows( "SELECT nhan, COUNT(*) so, SUM(so_tien) tien FROM $t GROUP BY nhan" );
		foreach ( (array) $rows as $r ) {
			$n = (string) $r['nhan'];
			if ( isset( $ra[ $n ] ) ) { $ra[ $n ] = (int) $r['so']; }
			$ra['tong'] += (int) $r['so'];
			/* Tiền ra KHÔNG cộng vào tổng tiền — nó là tiền đi khỏi tài khoản. */
			if ( 'ra' !== $n ) { $ra['tien'] += (int) $r['tien']; }
		}
		return $ra;
	}

	/**
	 * Những dòng NGÂN HÀNG CÓ mà SỔ GHẾ CHƯA CÓ — đây là câu hỏi đáng tiền nhất của cả màn.
	 *
	 * Chỉ xét nhãn 'ghe' và 'ma': tiền của mảng khác thì sổ ghế vốn không có, kể ra là nhiễu.
	 */
	public static function thieu_o_so_ghe( $gioi_han = 200 ) {
		global $wpdb;
		$t  = VHG_DB::t( 'sao_ke' );
		$bt = VHG_DB::t( 'thu' );
		return VHG_DB::rows( $wpdb->prepare(
			"SELECT s.* FROM $t s LEFT JOIN $bt u ON u.ref = s.ref
			 WHERE s.tien_ra = 0 AND s.nhan IN ('ghe','ma') AND u.id IS NULL
			 ORDER BY s.luc DESC LIMIT %d", max( 1, (int) $gioi_han ) ) );
	}

	/** Xoá sạch sổ sao kê. Không đụng sổ tiền — hai bảng khác nhau, đó là cả ý đồ. */
	public static function xoa_het() {
		global $wpdb;
		$t = VHG_DB::t( 'sao_ke' );
		$so = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
		/* ⚠️ `DELETE FROM` chứ KHÔNG `TRUNCATE`. TRUNCATE là lệnh DDL: nó không nằm trong giao
		   dịch, không lùi lại được, và bệ thử SQLite cũng không hiểu nó — tức chỗ này sẽ không
		   bao giờ được bài kiểm nào chạm tới. Vài nghìn dòng thì DELETE nhanh như nhau. */
		$wpdb->query( "DELETE FROM $t" );
		return array( 'ok' => true, 'thong_bao' => 'Đã dọn ' . $so . ' dòng sao kê. '
			. 'Sổ tiền của ghế KHÔNG bị đụng tới — hai sổ nằm ở hai bảng khác nhau.' );
	}
}
