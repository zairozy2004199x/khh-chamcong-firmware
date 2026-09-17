<?php
/**
 * PHIẾU LƯƠNG CỦA TÔI — nhân viên tự xem lương tháng của CHÍNH MÌNH trên trạm.
 *
 * =================================================================================================
 * VIỆC NÓ GIẢI QUYẾT
 * =================================================================================================
 * Bảng lương đã tự ra từ giờ đã chấm (`VHCC_BangLuong::dung`), nhưng nó chỉ có ở trang quản trị,
 * và cửa vào là bậc Kế toán. Nhân viên muốn biết tháng rồi mình được bao nhiêu thì đi hỏi cửa
 * hàng trưởng, cửa hàng trưởng mở bảng CẢ CƠ SỞ ra đọc hộ một dòng — tức mỗi câu hỏi là một lần
 * lương của hai mươi người khác bày ra trên màn hình giữa quầy.
 *
 * =================================================================================================
 * 🔴 BỐN CHỐT
 * =================================================================================================
 * 1. KHÔNG CÔNG BỐ THÌ KHÔNG AI THẤY GÌ. Kế toán gõ khoản cộng / khoản trừ rải ra suốt tháng;
 *    trong lúc ấy con số trong hệ đúng một nửa. Cho xem sống thì mỗi ngày người ta thấy một số
 *    khác, và mỗi lần nó tụt xuống trông y như vừa bị trừ tiền. Nên phải có MỘT động tác cố ý:
 *    kế toán bấm "Công bố tháng này", và trước lúc ấy màn nhân viên nói thẳng "chưa công bố".
 *
 * 2. 🔴 CHỈ PHIẾU CỦA CHÍNH MÌNH, VÀ MÃ LẤY TỪ PHIÊN. `VHCC_BangLuong::dung()` trả về CẢ CƠ SỞ —
 *    lương, căn cước, khoản phạt của mọi người. Lớp này lọc xuống đúng mấy dòng của người đang
 *    đăng nhập rồi mới trả ra, và KHÔNG mang theo `tong` của cơ sở. Quên một trong hai là rò cả
 *    bảng lương qua một cửa mà nhân viên thường nào cũng gọi được.
 *
 * 3. 🔴 ĐÂY LÀ SỐ CỦA HỆ, KHÔNG PHẢI SỐ CHUYỂN KHOẢN. Mấy cột BHXH và giờ thêm cố ý để trống cho
 *    kế toán điền ngoài hệ (xem đầu `class-vhcc-bang-luong.php`). Gọi con số này là "lương thực
 *    nhận" thì ai thấy tiền về ít hơn sẽ kết luận mình bị bớt — nên màn phải nói ra câu ấy, và
 *    lớp này trả kèm cờ `daDu` để màn không phải tự đoán.
 *
 * 4. ĐỌC, KHÔNG SỬA. Lớp này không có lấy một đường ghi nào vào số liệu lương. Thấy sai thì báo
 *    lượt chấm sai (`VHCC_Cham::nv_bao_sai`) hoặc hỏi quản lý — phiếu lương mở cho người ta CÁI
 *    CỬA, không mở cho họ cây bút.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_PhieuLuong {

	/** Sổ công bố, nằm trong bảng `cai_dat`: [ khoá cơ sở => [ 'YYYY-MM' => [ luc, boi ] ] ]. */
	const O = 'PHIEU_LUONG_CONG_BO';

	/** Công bố = mở lương cho cả cơ sở đọc → cùng bậc với chính bảng lương. */
	const QUYEN = 'luong';

	/** Nhiều nhất bấy nhiêu tháng hiện trong ô chọn của nhân viên. */
	const THANG_TOI_DA = 12;

	/* ====================================================================== sổ công bố */

	private static function khoa_cs( $coso ) {
		return VHCC_Luong::bo_chu( preg_replace( '/^CS_/i', '', trim( (string) $coso ) ) );
	}

	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return is_array( $d ) ? $d : array();
	}

	public static function da_cong_bo( $coso, $thang, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $k || '' === $tt ) { return false; }
		return ! empty( $so[ $k ][ $tt ] );
	}

	/** Tháng đã công bố của một cơ sở, mới nhất trước. */
	public static function thang_cua_coso( $coso, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		if ( '' === $k || empty( $so[ $k ] ) || ! is_array( $so[ $k ] ) ) { return array(); }
		$ds = array_keys( $so[ $k ] );
		rsort( $ds );
		return $ds;
	}

	/**
	 * Kế toán bật / tắt công bố một tháng của một cơ sở.
	 *
	 * ⚠️ TẮT ĐƯỢC, VÀ TẮT LÀ VIỆC THẬT. Công bố nhầm tháng chưa gõ xong khoản trừ thì phải rút
	 *    lại được ngay, chứ không phải chờ sửa cho đủ rồi mới dám nhìn mặt nhân viên.
	 */
	public static function cong_bo( $u, $coso, $thang, $bat = true ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Công bố phiếu lương' ) );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền trên cơ sở "' . $coso . '".' );
		}
		$k  = self::khoa_cs( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $k || '' === $tt ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở hoặc tháng.' );
		}
		$so = self::so();
		if ( $bat ) {
			$so[ $k ][ $tt ] = array(
				'luc' => current_time( 'mysql' ),
				'boi' => isset( $u['name'] ) ? (string) $u['name'] : '',
			);
		} else {
			unset( $so[ $k ][ $tt ] );
			if ( empty( $so[ $k ] ) ) { unset( $so[ $k ] ); }
		}
		VHCC_Luong::dat_cai_dat( self::O, $so, $u );
		return array( 'ok' => true, 'coso' => $coso, 'thang' => $tt, 'bat' => (bool) $bat );
	}

	/* ====================================================================== cửa của nhân viên */

	/**
	 * Cơ sở mà người này được phép hỏi phiếu lương.
	 *
	 * ⚠️ Dùng đúng danh sách mà đường chấm công đang dùng (`ds_coso_cham_cua_nv`), không dựng
	 *    danh sách riêng — hai danh sách rồi sẽ lệch nhau, và cái lệch ở đây là đọc được lương
	 *    của một cơ sở mình không thuộc về.
	 */
	public static function coso_cua( $u ) {
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) { return array(); }
		return VHCC_Online::ds_coso_cham_cua_nv( $ma, isset( $u['coso'] ) ? $u['coso'] : '' );
	}

	/**
	 * Danh sách (cơ sở, tháng) người này mở được — chỉ tháng ĐÃ CÔNG BỐ của cơ sở MÌNH THUỘC VỀ.
	 *
	 * ⚠️ KHÔNG dò xem người ấy có dòng trong tháng đó không. Dò thì mỗi lần mở màn là dựng lại
	 *    bảng lương của cả cơ sở cho mười hai tháng — một trang điện thoại không chịu nổi. Mở ra
	 *    mà không có dòng nào thì `phieu()` nói thẳng câu đó, và câu ấy cũng là một câu trả lời
	 *    đúng: tháng ấy hệ không ghi nhận giờ nào của anh/chị.
	 */
	public static function ds_thang( $u ) {
		$so = self::so();
		$ra = array();
		foreach ( self::coso_cua( $u ) as $cs ) {
			foreach ( self::thang_cua_coso( $cs, $so ) as $tt ) {
				$ra[] = array( 'coSo' => $cs, 'thang' => $tt );
				if ( count( $ra ) >= self::THANG_TOI_DA ) { return $ra; }
			}
		}
		return $ra;
	}

	/**
	 * PHIẾU LƯƠNG CỦA MỘT NGƯỜI, MỘT CƠ SỞ, MỘT THÁNG.
	 *
	 * 🔴 Ba cửa gác, theo thứ tự, và không cửa nào bỏ được:
	 *      1. mã nhân viên lấy từ PHIÊN (`$u`), không nhận từ biểu mẫu;
	 *      2. cơ sở phải nằm trong danh sách của chính người ấy;
	 *      3. tháng phải ĐÃ CÔNG BỐ.
	 *
	 * 🔴 Và một cửa nữa ở đường ra: `dung()` trả cả cơ sở, nên phải LỌC rồi mới trả, và tổng thì
	 *    cộng lại TỪ MẤY DÒNG ĐÃ LỌC chứ không mượn `tong` của bảng gốc.
	 */
	public static function phieu( $u, $coso, $thang ) {
		$ma = trim( (string) ( isset( $u['ma_nv'] ) ? $u['ma_nv'] : '' ) );
		if ( '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa bật chấm công online.' );
		}
		$cs = '';
		foreach ( self::coso_cua( $u ) as $x ) {
			if ( 0 === strcasecmp( trim( (string) $x ), trim( (string) $coso ) ) ) { $cs = $x; }
		}
		if ( '' === $cs ) {
			return array( 'ok' => false, 'error' => 'Anh/chị không thuộc cơ sở này.' );
		}
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		if ( ! self::da_cong_bo( $cs, $tt ) ) {
			return array( 'ok' => false, 'chuaCongBo' => true,
				'error' => 'Lương tháng ' . $tt . ' của ' . $cs . ' chưa được công bố. '
					. 'Kế toán còn đang nhập; công bố xong thì phiếu hiện ra ở đây.' );
		}

		$b = VHCC_BangLuong::dung( $cs, $tt );
		if ( empty( $b['ok'] ) ) { return $b; }

		/* 🔴 LỌC XUỐNG ĐÚNG MẤY DÒNG CỦA NGƯỜI NÀY. Xem chốt 2 ở đầu lớp. */
		$dong = array();
		foreach ( $b['dong'] as $d ) {
			if ( 0 !== strcasecmp( trim( (string) $d['ma'] ), $ma ) ) { continue; }
			/* Số căn cước và số thứ tự trong bảng của cơ sở không có việc gì trên phiếu của một
			   người — bỏ luôn ở đây thay vì tin màn hình sẽ không vẽ nó ra. */
			unset( $d['cccd'], $d['stt'] );
			$dong[] = $d;
		}
		if ( ! $dong ) {
			return array( 'ok' => true, 'coSo' => $cs, 'thang' => $tt, 'dong' => array(),
				'trong' => true, 'daDu' => false,
				'loi' => 'Tháng ' . $tt . ' hệ không ghi nhận giờ nào của anh/chị ở ' . $cs . '.' );
		}

		/* Tổng cộng lại TỪ MẤY DÒNG ĐÃ LỌC. Mấy khoản cộng/trừ chỉ gắn ở DÒNG CHÍNH (xem
		   `VHCC_BangLuong::dung`), nên cộng thẳng mọi dòng cũng không nhân đôi. */
		$luong_chinh = 0.0; $tong_cong = 0.0; $tong_tru = 0.0;
		$thieu_gia = 0; $thieu_gio = 0; $gio = 0.0;
		foreach ( $dong as $d ) {
			if ( null === $d['luongChinh'] ) { $thieu_gia++; } else { $luong_chinh += (float) $d['luongChinh']; }
			if ( null !== $d['gio'] ) { $gio += (float) $d['gio']; }
			$tong_cong += (float) $d['tongCong'];
			$tong_tru  += (float) $d['tongTru'];
			$thieu_gio += (int) $d['thieuGio'];
		}

		/* 🔴 CHƯA ĐỦ GIÁ THÌ KHÔNG BÀY TỔNG. Một dòng thiếu đơn giá mà vẫn cộng tổng thì con số
		   ra thấp hơn thật, và nó trông y như một tờ lương hoàn chỉnh. Ô trống bắt người ta hỏi;
		   số sai thì không ai hỏi. Cùng luật với tệp .xlsx xuất cho kế toán. */
		$du = ( 0 === $thieu_gia );

		return array(
			'ok'      => true,
			'coSo'    => $cs,
			'thang'   => $tt,
			'hoTen'   => (string) $dong[0]['ten'],
			'dong'    => $dong,
			'gio'     => round( $gio, 2 ),
			'gioTong' => (float) $dong[0]['gioTong'],
			'soNgay'  => (int) $dong[0]['soNgay'],
			'luongChinh' => $du ? round( $luong_chinh, 2 ) : null,
			'tongCong'   => round( $tong_cong, 2 ),
			'tongTru'    => round( $tong_tru, 2 ),
			'tong'       => $du ? round( $luong_chinh + $tong_cong - $tong_tru, 2 ) : null,
			'daDu'       => $du,
			'thieuGia'   => $thieu_gia,
			'thieuGio'   => $thieu_gio,
			/* ⚠️ Xem chốt 3: BHXH và giờ thêm nằm ngoài hệ, nên con số trên đây KHÔNG phải số
			   chuyển khoản. Màn phải nói ra, và trả cờ để nó khỏi tự đoán. */
			'ngoaiHe'    => array( 'BHXH', 'Lương giờ thêm' ),
		);
	}

	/** Tên các khoản cộng / trừ, để màn gọi đúng chữ kế toán đang dùng. */
	public static function ten_khoan() {
		return array( 'cong' => VHCC_ChotLuong::CONG, 'tru' => VHCC_ChotLuong::TRU );
	}
}
