<?php
/**
 * NỘP TIỀN — `JP2_08_NopTien.gs`.
 *
 * =================================================================================================
 * 🔴 HAI CỘT, VÀ CHỖ LỆCH GIỮA CHÚNG MỚI LÀ THÔNG TIN
 * =================================================================================================
 * · **NV báo nộp** — nhân viên tự khai đã nộp bao nhiêu, ngày nào, bằng tiền mặt hay chuyển khoản.
 * · **KT xác nhận** — kế toán nói đã NHẬN được bao nhiêu.
 *
 * Gộp một cột thì nhân viên khai bao nhiêu là sổ ghi bấy nhiêu, và không còn gì để đối chiếu —
 * tiền thiếu chỉ lộ ra lúc kiểm quỹ cuối tháng, khi không ai nhớ được khoản nào của ai. Tách hai
 * cột thì một dòng lệch là một câu hỏi cụ thể, hỏi được ngay hôm sau.
 *
 * =================================================================================================
 * 🔴 CHỈ BÁO CÁO **HOÀN TẤT** MỚI NHẬN TIỀN
 * =================================================================================================
 * Số phải nộp (`totalSubmit`) còn đổi được chừng nào kế toán chưa ký đủ hai phần: trả về sửa là
 * doanh thu đổi, hoàn khách đổi, lệch máy đổi. Nhận tiền trước đó là nhận theo một con số sắp
 * khác đi, và lúc nó khác thì đã có tiền nằm trong két không khớp phiếu nào.
 *
 * =================================================================================================
 * 🔴 KẾ TOÁN ĐÃ XÁC NHẬN THÌ NHÂN VIÊN KHÔNG SỬA ĐƯỢC NỮA
 * =================================================================================================
 * Sửa ngày hay xoá một lần nộp mà kế toán đã đối chiếu xong là đổi chính con số họ vừa ký. Chặn
 * ở MÁY CHỦ, không chỉ ẩn nút: ẩn nút thì gọi thẳng API là qua.
 *
 * @package VHCP_JP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_NopTien {

	const CHUA_NOP = 'CHUA_NOP';
	const MOT_PHAN = 'NOP_MOT_PHAN';
	const DA_NOP   = 'DA_NOP';

	private static function so( $v ) { return VHJP_Doc::num( $v ); }

	/** Số nhân viên phải nộp của một báo cáo. */
	public static function phai_nop( $bc ) {
		return self::so( isset( $bc['totalSubmit'] ) ? $bc['totalSubmit'] : 0 );
	}

	/**
	 * Tổng nhân viên ĐÃ BÁO nộp cho một báo cáo, gom một lượt cho nhiều báo cáo.
	 *
	 * ⚠️ Gom bằng MỘT vòng đọc rồi tra bảng, không gọi `tim()` trong vòng lặp báo cáo: 30 báo
	 *    cáo là 30 lượt quét cả bảng nộp tiền, và bảng ấy chỉ có lớn dần.
	 */
	public static function gom_nop() {
		$ra = array();
		foreach ( VHJP_Nguon::doc( 'JP_Payments' ) as $p ) {
			$k = VHJP_Doc::str( $p['reportId'] );
			if ( ! isset( $ra[ $k ] ) ) {
				$ra[ $k ] = array( 'tien' => 0, 'lan' => 0, 'boSung' => 0, 'ngayCuoi' => '',
					'cach' => array(), 'ghiChu' => array() );
			}
			$ra[ $k ]['tien'] += self::so( $p['amount'] );
			$ra[ $k ]['lan']++;
			if ( self::so( $p['isSupplement'] ) ) { $ra[ $k ]['boSung']++; }
			$n = VHJP_Doc::ngay( $p['payDate'] );
			if ( $n > $ra[ $k ]['ngayCuoi'] ) { $ra[ $k ]['ngayCuoi'] = $n; }
			$c = 'TM' === strtoupper( VHJP_Doc::str( $p['method'] ) ) ? 'Tiền mặt' : 'Chuyển khoản';
			if ( ! in_array( $c, $ra[ $k ]['cach'], true ) ) { $ra[ $k ]['cach'][] = $c; }
			$g = VHJP_Doc::str( $p['note'] );
			if ( '' !== $g ) { $ra[ $k ]['ghiChu'][] = $g; }
		}
		return $ra;
	}

	/** Tình trạng nộp suy từ SỐ TIỀN, không đọc cờ đã lưu. */
	public static function tinh_trang( $phai, $da ) {
		if ( $da <= 0 ) { return self::CHUA_NOP; }
		return $da >= $phai ? self::DA_NOP : self::MOT_PHAN;
	}

	/* ══════════════════════════════════════════════════════════════ ĐƯỜNG NHÂN VIÊN ══════ */

	/**
	 * `jpMyUnpaid` — báo cáo của CHÍNH người này còn thiếu tiền.
	 *
	 * ⚠️ Lọc theo `userId`, không theo cơ sở. Hai người cùng một cơ sở thì mỗi người nộp phần
	 *    báo cáo của mình; bày cả hai là người này ghi nộp hộ người kia rồi cả hai cùng tưởng
	 *    đã xong.
	 */
	public static function chua_nop( $u ) {
		$id  = VHJP_Doc::str( $u['id'] );
		$gom = self::gom_nop();
		$ra  = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			if ( VHJP_Doc::str( $r['userId'] ) !== $id ) { continue; }
			$phai = self::phai_nop( $r );
			$k    = VHJP_Doc::str( $r['id'] );
			$da   = isset( $gom[ $k ] ) ? $gom[ $k ]['tien'] : 0;
			if ( $da >= $phai ) { continue; }
			$ra[] = self::dong_nop( $r, $da );
		}
		usort( $ra, function ( $a, $b ) { return strcmp( $b['toDate'], $a['toDate'] ); } );
		return $ra;
	}

	private static function dong_nop( $r, $da ) {
		$phai = self::phai_nop( $r );
		return array(
			'id'           => VHJP_Doc::str( $r['id'] ),
			'locationName' => VHJP_Doc::str( $r['locationName'] ),
			'machineType'  => VHJP_Doc::str( $r['machineType'] ),
			'fromDate'     => VHJP_Doc::ngay( $r['fromDate'] ),
			'toDate'       => VHJP_Doc::ngay( $r['toDate'] ),
			'nvPayStatus'  => self::tinh_trang( $phai, $da ),
			'phaiNop'      => $phai,
			'daNop'        => $da,
			'conThieu'     => max( 0, $phai - $da ),
			'ktXacNhan'    => self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 ),
		);
	}

	/** Báo cáo này có phải của người đang gọi không — và có nhận tiền được không. */
	private static function bc_cua_toi( $u, $ma_bc ) {
		$r = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', VHJP_Doc::str( $ma_bc ) );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		if ( ! VHJP_Auth::la_kt( $u ) && VHJP_Doc::str( $r['userId'] ) !== VHJP_Doc::str( $u['id'] ) ) {
			throw new Exception( 'Báo cáo này không phải của bạn' );
		}
		return $r;
	}

	/** `jpPaymentHistory` — các lần đã nộp của một báo cáo. */
	public static function lich_su( $u, $ma_bc ) {
		self::bc_cua_toi( $u, $ma_bc );
		$ds = VHJP_Nguon::tim( 'JP_Payments', 'reportId', VHJP_Doc::str( $ma_bc ) );
		usort( $ds, function ( $a, $b ) {
			$x = strcmp( VHJP_Doc::ngay( $a['payDate'] ), VHJP_Doc::ngay( $b['payDate'] ) );
			return 0 !== $x ? $x : strcmp( (string) $a['id'], (string) $b['id'] );
		} );
		$ra = array();
		foreach ( $ds as $p ) {
			$ra[] = array(
				'id'           => VHJP_Doc::str( $p['id'] ),
				'amount'       => self::so( $p['amount'] ),
				'payDate'      => VHJP_Doc::ngay( $p['payDate'] ),
				'method'       => VHJP_Doc::str( $p['method'] ),
				'note'         => VHJP_Doc::str( $p['note'] ),
				'photoUrl'     => VHJP_Doc::str( $p['photoUrl'] ),
				'isSupplement' => self::so( $p['isSupplement'] ) ? 1 : 0,
				'createdBy'    => VHJP_Doc::str( $p['createdBy'] ),
			);
		}
		return $ra;
	}

	/**
	 * `jpAddPayment` — ghi một lần nộp.
	 *
	 * 🔴 KHÔNG NHẬN QUÁ SỐ CÒN THIẾU. Giao diện đã chặn, nhưng chặn ở đó chỉ là tiện cho người
	 *    gõ — gọi thẳng API là qua. Nhận quá là công nợ âm, và số âm ấy đi thẳng vào sổ 131.
	 *
	 * ⚠️ Ảnh chứng từ ghi TRƯỚC, phiếu ghi SAU. Ngược lại thì phiếu đã nằm trong sổ mà ảnh hỏng
	 *    giữa chừng, và không còn đường nào gắn ảnh vào phiếu ấy nữa.
	 */
	public static function them( $u, $d ) {
		$d     = is_array( $d ) ? $d : array();
		$ma_bc = VHJP_Doc::str( isset( $d['reportId'] ) ? $d['reportId'] : '' );
		$r     = self::bc_cua_toi( $u, $ma_bc );

		if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) {
			throw new Exception( 'Báo cáo chưa HOÀN TẤT nên số phải nộp còn đổi được — '
				. 'chờ kế toán ký đủ hai phần rồi hãy nộp.' );
		}
		$tien = self::so( isset( $d['amount'] ) ? $d['amount'] : 0 );
		if ( $tien <= 0 ) { throw new Exception( 'Số tiền nộp phải lớn hơn 0' ); }

		$gom  = self::gom_nop();
		$da   = isset( $gom[ $ma_bc ] ) ? $gom[ $ma_bc ]['tien'] : 0;
		$phai = self::phai_nop( $r );
		$con  = $phai - $da;
		if ( $con <= 0 ) { throw new Exception( 'Báo cáo này đã nộp đủ rồi' ); }
		if ( $tien > $con ) {
			throw new Exception( 'Vượt số còn thiếu (' . number_format( $con, 0, ',', '.' )
				. 'đ). Nhận quá là công nợ âm, và số âm ấy đi thẳng vào sổ 131.' );
		}

		$ngay = VHJP_Doc::ngay( isset( $d['payDate'] ) ? $d['payDate'] : '' );
		if ( '' === $ngay ) { $ngay = VHJP_Ma::hom_nay(); }
		$cach = 'TM' === strtoupper( VHJP_Doc::str( isset( $d['method'] ) ? $d['method'] : '' ) )
			? 'TM' : 'CK';

		/* Ảnh trước, phiếu sau — xem khối ⚠️ ở trên. */
		$anh_url = ''; $anh_id = '';
		$du = VHJP_Doc::str( isset( $d['dataUrl'] ) ? $d['dataUrl'] : '' );
		if ( '' !== $du ) {
			$a = VHJP_Anh::tai_len( $u, array( 'reportId' => $ma_bc, 'scope' => 'NOP_TIEN',
				'kind' => 'CHUNG_TU', 'dataUrl' => $du ) );
			if ( ! empty( $a['photo'] ) ) {
				$anh_url = VHJP_Doc::str( isset( $a['photo']['url'] ) ? $a['photo']['url'] : '' );
				$anh_id  = VHJP_Doc::str( isset( $a['photo']['id'] ) ? $a['photo']['id'] : '' );
			}
		}

		$p = VHJP_Ma::them( 'JP_Payments', 'NT', array(
			'reportId' => $ma_bc, 'locationId' => VHJP_Doc::str( $r['locationId'] ),
			'amount' => $tien, 'payDate' => $ngay, 'method' => $cach,
			'note' => VHJP_Doc::str( isset( $d['note'] ) ? $d['note'] : '' ),
			'photoId' => $anh_id, 'photoUrl' => $anh_url,
			/* "Bổ sung" = đã có lần nộp trước. Suy tại chỗ, không nhận cờ từ giao diện: giao
			   diện tính bằng bản dữ liệu nó đang giữ, mà bản ấy có thể đã cũ. */
			'isSupplement' => $da > 0 ? 1 : 0,
			'createdBy' => VHJP_Doc::str( $u['hoTen'] ), 'createdAt' => VHJP_Ma::hom_nay(),
		) );
		if ( ! is_array( $p ) ) { throw new Exception( 'Không ghi được phiếu nộp tiền' ); }

		self::dong_bo( $ma_bc );
		VHJP_NhatKy::ghi( $u, 'ADD_PAYMENT', $ma_bc, VHJP_Doc::str( $p['id'] ), $tien );

		$moi = $da + $tien;
		return array( 'ok' => true, 'id' => VHJP_Doc::str( $p['id'] ),
			'daNop' => $moi, 'conThieu' => max( 0, $phai - $moi ),
			'msg' => 'Đã ghi nộp ' . number_format( $tien, 0, ',', '.' ) . 'đ. '
				. ( $moi >= $phai ? 'Báo cáo này đã nộp đủ.'
					: 'Còn thiếu ' . number_format( $phai - $moi, 0, ',', '.' ) . 'đ.' ) );
	}

	/**
	 * Kế toán đã xác nhận rồi thì nhân viên không sửa/xoá được lần nộp nào nữa.
	 *
	 * 🔴 CHẶN Ở MÁY CHỦ. Giao diện có ẩn nút, nhưng ẩn nút thì gọi thẳng API là qua — và cái
	 *    bị đổi là chính con số kế toán vừa ký.
	 */
	private static function can_chua_xac_nhan( $u, $r ) {
		if ( VHJP_Auth::la_kt( $u ) ) { return; }
		if ( self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 ) > 0 ) {
			throw new Exception( 'Kế toán đã xác nhận nhận tiền của báo cáo này — '
				. 'không tự sửa được nữa. Báo kế toán nếu có nhầm.' );
		}
	}

	/** `jpSuaNgayNop` — đổi NGÀY của một lần nộp, giữ nguyên ghi chú và ảnh. */
	public static function sua_ngay( $u, $ma, $ngay ) {
		$ma = VHJP_Doc::str( $ma );
		$p  = VHJP_Nguon::tim_mot( 'JP_Payments', 'id', $ma );
		if ( ! $p ) { throw new Exception( 'Không tìm thấy lần nộp này' ); }
		$r = self::bc_cua_toi( $u, $p['reportId'] );
		self::can_chua_xac_nhan( $u, $r );

		$n = VHJP_Doc::ngay( $ngay );
		if ( '' === $n ) { throw new Exception( 'Chọn ngày nộp mới' ); }
		VHJP_Nguon::sua( 'JP_Payments', $ma, array( 'payDate' => $n ) );
		self::dong_bo( VHJP_Doc::str( $p['reportId'] ) );
		VHJP_NhatKy::ghi( $u, 'SUA_NGAY_NOP', VHJP_Doc::str( $p['reportId'] ), $ma, $n );
		return array( 'ok' => true, 'msg' => 'Đã đổi ngày nộp sang ' . VHJP_Doc::dmy( $n ) . '.' );
	}

	/**
	 * `jpDeletePayment` — xoá một lần nộp.
	 *
	 * ⚠️ XOÁ LUÔN ẢNH chứng từ đi kèm. Để lại là thư mục đầy ảnh không bản ghi nào trỏ tới, và
	 *    không ai biết để dọn.
	 */
	public static function xoa( $u, $ma ) {
		$ma = VHJP_Doc::str( $ma );
		$p  = VHJP_Nguon::tim_mot( 'JP_Payments', 'id', $ma );
		if ( ! $p ) { throw new Exception( 'Không tìm thấy lần nộp này' ); }
		$r = self::bc_cua_toi( $u, $p['reportId'] );
		self::can_chua_xac_nhan( $u, $r );

		$anh = VHJP_Doc::str( $p['photoId'] );
		if ( '' !== $anh ) {
			try { VHJP_Anh::xoa( $u, $anh ); } catch ( Throwable $e ) { /* ảnh mất rồi thì thôi */ }
		}
		VHJP_Nguon::xoa( 'JP_Payments', $ma );
		self::dong_bo( VHJP_Doc::str( $p['reportId'] ) );
		VHJP_NhatKy::ghi( $u, 'DEL_PAYMENT', VHJP_Doc::str( $p['reportId'] ), $ma,
			self::so( $p['amount'] ) );
		return array( 'ok' => true, 'msg' => 'Đã xoá lần nộp '
			. number_format( self::so( $p['amount'] ), 0, ',', '.' ) . 'đ.' );
	}

	/**
	 * Ghi lại tình trạng nộp lên đầu báo cáo sau mỗi lần đổi.
	 *
	 * ⚠️ Mấy ô này là BẢN SAO cho nhanh, không phải nguồn. Nguồn vẫn là tổng `JP_Payments` —
	 *    mọi phép tính ở đây đều cộng lại từ bảng ấy. Giữ bản sao vì bảng nộp tiền của kế toán
	 *    lọc theo tình trạng, và lọc trên một cột có sẵn thì nhanh hơn cộng lại từng dòng.
	 */
	private static function dong_bo( $ma_bc ) {
		$gom  = self::gom_nop();
		$g    = isset( $gom[ $ma_bc ] ) ? $gom[ $ma_bc ] : null;
		$r    = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { return; }
		$phai = self::phai_nop( $r );
		$da   = $g ? $g['tien'] : 0;
		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'nvPaid'      => $da >= $phai && $phai > 0 ? 1 : 0,
			'nvPaidDate'  => $g ? $g['ngayCuoi'] : null,
			'nvPayStatus' => self::tinh_trang( $phai, $da ),
		) );
	}

	/* ══════════════════════════════════════════════════════════════ ĐƯỜNG KẾ TOÁN ════════ */

	/**
	 * `jpKtPaymentBoard` — bảng nhân viên báo nộp so với kế toán xác nhận.
	 *
	 * 🔴 CỬA DUY NHẤT kế toán xem được nhân viên đã báo nộp gì. `jpPaymentHistory` phải mở từng
	 *    báo cáo mới thấy, nên thiếu cột "cách nộp" ở đây là đi tìm tiền mặt của một khoản đã
	 *    chuyển khoản — hoặc xác nhận đã nhận tiền mặt mà trong két không có.
	 */
	public static function bang_nop( $u, $f = array() ) {
		$f   = is_array( $f ) ? $f : array();
		$tu  = VHJP_Doc::ngay( isset( $f['fromDate'] ) ? $f['fromDate'] : '' );
		$den = VHJP_Doc::ngay( isset( $f['toDate'] ) ? $f['toDate'] : '' );
		$chi_lech = ! empty( $f['onlyLech'] );
		$gom = self::gom_nop();

		$rows = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' !== $tu && $n < $tu ) { continue; }
			if ( '' !== $den && $n > $den ) { continue; }
			$k    = VHJP_Doc::str( $r['id'] );
			$g    = isset( $gom[ $k ] ) ? $gom[ $k ] : null;
			$phai = self::phai_nop( $r );
			$nv   = $g ? $g['tien'] : 0;
			$kt   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
			$lech = $nv - $kt;
			if ( $chi_lech && 0 === $lech ) { continue; }
			$rows[] = array(
				'id' => $k, 'locationName' => VHJP_Doc::str( $r['locationName'] ),
				'period' => VHJP_Doc::dmy( $r['fromDate'] ) . ' – ' . VHJP_Doc::dmy( $r['toDate'] ),
				'userName' => VHJP_Doc::str( $r['userName'] ),
				'machineType' => VHJP_Doc::str( $r['machineType'] ),
				'status' => VHJP_Doc::str( $r['status'] ),
				'phaiNop' => $phai, 'nvPaid' => $nv, 'ktPaid' => $kt, 'lech' => $lech,
				'nvCachThu' => $g ? implode( ' + ', $g['cach'] ) : '',
				'nvPaidDate' => $g ? $g['ngayCuoi'] : '',
				'soLanNop' => $g ? $g['lan'] : 0,
				'coBoSung' => $g ? $g['boSung'] : 0,
				'nvPayNote' => $g ? implode( ' · ', $g['ghiChu'] ) : '',
			);
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $b['id'], $a['id'] ); } );
		return array( 'ok' => true, 'tuNgay' => $tu, 'denNgay' => $den, 'rows' => $rows );
	}

	/**
	 * `jpCongNoNhanVien` — gom theo NGƯỜI: ai đang còn cầm tiền của công ty.
	 *
	 * ⚠️ `conNo` tính theo số KẾ TOÁN XÁC NHẬN, không theo số nhân viên tự khai. Tính theo số
	 *    tự khai thì ai khai đã nộp đủ là hết nợ trên sổ, mà tiền thì chưa về.
	 */
	public static function cong_no_nv( $u, $f = array() ) {
		$f   = is_array( $f ) ? $f : array();
		$tu  = VHJP_Doc::ngay( isset( $f['fromDate'] ) ? $f['fromDate'] : '' );
		$den = VHJP_Doc::ngay( isset( $f['toDate'] ) ? $f['toDate'] : '' );
		$gom = self::gom_nop();

		$ng = array();
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_BaoCao::TT_HOAN_TAT !== VHJP_Doc::str( $r['status'] ) ) { continue; }
			$n = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' !== $tu && $n < $tu ) { continue; }
			if ( '' !== $den && $n > $den ) { continue; }
			$uid = VHJP_Doc::str( $r['userId'] );
			if ( ! isset( $ng[ $uid ] ) ) {
				$ng[ $uid ] = array( 'userName' => VHJP_Doc::str( $r['userName'] ),
					'coSo' => array(), 'soBaoCao' => 0, 'phaiNop' => 0,
					'nvBaoNop' => 0, 'ktXacNhan' => 0 );
			}
			$cs = VHJP_Doc::str( $r['locationName'] );
			if ( '' !== $cs && ! in_array( $cs, $ng[ $uid ]['coSo'], true ) ) {
				$ng[ $uid ]['coSo'][] = $cs;
			}
			$k = VHJP_Doc::str( $r['id'] );
			$ng[ $uid ]['soBaoCao']++;
			$ng[ $uid ]['phaiNop']   += self::phai_nop( $r );
			$ng[ $uid ]['nvBaoNop']  += isset( $gom[ $k ] ) ? $gom[ $k ]['tien'] : 0;
			$ng[ $uid ]['ktXacNhan'] += self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
		}

		$tong = array( 'phaiNop' => 0, 'nvBaoNop' => 0, 'ktXacNhan' => 0, 'conNo' => 0 );
		$rows = array();
		foreach ( $ng as $x ) {
			$x['coSo']     = implode( ', ', $x['coSo'] );
			$x['conNo']    = $x['phaiNop'] - $x['ktXacNhan'];
			$x['lechTuBao'] = $x['nvBaoNop'] - $x['ktXacNhan'];
			$tong['phaiNop']   += $x['phaiNop'];
			$tong['nvBaoNop']  += $x['nvBaoNop'];
			$tong['ktXacNhan'] += $x['ktXacNhan'];
			$tong['conNo']     += $x['conNo'];
			$rows[] = $x;
		}
		usort( $rows, function ( $a, $b ) {
			/* Ai đang cầm nhiều tiền nhất lên đầu — đó là thứ người mở màn này đi tìm. */
			if ( $a['conNo'] !== $b['conNo'] ) { return $b['conNo'] > $a['conNo'] ? 1 : -1; }
			return strcmp( $a['userName'], $b['userName'] );
		} );
		return array( 'ok' => true, 'tuNgay' => $tu, 'denNgay' => $den,
			'rows' => $rows, 'tong' => $tong );
	}

	/**
	 * Kế toán XÁC NHẬN đã nhận bao nhiêu của một báo cáo. Dùng bởi `jpConfirmPaidManual` và
	 * bởi lượt đối soát ngân hàng.
	 *
	 * ⚠️ Xác nhận QUÁ số phải nộp thì chối. Nhận dư là tiền của khoản khác đang bị ghi nhầm
	 *    chỗ, và ghi nhầm chỗ thì khoản kia mãi không khớp.
	 */
	public static function xac_nhan( $u, $ma_bc, $tien ) {
		if ( ! VHJP_Auth::la_kt( $u ) ) {
			throw new Exception( 'Chỉ kế toán mới xác nhận đã nhận tiền' );
		}
		$ma_bc = VHJP_Doc::str( $ma_bc );
		$r     = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bc );
		if ( ! $r ) { throw new Exception( 'Không tìm thấy báo cáo' ); }
		$tien = self::so( $tien );
		if ( $tien < 0 ) { throw new Exception( 'Số xác nhận không được âm' ); }
		$phai = self::phai_nop( $r );
		if ( $tien > $phai ) {
			throw new Exception( 'Xác nhận ' . number_format( $tien, 0, ',', '.' )
				. 'đ nhiều hơn số phải nộp ' . number_format( $phai, 0, ',', '.' )
				. 'đ — tiền của khoản khác đang bị ghi nhầm chỗ.' );
		}
		VHJP_Nguon::sua( 'JP_Reports', $ma_bc, array(
			'ktXacNhan' => $tien,
			'ktXacNhanBy' => VHJP_Doc::str( $u['hoTen'] ),
			'ktXacNhanAt' => VHJP_Ma::hom_nay() . ' ' . gmdate( 'H:i:s' ),
			'paid' => ( $phai > 0 && $tien >= $phai ) ? 1 : 0,
			'paidDate' => ( $phai > 0 && $tien >= $phai ) ? VHJP_Ma::hom_nay() : null,
			'payStatus' => self::tinh_trang( $phai, $tien ),
		) );
		VHJP_NhatKy::ghi( $u, 'KT_XAC_NHAN_TIEN', $ma_bc, '', $tien );
		return array( 'ok' => true, 'ktXacNhan' => $tien, 'phaiNop' => $phai,
			'msg' => 'Đã xác nhận nhận ' . number_format( $tien, 0, ',', '.' ) . 'đ'
				. ( $tien >= $phai ? ' — đủ.' : ' — còn thiếu '
					. number_format( $phai - $tien, 0, ',', '.' ) . 'đ.' ) );
	}

	/* ═══════════════════════════════════════════════════════════ LỊCH SỬ THEO THÁNG ══════ */

	/**
	 * `jpMyMonthHistory` — mọi báo cáo của CHÍNH người này trong một tháng, kèm từng ô/máy.
	 *
	 * ⚠️ Danh sách "Báo cáo gần đây" chỉ có 30 bản ở mức ĐẦU báo cáo. Hết tháng nhân viên cần
	 *    soát lại TỪNG Ô mình đã báo, nên màn này trả cả dòng chi tiết.
	 */
	public static function lich_su_thang( $u, $thang, $nam ) {
		$thang = (int) $thang; $nam = (int) $nam;
		if ( $thang < 1 || $thang > 12 || $nam < 2000 ) {
			throw new Exception( 'Tháng/năm không hợp lệ' );
		}
		$tu  = sprintf( '%04d-%02d-01', $nam, $thang );
		$den = gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) );
		$id  = VHJP_Doc::str( $u['id'] );
		$gom = self::gom_nop();

		$bc = array(); $ma_bc = array();
		$tong = array( 'doanhThu' => 0, 'phaiNop' => 0, 'daNop' => 0,
			'ktXacNhan' => 0, 'hangBan' => 0 );
		foreach ( VHJP_Nguon::doc( 'JP_Reports' ) as $r ) {
			if ( VHJP_Doc::str( $r['userId'] ) !== $id ) { continue; }
			$n = VHJP_Doc::ngay( $r['toDate'] );
			if ( '' === $n || $n < $tu || $n > $den ) { continue; }
			$k    = VHJP_Doc::str( $r['id'] );
			$phai = self::phai_nop( $r );
			$da   = isset( $gom[ $k ] ) ? $gom[ $k ]['tien'] : 0;
			$kt   = self::so( isset( $r['ktXacNhan'] ) ? $r['ktXacNhan'] : 0 );
			$ma_bc[] = $k;
			$bc[] = array(
				'id' => $k, 'locationName' => VHJP_Doc::str( $r['locationName'] ),
				'machineType' => VHJP_Doc::str( $r['machineType'] ),
				'fromDate' => VHJP_Doc::ngay( $r['fromDate'] ), 'toDate' => $n,
				'status' => VHJP_Doc::str( $r['status'] ),
				'revMeter' => self::so( $r['revMeter'] ), 'totalSubmit' => $phai,
				'nvPaid' => $da, 'ktPaid' => $kt,
				'warnCount' => self::so( $r['warnCount'] ),
				'chuKy' => VHJP_BaoCao::chu_ky( $r ),
			);
			$tong['doanhThu']  += self::so( $r['revMeter'] );
			$tong['phaiNop']   += $phai;
			$tong['daNop']     += $da;
			$tong['ktXacNhan'] += $kt;
		}
		usort( $bc, function ( $a, $b ) { return strcmp( $b['toDate'], $a['toDate'] ); } );

		$rows = array();
		foreach ( VHJP_Nguon::doc( 'JP_Rows' ) as $r ) {
			$k = VHJP_Doc::str( $r['reportId'] );
			if ( ! in_array( $k, $ma_bc, true ) ) { continue; }
			$loai = VHJP_Doc::str( $r['rowKind'] );
			$rows[] = array(
				'reportId' => $k, 'rowKind' => $loai,
				'machineCode' => VHJP_Doc::str( $r['machineCode'] ),
				'itemCode' => VHJP_Doc::str( $r['itemCode'] ),
				'mBefore' => VHJP_Doc::num_hoac_trong( $r['mBefore'] ),
				'mAfter' => VHJP_Doc::num_hoac_trong( $r['mAfter'] ),
				'mActual' => self::so( $r['mActual'] ), 'amount' => self::so( $r['amount'] ),
				'soldQty' => self::so( $r['soldQty'] ), 'hLeft' => self::so( $r['hLeft'] ),
				'stockLeftCalc' => self::so( isset( $r['stockLeftCalc'] ) ? $r['stockLeftCalc'] : 0 ),
				'stockActual' => VHJP_Doc::num_hoac_trong(
					isset( $r['stockActual'] ) ? $r['stockActual'] : '' ),
				'warnCount' => 0,
			);
			if ( in_array( $loai, VHJP_Kho::DONG_CO_HANG, true ) ) {
				$tong['hangBan'] += self::so( $r['soldQty'] );
			}
		}
		return array( 'ok' => true, 'thang' => $thang, 'nam' => $nam,
			'reports' => $bc, 'rows' => $rows, 'tong' => $tong );
	}
}
