<?php
/**
 * BẢNG & ĐỀ NGHỊ — mấy màn ĐỌC của kế toán, cộng đường đề nghị sửa tồn đầu.
 *
 * =================================================================================================
 * 🔴 TỒN ĐẦU KỲ NÀY = TỒN CUỐI KỲ TRƯỚC, VÀ NHÂN VIÊN KHÔNG ĐƯỢC TỰ SỬA
 * =================================================================================================
 * Nếu nhân viên sửa được ô tồn đầu thì mọi phép kiểm hàng hoá mất nghĩa: đếm thiếu 20 quả, sửa
 * tồn đầu xuống 20 là bảng cân. Nên ô ấy do máy chủ điền từ kỳ trước và KHOÁ; muốn đổi thì gửi
 * **đề nghị** kèm lý do, kế toán duyệt.
 *
 * ⚠️ KHOÁ THEO "KỲ TRƯỚC ĐÃ DUYỆT", KHÔNG THEO "CÓ TÌM THẤY SỐ". Hai chuyện khác nhau: tìm thấy
 *    số của một kỳ CHƯA duyệt rồi khoá luôn là ép nhân viên giữ một con số mà chính kế toán còn
 *    có thể trả về bắt sửa. Cờ `chot` nói điều đó, cờ `found` thì không.
 *
 * =================================================================================================
 * 🔴 BIÊN BẢN GIỮ CẢ SỐ CŨ LẪN SỐ MỚI
 * =================================================================================================
 * Duyệt xong mà chỉ còn số mới thì sáu tháng sau không ai dựng lại được căn cứ — cùng lý do biên
 * bản kiểm kê giữ cả `tonSo` và `tonThuc`.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Bang {

	const DN_CHO     = 'CHO';
	const DN_DUYET   = 'DUYET';
	const DN_TU_CHOI = 'TU_CHOI';

	/** Giờ được phép tự mở lại báo cáo đã nộp. */
	const GIO_MO_LAI = 24;

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	private static function can_kt( $u ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) { throw new Exception( 'Việc này cần tài khoản kế toán' ); }
	}

	/** Lọc báo cáo theo khoảng ngày KẾT THÚC kỳ. */
	private static function trong_khoang( $r, $tu, $den ) {
		$n = VHJP_Doc::ngay( $r['toDate'] );
		if ( '' === $n ) { return false; }
		if ( '' !== $tu && $n < $tu ) { return false; }
		if ( '' !== $den && $n > $den ) { return false; }
		return true;
	}

	/* ══════════════════════════════════════════════════════════════ BẢNG DOANH THU ═══════ */

	/** `jpRevenueBoard` — mỗi báo cáo một dòng tiền. */
	public static function bang_doanh_thu( $u, $f = array() ) {
		self::can_kt( $u );
		$f   = is_array( $f ) ? $f : array();
		$tu  = VHJP_Doc::ngay( isset( $f['fromDate'] ) ? $f['fromDate'] : '' );
		$den = VHJP_Doc::ngay( isset( $f['toDate'] ) ? $f['toDate'] : '' );
		$gom = VHJP_NopTien::gom_nop();

		$rows = array();
		$sum  = array( 'revMeter' => 0, 'revBank' => 0, 'adjMachine' => 0,
			'refundCustomer' => 0, 'cashActual' => 0, 'totalSubmit' => 0, 'paid' => 0 );
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			/* NHÁP không vào bảng này — đó là bản nhân viên đang gõ dở. */
			if ( VHJP_BaoCao::TT_NHAP === VHJP_Doc::str( $r['status'] ) ) { continue; }
			if ( ! self::trong_khoang( $r, $tu, $den ) ) { continue; }
			$k    = VHJP_Doc::str( $r['id'] );
			$phai = self::so( $r['totalSubmit'] );
			/* "Đã nộp" ở bảng này là số KẾ TOÁN XÁC NHẬN, không phải số nhân viên tự khai —
			   bảng này đứng cạnh sổ quỹ, mà sổ quỹ chỉ biết tiền đã thật sự về. */
			$da   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
			$hoan = VHJP_Tinh::hoan_tong( $r );
			$d = array(
				'id' => $k, 'maKH' => VHJP_Doc::str( $r['maKH'] ),
				'locationName' => VHJP_Doc::str( $r['locationName'] ),
				'period' => VHJP_Doc::dmy( $r['fromDate'] ) . ' – ' . VHJP_Doc::dmy( $r['toDate'] ),
				'userName' => VHJP_Doc::str( $r['userName'] ),
				'revMeter' => self::so( $r['revMeter'] ), 'revBank' => self::so( $r['revBank'] ),
				'adjMachine' => self::so( $r['adjMachine'] ), 'refundCustomer' => $hoan,
				'cashActual' => self::so( $r['cashActual'] ),
				'totalSubmit' => $phai, 'paid' => $da, 'conLai' => $phai - $da,
				'payStatus' => VHJP_NopTien::tinh_trang( $phai, $da ),
				'soLanNop' => isset( $gom[ $k ] ) ? $gom[ $k ]['lan'] : 0,
			);
			foreach ( array_keys( $sum ) as $o ) { $sum[ $o ] += $d[ $o ]; }
			$rows[] = $d;
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $b['id'], $a['id'] ); } );
		return array( 'ok' => true, 'tuNgay' => $tu, 'denNgay' => $den,
			'rows' => $rows, 'sum' => $sum );
	}

	/* ══════════════════════════════════════════════════════════════ BẢNG HÀNG HOÁ ════════ */

	/**
	 * `jpStockBoard` — mỗi DÒNG hàng của mọi báo cáo.
	 *
	 * 🔴 `lech` LÀ `null` KHI CHƯA ĐẾM, không phải 0. Hai chuyện khác hẳn nhau: chưa đếm là
	 *    chưa biết, còn 0 là đã đếm và khớp. Trả 0 cho cả hai thì ô "chỉ hiện dòng lệch" kéo
	 *    hết dòng chưa đếm vào, và kế toán đi soát một đống dòng chẳng có gì sai.
	 */
	public static function bang_hang( $u, $f = array() ) {
		self::can_kt( $u );
		$f   = is_array( $f ) ? $f : array();
		$tu  = VHJP_Doc::ngay( isset( $f['fromDate'] ) ? $f['fromDate'] : '' );
		$den = VHJP_Doc::ngay( isset( $f['toDate'] ) ? $f['toDate'] : '' );

		$bc = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_NHAP === VHJP_Doc::str( $r['status'] ) ) { continue; }
			if ( ! self::trong_khoang( $r, $tu, $den ) ) { continue; }
			$bc[ VHJP_Doc::str( $r['id'] ) ] = $r;
		}
		$ten_loai = array( 'MONEY' => 'Máy tiền', 'COIN' => 'Máy xu (tiền)',
			'STOCK' => 'Máy xu (hàng)', 'NGOAI' => 'Hàng ngoài máy',
			'MAY' => 'Ô máy (tách)', 'HANG' => 'Mã hàng (tách)' );

		$rows = array();
		foreach ( VHJP_Nguon::doc( 'JP_Rows' ) as $r ) {
			$k = VHJP_Doc::str( $r['reportId'] );
			if ( ! isset( $bc[ $k ] ) ) { continue; }
			$loai = VHJP_Doc::str( $r['rowKind'] );
			/* Dòng COIN không giữ hàng — bày nó ở bảng hàng hoá là một hàng toàn số 0. */
			if ( 'COIN' === $loai ) { continue; }
			$h = $bc[ $k ];
			$thuc = VHJP_Doc::num_hoac_trong( isset( $r['stockActual'] ) ? $r['stockActual'] : '' );
			$con  = self::so( isset( $r['stockLeftCalc'] ) ? $r['stockLeftCalc'] : 0 );
			$rows[] = array(
				'reportId' => $k, 'locationName' => VHJP_Doc::str( $h['locationName'] ),
				'period' => VHJP_Doc::dmy( $h['fromDate'] ) . ' – ' . VHJP_Doc::dmy( $h['toDate'] ),
				'rowKind' => $loai,
				'tenLoaiDong' => isset( $ten_loai[ $loai ] ) ? $ten_loai[ $loai ] : $loai,
				'machineCode' => VHJP_Doc::str( $r['machineCode'] ),
				'itemCode' => VHJP_Doc::str( $r['itemCode'] ),
				'itemMisa' => VHJP_Doc::str( $r['itemMisa'] ),
				'price' => self::so( $r['price'] ),
				'stockOpen' => self::so( isset( $r['stockOpen'] ) ? $r['stockOpen'] : 0 ),
				'addQty' => self::so( isset( $r['addQty1'] ) ? $r['addQty1'] : 0 )
					+ self::so( isset( $r['addQty2'] ) ? $r['addQty2'] : 0 ),
				'soldQty' => self::so( $r['soldQty'] ),
				'defectQty' => self::so( isset( $r['defectQty'] ) ? $r['defectQty'] : 0 ),
				'returnQty' => self::so( isset( $r['returnQty'] ) ? $r['returnQty'] : 0 ),
				'stockLeftCalc' => $con,
				'stockActual' => $thuc,
				/* 🔴 `null` = CHƯA ĐẾM. Xem khối 🔴 ở trên. */
				'lech' => '' === $thuc ? null : $thuc - $con,
				'amount' => self::so( $r['amount'] ),
			);
		}
		return array( 'ok' => true, 'tuNgay' => $tu, 'denNgay' => $den, 'rows' => $rows );
	}

	/* ════════════════════════════════════════════════════ DOANH THU TỪNG NGÀY (MISA) ═════ */

	/**
	 * `jpBaoCaoDoanhThuNgay` — khuôn "Daily Sales Report" của MISA: dòng = đơn vị, cột = ngày.
	 *
	 * 🔴 BÁO CÁO NHIỀU NGÀY DỒN VÀO NGÀY CUỐI KỲ, VÀ PHẢI NÓI RA. Một báo cáo 7 ngày chỉ có
	 *    MỘT con số tổng — không có cách nào chia nó ra bảy ngày mà không bịa. Dồn vào ngày
	 *    cuối là lựa chọn ít sai nhất (đó là ngày chốt số), nhưng im lặng dồn thì người đọc
	 *    tưởng hôm ấy bán gấp bảy lần. Nên mọi báo cáo dài hơn một ngày đều bị kể tên.
	 */
	public static function doanh_thu_ngay( $u, $thang, $nam ) {
		self::can_kt( $u );
		$thang = (int) $thang; $nam = (int) $nam;
		if ( $thang < 1 || $thang > 12 || $nam < 2000 ) {
			throw new Exception( 'Tháng/năm không hợp lệ' );
		}
		$tu     = sprintf( '%04d-%02d-01', $nam, $thang );
		$den    = gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) );
		$so_ngay = (int) gmdate( 't', strtotime( $tu . ' 00:00:00 UTC' ) );

		$cs = array();
		foreach ( VHJP_Nguon::doc( 'JP_Locations' ) as $l ) {
			$cs[ VHJP_Doc::str( $l['id'] ) ] = array(
				'ten' => VHJP_Doc::str( $l['name'] ),
				'ma'  => VHJP_Doc::str( isset( $l['code'] ) ? $l['code'] : '' ) );
		}

		$gom = array(); $nhieu_ngay = array(); $tong = 0;
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $n || $n < $tu || $n > $den ) { continue; }
			$tien = self::so( $r['revMeter'] );
			if ( 0 == $tien ) { continue; }
			$id  = VHJP_Doc::str( $r['locationId'] );
			$ngay = (int) substr( $n, 8, 2 );
			if ( ! isset( $gom[ $id ] ) ) {
				$gom[ $id ] = array(
					'unitId' => isset( $cs[ $id ] ) && '' !== $cs[ $id ]['ma'] ? $cs[ $id ]['ma'] : $id,
					'ten' => isset( $cs[ $id ] ) ? $cs[ $id ]['ten'] : VHJP_Doc::str( $r['locationName'] ),
					'ngay' => array_fill( 0, $so_ngay, 0 ), 'tong' => 0 );
			}
			$gom[ $id ]['ngay'][ $ngay - 1 ] += $tien;
			$gom[ $id ]['tong'] += $tien;
			$tong += $tien;

			$t1 = VHJP_Doc::ngay( $r['fromDate'] );
			if ( '' !== $t1 && $t1 !== $n ) {
				$nhieu_ngay[] = array( 'id' => VHJP_Doc::str( $r['id'] ),
					'coSo' => VHJP_Doc::str( $r['locationName'] ),
					'tu' => VHJP_Doc::dmy( $t1 ), 'den' => VHJP_Doc::dmy( $n ), 'tien' => $tien );
			}
		}

		/* Bốn tuần của khuôn MISA: 1–7 · 8–14 · 15–21 · 22–hết tháng. */
		$don_vi = array();
		foreach ( $gom as $g ) {
			$tuan = array( 0, 0, 0, 0 );
			foreach ( $g['ngay'] as $i => $v ) {
				$t = (int) floor( $i / 7 );
				if ( $t > 3 ) { $t = 3; }
				$tuan[ $t ] += $v;
			}
			$g['tuan'] = $tuan;
			$don_vi[]  = $g;
		}
		usort( $don_vi, function ( $a, $b ) { return strcmp( $a['unitId'], $b['unitId'] ); } );

		$canh = array();
		if ( $nhieu_ngay ) {
			$canh[] = count( $nhieu_ngay ) . ' báo cáo kéo dài HƠN MỘT NGÀY — toàn bộ tiền của '
				. 'chúng dồn vào ngày cuối kỳ, vì một báo cáo nhiều ngày chỉ có một con số tổng. '
				. 'Cột ngày ấy sẽ cao bất thường; đó là do gộp, không phải bán nhiều.';
		}
		if ( ! $don_vi ) {
			$canh[] = 'Tháng này chưa có báo cáo HOÀN TẤT nào — sổ doanh thu chỉ tính báo cáo đã '
				. 'ký đủ hai phần.';
		}

		$ty_gia = self::so( get_option( 'vhjp_ty_gia_sgd', 0 ) );
		if ( $ty_gia <= 0 ) {
			$ty_gia = 1;
			$canh[] = 'CHƯA khai tỷ giá SGD nên khối SGD đang in đúng bằng số VND. Khai ở '
				. 'WordPress → JP Capsule trước khi gửi file đi.';
		}

		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam, 'soNgay' => $so_ngay,
			'tyGia' => $ty_gia, 'tongVND' => $tong, 'tongSGD' => $tong / $ty_gia,
			/* Một khu vực duy nhất: JP chưa chia khu. Giữ đúng hình dạng lồng nhau mà khuôn
			   MISA đòi, để ngày có khu thật thì chỉ thêm phần tử, không sửa giao diện. */
			'khuVuc' => array( array( 'ten' => 'JP CAPSULE', 'donVi' => $don_vi ) ),
			'baoCaoNhieuNgay' => $nhieu_ngay, 'canhBao' => $canh );
	}

	/* ════════════════════════════════════════════════════════════════ TỒN ĐẦU KỲ ═════════ */

	/**
	 * `jpGetOpening` — số cuối kỳ TRƯỚC của một ô máy hoặc một mã hàng.
	 *
	 * ⚠️ Trả cả `found` lẫn `chot`, và chúng KHÁC NHAU: `found` = có số để điền; `chot` = số ấy
	 *    thuộc một kỳ ĐÃ DUYỆT nên đừng cho sửa. Giao diện khoá ô theo `chot`.
	 */
	public static function ton_dau( $u, $ma_bc, $may_id = '', $ma_hang = '' ) {
		$bc = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', VHJP_Doc::str( $ma_bc ) );
		if ( ! $bc ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $bc['locationId'] ); }

		$may_id  = VHJP_Doc::str( $may_id );
		$ma_hang = VHJP_Doc::str( $ma_hang );
		if ( '' === $may_id && '' === $ma_hang ) {
			throw new Exception( 'Phải cho biết ô máy hoặc mã hàng' );
		}
		$cs  = VHJP_Doc::str( $bc['locationId'] );
		$tu  = VHJP_Doc::ngay( $bc['fromDate'] );

		/* Kỳ TRƯỚC = báo cáo cùng cơ sở, kết thúc TRƯỚC ngày bắt đầu kỳ này, gần nhất. */
		$truoc = null;
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_Doc::str( $r['id'] ) === VHJP_Doc::str( $ma_bc ) ) { continue; }
			if ( VHJP_Doc::str( $r['locationId'] ) !== $cs ) { continue; }
			if ( VHJP_BaoCao::TT_NHAP === VHJP_Doc::str( $r['status'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $n || ( '' !== $tu && $n >= $tu ) ) { continue; }
			if ( ! $truoc || $n > VHJP_Doc::ngay( $truoc['toDate'] ) ) { $truoc = $r; }
		}
		if ( ! $truoc ) { return array( 'ok' => true, 'found' => false, 'chot' => false ); }

		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', VHJP_Doc::str( $truoc['id'] ) ) as $r ) {
			$khop = ( '' !== $may_id && VHJP_Doc::str( $r['machineId'] ) === $may_id )
				|| ( '' !== $ma_hang && VHJP_Doc::str( $r['itemCode'] ) === $ma_hang );
			if ( ! $khop ) { continue; }
			return array( 'ok' => true, 'found' => true,
				/* 🔴 `chot` theo TRẠNG THÁI kỳ trước, không theo việc tìm thấy số. */
				'chot' => VHJP_BaoCao::TT_HOAN_TAT === VHJP_Doc::str( $truoc['status'] ),
				'tuBaoCao' => VHJP_Doc::str( $truoc['id'] ),
				'mBefore' => self::so( $r['mAfter'] ), 'cBefore' => self::so( $r['cAfter'] ),
				'hBefore' => self::so( $r['hAfter'] ),
				'hOpen' => self::so( $r['hLeft'] ),
				'stockOpen' => self::so( isset( $r['stockLeftCalc'] ) ? $r['stockLeftCalc'] : 0 ),
				'itemMisa' => VHJP_Doc::str( $r['itemMisa'] ) );
		}
		return array( 'ok' => true, 'found' => false, 'chot' => false );
	}

	/* ═════════════════════════════════════════════════════════ ĐỀ NGHỊ SỬA TỒN ĐẦU ═══════ */

	/** `jpGuiDeNghiTonDau` — nhân viên xin kế toán sửa một ô tồn đầu. */
	public static function gui_de_nghi( $u, $d ) {
		$d     = is_array( $d ) ? $d : array();
		$ma_bc = VHJP_Doc::str( isset( $d['reportId'] ) ? $d['reportId'] : '' );
		$bc    = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $bc ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) ) { VHJP_Auth::can_coso( $u, $bc['locationId'] ); }

		$ma_dong = VHJP_Doc::str( isset( $d['rowId'] ) ? $d['rowId'] : '' );
		$dong    = VHJP_Nguon::tim_mot( 'JP_Rows', 'id', $ma_dong );
		if ( ! $dong || VHJP_Doc::str( $dong['reportId'] ) !== $ma_bc ) {
			throw new Exception( 'Không tìm thấy dòng này trong báo cáo' );
		}
		$ly_do = VHJP_Doc::str( isset( $d['lyDo'] ) ? $d['lyDo'] : '' );
		/* 🔴 BẮT BUỘC CÓ LÝ DO. Không có thì kế toán duyệt bằng gì, và sáu tháng sau dựng lại
		   căn cứ bằng gì. */
		if ( '' === $ly_do ) { throw new Exception( 'Phải ghi lý do — kế toán cần căn cứ để duyệt' ); }

		$la_hang = 'HANG' === VHJP_Doc::str( $dong['rowKind'] );
		$cu  = self::so( $la_hang ? ( isset( $dong['stockOpen'] ) ? $dong['stockOpen'] : 0 )
			: $dong['hOpen'] );
		$moi = self::so( isset( $d['soMoi'] ) ? $d['soMoi'] : 0 );
		if ( $moi === $cu ) { throw new Exception( 'Số mới bằng số cũ — không cần đề nghị' ); }

		/* Đang có một đề nghị CHỜ cho đúng dòng này thì đừng đẻ thêm cái nữa: kế toán mở ra
		   thấy hai dòng cùng xin sửa một ô, duyệt cái nào cũng đúng mà kết quả khác nhau. */
		foreach ( VHJP_Nguon::tim( 'JP_DeNghi', 'rowId', $ma_dong ) as $x ) {
			if ( self::DN_CHO === VHJP_Doc::str( $x['trangThai'] ) ) {
				throw new Exception( 'Dòng này đã có một đề nghị đang chờ kế toán duyệt.' );
			}
		}

		$p = VHJP_Ma::them( 'JP_DeNghi', 'DN', array(
			'loai' => 'TON_DAU', 'reportId' => $ma_bc, 'rowId' => $ma_dong,
			'locationId' => VHJP_Doc::str( $bc['locationId'] ),
			'locationName' => VHJP_Doc::str( $bc['locationName'] ),
			'itemCode' => VHJP_Doc::str( $dong['itemCode'] ),
			'itemMisa' => VHJP_Doc::str( $dong['itemMisa'] ),
			'itemName' => VHJP_Doc::str( $dong['itemName'] ),
			'soCu' => $cu, 'soMoi' => $moi, 'lyDo' => $ly_do,
			'userId' => VHJP_Doc::str( $u['id'] ), 'userName' => VHJP_Doc::str( $u['hoTen'] ),
			'guiLuc' => VHJP_Ma::hom_nay() . ' ' . gmdate( 'H:i:s' ),
			'trangThai' => self::DN_CHO,
		) );
		if ( ! is_array( $p ) ) { throw new Exception( 'Không ghi được đề nghị' ); }
		VHJP_NhatKy::ghi( $u, 'GUI_DE_NGHI', $ma_bc, VHJP_Doc::str( $p['id'] ),
			$cu . ' → ' . $moi );
		return array( 'ok' => true, 'id' => VHJP_Doc::str( $p['id'] ),
			'msg' => 'Đã gửi đề nghị sửa tồn đầu từ ' . $cu . ' thành ' . $moi
				. '. Kế toán duyệt xong thì số mới tự vào ô.' );
	}

	/** `jpKtDanhSachDeNghi` */
	public static function ds_de_nghi( $u, $chi_cho = false ) {
		self::can_kt( $u );
		$ds = array(); $so_cho = 0;
		foreach ( VHJP_Nguon::doc( 'JP_DeNghi' ) as $x ) {
			$cho = self::DN_CHO === VHJP_Doc::str( $x['trangThai'] );
			if ( $cho ) { $so_cho++; }
			if ( $chi_cho && ! $cho ) { continue; }
			$x['soCu']  = self::so( $x['soCu'] );
			$x['soMoi'] = self::so( $x['soMoi'] );
			$ds[] = $x;
		}
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['id'], (string) $a['id'] ); } );
		return array( 'ok' => true, 'soCho' => $so_cho, 'rows' => $ds );
	}

	/**
	 * `jpKtXuLyDeNghi` — duyệt hoặc từ chối.
	 *
	 * 🔴 DUYỆT THÌ GHI SỐ MỚI VÀO Ô VÀ TÍNH LẠI CẢ BÁO CÁO. Chỉ đổi trạng thái đề nghị mà không
	 *    đụng vào ô là kế toán bấm "Duyệt", thấy dòng chuyển sang xanh, và số trong báo cáo vẫn
	 *    y nguyên — không ai phát hiện cho tới lúc đối chiếu hàng.
	 */
	public static function xu_ly_de_nghi( $u, $d ) {
		self::can_kt( $u );
		$d  = is_array( $d ) ? $d : array();
		$ma = VHJP_Doc::str( isset( $d['id'] ) ? $d['id'] : '' );
		$x  = VHJP_Nguon::tim_mot( 'JP_DeNghi', 'id', $ma );
		if ( ! $x ) { throw new Exception( 'Không tìm thấy đề nghị' ); }
		if ( self::DN_CHO !== VHJP_Doc::str( $x['trangThai'] ) ) {
			throw new Exception( 'Đề nghị này đã được xử lý rồi' );
		}
		$tt = self::DN_DUYET === VHJP_Doc::str( isset( $d['trangThai'] ) ? $d['trangThai'] : '' )
			? self::DN_DUYET : self::DN_TU_CHOI;
		$gc = VHJP_Doc::str( isset( $d['ghiChu'] ) ? $d['ghiChu'] : '' );
		if ( self::DN_TU_CHOI === $tt && '' === $gc ) {
			throw new Exception( 'Từ chối thì phải ghi lý do — nhân viên sẽ đọc câu này' );
		}

		$da_ghi = 0;
		if ( self::DN_DUYET === $tt ) {
			$dong = VHJP_Nguon::tim_mot( 'JP_Rows', 'id', VHJP_Doc::str( $x['rowId'] ) );
			if ( ! $dong ) { throw new Exception( 'Dòng của đề nghị này không còn nữa' ); }
			$o = 'HANG' === VHJP_Doc::str( $dong['rowKind'] ) ? 'stockOpen' : 'hOpen';
			VHJP_Nguon::sua( 'JP_Rows', VHJP_Doc::str( $x['rowId'] ),
				array( $o => self::so( $x['soMoi'] ) ) );
			$da_ghi = 1;
			/* Tính lại cả báo cáo: đổi tồn đầu là đổi "còn lại", đổi lệch, đổi cảnh báo. */
			try { self::tinh_lai( VHJP_Doc::str( $x['reportId'] ) ); }
			catch ( Throwable $e ) { $da_ghi = 2; }
		}

		VHJP_Nguon::sua( 'JP_DeNghi', $ma, array( 'trangThai' => $tt,
			'ktBy' => VHJP_Doc::str( $u['hoTen'] ),
			'ktLuc' => VHJP_Ma::hom_nay() . ' ' . gmdate( 'H:i:s' ), 'ktGhiChu' => $gc ) );
		VHJP_NhatKy::ghi( $u, 'XU_LY_DE_NGHI', VHJP_Doc::str( $x['reportId'] ), $ma, $tt );

		$msg = self::DN_DUYET === $tt
			? 'Đã duyệt: tồn đầu đổi từ ' . self::so( $x['soCu'] ) . ' thành '
				. self::so( $x['soMoi'] ) . '.'
			: 'Đã từ chối đề nghị. Nhân viên sẽ đọc được lý do.';
		if ( 2 === $da_ghi ) {
			$msg .= ' ⚠️ Số đã ghi vào ô nhưng KHÔNG tính lại được cả báo cáo — mở báo cáo ra '
				. 'rồi bấm Lưu để nó tính lại.';
		}
		return array( 'ok' => true, 'trangThai' => $tt, 'msg' => $msg );
	}

	/**
	 * Tính lại một báo cáo từ dữ liệu ĐANG NẰM TRONG SỔ, rồi ghi số mới lên đầu báo cáo.
	 *
	 * 🔴 ĐI ĐÚNG ĐƯỜNG TÍNH CỦA `VHJP_BaoCao::luu()` — cùng `VHJP_Tinh::dong()` cho từng dòng,
	 *    cùng `VHJP_Tinh::bao_cao()` cho đầu báo cáo. Tự cộng lấy ở đây là hai đường tính cho
	 *    cùng một báo cáo, và ngày chúng lệch nhau thì không ai biết bên nào đúng.
	 *
	 * ⚠️ KHÔNG đụng vào chữ ký. Đây là lượt tính lại do KẾ TOÁN duyệt một đề nghị, không phải
	 *    nhân viên sửa số — huỷ chữ ký ở đây là kế toán tự xoá chữ ký của chính mình.
	 */
	public static function tinh_lai( $ma_bc ) {
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$head  = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $head ) { throw new Exception( 'Không tìm thấy báo cáo để tính lại' ); }

		$loai = VHJP_Doc::str( $head['machineType'] );
		$dong = array();
		foreach ( VHJP_Nguon::tim( 'JP_Rows', 'reportId', $ma_bc ) as $r ) {
			$dong[] = VHJP_Tinh::dong( $r, $loai );
		}
		$head = VHJP_Tinh::bao_cao( $head, $dong );

		foreach ( $dong as $r ) {
			$o = array();
			foreach ( array( 'mActual', 'cActual', 'collection', 'amount', 'soldQty', 'hLeft',
				'xuTong', 'stockLeftCalc', 'refundAmt', 'refundQty', 'rowKind' ) as $c ) {
				if ( array_key_exists( $c, $r ) ) { $o[ $c ] = $r[ $c ]; }
			}
			if ( $o ) { VHJP_Nguon::sua( 'JP_Rows', VHJP_Doc::str( $r['id'] ), $o ); }
		}
		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'revMeter' => $head['revMeter'], 'revBank' => $head['revBank'],
			'revCashMeter' => $head['revCashMeter'],
			'revHang' => $head['revHang'], 'lechTienHang' => $head['lechTienHang'],
			'adjMachine' => $head['adjMachine'],
			'refundCustomer' => $head['refundCustomer'], 'refundRows' => $head['refundRows'],
			'cashActual' => $head['cashActual'], 'totalSubmit' => $head['totalSubmit'],
			'warnCount' => $head['warnCount'],
		) );
		return $head;
	}

	/* ══════════════════════════════════════════════════════════════ MỞ LẠI BÁO CÁO ═══════ */

	/**
	 * `jpReopenIn24h` — nhân viên tự mở lại báo cáo MÌNH vừa nộp, trong 24 giờ.
	 *
	 * 🔴 CHỈ MỞ ĐƯỢC BÁO CÁO **CHƯA AI KÝ**. Đã có một chữ ký nghĩa là kế toán đã soát; mở lại
	 *    lúc ấy là xoá công soát của người khác mà họ không biết. Ca ấy đi đường `jpKtReopen`,
	 *    tức phải có kế toán bấm.
	 *
	 * ⚠️ Đếm 24 giờ từ LÚC NỘP, không từ lúc tạo. Báo cáo gõ dở cả tuần rồi mới nộp thì tính
	 *    từ lúc tạo là hết hạn ngay khi vừa nộp xong.
	 */
	public static function mo_lai_24h( $u, $ma_bc, $ly_do = '' ) {
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$r     = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) && VHJP_Doc::str( $r['userId'] ) !== VHJP_Doc::str( $u['id'] ) ) {
			throw new Exception( 'Báo cáo này không phải của bạn' );
		}
		$tt = VHJP_Doc::str( $r['status'] );
		if ( VHJP_BaoCao::TT_NHAP === $tt || VHJP_BaoCao::TT_CAN_SUA === $tt ) {
			return array( 'ok' => true, 'status' => $tt, 'msg' => 'Báo cáo đang sửa được rồi.' );
		}
		$k = VHJP_BaoCao::chu_ky( $r );
		if ( '' !== VHJP_Doc::str( $k['rev']['by'] ) || '' !== VHJP_Doc::str( $k['stock']['by'] ) ) {
			throw new Exception( 'Kế toán đã ký một phần báo cáo này — không tự mở lại được. '
				. 'Nhờ kế toán trả về nếu cần sửa.' );
		}
		$nop = VHJP_Doc::str( $r['submittedAt'] );
		if ( '' === $nop ) { throw new Exception( 'Báo cáo chưa có mốc nộp' ); }
		$gio = ( time() + 7 * 3600 - strtotime( $nop . ' UTC' ) ) / 3600;
		if ( $gio > self::GIO_MO_LAI ) {
			throw new Exception( 'Đã quá ' . self::GIO_MO_LAI . ' giờ kể từ lúc nộp ('
				. round( $gio ) . ' giờ) — nhờ kế toán trả về.' );
		}

		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'status' => VHJP_BaoCao::TT_NHAP, 'submittedAt' => null ) );
		VHJP_NhatKy::ghi( $u, 'REOPEN_24H', $ma_bc, '', VHJP_Doc::str( $ly_do ) );
		return array( 'ok' => true, 'status' => VHJP_BaoCao::TT_NHAP,
			'msg' => 'Đã mở lại báo cáo. Sửa xong nhớ nộp lại.' );
	}

	/**
	 * `jpKtReopen` — kế toán mở lại một báo cáo ĐÃ HOÀN TẤT.
	 *
	 * 🔴 HOÀN KHO TRƯỚC. Báo cáo hoàn tất đã trừ lớp tồn và ghi giá vốn vào 632; mở lại mà không
	 *    hoàn là sổ 632 còn dòng của một báo cáo đang sửa, và lượt duyệt sau trừ thêm lần nữa.
	 *
	 * ⚠️ ĐẾM SỐ BÁO CÁO KỲ SAU đã lấy số cuối của kỳ này làm tồn đầu — sửa số ở đây là mọi kỳ
	 *    sau lệch theo, mà chúng thì đã chốt rồi.
	 */
	public static function kt_mo_lai( $u, $ma_bc, $ly_do = '' ) {
		self::can_kt( $u );
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$ly_do = VHJP_Doc::str( $ly_do );
		if ( '' === $ly_do ) { throw new Exception( 'Phải ghi lý do mở lại' ); }
		$r = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		$tt = VHJP_Doc::str( $r['status'] );
		if ( VHJP_BaoCao::TT_HOAN_TAT !== $tt ) {
			throw new Exception( 'Chỉ mở lại được báo cáo đã HOÀN TẤT. Báo cáo này đang: ' . $tt );
		}

		$kho = null;
		try { $kho = VHJP_Kho::hoan_bao_cao( $u, $ma_bc ); }
		catch ( Throwable $e ) { $kho = array( 'loi' => $e->getMessage() ); }

		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'status' => VHJP_BaoCao::TT_CAN_SUA,
			'apprBy' => '', 'apprAt' => null,
			'apprRevBy' => '', 'apprRevAt' => null,
			'apprStockBy' => '', 'apprStockAt' => null,
			'rejectBy' => VHJP_Doc::str( $u['hoTen'] ),
			'rejectAt' => gmdate( 'Y-m-d H:i:s', time() + 7 * 3600 ),
			'rejectPart' => 'ALL', 'rejectReason' => $ly_do ) );

		/* Kỳ SAU của cùng cơ sở đã chốt dựa trên số cuối của kỳ này. */
		$sau = 0;
		$n   = VHJP_Doc::ngay( $r['toDate'] );
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $x ) {
			if ( VHJP_Doc::str( $x['id'] ) === $ma_bc ) { continue; }
			if ( VHJP_Doc::str( $x['locationId'] ) !== VHJP_Doc::str( $r['locationId'] ) ) { continue; }
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $x['status'] ) ) { continue; }
			if ( VHJP_Doc::ngay( $x['fromDate'] ) > $n ) { $sau++; }
		}

		VHJP_NhatKy::ghi( $u, 'KT_REOPEN', $ma_bc, '', $ly_do );
		return array( 'ok' => true, 'status' => VHJP_BaoCao::TT_CAN_SUA, 'warnLater' => $sau,
			'kho' => $kho,
			'msg' => 'Đã mở lại báo cáo và huỷ hai chữ ký.' . VHJP_Duyet::kho_msg( $kho, true ) );
	}

	/**
	 * `jpSuaKyBaoCao` — đổi khoảng ngày của một báo cáo.
	 *
	 * ⚠️ CHỈ ĐỔI ĐƯỢC KHI BÁO CÁO CÒN SỬA ĐƯỢC. Đổi kỳ của một báo cáo đã duyệt là đổi kỳ của
	 *    một con số đã vào sổ 632 theo ngày cuối kỳ cũ.
	 */
	public static function sua_ky( $u, $ma_bc, $tu, $den ) {
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$r     = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) ) {
			if ( VHJP_Doc::str( $r['userId'] ) !== VHJP_Doc::str( $u['id'] ) ) {
				throw new Exception( 'Báo cáo này không phải của bạn' );
			}
		}
		$tt = VHJP_Doc::str( $r['status'] );
		if ( VHJP_BaoCao::TT_NHAP !== $tt && VHJP_BaoCao::TT_CAN_SUA !== $tt ) {
			throw new Exception( 'Báo cáo đang ở trạng thái ' . $tt . ' nên không đổi kỳ được. '
				. 'Mở lại báo cáo trước.' );
		}
		$t1 = VHJP_Doc::ngay( $tu );
		if ( '' === $t1 ) { throw new Exception( 'Chọn ngày bắt đầu kỳ' ); }
		$t2 = VHJP_Doc::ngay( $den );
		if ( '' === $t2 ) { $t2 = $t1; }
		if ( $t2 < $t1 ) { throw new Exception( 'Ngày kết thúc sớm hơn ngày bắt đầu' ); }

		/* Kỳ CHỒNG kỳ của cùng cơ sở là tiền đếm hai lần — chặn ngay, đừng để màn Quét dây
		   chuyền phát hiện sau khi cả hai đã duyệt. */
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $x ) {
			if ( VHJP_Doc::str( $x['id'] ) === $ma_bc ) { continue; }
			if ( VHJP_Doc::str( $x['locationId'] ) !== VHJP_Doc::str( $r['locationId'] ) ) { continue; }
			if ( VHJP_BaoCao::TT_NHAP === VHJP_Doc::str( $x['status'] ) ) { continue; }
			$a = VHJP_Doc::ngay( $x['fromDate'] );
			$b = VHJP_Doc::ngay( $x['toDate'] );
			if ( '' === $a || '' === $b ) { continue; }
			if ( $t1 <= $b && $t2 >= $a ) {
				throw new Exception( 'Kỳ này chồng lên báo cáo ' . VHJP_Doc::str( $x['id'] )
					. ' (' . VHJP_Doc::dmy( $a ) . ' – ' . VHJP_Doc::dmy( $b )
					. ') của cùng cơ sở — tiền sẽ bị tính hai lần.' );
			}
		}

		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array( 'fromDate' => $t1, 'toDate' => $t2 ) );
		VHJP_NhatKy::ghi( $u, 'SUA_KY', $ma_bc, '', $t1 . ' → ' . $t2 );
		return array( 'ok' => true, 'fromDate' => $t1, 'toDate' => $t2,
			'msg' => 'Đã đổi kỳ sang ' . VHJP_Doc::dmy( $t1 ) . ' – ' . VHJP_Doc::dmy( $t2 ) . '.' );
	}
}
