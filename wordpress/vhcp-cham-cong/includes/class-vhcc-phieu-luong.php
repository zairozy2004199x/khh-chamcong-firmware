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

	public static function khoa_cs( $coso ) {
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

		return self::dung_phieu( $ma, $cs, $tt );
	}


	/**
	 * Dựng phiếu của MỘT mã — phần sau ba cửa gác của `phieu()`. Tách ra để trang in (link có
	 * chữ ký, xem `link_in()`) dùng CHÍNH phép tính này, không có bản thứ hai.
	 *
	 * ⚠️ Không tự gác gì: ai gọi phải gác trước (phiên + cơ sở + đã công bố, hoặc chữ ký).
	 */
	private static function dung_phieu( $ma, $cs, $tt ) {
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
		$luong_chinh = 0.0; $tong_cong = 0.0; $tong_tru = 0.0; $tong_bh = 0.0;
		$thieu_gia = 0; $thieu_gio = 0; $gio = 0.0;
		foreach ( $dong as $d ) {
			if ( null === $d['luongChinh'] ) { $thieu_gia++; } else { $luong_chinh += (float) $d['luongChinh']; }
			if ( null !== $d['gio'] ) { $gio += (float) $d['gio']; }
			$tong_cong += (float) $d['tongCong'];
			$tong_tru  += (float) $d['tongTru'];
			/* 🔴 BHXH NAY LÀ SỐ CỦA HỆ, KHÔNG CÒN "NGOÀI HỆ". Anh Thắng 19/09/2026 cho kế toán
			   chốt sổ BHXH (`VHCC_Bhxh`), nên phiếu lương phải trừ nó — không thì con số nhân
			   viên nhìn thấy cao hơn số thật vào tài khoản, và họ sẽ đi hỏi. */
			$tong_bh += (float) ( isset( $d['bhxh'] ) ? $d['bhxh'] : 0 );
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
			'bhxh'       => round( $tong_bh, 2 ),
			'tong'       => $du ? round( $luong_chinh + $tong_cong - $tong_tru - $tong_bh, 2 ) : null,
			'daDu'       => $du,
			'thieuGia'   => $thieu_gia,
			'thieuGio'   => $thieu_gio,
			/* ⚠️ Chốt 3 nay chỉ còn MỘT vế. BHXH đã vào hệ (kế toán chốt sổ, xem `VHCC_Bhxh`),
			   nên nó không còn nằm trong danh sách "ngoài hệ" nữa — để lại là nói dối theo
			   chiều ngược: người đọc tưởng còn một khoản trừ chưa tính, trong khi đã trừ rồi.
			   Người KHÔNG có trong sổ BHXH thì `bhxh` = 0 và phiếu không hiện dòng ấy. */
			'ngoaiHe'    => array( 'Lương giờ thêm' ),
			/* Khoản giữ lại (xem `VHCC_GiuLuong`) — gắn ở dòng chính. */
			'giu'        => isset( $dong[0]['giu'] ) ? $dong[0]['giu'] : array(),
			'traGiu'     => isset( $dong[0]['traGiu'] ) ? $dong[0]['traGiu'] : array(),
			'tichLuy'    => isset( $dong[0]['tichLuy'] ) ? $dong[0]['tichLuy'] : array(),
			/* Trang in A4 của CHÍNH phiếu này — "In / Lưu thành PDF". */
			'linkIn'     => self::link_in( $ma, $cs, $tt ),
		);
	}

	/* ====================================================================== cả cơ sở */

	/** Xem phiếu lương CẢ CƠ SỞ — bậc Cửa hàng trưởng, đúng cửa với bảng lương ở trang quản trị. */
	const QUYEN_CS = 'cong_coso';

	/**
	 * PHIẾU LƯƠNG CỦA CẢ MỘT CƠ SỞ, gọn lại cho màn điện thoại.
	 *
	 * =============================================================================================
	 * Anh Thắng 17/09/2026: *"nhân viên thì 1 phiếu của chính mình. Cửa hàng trưởng thì có chính
	 * mình và cả cửa hàng"*.
	 *
	 * 🔴 KHÔNG MỞ THÊM DỮ LIỆU NÀO SO VỚI TRANG QUẢN TRỊ. Cửa hàng trưởng vốn đã xem được bảng
	 *    lương cơ sở mình ở `VHCC_Web::the_bang_luong_cs()` với đúng quyền `cong_coso`. Cửa này
	 *    chỉ là đường khác tới cùng con số, cho người đứng ở quầy. Nếu nó đòi một quyền THẤP hơn
	 *    thì mới là mở thêm — nên nó dùng đúng quyền ấy, không tự đặt quyền riêng.
	 *
	 * 🔴 KHÔNG BỊ CHẶN BỞI "ĐÃ CÔNG BỐ". Cờ công bố sinh ra để NHÂN VIÊN khỏi thấy số nửa vời
	 *    trong lúc kế toán còn đang gõ. Người quản lý thì cần thấy đúng cái nửa vời ấy — đó là
	 *    việc của họ. Nhưng màn PHẢI nói ra tháng này đã công bố hay chưa, để họ biết nhân viên
	 *    bên dưới đang thấy gì.
	 *
	 * ⚠️ GỠ SỐ CĂN CƯỚC. `dung()` trả kèm nó cho tệp xuất kế toán; một màn điện thoại mở giữa
	 *    quầy thì không có lý do gì bày căn cước của hai mươi người.
	 */
	public static function ca_coso( $u, $coso, $thang ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN_CS ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN_CS, 'Xem phiếu lương cả cơ sở' ) );
		}
		$cs = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $u, $cs ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }

		$b = VHCC_BangLuong::dung( $cs, $tt );
		if ( empty( $b['ok'] ) ) { return $b; }

		/* Gom về MỖI NGƯỜI MỘT DÒNG. `dung()` trả mỗi người nhiều dòng (dòng chính + mấy dòng
		   giờ ăn giá khác); bày cả ra trên điện thoại là một người hiện ba lần, và người đọc
		   phải tự cộng. Khoản cộng/trừ chỉ gắn ở DÒNG CHÍNH nên cộng thẳng không nhân đôi. */
		$gom = array();
		foreach ( $b['dong'] as $d ) {
			$k = strtolower( trim( (string) $d['ma'] ) );
			if ( '' === $k ) { continue; }
			if ( ! isset( $gom[ $k ] ) ) {
				$gom[ $k ] = array( 'maNV' => $d['ma'], 'hoTen' => $d['ten'], 'gio' => 0.0,
					'luongChinh' => 0.0, 'thieuGia' => 0, 'thieuGio' => 0,
					'tongCong' => 0.0, 'tongTru' => 0.0, 'bhxh' => 0.0 );
			}
			if ( null === $d['luongChinh'] ) { $gom[ $k ]['thieuGia']++; }
			else { $gom[ $k ]['luongChinh'] += (float) $d['luongChinh']; }
			if ( null !== $d['gio'] ) { $gom[ $k ]['gio'] += (float) $d['gio']; }
			$gom[ $k ]['tongCong'] += (float) $d['tongCong'];
			$gom[ $k ]['tongTru']  += (float) $d['tongTru'];
			/* 🔴 BHXH CŨNG PHẢI VÀO ĐÂY. Bảng cả cửa hàng cộng ra một con số cho từng người rồi
			   cộng tiếp thành tổng cơ sở; bỏ sót một khoản TRỪ là mọi con số ấy cao hơn thật,
			   và nó lệch với chính phiếu riêng của người đó ở màn bên cạnh. */
			$gom[ $k ]['bhxh'] += (float) ( isset( $d['bhxh'] ) ? $d['bhxh'] : 0 );
			$gom[ $k ]['thieuGio'] += (int) $d['thieuGio'];
		}

		$dong = array(); $tong = 0.0; $du_ca = true;
		foreach ( $gom as $g ) {
			$du = ( 0 === $g['thieuGia'] );
			if ( ! $du ) { $du_ca = false; }
			$t = $du ? round( $g['luongChinh'] + $g['tongCong'] - $g['tongTru'] - $g['bhxh'], 2 ) : null;
			if ( null !== $t ) { $tong += $t; }
			$dong[] = array(
				'maNV'  => $g['maNV'],
				'hoTen' => $g['hoTen'],
				'gio'   => round( $g['gio'], 2 ),
				'bhxh'  => round( $g['bhxh'], 2 ),
				'tong'  => $t,
				'thieuGia' => $g['thieuGia'],
				'thieuGio' => $g['thieuGio'],
			);
		}
		usort( $dong, function ( $a, $b2 ) { return strcmp( $a['hoTen'], $b2['hoTen'] ); } );

		return array(
			'ok'      => true,
			'coSo'    => $cs,
			'thang'   => $tt,
			'dong'    => $dong,
			'soNguoi' => count( $dong ),
			/* Tổng chỉ có nghĩa khi MỌI dòng đủ giá — thiếu một người là con số ấy thấp hơn
			   thật mà trông hoàn chỉnh. Cùng luật với phiếu của một người. */
			'tong'    => $du_ca ? round( $tong, 2 ) : null,
			'daDu'    => $du_ca,
			/* Người quản lý cần biết NHÂN VIÊN BÊN DƯỚI đang thấy gì — xem chốt 2 ở trên. */
			'daCongBo' => self::da_cong_bo( $cs, $tt ),
		);
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * PHIẾU IN A4 (LƯU THÀNH PDF) + GỬI KHI CÔNG BỐ — 26/09/2026
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng: *"đến ngày công bố lương, mỗi người sẽ được 1 bản gửi tự động … dạng pdf"*, chốt
	 * gửi CẢ HAI KÊNH: thông báo trong app (chuông + đẩy) và email.
	 *
	 * 🔴 LINK CÓ CHỮ KÝ, MỖI LINK ĐÚNG MỘT PHIẾU. Người mở link từ email không có phiên đăng nhập
	 *    trên trình duyệt ấy. Nên link mang chữ ký HMAC của (mã, cơ sở, tháng) — sửa một chữ trong
	 *    link là chữ ký sai, không đọc được phiếu người khác. Và nó chỉ mở được KHI THÁNG ẤY CÒN
	 *    ĐANG CÔNG BỐ: kế toán bấm "Thu lại" là mọi link của tháng ấy thôi mở.
	 * 🔴 TRANG IN, KHÔNG TẠO TỆP PDF TRÊN MÁY CHỦ — cùng lý do với `VHCC_Pdf`: hosting chia sẻ
	 *    không có bộ chuyển HTML→PDF; bấm "In / Lưu thành PDF" là ra đúng tệp PDF, khổ A4.
	 * ⚠️ Lượt GỬI (đọc email, ghi nhật ký gửi) nằm ở `VHCC_GuiPhieu` — lớp này chỉ ĐỌC.
	 * ⚠️ Email CHỈ mang link, không mang con số lương — thư đi qua nhiều máy chủ, nằm lại trong
	 *    hộp thư lâu dài; con số ở sau link, link thì thu lại được.
	 */

	private static function ky_in( $ma, $cs, $tt ) {
		return substr( hash_hmac( 'sha256', strtolower( trim( (string) $ma ) ) . '|' . self::khoa_cs( $cs ) . '|' . $tt,
			wp_salt( 'auth' ) . '|vhcc-phieu' ), 0, 32 );
	}

	/** Link trang in phiếu của MỘT người, một cơ sở, một tháng. */
	public static function link_in( $ma, $cs, $tt ) {
		return add_query_arg( array( 'vhcc_phieu' => '1', 'm' => strtolower( trim( (string) $ma ) ),
			'cs' => (string) $cs, 'th' => (string) $tt, 'k' => self::ky_in( $ma, $cs, $tt ) ), home_url( '/' ) );
	}

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_in' ), 1 );
	}

	/** Cửa vào. `exit` ở đây, không ở `trang_in()` — bộ thử gọi được `trang_in()`. */
	public static function maybe_render_in() {
		if ( empty( $_GET['vhcc_phieu'] ) ) { return; }
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::trang_in(
			isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : '',
			isset( $_GET['cs'] ) ? sanitize_text_field( wp_unslash( $_GET['cs'] ) ) : '',
			isset( $_GET['th'] ) ? sanitize_text_field( wp_unslash( $_GET['th'] ) ) : '',
			isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : '' );
		exit;
	}

	/** HTML trang in. Sai chữ ký / chưa công bố thì trả một trang báo lỗi, không lộ gì. */
	public static function trang_in( $ma, $cs, $th, $k ) {
		$tt = VHCC_Luong::tien_to_thang( $th );
		$dau = '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow"><title>Phiếu lương</title><style>'
			. '@page{size:A4 portrait;margin:12mm}'
			. 'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:16px;font-size:13px;background:#fff}'
			. '.to{max-width:760px;margin:0 auto}.cty{font-weight:700;font-size:14px}'
			. 'h1{font-size:20px;text-align:center;margin:10px 0 2px}.phu{text-align:center;color:#555;margin:0 0 14px}'
			. 'table{width:100%;border-collapse:collapse;margin:8px 0}th,td{border:1px solid #bbb;padding:6px 8px;text-align:left}'
			. 'th{background:#f1f1f1}td.p,th.p{text-align:right;white-space:nowrap}'
			. '.tong td{font-weight:700;font-size:15px;background:#fafafa}.mo{color:#666;font-size:12px}'
			. '.thanh{text-align:center;margin:0 0 12px}.thanh button{font-size:15px;padding:8px 16px}'
			. '.ky{display:flex;justify-content:space-between;margin-top:28px;text-align:center}'
			. '@media print{.thanh{display:none}body{padding:0}}'
			. '</style></head><body><div class="to">';
		$cuoi = '</div></body></html>';
		$loi = function ( $c ) use ( $dau, $cuoi ) {
			return $dau . '<h1>Phiếu lương</h1><p class="phu">' . esc_html( $c ) . '</p>' . $cuoi;
		};
		if ( '' === $tt || '' === trim( (string) $ma ) || '' === trim( (string) $cs )
			|| ! hash_equals( self::ky_in( $ma, $cs, $tt ), (string) $k ) ) {
			return $loi( 'Link không hợp lệ.' );
		}
		if ( ! self::da_cong_bo( $cs, $tt ) ) {
			return $loi( 'Phiếu lương tháng ' . $tt . ' chưa công bố hoặc đã được thu lại.' );
		}
		$p = self::dung_phieu( $ma, $cs, $tt );
		if ( empty( $p['ok'] ) || ! empty( $p['trong'] ) ) {
			return $loi( ! empty( $p['loi'] ) ? $p['loi'] : 'Không có phiếu lương.' );
		}
		$tien = function ( $v ) { return null === $v ? '—' : esc_html( number_format( (float) $v, 0, ',', '.' ) ) . 'đ'; };
		$d0 = $p['dong'][0];
		$h  = $dau;
		$h .= '<div class="thanh"><button onclick="window.print()">In / Lưu thành PDF</button></div>';
		$h .= '<div class="cty">' . esc_html( VHCC_Pdf::ten_cong_ty() ) . '</div>';
		$h .= '<h1>PHIẾU LƯƠNG THÁNG ' . esc_html( (int) substr( $tt, 5, 2 ) . '/' . substr( $tt, 0, 4 ) ) . '</h1>';
		$h .= '<p class="phu">' . esc_html( VHCC_NhanSu::ten_coso( $p['coSo'] ) ) . '</p>';
		$h .= '<table><tr><th>Họ tên</th><td>' . esc_html( $p['hoTen'] ) . '</td><th>Mã NV</th><td>'
			. esc_html( strtoupper( (string) $d0['ma'] ) ) . '</td></tr>'
			. '<tr><th>Chức vụ</th><td>' . esc_html( (string) $d0['cv'] ) . '</td><th>Ngày có chấm</th><td>'
			. (int) $p['soNgay'] . '</td></tr></table>';

		$h .= '<table><thead><tr><th>Khoản</th><th>Cách tính</th><th class="p">Thành tiền</th></tr></thead><tbody>';
		foreach ( $p['dong'] as $d ) {
			if ( 'thang' === $d['cheDo'] ) {
				$ct = ( null === $d['luongCb'] ? 'chưa khai lương cơ bản' : $tien( $d['luongCb'] ) . '/tháng' )
					. ' × ' . esc_html( (string) $d['congThuc'] ) . ' công / ' . esc_html( null === $d['congYc'] ? '—' : (string) $d['congYc'] ) . ' công chuẩn';
			} elseif ( 'cong' === $d['cheDo'] ) {
				$ct = esc_html( (string) $d['congThuc'] ) . ' công đêm × ' . ( null === $d['gia'] ? 'chưa khai giá' : $tien( $d['gia'] ) );
			} else {
				$ct = esc_html( (string) $d['gio'] ) . ' giờ × ' . ( null === $d['gia'] ? 'chưa khai đơn giá' : $tien( $d['gia'] ) . '/giờ' );
			}
			$h .= '<tr><td>' . esc_html( ! empty( $d['laChinh'] ) ? 'Lương chính — ' . $d['cv'] : $d['cv'] ) . '</td><td>' . $ct
				. '</td><td class="p">' . $tien( $d['luongChinh'] ) . '</td></tr>';
		}
		foreach ( array( 'cong' => VHCC_ChotLuong::CONG, 'tru' => VHCC_ChotLuong::TRU ) as $nh => $ds ) {
			foreach ( $ds as $kh => $ten ) {
				if ( empty( $d0[ $nh ][ $kh ] ) ) { continue; }
				$h .= '<tr><td>' . ( 'tru' === $nh ? 'Trừ — ' : 'Cộng — ' ) . esc_html( $ten ) . '</td><td>'
					. ( 'cong' === $nh && ! empty( $d0['traGiu'][ $kh ] ) ? 'gồm ' . $tien( $d0['traGiu'][ $kh ] ) . ' trả khoản đã giữ' : '' )
					. '</td><td class="p">' . ( 'tru' === $nh ? '−' : '' ) . $tien( $d0[ $nh ][ $kh ] ) . '</td></tr>';
			}
		}
		if ( $p['bhxh'] > 0 ) {
			$h .= '<tr><td>Trừ — BHXH</td><td></td><td class="p">−' . $tien( $p['bhxh'] ) . '</td></tr>';
		}
		$h .= '<tr class="tong"><td colspan="2">TỔNG NHẬN (TOTAL SALARY)</td><td class="p">'
			. ( $p['daDu'] ? $tien( $p['tong'] ) : 'chưa đủ đơn giá' ) . '</td></tr></tbody></table>';

		/* Khoản giữ lại: số tháng này + tích luỹ còn giữ — nhân viên biết mình có bao nhiêu đang chờ. */
		$con = array();
		foreach ( (array) $p['tichLuy'] as $kh => $x ) { if ( (float) $x['con'] > 0 ) { $con[ $kh ] = $x['con']; } }
		if ( $p['giu'] || $con ) {
			$h .= '<table><thead><tr><th>Khoản giữ lại (chưa trả)</th><th class="p">Tháng này</th>'
				. '<th class="p">Tích luỹ còn giữ</th></tr></thead><tbody>';
			foreach ( array_unique( array_merge( array_keys( (array) $p['giu'] ), array_keys( $con ) ) ) as $kh ) {
				$h .= '<tr><td>' . esc_html( isset( VHCC_ChotLuong::CONG[ $kh ] ) ? VHCC_ChotLuong::CONG[ $kh ] : $kh ) . '</td>'
					. '<td class="p">' . ( isset( $p['giu'][ $kh ] ) ? $tien( $p['giu'][ $kh ] ) : '—' ) . '</td>'
					. '<td class="p">' . ( isset( $con[ $kh ] ) ? $tien( $con[ $kh ] ) : '—' ) . '</td></tr>';
			}
			$h .= '</tbody></table><p class="mo">Khoản giữ lại không nằm trong Tổng nhận tháng này — '
				. 'công ty trả vào kỳ quản lý chọn.</p>';
		}
		if ( $p['thieuGio'] ) {
			$h .= '<p class="mo">Tháng này có ' . (int) $p['thieuGio'] . ' lượt thiếu một đầu giờ nên không tính.</p>';
		}
		$h .= '<p class="mo">Số hệ thống tính từ giờ đã chấm và các khoản kế toán đã nhập. Có sai lệch '
			. 'xin báo cửa hàng trưởng hoặc kế toán.</p>';
		$h .= '<div class="ky"><div>Người nhận<br><br><br>' . esc_html( $p['hoTen'] ) . '</div>'
			. '<div>Kế toán<br><br><br>&nbsp;</div></div>';
		return $h . $cuoi;
	}

	/** Tên các khoản cộng / trừ, để màn gọi đúng chữ kế toán đang dùng. */
	public static function ten_khoan() {
		return array( 'cong' => VHCC_ChotLuong::CONG, 'tru' => VHCC_ChotLuong::TRU );
	}
}
