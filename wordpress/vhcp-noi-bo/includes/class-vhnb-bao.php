<?php
/**
 * HỘP THƯ CHUNG — chuông thông báo của trang Nội bộ.
 *
 * Anh Thắng 26/08/2026: *"chỗ thông báo tin nhắn chỗ nào"*, rồi nói rõ thêm:
 * *"Ví dụ như có chấm công, có chi phí nó sẽ hiện lên nội bộ này."*
 *
 * =============================================================================================
 * 🔴 NỘI BỘ LÀ NƠI NHẬN, KHÔNG PHẢI NƠI ĐI BỚI SỔ CỦA PLUGIN KHÁC
 * =============================================================================================
 * Cách sai — và là cách dễ viết hơn — là để trang nội bộ tự đọc bảng chấm công, bảng đơn chi
 * phí rồi dựng thông báo. Làm thế thì nội bộ phải BIẾT sơ đồ bảng của hai plugin kia: đổi một
 * cột bên ấy là hỏng bên này, mà không có gì báo; gỡ một plugin ra là trắng cả trang.
 *
 * Nên đảo chiều: hộp thư mở đúng MỘT CỬA NHẬN — `gui()` — và bên nào có tin thì tự gọi vào,
 * kèm sẵn câu chữ của mình. Nội bộ không cần biết "chấm công" hay "chi phí" là cái gì; nó chỉ
 * giữ và bày ra. Bên gửi gác `class_exists` nên chưa cài nội bộ thì lời gọi im lặng trôi qua,
 * không ai vỡ.
 *
 * =============================================================================================
 * 🔴 GỘP THEO `khoa`, KHÔNG ĐẺ MỖI VIỆC MỘT DÒNG
 * =============================================================================================
 * Một bài được 20 người bình luận mà đẻ 20 dòng thì chuông thành chỗ không ai mở. Mỗi tin mang
 * một `khoa` (VD `bl:12` — bình luận của bài 12); gửi trùng khoá thì CỘNG DỒN vào dòng cũ và
 * đẩy nó lên mới nhất, thành "3 người bình luận bài của bạn".
 *
 * ⚠️ GỬI TRÙNG KHOÁ MÀ DÒNG CŨ ĐÃ ĐỌC RỒI THÌ ĐẶT LẠI CHƯA ĐỌC và đếm lại từ 1. Không thì
 *    người ta đọc xong một lượt là mọi bình luận sau đó của bài ấy im lặng vĩnh viễn.
 *
 * ⚠️ KHÔNG TỰ BÁO CHO CHÍNH MÌNH. Tự bình luận bài mình, tự thả tim bài mình — chuông kêu là
 *    chuông nói lại đúng thứ người ta vừa làm. Chặn ở `gui()`, một chỗ, chứ không bắt mỗi nơi
 *    gọi tự nhớ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Bao {

	/** Giữ bao nhiêu ngày rồi dọn. Hộp thư không phải sổ lưu trữ. */
	const NGAY_GIU = 90;

	/** Trần một lượt đọc — chuông không bao giờ cần 500 dòng. */
	const TOI_DA = 30;

	/** Đếm quá ngần này thì hiện "99+", khỏi phải đọc một con số bốn chữ số. */
	const DEM_TRAN = 99;

	/* ====================================================================== cửa NHẬN */

	/**
	 * NHẬN MỘT TIN. Đây là cửa duy nhất — mọi plugin gọi vào đây.
	 *
	 * @param string $ma_nv     mã NV người NHẬN. Rỗng thì bỏ, vì không biết đưa cho ai.
	 * @param string $nguon     'noi_bo' · 'cham_cong' · 'chi_phi' … — chỉ để gắn nhãn và lọc.
	 * @param string $chu       câu hiện ra, viết sẵn bởi bên gửi (bên nhận không diễn giải lại).
	 * @param string $duong_dan bấm vào thì đi đâu. Rỗng = tin chỉ để đọc.
	 * @param string $khoa      khoá gộp. Rỗng thì tự sinh từ nội dung -> mỗi tin một dòng.
	 * @param string $tu_ma_nv  mã NV người GÂY RA việc — để không tự báo cho chính mình.
	 */
	public static function gui( $ma_nv, $nguon, $chu, $duong_dan = '', $khoa = '', $tu_ma_nv = '' ) {
		global $wpdb;
		$ma_nv = trim( (string) $ma_nv );
		$chu   = trim( (string) $chu );
		if ( '' === $ma_nv || '' === $chu ) { return false; }

		/* Không tự báo cho chính mình — xem khối cảnh báo ở đầu tệp. */
		$tu = trim( (string) $tu_ma_nv );
		if ( '' !== $tu && 0 === strcasecmp( $tu, $ma_nv ) ) { return false; }

		$nguon = trim( (string) $nguon );
		if ( '' === $nguon ) { $nguon = 'khac'; }
		$khoa = trim( (string) $khoa );
		if ( '' === $khoa ) { $khoa = $nguon . ':' . md5( $chu . '|' . $duong_dan ); }

		$t  = VHNB_DB::t( 'bao' );
		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $t WHERE ma_nv=%s AND khoa=%s LIMIT 1", $ma_nv, $khoa ), ARRAY_A );

		if ( $cu ) {
			/* Đã đọc rồi thì đếm LẠI TỪ 1, chưa đọc thì cộng dồn. Cộng dồn xuyên qua lượt đọc
			   là con số nói về những thứ người ta đã xem xong. */
			$da_doc = ! empty( $cu['da_doc'] );
			$wpdb->update( $t, array(
				'chu'       => VHNB_Bai::gon( $chu, 300 ),
				'duong_dan' => (string) $duong_dan,
				'nguon'     => $nguon,
				'so_lan'    => $da_doc ? 1 : ( (int) $cu['so_lan'] + 1 ),
				'da_doc'    => 0,
				'tao_luc'   => current_time( 'mysql' ),
			), array( 'id' => (int) $cu['id'] ) );
			return (int) $cu['id'];
		}

		$ok = $wpdb->insert( $t, array(
			'ma_nv'     => $ma_nv,
			'nguon'     => $nguon,
			'khoa'      => $khoa,
			'chu'       => VHNB_Bai::gon( $chu, 300 ),
			'duong_dan' => (string) $duong_dan,
			'so_lan'    => 1,
			'da_doc'    => 0,
			'tao_luc'   => current_time( 'mysql' ),
		) );
		return ( false === $ok ) ? false : (int) $wpdb->insert_id;
	}

	/* ====================================================================== đọc */

	/** Số tin CHƯA ĐỌC. */
	public static function chua_doc( $ma_nv ) {
		global $wpdb;
		$ma_nv = trim( (string) $ma_nv );
		if ( '' === $ma_nv ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bao' ) . ' WHERE ma_nv=%s AND da_doc=0', $ma_nv ) );
	}

	/** Chuỗi hiện trên chuông: '' · '3' · '99+'. */
	public static function nhan_dem( $ma_nv ) {
		$n = self::chua_doc( $ma_nv );
		if ( $n <= 0 ) { return ''; }
		return ( $n > self::DEM_TRAN ) ? ( self::DEM_TRAN . '+' ) : (string) $n;
	}

	/** Danh sách tin, mới nhất trước. Chưa đọc luôn đứng trên. */
	public static function ds( $ma_nv, $gioi_han = 0 ) {
		global $wpdb;
		$ma_nv = trim( (string) $ma_nv );
		if ( '' === $ma_nv ) { return array(); }
		$n = (int) $gioi_han;
		if ( $n <= 0 || $n > self::TOI_DA ) { $n = self::TOI_DA; }
		return VHNB_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHNB_DB::t( 'bao' )
			. ' WHERE ma_nv=%s ORDER BY da_doc ASC, tao_luc DESC, id DESC LIMIT %d', $ma_nv, $n ) );
	}

	/* ====================================================================== ghi nhận đã đọc */

	/**
	 * Đánh dấu đã đọc. `$id = 0` là đánh dấu TẤT CẢ.
	 *
	 * ⚠️ Luôn kèm `ma_nv` trong điều kiện, kể cả khi đã có `id`. Thiếu nó là gửi lên một id bất
	 *    kỳ thì đánh dấu được tin của người khác — nhỏ, nhưng vẫn là chạm vào hộp thư người ta.
	 */
	public static function danh_dau_doc( $ma_nv, $id = 0 ) {
		global $wpdb;
		$ma_nv = trim( (string) $ma_nv );
		if ( '' === $ma_nv ) { return 0; }
		$t  = VHNB_DB::t( 'bao' );
		$id = (int) $id;
		if ( $id > 0 ) {
			return (int) $wpdb->query( $wpdb->prepare(
				"UPDATE $t SET da_doc=1 WHERE ma_nv=%s AND id=%d", $ma_nv, $id ) );
		}
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET da_doc=1 WHERE ma_nv=%s AND da_doc=0", $ma_nv ) );
	}

	/** Dọn tin cũ. Gọi từ lượt dọn định kỳ, không gọi giữa đường vẽ trang. */
	public static function don_cu( $ngay = 0 ) {
		global $wpdb;
		$ngay = (int) $ngay;
		if ( $ngay <= 0 ) { $ngay = self::NGAY_GIU; }
		$moc = gmdate( 'Y-m-d H:i:s', strtotime( (string) current_time( 'mysql' ) ) - $ngay * 86400 );
		return (int) $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHNB_DB::t( 'bao' ) . ' WHERE tao_luc < %s', $moc ) );
	}
}
