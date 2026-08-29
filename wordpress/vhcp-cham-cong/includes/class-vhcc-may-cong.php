<?php
/**
 * CỔNG DỊCH VỤ CHO MÁY — phần thay thế Firebase.
 *
 * =============================================================================================
 * VÌ SAO CÓ TỆP NÀY
 * =============================================================================================
 * Trước 22/08/2026 máy chấm công nói chuyện với BA nơi:
 *      chấm công  -> Apps Script `/exec`   (rồi từ 1.2.0 thêm WordPress `/cham-cong-may`)
 *      lệnh + OTA -> Firebase RTDB         (`/queue`, `/ota`, `/status`, `/roster`, `/photo`)
 *      cơ sở      -> Firebase `/may/<khoá>` hoặc Apps Script `whoami`
 * Anh Thắng chốt: chạy HẾT trên host, kể cả đường máy. Nên nay chỉ còn MỘT nơi — chính website
 * này — và tệp này là toàn bộ những gì Firebase từng làm.
 *
 * Ba cái được:
 *   · một nguồn thật. Trước đây "máy này thuộc cơ sở nào" có tới ba nơi trả lời khác nhau được;
 *   · không còn khoá Firebase nằm trong firmware (khoá đó có quyền admin — ai có nó ĐẨY ĐƯỢC
 *     FIRMWARE TUỲ Ý vào cả chuỗi);
 *   · mất mạng Google thì hệ thống vẫn chạy.
 *
 * Cái mất, nói thẳng: website này thành ĐIỂM CHẾT DUY NHẤT. Host sập là máy không nhận lệnh,
 * không OTA được, và chấm công phải nằm chờ trong hàng đợi của firmware. Đổi lại thì trước đây
 * Apps Script sập cũng đã mất chấm công rồi, nên đây không phải một điểm chết MỚI.
 *
 * =============================================================================================
 * ĐI CHUNG MỘT ĐƯỜNG VỚI CHẤM CÔNG — CÓ CHỦ Ý
 * =============================================================================================
 * Mọi việc ở đây vào cùng `/cham-cong-may` bằng POST, cùng khoá `VHCC_KHOA_MAY`, phân biệt bằng
 * trường `viec`. Không mở đường mới vì:
 *   · đường đó đã được chống-chuyển-hướng (xem luật 2 trong class-vhcc-nhan.php). Mở đường thứ
 *     hai là phải nhớ chống lần nữa, mà quên thì im lặng hỏng;
 *   · firmware đã có sẵn URL + khoá cho đường này; thêm đường là thêm một thứ phải nạp xuống máy;
 *   · tường lửa của hosting (Imunify360) chỉ phải mở đúng MỘT chỗ.
 *
 * =============================================================================================
 * KHOÁ MÁY LÀ SERIAL, KHÔNG PHẢI TÊN MÁY TỰ KHAI
 * =============================================================================================
 * Firebase khoá hàng đợi theo `STATION_NAME` — tên gõ tay ở portal 192.168.4.1. Hai máy đặt trùng
 * tên là ăn chung hàng đợi: lệnh thêm nhân viên của cửa hàng này chạy sang máy cửa hàng kia, và
 * không có gì báo. Ở đây khoá là SERIAL đầu đọc (không có serial mới lấy MAC) — máy không tự bịa
 * ra được. Tên tự khai chỉ để hiện cho người đọc.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_MayCong {

	/** Trạng thái một lệnh trong hàng đợi. '' và 'cho' cùng nghĩa là CHƯA gửi (hàng cũ để trống). */
	const CHO  = 'cho';
	const GUI  = 'da-gui';
	const XONG = 'xong';
	/**
	 * 🔴 CHỜ ADMIN DUYỆT — TRƯỚC cả `CHO`, không phải một dạng của `CHO`.
	 * Anh Thắng 29/08/2026: *"trước khi đẩy xuống máy, nó sẽ gửi qua admin duyệt 1 lệnh để check
	 * đạt yêu cầu chưa trước khi đẩy"*. `lay_lenh()`/`so_lenh_cho()` chỉ lọc `trang_thai IN ('',
	 * CHO, GUI)` — một lệnh mang `CHO_DUYET` KHÔNG khớp điều kiện đó nên máy không thấy nó, không
	 * cần sửa gì ở hai hàm đó. Admin duyệt xong mới đổi sang `CHO` để máy nhận được ở nhịp sau.
	 * `TU_CHOI` là trạng thái CHẾT — không quay lại `CHO_DUYET`, muốn thử lại thì đặt lệnh mới.
	 */
	const CHO_DUYET = 'cho-duyet';
	const TU_CHOI   = 'tu-choi';

	/** Quá bao lâu không có nhịp thì coi là máy đứt (giây). Firmware đẩy nhịp mỗi 60s. */
	const HET_SONG = 300;

	/** Việc nào là việc của máy — có mặt trong này thì `VHCC_Nhan` giao xuống đây. */
	public static function viec_may() {
		return array( 'nhip', 'lenh', 'xong', 'anh_lenh', 'roster', 'anh_tra', 'toi_la_ai', 'dung' );
	}

	/**
	 * KHOÁ MÁY chuẩn. Một chỗ duy nhất định nghĩa, vì cả cổng máy lẫn màn web đều phải ra cùng
	 * một chuỗi — lệch nhau là lệnh đặt một nơi mà máy hỏi một nẻo, hàng đợi im lặng rỗng mãi.
	 */
	public static function khoa( $serial, $mac ) {
		$s = strtolower( trim( (string) $serial ) );
		if ( '' !== $s ) { return $s; }
		return strtolower( trim( (string) $mac ) );
	}

	/** Khoá máy của một dòng bảng `may`. */
	public static function khoa_hang( $m ) {
		return self::khoa( isset( $m['serial'] ) ? $m['serial'] : '', isset( $m['mac'] ) ? $m['mac'] : '' );
	}

	// ===========================================================================================
	// CỬA: nhận một gói đã qua kiểm khoá, trả mảng để `VHCC_Nhan` đóng gói gửi đi.
	// ===========================================================================================

	public static function phuc_vu( $viec, $d ) {
		$viec = strtolower( trim( (string) $viec ) );
		switch ( $viec ) {
			case 'nhip':      return self::nhip( $d );
			case 'lenh':      return self::lay_lenh( $d );
			case 'xong':      return self::bao_xong( $d );
			case 'anh_lenh':  return self::anh_cua_lenh( $d );
			case 'roster':    return self::nhan_roster( $d );
			case 'anh_tra':   return self::nhan_anh_trich( $d );
			case 'toi_la_ai': return self::toi_la_ai( $d );
			case 'dung':      return self::hoi_dung( $d );
		}
		/* Việc lạ: KHÔNG trả lỗi máy chủ. Firmware cũ hơn máy chủ (hoặc ngược lại) là chuyện
		   thường trong lúc OTA cả chuỗi — bắt nó đẩy lại vô hạn thì chỉ tốn 4G. */
		return array( 'boQua' => true, 'note' => 'Viec "' . $viec . '" khong co o may chu nay.' );
	}

	// ===========================================================================================
	// 1. NHỊP SỐNG — thay `/status`, và gánh luôn ba câu hỏi khác
	// ===========================================================================================

	/**
	 * Một lượt nhịp trả lời LUÔN bốn câu:
	 *      máy này thuộc cơ sở nào     (trước: Firebase `/may/<khoá>` hoặc `whoami`)
	 *      có firmware mới không       (trước: Firebase `/ota`)
	 *      có lệnh đang chờ không      (trước: đọc `/queue` mỗi 10 giây)
	 *      có ai bảo dừng tải lại không (trước: Firebase `/stop/<tên>`)
	 *
	 * Gộp lại vì đường 4G tính tiền theo lượt gọi và mỗi lượt AT-HTTP mất ~3-6 giây. Bốn lượt
	 * mỗi phút × 26 máy là 4G nghẽn; một lượt thì không. `coLenh` chỉ là CỜ — lệnh thật vẫn phải
	 * hỏi bằng việc `lenh`, để gói nhịp còn nhỏ hơn ngưỡng đọc ~1KB của module 4G.
	 */
	public static function nhip( $d ) {
		global $wpdb;
		$serial = isset( $d['hikSerial'] ) ? trim( (string) $d['hikSerial'] ) : '';
		$mac    = isset( $d['macAddress'] ) ? trim( (string) $d['macAddress'] ) : '';
		$khai   = isset( $d['stationName'] ) ? trim( (string) $d['stationName'] ) : '';
		$model  = isset( $d['hikModel'] ) ? trim( (string) $d['hikModel'] ) : '';
		$tram   = self::khoa( $serial, $mac );
		if ( '' === $tram ) {
			return array( 'boQua' => true, 'note' => 'Goi nhip khong co hikSerial lan macAddress.' );
		}

		/* Dùng chính bộ giải mã của đường chấm công: máy mới tự hiện ra trong bảng `may`, và
		   luật "phần cứng đổi thì chỉ ghi dấu, không tự sửa cơ sở" giữ nguyên — không có bản
		   thứ hai để lệch. */
		$gm   = VHCC_Nhan::giai_ma_tram( $serial, $mac, $khai, $model );
		$coso = $gm['choGan'] ? '' : $gm['station'];

		$bang = VHCC_DB::t( 'may_nhip' );
		$hang = array(
			'tram'     => $tram,
			'ten_tram' => $khai,
			'serial'   => $serial,
			'mac'      => $mac,
			'cua_hang' => $coso,
			'fw'       => isset( $d['fw'] ) ? substr( trim( (string) $d['fw'] ), 0, 40 ) : '',
			'duong'    => isset( $d['duong'] ) ? substr( trim( (string) $d['duong'] ), 0, 20 ) : '',
			'ip'       => isset( $d['ip'] ) ? substr( trim( (string) $d['ip'] ), 0, 60 ) : '',
			'song'     => isset( $d['song'] ) ? substr( trim( (string) $d['song'] ), 0, 20 ) : '',
			'heap'     => isset( $d['heap'] ) ? (int) $d['heap'] : 0,
			'hik'      => isset( $d['hik'] ) ? substr( trim( (string) $d['hik'] ), 0, 40 ) : '',
			'so_tong'  => isset( $d['soTong'] ) ? (int) $d['soTong'] : -1,
			'luc'      => current_time( 'mysql' ),
		);
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE tram=%s LIMIT 1", $tram ) );
		if ( $co ) {
			$wpdb->update( $bang, $hang, array( 'id' => (int) $co ) );
		} else {
			$wpdb->insert( $bang, $hang );
		}

		$ota = self::ota_cho( $tram, isset( $d['fw'] ) ? (string) $d['fw'] : '' );
		return array(
			'coSo'    => $coso,
			'choGan'  => $gm['choGan'] ? 1 : 0,
			'coLenh'  => self::so_lenh_cho( $tram ) > 0 ? 1 : 0,
			'dung'    => self::co_lenh_dung( $tram ) ? 1 : 0,
			'otaVer'  => isset( $ota['ver'] ) ? $ota['ver'] : '',
			'otaUrl'  => isset( $ota['url'] ) ? $ota['url'] : '',
		);
	}

	/** Máy hỏi "tôi thuộc cơ sở nào" — bản thay `whoami` của Apps Script. */
	public static function toi_la_ai( $d ) {
		$serial = isset( $d['hikSerial'] ) ? trim( (string) $d['hikSerial'] ) : '';
		$mac    = isset( $d['macAddress'] ) ? trim( (string) $d['macAddress'] ) : '';
		$khai   = isset( $d['stationName'] ) ? trim( (string) $d['stationName'] ) : '';
		$model  = isset( $d['hikModel'] ) ? trim( (string) $d['hikModel'] ) : '';
		$gm     = VHCC_Nhan::giai_ma_tram( $serial, $mac, $khai, $model );
		return array(
			'coSo'   => $gm['choGan'] ? '' : $gm['station'],
			'choGan' => $gm['choGan'] ? 1 : 0,
			'khoa'   => self::khoa( $serial, $mac ),
		);
	}

	// ===========================================================================================
	// 2. HÀNG ĐỢI LỆNH — thay `/queue`
	// ===========================================================================================

	public static function so_lenh_cho( $tram ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'queue' )
			. " WHERE tram=%s AND trang_thai IN ('', %s, %s)", $tram, self::CHO, self::GUI ) );
	}

	/**
	 * Lấy MỘT lệnh cũ nhất còn chờ.
	 *
	 * ⚠️ CHỈ MỘT, và cố ý KHÔNG kèm ảnh: module 4G đọc được khoảng 1KB một lượt, quá thì cụt
	 *    giữa chừng và JSON hỏng — mà JSON hỏng thì firmware bỏ qua, lệnh nằm lại ở đầu hàng
	 *    và chặn sạch phía sau. Đây đúng là lỗi đã làm mất cả tối 03/08/2026.
	 *
	 * ⚠️ TRẢ LẠI LỆNH ĐÃ GỬI MÀ CHƯA BÁO XONG. Firebase không có khái niệm "đã gửi": máy đọc
	 *    xong phải tự XOÁ, và xoá hỏng trên 4G là chuyện thường. Ở đây trạng thái nằm trên máy
	 *    chủ nên biết được lệnh nào đã gửi mà chưa ai báo xong — vẫn phải gửi lại, vì "gửi rồi"
	 *    không có nghĩa là "máy nhận được". Firmware có sổ `opDone` riêng nên nhận lại lệnh cũ
	 *    thì nó tự bỏ và báo xong lần nữa; không có chuyện thêm hai lần một người.
	 */
	public static function lay_lenh( $d ) {
		global $wpdb;
		$tram = self::tram_cua( $d );
		if ( '' === $tram ) { return array( 'empty' => true, 'note' => 'Thieu hikSerial/macAddress.' ); }

		$q = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'queue' )
			. " WHERE tram=%s AND trang_thai IN ('', %s, %s) ORDER BY id ASC LIMIT 1",
			$tram, self::CHO, self::GUI ), ARRAY_A );
		if ( ! $q ) { return array( 'empty' => true ); }

		$wpdb->update( VHCC_DB::t( 'queue' ),
			array( 'trang_thai' => self::GUI, 'gui_luc' => current_time( 'mysql' ) ),
			array( 'id' => (int) $q['id'] ) );

		/* Tên trường giữ ĐÚNG như Firebase từng trả, vì `checkEmployeeQueue` bên firmware đọc
		   đúng những tên này. Đổi tên ở đây là phải OTA cả chuỗi mới chạy lại được. */
		return array(
			'opId'       => (string) $q['op_id'],
			'action'     => (string) $q['action'],
			'employeeNo' => (string) $q['ma_nv'],
			'name'       => (string) $q['ho_ten'],
			'pin'        => (string) $q['pin_may'],
			'gender'     => (string) $q['gioi_tinh'],
			'hasPhoto'   => ( (int) $q['co_anh'] === 1 ),
			'date'       => (string) $q['ngay'],
			'time'       => (string) $q['gio'],
			'which'      => (string) $q['ben'],
			'startTime'  => (string) $q['tu_gio'],
			'endTime'    => (string) $q['den_gio'],
			'bfImage'    => ( (int) $q['kem_anh'] === 1 ),
		);
	}

	/** Máy báo đã xử lý xong một lệnh — thay lượt DELETE lên Firebase. */
	public static function bao_xong( $d ) {
		global $wpdb;
		$op = isset( $d['opId'] ) ? trim( (string) $d['opId'] ) : '';
		if ( '' === $op ) { return array( 'boQua' => true, 'note' => 'Thieu opId.' ); }
		$kq = isset( $d['ketQua'] ) ? substr( (string) $d['ketQua'], 0, 2000 ) : '';
		/* KHÔNG xoá hàng: hàng đợi cũng là nhật ký "lệnh này đã xuống máy chưa, lúc nào". Xoá là
		   mất chỗ duy nhất trả lời được câu đó khi có người hỏi vì sao mặt chưa vào máy.
		   Ảnh thì XOÁ (đặt về NULL) — nó nặng, và giữ lại không dùng vào việc gì. */
		$so = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'queue' )
			. ' SET trang_thai=%s, xong_luc=%s, ket_qua=%s, anh_b64=NULL WHERE op_id=%s',
			self::XONG, current_time( 'mysql' ), $kq, $op ) );
		return array( 'daGhi' => (int) $so );
	}

	/** Ảnh khuôn mặt của một lệnh thêm/sửa — lượt gọi RIÊNG, xem ghi chú ở `lay_lenh`. */
	public static function anh_cua_lenh( $d ) {
		global $wpdb;
		$op = isset( $d['opId'] ) ? trim( (string) $d['opId'] ) : '';
		if ( '' === $op ) { return array( 'boQua' => true, 'note' => 'Thieu opId.' ); }
		$anh = $wpdb->get_var( $wpdb->prepare(
			'SELECT anh_b64 FROM ' . VHCC_DB::t( 'queue' ) . ' WHERE op_id=%s LIMIT 1', $op ) );
		return array( 'anh' => (string) $anh );
	}

	/** Cờ "dừng tải lại" — thay `/stop/<tên máy>`. */
	public static function co_lenh_dung( $tram ) {
		return '1' === (string) self::cai_dat( 'DUNG_TAI_LAI:' . $tram, '' );
	}

	public static function hoi_dung( $d ) {
		$tram = self::tram_cua( $d );
		$co   = ( '' !== $tram && self::co_lenh_dung( $tram ) );
		if ( $co ) { self::luu_cai_dat( 'DUNG_TAI_LAI:' . $tram, '' ); }   // đọc một lần là hết
		return array( 'dung' => $co ? 1 : 0 );
	}

	// ===========================================================================================
	// 3. MÁY ĐẨY DỮ LIỆU LÊN — thay `/roster` và `/photoresp`
	// ===========================================================================================

	/**
	 * Máy quét xong đẩy sổ nhân viên trong đầu đọc lên.
	 * `dau=1` là trang đầu -> xoá sổ cũ của máy đó. Không xoá ở trang cuối vì trang cuối có thể
	 * không bao giờ tới (4G rớt giữa chừng) — xoá đầu thì tệ nhất là sổ thiếu, còn xoá cuối mà
	 * rớt thì sổ có cả người đã xoá lẫn người mới, tức sai mà nhìn như đúng.
	 */
	public static function nhan_roster( $d ) {
		global $wpdb;
		$tram = self::tram_cua( $d );
		if ( '' === $tram ) { return array( 'boQua' => true, 'note' => 'Thieu hikSerial/macAddress.' ); }
		$bang = VHCC_DB::t( 'may_roster' );
		if ( ! empty( $d['dau'] ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $bang WHERE tram=%s", $tram ) );
		}
		$ds  = isset( $d['ds'] ) && is_array( $d['ds'] ) ? $d['ds'] : array();
		$luc = current_time( 'mysql' );
		$so  = 0;
		foreach ( $ds as $x ) {
			$ma = isset( $x['ma'] ) ? trim( (string) $x['ma'] ) : '';
			if ( '' === $ma ) { continue; }
			/* Tra rồi ghi, KHÔNG dùng INSERT..ON DUPLICATE — y như `VHCC_Lich`: câu đó là cú
			   pháp riêng của MySQL nên phép thử không chạy nổi nó, mà chỗ này thì phải thử được. */
			$hang = array( 'ho_ten' => isset( $x['ten'] ) ? (string) $x['ten'] : '',
				'co_anh' => ! empty( $x['anh'] ) ? 1 : 0, 'cap_nhat' => $luc );
			$id = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $bang WHERE tram=%s AND ma_nv=%s LIMIT 1", $tram, $ma ) );
			if ( $id ) {
				$wpdb->update( $bang, $hang, array( 'id' => (int) $id ) );
			} else {
				$hang['tram'] = $tram; $hang['ma_nv'] = $ma;
				$wpdb->insert( $bang, $hang );
			}
			$so++;
		}
		return array( 'nhan' => $so );
	}

	/** Ảnh máy trích theo yêu cầu (lệnh `getphoto`) — thay `/photoresp`. */
	public static function nhan_anh_trich( $d ) {
		global $wpdb;
		$anh = isset( $d['anh'] ) ? (string) $d['anh'] : '';
		if ( '' === $anh ) { return array( 'boQua' => true, 'note' => 'Goi khong co anh.' ); }
		$wpdb->insert( VHCC_DB::t( 'anh_trich' ), array(
			'tram'  => self::tram_cua( $d ),
			'op_id' => isset( $d['opId'] ) ? (string) $d['opId'] : '',
			'ma_nv' => isset( $d['employeeNo'] ) ? (string) $d['employeeNo'] : '',
			'ngay'  => isset( $d['date'] ) ? (string) $d['date'] : '',
			'gio'   => isset( $d['time'] ) ? (string) $d['time'] : '',
			'ben'   => isset( $d['which'] ) ? (string) $d['which'] : '',
			'anh'   => $anh,
			'luc'   => current_time( 'mysql' ),
		) );
		return array( 'daGhi' => 1 );
	}

	// ===========================================================================================
	// 4. OTA — thay `/ota`
	// ===========================================================================================

	/**
	 * Bản firmware đang đặt cho một máy.
	 *
	 * Đặt ĐÍCH DANH một máy (`OTA_VER:<khoá>`) thì máy đó theo bản riêng; không có mới lấy bản
	 * chung (`OTA_VER`). Có đích danh là để THỬ MỘT MÁY TRƯỚC — đẩy thẳng cho cả 26 máy một bản
	 * chưa ai chạy thử là nếu bản đó hỏng thì mất luôn đường sửa từ xa của cả chuỗi.
	 *
	 * `$fw` là bản máy đang chạy: bằng bản đích thì trả rỗng, khỏi để firmware tự so.
	 */
	public static function ota_cho( $tram, $fw = '' ) {
		$ver = (string) self::cai_dat( 'OTA_VER:' . $tram, '' );
		$url = (string) self::cai_dat( 'OTA_URL:' . $tram, '' );
		if ( '' === $ver || '' === $url ) {
			$ver = (string) self::cai_dat( 'OTA_VER', '' );
			$url = (string) self::cai_dat( 'OTA_URL', '' );
		}
		if ( '' === $ver || '' === $url ) { return array( 'ver' => '', 'url' => '' ); }
		if ( '' !== $fw && trim( $fw ) === trim( $ver ) ) { return array( 'ver' => '', 'url' => '' ); }
		return array( 'ver' => $ver, 'url' => $url );
	}

	// ===========================================================================================
	// Phụ
	// ===========================================================================================

	private static function tram_cua( $d ) {
		return self::khoa(
			isset( $d['hikSerial'] ) ? $d['hikSerial'] : '',
			isset( $d['macAddress'] ) ? $d['macAddress'] : '' );
	}

	public static function cai_dat( $khoa, $mac_dinh = '' ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT gia_tri FROM ' . VHCC_DB::t( 'cai_dat' ) . ' WHERE khoa=%s LIMIT 1', $khoa ) );
		return null === $v ? $mac_dinh : $v;
	}

	public static function luu_cai_dat( $khoa, $gia_tri, $nguoi = '' ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'cai_dat' );
		$co = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE khoa=%s LIMIT 1", $khoa ) );
		$hang = array( 'gia_tri' => (string) $gia_tri, 'cap_nhat' => current_time( 'mysql' ),
			'nguoi_sua' => (string) $nguoi );
		if ( $co ) { $wpdb->update( $bang, $hang, array( 'id' => (int) $co ) ); }
		else { $hang['khoa'] = $khoa; $wpdb->insert( $bang, $hang ); }
		return true;
	}
}
