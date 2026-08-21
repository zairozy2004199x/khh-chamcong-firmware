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
		if ( $ch['url'] !== $tho ) {
			update_option( 'vhcc_exec_url', $ch['url'] );
			if ( $ch['mien'] !== '' ) { update_option( 'vhcc_exec_mien', $ch['mien'] ); }
		}
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

		$mien = '';
		if ( preg_match( '#^(https://script\.google\.com)/a/macros/([^/]+)(/s/.+)$#', $url, $m ) ) {
			$url   = $m[1] . '/macros' . $m[3];
			$mien  = $m[2];
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

		/* Trả kèm tên miền Workspace đã cắt: cắt xong là thông tin đó MẤT khỏi địa chỉ, mà phép
		   chẩn đoán lại cần nó để thử dạng theo tên miền. Đoán theo tên miền của website là sai —
		   tên miền Google Workspace không nhất thiết trùng tên miền trang web. Phép thử ca "bản
		   triển khai bị giới hạn" đã bắt được đúng chỗ này. */
		return array( 'url' => $url, 'sua' => $sua, 'mien' => $mien );
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

		/* 🔴 TỰ ĐI THEO CHUYỂN HƯỚNG, KHÔNG NHỜ WordPress — chỗ này đã tốn cả buổi.
		 *
		 * Apps Script trả 302 sang `script.googleusercontent.com/macros/echo?...`. Địa chỉ đó
		 * chỉ nhận GET: nó là chỗ LẤY KẾT QUẢ, script đã chạy xong rồi. Để `redirection => 5`
		 * thì WordPress đi theo mà GIỮ NGUYÊN phương thức POST, nên Google chối bằng
		 * `400 Bad Request` — đúng cái lỗi anh Thắng gặp: `GET` vào /exec thì 200 và trả về
		 * 570 KB giao diện, mà `POST` thì 400. Cùng một địa chỉ, khác phương thức.
		 *
		 * Trình duyệt và cURL hạ POST xuống GET khi gặp 302; WordPress thì không. Nên tự làm:
		 * POST với `redirection => 0`, lấy `Location`, rồi GET sang đó.
		 *
		 * Firmware cũng phải làm đúng việc này (`HTTPC_DISABLE_FOLLOW_REDIRECTS` rồi GET tay,
		 * xem esp32_hik_chamcong_full.ino) — cùng một nguyên nhân, ở hai nơi khác nhau.
		 *
		 * 307/308 thì giữ POST theo đúng chuẩn HTTP; Apps Script không dùng hai mã đó nhưng
		 * viết cho đúng vẫn rẻ hơn là sau này gặp rồi ngồi đoán lần nữa.
		 */
		$than = wp_json_encode( array(
			'key'  => $khoa,
			'fn'   => (string) $fn,
			'args' => array_values( (array) $args ),
		) );
		$dat_post = array(
			'timeout'     => self::TIMEOUT,
			'redirection' => 0,
			'headers'     => array( 'Content-Type' => 'text/plain;charset=UTF-8' ),
			'body'        => $than,
		);

		$r = wp_remote_post( $url, $dat_post );
		if ( is_wp_error( $r ) ) {
			return array( 'ok' => false, 'error' => 'Không gọi được app chấm công: ' . $r->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $r );
		for ( $i = 0; $i < 5 && in_array( $code, array( 301, 302, 303, 307, 308 ), true ); $i++ ) {
			$dich = trim( (string) wp_remote_retrieve_header( $r, 'location' ) );
			if ( $dich === '' ) { break; }
			$r = ( 307 === $code || 308 === $code )
				? wp_remote_post( $dich, $dat_post )
				: wp_remote_get( $dich, array( 'timeout' => self::TIMEOUT, 'redirection' => 0 ) );
			if ( is_wp_error( $r ) ) {
				return array( 'ok' => false, 'error' => 'Không lấy được kết quả từ app chấm công: '
					. $r->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $r );
		}
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
			$loi = isset( $j['error'] ) ? (string) $j['error'] : 'Lỗi không rõ từ app chấm công';
			/* 🔴 DỊCH LẠI MỘT CÂU CỦA APP GỐC.
			   `_requireAuth` bên Apps Script ném "Phiên đăng nhập không hợp lệ, hãy đăng nhập
			   lại." Câu đó đúng cho NGƯỜI đang ngồi trước app gốc, nhưng ở đây không có ai đăng
			   nhập cả: cầu nối gọi máy-với-máy bằng PIN admin khai trong `wp-config.php`. Người
			   đọc câu nguyên văn sẽ đi đăng nhập lại wp-admin — việc chẳng liên quan gì.
			   Câu duy nhất đúng là: PIN đó app gốc không nhận. */
			if ( false !== stripos( $loi, 'Phiên đăng nhập không hợp lệ' ) ) {
				$loi = 'App gốc không nhận PIN admin mà plugin gửi sang. Đó là hằng '
					. '<code>VHCC_PIN_ADMIN</code> trong <code>wp-config.php</code> — nó phải bằng ĐÚNG một '
					. 'PIN đang có trong sheet <code>PhanQuyen</code> của app gốc, vai trò Admin. '
					. ( '' === VHCC_May::pin()
						? 'Hiện plugin CHƯA khai PIN nào.'
						: 'Plugin đang gửi một PIN dài ' . strlen( VHCC_May::pin() ) . ' ký tự.' )
					. ' (Không liên quan gì tới việc đăng nhập wp-admin hay PIN trang chấm công.)';
			}
			return array( 'ok' => false, 'error' => $loi );
		}
		return array( 'ok' => true, 'data' => isset( $j['data'] ) ? $j['data'] : null );
	}

	/** Thử cầu nối (dùng ở trang Cài đặt). */
	public static function thu() {
		return self::goi( '__ping' );
	}

	/**
	 * CHẨN ĐOÁN ĐỊA CHỈ — thử cả hai dạng bằng GET trơn, nói rõ mỗi dạng trả về gì.
	 *
	 * 🔴 VÌ SAO CẦN: `400 Bad Request` của Google là lỗi ở CỔNG VÀO của Google, tức là yêu cầu
	 *    CHƯA TỚI được script. Nên mọi phỏng đoán kiểu "chưa dán CauNoiChamCong" hay "sai
	 *    WEB_KEY" đều vô nghĩa — script chưa hề chạy. Mà một câu 400 thì không nói được là
	 *    bản triển khai sai, hay bản triển khai không cho người ngoài gọi.
	 *
	 *    Phân biệt được bằng cách thử CẢ HAI dạng địa chỉ trên cùng một mã triển khai:
	 *      · dạng `/a/macros/<tên miền>/` đòi đăng nhập  + dạng rút gọn trả 400
	 *          -> bản triển khai bị giới hạn theo tên miền. Phải đặt "Who has access" = Anyone.
	 *      · dạng rút gọn trả HTML của app
	 *          -> địa chỉ tốt, lỗi nằm ở chỗ khác (chưa dán cầu nối / sai khoá / chưa Deploy).
	 *      · cả hai đều 400
	 *          -> mã triển khai không tồn tại, hoặc đó không phải bản triển khai Web app.
	 *
	 * Dùng GET chứ không POST: chỉ cần biết cổng vào có mở không, mà GET thì không chạm gì tới
	 * dữ liệu. Máy chấm công không liên quan — nó POST bằng địa chỉ riêng của nó.
	 */
	public static function chan_doan() {
		$url = self::url();
		if ( $url === '' ) { return array( 'ok' => false, 'error' => 'Chưa khai địa chỉ /exec.' ); }

		if ( ! preg_match( '#/s/([^/]+)/(exec|dev)#', $url, $m ) ) {
			return array( 'ok' => false, 'error' => 'Địa chỉ không có dạng .../s/<mã triển khai>/exec.' );
		}
		$ma = $m[1];

		$dang = array(
			'rút gọn (gọi ẩn danh được)' => 'https://script.google.com/macros/s/' . $ma . '/exec',
		);
		/* Dạng theo tên miền chỉ thử được khi biết tên miền — lấy từ chính địa chỉ anh đã dán,
		   hoặc từ tên miền của site này. */
		$mien = trim( (string) get_option( 'vhcc_exec_mien', '' ) );
		if ( $mien !== '' ) {
			/* Đã nhớ từ lúc cắt — nguồn đúng nhất. */
		} elseif ( preg_match( '#/a/macros/([^/]+)/#', self::url_tho(), $m2 ) ) {
			$mien = $m2[1];
		} else {
			$h = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			$mien = is_string( $h ) ? preg_replace( '/^www\./', '', $h ) : '';
		}
		if ( $mien !== '' ) {
			$dang[ 'theo tên miền ' . $mien ] = 'https://script.google.com/a/macros/' . $mien
				. '/s/' . $ma . '/exec';
		}

		$ra = array();
		foreach ( $dang as $ten => $u ) {
			$r = wp_remote_get( $u, array( 'timeout' => 25, 'redirection' => 5 ) );
			if ( is_wp_error( $r ) ) {
				$ra[] = array( 'ten' => $ten, 'ma' => 0, 'ket' => 'không gọi được: ' . $r->get_error_message() );
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $r );
			$body = (string) wp_remote_retrieve_body( $r );
			$ket  = 'HTML lạ';
			if ( stripos( $body, 'accounts.google.com' ) !== false || stripos( $body, 'Sign in' ) !== false
				|| stripos( $body, 'ServiceLogin' ) !== false ) {
				$ket = 'ĐÒI ĐĂNG NHẬP Google';
			} elseif ( $code === 400 ) {
				$ket = 'Google chối ở cổng vào (400) — yêu cầu chưa tới script';
			} elseif ( $code === 200 ) {
				$ket = 'TRẢ VỀ TRANG CỦA APP (' . strlen( $body ) . ' byte) — cổng vào mở';
			}
			$ra[] = array( 'ten' => $ten, 'ma' => $code, 'ket' => $ket );
		}

		/* Kết luận — chỗ này là toàn bộ giá trị của phép chẩn đoán. Bảng số liệu mà không kèm
		   câu "vậy phải làm gì" thì lại thành một việc nữa để đoán. */
		$mo    = false;
		$doi_dn = false;
		foreach ( $ra as $x ) {
			if ( strpos( $x['ket'], 'cổng vào mở' ) !== false ) { $mo = true; }
			if ( strpos( $x['ket'], 'ĐÒI ĐĂNG NHẬP' ) !== false ) { $doi_dn = true; }
		}
		if ( $mo ) {
			$kl = 'Địa chỉ TỐT — cổng vào của Google mở. Lỗi nằm ở bên trong: chưa dán '
				. 'CauNoiChamCong.gs, hoặc WEB_KEY chưa khớp, hoặc dán rồi mà chưa Deploy → New version.';
		} elseif ( $doi_dn ) {
			$kl = 'BẢN TRIỂN KHAI BỊ GIỚI HẠN — nó đòi người gọi đăng nhập, mà WordPress gọi '
				. 'máy-với-máy. Vào Apps Script → Deploy → Manage deployments → bản đang chạy → ✏️ → '
				. '"Who has access" = <b>Anyone</b> → Deploy. Máy chấm công KHÔNG bị ảnh hưởng: '
				. 'chúng cũng gọi ẩn danh nên Anyone là đúng cái chúng cần.';
		} else {
			$kl = 'Cả hai dạng đều bị chối ở cổng vào — mã triển khai này không tồn tại, hoặc nó '
				. 'không phải bản triển khai <b>Web app</b>. Lấy lại địa chỉ ở Deploy → Manage '
				. 'deployments, đúng dòng có chữ Web app, và so với địa chỉ máy chấm công đang dùng.';
		}
		return array( 'ok' => true, 'ma_trien_khai' => $ma, 'thu' => $ra, 'ket_luan' => $kl );
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
