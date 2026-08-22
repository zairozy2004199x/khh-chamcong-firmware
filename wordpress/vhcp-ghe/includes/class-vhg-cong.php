<?php
/**
 * HAI CỔNG CỦA PLUGIN: cổng nhận TIỀN (ngân hàng bắn tới) và cổng của GHẾ (ESP32 hỏi).
 *
 * =============================================================================================
 * 🔴 CỔNG NHẬN TIỀN LÀ ĐƯỜNG NÓNG NHẤT CỦA CẢ HỆ THỐNG
 * =============================================================================================
 * Mỗi lượt khách quét QR trả tiền là một lượt vào đây. Mất một lượt = mất một lần doanh thu, và
 * ghế KHÔNG chạy trong khi khách đã trả tiền — người ta đứng ở quầy cãi nhau, không phải cuối
 * tháng mới biết.
 *
 * Bốn luật, học từ chính đường máy chấm công (class-vhcc-nhan.php):
 *
 * 1. KHÔNG BAO GIỜ ĐƯỢC CHUYỂN HƯỚNG. WordPress rất thích chuyển hướng để thêm/bỏ dấu gạch
 *    cuối. Bên gửi webhook phần lớn KHÔNG đi theo 30x, hoặc đi theo bằng GET và mất trọn thân
 *    POST. Nên chặn chuyển hướng ngay từ `parse_request`.
 *
 * 2. TRẢ 200 CHO MỌI GÓI ĐÃ QUA KHOÁ, kể cả gói không đọc được. Ngân hàng/bên trung gian thấy
 *    khác 2xx là đẩy lại nhiều lần rồi bỏ hẳn, có bên còn TẮT webhook sau vài lần liên tiếp.
 *    Gói không đọc được thì ta GIỮ LẠI trong nhật ký để xử tay — đó mới là cách không mất tiền.
 *    Ca DUY NHẤT trả khác 200: sai khoá (401), để người cấu hình thấy ngay.
 *
 * 3. GHI NHẬT KÝ MỌI LƯỢT, KỂ CẢ LƯỢT BỊ TỪ CHỐI. Đó là cách duy nhất phân biệt "bên gửi chưa
 *    bắn" với "bắn rồi mà mình chặn" — hai ca đó đi sửa ở hai nơi khác hẳn.
 *
 * 4. KHOÁ ĐI TRÊN ĐƯỜNG DẪN (`?token=`). Bên gửi webhook thường không cho đặt header tuỳ ý.
 *    Nghĩa là khoá nằm trong nhật ký máy chủ và trong ô cấu hình của bên gửi -> coi như đã lộ
 *    một phần, phải đổi được dễ dàng. Nó nằm ở `wp-config.php`, không nằm trong mã nguồn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Cong {

	const DUONG_TIEN = 'ghe-tien';    // ngân hàng / SePay / VietQR / Tingo bắn vào đây
	const DUONG_MAY  = 'ghe-may';     // ESP32 của ghế hỏi ở đây

	public static function init() {
		add_rewrite_rule( '^' . self::DUONG_TIEN . '/?$', 'index.php?vhg_cong=tien', 'top' );
		add_rewrite_rule( '^' . self::DUONG_MAY . '/?$', 'index.php?vhg_cong=may', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhg_cong'; return $v; }

	/** Đường nào đang được gọi: 'tien', 'may', hoặc '' nếu không phải cổng của plugin. */
	public static function duong_nao() {
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		foreach ( array( self::DUONG_TIEN => 'tien', self::DUONG_MAY => 'may' ) as $duong => $ten ) {
			if ( $d === $duong || substr( $d, - ( strlen( $duong ) + 1 ) ) === '/' . $duong ) { return $ten; }
		}
		$q = get_query_var( 'vhg_cong' );
		return ( 'tien' === $q || 'may' === $q ) ? $q : '';
	}

	public static function chan_chuyen_huong() {
		if ( '' === self::duong_nao() ) { return; }
		add_filter( 'redirect_canonical', '__return_false', 99 );
		remove_action( 'template_redirect', 'redirect_canonical' );
		add_filter( 'wp_redirect', array( __CLASS__, 'khong_chuyen_huong' ), 99, 2 );
	}

	public static function khong_chuyen_huong( $dich, $tt ) {
		VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
			'có nơi cố chuyển hướng cổng sang ' . $dich . ' (' . $tt . ') — đã chặn' ) );
		return false;
	}

	private static function tra( $ma, $tt ) {
		if ( ! headers_sent() ) {
			status_header( $ma );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $tt );
		if ( ! defined( 'VHG_TEST' ) ) { exit; }
	}

	private static function than() {
		if ( defined( 'VHG_TEST' ) && isset( $GLOBALS['VHG_THAN'] ) ) { return (string) $GLOBALS['VHG_THAN']; }
		$t = file_get_contents( 'php://input' );
		return false === $t ? '' : $t;
	}

	/** Khoá cấu hình. Rỗng = ĐÓNG, không phải mở. */
	public static function khoa( $ten_hang ) {
		return defined( $ten_hang ) ? (string) constant( $ten_hang ) : '';
	}

	public static function phuc_vu() {
		$duong = self::duong_nao();
		if ( '' === $duong ) { return; }
		if ( 'tien' === $duong ) { self::cong_tien(); return; }
		self::cong_may();
	}

	// ===========================================================================================
	// CỔNG TIỀN
	// ===========================================================================================

	private static function cong_tien() {
		$tho = self::than();

		/* Khoá trên đường dẫn — xem luật 4 ở đầu tệp. */
		$gui  = isset( $_GET['token'] ) ? (string) $_GET['token'] : '';
		$that = self::khoa( 'VHG_KHOA_WEBHOOK' );
		if ( '' === $that || '' === $gui || ! hash_equals( $that, $gui ) ) {
			/* VẪN GHI NHẬT KÝ: chứng tỏ CÓ request tới nơi. Không có dòng này thì "bên gửi chưa
			   bắn" và "bắn rồi mà mình chặn vì sai khoá" nhìn giống hệt nhau. */
			VHG_Nhat_Ky::ghi( array( 'nguon' => self::nguon_gui(), 'tho' => $tho, 'ghi_chu' =>
				'' === $that
					? 'BỊ TỪ CHỐI: máy chủ chưa khai VHG_KHOA_WEBHOOK trong wp-config.php'
					: 'BỊ TỪ CHỐI: thiếu/sai token trên đường dẫn (request CÓ tới nơi)' ) );
			self::tra( 401, array( 'success' => false, 'error' => 'bad token' ) );
			return;
		}

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			/* GET vào đây gần như luôn là người ta dán link vào trình duyệt để thử. Trả lời tử tế
			   và KHÔNG ghi nhật ký — nếu không thì mỗi lần thử link là một dòng rác. */
			self::tra( 200, array( 'success' => true, 'note' =>
				'Cong nhan tien dang song. Hay cau hinh ben gui BAN POST vao dung dia chi nay.' ) );
			return;
		}

		$goi = json_decode( $tho, true );
		if ( ! is_array( $goi ) ) { $goi = array(); }
		/* Bên gửi dùng form (x-www-form-urlencoded) thay vì JSON -> gom thêm từ $_POST. */
		if ( ! empty( $_POST ) ) {
			foreach ( $_POST as $k => $v ) {
				if ( ! isset( $goi[ $k ] ) || '' === $goi[ $k ] ) { $goi[ $k ] = $v; }
			}
		}

		$nguon = self::nguon_gui();
		$ds = VHG_Doc::tach( $goi );
		if ( ! $ds ) {
			/* Không đọc được -> GIỮ LẠI kèm thân thô. Xem luật 2. */
			VHG_Nhat_Ky::ghi( array( 'nguon' => $nguon, 'tho' => $tho,
				'ghi_chu' => 'KHÔNG đọc được giao dịch nào từ gói — giữ thân thô để xử tay' ) );
			self::tra( 200, array( 'success' => true, 'parsed' => 0 ) );
			return;
		}

		$kq = array();
		foreach ( $ds as $ev ) {
			$r = VHG_Thu::nhan( $nguon, $ev );
			VHG_Nhat_Ky::ghi( array(
				'nguon'    => $nguon,
				'so_tien'  => isset( $ev['so_tien'] ) ? $ev['so_tien'] : 0,
				'noi_dung' => isset( $ev['noi_dung'] ) ? $ev['noi_dung'] : '',
				'ref'      => isset( $r['ref'] ) ? $r['ref'] : ( isset( $ev['ref'] ) ? $ev['ref'] : '' ),
				'khop'     => ! empty( $r['ma_may'] ),
				'ma_may'   => isset( $r['ma_may'] ) ? $r['ma_may'] : '',
				'ma_lenh'  => isset( $r['ma_lenh'] ) ? $r['ma_lenh'] : '',
				'ten_khai' => isset( $r['ten_khai'] ) ? $r['ten_khai'] : '',
				'ghi_chu'  => isset( $r['ghi_chu'] ) ? $r['ghi_chu'] : ( isset( $r['error'] ) ? $r['error'] : '' ),
				'tho'      => $tho,
			) );
			$kq[] = $r;
		}
		self::tra( 200, array( 'success' => true, 'nguon' => $nguon, 'count' => count( $kq ) ) );
	}

	/** Bên nào đang gửi. `?src=vietqr` là ta tự đặt lúc khai webhook; mặc định coi là SePay. */
	private static function nguon_gui() {
		$s = isset( $_GET['src'] ) ? strtolower( trim( (string) $_GET['src'] ) ) : '';
		if ( 'vietqr' === $s || 'tingo' === $s ) { return VHG_Thu::VIETQR; }
		return '' === $s ? VHG_Thu::SEPAY : preg_replace( '/[^a-z0-9_-]/', '', $s );
	}

	// ===========================================================================================
	// CỔNG CỦA GHẾ
	// ===========================================================================================

	/**
	 * ESP32 của ghế POST JSON `{key, ma_may, viec, …}`. Ba việc:
	 *   nhip  -> báo còn sống + trạng thái; trả về CÓ tiền chờ không, CÓ lệnh không, giá/phút
	 *   luot  -> lấy một lượt đã trả tiền (đánh dấu đã nhận)
	 *   lenh  -> lấy một lệnh bật/tắt tay
	 *
	 * Gộp câu trả lời vào `nhip` để ghế chỉ phải gọi MỘT lượt mỗi vòng khi không có gì xảy ra —
	 * ghế đặt ở cửa hàng, phần lớn thời gian là rảnh.
	 */
	private static function cong_may() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			self::tra( 405, array( 'ok' => false, 'error' => 'Cong nay chi nhan POST.' ) );
			return;
		}
		$d = json_decode( self::than(), true );
		if ( ! is_array( $d ) ) { $d = array(); }

		$that = self::khoa( 'VHG_KHOA_MAY' );
		$gui  = isset( $_SERVER['HTTP_X_VHG_KEY'] ) ? (string) $_SERVER['HTTP_X_VHG_KEY']
			: ( isset( $d['key'] ) ? (string) $d['key'] : '' );
		if ( '' === $that || '' === $gui || ! hash_equals( $that, $gui ) ) {
			self::tra( 401, array( 'ok' => false, 'error' => 'Sai khoa hoac chua cau hinh VHG_KHOA_MAY.' ) );
			return;
		}

		$ma_may = trim( (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) );
		if ( '' === $ma_may ) {
			self::tra( 200, array( 'ok' => false, 'error' => 'Thieu ma_may.' ) );
			return;
		}
		$viec = strtolower( trim( (string) ( isset( $d['viec'] ) ? $d['viec'] : 'nhip' ) ) );

		if ( 'luot' === $viec ) {
			$r = VHG_May::lay_luot( $ma_may );
			self::tra( 200, $r
				? array( 'ok' => true, 'co' => 1, 'ma_lenh' => $r['ma_lenh'],
					'so_tien' => (int) $r['so_tien'], 'phut' => self::phut_cua( $ma_may ) )
				: array( 'ok' => true, 'co' => 0 ) );
			return;
		}
		if ( 'lenh' === $viec ) {
			$r = VHG_May::lay_lenh( $ma_may );
			self::tra( 200, $r
				? array( 'ok' => true, 'co' => 1, 'viec' => $r['viec'], 'phut' => (int) $r['phut'] )
				: array( 'ok' => true, 'co' => 0 ) );
			return;
		}

		/* nhip (mặc định) */
		VHG_May::nhip( $ma_may, array(
			'trang_thai' => isset( $d['trang_thai'] ) ? $d['trang_thai'] : 'idle',
			'nguon'      => isset( $d['nguon'] ) ? $d['nguon'] : '',
			'con_lai'    => isset( $d['con_lai'] ) ? $d['con_lai'] : 0,
			'ip'         => isset( $d['ip'] ) ? $d['ip'] : '',
			'fw'         => isset( $d['fw'] ) ? $d['fw'] : '',
		) );
		$m = VHG_May::may( $ma_may );
		self::tra( 200, array(
			'ok'      => true,
			'coTien'  => VHG_May::so_cho( $ma_may ) > 0 ? 1 : 0,
			'coLenh'  => self::co_lenh( $ma_may ) ? 1 : 0,
			'gia'     => $m ? (int) $m['gia'] : 0,
			'phut'    => $m ? (int) $m['phut'] : 0,
			'soTk'    => $m ? (string) $m['so_tk'] : '',
			'khai'    => $m ? 1 : 0,
		) );
	}

	private static function phut_cua( $ma_may ) {
		$m = VHG_May::may( $ma_may );
		return $m ? (int) $m['phut'] : 6;
	}

	private static function co_lenh( $ma_may ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHG_DB::t( 'lenh' ) . ' WHERE ma_may=%s AND gui_luc IS NULL',
			$ma_may ) ) > 0;
	}
}

/**
 * NHẬT KÝ CỔNG — mọi lượt bắn tới đều để lại dấu. Xem luật 3 ở đầu class-vhg-cong.php.
 *
 * ⚠️ Giữ 2000 ký tự đầu của thân thô. Đủ để thấy bên gửi đang dùng tên trường gì khi họ đổi mà
 *    không báo; và đủ ngắn để bảng không phình. Cắt ở đây chứ không cắt lúc đọc — lúc đọc thì
 *    cần nguyên văn.
 */
class VHG_Nhat_Ky {

	/** Giữ tối đa bao nhiêu dòng. Nhật ký để chẩn đoán, không phải để lưu trữ. */
	const GIU = 500;

	public static function ghi( $d ) {
		global $wpdb;
		$wpdb->insert( VHG_DB::t( 'nhat_ky' ), array(
			'luc'      => current_time( 'mysql' ),
			'nguon'    => mb_substr( (string) ( isset( $d['nguon'] ) ? $d['nguon'] : '' ), 0, 30 ),
			'so_tien'  => (int) ( isset( $d['so_tien'] ) ? $d['so_tien'] : 0 ),
			'noi_dung' => mb_substr( (string) ( isset( $d['noi_dung'] ) ? $d['noi_dung'] : '' ), 0, 250 ),
			'ref'      => mb_substr( (string) ( isset( $d['ref'] ) ? $d['ref'] : '' ), 0, 120 ),
			'khop'     => ! empty( $d['khop'] ) ? 1 : 0,
			'ma_may'   => mb_substr( (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ), 0, 40 ),
			'ma_lenh'  => mb_substr( (string) ( isset( $d['ma_lenh'] ) ? $d['ma_lenh'] : '' ), 0, 40 ),
			'ten_khai' => mb_substr( (string) ( isset( $d['ten_khai'] ) ? $d['ten_khai'] : '' ), 0, 190 ),
			'ghi_chu'  => mb_substr( (string) ( isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '' ), 0, 250 ),
			'tho'      => mb_substr( (string) ( isset( $d['tho'] ) ? $d['tho'] : '' ), 0, 2000 ),
		) );
		self::don();
	}

	/** Cắt bớt cho khỏi phình. Xoá theo id nên không đụng vào hàng mới đang ghi. */
	private static function don() {
		global $wpdb;
		$bang = VHG_DB::t( 'nhat_ky' );
		$so = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );
		if ( $so <= self::GIU + 100 ) { return; }
		$moc = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $bang ORDER BY id DESC LIMIT 1 OFFSET %d", self::GIU ) );
		if ( $moc > 0 ) { $wpdb->query( $wpdb->prepare( "DELETE FROM $bang WHERE id <= %d", $moc ) ); }
	}

	public static function ds( $gioi_han = 100 ) {
		return VHG_DB::rows( 'SELECT * FROM ' . VHG_DB::t( 'nhat_ky' )
			. ' ORDER BY id DESC LIMIT ' . (int) $gioi_han );
	}

	public static function xoa() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . VHG_DB::t( 'nhat_ky' ) );
		return array( 'ok' => true, 'thong_bao' => 'Đã xoá nhật ký. Doanh thu đã ghi KHÔNG bị ảnh hưởng.' );
	}
}
