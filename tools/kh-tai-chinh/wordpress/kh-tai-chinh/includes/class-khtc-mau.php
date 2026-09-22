<?php
/**
 * Dữ liệu mẫu để chạy thử.
 *
 * ĐIỀU NGUY HIỂM NHẤT CỦA MỘT NÚT NHƯ THẾ NÀY là dữ liệu giả lẫn vào sổ thật
 * rồi không ai tách ra được nữa — tệ nhất là một hoá đơn mẫu lọt vào tờ khai
 * GTGT đem nộp. Nên có ba lớp chặn, và cả ba đều cần thiết:
 *
 *   1. CHỈ CHẠY KHI BẤM. Không có đường nào tự nạp, kể cả lúc kích hoạt plugin.
 *   2. TỪ CHỐI nếu pháp nhân đang chọn đã có bất kỳ dữ liệu nào không phải do
 *      chính nút này tạo ra. Không hỏi "anh chắc chưa" — với sổ tiền thì một
 *      cái bấm nhầm là quá đủ.
 *   3. NHỚ ĐÚNG TỪNG DÒNG ĐÃ TẠO, lưu danh sách id vào wp_options. Xoá là xoá
 *      đúng những dòng đó, không phải "xoá hết bảng" — nếu người dùng lỡ nhập
 *      dữ liệu thật xen vào thì nó vẫn còn nguyên.
 *
 * Số liệu mẫu được dựng ĂN KHỚP VỚI NHAU: sao kê có dòng đúng bằng tiền của
 * hoá đơn, chi phí mang số chứng từ trùng hoá đơn đầu vào, hợp đồng có mã điểm
 * trùng hoá đơn đầu ra. Dữ liệu rời rạc thì mọi màn hình đối soát đều ra rỗng
 * và người dùng tưởng phần mềm hỏng.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_Mau {

	/** Các bảng có thể chứa dòng mẫu, theo thứ tự xoá (con trước, cha sau). */
	const BANG = array( 'thanh_toan', 'ho_so', 'ds_dong', 'doi_soat', 'hd_vao', 'hd_ra', 'chi_phi', 'hop_dong', 'giao_dich', 'diem', 'ngan_hang' );

	/**
	 * Bảng KHÔNG mang cột `cty` — không hỏi trực tiếp theo pháp nhân được.
	 *
	 * ds_dong thuộc về một đợt đối soát, và đợt mới mang cty. Kiểm "sổ có dữ
	 * liệu thật không" chỉ cần nhìn bảng đợt là đủ; dòng cổng không thể tồn
	 * tại mà không có đợt.
	 */
	const KHONG_CO_CTY = array( 'ds_dong' );

	private static function khoa( $cty = null ) {
		return 'khtc_mau_' . ( $cty ? $cty : KHTC_Cty::dang_chon() );
	}

	/** Danh sách id đã tạo, theo bảng. */
	public static function da_tao( $cty = null ) {
		$d = get_option( self::khoa( $cty ), array() );
		return is_array( $d ) ? $d : array();
	}

	/** Đếm dòng mẫu còn sống — dòng người dùng đã xoá tay thì không đếm nữa. */
	public static function dem_mau( $cty = null ) {
		global $wpdb;
		$da  = self::da_tao( $cty );
		$tong = 0;
		foreach ( self::BANG as $t ) {
			$ids = array_map( 'intval', $da[ $t ] ?? array() );
			if ( ! $ids ) { continue; }
			$tong += (int) $wpdb->get_var(
				'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $t ) . ' WHERE id IN (' . implode( ',', $ids ) . ')'
			);
		}
		return $tong;
	}

	/**
	 * Dòng KHÔNG phải mẫu đang có trong sổ, theo bảng. Rỗng nghĩa là sổ sạch.
	 */
	public static function du_lieu_that( $cty = null ) {
		global $wpdb;
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		$da  = self::da_tao( $cty );
		$ra  = array();
		foreach ( self::BANG as $t ) {
			if ( in_array( $t, self::KHONG_CO_CTY, true ) ) { continue; }
			$ids   = array_map( 'intval', $da[ $t ] ?? array() );
			$tru   = $ids ? ' AND id NOT IN (' . implode( ',', $ids ) . ')' : '';
			$so    = (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $t ) . ' WHERE cty = %s' . $tru, $cty )
			);
			if ( $so ) { $ra[ $t ] = $so; }
		}
		return $ra;
	}

	/** Tháng cách đây $lui tháng, dạng YYYY-MM. */
	private static function thang( $lui ) {
		return gmdate( 'Y-m', strtotime( current_time( 'Y-m' ) . '-01 00:00:00 UTC -' . (int) $lui . ' month' ) );
	}

	/** Ngày thứ $ngay của tháng cách đây $lui tháng, dạng dd/mm/yyyy. */
	private static function ngay( $lui, $ngay ) {
		list( $d1, $d2 ) = KHTC_BaoCao::bien_thang( self::thang( $lui ) );
		$max = (int) substr( $d2, 8, 2 );
		return sprintf( '%02d/%s/%s', min( (int) $ngay, $max ), substr( $d1, 5, 2 ), substr( $d1, 0, 4 ) );
	}

	// ------------------------------------------------------------------ nạp

	public static function nap() {
		global $wpdb;
		$cty = KHTC_Cty::dang_chon();

		$that = self::du_lieu_that( $cty );
		if ( $that ) {
			$mo_ta = array();
			foreach ( $that as $t => $n ) { $mo_ta[] = KHTC_NhatKy::ten_bang( $t ) . ': ' . number_format( $n, 0, ',', '.' ); }
			return new WP_Error(
				'co_that',
				'Pháp nhân ' . KHTC_Cty::ten( $cty ) . ' đã có dữ liệu không phải dữ liệu mẫu (' . implode( ' · ', $mo_ta )
					. '). Không nạp dữ liệu mẫu đè lên sổ thật. Đổi sang pháp nhân còn trống, hoặc xoá dữ liệu kia trước.'
			);
		}
		if ( KHTC_Khoa::ngay( $cty ) ) {
			return new WP_Error( 'khoa', 'Sổ đang khoá đến hết ' . mysql2date( 'd/m/Y', KHTC_Khoa::ngay( $cty ) ) . '. Mở khoá ở mục Nhật ký rồi nạp lại.' );
		}

		// Cả lượt nạp chỉ ghi MỘT dòng nhật ký, không phải vài trăm dòng.
		KHTC_NhatKy::mo_lo();
		$id = array_fill_keys( self::BANG, array() );

		// --- hai tài khoản ngân hàng, mốc tính từ 13 tháng trước
		list( $moc ) = KHTC_BaoCao::bien_thang( self::thang( 13 ) );
		$vcb = KHTC_NganHang::them( array( 'ten' => '[Mẫu] Vietcombank 0071000123456', 'so_tk' => '0071000123456', 'so_du_dau' => 180000000, 'ngay_dau' => $moc ) );
		$mb  = KHTC_NganHang::them( array( 'ten' => '[Mẫu] MB Bank 8899669988', 'so_tk' => '8899669988', 'so_du_dau' => 45000000, 'ngay_dau' => $moc ) );
		$id['ngan_hang'] = array( $vcb, $mb );

		// --- danh mục điểm: ba điểm, mỗi điểm hai mã cửa hàng như hai máy POS.
		// Có nó thì màn hình Sinh hoá đơn mới chạy ra kết quả; thiếu nó thì bộ
		// dữ liệu mẫu bỏ trống đúng hai màn hình mới nhất.
		$diem = array(
			array( 'MAU-CRE-01', '[Mẫu] Crescent máy 1', 'Crescent Mall', 'KVC CRESCENT', 'HCM', 'KVC' ),
			array( 'MAU-CRE-02', '[Mẫu] Crescent máy 2', 'Crescent Mall', 'KVC CRESCENT', 'HCM', 'KVC' ),
			array( 'MAU-VIN-01', '[Mẫu] Vincom máy 1',   'Vincom Đồng Khởi', 'GM VINCOM', 'HCM', 'GM' ),
			array( 'MAU-ROY-01', '[Mẫu] Royal máy 1',    'Royal City',    'KVC ROYAL',  'Hà Nội', 'KVC' ),
			array( 'MAU-TEST',   '[Mẫu] Máy chạy thử',   'Máy chạy thử',  '',           '', '' ),
		);
		foreach ( $diem as $k => $d ) {
			KHTC_Diem::them(
				array(
					'ma_cua_hang' => $d[0],
					'ten_gian'    => $d[1],
					'ten_diem'    => $d[2],
					'ma_misa'     => $d[3],
					'khu_vuc'     => $d[4],
					'dich_vu'     => $d[5],
					// Một điểm đặt sẵn cờ bỏ qua, để người xem thấy cờ đó làm gì.
					'bo_qua'      => 'MAU-TEST' === $d[0] ? 1 : 0,
				),
				false
			);
		}
		self::gom_id( $id, 'diem' );

		// --- 13 tháng sao kê, có mùa vụ để bảng xu hướng nói lên điều gì đó
		$sao_ke = '';
		for ( $i = 12; $i >= 0; $i-- ) {
			$mua = 1 + 0.42 * sin( ( 12 - $i ) / 2.1 );
			// Cột 5 là mã giao dịch (chặn trùng khi dán lại), cột 6 là mã cửa
			// hàng (nối sang danh mục điểm để gom thành hoá đơn).
			$sao_ke .= sprintf( "%s\tDoanh thu QR Crescent\t%d\tThu\tMAU-T%02d-A\tMAU-CRE-01\n", self::ngay( $i, 5 ), round( 61000000 * $mua ), $i );
			$sao_ke .= sprintf( "%s\tDoanh thu QR Crescent may 2\t%d\tThu\tMAU-T%02d-B\tMAU-CRE-02\n", self::ngay( $i, 6 ), round( 57000000 * $mua ), $i );
			$sao_ke .= sprintf( "%s\tDoanh thu QR Vincom\t%d\tThu\tMAU-T%02d-C\tMAU-VIN-01\n", self::ngay( $i, 18 ), round( 27000000 * $mua ), $i );
			$sao_ke .= sprintf( "%s\tDoanh thu QR Royal City\t%d\tThu\tMAU-T%02d-D\tMAU-ROY-01\n", self::ngay( $i, 19 ), round( 14000000 * $mua ), $i );
			$sao_ke .= sprintf( "%s\tChi luong va van hanh\t-%d\tChi\n", self::ngay( $i, 20 ), round( 96000000 + 8000000 * cos( ( 12 - $i ) / 1.7 ) ) );
			$sao_ke .= sprintf( "%s\tChi thue mat bang\t-35.000.000\tChi\n", self::ngay( $i, 25 ) );
		}
		// Ba dòng của tháng này khớp đúng chứng từ bên dưới, để đối soát ra kết quả thật.
		$sao_ke .= sprintf( "%s\tTT tien dien EVN HCMC\t-19.872.000\tChi\t00012345\n", self::ngay( 0, 8 ) );
		$sao_ke .= sprintf( "%s\tTT tien nuoc SAWACO\t-3.360.000\tChi\t00088120\n", self::ngay( 0, 10 ) );
		$sao_ke .= sprintf( "%s\tCTY ABC thanh toan HD002\t%d\tThu\n", self::ngay( 0, 22 ), 41040000 );
		KHTC_GiaoDich::dan_hang_loat( $vcb, $sao_ke );
		KHTC_GiaoDich::dan_hang_loat( $mb, sprintf( "%s\tVNPay chuyen ve\t12.450.000\tThu\n%s\tPhi quan ly tai khoan\t-110.000\tChi\n", self::ngay( 0, 12 ), self::ngay( 0, 28 ) ) );
		self::gom_id( $id, 'giao_dich' );

		// --- hoá đơn đầu ra, mã điểm trùng hợp đồng bên dưới
		$ra = array(
			array( self::ngay( 0, 5 ),  '[M]HD001', 'CONG TY TNHH THUONG MAI ABC', '0301234567', '96.000.000', '8', 'HCM', 'KVC', 'KVC-CRESCENT' ),
			array( self::ngay( 0, 12 ), '[M]HD002', 'CONG TY CP DICH VU XYZ', '0309876543', '38.000.000', '8', 'HCM', 'GM', 'GM-VINCOM' ),
			array( self::ngay( 0, 18 ), '[M]HD003', 'CONG TY CP DU LICH DEF', '0311112222', '24.500.000', '8', 'HN', 'KVC', 'KVC-ROYAL' ),
			array( self::ngay( 0, 24 ), '[M]HD004', 'Khach le', '', '11.200.000', '0', 'HCM', 'KVC', 'KVC-CRESCENT' ),
			array( self::ngay( 1, 8 ),  '[M]HD005', 'CONG TY TNHH THUONG MAI ABC', '0301234567', '88.000.000', '8', 'HCM', 'KVC', 'KVC-CRESCENT' ),
		);
		foreach ( $ra as $r ) {
			$n = KHTC_HoaDonRa::them( array( 'ngay' => $r[0], 'so_hd' => $r[1], 'khach' => $r[2], 'mst' => $r[3], 'noi_dung' => 'Dich vu trong thang', 'chua_vat' => $r[4], 'thue_suat' => $r[5], 'khu_vuc' => $r[6], 'dich_vu' => $r[7], 'ma_diem' => $r[8] ) );
			if ( is_int( $n ) ) { $id['hd_ra'][] = $n; }
		}

		// --- hoá đơn đầu vào, một cái tiền mặt vượt ngưỡng để thấy cảnh báo khấu trừ
		$vao = array(
			array( self::ngay( 0, 8 ),  '00012345', 'EVN HCMC', '0300942001', 'Tien dien thang truoc', '18.400.000', '8', 'chuyen_khoan' ),
			array( self::ngay( 0, 10 ), '00088120', 'SAWACO', '0300476888', 'Tien nuoc thang truoc', '3.200.000', '5', 'chuyen_khoan' ),
			array( self::ngay( 0, 15 ), '00045002', 'CTY QUANG CAO MEDIA', '0305556666', 'Quang cao thang nay', '26.000.000', '8', 'chuyen_khoan' ),
			array( self::ngay( 0, 20 ), 'TM00918', 'Cua hang VLXD Minh Long', '0312345678', 'Vat tu sua chua', '24.000.000', '8', 'tien_mat' ),
		);
		foreach ( $vao as $r ) {
			$n = KHTC_HoaDonVao::them( array( 'ngay' => $r[0], 'so_hd' => $r[1], 'nha_cung_cap' => $r[2], 'mst' => $r[3], 'noi_dung' => $r[4], 'chua_vat' => $r[5], 'thue_suat' => $r[6], 'hinh_thuc' => $r[7] ) );
			if ( is_int( $n ) ) { $id['hd_vao'][] = $n; }
		}

		// --- chi phí, số chứng từ trùng hoá đơn đầu vào và trùng sao kê
		KHTC_ChiPhi::dan_hang_loat(
				sprintf( "%s\tKhu vui chơi\tTiền điện\tEVN HCMC\t19.872.000\t00012345\n", self::ngay( 0, 8 ) )
				. sprintf( "%s\tKhu vui chơi\tTiền nước\tSAWACO\t3.360.000\t00088120\n", self::ngay( 0, 10 ) )
				. sprintf( "%s\tVăn phòng\tMarketing\tCTY QUANG CAO MEDIA\t28.080.000\t00045002\n", self::ngay( 0, 15 ) )
				. sprintf( "%s\tKhu vui chơi\tLương\tBang luong thang nay\t96.000.000\n", self::ngay( 0, 20 ) )
				. sprintf( "%s\tKhu vui chơi\tThuê mặt bằng\tCTY BDS An Phu\t35.000.000\n", self::ngay( 0, 25 ) )
				. sprintf( "%s\tGhế massage\tSửa chữa — bảo trì\tCty Thanh Dat\t6.400.000\n", self::ngay( 0, 26 ) ),
			$vcb
		);
		self::gom_id( $id, 'chi_phi' );

		// --- một đợt đối soát cổng, cố tình để một dòng chưa về
		//
		// Kỳ đối soát để HẸP quanh mấy ngày cổng chốt tiền, không phủ cả tháng.
		// Phủ cả tháng thì mọi khoản thu khác trong tháng — tiền mặt, kênh khác
		// — đều rơi vào nhóm "Thừa", đúng về mặt định nghĩa nhưng người xem thử
		// sẽ tưởng phần mềm báo sai.
		list( $k1, $k2 ) = KHTC_BaoCao::bien_thang( self::thang( 0 ) );
		$ds1 = KHTC_GiaoDich::doc_ngay( self::ngay( 0, 21 ) );
		$ds2 = KHTC_GiaoDich::doc_ngay( self::ngay( 0, 24 ) );
		$dot = KHTC_DoiSoat::tao_dot( array( 'ten' => '[Mẫu] Payoo đợt cuối tháng', 'kenh' => 'payoo', 'ngan_hang_id' => $vcb, 'tu' => $ds1, 'den' => $ds2 ) );
		if ( is_int( $dot ) ) {
			$id['doi_soat'][] = $dot;
			KHTC_DoiSoat::nap_dong(
				$dot,
				sprintf( "%s\tPAY9001\t41.500.000\t460.000\tThanh toan QR\n", self::ngay( 0, 22 ) )
				. sprintf( "%s\tPAY9002\t750.000\t8.325\tQR chua ve\n", self::ngay( 0, 23 ) )
			);
			KHTC_DoiSoat::chay( $dot );
			$id['ds_dong'] = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE dot_id = %d', $dot ) );
		}

		// --- hợp đồng: một cái sắp hết hạn, một cái đã quá hạn, hai cái chia sẻ
		$hop = array(
			array( 'KVC Crescent Mall — Gian A1-05', 'CTY CP DAU TU CRESCENT', '0301234567', 'HCM', 'KVC-CRESCENT', 'Thuê cố định', '35.000.000', 12, 0, 'https://vi.du/hd-crescent.pdf' ),
			array( 'GM Vincom Đồng Khởi — Tầng B1', 'CTY CP VINCOM RETAIL', '0309876543', 'HCM', 'GM-VINCOM', 'Chia sẻ doanh thu', '0', -18, 18, '' ),
			array( 'KVC Royal City — Gian C3-02', 'CTY CP VINHOMES', '0312223344', 'HN', 'KVC-ROYAL', 'Chia sẻ doanh thu', '0', 300, 12, 'https://vi.du/hd-royal.pdf' ),
			array( 'GM Big C Thăng Long', 'CTY TNHH BIG C', '0313334444', 'HN', '', 'Thuê cố định', '16.500.000', 55, 0, '' ),
		);
		$hom_nay = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00 UTC' );
		foreach ( $hop as $h ) {
			$n = KHTC_PhapDanh::them(
				array(
					'loai' => 'thue', 'gian' => '[Mẫu] ' . $h[0], 'doi_tac' => $h[1], 'mst' => $h[2],
					'khu_vuc' => $h[3], 'ma_diem' => $h[4], 'hinh_thuc' => $h[5], 'gia_tri' => $h[6],
					'ngay_bat_dau' => gmdate( 'd/m/Y', $hom_nay - 300 * DAY_IN_SECONDS ),
					'ngay_het_han' => gmdate( 'd/m/Y', $hom_nay + $h[7] * DAY_IN_SECONDS ),
					'phan_tram' => $h[8], 'link_du_dau' => $h[9],
				)
			);
			if ( is_int( $n ) ) { $id['hop_dong'][] = $n; }
		}
		$n = KHTC_PhapDanh::them( array( 'loai' => 'ncc', 'doi_tac' => '[Mẫu] CTY BAO TRI THANH DAT', 'mst' => '0303334444', 'so_hd' => 'HD-BT-01', 'noi_dung' => 'Bao tri may lanh ca nam', 'gia_tri' => '120.000.000', 'ngay_ky' => self::ngay( 6, 5 ), 'ngay_het_han' => gmdate( 'd/m/Y', $hom_nay + 40 * DAY_IN_SECONDS ) ) );
		if ( is_int( $n ) ) { $id['hop_dong'][] = $n; }

		// --- hồ sơ: hai cái nối được, một cái đánh dấu hạch toán mà chưa nối
		KHTC_HoSo::dan_hang_loat(
				sprintf( "Hoá đơn đầu vào\t00012345\t%s\tEVN HCMC\t19.872.000\thd-evn.pdf\thttps://vi.du/f/1\n", self::ngay( 0, 8 ) )
				. sprintf( "Hoá đơn đầu vào\t00088120\t%s\tSAWACO\t3.360.000\thd-sawaco.pdf\thttps://vi.du/f/2\n", self::ngay( 0, 10 ) )
				. sprintf( "Uỷ nhiệm chi\tUNC-01\t%s\tVietcombank\t28.080.000\tunc.pdf\thttps://vi.du/f/3\n", self::ngay( 0, 16 ) ),
		);
		self::gom_id( $id, 'ho_so' );
		KHTC_HoSo::do_noi();

		// --- chạy các phép ghép để mọi màn hình đối soát có kết quả thật
		KHTC_ChiPhi::doi_soat( $k1, $k2, $vcb );
		KHTC_CongNo::tu_ghep( 'thu', $k1, $k2, $vcb );
		KHTC_CongNo::tu_ghep( 'tra', $k1, $k2, $vcb );
		$id['thanh_toan'] = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang( 'thanh_toan' ) . ' WHERE cty = %s', $cty ) );

		foreach ( $id as $t => $v ) { $id[ $t ] = array_values( array_unique( array_map( 'intval', $v ) ) ); }
		update_option( self::khoa( $cty ), $id );

		KHTC_NhatKy::dong_lo();
		$tong = array_sum( array_map( 'count', $id ) );
		KHTC_NhatKy::ghi( 'nap', '', 0, sprintf( 'Nạp dữ liệu mẫu để chạy thử — %d dòng', $tong ) );
		return $tong;
	}

	/**
	 * Gom id của những dòng vừa tạo. Các hàm dán hàng loạt chỉ trả về SỐ LƯỢNG
	 * chứ không trả id, nên phải hỏi lại cơ sở dữ liệu.
	 *
	 * Quét cả bảng theo pháp nhân là đủ an toàn vì nap() đã từ chối chạy khi
	 * pháp nhân còn dữ liệu không phải mẫu — mọi dòng đang có đều là của lượt
	 * nạp này.
	 */
	private static function gom_id( &$id, $bang ) {
		global $wpdb;
		$id[ $bang ] = array_merge(
			$id[ $bang ],
			$wpdb->get_col(
				$wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang( $bang ) . ' WHERE cty = %s', KHTC_Cty::dang_chon() )
			)
		);
	}

	// ------------------------------------------------------------------ xoá

	/**
	 * Xoá đúng những dòng đã tạo, không phải xoá sạch bảng.
	 *
	 * Nếu người dùng đã nhập dữ liệu thật xen vào rồi mới bấm xoá mẫu, dữ liệu
	 * thật phải còn nguyên. "TRUNCATE cho nhanh" ở đây là mất sổ của người ta.
	 */
	public static function xoa() {
		global $wpdb;
		$cty = KHTC_Cty::dang_chon();
		$da  = self::da_tao( $cty );
		$so  = 0;
		foreach ( self::BANG as $t ) {
			$ids = array_map( 'intval', $da[ $t ] ?? array() );
			if ( ! $ids ) { continue; }
			$so += (int) $wpdb->query(
				'DELETE FROM ' . KHTC_DB::bang( $t ) . ' WHERE id IN (' . implode( ',', $ids ) . ')'
			);
		}
		// Chạy lại đối soát sau khi nạp mẫu sinh thêm dòng thanh toán TỰ ĐỘNG mà
		// lượt nạp không biết. Bỏ chúng lại thì bảng thanh toán còn rác, và lần
		// nạp mẫu sau bị chặn vì tưởng đó là sổ thật.
		$tt = KHTC_DB::bang( 'thanh_toan' );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $tt WHERE cty = %s
				 AND ( ( bang = 'hd_ra' AND chung_tu_id NOT IN ( SELECT id FROM " . KHTC_DB::bang( 'hd_ra' ) . ' ) )
				    OR ( bang = \'chi_phi\' AND chung_tu_id NOT IN ( SELECT id FROM ' . KHTC_DB::bang( 'chi_phi' ) . " ) ) )",
				$cty
			)
		);

		delete_option( self::khoa( $cty ) );
		KHTC_NhatKy::ghi( 'xoa', '', 0, sprintf( 'Xoá dữ liệu mẫu — %d dòng', $so ) );
		return $so;
	}
}
