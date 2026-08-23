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
		$toi = self::toi();

		/* Đăng xuất xử trước mọi thứ. */
		if ( isset( $_POST['viec'] ) && 'thoat' === $_POST['viec'] ) {
			self::dat_cookie( '', false );
			self::ve( self::url() );
		}

		if ( ! $toi ) { self::trang_dang_nhap(); return; }

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

	/** Các tham số phải sống sót qua một lượt POST — bộ lọc, ô tìm, màn đang mở. */
	const THAM_SO = array( 'cs', 'q', 'loc', 'sua', 'pin' );

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

	private static function lam_viec( $viec, $toi ) {
		$bao = array();

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
			if ( 'Admin' !== $toi['role'] ) {
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
			if ( 'Admin' !== $toi['role'] ) {
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
			if ( 'Admin' !== $toi['role'] ) {
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
			if ( 'Admin' !== $toi['role'] ) {
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
		return array( 'ok' => true, 'noi_dung' => (string) $nd );
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
			. '@media(max-width:640px){.bo{padding:12px}h1{font-size:15px}}';
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
				if ( in_array( (string) $kq['role'], self::VAI_TRO, true ) ) {
					self::dat_cookie( $kq['token'] );
					self::ve( self::url() );
				}
				/* PIN ĐÚNG nhưng không đủ quyền: nói rõ, đừng báo "PIN sai" — người ta gõ lại
				   mười lần rồi tự khoá mình. */
				$loi = 'Tài khoản ' . $kq['name'] . ' (' . $kq['role'] . ') không mở được trang quản trị. '
					. 'Trang này chỉ dành cho Admin và Quản lý.';
			} else {
				$loi = isset( $kq['error'] ) ? $kq['error'] : 'PIN không đúng.';
			}
		}
		echo self::dau( 'Quản trị Chấm Công' );
		echo '<div class="bo" style="max-width:420px;padding-top:56px">';
		echo '<div class="the">';
		echo '<h2>Quản trị Chấm Công</h2>';
		echo '<p class="mo">Đăng nhập bằng PIN chấm công. Chỉ <b>Admin</b> và <b>Quản lý</b> vào được.</p>';
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
		echo '</div></div></body></html>';
	}

	private static function trang_chinh( $toi, $bao ) {
		global $wpdb;
		$ky  = self::chu_ky( (string) $_COOKIE[ self::COOKIE ] );
		$la  = 'Admin' === $toi['role'];
		$bang = VHCC_DB::t( 'nhan_vien' );
		$tong = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bang" );

		$GLOBALS['VHCC_FORM_ROI'] = '';

		echo self::dau( 'Quản trị Chấm Công' );
		echo '<header><div class="bo"><h1>Quản trị Chấm Công</h1>'
			. '<span class="mo">' . esc_html( $toi['name'] . ' · ' . $toi['role'] ) . '</span>'
			. '<form method="post" style="margin:0"><input type="hidden" name="ky" value="' . esc_attr( $ky ) . '">'
			. '<button name="viec" value="thoat">Thoát</button></form></div></header>';
		echo '<div class="bo">';

		$bao = array_merge( self::lay_bao(), (array) $bao );
		foreach ( $bao as $b ) { self::ve_bao( $b ); }

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
			echo '</div></body></html>';
			return;
		}

		self::the_nap_csv( $ky, $tong );
		self::the_tai_khoan( $ky, $la );
		self::the_ho_so( $ky, $toi );
		if ( $la ) { self::the_xoa_het( $ky, $tong ); }

		echo '</div></body></html>';
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
				. ( in_array( $vt, VHCC_Auth::vai_tro_vao(), true ) ? '' : ' — không vào được' ) . '</option>';
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
		if ( '' !== $xem_pin && 'Admin' !== $toi['role'] ) { $xem_pin = ''; }
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
				. ( in_array( $vt_h, VHCC_Auth::vai_tro_vao(), true ) ? '' : ' (không vào được)' )
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
					. ( in_array( $vt_c, VHCC_Auth::vai_tro_vao(), true ) ? '' : ' (không vào được)' )
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
				if ( 'Admin' === $toi['role'] ) {
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

		if ( 'Admin' === $toi['role'] ) {
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
							. ( in_array( $vt, VHCC_Auth::vai_tro_vao(), true ) ? '' : ' (không vào được)' )
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
