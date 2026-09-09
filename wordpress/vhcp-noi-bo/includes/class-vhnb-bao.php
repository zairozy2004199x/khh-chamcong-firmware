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
 * Nên đảo chiều: hộp thư mở CỬA NHẬN ở đây và bên nào có tin thì tự gọi vào, kèm sẵn câu chữ
 * của mình. Nội bộ không cần biết "chấm công" hay "chi phí" là cái gì; nó chỉ giữ và bày ra.
 * Bên gửi gác `class_exists` nên chưa cài nội bộ thì lời gọi im lặng trôi qua, không ai vỡ.
 *
 * Hai cửa, và chọn đúng cửa là quan trọng:
 *   · `gui()`  — CHỈ chuông riêng của một người. Được mang tiền, tên, giờ công.
 *   · `viec()` — chuông riêng + MỘT dòng trung tính ở bảng tin cho cả công ty đọc.
 *                Dùng cho GIAO DỊCH bên chi phí · chấm công · ghế · vé.
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

	/**
	 * MỘT GIAO DỊCH BÊN NGOÀI → CHUÔNG RIÊNG + MỘT DÒNG Ở BẢNG TIN.
	 *
	 * Anh Thắng 08/09/2026: *"khi có 1 giao dịch trên vận hành chi phí, chấm công, ghế thì hiện
	 * thông báo lên trang nội bộ, cả thông báo và trong bảng tin"*.
	 *
	 * =========================================================================================
	 * 🔴 HAI CÂU CHỮ, KHÔNG PHẢI MỘT — VÀ ĐÂY LÀ CHỖ DUY NHẤT ĐÁNG ĐỌC KỸ TRONG HÀM NÀY
	 * =========================================================================================
	 * Chuông là hộp thư RIÊNG của đúng một người: `$chu` được phép mang số tiền, giờ công, tên
	 * người — đó là việc của chính họ. Bảng tin thì 240 người đọc.
	 *
	 * Anh Thắng chốt 08/09/2026: bảng tin để **câu trung tính, không tiền không tên**.
	 *
	 * Nên `$tin_chung` là một tham số RIÊNG, và **KHÔNG BAO GIỜ** mặc định về `$chu`. Nếu nó
	 * mặc định về `$chu` thì mỗi nơi gọi quên truyền là cả chuỗi đọc được ai xin bao nhiêu tiền
	 * và ai bị sửa giờ công — một dòng thiếu sót ở chỗ khác, hậu quả ở đây. Rỗng thì KHÔNG đăng
	 * gì cả: thà thiếu một dòng bảng tin còn hơn rò một con số.
	 * `tools/test/kiem-noi-bo.php` chốt đúng việc này, đừng nới ra.
	 *
	 * 🔴 CHUÔNG HỎNG THÌ BẢNG TIN VẪN PHẢI LÊN. `gui()` trả false ở mấy ca hợp lệ: không tra ra
	 *    mã người nhận, hoặc người gây ra chính là người nhận. Bên chi phí thì ca đầu có thật —
	 *    bảng đơn chỉ giữ TÊN người lập, tra ngược không ra là thường. Giao dịch vẫn đã xảy ra,
	 *    nên dòng bảng tin không được phụ thuộc vào việc chuông có tìm được chủ hay không.
	 *
	 * @param string $ma_nv     mã NV NHẬN chuông. Rỗng thì bỏ chuông, bảng tin vẫn đăng.
	 * @param string $nguon     'chi_phi' · 'cham_cong' · 'ghe' · 've'.
	 * @param string $chu       câu cho CHUÔNG RIÊNG — được mang tiền/tên/giờ.
	 * @param string $duong_dan bấm vào chuông thì đi đâu.
	 * @param string $khoa      khoá gộp của CHUÔNG.
	 * @param string $tu_ma_nv  mã NV gây ra việc, để không tự báo cho chính mình.
	 * @param string $tin_chung câu cho BẢNG TIN — trung tính. Rỗng = không đăng bảng tin.
	 * @param string $khoa_tin  khoá gộp của BẢNG TIN. Rỗng = mỗi lượt một bài (hiếm khi đúng).
	 * @param string $co_so     cơ sở của giao dịch. Cửa hàng trưởng chỉ đọc được dòng của cơ sở
	 *                          mình; Quản lý trở lên đọc hết. Rỗng = không thuộc cơ sở nào, ai
	 *                          qua được bậc cũng đọc — dùng cho việc trải nhiều cơ sở (đơn chi
	 *                          phí gồm nhiều cơ sở trong một đơn).
	 * @return array( 'bao' => id|false, 'tin' => id|false )
	 */
	public static function viec( $ma_nv, $nguon, $chu, $duong_dan = '', $khoa = '',
			$tu_ma_nv = '', $tin_chung = '', $khoa_tin = '', $co_so = '' ) {
		$bao = self::gui( $ma_nv, $nguon, $chu, $duong_dan, $khoa, $tu_ma_nv );
		$tin = false;
		if ( '' !== trim( (string) $tin_chung ) ) {
			$tin = VHNB_Bai::dang_he_thong( $nguon, $tin_chung, $khoa_tin, $co_so );
		}
		return array( 'bao' => $bao, 'tin' => $tin );
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
