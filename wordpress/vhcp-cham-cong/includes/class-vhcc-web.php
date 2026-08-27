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

		$b = VHCC_Cham::bang_cham_cong( $toi, $cs, $th );
		if ( empty( $b['ok'] ) ) { self::loi_xuat( $b['error'] ); return; }
		if ( empty( $b['hang'] ) ) {
			self::loi_xuat( 'Tháng ' . $b['thang'] . ' chưa có dữ liệu chấm công nào ở ' . $cs
				. ' — không có gì để xuất.' );
			return;
		}

		$noi = VHCC_Xuat::xlsx( VHCC_Ca::to_xuat( $b, $cs ) );
		if ( null === $noi ) { self::loi_xuat( 'Không dựng được tệp .xlsx.' ); return; }

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
	/* ⚠️ `msoma` là của màn Máy & Firmware — khai ở đây chứ không chỉ ở `VHCC_WebMay::THAM_SO`:
	      danh sách này là thứ `o_loc()` đọc để chở tham số qua một lượt POST, và thiếu nó thì
	      chọn máy xong bấm một nút bất kỳ là ô chọn nhảy về máy đầu tiên. */
	const THAM_SO = array( 'cs', 'q', 'loc', 'sua', 'pin', 'man', 'ccs', 'cth', 'cbp', 'cng', 'cnv', 'ctk', 'lcs', 'lth', 'msoma' );

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

		/* Việc của màn Máy & Firmware. Định tuyến ở đây chứ không chép mã sang: `VHCC_WebMay`
		   tự gác `he_thong` lần nữa bên trong, nên nó không phụ thuộc vào chốt nào ở đây. */
		if ( VHCC_WebMay::la_viec( $viec ) ) {
			return VHCC_WebMay::viec( $viec, $toi );
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
		/* Địa chỉ cũ `?man=luong` nay dẫn về Bảng công — khối lương nằm trong đó. Ai đã lưu lại
		   đường ấy thì vẫn tới đúng chỗ, thay vì rơi về màn mặc định mà không hiểu vì sao. */
		if ( 'luong' === $man ) { $man = 'cham'; }
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
	const MAN_UU_TIEN = array( 'nha', 'ho_so', 'cham', 'cong_toi', 'cau_hinh', 'du_lieu', 'may' );

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
		if ( VHCC_Vai::duoc( $toi, 'he_thong' ) )   { $ds['may']      = 'Máy & Firmware'; }
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
		/* Quản lý nhân sự cũng là TRANG KHÁC (địa chỉ riêng, anh Thắng chốt vậy) nên là liên
		   kết. Chỉ vẽ cho người mở được nó — vẽ cho cả người không vào được thì bấm vào chỉ
		   nhận một câu chối, mà cái nút thì cứ nằm đó mời gọi mỗi ngày.
		   ⚠️ Gác `method_exists` cùng hàm với lời gọi (`tools/test/kiem-goi-cheo.php`). */
		if ( class_exists( 'VHCC_TrangNS' ) && method_exists( 'VHCC_TrangNS', 'url' )
			&& method_exists( 'VHCC_TrangNS', 'toi' ) && VHCC_TrangNS::toi() ) {
			echo '<a class="nut" href="' . esc_url( VHCC_TrangNS::url() ) . '">👥 Quản lý nhân sự</a>';
		}
		echo '</div></div>';
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

		$b = VHCC_Cham::bang_cham_cong( $toi, $cs, $th );
		if ( empty( $b['ok'] ) ) {
			echo '<div class="bao loi">' . esc_html( $b['error'] ) . '</div>';
			return;
		}
		self::ve_bang_cham( $b, $cs, $th, $ngay, $ma_nv, $ky, $toi );

		/* 🔴 GIỜ & LƯƠNG NẰM NGAY DƯỚI, CÙNG CƠ SỞ CÙNG THÁNG. Anh Thắng 27/08/2026: *"bảng
		   công và giờ lương gộp lại thành 1 trang"*.
		   ⚠️ Đặt SAU `ve_bang_cham` chứ không phải trước: người ta mở màn này ra để xem BẢNG
		      CÔNG, còn lương là thứ soi sau. Đảo lại là mỗi lần mở phải cuộn qua bảng tiền mới
		      tới thứ mình cần.
		   ⚠️ Và chỉ ở nhánh bảng công VẼ ĐƯỢC — bảng công lỗi mà vẫn in bảng tiền ra thì đó là
		      tiền tính từ một tháng không đọc nổi. */
		self::the_khoi_in( $toi, $cs, $th );
		self::the_khoi_luong( $toi, $cs, $th );
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

	private static function o_luoi_gio_mot( $r, $ho_ten, $ds_ca ) {
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
			return array(
				'noi' => $k, 'noi_tho' => $k, 'lop' => ' hong', 'phut' => null,
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
		$so   = '<b>' . self::so_vp( round( $r['phut'] / 60, 1 ) ) . '</b>';
		return array(
			'noi'     => $so . ( '' !== $ma_o ? '<div class="mca">' . esc_html( $ma_o ) . '</div>' : '' ),
			'noi_tho' => $so,
			'chu'     => $chu,
			'lop'     => ( $i_ca >= 0 ? ' ca' . ( ( $i_ca % 4 ) + 1 ) : '' ),
			'phut'    => (int) $r['phut'] );
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
		echo '<tr class="hang-sua" id="suaday"><td colspan="' . (int) $so_cot . '"><div class="hs-in">';

		if ( ! $duoc ) {
			echo '<div class="bao canh" style="margin:0">' . esc_html( $co_gio
				? 'Sửa giờ đã có cần quyền Admin. Thấy giờ sai thì gắn cờ để Admin sửa.'
				: 'Bù giờ vào ô trống cần quyền Cửa hàng trưởng trở lên.' ) . '</div></div></td></tr>';
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

		echo '<div class="the">';
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
				foreach ( $ds_ngay as $r ) { $tong_nguoi += (int) $r['phut']; }
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
			echo '<tr>';
			echo '<td>' . esc_html( $ho_ten )
				. ( isset( $khong_cham[ $ma ] )
					? ' <span class="duoi" title="Cả tháng chưa có lượt chấm nào — '
						. 'bấm vào một ô để bù giờ">chưa chấm</span>' : '' )
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
				$c_chinh = self::o_luoi_gio_mot( $r_chinh, $ho_ten, $ds_ca );

				/* Dòng phụ: chỉ vẽ hậu tố nào NGÀY ẤY có lượt chấm. Vẽ hết mọi hậu tố cho mọi
				   ngày là mỗi ô ba dòng dấu chấm, lưới cao gấp ba mà không thêm một tin nào. */
				$duoi = '';
				foreach ( $phu as $ht_p ) {
					if ( ! isset( $o[ $ma ][ $ht_p ][ $i ] ) ) { continue; }
					$c_p = self::o_luoi_gio_mot( $o[ $ma ][ $ht_p ][ $i ], $ho_ten, $ds_ca );
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

				/* Ngày ấy người này đứng ở cơ sở khác -> nói ra, đừng để ô trống trông như nghỉ. */
				if ( isset( $ck_nguoi[ $i ] ) ) {
					$duoi .= self::o_coso_khac( $ck_nguoi[ $i ], $ho_ten, $ngay_o );
				}
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
			echo '<td class="tong"><b>' . esc_html( VHCC_Cham::chu_gio( $tong_nguoi ) ) . '</b>';
			foreach ( $phut_phu as $ht_p => $p_p ) {
				echo '<div class="mo" style="font-size:10px">-' . esc_html( $ht_p ) . ' '
					. esc_html( VHCC_Cham::chu_gio( $p_p ) ) . '</div>';
			}
			/* Anh Thắng: *"cơ sở chính bao nhiêu công, cơ sở thứ 2 bao nhiêu công"* — con số lớn
			   là cơ sở đang xem, mỗi dòng dưới là một cơ sở khác. */
			echo self::tong_coso_khac( isset( $tk_ds[ strtoupper( $ma ) ] )
				? $tk_ds[ strtoupper( $ma ) ] : array() );
			echo '</td></tr>';
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

			echo '<tr><td>' . esc_html( $e['ten'] )
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
				$lop = '';
				if ( ! empty( $d['caLa'] ) || ! empty( $d['demThieuGio'] ) ) { $lop = ' hong'; }
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
				} elseif ( ! empty( $d['demThieuGio'] ) ) {
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
						. ( '' !== $dem_o
							? '<div class="mdem' . ( ! empty( $d['demThieuGio'] ) ? ' chu-hong' : '' )
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
			. '<span class="k hong">có giờ nhưng KHÔNG ra công</span>'
			. '<br>Dòng nhỏ <b>🌙</b> nằm <b>ngay trong ô</b> là phần ca đêm của ngày đó — mỗi '
			. 'người chỉ một hàng. <b>🌙</b> một mình = đêm đó CÓ làm · <b>🌙 kèm số</b> = công '
			. 'đêm được tính vào ngày đó (ca đêm đêm trước cho công sang hôm sau) · '
			. '<b class="chu-hong">🌙0</b> = có làm mà KHÔNG đủ giờ tối thiểu nên không ra công. '
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
		if ( $v['tien']['chuaKhaiNgayCong'] ) {
			/* 🔴 KHÔNG ĐOÁN MẪU SỐ. Đoán là sai tiền của MỌI người cùng lúc, mà bảng vẫn có số
			   nên chẳng ai nghi. */
			echo '<div class="bao loi"><b>Chưa khai số ngày công của ' . esc_html( $v['ncThang'] )
				. '</b> — cột Tiền hiện “—”. Hệ thống KHÔNG đoán mẫu số: đoán là sai tiền của mọi '
				. 'người cùng lúc, mà bảng vẫn có số nên chẳng ai nghi.'
				. ( $v['ncGoiY'] ? ' Tháng gần nhất đã khai tại cơ sở này: <b>'
					. esc_html( $v['ncGoiY'] ) . '</b> (chỉ để tham khảo, chưa dùng để tính).' : '' )
				. '</div>';
		}
		if ( $v['chuaKhaiKeToan'] ) {
			echo '<div class="bao canh">Chưa khai mã NV thuộc <b>Kế toán văn phòng</b> '
				. '(<code>ktMaNV</code>) — nên chưa ai được áp khung thứ Bảy 08:30–12:00 và luật '
				. 'Chủ nhật nghỉ.</div>';
		}
		if ( ! empty( $v['tien']['thieuLuong'] ) ) {
			echo '<div class="bao canh">Chưa khai lương cơ bản: '
				. esc_html( implode( ', ', $v['tien']['thieuLuong'] ) ) . '</div>';
		}
		/* 🔴 BỐN CỘT CÔNG ĐÃ DỜI LÊN LƯỚI — BỎ Ở ĐÂY.
		   Anh Thắng 27/08/2026: *"chưa bỏ bảng này đi à em"*, ngay sau khi bốn con số ấy hiện ra
		   ở cột TỔNG của lưới. Anh đúng: cùng một con số in ở hai chỗ trên cùng một màn là mời
		   người ta so hai chỗ, và ngày nào hai chỗ lệch nhau thì không ai biết tin cái nào.
		   ⚠️ NHƯNG KHÔNG BỎ CẢ BẢNG. Bốn cột còn lại (Lương tháng · Đơn giá · Tiền · Cần soi)
		      không có ở đâu khác — bỏ hết là mất chỗ DUY NHẤT xem tiền.
		   `Tổng công` thì GIỮ: nó là mốc để đối chiếu với cột TỔNG của lưới. Hai chỗ cùng in một
		   con số ở đây là CÓ CHỦ Ý — lệch nhau nghĩa là một trong hai phép tính sai, và đó đúng
		   là thứ cần lộ ra. Khác hẳn bốn cột vừa bỏ: chúng chỉ chép lại, không đối chiếu gì. */
		echo '<div class="cuon"><table><thead><tr><th>Mã</th><th>Tên</th><th>Tổng công</th>'
			. '<th>Lương tháng</th><th>Đơn giá 1 công</th><th>Tiền</th><th>Cần soi</th>'
			. '</tr></thead><tbody>';
		foreach ( $v['rows'] as $e ) {
			$soi = array();
			if ( $e['soNgayCaLa'] )          { $soi[] = $e['soNgayCaLa'] . ' ngày ca lạ'; }
			if ( $e['soNgayDemThieuGio'] )   { $soi[] = $e['soNgayDemThieuGio'] . ' đêm thiếu giờ'; }
			if ( $e['soNgayDemChuaDuCap'] )  { $soi[] = $e['soNgayDemChuaDuCap'] . ' đêm thiếu cặp giờ'; }
			echo '<tr' . ( $soi ? ' class="hong"' : '' ) . '>';
			echo '<td><b>' . esc_html( $e['ma'] ) . '</b></td>'
				. '<td>' . esc_html( $e['ten'] ) . ( $e['laKeToan'] ? ' <span class="mo">(kế toán)</span>' : '' ) . '</td>'
				. '<td><b>' . esc_html( $e['tong'] ) . '</b></td>'
				. '<td>' . esc_html( $e['luongThang'] ? number_format( $e['luongThang'] ) : '—' ) . '</td>'
				. '<td>' . esc_html( $e['donGiaCong'] ? number_format( $e['donGiaCong'] ) : '—' ) . '</td>'
				. '<td><b>' . esc_html( $e['tien'] ? number_format( $e['tien'] ) : '—' ) . '</b></td>'
				. '<td>' . ( $soi ? '<span class="chua">' . esc_html( implode( ' · ', $soi ) ) . '</span>' : '' )
				. '</td></tr>';
		}
		/* ⚠️ `colspan` phải đi theo số cột. Bỏ bốn cột mà quên chỗ này là cả chân bảng lệch sang
		   phải, và con số Tiền tổng nằm dưới cột Đơn giá — vẫn là một bảng đọc được, chỉ là đọc
		   sai. Hai cột đầu (Mã · Tên) -> colspan 2. */
		echo '</tbody><tfoot><tr><th colspan="2">Tổng</th><th>' . esc_html( $v['tong']['tong'] )
			. '</th><th colspan="2"></th><th>'
			. esc_html( $v['tien']['tongTien'] ? number_format( $v['tien']['tongTien'] ) : '—' )
			. '</th><th></th></tr></tfoot></table></div>';
		echo '</div>';

		/* 🔴 CHI TIẾT TỪNG NGÀY — GẬP LẠI, NHƯNG PHẢI CÓ. Không soi được thì không kiểm được
		   lương; mà mở sẵn thì cả nghìn dòng đè lên bảng tổng, thứ người ta mở màn này ra để xem. */
		echo '<div class="the"><details>';
		echo '<summary><b>Chi tiết từng ngày</b> — ' . count( $v['detail'] ) . ' dòng, để soi lại '
			. 'từng con số công ở trên</summary>';
		echo '<p class="mo">Ngày ca đêm được GIỮ lại dù 0 công, để đọc được công của hôm sau từ '
			. 'đâu ra — không soi được là không kiểm được lương.</p>';
		echo '<div class="cuon"><table><thead><tr><th>Ngày</th><th>Mã</th><th>Khung</th>'
			. '<th>Phút ca ngày</th><th>Công ngày</th><th>Tăng ca</th><th>Công đêm</th><th>Bù</th>'
			. '<th>Ghi chú</th></tr></thead><tbody>';
		foreach ( $v['detail'] as $d ) {
			$gc = array();
			if ( $d['kt7'] )           { $gc[] = 'kế toán thứ Bảy'; }
			if ( $d['ktCnNghi'] )      { $gc[] = 'Chủ nhật — lịch nghỉ'; }
			if ( $d['caLa'] )          { $gc[] = 'giờ ca ngày lọt hàng 2, KHÔNG tính'; }
			if ( $d['demSangNgay'] )   { $gc[] = 'ca đêm → công ghi cho ' . $d['demSangNgay']; }
			if ( $d['demTuNgay'] )     { $gc[] = 'công đêm từ ' . $d['demTuNgay']; }
			if ( $d['demThieuGio'] )   { $gc[] = 'đêm ' . $d['gioDemThuc'] . 'h < ngưỡng, KHÔNG được công'; }
			if ( $d['demChuaDuCap'] )  { $gc[] = 'đêm thiếu cặp giờ — vẫn tính, cần soi'; }
			echo '<tr><td>' . esc_html( $d['ngay'] ) . '</td><td>' . esc_html( $d['ma'] ) . '</td>'
				. '<td>' . esc_html( $d['khung'] ) . '</td><td>' . esc_html( $d['phutNgay'] ) . '</td>'
				/* ⚠️ `congTangCa` / `congBu` — KHÔNG phải `tangCa` / `bu`. Gõ nhầm tên khoá thì
				   `esc_html()` nhận null và in ra ô TRỐNG: không lỗi, không cảnh báo, chỉ là
				   cột Tăng ca và cột Bù trắng trơn trong bảng soi lương. Mà bảng soi lương
				   trắng một cột thì người đọc tưởng tháng ấy không ai tăng ca. */
				. '<td>' . esc_html( $d['congNgay'] ) . '</td><td>' . esc_html( $d['congTangCa'] ) . '</td>'
				. '<td>' . esc_html( $d['congDem'] ) . '</td><td>' . esc_html( $d['congBu'] ) . '</td>'
				. '<td class="mo">' . esc_html( implode( ' · ', $gc ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></details></div>';
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
