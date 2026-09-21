<?php
/**
 * ĐỔI GIÁ TRỊ — chép nguyên hành vi mục ⑥ của `goc/jp-capsule-v2/JP2_01_Core.gs`.
 *
 * =============================================================================================
 * 🔴 CHÉP Y NGUYÊN, KỂ CẢ CHỖ TRÔNG NHƯ LỖI. ĐÂY LÀ BẢN CHUYỂN, KHÔNG PHẢI BẢN SỬA.
 * =============================================================================================
 * Cả hệ JP tính tiền qua mấy hàm này. "Sửa cho đúng hơn" một hàm ở đây là đổi con số của MỌI
 * báo cáo cũ lẫn mới cùng lúc, và không ai đối chiếu nổi bản mới với bản cũ nữa — mất luôn
 * cách duy nhất để biết bản chuyển có đúng hay không.
 *
 * `tools/test/kiem-jp-doc.php` chạy CHÍNH tệp JavaScript gốc bằng node rồi đòi PHP ra y hệt.
 * Nên lệch một ly là bài kiểm đỏ, chứ không phải chờ tới lúc đối soát cuối tháng.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴🔴 MÌN CÓ SẴN TRONG BẢN GỐC — `num()` KHÔNG ĐỌC ĐƯỢC DẤU CHẤM NGĂN NGHÌN.
 * ---------------------------------------------------------------------------------------------
 * Nó bỏ mọi ký tự trừ chữ số, dấu chấm và dấu trừ, rồi để JavaScript đọc chuỗi còn lại. Hậu quả
 * đo được (chạy thật trên bản gốc):
 *
 *      "20000"      ->  20000     đúng
 *      "20,000"     ->  20000     đúng (dấu phẩy bị bỏ)
 *      "20000đ"     ->  20000     đúng
 *      "20.000"     ->  20        🔴 MẤT BA SỐ KHÔNG, KHÔNG MỘT LỜI BÁO
 *      "1.234.567"  ->  0         🔴 MẤT SẠCH
 *
 * Tức là: số tiền viết theo lối Việt Nam đi vào đây là sai tiền, im lặng. Hiện chưa thấy đường
 * nào đẩy chuỗi như thế vào — màn hình gửi số trần — nhưng chỉ cần một ô nhập dán từ Excel là
 * dính. Giữ nguyên hành vi vì đây là bản chuyển; `kiem-jp-doc.php` ghim đúng mấy con số trên
 * để ai muốn chữa thì phải chữa CÓ CHỦ Ý, và sửa cả bài kiểm.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 "CHƯA NHẬP" KHÁC "NHẬP SỐ 0" — VÀ ĐÓ LÀ MỘT SỰ CỐ CÓ THẬT.
 * ---------------------------------------------------------------------------------------------
 * `num()` biến cả hai thành 0. Chỉ số đồng hồ và tồn thực tế thì hai thứ ấy khác hẳn nhau, nên
 * có `blank()` và `num_hoac_trong()`. Chú thích trong bản gốc kể lại: đo nhầm bằng `jpBlank_`
 * làm "dòng ma hoá thành dòng có dữ liệu và chặn nộp mãi mãi" — 55 ô bị chặn oan.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Doc {

	/**
	 * Số. Xem khối 🔴🔴 ở đầu tệp trước khi đụng vào hàm này.
	 *
	 * Bám sát `Number()` của JavaScript: chuỗi rỗng ra 0, chuỗi không đọc được ra 0
	 * (bên kia là NaN rồi `isNaN` bắt lại).
	 */
	public static function num( $v ) {
		if ( '' === $v || null === $v ) { return 0; }
		/* `String(true)` bên JS ra "true" -> lọc sạch -> 0. PHP ép bool ra "1"/"" nên phải
		   chặn riêng, không thì hai bên lệch nhau ở đúng chỗ không ai ngờ. */
		if ( is_bool( $v ) ) { return 0; }
		$s = preg_replace( '/[^\d.\-]/', '', (string) $v );
		if ( '' === $s ) { return 0; }
		/* Hình dạng mà `Number()` chịu nhận: "-500", "12.5", ".5", "5.". Còn "1.2.3", "-",
		   "5-" thì bên kia ra NaN -> 0. */
		if ( ! preg_match( '/^-?(\d+\.?\d*|\.\d+)$/', $s ) ) { return 0; }
		$n = $s + 0;
		/* 🔴 SỐ NGUYÊN PHẢI RA KIỂU NGUYÊN. JavaScript chỉ có một kiểu số, nên bên kia `20.0`
		   và `20` là một. PHP thì `20 === 20.0` là SAI — để nguyên số thực là mọi phép so sánh
		   chặt trong phần mã chuyển sau này lệch đúng ở chỗ không ai ngờ, và `"20.000"` (ca có
		   mìn) trả về `20.0` trong khi bản gốc trả `20`. */
		return ( (float) $n == (int) $n && abs( $n ) < PHP_INT_MAX ) ? (int) $n : $n;
	}

	/** Chuỗi đã cắt khoảng trắng hai đầu. `null` ra chuỗi rỗng. */
	public static function str( $v ) {
		if ( null === $v ) { return ''; }
		if ( is_bool( $v ) ) { return $v ? 'true' : 'false'; }
		return trim( (string) $v );
	}

	/**
	 * CHƯA NHẬP hay chưa? Chỉ chuỗi rỗng và `null` mới là chưa nhập.
	 *
	 * ⚠️ Số `0` và chuỗi `'0'` KHÔNG phải "chưa nhập" — đó là người ta cố ý gõ 0. Lẫn hai thứ
	 *    này là chỗ bản gốc đã ngã một lần, xem khối 🔴 thứ hai ở đầu tệp.
	 */
	public static function blank( $v ) {
		return '' === $v || null === $v;
	}

	/** Số, nhưng GIỮ ô trống. Dùng cho chỉ số đồng hồ và tồn thực tế. */
	public static function num_hoac_trong( $v ) {
		return self::blank( $v ) ? '' : self::num( $v );
	}

	/**
	 * Số ảnh phải chụp. Ô TRỐNG là "chưa đặt" -> lấy mặc định; số 0 GÕ VÀO là cố ý -> giữ 0.
	 *
	 * ⚠️ Viết `?: 1` ở đây là số 0 gõ vào thành 1: kế toán đặt 0 rồi mở lại thấy 1, tưởng
	 *    không lưu được. Bản gốc từng có đúng ba chỗ hiểu ba kiểu về cùng một ô.
	 */
	public static function so_anh( $v, $mac_dinh = 1 ) {
		return self::blank( $v ) ? $mac_dinh : self::num( $v );
	}

	/** Chuẩn hoá về `yyyy-mm-dd`. Nhận `dd/mm/yyyy`, `yyyy-mm-dd`, hoặc đối tượng ngày. */
	public static function ngay( $v ) {
		/* Bên kia là `if (!v) return ''` — nên số 0 và chuỗi '0' cũng ra rỗng. Chép y nguyên. */
		if ( ! $v ) { return ''; }
		if ( $v instanceof DateTimeInterface ) { return $v->format( 'Y-m-d' ); }
		$s = trim( (string) $v );
		if ( preg_match( '#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $s, $m ) ) {
			return $m[3] . '-' . substr( '0' . $m[2], -2 ) . '-' . substr( '0' . $m[1], -2 );
		}
		if ( preg_match( '#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})#', $s, $m ) ) {
			return $m[1] . '-' . substr( '0' . $m[2], -2 ) . '-' . substr( '0' . $m[3], -2 );
		}
		/* Không nhận ra hình dạng thì TRẢ NGUYÊN, không ném lỗi và không đoán. Dữ liệu đời cũ
		   trong sheet đủ kiểu, nuốt nó đi là mất luôn. */
		return $s;
	}

	/** Ngày + giờ thành chuỗi `yyyy-mm-dd HH:MM`. */
	public static function ngay_gio( $v ) {
		if ( ! $v ) { return ''; }
		if ( $v instanceof DateTimeInterface ) { return $v->format( 'Y-m-d H:i' ); }
		return trim( (string) $v );
	}

	/** `yyyy-mm-dd` -> `dd/mm/yyyy`. Không đúng hình dạng thì trả nguyên. */
	public static function dmy( $v ) {
		$d = self::ngay( $v );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { return $d; }
		$p = explode( '-', $d );
		return $p[2] . '/' . $p[1] . '/' . $p[0];
	}

	/**
	 * So sánh chuỗi bỏ dấu, thường hoá — dùng khi dò tên cơ sở.
	 *
	 * ⚠️ CHỈ ĐỂ SO SÁNH. Đừng bao giờ lưu hay hiện kết quả của hàm này ra cho người đọc.
	 */
	public static function norm( $s ) {
		$s = mb_strtolower( self::str( $s ), 'UTF-8' );
		$n = array(
			'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
			'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
		);
		foreach ( $n as $thay => $bo ) {
			foreach ( preg_split( '//u', $bo, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
				$s = str_replace( $c, $thay, $s );
			}
		}
		return preg_replace( '/[^a-z0-9]+/', '', $s );
	}

	/** Tiền viết theo lối Việt Nam: `1.234.567`. Chỉ để HIỆN RA, không dùng để tính. */
	public static function money( $n ) {
		$v = self::num( $n );
		if ( (float) $v == (int) $v ) { return number_format( (float) $v, 0, ',', '.' ); }
		/* `toLocaleString('vi-VN')` bên kia in tối đa 3 số lẻ và cắt số 0 thừa ở đuôi. */
		$s = number_format( (float) $v, 3, ',', '.' );
		return rtrim( rtrim( $s, '0' ), ',' );
	}
}
