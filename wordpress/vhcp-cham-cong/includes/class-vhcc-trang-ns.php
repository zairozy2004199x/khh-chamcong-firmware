<?php
/**
 * TRANG /nhan-su/ — QUẢN LÝ NHÂN SỰ: AI VÀO ĐƯỢC TRANG NÀO.
 *
 * Anh Thắng 26/08/2026: *"Giờ anh muốn tạo 1 trang Quản lý nhân sự riêng, để cấu hình nhân sự
 * có thể xem những trang nào trong tất cả các trang anh làm"* — *"để điều phối nó dễ hơn"*.
 *
 * =============================================================================================
 * 🔴 TRANG NÀY KHÔNG GIỮ LUẬT. `VHCC_Cong` GIỮ.
 * =============================================================================================
 * Ở đây chỉ có bảng, ô chọn và nút Lưu. Mọi câu "người này vào được trang kia không" đều hỏi
 * `VHCC_Cong::duoc_vao()`. Viết lại luật ở đây cho nhanh là có HAI bộ luật cho cùng một câu
 * hỏi, rồi hôm nào sửa một bên là màn hình nói một đằng còn cửa vào cho một nẻo — mà đúng loại
 * lệch đó thì không ai phát hiện, vì cả hai bên đều "chạy".
 *
 * =============================================================================================
 * 🔴 KHÔNG MỘT DÒNG SCRIPT NÀO — như mọi màn quản trị khác của hệ.
 * =============================================================================================
 * `test-cham-cong.php` canh: tệp này không có thẻ script nào, không có thuộc tính `on...=`. Bảng
 * người × trang là ô `<select>` thuần và một nút Lưu; gập/xổ dùng `<details>` của chính HTML.
 *
 * ⚠️ CÓ PHÂN TRANG, NÊN LƯU CHỈ ĐỘNG VÀO NGƯỜI ĐANG HIỆN. Xem `VHCC_Cong::luu_nhieu()` — ghi
 *    đè cả sổ bằng một lát cắt 50 người là xoá sạch ngoại lệ của những người còn lại.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_TrangNS {

	const SLUG_MD = 'nhan-su';
	/** Bao nhiêu người một trang. 50 × 7 trang = 350 ô chọn — đã là nhiều cho một lượt tải. */
	const MOI_TRANG = 50;

	public static function slug() {
		$s = get_option( 'vhcc_slug_ns' );
		$s = $s ? sanitize_title( $s ) : self::SLUG_MD;
		return $s ? $s : self::SLUG_MD;
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhcc_ns', '1', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhcc_ns=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function query_vars( $v ) { $v[] = 'vhcc_ns'; return $v; }

	/**
	 * Cửa vào. `exit` nằm ở ĐÂY, không nằm trong `phuc_vu()` — bộ thử gọi được `phuc_vu()` mà
	 * không tự giết lượt chạy. Cùng luật với `VHCC_Web::maybe_render`.
	 */
	public static function maybe_render() {
		$is = ( (int) get_query_var( 'vhcc_ns' ) === 1 );
		if ( ! $is && isset( $_GET['vhcc_ns'] ) && '1' === $_GET['vhcc_ns'] ) { $is = true; }
		if ( ! $is ) { return; }
		nocache_headers();
		self::phuc_vu();
		exit;
	}

	/* ==================================================================== phiên & cửa */

	/**
	 * NGƯỜI ĐƯỢC VÀO TRANG NÀY — bậc Kế toán trở lên.
	 *
	 * 🔴 Cùng bậc với `VHCC_Cong::dat()`. Hai cửa lệch nhau thì có người vào được màn, tích
	 *    đủ thứ, bấm Lưu rồi nhận một câu chối — mà không hiểu vì sao mình lại nhìn thấy cả
	 *    cái bảng ấy ngay từ đầu.
	 *
	 * ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi (luật `tools/test/kiem-goi-cheo.php`).
	 */
	public static function toi() {
		if ( ! class_exists( 'VHCC_Web' ) || ! method_exists( 'VHCC_Web', 'nguoi_vao' ) ) { return null; }
		$u = VHCC_Web::nguoi_vao();
		if ( ! $u ) { return null; }
		return VHCC_Vai::duoc( $u, 'ho_so' ) ? $u : null;
	}

	/** Chưa đủ quyền thì nói ra CÂU GÌ. Tách khỏi `toi()` để phần chối cũng thử được. */
	public static function vi_sao_khong_vao( $u ) {
		if ( ! $u ) { return 'Chưa đăng nhập.'; }
		if ( VHCC_Vai::duoc( $u, 'ho_so' ) ) { return ''; }
		return 'Trang Quản lý nhân sự cần vai Kế toán trở lên. Tài khoản '
			. (string) ( isset( $u['name'] ) ? $u['name'] : '' ) . ' đang là '
			. VHCC_Vai::ten( $u ) . '.';
	}

	private static function ky() {
		$tok = isset( $_COOKIE[ VHCC_Web::COOKIE ] ) ? (string) $_COOKIE[ VHCC_Web::COOKIE ] : '';
		return VHCC_Web::chu_ky( $tok );
	}

	private static function ky_dung() {
		$tok = isset( $_COOKIE[ VHCC_Web::COOKIE ] ) ? (string) $_COOKIE[ VHCC_Web::COOKIE ] : '';
		$gui = isset( $_POST['ky'] ) ? (string) wp_unslash( $_POST['ky'] ) : '';
		return ( '' !== $tok && '' !== $gui && hash_equals( VHCC_Web::chu_ky( $tok ), $gui ) );
	}

	/* ==================================================================== địa chỉ & báo */

	/** Tham số phải sống sót qua một lượt POST — bộ lọc và số trang. */
	/* `sua_o` = mã người đang mở hàng sửa. Nó phải nằm trong THAM_SO để sống sót qua lượt POST —
	   thiếu thì lưu xong hàng tự đóng, mà anh Thắng đang muốn sửa tiếp mấy ô nữa. */
	const THAM_SO = array( 'ncs', 'nq', 'nvai', 'np', 'sua_o' );

	private static function url_hien() {
		$them = array();
		foreach ( self::THAM_SO as $k ) {
			$v = '';
			if ( isset( $_POST[ $k ] ) )    { $v = (string) wp_unslash( $_POST[ $k ] ); }
			elseif ( isset( $_GET[ $k ] ) ) { $v = (string) wp_unslash( $_GET[ $k ] ); }
			$v = sanitize_text_field( $v );
			if ( '' !== $v ) { $them[ $k ] = $v; }
		}
		return $them ? add_query_arg( $them, self::url() ) : self::url();
	}

	/** Ô ẩn chở bộ lọc qua một lượt POST — thiếu nó là lưu xong nhảy về trang 1 không lọc. */
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
		$tok = isset( $_COOKIE[ VHCC_Web::COOKIE ] ) ? (string) $_COOKIE[ VHCC_Web::COOKIE ] : '';
		return 'vhcc_ns_bao_' . md5( $tok );
	}

	private static function cat_bao( $bao ) { if ( $bao ) { set_transient( self::khoa_bao(), $bao, 120 ); } }

	/** Lấy ra và XOÁ — kết quả hiện MỘT lần, không dính lại ở lượt tải sau. */
	private static function lay_bao() {
		$b = get_transient( self::khoa_bao() );
		if ( false === $b ) { return array(); }
		delete_transient( self::khoa_bao() );
		return is_array( $b ) ? $b : array();
	}

	private static function ve( $url ) {
		wp_safe_redirect( $url );
		/* Bộ thử chạy trong CÙNG tiến trình — `exit` ở đây là giết luôn bài kiểm. */
		if ( defined( 'VHCC_TEST' ) ) { return; }
		exit;
	}

	/* ==================================================================== phục vụ */

	public static function phuc_vu() {
		$toi = self::toi();
		if ( ! $toi ) { self::trang_choi(); return; }

		/* 🔴 POST → CHUYỂN HƯỚNG → GET. Bấm F5 sau khi Lưu không được lưu lại lần nữa, và bộ
		   lọc / số trang không được biến mất. Cùng luật với `VHCC_Web::phuc_vu()`. */
		/* ⚠️ NÚT ÁP CẢ CỘT KHÔNG GỬI `viec`. Một biểu mẫu chỉ gửi tên/giá trị của ĐÚNG cái nút
		   vừa bấm — bấm nút cột thì `viec` (của nút Lưu) không có mặt. Chỉ nghe mỗi `viec` là
		   nút cột bấm xong không xảy ra gì cả, và không có gì báo. */
		$viec_gui = '';
		if ( isset( $_POST['viec'] ) )     { $viec_gui = (string) wp_unslash( $_POST['viec'] ); }
		elseif ( isset( $_POST['cot'] ) )  { $viec_gui = 'ap_cot'; }
		/* Nút Xoá cũng không gửi `viec` — nó mang tên riêng `xoa_ma` để CHỈ lượt bấm đúng nó
		   mới kéo theo một mã. Cùng lý do với nút áp cả cột ở trên. */
		elseif ( isset( $_POST['xoa_ma'] ) ) { $viec_gui = 'xoa_hs'; }
		/* Nút "Ghép với hồ sơ kia" ngay tại dòng "một người hai hồ sơ?" — anh Thắng 29/08/2026,
		   sau khi thấy bảng "Ghép hai mã" đứng riêng vẫn bị chê là "còn hai bảng" dù đã gộp
		   chung khung: *"cùng 1 nv có khác gì đâu"*, *"chả khác gì, cùng mã thì ghép lại thôi"*.
		   Nút này SỐNG NGAY TRONG bảng chính (cùng `<form>` với "Lưu bảng này"), mang tên riêng
		   `ghep_voi` — CHỈ nút vừa bấm góp mặt trong `$_POST`, không đụng các ô quyền khác trong
		   cùng form, y hệt cách `xoa_ma`/`cot` đã làm ở trên. Xem viec_ghep_voi(). */
		elseif ( isset( $_POST['ghep_voi'] ) ) { $viec_gui = 'ghep_voi_nv'; }

		if ( ! empty( $_POST ) && '' !== $viec_gui ) {
			$bao = self::ky_dung()
				? self::lam_viec( sanitize_text_field( $viec_gui ), $toi )
				: array( array( 'loi' => 'Phiên đã hết hoặc biểu mẫu không hợp lệ. Tải lại trang rồi làm lại.' ) );
			self::cat_bao( $bao );
			self::ve( self::url_hien() );
		}
		self::trang_chinh( $toi );
	}

	/**
	 * Việc làm được ở trang này. Danh sách TRẮNG, mặc định CHỐI.
	 *
	 * ⚠️ Không có nhánh `default` nào "cho qua". Thêm việc mới mà quên khai vào đây thì nó bị
	 *    chối — thấy ngay. Ngược lại là thêm một cửa không ai gác.
	 */
	public static function lam_viec( $viec, $toi ) {
		if ( 'luu_quyen' === $viec )   { return self::viec_luu( $toi ); }
		if ( 'ap_cot' === $viec )      { return self::viec_cot( $toi ); }
		if ( 'go_ngoai_le' === $viec ) { return self::viec_go( $toi ); }
		if ( 'sua_nhanh' === $viec )   { return self::viec_sua_nhanh( $toi ); }
		if ( 'them_vai' === $viec )    { return self::viec_them_vai( $toi ); }
		if ( 'dau_viec' === $viec )    { return self::viec_dau_viec( $toi ); }
		if ( 'xoa_vai' === $viec )     { return self::viec_xoa_vai( $toi ); }
		if ( 'ghe_rieng' === $viec )   { return self::viec_ghe_rieng( $toi ); }
		if ( 'quyen_noi_bo' === $viec ) { return self::viec_quyen_noi_bo( $toi ); }
		if ( 'ghep_ma' === $viec )     { return self::viec_ghep_ma( $toi ); }
		if ( 'ghep_voi_nv' === $viec ) { return self::viec_ghep_voi( $toi ); }
		if ( 'bo_ghep_ma' === $viec )  { return self::viec_bo_ghep_ma( $toi ); }
		if ( 'don_ma' === $viec )      { return self::viec_don_ma( $toi ); }
		if ( 'xoa_hs' === $viec )      { return self::viec_xoa_hs( $toi ); }
		return array( array( 'loi' => 'Không biết việc "' . $viec . '".' ) );
	}

	/**
	 * Đọc bảng ô quyền từ biểu mẫu, đã rửa sạch.
	 *
	 * ⚠️ Tách ra vì CẢ HAI nút — Lưu và Áp cả cột — đều gửi lên cùng một bảng `o[]`. Đọc riêng
	 *    mỗi chỗ một kiểu là sớm muộn hai chỗ rửa khác nhau.
	 */
	private static function doc_o() {
		$o = isset( $_POST['o'] ) ? wp_unslash( $_POST['o'] ) : array();
		if ( ! is_array( $o ) ) { return array(); }
		$sach = array();
		foreach ( $o as $ma => $cac ) {
			if ( ! is_array( $cac ) ) { continue; }
			$ma_s = sanitize_text_field( (string) $ma );
			if ( '' === $ma_s ) { continue; }
			foreach ( $cac as $trang => $dat ) {
				$sach[ $ma_s ][ sanitize_key( (string) $trang ) ] = sanitize_text_field( (string) $dat );
			}
		}
		return $sach;
	}

	/**
	 * TÁCH CỘT GHẾ RA KHỎI BẢNG Ô, TRẢ VỀ [bảng còn lại, bảng ghế].
	 *
	 * 🔴 HAI SỔ, HAI HÀM LƯU. Cột ghế đi chung một biểu mẫu với ba cột kia cho tiện tay người
	 *    bấm, nhưng bên dưới nó ghi sang một chỗ khác hẳn (`vhg_nguoidung`, không phải sổ ngoại
	 *    lệ). Để nguyên nó trong bảng giao cho `VHCC_Cong::luu_nhieu()` thì hàm ấy bỏ qua vì
	 *    không có trang tên "ghe" trong sổ của nó — bỏ qua IM LẶNG, và người bấm thấy "đã lưu"
	 *    trong khi không ai được đẩy đi đâu cả.
	 */
	private static function viec_quyen_noi_bo( $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'ho_so' ) ) {
			return array( array( 'loi' => 'Khai quyền trang Nội bộ cần vai Kế toán trở lên.' ) );
		}
		/* ⚠️ Gác CÙNG HÀM với lời gọi sang plugin khác. */
		if ( ! class_exists( 'VHNB_Quyen' ) || ! method_exists( 'VHNB_Quyen', 'dat' ) ) {
			return array( array( 'loi' => 'Chưa cài plugin Nội bộ trên site này.' ) );
		}
		$gui = isset( $_POST['nb'] ) ? wp_unslash( $_POST['nb'] ) : array();
		if ( ! is_array( $gui ) ) { return array( array( 'loi' => 'Biểu mẫu không hợp lệ.' ) ); }
		$sach = array();
		foreach ( $gui as $k => $v ) {
			$sach[ sanitize_key( (string) $k ) ] = sanitize_text_field( (string) $v );
		}
		/* Luật nằm ở `VHNB_Quyen::dat()` — nó tự bỏ việc lạ và bậc lạ. Đây không lọc lại. */
		$moi = VHNB_Quyen::dat( $sach );

		/* 🔴 NÓI RA CHỖ VỪA ĐÓNG CỬA. Siết ô "Vào trang Nội bộ" lên trên Nhân viên là đóng cửa
		   cả trang với mọi người dưới bậc ấy — đúng chuyện anh Thắng vừa vấp, và nó im lặng cho
		   tới khi có người bị chối. */
		$bao = array( array( 'ok' => 'Đã lưu phân quyền trang Nội bộ.' ) );
		$vao = isset( $moi['vao'] ) ? (string) $moi['vao'] : '';
		if ( '' !== $vao && VHCC_Vai::NV !== $vao ) {
			$bac = constant( 'VHNB_Quyen::BAC_DS' );
			$bao[] = array( 'canh' => 'Ô "Vào trang Nội bộ" đang đặt là '
				. ( isset( $bac[ $vao ] ) ? $bac[ $vao ] : $vao )
				. ' — mọi người dưới bậc ấy sẽ KHÔNG vào được trang Nội bộ nữa.' );
		}
		return $bao;
	}

	private static function viec_ghep_ma( $toi ) {
		$p = function ( $k ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		};
		$kq = VHCC_NhanSu::khai_ma_song_song( $toi, $p( 'ma_a' ), $p( 'ma_b' ),
			$p( 'gm_ten' ), $p( 'gm_ly_do' ) );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã ghép ' . $p( 'ma_b' ) . ' về ' . $p( 'ma_a' )
			. '. Lượt chấm công của mã phụ nay chảy về mã chính.' ) );
	}

	/**
	 * GHÉP NHANH NGAY TẠI DÒNG "một người hai hồ sơ?" — không phải gõ tay ở khối "Ghép hai mã".
	 *
	 * Anh Thắng 29/08/2026, sau ba lượt chỉnh chỗ đặt/khung của bảng "Ghép hai mã" vẫn chưa vừa
	 * ý: *"cùng 1 nv có khác gì đâu"*, *"chả khác gì, cùng mã thì ghép lại thôi"*. Đúng — cái anh
	 * cần không phải là chỗ ĐẶT bảng, mà là ĐỠ PHẢI gõ tay hai mã vào một bảng khác. Nút này gộp
	 * thẳng cặp mã ĐÃ BỊ HỆ PHÁT HIỆN (nhãn "một người hai hồ sơ?"), không bắt gõ lại.
	 *
	 * 🔴 GIÁ TRỊ NÚT MANG SẴN "MÃ_CHÍNH|MÃ_PHỤ", KHÔNG NHẬN TỪ Ô NHẬP TỰ DO. Nút được vẽ ở
	 *    `the_bang()` với đúng cặp mã hệ đã dò ra (dau_hieu_trung()['doi']), thứ tự chính/phụ đã
	 *    tính sẵn theo AI CÓ CHẤM CÔNG NHIỀU HƠN (xem the_bang()) — người bấm không tự gõ mã nên
	 *    không có đường gõ nhầm mã của người khác. Vẫn đi qua khai_ma_song_song() để giữ NGUYÊN
	 *    mọi chốt đã có (bậc quyền, hai mã không được trùng nhau, cặp chưa từng khai).
	 */
	private static function viec_ghep_voi( $toi ) {
		$raw = isset( $_POST['ghep_voi'] ) ? sanitize_text_field( wp_unslash( $_POST['ghep_voi'] ) ) : '';
		$cap = explode( '|', $raw, 2 );
		$a   = isset( $cap[0] ) ? trim( $cap[0] ) : '';
		$b   = isset( $cap[1] ) ? trim( $cap[1] ) : '';
		if ( '' === $a || '' === $b ) { return array( array( 'loi' => 'Thiếu mã cần ghép.' ) ); }
		/* Tên lấy THẲNG TỪ HỒ SƠ (mã chính), không tin chuỗi client gửi lên — hai hồ sơ trong
		   một cặp "một người hai hồ sơ?" vốn đã cùng tên (đó là lý do bị gắn nhãn), nên lấy tên
		   của bên nào cũng ra cùng một chuỗi. */
		$hs  = VHCC_NhanSu::ho_so( $a );
		$ten = $hs ? trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ) : '';
		$kq  = VHCC_NhanSu::khai_ma_song_song( $toi, $a, $b, $ten,
			'một người hai hồ sơ — ghép nhanh từ bảng nhân sự' );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã ghép ' . $b . ' về ' . $a
			. '. Lượt chấm công của ' . $b . ' nay chảy về ' . $a . '.' ) );
	}

	private static function viec_don_ma( $toi ) {
		$a = isset( $_POST['ma_a'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_a'] ) ) : '';
		$b = isset( $_POST['ma_b'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_b'] ) ) : '';
		$kq = VHCC_NhanSu::don_ma( $toi, $a, $b );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$c = (int) $kq['chuyen'];
		$g = (int) $kq['gop'];
		if ( ! $c && ! $g ) {
			return array( array( 'canh' => 'Mã ' . $b . ' không còn hàng nào để dồn.' ) );
		}
		/* ⚠️ Kể riêng hai con số: "chuyển" là đổi mã, "gộp" là hai hàng cùng ngày nhập làm một —
		   người đọc cần biết có bao nhiêu ngày bị nhập lại, vì đó là chỗ giờ có thể đổi. */
		return array( array( 'ok' => 'Đã dồn ' . $b . ' về ' . $a . ': chuyển ' . $c . ' hàng'
			. ( $g ? ', gộp ' . $g . ' hàng trùng ngày (giờ vào lấy sớm nhất, giờ ra lấy muộn nhất)' : '' )
			. '. Lưới nay chỉ còn một dòng cho người này.' ) );
	}

	/**
	 * XOÁ HẲN MỘT HỒ SƠ — nhịp cuối, sau khi hàng hỏi ở `hang_xoa()` đã nói ra cái sẽ mất.
	 *
	 * ⚠️ ĐI QUA `VHCC_NhanSu::xoa_ho_so()`, KHÔNG GỌI `$wpdb->delete` ở đây. Hai chốt nằm trong
	 *    hàm ấy: bậc Admin, và "còn lượt chấm công thì chối". Đây là cửa THỨ HAI vào cùng một
	 *    việc (cửa thứ nhất ở wp-admin) — cửa thứ hai mà tự xoá lấy là cửa không ai gác.
	 */
	private static function viec_xoa_hs( $toi ) {
		$ma = isset( $_POST['xoa_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['xoa_ma'] ) ) : '';
		if ( '' === $ma ) { return array( array( 'loi' => 'Thiếu Mã NV cần xoá.' ) ); }
		$hs  = VHCC_NhanSu::ho_so( $ma );
		$ten = $hs ? trim( (string) ( isset( $hs['ho_ten'] ) ? $hs['ho_ten'] : '' ) ) : '';

		/* 🔴 GỠ BẢN SAO Ở HAI HỆ KIA TRƯỚC, VÀ CHỈ KHI XOÁ ĐÃ CHẮC CHẮN ĐI QUA ĐƯỢC.
		   Người đã đẩy sang hệ Ghế / Vận hành chi phí thì bên ấy còn một dòng người dùng mang
		   mã này. Xoá sổ nhân sự mà để lại hai dòng đó là còn hai đường đăng nhập trỏ vào một
		   người không còn hồ sơ — không ai nhìn thấy, và không ai gỡ.
		   Nhưng gỡ TRƯỚC khi biết xoá có được không thì gặp người còn chấm công: xoá bị chối,
		   mà đường đăng nhập của họ đã mất. Nên hỏi `xoa_ho_so()` trước bằng chính phép đếm nó
		   dùng, rồi mới gỡ. */
		$so = VHCC_NhanSu::so_luot_cham( $ma );
		if ( $so > 0 || ! VHCC_Vai::duoc( $toi, 'xoa_ho_so' ) ) {
			$kq = VHCC_NhanSu::xoa_ho_so( $toi, $ma );   // để chính nó nói ra lời chối
			return array( array( 'loi' => isset( $kq['error'] ) ? $kq['error'] : 'Không xoá được.' ) );
		}
		$go = array();
		if ( VHCC_DayGhe::da_day( $ma ) ) {
			$r = VHCC_DayGhe::dat( $toi, $ma, false );
			if ( ! empty( $r['ok'] ) ) { $go[] = 'hệ Ghế'; }
		}
		if ( class_exists( 'VHCC_DayChiPhi' ) && method_exists( 'VHCC_DayChiPhi', 'da_day' )
			&& method_exists( 'VHCC_DayChiPhi', 'dat' ) && VHCC_DayChiPhi::da_day( $ma ) ) {
			$r = VHCC_DayChiPhi::dat( $toi, $ma, false );
			if ( ! empty( $r['ok'] ) ) { $go[] = 'Vận hành chi phí'; }
		}

		$kq = VHCC_NhanSu::xoa_ho_so( $toi, $ma );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã xoá hẳn hồ sơ ' . $ma
			. ( '' !== $ten ? ' — ' . $ten : '' ) . '.'
			. ( $go ? ' Đã gỡ luôn khỏi ' . implode( ' và ', $go ) . '.' : '' ) ) );
	}

	private static function viec_bo_ghep_ma( $toi ) {
		$a = isset( $_POST['ma_a'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_a'] ) ) : '';
		$b = isset( $_POST['ma_b'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_b'] ) ) : '';
		$kq = VHCC_NhanSu::bo_ma_song_song( $toi, $a, $b );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		/* ⚠️ Nói ra HẬU QUẢ, đừng chỉ báo "đã bỏ". Bỏ ghép là từ đây lượt của mã phụ lại ở
		   nguyên chỗ nó — công của người ấy tách làm hai trở lại. */
		return array( array( 'ok' => 'Đã bỏ ghép ' . $b . ' khỏi ' . $a
			. '. Từ giờ lượt chấm công của ' . $b . ' lại nằm riêng, không chảy về nữa.' ) );
	}

	private static function viec_ghe_rieng( $toi ) {
		$kq = VHCC_DayGhe::chuyen_sang_rieng( $toi );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		if ( isset( $kq['note'] ) ) { return array( array( 'canh' => $kq['note'] ) ); }
		$chep = (int) $kq['chep'];
		return array( array( 'ok' => 'Hệ ghế nay dùng SỔ RIÊNG'
			. ( $chep ? ' — đã chép sẵn ' . $chep . ' người đang đăng nhập được sang, để không ai '
				. 'bị đá ra.' : '. Sổ chung vốn không có ai nên không phải chép.' ) ) );
	}

	private static function tach_ghe( $sach ) {
		$ghe = array();
		$chi_phi = array();
		foreach ( $sach as $ma => $cac ) {
			if ( ! is_array( $cac ) ) { continue; }
			/* Hai cột này KHÔNG phải ngoại lệ quyền — chúng đẩy người thật sang hệ khác. Tách ra
			   trước khi phần còn lại đi vào sổ ngoại lệ, không thì `VHCC_Cong::dat()` chối chúng
			   bằng câu "không có trang tên ghe/chi_phi" và người bấm không hiểu vì sao. */
			if ( array_key_exists( VHCC_DayGhe::COT, $cac ) ) {
				$ghe[ $ma ] = (string) $cac[ VHCC_DayGhe::COT ];
				unset( $sach[ $ma ][ VHCC_DayGhe::COT ] );
			}
			if ( array_key_exists( VHCC_DayChiPhi::COT, $cac ) ) {
				$chi_phi[ $ma ] = (string) $cac[ VHCC_DayChiPhi::COT ];
				unset( $sach[ $ma ][ VHCC_DayChiPhi::COT ] );
			}
		}
		return array( $sach, $ghe, $chi_phi );
	}

	/** Đẩy/gỡ sang app Vận hành chi phí, rồi kể lại thành mấy dòng báo. */
	private static function luu_chi_phi( $toi, $ds ) {
		if ( ! $ds || ! self::cot_chi_phi( $toi ) ) { return array(); }
		$kq = VHCC_DayChiPhi::luu_nhieu( $toi, $ds );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$bao = array();
		if ( ! empty( $kq['doi'] ) ) {
			$bao[] = array( 'ok' => 'Vận hành chi phí: đã đẩy/gỡ ' . (int) $kq['doi'] . ' người. '
				. 'Họ đăng nhập /chi-phi bằng chính PIN chấm công.' );
		}
		foreach ( (array) $kq['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		return $bao;
	}

	/** Đẩy/gỡ theo bảng vừa tách, rồi kể lại thành mấy dòng báo. */
	private static function luu_ghe( $toi, $ghe ) {
		if ( ! $ghe || ! self::cot_ghe( $toi ) ) { return array(); }
		$kq  = VHCC_DayGhe::luu_nhieu( $toi, $ghe );
		$bao = array();
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		if ( ! empty( $kq['doi'] ) ) {
			$bao[] = array( 'ok' => 'Hệ ghế: đã đẩy/gỡ ' . (int) $kq['doi'] . ' người.' );
		}
		foreach ( (array) $kq['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		/* ⚠️ ĐẨY XONG MÀ HỆ GHẾ ĐANG ĐỌC SỔ KHÁC LÀ ĐẨY VÀO HƯ KHÔNG — nhắc lại ngay tại chỗ
		   vừa bấm, đừng bắt người ta cuộn lên tìm dải cảnh báo ở đầu trang. */
		if ( ! empty( $kq['doi'] ) && ! VHCC_DayGhe::nguon_dung() ) {
			$bao[] = array( 'canh' => 'Nhưng hệ ghế đang đọc SỔ CHUNG với app Chi phí, nên mấy '
				. 'người vừa đẩy CHƯA đăng nhập được. Bấm nút chuyển nguồn ở đầu trang.' );
		}
		return $bao;
	}

	private static function viec_luu( $toi ) {
		$sach = self::doc_o();
		if ( ! $sach ) { return array( array( 'loi' => 'Biểu mẫu không hợp lệ.' ) ); }
		list( $sach, $ghe, $chi_phi ) = self::tach_ghe( $sach );
		$bao_ghe = array_merge( self::luu_ghe( $toi, $ghe ), self::luu_chi_phi( $toi, $chi_phi ) );
		$kq = VHCC_Cong::luu_nhieu( $toi, $sach );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }

		/* 🔴 CHUYỂN CƠ SỞ CHẠY TRƯỚC LƯU QUYỀN THÌ SAI THỨ TỰ. Chuyển cơ sở RESET sạch ngoại lệ
		   của người ấy; chạy trước là nó xoá luôn mấy ô quyền vừa lưu ở ngay lượt này, mà màn
		   hình vẫn báo "đã lưu N ô". Nên: quyền trước, cơ sở sau — ai vừa bị chuyển thì quyền
		   của họ về mặc định, và đó đúng là điều anh Thắng muốn. */
		$vai = self::luu_vai( $toi );
		$cs  = self::luu_coso( $toi );
		$doi = (int) $kq['doi'];

		$bao = array();
		if ( $doi )          { $bao[] = array( 'ok' => 'Đã lưu ' . $doi . ' ô quyền vào trang.' ); }
		if ( $vai['doi'] )   { $bao[] = array( 'ok' => 'Đã đổi vai trò cho ' . $vai['doi'] . ' người.' ); }
		if ( $cs['doi'] ) {
			$bao[] = array( 'ok' => 'Đã chuyển ' . $cs['doi'] . ' người sang cơ sở khác'
				. ( $cs['go'] ? ' — quyền riêng của họ đã reset về mặc định (' . $cs['go'] . ' ô).'
					: '. Họ vốn không có quyền riêng nào nên không có gì phải reset.' ) );
		}
		foreach ( $cs['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		/* 🔴 LỖI VAI TRÒ PHẢI HIỆN RA, KHÔNG ĐƯỢC NUỐT. Mấy chốt trong `dat_vai_tro()` (không
		   nâng quá bậc mình, không đụng người trên mình, không tự sửa mình) chỉ có tác dụng nếu
		   người bấm ĐỌC ĐƯỢC câu chối. Nuốt đi thì màn hình báo "đã lưu", ô vai trở về giá trị
		   cũ, và người ta tưởng hệ thống hỏng chứ không biết mình vừa bị chặn. */
		foreach ( $vai['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		foreach ( $bao_ghe as $b ) { $bao[] = $b; }
		if ( ! $bao ) { return array( array( 'canh' => 'Không có ô nào đổi — chưa lưu gì.' ) ); }
		return $bao;
	}

	/**
	 * Chuyển cơ sở cho những người vừa đổi ô Cơ sở.
	 *
	 * ⚠️ ĐI QUA `VHCC_NhanSu::dat_co_so()` TỪNG NGƯỜI. Chốt bậc, chốt phụ trách cả hai cơ sở, và
	 *    bước RESET ngoại lệ quyền đều nằm trong hàm ấy — ghi tắt ở đây là bỏ cả ba.
	 */
	private static function luu_coso( $toi ) {
		$gui = isset( $_POST['cs'] ) ? wp_unslash( $_POST['cs'] ) : array();
		$ra  = array( 'doi' => 0, 'go' => 0, 'loi' => array() );
		if ( ! is_array( $gui ) ) { return $ra; }
		foreach ( $gui as $ma => $v ) {
			$ma_s = sanitize_text_field( (string) $ma );
			$v_s  = sanitize_text_field( is_array( $v ) ? '' : (string) $v );
			if ( '' === $ma_s ) { continue; }
			$r = VHCC_NhanSu::dat_co_so( $toi, $ma_s, $v_s );
			if ( empty( $r['ok'] ) ) {
				$ra['loi'][ $r['error'] ] = $ma_s . ': ' . $r['error'];
				continue;
			}
			if ( ! empty( $r['doi'] ) ) {
				$ra['doi']++; $ra['go'] += (int) $r['go'];
				/* Cơ sở đổi thì bản sao bên hệ ghế phải theo: cơ sở là thứ quyết định người ấy
				   chốt ca được ở đâu, nên để lệch là họ chốt nhầm ghế của cơ sở cũ. */
				VHCC_DayGhe::dong_bo( $ma_s );
				/* Bản sao bên Vận hành chi phí cũng phải theo — cùng lý do, cùng lúc. */
				if ( class_exists( 'VHCC_DayChiPhi' ) && method_exists( 'VHCC_DayChiPhi', 'dong_bo' ) ) {
					VHCC_DayChiPhi::dong_bo( $ma_s );
				}
			}
		}
		$ra['loi'] = array_values( $ra['loi'] );
		return $ra;
	}

	/**
	 * Ghi những ô vai trò vừa đổi.
	 *
	 * ⚠️ ĐI QUA `VHCC_NhanSu::dat_vai_tro()` TỪNG NGƯỜI, không tự ghi thẳng vào bảng. Ba chốt
	 *    chống tự nâng quyền nằm trong hàm ấy; ghi tắt ở đây là mở một cửa thứ hai không ai gác.
	 */
	private static function luu_vai( $toi ) {
		$gui = isset( $_POST['vai'] ) ? wp_unslash( $_POST['vai'] ) : array();
		$ra  = array( 'doi' => 0, 'loi' => array() );
		if ( ! is_array( $gui ) ) { return $ra; }
		foreach ( $gui as $ma => $v ) {
			$ma_s = sanitize_text_field( (string) $ma );
			$v_s  = sanitize_text_field( is_array( $v ) ? '' : (string) $v );
			if ( '' === $ma_s ) { continue; }
			$r = VHCC_NhanSu::dat_vai_tro( $toi, $ma_s, $v_s );
			if ( empty( $r['ok'] ) ) {
				/* Gộp theo câu, không in 50 dòng giống hệt nhau: bấm nhầm một cột là 50 hàng
				   cùng trượt vì cùng một lý do, và 50 dòng báo thì không ai đọc dòng nào. */
				$ra['loi'][ $r['error'] ] = $ma_s . ': ' . $r['error'];
				continue;
			}
			if ( ! empty( $r['doi'] ) ) {
				$ra['doi']++;
				/* Vai đổi thì bản sao bên hệ ghế phải theo — xem `VHCC_DayGhe::dong_bo()`. Người
				   chưa đẩy sang thì hàm ấy không đụng tới. */
				VHCC_DayGhe::dong_bo( $ma_s );
				/* Bản sao bên Vận hành chi phí cũng phải theo — cùng lý do, cùng lúc. */
				if ( class_exists( 'VHCC_DayChiPhi' ) && method_exists( 'VHCC_DayChiPhi', 'dong_bo' ) ) {
					VHCC_DayChiPhi::dong_bo( $ma_s );
				}
			}
		}
		$ra['loi'] = array_values( $ra['loi'] );
		return $ra;
	}

	/**
	 * ÁP MỘT GIÁ TRỊ CHO CẢ MỘT CỘT — những người đang hiện trên màn.
	 *
	 * 🔴 ĐÈ LÊN BẢNG VỪA GỬI, KHÔNG THAY NÓ. Người ta có thể đã bấm tay vài ô ở cột KHÁC rồi
	 *    mới bấm nút cột này. Bỏ `o[]` đi mà chỉ ghi mỗi cột được bấm thì mấy ô kia im lặng
	 *    mất — người dùng thấy nút mình bấm chạy đúng, nên không ai nghĩ tới chuyện đi kiểm
	 *    lại mấy ô đã đổi trước đó.
	 *
	 * ⚠️ Danh sách người lấy từ CHÍNH `o[]` chứ không phải một ô ẩn riêng: `o[]` vốn đã có đúng
	 *    những người đang hiện, nên không cần cuốn sổ thứ hai để rồi lệch với cuốn thứ nhất.
	 */
	private static function viec_cot( $toi ) {
		$gui = isset( $_POST['cot'] ) ? sanitize_text_field( wp_unslash( $_POST['cot'] ) ) : '';
		$phan = explode( '|', $gui, 2 );
		$trang = sanitize_key( isset( $phan[0] ) ? $phan[0] : '' );
		$dat   = isset( $phan[1] ) ? (string) $phan[1] : '';
		if ( ! in_array( $dat, array( 'mo', 'khoa', '' ), true ) ) {
			return array( array( 'loi' => 'Chỉ nhận: mo · khoa · (trống).' ) );
		}
		/* Cột ghế đi đường riêng — xem `tach_ghe()`. Chặn ở đây chứ đừng để nó rơi xuống
		   `VHCC_Cong::co()`: khoá "ghe" không có trong sổ trang, nên nó sẽ bị chối bằng một câu
		   sai hẳn ("Không có trang ghe trên site này") trong khi cột ấy đang hiện rành rành. */
		if ( VHCC_DayChiPhi::COT === $trang ) {
			if ( ! self::cot_chi_phi( $toi ) ) {
				return array( array( 'loi' => 'Đẩy người sang hệ Vận hành chi phí cần vai Admin.' ) );
			}
			$sach = self::doc_o();
			if ( ! $sach ) { return array( array( 'loi' => 'Không có người nào đang hiện để áp.' ) ); }
			$cp = array();
			foreach ( $sach as $ma_p => $x_p ) { $cp[ $ma_p ] = ( 'mo' === $dat ) ? 'mo' : ''; }
			$bao_p = self::luu_chi_phi( $toi, $cp );
			return $bao_p ? $bao_p : array( array( 'canh' => 'Cột "Vận hành chi phí" vốn đã như vậy '
				. 'cho ' . count( $cp ) . ' người đang hiện — không có gì đổi.' ) );
		}
		if ( VHCC_DayGhe::COT === $trang ) {
			if ( ! self::cot_ghe( $toi ) ) {
				return array( array( 'loi' => 'Đẩy người sang hệ ghế cần vai Admin.' ) );
			}
			$sach = self::doc_o();
			if ( ! $sach ) { return array( array( 'loi' => 'Không có người nào đang hiện để áp.' ) ); }
			$ghe = array();
			foreach ( $sach as $ma_g => $x_g ) { $ghe[ $ma_g ] = ( 'mo' === $dat ) ? 'mo' : ''; }
			$bao_g = self::luu_ghe( $toi, $ghe );
			return $bao_g ? $bao_g : array( array( 'canh' => 'Cột "Ghế massage" vốn đã như vậy cho '
				. count( $ghe ) . ' người đang hiện — không có gì đổi.' ) );
		}
		if ( ! VHCC_Cong::co( $trang ) ) {
			return array( array( 'loi' => 'Không có trang "' . $trang . '" trên site này.' ) );
		}
		$sach = self::doc_o();
		if ( ! $sach ) { return array( array( 'loi' => 'Không có người nào đang hiện để áp.' ) ); }
		foreach ( $sach as $ma => $cac ) { $sach[ $ma ][ $trang ] = $dat; }

		$kq = VHCC_Cong::luu_nhieu( $toi, $sach );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$ten = VHCC_Cong::ds();
		$ten = isset( $ten[ $trang ]['ten'] ) ? $ten[ $trang ]['ten'] : $trang;
		$nhan = ( 'mo' === $dat ) ? 'Mở' : ( ( 'khoa' === $dat ) ? 'Khoá' : 'Theo vai' );
		$doi  = (int) $kq['doi'];
		if ( ! $doi ) {
			return array( array( 'canh' => 'Cột "' . $ten . '" vốn đã là «' . $nhan . '» cho '
				. count( $sach ) . ' người đang hiện — không có gì đổi.' ) );
		}
		return array( array( 'ok' => 'Đã đặt «' . $nhan . '» cho cột "' . $ten . '" — '
			. $doi . ' ô đổi trên ' . count( $sach ) . ' người đang hiện.' ) );
	}

	private static function viec_sua_nhanh( $toi ) {
		$ma = isset( $_POST['ma_nv'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_nv'] ) ) : '';
		if ( '' === $ma ) { return array( array( 'loi' => 'Thiếu Mã NV.' ) ); }
		$dat = array( 'ma_nv' => $ma );
		/* Danh sách CHO PHÉP, không phải danh sách CHẶN — cùng luật với `luu_ho_so()`. Với danh
		   sách chặn thì mỗi cột mới thêm vào bảng là một ô người ta ghi được mà không ai nhớ ra
		   phải chặn. `cua_hang` và `vai_tro` CỐ Ý vắng mặt: hai thứ ấy có cửa riêng, có chốt
		   riêng (chuyển cơ sở reset quyền; đổi vai có ba chốt chống tự nâng). */
		foreach ( array( 'ho_ten', 'sdt', 'chuc_vu', 'nhiem_vu', 'ngay_vao_lam',
			'trang_thai_lam_viec', 'luong_co_ban', 'so_tai_khoan', 'ngan_hang' ) as $c ) {
			if ( isset( $_POST[ $c ] ) ) { $dat[ $c ] = sanitize_text_field( wp_unslash( $_POST[ $c ] ) ); }
		}
		/* ⚠️ Ô PIN ĐỂ TRỐNG = GIỮ NGUYÊN, không phải = xoá PIN. Gửi chuỗi rỗng xuống
		   `luu_ho_so()` là nó ghi đè thành rỗng, tức là mỗi lần sửa tên một người là xoá luôn
		   đường đăng nhập của họ — im lặng, và họ chỉ biết vào sáng hôm sau. */
		$pin = isset( $_POST['pin_dang_nhap'] ) ? trim( (string) wp_unslash( $_POST['pin_dang_nhap'] ) ) : '';
		if ( '' !== $pin ) { $dat['pin_dang_nhap'] = sanitize_text_field( $pin ); }

		$kq = VHCC_NhanSu::luu_ho_so( $toi, $dat );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		/* 🔴 NGƯỜI ĐÃ ĐẨY SANG HỆ GHẾ THÌ BẢN SAO BÊN ẤY PHẢI THEO. Đổi PIN ở đây mà bên ghế
		   giữ PIN cũ là người ta đứng ở quầy, gõ PIN mới, và cửa không mở — im lặng, và họ chỉ
		   biết khi đã hỏng việc. */
		$db_ghe = VHCC_DayGhe::dong_bo( $ma );
		/* Bản sao bên Vận hành chi phí cũng phải theo — cùng lý do, cùng lúc. */
		if ( class_exists( 'VHCC_DayChiPhi' ) && method_exists( 'VHCC_DayChiPhi', 'dong_bo' ) ) {
			VHCC_DayChiPhi::dong_bo( $ma );
		}
		$bao = array( array( 'ok' => 'Đã lưu hồ sơ ' . $ma . '.'
			. ( '' !== $pin ? ' PIN đã đổi.' : '' )
			. ( $db_ghe ? ' Hệ ghế đã cập nhật theo.' : '' ) ) );

		/* 🔴 LƯU LUÔN CẢ BẢNG — hàng sửa nhanh nằm TRONG form của bảng, nên mọi ô quyền / vai /
		   cơ sở đã tích đều gửi lên cùng lượt này. Không lưu chúng là chúng mất im lặng, và
		   người bấm thấy "Đã lưu hồ sơ" nên tin là xong. Bản trước vá bằng một câu nhắc; nhắc
		   là bắt người ta nhớ, mà cái gì bắt nhớ thì sớm muộn có người quên. */
		foreach ( self::viec_luu( $toi ) as $b ) {
			/* Bảng không có gì đổi thì `viec_luu()` trả một dòng "chưa lưu gì" — đúng cho nút
			   kia, nhưng ở đây nó nói ngược lại dòng "Đã lưu hồ sơ" ngay trên. Bỏ riêng dòng ấy. */
			if ( isset( $b['canh'] ) && false !== strpos( (string) $b['canh'], 'chưa lưu gì' ) ) { continue; }
			if ( isset( $b['loi'] ) && 'Biểu mẫu không hợp lệ.' === $b['loi'] ) { continue; }
			$bao[] = $b;
		}
		return $bao;
	}

	private static function viec_them_vai( $toi ) {
		$ten = isset( $_POST['vai_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['vai_ten'] ) ) : '';
		$goc = isset( $_POST['vai_goc'] ) ? sanitize_text_field( wp_unslash( $_POST['vai_goc'] ) ) : '';
		$kq  = VHCC_Vai::dat_them( $toi, $ten, $goc );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã khai vai "' . $ten . '" — quyền y như '
			. VHCC_Vai::TEN[ $goc ] . '. Gán được cho người ta ở cột Vai trò bên dưới.' ) );
	}

	/**
	 * Lưu / gỡ một dòng chia đầu việc.
	 *
	 * ⚠️ Ô Mã NV có gõ thì nó THẮNG ô chọn vai. Người ta gõ mã vào ô ấy là đã có ý riêng cho một
	 *    người; im lặng khai cho cả vai là làm ngược hẳn ý họ, và cả nhóm nhận quyền mà không ai
	 *    định cho.
	 */
	private static function viec_dau_viec( $toi ) {
		$ma   = isset( $_POST['dv_ma'] ) ? trim( (string) sanitize_text_field( wp_unslash( $_POST['dv_ma'] ) ) ) : '';
		$dich = isset( $_POST['dv_dich'] ) ? sanitize_text_field( wp_unslash( $_POST['dv_dich'] ) ) : '';
		if ( '' !== $ma ) { $dich = 'nv:' . $ma; }
		$q    = isset( $_POST['dv_quyen'] ) ? sanitize_text_field( wp_unslash( $_POST['dv_quyen'] ) ) : '';
		$dat  = isset( $_POST['dv_dat'] ) ? sanitize_text_field( wp_unslash( $_POST['dv_dat'] ) ) : '';
		$kq   = VHCC_Vai::dat_ngoai_le( $toi, $dich, $q, $dat );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$ai = ( 0 === strpos( $kq['dich'], 'vai:' ) )
			? 'vai "' . substr( $kq['dich'], 4 ) . '"' : 'Mã NV ' . substr( $kq['dich'], 3 );
		if ( '' === $kq['giaTri'] ) {
			return array( array( 'ok' => 'Đã gỡ dòng "' . VHCC_Vai::ten_viec( $q ) . '" của ' . $ai
				. ' — về lại theo thang vai.' ) );
		}
		return array( array( 'ok' => 'Đã ' . ( 'mo' === $kq['giaTri'] ? 'MỞ' : 'KHOÁ' ) . ' đầu việc "'
			. VHCC_Vai::ten_viec( $q ) . '" cho ' . $ai . '. Có hiệu lực ngay ở mọi cửa hỏi quyền, '
			. 'không chỉ ở chỗ hiện tab.' ) );
	}

	private static function viec_xoa_vai( $toi ) {
		$ten = isset( $_POST['vai_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['vai_ten'] ) ) : '';
		$kq  = VHCC_Vai::xoa_them( $toi, $ten );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã bỏ vai "' . $ten . '".' ) );
	}

	private static function viec_go( $toi ) {
		$ma = isset( $_POST['ma_nv'] ) ? sanitize_text_field( wp_unslash( $_POST['ma_nv'] ) ) : '';
		$tr = isset( $_POST['trang'] ) ? sanitize_key( wp_unslash( $_POST['trang'] ) ) : '';
		$kq = VHCC_Cong::dat( $toi, $ma, $tr, '' );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã gỡ ngoại lệ của ' . $ma . ' ở trang "' . $tr . '" — về theo vai.' ) );
	}

	/* ==================================================================== vẽ */

	private static function dau( $tieu_de ) {
		$h  = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		$h .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
		/* Trang quản trị KHÔNG được để công cụ tìm kiếm ghé vào. */
		$h .= '<meta name="robots" content="noindex, nofollow">';
		/* Dùng CHUNG bảng kiểu với trang quản trị chấm công — xem `VHCC_Web::css()`. */
		$css = ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'css' ) ) ? VHCC_Web::css() : '';
		$h .= '<title>' . esc_html( $tieu_de ) . '</title><style>' . $css . self::css_them()
			. '</style></head><body>';
		return $h;
	}

	/** Vài luật riêng của màn này — phần còn lại dùng chung với trang quản trị. */
	private static function css_them() {
		return '.o-vai{color:var(--mo)}'
			/* ==============================================================================
			 * BA NÚT BẤM LIỀN NHAU, KHÔNG PHẢI Ô XỔ
			 * ==============================================================================
			 * Anh Thắng 27/08/2026: *"dạng tích chọn cho nhanh được không"*. Anh đúng: ô xổ
			 * tốn HAI lần bấm cho mỗi ô — mở ra, rồi chọn — mà một màn có tới 150 ô.
			 *
			 * 🔴 NHƯNG KHÔNG PHẢI Ô TÍCH ☑. Ô tích chỉ có HAI trạng thái, mà ô này có BA:
			 *    theo vai · mở · khoá. Ép xuống hai là mất đúng cái trạng thái "theo vai" —
			 *    mà mất nó thì gỡ một ngoại lệ đã đặt là không gỡ được nữa, và cả bảng biến
			 *    thành 700 ô phải tích tay thay vì mấy dòng ngoại lệ.
			 *
			 * Nên: ba `radio` nằm liền nhau, trông như một dải nút. Một lần bấm là xong, giữ
			 * đủ ba trạng thái, và vẫn KHÔNG một dòng script nào.
			 * ============================================================================== */
			. '.ba{display:inline-flex;vertical-align:middle}'
			/* Ẩn chấm tròn nhưng KHÔNG dùng `display:none` — ẩn kiểu ấy là bàn phím không tab
			   tới được và trình đọc màn hình cũng không thấy. Đẩy ra khỏi tầm nhìn thì nó vẫn
			   là một ô chọn thật. */
			. '.ba input{position:absolute;opacity:0;width:1px;height:1px;margin:0}'
			. '.ba label{display:block;margin:0}'
			. '.ba span{display:block;padding:4px 9px;font-size:12px;line-height:1.2;cursor:pointer;'
			. 'border:1px solid #cbd5e1;background:#fff;color:var(--mo);white-space:nowrap;'
			. '-webkit-user-select:none;user-select:none}'
			. '.ba label:first-child span{border-radius:6px 0 0 6px}'
			. '.ba label:last-child span{border-radius:0 6px 6px 0}'
			. '.ba label+label span{border-left:0}'
			. '.ba span:hover{background:#f1f5f9}'
			/* Ba trạng thái ba màu: theo vai (xám) · mở (lục) · khoá (đỏ). Màu ở đây là để
			   LIẾC RA NGAY dòng nào khác mặc định giữa hàng trăm ô — không phải trang trí. */
			. '.ba input:checked+span{background:#e2e8f0;color:var(--chu);font-weight:600;'
			. 'box-shadow:inset 0 0 0 1px #94a3b8}'
			. '.ba input.v-mo:checked+span{background:#dcfce7;color:#15803d;box-shadow:inset 0 0 0 1px #16a34a}'
			. '.ba input.v-khoa:checked+span{background:#fee2e2;color:var(--do);box-shadow:inset 0 0 0 1px #dc2626}'
			/* Viền khi đi bằng bàn phím. Thiếu nó là tab qua cả bảng mà không biết đang ở ô nào. */
			. '.ba input:focus-visible+span{outline:2px solid var(--xanh);outline-offset:1px;'
			. 'position:relative;z-index:1}'
			. 'td.o-q-td{text-align:center;padding:4px 6px}'
			. 'th.tr-doc{white-space:normal;min-width:150px}'
			/* Nút áp cả cột nằm ngay dưới tên cột — nhỏ và nhạt, để không tranh chỗ với tên. */
			. '.cot-nut{display:inline-flex;margin-top:5px;font-weight:400}'
			. '.cot-nut button{padding:2px 7px;font-size:11px;font-weight:600;border:1px solid #cbd5e1;'
			. 'background:#fff;border-radius:0;color:var(--mo)}'
			. '.cot-nut button:first-child{border-radius:5px 0 0 5px}'
			. '.cot-nut button:last-child{border-radius:0 5px 5px 0}'
			. '.cot-nut button+button{border-left:0}'
			. '.cot-nut button:hover{background:#f1f5f9;color:var(--chu)}'
			. '.chua-ma{color:var(--do);font-size:12px}'
			. 'select.o-q-vai{padding:4px 6px;font-size:12.5px;border-radius:6px;max-width:170px}'
			/* Đường sang hồ sơ: nhạt và nhỏ, chỉ đậm lên khi rê chuột — mỗi hàng có một cái,
			   tô đậm sẵn là cả cột tên biến thành một rừng liên kết xanh. */
			. '.mo-hs{font-size:11px;color:var(--mo);text-decoration:none;white-space:nowrap;'
			. 'border:1px solid var(--vien);border-radius:5px;padding:1px 5px;margin-left:4px}'
			. '.mo-hs:hover{color:var(--xanh);border-color:var(--xanh)}'
			/* Đường "xoá" đỏ ngay từ lúc chưa rê chuột — nó là đường DUY NHẤT ở cột này dẫn tới
			   một việc không đảo lại được, nên không được trông giống ba đường kia. */
			. '.xoa-hs{color:var(--do);border-color:#fecaca}'
			. '.xoa-hs:hover{color:#fff;background:var(--do);border-color:var(--do)}'
			/* Nút xoá thật (nhịp hai): đỏ đặc, không lẫn với nút Lưu xanh. */
			. '.nut-do{background:var(--do);color:#fff;border:1px solid var(--do);border-radius:8px;'
			. 'padding:8px 14px;font-size:14px;font-weight:600;cursor:pointer}'
			. 'tr.hang-xoa>td{background:#fff7f7}'
			/* Hàng trùng: nền vàng nhạt + nhãn đỏ. Màu KHÔNG đứng một mình — nhãn có chữ, để
			   người mù màu và bản in đen trắng vẫn đọc ra. */
			. 'tr.hang-trung>td{background:#fffbeb}'
			. '.chip-t,.chip-n{display:inline-block;margin-left:5px;padding:0 6px;border-radius:9px;'
			. 'background:#fee2e2;color:var(--do);font-size:10.5px;font-weight:700;'
			. 'letter-spacing:.2px;vertical-align:middle}'
			. '.chip-n{margin-left:0}'
			/* 🔴 HAI KIỂU TRÙNG TÊN, HAI MÀU. "Khác cơ sở" nhiều khả năng là hai người thật —
			   để nó đỏ như báo động thì người ta tắt mắt với cả hai. Còn "một người hai hồ sơ"
			   thì đúng là hỏng: công chia đôi, lương tính theo hai nửa. */
			. '.chip-t{background:#fef3c7;color:#92400e}'
			. '.chip-nang{background:#fee2e2;color:var(--do)}'
			/* Hàng đang mở: viền đậm để mắt tìm lại được nó giữa 50 hàng sau khi tải lại trang. */
			. 'tr.dang-sua>td{box-shadow:inset 0 2px 0 var(--xanh)}'
			. 'tr.hang-sua>td{background:#eff6ff;border:2px solid var(--xanh);padding:12px 14px}'
			/* 🔴 Ô NHẬP PHẢI XUỐNG DÒNG DƯỚI NHÃN. Anh Thắng 27/08/2026: *"lệch khung"* — và đây
			   là chỗ nó lệch: `<label>Họ tên<input></label>` thì `<input>` là inline, nên
			   `width:100%` của nó bắt đầu NGAY SAU chữ "Họ tên" chứ không phải từ mép trái ô.
			   Ô rộng bằng cả ô lưới mà lại đẩy sang phải một đoạn bằng độ dài cái nhãn, thành ra
			   tràn ra ngoài và đè lên nhãn của ô kế bên. Nhãn dài bao nhiêu thì lệch bấy nhiêu —
			   nên "Trạng thái làm việc" lệch nặng nhất, còn "Họ tên" trông gần như bình thường.
			   Đó là lý do lỗi này lọt: nhìn ô đầu thì thấy ổn.
			   `display:block` cho ô nhập là hết: nó bắt đầu từ mép trái, và `width:100%` đo đúng
			   bề ngang ô lưới. */
			. 'tr.hang-sua .luoi{grid-template-columns:repeat(auto-fit,minmax(215px,1fr));'
			. 'align-items:start;gap:12px 14px}'
			. 'tr.hang-sua .luoi label{display:block;margin:0;font-size:12px;line-height:1.35}'
			. 'tr.hang-sua .luoi label input{display:block;width:100%;margin-top:4px}'
			/* Ô ngày của trình duyệt có bề ngang riêng, không co theo ô lưới — không ghim lại
			   thì riêng nó phình ra và đẩy lệch cả hàng. */
			. 'tr.hang-sua .luoi label input[type="date"]{max-width:100%;min-width:0}'
			/* Chú thích trong nhãn ("đang có 6 số…") xuống dòng riêng, đừng chen ngang làm nhãn
			   dài gấp ba rồi kéo ô lưới rộng ra theo. */
			. 'tr.hang-sua .luoi label .mo{display:block;font-size:11px;margin:1px 0 0}';
	}

	/**
	 * ĐÓNG TRANG — chân trang công ty (kèm số phiên bản) rồi mới tới thẻ đóng.
	 *
	 * 🔴 MỘT CHỖ ĐÓNG DUY NHẤT trong tệp này; `test-cham-cong.php` canh chuyện đó. Anh Thắng
	 *    26/08: *"Cuối mỗi tất cả các trang bổ sung tên phiên bản đang chạy để theo dõi"* —
	 *    một chỗ đóng thì nhãn phiên bản không thể thiếu ở một màn nào.
	 */
	private static function dong_trang( $so_div = 1 ) {
		echo str_repeat( '</div>', max( 0, (int) $so_div ) );
		if ( class_exists( 'VHCC_Cty' ) && method_exists( 'VHCC_Cty', 'html' ) ) {
			$h = VHCC_Cty::html();
			if ( '' !== $h ) { echo '<div class="bo">' . $h . '</div>'; }
		}
		echo '</body></html>';
	}

	/** Chưa đăng nhập / không đủ quyền — nói rõ và chỉ đường, đừng để một trang trắng. */
	private static function trang_choi() {
		$u   = ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'nguoi_vao' ) )
			? VHCC_Web::nguoi_vao() : null;
		$loi = self::vi_sao_khong_vao( $u );
		echo self::dau( 'Quản lý nhân sự' );
		echo '<div class="bo" style="max-width:520px;padding-top:56px"><div class="the">';
		echo '<h2>Quản lý nhân sự</h2>';
		echo '<div class="bao ' . ( $u ? 'canh' : 'loi' ) . '">' . esc_html( $loi ) . '</div>';
		echo '<p class="mo">Trang này khai <b>ai vào được trang nào</b> trong hệ K&amp;H. '
			. 'Đăng nhập bằng PIN ở trang quản trị chấm công rồi quay lại — cùng một phiên, '
			. 'không phải gõ PIN hai lần.</p>';
		if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
			echo '<p><a class="nut chinh" href="' . esc_url( VHCC_Web::url() ) . '">Tới trang đăng nhập</a></p>';
		}
		self::dong_trang( 2 );
	}

	private static function trang_chinh( $toi ) {
		$ds_trang = VHCC_Cong::ds();
		$cs   = isset( $_GET['ncs'] )  ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['ncs'] ) ) : '';
		$q    = isset( $_GET['nq'] )   ? sanitize_text_field( wp_unslash( $_GET['nq'] ) ) : '';
		$vai  = isset( $_GET['nvai'] ) ? sanitize_text_field( wp_unslash( $_GET['nvai'] ) ) : '';
		$p    = isset( $_GET['np'] )   ? max( 1, (int) $_GET['np'] ) : 1;

		echo self::dau( 'Quản lý nhân sự' );
		echo '<header><div class="bo">'
			. '<a class="hieu" href="' . esc_url( self::url() ) . '"><b>K&amp;H</b> Quản lý nhân sự</a>'
			. '<span class="mo">' . esc_html( (string) $toi['name'] ) . ' · '
			. esc_html( VHCC_Vai::ten( $toi ) ) . '</span>'
			. '</div></header>';
		echo '<div class="bo">';

		/* Tiêu đề màn — cùng kiểu với màn quản trị chấm công. Hai trang là một hệ; lệch tiêu đề
		   là người dùng tưởng mình lạc sang chỗ khác. */
		echo '<div class="tieu-man"><h1>Quản lý nhân sự</h1>'
			. '<p class="mo">Ai vào được trang nào · bảng vai trò · chia đầu việc</p></div>';

		foreach ( self::lay_bao() as $b ) { self::ve_bao( $b ); }

		self::the_duong_di( $toi, $ds_trang );
		self::canh_nguon();
		self::canh_ghe( $toi );

		if ( ! $ds_trang ) {
			echo '<div class="the"><div class="bao loi">Không dò thấy trang nào trên site này. '
				. 'Các plugin trang (Cổng, Nội bộ, Chi phí, Ghế, Hợp đồng) có thể chưa được kích hoạt.'
				. '</div></div>';
			self::dong_trang();
			return;
		}

		/* Anh Thắng 29/08/2026: "đẩy 2 bảng về đây chung luôn cho gọn", rồi sau khi thấy vẫn còn
		   hai ô viền riêng: "sao chưa ghép lại thành 1 bảng" — "Ghép hai mã về một người" là công
		   cụ CHỮA đúng cái nhãn "một người hai hồ sơ?" trong bảng nhân sự, nên bọc CHUNG một khung
		   `<div class="the">` quanh cả hai (the_bang() và the_ghep_ma() không tự vẽ khung riêng
		   nữa, xem chú thích ở đầu mỗi hàm) — một ô viền duy nhất, không phải hai ô đứng cạnh nhau. */
		echo '<div class="the">';
		self::the_bang( $toi, $ds_trang, $cs, $q, $vai, $p );
		self::the_ghep_ma( $toi );
		echo '</div>';
		self::the_dong_bo( $toi );
		self::the_quyen_noi_bo( $toi );
		self::canh_vai_la( $toi );
		self::the_vai( $toi );
		self::the_dau_viec( $toi );
		self::the_ngoai_pham_vi();
		self::the_mac_dinh( $ds_trang );
		self::dong_trang();
	}

	private static function ve_bao( $b ) {
		foreach ( array( 'ok' => 'ok', 'loi' => 'loi', 'canh' => 'canh' ) as $k => $lop ) {
			if ( isset( $b[ $k ] ) ) {
				echo '<div class="bao ' . $lop . '">' . esc_html( (string) $b[ $k ] ) . '</div>';
			}
		}
	}

	/**
	 * 🔴 CẢNH BÁO KHI ĐỔI VAI Ở ĐÂY KHÔNG CÓ HIỆU LỰC ĐĂNG NHẬP.
	 *
	 * Hệ có BỐN kho người dùng, và `VHCC_Auth::nguon()` quyết định lúc đăng nhập đọc kho nào:
	 * hồ sơ nhân sự · danh sách riêng của plugin · bản sao sổ PhanQuyen · sổ của app Vận hành
	 * chi phí (đây là MẶC ĐỊNH).
	 *
	 * Cột Vai trò của bảng này đọc và ghi vào HỒ SƠ NHÂN SỰ. Nên khi nguồn đang đặt là kho
	 * khác, bấm Lưu vẫn ghi thành công vào hồ sơ, màn hình vẫn báo "Đã đổi vai trò cho N
	 * người" — mà vai lúc người ta đăng nhập thì đọc từ kho kia, không đổi gì cả. Đó là lời nói
	 * dối tệ nhất một màn quản trị có thể nói: BÁO THÀNH CÔNG CHO MỘT VIỆC KHÔNG XẢY RA. Người
	 * khai đóng trang, tin là xong, và chỉ phát hiện khi có người kêu "sao tôi vẫn không vào
	 * được" — lúc ấy không ai nối được hai chuyện với nhau.
	 *
	 * ⚠️ CẢNH BÁO, KHÔNG PHẢI CHẶN. Ghi vào hồ sơ vẫn có ích: hồ sơ là nơi anh Thắng thật sự
	 *    nhập liệu, và ngày nào lật nguồn sang "hồ sơ" là mọi thứ khai ở đây có hiệu lực ngay.
	 *    Chặn lại thì mất luôn đường chuẩn bị trước.
	 */
	/**
	 * PHÂN QUYỀN TRANG NỘI BỘ — kéo ra đây thay vì bắt vào wp-admin.
	 *
	 * =========================================================================================
	 * 🔴 CHỖ KHAI QUYỀN PHẢI NẰM CẠNH CHỖ NGƯỜI TA NHÌN THẤY VẤN ĐỀ.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026, ảnh một Quản lý bị chối ở trang Nội bộ: *"Trang nội bộ là trang
	 * chung thì ai vẫn được vào mà"*. Đúng — mặc định của `VHNB_Quyen::VIEC` là Nhân viên. Nhưng
	 * ô ấy trên host đang đặt Admin, và chỗ đổi lại nằm trong wp-admin.
	 *
	 * Trang này đã là nơi trả lời "ai vào được trang nào". Để một trang trong hệ khai quyền ở
	 * chỗ khác thì người đi tìm sẽ tìm ở đây trước, không thấy, rồi kết luận là không đổi được.
	 *
	 * ⚠️ KHÔNG DỰNG LẠI LUẬT. `VHNB_Quyen` vẫn là nơi duy nhất giữ luật; đây chỉ là một cái ô
	 *    xổ gọi vào `dat()` của nó. Chép luật sang là hai bảng cùng nói về một cửa.
	 *
	 * ⚠️ Gác `method_exists` CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`, cho mọi
	 *    lời gọi sang plugin KHÁC. Chưa cài plugin Nội bộ thì đừng vẽ khối này ra.
	 */
	private static function the_quyen_noi_bo( $toi ) {
		if ( ! class_exists( 'VHNB_Quyen' ) || ! method_exists( 'VHNB_Quyen', 'cai_dat' )
			|| ! defined( 'VHNB_Quyen::VIEC' ) ) { return; }
		/* ⛔ Chốt này hôm nay CHƯA TỪNG rẽ sang false: cửa vào màn là `ho_so`, và đây cũng
		   `ho_so` — ai vào nổi trang đều thấy khối. Phá thử xác nhận là mã tương đương. Giữ vì
		   nó bảo vệ trước thay đổi ở CHỖ KHÁC: ngày cửa vào trang nới xuống bậc thấp hơn, nó
		   tự đứng ra chặn mà không ai phải nhớ. Quan hệ hai bậc ấy có phép thử canh riêng. */
		if ( ! VHCC_Vai::duoc( $toi, 'ho_so' ) ) { return; }

		$dang = VHNB_Quyen::cai_dat();
		$viec = constant( 'VHNB_Quyen::VIEC' );
		$bac  = constant( 'VHNB_Quyen::BAC_DS' );

		echo '<div class="the"><details><summary><b>Phân quyền trang Nội bộ</b> — '
			. 'ai vào được, ai đăng bài, ai dọn</summary>';
		echo '<p class="mo">Trang Nội bộ là <b>trang chung</b>: mặc định ai cũng vào và cũng đăng '
			. 'được. Siết lên thì siết ở đây — và nhớ rằng siết ô <b>Vào trang Nội bộ</b> là đóng '
			. 'cửa cả trang với mọi người dưới bậc ấy.</p>';
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Việc</th><th>Cần vai từ</th>'
			. '<th>Mặc định</th></tr></thead><tbody>';
		foreach ( $viec as $k => $v ) {
			$hien = isset( $dang[ $k ] ) ? (string) $dang[ $k ] : (string) $v['md'];
			echo '<tr><td>' . esc_html( (string) $v['nhan'] ) . '</td><td>';
			echo '<select name="nb[' . esc_attr( $k ) . ']">';
			foreach ( $bac as $ma_b => $ten_b ) {
				echo '<option value="' . esc_attr( $ma_b ) . '"'
					. selected( $ma_b, $hien, false ) . '>' . esc_html( $ten_b ) . '</option>';
			}
			echo '</select></td>';
			/* Nói ra MẶC ĐỊNH ngay cạnh — để người khai biết mình đang lệch khỏi nó bao xa. */
			$md = isset( $bac[ $v['md'] ] ) ? $bac[ $v['md'] ] : $v['md'];
			echo '<td class="mo">' . esc_html( $md )
				. ( $hien !== $v['md'] ? ' <span class="chu-hong">(đang khác)</span>' : '' )
				. '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<div class="hang" style="margin-top:10px">'
			. '<button class="chinh" name="viec" value="quyen_noi_bo">Lưu phân quyền Nội bộ</button>'
			. '</div></form></details></div>';
	}

	/**
	 * GHÉP HAI MÃ VỀ MỘT NGƯỜI — "mã song song".
	 *
	 * =========================================================================================
	 * 🔴 LỠ TẠO HAI HỒ SƠ CHO MỘT NGƯỜI THÌ CHỮA Ở ĐÂY.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026: *"1 nhân viên mà làm 2 cơ sở, nên hệ thống báo trùng"*. Cách ĐÚNG
	 * là một người một mã, cơ sở thứ hai khai vào ô **Cơ sở phụ**. Nhưng khi đã lỡ tạo hai mã
	 * rồi thì không xoá bớt được — mỗi mã đã có công, có lương, có lịch sử.
	 *
	 * `ma_song_song` là đường chữa: khai một cặp mã là "cùng một người", rồi
	 * `VHCC_NhanSu::ma_that()` dịch mã kia về mã chính khi lượt chấm công đi vào.
	 *
	 * ⚠️ TRƯỚC BẢN NÀY CHỈ KHAI ĐƯỢC TRONG wp-admin. Mà người phát hiện ra cặp trùng lại đang
	 *    đứng ở ĐÂY, nhìn đúng cái nhãn "một người hai hồ sơ" — bắt họ rời trang, đăng nhập
	 *    WordPress, đi tìm một màn khác, là gần như chắc chắn họ để đấy.
	 *
	 * 🔴 PHẢI KHAI TAY, HỆ KHÔNG ĐƯỢC TỰ SUY TỪ TÊN. Tên người Việt trùng rất nhiều; đoán sai
	 *    là gộp lương hai người khác nhau. Nhãn ở bảng trên chỉ NGHI, còn quyết thì là người.
	 */
	private static function the_ghep_ma( $toi ) {
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $toi ) ) { return; }
		$ds = VHCC_NhanSu::ds_ma_song_song();

		/* 🔴 KHÔNG tự vẽ khung `<div class="the">` — anh Thắng 29/08/2026: "đẩy 2 bảng về đây
		   chung luôn cho gọn" → "sao chưa ghép lại thành 1 bảng" → "Gộp gọn về 1 bảng luôn".
		   Nơi gọi (render()) bọc hàm này CHUNG một khung với the_bang() (bảng nhân sự chính) —
		   một ô viền duy nhất. KHÔNG chèn `<hr>`/khoảng cách riêng nữa (bản trước có, anh Thắng
		   vẫn thấy là "còn hai bảng") — liền mạch ngay sau bảng chính, chỉ cách nhau đúng khoảng
		   `<details>` tự có, giống mọi khối "…</table></div>" nối "<details>" khác trong CÙNG
		   một khung `.the` ở trang này (VD `the_dau_viec()` nối liền `the_vai()` bên dưới). */
		echo '<details' . ( $ds ? ' open' : '' ) . '>';
		echo '<summary><b>Ghép hai mã về một người</b> — '
			. ( $ds ? count( $ds ) . ' cặp đã khai' : 'chưa khai cặp nào' ) . '</summary>';
		echo '<p class="mo">Một người lỡ có hai Mã NV (thường vì làm ở hai cơ sở và bị tạo hồ sơ '
			. 'hai lần) thì với cả hệ họ là <b>hai người</b>: công chia đôi, lương tính theo hai '
			. 'nửa, mỗi hồ sơ một PIN. Khai cặp ở đây là lượt chấm công của mã phụ tự chảy về mã '
			. 'chính.<br><b>Chưa lỡ thì đừng dùng cái này</b> — người làm hai cơ sở chỉ cần khai '
			. 'ô <b>Cơ sở phụ</b> trong hồ sơ đầy đủ, vẫn một mã.</p>';
		/* 🔴 NÓI RÕ HAI MỨC. Khai cặp chỉ chữa từ nay về sau; lưới vẫn vẽ hai dòng cho tới khi
		   dồn nốt phần cũ. Không nói ra thì người khai tưởng xong, rồi mở lưới lên vẫn thấy hai
		   dòng và tin là chức năng hỏng. */
		echo '<p class="mo"><b>Hai mức, hai hậu quả.</b> <b>Ghép</b> chỉ chữa từ nay về sau — lượt '
			. 'mới của mã phụ sẽ tự chảy về mã chính, còn hàng đã nằm trong bảng thì vẫn mang mã '
			. 'cũ, nên lưới vẫn vẽ ra hai dòng. Bấm thêm <b>Dồn … hàng cũ</b> để gom nốt phần đã '
			. 'có. <span class="chu-hong">Dồn thì KHÔNG đảo lại được</span> — bỏ ghép sau đó cũng '
			. 'không tách ra như cũ.</p>';

		if ( $ds ) {
			echo '<div class="cuon"><table class="stt"><thead><tr><th>Mã chính</th><th>Mã phụ</th>'
				. '<th>Họ tên</th><th>Lý do</th><th>Ai khai</th><th></th></tr></thead><tbody>';
			foreach ( $ds as $r ) {
				echo '<tr><td><code>' . esc_html( (string) $r['ma_a'] ) . '</code></td>'
					. '<td><code>' . esc_html( (string) $r['ma_b'] ) . '</code></td>'
					. '<td>' . esc_html( (string) $r['ho_ten'] ) . '</td>'
					. '<td class="mo">' . esc_html( (string) $r['ly_do'] ) . '</td>'
					. '<td class="mo">' . esc_html( (string) $r['nguoi_khai'] ) . '</td>'
					. '<td><form method="post" style="margin:0">'
					. '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">'
					. self::o_loc()
					. '<input type="hidden" name="ma_a" value="' . esc_attr( (string) $r['ma_a'] ) . '">'
					. '<input type="hidden" name="ma_b" value="' . esc_attr( (string) $r['ma_b'] ) . '">'
					. '<button name="viec" value="bo_ghep_ma">Bỏ ghép</button>';
			/* 🔴 KHAI CẶP CHỈ CHỮA TỪ NAY VỀ SAU — hàng đã nằm trong bảng từ trước vẫn mang mã
			   máy, nên lưới vẫn vẽ ra hai người. Nút này dồn nốt phần cũ. Đứng RIÊNG, không chạy
			   kèm lúc khai: khai cặp là việc nhẹ và bỏ được, dồn thì KHÔNG ĐẢO ĐƯỢC. */
			$dem = VHCC_NhanSu::dem_don_ma( (string) $r['ma_a'], (string) $r['ma_b'] );
			$con = (int) $dem['chuyen'] + (int) $dem['gop'];
			if ( $con ) {
				echo ' <button name="viec" value="don_ma" class="chinh" '
					. 'title="' . esc_attr( 'Chuyển ' . (int) $dem['chuyen'] . ' hàng sang mã chính, '
						. 'gộp ' . (int) $dem['gop'] . ' hàng trùng ngày. KHÔNG đảo lại được.' ) . '">'
					. 'Dồn ' . $con . ' hàng cũ</button>';
			} else {
				echo ' <span class="mo">đã dồn xong</span>';
			}
			echo '</form></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		/* =====================================================================================
		 * 🔴 DÒ SẴN, ĐỪNG BẮT GÕ TAY 400 CẶP.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026: *"cách dò tên nhân viên trùng để ghép mã được không: theo họ tên
		 * nhân viên"*. Đúng — hai ô gõ tay là đủ cho một cặp, không đủ cho một chuỗi 26 cửa hàng.
		 *
		 * ⚠️ GỢI Ý THÌ ĐƯỢC, TỰ GHÉP THÌ KHÔNG. Tên người Việt trùng rất nhiều; đoán sai là gộp
		 *    lương hai người khác nhau, mà dồn xong thì không đảo lại được. Bảng này bày ra ĐỦ
		 *    BẰNG CHỨNG để người bấm tự quyết: mỗi bên đang ở cơ sở nào, mã máy có bao nhiêu
		 *    lượt, và tên ấy khớp mấy hồ sơ.
		 */
		$gy = VHCC_NhanSu::goi_y_ghep_ma( $toi );
		if ( $gy ) {
			echo '<div class="bao canh" style="margin-top:12px"><b>Dò theo họ tên: '
				. count( $gy ) . ' mã lạ đang có lượt chấm công mà không có hồ sơ.</b><br>'
				. '<span class="mo">Gần như chắc là mã của máy chấm công. Soi hai cột cơ sở rồi '
				. 'bấm Ghép — <b>hệ không tự ghép hộ</b>, vì trùng tên chưa chắc là một người.'
				. '</span></div>';
			echo '<div class="cuon"><table class="stt"><thead><tr><th>Tên máy gửi</th>'
				. '<th>Mã trên máy</th><th>Lượt</th><th>Cơ sở (máy)</th>'
				. '<th>Mã công ty</th><th>Cơ sở (hồ sơ)</th><th></th></tr></thead><tbody>';
			foreach ( $gy as $g ) {
				$lech_cs = ( '' !== $g['maCty'] && 0 !== strcasecmp(
					VHCC_NhanSu::chuan_coso( $g['coso'] ), VHCC_NhanSu::chuan_coso( $g['cosoHoSo'] ) ) );
				echo '<tr><td>' . esc_html( $g['ten'] ) . '</td>'
					. '<td><code>' . esc_html( $g['maMay'] ) . '</code></td>'
					. '<td>' . (int) $g['soLuot'] . '</td>'
					. '<td>' . esc_html( $g['coso'] ) . '</td>';
				if ( '' === $g['maCty'] ) {
					/* ⚠️ Tên khớp NHIỀU hồ sơ thì KHÔNG gợi ý — chọn đại một cái là đúng kiểu sai
					   mà cả khối này sinh ra để tránh. */
					echo '<td colspan="3"><span class="chu-hong">tên này khớp '
						. (int) $g['soHoSoKhop'] . ' hồ sơ</span> '
						. '<span class="mo">— tự chọn rồi gõ tay bên dưới</span></td></tr>';
					continue;
				}
				echo '<td><code>' . esc_html( $g['maCty'] ) . '</code></td>'
					. '<td>' . esc_html( $g['cosoHoSo'] )
					/* Lệch cơ sở là dấu hiệu MẠNH rằng đây là hai người khác nhau trùng tên. */
					. ( $lech_cs ? ' <span class="chu-hong">(khác cơ sở!)</span>' : '' ) . '</td>';
				echo '<td><form method="post" style="margin:0">'
					. '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">'
					. self::o_loc()
					. '<input type="hidden" name="ma_a" value="' . esc_attr( $g['maCty'] ) . '">'
					. '<input type="hidden" name="ma_b" value="' . esc_attr( $g['maMay'] ) . '">'
					. '<input type="hidden" name="gm_ten" value="' . esc_attr( $g['ten'] ) . '">'
					. '<input type="hidden" name="gm_ly_do" value="mã máy, dò theo họ tên">'
					. '<button name="viec" value="ghep_ma"' . ( $lech_cs ? '' : ' class="chinh"' ) . '>'
					. 'Ghép</button></form></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '<form method="post" class="hang" style="margin-top:10px;gap:8px;flex-wrap:wrap">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		echo '<div><label for="gm_a">Mã CHÍNH (giữ lại)</label>'
			. '<input id="gm_a" name="ma_a" placeholder="VD: MNNV2MTD0014"></div>';
		echo '<div><label for="gm_b">Mã PHỤ (chảy về mã chính)</label>'
			. '<input id="gm_b" name="ma_b" placeholder="VD: MNNV2MTD0022"></div>';
		echo '<div><label for="gm_t">Họ tên</label><input id="gm_t" name="gm_ten"></div>';
		echo '<div><label for="gm_l">Lý do</label>'
			. '<input id="gm_l" name="gm_ly_do" placeholder="VD: tạo hồ sơ hai lần"></div>';
		echo '<div><button class="chinh" name="viec" value="ghep_ma">Ghép hai mã</button></div>';
		echo '</form>';
		echo '</details>';
		/* Không đóng `<div class="the">` — xem chú thích ở đầu hàm này, nơi gọi lo khung chung. */
	}

	/**
	 * AI ĐANG MANG MỘT VAI HỆ PHẢI ĐOÁN — chứ không khai chính thức.
	 *
	 * =========================================================================================
	 * 🔴 SỬA DÒNG ĐỎ KÊU OAN XONG THÌ PHẢI NÓI RA CHUYỆN THẬT NẰM SAU NÓ.
	 * =========================================================================================
	 * Anh Thắng 28/08/2026 thấy *"MNKT4CTY0001: Vai trò «Kế Toán MTD» không có trong hệ"* mỗi
	 * lần bấm Lưu bảng, dù không đổi vai ai. `VHCC_NhanSu::dat_vai_tro()` nay thôi kêu khi
	 * không có gì đổi — đúng, nhưng dừng ở đó là GIẤU mất chuyện thật.
	 *
	 * ⚠️ VÀ CHUYỆN THẬT KHÔNG PHẢI LÀ "HỌ BỊ HẠ XUỐNG NHÂN VIÊN". Bản nháp của khối này viết
	 *    đúng câu ấy, và nó SAI: `VHCC_Vai::ma('Kế Toán MTD')` trả về KE_TOAN, vì nhánh đoán
	 *    theo tên bắt được tiền tố "ke toan". Người ấy đang ở đúng bậc.
	 *
	 * 🔴 CHUYỆN THẬT LÀ: BẬC ẤY ĐANG DO ĐOÁN MÀ RA. Đoán đúng hôm nay không có nghĩa đoán đúng
	 *    mai. Chính `VHCC_Vai::ma()` đã ghi: một vai tên "Điều phối POSH" gốc Quản lý thì nhánh
	 *    đoán không nhận ra, và người ấy rơi xuống Nhân viên mà không có gì báo. Khối này liệt
	 *    kê mọi chuỗi đang phải đoán, kèm BẬC HỆ ĐANG TÍNH CHO HỌ — để anh soi xem đoán ấy có
	 *    đúng ý mình không, và khai lại cho chắc.
	 *
	 * ⚠️ ĐẾM TRÊN CẢ SỔ, KHÔNG CHỈ TRANG ĐANG HIỆN. Bảng có lọc và phân trang; một người mang
	 *    chuỗi lạ ở trang 4 thì không ai nhìn thấy nữa — mà đó đúng là người cần thấy nhất.
	 */
	private static function canh_vai_la( $toi ) {
		$ds  = VHCC_Vai::ds_ten();
		$la  = array();
		$roi = 0;
		$rows = VHCC_DB::rows( 'SELECT ma_nv, ho_ten, vai_tro, cua_hang FROM '
			. VHCC_DB::t( 'nhan_vien' ) . " WHERE TRIM(vai_tro) <> ''" );
		foreach ( (array) $rows as $r ) {
			$v = trim( (string) $r['vai_tro'] );
			if ( in_array( $v, $ds, true ) ) { continue; }
			if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $r['cua_hang'] ) ) { continue; }
			$bac = VHCC_Vai::ma( $v );
			$r['bac'] = VHCC_Vai::TEN[ $bac ];
			/* Rơi xuống bậc thấp nhất = gần như chắc là đoán TRẬT, vì không ai đặt tên một vai
			   rồi mong nó thành Nhân viên. Đếm riêng để nói nặng nhẹ cho đúng. */
			$r['roi'] = ( VHCC_Vai::NV === $bac );
			if ( $r['roi'] ) { $roi++; }
			$la[] = $r;
		}
		if ( ! $la ) { return; }

		echo '<div class="the"><details' . ( $roi ? ' open' : '' ) . '>';
		echo '<summary><b>' . count( $la ) . ' người mang vai hệ phải ĐOÁN</b>'
			. ( $roi ? ' — <span class="chu-hong">' . $roi . ' người trong đó rơi xuống Nhân viên'
				. '</span>' : '' ) . '</summary>';
		echo '<p class="mo">Hồ sơ ghi một chuỗi không có trong danh sách vai (thường là dữ liệu cũ '
			. 'nạp sang). Hệ vẫn cho họ một bậc, nhưng bằng cách <b>đoán theo tên</b> — đúng hôm '
			. 'nay không có nghĩa đúng mai. Khai chuỗi ấy thành <b>vai tự tạo</b> ở khối ngay bên '
			. 'dưới là hết đoán; hoặc đổi ô <b>Vai trò</b> của họ sang một vai đã có.</p>';
		if ( $roi ) {
			echo '<div class="bao loi">' . $roi . ' người rơi xuống <b>Nhân viên</b> — bậc thấp '
				. 'nhất. Không ai đặt tên một vai rồi mong nó thành Nhân viên, nên đây gần như '
				. 'chắc là đoán trật, và họ đang bị hạ quyền mỗi lần đăng nhập.</div>';
		}
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Cơ sở</th><th>Hồ sơ đang ghi</th><th>Hệ đang tính là</th>'
			. '</tr></thead><tbody>';
		foreach ( $la as $r ) {
			echo '<tr><td><code>' . esc_html( (string) $r['ma_nv'] ) . '</code></td>'
				. '<td>' . esc_html( (string) $r['ho_ten'] ) . '</td>'
				. '<td>' . esc_html( (string) $r['cua_hang'] ) . '</td>'
				. '<td>' . esc_html( trim( (string) $r['vai_tro'] ) ) . '</td>'
				. '<td>' . ( $r['roi'] ? '<span class="chu-hong">' . esc_html( $r['bac'] ) . '</span>'
					: esc_html( $r['bac'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></details></div>';
	}

	/**
	 * HỆ GHẾ ĐANG ĐỌC SỔ NÀO — và nút chuyển, nếu đang đọc sai chỗ.
	 *
	 * 🔴 ĐẨY VÀO SỔ RIÊNG TRONG KHI HỆ GHẾ ĐANG ĐỌC SỔ CHUNG = ĐẨY VÀO HƯ KHÔNG. Màn hình báo
	 *    "đã đẩy 12 người", 12 người ấy gõ PIN và không ai vào được, mà không có một dòng nào
	 *    nói vì sao. Nên trạng thái này phải hiện ra TRƯỚC khi anh bấm, không phải sau.
	 *
	 * ⚠️ NÚT CHUYỂN CÓ CHÉP NGƯỜI ĐANG DÙNG SANG TRƯỚC — xem `VHCC_DayGhe::chuyen_sang_rieng()`.
	 *    Đổi cờ trần là ngay khoảnh khắc ấy KHÔNG AI vào được `/ghe`, kể cả người vừa bấm nút.
	 *    Nói ra chuyện đó ngay trên nút, để người bấm biết mình đang bấm cái gì.
	 */
	private static function canh_ghe( $toi ) {
		if ( ! self::cot_ghe( $toi ) || VHCC_DayGhe::nguon_dung() ) { return; }
		echo '<div class="bao canh"><b>Hệ ghế đang đọc SỔ CHUNG với app Vận hành chi phí.</b><br>'
			. 'Cột <b>Ghế massage</b> ở bảng dưới đẩy người vào <b>sổ riêng của hệ ghế</b>, mà sổ ấy '
			. 'thì hệ ghế chưa đọc tới — nên đẩy bây giờ là đẩy vào chỗ không ai hỏi đến.<br>'
			. '<button class="chinh" name="viec" value="ghe_rieng" style="margin-top:6px">'
			. 'Chuyển hệ ghế sang dùng sổ riêng</button> '
			. '<span class="mo">Nút này <b>chép sẵn những người đang đăng nhập được</b> sang sổ riêng '
			. 'trước khi đổi — không ai bị đá ra giữa chừng, kể cả anh.</span></div>';
	}

	private static function canh_nguon() {
		if ( ! class_exists( 'VHCC_Auth' ) || ! method_exists( 'VHCC_Auth', 'nguon' ) ) { return; }
		$n = VHCC_Auth::nguon();
		if ( 'ho_so' === $n ) { return; }

		$ten = array(
			'chung' => 'sổ người dùng của app Vận hành chi phí (bảng CH_NguoiDung)',
			'rieng' => 'danh sách riêng của plugin chấm công',
			'app'   => 'bản sao sổ PhanQuyen của app Apps Script cũ (bảng phan_quyen)',
		);
		$ten = isset( $ten[ $n ] ) ? $ten[ $n ] : $n;

		echo '<div class="bao canh"><b>Cột Vai trò ở đây ghi vào HỒ SƠ NHÂN SỰ, '
			. 'nhưng hệ đang đăng nhập bằng một cuốn sổ khác.</b><br>'
			. 'Nguồn người dùng đang đặt là <b>' . esc_html( $ten ) . '</b>. Đổi vai ở bảng này '
			. 'vẫn được ghi vào hồ sơ, nhưng <b>vai lúc người ta đăng nhập thì đọc từ sổ kia</b> '
			. '— nên chưa có hiệu lực ngay.';
		if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
			/* 🔴 KHÔNG PHẢI TAB "CẤU HÌNH" — mục đó không có ở đó. Bản trước trỏ nhầm sang
			   `man=cau_hinh`, và anh Thắng đi đúng đường ấy rồi báo *"vẫn chưa qua"*: tab Cấu
			   hình của trang này không hề có ô Nguồn người dùng. Ô THẬT nằm ở tab Hồ sơ & tài
			   khoản, khối 🔑 Tài khoản đăng nhập (VHCC_Web::the_tai_khoan()) — trỏ đúng chỗ đó. */
			echo ' Muốn khai ở đây là ăn ngay thì vào tab <a href="'
				. esc_url( add_query_arg( array( 'man' => 'ho_so' ), VHCC_Web::url() ) ) . '">'
				. 'Hồ sơ &amp; tài khoản</a>, khối <b>🔑 Tài khoản đăng nhập</b>, bấm nút cho cổng '
				. 'đọc thẳng <b>hồ sơ nhân sự</b>.';
		}
		/* ⚠️ Nói ra cái KHÔNG bị ảnh hưởng, kẻo người đọc tưởng cả hệ đang hỏng. Trạm chấm công
		   đọc thẳng hồ sơ (xem `VHCC_Tram::tim_pin`), không đi qua `nguon()`. */
		echo '<br><span class="mo">Trạm chấm công không bị ảnh hưởng — nó luôn đọc thẳng hồ sơ '
			. 'nhân sự. Chuyện này chỉ liên quan tới vai trò khi đăng nhập trang quản trị.</span>';
		echo '</div>';
	}

	/**
	 * KHỐI ĐỒNG BỘ — soi trước, đổi sau.
	 *
	 * Anh Thắng 27/08/2026: *"đồng bộ phần chấm công nhân sự trước, người nào sai đưa ra cảnh
	 * báo anh chỉnh lại quyền"*.
	 *
	 * 🔴 NÚT CHUYỂN NGUỒN CHỈ MỞ KHI KHÔNG CÒN MỤC NẶNG. Chuyển nguồn là đổi cả cuốn sổ mà cổng
	 *    PIN đang tra — 240 người đổi đường vào cùng một lúc. Người mất đường vào KHÔNG tự báo
	 *    được, vì cái họ mất chính là đường để báo. Nên bày ra trước, sửa hết, rồi mới cho bấm.
	 *
	 * ⚠️ KHOÁ NÚT, KHÔNG GIẤU NÚT. Giấu đi thì người ta không biết có đường ấy và đi tìm mãi;
	 *    khoá lại kèm câu "còn N chỗ phải sửa" thì vừa chặn vừa nói ra việc phải làm.
	 */
	private static function the_dong_bo( $toi ) {
		if ( ! class_exists( 'VHCC_Auth' ) || ! method_exists( 'VHCC_Auth', 'doi_chieu_ho_so' ) ) { return; }
		$kq = VHCC_Auth::doi_chieu_ho_so();
		$da = ( 'ho_so' === $kq['nguon'] );

		echo '<div class="the"><details' . ( $kq['nang'] ? ' open' : '' ) . '>';
		echo '<summary><b>Đồng bộ chấm công ↔ hồ sơ nhân sự</b> — '
			. ( $kq['nang']
				? '<span class="chua">' . (int) $kq['nang'] . ' chỗ phải sửa</span>'
				: '<span class="co">không còn chỗ nặng nào</span>' )
			. ( count( $kq['muc'] ) > $kq['nang']
				? ' · ' . ( count( $kq['muc'] ) - (int) $kq['nang'] ) . ' chỗ nên soát'
				: '' )
			. '</summary>';

		echo '<p class="mo">Cổng PIN đang tra <b>'
			. esc_html( $da ? 'hồ sơ nhân sự' : self::ten_nguon( $kq['nguon'] ) ) . '</b> — '
			. (int) $kq['so_cu'] . ' người đăng nhập được. Hồ sơ nhân sự có <b>'
			. (int) $kq['so_moi'] . '</b> người đã khai PIN.'
			. ( $da ? ' Hai bên đã là một — bảng dưới chỉ soi sức khoẻ của chính hồ sơ.' : '' ) . '</p>';

		if ( ! $kq['muc'] ) {
			echo '<div class="bao ok">Không thấy chỗ nào lệch.</div>';
		} else {
			echo '<div class="cuon"><table><thead><tr><th>Mức</th><th>Ai</th><th>Chuyện gì</th>'
				. '</tr></thead><tbody>';
			/* Mục NẶNG lên trước — cùng lý do với hồ sơ trùng: cái phải sửa ngay không được nằm
			   lẫn dưới một đống ghi chú. */
			foreach ( array( true, false ) as $muc_nang ) {
				foreach ( $kq['muc'] as $m ) {
					if ( (bool) $m['nang'] !== $muc_nang ) { continue; }
					echo '<tr' . ( $m['nang'] ? ' class="hang-trung"' : '' ) . '>';
					/* ⚠️ LỚP RIÊNG `chip-n`, KHÔNG DÙNG LẠI `chip-t` của bảng người. Hai khối nói
					   về hai chuyện khác nhau (một bên "hồ sơ trùng", một bên "lệch sổ"), mà
					   dùng chung tên lớp thì mọi phép thử soi `class="chip-t"` sẽ bắt nhầm sang
					   khối kia — đã đỏ oan đúng một lần vì chuyện đó. */
					echo '<td>' . ( $m['nang']
						? '<span class="chip-n">phải sửa</span>'
						: '<span class="mo">nên soát</span>' ) . '</td>';
					echo '<td><b>' . esc_html( $m['ten'] ) . '</b></td>';
					echo '<td>' . esc_html( $m['noi'] ) . '</td></tr>';
				}
			}
			echo '</tbody></table></div>';
		}

		if ( $da ) { echo '</details></div>'; return; }

		/* Đổi nguồn là việc HỆ THỐNG — chỉ Admin, đúng bằng chốt của `VHCC_Web` xử lượt đó. */
		echo '<div class="hang" style="margin-top:12px">';
		if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
			echo '<span class="mo">Chuyển nguồn là việc của Admin. Sửa xong mấy chỗ trên rồi '
				. 'nhờ Admin bấm chuyển.</span>';
		} elseif ( $kq['nang'] ) {
			echo '<button class="chinh" disabled>Chuyển sang hồ sơ nhân sự</button>';
			echo '<span class="mo">Còn <b>' . (int) $kq['nang'] . '</b> chỗ phải sửa. Sửa xong tải '
				. 'lại trang này là nút mở.</span>';
		} else {
			/* ⚠️ POST sang chính màn Cấu hình của trang quản trị, dùng ĐÚNG việc `doi_nguon` đã
			   có ở đó — nó mang sẵn chốt Admin và chốt "không ai vào được thì chối". Viết lại
			   một đường đổi nguồn thứ hai ở đây là hai cửa cho cùng một việc, và cửa mới thì
			   chưa ai gác. */
			echo '<form method="post" action="' . esc_url( VHCC_Web::url() ) . '" style="margin:0">';
			echo '<input type="hidden" name="ky" value="'
				. esc_attr( VHCC_Web::chu_ky( isset( $_COOKIE[ VHCC_Web::COOKIE ] )
					? (string) $_COOKIE[ VHCC_Web::COOKIE ] : '' ) ) . '">';
			echo '<input type="hidden" name="nguon" value="ho_so">';
			echo '<input type="hidden" name="man" value="cau_hinh">';
			echo '<button class="chinh" name="viec" value="doi_nguon">Chuyển sang hồ sơ nhân sự</button>';
			echo '</form>';
			echo '<span class="mo">Xong là mọi thứ khai ở trang này có hiệu lực ngay.</span>';
		}
		echo '</div>';
		echo '</details></div>';
	}

	/** Tên đọc được của một nguồn người dùng. */
	private static function ten_nguon( $n ) {
		$ten = array(
			'chung' => 'sổ người dùng của app Vận hành chi phí',
			'rieng' => 'danh sách riêng của plugin chấm công',
			'app'   => 'bản sao sổ PhanQuyen của app cũ',
			'ho_so' => 'hồ sơ nhân sự',
		);
		return isset( $ten[ $n ] ) ? $ten[ $n ] : (string) $n;
	}

	/** Thanh đường đi — chính những trang NGƯỜI ĐANG XEM vào được. Không vẽ trang họ không có. */
	private static function the_duong_di( $toi, $ds_trang ) {
		echo '<div class="the" style="padding:8px 10px;margin-bottom:14px"><div class="hang" style="gap:8px">';
		/* Cổng K&H là cửa trước, công khai — không nằm trong sổ quyền, nhưng vẫn phải có đường
		   quay ra, kẻo trang này thành ngõ cụt. */
		if ( class_exists( 'VHTC_Trang' ) && method_exists( 'VHTC_Trang', 'url' ) ) {
			echo '<a class="nut" href="' . esc_url( VHTC_Trang::url() ) . '">🏠 Cổng K&amp;H</a>';
		}
		foreach ( VHCC_Cong::ds_cua( $toi ) as $t ) {
			echo '<a class="nut" href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['ten'] ) . '</a>';
		}
		echo '</div></div>';
	}

	/**
	 * NÓI THẲNG NHỮNG TRANG BẢNG NÀY *KHÔNG* KHAI ĐƯỢC.
	 *
	 * 🔴 Đây là phần dễ bỏ nhất và cũng là phần dễ gây hiểu nhầm nhất. Người mở trang này ra
	 *    thấy một bảng tên là "Ai vào được trang nào" thì mặc nhiên tin rằng MỌI trang đều nằm
	 *    trong đó. Không nói ra thì hôm nào cần khoá app chi phí của một người, anh Thắng đi
	 *    tìm cột ấy, không thấy, và không biết là vì nó không thể có — chứ không phải vì em
	 *    quên. Danh sách lý do nằm ở `VHCC_Cong::SO`.
	 */
	private static function the_ngoai_pham_vi() {
		echo '<div class="the"><details>';
		echo '<summary>Những trang <b>không</b> khai được ở đây — và vì sao</summary>';
		echo '<ul class="mo" style="margin:6px 0 0 18px;padding:0">';
		/* ⚠️ `/ghe` CÓ HAI MẶT, VÀ CHỈ MỘT MẶT KHAI ĐƯỢC. Nói gọn thành "trang của khách" như
		   bản trước là sai từ ngày màn quản trị ghế ra đời — người đọc đi tìm cột Ghế, thấy
		   dòng này, rồi tin rằng nó không thể có. */
		echo '<li><b>Màn khách của Ghế massage</b> — khách quét QR trên ghế rồi trả tiền, không '
			. 'đăng nhập và không có Mã NV. Khoá được nó là ghế đứng im, tiền không vào. '
			. '<span class="mo">Còn <b>màn quản trị</b> của hệ ghế thì khai được — bằng cột '
			. '<b>Ghế massage</b> ở bảng trên. Nó không ghi ngoại lệ như ba cột kia mà <b>đẩy '
			. 'người thật</b> sang sổ người dùng của hệ ghế, vì `/ghe` có phiên riêng.</span></li>';
		echo '<li><b>Cổng K&amp;H</b> — cửa trước, công khai, chỉ liệt kê các hệ. Khoá cửa trước '
			. 'là khoá cả nhà.</li>';
		echo '<li><b>Thư viện hợp đồng</b> — giao diện lấy thẳng từ Apps Script và tự đăng nhập '
			. 'bên trong, không mang phiên chấm công sang.</li>';
		echo '<li><b>Vận hành chi phí</b> — app đó có <b>sổ người dùng riêng</b> (vai "Kế toán cá '
			. 'nhân" / "Kế toán NCC"), không nhất thiết có Mã NV trong hồ sơ chấm công. Khai quyền '
			. 'cho app chi phí thì làm ở chính app ấy, tại màn Cấu hình → Người dùng.</li>';
		echo '</ul>';
		echo '</details></div>';
	}

	/* ------------------------------------------------------------------ bảng người × trang */

	private static function the_bang( $toi, $ds_trang, $cs, $q, $vai, $p ) {
		$dang_sua = isset( $_GET['sua_o'] ) ? sanitize_text_field( wp_unslash( $_GET['sua_o'] ) ) : '';
		$dang_xoa = isset( $_GET['xoa_o'] ) ? sanitize_text_field( wp_unslash( $_GET['xoa_o'] ) ) : '';
		$nguoi = VHCC_NhanSu::ds_nhan_vien( $toi, $cs, $q );
		if ( '' !== $vai ) {
			$loc = array();
			foreach ( $nguoi as $r ) {
				if ( VHCC_Vai::ma( isset( $r['vai_tro'] ) ? $r['vai_tro'] : '' ) === $vai ) { $loc[] = $r; }
			}
			$nguoi = $loc;
		}
		/* 🔴 HỒ SƠ TRÙNG LÊN ĐẦU. Anh Thắng 27/08/2026: *"Nhân viên nào trùng tên, trùng Mã NV
		   thì đưa lên đầu nhé"*.
		   ⚠️ Dò trên TOÀN BỘ hồ sơ, không dò trên lát cắt đang lọc. Hai người trùng tên ở hai cơ
		      sở khác nhau mà chỉ dò trong một cơ sở thì mỗi bên thấy đúng một dòng, và cả hai
		      cùng trông bình thường — đúng cặp nguy hiểm nhất lại là cặp lọt.
		   ⚠️ Xếp TRƯỚC khi cắt trang. Cắt rồi mới xếp thì hồ sơ trùng nằm ở trang 4 vẫn ở trang
		      4, mà "đưa lên đầu" chính là để khỏi lật từng trang đi tìm. */
		$trung = VHCC_NhanSu::dau_hieu_trung( self::ds_de_do( $toi ) );
		$nguoi = VHCC_NhanSu::xep_trung_len_dau( $nguoi, $trung );
		$so_trung = 0;
		foreach ( $nguoi as $r_t ) {
			if ( isset( $trung[ (string) $r_t['ma_nv'] ] ) ) { $so_trung++; }
		}
		/* Anh Thắng 29/08/2026: "có ô nào hiện ra người có dữ liệu và người không có dữ liệu
		   không... để biết người đó có hoạt động" — chỉ đếm cho ĐÚNG những mã đang bị gắn cờ
		   trùng (xem dem_cham_cong_theo_ma()), không quét cả sổ. */
		$hoat_dong = VHCC_NhanSu::dem_cham_cong_theo_ma( array_keys( $trung ) );

		$tong  = count( $nguoi );
		$so_tr = max( 1, (int) ceil( $tong / self::MOI_TRANG ) );
		$p     = min( $p, $so_tr );
		$lat   = array_slice( $nguoi, ( $p - 1 ) * self::MOI_TRANG, self::MOI_TRANG );

		/* 🔴 KHÔNG tự mở `<div class="the">` ở đây — anh Thắng 29/08/2026: "đẩy 2 bảng về đây
		   chung luôn cho gọn" rồi "sao chưa ghép lại thành 1 bảng". Khung ngoài (mở/đóng) nay do
		   NƠI GỌI (render()) lo, bọc CHUNG quanh cả the_bang() lẫn the_ghep_ma() — một khung duy
		   nhất chứa cả hai, thay vì mỗi hàm tự vẽ khung riêng rồi hai khung nằm cạnh nhau. */
		echo '<h2>Ai vào được trang nào</h2>';
		echo '<p class="mo">Mặc định theo <b>vai trò</b> — bảng này chỉ ghi những chỗ <b>khác</b> '
			. 'mặc định. Để ô ở «Theo vai» là người ấy đi theo thang vai, đổi vai là quyền đổi theo. '
			. 'Chọn «Mở» hay «Khoá» là ghim cứng cho riêng người đó, vai đổi cũng không lay chuyển. '
			. '<b>Chuyển cơ sở thì quyền riêng của người đó reset về mặc định</b> — ngoại lệ khai '
			. 'theo hoàn cảnh ở cơ sở cũ, sang chỗ mới thì hoàn cảnh ấy hết.</p>';

		if ( $so_trung ) {
			echo '<div class="bao canh"><b>' . (int) $so_trung . ' hồ sơ đang trùng tên hoặc trùng '
				. 'Mã NV</b> — đã đưa lên đầu bảng. Mã NV là khoá của mọi lượt chấm công: hai hồ sơ '
				. 'của cùng một người là công bị chẻ đôi, hai người cùng một mã là công cộng nhầm '
				. 'sang nhau. Bấm <b>hồ sơ ↗</b> để xem rồi gộp hoặc sửa mã.</div>';
		}

		self::o_tim( $toi, $cs, $q, $vai );

		if ( ! $lat ) {
			echo '<div class="bao canh">Không có hồ sơ nào khớp bộ lọc.</div></div>';
			return;
		}

		echo '<form method="post">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		echo '<div class="cuon"><table><thead><tr>';
		echo '<th>Mã NV</th><th>Họ tên</th><th>Cơ sở</th><th>Vai trò</th>';
		/* 🔴 NÚT ÁP CẢ CỘT — thứ thật sự tiết kiệm thời gian. Anh Thắng: *"cho nhanh"*. Đổi ô
		   xổ thành nút bấm mới bớt được một lần bấm mỗi ô; còn khoá cả một cơ sở cho một trang
		   thì vẫn là 50 lần bấm. Nút này làm cả cột trong MỘT lần.
		   ⚠️ Chỉ áp cho người ĐANG HIỆN — cùng luật với nút Lưu. Bảng có lọc và phân trang, nên
		      "cả cột" nghĩa là cả cột của lát cắt này, không phải của 240 người. */
		foreach ( $ds_trang as $k_t => $t ) {
			echo '<th class="tr-doc">' . esc_html( $t['ten'] ) . '<br>';
			echo '<span class="cot-nut">';
			foreach ( array( '' => 'vai', 'mo' => 'Mở', 'khoa' => 'Khoá' ) as $gt => $ten ) {
				echo '<button type="submit" name="cot" value="' . esc_attr( $k_t . '|' . $gt ) . '"'
					. ' title="Áp «' . esc_attr( $ten ) . '» cho tất cả người đang hiện, rồi lưu luôn">'
					. esc_html( $ten ) . '</button>';
			}
			echo '</span></th>';
		}
		/* 🔴 CỘT GHẾ TRÔNG GIỐNG BA CỘT KIA NHƯNG LÀ MỘT CƠ CHẾ KHÁC — xem đầu `VHCC_DayGhe`.
		   Ba cột kia ghi một NGOẠI LỆ vào sổ quyền; cột này ĐẨY NGƯỜI THẬT sang sổ người dùng
		   của hệ ghế, vì `/ghe` có phiên riêng và không đọc `ma_nv`. Nên nó chỉ có HAI nút:
		   đẩy sang, hoặc gỡ ra. Không có "theo vai" — bên ấy không hỏi vai bên này bao giờ. */
		$co_ghe = self::cot_ghe( $toi );
		if ( $co_ghe ) {
			echo '<th class="tr-doc">Ghế massage<br><span class="cot-nut">';
			foreach ( array( 'mo' => 'Đẩy', '' => 'Gỡ' ) as $gt => $ten ) {
				echo '<button type="submit" name="cot" value="' . esc_attr( VHCC_DayGhe::COT . '|' . $gt ) . '"'
					. ' title="' . esc_attr( 'mo' === $gt
						? 'Đẩy tất cả người đang hiện sang hệ ghế, rồi lưu luôn'
						: 'Gỡ tất cả người đang hiện khỏi hệ ghế, rồi lưu luôn' ) . '">'
					. esc_html( $ten ) . '</button>';
			}
			echo '</span></th>';
		}
		$co_cp = self::cot_chi_phi( $toi );
		if ( $co_cp ) {
			echo '<th class="tr-doc">Vận hành chi phí<br><span class="cot-nut">';
			foreach ( array( 'mo' => 'Đẩy', '' => 'Gỡ' ) as $gt => $ten ) {
				echo '<button type="submit" name="cot" value="'
					. esc_attr( VHCC_DayChiPhi::COT . '|' . $gt ) . '"'
					. ' title="' . esc_attr( 'mo' === $gt
						? 'Đẩy tất cả người đang hiện sang app Vận hành chi phí, rồi lưu luôn'
						: 'Gỡ tất cả người đang hiện khỏi app Vận hành chi phí, rồi lưu luôn' ) . '">'
					. esc_html( $ten ) . '</button>';
			}
			echo '</span></th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $lat as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			$co_trung = isset( $trung[ $ma ] ) ? $trung[ $ma ] : null;
			$lop_hang = trim( ( $co_trung ? 'hang-trung ' : '' )
				. ( $dang_sua === $ma ? 'dang-sua' : '' ) );
			echo '<tr' . ( '' !== $lop_hang ? ' class="' . esc_attr( $lop_hang ) . '"' : '' ) . '>';
			echo '<td><a id="hs' . esc_attr( substr( md5( $ma ), 0, 8 ) ) . '"></a><b>'
				. esc_html( $ma ) . '</b>';
			if ( $co_trung ) {
				/* Nhãn nói RÕ trùng cái gì. Một chấm đỏ chung chung thì người ta phải tự đoán,
				   mà hai kiểu trùng này cần hai cách xử khác hẳn nhau: trùng tên thì gộp hai hồ
				   sơ, trùng mã thì phải cấp lại mã cho một bên. */
				if ( $co_trung['ma'] )  { echo '<span class="chip-t">trùng mã</span>'; }
				/* 🔴 TRÙNG TÊN CÙNG CƠ SỞ ĐỌC KHÁC HẲN TRÙNG TÊN KHÁC CƠ SỞ — anh Thắng
				   28/08/2026: *"1 nhân viên mà làm 2 cơ sở, nên hệ thống báo trùng. có ảnh
				   hưởng gì không"*. Khác cơ sở thì rất có thể là hai người thật; CÙNG cơ sở thì
				   gần như chắc là MỘT người bị tạo hai hồ sơ — và với cả hệ, hai mã NV là hai
				   người khác nhau: công chia đôi, lương tính theo hai nửa, mỗi hồ sơ một PIN. */
				if ( ! empty( $co_trung['motNguoi'] ) ) {
					echo '<span class="chip-t chip-nang" title="Cùng tên VÀ cùng cơ sở — gần như '
						. 'chắc là một người bị tạo hai hồ sơ. Công sẽ chia đôi giữa hai mã.">'
						. 'một người hai hồ sơ?</span>';
					/* 🔴 NHÃN CHỈ NGHI, KHÔNG NÓI MÃ NÀO LÀ MÃ THẬT. Đếm lượt chấm công THẬT của
					   đúng mã này (xem dem_cham_cong_theo_ma()) trả lời câu "mã nào đang hoạt
					   động, mã nào là hồ sơ rác an toàn để xoá" — mã 0 lượt gần như chắc là hồ sơ
					   tạo lỡ; mã có lượt là mã đang dùng, xoá nhầm là mất công của người ta. */
					$hd = isset( $hoat_dong[ $ma ] ) ? $hoat_dong[ $ma ] : null;
					if ( $hd ) {
						echo '<br><span class="mo">📋 ' . (int) $hd['luot'] . ' lượt chấm công'
							. ( $hd['tu'] ? ( ' (' . esc_html( $hd['tu'] ) . ' → ' . esc_html( $hd['den'] ) . ')' ) : '' )
							. '</span>';
					} else {
						echo '<br><span class="mo chu-hong">⚠ Chưa có lượt chấm công nào — '
							. 'gần như chắc là hồ sơ tạo lỡ, an toàn để xoá.</span>';
					}
					/* 🔴 GHÉP NGAY TẠI ĐÂY, KHÔNG BẮT GÕ TAY Ở BẢNG KHÁC. Anh Thắng 29/08/2026,
					   sau ba lượt chỉnh chỗ đặt/khung bảng "Ghép hai mã" vẫn chê: *"cùng 1 nv có
					   khác gì đâu"*, *"chả khác gì, cùng mã thì ghép lại thôi"*. Nút này nằm
					   NGAY TRONG `<form>` của bảng chính (không mở form mới — hai form lồng nhau
					   là HTML không hợp lệ, trình duyệt tự đóng form ngoài sớm và nút "Lưu bảng
					   này" sẽ hỏng), mang tên riêng `ghep_voi` để chỉ đúng lượt bấm nó mới có mặt
					   trong $_POST — cùng cách `xoa_ma`/`cot` đã làm ở đầu lam_viec(). Giá trị
					   nút đã đóng gói sẵn "mã_chính|mã_phụ": mã có NHIỀU lượt chấm công hơn giữ
					   làm chính, mã ít/không có giữ làm phụ — người bấm không phải tự đoán ai
					   là ai, xem viec_ghep_voi(). */
					if ( VHCC_NhanSu::co_quan_tri_nv( $toi ) && ! empty( $co_trung['doi'] ) ) {
						foreach ( $co_trung['doi'] as $ma_doi ) {
							$luot_toi = isset( $hoat_dong[ $ma ] ) ? (int) $hoat_dong[ $ma ]['luot'] : 0;
							$luot_doi = isset( $hoat_dong[ $ma_doi ] ) ? (int) $hoat_dong[ $ma_doi ]['luot'] : 0;
							$chinh = ( $luot_doi > $luot_toi ) ? $ma_doi : $ma;
							$phu   = ( $luot_doi > $luot_toi ) ? $ma : $ma_doi;
							echo '<br><button type="submit" name="ghep_voi" '
								. 'value="' . esc_attr( $chinh . '|' . $phu ) . '" class="nut" '
								. 'style="margin-top:4px;padding:2px 8px;font-size:12px" '
								. 'title="' . esc_attr( 'Gộp ' . $phu . ' (ít/không có chấm công hơn) '
									. 'vào ' . $chinh . '. Không đảo lại được, giống nút Ghép ở bảng dưới.' ) . '">'
								. 'Ghép với ' . esc_html( $ma_doi ) . '</button>';
						}
					}
				} elseif ( $co_trung['ten'] ) {
					echo '<span class="chip-t" title="Có người cùng tên ở CƠ SỞ KHÁC — nhiều khả '
						. 'năng là hai người thật, không phải lỗi.">trùng tên (khác cơ sở)</span>';
				}
			}
			echo '</td>';
			/* Nút mở thẳng hồ sơ người này ở màn Hồ sơ & tài khoản. Anh Thắng: *"bổ sung thêm
			   1 số thông tin nhân viên, với cấu hình này nó thông với thông tin nhân viên"*.
			   🔴 KHÔNG dựng lại màn hồ sơ ở đây. Thêm/sửa/xoá nhân sự đã có đủ ở màn kia; làm
			      lần hai là hai màn cùng ghi một bảng, và sớm muộn hai bên lệch luật. Nối
			      đường đi thì vẫn một lần bấm mà chỉ có MỘT nơi giữ luật. */
			echo '<td>' . esc_html( (string) $r['ho_ten'] );
			if ( '' !== $ma && class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
				/* 🔴 MỞ NGAY TẠI HÀNG, KHÔNG NHẢY TRANG. Anh Thắng 27/08/2026: *"thay vì nhảy ra 1
				   trang khác thì mình xổ xuống được không, chứ nhảy trang thì lại phải đi dò lại
				   người 2, 3 rất lâu"*.
				   Anh đúng, và cái mất không chỉ là thời gian: nhảy trang là mất bộ lọc, mất số
				   trang, mất chỗ đang đứng — nên sửa xong người thứ nhất là phải làm lại từ đầu
				   để tới người thứ hai. Nay `sua_o=<mã>` tải lại CHÍNH trang này, giữ nguyên
				   lọc và trang, rồi chèn một hàng sửa ngay dưới người ấy. Không script — cùng
				   lối `VHCC_Web` đã làm cho lưới chấm công (`tr.hang-sua`).
				   ⚠️ `#` anchor để trình duyệt cuộn thẳng tới hàng ấy: giữa 50 hàng mà tải lại
				      trang rồi đứng ở đầu bảng thì vẫn phải đi tìm. */
				if ( $dang_sua === $ma ) {
					echo ' <a class="mo-hs" href="' . esc_url( self::url_sua( '' ) ) . '">đóng ▲</a>';
				} else {
					echo ' <a class="mo-hs" title="Mở ô sửa ngay tại đây, không rời trang"'
						. ' href="' . esc_url( self::url_sua( $ma ) . '#hs' . substr( md5( $ma ), 0, 8 ) )
						. '">sửa ▾</a>';
				}
				/* Vẫn giữ đường sang hồ sơ ĐẦY ĐỦ — hàng sửa dưới đây chỉ có mấy ô hay dùng nhất,
				   còn CCCD, địa chỉ, hợp đồng, người liên hệ khẩn thì nằm ở màn kia. */
				echo ' <a class="mo-hs" title="Mở hồ sơ đầy đủ — CCCD, địa chỉ, hợp đồng…"'
					. ' href="' . esc_url( add_query_arg(
						array( 'man' => 'ho_so', 'sua' => $ma ), VHCC_Web::url() ) ) . '">đầy đủ ↗</a>';
				/* 🔴 XOÁ LÀ VIỆC KHÔNG ĐẢO LẠI ĐƯỢC, NÊN NÓ ĐI HAI NHỊP.
				   Anh Thắng 28/08/2026, sau khi thử thêm một người rồi thấy hàng rác trong sổ:
				   *"Giờ anh muốn xóa nhân viên đó đi"*. Trước nay chỉ wp-admin xoá được, nên
				   hàng rác cứ nằm đấy.
				   Nhịp một là một ĐƯỜNG DẪN (GET) — bấm nhầm thì không mất gì, chỉ mở ra lời
				   hỏi. Nhịp hai mới là nút gửi. Không dùng hộp thoại xác nhận bằng JavaScript:
				   cả màn này không có lấy một dòng script, mà thứ bộ thử PHP không với tới thì
				   không phải là chốt. */
				if ( VHCC_Vai::duoc( $toi, 'xoa_ho_so' ) ) {
					echo ' <a class="mo-hs xoa-hs" title="Xoá hẳn hồ sơ này khỏi sổ"'
						. ' href="' . esc_url( self::url_xoa( $ma ) . '#hs' . substr( md5( $ma ), 0, 8 ) )
						. '">xoá 🗑</a>';
				}
			}
			echo '</td>';
			echo '<td>' . self::o_coso( $toi, $ma, (string) $r['cua_hang'] ) . '</td>';
			echo '<td>' . self::o_vai( $toi, $ma, isset( $r['vai_tro'] ) ? $r['vai_tro'] : '' ) . '</td>';

			/* Giả một "người" chỉ có mã + vai, để hỏi `VHCC_Cong` xem MẶC ĐỊNH của họ ra sao.
			   ⚠️ Hỏi bằng CHÍNH hàm mà cửa vào dùng, không tự tính lại bậc ở đây — hai phép
			      tính cho cùng một câu hỏi là sớm muộn màn hình nói khác cửa vào. */
			$gia = array( 'ma_nv' => $ma, 'role' => (string) ( isset( $r['vai_tro'] ) ? $r['vai_tro'] : '' ) );

			foreach ( $ds_trang as $k => $t ) {
				$dat = VHCC_Cong::o( $ma, $k );
				/* Mặc định THEO VAI — tính với người KHÔNG mang ngoại lệ, nên phải hỏi thẳng
				   `VHCC_Vai`, không gọi `duoc_vao()` (nó đã tính cả ngoại lệ vào rồi). */
				$mac  = VHCC_Vai::duoc( $gia, $t['quyen'] );
				/* ⚠️ Không có mã NV thì ngoại lệ không bám vào đâu được — thẻ phiên mang mã
				   rỗng, nên `duoc_vao()` bỏ qua sạch. Nói ra, đừng vẽ một ô chọn vô tác dụng. */
				if ( '' === $ma ) {
					echo '<td class="o-q-td"><span class="chua-ma">chưa có Mã NV</span></td>';
					continue;
				}
				echo '<td class="o-q-td">' . self::ba_nut( $ma, $k, $dat, $mac ) . '</td>';
			}
			if ( $co_ghe ) {
				echo '<td class="o-q-td">'
					. ( '' === $ma ? '<span class="chua-ma">chưa có Mã NV</span>'
						: self::hai_nut_ghe( $ma ) ) . '</td>';
			}
			if ( $co_cp ) {
				echo '<td class="o-q-td">'
					. ( '' === $ma ? '<span class="chua-ma">chưa có Mã NV</span>'
						: self::hai_nut_chi_phi( $ma ) ) . '</td>';
			}
			echo '</tr>';
			$so_cot_hang = 4 + count( $ds_trang ) + ( $co_ghe ? 1 : 0 ) + ( $co_cp ? 1 : 0 );
			if ( $dang_sua === $ma ) {
				self::hang_sua( $toi, $r, $so_cot_hang );
			}
			if ( '' !== $ma && $dang_xoa === $ma ) {
				self::hang_xoa( $toi, $r, $so_cot_hang );
			}
		}
		echo '</tbody></table></div>';
		echo '<div class="hang" style="margin-top:12px">'
			. '<button class="chinh" name="viec" value="luu_quyen">Lưu bảng này</button>'
			. '<span class="mo">Chỉ lưu ' . count( $lat ) . ' người đang hiện — người ở trang khác '
			. 'không bị đụng tới.</span></div>';
		echo '</form>';

		self::thanh_trang( $p, $so_tr, $tong );
		/* Không đóng `<div class="the">` ở đây — xem chú thích ở đầu hàm này, nơi gọi lo cả mở
		   lẫn đóng khung chung. */
	}

	/** Địa chỉ trang này với ô sửa mở ở `$ma` (rỗng = đóng), giữ nguyên bộ lọc và số trang. */
	private static function url_sua( $ma ) {
		$u = self::url_hien();
		$u = remove_query_arg( 'sua_o', $u );
		return ( '' === $ma ) ? $u : add_query_arg( 'sua_o', $ma, $u );
	}

	private static function url_xoa( $ma ) {
		$u = remove_query_arg( array( 'sua_o', 'xoa_o' ), self::url_hien() );
		return ( '' === $ma ) ? $u : add_query_arg( 'xoa_o', $ma, $u );
	}

	/**
	 * HÀNG SỬA — chèn ngay dưới người đang mở, trong CHÍNH bảng này.
	 *
	 * Anh Thắng 27/08/2026: *"thay vì nhảy ra 1 trang khác thì mình xổ xuống được không… sửa
	 * xong anh đóng nó gọn lại là được"*.
	 *
	 * 🔴 CHỈ MẤY Ô HAY SỬA NHẤT. Bê cả 20 trường của màn hồ sơ vào đây thì hàng sửa cao hơn cả
	 *    màn hình, và cái lợi "không rời trang" mất sạch. CCCD, địa chỉ, hợp đồng, người liên hệ
	 *    khẩn vẫn nằm ở màn hồ sơ đầy đủ — có đường "đầy đủ ↗" ngay cạnh.
	 *
	 * ⚠️ ĐI QUA `VHCC_NhanSu::luu_ho_so()`, KHÔNG GHI THẲNG. Mọi chốt (bậc, quyền cơ sở, danh
	 *    sách cột cho phép, ô lương chỉ ai xem được mới ghi được) nằm trong hàm ấy. Đây là cửa
	 *    thứ hai vào cùng một việc — cửa thứ hai mà tự ghi lấy là cửa không ai gác.
	 *
	 * ⚠️ KHÔNG CÓ Ô MÃ NV. Mã là khoá của mọi lượt chấm công; đổi nó là việc riêng, chỉ Admin,
	 *    và có màn xem trước hẳn hoi (`VHCC_NhanSu::xem_truoc_doi_ma`).
	 */
	/**
	 * HÀNG HỎI TRƯỚC KHI XOÁ — nhịp hai của việc xoá hồ sơ.
	 *
	 * Anh Thắng 28/08/2026: *"Giờ anh muốn xóa nhân viên đó đi"* (một hàng thử anh vừa thêm từ
	 * màn cửa hàng). Trước nay chỉ wp-admin xoá được, nên hàng rác nằm lại trong sổ.
	 *
	 * 🔴 NÓI RA CÁI SẼ MẤT, KHÔNG CHỈ HỎI "CÓ CHẮC KHÔNG". Một câu "Bạn có chắc?" thì ai cũng
	 *    bấm Có. Ở đây liệt kê thẳng: mã, tên, cửa hàng, và người ấy đang có bao nhiêu lượt
	 *    chấm công — vì còn lượt chấm nào thì `VHCC_NhanSu::xoa_ho_so()` sẽ CHỐI, và biết
	 *    trước vẫn hơn bấm rồi mới đọc lời từ chối.
	 *
	 * ⚠️ CŨNG KHÔNG MỞ `<form>` — xem chú thích dài ở `hang_sua()`. Nút xoá mang luôn tên và
	 *    giá trị (`name="xoa_ma"`), nên chỉ khi bấm ĐÚNG nút ấy mã mới được gửi lên; các nút
	 *    khác của bảng gửi lượt của mình mà không kéo theo lệnh xoá nào.
	 */
	private static function hang_xoa( $toi, $r, $so_cot ) {
		if ( ! VHCC_Vai::duoc( $toi, 'xoa_ho_so' ) ) { return; }
		$ma  = trim( (string) $r['ma_nv'] );
		$ten = trim( (string) ( isset( $r['ho_ten'] ) ? $r['ho_ten'] : '' ) );
		$so  = VHCC_NhanSu::so_luot_cham( $ma );

		echo '<tr class="hang-sua hang-xoa"><td colspan="' . (int) $so_cot . '">';
		echo '<div class="bao ' . ( $so > 0 ? 'loi' : 'canh' ) . '" style="margin:0 0 10px">'
			. '<b>Xoá hẳn hồ sơ ' . esc_html( $ma )
			. ( '' !== $ten ? ' — ' . esc_html( $ten ) : '' ) . '?</b> '
			. 'Hồ sơ biến mất khỏi sổ nhân sự và <b>không lấy lại được</b>. '
			. 'Muốn giữ lại lịch sử thì đừng xoá — đổi <b>Trạng thái làm việc</b> thành '
			. '<b>Đã nghỉ</b> ở ô <b>sửa ▾</b>.';
		if ( $so > 0 ) {
			echo ' <br><b>Người này còn ' . (int) $so . ' lượt chấm công</b>, nên hệ sẽ CHỐI: '
				. 'bảng lương tháng cũ sẽ có mã mà không tra ra tên.';
		} else {
			echo ' Người này <b>chưa có lượt chấm công nào</b>, nên xoá đi không bỏ rơi dữ liệu cũ.';
		}
		echo '</div>';
		echo '<div class="hang">';
		echo '<button class="nut-do" name="xoa_ma" value="' . esc_attr( $ma ) . '">'
			. 'Xoá hẳn ' . esc_html( $ma ) . '</button>';
		echo '<a class="nut" href="' . esc_url( self::url_xoa( '' ) ) . '">Thôi, giữ lại</a>';
		echo '</div></td></tr>';
	}

	private static function hang_sua( $toi, $r, $so_cot ) {
		$ma  = trim( (string) $r['ma_nv'] );
		$luong = VHCC_NhanSu::co_xem_luong( $toi );
		$g = function ( $c ) use ( $r ) { return isset( $r[ $c ] ) ? (string) $r[ $c ] : ''; };

		/* =====================================================================================
		 * 🔴 KHÔNG MỞ `<form>` Ở ĐÂY. HÀNG NÀY NẰM TRONG FORM CỦA BẢNG RỒI.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026: *"nút sửa nhanh này nó không lưu được"*.
		 *
		 * Bản trước mở một `<form>` thứ hai ngay trong `<td>` — mà cả bảng đã nằm trong một
		 * `<form>` mở từ `the_bang()`. HTML CẤM form lồng form: trình duyệt bỏ thẻ mở bên trong,
		 * nhưng vẫn khớp thẻ `</form>` đóng của nó với form ĐANG mở — tức là form của BẢNG. Hàng
		 * sửa nhanh chèn ở GIỮA bảng, nên từ chỗ ấy trở đi mọi thứ rơi ra ngoài form: nút "Lưu
		 * bảng này" ở cuối bảng thành mồ côi, và cả khối sửa nhanh chạy trên một cấu trúc mà
		 * mỗi trình duyệt vá một kiểu.
		 *
		 * ⚠️ Không có gì báo, vì HTML sai không ném lỗi — nó chỉ lặng lẽ cho ra một cây DOM khác
		 *    cái mình viết. Đây là lý do bộ thử phải canh CHÍNH chuyện "chỉ có một form".
		 *
		 * Nay dùng chung form của bảng. `ky` và ô lọc đã có sẵn ở đó, không khai lại.
		 */
		echo '<tr class="hang-sua"><td colspan="' . (int) $so_cot . '">';
		echo '<input type="hidden" name="ma_nv" value="' . esc_attr( $ma ) . '">';
		echo '<b>Sửa nhanh ' . esc_html( $ma ) . '</b>';
		echo '<div class="luoi" style="margin-top:8px">';
		foreach ( array(
			'ho_ten'              => array( 'Họ tên', 'text' ),
			'sdt'                 => array( 'Số điện thoại', 'text' ),
			'chuc_vu'             => array( 'Chức vụ', 'text' ),
			'nhiem_vu'            => array( 'Nhiệm vụ', 'text' ),
			'ngay_vao_lam'        => array( 'Ngày vào làm', 'date' ),
			'trang_thai_lam_viec' => array( 'Trạng thái làm việc', 'text' ),
		) as $c => $o ) {
			/* ⚠️ KHÔNG còn `style="width:100%"` gõ tay ở đây. Bề ngang do CSS của `tr.hang-sua`
			   lo, cùng chỗ với `display:block` — tách hai thứ ấy ra hai nơi là sửa một bên rồi
			   quên bên kia, và lệch khung quay lại. */
			echo '<label>' . esc_html( $o[0] ) . '<input type="' . esc_attr( $o[1] ) . '" name="'
				. esc_attr( $c ) . '" value="' . esc_attr( $g( $c ) ) . '"></label>';
		}
		if ( $luong ) {
			/* Ô lương chỉ hiện với người có quyền xem — `luu_ho_so()` cũng chỉ nhận mấy ô này từ
			   họ, nên đây là hai tầng cho cùng một luật.
			   ⚠️ NHÁNH NÀY HIỆN CHƯA TỪNG RẼ SANG FALSE, và đó không phải lý do bỏ nó. Cửa vào
			      trang là `ho_so` (bậc 4), ô lương là `xem_luong_hs` (cũng bậc 4) — ai vào nổi
			      trang đều xem được lương. Khác cái chốt chết đã bỏ ở `dat_co_so()`: chốt kia
			      nằm ngay cạnh một chốt chặt hơn trong CÙNG hàm nên không bảo vệ trước gì cả,
			      còn chốt này bảo vệ trước thay đổi ở CHỖ KHÁC — ngày nào cửa vào trang nới
			      xuống bậc 3, nó tự đứng ra chặn mà không ai phải nhớ. Bộ thử canh chính quan
			      hệ hai bậc ấy. */
			echo '<label>Lương cơ bản<input name="luong_co_ban" value="'
				. esc_attr( $g( 'luong_co_ban' ) ) . '"></label>';
			echo '<label>Số tài khoản<input name="so_tai_khoan" value="'
				. esc_attr( $g( 'so_tai_khoan' ) ) . '"></label>';
			echo '<label>Ngân hàng<input name="ngan_hang" value="'
				. esc_attr( $g( 'ngan_hang' ) ) . '"></label>';
		}
		/* 🔴 KHÔNG ĐIỀN SẴN PIN CŨ VÀO Ô. Trang này chạy ngoài internet và ảnh chụp màn hình đi
		   khắp nơi — đúng luật màn Hồ sơ đang giữ. Để trống = giữ nguyên PIN cũ. */
		echo '<label>PIN đăng nhập <span class="mo">('
			. ( '' !== $g( 'pin_dang_nhap' ) ? 'đang có ' . strlen( $g( 'pin_dang_nhap' ) ) . ' số'
				: 'chưa có' ) . ', để trống = giữ nguyên)</span>'
			. '<input name="pin_dang_nhap" inputmode="numeric"></label>';
		echo '</div>';
		echo '<div class="hang" style="margin-top:10px">';
		echo '<button class="chinh" name="viec" value="sua_nhanh">Lưu hồ sơ này</button>';
		/* ⚠️ HAI NÚT LƯU TRÊN CÙNG MỘT BIỂU MẪU TỪNG LÀ MỘT CÁI BẪY: tích ô quyền ở bảng rồi bấm
		   nút này thì mấy ô ấy mất im lặng. Bản trước vá bằng một câu nhắc — nhắc là bắt người
		   ta nhớ, mà cái gì bắt nhớ thì sớm muộn có người quên. Nay `viec_sua_nhanh()` lưu LUÔN
		   cả bảng (dữ liệu đã nằm sẵn trong cùng biểu mẫu), nên bấm nút nào cũng không mất gì —
		   và câu nhắc kia thành thừa, bỏ đi. */
		echo '<a class="nut" href="' . esc_url( self::url_sua( '' ) ) . '">Đóng</a>';
		echo '<span class="mo">Còn CCCD, địa chỉ, hợp đồng… thì mở <b>đầy đủ ↗</b> ở cột Họ tên.</span>';
		echo '</div></td></tr>';
	}

	/**
	 * Ô CƠ SỞ — chuyển người sang cơ sở khác ngay tại đây.
	 *
	 * Anh Thắng 27/08/2026: *"Điều chỉnh bạn thuộc cơ sở nào nên bạn chuyển, khi chuyển quyền
	 * hạn sẽ reset lại mặc định"*.
	 *
	 * ⚠️ CHỈ VẼ Ô XỔ CHO NGƯỜI THẬT SỰ CHUYỂN ĐƯỢC — cùng luật với ô Vai trò. Chuyển cơ sở cần
	 *    bậc Quản lý trở lên (nó chuyển cả công và lương giữa hai cửa hàng), và phải phụ trách
	 *    cả cơ sở đi lẫn cơ sở đến.
	 *
	 * ⚠️ Danh sách cơ sở đọc từ `ds_coso()` — gom từ bảng máy, bảng chấm công và hồ sơ, KHÔNG tự
	 *    tạo cơ sở nào. Cho gõ tay ở đây là đẻ ra "VP_KH_HCM " với một dấu cách ở cuối, và từ đó
	 *    trở đi nó là một cơ sở khác trong mọi bảng tổng hợp.
	 */
	private static function o_coso( $toi, $ma, $cs_cu ) {
		if ( '' === $ma || ! VHCC_NhanSu::co_quan_tri_nv( $toi )
			|| ! VHCC_NhanSu::co_quyen_coso( $toi, $cs_cu ) ) {
			return esc_html( $cs_cu );
		}
		$ds = VHCC_NhanSu::ds_coso();
		$h  = '<select class="o-q-vai" name="cs[' . esc_attr( $ma ) . ']">';
		$co = false;
		foreach ( $ds as $c ) {
			if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $c ) ) { continue; }
			$chon = ( strtolower( trim( $cs_cu ) ) === strtolower( $c ) );
			if ( $chon ) { $co = true; }
			$h .= '<option value="' . esc_attr( $c ) . '"' . selected( true, $chon, false ) . '>'
				. esc_html( $c ) . '</option>';
		}
		/* Cơ sở hiện tại không có trong danh sách (gõ lệch, cơ sở đã bỏ) thì GIỮ nó làm lựa chọn
		   đang chọn — không giữ thì ô tự nhảy về dòng đầu, và bấm Lưu một cái là chuyển cả trang
		   người sang một cơ sở không ai định chuyển. */
		if ( ! $co ) {
			$h .= '<option value="' . esc_attr( $cs_cu ) . '" selected>'
				. esc_html( '' === trim( $cs_cu ) ? '— chưa khai —' : $cs_cu ) . '</option>';
		}
		return $h . '</select>';
	}

	/**
	 * Ô VAI TRÒ — sửa được ngay tại đây.
	 *
	 * Anh Thắng 27/08/2026: *"chỗ cột vai trò vẫn đang khóa chưa đổi vai trò được"*.
	 *
	 * 🔴 Ô XỔ, KHÔNG PHẢI BA NÚT như cột quyền. Vai có SÁU giá trị chứ không phải ba; vẽ sáu
	 *    nút cạnh nhau là mỗi hàng dài thêm một gang tay, mà cột này người ta đụng tới hiếm hơn
	 *    cột quyền nhiều. Hai kiểu ô khác nhau ở đây là CÓ CHỦ Ý, không phải quên đồng bộ.
	 *
	 * ⚠️ CHỈ VẼ Ô XỔ KHI NGƯỜI KHAI THẬT SỰ ĐỔI ĐƯỢC. Vẽ cho cả những hàng họ không đụng được
	 *    thì bấm xong bấm Lưu mới nhận câu chối — mà giữa một trang 50 người thì câu chối ấy
	 *    trôi mất, và người ta tưởng mình đã đổi. Không đổi được thì in ra chữ, kèm lý do ở
	 *    thuộc tính `title`.
	 */
	private static function o_vai( $toi, $ma, $vai_cu ) {
		$ma_vai  = VHCC_Vai::ma( $vai_cu );
		$ten_cu  = VHCC_Vai::TEN[ $ma_vai ];
		$bac_toi = VHCC_Vai::bac( $toi );
		$ma_toi  = trim( (string) ( isset( $toi['ma_nv'] ) ? $toi['ma_nv'] : '' ) );

		if ( '' === $ma ) { return '<span class="o-vai">' . esc_html( $ten_cu ) . '</span>'; }
		if ( '' !== $ma_toi && $ma_toi === $ma ) {
			return '<span class="o-vai" title="Không tự đổi vai trò của chính mình được">'
				. esc_html( $ten_cu ) . ' <span class="chua-ma">(chính bạn)</span></span>';
		}
		if ( VHCC_Vai::bac( array( 'role' => (string) $vai_cu ) ) > $bac_toi ) {
			return '<span class="o-vai" title="Người này đang ở bậc cao hơn vai của bạn">'
				. esc_html( $ten_cu ) . ' 🔒</span>';
		}

		/* Danh sách vai đọc từ `VHCC_Auth` — nơi duy nhất khai tên vai của cả hệ — rồi CẮT ở
		   bậc của người đang khai. Kế toán không thấy dòng "Admin" trong ô xổ, nên không có
		   đường bấm nhầm rồi nhận câu chối. */
		$h = '<select class="o-q-vai" name="vai[' . esc_attr( $ma ) . ']">';
		$co_cu = false;
		/* Đọc `VHCC_Vai::ds_ten()` chứ không đọc hằng của `VHCC_Auth`: danh sách nay gồm cả vai
		   TỰ TẠO ("Kế toán POSH", "Kế toán nhân sự"…). Đọc hằng thì vai vừa khai không có mặt
		   trong ô xổ — khai xong không gán được cho ai, tức là khai để đấy. */
		foreach ( VHCC_Vai::ds_ten() as $ten ) {
			if ( VHCC_Vai::bac( array( 'role' => $ten ) ) > $bac_toi ) { continue; }
			$chon = ( trim( (string) $vai_cu ) === $ten );
			if ( $chon ) { $co_cu = true; }
			$h .= '<option value="' . esc_attr( $ten ) . '"' . selected( true, $chon, false ) . '>'
				. esc_html( $ten ) . '</option>';
		}
		/* ⚠️ Hồ sơ đang ghi một chuỗi KHÔNG có trong danh sách ("ketoan", "NV", ô trống…) thì
		   phải giữ nguyên nó làm lựa chọn đang chọn. Không giữ thì ô xổ tự nhảy về dòng đầu, và
		   người khai chỉ bấm Lưu một cái là đổi vai cả trang mà không hề định đổi ai. */
		if ( ! $co_cu ) {
			$tho = trim( (string) $vai_cu );
			$h  .= '<option value="' . esc_attr( $tho ) . '" selected>'
				. esc_html( '' === $tho ? '— chưa khai —' : $tho . ' (chưa chuẩn)' ) . '</option>';
		}
		return $h . '</select>';
	}

	/**
	 * MỘT Ô QUYỀN — ba nút bấm liền nhau thay cho ô xổ.
	 *
	 * @param string $ma  Mã NV — cũng là thứ làm tên trường, nên phải khác rỗng.
	 * @param string $k   Khoá trang.
	 * @param string $dat Đang đặt: '' | 'mo' | 'khoa'.
	 * @param bool   $mac Theo vai thì người này CÓ vào được không — để in ✓ hay ✕ lên nút đầu.
	 *
	 * ⚠️ NÚT ĐẦU PHẢI NÓI RA THEO VAI LÀ VÀO ĐƯỢC HAY KHÔNG. Chỉ viết "vai" thì cả cột trông
	 *    giống hệt nhau, và người khai không biết bỏ ô ấy ở mặc định thì người ta vào được hay
	 *    không — tức là không quyết được có cần ngoại lệ hay không, đúng câu hỏi họ mở trang
	 *    này ra để trả lời.
	 *
	 * ⚠️ `id` phải DUY NHẤT trong cả trang: `<label>` bọc `<input>` thì bấm vào chữ là trúng ô,
	 *    nhưng trùng `id` là trình duyệt nhảy về ô ĐẦU TIÊN mang id ấy — bấm ở hàng 40 mà đổi
	 *    hàng 1. Ghép cả mã lẫn khoá trang, rồi băm cho sạch ký tự lạ.
	 */
	private static function ba_nut( $ma, $k, $dat, $mac ) {
		$goc = 'q' . substr( md5( $ma . '|' . $k ), 0, 10 );
		$h   = '<span class="ba">';
		$cac = array(
			''     => array( 'ten' => 'vai ' . ( $mac ? '✓' : '✕' ), 'lop' => 'v-vai',
				'chu' => 'Theo vai — ' . ( $mac ? 'vai hiện tại vào được' : 'vai hiện tại không vào được' ) ),
			'mo'   => array( 'ten' => 'Mở',   'lop' => 'v-mo',   'chu' => 'Mở riêng cho người này, dù vai chưa tới' ),
			'khoa' => array( 'ten' => 'Khoá', 'lop' => 'v-khoa', 'chu' => 'Khoá riêng người này, dù vai đã đủ' ),
		);
		foreach ( $cac as $gt => $c ) {
			$id = $goc . ( '' === $gt ? 'v' : $gt );
			$h .= '<label for="' . esc_attr( $id ) . '" title="' . esc_attr( $c['chu'] ) . '">'
				. '<input type="radio" id="' . esc_attr( $id ) . '" class="' . esc_attr( $c['lop'] ) . '"'
				. ' name="o[' . esc_attr( $ma ) . '][' . esc_attr( $k ) . ']"'
				. ' value="' . esc_attr( $gt ) . '"' . checked( $dat, $gt, false ) . '>'
				. '<span>' . esc_html( $c['ten'] ) . '</span></label>';
		}
		return $h . '</span>';
	}

	/**
	 * Cột Ghế có được vẽ ra không.
	 *
	 * 🔴 VẼ MỘT CỘT MÀ NGƯỜI XEM KHÔNG BẤM NỔI LÀ TỆ HƠN KHÔNG VẼ. Quyền đẩy đặt ở `he_thong`
	 *    (Admin) vì đó là màn có ngăn tiền, và vì PIN dùng chung — đẩy nhầm một người là trao
	 *    cho họ chìa khoá mà chính họ cũng không biết mình đang cầm.
	 */
	private static function cot_ghe( $toi ) {
		return VHCC_DayGhe::co_he_ghe() && VHCC_Vai::duoc( $toi, VHCC_DayGhe::QUYEN );
	}

	/**
	 * CỘT VẬN HÀNH CHI PHÍ — anh Thắng 28/08/2026: *"bên quản lý nhân sự chưa cho đẩy nhân sự
	 * sang vận hành chi phí"*, rồi *"Đồng bộ nhân sự với hệ thống vận hành chi phí luôn nhé em"*.
	 *
	 * Cùng cơ chế với cột Ghế: ĐẨY NGƯỜI THẬT sang sổ người dùng của app chi phí, chứ không ghi
	 * một ngoại lệ vào sổ quyền — app ấy có phiên riêng và không đọc `ma_nv` bên này.
	 */
	private static function cot_chi_phi( $toi ) {
		return VHCC_DayChiPhi::co_he_chi_phi() && VHCC_Vai::duoc( $toi, VHCC_DayChiPhi::QUYEN );
	}

	/** HAI nút, y như cột Ghế: có mặt bên ấy, hoặc không. */
	private static function hai_nut_chi_phi( $ma ) {
		$dat = VHCC_DayChiPhi::o( $ma );
		$goc = 'p' . substr( md5( $ma . '|chiphi' ), 0, 10 );
		$h   = '<span class="ba">';
		$cac = array(
			'mo' => array( 'ten' => 'Đẩy ✓', 'lop' => 'v-mo',
				'chu' => 'Có mặt trong sổ Người dùng & Phân quyền của app Vận hành chi phí — '
					. 'đăng nhập /chi-phi bằng chính PIN chấm công' ),
			''   => array( 'ten' => 'Gỡ', 'lop' => 'v-khoa',
				'chu' => 'Không có trong sổ người dùng của app Vận hành chi phí' ),
		);
		foreach ( $cac as $gt => $c ) {
			$id = $goc . ( '' === $gt ? 'g' : $gt );
			$h .= '<label for="' . esc_attr( $id ) . '" title="' . esc_attr( $c['chu'] ) . '">'
				. '<input type="radio" id="' . esc_attr( $id ) . '" class="' . esc_attr( $c['lop'] ) . '"'
				. ' name="o[' . esc_attr( $ma ) . '][' . esc_attr( VHCC_DayChiPhi::COT ) . ']"'
				. ' value="' . esc_attr( $gt ) . '"' . checked( $dat, $gt, false ) . '>'
				. '<span>' . esc_html( $c['ten'] ) . '</span></label>';
		}
		return $h . '</span>';
	}

	/**
	 * HAI nút, không phải ba. Bên ghế không có khái niệm "theo vai" — người ấy hoặc có mặt
	 * trong sổ người dùng của nó, hoặc không.
	 */
	private static function hai_nut_ghe( $ma ) {
		$dat = VHCC_DayGhe::o( $ma );
		$goc = 'g' . substr( md5( $ma . '|ghe' ), 0, 10 );
		$h   = '<span class="ba">';
		$cac = array(
			'mo' => array( 'ten' => 'Đẩy ✓', 'lop' => 'v-mo',
				'chu' => 'Có mặt trong sổ người dùng của hệ ghế — đăng nhập /ghe bằng chính PIN chấm công' ),
			''   => array( 'ten' => 'Gỡ', 'lop' => 'v-khoa',
				'chu' => 'Không có trong sổ người dùng của hệ ghế' ),
		);
		foreach ( $cac as $gt => $c ) {
			$id = $goc . ( '' === $gt ? 'g' : $gt );
			$h .= '<label for="' . esc_attr( $id ) . '" title="' . esc_attr( $c['chu'] ) . '">'
				. '<input type="radio" id="' . esc_attr( $id ) . '" class="' . esc_attr( $c['lop'] ) . '"'
				. ' name="o[' . esc_attr( $ma ) . '][' . esc_attr( VHCC_DayGhe::COT ) . ']"'
				. ' value="' . esc_attr( $gt ) . '"' . checked( $dat, $gt, false ) . '>'
				. '<span>' . esc_html( $c['ten'] ) . '</span></label>';
		}
		return $h . '</span>';
	}

	/**
	 * Toàn bộ hồ sơ người này được xem — CHỈ lấy `ma_nv` + `ho_ten`, để dò trùng.
	 *
	 * ⚠️ Không gọi lại `ds_nhan_vien()` không lọc: hàm ấy trả về cả hồ sơ đầy đủ (kể cả ô lương
	 *    của những người có quyền xem), tức là kéo 240 dòng × 30 cột về chỉ để đếm hai cột.
	 */
	private static function ds_de_do( $toi ) {
		global $wpdb;
		$ra = array();
		$rows = VHCC_DB::rows( 'SELECT ma_nv, ho_ten, cua_hang FROM ' . VHCC_DB::t( 'nhan_vien' ) );
		foreach ( (array) $rows as $r ) {
			/* Cửa hàng trưởng chỉ dò trong phạm vi họ thấy — đưa lên đầu một cái trùng mà họ
			   không mở nổi hồ sơ để xử thì chỉ tổ làm họ lo. */
			if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $r['cua_hang'] ) ) { continue; }
			$ra[] = $r;
		}
		return $ra;
	}

	private static function o_tim( $toi, $cs, $q, $vai ) {
		/* ➕ THÊM NHÂN SỰ. Anh Thắng: *"Chưa có chỗ bổ sung thêm nhân sự"* — đúng, trang này chỉ
		   có đường mở hồ sơ ĐÃ CÓ, không có đường tạo mới.
		   ⚠️ Trỏ sang `sua=+` của màn Hồ sơ — `VHCC_Web::the_sua_ho_so()` hiểu dấu `+` là "hồ sơ
		      mới" và dựng biểu mẫu có ô Mã NV. KHÔNG dựng biểu mẫu tạo hồ sơ thứ hai ở đây: tạo
		      hồ sơ là cấp Mã NV dùng chung cả chuỗi, mà hai cửa cho cùng một việc thì cửa mới
		      chưa ai gác. */
		if ( class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' )
			&& VHCC_NhanSu::co_quan_tri_nv( $toi ) ) {
			echo '<p style="margin:0 0 10px"><a class="nut chinh" href="'
				. esc_url( add_query_arg( array( 'man' => 'ho_so', 'sua' => '+' ), VHCC_Web::url() ) )
				. '">➕ Thêm nhân sự</a> <span class="mo">Tạo hồ sơ mới là cấp Mã NV dùng chung cả '
				. 'chuỗi — cần vai Quản lý trở lên.</span></p>';
		}

		echo '<form method="get" class="hang" style="margin:0 0 12px">';
		/* Không có permalink thì trang này nhận ra mình bằng `vhcc_ns=1` — ô tìm phải chở nó
		   theo, kẻo bấm Lọc là rơi về trang chủ. */
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<input type="hidden" name="vhcc_ns" value="1">';
		}
		echo '<div><label>Cơ sở</label><select name="ncs"><option value="">— tất cả —</option>';
		foreach ( VHCC_NhanSu::ds_coso() as $c ) {
			echo '<option value="' . esc_attr( $c ) . '"' . selected( $cs, $c, false ) . '>'
				. esc_html( $c ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label>Vai trò</label><select name="nvai"><option value="">— tất cả —</option>';
		foreach ( VHCC_Vai::TEN as $m => $ten ) {
			echo '<option value="' . esc_attr( $m ) . '"' . selected( $vai, $m, false ) . '>'
				. esc_html( $ten ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label>Tìm tên / mã</label>'
			. '<input type="text" name="nq" value="' . esc_attr( $q ) . '" placeholder="tên, mã NV, SĐT"></div>';
		echo '<button class="chinh">Lọc</button>';
		if ( '' !== $cs || '' !== $q || '' !== $vai ) {
			echo '<a class="nut" href="' . esc_url( self::url() ) . '">Bỏ lọc</a>';
		}
		echo '</form>';
	}

	private static function thanh_trang( $p, $so_tr, $tong ) {
		echo '<p class="mo">' . (int) $tong . ' hồ sơ · trang ' . (int) $p . '/' . (int) $so_tr . '</p>';
		if ( $so_tr < 2 ) { return; }
		echo '<div class="hang" style="gap:6px">';
		for ( $i = 1; $i <= $so_tr; $i++ ) {
			$u = add_query_arg( array( 'np' => $i ), self::url_hien() );
			echo '<a class="nut' . ( $i === (int) $p ? ' chinh' : '' ) . '" href="' . esc_url( $u ) . '">'
				. (int) $i . '</a>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ bảng vai trò */

	/**
	 * BẢNG VAI TRÒ — thêm vai riêng của công ty.
	 *
	 * Anh Thắng 27/08/2026: *"muốn thêm bảng vai trò: vì sau anh cần vai trò kế toán nhân sự,
	 * kế toán Posh"*.
	 *
	 * 🔴 MỖI VAI MỚI KẾ THỪA MỘT VAI GỐC, KHÔNG KHAI LẠI TỪNG QUYỀN. Xem chú thích dài ở
	 *    `VHCC_Vai::them()` cho lý do. Tóm tắt: "Kế toán POSH" và "Kế toán nhân sự" khác nhau ở
	 *    TÊN — để điều phối, để biết đơn này của ai — còn quyền thì cả hai đều là Kế toán.
	 *
	 * ⚠️ Gập lại mặc định. Khai vai là việc làm vài lần rồi thôi; để mở sẵn thì nó chiếm chỗ
	 *    của bảng người × trang, thứ người ta mở trang này ra để xem.
	 */
	private static function the_vai( $toi ) {
		$them    = VHCC_Vai::them();
		$bac_toi = VHCC_Vai::bac( $toi );

		echo '<div class="the"><details' . ( $them ? ' open' : '' ) . '>';
		echo '<summary><b>Bảng vai trò</b> — đang có ' . count( $them ) . ' vai riêng của công ty</summary>';
		echo '<p class="mo">Mỗi vai riêng <b>kế thừa quyền</b> của một vai gốc. Đặt tên để phân '
			. 'biệt khi điều phối (“Kế toán POSH”, “Kế toán nhân sự”), còn làm được những gì thì '
			. 'y hệt vai gốc. Cần một người lệch khỏi vai của họ thì dùng bảng <b>Ai vào được '
			. 'trang nào</b> ở trên — lệch ở đó có tên, có chỗ soát lại.</p>';

		if ( $them ) {
			echo '<div class="cuon"><table class="stt"><thead><tr><th>Tên vai</th><th>Quyền y như</th>'
				. '<th>Đang dùng</th><th></th></tr></thead><tbody>';
			foreach ( $them as $ten => $goc ) {
				$so = VHCC_Vai::dem_nguoi( $ten );
				echo '<tr><td><b>' . esc_html( $ten ) . '</b></td>';
				echo '<td>' . esc_html( VHCC_Vai::TEN[ $goc ] ) . '</td>';
				echo '<td>' . ( $so ? esc_html( $so . ' người' ) : '<span class="mo">chưa ai</span>' ) . '</td>';
				echo '<td>';
				/* Nút Bỏ chỉ hiện khi CHƯA AI dùng. Vẽ nó ra rồi chối là mời người ta bấm vào
				   một việc không làm được — mà `VHCC_Vai::xoa_them()` vẫn chặn ở tầng dưới. */
				if ( ! $so ) {
					echo '<form method="post" style="margin:0">'
						. '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">'
						. self::o_loc()
						. '<input type="hidden" name="vai_ten" value="' . esc_attr( $ten ) . '">'
						. '<button name="viec" value="xoa_vai">Bỏ</button></form>';
				} else {
					echo '<span class="mo">đổi vai cho họ trước rồi mới bỏ được</span>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '<form method="post" class="hang" style="margin-top:12px">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		echo '<div><label>Tên vai mới</label>'
			. '<input type="text" name="vai_ten" maxlength="40" placeholder="VD: Kế toán POSH"></div>';
		echo '<div><label>Quyền y như</label><select name="vai_goc">';
		/* ⚠️ CẮT Ở BẬC NGƯỜI ĐANG KHAI. Kế toán không thấy dòng "Admin" — tạo được vai gốc Admin
		   là tạo ra một đường nâng quyền: gán cho người khác, rồi nhờ người ấy nâng mình lên.
		   `VHCC_Vai::dat_them()` cũng chặn ở tầng dưới; đây chỉ là không mời gọi. */
		foreach ( VHCC_Vai::TEN as $ma_g => $ten_g ) {
			if ( VHCC_Vai::BAC[ $ma_g ] > $bac_toi ) { continue; }
			echo '<option value="' . esc_attr( $ma_g ) . '">' . esc_html( $ten_g ) . '</option>';
		}
		echo '</select></div>';
		echo '<button class="them" name="viec" value="them_vai">Thêm vai</button>';
		echo '</form>';
		echo '</details></div>';
	}

	/**
	 * CHIA ĐẦU VIỆC — ai làm được việc gì, lệch khỏi thang vai.
	 *
	 * Anh Thắng 27/08/2026: *"Chia bộ phận ai xem được từng đầu việc của mình"* — sau khi kể ra
	 * ba bộ phận dùng chung một đường: *"Nhân viên thì vào chấm công và xem công mình · Kế toán
	 * thì vào check công tháng · Kỹ thuật thì vào setup máy chấm công online"*.
	 *
	 * 🔴 KHÁC BẢNG "AI VÀO ĐƯỢC TRANG NÀO" Ở TRÊN. Bảng kia mở/khoá CẢ MỘT TRANG. Bảng này nhỏ
	 *    hơn một trang: trong cùng trang quản trị, ai thấy tab nào, ai bấm được việc nào. Người
	 *    Kỹ thuật cần đúng một việc — máy chấm công — chứ không cần cả trang, và cũng không nên
	 *    được nâng lên Admin chỉ để dựng một cái máy.
	 */
	private static function the_dau_viec( $toi ) {
		$nl      = VHCC_Vai::ngoai_le();
		$bac_toi = VHCC_Vai::bac( $toi );
		$so      = 0;
		foreach ( $nl as $ds_x ) { $so += count( (array) $ds_x ); }

		echo '<div class="the"><details' . ( $so ? ' open' : '' ) . '>';
		echo '<summary><b>Chia đầu việc</b> — đang có ' . (int) $so . ' dòng khác mặc định</summary>';
		echo '<p class="mo">Thang vai vẫn quyết định mặc định. Bảng này chỉ giữ những chỗ '
			. '<b>khác</b> mặc định — mở thêm một đầu việc cho một vai (VD vai <b>Kỹ thuật</b> '
			. 'được <b>Máy chấm công &amp; firmware</b> mà không cần lên Admin), hoặc thu một đầu '
			. 'việc của một người. Khai theo <b>vai</b> thì cả nhóm theo; khai theo <b>Mã NV</b> '
			. 'thì đè lên dòng của vai, cho đúng một người.</p>';
		echo '<p class="mo">⚠️ Không tự khai cho chính mình hay cho chính vai mình — nhờ người '
			. 'khác khai. Và chỉ chia được đầu việc mà vai của mình đang làm được.</p>';

		if ( $nl ) {
			echo '<div class="cuon"><table class="stt"><thead><tr><th>Cho ai</th><th>Đầu việc</th>'
				. '<th>Đang đặt</th><th></th></tr></thead><tbody>';
			foreach ( $nl as $dich => $ds_x ) {
				foreach ( $ds_x as $q => $v ) {
					echo '<tr><td>' . ( 0 === strpos( $dich, 'vai:' )
						? 'vai <b>' . esc_html( substr( $dich, 4 ) ) . '</b>'
						: 'Mã NV <b>' . esc_html( substr( $dich, 3 ) ) . '</b>' ) . '</td>';
					echo '<td>' . esc_html( VHCC_Vai::ten_viec( $q ) )
						. ' <span class="mo">(' . esc_html( $q ) . ')</span></td>';
					echo '<td>' . ( 'mo' === $v ? '<span class="co">Mở</span>'
						: '<span class="chua">Khoá</span>' ) . '</td>';
					echo '<td><form method="post" style="margin:0">'
						. '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">'
						. self::o_loc()
						. '<input type="hidden" name="dv_dich" value="' . esc_attr( $dich ) . '">'
						. '<input type="hidden" name="dv_quyen" value="' . esc_attr( $q ) . '">'
						. '<input type="hidden" name="dv_dat" value="">'
						. '<button name="viec" value="dau_viec">Gỡ</button></form></td></tr>';
				}
			}
			echo '</tbody></table></div>';
		}

		echo '<form method="post" class="hang" style="margin-top:12px">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		echo '<div><label for="dv_ai">Cho ai</label><select id="dv_ai" name="dv_dich">';
		foreach ( VHCC_Vai::ds_ten() as $t_vai ) {
			echo '<option value="vai:' . esc_attr( $t_vai ) . '">vai ' . esc_html( $t_vai ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="dv_ma">…hoặc riêng Mã NV</label>'
			. '<input id="dv_ma" type="text" name="dv_ma" maxlength="20" placeholder="để trống nếu khai theo vai"></div>';
		echo '<div><label for="dv_q">Đầu việc</label><select id="dv_q" name="dv_quyen">';
		/* ⚠️ CẮT Ở BẬC NGƯỜI ĐANG KHAI, y như ô chọn vai gốc ở khối trên. Kế toán không thấy
		   dòng "Cài đặt hệ thống" — vẽ ra rồi chối là mời người ta bấm vào một việc không làm
		   được. `VHCC_Vai::dat_ngoai_le()` vẫn chặn ở tầng dưới; đây chỉ là không mời gọi. */
		foreach ( VHCC_Vai::QUYEN as $q_x => $bac_x ) {
			if ( VHCC_Vai::BAC[ $bac_x ] > $bac_toi ) { continue; }
			echo '<option value="' . esc_attr( $q_x ) . '">' . esc_html( VHCC_Vai::ten_viec( $q_x ) )
				. ' — mặc định từ ' . esc_html( VHCC_Vai::TEN[ $bac_x ] ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="dv_d">Đặt thành</label><select id="dv_d" name="dv_dat">'
			. '<option value="mo">Mở</option><option value="khoa">Khoá</option></select></div>';
		echo '<button class="chinh" name="viec" value="dau_viec">Lưu dòng</button>';
		echo '</form>';
		echo '</details></div>';
	}

	/* ------------------------------------------------------------------ bảng mặc định */

	/** Thang vai đang quy định gì — để trả lời "sao người này vào được" mà không phải đọc mã. */
	private static function the_mac_dinh( $ds_trang ) {
		echo '<div class="the"><details>';
		echo '<summary>Mặc định theo vai — trang nào vai nào vào được</summary>';
		echo '<div class="cuon"><table><thead><tr><th>Trang</th><th>Địa chỉ</th>';
		foreach ( VHCC_Vai::TEN as $ten ) { echo '<th>' . esc_html( $ten ) . '</th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $ds_trang as $t ) {
			echo '<tr><td><b>' . esc_html( $t['ten'] ) . '</b></td>';
			echo '<td class="mo">' . esc_html( $t['url'] ) . '</td>';
			foreach ( VHCC_Vai::TEN as $ma_vai => $ten_vai ) {
				/* Hỏi bằng MÃ vai, không bằng tên: `VHCC_Vai::ma()` nhận cả hai, nhưng mã là
				   thứ không lệ thuộc chính tả tiếng Việt. */
				$ok = VHCC_Vai::duoc( array( 'role' => $ma_vai ), $t['quyen'] );
				echo '<td class="o-q-td">' . ( $ok ? '<span class="co">✓</span>'
					: '<span class="chua">✕</span>' ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="mo">Bậc trên làm được mọi việc của bậc dưới. Muốn đổi cột này thì đổi '
			. '<b>vai trò</b> trong hồ sơ, chứ đừng tích ngoại lệ cho từng người — ngoại lệ là để '
			. 'dành cho những trường hợp thật sự lệch.</p>';
		echo '</details></div>';
	}
}
