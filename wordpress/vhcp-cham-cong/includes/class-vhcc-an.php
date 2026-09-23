<?php
/**
 * ẨN MỘT MÃ KHỎI BẢNG CÔNG CỦA MỘT CƠ SỞ — rác thử máy, không phải người.
 *
 * =============================================================================================
 * 🔴 VÌ SAO "CHỜ TRẢ VỀ" KHÔNG LÀM ĐƯỢC VIỆC NÀY
 * =============================================================================================
 * Anh Thắng 16/09/2026: *"1 số nhân viên chạy test trên máy chấm công cũ, chưa giải quyết triệt
 * để, vậy để tránh rối, mình sẽ ẩn nó đi, khi ẩn thì nó không ảnh hưởng đến bảng công"*, rồi khi
 * thử nút cũ: *"bấm chờ trả về thì nó không được, nên cần ẩn đi"* — kèm ảnh câu chối
 * **"Không xong. Không tìm thấy hồ sơ 3925996292."**
 *
 * Đúng vậy, và đó là cả vấn đề: `3925996292` KHÔNG PHẢI một người. Đó là mã máy thô của một lượt
 * bấm thử trên máy chấm công cũ. Nó chưa bao giờ có hồ sơ, nên mọi cửa đi qua hồ sơ — "chờ trả
 * về", "cho nghỉ việc", sửa cơ sở — đều chối nó, và chối ĐÚNG.
 *
 * Nên sổ này cố ý KHÔNG hỏi hồ sơ một câu nào. Khoá là (cơ sở, mã) đọc thẳng từ bảng chấm công.
 *
 * =============================================================================================
 * 🔴 ẨN, KHÔNG PHẢI XOÁ
 * =============================================================================================
 * Lượt chấm vẫn nằm nguyên trong `cham_cong`. Ẩn chỉ là một dòng trong sổ cấu hình, bỏ ra là
 * hàng ấy hiện lại đủ số. Xoá công thì không lấy lại được — mà thứ đang ẩn là dữ liệu người ta
 * CHƯA chắc là rác (*"chưa giải quyết triệt để"*), nên đường lùi phải còn.
 *
 * ⚠️ VÀ ẨN THÌ PHẢI RA KHỎI CẢ TỔNG, không chỉ khuất mắt. *"khi ẩn thì nó không ảnh hưởng đến
 *    bảng công"*. Giấu một hàng mà vẫn cộng nó vào tổng cơ sở là tệ hơn không giấu: con số
 *    không khớp với những gì bày ra, và không còn hàng nào để người ta lần ra vì sao.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_An {

	/** Khoá trong bảng `cai_dat`. */
	const O = 'AN_MA_BANG_CONG';

	/**
	 * Ẩn / hiện là việc của người trực cửa hàng — cùng cửa với xem bảng công cơ sở mình.
	 *
	 * ⚠️ Cửa này MỘT MÌNH chưa đủ: phải kèm `co_quyen_coso()` cho đúng cơ sở. `cong_coso` chỉ
	 *    nói "được xem bảng công cơ sở MÌNH", không nói cơ sở nào. Xem `gac()`.
	 */
	const QUYEN = 'cong_coso';

	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return is_array( $d ) ? $d : array();
	}

	public static function khoa_cs( $coso ) {
		return VHCC_Luong::bo_chu( preg_replace( '/^CS_/i', '', trim( (string) $coso ) ) );
	}

	public static function khoa_ma( $ma ) {
		return strtoupper( trim( (string) $ma ) );
	}

	/** Mã này có đang bị ẩn khỏi bảng công của cơ sở ấy không. */
	public static function la_an( $coso, $ma, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		$m  = self::khoa_ma( $ma );
		if ( '' === $k || '' === $m ) { return false; }
		return ! empty( $so[ $k ][ $m ] );
	}

	/**
	 * Có bị ẩn ở BẤT KỲ cơ sở nào trong CHÙM của cơ sở này không.
	 *
	 * 🔴 PHẢI HỎI CẢ CHÙM, KHÔNG CHỈ CƠ SỞ ĐANG XEM. `VHCC_Luong::doc_thang()` đọc lượt chấm của
	 *    cả chùm (cơ sở chính + mấy cơ sở ghép vào nó), nhưng `dat()` ghi khoá ẩn theo đúng cơ
	 *    sở người ta ĐANG MỞ. Hỏi mỗi cơ sở đang xem thì: ẩn ở bảng cha xong mở bảng con, hàng
	 *    vẫn còn — mà trên bảng cha còn có sẵn đường dẫn "Mở bảng ..." sang bảng con.
	 *
	 * ⚠️ Gác `method_exists` cùng chỗ với lời gọi — luật của `kiem-goi-cheo.php`. Thiếu hàm chùm
	 *    thì lui về hỏi đúng một cơ sở, chứ không ngã.
	 */
	public static function la_an_chum( $coso, $ma, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		if ( self::la_an( $coso, $ma, $so ) ) { return true; }
		if ( ! class_exists( 'VHCC_Luong' ) || ! method_exists( 'VHCC_Luong', 'chum_cua' ) ) {
			return false;
		}
		foreach ( (array) VHCC_Luong::chum_cua( $coso ) as $cs_x ) {
			if ( self::la_an( $cs_x, $ma, $so ) ) { return true; }
		}
		/* 🔴 VÀ HỎI NGƯỢC LÊN CƠ SỞ CHA. `chum_cua()` chỉ đi XUỐNG (chính nó + mấy cơ sở phụ
		   ghép vào nó). Nhưng cảnh thật là ngược lại: người ta mở BẢNG GỘP của cơ sở cha, thấy
		   rác, bấm ẩn ở đó — khoá ghi dưới tên cha. Rồi ai mở bảng của cơ sở CON thì `chum_cua`
		   của con không chứa cha, và hàng ấy hiện lại. Hỏi cả hai chiều thì mới đúng chữ
		   *"ẩn thì ẩn luôn"*. */
		if ( method_exists( 'VHCC_Luong', 'ban_do_ghep' ) ) {
			$cs_chuan = VHCC_NhanSu::chuan_coso( $coso );
			foreach ( (array) VHCC_Luong::ban_do_ghep() as $phu => $chinh ) {
				if ( 0 === strcasecmp( (string) $phu, (string) $cs_chuan )
					&& self::la_an( $chinh, $ma, $so ) ) { return true; }
			}
		}
		return false;
	}

	/**
	 * Danh sách mã đang ẩn ở một cơ sở — để màn còn bày ra đường bỏ ẩn.
	 *
	 * 🔴 PHẢI CÓ CHỖ NHÌN THẤY. Một thứ ẩn được mà không liệt kê ra đâu cả thì đúng bằng xoá:
	 *    người sau mở bảng thấy thiếu người, không biết hỏi ai, và không có nút nào để thử.
	 */
	public static function ds( $coso, $so = null ) {
		$so = ( null === $so ) ? self::so() : $so;
		$k  = self::khoa_cs( $coso );
		if ( '' === $k || ! isset( $so[ $k ] ) || ! is_array( $so[ $k ] ) ) { return array(); }
		$ra = array();
		foreach ( $so[ $k ] as $m => $v ) {
			if ( ! empty( $v ) ) { $ra[] = (string) $m; }
		}
		sort( $ra );
		return $ra;
	}

	private static function gac( $u, $coso ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN, 'Ẩn một mã khỏi bảng công' );
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return 'Cơ sở ' . $coso . ' không thuộc phạm vi của anh/chị.';
		}
		return '';
	}

	/**
	 * Bật / tắt ẩn cho một mã ở một cơ sở.
	 *
	 * @param bool $an true = ẩn, false = hiện lại.
	 */
	public static function dat( $u, $coso, $ma, $an ) {
		$chan = self::gac( $u, $coso );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$k = self::khoa_cs( $coso );
		$m = self::khoa_ma( $ma );
		if ( '' === $k || '' === $m ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở hoặc mã.' );
		}
		$so = self::so();
		if ( $an ) {
			$so[ $k ][ $m ] = 1;
		} else {
			unset( $so[ $k ][ $m ] );
			if ( empty( $so[ $k ] ) ) { unset( $so[ $k ] ); }
		}
		VHCC_Luong::dat_cai_dat( self::O, $so, $u );
		return array( 'ok' => true, 'ma' => $m, 'an' => (bool) $an );
	}
}
