<?php
/**
 * QUÉT SỨC KHOẺ DÂY CHUYỀN — soi chéo mọi khâu đã dựng, tìm chỗ SỔ VẪN CÂN MÀ TIỀN VẪN SAI.
 *
 * =================================================================================================
 * 🔴 ĐÂY LÀ MÀN DUY NHẤT BẮT ĐƯỢC SAI SÓT MÀ BẢNG CÂN ĐỐI KHÔNG THẤY
 * =================================================================================================
 * Mọi bút toán JP sinh ra đều ĐỦ HAI VẾ theo cấu tạo — `VHJP_ButToan::sinh()` dựng cặp Nợ/Có từ
 * cùng một con số. Nên "sổ cân" gần như KHÔNG chứng minh được gì: doanh thu thiếu giá vốn vẫn cân,
 * giá vốn ghi sai đầu tài khoản vẫn cân, và một kỳ bị đếm hai lần thì cả doanh thu lẫn giá vốn
 * cùng nhân đôi nên lại càng cân. Sáu phép dưới đây đi tìm đúng những chỗ ấy.
 *
 * =================================================================================================
 * 🔴 QUÉT KHÔNG SỬA GÌ
 * =================================================================================================
 * Hàm này chỉ ĐỌC. Mọi đường sửa (xuất kho lại · đổi đầu tài khoản · sắp lại kỳ) đều là nút riêng,
 * do người bấm sau khi đọc số. Quét mà tự sửa là kế toán mở màn xem tình hình rồi phát hiện sổ đã
 * bị thay đổi sau lưng.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Quet {

	/** In tối đa ngần này dòng chi tiết cho mỗi phiếu thiếu lớp — phần còn lại chỉ đếm. */
	const TOI_DA_DONG = 10;

	/** Lệch dưới mức này coi như bằng nhau: tiền lưu 2 số lẻ, so bằng `!==` là luôn báo lệch. */
	const NGUONG = 0.5;

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Việc này cần tài khoản kế toán' ); }
	}

	private static function khoang( $thang, $nam ) {
		$thang = (int) $thang; $nam = (int) $nam;
		if ( $thang < 1 || $thang > 12 || $nam < 2000 ) {
			throw new Exception( 'Tháng/năm không hợp lệ' );
		}
		$tu = sprintf( '%04d-%02d-01', $nam, $thang );
		return array( $tu, gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) ), $thang, $nam );
	}

	/**
	 * `jpQuetDayChuyen` — sáu phép soi chéo trên các báo cáo của một tháng.
	 *
	 * ① Có hàng bán mà KHÔNG có dòng giá vốn nào  → lãi gộp của kỳ sai toàn bộ.
	 * ② Dòng kho còn cờ `thieuLop`                → giá vốn đang là số TẠM.
	 * ③ Số ở đầu báo cáo không khớp khi tính lại   → ai đó sửa tay thẳng vào bảng.
	 * ④ Dòng kho còn mang tài khoản của khối khác  → giá vốn JP ghi lấn sang kho ăn uống.
	 * ⑥ Hai kỳ chồng nhau                          → tiền có thể bị tính HAI LẦN.
	 *
	 * ⚠️ ①③⑥ cắt theo THÁNG ĐANG CHỌN; ② cũng vậy (theo ngày phiếu xuất). Riêng ④ là TOÀN SỔ,
	 *    vì nút sửa của nó (`jpDoiTkKhoCu`) cũng đổi toàn sổ — in một con số hẹp hơn nút bấm là
	 *    kế toán bấm xong thấy đổi nhiều hơn số vừa đọc.
	 */
	public static function quet( $u, $thang, $nam ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );

		$bc_ky = array();          /* báo cáo HOÀN TẤT có ngày cuối kỳ trong tháng */
		$moi    = array();         /* mọi báo cáo đã nộp (để soi kỳ chồng nhau)    */
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_NHAP === VHJP_Doc::str( $r['status'] ) ) { continue; }
			$moi[] = $r;
			$ngay = VHJP_Doc::ngay( $r['toDate'] );
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			if ( '' === $ngay || $ngay < $tu || $ngay > $den ) { continue; }
			$bc_ky[] = $r;
		}

		$mot = self::thieu_gia_von( $bc_ky );
		$hai = self::thieu_lop( $tu, $den );
		$ba  = self::lech_tinh_lai( $bc_ky );
		$bon = VHJP_ButToan::doi_tk_kho_cu( $u, false );
		$sau = self::ky_chong( $moi, $tu, $den );

		$so_van_de = $mot['so'] + $hai['soLo'] + $ba['so']
			+ (int) ( isset( $bon['soDong'] ) ? $bon['soDong'] : 0 ) + $sau['so'];

		return array(
			'ok' => true, 'thang' => $thang, 'nam' => $nam, 'tuNgay' => $tu, 'denNgay' => $den,
			'soBaoCao' => count( $bc_ky ),
			'soVanDe'  => $so_van_de,
			'sach'     => 0 === $so_van_de,

			'soThieuGiaVon'   => $mot['so'],
			'tienThieuGiaVon' => $mot['tien'],
			'thieuGiaVon'     => $mot['ds'],

			'soLoThieuLop'   => $hai['soLo'],
			'soDongThieuLop' => $hai['soDong'],
			'tienThieuLop'   => $hai['tien'],
			'thieuLop'       => $hai['ds'],

			'soLechTinhLai' => $ba['so'],
			'lechTinhLai'   => $ba['ds'],

			'soDongTkCu' => isset( $bon['soDong'] ) ? $bon['soDong'] : 0,
			'tienTkCu'   => isset( $bon['soTien'] ) ? $bon['soTien'] : 0,
			'tkCu'       => isset( $bon['nhom'] ) ? $bon['nhom'] : array(),

			'soKyChong'     => $sau['so'],
			'soKyChongTien' => $sau['soTien'],
			'tienKyChong'   => $sau['tien'],
			'kyChong'       => $sau['ds'],
		);
	}

	/* ══════════════════════════════════════════════ ① BÁN HÀNG MÀ KHÔNG CÓ GIÁ VỐN ═══════ */

	/**
	 * Báo cáo đã HOÀN TẤT, có hàng bán, mà `JP_KhoXuat` không có lấy một dòng.
	 *
	 * 🔴 KHÔNG ĐO BẰNG `revHang`. Mẫu chung không có bảng hàng riêng nên `revHang` bằng 0 ở gần
	 *    hết hệ thống; đo bằng nó là bỏ sót đúng những báo cáo đông nhất. Đo bằng Σ `soldQty`
	 *    của các dòng CÓ HÀNG — đó chính là con số mà `VHJP_Kho::xuat_bao_cao()` dùng để trừ kho,
	 *    nên "có số này mà không có dòng kho" là định nghĩa CHÍNH XÁC của lượt ghi sổ kho đã hỏng.
	 */
	private static function thieu_gia_von( $bc ) {
		$ds = array(); $tien = 0;
		foreach ( $bc as $r ) {
			$ma = VHJP_Doc::str( $r['id'] );
			$sl = 0;
			foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma ) as $x ) {
				if ( ! in_array( VHJP_Doc::str( $x['rowKind'] ), VHJP_Kho::DONG_CO_HANG, true ) ) {
					continue;
				}
				$q = self::so( $x['soldQty'] );
				if ( $q > 0 ) { $sl += $q; }
			}
			if ( $sl <= 0 ) { continue; }
			if ( VHJP_Nguon::tim( 'JP_KhoXuat', 'reportId', $ma ) ) { continue; }

			$dt = self::so( $r['revMeter'] );
			$ds[] = array( 'id' => $ma, 'coSo' => VHJP_Doc::str( $r['locationName'] ),
				'kyTu' => VHJP_Doc::ngay( $r['fromDate'] ),
				'kyDen' => VHJP_Doc::ngay( $r['toDate'] ),
				'soLuongBan' => $sl, 'doanhThu' => $dt,
				'nguoiKy' => VHJP_Doc::str( $r['apprBy'] ) );
			$tien += $dt;
		}
		return array( 'so' => count( $ds ), 'tien' => $tien, 'ds' => $ds );
	}

	/* ══════════════════════════════════════════════════ ② GIÁ VỐN ĐANG LÀ SỐ TẠM ═════════ */

	/** Dòng kho còn cờ `thieuLop`, gom theo phiếu. */
	private static function thieu_lop( $tu, $den ) {
		$nhom = array(); $so_dong = 0; $tien = 0;
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			if ( ! self::so( $x['thieuLop'] ) ) { continue; }
			$ngay = VHJP_Doc::ngay( $x['ngay'] );
			if ( '' === $ngay || $ngay < $tu || $ngay > $den ) { continue; }

			$bc = VHJP_Doc::str( $x['reportId'] );
			$ct = VHJP_Doc::str( $x['soChungTu'] );
			$k  = '' !== $bc ? 'BC|' . $bc : 'CT|' . $ct;
			if ( ! isset( $nhom[ $k ] ) ) {
				$nhom[ $k ] = array( 'reportId' => $bc, 'soChungTu' => $ct,
					'coSo' => VHJP_Doc::str( $x['locationName'] ), 'ngay' => $ngay,
					'soDong' => 0, 'tienTam' => 0, 'dong' => array() );
			}
			$sl = self::so( $x['qty'] );
			$gt = self::so( $x['amount'] );
			$nhom[ $k ]['soDong']++;
			$nhom[ $k ]['tienTam'] += $gt;
			/* Chỉ giữ mấy dòng đầu: một phiếu 400 dòng in hết là trang treo, mà người đọc cũng
			   chỉ cần biết phiếu nào và bao nhiêu tiền — `soDong` vẫn đếm đủ. */
			if ( count( $nhom[ $k ]['dong'] ) < self::TOI_DA_DONG ) {
				$nhom[ $k ]['dong'][] = array( 'itemCode' => VHJP_Doc::str( $x['itemCode'] ),
					'itemName' => VHJP_Doc::str( $x['itemName'] ),
					'qty' => $sl, 'unitCost' => self::so( $x['unitCost'] ), 'amount' => $gt );
			}
			$so_dong++;
			$tien += $gt;
		}
		$ds = array_values( $nhom );
		usort( $ds, function ( $a, $b ) { return strcmp( $b['ngay'], $a['ngay'] ); } );
		return array( 'soLo' => count( $ds ), 'soDong' => $so_dong, 'tien' => $tien, 'ds' => $ds );
	}

	/* ══════════════════════════════════════════ ③ SỐ Ở ĐẦU BÁO CÁO BỊ SỬA TAY ════════════ */

	/** Những ô đem so, kèm tên in ra cho người đọc. */
	private static function o_so_sanh() {
		return array(
			'revMeter'     => 'Doanh thu đồng hồ',
			'revBank'      => 'Tiền vào tài khoản',
			'revCashMeter' => 'Tiền mặt theo đồng hồ',
			'adjMachine'   => 'Lệch tiền máy',
			'cashActual'   => 'Tiền mặt thực nộp',
			'totalSubmit'  => 'Tổng phải nộp',
		);
	}

	/**
	 * Tính lại đầu báo cáo từ các dòng, rồi đem so với số đang lưu.
	 *
	 * 🔴 ĐI ĐÚNG ĐƯỜNG TÍNH CỦA `VHJP_BaoCao::luu()` — `VHJP_Tinh::dong()` cho từng dòng rồi
	 *    `VHJP_Tinh::bao_cao()` cho đầu. Tự cộng lấy ở đây là dựng ĐƯỜNG TÍNH THỨ HAI, và ngày
	 *    nó lệch với đường thật thì màn này báo động giả hàng loạt, rồi không ai tin nó nữa.
	 *
	 * ⚠️ KHÔNG ghi gì trở lại. Số đúng là số tính lại, nhưng ghi đè ở đây là sửa báo cáo đã có
	 *    chữ ký mà không ai bấm nút nào.
	 */
	private static function lech_tinh_lai( $bc ) {
		$o_ten = self::o_so_sanh();
		$ds = array();
		foreach ( $bc as $r ) {
			$ma   = VHJP_Doc::str( $r['id'] );
			$loai = VHJP_Doc::str( $r['machineType'] );
			$dong = array();
			foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma ) as $x ) {
				$dong[] = VHJP_Tinh::dong( $x, $loai );
			}
			if ( ! $dong ) { continue; }          /* không dòng nào thì không có gì để tính lại */

			$moi = VHJP_Tinh::bao_cao( $r, $dong );
			$o   = array();
			foreach ( $o_ten as $cot => $ten ) {
				$luu = self::so( isset( $r[ $cot ] ) ? $r[ $cot ] : 0 );
				$lai = self::so( isset( $moi[ $cot ] ) ? $moi[ $cot ] : 0 );
				if ( abs( $luu - $lai ) < self::NGUONG ) { continue; }
				$o[] = array( 'ten' => $ten, 'luu' => $luu, 'lai' => $lai );
			}
			if ( ! $o ) { continue; }
			$ds[] = array( 'id' => $ma, 'coSo' => VHJP_Doc::str( $r['locationName'] ),
				'kyTu' => VHJP_Doc::ngay( $r['fromDate'] ),
				'kyDen' => VHJP_Doc::ngay( $r['toDate'] ),
				'soDong' => count( $dong ), 'o' => $o );
		}
		return array( 'so' => count( $ds ), 'ds' => $ds );
	}

	/* ═════════════════════════════════════════════════════════ ⑥ HAI KỲ CHỒNG NHAU ═══════ */

	/**
	 * Hai báo cáo cùng cơ sở, cùng loại máy, có khoảng ngày giao nhau.
	 *
	 * =============================================================================================
	 * 🔴 CHỒNG NGÀY VÀ CHỒNG CHỈ SỐ LÀ HAI CHUYỆN KHÁC HẲN NHAU — PHẢI TÁCH
	 * =============================================================================================
	 * Chồng NGÀY mà chỉ số đồng hồ nối tiếp nhau thì tiền KHÔNG bị đếm hai lần: cái sai chỉ là
	 * nhãn kỳ. Chồng CHỈ SỐ mới là tiền vào sổ hai lượt. Gộp một câu thì hoặc báo động giả (kế
	 * toán đi sửa một thứ không sai), hoặc giấu mất khoản thật (một cặp chồng chỉ số lẫn giữa mười
	 * cặp chỉ lệch nhãn). Nên `tienTrung` được tính RIÊNG cho từng cặp, từ chính chỉ số đồng hồ.
	 *
	 * ⚠️ Đếm cả báo cáo CHƯA duyệt. Chưa duyệt thì chưa vào sổ — nhưng nó SẼ vào khi kế toán bấm
	 *    duyệt, và lúc ấy không còn ai nhìn màn này nữa. Chặn trước rẻ hơn gỡ sau.
	 */
	private static function ky_chong( $bc, $tu, $den ) {
		$nhom = array();
		foreach ( $bc as $r ) {
			$a = VHJP_Doc::ngay( $r['fromDate'] );
			$b = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $a || '' === $b ) { continue; }
			/* Cặp chỉ đáng nhắc khi nó dính vào tháng đang xem — không thì mở tháng nào cũng
			   thấy y một danh sách của cả năm. */
			if ( $b < $tu || $a > $den ) { continue; }
			$k = VHJP_Doc::str( $r['locationId'] ) . '|' . VHJP_Doc::str( $r['machineType'] );
			if ( ! isset( $nhom[ $k ] ) ) { $nhom[ $k ] = array(); }
			$nhom[ $k ][] = $r;
		}

		$ds = array(); $tien = 0; $so_tien = 0;
		foreach ( $nhom as $ds_bc ) {
			$n = count( $ds_bc );
			for ( $i = 0; $i < $n; $i++ ) {
				for ( $j = $i + 1; $j < $n; $j++ ) {
					$a = $ds_bc[ $i ];
					$b = $ds_bc[ $j ];
					$a1 = VHJP_Doc::ngay( $a['fromDate'] ); $a2 = VHJP_Doc::ngay( $a['toDate'] );
					$b1 = VHJP_Doc::ngay( $b['fromDate'] ); $b2 = VHJP_Doc::ngay( $b['toDate'] );
					$c1 = $a1 > $b1 ? $a1 : $b1;
					$c2 = $a2 < $b2 ? $a2 : $b2;
					if ( $c1 > $c2 ) { continue; }        /* không giao nhau ngày nào */

					$m = self::chi_so_trung( $a, $b );
					if ( $m['tien'] > 0 ) { $so_tien++; $tien += $m['tien']; }
					$ds[] = array(
						'locationId' => VHJP_Doc::str( $a['locationId'] ),
						'locationName' => VHJP_Doc::str( $a['locationName'] ),
						'machineType' => VHJP_Doc::str( $a['machineType'] ),
						'a' => array( 'id' => VHJP_Doc::str( $a['id'] ),
							'tu' => VHJP_Doc::dmy( $a1 ), 'den' => VHJP_Doc::dmy( $a2 ),
							'status' => VHJP_Doc::str( $a['status'] ) ),
						'b' => array( 'id' => VHJP_Doc::str( $b['id'] ),
							'tu' => VHJP_Doc::dmy( $b1 ), 'den' => VHJP_Doc::dmy( $b2 ),
							'status' => VHJP_Doc::str( $b['status'] ) ),
						'chongTu' => VHJP_Doc::dmy( $c1 ), 'chongDen' => VHJP_Doc::dmy( $c2 ),
						'soOTrung' => $m['so'], 'tienTrung' => $m['tien'] );
				}
			}
		}
		usort( $ds, function ( $x, $y ) {
			/* Cặp có tiền bị tính hai lần lên trước — đó là cặp phải sửa hôm nay. */
			if ( $x['tienTrung'] !== $y['tienTrung'] ) { return $y['tienTrung'] > $x['tienTrung'] ? 1 : -1; }
			return strcmp( $x['locationName'], $y['locationName'] );
		} );
		return array( 'so' => count( $ds ), 'soTien' => $so_tien, 'tien' => $tien, 'ds' => $ds );
	}

	/**
	 * Hai báo cáo có bao nhiêu Ô MÁY khai trùng chỉ số, và trùng bao nhiêu tiền.
	 *
	 * ⚠️ So theo TỪNG Ô MÁY, không cộng gộp cả báo cáo. Hai kỳ có thể cùng tổng chỉ số mà không
	 *    ô nào trùng (máy này chạy bù máy kia) — cộng gộp là báo nhầm; và ngược lại, một ô trùng
	 *    nằm giữa mười ô nối tiếp thì cộng gộp làm nó biến mất.
	 *
	 * ⚠️ Khoảng chỉ số là NỬA MỞ `[mBefore, mAfter)`: kỳ trước chốt ở 100 và kỳ sau mở ở 100 là
	 *    NỐI TIẾP, không phải chồng. Đo bằng khoảng đóng thì mọi cặp kỳ liền nhau đều bị báo.
	 */
	private static function chi_so_trung( $a, $b ) {
		$ma = self::o_may( VHJP_Doc::str( $a['id'] ) );
		$mb = self::o_may( VHJP_Doc::str( $b['id'] ) );
		$so = 0; $tien = 0;
		foreach ( $ma as $k => $x ) {
			if ( ! isset( $mb[ $k ] ) ) { continue; }
			$y  = $mb[ $k ];
			$t1 = max( $x['tu'], $y['tu'] );
			$t2 = min( $x['den'], $y['den'] );
			if ( $t2 <= $t1 ) { continue; }
			$so++;
			$gia   = $x['gia'] > 0 ? $x['gia'] : $y['gia'];
			$tien += ( $t2 - $t1 ) * $gia;
		}
		return array( 'so' => $so, 'tien' => $tien );
	}

	/** Chỉ số đồng hồ của từng ô máy trong một báo cáo: `ma ô => tu · den · gia`. */
	private static function o_may( $ma_bc ) {
		$ra = array();
		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc ) as $r ) {
			$k = VHJP_Doc::str( $r['machineId'] );
			if ( '' === $k ) { $k = VHJP_Doc::str( $r['machineCode'] ); }
			if ( '' === $k ) { continue; }
			$t = self::so( $r['mBefore'] );
			$d = self::so( $r['mAfter'] );
			if ( $d <= $t ) { continue; }         /* chưa gõ, hoặc đồng hồ không nhích */
			/* Cùng một ô máy hiện hai dòng (mẫu tách) thì lấy khoảng RỘNG NHẤT — hẹp lại là
			   bỏ sót phần chồng. */
			if ( isset( $ra[ $k ] ) ) {
				$ra[ $k ]['tu']  = min( $ra[ $k ]['tu'], $t );
				$ra[ $k ]['den'] = max( $ra[ $k ]['den'], $d );
				if ( $ra[ $k ]['gia'] <= 0 ) { $ra[ $k ]['gia'] = self::so( $r['price'] ); }
				continue;
			}
			$ra[ $k ] = array( 'tu' => $t, 'den' => $d, 'gia' => self::so( $r['price'] ) );
		}
		return $ra;
	}
}
