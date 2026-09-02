<?php
/**
 * THƯ VIỆN HỢP ĐỒNG CHẠY THẲNG TRÊN HOST.
 *
 * Anh Thắng 02/09/2026: *"Nếu giờ anh đang dùng web thì có thể đẩy thư viện hợp đồng lên và
 * chạy nội dung trên đó được không"* — anh chốt: nguồn ở **Google Drive + Google Sheet**, và
 * web cần làm tới mức **kho + tìm + xem/tải**.
 *
 * =================================================================================================
 * 🔴 ĐÂY LÀ LẦN ĐẦU PLUGIN HỢP ĐỒNG GIỮ DỮ LIỆU. Từ đầu tới giờ nó cố ý KHÔNG giữ gì (xem chú
 *    thích ở `VHD_DB`): mọi thứ nằm ở Sheet, plugin chỉ là cầu nối, nhờ vậy không bao giờ có hai
 *    nguồn sự thật lệch nhau. Nay có bảng thật thì cái rủi ro ấy quay lại, nên nó được chặn bằng
 *    ba luật cứng, và ba luật này là lý do tồn tại của gần hết mã bên dưới:
 *
 *      1. **Sheet vẫn là nguồn, host là BẢN SAO ĐỌC.** Không có một đường nào ở đây ghi ngược
 *         lên Sheet, và màn thư viện không có ô sửa nào. Muốn sửa hợp đồng thì sửa bên app gốc
 *         rồi kéo lại — một chiều, nên không bao giờ phải hỏi "bên nào mới".
 *      2. **Mỗi lần kéo là chép lại TOÀN BỘ**, không "cập nhật phần khác nhau". Đồng bộ từng
 *         phần nghe hay nhưng phải trả lời được câu "dòng biến mất bên Sheet thì bên này xử sao"
 *         — và câu ấy sai một lần là kho giữ lại hợp đồng đã bị xoá, mà không ai biết.
 *      3. **Giữ nguyên DÒNG GỐC trong cột `du_lieu`.** Ánh xạ cột do người khai, mà người thì gõ
 *         nhầm; giữ bản gốc thì khai lại là xong, không phải kéo lại từ đầu — và đối chiếu được
 *         với Sheet từng ô một khi nghi ngờ.
 *
 * ⚠️ KHÔNG ĐOÁN TÊN CỘT. Sheet hợp đồng của anh Thắng có mấy chục cột, tên tiếng Việt, và không
 *    ai ngoài anh biết cột nào là "ngày hết hạn". Nên plugin KHÔNG tự đoán: nó bày ra tên cột đọc
 *    được rồi để người khai chỉ. Đoán sai một cột ngày là cả bảng cảnh báo sắp hết hạn im lặng
 *    sai, mà nhìn màn hình thì vẫn đầy số.
 * =============================================================================================== */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_Kho {

	/** Ánh xạ "trường của kho" -> "tên cột trong Sheet". Một mảng trong `wp_options`. */
	const O_ANH_XA = 'vhd_anh_xa';

	/**
	 * LÔ ĐANG DÙNG. Mỗi lượt kéo ghi vào một lô mới; khi và CHỈ KHI ghi xong hết thì con trỏ này
	 * mới chuyển sang lô mới, rồi lô cũ mới bị xoá.
	 *
	 * 🔴 ĐÂY LÀ CÁCH "THAY TOÀN BỘ" MÀ KHÔNG BAO GIỜ ĐỂ KHO TRỐNG. Xoá trước rồi ghi mà đứt giữa
	 *    chừng (mạng rớt, PHP hết giờ, host cắt tiến trình) là mất sạch thư viện — tệ hơn hẳn kho
	 *    cũ. Với lô: đứt lúc nào thì con trỏ vẫn đang trỏ lô CŨ, kho vẫn đầy đủ và đúng như trước
	 *    lượt kéo; mấy dòng nửa chừng của lô mới nằm im và bị dọn ở lượt sau.
	 *
	 * ⚠️ KHÔNG DÙNG `CREATE TABLE … LIKE` + `RENAME TABLE`. Cách ấy gọn hơn nhưng là DDL: nhiều
	 *    hosting chia sẻ không cho tài khoản MySQL tạo/đổi tên bảng lúc chạy, và khi bị chối thì
	 *    chối im lặng — kho trống mà màn hình báo "đã ghi xong".
	 */
	const O_LO = 'vhd_lo';

	/** Lần kéo gần nhất: thời điểm, số dòng, người kéo. */
	const O_LAN_KEO = 'vhd_lan_keo';

	/**
	 * Những trường kho hiểu được. Khoá là tên cột trong MySQL, giá trị là nhãn hiện ra.
	 *
	 * ⚠️ CHỈ CHÍN TRƯỜNG, VÀ CỐ Ý ÍT. Sheet có mấy chục cột; bê hết sang là bảng MySQL phải đổi
	 *    mỗi lần Sheet thêm cột. Chín trường này là những thứ kho cần để TÌM và LỌC; mọi thứ còn
	 *    lại vẫn nguyên vẹn trong `du_lieu` và vẫn hiện ở màn chi tiết.
	 */
	const TRUONG = array(
		'ma'       => 'Mã hợp đồng',
		'ten'      => 'Tên hợp đồng',
		'coso'     => 'Cơ sở',
		'ben_a'    => 'Bên A',
		'ben_b'    => 'Bên B',
		'ngay_ky'  => 'Ngày ký',
		'ngay_het' => 'Ngày hết hạn',
		'tien'     => 'Tiền thuê / giá trị',
		'link'     => 'Đường dẫn tệp (Drive)',
	);

	/* ================================================================== ánh xạ */

	public static function anh_xa() {
		$m = get_option( self::O_ANH_XA, array() );
		if ( ! is_array( $m ) ) { $m = array(); }
		$ra = array();
		foreach ( self::TRUONG as $k => $nhan ) {
			$ra[ $k ] = isset( $m[ $k ] ) ? trim( (string) $m[ $k ] ) : '';
		}
		return $ra;
	}

	public static function luu_anh_xa( $m ) {
		$sach = array();
		foreach ( self::TRUONG as $k => $nhan ) {
			$sach[ $k ] = isset( $m[ $k ] ) ? trim( (string) ( is_array( $m[ $k ] ) ? '' : $m[ $k ] ) ) : '';
		}
		update_option( self::O_ANH_XA, $sach );
		return $sach;
	}

	/* ============================================================ đọc bảng thô */

	/**
	 * ĐƯA THỨ APP GỐC TRẢ VỀ VỀ MỘT HÌNH DẠNG DUY NHẤT: `array( 'cot' => [...], 'dong' => [[...]] )`.
	 *
	 * 🔴 KHÔNG BIẾT TRƯỚC HÌNH DẠNG, VÀ ĐÓ LÀ SỰ THẬT PHẢI SỐNG CHUNG. `getData` của app gốc là mã
	 *    của anh Thắng, viết trong Apps Script, có thể trả về bất kỳ dạng nào trong bốn dạng dưới —
	 *    và nếu ở đây chỉ nhận đúng một dạng thì hôm nào app đổi là kho im lặng nhận 0 dòng, màn
	 *    hình báo "kéo xong" và bảng trống trơn.
	 *
	 *    Bốn dạng nhận được:
	 *      a) `{ header:[...], rows:[[...]] }`  (hoặc `cot`/`dong`, `headers`/`values`)
	 *      b) `[[tiêu đề], [dòng], [dòng]…]`     — mảng của mảng, hàng đầu là tiêu đề
	 *      c) `[ {cột: giá trị}, … ]`            — mảng của object
	 *      d) `{ data: <một trong ba dạng trên> }`
	 *
	 * ⚠️ TRẢ VỀ `cot` RỖNG LÀ MỘT KẾT QUẢ HỢP LỆ, không phải lỗi — bảng không có tiêu đề thì cột
	 *    mang tên "Cột 1", "Cột 2"… Người khai vẫn chỉ được, chỉ là phải đếm.
	 */
	public static function doc_bang( $raw ) {
		/* (d) bóc lớp bọc, tối đa vài lớp — có app bọc hai lần. */
		$sau = 0;
		while ( is_array( $raw ) && $sau < 4 ) {
			$co_khoa = false;
			foreach ( array( 'data', 'result', 'ket_qua' ) as $k ) {
				if ( array_key_exists( $k, $raw ) ) { $raw = $raw[ $k ]; $co_khoa = true; break; }
			}
			if ( ! $co_khoa ) { break; }
			$sau++;
		}
		if ( ! is_array( $raw ) ) { return array( 'cot' => array(), 'dong' => array() ); }

		/* (a) có sẵn tiêu đề và thân riêng. */
		$cap = array(
			array( 'header', 'rows' ), array( 'cot', 'dong' ),
			array( 'headers', 'values' ), array( 'cols', 'rows' ),
		);
		foreach ( $cap as $c ) {
			if ( isset( $raw[ $c[0] ] ) && isset( $raw[ $c[1] ] )
				&& is_array( $raw[ $c[0] ] ) && is_array( $raw[ $c[1] ] ) ) {
				return array(
					'cot'  => self::chuoi_hoa( array_values( $raw[ $c[0] ] ) ),
					'dong' => self::dong_hoa( array_values( $raw[ $c[1] ] ), self::chuoi_hoa( array_values( $raw[ $c[0] ] ) ) ),
				);
			}
		}

		$ds = array_values( $raw );
		if ( ! $ds ) { return array( 'cot' => array(), 'dong' => array() ); }

		/* (c) mảng của object -> tên cột là khoá của phần tử ĐẦU TIÊN CÓ ĐỦ KHOÁ NHẤT.
		   ⚠️ Không lấy khoá của phần tử đầu: JSON bỏ ô rỗng ở cuối, nên hàng đầu có thể thiếu
		      cột mà những hàng sau vẫn có — lấy hàng đầu là mất hẳn mấy cột cuối bảng. */
		$la_obj = true;
		foreach ( $ds as $d ) {
			if ( ! is_array( $d ) || ( $d && array_keys( $d ) === range( 0, count( $d ) - 1 ) ) ) { $la_obj = false; break; }
		}
		if ( $la_obj ) {
			$cot = array();
			foreach ( $ds as $d ) {
				foreach ( array_keys( $d ) as $k ) {
					if ( ! in_array( (string) $k, $cot, true ) ) { $cot[] = (string) $k; }
				}
			}
			$dong = array();
			foreach ( $ds as $d ) {
				$h = array();
				foreach ( $cot as $k ) { $h[] = isset( $d[ $k ] ) ? self::o_hoa( $d[ $k ] ) : ''; }
				$dong[] = $h;
			}
			return array( 'cot' => $cot, 'dong' => $dong );
		}

		/* (b) mảng của mảng: hàng đầu là tiêu đề NẾU nó trông như tiêu đề. */
		$dau = is_array( $ds[0] ) ? self::chuoi_hoa( array_values( $ds[0] ) ) : array();
		if ( $dau && self::trong_nhu_tieu_de( $dau ) ) {
			return array( 'cot' => $dau, 'dong' => self::dong_hoa( array_slice( $ds, 1 ), $dau ) );
		}
		$rong = is_array( $ds[0] ) ? count( $ds[0] ) : 0;
		$cot  = array();
		for ( $i = 1; $i <= $rong; $i++ ) { $cot[] = 'Cột ' . $i; }
		return array( 'cot' => $cot, 'dong' => self::dong_hoa( $ds, $cot ) );
	}

	/**
	 * Hàng đầu có phải TIÊU ĐỀ không?
	 *
	 * ⚠️ ĐOÁN SAI THEO HAI CHIỀU ĐỀU HẠI, nhưng hại khác nhau: coi tiêu đề là dữ liệu thì thừa
	 *    một hợp đồng tên "Mã HĐ" (buồn cười, thấy ngay); coi dữ liệu là tiêu đề thì MẤT một
	 *    hợp đồng thật và tên cột thành một mã hợp đồng (không ai thấy). Nên nghiêng về "chỉ
	 *    nhận là tiêu đề khi khá chắc": mọi ô đều là chữ, không ô nào là ngày hay số.
	 */
	public static function trong_nhu_tieu_de( $hang ) {
		$co_chu = 0;
		foreach ( $hang as $o ) {
			$o = trim( (string) $o );
			if ( '' === $o ) { continue; }
			if ( null !== self::chuan_ngay( $o ) ) { return false; }
			if ( preg_match( '/^-?[\d.,\s]+$/u', $o ) ) { return false; }
			$co_chu++;
		}
		return $co_chu > 0;
	}

	private static function chuoi_hoa( $a ) {
		$ra = array();
		foreach ( (array) $a as $x ) { $ra[] = trim( self::o_hoa( $x ) ); }
		return $ra;
	}

	private static function dong_hoa( $ds, $cot ) {
		$n  = count( $cot );
		$ra = array();
		foreach ( (array) $ds as $d ) {
			if ( ! is_array( $d ) ) { continue; }
			$h = array();
			if ( $d && array_keys( $d ) !== range( 0, count( $d ) - 1 ) ) {
				foreach ( $cot as $k ) { $h[] = isset( $d[ $k ] ) ? self::o_hoa( $d[ $k ] ) : ''; }
			} else {
				foreach ( array_values( $d ) as $x ) { $h[] = self::o_hoa( $x ); }
			}
			while ( count( $h ) < $n ) { $h[] = ''; }
			$ra[] = $h;
		}
		return $ra;
	}

	/** Một ô của Sheet -> chuỗi. Mảng/object lồng thì gói JSON, không để lộ "Array". */
	private static function o_hoa( $x ) {
		if ( is_array( $x ) || is_object( $x ) ) { return (string) wp_json_encode( $x ); }
		if ( is_bool( $x ) ) { return $x ? '1' : ''; }
		return (string) $x;
	}

	/* ================================================================ chuẩn hoá ô */

	/**
	 * MỘT Ô NGÀY -> 'YYYY-MM-DD', hoặc null nếu không phải ngày.
	 *
	 * ⚠️ `dd/mm/yyyy` LÀ MẶC ĐỊNH, KHÔNG PHẢI `mm/dd/yyyy`. Sổ của anh Thắng viết kiểu Việt; đọc
	 *    nhầm chiều thì 03/09 thành 09/03 — vẫn là một ngày hợp lệ, vẫn hiện ra bình thường, và
	 *    sai đúng sáu tháng. Chỉ khi số đầu > 12 mới chắc chắn suy ra được chiều, còn lại thì
	 *    theo quy ước Việt.
	 */
	public static function chuan_ngay( $x ) {
		$s = trim( (string) $x );
		if ( '' === $s ) { return null; }

		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m ) ) {
			return self::ghep_ngay( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		if ( preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})#', $s, $m ) ) {
			$a = (int) $m[1]; $b = (int) $m[2];
			if ( $a > 12 && $b <= 12 ) { return self::ghep_ngay( (int) $m[3], $b, $a ); }
			if ( $b > 12 && $a <= 12 ) { return self::ghep_ngay( (int) $m[3], $a, $b ); }
			return self::ghep_ngay( (int) $m[3], $b, $a );   // quy ước Việt: ngày/tháng/năm
		}
		/* Chuỗi ISO đầy đủ mà Apps Script hay trả cho ô Date: 2026-09-01T00:00:00.000Z */
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T/', $s ) ) { return substr( $s, 0, 10 ); }
		return null;
	}

	private static function ghep_ngay( $y, $m, $d ) {
		if ( $y < 1900 || $y > 2200 || $m < 1 || $m > 12 || $d < 1 || $d > 31 ) { return null; }
		if ( ! checkdate( $m, $d, $y ) ) { return null; }
		return sprintf( '%04d-%02d-%02d', $y, $m, $d );
	}

	/**
	 * MỘT Ô TIỀN -> số nguyên đồng.
	 *
	 * ⚠️ DẤU CHẤM TRONG SỔ VIỆT LÀ DẤU NGÀN, KHÔNG PHẢI DẤU THẬP PHÂN. `15.000.000` là mười lăm
	 *    triệu, không phải mười lăm. Đọc nhầm thì tiền thuê một cửa hàng thành mười lăm đồng —
	 *    và con số ấy vẫn cộng được, vẫn ra một bảng trông bình thường.
	 *
	 * ⚠️ Nhưng `1500000.5` (Apps Script trả số thực) thì dấu chấm LÀ thập phân. Phân biệt bằng
	 *    số chữ số sau dấu: đúng ba chữ số và có nhóm đều đặn -> dấu ngàn.
	 */
	public static function chuan_tien( $x ) {
		if ( is_int( $x ) || is_float( $x ) ) { return (int) round( (float) $x ); }
		$s = trim( (string) $x );
		if ( '' === $s ) { return 0; }
		$am = ( 0 === strpos( $s, '-' ) );
		$s  = preg_replace( '/[^\d.,]/u', '', $s );
		if ( '' === $s ) { return 0; }

		$cham = substr_count( $s, '.' );
		$phay = substr_count( $s, ',' );
		if ( $cham && $phay ) {
			/* Cái nào đứng SAU cùng là dấu thập phân, cái kia là dấu ngàn. */
			$s = ( strrpos( $s, ',' ) > strrpos( $s, '.' ) )
				? str_replace( array( '.', ',' ), array( '', '.' ), $s )
				: str_replace( ',', '', $s );
		} elseif ( $cham || $phay ) {
			/* Một loại dấu duy nhất: đếm chữ số SAU dấu cuối cùng. Đúng ba chữ số = nhóm ngàn
			   (`15.000.000`, `1,500`); khác ba = thập phân (`1500000.5`, `12,75`).
			   ⚠️ Ba chữ số cũng có thể là thập phân thật (`1.234` mét khối) — nhưng đây là sổ
			      TIỀN của một chuỗi cửa hàng Việt, nơi `1.234` gần như chắc chắn là một nghìn
			      hai trăm ba tư đồng. Đoán theo cái thường gặp, và nói ra là đang đoán. */
			$d   = $cham ? '.' : ',';
			$sau = strlen( $s ) - strrpos( $s, $d ) - 1;
			$s   = ( 3 === $sau )
				? str_replace( $d, '', $s )      // nhóm ngàn -> bỏ hết dấu
				: str_replace( $d, '.', $s );    // thập phân -> đưa về dấu chấm của PHP
		}
		$n = (int) round( (float) $s );
		return $am ? -$n : $n;
	}

	/* ==================================================================== kéo về */

	/**
	 * KÉO TOÀN BỘ THƯ VIỆN TỪ APP GỐC VỀ HOST.
	 *
	 * @param array  $u        người đang làm (đã qua cổng PIN)
	 * @param bool   $chi_xem  true = chỉ đếm và kể, KHÔNG ghi gì. Mặc định true.
	 * @param string $fn       hàm của app gốc để lấy bảng (mặc định `getData`)
	 */
	public static function keo( $u, $chi_xem = true, $fn = 'getData' ) {
		global $wpdb;
		if ( ! self::duoc_quan( $u ) ) {
			return array( 'ok' => false, 'error' => 'Kéo thư viện hợp đồng cần vai Admin hoặc Quản lý.' );
		}
		$r = VHD_CauNoi::goi( $fn );
		if ( empty( $r['ok'] ) ) { return array( 'ok' => false, 'error' => $r['error'] ); }

		$bang = self::doc_bang( $r['data'] );
		if ( ! $bang['dong'] ) {
			return array( 'ok' => false, 'error' => 'App gốc trả về 0 dòng qua hàm "' . $fn . '". '
				. 'Kiểm lại tên hàm ở ô "Hàm lấy dữ liệu", hoặc mở app gốc xem sheet có dữ liệu không.' );
		}

		$ax  = self::anh_xa();
		$vi  = array();                       // trường -> chỉ số cột
		foreach ( $ax as $k => $ten_cot ) {
			$vi[ $k ] = ( '' === $ten_cot ) ? -1 : self::vi_tri_cot( $bang['cot'], $ten_cot );
		}
		$thieu = array();
		foreach ( $ax as $k => $ten_cot ) {
			if ( '' !== $ten_cot && $vi[ $k ] < 0 ) { $thieu[] = self::TRUONG[ $k ] . ' → "' . $ten_cot . '"'; }
		}
		/* 🔴 KHAI MỘT CỘT KHÔNG TỒN TẠI LÀ CHỐI HẲN, không lặng lẽ bỏ qua. Bỏ qua thì cột ấy vào
		   kho toàn rỗng, bảng vẫn dựng được, và không có gì nói rằng ngày hết hạn của cả nghìn
		   hợp đồng đang trống. */
		if ( $thieu ) {
			return array( 'ok' => false, 'error' => 'Cột đã khai không có trong bảng của app gốc: '
				. implode( ' · ', $thieu ) . '. Tên cột hiện có: ' . implode( ' · ', $bang['cot'] ) );
		}

		$hang = array();
		$ma_trung = array();
		foreach ( $bang['dong'] as $i => $d ) {
			$lay = function ( $k ) use ( $vi, $d ) {
				return ( $vi[ $k ] >= 0 && isset( $d[ $vi[ $k ] ] ) ) ? (string) $d[ $vi[ $k ] ] : '';
			};
			$goc = array();
			foreach ( $bang['cot'] as $j => $ten_cot ) {
				$goc[ $ten_cot ] = isset( $d[ $j ] ) ? $d[ $j ] : '';
			}
			$ma = trim( $lay( 'ma' ) );
			if ( '' !== $ma ) {
				$k_ma = mb_strtolower( $ma, 'UTF-8' );
				if ( isset( $ma_trung[ $k_ma ] ) ) { $ma_trung[ $k_ma ]++; } else { $ma_trung[ $k_ma ] = 1; }
			}
			$hang[] = array(
				'ma'       => mb_substr( $ma, 0, 190, 'UTF-8' ),
				'ten'      => $lay( 'ten' ),
				'coso'     => mb_substr( trim( $lay( 'coso' ) ), 0, 190, 'UTF-8' ),
				'ben_a'    => mb_substr( trim( $lay( 'ben_a' ) ), 0, 255, 'UTF-8' ),
				'ben_b'    => mb_substr( trim( $lay( 'ben_b' ) ), 0, 255, 'UTF-8' ),
				'ngay_ky'  => self::chuan_ngay( $lay( 'ngay_ky' ) ),
				'ngay_het' => self::chuan_ngay( $lay( 'ngay_het' ) ),
				'tien'     => self::chuan_tien( $lay( 'tien' ) ),
				'link'     => trim( $lay( 'link' ) ),
				'du_lieu'  => wp_json_encode( $goc, JSON_UNESCAPED_UNICODE ),
				'hang'     => (int) $i + 1,
			);
		}

		$trung = array();
		foreach ( $ma_trung as $k => $n ) { if ( $n > 1 ) { $trung[] = $k . ' (' . $n . ' lần)'; } }

		$ke = array(
			'ok'       => true,
			'chi_xem'  => (bool) $chi_xem,
			'so_cot'   => count( $bang['cot'] ),
			'cot'      => $bang['cot'],
			'so_dong'  => count( $hang ),
			'xem_thu'  => array_slice( $hang, 0, 5 ),
			'ma_trung' => $trung,
			'thieu_ma' => 0,
			'thieu_het' => 0,
		);
		foreach ( $hang as $h ) {
			if ( '' === $h['ma'] ) { $ke['thieu_ma']++; }
			if ( null === $h['ngay_het'] ) { $ke['thieu_het']++; }
		}
		if ( $chi_xem ) { return $ke; }

		/* 🔴 THAY TOÀN BỘ BẰNG CÁCH ĐỔI LÔ — xem chú thích ở hằng `O_LO`. Ghi hết lô mới, chỉ khi
		   xong xuôi mới chuyển con trỏ, rồi mới dọn lô cũ. Đứt ở bất kỳ bước nào thì kho vẫn là
		   kho cũ, đầy đủ. */
		$t   = VHD_DB::t( 'hd' );
		$nay = current_time( 'mysql' );
		$lo_cu  = self::lo();
		/* 🔴 LÔ MỚI PHẢI KHÁC LÔ CŨ, KHÔNG ĐƯỢC TRÙNG DÙ CHỈ MỘT LẦN. Bản đầu gieo bằng
		   `current_time('mysql')` — chỉ tới GIÂY — nên hai lượt kéo trong cùng một giây ra cùng
		   một lô, và bước dọn `DELETE lo=$lo_cu` ngay sau đó xoá luôn lô vừa ghi: kho TRỐNG
		   TRƠN, mà màn hình báo "đã ghi xong". Phép thử "kéo lại lần hai" bắt đúng chỗ này.
		   Nay gieo bằng microtime + số ngẫu nhiên, và vẫn kiểm lại một vòng cho chắc. */
		$lo_moi = '';
		for ( $lan = 0; $lan < 5; $lan++ ) {
			$lo_moi = substr( md5( microtime( true ) . '|' . wp_rand( 1, 2147483647 ) . '|' . $lan ), 0, 16 );
			if ( $lo_moi !== $lo_cu ) { break; }
		}
		if ( $lo_moi === $lo_cu ) {
			return array( 'ok' => false, 'error' => 'Không sinh được mã lô mới — thử lại lượt kéo.' );
		}
		foreach ( $hang as $h ) {
			$h['cap_nhat'] = $nay;
			$h['lo']       = $lo_moi;
			$wpdb->insert( $t, $h );
		}
		/* ⚠️ BỘ THỬ KHÔNG ĐO ĐƯỢC CHỐT NÀY. Bệ đỡ giả chạy SQLite, ở đó `insert()` không bao giờ
		   trượt lẻ tẻ, nên bỏ chốt đi bài kiểm vẫn xanh — phá thử đã chỉ ra. Giữ nó vì trên MySQL
		   thật thì trượt lẻ tẻ là chuyện có thật (ô vượt `max_allowed_packet`, kết nối chập giữa
		   chừng), và đổi con trỏ sang một lô thiếu dòng là MẤT hợp đồng, im lặng. */
		$da = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE lo=%s", $lo_moi ) );
		/* 🔴 ĐẾM LẠI TRƯỚC KHI ĐỔI CON TRỎ. `insert()` có thể trượt từng dòng (ô quá dài, mất kết
		   nối giữa chừng) mà không ném gì; đổi con trỏ sang một lô thiếu dòng là mất hợp đồng, im
		   lặng. Thiếu thì DỌN lô dở dang và giữ nguyên kho cũ. */
		if ( $da !== count( $hang ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE lo=%s", $lo_moi ) );
			return array( 'ok' => false, 'error' => 'Ghi vào kho bị thiếu (' . $da . '/' . count( $hang )
				. ' dòng) nên đã huỷ lượt kéo — kho cũ giữ nguyên. Thử lại; nếu vẫn thiếu thì bảng có ô '
				. 'quá dài hoặc kết nối cơ sở dữ liệu đang chập.' );
		}
		update_option( self::O_LO, $lo_moi );
		if ( '' !== $lo_cu ) { $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE lo=%s", $lo_cu ) ); }
		/* Dọn nốt lô mồ côi của những lượt kéo đứt trước đây. */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE lo<>%s", $lo_moi ) );

		update_option( self::O_LAN_KEO, array(
			'luc'   => $nay,
			'so'    => count( $hang ),
			'boi'   => isset( $u['name'] ) ? (string) $u['name'] : '',
			'ham'   => (string) $fn,
		) );
		$ke['da_ghi'] = count( $hang );
		return $ke;
	}

	/**
	 * Tìm tên cột — khớp CHÍNH XÁC trước, rồi mới khớp lỏng (bỏ dấu cách, hạ chữ thường).
	 *
	 * ⚠️ Khớp lỏng NGAY TỪ ĐẦU thì "Ngày ký" và "Ngày kỳ" thành một; khớp chính xác trước rồi
	 *    mới nới là giữ được đúng cột khi tên có sẵn, và vẫn cứu được ca thừa một dấu cách.
	 */
	public static function vi_tri_cot( $cot, $ten ) {
		$ten = trim( (string) $ten );
		foreach ( $cot as $i => $c ) { if ( (string) $c === $ten ) { return (int) $i; } }
		$g = self::rut( $ten );
		foreach ( $cot as $i => $c ) { if ( self::rut( $c ) === $g ) { return (int) $i; } }
		return -1;
	}

	private static function rut( $s ) {
		$s = preg_replace( '/\s+/u', '', (string) $s );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/* ====================================================================== đọc */

	public static function lo() {
		return (string) get_option( self::O_LO, '' );
	}

	/** Mệnh đề lọc lô, dùng chung cho mọi câu đọc — quên một chỗ là kho lẫn dòng của lô dở dang. */
	private static function dk_lo() {
		global $wpdb;
		$lo = self::lo();
		return ( '' === $lo ) ? '1=1' : $wpdb->prepare( 'lo=%s', $lo );
	}

	public static function lan_keo() {
		$x = get_option( self::O_LAN_KEO, array() );
		return is_array( $x ) ? $x : array();
	}

	public static function dem() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHD_DB::t( 'hd' )
			. ' WHERE ' . self::dk_lo() );
	}

	/**
	 * TÌM TRONG KHO.
	 *
	 * @param array $loc `q` (chữ) · `coso` · `het_truoc` (yyyy-mm-dd) · `trang` · `moi_trang`
	 */
	public static function tim( $loc = array() ) {
		global $wpdb;
		$t = VHD_DB::t( 'hd' );

		$dk = array( self::dk_lo() );
		$tv = array();
		$q  = isset( $loc['q'] ) ? trim( (string) $loc['q'] ) : '';
		if ( '' !== $q ) {
			/* Tìm cả trong DÒNG GỐC: người ta nhớ tên chủ nhà hay số điện thoại — mấy thứ ấy
			   nằm ở cột không được ánh xạ, mà vẫn phải tìm ra. */
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$dk[] = '(ma LIKE %s OR ten LIKE %s OR coso LIKE %s OR ben_a LIKE %s OR ben_b LIKE %s OR du_lieu LIKE %s)';
			array_push( $tv, $like, $like, $like, $like, $like, $like );
		}
		$cs = isset( $loc['coso'] ) ? trim( (string) $loc['coso'] ) : '';
		if ( '' !== $cs ) { $dk[] = 'coso=%s'; $tv[] = $cs; }
		$ht = isset( $loc['het_truoc'] ) ? trim( (string) $loc['het_truoc'] ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ht ) ) {
			$dk[] = '(ngay_het IS NOT NULL AND ngay_het<=%s)'; $tv[] = $ht;
		}
		$where = implode( ' AND ', $dk );

		$moi = isset( $loc['moi_trang'] ) ? max( 5, min( 200, (int) $loc['moi_trang'] ) ) : 50;
		$tr  = isset( $loc['trang'] ) ? max( 1, (int) $loc['trang'] ) : 1;
		$bo  = ( $tr - 1 ) * $moi;

		$sql_dem = "SELECT COUNT(*) FROM $t WHERE $where";
		$tong = (int) ( $tv ? $wpdb->get_var( $wpdb->prepare( $sql_dem, $tv ) ) : $wpdb->get_var( $sql_dem ) );

		/* Sắp: hợp đồng SẮP HẾT HẠN lên trước — đó là thứ người mở kho đi tìm. Không có ngày hết
		   hạn thì xuống cuối, chứ không lẫn vào giữa như khi để MySQL tự xếp NULL. */
		$sql = "SELECT * FROM $t WHERE $where"
			. ' ORDER BY (ngay_het IS NULL) ASC, ngay_het ASC, hang ASC'
			. ' LIMIT ' . (int) $bo . ', ' . (int) $moi;
		$ds = $tv ? $wpdb->get_results( $wpdb->prepare( $sql, $tv ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		return array( 'tong' => $tong, 'trang' => $tr, 'moi_trang' => $moi,
			'so_trang' => max( 1, (int) ceil( $tong / $moi ) ), 'ds' => (array) $ds );
	}

	public static function mot( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHD_DB::t( 'hd' ) . ' WHERE id=%d AND ' . self::dk_lo(), (int) $id ), ARRAY_A );
		return $r ? $r : null;
	}

	/** Danh sách cơ sở đang có trong kho — để đổ vào ô lọc. */
	public static function ds_coso() {
		global $wpdb;
		$ds = $wpdb->get_col( 'SELECT DISTINCT coso FROM ' . VHD_DB::t( 'hd' )
			. " WHERE coso<>'' AND " . self::dk_lo() . ' ORDER BY coso' );
		return array_values( array_filter( (array) $ds ) );
	}

	/** Ai được kéo / khai cột. Xem thư viện thì chỉ cần qua cổng PIN. */
	public static function duoc_quan( $u ) {
		$v = isset( $u['role'] ) ? (string) $u['role'] : '';
		return in_array( $v, array( 'Admin', 'Quản lý' ), true );
	}
}
