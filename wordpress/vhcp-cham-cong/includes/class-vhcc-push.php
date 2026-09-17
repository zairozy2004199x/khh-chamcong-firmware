<?php
/**
 * THÔNG BÁO ĐẨY CHO TRẠM — nhắc nhân viên chấm công, báo yêu cầu đã duyệt.
 *
 * =============================================================================================
 * 🔴 ĐẨY TÍN HIỆU RỖNG, KHÔNG ĐẨY NỘI DUNG. ĐÂY LÀ QUYẾT ĐỊNH TRUNG TÂM CỦA TỆP NÀY
 * =============================================================================================
 * Chuẩn Web Push cho phép gửi kèm nội dung, nhưng nội dung ấy phải mã hoá theo `aes128gcm`:
 * bắt tay ECDH trên P-256, kéo khoá bằng HKDF, rồi AES-GCM. Ba thứ đó trong PHP cần
 * `openssl_pkey_derive()` — mà hàm ấy CHỈ CÓ TỪ PHP 7.3, trong khi plugin này khai `Requires
 * PHP: 7.2`. Nâng mức tối thiểu vì một tính năng phụ là bắt cả hệ thống phải đi theo.
 *
 * Nên ở đây máy chủ chỉ đẩy một TIẾNG GÕ CỬA rỗng. Worker nhận được thì tự gọi
 * `?viec=push_hop` để lấy nội dung. Đổi lại được ba thứ, và cả ba đều đáng:
 *
 *   · Không cần thư viện ngoài, không composer, chạy được trên PHP 7.2.
 *   · NỘI DUNG KHÔNG BAO GIỜ NẰM TRÊN MÁY CHỦ CỦA APPLE/GOOGLE. Tin nhắn ở đây có tên người
 *     và giờ chấm công — thứ không có lý do gì phải đi qua bên thứ ba, dù có mã hoá.
 *   · Nội dung LUÔN MỚI lúc hiện ra. Đẩy kèm nội dung thì tin nằm chờ trong hàng đợi của
 *     Apple có thể tới sau vài phút, lúc ấy "bạn chưa chấm công" có khi đã sai.
 *
 * Giá phải trả: mất mạng đúng lúc nhận thì worker không lấy được nội dung. Lúc đó nó hiện một
 * câu chung chung — vẫn hơn im lặng, vì im lặng là iOS thu hồi quyền (xem dưới).
 *
 * =============================================================================================
 * 🔴 BA RÀNG BUỘC CỦA iOS. THIẾU MỘT LÀ KHÔNG CÓ THÔNG BÁO NÀO, VÀ KHÔNG BÁO LỖI
 * =============================================================================================
 * 1. PHẢI ĐÃ THÊM VÀO MÀN HÌNH CHÍNH. Trên iPhone, `Notification.requestPermission()` gọi
 *    trong tab Safari trả về `denied` ngay lập tức, không hiện hộp thoại nào. Chỉ chế độ
 *    standalone mới xin được. Đây là lý do nút bật thông báo phải tự ẩn khi chưa cài — bày
 *    một cái nút bấm vào không có gì xảy ra là cách nhanh nhất để người dùng hết tin.
 * 2. PHẢI LÀ iOS 16.4 TRỞ LÊN. Dưới mức đó `PushManager` không tồn tại.
 * 3. PHẢI XIN QUYỀN TỪ MỘT CÚ CHẠM. Gọi lúc trang vừa mở là bị chặn im lặng.
 *
 * ⚠️ VÀ: MỖI TIẾNG GÕ CỬA PHẢI HIỆN ĐÚNG MỘT THÔNG BÁO. Nhận push mà không gọi
 *    `showNotification()` thì iOS/Chrome tự hiện một thông báo "trang này chạy nền" mấy lần
 *    đầu, rồi THU HỒI QUYỀN. Nên khối `push` trong worker không có nhánh nào thoát ra mà
 *    không hiện gì — kể cả khi lấy nội dung thất bại.
 *
 * =============================================================================================
 * PHÂN BIỆT HAI THỨ HAY BỊ LẪN
 * =============================================================================================
 * `khoa_hop` là mật khẩu của HỘP TIN, không phải thẻ phiên. Worker không đọc được
 * `localStorage` (nơi trạm cất thẻ), nên nó cần một chìa riêng cất ở IndexedDB. Chìa này chỉ
 * mở được đúng một việc: đọc tin chờ của chính thuê bao ấy. Lộ ra thì đọc được thông báo của
 * một người — không chấm công được, không xem được bảng lương.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Push {

	/** Tin chờ quá hạn này thì bỏ — nhắc chấm công của hôm qua hiện ra hôm nay là gây nhiễu. */
	const TIN_SONG_PHUT = 180;

	/** Đẩy hỏng liên tiếp bấy nhiêu lần thì xoá thuê bao. Máy đã gỡ app hoặc đổi máy. */
	const HONG_TOI_DA = 3;

	public static function init() {
		add_action( 'vhcc_push_nhac', array( __CLASS__, 'chay_nhac' ) );
		if ( ! wp_next_scheduled( 'vhcc_push_nhac' ) ) {
			/* 5 phút một lượt: nhắc chấm công trễ 15 phút mà quét 15 phút một lần thì có người
			   nhận lúc trễ 29 phút. Lượt quét chỉ là một câu đếm trên bảng chấm công. */
			wp_schedule_event( time() + 300, 'vhcc_5phut', 'vhcc_push_nhac' );
		}
		add_filter( 'cron_schedules', array( __CLASS__, 'nhip' ) );
	}

	public static function nhip( $ds ) {
		$ds['vhcc_5phut'] = array( 'interval' => 300, 'display' => 'Mỗi 5 phút (chấm công)' );
		return $ds;
	}

	// ==================================================================== khoá VAPID

	/**
	 * Cặp khoá của máy chủ. Sinh MỘT LẦN rồi giữ mãi.
	 *
	 * ⚠️ ĐỔI KHOÁ LÀ MẤT TOÀN BỘ THUÊ BAO. Trình duyệt gắn thuê bao với khoá công khai lúc
	 *    đăng ký; khoá mới thì mọi lượt đẩy trả về 403 và ai cũng phải bật lại thông báo. Nên
	 *    hàm này chỉ sinh khi CHƯA CÓ, không có đường tự động xoay khoá.
	 */
	public static function khoa() {
		$k = get_option( 'vhcc_vapid' );
		if ( is_array( $k ) && ! empty( $k['pub'] ) && ! empty( $k['pem'] ) ) { return $k; }

		$r = openssl_pkey_new( array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		) );
		if ( ! $r ) { return null; }

		openssl_pkey_export( $r, $pem );
		$d = openssl_pkey_get_details( $r );
		if ( empty( $d['ec']['x'] ) || empty( $d['ec']['y'] ) ) { return null; }

		/* Dạng "uncompressed point": 0x04 ‖ X(32) ‖ Y(32). Nhồi 0 bên TRÁI — openssl cắt byte
		   0 dẫn đầu, mà thiếu một byte là trình duyệt từ chối cả thuê bao. */
		$pub = "\x04"
			. str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT )
			. str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );

		$k = array( 'pub' => self::b64u( $pub ), 'pem' => $pem );
		update_option( 'vhcc_vapid', $k, false );   // không autoload: chỉ dùng khi đẩy
		return $k;
	}

	public static function khoa_cong_khai() {
		$k = self::khoa();
		return $k ? $k['pub'] : '';
	}

	private static function b64u( $s ) {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
	}

	/**
	 * Chữ ký ECDSA của openssl ra dạng DER `SEQUENCE{INTEGER r, INTEGER s}`; JOSE cần `r‖s`
	 * trần, mỗi số đúng 32 byte.
	 *
	 * ⚠️ HAI CÁI BẪY, CẢ HAI ĐỀU CHỈ HIỆN RA Ở MỘT SỐ LƯỢT KÝ:
	 *    · DER thêm một byte `0x00` trước số có bit cao bằng 1 (để nó không bị đọc là số âm).
	 *      Không `ltrim` là ra 33 byte.
	 *    · Ngược lại, r hoặc s có thể ngắn hơn 32 byte khi byte đầu tình cờ bằng 0 — khoảng
	 *      1/256 số lượt. Không `str_pad` là ra 63 byte, và lỗi này chỉ thỉnh thoảng mới xảy
	 *      ra nên rất dễ tưởng là lỗi mạng.
	 */
	private static function der_sang_raw( $der ) {
		if ( strlen( $der ) < 8 || "\x30" !== $der[0] ) { return null; }

		$off = 2;
		if ( ord( $der[1] ) > 0x80 ) { $off += ord( $der[1] ) - 0x80; }   // độ dài dạng dài

		$doc = function ( $der, &$off ) {
			if ( ! isset( $der[ $off ] ) || "\x02" !== $der[ $off ] ) { return null; }
			$off++;
			$len = ord( $der[ $off++ ] );
			$v   = substr( $der, $off, $len );
			$off += $len;
			$v = ltrim( $v, "\0" );
			if ( strlen( $v ) > 32 ) { return null; }
			return str_pad( $v, 32, "\0", STR_PAD_LEFT );
		};

		$r = $doc( $der, $off );
		$s = $doc( $der, $off );
		return ( null === $r || null === $s ) ? null : $r . $s;
	}

	/** Thẻ VAPID cho MỘT máy chủ đẩy. `aud` phải là gốc của endpoint, không phải cả endpoint. */
	private static function jwt( $goc ) {
		$k = self::khoa();
		if ( ! $k ) { return ''; }

		$pk = openssl_pkey_get_private( $k['pem'] );
		if ( ! $pk ) { return ''; }

		$head = self::b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$than = self::b64u( wp_json_encode( array(
			'aud' => $goc,
			'exp' => time() + 12 * HOUR_IN_SECONDS,   // trần của chuẩn là 24h; 12h cho thoáng
			'sub' => 'mailto:' . self::email_lien_he(),
		), JSON_UNESCAPED_SLASHES ) );

		$ky = $head . '.' . $than;
		if ( ! openssl_sign( $ky, $der, $pk, OPENSSL_ALGO_SHA256 ) ) { return ''; }

		$raw = self::der_sang_raw( $der );
		return $raw ? $ky . '.' . self::b64u( $raw ) : '';
	}

	/** Apple đòi `sub` là địa chỉ liên hệ thật; sai định dạng là 400 ở mọi lượt đẩy. */
	private static function email_lien_he() {
		$e = get_option( 'vhcc_push_email' );
		if ( $e && is_email( $e ) ) { return $e; }
		$e = get_option( 'admin_email' );
		return ( $e && is_email( $e ) ) ? $e : 'admin@' . wp_parse_url( home_url(), PHP_URL_HOST );
	}

	// ==================================================================== thuê bao

	/** Trạm gọi sau khi trình duyệt cấp thuê bao. Trả về `khoa_hop` để worker cất ở IndexedDB. */
	public static function dang_ky( $ma_nv, $endpoint, $p256dh = '', $auth = '', $thiet_bi = '' ) {
		global $wpdb;

		$ma_nv    = trim( (string) $ma_nv );
		$endpoint = trim( (string) $endpoint );
		if ( '' === $ma_nv || '' === $endpoint ) {
			return array( 'ok' => false, 'error' => 'Thiếu mã nhân viên hoặc địa chỉ đẩy.' );
		}
		if ( ! preg_match( '#^https://#', $endpoint ) || strlen( $endpoint ) > 500 ) {
			return array( 'ok' => false, 'error' => 'Địa chỉ đẩy không hợp lệ.' );
		}

		$b   = VHCC_DB::t( 'push' );
		/* Khoá theo BĂM của endpoint, không theo endpoint: endpoint của Apple dài 300+ ký tự,
		   mà UNIQUE KEY trên cột dài như vậy vượt giới hạn 191 ký tự của utf8mb4. */
		$bam = hash( 'sha256', $endpoint );
		$cu  = $wpdb->get_row( $wpdb->prepare( "SELECT id, khoa_hop FROM $b WHERE bam = %s", $bam ) );

		if ( $cu ) {
			/* Cùng máy, có thể đổi người (máy dùng chung ở cơ sở). Cập nhật mã NV, giữ khoá
			   hộp — worker đang cầm chìa cũ trong IndexedDB, đổi chìa là nó câm cho tới lần
			   mở trang sau. */
			$wpdb->update( $b, array(
				'ma_nv'    => $ma_nv,
				'p256dh'   => $p256dh,
				'auth'     => $auth,
				'thiet_bi' => substr( $thiet_bi, 0, 190 ),
				'hong_lan' => 0,
				'lan_cuoi' => current_time( 'mysql' ),
			), array( 'id' => $cu->id ) );
			return array( 'ok' => true, 'khoa_hop' => $cu->khoa_hop, 'moi' => false );
		}

		$khoa = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( $b, array(
			'ma_nv'    => $ma_nv,
			'bam'      => $bam,
			'endpoint' => $endpoint,
			'p256dh'   => $p256dh,
			'auth'     => $auth,
			'khoa_hop' => $khoa,
			'thiet_bi' => substr( $thiet_bi, 0, 190 ),
			'tao_luc'  => current_time( 'mysql' ),
			'lan_cuoi' => current_time( 'mysql' ),
		) );

		return array( 'ok' => true, 'khoa_hop' => $khoa, 'moi' => true );
	}

	public static function huy( $endpoint ) {
		global $wpdb;
		$wpdb->delete( VHCC_DB::t( 'push' ), array( 'bam' => hash( 'sha256', (string) $endpoint ) ) );
		return array( 'ok' => true );
	}

	/** Worker gọi sau khi nghe tiếng gõ cửa. Lấy tin chờ rồi xoá luôn. */
	public static function hop( $khoa_hop ) {
		global $wpdb;

		$khoa_hop = (string) $khoa_hop;
		if ( 64 !== strlen( $khoa_hop ) || ! ctype_xdigit( $khoa_hop ) ) {
			return array( 'ok' => false, 'error' => 'Chìa không hợp lệ.' );
		}

		$bp = VHCC_DB::t( 'push' );
		$ma = $wpdb->get_var( $wpdb->prepare( "SELECT ma_nv FROM $bp WHERE khoa_hop = %s", $khoa_hop ) );
		if ( ! $ma ) { return array( 'ok' => false, 'error' => 'Chìa không còn hiệu lực.' ); }

		$bt  = VHCC_DB::t( 'push_tin' );
		$han = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::TIN_SONG_PHUT * 60 );

		$tin = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, tieu_de, than, duong_dan FROM $bt
			 WHERE ma_nv = %s AND tao_luc >= %s ORDER BY id DESC LIMIT 1",
			$ma, $han
		), ARRAY_A );

		/* Dọn cả tin cũ của người này — đọc xong là hết việc của chúng. */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $bt WHERE ma_nv = %s", $ma ) );

		if ( ! $tin ) {
			return array( 'ok' => true, 'tin' => null );
		}
		unset( $tin['id'] );
		return array( 'ok' => true, 'tin' => $tin );
	}

	// ==================================================================== gửi

	/**
	 * Đặt tin vào hộp rồi gõ cửa mọi máy của người ấy.
	 *
	 * ⚠️ ĐẶT TIN TRƯỚC, GÕ CỬA SAU. Ngược lại thì worker tới hộp trước khi tin kịp vào, và nó
	 *    hiện câu chung chung trong khi tin thật đang nằm đó.
	 */
	public static function gui( $ma_nv, $tieu_de, $than = '', $duong_dan = '' ) {
		global $wpdb;

		$ma_nv = trim( (string) $ma_nv );
		if ( '' === $ma_nv || '' === trim( (string) $tieu_de ) ) { return 0; }

		$wpdb->insert( VHCC_DB::t( 'push_tin' ), array(
			'ma_nv'     => $ma_nv,
			'tieu_de'   => substr( (string) $tieu_de, 0, 190 ),
			'than'      => substr( (string) $than, 0, 500 ),
			'duong_dan' => substr( (string) $duong_dan, 0, 190 ),
			'tao_luc'   => current_time( 'mysql' ),
		) );

		$b  = VHCC_DB::t( 'push' );
		$ds = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, endpoint, hong_lan FROM $b WHERE ma_nv = %s", $ma_nv
		) );

		$xong = 0;
		foreach ( $ds as $t ) {
			if ( self::go_cua( $t->endpoint ) ) {
				$xong++;
				$wpdb->update( $b, array( 'hong_lan' => 0, 'lan_cuoi' => current_time( 'mysql' ) ), array( 'id' => $t->id ) );
			} else {
				$lan = (int) $t->hong_lan + 1;
				if ( $lan >= self::HONG_TOI_DA ) {
					$wpdb->delete( $b, array( 'id' => $t->id ) );
				} else {
					$wpdb->update( $b, array( 'hong_lan' => $lan ), array( 'id' => $t->id ) );
				}
			}
		}
		return $xong;
	}

	/** Một lượt POST rỗng tới máy chủ đẩy. Trả true khi nó nhận (201/200/202). */
	private static function go_cua( $endpoint ) {
		$goc = wp_parse_url( $endpoint, PHP_URL_SCHEME ) . '://' . wp_parse_url( $endpoint, PHP_URL_HOST );
		$jwt = self::jwt( $goc );
		if ( '' === $jwt ) { return false; }

		$r = wp_remote_post( $endpoint, array(
			'timeout'     => 8,
			'redirection' => 0,
			'headers'     => array(
				'Authorization'  => 'vapid t=' . $jwt . ', k=' . self::khoa_cong_khai(),
				/* Rỗng: `TTL` vẫn bắt buộc, `Content-Length: 0` để máy chủ đẩy không chờ thân. */
				'TTL'            => (string) ( self::TIN_SONG_PHUT * 60 ),
				'Urgency'        => 'normal',
				'Content-Length' => '0',
			),
			'body'        => '',
		) );

		if ( is_wp_error( $r ) ) { return false; }
		$ma = (int) wp_remote_retrieve_response_code( $r );

		/* 404/410 = thuê bao chết hẳn (gỡ app, xoá khỏi màn hình chính). Đếm như một lượt hỏng
		   để nó bị dọn sau đủ số lần, chứ không giữ mãi một địa chỉ không ai ở. */
		return ( 200 === $ma || 201 === $ma || 202 === $ma );
	}

	// ==================================================================== nhắc tự động

	/**
	 * NHẮC AI QUÊN CHẤM RA. Quét mỗi 5 phút, gửi mỗi người mỗi ngày một lần.
	 *
	 * =========================================================================================
	 * 🔴 CHỈ NHẮC CHẤM RA, KHÔNG NHẮC CHẤM VÀO. ĐÂY KHÔNG PHẢI THIẾU SÓT
	 * =========================================================================================
	 * Nhắc "ca của bạn bắt đầu 15 phút rồi mà chưa chấm" thì phải biết ca bắt đầu lúc mấy giờ.
	 * Trong hệ này KHÔNG CÓ con số đó: `LICH_CA` chỉ là danh sách TÊN tự do ("Sáng", "Chiều",
	 * "Tối") do người xếp lịch tự đặt, không gắn giờ. Riêng khối Văn phòng có `VP_CONG_CFG`
	 * (`ngayTu` 08:30) nhưng đó là cấu hình của MỘT khối, không phải của cả chuỗi.
	 *
	 * Đoán một mốc chung — "cứ 9h ai chưa chấm thì nhắc" — là gửi nhầm cho toàn bộ cơ sở chạy
	 * ca chiều và ca tối. Nhắc nhầm vài lần là người ta tắt thông báo, và lúc ấy mất luôn cả
	 * những tin cần thiết. Thà không nhắc còn hơn nhắc sai.
	 *
	 * Ngược lại, "đã chấm vào mà chưa chấm ra sau N giờ" ĐÚNG với mọi cơ sở và mọi ca, vì nó
	 * đo từ chính giờ vào của người đó chứ không cần biết lịch. Nên đây là thứ nhắc được.
	 *
	 * ⚠️ MUỐN THÊM NHẮC CHẤM VÀO thì phải thêm giờ cho từng ca trước (một cột `bat_dau` trong
	 *    `LICH_CA`), không phải thêm một mốc đoán ở đây.
	 */
	public static function chay_nhac() {
		global $wpdb;

		if ( '1' !== (string) get_option( 'vhcc_push_nhac', '1' ) ) { return; }

		$gio_toi_da = (int) get_option( 'vhcc_push_gio_ra', 10 );   // giờ kể từ lúc vào
		if ( $gio_toi_da < 1 ) { return; }

		$ngay    = current_time( 'Y-m-d' );
		$bay_gio = VHCC_DB::giay( current_time( 'H:i:s' ) );
		if ( null === $bay_gio ) { return; }

		$da = get_option( 'vhcc_push_da_nhac', array() );
		if ( ! is_array( $da ) || ( isset( $da['ngay'] ) ? $da['ngay'] : '' ) !== $ngay ) {
			$da = array( 'ngay' => $ngay, 'ra' => array() );
		}

		$bc = VHCC_DB::t( 'cham_cong' );
		$bp = VHCC_DB::t( 'push' );

		/* Chỉ lấy người CÓ thuê bao — không quét cả bảng chấm công của cả chuỗi mỗi 5 phút.
		   Nối bằng `IN (SELECT …)` chứ không JOIN: một người có thể có 2–3 máy, JOIN thì mỗi
		   lượt chấm ra mấy hàng và câu đếm dưới đây nhắc trùng. */
		$hang = $wpdb->get_results( $wpdb->prepare(
			"SELECT ma_nv, coso, ho_ten, gio_vao_giay
			 FROM $bc
			 WHERE ngay = %s
			   AND gio_vao_giay IS NOT NULL
			   AND gio_ra_giay IS NULL
			   AND ma_nv IN ( SELECT ma_nv FROM $bp )",
			$ngay
		) );
		if ( ! $hang ) { return; }

		$nguong = $gio_toi_da * 3600;

		foreach ( $hang as $h ) {
			$ma = (string) $h->ma_nv;
			if ( '' === $ma || in_array( $ma, $da['ra'], true ) ) { continue; }

			$vao = (int) $h->gio_vao_giay;
			$da_lam = $bay_gio - $vao;
			/* Ca đêm: vào 21:00 (75600), bây giờ 02:00 (7200) -> hiệu âm. Cộng một ngày để ra
			   số giờ thật. Không có nhánh này thì người trực đêm không bao giờ được nhắc. */
			if ( $da_lam < 0 ) { $da_lam += VHCC_DB::NGAY_GIAY; }

			if ( $da_lam < $nguong ) { continue; }

			self::gui(
				$ma,
				'Chưa chấm công ra',
				'Bạn chấm vào lúc ' . substr( VHCC_DB::hhmmss( $vao ), 0, 5 )
					. ' — đã ' . intdiv( $da_lam, 3600 ) . ' giờ. Nhớ chấm ra trước khi về.'
			);
			$da['ra'][] = $ma;
		}

		update_option( 'vhcc_push_da_nhac', $da, false );
	}

	// ==================================================================== giao diện

	/**
	 * Ô "Bật thông báo" trong màn chính của trạm.
	 *
	 * =========================================================================================
	 * 🔴 Ô NÀY MẶC ĐỊNH ẨN, VÀ CHỈ JS MỚI MỞ NÓ RA
	 * =========================================================================================
	 * Máy chủ không biết người ta đang mở bằng gì. Trên iPhone, nếu trang đang chạy trong tab
	 * Safari (chưa thêm vào màn hình chính) thì `Notification.requestPermission()` trả `denied`
	 * NGAY, không hiện hộp thoại nào — người dùng bấm nút và không có gì xảy ra. Đó là cách
	 * nhanh nhất để họ kết luận "app hỏng".
	 *
	 * Nên phép thử phải chạy ở TRÌNH DUYỆT, và nút chỉ hiện khi cả ba điều kiện cùng đúng:
	 *   · có `PushManager` (iOS 16.4 trở lên; dưới mức đó không có đối tượng này)
	 *   · có `serviceWorker`
	 *   · đang ở chế độ standalone HOẶC không phải iOS (Android/desktop xin quyền được từ tab)
	 *
	 * Chưa đủ điều kiện thì thay vì nút, hiện một câu chỉ đường: "Thêm vào màn hình chính rồi
	 * mở lại". Nói thẳng còn hơn một cái nút chết.
	 *
	 * ⚠️ XIN QUYỀN PHẢI TỪ CÚ CHẠM. `requestPermission()` gọi lúc trang vừa mở là bị chặn im
	 *    lặng trên iOS. Nên nó nằm trong `onclick`, không nằm trong khối khởi tạo.
	 */
	public static function giao_dien() {
		$pub = self::khoa_cong_khai();
		if ( '' === $pub ) { return; }   // openssl không sinh được khoá: im, đừng bày nút hỏng
		?>
<div id="oTB" class="an" style="margin:14px 0 0">
	<button id="btTB" class="phu" style="width:100%">Bật thông báo nhắc chấm công</button>
	<p id="ghiTB" class="ct" style="margin:6px 0 0"></p>
</div>
<script>
(function () {
	var KHOA = <?php echo wp_json_encode( $pub ); ?>;
	var TRANG = <?php echo wp_json_encode( trailingslashit( VHCC_Tram::url() ) ); ?>;
	var o = document.getElementById('oTB'), bt = document.getElementById('btTB'), ghi = document.getElementById('ghiTB');
	if (!o) { return; }

	var iOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
	var caiRoi = window.navigator.standalone === true
		|| (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
	var duoc = ('serviceWorker' in navigator) && ('PushManager' in window);

	function hien(t) { ghi.textContent = t; }

	if (!duoc) {
		if (iOS) {
			o.classList.remove('an');
			bt.style.display = 'none';
			hien('Thông báo cần iOS 16.4 trở lên. Máy này chưa hỗ trợ.');
		}
		return;   // Android/desktop cũ: im, không bày gì
	}
	if (iOS && !caiRoi) {
		o.classList.remove('an');
		bt.style.display = 'none';
		hien('Để nhận nhắc chấm công: bấm nút Chia sẻ của Safari → Thêm vào màn hình chính, rồi mở lại từ đó.');
		return;
	}

	o.classList.remove('an');

	if (Notification.permission === 'denied') {
		bt.style.display = 'none';
		hien('Thông báo đang bị chặn. Vào Cài đặt máy → Thông báo → Chấm công để bật lại.');
		return;
	}
	if (Notification.permission === 'granted') { xemDaDangKy(); }

	function xemDaDangKy() {
		navigator.serviceWorker.ready.then(function (r) {
			return r.pushManager.getSubscription();
		}).then(function (s) {
			if (s) { bt.textContent = 'Tắt thông báo'; bt.dataset.bat = '1'; hien('Đang bật.'); }
		}).catch(function () {});
	}

	// base64url của khoá VAPID -> Uint8Array. applicationServerKey không nhận chuỗi.
	function sangByte(b64) {
		var d = (b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
		var raw = atob(d), u = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) { u[i] = raw.charCodeAt(i); }
		return u;
	}

	function catChia(khoa) {
		return new Promise(function (ok) {
			var r = indexedDB.open('vhcc', 1);
			r.onupgradeneeded = function () { r.result.createObjectStore('kv'); };
			r.onerror = function () { ok(false); };
			r.onsuccess = function () {
				try {
					var t = r.result.transaction('kv', 'readwrite');
					t.objectStore('kv').put(khoa, 'khoa_hop');
					t.oncomplete = function () { ok(true); };
					t.onerror = function () { ok(false); };
				} catch (e) { ok(false); }
			};
		});
	}

	// ⚠️ MỌI LỆNH SAU CỔNG ĐĂNG NHẬP PHẢI KÈM THẺ PHIÊN. Trạm cất thẻ ở localStorage dưới
	//    khoá `cc_session` và mọi lời gọi của nó đều gửi `{token: …}`. Thiếu nó thì
	//    `VHCC_Tram::cong()` chối ngay ở `self::nguoi()`, và người dùng chỉ thấy "bật không
	//    thành công" mà không biết vì sao. (Riêng `push_hop` của worker KHÔNG đi đường này —
	//    nó gác bằng khoá hộp, xem class-vhcc-push.php.)
	function theP() {
		// Dùng hàm token() của trang khi có — nó là nguồn sự thật. Khối này chạy trong
		// onclick nên script chính (khai ở dưới trong HTML) đã nạp xong.
		if (typeof token === 'function') { try { return token() || ''; } catch (e) {} }
		try { return localStorage.getItem('cc_session') || ''; } catch (e) { return ''; }
	}

	function goi(viec, than) {
		than = than || {};
		than.token = theP();
		return fetch(TRANG + '?viec=' + viec, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(than)
		}).then(function (r) {
			return r.text().then(function (chu) {
				var j = null;
				try { j = JSON.parse(chu); } catch (e) {}
				if (!j) {
					// Không phải JSON: hosting chặn, luật đường chưa nạp, hoặc lỗi PHP. Mấy
					// chữ đầu thường lộ nguyên nhân — đưa ra thay vì nuốt.
					var dau = String(chu || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 90);
					throw new Error('Máy chủ trả nội dung lạ (mã ' + r.status + ')'
						+ (r.status === 404 ? ' — vào Cài đặt → Đường dẫn tĩnh bấm Lưu' : '')
						+ (dau ? ': ' + dau : ''));
				}
				if (!j.ok) { throw new Error(j.error || 'Máy chủ chối.'); }
				return j;
			});
		});
	}

	bt.addEventListener('click', function () {
		bt.disabled = true;

		if (bt.dataset.bat === '1') {
			navigator.serviceWorker.ready.then(function (r) { return r.pushManager.getSubscription(); })
			.then(function (s) {
				if (!s) { return null; }
				var ep = s.endpoint;
				return s.unsubscribe().then(function () { return goi('push_huy', { endpoint: ep }); });
			})
			.then(function () {
				bt.textContent = 'Bật thông báo nhắc chấm công';
				bt.dataset.bat = ''; hien('Đã tắt.');
			})
			.catch(function (e) { hien((e && e.message) ? e.message : 'Không tắt được, thử lại.'); })
			.then(function () { bt.disabled = false; });
			return;
		}

		hien('Đang xin quyền…');
		// ⚠️ Gọi TRỰC TIẾP trong onclick. Bọc nó vào một then() phía sau lời gọi async khác là
		//    mất "cú chạm", và iOS chặn im lặng.
		Notification.requestPermission().then(function (q) {
			if (q !== 'granted') {
				hien(q === 'denied'
					? 'Bạn đã từ chối. Vào Cài đặt máy → Thông báo → Chấm công để bật lại.'
					: 'Chưa cấp quyền.');
				bt.disabled = false;
				return null;
			}
			return navigator.serviceWorker.ready.then(function (r) {
				return r.pushManager.subscribe({
					userVisibleOnly: true,               // bắt buộc; Chrome từ chối nếu thiếu
					applicationServerKey: sangByte(KHOA)
				});
			});
		}).then(function (s) {
			if (!s) { return; }
			var j = s.toJSON();
			return goi('push_dk', {
				endpoint: s.endpoint,
				p256dh: (j.keys && j.keys.p256dh) ? j.keys.p256dh : '',
				auth: (j.keys && j.keys.auth) ? j.keys.auth : ''
			}).then(function (kq) {
				if (!kq.khoa_hop) { throw new Error('Máy chủ không phát chìa hộp tin.'); }
				return catChia(kq.khoa_hop);
			}).then(function (duocCat) {
				if (!duocCat) { throw new Error('Không cất được chìa vào IndexedDB (chế độ riêng tư?).'); }
				bt.textContent = 'Tắt thông báo';
				bt.dataset.bat = '1';
				hien('Đã bật. Bạn sẽ được nhắc nếu quên chấm ra.');
				bt.disabled = false;
			});
		}).catch(function (e) {
			// ⚠️ NÓI RA LỖI THẬT. Bản đầu in "kiểm tra mạng rồi thử lại" cho MỌI lỗi, và khi
			//    nguyên nhân là thiếu thẻ phiên thì câu ấy dẫn người ta đi sai hướng hoàn
			//    toàn — đổi wifi mấy lần vẫn hỏng. Một câu chung chung ở đây tốn nhiều thời
			//    gian hơn là nó tiết kiệm.
			hien((e && e.message) ? e.message : 'Bật không thành công.');
			bt.disabled = false;
		});
	});
})();
</script>
		<?php
	}
}
