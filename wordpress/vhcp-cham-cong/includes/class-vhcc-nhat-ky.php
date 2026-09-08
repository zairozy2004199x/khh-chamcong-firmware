<?php
/**
 * NHẬT KÝ TRẠM CHẤM CÔNG — một lượt "LƯU CHẤM CÔNG" mất bao lâu, và mất ở KHÂU NÀO.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY
 * =============================================================================================
 * Tối 08/09/2026, một nhân viên bấm Lưu lúc 22:03 và nhận dòng đỏ *"Máy chủ không trả lời sau
 * 10 giây"*. Đi tìm nguyên nhân thì không có gì để đọc:
 *
 *   · Dòng đỏ ấy do CHÍNH TRANG tự in ra khi đồng hồ đếm ngược của nó chạy hết (xem
 *     `templates/tram.php`, hằng `CHO_TOI_DA`). Nó chỉ nói "10 giây trôi qua", không nói máy chủ
 *     đã làm gì — thậm chí không nói máy chủ có nhận được gói tin hay không.
 *   · `php.error.log` của hosting trống trơn — đúng như phải thế: CHẬM không phải LỖI, PHP chạy
 *     xong xuôi thì chẳng có gì để ghi.
 *   · Nhật ký duy nhất plugin có (`vhcc_nhat_ky_may`) là của đường MÁY ESP32. Đường chấm công
 *     online không ghi lấy một dòng.
 *
 * Nên câu hỏi quyết định — *"gói tin lên tới nơi chậm, hay máy chủ xử lý chậm?"* — không ai trả
 * lời được, mà hai câu trả lời ấy dẫn tới hai chỗ sửa khác hẳn nhau (nhà mạng ↔ hosting). Tệp
 * này sinh ra để lần sau có người bị, mở màn nhật ký là đọc ra ngay.
 *
 * =============================================================================================
 * ĐO NHỮNG GÌ
 * =============================================================================================
 *   ms_duong  — gói tin đi từ điện thoại tới lúc PHP bắt đầu chạy. To = mạng/đường truyền.
 *   ms_may    — PHP xử lý xong hết bao lâu.            To = hosting.
 *   ms_anh    — trong đó, riêng khâu ghi tệp ảnh.      To = đĩa hoặc thư mục ảnh quá đông tệp.
 *   ms_csdl   — trong đó, riêng khâu ghi bảng.         To = MySQL đang bị khoá / quá tải.
 *   kb        — thân gói to bằng nào (ảnh nặng cỡ nào).
 *
 * Bốn con số ấy chia được nguyên nhân thành bốn ngả riêng biệt. Chỉ có tổng thời gian thì mãi
 * mãi vẫn là đoán.
 *
 * =============================================================================================
 * ⚠️ `ms_duong` LÀ ƯỚC LƯỢNG, SAI SỐ CỠ ±1,5 GIÂY — ĐỪNG ĐỌC NÓ NHƯ SỐ ĐO CHÍNH XÁC
 * =============================================================================================
 * Điện thoại tự gửi kèm "lúc bấm gửi là mấy giờ" theo ĐỒNG HỒ MÁY CHỦ mà nó đã đồng bộ ở lượt
 * `viec=gio` (biến `MOC` trong `templates/tram.php`). Hai chỗ làm nó lệch:
 *   · mốc ấy lấy theo GIÂY tròn (`current_time('timestamp')`) -> lệch tới ±1s;
 *   · lượt `gio` cũng mất vài trăm ms mới về tới nơi, nên mốc luôn hơi cũ.
 * Vậy nên: 800ms và 1500ms là NHƯ NHAU, đừng phân biệt. Nhưng 900ms với 12000ms thì khác nhau
 * một trời một vực — và đó đúng là câu hỏi cần trả lời. Đo thô mà trả lời được câu hỏi đúng thì
 * hơn hẳn đo tinh mà không có gì để đo.
 *
 * =============================================================================================
 * 🔴 GHI VÀO BẢNG, KHÔNG GHI VÀO `wp_options`
 * =============================================================================================
 * Nhật ký máy ESP32 giữ 200 dòng trong một option, và với nó thì hợp lý. Ở đây thì KHÔNG:
 * option là đọc-sửa-ghi cả mảng, hai lượt chạy cùng một khoảnh khắc là lượt sau đè mất dòng của
 * lượt trước. Mà cái sổ này sinh ra để soi đúng lúc NHIỀU NGƯỜI CÙNG BẤM — tức là nó sẽ mất
 * đúng những dòng đáng giá nhất, và mất im lặng. Một dòng một `INSERT` thì không có chuyện đó.
 *
 * ⚠️ CÁI GIÁ, nói ra cho sòng phẳng: mỗi lượt chấm công gánh thêm một câu `INSERT`. Nó nằm SAU
 *    khi `ms_may` đã chốt, nên sổ không tự đo cả chính mình — nhưng người dùng vẫn chờ thêm
 *    chừng ấy. Một `INSERT` mười cột vào bảng chỉ có khoá chính là dưới một mili giây, đổi lấy
 *    việc thôi phải đoán thì rẻ. Đó cũng là lý do việc nhẹ không ghi, và việc dọn bớt chỉ chạy
 *    thưa (xem `chot()`).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NhatKy {

	/** Tên bảng (chưa có tiền tố) — sơ đồ khai ở VHCC_DB::bang(). */
	const BANG = 'nhat_ky_tram';

	/** Giữ lại bao nhiêu dòng gần nhất. Vài chục lượt chấm mỗi ngày -> đủ soi hơn một tuần. */
	const GIU = 500;

	/**
	 * Việc NHẸ chỉ ghi khi vượt ngưỡng này (ms).
	 *
	 * ⚠️ Lượt `cham` thì ghi HẾT, không xét ngưỡng — xem `chot()`. Sổ chỉ chép lượt chậm là sổ
	 *    không có nền để so: đọc thấy 9 giây mà không biết ngày thường là 0,4 giây hay 6 giây
	 *    thì con số ấy chẳng nói lên điều gì.
	 */
	const CHAM_MS = 3000;

	private static $mo      = false;
	private static $viec    = '';
	private static $bat_dau = array();   // khâu => microtime(true) lúc bấm
	private static $ms      = array();   // khâu => mili giây cộng dồn
	private static $tin     = array();   // ma_nv / coso — điền dần khi biết
	private static $gui_luc = null;      // ms, giờ máy chủ ước lượng lúc điện thoại gửi
	private static $kb      = 0;

	/**
	 * Mở đồng hồ cho một lượt gọi. Gọi ở ĐẦU `VHCC_Tram::cong()`, trước mọi việc.
	 *
	 * `$than` là thân JSON đã giải mã — lấy `gui_luc` từ đó. Điện thoại đời cũ hoặc trang còn
	 * bản cũ trong bộ nhớ đệm sẽ KHÔNG gửi trường ấy; lúc đó `ms_duong` để trống chứ không đoán.
	 */
	public static function mo( $viec, $than = array(), $kb = null ) {
		self::$mo      = true;
		self::$viec    = substr( (string) $viec, 0, 20 );
		self::$bat_dau = array();
		self::$ms      = array();
		self::$tin     = array();
		self::$gui_luc = null;
		self::$kb      = ( null === $kb ) ? 0 : (int) round( (int) $kb / 1024 );

		if ( is_array( $than ) && isset( $than['gui_luc'] ) && is_numeric( $than['gui_luc'] ) ) {
			$g = (float) $than['gui_luc'];
			/* Chỉ nhận mốc NẰM TRONG KHOẢNG HỢP LÝ. Điện thoại sai giờ, người ta sửa tay đồng
			   hồ, hay trang nằm mở qua đêm rồi mới bấm — đều cho ra một con số vô nghĩa. Quá
			   2 phút lệch thì bỏ, vì một cột "đường truyền: 4 tiếng" chỉ làm người đọc lạc
			   hướng, tệ hơn hẳn một ô để trống. */
			$lech = abs( self::bay_gio_ms() - $g );
			if ( $lech < 120000 ) { self::$gui_luc = $g; }
		}
	}

	/** Bắt đầu đo một khâu. Gọi lồng nhau cùng tên hai lần thì lần sau thắng — cố ý, đơn giản. */
	public static function bam( $khau ) {
		if ( ! self::$mo ) { return; }
		self::$bat_dau[ $khau ] = microtime( true );
	}

	/** Kết thúc một khâu, CỘNG DỒN vào tổng của khâu đó (một lượt có thể ghi hai tấm ảnh). */
	public static function dung( $khau ) {
		if ( ! self::$mo || ! isset( self::$bat_dau[ $khau ] ) ) { return; }
		$them = ( microtime( true ) - self::$bat_dau[ $khau ] ) * 1000;
		self::$ms[ $khau ] = ( isset( self::$ms[ $khau ] ) ? self::$ms[ $khau ] : 0 ) + $them;
		unset( self::$bat_dau[ $khau ] );
	}

	/** Ghi thêm dữ kiện biết được giữa chừng (mã NV, cơ sở). Không nhận PIN, không nhận thẻ. */
	public static function tin( $khoa, $gia_tri ) {
		if ( ! self::$mo ) { return; }
		self::$tin[ $khoa ] = (string) $gia_tri;
	}

	/**
	 * Chốt một dòng. Gọi ngay trước khi trả JSON về (xem `VHCC_Tram::ra()`).
	 *
	 * 🔴 KHÔNG ĐƯỢC LÀM HỎNG LƯỢT CHẤM CÔNG. Đây là sổ ghi chép, không phải nghiệp vụ: bảng
	 *    chưa dựng, MySQL chối, cột thiếu — tất cả đều nuốt và đi tiếp. Một cái nhật ký làm
	 *    người ta không chấm công được thì tệ hơn hẳn việc không có nhật ký nào.
	 */
	public static function chot( $kq = '' ) {
		if ( ! self::$mo ) { return; }
		self::$mo = false;                     // chốt một lần cho mỗi lượt

		$ms_may = ( microtime( true ) - self::moc_vao() ) * 1000;

		/* Việc nhẹ mà chạy nhanh thì không ghi: một ngày có hàng nghìn lượt `gio`/`toi`, để
		   chúng vào sổ là 500 dòng đầy trong buổi sáng và đẩy văng hết lượt `cham` — đúng thứ
		   duy nhất cần soi. Chậm hoặc hỏng thì vẫn ghi, vì lúc đó chúng LÀ manh mối. */
		$dang_ke = ( 'cham' === self::$viec )
			|| ( $ms_may >= self::CHAM_MS )
			|| ( '' !== $kq && 0 === strpos( $kq, 'loi' ) );
		if ( ! $dang_ke ) { return; }

		$dong = array(
			'luc'      => current_time( 'mysql' ),
			'viec'     => self::$viec,
			'ma_nv'    => isset( self::$tin['ma_nv'] ) ? substr( self::$tin['ma_nv'], 0, 40 ) : '',
			'coso'     => isset( self::$tin['coso'] ) ? substr( self::$tin['coso'], 0, 190 ) : '',
			'kq'       => substr( (string) $kq, 0, 190 ),
			'ms_may'   => (int) round( $ms_may ),
			'ms_duong' => ( null === self::$gui_luc ) ? null
				: max( 0, (int) round( self::moc_vao_ms() - self::$gui_luc ) ),
			'ms_anh'   => isset( self::$ms['anh'] ) ? (int) round( self::$ms['anh'] ) : 0,
			'ms_csdl'  => isset( self::$ms['csdl'] ) ? (int) round( self::$ms['csdl'] ) : 0,
			'kb'       => (int) self::$kb,
		);

		global $wpdb;
		$bang = VHCC_DB::t( self::BANG );
		if ( ! VHCC_DB::co_bang( $bang ) ) { return; }
		$wpdb->insert( $bang, $dong );

		/* Dọn thưa tay: cứ khoảng 50 lượt mới cắt một lần. Cắt mỗi lượt là thêm một câu DELETE
		   vào đúng đường đang nghi là chậm — sổ đo lại thành thứ làm chậm. */
		if ( 1 === wp_rand( 1, 50 ) ) { self::don(); }
	}

	/** Cắt bớt phần đuôi, giữ `GIU` dòng mới nhất. Cắt theo id nên không phải sắp xếp gì. */
	public static function don() {
		global $wpdb;
		$bang = VHCC_DB::t( self::BANG );
		if ( ! VHCC_DB::co_bang( $bang ) ) { return; }
		$max = (int) $wpdb->get_var( "SELECT MAX(id) FROM $bang" );
		if ( $max > self::GIU ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $bang WHERE id <= %d", $max - self::GIU ) );
		}
	}

	/** Mấy dòng gần nhất, mới trước. */
	public static function ds( $gioi_han = 200 ) {
		global $wpdb;
		$bang = VHCC_DB::t( self::BANG );
		if ( ! VHCC_DB::co_bang( $bang ) ) { return array(); }
		$r = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $bang ORDER BY id DESC LIMIT %d", max( 1, (int) $gioi_han ) ), ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	public static function xoa() {
		global $wpdb;
		$bang = VHCC_DB::t( self::BANG );
		if ( ! VHCC_DB::co_bang( $bang ) ) { return; }
		$wpdb->query( "DELETE FROM $bang" );
	}

	/**
	 * Tóm tắt mấy dòng đã đọc: chậm nhất, trung vị, bao nhiêu lượt quá 10 giây.
	 *
	 * 🔴 TRUNG VỊ CHỨ KHÔNG PHẢI TRUNG BÌNH. Một lượt 40 giây kéo trung bình của 50 lượt lên
	 *    gần một giây, và người đọc kết luận "hệ thống chậm" trong khi 49 lượt kia chạy 0,3
	 *    giây. Câu hỏi thật là *"lượt bình thường nhanh hay chậm"* — đó là trung vị.
	 *
	 * ⚠️ Mốc 10 giây không phải con số tròn cho đẹp: đúng bằng `CHO_TOI_DA` của trang. Vượt nó
	 *    là người dùng NHÌN THẤY dòng đỏ. Đổi bên kia thì đổi luôn ở đây, kẻo sổ báo "0 lượt
	 *    quá hạn" trong khi nhân viên đang đứng chửi cái màn hình.
	 */
	public static function tom_tat( $dong ) {
		$ds = array();
		$qua = 0;
		foreach ( $dong as $d ) {
			if ( 'cham' !== $d['viec'] ) { continue; }
			$t = (int) $d['ms_may'] + (int) $d['ms_duong'];
			$ds[] = $t;
			if ( $t >= 10000 ) { $qua++; }
		}
		if ( ! $ds ) { return array( 'so' => 0, 'giua' => 0, 'lau_nhat' => 0, 'qua_han' => 0 ); }
		sort( $ds );
		$n = count( $ds );
		$giua = ( 1 === $n % 2 ) ? $ds[ ( $n - 1 ) / 2 ]
			: (int) round( ( $ds[ $n / 2 - 1 ] + $ds[ $n / 2 ] ) / 2 );
		return array( 'so' => $n, 'giua' => $giua, 'lau_nhat' => $ds[ $n - 1 ], 'qua_han' => $qua );
	}

	// ================================================================= giờ giấc

	/**
	 * Lúc PHP nhận được lượt gọi (giây, có phần lẻ).
	 *
	 * ⚠️ `REQUEST_TIME_FLOAT` là mốc do PHP đặt, KHÔNG phải `microtime()` gọi ở đầu hàm. Khác
	 *    nhau đúng ở chỗ cần: thời gian WordPress nạp xong 200 tệp trước khi tới được đây cũng
	 *    là thời gian người dùng phải chờ, nên nó PHẢI nằm trong con số đo.
	 */
	private static function moc_vao() {
		return isset( $_SERVER['REQUEST_TIME_FLOAT'] )
			? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
	}

	/** Cùng mốc đó nhưng quy về ĐỒNG HỒ MÁY CHỦ (đã cộng lệch múi giờ), tính bằng ms. */
	private static function moc_vao_ms() {
		return ( self::moc_vao() + (float) get_option( 'gmt_offset', 0 ) * 3600 ) * 1000;
	}

	/** Giờ máy chủ bây giờ, ms — cùng gốc với `gui_luc` mà điện thoại gửi lên. */
	private static function bay_gio_ms() {
		return (float) current_time( 'timestamp' ) * 1000;
	}
}
