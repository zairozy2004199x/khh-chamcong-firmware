<?php
/**
 * CHUYỂN TIẾP LỆNH sang app chấm công trên Apps Script.
 *
 * Trình duyệt gọi WordPress → WordPress gọi /exec kèm KHOÁ BÍ MẬT → Apps Script làm việc với
 * Sheet/Drive/AI rồi trả JSON → WordPress trả nguyên văn về trình duyệt.
 *
 * Vì sao đi vòng chứ không gọi thẳng /exec từ trình duyệt:
 *   - Khoá bí mật không xuống trình duyệt. Gọi thẳng là xem mã trang thấy khoá, rồi ghi được
 *     vào sheet chấm công.
 *   - Cổng PIN nằm ở WordPress, dùng chung tài khoản với app Vận hành chi phí.
 *   - Trình duyệt gọi chéo tên miền sang script.google.com còn vướng CORS; gọi từ máy chủ thì không.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_CauNoi {

	/** Lệnh nào cũng có thể chạy lâu: bóc tách 1 file PDF bằng AI mất 1–3 phút. */
	const TIMEOUT = 300;

	public static function url()  { return trim( (string) get_option( 'vhcc_exec_url', '' ) ); }
	public static function khoa() { return trim( (string) get_option( 'vhcc_web_key', '' ) ); }

	/** Khoá dùng chung với Apps Script — sinh sẵn để không ai đặt tay một chuỗi ngắn dễ đoán. */
	public static function bao_dam_khoa() {
		$k = self::khoa();
		if ( $k === '' ) {
			$k = bin2hex( random_bytes( 24 ) );
			update_option( 'vhcc_web_key', $k );
		}
		return $k;
	}

	/**
	 * Gọi một hàm của app gốc.
	 *
	 * @return array [ 'ok' => bool, 'data' => mixed, 'error' => string ]
	 */
	public static function goi( $fn, $args = array() ) {
		$url = self::url();
		if ( $url === '' ) {
			return array( 'ok' => false, 'error' => 'Chưa khai địa chỉ /exec của app chấm công trong Cài đặt.' );
		}
		$khoa = self::khoa();
		if ( $khoa === '' ) {
			return array( 'ok' => false, 'error' => 'Chưa có khoá cầu nối. Vào Cài đặt → Hệ thống chấm công để sinh khoá.' );
		}

		$r = wp_remote_post( $url, array(
			'timeout'     => self::TIMEOUT,
			'redirection' => 5,   // Apps Script luôn chuyển hướng sang googleusercontent.com
			'headers'     => array( 'Content-Type' => 'text/plain;charset=UTF-8' ),
			'body'        => wp_json_encode( array(
				'key'  => $khoa,
				'fn'   => (string) $fn,
				'args' => array_values( (array) $args ),
			) ),
		) );

		if ( is_wp_error( $r ) ) {
			return array( 'ok' => false, 'error' => 'Không gọi được app chấm công: ' . $r->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $r );
		$body = (string) wp_remote_retrieve_body( $r );

		// Apps Script trả trang HTML khi deploy sai quyền hoặc URL sai — nói rõ chứ đừng để
		// giao diện nhận một cục HTML rồi báo "lỗi không rõ".
		if ( $body !== '' && $body[0] !== '{' && $body[0] !== '[' ) {
			if ( stripos( $body, 'accounts.google.com' ) !== false || stripos( $body, 'Sign in' ) !== false ) {
				return array( 'ok' => false, 'error' => 'App hợp đồng đang đòi đăng nhập Google. '
					. 'Vào Apps Script → Deploy → Manage deployments, đặt "Who has access" = Anyone, rồi Deploy lại.' );
			}
			if ( stripos( $body, 'Script function not found' ) !== false || stripos( $body, 'doPost' ) !== false ) {
				return array( 'ok' => false, 'error' => 'App hợp đồng chưa có hàm doPost — chưa dán file CauNoiChamCong.gs, '
					. 'hoặc dán rồi mà chưa Deploy → New version.' );
			}
			return array( 'ok' => false, 'error' => 'App hợp đồng trả về không phải JSON (mã ' . $code . '). '
				. 'Kiểm lại địa chỉ /exec và đã Deploy bản mới chưa. Đầu phản hồi: '
				. mb_substr( wp_strip_all_tags( $body ), 0, 160 ) );
		}

		$j = json_decode( $body, true );
		if ( ! is_array( $j ) ) {
			return array( 'ok' => false, 'error' => 'Không đọc được phản hồi của app chấm công (mã ' . $code . ').' );
		}
		if ( empty( $j['ok'] ) ) {
			return array( 'ok' => false, 'error' => isset( $j['error'] ) ? (string) $j['error'] : 'Lỗi không rõ từ app chấm công' );
		}
		return array( 'ok' => true, 'data' => isset( $j['data'] ) ? $j['data'] : null );
	}

	/** Thử cầu nối (dùng ở trang Cài đặt). */
	public static function thu() {
		return self::goi( '__ping' );
	}

	/**
	 * GIAO DIỆN GỐC lấy thẳng từ project Apps Script.
	 *
	 * Nhờ vậy không phải chép Index.html sang plugin: sửa giao diện bên Apps Script rồi Deploy
	 * là trang web có bản mới. Chép sang đây là sinh ra hai bản, rồi sửa một bên quên bên kia.
	 *
	 * Nhớ tạm 10 phút để mỗi lần mở trang không phải ra mạng; bấm "Làm mới giao diện" trong
	 * Cài đặt thì xoá nhớ tạm ngay.
	 */
	public static function giao_dien( $bo_qua_cache = false ) {
		$key = 'vhcc_giaodien';
		if ( ! $bo_qua_cache ) {
			$c = get_transient( $key );
			if ( is_string( $c ) && $c !== '' ) { return array( 'ok' => true, 'html' => $c, 'tuCache' => true ); }
		}
		$r = self::goi( '__giaoDien' );
		if ( empty( $r['ok'] ) ) { return array( 'ok' => false, 'error' => $r['error'] ); }
		$html = (string) $r['data'];
		if ( trim( $html ) === '' ) {
			return array( 'ok' => false, 'error' => 'App hợp đồng trả về giao diện rỗng — kiểm biến CN_FILE_GIAO_DIEN trong CauNoiChamCong.gs.' );
		}
		set_transient( $key, $html, 600 );
		return array( 'ok' => true, 'html' => $html, 'tuCache' => false );
	}

	public static function xoa_cache_giao_dien() {
		delete_transient( 'vhcc_giaodien' );
	}
}
