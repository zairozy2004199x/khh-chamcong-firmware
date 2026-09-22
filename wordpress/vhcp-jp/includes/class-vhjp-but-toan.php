<?php
/**
 * BÚT TOÁN — `JP2_14_ButToan.gs`.
 *
 * =================================================================================================
 * 🔴 BÚT TOÁN KHÔNG LƯU Ở ĐÂU. SUY RA TỪ CHỨNG TỪ, MỖI LẦN XEM.
 * =================================================================================================
 * Không có bảng `JP_ButToan`, không có nút "chốt sổ", và điều đó là CỐ Ý.
 *
 * Lưu bút toán thành một bảng riêng nghĩa là có hai sự thật: chứng từ, và bản sao của nó. Sửa một
 * phiếu nhập mà quên sinh lại bút toán thì sổ nói một đằng, kho nói một nẻo — và không ai biết bên
 * nào đúng cho tới lúc đối chiếu với MISA, tức là cuối tháng, tức là quá muộn. Suy ra mỗi lần xem
 * thì chỉ có MỘT sự thật, và mọi màn đọc nó đều tự khớp nhau.
 *
 * Cái giá phải trả là tốc độ: mở sổ là quét lại chứng từ. Với cỡ dữ liệu của JP (vài nghìn dòng
 * một tháng) thì đó là cái giá rẻ, và đổi lại là không bao giờ phải đi tìm "vì sao hai bảng lệch".
 *
 * ⚠️ KẾ TOÁN QUEN VỚI "CHỐT SỔ" SẼ ĐI TÌM NÚT CHỐT. Ba màn đọc bộ này phải nói rõ ra là không có
 *    nút ấy, chứ không để người ta đi tìm rồi tưởng mình chưa làm đủ bước.
 *
 * =================================================================================================
 * 🔴 CHỈ BÁO CÁO **HOÀN TẤT** MỚI VÀO SỔ
 * =================================================================================================
 * Báo cáo đang chờ duyệt là con số nhân viên khai, chưa ai soát. Đưa nó vào sổ là doanh thu của kỳ
 * nhảy lên rồi tụt xuống mỗi lần kế toán trả về một báo cáo. Nhưng KHÔNG ĐƯỢC IM: phần bị bỏ ra
 * phải hiện thành con số (`boQua`), không thì tiền biến mất êm và không ai hỏi.
 *
 * =================================================================================================
 * 🔴 CÓ NHỮNG ĐẦU TÀI KHOẢN LÀ **TẠM** — VÀ PHẢI NÓI RA LÀ TẠM
 * =================================================================================================
 * Bảng tài khoản chia theo từng khối kinh doanh, và phần của JP chưa được kế toán chốt hết. Đoán
 * một đầu rồi in ra như thật là đúng cái đã xảy ra với `1567` (kho ĂN UỐNG) — giá vốn JP bị ghi
 * lấn sang khối khác suốt một thời gian, sổ vẫn CÂN nên không phép kiểm nào báo, và sửa lại là
 * sửa cả sổ. Nên đầu nào còn tạm thì đi kèm nhãn TẠM ở cả ba màn, và nói rõ cần xin gì để chốt.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_ButToan {

	/* Đầu tài khoản. Đổi ở đây là đổi mọi màn — đừng gõ chuỗi thẳng vào chỗ khác. */
	const TK_PHAI_THU   = '131';
	const TK_DOANH_THU  = '5111';
	const TK_GIAM_TRU   = '5213';
	const TK_GIA_VON    = '6321';
	const TK_CHI_PHI_BH = '64116';
	const TK_HANG_HOA   = '1561';
	const TK_PHAI_TRA   = '331';
	const TK_TIEN_MAT   = '1111';
	const TK_TIEN_NH    = '1121';
	const TK_THU_KHAC   = '711';
	const TK_CHI_KHAC   = '811';
	const TK_KET_QUA    = '911';
	const TK_PHAI_THU_K = '1388';
	const TK_VON        = '411';

	/** Đầu nhận lãi/lỗ. Chưa chốt — xem `tk_tam()`. */
	const TK_LAI_LO     = '421?';

	/**
	 * Suất thuế GTGT đang tách. `0` = KHÔNG tách, toàn bộ số thu vào 5111.
	 *
	 * ⚠️ Để 0 là CHỦ Ý, không phải quên. Tách thuế cần biết JP có xuất hoá đơn hay không và suất
	 *    bao nhiêu — đoán rồi tách là doanh thu thuần sai, và sai theo hướng ĐẸP HƠN thực tế.
	 */
	const VAT_SUAT = 0;

	/**
	 * Đầu tài khoản còn TẠM, kèm việc cần làm để chốt.
	 *
	 * 🔴 Mỗi mục ở đây là một chỗ máy ĐOÁN. In ra ở cả ba màn, không bỏ cho gọn: một con số đúng
	 *    nằm dưới một đầu sai thì vẫn là một con số sai, mà bảng thì vẫn cân nên không ai thấy.
	 */
	public static function tk_tam() {
		return array(
			array( 'tk' => self::TK_DOANH_THU,
				'viec' => 'Đầu doanh thu của JP. Bảng tài khoản chia theo khối, chưa rõ JP dùng '
					. '5111 hay một con riêng. Xin Sổ chi tiết TK 511 chi nhánh MTD tháng 7/2026.' ),
			array( 'tk' => self::TK_GIAM_TRU,
				'viec' => 'Đầu ghi hoàn tiền khách. Đang dùng 5213 (giảm giá hàng bán); nếu kế '
					. 'toán muốn 5212 (hàng bán bị trả lại) thì đổi một chỗ trong VHJP_ButToan.' ),
			array( 'tk' => self::TK_VON,
				'viec' => 'Đối ứng của tồn kho ĐẦU KỲ. Khai tồn mở sổ không có người bán nào đứng '
					. 'sau, nên tạm treo vào 411 — kế toán chốt đầu thật thì đổi.' ),
			array( 'tk' => self::TK_LAI_LO,
				'viec' => 'Đầu nhận lãi/lỗ cuối kỳ. 4212 là Lợi nhuận KH, 4213 là Lợi nhuận TQ — '
					. 'đều của khối khác. Chọn bừa là đẩy lãi JP sang khối khác, đúng y lỗi 1567.' ),
		);
	}

	/** Tên tài khoản, cho bảng cân đối. */
	public static function ten_tk( $tk ) {
		$ds = array(
			'131' => 'Phải thu của khách hàng (nhân viên cơ sở giữ tiền)',
			'1111' => 'Tiền mặt', '1121' => 'Tiền gửi ngân hàng',
			'1388' => 'Phải thu khác (kiểm kê thừa)',
			'1561' => 'Hàng hoá JP', '1567' => 'Hàng hoá — kho ĂN UỐNG (đầu của khối khác)',
			'156' => 'Hàng hoá (đầu tổng)',
			'331' => 'Phải trả người bán', '411' => 'Nguồn vốn kinh doanh',
			'5111' => 'Doanh thu bán hàng JP', '5213' => 'Giảm giá hàng bán',
			'632' => 'Giá vốn hàng bán (đầu TỔNG — dữ liệu trước 06/08/2026)',
			'6321' => 'Giá vốn hàng bán JP',
			'641' => 'Chi phí bán hàng (đầu TỔNG — dữ liệu trước 06/08/2026)',
			'64116' => 'Chi phí khác JP (xé mẫu, tặng mall)',
			'711' => 'Thu nhập khác (lệch máy thừa tiền)',
			'811' => 'Chi phí khác (lệch máy thiếu tiền)',
			'911' => 'Xác định kết quả kinh doanh',
			'421?' => 'Lợi nhuận sau thuế chưa phân phối — ĐẦU CHƯA CHỐT',
		);
		$tk = VHJP_Doc::str( $tk );
		return isset( $ds[ $tk ] ) ? $ds[ $tk ] : $tk;
	}

	/** Đầu còn TẠM thì trả câu việc cần làm; không thì rỗng. */
	public static function viec_tam( $tk ) {
		foreach ( self::tk_tam() as $t ) {
			if ( $t['tk'] === VHJP_Doc::str( $tk ) ) { return $t['viec']; }
		}
		return '';
	}

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Sổ kế toán chỉ kế toán mới xem được' ); }
	}

	private static function khoang( $thang, $nam ) {
		$thang = (int) $thang; $nam = (int) $nam;
		if ( $thang < 1 || $thang > 12 || $nam < 2000 ) {
			throw new Exception( 'Tháng/năm không hợp lệ' );
		}
		$tu = sprintf( '%04d-%02d-01', $nam, $thang );
		return array( $tu, gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) ), $thang, $nam );
	}

	/* ═════════════════════════════════════════════════════════ BỘ SINH BÚT TOÁN ══════════ */

	/**
	 * Dựng TOÀN BỘ bút toán từ đầu đến giờ, không cắt theo kỳ.
	 *
	 * 🔴 KHÔNG CẮT Ở ĐÂY. Dư đầu kỳ của một tài khoản là tổng mọi bút toán TRƯỚC kỳ; cắt sớm thì
	 *    dư đầu luôn bằng 0 và bảng cân đối thành một bảng chỉ có phát sinh. Người gọi tự chia
	 *    hai phía theo ngày — một danh sách, hai cách đọc.
	 *
	 * Mỗi bút toán: ngay · soCt · dienGiai · dienGiaiChung · tkNo · tkCo · soTien · maDoiTuong ·
	 * maDonVi · nguon.
	 *
	 * @return array array( 'ds' => bút toán[], 'boQua' => thống kê phần KHÔNG vào sổ )
	 */
	public static function sinh() {
		$bt = array();
		$bo = array( 'bcChuaDuyet' => 0, 'tienChuaDuyet' => 0, 'phieuHuy' => 0,
			'thieuTk' => 0, 'thieuNgay' => 0 );

		$ma_don_vi = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$id = VHJP_Doc::str( $l['id'] );
			$c  = VHJP_Doc::str( isset( $l['code'] ) ? $l['code'] : '' );
			$ma_don_vi[ $id ] = '' !== $c ? $c : $id;
		}
		$dv = function ( $id ) use ( $ma_don_vi ) {
			$id = VHJP_Doc::str( $id );
			return isset( $ma_don_vi[ $id ] ) ? $ma_don_vi[ $id ] : $id;
		};
		$them = function ( $x ) use ( &$bt ) {
			if ( self::so( $x['soTien'] ) == 0 ) { return; }
			/* Nợ và Có cùng một đầu thì KHÔNG phải bút toán: nó không dời gì cả. Điều chuyển kho
			   (156/156) rơi đúng vào đây — ghi ra là bảng cân đối có một dòng phát sinh Nợ = Có
			   trên cùng tài khoản, trông như có việc gì đó xảy ra trong khi không. */
			if ( VHJP_Doc::str( $x['tkNo'] ) === VHJP_Doc::str( $x['tkCo'] ) ) { return; }
			$bt[] = $x;
		};

		/* ── ① BÁO CÁO ĐÃ HOÀN TẤT ──
		   Ba vế dựng đúng số nhân viên phải nộp: doanh thu − hoàn khách ± lệch máy = totalSubmit,
		   và cả ba đều đi qua 131. Thiếu một vế là dư 131 không bằng số phải nộp, mà bảng vẫn
		   cân nên không phép kiểm nào báo. */
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			$tt = VHJP_Doc::str( $r['status'] );
			if ( VHJP_BaoCao::TT_HOAN_TAT !== $tt ) {
				if ( VHJP_BaoCao::TT_NHAP !== $tt ) {
					$bo['bcChuaDuyet']++;
					$bo['tienChuaDuyet'] += self::so( $r['totalSubmit'] );
				}
				continue;
			}
			$ngay = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $ngay ) { $bo['thieuNgay']++; continue; }
			$id  = VHJP_Doc::str( $r['id'] );
			$cs  = VHJP_Doc::str( $r['locationId'] );
			$ten = VHJP_Doc::str( $r['locationName'] );
			$chung = 'Doanh thu kỳ ' . VHJP_Doc::dmy( $r['fromDate'] ) . ' → '
				. VHJP_Doc::dmy( $r['toDate'] ) . ' tại ' . $ten;

			$them( array( 'ngay' => $ngay, 'soCt' => $id, 'dienGiai' => 'Doanh thu bán hàng',
				'dienGiaiChung' => $chung, 'tkNo' => self::TK_PHAI_THU, 'tkCo' => self::TK_DOANH_THU,
				'soTien' => self::so( $r['revMeter'] ), 'maDoiTuong' => $id, 'maDonVi' => $dv( $cs ),
				'nguon' => 'BAO_CAO' ) );

			$hoan = VHJP_Tinh::hoan_tong( $r );
			$them( array( 'ngay' => $ngay, 'soCt' => $id, 'dienGiai' => 'Hoàn tiền khách',
				'dienGiaiChung' => $chung, 'tkNo' => self::TK_GIAM_TRU, 'tkCo' => self::TK_PHAI_THU,
				'soTien' => $hoan, 'maDoiTuong' => $id, 'maDonVi' => $dv( $cs ),
				'nguon' => 'BAO_CAO' ) );

			$adj = self::so( $r['adjMachine'] );
			if ( $adj > 0 ) {
				$them( array( 'ngay' => $ngay, 'soCt' => $id, 'dienGiai' => 'Lệch máy — thừa tiền',
					'dienGiaiChung' => $chung, 'tkNo' => self::TK_PHAI_THU, 'tkCo' => self::TK_THU_KHAC,
					'soTien' => $adj, 'maDoiTuong' => $id, 'maDonVi' => $dv( $cs ),
					'nguon' => 'BAO_CAO' ) );
			} elseif ( $adj < 0 ) {
				$them( array( 'ngay' => $ngay, 'soCt' => $id, 'dienGiai' => 'Lệch máy — thiếu tiền',
					'dienGiaiChung' => $chung, 'tkNo' => self::TK_CHI_KHAC, 'tkCo' => self::TK_PHAI_THU,
					'soTien' => -$adj, 'maDoiTuong' => $id, 'maDonVi' => $dv( $cs ),
					'nguon' => 'BAO_CAO' ) );
			}
		}

		/* ── ② DÒNG XUẤT KHO ──
		   Lấy THẲNG `tkNo`/`tkCo` đã ghi trên dòng, không suy lại theo loại. Dòng đời cũ mang đầu
		   cũ (632/1567) phải hiện ra ĐÚNG đầu cũ thì `jpDoiTkKhoCu` mới có việc để làm và kế toán
		   mới thấy được là sổ đang lệch khối. Suy lại ở đây là giấu mất chính cái cần sửa. */
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			$ngay = VHJP_Doc::ngay( $x['ngay'] );
			if ( '' === $ngay ) { $bo['thieuNgay']++; continue; }
			$cap = self::tk_cua_dong( $x );
			$no  = $cap['tkNo'];
			$co  = $cap['tkCo'];
			if ( $cap['thieu'] ) { $bo['thieuTk']++; }
			$bc = VHJP_Doc::str( $x['reportId'] );
			$them( array( 'ngay' => $ngay, 'soCt' => VHJP_Doc::str( $x['soChungTu'] ),
				'dienGiai' => self::ten_xuat( $x ),
				'dienGiaiChung' => 'Xuất kho ' . VHJP_Kho::ten_kho( $x['khoId'] ) . ' · '
					. VHJP_Doc::str( $x['itemCode'] ) . ' × ' . self::so( $x['qty'] ),
				'tkNo' => $no, 'tkCo' => $co, 'soTien' => self::so( $x['amount'] ),
				'maDoiTuong' => $bc, 'maDonVi' => $dv( $x['khoId'] ), 'nguon' => 'KHO_XUAT' ) );
		}

		/* ── ③ PHIẾU NHẬP ── chỉ MUA sinh công nợ; đầu kỳ treo 411; kiểm kê thừa 1388. */
		foreach ( VHJP_Nguon::doc( 'JP_KhoNhap' ) as $p ) {
			if ( '' !== VHJP_Doc::str( $p['huyBy'] ) ) { $bo['phieuHuy']++; continue; }
			$ngay = VHJP_Doc::ngay( $p['ngay'] );
			if ( '' === $ngay ) { $bo['thieuNgay']++; continue; }
			$lo = VHJP_Doc::str( $p['loaiNhap'] );
			$co = '';
			$dg = '';
			if ( VHJP_Kho::N_MUA === $lo )          { $co = self::TK_PHAI_TRA;   $dg = 'Mua hàng nhập kho'; }
			elseif ( VHJP_Kho::N_DAU_KY === $lo )   { $co = self::TK_VON;        $dg = 'Tồn kho đầu kỳ'; }
			elseif ( VHJP_Kho::N_KIEM_KE === $lo )  { $co = self::TK_PHAI_THU_K; $dg = 'Kiểm kê thừa'; }
			/* Nhận điều chuyển và cơ sở trả về KHÔNG sinh bút toán: hàng chỉ đổi kho trong cùng
			   một pháp nhân, vế xuất bên kia đã bị bỏ vì cùng đầu 156/156. */
			if ( '' === $co ) { continue; }
			$them( array( 'ngay' => $ngay, 'soCt' => VHJP_Doc::str( $p['soChungTu'] ),
				'dienGiai' => $dg,
				'dienGiaiChung' => $dg . ' — ' . VHJP_Kho::ten_kho( $p['khoId'] )
					. ( '' !== VHJP_Doc::str( $p['nccTen'] ) ? ' · ' . VHJP_Doc::str( $p['nccTen'] ) : '' ),
				'tkNo' => self::TK_HANG_HOA, 'tkCo' => $co,
				'soTien' => self::so( $p['tongTien'] ),
				'maDoiTuong' => VHJP_Doc::str( $p['nccMa'] ), 'maDonVi' => $dv( $p['khoId'] ),
				'nguon' => 'KHO_NHAP' ) );
		}

		/* ── ④ NỘP TIỀN ── tiền mặt và chuyển khoản vào HAI đầu khác nhau. */
		foreach ( VHJP_Nguon::doc( 'JP_Payments' ) as $t ) {
			$ngay = VHJP_Doc::ngay( $t['payDate'] );
			if ( '' === $ngay ) { $bo['thieuNgay']++; continue; }
			$tm = 'TM' === strtoupper( VHJP_Doc::str( $t['method'] ) );
			$them( array( 'ngay' => $ngay, 'soCt' => VHJP_Doc::str( $t['id'] ),
				'dienGiai' => $tm ? 'Nhận tiền mặt' : 'Nhận chuyển khoản',
				'dienGiaiChung' => 'Nhân viên nộp tiền của báo cáo '
					. VHJP_Doc::str( $t['reportId'] ),
				'tkNo' => $tm ? self::TK_TIEN_MAT : self::TK_TIEN_NH,
				'tkCo' => self::TK_PHAI_THU, 'soTien' => self::so( $t['amount'] ),
				'maDoiTuong' => VHJP_Doc::str( $t['reportId'] ),
				'maDonVi' => $dv( $t['locationId'] ), 'nguon' => 'NOP_TIEN' ) );
		}

		/* ── ⑤ TRẢ TIỀN NHÀ CUNG CẤP ── */
		foreach ( VHJP_Nguon::doc( 'JP_KhoTraNcc' ) as $t ) {
			if ( '' !== VHJP_Doc::str( $t['huyBy'] ) ) { $bo['phieuHuy']++; continue; }
			$ngay = VHJP_Doc::ngay( $t['ngay'] );
			if ( '' === $ngay ) { $bo['thieuNgay']++; continue; }
			$tm = 'TM' === strtoupper( VHJP_Doc::str( $t['hinhThuc'] ) );
			$them( array( 'ngay' => $ngay, 'soCt' => VHJP_Doc::str( $t['soChungTu'] ),
				'dienGiai' => 'Trả tiền nhà cung cấp',
				'dienGiaiChung' => 'Trả ' . VHJP_Doc::str( $t['nccTen'] )
					. ( $tm ? ' bằng tiền mặt' : ' bằng chuyển khoản' ),
				'tkNo' => self::TK_PHAI_TRA, 'tkCo' => $tm ? self::TK_TIEN_MAT : self::TK_TIEN_NH,
				'soTien' => self::so( $t['soTien'] ),
				'maDoiTuong' => VHJP_Doc::str( $t['nccMa'] ), 'maDonVi' => '',
				'nguon' => 'TRA_NCC' ) );
		}

		usort( $bt, function ( $a, $b ) {
			$x = strcmp( $a['ngay'], $b['ngay'] );
			if ( 0 !== $x ) { return $x; }
			$y = strcmp( $a['soCt'], $b['soCt'] );
			return 0 !== $y ? $y : strcmp( $a['tkNo'], $b['tkNo'] );
		} );
		return array( 'ds' => $bt, 'boQua' => $bo );
	}

	/**
	 * Cặp tài khoản THẬT của một dòng xuất kho — nguồn DUY NHẤT của phép suy này.
	 *
	 * 🔴 DÒNG THIẾU TÀI KHOẢN RƠI VÀO ĐẦU CŨ (632/1567), VÀ ĐÓ LÀ CHỦ Ý. Dòng không có đầu là
	 *    dòng đời cũ; cho nó rơi vào đầu cũ thì nó hiện lên ở màn "đổi tài khoản cũ" cùng với
	 *    đồng bọn và được dọn cùng một lượt. Cho nó rơi thẳng vào 6321/1561 thì nó biến mất khỏi
	 *    màn ấy — đúng, nhưng chỉ đúng trên màn hình: dữ liệu vẫn trống, và lượt xuất sau vẫn
	 *    đọc ra một dòng không có đầu.
	 *
	 * ⚠️ `sinh()` và `doi_tk_kho_cu()` PHẢI dùng chung hàm này. Hai nơi tự suy lấy là bảng cân
	 *    đối nói dòng ấy đang ở 632 trong khi lượt đổi không thấy nó đâu — cảnh báo hiện lên,
	 *    bấm "Đổi thật", và cảnh báo không bao giờ tắt.
	 */
	private static function tk_cua_dong( $x ) {
		$no = VHJP_Doc::str( $x['tkNo'] );
		$co = VHJP_Doc::str( $x['tkCo'] );
		$thieu = ( '' === $no || '' === $co );
		return array( 'tkNo' => '' !== $no ? $no : '632',
			'tkCo' => '' !== $co ? $co : '1567', 'thieu' => $thieu );
	}

	/** Câu diễn giải của một dòng xuất kho, nói rõ cả lượt điều chỉnh âm. */
	private static function ten_xuat( $x ) {
		$bang = VHJP_Kho::bang_loai_xuat();
		$ma   = VHJP_Doc::str( $x['loai'] );
		$ten  = isset( $bang[ $ma ] ) ? $bang[ $ma ]['ten'] : $ma;
		if ( self::so( $x['qty'] ) < 0 ) { return 'Điều chỉnh giảm — ' . $ten; }
		return $ten;
	}

	/* ══════════════════════════════════════════════════════════ ① NHẬT KÝ CHUNG ══════════ */

	/**
	 * `jpSoNhatKyChung` — mọi bút toán của một kỳ. Lọc theo tài khoản thì thành SỔ CHI TIẾT.
	 *
	 * ⚠️ Lọc một tài khoản thì phải kèm DƯ ĐẦU KỲ và cột dư chạy — không có thì màn này chỉ là
	 *    một danh sách, không phải một cuốn sổ, và không đối chiếu được với MISA.
	 */
	public static function nhat_ky_chung( $u, $thang, $nam, $tk = '' ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );
		$tk = VHJP_Doc::str( $tk );

		$sinh = self::sinh();
		$rows = array(); $tong_no = 0; $tong_co = 0;
		$du_dau_no = 0; $du_dau_co = 0;

		/* Dư đầu kỳ: chỉ có nghĩa khi đang soi MỘT tài khoản. */
		if ( '' !== $tk ) {
			$du = 0;
			foreach ( $sinh['ds'] as $x ) {
				if ( $x['ngay'] >= $tu ) { continue; }
				if ( $x['tkNo'] === $tk ) { $du += $x['soTien']; }
				if ( $x['tkCo'] === $tk ) { $du -= $x['soTien']; }
			}
			if ( $du >= 0 ) { $du_dau_no = $du; } else { $du_dau_co = -$du; }
		}

		$du = $du_dau_no - $du_dau_co;
		foreach ( $sinh['ds'] as $x ) {
			if ( $x['ngay'] < $tu || $x['ngay'] > $den ) { continue; }
			if ( '' !== $tk && $x['tkNo'] !== $tk && $x['tkCo'] !== $tk ) { continue; }
			$r = $x;
			if ( '' !== $tk ) {
				$du += ( $x['tkNo'] === $tk ? $x['soTien'] : -$x['soTien'] );
				$r['duNo'] = $du >= 0 ? $du : 0;
				$r['duCo'] = $du < 0 ? -$du : 0;
			}
			$rows[]   = $r;
			$tong_no += $x['soTien'];
			$tong_co += $x['soTien'];
		}

		/* 🔴 Mỗi bút toán một vế Nợ một vế Có nên tổng luôn bằng nhau — phép "cân" ở màn này CHỈ
		   có nghĩa khi lọc cả sổ, và nó bắt đúng một loại lỗi: bút toán sinh ra thiếu vế. Lọc
		   một tài khoản thì hai tổng ấy không so được với nhau, nên đừng khoe là đã kiểm. */
		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam, 'tk' => $tk,
			'tuNgay' => $tu, 'denNgay' => $den,
			'rows' => $rows, 'soDong' => count( $rows ),
			'tongNo' => $tong_no, 'tongCo' => $tong_co, 'canBang' => $tong_no === $tong_co,
			'duDauNo' => $du_dau_no, 'duDauCo' => $du_dau_co,
			'boQua' => $sinh['boQua'], 'tkTam' => self::tk_tam(), 'vatSuat' => self::VAT_SUAT );
	}

	/* ══════════════════════════════════════════════════ ② BẢNG CÂN ĐỐI PHÁT SINH ═════════ */

	/**
	 * `jpBangCanDoiPhatSinh` — mỗi tài khoản một dòng: dư đầu · phát sinh · dư cuối.
	 *
	 * 🔴 BA PHÉP CÂN, IN RIÊNG TỪNG CÁI. Gộp thành một chữ "cân" thì lệch ở đâu cũng không biết —
	 *    đúng bệnh của cờ `canBang` gộp ở báo cáo N-X-T.
	 *
	 * ⚠️ NÓI ĐÚNG SỨC MẠNH CỦA CHÚNG: cả BA đều là phép kiểm CẤU TRÚC, không phải phép kiểm số
	 *    liệu. Mỗi bút toán `sinh()` đẻ ra đều có đúng một vế Nợ và một vế Có bằng nhau, nên tổng
	 *    phát sinh cân, tổng dư đầu cân và tổng dư cuối cân — LUÔN LUÔN, kể cả khi mọi con số đều
	 *    sai. Thứ duy nhất chúng bắt được là một ngày nào đó `sinh()` bị sửa và đẻ ra bút toán
	 *    thiếu vế. Đó vẫn là một cái chốt đáng giữ, nhưng ĐỪNG đọc chữ "CÂN" trên màn này thành
	 *    "sổ đúng": việc bắt số sai là của `jpKiemTraButToan` (so hai đường độc lập) và của
	 *    `jpQuetDayChuyen`.
	 */
	public static function can_doi( $u, $thang, $nam ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );
		$sinh = self::sinh();

		$gom = array();
		$lay = function ( $tk ) use ( &$gom ) {
			$tk = VHJP_Doc::str( $tk );
			if ( ! isset( $gom[ $tk ] ) ) {
				$viec = self::viec_tam( $tk );
				$gom[ $tk ] = array( 'tk' => $tk, 'tenTk' => self::ten_tk( $tk ),
					'laTam' => '' !== $viec, 'viecTam' => $viec,
					'dau' => 0, 'psNo' => 0, 'psCo' => 0, 'soDong' => 0 );
			}
			return $tk;
		};

		foreach ( $sinh['ds'] as $x ) {
			$no = $lay( $x['tkNo'] );
			$co = $lay( $x['tkCo'] );
			if ( $x['ngay'] < $tu ) {
				$gom[ $no ]['dau'] += $x['soTien'];
				$gom[ $co ]['dau'] -= $x['soTien'];
				continue;
			}
			if ( $x['ngay'] > $den ) { continue; }
			$gom[ $no ]['psNo'] += $x['soTien'];
			$gom[ $co ]['psCo'] += $x['soTien'];
			$gom[ $no ]['soDong']++;
			$gom[ $co ]['soDong']++;
		}

		$tong = array( 'duDauNo' => 0, 'duDauCo' => 0, 'psNo' => 0, 'psCo' => 0,
			'duCuoiNo' => 0, 'duCuoiCo' => 0 );
		$rows = array();
		foreach ( $gom as $r ) {
			$cuoi = $r['dau'] + $r['psNo'] - $r['psCo'];
			$r['duDauNo']  = $r['dau'] >= 0 ? $r['dau'] : 0;
			$r['duDauCo']  = $r['dau'] < 0 ? -$r['dau'] : 0;
			$r['duCuoiNo'] = $cuoi >= 0 ? $cuoi : 0;
			$r['duCuoiCo'] = $cuoi < 0 ? -$cuoi : 0;
			unset( $r['dau'] );
			/* Tài khoản không phát sinh gì trong kỳ và không còn dư thì bỏ — bảng toàn số 0 che
			   mất mấy đầu thật sự có việc. */
			if ( 0 === $r['psNo'] && 0 === $r['psCo'] && 0 === $r['duDauNo']
				&& 0 === $r['duDauCo'] && 0 === $cuoi ) { continue; }
			$tong['duDauNo']  += $r['duDauNo'];
			$tong['duDauCo']  += $r['duDauCo'];
			$tong['psNo']     += $r['psNo'];
			$tong['psCo']     += $r['psCo'];
			$tong['duCuoiNo'] += $r['duCuoiNo'];
			$tong['duCuoiCo'] += $r['duCuoiCo'];
			$rows[] = $r;
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $a['tk'], $b['tk'] ); } );

		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam,
			'tuNgay' => $tu, 'denNgay' => $den, 'rows' => $rows, 'tong' => $tong,
			'canBangPs'   => $tong['psNo'] === $tong['psCo'],
			'lechPs'      => $tong['psNo'] - $tong['psCo'],
			'canBangDau'  => $tong['duDauNo'] === $tong['duDauCo'],
			'lechDau'     => $tong['duDauNo'] - $tong['duDauCo'],
			'canBangCuoi' => $tong['duCuoiNo'] === $tong['duCuoiCo'],
			'lechCuoi'    => $tong['duCuoiNo'] - $tong['duCuoiCo'],
			'boQua' => $sinh['boQua'], 'tkTam' => self::tk_tam(), 'vatSuat' => self::VAT_SUAT );
	}

	/* ═══════════════════════════════════════════════════════════ ③ KIỂM TRA SỔ ═══════════ */

	/**
	 * `jpKiemTraButToan` — so HAI ĐƯỜNG tính ra cùng một con số.
	 *
	 * 🔴 PHÉP KIỂM CHỈ CÓ NGHĨA KHI HAI ĐƯỜNG THẬT SỰ KHÁC NHAU.
	 *    · Dư 131 qua bút toán: cộng từng vế Nợ/Có của mọi bút toán chạm 131.
	 *    · Dư 131 qua sổ công nợ: Σ `totalSubmit` của báo cáo hoàn tất − phần đã thu.
	 *    · Tiền đã thu qua bút toán: đọc TỪNG DÒNG `JP_Payments` (phải biết tiền mặt hay chuyển
	 *      khoản để vào đúng 1111/1121).
	 *    · Tiền đã thu qua sổ công nợ: cộng theo CỜ `paid` của báo cáo.
	 *    Hai lối ấy lệch nhau đúng khi đối soát đánh dấu đã thu mà không sinh dòng nộp tiền, hoặc
	 *    ngược lại — loại lỗi không màn nào khác bắt được.
	 *
	 * ⚠️ `totalSubmit` được tính lại tại chỗ và so với số đang lưu. Đường sửa trong app đã đóng,
	 *    nên lệch ở đây gần như chắc chắn là ai đó sửa tay thẳng vào dữ liệu.
	 */
	public static function kiem_tra( $u, $thang, $nam ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );
		$sinh = self::sinh();

		$du_bt = 0;
		foreach ( $sinh['ds'] as $x ) {
			if ( $x['ngay'] > $den ) { continue; }
			if ( $x['tkNo'] === self::TK_PHAI_THU ) { $du_bt += $x['soTien']; }
			if ( $x['tkCo'] === self::TK_PHAI_THU ) { $du_bt -= $x['soTien']; }
		}
		$thu_bt = 0;
		foreach ( $sinh['ds'] as $x ) {
			if ( 'NOP_TIEN' !== $x['nguon'] ) { continue; }
			if ( $x['ngay'] < $tu || $x['ngay'] > $den ) { continue; }
			$thu_bt += $x['soTien'];
		}

		$phai_thu = 0; $thu_cn = 0; $lech_ts = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			$ngay = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $ngay || $ngay > $den ) { continue; }
			$ts = self::so( $r['totalSubmit'] );
			$phai_thu += $ts;
			if ( self::so( $r['paid'] ) ) { $phai_thu -= $ts; }

			/* Cộng "tiền đã thu" theo CỜ paid, chỉ trong kỳ — để so với đường đọc từng dòng nộp. */
			if ( self::so( $r['paid'] ) ) {
				$n2 = VHJP_Doc::ngay( $r['paidDate'] );
				if ( '' === $n2 ) { $n2 = $ngay; }
				if ( $n2 >= $tu && $n2 <= $den ) { $thu_cn += $ts; }
			}

			$suy = self::so( $r['revMeter'] ) + self::so( $r['adjMachine'] ) - VHJP_Tinh::hoan_tong( $r );
			if ( $suy !== $ts && $ngay >= $tu ) {
				$lech_ts[] = array( 'id' => VHJP_Doc::str( $r['id'] ),
					'coSo' => VHJP_Doc::str( $r['locationName'] ),
					'totalSubmit' => $ts, 'suyRa' => $suy, 'lech' => $ts - $suy );
			}
		}

		$lech_du  = $du_bt - $phai_thu;
		$lech_thu = $thu_bt - $thu_cn;
		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam,
			'du131ButToan' => $du_bt, 'du131CongNo' => $phai_thu, 'lechDu' => $lech_du,
			'thuButToan' => $thu_bt, 'thuCongNo' => $thu_cn, 'lechThu' => $lech_thu,
			'khop' => 0 === $lech_du && 0 === $lech_thu && ! $lech_ts,
			'soLechTongSubmit' => count( $lech_ts ), 'lechTongSubmit' => $lech_ts,
			'boQua' => $sinh['boQua'], 'tkTam' => self::tk_tam(), 'vatSuat' => self::VAT_SUAT );
	}

	/* ═════════════════════════════════════════════════ ④ KẾT QUẢ KINH DOANH (911) ════════ */

	/**
	 * `jpKetQuaKinhDoanh` — kết chuyển 911, và so lại bằng một đường thứ hai.
	 *
	 * ⚠️ NÓI ĐÚNG SỨC MẠNH CỦA PHÉP KIỂM, KHÔNG NÓI QUÁ. Doanh thu · giảm trừ · lệch máy được
	 *    cộng theo HAI lối thật sự khác nhau (một qua bút toán, một đọc thẳng cột của báo cáo)
	 *    nên khớp là bằng chứng thật. Riêng giá vốn và chi phí bán hàng thì hai lối cùng đọc
	 *    `JP_KhoXuat.amount`, chỉ khác đường đi trong mã — bắt được lỗi suy đầu tài khoản và lỗi
	 *    lọc ngày, KHÔNG bắt được số tiền ghi sai. Nên mỗi khoản mang cờ `doclap`, và giao diện
	 *    in đúng mức ấy ra.
	 *
	 * ⚠️ KHÔNG CÓ NÚT "KẾT CHUYỂN" và cũng đừng thêm. Bút toán suy ra mỗi lần xem; một nút ghi
	 *    thật vào sổ là đẻ ra bản sao thứ hai của sự thật.
	 */
	public static function ket_qua_kd( $u, $thang, $nam ) {
		self::can_kt( $u );
		list( $tu, $den, $thang, $nam ) = self::khoang( $thang, $nam );
		$sinh = self::sinh();

		/* ── Đường MỘT: cộng từ bút toán ── */
		$ps = array();
		foreach ( $sinh['ds'] as $x ) {
			if ( $x['ngay'] < $tu || $x['ngay'] > $den ) { continue; }
			foreach ( array( 'tkNo' => 1, 'tkCo' => -1 ) as $ben => $dau ) {
				$tk = $x[ $ben ];
				if ( ! isset( $ps[ $tk ] ) ) { $ps[ $tk ] = array( 'no' => 0, 'co' => 0 ); }
				if ( 1 === $dau ) { $ps[ $tk ]['no'] += $x['soTien']; }
				else { $ps[ $tk ]['co'] += $x['soTien']; }
			}
		}
		$g = function ( $tk, $ben ) use ( $ps ) {
			return isset( $ps[ $tk ] ) ? $ps[ $tk ][ $ben ] : 0;
		};

		$doanh_thu  = $g( self::TK_DOANH_THU, 'co' ) - $g( self::TK_DOANH_THU, 'no' );
		$giam_tru   = $g( self::TK_GIAM_TRU, 'no' ) - $g( self::TK_GIAM_TRU, 'co' );
		$gia_von    = $g( self::TK_GIA_VON, 'no' ) - $g( self::TK_GIA_VON, 'co' );
		$chi_phi_bh = $g( self::TK_CHI_PHI_BH, 'no' ) - $g( self::TK_CHI_PHI_BH, 'co' );
		$thu_khac   = $g( self::TK_THU_KHAC, 'co' ) - $g( self::TK_THU_KHAC, 'no' );
		$chi_khac   = $g( self::TK_CHI_KHAC, 'no' ) - $g( self::TK_CHI_KHAC, 'co' );

		/* ── Đường HAI: cộng thẳng từ chứng từ ── */
		$ct_dt = 0; $ct_gt = 0; $ct_thu = 0; $ct_chi = 0;
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $n || $n < $tu || $n > $den ) { continue; }
			$ct_dt += self::so( $r['revMeter'] );
			$ct_gt += VHJP_Tinh::hoan_tong( $r );
			$adj = self::so( $r['adjMachine'] );
			if ( $adj > 0 ) { $ct_thu += $adj; } elseif ( $adj < 0 ) { $ct_chi += -$adj; }
		}
		$ct_gv = 0; $ct_bh = 0;
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			$n = VHJP_Doc::ngay( $x['ngay'] );
			if ( '' === $n || $n < $tu || $n > $den ) { continue; }
			$tk = VHJP_Doc::str( $x['tkNo'] );
			if ( self::TK_GIA_VON === $tk )    { $ct_gv += self::so( $x['amount'] ); }
			if ( self::TK_CHI_PHI_BH === $tk ) { $ct_bh += self::so( $x['amount'] ); }
		}

		$lech = array();
		$so = function ( $khoan, $bt, $ct, $doc_lap ) use ( &$lech ) {
			if ( $bt === $ct ) { return; }
			$lech[] = array( 'khoan' => $khoan, 'tuButToan' => $bt, 'tuChungTu' => $ct,
				'lech' => $bt - $ct, 'doclap' => $doc_lap ? true : false );
		};
		$so( 'Doanh thu bán hàng', $doanh_thu, $ct_dt, true );
		$so( 'Các khoản giảm trừ', $giam_tru, $ct_gt, true );
		$so( 'Thu nhập khác (lệch máy thừa)', $thu_khac, $ct_thu, true );
		$so( 'Chi phí khác (lệch máy thiếu)', $chi_khac, $ct_chi, true );
		$so( 'Giá vốn hàng bán', $gia_von, $ct_gv, false );
		$so( 'Chi phí bán hàng', $chi_phi_bh, $ct_bh, false );

		$dt_thuan = $doanh_thu - $giam_tru;
		$lai_gop  = $dt_thuan - $gia_von;
		$ln_thuan = $lai_gop - $chi_phi_bh;
		$ln_khac  = $thu_khac - $chi_khac;
		$ln_truoc = $ln_thuan + $ln_khac;

		/* Bút toán kết chuyển — đúng thứ kế toán gõ sang MISA. Khoản nào bằng 0 thì KHÔNG in:
		   một dòng kết chuyển 0đ là một dòng gõ thừa, và gõ thừa vào MISA thì phải đi xoá. */
		$kc = array(); $stt = 0;
		$them_kc = function ( $no, $co, $tien, $dg ) use ( &$kc, &$stt ) {
			if ( 0 == $tien ) { return; }
			$stt++;
			$kc[] = array( 'stt' => $stt, 'tkNo' => $no, 'tenNo' => self::ten_tk( $no ),
				'tkCo' => $co, 'tenCo' => self::ten_tk( $co ), 'soTien' => $tien,
				'dienGiai' => $dg );
		};
		$them_kc( self::TK_DOANH_THU, self::TK_KET_QUA, $doanh_thu, 'Kết chuyển doanh thu bán hàng' );
		$them_kc( self::TK_KET_QUA, self::TK_GIAM_TRU, $giam_tru, 'Kết chuyển các khoản giảm trừ' );
		$them_kc( self::TK_KET_QUA, self::TK_GIA_VON, $gia_von, 'Kết chuyển giá vốn hàng bán' );
		$them_kc( self::TK_KET_QUA, self::TK_CHI_PHI_BH, $chi_phi_bh, 'Kết chuyển chi phí bán hàng' );
		$them_kc( self::TK_THU_KHAC, self::TK_KET_QUA, $thu_khac, 'Kết chuyển thu nhập khác' );
		$them_kc( self::TK_KET_QUA, self::TK_CHI_KHAC, $chi_khac, 'Kết chuyển chi phí khác' );

		$no_911 = $giam_tru + $gia_von + $chi_phi_bh + $chi_khac;
		$co_911 = $doanh_thu + $thu_khac;

		$buoc_cuoi = null;
		if ( 0 != $ln_truoc ) {
			$buoc_cuoi = array(
				'tkNo' => $ln_truoc >= 0 ? self::TK_KET_QUA : self::TK_LAI_LO,
				'tkCo' => $ln_truoc >= 0 ? self::TK_LAI_LO : self::TK_KET_QUA,
				'soTien' => abs( $ln_truoc ),
				'chuaChotDau' => true,
			);
		}

		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam,
			'tuNgay' => $tu, 'denNgay' => $den,
			'doanhThu' => $doanh_thu, 'giamTru' => $giam_tru, 'doanhThuThuan' => $dt_thuan,
			'giaVon' => $gia_von, 'laiGop' => $lai_gop,
			'tyLeLaiGop' => 0 != $dt_thuan ? round( $lai_gop * 100 / $dt_thuan, 1 ) : null,
			'chiPhiBH' => $chi_phi_bh, 'loiNhuanThuan' => $ln_thuan,
			'thuNhapKhac' => $thu_khac, 'chiPhiKhac' => $chi_khac, 'loiNhuanKhac' => $ln_khac,
			'lnTruocThue' => $ln_truoc,
			'ketChuyen' => $kc, 'buocCuoi' => $buoc_cuoi,
			'no911' => $no_911, 'co911' => $co_911,
			'trietTieu911' => ( $co_911 - $no_911 ) === $ln_truoc,
			'khop' => ! $lech, 'lech' => $lech,
			'boQua' => $sinh['boQua'], 'tkTam' => self::tk_tam(), 'vatSuat' => self::VAT_SUAT );
	}

	/* ═══════════════════════════════════════════════════ ⑤ ĐỔI TÀI KHOẢN ĐỜI CŨ ══════════ */

	/**
	 * Đầu cũ -> đầu của JP. `1567` là kho ĂN UỐNG, tức giá vốn JP đang ghi lấn sang khối khác.
	 *
	 * 🔴 CHỈ BA CẶP NÀY. Bảng đổi rộng ra một dòng là một lượt ghi đè hàng loạt lên sổ đã chốt,
	 *    và không có đường lùi.
	 */
	public static function bang_doi_tk() {
		return array( '632' => '6321', '641' => '64116', '1567' => self::TK_HANG_HOA );
	}

	/**
	 * `jpDoiTkKhoCu` — đổi đầu tài khoản trên dòng kho đời cũ.
	 *
	 * ⚠️ HAI BƯỚC CÓ CHỦ Ý: `$ghi = false` chỉ ĐẾM và in mẫu, `true` mới sửa. Đây là sửa thẳng
	 *    vào sổ đã chốt — một nút bấm nhầm là ghi đè hàng loạt.
	 *
	 * ⚠️ Chạy lại NHIỀU LẦN vẫn an toàn: chỉ đụng đúng ba đầu trong bảng, dòng đã đúng thì không
	 *    khớp bảng nên không bị đụng tới.
	 *
	 * 🔴 KHÔNG ĐỔI ĐẦU TRÊN DÒNG ĐÃ MANG ĐẦU ĐÚNG. Đổi `6321 -> 6321` thì vô hại, nhưng đổi theo
	 *    kiểu "cứ ghi đè cho chắc" là lúc bảng đổi có một dòng sai thì mọi dòng đúng cũng hỏng.
	 */
	public static function doi_tk_kho_cu( $u, $ghi = false ) {
		self::can_kt( $u );
		$bang = self::bang_doi_tk();
		$ghi  = (bool) $ghi;

		$nhom = array(); $so_dong = 0; $tien = 0; $da_ghi = 0;
		foreach ( VHJP_Nguon::doc( 'JP_KhoXuat' ) as $x ) {
			/* Đọc qua `tk_cua_dong()` chứ không đọc thẳng ô: dòng THIẾU HẲN tài khoản phải được
			   lượt này tóm, không thì bảng cân đối báo nó ở 632 mà bấm "Đổi thật" chẳng đụng tới,
			   và cảnh báo không bao giờ tắt. */
			$cap = self::tk_cua_dong( $x );
			$no  = $cap['tkNo'];
			$co  = $cap['tkCo'];
			$no_moi = isset( $bang[ $no ] ) ? $bang[ $no ] : $no;
			$co_moi = isset( $bang[ $co ] ) ? $bang[ $co ] : $co;
			if ( $no_moi === $no && $co_moi === $co && ! $cap['thieu'] ) { continue; }

			$k = $no . '|' . $co;
			if ( ! isset( $nhom[ $k ] ) ) {
				$nhom[ $k ] = array( 'tkNoCu' => $no, 'tkCoCu' => $co,
					'tkNoMoi' => $no_moi, 'tkCoMoi' => $co_moi, 'soDong' => 0, 'soTien' => 0 );
			}
			$nhom[ $k ]['soDong']++;
			$nhom[ $k ]['soTien'] += self::so( $x['amount'] );
			$so_dong++;
			$tien += self::so( $x['amount'] );

			if ( $ghi ) {
				if ( false !== VHJP_Nguon::sua( 'JP_KhoXuat', $x['id'],
					array( 'tkNo' => $no_moi, 'tkCo' => $co_moi ) ) ) { $da_ghi++; }
			}
		}

		$msg = $so_dong
			? ( $ghi
				? 'Đã đổi ' . $da_ghi . '/' . $so_dong . ' dòng · '
					. number_format( $tien, 0, ',', '.' ) . 'đ về đầu tài khoản của JP.'
					. ( $da_ghi < $so_dong ? ' ⚠️ ' . ( $so_dong - $da_ghi )
						. ' dòng ghi không được — chạy lại lượt này.' : '' )
				: 'Xem trước: ' . $so_dong . ' dòng · '
					. number_format( $tien, 0, ',', '.' ) . 'đ đang mang đầu cũ. '
					. 'Chưa sửa gì — bấm "Đổi thật" mới ghi.' )
			: 'Không còn dòng nào mang đầu cũ. Sổ đang dùng đúng đầu của JP.';

		if ( $ghi && $so_dong ) {
			VHJP_NhatKy::ghi( $u, 'KHO_DOI_TK', '', '', $da_ghi . '/' . $so_dong . ' dòng · ' . $tien );
		}
		return array( 'ok' => true, 'ghi' => $ghi, 'soDong' => $so_dong, 'daGhi' => $da_ghi,
			'soTien' => $tien, 'nhom' => array_values( $nhom ), 'msg' => $msg );
	}
}
