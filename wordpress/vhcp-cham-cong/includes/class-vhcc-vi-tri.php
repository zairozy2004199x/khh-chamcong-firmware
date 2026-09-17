<?php
/**
 * GÁC VỊ TRÍ THEO CƠ SỞ — chấm công ở xa cơ sở thì nói ra, và nếu cơ sở muốn thì chặn.
 *
 * =================================================================================================
 * TRƯỚC BẢN NÀY: toạ độ CHỈ LÀ MỘT DÒNG CHỮ, KHÔNG AI ĐỌC.
 * =================================================================================================
 * `VHCC_Online::gps_thanh_chu()` dán "GPS 10.775500,106.702100 ±35m" vào ghi chú của lượt chấm,
 * rồi thôi. Muốn biết người đó có đứng ở cửa hàng thật không thì phải tự chép cặp số ra Google
 * Maps, cho từng lượt, của từng người, của cả tháng — nên trên thực tế KHÔNG AI LÀM. Toạ độ được
 * ghi đầy đủ suốt nhiều tháng mà chưa từng ngăn được một lượt chấm hộ nào.
 *
 * Bộ này để máy làm việc đó: khai một lần toạ độ + bán kính cho mỗi cơ sở, rồi mỗi lượt chấm tự
 * so và tự nói.
 *
 * =================================================================================================
 * 🔴 NĂM LUẬT, BỎ CÁI NÀO CŨNG THÀNH CHẶN OAN NGƯỜI ĐI LÀM THẬT
 * =================================================================================================
 * 1. SAI SỐ PHẢI CỘNG VỀ PHÍA NHÂN VIÊN. Điện thoại báo "cách 60m" kèm "±80m" nghĩa là người ấy
 *    có thể đang đứng ngay trong cửa hàng. So thẳng 60 > 50 rồi kết luận "ở ngoài" là vu cho
 *    người đứng đúng chỗ. Nên chỉ kết luận NGOÀI khi `mét − sai_số > bán_kính` — tức là kể cả
 *    khi máy đo sai theo hướng có lợi nhất thì vẫn ở ngoài.
 *
 * 2. SAI SỐ QUÁ THÔ THÌ KHÔNG KẾT LUẬN GÌ. Trên ≥ `VHCC_Online::GPS_THO` (2km) trình duyệt đang
 *    đoán theo địa chỉ mạng chứ không phải vệ tinh — đúng lý do màn trạm CỐ Ý không vẽ bản đồ ở
 *    mức ấy. Lấy con số đó ra gác thì cả một toà nhà bị báo "ở ngoài" vì nhà mạng định tuyến qua
 *    tổng đài quận khác.
 *
 * 3. KHÔNG CÓ TOẠ ĐỘ THÌ KHÔNG BAO GIỜ CHẶN. Tài liệu phát cho cơ sở nói thẳng: *"Vị trí nên bật
 *    nhưng KHÔNG bắt buộc. Trong nhà hay dưới hầm thường không bắt được"*. Chặn khi thiếu toạ độ
 *    là khoá cửa đúng những cơ sở nằm trong trung tâm thương mại — và họ không có cách nào tự
 *    gỡ, vì lỗi nằm ở bê tông chứ không ở người.
 *
 * 4. CƠ SỞ CHƯA KHAI MỐC THÌ KHÔNG GÁC. Mặc định là TẮT cho mọi cơ sở. Bật dần từng nơi, sau khi
 *    đã đứng tại chỗ bấm "Lấy toạ độ chỗ tôi đang đứng" — chứ không phải bật cả chuỗi bằng một
 *    ô tích rồi sáng hôm sau cả công ty không chấm công được.
 *
 * 5. BÁN KÍNH CÓ SÀN. Khai 5m thì mọi lượt đều "ở ngoài", kể cả người đứng cạnh máy tính tiền:
 *    GPS trong nhà hiếm khi tốt hơn 20–30m. Sàn `BK_TOI_THIEU` chặn cái bẫy đó ngay lúc khai,
 *    chứ không để phát hiện ra bằng một ngày cả cửa hàng chấm công hỏng.
 *
 * ⚠️ CHẶN LÀ CHẶN LƯỢT CHẤM, KHÔNG PHẢI TRỪ CÔNG. Lượt bị chặn thì KHÔNG có hàng nào được ghi —
 *    người ta chấm lại được ngay khi đứng đúng chỗ. Đây cố ý không phải "ghi vào rồi đánh dấu
 *    ngờ": ghi vào là công đã lên bảng, và gỡ ra thì phải có người đi gỡ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_ViTri {

	/** Khoá trong bảng `cai_dat`. Giá trị là JSON: { "<cơ sở>": {lat,lng,bk,cheDo,nguoi,luc} }. */
	const O = 'VI_TRI_CO_SO';

	/** Quyền khai mốc. Đây là cấu hình ảnh hưởng cả một cơ sở, đúng bậc của `ngoai_coso`. */
	const QUYEN = 'ngoai_coso';

	const TAT  = 'tat';   // không gác gì — mặc định
	const GHI  = 'ghi';   // vẫn cho chấm, nhưng dán kết luận vào ghi chú của lượt
	const CHAN = 'chan';  // ở ngoài CHẮC CHẮN thì chối lượt chấm

	const TEN_CHE_DO = array(
		self::TAT  => 'Tắt — không so vị trí',
		self::GHI  => 'Chỉ ghi chú (khuyên dùng khi mới bật)',
		self::CHAN => 'Chặn lượt chấm ở ngoài vùng',
	);

	/** Mét. Dưới ngưỡng này thì GPS trong nhà không bao giờ với tới — xem luật 5. */
	const BK_TOI_THIEU = 30;
	/** Mét. Trên ngưỡng này thì "vùng" rộng hơn cả một phường, gác cũng như không. */
	const BK_TOI_DA    = 5000;
	const BK_MAC_DINH  = 150;

	/* ============================================================================ đọc */

	/** Toàn bộ mốc đã khai. Trả về mảng cơ sở => mốc; chưa khai gì thì mảng rỗng. */
	public static function ds() {
		$d = VHCC_Luong::cai_dat( self::O, array() );
		if ( ! is_array( $d ) ) { return array(); }
		$ra = array();
		foreach ( $d as $coso => $m ) {
			$m = self::chuan( $m );
			if ( null !== $m ) { $ra[ (string) $coso ] = $m; }
		}
		return $ra;
	}

	/**
	 * Mốc của MỘT cơ sở, hoặc null.
	 *
	 * ⚠️ So tên cơ sở KHÔNG PHÂN BIỆT HOA THƯỜNG. Tên cơ sở trong hệ này do người gõ tay ở nhiều
	 *    màn khác nhau ("POSH_HCM" / "Posh_HCM"), và một mốc không khớp được vì chữ hoa thì nó
	 *    im lặng thành "chưa khai" — tức là gác đã bật mà không gác gì, đúng kiểu hỏng tệ nhất.
	 */
	public static function mot( $coso ) {
		$c = trim( (string) $coso );
		if ( '' === $c ) { return null; }
		$ds = self::ds();
		if ( isset( $ds[ $c ] ) ) { return $ds[ $c ]; }
		foreach ( $ds as $ten => $m ) {
			if ( 0 === strcasecmp( (string) $ten, $c ) ) { return $m; }
		}
		return null;
	}

	/** Một mốc thô (từ JSON hay từ biểu mẫu) -> mốc đúng khuôn, hoặc null nếu không dùng được. */
	private static function chuan( $m ) {
		if ( ! is_array( $m ) ) { return null; }
		$lat = isset( $m['lat'] ) && is_numeric( $m['lat'] ) ? (float) $m['lat'] : null;
		$lng = isset( $m['lng'] ) && is_numeric( $m['lng'] ) ? (float) $m['lng'] : null;
		if ( null === $lat || null === $lng ) { return null; }
		if ( ! self::toa_do_hop_le( $lat, $lng ) ) { return null; }
		$bk = isset( $m['bk'] ) && is_numeric( $m['bk'] ) ? (int) round( (float) $m['bk'] ) : self::BK_MAC_DINH;
		$bk = max( self::BK_TOI_THIEU, min( self::BK_TOI_DA, $bk ) );
		$cd = isset( $m['cheDo'] ) ? (string) $m['cheDo'] : self::TAT;
		if ( ! isset( self::TEN_CHE_DO[ $cd ] ) ) { $cd = self::TAT; }
		return array(
			'lat'   => $lat,
			'lng'   => $lng,
			'bk'    => $bk,
			'cheDo' => $cd,
			'nguoi' => isset( $m['nguoi'] ) ? (string) $m['nguoi'] : '',
			'luc'   => isset( $m['luc'] ) ? (string) $m['luc'] : '',
		);
	}

	/**
	 * Toạ độ có nằm trên Trái Đất không — và có phải số 0 lạc không.
	 *
	 * 🔴 (0,0) LÀ CÁI BẪY. Ô gõ để trống, JavaScript gửi lên chuỗi rỗng, PHP ép về `0` — và (0,0)
	 *    là một điểm CÓ THẬT ngoài khơi vịnh Guinea. Mốc ấy lọt qua mọi phép kiểm biên rồi biến
	 *    mọi lượt chấm thành "cách cơ sở 11.000km". Chối thẳng cặp 0 là rẻ hơn nhiều so với đi
	 *    tìm xem vì sao cả cửa hàng bị chặn.
	 */
	public static function toa_do_hop_le( $lat, $lng ) {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) { return false; }
		$lat = (float) $lat;
		$lng = (float) $lng;
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) { return false; }
		if ( abs( $lat ) < 0.0001 && abs( $lng ) < 0.0001 ) { return false; }
		return true;
	}

	/**
	 * ĐỌC TOẠ ĐỘ TỪ MỘT Ô GÕ DUY NHẤT — cặp số, hoặc nguyên cái link Google Maps dán vào.
	 *
	 * 🔴 VÌ SAO KHÔNG PHẢI HAI Ô "VĨ ĐỘ" / "KINH ĐỘ". Màn quản trị này CỐ Ý không có một dòng
	 *    script nào (xem chú thích khối Xoá ở `VHCC_Web::the_man_coso`), nên không có nút "lấy
	 *    toạ độ chỗ tôi đang đứng" nào chạy được ở đây. Đường lấy toạ độ có thật của người dùng
	 *    là: mở Google Maps trên điện thoại, nhấn giữ vào cửa hàng, chép cái link. Bắt họ tự tách
	 *    link ấy ra hai con số rồi gõ vào hai ô là thêm đúng một bước để gõ nhầm — và gõ nhầm một
	 *    chữ số ở đây thì cả cơ sở bị chặn chấm công.
	 *
	 * ⚠️ THỨ TỰ LUÔN LÀ (VĨ ĐỘ, KINH ĐỘ). Cả hai dạng link của Google đều vậy. Đảo lại thì ở Việt
	 *    Nam (vĩ ~10–23, kinh ~102–110) toạ độ rơi xuống Ấn Độ Dương — nên có phép kiểm biên bắt
	 *    được, nhưng chỉ khi kinh độ > 90; cặp (10.7, 106.7) đảo thành (106.7, 10.7) thì vĩ độ
	 *    106 vượt biên và bị chối. Đó là lý do phép kiểm biên ở đây đáng giá hơn vẻ ngoài của nó.
	 *
	 * @return array|null array('lat'=>float,'lng'=>float) hoặc null nếu không đọc ra.
	 */
	public static function doc_toa_do( $s ) {
		$s = trim( (string) $s );
		if ( '' === $s ) { return null; }

		$thu = array();

		/* Link Google Maps dạng .../@10.7755,106.7021,17z — chấm giữa bản đồ. */
		if ( preg_match( '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $s, $m ) ) {
			$thu[] = array( $m[1], $m[2] );
		}
		/* Dạng ...!3d10.7755!4d106.7021 — CHÍNH XÁC HƠN `@`: đây là điểm được ghim, còn `@` chỉ
		   là chỗ khung nhìn đang đặt. Nên xếp trước khi chọn. */
		if ( preg_match( '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $s, $m ) ) {
			array_unshift( $thu, array( $m[1], $m[2] ) );
		}
		/* Dạng ?q=10.7755,106.7021 hoặc ?ll= / &destination= */
		if ( preg_match( '/[?&](?:q|ll|destination|daddr)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $s, $m ) ) {
			array_unshift( $thu, array( $m[1], $m[2] ) );
		}
		/* Cặp số gõ thẳng. Để CUỐI: một cái link cũng chứa đầy số (mức phóng, id), và bắt bừa
		   cặp đầu tiên trong link là lấy nhầm mức phóng làm kinh độ. */
		if ( ! $thu && preg_match( '/^\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*$/', $s, $m ) ) {
			$thu[] = array( $m[1], $m[2] );
		}

		foreach ( $thu as $c ) {
			if ( self::toa_do_hop_le( $c[0], $c[1] ) ) {
				return array( 'lat' => (float) $c[0], 'lng' => (float) $c[1] );
			}
		}
		return null;
	}

	/* ========================================================================== đo đạc */

	/**
	 * Khoảng cách hai điểm, tính bằng MÉT (haversine).
	 *
	 * ⚠️ KHÔNG dùng phép trừ toạ độ rồi nhân hằng số. Ở vĩ độ Việt Nam một độ kinh tuyến ngắn hơn
	 *    một độ vĩ tuyến ~4%, và sai lệch ấy đúng bằng cỡ bán kính đang gác — tức là nó đủ để
	 *    người đứng phía đông cửa hàng bị chặn còn người đứng phía bắc thì không.
	 */
	public static function khoang_cach( $lat1, $lng1, $lat2, $lng2 ) {
		$r   = 6371000.0;
		$p1  = deg2rad( (float) $lat1 );
		$p2  = deg2rad( (float) $lat2 );
		$dp  = deg2rad( (float) $lat2 - (float) $lat1 );
		$dl  = deg2rad( (float) $lng2 - (float) $lng1 );
		$a   = sin( $dp / 2 ) * sin( $dp / 2 )
			+ cos( $p1 ) * cos( $p2 ) * sin( $dl / 2 ) * sin( $dl / 2 );
		$a   = max( 0.0, min( 1.0, $a ) );
		return (int) round( 2 * $r * atan2( sqrt( $a ), sqrt( 1 - $a ) ) );
	}

	/**
	 * XÉT MỘT LƯỢT CHẤM. Trả về một mảng mô tả, KHÔNG tự quyết định gì — nơi gọi quyết.
	 *
	 * @param string     $coso tên cơ sở lượt chấm sẽ ghi vào (đã qua `chuan_coso`).
	 * @param array|null $gps  {lat,lng,acc} như trạm gửi lên. Thiếu / hỏng đều chấp nhận được.
	 *
	 * @return array {
	 *   bool        gac    có mốc và mốc đang bật (`ghi` hoặc `chan`) hay không
	 *   string      cheDo  tat | ghi | chan
	 *   string      ket    'trong' | 'ngoai' | 'khong_ro' | 'khong_gac'
	 *   int|null    met    khoảng cách đo được, null khi không đo được
	 *   int|null    bk     bán kính đang gác
	 *   bool        chan   nơi gọi PHẢI chối lượt này
	 *   string      chu    một câu tiếng Việt để dán vào ghi chú / hiện cho người bấm
	 * }
	 */
	public static function xet( $coso, $gps ) {
		$khong = array( 'gac' => false, 'cheDo' => self::TAT, 'ket' => 'khong_gac',
			'met' => null, 'bk' => null, 'chan' => false, 'chu' => '' );

		$m = self::mot( $coso );
		if ( ! $m || self::TAT === $m['cheDo'] ) { return $khong; }

		$cd  = $m['cheDo'];
		$bk  = (int) $m['bk'];
		$ra  = array( 'gac' => true, 'cheDo' => $cd, 'ket' => 'khong_ro',
			'met' => null, 'bk' => $bk, 'chan' => false, 'chu' => '' );

		/* Luật 3: thiếu toạ độ thì nói ra, nhưng KHÔNG chặn. */
		$lat = ( is_array( $gps ) && isset( $gps['lat'] ) && is_numeric( $gps['lat'] ) ) ? (float) $gps['lat'] : null;
		$lng = ( is_array( $gps ) && isset( $gps['lng'] ) && is_numeric( $gps['lng'] ) ) ? (float) $gps['lng'] : null;
		if ( null === $lat || null === $lng || ! self::toa_do_hop_le( $lat, $lng ) ) {
			$ra['chu'] = 'VỊ TRÍ: máy không gửi được toạ độ — không đối chiếu được với cơ sở.';
			return $ra;
		}

		$acc = ( is_array( $gps ) && isset( $gps['acc'] ) && is_numeric( $gps['acc'] ) )
			? max( 0.0, (float) $gps['acc'] ) : null;

		/* Luật 2: sai số thô hơn ngưỡng của bản đồ thì con số không nói được gì về chỗ đứng. */
		if ( null !== $acc && $acc >= VHCC_Online::GPS_THO ) {
			$ra['chu'] = 'VỊ TRÍ: sai số ' . self::met( (int) round( $acc ) )
				. ' — chỉ là ước lượng theo mạng, không đối chiếu được với cơ sở.';
			return $ra;
		}

		$met = self::khoang_cach( $lat, $lng, $m['lat'], $m['lng'] );
		$ra['met'] = $met;

		/* Luật 1: sai số cộng về phía nhân viên ở CẢ HAI chiều kết luận. */
		$gan  = $met + ( null !== $acc ? (int) round( $acc ) : 0 );   // xa nhất có thể
		$xa   = $met - ( null !== $acc ? (int) round( $acc ) : 0 );   // gần nhất có thể

		if ( $gan <= $bk ) {
			$ra['ket'] = 'trong';
			$ra['chu'] = 'VỊ TRÍ: trong vùng cơ sở (' . self::met( $met ) . ' / bán kính '
				. self::met( $bk ) . ').';
			return $ra;
		}

		if ( $xa > $bk ) {
			$ra['ket']  = 'ngoai';
			$ra['chan'] = ( self::CHAN === $cd );
			$ra['chu']  = 'VỊ TRÍ: NGOÀI vùng cơ sở — cách ' . self::met( $met )
				. ', bán kính cho phép ' . self::met( $bk )
				. ( null !== $acc ? ' (sai số ' . self::met( (int) round( $acc ) ) . ')' : '' ) . '.';
			return $ra;
		}

		/* Nằm đúng trong khoảng mà sai số còn che được — không kết luận, và KHÔNG chặn. */
		$ra['ket'] = 'khong_ro';
		$ra['chu'] = 'VỊ TRÍ: cách ' . self::met( $met ) . ', sai số '
			. self::met( null !== $acc ? (int) round( $acc ) : 0 ) . ' — chưa đủ chắc để nói '
			. 'trong hay ngoài vùng (bán kính ' . self::met( $bk ) . ').';
		return $ra;
	}

	/** "80m" / "1,2km" — cùng cách đọc với `VHCC_Online::do_dai`, để hai nơi không nói hai kiểu. */
	public static function met( $m ) {
		$m = (int) round( (float) $m );
		if ( $m < 1000 ) { return $m . 'm'; }
		return ( $m < 10000 ? number_format( $m / 1000, 1 ) : (string) (int) round( $m / 1000 ) ) . 'km';
	}

	/** Câu chối khi `chan` — nói rõ ai sửa được, vì người bị chối không tự sửa được mốc. */
	public static function loi_chan( $coso, $xet ) {
		return 'Cơ sở "' . $coso . '" đang bật gác vị trí, mà chỗ anh/chị đứng cách cơ sở '
			. self::met( isset( $xet['met'] ) ? $xet['met'] : 0 ) . ' — quá bán kính '
			. self::met( isset( $xet['bk'] ) ? $xet['bk'] : 0 ) . ' nên lượt chấm này KHÔNG được ghi. '
			. 'Đứng tại cơ sở rồi chấm lại. Nếu anh/chị ĐANG ở đúng cơ sở thì báo quản lý: mốc toạ '
			. 'độ của cơ sở khai sai, sửa ở Quản trị → Cài đặt → Vị trí cơ sở.';
	}

	/* ============================================================================ ghi */

	/**
	 * Khai / sửa mốc của một cơ sở.
	 *
	 * ⚠️ KHÔNG nhận `cheDo` = chan khi chưa có toạ độ. Bật chặn trên một cơ sở chưa có mốc thì
	 *    `xet()` trả 'khong_gac' và chẳng chặn gì — người khai tưởng đã khoá, mà cửa vẫn mở.
	 *    Chối ngay tại đây thì họ biết còn thiếu bước lấy toạ độ.
	 */
	public static function luu( $u, $coso, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Khai vị trí cơ sở' ) );
		}
		$c = VHCC_NhanSu::chuan_coso( (string) $coso );
		if ( '' === $c ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $c ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở "' . $c . '".' );
		}

		$lat = isset( $dat['lat'] ) ? $dat['lat'] : '';
		$lng = isset( $dat['lng'] ) ? $dat['lng'] : '';
		$cd  = isset( $dat['cheDo'] ) ? (string) $dat['cheDo'] : self::TAT;
		if ( ! isset( self::TEN_CHE_DO[ $cd ] ) ) {
			return array( 'ok' => false, 'error' => 'Chế độ không hợp lệ.' );
		}

		/* Một ô gõ chung (cặp số hoặc link Google Maps) thắng cặp lat/lng rời — màn quản trị chỉ
		   gửi ô chung, cặp rời để dành cho nơi gọi bằng mã. */
		$go = isset( $dat['toaDo'] ) ? trim( (string) $dat['toaDo'] ) : '';
		if ( '' !== $go ) {
			$d = self::doc_toa_do( $go );
			if ( null === $d ) {
				return array( 'ok' => false, 'error' => 'Không đọc ra toạ độ từ "'
					. mb_substr( $go, 0, 60 ) . '". Dán cặp số dạng <code>10.775500, 106.702100</code> '
					. 'hoặc nguyên cái link Google Maps (mở Maps trên điện thoại, nhấn giữ vào cửa '
					. 'hàng, Chia sẻ → Sao chép liên kết).' );
			}
			$lat = $d['lat'];
			$lng = $d['lng'];
		}

		$co_toa_do = ( '' !== trim( (string) $lat ) && '' !== trim( (string) $lng ) );
		if ( ! $co_toa_do ) {
			if ( self::TAT !== $cd ) {
				return array( 'ok' => false, 'error' => 'Chưa có toạ độ thì chưa bật gác được. '
					. 'Đứng tại cơ sở, bấm "Lấy toạ độ chỗ tôi đang đứng", rồi mới chọn chế độ.' );
			}
			return self::ghi_o( $c, null, $u );   // xoá mốc
		}
		if ( ! self::toa_do_hop_le( $lat, $lng ) ) {
			return array( 'ok' => false, 'error' => 'Toạ độ không hợp lệ. Vĩ độ −90…90, kinh độ '
				. '−180…180, và không nhận cặp 0,0.' );
		}

		$bk_vao = isset( $dat['bk'] ) ? trim( (string) $dat['bk'] ) : '';
		$bk     = ( '' === $bk_vao ) ? self::BK_MAC_DINH : (int) round( (float) $bk_vao );
		if ( $bk < self::BK_TOI_THIEU ) {
			return array( 'ok' => false, 'error' => 'Bán kính nhỏ nhất là ' . self::BK_TOI_THIEU
				. 'm. GPS trong nhà hiếm khi chính xác hơn thế, đặt nhỏ hơn là chặn nhầm cả người '
				. 'đứng ngay trong cửa hàng.' );
		}
		if ( $bk > self::BK_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Bán kính lớn nhất là ' . self::BK_TOI_DA
				. 'm — rộng hơn thế thì vùng phủ cả phường, gác cũng như không.' );
		}

		return self::ghi_o( $c, array(
			'lat'   => (float) $lat,
			'lng'   => (float) $lng,
			'bk'    => $bk,
			'cheDo' => $cd,
			'nguoi' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'luc'   => current_time( 'mysql' ),
		), $u );
	}

	/** Xoá mốc của một cơ sở — về đúng trạng thái "chưa khai", không phải "khai rồi mà tắt". */
	public static function xoa( $u, $coso ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Xoá vị trí cơ sở' ) );
		}
		$c = VHCC_NhanSu::chuan_coso( (string) $coso );
		if ( '' === $c ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $c ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở "' . $c . '".' );
		}
		return self::ghi_o( $c, null, $u );
	}

	/**
	 * Ghi một ô vào bản đồ mốc.
	 *
	 * ⚠️ ĐỌC–SỬA–GHI CẢ BẢN ĐỒ, nên phải xoá đúng khoá cũ kể cả khi nó khác chữ hoa. Không xoá
	 *    thì "POSH_HCM" và "Posh_HCM" cùng nằm trong JSON, `mot()` bắt được cái nào trước là hên
	 *    xui — và người vừa sửa bán kính thấy nó không đổi gì.
	 */
	private static function ghi_o( $coso, $moc, $u ) {
		$d = VHCC_Luong::cai_dat( self::O, array() );
		if ( ! is_array( $d ) ) { $d = array(); }
		foreach ( array_keys( $d ) as $ten ) {
			if ( 0 === strcasecmp( (string) $ten, $coso ) ) { unset( $d[ $ten ] ); }
		}
		if ( null !== $moc ) { $d[ $coso ] = $moc; }
		VHCC_Luong::dat_cai_dat( self::O, $d, $u );
		return array( 'ok' => true, 'coSo' => $coso, 'moc' => $moc );
	}
}
