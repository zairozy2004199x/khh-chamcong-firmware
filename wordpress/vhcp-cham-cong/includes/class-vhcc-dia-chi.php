<?php
/**
 * TOẠ ĐỘ -> ĐỊA CHỈ CỤ THỂ — "số 12 Nguyễn Huệ, Bến Nghé, Quận 1" thay cho "10.7755,106.7021".
 *
 * =================================================================================================
 * Anh Thắng 20/09/2026: *"chỗ định vị có chèn được địa chỉ cụ thể nơi đứng không"*.
 * =================================================================================================
 * Được, nhưng một cặp số thành một cái tên đường thì phải đi hỏi máy chủ bản đồ của người khác —
 * và đó là chỗ mọi cách làm hiển nhiên đều hỏng. Bốn luật dưới đây, bỏ cái nào cũng hỏng theo một
 * kiểu riêng, và ba trong bốn kiểu ấy không ai phát hiện ra cho tới lúc muộn.
 *
 * =================================================================================================
 * 🔴 LUẬT 1 — KHÔNG BAO GIỜ TRA ĐỊA CHỈ TRONG LÚC NGƯỜI TA ĐANG CHẤM CÔNG
 * =================================================================================================
 * Cám dỗ lớn nhất là tra ngay lúc ghi lượt chấm: một dòng, ghi thẳng xuống cột, xong. Nhưng như
 * thế là đem một lượt gọi ra internet chèn vào giữa người lao động và nút chấm công của họ. Máy
 * chủ bản đồ chậm 8 giây thì lượt chấm chậm 8 giây; nó sập thì cả chuỗi cửa hàng đứng chờ một
 * dịch vụ MIỄN PHÍ của người khác để được ghi giờ đi làm.
 *
 * Nên: lượt chấm chỉ ghi CẶP SỐ (việc ấy không cần mạng), còn tên đường được điền SAU, ở chỗ khác,
 * và nếu không điền được thì bảng vẫn có cặp số y như trước.
 *
 * =================================================================================================
 * 🔴 LUẬT 2 — NHỚ LẠI, VÀ NHỚ THEO Ô LƯỚI CHỨ KHÔNG THEO TỪNG CẶP SỐ
 * =================================================================================================
 * Chính sách của Nominatim (dịch vụ tra địa chỉ của OpenStreetMap) nói rõ: tối đa MỘT lượt mỗi
 * giây, phải khai danh tính, phải nhớ lại kết quả, và cấm tra hàng loạt. Một chuỗi vài trăm người
 * chấm hai lượt mỗi ngày là vài trăm lượt tra — thừa sức bị chặn cả tên miền.
 *
 * Chốt chặn thật nằm ở phép làm tròn: hai người đứng cạnh nhau trước cùng một cửa hàng KHÔNG cho
 * ra cùng một cặp số (GPS lệch vài mét mỗi lần đo), nên nhớ theo cặp số thì gần như không bao giờ
 * trúng lại. Làm tròn về lưới `LAM_TRON` chữ số thập phân (~11m) thì mọi lượt chấm của cùng một
 * cửa hàng rơi vào CÙNG một ô, và từ ngày thứ hai trở đi gần như không phải hỏi ra ngoài nữa.
 *
 * =================================================================================================
 * 🔴 LUẬT 3 — CHỈ TRA TRONG KHUNG VIỆT NAM
 * =================================================================================================
 * Cùng lý do với `VHCC_BanDo`: một đường mà ai gõ toạ độ nào cũng khiến máy chủ mình đi hỏi hộ là
 * một cổng tra địa chỉ miễn phí cho người lạ, mở bằng tên miền của mình. Toạ độ ngoài khung Việt
 * Nam thì trang chấm công không có việc gì với nó.
 *
 * =================================================================================================
 * ⚠️ LUẬT 4 — TÊN ĐƯỜNG LÀ CHỮ CỦA NGƯỜI NGOÀI, KHÔNG PHẢI CHỮ CỦA MÌNH
 * =================================================================================================
 * Chuỗi trả về do người tình nguyện khắp thế giới gõ vào OpenStreetMap. Nó phải được thoát khi in
 * ra y như mọi dữ liệu ngoài khác, và phải bị CẮT NGẮN trước khi cất — một địa chỉ đầy đủ của
 * Nominatim dài tới vài trăm ký tự và sẽ tràn cột.
 *
 * ⚠️ ĐỊA CHỈ KHÔNG PHẢI BẰNG CHỨNG, CẶP SỐ MỚI LÀ. Tên đường chỉ để người đọc bảng khỏi phải chép
 *    toạ độ sang Google Maps. Nó có thể trỏ vào toà nhà bên cạnh, nhất là ở hẻm — nên chỗ nào kết
 *    luận trong/ngoài vùng thì vẫn tính bằng cặp số, không bao giờ tính bằng cái tên này.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DiaChi {

	/** Số chữ số thập phân của ô lưới nhớ. 4 ≈ 11m — xem luật 2. */
	const LAM_TRON = 4;

	/** Nhớ bao lâu. Tên đường không đổi hằng tháng. */
	const SONG_NGAY = 180;

	/** Cắt trước khi cất. Nominatim trả địa chỉ đầy đủ dài vài trăm ký tự. */
	const DAI_TOI_DA = 180;

	/** Chính sách Nominatim: tối đa 1 lượt/giây. Đây là khoá toàn hệ, không phải mỗi tiến trình. */
	const O_NHIP = 'vhcc_dia_chi_nhip';

	/** Một lượt cron điền tối đa bấy nhiêu ô. 1 giây/ô -> khoảng 20 giây, không nghẽn cron. */
	const MOI_LUOT = 20;

	/**
	 * 🔴 KHAI NHỊP TRƯỚC, XẾP LỊCH SAU. THỨ TỰ HAI DÒNG NÀY LÀ CẢ TÍNH NĂNG.
	 *
	 * `wp_schedule_event()` tra tên nhịp trong danh sách do bộ lọc `cron_schedules` dựng ra. Gọi
	 * nó TRƯỚC khi `add_filter` chạy thì `vhcc_15phut` chưa có trong danh sách, WordPress trả về
	 * một WP_Error rồi thôi — KHÔNG xếp lịch gì cả. Lượt tải trang sau lại y như vậy, nên lịch
	 * không bao giờ được xếp.
	 *
	 * Và nó hỏng IM LẶNG hoàn hảo: không báo lỗi, không dòng nhật ký, plugin chạy bình thường,
	 * chỉ có tên đường là không bao giờ hiện ra. Anh Thắng 20/09/2026: *"Do định vị hay do app.
	 * Chưa lấy được"* — đúng câu hỏi mà một lỗi kiểu này bắt người ta phải hỏi.
	 *
	 * ⚠️ `VHCC_Push::init()` từng mắc y hệt, đã sửa cùng lượt. Ai thêm cron mới cho plugin này
	 *    thì đọc lại chỗ này trước.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'nhip' ) );
		add_action( 'vhcc_dia_chi_dien', array( __CLASS__, 'dien_dan' ) );
		if ( ! wp_next_scheduled( 'vhcc_dia_chi_dien' ) ) {
			/* 15 phút một lượt, mỗi lượt 20 ô. Đủ để địa chỉ của hôm nay hiện ra trong ngày,
			   mà không bao giờ giống một đợt tra hàng loạt. */
			wp_schedule_event( time() + 600, 'vhcc_15phut', 'vhcc_dia_chi_dien' );
		}
	}

	public static function nhip( $ds ) {
		$ds['vhcc_15phut'] = array( 'interval' => 900, 'display' => 'Mỗi 15 phút (tra địa chỉ)' );
		return $ds;
	}

	/* ============================================================================ ô lưới */

	/** Cặp số -> khoá ô lưới, hoặc '' nếu toạ độ không dùng được / ngoài khung Việt Nam. */
	public static function o( $lat, $lng ) {
		if ( ! class_exists( 'VHCC_ViTri' ) || ! VHCC_ViTri::toa_do_hop_le( $lat, $lng ) ) { return ''; }
		$lat = (float) $lat;
		$lng = (float) $lng;
		/* Luật 3 — dùng đúng khung của `VHCC_BanDo` để hai nơi không nói hai kiểu. */
		if ( $lat < VHCC_BanDo::LAT_MIN || $lat > VHCC_BanDo::LAT_MAX
			|| $lng < VHCC_BanDo::LNG_MIN || $lng > VHCC_BanDo::LNG_MAX ) { return ''; }
		return number_format( $lat, self::LAM_TRON, '.', '' ) . ','
			. number_format( $lng, self::LAM_TRON, '.', '' );
	}

	/* ============================================================================== đọc */

	/**
	 * ĐỊA CHỈ ĐÃ NHỚ của một cặp số. KHÔNG BAO GIỜ RA MẠNG.
	 *
	 * 🔴 Đây là hàm mà mọi chỗ VẼ MÀN phải gọi. Một lưới công cả tháng có hàng chục ô; để chỗ vẽ
	 *    màn gọi được hàm có ra mạng là dựng sẵn cái ngày một lần mở bảng bắn sáu mươi lượt gọi
	 *    ra ngoài — đúng định nghĩa "tra hàng loạt" mà chính sách cấm, và trang thì treo.
	 *
	 * @return string địa chỉ, hoặc '' khi chưa tra được.
	 */
	public static function nho( $lat, $lng ) {
		global $wpdb;
		$o = self::o( $lat, $lng );
		if ( '' === $o ) { return ''; }
		$d = $wpdb->get_var( $wpdb->prepare(
			'SELECT dia_chi FROM ' . VHCC_DB::t( 'dia_chi' ) . ' WHERE o=%s', $o ) );
		return null === $d ? '' : (string) $d;
	}

	/** Địa chỉ đã nhớ của một dòng `VHCC_ViTri::dong()`. '' khi chưa có. */
	public static function nho_dong( $s ) {
		if ( ! class_exists( 'VHCC_ViTri' ) ) { return ''; }
		$v = VHCC_ViTri::doc_dong( $s );
		if ( null === $v ) { return ''; }
		return self::nho( $v['lat'], $v['lng'] );
	}

	/* =============================================================================== ghi */

	/**
	 * TRA MỘT Ô — CÓ RA MẠNG. Chỉ gọi từ cron hoặc từ một cú bấm của người, không từ chỗ vẽ màn.
	 *
	 * ⚠️ GHI CẢ KHI KHÔNG TÌM RA. Một ô giữa ruộng thì Nominatim trả rỗng, và không ghi gì thì
	 *    lượt cron sau lại hỏi đúng ô ấy, mãi mãi — vài chục lượt mỗi ngày cho một cặp số không
	 *    bao giờ có tên. Ghi một hàng rỗng là nhớ rằng "đã hỏi rồi, không có".
	 *
	 * @return string địa chỉ tra được ('' khi không có / hỏng).
	 */
	public static function tra( $lat, $lng ) {
		global $wpdb;
		$o = self::o( $lat, $lng );
		if ( '' === $o ) { return ''; }

		$da = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'dia_chi' ) . ' WHERE o=%s', $o ), ARRAY_A );
		if ( $da && '' !== (string) $da['dia_chi'] ) { return (string) $da['dia_chi']; }
		/* Đã hỏi mà không ra, và chưa quá hạn -> đừng hỏi lại. */
		if ( $da && strtotime( (string) $da['tra_luc'] ) > time() - self::SONG_NGAY * DAY_IN_SECONDS ) {
			return '';
		}

		self::cho_nhip();

		list( $la, $ln ) = explode( ',', $o );
		$url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=0'
			. '&accept-language=vi&lat=' . rawurlencode( $la ) . '&lon=' . rawurlencode( $ln );
		$kq = wp_remote_get( $url, array(
			'timeout' => 8,
			/* Khai danh tính đàng hoàng — chính sách của họ đòi thế, và User-Agent trống là lý
			   do phổ biến nhất khiến cả tên miền bị chặn. Giống hệt `VHCC_BanDo`. */
			'headers' => array(
				'User-Agent' => 'VHCC-ChamCong/' . VHCC_VERSION . ' (+' . home_url( '/' ) . ')',
				'Referer'    => home_url( '/' ),
			),
		) );

		/* 🔴 HỎNG MẠNG THÌ KHÔNG GHI GÌ CẢ. Ghi một hàng rỗng ở đây là nhớ nhầm "chỗ này không
		   có tên" cho một ô chỉ đơn giản là hôm ấy mất mạng — và nhớ suốt 180 ngày. */
		if ( is_wp_error( $kq ) || 200 !== (int) wp_remote_retrieve_response_code( $kq ) ) { return ''; }

		$j = json_decode( (string) wp_remote_retrieve_body( $kq ), true );
		$d = ( is_array( $j ) && isset( $j['display_name'] ) ) ? trim( (string) $j['display_name'] ) : '';
		/* Luật 4: cắt trước khi cất. Và bỏ đuôi ", Việt Nam" — mọi hàng đều có, chỉ tốn chỗ. */
		$d = preg_replace( '/,\s*(Việt Nam|Vietnam)\s*$/u', '', $d );
		$d = mb_substr( $d, 0, self::DAI_TOI_DA );

		self::ghi( $o, $d );
		return $d;
	}

	/**
	 * TRA CHO MỘT NGƯỜI ĐANG ĐỨNG CHỜ — đọc sổ nhớ trước, và TUYỆT ĐỐI KHÔNG NGỦ.
	 *
	 * 🔴 VÌ SAO KHÔNG DÙNG THẲNG `tra()` Ở ĐÂY. `tra()` giữ nhịp 1 lượt/giây bằng cách NGỦ, và
	 *    ngủ trong một lượt gọi của trình duyệt là giữ luôn một tiến trình PHP. Tám giờ sáng cả
	 *    chuỗi mở màn chấm công cùng lúc: mỗi máy một tiến trình nằm ngủ chờ tới lượt, và hosting
	 *    hết sạch tiến trình — nghĩa là cả trang web đứng, không riêng gì ô địa chỉ.
	 *
	 * Nên ở đây: nhịp còn trống thì đi hỏi (nhanh, hạn 4 giây); nhịp đang bận thì TRẢ RỖNG NGAY
	 * và để lượt cron điền sau. Người dùng mất tên đường trong vài phút, không ai mất trang web.
	 *
	 * @return string địa chỉ, hoặc '' khi chưa có.
	 */
	public static function tra_nhanh( $lat, $lng ) {
		$d = self::nho( $lat, $lng );
		if ( '' !== $d ) { return $d; }
		if ( '' === self::o( $lat, $lng ) ) { return ''; }
		/* Nhịp đang bận -> nhường, không xếp hàng. */
		if ( microtime( true ) - (float) get_option( self::O_NHIP, 0 ) < 1.0 ) { return ''; }
		return self::tra( $lat, $lng );
	}

	private static function ghi( $o, $dia_chi ) {
		global $wpdb;
		$b = VHCC_DB::t( 'dia_chi' );
		$co = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $b WHERE o=%s", $o ) );
		$d  = array( 'dia_chi' => (string) $dia_chi, 'tra_luc' => current_time( 'mysql' ) );
		if ( $co ) {
			$wpdb->update( $b, $d, array( 'o' => $o ) );
		} else {
			$d['o'] = $o;
			$wpdb->insert( $b, $d );
		}
	}

	/**
	 * Giữ nhịp 1 lượt/giây cho CẢ HỆ, không phải cho mỗi tiến trình.
	 *
	 * ⚠️ Nhịp nằm trong `option` chứ không phải một biến tĩnh: cron và cú bấm của người là hai
	 *    tiến trình PHP khác nhau, và hai tiến trình mỗi cái giữ nhịp riêng thì cộng lại vẫn là
	 *    hai lượt trong một giây — đúng cái ngưỡng đang cố không vượt.
	 */
	private static function cho_nhip() {
		$truoc = (float) get_option( self::O_NHIP, 0 );
		$cho   = 1.0 - ( microtime( true ) - $truoc );
		if ( $cho > 0 && $cho <= 1.0 ) { usleep( (int) round( $cho * 1000000 ) ); }
		update_option( self::O_NHIP, microtime( true ), false );
	}

	/* ========================================================================== chẩn đoán */

	/**
	 * VÌ SAO CHỖ NÀY CHƯA CÓ TÊN ĐƯỜNG — trả lời bằng câu tiếng Việt, không bắt ai đoán.
	 *
	 * Anh Thắng 20/09/2026, kèm ảnh một lượt chấm có GPS ±11m mà ô địa chỉ trống: *"Do định vị
	 * hay do app. Chưa lấy được"*.
	 *
	 * 🔴 CÂU HỎI ẤY LÀ MỘT LỖI CỦA HỆ, KHÔNG PHẢI CỦA NGƯỜI HỎI. Một ô trống có thể là năm
	 *    chuyện hoàn toàn khác nhau — máy chưa bắt được GPS, toạ độ ngoài khung, chưa tra bao
	 *    giờ, đã tra mà chỗ đó không có tên, hay hosting chặn đường ra internet — và bốn trong
	 *    năm chuyện ấy KHÔNG phải lỗi định vị. Bắt người dùng phân biệt bằng mắt là bắt họ làm
	 *    việc của máy. Hàm này đi hỏi thật rồi nói thẳng ra cái nào.
	 *
	 * ⚠️ HÀM NÀY CÓ RA MẠNG (và có thể ngủ tới 1 giây). Chỉ gọi từ trang chẩn đoán — người ta
	 *    mở nó có chủ đích, mỗi lần một lượt. Đừng gọi từ chỗ vẽ màn.
	 *
	 * @return array array('ket' => mã, 'chu' => câu tiếng Việt)
	 */
	public static function chan_doan( $lat, $lng ) {
		$o = self::o( $lat, $lng );
		if ( '' === $o ) {
			return array( 'ket' => 'ngoai_khung', 'chu' => 'Toa do khong dung duoc (rong, sai, '
				. 'hoac ngoai khung Viet Nam) nen KHONG tra. Day khong phai loi mang.' );
		}

		$da = self::nho( $lat, $lng );
		if ( '' !== $da ) {
			return array( 'ket' => 'co', 'chu' => 'DA CO trong so nho: ' . $da );
		}

		/* Chưa có trong sổ. Đi hỏi THẬT để biết đường ra internet có thông không — đây mới là
		   thứ trang chẩn đoán cần trả lời, và là thứ không suy ra được từ cơ sở dữ liệu. */
		self::cho_nhip();
		list( $la, $ln ) = explode( ',', $o );
		$url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=0'
			. '&accept-language=vi&lat=' . rawurlencode( $la ) . '&lon=' . rawurlencode( $ln );
		$kq = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'User-Agent' => 'VHCC-ChamCong/' . VHCC_VERSION . ' (+' . home_url( '/' ) . ')',
				'Referer'    => home_url( '/' ),
			),
		) );

		if ( is_wp_error( $kq ) ) {
			return array( 'ket' => 'khong_ra_duoc_mang', 'chu' => 'MAY CHU KHONG RA DUOC INTERNET '
				. 'toi nominatim.openstreetmap.org: ' . $kq->get_error_message()
				. ' -- Hosting dang chan ket noi ra ngoai. Day KHONG phai loi dinh vi va cung '
				. 'khong phai loi app; nho ben hosting mo cho ten mien nay (giong nhu da mo cho '
				. 'tile.openstreetmap.org de o anh ban do chay duoc).' );
		}
		$ma = (int) wp_remote_retrieve_response_code( $kq );
		if ( 200 !== $ma ) {
			return array( 'ket' => 'bi_choi', 'chu' => 'May chu ban do TRA LOI ' . $ma
				. ' (khong phai 200). 403/429 thuong la bi chan vi goi qua day hoac thieu khai '
				. 'danh tinh. Day khong phai loi dinh vi.' );
		}

		$j = json_decode( (string) wp_remote_retrieve_body( $kq ), true );
		if ( ! is_array( $j ) || ! isset( $j['display_name'] ) || '' === trim( (string) $j['display_name'] ) ) {
			return array( 'ket' => 'cho_nay_khong_co_ten', 'chu' => 'Ra duoc internet, may chu ban '
				. 'do tra loi BINH THUONG nhung CHO NAY KHONG CO TEN DUONG trong ban do (ruong, '
				. 'duong moi mo, hem chua ai ve). Toa do van dung. Khong sua duoc tu phia minh.' );
		}

		return array( 'ket' => 'tra_duoc', 'chu' => 'TRA DUOC ngay bay gio: '
			. mb_substr( trim( (string) $j['display_name'] ), 0, 120 )
			. ' -- Neu o dia chi van trong thi la lich nen chua chay (xem dong "lich dien dan").' );
	}

	/* ============================================================================== cron */

	/**
	 * ĐIỀN DẦN: lấy mấy ô chấm công gần đây chưa có địa chỉ rồi tra, tối đa `MOI_LUOT` ô.
	 *
	 * ⚠️ CHỈ NHÌN LẠI `NGAY_LUI` NGÀY. Bật tính năng này trên một bảng đã có hai năm dữ liệu mà
	 *    quét cả bảng là hàng chục nghìn ô — chạy vài tháng mới xong, và trong lúc ấy thì đúng
	 *    là tra hàng loạt. Địa chỉ của lượt chấm năm ngoái cũng không ai còn cần.
	 *
	 * @return int số ô đã tra xong.
	 */
	const NGAY_LUI = 45;

	public static function dien_dan() {
		global $wpdb;
		$tu = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . self::NGAY_LUI . ' day' ) );
		$cc = VHCC_DB::t( 'cham_cong' );
		$hang = VHCC_DB::rows( $wpdb->prepare(
			"SELECT vt_vao, vt_ra FROM $cc WHERE ngay >= %s AND (vt_vao <> '' OR vt_ra <> '')"
			. ' ORDER BY ngay DESC, id DESC LIMIT 400', $tu ) );

		$can = array();
		foreach ( $hang as $h ) {
			foreach ( array( 'vt_vao', 'vt_ra' ) as $c ) {
				$v = VHCC_ViTri::doc_dong( (string) $h[ $c ] );
				if ( null === $v ) { continue; }
				$o = self::o( $v['lat'], $v['lng'] );
				if ( '' !== $o ) { $can[ $o ] = array( $v['lat'], $v['lng'] ); }
			}
		}
		if ( ! $can ) { return 0; }

		/* Bỏ ra những ô đã hỏi rồi — kể cả ô đã hỏi mà không có tên (xem `tra()`). */
		$khoa = array_keys( $can );
		$cho  = implode( ',', array_fill( 0, count( $khoa ), '%s' ) );
		$da   = $wpdb->get_col( $wpdb->prepare(
			'SELECT o FROM ' . VHCC_DB::t( 'dia_chi' ) . " WHERE o IN ($cho)", $khoa ) );
		foreach ( (array) $da as $o ) { unset( $can[ (string) $o ] ); }

		$so = 0;
		foreach ( $can as $xy ) {
			if ( $so >= self::MOI_LUOT ) { break; }
			self::tra( $xy[0], $xy[1] );
			$so++;
		}
		return $so;
	}
}
