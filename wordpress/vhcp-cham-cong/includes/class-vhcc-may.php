<?php
/**
 * MÁY CHẤM CÔNG + CẬP NHẬT FIRMWARE — nay chạy THẲNG TRÊN HOST.
 *
 * =============================================================================================
 * ĐỔI LỚN, 22/08/2026: KHÔNG CÒN FIREBASE, KHÔNG CÒN APPS SCRIPT
 * =============================================================================================
 * Bản trước của tệp này không có một dòng nào chạm cơ sở dữ liệu: nó gọi 23 hàm Apps Script qua
 * cầu nối, và Apps Script là nơi duy nhất nói chuyện với Firebase. Anh Thắng chốt đổi: cả hệ
 * thống — kể cả đường máy chấm công — chạy thẳng trên host, không dính Google Sheet nữa.
 *
 * Nên nay mọi phép ở đây là câu SQL trên chính MySQL của website. Máy hỏi WordPress, WordPress
 * trả lời; xem class-vhcc-may-cong.php cho phía máy.
 *
 * =============================================================================================
 * CÁI MẤT ĐI, VÀ VÌ SAO KHÔNG TIẾC
 * =============================================================================================
 * · `doi_chieu()` / `soi_lai_mysql()` — đối chiếu sheet `MayChamCong` với bảng `may`. GỠ. Chúng
 *   sinh ra để canh ca "một lượt bấm rơi vào hai cơ sở khác nhau" trong giai đoạn ghi song song.
 *   Nay chỉ còn MỘT nơi trả lời "máy này thuộc cơ sở nào" — bảng `may` — nên không còn hai bên
 *   để lệch. Giữ lại một nút đối chiếu không còn gì để đối chiếu là mời người ta tin nhầm.
 * · `xemKhoiTest` / `donKhoiTest` — dọn khối tháng tên "test" mà gói thử đường truyền tạo ra
 *   TRONG SHEET. GỠ: không còn sheet, và cổng nhận đã chặn gói `TEST4G` từ trước khi ghi.
 * · `getLuongMayTuDong` / `getGiaMayTuDong` / `setGiaMayTuDong` — của giao diện gốc trên Apps
 *   Script, WordPress chưa bao giờ gọi. Không dựng lại ở đây.
 *
 * =============================================================================================
 * 🔴 LUẬT KHÔNG ĐỔI: PHẦN CỨNG ĐỔI THÌ CHỈ GHI DẤU, KHÔNG TỰ SỬA
 * =============================================================================================
 * "Thay bo ESP32" và "mang bo sang cửa hàng khác" nhìn từ máy chủ giống hệt nhau — firmware nhớ
 * serial trong NVS nên bo mang đi vẫn khai serial cũ. Đoán sai là chấm công của cửa hàng mới
 * chảy vào cơ sở cũ: sai người, sai lương, không ai thấy. Luật đó nằm ở `VHCC_Nhan::ghi_nhan_may`
 * và KHÔNG chuyển vào đây — một luật, một chỗ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_May {

	/**
	 * PIN admin. CHỈ còn dùng cho việc NẠP DỮ LIỆU CŨ TỪ SHEET (`VHCC_Keo`) — việc tạm, bỏ được
	 * ngay khi nạp xong. Đường máy không dùng PIN này nữa.
	 */
	public static function pin() {
		return defined( 'VHCC_PIN_ADMIN' ) ? (string) VHCC_PIN_ADMIN : (string) get_option( 'vhcc_pin_admin', '' );
	}

	/** Khoá máy chuẩn — một định nghĩa duy nhất, ở VHCC_MayCong. */
	public static function khoa( $serial, $mac ) { return VHCC_MayCong::khoa( $serial, $mac ); }

	// ======================================================================= danh sách & trạng thái

	/**
	 * Danh sách máy, kèm nhịp sống mới nhất.
	 *
	 * LEFT JOIN chứ không JOIN: máy vừa khai mà chưa gửi nhịp nào vẫn phải hiện ra, nếu không thì
	 * người ta không thấy nó để mà gán cơ sở, và lượt bấm cứ nằm mãi trong bảng "chờ gán".
	 */
	public static function ds_may() {
		$may  = VHCC_DB::t( 'may' );
		$nhip = VHCC_DB::t( 'may_nhip' );
		$ds   = VHCC_DB::rows(
			"SELECT m.*, n.fw, n.duong, n.ip, n.song, n.heap, n.so_tong, n.luc AS nhip_luc,"
			. " n.ten_tram AS nhip_ten FROM $may m"
			. " LEFT JOIN $nhip n ON n.tram = LOWER(CASE WHEN m.serial <> '' THEN m.serial ELSE m.mac END)"
			. ' ORDER BY m.cua_hang ASC, m.id ASC' );
		$gio = current_time( 'timestamp' );
		foreach ( $ds as $i => $x ) {
			$ds[ $i ]['tram'] = self::khoa( $x['serial'], $x['mac'] );
			/* `con_song` chứ không phải `song`: cột `song` của bảng nhịp là ĐỘ MẠNH SÓNG 4G. Đè
			   lên nó là mất số đo duy nhất cho biết vì sao một máy hay rớt. */
			$ds[ $i ]['con_song'] = self::con_song( isset( $x['nhip_luc'] ) ? $x['nhip_luc'] : '', $gio );
			$ds[ $i ]['cho']      = VHCC_MayCong::so_lenh_cho( $ds[ $i ]['tram'] );
		}
		return array( 'ok' => true, 'data' => $ds );
	}

	/**
	 * Máy còn sống không. Tách riêng thành phép thuần để thử được bằng con số — "máy mất tích" là
	 * thứ phải báo đúng: báo nhầm thì người ta chạy tới cửa hàng vô ích, mà bỏ sót thì cả ngày
	 * không ai biết một cơ sở đang không chấm công được.
	 */
	public static function con_song( $luc, $bay_gio = null ) {
		if ( '' === trim( (string) $luc ) ) { return false; }
		$t = strtotime( $luc );
		if ( ! $t ) { return false; }
		if ( null === $bay_gio ) { $bay_gio = current_time( 'timestamp' ); }
		return ( $bay_gio - $t ) <= VHCC_MayCong::HET_SONG;
	}

	public static function may_theo_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'may' ) . ' WHERE id=%d LIMIT 1', (int) $id ), ARRAY_A );
	}

	/** Tra máy rồi trả luôn khoá trạm — dùng ở mọi phép nhận `$id` từ biểu mẫu. */
	private static function tram_theo_id( $id ) {
		$m = self::may_theo_id( $id );
		if ( ! $m ) { return array( null, '' ); }
		return array( $m, self::khoa( $m['serial'], $m['mac'] ) );
	}

	// ======================================================================= gán cơ sở

	/**
	 * GÁN máy vào cơ sở.
	 *
	 * ⚠️ Gán xong thì soi lại bảng `cho_gan`: lượt bấm của máy này đang nằm chờ ở đó, và chúng
	 *    là công của người thật. Không tự chuyển thì người ta phải gõ tay lại từng lượt.
	 */
	public static function gan_may( $id, $coso ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$wpdb->update( VHCC_DB::t( 'may' ), array( 'cua_hang' => $coso ), array( 'id' => (int) $m['id'] ) );
		$chuyen = self::chuyen_cho_gan( $m, $coso );
		return array( 'ok' => true, 'thong_bao' => 'Đã gán máy ' . ( $m['serial'] ? $m['serial'] : $m['mac'] )
			. ' vào ' . $coso . ( $chuyen > 0 ? ' · chuyển ' . $chuyen . ' lượt bấm đang chờ gán vào bảng chấm công.' : '.' ) );
	}

	public static function bo_gan( $id ) {
		global $wpdb;
		list( $m ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$wpdb->update( VHCC_DB::t( 'may' ), array( 'cua_hang' => '' ), array( 'id' => (int) $m['id'] ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã bỏ gán. Lượt bấm từ máy này sẽ nằm ở bảng "chờ gán".' );
	}

	/**
	 * Lượt bấm đã giữ ở `cho_gan` -> đưa vào bảng chấm công của cơ sở vừa gán.
	 *
	 * Đi qua ĐÚNG `VHCC_Nhan::ghi_gio` mà cổng máy dùng — không có bản ghi giờ thứ hai. Nhờ luật
	 * "chỉ nới, không thu hẹp" của hàm đó nên chạy lại bao nhiêu lần cũng ra một kết quả.
	 */
	private static function chuyen_cho_gan( $m, $coso ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'cho_gan' );
		$ds = VHCC_DB::rows( $wpdb->prepare(
			"SELECT * FROM $bang WHERE da_chuyen='' AND ("
			. ' ( serial <> %s AND LOWER(serial)=LOWER(%s) ) OR ( serial=%s AND LOWER(mac)=LOWER(%s) ) )',
			'', (string) $m['serial'], '', (string) $m['mac'] ) );
		$so = 0;
		foreach ( $ds as $r ) {
			$phan = preg_split( '/\s+/', (string) $r['thoi_diem'] );
			$ngay = isset( $phan[0] ) ? $phan[0] : '';
			$giay = VHCC_DB::giay( isset( $phan[1] ) ? $phan[1] : '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || null === $giay ) { continue; }
			$kq = VHCC_Nhan::ghi_gio( $coso, $ngay, $r['ma_nv'], $r['ho_ten'], $giay, '', 'may',
				'chuyển từ hàng chờ gán' );
			if ( isset( $kq['loi'] ) ) { continue; }
			$wpdb->update( $bang, array( 'da_chuyen' => $coso ), array( 'id' => (int) $r['id'] ) );
			$so++;
		}
		return $so;
	}

	public static function luu_sim( $id, $sim ) {
		global $wpdb;
		list( $m ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$wpdb->update( VHCC_DB::t( 'may' ), array( 'sim' => substr( trim( (string) $sim ), 0, 60 ) ),
			array( 'id' => (int) $m['id'] ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã lưu số SIM.' );
	}

	// ======================================================================= hàng đợi lệnh

	/**
	 * Đặt một lệnh cho máy.
	 *
	 * `op_id` sinh ở ĐÂY chứ không để cơ sở dữ liệu tự đánh số, vì firmware giữ một sổ `opDone`
	 * riêng theo chuỗi này để khỏi làm hai lần một lệnh. Số tự tăng thì sau khi dọn bảng sẽ cấp
	 * lại số cũ, và máy sẽ bỏ qua lệnh mới vì tưởng đã làm rồi.
	 */
	public static function dat_lenh( $tram, $action, $them = array(), $nguoi = '' ) {
		global $wpdb;
		$tram = strtolower( trim( (string) $tram ) );
		if ( '' === $tram ) { return array( 'ok' => false, 'error' => 'Thiếu máy nhận lệnh.' ); }
		$action = trim( (string) $action );
		if ( '' === $action ) { return array( 'ok' => false, 'error' => 'Thiếu tên lệnh.' ); }

		$hang = array_merge( array(
			'op_id'      => self::op_id( $action ),
			'action'     => $action,
			'tram'       => $tram,
			'trang_thai' => VHCC_MayCong::CHO,
			'tao_luc'    => current_time( 'mysql' ),
			'nguoi_dat'  => (string) $nguoi,
		), (array) $them );
		$ok = $wpdb->insert( VHCC_DB::t( 'queue' ), $hang );
		if ( false === $ok ) { return array( 'ok' => false, 'error' => 'Không ghi được lệnh vào hàng đợi.' ); }
		return array( 'ok' => true, 'opId' => $hang['op_id'], 'thong_bao' => 'Đã đặt lệnh "' . $action . '". '
			. 'Máy nhận trong khoảng 10 giây nếu đang online.' );
	}

	/** Chuỗi op duy nhất. `uniqid` có phần thời gian nên vẫn xếp theo thứ tự đặt. */
	public static function op_id( $action ) {
		return substr( preg_replace( '/[^a-z0-9]/', '', strtolower( $action ) ), 0, 8 )
			. '-' . uniqid();
	}

	public static function yeu_cau_quet( $id ) {
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		return self::dat_lenh( $tram, 'scan', array( 'cua_hang' => (string) $m['cua_hang'] ) );
	}

	/**
	 * TẢI LẠI sổ chấm công của đầu đọc trong một khoảng.
	 *
	 * ⚠️ Khoảng rộng là máy đẩy lên hàng nghìn lượt qua 4G, mất hàng giờ và nghẽn cả đường trong
	 *    lúc đó. Chặn ở đây chứ không nhắc suông: 31 ngày là đủ cho mọi ca thật (mất mạng một
	 *    đợt, đầu đọc treo một hôm).
	 */
	public static function tai_lai( $id, $tu, $den, $ma_nv = '', $kem_anh = false ) {
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$tu  = trim( (string) $tu );
		$den = trim( (string) $den );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $tu ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $den ) ) {
			return array( 'ok' => false, 'error' => 'Ngày phải theo dạng yyyy-mm-dd.' );
		}
		if ( strtotime( $den ) < strtotime( $tu ) ) {
			return array( 'ok' => false, 'error' => 'Ngày kết thúc sớm hơn ngày bắt đầu.' );
		}
		$songay = (int) floor( ( strtotime( $den ) - strtotime( $tu ) ) / 86400 ) + 1;
		if ( $songay > 31 ) {
			return array( 'ok' => false, 'error' => 'Khoảng ' . $songay . ' ngày là quá rộng. Máy phải đẩy '
				. 'từng lượt bấm qua 4G nên khoảng rộng làm nghẽn cả đường truyền hàng giờ. Chia thành '
				. 'nhiều đợt, mỗi đợt tối đa 31 ngày.' );
		}
		return self::dat_lenh( $tram, 'backfill', array(
			'cua_hang' => (string) $m['cua_hang'],
			'ma_nv'    => trim( (string) $ma_nv ),
			'tu_gio'   => $tu . ' 00:00:00',
			'den_gio'  => $den . ' 23:59:59',
			'kem_anh'  => $kem_anh ? 1 : 0,
		) );
	}

	/** Bảo máy đang tải lại DỪNG. Máy đọc cờ này giữa chừng nên dừng được ngay, không phải chờ hết. */
	public static function dung_tai_lai( $id ) {
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		VHCC_MayCong::luu_cai_dat( 'DUNG_TAI_LAI:' . $tram, '1' );
		return array( 'ok' => true, 'thong_bao' => 'Đã đặt cờ dừng. Máy dừng ở lượt kiểm tra kế tiếp.' );
	}

	/** Hàng đợi của một máy (hoặc cả chuỗi khi `$tram` rỗng). */
	public static function ds_lenh( $tram = '', $gom_xong = false, $gioi_han = 200 ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'queue' );
		$dieu = array();
		$tv   = array();
		if ( '' !== trim( (string) $tram ) ) { $dieu[] = 'tram=%s'; $tv[] = strtolower( trim( $tram ) ); }
		if ( ! $gom_xong ) { $dieu[] = "trang_thai <> '" . VHCC_MayCong::XONG . "'"; }
		$sql = "SELECT id, op_id, action, tram, ten_tram, ma_nv, ho_ten, cua_hang, trang_thai,"
			. " tu_gio, den_gio, ngay, gio, ben, co_anh, tao_luc, gui_luc, xong_luc, ket_qua FROM $bang"
			. ( $dieu ? ' WHERE ' . implode( ' AND ', $dieu ) : '' )
			. ' ORDER BY id DESC LIMIT ' . (int) $gioi_han;
		if ( $tv ) { $sql = $wpdb->prepare( $sql, $tv ); }
		return VHCC_DB::rows( $sql );
	}

	/**
	 * Xoá một lệnh khỏi hàng đợi.
	 *
	 * Chỉ xoá được lệnh CHƯA gửi. Lệnh đã xuống máy thì xoá ở đây không gọi nó về được — máy đã
	 * cầm rồi — mà lại mất dấu vết để đối chiếu khi có người hỏi "vì sao mặt này vào máy".
	 */
	public static function xoa_lenh( $op_id ) {
		global $wpdb;
		$op = trim( (string) $op_id );
		$q  = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'queue' ) . ' WHERE op_id=%s LIMIT 1', $op ), ARRAY_A );
		if ( ! $q ) { return array( 'ok' => false, 'error' => 'Không có lệnh nào mang mã đó.' ); }
		if ( VHCC_MayCong::GUI === $q['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Lệnh này đã xuống máy rồi — xoá ở đây không gọi nó '
				. 'về được. Chờ máy báo xong, hoặc đặt lệnh ngược lại nếu cần huỷ tác dụng.' );
		}
		$wpdb->delete( VHCC_DB::t( 'queue' ), array( 'id' => (int) $q['id'] ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá lệnh chưa gửi.' );
	}

	/** Sổ nhân viên đang nằm trong đầu đọc (kết quả lệnh quét). */
	public static function roster( $id ) {
		global $wpdb;
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$ds = VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'may_roster' ) . ' WHERE tram=%s ORDER BY ma_nv ASC', $tram ) );
		return array( 'ok' => true, 'data' => $ds, 'tram' => $tram );
	}

	/**
	 * ĐỐI CHIẾU MẶT TRONG MÁY VỚI HỒ SƠ TRÊN WEB.
	 *
	 * Đây là phép đáng giá nhất của màn máy, và nó chỉ làm được từ khi hàng đợi về host: người
	 * đã nghỉ việc mà mặt còn trong đầu đọc thì VẪN chấm công được, và bảng lương vẫn tính —
	 * không có gì báo, vì mỗi bên đều thấy mình đúng.
	 */
	public static function doi_chieu_roster( $id ) {
		global $wpdb;
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$coso = (string) $m['cua_hang'];
		$trong_may = array();
		foreach ( VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, ho_ten FROM ' . VHCC_DB::t( 'may_roster' ) . ' WHERE tram=%s', $tram ) ) as $r ) {
			$trong_may[ strtoupper( trim( $r['ma_nv'] ) ) ] = $r['ho_ten'];
		}
		$tren_web = array();
		foreach ( VHCC_DB::rows( $wpdb->prepare(
			'SELECT ma_nv, ho_ten, trang_thai_lam_viec FROM ' . VHCC_DB::t( 'nhan_vien' )
			. ' WHERE LOWER(cua_hang)=LOWER(%s)', $coso ) ) as $r ) {
			$tren_web[ strtoupper( trim( $r['ma_nv'] ) ) ] = $r;
		}
		$thua = array();     // có mặt trong máy mà web không còn -> NGUY: chấm công được mà không nên
		$thieu = array();    // web có mà máy chưa có -> người mới chưa lấy được mặt
		foreach ( $trong_may as $ma => $ten ) {
			if ( ! isset( $tren_web[ $ma ] ) ) { $thua[] = array( 'ma' => $ma, 'ten' => $ten, 'vi_sao' => 'không có hồ sơ ở cơ sở này' ); continue; }
			/* Truyền THÔ cho da_nghi — nó tự hạ chữ bằng mb_strtolower. `strtolower` của PHP
			   không hạ được chữ CÓ DẤU, nên "NGHỈ" viết hoa lọt qua phép so sánh chữ thường. */
			if ( VHCC_NhanSu::da_nghi( $tren_web[ $ma ]['trang_thai_lam_viec'] ) ) {
				$thua[] = array( 'ma' => $ma, 'ten' => $ten, 'vi_sao' => 'hồ sơ ghi ' . $tren_web[ $ma ]['trang_thai_lam_viec'] );
			}
		}
		foreach ( $tren_web as $ma => $r ) {
			if ( ! isset( $trong_may[ $ma ] ) ) { $thieu[] = array( 'ma' => $ma, 'ten' => $r['ho_ten'] ); }
		}
		return array( 'ok' => true, 'tram' => $tram, 'coso' => $coso,
			'soMay' => count( $trong_may ), 'soWeb' => count( $tren_web ),
			'thua' => $thua, 'thieu' => $thieu );
	}

	/** Máy này đang ra sao — thay `chanDoanMay`. Không gọi đi đâu cả, chỉ đọc những gì máy đã báo. */
	public static function chan_doan( $id ) {
		global $wpdb;
		list( $m, $tram ) = self::tram_theo_id( $id );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		$n = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'may_nhip' ) . ' WHERE tram=%s LIMIT 1', $tram ), ARRAY_A );
		return array( 'ok' => true, 'tram' => $tram, 'may' => $m, 'nhip' => $n,
			'song' => self::con_song( $n ? $n['luc'] : '' ),
			'cho'  => VHCC_MayCong::so_lenh_cho( $tram ) );
	}

	// ======================================================================= firmware

	/**
	 * Link .bin có dùng được cho OTA không.
	 *
	 * ⚠️ Module 4G A7680C chết ở khoảng 532 ký tự, mà link release của GitHub trả HTTP 302 rồi
	 *    chuyển hướng dài 943 ký tự. Đẩy một link như vậy = mọi máy 4G KHÔNG BAO GIỜ tải được bản
	 *    mới, tức MẤT LUÔN đường sửa từ xa của cả chuỗi và phải đi từng cửa hàng cắm USB. Sai một
	 *    lần là đi 26 chỗ.
	 *
	 * Từ khi bỏ Firebase, đây là lớp gác DUY NHẤT còn lại cho việc đó — trước còn Apps Script kiểm
	 * quyền admin ở giữa. Nên đừng nới nó.
	 */
	public static function ota_url_hop_le( $url ) {
		$u = trim( (string) $url );
		if ( '' === $u ) { return 'Thiếu link .bin.'; }
		if ( 0 !== strpos( $u, 'https://' ) ) { return 'Link phải bắt đầu bằng https://'; }
		if ( strlen( $u ) > 300 ) {
			return 'Link dài ' . strlen( $u ) . ' ký tự. Module 4G chết ở khoảng 532 ký tự và link '
				. 'chuyển hướng còn dài hơn nữa — dùng link raw ngắn.';
		}
		if ( '.bin' !== substr( $u, -4 ) ) { return 'Link phải kết thúc bằng .bin'; }
		if ( false !== strpos( $u, '/releases/download/' ) ) {
			return 'Đây là link RELEASE của GitHub — nó trả HTTP 302 rồi chuyển hướng dài ~943 ký tự, '
				. 'máy 4G sẽ KHÔNG BAO GIỜ tải được. Dùng link raw của nhánh "bin" '
				. '(raw.githubusercontent.com/…/bin/…), nó trả 200 thẳng.';
		}
		return '';                                            // rỗng = dùng được
	}

	/**
	 * ĐẶT BẢN FIRMWARE. `$id` rỗng = cho CẢ CHUỖI; có `$id` = chỉ máy đó.
	 *
	 * ⚠️ ĐẨY CẢ CHUỖI MỘT BẢN CHƯA AI CHẠY THỬ LÀ CANH BẠC KHÔNG CÓ ĐƯỜNG LÙI: bản hỏng thì mọi
	 *    máy mất luôn đường nhận bản sau, và cách sửa duy nhất là mang USB đi 26 cửa hàng. Nên
	 *    đặt cho MỘT máy trước, xem nó lên đúng bản và còn gửi nhịp, rồi mới đẩy cả chuỗi.
	 *    Máy chấm công vẫn chạy trong lúc chờ — bản này không đụng gì tới việc chấm.
	 */
	public static function dat_ota( $ver, $url, $xac_nhan, $id = 0 ) {
		$ver = trim( (string) $ver );
		$url = trim( (string) $url );
		if ( '' === $ver ) { return array( 'ok' => false, 'error' => 'Thiếu số phiên bản.' ); }
		$loi = self::ota_url_hop_le( $url );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }

		$tram = '';
		if ( (int) $id > 0 ) {
			list( $m, $tram ) = self::tram_theo_id( $id );
			if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
		} elseif ( 'DONG Y' !== trim( (string) $xac_nhan ) ) {
			/* Xác nhận chỉ đòi khi đẩy CẢ CHUỖI. Bắt gõ cho một máy thử là người ta ngại thử, rồi
			   lại đẩy thẳng cả chuỗi — đúng thứ cần tránh. */
			return array( 'ok' => false, 'error' => 'Đây là lệnh nạp firmware cho MỌI máy trong chuỗi. '
				. 'Gõ đúng chữ "DONG Y" vào ô xác nhận nếu thật sự muốn đẩy. '
				. 'Nên thử một máy trước: chọn máy trong danh sách rồi bấm "Đặt riêng cho máy này".' );
		}
		$hau = '' !== $tram ? ':' . $tram : '';
		VHCC_MayCong::luu_cai_dat( 'OTA_VER' . $hau, $ver );
		VHCC_MayCong::luu_cai_dat( 'OTA_URL' . $hau, $url );
		VHCC_MayCong::luu_cai_dat( 'OTA_LUC' . $hau, current_time( 'mysql' ) );
		return array( 'ok' => true, 'thong_bao' => '' !== $tram
			? 'Đã đặt bản ' . $ver . ' RIÊNG cho máy ' . $tram . '. Máy nhận trong 60 giây, nạp xong tự khởi động lại.'
			: 'Đã đặt bản ' . $ver . ' cho CẢ CHUỖI. Máy nhận trong 60 giây.' );
	}

	public static function go_ota( $id = 0 ) {
		$hau = '';
		if ( (int) $id > 0 ) {
			list( $m, $tram ) = self::tram_theo_id( $id );
			if ( ! $m ) { return array( 'ok' => false, 'error' => 'Không có máy nào mang số ' . (int) $id . '.' ); }
			$hau = ':' . $tram;
		}
		VHCC_MayCong::luu_cai_dat( 'OTA_VER' . $hau, '' );
		VHCC_MayCong::luu_cai_dat( 'OTA_URL' . $hau, '' );
		return array( 'ok' => true, 'thong_bao' => 'Đã gỡ lệnh cập nhật' . ( '' !== $hau ? ' của máy này.' : ' của cả chuỗi.' ) );
	}

	public static function ota_dang_dat() {
		return array( 'ok' => true, 'data' => array(
			'ver' => (string) VHCC_MayCong::cai_dat( 'OTA_VER', '' ),
			'url' => (string) VHCC_MayCong::cai_dat( 'OTA_URL', '' ),
			'luc' => (string) VHCC_MayCong::cai_dat( 'OTA_LUC', '' ),
		) );
	}

	/**
	 * Các máy đang chạy bản nào.
	 *
	 * Thay `getFwMoiNhat` — hàm đó hỏi Apps Script xem GitHub có bản nào mới. Bỏ, vì nó bắt máy
	 * chủ gọi ra ngoài mỗi lần mở màn, mà câu trả lời hữu ích hơn thì nằm ngay trong nhà: MÁY
	 * ĐANG CHẠY BẢN NÀO. Đó mới là thứ cho biết lượt OTA vừa rồi tới được bao nhiêu máy.
	 */
	public static function fw_dang_chay() {
		$dem = array();
		foreach ( VHCC_DB::rows( 'SELECT fw, COUNT(*) AS so FROM ' . VHCC_DB::t( 'may_nhip' )
			. ' GROUP BY fw ORDER BY so DESC' ) as $r ) {
			$dem[] = array( 'ver' => '' === trim( (string) $r['fw'] ) ? '(chưa báo)' : $r['fw'],
				'so' => (int) $r['so'] );
		}
		return array( 'ok' => true, 'data' => $dem );
	}
}
