<?php
/**
 * KHOÁ VAPID — thứ duy nhất chứng minh với Apple/Google rằng lượt đẩy này là của website mình.
 *
 * =================================================================================================
 * WEB PUSH LÀM VIỆC NHƯ THẾ NÀO, GỌN NHẤT CÓ THỂ
 * =================================================================================================
 * Điện thoại không nhận thông báo từ máy chủ mình. Nó nhận từ **máy chủ đẩy của hãng** — Apple với
 * iPhone, Google với Android. Trình duyệt đăng ký với hãng rồi trả về một *endpoint*: một địa chỉ
 * https dài, và đó chính là "hộp thư" của đúng cái máy ấy.
 *
 * Mình gửi thông báo bằng cách POST vào địa chỉ đó. Nhưng ai POST vào cũng được thì hộp thư ấy
 * thành chỗ đổ rác, nên hãng đòi mỗi lượt POST phải mang một **JWT ký bằng khoá riêng của
 * website** — và khoá công khai tương ứng phải đúng cái khoá trình duyệt đã dùng lúc đăng ký. Đó
 * là VAPID.
 *
 * =================================================================================================
 * 🔴 BỐN CHỖ HỎNG IM LẶNG, MỖI CHỖ MẤT NỬA NGÀY NẾU KHÔNG BIẾT TRƯỚC
 * =================================================================================================
 * 1. KHOÁ SINH MỘT LẦN RỒI KHÔNG BAO GIỜ ĐƯỢC ĐỔI. Mọi máy đã đăng ký đều gắn với khoá công khai
 *    cũ; sinh khoá mới là TOÀN BỘ máy đã đăng ký thành rác — và chúng không báo lỗi gì cả, chỉ
 *    lặng lẽ không nhận được thông báo nào nữa. Nên `sinh()` chỉ chạy khi kho trống, và không có
 *    nút "sinh lại" ở đâu hết.
 *
 * 2. CHỮ KÝ ES256 PHẢI LÀ `r‖s` 64 BYTE, KHÔNG PHẢI DER. `openssl_sign()` trả về DER
 *    (`30 44 02 20 …`), còn JWT đòi hai số r và s dán liền, mỗi số đúng 32 byte. Đưa thẳng DER vào
 *    thì hãng trả 401 và câu lỗi không nói gì về chuyện này. Tệ hơn: r hoặc s thỉnh thoảng chỉ
 *    dài 31 byte (khi byte đầu là 0) — nên lỗi này **đúng khoảng 1/256 lượt** nếu chỉ cắt cứng mà
 *    không đệm số 0 vào đầu. Một lỗi hiện ra một lần trong hai trăm lần là lỗi không ai tìm ra.
 *
 * 3. BASE64URL, KHÔNG PHẢI BASE64. Dấu `+` `/` `=` làm hỏng JWT. Cả khoá công khai gửi cho trình
 *    duyệt cũng vậy.
 *
 * 4. `aud` LÀ GỐC CỦA ENDPOINT, KHÔNG PHẢI CẢ ĐỊA CHỈ. `https://fcm.googleapis.com` chứ không
 *    phải `https://fcm.googleapis.com/fcm/send/abc…`. Sai chỗ này cũng là 401.
 *
 * ⚠️ KHOÁ RIÊNG NẰM TRONG `wp_options`, KHÔNG nằm trong mã nguồn. Kho mã này CÔNG KHAI (xem
 *    README ở gốc) — một khoá riêng commit vào đây là ai cũng gửi được thông báo tới nhân viên
 *    của mình, mang đúng tên website mình.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCAPP_Khoa {

	const O = 'ccapp_vapid';

	/** Địa chỉ liên hệ trong JWT. Hãng dùng nó để báo khi mình gửi sai — phải là mailto: thật. */
	public static function lien_he() {
		$m = get_option( 'admin_email' );
		return ( is_string( $m ) && '' !== $m ) ? 'mailto:' . $m : 'mailto:admin@localhost';
	}

	public static function co() {
		$k = get_option( self::O );
		return is_array( $k ) && ! empty( $k['pub'] ) && ! empty( $k['pem'] );
	}

	/**
	 * Cặp khoá đang dùng. Chưa có thì SINH MỘT LẦN rồi giữ mãi (xem chỗ hỏng số 1).
	 *
	 * @return array|null array('pub'=>base64url khoá công khai, 'pem'=>khoá riêng PEM) hoặc null
	 *                    khi máy chủ không có OpenSSL đường cong P-256.
	 */
	public static function cap() {
		$k = get_option( self::O );
		if ( is_array( $k ) && ! empty( $k['pub'] ) && ! empty( $k['pem'] ) ) { return $k; }
		return self::sinh();
	}

	private static function sinh() {
		if ( ! function_exists( 'openssl_pkey_new' ) ) { return null; }
		$res = openssl_pkey_new( array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		) );
		if ( ! $res ) { return null; }
		$ct = openssl_pkey_get_details( $res );
		if ( ! $ct || empty( $ct['ec']['x'] ) || empty( $ct['ec']['y'] ) ) { return null; }
		$pem = '';
		if ( ! openssl_pkey_export( $res, $pem ) ) { return null; }

		/* Khoá công khai dạng "không nén": byte 0x04 rồi X rồi Y, mỗi số đúng 32 byte. Đệm 0 vào
		   ĐẦU khi ngắn — openssl bỏ số 0 dẫn đầu, và một khoá 63 byte thì trình duyệt chối thẳng
		   lúc đăng ký. */
		$pub = "\x04" . self::day32( $ct['ec']['x'] ) . self::day32( $ct['ec']['y'] );

		$k = array( 'pub' => self::b64u( $pub ), 'pem' => $pem, 'sinh_luc' => current_time( 'mysql' ) );
		update_option( self::O, $k, false );
		return $k;
	}

	/** Khoá công khai cho trình duyệt (`applicationServerKey`). '' = chưa sẵn sàng. */
	public static function pub() {
		$k = self::cap();
		return ( is_array( $k ) && ! empty( $k['pub'] ) ) ? (string) $k['pub'] : '';
	}

	/* ===================================================================== JWT */

	/**
	 * JWT VAPID cho một endpoint.
	 *
	 * @param string $endpoint địa chỉ hộp thư của máy nhận.
	 * @param int    $han_giay sống bao lâu. Hãng chối JWT quá 24 giờ; 12 giờ là dư dùng.
	 * @return string '' nếu không ký được.
	 */
	public static function jwt( $endpoint, $han_giay = 43200 ) {
		$k = self::cap();
		if ( ! is_array( $k ) || empty( $k['pem'] ) ) { return ''; }

		$aud = self::goc( $endpoint );
		if ( '' === $aud ) { return ''; }

		$dau  = self::b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$than = self::b64u( wp_json_encode( array(
			'aud' => $aud,
			'exp' => time() + (int) $han_giay,
			'sub' => self::lien_he(),
		) ) );
		$ky_tren = $dau . '.' . $than;

		$der = '';
		$pk  = openssl_pkey_get_private( $k['pem'] );
		if ( ! $pk ) { return ''; }
		if ( ! openssl_sign( $ky_tren, $der, $pk, 'sha256' ) ) { return ''; }

		$raw = self::der_sang_raw( $der );
		if ( '' === $raw ) { return ''; }
		return $ky_tren . '.' . self::b64u( $raw );
	}

	/**
	 * `aud` của JWT = GỐC của endpoint (scheme://host[:port]), không phải cả địa chỉ.
	 * Xem chỗ hỏng số 4.
	 */
	public static function goc( $url ) {
		$p = wp_parse_url( (string) $url );
		if ( empty( $p['scheme'] ) || empty( $p['host'] ) ) { return ''; }
		$g = $p['scheme'] . '://' . $p['host'];
		if ( ! empty( $p['port'] ) ) { $g .= ':' . (int) $p['port']; }
		return $g;
	}

	/**
	 * CHỮ KÝ DER (openssl) -> `r‖s` 64 byte (JWT). Xem chỗ hỏng số 2.
	 *
	 * DER của một chữ ký ECDSA:
	 *     30 <dài>  02 <dài-r> <r…>  02 <dài-s> <s…>
	 * r và s là số nguyên DER nên:
	 *   · có thể dài 33 byte, với một byte 0x00 dẫn đầu để nói "đây là số DƯƠNG" khi bit cao bật;
	 *   · có thể dài 31 byte hoặc ít hơn, khi số ấy tình cờ nhỏ.
	 * Cả hai trường hợp đều phải quy về đúng 32 byte: cắt bỏ 0x00 dẫn đầu, rồi đệm 0x00 vào ĐẦU
	 * cho đủ. Cắt cứng 32 byte cuối cũng ra đúng ở trường hợp một, nhưng SAI ở trường hợp hai —
	 * và trường hợp hai xảy ra khoảng 1/256 lượt ký.
	 *
	 * @return string 64 byte, hoặc '' nếu DER không đọc được.
	 */
	public static function der_sang_raw( $der ) {
		$d = (string) $der;
		$n = strlen( $d );
		$i = 0;
		if ( $i >= $n || "\x30" !== $d[ $i ] ) { return ''; }
		$i++;
		if ( $i >= $n ) { return ''; }
		$l = ord( $d[ $i ] );
		$i++;
		if ( $l & 0x80 ) {                       // dạng dài: 0x81 <len>, 0x82 <len><len>…
			$so = $l & 0x7F;
			if ( $so < 1 || $so > 2 || $i + $so > $n ) { return ''; }
			$i += $so;
		}

		$doc = function ( $d, &$i, $n ) {
			if ( $i >= $n || "\x02" !== $d[ $i ] ) { return null; }
			$i++;
			if ( $i >= $n ) { return null; }
			$len = ord( $d[ $i ] );
			$i++;
			if ( $len & 0x80 ) { return null; }  // r/s không bao giờ dài tới mức cần dạng dài
			if ( $i + $len > $n ) { return null; }
			$v = substr( $d, $i, $len );
			$i += $len;
			return $v;
		};

		$r = $doc( $d, $i, $n );
		$s = $doc( $d, $i, $n );
		if ( null === $r || null === $s ) { return ''; }
		return self::day32( $r ) . self::day32( $s );
	}

	/** Một số nhị phân -> đúng 32 byte: bỏ 0x00 dẫn đầu, rồi đệm 0x00 vào đầu cho đủ. */
	public static function day32( $v ) {
		$v = ltrim( (string) $v, "\x00" );
		if ( strlen( $v ) > 32 ) { $v = substr( $v, -32 ); }
		return str_pad( $v, 32, "\x00", STR_PAD_LEFT );
	}

	/** base64url — không dấu `=`, `+` thành `-`, `/` thành `_`. Xem chỗ hỏng số 3. */
	public static function b64u( $s ) {
		return rtrim( strtr( base64_encode( (string) $s ), '+/', '-_' ), '=' );
	}

	public static function b64u_giai( $s ) {
		$s = strtr( (string) $s, '-_', '+/' );
		$du = strlen( $s ) % 4;
		if ( $du ) { $s .= str_repeat( '=', 4 - $du ); }
		$r = base64_decode( $s, true );
		return ( false === $r ) ? '' : $r;
	}
}
