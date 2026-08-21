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

	/**
	 * Địa chỉ /exec — TỰ CHỮA nếu trong cơ sở dữ liệu đang là dạng gọi không được.
	 *
	 * 🔴 Bản đầu chỉ chuẩn hoá lúc BẤM LƯU. Sai: địa chỉ dạng `/a/macros/<tên miền>/` đã nằm
	 *    trong cơ sở dữ liệu từ trước rồi, nên trang chấm công vẫn gọi bằng địa chỉ hỏng và
	 *    vẫn báo `400 Bad Request` — cho tới khi có người tình cờ bấm Lưu. Mà người đọc trang
	 *    lỗi thì không có lý do gì để đi bấm Lưu một biểu mẫu họ không sửa gì.
	 *    Việc sửa được bằng máy thì đừng bắt người phải bấm.
	 *
	 * Ghi lại luôn giá trị đã sửa (nhiều nhất một lần) để màn Cài đặt hiện đúng cái đang dùng —
	 * chứ không phải chỗ này gọi một địa chỉ mà màn hình khoe một địa chỉ khác.
	 */
	public static function url() {
		$tho = trim( (string) get_option( 'vhcc_exec_url', '' ) );
		if ( $tho === '' ) { return ''; }
		$ch = self::chuan_hoa_url( $tho );
		if ( $ch['url'] !== $tho ) { update_option( 'vhcc_exec_url', $ch['url'] ); }
		return $ch['url'];
	}

	/** Giá trị THÔ đang nằm trong cơ sở dữ liệu — chỉ dùng để chẩn đoán, đừng gọi bằng nó. */
	public static function url_tho() { return trim( (string) get_option( 'vhcc_exec_url', '' ) ); }

	/**
	 * CHUẨN HOÁ địa chỉ /exec, và nói rõ đã sửa gì.
	 *
	 * 🔴 CA THẬT, mất một buổi mới ra: trình soạn Apps Script của tài khoản Google Workspace
	 *    hiện địa chỉ dạng `script.google.com/a/macros/<tên miền>/s/<ID>/exec`. Dạng đó BUỘC
	 *    người gọi phải đăng nhập bằng tài khoản của tên miền đó. WordPress gọi máy-với-máy,
	 *    không đăng nhập được, nên Google trả `400 Bad Request` — một câu không hề nhắc gì tới
	 *    đăng nhập, nên đọc xong vẫn tưởng mình dán sai ID hoặc quên Deploy.
	 *    Cùng một bản triển khai đó, bỏ đoạn `/a/macros/<tên miền>` đi là gọi ẩn danh được.
	 *    Firmware cũng chỉ chạy được với dạng rút gọn — nó có `static_assert` chặn dạng kia.
	 *
	 * Sửa luôn chứ không chỉ báo lỗi: giữ nguyên ID bản triển khai nên không có gì để đoán,
	 * mà bắt anh Thắng tự cắt chuỗi 60 ký tự bằng tay thì thêm một chỗ gõ sai.
	 *
	 * @return array [ 'url' => string, 'sua' => string[] ] — `sua` rỗng là không đổi gì.
	 */
	public static function chuan_hoa_url( $url ) {
		$url = trim( (string) $url );
		$sua = array();
		if ( $url === '' ) { return array( 'url' => '', 'sua' => $sua ); }

		if ( preg_match( '#^(https://script\.google\.com)/a/macros/([^/]+)(/s/.+)$#', $url, $m ) ) {
			$url   = $m[1] . '/macros' . $m[3];
			$sua[] = 'Đã bỏ đoạn <code>/a/macros/' . esc_html( $m[2] ) . '</code> khỏi địa chỉ. '
				. 'Dạng đó đòi người gọi đăng nhập bằng tài khoản ' . esc_html( $m[2] )
				. ', mà WordPress gọi máy-với-máy nên Google trả <code>400 Bad Request</code>. '
				. 'Vẫn đúng bản triển khai đó, chỉ khác đường vào.';
		}

		/* Dấu / ở cuối: Apps Script bỏ qua được, nhưng để nguyên thì địa chỉ này khác địa chỉ
		   trong nhật ký nên đối chiếu bằng mắt hay bị lẫn. */
		if ( substr( $url, -6 ) === '/exec/' ) {
			$url   = substr( $url, 0, -1 );
			$sua[] = 'Đã bỏ dấu <code>/</code> ở cuối.';
		}

		/* `/dev` là địa chỉ bản thử — nó LUÔN đòi đăng nhập, không bao giờ gọi được từ ngoài. */
		if ( substr( $url, -4 ) === '/dev' ) {
			$sua[] = '⚠️ Đây là địa chỉ <code>/dev</code> (bản thử), nó <b>luôn</b> đòi đăng nhập '
				. 'nên gọi từ WordPress không bao giờ được. Lấy địa chỉ <code>/exec</code> ở '
				. 'Deploy → Manage deployments.';
		}

		return array( 'url' => $url, 'sua' => $sua );
	}
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
				return array( 'ok' => false, 'error' => 'App chấm công đang đòi đăng nhập Google. '
					. 'Vào Apps Script → Deploy → Manage deployments, đặt "Who has access" = Anyone, rồi Deploy lại.' );
			}
			if ( stripos( $body, 'Script function not found' ) !== false || stripos( $body, 'doPost' ) !== false ) {
				return array( 'ok' => false, 'error' => 'App chấm công chưa có hàm doPost — chưa dán file CauNoiChamCong.gs, '
					. 'hoặc dán rồi mà chưa Deploy → New version.' );
			}
			return array( 'ok' => false, 'error' => 'App chấm công trả về không phải JSON (mã ' . $code . '). '
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
			return array( 'ok' => false, 'error' => 'App chấm công trả về giao diện rỗng — kiểm biến CN_FILE_GIAO_DIEN trong CauNoiChamCong.gs.' );
		}
		set_transient( $key, $html, 600 );
		return array( 'ok' => true, 'html' => $html, 'tuCache' => false );
	}

	public static function xoa_cache_giao_dien() {
		delete_transient( 'vhcc_giaodien' );
	}
}
