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
	/* `nman` + bốn ô lọc của TAB LỆNH cũng phải sống sót: bấm Đẩy xong mà rơi về trang tổng
	   không lọc là người đang làm dở mất chỗ đứng, và dễ đẩy nhầm ở lượt sau. */
	const THAM_SO = array( 'ncs', 'nq', 'nvai', 'nmang', 'nbp', 'np', 'sua_o', 'pin_o', 'gop_a', 'gop_b',
		'nman', 'vbp', 'vcs', 'vq', 'vchi' );

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
		/* Hai nút máy chấm công ở cột Họ tên ("sửa lại trên máy 🔄" / "gỡ khỏi máy 📵") — cùng lý
		   do và cùng lối với `ghep_voi`/`xoa_ma` ở trên. Xem viec_sua_may()/viec_xoa_may(). */
		/* Nút "+"/"−" trên chip Ghế / Chi phí ở cột Quyền vào trang — anh Thắng 13/09/2026:
		   *"đang có 1 nhân viên mới, cần add vào trang chi phí để nhập báo cáo, nhưng không có
		   chỗ"*. Từ 3.77.0 năm cột quyền gộp làm một cột CHỈ ĐỌC, và chỗ khai chuyển xuống khối
		   luật bộ phận — mà luật bộ phận thì đụng cả phòng. Nút này trả lại đường đẩy ĐÍCH DANH
		   một người, không đi qua luật nhóm. Tên riêng `day_1`, cùng lối với `ghep_voi`/`xoa_ma`. */
		elseif ( isset( $_POST['day_1'] ) ) { $viec_gui = 'day_mot'; }
		/* Hai nút của TAB LỆNH (đẩy / gỡ hàng loạt sang bản chi phí của tab đang mở). Mỗi nút một
		   tên riêng vì chúng làm hai việc NGƯỢC NHAU trên cùng một danh sách đã tích — gộp
		   vào một `viec` rồi đọc thêm một ô ẩn là mở đường cho lượt bấm nhầm thành lượt gỡ. */
		elseif ( isset( $_POST['vp_day'] ) || isset( $_POST['vp_go'] ) ) { $viec_gui = 'day_vp'; }
		elseif ( isset( $_POST['sua_may'] ) ) { $viec_gui = 'sua_may_nv'; }
		elseif ( isset( $_POST['xoa_may'] ) ) { $viec_gui = 'xoa_may_nv'; }

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
		if ( 'vai_bp' === $viec )      { return self::viec_vai_bp( $toi ); }
		if ( 'luu_nhom' === $viec )    { return self::viec_luu_nhom( $toi ); }
		if ( 'bp_them' === $viec )     { return self::viec_bp_them( $toi ); }
		if ( 'ten_mang' === $viec )    { return self::viec_ten_mang( $toi ); }
		if ( 'bd_chi_phi' === $viec )  { return self::viec_bd_chi_phi( $toi ); }
		if ( 'bdbp_chi_phi' === $viec ) { return self::viec_bdbp_chi_phi( $toi ); }
		if ( 'mang_an' === $viec )     { return self::viec_mang_an( $toi, true ); }
		if ( 'mang_hien' === $viec )   { return self::viec_mang_an( $toi, false ); }
		if ( 'bp_doi_ten' === $viec )  { return self::viec_bp_doi_ten( $toi ); }
		if ( 'bp_gop' === $viec )      { return self::viec_bp_gop( $toi ); }
		if ( 'bp_xoa' === $viec )      { return self::viec_bp_xoa( $toi ); }
		if ( 'vai_goi_y' === $viec )   { return self::viec_vai_goi_y( $toi ); }
		if ( 'ap_day' === $viec )      { return self::viec_ap_day( $toi ); }
		if ( 'day_mot' === $viec )     { return self::viec_day_mot( $toi ); }
		if ( 'day_vp' === $viec )      { return self::viec_day_vp( $toi ); }
		if ( 'gop_that' === $viec )    { return self::viec_gop_that( $toi ); }
		if ( 'ghe_rieng' === $viec )   { return self::viec_ghe_rieng( $toi ); }
		if ( 'quyen_noi_bo' === $viec ) { return self::viec_quyen_noi_bo( $toi ); }
		if ( 'ghep_ma' === $viec )     { return self::viec_ghep_ma( $toi ); }
		if ( 'ghep_voi_nv' === $viec ) { return self::viec_ghep_voi( $toi ); }
		if ( 'bo_ghep_ma' === $viec )  { return self::viec_bo_ghep_ma( $toi ); }
		if ( 'don_ma' === $viec )      { return self::viec_don_ma( $toi ); }
		if ( 'xoa_hs' === $viec )      { return self::viec_xoa_hs( $toi ); }
		if ( 'sua_may_nv' === $viec )  { return self::viec_sua_may( $toi ); }
		if ( 'xoa_may_nv' === $viec )  { return self::viec_xoa_may( $toi ); }
		if ( 'duyet_may' === $viec )   { return self::viec_duyet_may( $toi ); }
		if ( 'tu_choi_may' === $viec ) { return self::viec_tu_choi_may( $toi ); }
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

		/* 🔴 KHÔNG CÒN Ô "VÀO TRANG" ĐỂ MÀ CẢNH BÁO — anh Thắng báo chuyện ấy HAI LẦN.
		   28/08/2026: *"Trang nội bộ là trang chung thì ai vẫn được vào mà"* — lần đó bản vá
		   chỉ kéo cái ô xổ từ wp-admin ra đây rồi thêm một dòng cảnh báo, tức là vẫn để nguyên
		   cái bẫy, chỉ dán thêm biển báo cạnh nó. 30/08/2026 anh vấp lại đúng chỗ đó:
		   *"trang nội bộ là chung công ty nên ai cũng vào được hết, có mật khẩu là vào, đó là
		   lý do anh đặt trang chủ mà"*.

		   Nay 'vao' đã bị gỡ hẳn khỏi `VHNB_Quyen::VIEC`, nên bảng dưới không còn ô ấy và
		   `$moi['vao']` không bao giờ tồn tại. Chặn riêng một người thì dùng chính bảng ngoại
		   lệ của trang này (`VHCC_Cong`), khoá theo TỪNG NGƯỜI. */
		unset( $moi );
		return array( array( 'ok' => 'Đã lưu phân quyền trang Nội bộ.' ) );
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

	/**
	 * MÀN XEM TRƯỚC GỘP HỒ SƠ — bày hết hậu quả ra, rồi mới cho gõ xác nhận.
	 *
	 * 🔴 Gộp xoá một hồ sơ và dời `ma_nv` ở hai mươi bảng. KHÔNG CÓ ĐƯỜNG LÙI. Nên màn này phải
	 *    trả lời được ba câu trước khi anh Thắng bấm: dời bao nhiêu, có lượt nào trùng ngày
	 *    không, và hai hồ sơ đang khác nhau ở ô nào.
	 */
	private static function the_gop( $toi ) {
		$a = isset( $_GET['gop_a'] ) ? sanitize_text_field( wp_unslash( $_GET['gop_a'] ) ) : '';
		$b = isset( $_GET['gop_b'] ) ? sanitize_text_field( wp_unslash( $_GET['gop_b'] ) ) : '';
		if ( '' === $a || '' === $b ) { return; }

		$xt = VHCC_NhanSu::gop_ho_so( $toi, $a, $b );
		echo '<div class="the">';
		echo '<h2>Gộp hai hồ sơ</h2>';
		if ( empty( $xt['ok'] ) ) {
			echo '<div class="bao loi">' . esc_html( (string) $xt['error'] ) . '</div>';
			echo '<a class="nut" href="' . esc_url( self::url() ) . '">← Về bảng nhân sự</a></div>';
			return;
		}

		echo '<div class="bao canh"><b>Chưa đổi gì cả</b> — đây là bản xem trước. Gộp xong thì '
			. '<b>không lùi lại được</b>: hồ sơ <b>' . esc_html( $b ) . '</b> bị xoá, và mọi lịch sử '
			. 'của nó chuyển sang <b>' . esc_html( $a ) . '</b>.</div>';

		echo '<div class="luoi">';
		echo '<div class="the"><b>GIỮ LẠI</b><div class="cs-ten">' . esc_html( $a ) . '</div>'
			. '<div class="mo">' . esc_html( (string) $xt['tenGiu'] ) . '</div></div>';
		echo '<div class="the"><b style="color:var(--do)">SẼ XOÁ</b><div class="cs-ten">' . esc_html( $b ) . '</div>'
			. '<div class="mo">' . esc_html( (string) $xt['tenBo'] ) . '</div></div>';
		echo '</div>';

		/* Đảo chiều: chọn nhầm bên nào giữ là hỏng, nên để đổi bằng một cú bấm. */
		echo '<p class="mo"><a href="' . esc_url( add_query_arg(
			array( 'gop_a' => $b, 'gop_b' => $a ), self::url_hien() ) ) . '">⇄ Đảo lại</a>'
			. ' — giữ <b>' . esc_html( $b ) . '</b>, xoá <b>' . esc_html( $a ) . '</b> thay vì ngược lại.</p>';

		echo '<h3>Dữ liệu sẽ chuyển</h3>';
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Sổ</th><th>Số dòng chuyển</th>'
			. '<th>Trùng khoá</th></tr></thead><tbody>';
		$co_dong = false;
		foreach ( (array) $xt['bang'] as $ten => $x ) {
			if ( empty( $x['doi'] ) && empty( $x['dung'] ) ) { continue; }
			$co_dong = true;
			echo '<tr><td>' . esc_html( $ten ) . '</td><td><b>' . (int) $x['doi'] . '</b></td>';
			echo '<td>' . ( $x['dung']
				? ( 'cham_cong' === $ten
					? '<span class="chu-co">' . (int) $x['dung'] . ' lượt trùng ngày — sẽ GIỮ CẢ HAI, '
						. 'chỉ đổi hậu tố</span>'
					: '<span class="chua">' . (int) $x['dung'] . ' dòng sẽ bị BỎ (giữ dòng của '
						. esc_html( $a ) . ')</span>' )
				: '<span class="mo">—</span>' ) . '</td></tr>';
		}
		if ( ! $co_dong ) { echo '<tr><td colspan="3" class="mo">Hồ sơ này chưa có dữ liệu gì.</td></tr>'; }
		echo '</tbody></table></div>';

		if ( ! empty( $xt['dungCC'] ) ) {
			echo '<div class="bao ok"><b>Lượt chấm trùng ngày sẽ KHÔNG bị mất</b> — mỗi lượt là tiền '
				. 'công, nên hệ đổi hậu tố để giữ cả hai. Bảng công sẽ hiện hai lượt trong ngày, anh '
				. 'xem rồi xoá bớt cái thừa: ' . esc_html( implode( ' · ', array_slice( (array) $xt['dungCC'], 0, 12 ) ) )
				. ( count( (array) $xt['dungCC'] ) > 12 ? ' …' : '' ) . '</div>';
		}

		/* 🔴 HỌ TÊN KHÁC NHAU = CÓ THỂ KHÔNG PHẢI MỘT NGƯỜI. Đây là hỏng nặng nhất mà màn này
		   có thể gây ra: gộp công của hai người vào một, và sau đó KHÔNG phân biệt được hàng nào
		   vốn của ai. Mọi cảnh báo khác chỉ là "sửa lại cho gọn"; cảnh báo này là "dừng lại".
		   Vẫn cho gộp (đổi tên là chuyện có thật — lấy chồng, sửa chính tả), nhưng phải đập vào
		   mắt, không nằm lẫn trong bảng so ô. */
		$ten_khac = false; $cs_khac = false;
		foreach ( (array) $xt['khac'] as $k_t ) {
			if ( 'Họ tên' === $k_t['o'] ) { $ten_khac = true; }
			if ( 'Cơ sở chính' === $k_t['o'] ) { $cs_khac = true; }
		}
		/* Tên GIỐNG mà cơ sở KHÁC: có thể một người làm hai nơi (thường), có thể hai người trùng
		   tên (cũng thường, tên Việt trùng rất nhiều). Không đủ để chặn, nhưng phải nhắc soát. */
		if ( ! $ten_khac && $cs_khac ) {
			echo '<div class="bao canh"><b>Hai hồ sơ này ở HAI CƠ SỞ KHÁC NHAU</b> — có thể là một '
				. 'người làm hai nơi (chuyện thường ở chuỗi), cũng có thể là hai người trùng tên '
				. '(tên Việt trùng rất nhiều). Soát <b>CCCD · SĐT · ngày vào làm</b> ở bảng dưới '
				. 'trước khi gõ xác nhận.</div>';
		}
		if ( $ten_khac ) {
			echo '<div class="bao loi"><b>⛔ HAI HỒ SƠ NÀY GHI HAI HỌ TÊN KHÁC NHAU</b> — rất có thể '
				. 'đây là <b>hai người khác nhau</b>, không phải một người hai hồ sơ. Gộp nhầm là trộn '
				. 'công của hai người vào một, và sau đó <b>không phân biệt được lượt nào của ai</b>. '
				. 'Soát kỹ CCCD / SĐT / ngày vào làm ở bảng dưới trước khi gõ xác nhận.</div>';
		}
		if ( ! empty( $xt['khac'] ) ) {
			echo '<h3>Hai hồ sơ đang khác nhau</h3>';
			echo '<p class="mo">Gộp xong thì <b>giữ nguyên ô của ' . esc_html( $a ) . '</b>. Ô nào của '
				. esc_html( $b ) . ' mới đúng thì sửa sang ' . esc_html( $a ) . ' TRƯỚC khi gộp.</p>';
			echo '<div class="cuon"><table class="stt"><thead><tr><th>Ô</th>'
				. '<th>' . esc_html( $a ) . ' (giữ)</th><th>' . esc_html( $b ) . ' (xoá)</th></tr></thead><tbody>';
			foreach ( (array) $xt['khac'] as $k ) {
				echo '<tr><td>' . esc_html( (string) $k['o'] ) . '</td>'
					. '<td>' . ( '' !== $k['giu'] ? esc_html( (string) $k['giu'] ) : '<span class="mo">(trống)</span>' ) . '</td>'
					. '<td>' . ( '' !== $k['bo'] ? esc_html( (string) $k['bo'] ) : '<span class="mo">(trống)</span>' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		/* ⚠️ KHÔNG IN PIN. Chỉ nói chuyện gì sẽ xảy ra với nó. */
		echo '<h3>Mã PIN</h3><p class="mo">';
		if ( ! empty( $xt['pin']['chuyen'] ) ) {
			echo '<b>' . esc_html( $a ) . ' chưa có PIN, ' . esc_html( $b ) . ' thì có</b> — PIN ấy sẽ '
				. 'chuyển sang ' . esc_html( $a ) . ', để người này vẫn đăng nhập được sau khi gộp.';
		} elseif ( ! empty( $xt['pin']['giuCo'] ) && ! empty( $xt['pin']['boCo'] ) && ! empty( $xt['pin']['khac'] ) ) {
			echo 'Hai hồ sơ đang mang <b>hai PIN khác nhau</b>. Giữ PIN của ' . esc_html( $a )
				. ', bỏ PIN của ' . esc_html( $b ) . ' — sau khi gộp, người này chỉ còn gõ được PIN của '
				. esc_html( $a ) . '. Nhớ báo họ.';
		} elseif ( empty( $xt['pin']['giuCo'] ) && empty( $xt['pin']['boCo'] ) ) {
			echo 'Cả hai đều <b>chưa có PIN</b> — gộp xong vẫn chưa đăng nhập được, phải cấp PIN.';
		} else {
			echo 'Hai bên cùng một PIN — không có gì phải chọn.';
		}
		echo '</p>';

		if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) {
			echo '<div class="bao canh">Gộp hồ sơ là việc của <b>Admin</b>. Nhờ Admin bấm giúp.</div>';
		} else {
			/* Gõ tay, không phải ô tích — cùng lối với "XOA HET" và "MAT DUONG". */
			echo '<form method="post" class="hang" style="margin-top:12px">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
			echo '<input type="hidden" name="gop_a" value="' . esc_attr( $a ) . '">';
			echo '<input type="hidden" name="gop_b" value="' . esc_attr( $b ) . '">';
			echo '<div><label>Gõ <code>GOP</code> để xác nhận</label>'
				. '<input type="text" name="xac_nhan" placeholder="GOP" style="max-width:140px"></div>';
			echo '<button class="nut-do" name="viec" value="gop_that">Gộp ' . esc_html( $b )
				. ' vào ' . esc_html( $a ) . '</button>';
			echo '<a class="nut" href="' . esc_url( self::url() ) . '">Huỷ</a>';
			echo '</form>';
		}
		echo '</div>';
	}

	/**
	 * GÕ THẲNG HAI MÃ ĐỂ GỘP — đường không phụ thuộc vào phép tự dò.
	 *
	 * 🔴 Phép dò trùng so tên đã chuẩn hoá. Hai hồ sơ của cùng một người mà tên gõ lệch nhau
	 *    ("Nguyễn Thị Mai Anh" / "Nguyen Thi Mai Anh 1" / thêm dấu cách) thì KHÔNG vào nhóm nào,
	 *    nên không có nút nào mời ghép — và người ta tưởng hệ không làm được.
	 *    Ô gõ tay là đường cuối cùng, luôn đi được. Vẫn qua đúng màn xem trước ấy.
	 */
	private static function the_gop_tay( $toi ) {
		if ( ! VHCC_Vai::duoc( $toi, 'he_thong' ) ) { return; }
		echo '<div class="the"><details><summary><b>Gộp hai hồ sơ bất kỳ</b> '
			. '<span class="mo" style="font-weight:400">(khi hệ không tự dò ra cặp)</span></summary>';
		echo '<p class="mo">Hệ tự dò cặp bằng cách so <b>tên</b>. Hai hồ sơ cùng người mà tên gõ lệch '
			. 'nhau thì không vào nhóm nào, nên không có nút nào mời ghép. Gõ thẳng hai mã ở đây — '
			. 'vẫn đi qua <b>màn xem trước</b>, chưa gộp gì ngay.</p>';
		echo '<form method="get" class="hang">';
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<input type="hidden" name="vhcc_ns" value="1">';
		}
		echo '<div><label>Mã GIỮ LẠI</label><input type="text" name="gop_a" placeholder="vd: MNNV2KVC0036" required></div>';
		echo '<div><label>Mã SẼ XOÁ</label><input type="text" name="gop_b" placeholder="vd: MNNV2KVC0024" required></div>';
		echo '<button class="chinh">Xem trước</button>';
		echo '</form></details></div>';
	}

	/** Gộp thật — chỉ khi gõ đúng chuỗi xác nhận. */
	private static function viec_gop_that( $toi ) {
		$a  = isset( $_POST['gop_a'] ) ? sanitize_text_field( wp_unslash( $_POST['gop_a'] ) ) : '';
		$b  = isset( $_POST['gop_b'] ) ? sanitize_text_field( wp_unslash( $_POST['gop_b'] ) ) : '';
		$go = isset( $_POST['xac_nhan'] ) ? trim( (string) wp_unslash( $_POST['xac_nhan'] ) ) : '';
		if ( 'GOP' !== $go ) {
			return array( array( 'loi' => 'Chưa gộp gì. Phải gõ đúng chữ GOP (in hoa, không dấu) vào '
				. 'ô xác nhận — gộp không lùi lại được.' ) );
		}
		$kq = VHCC_NhanSu::gop_ho_so( $toi, $a, $b, true );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => (string) $kq['error'] ) ); }
		$cc = isset( $kq['bang']['cham_cong'] ) ? $kq['bang']['cham_cong'] : array( 'doi' => 0, 'dung' => 0 );
		$bo_di = 0;
		foreach ( (array) $kq['bang'] as $ten => $x ) {
			if ( 'cham_cong' === $ten ) { continue; }
			$bo_di += (int) $x['dung'];
		}
		return array( array( 'ok' => 'Đã gộp ' . $b . ' vào ' . $a . '. Chuyển ' . (int) $cc['doi']
			. ' lượt chấm công'
			. ( $cc['dung'] ? ' (trong đó ' . (int) $cc['dung'] . ' lượt trùng ngày đã đổi hậu tố — '
				. 'giữ cả hai, xem bảng công rồi xoá bớt cái thừa)' : '' )
			. ( $bo_di ? ', bỏ ' . $bo_di . ' dòng trùng khoá ở các sổ khác' : '' )
			. ( ! empty( $kq['pinChuyen'] ) ? ', PIN đã chuyển sang ' . $a : '' )
			. '. Hồ sơ ' . $b . ' đã xoá — việc này đã ghi vào nhật ký hồ sơ.' ) );
	}

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

	/**
	 * SỬA LẠI TRÊN MÁY — nút "sửa lại trên máy 🔄" ở cột Họ tên.
	 *
	 * Anh Thắng 29/08/2026: *"thêm tính năng xoá, sửa trường hợp lỗi hoặc nhân viên nghỉ việc"*.
	 * Sửa hồ sơ (tên, ảnh, giới tính…) qua ô "sửa ▾"/"đầy đủ ↗" chỉ ghi lên web — máy chấm công
	 * KHÔNG tự biết mà cập nhật lại, vì nó chỉ nhận lệnh lúc TẠO hồ sơ (xem `day_len_may()`). Nút
	 * này đẩy LẠI đúng thông tin hiện có trong hồ sơ xuống mọi máy ở cơ sở người ấy.
	 *
	 * ⚠️ ĐỌC HỒ SƠ MỚI TỪ DB (`sua_lai_tren_may()` tự làm việc này), KHÔNG TIN GIÁ TRỊ CLIENT GỬI.
	 *    Nút chỉ mang mã NV; tên/ảnh/giới tính lấy thẳng từ sổ để khỏi có đường gửi giả.
	 */
	private static function viec_sua_may( $toi ) {
		$ma = isset( $_POST['sua_may'] ) ? sanitize_text_field( wp_unslash( $_POST['sua_may'] ) ) : '';
		if ( '' === $ma ) { return array( array( 'loi' => 'Thiếu mã nhân viên.' ) ); }
		$kq = VHCC_NhanSu::sua_lai_tren_may( $toi, $ma );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => $kq['thong_bao'] ) );
	}

	/**
	 * GỠ KHỎI MÁY — nút "gỡ khỏi máy 📵" ở cột Họ tên.
	 *
	 * Dùng khi tạo hồ sơ lỡ đẩy nhầm xuống máy, hoặc nhân viên nghỉ việc mà vẫn còn quẹt được.
	 * 🔴 CHỈ GỠ TRÊN MÁY VẬT LÝ — hồ sơ và lịch sử chấm công trên web KHÔNG bị đụng tới. Muốn xoá
	 *    hẳn hồ sơ thì dùng nút "xoá 🗑" (nếu chưa có lượt chấm công) hoặc đổi Trạng thái làm việc
	 *    thành "Đã nghỉ" ở ô "sửa ▾" (giữ lại lịch sử). Ba việc khác nhau, ba nút khác nhau.
	 */
	private static function viec_xoa_may( $toi ) {
		$ma = isset( $_POST['xoa_may'] ) ? sanitize_text_field( wp_unslash( $_POST['xoa_may'] ) ) : '';
		if ( '' === $ma ) { return array( array( 'loi' => 'Thiếu mã nhân viên.' ) ); }
		$kq = VHCC_NhanSu::xoa_khoi_may( $toi, $ma );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => $kq['thong_bao'] ) );
	}

	/**
	 * DUYỆT LỆNH XUỐNG MÁY — nút "Duyệt" ở khối "Chờ duyệt trước khi xuống máy chấm công".
	 *
	 * Anh Thắng 29/08/2026: *"trước khi đẩy xuống máy, nó sẽ gửi qua admin duyệt 1 lệnh để check
	 * đạt yêu cầu chưa trước khi đẩy"*. Lệnh do Cửa hàng trưởng đặt qua "Thêm nhanh" nằm ở trạng
	 * thái `cho-duyet` (xem `VHCC_NhanSu::day_len_may()`) — máy KHÔNG thấy cho tới khi duyệt ở đây.
	 *
	 * ⚠️ ĐỌC LẠI HÀNG TRƯỚC KHI DUYỆT ĐỂ SOI QUYỀN CƠ SỞ. `op_id` không tự nói cơ sở nào — phải
	 *    tra đúng hàng rồi so `co_quyen_coso()`, không thì Kế toán cơ sở A duyệt được cả lệnh của
	 *    cơ sở B chỉ vì đoán đúng một `op_id`.
	 */
	private static function viec_duyet_may( $toi ) {
		if ( ! VHCC_NhanSu::co_sua_ho_so( $toi ) ) {
			return array( array( 'loi' => 'Duyệt lệnh xuống máy cần vai Kế toán trở lên.' ) );
		}
		$op = isset( $_POST['op_id'] ) ? sanitize_text_field( wp_unslash( $_POST['op_id'] ) ) : '';
		if ( '' === $op ) { return array( array( 'loi' => 'Thiếu mã lệnh.' ) ); }
		if ( ! class_exists( 'VHCC_May' ) || ! method_exists( 'VHCC_May', 'duyet_lenh' ) ) {
			return array( array( 'loi' => 'Chưa cài phần máy chấm công.' ) );
		}
		global $wpdb;
		$q = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'queue' ) . ' WHERE op_id=%s LIMIT 1', $op ), ARRAY_A );
		if ( ! $q ) { return array( array( 'loi' => 'Không có lệnh nào mang mã đó.' ) ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, (string) $q['cua_hang'] ) ) {
			return array( array( 'loi' => 'Không có quyền với cơ sở của lệnh này.' ) );
		}
		$kq = VHCC_May::duyet_lenh( $op, isset( $toi['name'] ) ? (string) $toi['name'] : '' );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => $kq['thong_bao'] ) );
	}

	/** TỪ CHỐI LỆNH XUỐNG MÁY — nút "Từ chối" cùng khối với `viec_duyet_may()`, cùng chốt quyền. */
	private static function viec_tu_choi_may( $toi ) {
		if ( ! VHCC_NhanSu::co_sua_ho_so( $toi ) ) {
			return array( array( 'loi' => 'Từ chối lệnh xuống máy cần vai Kế toán trở lên.' ) );
		}
		$op = isset( $_POST['op_id'] ) ? sanitize_text_field( wp_unslash( $_POST['op_id'] ) ) : '';
		if ( '' === $op ) { return array( array( 'loi' => 'Thiếu mã lệnh.' ) ); }
		if ( ! class_exists( 'VHCC_May' ) || ! method_exists( 'VHCC_May', 'tu_choi_lenh' ) ) {
			return array( array( 'loi' => 'Chưa cài phần máy chấm công.' ) );
		}
		global $wpdb;
		$q = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'queue' ) . ' WHERE op_id=%s LIMIT 1', $op ), ARRAY_A );
		if ( ! $q ) { return array( array( 'loi' => 'Không có lệnh nào mang mã đó.' ) ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, (string) $q['cua_hang'] ) ) {
			return array( array( 'loi' => 'Không có quyền với cơ sở của lệnh này.' ) );
		}
		$kq = VHCC_May::tu_choi_lenh( $op, 'Admin từ chối lúc duyệt',
			isset( $toi['name'] ) ? (string) $toi['name'] : '' );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => $kq['thong_bao'] ) );
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
		if ( class_exists( 'VHCC_DayBaoCao' ) && method_exists( 'VHCC_DayBaoCao', 'da_day' )
			&& method_exists( 'VHCC_DayBaoCao', 'dat' ) && VHCC_DayBaoCao::da_day( $ma ) ) {
			$r = VHCC_DayBaoCao::dat( $toi, $ma, false );
			if ( ! empty( $r['ok'] ) ) { $go[] = 'Quản trị báo cáo cơ sở'; }
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
		$bao_cao = array();
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
			if ( array_key_exists( VHCC_DayBaoCao::COT, $cac ) ) {
				$bao_cao[ $ma ] = (string) $cac[ VHCC_DayBaoCao::COT ];
				unset( $sach[ $ma ][ VHCC_DayBaoCao::COT ] );
			}
		}
		return array( $sach, $ghe, $chi_phi, $bao_cao );
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

	/**
	 * Đẩy/gỡ sang màn Quản trị báo cáo cơ sở, rồi kể lại thành mấy dòng báo.
	 *
	 * ⚠️ NÓI RA AI CHƯA GHÉP ĐƯỢC CƠ SỞ. Mã cơ sở bên nhân sự (`FZ_SC_VIVO_T4`) không trùng chữ
	 *    với tên cơ sở bên máy POS, nên người đẩy sang mà chưa khai bảng ghép thì đăng nhập được
	 *    nhưng KHÔNG thấy cơ sở nào — im lặng là họ tưởng hệ hỏng.
	 */
	private static function luu_bao_cao( $toi, $ds ) {
		if ( ! $ds || ! self::cot_bao_cao( $toi ) ) { return array(); }
		$kq = VHCC_DayBaoCao::luu_nhieu( $toi, $ds );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$bao = array();
		if ( ! empty( $kq['doi'] ) ) {
			$bao[] = array( 'ok' => 'Quản trị báo cáo cơ sở: đã đẩy/gỡ ' . (int) $kq['doi'] . ' người. '
				. 'Họ vào màn báo cáo bằng chính PIN chấm công.' );
		}
		foreach ( (array) $kq['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		if ( ! empty( $kq['chua_ghep'] ) ) {
			$bao[] = array( 'canh' => 'Nhưng chưa ghép mã cơ sở sang tên cơ sở bên máy POS cho: '
				. implode( ', ', array_map( 'strval', (array) $kq['chua_ghep'] ) )
				. '. Khai bảng ghép ở tab Quản trị của màn báo cáo, không thì họ đăng nhập được mà '
				. 'không thấy cơ sở nào.' );
		}
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
		/* 🔴 CHỐI CẢ LƯỢT KHI **MỌI** NHÓM ĐỀU VẮNG, KHÔNG PHẢI KHI RIÊNG BẢNG Ô VẮNG.
		   Một lượt Lưu chở BỐN nhóm độc lập: ô quyền (`o`), vai trò (`vai`), cơ sở (`cs_co`)
		   và quyền trang Nội bộ. Bắt riêng `o` rỗng là vứt sạch ba nhóm kia — mà lại vứt kèm
		   câu "Biểu mẫu không hợp lệ", nên người bấm đi kiểm trình duyệt chứ không ngờ dữ liệu
		   mình gửi vẫn nguyên vẹn. Hôm nay trang luôn vẽ cột quyền nên `o` luôn có mặt và lỗi
		   không lộ; ngày cột ấy ẩn đi với một vai nào đó thì đổi cơ sở im lặng không ăn. */
		if ( ! $sach && ! isset( $_POST['vai'] ) && ! isset( $_POST['cs_co'] )
			&& ! isset( $_POST['mbp_co'] ) && ! isset( $_POST['nb'] ) ) {
			return array( array( 'loi' => 'Biểu mẫu không hợp lệ.' ) );
		}
		list( $sach, $ghe, $chi_phi, $bao_cao ) = self::tach_ghe( $sach );
		$bao_ghe = array_merge( self::luu_ghe( $toi, $ghe ), self::luu_chi_phi( $toi, $chi_phi ),
			self::luu_bao_cao( $toi, $bao_cao ) );
		$kq = VHCC_Cong::luu_nhieu( $toi, $sach );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }

		/* 🔴 CHUYỂN CƠ SỞ CHẠY TRƯỚC LƯU QUYỀN THÌ SAI THỨ TỰ. Chuyển cơ sở RESET sạch ngoại lệ
		   của người ấy; chạy trước là nó xoá luôn mấy ô quyền vừa lưu ở ngay lượt này, mà màn
		   hình vẫn báo "đã lưu N ô". Nên: quyền trước, cơ sở sau — ai vừa bị chuyển thì quyền
		   của họ về mặc định, và đó đúng là điều anh Thắng muốn. */
		$vai = self::luu_vai( $toi );
		$cs  = self::luu_coso( $toi );
		/* 🔴 CHẠY SAU `luu_coso()`. Mảng suy ra TỪ cơ sở, nên ai vừa được chuyển cơ sở trong đúng
		   lượt này phải được xét theo cơ sở MỚI. Chạy trước là hàng ấy so với cơ sở cũ: người ta
		   chọn «theo cơ sở» mà vẫn bị ghi cứng mảng của chỗ cũ, không dòng đỏ nào. */
		$mbp = self::luu_mang_bp( $toi );
		$doi = (int) $kq['doi'];

		$bao = array();
		if ( $doi )          { $bao[] = array( 'ok' => 'Đã lưu ' . $doi . ' ô quyền vào trang.' ); }
		if ( $vai['doi'] )   { $bao[] = array( 'ok' => 'Đã đổi vai trò cho ' . $vai['doi'] . ' người.' ); }
		if ( $cs['doi'] ) {
			$bao[] = array( 'ok' => 'Đã chuyển ' . $cs['doi'] . ' người sang cơ sở khác'
				. ( $cs['go'] ? ' — quyền riêng của họ đã reset về mặc định (' . $cs['go'] . ' ô).'
					: '. Họ vốn không có quyền riêng nào nên không có gì phải reset.' ) );
		}
		if ( ! empty( $cs['doiQL'] ) ) {
			$bao[] = array( 'ok' => 'Đã đổi ô "chỉ QL" (chỉ quản lý — không chấm công) cho '
				. $cs['doiQL'] . ' người. Cơ sở đặt "chỉ QL" không còn hiện trong ô chọn lúc chấm '
				. 'và không mọc hàng trống trong bảng công — nhưng họ VẪN quản lý nhân viên ở đó, '
				. 'và mấy lượt đã chấm trước đó không mất.' );
		}
		if ( ! empty( $cs['doiChinh'] ) ) {
			$bao[] = array( 'ok' => 'Đã đổi CƠ SỞ CHÍNH cho ' . $cs['doiChinh'] . ' người — họ vẫn '
				. 'làm ở đủ những cơ sở đang tích, chỉ đổi cơ sở được chọn sẵn lúc chấm công. '
				. 'Quyền riêng KHÔNG bị reset.' );
		}
		if ( $mbp['doi'] ) {
			$bao[] = array( 'ok' => 'Đã xếp lại mảng / bộ phận cho ' . $mbp['doi'] . ' người. '
				. 'Ô để «theo cơ sở» thì họ tự đi theo mảng của cơ sở — đổi mảng cơ sở là họ đổi theo, '
				. 'khỏi sửa lại từng hồ sơ.' );
		}
		foreach ( $mbp['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
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
	 * ⚠️ ĐI QUA `VHCC_NhanSu::dat_ds_coso()` TỪNG NGƯỜI. Chốt bậc, chốt phụ trách cả cơ sở CŨ
	 *    lẫn cơ sở MỚI, bước khử tên trùng và bước RESET ngoại lệ quyền đều nằm trong hàm ấy —
	 *    ghi tắt ở đây là bỏ hết.
	 */
	private static function luu_coso( $toi ) {
		/* 🔴 DUYỆT THEO `cs_co`, KHÔNG THEO `cs`. Ô tích bỏ hết thì trình duyệt KHÔNG gửi `cs[MA]`
		   nào cả — duyệt theo `cs` là hàng ấy biến mất khỏi lượt lưu, và người ta bỏ tích xong
		   bấm Lưu thấy y nguyên, bấm lại mấy lượt rồi thôi. `cs_co[MA]` là ô ẩn luôn có mặt cho
		   MỌI hàng đang hiện, nên nó mới là danh sách "hàng nào có gửi cơ sở lên". */
		$gui = isset( $_POST['cs'] ) ? wp_unslash( $_POST['cs'] ) : array();
		$co  = isset( $_POST['cs_co'] ) ? wp_unslash( $_POST['cs_co'] ) : array();
		/* Nút tròn "chính" của từng hàng — xem `o_coso()`. Một giá trị cho mỗi mã, không phải
		   mảng: mỗi người chỉ có MỘT cơ sở chính. */
		$ch  = isset( $_POST['cs_chinh'] ) ? wp_unslash( $_POST['cs_chinh'] ) : array();
		if ( ! is_array( $ch ) ) { $ch = array(); }
		/* Ô tích "chỉ QL" — mảng, và BỎ TÍCH HẾT thì trình duyệt không gửi phần tử nào. Nên
		   danh sách "hàng nào có gửi" vẫn là `cs_co[MA]` (ô ẩn luôn có mặt), và với hàng ấy thì
		   `cs_ql[MA]` vắng nghĩa là "không cơ sở nào chỉ quản lý" — đúng ý người bỏ tích. */
		$ql  = isset( $_POST['cs_ql'] ) ? wp_unslash( $_POST['cs_ql'] ) : array();
		if ( ! is_array( $ql ) ) { $ql = array(); }
		$ra  = array( 'doi' => 0, 'doiChinh' => 0, 'doiQL' => 0, 'go' => 0, 'loi' => array() );
		if ( ! is_array( $gui ) ) { $gui = array(); }
		if ( ! is_array( $co ) || ! $co ) { return $ra; }
		foreach ( $co as $ma => $bo_qua ) {
			$ma_s = sanitize_text_field( (string) $ma );
			if ( '' === $ma_s ) { continue; }
			$v = isset( $gui[ $ma ] ) ? $gui[ $ma ] : array();
			$ds_cs = array();
			foreach ( (array) $v as $x ) { $ds_cs[] = sanitize_text_field( (string) $x ); }
			$c_ch = isset( $ch[ $ma ] ) ? sanitize_text_field( (string) $ch[ $ma ] ) : '';
			$ds_ql = array();
			foreach ( (array) ( isset( $ql[ $ma ] ) ? $ql[ $ma ] : array() ) as $x_q ) {
				$ds_ql[] = sanitize_text_field( (string) $x_q );
			}
			$r = VHCC_NhanSu::dat_ds_coso( $toi, $ma_s, $ds_cs, $c_ch, $ds_ql );
			if ( empty( $r['ok'] ) ) {
				$ra['loi'][ $r['error'] ] = $ma_s . ': ' . $r['error'];
				continue;
			}
			/* Đổi cơ sở CHÍNH: đếm riêng và KHÔNG reset quyền (xem `dat_ds_coso`) — nhưng bản
			   sao bên hệ ghế vẫn phải theo, vì ô `coso` bên ấy được ghép từ `cua_hang` trước
			   rồi mới tới `coso_phu`, tức là thứ tự vừa đổi. */
			if ( ! empty( $r['doiQL'] ) ) { $ra['doiQL']++; }
			if ( ! empty( $r['doiChinh'] ) ) {
				$ra['doiChinh']++;
				VHCC_DayGhe::dong_bo( $ma_s );
				if ( class_exists( 'VHCC_DayChiPhi' ) && method_exists( 'VHCC_DayChiPhi', 'dong_bo' ) ) {
					VHCC_DayChiPhi::dong_bo( $ma_s );
				}
				/* Và bản sao bên màn Quản trị báo cáo cơ sở — cùng lý do, cùng lúc: cửa hàng trưởng
				   vào màn ấy bằng chính PIN này, để lệch là sáng hôm sau họ không nhập được báo cáo. */
				if ( class_exists( 'VHCC_DayBaoCao' ) && method_exists( 'VHCC_DayBaoCao', 'dong_bo' ) ) {
					VHCC_DayBaoCao::dong_bo( $ma_s );
				}
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
				/* Và bản sao bên màn Quản trị báo cáo cơ sở — cùng lý do, cùng lúc: cửa hàng trưởng
				   vào màn ấy bằng chính PIN này, để lệch là sáng hôm sau họ không nhập được báo cáo. */
				if ( class_exists( 'VHCC_DayBaoCao' ) && method_exists( 'VHCC_DayBaoCao', 'dong_bo' ) ) {
					VHCC_DayBaoCao::dong_bo( $ma_s );
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
				/* Và bản sao bên màn Quản trị báo cáo cơ sở — cùng lý do, cùng lúc: cửa hàng trưởng
				   vào màn ấy bằng chính PIN này, để lệch là sáng hôm sau họ không nhập được báo cáo. */
				if ( class_exists( 'VHCC_DayBaoCao' ) && method_exists( 'VHCC_DayBaoCao', 'dong_bo' ) ) {
					VHCC_DayBaoCao::dong_bo( $ma_s );
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

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * ĐIỀU ĐỘNG CẢ CỘT — mảng kinh doanh / bộ phận.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Xét TRƯỚC chốt `mo · khoa · (trống)` bên dưới: hai cột này mang giá trị là TÊN MẢNG
		 * ("Khu vui chơi"), không phải ba từ khoá của cột quyền. Để rơi xuống đó là mọi lượt
		 * điều động ăn câu "Chỉ nhận: mo · khoa · (trống)" — một câu chối đúng luật nhưng nói về
		 * một cột khác hẳn, và người bấm không đời nào đoán ra.
		 *
		 * ⚠️ Vẫn đi qua `dat_mang_bo_phan()` từng người, không ghi thẳng một câu UPDATE cho cả
		 *    lượt: chốt quyền và danh sách trắng nằm trong hàm ấy. Nhanh hơn được vài mili giây
		 *    mà mở một cửa hậu không ai canh thì không đáng.
		 */
		if ( 'mbp_mang' === $trang || 'mbp_bp' === $trang ) {
			if ( ! VHCC_NhanSu::co_sua_ho_so( $toi ) ) {
				return array( array( 'loi' => 'Điều động mảng / bộ phận cần vai Kế toán trở lên.' ) );
			}
			$la_mang = ( 'mbp_mang' === $trang );
			/* '*' = "lấy giá trị ở ô xổ cạnh nút"; '' = trả cả cột về «theo cơ sở». */
			$gt = '';
			if ( '*' === $dat ) {
				$o_gt = $la_mang ? 'cot_mang' : 'cot_bp';
				$gt = isset( $_POST[ $o_gt ] ) ? sanitize_text_field( wp_unslash( $_POST[ $o_gt ] ) ) : '';
				if ( '' === $gt ) {
					return array( array( 'canh' => 'Chưa chọn ' . ( $la_mang ? 'mảng' : 'bộ phận' )
						. ' nào ở ô cạnh nút Áp — chưa điều động ai.' ) );
				}
			} elseif ( '' !== $dat ) {
				return array( array( 'loi' => 'Giá trị điều động không hợp lệ.' ) );
			}
			$co = isset( $_POST['mbp_co'] ) ? wp_unslash( $_POST['mbp_co'] ) : array();
			if ( ! is_array( $co ) || ! $co ) {
				return array( array( 'loi' => 'Không có người nào đang hiện để điều động.' ) );
			}
			/* Giữ nguyên TRỤC KIA của từng người. Áp cột Mảng mà ghi đè luôn Bộ phận là một lần
			   bấm xoá sạch sơ đồ tổ chức của cả trang, và không có đường lùi. */
			$m_g = isset( $_POST['mbp_mang'] ) ? wp_unslash( $_POST['mbp_mang'] ) : array();
			$b_g = isset( $_POST['mbp_bp'] ) ? wp_unslash( $_POST['mbp_bp'] ) : array();
			$doi = 0; $loi = array();
			foreach ( array_keys( $co ) as $ma_raw ) {
				$ma_c = sanitize_text_field( (string) $ma_raw );
				if ( '' === $ma_c ) { continue; }
				$m_c = $la_mang ? $gt : self::gom_mang( isset( $m_g[ $ma_raw ] ) ? $m_g[ $ma_raw ] : array() );
				$b_c = $la_mang
					? ( isset( $b_g[ $ma_raw ] ) ? sanitize_text_field( (string) $b_g[ $ma_raw ] ) : '' )
					: $gt;
				$kq_c = VHCC_NhanSu::dat_mang_bo_phan( $toi, $ma_c, $m_c, $b_c );
				if ( empty( $kq_c['ok'] ) ) { $loi[] = (string) $kq_c['error']; continue; }
				if ( ! empty( $kq_c['doi'] ) ) { $doi++; }
			}
			$bao_mb = array();
			if ( $doi ) {
				$bao_mb[] = array( 'ok' => 'Đã điều động ' . $doi . ' người sang '
					. ( '' === $gt ? '«theo cơ sở»' : ( ( $la_mang ? 'mảng' : 'bộ phận' ) . ' "' . $gt . '"' ) ) . '.' );
			}
			foreach ( $loi as $l_c ) { $bao_mb[] = array( 'loi' => $l_c ); }
			return $bao_mb ? $bao_mb : array( array( 'canh' => 'Cả ' . count( $co )
				. ' người đang hiện vốn đã như vậy — không có gì đổi.' ) );
		}

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
		if ( VHCC_DayBaoCao::COT === $trang ) {
			if ( ! self::cot_bao_cao( $toi ) ) {
				return array( array( 'loi' => 'Đẩy người sang màn Quản trị báo cáo cơ sở cần vai Admin.' ) );
			}
			$sach = self::doc_o();
			if ( ! $sach ) { return array( array( 'loi' => 'Không có người nào đang hiện để áp.' ) ); }
			$bc = array();
			foreach ( $sach as $ma_b => $x_b ) { $bc[ $ma_b ] = ( 'mo' === $dat ) ? 'mo' : ''; }
			$bao_b = self::luu_bao_cao( $toi, $bc );
			return $bao_b ? $bao_b : array( array( 'canh' => 'Cột "Quản trị báo cáo cơ sở" vốn đã '
				. 'như vậy cho ' . count( $bc ) . ' người đang hiện — không có gì đổi.' ) );
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
		/* Và bản sao bên màn Quản trị báo cáo cơ sở — cùng lý do, cùng lúc: cửa hàng trưởng
		   vào màn ấy bằng chính PIN này, để lệch là sáng hôm sau họ không nhập được báo cáo. */
		if ( class_exists( 'VHCC_DayBaoCao' ) && method_exists( 'VHCC_DayBaoCao', 'dong_bo' ) ) {
			VHCC_DayBaoCao::dong_bo( $ma );
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
			/* Dải đếm mảng / bộ phận — mỗi ô là một đường lọc, nên phải trông BẤM ĐƯỢC.
			   Xuống hàng tự do: 12 bộ phận trên một hàng không xuống dòng là cả trang trôi ngang. */
			. '.dai-mb{margin:0 0 12px;padding:10px 12px;background:#f8fafc;border:1px solid var(--vien);'
			. 'border-radius:10px}'
			. '.dai-hang{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px}'
			. '.dai-hang>b{font-size:12px;color:var(--mo);text-transform:uppercase;letter-spacing:.4px;'
			. 'min-width:132px}'
			. '.dai-hang .nut{padding:3px 9px;font-size:12.5px}'
			. '.dai-mb p.mo{margin:4px 0 0;font-size:12.5px}'
			/* Ô điều động phải đọc được HẾT chữ. `.o-q-vai` ghim max-width 170px cho ô trong
			   hàng; ô đầu cột dài hơn nên bị cắt mất đuôi, trông như chữ lỗi. */
			. 'select.o-dieu-dong{max-width:none;width:100%;margin-top:4px}'
			/* Ô Bộ phận chở NHÃN SUY RA («theo cơ sở → Khối Nhân Viên Cơ Sở»). Cắt mất đuôi là
			   che đúng phần thông tin, nên cột này nới rộng hơn ô xổ thường. */
			. 'th.tr-doc{min-width:170px}'
			. 'td select[name^="mbp_bp"]{max-width:none;width:100%;min-width:160px}'
			. '.dai-hang .nut.vai-chan{border-color:#fecaca;background:#fef2f2;color:var(--do)}'
			. '.mb-hop{display:flex;flex-direction:column;gap:1px}'
			. '.mb-o{display:flex;align-items:center;gap:5px;font-size:12px;white-space:nowrap;cursor:pointer}'
			. '.mb-o input{margin:0}'
			/* Nhãn suy ra: nhạt và nhỏ, nhưng PHẢI có — ô không tích gì mà không có nhãn thì
			   trông y như dữ liệu thiếu, và người ta đi tích tay cho cả sổ. */
			. '.mb-suy{font-size:11px;color:var(--mo);margin-top:3px;cursor:help;white-space:normal;'
			. 'max-width:200px;line-height:1.35}'
			. 'select.o-q-vai{padding:4px 6px;font-size:12.5px;border-radius:6px;max-width:170px}'
			/* Đường sang hồ sơ: nhạt và nhỏ, chỉ đậm lên khi rê chuột — mỗi hàng có một cái,
			   tô đậm sẵn là cả cột tên biến thành một rừng liên kết xanh. */
			. '.mo-hs{font-size:11px;color:var(--mo);text-decoration:none;white-space:nowrap;'
			. 'border:1px solid var(--vien);border-radius:5px;padding:1px 5px;margin-left:4px}'
			. '.mo-hs:hover{color:var(--xanh);border-color:var(--xanh)}'
			/* Đường "xoá" đỏ ngay từ lúc chưa rê chuột — nó là đường DUY NHẤT ở cột này dẫn tới
			   một việc không đảo lại được, nên không được trông giống ba đường kia. */
			/* Ô hiện PIN: nền vàng nhạt như một tờ giấy nhắc tạm — khác hẳn nền trắng của bảng,
			   để người đang mở nó nhớ rằng có một bí mật đang nằm trên màn hình mình. */
			. '.pin-o{margin:5px 0 0;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;'
			. 'padding:5px 9px;font-size:12.5px;display:inline-block}'
			. '.pin-o.pin-loi{background:#fef2f2;border-color:#fecaca;color:var(--do)}'
			. '.pin-so{font-size:17px;font-weight:700;letter-spacing:2px;color:#92400e}'
			. '.pin-nhac{color:var(--mo);font-size:11px;margin-left:6px}'
			/* Lưới ô tích cơ sở trong cột hẹp: xếp dọc, chữ nhỏ, cuộn khi quá dài. Một người
			   hiếm khi quá 3–4 cơ sở, nhưng cột này còn phải sống được ở chuỗi 26 cửa hàng. */
			. '.o-cs-tich{display:flex;flex-direction:column;gap:1px;max-height:112px;overflow:auto;'
			. 'min-width:186px}'
			. '.o-cs-tich label{display:flex;align-items:center;gap:5px;font-size:11.5px;'
			. 'white-space:nowrap;cursor:pointer}'
			. '.o-cs-tich input[disabled]+*,.o-cs-tich label:has(input[disabled]){color:var(--mo)}'
			/* Một hàng = một cơ sở: ô tích bên trái, nút tròn "chính" dạt sang phải. */
			. '.o-cs-tich .cs-hang{display:flex;align-items:center;gap:8px;justify-content:space-between}'
			. '.o-cs-tich .cs-ch{color:var(--mo);font-size:10.5px;gap:3px;flex:0 0 auto}'
			. '.o-cs-tich .cs-ch:has(input:checked){color:var(--xanh,#0369a1);font-weight:600}'
			. '.o-cs-tich .cs-ql{color:var(--mo);font-size:10.5px;gap:3px;flex:0 0 auto}'
			. '.o-cs-tich .cs-ql:has(input:checked){color:#92400e;font-weight:600}'
			. '.o-cs-tich .cs-hang label:first-child{margin-right:auto}'
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
			. 'tr.hang-sua .luoi label .mo{display:block;font-size:11px;margin:1px 0 0}'
			/* ==============================================================================
			 * CO GIÃN THEO TRANG — bảng nhân sự phải VỪA bề ngang trang
			 * ==============================================================================
			 * Anh Thắng 13/09/2026: *"Chỉnh lại co giãn theo trang"* — ảnh gửi kèm cho thấy
			 * cột "Ghế massage" bị mép phải cắt cụt, còn thanh cuộn thì kéo CẢ TRANG đi ngang.
			 *
			 * 🔴 HAI CHUYỆN KHÁC NHAU, phải chữa cả hai — chữa một cái thì cái kia vẫn còn:
			 *    (1) cả trang trôi ngang (lỗi thật, xem ngay dưới đây);
			 *    (2) bảng 11 cột rộng hơn khung (phải bóp lại cho vừa).
			 * ============================================================================== */
			/* 🔴 (1) Ô chọn ẩn của dải ba nút là `position:absolute` mà KHÔNG có ổ neo nào, nên
			   nó đo theo cả TRANG chứ không theo ô chứa — và `overflow-x` của `.cuon` chỉ cắt
			   được thứ nằm trong lòng nó, không cắt được thứ neo ra ngoài. Hậu quả: trang thừa
			   ra ~60px bề ngang, thanh cuộn dưới cùng kéo luôn cả tiêu đề lẫn dải lọc đi theo,
			   mà cột Mã NV ghim trái thì hết ghim — vì nó ghim theo khung `.cuon`, không phải
			   theo trang. Một chữ `position:relative` là ô ẩn về đúng trong lòng ô của nó. */
			. '.ba{position:relative}'
			/* (2a) Màn này là bảng 11 cột. Khung chung 1760px hợp với mọi màn khác, nhưng ở đây
			   nó bỏ phí phần bề ngang còn lại của màn 1920 rồi lại đi cắt hai cột cuối. */
			. '.bo{max-width:2200px}'
			/* (2b) Chế độ GỌN cho riêng bảng nhân sự: chữ nhỏ hơn một điểm, ô sát lại. Bóp đều
			   tay như vậy rẻ hơn nhiều so với bỏ bớt cột — mỗi cột ở đây đều có người dùng. */
			. 'table.b-ns{font-size:12.5px}'
			. 'table.b-ns th,table.b-ns td{padding:6px 7px}'
			. '.cuon-ns input,.cuon-ns select{padding:4px 6px}'
			/* Năm cột nút (ba cột quyền + Ghế + Chi phí) trước bị ghim 170px chỉ vì cột Bộ phận
			   cần chỗ. Nay ghim sàn theo đúng bề ngang dải nút, còn tên cột thì cứ xuống dòng. */
			. 'table.b-ns th.tr-doc{min-width:116px}'
			. 'table.b-ns .ba span{padding:3px 6px;font-size:11px}'
			. 'table.b-ns .cot-nut button{padding:2px 5px;font-size:10.5px}'
			/* Hai cột điều động vẫn phải đọc HẾT nhãn «theo cơ sở → …» — đó là lý do chúng tồn
			   tại. Nên bóp nhẹ hơn mấy cột nút, và có sàn riêng. */
			/* ⚠️ Phải ghi lại `max-width:none` Ở ĐÂY. Luật `select.o-q-vai{max-width:152px}`
			   ngay dưới nặng ký hơn luật `td select[name^="mbp_bp"]` ở trên, nên bỏ dòng này
			   là ô Bộ phận bị cắt cụt đuôi «theo cơ sở → …» — đúng lỗi bản 3.69.0. */
			. 'table.b-ns td select[name^="mbp_bp"]{max-width:none;min-width:146px}'
			. 'table.b-ns select.o-q-vai{max-width:152px;font-size:12px}'
			. 'table.b-ns .mb-suy{max-width:158px}'
			. 'table.b-ns .o-cs-tich{min-width:0;font-size:11px}'
			. 'table.b-ns .o-cs-tich label{font-size:11px}'
			. 'table.b-ns .o-cs-tich .cs-hang{gap:6px}'
			/* Màn hẹp (máy tính xách tay 13", máy bảng) thì KHÔNG bóp tiếp nữa — bóp nữa là
			   không đọc được. Ở đó để `.cuon` cuộn ngang như cũ; khác một chỗ: nay chỉ RIÊNG
			   bảng cuộn, tiêu đề và dải lọc đứng yên, và cột Mã NV ghim trái chạy đúng. */
			. '@media(max-width:1100px){.bo{padding-left:12px;padding-right:12px}}'
			/* ==============================================================================
			 * DẢI CHIP "VÀO ĐƯỢC ĐÂU, VÌ ĐÂU" — thay cho năm cột nút cũ (3.77.0)
			 * ============================================================================== */
			. '.chip-q-dai{display:flex;flex-wrap:wrap;gap:4px}'
			. '.chip-q{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:9px;'
			. 'font-size:11px;font-weight:600;line-height:1.5;white-space:nowrap;border:1px solid}'
			/* Xanh = vào được, xám = không. Màu KHÔNG đứng một mình: chip luôn có CHỮ tên trang,
			   nên bản in đen trắng và người mù màu vẫn đọc ra mình đang xem cột nào. */
			. '.chip-q.q-co{background:#dcfce7;border-color:#86efac;color:#15803d}'
			. '.chip-q.q-khong{background:#f1f5f9;border-color:#e2e8f0;color:#94a3b8}'
			/* Chữ nhỏ trong chip nói TẦNG nào quyết (bp · mảng · riêng). Nhạt hơn tên trang —
			   nó là chú thích, không phải nhãn. */
			. '.chip-q i{font-style:normal;font-size:9.5px;font-weight:700;opacity:.7;'
			. 'text-transform:uppercase;letter-spacing:.3px}'
			/* Đặt RIÊNG cho một người: viền đứt. Giữa 245 hàng đi theo luật chung, dòng đi riêng
			   phải nhận ra được mà không cần rê chuột — nó là thứ duy nhất ở cột này mà đổi luật
			   nhóm cũng không lay chuyển. */
			. '.chip-q.q-rieng{border-style:dashed;border-width:1.5px}'
			/* ⚠ = luật nói một đằng, sổ người dùng bên kia một nẻo. Vàng, không đỏ: đây là "còn
			   việc phải bấm", không phải "hỏng". */
			. '.chip-q.q-lech{background:#fef3c7;border-color:#fcd34d;color:#92400e}'
			/* Nút +/− của hai chip ĐẨY NGƯỜI. Nhỏ, nằm gọn trong chip, nhưng phải BẤM ĐƯỢC
			   bằng ngón tay: 16px là mức thấp nhất còn trúng trên màn cảm ứng. */
			. '.chip-q .chip-nut{margin-left:4px;min-width:16px;height:16px;line-height:1;padding:0 3px;'
			. 'border:1px solid currentColor;border-radius:5px;background:#fff;color:inherit;'
			. 'font-size:12px;font-weight:700;cursor:pointer;opacity:.75}'
			. '.chip-q .chip-nut:hover{opacity:1}'
			/* Khối "đặt riêng" trong hàng sửa: mỗi trang một cụm nhãn + dải nút. */
			. '.q-mot{display:inline-flex;flex-direction:column;gap:3px}'
			. '.q-ten{font-size:11.5px;color:var(--mo);font-weight:600}'
			/* Số người của mỗi nhóm trong bảng luật — để biết đang khai cho bao nhiêu người. */
			. '.sl-nho{display:inline-block;min-width:18px;text-align:center;border-radius:9px;'
			. 'background:#e2e8f0;color:#475569;font-size:11px;padding:0 5px;margin-left:4px}'
			/* Bảng luật nhóm: cột nút bó sát bề ngang dải nút, phần thừa dồn cho cột TÊN NHÓM.
			   Không ghim thì `width:100%` chia đều, và mỗi dải nút lọt thỏm giữa một ô rộng gấp
			   ba nó — mắt phải chạy cả gang tay mới nối được tên phòng với ô mình vừa bấm. */
			. 'table.b-nhom td.o-q-td,table.b-nhom th.tr-doc{width:1%;white-space:nowrap}'
			. 'table.b-nhom td.o-q-td{text-align:left}'
			/* Mảng đang ẩn: mờ đi nhưng VẪN ĐỌC ĐƯỢC — nó còn sửa được, và còn phải thấy nó đang ẩn. */
			. 'tr.mang-an>td{background:#f8fafc;opacity:.75}'
			. '.chip-an{display:inline-block;margin-left:5px;padding:0 6px;border-radius:9px;'
			. 'background:#e2e8f0;color:#475569;font-size:10.5px;font-weight:700;vertical-align:middle}';
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
		$mang = isset( $_GET['nmang'] ) ? sanitize_text_field( wp_unslash( $_GET['nmang'] ) ) : '';
		$nbp  = isset( $_GET['nbp'] )  ? sanitize_text_field( wp_unslash( $_GET['nbp'] ) ) : '';
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
		self::dai_tab_ns( $toi );
		self::canh_nguon();
		self::canh_ghe( $toi );

		/* ═══ TAB LỆNH ═══════════════════════════════════════════════════════════════════
		 * Anh Thắng 15/09/2026: *"Tạo tab lệnh để đẩy dữ liệu nv sang 1 trang chi phí văn
		 * phòng trước"*.
		 *
		 * 🔴 MÀN RIÊNG, KHÔNG PHẢI MỘT CỘT NỮA TRÊN BẢNG. Bảng "Ai vào được trang nào" đã năm
		 *    cột và phải cuộn ngang trên máy tính; thêm một cột cho mỗi bản chi phí (VP · MTD ·
		 *    HN) là bảy cột, tức bảng thành thứ không đọc nổi. Và việc ở đây khác hẳn: đẩy
		 *    người sang một app là việc làm HÀNG LOẠT theo phòng ban, một đợt rồi thôi — không
		 *    phải việc chỉnh lắt nhắt từng người như mấy cột quyền kia.
		 *
		 * ⚠️ VẼ TRƯỚC KHI `return`, và đứng SAU mấy dải cảnh báo: nếu cổng PIN đang đọc sai kho
		 *    thì đẩy xong người ta vẫn không đăng nhập được — phải thấy dải ấy trước đã.
		 */
		if ( isset( self::ds_dich_day()[ self::man_ns() ] ) ) {
			self::the_tab_day( $toi, self::man_ns() );
			self::dong_trang();
			return;
		}

		if ( ! $ds_trang ) {
			echo '<div class="the"><div class="bao loi">Không dò thấy trang nào trên site này. '
				. 'Các plugin trang (Cổng, Nội bộ, Chi phí, Ghế, Hợp đồng) có thể chưa được kích hoạt.'
				. '</div></div>';
			self::dong_trang();
			return;
		}

		/* 🔴 KHỐI "GHÉP HAI MÃ VỀ MỘT NGƯỜI" (danh sách 44 cặp + form gõ tay + gợi ý mã máy) ĐÃ
		   BỎ HẲN — anh Thắng 29/08/2026, sau ba lượt chỉnh chỗ đặt/khung vẫn không đúng ý ("cùng 1
		   nv có khác gì đâu", "chả khác gì, cùng mã thì ghép lại thôi"), rồi chốt "xóa luôn" khi
		   được hỏi lại rõ mất những gì (đã xác nhận: mất luôn cả xem/Bỏ ghép 44 cặp cũ, form gõ
		   tay cho cặp hệ không tự dò ra, và gợi ý mã máy). Đường ghép DUY NHẤT còn lại là nút
		   "Ghép với hồ sơ kia" ngay tại dòng "một người hai hồ sơ?" trong the_bang() (thêm ở bản
		   trước) — chỉ dùng được khi hệ TỰ DÒ ra trùng tên+cùng cơ sở, không có đường gõ tay nữa.
		   `khai_ma_song_song()`/`viec_ghep_ma()`/`viec_bo_ghep_ma()`/`viec_don_ma()` GIỮ NGUYÊN
		   ở tầng máy chủ (không xoá) — cùng cách `go_ngoai_le`/`ngoai_le_phang()` được giữ khi bỏ
		   `the_ngoai_le()` trước đó: bỏ MÀN HÌNH quản lý qua UI, không bỏ NĂNG LỰC ở lõi. */
		self::the_gop( $toi );
		self::the_cho_duyet_may( $toi );
		echo '<div class="the">';
		self::the_bang( $toi, $ds_trang, $cs, $q, $vai, $p, $mang, $nbp );
		echo '</div>';
		/* Ngay DƯỚI bảng: cột "Quyền vào trang" trong bảng chỉ ĐỌC, nên chỗ khai phải là thứ
		   tiếp theo mắt chạm tới — không bắt người ta đi tìm ở cuối trang. */
		self::the_quyen_nhom( $toi );
		self::the_dong_bo( $toi );
		self::the_quyen_noi_bo( $toi );
		self::canh_vai_la( $toi );
		self::the_vai( $toi );
		/* Ngay SAU Bảng vai trò: khai vai cho bộ phận chỉ có nghĩa khi vai đã tồn tại, nên hai
		   khối phải đứng cạnh nhau và đúng thứ tự đọc. */
		/* Sơ đồ tổ chức đứng TRƯỚC hai khối khai theo bộ phận: sửa tên phòng xong mới khai vai
		   và quyền cho nó — đúng thứ tự người ta làm. */
		self::the_bo_chi_phi( $toi );
		self::the_so_do( $toi );
		self::the_vai_bp( $toi );
		self::the_gop_tay( $toi );
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
			. 'ai đăng bài, ai lập nhóm, ai dọn</summary>';
		echo '<p class="mo">Trang Nội bộ là <b>trang chung</b> của công ty: <b>ai có PIN là vào '
			. 'được</b>, không khoá theo vai. Mấy ô dưới đây chỉ quyết định ai được <i>làm gì</i> '
			. 'khi đã vào. Cần chặn riêng một người thì khoá đúng người đó ở bảng nhân sự trên '
			. 'trang này.</p>';
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
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * TAB LỆNH — ĐẨY NHÂN SỰ SANG MỘT BẢN CHI PHÍ
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 15/09/2026: *"Tạo tab lệnh để đẩy dữ liệu nv sang 1 trang chi phí văn phòng
	 * trước"*. Bản Văn phòng làm trước; MTD và HN đi sau theo đúng khuôn này.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Màn đang mở trên trang này. '' = trang tổng như cũ. */
	private static function man_ns() {
		return isset( $_GET['nman'] ) ? sanitize_key( wp_unslash( $_GET['nman'] ) ) : '';
	}

	/**
	 * CÁC BẢN CHI PHÍ ĐẨY ĐƯỢC SANG — khai MỘT CHỖ.
	 *
	 * Thêm một bản (Hà Nội…) là thêm ĐÚNG MỘT DÒNG ở đây, cộng một lớp sáu hàm kiểu
	 * `VHCC_DayChiPhiVP`. Gõ tay tên bản ở dải tab, ở bộ định tuyến, ở hàm lưu… là ba chỗ phải
	 * nhớ sửa, và chỗ quên sẽ là chỗ không ai bấm tới nên không ai phát hiện.
	 */
	private static function ds_dich_day() {
		return array(
			'day_vp'  => 'VHCC_DayChiPhiVP',
			'day_mtd' => 'VHCC_DayChiPhiMTD',
		);
	}

	/** Lớp lo bản đích của một tab. '' nếu tab ấy không có thật. */
	private static function lop_dich( $man ) {
		$ds = self::ds_dich_day();
		if ( ! isset( $ds[ $man ] ) ) { return ''; }
		$lop = $ds[ $man ];
		/* ⚠️ Gác CÙNG HÀM với chỗ dùng — lớp có thể chưa nạp nếu ai đó gỡ bớt tệp. */
		return ( class_exists( $lop ) && method_exists( $lop, 'co_he' ) ) ? $lop : '';
	}

	/**
	 * Dải tab ở đầu trang. Vẽ ở CẢ HAI màn, kẻo vào tab lệnh rồi không có đường về.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 TAB VẪN HIỆN KHI BẢN ĐÍCH CHƯA CÀI — VÀ ĐÂY LÀ MỘT LỖI EM TỰ GÂY RA RỒI PHẢI SỬA.
	 *
	 *    Bản trước em giấu hẳn tab khi `co_he()` trả false, lý do nghe rất hợp: "một tab bấm
	 *    vào chỉ để đọc câu chưa cài là thứ người ta bấm lại vào tuần sau". Nhưng anh Thắng cài
	 *    xong bản chấm công mới, mở trang ra, và nhắn *"Chưa thấy tab đẩy"* — vì bản Chi phí
	 *    Văn phòng chưa được cài. Màn KHÔNG NÓI GÌ CẢ, nên không có cách nào biết là thiếu cái
	 *    gì: y hệt tính năng chưa làm, hay làm hỏng.
	 *
	 *    Giấu một thứ vì nó chưa dùng được chỉ đúng khi người dùng KHÔNG ĐANG TÌM nó. Ở đây họ
	 *    đang tìm. Nên nay tab luôn có mặt cho người đủ vai, và tab nào chưa cài được thì tự
	 *    nói ra ngay trên nhãn — mờ đi, kèm chữ "chưa cài".
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 */
	private static function dai_tab_ns( $toi ) {
		if ( ! self::co_quyen_day( $toi ) ) { return; }
		$man = self::man_ns();
		echo '<div class="the" style="padding:8px 10px;margin-bottom:14px"><div class="hang" style="gap:8px">';
		$url = self::url();
		echo '<a class="nut' . ( '' === $man ? ' chinh' : '' ) . '" href="' . esc_url( $url )
			. '">Tổng quan</a>';
		foreach ( self::ds_dich_day() as $k => $lop ) {
			$co  = ( '' !== self::lop_dich( $k ) ) && call_user_func( array( $lop, 'co_he' ) );
			$ten = '⇪ ' . ( '' !== self::lop_dich( $k )
				? call_user_func( array( $lop, 'ten_he' ) ) : $k );
			$u   = add_query_arg( array( 'nman' => $k ), self::url() );
			echo '<a class="nut' . ( $man === $k ? ' chinh' : '' ) . '" href="' . esc_url( $u ) . '"'
				. ( $co ? '' : ' style="opacity:.55"' ) . '>' . esc_html( $ten )
				. ( $co ? '' : ' <span class="chua">· chưa cài</span>' ) . '</a>';
		}
		echo '</div></div>';
	}

	/**
	 * Ai được đẩy người sang một app chi phí.
	 *
	 * 🔴 CÙNG BẬC VỚI ĐẨY SANG BẢN KHU VUI CHƠI, và vì cùng một lý do: màn chi phí có ngăn
	 *    TIỀN (duyệt chi, quyết toán), còn PIN đẩy sang là PIN chấm công dùng chung. Đẩy nhầm
	 *    một người là trao cho họ chìa khoá mà chính họ cũng không biết mình đang cầm.
	 *
	 * ⚠️ HỎI QUYỀN THÔI, KHÔNG HỎI "ĐÃ CÀI CHƯA". Hai câu ấy tách hẳn nhau từ 15/09/2026 —
	 *    xem khối dài ở `dai_tab_ns()`.
	 */
	private static function co_quyen_day( $toi ) {
		return class_exists( 'VHCC_DayChiPhi' )
			&& VHCC_Vai::duoc( $toi, VHCC_DayChiPhi::QUYEN );
	}

	/**
	 * Thân tab lệnh — MỘT thân cho mọi bản đích.
	 *
	 * ⚠️ CHƯA CÀI BẢN ĐÍCH THÌ NÓI RA, KHÔNG IM. Anh Thắng 15/09/2026: *"Chưa thấy tab đẩy"* —
	 *    vì bản ấy chưa cài mà màn không nói gì. Xem khối dài ở `dai_tab_ns()`.
	 */
	private static function the_tab_day( $toi, $man ) {
		$lop = self::lop_dich( $man );
		if ( '' === $lop ) {
			echo '<div class="the"><div class="bao loi">Không có bản đích nào tên "'
				. esc_html( $man ) . '".</div></div>';
			return;
		}
		$ten_he = call_user_func( array( $lop, 'ten_he' ) );
		if ( ! self::co_quyen_day( $toi ) ) {
			echo '<div class="the"><div class="bao loi">Đẩy người sang hệ ' . esc_html( $ten_he )
				. ' cần vai Admin — màn ấy có ngăn tiền.</div></div>';
			return;
		}
		if ( ! call_user_func( array( $lop, 'co_he' ) ) ) {
			echo '<div class="the"><div class="bao canh"><b>Chưa cài plugin ' . esc_html( $ten_he )
				. ' trên site này</b> (hoặc bản bên ấy quá cũ), nên chưa đẩy được ai. '
				. 'Cài plugin ấy rồi kích hoạt, tải lại trang này là tab mở.</div></div>';
			return;
		}
		$bp  = isset( $_GET['vbp'] )  ? sanitize_text_field( wp_unslash( $_GET['vbp'] ) ) : '';
		$cs  = isset( $_GET['vcs'] )  ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['vcs'] ) ) : '';
		$q   = isset( $_GET['vq'] )   ? sanitize_text_field( wp_unslash( $_GET['vq'] ) ) : '';
		$chi = isset( $_GET['vchi'] ) ? sanitize_key( wp_unslash( $_GET['vchi'] ) ) : '';

		echo '<div class="tieu-man"><h1>Đẩy nhân sự sang ' . esc_html( $ten_he ) . '</h1>'
			. '<p class="mo">Người được đẩy có mặt trong sổ <b>Người dùng &amp; Phân quyền</b> của '
			. 'bản ấy, và đăng nhập bằng <b>chính PIN chấm công</b> của họ.</p></div>';

		/* 🔴 NÓI TRƯỚC NHỮNG GÌ LƯỢT ĐẨY GIỮ NGUYÊN. Sổ bên ấy có người thật đang dùng, và ô
		   TK Có · Mã đối tượng · Đơn vị là bảng khai của KẾ TOÁN. Không nói ra thì người bấm
		   không biết mình đang đụng vào sổ của ai. */
		echo '<div class="the"><div class="bao canh">'
			. '<b>Lượt đẩy chỉ sửa bốn ô</b> của đúng người được chọn: Tên · PIN · Vai trò · Cơ sở '
			. '(kèm Bộ phận và Mã NV). Những ô kế toán bên ấy tự khai — TK Có, Mã đối tượng, Đơn vị '
			. '— <b>giữ nguyên</b>. Hàng của người khác không bị đụng tới.'
			. '</div></div>';

		$ds = VHCC_NhanSu::ds_nhan_vien( $toi, $cs, $q );

		/* ── Bộ lọc ─────────────────────────────────────────────────────────────────── */
		echo '<div class="the"><form method="get" class="hang" style="gap:8px;flex-wrap:wrap">';
		echo '<input type="hidden" name="nman" value="' . esc_attr( $man ) . '">';
		echo '<select name="vbp"><option value="">— Mọi bộ phận —</option>';
		foreach ( VHCC_NhanSu::ds_bo_phan() as $b ) {
			echo '<option value="' . esc_attr( $b ) . '"' . selected( $bp, $b, false ) . '>'
				. esc_html( $b ) . '</option>';
		}
		echo '</select>';
		echo '<input type="text" name="vq" value="' . esc_attr( $q ) . '" placeholder="Tìm tên / mã NV">';
		echo '<select name="vchi">'
			. '<option value=""' . selected( $chi, '', false ) . '>Tất cả</option>'
			. '<option value="chua"' . selected( $chi, 'chua', false ) . '>Chưa đẩy</option>'
			. '<option value="roi"' . selected( $chi, 'roi', false ) . '>Đã đẩy</option>'
			. '</select>';
		echo '<button class="nut">Lọc</button>';
		echo '<a class="nut" href="' . esc_url( add_query_arg( array( 'nman' => $man ), self::url() ) )
			. '">Xoá lọc</a>';
		echo '</form></div>';

		/* ── Danh sách ──────────────────────────────────────────────────────────────── */
		$loc = array();
		foreach ( (array) $ds as $r ) {
			$r  = (array) $r;
			$ma = trim( (string) ( isset( $r['ma_nv'] ) ? $r['ma_nv'] : '' ) );
			if ( '' === $ma ) { continue; }
			if ( '' !== $bp && trim( (string) ( isset( $r['bo_phan'] ) ? $r['bo_phan'] : '' ) ) !== $bp ) { continue; }
			$da = call_user_func( array( $lop, 'da_day' ), $ma );
			if ( 'chua' === $chi && $da ) { continue; }
			if ( 'roi' === $chi && ! $da ) { continue; }
			$loc[] = array( 'r' => $r, 'ma' => $ma, 'da' => $da );
		}

		echo '<div class="the">';
		if ( ! $loc ) {
			echo '<div class="bao">Không có ai khớp bộ lọc.</div></div>';
			return;
		}
		echo '<form method="post">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo '<input type="hidden" name="nman" value="' . esc_attr( $man ) . '">';
		echo '<div class="cuon"><table><thead><tr>'
			. '<th style="width:34px"></th><th>Mã NV</th><th>Họ tên</th><th>Bộ phận</th>'
			. '<th>Cơ sở</th><th>PIN</th><th>Bên kia</th></tr></thead><tbody>';
		$thieu_pin = 0;
		foreach ( $loc as $x ) {
			$r   = $x['r'];
			$pin = trim( (string) ( isset( $r['pin_dang_nhap'] ) ? $r['pin_dang_nhap'] : '' ) );
			/* Sổ cũ nạp từ Google Sheets có hàng ra "1234.0" — cùng phép rửa với `ho_so_day()`,
			   không thì màn này báo "chưa có PIN" cho người thật ra có. */
			if ( preg_match( '/^(\d+)\.0*$/', $pin, $m_p ) ) { $pin = $m_p[1]; }
			$co_pin = (bool) preg_match( '/^\d{4,8}$/', $pin );
			if ( ! $co_pin ) { $thieu_pin++; }
			echo '<tr>';
			/* 🔴 KHÔNG CÓ PIN THÌ KHÔNG CHO TÍCH. Lõi sẽ chối lượt ấy và trả về một câu lỗi,
			   nhưng để người ta tích được rồi mới báo là bắt họ bấm một lần vô ích — và trong
			   một danh sách trăm dòng thì mấy câu lỗi ấy trôi mất. */
			echo '<td>' . ( $co_pin
				? '<input type="checkbox" name="vpma[]" value="' . esc_attr( $x['ma'] ) . '">'
				: '' ) . '</td>';
			echo '<td>' . esc_html( $x['ma'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( isset( $r['ho_ten'] ) ? $r['ho_ten'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( isset( $r['bo_phan'] ) ? $r['bo_phan'] : '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( isset( $r['cua_hang'] ) ? $r['cua_hang'] : '' ) ) . '</td>';
			/* ⚠️ KHÔNG IN PIN RA MÀN — trang này chạy ngoài internet. Chỉ nói CÓ hay KHÔNG. */
			echo '<td>' . ( $co_pin ? '<span class="co">có</span>'
				: '<span class="chua">chưa có</span>' ) . '</td>';
			echo '<td>' . ( $x['da'] ? '<span class="co">đã đẩy</span>' : '<span class="mo">—</span>' )
				. '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<div class="hang" style="gap:8px;margin-top:10px">';
		echo '<button class="chinh" name="vp_day" value="1">⇪ Đẩy người đã tích</button>';
		echo '<button class="nut" name="vp_go" value="1">Gỡ người đã tích</button>';
		echo '<span class="mo">' . count( $loc ) . ' người đang hiện'
			. ( $thieu_pin ? ' · <b>' . (int) $thieu_pin . '</b> người chưa có PIN nên không tích được' : '' )
			. '</span>';
		echo '</div></form></div>';

		if ( $thieu_pin ) {
			echo '<div class="the"><div class="bao canh">Có <b>' . (int) $thieu_pin . '</b> người '
				. 'chưa có PIN chấm công (4–8 số) nên chưa đẩy được. Cấp PIN ở màn '
				. '<b>Hồ sơ &amp; tài khoản</b> rồi quay lại đây.</div></div>';
		}
	}

	/**
	 * Đẩy / gỡ hàng loạt theo danh sách vừa tích — cho BẢN ĐÍCH CỦA TAB ĐANG MỞ.
	 *
	 * 🔴 ĐÍCH LẤY TỪ Ô `nman` CỦA CHÍNH BIỂU MẪU, không đoán. Biểu mẫu trong tab nào thì mang
	 *    theo tên tab ấy; đọc chỗ khác (URL hiện tại, tab mở lần trước) là có ngày bấm ở tab
	 *    Máy tự động mà người rơi sang sổ Văn phòng — sai sổ thì không có gì báo, và người ấy
	 *    lặng lẽ có chìa khoá vào một màn có ngăn tiền không ai định.
	 */
	private static function viec_day_vp( $toi ) {
		$man = isset( $_POST['nman'] ) ? sanitize_key( wp_unslash( $_POST['nman'] ) ) : '';
		$lop = self::lop_dich( $man );
		if ( '' === $lop || ! method_exists( $lop, 'luu_nhieu' ) || ! method_exists( $lop, 'ten_he' ) ) {
			return array( array( 'loi' => 'Không rõ đẩy sang bản chi phí nào — tải lại trang rồi làm lại.' ) );
		}
		$ten_he = call_user_func( array( $lop, 'ten_he' ) );
		if ( ! call_user_func( array( $lop, 'co_he' ) ) ) {
			return array( array( 'loi' => 'Chưa cài plugin ' . $ten_he . ' trên site này.' ) );
		}
		$day = isset( $_POST['vp_day'] );
		$ma  = isset( $_POST['vpma'] ) ? wp_unslash( $_POST['vpma'] ) : array();
		if ( ! is_array( $ma ) || ! $ma ) {
			return array( array( 'loi' => 'Chưa tích ai cả.' ) );
		}
		$bang = array();
		foreach ( $ma as $m ) {
			$m = sanitize_text_field( (string) $m );
			if ( '' !== $m ) { $bang[ $m ] = $day ? 'mo' : ''; }
		}
		$kq = call_user_func( array( $lop, 'luu_nhieu' ), $toi, $bang );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$bao = array();
		$bao[] = array( 'ok' => $ten_he . ': đã ' . ( $day ? 'đẩy' : 'gỡ' ) . ' '
			. (int) $kq['doi'] . ' người'
			. ( $day ? '. Họ đăng nhập trang ấy bằng chính PIN chấm công.' : '.' ) );
		/* 🔴 PHẢI KỂ TÊN NGƯỜI BỊ XOÁ PIN RA. Im lặng là sáng hôm sau có người gõ PIN không vào
		   được và không ai nối được hai chuyện với nhau. */
		if ( ! empty( $kq['mat_pin'] ) ) {
			$bao[] = array( 'canh' => 'Bên ' . $ten_he . ' có người trùng PIN nên đã bị XOÁ PIN (hàng vẫn còn, '
				. 'chỉ mất đường đăng nhập): ' . implode( ', ', (array) $kq['mat_pin'] )
				. '. Cấp PIN mới cho họ bên ấy là dùng lại được.' );
		}
		foreach ( (array) $kq['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		return $bao;
	}

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
			. '<b>bên trong</b> app chi phí thì vẫn phải làm ở chính app ấy, màn Cấu hình → Người '
			. 'dùng. <span class="mo">Ở đây chỉ khai được <b>ai có mặt trong sổ ấy</b> — cột '
			. '<b>Vận hành chi phí</b> của bảng luật nhóm.</span></li>';
		/* 🔴 3.77.0 — NÓI THẲNG RANH GIỚI CỦA LUẬT NHÓM. Hai cột đẩy người trông y hệt ba cột
		   trang trong cùng một bảng, nên người khai dễ tin rằng tích xong là xong. Không phải:
		   bên kia là sổ người dùng riêng, phải có một lượt bấm tạo tài khoản thật. */
		echo '<li><b>Luật nhóm cho hai cột đẩy người</b> (Ghế massage · Vận hành chi phí) chỉ là '
			. '<b>lời khai</b> — nó nói ai <i>nên</i> có tài khoản. Tài khoản thật vẫn phải bấm '
			. 'tạo, ở dải chênh lệch ngay dưới bảng luật. Hai hệ ấy đều có ngăn tiền, nên không '
			. 'tự tạo tài khoản cho ai mà không có một lượt bấm để quy trách nhiệm.</li>';
		echo '</ul>';
		echo '</details></div>';
	}

	/**
	 * "CHỜ ADMIN DUYỆT" — lệnh ghi tên xuống máy do Cửa hàng trưởng đặt qua form "Thêm nhanh",
	 * CHƯA xuống máy cho tới khi có người duyệt ở đây.
	 *
	 * Anh Thắng 29/08/2026: *"trước khi đẩy xuống máy, nó sẽ gửi qua admin duyệt 1 lệnh để check
	 * đạt yêu cầu chưa trước khi đẩy"*. Trước bản này, `VHCC_NhanSu::them_nv_cua_hang()` (đường
	 * "Cửa hàng trưởng thêm nhanh") đẩy THẲNG xuống máy ngay khi tạo hồ sơ — Admin không có cơ
	 * hội soi trước khi một khuôn mặt lạ được ghi vào đầu đọc.
	 *
	 * 🔴 MỖI HÀNG MỘT `<form>` RIÊNG, KHÔNG PHẢI TÊN NÚT RIÊNG. Khối này đứng NGOÀI `<form>` của
	 *    `the_bang()` (gọi TRƯỚC nó ở `render()`), nên không đụng luật "không lồng form" của bảng
	 *    kia — ở đây bấm Duyệt/Từ chối hàng nào chỉ gửi đúng hàng đó vì mỗi hàng có form của
	 *    riêng nó. Cùng lối khối "đơn xin trễ" ở `VHCC_Web` (`name="viec"` bình thường).
	 *
	 * ⚠️ LỌC THEO QUYỀN CƠ SỞ CỦA NGƯỜI XEM, KHÔNG PHẢI CỦA NGƯỜI ĐẶT. Kế toán một cơ sở chỉ nên
	 *    thấy (và duyệt được) lệnh của cơ sở mình; Quản lý/Admin (`co_quan_tri_nv`) thấy hết.
	 */
	private static function the_cho_duyet_may( $toi ) {
		if ( ! class_exists( 'VHCC_May' ) || ! method_exists( 'VHCC_May', 'ds_cho_duyet' ) ) { return; }
		$cua_toi = array();
		foreach ( VHCC_May::ds_cho_duyet() as $r ) {
			if ( VHCC_NhanSu::co_quyen_coso( $toi, (string) $r['cua_hang'] ) ) { $cua_toi[] = $r; }
		}
		if ( ! $cua_toi ) { return; }
		echo '<div class="the"><h2>⏳ Chờ duyệt trước khi xuống máy chấm công</h2>';
		echo '<p class="mo">Lệnh do <b>Cửa hàng trưởng</b> đặt qua form "Thêm người mới vào cửa '
			. 'hàng" — máy <b>CHƯA nhận được gì</b> cho tới khi duyệt ở đây.</p>';
		echo '<div class="cuon"><table class="cc"><thead><tr><th>Mã NV</th><th>Họ tên</th>'
			. '<th>Cơ sở</th><th>Việc</th><th>Ai đặt</th><th>Lúc đặt</th><th>Duyệt</th></tr></thead><tbody>';
		$viec_ten = array( 'add' => 'Thêm mới', 'edit' => 'Sửa lại', 'delete' => 'Gỡ khỏi máy' );
		foreach ( $cua_toi as $r ) {
			echo '<tr><td><b>' . esc_html( (string) $r['ma_nv'] ) . '</b></td>';
			echo '<td>' . esc_html( (string) $r['ho_ten'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['cua_hang'] ) . '</td>';
			echo '<td>' . esc_html( isset( $viec_ten[ $r['action'] ] ) ? $viec_ten[ $r['action'] ] : (string) $r['action'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['nguoi_dat'] ) . '</td>';
			echo '<td class="mo">' . esc_html( (string) $r['tao_luc'] ) . '</td>';
			/* Mỗi hàng một biểu mẫu RIÊNG — hai nút cùng một hàng, mỗi nút mang việc của nó. Gom
			   cả bảng vào một form thì bấm Duyệt ở hàng ba lại gửi cả chín hàng. */
			echo '<td><form method="post" class="hang" style="gap:6px;margin:0">'
				. '<input type="hidden" name="op_id" value="' . esc_attr( (string) $r['op_id'] ) . '">'
				. '<button class="chinh" name="viec" value="duyet_may">Duyệt</button>'
				. '<button class="nut-do" name="viec" value="tu_choi_may">Từ chối</button>'
				. '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/* ------------------------------------------------------------------ bảng người × trang */

	private static function the_bang( $toi, $ds_trang, $cs, $q, $vai, $p, $mang = '', $nbp = '' ) {
		$dang_sua = isset( $_GET['sua_o'] ) ? sanitize_text_field( wp_unslash( $_GET['sua_o'] ) ) : '';
		$dang_pin = isset( $_GET['pin_o'] ) ? sanitize_text_field( wp_unslash( $_GET['pin_o'] ) ) : '';
		$dang_xoa = isset( $_GET['xoa_o'] ) ? sanitize_text_field( wp_unslash( $_GET['xoa_o'] ) ) : '';
		$nguoi = VHCC_NhanSu::ds_nhan_vien( $toi, $cs, $q );
		/* Dải đếm vai dựng TRƯỚC khi lọc — bấm vào một ô xong mà dải chỉ còn mỗi ô ấy thì không
		   còn đường bấm sang ô khác, phải Bỏ lọc rồi làm lại. Cùng lối với dải mảng/bộ phận. */
		$dem_v = VHCC_NhanSu::dem_vai( $nguoi );
		if ( '' !== $vai ) {
			/* 🔴 NHẬN CẢ HAI KIỂU GIÁ TRỊ.
			   Ô xổ trên thanh lọc gửi MÃ BẬC ('NHAN_VIEN'…) — năm bậc, gộp mọi vai cùng bậc.
			   Dải đếm bên dưới gửi TÊN VAI THẬT ('Kế Toán MTD') — vì nó đếm theo chuỗi trong sổ.
			   Chỉ nhận một kiểu thì cái kia bấm vào ra bảng rỗng, mà bảng rỗng trông y như
			   "không có ai như vậy" chứ không giống một bộ lọc hiểu nhầm. */
			$la_ma = isset( VHCC_Vai::BAC[ $vai ] );
			$k_vai = VHCC_Vai::khoa_ten( $vai );
			$loc = array();
			foreach ( $nguoi as $r ) {
				$t_r = trim( (string) ( isset( $r['vai_tro'] ) ? $r['vai_tro'] : '' ) );
				$khop = $la_ma
					? ( VHCC_Vai::ma( $t_r ) === $vai )
					: ( '— chưa khai vai —' === $vai ? '' === $t_r : VHCC_Vai::khoa_ten( $t_r ) === $k_vai );
				if ( $khop ) { $loc[] = $r; }
			}
			$nguoi = $loc;
		}
		/* Dải đếm dựng TRƯỚC khi lọc mảng/bộ phận — bấm vào "Phòng Kỹ Thuật 4" xong mà dải chỉ
		   còn mỗi Phòng Kỹ Thuật thì không còn đường bấm sang ô khác, phải Bỏ lọc rồi làm lại. */
		$dem_mb = VHCC_NhanSu::dem_mang_bo_phan( $nguoi );
		if ( '' !== $mang || '' !== $nbp ) {
			$loc_mb = array();
			foreach ( $nguoi as $r ) {
				$x = VHCC_NhanSu::mang_bo_phan_cua( $r );
				/* '(chưa xếp)' là một lựa chọn THẬT của ô lọc, không phải ô trống. Không có nó thì
				   không có đường nào tìm ra người chưa ai xếp — mà đó đúng là danh sách việc. */
				/* 🔴 KHỚP THEO DANH SÁCH, KHÔNG THEO CHUỖI GHÉP. Người làm hai mảng mang chuỗi
				   "Khu vui chơi + Máy tự động"; so bằng chuỗi ấy thì họ không khớp ô lọc nào và
				   BIẾN MẤT khỏi mọi bộ lọc — mà biến mất thì không ai thấy để mà thắc mắc. */
				$co_m = ! empty( $x['dsMang'] ) ? $x['dsMang'] : array( '(chưa xếp)' );
				$kb   = ( '' !== $x['boPhan'] ) ? $x['boPhan'] : '(chưa xếp)';
				if ( '' !== $mang && ! in_array( $mang, $co_m, true ) ) { continue; }
				if ( '' !== $nbp && $kb !== $nbp ) { continue; }
				$loc_mb[] = $r;
			}
			$nguoi = $loc_mb;
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

		/* 🔴 KHÔNG tự mở `<div class="the">` ở đây — khung ngoài (mở/đóng) do NƠI GỌI (render())
		   lo. Trước 29/08/2026 khung này còn bọc CHUNG the_bang() với the_ghep_ma() ("Ghép hai
		   mã về một người") sau nhiều lượt chỉnh chỗ đặt/khung; the_ghep_ma() đã BỎ HẲN sau đó
		   (anh Thắng: "xóa luôn"), nên khung giờ chỉ còn bọc mỗi the_bang() — vẫn giữ cách tách
		   mở/đóng khung ra khỏi hàm này, phòng khi có khối khác cần ghép chung sau này. */
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

		/* 🔴 09/09/2026 — DẢI BÁO "CÓ SẴN ẢNH CHỜ LẤY". Anh Thắng: *"chỗ hồ sơ này, nếu nv có
		   ảnh hợp lệ chờ lưu hoặc đẩy vào máy thì đưa một thông báo nhỏ và link dẫn sang để
		   đẩy"*. Khối lấy ảnh nằm ở màn Bảng công; người đang đứng ở trang này không có gì nói
		   cho biết bên kia có sẵn ảnh dùng được.
		   ⚠️ CHỈ ĐẾM CƠ SỞ NGƯỜI NÀY ĐƯỢC XEM. Dải báo mà kê tên cơ sở ngoài phạm vi thì nó vừa
		      là một chỗ rò tên cơ sở, vừa là mấy đường dẫn bấm vào chỉ nhận câu chối.
		   ⚠️ Gác `class_exists`/`method_exists` cùng hàm với lời gọi (luật `kiem-goi-cheo.php`):
		      gỡ lớp nhận diện khuôn mặt ra thì dải này tự biến mất, không nổ. */
		if ( class_exists( 'VHCC_Mat' ) && method_exists( 'VHCC_Mat', 'cho_lay_anh_theo_coso' )
			&& class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
			$cho_anh = array();
			foreach ( (array) VHCC_Mat::cho_lay_anh_theo_coso() as $k_cs => $so_ng ) {
				if ( VHCC_NhanSu::co_quyen_coso( $toi, $k_cs ) ) { $cho_anh[ $k_cs ] = (int) $so_ng; }
			}
			if ( $cho_anh ) {
				$tong_anh = 0;
				foreach ( $cho_anh as $so_ng ) { $tong_anh += $so_ng; }
				echo '<div class="bao ok">📷 <b>' . $tong_anh . ' người chưa có ảnh thẻ nhưng ĐÃ có '
					. 'sẵn ảnh khuôn mặt dùng được</b> trong các lượt chấm công online của chính họ '
					. '— lấy làm ảnh thẻ được ngay, khỏi gọi ra chụp lại. Mở màn <b>Bảng công</b> của '
					. 'cơ sở rồi bấm <b>Chỉ lưu vào hồ sơ</b> hoặc <b>Lưu &amp; đẩy xuống máy</b>:';
				echo '<div class="hang" style="gap:6px;margin-top:6px">';
				foreach ( $cho_anh as $k_cs => $so_ng ) {
					echo '<a class="nut" href="' . esc_url( add_query_arg(
						array( 'man' => 'cham', 'ccs' => $k_cs ), VHCC_Web::url() ) ) . '">'
						. esc_html( $k_cs ) . ' <b>' . (int) $so_ng . '</b></a>';
				}
				echo '</div></div>';
			}
		}

		self::o_tim( $toi, $cs, $q, $vai, $mang, $nbp );
		self::dai_mang_bp( $dem_mb, $mang, $nbp );
		self::dai_vai( $dem_v, $vai );

		if ( ! $lat ) {
			echo '<div class="bao canh">Không có hồ sơ nào khớp bộ lọc.</div></div>';
			return;
		}

		echo '<form method="post">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		/* `cuon-ns` + `b-ns`: bảng NÀY chạy chế độ gọn để vừa bề ngang trang — xem `css_them()`.
		   Đặt lớp riêng chứ không sửa `table` chung: mấy bảng nhỏ cùng trang không cần bóp. */
		/* 🔴 MỒI SẴN BỘ PHẬN & MẢNG CHO CẢ LÁT CẮT. Cột "Quyền vào trang" hỏi `VHCC_Cong::giai()`
		   ba lần mỗi hàng; không mồi thì mỗi hàng là một lượt SELECT hồ sơ — cho đúng những dòng
		   vừa đọc xong ở ngay trên. 50 hàng = 50 lượt thừa, mỗi lần mở trang. */
		foreach ( $lat as $r_moi ) {
			$ma_moi = trim( (string) $r_moi['ma_nv'] );
			if ( '' !== $ma_moi ) { VHCC_Cong::nhom_cua( $ma_moi, $r_moi ); }
		}
		echo '<div class="cuon cuon-ns"><table class="b-ns"><thead><tr>';
		echo '<th>Mã NV</th><th>Họ tên</th><th>Cơ sở</th><th>Vai trò</th>';
		/* =====================================================================================
		 * 🔴 BẢNG NÀY LÀ BẢNG QUYỀN, KHÔNG PHẢI BẢNG SƠ ĐỒ TỔ CHỨC — anh Thắng 14/09/2026
		 * =====================================================================================
		 * *"loại bỏ mảng kinh doanh và bộ phận (sẽ tạo trong thông tin nhân viên)"*, rồi ngay sau
		 * đó: *"mở lại quyền truy cập trang"*. Hai câu ấy là MỘT cặp đổi chỗ, không phải hai việc.
		 *
		 * Bản 3.77.0 làm ngược: gộp năm cột quyền thành một dải chip CHỈ ĐỌC, trong khi hai cột
		 * Mảng/Bộ phận giữ nguyên — mỗi ô là một hộp tích bốn dòng, nên chúng chiếm hết bề ngang
		 * lẫn chiều cao của bảng. Kết cục là màn hình dành chỗ đẹp nhất cho thứ khai MỘT LẦN, còn
		 * thứ phải bấm HÀNG NGÀY thì chỉ được nhìn.
		 *
		 * Nay trả đúng chỗ: quyền vào trang bấm thẳng trên hàng; mảng và bộ phận chuyển vào khối
		 * «sửa ▾» — thông tin nhân viên của chính người ấy, và đúng bậc quyền để sửa nó.
		 *
		 * ⚠️ Dải đếm và ô lọc theo mảng / bộ phận Ở TRÊN vẫn giữ nguyên. Bỏ cột là bỏ chỗ KHAI,
		 *    không phải bỏ chỗ NHÌN — mất dải đếm là mất luôn danh sách "ai chưa suy ra mảng".
		 * ===================================================================================== */

		/* 🔴 NÚT ÁP CẢ CỘT — thứ thật sự tiết kiệm thời gian. Đổi ô xổ thành nút bấm mới bớt
		   được một lần bấm mỗi ô; còn khoá cả một cơ sở cho một trang thì vẫn là 50 lần bấm.
		   ⚠️ Chỉ áp cho người ĐANG HIỆN — cùng luật với nút Lưu. Bảng có lọc và phân trang, nên
		      "cả cột" nghĩa là cả cột của lát cắt này, không phải của 240 người. */
		foreach ( $ds_trang as $k_t => $t ) {
			echo '<th class="tr-doc">' . esc_html( $t['ten'] ) . '<br><span class="cot-nut">';
			foreach ( array( '' => 'vai', 'mo' => 'Mở', 'khoa' => 'Khoá' ) as $gt => $ten ) {
				echo '<button type="submit" name="cot" value="' . esc_attr( $k_t . '|' . $gt ) . '"'
					. ' title="Áp «' . esc_attr( $ten ) . '» cho tất cả người đang hiện, rồi lưu luôn">'
					. esc_html( $ten ) . '</button>';
			}
			echo '</span></th>';
		}
		/* 🔴 CỘT GHẾ TRÔNG GIỐNG BA CỘT KIA NHƯNG LÀ MỘT CƠ CHẾ KHÁC — xem đầu `VHCC_DayGhe`.
		   Ba cột kia ghi một NGOẠI LỆ vào sổ quyền; cột này ĐẨY NGƯỜI THẬT sang sổ người dùng
		   của hệ ghế, vì `/ghe` có phiên riêng và không đọc `ma_nv`. Nên nó chỉ có HAI nút. */
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
		$co_bc = self::cot_bao_cao( $toi );
		if ( $co_bc ) {
			echo '<th class="tr-doc">Quản trị báo cáo cơ sở<br><span class="cot-nut">';
			foreach ( array( 'mo' => 'Đẩy', '' => 'Gỡ' ) as $gt => $ten ) {
				echo '<button type="submit" name="cot" value="'
					. esc_attr( VHCC_DayBaoCao::COT . '|' . $gt ) . '"'
					. ' title="' . esc_attr( 'mo' === $gt
						? 'Đẩy tất cả người đang hiện sang màn Quản trị báo cáo cơ sở, rồi lưu luôn'
						: 'Gỡ tất cả người đang hiện khỏi màn Quản trị báo cáo cơ sở, rồi lưu luôn' ) . '">'
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
							/* 🔴 KHÔNG GỘP NGAY TỪ NÚT NÀY. Gộp xoá một hồ sơ và dời lịch sử chấm
							   công ở 20 bảng — không có đường lùi. Nút chỉ MỞ MÀN XEM TRƯỚC; ở đó
							   bày ra sẽ dời gì, đụng gì, mất gì, rồi mới cho gõ xác nhận.
							   Dùng đường dẫn (GET) chứ không POST: xem trước là việc ĐỌC, F5 lại
							   không lỡ tay làm gì cả. */
							echo '<br><a class="nut" style="margin-top:4px;padding:2px 8px;font-size:12px" '
								. 'href="' . esc_url( add_query_arg(
									array( 'gop_a' => $chinh, 'gop_b' => $phu ), self::url_hien() ) ) . '" '
								. 'title="' . esc_attr( 'Xem trước việc gộp ' . $phu . ' vào ' . $chinh
									. ' — chưa đổi gì cả.' ) . '">'
								. 'Ghép với ' . esc_html( $ma_doi ) . '</a>';
						}
					}
				} elseif ( $co_trung['ten'] ) {
					echo '<span class="chip-t" title="Có người cùng tên ở CƠ SỞ KHÁC — có thể là hai '
						. 'người thật, cũng có thể là một người làm hai nơi.">trùng tên (khác cơ sở)</span>';
					/* 🔴 CŨNG PHẢI CÓ ĐƯỜNG GHÉP. Anh Thắng 13/09/2026: *"Không hiện chỗ sửa hồ sơ
					   để ghép"* — ảnh là hai hồ sơ "Nguyễn Thị Mai Anh" mang đúng nhãn này mà
					   không có nút nào.
					   Một người làm hai cơ sở là chuyện THƯỜNG ở chuỗi này, nên "khác cơ sở" không
					   đủ để kết luận hai người. Bản trước cố ý giấu nút vì sợ gộp nhầm — nhưng giấu
					   đường đi thì người ta đi đường vòng (sửa tay, xoá bớt), còn nguy hơn.
					   ⚠️ Nút MỞ XEM TRƯỚC, và ở đó có cảnh báo riêng cho ca khác cơ sở. Chốt nằm ở
					      màn xem trước, không nằm ở việc giấu nút. */
					if ( VHCC_Vai::duoc( $toi, 'he_thong' ) && ! empty( $co_trung['doiTen'] ) ) {
						foreach ( $co_trung['doiTen'] as $md ) {
							$lt = isset( $hoat_dong[ $ma ] ) ? (int) $hoat_dong[ $ma ]['luot'] : 0;
							$ld = isset( $hoat_dong[ $md ] ) ? (int) $hoat_dong[ $md ]['luot'] : 0;
							$gi = ( $ld > $lt ) ? $md : $ma;
							$bo = ( $ld > $lt ) ? $ma : $md;
							echo '<br><a class="nut" style="margin-top:4px;padding:2px 8px;font-size:12px" '
								. 'href="' . esc_url( add_query_arg(
									array( 'gop_a' => $gi, 'gop_b' => $bo ), self::url_hien() ) ) . '" '
								. 'title="' . esc_attr( 'Xem trước việc gộp ' . $bo . ' vào ' . $gi
									. ' — chưa đổi gì cả. Hai hồ sơ này KHÁC cơ sở, soát kỹ trước.' ) . '">'
								. 'Ghép với ' . esc_html( $md ) . '</a>';
						}
					}
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
				/* 🔴 HAI NÚT MÁY CHẤM CÔNG — anh Thắng 29/08/2026: *"thêm tính năng xoá, sửa trường
				   hợp lỗi hoặc nhân viên nghỉ việc"*. Trước nay chỉ lúc TẠO hồ sơ mới có lệnh đẩy
				   xuống máy (`day_len_may()`); sửa tên/ảnh sau đó hay cho nghỉ việc thì máy vẫn giữ
				   nguyên thông tin cũ / vẫn cho quẹt — không có đường nào chữa lại hay gỡ ra.
				   Firmware đã có sẵn `edit`/`delete` (xem `esp32_hik_chamcong_full.ino`), chỉ thiếu
				   phía WordPress gọi tới — xem `VHCC_NhanSu::sua_lai_tren_may()` / `xoa_khoi_may()`.
				   Không mở `<form>` riêng — cùng lối `ghep_voi`/`xoa_ma` ở trên, mỗi nút mang tên
				   riêng nên chỉ lượt bấm đúng nó mới gửi giá trị lên. */
				echo ' <button type="submit" class="mo-hs" name="sua_may" value="' . esc_attr( $ma ) . '"'
					. ' title="Đẩy lại tên/ảnh/giới tính hiện có xuống các máy ở cơ sở này — dùng khi'
					. ' sửa hồ sơ xong mà máy còn hiện thông tin cũ">sửa lại trên máy 🔄</button>';
				echo ' <button type="submit" class="mo-hs" name="xoa_may" value="' . esc_attr( $ma ) . '"'
					. ' title="Gỡ người này khỏi các máy chấm công ở cơ sở — hồ sơ và lịch sử chấm'
					. ' công trên web KHÔNG bị đụng tới, chỉ máy vật lý thôi. Dùng khi tạo lỗi hoặc'
					. ' khi nhân viên nghỉ việc.">gỡ khỏi máy 📵</button>';
				/* 🔴 XOÁ LÀ VIỆC KHÔNG ĐẢO LẠI ĐƯỢC, NÊN NÓ ĐI HAI NHỊP.
				   Anh Thắng 28/08/2026, sau khi thử thêm một người rồi thấy hàng rác trong sổ:
				   *"Giờ anh muốn xóa nhân viên đó đi"*. Trước nay chỉ wp-admin xoá được, nên
				   hàng rác cứ nằm đấy.
				   Nhịp một là một ĐƯỜNG DẪN (GET) — bấm nhầm thì không mất gì, chỉ mở ra lời
				   hỏi. Nhịp hai mới là nút gửi. Không dùng hộp thoại xác nhận bằng JavaScript:
				   cả màn này không có lấy một dòng script, mà thứ bộ thử PHP không với tới thì
				   không phải là chốt. */
				/* 🔴 XEM PIN — anh Thắng 31/08/2026: *"Bổ sung xem PIn được tại vị trí này"*, kèm
				   ảnh đúng dãy nút này.

				   🔴 BẤM MỚI HIỆN, VÀ CHỈ HIỆN MỘT NGƯỜI. Luật của cả hệ từ đầu là không in PIN
				      ra màn — trang chạy ngoài internet, ảnh chụp bảng nhân sự đi khắp nơi
				      (chính tấm ảnh kèm yêu cầu này là một ví dụ: cả bảng 8 người ra ngoài chat).
				      In sẵn thành một cột là mỗi tấm ảnh lộ toàn bộ chìa khoá của cả cơ sở. Bấm
				      một người, tải lại trang, hiện đúng số ấy — ảnh chụp cả bảng vẫn sạch.

				   ⚠️ Bậc ADMIN (`xem_pin`), cao hơn cửa vào trang này một bậc. Xem PIN là đăng
				      nhập thay người ta được mà màn hình của họ không có gì đổi. */
				if ( VHCC_Vai::duoc( $toi, 'xem_pin' ) ) {
					if ( $dang_pin === $ma ) {
						echo ' <a class="mo-hs" href="' . esc_url( self::url_pin( '' ) ) . '">ẩn PIN ▲</a>';
					} else {
						echo ' <a class="mo-hs" title="Hiện số PIN đang dùng của người này — mỗi lượt'
							. ' xem đều vào sổ, và chỉ hiện đúng một người"'
							. ' href="' . esc_url( self::url_pin( $ma ) . '#hs' . substr( md5( $ma ), 0, 8 ) )
							. '">xem PIN 👁</a>';
					}
				}
				if ( VHCC_Vai::duoc( $toi, 'xoa_ho_so' ) ) {
					echo ' <a class="mo-hs xoa-hs" title="Xoá hẳn hồ sơ này khỏi sổ"'
						. ' href="' . esc_url( self::url_xoa( $ma ) . '#hs' . substr( md5( $ma ), 0, 8 ) )
						. '">xoá 🗑</a>';
				}
				if ( $dang_pin === $ma ) { self::o_xem_pin( $toi, $ma ); }
			}
			echo '</td>';
			echo '<td>' . self::o_coso( $toi, $ma, (string) $r['cua_hang'], $r ) . '</td>';
			echo '<td>' . self::o_vai( $toi, $ma, isset( $r['vai_tro'] ) ? $r['vai_tro'] : '', $r ) . '</td>';
			/* Giả một "người" chỉ có mã + vai, để hỏi `VHCC_Cong` xem MẶC ĐỊNH của họ ra sao.
			   ⚠️ Hỏi bằng CHÍNH hàm mà cửa vào dùng, không tự tính lại bậc ở đây — hai phép
			      tính cho cùng một câu hỏi là sớm muộn màn hình nói khác cửa vào. */
			$gia = array( 'ma_nv' => $ma, 'role' => (string) ( isset( $r['vai_tro'] ) ? $r['vai_tro'] : '' ) );

			foreach ( $ds_trang as $k => $t ) {
				/* ⚠️ Không có mã NV thì ngoại lệ không bám vào đâu được — thẻ phiên mang mã rỗng,
				   nên `duoc_vao()` bỏ qua sạch. Nói ra, đừng vẽ một ô chọn vô tác dụng. */
				if ( '' === $ma ) {
					echo '<td class="o-q-td"><span class="chua-ma">chưa có Mã NV</span></td>';
					continue;
				}
				/* 🔴 NÚT ĐẦU PHẢI NÓI ĐÚNG NÓ ĐANG THEO GÌ. Có luật nhóm thì "bỏ ngoại lệ" nghĩa
				   là về theo LUẬT BỘ PHẬN, không phải về theo thang vai — xem `ba_nut()`. */
				$n_t = VHCC_Cong::nhom_noi_gi( $ma, $k );
				$mac = ( null !== $n_t ) ? (bool) $n_t['duoc'] : VHCC_Vai::duoc( $gia, $t['quyen'] );
				echo '<td class="o-q-td">'
					. self::ba_nut( $ma, $k, VHCC_Cong::o( $ma, $k ), $mac, $n_t ) . '</td>';
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
			if ( $co_bc ) {
				echo '<td class="o-q-td">'
					. ( '' === $ma ? '<span class="chua-ma">chưa có Mã NV</span>'
						: self::hai_nut_bao_cao( $ma ) ) . '</td>';
			}
			echo '</tr>';
			$so_cot_hang = 4 + count( $ds_trang ) + ( $co_ghe ? 1 : 0 ) + ( $co_cp ? 1 : 0 )
				+ ( $co_bc ? 1 : 0 );
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

	/**
	 * Địa chỉ mở / đóng ô XEM PIN của một người.
	 *
	 * ⚠️ Gỡ luôn `xoa_o` và `sua_o`: ba thứ ấy đều chèn thêm một khối vào giữa bảng, mở cùng lúc
	 *    thì hàng người này đẩy hàng người kia đi và người bấm không còn biết mình đang xem của ai.
	 */
	/**
	 * Ô hiện PIN của MỘT người, ngay dưới tên họ.
	 *
	 * ⚠️ Gọi `VHCC_NhanSu::xem_pin()` chứ không đọc thẳng cột: hàm ấy giữ hai thứ mà màn này
	 *    không được tự làm lấy — chốt quyền, và ghi vào sổ rằng đã xem. Đọc thẳng cột cho ra
	 *    đúng con số ấy mà bỏ mất cả hai.
	 */
	private static function o_xem_pin( $toi, $ma ) {
		$r = VHCC_NhanSu::xem_pin( $toi, $ma );
		if ( empty( $r['ok'] ) ) {
			echo '<div class="pin-o pin-loi">' . esc_html( (string) $r['error'] ) . '</div>';
			return;
		}
		if ( '' === (string) $r['pin'] ) {
			echo '<div class="pin-o">Người này <b>chưa có PIN</b> — cấp ở ô <b>sửa ▾</b> hoặc ở '
				. 'màn Nhân sự cửa hàng.</div>';
			return;
		}
		/* Số to, giãn chữ: người ta mở ô này ra để ĐỌC CHO AI ĐÓ QUA ĐIỆN THOẠI, và 6 chữ số
		   dính nhau thì đọc nhầm 3 với 8. */
		echo '<div class="pin-o"><b>PIN:</b> <code class="pin-so">' . esc_html( $r['pin'] ) . '</code>'
			. ' <span class="pin-nhac">đã vào sổ — đóng lại khi đọc xong</span></div>';
	}

	private static function url_pin( $ma ) {
		$u = remove_query_arg( array( 'sua_o', 'xoa_o', 'pin_o' ), self::url_hien() );
		return ( '' === $ma ) ? $u : add_query_arg( 'pin_o', $ma, $u );
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

		/* =====================================================================================
		 * MẢNG KINH DOANH & BỘ PHẬN — THÔNG TIN NHÂN VIÊN, KHÔNG PHẢI MỘT CỘT CỦA BẢNG
		 * =====================================================================================
		 * Anh Thắng 14/09/2026: *"loại bỏ mảng kinh doanh và bộ phận (sẽ tạo trong thông tin
		 * nhân viên)"*.
		 *
		 * Anh ấy đúng ở chỗ ĐÂY LÀ THÔNG TIN CỦA NGƯỜI, không phải một thao tác hàng ngày. Khai
		 * một lần lúc vào làm, đổi khi điều động — mà bản trước cho nó hai cột rộng nhất bảng,
		 * mỗi ô một hộp tích bốn dòng, nhân với 50 hàng.
		 *
		 * ⚠️ ĐẶT Ở ĐÂY CHỨ KHÔNG Ở MÀN HỒ SƠ CỦA CỬA HÀNG TRƯỞNG (`VHCC_WebNS::the_sua`). Màn ấy
		 *    gác bằng `ho_so_coso` — cửa hẹp, chỉ mở bốn ô liên lạc và ô PIN, cố ý không cho đụng
		 *    tới thứ ra tiền hay ra quyền. Mảng và bộ phận nay CHÍNH LÀ thứ ra quyền (luật nhóm,
		 *    bó phạm vi theo mảng), nên chúng phải ở bậc Kế toán — tức ở đây.
		 * ===================================================================================== */
		if ( '' !== $ma ) {
			list( $o_mang, $o_bp ) = self::o_mang_bp( $toi, $ma, $r );
			echo '<div style="margin-top:12px;padding-top:10px;border-top:1px dashed var(--vien)">';
			echo '<b>Mảng kinh doanh &amp; bộ phận</b> <span class="mo">— để trống là <b>trôi theo '
				. 'cơ sở</b>: đổi mảng của cơ sở thì người này theo ngay, khỏi sửa lại.</span>';
			echo '<div class="hang" style="gap:22px;margin-top:8px;flex-wrap:wrap;align-items:flex-start">';
			echo '<span class="q-mot"><span class="q-ten">Mảng kinh doanh</span>' . $o_mang . '</span>';
			echo '<span class="q-mot"><span class="q-ten">Bộ phận</span>' . $o_bp . '</span>';
			echo '</div></div>';
		}

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
	/**
	 * Ô CƠ SỞ — LƯỚI Ô TÍCH, NHIỀU CƠ SỞ MỘT NGƯỜI.
	 *
	 * Anh Thắng 31/08/2026: *"thay vì cửa hàng trưởng làm ở 1 cơ sở đó, tích chọn quản lý các cơ
	 * sở khác, thì có thể quản lý các nhân viên ở các cơ sở khác"*, và chốt **một ô chung**: tích
	 * cơ sở nào là vừa làm việc vừa quản ở đó.
	 *
	 * 🔴 Ô XỔ CHỌN MỘT LÀ CÁI CÒN SÓT LẠI. Màn Hồ sơ đã đổi sang lưới ô tích từ bản 3.13.0, còn
	 *    trang này vẫn bắt chọn đúng một — hai màn nói hai kiểu về cùng một thứ, và bấm Lưu ở
	 *    đây là xoá sạch những cơ sở khai bên kia.
	 *
	 * ⚠️ CƠ SỞ NGƯỜI TA ĐANG KHAI MÀ MÌNH KHÔNG PHỤ TRÁCH thì VẪN hiện, tích sẵn, và KHOÁ lại
	 *    (`disabled` + ô ẩn chở giá trị). Không hiện thì bấm Lưu là lặng lẽ gỡ người ta khỏi một
	 *    cơ sở mình không có quyền đụng tới; hiện mà cho bỏ tích thì cũng thế.
	 */
	private static function o_coso( $toi, $ma, $cs_cu, $hs = array() ) {
		$dang = VHCC_NhanSu::ds_coso_hs( is_array( $hs ) && $hs
			? $hs : array( 'cua_hang' => $cs_cu, 'coso_phu' => '' ) );
		if ( '' === $ma || ! VHCC_NhanSu::co_quan_tri_nv( $toi ) ) {
			return esc_html( implode( ', ', $dang ) );
		}
		$co_dang = array();
		foreach ( $dang as $c ) { $co_dang[ VHCC_NhanSu::chu_thuong( $c ) ] = $c; }

		$ds = VHCC_NhanSu::ds_coso();
		foreach ( $dang as $c ) {
			if ( ! in_array( $c, $ds, true ) ) { $ds[] = $c; }
		}
		sort( $ds );

		$ten = 'cs[' . esc_attr( $ma ) . '][]';
		/* Hàng nào bày ra thì gom trước, VẼ SAU — cột "chính" chỉ có nghĩa khi bày từ hai cơ sở
		   trở lên, mà số hàng bày ra chỉ biết được sau khi đã lọc xong. */
		$bay = array();
		foreach ( $ds as $c ) {
			$k     = VHCC_NhanSu::chu_thuong( $c );
			$tich  = isset( $co_dang[ $k ] );
			$duoc  = VHCC_NhanSu::co_quyen_coso( $toi, $c );
			if ( ! $duoc && ! $tich ) { continue; }   // không quản, không khai -> không bày ra
			$bay[] = array( $c, $tich, $duoc );
		}
		$chinh   = isset( $dang[0] ) ? $dang[0] : '';
		$ten_ch  = 'cs_chinh[' . esc_attr( $ma ) . ']';
		$ten_ql  = 'cs_ql[' . esc_attr( $ma ) . '][]';
		$ve_chinh = count( $bay ) > 1;
		/* Cờ "chỉ quản lý — không chấm công" đang đặt ở những cơ sở nào (xem
		   `VHCC_NhanSu::ds_coso_ql()`). Cũng chỉ có nghĩa khi bày từ hai cơ sở trở lên: một cơ
		   sở mà đặt "chỉ quản lý" thì người ấy không chấm được ở đâu cả. */
		$co_ql = array();
		foreach ( VHCC_NhanSu::ds_coso_ql( is_array( $hs ) && $hs
			? $hs : array( 'cua_hang' => $cs_cu, 'coso_phu' => '', 'coso_ql' => '' ) ) as $c_q ) {
			$co_ql[ VHCC_NhanSu::chu_thuong( $c_q ) ] = 1;
		}

		$h = '<div class="o-cs-tich">';
		/* 🔴 Ô ẨN CHỞ CƠ SỞ CHÍNH ĐANG CÓ, ĐẶT TRƯỚC MỌI NÚT TRÒN. Hai lý do, cả hai đều là mất
		   dữ liệu im lặng nếu thiếu: (1) nút tròn của cơ sở mình KHÔNG phụ trách bị khoá nên
		   không gửi gì lên — không chở thì mỗi lượt Lưu là cơ sở chính của người ta nhảy về cơ
		   sở đầu bảng chữ cái; (2) hàng chỉ có một cơ sở thì không vẽ nút nào cả. PHP lấy giá
		   trị GỬI SAU cho một tên vô hướng, nên đặt trước = nút tròn bấm được luôn đè lên. */
		if ( '' !== $chinh ) {
			$h .= '<input type="hidden" name="' . $ten_ch . '" value="' . esc_attr( $chinh ) . '">';
		}
		foreach ( $bay as $b ) {
			list( $c, $tich, $duoc ) = $b;
			$h .= '<div class="cs-hang">';
			$h .= '<label' . ( $duoc ? '' : ' title="Cơ sở bạn không phụ trách — giữ nguyên"' ) . '>'
				. '<input type="checkbox" name="' . $ten . '" value="' . esc_attr( $c ) . '"'
				. checked( true, $tich, false ) . ( $duoc ? '' : ' disabled' ) . '>'
				. esc_html( $c ) . '</label>';
			/* Ô khoá không gửi giá trị lên — chở bằng ô ẩn, kẻo lượt Lưu gỡ mất nó. */
			if ( ! $duoc && $tich ) {
				$h .= '<input type="hidden" name="' . $ten . '" value="' . esc_attr( $c ) . '">';
			}
			if ( $ve_chinh ) {
				$la_ch = VHCC_NhanSu::chu_thuong( $c ) === VHCC_NhanSu::chu_thuong( $chinh );
				$h .= '<label class="cs-ch" title="Cơ sở CHÍNH — cơ sở được chọn sẵn khi người này'
					. ' mở trang chấm công. Chấm ở cơ sở nào trong danh sách cũng được tính đủ.">'
					. '<input type="radio" name="' . $ten_ch . '" value="' . esc_attr( $c ) . '"'
					. checked( true, $la_ch, false ) . ( $duoc ? '' : ' disabled' ) . '>chính</label>';
				/* 🔴 CHỈ QUẢN LÝ — KHÔNG CHẤM CÔNG. Anh Thắng 09/09/2026: *"đối với cửa hàng chỉ
				   quản lý nhân viên không chấm công thì làm sao để loại ra khỏi bảng chấm công,
				   nhưng vẫn quản lý được nhân viên cơ sở đó"*. Tích ô này thì cơ sở ấy biến khỏi
				   ô xổ cơ sở trên trạm và khỏi lưới bảng công của người này — nhưng ô TÍCH vẫn
				   nguyên, nên quyền quản lý ở đó không đổi một chút nào. */
				$la_ql = isset( $co_ql[ VHCC_NhanSu::chu_thuong( $c ) ] );
				$h .= '<label class="cs-ql" title="CHỈ QUẢN LÝ — không chấm công ở cơ sở này.'
					. ' Cơ sở sẽ không hiện trong ô chọn lúc chấm và không mọc hàng trống trong'
					. ' bảng công, nhưng người này VẪN quản lý nhân viên ở đó. Không đặt được cho'
					. ' cơ sở chính.">'
					. '<input type="checkbox" name="' . $ten_ql . '" value="' . esc_attr( $c ) . '"'
					. checked( true, $la_ql, false ) . ( $duoc ? '' : ' disabled' ) . '>chỉ QL</label>';
				/* Ô khoá không gửi giá trị — chở bằng ô ẩn, kẻo lượt Lưu của người không phụ
				   trách cơ sở ấy lặng lẽ bật lại chấm công ở đó. */
				if ( ! $duoc && $la_ql ) {
					$h .= '<input type="hidden" name="' . $ten_ql . '" value="' . esc_attr( $c ) . '">';
				}
			}
			$h .= '</div>';
		}
		/* 🔴 Ô ẨN ĐÁNH DẤU "HÀNG NÀY CÓ GỬI CƠ SỞ". Bỏ tích HẾT thì trình duyệt không gửi phần
		   tử nào, và nơi xử không phân biệt nổi "người ta bỏ hết" với "hàng này không có trên
		   trang" — đoán sai chiều nào cũng hỏng: một bên xoá oan, một bên không xoá được. */
		$h .= '<input type="hidden" name="cs_co[' . esc_attr( $ma ) . ']" value="1">';
		return $h . '</div>';
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

	/**
	 * Ô CHỌN MẢNG / BỘ PHẬN của một hàng.
	 *
	 * 🔴 DÒNG ĐẦU Ô XỔ LÀ "THEO CƠ SỞ", KHÔNG PHẢI MỘT Ô TRỐNG CÂM.
	 *    Cột để trống nghĩa là người này trôi theo cơ sở — một trạng thái ĐÚNG và là mặc định của
	 *    199/225 người, không phải thiếu dữ liệu. Ô trống câm thì người đọc tưởng chưa khai và đi
	 *    khai tay cho cả sổ, mà khai tay xong là mất hẳn tính "đổi mảng cơ sở thì mọi người theo".
	 *    Nên dòng ấy nói luôn nó đang suy ra cái gì: «theo cơ sở (Khu vui chơi)».
	 *
	 * ⚠️ Ô ẨN `mbp_co[MÃ]` phải có mặt ở MỌI hàng đang hiện. Thiếu nó thì hàng nào người ta chọn
	 *    về "theo cơ sở" sẽ không gửi lên gì cả và lượt lưu bỏ qua hàng ấy — bấm Lưu thấy y
	 *    nguyên, bấm mấy lượt rồi thôi. Đúng bài học của `cs_co[]` ở `luu_coso()`.
	 */
	private static function o_mang_bp( $toi, $ma, $hs ) {
		$x = VHCC_NhanSu::mang_bo_phan_cua( $hs );
		if ( '' === $ma || ! VHCC_NhanSu::co_sua_ho_so( $toi ) ) {
			$m = ( '' !== $x['mang'] ) ? $x['mang'] : '— chưa xếp —';
			$b = ( '' !== $x['boPhan'] ) ? $x['boPhan'] : '— chưa xếp —';
			return array( '<span class="o-vai">' . esc_html( $m ) . '</span>',
				'<span class="o-vai">' . esc_html( $b ) . '</span>' );
		}
		$sm = VHCC_NhanSu::suy_mang( $hs );

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * Ô MẢNG LÀ HỘP TÍCH NHIỀU Ô, KHÔNG PHẢI Ô XỔ MỘT LỰA CHỌN.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 13/09/2026: *"nhân viên là người làm thì họ có thể làm ở 2 mảng nhiều cơ sở"*
		 * — *"làm ở 2 mảng, thì chấm công ở 2 mảng"*. Ô xổ một lựa chọn bắt người khai phải CHỌN
		 * BỎ một mảng người ta thật sự làm; khai xong là sổ ghi sai, mà lại sai do chính cái ô ép
		 * ra. Cùng hình dạng với cột Cơ sở bên cạnh, nên tay đã quen.
		 *
		 * ⚠️ KHÔNG TÍCH Ô NÀO = «theo cơ sở», KHÔNG phải "không thuộc mảng nào". Nhãn ngay dưới
		 *    nói rõ nó đang suy ra gì từ cơ sở nào — không có nhãn ấy thì ô trắng trông y như dữ
		 *    liệu thiếu, và người ta đi tích tay cho cả sổ, làm mất luôn tính "đổi mảng cơ sở thì
		 *    mọi người theo".
		 */
		$hm = '<input type="hidden" name="mbp_co[' . esc_attr( $ma ) . ']" value="1">';
		$hm .= '<div class="mb-hop">';
		/* 🔴 MẢNG ĐANG ẨN MÀ NGƯỜI NÀY ĐANG KHAI TAY THÌ VẪN PHẢI HIỆN. Bỏ nó khỏi hộp tích là ô
		   ấy mất dấu tích, và một cú bấm Lưu xoá luôn mảng của họ — im lặng, vì trên màn chưa bao
		   giờ có ô ấy để mà thấy nó biến mất. Đúng cái bẫy `dat_vai_tro()` đã dính với "Kế Toán
		   MTD", chỉ khác chỗ lần này là mảng. */
		$ds_bay = VHCC_NhanSu::ds_mang();
		foreach ( $x['dsMangKhai'] as $m_giu ) {
			if ( ! in_array( $m_giu, $ds_bay, true ) ) { $ds_bay[] = $m_giu; }
		}
		foreach ( $ds_bay as $ten ) {
			$tick = in_array( $ten, $x['dsMangKhai'], true );
			$hm .= '<label class="mb-o"><input type="checkbox" name="mbp_mang[' . esc_attr( $ma ) . '][]"'
				. ' value="' . esc_attr( $ten ) . '"' . checked( true, $tick, false ) . '> '
				/* ⚠️ NHÃN đọc tên hiện ra, GIÁ TRỊ vẫn là mã lưu — xem `VHCC_NhanSu::ten_mang()`.
				   Gửi tên dài lên là lương cả mảng rơi về «Chưa xếp». */
				. esc_html( VHCC_NhanSu::ten_mang( $ten ) ) . '</label>';
		}
		$hm .= '</div>';
		if ( ! $x['dsMangKhai'] ) {
			$hm .= '<div class="mb-suy" title="' . esc_attr( $sm['vi'] ) . '">'
				. ( $sm['dsMang']
					? '« theo cơ sở → ' . esc_html( implode( ' + ',
						array_map( array( 'VHCC_NhanSu', 'ten_mang' ), $sm['dsMang'] ) ) ) . ' »'
					: '⚠️ ' . esc_html( $sm['vi'] ) )
				. '</div>';
		} else {
			$hm .= '<div class="mb-suy">« đã ghim tay — bỏ hết tích để trôi lại theo cơ sở »</div>';
		}

		$hb = '<select class="o-q-vai" name="mbp_bp[' . esc_attr( $ma ) . ']">';
		$suy_b = VHCC_NhanSu::suy_bo_phan( $hs );
		$hb .= '<option value=""' . selected( '', $x['boPhanKhai'], false ) . '>« '
			. esc_html( '' !== $suy_b ? 'theo cơ sở → ' . $suy_b : 'chưa có cơ sở — chọn tay' ) . ' »</option>';
		foreach ( VHCC_NhanSu::ds_bo_phan() as $ten ) {
			$hb .= '<option value="' . esc_attr( $ten ) . '"' . selected( $ten, $x['boPhanKhai'], false ) . '>'
				. esc_html( $ten ) . '</option>';
		}
		$hb .= '</select>';
		return array( $hm, $hb );
	}

	/**
	 * Lưu mảng / bộ phận cho những hàng đang hiện.
	 *
	 * ⚠️ ĐIỀU ĐỘNG HÀNG LOẠT ĐI QUA ĐÚNG HÀM NÀY. Nút "áp cả cột" chỉ điền sẵn ô xổ rồi gửi lượt
	 *    lưu bình thường — không có đường ghi tắt thứ hai xuống thẳng cơ sở dữ liệu, nên chốt
	 *    quyền và chốt danh sách trắng của `dat_mang_bo_phan()` không thể bị đi vòng.
	 */
	/** Ô tích mảng của một hàng -> chuỗi "A, B". Bỏ tích hết -> '' = trôi lại theo cơ sở. */
	private static function gom_mang( $gui ) {
		$ra = array();
		foreach ( (array) $gui as $x ) {
			$x = sanitize_text_field( (string) $x );
			if ( '' !== $x && ! in_array( $x, $ra, true ) ) { $ra[] = $x; }
		}
		return implode( ', ', $ra );
	}

	private static function luu_mang_bp( $toi ) {
		$co = isset( $_POST['mbp_co'] ) ? wp_unslash( $_POST['mbp_co'] ) : array();
		if ( ! is_array( $co ) || ! $co ) { return array( 'doi' => 0, 'loi' => array() ); }
		$m_g = isset( $_POST['mbp_mang'] ) ? wp_unslash( $_POST['mbp_mang'] ) : array();
		$b_g = isset( $_POST['mbp_bp'] ) ? wp_unslash( $_POST['mbp_bp'] ) : array();
		$doi = 0; $loi = array();
		foreach ( array_keys( $co ) as $ma_raw ) {
			$ma = sanitize_text_field( (string) $ma_raw );
			if ( '' === $ma ) { continue; }
			$m = self::gom_mang( isset( $m_g[ $ma_raw ] ) ? $m_g[ $ma_raw ] : array() );
			$b = isset( $b_g[ $ma_raw ] ) ? sanitize_text_field( (string) $b_g[ $ma_raw ] ) : '';
			$kq = VHCC_NhanSu::dat_mang_bo_phan( $toi, $ma, $m, $b );
			if ( empty( $kq['ok'] ) ) { $loi[] = (string) $kq['error']; continue; }
			if ( ! empty( $kq['doi'] ) ) { $doi++; }
		}
		return array( 'doi' => $doi, 'loi' => $loi );
	}

	private static function o_vai( $toi, $ma, $vai_cu, $hs = array() ) {
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
		/* 🔴 GỢI Ý THEO BỘ PHẬN — BÀY LÊN ĐẦU, KHÔNG CẮT BỚT.
		   Anh Thắng: *"nếu Khối cơ sở nó sinh ra là nhân viên, cửa hàng trưởng, cửa hàng phó"*.
		   Cách hiển nhiên (lọc ô xổ chỉ còn vai của bộ phận) là một cái bẫy: người đang mang vai
		   ngoài bộ phận thì lựa chọn ấy biến mất, ô NHẢY VỀ DÒNG ĐẦU, và một cú bấm Lưu đổi vai
		   của họ mà không ai định đổi. Nên chia NHÓM chứ không lọc — mọi vai vẫn còn nguyên. */
		$gy  = VHCC_NhanSu::vai_goi_y( $hs );
		$uu  = array();
		foreach ( $gy['trong'] as $t_uu ) {
			if ( VHCC_Vai::bac( array( 'role' => $t_uu ) ) <= $bac_toi ) { $uu[ $t_uu ] = 1; }
		}
		$mot = function ( $ten ) use ( $vai_cu, &$co_cu ) {
			$chon = ( trim( (string) $vai_cu ) === $ten );
			if ( $chon ) { $co_cu = true; }
			return '<option value="' . esc_attr( $ten ) . '"' . selected( true, $chon, false ) . '>'
				. esc_html( $ten ) . '</option>';
		};
		if ( $uu ) {
			$h .= '<optgroup label="' . esc_attr( 'Vai của ' . $gy['boPhan'] ) . '">';
			foreach ( array_keys( $uu ) as $t_uu ) { $h .= $mot( $t_uu ); }
			$h .= '</optgroup><optgroup label="Vai khác">';
		}
		foreach ( VHCC_Vai::ds_ten() as $ten ) {
			if ( VHCC_Vai::bac( array( 'role' => $ten ) ) > $bac_toi ) { continue; }
			if ( isset( $uu[ $ten ] ) ) { continue; }   // đã bày ở nhóm trên
			$h .= $mot( $ten );
		}
		if ( $uu ) { $h .= '</optgroup>'; }
		/* ⚠️ Hồ sơ đang ghi một chuỗi KHÔNG có trong danh sách ("ketoan", "NV", ô trống…) thì
		   phải giữ nguyên nó làm lựa chọn đang chọn. Không giữ thì ô xổ tự nhảy về dòng đầu, và
		   người khai chỉ bấm Lưu một cái là đổi vai cả trang mà không hề định đổi ai. */
		if ( ! $co_cu ) {
			$tho = trim( (string) $vai_cu );
			$h  .= '<option value="' . esc_attr( $tho ) . '" selected>'
				. esc_html( '' === $tho ? '— chưa khai —' : $tho . ' (chưa chuẩn)' ) . '</option>';
		}
		$h .= '</select>';
		/* Khai vai cho bộ phận TRƯỚC rồi tạo vai SAU là thứ tự tự nhiên ("Cửa hàng phó" chưa
		   tồn tại lúc anh Thắng nói ra nó). Im lặng bỏ qua thì người khai tưởng đã xong. */
		if ( ! empty( $gy['thieu'] ) ) {
			$h .= '<div class="mb-suy" title="Khai vai này ở khối Bảng vai trò bên dưới">⚠️ '
				. esc_html( $gy['boPhan'] ) . ' có khai vai «' . esc_html( implode( '», «', $gy['thieu'] ) )
				. '» — hệ chưa có vai ấy</div>';
		}
		return $h;
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
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * PHÂN QUYỀN THEO BỘ PHẬN & MẢNG — khai MỘT LẦN cho cả phòng
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 13/09/2026: *"Khi xây bộ phận xong thì chỗ này theo bộ rồi, không cần phân quyền
	 * từng người nữa"* — *"Ghế massage dành cho mảng kinh doanh máy tự động"*.
	 *
	 * 🔴 HAI CHIỀU, VÀ CHÚNG KHÁC NHAU THẬT:
	 *    · BỘ PHẬN trả lời "người này làm VIỆC GÌ" — Phòng Kế Toán cần trang chấm công, Khối Nhân
	 *      Viên Cơ Sở cần trạm.
	 *    · MẢNG trả lời "người này làm Ở ĐÂU" — và đó mới là chiều đúng cho Ghế massage: ghế nằm
	 *      trong mảng Máy tự động, ai chạy mảng ấy thì cần, bất kể họ thuộc phòng nào.
	 *    Gộp hai chiều vào một bảng là sớm muộn phải khai "Khối Nhân Viên Cơ Sở ở mảng Máy tự
	 *    động" thành một dòng riêng — tức là quay lại đúng chỗ tích tay từng trường hợp.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function the_quyen_nhom( $toi ) {
		/* 🔴 CHỈ VẼ CỘT NGƯỜI XEM BẤM ĐƯỢC — cùng luật với hai cột đẩy ở bảng chính. Vẽ một cột
		   mà bấm vào là bị chối thì tệ hơn không vẽ: họ bấm, thấy câu chối, và tưởng hệ hỏng. */
		$cot = VHCC_Cong::cot_nhom();
		if ( ! self::cot_ghe( $toi ) )     { unset( $cot[ VHCC_DayGhe::COT ] ); }
		if ( ! self::cot_chi_phi( $toi ) ) { unset( $cot[ VHCC_DayChiPhi::COT ] ); }
		if ( ! $cot ) { return; }
		$sua = VHCC_Vai::duoc( $toi, 'ho_so' );
		/* MỘT lượt đọc cho cả khối: vừa đếm người mỗi nhóm, vừa dò chênh lệch ghế/chi phí. Hai
		   việc ấy hỏi cùng sáu cột của cùng một bảng — tách ra là đọc cả sổ hai lần. */
		$so_do = self::ds_nhom_tho();
		$dem   = VHCC_NhanSu::dem_mang_bo_phan( $so_do );
		$bo_ds = VHCC_NhanSu::bo_mang_ds();

		echo '<div class="the"><h2>Phân quyền theo bộ phận &amp; mảng</h2>';
		echo '<p class="mo">Khai ở đây là khai cho <b>cả nhóm</b> — thêm người vào bộ phận ấy là '
			. 'họ có ngay, khỏi tích tay. Thứ tự xét: <b>đặt riêng cho một người</b> → <b>luật bộ '
			. 'phận</b> → <b>luật mảng</b> → <b>thang vai</b>. Ô để ở <b>«—»</b> là nhóm ấy không '
			. 'nói gì, để tầng dưới quyết.</p>';
		/* ⚠️ NÓI TRƯỚC LUẬT "MỞ THẮNG". Người làm hai mảng là chuyện thường ở chuỗi này; ai khai
		   luật mà không biết điều đó thì tưởng mình vừa khoá xong một mảng. */
		echo '<p class="mo">⚠️ Một người làm <b>hai mảng</b> thì <b>«Mở» thắng</b> — khoá một mảng '
			. 'không cắt được đường vào mà mảng kia đang mở. Muốn khoá đích danh một người thì đặt '
			. 'riêng ở khối <b>sửa ▾</b> của hàng người ấy: tầng đó thắng tất cả.</p>';

		if ( ! $sua ) {
			echo '<div class="bao canh">Chỉ xem — khai quyền theo nhóm cần vai Kế toán trở lên.</div>';
		}
		/* ⚠️ NÓI TRƯỚC CỘT PHẠM VI LÀM GÌ. Nó là cái SIẾT duy nhất trong khối này — mấy cột kia
		   mở/khoá một trang, còn cột này đổi CẢ TẦM NHÌN: công, lương, hồ sơ, số tài khoản. */
		echo '<p class="mo">Cột <b>Phạm vi</b> là thứ làm cho việc tách phòng theo mảng có nghĩa: '
			. 'tick vào thì người của phòng ấy <b>chỉ thấy cơ sở thuộc mảng của chính họ</b> — kể '
			. 'cả bậc Kế toán. Không tick thì từ bậc Quản lý trở lên vẫn thấy <b>mọi cơ sở</b>, và '
			. 'cái tên «KVC · Phòng Kế Toán» chỉ là cái nhãn.</p>';
		echo '<p class="mo">⚠️ Chỗ nào <b>không chắc thì mở</b>, không khoá: người chưa suy ra mảng '
			. 'nào, và cơ sở chưa ai khai mảng — đều cho qua. Siết hụt thì thấy và sửa được; siết '
			. 'oan thì âm thầm chặn việc của người ta.</p>';
		echo '<form method="post">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo '<input type="hidden" name="bo_mang_co" value="1">';
		echo self::o_loc();

		foreach ( array(
			array( 'loai' => 'bp',   'nhan' => 'Bộ phận',
				'ds' => VHCC_NhanSu::ds_bo_phan(), 'dem' => $dem['boPhan'] ),
			array( 'loai' => 'mang', 'nhan' => 'Mảng kinh doanh',
				'ds' => VHCC_NhanSu::ds_mang(),    'dem' => $dem['mang'] ),
		) as $kh ) {
			echo '<div class="cuon" style="margin-top:12px"><table class="stt b-nhom"><thead><tr><th>'
				. esc_html( $kh['nhan'] ) . '</th>';
			foreach ( $cot as $t ) {
				echo '<th class="tr-doc">' . esc_html( $t['ten'] )
					. ( 'day' === $t['kieu'] ? '<br><span class="mo" style="font-weight:400;'
						. 'font-size:10.5px">phải bấm đẩy — xem dải dưới</span>' : '' ) . '</th>';
			}
			/* 🔴 CỘT PHẠM VI CHỈ CÓ Ở BẢNG BỘ PHẬN, không có ở bảng mảng. "Người của mảng này chỉ
			   thấy mảng này" là một câu vô nghĩa — nó tự đúng. Câu có nghĩa là "người của PHÒNG
			   này chỉ thấy mảng của chính họ", và phòng mới là thứ tách đôi theo mảng. */
			if ( 'bp' === $kh['loai'] ) {
				echo '<th class="tr-doc">Phạm vi<br><span class="mo" style="font-weight:400;'
					. 'font-size:10.5px">chỉ thấy mảng của mình</span></th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $kh['ds'] as $ten ) {
				$so = isset( $kh['dem'][ $ten ] ) ? (int) $kh['dem'][ $ten ] : 0;
				/* Nhãn đọc tên hiện ra; khoá luật (`$ten`) vẫn là mã lưu — luật khoá bằng mã. */
				$nhan_n = ( 'mang' === $kh['loai'] ) ? VHCC_NhanSu::ten_mang( $ten ) : $ten;
				echo '<tr><td>' . esc_html( $nhan_n )
					. ' <span class="sl-nho" title="Số người đang thuộc nhóm này">' . $so . '</span></td>';
				foreach ( $cot as $k => $t ) {
					echo '<td class="o-q-td">'
						. self::ba_nut_nhom( $kh['loai'], $ten, $k, VHCC_Cong::o_nhom( $kh['loai'], $ten, $k ), $sua )
						. '</td>';
				}
				if ( 'bp' === $kh['loai'] ) {
					echo '<td class="o-q-td"><label class="mb-o" style="justify-content:center">'
						. '<input type="checkbox" name="bo_mang[]" value="' . esc_attr( $ten ) . '"'
						. checked( true, in_array( $ten, $bo_ds, true ), false )
						. ( $sua ? '' : ' disabled' ) . '> bó</label></td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}

		if ( $sua ) {
			echo '<div class="hang" style="margin-top:12px">'
				. '<button class="chinh" name="viec" value="luu_nhom">Lưu luật nhóm</button>'
				. '<span class="mo">Có hiệu lực ngay ở lượt đăng nhập sau — không phải đụng vào '
				. 'hồ sơ ai cả.</span></div>';
		}
		echo '</form>';

		self::dai_chenh_day( $cot, $so_do );
		echo '</div>';
	}

	/** Dải ba nút cho MỘT ô luật nhóm. Nút đầu là «—» (không khai), không phải «theo vai». */
	private static function ba_nut_nhom( $loai, $ten, $cot, $dat, $sua ) {
		$goc = 'n' . substr( md5( $loai . '|' . $ten . '|' . $cot ), 0, 10 );
		$h   = '<span class="ba">';
		$cac = array(
			''     => array( 'ten' => '—', 'lop' => 'v-vai',
				'chu' => 'Nhóm này không nói gì về cột ấy — để tầng dưới (mảng, rồi thang vai) quyết' ),
			'mo'   => array( 'ten' => 'Mở',   'lop' => 'v-mo',   'chu' => 'Cả nhóm này được vào' ),
			'khoa' => array( 'ten' => 'Khoá', 'lop' => 'v-khoa', 'chu' => 'Cả nhóm này bị chặn' ),
		);
		foreach ( $cac as $gt => $c ) {
			$id = $goc . ( '' === $gt ? 'x' : $gt );
			$h .= '<label for="' . esc_attr( $id ) . '" title="' . esc_attr( $c['chu'] ) . '">'
				. '<input type="radio" id="' . esc_attr( $id ) . '" class="' . esc_attr( $c['lop'] ) . '"'
				. ' name="nhom[' . esc_attr( $loai ) . '][' . esc_attr( $ten ) . '][' . esc_attr( $cot ) . ']"'
				. ' value="' . esc_attr( $gt ) . '"' . checked( $dat, $gt, false )
				. ( $sua ? '' : ' disabled' ) . '>'
				. '<span>' . esc_html( $c['ten'] ) . '</span></label>';
		}
		return $h . '</span>';
	}

	/**
	 * DẢI CHÊNH LỆCH — luật nói một đằng, sổ người dùng bên kia đang một nẻo.
	 *
	 * 🔴 VÌ SAO KHÔNG TỰ ĐẨY. Ghế massage và Vận hành chi phí có SỔ NGƯỜI DÙNG RIÊNG và cả hai
	 *    đều là màn có ngăn tiền. Tự tạo tài khoản cho 37 người vì ai đó vừa tích một ô là trao
	 *    chìa khoá mà chính họ cũng không biết mình đang cầm — và không có một lượt bấm nào để
	 *    quy trách nhiệm. Nên: luật KHAI ở trên, còn tạo tài khoản thật thì một nút, một lượt
	 *    bấm, đếm rõ bao nhiêu người.
	 *
	 * ⚠️ ĐẾM LẠI Ở MÁY CHỦ khi bấm, không tin danh sách gửi lên — xem `viec_ap_day()`.
	 */
	private static function dai_chenh_day( $cot, $so_do ) {
		$co = array();
		foreach ( $cot as $k => $t ) { if ( 'day' === $t['kieu'] ) { $co[ $k ] = $t['ten']; } }
		if ( ! $co ) { return; }
		$ch = self::chenh_day( $so_do );
		$co_gi = false;
		foreach ( $co as $k => $ten ) {
			if ( ! isset( $ch[ $k ] ) ) { continue; }
			if ( $ch[ $k ]['them'] || $ch[ $k ]['bo'] ) { $co_gi = true; }
		}
		if ( ! $co_gi ) {
			echo '<p class="mo" style="margin-top:12px">✓ Sổ người dùng của Ghế massage và Vận '
				. 'hành chi phí đang <b>khớp đúng luật</b> ở trên — không còn ai lệch.</p>';
			return;
		}
		echo '<form method="post" style="margin-top:12px">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
		foreach ( $co as $k => $ten ) {
			if ( ! isset( $ch[ $k ] ) ) { continue; }
			foreach ( array(
				'them' => array( 'Đẩy hết', 'chưa có tài khoản <b>' . $ten . '</b> mà luật nói NÊN có' ),
				'bo'   => array( 'Gỡ hết',  'đang có tài khoản <b>' . $ten . '</b> mà luật nói KHÔNG nên có' ),
			) as $chieu => $v ) {
				$so = count( $ch[ $k ][ $chieu ] );
				if ( ! $so ) { continue; }
				echo '<div class="bao canh"><b>' . (int) $so . ' người</b> ' . $v[1] . ' — '
					. esc_html( implode( ', ', array_slice( $ch[ $k ][ $chieu ], 0, 8 ) ) )
					. ( $so > 8 ? '…' : '' )
					. ' <button class="nut" name="viec" value="ap_day" '
					. 'style="margin-left:6px">' . esc_html( $v[0] . ' ' . $so . ' người' ) . '</button>'
					. '<input type="hidden" name="ap_day_o" value="' . esc_attr( $k . '|' . $chieu ) . '">'
					. '</div>';
			}
		}
		echo '</form>';
	}

	/**
	 * AI ĐANG LỆCH GIỮA LUẬT NHÓM VÀ SỔ NGƯỜI DÙNG BÊN KIA.
	 *
	 * @return array [ cột => [ 'them' => [mã…], 'bo' => [mã…] ] ]
	 *
	 * ⚠️ MỘT LƯỢT ĐỌC CHO CẢ SỔ, rồi tính trong bộ nhớ. Hỏi `VHCC_Cong::nhom_noi_gi()` từng người
	 *    là 245 lượt SELECT cho một dải báo.
	 */
	private static function chenh_day( $so_do ) {
		$ra = array();
		$cot = VHCC_Cong::cot_nhom();
		$day = array();
		foreach ( $cot as $k => $t ) { if ( 'day' === $t['kieu'] ) { $day[] = $k; } }
		if ( ! $day ) { return $ra; }
		$l = VHCC_Cong::luat_nhom();
		if ( ! $l['bp'] && ! $l['mang'] ) { return $ra; }

		foreach ( $day as $k ) { $ra[ $k ] = array( 'them' => array(), 'bo' => array() ); }
		foreach ( (array) $so_do as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			foreach ( $day as $k ) {
				$n = VHCC_Cong::nhom_noi_gi_hs( $r, $k );
				if ( null === $n ) { continue; }
				$da = self::da_day_cot( $k, $ma );
				if ( $n['duoc'] && ! $da )  { $ra[ $k ]['them'][] = $ma; }
				if ( ! $n['duoc'] && $da )  { $ra[ $k ]['bo'][]   = $ma; }
			}
		}
		return $ra;
	}

	/**
	 * SỔ NHÂN SỰ RÚT GỌN — đúng sáu cột mà `mang_bo_phan_cua()` cần, không hơn.
	 *
	 * ⚠️ ĐỪNG gọi `ds_nhan_vien()` cho việc này: hàm ấy trả cả hồ sơ đầy đủ (kể cả ô lương với
	 *    người có quyền xem), tức kéo 245 dòng × 30 cột về chỉ để đếm hai cột.
	 */
	private static function ds_nhom_tho() {
		return (array) VHCC_DB::rows( 'SELECT ma_nv, cua_hang, coso_phu, coso_ql, mang, bo_phan FROM '
			. VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv <> ''" );
	}

	/** Người này đã có tài khoản bên hệ của cột `$k` chưa. */
	private static function da_day_cot( $k, $ma ) {
		if ( class_exists( 'VHCC_DayGhe' ) && VHCC_DayGhe::COT === $k )       { return VHCC_DayGhe::da_day( $ma ); }
		if ( class_exists( 'VHCC_DayChiPhi' ) && VHCC_DayChiPhi::COT === $k ) { return VHCC_DayChiPhi::da_day( $ma ); }
		return false;
	}

	/** LƯU BẢNG LUẬT NHÓM. */
	private static function viec_luu_nhom( $toi ) {
		$gui = isset( $_POST['nhom'] ) ? wp_unslash( $_POST['nhom'] ) : array();
		if ( ! is_array( $gui ) ) { return array( array( 'loi' => 'Biểu mẫu không hợp lệ.' ) ); }
		$sach = array( 'bp' => array(), 'mang' => array() );
		foreach ( array( 'bp', 'mang' ) as $loai ) {
			if ( ! isset( $gui[ $loai ] ) || ! is_array( $gui[ $loai ] ) ) { continue; }
			foreach ( $gui[ $loai ] as $ten => $cac ) {
				if ( ! is_array( $cac ) ) { continue; }
				$ten_s = sanitize_text_field( (string) $ten );
				if ( '' === $ten_s ) { continue; }
				foreach ( $cac as $k => $v ) {
					$sach[ $loai ][ $ten_s ][ sanitize_key( (string) $k ) ]
						= sanitize_text_field( (string) $v );
				}
			}
		}
		$kq = VHCC_Cong::luu_nhom( $toi, $sach );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$bao = self::luu_bo_mang( $toi );
		if ( ! $kq['doi'] && ! $bao ) { return array( array( 'canh' => 'Không có ô nào đổi — chưa lưu gì.' ) ); }
		$ra = array();
		if ( $kq['doi'] ) {
			$ra[] = array( 'ok' => 'Đã lưu ' . (int) $kq['doi'] . ' ô luật nhóm. '
				. 'Có hiệu lực ngay ở lượt đăng nhập sau.' );
		}
		foreach ( $bao as $b ) { $ra[] = $b; }
		return $ra;
	}

	/**
	 * LƯU CỘT PHẠM VI.
	 *
	 * 🔴 CHỈ ĐỘNG VÀO KHI BIỂU MẪU CÓ VẼ CỘT ẤY (`bo_mang_co`). Ô tích không gửi gì khi bỏ tích,
	 *    nên "không thấy ô nào" và "biểu mẫu không có cột ấy" trông giống hệt nhau — thiếu cờ này
	 *    thì một lượt POST từ chỗ khác là gỡ sạch mọi phòng đang bó, im lặng.
	 */
	private static function luu_bo_mang( $toi ) {
		if ( empty( $_POST['bo_mang_co'] ) ) { return array(); }
		$gui = isset( $_POST['bo_mang'] ) ? (array) wp_unslash( $_POST['bo_mang'] ) : array();
		$moi = array();
		foreach ( $gui as $x ) {
			$x = sanitize_text_field( (string) $x );
			if ( '' !== $x && ! in_array( $x, $moi, true ) ) { $moi[] = $x; }
		}
		$cu  = VHCC_NhanSu::bo_mang_ds();
		$bao = array();
		foreach ( VHCC_NhanSu::ds_bo_phan() as $bp ) {
			$co_cu  = in_array( $bp, $cu, true );
			$co_moi = in_array( $bp, $moi, true );
			if ( $co_cu === $co_moi ) { continue; }
			$r = VHCC_NhanSu::dat_bo_mang( $toi, $bp, $co_moi );
			if ( empty( $r['ok'] ) ) { $bao[] = array( 'loi' => $r['error'] ); continue; }
			$bao[] = array( $co_moi ? 'canh' : 'ok', $bp );
		}
		if ( ! $bao ) { return array(); }
		$bat = array(); $tat = array();
		foreach ( $bao as $b ) {
			if ( isset( $b['loi'] ) ) { continue; }
			if ( 'canh' === $b[0] ) { $bat[] = $b[1]; } else { $tat[] = $b[1]; }
		}
		$ra = array();
		foreach ( $bao as $b ) { if ( isset( $b['loi'] ) ) { $ra[] = $b; } }
		/* Bật bó là SIẾT — báo bằng dòng vàng, và kê tên phòng ra. Dòng xanh cho một lượt siết
		   thì người bấm lướt qua, mà siết là thứ phải đọc kỹ. */
		if ( $bat ) {
			$ra[] = array( 'canh' => '⚠️ Đã BÓ phạm vi theo mảng cho: ' . implode( ' · ', $bat )
				. '. Người của mấy phòng này từ nay chỉ thấy cơ sở thuộc mảng của chính họ — '
				. 'kiểm lại xem có ai đang cần nhìn cả công ty không.' );
		}
		if ( $tat ) {
			$ra[] = array( 'ok' => 'Đã BỎ bó phạm vi cho: ' . implode( ' · ', $tat )
				. ' — họ nhìn lại theo đúng thang vai.' );
		}
		return $ra;
	}

	/**
	 * ĐẨY / GỠ HÀNG LOẠT CHO KHỚP LUẬT.
	 *
	 * 🔴 ĐẾM LẠI Ở ĐÂY, KHÔNG TIN DANH SÁCH GỬI LÊN. Biểu mẫu nằm trên một trang có thể đã mở từ
	 *    nửa tiếng trước; trong khoảng ấy luật đổi, người vào người ra. Nhận danh sách mã từ biểu
	 *    mẫu là tạo tài khoản cho một tập người của nửa tiếng trước — mà đây là hệ có ngăn tiền.
	 */
	private static function viec_ap_day( $toi ) {
		$o = isset( $_POST['ap_day_o'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_day_o'] ) ) : '';
		$x = explode( '|', $o );
		$k = isset( $x[0] ) ? $x[0] : '';
		$chieu = isset( $x[1] ) ? $x[1] : '';
		if ( ! in_array( $chieu, array( 'them', 'bo' ), true ) ) {
			return array( array( 'loi' => 'Chỉ nhận: them · bo.' ) );
		}
		$cot = VHCC_Cong::cot_nhom();
		if ( ! isset( $cot[ $k ] ) || 'day' !== $cot[ $k ]['kieu'] ) {
			return array( array( 'loi' => 'Không có cột đẩy "' . $k . '".' ) );
		}
		$ch = self::chenh_day( self::ds_nhom_tho() );
		$ds = isset( $ch[ $k ][ $chieu ] ) ? $ch[ $k ][ $chieu ] : array();
		if ( ! $ds ) { return array( array( 'canh' => 'Không còn ai lệch — chưa làm gì.' ) ); }

		$bang = array();
		foreach ( $ds as $ma ) { $bang[ $ma ] = ( 'them' === $chieu ) ? 'mo' : ''; }
		if ( class_exists( 'VHCC_DayGhe' ) && VHCC_DayGhe::COT === $k ) {
			$kq = VHCC_DayGhe::luu_nhieu( $toi, $bang );
		} elseif ( class_exists( 'VHCC_DayChiPhi' ) && VHCC_DayChiPhi::COT === $k ) {
			$kq = VHCC_DayChiPhi::luu_nhieu( $toi, $bang );
		} else {
			return array( array( 'loi' => 'Không có hệ nào nhận cột "' . $k . '".' ) );
		}
		if ( empty( $kq['ok'] ) ) {
			return array( array( 'loi' => isset( $kq['error'] ) ? $kq['error'] : 'Không đẩy được.' ) );
		}
		return array( array( 'ok' => ( 'them' === $chieu ? 'Đã đẩy ' : 'Đã gỡ ' )
			. (int) ( isset( $kq['doi'] ) ? $kq['doi'] : count( $bang ) ) . ' người ở cột '
			. $cot[ $k ]['ten'] . ' cho khớp luật nhóm.' ) );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * ĐẨY / GỠ ĐÍCH DANH MỘT NGƯỜI — KHÔNG ĐI QUA LUẬT NHÓM
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 13/09/2026: *"lúc đầu phân quyền trang theo bộ phận, mà bộ phận nó đang dính
	 * chung các nv khác, nên đã ngừng"*, *"đang có 1 nhân viên mới, cần add vào trang chi phí để
	 * nhập báo cáo, nhưng không có chỗ"*.
	 *
	 * 🔴 ĐÚNG LÀ KHÔNG CÒN CHỖ. 3.77.0 gộp năm cột quyền thành MỘT cột chỉ đọc và dời chỗ khai
	 *    xuống khối luật bộ phận & mảng. Với ba cột quyền trang thì vẫn còn đường riêng (khối
	 *    "sửa ▾" của hàng), nhưng hai cột ĐẨY NGƯỜI — Ghế và Chi phí — thì mất hẳn: đường duy
	 *    nhất còn lại là nút "Đẩy hết N người" ở dải chênh lệch, mà nút ấy đi theo luật CẢ PHÒNG.
	 *    Thêm một người vào chi phí hoá ra phải mở luật cho cả bộ phận của họ — đúng cái anh
	 *    Thắng vừa ngừng dùng vì "dính chung các nv khác".
	 *
	 * ⚠️ VÀ NÓ KHÔNG PHẢI "MỞ LẠI CỬA CŨ". Ngoại lệ đích danh vẫn là tầng HẸP NHẤT và vẫn hiện
	 *    ra ở chip (dấu ⚠ khi lệch luật nhóm) — người khai thấy ngay mình vừa đi ngược luật
	 *    phòng, chứ không phải lặng lẽ có một người nằm ngoài mọi luật.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function viec_day_mot( $toi ) {
		$o = isset( $_POST['day_1'] ) ? sanitize_text_field( wp_unslash( $_POST['day_1'] ) ) : '';
		$x = explode( '|', $o );
		$k = isset( $x[0] ) ? $x[0] : '';
		$ma = isset( $x[1] ) ? strtoupper( trim( (string) $x[1] ) ) : '';
		$chieu = isset( $x[2] ) ? $x[2] : '';
		if ( '' === $ma ) { return array( array( 'loi' => 'Thiếu Mã NV.' ) ); }
		if ( ! in_array( $chieu, array( 'mo', '' ), true ) ) {
			return array( array( 'loi' => 'Chỉ nhận: mo · (rỗng).' ) );
		}
		$cot = VHCC_Cong::cot_nhom();
		if ( ! isset( $cot[ $k ] ) || 'day' !== $cot[ $k ]['kieu'] ) {
			return array( array( 'loi' => 'Không có cột đẩy "' . $k . '".' ) );
		}
		/* ⚠️ GÁC `class_exists` CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. Bốn
		   plugin cài độc lập, bản có thể lệch nhau bất cứ lúc nào. */
		if ( class_exists( 'VHCC_DayGhe' ) && VHCC_DayGhe::COT === $k ) {
			if ( ! self::cot_ghe( $toi ) ) {
				return array( array( 'loi' => 'Không có quyền đẩy người sang hệ Ghế.' ) );
			}
			$kq = VHCC_DayGhe::dat( $toi, $ma, $chieu );
		} elseif ( class_exists( 'VHCC_DayChiPhi' ) && VHCC_DayChiPhi::COT === $k ) {
			if ( ! self::cot_chi_phi( $toi ) ) {
				return array( array( 'loi' => 'Không có quyền đẩy người sang hệ Vận hành chi phí.' ) );
			}
			$kq = VHCC_DayChiPhi::dat( $toi, $ma, $chieu );
		} else {
			return array( array( 'loi' => 'Không có hệ nào nhận cột "' . $k . '".' ) );
		}
		if ( empty( $kq['ok'] ) ) {
			return array( array( 'loi' => isset( $kq['error'] ) ? $kq['error'] : 'Không đẩy được.' ) );
		}
		if ( '' === $chieu ) {
			return array( array( 'ok' => 'Đã gỡ ' . $ma . ' khỏi ' . $cot[ $k ]['ten'] . '.' ) );
		}
		/* 🔴 ĐẨY XONG PHẢI NÓI NGƯỜI ẤY CÓ BỊ BÓ BỘ PHẬN KHÔNG. Rỗng bên chi phí nghĩa là thấy sổ
		   của MỌI mảng — nới quyền âm thầm, xem khối dài ở `VHCC_DayChiPhi::bo_phan_day()`. */
		$them = '';
		if ( class_exists( 'VHCC_DayChiPhi' ) && VHCC_DayChiPhi::COT === $k
			&& method_exists( 'VHCC_DayChiPhi', 'bo_phan_day' ) ) {
			$hs_d = VHCC_NhanSu::ho_so( $ma );
			$bp_d = $hs_d ? VHCC_DayChiPhi::bo_phan_day( $hs_d ) : '';
			$them = ( '' === $bp_d )
				? ' ⚠️ Người này CHƯA bị bó bộ phận bên ấy — họ xem được sổ chi phí của mọi mảng.'
					. ' Khai bản đồ ở khối «Đẩy sang Vận hành chi phí — bó bộ phận» bên dưới.'
				: ' Bộ phận gửi sang: «' . $bp_d . '».';
		}
		return array( array( 'ok' => 'Đã đẩy ' . $ma . ' sang ' . $cot[ $k ]['ten'] . '.' . $them ) );
	}

	/**
	 * TÊN NGẮN CỦA MỘT CỘT QUYỀN — để năm cái chip nằm vừa một ô hẹp.
	 *
	 * ⚠️ Có ĐƯỜNG LUI về tên đầy đủ. Gõ cứng cả năm tên ở đây thì thêm một trang vào `VHCC_Cong::SO`
	 *    là chip của nó trống trơn — mà sổ trang sinh ra để TỰ DÒ, không phải để gõ hai lần.
	 */
	private static function ten_gon( $khoa, $ten_day ) {
		$b = array(
			'cham_cong' => 'Chấm công',
			'tram'      => 'Trạm',
			'noi_bo'    => 'Nội bộ',
		);
		if ( class_exists( 'VHCC_DayGhe' ) )    { $b[ VHCC_DayGhe::COT ]    = 'Ghế'; }
		if ( class_exists( 'VHCC_DayChiPhi' ) ) { $b[ VHCC_DayChiPhi::COT ] = 'Chi phí'; }
		return isset( $b[ $khoa ] ) ? $b[ $khoa ] : (string) $ten_day;
	}

	/**
	 * DẢI CHIP "NGƯỜI NÀY VÀO ĐƯỢC ĐÂU, VÀ VÌ ĐÂU" — cột đọc, không phải cột khai.
	 *
	 * 🔴 CHỮ "VÌ ĐÂU" MỚI LÀ PHẦN CÓ GIÁ TRỊ. Một cái chip xanh nói "vào được" thì nhìn bảng vai
	 *    cũng đoán ra; thứ không đoán được là nó xanh VÌ luật bộ phận, VÌ luật mảng, hay VÌ ai đó
	 *    đã đặt riêng cho người này từ sáu tháng trước. Không in ra thì người đi soát phải mở ba
	 *    khối khác nhau cho mỗi hàng — tức là không ai soát.
	 *
	 * ⚠️ HỎI BẰNG CHÍNH HÀM CỦA CỬA VÀO (`VHCC_Cong::giai`). Tự tính lại thứ tự bốn tầng ở đây là
	 *    có hai bộ luật cho cùng một câu hỏi, và sớm muộn màn hình nói khác cửa vào.
	 */
	private static function dai_chip_quyen( $gia, $ma, $ds_trang, $co_ghe, $co_cp ) {
		$h = '<span class="chip-q-dai">';
		foreach ( $ds_trang as $k => $t ) {
			$g  = VHCC_Cong::giai( $gia, $k );
			$h .= self::chip_quyen( self::ten_gon( $k, $t['ten'] ), (bool) $g['duoc'],
				(string) $g['vi'], (string) $g['ten'] );
		}
		if ( $co_ghe ) {
			$h .= self::chip_day( VHCC_DayGhe::COT, 'Ghế', VHCC_DayGhe::da_day( $ma ), $ma );
		}
		if ( $co_cp ) {
			$h .= self::chip_day( VHCC_DayChiPhi::COT, 'Chi phí', VHCC_DayChiPhi::da_day( $ma ), $ma );
		}
		return $h . '</span>';
	}

	/** Một chip trang: xanh = vào được, xám = không. Chữ nhỏ phía sau nói TẦNG nào quyết. */
	private static function chip_quyen( $ten, $duoc, $vi, $nhom ) {
		$nho = '';
		$chu = ( $duoc ? 'Vào được' : 'Không vào được' ) . ' — ';
		if ( 'bo_phan' === $vi )      { $nho = 'bp';   $chu .= 'theo luật của bộ phận «' . $nhom . '»'; }
		elseif ( 'mang' === $vi )     { $nho = 'mảng'; $chu .= 'theo luật của mảng «' . $nhom . '»'; }
		elseif ( 'rieng' === $vi )    { $nho = 'riêng'; $chu .= 'ĐẶT RIÊNG cho người này — mở «sửa ▾» ở cột Họ tên để đổi hoặc gỡ'; }
		else                          { $chu .= 'theo thang vai, không ai khai riêng'; }
		return '<span class="chip-q ' . ( $duoc ? 'q-co' : 'q-khong' )
			. ( 'rieng' === $vi ? ' q-rieng' : '' ) . '" title="' . esc_attr( $chu ) . '">'
			. esc_html( $ten ) . ( '' !== $nho ? '<i>' . esc_html( $nho ) . '</i>' : '' ) . '</span>';
	}

	/**
	 * Chip của hai cột ĐẨY NGƯỜI (Ghế, Chi phí).
	 *
	 * 🔴 KHÁC HẲN chip trang: ở đây "có tài khoản" là SỰ THẬT bên hệ kia, còn luật nhóm mới chỉ
	 *    là LỜI KHAI. Hai thứ lệch nhau là chuyện bình thường và phải NÓI RA — luật vừa khai
	 *    xong thì chưa ai có tài khoản cả. Dấu ⚠ ở đây nghĩa là "còn việc phải bấm", không phải
	 *    "hỏng".
	 */
	private static function chip_day( $cot, $ten, $da_day, $ma ) {
		$n   = VHCC_Cong::nhom_noi_gi( $ma, $cot );
		$nen = ( null === $n ) ? null : (bool) $n['duoc'];
		$da  = (bool) $da_day;
		$lech = ( null !== $nen && $nen !== $da );
		$chu = $da ? 'ĐANG có tài khoản bên hệ này' : 'Chưa có tài khoản bên hệ này';
		if ( null !== $n ) {
			$chu .= ' · luật ' . ( 'bo_phan' === $n['vi'] ? 'bộ phận' : 'mảng' ) . ' «' . $n['ten'] . '» nói '
				. ( $nen ? 'NÊN có' : 'KHÔNG nên có' );
		}
		if ( $lech ) {
			$chu .= ' — còn việc phải bấm, xem dải chênh lệch ngay dưới bảng';
		}
		/* 🔴 NÚT NGAY TRÊN CHIP — đường đẩy ĐÍCH DANH một người. Xem khối dài ở viec_day_mot():
		   từ 3.77.0 hai cột đẩy này mất hẳn chỗ khai riêng, chỉ còn nút "Đẩy hết" đi theo luật
		   CẢ PHÒNG. Nút ở đây nằm trong `<form>` của bảng chính, mang tên riêng `day_1` nên chỉ
		   lượt bấm đúng nó mới có mặt trong $_POST — cùng lối `ghep_voi`/`xoa_ma` đã dùng. */
		$nut = '<button type="submit" class="chip-nut" name="day_1"'
			. ' value="' . esc_attr( $cot . '|' . $ma . '|' . ( $da ? '' : 'mo' ) ) . '"'
			. ' title="' . esc_attr( $da
				? 'Gỡ RIÊNG người này khỏi ' . $ten . ' — không đụng ai khác'
				: 'Đẩy RIÊNG người này sang ' . $ten . ' — không đụng ai khác' ) . '">'
			. ( $da ? '−' : '+' ) . '</button>';
		return '<span class="chip-q ' . ( $da ? 'q-co' : 'q-khong' ) . ( $lech ? ' q-lech' : '' )
			. '" title="' . esc_attr( $chu ) . '">' . esc_html( $ten )
			. ( $lech ? '<i>⚠</i>' : '' ) . $nut . '</span>';
	}

	/**
	 * @param array|null $nhom Luật nhóm đang quyết ô này (`VHCC_Cong::nhom_noi_gi`), hoặc null.
	 *
	 * 🔴 NÚT ĐẦU PHẢI NÓI ĐÚNG NÓ ĐANG THEO GÌ. Từ 3.77.0 "bỏ ngoại lệ" không còn nghĩa là "về
	 *    theo vai" nữa — nó về theo LUẬT BỘ PHẬN nếu bộ phận ấy có khai. Vẫn ghi "theo vai" thì
	 *    người bấm tưởng mình vừa trả người ta về thang vai, trong khi thực tế họ rơi vào luật
	 *    của cả phòng, có thể ngược hẳn.
	 */
	private static function ba_nut( $ma, $k, $dat, $mac, $nhom = null ) {
		$goc = 'q' . substr( md5( $ma . '|' . $k ), 0, 10 );
		$h   = '<span class="ba">';
		if ( null !== $nhom ) {
			$n_ten = ( 'bo_phan' === $nhom['vi'] ? 'bộ phận' : 'mảng' ) . ' «' . $nhom['ten'] . '»';
			$dau   = array( 'ten' => 'theo bộ ' . ( $mac ? '✓' : '✕' ),
				'chu' => 'Không đặt riêng — đi theo luật của ' . $n_ten . ', hiện đang '
					. ( $mac ? 'MỞ' : 'KHOÁ' ) );
		} else {
			$dau = array( 'ten' => 'vai ' . ( $mac ? '✓' : '✕' ),
				'chu' => 'Theo vai — ' . ( $mac ? 'vai hiện tại vào được' : 'vai hiện tại không vào được' ) );
		}
		$cac = array(
			''     => array( 'ten' => $dau['ten'], 'lop' => 'v-vai', 'chu' => $dau['chu'] ),
			'mo'   => array( 'ten' => 'Mở',   'lop' => 'v-mo',   'chu' => 'Mở riêng cho người này, dù luật chung chưa tới' ),
			'khoa' => array( 'ten' => 'Khoá', 'lop' => 'v-khoa', 'chu' => 'Khoá riêng người này, dù luật chung đã mở' ),
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

	/**
	 * CỘT QUẢN TRỊ BÁO CÁO CƠ SỞ — anh Thắng 15/09/2026: *"bổ sung thêm cột bên trang nhân sự để
	 * cấp quyền đẩy sang"*, vì *"bên đó là các cửa hàng trưởng được quyền nhập báo cáo"*.
	 *
	 * Cùng cơ chế với cột Ghế và cột Chi phí: ĐẨY NGƯỜI THẬT kèm PIN sang sổ người dùng của màn
	 * báo cáo, chứ không ghi một ngoại lệ vào sổ quyền — màn ấy có phiên riêng.
	 */
	private static function cot_bao_cao( $toi ) {
		return VHCC_DayBaoCao::co_he_bao_cao() && VHCC_Vai::duoc( $toi, VHCC_DayBaoCao::QUYEN );
	}

	/** HAI nút, y như cột Ghế và cột Chi phí: có mặt bên ấy, hoặc không. */
	private static function hai_nut_bao_cao( $ma ) {
		$dat = VHCC_DayBaoCao::o( $ma );
		$goc = 'b' . substr( md5( $ma . '|baocao' ), 0, 10 );
		$h   = '<span class="ba">';
		$cac = array(
			'mo' => array( 'ten' => 'Đẩy ✓', 'lop' => 'v-mo',
				'chu' => 'Được nhập báo cáo ngày của cơ sở mình ở màn Quản trị báo cáo cơ sở — '
					. 'đăng nhập bằng chính PIN chấm công' ),
			''   => array( 'ten' => 'Gỡ', 'lop' => 'v-khoa',
				'chu' => 'Không có trong sổ người dùng của màn Quản trị báo cáo cơ sở' ),
		);
		foreach ( $cac as $gt => $c ) {
			$id = $goc . ( '' === $gt ? 'g' : $gt );
			$h .= '<label for="' . esc_attr( $id ) . '" title="' . esc_attr( $c['chu'] ) . '">'
				. '<input type="radio" id="' . esc_attr( $id ) . '" class="' . esc_attr( $c['lop'] ) . '"'
				. ' name="o[' . esc_attr( $ma ) . '][' . esc_attr( VHCC_DayBaoCao::COT ) . ']"'
				. ' value="' . esc_attr( $gt ) . '"' . checked( $dat, $gt, false ) . '>'
				. '<span>' . esc_html( $c['ten'] ) . '</span></label>';
		}
		return $h . '</span>';
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


	/**
	 * DẢI ĐẾM THEO MẢNG & BỘ PHẬN — bản ngang của thanh bên trong ảnh anh Thắng gửi 13/09/2026.
	 *
	 * Mỗi ô là một ĐƯỜNG LỌC, không phải một con số để ngắm: bấm "Phòng Kỹ Thuật 4" là bảng dưới
	 * còn đúng bốn người ấy. Đó mới là "điều động dễ hơn" — thấy nhóm, lọc ra nhóm, áp cả cột.
	 *
	 * ⚠️ SỐ NÀY ĐẾM TRÊN PHẠM VI NGƯỜI ĐANG XEM ĐƯỢC XEM, không phải cả sổ — xem
	 *    `VHCC_NhanSu::dem_mang_bo_phan()`. Cửa hàng trưởng đọc được tổng của cả chuỗi thì dải
	 *    này thành một chỗ rò đúng thứ mà bảng bên dưới đang giấu.
	 */
	private static function dai_mang_bp( $dem, $mang_dang, $bp_dang ) {
		if ( empty( $dem['mang'] ) && empty( $dem['boPhan'] ) ) { return; }
		echo '<div class="dai-mb">';
		foreach ( array(
			array( 'ten' => 'Mảng kinh doanh', 'o' => 'nmang', 'ds' => $dem['mang'], 'dang' => $mang_dang ),
			array( 'ten' => 'Bộ phận', 'o' => 'nbp', 'ds' => $dem['boPhan'], 'dang' => $bp_dang ),
		) as $k ) {
			if ( ! $k['ds'] ) { continue; }
			echo '<div class="dai-hang"><b>' . esc_html( $k['ten'] ) . '</b>';
			foreach ( $k['ds'] as $ten => $so ) {
				/* Ô "— chưa xếp —" của phép đếm và giá trị "(chưa xếp)" của ô lọc là HAI chuỗi
				   khác nhau: một cái để đọc, một cái để so. Dùng lẫn là bấm vào không ra gì. */
				$gt  = ( '— chưa xếp —' === $ten ) ? '(chưa xếp)' : $ten;
				$dang = ( (string) $k['dang'] === (string) $gt );
				$u = add_query_arg( array( $k['o'] => $dang ? '' : $gt, 'np' => 1 ), self::url_hien() );
				/* ⚠️ NHÃN đọc tên hiện ra; `$gt` (giá trị lọc) vẫn là MÃ LƯU. Đổi `$gt` sang tên
				   dài là bấm vào ô lọc ra 0 người — mà bảng thì vẫn đầy. */
				$nhan = ( 'nmang' === $k['o'] ) ? VHCC_NhanSu::ten_mang( $ten ) : $ten;
				echo '<a class="nut' . ( $dang ? ' chinh' : '' ) . '" href="' . esc_url( $u ) . '"'
					. ( $dang ? ' title="Đang lọc theo ô này — bấm lần nữa để bỏ lọc"' : '' ) . '>'
					. esc_html( $nhan ) . ' <b>' . (int) $so . '</b></a>';
			}
			echo '</div>';
		}
		if ( ! empty( $dem['theoCoSo'] ) ) {
			echo '<p class="mo">' . (int) $dem['theoCoSo'] . ' người đang <b>trôi theo cơ sở</b> — '
				. 'chưa ai xếp tay, hệ suy mảng từ <b>mọi cơ sở người ấy làm</b> (bỏ cơ sở «chỉ QL») '
				. 'và xếp vào «' . esc_html( VHCC_NhanSu::BP_CO_SO ) . '». Đó là trạng thái ĐÚNG cho '
				. 'nhân viên quầy — rê chuột vào ô Mảng để đọc nó suy từ cơ sở nào.</p>';
		}
		/* Người làm hai mảng có mặt ở CẢ HAI ô, nên tổng của dải lớn hơn số người. Nói ra, kẻo
		   người đọc cộng lại thấy lệch rồi tưởng số sai. */
		if ( ! empty( $dem['nhieuMang'] ) ) {
			echo '<p class="mo">' . (int) $dem['nhieuMang'] . ' người làm <b>từ hai mảng trở lên</b> — '
				. 'họ được đếm ở từng mảng, nên cộng dải lại sẽ lớn hơn số người. Đó là đúng: '
				. 'làm hai mảng thì chấm công ở hai mảng.</p>';
		}
		/* 🔴 CON SỐ THẬT SỰ LÀ VIỆC PHẢI LÀM — và chỉ còn ĐÚNG một cảnh: không suy ra nổi mảng
		   nào (chưa gắn cơ sở, hoặc cơ sở chưa ai khai mảng). Thuộc NHIỀU mảng đã bỏ khỏi đây từ
		   3.69.0: đó là trạng thái hợp lệ của nhân viên chạy giữa hai mảng, gắn cờ cho họ là gắn
		   cờ cho phần lớn sổ, và một danh sách việc dài bằng cả công ty thì chỉ là nhiễu. */
		if ( ! empty( $dem['canChonTay'] ) ) {
			$u_tay = add_query_arg( array( 'nmang' => '(chưa xếp)', 'np' => 1 ), self::url_hien() );
			echo '<p class="bao canh" style="margin:6px 0 0">⚠️ <b>' . (int) $dem['canChonTay']
				. ' người hệ KHÔNG suy ra mảng</b> — chưa gắn cơ sở, hoặc cơ sở của họ chưa ai khai '
				. 'mảng. Đây là danh sách việc: <a href="' . esc_url( $u_tay ) . '">xem '
				. (int) $dem['canChonTay'] . ' người này</a>.</p>';
		}
		echo '</div>';
	}


	/**
	 * DẢI ĐẾM THEO VAI TRÒ — và tô đỏ vai nào ĐANG BỊ CHỐI Ở CỬA.
	 *
	 * 🔴 Từ lúc cổng PIN đọc thẳng hồ sơ (nguồn `ho_so`), cột Vai trò quyết định ai vào được.
	 *    Một hồ sơ mang chuỗi vai không có trong danh sách được vào là người đó đăng nhập không
	 *    được — mà màn hình chỉ nói "PIN không đúng hoặc chưa được cấp", nên họ đổ cho cái PIN.
	 *    Dải này là chỗ duy nhất nhìn ra chuyện đó trước khi có người gọi điện than.
	 *
	 * ⚠️ Mỗi ô là một ĐƯỜNG LỌC theo TÊN VAI THẬT, không phải theo bậc — xem chốt hai kiểu giá
	 *    trị ở `the_bang()`.
	 */
	private static function dai_vai( $dem, $dang ) {
		if ( empty( $dem['vai'] ) ) { return; }
		echo '<div class="dai-mb"><div class="dai-hang"><b>Vai trò</b>';
		foreach ( $dem['vai'] as $ten => $x ) {
			$dg = ( (string) $dang === (string) $ten );
			$u  = add_query_arg( array( 'nvai' => $dg ? '' : $ten, 'np' => 1 ), self::url_hien() );
			echo '<a class="nut' . ( $dg ? ' chinh' : '' ) . ( empty( $x['vao'] ) ? ' vai-chan' : '' ) . '"'
				. ' href="' . esc_url( $u ) . '"'
				. ( empty( $x['vao'] )
					? ' title="Vai này KHÔNG có trong danh sách được vào cổng — người mang nó đăng nhập không được"'
					: ( $dg ? ' title="Đang lọc theo ô này — bấm lần nữa để bỏ lọc"' : '' ) ) . '>'
				. ( empty( $x['vao'] ) ? '⛔ ' : '' ) . esc_html( $ten ) . ' <b>' . (int) $x['so'] . '</b></a>';
		}
		echo '</div>';
		if ( ! empty( $dem['chan'] ) ) {
			echo '<p class="bao canh" style="margin:6px 0 0">⛔ <b>' . (int) $dem['chan']
				. ' người mang vai KHÔNG vào được cổng</b> — hồ sơ ghi một tên vai mà hệ không có. '
				. 'Họ gõ đúng PIN vẫn bị chối, và màn hình chỉ nói "PIN không đúng" nên không ai '
				. 'đoán ra. Bấm ô ⛔ ở trên để xem họ là ai, rồi đổi sang một vai có thật — hoặc '
				. 'khai thêm vai ấy ở khối <b>Bảng vai trò</b> bên dưới.</p>';
		}
		echo '</div>';
	}

	private static function o_tim( $toi, $cs, $q, $vai, $mang = '', $nbp = '' ) {
		/* 🔴 08/09/2026 — ĐÃ BỎ nút "➕ Thêm nhân sự" (và câu giải thích dài kèm nó).
		   Anh Thắng: *"loại bỏ chỗ này tránh nhầm"*.
		   Nút ấy chỉ là một ĐƯỜNG DẪN sang biểu mẫu ở màn *Hồ sơ & tài khoản*, nhưng đặt ở đây
		   thì nó lại làm trang này trông như một cửa thêm người thứ hai — đúng cái rối mà đợt
		   3.42.0 đang gỡ. Trang này làm MỘT việc: khai ai vào được trang nào.
		   ⚠️ ĐỪNG DỰNG LẠI. Muốn thêm người thì đi màn *Hồ sơ & tài khoản* — ở đó thẻ
		      "➕ Tạo nhân sự mới" đứng ngay đầu màn, không phải đi tìm. */

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
		foreach ( array(
			array( 'o' => 'nmang', 'ten' => 'Mảng kinh doanh', 'gt' => $mang, 'ds' => VHCC_NhanSu::ds_mang() ),
			array( 'o' => 'nbp', 'ten' => 'Bộ phận', 'gt' => $nbp, 'ds' => VHCC_NhanSu::ds_bo_phan() ),
		) as $c_l ) {
			echo '<div><label>' . esc_html( $c_l['ten'] ) . '</label><select name="' . esc_attr( $c_l['o'] ) . '">'
				. '<option value="">— tất cả —</option>';
			foreach ( $c_l['ds'] as $t_l ) {
				/* Nhãn đọc tên hiện ra, giá trị gửi lên vẫn là mã lưu — bộ lọc so bằng mã. */
				echo '<option value="' . esc_attr( $t_l ) . '"' . selected( $c_l['gt'], $t_l, false ) . '>'
					. esc_html( 'nmang' === $c_l['o'] ? VHCC_NhanSu::ten_mang( $t_l ) : $t_l ) . '</option>';
			}
			echo '<option value="(chưa xếp)"' . selected( $c_l['gt'], '(chưa xếp)', false )
				. '>(chưa xếp)</option></select></div>';
		}
		echo '<div><label>Tìm tên / mã</label>'
			. '<input type="text" name="nq" value="' . esc_attr( $q ) . '" placeholder="tên, mã NV, SĐT"></div>';
		echo '<button class="chinh">Lọc</button>';
		if ( '' !== $cs || '' !== $q || '' !== $vai || '' !== $mang || '' !== $nbp ) {
			echo '<a class="nut" href="' . esc_url( self::url() ) . '">Bỏ lọc</a>';
		}
		echo '</form>';
	}

	/** Lưu danh sách vai của MỘT bộ phận. Bỏ tích hết = bộ phận ấy thôi gợi ý. */
	private static function viec_vai_bp( $toi ) {
		$bp = isset( $_POST['vbp_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['vbp_ten'] ) ) : '';
		$ds = isset( $_POST['vbp_vai'] ) ? (array) wp_unslash( $_POST['vbp_vai'] ) : array();
		$sach = array();
		foreach ( $ds as $t ) { $sach[] = sanitize_text_field( (string) $t ); }
		$kq = VHCC_NhanSu::dat_vai_bo_phan( $toi, $bp, $sach );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => $kq['so']
			? ( 'Đã khai ' . (int) $kq['so'] . ' vai cho bộ phận "' . $bp . '" — mấy vai này sẽ '
				. 'được bày LÊN ĐẦU ô Vai trò của người thuộc bộ phận ấy. Vai khác vẫn chọn được '
				. 'bình thường, chỉ nằm ở nhóm dưới.' )
			: ( 'Đã bỏ khai vai cho bộ phận "' . $bp . '" — ô Vai trò của họ trở lại như cũ.' ) ) );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * ĐẨY SANG VẬN HÀNH CHI PHÍ — BÓ BỘ PHẬN CHO ĐÚNG
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Xem khối chú thích dài ở `VHCC_DayChiPhi::bo_phan_day()`. Tóm tắt: ô Chức vụ của hồ sơ
	 * chấm công trước giờ được gửi thẳng sang cột Bộ phận bên chi phí mà không kiểm gì, và bên
	 * ấy quy mọi tên lạ về "không bó bộ phận" — tức NHÌN THẤY SỔ CỦA MỌI MẢNG.
	 *
	 * 🔴 KHỐI NÀY KHÔNG PHẢI ĐỂ KHAI CHO ĐẸP. Nó là chỗ DUY NHẤT nói ra ai đang không bị bó —
	 *    con số ấy trước bản này không hiện ở đâu, cả hai bên.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function the_bo_chi_phi( $toi ) {
		if ( ! self::cot_chi_phi( $toi ) ) { return; }
		$ds_bp = VHCC_DayChiPhi::ds_bo_phan_chi_phi();
		if ( ! $ds_bp ) { return; }
		$bd  = VHCC_DayChiPhi::ban_do();
		$ho  = VHCC_DayChiPhi::da_day_ds();

		/* Ai đang có tài khoản bên ấy mà không bó bộ phận. Đọc một lượt, tính trong bộ nhớ. */
		$khong_bo = array();
		foreach ( (array) $ho as $ma_h ) {
			$ma_h = trim( (string) $ma_h );
			if ( '' === $ma_h ) { continue; }
			$hs_h = VHCC_NhanSu::ho_so( $ma_h );
			if ( ! $hs_h ) { continue; }
			if ( '' === VHCC_DayChiPhi::bo_phan_day( $hs_h ) ) { $khong_bo[] = $ma_h; }
		}

		echo '<div class="the"><details' . ( $khong_bo ? ' open' : '' ) . '>';
		echo '<summary><b>Đẩy sang Vận hành chi phí — bó bộ phận</b> — '
			. ( $khong_bo ? '<span class="chua-ma">' . count( $khong_bo ) . ' người chưa bị bó</span>'
				: 'mọi người đều đã bó' ) . '</summary>';
		echo '<p class="mo">App chi phí bó quyền theo <b>bộ phận của nó</b> (' 
			. esc_html( implode( ' · ', $ds_bp ) ) . '). Tên nào nó không nhận ra thì nó hiểu là '
			. '<b>không bó gì</b> — người ấy xem được sổ chi phí của <b>mọi mảng</b>.</p>';
		echo '<p class="mo">⚠️ Đặt tên phòng ban theo mảng («KVC · Phòng Kế Toán») là sinh ra đúng '
			. 'mấy cái tên bên kia không biết. Khai bản đồ dưới đây một lần thì mảng nào cũng gửi '
			. 'sang đúng bộ phận, khỏi phụ thuộc ô Chức vụ gõ tay.</p>';

		echo '<div class="cuon"><table class="stt"><thead><tr><th>Mảng (bên chấm công)</th>'
			. '<th>Gửi sang bộ phận (bên chi phí)</th></tr></thead><tbody>';
		foreach ( VHCC_NhanSu::ds_mang_tat_ca() as $m ) {
			$dang = isset( $bd[ $m ] ) ? $bd[ $m ] : '';
			echo '<tr><td><b>' . esc_html( VHCC_NhanSu::ten_mang( $m ) ) . '</b>'
				. ( VHCC_NhanSu::ten_mang( $m ) !== $m
					? ' <span class="mo"><code>' . esc_html( $m ) . '</code></span>' : '' ) . '</td>';
			echo '<td><form method="post" class="hang" style="gap:6px;margin:0">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
			echo '<input type="hidden" name="bd_mang" value="' . esc_attr( $m ) . '">';
			echo '<select name="bd_bp" class="o-q-vai"><option value="">— chưa khai —</option>';
			foreach ( $ds_bp as $b ) {
				echo '<option value="' . esc_attr( $b ) . '"' . selected( $b, $dang, false ) . '>'
					. esc_html( $b ) . '</option>';
			}
			echo '</select><button name="viec" value="bd_chi_phi">Lưu</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * BẢNG THỨ HAI — PHÒNG BAN, KHÔNG PHẢI MẢNG
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 BẢNG MẢNG Ở TRÊN KHÔNG ĐỦ, và thiếu bảng này là lý do chính khiến người ta không bị
		 *    bó. Xem khối dài ở `VHCC_DayChiPhi::ban_do_bp()`: mảng là hạt to (*Khu Vui Chơi*),
		 *    một mảng chứa cả Kế toán lẫn Kỹ thuật — ép chung một bộ phận chi phí là bó SAI.
		 *    Phòng ban mới là hạt vừa, và nó là ô mà hệ nhân sự thật sự quyết.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$bd_bp  = VHCC_DayChiPhi::ban_do_bp();
		$ds_pbn = VHCC_NhanSu::ds_bo_phan();
		if ( $ds_pbn ) {
			echo '<p class="mo" style="margin-top:14px"><b>Phòng ban → bộ phận chi phí.</b> '
				. 'Hạt nhỏ hơn bảng mảng ở trên và <b>được tra trước</b> — anh Thắng 13/09/2026: '
				. '<i>"quyết định bộ phận do nhân sự quyết định, bên chi phí chỉ biết bộ phận đó '
				. 'có được quyền không thôi"</i>. Tên nào trùng sẵn một bộ phận bên kia thì khỏi '
				. 'khai, nó tự khớp.</p>';
			echo '<div class="cuon"><table class="stt"><thead><tr><th>Phòng ban (bên nhân sự)</th>'
				. '<th>Gửi sang bộ phận (bên chi phí)</th></tr></thead><tbody>';
			foreach ( $ds_pbn as $pbn ) {
				$tu_khop = VHCC_DayChiPhi::bo_phan_hop_le( $pbn );
				$dang_bp = isset( $bd_bp[ $pbn ] ) ? $bd_bp[ $pbn ] : '';
				echo '<tr><td><b>' . esc_html( $pbn ) . '</b>'
					. ( '' !== $tu_khop
						? ' <span class="mo">✓ bên kia hiểu sẵn «' . esc_html( $tu_khop ) . '»</span>'
						: '' ) . '</td>';
				echo '<td><form method="post" class="hang" style="gap:6px;margin:0">';
				echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
				echo '<input type="hidden" name="bdbp_ten" value="' . esc_attr( $pbn ) . '">';
				echo '<select name="bdbp_bp" class="o-q-vai"><option value="">'
					. ( '' !== $tu_khop ? '— tự khớp —' : '— chưa khai —' ) . '</option>';
				foreach ( $ds_bp as $b ) {
					echo '<option value="' . esc_attr( $b ) . '"' . selected( $b, $dang_bp, false ) . '>'
						. esc_html( $b ) . '</option>';
				}
				echo '</select><button name="viec" value="bdbp_chi_phi">Lưu</button>';
				echo '</form></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		if ( $khong_bo ) {
			echo '<div class="bao canh" style="margin-top:10px"><b>' . count( $khong_bo )
				. ' người đang có tài khoản Vận hành chi phí mà KHÔNG bị bó bộ phận</b> — họ xem '
				. 'được sổ chi phí của mọi mảng. Khai bản đồ ở trên cho mảng của họ, hoặc sửa ô '
				. '<b>Chức vụ</b> trong hồ sơ thành đúng một tên bên kia hiểu.<br>'
				. esc_html( implode( ', ', array_slice( $khong_bo, 0, 12 ) ) )
				. ( count( $khong_bo ) > 12 ? '…' : '' ) . '</div>';
		} else {
			echo '<p class="mo" style="margin-top:10px">✓ Mọi người đang đẩy sang chi phí đều gửi '
				. 'kèm một bộ phận bên ấy hiểu — không ai nhìn quá phần của mình.</p>';
		}
		echo '</details></div>';
	}

	private static function viec_bd_chi_phi( $toi ) {
		$m  = isset( $_POST['bd_mang'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_mang'] ) ) : '';
		$b  = isset( $_POST['bd_bp'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_bp'] ) ) : '';
		$kq = VHCC_DayChiPhi::dat_ban_do( $toi, $m, $b );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		/* 🔴 KHAI XONG CHƯA ĂN NGAY — sổ bên kia vẫn giữ bộ phận cũ cho tới lượt đồng bộ. Không
		   nói ra thì người khai đóng trang, tin là xong, và mấy người kia vẫn nhìn thấy mọi mảng. */
		$so = 0;
		foreach ( (array) VHCC_DayChiPhi::da_day_ds() as $ma_d ) {
			if ( VHCC_DayChiPhi::dong_bo( $ma_d ) ) { $so++; }
		}
		return array( array( 'ok' => ( '' === $b
			? 'Đã bỏ khai bản đồ cho mảng «' . $m . '».'
			: 'Mảng «' . $m . '» nay gửi sang chi phí là bộ phận «' . $b . '».' )
			. ( $so ? ' Đã cập nhật lại ' . $so . ' tài khoản bên ấy cho khớp.' : '' ) ) );
	}

	/** Cùng lối với viec_bd_chi_phi(), nhưng cho bản đồ PHÒNG BAN — hạt nhỏ hơn, tra trước. */
	private static function viec_bdbp_chi_phi( $toi ) {
		$n  = isset( $_POST['bdbp_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['bdbp_ten'] ) ) : '';
		$b  = isset( $_POST['bdbp_bp'] ) ? sanitize_text_field( wp_unslash( $_POST['bdbp_bp'] ) ) : '';
		$kq = VHCC_DayChiPhi::dat_ban_do_bp( $toi, $n, $b );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		/* 🔴 KHAI XONG CHƯA ĂN NGAY — cùng cái bẫy với bản đồ mảng: sổ bên kia giữ bộ phận cũ cho
		   tới lượt đồng bộ, mà người khai thì đóng trang và tin là xong. */
		$so = 0;
		foreach ( (array) VHCC_DayChiPhi::da_day_ds() as $ma_d ) {
			if ( VHCC_DayChiPhi::dong_bo( $ma_d ) ) { $so++; }
		}
		return array( array( 'ok' => ( '' === $b
			? 'Đã bỏ khai bản đồ cho phòng ban «' . $n . '».'
			: 'Phòng ban «' . $n . '» nay gửi sang chi phí là bộ phận «' . $b . '».' )
			. ( $so ? ' Đã cập nhật lại ' . $so . ' tài khoản bên ấy cho khớp.' : '' ) ) );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * SƠ ĐỒ TỔ CHỨC — sửa được ngay trên màn, không phải chờ một bản cập nhật
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 13/09/2026: *"cơ cấu vai trò phòng ban nó đang sai"*.
	 *
	 * Và cái sai lớn nhất không phải mấy cái tên — là **không có chỗ nào sửa chúng**. Danh sách
	 * phòng ban gieo một lần vào option rồi nằm đó; thấy sai thì phải nhắn cho người viết mã và
	 * chờ, cho một việc lẽ ra là gõ lại một cái tên.
	 *
	 * ⚠️ ĐỔI TÊN MANG THEO CẢ BA SỔ bám vào cái tên ấy (người · vai bày lên đầu · luật quyền) —
	 *    xem `VHCC_NhanSu::doi_ten_bo_phan()`. Riêng GỘP và XOÁ cần Admin: chúng xoá hẳn một
	 *    phòng và kéo luật quyền của phòng ấy đi theo.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	private static function the_so_do( $toi ) {
		if ( ! VHCC_NhanSu::co_sua_ho_so( $toi ) ) { return; }
		$ds    = VHCC_NhanSu::ds_bo_phan();
		$dem   = VHCC_NhanSu::dem_khai_bo_phan();
		$admin = VHCC_Vai::duoc( $toi, 'he_thong' );

		echo '<div class="the"><details open><summary><b>Sơ đồ tổ chức — mảng kinh doanh &amp; '
			. 'phòng ban</b> — ' . count( $ds ) . ' phòng</summary>';

		/* ---- 1. MẢNG KINH DOANH ---- */
		echo '<h3 style="margin:12px 0 4px;font-size:14px">Mảng kinh doanh</h3>';
		/* 🔴 NÓI THẲNG VÌ SAO CHỈ ĐỔI ĐƯỢC TÊN HIỆN RA. Không nói thì người khai gõ tên dài vào,
		   thấy màn hình đổi, và tin rằng giá trị lưu cũng đổi theo — rồi đi tìm nó ở chỗ khác. */
		echo '<p class="mo">Đổi được <b>tên hiện ra</b>. Mã lưu bên dưới thì <b>giữ nguyên</b>: '
			. 'lương, chi phí và dự án đều khớp đúng chuỗi ấy, đổi đi là lương cả mảng rơi về '
			. '«Chưa xếp» mà không có gì báo.</p>';
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Mã lưu</th>'
			. '<th>Tên hiện ra</th></tr></thead><tbody>';
		$an_ds = VHCC_NhanSu::mang_an();
		foreach ( VHCC_NhanSu::ds_mang_tat_ca() as $m ) {
			$dang_an = in_array( $m, $an_ds, true );
			echo '<tr' . ( $dang_an ? ' class="mang-an"' : '' ) . '><td><code>' . esc_html( $m ) . '</code>'
				/* ⚠️ Lớp RIÊNG, không mượn `chip-t`. Lớp ấy nghĩa là "trùng tên", và bộ thử canh
				   đúng chuỗi `class="chip-t"` để bắt cảnh báo trùng tên báo oan — mượn nó ở đây
				   là làm hỏng một cái chốt ở chỗ khác. */
				. ( $dang_an ? ' <span class="chip-an">đang ẩn</span>' : '' ) . '</td>';
			echo '<td><form method="post" class="hang" style="gap:6px;margin:0">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
			echo '<input type="hidden" name="mang_ma" value="' . esc_attr( $m ) . '">';
			echo '<input type="text" name="mang_ten" value="' . esc_attr( VHCC_NhanSu::ten_mang( $m ) )
				. '" style="min-width:230px">';
			echo '<button name="viec" value="ten_mang">Lưu tên</button>';
			/* 🔴 ẨN, KHÔNG XOÁ. Anh Thắng 13/09/2026: *"bỏ 2 cái dưới cùng cho anh"*. Bỏ hẳn một
			   mảng khỏi `VHCC_Luong::BP_DS` là cơ sở nào đang xếp vào đó rơi về "Chưa xếp", tức
			   mất công thức lương — im lặng. Ẩn thì nó biến khỏi mọi ô chọn, còn lương vẫn tra
			   ra như cũ, và bấm một cái là hiện lại. */
			echo '<button name="viec" value="' . ( $dang_an ? 'mang_hien' : 'mang_an' ) . '" title="'
				. esc_attr( $dang_an
					? 'Cho mảng này hiện lại ở các ô chọn'
					: 'Giấu mảng này khỏi các ô chọn — lương và dữ liệu cũ KHÔNG đụng gì' ) . '">'
				. ( $dang_an ? 'Hiện lại' : 'Ẩn' ) . '</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';

		/* ---- 2. PHÒNG BAN, XẾP THEO MẢNG ---- */
		echo '<h3 style="margin:16px 0 4px;font-size:14px">Phòng ban</h3>';
		echo '<p class="mo">Đổi tên ở đây thì <b>người, vai bày lên đầu và luật quyền</b> của phòng '
			. 'ấy đi theo — không rơi mất thứ nào. <b>Gộp</b> là nhập hai phòng làm một (người của '
			. 'phòng bị gộp chuyển sang hết); <b>Xoá</b> chỉ làm được khi phòng không còn ai khai tay.</p>';
		if ( ! $admin ) {
			echo '<p class="mo">Vai hiện tại đổi tên và thêm được; <b>gộp</b> và <b>xoá</b> cần Admin.</p>';
		}
		/* 🔴 KHÔNG CÓ CỘT "THUỘC MẢNG". Anh Thắng 13/09/2026: *"Bỏ mảng luôn… anh tạo phòng ban
		   theo mảng đó luôn cho gọn"* — tức là tên phòng tự nói nó thuộc mảng nào, khỏi cần thêm
		   một ô xổ nữa cho mỗi hàng. Một cột 12 ô xổ chỉ để nhắc lại điều cái tên đã nói là công
		   khai gấp đôi, và hai chỗ ấy lệch nhau lúc nào không ai biết. */
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Phòng ban</th>'
			. '<th>Đổi tên</th><th>Gộp / Xoá</th></tr></thead><tbody>';
		foreach ( $ds as $bp ) {
			$so = isset( $dem[ $bp ] ) ? (int) $dem[ $bp ] : 0;
			echo '<tr><td><b>' . esc_html( $bp ) . '</b>'
				. ' <span class="sl-nho" title="Số người ĐANG KHAI TAY phòng này">' . $so . '</span></td>';
			echo '<td><form method="post" class="hang" style="gap:6px;margin:0">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
			echo '<input type="hidden" name="bp_cu" value="' . esc_attr( $bp ) . '">';
			echo '<input type="text" name="bp_moi" value="' . esc_attr( $bp ) . '" style="min-width:190px">';
			echo '<button name="viec" value="bp_doi_ten">Đổi tên</button>';
			echo '</form></td>';
			echo '<td>';
			if ( $admin ) {
				echo '<form method="post" class="hang" style="gap:6px;margin:0">';
				echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
				echo '<input type="hidden" name="bp_cu" value="' . esc_attr( $bp ) . '">';
				echo '<select name="bp_vao"><option value="">— gộp vào phòng —</option>';
				foreach ( $ds as $d ) {
					if ( $d === $bp ) { continue; }
					echo '<option value="' . esc_attr( $d ) . '">' . esc_html( $d ) . '</option>';
				}
				echo '</select>';
				echo '<button name="viec" value="bp_gop" title="Nhập phòng này vào phòng vừa chọn — '
					. 'người, vai và luật quyền chuyển sang hết, rồi phòng này biến mất">Gộp</button>';
				/* ⚠️ Nút Xoá KHÔNG hiện khi còn người — nút bấm vào chỉ nhận câu chối thì tệ hơn
				   là không có nút, và câu chối ấy còn dạy sai: trông như hệ hỏng chứ không phải
				   như một cái chốt. */
				if ( ! $so && VHCC_NhanSu::BP_CO_SO !== $bp ) {
					echo '<button class="nut xoa-hs" name="viec" value="bp_xoa" '
						. 'title="Xoá hẳn phòng này khỏi sơ đồ">Xoá</button>';
				} elseif ( $so ) {
					echo '<span class="mo" style="font-size:11.5px">còn ' . $so . ' người — gộp, đừng xoá</span>';
				}
				echo '</form>';
			} else {
				echo '<span class="mo">—</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<form method="post" class="hang" style="gap:6px;margin-top:10px">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo '<input type="text" name="bp_ten" placeholder="Tên phòng ban mới — đặt kèm mảng cho gọn, '
			. 'ví dụ «MTĐ · Phòng Kỹ Thuật»" style="min-width:330px">';
		echo '<button class="chinh" name="viec" value="bp_them">Thêm phòng ban</button>';
		echo '</form>';
		echo '</details></div>';
	}

	private static function viec_ten_mang( $toi ) {
		$m  = isset( $_POST['mang_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['mang_ma'] ) ) : '';
		$t  = isset( $_POST['mang_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['mang_ten'] ) ) : '';
		$kq = VHCC_NhanSu::dat_ten_mang( $toi, $m, $t );
		if ( empty( $kq['ok'] ) ) { return array( array( 'canh' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Mảng «' . $m . '» nay hiện ra là «' . $kq['ten'] . '». '
			. 'Mã lưu giữ nguyên — lương và chi phí không đụng gì.' ) );
	}

	private static function viec_mang_an( $toi, $an ) {
		$m  = isset( $_POST['mang_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['mang_ma'] ) ) : '';
		$kq = VHCC_NhanSu::dat_mang_an( $toi, $m, $an );
		if ( empty( $kq['ok'] ) ) { return array( array( 'canh' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Mảng «' . $m . '» ' . ( $an
			? 'đã ẩn khỏi các ô chọn — lương và dữ liệu cũ không đụng gì.'
			: 'đã hiện lại ở các ô chọn.' ) ) );
	}

	private static function viec_bp_them( $toi ) {
		$ten = isset( $_POST['bp_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['bp_ten'] ) ) : '';
		$kq  = VHCC_NhanSu::them_bo_phan( $toi, $ten );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã thêm phòng ban "' . $kq['ten'] . '". '
			. 'Khai vai và luật quyền cho nó ở hai khối ngay trên.' ) );
	}

	private static function viec_bp_doi_ten( $toi ) {
		return self::doi_ten_bp( $toi,
			isset( $_POST['bp_moi'] ) ? sanitize_text_field( wp_unslash( $_POST['bp_moi'] ) ) : '', false );
	}

	private static function viec_bp_gop( $toi ) {
		$vao = isset( $_POST['bp_vao'] ) ? sanitize_text_field( wp_unslash( $_POST['bp_vao'] ) ) : '';
		if ( '' === $vao ) { return array( array( 'canh' => 'Chưa chọn phòng để gộp vào.' ) ); }
		return self::doi_ten_bp( $toi, $vao, true );
	}

	/** Chung cho đổi tên và gộp — cùng một việc ở lõi, khác nhau đúng một ý định. */
	private static function doi_ten_bp( $toi, $moi, $gop ) {
		$cu = isset( $_POST['bp_cu'] ) ? sanitize_text_field( wp_unslash( $_POST['bp_cu'] ) ) : '';
		$kq = VHCC_NhanSu::doi_ten_bo_phan( $toi, $cu, $moi, $gop );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		$bao = array( array( 'ok' => ( $kq['gop'] ? 'Đã GỘP "' : 'Đã đổi tên "' ) . $kq['cu']
			. ( $kq['gop'] ? '" vào "' : '" thành "' ) . $kq['moi'] . '"'
			. ( $kq['nguoi'] ? ' — ' . (int) $kq['nguoi'] . ' người chuyển theo.' : '.' ) ) );
		/* 🔴 NÓI RA CHỖ HAI BÊN KHAI NGƯỢC NHAU. Giữ của phòng đích là một lựa chọn phải nói
		   thành lời — im lặng thì cả một phòng vừa đổi quyền mà không ai biết. */
		if ( ! empty( $kq['lech'] ) ) {
			$ten_cot = VHCC_Cong::cot_nhom();
			$dong = array();
			foreach ( $kq['lech'] as $k => $v ) {
				$dong[] = ( isset( $ten_cot[ $k ]['ten'] ) ? $ten_cot[ $k ]['ten'] : $k )
					. ' (giữ «' . $v['giu'] . '», bỏ «' . $v['bo'] . '»)';
			}
			$bao[] = array( 'canh' => '⚠️ Hai phòng khai NGƯỢC nhau ở ' . count( $dong )
				. ' cột quyền — đã giữ theo phòng đích: ' . implode( ' · ', $dong )
				. '. Soát lại ở khối Phân quyền theo bộ phận.' );
		}
		return $bao;
	}

	private static function viec_bp_xoa( $toi ) {
		$ten = isset( $_POST['bp_cu'] ) ? sanitize_text_field( wp_unslash( $_POST['bp_cu'] ) ) : '';
		$kq  = VHCC_NhanSu::xoa_bo_phan( $toi, $ten );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		return array( array( 'ok' => 'Đã xoá phòng ban "' . $kq['ten'] . '"'
			. ( $kq['vai'] ? ', kèm dòng vai của nó' : '' )
			. ( $kq['luat'] ? ' và ' . (int) $kq['luat'] . ' ô luật quyền' : '' ) . '.' ) );
	}

	private static function viec_vai_goi_y( $toi ) {
		$kq = VHCC_NhanSu::dien_vai_goi_y( $toi );
		if ( empty( $kq['ok'] ) ) { return array( array( 'loi' => $kq['error'] ) ); }
		if ( ! $kq['so'] ) {
			return array( array( 'canh' => 'Không phòng nào đang trống — chưa điền gì.' ) );
		}
		return array( array( 'ok' => 'Đã điền gợi ý cho ' . (int) $kq['so'] . ' phòng đang trống: '
			. implode( ' · ', $kq['ten'] ) . '. Đây là GỢI Ý — bỏ tích chỗ nào không đúng.' ) );
	}

	/**
	 * KHỐI KHAI "BỘ PHẬN DÙNG NHỮNG VAI NÀO".
	 *
	 * ⚠️ Đây là GỢI Ý, không phải chốt quyền. Nói thẳng ra trên màn, kẻo người khai tưởng mình
	 *    vừa bó quyền ai đó và yên tâm bỏ qua chuyện phân quyền thật.
	 */
	private static function the_vai_bp( $toi ) {
		if ( ! VHCC_NhanSu::co_sua_ho_so( $toi ) ) { return; }
		$ban = VHCC_NhanSu::vai_theo_bo_phan();
		$co  = VHCC_Vai::ds_ten();
		$so_khai = 0;
		foreach ( $ban as $ds_k ) { $so_khai += count( $ds_k ); }

		echo '<div class="the"><details><summary><b>Vai trò theo bộ phận</b> — '
			. ( $ban ? count( $ban ) . ' bộ phận đã khai · ' . $so_khai . ' dòng vai'
				: '<span class="mo">chưa khai bộ phận nào</span>' ) . '</summary>';
		echo '<p class="mo">Khai ở đây thì ô <b>Vai trò</b> của người thuộc bộ phận ấy sẽ bày mấy '
			. 'vai này <b>lên đầu</b>, cho nhanh tay. <b>Không cắt bớt lựa chọn nào</b> — vai khác '
			. 'vẫn chọn được, chỉ nằm ở nhóm dưới. Đây là gợi ý, <b>không phải chốt quyền</b>: '
			. 'quyền vẫn đi theo thang vai như cũ.</p>';
		echo '<div class="cuon"><table class="stt"><thead><tr><th>Bộ phận</th>'
			. '<th>Vai bày lên đầu</th><th></th></tr></thead><tbody>';
		foreach ( VHCC_NhanSu::ds_bo_phan() as $bp ) {
			$dang = isset( $ban[ $bp ] ) ? $ban[ $bp ] : array();
			echo '<tr><td><b>' . esc_html( $bp ) . '</b></td>';
			echo '<td><form method="post" class="hang" style="gap:8px;margin:0">';
			echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
			echo '<input type="hidden" name="vbp_ten" value="' . esc_attr( $bp ) . '">';
			echo '<div class="o-vai-tick">';
			foreach ( $co as $t ) {
				echo '<label><input type="checkbox" name="vbp_vai[]" value="' . esc_attr( $t ) . '"'
					. checked( true, in_array( $t, $dang, true ), false ) . '> ' . esc_html( $t ) . '</label>';
			}
			echo '</div>';
			/* Vai khai rồi mà hệ chưa có — giữ lại bằng ô ẩn, không thì bấm Lưu là mất khai. */
			foreach ( $dang as $t ) {
				if ( in_array( $t, $co, true ) ) { continue; }
				echo '<input type="hidden" name="vbp_vai[]" value="' . esc_attr( $t ) . '">';
				echo '<span class="chua-ma">⚠️ «' . esc_html( $t ) . '» chưa có trong hệ — khai ở '
					. 'khối Bảng vai trò</span>';
			}
			echo '<button name="viec" value="vai_bp">Lưu</button>';
			echo '</form></td><td></td></tr>';
		}
		echo '</tbody></table></div>';
		/* 🔴 10 TRÊN 12 PHÒNG ĐANG TRỐNG (ảnh anh Thắng 13/09/2026) — khối này gần như vô dụng.
		   Nút điền gợi ý CHỈ điền vào phòng đang trống, không đè phòng đã khai, và nói rõ nó là
		   gợi ý. Tự điền im lặng thì đó là đoán hộ cả sơ đồ tổ chức của công ty. */
		echo '<form method="post" class="hang" style="gap:8px;margin-top:10px">';
		echo '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo '<button name="viec" value="vai_goi_y">Điền gợi ý cho phòng đang trống</button>';
		echo '<span class="mo">Chỉ điền vào phòng <b>chưa khai gì</b> — phòng đã khai không đụng. '
			. 'Điền xong bỏ tích chỗ nào không đúng.</span>';
		echo '</form>';
		echo '</details></div>';
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
