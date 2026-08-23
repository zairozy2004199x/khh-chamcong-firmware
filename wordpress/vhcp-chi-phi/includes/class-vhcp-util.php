<?php
/**
 * Tiện ích chung — cổng chuyển đổi giữa cách Google Sheet lưu giá trị và cách MySQL lưu.
 *
 * Bên Apps Script, ô trống ('') khác 0: "chưa nhập Thực mua" khác "Thực mua = 0".
 * Ở đây ô trống = NULL, nên mọi hàm đọc/ghi đều đi qua blank_or_num()/num().
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Util {

	/** Múi giờ app (mặc định Asia/Bangkok — giống appsscript.json của app cũ). */
	public static function tz() {
		$tz = get_option( 'vhcp_timezone' );
		if ( ! $tz ) { $tz = 'Asia/Bangkok'; }
		try { return new DateTimeZone( $tz ); } catch ( Exception $e ) { return new DateTimeZone( 'Asia/Bangkok' ); }
	}

	public static function now() {
		return new DateTime( 'now', self::tz() );
	}

	/** 'Y-m-d H:i:s' theo giờ app — dạng lưu vào cột DATETIME. */
	public static function now_sql() {
		return self::now()->format( 'Y-m-d H:i:s' );
	}

	/** 'Y-m-d' hôm nay theo giờ app. */
	public static function today_sql() {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Bản sao của _fmt() bên Apps Script: giá trị ngày → 'dd/MM/yyyy', còn lại giữ nguyên chuỗi.
	 * Chuỗi ISO trong DB ('2026-08-20', '2026-08-20 09:12:00') được coi là ngày.
	 */
	public static function fmt( $v ) {
		if ( $v === null || $v === '' ) { return ''; }
		if ( $v instanceof DateTimeInterface ) { return $v->format( 'd/m/Y' ); }
		$s = (string) $v;
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(:\d{2})?)?$/', $s, $m ) ) {
			if ( $m[1] === '0000' ) { return ''; }
			return $m[3] . '/' . $m[2] . '/' . $m[1];
		}
		return $s;
	}

	/**
	 * NGÀY CÓ VÔ LÝ KHÔNG (đã ở dạng dd/MM/yyyy)?
	 *
	 * Bảng xuất MISA từng ra ngày "22/08/4622": gõ nhầm năm lúc nhập / ô ngày của file
	 * nạp vào bị lệch. Không tự sửa hộ — ngày là số liệu kế toán, đoán sai còn tệ hơn —
	 * nhưng phải BÁO trước khi tệp đi sang MISA, chứ không để lọt xuống sổ.
	 */
	public static function ngay_vo_ly( $dmy ) {
		$s = trim( (string) $dmy );
		if ( $s === '' ) { return false; }
		if ( ! preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m ) ) { return true; }
		$d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
		if ( $y < 2000 || $y > 2100 ) { return true; }
		return ! checkdate( $mo, $d, $y );
	}

	/** Bản sao của _fmtDT(): 'dd/MM/yyyy HH:mm:ss'. */
	public static function fmt_dt( $v ) {
		if ( $v === null || $v === '' ) { return ''; }
		if ( $v instanceof DateTimeInterface ) { return $v->format( 'd/m/Y H:i:s' ); }
		$s = (string) $v;
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/', $s, $m ) ) {
			return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . ( isset( $m[6] ) ? $m[6] : '00' );
		}
		return self::fmt( $s );
	}

	/**
	 * Đọc chuỗi ngày người dùng gửi lên ('dd/MM/yyyy', 'yyyy-mm-dd', 'dd-mm-yyyy') → 'Y-m-d'.
	 * Không đọc được → null (ô trống).
	 */
	public static function parse_date( $v ) {
		if ( $v === null || $v === '' ) { return null; }
		if ( $v instanceof DateTimeInterface ) { return $v->format( 'Y-m-d' ); }
		$s = trim( (string) $v );
		if ( $s === '' ) { return null; }

		// 🔴 SỐ SÊ-RI CỦA BẢNG TÍNH ("46232.0") — PHẢI CHẶN Ở ĐÂY.
		//
		// Google Sheets / Excel lưu ngày là SỐ NGÀY kể từ 30/12/1899. Xuất ra CSV mà ô đó
		// chưa định dạng ngày thì ra "46232.0". Rơi xuống strtotime() bên dưới thì nó đọc
		// "4623" thành NĂM -> ra 23/08/4623. Đây chính là 580 dòng "22/08/4621" trong bảng
		// xuất MISA: mỗi dòng mất luôn ngày thật, thay bằng ngày nhập liệu + một năm bịa.
		//
		// Khoảng 20000–60000 là 1954–2064 — đủ rộng cho mọi ngày có thật của sổ sách, mà
		// vẫn không nuốt nhầm số 4 chữ số (năm) hay số nhỏ.
		if ( preg_match( '#^(\d{5})(?:\.0+)?$#', $s, $m ) ) {
			$n = (int) $m[1];
			if ( $n >= 20000 && $n <= 60000 ) {
				return gmdate( 'Y-m-d', ( $n - 25569 ) * 86400 );   // 25569 = 01/01/1970 tính theo gốc bảng tính
			}
		}

		// Năm phải là năm THẬT. "4621-08-23" là rác do sê-ri bảng tính sinh ra (xem trên);
		// nhận nó vào là chép nguyên cái sai xuống cơ sở dữ liệu.
		$hop_le = function ( $y ) { return ( (int) $y >= 2000 && (int) $y <= 2100 ); };
		if ( preg_match( '#^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})#', $s, $m ) ) {
			return $hop_le( $m[1] ) ? sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] ) : null;
		}
		if ( preg_match( '#^(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})#', $s, $m ) ) {
			$y = (int) $m[3];
			if ( $y < 100 ) { $y += 2000; }
			return $hop_le( $y ) ? sprintf( '%04d-%02d-%02d', $y, $m[2], $m[1] ) : null;
		}
		// Số trơ trọi mà không phải sê-ri hợp lệ thì KHÔNG phải ngày — đừng để strtotime
		// biến "2026" thành hôm nay.
		if ( preg_match( '#^\d+(?:\.\d+)?$#', $s ) ) { return null; }
		// strtotime() là chốt chặn cuối và là chỗ DỄ BỊA NHẤT: "46213.0" nó đọc thành năm
		// 4621. Ngày ngoài 2000–2100 thì thà trả null để chỗ gọi báo lỗi, còn hơn ghi xuống
		// một ngày không ai nhận ra là sai cho tới lúc xuất MISA.
		$ts = strtotime( $s );
		if ( ! $ts ) { return null; }
		$y = (int) gmdate( 'Y', $ts );
		if ( $y < 2000 || $y > 2100 ) { return null; }
		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * Đọc SỐ từ một ô bảng tính, dùng CHUNG cho mọi chỗ nạp dữ liệu.
	 *
	 * Ô có thể là chuỗi kiểu Việt Nam ("1.234.567", "1.234.567,5"), kiểu Mỹ
	 * ("1,234,567.5") hoặc SỐ THÔ của file .xlsx ("2405000.0000000005" —
	 * Google xuất công thức ra đủ độ chính xác float). Chỗ nào tự bỏ dấu chấm
	 * là biến 0,04 thành 4 và 2405000.0000000005 thành 2,4 triệu tỉ, nên chỉ
	 * bỏ dấu chấm khi nó ĐÚNG LÀ dấu nghìn (nhóm 3 chữ số).
	 *
	 * @return float|null null nếu ô trống hoặc không có số nào
	 */
	public static function doc_so( $v ) {
		if ( is_bool( $v ) ) { return $v ? 1.0 : 0.0; }
		if ( is_int( $v ) || is_float( $v ) ) { return (float) $v; }
		$s = trim( (string) $v );
		if ( $s === '' ) { return null; }
		$s   = str_replace( array( ' ', "\xc2\xa0", '₫', 'đ', 'VND' ), '', $s );
		$neg = ( strpos( $s, '(' ) !== false && strpos( $s, ')' ) !== false ) || strpos( $s, '-' ) === 0;
		$s   = str_replace( array( '(', ')', '+' ), '', $s );
		// SỐ KIỂU KHOA HỌC của file .xlsx: Google xuất số lớn thành "5.6453868E8".
		// Bộ đọc cũ bỏ mọi ký tự không phải chữ số/dấu chấm, tức bỏ luôn chữ E, nên
		// 5.6453868E8 thành 5.64538688 — 564.538.680đ hiện ra 5,65đ. Nhận dạng trước
		// mọi thứ khác vì chuỗi kiểu này không bao giờ là dấu nghìn.
		if ( preg_match( '/^-?\d+(?:[.,]\d+)?[eE][+-]?\d+$/', $s ) ) {
			$f = (float) str_replace( ',', '.', $s );
			if ( $neg && $f > 0 ) { $f = -$f; }
			return $f;
		}

		$has_dot   = strpos( $s, '.' ) !== false;
		$has_comma = strpos( $s, ',' ) !== false;
		if ( $has_dot && $has_comma ) {
			// dấu nào ở sau cùng là dấu thập phân
			if ( strrpos( $s, ',' ) > strrpos( $s, '.' ) ) { $s = str_replace( '.', '', $s ); $s = str_replace( ',', '.', $s ); }
			else { $s = str_replace( ',', '', $s ); }
		} elseif ( $has_comma ) {
			$s = ( preg_match( '/,\d{3}(\D|$)/', $s ) ) ? str_replace( ',', '', $s ) : str_replace( ',', '.', $s );
		} elseif ( $has_dot ) {
			// CHỈ bỏ dấu chấm khi cả chuỗi là số nhóm nghìn: 1.234.567 -> 1234567.
			// Còn 2405000.0000000005 hay 0.04 là số thô, giữ nguyên dấu thập phân.
			if ( preg_match( '/^-?\d{1,3}(\.\d{3})+$/', $s ) ) { $s = str_replace( '.', '', $s ); }
		}
		$s = preg_replace( '/[^0-9.\-]/', '', $s );
		if ( $s === '' || $s === '-' || ! is_numeric( $s ) ) { return null; }
		$f = (float) $s;
		if ( $neg && $f > 0 ) { $f = -$f; }
		return $f;
	}

	/**
	 * Bỏ đuôi ".0" của MÃ SỐ.
	 *
	 * Bảng tính coi PIN, TK Có, mã đơn vị là SỐ, nên khi xuất ra thì "2222" thành "2222.0"
	 * và "141" thành "141.0". PIN kiểu đó không còn khớp luật 4–8 chữ số nữa: người đó
	 * KHÔNG đăng nhập được, mà nhìn bảng thì vẫn thấy PIN nằm đó.
	 *
	 * Chỉ cắt khi phần thập phân toàn số 0 — "1.5" là số thật, không được cắt.
	 */
	/** Số tiền kiểu Việt Nam để ghép vào câu thông báo: 1234567 -> "1.234.567đ". */
	public static function tien( $v ) {
		return number_format( (float) self::num( $v ), 0, ',', '.' ) . 'đ';
	}

	public static function ma_so( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) { return ''; }
		if ( preg_match( '/^(-?\d+)\.0*$/', $s, $m ) ) { return $m[1]; }
		// Mã có cả CHỮ cũng bị bảng tính thêm đuôi: "NV9.0" -> "NV9". Chỉ cắt khi phần thập
		// phân toàn số 0 và phần trước không chứa dấu chấm nào — "1.5" hay "A.50" không đụng.
		if ( preg_match( '/^([A-Za-z0-9_\-]+)\.0+$/', $s, $m2 ) ) { return $m2[1]; }
		return $s;
	}

	/**
	 * PIN chỉ gồm chữ số — bảng tính hay trả về "2222.0", giữ nguyên là không đăng nhập được.
	 * Phải cắt đuôi ".0" TRƯỚC khi bỏ ký tự lạ, không thì "2222.0" thành "22220" — vẫn sai,
	 * mà lần này còn sai âm thầm vì trông vẫn giống một PIN hợp lệ.
	 */
	public static function pin_sach( $v ) {
		return preg_replace( '/\D+/', '', self::ma_so( $v ) );
	}

	/** Số (0 nếu không phải số) — tương đương Number(x)||0. */
	public static function num( $v ) {
		if ( is_bool( $v ) ) { return $v ? 1 : 0; }
		if ( $v === null || $v === '' ) { return 0; }
		if ( is_numeric( $v ) ) { return 0 + $v; }
		$s = str_replace( array( ',', ' ', "\xc2\xa0" ), '', (string) $v );
		return is_numeric( $s ) ? 0 + $s : 0;
	}

	/**
	 * Ô "có thể trống" (Thực mua / Thuế suất / Tạm ứng duyệt…):
	 * trả về null nếu trống hoặc không phải số, ngược lại trả số.
	 */
	public static function blank_or_num( $v ) {
		if ( $v === null ) { return null; }
		if ( is_bool( $v ) ) { return $v ? 1 : 0; }
		if ( is_string( $v ) && trim( $v ) === '' ) { return null; }
		if ( is_numeric( $v ) ) { return 0 + $v; }
		$s = str_replace( array( ',', ' ', "\xc2\xa0" ), '', (string) $v );
		return is_numeric( $s ) ? 0 + $s : null;
	}

	/** Ô trống → '' ; số → số (để trả về UI đúng như app cũ). */
	public static function out_num( $v ) {
		return ( $v === null || $v === '' ) ? '' : 0 + $v;
	}

	public static function s( $v ) {
		if ( $v === null || is_bool( $v ) || is_array( $v ) ) { return is_bool( $v ) ? ( $v ? 'true' : 'false' ) : ''; }
		return trim( (string) $v ) === '' ? (string) $v : (string) $v;
	}

	/** Chuỗi đã trim. */
	public static function st( $v ) {
		if ( $v === null || is_array( $v ) ) { return ''; }
		if ( is_bool( $v ) ) { return $v ? 'true' : 'false'; }
		return trim( (string) $v );
	}

	/** Bản sao của _uid(): 'D_<base36 thời gian><base36 ngẫu nhiên>'. */
	public static function uid( $prefix ) {
		$t = base_convert( (string) ( (int) ( microtime( true ) * 1000 ) ), 10, 36 );
		$r = base_convert( (string) wp_rand( 0, 46655 ), 10, 36 );
		return $prefix . '_' . $t . $r;
	}

	/** _cnFlag(): mặc định TÍCH (kế toán cá nhân xử lý). */
	public static function cn_flag( $v ) {
		if ( $v === 0 || $v === '0' || $v === false ) { return false; }
		return strtolower( trim( (string) $v ) ) !== 'false';
	}

	/** _isPhatSinh(): 1 = dòng phát sinh. */
	public static function is_phat_sinh( $v ) {
		if ( $v === 1 || $v === '1' || $v === true ) { return true; }
		return strtolower( trim( (string) $v ) ) === 'true';
	}

	/** _isNcc(): NCC nếu phân loại NCC HOẶC dòng cá nhân bị bỏ tích. */
	public static function is_ncc( $pltt, $cn ) {
		return ( $pltt === 'Nhà cung cấp' ) || ! self::cn_flag( $cn );
	}

	/** _daSan()/_mkSan(): làm sạch tên sheet/dự án. */
	public static function san( $s ) {
		$s = preg_replace( '/[\[\]\*\?\/\\\\:]/', ' ', (string) $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		$s = trim( $s );
		return mb_substr( $s, 0, 60 );
	}

	/** _quyenTruthy(). */
	public static function quyen_truthy( $v ) {
		if ( $v === 1 || $v === true || $v === '1' ) { return true; }
		$s = strtolower( trim( (string) $v ) );
		return in_array( $s, array( 'true', '✓', 'x' ), true );
	}

	/** Nối các phần không rỗng bằng dấu cách — bản sao hàm _j() dùng khi xuất MISA. */
	public static function j( $arr ) {
		$out = array();
		foreach ( $arr as $x ) {
			if ( $x === null ) { continue; }
			if ( trim( (string) $x ) === '' ) { continue; }
			$out[] = (string) $x;
		}
		return implode( ' ', $out );
	}

	/** _kyNum(): xếp thứ tự kỳ (tuần/tháng) theo ngày đầu kỳ. */
	public static function ky_num( $s ) {
		$s = trim( (string) $s );
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m ) ) {
			return (int) $m[3] * 10000 + (int) $m[2] * 100 + (int) $m[1];
		}
		if ( preg_match( '#\((\d{1,2})/(\d{1,2})\s*-\s*(\d{1,2})/(\d{1,2})/(\d{4})\)#', $s, $r ) ) {
			$sd = (int) $r[1]; $sm = (int) $r[2]; $em = (int) $r[4]; $yy = (int) $r[5];
			$sy = ( $sm > $em ) ? $yy - 1 : $yy;
			return $sy * 10000 + $sm * 100 + $sd;
		}
		if ( preg_match( '#(\d{1,2})/(\d{1,2})/(\d{4})#', $s, $g ) ) {
			return (int) $g[3] * 10000 + (int) $g[2] * 100 + (int) $g[1];
		}
		if ( preg_match( '#T\s*(\d{1,2})\s*/\s*(\d{4})#i', $s, $t ) ) {
			return (int) $t[2] * 10000 + (int) $t[1] * 100;
		}
		return -1;
	}

	/** Bản sao _vhParseDMY(): chuỗi ngày bất kỳ → DateTime (không giờ) hoặc null. */
	public static function vh_parse_dmy( $v ) {
		$ymd = self::parse_date( $v );
		if ( ! $ymd ) { return null; }
		$d = DateTime::createFromFormat( 'Y-m-d|', $ymd, self::tz() );
		return $d ? $d : null;
	}

	public static function vh_ymd( DateTime $d ) {
		return (int) $d->format( 'Y' ) * 10000 + (int) $d->format( 'n' ) * 100 + (int) $d->format( 'j' );
	}

	/** Thứ Hai của tuần chứa $d. */
	public static function vh_monday( DateTime $d ) {
		$x = new DateTime( $d->format( 'Y-m-d' ), self::tz() );
		$g = (int) $x->format( 'w' );              // 0 = Chủ nhật
		$shift = ( $g === 0 ) ? -6 : 1 - $g;
		if ( $shift !== 0 ) { $x->modify( ( $shift > 0 ? '+' : '' ) . $shift . ' day' ); }
		return $x;
	}

	/** Số tuần ISO. */
	public static function vh_week_no( DateTime $d ) {
		return (int) $d->format( 'W' );
	}

	/** Trả mảng JSON an toàn cho REST (chuỗi UTF-8, số là số). */
	public static function ok( $extra = array() ) {
		return array_merge( array( 'success' => true ), $extra );
	}

	public static function err( $msg ) {
		return array( 'success' => false, 'error' => $msg );
	}
}
