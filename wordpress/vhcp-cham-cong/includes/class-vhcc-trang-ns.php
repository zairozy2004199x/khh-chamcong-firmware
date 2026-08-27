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
	const THAM_SO = array( 'ncs', 'nq', 'nvai', 'np' );

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

		$vai = self::luu_vai( $toi );
		$doi = (int) $kq['doi'];

		$bao = array();
		if ( $doi )          { $bao[] = array( 'ok' => 'Đã lưu ' . $doi . ' ô quyền vào trang.' ); }
		if ( $vai['doi'] )   { $bao[] = array( 'ok' => 'Đã đổi vai trò cho ' . $vai['doi'] . ' người.' ); }
		/* 🔴 LỖI VAI TRÒ PHẢI HIỆN RA, KHÔNG ĐƯỢC NUỐT. Mấy chốt trong `dat_vai_tro()` (không
		   nâng quá bậc mình, không đụng người trên mình, không tự sửa mình) chỉ có tác dụng nếu
		   người bấm ĐỌC ĐƯỢC câu chối. Nuốt đi thì màn hình báo "đã lưu", ô vai trở về giá trị
		   cũ, và người ta tưởng hệ thống hỏng chứ không biết mình vừa bị chặn. */
		foreach ( $vai['loi'] as $l ) { $bao[] = array( 'loi' => $l ); }
		if ( ! $bao ) { return array( array( 'canh' => 'Không có ô nào đổi — chưa lưu gì.' ) ); }
		return $bao;
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
			. '.mo-hs:hover{color:var(--xanh);border-color:var(--xanh)}';
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

		foreach ( self::lay_bao() as $b ) { self::ve_bao( $b ); }

		self::the_duong_di( $toi, $ds_trang );

		if ( ! $ds_trang ) {
			echo '<div class="the"><div class="bao loi">Không dò thấy trang nào trên site này. '
				. 'Các plugin trang (Cổng, Nội bộ, Chi phí, Ghế, Hợp đồng) có thể chưa được kích hoạt.'
				. '</div></div>';
			self::dong_trang();
			return;
		}

		self::the_bang( $toi, $ds_trang, $cs, $q, $vai, $p );
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
		$nguoi = VHCC_NhanSu::ds_nhan_vien( $toi, $cs, $q );
		if ( '' !== $vai ) {
			$loc = array();
			foreach ( $nguoi as $r ) {
				if ( VHCC_Vai::ma( isset( $r['vai_tro'] ) ? $r['vai_tro'] : '' ) === $vai ) { $loc[] = $r; }
			}
			$nguoi = $loc;
		}
		$tong  = count( $nguoi );
		$so_tr = max( 1, (int) ceil( $tong / self::MOI_TRANG ) );
		$p     = min( $p, $so_tr );
		$lat   = array_slice( $nguoi, ( $p - 1 ) * self::MOI_TRANG, self::MOI_TRANG );

		echo '<div class="the">';
		echo '<h2>Ai vào được trang nào</h2>';
		echo '<p class="mo">Mặc định theo <b>vai trò</b> — bảng này chỉ ghi những chỗ <b>khác</b> '
			. 'mặc định. Để ô ở «Theo vai» là người ấy đi theo thang vai, đổi vai là quyền đổi theo. '
			. 'Chọn «Mở» hay «Khoá» là ghim cứng cho riêng người đó, vai đổi cũng không lay chuyển.</p>';

		self::o_tim( $cs, $q, $vai );

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
			echo '<tr>';
			echo '<td><b>' . esc_html( $ma ) . '</b></td>';
			/* Nút mở thẳng hồ sơ người này ở màn Hồ sơ & tài khoản. Anh Thắng: *"bổ sung thêm
			   1 số thông tin nhân viên, với cấu hình này nó thông với thông tin nhân viên"*.
			   🔴 KHÔNG dựng lại màn hồ sơ ở đây. Thêm/sửa/xoá nhân sự đã có đủ ở màn kia; làm
			      lần hai là hai màn cùng ghi một bảng, và sớm muộn hai bên lệch luật. Nối
			      đường đi thì vẫn một lần bấm mà chỉ có MỘT nơi giữ luật. */
			echo '<td>' . esc_html( (string) $r['ho_ten'] );
			if ( '' !== $ma && class_exists( 'VHCC_Web' ) && method_exists( 'VHCC_Web', 'url' ) ) {
				echo ' <a class="mo-hs" title="Mở hồ sơ đầy đủ — sửa thông tin, PIN, lương"'
					. ' href="' . esc_url( add_query_arg(
						array( 'man' => 'ho_so', 'q' => $ma ), VHCC_Web::url() ) ) . '">hồ sơ ↗</a>';
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $r['cua_hang'] ) . '</td>';
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
		foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $ten ) {
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

	private static function o_tim( $cs, $q, $vai ) {
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
