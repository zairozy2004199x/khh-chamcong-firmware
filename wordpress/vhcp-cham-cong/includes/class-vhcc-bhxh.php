<?php
/**
 * SỔ BẢO HIỂM XÃ HỘI — ai đóng, mỗi tháng trừ bao nhiêu.
 *
 * =================================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =================================================================================================
 * Anh Thắng 19/09/2026, kèm ảnh tờ lương thật: *"Đối với nhân viên cố định sẽ có thêm bảo hiểm xã
 * hội. Bổ sung tab bên Phân Quyền Kế toán để kế toán chốt BHXH bạn nào đóng sẽ được thêm vào
 * bảng"*.
 *
 * Cột BHXH vốn đã có mặt trong bố cục tờ nộp (cột L), và công thức tổng lương đã trừ nó từ lâu
 * (`M = I + K − L`) — nhưng hệ chưa bao giờ có dữ liệu để đổ vào, nên ô ấy luôn trống và kế toán
 * phải gõ tay vào tệp sau khi xuất. Gõ tay ngoài tệp thì tháng sau gõ lại từ đầu, và không ai
 * soi được ai đang đóng.
 *
 * =================================================================================================
 * ⚠️ HAI QUYẾT ĐỊNH CỦA ANH THẮNG, ĐỪNG TỰ ĐỔI
 * =================================================================================================
 *   · **Kế toán gõ thẳng số tiền**, máy KHÔNG tự tính theo tỉ lệ. Đối chiếu tờ thật: Truyền lương
 *     cơ bản 4.000.000 mà BHXH 596.610 — không phải 10,5% của 4.000.000. Mức đóng bảo hiểm là một
 *     con số khác hẳn lương cơ bản, và nó nằm ngoài hệ. Tự nhân một tỉ lệ nào đó là bịa ra một con
 *     số trông như thật rồi trừ thẳng vào lương người ta.
 *   · **Khai một lần, tự lặp hằng tháng.** Sổ này là DANH SÁCH NGƯỜI ĐANG ĐÓNG, không phải một ô
 *     nhập của riêng tháng nào. Vào bảo hiểm thì bật một lần, nghỉ thì tắt.
 *
 * 🔴 VÌ TỰ LẶP NÊN PHẢI CÓ **THÁNG BẮT ĐẦU**. Không có nó thì hôm nay khai một người là bảng lương
 *    của mọi tháng trước cũng mọc thêm một khoản trừ — viết lại quá khứ của những tháng đã trả
 *    tiền xong. Mặc định là tháng đang mở, tức "từ nay trở đi", đúng thứ người khai đang nghĩ.
 *
 * ⚠️ BHXH LÀ KHOẢN **TRỪ**. Đối chiếu tờ thật: 4.000.000 − 596.610 = 3.403.390.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Bhxh {

	const O = 'BHXH_SO';

	/** Cửa KẾ TOÁN. Đây là khoản trừ thẳng vào lương và nó tự lặp mọi tháng. */
	const QUYEN = 'bhxh';

	/** Trần vô lý cho một ô tiền BHXH một tháng — trên mức này gần như chắc là gõ dư số 0. */
	const TIEN_TOI_DA = 50000000;

	/* ====================================================================== đọc */

	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return is_array( $d ) ? $d : array();
	}

	public static function khoa_ma( $ma ) {
		return strtolower( trim( (string) $ma ) );
	}

	/**
	 * Một người tháng ấy đóng bao nhiêu. `0.0` nghĩa là KHÔNG đóng.
	 *
	 * ⚠️ TRẢ 0 CHỨ KHÔNG TRẢ `null`. Nơi gọi đem nó đi TRỪ; `null` lỡ lọt vào một phép cộng
	 *    chuỗi là thành số 0 ở chỗ này và chuỗi rỗng ở chỗ kia.
	 */
	public static function cua( $ma_nv, $thang, $so = null ) {
		$s = ( null === $so ) ? self::so() : $so;
		$m = self::khoa_ma( $ma_nv );
		if ( '' === $m || ! isset( $s[ $m ] ) ) { return 0.0; }
		$d = $s[ $m ];
		if ( ! is_array( $d ) || empty( $d['tien'] ) ) { return 0.0; }

		/* 🔴 CHƯA TỚI THÁNG BẮT ĐẦU THÌ CHƯA TRỪ. Xem khối chú thích đầu lớp: không có chốt này
		   thì khai hôm nay là mọi tháng đã chốt xong cũng mọc thêm một khoản trừ. */
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$tu = isset( $d['tu'] ) ? (string) $d['tu'] : '';
		if ( '' !== $tt && '' !== $tu && $tt < $tu ) { return 0.0; }

		return (float) $d['tien'];
	}

	/** Người này có trong sổ không (kể cả khi chưa tới tháng bắt đầu). */
	public static function co( $ma_nv, $so = null ) {
		$s = ( null === $so ) ? self::so() : $so;
		$m = self::khoa_ma( $ma_nv );
		return ( '' !== $m && isset( $s[ $m ] ) && ! empty( $s[ $m ]['tien'] ) );
	}

	/** Cả sổ, đã xếp theo tên — để vẽ ra màn. */
	public static function ds( $so = null ) {
		$s  = ( null === $so ) ? self::so() : $so;
		$ra = array();
		foreach ( $s as $m => $d ) {
			if ( ! is_array( $d ) || empty( $d['tien'] ) ) { continue; }
			$ra[] = array(
				'ma'   => isset( $d['ma'] ) ? (string) $d['ma'] : strtoupper( (string) $m ),
				'ten'  => isset( $d['ten'] ) ? (string) $d['ten'] : '',
				'tien' => (float) $d['tien'],
				'tu'   => isset( $d['tu'] ) ? (string) $d['tu'] : '',
			);
		}
		usort( $ra, function ( $a, $b ) {
			$c = strcasecmp( $a['ten'], $b['ten'] );
			return ( 0 !== $c ) ? $c : strcasecmp( $a['ma'], $b['ma'] );
		} );
		return $ra;
	}

	/** Tổng một tháng — cho dòng tóm tắt trên màn khai. */
	public static function tong_thang( $thang, $so = null ) {
		$s = ( null === $so ) ? self::so() : $so;
		$t = 0.0;
		foreach ( array_keys( $s ) as $m ) { $t += self::cua( $m, $thang, $s ); }
		return round( $t, 2 );
	}

	/**
	 * NHỮNG NGƯỜI ĂN LƯƠNG THÁNG — danh sách gợi ý để kế toán khai BHXH.
	 *
	 * =============================================================================================
	 * 🔴 VÌ SAO CÓ HÀM NÀY
	 * =============================================================================================
	 * Anh Thắng 19/09/2026: *"nếu là nhân viên Lương theo công thì sẽ hiện hết vào này để tính
	 * bhxh, còn nhân viên Parttime thì không cần"*.
	 *
	 * Bản trước bắt gõ tay Mã NV. Gõ tay một mã dạng `MNNV2KVC0166` là mời gõ nhầm — mà gõ nhầm
	 * ở đây là trừ tiền của người khác, và tháng sau mới lộ. Tệ hơn: không ai nhìn ra được
	 * **ai còn thiếu**, nên một người mới vào bảo hiểm có thể nằm ngoài sổ hết tháng này sang
	 * tháng khác mà không có gì báo.
	 *
	 * ⚠️ AI LÀ "LƯƠNG THEO CÔNG" — HAI ĐƯỜNG, CHỈ CẦN MỘT ĐÚNG. Đúng hai đường mà
	 *    `VHCC_BangLuong::dung()` dùng để rẽ sang lối tính theo tháng:
	 *      · **lương cơ bản trong hồ sơ** (`nhan_vien.luong_co_ban`), và
	 *      · **khai ăn lương tháng ngay trên bảng lương** (`VHCC_ChotLuong`, theo từng tháng).
	 *    Chỉ hỏi hồ sơ thì sót hẳn những người kế toán khai thẳng trên bảng lương — họ đang ăn
	 *    lương tháng thật mà không bao giờ hiện ra để khai BHXH. Bộ thử bắt được đúng chỗ này.
	 *    Người tính theo giờ (parttime) không có mặt ở cả hai đường, nên không lọt vào danh
	 *    sách này — đúng ý anh Thắng.
	 *
	 * ⚠️ KHÔNG TỰ THÊM VÀO SỔ, chỉ BÀY RA. Có lương cơ bản không có nghĩa là đang đóng bảo hiểm;
	 *    số tiền đóng thì máy không biết (xem khối chú thích đầu lớp). Tự thêm với số 0 là đẻ ra
	 *    một sổ đầy dòng 0đ, trông như đã xét hết mọi người và ai cũng không đóng.
	 */
	public static function ds_luong_thang( $so = null ) {
		global $wpdb;
		$s = ( null === $so ) ? self::so() : $so;

		/* ĐƯỜNG HAI: ai được khai ăn lương tháng trên bảng lương, ở bất kỳ cơ sở / tháng nào.
		   ⚠️ Gác `class_exists` cùng hàm với lời gọi — luật của `kiem-goi-cheo.php`. */
		$khai = array();
		if ( class_exists( 'VHCC_ChotLuong' ) && method_exists( 'VHCC_ChotLuong', 'so' ) ) {
			foreach ( (array) VHCC_ChotLuong::so() as $cs_x ) {
				if ( ! is_array( $cs_x ) ) { continue; }
				foreach ( $cs_x as $th_x ) {
					if ( ! is_array( $th_x ) || empty( $th_x['luongThang'] ) ) { continue; }
					foreach ( (array) $th_x['luongThang'] as $m_x => $d_x ) {
						if ( ! empty( $d_x['lcb'] ) ) { $khai[ self::khoa_ma( $m_x ) ] = (float) $d_x['lcb']; }
					}
				}
			}
		}

		/* 🔴 MỘT LƯỢT ĐỌC CHO CẢ SỔ NHÂN SỰ, rồi tự dò — KHÔNG hỏi `VHCC_NhanSu::ho_so()` từng
		   mã một. Hai lý do, lý do sau mới là lý do thật:
		     · mỗi mã một câu truy vấn thì danh sách càng dài càng chậm;
		     · và `ho_so()` so mã CÓ PHÂN BIỆT HOA THƯỜNG, trong khi khoá của `VHCC_ChotLuong`
		       lưu ở dạng thường. Hỏi nó bằng `bh_truyen` thì không thấy ai, nên cả nhóm "khai
		       ăn lương tháng trên bảng lương" lặng lẽ biến mất khỏi màn — bộ thử bắt đúng chỗ
		       này. Dò trên mảng thì tự mình nắm được luật so khoá. */
		$r = $wpdb->get_results(
			'SELECT ma_nv, ho_ten, cua_hang, chuc_vu, luong_co_ban FROM ' . VHCC_DB::t( 'nhan_vien' )
			. " WHERE trang_thai_lam_viec NOT IN ('Nghỉ việc','Nghỉ hẳn')"
			. ' ORDER BY cua_hang, ho_ten', ARRAY_A );

		$ra = array();
		foreach ( (array) $r as $x ) {
			$ma = trim( (string) $x['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$k   = self::khoa_ma( $ma );
			$lcb = (float) $x['luong_co_ban'];
			if ( $lcb <= 0 && isset( $khai[ $k ] ) ) { $lcb = (float) $khai[ $k ]; }
			/* Không có ở đường nào thì đây là người tính theo giờ — parttime, bỏ qua. */
			if ( $lcb <= 0 ) { continue; }
			$ra[] = array(
				'ma'    => $ma,
				'ten'   => (string) $x['ho_ten'],
				'coso'  => (string) $x['cua_hang'],
				'cv'    => (string) $x['chuc_vu'],
				'lcb'   => $lcb,
				/* Đã có trong sổ chưa, và đang trừ bao nhiêu — để màn bày một bảng duy nhất
				   thay vì bắt người đọc đối chiếu hai danh sách bằng mắt. */
				'trong' => isset( $s[ $k ] ) && ! empty( $s[ $k ]['tien'] ),
				'tien'  => ( isset( $s[ $k ]['tien'] ) ? (float) $s[ $k ]['tien'] : 0.0 ),
				'tu'    => ( isset( $s[ $k ]['tu'] ) ? (string) $s[ $k ]['tu'] : '' ),
			);
		}
		return $ra;
	}

	/**
	 * Người đang có trong sổ mà KHÔNG phải lương tháng — nghĩa là parttime, hoặc hồ sơ vừa bị
	 * gỡ lương cơ bản. Bày riêng để không ai lặng lẽ bị trừ ngoài tầm mắt.
	 */
	public static function ds_ngoai_luong_thang( $so = null ) {
		$s  = ( null === $so ) ? self::so() : $so;
		$lt = array();
		foreach ( self::ds_luong_thang( $s ) as $x ) { $lt[ self::khoa_ma( $x['ma'] ) ] = true; }
		$ra = array();
		foreach ( self::ds( $s ) as $x ) {
			if ( ! isset( $lt[ self::khoa_ma( $x['ma'] ) ] ) ) { $ra[] = $x; }
		}
		return $ra;
	}

	/* ====================================================================== ghi */

	private static function gac( $u ) {
		if ( VHCC_Vai::duoc( $u, self::QUYEN ) ) { return ''; }
		return VHCC_Vai::loi( $u, self::QUYEN, 'Sửa sổ BHXH' );
	}

	/**
	 * Cho một người vào sổ, hoặc sửa số tiền của người đã có.
	 *
	 * @param mixed $tien Số tiền trừ mỗi tháng. Để trống hay 0 = BỎ khỏi sổ.
	 * @param mixed $tu   Tháng bắt đầu `yyyy-mm`. Để trống = tháng đang chạy.
	 */
	public static function dat( $u, $ma_nv, $tien, $tu = '' ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		$ma = trim( (string) $ma_nv );
		$m  = self::khoa_ma( $ma );
		if ( '' === $m ) { return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên.' ); }

		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) {
			return array( 'ok' => false, 'error' => 'Không có ai mang mã "' . $ma
				. '" trong sổ nhân sự. Kiểm lại mã — trừ tiền nhầm người thì tháng sau mới lộ.' );
		}

		$s = self::so();
		$t = VHCC_NhanSu::so_tien( $tien );
		if ( $t <= 0 ) {
			/* Số 0 hay ô trống = BỎ khỏi sổ, không phải "đóng 0đ". Một dòng 0đ nằm trong sổ
			   trông như đã xét xong và bằng không. */
			unset( $s[ $m ] );
			VHCC_Luong::dat_cai_dat( self::O, $s, $u );
			return array( 'ok' => true, 'bo' => true, 'ma' => $ma );
		}
		if ( $t > self::TIEN_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Số tiền BHXH quá lớn ('
				. number_format( $t, 0, ',', '.' ) . 'đ) — gõ dư số 0?' );
		}

		$tt = VHCC_Luong::tien_to_thang( $tu );
		if ( '' === $tt ) { $tt = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		$s[ $m ] = array(
			'ma'   => strtoupper( $ma ),
			/* Nhớ tên lúc khai để màn còn đọc được cả khi người ấy đã nghỉ và bị lọc khỏi
			   danh sách nhân sự — một dòng trừ tiền không tên là một dòng không ai dám xoá. */
			'ten'  => (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ),
			'tien' => round( $t, 2 ),
			'tu'   => $tt,
		);
		VHCC_Luong::dat_cai_dat( self::O, $s, $u );
		return array( 'ok' => true, 'ma' => strtoupper( $ma ), 'tien' => round( $t, 2 ), 'tu' => $tt );
	}

	public static function xoa( $u, $ma_nv ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		$m = self::khoa_ma( $ma_nv );
		$s = self::so();
		if ( '' === $m || ! isset( $s[ $m ] ) ) {
			return array( 'ok' => false, 'error' => 'Người này không có trong sổ BHXH.' );
		}
		$ten = isset( $s[ $m ]['ten'] ) ? (string) $s[ $m ]['ten'] : strtoupper( $m );
		unset( $s[ $m ] );
		VHCC_Luong::dat_cai_dat( self::O, $s, $u );
		return array( 'ok' => true, 'ten' => $ten );
	}
}
