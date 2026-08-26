<?php
/**
 * TRANG QUẢN TRỊ NGOÀI WEB — làm mọi việc mà KHÔNG cần vào wp-admin.
 *
 * Anh Thắng: *"Cho ra web để dễ thao tác được không"* và *"mọi việc anh thao tác trên web giao
 * diện bên ngoài hết, không làm bên trong wp-admin"*.
 *
 * Vì sao đáng làm: wp-admin đòi một tài khoản WordPress. Quản lý cửa hàng không có, và cũng
 * không nên có — tài khoản wp-admin mở ra cả website, chứ không riêng chấm công. Trang này gác
 * bằng ĐÚNG cổng PIN của hệ thống chấm công, nên ai đang đăng nhập được /cham-cong là dùng được,
 * không phát thêm tài khoản nào.
 *
 * 🔴 CHỈ ADMIN / QUẢN LÝ. Hồ sơ nhân sự có CCCD, số tài khoản, lương — mở cho Kế toán cơ sở là
 *    mở cả bảng lương của người khác.
 *
 * 🔴 KHÔNG BAO GIỜ IN PIN RA. Trang này chạy ngoài internet; ảnh chụp màn hình đi khắp nơi.
 *    Bảng chỉ in SỐ CHỮ SỐ, đúng luật của màn Cài đặt.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Web {

	const COOKIE = 'vhcc_qt';
	/** Vai trò được vào trang này. Hẹp hơn cổng /cham-cong — đây là hồ sơ, không phải bảng công. */
	const VAI_TRO = array( 'Admin', 'Quản lý' );

	public static function slug() {
		$s = get_option( 'vhcc_slug_qt' );
		$s = $s ? sanitize_title( $s ) : 'quan-tri-cham-cong';
		return $s ? $s : 'quan-tri-cham-cong';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcc_qt', '1', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcc_qt=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function query_vars( $v ) { $v[] = 'vhcc_qt'; return $v; }

	public static function maybe_render() {
		$is = ( (int) get_query_var( 'vhcc_qt' ) === 1 );
		if ( ! $is && isset( $_GET['vhcc_qt'] ) && '1' === $_GET['vhcc_qt'] ) { $is = true; }
		if ( ! $is ) { return; }
		nocache_headers();
		self::phuc_vu();
		exit;
	}

	// ======================================================================= phiên

	/**
	 * Người đang xem, hoặc null.
	 *
	 * Phiên nằm ở COOKIE HttpOnly chứ không phải localStorage: trang này là HTML dựng sẵn ở máy
	 * chủ, mà localStorage thì máy chủ không đọc được. HttpOnly cũng có nghĩa JavaScript không
	 * đọc được token — một lỗi XSS ở đâu đó trên website không lấy được phiên của trang này.
	 */
	public static function toi() {
		$tok = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
		if ( '' === $tok ) { return null; }
		$u = VHCC_Auth::user_by_token( $tok );
		if ( ! $u ) { return null; }
		/* ⚠️ user_by_token trả khoá `role`/`name`/`coso` — KHÔNG phải `vai_tro`/`ten` như cột
		   trong bảng. Đọc nhầm tên khoá thì vai trò ra rỗng, và trang này chối SẠCH mọi người
		   kể cả Admin, mà không báo gì khác ngoài màn đăng nhập. */
		$vt = isset( $u['role'] ) ? (string) $u['role'] : '';
		if ( ! in_array( $vt, self::VAI_TRO, true ) ) { return null; }
		return $u;
	}

	/**
	 * Ai VÀO được trang này — rộng hơn `toi()`.
	 *
	 * 🔴 VÌ SAO PHẢI TÁCH LÀM HAI. Trang này ban đầu chỉ có màn HỒ SƠ + TÀI KHOẢN, nên `toi()`
	 * gác thẳng ở cửa: không phải Admin/Quản lý là bị đá về màn đăng nhập. Nay có thêm màn BẢNG
	 * CHẤM CÔNG — thứ Cửa hàng trưởng phải xem hằng ngày, và chỉ xem CƠ SỞ CỦA MÌNH.
	 * Giữ nguyên cửa cũ thì họ không vào nổi; nới cửa cũ ra thì họ vào luôn được màn Hồ sơ, tức
	 * là sửa được lương và cấp được PIN cho cả chuỗi. Nên: cửa VÀO rộng, cửa TỪNG MÀN hẹp.
	 *
	 * ⚠️ Không nới thêm gì so với hệ chấm công: `user_by_token` vốn đã lọc theo
	 *    `VHCC_Auth::vai_tro_vao()`. Cửa hàng trưởng chỉ vào được khi anh Thắng TÍCH họ ở màn
	 *    Cài đặt — quyết định đó nằm trên màn hình, không nằm trong một dòng mã em tự đổi.
	 */
	public static function nguoi_vao() {
		$tok = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
		if ( '' === $tok ) { return null; }
		return VHCC_Auth::user_by_token( $tok );
	}

	/**
	 * Người này có được vào màn HỒ SƠ / TÀI KHOẢN không.
	 *
	 * 🔴 Trước đây so tên vai trò với một mảng cứng trong mã (`['Admin','Quản lý']`) — nên Kế
	 *    toán bị chối, dù anh Thắng chốt kế toán *"full quyền ngoài admin"*. Nay hỏi bảng vai:
	 *    thêm/bớt quyền là sửa MỘT bảng, không phải đi tìm từng mảng cứng nằm rải trong mã.
	 */
	public static function co_ho_so( $u ) {
		return VHCC_Vai::duoc( $u, 'ho_so' );
	}

	/**
	 * Mở phiên trang quản trị cho một thẻ đã phát ở nơi khác.
	 *
	 * Dùng khi người ta đăng nhập ở TRẠM: cùng một thẻ, nên bấm sang trang quản trị là vào
	 * thẳng, không gõ PIN lần hai. Không có bước này thì "gộp một trang" chỉ là cái liên kết —
	 * bấm vào vẫn rơi ra màn PIN, và người dùng vẫn thấy hai hệ thống rời nhau.
	 *
	 * ⚠️ Chỉ nhận thẻ do chính hệ phát ra (kiểm bằng `user_by_token`). Nhận bừa là ai gửi một
	 *    chuỗi 64 ký tự cũng mở được phiên.
	 */
	public static function mo_phien( $tok ) {
		$tok = (string) $tok;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $tok ) ) { return false; }
		if ( ! VHCC_Auth::user_by_token( $tok ) ) { return false; }
		self::dat_cookie( $tok );
		return true;
	}

	private static function dat_cookie( $tok, $song = true ) {
		$tuoi = $song ? ( time() + 12 * 3600 ) : ( time() - 3600 );
		$args = array(
			'expires'  => $tuoi,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,          // JavaScript KHÔNG đọc được
			'samesite' => 'Lax',         // trang khác POST sang không mang theo phiên
		);
		if ( version_compare( PHP_VERSION, '7.3', '>=' ) ) {
			setcookie( self::COOKIE, $song ? $tok : '', $args );
		} else {
			setcookie( self::COOKIE, $song ? $tok : '', $tuoi, '/', '', is_ssl(), true );
		}
	}

	/**
	 * Chữ ký chống giả mạo biểu mẫu.
	 *
	 * Cookie đã SameSite=Lax nên POST từ trang khác không mang phiên sang, nhưng SameSite là
	 * hàng rào của TRÌNH DUYỆT — trình duyệt cũ không có nó. Thêm một chữ ký buộc vào chính
	 * token thì hàng rào nằm ở máy chủ, không phụ thuộc trình duyệt của người dùng.
	 */
	public static function chu_ky( $tok ) {
		return hash_hmac( 'sha256', 'vhcc-qt|' . (string) $tok, wp_salt( 'nonce' ) );
	}

	private static function chu_ky_dung() {
		$tok = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
		$gui = isset( $_POST['ky'] ) ? (string) wp_unslash( $_POST['ky'] ) : '';
		return ( '' !== $tok && '' !== $gui && hash_equals( self::chu_ky( $tok ), $gui ) );
	}

	// ======================================================================= phục vụ

	public static function phuc_vu() {
		/* Cửa VÀO rộng — xem `nguoi_vao()`. Màn nào hẹp thì chính màn đó gác. */
		$toi = self::nguoi_vao();

		/* Đăng xuất xử trước mọi thứ. */
		if ( isset( $_POST['viec'] ) && 'thoat' === $_POST['viec'] ) {
			self::dat_cookie( '', false );
			self::ve( self::url() );
		}

		if ( ! $toi ) { self::trang_dang_nhap(); return; }

		/* 🔴 TẢI TỆP XỬ TRƯỚC MỌI THỨ KHÁC — trước cả `trang_chinh()`, vì gửi tệp đòi đặt
		   header, mà header chỉ đặt được khi CHƯA in ra một byte nào. Để nhánh này xuống dưới là
		   PHP báo "headers already sent" và trình duyệt nhận về một tệp .xlsx có lẫn cả trang
		   HTML ở đầu — Excel mở ra báo hỏng, mà chẳng ai đoán được vì sao. */
		if ( isset( $_GET['xuat'] ) ) { self::xuat_tep( $toi ); }

		/* 🔴 POST → CHUYỂN HƯỚNG → GET. Anh Thắng: *"cứ bấm F5 là nó reset về ban đầu"*, và
		   trình duyệt hiện hộp "Confirm Form Resubmission".
		   Trước đây bấm Lưu là POST rồi VẼ THẲNG kết quả, nên địa chỉ trên thanh vẫn là địa chỉ
		   trần: F5 là gửi lại nguyên cái POST đó (lưu lại lần nữa, hoặc nạp lại cả file .csv),
		   và mọi bộ lọc — cơ sở, ô Tìm, trạng thái — biến mất vì chúng nằm ở query mà POST không
		   mang theo. Nay: làm việc xong thì CẤT kết quả, chuyển hướng về đúng địa chỉ CÓ BỘ LỌC,
		   rồi mới vẽ. F5 chỉ tải lại một trang GET — không lặp lại việc gì, không mất bộ lọc. */
		if ( ! empty( $_POST ) && isset( $_POST['viec'] ) ) {
			$bao = self::chu_ky_dung()
				? self::lam_viec( sanitize_text_field( wp_unslash( $_POST['viec'] ) ), $toi )
				: array( array( 'loi' => 'Phiên đã hết hoặc biểu mẫu không hợp lệ. Tải lại trang rồi làm lại.' ) );
			self::cat_bao( $bao );
			self::ve( self::url_hien() );
		}
		self::trang_chinh( $toi, array() );
	}

	/**
	 * Gửi tệp .xlsx về trình duyệt. Không quay lại — hoặc gửi tệp rồi thoát, hoặc vẽ một trang
	 * báo lỗi rồi thoát.
	 */
	private static function xuat_tep( $toi ) {
		$loai = sanitize_text_field( wp_unslash( $_GET['xuat'] ) );
		$cs   = isset( $_GET['ccs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['ccs'] ) ) : '';
		$th   = isset( $_GET['cth'] ) ? sanitize_text_field( wp_unslash( $_GET['cth'] ) ) : '';
		if ( '' === $th ) { $th = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		$chan = self::vi_sao_khong_xuat( $toi, $loai, $cs );
		if ( '' !== $chan ) { self::loi_xuat( $chan ); }

		$b = VHCC_Cham::bang_cham_cong( $toi, $cs, $th );
		if ( empty( $b['ok'] ) ) { self::loi_xuat( $b['error'] ); }
		if ( empty( $b['hang'] ) ) {
			self::loi_xuat( 'Tháng ' . $b['thang'] . ' chưa có dữ liệu chấm công nào ở ' . $cs
				. ' — không có gì để xuất.' );
		}

		$noi = VHCC_Xuat::xlsx( VHCC_Ca::to_xuat( $b, $cs ) );
		if ( null === $noi ) { self::loi_xuat( 'Không dựng được tệp .xlsx.' ); }

		/* Tên tệp chỉ giữ chữ/số/gạch — dấu tiếng Việt và khoảng trắng trong `Content-Disposition`
		   là mỗi trình duyệt đặt tên một kiểu, có cái cắt cụt ngay chỗ dấu cách. */
		$ten = 'cong-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $cs ) . '-' . $b['thang'] . '.xlsx';
		VHCC_Xuat::gui( $ten, $noi );
	}

	/**
	 * Người này có tải được tệp không? '' = được, hoặc câu từ chối.
	 *
	 * ⚠️ Tách ra khỏi `xuat_tep` vì hàm kia luôn kết bằng `exit` — gọi nó trong bộ thử là GIẾT
	 *    luôn cả lượt chạy, nên phần gác cửa sẽ vĩnh viễn không có phép thử nào. Một cửa không
	 *    thử được là một cửa sớm muộn hở.
	 */
	public static function vi_sao_khong_xuat( $toi, $loai, $cs ) {
		if ( 'ca' !== $loai ) { return 'Không biết xuất kiểu "' . $loai . '".'; }
		if ( ! VHCC_Vai::duoc( $toi, 'cong_coso' ) ) {
			return 'Xuất bảng công cần quyền Cửa hàng trưởng trở lên.';
		}
		$cs = VHCC_NhanSu::chuan_coso( $cs );
		if ( '' === $cs ) { return 'Chưa chọn cơ sở.'; }
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return 'Không có quyền cơ sở này.'; }
		if ( ! VHCC_Xuat::co_xlsx() ) {
			return 'Máy chủ này thiếu phần mở rộng ZipArchive của PHP nên không dựng được tệp '
				. '.xlsx. Nhờ bên hosting bật `php-zip` giúp — bật xong là nút này chạy ngay, '
				. 'không phải cài lại gì.';
		}
		return '';
	}

	private static function loi_xuat( $loi ) {
		echo self::dau( 'Không xuất được' );
		echo '<div class="bo" style="max-width:560px;padding-top:40px"><div class="the">';
		echo '<h2>Không xuất được tệp</h2>';
		echo '<div class="bao loi">' . esc_html( $loi ) . '</div>';
		echo '<p><a class="nut chinh" href="' . esc_url( self::url() ) . '">Quay lại</a></p>';
		self::dong_trang( 2 );
		exit;
	}

	/**
	 * ĐÓNG TRANG — chân trang công ty rồi mới tới mấy thẻ đóng.
	 *
	 * 🔴 MỘT CHỖ ĐÓNG, KHÔNG BẢY CHỖ. Trước đây mỗi màn tự `echo '</div></body></html>'` —
	 *    bảy dòng giống hệt nhau nằm rải rác. Thêm chân trang kiểu ấy là phải sửa bảy chỗ và
	 *    quên một chỗ, rồi đúng cái màn bị quên thì thiếu thông tin công ty mà chẳng ai để ý.
	 *    `test-cham-cong.php` canh: trong tệp này KHÔNG còn dòng `</body></html>` nào ngoài đây.
	 *
	 * @param int $so_div Số thẻ <div> còn phải đóng (màn báo lỗi xuất tệp lồng sâu hơn một tầng).
	 */
	private static function dong_trang( $so_div = 1 ) {
		echo str_repeat( '</div>', max( 0, (int) $so_div ) );
		/* ⚠️ Gác `method_exists` cùng hàm với lời gọi — xem `tools/test/kiem-goi-cheo.php`.
		   `VHCC_Cty` cùng plugin nên chắc chắn có, nhưng ai đó gỡ tệp ra khỏi bản cài thì
		   trang vẫn phải chạy: thiếu chân trang là thiếu một đoạn chữ, không phải trắng trang. */
		if ( class_exists( 'VHCC_Cty' ) && method_exists( 'VHCC_Cty', 'html' ) ) {
			/* 🔴 BỌC TRONG `.bo`. Anh Thắng 26/08: *"bị lệch"* — chân trang in ra SAU khi đã đóng
			   `.bo` nên nó nằm ngoài khung, dính sát mép trái màn hình trong khi cả trang còn
			   lại thụt vào. Khung `.bo` là thứ giữ mọi thứ thẳng hàng; ra ngoài nó là lệch. */
			$h_cty = VHCC_Cty::html();
			if ( '' !== $h_cty ) { echo '<div class="bo">' . $h_cty . '</div>'; }
		}
		echo '</body></html>';
	}

	/** Các tham số phải sống sót qua một lượt POST — bộ lọc, ô tìm, màn đang mở. */
	const THAM_SO = array( 'cs', 'q', 'loc', 'sua', 'pin', 'man', 'ccs', 'cth', 'cbp', 'cng', 'cnv', 'ctk' );

	/** Địa chỉ hiện tại KÈM bộ lọc, lấy từ POST (ô ẩn) rồi mới tới GET. */
	private static function url_hien() {
		$them = array();
		foreach ( self::THAM_SO as $k ) {
			$v = '';
			if ( isset( $_POST[ $k ] ) )     { $v = (string) wp_unslash( $_POST[ $k ] ); }
			elseif ( isset( $_GET[ $k ] ) )  { $v = (string) wp_unslash( $_GET[ $k ] ); }
			$v = sanitize_text_field( $v );
			if ( '' !== $v ) { $them[ $k ] = $v; }
		}
		return $them ? add_query_arg( $them, self::url() ) : self::url();
	}

	/** Ô ẩn chở bộ lọc qua một lượt POST — thiếu nó là lưu xong nhảy về danh sách đầy đủ. */
	private static function o_loc() {
		$h = '';
		foreach ( self::THAM_SO as $k ) {
			if ( ! isset( $_GET[ $k ] ) ) { continue; }
			$v = sanitize_text_field( (string) wp_unslash( $_GET[ $k ] ) );
			if ( '' === $v ) { continue; }
			$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $h;
	}

	private static function khoa_bao() {
		$tok = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
		return 'vhcc_qt_bao_' . md5( $tok );
	}

	/** Cất kết quả một lượt làm việc để lượt GET ngay sau đó vẽ ra. */
	private static function cat_bao( $bao ) {
		if ( $bao ) { set_transient( self::khoa_bao(), $bao, 120 ); }
	}

	/** Lấy ra và XOÁ — kết quả chỉ hiện MỘT LẦN, không dính lại ở lần tải trang sau. */
	private static function lay_bao() {
		$b = get_transient( self::khoa_bao() );
		if ( false === $b ) { return array(); }
		delete_transient( self::khoa_bao() );
		return is_array( $b ) ? $b : array();
	}

	private static function ve( $url ) {
		wp_safe_redirect( $url );
		/* Bài kiểm chạy trong CÙNG một tiến trình; `exit` ở đây là giết luôn cả bài kiểm, nên
		   không phép thử nào chạm được vào đường chuyển hướng. Một cái mối hẹp, có tên, hơn là
		   để cả đường đăng nhập không ai thử. */
		if ( defined( 'VHCC_TEST' ) ) { return; }
		exit;
	}

	// ======================================================================= việc

	/**
	 * Việc của màn BẢNG CHẤM CÔNG — mở cho mọi vai vào được trang.
	 * Quyền theo CƠ SỞ vẫn do lớp dưới gác (`VHCC_Cham` gọi `VHCC_NhanSu::co_quyen_coso`), nên
	 * Cửa hàng trưởng chỉ đụng được cơ sở mình. Danh sách này chỉ nói "việc này không cần quyền
	 * hồ sơ", không nói "ai làm cũng được".
	 */
	/**
	 * Việc làm được ở màn Bảng chấm công — tức là KHÔNG cần bậc Kế toán như việc hồ sơ.
	 *
	 * ⚠️ Có tên ở đây KHÔNG phải là được làm. Nó chỉ nói "việc này không thuộc màn Hồ sơ";
	 *    quyền thật vẫn do chính lớp xử lý hỏi `VHCC_Vai` (bù: `cham_bu` bậc 2, nạp công:
	 *    `nap_cong` bậc 3). Hai tầng ấy khác nhau — bỏ tầng sau vì "đã có tầng trước" là để
	 *    Cửa hàng trưởng nạp đè cả tháng công của cơ sở khác.
	 */
	const VIEC_CHAM = array( 'co', 'xu_ly_co', 'bu', 'xem_cong', 'nap_cong', 'ca', 'cach_tinh' );

	private static function lam_viec( $viec, $toi ) {
		$bao = array();

		/* 🔴 DANH SÁCH TRẮNG, MẶC ĐỊNH LÀ CHỐI. Từ khi cửa vào trang được nới ra (xem
		   `nguoi_vao()`), một Cửa hàng trưởng đã có phiên hợp lệ ở đây — mà mọi việc bên dưới đều
		   là việc HỒ SƠ: nạp .csv đè cả sổ nhân sự, cấp PIN, đổi vai trò, xoá hết. Nới cửa mà quên
		   chốt này là mở toang đúng những thứ `VAI_TRO` sinh ra để giữ.
		   Viết theo hướng CHỐI TRƯỚC: thêm một việc mới mà quên khai thì nó bị chối, chứ không
		   lọt. Ngược lại (danh sách đen) thì quên một dòng là mở một cửa, và cửa đó im lặng. */
		if ( ! in_array( $viec, self::VIEC_CHAM, true ) && ! self::co_ho_so( $toi ) ) {
			return array( array( 'loi' => 'Tài khoản ' . ( isset( $toi['name'] ) ? $toi['name'] : '' )
				. ' (' . VHCC_Vai::ten( $toi ) . ') chỉ xem được bảng chấm công. '
				. 'Việc này thuộc màn Hồ sơ — cần bậc Kế toán trở lên.' ) );
		}

		if ( 'co' === $viec ) {
			$r = VHCC_Cham::luu_ghi_chu( $toi, array(
				'coso'   => isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '',
				'ngay'   => isset( $_POST['ngay'] ) ? wp_unslash( $_POST['ngay'] ) : '',
				'ma_nv'  => isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '',
				'ho_ten' => isset( $_POST['ho_ten'] ) ? wp_unslash( $_POST['ho_ten'] ) : '',
				'ghi_chu' => isset( $_POST['ghi_chu'] ) ? wp_unslash( $_POST['ghi_chu'] ) : '',
			) );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã gắn cờ ' . $r['flagId'] . '. Cờ nằm CẠNH giờ chấm, không đè lên — '
					. 'giờ trong bảng vẫn nguyên như máy ghi.' ) );
		}

		if ( 'xu_ly_co' === $viec ) {
			$r = VHCC_Cham::xu_ly_ghi_chu( $toi,
				isset( $_POST['flag_id'] ) ? wp_unslash( $_POST['flag_id'] ) : '',
				isset( $_POST['ket_luan'] ) ? wp_unslash( $_POST['ket_luan'] ) : '' );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã đánh dấu cờ là xử lý xong. Nội dung cờ giữ nguyên, kết luận nối thêm '
					. 'vào cuối — còn đó để tra lại về sau.' ) );
		}

		if ( 'bu' === $viec ) {
			$r = VHCC_Bu::ghi( $toi, array(
				'coso'  => isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '',
				'ngay'  => isset( $_POST['ngay'] ) ? wp_unslash( $_POST['ngay'] ) : '',
				'ma_nv' => isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '',
				'vao'   => isset( $_POST['bu_vao'] ) ? wp_unslash( $_POST['bu_vao'] ) : '',
				'ra'    => isset( $_POST['bu_ra'] ) ? wp_unslash( $_POST['bu_ra'] ) : '',
				'ly_do' => isset( $_POST['ly_do'] ) ? wp_unslash( $_POST['ly_do'] ) : '',
			) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			$chu = array();
			foreach ( $r['daGhi'] as $o => $g ) { $chu[] = ( 'vao' === $o ? 'giờ vào ' : 'giờ ra ' ) . $g; }
			$noi = 'Đã bù ' . implode( ' · ', $chu ) . ' cho ' . $r['maNV'] . ' ngày ' . $r['ngay']
				. '. Lượt bù mang nhãn nguồn "bù" và đã vào sổ nhật ký — xoá không được.';
			if ( $r['boQua'] ) {
				$noi .= ' BỎ QUA ' . implode( ', ', $r['boQua'] ) . ': bù không đè lên giờ đã có.';
			}
			return array( array( 'xong' => $noi ) );
		}

		if ( 'bo_phan' === $viec ) {
			$o = isset( $_POST['bp'] ) ? (array) wp_unslash( $_POST['bp'] ) : array();
			$doi = 0; $loi = array();
			foreach ( $o as $cs_x => $bp_x ) {
				$cs_x = sanitize_text_field( $cs_x );
				$bp_x = sanitize_text_field( is_array( $bp_x ) ? '' : $bp_x );
				/* Chỉ ghi cơ sở THẬT SỰ ĐỔI. Ghi lại hết thì mỗi lượt Lưu là mấy chục lượt ghi
				   vào kho, và nhật ký (nếu sau này có) đầy dòng "đổi từ X sang X". */
				if ( VHCC_Luong::bo_phan_cua( $cs_x ) === ( '' === $bp_x ? VHCC_Luong::BP_CHUA_XEP : $bp_x ) ) { continue; }
				$r = VHCC_NhanSu::xep_bo_phan( $toi, $cs_x, $bp_x );
				if ( empty( $r['ok'] ) ) { $loi[] = $cs_x . ': ' . $r['error']; }
				else { $doi++; }
			}
			if ( $loi ) { return array( array( 'loi' => implode( ' · ', $loi ) ) ); }
			return array( array( 'xong' => $doi
				? 'Đã xếp bộ phận cho ' . $doi . ' cơ sở. Công thức tính công của khối tương ứng '
					. 'áp dụng ngay từ lần xem bảng sau.'
				: 'Không có cơ sở nào đổi bộ phận.' ) );
		}

		if ( 'cong_thuc' === $viec ) {
			$khoi = isset( $_POST['ctk'] ) ? sanitize_text_field( wp_unslash( $_POST['ctk'] ) ) : '';
			$o    = isset( $_POST['ct'] ) ? (array) wp_unslash( $_POST['ct'] ) : array();
			$cfg  = array();
			foreach ( $o as $k => $v ) {
				$k = sanitize_text_field( $k );
				if ( ! array_key_exists( $k, VHCC_Luong::VP_O ) ) { continue; }
				$v = is_array( $v ) ? '' : trim( (string) $v );
				/* Ô danh sách mã: tách ngay ở đây thành mảng. Để chuỗi thì `vp_cfg()` phải tự
				   đoán, và nó đang đoán bằng `explode(',')` — hai nơi tách một thứ. */
				if ( 'ds' === VHCC_Luong::VP_O[ $k ][1] ) {
					$v = preg_split( '/[\s,;]+/', $v, -1, PREG_SPLIT_NO_EMPTY );
					if ( ! $v ) { continue; }        // rỗng = bỏ khai
				}
				$cfg[ $k ] = $v;
			}
			if ( '' === $khoi ) {
				/* Bản CHUNG: ô để trống nghĩa là gì thì `dat_vp_cfg` đã có luật riêng của nó —
				   không tự diễn giải lại ở đây, kẻo hai nơi hiểu khác nhau. */
				$r = VHCC_Luong::dat_vp_cfg( $toi, $cfg, '', '' );
			} else {
				$r = VHCC_Luong::dat_cfg_khoi( $toi, $khoi, $cfg );
			}
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			$cc = VHCC_Luong::ca_chuan( VHCC_Luong::vp_cfg_thu( $khoi ) );
			/* 🔴 Báo lại NGAY con số ra tiền, không chỉ báo "đã lưu". Người vừa đổi mốc bậc thang
			   phải thấy ca chuẩn nay ra mấy công — đó là chỗ sai đắt nhất của cả màn này. */
			return array( array( 'xong' => ( isset( $r['thongBao'] ) ? $r['thongBao'] : 'Đã lưu công thức.' )
				. ' Ca chuẩn của khối này nay: ' . $cc['gio'] . ' tiếng → ' . $cc['cong'] . ' công.' ) );
		}

		if ( 'sua_gio' === $viec ) {
			$r = VHCC_Bu::sua( $toi, array(
				'coso'    => isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '',
				'ngay'    => isset( $_POST['ngay'] ) ? wp_unslash( $_POST['ngay'] ) : '',
				'ma_nv'   => isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '',
				'vao'     => isset( $_POST['sg_vao'] ) ? wp_unslash( $_POST['sg_vao'] ) : '',
				'ra'      => isset( $_POST['sg_ra'] ) ? wp_unslash( $_POST['sg_ra'] ) : '',
				'xoa_vao' => ! empty( $_POST['sg_xoa_vao'] ),
				'xoa_ra'  => ! empty( $_POST['sg_xoa_ra'] ),
				'ly_do'   => isset( $_POST['ly_do'] ) ? wp_unslash( $_POST['ly_do'] ) : '',
			) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			/* Câu báo nói CŨ -> MỚI, không chỉ nói "đã lưu". Người vừa sửa giờ công của người
			   khác phải đọc lại được đúng thứ mình vừa làm, ngay lúc còn nhớ mình định làm gì. */
			$chu = array();
			foreach ( $r['doi'] as $o => $d ) {
				$chu[] = ( 'vao' === $o ? 'giờ vào ' : 'giờ ra ' ) . $d['cu'] . ' → ' . $d['moi'];
			}
			return array( array( 'xong' => 'Đã sửa ' . implode( ' · ', $chu ) . ' cho ' . $r['maNV']
				. ' ngày ' . $r['ngay'] . '. Dòng này nay mang nhãn nguồn "sửa" (thôi tính là lượt '
				. 'máy ghi) và đã vào sổ nhật ký kèm giờ cũ — xoá không được.' ) );
		}

		if ( 'ca' === $viec ) {
			$ds_ca = array();
			$ten_ca = isset( $_POST['ca_ten'] ) ? (array) wp_unslash( $_POST['ca_ten'] ) : array();
			foreach ( $ten_ca as $i => $ten ) {
				$ds_ca[] = array(
					'ten'  => sanitize_text_field( $ten ),
					'tu'   => isset( $_POST['ca_tu'][ $i ] )   ? sanitize_text_field( wp_unslash( $_POST['ca_tu'][ $i ] ) ) : '',
					'den'  => isset( $_POST['ca_den'][ $i ] )  ? sanitize_text_field( wp_unslash( $_POST['ca_den'][ $i ] ) ) : '',
					'tuW'  => isset( $_POST['ca_tuw'][ $i ] )  ? sanitize_text_field( wp_unslash( $_POST['ca_tuw'][ $i ] ) ) : '',
					'denW' => isset( $_POST['ca_denw'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['ca_denw'][ $i ] ) ) : '',
				);
			}
			$r = VHCC_Ca::luu( $toi, isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '', $ds_ca );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => $r['so_ca']
				? 'Đã khai ' . $r['so_ca'] . ' ca cho ' . $r['coSo'] . '. Giờ công tách lại theo ca mới ngay.'
				: 'Đã bỏ khai ca riêng của ' . $r['coSo'] . ' — quay về dùng ca chung.' ) );
		}

		if ( 'cach_tinh' === $viec ) {
			$ct = isset( $_POST['ct'] ) ? (array) wp_unslash( $_POST['ct'] ) : array();
			$sach = array();
			foreach ( $ct as $cs_ct => $kieu_ct ) {
				$sach[ sanitize_text_field( $cs_ct ) ] = sanitize_text_field( $kieu_ct );
			}
			$r = VHCC_Luong::dat_cach_tinh( $toi, $sach );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => 'Đã lưu cách tính: ' . $r['so_khai'] . ' cơ sở khai thẳng, '
				. 'còn lại suy theo bộ phận. Bảng công đọc lại theo cách mới ngay — không giờ chấm '
				. 'nào bị sửa.' ) );
		}

		if ( 'xem_cong' === $viec || 'nap_cong' === $viec ) {
			$f = self::doc_tep();
			if ( empty( $f['ok'] ) ) { return array( array( 'loi' => $f['error'] ) ); }
			/* Cơ sở MỚI gõ tay thắng ô xổ xuống. Vòng tròn có thật: muốn có cơ sở trong danh
			   sách thì phải có dữ liệu, mà muốn nạp dữ liệu thì phải chọn được cơ sở. Anh Thắng
			   26/08: *"nếu chưa có cơ sở cũ chỗ này thì sao"* — đúng, tệp JP_SANBAY không nạp
			   được vì cơ sở ấy chưa từng xuất hiện ở đâu. */
			$cs_nap = isset( $_POST['ccs_moi'] ) ? trim( (string) wp_unslash( $_POST['ccs_moi'] ) ) : '';
			if ( '' === $cs_nap ) {
				$cs_nap = isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '';
			}
			$r = VHCC_NapCong::nap( $toi, $cs_nap, VHCC_NapCong::tach( $f['noi_dung'] ),
				'xem_cong' === $viec, isset( $f['ten'] ) ? $f['ten'] : '' );
			$r['viec'] = $viec;
			return array( $r );
		}

		if ( 'xem_csv' === $viec || 'nap_csv' === $viec ) {
			$f = self::doc_tep();
			if ( empty( $f['ok'] ) ) { return array( array( 'loi' => $f['error'] ) ); }
			$r = VHCC_NapCsv::nap( $f['noi_dung'], 'xem_csv' === $viec,
				isset( $_POST['coso'] ) ? sanitize_text_field( wp_unslash( $_POST['coso'] ) ) : '' );
			$r['viec'] = $viec;
			return array( $r );
		}

		if ( 'lui_csv' === $viec ) {
			$r = VHCC_NapCsv::lui();
			return array( empty( $r['ok'] )
				? array( 'loi' => $r['error'] )
				: array( 'xong' => 'Đã hoàn tác lượt nạp lúc ' . $r['luc'] . ': trả lại ' . (int) $r['ve']
					. ' hồ sơ, xoá ' . (int) $r['xoa'] . ' hồ sơ mới thêm.' ) );
		}

		/* 🔴 XOÁ SẠCH HỒ SƠ. Anh Thắng: *"xoá hết dữ liệu nhân viên xong bổ sung lại từ đầu"*.
		   Đòi gõ đúng chữ, và CHỈ Admin — Quản lý không được xoá cả sổ nhân sự của chuỗi. */
		if ( 'xoa_het' === $viec ) {
			if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
				return array( array( 'loi' => 'Chỉ Admin mới xoá được cả sổ hồ sơ.' ) );
			}
			$go = isset( $_POST['xac_nhan'] ) ? trim( (string) wp_unslash( $_POST['xac_nhan'] ) ) : '';
			if ( 'XOA HET' !== $go ) {
				return array( array( 'loi' => 'Chưa xoá gì. Phải gõ đúng chữ XOA HET (in hoa, không dấu) vào ô xác nhận.' ) );
			}
			global $wpdb;
			$so = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) );
			$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) );
			delete_option( VHCC_NapCsv::O_LUI );   // ảnh chụp cũ không còn nghĩa gì sau khi xoá sạch
			return array( array( 'xong' => 'Đã xoá ' . $so . ' hồ sơ nhân sự. '
				. 'Lượt chấm công, bảng lương và lịch làm KHÔNG bị xoá — chúng gắn theo Mã NV, '
				. 'nạp lại hồ sơ đúng mã là khớp lại như cũ.' ) );
		}

		if ( 'doi_nguon' === $viec ) {
			if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
				return array( array( 'loi' => 'Chỉ Admin mới đổi được nguồn người dùng.' ) );
			}
			$ng = isset( $_POST['nguon'] ) ? sanitize_text_field( wp_unslash( $_POST['nguon'] ) ) : '';
			if ( ! in_array( $ng, array( 'ho_so', 'rieng', 'chung', 'app' ), true ) ) {
				return array( array( 'loi' => 'Nguồn không hợp lệ.' ) );
			}
			/* 🔴 KHÔNG cho tự khoá mình ra ngoài. Đổi sang một nguồn KHÔNG AI vào được thì lần
			   sau hết phiên là không còn đường nào mở lại ngoài wp-admin — đúng vòng tròn vừa
			   gỡ xong. Đếm TRƯỚC khi đổi, chối nếu bằng 0. */
			$thu = VHCC_Auth::users_cua( $ng );
			$dem = 0;
			if ( ! is_wp_error( $thu ) ) {
				$cho = VHCC_Auth::vai_tro_vao();
				foreach ( $thu as $x ) {
					if ( '' !== $x['pin'] && in_array( $x['vaiTro'], $cho, true ) ) { $dem++; }
				}
			}
			if ( ! $dem ) {
				return array( array( 'loi' => 'Không đổi. Nguồn đó hiện KHÔNG AI đăng nhập được — '
					. 'đổi sang là tự khoá mình ra ngoài, hết phiên là không còn đường nào mở lại. '
					. 'Khai PIN và Vai trò cho ít nhất một người trước đã.' ) );
			}
			update_option( 'vhcc_nguon_nguoidung', $ng );
			return array( array( 'xong' => 'Cổng đăng nhập giờ đọc: ' . $ng . ' — ' . $dem
				. ' người đăng nhập được.' ) );
		}

		if ( 'luu_nhiem_vu' === $viec ) {
			update_option( self::O_NHIEM_VU,
				isset( $_POST['ds_nv'] ) ? (string) wp_unslash( $_POST['ds_nv'] ) : '', false );
			return array( array( 'xong' => 'Đã lưu danh sách Nhiệm vụ — '
				. count( self::ds_nhiem_vu() ) . ' mục.' ) );
		}

		if ( 'doi_ma' === $viec ) {
			if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
				return array( array( 'loi' => 'Chỉ Admin mới đổi được Mã NV.' ) );
			}
			$r = VHCC_NhanSu::doi_ma(
				isset( $_POST['ma_cu'] ) ? wp_unslash( $_POST['ma_cu'] ) : '',
				isset( $_POST['ma_moi'] ) ? wp_unslash( $_POST['ma_moi'] ) : '' );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			if ( ! empty( $r['khong_doi'] ) ) {
				return array( array( 'loi' => 'Mã mới trùng mã cũ — không đổi gì.' ) );
			}
			$keo = array();
			foreach ( (array) $r['bang'] as $t => $n ) { $keo[] = $t . ': ' . (int) $n; }
			return array( array( 'xong' => 'Đã đổi ' . $r['cu'] . ' → ' . $r['moi'] . '. '
				. ( $keo ? 'Kéo theo — ' . implode( ' · ', $keo ) . '.' : 'Người này chưa có hàng nào ở bảng khác.' ) ) );
		}

		if ( 'luu_nhieu' === $viec ) {
			$r = self::luu_nhieu();
			$b = array( array( 'xong' => 'Đã lưu ' . $r['luu'] . ' dòng'
				. ( $r['bo_qua'] ? ' (' . $r['bo_qua'] . ' dòng không đổi gì nên không ghi)' : '' ) . '.'
				. ( $r['loi'] ? '' : ' Nhớ bấm "Nạp tài khoản" ở ô 🔑 nếu cổng đang KHÔNG đọc thẳng hồ sơ.' ) ) );
			if ( $r['loi'] ) {
				$b[] = array( 'loi' => count( $r['loi'] ) . ' dòng bị chối: ' . implode( ' · ', $r['loi'] ) );
			}
			return $b;
		}

		if ( 'vai_tro_hang_loat' === $viec ) {
			$vt = isset( $_POST['vt_hl'] ) ? sanitize_text_field( wp_unslash( $_POST['vt_hl'] ) ) : '';
			if ( ! in_array( $vt, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) {
				return array( array( 'loi' => 'Vai trò không hợp lệ.' ) );
			}
			$n = self::vai_tro_hang_loat( $vt );
			return array( array( 'xong' => 'Đã đặt vai trò "' . $vt . '" cho ' . $n
				. ' dòng đang hiện theo bộ lọc.' ) );
		}

		if ( 'sua_hs' === $viec ) {
			$r = self::luu_ho_so();
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'xong' => $r['thong_bao'] ) );
		}

		if ( 'khai_admin' === $viec ) {
			if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
				return array( array( 'loi' => 'Chỉ Admin mới khai được tài khoản Admin khác.' ) );
			}
			$r = VHCC_NguoiDung::khai_admin( isset( $_POST['ten'] ) ? wp_unslash( $_POST['ten'] ) : '' );
			return array( empty( $r['ok'] ) ? array( 'loi' => $r['error'] ) : array( 'pin_moi' => $r ) );
		}

		if ( 'nap_tk' === $viec ) {
			$r = VHCC_NguoiDung::nap_tu_cu( 'ho_so', false,
				isset( $_POST['coso'] ) ? sanitize_text_field( wp_unslash( $_POST['coso'] ) ) : '',
				isset( $_POST['vt'] ) ? sanitize_text_field( wp_unslash( $_POST['vt'] ) ) : '' );
			$r['viec'] = 'nap_tk';
			return array( $r );
		}

		return array( array( 'loi' => 'Việc không rõ.' ) );
	}

	/** Đọc file tải lên. Không lưu lại đâu cả — xem class-vhcc-admin.php cùng lý do. */
	private static function doc_tep() {
		if ( ! isset( $_FILES['tep'] ) || ! is_array( $_FILES['tep'] ) ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn file nào.' );
		}
		$f   = $_FILES['tep'];
		$loi = isset( $f['error'] ) ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $loi ) { return array( 'ok' => false, 'error' => 'Chưa chọn file nào.' ); }
		if ( UPLOAD_ERR_INI_SIZE === $loi || UPLOAD_ERR_FORM_SIZE === $loi ) {
			return array( 'ok' => false, 'error' => 'File lớn hơn mức hosting cho tải lên. '
				. 'Xuất riêng từng cơ sở rồi tải từng file.' );
		}
		if ( UPLOAD_ERR_OK !== $loi ) {
			return array( 'ok' => false, 'error' => 'Tải file lên không xong (mã lỗi ' . $loi . ').' );
		}
		$duong = isset( $f['tmp_name'] ) ? (string) $f['tmp_name'] : '';
		if ( '' === $duong || ! is_uploaded_file( $duong ) ) {
			return array( 'ok' => false, 'error' => 'File tải lên không hợp lệ.' );
		}
		if ( (int) filesize( $duong ) > 8 * 1024 * 1024 ) {
			return array( 'ok' => false, 'error' => 'File lớn hơn 8 MB. Xuất riêng từng cơ sở rồi tải từng file.' );
		}
		$ten = isset( $f['name'] ) ? strtolower( (string) $f['name'] ) : '';
		if ( ! preg_match( '/\.(csv|tsv|txt)$/', $ten ) ) {
			return array( 'ok' => false, 'error' => 'Chỉ nhận .csv / .tsv / .txt. Trong Google Sheets chọn '
				. 'File → Tải xuống → Giá trị được phân tách bằng dấu phẩy (.csv).' );
		}
		$nd = file_get_contents( $duong );
		if ( false === $nd || '' === trim( (string) $nd ) ) {
			return array( 'ok' => false, 'error' => 'File rỗng.' );
		}
		if ( ! mb_check_encoding( $nd, 'UTF-8' ) ) {
			$nd = mb_convert_encoding( $nd, 'UTF-8', 'Windows-1258, Windows-1252, ISO-8859-1' );
		}
		/* Trả cả TÊN TỆP: bộ nạp công dùng nó để đối chiếu với ô cơ sở đang chọn — nạp nhầm cửa
		   hàng là cái sai hoàn toàn im lặng, mà tên tệp thì nói sẵn cơ sở nào. */
		return array( 'ok' => true, 'noi_dung' => (string) $nd,
			'ten' => isset( $f['name'] ) ? sanitize_file_name( (string) $f['name'] ) : '' );
	}

	/**
	 * LƯU CẢ BẢNG MỘT LƯỢT.
	 *
	 * Anh Thắng: *"bấm khai 1 lần và lưu 1 lần được không"*. Ô mang tên `truong[MÃ]` nên một
	 * lượt gửi chở theo cả trăm dòng.
	 *
	 * 🔴 CHỈ GHI DÒNG THẬT SỰ ĐỔI. Ghi đè cả trăm dòng bằng đúng giá trị cũ thì cột `cap_nhat`
	 *    của mọi người nhảy về hôm nay, và sau đó không còn cách nào biết hồ sơ nào mới sửa
	 *    thật. So trước, khác mới ghi.
	 *
	 * 🔴 MỘT DÒNG HỎNG KHÔNG ĐƯỢC LÀM HỎNG CẢ LƯỢT. Chối riêng dòng đó, kêu tên ra, và vẫn lưu
	 *    những dòng còn lại — bắt làm lại từ đầu cả trăm dòng vì một PIN gõ nhầm là cách chắc
	 *    nhất để người ta thôi dùng nút này.
	 */
	private static function luu_nhieu() {
		global $wpdb;
		$bang = VHCC_DB::t( 'nhan_vien' );
		$ten_o = array( 'ho_ten', 'cua_hang', 'coso_phu', 'chuc_vu', 'nhiem_vu', 'vai_tro', 'pin_dang_nhap' );

		/* Gom mã từ mọi ô gửi lên — không tin một ô riêng lẻ nào. */
		$ma_ds = array();
		foreach ( $ten_o as $c ) {
			if ( ! isset( $_POST[ $c ] ) || ! is_array( $_POST[ $c ] ) ) { continue; }
			foreach ( array_keys( $_POST[ $c ] ) as $m ) { $ma_ds[ (string) $m ] = 1; }
		}
		if ( ! $ma_ds ) { return array( 'luu' => 0, 'bo_qua' => 0, 'loi' => array() ); }

		$hien = array();
		foreach ( VHCC_DB::rows( "SELECT * FROM $bang" ) as $r ) { $hien[ (string) $r['ma_nv'] ] = $r; }
		/* PIN nào đang thuộc về ai — để bắt trùng, kể cả trùng giữa hai dòng TRONG CÙNG lượt gửi. */
		$pin_cua = array();
		foreach ( $hien as $m => $r ) {
			if ( '' !== trim( (string) $r['pin_dang_nhap'] ) ) { $pin_cua[ $r['pin_dang_nhap'] ] = $m; }
		}

		$luu = 0; $bo_qua = 0; $loi = array();
		foreach ( array_keys( $ma_ds ) as $ma ) {
			if ( ! isset( $hien[ $ma ] ) ) { continue; }
			$cu  = $hien[ $ma ];
			$ten = trim( (string) $cu['ho_ten'] );
			$ghi = array();

			foreach ( $ten_o as $c ) {
				if ( ! isset( $_POST[ $c ][ $ma ] ) ) { continue; }
				$v = trim( (string) wp_unslash( $_POST[ $c ][ $ma ] ) );

				if ( 'pin_dang_nhap' === $c ) {
					$xoa = ! empty( $_POST['xoa_pin'][ $ma ] );
					$pv  = VHCC_NapCsv::pin( $v );
					if ( '' === $pv ) {
						if ( $xoa ) { $ghi[ $c ] = ''; }   // ô trống = GIỮ NGUYÊN, trừ khi tích xoá
						continue;
					}
					if ( ! preg_match( '/^\d{4,8}$/', $pv ) ) {
						$loi[] = $ten . ': PIN phải 4–8 chữ số';
						continue 2;                        // bỏ CẢ dòng này, không lưu nửa vời
					}
					if ( isset( $pin_cua[ $pv ] ) && $pin_cua[ $pv ] !== $ma ) {
						$k_ten = isset( $hien[ $pin_cua[ $pv ] ] ) ? $hien[ $pin_cua[ $pv ] ]['ho_ten'] : $pin_cua[ $pv ];
						$loi[] = $ten . ': PIN trùng với ' . $k_ten;
						continue 2;
					}
					$ghi[ $c ] = $pv;
					continue;
				}

				if ( 'vai_tro' === $c ) {
					if ( '' !== $v && ! in_array( $v, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) { continue; }
					$ghi[ $c ] = $v;
					continue;
				}
				$ghi[ $c ] = $v;
			}

			/* So với giá trị đang có — khác mới ghi. */
			$khac = array();
			foreach ( $ghi as $c => $v ) {
				if ( (string) $v !== (string) $cu[ $c ] ) { $khac[ $c ] = $v; }
			}
			if ( ! $khac ) { $bo_qua++; continue; }

			if ( isset( $khac['pin_dang_nhap'] ) ) {
				unset( $pin_cua[ (string) $cu['pin_dang_nhap'] ] );
				if ( '' !== $khac['pin_dang_nhap'] ) { $pin_cua[ $khac['pin_dang_nhap'] ] = $ma; }
			}
			$khac['cap_nhat'] = current_time( 'mysql' );
			$wpdb->update( $bang, $khac, array( 'ma_nv' => $ma ) );
			$luu++;
		}
		return array( 'luu' => $luu, 'bo_qua' => $bo_qua, 'loi' => $loi );
	}

	/**
	 * ĐẶT VAI TRÒ CHO MỌI DÒNG ĐANG HIỆN theo bộ lọc.
	 *
	 * ⚠️ PHẢI dựng lại ĐÚNG bộ lọc của lượt xem, không phải "cả bảng". Người dùng đang nhìn 237
	 *    dòng đã lọc và bấm "áp cho 237 dòng"; nếu ngầm áp cho cả 240 thì ba người ngoài bộ lọc
	 *    bị đổi vai trò mà không ai thấy.
	 */
	private static function vai_tro_hang_loat( $vt ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'nhan_vien' );
		$cs   = isset( $_POST['cs'] ) ? sanitize_text_field( wp_unslash( $_POST['cs'] ) ) : '';
		$tim  = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$loc  = isset( $_POST['loc'] ) ? sanitize_text_field( wp_unslash( $_POST['loc'] ) ) : '';

		$dk = array(); $ts = array();
		if ( 'chua_pin' === $loc )     { $dk[] = "pin_dang_nhap=''"; }
		elseif ( 'co_pin' === $loc )   { $dk[] = "pin_dang_nhap<>''"; }
		elseif ( 'chua_vt' === $loc )  { $dk[] = "vai_tro=''"; }
		elseif ( 'chua_vao' === $loc ) {
			$in = array();
			foreach ( VHCC_Auth::vai_tro_vao() as $v ) { $in[] = $wpdb->prepare( '%s', $v ); }
			$dk[] = "( pin_dang_nhap='' OR vai_tro NOT IN (" . implode( ',', $in ) . ') )';
		}
		if ( '' !== $cs ) { $dk[] = 'cua_hang=%s'; $ts[] = $cs; }
		if ( '' !== $tim ) {
			$dk[] = '(ma_nv LIKE %s OR ho_ten LIKE %s OR sdt LIKE %s OR cccd LIKE %s)';
			$nhu  = '%' . $wpdb->esc_like( $tim ) . '%';
			array_push( $ts, $nhu, $nhu, $nhu, $nhu );
		}
		$where = $dk ? ' WHERE ' . implode( ' AND ', $dk ) : '';
		$sql   = "UPDATE $bang SET vai_tro=%s, cap_nhat=%s" . $where;
		array_unshift( $ts, $vt, current_time( 'mysql' ) );
		return (int) $wpdb->query( $wpdb->prepare( $sql, $ts ) );
	}

	/** Lưu một hồ sơ sửa tay. Ô để TRỐNG là xoá ô đó — khác hẳn luật của lượt nạp .csv. */
	private static function luu_ho_so() {
		global $wpdb;
		$ma = isset( $_POST['ma_nv'] ) ? trim( (string) wp_unslash( $_POST['ma_nv'] ) ) : '';
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		$xoa_pin = ! empty( $_POST['xoa_pin'] );
		$ghi = array();
		foreach ( self::COT_SUA as $c ) {
			if ( ! isset( $_POST[ $c ] ) ) { continue; }
			$v = trim( (string) wp_unslash( $_POST[ $c ] ) );
			if ( in_array( $c, VHCC_NapCsv::COT_TIEN, true ) )      { $ghi[ $c ] = VHCC_NapCsv::tien( $v ); }
			elseif ( in_array( $c, VHCC_NapCsv::COT_NGAY, true ) )  { $ghi[ $c ] = VHCC_NapCsv::ngay( $v ); }
			elseif ( 'pin_dang_nhap' === $c || 'pin_may' === $c ) {
				$p = VHCC_NapCsv::pin( $v );
				/* 🔴 Ô PIN TRỐNG = GIỮ NGUYÊN, không phải xoá. Ô này không bao giờ điền sẵn PIN
				   cũ (kẻo một ảnh chụp mất sạch mật khẩu cả chuỗi), nên "trống" là trạng thái
				   BÌNH THƯỜNG của nó — coi trống là xoá thì mỗi lần sửa tên là một người mất
				   đường đăng nhập, mà màn hình vẫn báo "Đã lưu". */
				if ( '' === $p ) {
					if ( 'pin_dang_nhap' === $c && $xoa_pin ) { $ghi[ $c ] = ''; }
					continue;
				}
				if ( ! preg_match( '/^\d{4,8}$/', $p ) ) {
					return array( 'ok' => false, 'error' => 'PIN phải là 4–8 CHỮ SỐ. Đã nhập: "'
						. $p . '". Không lưu gì cả — sửa lại rồi bấm Lưu.' );
				}
				$ghi[ $c ] = $p;
			} elseif ( 'vai_tro' === $c ) {
				/* Rỗng = chưa khai, ghi được (đó là một trạng thái thật). Nhưng giá trị LẠ thì
				   bỏ hẳn — ghi bừa một vai trò hệ thống không hiểu là người đó vừa không đăng
				   nhập được, vừa nhìn như đã khai xong. */
				if ( '' !== $v && ! in_array( $v, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) { continue; }
				$ghi[ $c ] = $v;
			} else { $ghi[ $c ] = $v; }
		}
		if ( ! $ghi ) { return array( 'ok' => false, 'error' => 'Không có gì để lưu.' ); }
		/* 🔴 HAI NGƯỜI CÙNG PIN thì cổng đăng nhập nhận người GẶP TRƯỚC, và nhật ký ghi tên
		   người đó — người kia làm gì cũng mang tên người này. Chặn ngay lúc lưu, và nói ra
		   trùng với AI. */
		if ( ! empty( $ghi['pin_dang_nhap'] ) ) {
			$trung = $wpdb->get_var( $wpdb->prepare(
				'SELECT ho_ten FROM ' . VHCC_DB::t( 'nhan_vien' )
				. ' WHERE pin_dang_nhap=%s AND ma_nv<>%s LIMIT 1', $ghi['pin_dang_nhap'], $ma ) );
			if ( $trung ) {
				return array( 'ok' => false, 'error' => 'PIN này đã cấp cho ' . $trung
					. '. Hai người cùng PIN thì nhật ký không phân biệt được ai làm việc gì. '
					. 'Không lưu gì cả — chọn PIN khác.' );
			}
		}

		$co = $wpdb->get_var( $wpdb->prepare(
			'SELECT ma_nv FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', $ma ) );
		$ghi['cap_nhat'] = current_time( 'mysql' );
		if ( $co ) { $wpdb->update( VHCC_DB::t( 'nhan_vien' ), $ghi, array( 'ma_nv' => $ma ) ); }
		else { $ghi['ma_nv'] = $ma; $wpdb->insert( VHCC_DB::t( 'nhan_vien' ), $ghi ); }

		/* 🔴 NÓI RÕ ĐÃ LÀM GÌ VỚI PIN. Anh Thắng: *"bấm lưu pin, có ghi đã lưu hồ sơ, nhưng
		   không thấy pin đâu"*. PIN CÓ được lưu — chỉ là ô không bao giờ hiện nó ra (cố ý), nên
		   câu "Đã lưu hồ sơ" trống rỗng đọc y như chưa lưu được. Kể đích danh việc đã làm. */
		$them_loi = '';
		if ( isset( $ghi['pin_dang_nhap'] ) ) {
			$them_loi = ( '' === $ghi['pin_dang_nhap'] )
				? ' Đã XOÁ PIN đăng nhập — người này không đăng nhập được nữa.'
				: ' Đã đặt PIN đăng nhập MỚI (' . strlen( $ghi['pin_dang_nhap'] ) . ' số). '
					. 'Ô PIN cố ý không hiện PIN ra, chỉ báo "đang có N số" ở dưới ô — '
					. 'trang này chạy ngoài internet, in PIN ra là một ảnh chụp mất sạch mật khẩu cả chuỗi.';
		}
		if ( ! empty( $ghi['vai_tro'] ) ) {
			$them_loi .= ' Vai trò: ' . $ghi['vai_tro'] . '.';
			if ( ! in_array( $ghi['vai_tro'], VHCC_Auth::vai_tro_vao(), true ) ) {
				$them_loi .= ' ⚠️ Vai trò này KHÔNG vào được trang chấm công.';
			}
		}
		if ( '' !== $them_loi ) {
			$them_loi .= ' Nhớ bấm "Nạp tài khoản" ở ô 🔑 bên trên thì thay đổi mới có hiệu lực ở cổng đăng nhập.';
		}
		return array( 'ok' => true,
			'thong_bao' => ( $co ? 'Đã lưu hồ sơ ' : 'Đã thêm hồ sơ ' ) . $ma . '.' . $them_loi );
	}

	// ======================================================================= vẽ

	private static function dau( $tieu_de ) {
		$h  = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		$h .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
		/* Trang quản trị KHÔNG được để công cụ tìm kiếm ghé vào. */
		$h .= '<meta name="robots" content="noindex, nofollow">';
		$h .= '<title>' . esc_html( $tieu_de ) . '</title><style>' . self::css() . '</style></head><body>';
		return $h;
	}

	private static function css() {
		return ':root{--nen:#f1f5f9;--the:#fff;--vien:#e2e8f0;--chu:#0f172a;--mo:#64748b;'
			. '--xanh:#2563eb;--do:#dc2626;--vang:#f59e0b;--luc:#16a34a}'
			. '*{box-sizing:border-box}'
			. 'body{margin:0;font:15px/1.6 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;'
			. 'background:var(--nen);color:var(--chu)}'
			/* Dùng hết bề ngang màn hình. Bảng hồ sơ có 9 cột; ép vào 1180px là cột nào cũng
			   chật, chữ xuống dòng, và mỗi hàng cao gấp ba. */
			. '.bo{max-width:1760px;margin:0 auto;padding:16px 20px}'
			. 'header{background:var(--the);border-bottom:1px solid var(--vien);position:sticky;top:0;z-index:5}'
			. 'header .bo{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px}'
			. 'h1{font-size:17px;margin:0;flex:1}'
			. '.the{background:var(--the);border:1px solid var(--vien);border-radius:10px;padding:16px;margin:0 0 16px}'
			. '.the h2{font-size:15px;margin:0 0 4px}'
			. '.mo{color:var(--mo);font-size:13px;margin:4px 0}'
			. 'label{display:block;font-size:13px;color:var(--mo);margin:0 0 3px}'
			. 'input,select,textarea{font:inherit;padding:7px 9px;border:1px solid #cbd5e1;'
			. 'border-radius:7px;background:#fff;color:var(--chu);max-width:100%}'
			. 'input:focus,select:focus{outline:2px solid var(--xanh);outline-offset:1px}'
			. '.hang{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}'
			. 'button{font:inherit;font-weight:600;padding:8px 14px;border-radius:7px;border:1px solid #cbd5e1;'
			. 'background:#fff;color:var(--chu);cursor:pointer}'
			. 'button.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. 'button.nguy{background:var(--do);border-color:var(--do);color:#fff}'
			. '.nut{display:inline-block;font-size:14px;font-weight:600;padding:8px 12px;border-radius:7px;'
			. 'border:1px solid #cbd5e1;background:#fff;color:var(--chu);text-decoration:none}'
			. '.nut.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			/* Dải bộ phận: mỗi bộ phận là một LIÊN KẾT kèm số cơ sở đang có. Bộ phận rỗng thì
			   mờ đi — vẫn bấm được (để thấy câu giải thích), nhưng không mời gọi bấm. */
			. '.loc-bp{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-top:10px}'
			. '.nhan-bp{font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;'
			. 'color:var(--mo);margin-right:2px}'
			. '.loc-bp .nut{padding:6px 11px;font-size:13px}'
			. '.loc-bp .nut.trong{opacity:.55}'
			. '.loc-bp .sl{display:inline-block;min-width:18px;text-align:center;border-radius:9px;'
			. 'background:#e2e8f0;color:#475569;font-size:11px;padding:0 5px;margin-left:3px}'
			. '.loc-bp .nut.chinh .sl{background:rgba(255,255,255,.28);color:#fff}'
			. '.luoi{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px}'
			. 'table{border-collapse:collapse;width:100%;font-size:13.5px}'
			. 'th,td{text-align:left;padding:7px 9px;border-bottom:1px solid var(--vien);vertical-align:top}'
			. 'th{background:#f8fafc;font-size:12.5px;color:var(--mo);white-space:nowrap}'
			. '.cuon{overflow-x:auto;-webkit-overflow-scrolling:touch}'
			/* Cột Mã NV DÍNH BÊN TRÁI. Bảng rộng hơn màn hình nên phải cuộn ngang; không ghim
			   cột mã lại thì cuộn sang phải là mất luôn thứ cho biết đang sửa hồ sơ của AI. */
			. '.cuon td:first-child,.cuon th:first-child{position:sticky;left:0;z-index:2;'
			. 'background:var(--the);box-shadow:1px 0 0 var(--vien)}'
			. '.cuon th:first-child{background:#f8fafc}'
			. '.cuon td:last-child{white-space:nowrap}'
			. '.cuon input,.cuon select{padding:6px 8px}'
			. '.bao{border-radius:9px;padding:11px 13px;margin:0 0 12px;border:1px solid}'
			. '.bao.ok{background:#f0fdf4;border-color:#bbf7d0}'
			. '.bao.loi{background:#fef2f2;border-color:#fecaca}'
			. '.bao.canh{background:#fffbeb;border-color:#fde68a}'
			. '.bao ul{margin:6px 0 0 18px;padding:0}'
			. '.cu{color:var(--do);text-decoration:line-through}'
			. '.moi{color:var(--luc);font-weight:600}'
			. '.co{color:var(--luc);font-weight:600}.chua{color:var(--do);font-weight:600}'
			. '.pin-ho{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:15px;letter-spacing:2px;'
			. 'user-select:all;background:#fef3c7;padding:1px 6px;border-radius:5px;color:var(--chu)}'
			. '.pin{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:22px;letter-spacing:4px;'
			. 'user-select:all;background:#fffbeb;padding:6px 12px;border-radius:8px;display:inline-block}'
			/* ---- lưới bảng chấm công: 31 cột ngày, phải nhỏ và dày ---- */
			. 'table.cc{font-size:11.5px}'
			. 'table.cc th,table.cc td{padding:3px 4px;border:1px solid var(--vien);text-align:center;'
			. 'white-space:nowrap;vertical-align:middle}'
			. 'table.cc th.ng{width:34px;min-width:34px;padding:3px 2px}'
			/* Chủ nhật tô nhạt để đếm tuần bằng mắt — nhìn một tháng mà không có mốc thì đếm mãi. */
			. 'table.cc th.cn{background:#fef2f2;color:var(--do)}'
			. 'table.cc th.nay,table.cc td.nay{outline:2px solid var(--xanh);outline-offset:-2px}'
			. 'table.cc td:first-child{text-align:left;min-width:190px;white-space:normal;line-height:1.3}'
			. 'table.cc td.o{color:var(--mo);line-height:1.25}'
			/* Thiếu giờ ra: nền đỏ nhạt. Không dùng MỖI màu chữ — ô còn có chữ "?" để người mù
			   màu và bản in đen trắng vẫn đọc được. */
			. 'table.cc td.hong{background:#fef2f2;color:var(--do);font-weight:600}'
			/* Cả DÒNG thiếu giờ ra ở bảng chi tiết. Luật riêng chứ không dùng chung với `td.hong`
			   ở trên: `td.hong` tô MỘT ô của lưới, còn ở đây cờ nằm trên `<tr>`. Thiếu luật này
			   thì thuộc tính có mà màu không lên — đúng kiểu hỏng không kêu tiếng nào. */
			. 'table.cc tr.hong>td{background:#fef2f2}'
			. 'table.cc td.cco{box-shadow:inset 0 0 0 2px var(--vang)}'
			. 'table.cc td.tong{font-weight:700;background:#f8fafc}'
			. '.duoi{background:#e0e7ff;color:#3730a3;border-radius:4px;padding:0 5px;font-size:11px;font-weight:600}'
			. '.chu-hong{color:var(--do);font-weight:600}.chu-co{color:var(--vang);font-weight:600}'
			/* Lưới Công Văn phòng. Màu ở đây là LÝ DO chứ không phải trang trí, nên mỗi lớp phải
			   đi kèm một câu trong phần chú thích dưới lưới — màu không có chú giải thì người đọc
			   chỉ biết "ô này khác màu", không biết khác vì gì. */
			. 'table.cc td.oc{text-align:center;white-space:nowrap}'
			. 'table.cc td.oc.hong{background:#fef2f2;color:var(--do)}'
			. 'table.cc td.oc.vang{background:#fffbeb;color:#b45309}'
			. 'table.cc td.oc.tim{background:#f5f3ff;color:#6d28d9}'
			. 'table.cc td.oc.luc{background:#f0fdf4;color:#15803d}'
			. 'table.cc td.tong{text-align:right;font-weight:700;background:#f8fafc}'
			. '.chu-luc{color:var(--luc);font-weight:600}'
			. '.k{padding:1px 6px;border-radius:3px;font-size:12px}'
			. '.k.luc{background:#f0fdf4;color:#15803d}.k.tim{background:#f5f3ff;color:#6d28d9}'
			. '.k.vang{background:#fffbeb;color:#b45309}.k.hong{background:#fef2f2;color:var(--do)}'
			/* Màu theo CA. Bốn tông đủ phân biệt mà không chói; ca thứ 5 trở đi quay vòng lại —
			   một cơ sở có hơn bốn ca là chuyện hiếm, và quay vòng vẫn hơn là tất cả cùng trắng. */
			. 'table.cc td.oc.ca1,table.cc th.ca1{background:#eff6ff}'
			. 'table.cc td.oc.ca2,table.cc th.ca2{background:#f0fdf4}'
			. 'table.cc td.oc.ca3,table.cc th.ca3{background:#faf5ff}'
			. 'table.cc td.oc.ca4,table.cc th.ca4{background:#fff7ed}'
			. '.k.ca1{background:#eff6ff;color:#1d4ed8}.k.ca2{background:#f0fdf4;color:#15803d}'
			. '.k.ca3{background:#faf5ff;color:#7e22ce}.k.ca4{background:#fff7ed;color:#c2410c}'
			/* Mã ca nằm DƯỚI số giờ, nhỏ và nhạt hơn: số giờ vẫn là thứ đọc trước, mã ca là thứ
			   liếc thấy. Đảo ngược cỡ chữ là cả lưới trông như một rừng mã. */
			. '.mca{font-size:10px;font-weight:600;opacity:.75;line-height:1.1;margin-top:1px}'
			/* Ô bấm được: đường liên kết phủ KÍN ô, giữ nguyên màu chữ. Chỉ tô nền khi rê chuột
			   — tô sẵn thì cả lưới 600 ô xanh lè, không còn nhìn ra màu theo ca nữa. */
			. 'table.cc a.o-sua{display:block;margin:-3px -4px;padding:3px 4px;color:inherit;'
			. 'text-decoration:none;border-radius:3px}'
			. 'table.cc a.o-sua:hover{background:#1d4ed8;color:#fff;box-shadow:0 0 0 2px #1d4ed8}'
			. 'table.cc a.o-sua:focus-visible{outline:2px solid var(--xanh);outline-offset:1px}'
			/* Ô đang mở để sửa: viền đậm để mắt tìm lại được nó giữa 600 ô. */
			. 'table.cc td.dang-sua{outline:3px solid var(--do);outline-offset:-3px}'
			/* Hàng sửa nội tuyến: nền khác hẳn, và chữ về cỡ thường (lưới đang 11.5px). */
			. 'table.cc tr.hang-sua>td{background:#fffbeb;border:2px solid var(--vang);'
			. 'text-align:left;white-space:normal;padding:10px 12px;font-size:14px}'
			. 'table.cc tr.hang-sua label{font-size:12px}'
			/* Khối thu gọn bằng <details> của chính HTML — không JavaScript. Phải cho `summary`
			   trông ra một cái nút bấm được, kẻo nó nằm im như một dòng chữ và không ai bấm. */
			. 'summary{cursor:pointer;padding:6px 0;font-size:15px;user-select:none}'
			. 'summary::marker{color:var(--xanh)}'
			. 'summary:hover{color:var(--xanh)}'
			/* --- đầu trang --- */
			. '.hieu{flex:1;font-size:16px;text-decoration:none;color:var(--chu)}'
			. '.hieu b{color:var(--xanh)}'
			/* --- trang chào: thẻ việc --- */
			. '.chao{background:linear-gradient(180deg,#f8fafc,var(--the))}'
			. '.the-viec{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px}'
			. '.viec{display:block;padding:13px 14px;border:1px solid var(--vien);border-radius:10px;'
			. 'text-decoration:none;color:var(--chu);background:var(--the)}'
			. '.viec:hover{border-color:var(--xanh);background:#f8fafc}'
			. '.viec b{display:block;font-size:15px;margin-bottom:3px}'
			. '.viec span{display:block;font-size:13px;color:var(--mo);line-height:1.45}'
			. '.viec-chinh{border-color:var(--xanh);background:#eff6ff}'
			. '.viec-chinh b{color:var(--xanh)}'
			. 'input.link{width:100%;min-width:220px;font-size:12px;font-family:ui-monospace,Menlo,Consolas,monospace}'
			/* --- ĐIỆN THOẠI ---
			   Cửa hàng trưởng ở cơ sở phần lớn chỉ có điện thoại. Bảng ngang thì vẫn phải cuộn —
			   một tháng 31 cột không có cách nào nhét vừa màn 5 inch — nhưng MỌI THỨ KHÁC thì
			   không được bắt cuộn ngang: ô lọc xếp dọc, thẻ việc một cột, chữ vừa đọc. */
			. '@media(max-width:640px){'
			. '.bo{padding:10px}h1{font-size:15px}.hieu{font-size:15px}'
			. '.ai{width:100%;order:3;font-size:12px}'
			. '.the{padding:13px;border-radius:9px}'
			. '.hang{gap:8px}.hang>div{flex:1 1 140px}'
			. '.luoi{grid-template-columns:1fr}'
			. '.the-viec{grid-template-columns:1fr}'
			. 'table.cc{font-size:11px}table.cc td:first-child{min-width:140px}'
			. 'input,select,textarea{font-size:16px}'   /* 16px: dưới mức này iPhone tự phóng to trang */
			. '.nut{padding:9px 12px}'
			. '}'
			/* In ra giấy: bỏ nền, bỏ nút, để bảng lọt trang ngang. */
			. '@media print{header,form,.nut{display:none!important}'
			. 'body{background:#fff}.the{border:0;padding:0;margin:0 0 10px}'
			. '.cuon{overflow:visible}table.cc{font-size:9px}}'
			/* Chân trang công ty mang bộ kiểu chữ riêng, tiền tố `cty-`. Ghép vào đây thay vì
			   in thẻ <style> thứ hai giữa trang — một trang một khối kiểu chữ.
			   ⚠️ Gác `method_exists` cùng hàm với lời gọi, xem `tools/test/kiem-goi-cheo.php`. */
			. ( ( class_exists( 'VHCC_Cty' ) && method_exists( 'VHCC_Cty', 'css' ) )
				? VHCC_Cty::css() : '' );
	}

	/**
	 * ĐƯỜNG VÀO BẰNG QUYỀN QUẢN TRỊ WORDPRESS — gỡ thế bí "không PIN nào vào được".
	 *
	 * 🔴 Thế bí có thật, anh Thắng gặp ngay: muốn nạp tài khoản đăng nhập thì phải vào trang
	 *    này; muốn vào trang này thì phải có tài khoản đăng nhập. Vòng tròn, và không có đường
	 *    nào tự mở.
	 *
	 * ⚠️ ĐÂY KHÔNG PHẢI NỚI QUYỀN. Người bấm được nút này là người đang đăng nhập WordPress với
	 *    quyền `manage_options` — tức là người sửa được cả website, cài/gỡ được chính plugin
	 *    này, và đọc thẳng được bảng người dùng trong database. Quyền đó đã CAO HƠN một PIN
	 *    Admin của chấm công. Bắt họ đi vòng qua PIN không thêm một lớp an toàn nào, chỉ thêm
	 *    một vòng tròn không lối ra.
	 *
	 * ⚠️ Vẫn qua nonce: không có nonce thì một trang khác dụ được quản trị viên bấm vào là mở
	 *    sẵn một phiên chấm công.
	 */
	private static function vao_bang_wp() {
		if ( ! is_user_logged_in() || ! current_user_can( VHCC_Admin::CAP ) ) { return false; }
		if ( ! isset( $_POST['vao_wp'] ) ) { return false; }
		check_admin_referer( 'vhcc_vao_wp' );
		$u   = wp_get_current_user();
		$ten = ( $u && ! empty( $u->display_name ) ) ? (string) $u->display_name : 'Quản trị WordPress';
		$tok = VHCC_Auth::phat_token( $ten, 'Admin', '' );
		self::dat_cookie( $tok );
		self::ve( self::url() );
		return true;
	}

	private static function trang_dang_nhap() {
		self::vao_bang_wp();
		$loi = '';
		if ( isset( $_POST['pin'] ) ) {
			$kq = VHCC_Auth::login( (string) wp_unslash( $_POST['pin'] ) );
			if ( ! empty( $kq['ok'] ) ) {
				/* ⚠️ Gác bằng CÙNG danh sách với `user_by_token` (`vai_tro_vao`), không phải
				   `VAI_TRO`. Hai chỗ gác lệch nhau thì có vai vào được cửa này rồi bị `toi()`
				   đá ra ở lượt sau — người dùng thấy "đăng nhập xong lại về màn đăng nhập",
				   không một câu giải thích. Ai vào được thì thấy màn nào là việc của
				   `trang_chinh`, không phải của cửa này. */
				if ( in_array( (string) $kq['role'], VHCC_Auth::vai_tro_vao(), true ) ) {
					self::dat_cookie( $kq['token'] );
					self::ve( self::url() );
				}
				/* PIN ĐÚNG nhưng không đủ quyền: nói rõ, đừng báo "PIN sai" — người ta gõ lại
				   mười lần rồi tự khoá mình. */
				$loi = 'Tài khoản ' . $kq['name'] . ' (' . $kq['role'] . ') không được vào hệ thống chấm công. '
					. 'Vai trò vào được: ' . implode( ' · ', VHCC_Auth::vai_tro_vao() ) . '.';
			} else {
				$loi = isset( $kq['error'] ) ? $kq['error'] : 'PIN không đúng.';
			}
		}
		echo self::dau( 'Quản trị Chấm Công' );
		echo '<div class="bo" style="max-width:420px;padding-top:56px">';
		echo '<div class="the">';
		echo '<h2>Quản trị Chấm Công</h2>';
		echo '<p class="mo">Đăng nhập bằng PIN chấm công. Vai trò vào được: <b>'
			. esc_html( implode( ' · ', VHCC_Auth::vai_tro_vao() ) ) . '</b>. '
			. 'Màn <b>Hồ sơ &amp; tài khoản</b> chỉ Admin / Quản lý mở được.</p>';
		if ( '' !== $loi ) { echo '<div class="bao loi">' . esc_html( $loi ) . '</div>'; }

		/* CHƯA AI ĐĂNG NHẬP ĐƯỢC thì nói thẳng ra, kèm đường vào — đừng để người ta gõ PIN mãi
		   vào một danh sách vốn rỗng. */
		$vao = 0;
		$u_all = VHCC_Auth::users();
		if ( ! is_wp_error( $u_all ) ) {
			$cho = VHCC_Auth::vai_tro_vao();
			foreach ( $u_all as $x ) {
				if ( '' !== $x['pin'] && in_array( $x['vaiTro'], $cho, true ) ) { $vao++; }
			}
		}
		$la_qt = ( is_user_logged_in() && current_user_can( VHCC_Admin::CAP ) );

		if ( ! $vao ) {
			echo '<div class="bao canh"><b>Chưa có tài khoản nào đăng nhập được.</b> '
				. 'Nguồn người dùng đang đặt là <b>' . esc_html( VHCC_Auth::nguon() ) . '</b>, và trong đó '
				. 'không ai vừa có PIN vừa mang vai trò được vào ('
				. esc_html( implode( ' · ', VHCC_Auth::vai_tro_vao() ) ) . ').'
				. ( $la_qt ? ' Bấm nút bên dưới để vào bằng chính quyền quản trị WordPress của anh.' : '' )
				. '</div>';
		}

		/* Đang đăng nhập WordPress với quyền quản trị -> vào thẳng. Xem vao_bang_wp() vì sao. */
		if ( $la_qt ) {
			echo '<form method="post" style="margin:0 0 14px">';
			wp_nonce_field( 'vhcc_vao_wp' );
			echo '<button class="chinh" name="vao_wp" value="1" style="width:100%">'
				. 'Vào bằng tài khoản WordPress</button>';
			echo '<p class="mo" style="text-align:center;margin:6px 0 0">Anh đang đăng nhập '
				. 'wp-admin ở trình duyệt này — quyền đó đã cao hơn một PIN Admin.</p></form>';
			echo '<hr style="border:0;border-top:1px solid var(--vien);margin:0 0 14px">';
		}

		echo '<form method="post"><label for="pin">PIN</label>'
			. '<input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off" '
			. 'autofocus required style="width:100%;font-size:19px;letter-spacing:3px;text-align:center">'
			. '<button class="chinh" style="width:100%;margin-top:10px">Vào</button></form>';
		self::dong_trang( 2 );
	}

	private static function trang_chinh( $toi, $bao ) {
		global $wpdb;
		$ky  = self::chu_ky( (string) $_COOKIE[ self::COOKIE ] );
		$la  = VHCC_Vai::duoc( $toi, 'he_thong' );   // khối hệ thống: nguồn người dùng, xoá sạch, khai Admin
		$bang = VHCC_DB::t( 'nhan_vien' );
		$tong = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );

		$GLOBALS['VHCC_FORM_ROI'] = '';

		echo self::dau( 'Quản trị Chấm Công' );
		echo '<header><div class="bo">'
			. '<a class="hieu" href="' . esc_url( self::url() ) . '"><b>K&amp;H</b> Chấm công</a>'
			. '<span class="mo ai">' . esc_html( $toi['name'] ) . ' · '
			. esc_html( VHCC_Vai::ten( $toi ) ) . '</span>'
			. '<form method="post" style="margin:0"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<button name="viec" value="thoat">Thoát</button></form></div></header>';
		echo '<div class="bo">';

		$bao = array_merge( self::lay_bao(), (array) $bao );
		foreach ( $bao as $b ) { self::ve_bao( $b ); }

		/* ------------------------------------------------------------------ chọn màn
		   Thanh màn dựng theo QUYỀN, không theo tên vai trò: mỗi người chỉ thấy những màn mình
		   mở được, nên không có chuyện bấm vào một mục rồi bị chối. Người chỉ có đúng một màn
		   thì không vẽ thanh — một cái thanh một mục chỉ tổ chiếm chỗ. */
		$ds_man = self::man_cua( $toi );
		$man    = isset( $_GET['man'] ) ? sanitize_text_field( wp_unslash( $_GET['man'] ) ) : '';
		/* 🔴 `?man=vp` LÀ ĐƯỜNG CŨ, VẪN PHẢI MỞ ĐƯỢC. Anh Thắng đã gửi link kèm `man=vp` cho các
		   bộ phận rồi; gộp tab mà để đường cũ rơi về màn mặc định thì người nhận bấm vào không
		   thấy thứ người gửi bảo họ xem, và chẳng ai đoán ra vì sao. Quy về tab đã gộp. */
		if ( 'vp' === $man ) { $man = 'cham'; }
		if ( ! isset( $ds_man[ $man ] ) ) { $man = self::man_mac_dinh( $ds_man ); }
		if ( count( $ds_man ) > 1 ) { self::thanh_man( $man, $ds_man ); }

		if ( 'nha' === $man ) {
			self::the_nha( $toi );
			self::dong_trang();
			return;
		}

		if ( 'cong_toi' === $man ) {
			self::the_cong_toi( $toi );
			self::dong_trang();
			return;
		}

		if ( 'cham' === $man ) {
			self::the_bang_cham( $ky, $toi );
			self::dong_trang();
			return;
		}

		$sua = isset( $_GET['sua'] ) ? sanitize_text_field( wp_unslash( $_GET['sua'] ) ) : '';
		if ( '' !== $sua ) {
			/* Màn sửa vẫn cần mấy danh sách xổ ra của bảng — dựng luôn ở đây. */
			$b_hs = VHCC_DB::t( 'nhan_vien' );
			echo self::goi_y( 'dl_ch', "SELECT DISTINCT cua_hang AS v FROM $b_hs WHERE cua_hang<>''" );
			echo self::goi_y( 'dl_cv', "SELECT DISTINCT chuc_vu  AS v FROM $b_hs WHERE chuc_vu<>''" );
			echo self::goi_y( 'dl_nv', "SELECT DISTINCT nhiem_vu AS v FROM $b_hs WHERE nhiem_vu<>''", true,
				array( 'Nhân Viên', 'Admin', 'Cửa Hàng Trưởng', 'Kế Toán' ) );
			echo self::goi_y( 'dl_cp', "SELECT DISTINCT coso_phu AS v FROM $b_hs WHERE coso_phu<>''", true );
			self::the_sua_ho_so( $ky, $sua, $la );
			self::dong_trang();
			return;
		}

		self::the_nap_csv( $ky, $tong );
		self::the_tai_khoan( $ky, $la );
		self::the_ho_so( $ky, $toi );
		if ( $la ) { self::the_xoa_het( $ky, $tong ); }

		self::dong_trang();
	}

	/**
	 * Những màn người này mở được, theo thứ tự hiện ra.
	 *
	 * 🔴 MỘT NƠI DUY NHẤT quyết định thanh màn VÀ quyết định `?man=` nào hợp lệ. Hai danh sách
	 *    (một để vẽ nút, một để gác) là kiểu lỗi mở cửa sau: nút không hiện, nhưng gõ thẳng
	 *    `?man=ho_so` lên thanh địa chỉ thì vẫn vào. Ở đây danh sách vẽ nút CHÍNH LÀ danh sách
	 *    gác, nên không có cửa sau nào để quên.
	 *
	 * ⚠️ Luôn trả về ít nhất một màn. Rỗng thì `key()` cho null và trang ra trắng — người dùng
	 *    thấy một trang trắng chứ không thấy câu giải thích nào.
	 */
	/**
	 * Màn mở ra khi chưa chọn gì — khai THẲNG THỨ TỰ ƯU TIÊN, không suy từ vị trí trong danh sách.
	 *
	 * 🔴 Bản trước lấy `end( array_keys( $ds_man ) )` với lý do "màn cuối là màn cao nhất". Cách
	 *    đó trộn HAI thứ khác nhau — thứ tự HIỆN trên thanh và bậc QUYỀN — và nó im lặng cho tới
	 *    khi có hai màn cùng bậc: thêm tab "Bảng công tháng" (cùng bậc `cong_coso` với "Bảng chấm
	 *    công") là Cửa hàng trưởng đăng nhập vào bỗng rơi thẳng vào tab mới, chỉ vì nó được khai
	 *    sau một dòng. Không có gì báo, và thanh nút thì trông y hệt.
	 *
	 * Nay: thứ tự ưu tiên nằm ở đây, thành chữ. Thêm màn mới không đổi màn mặc định của ai —
	 * trừ khi cố ý khai nó vào danh sách này.
	 */
	/* 🔴 'nha' đứng ĐẦU: ai đăng nhập vào cũng rơi vào trang chào trước.
	   Anh Thắng 26/08: *"làm lại giao diện web chuẩn để anh gửi các bộ phận"* — người bộ phận mở
	   đường link ra mà rơi thẳng vào một bảng số thì không biết mình được làm gì và bấm vào đâu.
	   Trang chào nói ra trước, rồi mới tới bảng. */
	const MAN_UU_TIEN = array( 'nha', 'ho_so', 'cham', 'cong_toi' );

	public static function man_mac_dinh( $ds_man ) {
		foreach ( self::MAN_UU_TIEN as $k ) {
			if ( isset( $ds_man[ $k ] ) ) { return $k; }
		}
		$khoa = array_keys( (array) $ds_man );
		return $khoa ? $khoa[0] : 'cong_toi';
	}

	public static function man_cua( $toi ) {
		$ds = array( 'nha' => 'Trang chính' );
		if ( VHCC_Vai::duoc( $toi, 'cong_minh' ) ) { $ds['cong_toi'] = 'Công của tôi'; }
		/* 🔴 MỘT TAB, KHÔNG HAI. Anh Thắng 26/08/2026: *"bản chấm công và bảng công tháng gộp
		   lại, sửa 1 lần"*. Hai tab cũ nói về CÙNG một tháng của CÙNG một cơ sở, chỉ khác cách
		   bày: một bên từng lượt chấm, một bên lưới ngang. Tách ra là bắt người ta chọn cơ sở và
		   tháng HAI LẦN, và chọn lệch một ô là hai màn nói về hai chỗ khác nhau mà không có gì
		   báo. Anh đứng ngay màn Chấm công và nói *"anh chưa thấy lưới"* — lưới nằm ở tab kia. */
		if ( VHCC_Vai::duoc( $toi, 'cong_coso' ) ) { $ds['cham']     = 'Bảng công'; }
		if ( VHCC_Vai::duoc( $toi, 'ho_so' ) )     { $ds['ho_so']    = 'Hồ sơ & tài khoản'; }
		if ( ! $ds ) { $ds['cong_toi'] = 'Công của tôi'; }
		return $ds;
	}

	/** Người này có được vào màn HỒ SƠ / TÀI KHOẢN không. Giữ tên cũ, hỏi bảng vai. */
	private static function thanh_man( $man, $ds ) {
		echo '<div class="the" style="padding:8px 10px;margin-bottom:14px"><div class="hang" style="gap:8px;flex-wrap:wrap">';
		foreach ( $ds as $k => $ten ) {
			$url = add_query_arg( array( 'man' => $k ), self::url() );
			echo '<a class="nut' . ( $k === $man ? ' chinh' : '' ) . '" href="' . esc_url( $url ) . '">'
				. esc_html( $ten ) . '</a>';
		}
		/* Chấm công là TRANG KHÁC (cần camera, và phải nhẹ để mở bằng 3G ở cơ sở) nên là một
		   liên kết chứ không phải một màn. Vẫn để chung thanh: với người dùng thì đó vẫn là
		   "một hệ thống, bấm qua lại được", đúng thứ anh Thắng hỏi. */
		echo '<a class="nut" href="' . esc_url( VHCC_Tram::url() ) . '">📷 Chấm công</a>';
		echo '</div></div>';
	}

	/** Cơ sở người này được xem — Admin/Quản lý thấy hết, còn lại thấy cơ sở mình phụ trách. */
	private static function ds_coso_xem( $toi ) {
		$vt = isset( $toi['role'] ) ? (string) $toi['role'] : '';
		if ( 'Admin' === $vt || 'Quản lý' === $vt ) { return VHCC_NhanSu::ds_coso(); }
		return VHCC_NhanSu::ds_coso_cua( $toi );
	}

	/* ===========================================================================
	 *  MÀN BẢNG CHẤM CÔNG
	 * ---------------------------------------------------------------------------
	 *  🔴 MÀN NÀY CHỈ ĐỌC GIỜ. Không có một ô nhập giờ nào, và sẽ không bao giờ có.
	 *  Giờ chấm công chỉ được ghi bởi ĐÚNG HAI đường: cổng nhận từ máy (`VHCC_Nhan`) và trạm
	 *  chấm công online (`VHCC_Online`). Mở đường thứ ba "để sửa cho nhanh" là mở đường sửa
	 *  lương bằng tay mà không để lại dấu vết — thấy một ngày sai thì GẮN CỜ: cờ nằm cạnh, giữ
	 *  lại lý do, và giờ gốc vẫn nguyên để đối chiếu.
	 *
	 *  ⚠️ Lưới xếp NGƯỜI theo hàng, NGÀY theo cột — giống hệt sheet `CS_` anh Thắng vẫn nhìn.
	 *     Đổi sang mỗi lượt một dòng thì đúng về dữ liệu nhưng không ai soi nổi một tháng.
	 * ======================================================================== */
	/**
	 * MÀN "CÔNG CỦA TÔI" — ai cũng có, kể cả nhân viên bậc thấp nhất.
	 *
	 * Anh Thắng: *"Nhân viên (chỉ chấm công và xem công của mình)"*. Đây là nửa sau của câu đó.
	 * Nửa trước — chấm công — nằm ở trang trạm, vì nó cần camera và phải nhẹ; thanh màn có liên
	 * kết bấm qua.
	 *
	 * 🔴 KHÔNG IN TIỀN. Cùng lý do với bản trên điện thoại: đây là bậc thấp nhất của hệ, và số
	 *    "công" dùng để trả lương do VHCC_Luong tính chứ không phải màn này. Xem chú thích dài
	 *    ở `VHCC_Online::bang_thang()`.
	 *
	 * ⚠️ Đọc MÃ NV từ THẺ PHIÊN, không nhận từ `$_GET`. Nhận từ URL là mở đường cho một nhân
	 *    viên gõ mã người khác vào thanh địa chỉ và xem công của họ.
	 */
	private static function the_cong_toi( $toi ) {
		$ma_nv = trim( isset( $toi['ma_nv'] ) ? (string) $toi['ma_nv'] : '' );
		$th    = isset( $_GET['cth'] ) ? sanitize_text_field( wp_unslash( $_GET['cth'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { $th = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		echo '<div class="the">';
		echo '<h2>Công của tôi</h2>';

		if ( '' === $ma_nv ) {
			/* Không có mã thì không tra được gì — nhưng phải nói RÕ thiếu ở đâu và ai sửa được,
			   chứ không phải một bảng trống. Bảng trống làm người ta tưởng mình mất công. */
			echo '<p class="loi" style="margin-top:10px">Hồ sơ của anh/chị <b>chưa có Mã NV</b>, '
				. 'nên hệ thống chưa biết các lượt chấm công thuộc về ai. '
				. 'Nhờ quản lý cửa hàng hoặc kế toán khai ô <b>Mã NV</b> trong hồ sơ nhân sự — '
				. 'khai xong là bảng dưới đây có số ngay, không phải chấm lại.</p>';
			echo '</div>';
			return;
		}

		$ds_cs = VHCC_Online::ds_coso_cua_nv( $ma_nv, VHCC_NhanSu::chuan_coso( $toi['coso'] ) );
		$kq    = VHCC_Online::bang_thang( $ma_nv, $ds_cs, $th );
		$tong  = $kq['tong'];

		echo '<p class="mo">' . esc_html( $toi['name'] ) . ' · mã <b>' . esc_html( $ma_nv ) . '</b> · '
			. esc_html( $ds_cs ? implode( ' · ', $ds_cs ) : 'chưa khai cơ sở' ) . '</p>';

		echo '<form method="get" class="hang" style="margin-top:10px">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<input type="hidden" name="man" value="cong_toi">';
		echo '<div><label for="cth">Tháng</label><input id="cth" name="cth" type="month" value="'
			. esc_attr( $th ) . '"></div>';
		echo '<div><button class="chinh">Xem</button></div>';
		echo '</form>';

		echo '<p style="margin:12px 0 4px"><b>' . (int) $tong['ngay'] . '</b> ngày · <b>'
			. (int) $tong['luot'] . '</b> lượt · <b>' . esc_html( self::gio_phut( $tong['phut'] ) )
			. '</b> có mặt</p>';
		if ( $tong['thieuRa'] ) {
			echo '<p class="loi"><b>' . (int) $tong['thieuRa'] . ' lượt thiếu giờ ra</b> '
				. '(ô Ra để trống bên dưới). Báo quản lý bổ sung <b>trước khi chốt lương tháng</b>.</p>';
		}

		if ( ! $kq['dong'] ) {
			echo '<p class="mo" style="margin-top:10px">Tháng này chưa có lượt chấm công nào.</p>';
			echo '</div>';
			return;
		}

		echo '<div class="cuon"><table class="cc"><thead><tr><th>Ngày</th><th>Cơ sở</th><th>Hàng</th>'
			. '<th>Vào</th><th>Ra</th><th>Giờ có mặt</th></tr></thead><tbody>';
		foreach ( $kq['dong'] as $d ) {
			$thieu = ( '' === $d['ra'] );
			echo '<tr>';
			echo '<td>' . esc_html( $d['ngay'] ) . '</td>';
			echo '<td>' . esc_html( $d['coSo'] ) . '</td>';
			echo '<td>' . esc_html( '' !== $d['hauTo'] ? $d['hauTo'] : 'chính' ) . '</td>';
			echo '<td>' . esc_html( '' !== $d['vao'] ? $d['vao'] : '—' ) . '</td>';
			echo '<td' . ( $thieu ? ' class="chu-hong"' : '' ) . '>'
				. ( $thieu ? 'thiếu ?' : esc_html( $d['ra'] ) ) . '</td>';
			echo '<td>' . esc_html( self::gio_phut( $d['phut'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo" style="margin-top:10px">Số ở đây là <b>giờ có mặt</b> đọc thẳng từ bảng '
			. 'chấm công — chưa trừ nghỉ, chưa quy ra công tính lương. Bảng lương do kế toán chốt '
			. 'có thể khác; thấy lệch thì báo, đừng tự cộng.</p>';
		echo '</div>';
	}

	/** Phút -> "7h30". Rỗng/null -> "—". */
	private static function gio_phut( $p ) {
		if ( null === $p || '' === $p ) { return '—'; }
		$p = (int) $p;
		$g = intdiv( $p, 60 );
		$m = $p % 60;
		return $g . 'h' . ( $m ? sprintf( '%02d', $m ) : '' );
	}

	/**
	 * MÀN QUẢN TRỊ CÔNG CƠ SỞ — dựng theo ĐÚNG tab "Chấm công" của bản Apps Script.
	 *
	 * Anh Thắng 26/08/2026: *"anh đã gửi code và web appscript, em làm y như mẫu đó"*. Nên màn
	 * này không phải em tự nghĩ ra bố cục: nó là bản dịch của `<div id="v-dash">` trong
	 * Index.html — cùng bộ lọc, cùng hai bảng, cùng quy ước màu.
	 *
	 *   · Bộ lọc: bộ phận · cơ sở · tháng · ngày · nhân viên
	 *   · Bảng chi tiết: từng lượt một, dòng đỏ = thiếu giờ ra, nút 🚩 gắn cờ
	 *   · Bảng tổng theo người: ngày công · ngày thiếu OUT · tổng giờ làm
	 *
	 * 🔴 HAI BẢNG ĐI TỪ CÙNG MỘT MẢNG. Bản gốc ghi rõ điều này ở chỗ xuất PDF — *"số trên PDF =
	 *    số trên màn hình, không có công thức thứ hai"*. Giữ nguyên: `VHCC_Cham::gom_tong()`
	 *    nhận chính mảng `hang` đã lọc, chứ không đi đọc lại cơ sở dữ liệu bằng một câu truy vấn
	 *    khác. Hai đường đọc là hai con số, và không con nào giải thích được con kia.
	 *
	 * ⚠️ Bảng TỔNG cố ý KHÔNG lọc theo ngày — đúng bản gốc (`renderTotals` chỉ lọc tháng và
	 *    nhân viên). Chọn một ngày để soi chi tiết thì bảng tổng vẫn là tổng CẢ THÁNG; nếu nó
	 *    tụt xuống còn một ngày thì cột "Ngày công" luôn bằng 1 và mất hết ý nghĩa.
	 */
	private static function the_bang_cham( $ky, $toi ) {
		$ds_cs = self::ds_coso_xem( $toi );
		$bp    = isset( $_GET['cbp'] ) ? sanitize_text_field( wp_unslash( $_GET['cbp'] ) ) : '';
		$cs    = isset( $_GET['ccs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['ccs'] ) ) : '';
		$th    = isset( $_GET['cth'] ) ? sanitize_text_field( wp_unslash( $_GET['cth'] ) ) : '';
		$ngay  = isset( $_GET['cng'] ) ? sanitize_text_field( wp_unslash( $_GET['cng'] ) ) : '';
		$ma_nv = isset( $_GET['cnv'] ) ? sanitize_text_field( wp_unslash( $_GET['cnv'] ) ) : '';
		if ( '' === $th ) { $th = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		/* Lọc danh sách cơ sở theo bộ phận trước khi vẽ ô chọn — chọn bộ phận mà ô cơ sở vẫn
		   liệt kê cả chuỗi thì cái lọc ấy không lọc gì. */
		if ( '' !== $bp ) {
			$loc = array();
			foreach ( $ds_cs as $x ) {
				if ( VHCC_Luong::bo_phan_cua( $x ) === $bp ) { $loc[] = $x; }
			}
			$ds_cs = $loc;
			/* Cơ sở đang chọn rơi ra ngoài bộ phận vừa lọc thì bỏ chọn, đừng giữ lại một cơ sở
			   không còn nằm trong danh sách — bảng sẽ hiện dữ liệu của một chỗ mà ô chọn không
			   hề trỏ tới. */
			if ( '' !== $cs && ! in_array( $cs, $ds_cs, true ) ) { $cs = ''; }
		}
		if ( '' === $cs && 1 === count( $ds_cs ) ) { $cs = $ds_cs[0]; }

		echo '<div class="the">';
		echo '<h2>Chấm công</h2>';
		echo '<p class="mo">Màn này <b>chỉ đọc</b> giờ chấm công — không có nút nào sửa giờ. '
			. 'Giờ chỉ vào bằng hai đường: máy chấm công và trạm chấm công online. '
			. 'Thấy một ngày sai thì <b>gắn cờ</b>: cờ nằm cạnh, không đè lên giờ, và giữ lại lý do.</p>';
		/* Chỉ đường sang lưới ngang. Anh Thắng 26/08 đứng ngay màn này và nói *"anh chưa thấy
		   lưới"* — lưới nằm ở tab khác, mà không có câu nào trên màn này nói ra điều đó. Hai màn
		   nói về cùng một tháng của cùng một người thì phải trỏ được sang nhau. */
		/* Trước 26/08/2026 câu này chỉ sang một TAB KHÁC. Hai tab nay đã gộp, nên nó chỉ xuống
		   một khối ngay dưới — cùng cơ sở, cùng tháng, không phải chọn lại. */
		echo '<p class="mo">Cuộn xuống <a href="#luoithang"><b>Lưới cả tháng</b></a> để xem '
			. '<b>dạng lưới ngang</b>: mỗi ô một số, cả tháng của cả cơ sở trên một màn.</p>';

		/* 🔴 BỘ PHẬN LÀ LIÊN KẾT, KHÔNG PHẢI Ô CHỌN NẰM TRONG BIỂU MẪU.
		   Anh Thắng 26/08: *"Lỗi rồi"* — chọn Bộ phận = Văn phòng rồi mở ô Cơ sở ra thì vẫn
		   thấy nguyên cả chuỗi cơ sở. Phần lọc ở trên VẪN đúng, nhưng nó chỉ chạy SAU khi bấm
		   "Xem": ô Bộ phận nằm CÙNG biểu mẫu với ô Cơ sở, hai ô cùng gửi lên một lượt, nên
		   không ô nào lọc được ô nào. Người dùng chọn xong, mở ô bên cạnh ra, thấy y nguyên —
		   và kết luận là hỏng. Họ đúng: một cái lọc chỉ lọc sau một cú bấm nữa thì không phải
		   cái lọc.
		   Màn này KHÔNG có một dòng script nào (phép thử "màn quản trị KHÔNG có thẻ <script>"),
		   nên không tự gửi biểu mẫu lúc đổi ô được. Cách chạy được mà không cần script: bộ phận
		   là LIÊN KẾT — bấm một cái là tải lại trang, ô Cơ sở dựng lại đã lọc sẵn.

		   ⚠️ ĐẾM SỐ CƠ SỞ NGAY TRÊN TỪNG NHÃN. Bộ phận chưa xếp cơ sở nào thì bấm vào chỉ thấy
		      một ô chọn rỗng, không câu nào giải thích — mà "rỗng" trông y hệt "hỏng". Có con
		      số thì nhìn là biết ngay chỗ nào chưa xếp, khỏi bấm thử từng cái. */
		$ds_tat_ca = self::ds_coso_xem( $toi );
		$dem_bp    = array();
		foreach ( $ds_tat_ca as $x ) {
			$b = VHCC_Luong::bo_phan_cua( $x );
			$dem_bp[ $b ] = ( isset( $dem_bp[ $b ] ) ? $dem_bp[ $b ] : 0 ) + 1;
		}
		/* Giữ tháng / ngày / mã NV khi đổi bộ phận — đổi bộ phận không phải là bắt đầu lại từ
		   đầu. KHÔNG giữ `ccs`: cơ sở cũ có thể không thuộc bộ phận mới. */
		$giu = array( 'man' => 'cham' );
		if ( '' !== $th )    { $giu['cth'] = $th; }
		if ( '' !== $ngay )  { $giu['cng'] = $ngay; }
		if ( '' !== $ma_nv ) { $giu['cnv'] = $ma_nv; }

		echo '<div class="loc-bp"><span class="nhan-bp">Bộ phận</span>';
		$cac_bp = array_merge( array( '' ), VHCC_Luong::BP_DS, array( VHCC_Luong::BP_CHUA_XEP ) );
		foreach ( $cac_bp as $x ) {
			$nh = ( '' === $x ) ? 'Tất cả' : $x;
			$sl = ( '' === $x ) ? count( $ds_tat_ca ) : ( isset( $dem_bp[ $x ] ) ? $dem_bp[ $x ] : 0 );
			$u  = add_query_arg( ( '' === $x ) ? $giu : array_merge( $giu, array( 'cbp' => $x ) ), self::url() );
			echo '<a class="nut' . ( $x === $bp ? ' chinh' : '' ) . ( 0 === $sl ? ' trong' : '' )
				. '" href="' . esc_url( $u ) . '">' . esc_html( $nh )
				. ' <span class="sl">' . (int) $sl . '</span></a>';
		}
		echo '</div>';

		echo '<form method="get" class="hang" style="margin-top:10px">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<input type="hidden" name="man" value="cham">';
		/* Bộ phận đi kèm biểu mẫu bằng ô ẩn: bấm "Xem" mà rơi mất bộ phận đang lọc thì cả danh
		   sách cơ sở nhảy về đầy đủ ngay sau cú bấm — đúng cái lỗi vừa sửa, chỉ chậm một nhịp. */
		if ( '' !== $bp ) { echo '<input type="hidden" name="cbp" value="' . esc_attr( $bp ) . '">'; }

		echo '<div><label for="ccs">Cơ sở</label><select id="ccs" name="ccs">';
		echo '<option value="">— chọn cơ sở —</option>';
		foreach ( $ds_cs as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . selected( $x, $cs, false ) . '>'
				. esc_html( $x ) . '</option>';
		}
		echo '</select></div>';

		echo '<div><label for="cth">Tháng</label><input id="cth" name="cth" type="month" value="'
			. esc_attr( $th ) . '"></div>';
		echo '<div><label for="cng">Ngày</label><input id="cng" name="cng" type="date" value="'
			. esc_attr( $ngay ) . '" placeholder="cả tháng"></div>';
		echo '<div><label for="cnv">Nhân viên</label><input id="cnv" name="cnv" value="'
			. esc_attr( $ma_nv ) . '" placeholder="mã NV — trống = tất cả" style="width:170px"></div>';
		echo '<div><button class="chinh">Xem</button></div>';
		echo '</form>';

		if ( '' === $cs ) {
			echo '<p class="mo" style="margin-top:12px">'
				. ( $ds_cs ? 'Chọn một cơ sở rồi bấm Xem.'
					: ( '' !== $bp
						? 'Không có cơ sở nào thuộc bộ phận này trong phạm vi của anh/chị.'
						: 'Tài khoản này chưa được gán cơ sở nào — nhờ Admin khai ô "Cửa hàng phụ trách".' ) )
				. '</p></div>';
			/* 🔴 VẪN vẽ khối nạp công ở đây. Anh Thắng 26/08: *"không thấy chỗ nạp dữ liệu công"* —
			   đúng, vì bản đầu đặt khối nạp ở CUỐI, sau bảng, mà bảng chỉ vẽ khi đã chọn cơ sở.
			   Tức là đúng lúc bảng công còn TRỐNG — lúc người ta cần nạp nhất — thì cái nút nạp
			   lại là thứ duy nhất không hiện. Ngược đời, và im lặng: màn hình trông vẫn bình
			   thường, chỉ thiếu mất thứ đang cần. */
			self::the_nap_cong( $cs, $ky, $toi, $ds_cs );
			return;
		}
		echo '</div>';

		$b = VHCC_Cham::bang_cham_cong( $toi, $cs, $th );
		if ( empty( $b['ok'] ) ) {
			echo '<div class="bao loi">' . esc_html( $b['error'] ) . '</div>';
			return;
		}
		self::ve_bang_cham( $b, $cs, $th, $ngay, $ma_nv, $ky, $toi );
	}

	/**
	 * Hai bảng của tab Chấm công: chi tiết từng lượt, rồi tổng theo người.
	 * Tách hàm để phần dựng bảng thử được riêng, không phải dựng cả trang.
	 */
	private static function ve_bang_cham( $b, $cs, $th, $ngay, $ma_nv, $ky, $toi ) {
		$tt   = (string) $b['thang'];
		$hang = (array) $b['hang'];
		/* Hỏi quyền MỘT LẦN ở đây rồi truyền xuống, thay vì hỏi lại trong vòng lặp mấy trăm
		   dòng. Và nhất là để cột tiêu đề với cột nội dung dùng CHUNG một câu trả lời — hỏi hai
		   nơi là có ngày bảng lệch cột mà không ai hiểu vì sao. */
		$duoc_sua_gio = VHCC_Vai::duoc( $toi, 'sua_gio' );

		/* Lọc cho BẢNG CHI TIẾT. Bảng tổng dùng mảng khác — xem chú thích ở `the_bang_cham`. */
		$loc_thang = array();
		foreach ( $hang as $r ) {
			if ( '' !== $ma_nv && strcasecmp( (string) $r['maNV'], $ma_nv ) !== 0 ) { continue; }
			$loc_thang[] = $r;
		}
		$chi_tiet = array();
		foreach ( $loc_thang as $r ) {
			if ( '' !== $ngay && (string) $r['ngay'] !== $ngay ) { continue; }
			$chi_tiet[] = $r;
		}

		/* Cờ đã gắn, tra theo (ngày, mã) để đánh dấu dòng nào đang chờ kiểm. */
		/* ⚠️ `$b['co']` là hàng ĐỌC THẲNG từ bảng `ghi_chu` — khoá gạch dưới (`ma_nv`,
		   `ghi_chu`, `trang_thai`), KHÔNG phải khoá lưng lạc đà như mảng `hang`. Hai kiểu khoá
		   nằm cạnh nhau trong cùng một hàm là chỗ rất dễ gõ nhầm, mà gõ nhầm thì cột Kiểm tra
		   im lặng hiện 🚩 cho cả ngày ĐÃ có cờ — không báo lỗi gì. */
		$co_theo = array();
		foreach ( (array) $b['co'] as $c ) {
			if ( 'Đã xử lý' === (string) $c['trang_thai'] ) { continue; }
			$co_theo[ (string) $c['ngay'] . '|' . strtoupper( (string) $c['ma_nv'] ) ] = $c;
		}

		/* Ngày thiếu giờ ra — tính TRƯỚC khi vẽ.
		   Bản trước gom `$thieu` ngay trong vòng lặp vẽ bảng chi tiết, nên bảng tổng buộc phải
		   nằm SAU nó. Anh Thắng 26/08: *"lưới chiều ngang nó gọn, này quá dài"* — đúng, một tháng
		   của 24 người là mấy trăm dòng, và thứ gọn nhất (bảng tổng) lại nằm dưới đáy. Tách phép
		   đếm ra khỏi phép vẽ thì muốn xếp thứ tự nào cũng được. */
		$thieu = array();
		foreach ( $chi_tiet as $r ) {
			if ( '' !== $r['vao'] && '' === $r['ra'] ) { $thieu[] = $r; }
		}

		/* 🔴 MẤY KHỐI CẤU HÌNH LÊN TRÊN, TRƯỚC BẢNG SỐ.
		   Bản trước em xếp chúng xuống cuối với lý do "chúng đổi cách đọc của cả cơ sở nên
		   không nên nằm lẫn giữa đường đi hằng ngày". Anh Thắng 26/08: *"Đưa 3 cái này lên
		   trên"* — và anh đúng: chính vì chúng đổi cách đọc CẢ BẢNG nên phải nhìn thấy chúng
		   TRƯỚC khi đọc số, không phải sau khi đã tin vào số. Cả ba đều thu gọn sẵn nên mỗi cái
		   chỉ chiếm một dòng. */
		self::the_bo_phan( $ky, $toi );
		self::the_khai_ca( $cs, $ky, $toi );
		self::the_cach_tinh( $ky, $toi );
		self::the_cong_thuc_vp( $cs, $ky, $toi );

		self::the_tong_cham( $loc_thang, $tt, $cs, $th );

		/* 🔴 LƯỚI NGANG NGAY DƯỚI BẢNG TỔNG, CÙNG MỘT MÀN.
		   Trước đây nó là tab riêng ("Bảng công tháng"), và anh Thắng đứng ngay màn này nói
		   *"anh chưa thấy lưới"*. Nay cả hai cách bày cùng một tháng nằm chung một chỗ, chọn cơ
		   sở và tháng đúng MỘT lần — chọn hai lần là có ngày hai màn nói về hai chỗ khác nhau. */
		self::the_luoi_thang( $cs, $th, $ky, $toi );

		/* Bảng chi tiết THU GỌN SẴN. Dùng thẻ <details> của chính HTML, không phải JavaScript:
		   cả màn quản trị này không có lấy một dòng script, và thứ chỉ chạy khi trình duyệt chịu
		   chạy thì bộ thử PHP không với tới được. <details> còn mở được cả khi tắt JS, và Ctrl+F
		   của trình duyệt vẫn tìm thấy chữ bên trong. */
		echo '<div class="the">';
		echo '<details><summary><b>Chi tiết từng lượt</b> — ' . count( $chi_tiet ) . ' lượt'
			. ( $thieu ? ' · <span class="chu-hong">' . count( $thieu ) . ' ngày thiếu giờ ra</span>' : '' )
			. ' <span class="mo">(bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Dòng nền đỏ = <b>thiếu giờ ra</b> (quên bấm '
			. 'lúc về). Cột <b>Giờ làm</b> để trống nghĩa là giờ ra sớm hơn giờ vào — dấu hiệu ghi '
			. 'sai, mở ra xem. Bấm 🚩 để gắn cờ nhờ cấp trên kiểm.</p>';

		if ( ! $chi_tiet ) {
			echo '<p class="mo">Không có lượt nào'
				. ( '' !== $ngay ? ' trong ngày ' . esc_html( $ngay ) : ' trong tháng ' . esc_html( $tt ) )
				. ( '' !== $ma_nv ? ' của mã ' . esc_html( $ma_nv ) : '' ) . '.</p>';
		} else {
			echo '<div class="cuon"><table class="cc"><thead><tr>'
				. '<th>Ngày</th><th>Mã NV</th><th>Họ tên</th><th>Hàng</th>'
				. '<th>Giờ vào</th><th>Giờ ra</th><th>Giờ làm</th>'
				. ( $duoc_sua_gio ? '<th>Sửa</th>' : '' ) . '<th>Kiểm tra</th>'
				. '</tr></thead><tbody>';
			foreach ( $chi_tiet as $r ) {
				$mat_ra = ( '' !== $r['vao'] && '' === $r['ra'] );
				$khoa = (string) $r['ngay'] . '|' . strtoupper( (string) $r['maNV'] );
				$co   = isset( $co_theo[ $khoa ] ) ? $co_theo[ $khoa ] : null;

				echo '<tr' . ( $mat_ra ? ' class="hong"' : '' ) . '>';
				echo '<td>' . esc_html( $r['ngay'] ) . '</td>';
				echo '<td>' . esc_html( $r['maNV'] ) . '</td>';
				echo '<td style="text-align:left">' . esc_html( $r['hoTen'] ) . '</td>';
				echo '<td>' . ( '' !== $r['hauTo']
					? '<span class="duoi">' . esc_html( $r['hauTo'] ) . '</span>' : 'chính' ) . '</td>';
				echo '<td>' . esc_html( '' !== $r['vao'] ? $r['vao'] : '—' ) . '</td>';
				echo '<td' . ( $mat_ra ? ' class="chu-hong"' : '' ) . '>'
					. ( $mat_ra ? 'thiếu' : esc_html( '' !== $r['ra'] ? $r['ra'] : '—' ) ) . '</td>';
				echo '<td>' . esc_html( VHCC_Cham::chu_gio( $r['phut'] ) ) . '</td>';
				/* Cột sửa: chỉ Admin thấy. Bấm là ĐIỀN SẴN ngày + mã xuống khối "Sửa giờ" bên
				   dưới, không sửa ngay — sửa ngay bằng một cú bấm là sửa lương bằng một cú bấm.
				   ⚠️ Điền bằng một lượt tải lại trang, không bằng JavaScript: cả màn này không
				   có lấy một dòng script, xem chú thích ở cột cờ ngay dưới. */
				if ( $duoc_sua_gio ) {
					echo '<td><a href="' . esc_url( add_query_arg( array(
							'sgn' => (string) $r['ngay'], 'sgm' => (string) $r['maNV'] ),
							self::url_hien() ) . '#suaday' )
						. '" title="Sửa giờ ngày này">✏️</a></td>';
				}
				echo '<td>' . ( $co
					? '<span class="chu-co" title="' . esc_attr( (string) $co['ghi_chu'] ) . '">🚩 đã gắn cờ</span>'
					/* Bấm cờ chỉ ĐIỀN SẴN ngày và mã xuống khối bên dưới, KHÔNG gắn ngay: cờ
					   không có lý do thì người đọc cờ chẳng biết phải kiểm gì.
					   ⚠️ Điền bằng một lượt tải lại trang chứ không bằng JavaScript. Cả màn quản
					   trị này không có lấy một dòng script; thêm một dòng vào đây là mở ra một
					   thứ chỉ chạy khi trình duyệt chịu chạy, mà lại KHÔNG có cách nào thử được
					   bằng bộ thử PHP. Đường liên kết thì thử được, bấm Lùi vẫn đúng. */
					: '<a href="' . esc_url( add_query_arg( array(
							'gnd' => (string) $r['ngay'], 'gma' => (string) $r['maNV'],
							'gten' => (string) $r['hoTen'] ), self::url_hien() ) . '#gancoform' )
						. '" title="Gắn cờ ngày này">🚩</a>' ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
			echo '<p class="mo" style="margin-top:8px">' . count( $chi_tiet ) . ' lượt'
				. ( '' !== $ngay || '' !== $ma_nv ? ' (đang lọc)' : '' ) . '.</p>';
		}
		echo '</details></div>';

		self::the_bu( $cs, $tt, $ky, $toi, $thieu );
		self::the_nap_cong( $cs, $ky, $toi, self::ds_coso_xem( $toi ) );
		self::the_co( $b, $cs, $tt, $ky, $thieu );
	}

	/**
	 * BẢNG TỔNG — cả tháng, KHÔNG theo ngày.
	 * Đứng TRƯỚC bảng chi tiết vì nó là thứ gọn nhất: một dòng một người, nhìn phát ra ngay.
	 */
	private static function the_tong_cham( $loc_thang, $tt, $cs, $th ) {
		$tong = VHCC_Cham::gom_tong( $loc_thang );
		echo '<div class="the">';
		echo '<h3 style="margin:0 0 6px">Tổng giờ làm theo nhân viên · ' . esc_html( $tt ) . '</h3>';
		echo '<p class="mo" style="margin:0 0 10px">Tổng của <b>cả tháng</b> — không đổi theo ô '
			. 'Ngày ở trên. Chọn một ngày là để soi bảng chi tiết, còn bảng này mà tụt xuống một '
			. 'ngày thì cột Ngày công luôn bằng 1 và hết ý nghĩa.</p>';
		if ( ! $tong ) {
			echo '<p class="mo">Chưa có dữ liệu.</p>';
		} else {
			echo '<div class="cuon"><table class="cc"><thead><tr>'
				. '<th>Mã NV</th><th>Họ tên</th><th>Ngày công</th><th>Ngày thiếu giờ ra</th>'
				. '<th>Tổng giờ làm</th></tr></thead><tbody>';
			$tong_phut = 0;
			$tong_ngay = 0;
			foreach ( $tong as $o ) {
				$tong_phut += (int) $o['phut'];
				$tong_ngay += (int) $o['ngay'];
				echo '<tr>';
				echo '<td>' . esc_html( $o['maNV'] ) . '</td>';
				echo '<td style="text-align:left">' . esc_html( $o['hoTen'] ) . '</td>';
				echo '<td>' . (int) $o['ngay'] . '</td>';
				echo '<td' . ( $o['thieu'] ? ' class="chu-hong"' : '' ) . '>' . (int) $o['thieu'] . '</td>';
				echo '<td><b>' . esc_html( VHCC_Cham::chu_gio( $o['phut'] ) ) . '</b></td>';
				echo '</tr>';
			}
			echo '<tr class="tong"><td colspan="2">' . count( $tong ) . ' người</td>'
				. '<td>' . (int) $tong_ngay . '</td><td></td>'
				. '<td><b>' . esc_html( VHCC_Cham::chu_gio( $tong_phut ) ) . '</b></td></tr>';
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	/**
	 * TRANG CHÀO — người mở trang ra thấy NGAY mình làm được gì và bấm vào đâu.
	 *
	 * Anh Thắng 26/08/2026: *"làm lại giao diện web chuẩn để anh gửi các bộ phận"*.
	 *
	 * 🔴 Vấn đề thật: đường link gửi cho một cửa hàng trưởng mở ra là rơi thẳng vào một bảng số
	 *    mấy trăm ô. Người ta không biết mình được làm gì, không biết cái nút nào là của mình,
	 *    và cũng không biết mình KHÔNG được làm gì — nên bấm bừa rồi nhận về câu chối. Trang này
	 *    nói ra trước, bằng lời, rồi mới tới bảng.
	 *
	 * ⚠️ Thẻ việc dựng theo QUYỀN, không theo tên vai trò. Ai không mở được việc gì thì việc ấy
	 *    KHÔNG hiện — chứ không hiện rồi chối. Hiện rồi chối là dạy người dùng rằng cái màn này
	 *    hay nói dối, và từ đó họ không tin cái nút nào nữa.
	 */
	private static function the_nha( $toi ) {
		$ten = isset( $toi['name'] ) ? (string) $toi['name'] : '';
		$ds_cs = self::ds_coso_xem( $toi );
		$th_nay = substr( (string) current_time( 'Y-m-d' ), 0, 7 );

		echo '<div class="the chao">';
		echo '<h2 style="font-size:19px;margin:0 0 2px">Chào ' . esc_html( $ten ) . '</h2>';
		echo '<p class="mo" style="margin:0">Anh/chị đang vào với vai <b>' . esc_html( VHCC_Vai::ten( $toi ) )
			. '</b>' . ( $ds_cs ? ' · phụ trách <b>' . count( $ds_cs ) . '</b> cơ sở' : '' )
			. '. Bên dưới là <b>đúng những việc anh/chị làm được</b> — việc nào không hiện là vai này '
			. 'chưa mở, không phải hỏng.</p>';
		echo '</div>';

		/* Danh sách việc. Mỗi việc: quyền cần có · tên · một câu "để làm gì" · đường tới. */
		$viec = array();
		$viec[] = array( 'q' => 'cham_online', 'ten' => '📷 Chấm công',
			'chu' => 'Bấm giờ vào / giờ ra bằng điện thoại, có ảnh và vị trí.',
			'url' => VHCC_Tram::url(), 'chinh' => true );
		$viec[] = array( 'q' => 'cong_minh', 'ten' => 'Công của tôi',
			'chu' => 'Xem tháng này mình đi làm bao nhiêu ngày, bao nhiêu giờ.',
			'url' => add_query_arg( array( 'man' => 'cong_toi' ), self::url() ) );
		$viec[] = array( 'q' => 'cong_coso', 'ten' => 'Bảng chấm công',
			'chu' => 'Giờ vào / giờ ra từng ngày của cơ sở. Chỉ đọc — thấy sai thì gắn cờ.',
			'url' => add_query_arg( array( 'man' => 'cham', 'cth' => $th_nay ), self::url() ) );
		$viec[] = array( 'q' => 'cong_coso', 'ten' => 'Bảng công tháng',
			'chu' => 'Lưới cả tháng: ai làm ca nào, mấy giờ. Xuất được ra Excel.',
			'url' => add_query_arg( array( 'man' => 'vp', 'cth' => $th_nay ), self::url() ) );
		$viec[] = array( 'q' => 'cham_bu', 'ten' => 'Chấm công bù',
			'chu' => 'Máy hỏng hoặc nhân viên quên bấm thì bù vào — có ghi lại ai bù, vì sao.',
			'url' => add_query_arg( array( 'man' => 'cham', 'cth' => $th_nay ), self::url() ) . '#bucong' );
		$viec[] = array( 'q' => 'lich_lam', 'ten' => 'Khai ca làm việc',
			'chu' => 'Cơ sở chạy mấy ca, mỗi ca từ mấy giờ đến mấy giờ.',
			'url' => add_query_arg( array( 'man' => 'vp', 'cth' => $th_nay ), self::url() ) . '#khaica' );
		$viec[] = array( 'q' => 'nap_cong', 'ten' => 'Nạp công từ .csv',
			'chu' => 'Đưa bảng công cũ từ Google Sheets vào. Có nút Xem trước, chưa ghi gì.',
			'url' => add_query_arg( array( 'man' => 'cham', 'cth' => $th_nay ), self::url() ) . '#napcong' );
		$viec[] = array( 'q' => 'ho_so', 'ten' => 'Hồ sơ & tài khoản',
			'chu' => 'Khai người, cấp PIN, đặt vai trò và cơ sở phụ trách.',
			'url' => add_query_arg( array( 'man' => 'ho_so' ), self::url() ) );

		echo '<div class="the"><h3 style="margin:0 0 10px">Việc anh/chị làm được</h3>';
		echo '<div class="the-viec">';
		foreach ( $viec as $v ) {
			if ( ! VHCC_Vai::duoc( $toi, $v['q'] ) ) { continue; }
			echo '<a class="viec' . ( empty( $v['chinh'] ) ? '' : ' viec-chinh' ) . '" href="'
				. esc_url( $v['url'] ) . '">';
			echo '<b>' . esc_html( $v['ten'] ) . '</b>';
			echo '<span>' . esc_html( $v['chu'] ) . '</span>';
			echo '</a>';
		}
		echo '</div></div>';

		self::the_link_bo_phan( $toi, $ds_cs, $th_nay );
		self::the_trang_khac();
	}

	/**
	 * ĐƯỜNG SANG CÁC TRANG KHÁC CỦA CÔNG TY.
	 *
	 * Anh Thắng 26/08/2026: *"làm 1 trang chủ ghép các trang chấm công chung lại… tạo 1 trang chủ
	 * công ty K&H để liên kết đến các trang con"*. Trang chào này đã ghép xong phần chấm công;
	 * khối dưới đây là đường ra khỏi nó — sang cổng K&H và sang bảng tin nội bộ.
	 *
	 * ⚠️ DÒ TỪNG HÀM, KHÔNG DÒ MỖI TÊN LỚP. Bốn plugin cài độc lập nên bản có thể lệch nhau:
	 *    lớp CÓ mà hàm KHÔNG là trắng cả trang. Đúng vết đã xảy ra thật ở chân trang app chi phí
	 *    ngày 23/08/2026.
	 */
	private static function the_trang_khac() {
		$co = function ( $lop, $ham ) { return class_exists( $lop ) && method_exists( $lop, $ham ); };

		$ds = array();
		if ( $co( 'VHNB_Trang', 'url' ) ) {
			$ds[] = array( 'ten' => '💬 Nội bộ', 'url' => VHNB_Trang::url(),
				'chu' => 'Bảng tin công ty: thông báo, trao đổi theo bộ phận. Dùng chung PIN với trang này.' );
		}
		if ( $co( 'VHTC_Trang', 'url' ) ) {
			$ds[] = array( 'ten' => '🏠 Cổng K&H', 'url' => VHTC_Trang::url(),
				'chu' => 'Trang chủ công ty — đường vào mọi phần mềm K&H.' );
		}
		/* Chưa cài trang nào thì KHÔNG in cái khung rỗng ra. Một khối tiêu đề không có gì bên
		   dưới trông y như trang bị hỏng. */
		if ( ! $ds ) { return; }

		echo '<div class="the"><h3 style="margin:0 0 10px">Trang khác của công ty</h3>';
		echo '<div class="the-viec">';
		foreach ( $ds as $v ) {
			echo '<a class="viec" href="' . esc_url( $v['url'] ) . '"><b>' . esc_html( $v['ten'] ) . '</b>'
				. '<span>' . esc_html( $v['chu'] ) . '</span></a>';
		}
		echo '</div></div>';
	}

	/**
	 * ĐƯỜNG LINK GỬI CHO TỪNG BỘ PHẬN / CƠ SỞ.
	 *
	 * Anh Thắng cần *"gửi các bộ phận"*. Gửi mỗi địa chỉ trần thì người nhận mở ra phải tự chọn
	 * bộ phận, chọn cơ sở, chọn tháng — ba lần chọn trước khi thấy được thứ mình cần, và chọn sai
	 * một ô là nhìn nhầm số của cơ sở khác. Link ở đây mang sẵn cả ba.
	 *
	 * ⚠️ LINK KHÔNG PHẢI LÀ CHÌA KHOÁ. Người nhận vẫn phải đăng nhập bằng PIN của họ, và vẫn chỉ
	 *    thấy cơ sở thuộc phạm vi của họ — link chỉ đỡ mấy lượt bấm chọn. Ai đó chuyển tiếp link
	 *    cho người ngoài thì người ngoài mở ra vẫn là màn nhập PIN.
	 */
	private static function the_link_bo_phan( $toi, $ds_cs, $th_nay ) {
		if ( ! VHCC_Vai::duoc( $toi, 'cong_coso' ) || ! $ds_cs ) { return; }

		/* Gom cơ sở theo bộ phận để người gửi tìm ra chỗ mình cần mà không phải đọc cả danh sách. */
		$theo_bp = array();
		foreach ( $ds_cs as $x ) { $theo_bp[ VHCC_Luong::bo_phan_cua( $x ) ][] = $x; }
		ksort( $theo_bp );

		echo '<div class="the" id="guilink"><details><summary><b>Đường link gửi cho bộ phận</b> '
			. '<span class="mo">(bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Mỗi dòng là một đường link mở sẵn <b>đúng cơ sở '
			. 'và tháng này</b> — người nhận khỏi phải chọn. Bôi đen ô rồi copy, dán vào Zalo là xong.</p>';
		echo '<p class="mo">⚠️ Link <b>không phải chìa khoá</b>: người nhận vẫn phải đăng nhập bằng '
			. 'PIN của họ và vẫn chỉ thấy cơ sở thuộc phạm vi của họ. Chuyển tiếp cho người ngoài '
			. 'thì người ngoài mở ra chỉ thấy màn nhập PIN.</p>';
		echo '<div class="cuon"><table><thead><tr><th>Bộ phận</th><th>Cơ sở</th>'
			. '<th>Link bảng chấm công</th><th>Link bảng công tháng</th></tr></thead><tbody>';
		foreach ( $theo_bp as $bp => $ds ) {
			sort( $ds );
			foreach ( $ds as $i => $cs ) {
				echo '<tr>';
				echo '<td>' . ( 0 === $i ? esc_html( $bp ) : '' ) . '</td>';
				echo '<td><b>' . esc_html( $cs ) . '</b></td>';
				foreach ( array( 'cham', 'vp' ) as $m ) {
					$u = add_query_arg( array( 'man' => $m, 'ccs' => $cs, 'cth' => $th_nay ), self::url() );
					/* Ô chỉ đọc để bôi đen copy. KHÔNG gắn `onfocus="this.select()"` cho tiện —
					   cả màn này không có lấy một dòng script, và một thuộc tính JS lẻ ở đây là
					   cái khe để dòng thứ hai chui vào sau. Bấm vào ô rồi Ctrl+A vẫn chọn được. */
					echo '<td><input class="link" readonly value="' . esc_attr( $u ) . '">'
						. ' <a href="' . esc_url( $u ) . '">mở</a></td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table></div></details></div>';
	}

	/**
	 * TAB "CÔNG VĂN PHÒNG" — lưới người × ngày, mỗi ô là SỐ CÔNG.
	 *
	 * Anh Thắng 26/08/2026: *"hiện bảng công theo hàng ngang giống này"* kèm ảnh tab Công Văn
	 * phòng của bản Apps Script. Nên đây là bản dịch của `vpcVeLuoi`, để tab riêng đúng như bản
	 * gốc — không nhét vào màn Bảng chấm công, vì hai màn trả lời hai câu khác nhau:
	 *
	 *    Bảng chấm công  -> "hôm đó người này bấm máy lúc mấy giờ"   (GIỜ, chỉ đọc, không tính)
	 *    Công Văn phòng  -> "tháng này người này được mấy công"      (CÔNG, đã qua phép tính)
	 *
	 * 🔴 Ô TRỐNG VÀ Ô SỐ 0 LÀ HAI CHUYỆN KHÁC NHAU, và lưới phải phân biệt được:
	 *      dấu `·` = ngày đó KHÔNG có dữ liệu chấm công (nghỉ, hoặc chưa nạp)
	 *      số `0`  = CÓ giờ chấm mà KHÔNG ra công (ca lạ, ca đêm thiếu giờ, kế toán chấm CN)
	 *    Gộp hai thứ này lại là xoá mất đúng những ngày cần soi.
	 */
	/**
	 * LƯỚI NGANG CẢ THÁNG — người × ngày, mỗi ô một số.
	 *
	 * Trước 26/08/2026 đây là một TAB RIÊNG ("Bảng công tháng") với bộ lọc riêng. Anh Thắng:
	 * *"bản chấm công và bảng công tháng gộp lại, sửa 1 lần"* — nên nó thành một KHỐI của màn
	 * Bảng công, dùng chung cơ sở và tháng đã chọn ở trên. Không còn ô chọn nào ở đây nữa.
	 *
	 * 🔴 ĐƠN VỊ CỦA Ô DO CƠ SỞ QUYẾT, KHÔNG PHẢI MỘT CÔNG THỨC CHO TẤT CẢ.
	 *    Anh Thắng 26/08: *"này là cơ sở mà, nên kiểu chấm khác, tính theo giờ"* — anh mở lưới ở
	 *    `FZ_SC_VIVO_T4` và thấy toàn số lẻ 0.63 · 0.31 · 0.84. Đúng: đó là công thức VĂN PHÒNG
	 *    (bậc thang theo khung 08:30–17:00) đem áp lên một CỬA HÀNG, nơi người ta làm ca gãy và
	 *    trả theo giờ. Con số ra vẫn là số, vẫn cộng được, chỉ là không có nghĩa gì.
	 */
	private static function the_luoi_thang( $cs, $th, $ky, $toi ) {
		if ( '' === $cs ) { return; }
		$la_vp    = ( 'cong' === VHCC_Luong::cach_tinh( $cs ) );
		$khai_roi = VHCC_Luong::cach_tinh_da_khai( $cs );
		$vi_sao   = $khai_roi ? 'đã khai thẳng'
			: 'suy theo bộ phận <b>' . esc_html( VHCC_Luong::bo_phan_cua( $cs ) ) . '</b>';

		echo '<div class="the" id="luoithang">';
		echo '<h2>Lưới cả tháng</h2>';
		if ( $la_vp ) {
			echo '<p class="mo"><b>' . esc_html( $cs ) . '</b> đang tính <b>THEO CÔNG</b> (' . $vi_sao
				. ') nên mỗi ô là <b>số công</b> — đã qua phép tính (khung giờ, tăng ca, ca đêm, '
				. 'công bù), không phải số giờ thô. Rê chuột lên ô để đọc vì sao ra con số đó.</p>';
		} else {
			echo '<p class="mo"><b>' . esc_html( $cs ) . '</b> đang tính <b>THEO GIỜ</b> (' . $vi_sao
				. ') nên mỗi ô là <b>số giờ làm</b> (giờ ra trừ giờ vào), không quy ra công. Cửa hàng '
				. 'làm ca gãy, đem công thức khung giờ của Văn phòng vào đây là ra một dãy số lẻ '
				. 'không có nghĩa.</p>';
			echo '<p style="margin:10px 0 0"><a class="nut" href="'
				. esc_url( add_query_arg( array( 'xuat' => 'ca', 'ccs' => $cs, 'cth' => $th ), self::url() ) )
				. '">⬇ Xuất Excel (.xlsx)</a> <span class="mo">— ba trang: chi tiết từng ca '
				. '(ca nào, từ mấy giờ đến mấy giờ, mấy tiếng) · tổng theo ca · từng lượt chấm.</span></p>';
		}
		echo '<p class="mo">Đổi đơn vị ở khối <b>Cách tính công của từng cơ sở</b> dưới cùng.</p>';
		echo '</div>';

		/* Hỏi quyền MỘT LẦN rồi truyền xuống — ô nào bấm được là do quyền, không phải do lưới. */
		$duoc_sua = VHCC_Vai::duoc( $toi, 'sua_gio' );
		$duoc_bu  = ( '' === VHCC_Bu::vi_sao_khong_duoc( $toi, $cs, 'X' )
			|| VHCC_Vai::duoc( $toi, 'cham_bu' ) );
		if ( $duoc_sua || $duoc_bu ) {
			echo '<p class="mo" style="margin:-6px 0 12px">💡 Bấm thẳng vào <b>một ô</b> trong lưới '
				. 'là nhảy xuống đúng ngày, đúng người: ô <b>có giờ</b> → '
				. ( $duoc_sua ? '<b>Sửa giờ công</b>' : 'chỉ Admin sửa được' )
				. ' · ô <b>trống</b> → ' . ( $duoc_bu ? '<b>Chấm công bù</b>' : 'cần quyền Cửa hàng trưởng' )
				. '.</p>';
		}
		if ( $la_vp ) {
			self::ve_luoi_vp( VHCC_Luong::vp_bang_cong_va_luong( $cs, $th ), $duoc_sua, $duoc_bu, $ky, $toi );
			return;
		}
		$b_gio = VHCC_Cham::bang_cham_cong( $toi, $cs, $th );
		self::ve_luoi_gio( $b_gio, $th, $duoc_sua, $duoc_bu, $ky, $toi );
		self::the_tong_ca( $b_gio, $cs );
	}

	/**
	 * CÁCH TÍNH CÔNG CỦA TỪNG CƠ SỞ — theo giờ hay theo công.
	 *
	 * Anh Thắng 26/08/2026: *"bổ sung phần cấu hình để phân biệt cơ sở nào tính theo giờ, cơ sở
	 * nào tính theo công"*.
	 *
	 * 🔴 Vẽ CẢ DANH SÁCH, không phải chỉ cơ sở đang xem. Sửa từng cơ sở một thì không ai thấy
	 *    được bức tranh chung, và chỗ sai thường lộ ra đúng lúc nhìn cả bảng: "ủa sao cái kho này
	 *    lại đang tính theo công".
	 */
	private static function the_cach_tinh( $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'ngoai_coso' ) ) { return; }
		$ds = self::ds_coso_xem( $toi );
		if ( ! $ds ) { return; }

		echo '<div class="the" id="cachtinh"><details><summary><b>Cách tính công của từng cơ sở</b> '
			. '<span class="mo">(' . count( $ds ) . ' cơ sở · bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0"><b>Theo giờ</b> = mỗi ô là số giờ làm thật (giờ ra '
			. 'trừ giờ vào) — hợp với cửa hàng làm ca gãy. <b>Theo công</b> = quy ra số công theo '
			. 'khung giờ, tăng ca, ca đêm — hợp với Văn phòng làm giờ hành chính.</p>';
		echo '<p class="mo">Để <b>Theo bộ phận</b> là giữ luật cũ: bộ phận đúng chữ "Văn phòng" thì '
			. 'tính theo công, còn lại theo giờ. Cột <b>Đang dùng</b> cho biết luật ấy đang ra kết quả gì.</p>';
		echo '<p class="mo">⚠️ Đổi cách tính là đổi <b>con số ra tiền</b> của cả cơ sở. Bảng công vẫn '
			. 'chỉ đọc — đổi ở đây <b>không sửa một giờ chấm nào</b>, chỉ đổi cách đọc số đã có.</p>';
		/* Công tắc này chỉ chọn DÙNG công thức nào. Bản thân công thức tính công của khối Văn
		   phòng (khung ca ngày, bậc thang, ca đêm, công bù, luật riêng của Kế toán) là một màn
		   khác, nhiều ô hơn hẳn — trỏ sang chứ không nhét vào đây, kẻo hai thứ lẫn vào nhau. */
		echo '<p class="mo">Đây chỉ là công tắc <b>chọn công thức nào</b>. Bản thân <b>công thức '
			. 'tính công của khối Văn phòng</b> (khung ca ngày, bậc thang, ca đêm, công bù, luật '
			. 'riêng của Kế toán) nằm ở màn <b>Cấu hình lương</b> trong wp-admin'
			. ( current_user_can( 'manage_options' )
				? ' — <a href="' . esc_url( admin_url( 'admin.php?page=vhcc-cf-luong' ) ) . '">mở ngay</a>'
				: '' ) . '.</p>';

		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="cach_tinh">' . self::o_loc();
		echo '<div class="cuon"><table><thead><tr><th>Cơ sở</th><th>Bộ phận</th>'
			. '<th>Cách tính</th><th>Đang dùng</th></tr></thead><tbody>';
		foreach ( $ds as $x ) {
			$m    = VHCC_Luong::ban_do_cach_tinh();
			$chon = isset( $m[ $x ] ) ? (string) $m[ $x ] : '';
			$dang = VHCC_Luong::cach_tinh( $x );
			echo '<tr><td><b>' . esc_html( $x ) . '</b></td>';
			echo '<td>' . esc_html( VHCC_Luong::bo_phan_cua( $x ) ) . '</td>';
			echo '<td><select name="ct[' . esc_attr( $x ) . ']">';
			foreach ( array( '' => '— theo bộ phận —', 'gio' => 'Theo giờ', 'cong' => 'Theo công' ) as $k => $n ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $chon, false ) . '>'
					. esc_html( $n ) . '</option>';
			}
			echo '</select></td>';
			echo '<td><span class="k ' . ( 'cong' === $dang ? 'ca2' : 'ca1' ) . '">'
				. ( 'cong' === $dang ? 'số công' : 'số giờ' ) . '</span>'
				. ( '' === $chon ? ' <span class="mo">(suy theo bộ phận)</span>' : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p><button class="chinh">Lưu cách tính</button></p>';
		echo '</form></details></div>';
	}

	/**
	 * BỌC MỘT Ô CỦA LƯỚI THÀNH ĐƯỜNG BẤM ĐƯỢC — bấm là sửa/bù đúng ngày đó, đúng người đó.
	 *
	 * Anh Thắng 26/08/2026: *"sửa là sửa trực tiếp trong này luôn nhé"* — kèm ảnh khối Sửa giờ
	 * công và ảnh lưới cả tháng. Đúng: bắt người ta đọc ô ở dòng thứ 14 cột 22, rồi cuộn xuống
	 * gõ lại ngày và mã vào biểu mẫu, là bắt chép tay một thứ máy đã biết — và chép sai một chữ
	 * số thì sửa nhầm ngày của người khác mà màn hình vẫn báo "Đã sửa".
	 *
	 * 🔴 Ô CÓ GIỜ và Ô TRỐNG đi hai đường khác nhau, vì đó là hai việc khác nhau:
	 *      có giờ  -> khối **Sửa giờ công** (đè lên giờ đã có · quyền Admin)
	 *      trống   -> khối **Chấm công bù** (điền vào ô còn trống · quyền Cửa hàng trưởng)
	 *    Trỏ nhầm đường là người ta bấm vào một ngày trống rồi nhận câu "chưa có dòng nào để sửa".
	 *
	 * ⚠️ KHÔNG dùng JavaScript — cả màn này không có lấy một dòng script. Một đường liên kết
	 *    mang sẵn tham số thì bấm Lùi vẫn đúng, và bộ thử PHP soi được.
	 */
	private static function o_sua( $noi_dung, $ngay, $ma_day_du, $co_gio, $duoc_sua, $duoc_bu ) {
		$duoc = $co_gio ? $duoc_sua : $duoc_bu;
		if ( ! $duoc ) { return $noi_dung; }
		$url = $co_gio
			? ( add_query_arg( array( 'sgn' => $ngay, 'sgm' => $ma_day_du ), self::url_hien() ) . '#suaday' )
			: ( add_query_arg( array( 'gnd' => $ngay, 'gma' => $ma_day_du ), self::url_hien() ) . '#bucong' );
		return '<a class="o-sua" href="' . esc_url( $url ) . '">' . $noi_dung . '</a>';
	}

	/**
	 * Ô nào đang được chọn để sửa / bù: [ngày, mã, có-giờ-hay-không].
	 *
	 * ⚠️ Chỉ nhận ngày THUỘC ĐÚNG THÁNG đang xem. Không kiểm thì bấm một ô ở tháng 7, đổi sang
	 *    tháng 8, rồi hàng sửa vẫn mở ra giữa lưới tháng 8 với một ngày của tháng 7 — và người
	 *    ta bấm Lưu.
	 */
	private static function o_dang_sua( $tt ) {
		$sgn = isset( $_GET['sgn'] ) ? sanitize_text_field( wp_unslash( $_GET['sgn'] ) ) : '';
		$sgm = isset( $_GET['sgm'] ) ? sanitize_text_field( wp_unslash( $_GET['sgm'] ) ) : '';
		if ( '' !== $sgn && '' !== $sgm ) {
			return ( 0 === strpos( $sgn, $tt . '-' ) ) ? array( $sgn, $sgm, true ) : array( '', '', true );
		}
		$gnd = isset( $_GET['gnd'] ) ? sanitize_text_field( wp_unslash( $_GET['gnd'] ) ) : '';
		$gma = isset( $_GET['gma'] ) ? sanitize_text_field( wp_unslash( $_GET['gma'] ) ) : '';
		if ( '' !== $gnd && '' !== $gma ) {
			return ( 0 === strpos( $gnd, $tt . '-' ) ) ? array( $gnd, $gma, false ) : array( '', '', false );
		}
		return array( '', '', false );
	}

	/**
	 * HÀNG SỬA NỘI TUYẾN — biểu mẫu hiện NGAY DƯỚI dòng của người vừa bấm, trong chính cái lưới.
	 *
	 * Anh Thắng 26/08/2026: *"mình có sửa trong khu này luôn được không, hay phải bắt buộc nhảy
	 * vào ô sửa"*. Được — và đây là câu trả lời.
	 *
	 * 🔴 VÌ SAO KHÔNG NHÉT BIỂU MẪU VÀO CHÍNH CÁI Ô.
	 *    Ô trong lưới rộng chừng 34px. Nhét hai ô giờ + ô lý do vào đó thì hoặc là cả lưới giãn
	 *    ra gấp mười (mất luôn cái lợi "cả tháng trên một màn"), hoặc là mấy ô nhập bé tới mức
	 *    không bấm nổi trên điện thoại. Nên biểu mẫu chiếm TRỌN một hàng ngay dưới — vẫn nằm
	 *    trong lưới, vẫn thấy dòng của đúng người đó ở ngay trên.
	 *
	 * 🔴 CHỈ MỘT HÀNG, CỦA ĐÚNG Ô VỪA BẤM.
	 *    Vẽ sẵn biểu mẫu cho cả 600 ô là 600 biểu mẫu trong một trang: trang nặng, và mọi ô đều
	 *    trông như đang chờ sửa. Bấm ô nào thì mở hàng của ô ấy — địa chỉ mang sẵn `sgn`/`sgm`
	 *    nên bấm Lùi vẫn đúng, và không cần một dòng JavaScript nào.
	 *
	 * ⚠️ Ô CÓ GIỜ và Ô TRỐNG là hai việc khác nhau, nên hai biểu mẫu khác nhau:
	 *      có giờ -> `sua_gio` (đè lên giờ đã có · quyền Admin)
	 *      trống  -> `bu`      (điền vào ô còn trống · quyền Cửa hàng trưởng)
	 */
	private static function hang_sua( $so_cot, $cs, $ngay, $ma_dd, $co_gio, $ky, $toi ) {
		$duoc = $co_gio ? VHCC_Vai::duoc( $toi, 'sua_gio' ) : VHCC_Vai::duoc( $toi, 'cham_bu' );
		echo '<tr class="hang-sua" id="suaday"><td colspan="' . (int) $so_cot . '">';

		if ( ! $duoc ) {
			echo '<div class="bao canh" style="margin:0">' . esc_html( $co_gio
				? 'Sửa giờ đã có cần quyền Admin. Thấy giờ sai thì gắn cờ để Admin sửa.'
				: 'Bù giờ vào ô trống cần quyền Cửa hàng trưởng trở lên.' ) . '</div></td></tr>';
			return;
		}

		$dg = VHCC_Bu::gio_hien_tai( $cs, $ngay, $ma_dd );
		echo '<form method="post" class="hang" style="margin:0;align-items:flex-end">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="' . ( $co_gio ? 'sua_gio' : 'bu' ) . '">'
			. self::o_loc()
			. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">'
			. '<input type="hidden" name="ngay" value="' . esc_attr( $ngay ) . '">'
			. '<input type="hidden" name="ma_nv" value="' . esc_attr( $ma_dd ) . '">';

		/* Nhắc lại ĐANG SỬA AI, NGÀY NÀO, GIỜ ĐANG CÓ LÀ BAO NHIÊU. Hàng nằm ngay dưới dòng của
		   người đó, nhưng lưới 31 cột thì mắt vẫn lạc — và sửa nhầm người là sửa nhầm lương. */
		echo '<div style="flex:0 0 auto"><label>Đang ' . ( $co_gio ? 'sửa' : 'bù' ) . '</label>'
			. '<b>' . esc_html( $ma_dd ) . '</b> · ' . esc_html( self::ngay_vn( $ngay ) )
			. '<div class="mo" style="font-size:11.5px">đang có: vào <b>' . esc_html( $dg['vao'] )
			. '</b> · ra <b>' . esc_html( $dg['ra'] ) . '</b></div></div>';

		$tv = $co_gio ? 'sg_vao' : 'bu_vao';
		$tr = $co_gio ? 'sg_ra' : 'bu_ra';
		echo '<div><label for="iv_' . esc_attr( $tv ) . '">Giờ vào' . ( $co_gio ? ' mới' : '' ) . '</label>'
			. '<input id="iv_' . esc_attr( $tv ) . '" name="' . esc_attr( $tv ) . '" type="time"></div>';
		echo '<div><label for="iv_' . esc_attr( $tr ) . '">Giờ ra' . ( $co_gio ? ' mới' : '' ) . '</label>'
			. '<input id="iv_' . esc_attr( $tr ) . '" name="' . esc_attr( $tr ) . '" type="time"></div>';

		if ( $co_gio ) {
			/* Ô trống = GIỮ NGUYÊN. Muốn xoá trắng phải tích — một hành động riêng, cố ý. */
			echo '<div style="flex:0 0 auto"><label>Xoá trắng</label>'
				. '<label style="display:inline;font-size:12px;margin-right:10px">'
				. '<input type="checkbox" name="sg_xoa_vao" value="1"> vào</label>'
				. '<label style="display:inline;font-size:12px">'
				. '<input type="checkbox" name="sg_xoa_ra" value="1"> ra</label></div>';
		}
		echo '<div style="flex:1 1 240px"><label for="iv_ly">Vì sao *</label>'
			. '<input id="iv_ly" name="ly_do" required minlength="5" style="width:100%" '
			. 'placeholder="' . esc_attr( $co_gio ? 'VD: máy lệch đồng hồ 2 tiếng — đối chiếu camera'
				: 'VD: máy hỏng sáng nay, có camera' ) . '"></div>';
		echo '<div><button class="chinh">' . ( $co_gio ? 'Lưu giờ' : 'Bù giờ' ) . '</button></div>';
		echo '<div><a class="nut" href="' . esc_url( remove_query_arg(
			array( 'sgn', 'sgm', 'gnd', 'gma' ), self::url_hien() ) ) . '#luoithang">Đóng</a></div>';
		echo '</form></td></tr>';
	}

	/**
	 * XẾP CƠ SỞ VÀO BỘ PHẬN.
	 *
	 * Anh Thắng 26/08/2026: *"bổ sung set cơ sở thuộc bộ phận nào"* — kèm ảnh ô lọc Bộ phận và
	 * danh sách 21 cơ sở.
	 *
	 * 🔴 THIẾU MÀN NÀY THÌ HAI THỨ VỪA LÀM ĐỀU KHÔNG DÙNG ĐƯỢC.
	 *    Bảng `bo_phan_coso` có từ lâu và `VHCC_NhanSu::xep_bo_phan()` cũng có, nhưng chưa có
	 *    chỗ nào TRÊN WEB để khai. Nên: ô lọc "Bộ phận" liệt kê đủ bốn mục mà chọn cái nào cũng
	 *    ra rỗng, và **công thức tính công riêng từng khối** (làm 26/08) không có tác dụng với
	 *    cơ sở nào cả — vì không cơ sở nào thuộc khối nào. Cả hai đều hỏng IM LẶNG: màn hình
	 *    trông đầy đủ, chỉ là không có gì xảy ra.
	 *
	 * ⚠️ Bộ phận quyết định CÔNG THỨC LƯƠNG của cả cơ sở, nên gác ở bậc Quản lý trở lên
	 *    (`VHCC_NhanSu::xep_bo_phan` gác lại lần nữa ở máy chủ — ẩn cái khối không phải là gác).
	 */
	private static function the_bo_phan( $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'ngoai_coso' ) ) { return; }
		$ds = self::ds_coso_xem( $toi );
		if ( ! $ds ) { return; }

		/* Đếm xem còn bao nhiêu cơ sở chưa xếp — con số này là lý do người ta mở khối ra. */
		$chua = 0;
		foreach ( $ds as $x ) {
			if ( VHCC_Luong::BP_CHUA_XEP === VHCC_Luong::bo_phan_cua( $x ) ) { $chua++; }
		}

		echo '<div class="the" id="bophan"><details' . ( $chua ? ' open' : '' ) . '><summary>'
			. '<b>Cơ sở thuộc bộ phận nào</b> <span class="mo">(' . count( $ds ) . ' cơ sở'
			. ( $chua ? ' · <b class="chu-hong">' . $chua . ' chưa xếp</b>' : ' · đã xếp hết' )
			. ' · bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Bộ phận quyết định <b>công thức tính công</b> của '
			. 'cả cơ sở, và là thứ mà ô lọc <b>Bộ phận</b> ở đầu màn dựa vào.</p>';
		/* 🔴 Nói THẲNG hậu quả của việc để trống. "Chưa xếp" nghe như một trạng thái vô hại. */
		echo '<p class="mo">⚠️ Cơ sở để <b>Chưa xếp</b> thì <b>không có công thức tính công</b> — '
			. 'đó là hành vi cố ý (thà không tính còn hơn tính bằng công thức của bộ phận khác), '
			. 'nhưng nghĩa là bảng công của cơ sở đó sẽ không ra số công nào.</p>';

		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="bo_phan">' . self::o_loc();
		echo '<div class="cuon"><table><thead><tr><th>Cơ sở</th><th>Bộ phận</th>'
			. '<th>Cách tính đang dùng</th></tr></thead><tbody>';
		foreach ( $ds as $x ) {
			$bp = VHCC_Luong::bo_phan_cua( $x );
			$la_chua = ( VHCC_Luong::BP_CHUA_XEP === $bp );
			echo '<tr' . ( $la_chua ? ' class="hong"' : '' ) . '><td><b>' . esc_html( $x ) . '</b></td>';
			echo '<td><select name="bp[' . esc_attr( $x ) . ']">';
			echo '<option value=""' . ( $la_chua ? ' selected' : '' ) . '>— chưa xếp —</option>';
			foreach ( VHCC_Luong::BP_DS as $b ) {
				echo '<option value="' . esc_attr( $b ) . '"' . selected( $b, $bp, false ) . '>'
					. esc_html( $b ) . '</option>';
			}
			echo '</select></td>';
			$ct = VHCC_Luong::cach_tinh( $x );
			echo '<td><span class="k ' . ( 'cong' === $ct ? 'ca2' : 'ca1' ) . '">'
				. ( 'cong' === $ct ? 'số công' : 'số giờ' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p><button class="chinh">Lưu bộ phận</button></p></form>';
		echo '</details></div>';
	}

	/**
	 * CÔNG THỨC TÍNH CÔNG — bản chung, và bản riêng của từng KHỐI.
	 *
	 * Anh Thắng 26/08/2026: *"bổ sung cho khối văn phòng phương pháp tính công"* và *"trang quản
	 * trị không cần tách ra đâu"* — nên biểu mẫu này mang từ wp-admin về đây, cạnh chỗ người ta
	 * đang nhìn bảng công.
	 *
	 * 🔴 VÌ SAO PHẢI CÓ BẢN RIÊNG TỪNG KHỐI.
	 *    Ảnh anh gửi cho thấy màn cấu hình đang để **ca ngày 08:30–21:30**. Đó là khung của CỬA
	 *    HÀNG (mở tới 21:30), nhưng cả hệ chỉ có MỘT bộ số, nên đúng bộ số ấy cũng đang tính công
	 *    cho Văn phòng — nơi người ta về lúc 17:00. Một ngày Văn phòng đủ giờ chỉ phủ 8 trên 13
	 *    tiếng của khung, và tuỳ ô "Làm thiếu giờ thì tính sao" mà nó rơi xuống nửa công hoặc lên
	 *    1.5 công. Bảng vẫn ra số, vẫn đẹp, chỉ là sai — và sai theo hướng nào thì phụ thuộc một ô
	 *    mà người sửa khung giờ cửa hàng không hề nghĩ mình đang chạm tới.
	 *
	 * ⚠️ Ô để TRỐNG ở bản riêng = KHÔNG khai = theo bản chung. Nên khối riêng chỉ cần khai đúng
	 *    mấy ô thật sự khác, không phải chép lại cả bộ. Chép cả bộ là ba bản sao của cùng một
	 *    thứ: sửa "công của ca đêm" ở bản chung xong vẫn sai ở hai bản kia.
	 */
	private static function the_cong_thuc_vp( $cs, $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'luong' ) ) { return; }

		$ds_khoi = VHCC_Luong::vp_cfg_ds_khoi();
		$khoi = isset( $_GET['ctk'] ) ? sanitize_text_field( wp_unslash( $_GET['ctk'] ) ) : '';
		if ( '' !== $khoi && ! in_array( $khoi, $ds_khoi, true ) ) { $khoi = ''; }

		$chung = VHCC_Luong::vp_cfg();
		$rieng = ( '' === $khoi ) ? array() : VHCC_Luong::vp_cfg_khoi( $khoi );
		/* Bộ số ĐANG THẬT SỰ CHẠY của khối đang xem — chung đè bởi riêng. Hiện con số này chứ
		   không hiện mỗi ô trống: người ta cần biết khối này đang chạy bằng gì, không phải biết
		   khối này chưa khai gì. */
		$dang = array_merge( $chung, $rieng );
		$cc   = VHCC_Luong::ca_chuan( $dang );

		echo '<div class="the" id="congthuc"><details><summary><b>Công thức tính công</b> '
			. '<span class="mo">(' . ( '' === $khoi ? 'bản chung' : 'khối ' . esc_html( $khoi ) )
			. ' · bấm để mở)</span></summary>';

		/* 🔴 CON SỐ PHẢI ĐỌC TRƯỚC KHI BẤM LƯU. Ca chuẩn dài mấy tiếng thì ra mấy công — đặt mốc
		   bậc thang sai là chính NGÀY LÀM BÌNH THƯỜNG thành 1.5 công, tức lương cả khối tăng 50%,
		   không riêng người thiếu giờ. */
		echo '<div class="bao canh" style="margin:10px 0"><b>Ca chuẩn của khối đang xem: '
			. esc_html( $cc['gio'] ) . ' tiếng → ' . esc_html( $cc['cong'] ) . ' công.</b> '
			. 'Đọc con số này TRƯỚC khi bấm Lưu: mốc bậc thang đặt sai thì <i>ngày làm bình '
			. 'thường</i> cũng thành 1.5 công — lương cả khối tăng, không riêng người thiếu giờ.</div>';

		/* Thanh chọn khối. Đi bằng đường liên kết chứ không bằng JavaScript — cả màn này không
		   có lấy một dòng script. */
		echo '<div class="hang" style="gap:8px;margin:0 0 12px">';
		echo '<a class="nut' . ( '' === $khoi ? ' chinh' : '' ) . '" href="'
			. esc_url( add_query_arg( array( 'ctk' => '' ), self::url_hien() ) . '#congthuc' )
			. '">Bản chung</a>';
		foreach ( $ds_khoi as $k ) {
			$so = count( VHCC_Luong::vp_cfg_khoi( $k ) );
			echo '<a class="nut' . ( $k === $khoi ? ' chinh' : '' ) . '" href="'
				. esc_url( add_query_arg( array( 'ctk' => $k ), self::url_hien() ) . '#congthuc' )
				. '">' . esc_html( $k )
				. ( $so ? ' <span class="k tim">' . (int) $so . ' ô riêng</span>' : '' ) . '</a>';
		}
		echo '</div>';

		if ( '' === $khoi ) {
			echo '<p class="mo">Đang sửa <b>bản chung</b> — bộ số này áp cho mọi khối CHƯA khai riêng.</p>';
		} else {
			echo '<p class="mo">Đang sửa riêng cho khối <b>' . esc_html( $khoi ) . '</b>. Ô nào để '
				. '<b>trống</b> thì khối này <b>theo bản chung</b>; ô nào gõ vào thì <b>chỉ khối này</b> '
				. 'dùng số ấy. Xoá trắng một ô là bỏ khai, quay về bản chung.</p>';
		}

		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="cong_thuc">' . self::o_loc()
			. '<input type="hidden" name="ctk" value="' . esc_attr( $khoi ) . '">';
		echo '<div class="luoi">';
		foreach ( VHCC_Luong::VP_O as $k => $mt ) {
			list( $nhan, $kieu, $chu ) = $mt;
			$id = 'ct_' . $k;
			/* Bản riêng: ô hiện GIÁ TRỊ ĐÃ KHAI RIÊNG (rỗng nếu chưa khai), còn gợi ý bên dưới
			   nói bản chung đang là bao nhiêu. Đổ sẵn giá trị chung vào ô là bấm Lưu một phát
			   thành khai riêng TOÀN BỘ, và từ đó bản chung không còn với tới khối này nữa. */
			$gt = ( '' === $khoi ) ? $chung[ $k ] : ( isset( $rieng[ $k ] ) ? $rieng[ $k ] : '' );
			$gt_chung = $chung[ $k ];
			if ( 'ds' === $kieu ) {
				$gt = is_array( $gt ) ? implode( ', ', $gt ) : (string) $gt;
				$gt_chung = is_array( $gt_chung ) ? implode( ', ', $gt_chung ) : (string) $gt_chung;
			}

			echo '<div><label for="' . esc_attr( $id ) . '">' . esc_html( $nhan ) . '</label>';
			if ( 'chon' === $kieu ) {
				echo '<select id="' . esc_attr( $id ) . '" name="ct[' . esc_attr( $k ) . ']">';
				if ( '' !== $khoi ) { echo '<option value="">— theo bản chung —</option>'; }
				foreach ( VHCC_Luong::VP_DUOI_MIN as $vk => $vn ) {
					echo '<option value="' . esc_attr( $vk ) . '"' . selected( $vk, (string) $gt, false )
						. '>' . esc_html( $vn ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'tick' === $kieu ) {
				/* 🔴 BA TRẠNG THÁI, KHÔNG PHẢI Ô TÍCH. Ô tích chỉ nói được có/không; ở bản riêng
				   còn trạng thái thứ ba là "không khai, theo bản chung". Dùng ô tích thì mỗi lượt
				   Lưu đều khai cứng một giá trị, và không còn đường nào bỏ khai. */
				$hien = ( '' === (string) $gt ) ? '' : ( $gt ? '1' : '0' );
				echo '<select id="' . esc_attr( $id ) . '" name="ct[' . esc_attr( $k ) . ']">';
				if ( '' !== $khoi ) { echo '<option value="">— theo bản chung —</option>'; }
				foreach ( array( '1' => 'Có', '0' => 'Không' ) as $vk => $vn ) {
					echo '<option value="' . esc_attr( $vk ) . '"' . selected( $vk, $hien, false )
						. '>' . esc_html( $vn ) . '</option>';
				}
				echo '</select>';
			} else {
				$type = ( 'gio' === $kieu ) ? 'time' : 'text';
				echo '<input id="' . esc_attr( $id ) . '" name="ct[' . esc_attr( $k ) . ']" type="'
					. esc_attr( $type ) . '" value="' . esc_attr( (string) $gt ) . '"'
					. ( '' !== $khoi ? ' placeholder="' . esc_attr( (string) $gt_chung ) . '"' : '' ) . '>';
			}
			if ( '' !== $khoi ) {
				echo '<div class="mo" style="font-size:11.5px">Bản chung: <b>'
					. esc_html( '' === (string) $gt_chung ? '(trống)' : (string) $gt_chung ) . '</b></div>';
			}
			if ( '' !== $chu ) { echo '<div class="mo" style="font-size:11.5px">' . esc_html( $chu ) . '</div>'; }
			echo '</div>';
		}
		echo '</div>';
		echo '<p style="margin-top:12px"><button class="chinh">'
			. ( '' === $khoi ? 'Lưu bản chung' : 'Lưu riêng cho khối ' . esc_html( $khoi ) )
			. '</button></p></form>';
		echo '</details></div>';
	}

	/**
	 * TỔNG GIỜ THEO CA — *"bạn đó làm ca nào, ca đó mấy tiếng"*.
	 * Một dòng một người, một cột một ca. Cột cuối là phần giờ KHÔNG thuộc ca nào — cột ấy có số
	 * là dấu hiệu khung ca đang khai lệch với giờ người ta làm thật, không phải lỗi của ai.
	 */
	private static function the_tong_ca( $b, $cs ) {
		if ( empty( $b['ok'] ) ) { return; }
		$ds_ca = VHCC_Ca::cua( $cs );
		if ( ! $ds_ca ) { return; }

		$ten_ca = array();
		foreach ( $ds_ca as $c ) { $ten_ca[] = $c['ten']; }

		$nguoi = array();
		foreach ( (array) $b['hang'] as $r ) {
			$ma = (string) $r['maNV'];
			if ( ! isset( $nguoi[ $ma ] ) ) {
				$nguoi[ $ma ] = array( 'ten' => (string) $r['hoTen'], 'ca' => array(), 'ngoai' => 0 );
			}
			$x = VHCC_Ca::tach( $ds_ca, $r['vaoGiay'], $r['raGiay'], VHCC_Ca::la_cuoi_tuan( $r['ngay'] ) );
			foreach ( $x['ds'] as $o ) {
				$nguoi[ $ma ]['ca'][ $o['ten'] ] = ( isset( $nguoi[ $ma ]['ca'][ $o['ten'] ] )
					? $nguoi[ $ma ]['ca'][ $o['ten'] ] : 0 ) + (int) $o['phut'];
			}
			$nguoi[ $ma ]['ngoai'] += (int) $x['ngoai_ca'];
		}
		uasort( $nguoi, function ( $a, $c ) { return strcasecmp( $a['ten'], $c['ten'] ); } );

		echo '<div class="the"><h3 style="margin:0 0 6px">Tổng giờ theo ca</h3>';
		$nguon = VHCC_Ca::nguon_ca( $cs );
		echo '<p class="mo" style="margin:0 0 10px">Khung ca đang dùng: '
			. ( 'rieng' === $nguon ? '<b>khai riêng cho ' . esc_html( $cs ) . '</b>'
				: ( 'chung' === $nguon ? '<b>khai chung</b> cho mọi cơ sở'
					: '<b>mặc định</b> (chưa ai khai)' ) )
			. ' — ' . esc_html( self::chu_ds_ca( $ds_ca ) ) . '. Khai lại ở khối dưới cùng.</p>';
		if ( ! $nguoi ) { echo '<p class="mo">Chưa có dữ liệu.</p></div>'; return; }

		echo '<div class="cuon"><table class="cc"><thead><tr><th>Nhân viên</th>';
		foreach ( $ds_ca as $i => $c ) {
			echo '<th class="ca' . ( ( $i % 4 ) + 1 ) . '"><b>' . esc_html( VHCC_Ca::ma_ngan( $i ) )
				. '</b> ' . esc_html( $c['ten'] ) . '<div style="font-weight:400;opacity:.7">'
				. esc_html( $c['tu'] . '–' . $c['den'] ) . '</div></th>';
		}
		echo '<th>Ngoài ca</th><th>TỔNG</th></tr></thead><tbody>';
		foreach ( $nguoi as $x ) {
			echo '<tr><td>' . esc_html( $x['ten'] ) . '</td>';
			$tong = 0;
			foreach ( $ten_ca as $tc ) {
				$p = isset( $x['ca'][ $tc ] ) ? (int) $x['ca'][ $tc ] : 0;
				$tong += $p;
				echo '<td class="oc">' . ( $p ? '<b>' . esc_html( VHCC_Cham::chu_gio( $p ) ) . '</b>' : '·' ) . '</td>';
			}
			$tong += (int) $x['ngoai'];
			echo '<td class="oc' . ( $x['ngoai'] ? ' vang' : '' ) . '">'
				. ( $x['ngoai'] ? esc_html( VHCC_Cham::chu_gio( $x['ngoai'] ) ) : '·' ) . '</td>';
			echo '<td class="tong"><b>' . esc_html( VHCC_Cham::chu_gio( $tong ) ) . '</b></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo" style="margin-top:8px">Cột <b>Ngoài ca</b> có số nghĩa là người ta có làm '
			. 'những giờ KHÔNG nằm trong khung ca nào đang khai — không phải lỗi của ai, mà là dấu '
			. 'hiệu khung ca khai chưa khớp với giờ làm thật. Sửa khung ca ở khối dưới cùng.</p>';
		echo '</div>';
	}

	private static function chu_ds_ca( $ds_ca ) {
		$c = array();
		foreach ( $ds_ca as $x ) { $c[] = $x['ten'] . ' ' . $x['tu'] . '–' . $x['den']; }
		return implode( ' · ', $c );
	}

	/**
	 * KHAI CA cho một cơ sở.
	 *
	 * Luôn vẽ thêm HAI dòng trống để thêm ca mà không cần JavaScript — cả màn này không có lấy
	 * một dòng script, và một cái nút "＋ Thêm ca" chạy bằng script thì bộ thử PHP không với tới.
	 * Muốn thêm ca thứ ba trở lên thì lưu một lượt rồi hai dòng trống mới lại hiện ra.
	 */
	private static function the_khai_ca( $cs, $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'lich_lam' ) ) { return; }
		$ds  = VHCC_Ca::cua( $cs );
		$ngu = VHCC_Ca::nguon_ca( $cs );

		echo '<div class="the" id="khaica"><details><summary><b>Khai ca làm việc</b> — '
			. count( $ds ) . ' ca <span class="mo">(bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Mỗi ca có giờ <b>ngày thường</b> và giờ '
			. '<b>cuối tuần</b> (T7–CN). Để trống ô cuối tuần = dùng như ngày thường. '
			. 'Ca qua nửa đêm cứ khai thẳng (VD 22:00 → 06:00), hệ hiểu là kết thúc hôm sau.</p>';
		if ( 'rieng' !== $ngu ) {
			echo '<p class="mo">Cơ sở này đang mượn khung ca '
				. ( 'chung' === $ngu ? 'khai chung' : 'mặc định' )
				. '. Lưu một lượt là nó có khung ca RIÊNG, đổi cơ sở khác không ảnh hưởng.</p>';
		}
		/* Ô `ccs` của màn này phải đứng SAU `o_loc()` — xem chú thích ở `the_bu`. */
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="ca">' . self::o_loc()
			. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">';
		echo '<div class="cuon"><table><thead><tr><th>Tên ca</th><th>Từ (T2–T6)</th><th>Đến</th>'
			. '<th>Từ (T7–CN)</th><th>Đến</th></tr></thead><tbody>';
		$hang = $ds;
		$hang[] = array( 'ten' => '', 'tu' => '', 'den' => '', 'tuW' => '', 'denW' => '' );
		$hang[] = array( 'ten' => '', 'tu' => '', 'den' => '', 'tuW' => '', 'denW' => '' );
		foreach ( $hang as $i => $c ) {
			echo '<tr>';
			echo '<td><input name="ca_ten[' . (int) $i . ']" value="' . esc_attr( $c['ten'] )
				. '" placeholder="VD: Ca 1" style="width:130px"></td>';
			foreach ( array( 'ca_tu' => 'tu', 'ca_den' => 'den', 'ca_tuw' => 'tuW', 'ca_denw' => 'denW' ) as $o => $k ) {
				echo '<td><input type="time" name="' . $o . '[' . (int) $i . ']" value="'
					. esc_attr( $c[ $k ] ) . '"></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo">Xoá hết tên ca rồi lưu = bỏ khai riêng, quay về dùng ca chung.</p>';
		echo '<p><button class="chinh">Lưu ca cho ' . esc_html( $cs ) . '</button></p>';
		echo '</form></details></div>';
	}

	/**
	 * LƯỚI THEO GIỜ — cho cơ sở KHÔNG thuộc Văn phòng.
	 *
	 * Mỗi ô là số giờ làm của ngày đó, lấy đúng con số mà cột "Giờ làm" của màn Bảng chấm công
	 * đang hiện. Cố ý dùng CÙNG một phép tính (`VHCC_Cham::phut_lam`) chứ không tính lại ở đây:
	 * hai màn nói về cùng một ngày của cùng một người mà ra hai con số thì không con nào giải
	 * thích được con kia.
	 *
	 * ⚠️ Đây là GIỜ LÀM THỰC TẾ, không phải giờ được trả tiền. Tiền tính theo phần giao với khung
	 *    ca đã xếp (xem `VHCC_Luong::bao_cao_theo_gio`), nên hai số có thể lệch — nói thẳng ra ở
	 *    chú giải chứ không để người đọc tự phát hiện lúc đối lương.
	 */
	private static function ve_luoi_gio( $b, $th, $duoc_sua = false, $duoc_bu = false, $ky = '', $toi = array() ) {
		if ( empty( $b['ok'] ) ) {
			echo '<div class="bao loi">' . esc_html( $b['error'] ) . '</div>';
			return;
		}
		$tt  = (string) $b['thang'];
		$moc = strtotime( $tt . '-01 00:00:00 UTC' );
		if ( false === $moc ) {
			echo '<div class="bao loi">Tháng không hợp lệ.</div>';
			return;
		}
		$so_ngay = (int) gmdate( 't', $moc );
		$thu_vn  = array( 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' );
		$ds_ca   = VHCC_Ca::cua( (string) $b['coSo'] );
		/* Ô đang được chọn để sửa / bù — đọc từ chính địa chỉ, nên bấm Lùi vẫn đúng. */
		list( $sg_n, $sg_m, $sg_co ) = self::o_dang_sua( $tt );

		/* Gom [mã][hậu tố][số ngày]. Hàng `-CD` / `-TC` là HÀNG RIÊNG trong sổ công — gộp vào
		   hàng chính là mất chỗ để nhìn ra ca đêm và người tăng cường. */
		$o    = array();
		$ten  = array();
		foreach ( (array) $b['hang'] as $r ) {
			$ma = (string) $r['maNV'];
			$ht = (string) $r['hauTo'];
			$o[ $ma ][ $ht ][ (int) substr( (string) $r['ngay'], 8, 2 ) ] = $r;
			if ( ! isset( $ten[ $ma ] ) || '' === $ten[ $ma ] ) { $ten[ $ma ] = (string) $r['hoTen']; }
		}
		uasort( $ten, function ( $a, $c ) { return strcasecmp( $a, $c ); } );

		echo '<div class="the">';
		if ( ! $ten ) {
			echo '<p class="mo">Tháng ' . esc_html( $tt ) . ' chưa có dữ liệu chấm công nào ở cơ sở này. '
				. 'Nạp công từ .csv ở màn <b>Bảng chấm công</b>, hoặc chờ máy đẩy giờ về.</p></div>';
			return;
		}

		echo '<div class="cuon"><table class="cc"><thead><tr><th>Nhân viên</th>';
		for ( $i = 1; $i <= $so_ngay; $i++ ) {
			$t  = (int) gmdate( 'w', strtotime( sprintf( '%s-%02d 00:00:00 UTC', $tt, $i ) ) );
			$cn = ( 0 === $t || 6 === $t );
			echo '<th class="ng' . ( $cn ? ' cn' : '' ) . '">' . $i
				. '<div style="font-weight:400;opacity:.7">' . $thu_vn[ $t ] . '</div></th>';
		}
		echo '<th>TỔNG</th></tr></thead><tbody>';

		$tong_cs = 0;
		foreach ( $ten as $ma => $ho_ten ) {
			$hts = array_keys( $o[ $ma ] );
			sort( $hts );                       /* hàng chính ('') luôn đứng đầu */
			$tong_nguoi = 0;
			foreach ( $o[ $ma ] as $ds_ngay ) {
				foreach ( $ds_ngay as $r ) { $tong_nguoi += (int) $r['phut']; }
			}
			$tong_cs += $tong_nguoi;

			foreach ( $hts as $k_ht => $ht ) {
				$chinh = ( '' === $ht );
				echo '<tr>';
				echo $chinh
					? '<td>' . esc_html( $ho_ten ) . '</td>'
					: '<td class="o" style="padding-left:20px">↳ <code>-' . esc_html( $ht ) . '</code></td>';
				$phut_dong = 0;
				for ( $i = 1; $i <= $so_ngay; $i++ ) {
					$ma_dd  = $ma . ( '' !== $ht ? '-' . $ht : '' );
					$ngay_o = sprintf( '%s-%02d', $tt, $i );
					$dang   = ( $ngay_o === $sg_n && 0 === strcasecmp( $ma_dd, (string) $sg_m ) );
					if ( ! isset( $o[ $ma ][ $ht ][ $i ] ) ) {
						echo '<td class="o' . ( $dang ? ' dang-sua' : '' ) . '">'
							. self::o_sua( '·', $ngay_o, $ma_dd, false, $duoc_sua, $duoc_bu )
							. '</td>';
						continue;
					}
					$r = $o[ $ma ][ $ht ][ $i ];
					/* Ba trạng thái khác nhau, ba ký hiệu khác nhau — gộp lại là xoá mất đúng
					   những ngày cần soi:
					     thiếu giờ ra   -> `?`  (quên bấm lúc về)
					     ra sớm hơn vào -> `—`  (dấu hiệu ghi sai)
					     bình thường    -> số giờ */
					if ( null === $r['phut'] ) {
						$thieu_ra = ( '' !== $r['vao'] && '' === $r['ra'] );
						echo '<td class="oc hong' . ( $dang ? ' dang-sua' : '' ) . '" title="' . esc_attr( self::ngay_vn( $r['ngay'] ) . ' · ' . $ho_ten
							. "\n" . ( '' !== $r['vao'] ? $r['vao'] : '—' ) . ' → '
							. ( '' !== $r['ra'] ? $r['ra'] : '—' ) . "\n"
							. ( $thieu_ra ? '⚠ thiếu giờ ra — quên bấm lúc về'
								: '⚠ giờ ra sớm hơn giờ vào — dấu hiệu ghi sai' ) ) . '">'
							. self::o_sua( ( $thieu_ra ? '?' : '—' ), (string) $r['ngay'], $ma_dd, true,
								$duoc_sua, $duoc_bu ) . '</td>';
						continue;
					}
					$phut_dong += (int) $r['phut'];
					/* Chú thích nói luôn ngày đó rơi vào ca nào, mỗi ca mấy tiếng — đúng câu anh
					   Thắng hỏi: *"làm ca nào, ca đó mấy tiếng, từ ca nào đến ca nào"*. */
					$tc  = VHCC_Ca::tach( $ds_ca, $r['vaoGiay'], $r['raGiay'],
						VHCC_Ca::la_cuoi_tuan( $r['ngay'] ) );
					$chu = self::ngay_vn( $r['ngay'] ) . ' · ' . $ho_ten
						. "\n" . $r['vao'] . ' → ' . $r['ra']
						. "\n" . VHCC_Cham::chu_gio( $r['phut'] );
					$td = VHCC_Ca::tu_den( $tc );
					if ( '' !== $td ) { $chu .= "\n" . $td; }
					$chu_ca = VHCC_Ca::chu( $tc );
					if ( '' !== $chu_ca ) { $chu .= "\n" . $chu_ca; }
					/* 🔴 MÃ CA IN THẲNG VÀO Ô, không giấu trong chú thích rê chuột.
					   Anh Thắng 26/08: *"hiện sẵn bạn ca nào ca nào luôn nhé, đi rà này rất khó"*.
					   Đúng: một tháng của 21 người là hơn 600 ô, rê chuột từng ô để biết ca thì
					   không ai rà nổi. Số giờ nằm trên, mã ca nằm dưới, và MÀU NỀN theo ca chính
					   — nhìn một cái là thấy cả tháng ai chạy ca nào. */
					$i_ca = VHCC_Ca::ca_chinh( $ds_ca, $tc );
					$ma_o = VHCC_Ca::ma_o( $ds_ca, $tc );
					echo '<td class="oc' . ( $i_ca >= 0 ? ' ca' . ( ( $i_ca % 4 ) + 1 ) : '' )
						. ( $dang ? ' dang-sua' : '' ) . '" title="' . esc_attr( $chu ) . '">'
						. self::o_sua( '<b>' . self::so_vp( round( $r['phut'] / 60, 1 ) ) . '</b>'
							. ( '' !== $ma_o ? '<div class="mca">' . esc_html( $ma_o ) . '</div>' : '' ),
							(string) $r['ngay'], $ma_dd, true, $duoc_sua, $duoc_bu )
						. '</td>';
				}
				echo $chinh && 1 === count( $hts )
					? '<td class="tong"><b>' . esc_html( VHCC_Cham::chu_gio( $tong_nguoi ) ) . '</b></td>'
					: ( $chinh
						? '<td class="tong"><b>' . esc_html( VHCC_Cham::chu_gio( $tong_nguoi ) ) . '</b>'
							. '<br><span class="mo">gồm cả hàng dưới</span></td>'
						: '<td class="tong"><span class="mo">' . esc_html( VHCC_Cham::chu_gio( $phut_dong ) )
							. '</span></td>' );
				echo '</tr>';
				/* Hàng sửa nội tuyến: chỉ mở cho ĐÚNG dòng vừa bấm, ngay dưới dòng ấy. */
				if ( '' !== $sg_n && 0 === strcasecmp( $ma . ( '' !== $ht ? '-' . $ht : '' ), (string) $sg_m ) ) {
					self::hang_sua( $so_ngay + 2, (string) $b['coSo'], $sg_n,
						$ma . ( '' !== $ht ? '-' . $ht : '' ), $sg_co, $ky, $toi );
				}
				unset( $k_ht );
			}
		}
		echo '<tr class="tong"><td>' . count( $ten ) . ' người</td>';
		echo '<td colspan="' . (int) $so_ngay . '"></td>';
		echo '<td><b>' . esc_html( VHCC_Cham::chu_gio( $tong_cs ) ) . '</b></td></tr>';
		echo '</tbody></table></div>';

		/* Chú giải mã ca — bắt buộc phải có, vì mã trong ô là C1/C2/C3 theo VỊ TRÍ, không phải
		   tên ca. Không có bảng quy đổi này thì mã trong ô là chữ vô nghĩa. */
		echo '<p class="mo" style="margin-top:8px">Mã ca trong ô: ';
		foreach ( $ds_ca as $i => $c ) {
			echo '<span class="k ca' . ( ( $i % 4 ) + 1 ) . '"><b>' . esc_html( VHCC_Ca::ma_ngan( $i ) )
				. '</b> ' . esc_html( $c['ten'] . ' ' . $c['tu'] . '–' . $c['den'] ) . '</span> ';
		}
		echo '<span class="k vang"><b>?</b> giờ không thuộc ca nào</span>';
		echo '<br>Ô có <b>hai mã</b> (VD <b>C1·C2</b>) là ngày đó vắt qua hai ca; nền tô theo ca '
			. '<b>ăn nhiều giờ nhất</b>. Rê chuột lên ô để đọc mỗi ca mấy tiếng.</p>';

		echo '<p class="mo" style="margin-top:8px">Ô là <b>số giờ làm</b> của ngày đó (giờ ra trừ giờ '
			. 'vào) · dấu <b>·</b> = không có dữ liệu chấm công · '
			. '<span class="k hong">?</span> = thiếu giờ ra (quên bấm lúc về) · '
			. '<span class="k hong">—</span> = giờ ra sớm hơn giờ vào, dấu hiệu ghi sai.'
			. '<br>Dòng <b>↳ <code>-CD</code></b> / <b><code>-TC</code></b> là hàng riêng của người đó '
			. '(ca đêm · tăng cường); TỔNG của dòng chính đã gồm cả mấy hàng ấy.'
			. '<br>⚠️ Đây là <b>giờ làm thực tế</b>, không phải giờ được trả tiền: tiền tính theo phần '
			. 'giao với khung ca đã xếp, nên hai số có thể lệch.</p>';
		echo '</div>';
	}

	/** Lưới người × ngày. Tách hàm để thử được riêng, không phải dựng cả trang. */
	private static function ve_luoi_vp( $b, $duoc_sua = false, $duoc_bu = false, $ky = '', $toi = array() ) {
		$tt   = (string) $b['month'];
		$rows = (array) $b['rows'];
		$moc  = strtotime( $tt . '-01 00:00:00 UTC' );
		if ( false === $moc ) {
			echo '<div class="bao loi">Tháng không hợp lệ.</div>';
			return;
		}
		$so_ngay = (int) gmdate( 't', $moc );
		$thu_vn  = array( 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' );

		/* Gom `detail` về [mã][số ngày]. `detail` CHỈ có ngày có dữ liệu — ngày nghỉ không có
		   dòng nào, nên ô để dấu `·` chứ không phải số 0. */
		$o = array();
		foreach ( (array) $b['detail'] as $d ) {
			$n = (int) substr( (string) $d['ngay'], 8, 2 );
			$o[ (string) $d['ma'] ][ $n ] = $d;
		}

		echo '<div class="the">';
		if ( ! $rows ) {
			echo '<p class="mo">Tháng ' . esc_html( $tt ) . ' chưa có dữ liệu chấm công nào ở cơ sở này. '
				. 'Nạp công từ .csv ở màn <b>Bảng chấm công</b>, hoặc chờ máy đẩy giờ về.</p></div>';
			return;
		}

		echo '<div class="cuon"><table class="cc"><thead><tr>';
		echo '<th>Nhân viên</th>';
		for ( $i = 1; $i <= $so_ngay; $i++ ) {
			$t  = (int) gmdate( 'w', strtotime( sprintf( '%s-%02d 00:00:00 UTC', $tt, $i ) ) );
			$cn = ( 0 === $t || 6 === $t );
			echo '<th class="ng' . ( $cn ? ' cn' : '' ) . '">' . $i
				. '<div style="font-weight:400;opacity:.7">' . $thu_vn[ $t ] . '</div></th>';
		}
		echo '<th>TỔNG</th></tr></thead><tbody>';

		list( $sg_n, $sg_m, $sg_co ) = self::o_dang_sua( $tt );
		$lech = 0;
		foreach ( $rows as $e ) {
			$ma  = (string) $e['ma'];
			$ngd = isset( $o[ $ma ] ) ? $o[ $ma ] : array();

			echo '<tr><td>' . esc_html( $e['ten'] )
				. ( ! empty( $e['laKeToan'] ) ? ' <span class="duoi">KT</span>' : '' ) . '</td>';
			$cong = 0.0;
			$co_dem = false;
			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				$ngay_o = sprintf( '%s-%02d', $tt, $i );
				$dang   = ( $ngay_o === $sg_n && 0 === strcasecmp( $ma, (string) $sg_m ) );
				if ( ! isset( $ngd[ $i ] ) ) {
					echo '<td class="o' . ( $dang ? ' dang-sua' : '' ) . '">'
						. self::o_sua( '·', $ngay_o, $ma, false, $duoc_sua, $duoc_bu ) . '</td>';
					continue;
				}
				$d = $ngd[ $i ];
				if ( $d['congDem'] || '' !== $d['h2vao'] || '' !== $d['h2ra'] ) { $co_dem = true; }
				$cong += (float) $d['tong'];

				/* Màu = LÝ DO, không phải trang trí. Đỏ là chỗ CÓ giờ mà KHÔNG ra công — đúng
				   thứ cần soi. */
				$lop = '';
				if ( ! empty( $d['caLa'] ) || ! empty( $d['demThieuGio'] ) ) { $lop = ' hong'; }
				elseif ( ! empty( $d['ktCnNghi'] ) )   { $lop = ' vang'; }
				elseif ( $d['congDem'] )               { $lop = ' tim'; }
				elseif ( $d['congTangCa'] )            { $lop = ' luc'; }

				echo '<td class="oc' . $lop . ( $dang ? ' dang-sua' : '' ) . '" title="'
					. esc_attr( self::chu_o_vp( $d, $e['ten'] ) ) . '">'
					. self::o_sua(
						( $d['tong'] ? '<b>' . self::so_vp( $d['tong'] ) . '</b>'
							: '<span class="chu-hong">0</span>' ),
						$ngay_o, $ma, true, $duoc_sua, $duoc_bu )
					. '</td>';
			}
			/* 🔴 Ô đối chiếu. Lưới cộng ra khác bảng tổng của engine = một trong hai chỗ sai,
			   phải kêu ngay chứ không im lặng in ra hai con số. */
			$cong = round( $cong, 2 );
			$khop = ( abs( $cong - (float) $e['tong'] ) < 0.005 );
			if ( ! $khop ) { $lech++; }
			echo '<td class="tong' . ( $khop ? '' : ' chu-hong' ) . '"><b>' . self::so_vp( $cong ) . '</b>'
				. ( $khop ? '' : ' ≠ ' . self::so_vp( $e['tong'] ) ) . '</td>';
			echo '</tr>';
			if ( '' !== $sg_n && 0 === strcasecmp( $ma, (string) $sg_m ) ) {
				self::hang_sua( $so_ngay + 2, (string) $b['station'], $sg_n, $ma, $sg_co, $ky, $toi );
			}

			/* Dòng con hàng -CD. Trong sổ mỗi người có thể chiếm nhiều hàng; lưới chỉ vẽ một dòng
			   thì hàng ca đêm biến mất khỏi tầm mắt dù công của nó ĐÃ cộng vào tổng.
			   Hai ngày KHÁC NHAU trong cùng dòng: ngày LÀM ca đêm hiện 🌙, ngày ĐƯỢC TÍNH công
			   hiện SỐ (ca đêm 04/08 cho công vào 05/08). */
			if ( ! $co_dem ) { continue; }
			echo '<tr><td class="o" style="padding-left:20px">↳ ca đêm <code>-CD</code></td>';
			$cong_d = 0.0;
			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				if ( ! isset( $ngd[ $i ] ) ) { echo '<td class="o">·</td>'; continue; }
				$d = $ngd[ $i ];
				$lam = ( '' !== $d['h2vao'] || '' !== $d['h2ra'] );
				if ( ! $lam && ! $d['congDem'] && empty( $d['demThieuGio'] ) && empty( $d['demChuaDuCap'] ) ) {
					echo '<td class="o">·</td>';
					continue;
				}
				$cong_d += (float) $d['congDem'];
				$lop = ! empty( $d['demThieuGio'] ) ? ' hong' : ( $d['congDem'] ? ' tim' : '' );
				echo '<td class="oc' . $lop . '" title="' . esc_attr( self::chu_dem_vp( $d, $e['ten'] ) ) . '">'
					. ( $d['congDem'] ? '<b>' . self::so_vp( $d['congDem'] ) . '</b>'
						: ( ! empty( $d['demThieuGio'] ) ? '0' : ( $lam ? '🌙' : '·' ) ) ) . '</td>';
			}
			echo '<td class="tong">' . self::so_vp( $cong_d ) . '</td></tr>';
		}
		echo '</tbody></table></div>';

		echo '<p class="mo" style="margin-top:8px">Ô là <b>số công</b> của ngày đó · dấu '
			. '<b>·</b> = không có dữ liệu chấm công · '
			. '<span class="k luc">có tăng ca</span> <span class="k tim">có công đêm</span> '
			. '<span class="k vang">kế toán chấm chủ nhật</span> '
			. '<span class="k hong">có giờ nhưng KHÔNG ra công</span>'
			. '<br>Dòng <b>↳ ca đêm <code>-CD</code></b> là hàng thứ hai của người đó: '
			. '<b>🌙</b> = đêm đó CÓ làm · <b>số</b> = công đêm được tính vào ngày đó '
			. '(ca đêm đêm trước cho công sang hôm sau).';
		echo $lech
			? '<br><b class="chu-hong">⚠️ ' . (int) $lech . ' người có tổng ở lưới KHÁC tổng của phép '
				. 'tính — đừng dùng số nào cả, báo lại để tra.</b></p>'
			: '<br><span class="chu-luc">✓ Tổng từng người khớp với phép tính.</span></p>';
		echo '</div>';
	}

	/** 1.5 -> "1.5", 2.0 -> "2". Số công hay là số lẻ .5, in ".00" vào chỉ tổ chật lưới. */
	private static function so_vp( $n ) {
		$n = round( (float) $n, 2 );
		return rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
	}

	/** Chú thích rê chuột của một ô ngày — nói VÌ SAO ô đó ra con số ấy. */
	private static function chu_o_vp( $d, $ten ) {
		$c = array( self::ngay_vn( $d['ngay'] ) . ' · ' . $ten );
		if ( '' !== $d['vao'] || '' !== $d['ra'] ) {
			$c[] = ( '' !== $d['vao'] ? $d['vao'] : '—' ) . ' → ' . ( '' !== $d['ra'] ? $d['ra'] : '—' );
		}
		if ( $d['gioNgay'] )    { $c[] = self::so_vp( $d['gioNgay'] ) . 'h trong khung ' . $d['khung']; }
		if ( $d['congNgay'] )   { $c[] = 'ngày ' . self::so_vp( $d['congNgay'] ); }
		if ( $d['congTangCa'] ) { $c[] = 'tăng ca ' . self::so_vp( $d['congTangCa'] ); }
		if ( $d['congDem'] )    { $c[] = 'đêm ' . self::so_vp( $d['congDem'] ); }
		if ( $d['congBu'] )     { $c[] = 'bù ' . self::so_vp( $d['congBu'] ); }
		if ( ! empty( $d['caLa'] ) )       { $c[] = '⚠ hàng 2 nằm trong ca ngày → KHÔNG tính'; }
		if ( ! empty( $d['demThieuGio'] ) ) {
			$c[] = '⚠ ca đêm ' . self::so_vp( $d['gioDemThuc'] ) . 'h < mức tối thiểu';
		}
		if ( ! empty( $d['ktCnNghi'] ) )   { $c[] = '⚠ kế toán chấm chủ nhật → 0 công'; }
		return implode( "\n", $c );
	}

	/** Chú thích rê chuột của ô dòng ca đêm. */
	private static function chu_dem_vp( $d, $ten ) {
		$c = array( self::ngay_vn( $d['ngay'] ) . ' · ' . $ten );
		if ( '' !== $d['h2vao'] || '' !== $d['h2ra'] ) {
			$c[] = 'hàng 2 (' . ( '' !== $d['h2vao'] ? $d['h2vao'] : '—' ) . ' → '
				. ( '' !== $d['h2ra'] ? $d['h2ra'] : '—' ) . ')';
		}
		if ( '' !== $d['demSangNgay'] ) { $c[] = 'ca đêm này cho công vào ngày ' . self::ngay_vn( $d['demSangNgay'] ); }
		if ( '' !== $d['demTuNgay'] )   { $c[] = 'công đêm nhận từ ca ngày ' . self::ngay_vn( $d['demTuNgay'] ); }
		if ( ! empty( $d['demThieuGio'] ) ) {
			$c[] = '⚠ ca đêm chỉ ' . self::so_vp( $d['gioDemThuc'] ) . 'h < mức tối thiểu → KHÔNG tính';
		}
		if ( ! empty( $d['demChuaDuCap'] ) ) { $c[] = '⚠ thiếu giờ vào hoặc giờ ra → không đo được, vẫn tính đủ'; }
		return implode( "\n", $c );
	}

	private static function ngay_vn( $ngay ) {
		$p = explode( '-', (string) $ngay );
		return ( 3 === count( $p ) ) ? $p[2] . '/' . $p[1] . '/' . $p[0] : (string) $ngay;
	}

	/**
	 * Khối CHẤM CÔNG BÙ — cửa ghi giờ thứ ba, xem `VHCC_Bu` cho lý do nó được phép tồn tại.
	 *
	 * Ô ngày và mã điền sẵn từ dòng anh/chị bấm 🚩 ở bảng chi tiết, y như khối cờ: hai việc đi
	 * cùng một dòng dữ liệu, bắt gõ lại ngày và mã cho từng việc là mời gõ nhầm.
	 */
	private static function the_bu( $cs, $tt, $ky, $toi, $thieu ) {
		if ( ! VHCC_Vai::duoc( $toi, 'cham_bu' ) ) { return; }
		$o_loc  = self::o_loc();
		$g_ngay = isset( $_GET['gnd'] ) ? sanitize_text_field( wp_unslash( $_GET['gnd'] ) ) : '';
		$g_ma   = isset( $_GET['gma'] ) ? sanitize_text_field( wp_unslash( $_GET['gma'] ) ) : '';

		echo '<div class="the" id="bucong"><h2>Chấm công bù</h2>';
		echo '<p class="mo">Dùng khi <b>máy hỏng</b> hoặc <b>nhân viên quên bấm</b>. Bù chỉ điền vào '
			. 'ô <b>còn trống</b> — giờ máy đã ghi thì bù không đè lên được. Mỗi lượt bù đều vào sổ '
			. 'nhật ký (ai bù · cho ai · vì sao) và <b>không xoá được</b>.</p>';
		echo '<p class="mo">⚠️ Không tự bù cho mình được, kể cả Admin — nhờ người khác bù giúp. '
			. 'Bù công là đổi thẳng ra tiền, nên chỗ này không để hở.</p>';
		if ( $thieu ) {
			echo '<p class="mo">Đang có <b>' . count( $thieu ) . '</b> ngày thiếu giờ ra ở bảng trên — '
				. 'bấm 🚩 ở dòng nào thì ngày và mã của dòng đó điền sẵn xuống đây.</p>';
		}
		/* ⚠️ `o_loc()` cũng chở `ccs` (nó nằm trong THAM_SO), nên form có HAI ô cùng tên và ô SAU
		   thắng. Phải để ô của màn này đứng SAU: `$cs` đã qua bộ lọc bộ phận — chọn một bộ phận
		   mà cơ sở cũ rơi ra ngoài thì `$cs` thành rỗng, trong khi `?ccs=` trên thanh địa chỉ vẫn
		   giữ cơ sở cũ. Để o_loc thắng là bù giờ vào một cơ sở mà màn hình không hề đang hiện. */
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="bu">' . $o_loc
			. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">';
		echo '<div class="luoi">';
		echo '<div><label for="bu_ngay">Ngày *</label><input id="bu_ngay" name="ngay" type="date" required'
			. ' value="' . esc_attr( $g_ngay ) . '"'
			. ' max="' . esc_attr( (string) current_time( 'Y-m-d' ) ) . '"></div>';
		echo '<div><label for="bu_ma">Mã NV *</label><input id="bu_ma" name="ma_nv" required'
			. ' value="' . esc_attr( $g_ma ) . '" placeholder="MNNV… (kèm -CD nếu là ca đêm)"></div>';
		echo '<div><label for="bu_vao">Giờ vào</label><input id="bu_vao" name="bu_vao" type="time"></div>';
		echo '<div><label for="bu_ra">Giờ ra</label><input id="bu_ra" name="bu_ra" type="time"></div>';
		echo '</div>';
		echo '<p><label for="bu_ly">Vì sao phải bù *</label>'
			. '<input id="bu_ly" name="ly_do" required minlength="5" style="width:100%" '
			. 'placeholder="VD: máy chấm công mất điện sáng 12/8 — có camera đối chiếu"></p>';
		echo '<p><button class="chinh">Bù giờ</button></p></form>';

		/* 🔴 MỘT SỔ CHO CẢ HAI VIỆC — bù và sửa đè cùng ghi vào bảng `cham_bu`.
		   Tách làm hai sổ thì người soát phải mở hai chỗ mới dựng lại được chuyện gì đã xảy ra
		   với một ngày công; mà thứ họ cần biết là "ngày này ai đã động vào, mấy lần", không
		   phải "ai đã bù" và "ai đã sửa" thành hai câu chuyện rời nhau. */
		$nk = VHCC_Bu::ds_nhat_ky( $toi, $cs, $tt );
		if ( $nk ) {
			echo '<h3 style="margin:14px 0 6px">Đã động vào tháng này (' . count( $nk ) . ')</h3>';
			echo '<div class="cuon"><table><thead><tr><th>Ngày</th><th>Mã NV</th><th>Việc</th>'
				. '<th>Ô</th><th>Giờ cũ</th><th>Giờ mới</th><th>Lý do</th><th>Người làm</th>'
				. '</tr></thead><tbody>';
			foreach ( $nk as $x ) {
				$la_sua = ( 'sua' === ( isset( $x['viec'] ) ? $x['viec'] : 'bu' ) );
				echo '<tr><td>' . esc_html( $x['ngay'] ) . '</td>';
				echo '<td>' . esc_html( $x['ma_nv'] ) . '</td>';
				echo '<td>' . ( $la_sua ? '<span class="k hong">sửa đè</span>' : '<span class="k luc">bù</span>' ) . '</td>';
				echo '<td>' . ( 'vao' === $x['o_gio'] ? 'giờ vào' : 'giờ ra' ) . '</td>';
				/* Cột giờ cũ chỉ có nghĩa với lượt SỬA. Lượt bù thì ô vốn trống — in '—' ở đó là
				   đúng, in '00:00' thì lại thành một con số trông như thật. */
				echo '<td>' . esc_html( $la_sua
					? VHCC_Bu::hhmm_hoac_trong( isset( $x['gio_cu_giay'] ) && '' !== $x['gio_cu_giay']
						? $x['gio_cu_giay'] : null )
					: '—' ) . '</td>';
				echo '<td><b>' . esc_html( VHCC_Bu::hhmm_hoac_trong(
					( null === $x['gio_giay'] || '' === $x['gio_giay'] ) ? null : $x['gio_giay'] ) )
					. '</b></td>';
				echo '<td style="white-space:pre-wrap;max-width:340px">' . esc_html( $x['ly_do'] ) . '</td>';
				echo '<td>' . esc_html( $x['nguoi_bu'] ) . '<br><span class="mo">'
					. esc_html( substr( (string) $x['tao_luc'], 0, 16 ) ) . '</span></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	/* 🔴 KHỐI "SỬA GIỜ CÔNG" Ở CUỐI MÀN ĐÃ BỎ (anh Thắng 26/08/2026: *"Loại bỏ chỗ này. Chỗ này
	   đã hiện đủ rồi."*).
	   Hàng sửa nội tuyến trong lưới (`hang_sua`) làm đúng việc ấy mà không bắt ai gõ lại ngày và
	   mã: bấm ô nào thì biểu mẫu mở dưới đúng dòng ấy. Để lại cả hai là hai biểu mẫu giống hệt
	   nhau trên cùng một màn, và người dùng phải đoán cái nào mới là cái đang dùng.
	   ⚠️ Khối "Chấm công bù" thì GIỮ: người chưa có dòng nào trong tháng thì lưới không vẽ hàng
	      của họ, tức là không có ô nào để bấm — bù cho họ phải đi bằng đường khác. */

	/**
	 * Khối NẠP CÔNG TỪ .CSV.
	 *
	 * 🔴 Khối này còn có việc thứ hai, quan trọng không kém việc nạp: NÓI RA cho anh Thắng biết
	 *    nút "Nạp .csv" ở màn Hồ sơ không nạp giờ công. Anh nạp 240 hồ sơ rồi hỏi *"Sao dữ liệu
	 *    chấm công chưa vào, anh nạp rồi mà"* — và hệ thống lúc đó không có một câu nào phân biệt
	 *    hai thứ. Một màn im lặng đúng vẫn là một màn sai.
	 */
	private static function the_nap_cong( $cs, $ky, $toi, $ds_cs = array() ) {
		if ( ! VHCC_Vai::duoc( $toi, 'nap_cong' ) ) { return; }
		echo '<div class="the" id="napcong"><h2>Nạp công từ .csv (Sheets cũ)</h2>';
		echo '<p class="mo">Nhận đúng tệp <b>"Bảng chạy · Hệ Thống Chấm Công Cơ Sở"</b> xuất từ '
			. 'Google Sheets — dạng bảng ngang, mỗi ngày một cụm cột. Một tệp chứa nhiều tháng '
			. 'chồng nhau cũng đọc được.</p>';
		echo '<p class="mo">⚠️ Đây là chỗ DUY NHẤT nạp <b>giờ công</b>. Nút "Nạp .csv" ở màn '
			. '<b>Hồ sơ &amp; tài khoản</b> nạp <b>sổ nhân sự</b> (ai, mã gì, PIN gì) — nạp xong '
			. 'bảng công vẫn trắng là đúng, không phải mất dữ liệu.</p>';
		echo '<p class="mo">Nạp lại bao nhiêu lần cũng không sinh trùng, và <b>không bao giờ xoá bớt '
			. 'giờ đã có</b>: tệp cũ thiếu giờ ra thì giờ ra do máy ghi vẫn nguyên.</p>';

		/* Ô chọn cơ sở RIÊNG của khối này, không mượn ô lọc ở trên.
		   ⚠️ Mượn ô trên thì khối nạp chỉ dùng được sau khi đã bấm Xem — mà lúc chưa có dữ liệu
		      thì bảng trống, người ta không có lý do gì để bấm Xem, nên không bao giờ tới được
		      cái nút này. Khối nạp phải đứng được MỘT MÌNH. */
		if ( ! $ds_cs ) { $ds_cs = self::ds_coso_xem( $toi ); }
		if ( ! $ds_cs ) {
			echo '<p class="mo">Tài khoản này chưa được gán cơ sở nào — nhờ Admin khai ô '
				. '"Cửa hàng phụ trách" ở màn Hồ sơ.</p></div>';
			return;
		}
		/* Khối nạp có ô CHỌN cơ sở riêng (`name="ccs"`) nằm dưới, nên `o_loc()` phải đứng TRƯỚC
		   nó — kẻo ô ẩn chở `ccs` cũ thắng cái ô người ta vừa chọn. */
		echo '<form method="post" enctype="multipart/form-data">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc();
		echo '<div class="hang">';
		echo '<div><label for="ncs">Nạp vào cơ sở *</label><select id="ncs" name="ccs" required>';
		if ( '' === $cs ) { echo '<option value="">— chọn cơ sở —</option>'; }
		foreach ( $ds_cs as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . selected( $x, $cs, false ) . '>'
				. esc_html( $x ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="ncsm">…hoặc cơ sở MỚI</label>'
			. '<input id="ncsm" name="ccs_moi" placeholder="VD: JP_SANBAY" style="width:180px"></div>';
		echo '<div><label for="ntep">Tệp .csv *</label>'
			. '<input id="ntep" type="file" name="tep" accept=".csv,.tsv,.txt" required></div>';
		echo '<div><button name="viec" value="xem_cong">Xem trước</button></div>';
		echo '<div><button class="chinh" name="viec" value="nap_cong">Nạp thật</button></div>';
		echo '</div>';
		echo '<p class="mo">Cơ sở <b>chưa có trong danh sách</b> (mới mở, hoặc chưa ai chấm công '
			. 'lần nào) thì gõ mã vào ô <b>cơ sở MỚI</b> — ô ấy thắng ô xổ xuống. Xem trước sẽ nói '
			. 'rõ đây là cơ sở chưa từng có, trước khi anh/chị bấm Nạp thật.</p>';
		echo '<p class="mo">Bấm <b>Xem trước</b> đi đã: nó đếm và kể ra mọi chỗ lạ mà '
			. '<b>không ghi gì</b>. Bốn con số nó in ra phải khớp với tệp đang cầm trên tay.</p>';
		echo '</form></div>';
	}

	/** Khối cờ: gắn mới · danh sách đang chờ · ngày thiếu giờ ra. */
	private static function the_co( $b, $cs, $tt, $ky, $thieu ) {
		$o_loc = self::o_loc();

		if ( $thieu ) {
			echo '<div class="the"><h2>Ngày thiếu giờ ra (' . count( $thieu ) . ')</h2>';
			echo '<p class="mo">Có giờ vào mà không có giờ ra — hệ thống <b>không tự điền</b>: điền là '
				. 'bịa ra số giờ làm cho một ngày, mà số đó thành tiền. Gắn cờ để còn tra lại.</p>';
			echo '<div class="cuon"><table><thead><tr><th>Ngày</th><th>Mã NV</th><th>Họ tên</th>'
				. '<th>Giờ vào</th></tr></thead><tbody>';
			foreach ( $thieu as $x ) {
				echo '<tr><td>' . esc_html( $x['ngay'] ) . '</td><td>' . esc_html( $x['maNV'] )
					. ( '' !== $x['hauTo'] ? ' <span class="duoi">' . esc_html( $x['hauTo'] ) . '</span>' : '' )
					. '</td><td>' . esc_html( $x['hoTen'] ) . '</td><td>'
					. esc_html( substr( (string) $x['vao'], 0, 5 ) ) . '</td></tr>';
			}
			echo '</tbody></table></div></div>';
		}

		/* Giá trị bấm 🚩 ở bảng chi tiết gửi sang — điền sẵn chứ không gắn thay người ta. */
		$g_ngay = isset( $_GET['gnd'] ) ? sanitize_text_field( wp_unslash( $_GET['gnd'] ) ) : '';
		$g_ma   = isset( $_GET['gma'] ) ? sanitize_text_field( wp_unslash( $_GET['gma'] ) ) : '';
		$g_ten  = isset( $_GET['gten'] ) ? sanitize_text_field( wp_unslash( $_GET['gten'] ) ) : '';

		echo '<div class="the" id="gancoform"><h2>Gắn cờ cần kiểm</h2>';
		echo '<p class="mo">Cờ KHÔNG đụng vào giờ đã ghi. Nó chỉ ghi lại <b>ngày nào, của ai, nghi gì</b> '
			. 'để người duyệt lương biết mà tra.</p>';
		if ( '' !== $g_ngay || '' !== $g_ma ) {
			echo '<p class="mo">Đã điền sẵn từ dòng anh/chị vừa bấm — <b>còn thiếu lý do</b>, '
				. 'ghi vào ô dưới rồi bấm Gắn cờ.</p>';
		}
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="co">' . $o_loc;
		echo '<div class="luoi">';
		echo '<div><label for="co_ngay">Ngày *</label><input id="co_ngay" name="ngay" type="date" required '
			. 'value="' . esc_attr( $g_ngay ) . '" '
			. 'min="' . esc_attr( $tt . '-01' ) . '" max="' . esc_attr( $tt . '-' . gmdate( 't', (int) strtotime( $tt . '-01' ) ) ) . '"></div>';
		echo '<div><label for="co_ma">Mã NV</label><input id="co_ma" name="ma_nv" placeholder="MNNV…" '
			. 'value="' . esc_attr( $g_ma ) . '"></div>';
		echo '<div><label for="co_ten">Họ tên</label><input id="co_ten" name="ho_ten" '
			. 'value="' . esc_attr( $g_ten ) . '"></div>';
		echo '</div>';
		echo '<p><label for="co_nd">Cần kiểm gì *</label>'
			. '<textarea id="co_nd" name="ghi_chu" rows="2" required style="width:100%" '
			. 'placeholder="VD: quên check-out, giờ ra 23:50 nhưng cửa hàng đóng 22:00"></textarea></p>';
		echo '<p><button class="chinh">Gắn cờ</button></p></form></div>';

		$cho = array();
		$xong = array();
		foreach ( (array) $b['co'] as $c ) {
			if ( 'Đã xử lý' === (string) $c['trang_thai'] ) { $xong[] = $c; } else { $cho[] = $c; }
		}
		echo '<div class="the"><h2>Cờ tháng này (' . count( (array) $b['co'] ) . ')</h2>';
		if ( ! $b['co'] ) {
			echo '<p class="mo">Chưa có cờ nào.</p></div>';
			return;
		}
		echo '<div class="cuon"><table><thead><tr><th>Ngày</th><th>Người</th><th>Nội dung</th>'
			. '<th>Người gắn</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
		foreach ( array_merge( $cho, $xong ) as $c ) {
			$da = ( 'Đã xử lý' === (string) $c['trang_thai'] );
			echo '<tr><td>' . esc_html( $c['ngay'] ) . '</td>';
			echo '<td>' . esc_html( $c['ho_ten'] ? $c['ho_ten'] : $c['ma_nv'] )
				. ( $c['ho_ten'] && $c['ma_nv'] ? '<br><span class="mo">' . esc_html( $c['ma_nv'] ) . '</span>' : '' )
				. '</td>';
			echo '<td style="white-space:pre-wrap;max-width:420px">' . esc_html( $c['ghi_chu'] ) . '</td>';
			echo '<td>' . esc_html( $c['nguoi_gan'] ) . '<br><span class="mo">'
				. esc_html( substr( (string) $c['tao_luc'], 0, 16 ) ) . '</span></td>';
			echo '<td><span class="' . ( $da ? 'co' : 'chua' ) . '">' . esc_html( $c['trang_thai'] ) . '</span></td>';
			echo '<td>';
			if ( ! $da ) {
				echo '<form method="post" style="margin:0">'
					. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
					. '<input type="hidden" name="viec" value="xu_ly_co">'
					. '<input type="hidden" name="flag_id" value="' . esc_attr( $c['flag_id'] ) . '">'
					. $o_loc
					. '<input name="ket_luan" placeholder="kết luận" style="width:150px">'
					. ' <button>Xong</button></form>';
			} else {
				echo '<span class="mo">' . esc_html( substr( (string) $c['xu_ly_luc'], 0, 16 ) ) . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private static function ve_bao( $b ) {
		if ( isset( $b['loi'] ) ) {
			echo '<div class="bao loi"><b>Không xong.</b> ' . esc_html( $b['loi'] ) . '</div>';
			return;
		}
		if ( isset( $b['xong'] ) ) {
			echo '<div class="bao ok">' . esc_html( $b['xong'] ) . '</div>';
			return;
		}
		if ( isset( $b['pin_moi'] ) ) {
			echo '<div class="bao canh"><b>Đã khai tài khoản Admin toàn quyền: '
				. esc_html( $b['pin_moi']['ten'] ) . '</b><br>PIN — <span class="pin">'
				. esc_html( $b['pin_moi']['pin'] ) . '</span><br>'
				. '<span class="mo">Ghi lại NGAY. Rời trang này là không xem lại được, '
				. 'và hệ thống không lưu chỗ nào khác để in ra.</span></div>';
			return;
		}
		if ( isset( $b['viec'] ) && ( 'nap_cong' === $b['viec'] || 'xem_cong' === $b['viec'] ) ) {
			self::ve_bao_cong( $b );
			return;
		}
		if ( isset( $b['viec'] ) && ( 'nap_csv' === $b['viec'] || 'xem_csv' === $b['viec'] ) ) {
			self::ve_bao_csv( $b, 'xem_csv' === $b['viec'] );
			return;
		}
		if ( isset( $b['viec'] ) && 'nap_tk' === $b['viec'] ) {
			echo '<div class="bao ' . ( $b['them'] ? 'ok' : 'canh' ) . '">Nạp <b>' . (int) $b['them']
				. '</b> tài khoản đăng nhập từ hồ sơ. Hiện <b>' . (int) $b['vao']
				. '</b> người đăng nhập được.</div>';
			if ( ! empty( $b['vt_trong'] ) ) {
				echo '<div class="bao canh">' . (int) $b['vt_trong'] . ' người hồ sơ không ghi vai trò '
					. 'đăng nhập — đã đặt thành <b>' . esc_html( $b['vt_mac_dinh'] ) . '</b>.'
					. ( empty( $b['vao'] ) ? ' <b>Hiện KHÔNG AI đăng nhập được</b> — chọn lại vai trò rồi nạp lại.' : '' )
					. '</div>';
			}
			if ( ! empty( $b['bo'] ) ) {
				echo '<div class="bao loi"><b>' . count( (array) $b['bo'] ) . ' dòng bỏ qua:</b><ul>';
				foreach ( (array) $b['bo'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
				echo '</ul></div>';
			}
			return;
		}
	}

	/**
	 * Kết quả nạp công.
	 *
	 * 🔴 Kể ĐỦ BỐN con số (khối · tháng · người · lượt) chứ không chỉ "đã nạp xong". Bộ đọc này
	 *    đoán bố cục từ hàng tiêu đề, nên cách duy nhất để biết nó đoán đúng là nhìn bốn con số
	 *    ấy có khớp với tệp đang cầm trên tay không. "Nạp xong" thì lúc đọc sai cũng in ra y hệt.
	 */
	private static function ve_bao_cong( $b ) {
		if ( empty( $b['ok'] ) ) {
			echo '<div class="bao loi"><b>Không đọc được tệp.</b> ' . esc_html( $b['error'] ) . '</div>';
			if ( ! empty( $b['canh'] ) ) {
				echo '<div class="bao canh"><ul>';
				foreach ( array_slice( (array) $b['canh'], 0, 20 ) as $c ) { echo '<li>' . esc_html( $c ) . '</li>'; }
				echo '</ul></div>';
			}
			return;
		}
		$xem = ! empty( $b['chi_xem'] );
		echo '<div class="bao ' . ( $xem ? 'canh' : 'ok' ) . '">';
		echo '<b>' . ( $xem ? 'XEM TRƯỚC — chưa ghi gì vào bảng công.' : 'Đã nạp vào bảng công.' ) . '</b><br>';
		echo 'Cơ sở <b>' . esc_html( $b['coSo'] ) . '</b> · '
			. esc_html( (string) $b['so_khoi'] ) . ' bảng trong tệp · tháng <b>'
			. esc_html( $b['thang'] ) . '</b> · ' . esc_html( (string) $b['so_ngay'] )
			. ' ngày có công · <b>' . esc_html( (string) $b['so_nguoi'] )
			. '</b> người · <b>' . esc_html( (string) $b['so_luot'] ) . '</b> lượt giờ';
		if ( ! $xem ) { echo ' · đã ghi <b>' . esc_html( (string) $b['da_ghi'] ) . '</b>'; }
		echo '.<br><span class="mo">Bốn con số này phải khớp với tệp đang cầm trên tay. Lệch là bộ đọc '
			. 'đoán nhầm bố cục cột — đừng bấm Nạp thật.</span></div>';

		/* 🔴 Hai cảnh báo NẶNG hơn mọi thứ khác, nên đặt ngay trên cùng. */
		if ( ! empty( $b['lech_ten'] ) ) {
			echo '<div class="bao loi"><b>⚠️ TÊN TỆP KHÔNG KHỚP CƠ SỞ ĐANG CHỌN.</b><br>'
				. 'Tệp trông như của cơ sở <b>' . esc_html( $b['lech_ten'] ) . '</b>, '
				. 'nhưng đang nạp vào <b>' . esc_html( $b['coSo'] ) . '</b>.<br>'
				. '<span class="mo">Nạp nhầm cửa hàng thì cả tháng công chui vào sổ của nơi khác mà '
				. 'không câu nào báo. Kiểm lại ô cơ sở trước khi bấm Nạp thật.</span></div>';
		}
		if ( ! empty( $b['la_moi'] ) ) {
			echo '<div class="bao canh"><b>Cơ sở "' . esc_html( $b['coSo'] ) . '" CHƯA có trong hệ thống.</b><br>'
				. ( ! empty( $b['chi_xem'] )
					? 'Bấm Nạp thật là nó được TẠO MỚI cùng với số công trong tệp. '
					: 'Đã tạo mới cùng với số công trong tệp. ' )
				. '<span class="mo">Gõ sai một ký tự là đẻ ra một cơ sở ma mang cả tháng công, mà '
				. 'nhìn bảng thì trông y như thật. Soi lại mã cho chắc.</span></div>';
		}
		if ( ! empty( $b['la'] ) ) {
			echo '<div class="bao canh"><b>' . count( (array) $b['la'] ) . ' mã có công nhưng CHƯA có hồ sơ:</b> '
				. esc_html( implode( ' · ', array_keys( (array) $b['la'] ) ) )
				. '<br><span class="mo">Giờ vẫn nạp, nhưng bảng lương sẽ không biết mấy mã này là ai. '
				. 'Khai hồ sơ ở màn Hồ sơ &amp; tài khoản rồi số liệu tự khớp lại.</span></div>';
		}
		if ( ! empty( $b['canh'] ) ) {
			$ds = (array) $b['canh'];
			echo '<div class="bao canh"><b>' . count( $ds ) . ' chỗ cần biết:</b><ul>';
			foreach ( array_slice( $ds, 0, 30 ) as $c ) { echo '<li>' . esc_html( $c ) . '</li>'; }
			echo '</ul>';
			if ( count( $ds ) > 30 ) { echo '<span class="mo">…và ' . ( count( $ds ) - 30 ) . ' dòng nữa.</span>'; }
			echo '</div>';
		}
	}

	/** 🔴 Kể TỪNG Ô đổi gì — chỗ này mới là thứ cho thấy bản đồ cột có sai không. */
	private static function ve_bao_csv( $r, $xem ) {
		if ( empty( $r['ok'] ) ) {
			echo '<div class="bao loi"><b>Không nạp được.</b> ' . esc_html( $r['error'] ) . '</div>';
			return;
		}
		echo '<div class="bao ' . ( $r['them'] + $r['sua'] ? 'ok' : 'canh' ) . '">'
			. ( $xem ? '<b>XEM TRƯỚC — chưa ghi gì.</b> ' : '<b>Đã nạp.</b> ' )
			. 'Đọc ' . (int) $r['so_dong'] . ' dòng · thêm <b>' . (int) $r['them']
			. '</b> · đổi <b>' . (int) $r['sua'] . '</b> hồ sơ.';
		if ( ! empty( $r['coso'] ) ) {
			echo ' Chỉ cơ sở <b>' . esc_html( $r['coso'] ) . '</b>, bỏ qua ' . (int) $r['lech'] . ' người nơi khác.';
		}
		echo '<br><span class="mo">Cột lấy được: ' . esc_html( implode( ' · ', (array) $r['cot'] ) ) . '</span>';
		echo '</div>';

		if ( ! empty( $r['cot_la'] ) ) {
			echo '<div class="bao canh"><b>' . count( (array) $r['cot_la'] ) . ' cột KHÔNG nhận ra, đã bỏ qua:</b> '
				. esc_html( implode( ' · ', (array) $r['cot_la'] ) ) . '</div>';
		}
		if ( ! empty( $r['doi'] ) ) {
			echo '<div class="the"><h2>' . ( $xem ? 'Sẽ đổi những ô này' : 'Đã đổi những ô này' ) . '</h2>';
			echo '<p class="mo">Sai bản đồ cột thì lộ ra ngay ở bảng này — ví dụ Chức vụ nhảy sang '
				. 'Nhiệm vụ, hay PIN rơi vào CCCD. Thấy sai thì <b>đừng bấm Nạp</b>, báo lại để sửa cách đọc cột.</p>';
			echo '<div class="cuon"><table><thead><tr><th>Mã NV</th><th>Họ tên</th><th>Ô</th>'
				. '<th>Đang là</th><th>Sẽ thành</th></tr></thead><tbody>';
			foreach ( (array) $r['doi'] as $ma => $x ) {
				if ( ! empty( $x['moi'] ) ) {
					echo '<tr><td>' . esc_html( $ma ) . '</td><td>' . esc_html( $x['ten'] )
						. '</td><td colspan="3"><span class="moi">hồ sơ mới</span></td></tr>';
					continue;
				}
				$dau_tien = true;
				foreach ( (array) $x['o'] as $c => $v ) {
					echo '<tr><td>' . ( $dau_tien ? esc_html( $ma ) : '' ) . '</td><td>'
						. ( $dau_tien ? esc_html( $x['ten'] ) : '' ) . '</td><td>' . esc_html( $c ) . '</td>'
						. '<td><span class="cu">' . esc_html( '' === $v['cu'] ? '(trống)' : $v['cu'] ) . '</span></td>'
						. '<td><span class="moi">' . esc_html( '' === $v['moi'] ? '(trống)' : $v['moi'] ) . '</span></td></tr>';
					$dau_tien = false;
				}
			}
			echo '</tbody></table></div>';
			if ( count( (array) $r['doi'] ) >= 200 ) {
				echo '<p class="mo">Chỉ liệt kê 200 hồ sơ đầu — còn nữa.</p>';
			}
			echo '</div>';
		}
		if ( ! empty( $r['bo'] ) ) {
			echo '<div class="bao loi"><b>' . count( (array) $r['bo'] ) . ' dòng KHÔNG nạp được:</b><ul>';
			foreach ( (array) $r['bo'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
			echo '</ul></div>';
		}
		if ( ! empty( $r['canh'] ) ) {
			echo '<div class="bao canh"><ul>';
			foreach ( (array) $r['canh'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
			echo '</ul></div>';
		}
	}

	private static function the_nap_csv( $ky, $tong ) {
		$lui = VHCC_NapCsv::co_lui();
		echo '<div class="the"><h2>📥 Nạp hồ sơ nhân viên từ file .csv</h2>';
		echo '<p class="mo">Google Sheets → <b>File → Tải xuống → Giá trị được phân tách bằng dấu phẩy '
			. '(.csv)</b>. Lấy đủ mọi cột. Khớp theo <b>Mã NV</b> nên nạp lại là cập nhật, không nhân đôi. '
			. 'Ô để trống trong file <b>không</b> xoá dữ liệu đang có. Hiện có <b>' . (int) $tong . '</b> hồ sơ.</p>';
		echo '<form method="post" enctype="multipart/form-data">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc();
		echo '<div class="hang">';
		echo '<div><label for="tep">File .csv</label><input id="tep" type="file" name="tep" '
			. 'accept=".csv,.tsv,.txt" required></div>';
		echo '<div><label for="cs">Chỉ nhận cơ sở</label>'
			. '<input id="cs" name="coso" placeholder="trống = nhận hết" style="width:170px"></div>';
		echo '<button name="viec" value="xem_csv">Xem trước</button>';
		echo '<button class="chinh" name="viec" value="nap_csv">Nạp</button>';
		echo '</div></form>';
		echo '<p class="mo"><b>Luôn bấm Xem trước trước.</b> Bảng "sẽ đổi những ô này" cho thấy '
			. 'từng ô <i>đang là</i> → <i>sẽ thành</i>, nên đọc sai cột là thấy ngay, trước khi ghi đè.</p>';
		if ( ! empty( $lui['luc'] ) ) {
			echo '<form method="post" style="margin-top:8px">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc()
				. '<button name="viec" value="lui_csv">↩ Hoàn tác lượt nạp lúc '
				. esc_html( $lui['luc'] ) . '</button>'
				. '<span class="mo"> — chỉ lùi được MỘT bước.</span></form>';
		}
		echo '</div>';
	}

	private static function the_tai_khoan( $ky, $la_admin ) {
		$kho = VHCC_NguoiDung::do_kho_cu();
		$ds  = VHCC_NguoiDung::ds();
		$vao_ht = 0;
		$u_ht   = VHCC_Auth::users();
		if ( ! is_wp_error( $u_ht ) ) {
			$cho_ht = VHCC_Auth::vai_tro_vao();
			foreach ( $u_ht as $x ) {
				if ( '' !== $x['pin'] && in_array( $x['vaiTro'], $cho_ht, true ) ) { $vao_ht++; }
			}
		}
		$nguon_ht = VHCC_Auth::nguon();
		$nhan_ng  = array(
			'ho_so' => 'Hồ sơ Nhân sự (đọc thẳng cột PIN đăng nhập)',
			'rieng' => 'Danh sách riêng của plugin',
			'chung' => 'Bảng người dùng của Vận Hành Chi Phí',
			'app'   => 'Sổ Phân quyền của app gốc',
		);

		echo '<div class="the"><h2>🔑 Tài khoản đăng nhập</h2>';
		echo '<p class="mo">Cổng <code>/cham-cong</code> đang đọc: <b>'
			. esc_html( isset( $nhan_ng[ $nguon_ht ] ) ? $nhan_ng[ $nguon_ht ] : $nguon_ht ) . '</b> — '
			. '<b>' . (int) $vao_ht . '</b> người đăng nhập được.</p>';

		/* 🔴 CHUYỂN NGUỒN NGAY TẠI ĐÂY. Anh Thắng khai PIN trong hồ sơ rồi vẫn *"chưa đăng nhập
		   bằng pin"* — vì cổng đang đọc một danh sách KHÁC, và muốn PIN có hiệu lực thì phải
		   nhớ bấm thêm "Nạp tài khoản" để chép sang. Hai bản danh sách cho cùng một việc thì
		   sớm muộn lệch nhau, và cái lệch đó im lặng. Đọc thẳng hồ sơ là hết bước chép. */
		if ( 'ho_so' !== $nguon_ht ) {
			echo '<div class="bao canh"><b>PIN khai trong hồ sơ bên dưới hiện CHƯA có hiệu lực</b> ở '
				. 'cổng đăng nhập, vì cổng đang đọc một danh sách khác. Bấm nút dưới để cổng đọc '
				. 'thẳng hồ sơ — sửa ở đâu có hiệu lực ngay ở đó.'
				. '<form method="post" style="margin-top:8px">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc()
				. '<input type="hidden" name="nguon" value="ho_so">'
				. '<button class="chinh" name="viec" value="doi_nguon">Cho cổng đọc thẳng Hồ sơ Nhân sự ('
				. (int) $kho['ho_so']['vao'] . ' người vào được)</button></form></div>';
		} else {
			echo '<div class="bao ok">Cổng đăng nhập đọc THẲNG hồ sơ — khai PIN và Vai trò ở bảng '
				. 'bên dưới là có hiệu lực ngay, không phải nạp thêm bước nào.'
				. '<form method="post" style="margin-top:8px">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc()
				. '<input type="hidden" name="nguon" value="rieng">'
				. '<button name="viec" value="doi_nguon">Quay về danh sách riêng</button></form></div>';
		}

		echo '<p class="mo">Danh sách riêng đang có <b>' . count( $ds ) . '</b> người, trong đó <b>'
			. VHCC_NguoiDung::so_vao_duoc( $ds ) . '</b> người đăng nhập được. '
			. 'Hồ sơ Nhân sự có <b>' . (int) $kho['ho_so']['co'] . '</b> người khai PIN đăng nhập, '
			. '<b>' . (int) $kho['ho_so']['vao'] . '</b> người trong đó vào được.</p>';

		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<div class="hang">';
		echo '<div><label for="ntk">Nạp tài khoản từ hồ sơ — cơ sở</label>'
			. '<select id="ntk" name="coso"><option value="">— cả chuỗi —</option>';
		foreach ( VHCC_NguoiDung::ds_coso_cu( 'ho_so' ) as $cs => $dem ) {
			echo '<option value="' . esc_attr( $cs ) . '">'
				. esc_html( '' === $cs ? '(không khai cơ sở)' : $cs ) . ' — ' . (int) $dem['pin'] . ' PIN'
				. '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="vtk">Vai trò nếu hồ sơ không ghi</label><select id="vtk" name="vt">';
		foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt ) {
			echo '<option value="' . esc_attr( $vt ) . '"' . selected( $vt, 'Nhân viên', false ) . '>'
				. esc_html( $vt )
				. ' — bậc ' . VHCC_Vai::BAC[ VHCC_Vai::ma( $vt ) ] . '</option>';
		}
		echo '</select></div>';
		echo '<button class="chinh" name="viec" value="nap_tk">Nạp tài khoản</button>';
		echo '</div></form>';
		echo '<p class="mo">Sổ nhân viên ghi <i>Chức vụ</i> là "Máy tự động", "Khu vui chơi"… — đó là '
			. 'chức vụ, <b>không phải vai trò đăng nhập</b>. Để mặc <i>Nhân viên</i> là nạp xong '
			. 'không ai đăng nhập được.</p>';

		if ( $la_admin ) {
			echo '<hr style="border:0;border-top:1px solid var(--vien);margin:14px 0">';
			echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
			echo '<div class="hang"><div><label for="tad">Khai thêm tài khoản Admin toàn quyền</label>'
				. '<input id="tad" name="ten" placeholder="Tên hiện trong nhật ký" style="width:230px"></div>'
				. '<button name="viec" value="khai_admin">Khai Admin</button></div></form>';
			echo '<p class="mo">PIN 6 số sinh ngẫu nhiên, hiện <b>đúng một lần</b> ngay sau khi bấm — '
				. 'hệ thống không lưu chỗ nào để in lại. Ghi ngay rồi cất.</p>';
		}
		echo '</div>';
	}

	private static function the_ho_so( $ky, $toi ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'nhan_vien' );
		/* 🔴 XEM PIN — TỪNG NGƯỜI MỘT, KHÔNG BAO GIỜ CẢ BẢNG.
		   Anh Thắng cần đọc PIN để báo lại cho nhân viên, đó là việc thật. Nhưng in cả 240 PIN
		   ra một màn hình thì một ảnh chụp là mất sạch mật khẩu cả chuỗi — trong chính dự án này
		   đã mất một khoá cầu nối vì một ảnh gửi qua chat. Nên: bấm 👁 ở ĐÚNG dòng cần xem, và
		   chỉ dòng đó hiện. Chỉ Admin. Không lưu lại đâu cả. */
		$xem_pin = isset( $_GET['pin'] ) ? sanitize_text_field( wp_unslash( $_GET['pin'] ) ) : '';
		if ( '' !== $xem_pin && ! VHCC_Vai::duoc( $toi, 'xem_pin' ) ) { $xem_pin = ''; }
		$cs   = isset( $_GET['cs'] ) ? sanitize_text_field( wp_unslash( $_GET['cs'] ) ) : '';
		$tim  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		/* 🔴 "AI CÓ PIN, AI CHƯA" PHẢI NHÌN LƯỚT LÀ THẤY, VÀ LỌC RA ĐƯỢC.
		   Anh Thắng: *"cần hiện để biết ai có pin chưa"*. Soi 240 dòng chữ xám nhỏ để tìm người
		   còn thiếu là việc không ai làm nổi — mà bỏ sót một người thì tháng sau người đó không
		   đăng nhập được và cũng không ai biết vì sao. */
		$loc = isset( $_GET['loc'] ) ? sanitize_text_field( wp_unslash( $_GET['loc'] ) ) : '';
		$vao_duoc = VHCC_Auth::vai_tro_vao();

		$dk = array(); $ts = array();
		if ( 'chua_pin' === $loc )      { $dk[] = "pin_dang_nhap=''"; }
		elseif ( 'co_pin' === $loc )    { $dk[] = "pin_dang_nhap<>''"; }
		elseif ( 'chua_vt' === $loc )   { $dk[] = "vai_tro=''"; }
		elseif ( 'chua_vao' === $loc )  {
			/* "Chưa đăng nhập được" = thiếu PIN, HOẶC vai trò không nằm trong nhóm được vào.
			   Đây mới là câu hỏi thật: không phải "có PIN chưa", mà "vào được chưa". */
			$in = array();
			foreach ( $vao_duoc as $v ) { $in[] = $wpdb->prepare( '%s', $v ); }
			$dk[] = "( pin_dang_nhap='' OR vai_tro NOT IN (" . implode( ',', $in ) . ') )';
		}
		if ( '' !== $cs ) { $dk[] = 'cua_hang=%s'; $ts[] = $cs; }
		if ( '' !== $tim ) {
			$dk[] = '(ma_nv LIKE %s OR ho_ten LIKE %s OR sdt LIKE %s OR cccd LIKE %s)';
			$nhu  = '%' . $wpdb->esc_like( $tim ) . '%';
			array_push( $ts, $nhu, $nhu, $nhu, $nhu );
		}
		$where = $dk ? ' WHERE ' . implode( ' AND ', $dk ) : '';
		$sql   = "SELECT * FROM $bang" . $where . ' ORDER BY cua_hang ASC, ho_ten ASC LIMIT 100';
		$rows  = VHCC_DB::rows( $ts ? $wpdb->prepare( $sql, $ts ) : $sql );

		echo '<div class="the"><h2>👤 Hồ sơ nhân sự</h2>';
		echo '<form method="get" class="hang" style="margin-bottom:10px">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<div><label for="fcs">Cơ sở</label><select id="fcs" name="cs"><option value="">— mọi cơ sở —</option>';
		foreach ( VHCC_DB::rows( "SELECT DISTINCT cua_hang FROM $bang WHERE cua_hang<>'' ORDER BY cua_hang" ) as $x ) {
			echo '<option value="' . esc_attr( $x['cua_hang'] ) . '"' . selected( $x['cua_hang'], $cs, false )
				. '>' . esc_html( $x['cua_hang'] ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="fq">Tìm</label><input id="fq" name="q" value="' . esc_attr( $tim )
			. '" placeholder="mã / tên / SĐT / CCCD" style="width:200px"></div>';
		echo '<div><label for="fl">Trạng thái</label><select id="fl" name="loc">';
		foreach ( array(
			''         => '— tất cả —',
			'chua_vao' => '⚠ CHƯA đăng nhập được',
			'chua_pin' => '✖ chưa có PIN',
			'chua_vt'  => '✖ chưa khai vai trò',
			'co_pin'   => '✔ đã có PIN',
		) as $k_l => $n_l ) {
			echo '<option value="' . esc_attr( $k_l ) . '"' . selected( $k_l, $loc, false ) . '>'
				. esc_html( $n_l ) . '</option>';
		}
		echo '</select></div>';
		echo '<button>Tìm</button>';
		echo '<a class="nut chinh" href="' . esc_url( add_query_arg( 'sua', '+', self::url() ) )
			. '">+ Hồ sơ mới</a>';
		echo '</form>';

		/* Bộ đếm trên đầu bảng — con số này mới là thứ cho biết còn bao nhiêu việc phải làm. */
		$in_vt = array();
		foreach ( $vao_duoc as $v ) { $in_vt[] = $wpdb->prepare( '%s', $v ); }
		$in_vt = implode( ',', $in_vt );
		$tong_hs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );
		$co_pin_hs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang WHERE pin_dang_nhap<>''" );
		$vao_hs  = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM $bang WHERE pin_dang_nhap<>'' AND vai_tro IN ($in_vt)" );
		$thieu   = $tong_hs - $vao_hs;
		echo '<div class="bao ' . ( $thieu ? 'canh' : 'ok' ) . '" style="margin:10px 0">'
			. '<b>' . $vao_hs . '/' . $tong_hs . '</b> người đăng nhập được'
			. ' · <b>' . $co_pin_hs . '</b> có PIN'
			. ' · <b>' . ( $tong_hs - $co_pin_hs ) . '</b> chưa có PIN';
		if ( $thieu ) {
			echo ' — <a href="' . esc_url( add_query_arg( array( 'loc' => 'chua_vao', 'cs' => $cs, 'q' => $tim ),
				self::url() ) ) . '"><b>xem ' . $thieu . ' người chưa vào được</b></a>';
		}
		echo '</div>';

		if ( ! $rows ) {
			echo '<p class="mo">Chưa có hồ sơ nào khớp bộ lọc đang chọn.'
				. ( '' !== $loc ? ' <a href="' . esc_url( self::url() ) . '">Bỏ lọc</a>' : ' Nạp file .csv ở ô trên.' )
				. '</p></div>';
			return;
		}
		/* 🔴 CHO CHỌN, ĐỪNG BẮT GÕ TAY. Anh Thắng: *"chọn"*. Chức vụ và Nhiệm vụ là hai ô mà
		   BẢNG LƯƠNG đọc để quyết cách tính (nhóm "Máy tự động" tính khác, "Trực Ghế Posh - JP"
		   tính theo GIỜ). Gõ tay qua 240 dòng là sớm muộn có "Khu vui choi" thiếu dấu, và người
		   đó rơi ra khỏi nhóm mà không có gì báo — sai lệch chỉ lộ ra ở bảng lương cuối tháng.

		   Dùng <datalist> chứ không <select>: nó vừa xổ ra danh sách đang có để bấm, vừa cho gõ
		   một giá trị MỚI khi cần. <select> thuần thì khai một chức vụ mới là phải sửa mã. */
		echo self::goi_y( 'dl_ch', "SELECT DISTINCT cua_hang AS v FROM $bang WHERE cua_hang<>''" );
		echo self::goi_y( 'dl_cv', "SELECT DISTINCT chuc_vu  AS v FROM $bang WHERE chuc_vu<>''" );
		/* 🔴 NHIỆM VỤ KHÔNG TỰ GOM TỪ DỮ LIỆU NỮA. Gom tự động thì cột Nhiệm vụ của sổ cũ đang
		   chứa lẫn TÊN CƠ SỞ ("JP Aeon Mall Tân Phú", "JP VINCOM 3/2"), và chúng trôi hết vào
		   danh sách xổ ra — anh Thắng: *"1 cái đầu với 3 cái cuối là nhiệm vụ, còn mấy cái khác
		   không phải"*. Một danh sách gợi ý mà 2/3 là rác thì tệ hơn không có: nó mời người ta
		   bấm nhầm.

		   Nên danh sách này do NGƯỜI KHAI, sửa ngay trên trang. Em không đoán thay — đoán sai
		   một mục là 240 lần bấm nhầm. */
		echo self::goi_y( 'dl_nv', '', false, self::ds_nhiem_vu() );
		echo self::goi_y( 'dl_cp', "SELECT DISTINCT coso_phu AS v FROM $bang WHERE coso_phu<>''", true );

		/* 🔴 MỘT FORM CHO CẢ BẢNG, MỘT NÚT LƯU. Anh Thắng: *"bấm khai 1 lần và lưu 1 lần được
		   không"*. Có 237 người cần khai Vai trò; bấm Lưu 237 lần, mỗi lần một vòng tải trang,
		   là việc không ai làm xong. Mỗi ô mang tên kiểu `vai_tro[MÃ]` nên một lượt gửi mang
		   theo cả bảng, và chỉ dòng NÀO THẬT SỰ ĐỔI mới được ghi. */
		echo '<form method="post" id="vhcc-bang">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc()
			. '<input type="hidden" name="viec" value="luu_nhieu"></form>';

		/* Đặt hàng loạt — thứ thật sự cứu 237 dòng cùng cần một vai trò. Nói rõ PHẠM VI: chỉ
		   những dòng ĐANG HIỆN theo bộ lọc, không phải cả sổ. */
		echo '<form method="post" class="hang" style="margin:10px 0;padding:10px;'
			. 'background:#f8fafc;border:1px solid var(--vien);border-radius:8px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc();
		echo '<div><label for="hl">Đặt Vai trò cho <b>' . count( $rows ) . ' dòng đang hiện</b></label>'
			. '<select id="hl" name="vt_hl">';
		foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt_h ) {
			echo '<option value="' . esc_attr( $vt_h ) . '"' . selected( $vt_h, 'Nhân viên', false ) . '>'
				. esc_html( $vt_h )
				. ' (bậc ' . VHCC_Vai::BAC[ VHCC_Vai::ma( $vt_h ) ] . ')'
				. '</option>';
		}
		echo '</select></div>';
		echo '<button name="viec" value="vai_tro_hang_loat">Áp cho ' . count( $rows ) . ' dòng</button>';
		echo '<span class="mo">Chỉ đụng những dòng đang hiện theo bộ lọc trên — không phải cả sổ.</span>';
		echo '</form>';

		echo '<div class="cuon"><table><thead><tr><th>Mã NV</th><th>Họ tên</th><th>Cửa hàng</th>'
			. '<th>Cơ sở phụ</th><th>Chức vụ</th><th>Nhiệm vụ</th><th>Vai trò</th><th>PIN</th>'
			. '<th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$id = 'vhcc-bang';
			$k  = '[' . esc_attr( $r['ma_nv'] ) . ']';
			echo '<tr><td><code>' . esc_html( $r['ma_nv'] ) . '</code></td>';
			echo '<td><input form="' . $id . '" name="ho_ten' . $k . '" value="' . esc_attr( $r['ho_ten'] ) . '" style="width:170px"></td>';
			echo '<td><input form="' . $id . '" name="cua_hang' . $k . '" list="dl_ch" value="' . esc_attr( $r['cua_hang'] ) . '" style="width:120px"></td>';
			echo '<td><input form="' . $id . '" name="coso_phu' . $k . '" list="dl_cp" value="' . esc_attr( (string) $r['coso_phu'] ) . '" style="width:140px"></td>';
			echo '<td><input form="' . $id . '" name="chuc_vu' . $k . '" list="dl_cv" value="' . esc_attr( $r['chuc_vu'] ) . '" style="width:140px"></td>';
			echo '<td><input form="' . $id . '" name="nhiem_vu' . $k . '" list="dl_nv" value="' . esc_attr( $r['nhiem_vu'] ) . '" style="width:150px"></td>';
			/* Ô NHẬP PIN — nhưng KHÔNG BAO GIỜ điền sẵn PIN cũ vào đó. Trang này chạy ngoài
			   internet; đổ 240 PIN ra màn hình là một ảnh chụp mất sạch mật khẩu cả chuỗi.
			   Luật: ô TRỐNG = giữ nguyên PIN cũ. Muốn bỏ hẳn thì tích ô "xoá" bên cạnh — phải
			   là một việc CÓ Ý, không phải hậu quả của việc để trống một ô. */
			/* 🔴 VAI TRÒ ĐĂNG NHẬP — ô quyết định người này có vào được trang web hay không.
			   Đây là <select>, KHÔNG phải ô gõ tự do như Chức vụ: danh sách vai trò là một tập
			   ĐÓNG mà hệ thống phải hiểu từng giá trị. Gõ "Kế Toán" thay vì "Kế toán cá nhân"
			   là người đó không đăng nhập được, mà không có gì báo. */
			$vt_r = (string) $r['vai_tro'];
			echo '<td><select form="' . $id . '" name="vai_tro' . $k . '" style="width:130px'
				. ( '' === $vt_r ? ';border-color:#fca5a5;background:#fef2f2' : '' ) . '">';
			echo '<option value=""' . selected( '', $vt_r, false ) . '>✖ chưa khai</option>';
			foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt_c ) {
				echo '<option value="' . esc_attr( $vt_c ) . '"' . selected( $vt_c, $vt_r, false ) . '>'
					. esc_html( $vt_c )
					. ' (bậc ' . VHCC_Vai::BAC[ VHCC_Vai::ma( $vt_c ) ] . ')'
					. '</option>';
			}
			echo '</select></td>';

			$co_pin = ( '' !== trim( (string) $r['pin_dang_nhap'] ) );
			$dang_ho = ( $co_pin && $xem_pin === (string) $r['ma_nv'] );
			echo '<td><input form="' . $id . '" name="pin_dang_nhap' . $k . '" inputmode="numeric" '
				. 'autocomplete="off" placeholder="' . ( $co_pin ? 'giữ nguyên' : 'chưa có' ) . '" '
				. 'style="width:96px">';
			echo '<div style="font-size:11.5px;margin-top:3px;white-space:nowrap">';
			if ( $dang_ho ) {
				echo '<b class="pin-ho">' . esc_html( $r['pin_dang_nhap'] ) . '</b> '
					. '<a href="' . esc_url( remove_query_arg( 'pin' ) ) . '">ẩn</a>';
			} elseif ( $co_pin ) {
				echo '<span class="co">✔ có ' . strlen( (string) $r['pin_dang_nhap'] ) . ' số</span>';
				if ( VHCC_Vai::duoc( $toi, 'xem_pin' ) ) {
					echo ' <a href="' . esc_url( add_query_arg( 'pin', $r['ma_nv'] ) ) . '">👁</a>';
				}
				echo ' <label style="display:inline;color:var(--do)"><input form="' . $id . '" '
					. 'type="checkbox" name="xoa_pin' . $k . '" value="1" style="vertical-align:-1px"> xoá</label>';
			} else {
				echo '<span class="chua">✖ chưa có PIN</span>';
			}
			echo '</div></td>';
			echo '<td><a class="nut" href="' . esc_url( add_query_arg( 'sua', $r['ma_nv'], self::url() ) )
				. '">Sửa đủ</a></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p style="margin:12px 0"><button class="chinh" form="vhcc-bang" '
			. 'style="font-size:16px;padding:10px 22px">💾 Lưu tất cả ' . count( $rows ) . ' dòng</button>'
			. ' <span class="mo">Chỉ ghi dòng THẬT SỰ có ô đổi — bấm khi không sửa gì thì không ghi gì.</span></p>';
		echo '<details style="margin-top:10px"><summary class="mo" style="cursor:pointer">'
			. 'Sửa danh sách <b>Nhiệm vụ</b> xổ ra (' . count( self::ds_nhiem_vu() ) . ' mục)</summary>';
		echo '<form method="post" style="margin-top:8px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc()
			. '<textarea name="ds_nv" rows="6" style="width:320px;font-family:ui-monospace,monospace">'
			. esc_textarea( implode( "\n", self::ds_nhiem_vu() ) ) . '</textarea><br>'
			. '<button name="viec" value="luu_nhiem_vu" style="margin-top:6px">Lưu danh sách</button>'
			. '<p class="mo">Mỗi dòng một mục. Danh sách này KHÔNG tự gom từ dữ liệu — cột Nhiệm vụ '
			. 'của sổ cũ đang lẫn tên cơ sở, gom vào là danh sách toàn rác và mời bấm nhầm.</p>'
			. '</form></details>';

		if ( VHCC_Vai::duoc( $toi, 'xem_pin' ) ) {
			echo '<p class="mo">Cần đọc PIN để báo cho nhân viên thì bấm <b>👁 xem</b> ở đúng dòng đó — '
				. 'hiện <b>một người một lúc</b>, và chỉ Admin thấy nút này. Cố ý không có nút '
				. '"hiện hết": in 240 PIN ra một màn hình thì một ảnh chụp là mất sạch mật khẩu cả '
				. 'chuỗi. Sổ gốc trong Google Sheets vẫn có đủ PIN nếu anh cần tra hàng loạt.</p>';
		}
		echo '<p class="mo">Bốn ô <b>Cửa hàng · Cơ sở phụ · Chức vụ · Nhiệm vụ</b> xổ ra danh sách '
			. 'gợi ý — bấm chọn cho khỏi gõ sai dấu, nhưng vẫn gõ được giá trị mới. '
			. 'Hiện tối đa 100 dòng — lọc theo cơ sở hoặc gõ ô Tìm để thu hẹp. '
			. '<b>Ô PIN để trống = giữ nguyên PIN cũ</b>; gõ 4–8 chữ số để đổi; tích <b>xoá</b> để bỏ hẳn. '
			. 'PIN cũ KHÔNG được điền sẵn vào ô — đổ 240 PIN ra màn hình là một ảnh chụp mất sạch mật khẩu cả chuỗi. '
			. 'Đổi PIN ở đây rồi nhớ bấm <b>Nạp tài khoản</b> ở ô 🔑 bên trên thì người đó mới đăng nhập được. '
			. '<b>Mã NV không sửa được ở đây</b>: đổi mã là sửa mọi hàng chấm công đã có của người đó.</p>';
		echo '</div>';
		echo $GLOBALS['VHCC_FORM_ROI'];
		$GLOBALS['VHCC_FORM_ROI'] = '';
	}

	/**
	 * Một <datalist> dựng từ CHÍNH dữ liệu đang có.
	 *
	 * @param bool $tach  ô nhiều giá trị cách nhau dấu phẩy (Nhiệm vụ, Cơ sở phụ) — tách ra
	 *                    thành từng mục, không thì gợi ý cả cụm "FARM_PT, FZ_LTVT" là vô dụng.
	 */
	private static function goi_y( $id, $sql, $tach = false, $luon_co = array() ) {
		$ds = array();
		foreach ( (array) $luon_co as $v ) { if ( '' !== trim( (string) $v ) ) { $ds[ trim( $v ) ] = 1; } }
		foreach ( ( '' === $sql ? array() : VHCC_DB::rows( $sql ) ) as $x ) {
			$v = trim( (string) $x['v'] );
			if ( '' === $v ) { continue; }
			foreach ( $tach ? explode( ',', $v ) : array( $v ) as $m ) {
				$m = trim( $m );
				if ( '' !== $m ) { $ds[ $m ] = 1; }
			}
		}
		ksort( $ds );
		$h = '<datalist id="' . esc_attr( $id ) . '">';
		foreach ( array_keys( $ds ) as $v ) { $h .= '<option value="' . esc_attr( $v ) . '">'; }
		return $h . '</datalist>';
	}

	/**
	 * MÀN SỬA ĐỦ MỘT HỒ SƠ — và là chỗ DUY NHẤT đổi được Mã NV.
	 *
	 * Anh Thắng: *"bổ sửa thông tin nhân sự"* và *"Admin có quyền sửa luôn mã nhân viên lại cho
	 * chuẩn nhé"*.
	 *
	 * ⚠️ Đổi mã KHÔNG để ở bảng danh sách. Ở đó mỗi dòng là một ô nhỏ giữa 240 dòng, gõ nhầm
	 *    một ký tự rồi bấm Lưu là xong — mà đây là việc kéo theo cả lịch sử chấm công. Đưa vào
	 *    màn riêng, có ô xác nhận, và nói trước nó sẽ động vào bao nhiêu hàng.
	 */
	const O_NHIEM_VU = 'vhcc_ds_nhiem_vu';

	/**
	 * DANH SÁCH NHIỆM VỤ — do người khai, không đoán từ dữ liệu.
	 *
	 * Mặc định là đúng bốn mục anh Thắng chốt lần cuối: *"1 cái đầu với 3 cái cuối là nhiệm vụ"*
	 * — Admin · Kế Toán · Nhân Viên · Thu Tiền. Thiếu mục nào thì thêm ngay trên trang, một
	 * dòng một mục.
	 */
	public static function ds_nhiem_vu() {
		$tho = get_option( self::O_NHIEM_VU );
		if ( ! is_string( $tho ) || '' === trim( $tho ) ) {
			return array( 'Admin', 'Kế Toán', 'Nhân Viên', 'Thu Tiền' );
		}
		$ra = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $tho ) as $d ) {
			$d = trim( $d );
			if ( '' !== $d && ! in_array( $d, $ra, true ) ) { $ra[] = $d; }
		}
		return $ra;
	}

	private static function the_sua_ho_so( $ky, $ma, $la_admin ) {
		global $wpdb;
		$them_moi = ( '+' === $ma );
		$r = $them_moi ? array() : VHCC_NhanSu::ho_so( $ma );
		if ( ! $them_moi && ! $r ) {
			echo '<div class="bao loi">Không thấy hồ sơ mang mã ' . esc_html( $ma ) . '.</div>';
			return;
		}
		$g = function ( $c ) use ( $r ) { return isset( $r[ $c ] ) ? (string) $r[ $c ] : ''; };

		echo '<div class="the"><h2>' . ( $them_moi ? '➕ Hồ sơ mới' : '✏️ Sửa hồ sơ '
			. esc_html( $ma ) . ' — ' . esc_html( $g( 'ho_ten' ) ) ) . '</h2>';
		echo '<p><a class="nut" href="' . esc_url( self::url() ) . '">← Về danh sách</a></p>';

		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<input type="hidden" name="viec" value="sua_hs">';
		if ( $them_moi ) {
			echo '<div class="luoi"><label>Mã NV <b style="color:var(--do)">*</b>'
				. '<input name="ma_nv" required style="width:100%"></label>'
				. '<label>Họ tên<input name="ho_ten" style="width:100%"></label></div>';
		} else {
			echo '<input type="hidden" name="ma_nv" value="' . esc_attr( $ma ) . '">';
			echo '<div class="luoi"><label>Họ tên<input name="ho_ten" value="'
				. esc_attr( $g( 'ho_ten' ) ) . '" style="width:100%"></label></div>';
		}

		foreach ( self::NHOM_SUA as $nhom => $cot_ds ) {
			echo '<h3 style="font-size:13.5px;color:var(--mo);margin:16px 0 6px;'
				. 'border-top:1px solid var(--vien);padding-top:12px">' . esc_html( $nhom ) . '</h3>';
			echo '<div class="luoi">';
			foreach ( $cot_ds as $c => $nhan ) {
				echo '<label>' . esc_html( $nhan );
				if ( 'vai_tro' === $c ) {
					echo '<select name="vai_tro" style="width:100%">';
					echo '<option value=""' . selected( '', $g( 'vai_tro' ), false ) . '>— chưa khai —</option>';
					foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt ) {
						echo '<option value="' . esc_attr( $vt ) . '"' . selected( $vt, $g( 'vai_tro' ), false )
							. '>' . esc_html( $vt )
							. ' (bậc ' . VHCC_Vai::BAC[ VHCC_Vai::ma( $vt ) ] . ')'
							. '</option>';
					}
					echo '</select>';
				} elseif ( 'pin_dang_nhap' === $c || 'pin_may' === $c ) {
					/* ⚠️ KHÔNG điền sẵn PIN cũ, kể cả ở màn sửa từng người. Trống = giữ nguyên. */
					$co = ( '' !== trim( $g( $c ) ) );
					echo '<input name="' . esc_attr( $c ) . '" inputmode="numeric" autocomplete="off" '
						. 'placeholder="' . ( $co ? 'để trống = giữ nguyên' : 'chưa có' ) . '" style="width:100%">';
					if ( $co && 'pin_dang_nhap' === $c ) {
						echo '<span class="mo" style="font-size:12px">đang có ' . strlen( $g( $c ) )
							. ' số &nbsp;<label style="display:inline;color:var(--do)">'
							. '<input type="checkbox" name="xoa_pin" value="1"> xoá hẳn</label></span>';
					}
				} elseif ( in_array( $c, VHCC_NapCsv::COT_NGAY, true ) ) {
					echo '<input type="date" name="' . esc_attr( $c ) . '" value="'
						. esc_attr( $g( $c ) ) . '" style="width:100%">';
				} else {
					$dl = array( 'cua_hang' => 'dl_ch', 'chuc_vu' => 'dl_cv',
						'nhiem_vu' => 'dl_nv', 'coso_phu' => 'dl_cp' );
					echo '<input name="' . esc_attr( $c ) . '" value="' . esc_attr( $g( $c ) ) . '"'
						. ( isset( $dl[ $c ] ) ? ' list="' . $dl[ $c ] . '"' : '' ) . ' style="width:100%">';
				}
				echo '</label>';
			}
			echo '</div>';
		}
		echo '<p style="margin-top:16px"><button class="chinh">Lưu hồ sơ</button></p></form>';

		/* ---- Đổi mã: việc riêng, chỉ Admin, có xác nhận ---- */
		if ( ! $them_moi && $la_admin ) {
			$dem = 0;
			foreach ( VHCC_NhanSu::bang_theo_ma() as $ten => $cot_ds ) {
				$t = VHCC_DB::t( $ten );
				foreach ( $cot_ds as $cot ) {
					$dem += (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM $t WHERE $cot=%s", $ma ) );
				}
			}
			echo '<div style="border-top:2px solid #fecaca;margin-top:20px;padding-top:14px">';
			echo '<h3 style="font-size:14px;margin:0 0 4px;color:var(--do)">Đổi Mã NV</h3>';
			echo '<p class="mo">Mã nhân viên là thứ NỐI hồ sơ với chấm công, lương, lịch làm, yêu cầu '
				. 'và sổ mặt trong máy. Đổi mã ở đây sẽ <b>kéo theo cả ' . (int) $dem . ' hàng</b> đang '
				. 'mang mã <code>' . esc_html( $ma ) . '</code> sang mã mới — không hàng nào rơi lại.</p>';
			echo '<p class="mo">Không đổi được sang một mã <b>đã có người</b>: gộp hai mã là trộn công '
				. 'của hai người vào một, và sau đó không phân biệt được hàng nào vốn của ai.</p>';
			echo '<form method="post" class="hang">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">' . self::o_loc()
				. '<input type="hidden" name="viec" value="doi_ma">'
				. '<input type="hidden" name="ma_cu" value="' . esc_attr( $ma ) . '">'
				. '<div><label for="mm">Mã mới</label>'
				. '<input id="mm" name="ma_moi" required style="width:210px" placeholder="' . esc_attr( $ma ) . '"></div>'
				. '<button class="nguy">Đổi mã và kéo theo ' . (int) $dem . ' hàng</button></form>';
			echo '</div>';
		}
		echo '</div>';
	}

	private static function the_xoa_het( $ky, $tong ) {
		echo '<div class="the" style="border-color:#fecaca">';
		echo '<h2 style="color:var(--do)">🗑 Xoá sạch hồ sơ nhân sự</h2>';
		echo '<p class="mo">Xoá cả <b>' . (int) $tong . '</b> hồ sơ để nạp lại từ đầu. '
			. '<b>Lượt chấm công, bảng lương và lịch làm KHÔNG bị xoá</b> — chúng gắn theo Mã NV, '
			. 'nạp lại hồ sơ đúng mã là khớp lại như cũ.</p>';
		echo '<p class="mo">Việc này <b>không hoàn tác được</b>. Nút ↩ Hoàn tác chỉ lùi được lượt nạp .csv.</p>';
		echo '<form method="post" class="hang"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<div><label for="xn">Gõ <b>XOA HET</b> để xác nhận</label>'
			. '<input id="xn" name="xac_nhan" placeholder="XOA HET" style="width:150px"></div>';
		echo '<button class="nguy" name="viec" value="xoa_het">Xoá sạch hồ sơ</button></form>';
		echo '</div>';
	}

	/**
	 * NHÃN + NHÓM cho màn sửa đủ. Thứ tự ở đây CHÍNH LÀ thứ tự hiện ra.
	 *
	 * Gom theo nhóm chứ không đổ một dãy 18 ô: hồ sơ nhân sự có bốn nhóm chẳng liên quan nhau
	 * (nhận dạng · công việc · liên hệ · lương), trộn chung thì mỗi lần sửa một ô phải dò cả màn.
	 */
	const NHOM_SUA = array(
		'Công việc'  => array(
			'cua_hang'            => 'Cửa hàng chính',
			'coso_phu'           => 'Cơ sở phụ (cách nhau dấu phẩy)',
			'chuc_vu'            => 'Chức vụ',
			'nhiem_vu'           => 'Nhiệm vụ (cách nhau dấu phẩy)',
			'trang_thai_lam_viec' => 'Trạng thái làm việc',
			'ngay_vao_lam'       => 'Ngày vào làm',
			'loai_hop_dong'      => 'Loại hợp đồng',
		),
		'Cá nhân'    => array(
			'ngay_sinh' => 'Ngày sinh',
			'gioi_tinh' => 'Giới tính',
			'cccd'      => 'CCCD',
			'sdt'       => 'Số điện thoại',
			'dia_chi'   => 'Địa chỉ',
		),
		'Lương'      => array(
			'luong_co_ban' => 'Lương cơ bản',
			'so_tai_khoan' => 'Số tài khoản',
			'ngan_hang'    => 'Ngân hàng',
		),
		'Đăng nhập'  => array(
			'vai_tro'       => 'Vai trò đăng nhập',
			'pin_dang_nhap' => 'PIN đăng nhập trang web (4–8 số)',
			'pin_may'       => 'PIN trên máy chấm công',
		),
	);

	/** Các ô sửa được ngoài web. Cố ý KHÔNG cho sửa `ma_nv` — đổi mã là sửa mọi hàng chấm công. */
	const COT_SUA = array( 'ho_ten', 'cua_hang', 'coso_phu', 'chuc_vu', 'nhiem_vu', 'vai_tro',
		'trang_thai_lam_viec', 'sdt', 'cccd', 'ngay_sinh', 'gioi_tinh', 'dia_chi',
		'ngay_vao_lam', 'loai_hop_dong', 'luong_co_ban', 'so_tai_khoan', 'ngan_hang',
		'pin_dang_nhap', 'pin_may' );
}
