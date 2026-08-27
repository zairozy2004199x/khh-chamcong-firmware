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

	private static function viec_luu( $toi ) {
		$sach = self::doc_o();
		if ( ! $sach ) { return array( array( 'loi' => 'Biểu mẫu không hợp lệ.' ) ); }
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
			if ( ! empty( $r['doi'] ) ) { $ra['doi']++; $ra['go'] += (int) $r['go']; }
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
			if ( ! empty( $r['doi'] ) ) { $ra['doi']++; }
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
		return array( array( 'ok' => 'Đã lưu hồ sơ ' . $ma . '.'
			. ( '' !== $pin ? ' PIN đã đổi.' : '' ) ) );
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
			/* Hàng trùng: nền vàng nhạt + nhãn đỏ. Màu KHÔNG đứng một mình — nhãn có chữ, để
			   người mù màu và bản in đen trắng vẫn đọc ra. */
			. 'tr.hang-trung>td{background:#fffbeb}'
			. '.chip-t,.chip-n{display:inline-block;margin-left:5px;padding:0 6px;border-radius:9px;'
			. 'background:#fee2e2;color:var(--do);font-size:10.5px;font-weight:700;'
			. 'letter-spacing:.2px;vertical-align:middle}'
			. '.chip-n{margin-left:0}'
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

		if ( ! $ds_trang ) {
			echo '<div class="the"><div class="bao loi">Không dò thấy trang nào trên site này. '
				. 'Các plugin trang (Cổng, Nội bộ, Chi phí, Ghế, Hợp đồng) có thể chưa được kích hoạt.'
				. '</div></div>';
			self::dong_trang();
			return;
		}

		self::the_bang( $toi, $ds_trang, $cs, $q, $vai, $p );
		self::the_dong_bo( $toi );
		self::the_vai( $toi );
		self::the_dau_viec( $toi );
		self::the_ngoai_le();
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
			echo ' Muốn khai ở đây là ăn ngay thì vào <a href="'
				. esc_url( add_query_arg( array( 'man' => 'cau_hinh' ), VHCC_Web::url() ) ) . '">'
				. 'Quản trị chấm công → Cấu hình</a>, đổi <b>Nguồn người dùng</b> sang '
				. '<b>“hồ sơ nhân sự”</b>.';
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
		echo '<li><b>Ghế massage</b> — trang của <b>khách</b>. Khách quét QR rồi trả tiền, '
			. 'không đăng nhập và không có Mã NV. Khoá được nó là ghế đứng im, tiền không vào.</li>';
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

		$tong  = count( $nguoi );
		$so_tr = max( 1, (int) ceil( $tong / self::MOI_TRANG ) );
		$p     = min( $p, $so_tr );
		$lat   = array_slice( $nguoi, ( $p - 1 ) * self::MOI_TRANG, self::MOI_TRANG );

		echo '<div class="the">';
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
				if ( $co_trung['ten'] ) { echo '<span class="chip-t">trùng tên</span>'; }
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
			echo '</tr>';
			if ( $dang_sua === $ma ) { self::hang_sua( $toi, $r, 4 + count( $ds_trang ) ); }
		}
		echo '</tbody></table></div>';
		echo '<div class="hang" style="margin-top:12px">'
			. '<button class="chinh" name="viec" value="luu_quyen">Lưu bảng này</button>'
			. '<span class="mo">Chỉ lưu ' . count( $lat ) . ' người đang hiện — người ở trang khác '
			. 'không bị đụng tới.</span></div>';
		echo '</form>';

		self::thanh_trang( $p, $so_tr, $tong );
		echo '</div>';
	}

	/** Địa chỉ trang này với ô sửa mở ở `$ma` (rỗng = đóng), giữ nguyên bộ lọc và số trang. */
	private static function url_sua( $ma ) {
		$u = self::url_hien();
		$u = remove_query_arg( 'sua_o', $u );
		return ( '' === $ma ) ? $u : add_query_arg( 'sua_o', $ma, $u );
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
	private static function hang_sua( $toi, $r, $so_cot ) {
		$ma  = trim( (string) $r['ma_nv'] );
		$luong = VHCC_NhanSu::co_xem_luong( $toi );
		$g = function ( $c ) use ( $r ) { return isset( $r[ $c ] ) ? (string) $r[ $c ] : ''; };

		echo '<tr class="hang-sua"><td colspan="' . (int) $so_cot . '">';
		echo '<form method="post"><input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">';
		echo self::o_loc();
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
		echo '<a class="nut" href="' . esc_url( self::url_sua( '' ) ) . '">Đóng</a>';
		echo '<span class="mo">Còn CCCD, địa chỉ, hợp đồng… thì mở <b>đầy đủ ↗</b> ở cột Họ tên.</span>';
		echo '</div></form></td></tr>';
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
			echo '<div class="cuon"><table><thead><tr><th>Tên vai</th><th>Quyền y như</th>'
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
		echo '<button class="chinh" name="viec" value="them_vai">Thêm vai</button>';
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
			echo '<div class="cuon"><table><thead><tr><th>Cho ai</th><th>Đầu việc</th>'
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

	/* ------------------------------------------------------------------ soát ngoại lệ */

	/**
	 * MỌI NGOẠI LỆ ĐANG CÓ, GOM MỘT CHỖ.
	 *
	 * 🔴 Vì sao cần khối này: bảng chính có bộ lọc và phân trang, nên một ô "Khoá" đặt nhầm cho
	 *    người ở trang 4 thì không ai nhìn thấy lại nữa — và người ấy chỉ biết mình bị chặn.
	 *    Danh sách này là chỗ soát: ngắn, đầy đủ, gỡ được từng dòng.
	 */
	private static function the_ngoai_le() {
		$ds = VHCC_Cong::ngoai_le_phang();
		echo '<div class="the"><details' . ( $ds ? ' open' : '' ) . '>';
		echo '<summary><b>Đang có ' . count( $ds ) . ' ngoại lệ</b> — những chỗ khác mặc định theo vai</summary>';
		if ( ! $ds ) {
			echo '<p class="mo">Chưa khai ngoại lệ nào. Cả công ty đang đi theo thang vai.</p>';
			echo '</details></div>';
			return;
		}
		echo '<div class="cuon"><table><thead><tr><th>Mã NV</th><th>Họ tên</th><th>Trang</th>'
			. '<th>Đang đặt</th><th></th></tr></thead><tbody>';
		foreach ( $ds as $x ) {
			$hs  = VHCC_NhanSu::ho_so( $x['ma_nv'] );
			$ten = $hs ? (string) $hs['ho_ten'] : '';
			echo '<tr><td><b>' . esc_html( $x['ma_nv'] ) . '</b></td>';
			echo '<td>' . ( '' !== $ten ? esc_html( $ten )
				: '<span class="chua-ma">không thấy hồ sơ</span>' ) . '</td>';
			echo '<td>' . esc_html( $x['ten'] )
				. ( $x['co'] ? '' : ' <span class="chua-ma">(trang không còn cài)</span>' ) . '</td>';
			echo '<td>' . ( 'mo' === $x['dat']
				? '<span class="co">Mở</span>' : '<span class="chua">Khoá</span>' ) . '</td>';
			echo '<td><form method="post" style="margin:0">'
				. '<input type="hidden" name="ky" value="' . esc_attr( self::ky() ) . '">'
				. self::o_loc()
				. '<input type="hidden" name="ma_nv" value="' . esc_attr( $x['ma_nv'] ) . '">'
				. '<input type="hidden" name="trang" value="' . esc_attr( $x['trang'] ) . '">'
				. '<button name="viec" value="go_ngoai_le">Gỡ</button></form></td></tr>';
		}
		echo '</tbody></table></div></details></div>';
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
