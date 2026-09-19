<?php
/**
 * LỊCH NGHỈ LỄ CỦA CẢ CHUỖI, VÀ HỆ SỐ GIỜ LỄ.
 *
 * =================================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =================================================================================================
 * Anh Thắng 18/09/2026: *"Cho anh hỏi chỗ set lịch lương lễ và ngày lễ, ngày đó x2 hay x3"*.
 * Câu trả lời thật lúc ấy là: KHÔNG CÓ CHỖ NÀO. Cơ sở tính theo giờ trả đúng một đơn giá cho mọi
 * ngày trong tháng; mùng 2/9 hay thứ Ba đều như nhau. Chỉ nhánh Máy Tự Động (`VHCC_Luong::
 * mtd_tinh_luong`) mới biết tới ngày lễ, và nó đọc một danh sách `MTD_NGAY_LE` không có màn nào
 * sửa được — khai xong chỉ nằm đó.
 *
 * Hỏi lại thì anh chọn hai điều, và cả hai định hình tệp này:
 *   · hệ số **tự set theo cơ chế công ty** — không đóng cứng x2/x3 trong mã. Luật lao động nói
 *     tối thiểu 300% cho ngày lễ, nhưng công ty có thể trả hơn, và cách đếm "300%" của mỗi nơi
 *     một khác (có nơi đã gồm lương ngày, có nơi chưa). Đoán hộ là sai tiền hàng loạt.
 *   · danh sách ngày lễ **chung cả chuỗi** — không phải mỗi cửa hàng một bản. Tết là Tết ở mọi
 *     cơ sở; để từng nơi tự khai là chắc chắn có nơi quên, và sai ấy chỉ lộ ra ở bảng lương
 *     tháng sau.
 *
 * =================================================================================================
 * ⚠️ HAI KIỂU NGÀY, CỐ Ý
 * =================================================================================================
 *   · `2026-09-02` — MỘT LẦN. Dùng cho Tết âm (mỗi năm rơi một ngày dương khác nhau) và cho ngày
 *     nghỉ bù do công ty tự cho.
 *   · `09-02`      — LẶP HẰNG NĂM. Quốc khánh, 30/4, 1/5, 1/1 dương.
 *
 * Giữ đúng quy ước mà `VHCC_Luong::mtd_la_le()` đã dùng từ trước — hai bộ luật ngày lễ trong cùng
 * một plugin là thứ sẽ lệch nhau vào một ngày nào đó, và lệch ở chỗ không ai soi.
 *
 * ⚠️ HỆ SỐ LÀ NHÂN TỬ TRÊN GIỜ, KHÔNG PHẢI ĐƠN GIÁ THỨ HAI. Khai 2 nghĩa là giờ ngày ấy ăn gấp
 *    đôi giờ thường; đơn giá của từng người vẫn là đơn giá trong `VHCC_GiaGio`. Nếu để thành một
 *    bảng giá riêng thì mỗi lần nâng lương phải nhớ nâng hai chỗ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NgayLe {

	const O = 'NGAY_LE_CFG';

	/** Cùng cửa với lịch nghỉ lễ đã khai trong bảng vai: Kế toán trở lên. */
	const QUYEN = 'ngay_le';

	/**
	 * 🔴 MẶC ĐỊNH LÀ 1, KHÔNG PHẢI 2 hay 3.
	 *
	 * Chưa ai khai hệ số thì hệ thống KHÔNG tự nhân gì cả. Đoán hộ 2 là bỗng dưng cộng tiền cho
	 * cả chuỗi mà không ai bấm nút nào — và con số ấy sẽ được trả thật trước khi có người kịp
	 * nhận ra. Màn khai nói thẳng "đang là 1, tức chưa nhân" để không ai tưởng đã bật.
	 */
	const HE_SO_MAC_DINH = 1.0;

	/** Trần chặn gõ nhầm: 10 lần giờ thường là chắc chắn thừa một chữ số. */
	const HE_SO_TOI_DA = 10.0;

	/* ====================================================================== đọc */

	public static function cfg() {
		$d = VHCC_Luong::cai_dat( self::O, null );
		if ( ! is_array( $d ) ) { return array( 'heSo' => self::HE_SO_MAC_DINH, 'ngay' => array() ); }
		$hs = ( isset( $d['heSo'] ) && is_numeric( $d['heSo'] ) ) ? (float) $d['heSo'] : self::HE_SO_MAC_DINH;
		$ng = ( isset( $d['ngay'] ) && is_array( $d['ngay'] ) ) ? $d['ngay'] : array();
		return array( 'heSo' => $hs, 'ngay' => $ng );
	}

	/** Hệ số dùng chung cho mọi ngày lễ chưa khai hệ số riêng. */
	public static function he_so_chung( $cfg = null ) {
		$c = ( null === $cfg ) ? self::cfg() : $cfg;
		return (float) $c['heSo'];
	}

	/**
	 * Danh sách ngày lễ, đã xếp: lặp hằng năm trước (theo tháng/ngày), rồi ngày một lần.
	 * Mỗi mục: array( 'ngay', 'ten', 'heSo' (đã quy về số thật), 'rieng' (có khai riêng không),
	 * 'lap' (có lặp hằng năm không) ).
	 */
	public static function ds( $cfg = null ) {
		$c  = ( null === $cfg ) ? self::cfg() : $cfg;
		$ra = array();
		foreach ( $c['ngay'] as $k => $v ) {
			/* 🔴 KHOÁ TRONG KHO ĐÃ CHUẨN RỒI — ĐỪNG CHẠY LẠI `chuan_ngay()` LÊN NÓ.
			   Kho lưu `MM-DD`, còn `chuan_ngay()` đọc hai số theo kiểu người gõ là `DD-MM`.
			   Chạy lại là nó LẬT NGƯỢC khoá: `09-02` (2 tháng 9) thành `02-09` (9 tháng 2), và
			   mọi ngày lễ lặp hằng năm lặng lẽ nhảy sang một ngày khác. Bài
			   `kiem-ngay-le-quy-cong.php` bắt được đúng lỗi này.
			   Ở đây chỉ SOI HÌNH DẠNG, không đọc lại nghĩa. */
			$k = trim( (string) $k );
			if ( 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2}|\d{2}-\d{2})$/', $k ) ) { continue; }
			$rieng = ( is_array( $v ) && isset( $v['heSo'] ) && is_numeric( $v['heSo'] )
				&& (float) $v['heSo'] > 0 );
			$ra[] = array(
				'ngay'  => $k,
				'ten'   => is_array( $v ) ? trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ) : '',
				'heSo'  => $rieng ? (float) $v['heSo'] : (float) $c['heSo'],
				'rieng' => $rieng,
				'lap'   => ( 5 === strlen( $k ) ),
			);
		}
		usort( $ra, function ( $a, $b ) {
			if ( $a['lap'] !== $b['lap'] ) { return $a['lap'] ? -1 : 1; }
			return strcmp( $a['ngay'], $b['ngay'] );
		} );
		return $ra;
	}

	/** Chỉ mấy cái khoá ngày — để `VHCC_Luong::mtd_ngay_le()` gộp vào danh sách của nhánh MTD. */
	public static function ds_ngay( $cfg = null ) {
		$ra = array();
		foreach ( self::ds( $cfg ) as $x ) { $ra[] = $x['ngay']; }
		return $ra;
	}

	/**
	 * Ngày này có phải ngày lễ không. Nhận `YYYY-MM-DD`.
	 *
	 * ⚠️ Cùng luật với `VHCC_Luong::mtd_la_le()`: khoá 5 ký tự là `MM-DD` lặp hằng năm.
	 */
	public static function la_le( $ngay, $cfg = null ) {
		return ( null !== self::muc_cua( $ngay, $cfg ) );
	}

	/** Mục ngày lễ khớp với ngày này, hoặc `null`. Khớp ĐÚNG NGÀY thắng khớp lặp hằng năm. */
	public static function muc_cua( $ngay, $cfg = null ) {
		$d = trim( (string) $ngay );
		if ( 10 !== strlen( $d ) ) { return null; }
		$md   = substr( $d, 5 );
		$vong = null;
		foreach ( self::ds( $cfg ) as $x ) {
			if ( $x['ngay'] === $d ) { return $x; }          // đúng ngày: thắng ngay
			if ( $x['lap'] && $x['ngay'] === $md ) { $vong = $x; }
		}
		return $vong;
	}

	/**
	 * Hệ số của một ngày. Ngày thường trả về `1.0` — KHÔNG trả `0`.
	 *
	 * 🔴 Trả 0 cho ngày thường là cách chắc chắn nhất để một nơi gọi quên kiểm tra rồi nhân
	 *    lương cả tháng với 0. Trả 1 thì nơi nào lỡ nhân bừa cũng vẫn ra đúng số cũ.
	 */
	public static function he_so_cua( $ngay, $cfg = null ) {
		$m = self::muc_cua( $ngay, $cfg );
		return $m ? (float) $m['heSo'] : 1.0;
	}

	/** Viết một hệ số cho người đọc: 2.00 → "2", 2.50 → "2,5". */
	public static function so( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2, ',', '' ), '0' ), ',' );
	}

	public static function ten_cua( $ngay, $cfg = null ) {
		$m = self::muc_cua( $ngay, $cfg );
		return $m ? (string) $m['ten'] : '';
	}

	/** Có bật thật không — chưa khai ngày nào, hoặc hệ số vẫn là 1, thì cả tính năng là số 0. */
	public static function dang_chay( $cfg = null ) {
		$c = ( null === $cfg ) ? self::cfg() : $cfg;
		foreach ( self::ds( $c ) as $x ) {
			if ( (float) $x['heSo'] > 1.0 ) { return true; }
		}
		return false;
	}

	/* ====================================================================== ghi */

	/**
	 * Chuẩn hoá một ngày người ta gõ vào. Nhận `YYYY-MM-DD`, `MM-DD`, và `DD/MM` / `DD/MM/YYYY`
	 * kiểu người Việt gõ quen tay.
	 *
	 * ⚠️ TRẢ '' KHI KHÔNG ĐỌC ĐƯỢC, đừng đoán. Đoán nhầm một ngày là nhân đôi lương của một
	 *    ngày không ai nghỉ, và không có gì trên màn cho thấy nó sai.
	 */
	public static function chuan_ngay( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return ''; }
		$s = str_replace( array( '.', '/' ), '-', $s );

		if ( 1 === preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m ) ) {
			return self::ghep( $m[1], $m[2], $m[3] );
		}
		/* `DD-MM-YYYY` — kiểu gõ quen của người Việt. Chỉ nhận khi số đầu > 12 hoặc số giữa ≤ 12
		   thì vẫn mơ hồ, nên ĐÒI đủ bốn chữ số năm ở cuối mới đọc theo chiều này. */
		if ( 1 === preg_match( '/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $s, $m ) ) {
			return self::ghep( $m[3], $m[2], $m[1] );
		}
		/* ═══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 HAI SỐ = LẶP HẰNG NĂM, VÀ NGƯỜI VIỆT GÕ **NGÀY TRƯỚC THÁNG SAU**
		 * ═══════════════════════════════════════════════════════════════════════════════════
		 * Đây là chỗ dễ sai nhất cả tệp, và sai KHÔNG BÁO GÌ. Kho lưu dùng `MM-DD` cho khớp
		 * `VHCC_Luong::mtd_la_le()` — nhưng người gõ vào ô ấy là người Việt, và "2-9" với họ là
		 * **mùng 2 tháng 9**, Quốc khánh. Đọc thẳng theo kho là ra **mùng 9 tháng 2**: mất hẳn
		 * ngày lễ lớn, mà trên màn vẫn hiện một dòng trông như đã khai xong.
		 *
		 * Nên: ĐỌC theo `DD-MM` (kiểu người ta gõ), LƯU theo `MM-DD` (kiểu kho cần).
		 *
		 * ⚠️ Số thứ hai > 12 thì không thể là tháng — người ấy đang gõ theo kiểu `MM-DD`, đọc
		 *    ngược lại. `9-25` chỉ có thể là 25 tháng 9.
		 * ═══════════════════════════════════════════════════════════════════════════════════ */
		if ( 1 === preg_match( '/^(\d{1,2})-(\d{1,2})$/', $s, $m ) ) {
			$a = (int) $m[1];
			$b = (int) $m[2];
			if ( $b > 12 && $a >= 1 && $a <= 12 ) { $t = $a; $n = $b; }   // MM-DD
			else { $n = $a; $t = $b; }                                     // DD-MM, kiểu Việt
			if ( $t < 1 || $t > 12 || $n < 1 || $n > 31 ) { return ''; }
			return sprintf( '%02d-%02d', $t, $n );
		}
		return '';
	}

	private static function ghep( $nam, $thang, $ngay ) {
		$y = (int) $nam; $t = (int) $thang; $n = (int) $ngay;
		if ( $y < 2000 || $y > 2100 || ! checkdate( $t, $n, $y ) ) { return ''; }
		return sprintf( '%04d-%02d-%02d', $y, $t, $n );
	}

	private static function doc_he_so( $x ) {
		$s = trim( str_replace( ',', '.', (string) $x ) );
		if ( '' === $s || ! is_numeric( $s ) ) { return null; }
		$n = (float) $s;
		if ( $n < 1.0 || $n > self::HE_SO_TOI_DA ) { return null; }
		return round( $n, 2 );
	}

	private static function gac( $u ) {
		if ( VHCC_Vai::duoc( $u, self::QUYEN ) ) { return ''; }
		return VHCC_Vai::loi( $u, self::QUYEN, 'Sửa lịch nghỉ lễ' );
	}

	private static function luu( $c, $u ) {
		VHCC_Luong::dat_cai_dat( self::O, $c, $u );
	}

	/** Đặt hệ số dùng chung. */
	public static function dat_he_so( $u, $he_so ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		$n = self::doc_he_so( $he_so );
		if ( null === $n ) {
			return array( 'ok' => false, 'error' => 'Hệ số phải là số từ 1 tới '
				. self::HE_SO_TOI_DA . '. Gõ 1 nghĩa là ngày lễ ăn y như ngày thường; gõ 2 là '
				. 'gấp đôi.' );
		}
		$c = self::cfg();
		$c['heSo'] = $n;
		self::luu( $c, $u );
		return array( 'ok' => true, 'heSo' => $n );
	}

	/** Thêm hoặc sửa một ngày lễ. Hệ số để trống = theo hệ số chung. */
	public static function them( $u, $ngay, $ten = '', $he_so = '' ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		$k = self::chuan_ngay( $ngay );
		if ( '' === $k ) {
			return array( 'ok' => false, 'error' => 'Không đọc được ngày "'
				. mb_substr( trim( (string) $ngay ), 0, 20 ) . '". Gõ '
				. '<b>2027-02-17</b> cho ngày chỉ nghỉ một lần (Tết âm), hay <b>2-9</b> '
				. '(ngày trước, tháng sau) cho ngày lặp hằng năm (Quốc khánh).' );
		}
		$c = self::cfg();
		$m = array( 'ten' => mb_substr( trim( (string) $ten ), 0, 60 ) );
		$n = self::doc_he_so( $he_so );
		/* Ô hệ số riêng để trống là "theo hệ số chung" — chứ không phải 0. Lưu `null` để sau này
		   đổi hệ số chung là mọi ngày chưa khai riêng đổi theo, khỏi sửa từng dòng. */
		if ( null !== $n ) { $m['heSo'] = $n; }
		$c['ngay'][ $k ] = $m;
		self::luu( $c, $u );
		return array( 'ok' => true, 'ngay' => $k );
	}

	/**
	 * 🔴 KHOÁ ĐÃ LƯU THÌ DÙNG NGUYÊN, KHÔNG ĐỌC LẠI BẰNG `chuan_ngay()`.
	 *
	 * Kho lưu `MM-DD`; `chuan_ngay()` đọc hai số theo kiểu người gõ là `DD-MM`. Đem một khoá
	 * đã chuẩn qua nó là LẬT NGƯỢC: `09-02` (2 tháng 9) thành `02-09` (9 tháng 2).
	 *
	 * Đây đúng là lỗi đã có trong `xoa()`: nút **Xoá** ở một dòng lặp hằng năm đi tìm một khoá
	 * không tồn tại rồi trả về "không có ngày ấy trong lịch" — tức là **chưa bao giờ xoá được
	 * một ngày lễ hằng năm**. Bài `kiem-ngay-le-quy-cong.php` bắt được nó khi thêm nút Sửa.
	 * Cùng họ với lỗi đã sửa trong `ds()`.
	 *
	 * Luật phân biệt: khoá đến TỪ KHO (ô ẩn trên màn) thì đã đúng hình dạng — dùng nguyên.
	 * Chuỗi đến TỪ NGƯỜI GÕ thì mới đọc nghĩa.
	 */
	private static function khoa_da_luu( $s ) {
		$k = trim( (string) $s );
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2}|\d{2}-\d{2})$/', $k ) ) { return $k; }
		return self::chuan_ngay( $k );
	}

	public static function xoa( $u, $ngay ) {
		$loi = self::gac( $u );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		$k = self::khoa_da_luu( $ngay );
		$c = self::cfg();
		if ( '' === $k || ! isset( $c['ngay'][ $k ] ) ) {
			return array( 'ok' => false, 'error' => 'Không có ngày ấy trong lịch.' );
		}
		unset( $c['ngay'][ $k ] );
		self::luu( $c, $u );
		return array( 'ok' => true, 'ngay' => $k );
	}
}
