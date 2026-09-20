<?php
/**
 * QUY ĐỔI GIỜ LÀM RA SỐ CÔNG — cho người ăn LƯƠNG THÁNG.
 *
 * =================================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =================================================================================================
 * Anh Thắng 19/09/2026, hai câu liền nhau:
 *   · *"thiết lập lương cố định, anh đúng bằng công nó đủ lương thì hệ thống không hiểu"*
 *   · *"Đối với nhân viên tính theo công tháng thì khi tích vào đó, nv sẽ quy đổi theo 4 tiếng
 *     1/2 công và 8h là 1 công (bổ sung bảng set)"*
 *
 * Câu đầu là lúc anh nhìn ô "chấm công 260,00 giờ" rồi gõ 260 vào **Số công chuẩn** — và bị
 * chối, vì ô ấy đếm bằng NGÀY, tối đa 31. Anh không gõ nhầm; cái ô ấy hỏi sai câu.
 *
 * Lối cũ đếm **số công thực = SỐ NGÀY CÓ CHẤM**, không nhìn ngày ấy làm mấy giờ. Nghĩa là:
 *   · tạt vào 2 tiếng rồi về  → vẫn tính TRỌN một công;
 *   · làm 12 tiếng            → cũng đúng một công.
 * Với người ăn lương tháng thì đó là trả sai tiền theo cả hai chiều, và bảng vẫn đầy số nên
 * không ai thấy. Nay quy đổi theo giờ thật, bằng một bảng bậc mà công ty tự đặt.
 *
 * =================================================================================================
 * ⚠️ BẢNG BẬC ĐỌC TỪ TRÊN XUỐNG, LẤY BẬC ĐẦU TIÊN ĐỦ GIỜ
 * =================================================================================================
 * Mặc định đúng câu anh Thắng nói:  ≥ 8 giờ → 1 công  ·  ≥ 4 giờ → 0,5 công  ·  dưới 4 giờ → 0.
 *
 * ⚠️ LÀM DƯ KHÔNG THÀNH CÔNG LẺ. 12 giờ vẫn là 1 công, không phải 1,5 — "8h là 1 công" là câu
 *    nói về một ngày công đủ, không phải một tỉ lệ chạy tuyến tính. Giờ làm thêm là chuyện của
 *    khoản cộng, không phải của mẫu số lương tháng. Màn khai nói thẳng điều này.
 *
 * ⚠️ BẢNG NÀY CHỈ CHẠM TỚI NGƯỜI ĂN LƯƠNG THÁNG. Người tính theo giờ vẫn lấy giờ nhân đơn giá,
 *    không đi qua đây một bước nào — quy đổi giờ của họ ra công rồi nhân lại là làm tròn mất
 *    mấy giờ lẻ mà chẳng để làm gì.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_QuyCong {

	const O = 'QUY_CONG_CFG';

	/** Cùng cửa với đơn giá: đây là một cái nút đổi tiền của cả chuỗi. */
	const QUYEN = 'gia_gio';

	/** Bậc mặc định — đúng câu anh Thắng: 8h là 1 công, 4h là nửa công. */
	const MAC_DINH = array(
		array( 'gio' => 8.0, 'cong' => 1.0 ),
		array( 'gio' => 4.0, 'cong' => 0.5 ),
	);

	const SO_BAC_TOI_DA = 6;

	/* ====================================================================== đọc */

	/**
	 * Bảng bậc đang dùng, đã xếp GIỜ GIẢM DẦN.
	 *
	 * 🔴 XẾP Ở ĐÂY, KHÔNG TIN THỨ TỰ NGƯỜI GÕ. Bậc 4h đứng trên bậc 8h thì mọi ngày ≥ 4 giờ đều
	 *    dừng ở nửa công — kể cả ngày làm 10 tiếng. Sai một nửa tiền lương, và nhìn bảng bậc
	 *    vẫn thấy "có đủ hai dòng, đúng số anh đọc cho".
	 */
	public static function bac() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		$ra = array();
		if ( is_array( $d ) && isset( $d['bac'] ) && is_array( $d['bac'] ) ) {
			foreach ( $d['bac'] as $x ) {
				if ( ! is_array( $x ) || ! isset( $x['gio'], $x['cong'] ) ) { continue; }
				$g = (float) $x['gio'];
				$c = (float) $x['cong'];
				if ( $g <= 0 || $c <= 0 ) { continue; }
				$ra[] = array( 'gio' => round( $g, 2 ), 'cong' => round( $c, 2 ) );
			}
		}
		if ( ! $ra ) { $ra = self::MAC_DINH; }
		usort( $ra, function ( $a, $b ) {
			if ( $a['gio'] === $b['gio'] ) { return 0; }
			return ( $a['gio'] > $b['gio'] ) ? -1 : 1;
		} );
		return array_slice( $ra, 0, self::SO_BAC_TOI_DA );
	}

	/** Có phải đang chạy bảng mặc định không — để màn khai nói rõ "chưa ai đổi gì". */
	public static function la_mac_dinh() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		return ! ( is_array( $d ) && ! empty( $d['bac'] ) );
	}

	/**
	 * MỘT NGÀY làm `$gio` giờ thì được mấy công.
	 *
	 * ⚠️ Không đủ bậc thấp nhất thì 0 công — KHÔNG làm tròn lên. Tạt vào một tiếng rồi về mà
	 *    tính nửa công là mở đúng cái cửa mà bảng bậc sinh ra để đóng.
	 */
	public static function cong_cua_ngay( $gio, $bac = null ) {
		$g = (float) $gio;
		if ( $g <= 0 ) { return 0.0; }
		foreach ( ( null === $bac ? self::bac() : $bac ) as $b ) {
			if ( $g + 0.0001 >= (float) $b['gio'] ) { return (float) $b['cong']; }
		}
		return 0.0;
	}

	/**
	 * Cả tháng: mảng `ngày => số giờ` ra tổng công.
	 *
	 * ⚠️ CỘNG THEO TỪNG NGÀY, không cộng giờ cả tháng rồi mới quy đổi. Gộp trước là 26 ngày ×
	 *    4 giờ (đáng ra 13 công) thành 104 giờ, rơi vào bậc 8h, ra đúng 1 công.
	 */
	public static function cong_cua_thang( $gio_theo_ngay, $bac = null ) {
		$b = ( null === $bac ) ? self::bac() : $bac;
		$t = 0.0;
		foreach ( (array) $gio_theo_ngay as $g ) { $t += self::cong_cua_ngay( $g, $b ); }
		return round( $t, 2 );
	}

	/** Một dòng chữ mô tả bảng bậc, để in trên màn và trong câu báo. */
	public static function mo_ta( $bac = null ) {
		$ra = array();
		foreach ( ( null === $bac ? self::bac() : $bac ) as $b ) {
			$ra[] = 'từ ' . self::so( $b['gio'] ) . 'h → ' . self::so( $b['cong'] ) . ' công';
		}
		return implode( ' · ', $ra );
	}

	public static function so( $n ) {
		$n = (float) $n;
		return rtrim( rtrim( number_format( $n, 2, ',', '' ), '0' ), ',' );
	}

	/* ====================================================================== ghi */

	/**
	 * Ghi lại cả bảng. `$gio` và `$cong` là hai mảng song song, dòng nào trống cả hai thì bỏ.
	 */
	public static function dat( $u, $gio, $cong ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Sửa bảng quy đổi giờ ra công' ) );
		}
		$gio  = (array) $gio;
		$cong = (array) $cong;
		$bac  = array();
		$thay = array();
		foreach ( $gio as $i => $g ) {
			$g = trim( str_replace( ',', '.', (string) $g ) );
			$c = trim( str_replace( ',', '.', (string) ( isset( $cong[ $i ] ) ? $cong[ $i ] : '' ) ) );
			if ( '' === $g && '' === $c ) { continue; }
			if ( '' === $g || '' === $c || ! is_numeric( $g ) || ! is_numeric( $c ) ) {
				return array( 'ok' => false, 'error' => 'Mỗi bậc phải có ĐỦ hai ô: số giờ và số '
					. 'công. Bỏ trống một ô thì bậc ấy không có nghĩa — muốn bỏ bậc thì xoá '
					. 'trống CẢ HAI ô.' );
			}
			$g = round( (float) $g, 2 );
			$c = round( (float) $c, 2 );
			if ( $g <= 0 || $g > 24 ) {
				return array( 'ok' => false, 'error' => 'Số giờ của một bậc phải từ 0 tới 24 — '
					. 'gõ "' . $g . '" thì không có ngày nào chạm tới.' );
			}
			if ( $c <= 0 || $c > 3 ) {
				return array( 'ok' => false, 'error' => 'Số công của một bậc phải từ 0 tới 3 — '
					. 'gõ "' . $c . '" thì một ngày ăn bằng mấy ngày.' );
			}
			/* 🔴 CHẶN HAI BẬC CÙNG SỐ GIỜ. Hai dòng "8h" với hai số công khác nhau thì kết quả
			   tuỳ vào thứ tự xếp — tức là tuỳ may rủi, và nó sẽ đổi sau một lần lưu lại. */
			$kh = (string) $g;
			if ( isset( $thay[ $kh ] ) ) {
				return array( 'ok' => false, 'error' => 'Có hai bậc cùng mốc ' . self::so( $g )
					. ' giờ. Mỗi mốc giờ chỉ được một dòng.' );
			}
			$thay[ $kh ] = true;
			$bac[] = array( 'gio' => $g, 'cong' => $c );
		}
		if ( ! $bac ) {
			return array( 'ok' => false, 'error' => 'Phải còn ít nhất một bậc. Xoá hết thì không '
				. 'ngày nào ra công nào, và mọi người ăn lương tháng nhận 0đ.' );
		}
		if ( count( $bac ) > self::SO_BAC_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Nhiều nhất ' . self::SO_BAC_TOI_DA . ' bậc.' );
		}
		usort( $bac, function ( $a, $b ) {
			if ( $a['gio'] === $b['gio'] ) { return 0; }
			return ( $a['gio'] > $b['gio'] ) ? -1 : 1;
		} );
		/* 🔴 BẬC GIỜ CAO HƠN PHẢI ĐƯỢC CÔNG CAO HƠN. Khai 8h→0,5 mà 4h→1 thì người làm ÍT hơn
		   ăn NHIỀU hơn — không ai cố ý khai thế, nhưng gõ nhầm hai ô cạnh nhau thì ra đúng vậy,
		   và bảng lương vẫn đầy số. */
		for ( $i = 1; $i < count( $bac ); $i++ ) {
			if ( $bac[ $i ]['cong'] >= $bac[ $i - 1 ]['cong'] ) {
				return array( 'ok' => false, 'error' => 'Bậc ' . self::so( $bac[ $i ]['gio'] )
					. 'h đang được ' . self::so( $bac[ $i ]['cong'] ) . ' công, bằng hoặc hơn bậc '
					. self::so( $bac[ $i - 1 ]['gio'] ) . 'h (' . self::so( $bac[ $i - 1 ]['cong'] )
					. ' công) — làm ít hơn mà ăn nhiều hơn. Đảo lại hai ô số công?' );
			}
		}
		VHCC_Luong::dat_cai_dat( self::O, array( 'bac' => $bac ), $u );
		return array( 'ok' => true, 'bac' => $bac, 'moTa' => self::mo_ta( $bac ) );
	}

	/** Về lại bảng mặc định 8h = 1 công, 4h = 0,5 công. */
	public static function ve_mac_dinh( $u ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Sửa bảng quy đổi giờ ra công' ) );
		}
		VHCC_Luong::dat_cai_dat( self::O, array(), $u );
		return array( 'ok' => true, 'moTa' => self::mo_ta( self::MAC_DINH ) );
	}
}
