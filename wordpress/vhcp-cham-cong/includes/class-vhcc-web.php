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
	/**
	 * Thẻ đang nằm trong cookie của trang này — '' là chưa đăng nhập.
	 *
	 * =========================================================================================
	 * 🔴 VÌ SAO PHẢI CÓ ĐƯỜNG ĐỌC NGƯỢC LẠI.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"nhân viên đăng nhập bên quản trị chấm công, nhưng qua chấm công
	 * đăng nhập online lại bắt đăng nhập lại"*. Thẻ thì đã là MỘT loại chung từ 25/08 — cùng
	 * `phat_token()`, cùng bảng phiên. Cái còn rời nhau là CHỖ CẤT: trang này để ở cookie, trạm
	 * để ở localStorage. Chiều trạm → quản trị đã bắc cầu bằng `mo_phien()`; chiều ngược lại
	 * thì trang trạm không có cách nào biết cookie đang có gì, vì cookie HttpOnly.
	 *
	 * ⚠️ HÀM NÀY TRẢ RA MỘT THỨ BÍ MẬT. Chỉ được gọi từ trong máy chủ, và chỉ để trao lại cho
	 *    ĐÚNG người đang cầm cookie ấy — tức là người đã đăng nhập rồi. Không bao giờ in nó ra
	 *    trang, không ghi vào nhật ký, không gửi cho lời gọi đến từ tên miền khác.
	 */
	public static function the_phien() {
		return isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
	}

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

	/**
	 * =========================================================================================
	 * 🔴 CẢ TRANG PHẢI NÓI RA ĐƯỢC LỖI, KHÔNG ĐỂ TRANG TRẮNG.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026 gặp *"Đã có một lỗi nghiêm trọng trên trang web của bạn"* ba lần trong
	 * một ngày — ở nút Xuất Excel, rồi ở màn Bảng công, rồi ở khối Thêm người mới. Mỗi lần là
	 * một trang trắng không nói gì, và mỗi lần em phải đoán.
	 *
	 * Lần đầu tìm ra chỗ hỏng CHỈ VÌ có `try/catch` ở đúng nút ấy: câu lỗi nói thẳng
	 * *"Call to undefined function wp_tempnam() (class-vhcc-xuat.php dòng 40)"*. Sửa xong trong
	 * một lượt. Nên đưa cái lưới ấy ra CẢ TRANG.
	 *
	 * ⚠️ VÌ SAO KHÔNG DỰA VÀO `register_shutdown_function`: WordPress có bộ bắt lỗi riêng đăng
	 *    ký shutdown TRƯỚC mình — nó in trang lỗi xong thì `headers_sent()` thành true và hàm
	 *    của mình lặng lẽ bỏ qua. `try/catch` chạy NGAY tại chỗ, không bị giành.
	 *
	 * ⚠️ HẾT BỘ NHỚ THÌ `catch` KHÔNG BẮT ĐƯỢC — cái đó là một loại chết khác. Nhưng lỗi do MÃ
	 *    (gọi hàm không có, sai kiểu, chia cho 0) thì bắt hết, và đó là loại hay gặp nhất.
	 */
	public static function phuc_vu() {
		try {
			self::phuc_vu_that();
		} catch ( \Throwable $e ) {
			self::trang_hong( $e );
		}
	}

	/**
	 * Trang báo hỏng — đọc được bằng mắt, chụp màn hình gửi đi được.
	 *
	 * ⚠️ KHÔNG in dấu vết gọi hàm (stack trace): nó dài, khó đọc, và có thể lộ đường dẫn máy chủ.
	 *    Một dòng "tệp nào, dòng nào" là đủ để tìm ra chỗ hỏng — đã chứng minh một lần.
	 */
	private static function trang_hong( $e ) {
		if ( headers_sent() ) { return; }
		status_header( 500 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><meta charset="utf-8"><title>Trang chấm công gặp lỗi</title>';
		echo '<div style="font:15px/1.6 system-ui,Arial;max-width:680px;margin:60px auto;'
			. 'padding:20px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px">';
		echo '<h2 style="margin:0 0 8px">Trang chấm công gặp lỗi</h2>';
		echo '<p style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13.5px;'
			. 'background:#fff;padding:10px;border-radius:7px;border:1px solid #fecaca">'
			. esc_html( $e->getMessage() ) . '<br><b>' . esc_html( basename( $e->getFile() ) )
			. '</b> dòng <b>' . (int) $e->getLine() . '</b></p>';
		echo '<p><b>Chụp nguyên khung trên gửi cho người viết phần mềm</b> — nó chỉ thẳng chỗ hỏng, '
			. 'không phải đoán.</p>';
		echo '<p style="color:#78716c;font-size:13px">Bản đang chạy: '
			. esc_html( defined( 'VHCC_VERSION' ) ? VHCC_VERSION : '?' ) . ' · PHP '
			. esc_html( PHP_VERSION ) . '</p>';
		echo '<p><a href="' . esc_url( self::url() ) . '">← Về trang chính</a></p></div>';
	}

	private static function phuc_vu_that() {
		/* Cửa VÀO rộng — xem `nguoi_vao()`. Màn nào hẹp thì chính màn đó gác. */
		$toi = self::nguoi_vao();

		/* Đăng xuất xử trước mọi thứ. */
		if ( isset( $_POST['viec'] ) && 'thoat' === $_POST['viec'] ) {
			self::dat_cookie( '', false );
			self::ve( self::url() );
		}

		if ( ! $toi ) { self::trang_dang_nhap(); return; }

		/* 🔴 CHỐT "AI VÀO ĐƯỢC TRANG NÀO" — khai ở trang Quản lý nhân sự (`VHCC_TrangNS`).
		   Đặt SAU cửa đăng nhập và TRƯỚC mọi việc: người bị khoá riêng phải dừng ở đây, không
		   phải dừng ở từng nút bên trong. Mặc định vẫn là thang vai, nên bản này không siết
		   thêm của ai — chỉ những người đã bị khai `khoa` mới đổi.
		   ⚠️ Gác `method_exists` cùng hàm với lời gọi (luật `tools/test/kiem-goi-cheo.php`). */
		if ( class_exists( 'VHCC_Cong' ) && method_exists( 'VHCC_Cong', 'duoc_vao' ) ) {
			if ( ! VHCC_Cong::duoc_vao( $toi, 'cham_cong' ) ) {
				self::trang_bi_khoa( VHCC_Cong::vi_sao_khong( $toi, 'cham_cong' ) );
				return;
			}
		}

		/* 🔴 TẢI TỆP XỬ TRƯỚC MỌI THỨ KHÁC — trước cả `trang_chinh()`, vì gửi tệp đòi đặt
		   header, mà header chỉ đặt được khi CHƯA in ra một byte nào. Để nhánh này xuống dưới là
		   PHP báo "headers already sent" và trình duyệt nhận về một tệp .xlsx có lẫn cả trang
		   HTML ở đầu — Excel mở ra báo hỏng, mà chẳng ai đoán được vì sao. */
		/* ⚠️ `return` NGAY SAU. Trong bản thật hai hàm này kết bằng `exit` nên dòng return không
		   bao giờ chạy; nhưng dưới bộ thử chúng chỉ `return`, và thiếu chỗ dừng ở đây thì luồng
		   chạy tiếp rồi in NGUYÊN CẢ MÀN QUẢN TRỊ ra sau tờ giấy — hai trang HTML lồng nhau. */
		if ( isset( $_GET['xuat'] ) ) { self::xuat_tep( $toi ); return; }

		/* 🔴 TỜ IN CŨNG XỬ TRƯỚC MỌI THỨ. `VHCC_Pdf::trang_in()` trả về MỘT TRANG HTML HOÀN
		   CHỈNH (có `<!DOCTYPE>`, `@page{size:A4}`, khuôn giấy riêng) — in nó ra giữa màn quản
		   trị là hai trang HTML lồng nhau, và tờ giấy in ra hỏng khuôn. Cùng lý do với nhánh
		   xuất .xlsx ngay trên. */
		if ( isset( $_GET['to_in'] ) ) { self::to_in( $toi ); return; }

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
		if ( '' !== $chan ) { self::loi_xuat( $chan ); return; }

		/* =====================================================================================
		 * 🔴 XUẤT LÀ CHỖ DỄ CHẾT NHẤT CỦA CẢ TRANG, VÀ NÓ CHẾT KHÔNG NÓI GÌ.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026 gửi ảnh: bấm Xuất Excel ở TUTU_BT ra *"Đã có một lỗi nghiêm trọng
		 * trên trang web của bạn"* — trang trắng, không một chữ nào cho biết vướng ở đâu.
		 *
		 * Lượt xuất phải giữ CẢ THÁNG của CẢ CƠ SỞ trong bộ nhớ hai lần: một lần là mảng dữ
		 * liệu, một lần là chuỗi XML dựng ra từ nó. Một cơ sở 20 người × 31 ngày là chuyện
		 * thường; nhưng cùng chỗ ấy trên hosting chia sẻ có thể chỉ được cấp 40 MB. Vượt là PHP
		 * chết giữa chừng — mà lúc ấy `loi_xuat()` không bao giờ được gọi tới.
		 *
		 * Nên: NÂNG trần trước, và ĐÓN cái chết nếu vẫn xảy ra.
		 */
		if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
		/* ⚠️ `@` vì hosting bật `safe_mode`/`disable_functions` sẽ ném cảnh báo ra giữa tệp
		   .xlsx — và một tệp .xlsx có mấy dòng chữ ở đầu thì Excel báo hỏng. */
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }

		/* 🔴 ĐÓN CÁI CHẾT. Không chặn được nó, nhưng nói ra được vướng gì — và câu ấy là thứ
		   duy nhất người dùng có thể chụp màn hình gửi đi. Trang trắng thì không. */
		$da_gui = false;
		register_shutdown_function( function () use ( &$da_gui, $cs, $th ) {
			if ( $da_gui ) { return; }
			$e = error_get_last();
			if ( ! $e || ! in_array( $e['type'],
				array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
			if ( headers_sent() ) { return; }
			$het = ( false !== stripos( (string) $e['message'], 'memory' ) );
			status_header( 500 );
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!DOCTYPE html><meta charset="utf-8"><title>Xuất Excel không xong</title>';
			echo '<div style="font:15px/1.6 system-ui,Arial;max-width:640px;margin:60px auto;'
				. 'padding:20px;border:1px solid #fde68a;background:#fffbeb;border-radius:10px">';
			echo '<h2 style="margin:0 0 8px">Xuất Excel không xong</h2>';
			echo '<p>Cơ sở <b>' . esc_html( $cs ) . '</b> · tháng <b>' . esc_html( $th ) . '</b>.</p>';
			echo $het
				? '<p><b>Máy chủ hết bộ nhớ</b> giữa chừng. Xuất theo <b>từng tháng một</b>, hoặc '
					. 'nhờ bên hosting nâng <code>memory_limit</code> lên 256M.</p>'
				: '<p>Máy chủ dừng giữa chừng: <code>' . esc_html( substr( (string) $e['message'], 0, 200 ) )
					. '</code></p>';
			echo '<p style="color:#78716c;font-size:13px">Giới hạn bộ nhớ đang là <b>'
				. esc_html( (string) ini_get( 'memory_limit' ) ) . '</b>, lúc dừng đã dùng <b>'
				. esc_html( size_format( memory_get_peak_usage( true ) ) ) . '</b>.</p>';
			echo '<p><a href="' . esc_url( self::url() ) . '">← Quay lại bảng công</a></p></div>';
		} );

		$b = VHCC_Cham::bang_cham_cong( $toi, $cs, $th );
		if ( empty( $b['ok'] ) ) { self::loi_xuat( $b['error'] ); return; }
		if ( empty( $b['hang'] ) ) {
			self::loi_xuat( 'Tháng ' . $b['thang'] . ' chưa có dữ liệu chấm công nào ở ' . $cs
				. ' — không có gì để xuất.' );
			return;
		}

		/* ---------------------------------------------------------------------------------
		 * XUẤT ẢNH (.svg) — anh Thắng 28/08/2026: *"Thêm xuất dạng ảnh ra (kèm thêm giờ vào ra
		 * nữa nhé)"*. Rẽ ở đây, TRƯỚC đường dựng .xlsx: ảnh không dùng ZipArchive, không dùng
		 * `VHCC_Xuat`, nên không có lý gì bắt nó đi chung khúc dễ chết ấy.
		 * ------------------------------------------------------------------------------- */
		if ( 'anh' === $loai ) {
			$ng_tre = ( class_exists( 'VHCC_Tre' ) && method_exists( 'VHCC_Tre', 'cua' ) )
				? (int) VHCC_Tre::cua( $cs ) : 0;
			try {
				$svg = VHCC_Anh::svg( $b, VHCC_Ca::cua( $cs ), VHCC_Luong::cach_tinh( $cs ),
					$ng_tre, (string) current_time( 'd/m/Y H:i' ) );
			} catch ( \Throwable $e ) {
				$da_gui = true;
				self::loi_xuat( 'Dựng ảnh thì gặp lỗi: ' . $e->getMessage()
					. ' (' . basename( $e->getFile() ) . ' dòng ' . $e->getLine() . ').' );
				return;
			}
			if ( '' === $svg ) {
				$da_gui = true;
				self::loi_xuat( 'Tháng "' . $th . '" không đọc ra ngày nào — không dựng được ảnh.' );
				return;
			}
			$da_gui = true;
			VHCC_Xuat::gui( 'cong-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $cs ) . '-'
				. $b['thang'] . '.svg', $svg, 'image/svg+xml' );
			return;
		}

		/* Đường CHẨN ĐOÁN: `&thu=1` — in ra con số thay vì dựng tệp. Mở được bằng trình duyệt,
		   đọc được bằng mắt, chụp màn hình gửi đi được. Không in gì bí mật: chỉ tình trạng máy. */
		if ( ! empty( $_GET['thu'] ) ) {
			$da_gui = true;
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo "== XUAT EXCEL: CHAN DOAN ==\n";
			echo 'co so        : ' . $cs . "\n";
			echo 'thang        : ' . $b['thang'] . "\n";
			echo 'so nguoi     : ' . count( (array) $b['hang'] ) . "\n";
			echo 'so luot      : ' . ( isset( $b['ds'] ) ? count( (array) $b['ds'] ) : 0 ) . "\n";
			echo 'ZipArchive   : ' . ( VHCC_Xuat::co_xlsx() ? 'co' : 'CHUA CO' ) . "\n";
			echo 'memory_limit : ' . (string) ini_get( 'memory_limit' ) . "\n";
			echo 'dang dung    : ' . size_format( memory_get_usage( true ) ) . "\n";
			echo 'dinh cao     : ' . size_format( memory_get_peak_usage( true ) ) . "\n";
			echo 'php          : ' . PHP_VERSION . "\n";
			echo "\nDoc duoc dong nay tuc la buoc DOC DU LIEU da xong; cho chet (neu co) nam o\n";
			echo "buoc dung tep .xlsx ngay sau day.\n";
			/* Bài kiểm chạy trong CÙNG một tiến trình; `exit` ở đây là giết luôn cả bài kiểm,
			   nên không phép thử nào chạm được vào đường này — y như chốt ở `ve()`. */
			if ( defined( 'VHCC_TEST' ) ) { return; }
			exit;
		}

		/* =====================================================================================
		 * 🔴 BẮT `Throwable`, KHÔNG CHỈ TRÔNG VÀO `register_shutdown_function`.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026 cài bản có lớp đón-cái-chết mà VẪN thấy trang trắng "Đã có một
		 * lỗi nghiêm trọng trên trang web của bạn". Lý do: WordPress có bộ bắt lỗi riêng
		 * (`WP_Fatal_Error_Handler`) đăng ký shutdown TRƯỚC mình, nên nó in trang lỗi xong,
		 * `headers_sent()` thành true, và hàm của mình lặng lẽ bỏ qua.
		 *
		 * `try/catch ( \Throwable )` chạy NGAY tại chỗ, trước khi WordPress kịp xen vào — và
		 * nó bắt được `Error`, tức mọi lỗi do MÃ (gọi hàm không có, sai kiểu, chia cho 0). Đó
		 * là loại lỗi mà một tệp .xlsx dựng từ dữ liệu thật hay vấp nhất.
		 *
		 * ⚠️ Hết bộ nhớ thì `catch` KHÔNG bắt được — cái đó vẫn phải trông vào lớp shutdown ở
		 *    trên và vào việc nâng trần. Hai lớp cho hai loại chết khác nhau, không thay nhau.
		 */
		try {
			$noi = VHCC_Xuat::xlsx( VHCC_Ca::to_xuat( $b, $cs ) );
		} catch ( \Throwable $e ) {
			$da_gui = true;
			self::loi_xuat( 'Dựng tệp .xlsx thì gặp lỗi: ' . $e->getMessage()
				. ' (' . basename( $e->getFile() ) . ' dòng ' . $e->getLine() . ').'
				. ' Gửi nguyên câu này cho người viết phần mềm — nó chỉ thẳng chỗ hỏng.' );
			return;
		}
		if ( null === $noi ) {
			self::loi_xuat( 'Không dựng được tệp .xlsx. Máy chủ có ZipArchive: '
				. ( VHCC_Xuat::co_xlsx() ? 'có' : 'CHƯA CÓ — nhờ hosting bật phần mở rộng zip của PHP' )
				. '.' );
			return;
		}
		$da_gui = true;

		/* Tên tệp chỉ giữ chữ/số/gạch — dấu tiếng Việt và khoảng trắng trong `Content-Disposition`
		   là mỗi trình duyệt đặt tên một kiểu, có cái cắt cụt ngay chỗ dấu cách. */
		$ten = 'cong-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $cs ) . '-' . $b['thang'] . '.xlsx';
		VHCC_Xuat::gui( $ten, $noi );
	}

	/**
	 * TỜ IN BẢNG CHẤM CÔNG — khổ A4, in thẳng từ trình duyệt.
	 *
	 * Anh Thắng chốt: *"mọi việc anh thao tác trên web giao diện bên ngoài hết"*. Đây là màn
	 * cuối trong nhóm việc hằng ngày còn kẹt trong wp-admin.
	 *
	 * 🔴 SỐ TRÊN TỜ GIẤY DO MÁY CHỦ TÍNH. Bản Apps Script cũ nhận số do trình duyệt tính rồi
	 *    đẩy lên — tức ai sửa được yêu cầu là sửa được tờ giấy chấm công đem đi ký.
	 *
	 * ⚠️ Không dùng thư viện HTML→PDF nào. Tờ giấy in ra từ chính trình duyệt (Ctrl+P → Lưu
	 *    thành PDF): đúng khổ A4, đúng khuôn, mà không phụ thuộc một thư viện có thể hỏng sau
	 *    một lượt cập nhật PHP của hosting.
	 */
	private static function to_in( $toi ) {
		$cs  = isset( $_GET['ics'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['ics'] ) ) : '';
		$tu  = isset( $_GET['itu'] ) ? sanitize_text_field( wp_unslash( $_GET['itu'] ) ) : '';
		$den = isset( $_GET['iden'] ) ? sanitize_text_field( wp_unslash( $_GET['iden'] ) ) : '';

		$chan = self::vi_sao_khong_in( $toi, $cs, $tu, $den );
		if ( '' !== $chan ) { self::loi_xuat( $chan ); return; }

		nocache_headers();
		echo VHCC_Pdf::trang_in( $cs, $tu, $den,
			isset( $toi['name'] ) ? (string) $toi['name'] : '' );
		/* Bộ thử chạy trong CÙNG tiến trình — `exit` ở đây là giết luôn bài kiểm. */
		if ( defined( 'VHCC_TEST' ) ) { return; }
		exit;
	}

	/**
	 * Người này có in được tờ ấy không? '' = được, hoặc câu từ chối.
	 *
	 * ⚠️ Tách khỏi `to_in()` vì hàm kia kết bằng `exit` — gọi nó trong bộ thử là giết cả lượt
	 *    chạy, nên phần gác cửa sẽ vĩnh viễn không có phép thử nào. Cùng lối với
	 *    `vi_sao_khong_xuat()`.
	 */
	public static function vi_sao_khong_in( $toi, $cs, $tu, $den ) {
		/* Tờ in là BẢNG CHẤM CÔNG, không phải bảng lương — nên gác bằng `cong_coso` (Cửa hàng
		   trưởng), đúng bằng cửa của màn Bảng công. Cửa hàng trưởng phải in được bảng công cơ
		   sở mình để dán lên bảng tin và cho người ta ký. */
		if ( ! VHCC_Vai::duoc( $toi, 'cong_coso' ) ) {
			return 'In bảng chấm công cần quyền Cửa hàng trưởng trở lên.';
		}
		$cs = VHCC_NhanSu::chuan_coso( $cs );
		if ( '' === $cs ) { return 'Chưa chọn cơ sở.'; }
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return 'Không có quyền cơ sở này.'; }
		/* 🔴 KHUÔN NGÀY PHẢI KIỂM Ở ĐÂY. Hai chuỗi này đi thẳng vào câu SQL của `VHCC_Pdf::gom()`;
		   thả một chuỗi tuỳ ý xuống đó là mở một cửa mà không ai gác. */
		$mau = '/^\d{4}-\d{2}-\d{2}$/';
		if ( ! preg_match( $mau, (string) $tu ) )  { return 'Ngày bắt đầu không hợp lệ.'; }
		if ( ! preg_match( $mau, (string) $den ) ) { return 'Ngày kết thúc không hợp lệ.'; }
		/* Đảo ngày thì tờ giấy ra RỖNG mà không nói vì sao — người ta tưởng tháng ấy không ai
		   đi làm. Nói thẳng còn hơn. */
		if ( $den < $tu ) { return 'Ngày kết thúc đang trước ngày bắt đầu.'; }
		if ( ! class_exists( 'VHCC_Pdf' ) || ! method_exists( 'VHCC_Pdf', 'trang_in' ) ) {
			return 'Thiếu phần dựng tờ in (VHCC_Pdf).';
		}
		return '';
	}

	/**
	 * Người này có tải được tệp không? '' = được, hoặc câu từ chối.
	 *
	 * ⚠️ Tách ra khỏi `xuat_tep` vì hàm kia luôn kết bằng `exit` — gọi nó trong bộ thử là GIẾT
	 *    luôn cả lượt chạy, nên phần gác cửa sẽ vĩnh viễn không có phép thử nào. Một cửa không
	 *    thử được là một cửa sớm muộn hở.
	 */
	/**
	 * Kiểu xuất này có cần ZipArchive không.
	 *
	 * 🔴 ZipArchive CHỈ CẦN CHO .xlsx. Ảnh .svg là chuỗi chữ máy chủ tự ghép — không cần một
	 *    phần mở rộng nào. Bắt nó qua cùng một chốt là hosting thiếu `php-zip` thì mất luôn
	 *    đường xuất DUY NHẤT còn chạy được, mà lời chối lại nói về Excel.
	 *
	 * ⚠️ Tách thành hàm THUẦN chứ không viết thẳng vào `vi_sao_khong_xuat()`: máy nào chạy bộ
	 *    thử cũng CÓ ZipArchive, nên một dòng `if` nằm trong đó là dòng không phép thử nào phân
	 *    biệt được — bỏ đi vẫn xanh. Hỏi thẳng hàm này thì hỏi được cả trên máy có lẫn máy không.
	 */
	public static function xuat_can_zip( $loai ) {
		return 'ca' === $loai;
	}

	public static function vi_sao_khong_xuat( $toi, $loai, $cs ) {
		if ( ! in_array( $loai, array( 'ca', 'anh' ), true ) ) {
			return 'Không biết xuất kiểu "' . $loai . '".';
		}
		if ( ! VHCC_Vai::duoc( $toi, 'cong_coso' ) ) {
			return 'Xuất bảng công cần quyền Cửa hàng trưởng trở lên.';
		}
		$cs = VHCC_NhanSu::chuan_coso( $cs );
		if ( '' === $cs ) { return 'Chưa chọn cơ sở.'; }
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return 'Không có quyền cơ sở này.'; }
		if ( self::xuat_can_zip( $loai ) && ! VHCC_Xuat::co_xlsx() ) {
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
		/* 🔴 BỘ THỬ CHẠY TRONG CÙNG TIẾN TRÌNH — `exit` ở đây là giết luôn bài kiểm, và bài kiểm
		   chết thì KHÔNG in ra báo cáo: trông y như "phép thử không bắt được" trong khi nó bắt
		   được, chỉ là báo cáo bị chôn cùng. Đã vấp đúng chuyện đó khi thêm tờ in. Cùng lối với
		   `ve()`: một cái mối hẹp, có tên, hơn là để cả nhánh chối không ai thử.
		   ⚠️ Mọi chỗ gọi hàm này PHẢI `return` ngay sau — trong bộ thử nó không còn dừng luồng. */
		if ( defined( 'VHCC_TEST' ) ) { return; }
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
		/* 🔴 ĐÓNG KHUNG CỘT DỌC — và chỉ khi nó ĐÃ ĐƯỢC MỞ.
		   Màn đăng nhập, tờ in, trang xuất tệp đều gọi `dau()` mà KHÔNG có cột dọc (chưa biết
		   người là ai thì lấy gì vẽ menu). Đóng vô điều kiện là mỗi trang ấy thừa hai thẻ đóng —
		   trình duyệt tự sửa nên không ai thấy gì, cho tới ngày có một thẻ khác bị nuốt theo. */

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
		/* 🔴 ĐÓNG KHUNG CỘT DỌC SAU CHÂN TRANG, không trước.
		   Đóng trước là chân trang rơi ra NGOÀI vùng nội dung: trên màn rộng nó trải hết bề
		   ngang, chui xuống dưới cả cột dọc, và lệch hẳn so với mọi thứ phía trên. Đúng cái anh
		   Thắng đã bắt một lần rồi — *"bị lệch"*, 26/08 — chỉ khác chỗ lệch.
		   ⚠️ Và chỉ đóng khi nó ĐÃ ĐƯỢC MỞ. Màn đăng nhập, tờ in, trang xuất tệp đều gọi `dau()`
		      mà không có cột dọc (chưa biết người là ai thì lấy gì vẽ menu). */
		if ( ! empty( $GLOBALS['VHCC_CO_COT'] ) ) {
			echo '</main></div>';
			$GLOBALS['VHCC_CO_COT'] = false;
		}
		echo '</body></html>';
	}

	/** Các tham số phải sống sót qua một lượt POST — bộ lọc, ô tìm, màn đang mở. */
	/* ⚠️ `msoma` là của màn Máy & Firmware — khai ở đây chứ không chỉ ở `VHCC_WebMay::THAM_SO`:
	      danh sách này là thứ `o_loc()` đọc để chở tham số qua một lượt POST, và thiếu nó thì
	      chọn máy xong bấm một nút bất kỳ là ô chọn nhảy về máy đầu tiên. */
	const THAM_SO = array( 'cs', 'q', 'loc', 'sua', 'pin', 'man', 'ccs', 'cth', 'cbp', 'cng', 'cnv', 'ctk',
		'lcs', 'lth', 'ltu', 'lden', 'msoma' );

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
	/* ⚠️ `them_nv` NẰM TRONG DANH SÁCH NÀY, và đó là chỗ dễ quên nhất của cả tính năng ấy.
	   Chốt bên dưới chối mọi việc của người không có bậc hồ sơ — đúng cho việc hồ sơ, nhưng
	   `them_nv` là cửa RIÊNG mở cho Cửa hàng trưởng (anh Thắng 28/08/2026). Quên khai vào đây
	   thì nút vẫn vẽ ra, vẫn bấm được, và câu trả lời là một dòng nói về màn Hồ sơ — người dùng
	   đi xin đúng cái quyền mà họ không cần. Phần gác thật nằm trong
	   `VHCC_NhanSu::them_nv_cua_hang()`, hỏi đúng đầu việc `them_nv`. */
	const VIEC_CHAM = array( 'co', 'xu_ly_co', 'bu', 'xem_cong', 'nap_cong', 'ca', 'cach_tinh',
		'them_nv', 'muc_tre', 'duyet_tre', 'choi_tre', 'xin_tre', 'cho_tra' );

	private static function lam_viec( $viec, $toi ) {
		$bao = array();

		/* 🔴 DANH SÁCH TRẮNG, MẶC ĐỊNH LÀ CHỐI. Từ khi cửa vào trang được nới ra (xem
		   `nguoi_vao()`), một Cửa hàng trưởng đã có phiên hợp lệ ở đây — mà mọi việc bên dưới đều
		   là việc HỒ SƠ: nạp .csv đè cả sổ nhân sự, cấp PIN, đổi vai trò, xoá hết. Nới cửa mà quên
		   chốt này là mở toang đúng những thứ `VAI_TRO` sinh ra để giữ.
		   Viết theo hướng CHỐI TRƯỚC: thêm một việc mới mà quên khai thì nó bị chối, chứ không
		   lọt. Ngược lại (danh sách đen) thì quên một dòng là mở một cửa, và cửa đó im lặng. */
		/* 🔴 ĐỊNH TUYẾN HAI MÀN RỜI **TRƯỚC** CHỐT DƯỚI, và cố ý.
		   Chốt dưới là danh sách trắng chống việc HỒ SƠ: ai không có bậc hồ sơ thì mọi việc lạ
		   đều bị chối. Đúng cho màn Hồ sơ, nhưng SAI cho hai màn này — việc của chúng chẳng dính
		   gì tới hồ sơ, và người cần chúng nhất lại là người KHÔNG có bậc hồ sơ:
		     • Cửa hàng trưởng xếp lịch cửa hàng mình (mô hình anh Thắng chốt giao đúng việc ấy);
		     • Nhân viên xem lịch của mình và xin đổi;
		     • vai Kỹ thuật dựng máy (2.67.0) — bậc 1, mở riêng đúng một đầu việc.
		   Để sau chốt thì cả ba bị đá ra bằng một câu nói về màn Hồ sơ, mà họ không hề đụng tới
		   hồ sơ — câu trả lời sai chỗ, và người đọc đi xin nhầm quyền.
		   ⚠️ Đứng trước chốt KHÔNG phải là không có gác: `VHCC_WebLich::viec()` và
		      `VHCC_WebMay::viec()` mỗi cái tự hỏi quyền ngay dòng đầu của mình. Chép mã sang đây
		      mới là chỗ hỏng — hai nơi cùng giữ một luật thì sớm muộn hiểu khác nhau. */
		if ( VHCC_WebLich::la_viec( $viec ) ) {
			return VHCC_WebLich::viec( $viec, $toi );
		}

		if ( VHCC_WebMay::la_viec( $viec ) ) {
			return VHCC_WebMay::viec( $viec, $toi );
		}

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

		if ( 'ten_cs' === $viec ) {
			$o = isset( $_POST['tcs'] ) ? (array) wp_unslash( $_POST['tcs'] ) : array();
			$sach = array();
			foreach ( $o as $ma_x => $ten_x ) {
				$sach[ sanitize_text_field( $ma_x ) ] = sanitize_text_field( is_array( $ten_x ) ? '' : $ten_x );
			}
			$r = VHCC_NhanSu::dat_ten_coso( $toi, $sach );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => $r['doi']
				? 'Đã đổi tên ' . (int) $r['doi'] . ' cơ sở — nay còn ' . (int) $r['so']
					. ' mã có tên. Mã KHÔNG đổi: mọi bảng cũ vẫn trỏ đúng chỗ.'
				: 'Không có tên nào đổi.' ) );
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
			/* Ô TÍCH VAI gửi lên dạng mảng (`ctv[ktVaiTro][]`), không phải một chuỗi.
			   🔴 BỎ HẾT TÍCH = KHAI RỖNG, không phải "bỏ khai". Khác hẳn ô chữ để trống: người
			      ta bỏ tích là CÓ Ý nói "không lấy theo vai nữa". Coi nó là bỏ khai thì giá trị
			      cũ ở lại, bấm Lưu bao nhiêu lần cũng không gỡ được — mà màn hình thì hiện ô
			      trống, nên trông như đã gỡ rồi. */
			$gui_v = isset( $_POST['ctv'] ) ? wp_unslash( $_POST['ctv'] ) : array();
			foreach ( (array) VHCC_Luong::VP_O as $k_v => $o_v ) {
				if ( 'ktVaiTro' !== $k_v ) { continue; }
				$ds_v = ( is_array( $gui_v ) && isset( $gui_v[ $k_v ] ) && is_array( $gui_v[ $k_v ] ) )
					? $gui_v[ $k_v ] : array();
				$sach_v = array();
				foreach ( $ds_v as $x_v ) {
					$x_v = sanitize_text_field( (string) $x_v );
					if ( '' !== $x_v ) { $sach_v[] = $x_v; }
				}
				$cfg[ $k_v ] = $sach_v;
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

		/* CỬA HÀNG TRƯỞNG THÊM NGƯỜI MỚI — xem `VHCC_NhanSu::them_nv_cua_hang()`. */
		if ( 'them_nv' === $viec ) {
			/* ⚠️ ẢNH RỬA TRƯỚC, TẠO HỒ SƠ SAU — nhưng ảnh HỎNG thì VẪN tạo hồ sơ.
			   Ảnh là phần không bắt buộc (anh Thắng chốt thế); chối cả lượt thêm người chỉ vì
			   một tấm ảnh sai định dạng là đổi một tiện ích thành một cửa chặn. */
			$anh_kq = VHCC_NhanSu::rua_anh_the(
				isset( $_FILES['tn_anh'] ) ? $_FILES['tn_anh'] : array() );
			$r = VHCC_NhanSu::them_nv_cua_hang( $toi, array(
				'anh_the'   => ! empty( $anh_kq['ok'] ) ? $anh_kq['anh'] : '',
				'ho_ten'    => isset( $_POST['tn_ho_ten'] ) ? wp_unslash( $_POST['tn_ho_ten'] ) : '',
				'cccd'      => isset( $_POST['tn_cccd'] ) ? wp_unslash( $_POST['tn_cccd'] ) : '',
				'sdt'       => isset( $_POST['tn_sdt'] ) ? wp_unslash( $_POST['tn_sdt'] ) : '',
				'gioi_tinh' => isset( $_POST['tn_gioi_tinh'] ) ? wp_unslash( $_POST['tn_gioi_tinh'] ) : '',
				'chuc_vu'   => isset( $_POST['tn_chuc_vu'] ) ? wp_unslash( $_POST['tn_chuc_vu'] ) : '',
				'cua_hang'  => isset( $_POST['tn_coso'] ) ? wp_unslash( $_POST['tn_coso'] ) : '',
			) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			/* 🔴 NÓI RA BƯỚC TIẾP THEO, KHÔNG CHỈ NÓI "ĐÃ THÊM". Hồ sơ mở xong mà người mới
			   chưa có mã PIN thì họ vẫn chưa chấm công được — mà cửa hàng trưởng thì tưởng đã
			   xong. Câu này là chỗ duy nhất họ đọc sau khi bấm. */
			$bao_tn = array( array( 'xong' => 'Đã thêm ' . $r['ma_nv'] . ' vào ' . $r['coso'] . '. '
				. $r['day_may'] . ' Bảo người mới vào trang Chấm công → "Quên PIN?" → gõ họ tên '
				. 'và số căn cước của mình để tự đặt mã PIN.' ) );
			if ( empty( $anh_kq['ok'] ) ) {
				$bao_tn[] = array( 'canh' => 'Hồ sơ đã tạo, nhưng ẢNH THẺ không nhận được: '
					. ( isset( $anh_kq['error'] ) ? $anh_kq['error'] : 'lỗi không rõ' )
					. ' Mở hồ sơ người này rồi tải ảnh lên sau.' );
			}
			return $bao_tn;
		}

		if ( 'sua_gio' === $viec ) {
			$cs_g  = isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '';
			$ngay_g = isset( $_POST['ngay'] ) ? wp_unslash( $_POST['ngay'] ) : '';
			$ma_g   = isset( $_POST['ma_nv'] ) ? wp_unslash( $_POST['ma_nv'] ) : '';
			$ly_g   = isset( $_POST['ly_do'] ) ? wp_unslash( $_POST['ly_do'] ) : '';
			$v_g    = isset( $_POST['sg_vao'] ) ? wp_unslash( $_POST['sg_vao'] ) : '';
			$r_g    = isset( $_POST['sg_ra'] ) ? wp_unslash( $_POST['sg_ra'] ) : '';

			/* 🔴 MỘT LƯỢT SỬA CÓ THỂ CHẠM NHIỀU DÒNG (anh Thắng 27/08/2026: *"nếu cơ sở được ghép
			   từ 2 cơ sở, thì khi sửa sẽ sửa luôn được cả 2 là 4 giờ vào ra"*).
			   Dạng ô ĐƠN vẫn phải chạy: biểu mẫu đang mở sẵn trên máy ai đó trước lúc cập nhật
			   plugin sẽ gửi lên đúng dạng cũ, và bắt người ta bấm lại là mất luôn cả ô Vì sao. */
			if ( ! is_array( $v_g ) && ! is_array( $r_g ) ) {
				$r = VHCC_Bu::sua( $toi, array(
					'coso'    => $cs_g,
					'ngay'    => $ngay_g,
					'ma_nv'   => $ma_g,
					'vao'     => $v_g,
					'ra'      => $r_g,
					'xoa_vao' => ! empty( $_POST['sg_xoa_vao'] ),
					'xoa_ra'  => ! empty( $_POST['sg_xoa_ra'] ),
					'ly_do'   => $ly_g,
				) );
				if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
				return array( array( 'xong' => self::chu_sua( array( $r ) ) ) );
			}

			$v_g = (array) $v_g;
			$r_g = (array) $r_g;
			$xv  = isset( $_POST['sg_xoa_vao'] ) ? (array) wp_unslash( $_POST['sg_xoa_vao'] ) : array();
			$xr  = isset( $_POST['sg_xoa_ra'] ) ? (array) wp_unslash( $_POST['sg_xoa_ra'] ) : array();
			/* ⚠️ CHỈ CHO SỬA TRONG ĐÚNG CHÙM CƠ SỞ CỦA `ccs`. Khoá ô là do biểu mẫu gửi lên, tức
			   là người gửi đổi được — không chặn ở đây thì gõ một tên cơ sở bất kỳ vào khoá là
			   sửa được bảng công của cơ sở mình không có quyền xem. `VHCC_Bu::sua()` có gác quyền
			   riêng, nhưng gác hai lớp mới là gác. */
			$chum = array_map( 'strtoupper', VHCC_Luong::chum_cua( $cs_g ) );
			$ma_goc = VHCC_Nhan::tach_hau_to( $ma_g );
			$ma_goc = $ma_goc[0];

			$xong = array();
			$loi  = array();
			foreach ( array_keys( $v_g + $r_g ) as $khoa ) {
				$khoa = (string) $khoa;
				$phan = explode( '~', $khoa, 2 );
				$cs_i = VHCC_NhanSu::chuan_coso( $phan[0] );
				$ht_i = isset( $phan[1] ) ? strtoupper( trim( $phan[1] ) ) : '';
				if ( ! in_array( strtoupper( $cs_i ), $chum, true ) ) {
					$loi[] = $phan[0] . ': không thuộc chùm cơ sở đang xem';
					continue;
				}
				if ( '' !== $ht_i && ! in_array( $ht_i, array( 'TT', 'TG', 'CD', 'CT', 'TC' ), true ) ) {
					$loi[] = $cs_i . ': hậu tố lạ "' . $ht_i . '"';
					continue;
				}
				$vao_i = isset( $v_g[ $khoa ] ) ? (string) $v_g[ $khoa ] : '';
				$ra_i  = isset( $r_g[ $khoa ] ) ? (string) $r_g[ $khoa ] : '';
				$xv_i  = ! empty( $xv[ $khoa ] );
				$xr_i  = ! empty( $xr[ $khoa ] );
				/* Dòng KHÔNG ĐỘNG TỚI thì bỏ qua LẶNG LẼ. Gọi `sua()` cho nó là ăn ngay câu
				   "Không có gì thay đổi" và cả lượt sửa hỏng vì một dòng người ta cố ý để yên. */
				if ( '' === trim( $vao_i ) && '' === trim( $ra_i ) && ! $xv_i && ! $xr_i ) { continue; }
				$r = VHCC_Bu::sua( $toi, array(
					'coso'    => $cs_i,
					'ngay'    => $ngay_g,
					'ma_nv'   => $ma_goc . ( '' !== $ht_i ? '-' . $ht_i : '' ),
					'vao'     => $vao_i,
					'ra'      => $ra_i,
					'xoa_vao' => $xv_i,
					'xoa_ra'  => $xr_i,
					'ly_do'   => $ly_g,
				) );
				if ( empty( $r['ok'] ) ) { $loi[] = self::ten_dong_sua( $cs_i, $ht_i ) . ': ' . $r['error']; }
				else { $xong[] = $r; }
			}
			/* 🔴 SỬA ĐƯỢC DÒNG NÀO THÌ BÁO DÒNG ẤY, HỎNG DÒNG NÀO BÁO DÒNG ẤY. Nuốt lỗi đi là
			   người ta đọc "Đã sửa" rồi bỏ đi, trong khi ca đêm vẫn nguyên giờ cũ. */
			if ( ! $xong && ! $loi ) {
				return array( array( 'loi' => 'Không ô giờ nào được điền — gõ giờ mới hoặc tích Xoá trắng.' ) );
			}
			if ( ! $xong ) { return array( array( 'loi' => implode( ' · ', $loi ) ) ); }
			$noi = self::chu_sua( $xong );
			if ( $loi ) { $noi .= ' CHƯA sửa được: ' . implode( ' · ', $loi ) . '.'; }
			return array( array( 'xong' => $noi ) );
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

		/* ---------------------------------------------------------------------------------
		 * ĐI TRỄ: mức cho phép của cửa hàng · duyệt đơn · không duyệt · nhân viên nộp đơn.
		 * ------------------------------------------------------------------------------- */
		if ( 'muc_tre' === $viec ) {
			$r = VHCC_Tre::dat( $toi, isset( $_POST['ccs'] ) ? wp_unslash( $_POST['ccs'] ) : '',
				isset( $_POST['muc'] ) ? wp_unslash( $_POST['muc'] ) : '' );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => empty( $r['bo_khai'] )
				? 'Từ giờ ai vào trễ quá ' . (int) $r['phut'] . ' phút mà không có đơn thì ô ngày '
					. 'đó vàng lên. Số giờ không đổi.'
				: 'Đã bỏ mức riêng — cửa hàng này quay về dùng mức ' . (int) $r['phut'] . ' phút.' ) );
		}

		if ( 'duyet_tre' === $viec || 'choi_tre' === $viec ) {
			$dat = ( 'duyet_tre' === $viec ) ? VHCC_XinTre::DUYET : VHCC_XinTre::TU_CHOI;
			$r   = VHCC_XinTre::duyet( $toi, isset( $_POST['don'] ) ? wp_unslash( $_POST['don'] ) : 0,
				$dat, isset( $_POST['ly_do_choi'] ) ? wp_unslash( $_POST['ly_do_choi'] ) : '' );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			/* Nói ra HẬU QUẢ, không chỉ "đã duyệt": người bấm cần biết cái ô vàng kia có tắt
			   hay không, vì đó mới là lý do họ bấm. */
			return array( array( 'xong' => ( VHCC_XinTre::DUYET === $dat )
				? 'Đã duyệt đơn của ' . $r['ma_nv'] . ' ngày ' . $r['ngay']
					. '. Ô vàng của đúng ngày đó thôi kêu — số giờ giữ nguyên.'
				: 'Đã ghi KHÔNG duyệt đơn của ' . $r['ma_nv'] . ' ngày ' . $r['ngay']
					. '. Ô vàng vẫn còn.' ) );
		}

		if ( 'xin_tre' === $viec ) {
			$r = VHCC_XinTre::nop( $toi, array(
				'ngay'    => isset( $_POST['xt_ngay'] ) ? wp_unslash( $_POST['xt_ngay'] ) : '',
				'so_phut' => isset( $_POST['xt_phut'] ) ? wp_unslash( $_POST['xt_phut'] ) : '',
				'ly_do'   => isset( $_POST['xt_ly_do'] ) ? wp_unslash( $_POST['xt_ly_do'] ) : '',
			) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => ( empty( $r['lai'] ) ? 'Đã gửi đơn' : 'Đã gửi lại đơn' )
				. ' xin phép đi trễ ' . (int) $r['phut'] . ' phút ngày ' . $r['ngay']
				. '. Cửa hàng trưởng ' . $r['coSo'] . ' sẽ thấy nó trong mục Lệnh đi trễ.'
				. ( empty( $r['muon'] ) ? ''
					: ' ⚠ Ngày này đã qua nên đơn được ghi là NỘP MUỘN — xin phép trước khi tới '
						. 'cửa hàng thì mới đúng nghĩa xin phép.' ) ) );
		}

		if ( 'cho_tra' === $viec ) {
			$r = VHCC_TraVe::dat( $toi,
				isset( $_POST['tv_ma'] ) ? wp_unslash( $_POST['tv_ma'] ) : '',
				isset( $_POST['tv_bat'] ) && '1' === (string) wp_unslash( $_POST['tv_bat'] ) );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			return array( array( 'xong' => $r['bat']
				? 'Đã đánh dấu ' . $r['ma_nv'] . ( '' !== $r['ho_ten'] ? ' — ' . $r['ho_ten'] : '' )
					. ' chờ trả về nhân sự. Người này VẪN ở ' . $r['coSo'] . ', chỉ nằm riêng ở '
					. 'cuối bảng. Tháng nào đã hết mà không phát sinh công thì hệ tự trả về kho '
					. 'nhân sự — hồ sơ và công cũ vẫn còn nguyên.'
				: 'Đã bỏ đánh dấu ' . $r['ma_nv'] . ' — người này ở lại ' . $r['coSo'] . ' như cũ.' ) );
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

		if ( 'ghep_cs' === $viec ) {
			$g = isset( $_POST['gh'] ) ? (array) wp_unslash( $_POST['gh'] ) : array();
			$sach_g = array();
			foreach ( $g as $phu_g => $chinh_g ) {
				$sach_g[ sanitize_text_field( $phu_g ) ] = sanitize_text_field( $chinh_g );
			}
			$r = VHCC_Luong::dat_ghep( $toi, $sach_g );
			if ( empty( $r['ok'] ) ) { return array( array( 'loi' => $r['error'] ) ); }
			/* 🔴 KHAI MỘT DÒNG MÀ IM LẶNG BỎ NÓ LÀ CUỐI THÁNG THIẾU CÔNG CẢ MỘT CA, mà màn hình
			   đã báo "đã lưu". Kể đích danh dòng nào không nhận và vì sao. */
			$bao_g = array( array( 'xong' => 'Đã lưu ghép bảng công: ' . (int) $r['so'] . ' cơ sở '
				. 'đang ghép vào bảng khác. Bảng công đọc lại ngay — không giờ chấm nào bị sửa.' ) );
			if ( ! empty( $r['bo_qua'] ) ) {
				$bao_g[] = array( 'canh' => 'KHÔNG nhận ' . count( $r['bo_qua'] ) . ' dòng: '
					. implode( ' · ', $r['bo_qua'] ) );
			}
			return $bao_g;
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
			/* Danh sách trắng phải gộp cả vai tự tạo (`VHCC_Vai::ds_ten()`) — xem lý do đầy đủ ở
			   `VHCC_NhanSu::dat_vai_tro()`. Dùng `VHCC_Auth::VAI_TRO_TAT_CA` không thôi thì đặt
			   hàng loạt một vai vừa khai ở "Bảng vai trò" luôn bị chối "không hợp lệ". */
			if ( ! in_array( $vt, VHCC_Vai::ds_ten(), true ) ) {
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
					/* Gộp cả vai tự tạo — xem `VHCC_NhanSu::dat_vai_tro()`. */
					if ( '' !== $v && ! in_array( $v, VHCC_Vai::ds_ten(), true ) ) { continue; }
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

		/* 🔴 CƠ SỞ PHỤ ĐẾN TỪ NHIỀU Ô, GOM LẠI THÀNH MỘT CHUỖI.
		   Màn sửa đủ nay dùng lưới ô tích (xem `o_coso_phu()`), nên giá trị về dưới dạng MẢNG
		   `coso_phu_o[]` — mỗi ô tích một phần tử, cộng thêm ô gõ tay ở cuối cho cơ sở chưa có
		   trong sổ. Cột trong bảng vẫn là một chuỗi ngăn bằng dấu phẩy, y như cũ: đổi cách
		   NHẬP chứ không đổi cách LƯU, nên mọi chỗ đang đọc cột này không phải sửa gì.

		   ⚠️ Bỏ trùng theo CHỮ THƯỜNG CÓ DẤU (`chu_thuong`, không phải `strtolower` — hàm ấy
		      không hạ được chữ có dấu). Tích một cơ sở rồi gõ lại chính nó ở ô cuối là chuyện
		      thường, mà "FZ_LTVT, FZ_LTVT" thì mọi phép đếm cơ sở đều lệch.

		   ⚠️ MẢNG RỖNG LÀ XOÁ HẾT, ĐÚNG Ý NGƯỜI BẤM: bỏ tích hết rồi Lưu nghĩa là người này
		      thôi làm ở cơ sở phụ nào. Nhưng biểu mẫu KHÔNG gửi gì khi không có ô tích nào —
		      nên ô gõ tay ở cuối luôn có mặt, và nó bảo đảm `coso_phu_o` luôn được gửi lên. */
		if ( isset( $_POST['coso_phu_o'] ) && is_array( $_POST['coso_phu_o'] ) ) {
			$cp  = array();
			$da  = array();
			foreach ( (array) wp_unslash( $_POST['coso_phu_o'] ) as $x ) {
				foreach ( explode( ',', (string) $x ) as $m ) {
					$m = trim( $m );
					if ( '' === $m ) { continue; }
					$k = VHCC_NhanSu::chu_thuong( $m );
					if ( isset( $da[ $k ] ) ) { continue; }
					$da[ $k ] = 1;
					$cp[]     = $m;
				}
			}
			$ghi['coso_phu'] = implode( ', ', $cp );
		}

		foreach ( self::COT_SUA as $c ) {
			/* Ô tích ở trên đã lo cột này; một ô `coso_phu` gõ tay còn sót lại trong biểu mẫu
			   sẽ ghi đè mất kết quả gom. */
			if ( 'coso_phu' === $c && isset( $ghi['coso_phu'] ) ) { continue; }
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
				   nhập được, vừa nhìn như đã khai xong. Gộp cả vai tự tạo — xem
				   `VHCC_NhanSu::dat_vai_tro()`. */
				if ( '' !== $v && ! in_array( $v, VHCC_Vai::ds_ten(), true ) ) { continue; }
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

	/**
	 * BẢNG MÀU & KIỂU DÙNG CHUNG CHO MỌI TRANG NGOÀI WEB CỦA HỆ CHẤM CÔNG.
	 *
	 * 🔴 CÔNG KHAI CÓ CHỦ Ý. Trang Quản lý nhân sự (`VHCC_TrangNS`) là một địa chỉ riêng nhưng
	 *    phải trông y hệt trang này — hai trang cùng một hệ mà lệch font, lệch nút, lệch màu báo
	 *    lỗi thì người dùng tưởng mình lạc sang chỗ khác. Chép CSS sang tệp thứ hai là hôm nào
	 *    sửa một bên thì bên kia lệch, và không có gì báo.
	 */
	public static function css() {
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
			/* ==================================================================== khung HR V5.2
			   Anh Thắng 27/08/2026, kèm ba ảnh phần mềm HR V5.2 của Mr Trung: *"Chỗ phần giao
			   diện và tính năng của trang chấm công thiết kế đẹp mắt y như này"*.

			   Mẫu ấy là một khung QUẢN TRỊ đúng nghĩa: cột dọc tối bên trái giữ mọi đầu việc,
			   vùng nội dung sáng bên phải, và mỗi màn mở đầu bằng một tiêu đề có gạch chân.
			   Thanh nút NGANG cũ hỏng dần theo số màn: nay đã tám mục, chúng xuống hai hàng,
			   và mục nào nằm hàng dưới thì mắt không quét tới.

			   ⚠️ CỘT DỌC CHỈ TRÊN MÀN RỘNG. Anh mở bằng điện thoại nhiều hơn máy tính, mà một
			      cột 220px trên màn 390px là ăn hơn nửa bề ngang. Dưới 1000px thì cột ấy nằm
			      ngang trên đầu và cuộn ngang được — vẫn đủ mọi mục, không mất mục nào.
			   ⚠️ KHÔNG một dòng script — cùng luật với cả màn quản trị. Cột dọc, thẻ, gập/xổ đều
			      là CSS thuần. */
			. '.ung{display:block}'
			. '/* 🔴 `aside.canh`, KHÔNG PHẢI `.canh` TRẦN. Anh Thắng 28/08/2026 gửi ảnh hai khối cảnh báo cao vống gần hết màn hình: *"không có mã mà sao ra rộng thế"*. Lý do là hai class KHÁC NGHĨA trùng tên: `.canh` của thanh điều hướng bên (cạnh trang), và `.bao canh` của thẻ cảnh báo. Thẻ cảnh báo ăn phải `height:100vh` của thanh bên nên khối nào cũng cao đúng một màn hình, dù bên trong chỉ có một dòng chữ. Buộc vào đúng thẻ `<aside>` thì hai thứ thôi giẫm lên nhau, mà không phải đổi tên class ở hàng chục chỗ đang dùng. */'
			. 'aside.canh{background:#0f2744;color:#cbd5e1;display:flex;flex-direction:column}'
			. '.canh-hieu{padding:14px 16px 12px;border-bottom:1px solid rgba(255,255,255,.08)}'
			/* 🔴 `flex:1` CỦA `.hieu` PHẢI BỊ GỠ Ở ĐÂY.
			   Anh Thắng 27/08/2026, kèm ảnh cột dọc: *"bị lệch"* — một khoảng đen mênh mông giữa
			   logo và mục đầu tiên, menu bị đẩy xuống quá nửa cột.
			   Nguyên do: logo mang cả hai lớp `hieu canh-hieu`, mà `.hieu{flex:1}` vốn dựng cho
			   thanh đầu trang CŨ — một flex hàng NGANG, ở đó `flex:1` để logo ăn hết chỗ thừa và
			   đẩy nút Thoát sang phải. Nay logo nằm trong flex CỘT, nên đúng cái `flex:1` ấy làm
			   nó giãn theo CHIỀU CAO và ăn hết chỗ của menu.
			   ⚠️ Một lớp dùng lại ở ngữ cảnh khác thì thuộc tính của nó đổi nghĩa. Giữ `.hieu`
			      (phép thử canh logo vẫn là liên kết về trang chính) nhưng phải nói lại chiều cao. */
			. 'a.canh-hieu{display:block;flex:0 0 auto;text-decoration:none;color:#fff;font-size:16px}'
			. '.canh-hieu b{font-size:21px;color:#fff;letter-spacing:.5px;line-height:1.2}'
			. '.canh-hieu span{display:block;font-size:10.5px;letter-spacing:1.2px;text-transform:uppercase;'
			. 'color:#7c9cc4;margin-top:3px}'
			. '.canh-nav{display:flex;gap:2px;overflow-x:auto;padding:8px}'
			. '.canh-nav a{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:8px;'
			. 'color:#cbd5e1;text-decoration:none;font-size:14px;font-weight:600;white-space:nowrap}'
			. '.canh-nav a:hover{background:rgba(255,255,255,.07);color:#fff}'
			/* Mục ĐANG MỞ phải khác hẳn, không chỉ đậm hơn một chút: cột này có tám mục và người
			   ta liếc chứ không đọc. */
			. '.canh-nav a.dang{background:var(--xanh);color:#fff}'
			. '.canh-nav a .bt{font-size:15px;line-height:1;width:18px;text-align:center}'
			. '.canh-duoi{padding:10px 12px;border-top:1px solid rgba(255,255,255,.08);font-size:12px}'
			. '.canh-ai{background:rgba(255,255,255,.06);border-radius:8px;padding:8px 10px;margin-bottom:8px}'
			. '.canh-ai b{display:block;color:#fff;font-size:13px}'
			. '.canh-ai span{color:#7c9cc4}'
			. '.canh-duoi form{margin:0}'
			. '.canh-duoi button{width:100%;background:var(--do);border-color:var(--do);color:#fff}'
			. '.canh-pb{color:#5c7ba3;font-size:11px;margin-top:8px;line-height:1.5}'
			. '@media(min-width:1000px){'
			. '.ung{display:grid;grid-template-columns:232px minmax(0,1fr);min-height:100vh}'
			. 'aside.canh{position:sticky;top:0;height:100vh;overflow-y:auto}'
			. '.canh-nav{flex-direction:column;overflow:visible;padding:10px 8px;flex:1}'
			. '.canh-duoi{padding:12px}'
			. '}'
			/* Tiêu đề màn — chữ hoa, gạch chân xanh chạy dưới đúng bề rộng chữ. Mỗi màn phải tự
			   nói mình là màn nào; không có nó thì tám màn mở ra trông giống hệt nhau. */
			. '.tieu-man{background:var(--the);border:1px solid var(--vien);border-radius:10px;'
			. 'padding:12px 16px;margin:0 0 16px;display:flex;align-items:center;'
			. 'gap:12px;flex-wrap:wrap}'
			. '.tieu-man h1{font-size:17px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;'
			. 'margin:0;padding-bottom:5px;border-bottom:3px solid var(--xanh);flex:0 0 auto}'
			. '.tieu-man .mo{margin:0}'

			/* ================================================== nhãn khối, theo mẫu HR V5.2 (ảnh 2)
			   Trong mẫu, mỗi khối mở đầu bằng một NHÃN chữ hoa nền nhạt nằm sát mép trên — nhìn
			   là biết khối bắt đầu từ đâu.
			   🔴 Vì sao cần: màn Máy & Firmware có CHÍN khối liền nhau, màn Cấu hình có năm. Hiện
			      chúng là chín cái thẻ trắng nối đuôi, tiêu đề chìm vào giữa đám chữ, nên cuộn
			      xuống là mất dấu mình đang ở khối nào. Người trực một cửa hàng mất chấm công
			      phải đọc từ đầu màn xuống mới tìm được khối mình cần.
			   ⚠️ Chỉ áp cho `h2`/`h3` là CON TRỰC TIẾP của `.the`. Mấy `h3` nằm sâu bên trong
			      (trong `<details>`, trong bảng) là tiêu đề phụ — bôi nhãn cho chúng nữa thì cả
			      màn đầy nhãn, và nhãn hết nghĩa. */
			. '.the>h2,.the>h3{font-size:13px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;'
			. 'color:#1e40af;background:#eff6ff;border:1px solid #dbeafe;border-radius:8px;'
			. 'padding:7px 11px;margin:-4px -4px 12px;display:inline-block}'
			. '.the h2{font-size:15px;margin:0 0 4px}'
			. '.mo{color:var(--mo);font-size:13px;margin:4px 0}'
			. 'label{display:block;font-size:13px;color:var(--mo);margin:0 0 3px}'
			/* Nhãn CHO TRÌNH ĐỌC MÀN HÌNH, không hiện ra. Bảng tên cơ sở có 21 ô nhập giống hệt
			   nhau; không có nhãn thì người dùng trình đọc nghe 21 lần "ô nhập" mà không biết ô
			   nào của mã nào. `display:none` thì trình đọc cũng bỏ qua — phải kéo ra ngoài khung
			   nhìn chứ không được ẩn hẳn. */
			. '.an{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);'
			. 'white-space:nowrap}'
			. 'input,select,textarea{font:inherit;padding:7px 9px;border:1px solid #cbd5e1;'
			. 'border-radius:7px;background:#fff;color:var(--chu);max-width:100%}'
			. 'input:focus,select:focus{outline:2px solid var(--xanh);outline-offset:1px}'
			. '.hang{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}'
			. 'button{font:inherit;font-weight:600;padding:8px 14px;border-radius:7px;border:1px solid #cbd5e1;'
			. 'background:#fff;color:var(--chu);cursor:pointer}'
			. 'button.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}'
			. 'button.nguy{background:var(--do);border-color:var(--do);color:#fff}'
			/* Nút theo VIỆC, không theo chỗ đứng — mẫu HR V5.2 (ảnh 2) tô xanh lá cho "Thêm",
			   cam cho "Tải dữ liệu". Màu là thứ mắt đọc trước chữ, nên nó phải nói đúng: xanh lá
			   = thêm mới, cam = việc chạy lâu và chạm ra ngoài (đọc máy, nạp tệp). */
			. 'button.them{background:var(--luc);border-color:var(--luc);color:#fff}'
			. 'button.chay{background:var(--vang);border-color:var(--vang);color:#fff}'
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
			/* ================================= đánh số hàng, theo mẫu HR V5.2 (ảnh 1 và 2)
			   Mẫu có một cột số chạy dọc bên trái mọi bảng danh sách. Không phải trang trí: bảng
			   máy có 26 dòng, bảng lịch cả tháng có mấy trăm — người trực gọi điện cho cửa hàng
			   mà nói được "dòng 12" thì hai bên nhìn cùng một chỗ, còn không thì phải đọc cả mã
			   lẫn tên ra để đối chiếu.
			   ⚠️ ĐÁNH SỐ BẰNG CSS, không thêm một cột `<td>` nào. Thêm cột thật là mỗi bảng phải
			      sửa cả `<thead>` lẫn mọi `colspan` của hàng tổng — chỗ nào quên là bảng lệch
			      một ô và không có gì báo. Số ở đây là thứ để NHÌN, không phải dữ liệu.
			   ⚠️ Chỉ áp cho bảng khai lớp `stt`. Bôi cho mọi bảng thì bảng hai dòng cũng có số,
			      và bảng lưới cả tháng (cột đầu là tên người) bị chèn số vào giữa tên. */
			. 'table.stt tbody{counter-reset:d}'
			. 'table.stt tbody tr{counter-increment:d}'
			. 'table.stt tbody td:first-child::before{content:counter(d) ". ";color:var(--mo);'
			. 'font-size:11.5px;font-weight:600}'
			/* Hàng TỔNG không phải một dòng dữ liệu — đánh số cho nó là bảng 26 máy hoá ra 27. */
			. 'table.stt tbody tr.tong{counter-increment:none}'
			. 'table.stt tbody tr.tong td:first-child::before{content:none}'
			. '.cuon{overflow-x:auto;-webkit-overflow-scrolling:touch}'
			/* Cột Mã NV DÍNH BÊN TRÁI. Bảng rộng hơn màn hình nên phải cuộn ngang; không ghim
			   cột mã lại thì cuộn sang phải là mất luôn thứ cho biết đang sửa hồ sơ của AI. */
			. '.cuon td:first-child,.cuon th:first-child{position:sticky;left:0;z-index:2;'
			. 'background:var(--the);box-shadow:1px 0 0 var(--vien)}'
			. '.cuon th:first-child{background:#f8fafc}'
			. '.cuon td:last-child{white-space:nowrap}'
			. '.cuon input,.cuon select{padding:6px 8px}'
			. '.bao{border-radius:9px;padding:11px 13px;margin:0 0 12px;border:1px solid}'
			/* Dải kết quả theo mẫu HR V5.2 (ảnh 1): một chấm tròn màu ở đầu dòng rồi tới chữ.
			   Chấm ấy làm dải báo nhận ra được TRƯỚC KHI đọc — người vừa bấm Lưu chỉ cần biết
			   "xanh hay đỏ", và họ liếc chứ không đọc. */
			. '.bao{position:relative;padding-left:30px}'
			. '.bao::before{content:"";position:absolute;left:12px;top:1.05em;width:9px;height:9px;'
			. 'border-radius:50%;background:currentColor;opacity:.75}'
			. '.bao.ok{background:#f0fdf4;border-color:#bbf7d0;color:#15803d}'
			. '.bao.ok b,.bao.loi b,.bao.canh b{color:var(--chu)}'
			. '.bao.loi{color:var(--do)}.bao.canh{color:#b45309}'
			. '.bao.loi{background:#fef2f2;border-color:#fecaca}'
			. '.bao.canh{background:#fffbeb;border-color:#fde68a}'
			/* Ô "thiếu giờ NHƯNG đã có đơn được duyệt": không vàng (thôi kêu), nhưng cũng không
			   trắng trơn — còn một gạch chân xanh để cửa hàng trưởng nhìn ra chỗ nào là do đơn,
			   chứ không phải chỗ nào cũng đủ giờ. */
			. 'td.xin-tre{box-shadow:inset 0 -3px 0 #86efac}'
			/* Hàng của người chờ trả về nhân sự: nền xám nhạt + nút tích đổi màu. Nhạt chứ
			   không đỏ — đây không phải lỗi, chỉ là một chỗ đứng khác. */
			. 'td.cho-tra{background:#f8fafc}'
			. '.cho-tra-nhan{color:#7c3aed;border-color:#ddd6fe}'
			. 'button.mo-hs{background:none;cursor:pointer;font-family:inherit}'
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
			/* Tên người trong lưới là ĐƯỜNG SANG HỒ SƠ. Gạch chân chấm để thấy là bấm được mà
			   không hoá thành một dãy chữ xanh chạy dọc cả cột — cột này có mấy chục dòng. */
			. 'a.ten-nv{color:inherit;text-decoration:none;border-bottom:1px dotted #94a3b8}'
			. 'a.ten-nv:hover{color:var(--xanh);border-bottom-color:var(--xanh)}'
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
			/* Dòng phụ TRONG ô: hàng ca đêm / tăng cường nay nằm cùng ô với hàng chính thay vì
			   chiếm một hàng riêng. Nhỏ hơn và có vạch ngăn mảnh ở trên — để mắt vẫn tách được
			   "số của hàng chính" với "số của hàng phụ", thứ mà trước đây cái hàng riêng lo. */
			. '.mdem{font-size:10px;font-weight:600;line-height:1.15;margin-top:2px;padding-top:1px;'
			. 'border-top:1px dotted var(--vien);opacity:.95;border-radius:0 0 3px 3px}'
			. '.mdem code{font-size:9px;opacity:.8}'
			. '.mdem.hong{background:#fef2f2;color:var(--do)}'
			/* Ngày đứng ở CƠ SỞ KHÁC: xám, không mang màu ca nào — để mắt tách ngay khỏi mấy
			   dòng thuộc về bảng đang đọc. Con số ở đây KHÔNG nằm trong cột TỔNG. */
			. '.mdem.ngoai{background:#f1f5f9;color:#475569;font-style:italic}'
			. '.duoi.ngoai{background:#f1f5f9;color:#475569}'
			/* Nhãn CHÍNH / PHỤ trong bảng khai ghép — nằm cạnh TÊN, vì đó là chỗ mắt dừng
			   lại đầu tiên. Hai màu khác hẳn nhau: đọc lướt cả cột là thấy ngay cấu trúc. */
			. '.duoi.nhan-chinh{background:#dcfce7;color:#15803d}'
			. '.duoi.nhan-phu{background:#f3e8ff;color:#7e22ce}'
			. 'tr.hang-phu>td:first-child{padding-left:22px}'
			. 'tr.hang-phu>td{background:#fcfaff}'
			/* Dòng TỔNG của cơ sở khác: xám, nghiêng, có vạch ngăn — để mắt không đọc nhầm nó
			   là một phần của con số lớn phía trên. */
			/* Nhãn cơ sở PHỤ đã ghép: xanh nhạt, KHÁC hẳn dòng xám của cơ sở khác. Con số này
			   ĐÃ nằm trong TỔNG; dòng xám thì không. Cho chúng giống nhau là mời người ta trừ
			   nhầm một trong hai. */
			/* Tách công trong ô TỔNG: nhỏ và nhạt hơn con số lớn — con số lớn vẫn là thứ đọc
			   trước, phần tách là thứ liếc thấy. Đảo cỡ chữ là cột TỔNG thành một đoạn văn. */
			. '.tach-cong{font-size:10.5px;font-weight:500;line-height:1.3;margin-top:2px;'
			. 'color:var(--mo);white-space:nowrap}'
			. '.tach-cong b{font-weight:700;color:var(--chu)}'
			. '.mghep{font-size:9.5px;font-weight:700;line-height:1.15;margin-top:2px;padding:0 3px;'
			. 'border-radius:3px;background:#e0f2fe;color:#0369a1;letter-spacing:.2px}'
			. '.tk-ngoai{font-size:10.5px;font-weight:600;line-height:1.25;margin-top:3px;padding-top:2px;'
			. 'border-top:1px dotted var(--vien);color:#475569;font-style:italic;white-space:nowrap}'
			. '.mdem.ca1{background:#dbeafe;color:#1d4ed8}.mdem.ca2{background:#dcfce7;color:#15803d}'
			. '.mdem.ca3{background:#f3e8ff;color:#7e22ce}.mdem.ca4{background:#ffedd5;color:#c2410c}'
			/* Ô bấm được: đường liên kết phủ KÍN ô, giữ nguyên màu chữ. Chỉ tô nền khi rê chuột
			   — tô sẵn thì cả lưới 600 ô xanh lè, không còn nhìn ra màu theo ca nữa. */
			. 'table.cc a.o-sua{display:block;margin:-3px -4px;padding:3px 4px;color:inherit;'
			. 'text-decoration:none;border-radius:3px}'
			. 'table.cc a.o-sua:hover{background:#1d4ed8;color:#fff;box-shadow:0 0 0 2px #1d4ed8}'
			. 'table.cc a.o-sua:focus-visible{outline:2px solid var(--xanh);outline-offset:1px}'
			/* Ô đang mở để sửa: viền đậm để mắt tìm lại được nó giữa 600 ô. */
			/* 🔴 BẤM SỬA THÌ ĐỪNG NHẢY LÊN ĐỈNH. Anh Thắng 27/08/2026: *"khi bấm sửa công nó cứ
			   nhảy lên như này, chỉnh đứng yên cho anh"*.
			   Đường bấm mang neo `#suaday`, nên trình duyệt cuộn hàng sửa lên SÁT đỉnh — mà đỉnh
			   thì có thanh đầu trang dính đè lên, và cả hàng của người đang sửa cũng bị đẩy khuất.
			   `scroll-margin-top` bảo trình duyệt chừa sẵn khoảng ấy: hàng sửa dừng ngay dưới
			   thanh, cùng với hàng của người đó còn trong tầm mắt. Ghim cho cả ô đang sửa, vì
			   nhánh chấm bù nhảy tới `#bucong` chứ không phải `#suaday`. */
			/* Chừa chỗ cho thanh đầu trang khi trình duyệt nhảy tới neo. Neo nay nằm trên hàng
			   NGƯỜI (`#suaday`), nên hàng ấy dừng dưới thanh chứ không chui vào sau nó. */
			. 'table.cc tr#suaday,table.cc tr.hang-sua,table.cc td.dang-sua{scroll-margin-top:96px}'
			/* Hàng hồ sơ cũng chừa chỗ cho thanh đầu trang khi trình duyệt nhảy tới neo `#hs-…`. */
			. 'tr[id^="hs-"]{scroll-margin-top:96px}'
			. 'table.cc td.dang-sua{outline:3px solid var(--do);outline-offset:-3px}'
			/* Hàng sửa nội tuyến: nền khác hẳn, và chữ về cỡ thường (lưới đang 11.5px). */
			/* 🔴 RUỘT HÀNG SỬA DÍNH BÊN TRÁI. Anh Thắng 27/08/2026: *"lệch ô sửa"*.
			   `colspan` phủ hết 33 cột nên ô ấy rộng bằng CẢ BẢNG — biểu mẫu bên trong trải theo,
			   ô "Vì sao" dài mấy nghìn điểm ảnh và nút Lưu nằm ngoài tầm nhìn. Người ta thấy hàng
			   sửa mở ra mà không thấy nút bấm, rồi tưởng hỏng.
			   Ghim khối ruột đúng bề rộng khung nhìn: cuộn ngang tới đâu thì hàng sửa vẫn nằm
			   nguyên chỗ mắt đang nhìn. Cùng cơ chế với cột Nhân viên vốn đã ghim bên trái. */
			. '.hs-in{position:sticky;left:0;width:calc(100vw - 56px);max-width:1100px;'
			. 'box-sizing:border-box}'
			. '@media(max-width:640px){.hs-in{width:calc(100vw - 24px)}}'
			. 'table.cc tr.hang-sua>td{background:#fffbeb;border:2px solid var(--vang);'
			. 'text-align:left;white-space:normal;padding:10px 12px;font-size:14px}'
			. 'table.cc tr.hang-sua label{font-size:12px}'
			/* Khối thu gọn bằng <details> của chính HTML — không JavaScript. Phải cho `summary`
			   trông ra một cái nút bấm được, kẻo nó nằm im như một dòng chữ và không ai bấm. */
			/* Ô tích chọn vai: xuống dòng được, mỗi vai một nhãn bấm cả chữ. */
			. '.o-vai-tick{display:flex;flex-wrap:wrap;gap:6px 12px;padding:4px 0}'
			. '.o-vai-tick label{display:inline-flex;align-items:center;gap:5px;margin:0;'
			. 'font-size:13px;color:var(--chu);cursor:pointer}'
			. '.o-vai-tick input{width:auto;margin:0}'
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
			/* Thẻ Truy cập nhanh theo mẫu HR V5.2: một vòng tròn nhạt mang biểu tượng, rồi tên,
			   rồi một câu, rồi dòng "Mở →". Vòng tròn không phải trang trí — tám thẻ chữ giống
			   nhau thì mắt phải ĐỌC từng cái; có hình thì nhận ra thẻ mình cần mà chưa đọc. */
			. '.viec{position:relative;padding-left:62px}'
			. '.viec .bt{position:absolute;left:14px;top:13px;width:36px;height:36px;border-radius:50%;'
			. 'background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:18px}'
			. '.viec .mo-cn{display:block;margin-top:7px;font-size:13px;font-weight:700;color:var(--xanh)}'
			. '.viec-chinh .bt{background:#dbeafe}'
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

	/**
	 * BỊ KHOÁ RIÊNG — nói ra vì sao và ai gỡ được, rồi cho đường thoát.
	 *
	 * ⚠️ VẪN CÓ NÚT THOÁT. Không có nó thì người bị khoá mắc kẹt với một phiên họ không dùng
	 *    được và cũng không bỏ được — phải xoá cookie bằng tay mới đăng nhập tài khoản khác.
	 */
	private static function trang_bi_khoa( $loi ) {
		$tok = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
		echo self::dau( 'Không vào được' );
		echo '<div class="bo" style="max-width:520px;padding-top:56px"><div class="the">';
		echo '<h2>Không vào được trang này</h2>';
		echo '<div class="bao canh">' . esc_html( $loi ) . '</div>';
		echo '<form method="post" style="margin:0">'
			. '<input type="hidden" name="ky" value="' . esc_attr( self::chu_ky( $tok ) ) . '">'
			. '<button name="viec" value="thoat">Thoát</button></form>';
		self::dong_trang( 2 );
	}

	private static function trang_dang_nhap() {
		self::vao_bang_wp();
		$loi = '';
		/* Quên PIN: hai bước, xem `VHCC_QuenPin`. Kết quả để riêng, không trộn vào `$loi` của ô
		   PIN — hai ô khác nhau mà chung một dòng báo thì người ta không biết nó nói về ô nào. */
		$qp = array( 'loi' => '', 'ok' => '', 'the' => '', 'ten' => '' );
		if ( isset( $_POST['qp_viec'] ) ) { $qp = self::viec_quen_pin(); }
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

		if ( '' !== $qp['loi'] ) { echo '<div class="bao loi">' . esc_html( $qp['loi'] ) . '</div>'; }
		if ( '' !== $qp['ok'] )  { echo '<div class="bao ok">' . esc_html( $qp['ok'] ) . '</div>'; }

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
		self::khoi_quen_pin( $qp );
		self::dong_trang( 2 );
	}

	/**
	 * KHỐI "QUÊN PIN" — hai bước, gập sẵn.
	 *
	 * Anh Thắng 28/08/2026: *"Thiếu phần lấy lại mã PIN ( Để lấy lại mã PIN nhập Họ Tên và số
	 * Căn Cước Công Dân )"*.
	 *
	 * 🔴 KHÔNG HIỆN PIN CŨ — luật của trang này từ đầu. Khớp danh tính thì cho ĐẶT PIN MỚI.
	 * ⚠️ Gập sẵn: người vào đây chín trên mười là để gõ PIN, không phải để lấy lại. Mở sẵn khi
	 *    đang dở bước 2, kẻo họ vừa khớp danh tính xong lại phải đi tìm cái ô.
	 */
	private static function khoi_quen_pin( $qp ) {
		$dang = ( '' !== $qp['the'] );
		echo '<div class="the" style="margin-top:14px"><details' . ( $dang ? ' open' : '' ) . '>';
		echo '<summary><b>Quên PIN?</b> — lấy lại bằng họ tên và số căn cước</summary>';

		if ( ! $dang ) {
			/* Không ai khai CCCD thì đường này vô dụng — nói ra, đừng để người ta gõ mãi. */
			$co = VHCC_QuenPin::so_co_cccd();
			if ( ! $co ) {
				echo '<div class="bao canh">Chưa hồ sơ nào khai <b>số căn cước</b>, nên chưa ai lấy '
					. 'lại PIN bằng đường này được. Nhờ quản lý khai ô <b>CCCD</b> trong hồ sơ nhân '
					. 'sự trước.</div></details></div>';
				return;
			}
			echo '<p class="mo">Gõ đúng như trong hồ sơ nhân sự. Khớp rồi thì đặt PIN mới ngay — '
				. '<b>hệ thống không hiện PIN cũ ra màn hình</b> bao giờ.</p>';
			echo '<form method="post"><input type="hidden" name="qp_viec" value="tra">';
			echo '<div><label for="qp_ten">Họ tên</label>'
				. '<input id="qp_ten" name="qp_ten" required style="width:100%"></div>';
			echo '<div style="margin-top:8px"><label for="qp_cccd">Số căn cước công dân</label>'
				. '<input id="qp_cccd" name="qp_cccd" inputmode="numeric" required style="width:100%"></div>';
			echo '<button class="chinh" style="width:100%;margin-top:10px">Tra hồ sơ</button></form>';
			echo '</details></div>';
			return;
		}

		echo '<p><span class="chu-luc">✓ Khớp hồ sơ ' . esc_html( $qp['ten'] ) . '.</span> '
			. 'Đặt PIN mới ngay bây giờ — <b>ô này sống 5 phút</b>.</p>';
		echo '<form method="post"><input type="hidden" name="qp_viec" value="dat">';
		echo '<input type="hidden" name="qp_the" value="' . esc_attr( $qp['the'] ) . '">';
		echo '<div><label for="qp_moi">PIN mới (4–8 chữ số)</label>'
			. '<input id="qp_moi" name="qp_moi" type="password" inputmode="numeric" required '
			. 'autocomplete="off" style="width:100%;font-size:19px;letter-spacing:3px;text-align:center">'
			. '</div>';
		echo '<button class="chinh" style="width:100%;margin-top:10px">Đặt PIN mới</button></form>';
		echo '</details></div>';
	}

	/** Hai bước của "quên PIN". Lõi ở `VHCC_QuenPin`; đây chỉ đọc biểu mẫu và kể lại. */
	private static function viec_quen_pin() {
		$ra = array( 'loi' => '', 'ok' => '', 'the' => '', 'ten' => '' );
		$p  = function ( $k ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		};
		$viec = $p( 'qp_viec' );

		if ( 'tra' === $viec ) {
			$kq = VHCC_QuenPin::tra( $p( 'qp_ten' ), $p( 'qp_cccd' ) );
			if ( empty( $kq['ok'] ) ) { $ra['loi'] = $kq['error']; return $ra; }
			$ra['the'] = $kq['the'];
			$ra['ten'] = $kq['ho_ten'];
			return $ra;
		}
		if ( 'dat' === $viec ) {
			$kq = VHCC_QuenPin::dat( $p( 'qp_the' ), $p( 'qp_moi' ) );
			if ( empty( $kq['ok'] ) ) {
				$ra['loi'] = $kq['error'];
				/* ⚠️ THẺ CÒN HẠN THÌ GIỮ Ô LẠI. PIN trùng người khác hay sai khuôn là lỗi gõ,
				   không phải lỗi danh tính — bắt tra lại CCCD từ đầu chỉ vì gõ hụt một số là
				   thừa, và người ta sẽ chọn một PIN dễ đoán cho xong. */
				$the = VHCC_QuenPin::doc_the( $p( 'qp_the' ) );
				if ( '' !== $the ) {
					$hs = VHCC_NhanSu::ho_so( $the );
					$ra['the'] = $p( 'qp_the' );
					$ra['ten'] = $hs ? (string) $hs['ho_ten'] : $the;
				}
				return $ra;
			}
			$ra['ok'] = 'Đã đặt PIN mới cho ' . $kq['ho_ten'] . '. Đăng nhập bằng PIN mới ngay bên trên.';
			return $ra;
		}
		return $ra;
	}

	private static function trang_chinh( $toi, $bao ) {
		global $wpdb;
		$ky  = self::chu_ky( (string) $_COOKIE[ self::COOKIE ] );
		$la  = VHCC_Vai::duoc( $toi, 'he_thong' );   // khối hệ thống: nguồn người dùng, xoá sạch, khai Admin
		$bang = VHCC_DB::t( 'nhan_vien' );
		$tong = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );

		$GLOBALS['VHCC_FORM_ROI'] = '';

		echo self::dau( 'Quản trị Chấm Công' );
		$ds_man = self::man_cua( $toi );
		$man    = isset( $_GET['man'] ) ? sanitize_text_field( wp_unslash( $_GET['man'] ) ) : '';
		if ( 'vp' === $man )    { $man = 'cham'; }
		if ( 'luong' === $man ) { $man = 'cham'; }
		if ( ! isset( $ds_man[ $man ] ) ) { $man = self::man_mac_dinh( $ds_man ); }

		self::cot_doc( $man, $ds_man, $ky, $toi );
		echo '<div class="bo">';

		$bao = array_merge( self::lay_bao(), (array) $bao );
		foreach ( $bao as $b ) { self::ve_bao( $b ); }
		self::tieu_man( $man, $ds_man, $toi );
		self::canh_lech_vai( $toi );

		/* ------------------------------------------------------------------ chọn màn
		   Thanh màn dựng theo QUYỀN, không theo tên vai trò: mỗi người chỉ thấy những màn mình
		   mở được, nên không có chuyện bấm vào một mục rồi bị chối. Người chỉ có đúng một màn
		   thì không vẽ thanh — một cái thanh một mục chỉ tổ chiếm chỗ. */

		if ( 'nha' === $man ) {
			self::the_nha( $toi );
			self::dong_trang();
			return;
		}

		if ( 'cong_toi' === $man ) {
			self::the_cong_toi( $toi );
			/* Khối xin phép đứng NGAY DƯỚI bảng công của chính mình: thấy ngày nào mình vào trễ
			   thì xin phép ngay tại đó, không phải đi tìm màn khác. */
			self::the_xin_tre( $toi, $ky );
			self::dong_trang();
			return;
		}

		if ( 'cham' === $man ) {
			self::the_bang_cham( $ky, $toi );
			self::dong_trang();
			return;
		}

		if ( 'cau_hinh' === $man ) {
			self::the_man_cau_hinh( $ky, $toi );
			self::dong_trang();
			return;
		}

		if ( 'du_lieu' === $man ) {
			self::the_man_du_lieu( $ky, $toi );
			self::dong_trang();
			return;
		}

		if ( 'may' === $man ) {
			VHCC_WebMay::man( $ky, $toi );
			self::dong_trang();
			return;
		}

		if ( 'lich' === $man ) {
			VHCC_WebLich::man( $ky, $toi );
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
	/* ⚠️ MỌI màn khai được đều phải có tên ở đây — có phép thử canh chuyện đó. Hai màn khai
	   cấu hình / nạp dữ liệu đứng CUỐI: chúng là việc làm một lần, không ai muốn mở app ra là
	   rơi thẳng vào bảng khai cấu hình. Nhưng vẫn phải có tên, kẻo người CHỈ có hai màn ấy lại
	   rơi vào nhánh đoán mò ở cuối hàm. */
	/* 'may' đứng CUỐI, cùng lối với hai màn khai cấu hình: Admin mở app ra là để xem bảng công,
	   không phải để rơi thẳng vào màn có nút đẩy firmware cả chuỗi. Nhưng vẫn phải có tên. */
	const MAN_UU_TIEN = array( 'nha', 'ho_so', 'cham', 'cong_toi', 'cau_hinh', 'du_lieu', 'lich', 'may' );

	public static function man_mac_dinh( $ds_man ) {
		foreach ( self::MAN_UU_TIEN as $k ) {
			if ( isset( $ds_man[ $k ] ) ) { return $k; }
		}
		$khoa = array_keys( (array) $ds_man );
		return $khoa ? $khoa[0] : 'cong_toi';
	}

	/**
	 * HỒ SƠ GHI MỘT VAI, PHIÊN NÀY LẠI MANG VAI KHÁC — NÓI RA NGAY TRÊN MÀN NGƯỜI ẤY ĐANG ĐỨNG.
	 *
	 * =========================================================================================
	 * 🔴 ĐÂY LÀ LỖI IM LẶNG ĐÃ CẮN NHIỀU LẦN.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"cửa hàng trưởng sẽ xem được full tháng của nhân viên tại cửa hàng
	 * đang cùng cơ sở"*, rồi *"hiện tại chưa xem được"* — kèm ảnh chị Mỹ Tiên đăng nhập: hồ sơ
	 * ghi **Cửa hàng trưởng**, mà góc màn ghi **Nhân viên**, và cột dọc không có tab Bảng công.
	 *
	 * Luật thì đúng: `man_cua()` mở tab Bảng công cho ai có `cong_coso` (bậc Cửa hàng trưởng).
	 * Vai trong THẺ PHIÊN mới là thứ nó hỏi — mà thẻ ấy lấy vai từ NGUỒN NGƯỜI DÙNG đang đặt.
	 * Nguồn không phải `ho_so` thì đổi vai trong hồ sơ chẳng đi tới đâu: hồ sơ nói một đằng,
	 * cửa vào tính một nẻo, và KHÔNG CÓ GÌ BÁO.
	 *
	 * ⚠️ Màn Quản lý nhân sự đã có dải cảnh báo cho chuyện này (`VHCC_TrangNS::canh_nguon()`).
	 *    Nhưng người BỊ ẢNH HƯỞNG không đứng ở đó — họ đứng ở đây, nhìn một cột dọc thiếu mục và
	 *    không biết vì sao. Nói ở cả hai chỗ mới đủ.
	 *
	 * ⚠️ CHỈ KÊU KHI THẬT SỰ LỆCH BẬC. So tên vai thì "Kế toán" với "Kế toán cá nhân" thành lệch,
	 *    mà hai cái ấy cùng bậc — kêu lên là báo động giả, và báo động giả thì người ta tắt đi.
	 */
	private static function canh_lech_vai( $toi ) {
		$ma = trim( isset( $toi['ma_nv'] ) ? (string) $toi['ma_nv'] : '' );
		if ( '' === $ma ) { return; }
		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) { return; }

		$vai_hs = trim( (string) $hs['vai_tro'] );
		if ( '' === $vai_hs ) { return; }
		$bac_hs = VHCC_Vai::bac( array( 'role' => $vai_hs ) );
		$bac_ps = VHCC_Vai::bac( $toi );
		if ( $bac_hs === $bac_ps ) { return; }

		$ten_hs = VHCC_Vai::TEN[ VHCC_Vai::ma( $vai_hs ) ];
		$ten_ps = VHCC_Vai::ten( $toi );
		/* Cao hơn hay thấp hơn đều là lệch, nhưng hậu quả khác nhau — nói đúng cái đang xảy ra. */
		$thap = ( $bac_ps < $bac_hs );
		echo '<div class="bao ' . ( $thap ? 'loi' : 'canh' ) . '">'
			. '<b>Hồ sơ của anh/chị ghi vai ' . esc_html( $ten_hs ) . ', nhưng phiên này đang là '
			. esc_html( $ten_ps ) . '.</b><br>'
			. ( $thap
				? 'Nên màn hình đang thiếu những mục của vai ' . esc_html( $ten_hs ) . '. '
				: 'Nên màn hình đang mở rộng hơn vai ghi trong hồ sơ. ' )
			. '<span class="mo">Vai lúc đăng nhập đọc từ <b>Nguồn người dùng</b>, mà nguồn đang đặt '
			. 'không phải hồ sơ nhân sự. Nhờ Admin vào <b>Cấu hình → Nguồn người dùng</b> đổi sang '
			. '<b>“hồ sơ nhân sự”</b>, rồi đăng nhập lại.</span></div>';
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
		/* 🔴 KHAI CẤU HÌNH VÀ NẠP DỮ LIỆU TÁCH KHỎI MÀN BẢNG CÔNG.
		   Anh Thắng 26/08/2026: *"cái này cho qua cấu hình đi, vì chỗ này là bảng công mà hiện
		   phân quyền cơ sở làm gì trong này đâu"*, rồi *"Cho qua tab cấu hình luôn nhé"* (khối
		   Cách tính công), rồi *"Đẩy này qua tab dữ liệu đầu vào đi"* (khối nạp .csv).
		   Anh đúng, và lý do sâu hơn chuyện chật màn: bảng công là thứ người ta MỞ HẰNG NGÀY để
		   ĐỌC; khai cấu hình là việc làm MỘT LẦN rồi thôi, mà mỗi lần làm là đổi cách tính tiền
		   của cả cơ sở. Để hai loại việc ấy chung một màn thì thao tác hằng ngày cứ lướt ngang
		   qua mấy cái nút đổi tiền — sớm muộn có người bấm nhầm.
		   Vẫn dùng chung quyền `ngoai_coso` như trước, KHÔNG nới ra: dời chỗ không phải mở quyền. */
		/* 🔴 GIỜ & LƯƠNG KHÔNG CÒN LÀ MỘT TAB RIÊNG — nó nằm TRONG màn Bảng công.
		   Anh Thắng 27/08/2026: *"bảng công và giờ lương gộp lại thành 1 trang"*. Anh đúng, và
		   là lần thứ hai anh nói cùng một điều (lần trước là gộp Bảng chấm công với Bảng công
		   tháng): hai bảng nói về CÙNG một cơ sở, CÙNG một tháng, chỉ khác cách bày. Tách ra là
		   bắt người ta chọn cơ sở và tháng HAI LẦN — và chọn lệch một ô là hai bảng nói về hai
		   chỗ khác nhau mà không có gì báo.
		   Xem khối lương ở cuối `the_bang_cham()`. */
		if ( VHCC_Vai::duoc( $toi, 'ngoai_coso' ) ) { $ds['cau_hinh'] = 'Cấu hình'; }
		if ( VHCC_Vai::duoc( $toi, 'nap_cong' ) )   { $ds['du_lieu']  = 'Dữ liệu đầu vào'; }
		/* 🔴 MÁY & FIRMWARE LÀ BẬC ADMIN (`he_thong`), không nới.
		   Anh Thắng 27/08/2026: *"Máy & Firmware · Cổng nhận từ máy"* — hai màn wp-admin cuối
		   cùng ra web. Ở wp-admin cửa là `manage_options`, tức phải có tài khoản WordPress quản
		   trị; đưa ra web là bỏ mất lớp gác ấy, nên phải dựng lại bằng bậc vai. Trong đó có nút
		   đẩy firmware cho MỌI máy trong chuỗi — đẩy nhầm một bản là mất luôn đường sửa từ xa
		   của cả 26 cửa hàng và phải đi từng nơi cắm USB. */
		/* 🔴 TAB LỊCH HIỆN CHO MỌI NGƯỜI ĐĂNG NHẬP ĐƯỢC — không riêng người xếp lịch.
		   Nhân viên vào đây không để xếp lịch cho ai; họ hỏi "mai tôi làm ca nào", và màn này
		   trả lời được câu ấy mà không cần quyền gì thêm. Gác bằng `lich_lam` là đúng một nửa:
		   nửa kia (xem lịch của chính mình, xin đổi) bị khoá mất mà chẳng vì lý do nào. */
		if ( VHCC_Vai::duoc( $toi, 'cham_online' ) ) { $ds['lich']     = 'Lịch làm việc'; }
		if ( VHCC_Vai::duoc( $toi, 'may' ) )        { $ds['may']      = 'Máy & Firmware'; }
		if ( ! $ds ) { $ds['cong_toi'] = 'Công của tôi'; }
		return $ds;
	}

	/** Người này có được vào màn HỒ SƠ / TÀI KHOẢN không. Giữ tên cũ, hỏi bảng vai. */
	/**
	 * Biểu tượng của từng màn. Khai một chỗ để cột dọc và lưới thẻ ở Trang chính dùng CHUNG —
	 * hai nơi vẽ hai bộ icon khác nhau là cùng một mục trông như hai mục.
	 */
	const MAN_BIEU = array(
		'nha'      => '🏠', 'cong_toi' => '🕐', 'cham'    => '📋', 'ho_so' => '👤',
		'cau_hinh' => '⚙️', 'du_lieu'  => '🗂️', 'lich'    => '📅', 'may'   => '🖥️',
	);

	/** Một câu nói màn ấy để làm gì — hiện trên thẻ Truy cập nhanh và dưới tiêu đề màn. */
	const MAN_CHU = array(
		'nha'      => 'Đường vào mọi đầu việc anh/chị làm được',
		'cong_toi' => 'Tháng này mình đi làm bao nhiêu ngày, bao nhiêu giờ',
		'cham'     => 'Lưới cả tháng, giờ vào / giờ ra, sửa ngay tại ô',
		'ho_so'    => 'Khai người, cấp PIN, đặt vai trò và cơ sở',
		'cau_hinh' => 'Bộ phận, cách tính công, ghép bảng, tên cơ sở',
		'du_lieu'  => 'Nạp bảng công cũ từ .csv, xem trước rồi mới ghi',
		'lich'     => 'Xếp ca cho cửa hàng, duyệt xin đổi lịch',
		'may'      => 'Thiết bị, cổng nhận từ máy, nạp firmware',
	);

	public static function bieu_man( $k )  {
		return isset( self::MAN_BIEU[ $k ] ) ? self::MAN_BIEU[ $k ] : '▪';
	}
	public static function chu_man( $k ) {
		return isset( self::MAN_CHU[ $k ] ) ? self::MAN_CHU[ $k ] : '';
	}

	/**
	 * CỘT DỌC BÊN TRÁI — khung của cả màn quản trị.
	 *
	 * Anh Thắng 27/08/2026, kèm ba ảnh phần mềm HR V5.2: *"Chỗ phần giao diện và tính năng của
	 * trang chấm công thiết kế đẹp mắt y như này"*.
	 *
	 * 🔴 VÌ SAO BỎ THANH NÚT NGANG. Nó hỏng dần theo số màn, và hỏng im lặng: nay đã tám mục,
	 *    trên màn hẹp chúng xuống hai hàng, mục nào rơi hàng dưới thì mắt không quét tới. Người
	 *    dùng không báo "thiếu nút" — họ chỉ không bao giờ bấm vào nó. Cột dọc thì mỗi mục một
	 *    dòng, thứ tự cố định, và mục đang mở nổi hẳn lên.
	 *
	 * ⚠️ VẼ THEO QUYỀN, y như thanh cũ. Ai không mở được màn nào thì màn ấy KHÔNG có mặt — chứ
	 *    không hiện rồi chối. Hiện rồi chối là dạy người dùng rằng màn này hay nói dối.
	 */
	private static function cot_doc( $man, $ds, $ky, $toi ) {
		echo '<div class="ung"><aside class="canh">';
		/* Tên hệ vẫn là ĐƯỜNG VỀ TRANG CHÍNH — người ta bấm vào logo để về nhà ở mọi trang web
		   trên đời, và bỏ mất thói quen ấy là bắt họ đi tìm một mục trong danh sách. */
		echo '<a class="hieu canh-hieu" href="' . esc_url( self::url() ) . '">'
			. '<b>K&amp;H</b> Chấm công<span>Nhân sự · Chấm công · Lương</span></a>';

		echo '<nav class="canh-nav">';
		foreach ( $ds as $k => $ten ) {
			$url = add_query_arg( array( 'man' => $k ), self::url() );
			echo '<a class="' . ( $k === $man ? 'dang' : '' ) . '" href="' . esc_url( $url ) . '">'
				. '<span class="bt">' . self::bieu_man( $k ) . '</span>' . esc_html( $ten ) . '</a>';
		}
		/* Chấm công là TRANG KHÁC (cần camera, và phải nhẹ để mở bằng 3G ở cơ sở) nên là một
		   liên kết chứ không phải một màn. Vẫn để chung cột: với người dùng thì đó vẫn là
		   "một hệ thống, bấm qua lại được", đúng thứ anh Thắng hỏi. */
		echo '<a href="' . esc_url( VHCC_Tram::url() ) . '"><span class="bt">📷</span>Chấm công</a>';
		/* Quản lý nhân sự cũng là TRANG KHÁC. Chỉ vẽ cho người mở được nó — vẽ cho cả người
		   không vào được thì bấm vào chỉ nhận một câu chối, mà cái nút thì cứ nằm đó mời gọi.
		   ⚠️ Gác `method_exists` cùng hàm với lời gọi (`tools/test/kiem-goi-cheo.php`). */
		if ( class_exists( 'VHCC_TrangNS' ) && method_exists( 'VHCC_TrangNS', 'url' )
			&& method_exists( 'VHCC_TrangNS', 'toi' ) && VHCC_TrangNS::toi() ) {
			echo '<a href="' . esc_url( VHCC_TrangNS::url() ) . '"><span class="bt">👥</span>Quản lý nhân sự</a>';
		}
		echo '</nav>';

		echo '<div class="canh-duoi">';
		/* ⚠️ TÊN VÀ VAI ĐI LIỀN MỘT CHUỖI (`Tên · Vai`), không tách làm hai thẻ rời. Đó là cách
		   đầu trang cũ viết, và là thứ người ta quen liếc để biết mình đang vào bằng tài khoản
		   nào — nhất là mấy máy dùng chung ở cửa hàng. */
		echo '<div class="canh-ai"><b>' . esc_html( isset( $toi['name'] ) ? $toi['name'] : '' )
			. ' · ' . esc_html( VHCC_Vai::ten( $toi ) ) . '</b>'
			. ( ! empty( $toi['ma_nv'] ) ? '<span>Mã NV ' . esc_html( $toi['ma_nv'] ) . '</span>' : '' )
			. '</div>';
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<button name="viec" value="thoat">Thoát</button></form>';
		/* Phiên bản đang chạy — để đối chiếu khi vừa cài đè bản mới. Cùng con số với chân trang,
		   chỉ khác chỗ đứng: ở đây nó luôn trong tầm mắt, không phải cuộn xuống đáy. */
		if ( defined( 'VHCC_VERSION' ) ) {
			echo '<div class="canh-pb">Chấm công ' . esc_html( VHCC_VERSION ) . '</div>';
		}
		echo '</div></aside><main class="vung">';
		$GLOBALS['VHCC_CO_COT'] = true;
	}

	/**
	 * TIÊU ĐỀ CỦA MÀN ĐANG MỞ.
	 *
	 * 🔴 Không có nó thì tám màn mở ra trông giống hệt nhau — cùng nền, cùng thẻ trắng, cùng
	 *    bảng. Người ta bấm một mục ở cột dọc rồi không chắc mình đã sang màn khác chưa, nhất
	 *    là khi màn mới cũng mở đầu bằng một ô chọn cơ sở y như màn cũ.
	 */
	private static function tieu_man( $man, $ds, $toi ) {
		$ten = isset( $ds[ $man ] ) ? $ds[ $man ] : '';
		if ( '' === $ten ) { return; }
		$chu = self::chu_man( $man );
		echo '<div class="tieu-man"><h1>' . esc_html( $ten ) . '</h1>'
			. ( '' !== $chu ? '<p class="mo">' . esc_html( $chu ) . '</p>' : '' ) . '</div>';
	}


	/**
	 * Cơ sở người này được xem — ai có `cong_tat_ca` thấy hết, còn lại thấy cơ sở mình phụ trách.
	 *
	 * 🔴 TRƯỚC ĐÂY SO VAI BẰNG MỘT MẢNG CỨNG `['Admin','Quản lý']` — và nó SAI với Kế toán.
	 * Kế toán ở bậc 4, cao hơn Quản lý (bậc 3), có `cong_tat_ca`, và anh Thắng chốt kế toán
	 * *"full quyền ngoài admin"*. Nhưng vì tên "Kế toán" không có trong mảng ấy, họ rơi xuống
	 * nhánh dưới và chỉ thấy ĐÚNG MỘT cơ sở — cơ sở ghi trên thẻ phiên của họ.
	 *
	 * Hậu quả im lặng và đúng loại khó lần: ô chọn cơ sở của màn Bảng công chỉ có một dòng, mà
	 * một dòng thì `the_bang_cham()` TỰ CHỌN luôn — nên màn mở ra vẫn có số, vẫn trông bình
	 * thường, chỉ là kế toán không bao giờ xem được cơ sở khác và cũng không thấy có gì để bấm.
	 * Không lỗi, không cảnh báo, không ai đi báo.
	 *
	 * Đây đúng là loại lỗi mà cả `VHCC_Vai` sinh ra để dẹp: hỏi QUYỀN, đừng so TÊN VAI. Tìm ra
	 * khi gộp khối lương vào màn này — khối in ra tên cơ sở, và tên ấy không khớp ô chọn.
	 */
	private static function ds_coso_xem( $toi ) {
		if ( VHCC_Vai::duoc( $toi, 'cong_tat_ca' ) ) { return VHCC_NhanSu::ds_coso(); }
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
	/**
	 * XIN PHÉP ĐI TRỄ — chỗ nhân viên nộp đơn, và chỗ họ xem đơn mình đã nộp ra sao.
	 *
	 * Anh Thắng 27/08/2026: *"tại trang chấm công online nhân viên sẽ chọn Xin Phép đi trễ
	 * TRƯỚC KHI TỚI cửa hàng"*.
	 *
	 * 🔴 NGÀY MẶC ĐỊNH LÀ HÔM NAY, không phải ô trống. Người ta mở màn này lúc đang trên đường
	 *    tới cửa hàng — bắt gõ ngày là thêm một chỗ gõ sai, mà gõ sai ngày thì đơn xin phép cho
	 *    một hôm khác, và ô vàng của hôm nay vẫn nguyên.
	 *
	 * 🔴 KHÔNG CÓ Ô "MÃ NV". Mã lấy từ phiên đăng nhập (xem `VHCC_XinTre::nop`). Để người gửi
	 *    tự khai mã là ai cũng nộp được đơn đứng tên người khác.
	 */
	private static function the_xin_tre( $toi, $ky ) {
		$ma_nv = trim( isset( $toi['ma_nv'] ) ? (string) $toi['ma_nv'] : '' );
		if ( '' === $ma_nv ) { return; }
		if ( ! class_exists( 'VHCC_XinTre' ) || ! method_exists( 'VHCC_XinTre', 'cua_nguoi' ) ) { return; }

		$ds     = VHCC_XinTre::cua_nguoi( $ma_nv );
		$hom_nay = substr( (string) current_time( 'Y-m-d' ), 0, 10 );
		$cho_sl = 0;
		foreach ( $ds as $d ) {
			if ( VHCC_XinTre::CHO === (string) $d['trang_thai'] ) { $cho_sl++; }
		}

		echo '<div class="the" id="xintre"><details' . ( $cho_sl ? ' open' : '' ) . '>';
		echo '<summary><b>Xin phép đi trễ</b>'
			. ( $cho_sl ? ' — <b style="color:#b45309">' . $cho_sl . ' đơn đang chờ duyệt</b>' : '' )
			. ' <span class="mo">(bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Nộp <b>trước khi tới cửa hàng</b>. Cửa hàng '
			. 'trưởng duyệt thì cảnh báo đi trễ của ngày đó được bỏ — <b>số giờ công không đổi</b>, '
			. 'đơn không cộng bù giờ cho ai cả.</p>';

		echo '<form method="post" class="hang">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="xin_tre">';
		echo '<div><label for="xt_ngay">Ngày</label><input id="xt_ngay" name="xt_ngay" type="date"'
			. ' value="' . esc_attr( $hom_nay ) . '" required></div>';
		echo '<div><label for="xt_phut">Xin trễ (phút)</label><input id="xt_phut" name="xt_phut"'
			. ' type="number" min="1" max="' . VHCC_Tre::TOI_DA . '" value="15" required'
			. ' style="width:110px"></div>';
		echo '<div style="flex:1;min-width:220px"><label for="xt_ly_do">Lý do</label>'
			. '<input id="xt_ly_do" name="xt_ly_do" maxlength="250" required'
			. ' placeholder="VD: kẹt xe cầu Sài Gòn" style="width:100%"></div>';
		echo '<div><button class="chinh">Gửi đơn</button></div>';
		echo '</form>';

		if ( ! $ds ) {
			echo '<p class="mo" style="margin-top:10px">Chưa nộp đơn nào.</p>';
			echo '</details></div>';
			return;
		}
		echo '<div class="cuon" style="margin-top:12px"><table class="cc"><thead><tr>'
			. '<th>Ngày</th><th>Xin trễ</th><th>Lý do</th><th>Kết quả</th>'
			. '</tr></thead><tbody>';
		foreach ( $ds as $d ) {
			$tt = (string) $d['trang_thai'];
			echo '<tr><td><b>' . esc_html( self::ngay_vn( (string) $d['ngay'] ) ) . '</b></td>';
			echo '<td class="oc">' . (int) $d['so_phut'] . ' phút</td>';
			echo '<td>' . esc_html( (string) $d['ly_do'] ) . '</td>';
			$mau = ( VHCC_XinTre::DUYET === $tt ) ? 'ca2'
				: ( ( VHCC_XinTre::TU_CHOI === $tt ) ? 'ca4' : 'ca1' );
			echo '<td><span class="k ' . esc_attr( $mau ) . '">'
				. esc_html( isset( VHCC_XinTre::TEN_TT[ $tt ] ) ? VHCC_XinTre::TEN_TT[ $tt ] : $tt )
				. '</span>';
			if ( VHCC_XinTre::TU_CHOI === $tt && '' !== trim( (string) $d['ly_do_choi'] ) ) {
				echo '<div class="mo" style="font-size:11.5px">'
					. esc_html( (string) $d['ly_do_choi'] ) . '</div>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo" style="margin-top:8px">Mỗi ngày <b>một đơn</b>. Nộp lại cho cùng một '
			. 'ngày là <b>đè lên</b> đơn cũ và quay về chờ duyệt.</p>';
		echo '</details></div>';
	}

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
	/**
	 * KHỐI "THÊM NGƯỜI MỚI" — cho cửa hàng trưởng, ngay trên màn Bảng công.
	 *
	 * =========================================================================================
	 * 🔴 ĐẶT Ở ĐÂY VÌ ĐÂY LÀ MÀN HỌ MỞ HẰNG NGÀY.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"thêm phần cửa hàng trưởng bổ sung nhân sự cho cửa hàng mình"*.
	 * Cửa hàng trưởng không có tab Hồ sơ (đó là bậc Kế toán trở lên), nên khối này phải nằm
	 * trong một màn họ vào được — và Bảng công là màn duy nhất họ mở mỗi ngày.
	 *
	 * ⚠️ GẬP LẠI, KHÔNG BÀY SẴN. Thêm người là việc vài tháng một lần; bảng công là việc hằng
	 *    ngày. Bày sẵn một biểu mẫu tạo hồ sơ ngay giữa đường đọc hằng ngày là sớm muộn có
	 *    người điền nhầm vào đó.
	 */
	private static function khoi_them_nv( $ky, $toi, $cs, $ds_cs ) {
		if ( ! VHCC_Vai::duoc( $toi, 'them_nv' ) ) { return; }
		/* Người đã có tab Hồ sơ thì tạo hồ sơ ở đó — đầy đủ ô hơn, và cấp được mã CHUẨN. Bày
		   thêm một cửa hẹp bên cạnh một cửa rộng chỉ làm người ta phân vân chọn cửa nào. */
		if ( VHCC_Vai::duoc( $toi, 'ho_so' ) ) { return; }

		echo '<details class="gap" style="margin-top:12px"><summary><b>Thêm người mới vào cửa hàng'
			. '</b> — người vừa vào làm, chấm công được ngay</summary>';
		echo '<p class="mo">Hệ cấp một <b>mã tạm</b> (bắt đầu bằng <code>TAM-</code>) để người mới '
			. 'đi làm được ngay; Admin đổi sang mã chuẩn của công ty sau, công đã chấm vẫn theo '
			. 'sang mã mới.</p>';
		/* 🔴 NÓI RA ĐƯỜNG LẤY PIN NGAY TẠI ĐÂY. Không màn nào cấp PIN qua tay cửa hàng trưởng —
		   cố ý, để không ai phải cầm mã của người khác. Nhưng người bấm nút phải biết bước tiếp
		   theo là gì, không thì họ đứng chờ một mã PIN không bao giờ tới. */
		echo '<p class="mo">Xong rồi, bảo người mới vào trang <b>Chấm công</b> → bấm '
			. '<b>Quên PIN?</b> → gõ <b>họ tên + số căn cước</b> của chính mình để tự đặt mã PIN. '
			. 'Không ai phải đọc mã PIN của ai.</p>';

		/* ⚠️ `enctype` PHẢI CÓ, không thì ô ảnh gửi lên chỉ còn cái TÊN TỆP — biểu mẫu vẫn chạy,
		   vẫn báo "đã thêm", mà ảnh biến mất không dấu vết. */
		echo '<form method="post" class="hang" enctype="multipart/form-data" '
			. 'style="gap:8px;flex-wrap:wrap">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo '<input type="hidden" name="man" value="cham">';
		echo '<div><label for="tn_ten">Họ tên</label>'
			. '<input id="tn_ten" name="tn_ho_ten" required></div>';
		echo '<div><label for="tn_cc">Số căn cước</label>'
			. '<input id="tn_cccd" name="tn_cccd" required placeholder="12 số" style="width:150px"></div>';
		echo '<div><label for="tn_sdt">Điện thoại</label>'
			. '<input id="tn_sdt" name="tn_sdt" style="width:130px"></div>';
		echo '<div><label for="tn_gt">Giới tính</label><select id="tn_gt" name="tn_gioi_tinh">'
			. '<option value="">—</option><option value="male">Nam</option>'
			. '<option value="female">Nữ</option></select></div>';
		/* 🔴 CHỨC VỤ CHỌN SẴN TỪ SỔ, KHÔNG GÕ TAY TỰ DO.
		   Anh Thắng 28/08/2026: *"Thiếu chức vụ, lấy sẵn từ hệ thống (chức vụ lấy từ chức vụ của
		   cửa hàng trưởng đi xuống)"*. Gõ tay thì mỗi người một cách viết — "Thu ngân", "thu
		   ngan", "TN" — và bảng lương gom theo chức vụ ra ba nhóm cho cùng một việc.
		   ⚠️ `<datalist>` chứ không phải `<select>`: cửa hàng mới mở thì sổ chưa có chức vụ nào,
		      ô chọn rỗng mà không gõ được là bế tắc và người ta bỏ trống luôn. Đây là ô gõ CÓ
		      GỢI Ý — chọn cho nhanh, gõ mới cũng được. Và vẫn không cần một dòng script nào. */
		$cv_ds = VHCC_NhanSu::chuc_vu_cho( $toi, '' !== $cs ? $cs
			: ( isset( $ds_cs[0] ) ? (string) $ds_cs[0] : '' ) );
		echo '<div><label for="tn_cv">Chức vụ</label>'
			. '<input id="tn_cv" name="tn_chuc_vu" list="tn_cv_ds" style="width:150px"'
			. ( $cv_ds ? ' placeholder="chọn hoặc gõ"' : '' ) . '>';
		if ( $cv_ds ) {
			echo '<datalist id="tn_cv_ds">';
			foreach ( $cv_ds as $x ) { echo '<option value="' . esc_attr( $x ) . '">'; }
			echo '</datalist>';
		}
		echo '</div>';
		/* Phụ trách một cơ sở thì không phải chọn; nhiều cơ sở thì PHẢI chọn — đoán hộ là đoán
		   sai vào đúng cái cột quyết định công và lương chảy về cửa hàng nào. */
		if ( count( (array) $ds_cs ) > 1 ) {
			echo '<div><label for="tn_cs">Cơ sở</label><select id="tn_cs" name="tn_coso" required>';
			echo '<option value="">— chọn —</option>';
			foreach ( (array) $ds_cs as $x ) {
				echo '<option value="' . esc_attr( $x ) . '"' . selected( $x, $cs, false ) . '>'
					. esc_html( $x ) . '</option>';
			}
			echo '</select></div>';
		} else {
			$mot = isset( $ds_cs[0] ) ? (string) $ds_cs[0] : '';
			echo '<input type="hidden" name="tn_coso" value="' . esc_attr( $mot ) . '">';
			if ( '' !== $mot ) {
				echo '<div><label>Cơ sở</label><div style="padding-top:6px"><b>'
					. esc_html( $mot ) . '</b></div></div>';
			}
		}
		/* =====================================================================================
		 * 🔴 ẢNH THẺ — KHÔNG ÉP, NHƯNG PHẢI NÓI RÕ THIẾU THÌ MẤT GÌ.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026 xin thêm ảnh thẻ, để đẩy lên máy chấm công và làm mẫu đối chiếu
		 * khuôn mặt cho chấm công online — *"không ép buộc, nhưng không có là phải đưa ra cảnh
		 * báo bù sau. hiện ảnh thẻ mẫu cho cửa hàng trưởng biết"*.
		 *
		 * ⚠️ MẪU VẼ THẲNG BẰNG SVG, không nhúng ảnh một người thật. Ảnh mẫu là ảnh MẶT — dùng
		 *    mặt của một nhân viên nào đó làm mẫu cho cả chuỗi là chuyện không xin phép được.
		 */
		echo '<div style="flex:0 0 100%;display:flex;gap:14px;align-items:flex-start;'
			. 'margin-top:6px;padding-top:10px;border-top:1px dashed var(--vien)">';
		echo self::anh_the_mau();
		echo '<div><label for="tn_anh"><b>Ảnh thẻ</b> <span class="mo">— không bắt buộc</span></label>'
			. '<input id="tn_anh" name="tn_anh" type="file" accept="image/*" capture="user">'
			. '<p class="mo" style="margin:6px 0 0;max-width:520px">Có ảnh thì <b>máy chấm công '
			. 'tự nhận khuôn mặt</b>, không phải gọi người ra máy chụp lại. '
			. 'Chụp như <b>ảnh thẻ bên cạnh</b>: chính diện, ngang vai, <b>nền trơn một màu</b> '
			. '(xanh hoặc trắng), thấy rõ cả khuôn mặt, tóc vén gọn, <b>không</b> kính râm, '
			. '<b>không</b> khẩu trang, <b>không</b> đội mũ. Ảnh to cũng được — hệ tự thu nhỏ.<br>'
			. '<b>Đừng</b> dùng ảnh chụp nghiêng, ảnh selfie góc cao, hay ảnh cắt từ ảnh tập thể: '
			. 'máy chấm công lấy khuôn mặt từ tấm này, lấy nhầm là người ấy quẹt mãi không nhận.<br>'
			. 'Bỏ trống vẫn thêm người được, nhưng tên người ấy sẽ nằm trong khối '
			. '<b>Chưa có ảnh thẻ</b> ngay dưới cho tới khi bù.</p></div>';
		echo '</div>';

		echo '<div><button class="chinh" name="viec" value="them_nv">Thêm người</button></div>';
		echo '</form>';
		echo '</details>';
	}

	/**
	 * ẢNH THẺ MẪU — vẽ bằng SVG, không nhúng mặt người thật.
	 *
	 * Anh Thắng 28/08/2026: *"hiện ảnh thẻ mẫu cho cửa hàng trưởng biết"*. Một câu chữ "chụp
	 * chính diện" thì ai cũng gật, rồi vẫn gửi lên ảnh chụp nghiêng trong quán cà phê. Hình vẽ
	 * nói được cái mà câu chữ nói không xong: khuôn mặt chiếm bao nhiêu phần khung, cắt tới đâu.
	 */
	private static function anh_the_mau() {
		/* Vẽ theo đúng tấm ảnh anh Thắng gửi 28/08/2026: nền XANH, chính diện, ngang vai, áo sơ
		   mi trắng, tóc buộc gọn. Nền xanh là chi tiết đáng vẽ nhất — nó nói ngay "ảnh thẻ chụp
		   ở tiệm", chứ không phải ảnh cắt từ điện thoại. */
		$sv = '<svg width="118" height="150" viewBox="0 0 118 150" role="img" '
			. 'aria-label="Ảnh thẻ mẫu: nền xanh, chụp chính diện, ngang vai, áo sơ mi" '
			. 'style="border:1px solid var(--vien);border-radius:6px;flex:0 0 auto">'
			. '<rect x="0" y="0" width="118" height="150" fill="#1f6fc4"/>'
			/* Vai + áo sơ mi trắng, cắt ngang ngực. */
			. '<path d="M16 150 C16 120 34 108 59 108 C84 108 102 120 102 150 Z" fill="#f1f5f9"/>'
			. '<path d="M49 108 L59 124 L69 108 Z" fill="#1f6fc4" opacity=".35"/>'
			/* Cổ. */
			. '<rect x="50" y="92" width="18" height="20" rx="7" fill="#e8c9ad"/>'
			/* Tóc buộc gọn: khối tóc ôm đầu, không xoã ra ngoài khung. */
			. '<path d="M28 66 C28 36 42 24 59 24 C76 24 90 36 90 66 L90 80 '
			. 'C86 74 86 52 82 47 C73 57 45 57 36 47 C32 52 32 74 28 80 Z" fill="#2f2a28"/>'
			/* Khuôn mặt. */
			. '<ellipse cx="59" cy="64" rx="25" ry="29" fill="#f0d3ba"/>'
			. '<ellipse cx="49" cy="61" rx="3" ry="3.4" fill="#3b2f2a"/>'
			. '<ellipse cx="69" cy="61" rx="3" ry="3.4" fill="#3b2f2a"/>'
			. '<path d="M44 54 Q49 51 54 54" stroke="#3b2f2a" stroke-width="1.8" fill="none" '
			. 'stroke-linecap="round"/>'
			. '<path d="M64 54 Q69 51 74 54" stroke="#3b2f2a" stroke-width="1.8" fill="none" '
			. 'stroke-linecap="round"/>'
			. '<path d="M59 66 L59 73" stroke="#d9ab8a" stroke-width="1.6" stroke-linecap="round"/>'
			. '<path d="M52 80 Q59 84 66 80" stroke="#c4756b" stroke-width="2.4" fill="none" '
			. 'stroke-linecap="round"/>'
			/* Khung ngắm: mặt nằm gọn trong khung, chừa lề trên và hai bên. */
			. '<rect x="21" y="18" width="76" height="96" fill="none" stroke="#ffffff" '
			. 'stroke-width="1.4" stroke-dasharray="5 4" opacity=".85"/>'
			. '</svg>';
		return '<div style="text-align:center;flex:0 0 auto">' . $sv
			. '<div class="mo" style="font-size:11px;margin-top:3px">Ảnh thẻ mẫu</div></div>';
	}

	/**
	 * AI Ở CƠ SỞ NÀY CHƯA CÓ ẢNH THẺ — cảnh báo để bù sau.
	 *
	 * Anh Thắng chốt ảnh thẻ *"không ép buộc, nhưng không có là phải đưa ra cảnh báo bù sau"*.
	 * Không ép là đúng: bắt buộc thì người ta gửi bừa một tấm cho qua cửa, và tấm ấy còn tệ hơn
	 * không có — máy nhận nhầm mặt thì lượt chấm công của người khác mang tên mình.
	 */
	private static function khoi_thieu_anh( $toi, $cs ) {
		if ( '' === $cs ) { return; }
		if ( ! VHCC_Vai::duoc( $toi, 'them_nv' ) && ! VHCC_Vai::duoc( $toi, 'ho_so' ) ) { return; }
		/* 🔴 CHỐT CƠ SỞ, VÀ ĐÂY LÀ CHỖ SUÝT RÒ. `$cs` đến thẳng từ thanh địa chỉ; bảng công bên
		   dưới có chối cơ sở lạ, nhưng khối này vẽ TRƯỚC chỗ chối ấy — nên gõ tay `ccs` của một
		   cửa hàng khác là đọc được HỌ TÊN + MÃ NV cả cơ sở đó, dù màn chính vẫn báo "Không có
		   quyền cơ sở này". */
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }
		$ds = VHCC_NhanSu::thieu_anh_the( $cs );
		if ( ! $ds ) { return; }
		echo '<div class="bao canh" style="margin-top:10px"><b>' . count( $ds )
			. ' người ở cơ sở này chưa có ảnh thẻ.</b> '
			. '<span class="mo">Chưa có ảnh thì khuôn mặt phải lấy trực tiếp tại máy chấm công, '
			. 'và chấm công online không có gì để đối chiếu. Bù dần cũng được — mở hồ sơ từng '
			. 'người rồi tải ảnh lên.</span><br><span class="mo">';
		$ten = array();
		foreach ( $ds as $x ) { $ten[] = $x['ho_ten'] . ' (' . $x['ma_nv'] . ')'; }
		echo esc_html( implode( ' · ', $ten ) ) . '</span></div>';
	}

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

		self::khoi_them_nv( $ky, $toi, $cs, $ds_cs );

		/* 🔴 CỬA HÀNG TRƯỞNG QUẢN NHIỀU GIAN HÀNG: HIỆN HẾT, KHỎI BẮT CHỌN TỪNG CÁI.
		   Anh Thắng 29/08/2026: *"nếu nhân viên làm cửa hàng trưởng 2 gian hàng thì sẽ hiện 2
		   bảng công của 2 cửa hàng mình quản lý"* — cơ sở phụ đã đẩy đúng cả hai cơ sở vào
		   `ds_coso_xem()` (xem đó), nhưng màn này vẫn bắt chọn MỘT qua ô lọc rồi mới vẽ bảng,
		   nên cơ sở phụ có tới cũng như không: không ai đi bấm chọn cái mình còn chưa biết là có.

		   ⚠️ CHỈ áp dụng khi KHÔNG có `cong_tat_ca` (Admin/Quản lý/Kế toán) VÀ số cơ sở đủ NHỎ
		      (≤3 — khớp đúng mốc "chọn được 2–3 cơ sở" của bản 3.2.0 bên màn Sửa đủ). Những vai
		      quản hàng chục cơ sở dựng hết một lượt là một trang không ai cuộn nổi; họ vẫn chọn
		      từng cơ sở qua ô lọc như cũ — đây không phải nới quyền, chỉ đổi cách trình bày cho
		      đúng một CHT một-vài-cửa-hàng.
		   ⚠️ CHỈ tự hiện hết khi CHƯA CHỌN GÌ (`$cs` rỗng). Bấm chọn đúng MỘT cơ sở ở ô lọc (để
		      lọc theo Nhân viên/Ngày cho gọn, hay để mở khối In/Lương của riêng cơ sở đó) vẫn
		      phải ra đúng MỘT bảng — không thì ô lọc trên kia trở thành vô tác dụng. */
		$hien_het = ( '' === $cs && $ds_cs && count( $ds_cs ) <= 3
			&& ! VHCC_Vai::duoc( $toi, 'cong_tat_ca' ) );

		if ( '' === $cs && ! $hien_het ) {
			self::khoi_thieu_anh( $toi, $cs );
			echo '<p class="mo" style="margin-top:12px">'
				. ( $ds_cs ? 'Chọn một cơ sở rồi bấm Xem.'
					: ( '' !== $bp
						? 'Không có cơ sở nào thuộc bộ phận này trong phạm vi của anh/chị.'
						: 'Tài khoản này chưa được gán cơ sở nào — nhờ Admin khai ô "Cửa hàng phụ trách".' ) )
				. '</p></div>';
			/* 🔴 CHƯA CHỌN CƠ SỞ THÌ CHỈ ĐƯỜNG SANG CHỖ NẠP, ĐỪNG ĐỂ NGƯỜI TA ĐỨNG TRƯỚC MÀN TRỐNG.
			   Anh Thắng từng vấp đúng chỗ này: *"không thấy chỗ nạp dữ liệu công"* — bản đầu đặt
			   khối nạp ở CUỐI màn, sau bảng, mà bảng chỉ vẽ khi đã chọn cơ sở. Tức là đúng lúc
			   bảng công còn TRỐNG, lúc người ta cần nạp nhất, thì cái nút nạp là thứ duy nhất
			   không hiện.
			   Nay khối nạp ở tab riêng (anh Thắng: *"Đẩy này qua tab dữ liệu đầu vào đi"*), nên ở
			   đây không vẽ lại nó nữa — nhưng PHẢI có một câu chỉ đường, kẻo lại rơi vào đúng cái
			   bẫy cũ dưới một hình dạng khác. */
			echo '<p class="mo" style="margin-top:8px">Chưa có dữ liệu công? Nạp từ .csv ở tab '
				. '<a href="' . esc_url( add_query_arg( array( 'man' => 'du_lieu' ), self::url() ) )
				. '"><b>Dữ liệu đầu vào</b></a>.</p>';
			return;
		}
		echo '</div>';

		/* $hien_het thì vẽ HẾT các cơ sở của người này; ngược lại (đã chọn ở ô lọc) chỉ vẽ đúng
		   một cơ sở — mảng một phần tử để dùng chung một vòng lặp, không tách hai nhánh mã. */
		$ds_ve = $hien_het ? $ds_cs : array( $cs );
		foreach ( $ds_ve as $mot_cs ) {
			/* Tên cơ sở làm tiêu đề CHỈ khi có hơn một bảng — một bảng thì tiêu đề "Chấm công"
			   ở trên đã đủ nói, thêm một dòng tên cơ sở nữa là lặp lại vô ích. */
			if ( count( $ds_ve ) > 1 ) {
				echo '<h3 style="margin:22px 0 4px">🏬 ' . esc_html( $mot_cs ) . '</h3>';
			}
			self::khoi_thieu_anh( $toi, $mot_cs );
			$b = VHCC_Cham::bang_cham_cong( $toi, $mot_cs, $th );
			if ( empty( $b['ok'] ) ) {
				echo '<div class="bao loi">' . esc_html( $b['error'] ) . '</div>';
				continue;
			}
			self::ve_bang_cham( $b, $mot_cs, $th, $ngay, $ma_nv, $ky, $toi );

			/* 🔴 GIỜ & LƯƠNG NẰM NGAY DƯỚI, CÙNG CƠ SỞ CÙNG THÁNG. Anh Thắng 27/08/2026: *"bảng
			   công và giờ lương gộp lại thành 1 trang"*.
			   ⚠️ Đặt SAU `ve_bang_cham` chứ không phải trước: người ta mở màn này ra để xem BẢNG
			      CÔNG, còn lương là thứ soi sau. Đảo lại là mỗi lần mở phải cuộn qua bảng tiền mới
			      tới thứ mình cần.
			   ⚠️ Và chỉ ở nhánh bảng công VẼ ĐƯỢC — bảng công lỗi mà vẫn in bảng tiền ra thì đó là
			      tiền tính từ một tháng không đọc nổi. */
			self::the_khoi_in( $toi, $mot_cs, $th );
			self::the_khoi_luong( $toi, $mot_cs, $th );
		}
	}

	/**
	 * KHỐI IN — mở tờ giấy A4 của đúng cơ sở và tháng đang xem.
	 *
	 * ⚠️ KHÔNG ĐẺ Ô CHỌN CƠ SỞ RIÊNG, cùng luật với khối lương: cơ sở lấy thẳng từ màn Bảng
	 *    công. Chỉ có hai ô NGÀY, vì tờ in tính theo khoảng ngày chứ không theo tháng — mặc
	 *    định là đầu và cuối tháng đang xem, nên phần lớn lượt chỉ việc bấm.
	 *
	 * ⚠️ `target="_blank"`: tờ in là một trang HTML riêng khổ A4. Mở đè lên màn Bảng công thì
	 *    in xong phải bấm Back và chọn lại cơ sở + tháng — đúng cái phiền mà việc gộp trang vừa
	 *    dẹp xong.
	 */
	private static function the_khoi_in( $toi, $cs, $th ) {
		if ( '' === $cs ) { return; }
		if ( ! VHCC_Vai::duoc( $toi, 'cong_coso' ) ) { return; }
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }

		/* Đầu và cuối tháng đang xem. `t` của `date('t')` = số ngày trong tháng ấy — đừng gõ 31,
		   tháng 2 sẽ ra một ngày không tồn tại và câu SQL trả rỗng. */
		$tu  = $th . '-01';
		$den = $th . '-' . gmdate( 't', (int) strtotime( $tu . ' 00:00:00' ) );

		echo '<div class="the"><details>';
		echo '<summary><b>In bảng chấm công</b> — tờ A4 của ' . esc_html( $cs ) . '</summary>';
		echo '<p class="mo">Chọn khoảng ngày rồi bấm <b>Mở tờ in</b> — tờ giấy mở ở thẻ mới, '
			. 'Ctrl+P là in hoặc lưu thành PDF. Số trên tờ giấy do <b>máy chủ</b> tính từ cơ sở '
			. 'dữ liệu, không phải do trình duyệt.</p>';
		echo '<form method="get" target="_blank" class="hang" style="margin:0">';
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<input type="hidden" name="vhcc_qt" value="1">';
		}
		echo '<input type="hidden" name="to_in" value="1">';
		echo '<input type="hidden" name="ics" value="' . esc_attr( $cs ) . '">';
		echo '<div><label>Từ ngày</label><input type="date" name="itu" value="'
			. esc_attr( $tu ) . '" required></div>';
		echo '<div><label>Đến ngày</label><input type="date" name="iden" value="'
			. esc_attr( $den ) . '" required></div>';
		echo '<button class="chinh">🖨 Mở tờ in</button>';
		echo '</form>';
		echo '</details></div>';
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
		/* Mấy khối KHAI CẤU HÌNH đã dời sang tab riêng — xem `man_cua()`. Ở đây chỉ để lại
		   một dòng chỉ đường, kẻo người quen tay tìm mãi không thấy rồi tưởng mất tính năng. */
		echo '<p class="mo" style="margin:-6px 0 12px">⚙️ Khai <b>bộ phận của cơ sở</b>, '
			. '<b>ca làm việc</b>, <b>cách tính công</b> và <b>công thức tính công</b> nay ở tab '
			. '<a href="' . esc_url( add_query_arg( array( 'man' => 'cau_hinh' ), self::url() ) )
			. '"><b>Cấu hình</b></a>. Nạp công từ .csv ở tab '
			. '<a href="' . esc_url( add_query_arg( array( 'man' => 'du_lieu' ), self::url() ) )
			. '"><b>Dữ liệu đầu vào</b></a>.</p>';

		/* 🔴 LƯỚI ĐỨNG TRƯỚC BẢNG TỔNG. Anh Thắng 27/08/2026: *"cho bảng này lên trên"*.
		   Trước đây lưới là tab riêng ("Bảng công tháng"), rồi được kéo về cùng màn nhưng xếp
		   DƯỚI bảng tổng. Nhầm thứ tự: bảng tổng trả lời "cả tháng được mấy công" — một con số
		   người ta đọc lúc chốt lương, mỗi tháng một lần; còn lưới trả lời "ngày nào ai đi làm,
		   ô nào sai" — thứ mở màn này ra là để soi, ngày nào cũng soi. Thứ dùng nhiều nằm dưới
		   thì mỗi lượt phải cuộn qua thứ dùng ít.
		   Cả hai vẫn CÙNG MỘT MÀN, cùng một ô chọn cơ sở và tháng — chọn hai lần là có ngày hai
		   bảng nói về hai chỗ khác nhau. */
		self::the_luoi_thang( $cs, $th, $ky, $toi );

		self::the_tong_cham( $loc_thang, $tt, $cs, $th );

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

		self::the_nhat_ky_gio( $cs, $tt, $ky, $toi );
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

		/* Danh sách việc. Mỗi việc: quyền cần có · biểu tượng · tên · một câu "để làm gì" · đường tới.
		   ⚠️ BIỂU TƯỢNG RA KHỎI TÊN. Trước đây tên là "📷 Chấm công" — hình dán liền chữ nên nó
		      trôi theo chữ khi tên xuống dòng, và không xếp thẳng hàng với các thẻ khác. Nay hình
		      nằm trong vòng tròn riêng, tên là tên. */
		$viec = array();
		$viec[] = array( 'q' => 'cham_online', 'bt' => '📷', 'ten' => 'Chấm công',
			'chu' => 'Bấm giờ vào / giờ ra bằng điện thoại, có ảnh và vị trí.',
			'url' => VHCC_Tram::url(), 'chinh' => true );
		$viec[] = array( 'q' => 'cong_minh', 'bt' => self::bieu_man( 'cong_toi' ), 'ten' => 'Công của tôi',
			'chu' => 'Xem tháng này mình đi làm bao nhiêu ngày, bao nhiêu giờ.',
			'url' => add_query_arg( array( 'man' => 'cong_toi' ), self::url() ) );
		$viec[] = array( 'q' => 'cong_coso', 'bt' => self::bieu_man( 'cham' ), 'ten' => 'Bảng công',
			'chu' => 'Lưới cả tháng và từng lượt chấm. Bấm thẳng vào ô để bù hoặc sửa giờ.',
			'url' => add_query_arg( array( 'man' => 'cham', 'cth' => $th_nay ), self::url() ) );
		$viec[] = array( 'q' => 'cham_bu', 'bt' => '✏️', 'ten' => 'Chấm công bù',
			'chu' => 'Máy hỏng hoặc nhân viên quên bấm thì bù vào — có ghi lại ai bù, vì sao.',
			'url' => add_query_arg( array( 'man' => 'cham', 'cth' => $th_nay ), self::url() ) . '#bucong' );
		$viec[] = array( 'q' => 'cham_online', 'bt' => self::bieu_man( 'lich' ), 'ten' => 'Lịch làm việc',
			'chu' => 'Xem ca của mình, xin đổi lịch. Cửa hàng trưởng thì xếp ca cho cả cửa hàng.',
			'url' => add_query_arg( array( 'man' => 'lich' ), self::url() ) );
		$viec[] = array( 'q' => 'nap_cong', 'bt' => self::bieu_man( 'du_lieu' ), 'ten' => 'Dữ liệu đầu vào',
			'chu' => 'Đưa bảng công cũ từ Google Sheets vào. Có nút Xem trước, chưa ghi gì.',
			'url' => add_query_arg( array( 'man' => 'du_lieu' ), self::url() ) );
		$viec[] = array( 'q' => 'ngoai_coso', 'bt' => self::bieu_man( 'cau_hinh' ), 'ten' => 'Cấu hình',
			'chu' => 'Bộ phận, cách tính công, ghép bảng công, tên đầy đủ của cơ sở.',
			'url' => add_query_arg( array( 'man' => 'cau_hinh' ), self::url() ) );
		$viec[] = array( 'q' => 'ho_so', 'bt' => self::bieu_man( 'ho_so' ), 'ten' => 'Hồ sơ & tài khoản',
			'chu' => 'Khai người, cấp PIN, đặt vai trò và cơ sở phụ trách.',
			'url' => add_query_arg( array( 'man' => 'ho_so' ), self::url() ) );
		$viec[] = array( 'q' => 'may', 'bt' => self::bieu_man( 'may' ), 'ten' => 'Máy & Firmware',
			'chu' => 'Thiết bị ở cửa hàng, cổng nhận từ máy, nạp firmware từ xa.',
			'url' => add_query_arg( array( 'man' => 'may' ), self::url() ) );

		echo '<div class="the"><h3 style="margin:0 0 10px">Việc anh/chị làm được</h3>';
		echo '<div class="the-viec">';
		foreach ( $viec as $v ) {
			if ( ! VHCC_Vai::duoc( $toi, $v['q'] ) ) { continue; }
			echo '<a class="viec' . ( empty( $v['chinh'] ) ? '' : ' viec-chinh' ) . '" href="'
				. esc_url( $v['url'] ) . '">';
			echo '<span class="bt">' . ( isset( $v['bt'] ) ? $v['bt'] : '▪' ) . '</span>';
			echo '<b>' . esc_html( $v['ten'] ) . '</b>';
			echo '<span>' . esc_html( $v['chu'] ) . '</span>';
			echo '<span class="mo-cn">Mở chức năng →</span>';
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
		/* 🔴 VẬN HÀNH CHI PHÍ — anh Thắng 28/08/2026: *"https://khmatrix.com/chi-phi/ liên kết
		   trang vận hành chi phí vào nhé"*. Đây mới là cái LIÊN KẾT; phần đăng nhập một lần
		   (SSO) sang bên ấy là việc riêng, chưa làm — nên nói thẳng trong dòng mô tả rằng bên
		   ấy đăng nhập riêng, kẻo người bấm sang tưởng hệ hỏng khi bị hỏi mật khẩu. */
		if ( $co( 'VHCP_App', 'app_url' ) ) {
			$ds[] = array( 'ten' => '💰 Vận hành chi phí', 'url' => VHCP_App::app_url(),
				'chu' => 'Đề nghị chi, duyệt chi, quyết toán theo cơ sở. Hiện đăng nhập riêng — '
					. 'chưa dùng chung mã PIN với trang này.' );
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
			/* Anh Thắng 28/08/2026: *"Thêm xuất dạng ảnh ra ( kèm thêm giờ vào ra nữa nhé )"*.
			   Một tấm ảnh gửi đi được ngay — không cần người nhận có Excel, không cần họ có tài
			   khoản vào trang. Và mỗi ô có sẵn giờ vào–giờ ra nên cầm ảnh là đối chiếu được. */
			echo '<p style="margin:8px 0 0"><a class="nut" href="'
				. esc_url( add_query_arg( array( 'xuat' => 'anh', 'ccs' => $cs, 'cth' => $th ), self::url() ) )
				. '">🖼 Xuất ảnh (.svg)</a> <span class="mo">— cả tháng trên một tấm, mỗi ô có '
				. '<b>giờ vào–giờ ra</b> ngay dưới số giờ công. Mở bằng trình duyệt hoặc kéo thẳng '
				. 'vào Word / Zalo; muốn ra .png thì mở lên rồi chuột phải → lưu ảnh.</span></p>';
		}
		/* 🔴 BẢNG GHÉP PHẢI NÓI RA NÓ ĐANG GỒM NHỮNG GÌ. Một bảng lặng lẽ cộng thêm công của một
		   mã cơ sở khác là con số đúng mà không ai kiểm được — người đọc cộng tay lại theo mã
		   chính thì ra thiếu, và không hiểu vì sao. */
		$cs_phu = method_exists( 'VHCC_Luong', 'phu_cua' ) ? VHCC_Luong::phu_cua( $cs ) : array();
		if ( $cs_phu ) {
			echo '<p class="mo">🔗 Bảng này <b>đã gồm</b> công của ' . count( $cs_phu ) . ' cơ sở ghép '
				. 'vào: <b>' . esc_html( implode( ' · ', $cs_phu ) ) . '</b>. Ô nào đến từ đó có nhãn '
				. 'nhỏ ngay trong ô. Đổi ở khối <b>Ghép bảng công của hai cơ sở</b> tại tab Cấu hình.</p>';
		}
		$cs_ghep = method_exists( 'VHCC_Luong', 'ghep_vao' ) ? VHCC_Luong::ghep_vao( $cs ) : '';
		if ( '' !== $cs_ghep ) {
			/* Mở thẳng cơ sở PHỤ: vẫn xem được (để soi), nhưng phải nói ngay rằng con số ở đây
			   ĐÃ nằm trong bảng kia — không thì có người lấy cả hai bảng rồi cộng lại. */
			echo '<div class="bao canh" style="margin:10px 0 0">🔗 Cơ sở này đang <b>ghép vào bảng '
				. 'công của ' . esc_html( $cs_ghep ) . '</b>. Số ở đây <b>đã được tính vào bảng kia</b> '
				. '— đừng cộng hai bảng lại. <a href="'
				. esc_url( add_query_arg( array( 'man' => 'cham', 'ccs' => $cs_ghep, 'cth' => $th ), self::url() ) )
				. '">Mở bảng ' . esc_html( $cs_ghep ) . '</a></div>';
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
		self::the_tong_ca( $b_gio, $cs, $toi );
		/* 🔴 KHỐI KHAI CA PHẢI ĐỨNG NGAY ĐÂY, KHÔNG PHẢI CHỈ Ở MÀN CẤU HÌNH.
		   Nó vốn nằm một mình trong màn Cấu hình, mà màn ấy gác `ngoai_coso` — bậc Quản lý. Tức
		   là Cửa hàng trưởng, đúng người biết cửa hàng mình vào ca mấy giờ, KHÔNG có cửa nào để
		   khai. Kết quả là mọi cửa hàng cứ chạy bằng khung ca mặc định và không ai sửa được.
		   Anh Thắng 27/08/2026 đã giao thẳng: *"Cho qua bên Tài khoản cửa hàng trưởng tự set giờ
		   ca vào ra của cửa hàng"*.
		   Đặt cạnh chính cái bảng bị sai vì nó: thấy số lệch → cuộn xuống một khối là sửa được.
		   Chốt bên trong khối là `lich_lam` + `co_quyen_coso`, nên Quản lý vẫn khai như cũ và
		   Cửa hàng trưởng chỉ khai được cửa hàng mình — thêm chỗ vẽ, không nới quyền. */
		if ( 'cong' !== VHCC_Luong::cach_tinh( $cs ) ) {
			self::the_khai_ca( $cs, $ky, $toi );
			self::the_lenh_tre( $cs, $ky, $toi );
		}
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
	/**
	 * GHÉP BẢNG CÔNG CỦA HAI CƠ SỞ.
	 *
	 * Anh Thắng 27/08/2026: *"muốn ghép 2 bảng công lại thì như nào, vì VP_HCM có công SETUP
	 * (tức công đêm đó em), hay giờ add vào khó thì anh sửa tay"*.
	 *
	 * Sửa tay được, nhưng sửa tay MỖI THÁNG cho cả bảng lương là chỗ sinh lỗi đắt nhất — và cái
	 * sai ấy không ai soi lại được, vì con số cuối cùng nằm trong đầu người cộng.
	 *
	 * 🔴 KHÁC HẲN KHỐI "CƠ SỞ KHÁC" TRONG LƯỚI. Cơ sở khác là nơi làm việc THẬT SỰ khác — bày ra
	 *    để nhìn, KHÔNG cộng vào, vì lương tính theo cơ sở. Cơ sở GHÉP là một phần của chính
	 *    bảng này (ca đêm của cùng người, cùng bảng lương) nên phải CỘNG VÀO. Màn phải nói rõ
	 *    chỗ khác nhau ấy, kẻo người khai chọn nhầm và trả sai tiền theo cả hai hướng.
	 */
	private static function the_ghep_cs( $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'ngoai_coso' ) ) { return; }
		$ds = self::ds_coso_xem( $toi );
		if ( count( $ds ) < 2 ) { return; }   // một cơ sở thì không có gì để ghép
		$ban_do = VHCC_Luong::ban_do_ghep();

		echo '<div class="the" id="ghepcs"><details' . ( $ban_do ? ' open' : '' ) . '>';
		echo '<summary><b>Ghép bảng công của hai cơ sở</b> <span class="mo">('
			. count( $ban_do ) . ' cơ sở đang ghép · bấm để mở)</span></summary>';
		echo '<p class="mo" style="margin:10px 0">Dùng khi một cơ sở thật ra là <b>một phần của cơ '
			. 'sở khác</b> — ca đêm được chấm vào mã riêng chẳng hạn (<code>SETUP_VP</code> là ca '
			. 'đêm của <code>VP_HCM</code>). Khai xong thì bảng công của cơ sở chính <b>gồm luôn</b> '
			. 'công của cơ sở phụ, không phải mở hai bảng rồi cộng tay.</p>';
		echo '<div class="bao canh"><b>Chỉ ghép khi ĐÚNG LÀ MỘT BẢNG LƯƠNG.</b> Người làm ở hai '
			. 'cơ sở <b>khác nhau</b> thì đừng ghép — lương tính theo cơ sở (đơn giá, khung ca, cách '
			. 'tính công đều riêng), và lưới đã có sẵn <b>dòng xám</b> bày ra những ngày ấy mà không '
			. 'cộng vào. Ghép nhầm là cộng công của bảng người khác vào bảng này.</div>';
		echo '<p class="mo">Cơ sở phụ vẫn mở riêng được để soi; màn của nó sẽ nói nó đang ghép vào đâu.</p>';
		/* Anh Thắng 27/08/2026: *"như cái này biết cái nào bảng chính, cái nào bảng phụ"*. */
		echo '<p class="mo">Cách đọc bảng dưới: <span class="duoi nhan-chinh">CHÍNH</span> là bảng '
			. 'lương thật · <span class="duoi nhan-phu">PHỤ</span> là cơ sở <b>không có bảng riêng</b>, '
			. 'công của nó chạy vào bảng chính · hàng phụ <b>thụt vào</b> ngay dưới bảng chính của nó. '
			. 'Không nhãn = đứng một mình.</p>';

		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="ghep_cs">' . self::o_loc();
		/* 🔴 XẾP THEO NHÓM, KHÔNG THEO BẢNG CHỮ CÁI.
		   Anh Thắng 27/08/2026, sau khi khai xong: *"như cái này biết cái nào bảng chính, cái
		   nào bảng phụ"*. Bảng CÓ nói — ở cột thứ ba. Nhưng xếp A-Z thì một bảng chính và mấy
		   cơ sở phụ của nó nằm rải rác cách nhau mấy hàng (PART_TIME ở giữa, JP_HCM ở trên,
		   POSH_HCM ở dưới), nên muốn biết "cái nào thuộc cái nào" là phải đọc cả bảng rồi tự
		   ghép trong đầu. Nay: bảng chính đứng đầu nhóm, cơ sở phụ thụt vào ngay dưới nó, và
		   nhãn CHÍNH/PHỤ nằm CẠNH TÊN chứ không phải ở cột cuối. */
		$nhom = array();
		foreach ( $ds as $x ) {
			if ( '' === VHCC_Luong::ghep_vao( $x ) ) { $nhom[] = $x; }   // đứng riêng hoặc là CHÍNH
		}
		$hang = array();
		foreach ( $nhom as $x ) {
			$hang[] = array( $x, false );
			foreach ( VHCC_Luong::phu_cua( $x ) as $p ) {
				/* Chỉ vẽ cơ sở phụ mà người này XEM ĐƯỢC — bảng đang dựng từ `ds_coso_xem()`. */
				if ( in_array( $p, $ds, true ) ) { $hang[] = array( $p, true ); }
			}
		}
		/* Cơ sở phụ mà cơ sở CHÍNH của nó nằm ngoài tầm xem: vẫn phải vẽ, kẻo nó biến mất khỏi
		   màn và không ai gỡ ghép được nữa. */
		foreach ( $ds as $x ) {
			$co = false;
			foreach ( $hang as $h ) {
				if ( 0 === strcasecmp( (string) $h[0], (string) $x ) ) { $co = true; break; }
			}
			if ( ! $co ) { $hang[] = array( $x, true ); }
		}

		echo '<div class="cuon"><table><thead><tr><th>Cơ sở</th><th>Ghép vào bảng công của</th>'
			. '<th>Nghĩa là gì</th></tr></thead><tbody>';
		foreach ( $hang as $h ) {
			$x    = $h[0];
			$la_phu = $h[1];
			$chon = VHCC_Luong::ghep_vao( $x );
			$phu  = VHCC_Luong::phu_cua( $x );

			echo '<tr' . ( $la_phu ? ' class="hang-phu"' : '' ) . '>';
			/* Nhãn nằm CẠNH TÊN. Đó là chỗ mắt dừng lại đầu tiên; để nó ở cột cuối là bắt người
			   ta đọc ngang cả hàng mới biết hàng này là gì. */
			echo '<td>' . ( $la_phu ? '<span class="mo">↳ </span>' : '' )
				. '<b>' . esc_html( $x ) . '</b>';
			if ( '' !== $chon ) {
				echo ' <span class="duoi nhan-phu">PHỤ</span>';
			} elseif ( $phu ) {
				echo ' <span class="duoi nhan-chinh">CHÍNH</span>';
			}
			echo '</td>';

			echo '<td><select name="gh[' . esc_attr( $x ) . ']">';
			echo '<option value="">— bảng riêng —</option>';
			foreach ( $ds as $y ) {
				if ( 0 === strcasecmp( (string) $x, (string) $y ) ) { continue; }
				echo '<option value="' . esc_attr( $y ) . '"'
					. ( 0 === strcasecmp( (string) $chon, (string) $y ) ? ' selected' : '' ) . '>'
					. esc_html( $y ) . '</option>';
			}
			echo '</select></td>';

			/* Cột cuối nói bằng CÂU, không bằng thuật ngữ: người đọc cần biết CÔNG CHẠY ĐI ĐÂU,
			   đó mới là thứ ra tiền. */
			echo '<td>';
			if ( '' !== $chon ) {
				echo '<span class="mo">Công của cơ sở này <b>cộng vào bảng ' . esc_html( $chon )
					. '</b> — nó không có bảng lương riêng.</span>';
			} elseif ( $phu ) {
				echo '<span class="mo">Bảng lương thật. Đã <b>gồm</b> công của '
					. count( $phu ) . ' cơ sở: <b>' . esc_html( implode( ' · ', $phu ) ) . '</b>.</span>';
			} else {
				echo '<span class="mo">Bảng lương riêng, đứng một mình.</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p style="margin-top:10px"><button class="chinh">Lưu ghép bảng</button></p>';
		echo '</form></details></div>';
	}

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
			/* Tên kiểu lấy từ MỘT bảng khai trong `VHCC_Luong` — ba màn nói cùng một tên, và
			   thêm kiểu mới thì không phải đi sửa từng chỗ. */
			$ds_kieu = array( '' => '— theo bộ phận —' );
			foreach ( VHCC_Luong::CACH_TINH_TEN as $k_ct => $n_ct ) { $ds_kieu[ $k_ct ] = $n_ct; }
			foreach ( $ds_kieu as $k => $n ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $chon, false ) . '>'
					. esc_html( $n ) . '</option>';
			}
			echo '</select>';
			/* Một dòng nói kiểu ĐANG dùng nghĩa là gì. Tên kiểu ("Theo giờ", "Theo khung ca")
			   nghe thì rõ, nhưng khác nhau ở chỗ nào thì chỉ người viết ra mới biết — mà đây là
			   ô đổi con số ra tiền của cả cơ sở.
			   ⚠️ Dòng này đứng SAU `</select>`, không phải trước. Một `<div>` nằm trong thân
			      `<select>` là HTML sai: trình duyệt vứt nó ra ngoài theo cách riêng của mỗi
			      bản, và không có gì báo. */
			if ( isset( VHCC_Luong::CACH_TINH_CHU[ $dang ] ) ) {
				echo '<div class="mo" style="font-size:11.5px;max-width:420px">'
					. esc_html( VHCC_Luong::CACH_TINH_CHU[ $dang ] ) . '</div>';
			}
			echo '</td>';
			$nhan_ct = array( 'cong' => array( 'ca2', 'số công' ), 'ngay' => array( 'ca3', 'công / ngày' ),
				'ca' => array( 'ca4', 'giờ theo ca' ) );
			$n_ct    = isset( $nhan_ct[ $dang ] ) ? $nhan_ct[ $dang ] : array( 'ca1', 'số giờ' );
			echo '<td><span class="k ' . esc_attr( $n_ct[0] ) . '">' . esc_html( $n_ct[1] ) . '</span>'
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
	/**
	 * MỘT LƯỢT CHẤM -> phần ruột của một dòng trong ô lưới GIỜ.
	 *
	 * Tách ra vì từ khi hàng `-CD` / `-TC` gộp vào cùng ô với hàng chính, đoạn dựng này chạy
	 * HAI lần cho cùng một ô. Để nguyên tại chỗ thì phải chép đôi, và chép đôi cái đoạn phân
	 * biệt `?` với `—` là chuyện sớm muộn hai bản lệch nhau — lúc ấy dòng chính và dòng phụ
	 * đọc cùng một dữ liệu ra hai ký hiệu khác nhau mà không ai hiểu vì sao.
	 *
	 * Trả về: noi (ruột đầy đủ, có mã ca) · noi_tho (chỉ con số, cho dòng phụ chật chỗ) ·
	 *         chu (chú thích rê chuột) · lop (hậu tố lớp CSS) · phut (null = không đo được).
	 *
	 * `$r === null` = ngày ấy không có lượt chấm nào -> dấu `·`.
	 */
	/**
	 * NGÀY NGƯỜI ẤY ĐỨNG Ở CƠ SỞ KHÁC -> một dòng phụ trong chính ô ngày đó.
	 *
	 * Anh Thắng 27/08/2026: *"nhân viên mà làm từ 2 cơ sở trở lên thì cũng nhớ ghép lại"*.
	 *
	 * 🔴 KHÔNG CỘNG VÀO TỔNG của cơ sở đang xem, và phải NÓI RA là của cơ sở nào. Lương tính
	 *    theo cơ sở (đơn giá, khung ca, cách tính công đều riêng), nên cộng vào là trả sai tiền
	 *    ở cả hai nơi. Dòng này chỉ trả lời một câu: *hôm ấy người ta CÓ đi làm, chỉ là ở chỗ
	 *    khác* — thay cho một ô trống trông y hệt ngày nghỉ.
	 *
	 * Nền xám nhạt, khác hẳn màu theo ca của mấy dòng kia: nhìn cái là biết dòng này không
	 * thuộc bảng đang đọc.
	 */
	/**
	 * Nhãn cạnh TÊN: người này tháng ấy còn chạy ở cơ sở nào nữa.
	 *
	 * Phải có nhãn ở đầu hàng chứ không chỉ mấy dòng nhỏ rải trong ô: một người làm hai nơi mà
	 * ở lưới này chỉ có ba ngày thì nhìn hàng ấy tưởng người ta nghỉ gần hết tháng. Nhãn nói
	 * ngay: *phần còn lại nằm ở bảng cơ sở kia*.
	 */
	/**
	 * Ô TỔNG: mấy dòng "cơ sở kia được bao nhiêu".
	 *
	 * Anh Thắng 27/08/2026: *"phải hiện rõ cơ sở chính bao nhiêu công, cơ sở thứ 2 bao nhiêu
	 * công"*. Con số lớn phía trên là của cơ sở ĐANG XEM; mỗi dòng dưới đây là một cơ sở khác.
	 *
	 * 🔴 CÓ NHÃN ĐƠN VỊ Ở TỪNG DÒNG. Cơ sở kia có thể tính THEO GIỜ trong khi cơ sở đang xem
	 *    tính THEO CÔNG — hai con số nằm chồng nhau mà không ghi đơn vị thì người đọc cộng
	 *    thẳng chúng lại, và ra một tổng không có nghĩa gì.
	 *
	 * 🔴 KHÔNG CỘNG VÀO SỐ LỚN. Lương tính theo cơ sở; công của ngày ấy thuộc bảng của cơ sở kia.
	 */
	/**
	 * Ô TỔNG: con số lớn tách ra thành NGÀY · ĐÊM · TĂNG CA · BÙ.
	 *
	 * Anh Thắng 27/08/2026: *"tổng công ngày đêm hiện vô cuối hàng nhân viên luôn"*.
	 *
	 * Bốn con số ấy vốn CÓ, nhưng nằm ở bảng Giờ & Lương phía dưới — tức phải cuộn xuống rồi dò
	 * lại đúng người. Mà câu hỏi *"trong 27.5 công này bao nhiêu là đêm"* là câu hỏi ngay tại
	 * hàng, lúc đang nhìn hàng ấy.
	 *
	 * 🔴 CHỈ HIỆN PHẦN KHÁC 0. Hiện đủ bốn cho mọi hàng là mỗi hàng cao gấp đôi để nói "đêm 0 ·
	 *    tăng ca 0 · bù 0" — và cái đáng chú ý (người DUY NHẤT có công đêm) chìm nghỉm giữa
	 *    một rừng số 0.
	 *
	 * 🔴 KHÔNG HIỆN GÌ KHI CẢ THÁNG CHỈ CÓ CÔNG NGÀY. Lúc ấy "ngày 23" chỉ là chép lại con số
	 *    lớn ngay trên nó — thêm một dòng để nói đúng thứ vừa nói xong.
	 */
	private static function tach_cong( $e ) {
		$phan = array();
		$dem  = (float) ( isset( $e['congDem'] ) ? $e['congDem'] : 0 );
		$tc   = (float) ( isset( $e['congTangCa'] ) ? $e['congTangCa'] : 0 );
		$bu   = (float) ( isset( $e['congBu'] ) ? $e['congBu'] : 0 );
		$ngay = (float) ( isset( $e['congNgay'] ) ? $e['congNgay'] : 0 );
		/* Không có gì ngoài công ngày -> im. */
		if ( abs( $dem ) < 0.005 && abs( $tc ) < 0.005 && abs( $bu ) < 0.005 ) { return ''; }
		if ( abs( $ngay ) >= 0.005 ) { $phan[] = 'ngày <b>' . self::so_vp( $ngay ) . '</b>'; }
		if ( abs( $dem ) >= 0.005 )  { $phan[] = '🌙 <b>' . self::so_vp( $dem ) . '</b>'; }
		if ( abs( $tc ) >= 0.005 )   { $phan[] = 'TC <b>' . self::so_vp( $tc ) . '</b>'; }
		if ( abs( $bu ) >= 0.005 )   { $phan[] = 'bù <b>' . self::so_vp( $bu ) . '</b>'; }
		return '<div class="tach-cong" title="' . esc_attr( 'Bốn phần cộng lại thành con số lớn: '
			. 'công ngày ' . self::so_vp( $ngay ) . ' · công đêm ' . self::so_vp( $dem )
			. ' · tăng ca ' . self::so_vp( $tc ) . ' · công bù ' . self::so_vp( $bu ) )
			. '">' . implode( ' · ', $phan ) . '</div>';
	}

	private static function tong_coso_khac( $tk_nguoi ) {
		if ( ! is_array( $tk_nguoi ) || ! $tk_nguoi ) { return ''; }
		$cs = array_keys( $tk_nguoi );
		sort( $cs );
		$h = '';
		foreach ( $cs as $c ) {
			$x  = $tk_nguoi[ $c ];
			$so = ( 'cong' === $x['donVi'] && null !== $x['cong'] )
				? self::so_vp( $x['cong'] ) . ' công'
				: self::so_vp( $x['gio'] ) . ' giờ';
			$h .= '<div class="tk-ngoai" title="' . esc_attr( $c . ' — ' . $x['soNgay'] . ' ngày · '
				. $so . "\n" . 'Tính bằng công thức của CHÍNH cơ sở ấy. KHÔNG cộng vào tổng ở đây: '
				. 'lương tính theo cơ sở.' ) . '">' . esc_html( $c ) . ' <b>' . esc_html( $so ) . '</b>'
				. '<span class="mo"> · ' . (int) $x['soNgay'] . 'n</span></div>';
		}
		return $h;
	}

	/**
	 * DẤU TÍCH "CHỜ TRẢ VỀ NHÂN SỰ" trên hàng của từng người.
	 *
	 * Anh Thắng 28/08/2026: *"Thêm dấu tích, nhân viên nghỉ việc, trong tháng đó không phát sinh
	 * công, nó tự đẩy ngược về nhân sự. Khi tích thì trong cửa hàng đó vẫn có, nhưng nằm phía là
	 * chờ trả về nhân sự"*.
	 *
	 * ⚠️ KHÔNG MỞ `<form>` BAO CẢ BẢNG. Lưới cả tháng là một `<table>` lớn; một form bọc ngoài
	 *    rồi mỗi hàng một nút thì bấm nút hàng ba lại gửi cả bảng. Mỗi dấu tích là MỘT form nhỏ
	 *    nằm gọn trong `<td>` của chính hàng ấy — và lưới này không nằm trong form nào khác
	 *    (khác hẳn bảng ở màn Quản lý nhân sự, xem chú thích dài ở `hang_sua()`).
	 */
	private static function o_cho_tra( $ma, $dang_cho, $ky, $toi, $cs ) {
		if ( ! VHCC_Vai::duoc( $toi, 'lich_lam' ) ) {
			/* Người không có quyền tích vẫn phải THẤY ai đang chờ trả — nếu không, họ đọc bảng
			   mà không hiểu vì sao mấy hàng cuối lại nằm tách ra. */
			return $dang_cho ? ' <span class="duoi cho-tra-nhan" title="Đang chờ trả về nhân sự">'
				. 'chờ trả về</span>' : '';
		}
		$h  = ' <form method="post" style="display:inline">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. self::o_loc()
			. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">'
			. '<input type="hidden" name="tv_ma" value="' . esc_attr( $ma ) . '">'
			. '<input type="hidden" name="tv_bat" value="' . ( $dang_cho ? '0' : '1' ) . '">';
		$h .= '<button class="mo-hs' . ( $dang_cho ? ' cho-tra-nhan' : '' ) . '"'
			. ' name="viec" value="cho_tra"'
			. ' title="' . esc_attr( $dang_cho
				? 'Đang chờ trả về nhân sự. Bấm để bỏ đánh dấu — người này ở lại cửa hàng như cũ.'
				: 'Đánh dấu chờ trả về nhân sự. Người này VẪN ở trong cửa hàng, chỉ nằm riêng ở '
					. 'cuối bảng; tháng nào đã hết mà không phát sinh công thì hệ tự trả về kho '
					. 'nhân sự.' ) . '">'
			. ( $dang_cho ? '☑ chờ trả về' : '☐ chờ trả về' ) . '</button>';
		$h .= '</form>';
		return $h;
	}

	private static function chip_coso_khac( $ck_nguoi ) {
		if ( ! is_array( $ck_nguoi ) || ! $ck_nguoi ) { return ''; }
		$cs = array();
		foreach ( $ck_nguoi as $x ) {
			$t = (string) $x['coso'];
			if ( '' !== $t ) { $cs[ $t ] = true; }
		}
		if ( ! $cs ) { return ''; }
		$ds = array_keys( $cs );
		sort( $ds );
		return ' <span class="duoi ngoai" title="Tháng này còn chấm công ở: ' . esc_attr( implode( ' · ', $ds ) )
			. "\n" . 'Những ngày ấy hiện thành dòng xám trong ô, và KHÔNG cộng vào cột TỔNG ở đây — '
			. 'công của chúng thuộc bảng của cơ sở kia.">cũng làm ở ' . esc_html( implode( ' · ', $ds ) )
			. '</span>';
	}

	private static function o_coso_khac( $ck, $ho_ten, $ngay ) {
		if ( ! is_array( $ck ) || '' === (string) $ck['coso'] ) { return ''; }
		$chu = self::ngay_vn( $ngay ) . ' · ' . $ho_ten
			. "\n" . 'chấm ở cơ sở ' . $ck['coso']
			. "\n" . ( '' !== $ck['vao'] ? $ck['vao'] : '—' ) . ' → '
			. ( '' !== $ck['ra'] ? $ck['ra'] : '—' )
			. "\n" . VHCC_Cham::chu_gio( $ck['phut'] )
			. "\n" . '⚠ KHÔNG cộng vào tổng của cơ sở đang xem — công của ngày này thuộc bảng '
			. 'của cơ sở kia.';
		return '<div class="mdem ngoai" title="' . esc_attr( $chu ) . '">'
			. esc_html( $ck['coso'] ) . ' '
			. ( null === $ck['phut'] ? '—' : self::so_vp( round( (int) $ck['phut'] / 60, 1 ) ) )
			. '</div>';
	}

	/**
	 * @param string $kieu Cách tính của cơ sở: 'gio' | 'cong' | 'ngay'. Xem `VHCC_Luong::cach_tinh`.
	 */
	private static function o_luoi_gio_mot( $r, $ho_ten, $ds_ca, $kieu = 'gio', $nguong_tre = 0,
		$don_tre = array() ) {
		if ( null === $r ) {
			return array( 'noi' => '·', 'noi_tho' => '·', 'chu' => '', 'lop' => '', 'phut' => null );
		}
		/* Ba trạng thái khác nhau, ba ký hiệu khác nhau — gộp lại là xoá mất đúng những ngày
		   cần soi:
		     thiếu giờ ra   -> `?`  (quên bấm lúc về)
		     ra sớm hơn vào -> `—`  (dấu hiệu ghi sai)
		     bình thường    -> số giờ */
		if ( null === $r['phut'] ) {
			$thieu_ra = ( '' !== $r['vao'] && '' === $r['ra'] );
			$k = $thieu_ra ? '?' : '—';
			/* Thiếu giờ ra thì KHÔNG tính công, kể cả ở kiểu "có đi là được": tính đại 1 công
			   cho ngày quên check-out là biến một lỗi bấm máy thành tiền. */
			return array(
				'noi' => $k, 'noi_tho' => $k, 'lop' => ' hong', 'phut' => null, 'cong' => 0,
				'chu' => self::ngay_vn( $r['ngay'] ) . ' · ' . $ho_ten
					. "\n" . ( '' !== $r['vao'] ? $r['vao'] : '—' ) . ' → '
					. ( '' !== $r['ra'] ? $r['ra'] : '—' ) . "\n"
					. ( $thieu_ra ? '⚠ thiếu giờ ra — quên bấm lúc về'
						: '⚠ giờ ra sớm hơn giờ vào — dấu hiệu ghi sai' ) );
		}
		/* Chú thích nói luôn ngày đó rơi vào ca nào, mỗi ca mấy tiếng — đúng câu anh Thắng hỏi:
		   *"làm ca nào, ca đó mấy tiếng, từ ca nào đến ca nào"*. */
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
		   Anh Thắng 26/08: *"hiện sẵn bạn ca nào ca nào luôn nhé, đi rà này rất khó"*. Đúng: một
		   tháng của 21 người là hơn 600 ô, rê chuột từng ô để biết ca thì không ai rà nổi. Số
		   giờ nằm trên, mã ca nằm dưới, và MÀU NỀN theo ca chính — nhìn một cái là thấy cả
		   tháng ai chạy ca nào. */
		$i_ca = VHCC_Ca::ca_chinh( $ds_ca, $tc );
		$ma_o = VHCC_Ca::ma_o( $ds_ca, $tc );
		/* 🔴 "CÓ ĐI LÀ ĐƯỢC": in 1 CÔNG, không in số giờ.
		   Anh Thắng 28/08/2026: *"1 số cửa hàng chấm công theo có đi là được… có giờ vào và giờ
		   ra là được"*. Với những cửa hàng ấy, ô ghi `0.41` hay `0.03` không nói lên điều gì —
		   mà lại trông như người ta chỉ làm mười lăm phút.
		   ⚠️ SỐ GIỜ VẪN GIỮ trong chú thích rê chuột: đổi cách ĐỌC chứ không giấu dữ liệu, và
		      đổi kiểu tính lại là ra lại đúng như cũ. */
		$cong = ( 'ngay' === $kieu ) ? VHCC_Luong::cong_co_di( $r['vaoGiay'], $r['raGiay'] ) : 0;

		/* 🔴 "THEO KHUNG CA": LẤY GIỜ CA LÀM GIỜ CÔNG, HẾT SỐ LẺ.
		   Anh Thắng 28/08/2026, nhìn lưới đầy 5.1 · 6.9 · 13.1: *"Chưa làm tròn giờ theo ca"*.
		   Người vào 05:57 về 14:03 không làm 8.1 tiếng — họ làm đúng MỘT CA. Sáu phút ấy là
		   quãng đi từ cửa vào máy; cộng vào là trả tiền cho việc bấm máy sớm, mà người bấm muộn
		   ba phút thì bị trừ. Sai hai hướng nên tổng tháng nhìn vẫn "hợp lý".
		   Ngưỡng lấy từ chính ngưỡng đi trễ của cửa hàng (`VHCC_Tre`) — anh đã set nó ở đó rồi,
		   và hai câu hỏi vốn là một: trễ bao nhiêu phút thì còn coi như đủ ca. */
		$phut_o = (int) $r['phut'];
		$lop_th = '';
		if ( 'ca' === $kieu ) {
			$lt = VHCC_Ca::lam_tron( $ds_ca, $r['vaoGiay'], $r['raGiay'],
				VHCC_Ca::la_cuoi_tuan( $r['ngay'] ), $nguong_tre );
			$phut_o = (int) $lt['phut'];
			/* 🔴 NÓI RA MỖI KHI CON SỐ TRONG Ô KHÁC GIỜ MÁY GHI — cả khi tròn LÊN (trễ trong
			   ngưỡng) lẫn khi cắt XUỐNG (bấm sớm/về muộn vài phút). Người đọc bảng lương phải
			   đối chiếu được ô với giờ chấm thật; một con số đã bị đổi mà không nói là con số
			   không ai kiểm được. */
			if ( $phut_o !== (int) $r['phut'] ) {
				$chu .= "\n✓ giờ công theo ca: " . VHCC_Cham::chu_gio( $phut_o )
					. ' (giờ chấm thật ' . VHCC_Cham::chu_gio( (int) $r['phut'] ) . ')';
			}
			if ( ! empty( $lt['tron'] ) ) {
				$chu .= "\n✓ đã làm tròn lên đủ ca";
			}
			/* 🔴 NÓI RA NGƯỜI NÀY NHẬN TỪ CA NÀO ĐẾN CA NÀO.
			   Anh Thắng 28/08/2026: *"giờ như này là bạn đang làm từ ca 2 đến ca 3. lấy giờ ca
			   tính đầu và đuôi thì biết bạn đó bắt đầu từ ca nào"*. Dòng "Ca 1 → Ca 3" ở trên
			   kể MỌI ca lượt chấm chạm vào, kể cả ca chỉ chạm 59 phút ở rìa — nên nó nói khác
			   với con số trong ô, và người đọc không hiểu vì sao. */
			if ( '' !== $lt['ca_dau'] ) {
				$chu .= "\n▸ nhận ca: " . $lt['ca_dau']
					. ( $lt['ca_cuoi'] !== $lt['ca_dau'] ? ' → ' . $lt['ca_cuoi'] : '' );
			}
			foreach ( (array) $lt['ria'] as $x ) {
				$chu .= "\n· " . VHCC_Cham::chu_gio( (int) $x['phut'] ) . ' chạm ' . $x['ten']
					. ' — ngoài ca nhận, không tính công';
			}
			/* 🔴 THIẾU GIỜ THÌ Ô VÀNG. Anh Thắng 27/08: *"nếu bạn nào chấm thiếu giờ thì hiện
			   cảnh báo ô vàng cho cửa hàng trưởng biết"*. Vàng chứ không đỏ: thiếu giờ chưa
			   chắc là lỗi của ai — có thể là xin nghỉ nửa ca, có thể là máy hỏng. */
			/* 🔴 ĐƠN XIN PHÉP ĐI TRỄ ĐÃ DUYỆT THÌ THÔI VÀNG — nhưng SỐ KHÔNG ĐỔI.
			   Anh Thắng 27/08: *"cửa hàng trưởng duyệt đơn thì cảnh báo đó sẽ bỏ"*. Bỏ CẢNH
			   BÁO, không bỏ giờ: nếu đơn cộng bù giờ thì nó thành một cửa cấp công không đi qua
			   chấm công, và cửa ấy không có ai gác. Ô vẫn ghi đúng số giờ có mặt, chỉ là thôi
			   kêu — vì đã có người chịu trách nhiệm cho chỗ thiếu ấy. */
			$xin_ok = ( class_exists( 'VHCC_XinTre' ) && method_exists( 'VHCC_XinTre', 'da_duyet' ) )
				&& VHCC_XinTre::da_duyet( $don_tre, (string) $r['maNV'], (string) $r['ngay'] );
			foreach ( (array) $lt['thieu'] as $x ) {
				$lop_th = $xin_ok ? ' xin-tre' : ' vang';
				$chu   .= "\n" . ( $xin_ok ? '✓' : '⚠' ) . ' thiếu '
					. VHCC_Cham::chu_gio( (int) $x['phut'] ) . ' của ' . $x['ten']
					. ( $xin_ok ? ' — đã có đơn xin phép đi trễ được duyệt' : '' );
			}
			if ( ! empty( $lt['ngoai_moi_ca'] ) ) {
				$lop_th = ' vang';
				$chu   .= "\n⚠ cả lượt nằm NGOÀI mọi khung ca — giữ nguyên giờ thật. "
					. 'Khung ca của cửa hàng có thể đang khai lệch.';
			}
		}
		$so = ( 'ngay' === $kieu )
			? '<b>' . (int) $cong . '</b>'
			: '<b>' . self::so_vp( round( $phut_o / 60, 1 ) ) . '</b>';
		return array(
			'noi'     => $so . ( '' !== $ma_o ? '<div class="mca">' . esc_html( $ma_o ) . '</div>' : '' ),
			'noi_tho' => $so,
			'chu'     => $chu,
			'lop'     => ( $i_ca >= 0 ? ' ca' . ( ( $i_ca % 4 ) + 1 ) : '' ) . $lop_th,
			'cong'    => (int) $cong,
			'phut'    => $phut_o );
	}

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
	/**
	 * Câu báo sau khi sửa giờ: nói CŨ -> MỚI cho TỪNG dòng, không chỉ nói "đã lưu".
	 *
	 * Người vừa sửa giờ công của người khác phải đọc lại được đúng thứ mình vừa làm, ngay lúc còn
	 * nhớ mình định làm gì. Một lượt nay chạm được nhiều dòng (ca chính ở cơ sở này, ca đêm ở cơ
	 * sở đã ghép), nên câu báo phải kể ra dòng nào đổi gì — gộp lại thành một câu là người đọc
	 * không biết giờ mình gõ rơi vào ca nào.
	 */
	private static function chu_sua( $ds ) {
		$phan = array();
		$ma   = '';
		$ngay = '';
		foreach ( $ds as $r ) {
			$ma   = isset( $r['maNV'] ) ? (string) $r['maNV'] : $ma;
			$ngay = isset( $r['ngay'] ) ? (string) $r['ngay'] : $ngay;
			$chu  = array();
			foreach ( $r['doi'] as $o => $d ) {
				$chu[] = ( 'vao' === $o ? 'giờ vào ' : 'giờ ra ' ) . $d['cu'] . ' → ' . $d['moi'];
			}
			list( , $ht ) = VHCC_Nhan::tach_hau_to( isset( $r['maNV'] ) ? $r['maNV'] : '' );
			$phan[] = ( count( $ds ) > 1
				? self::ten_dong_sua( isset( $r['coSo'] ) ? (string) $r['coSo'] : '', $ht ) . ': ' : '' )
				. implode( ' · ', $chu );
		}
		return 'Đã sửa ' . implode( ' | ', $phan ) . ' cho ' . $ma . ' ngày ' . $ngay
			. '. Dòng này nay mang nhãn nguồn "sửa" (thôi tính là lượt máy ghi) và đã vào sổ nhật '
			. 'ký kèm giờ cũ — xoá không được.';
	}

	/**
	 * Tên dễ đọc của một dòng trong chùm: cơ sở + ca nào.
	 *
	 * Hậu tố trần (`TC`, `CD`) chỉ người dựng bảng mới hiểu. Người sửa lương cần đọc ra "ca đêm"
	 * — sửa nhầm ca là sửa nhầm tiền, mà hai ô giờ trông giống hệt nhau.
	 */
	private static function ten_dong_sua( $coso, $hau_to ) {
		$ten = array( 'TT' => 'thu tiền', 'TG' => 'trực ghế', 'CD' => 'ca đêm / tăng ca',
			'CT' => 'công tối', 'TC' => 'tăng cường / ca đêm' );
		return $coso . ( '' === $hau_to ? ' · ca chính'
			: ' · ' . ( isset( $ten[ $hau_to ] ) ? $ten[ $hau_to ] : $hau_to ) . ' (-' . $hau_to . ')' );
	}

	/** Một cặp ô giờ vào / giờ ra. `$khoa` rỗng = dạng ô ĐƠN cũ; có khoá = dạng mảng theo dòng. */
	private static function o_cap_gio( $co_gio, $khoa ) {
		$tv = ( $co_gio ? 'sg_vao' : 'bu_vao' ) . ( '' === $khoa ? '' : '[' . $khoa . ']' );
		$tr = ( $co_gio ? 'sg_ra' : 'bu_ra' ) . ( '' === $khoa ? '' : '[' . $khoa . ']' );
		$id = 'iv_' . preg_replace( '/[^A-Za-z0-9]+/', '_', ( $co_gio ? 'sg' : 'bu' ) . '_' . $khoa );
		$h  = '<div><label for="' . esc_attr( $id . '_v' ) . '">Giờ vào' . ( $co_gio ? ' mới' : '' ) . '</label>'
			. '<input id="' . esc_attr( $id . '_v' ) . '" name="' . esc_attr( $tv ) . '" type="time"></div>';
		$h .= '<div><label for="' . esc_attr( $id . '_r' ) . '">Giờ ra' . ( $co_gio ? ' mới' : '' ) . '</label>'
			. '<input id="' . esc_attr( $id . '_r' ) . '" name="' . esc_attr( $tr ) . '" type="time"></div>';
		if ( $co_gio ) {
			/* Ô trống = GIỮ NGUYÊN. Muốn xoá trắng phải tích — một hành động riêng, cố ý. */
			$xv = 'sg_xoa_vao' . ( '' === $khoa ? '' : '[' . $khoa . ']' );
			$xr = 'sg_xoa_ra' . ( '' === $khoa ? '' : '[' . $khoa . ']' );
			$h .= '<div style="flex:0 0 auto"><label>Xoá trắng</label>'
				. '<label style="display:inline;font-size:12px;margin-right:10px">'
				. '<input type="checkbox" name="' . esc_attr( $xv ) . '" value="1"> vào</label>'
				. '<label style="display:inline;font-size:12px">'
				. '<input type="checkbox" name="' . esc_attr( $xr ) . '" value="1"> ra</label></div>';
		}
		return $h;
	}

	private static function hang_sua( $so_cot, $cs, $ngay, $ma_dd, $co_gio, $ky, $toi ) {
		$duoc = $co_gio ? VHCC_Vai::duoc( $toi, 'sua_gio' ) : VHCC_Vai::duoc( $toi, 'cham_bu' );
		/* 🔴 HÀNG SỬA PHẢI DÍNH BÊN TRÁI, KHÔNG TRẢI THEO BỀ RỘNG BẢNG.
		   Anh Thắng 27/08/2026: *"lệch ô sửa"* — kèm ảnh khối vàng kéo dài sang phải và nút Lưu
		   bị cắt ngoài mép.
		   Nguyên do: `colspan` phủ hết 33 cột, mà bảng thì rộng hơn màn hình và nằm trong khung
		   cuộn ngang. Ô ấy vì thế rộng bằng CẢ BẢNG, nên biểu mẫu bên trong trải theo — ô Vì sao
		   dài mấy nghìn điểm ảnh và nút Lưu nằm ngoài tầm nhìn. Người ta thấy hàng sửa mở ra mà
		   không thấy nút bấm, rồi tưởng hỏng.
		   Cách chữa: bọc ruột trong một khối DÍNH BÊN TRÁI (`position:sticky;left:0`) rộng đúng
		   bằng khung nhìn — cuộn ngang tới đâu thì hàng sửa vẫn nằm nguyên chỗ mắt đang nhìn.
		   Cùng cơ chế với cột Nhân viên vốn đã ghim bên trái. */
		echo '<tr class="hang-sua"><td colspan="' . (int) $so_cot . '"><div class="hs-in">';

		if ( ! $duoc ) {
			echo '<div class="bao canh" style="margin:0">' . esc_html( $co_gio
				? 'Sửa giờ đã có cần quyền Admin. Thấy giờ sai thì gắn cờ để Admin sửa.'
				: 'Bù giờ vào ô trống cần quyền Cửa hàng trưởng trở lên.' ) . '</div></div></td></tr>';
			return;
		}

		/* 🔴 SỬA CẢ CHÙM CƠ SỞ TRONG MỘT LƯỢT.
		   Anh Thắng 27/08/2026: *"nếu cơ sở được ghép từ 2 cơ sở, thì khi sửa sẽ sửa luôn được
		   cả 2 là 4 giờ vào ra"*.
		   Lưới nay gộp VP_KH-HCM + SETUP_VP thành một hàng, nhưng dòng ca đêm nằm ở cơ sở PHỤ.
		   Chỉ dựng một cặp ô cho cơ sở đang xem thì bấm sửa ngày có ca đêm sẽ bị máy chủ đáp
		   "chưa có dòng chấm công nào để sửa" — trong khi ô ấy đang hiện số. */
		$chum = VHCC_Luong::chum_cua( $cs );
		$dong = $co_gio ? VHCC_Bu::cac_o( $chum, $ngay, $ma_dd ) : array();
		$dg   = VHCC_Bu::gio_hien_tai( $cs, $ngay, $ma_dd );

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

		if ( count( $dong ) > 1 ) {
			/* Hơn một dòng thật -> mỗi dòng một cặp ô, KHÔNG gộp. Gộp lại là bắt người ta đoán
			   giờ mình gõ sẽ rơi vào ca nào — mà hai ca ấy trả tiền khác nhau. */
			foreach ( $dong as $d_i ) {
				$khoa = $d_i['coso'] . '~' . $d_i['hauTo'];
				echo '<div style="flex:1 1 100%;border-top:1px dashed #cbd5e1;margin-top:6px;padding-top:6px">'
					. '<div class="mo" style="font-size:11.5px;margin-bottom:2px"><b>'
					. esc_html( self::ten_dong_sua( $d_i['coso'], $d_i['hauTo'] ) ) . '</b>'
					. ' — đang có: vào <b>' . esc_html( $d_i['vao'] ) . '</b> · ra <b>'
					. esc_html( $d_i['ra'] ) . '</b></div>'
					. '<div class="hang" style="margin:0;align-items:flex-end">'
					. self::o_cap_gio( $co_gio, $khoa ) . '</div></div>';
			}
		} else {
			/* Đúng một dòng (hoặc chưa có dòng nào) -> giữ nguyên dạng ô ĐƠN. */
			echo self::o_cap_gio( $co_gio, '' );
			/* Chế độ BÙ chỉ ghi vào cơ sở đang xem, nhưng vẫn phải NÓI RA ngày ấy cơ sở ghép có
			   gì — nếu không, người ta bù một ca vào đây trong khi ca kia đã có giờ ở cơ sở phụ,
			   thành một ngày hai ca chồng nhau mà không ai thấy. */
			if ( ! $co_gio && count( $chum ) > 1 ) {
				$khac = VHCC_Bu::cac_o( $chum, $ngay, $ma_dd );
				$noi  = array();
				foreach ( $khac as $k_i ) {
					if ( 0 === strcasecmp( $k_i['coso'], $cs ) && '' === $k_i['hauTo'] ) { continue; }
					$noi[] = self::ten_dong_sua( $k_i['coso'], $k_i['hauTo'] )
						. ': vào ' . $k_i['vao'] . ' · ra ' . $k_i['ra'];
				}
				if ( $noi ) {
					echo '<div class="mo" style="flex:1 1 100%;font-size:11.5px">Ngày này ở cơ sở đã '
						. 'ghép cũng đang có: ' . esc_html( implode( ' | ', $noi ) ) . '</div>';
				}
			}
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
	 * CÒN THIẾU GÌ ĐỂ RA ĐƯỢC TIỀN — ba dải cảnh báo, dời từ màn Bảng công sang đây.
	 *
	 * Anh Thắng 27/08/2026, kèm hai ảnh: *"bỏ chỗ này"*.
	 *
	 * 🔴 CHÚNG ĐÚNG, NHƯNG ĐỨNG NHẦM CHỖ. Bảng công là màn người ta mở HẰNG NGÀY để ĐỌC; ba dải
	 *    ấy nói về việc KHAI MỘT LẦN, và cái dài nhất dán tên hai mươi mấy người ra giữa màn,
	 *    mỗi lần mở lại đọc lại. Người xem công không khai được gì với chúng — họ không có
	 *    quyền, và cũng không phải việc của họ. Ở đây thì ngược lại: người mở màn Cấu hình đang
	 *    đi khai, và mấy ô để khai nằm ngay bên dưới.
	 *
	 * ⚠️ TRA THẲNG, KHÔNG CHẠY ENGINE LƯƠNG. Ba câu hỏi này chỉ cần ba phép tra rẻ; gọi
	 *    `bang_cong_va_luong()` để lấy chúng là tính lại cả tháng của cả cơ sở chỉ để biết một ô
	 *    cấu hình có trống hay không.
	 */
	private static function the_thieu_khai( $ky, $toi, $cs ) {
		if ( '' === $cs ) { return; }
		if ( ! VHCC_Vai::duoc( $toi, 'luong' ) ) { return; }

		global $wpdb;
		$cfg = VHCC_Luong::vp_cfg( $cs );
		$ds  = array();

		/* 🔴 KHÔNG ĐOÁN MẪU SỐ. Đoán là sai tiền của MỌI người cùng lúc, mà bảng vẫn có số nên
		   chẳng ai nghi. Nên chưa khai thì cột Tiền hiện “—”, và phải nói ra vì sao. */
		if ( empty( $cfg['ngayCongThang'] ) ) {
			$ds[] = array( 'loi', 'Chưa khai <b>số ngày công chuẩn của tháng</b> — cột Tiền hiện “—”. '
				. 'Hệ thống KHÔNG đoán mẫu số: đoán là sai tiền của mọi người cùng lúc, mà bảng vẫn '
				. 'có số nên chẳng ai nghi. Khai ở khối <b>Công thức tính công</b> bên dưới.' );
		}
		if ( empty( $cfg['ktMaNV'] ) ) {
			$ds[] = array( 'canh', 'Chưa khai mã NV thuộc <b>Kế toán văn phòng</b> (<code>ktMaNV</code>) '
				. '— nên chưa ai được áp khung thứ Bảy 08:30–12:00 và luật Chủ nhật nghỉ.' );
		}

		/* Đếm thôi, KHÔNG dán tên ra. Hai mươi mấy cái tên chạy ba dòng là thứ người ta lướt qua
		   chứ không đọc; một CON SỐ kèm đường tới đúng bảng để sửa mới là thứ dùng được. */
		$thieu = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' )
			. ' WHERE cua_hang=%s AND (luong_co_ban IS NULL OR luong_co_ban<=0)', $cs ) );
		if ( $thieu > 0 ) {
			$ds[] = array( 'canh', 'Có <b>' . $thieu . ' người</b> ở cơ sở này chưa khai '
				. '<b>lương cơ bản</b> — tiền của họ ra 0. Khai ở màn <b>Hồ sơ &amp; tài khoản</b>, '
				. 'cột Lương cơ bản.' );
		}

		echo '<div class="the" id="thieukhai"><h2>Còn thiếu để ra được tiền</h2>';
		if ( ! $ds ) {
			echo '<p class="mo">Đủ cả: đã khai ngày công chuẩn, đã khai mã kế toán văn phòng, và '
				. 'mọi người ở cơ sở này đều có lương cơ bản.</p></div>';
			return;
		}
		/* ⚠️ Chuỗi ở đây do CHÍNH HÀM NÀY dựng — không có mẩu nào đến từ người dùng, nên in
		   thẳng. `wp_kses()` là để lọc chữ người khác gõ; gọi nó cho chuỗi của mình vừa thừa vừa
		   kéo theo một hàm mà bộ thử không có (và trang trắng ngay lần đầu ai đó mở màn này). */
		foreach ( $ds as $x ) {
			echo '<div class="bao ' . esc_attr( $x[0] ) . '">' . $x[1] . '</div>';
		}
		echo '</div>';
	}

	/**
	 * ĐẶT TÊN ĐẦY ĐỦ CHO MÃ CƠ SỞ.
	 *
	 * Mã trong sổ là thứ máy đọc: `FARM_PT`, `FF_SC`, `PINPALL_HCM`. Người đọc bảng phải tự dịch
	 * trong đầu, người mới thì không dịch nổi — mà trên một ô chọn hai mươi mấy dòng, đoán sai
	 * một dòng là xếp lịch hoặc nạp công cho cửa hàng khác.
	 *
	 * 🔴 CHỈ THÊM MỘT LỚP TÊN ĐỂ HIỆN RA, KHÔNG ĐỔI MÃ. Mã cơ sở là KHOÁ: chấm công, lịch, máy,
	 *    lương đều trỏ vào nó, và cái máy ngoài cửa hàng cũng khai bằng chính mã ấy. Đổi mã cho
	 *    dễ đọc là cắt đứt mọi dòng cũ — và cắt im lặng, vì bảng mới vẫn đầy số.
	 */
	private static function the_ten_cs( $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'ngoai_coso' ) ) { return; }
		$ds  = VHCC_NhanSu::ds_coso();
		if ( ! $ds ) { return; }
		$ten = VHCC_NhanSu::ten_coso_bang();
		$chua = 0;
		foreach ( $ds as $x ) { if ( ! isset( $ten[ $x ] ) ) { $chua++; } }

		echo '<div class="the" id="tencs"><details' . ( $chua ? '' : '' ) . '><summary>'
			. '<b>Tên đầy đủ của cơ sở</b> <span class="mo">(' . count( $ds ) . ' mã'
			. ( $chua ? ' · <b>' . (int) $chua . ' chưa đặt tên</b>' : ' · đã đặt tên hết' )
			. ')</span></summary>';
		echo '<p class="mo">Mã là thứ <b>máy</b> đọc và là <b>khoá</b> của mọi bảng — nó không đổi. '
			. 'Tên ở đây chỉ để <b>hiện ra cho người đọc</b>, và luôn hiện kèm mã (<code>FF_SC — '
			. 'Fun Fair Sense City</code>) để còn đối chiếu với tệp .csv của máy và với cái nhãn dán '
			. 'trên máy chấm công. Để trống là gỡ tên đi.</p>';
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">';
		echo self::o_loc();
		echo '<div class="cuon"><table class="cc"><thead><tr><th>Mã cơ sở</th><th>Tên đầy đủ</th>'
			. '</tr></thead><tbody>';
		foreach ( $ds as $x ) {
			$id = 'tcs_' . preg_replace( '/[^A-Za-z0-9]+/', '_', $x );
			echo '<tr><td><b>' . esc_html( $x ) . '</b></td>';
			echo '<td style="text-align:left"><label class="an" for="' . esc_attr( $id ) . '">Tên của '
				. esc_html( $x ) . '</label>'
				. '<input id="' . esc_attr( $id ) . '" name="tcs[' . esc_attr( $x ) . ']" maxlength="60" '
				. 'style="width:100%" value="' . esc_attr( isset( $ten[ $x ] ) ? $ten[ $x ] : '' ) . '"></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p><button class="chinh" name="viec" value="ten_cs">Lưu tên cơ sở</button></p>';
		echo '</form></details></div>';
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
			} elseif ( 'ktVaiTro' === $k ) {
				/* 🔴 VAI TRÒ THÌ TÍCH, ĐỪNG GÕ. Anh Thắng 27/08/2026: *"Lấy theo vai trò nhân
				   viên là kế toán. hoặc vai trò khác, mình kích vào"*.
				   Gõ tay tên vai là mời gõ sai: "Kế toán" / "ke toan" / "Kế Toán" — mà sai một
				   chữ thì ô ấy không khớp ai, và bảng lương vẫn ra số nên chẳng ai nghi. Tích
				   từ CHÍNH danh sách vai đang có (kể cả vai tự tạo như "Kế toán POSH") thì không
				   có cách viết nào lọt.
				   ⚠️ Ô tích thật, không phải ô xổ nhiều dòng: một người có thể thuộc nhiều vai
				      được tính, và `<select multiple>` trên điện thoại là một cái bẫy — bấm một
				      dòng là bỏ chọn hết mấy dòng kia. */
				$dang = is_array( $gt ) ? $gt : preg_split( '/[\s,;]+/', (string) $gt, -1, PREG_SPLIT_NO_EMPTY );
				$dang_ma = array();
				foreach ( (array) $dang as $x ) { $dang_ma[ VHCC_Vai::ma( $x ) ] = true; }
				echo '<div class="o-vai-tick">';
				foreach ( VHCC_Vai::ds_ten() as $ten_vai ) {
					$idv = $id . '_' . substr( md5( $ten_vai ), 0, 6 );
					echo '<label for="' . esc_attr( $idv ) . '"><input type="checkbox"'
						. ' id="' . esc_attr( $idv ) . '"'
						. ' name="ctv[' . esc_attr( $k ) . '][]" value="' . esc_attr( $ten_vai ) . '"'
						. checked( isset( $dang_ma[ VHCC_Vai::ma( $ten_vai ) ] ), true, false )
						. '><span>' . esc_html( $ten_vai ) . '</span></label>';
				}
				echo '</div>';
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
	private static function the_tong_ca( $b, $cs, $toi = array() ) {
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
			. ' — ' . esc_html( self::chu_ds_ca( $ds_ca ) ) . '.</p>';
		/* 🔴 MƯỢN KHUNG CA CỦA NGƯỜI KHÁC LÀ SỐ SAI, PHẢI NÓI THẲNG RA LÀ SAI.
		   Anh Thắng 28/08/2026: *"Anh thấy ca làm nó có nè, nhưng nó đang lấy chung từ cửa hàng
		   khác nên bị sai"*. Bảng vẫn ra số đẹp, cột nào cũng có giờ — nên nhìn qua tưởng đúng.
		   Một dòng chữ xám nói "mặc định (chưa ai khai)" thì không ai đọc. Cả bảng chỉ đúng khi
		   khung ca đúng, nên chỗ chưa khai phải là một khối vàng có đường bấm thẳng tới ô khai,
		   chứ không phải một câu chú thích. */
		if ( 'rieng' !== $nguon ) {
			echo '<div class="bao canh" style="margin:0 0 10px"><b>' . esc_html( $cs ) . ' chưa có '
				. 'khung ca riêng.</b> Bảng dưới đang chia giờ theo khung '
				. ( 'chung' === $nguon ? '<b>khai chung cho mọi cơ sở</b>' : '<b>mặc định của hệ</b>' )
				. ' — cửa hàng nào giờ vào ra khác khung đó thì <b>số ở đây sai</b>, và phần lệch '
				. 'sẽ rơi hết vào cột <b>Ngoài ca</b>. '
				. ( VHCC_Vai::duoc( $toi, 'lich_lam' )
					? 'Khai giờ ca thật của cửa hàng ở khối <b>Khai ca làm việc</b> ngay dưới bảng này — '
						. '<a href="#khaica">xuống đó ↓</a>'
					: 'Nhờ Cửa hàng trưởng khai giờ ca thật của cửa hàng' )
				. '.</div>';
		}
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
	 * LỆNH ĐI TRỄ — đơn xin phép của cửa hàng, và ô đặt mức trễ cho phép.
	 *
	 * Anh Thắng 27/08/2026: *"bên tài khoản cửa hàng trưởng sẽ hiện trong phần Lệnh đi trễ, cửa
	 * hàng trưởng duyệt đơn thì cảnh báo đó sẽ bỏ"*; và khi được hỏi trễ bao nhiêu phút thì kêu:
	 * *"trễ tầm bao nhiêu phút (do cửa hàng trưởng set)"*.
	 *
	 * 🔴 NGƯỠNG VÀ ĐƠN ĐỨNG CHUNG MỘT KHỐI, CỐ Ý. Hai thứ trả lời cùng một câu hỏi — "chỗ thiếu
	 *    này có đáng kêu không" — chỉ khác đường: ngưỡng là luật chung của cửa hàng, đơn là
	 *    ngoại lệ của một người một ngày. Tách ra hai màn thì người sửa ngưỡng không thấy mình
	 *    vừa làm cả chồng đơn thành thừa, và ngược lại.
	 *
	 * ⚠️ KHỐI TỰ MỞ khi có đơn đang chờ. Đơn chờ duyệt mà nằm trong một khối gập kín thì nó chờ
	 *    mãi — và người nộp đơn thì đang đứng ở cửa hàng.
	 */
	private static function the_lenh_tre( $cs, $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'lich_lam' ) ) { return; }
		if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }
		if ( ! class_exists( 'VHCC_XinTre' ) || ! method_exists( 'VHCC_XinTre', 'cho_duyet' ) ) { return; }

		$cho  = VHCC_XinTre::cho_duyet( $cs );
		$muc  = VHCC_Tre::cua( $cs );
		$ngu  = VHCC_Tre::nguon( $cs );
		$mo   = ( $cho || ( isset( $_GET['tre'] ) && '1' === (string) wp_unslash( $_GET['tre'] ) ) );

		echo '<div class="the" id="lenhtre"><details' . ( $mo ? ' open' : '' ) . '>';
		echo '<summary><b>Lệnh đi trễ</b> — '
			. ( $cho ? '<b style="color:#b45309">' . count( $cho ) . ' đơn đang chờ duyệt</b>'
				: 'không có đơn nào chờ' )
			. ' <span class="mo">(mức cho phép hiện tại: ' . (int) $muc . ' phút)</span></summary>';

		/* ---- ô đặt ngưỡng ---- */
		echo '<form method="post" class="hang" style="margin:10px 0 4px">'
			. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<input type="hidden" name="viec" value="muc_tre">' . self::o_loc()
			. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">';
		echo '<div><label for="mtre">Trễ bao nhiêu phút thì cảnh báo</label>'
			. '<input id="mtre" name="muc" type="number" min="0" max="' . VHCC_Tre::TOI_DA . '"'
			. ' value="' . esc_attr( (string) $muc ) . '" style="width:110px"></div>';
		echo '<div><button class="chinh">Lưu mức trễ</button></div>';
		echo '<div><span class="mo">Đang dùng mức '
			. ( 'rieng' === $ngu ? '<b>riêng của ' . esc_html( $cs ) . '</b>'
				: ( 'chung' === $ngu ? '<b>khai chung</b> cho mọi cơ sở'
					: '<b>mặc định</b> ' . VHCC_Tre::MAC_DINH . ' phút' ) )
			. '. Để trống rồi lưu = bỏ mức riêng.</span></div>';
		echo '</form>';
		echo '<p class="mo" style="margin:0 0 12px">Ai vào trễ quá mức này mà <b>không có đơn</b> '
			. 'thì ô ngày đó <b>vàng lên</b> trong bảng công. Số giờ vẫn giữ nguyên — đây là cảnh '
			. 'báo để anh chị hỏi lại, không phải máy tự trừ tiền. Số <b>0</b> nghĩa là trễ một '
			. 'phút cũng kêu.</p>';

		/* ---- đơn chờ duyệt ---- */
		if ( ! $cho ) {
			echo '<p class="mo">Chưa có đơn nào chờ. Nhân viên nộp đơn ở trang <b>chấm công '
				. 'online</b>, mục <b>Xin phép đi trễ</b>, <b>trước khi</b> tới cửa hàng.</p>';
			echo '</details></div>';
			return;
		}
		echo '<div class="cuon"><table class="cc"><thead><tr><th>Ngày</th><th>Nhân viên</th>'
			. '<th>Xin trễ</th><th>Lý do</th><th>Nộp lúc</th><th>Duyệt</th></tr></thead><tbody>';
		foreach ( $cho as $d ) {
			echo '<tr><td><b>' . esc_html( self::ngay_vn( (string) $d['ngay'] ) ) . '</b></td>';
			echo '<td>' . esc_html( (string) $d['ho_ten'] )
				. ' <span class="mo">' . esc_html( (string) $d['ma_nv'] ) . '</span></td>';
			echo '<td class="oc"><b>' . (int) $d['so_phut'] . '</b> phút</td>';
			echo '<td>' . esc_html( (string) $d['ly_do'] ) . '</td>';
			echo '<td class="mo">' . esc_html( (string) $d['tao_luc'] ) . '</td>';
			/* Mỗi hàng một biểu mẫu RIÊNG — hai nút cùng một hàng, mỗi nút mang việc của nó.
			   Gom cả bảng vào một form thì bấm Duyệt ở hàng ba lại gửi cả chín hàng. */
			echo '<td><form method="post" class="hang" style="gap:6px;margin:0">'
				. '<input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
				. self::o_loc()
				. '<input type="hidden" name="ccs" value="' . esc_attr( $cs ) . '">'
				. '<input type="hidden" name="don" value="' . (int) $d['id'] . '">'
				. '<button class="chinh" name="viec" value="duyet_tre">Duyệt</button>'
				. '<button class="nut-do" name="viec" value="choi_tre">Không duyệt</button>'
				. '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo" style="margin-top:8px">Duyệt xong, ô vàng của đúng ngày đó trong bảng '
			. 'công <b>thôi kêu</b> — nhưng số giờ <b>không đổi</b>, và ô ấy còn một gạch xanh dưới '
			. 'chân để anh chị vẫn nhìn ra chỗ nào là do đơn.</p>';
		echo '</details></div>';
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
		/* 🔴 CHỐT CƠ SỞ NGAY TẠI KHỐI VẼ. Khối này nay còn được vẽ trên màn Bảng công (xem
		   `the_man_cham`), nơi mã cơ sở đến từ `?ccs=` gõ tay được. `VHCC_Ca::luu` có chốt riêng
		   nên lưu thì không lọt, nhưng bày ra khung ca của cửa hàng người khác đã là rò rồi. */
		if ( '' === $cs || ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }
		$ds  = VHCC_Ca::cua( $cs );
		$ngu = VHCC_Ca::nguon_ca( $cs );

		/* Chưa khai riêng thì MỞ SẴN. Anh Thắng 28/08/2026 gửi ảnh bảng Tổng giờ theo ca của một
		   cửa hàng: *"Anh thấy ca làm nó có nè, nhưng nó đang lấy chung từ cửa hàng khác nên bị
		   sai"*. Khung ca ấy là khung MẶC ĐỊNH — cửa hàng chưa ai khai nên mượn tạm. Khối khai
		   ca thì nằm gập kín trong một màn mà Cửa hàng trưởng còn không vào được. Gập kín một
		   việc CHƯA LÀM là cách chắc chắn nhất để nó không bao giờ được làm. */
		$mo = ( 'rieng' !== $ngu )
			|| ( isset( $_GET['khaica'] ) && '1' === (string) wp_unslash( $_GET['khaica'] ) );
		echo '<div class="the" id="khaica"><details' . ( $mo ? ' open' : '' ) . '>'
			. '<summary><b>Khai ca làm việc của ' . esc_html( $cs ) . '</b> — '
			. count( $ds ) . ' ca <span class="mo">(bấm để '
			. ( $mo ? 'gập lại' : 'mở' ) . ')</span></summary>';
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
		/* Cách tính của CƠ SỞ ĐANG XEM — quyết định ô in số giờ hay in 1 công. */
		$kieu_ct = VHCC_Luong::cach_tinh( (string) $b['coSo'] );
		/* Ngưỡng "thiếu bao nhiêu phút thì vẫn coi là đủ ca" — CÙNG con số với ngưỡng đi trễ của
		   cửa hàng, vì hai câu hỏi vốn là một. Gác `class_exists` cùng thân hàm với lời gọi. */
		$nguong_tre = ( class_exists( 'VHCC_Tre' ) && method_exists( 'VHCC_Tre', 'cua' ) )
			? (int) VHCC_Tre::cua( (string) $b['coSo'] ) : 0;
		/* Đơn xin phép đi trễ của cả tháng, hỏi MỘT LƯỢT. Lưới có hơn 600 ô; để mỗi ô tự hỏi
		   một câu là 600 lượt truy vấn cho một lần mở trang. */
		$don_tre = ( class_exists( 'VHCC_XinTre' ) && method_exists( 'VHCC_XinTre', 'ban_do_thang' ) )
			? VHCC_XinTre::ban_do_thang( (string) $b['coSo'], $tt ) : array();
		/* Ô đang được chọn để sửa / bù — đọc từ chính địa chỉ, nên bấm Lùi vẫn đúng. */
		list( $sg_n, $sg_m, $sg_co ) = self::o_dang_sua( $tt );

		/* Gom [mã][hậu tố][số ngày]. Hàng `-CD` / `-TC` là HÀNG RIÊNG trong sổ công — gộp vào
		   hàng chính là mất chỗ để nhìn ra ca đêm và người tăng cường. */
		$o    = array();
		$ten  = array();
		$khong_cham = array();
		foreach ( (array) $b['hang'] as $r ) {
			$ma = (string) $r['maNV'];
			$ht = (string) $r['hauTo'];
			$o[ $ma ][ $ht ][ (int) substr( (string) $r['ngay'], 8, 2 ) ] = $r;
			if ( ! isset( $ten[ $ma ] ) || '' === $ten[ $ma ] ) { $ten[ $ma ] = (string) $r['hoTen']; }
		}
		/* 🔴 NGƯỜI CẢ THÁNG KHÔNG CÓ LƯỢT CHẤM NÀO VẪN PHẢI CÓ MỘT HÀNG.
		   Anh Thắng 26/08: *"Vẫn còn"* — khối "Chấm công bù" rời ở cuối màn. Nó bị bỏ, vì lưới
		   đã bù được ngay tại ô. Nhưng lưới cũ dựng danh sách người TỪ CHÍNH các lượt chấm, nên
		   ai cả tháng chưa bấm lần nào thì không có hàng — không có ô nào để bấm — và đó đúng là
		   người CẦN bù nhất (máy hỏng cả tháng, người mới chưa đăng vân tay).
		   Bỏ khối rời mà không vá chỗ này là bỏ mất một việc, chứ không phải dọn màn hình.
		   Nên: lấy thêm danh sách người của cơ sở từ SỔ NHÂN SỰ, ai chưa có hàng thì dựng một
		   hàng toàn dấu chấm — bấm ô nào cũng bù được.

		   ⚠️ Gác `method_exists` cùng chỗ với lời gọi. Và nếu sổ nhân sự chưa khai ai thì lưới
		      vẫn chạy y như cũ, chỉ là không có hàng nào thêm. */
		if ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ds_nhan_vien' ) ) {
			foreach ( VHCC_NhanSu::ds_nhan_vien( $toi, (string) $b['coSo'] ) as $hs ) {
				$ma_hs = trim( (string) $hs['ma_nv'] );
				if ( '' === $ma_hs || isset( $ten[ $ma_hs ] ) ) { continue; }
				$ten[ $ma_hs ] = trim( (string) $hs['ho_ten'] );
				/* Phải có hàng chính rỗng, không phải mảng rỗng: `array_keys( array() )` ra rỗng
				   thì vòng vẽ hàng chạy 0 lượt và người ấy lại biến mất. */
				$o[ $ma_hs ] = array( '' => array() );
				$khong_cham[ $ma_hs ] = true;
			}
		}
		uasort( $ten, function ( $a, $c ) { return strcasecmp( $a, $c ); } );

		/* 🔴 NGƯỜI CHỜ TRẢ VỀ NHÂN SỰ XUỐNG CUỐI BẢNG, KHÔNG BIẾN MẤT.
		   Anh Thắng 28/08/2026: *"Khi tích thì trong cửa hàng đó vẫn có, nhưng nằm phía là chờ
		   trả về nhân sự"*. Đúng: người nghỉ giữa tháng vẫn còn công của những ngày đã làm, và
		   bảng lương tháng ấy vẫn phải tra ra tên họ. Cho biến mất là bảng lương có mã mà không
		   có người. */
		$cs_luoi = (string) $b['coSo'];
		/* 🔴 TỰ TRẢ VỀ NHÂN SỰ — nhưng CHỈ khi tháng đang xem đã hết hẳn.
		   Anh Thắng 28/08/2026: *"trong tháng đó không phát sinh công, nó tự đẩy ngược về nhân
		   sự"*. Đọc câu ấy vào tháng ĐANG CHẠY thì nó luôn đúng vào ngày mùng một — và cả cửa
		   hàng bị trả về nhân sự sạch trong một buổi sáng. `VHCC_TraVe::quet()` tự giữ chốt ấy;
		   ở đây chỉ cần nói ra ai vừa được trả, vì đó là thay đổi dữ liệu xảy ra sau lưng người
		   đang xem — im lặng là sáng hôm sau họ mở bảng và thiếu người mà không hiểu vì sao. */
		$vua_tra = ( class_exists( 'VHCC_TraVe' ) && method_exists( 'VHCC_TraVe', 'quet' ) )
			? VHCC_TraVe::quet( $cs_luoi, $tt, isset( $toi['name'] ) ? $toi['name'] : '' )
			: array();
		$cho_tra = ( class_exists( 'VHCC_TraVe' ) && method_exists( 'VHCC_TraVe', 'ds_cho' ) )
			? VHCC_TraVe::ds_cho( $cs_luoi ) : array();
		if ( $cho_tra ) {
			$tren = array();
			$duoi = array();
			foreach ( $ten as $ma_s => $t_s ) {
				if ( isset( $cho_tra[ $ma_s ] ) ) { $duoi[ $ma_s ] = $t_s; }
				else { $tren[ $ma_s ] = $t_s; }
			}
			$ten = $tren + $duoi;
		}

		echo '<div class="the">';
		if ( $vua_tra ) {
			$ten_tra = array();
			foreach ( $vua_tra as $x ) {
				$ten_tra[] = $x['ma_nv'] . ( '' !== $x['ho_ten'] ? ' — ' . $x['ho_ten'] : '' );
			}
			echo '<div class="bao canh"><b>Đã trả ' . count( $vua_tra ) . ' người về nhân sự.</b> '
				. esc_html( implode( ' · ', $ten_tra ) ) . '. Tháng ' . esc_html( $tt ) . ' đã hết '
				. 'mà những người này không phát sinh công nào, và họ đã được đánh dấu '
				. '<b>chờ trả về</b>. Hồ sơ và toàn bộ công cũ <b>vẫn còn nguyên</b> — chỉ ô '
				. '<b>Cửa hàng</b> được xoá trắng, bên nhân sự xếp tiếp.</div>';
		}
		if ( ! $ten ) {
			echo '<p class="mo">Tháng ' . esc_html( $tt ) . ' chưa có dữ liệu chấm công nào ở cơ sở này, '
				. 'mà sổ nhân sự cũng chưa có ai thuộc cơ sở này. Nạp công từ .csv ở màn '
				. '<b>Bảng chấm công</b>, hoặc khai người ở màn <b>Hồ sơ &amp; tài khoản</b>.</p></div>';
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

		/* ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi — luật của `kiem-goi-cheo.php`. Thiếu hàm
		   thì lưới chạy y như trước, chỉ là không có dòng cơ sở khác. */
		$ck_ds = method_exists( 'VHCC_Cham', 'ngay_o_coso_khac' )
			? VHCC_Cham::ngay_o_coso_khac( array_keys( $ten ), (string) $b['coSo'], $tt ) : array();
		$tk_ds = method_exists( 'VHCC_Cham', 'tong_o_coso_khac' )
			? VHCC_Cham::tong_o_coso_khac( array_keys( $ten ), (string) $b['coSo'], $tt ) : array();

		$tong_cs = 0;
		foreach ( $ten as $ma => $ho_ten ) {
			$ck_nguoi = isset( $ck_ds[ strtoupper( $ma ) ] ) ? $ck_ds[ strtoupper( $ma ) ] : array();
			$hts = array_keys( $o[ $ma ] );
			sort( $hts );                       /* hàng chính ('') luôn đứng đầu */
			$tong_nguoi = 0;
			foreach ( $o[ $ma ] as $ds_ngay ) {
				foreach ( $ds_ngay as $r ) {
					/* Kiểu "có đi là được" thì TỔNG là số NGÀY CÔNG, không phải số giờ — cộng
					   giờ ở một cửa hàng trả theo ngày là ra một con số không ai dùng vào việc
					   gì, mà lại nằm ngay cột TỔNG. */
					$tong_nguoi += ( 'ngay' === $kieu_ct )
						? VHCC_Luong::cong_co_di( $r['vaoGiay'], $r['raGiay'] )
						: (int) $r['phut'];
				}
			}
			$tong_cs += $tong_nguoi;

			/* 🔴 MỘT NGƯỜI = MỘT HÀNG. Hàng `-CD` / `-TC` nay là DÒNG PHỤ TRONG Ô, không còn
			   là một hàng riêng bên dưới.
			   Anh Thắng 27/08/2026: *"ghép ... lại 1 bảng"*, rồi nhắc thêm *"với cơ sở khác ...
			   cũng nhớ ghép lại giúp anh"* — nên lưới GIỜ đi cùng luật với lưới CÔNG.
			   Vì sao đổi: một người có hàng phụ là chiếm hai hàng, nên mắt phải nhảy xuống hàng
			   dưới rồi ngược lên mới ghép được một ngày; mà cột ngày thì ở tận trên đầu bảng.
			   ⚠️ GỘP CHỖ ĐỨNG, KHÔNG GỘP CON SỐ. Giờ của hàng chính và giờ của hàng phụ vẫn là
			      hai dòng riêng trong ô, mỗi dòng mang nhãn của nó. Cộng thành một số là mất
			      chỗ để nhìn ra ca đêm và người tăng cường — đúng thứ hai hàng ấy sinh ra để
			      chỉ. Và mỗi dòng vẫn là một đường bấm RIÊNG: bấm dòng `-CD` là sửa đúng hàng
			      `-CD`, không phải sửa hàng chính. */
			$phu = array();
			foreach ( $hts as $ht ) {
				if ( '' !== $ht ) { $phu[] = $ht; }
			}
			/* 🔴 NEO ĐẶT TRÊN HÀNG CỦA NGƯỜI, KHÔNG TRÊN HÀNG SỬA.
			   Anh Thắng 27/08/2026, sau lượt chữa trước: *"bấm vào nó vẫn cứ nhảy chỗ sửa"* —
			   kèm ảnh hàng "Huỳnh Minh Nhật" bị cắt mất nửa ở mép trên.
			   Neo trên hàng SỬA thì trình duyệt kéo đúng hàng ấy lên đỉnh, và hàng của người
			   đang sửa — cùng cái ô vừa bấm — bị đẩy khuất lên trên. Người ta mở biểu mẫu ra
			   rồi mất luôn chỗ đứng: không còn thấy mình đang sửa ngày nào của ai.
			   Neo trên hàng NGƯỜI thì hàng ấy lên đỉnh và hàng sửa nằm ngay dưới — cả hai cùng
			   trong tầm mắt, đúng thứ tự mắt đọc. */
			echo '<tr' . ( ( '' !== $sg_n && 0 === strcasecmp( $ma, (string) $sg_m ) )
				? ' id="suaday"' : '' ) . '>';
			echo '<td' . ( isset( $cho_tra[ $ma ] ) ? ' class="cho-tra"' : '' ) . '>'
				. self::ten_nguoi( $ma, $ho_ten, $toi )
				. ( isset( $khong_cham[ $ma ] )
					? ' <span class="duoi" title="Cả tháng chưa có lượt chấm nào — '
						. 'bấm vào một ô để bù giờ">chưa chấm</span>' : '' )
				. self::o_cho_tra( $ma, isset( $cho_tra[ $ma ] ), $ky, $toi, $cs_luoi )
				. self::chip_coso_khac( $ck_nguoi ) . '</td>';
			$phut_phu = array();
			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				$ngay_o = sprintf( '%s-%02d', $tt, $i );
				/* Ô sáng lên khi ĐÚNG ngày ấy VÀ đúng người ấy đang mở hàng sửa — bất kể đang
				   sửa hàng chính hay một hàng phụ, vì cả hai nay nằm chung một ô. */
				$dang = false;
				if ( $ngay_o === $sg_n ) {
					foreach ( $hts as $ht_d ) {
						if ( 0 === strcasecmp( $ma . ( '' !== $ht_d ? '-' . $ht_d : '' ), (string) $sg_m ) ) {
							$dang = true;
							break;
						}
					}
				}

				$r_chinh = isset( $o[ $ma ][''][ $i ] ) ? $o[ $ma ][''][ $i ] : null;
				$c_chinh = self::o_luoi_gio_mot( $r_chinh, $ho_ten, $ds_ca, $kieu_ct, $nguong_tre,
					$don_tre );

				/* Dòng phụ: chỉ vẽ hậu tố nào NGÀY ẤY có lượt chấm. Vẽ hết mọi hậu tố cho mọi
				   ngày là mỗi ô ba dòng dấu chấm, lưới cao gấp ba mà không thêm một tin nào. */
				$duoi = '';
				foreach ( $phu as $ht_p ) {
					if ( ! isset( $o[ $ma ][ $ht_p ][ $i ] ) ) { continue; }
					$c_p = self::o_luoi_gio_mot( $o[ $ma ][ $ht_p ][ $i ], $ho_ten, $ds_ca, $kieu_ct,
						$nguong_tre, $don_tre );
					if ( null !== $c_p['phut'] ) {
						if ( ! isset( $phut_phu[ $ht_p ] ) ) { $phut_phu[ $ht_p ] = 0; }
						$phut_phu[ $ht_p ] += (int) $c_p['phut'];
					}
					/* 🔴 DÒNG PHỤ GIỮ NGUYÊN MÀU THEO CA và giữ NGUYÊN chú thích của nó.
					   Trước khi gộp, hàng `-CD` là một `<td>` riêng nên nó có nền tô theo ca và
					   có chú thích rê chuột của riêng nó. Gộp vào ô mà bỏ hai thứ ấy là ca đêm
					   mất màu — mà màu theo ca chính là thứ để lướt mắt nhận ra ai chạy ca nào,
					   và ca đêm là ca người ta cần nhận ra nhất. */
					$duoi .= '<div class="mdem' . $c_p['lop'] . '"'
						. ( '' !== $c_p['chu'] ? ' title="' . esc_attr( $c_p['chu'] ) . '"' : '' ) . '>'
						. self::o_sua( '<code>-' . esc_html( $ht_p ) . '</code> ' . $c_p['noi_tho'],
							$ngay_o, $ma . '-' . $ht_p, true, $duoc_sua, $duoc_bu )
						. '</div>';
				}

				/* 🔴 NGÀY Ở CƠ SỞ KHÁC NAY LÀ MỘT HÀNG RIÊNG, KHÔNG CÒN LÀ DÒNG XÁM TRONG Ô.
				   Anh Thắng 28/08/2026: *"khi ghép cửa hàng phụ, thì hiện ra 2 hàng chấm công
				   riêng nhé"*. Anh đúng: dòng xám nhỏ `TUTU_TP 12.6` nhét vào ô của cơ sở đang
				   xem làm hai cơ sở trộn trên MỘT dòng — đọc một hàng mà phải tự tách xem số
				   nào của ai. Xem khối "hàng riêng cho từng cơ sở phụ" ngay dưới vòng lặp này. */
				$lop_o = ( null === $r_chinh && '' === $duoi ) ? 'o' : ( 'oc' . $c_chinh['lop'] );
				$chu_o = $c_chinh['chu'];
				echo '<td class="' . $lop_o . ( $dang ? ' dang-sua' : '' ) . '"'
					. ( '' !== $chu_o ? ' title="' . esc_attr( $chu_o ) . '"' : '' ) . '>'
					. self::o_sua( $c_chinh['noi'], $ngay_o, $ma, null !== $r_chinh, $duoc_sua, $duoc_bu )
					. $duoi . '</td>';
			}
			/* TỔNG vẫn là tổng CẢ NGƯỜI (mọi hàng), y như trước — chỉ khác chỗ nó không còn phải
			   nói "gồm cả hàng dưới" nữa, vì không còn hàng dưới. Có hàng phụ thì kể ra từng
			   hậu tố mấy tiếng: đó là con số trước đây nằm ở ô TỔNG của hàng riêng. */
			echo '<td class="tong"><b>' . esc_html( 'ngay' === $kieu_ct
				? ( (int) $tong_nguoi . ' công' )
				: VHCC_Cham::chu_gio( $tong_nguoi ) ) . '</b>';
			foreach ( $phut_phu as $ht_p => $p_p ) {
				echo '<div class="mo" style="font-size:10px">-' . esc_html( $ht_p ) . ' '
					. esc_html( VHCC_Cham::chu_gio( $p_p ) ) . '</div>';
			}
			/* Anh Thắng: *"cơ sở chính bao nhiêu công, cơ sở thứ 2 bao nhiêu công"* — con số lớn
			   là cơ sở đang xem, mỗi dòng dưới là một cơ sở khác. */
			echo self::tong_coso_khac( isset( $tk_ds[ strtoupper( $ma ) ] )
				? $tk_ds[ strtoupper( $ma ) ] : array() );
			echo '</td></tr>';

			/* =============================================================================
			 * 🔴 HÀNG RIÊNG CHO TỪNG CƠ SỞ PHỤ.
			 * =============================================================================
			 * Anh Thắng 28/08/2026: *"khi ghép cửa hàng phụ, thì hiện ra 2 hàng chấm công riêng
			 * nhé"*, kèm ảnh hàng "Mai Quốc Hương" đang trộn `TUTU_TP 12.6` vào ô của cơ sở
			 * đang xem.
			 *
			 * ⚠️ HÀNG NÀY CHỈ ĐỌC, KHÔNG BẤM SỬA ĐƯỢC. Công của những ngày ấy thuộc bảng của cơ
			 *    sở kia; sửa từ đây là sửa vào bảng người khác đang quản. Muốn sửa thì mở đúng
			 *    cơ sở ấy — và đó cũng là nơi có người chịu trách nhiệm về con số.
			 *
			 * ⚠️ VÀ KHÔNG CỘNG VÀO TỔNG CỦA CƠ SỞ ĐANG XEM. Tổng của hàng phụ là tổng của RIÊNG
			 *    nó, tính bằng đơn vị của chính cơ sở ấy (giờ hay công) — hai cơ sở có thể khai
			 *    hai cách tính khác nhau.
			 */
			$cs_phu = array();
			foreach ( (array) $ck_nguoi as $i_ck => $x_ck ) {
				$c_ck = trim( (string) $x_ck['coso'] );
				if ( '' === $c_ck ) { continue; }
				$cs_phu[ $c_ck ][ $i_ck ] = $x_ck;
			}
			ksort( $cs_phu );
			foreach ( $cs_phu as $ten_cs_p => $ngay_cs_p ) {
				$kieu_p = VHCC_Luong::cach_tinh( $ten_cs_p );
				$tong_p = 0;
				echo '<tr class="hang-phu"><td><span class="mo">↳ cũng làm ở</span> <b>'
					. esc_html( $ten_cs_p ) . '</b>';
				echo '<div class="mo" style="font-size:10.5px">' . esc_html( $ho_ten ) . '</div></td>';
				for ( $i_p = 1; $i_p <= $so_ngay; $i_p++ ) {
					if ( ! isset( $ngay_cs_p[ $i_p ] ) ) { echo '<td class="o">·</td>'; continue; }
					$x_p  = $ngay_cs_p[ $i_p ];
					$ng_p = $tt . '-' . str_pad( (string) $i_p, 2, '0', STR_PAD_LEFT );
					$p_p  = ( null === $x_p['phut'] || '' === $x_p['phut'] ) ? null : (int) $x_p['phut'];
					if ( 'ngay' === $kieu_p ) {
						$c_p     = ( null !== $p_p && '' !== trim( (string) $x_p['ra'] ) ) ? 1 : 0;
						$tong_p += $c_p;
						$so_p    = '<b>' . (int) $c_p . '</b>';
					} else {
						$tong_p += (int) $p_p;
						$so_p    = ( null === $p_p ) ? '?' : '<b>' . self::so_vp( round( $p_p / 60, 1 ) ) . '</b>';
					}
					$chu_p = self::ngay_vn( $ng_p ) . ' · ' . $ho_ten
						. "\n" . 'chấm ở cơ sở ' . $ten_cs_p
						. "\n" . ( '' !== $x_p['vao'] ? $x_p['vao'] : '—' ) . ' → '
						. ( '' !== $x_p['ra'] ? $x_p['ra'] : '—' )
						. "\n" . VHCC_Cham::chu_gio( $p_p )
						. "\n" . '⚠ Hàng CHỈ ĐỌC. Công của ngày này thuộc bảng của cơ sở '
						. $ten_cs_p . ' — muốn sửa thì mở đúng cơ sở ấy.';
					echo '<td class="oc" title="' . esc_attr( $chu_p ) . '">' . $so_p . '</td>';
				}
				echo '<td class="tong"><b>' . esc_html( 'ngay' === $kieu_p
					? ( (int) $tong_p . ' công' )
					: VHCC_Cham::chu_gio( $tong_p ) ) . '</b>'
					. '<div class="mo" style="font-size:10px">không cộng vào tổng trên</div></td></tr>';
			}
			/* Hàng sửa nội tuyến: mở ngay dưới hàng của ĐÚNG người vừa bấm — dù bấm dòng chính
			   hay một dòng phụ, vì cả hai nay là một hàng. Mã truyền xuống vẫn là mã ĐẦY ĐỦ đọc
			   từ địa chỉ, nên sửa vẫn ăn đúng hàng `-CD`. */
			if ( '' !== $sg_n ) {
				foreach ( $hts as $ht_s ) {
					if ( 0 === strcasecmp( $ma . ( '' !== $ht_s ? '-' . $ht_s : '' ), (string) $sg_m ) ) {
						self::hang_sua( $so_ngay + 2, (string) $b['coSo'], $sg_n,
							(string) $sg_m, $sg_co, $ky, $toi );
						break;
					}
				}
			}
		}
		echo '<tr class="tong"><td>' . count( $ten ) . ' người</td>';
		echo '<td colspan="' . (int) $so_ngay . '"></td>';
		echo '<td><b>' . esc_html( 'ngay' === $kieu_ct
			? ( (int) $tong_cs . ' công' )
			: VHCC_Cham::chu_gio( $tong_cs ) ) . '</b></td></tr>';
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

		if ( 'ngay' === $kieu_ct ) {
			/* 🔴 NÓI RA ĐƠN VỊ NGAY DƯỚI LƯỚI. Một bảng toàn số 1 mà không có câu nào giải
			   thích thì người đọc tưởng máy hỏng — nhất là người vừa quen nhìn số giờ. */
			echo '<p class="mo" style="margin-top:8px"><b>' . esc_html( (string) $b['coSo'] )
				. '</b> đang tính <b>CÓ ĐI LÀ ĐƯỢC</b>: mỗi ô là <b>số công</b> — có đủ giờ vào '
				. 'và giờ ra thì tính <b>1 công</b>, không quy ra số giờ. Thiếu giờ ra thì '
				. '<b>không tính</b> (rê chuột lên ô để xem giờ thật). Đổi kiểu tính ở tab '
				. '<b>Cấu hình</b> → khối <b>Cách tính công của từng cơ sở</b>.</p>';
		}
		echo '<p class="mo" style="margin-top:8px">Ô là <b>số giờ làm</b> của ngày đó (giờ ra trừ giờ '
			. 'vào) · dấu <b>·</b> = không có dữ liệu chấm công · '
			. '<span class="k hong">?</span> = thiếu giờ ra (quên bấm lúc về) · '
			. '<span class="k hong">—</span> = giờ ra sớm hơn giờ vào, dấu hiệu ghi sai.'
			. '<br>Dòng nhỏ <b><code>-CD</code></b> / <b><code>-TC</code></b> nằm <b>ngay trong ô</b> '
			. 'là hàng riêng của người đó (ca đêm · tăng cường) — mỗi người chỉ một hàng, không '
			. 'phải tìm xuống hàng dưới nữa. Bấm thẳng dòng nhỏ ấy là sửa đúng hàng ấy. '
			. 'TỔNG đã gồm cả mấy dòng nhỏ, và kể ra bên dưới mỗi hậu tố mấy tiếng.'
			. '<br>Dòng nhỏ <b>nền xám nghiêng</b> (VD <i>FF_SC 8</i>) là ngày người ấy chấm ở '
			. '<b>cơ sở khác</b> — bày ra để ô ấy khỏi trông như ngày nghỉ. '
			. '<b>KHÔNG cộng vào cột TỔNG ở đây</b>: lương tính theo cơ sở, công của ngày ấy '
			. 'thuộc bảng của cơ sở kia.'
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

		/* 🔴 HÀNG BỊ BỎ VÌ HẬU TỐ PHẢI ĐƯỢC KÊU LÊN.
		   Anh Thắng 27/08/2026: *"đã ghép nhưng sao bảng phụ không hiện ra"* — cả sổ `SETUP_VP`
		   biến mất khỏi lưới vì mã trong đó mang hậu tố engine này không nhận, và không có một
		   dòng nào nói ra. Bảng vẫn đầy số, chỉ là thiếu hẳn một cơ sở. */
		if ( ! empty( $b['boHauTo'] ) ) {
			$bh = array();
			$so_bh = 0;
			foreach ( (array) $b['boHauTo'] as $ht_b => $so_b ) {
				$bh[] = '<code>-' . esc_html( $ht_b ) . '</code> (' . (int) $so_b . ' hàng)';
				$so_bh += (int) $so_b;
			}
			echo '<div class="bao canh"><b>' . (int) $so_bh . ' hàng KHÔNG vào bảng này</b> vì mang '
				. 'hậu tố nhiệm vụ khác: ' . implode( ' · ', $bh ) . '.<br>'
				. '<code>-TT</code> (Thu Tiền) và <code>-TG</code> (Trực Ghế) là nhiệm vụ của khối '
				. 'MTD — chúng có bảng lương riêng, kéo vào đây là tính hai lần. Hậu tố lạ khác thì '
				. 'gần như chắc là gõ sai lúc nạp .csv: sửa mã trong tệp rồi nạp lại.</div>';
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

		/* ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi — luật của `kiem-goi-cheo.php`. */
		$ma_ds = array();
		foreach ( $rows as $e_m ) { $ma_ds[] = (string) $e_m['ma']; }
		$ck_ds = method_exists( 'VHCC_Cham', 'ngay_o_coso_khac' )
			? VHCC_Cham::ngay_o_coso_khac( $ma_ds, (string) $b['station'], $tt ) : array();
		$tk_ds = method_exists( 'VHCC_Cham', 'tong_o_coso_khac' )
			? VHCC_Cham::tong_o_coso_khac( $ma_ds, (string) $b['station'], $tt ) : array();

		$lech = 0;
		foreach ( $rows as $e ) {
			$ma  = (string) $e['ma'];
			$ngd = isset( $o[ $ma ] ) ? $o[ $ma ] : array();
			$ck_nguoi = isset( $ck_ds[ strtoupper( $ma ) ] ) ? $ck_ds[ strtoupper( $ma ) ] : array();

			echo '<tr' . ( ( '' !== $sg_n && 0 === strcasecmp( $ma, (string) $sg_m ) )
				? ' id="suaday"' : '' ) . '><td>' . self::ten_nguoi( $ma, $e['ten'], $toi )
				. ( ! empty( $e['laKeToan'] ) ? ' <span class="duoi">KT</span>' : '' )
				. self::chip_coso_khac( $ck_nguoi ) . '</td>';
			$cong = 0.0;
			$co_dem = false;
			for ( $i = 1; $i <= $so_ngay; $i++ ) {
				$ngay_o = sprintf( '%s-%02d', $tt, $i );
				$dang   = ( $ngay_o === $sg_n && 0 === strcasecmp( $ma, (string) $sg_m ) );
				if ( ! isset( $ngd[ $i ] ) ) {
					/* Ngày ấy người này đứng ở CƠ SỞ KHÁC. Không nói ra thì ô này là dấu chấm,
					   và dấu chấm ở đây nghĩa là "không có dữ liệu chấm công" — sai hẳn: có,
					   chỉ là ở chỗ khác. */
					$ngoai = isset( $ck_nguoi[ $i ] )
						? self::o_coso_khac( $ck_nguoi[ $i ], $e['ten'], $ngay_o ) : '';
					echo '<td class="o' . ( $dang ? ' dang-sua' : '' ) . '">'
						. self::o_sua( '·', $ngay_o, $ma, false, $duoc_sua, $duoc_bu )
						. $ngoai . '</td>';
					continue;
				}
				$d = $ngd[ $i ];
				if ( $d['congDem'] || '' !== $d['h2vao'] || '' !== $d['h2ra'] ) { $co_dem = true; }
				$cong += (float) $d['tong'];

				/* Màu = LÝ DO, không phải trang trí. Đỏ là chỗ CÓ giờ mà KHÔNG ra công — đúng
				   thứ cần soi. */
				/* 🔴 CÓ GIỜ VÀO MÀ KHÔNG CÓ GIỜ RA -> Ô ĐỎ, kèm dấu `?`.
				   Anh Thắng 27/08/2026, ngay khi bảo bỏ hai bảng phụ: *"vì bảng này khi có giờ
				   vào mà không có giờ ra, thì sẽ đỏ ô đó là được"*. Đó chính là thứ làm cho hai
				   bảng kia thành thừa: người ta mở chúng ra để đi tìm đúng mấy ngày này.
				   Ngày quên bấm lúc về vẫn RA CÔNG (engine tính từ phần giờ nằm trong khung), nên
				   ô có số trông y hệt một ngày bình thường — không đánh dấu là nó lẫn vào giữa
				   ba mươi ô khác và không ai đi tìm nữa.
				   ⚠️ Xét CẢ HÀNG 2: ca đêm quên bấm ra cũng là quên bấm. */
				/* ⚠️ THIẾU MỘT ĐẦU GIỜ, chiều nào cũng vậy. Bản trước chỉ bắt "có vào, không ra"
				   — nên ô ca đêm `05:51 → —` (dấu vết của lượt bấm lúc RA) không đỏ, không có
				   dấu ?, và người ta không có gì để đi tìm. */
				$thieu_ra = ( ( '' !== $d['vao'] ) !== ( '' !== $d['ra'] ) )
					|| ( ( '' !== $d['h2vao'] ) !== ( '' !== $d['h2ra'] ) );

				/* ⚠️ KHÔNG thêm `demChuaDuCap` vào đây: `$thieu_ra` đã bao trọn nó. Ca đêm thiếu
				   cặp nghĩa là đúng một trong `h2vao`/`h2ra` rỗng, mà đó chính là vế thứ hai của
				   `$thieu_ra`. Thêm vào là một chốt KHÔNG BAO GIỜ chạy — đã phá thử để thấy:
				   bỏ nó đi mà bộ thử vẫn xanh. Chốt chết đứng cạnh chốt thật thì lần sau có
				   người sửa `$thieu_ra` sẽ tưởng ô vẫn còn được chốt kia đỡ. */
				$lop = '';
				if ( $thieu_ra || ! empty( $d['caLa'] ) || ! empty( $d['demThieuGio'] ) ) { $lop = ' hong'; }
				elseif ( ! empty( $d['ktCnNghi'] ) )   { $lop = ' vang'; }
				elseif ( $d['congDem'] )               { $lop = ' tim'; }
				elseif ( $d['congTangCa'] )            { $lop = ' luc'; }

				/* 🔴 CA ĐÊM NẰM TRONG CHÍNH Ô ẤY, không phải một hàng thứ hai.
				   Anh Thắng 27/08/2026: *"ghép ... lại 1 bảng"* — và chọn kiểu hai dòng nhỏ
				   trong cùng ô. Trước đây mỗi người có làm đêm là chiếm HAI hàng, nên một cơ
				   sở 24 người ra 40-mấy hàng và mắt phải nhảy xuống hàng dưới rồi ngược lên để
				   ghép một ngày. Nay một người đúng một hàng.
				   ⚠️ GỘP CHỖ ĐỨNG, KHÔNG GỘP CON SỐ. Số công ngày và số công đêm vẫn là hai
				      dòng riêng trong ô: cộng chúng thành một số là xoá mất chỗ để nhìn ra
				      "đêm đó có làm mà không được công". Đúng thứ khối này sinh ra để soi.
				   Hai NGÀY khác nhau trong cùng một hàng: đêm 04/08 hiện 🌙 ở ô ngày 4, còn
				   công của nó hiện SỐ ở ô ngày 5 — ca đêm cho công sang hôm sau. */
				$dem_o = '';
				$lam_d = ( '' !== $d['h2vao'] || '' !== $d['h2ra'] );
				if ( $d['congDem'] ) {
					$dem_o = '🌙' . self::so_vp( $d['congDem'] );
				} elseif ( ! empty( $d['demThieuGio'] ) || ! empty( $d['demChuaDuCap'] ) ) {
					/* 🔴 `🌙0` — CÓ LÀM ĐÊM MÀ KHÔNG RA CÔNG. Hai nguyên do khác nhau (không đủ
					   giờ tối thiểu · thiếu một đầu giờ) nhưng hậu quả trên bảng lương giống
					   hệt: đêm ấy 0 công. `🌙` trơn nghĩa là "đêm đó có làm" và người đọc hiểu
					   là công nằm ở ô ngày mai — để nó ở đây là hứa một công không tồn tại. */
					$dem_o = '🌙0';
				} elseif ( $lam_d ) {
					$dem_o = '🌙';
				}
				$ngoai = isset( $ck_nguoi[ $i ] )
					? self::o_coso_khac( $ck_nguoi[ $i ], $e['ten'], $ngay_o ) : '';
				/* 🔴 NGÀY ĐẾN TỪ CƠ SỞ PHỤ ĐÃ GHÉP: một nhãn nhỏ, KHÁC HẲN dòng xám "cơ sở khác".
				   Con số này ĐÃ cộng vào TỔNG (nó là một phần của chính bảng này), nên nhãn chỉ
				   để soi lại được — đừng để nó trông giống dòng xám vốn KHÔNG cộng vào. Hai thứ
				   trông giống nhau là người đọc lại đi trừ ra. */
				if ( ! empty( $d['tuCoSo'] ) ) {
					$ngoai .= '<div class="mghep" title="' . esc_attr( 'Ngày này chấm ở ' . $d['tuCoSo']
						. ' — cơ sở ấy đã GHÉP vào bảng này, nên công của nó ĐÃ nằm trong cột TỔNG.' )
						. '">' . esc_html( $d['tuCoSo'] ) . '</div>';
				}
				if ( '' !== $dem_o ) {
					/* Chú thích của ô nay phải nói CẢ HAI phần — hàng ca đêm không còn ô riêng
					   để mang chú thích của nó nữa. */
					/* Anh Thắng 27/08/2026: *"tách ra 2 ô, bằng gạch ngang, cho dễ nhìn"*.
					   `title` là văn bản THUẦN — không tô màu, không kẻ khung được. Nên phần ngăn
					   phải là một gạch DÀI THẬT thì mắt mới tách được hai khối chữ; mấy dấu gạch
					   ngắn kiểu "— ca đêm —" chìm ngay vào đám chữ quanh nó. */
					$chu_o = self::chu_o_vp( $d, $e['ten'] )
						. "\n────────────────\n🌙 CA ĐÊM\n"
						. self::chu_dem_vp( $d, $e['ten'] );
				} else {
					$chu_o = self::chu_o_vp( $d, $e['ten'] );
				}
				echo '<td class="oc' . $lop . ( $dang ? ' dang-sua' : '' ) . '" title="'
					. esc_attr( $chu_o ) . '">'
					. self::o_sua(
						( $d['tong'] ? '<b>' . self::so_vp( $d['tong'] ) . '</b>'
							: '<span class="chu-hong">0</span>' )
						/* Dấu `?` ngay cạnh số: màu đỏ nói "có chuyện", dấu này nói "chuyện gì".
						   Chỉ tô đỏ thì ba nguyên do đỏ khác nhau trông giống hệt nhau. */
						. ( $thieu_ra ? '<span class="chu-hong"> ?</span>' : '' )
						. ( '' !== $dem_o
							? '<div class="mdem' . ( ! empty( $d['demThieuGio'] ) || ! empty( $d['demChuaDuCap'] )
								? ' chu-hong' : '' )
								. '">' . esc_html( $dem_o ) . '</div>' : '' ),
						$ngay_o, $ma, true, $duoc_sua, $duoc_bu )
					. $ngoai . '</td>';
			}
			/* 🔴 Ô đối chiếu. Lưới cộng ra khác bảng tổng của engine = một trong hai chỗ sai,
			   phải kêu ngay chứ không im lặng in ra hai con số. */
			$cong = round( $cong, 2 );
			$khop = ( abs( $cong - (float) $e['tong'] ) < 0.005 );
			if ( ! $khop ) { $lech++; }
			echo '<td class="tong' . ( $khop ? '' : ' chu-hong' ) . '"><b>' . self::so_vp( $cong ) . '</b>'
				. ( $khop ? '' : ' ≠ ' . self::so_vp( $e['tong'] ) )
				/* Anh Thắng: *"tổng công ngày đêm hiện vô cuối hàng nhân viên luôn"*. */
				. self::tach_cong( $e )
				. self::tong_coso_khac( isset( $tk_ds[ strtoupper( $ma ) ] )
					? $tk_ds[ strtoupper( $ma ) ] : array() ) . '</td>';
			echo '</tr>';
			if ( '' !== $sg_n && 0 === strcasecmp( $ma, (string) $sg_m ) ) {
				self::hang_sua( $so_ngay + 2, (string) $b['station'], $sg_n, $ma, $sg_co, $ky, $toi );
			}

		}
		echo '</tbody></table></div>';

		echo '<p class="mo" style="margin-top:8px">Ô là <b>số công</b> của ngày đó · dấu '
			. '<b>·</b> = không có dữ liệu chấm công · '
			. '<span class="k luc">có tăng ca</span> <span class="k tim">có công đêm</span> '
			. '<span class="k vang">kế toán chấm chủ nhật</span> '
			. '<span class="k hong">có giờ nhưng KHÔNG ra công</span> '
			. '<span class="k hong">? = có giờ vào mà THIẾU giờ ra</span>'
			. '<br>Dòng nhỏ <b>🌙</b> nằm <b>ngay trong ô</b> là phần ca đêm của ngày đó — mỗi '
			. 'người chỉ một hàng. <b>🌙</b> một mình = đêm đó CÓ làm · <b>🌙 kèm số</b> = công '
			. 'đêm được tính vào ngày đó (ca đêm đêm trước cho công sang hôm sau) · '
			. '<b class="chu-hong">🌙0</b> = đêm đó CÓ làm mà KHÔNG ra công — hoặc không đủ giờ '
			. 'tối thiểu, hoặc <b>thiếu một đầu giờ</b> (chỉ bấm vào, hoặc chỉ bấm ra). '
			. 'Thiếu một đầu giờ thì <b>không tính công đêm</b>: một lần bấm lẻ không chứng minh '
			. 'được có ca đêm. Bù nốt giờ còn thiếu (bấm thẳng vào ô) là ca ấy tính đủ ngay. '
			. 'Số lớn phía trên đã là TỔNG công của ngày, gồm cả phần đêm.'
			. '<br>Dòng nhỏ <b>nền xám nghiêng</b> (VD <i>FF_SC 8</i>) là ngày người ấy chấm ở '
			. '<b>cơ sở khác</b> — bày ra để ô ấy khỏi trông như ngày nghỉ. '
			. '<b>KHÔNG cộng vào cột TỔNG ở đây</b>: lương tính theo cơ sở, công của ngày ấy '
			. 'thuộc bảng của cơ sở kia.'
			. '<br>Cột <b>TỔNG</b>: con số lớn là <b>tổng công cả tháng</b>; dòng nhỏ dưới nó tách '
			. 'ra <b>ngày</b> · <b>🌙 đêm</b> · <b>TC</b> (tăng ca) · <b>bù</b> — chỉ hiện phần nào '
			. 'khác 0, và không hiện gì nếu cả tháng chỉ có công ngày.'
			. '<br>Nhãn <b>nền xanh nhạt</b> (VD <i>SETUP_VP</i>) thì ngược lại: cơ sở ấy đã '
			. '<b>ghép vào bảng này</b> (ca đêm chấm ở mã riêng chẳng hạn), nên công của nó '
			. '<b>ĐÃ nằm trong cột TỔNG</b> — nhãn chỉ để soi lại được nó đến từ đâu.';
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
		/* Nói ra ngay dòng đầu của chú thích, đừng để lẫn giữa mấy dòng khác: đây là ngày phải
		   đi bù, và bù được thì lương mới đúng. */
		if ( '' !== $d['vao'] && '' === $d['ra'] ) {
			$c[] = '⚠ CÓ giờ vào mà KHÔNG có giờ ra — quên bấm lúc về, cần bù';
		}
		if ( '' !== $d['h2vao'] && '' === $d['h2ra'] ) {
			$c[] = '⚠ hàng 2 (ca đêm) có giờ vào mà KHÔNG có giờ ra — quên bấm lúc về, cần bù';
		}
		/* CHIỀU NGƯỢC LẠI cũng phải bắt: chỉ có giờ RA là quên bấm lúc VÀO. Ảnh anh Thắng gửi
		   27/08 chính là ô ấy — ca đêm `05:51 → —` đọc xuôi trông như quên bấm về, nhưng một ca
		   đêm bắt đầu lúc 5 giờ 51 sáng thì nhiều phần là dấu vết của LƯỢT BẤM LÚC RA. Không bắt
		   chiều này thì ô không đỏ, không có dấu ?, và ca đêm ấy im lặng mất công. */
		if ( '' === $d['h2vao'] && '' !== $d['h2ra'] ) {
			$c[] = '⚠ hàng 2 (ca đêm) có giờ ra mà KHÔNG có giờ vào — quên bấm lúc vào, cần bù';
		}
		if ( '' === $d['vao'] && '' !== $d['ra'] ) {
			$c[] = '⚠ CÓ giờ ra mà KHÔNG có giờ vào — quên bấm lúc vào, cần bù';
		}
		if ( ! empty( $d['caLa'] ) )       { $c[] = '⚠ hàng 2 nằm trong ca ngày → KHÔNG tính'; }
		if ( ! empty( $d['demThieuGio'] ) ) {
			$c[] = '⚠ ca đêm ' . self::so_vp( $d['gioDemThuc'] ) . 'h < mức tối thiểu';
		}
		if ( ! empty( $d['ktCnNghi'] ) )   { $c[] = '⚠ kế toán chấm chủ nhật → 0 công'; }
		return implode( "\n", $c );
	}

	/**
	 * Neo tới hàng của một người trong bảng hồ sơ.
	 *
	 * ⚠️ Tách riêng vì nó phải khớp CHÍNH XÁC với `id` đặt trên `<tr>` — hai chỗ dựng cùng một
	 *    chuỗi bằng hai đoạn mã chép tay là sớm muộn lệch nhau, và lệch thì neo trỏ vào hư không:
	 *    trình duyệt lặng lẽ đứng yên ở đỉnh, y như chưa chữa gì.
	 * ⚠️ `esc_url()` đã chạy ở nơi gọi cho phần địa chỉ; phần neo nối SAU nên phải tự sạch — mã
	 *    NV chỉ còn chữ và số nên không có gì để thoát.
	 */
	private static function neo_hs( $ma ) {
		return '#hs-' . preg_replace( '/[^A-Za-z0-9]+/', '_', (string) $ma );
	}

	/**
	 * TÊN NGƯỜI TRONG LƯỚI — bấm vào là sang thẳng hồ sơ của đúng người ấy.
	 *
	 * Anh Thắng 27/08/2026, chỉ vào cột Nhân viên: *"Khi bấm vào thông tin nhân sự chỗ này, nó sẽ
	 * nhảy sang tab thông tin nhân sự đó, để tiện chỉnh thông tin nhân, set cơ sở, hay liên kết
	 * các web khác lại theo cấu hình"*.
	 *
	 * 🔴 ĐƯỜNG ĐI VỐN DÀI VÀ DỄ LẠC. Đang xem lưới, thấy một người sai cơ sở hoặc thiếu lương cơ
	 *    bản, muốn sửa thì phải: sang tab Hồ sơ → gõ tên vào ô tìm → dò trong danh sách vài trăm
	 *    người → bấm Sửa. Bốn bước, và bước "gõ tên" là bước hay lạc nhất: tên trùng, tên có dấu
	 *    gõ khác nhau, người ta gõ "Thắng" mà sổ ghi "Thăng".
	 *    Cái tên đang nằm sẵn trước mắt CHÍNH LÀ khoá — dùng nó, đừng bắt gõ lại.
	 *
	 * ⚠️ CHỈ VẼ LIÊN KẾT CHO NGƯỜI MỞ ĐƯỢC HỒ SƠ. Vẽ cho cả người không có quyền thì bấm vào chỉ
	 *    nhận một câu chối — mà cái liên kết thì cứ nằm đó mời gọi mỗi ngày. Cùng luật với cột
	 *    dọc và thẻ Truy cập nhanh.
	 * ⚠️ Mã rỗng thì trả tên trơn: không có khoá thì không có chỗ để tới.
	 */
	private static function ten_nguoi( $ma, $ten, $toi, $duoi = '' ) {
		$ten_h = esc_html( (string) $ten );
		$ma    = trim( (string) $ma );
		if ( '' === $ma || ! VHCC_Vai::duoc( $toi, 'ho_so' ) ) { return $ten_h . $duoi; }
		$url = add_query_arg( array( 'man' => 'ho_so', 'sua' => $ma ), self::url() );
		return '<a class="ten-nv" href="' . esc_url( $url ) . '" title="'
			. esc_attr( 'Mở hồ sơ ' . $ma . ' — sửa cơ sở, bộ phận, lương cơ bản, PIN' ) . '">'
			. $ten_h . '</a>' . $duoi;
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
		/* Anh Thắng 27/08/2026: *"thiếu có thể do bấm nhầm, không được cộng vào nhé, trừ khi có
		   thêm giờ ra"*. Câu chú thích phải nói ĐÚNG cái engine vừa làm — bản trước ghi "vẫn
		   tính đủ" đúng lúc engine cộng đủ; nay engine KHÔNG cộng, mà câu ấy còn nguyên thì màn
		   hình đang nói dối người kiểm lương. */
		if ( ! empty( $d['demChuaDuCap'] ) ) {
			$c[] = '⚠ ca đêm THIẾU một đầu giờ → KHÔNG tính công đêm. Bù nốt giờ còn thiếu thì '
				. 'tính đủ ngay (bấm vào ô này).';
		}
		return implode( "\n", $c );
	}

	private static function ngay_vn( $ngay ) {
		$p = explode( '-', (string) $ngay );
		return ( 3 === count( $p ) ) ? $p[2] . '/' . $p[1] . '/' . $p[0] : (string) $ngay;
	}

	/**
	 * SỔ NHẬT KÝ GIỜ CÔNG — mọi lượt bù và mọi lượt sửa đè của tháng đang xem.
	 *
	 * 🔴 KHỐI "CHẤM CÔNG BÙ" RỜI ĐÃ BỎ (anh Thắng 26/08/2026: *"Vẫn còn"*, sau khi khối "Sửa giờ
	 *    công" rời bị bỏ ở lượt trước). Bù và sửa nay làm ngay TẠI Ô trong lưới cả tháng: bấm ô
	 *    trống → bù, bấm ô có giờ → sửa. Không phải gõ lại ngày và mã cho từng lượt.
	 *
	 * ⚠️ Trước khi bỏ, khối rời còn giữ MỘT việc mà lưới không làm được: bù cho người cả tháng
	 *    chưa chấm lần nào — lưới cũ dựng hàng từ chính các lượt chấm nên họ không có hàng, không
	 *    có ô để bấm. Việc ấy đã vá ở `ve_luoi_gio`: lưới kéo thêm người từ sổ nhân sự, ai chưa
	 *    chấm lần nào vẫn có một hàng toàn dấu chấm, gắn nhãn "chưa chấm". Bỏ một khối mà không
	 *    vá chỗ đó là bỏ mất một việc, chứ không phải dọn màn hình.
	 *
	 * 🔴 MỘT SỔ CHO CẢ HAI VIỆC — bù và sửa đè cùng ghi vào bảng `cham_bu`.
	 *    Tách làm hai sổ thì người soát phải mở hai chỗ mới dựng lại được chuyện gì đã xảy ra với
	 *    một ngày công; mà thứ họ cần biết là "ngày này ai đã động vào, mấy lần", không phải "ai
	 *    đã bù" và "ai đã sửa" thành hai câu chuyện rời nhau.
	 */
	private static function the_nhat_ky_gio( $cs, $tt, $ky, $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'cham_bu' ) && ! VHCC_Vai::duoc( $toi, 'sua_gio' ) ) { return; }
		echo '<div class="the" id="bucong"><h2>Đã động vào giờ công tháng này</h2>';
		echo '<p class="mo">Mỗi lượt <b>bù</b> (điền ô còn trống) và mỗi lượt <b>sửa đè</b> đều vào '
			. 'sổ này — ai làm · cho ai · ngày nào · giờ cũ ra sao · vì sao — và <b>không xoá được</b>. '
			. 'Bù và sửa làm ngay tại ô trong <a href="#luoithang"><b>Lưới cả tháng</b></a>: ô '
			. '<b>trống</b> thì bù, ô <b>có giờ</b> thì sửa.</p>';
		echo '<p class="mo">⚠️ Không tự bù cho mình được, kể cả Admin — nhờ người khác bù giúp. '
			. 'Bù công là đổi thẳng ra tiền, nên chỗ này không để hở.</p>';

		$nk = VHCC_Bu::ds_nhat_ky( $toi, $cs, $tt );
		if ( $nk ) {
			echo '<p class="mo"><b>' . count( $nk ) . '</b> lượt trong tháng ' . esc_html( $tt ) . '.</p>';
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
		} else {
			echo '<p class="mo">Tháng này chưa ai bù hay sửa giờ công ở cơ sở này.</p>';
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

	/* ===========================================================================
	 *  MÀN CẤU HÌNH — khai một lần rồi thôi
	 * ---------------------------------------------------------------------------
	 *  Anh Thắng 26/08/2026: *"cái này cho qua cấu hình đi, vì chỗ này là bảng công mà hiện
	 *  phân quyền cơ sở làm gì trong này đâu"* và *"Cho qua tab cấu hình luôn nhé"*.
	 *
	 *  🔴 BỐN KHỐI Ở ĐÂY ĐỀU ĐỔI CÁCH TÍNH RA TIỀN của cả một cơ sở. Chúng từng nằm trên màn
	 *     Bảng công — màn người ta mở hằng ngày chỉ để ĐỌC. Thao tác hằng ngày mà cứ lướt ngang
	 *     qua mấy cái nút đổi tiền thì sớm muộn có người bấm nhầm, và cái bấm nhầm ấy không kêu
	 *     lên ngay: nó chỉ lộ ra ở bảng lương cuối tháng.
	 * =========================================================================== */
	/* ===========================================================================
	 *  MÀN GIỜ & LƯƠNG
	 * ---------------------------------------------------------------------------
	 *  🔴 MÀN NÀY CHỈ VẼ. Mọi phép tính tiền nằm ở `VHCC_Luong::bang_cong_va_luong()` — đúng
	 *  hàm mà màn wp-admin vẫn gọi. Không có một dòng công thức nào ở đây, và sẽ không bao giờ
	 *  có: hai nơi cùng tính lương là hai nơi sớm muộn ra hai con số, mà không ai biết tin cái
	 *  nào. Ghép ra web là đổi CÁCH BÀY, không đổi nghiệp vụ.
	 *
	 *  ⚠️ Ba kiểu cơ sở, ba bảng khác nhau — `tho` (chưa có công thức) · `mtd` (máy tự động,
	 *     tính theo công + giờ) · `vp` (văn phòng, tính theo ngày công). Không gộp làm một:
	 *     mỗi kiểu có những cột mà kiểu kia không có nghĩa gì.
	 * ======================================================================== */
	private static function the_khoi_luong( $toi, $cs, $th ) {
		/* 🔴 KHÔNG CÓ Ô LỌC RIÊNG. Cơ sở và tháng nhận thẳng từ màn Bảng công — đó là toàn bộ
		   điểm của việc gộp. Dựng thêm một ô chọn ở đây là hai ô cho cùng một thứ, và người ta
		   sẽ chọn lệch: bảng trên nói về cơ sở này, bảng dưới nói về cơ sở kia, mà cả hai đều
		   trông đúng. */
		if ( '' === $cs ) { return; }

		/* 🔴 GÁC RIÊNG BẰNG `luong`. Màn Bảng công mở cho `cong_coso` (bậc Cửa hàng trưởng) —
		   nếu khối này đi theo cửa ấy thì gộp trang xong là mở bảng lương của cả cơ sở cho
		   người cùng cơ sở. Gộp CHỖ BÀY, không gộp QUYỀN. */
		if ( ! VHCC_Vai::duoc( $toi, 'luong' ) ) { return; }

		/* Chốt cơ sở: `bang_cong_va_luong()` không nhận người dùng nên nó không gác gì.
		   ⚠️ Nhánh này hiện chưa từng chối ai (bậc `luong` = 4 đã có `cong_tat_ca` = 3), nhưng
		      giữ — nó chặn ngay ngày nào quyền `luong` được nới xuống. Bộ thử canh quan hệ ấy. */
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) { return; }

		$r = VHCC_Luong::bang_cong_va_luong( $cs, $th );

		echo '<div class="the"><details>';
		/* 🔴 TÊN PHẢI THEO NỘI DUNG. Anh Thắng 27/08/2026: *"chưa bỏ bảng này đi à em"* — sau khi
		   bốn cột công (ngày · tăng ca · đêm · bù) dời lên cột TỔNG của lưới, khối này không còn
		   là "bảng công" nữa: nó chỉ còn phần QUY RA TIỀN. Giữ tên cũ là mời người ta mở ra tìm
		   công rồi thấy một bảng khác hẳn thứ mình định tìm. */
		echo '<summary><b>Lương</b> — ' . esc_html( $cs ) . ' · tháng '
			. esc_html( $th ) . ( empty( $r['ok'] ) ? '' : ' · cách tính <code>'
				. esc_html( $r['kieu'] ) . '</code>' ) . '</summary>';
		if ( empty( $r['ok'] ) ) {
			echo '<div class="bao loi">' . esc_html( $r['error'] ) . '</div></details></div>';
			return;
		}
		echo '<p class="mo">Lấy <b>tổng công</b> của lưới ở trên nhân ra tiền. Cách tính do '
			. '<b>bộ phận</b> của cơ sở quyết định (' . esc_html( $r['boPhan'] )
			. ') — khai ở màn <b>Cấu hình</b>.</p>';
		echo '<p class="mo">Bốn con số <b>công ngày · tăng ca · công đêm · công bù</b> nay nằm ngay '
			. 'trong cột <b>TỔNG</b> của lưới, ở đúng hàng của từng người — không phải cuộn xuống '
			. 'đây rồi dò lại tên.</p>';
		echo '</details></div>';

		if ( 'tho' === $r['kieu'] ) { self::luong_tho( $r['tho'] ); return; }
		if ( 'mtd' === $r['kieu'] ) { self::luong_mtd( $r['mtd'] ); return; }
		self::luong_vp( $r['vp'] );
	}

	/** Cơ sở CHƯA có công thức — chỉ giờ vào / giờ ra thô, không bịa ra con số tiền nào. */
	private static function luong_tho( $t ) {
		echo '<div class="the">';
		/* 🔴 NÓI RÕ VÌ SAO KHÔNG CÓ TIỀN. Bịa một công thức là đưa ra con số tiền mà không ai
		   biết từ đâu — mà bảng thì vẫn có số nên chẳng ai nghi. Đúng câu màn wp-admin đang nói. */
		echo '<div class="bao canh"><b>Cơ sở này chưa có công thức lương.</b> Bảng dưới chỉ là giờ '
			. 'vào / giờ ra thô. Hệ thống cố ý KHÔNG suy ra một cách tính nào — bịa công thức là '
			. 'đưa ra một con số tiền mà không ai biết từ đâu. Khai bộ phận cho cơ sở ở màn '
			. '<b>Cấu hình</b> thì bảng này thành bảng lương.</div>';
		echo '<div class="cuon"><table><thead><tr><th>Mã</th><th>Tên</th><th>Ngày</th>'
			. '<th>Vào</th><th>Ra</th></tr></thead><tbody>';
		foreach ( $t['rows'] as $e ) {
			foreach ( $e['ngay'] as $i => $d ) {
				echo '<tr><td>' . ( 0 === $i ? '<b>' . esc_html( $e['ma'] ) . '</b>' : '' ) . '</td>'
					. '<td>' . ( 0 === $i ? esc_html( $e['ten'] ) : '' ) . '</td>'
					. '<td>' . esc_html( $d['date'] ) . '</td>'
					. '<td>' . esc_html( $d['vao'] ) . '</td>'
					. '<td>' . esc_html( $d['ra'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div></div>';
	}

	/** Máy tự động — tính theo CÔNG và theo GIỜ, tách thường / cuối tuần / lễ. */
	private static function luong_mtd( $m ) {
		echo '<div class="the">';
		if ( $m['chuaKhaiGia'] ) {
			/* Nói rõ ô tiền bằng 0 vì THIẾU ĐƠN GIÁ, không phải vì không ai làm. */
			echo '<div class="bao loi"><b>Chưa khai đơn giá</b> (<code>MTD_DON_GIA</code>) — mọi ô '
				. 'tiền dưới đây là 0 vì thiếu đơn giá, KHÔNG phải vì không ai làm.</div>';
		}
		if ( ! empty( $m['theoGioCaCoSo'] ) ) {
			echo '<p class="mo">Cơ sở này được tích “tính theo giờ” — mọi dòng tính theo tiếng.</p>';
		}
		echo '<div class="cuon"><table><thead><tr><th>Mã</th><th>Tên</th>'
			. '<th>Công thường</th><th>Công cuối tuần</th><th>Công lễ</th>'
			. '<th>Giờ thường</th><th>Giờ cuối tuần</th><th>Giờ lễ</th>'
			. '<th>Tiền công</th><th>Tiền giờ</th><th>Tổng</th></tr></thead><tbody>';
		foreach ( $m['rows'] as $e ) {
			echo '<tr><td><b>' . esc_html( $e['ma'] ) . '</b></td><td>' . esc_html( $e['ten'] ) . '</td>'
				. '<td>' . esc_html( $e['cong']['thuong'] ) . '</td>'
				. '<td>' . esc_html( $e['cong']['cuoiTuan'] ) . '</td>'
				. '<td>' . esc_html( $e['cong']['le'] ) . '</td>'
				. '<td>' . esc_html( $e['gio']['thuong'] ) . '</td>'
				. '<td>' . esc_html( $e['gio']['cuoiTuan'] ) . '</td>'
				. '<td>' . esc_html( $e['gio']['le'] ) . '</td>'
				. '<td>' . esc_html( number_format( $e['tienCong'] ) ) . '</td>'
				. '<td>' . esc_html( number_format( $e['tienGio'] ) ) . '</td>'
				. '<td><b>' . esc_html( number_format( $e['tong'] ) ) . '</b></td></tr>';
		}
		echo '</tbody><tfoot><tr><th colspan="8">Tổng</th>'
			. '<th>' . esc_html( number_format( $m['tong']['tienCong'] ) ) . '</th>'
			. '<th>' . esc_html( number_format( $m['tong']['tienGio'] ) ) . '</th>'
			. '<th>' . esc_html( number_format( $m['tong']['tong'] ) ) . '</th></tr></tfoot>';
		echo '</table></div></div>';
	}

	/** Văn phòng — tính theo NGÀY CÔNG, có tăng ca / ca đêm / công bù. */
	private static function luong_vp( $v ) {
		echo '<div class="the">';
		/* 🔴 BA DẢI CẢNH BÁO LƯƠNG ĐÃ RỜI KHỎI ĐÂY.
		   Anh Thắng 27/08/2026, kèm hai ảnh: *"bỏ chỗ này"* — dải "chưa khai số ngày công",
		   dải "chưa khai ktMaNV", và dải "chưa khai lương cơ bản" liệt kê thẳng tên 24 người.

		   Chúng đúng, nhưng đứng nhầm chỗ. Bảng công là màn người ta mở HẰNG NGÀY để ĐỌC; ba
		   dải ấy nói về việc KHAI MỘT LẦN, và cái dài nhất trong đó dán tên hai mươi mấy người
		   ra giữa màn, mỗi lần mở lại đọc lại. Người xem công không khai được gì với chúng —
		   họ không có quyền, và cũng không phải việc của họ.
		   Chúng nay nằm ở màn **Cấu hình**, cạnh đúng mấy ô dùng để khai. Xem `the_thieu_khai()`.
		   ⚠️ KHÔNG XOÁ HẲN, chỉ dời. Xoá là chưa khai số ngày công thì cột Tiền hiện "—" mà
		      không câu nào nói vì sao, và người kiểm lương đi tìm một lỗi không có thật. */
		/* 🔴 CẢ HAI BẢNG DƯỚI ĐÂY ĐÃ BỎ — LƯỚI NÓI HẾT RỒI.
		   Anh Thắng 27/08/2026, hai lượt liền: *"bỏ bảng này đi, không cần thiết"* (bảng lương
		   người-theo-người) và *"này cũng bỏ đi, không cần thiết"* (Chi tiết từng ngày, 355 dòng
		   cho một tháng). Rồi anh nói luôn lý do, và lý do ấy mới là chỗ đáng nghe:
		   *"vì bảng này khi có giờ vào mà không có giờ ra, thì sẽ đỏ ô đó là được"*.

		   Đúng. Hai bảng ấy sinh ra để trả lời câu *"vì sao ô kia ra con số đó"* — nhưng chúng
		   trả lời bằng hàng trăm dòng xếp theo NGÀY, trong khi người hỏi đang chỉ tay vào MỘT ô.
		   Muốn dùng được phải dò ngày rồi dò mã, giữa một bảng dài hơn cả màn hình.
		   Nay lưới tự trả lời NGAY TẠI Ô: rê chuột ra khung giờ · số phút · vì sao ra công đó ·
		   phần ca đêm; và ô nào THIẾU GIỜ RA thì đỏ kèm dấu `?`. Cùng một câu trả lời, ở đúng
		   chỗ người ta hỏi.

		   ⚠️ EM CÓ NÊU LO NGẠI về phần TIỀN và anh vẫn chốt bỏ, nên ghi lại cho người đọc sau:
		      lương tháng · đơn giá · tiền TỪNG NGƯỜI không còn màn nào bày ra nữa. Con số tổng
		      của cả cơ sở vẫn còn ngay dưới đây. Cần lại bảng chi tiết thì dựng lại — dữ liệu
		      vẫn nguyên trong `vp_gan_tien()`, chỉ là không vẽ.

		   Ba khối cảnh báo phía trên GIỮ NGUYÊN: chúng không phải bảng, chúng là câu chỉ đường
		   *"còn thiếu cái này thì cột Tiền mới ra số"*. Bỏ theo là mất luôn chỗ biết vì sao tiền
		   chưa tính được. */
		$vp_tt = isset( $v['tien']['tongTien'] ) ? (float) $v['tien']['tongTien'] : 0.0;
		echo '<p class="mo" style="margin:10px 0 0"><b>Cả cơ sở:</b> '
			. esc_html( $v['tong']['tong'] ) . ' công'
			. ( $vp_tt > 0 ? ' · <b>' . esc_html( number_format( $vp_tt ) ) . '</b> đồng' : '' )
			. '. Số công của từng người nằm ở cột <b>TỔNG</b> của lưới phía trên.</p>';
		echo '</div>';
	}

	private static function the_man_cau_hinh( $ky, $toi ) {
		$ds_cs = self::ds_coso_xem( $toi );
		$cs    = isset( $_GET['ccs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['ccs'] ) ) : '';
		if ( '' !== $cs && ! in_array( $cs, $ds_cs, true ) ) { $cs = ''; }
		if ( '' === $cs && 1 === count( $ds_cs ) ) { $cs = $ds_cs[0]; }

		echo '<div class="the"><h2>Cấu hình chấm công</h2>';
		echo '<p class="mo">Khai <b>một lần rồi thôi</b>. Mỗi ô ở đây đổi <b>cách tính ra tiền</b> '
			. 'của cả cơ sở — đọc kỹ trước khi lưu. Bảng công vẫn <b>chỉ đọc</b>: đổi ở đây '
			. '<b>không sửa một giờ chấm nào</b>, chỉ đổi cách đọc số đã có.</p>';

		/* Hai khối dưới (ca làm việc · công thức) là của MỘT cơ sở, nên phải chọn cơ sở trước.
		   Hai khối còn lại (bộ phận · cách tính) là bảng của MỌI cơ sở nên không cần. */
		echo '<form method="get" class="hang" style="margin-top:10px">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<input type="hidden" name="man" value="cau_hinh">';
		echo '<div><label for="ccs">Cơ sở (cho khối Ca làm việc &amp; Công thức)</label>'
			. '<select id="ccs" name="ccs"><option value="">— chọn cơ sở —</option>';
		foreach ( $ds_cs as $x ) {
			echo '<option value="' . esc_attr( $x ) . '"' . selected( $x, $cs, false ) . '>'
				. esc_html( $x ) . '</option>';
		}
		echo '</select></div><div><button class="chinh">Xem</button></div></form>';
		echo '</div>';

		self::the_bo_phan( $ky, $toi );
		self::the_cach_tinh( $ky, $toi );
		self::the_ghep_cs( $ky, $toi );
		self::the_ten_cs( $ky, $toi );
		self::the_thieu_khai( $ky, $toi, $cs );

		if ( '' === $cs ) {
			echo '<div class="the"><p class="mo">Chọn một cơ sở ở trên để khai <b>ca làm việc</b> '
				. 'và <b>công thức tính công</b> cho cơ sở đó.</p></div>';
			return;
		}

		/* 🔴 CA LÀ CỦA CƠ SỞ, CÔNG THỨC CÔNG LÀ CỦA VĂN PHÒNG — KHÔNG BÀY CẢ HAI CHO CẢ HAI.
		   Anh Thắng 26/08: *"Cơ sở mới có ca, Bộ Phận VP không có ca"* và *"Bộ phận văn phòng
		   tính theo công thức này (tức tính dạng công). Cái trên là tính theo dạng giờ, cho cơ
		   sở."*
		   Cơ sở tính theo GIỜ thì con số là giờ ra trừ giờ vào — bộ công thức bậc thang/ca đêm
		   không hề được dùng tới, bày ra chỉ mời người ta khai một bộ số không ăn vào đâu.
		   Cơ sở tính theo CÔNG thì ngược lại: nó không chạy ca gãy, khai ca là khai cho vui.
		   Bày nhầm khối nguy hiểm hơn thiếu khối: người khai tưởng mình vừa đổi được cái gì đó. */
		$theo_cong = ( 'cong' === VHCC_Luong::cach_tinh( $cs ) );
		if ( $theo_cong ) {
			self::the_cong_thuc_vp( $cs, $ky, $toi );
			echo '<div class="the"><p class="mo"><b>' . esc_html( $cs ) . '</b> đang tính '
				. '<b>THEO CÔNG</b> nên không dùng tới khối <b>Ca làm việc</b> — khối ấy chỉ có '
				. 'nghĩa với cơ sở tính theo giờ (ca gãy). Đổi cách tính ở khối '
				. '<b>Cách tính công của từng cơ sở</b> bên trên.</p></div>';
		} else {
			self::the_khai_ca( $cs, $ky, $toi );
			echo '<div class="the"><p class="mo"><b>' . esc_html( $cs ) . '</b> đang tính '
				. '<b>THEO GIỜ</b> (mỗi ô là số giờ làm) nên không dùng tới <b>Công thức tính '
				. 'công</b> — bộ bậc thang, ca đêm, công bù chỉ chạy khi cơ sở tính theo công. '
				. 'Đổi cách tính ở khối <b>Cách tính công của từng cơ sở</b> bên trên.</p></div>';
		}
	}

	/* ===========================================================================
	 *  MÀN DỮ LIỆU ĐẦU VÀO — chỗ giờ công đi vào hệ thống
	 * ---------------------------------------------------------------------------
	 *  Anh Thắng 26/08/2026: *"Đẩy này qua tab dữ liệu đầu vào đi"*.
	 *
	 *  ⚠️ Tab này cố ý CHỈ có một việc. Nó là cửa DUY NHẤT nạp giờ công bằng tay, và người ta
	 *     tìm tới nó đúng những lúc đang rối (máy hỏng, sổ cũ chưa vào). Nhét thêm thứ khác vào
	 *     đây là mời bấm nhầm vào lúc dễ bấm nhầm nhất.
	 * =========================================================================== */
	private static function the_man_du_lieu( $ky, $toi ) {
		echo '<div class="the"><h2>Dữ liệu đầu vào</h2>';
		echo '<p class="mo">Giờ công vào hệ thống bằng <b>bốn</b> đường: máy chấm công · trạm '
			. 'chấm công online · <b>bù / sửa ngay trên lưới</b> ở tab <b>Bảng công</b> · và '
			. '<b>nạp .csv</b> ở dưới đây. Ba đường đầu chạy hằng ngày; đường thứ tư là để '
			. 'chuyển sổ cũ sang, hoặc vá lại một tháng máy hỏng.</p>';
		echo '</div>';
		self::the_nap_cong( '', $ky, $toi, self::ds_coso_xem( $toi ) );
	}

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
		echo '<div><button class="chay" name="viec" value="nap_cong">Nạp thật</button></div>';
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
			/* 🔴 THU GỌN SẴN. Anh Thắng 26/08/2026: *"Cho này gọn lại, khi nào bấm xổ mới xổ ra"*.
			   Bảng này có bao nhiêu dòng là do dữ liệu quyết định — sổ thật đang 36 ngày, và nó
			   xổ hết ra giữa màn, đẩy mọi thứ phía dưới đi mất mấy màn hình. Người mở màn bảng
			   công phần lớn không đến đây để đọc nó; họ chỉ cần biết CÓ BAO NHIÊU. Con số nằm
			   ngay trên nhãn, ai cần chi tiết thì bấm.
			   ⚠️ Dùng `<details>` của chính HTML, KHÔNG JavaScript — cả màn quản trị này không
			      có lấy một dòng script, và Ctrl+F của trình duyệt vẫn tìm được chữ bên trong. */
			echo '<div class="the"><details><summary><b>Ngày thiếu giờ ra</b> — '
				. '<span class="chu-hong">' . count( $thieu ) . ' ngày</span> '
				. '<span class="mo">(bấm để mở)</span></summary>';
			echo '<p class="mo" style="margin:10px 0">Có giờ vào mà không có giờ ra — hệ thống '
				. '<b>không tự điền</b>: điền là bịa ra số giờ làm cho một ngày, mà số đó thành '
				. 'tiền. Gắn cờ để còn tra lại.</p>';
			echo '<div class="cuon"><table><thead><tr><th>Ngày</th><th>Mã NV</th><th>Họ tên</th>'
				. '<th>Giờ vào</th></tr></thead><tbody>';
			foreach ( $thieu as $x ) {
				echo '<tr><td>' . esc_html( $x['ngay'] ) . '</td><td>' . esc_html( $x['maNV'] )
					. ( '' !== $x['hauTo'] ? ' <span class="duoi">' . esc_html( $x['hauTo'] ) . '</span>' : '' )
					. '</td><td>' . esc_html( $x['hoTen'] ) . '</td><td>'
					. esc_html( substr( (string) $x['vao'], 0, 5 ) ) . '</td></tr>';
			}
			echo '</tbody></table></div></details></div>';
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
		/* 🔴 CÓ HẠNG BÁO THỨ BA: việc XONG nhưng một phần bên trong hỏng.
		   Ảnh thẻ là ca đầu tiên cần nó — hồ sơ tạo xong mà ảnh không nhận được thì "Không xong"
		   là sai (người ĐÃ được thêm), mà nền xanh "xong" cũng sai (ảnh mất mà không ai biết).
		   Thiếu nhánh này thì mảng báo cứ trả về rồi rơi vào im lặng. */
		if ( isset( $b['canh'] ) ) {
			echo '<div class="bao canh">' . esc_html( $b['canh'] ) . '</div>';
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
		/* 🔴 BIỂU MẪU `method="get"` PHẢI TỰ CHỞ LẤY MÀN CỦA NÓ.
		   Anh Thắng 26/08/2026: *"Bấm gõ tìm kiếm nhân sự nó cứ nhảy sang trang chính"*.
		   Gửi biểu mẫu GET là trình duyệt dựng LẠI thanh địa chỉ CHỈ TỪ các ô trong biểu mẫu —
		   mọi tham số đang có trên địa chỉ cũ, kể cả `man=ho_so`, biến mất. Không còn `man` thì
		   `man_mac_dinh()` trả về 'nha', và người ta rơi vào Trang chính đúng lúc vừa gõ xong một
		   câu tìm. Ba biểu mẫu GET khác trên màn này đều đã chở `man`; đây là cái duy nhất sót. */
		echo '<input type="hidden" name="man" value="ho_so">';
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
		/* Ô Cửa hàng đi cùng một nguồn với ô Cơ sở phụ — xem chú thích dưới. Hai ô hỏi cùng một
		   câu ("cơ sở nào đang có?") mà xổ ra hai danh sách khác nhau thì người khai phải tự
		   nhớ mã nào gõ được ở ô nào. */
		$sql_cs = "SELECT DISTINCT cua_hang AS v FROM $bang WHERE cua_hang<>''"
			. " UNION SELECT DISTINCT coso_phu AS v FROM $bang WHERE coso_phu<>''";
		echo self::goi_y( 'dl_ch', $sql_cs, true );
		echo self::goi_y( 'dl_cv', "SELECT DISTINCT chuc_vu  AS v FROM $bang WHERE chuc_vu<>''" );
		/* 🔴 NHIỆM VỤ KHÔNG TỰ GOM TỪ DỮ LIỆU NỮA. Gom tự động thì cột Nhiệm vụ của sổ cũ đang
		   chứa lẫn TÊN CƠ SỞ ("JP Aeon Mall Tân Phú", "JP VINCOM 3/2"), và chúng trôi hết vào
		   danh sách xổ ra — anh Thắng: *"1 cái đầu với 3 cái cuối là nhiệm vụ, còn mấy cái khác
		   không phải"*. Một danh sách gợi ý mà 2/3 là rác thì tệ hơn không có: nó mời người ta
		   bấm nhầm.

		   Nên danh sách này do NGƯỜI KHAI, sửa ngay trên trang. Em không đoán thay — đoán sai
		   một mục là 240 lần bấm nhầm. */
		echo self::goi_y( 'dl_nv', '', false, self::ds_nhiem_vu() );
		/* 🔴 CƠ SỞ PHỤ PHẢI XỔ RA **MỌI CƠ SỞ**, KHÔNG PHẢI CHỈ NHỮNG CƠ SỞ ĐÃ TỪNG BỊ KHAI PHỤ.
		   Anh Thắng 28/08/2026, gửi ảnh ô Cơ sở phụ xổ xuống chỉ có mười mục:
		   *"Cơ sở phụ sao cơ sở có, cơ sở không"*.
		   Bản trước gom `DISTINCT coso_phu` — tức chỉ những cơ sở ĐÃ CÓ NGƯỜI khai làm phụ.
		   Cơ sở mới, hay cơ sở chưa ai làm thêm ở đó, thì không bao giờ có mặt; mà đúng lúc
		   người khai cần nó nhất lại là lúc chưa ai khai. Danh sách tự nuôi mình như vậy chỉ
		   lớn lên được nếu ai đó gõ tay đúng chính tả một lần đầu — gõ sai một dấu là sinh ra
		   một mã cơ sở thứ hai, im lặng, và bảng công của người ấy tách làm đôi.
		   Nguồn đúng là HỢP của cả hai cột: cửa hàng chính và cơ sở phụ. */
		echo self::goi_y( 'dl_cp', $sql_cs, true );

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
			/* 🔴 NEO TRÊN HÀNG CỦA NGƯỜI ẤY — bấm 👁 để hiện PIN thì đứng nguyên chỗ.
			   Anh Thắng 27/08/2026: *"bấm là hiện ra luôn nhé, đây nó hiện nhưng cứ nhảy lên đầu
			   trang, xong phải kéo lại"*.
			   Bấm 👁 là tải lại cả trang (màn này KHÔNG có một dòng script nào — xem đầu tệp),
			   nên trình duyệt về đỉnh. Bảng hồ sơ dài mấy trăm dòng: người ta cuộn tới hàng thứ
			   180, bấm 👁, rồi phải cuộn lại từ đầu để đọc con số vừa hiện ra. Neo đưa họ về
			   đúng hàng ấy — cùng cách đã chữa cho hàng sửa giờ. */
			echo '<tr id="hs-' . esc_attr( preg_replace( '/[^A-Za-z0-9]+/', '_', (string) $r['ma_nv'] ) )
				. '"><td><code>' . esc_html( $r['ma_nv'] ) . '</code></td>';
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
					. '<a href="' . esc_url( remove_query_arg( 'pin' ) ) . self::neo_hs( $r['ma_nv'] )
					. '">ẩn</a>';
			} elseif ( $co_pin ) {
				echo '<span class="co">✔ có ' . strlen( (string) $r['pin_dang_nhap'] ) . ' số</span>';
				if ( VHCC_Vai::duoc( $toi, 'xem_pin' ) ) {
					echo ' <a href="' . esc_url( add_query_arg( 'pin', $r['ma_nv'] ) )
						. self::neo_hs( $r['ma_nv'] ) . '">👁</a>';
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
	/**
	 * CƠ SỞ PHỤ — LƯỚI Ô TÍCH, chọn bao nhiêu cơ sở cũng được.
	 *
	 * Anh Thắng 28/08/2026, sau khi thấy ô xổ xuống chỉ chọn được một mục:
	 * *"Nếu lÀM 2,3 cơ sở phụ thì sao chọn"*.
	 *
	 * 🔴 MỘT Ô GÕ CHỮ KHÔNG PHẢI LÀ CHỖ KHAI NHIỀU GIÁ TRỊ.
	 *    Cột này lưu chuỗi ngăn bằng dấu phẩy (`FARM_PT, FZ_LTVT`), nhưng ô `<input list=…>` là
	 *    ô MỘT giá trị: bấm chọn mục thứ hai là trình duyệt THAY cả ô, mất luôn mục thứ nhất.
	 *    Muốn hai cơ sở thì phải tự gõ dấu phẩy — mà gõ tay giữa 240 dòng là sớm muộn có một
	 *    mã sai chính tả, và người ấy lặng lẽ rơi khỏi bảng công của cơ sở kia.
	 *
	 * 🔴 KHÔNG DÙNG `<select multiple>`. Nó đòi giữ Ctrl để chọn nhiều — thứ không ai đoán ra
	 *    nếu chưa được dạy, và trên điện thoại thì gần như không bấm nổi. Ô tích thì bấm là
	 *    xong, và nhìn một cái là thấy đang chọn những gì.
	 *
	 * ⚠️ Vẫn giữ MỘT Ô GÕ CHỮ ở cuối cho cơ sở CHƯA có trong sổ. Lưới ô tích dựng từ dữ liệu
	 *    đang có, nên cơ sở mới mở tuần này chưa thể nằm trong đó — bỏ ô ấy là khai một cơ sở
	 *    mới thành việc không làm được ở đây, và người ta quay lại gõ tay chỗ khác.
	 *
	 * Cả hai đi cùng một tên `coso_phu_o[]`, nên bộ nhận ở `luu_ho_so()` không cần biết giá trị
	 * đến từ ô tích hay từ ô gõ.
	 */
	private static function o_coso_phu( $dang_co ) {
		$chon = array();
		foreach ( explode( ',', (string) $dang_co ) as $x ) {
			$x = trim( $x );
			if ( '' !== $x ) { $chon[ VHCC_NhanSu::chu_thuong( $x ) ] = $x; }
		}
		/* `ds_moi_coso()` quét CẢ hồ sơ đang sửa, nên mọi cơ sở người này đang khai đã nằm sẵn
		   trong danh sách — kể cả cơ sở không còn ai khác làm ở đó. Cố ý KHÔNG thêm một vòng
		   "bổ sung $chon vào $ds": nó không đổi kết quả trong bất kỳ ca nào, mà một dòng không
		   phép thử nào phân biệt được là một dòng không ai dám sửa về sau. */
		$ds = self::ds_moi_coso();
		ksort( $ds );

		$h = '<div style="border:1px solid var(--vien);border-radius:8px;padding:8px 10px;'
			. 'background:var(--the,#fff)">';
		if ( $ds ) {
			$h .= '<div style="display:flex;flex-wrap:wrap;gap:4px 14px;max-height:180px;overflow:auto">';
			foreach ( $ds as $k => $v ) {
				$h .= '<label style="display:flex;align-items:center;gap:5px;font-size:13px;'
					. 'font-weight:400;white-space:nowrap">'
					. '<input type="checkbox" name="coso_phu_o[]" value="' . esc_attr( $v ) . '"'
					. checked( isset( $chon[ $k ] ), true, false ) . '>'
					. esc_html( $v ) . '</label>';
			}
			$h .= '</div>';
		}
		$h .= '<div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">'
			. '<input name="coso_phu_o[]" list="dl_cp" placeholder="cơ sở khác — gõ mã rồi Lưu"'
			. ' style="flex:1;min-width:190px;font-size:13px">'
			. '<span class="mo" style="font-size:11.5px">Tích bao nhiêu cơ sở cũng được.</span>'
			. '</div></div>';
		return $h;
	}

	/** Mọi mã cơ sở đang có trong sổ nhân sự: [ chữ thường => cách viết gốc ]. */
	private static function ds_moi_coso() {
		$b  = VHCC_DB::t( 'nhan_vien' );
		$ra = array();
		$sql = "SELECT DISTINCT cua_hang AS v FROM $b WHERE cua_hang<>''"
			. " UNION SELECT DISTINCT coso_phu AS v FROM $b WHERE coso_phu<>''";
		foreach ( VHCC_DB::rows( $sql ) as $x ) {
			foreach ( explode( ',', (string) $x['v'] ) as $m ) {
				$m = trim( $m );
				if ( '' === $m ) { continue; }
				$k = VHCC_NhanSu::chu_thuong( $m );
				if ( ! isset( $ra[ $k ] ) ) { $ra[ $k ] = $m; }
			}
		}
		return $ra;
	}

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
				} elseif ( 'coso_phu' === $c ) {
					echo self::o_coso_phu( $g( $c ) );
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
