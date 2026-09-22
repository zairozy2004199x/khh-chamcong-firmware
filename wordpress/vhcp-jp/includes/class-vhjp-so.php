<?php
/**
 * SỔ KẾ TOÁN — `JP2_11_SoSach.gs`.
 *
 * =================================================================================================
 * SỔ CHỈ ĐỌC, KHÔNG BAO GIỜ GHI
 * =================================================================================================
 * Mọi hàm ở đây dựng lại một bảng TỪ chứng từ đã có. Không hàm nào tạo, sửa hay xoá một dòng nào.
 * Đó là ranh giới cố ý: sổ mà tự ghi thì con số trên sổ không còn truy được về chứng từ nào, và
 * lúc lệch thì không ai biết bên nào mới là sự thật.
 *
 * =================================================================================================
 * 🔴 SỔ 632 CHỈ ĐI SAU, KHÔNG ĐI TRƯỚC
 * =================================================================================================
 * Dòng giá vốn sinh ra ở ba chỗ, và CHỈ ba chỗ ấy:
 *   · kế toán ký đủ hai phần một báo cáo   -> `VHJP_Kho::xuat_bao_cao()`  -> Nợ 6321
 *   · lập phiếu xé mẫu / tặng mall          -> `VHJP_Kho::xuat()`          -> Nợ 64116
 *   · kiểm kê thiếu                         -> `VHJP_Kho::kiem_ke()`       -> Nợ 6321
 * Sổ này chỉ xếp lại mấy dòng ấy theo tài khoản và cộng dồn. Nếu sổ trống mà báo cáo thì đã hoàn
 * tất, lỗi nằm ở đường GHI chứ không ở đây — nên sổ phải NÓI RA chuyện đó (`canhBao`) thay vì in
 * một bảng rỗng trông như "tháng này không bán gì".
 *
 * =================================================================================================
 * 🔴 SO TÀI KHOẢN THEO TIỀN TỐ, KHÔNG SO BẰNG
 * =================================================================================================
 * Đầu thật của JP là `6321` và `64116`, đổi từ `632`/`641` ngày 06/08/2026. Dữ liệu ghi trước lần
 * đổi vẫn mang đầu cũ. Lọc `=== '632'` thì dòng `6321` KHÔNG BAO GIỜ khớp — sổ giá vốn ra rỗng mà
 * không câu nào báo. Giao diện đã ghi đúng cái bẫy này vào chú thích; bên máy chủ phải khớp.
 *
 * ⚠️ NHƯNG `632` KHÔNG ĐƯỢC NUỐT `6321`. Chọn mục "632 — dữ liệu cũ" là muốn xem ĐÚNG dòng đời
 *    cũ để còn chạy `jpDoiTkKhoCu`. Nên: khớp CHÍNH XÁC trước, và chỉ khi tài khoản được chọn là
 *    một đầu CHA (`632`/`641`) thì mới gom thêm đầu con — không, vẫn khớp chính xác. Hai mục
 *    trong ô chọn là hai bộ dữ liệu khác nhau, gộp lại là mất đúng cái để đối chiếu.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_So {

	/** Tên tài khoản cho dòng tiêu đề của bản xuất MISA. */
	public static function ten_tk( $tk ) {
		$ds = array(
			'6321'  => 'Giá vốn hàng bán JP',
			'64116' => 'Chi phí khác JP (xé mẫu, tặng mall)',
			'632'   => 'Giá vốn hàng bán (dữ liệu trước 06/08/2026)',
			'641'   => 'Chi phí bán hàng (dữ liệu trước 06/08/2026)',
			'1561'  => 'Hàng hoá',
			'156'   => 'Hàng hoá',
			'331'   => 'Phải trả người bán',
			'1388'  => 'Phải thu khác',
		);
		$tk = VHJP_Doc::str( $tk );
		return isset( $ds[ $tk ] ) ? $ds[ $tk ] : $tk;
	}

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Sổ kế toán chỉ kế toán mới xem được' ); }
	}

	/** Đầu và cuối của một tháng, dạng `yyyy-mm-dd`. */
	private static function khoang( $thang, $nam ) {
		$thang = (int) $thang; $nam = (int) $nam;
		if ( $thang < 1 || $thang > 12 || $nam < 2000 ) {
			throw new Exception( 'Tháng/năm không hợp lệ' );
		}
		$tu = sprintf( '%04d-%02d-01', $nam, $thang );
		return array( $tu, gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) ), $thang, $nam );
	}

	/* ══════════════════════════════════════════════════════════════════ SỔ 632 ═══════════ */

	/**
	 * `jpSo632` — sổ chi tiết một tài khoản chi phí, đúng khuôn MISA.
	 *
	 * ⚠️ `$du_dau` do kế toán GÕ VÀO, không suy ra được. Tài khoản chi phí kết chuyển cuối kỳ nên
	 *    số dư đầu kỳ của nó nằm trong MISA, không nằm trong web này. Tự tính lấy một con số rồi
	 *    in ra như thật là chỗ hai hệ nói hai chuyện mà không ai biết bên nào đúng.
	 *
	 * ⚠️ DÒNG ÂM (điều chỉnh giảm xuất sau kiểm kê thừa) ĐỨNG NGUYÊN TRONG SỔ, không bị lọc ra.
	 *    Lọc đi thì tổng phát sinh lớn hơn thực tế đúng bằng phần đã đảo, và dòng gốc thì vẫn còn
	 *    đó — sổ tự mâu thuẫn với chính nó.
	 */
	public static function so_632( $u, $thang, $nam, $du_dau = 0, $tk = '6321' ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );
		$tk = VHJP_Doc::str( $tk );
		if ( '' === $tk ) { $tk = '6321'; }
		$du_dau = self::so( $du_dau );

		$ten_kho = array();
		$ma_don_vi = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$id = VHJP_Doc::str( $l['id'] );
			$ten_kho[ $id ]   = VHJP_Doc::str( $l['name'] );
			$ma_don_vi[ $id ] = VHJP_Doc::str( isset( $l['code'] ) ? $l['code'] : '' );
		}
		$bang_loai = VHJP_Kho::bang_loai_xuat();

		$rows = array(); $tong = 0; $thieu = array(); $gia_0 = array();
		$ds = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			/* Khớp CHÍNH XÁC — xem khối 🔴 thứ hai ở đầu tệp. */
			if ( VHJP_Doc::str( $x['tkNo'] ) !== $tk ) { continue; }
			$n = VHJP_Doc::ngay( $x['ngay'] );
			if ( $n < $tu || $n > $den ) { continue; }
			$ds[] = $x;
		}
		usort( $ds, function ( $a, $b ) {
			$x = strcmp( VHJP_Doc::ngay( $a['ngay'] ), VHJP_Doc::ngay( $b['ngay'] ) );
			return 0 !== $x ? $x : strcmp( (string) $a['id'], (string) $b['id'] );
		} );

		$du = $du_dau;
		foreach ( $ds as $x ) {
			$ma_loai = VHJP_Doc::str( $x['loai'] );
			$sl      = self::so( $x['qty'] );
			$tien    = self::so( $x['amount'] );
			$kho     = VHJP_Doc::str( $x['khoId'] );
			$tl      = self::so( $x['thieuLop'] ) ? 1 : 0;
			$dg      = isset( $bang_loai[ $ma_loai ] ) ? $bang_loai[ $ma_loai ]['ten'] : $ma_loai;
			if ( $sl < 0 ) { $dg = 'Điều chỉnh giảm — ' . $dg; }

			$du += $tien;
			$rows[] = array(
				'ngayHachToan'  => VHJP_Doc::ngay( $x['ngay'] ),
				'ngayChungTu'   => VHJP_Doc::ngay( $x['ngay'] ),
				'soChungTu'     => VHJP_Doc::str( $x['soChungTu'] ),
				'dienGiaiChung' => self::dien_giai_chung( $x, $ten_kho ),
				'dienGiai'      => $dg,
				'tkDoiUng'      => VHJP_Doc::str( $x['tkCo'] ),
				'phatSinhNo'    => $tien,
				'phatSinhCo'    => 0,
				'duNo'          => $du,
				'duCo'          => 0,
				'maDoiTuong'    => VHJP_Doc::str( $x['reportId'] ),
				'maDonVi'       => isset( $ma_don_vi[ $kho ] ) && '' !== $ma_don_vi[ $kho ]
					? $ma_don_vi[ $kho ] : $kho,
				'tenDonVi'      => VHJP_Kho::ten_kho( $kho ),
				'maHang'        => VHJP_Doc::str( $x['itemCode'] ),
				'soLuong'       => $sl,
				'giaVon'        => self::so( $x['unitCost'] ),
				'thieuLop'      => $tl,
				'loai'          => $ma_loai,
			);
			$tong += $tien;
			if ( $tl ) { $thieu[] = VHJP_Doc::str( $x['itemCode'] ); }
			if ( ! $tl && $sl > 0 && self::so( $x['unitCost'] ) <= 0 ) {
				$gia_0[] = VHJP_Doc::str( $x['itemCode'] );
			}
		}

		$canh = array();
		if ( $thieu ) {
			$canh[] = count( $thieu ) . ' dòng THIẾU LỚP TỒN nên giá vốn ghi 0đ ('
				. implode( ', ', array_slice( array_unique( $thieu ), 0, 8 ) )
				. '). Kho chưa có phiếu nhập cho mấy mã ấy — nhập bù rồi bấm "Xuất lại" ở nhật ký '
				. 'xuất kho, sổ sẽ dựng lại đúng.';
		}
		if ( $gia_0 ) {
			$canh[] = count( $gia_0 ) . ' dòng có lớp tồn nhưng ĐƠN GIÁ 0đ ('
				. implode( ', ', array_slice( array_unique( $gia_0 ) , 0, 8 ) )
				. '). Phiếu nhập gõ giá mua = 0 — sửa phiếu nhập gốc, không sửa sổ.';
		}
		/* 🔴 CẢNH BÁO QUAN TRỌNG NHẤT CỦA CẢ SỔ NÀY. Báo cáo đã HOÀN TẤT mà không có dòng giá vốn
		   nào là dấu hiệu đường ghi bị đứt — đúng cái đã xảy ra suốt thời gian móc "duyệt -> sổ
		   kho" chưa được nối. Bảng rỗng thì trông y như "tháng này không bán gì". */
		if ( 0 === strpos( $tk, '632' ) ) {
			$thieu_bc = self::bao_cao_khong_co_gia_von( $tu, $den );
			if ( $thieu_bc ) {
				$canh[] = count( $thieu_bc ) . ' báo cáo ĐÃ HOÀN TẤT trong kỳ mà KHÔNG có dòng giá '
					. 'vốn nào (' . implode( ', ', array_slice( $thieu_bc, 0, 6 ) )
					. ( count( $thieu_bc ) > 6 ? ' …' : '' ) . '). Lãi gộp đang bị thổi lên đúng '
					. 'bằng phần thiếu. Vào Duyệt, bấm ký lại báo cáo ấy — sổ kho sẽ ghi bù.';
			}
		}

		return array(
			'ok' => true, 'taiKhoan' => $tk, 'tenTaiKhoan' => self::ten_tk( $tk ),
			'thang' => $thang, 'nam' => $nam, 'tuNgay' => $tu, 'denNgay' => $den,
			'duDauKy' => $du_dau, 'tongPhatSinhNo' => $tong, 'duCuoiKy' => $du_dau + $tong,
			'rows' => $rows, 'canhBao' => $canh,
		);
	}

	/** Câu diễn giải chung của một dòng sổ — nói rõ hàng đi từ đâu, theo chứng từ nào. */
	private static function dien_giai_chung( $x, $ten_kho ) {
		$kho = VHJP_Doc::str( $x['khoId'] );
		$ten = isset( $ten_kho[ $kho ] ) ? $ten_kho[ $kho ] : VHJP_Kho::ten_kho( $kho );
		$bc  = VHJP_Doc::str( $x['reportId'] );
		if ( '' !== $bc ) {
			return 'Giá vốn hàng bán tại ' . $ten . ' theo báo cáo ' . $bc;
		}
		return 'Xuất kho tại ' . $ten . ' theo chứng từ ' . VHJP_Doc::str( $x['soChungTu'] );
	}

	/**
	 * Báo cáo HOÀN TẤT trong kỳ mà sổ kho chưa ghi dòng nào.
	 *
	 * ⚠️ Chỉ đếm báo cáo THẬT SỰ CÓ HÀNG BÁN. Báo cáo toàn dòng máy xu (chỉ tiền, không hàng) thì
	 *    không có giá vốn là ĐÚNG — kể tên nó ra là cảnh báo kêu sói, và cảnh báo kêu sói vài lần
	 *    là lần thật không ai đọc nữa.
	 */
	private static function bao_cao_khong_co_gia_von( $tu, $den ) {
		$co_dong = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			$bc = VHJP_Doc::str( $x['reportId'] );
			if ( '' !== $bc ) { $co_dong[ $bc ] = 1; }
		}
		$ban = array();
		foreach ( VHJP_Nguon::doc( 'JP_Rows' ) as $r ) {
			if ( ! in_array( VHJP_Doc::str( $r['rowKind'] ), VHJP_Kho::DONG_CO_HANG, true ) ) { continue; }
			if ( '' === VHJP_Doc::str( $r['itemCode'] ) ) { continue; }
			if ( self::so( $r['soldQty'] ) <= 0 ) { continue; }
			$ban[ VHJP_Doc::str( $r['reportId'] ) ] = 1;
		}
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $bc ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $bc['status'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $bc['toDate'] );
			if ( $n < $tu || $n > $den ) { continue; }
			$id = VHJP_Doc::str( $bc['id'] );
			if ( isset( $co_dong[ $id ] ) ) { continue; }
			if ( ! isset( $ban[ $id ] ) ) { continue; }
			$ra[] = $id;
		}
		sort( $ra );
		return $ra;
	}

	/* ══════════════════════════════════════════════════════════ CÔNG NỢ NHÀ CUNG CẤP ═════ */

	/**
	 * `jpCongNoNcc` — còn nợ ai bao nhiêu, theo tháng.
	 *
	 * 🔴 CHỈ PHIẾU **MUA** SINH CÔNG NỢ. Bốn đường nhập còn lại — đầu kỳ, kiểm kê thừa, nhận điều
	 *    chuyển, cơ sở trả về — KHÔNG nợ ai cả. Gộp chúng vào là số phải trả phình lên bằng cả
	 *    tồn kho, và không đối chiếu được với sổ 331 nào.
	 *
	 * 🔴 PHIẾU ĐÃ HUỶ KHÔNG TÍNH, cả bên mua lẫn bên trả. Huỷ một phiếu trả tiền mà vẫn trừ công
	 *    nợ là nhà cung cấp còn đòi mà sổ nói đã trả xong.
	 *
	 * ⚠️ Dư đầu kỳ suy từ CHÍNH chứng từ trước kỳ, không có ô gõ tay. Khác sổ 632 ở chỗ: 632 là
	 *    tài khoản kết chuyển nên số dư nằm bên MISA, còn công nợ thì mọi phiếu mua và phiếu trả
	 *    đều nằm ngay trong web này — tự suy được thì đừng bắt người ta gõ.
	 */
	public static function cong_no_ncc( $u, $thang, $nam ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );

		$gom = array();
		$moi = function ( $ma, $ten ) {
			return array( 'nccMa' => $ma, 'nccTen' => $ten, 'duDauKy' => 0, 'phatSinh' => 0,
				'daTra' => 0, 'duCuoiKy' => 0, 'conNo' => false,
				'soPhieuNhap' => 0, 'soPhieuTra' => 0 );
		};
		$khoa = function ( $ma, $ten ) { return VHJP_Doc::str( $ma ) . '|' . VHJP_Doc::str( $ten ); };

		$khong_ten = 0;
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			if ( VHJP_Kho::N_MUA !== VHJP_Doc::str( $p['loaiNhap'] ) ) { continue; }
			if ( '' !== VHJP_Doc::str( $p['huyBy'] ) ) { continue; }
			$ma  = VHJP_Doc::str( $p['nccMa'] );
			$ten = VHJP_Doc::str( $p['nccTen'] );
			if ( '' === $ma && '' === $ten ) { $khong_ten++; continue; }
			$k = $khoa( $ma, $ten );
			if ( ! isset( $gom[ $k ] ) ) { $gom[ $k ] = $moi( $ma, $ten ); }
			$n    = VHJP_Doc::ngay( $p['ngay'] );
			$tien = self::so( $p['tongTien'] );
			if ( $n < $tu ) {
				$gom[ $k ]['duDauKy'] += $tien;
			} elseif ( $n <= $den ) {
				$gom[ $k ]['phatSinh'] += $tien;
				$gom[ $k ]['soPhieuNhap']++;
			}
		}
		$tra_la = array();
		foreach ( VHJP_Nguon::doc( 'JP_KhoTraNcc' ) as $t ) {
			if ( '' !== VHJP_Doc::str( $t['huyBy'] ) ) { continue; }
			$ma  = VHJP_Doc::str( $t['nccMa'] );
			$ten = VHJP_Doc::str( $t['nccTen'] );
			$k   = $khoa( $ma, $ten );
			if ( ! isset( $gom[ $k ] ) ) {
				/* Trả tiền cho một nhà cung cấp chưa từng có phiếu mua nào — vẫn phải hiện, và
				   phải được kể ra. Bỏ qua thì tiền đã chi mà sổ không thấy ở đâu. */
				$gom[ $k ] = $moi( $ma, $ten );
				$tra_la[]  = '' !== $ten ? $ten : $ma;
			}
			$n    = VHJP_Doc::ngay( $t['ngay'] );
			$tien = self::so( $t['soTien'] );
			if ( $n < $tu ) {
				$gom[ $k ]['duDauKy'] -= $tien;
			} elseif ( $n <= $den ) {
				$gom[ $k ]['daTra'] += $tien;
				$gom[ $k ]['soPhieuTra']++;
			}
		}

		$tong = array( 'duDauKy' => 0, 'phatSinh' => 0, 'daTra' => 0, 'duCuoiKy' => 0 );
		$vuot = array();
		foreach ( $gom as $k => $r ) {
			$cuoi = $r['duDauKy'] + $r['phatSinh'] - $r['daTra'];
			$gom[ $k ]['duCuoiKy'] = $cuoi;
			$gom[ $k ]['conNo']    = $cuoi > 0;
			$tong['duDauKy']  += $r['duDauKy'];
			$tong['phatSinh'] += $r['phatSinh'];
			$tong['daTra']    += $r['daTra'];
			$tong['duCuoiKy'] += $cuoi;
			if ( $cuoi < 0 ) { $vuot[] = ( '' !== $r['nccTen'] ? $r['nccTen'] : $r['nccMa'] )
				. ' (' . number_format( -$cuoi, 0, ',', '.' ) . 'đ)'; }
		}
		/* Dòng không phát sinh gì trong kỳ VÀ không còn nợ thì bỏ — bảng toàn số 0 che mất mấy
		   nhà cung cấp thật sự đang còn nợ. */
		$rows = array();
		foreach ( $gom as $r ) {
			if ( 0 === $r['phatSinh'] && 0 === $r['daTra'] && 0 === $r['duCuoiKy']
				&& 0 === $r['duDauKy'] ) { continue; }
			$rows[] = $r;
		}
		usort( $rows, function ( $a, $b ) {
			/* Ai còn nợ nhiều nhất lên đầu — đó là thứ người mở màn này đi tìm. */
			if ( $a['duCuoiKy'] !== $b['duCuoiKy'] ) { return $b['duCuoiKy'] > $a['duCuoiKy'] ? 1 : -1; }
			return strcmp( $a['nccTen'], $b['nccTen'] );
		} );

		$canh = array();
		if ( $khong_ten ) {
			$canh[] = $khong_ten . ' phiếu mua KHÔNG khai nhà cung cấp nên không vào được sổ công '
				. 'nợ nào. Giá trị của chúng vẫn nằm trong tồn kho, nhưng khoản phải trả thì biến '
				. 'mất — mở "Nhật ký nhập kho", điền NCC cho mấy phiếu ấy.';
		}
		if ( $tra_la ) {
			$canh[] = count( $tra_la ) . ' nhà cung cấp có phiếu TRẢ TIỀN mà chưa từng có phiếu mua '
				. 'nào (' . implode( ', ', array_slice( array_unique( $tra_la ), 0, 6 ) )
				. '). Hoặc tên gõ lệch với phiếu mua, hoặc thiếu phiếu nhập.';
		}
		if ( $vuot ) {
			$canh[] = count( $vuot ) . ' nhà cung cấp ĐÃ TRẢ VƯỢT (' . implode( ', ', array_slice( $vuot, 0, 6 ) )
				. '). Hoặc còn thiếu phiếu nhập, hoặc một phiếu trả bị ghi hai lần.';
		}

		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam,
			'tuNgay' => $tu, 'denNgay' => $den,
			'rows' => $rows, 'tong' => $tong, 'canhBao' => $canh );
	}
	/* ═══════════════════════════════════════════════ SỔ CÔNG NỢ THEO CƠ SỞ ═══════════════ */

	/**
	 * `jpSoCongNo` — dư đầu kỳ + phát sinh − đã nhận, theo từng cơ sở, theo tháng.
	 *
	 * =============================================================================================
	 * 🔴 DƯ ĐẦU KỲ ĐƯỢC SUY, KHÔNG ĐƯỢC LƯU — NÊN PHẢI NÓI RA SUY TỪ ĐÂU.
	 * =============================================================================================
	 * JP không có bảng "dư đầu kỳ": nó cộng lại mọi báo cáo HOÀN TẤT có ngày cuối kỳ TRƯỚC mốc.
	 * Cách ấy đúng và tự sửa mình (duyệt bù một báo cáo cũ là dư đầu kỳ đổi theo ngay), nhưng nó
	 * đẻ ra một con số không giải thích được nếu chỉ in mỗi số. Nên mỗi hàng kèm `nguonDuDauKy`
	 * nói rõ: bao nhiêu báo cáo, phải thu bao nhiêu, đã nhận bao nhiêu. Không có câu ấy thì kế
	 * toán thấy lệch cũng không biết mở đâu ra mà tra.
	 *
	 * =============================================================================================
	 * 🔴 CHỈ BÁO CÁO HOÀN TẤT MỚI LÀ PHẢI THU.
	 * =============================================================================================
	 * Báo cáo chưa đủ hai chữ ký thì con số còn sửa được — đưa vào sổ công nợ là ghi một khoản
	 * phải thu có thể bốc hơi ngày mai. Nhưng cũng KHÔNG được im: tiền ấy có thật ngoài đời, nên
	 * đếm riêng ở `phaiThuChuaDuyet`/`soBaoCaoChuaDuyet` và giao diện in một dòng cảnh báo.
	 *
	 * ⚠️ Xếp kỳ theo `toDate` — đúng cách `cong_no_nv()` đang xếp. Hai sổ công nợ cắt kỳ theo hai
	 *    ô ngày khác nhau là hai tổng khác nhau cho cùng một tháng.
	 */
	public static function so_cong_no( $u, $thang, $nam ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );

		$gom = VHJP_DoiSoat::gom_cach();
		$cs  = array();

		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			$ma  = VHJP_Doc::str( $r['locationId'] );
			$ten = VHJP_Doc::str( $r['locationName'] );
			if ( '' === $ma && '' === $ten ) { continue; }
			$ngay = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $ngay ) { continue; }
			$st = VHJP_Doc::str( $r['status'] );
			if ( VHJP_BaoCao::TT_NHAP === $st ) { continue; }   /* đang gõ dở — chưa là gì cả */

			if ( ! isset( $cs[ $ma ] ) ) {
				$cs[ $ma ] = array( 'locationId' => $ma, 'locationName' => $ten,
					'duDauKy' => 0, 'dauSoBc' => 0, 'dauPhaiThu' => 0, 'dauDaNhan' => 0,
					'phatSinh' => 0, 'daNhanTM' => 0, 'daNhanCK' => 0, 'daNhanQR' => 0,
					'daNhanKhac' => 0, 'daNhan' => 0, 'soBaoCao' => 0,
					'soBaoCaoChuaDuyet' => 0, 'phaiThuChuaDuyet' => 0 );
			}
			if ( '' === $cs[ $ma ]['locationName'] ) { $cs[ $ma ]['locationName'] = $ten; }

			$phai = VHJP_NopTien::phai_nop( $r );
			$nhan = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );

			/* Chưa hoàn tất: KHÔNG vào phải thu, nhưng phải đếm riêng — xem khối 🔴 thứ hai. */
			if ( VHJP_BaoCao::TT_HOAN_TAT !== $st ) {
				if ( $ngay >= $tu && $ngay <= $den ) {
					$cs[ $ma ]['soBaoCaoChuaDuyet']++;
					$cs[ $ma ]['phaiThuChuaDuyet'] += $phai;
				}
				continue;
			}

			if ( $ngay < $tu ) {
				$cs[ $ma ]['duDauKy']    += $phai - $nhan;
				$cs[ $ma ]['dauSoBc']++;
				$cs[ $ma ]['dauPhaiThu'] += $phai;
				$cs[ $ma ]['dauDaNhan']  += $nhan;
				continue;
			}
			if ( $ngay > $den ) { continue; }

			$t = VHJP_DoiSoat::tach_cach_thu( $r, $gom );
			$cs[ $ma ]['soBaoCao']++;
			$cs[ $ma ]['phatSinh']   += $phai;
			$cs[ $ma ]['daNhan']     += $nhan;
			$cs[ $ma ]['daNhanTM']   += $t['TM'];
			$cs[ $ma ]['daNhanCK']   += $t['CK'];
			$cs[ $ma ]['daNhanQR']   += $t['QR'];
			$cs[ $ma ]['daNhanKhac'] += $t['KHAC'];
		}

		$tong = array( 'duDauKy' => 0, 'phatSinh' => 0, 'daNhan' => 0, 'daNhanTM' => 0,
			'daNhanCK' => 0, 'daNhanQR' => 0, 'daNhanKhac' => 0, 'duCuoiKy' => 0,
			'phaiThuChuaDuyet' => 0, 'soBaoCaoChuaDuyet' => 0 );
		$rows = array();
		foreach ( $cs as $x ) {
			/* Cơ sở tháng này không có gì VÀ không mang nợ cũ thì không in — một bảng đầy hàng 0
			   là bảng không ai đọc hết, và hàng cần nhìn lẫn vào giữa. */
			if ( ! $x['soBaoCao'] && ! $x['soBaoCaoChuaDuyet'] && 0 === (int) round( $x['duDauKy'] ) ) {
				continue;
			}
			$x['duCuoiKy'] = $x['duDauKy'] + $x['phatSinh'] - $x['daNhan'];
			$x['conThieu'] = $x['duCuoiKy'] > 0;
			$x['tinhTrang'] = $x['duCuoiKy'] > 0 ? 'Còn thiếu'
				: ( $x['duCuoiKy'] < 0 ? 'Nhận vượt' : 'Đã đủ' );
			$x['nguonDuDauKy'] = $x['dauSoBc']
				? ( $x['dauSoBc'] . ' báo cáo hoàn tất có kỳ kết thúc trước '
					. VHJP_Doc::dmy( $tu ) . ': phải thu '
					. number_format( $x['dauPhaiThu'], 0, ',', '.' ) . 'đ − đã nhận '
					. number_format( $x['dauDaNhan'], 0, ',', '.' ) . 'đ' )
				: 'Không có báo cáo hoàn tất nào trước kỳ này';

			foreach ( array_keys( $tong ) as $k ) {
				if ( isset( $x[ $k ] ) ) { $tong[ $k ] += $x[ $k ]; }
			}
			$rows[] = $x;
		}
		usort( $rows, function ( $a, $b ) {
			/* Nợ nhiều nhất lên đầu — đó là thứ người mở sổ này đi tìm. */
			if ( $a['duCuoiKy'] !== $b['duCuoiKy'] ) { return $b['duCuoiKy'] > $a['duCuoiKy'] ? 1 : -1; }
			return strcmp( $a['locationName'], $b['locationName'] );
		} );

		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam,
			'tuNgay' => $tu, 'denNgay' => $den, 'rows' => $rows, 'tong' => $tong );
	}

}
