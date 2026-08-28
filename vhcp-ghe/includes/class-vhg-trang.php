<?php
/**
 * TRANG NGOÀI `/ghe` — bản thay cho dashboard "POSH massage" của Apps Script.
 *
 * =============================================================================================
 * KHÁC HẲN TRANG `/cham-cong`: TRANG NÀY TỰ CHỨA
 * =============================================================================================
 * Trang chấm công lấy giao diện thẳng từ Apps Script để khỏi tồn tại "bản chép" lệch với bản
 * gốc. Trang này KHÔNG làm vậy, cố ý: cả hệ thống ghế đã rời hẳn Google, nên đi vòng qua Apps
 * Script là dựng lại đúng cái phụ thuộc vừa gỡ. Giao diện nằm trong plugin, dữ liệu lấy từ
 * MySQL, không gọi ra ngoài lượt nào.
 *
 * =============================================================================================
 * BA LUẬT CỦA MÀN NÀY
 * =============================================================================================
 * 1. KHÔNG BAO GIỜ CHUYỂN HƯỚNG. Giống hai cổng máy: WordPress rất thích thêm/bỏ dấu gạch cuối,
 *    mà trang này người ta lưu vào màn hình chính điện thoại — một lượt 301 là mất phiên.
 *
 * 2. LỖI PHẢI NÓI RA. Ghế mất kết nối, tiền đã vào mà ghế chưa nhận — hai chuyện đó để TRÊN
 *    CÙNG, trên cả con số doanh thu. Người mở trang này lúc 9 giờ tối đang đứng cạnh một ghế
 *    không chạy và một khách đang cáu; họ cần câu trả lời trước, không cần báo cáo tháng.
 *
 * 3. BẬT TAY LÀ CHO KHÔNG MỘT LƯỢT. Nút đó có, vì thực tế cần, nhưng phải ghi lại ai bấm và
 *    lúc nào — cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Trang {

	/** Tên hệ thống — khai MỘT chỗ. Nó hiện ở thẻ tiêu đề trình duyệt, màn đăng nhập và dải đầu
	    trang; ba chỗ gõ tay là ba chỗ lệch nhau sau lần đổi tên đầu tiên. */
	const TEN_HE_THONG = 'Hệ Thống Thanh Toán Ghế Massage Tự Động POSH';
	const TEN_NGAN     = 'POSH Massage';

	public static function slug() {
		$s = get_option( 'vhg_slug' );
		$s = $s ? sanitize_title( $s ) : 'ghe';
		return $s ? $s : 'ghe';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhg', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhg_app=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'chan_chuyen_huong' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'phuc_vu' ), 0 );
	}

	public static function query_vars( $v ) { $v[] = 'vhg_app'; return $v; }

	private static function la_trang() {
		if ( 1 === (int) get_query_var( 'vhg_app' ) ) { return true; }
		if ( isset( $_GET['vhg'] ) && 'app' === $_GET['vhg'] ) { return true; }
		$d = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$d = trim( (string) parse_url( $d, PHP_URL_PATH ), '/' );
		$s = self::slug();
		return $d === $s || substr( $d, - ( strlen( $s ) + 1 ) ) === '/' . $s;
	}

	/** Luật 1. Xem khối đầu tệp. */
	public static function chan_chuyen_huong() {
		if ( ! self::la_trang() ) { return; }
		add_filter( 'redirect_canonical', '__return_false', 99 );
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	public static function phuc_vu() {
		if ( ! self::la_trang() ) { return; }
		if ( isset( $_GET['api'] ) || isset( $_POST['api'] ) ) {
			self::api();
			if ( ! defined( 'VHG_TEST' ) ) { exit; }
			return;
		}
		self::ve();
		if ( ! defined( 'VHG_TEST' ) ) { exit; }
	}

	// =========================================================================================
	// API — JSON, tất cả đi qua POST và mang token của phiên
	// =========================================================================================

	private static function tra( $d ) {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		echo wp_json_encode( $d );
	}

	private static function than() {
		if ( defined( 'VHG_TEST' ) && isset( $GLOBALS['VHG_THAN'] ) ) { return (string) $GLOBALS['VHG_THAN']; }
		$t = file_get_contents( 'php://input' );
		return is_string( $t ) ? $t : '';
	}

	public static function api() {
		$d = json_decode( self::than(), true );
		if ( ! is_array( $d ) ) { $d = array(); }
		foreach ( $_POST as $k => $v ) { if ( ! isset( $d[ $k ] ) ) { $d[ $k ] = $v; } }
		$viec = isset( $_GET['api'] ) ? (string) $_GET['api']
			: (string) ( isset( $d['api'] ) ? $d['api'] : '' );
		$viec = preg_replace( '/[^a-z_]/', '', strtolower( $viec ) );

		if ( 'login' === $viec ) {
			self::tra( VHG_Auth::login( isset( $d['pin'] ) ? $d['pin'] : '' ) );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * BÁO CÁO DOANH THU — ĐĂNG NHẬP BẰNG PIN RIÊNG (bảng `bc_pin`), TÁCH KHỎI TOKEN /ghe.
		 *
		 * Anh Thắng 27/08/2026: *"mỗi nhân viên 1 PIN, gán cho cơ sở rồi; đăng nhập thấy cơ sở
		 * mình. Sau PIN này dùng chung với nhân sự K&H để chấm công, nộp báo cáo, ghi chi phí"*.
		 *
		 * → Các việc `bc_*` (trừ quản lý PIN `bc_pin_*`) chạy TRƯỚC cổng token: chúng tự gác bằng
		 *   PIN riêng trong VHG_BaoCao (fail-closed — PIN sai/rỗng không trả dữ liệu). Người thu
		 *   tiền KHÔNG cần tài khoản /ghe; ngược lại token /ghe KHÔNG tự mở được báo cáo.
		 *
		 * ⚠️ Đặt ở ĐÂY, trước `$tok`, là CỐ Ý. Để sau cổng token thì phải cấp token /ghe cho mọi
		 *    nhân viên thu tiền — đúng thứ mô hình PIN-riêng dựng ra để khỏi phải làm.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 0 === strpos( $viec, 'bc_' ) && 0 !== strpos( $viec, 'bc_pin_' ) ) {
			$pin = (string) ( isset( $d['pin'] ) ? $d['pin'] : '' );
			if ( 'bc_boot' === $viec ) {
				self::tra( VHG_BaoCao::boot( $pin ) ); return;
			}
			if ( 'bc_lastmeters' === $viec ) {
				self::tra( array( 'ok' => true, 'map' => VHG_BaoCao::lay_chiso_truoc(
					isset( $d['codes'] ) ? (array) $d['codes'] : array(),
					isset( $d['ngay'] ) ? $d['ngay'] : '' ) ) );
				return;
			}
			if ( 'bc_checkday' === $viec ) {
				self::tra( VHG_BaoCao::kiem_ngay(
					isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['ngay'] ) ? $d['ngay'] : '', $pin ) );
				return;
			}
			if ( 'bc_submit' === $viec ) {
				self::tra( VHG_BaoCao::luu( $d, $pin ) ); return;
			}
			if ( 'bc_recent' === $viec ) {
				self::tra( array( 'ok' => true, 'ds' => VHG_BaoCao::ds_24h( $pin ) ) ); return;
			}
			if ( 'bc_edit' === $viec ) {
				self::tra( VHG_BaoCao::sua_dong(
					isset( $d['report_id'] ) ? $d['report_id'] : '',
					isset( $d['ma_may'] ) ? $d['ma_may'] : '',
					isset( $d['patch'] ) ? $d['patch'] : array(), $pin ) );
				return;
			}
			if ( 'bc_history' === $viec ) {
				self::tra( array( 'ok' => true, 'ds' => VHG_BaoCao::lich_su(
					isset( $d['thang'] ) ? $d['thang'] : '', $pin ) ) );
				return;
			}
			if ( 'bc_unpaid' === $viec ) {
				self::tra( array( 'ok' => true, 'ds' => VHG_BaoCao::chua_nop( $pin ) ) ); return;
			}
			if ( 'bc_supplement' === $viec ) {
				self::tra( VHG_BaoCao::nop_bosung(
					isset( $d['report_id'] ) ? $d['report_id'] : '',
					isset( $d['ngay'] ) ? $d['ngay'] : '',
					isset( $d['so_tien'] ) ? $d['so_tien'] : '',
					isset( $d['hinhthuc'] ) ? $d['hinhthuc'] : 'cash', $pin ) );
				return;
			}
			if ( 'bc_denghi_gui' === $viec ) {
				self::tra( VHG_BaoCao::denghi_gui( $d, $pin ) ); return;
			}
			if ( 'bc_denghi_ds' === $viec ) {
				self::tra( array( 'ok' => true, 'ds' => VHG_BaoCao::denghi_ds(
					isset( $d['coso'] ) ? $d['coso'] : '', $pin ) ) );
				return;
			}
			if ( 'bc_yeucau' === $viec ) {
				self::tra( VHG_BaoCao::yeucau_ds( $pin ) ); return;
			}
			if ( 'bc_phien' === $viec ) {
				self::tra( VHG_BaoCao::phien( $pin, isset( $d['ngay'] ) ? $d['ngay'] : '' ) ); return;
			}
			if ( 'bc_chot_som' === $viec ) {
				self::tra( VHG_BaoCao::chot_som(
					isset( $d['ngay'] ) ? $d['ngay'] : '',
					isset( $d['ly_do'] ) ? $d['ly_do'] : '', $pin ) );
				return;
			}
			if ( 'bc_doichieu' === $viec ) {
				self::tra( VHG_BaoCao::doi_chieu( $pin, isset( $d['ngay'] ) ? $d['ngay'] : '' ) ); return;
			}
			if ( 'bc_hoidap' === $viec ) {
				self::tra( VHG_BaoCao::hoi_dap( $pin, isset( $d['cau'] ) ? $d['cau'] : '' ) ); return;
			}
			self::tra( array( 'ok' => false, 'error' => 'Việc báo cáo không rõ: ' . $viec ) );
			return;
		}

		$tok = (string) ( isset( $d['token'] ) ? $d['token'] : '' );
		$ai  = VHG_Auth::user_by_token( $tok );
		if ( ! $ai ) {
			/* `het_phien` là MÃ, không phải câu chữ: giao diện phải phân biệt được "hết phiên,
			   hiện lại ô PIN" với "lỗi khác, đừng đá người ta ra". Bắt theo câu chữ thì sửa một
			   dấu phẩy trong thông báo là đăng nhập lại vô hạn. */
			self::tra( array( 'ok' => false, 'ma' => 'het_phien',
				'error' => 'Phiên đã hết hoặc quyền đã bị thu — đăng nhập lại.' ) );
			return;
		}

		if ( 'logout' === $viec ) { self::tra( VHG_Auth::logout( $tok ) ); return; }

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 CHỐT PHÂN QUYỀN — MỘT CHỖ, TRƯỚC MỌI NHÁNH.
		 *
		 * Danh sách việc nằm ở `VHG_Auth::VIEC_QUAN_TRI`. Đặt chốt ở đây chứ không rải `if role`
		 * vào từng nhánh: rải thì lỗi chỉ lộ ra ở việc BỊ QUÊN, mà việc bị quên thì theo định
		 * nghĩa không ai nghĩ tới lúc đọc lại. Thêm việc mới mà quên khai là nó rơi vào nhóm
		 * "ai cũng làm được" — nên phép thử canh đúng chuyện đó.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( ! VHG_Auth::duoc_lam( $ai['role'], $viec ) ) {
			self::tra( array( 'ok' => false, 'ma' => 'khong_du_quyen',
				'error' => 'Việc này chỉ Admin hoặc Quản lý làm được. '
					. 'Anh/chị đang đăng nhập với vai trò ' . $ai['role'] . '.' ) );
			return;
		}

		if ( 'so_lieu' === $viec ) {
			self::tra( self::so_lieu( isset( $d['ky'] ) ? $d['ky'] : 'today', $ai ) );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * TRANG KẾ TOÁN (kt_*) — token + vai trò CHỐT hoặc QUẢN TRỊ (Admin/Quản lý).
		 * Kế toán = người "Chốt doanh số"; quản lý/Admin cũng vào được. Người thu (chỉ giúp khách)
		 * KHÔNG vào. Đọc/ghi chung bảng bc/bc_dong — xem class-vhg-ketoan.php.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 0 === strpos( $viec, 'kt_' ) ) {
			$q = VHG_Auth::quyen_cua( $ai['role'] );
			if ( empty( $q['quan_tri'] ) && empty( $q['chot_doanh_so'] ) ) {
				self::tra( array( 'ok' => false, 'ma' => 'khong_du_quyen',
					'error' => 'Trang kế toán chỉ dành cho vai trò Chốt doanh số, Quản lý hoặc Admin.' ) );
				return;
			}
			$boi = (string) $ai['name'];
			if ( 'kt_ds' === $viec )       { self::tra( VHG_KeToan::ds( isset( $d['thang'] ) ? $d['thang'] : '' ) ); return; }
			if ( 'kt_ct' === $viec )       { self::tra( VHG_KeToan::chi_tiet( isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['ngay'] ) ? $d['ngay'] : '' ) ); return; }
			if ( 'kt_sua' === $viec )      { self::tra( VHG_KeToan::sua( isset( $d['report_id'] ) ? $d['report_id'] : '', isset( $d['ma_may'] ) ? $d['ma_may'] : '', isset( $d['patch'] ) ? $d['patch'] : array(), $boi ) ); return; }
			if ( 'kt_duyet' === $viec )    { self::tra( VHG_KeToan::duyet( isset( $d['targets'] ) ? $d['targets'] : array(), ! empty( $d['on'] ), $boi ) ); return; }
			if ( 'kt_duyet_ngay' === $viec ) { self::tra( VHG_KeToan::duyet_ngay( isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['ngay'] ) ? $d['ngay'] : '', ! empty( $d['on'] ), $boi ) ); return; }
			if ( 'kt_khoa' === $viec )     { self::tra( VHG_KeToan::khoa( isset( $d['ngay'] ) ? $d['ngay'] : '', ! empty( $d['on'] ), isset( $d['coso'] ) ? $d['coso'] : '', $boi ) ); return; }
			if ( 'kt_khoa_ds' === $viec )  { self::tra( VHG_KeToan::khoa_ds() ); return; }
			if ( 'kt_xoa' === $viec )      { self::tra( VHG_KeToan::xoa( isset( $d['targets'] ) ? $d['targets'] : array(), isset( $d['ly_do'] ) ? $d['ly_do'] : '', $boi ) ); return; }
			if ( 'kt_rac_ds' === $viec )   { self::tra( VHG_KeToan::rac_ds( isset( $d['gh'] ) ? $d['gh'] : 100 ) ); return; }
			if ( 'kt_rac_hoan' === $viec ) { self::tra( VHG_KeToan::rac_hoan( isset( $d['ids'] ) ? $d['ids'] : array(), $boi ) ); return; }
			if ( 'kt_doi_ngay' === $viec ) { self::tra( VHG_KeToan::doi_ngay( isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['ngay_cu'] ) ? $d['ngay_cu'] : '', isset( $d['ngay_moi'] ) ? $d['ngay_moi'] : '', isset( $d['ly_do'] ) ? $d['ly_do'] : '', $boi ) ); return; }
			if ( 'kt_undo_ds' === $viec )  { self::tra( VHG_KeToan::undo_ds( isset( $d['gh'] ) ? $d['gh'] : 40 ) ); return; }
			if ( 'kt_undo' === $viec )     { self::tra( VHG_KeToan::undo( isset( $d['id'] ) ? (int) $d['id'] : 0, $boi ) ); return; }
			if ( 'kt_denghi_ds' === $viec )   { self::tra( VHG_KeToan::denghi_ds( ! empty( $d['tatca'] ) ) ); return; }
			if ( 'kt_denghi_duyet' === $viec ) { self::tra( VHG_KeToan::denghi_duyet( isset( $d['id'] ) ? $d['id'] : '', isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '', $boi ) ); return; }
			if ( 'kt_denghi_tuchoi' === $viec ) { self::tra( VHG_KeToan::denghi_tuchoi( isset( $d['id'] ) ? $d['id'] : '', isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '', $boi ) ); return; }
			if ( 'kt_yeucau_tao' === $viec )  { self::tra( VHG_KeToan::yeucau_tao( isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['ngay'] ) ? $d['ngay'] : '', isset( $d['loai'] ) ? $d['loai'] : 'bo_sung', isset( $d['noi_dung'] ) ? $d['noi_dung'] : '', $boi ) ); return; }
			if ( 'kt_yeucau_ds' === $viec )   { self::tra( VHG_KeToan::yeucau_ds( isset( $d['thang'] ) ? $d['thang'] : '' ) ); return; }
			if ( 'kt_yeucau_huy' === $viec )  { self::tra( VHG_KeToan::yeucau_huy( isset( $d['id'] ) ? $d['id'] : '', $boi ) ); return; }
			if ( 'kt_can_nop' === $viec )    { self::tra( VHG_KeToan::can_nop( isset( $d['thang'] ) ? $d['thang'] : '' ) ); return; }
			if ( 'kt_nop_tay' === $viec )    { self::tra( VHG_KeToan::nop_tay( isset( $d['pheps'] ) ? $d['pheps'] : array(), isset( $d['thang'] ) ? $d['thang'] : '', ! empty( $d['apply'] ), isset( $d['ly_do'] ) ? $d['ly_do'] : '', $boi ) ); return; }
			if ( 'kt_ck' === $viec )         { self::tra( VHG_KeToan::doisoat_ck( isset( $d['rows'] ) ? $d['rows'] : array(), isset( $d['thang'] ) ? $d['thang'] : '', ! empty( $d['apply'] ), isset( $d['ly_do'] ) ? $d['ly_do'] : '', $boi ) ); return; }
			if ( 'kt_ma_nop_ds' === $viec )  { self::tra( VHG_KeToan::ma_nop_ds() ); return; }
			if ( 'kt_ma_nop_luu' === $viec ) { self::tra( VHG_KeToan::ma_nop_luu( isset( $d['id'] ) ? (int) $d['id'] : 0, isset( $d['code'] ) ? $d['code'] : '', isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '' ) ); return; }
			if ( 'kt_ma_nop_xoa' === $viec ) { self::tra( VHG_KeToan::ma_nop_xoa( isset( $d['id'] ) ? (int) $d['id'] : 0 ) ); return; }
			if ( 'kt_congno' === $viec )     { self::tra( VHG_KeToan::cong_no( isset( $d['thang'] ) ? $d['thang'] : '' ) ); return; }
			if ( 'kt_congno_chot' === $viec ) { self::tra( VHG_KeToan::cong_no_chot( isset( $d['thang'] ) ? $d['thang'] : '', isset( $d['ly_do'] ) ? $d['ly_do'] : '', $boi ) ); return; }
			if ( 'kt_congno_mo' === $viec )  { self::tra( VHG_KeToan::cong_no_mo( isset( $d['thang'] ) ? $d['thang'] : '', $boi ) ); return; }
			if ( 'kt_congno_dat' === $viec ) { self::tra( VHG_KeToan::cong_no_dat( isset( $d['thang'] ) ? $d['thang'] : '', isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['so_tien'] ) ? $d['so_tien'] : 0, isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '', $boi ) ); return; }
			if ( 'kt_qr_ds' === $viec )      { self::tra( VHG_KeToan::qr_ds( isset( $d['thang'] ) ? $d['thang'] : '' ) ); return; }
			if ( 'kt_qr_ap' === $viec )      { self::tra( VHG_KeToan::qr_ap( isset( $d['targets'] ) ? $d['targets'] : array(), isset( $d['ly_do'] ) ? $d['ly_do'] : '', $boi ) ); return; }
			if ( 'kt_ma_misa_ds' === $viec )  { self::tra( VHG_KeToan::ma_misa_ds() ); return; }
			if ( 'kt_ma_misa_luu' === $viec ) { self::tra( VHG_KeToan::ma_misa_luu( isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['unit_id'] ) ? $d['unit_id'] : '', isset( $d['unit_name'] ) ? $d['unit_name'] : '', isset( $d['vung'] ) ? $d['vung'] : '', isset( $d['thu_tu'] ) ? $d['thu_tu'] : 0, isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '' ) ); return; }
			if ( 'kt_ma_misa_xoa' === $viec ) { self::tra( VHG_KeToan::ma_misa_xoa( isset( $d['coso_key'] ) ? $d['coso_key'] : '' ) ); return; }
			if ( 'kt_ma_misa_seed' === $viec ) { self::tra( VHG_KeToan::ma_misa_seed() ); return; }
			if ( 'kt_misa' === $viec )        { self::tra( VHG_KeToan::misa_chungtu( isset( $d['from'] ) ? $d['from'] : '', isset( $d['to'] ) ? $d['to'] : '', isset( $d['thang'] ) ? $d['thang'] : '', ! empty( $d['chi_tien_mat'] ), isset( $d['so_ct_dau'] ) ? $d['so_ct_dau'] : '' ) ); return; }
			if ( 'kt_baocao_ngay' === $viec ) { self::tra( VHG_KeToan::baocao_ngay( isset( $d['thang'] ) ? $d['thang'] : '', ! empty( $d['chi_da_duyet'] ) ) ); return; }
			if ( 'kt_selftest' === $viec )    { self::tra( VHG_KeToan::selftest() ); return; }
			if ( 'kt_lichsu' === $viec )      { self::tra( VHG_KeToan::lich_su( isset( $d['coso'] ) ? $d['coso'] : '', isset( $d['nam'] ) ? $d['nam'] : '' ) ); return; }
			if ( 'kt_import' === $viec ) {
				/* Nhập doanh thu cũ GHI ĐÈ được cả tháng — chỉ Quản trị, không mở cho vai trò chốt. */
				if ( empty( $q['quan_tri'] ) ) {
					self::tra( array( 'ok' => false, 'ma' => 'khong_du_quyen',
						'error' => 'Nhập doanh thu cũ chỉ dành cho Quản lý hoặc Admin.' ) );
					return;
				}
				self::tra( VHG_KeToan::nhap_doanhthu( isset( $d['rows'] ) ? $d['rows'] : array(),
					! empty( $d['ghi_de'] ), ! empty( $d['duyet'] ),
					! isset( $d['tao_ghe'] ) || ! empty( $d['tao_ghe'] ), $boi ) );
				return;
			}
			self::tra( array( 'ok' => false, 'error' => 'Việc kế toán không rõ: ' . $viec ) );
			return;
		}

		if ( 'bat' === $viec || 'tat' === $viec || 'khoi_dong_lai' === $viec || 'mo_khoa' === $viec ) {
			$r = VHG_May::dat_lenh(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				'bat' === $viec ? 'on' : ( 'tat' === $viec ? 'off' : ( 'mo_khoa' === $viec ? 'mokhoa' : 'reboot' ) ),
				isset( $d['phut'] ) ? $d['phut'] : 0,
				/* Ghi TÊN NGƯỜI ĐANG CẦM PHIÊN, không lấy tên từ gói gửi lên. Luật 3: bật tay là
				   cho không một lượt, nên chữ ký phải là thứ người bấm không tự khai được. */
				$ai['name'],
				isset( $d['ly_do'] ) ? $d['ly_do'] : '' );
			self::tra( $r );
			return;
		}

		if ( 'test' === $viec ) {
			/* Chế độ kỹ thuật: bật (bat=1) / tắt (bat=0). Ghế trong chế độ này KHÔNG khoá lỗi. */
			$r = VHG_May::dat_lenh(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '', 'test',
				! empty( $d['bat'] ) ? 1 : 0, $ai['name'],
				isset( $d['ly_do'] ) ? $d['ly_do'] : '' );
			self::tra( $r );
			return;
		}

		if ( 'gan_ma' === $viec ) {
			/* 🔴 Gán ghế NGAY TRÊN ĐIỆN THOẠI. Người đi lắp ghế ở Aeon Tân Phú cầm cái điện
			 *    thoại, không cầm wp-admin. Bắt họ nhắn về văn phòng nhờ ai đó vào wp-admin gán
			 *    hộ là thêm một vòng chờ, và trong lúc chờ thì ghế đứng đó không thu được đồng nào.
			 *
			 * ⚠️ Ghi TÊN NGƯỜI CẦM PHIÊN vào nhật ký, không lấy tên từ gói gửi lên: gán mã là
			 *    đổi khoá của một dòng doanh thu, phải biết ai làm. */
			$r = VHG_May::gan_ma(
				isset( $d['ma_cu'] ) ? $d['ma_cu'] : '',
				isset( $d['ma_moi'] ) ? $d['ma_moi'] : '',
				isset( $d['coso_id'] ) ? (int) $d['coso_id'] : null );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' gán mã ghế: ' . (string) ( isset( $d['ma_cu'] ) ? $d['ma_cu'] : '' )
					. ' -> ' . (string) ( isset( $d['ma_moi'] ) ? $d['ma_moi'] : '' ) ) );
			}
			self::tra( $r );
			return;
		}

		/* ---- TAB QUẢN LÝ GHẾ: địa điểm + ghế (chỉ quản trị, đã chặn ở duoc_lam) ---------------- */
		if ( 'coso_luu' === $viec ) {
			$r = VHG_May::luu_coso( isset( $d['id'] ) ? (int) $d['id'] : 0,
				isset( $d['ten'] ) ? $d['ten'] : '',
				isset( $d['tinh'] ) ? $d['tinh'] : null );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' lưu địa điểm: ' . (string) ( isset( $d['ten'] ) ? $d['ten'] : '' ) ) );
			}
			self::tra( $r ); return;
		}
		if ( 'coso_xoa' === $viec ) {
			$r = VHG_May::xoa_coso( isset( $d['id'] ) ? (int) $d['id'] : 0 );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' xoá địa điểm id=' . (int) ( isset( $d['id'] ) ? $d['id'] : 0 ) ) );
			}
			self::tra( $r ); return;
		}
		if ( 'may_them' === $viec ) {
			/* Thêm/lưu ghế: mã + địa điểm (giá/thời lượng để trống = dùng ô chung). */
			$r = VHG_May::luu_may( array(
				'ma'      => isset( $d['ma'] ) ? $d['ma'] : '',
				'coso_id' => isset( $d['coso_id'] ) ? (int) $d['coso_id'] : 0,
			) );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' thêm ghế: ' . (string) ( isset( $d['ma'] ) ? $d['ma'] : '' ) ) );
			}
			self::tra( $r ); return;
		}
		if ( 'may_xoa' === $viec ) {
			$r = VHG_May::xoa_may( isset( $d['ma'] ) ? (string) $d['ma'] : '' );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
					$ai['name'] . ' xoá ghế: ' . (string) ( isset( $d['ma'] ) ? $d['ma'] : '' ) ) );
			}
			self::tra( $r ); return;
		}
		if ( 'may_coso' === $viec ) {
			$r = VHG_May::dat_coso( isset( $d['ma'] ) ? (string) $d['ma'] : '',
				isset( $d['coso_id'] ) ? (int) $d['coso_id'] : 0 );
			self::tra( $r ); return;
		}

		if ( 'ma_tra' === $viec ) {
			/* Nhân viên tra hộ khách QUÊN PIN — chỉ cần số điện thoại.
			   ⚠️ Đường này bỏ qua PIN, nên nó CHỈ được nằm ở đây: trang `/ghe` đã qua cổng PIN
			      nhân viên. Trang của khách không có việc này, và không được có. */
			$sdt_tra = isset( $d['sdt'] ) ? $d['sdt'] : '';
			$kq_ma   = VHG_Ma::tra_nhan_vien( $sdt_tra );
			/* 🔴 TRA MỘT LẦN, RA CẢ HAI. Khách đứng ở quầy nói "em có mua trước" — họ không nhớ
			   mình mua MÃ hay nạp VÍ, và không có lý do gì phải nhớ. Bắt nhân viên tra hai chỗ
			   là có ngày họ tra một chỗ, thấy trống, rồi nói với khách là "không có gì". */
			$kq_vi = VHG_Vi::tra_nhan_vien( $sdt_tra );
			if ( ! empty( $kq_vi['ok'] ) ) {
				$kq_ma['vi'] = array(
					'dung'    => (int) $kq_vi['so_du']['dung'],
					'cho'     => (int) $kq_vi['so_du']['cho'],
					'tong'    => (int) $kq_vi['so_du']['tong'],
					'con_cho' => (int) $kq_vi['so_du']['con_cho'],
					'khoa'    => empty( $kq_vi['so_du']['khoa'] ) ? 0 : 1,
				);
			}
			/* Không có mã NHƯNG có ví thì vẫn là một lượt tra THÀNH CÔNG — đừng trả `ok=false`
			   chỉ vì một trong hai vế trống. */
			if ( empty( $kq_ma['ok'] ) && ! empty( $kq_vi['ok'] ) ) {
				$kq_ma = array( 'ok' => true, 'ds' => array(), 'vi' => $kq_ma['vi'] );
			}
			self::tra( $kq_ma );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * NHÂN VIÊN TIÊU VÍ HỘ KHÁCH.
		 *
		 * Anh Thắng 23/08/2026: *"khách không biết bấm nhiều lần, dẫn đến khóa 10p. Vậy nhân
		 * viên có thể vào điều khiển ghế, nhập số điện thoại khách, hiện số dư và kích ghế giúp
		 * luôn"*.
		 *
		 * ⚠️ Đường này BỎ QUA PIN của khách, nên nó CHỈ được nằm ở đây: trang `/ghe` đã qua cổng
		 *    PIN nhân viên. Trang của khách không có việc này, và không được có.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 'vi_tra_nv' === $viec ) {
			self::tra( VHG_Vi::tra_nhan_vien( isset( $d['sdt'] ) ? $d['sdt'] : '' ) );
			return;
		}

		if ( 'vi_tieu_nv' === $viec ) {
			/* 🔴 Tên người bấm là BẮT BUỘC — xem VHG_Vi::tieu_nhan_vien(). Đây là tiêu tiền của
			   khách mà không có PIN của họ; một dòng sổ không tên là một cửa hậu. */
			self::tra( VHG_Vi::tieu_nhan_vien(
				isset( $d['sdt'] ) ? $d['sdt'] : '',
				isset( $d['menh_gia'] ) ? (int) $d['menh_gia'] : 0,
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				(string) $ai['name'] ) );
			return;
		}

		if ( 'qua_trao' === $viec ) {
			/* Trao quà là một hành động có hậu quả (một phần quà chỉ trao được một lần), nhưng
			   KHÔNG phải quyết định về tiền như huỷ mã — người đứng quầy trao được. */
			self::tra( VHG_Vi::trao_qua(
				isset( $d['id'] ) ? (int) $d['id'] : 0, (string) $ai['name'] ) );
			return;
		}

		if ( 'ma_huy' === $viec ) {
			/* 🔴 Huỷ mã là quyết định về TIỀN: khách đã trả rồi. Chỉ Admin và Quản lý — người
			   đứng quầy không nên tự quyết chuyện hoàn/không hoàn, và nếu có quyết thì cũng
			   không ai biết để hỏi lại. Chốt nằm ở `VHG_Auth::VIEC_QUAN_TRI`, đã chặn ở đầu hàm. */
			$r = VHG_Ma::huy( isset( $d['ma'] ) ? $d['ma'] : '',
				isset( $d['ly_do'] ) ? $d['ly_do'] : '', $ai['name'] );
			if ( ! empty( $r['ok'] ) ) {
				VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' => $ai['name']
					. ' huỷ mã ' . (string) ( isset( $d['ma'] ) ? $d['ma'] : '' )
					. ' — ' . (string) ( isset( $d['ly_do'] ) ? $d['ly_do'] : '' ) ) );
			}
			self::tra( $r );
			return;
		}

		if ( 'so_may' === $viec ) {
			/* Số liệu MỘT ghế cho màn chốt ca. Gọi riêng chứ không nhét vào lượt `so_lieu`: nó
			   chỉ cần khi người ta bấm Thu tiền mặt, mà `so_lieu` chạy mỗi lần tải trang. */
			$ma = trim( (string) ( isset( $d['ma_may'] ) ? $d['ma_may'] : '' ) );
			/* `ds_may_theo_ma()` chứ không `may()`: chỉ bản này mới kèm tên cơ sở (có JOIN). Màn
			   chốt ca phải nói rõ đang đếm tiền của ghế nào Ở ĐÂU — người đi thu tiền đi nhiều
			   cơ sở trong một buổi. */
			$bd = VHG_May::ds_may_theo_ma();
			$m  = isset( $bd[ $ma ] ) ? $bd[ $ma ] : null;
			if ( ! $m ) { self::tra( array( 'ok' => false, 'error' => 'Không thấy ghế ' . $ma . '.' ) ); return; }
			self::tra( array( 'ok' => true, 'ma_may' => $ma,
				'coso' => (string) ( isset( $m['coso_ten'] ) ? $m['coso_ten'] : '' ),
				'gia'  => (int) VHG_May::ty_le_cua( $m )['gia'],
				'hom_nay' => VHG_Thu::tong_may( $ma, 'today' ),
				'tuan'    => VHG_Thu::tong_may( $ma, 'week' ),
				'thang'   => VHG_Thu::tong_may( $ma, 'month' ),
				'tat_ca'  => VHG_Thu::tong_may( $ma, 'all' ) ) );
			return;
		}

		if ( 'tien_mat' === $viec ) {
			self::tra( VHG_Thu::thu_tien_mat(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				isset( $d['so_tien'] ) ? $d['so_tien'] : 0,
				$ai['name'] ) );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * QUỸ TIỀN MẶT — CHỐT CA THEO GHẾ, VÀ NỘP TIỀN VỀ QUẦY.
		 *
		 * Anh Thắng 23/08/2026: *"Mở ứng dụng tới quét QR tại máy. Bấm thu tiền (chốt ca, dữ liệu
		 * chốt ca). Nhập số tiền mặt, chỉ số máy tiền mặt"*.
		 *
		 * ⚠️ TÊN NGƯỜI LẤY TỪ PHIÊN, KHÔNG NHẬN TỪ GÓI TIN — cả ba việc dưới đây. Nhận từ gói tin
		 *    là ai cũng chốt hộ, nộp hộ, xoá nợ tiền mặt hộ người khác.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 'chot_xem' === $viec ) {
			/* Cơ sở lấy từ PHIÊN, không nhận từ gói tin — xem chú thích trong VHG_Quy. */
			self::tra( VHG_Quy::truoc_khi_chot(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '', (string) $ai['coso'] ) );
			return;
		}

		if ( 'chot_luu' === $viec ) {
			self::tra( VHG_Quy::chot(
				isset( $d['ma_may'] ) ? $d['ma_may'] : '',
				isset( $d['chi_so'] ) ? $d['chi_so'] : 0,
				isset( $d['tien_dem'] ) ? $d['tien_dem'] : 0,
				(string) $ai['name'],
				isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '',
				/* Mã lượt do ĐIỆN THOẠI sinh — app gửi lại khi sóng yếu thì không ghi hai lần.
				   Trang web không gửi, và không cần: bấm hai lần trên web là hai lần cố ý. */
				isset( $d['ma_lan'] ) ? $d['ma_lan'] : '',
				(string) $ai['coso'] ) );
			return;
		}

		if ( 'chot_coso' === $viec ) {
			/* PREFETCH: máy trạm lấy số liệu chốt của CẢ CƠ SỞ trong một lượt, để ghế sau chốt
			   ngay không phải hỏi lại. Cơ sở lấy TỪ PHIÊN — cùng rào với chot_xem/chot_luu, nên
			   người gắn cơ sở chỉ tải được cơ sở mình. */
			self::tra( VHG_Quy::chot_coso( (string) $ai['coso'] ) );
			return;
		}

		if ( 'nhat_ky_may' === $viec ) {
			/* Lịch sử bật/tắt ghế — chỉ quản trị (đã chặn ở duoc_lam qua VIEC_QUAN_TRI). */
			$ky = isset( $d['ky'] ) ? (string) $d['ky'] : 'week';
			$ky = ( in_array( $ky, array( 'today', 'week', 'month', 'year', 'all' ), true )
				|| preg_match( '/^\d{4}-\d{2}$/', (string) $ky ) ) ? $ky : 'week';
			self::tra( array( 'ok' => true, 'ky' => $ky,
				'ds'  => VHG_May::ds_bat_tat( $ky, 500 ),
				'gom' => VHG_May::tong_bat_tat_may( $ky ) ) );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * QUẢN LÝ PIN BÁO CÁO (bc_pin_*) — ADMIN hoặc vai trò QUẢN TRỊ. Đây là chỗ CẤP DANH TÍNH
		 * thu tiền: cấp một PIN gán sai cơ sở là cho người ta xem/nhập doanh thu cơ sở không phải
		 * của họ. Vì thế các việc này GIỮ cổng token + quyền Quản trị (khác các `bc_*` của nhân
		 * viên chạy trước cổng bằng PIN riêng).
		 * 🔴 Anh Thắng 28/08/2026: nhóm "Quản trị" được set PIN báo cáo cho nhân viên mình quản lý.
		 *    Mặc định nhóm này = Admin + Quản lý; Admin khai thêm vai trò khác ở bảng phân quyền.
		 *    PIN thật do người dùng nhập ở đây — KHÔNG seed trong mã (repo công khai). */
		if ( 0 === strpos( $viec, 'bc_pin_' ) ) {
			if ( ! VHG_Auth::la_quan_tri( $ai['role'] ) ) {
				self::tra( array( 'ok' => false, 'ma' => 'khong_du_quyen',
					'error' => 'Chỉ Admin hoặc vai trò Quản trị mới quản lý được PIN báo cáo.' ) );
				return;
			}
			if ( 'bc_pin_ds' === $viec ) {
				/* Kèm danh sách nhân viên từ nhân sự (Admin-only) để chọn nhanh: điền tên + PIN +
				   tích cơ sở của đúng người. Bỏ PIN rỗng. */
				$ns = array();
				$us = VHG_Auth::users();
				if ( ! is_wp_error( $us ) ) {
					foreach ( (array) $us as $u ) {
						$pin = (string) ( isset( $u['pin'] ) ? $u['pin'] : '' );
						$ten = (string) ( isset( $u['ten'] ) ? $u['ten'] : '' );
						if ( '' === $ten ) { continue; }
						$ns[] = array( 'ten' => $ten, 'pin' => $pin,
							'coso' => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
							'vaiTro' => (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : '' ) );
					}
				}
				self::tra( array( 'ok' => true, 'ds' => VHG_BaoCao::pin_ds(), 'nhan_su' => $ns ) ); return;
			}
			if ( 'bc_pin_luu' === $viec ) {
				$r = VHG_BaoCao::pin_luu( $d );
				if ( ! empty( $r['ok'] ) ) {
					VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
						$ai['name'] . ' lưu PIN báo cáo: ' . (string) ( isset( $d['ten'] ) ? $d['ten'] : '' ) ) );
				}
				self::tra( $r ); return;
			}
			if ( 'bc_pin_xoa' === $viec ) {
				$r = VHG_BaoCao::pin_xoa( isset( $d['pin_xoa'] ) ? $d['pin_xoa'] : '' );
				if ( ! empty( $r['ok'] ) ) {
					VHG_Nhat_Ky::ghi( array( 'nguon' => 'he-thong', 'ghi_chu' =>
						$ai['name'] . ' xoá PIN báo cáo.' ) );
				}
				self::tra( $r ); return;
			}
			self::tra( array( 'ok' => false, 'error' => 'Việc PIN không rõ: ' . $viec ) );
			return;
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * CẤU HÌNH NHÂN SỰ NGAY TRÊN TRANG /ghe.
		 *
		 * Anh Thắng 23/08/2026: *"chưa thấy tab cấu hình trên wed"*.
		 *
		 * Màn khai người dùng vốn nằm trong wp-admin, mà anh Thắng điều hành cả hệ này từ trang
		 * /ghe trên điện thoại. Bắt mở wp-admin trên điện thoại để thêm một người thu là việc sẽ
		 * không ai làm — rồi cả nhà dùng chung một tài khoản, và toàn bộ phân quyền vừa dựng
		 * thành số 0.
		 *
		 * 🔴 CHỈ ADMIN. Đây là chỗ CẤP PIN — cấp một PIN sai vai trò là cho người ta xem doanh
		 *    thu cả chuỗi. Quản lý xem được doanh thu, nhưng không được tự cấp quyền cho ai.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( 0 === strpos( $viec, 'ch_' ) ) {
			if ( 'Admin' !== $ai['role'] ) {
				self::tra( array( 'ok' => false, 'ma' => 'khong_du_quyen',
					'error' => 'Chỉ Admin mới đổi được cấu hình nhân sự.' ) );
				return;
			}
			self::tra( self::cau_hinh( $viec, $d, $ai ) );
			return;
		}

		if ( 'quy_toi' === $viec ) {
			self::tra( array( 'ok' => true, 'cam' => VHG_Quy::dang_cam( (string) $ai['name'] ) ) );
			return;
		}

		if ( 'nop_tao' === $viec ) {
			self::tra( VHG_Quy::nop( (string) $ai['name'],
				isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '',
				isset( $d['ma_lan'] ) ? $d['ma_lan'] : '' ) );
			return;
		}

		/* 🔴 XÁC NHẬN ĐÃ NHẬN TIỀN LÀ QUYẾT ĐỊNH VỀ TIỀN — chỉ Admin và Quản lý.
		   Người đứng quầy tự xác nhận lượt nộp của chính mình thì cái sổ này không còn nói được
		   gì cả: nó chỉ ghi lại điều người nộp muốn nó ghi. Huỷ lượt nộp cũng vậy. */
		/* Hai việc này đã nằm trong `VIEC_QUAN_TRI` nên chốt chung ở đầu hàm đã chặn rồi —
		   không kiểm lại lần nữa ở đây. Hai chốt cho một luật là hai chỗ phải nhớ sửa. */
		if ( 'nop_nhan' === $viec || 'nop_huy' === $viec ) {
			if ( 'nop_huy' === $viec ) {
				self::tra( VHG_Quy::huy_nop( isset( $d['id'] ) ? (int) $d['id'] : 0 ) );
				return;
			}
			self::tra( VHG_Quy::nhan(
				isset( $d['id'] ) ? (int) $d['id'] : 0,
				isset( $d['so_tien_nhan'] ) ? (int) $d['so_tien_nhan'] : 0,
				(string) $ai['name'],
				isset( $d['ghi_chu'] ) ? $d['ghi_chu'] : '' ) );
			return;
		}

		self::tra( array( 'ok' => false, 'error' => 'Việc không rõ: ' . $viec ) );
	}

	/**
	 * Toàn bộ số liệu một màn, MỘT LƯỢT GỌI.
	 *
	 * Gọi tách ra bốn lượt thì trên 4G ở trung tâm thương mại là bốn cơ hội hỏng, và màn hình
	 * hiện nửa vời — doanh thu có mà tình trạng ghế trống, người đọc không biết đang xem cái gì.
	 */
	private static function so_lieu( $ky, $ai ) {
		$ky  = ( in_array( $ky, array( 'today', 'week', 'month', 'year', 'all' ), true )
			|| preg_match( '/^\d{4}-\d{2}$/', (string) $ky ) ) ? $ky : 'today';   // cho phép tháng bất kỳ

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 NHÂN VIÊN NHẬN MỘT GÓI TIN KHÁC HẲN, KHÔNG PHẢI CÙNG GÓI RỒI GIẤU BỚT Ở GIAO DIỆN.
		 *
		 * Anh Thắng 23/08/2026 chốt: nhân viên không xem tiền của người khác, không xem tổng
		 * doanh thu, không gán mã ghế, không điều khiển ghế.
		 *
		 * Gửi đủ rồi để JS ẩn đi là KHÔNG giấu được gì cả: mở tab Network trên chính điện thoại
		 * của mình là thấy nguyên doanh thu cả hệ thống, tiền từng người đang cầm, số điện thoại
		 * khách. Cắt ngay từ máy chủ thì thứ không được xem KHÔNG BAO GIỜ rời khỏi máy chủ.
		 *
		 * ⚠️ Người thu vẫn phải thấy ĐỦ để làm việc của mình: tiền mình đang cầm, lượt mình đã
		 *    chốt, và danh sách ghế (để biết ghế nào mất kết nối). Cắt tới mức không làm việc
		 *    được thì họ sẽ đi mượn tài khoản quản lý — và lúc đó phân quyền thành số 0.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		$q = VHG_Auth::quyen_cua( $ai['role'] );
		if ( empty( $q['quan_tri'] ) ) { return self::so_lieu_khong_quan_tri( $ky, $ai, $q ); }

		$t   = VHG_Thu::tong_hop( $ky );
		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * DOANH THU DASHBOARD: WEBHOOK THIẾU THÌ LẤY BÁO-CÁO (anh Thắng 27/08/2026).
		 *
		 * "Doanh thu kỳ" + bảng địa điểm vốn chỉ tính TIỀN THẬT VÀO (webhook QR + chốt-ca) từ bảng
		 * `thu`. Tháng cũ nhập tay chưa có webhook → cơ sở hiện 0đ. Với cơ sở nào trong kỳ KHÔNG có
		 * tiền thật (tong=0) thì thay bằng tổng BÁO-CÁO (bc) của cơ sở đó — KHÔNG cộng đôi với cơ sở
		 * đã có webhook. Ô lấy từ báo cáo gắn cờ `nguon_bc` để giao diện ghi rõ nguồn.
		 * ═════════════════════════════════════════════════════════════════════════════════════ */
		if ( class_exists( 'VHG_KeToan' ) ) {
			$bc_ct = VHG_KeToan::doanhthu_ky( $ky );
			if ( ! empty( $bc_ct ) ) {
				$idx = array();
				foreach ( $t['theo_coso'] as $i => $c ) { $idx[ VHG_BaoCao::squash( $c['coso'] ) ] = $i; }
				foreach ( $bc_ct as $ck => $b ) {
					if ( (int) $b['tong'] <= 0 ) { continue; }
					if ( isset( $idx[ $ck ] ) ) {
						$i = $idx[ $ck ];
						if ( (int) $t['theo_coso'][ $i ]['tong'] > 0 ) { continue; }   // đã có webhook → không đụng
						$t['theo_coso'][ $i ]['tong']     = (int) $b['tong'];
						$t['theo_coso'][ $i ]['qr']       = (int) $b['qr'];
						$t['theo_coso'][ $i ]['tien_mat'] = (int) $b['tien_mat'];
						$t['theo_coso'][ $i ]['so_luot']  = (int) $b['so_luot'];
						$t['theo_coso'][ $i ]['nguon_bc'] = 1;
					} else {
						$t['theo_coso'][] = array( 'coso' => $b['coso'], 'so_may' => 0,
							'so_luot' => (int) $b['so_luot'], 'qr' => (int) $b['qr'],
							'tien_mat' => (int) $b['tien_mat'], 'tong' => (int) $b['tong'], 'nguon_bc' => 1 );
					}
					$t['tong']     += (int) $b['tong'];
					$t['qr']       += (int) $b['qr'];
					$t['tien_mat'] += (int) $b['tien_mat'];
				}
			}
		}
		/* Chỉ số máy đếm lần chốt gần nhất, MỘT lượt hỏi cho tất cả ghế — xem
		   VHG_Quy::chot_cuoi_theo_may(). */
		$cs_cuoi = VHG_Quy::chot_cuoi_theo_may();
		$may = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$may[] = array(
				'chot' => isset( $cs_cuoi[ $m['ma'] ] ) ? $cs_cuoi[ $m['ma'] ] : null,
				'ma'      => $m['ma'],
				'coso'    => $m['coso_ten'] ? $m['coso_ten'] : '',
				'song'    => ! empty( $m['con_song'] ),
				'tt'      => (string) $m['trang_thai'],
				'con_lai' => (int) $m['con_lai'],
				'cho'     => (int) $m['cho'],
				'gia'     => (int) VHG_May::ty_le_cua( $m )['gia'],
				'phut'    => (int) VHG_May::ty_le_cua( $m )['phut'],
				/* Cục nhận tiền: gửi CẢ mã lẫn câu giải thích. Người đứng quầy không tra bảng mã
				   — mà đây lại đúng là người phải chạy ra xem cái máy. */
				'tm'      => (string) $m['tm_loi'],
				'tm_cu'   => (string) $m['tm_cuoi'],
				/* Gửi CẢ hai ngôn ngữ trong một lượt. Gửi theo ngôn ngữ đang chọn thì mỗi lần
				   đổi VI/EN lại phải gọi lại máy chủ — trên 4G là vài giây đứng nhìn cho một
				   việc hoàn toàn nằm trong máy người ta. */
				'tm_chu'    => VHG_May::loi_tien_chu( '' !== (string) $m['tm_loi']
					? $m['tm_loi'] : $m['tm_cuoi'] ),
				'tm_chu_en' => VHG_May::loi_tien_chu( '' !== (string) $m['tm_loi']
					? $m['tm_loi'] : $m['tm_cuoi'], 'en' ),
				'khoa'    => ! empty( $m['khoa'] ) ? 1 : 0,   // ghế đang KHÓA lỗi
				'kt'      => ! empty( $m['kt'] ) ? 1 : 0,     // ghế đang chế độ KỸ THUẬT (test)
			);
		}
		/* Ghế đang chờ gán mã + danh sách cơ sở: gửi kèm luôn trong lượt số liệu, không thêm
		   lượt gọi. Xem ghi chú "một lượt gọi ra đủ màn" ở dưới. */
		$cho_gan = array();
		foreach ( VHG_May::chua_gan() as $g ) {
			$cho_gan[] = array( 'ma' => $g['ma'], 'mac' => $g['mac'],
				'song' => ! empty( $g['con_song'] ), 'luc' => (string) $g['nhip_luc'] );
		}
		$ds_coso = array();
		foreach ( VHG_May::ds_coso() as $c ) {
			$ds_coso[] = array( 'id' => (int) $c['id'], 'ten' => (string) $c['ten'],
				'tinh' => (string) ( isset( $c['tinh'] ) ? $c['tinh'] : '' ) );
		}
		/* NHẬT KÝ BẬT TỪ XA — gửi kèm trong chính lượt số liệu, không thêm lượt gọi. Mỗi lần bấm
		   Bật là CHO KHÔNG một lượt: cuối tháng nhìn "ghế chạy 180 lượt, thu 140" thì 40 lượt kia
		   phải giải thích được bằng con số, không bằng trí nhớ. Kèm tổng THÁNG bất kể đang xem kỳ
		   nào — câu hỏi thật lúc đối chiếu luôn là "tháng này bao nhiêu". */
		$bat_ky    = VHG_May::tong_lenh( $ky );
		$bat_may   = VHG_May::tong_lenh_may( $ky );
		$bat_thang = VHG_May::tong_lenh( 'month' );
		$bat_ngay  = VHG_May::tong_lenh_ngay( $ky );
		$bat_ds    = array();
		foreach ( VHG_May::ds_lenh_bat( $ky, 60 ) as $l ) {
			$bat_ds[] = array( 'luc' => (string) $l['tao_luc'], 'ma' => (string) $l['ma_may'],
				'phut' => (int) $l['phut'], 'nguoi' => (string) $l['nguoi'],
				'ly_do' => (string) $l['ly_do'],
				/* `gui_luc` rỗng = ghế chưa lấy lệnh (đang mất mạng). Vẫn tính vào nhật ký, nhưng
				   phải hiện ra: người đọc cần phân biệt "đã chạy" với "sẽ chạy khi ghế lên". */
				'da_gui' => '' !== trim( (string) $l['gui_luc'] ) );
		}

		$cho = array();
		foreach ( VHG_May::ds_cho( true, 50 ) as $c ) {
			$cho[] = array( 'luc' => $c['tao_luc'], 'ma_may' => $c['ma_may'],
				'so_tien' => (int) $c['so_tien'], 'ma_lenh' => $c['ma_lenh'] );
		}
		$gd = array();
		foreach ( VHG_Thu::ds( $ky, 60 ) as $r ) {
			$gd[] = array(
				'luc'     => $r['luc'],
				'may'     => '' !== $r['ma_may'] ? $r['ma_may'] : ( '' !== $r['ten_khai'] ? $r['ten_khai'] : '' ),
				'nguon'   => $r['nguon'],
				'so_tien' => (int) $r['so_tien'],
				'noi_dung' => $r['noi_dung'],
			);
		}
		return array( 'ok' => true, 'ky' => $ky, 'ai' => $ai, 'tong' => $t,
			'may' => $may, 'cho' => $cho, 'gd' => $gd,
			'choGan' => $cho_gan, 'coso' => $ds_coso,
			'bat' => array( 'ky' => $bat_ky, 'thang' => $bat_thang,
				'ngay' => $bat_ngay, 'may' => $bat_may, 'ds' => $bat_ds ),
			/* Tab Thu tiền: tách hai đường tiền mặt (ghế nuốt / người thu) — xem khối giải thích
			   trên VHG_Thu::ND_GHE_NUOT. Gửi kèm trong lượt này, không thêm lượt gọi. */
			'thu' => array( 'ds' => VHG_Thu::ds_tien_mat( $ky, 80 ),
				'may' => array_values( VHG_Thu::theo_may_tien_mat( $ky ) ) ),
			/* Tab Quỹ: chốt ca theo ghế + tiền đang trên tay + lượt nộp chờ xác nhận.
			   ⚠️ `toi` là tiền trên tay CỦA CHÍNH NGƯỜI ĐANG ĐĂNG NHẬP, tính từ tên trong phiên
			      — nó là con số họ phải nộp, nên không được lấy từ đâu khác. */
			'quy' => array(
				'tong'    => VHG_Quy::tong( $ky ),
				'toi'     => VHG_Quy::dang_cam( (string) $ai['name'] ),
				'ca'      => VHG_Quy::bao_cao_ca( (string) $ai['name'] ),
				'cam'     => VHG_Quy::ai_dang_cam(),
				'cho'     => VHG_Quy::nop_cho( 50 ),
				'chot'    => VHG_Quy::ds_chot( $ky, 120 ),
				'nop'     => VHG_Quy::ds_nop( $ky, 80 ),
				'nguoi'   => VHG_Quy::theo_nguoi( $ky ),
				'don_vi'  => VHG_Quy::don_vi(),
				'toi_la'  => (string) $ai['name'],
				'quyen_nhan' => in_array( $ai['role'], array( 'Admin', 'Quản lý' ), true ) ? 1 : 0 ),
			/* Mã mua trước: tổng kỳ + khoản đang NỢ (mã không hết hạn nên nó chỉ cộng lên). */
			'ma' => array( 'tong' => VHG_Ma::tong( $ky ), 'no' => VHG_Ma::tien_no(),
				'ds' => VHG_Ma::ds( $ky, 120 ), 'quyen_huy' =>
					in_array( $ai['role'], array( 'Admin', 'Quản lý' ), true ) ? 1 : 0 ),
			/* 🔴 VÍ ĐI CÙNG MÃ, KHÔNG TÁCH RA TAB RIÊNG.
			   Anh Thắng 23/08/2026: *"trên wed cũng chưa có số dư của ví khách"*.
			   Hai thứ này cùng trả lời một câu hỏi của nhân viên đang đứng ở quầy: *"khách này
			   còn gì chưa dùng?"* — tách hai tab là bắt họ nhớ khách mua kiểu nào trước khi tra,
			   mà chính khách cũng không nhớ.
			   ⚠️ `so_du` là NỢ, đúng như `VHG_Ma::tien_no()` bên trên: tiền đã thu, dịch vụ chưa
			      trả. Hai con số phải đứng CẠNH NHAU thì mới ra được tổng nợ thật. */
			'vi' => array( 'no' => VHG_Vi::tong_no(), 'ds' => self::vi_gon( VHG_Vi::ds_vi( 60 ) ),
				'co_ban' => VHG_Vi::goi_nap() ? 1 : 0 ),
			/* Quà chờ trao — việc CÓ THẬT của người đứng quầy, nên nó phải nằm trên màn họ mở
			   cả ca chứ không nằm trong wp-admin. Số điện thoại che ngay từ máy chủ. */
			'qua' => array( 'cho' => self::qua_gon( VHG_Vi::qua_cho_trao( 40 ) ),
				'tong' => VHG_Vi::tong_qua(), 'bat' => VHG_Vi::tich_cf()['bat'] ? 1 : 0 ),
			/* Bảng giá để nhân viên chọn gói khi tiêu ví hộ khách. Dùng CHUNG hàm với trang
			   khách — hai nơi tính giá khác nhau là nhân viên đọc một đằng, khách một nẻo. */
			'goi' => VHG_Ma::ds_menh_gia(),
			'quyen' => $q,
			'luc' => current_time( 'H:i:s' ) );
	}

	/**
	 * Số liệu và thao tác của tab Cấu hình. Mọi việc bắt đầu bằng `ch_`.
	 *
	 * ⚠️ KHÔNG BAO GIỜ GỬI PIN RA NGOÀI, kể cả cho Admin. Bảng chỉ nói PIN dài mấy số — đủ để
	 *    biết mình đang gõ thiếu hay thừa. Quên PIN thì xoá người đó rồi thêm lại; in PIN ra màn
	 *    là một ảnh chụp màn hình gửi nhầm nhóm chat và cả chuỗi mất doanh thu.
	 */
	private static function cau_hinh( $viec, $d, $ai ) {
		if ( 'ch_xem' === $viec ) {
			$ds = array();
			foreach ( (array) get_option( 'vhg_nguoidung' ) as $i => $x ) {
				$x = (array) $x;
				$ten  = (string) ( isset( $x['ten'] ) ? $x['ten'] : '' );
				$vt   = (string) ( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' );
				$coso = (string) ( isset( $x['coso'] ) ? $x['coso'] : '' );
				/* "Bạn" = ĐÚNG dòng đang đăng nhập (tên+cơ sở+vai trò), không phải mọi dòng trùng tên.
				   Trùng tên (vd hệ nhân sự đẩy sang thêm 1 bản) thì bản kia vẫn xoá được. */
				$la_ban = ( $ten === (string) $ai['name']
					&& $coso === (string) ( isset( $ai['coso'] ) ? $ai['coso'] : '' )
					&& $vt === (string) ( isset( $ai['role'] ) ? $ai['role'] : '' ) );
				$ds[] = array(
					'i'       => (int) $i,
					'ten'     => $ten,
					'vai_tro' => $vt,
					'coso'    => $coso,
					'pin_dai' => strlen( (string) ( isset( $x['pin'] ) ? $x['pin'] : '' ) ),
					'la_ban'  => $la_ban ? 1 : 0,
				);
			}
			$cs = array();
			foreach ( VHG_May::ds_coso() as $c ) { $cs[] = (string) $c['ten']; }
			return array( 'ok' => true,
				'nguoi'      => $ds,
				'coso'       => $cs,
				'vai_tro'    => VHG_Auth::VAI_TRO_TAT_CA,
				'vao'        => VHG_Auth::vai_tro_vao(),
				'chot'       => VHG_Auth::vai_tro_chot(),
				'giup'       => VHG_Auth::vai_tro_giup_khach(),
				'quantri'    => VHG_Auth::vai_tro_quan_tri(),
				'nguon'      => (string) get_option( 'vhg_nguon_nguoidung', 'chung' ),
				'don_vi'     => VHG_Quy::don_vi(),
				'toi_la'     => (string) $ai['name'] );
		}

		if ( 'ch_them' === $viec ) {
			/* Dùng CHUNG hàm với wp-admin. Chép ra bản thứ hai là hai bộ luật cho một việc —
			   chỗ này quên chặn PIN trùng, chỗ kia quên chặn PIN dễ đoán. */
			return VHG_Admin::them_nguoi_dung(
				isset( $d['ten'] ) ? $d['ten'] : '',
				isset( $d['pin'] ) ? $d['pin'] : '',
				isset( $d['vai_tro'] ) ? $d['vai_tro'] : '',
				isset( $d['coso'] ) ? $d['coso'] : '' );
		}

		if ( 'ch_xoa' === $viec ) {
			$ds = array_values( (array) get_option( 'vhg_nguoidung' ) );
			$i  = isset( $d['i'] ) ? (int) $d['i'] : -1;
			if ( ! isset( $ds[ $i ] ) ) { return array( 'ok' => false, 'error' => 'Không thấy người này.' ); }
			$x = (array) $ds[ $i ];
			/* 🔴 CHỈ CHẶN XOÁ ADMIN CUỐI CÙNG. Xoá một dòng KHÔNG cắt phiên đang chạy (token nằm ở
			   bảng `phien`, không tra lại danh sách người), nên xoá bản trùng tên/của chính mình là
			   an toàn miễn CÒN một Admin khác để đăng nhập lại về sau. Mất Admin cuối là hết đường
			   vào ngoài cơ sở dữ liệu. */
			if ( 'Admin' === (string) ( isset( $x['vaiTro'] ) ? $x['vaiTro'] : '' ) ) {
				$so_admin = 0;
				foreach ( $ds as $u ) { if ( 'Admin' === (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : '' ) ) { $so_admin++; } }
				if ( $so_admin <= 1 ) {
					return array( 'ok' => false, 'error' => 'Không xoá Admin cuối cùng — sẽ không còn ai đăng nhập lại được.' );
				}
			}
			$ten = (string) ( isset( $x['ten'] ) ? $x['ten'] : '' );
			array_splice( $ds, $i, 1 );
			update_option( 'vhg_nguoidung', array_values( $ds ) );
			return array( 'ok' => true, 'thong_bao' => 'Đã xoá ' . $ten . '.' );
		}

		if ( 'ch_vai_tro' === $viec ) {
			$loc = function ( $ds ) {
				$ra = array();
				foreach ( (array) $ds as $v ) {
					$v = (string) $v;
					if ( in_array( $v, VHG_Auth::VAI_TRO_TAT_CA, true ) && ! in_array( $v, $ra, true ) ) {
						$ra[] = $v;
					}
				}
				return $ra;
			};
			$vao     = $loc( isset( $d['vao'] ) ? $d['vao'] : array() );
			$chot    = $loc( isset( $d['chot'] ) ? $d['chot'] : array() );
			$giup    = $loc( isset( $d['giup'] ) ? $d['giup'] : array() );
			$quantri = $loc( isset( $d['quantri'] ) ? $d['quantri'] : array() );
			/* 🔴 ADMIN LUÔN CÓ, ở cả bốn danh sách. Lưu một danh sách thiếu Admin là tự khoá mình
			   ra khỏi chính cái màn vừa dùng để lưu nó, và không có đường tự mở lại. */
			$them_admin = function ( $ds ) {
				if ( ! in_array( 'Admin', $ds, true ) ) { array_unshift( $ds, 'Admin' ); }
				return $ds;
			};
			$vao     = $them_admin( $vao );
			$chot    = $them_admin( $chot );
			$giup    = $them_admin( $giup );
			$quantri = $them_admin( $quantri );
			update_option( 'vhg_vai_tro_vao', $vao );
			update_option( 'vhg_vai_tro_chot', $chot );
			update_option( 'vhg_vai_tro_giup', $giup );
			update_option( 'vhg_vai_tro_quantri', $quantri );
			return array( 'ok' => true, 'thong_bao' => 'Đã lưu phân quyền.' );
		}

		if ( 'ch_don_vi' === $viec ) {
			return VHG_Quy::luu_don_vi( isset( $d['don_vi'] ) ? $d['don_vi'] : 0 );
		}

		return array( 'ok' => false, 'error' => 'Việc cấu hình không rõ: ' . $viec );
	}

	/**
	 * Gói tin cho người KHÔNG PHẢI QUẢN TRỊ — dựng theo BỘ QUYỀN, không phải theo một cái tên.
	 *
	 * Anh Thắng 23/08/2026: *"Đấy là bạn Hotline bật ghế cho khách chứ không phải nhân viên.
	 * Nhân viên là các bạn thu tiền tại máy"*.
	 *
	 * 🔴 DỰNG RIÊNG, KHÔNG PHẢI `unset()` BỚT TỪ GÓI ĐẦY ĐỦ.
	 *    `unset()` là danh sách những thứ PHẢI BỎ, và danh sách đó phải nhớ cập nhật mỗi lần
	 *    thêm một khoá mới ở gói đầy đủ — quên một lần là rò một lần, im lặng. Dựng riêng thì
	 *    danh sách là những thứ ĐƯỢC GỬI, và quên ở đây chỉ làm thiếu chứ không làm rò.
	 *
	 * 🔴 VÀ DỰNG THEO QUYỀN, KHÔNG THEO VAI TRÒ. Một hàm `so_lieu_nhan_vien()` là đúng đúng một
	 *    ngày — tới lúc có vai trò thứ ba (Hotline) thì phải đẻ thêm một hàm nữa, rồi hàm thứ tư.
	 *    Hỏi `quyen_cua()` thì thêm vai trò mới chỉ là thêm một dòng khai, không đụng vào đây.
	 */
	private static function so_lieu_khong_quan_tri( $ky, $ai, $q ) {
		$toi = (string) $ai['name'];

		/* Danh sách ghế: ai cũng cần, nhưng ĐÃ BỎ giá và số phút.
		   · Người thu cần biết ghế nào mất kết nối — ghế mất mạng thì lệch máy-với-sổ là bình
		     thường, không phải mất tiền.
		   · Bạn Hotline cần đúng danh sách đó để bấm bật cho khách đang gọi tới. */
		$cs_cuoi = VHG_Quy::chot_cuoi_theo_may();
		$may = array();
		foreach ( VHG_May::ds_may() as $m ) {
			$may[] = array(
				/* Người thu cũng cần chỉ số lần trước: đứng cạnh ghế, so với màn máy đếm là
				   biết ngay ngăn đang có bao nhiêu — trước cả khi mở ngăn ra đếm. */
				'chot' => isset( $cs_cuoi[ $m['ma'] ] ) ? $cs_cuoi[ $m['ma'] ] : null,
				'ma'      => $m['ma'],
				'coso'    => $m['coso_ten'] ? $m['coso_ten'] : '',
				'song'    => ! empty( $m['con_song'] ),
				'tt'      => (string) $m['trang_thai'],
				'con_lai' => (int) $m['con_lai'],
				'tm'      => (string) $m['tm_loi'],
			);
		}

		/* Quỹ: phần CỦA MÌNH thì ai cũng thấy. Phần của người khác chỉ người chốt doanh số mới
		   thấy — đó đúng là việc của họ (nhận tiền và đối chiếu). */
		$chot_toi = array();
		foreach ( VHG_Quy::ds_chot( $ky, 200 ) as $c ) {
			if ( (string) $c['nguoi'] === $toi ) { $chot_toi[] = $c; }
		}
		$nop_toi = array();
		foreach ( VHG_Quy::ds_nop( $ky, 120 ) as $n ) {
			if ( (string) $n['nguoi'] === $toi ) { $nop_toi[] = $n; }
		}
		$ke_toan = ! empty( $q['chot_doanh_so'] );
		$quy = array(
			'toi'    => VHG_Quy::dang_cam( $toi ),
			/* Báo cáo ca: quãng CHƯA NỘP của chính người này — xem VHG_Quy::bao_cao_ca(). */
			'ca'     => VHG_Quy::bao_cao_ca( $toi ),
			'toi_la' => $toi,
			'don_vi' => VHG_Quy::don_vi(),
			/* Kế toán xem được MỌI lượt chốt: họ phải đối chiếu tiền nhận với lượt đã chốt. */
			'chot'   => $ke_toan ? VHG_Quy::ds_chot( $ky, 120 ) : $chot_toi,
			'nop'    => $ke_toan ? VHG_Quy::ds_nop( $ky, 80 ) : $nop_toi,
			'cam'    => $ke_toan ? VHG_Quy::ai_dang_cam() : array(),
			'cho'    => $ke_toan ? VHG_Quy::nop_cho( 50 ) : array(),
			'nguoi'  => $ke_toan ? VHG_Quy::theo_nguoi( $ky ) : array(),
			/* Rỗng chứ không thiếu khoá: giao diện đọc `q.tong.tren_tay` mà gặp `undefined` thì
			   nổ giữa lúc vẽ, và người dùng nhìn thấy một trang trắng. */
			'tong'   => $ke_toan ? VHG_Quy::tong( $ky )
				: array( 'tren_tay' => 0, 'cho_xac_nhan' => 0, 'so_cho' => 0,
					'chot_ky' => 0, 'lech_may' => 0, 'lech_dem' => 0 ),
			'quyen_nhan' => $ke_toan ? 1 : 0,
		);

		/* Kế toán cần danh sách cơ sở cho ô lọc ở tab Duyệt báo cáo (kt-duyet) — trước đây gửi
		   rỗng cho MỌI người không phải quản trị, kể cả kế toán, nên ô lọc luôn trống dù đúng
		   tab của họ. Người thu/Hotline không có tab dùng tới nó nên vẫn giữ rỗng cho gọn. */
		$ds_coso = array();
		if ( $ke_toan ) {
			foreach ( VHG_May::ds_coso() as $c ) {
				$ds_coso[] = array( 'id' => (int) $c['id'], 'ten' => (string) $c['ten'],
					'tinh' => (string) ( isset( $c['tinh'] ) ? $c['tinh'] : '' ) );
			}
		}
		$ra = array( 'ok' => true, 'ky' => $ky, 'ai' => $ai,
			'may' => $may, 'cho' => array(), 'gd' => array(),
			'choGan' => array(), 'coso' => $ds_coso,
			'quy' => $quy, 'quyen' => $q,
			'luc' => current_time( 'H:i:s' ) );

		/* Bạn Hotline cần BẢNG GIÁ để chọn gói khi tiêu ví hộ khách, và danh sách lượt đã trả
		   tiền mà ghế chưa nhận — đó chính là lý do khách gọi tới. Không kèm doanh thu. */
		if ( ! empty( $q['giup_khach'] ) ) {
			$ra['goi'] = VHG_Ma::ds_menh_gia();
			$cho_ = array();
			foreach ( VHG_May::ds_cho( true, 50 ) as $c ) {
				$cho_[] = array( 'luc' => $c['tao_luc'], 'ma_may' => $c['ma_may'],
					'so_tien' => (int) $c['so_tien'], 'ma_lenh' => $c['ma_lenh'] );
			}
			$ra['cho'] = $cho_;
		}
		return $ra;
	}

	/**
	 * Rút gọn danh sách ví trước khi gửi ra trình duyệt.
	 *
	 * 🔴 CHE SỐ ĐIỆN THOẠI, VÀ CẮT LUÔN SỐ ĐẦY ĐỦ RA KHỎI GÓI TIN.
	 *    Che ở giao diện là chưa đủ: số đầy đủ vẫn nằm trong gói JSON, và mở tab Network ra là
	 *    thấy — hoặc chỉ cần một dòng trong bảng điều khiển trình duyệt là xuất được cả danh
	 *    sách khách hàng. Phải cắt ở MÁY CHỦ, trước khi gửi.
	 *
	 * ⚠️ Cũng vì thế mà hàm này KHÔNG dùng chung với màn quản trị: màn kia chạy trong wp-admin,
	 *    đã qua cổng đăng nhập của WordPress và có quyền cao hơn. Trang `/ghe` là màn nhân viên
	 *    ca nào cũng mở, thường để nguyên trên một cái máy tính ở quầy.
	 *
	 * Bốn số cuối vẫn còn — đủ để nhân viên đối chiếu với khách đang đứng trước mặt.
	 */
	/** Quà chờ trao, đã che số điện thoại — cùng lý do với `vi_gon()`. */
	private static function qua_gon( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $q ) {
			$ra[] = array(
				'id'      => (int) $q['id'],
				'sdt_che' => VHG_Ma::sdt_che( isset( $q['sdt'] ) ? $q['sdt'] : '' ),
				'ghi_chu' => (string) ( isset( $q['ghi_chu'] ) ? $q['ghi_chu'] : '' ),
				'moc'     => (int) ( isset( $q['moc'] ) ? $q['moc'] : 0 ),
				'tao_luc' => (string) ( isset( $q['tao_luc'] ) ? $q['tao_luc'] : '' ),
			);
		}
		return $ra;
	}

	private static function vi_gon( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $v ) {
			$ra[] = array(
				'sdt_che'    => VHG_Ma::sdt_che( isset( $v['sdt'] ) ? $v['sdt'] : '' ),
				'so_du_dung' => (int) ( isset( $v['so_du_dung'] ) ? $v['so_du_dung'] : 0 ),
				'so_du_cho'  => (int) ( isset( $v['so_du_cho'] ) ? $v['so_du_cho'] : 0 ),
				'da_nap'     => (int) ( isset( $v['da_nap'] ) ? $v['da_nap'] : 0 ),
				'da_tieu'    => (int) ( isset( $v['da_tieu'] ) ? $v['da_tieu'] : 0 ),
				'khoa'       => empty( $v['khoa'] ) ? 0 : 1,
			);
		}
		return $ra;
	}

	// =========================================================================================
	// GIAO DIỆN
	// =========================================================================================

	public static function ve() {
		if ( ! headers_sent() ) {
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		$api = esc_url( self::url() );
		/* Ảnh nền do người dùng khai trong Cài đặt. `esc_url_raw` rồi mới nhét vào CSS: chuỗi này
		   đi thẳng vào một thuộc tính style, nên một dấu nháy lọt qua là chèn được CSS tuỳ ý. */
		$nen = esc_url_raw( (string) get_option( 'vhg_anh_nen', '' ) );
		$lop = '';
		$bien_nen = '';
		if ( '' !== $nen && ! preg_match( '/["\\\\()]/', $nen ) ) {
			$lop       = ' class="co-anh"';
			$bien_nen  = ' style="--nen:url(&quot;' . esc_attr( $nen ) . '&quot;)"';
		}
		echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . esc_html( self::TEN_HE_THONG ) . '</title>'
			/* Người đứng quầy lưu trang này vào màn hình chính điện thoại. */
			. '<meta name="theme-color" content="#12141f">'
			. '<style>' . self::css() . VHG_Chan::css() . '</style></head><body' . $lop . $bien_nen . '>'
			. '<div id="app"></div>'
			. '<script>window.VHG_API=' . wp_json_encode( $api ) . ';'
				/* 🔴 SỐ BẢN ĐANG CHẠY, ĐƯA THẲNG RA TRANG.
				   Nửa số lần "vẫn không chạy" là máy chủ còn chạy bản cũ — cài đè xong mà trình
				   duyệt giữ bản JS trong bộ nhớ đệm, hoặc plugin chưa kích hoạt lại. Không hiện
				   số bản thì câu đó phải hỏi vòng qua ảnh chụp và phỏng đoán; hiện ra thì nhìn
				   một giây là biết. */
				. 'window.VHG_BAN=' . wp_json_encode( defined( 'VHG_VERSION' ) ? VHG_VERSION : '?' ) . ';'
				. 'window.VHG_TEN=' . wp_json_encode( self::TEN_HE_THONG ) . ';</script>'
			. '<script>' . self::js() . '</script>'
			/* Module BÁO CÁO DOANH THU (thu tiền) — tự chứa, cổng bc-PIN riêng, không cần token.
			   Tách script riêng để KHÔNG đụng SPA token; mở từ nút ở màn đăng nhập. */
			. '<div id="bc-app"></div><script>' . self::js_baocao() . '</script>'
			/* Chân trang pháp lý — DỰNG Ở MÁY CHỦ, ngoài `#app`. Nằm trong JS thì JS hỏng là
			   thông tin công ty biến mất; xem VHG_Chan::html(). */
			. VHG_Chan::html()
			. '</body></html>';
	}

	/**
	 * MODULE BÁO CÁO DOANH THU (thu tiền) — tự chứa, cổng bc-PIN riêng, gọi các việc `bc_*` bằng
	 * `pin` (KHÔNG token). Port từ app Apps Script "POSH v3 · THU TIỀN". Giữ các bất biến:
	 *   · actual = (chỉ số sau − trước) × đơn_vị ; tiền mặt = actual − QR ± tăng/giảm (server ép lại).
	 *   · Chỉ số trước KHOÁ (lấy từ lần thu trước / máy đếm chung); chỉ nhập tay lần đầu.
	 *   · Chỉ gửi ghế CÓ nhập chỉ số sau. Chống bấm gửi 2 lần.
	 *   · Nhập tới cơ sở cuối → báo "ĐỦ BÁO CÁO"; còn điểm chưa thu → xin CHỐT CA SỚM.
	 *   · Mọi chữ đặt bằng textContent (chống XSS từ ô dữ liệu).
	 */
	private static function js_baocao() {
		return <<<'JS'
(function(){
  var API = window.VHG_API || '';
  var PIN='', BC=null, NGAY='', LOC='', LAST={}, GUI_DANG=false;
  /* GỌN = màn điện thoại: chỉ nhập chỉ số + gửi. ĐẦY ĐỦ = máy tính: hiện hết. Mặc định theo bề
     ngang màn hình, nhớ lựa chọn của người dùng. Anh Thắng 27/08: điện thoại ít thông tin thôi. */
  var GON = true;
  try{ var _p=localStorage.getItem('bc_gon');
    GON = (_p==='0') ? false : (_p==='1') ? true : (!window.matchMedia || window.matchMedia('(max-width: 860px)').matches);
  }catch(e){ GON = true; }
  function datGon(v){ GON=!!v; try{ localStorage.setItem('bc_gon', GON?'1':'0'); }catch(e){} veChinh(); }

  function $(id){ return document.getElementById(id); }
  function el(t,c,tx){ var e=document.createElement(t); if(c)e.className=c; if(tx!=null)e.textContent=tx; return e; }
  function money(n){ return (Number(n)||0).toLocaleString('vi-VN'); }
  function snum(s){ s=String(s==null?'':s); var neg=/^\s*-/.test(s); var d=s.replace(/[^0-9]/g,'');
    if(!d) return 0; return (neg?-1:1)*parseInt(d,10); }
  function meterVal(s){ s=String(s==null?'':s).replace(/[^0-9]/g,''); return s===''?'':parseInt(s,10); }
  function coThu(s){ return /[0-9]/.test(String(s==null?'':s)); }

  function goi(viec,d,cb){
    d=d||{}; if(!d.pin) d.pin=PIN;
    var x=new XMLHttpRequest();
    /* 🔴 KHÔNG CÓ timeout/onerror TRƯỚC ĐÂY = TREO VĨNH VIỄN khi mạng rớt giữa chừng (site
       chạy ở cơ sở, wifi/4G yếu). readyState không bao giờ lên 4 thì cb() không bao giờ gọi,
       nút "Đang lưu…" đứng mãi và không có cách nào tự phục hồi ngoài tải lại trang. */
    var xongMotLan=false; function xong(r){ if(xongMotLan) return; xongMotLan=true; cb(r); }
    x.open('POST', API + (API.indexOf('?')<0?'?':'&') + 'api=' + viec, true);
    x.setRequestHeader('Content-Type','application/json');
    x.timeout=25000;
    x.onreadystatechange=function(){
      if(x.readyState!==4) return;
      var r=null; try{ r=JSON.parse(x.responseText); }catch(e){}
      xong(r || { ok:false, error:'Không đọc được trả lời của máy chủ (mạng hoặc tường lửa).' });
    };
    x.ontimeout=function(){ xong({ ok:false, error:'Máy chủ không trả lời — mạng yếu hoặc quá tải. Thử lại.' }); };
    x.onerror=function(){ xong({ ok:false, error:'Mất kết nối mạng khi gửi. Thử lại.' }); };
    x.send(JSON.stringify(d));
  }

  function styleOnce(){
    if($('bc-style')) return;
    var s=el('style'); s.id='bc-style';
    s.textContent = [
      '#bc-app{position:fixed;inset:0;z-index:100000;display:none;overflow:auto;background:#f5f7fb;color:#0f172a;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}',
      '#bc-app.mo{display:block}',
      '.bc-wrap{width:100%;padding:16px clamp(12px,2vw,28px) 60px}',
      '.bc-top{position:sticky;top:0;z-index:5;display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#0f172a;color:#fff;padding:12px 16px;border-radius:0 0 12px 12px}',
      '.bc-top b{color:#d4af37}',
      '.bc-sp{flex:1}',
      '.bc-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin:14px auto;max-width:1180px}',
      '.bc-h{margin:0 0 4px;font-size:15px}',
      '.bc-mut{color:#64748b;font-size:12.5px;margin:2px 0}',
      '.bc-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}',
      '.bc-f{display:flex;flex-direction:column;gap:5px;min-width:0}',
      '.bc-f>span{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#334155}',
      '.bc-app-in input,.bc-app-in select,#bc-app input,#bc-app select{font:inherit;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#0f172a}',
      '#bc-app input:focus,#bc-app select:focus{outline:none;border-color:#4f46e5}',
      '.bc-btn{font:inherit;font-weight:700;padding:10px 16px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;cursor:pointer}',
      '.bc-btn.pri{background:#4f46e5;border-color:#4f46e5;color:#fff}',
      '.bc-btn.pri:disabled{opacity:.6;cursor:not-allowed}',
      '.bc-btn.warn{background:#b45309;border-color:#b45309;color:#fff}',
      '.bc-gate{max-width:340px;margin:60px auto;text-align:center}',
      '.bc-gate input{width:100%;text-align:center;letter-spacing:10px;font-size:20px;margin:14px 0 8px}',
      '.bc-err{color:#b91c1c;font-size:12.5px;min-height:18px}',
      '.bc-ok{color:#059669}',
      '.bc-scroll{overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;margin-top:12px}',
      '.bc-t{width:100%;border-collapse:collapse;min-width:820px}',
      '.bc-t th{background:#0f172a;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.4px;padding:10px;text-align:left;white-space:nowrap}',
      '.bc-t td{padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top}',
      '.bc-t input{width:100%;min-width:78px;text-align:right;font-variant-numeric:tabular-nums}',
      '.bc-t input.note{text-align:left;min-width:120px}',
      '.bc-ro{display:block;text-align:right;font-weight:800;padding:9px 10px;border-radius:9px;background:#f1f5f9}',
      '.bc-cash{background:#ecfdf5;color:#059669}',
      '.bc-warn{font-size:11px;color:#b91c1c;font-weight:600;margin-top:3px}',
      '.bc-tot{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:12px}',
      '.bc-tt{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px}',
      '.bc-tt span{display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#64748b}',
      '.bc-tt b{font-size:16px;font-variant-numeric:tabular-nums}',
      '.bc-prog{display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:10px 12px;margin-top:6px;font-size:13px}',
      '.bc-prog.du{background:#ecfdf5;border-color:#a7f3d0}',
      '.bc-chip{display:inline-block;padding:3px 9px;border-radius:12px;font-size:12px;background:#fff;border:1px solid #cbd5e1}',
      '.bc-chip.x{background:#dcfce7;border-color:#86efac}',
      '.bc-chip.o{background:#fee2e2;border-color:#fecaca}',
      '.bc-msg{font-size:13px;font-weight:600;margin-top:10px}',
      '.bc-lech{background:#fef2f2}', '.bc-khop{color:#059669}'
    ].join('');
    document.head.appendChild(s);
  }

  // ---------------- CỔNG PIN ----------------
  function moBaoCao(){
    styleOnce();
    var app=$('bc-app'); app.className='mo'; app.textContent='';
    var g=el('div','bc-card bc-gate');
    g.appendChild(el('div',null,'📋'));
    g.appendChild(el('h2','bc-h','Báo cáo doanh thu'));
    g.appendChild(el('div','bc-mut','Nhập mã PIN nhân viên thu tiền'));
    var inp=el('input'); inp.type='tel'; inp.inputMode='numeric'; inp.maxLength=10; inp.placeholder='PIN';
    g.appendChild(inp);
    var er=el('div','bc-err');
    var b=el('button','bc-btn pri','Vào'); b.style.width='100%';
    var back=el('button','bc-btn','← Quay lại đăng nhập'); back.style.cssText='width:100%;margin-top:8px';
    back.onclick=function(){ app.className=''; };
    g.appendChild(er); g.appendChild(b); g.appendChild(back);
    app.appendChild(g);
    function thu(){
      var v=(inp.value||'').trim();
      if(!v){ er.textContent='Nhập mã PIN.'; return; }
      b.disabled=true; er.textContent='Đang kiểm tra…'; er.className='bc-err';
      goi('bc_boot',{pin:v},function(r){
        b.disabled=false;
        if(!r){ er.textContent='Không nhận được trả lời máy chủ.'; return; }
        if(!r.ok || !r.pinOk){ er.textContent=(r.error||'PIN không đúng.'); inp.value=''; inp.focus(); return; }
        PIN=v; BC=r; NGAY=r.today||''; LOC='';
        veChinh();
      });
    }
    b.onclick=thu;
    inp.addEventListener('keydown',function(e){ if(e.key==='Enter') thu(); });
    inp.focus();
  }

  // ---------------- MÀN CHÍNH ----------------
  function veChinh(){
    var app=$('bc-app'); app.textContent='';
    // thanh trên
    var top=el('div','bc-top');
    var t=el('div'); t.appendChild(el('b',null,'POSH ')); t.appendChild(document.createTextNode((BC.staff||'')));
    top.appendChild(t);
    top.appendChild(el('span','bc-sp'));
    var tg=el('button','bc-btn', GON ? '🖥 Đầy đủ' : '📱 Gọn');
    tg.title = GON ? 'Chuyển sang chế độ đầy đủ (máy tính)' : 'Chuyển sang chế độ gọn (điện thoại)';
    tg.onclick=function(){ datGon(!GON); };
    top.appendChild(tg);
    var out=el('button','bc-btn','Thoát'); out.onclick=function(){ PIN=''; BC=null; $('bc-app').className=''; };
    top.appendChild(out);
    app.appendChild(top);

    var wrap=el('div','bc-wrap bc-app-in');

    // tiến độ phiên
    var prog=el('div'); prog.id='bc-prog'; wrap.appendChild(prog);

    // chọn ngày + cơ sở
    var c1=el('div','bc-card');
    c1.appendChild(el('h3','bc-h','Báo cáo doanh thu theo cơ sở'));
    c1.appendChild(el('div','bc-mut', GON
      ? 'Nhập CHỈ SỐ SAU (và QR nếu có), rồi bấm Gửi. Xong hết cơ sở thì Xin chốt ca.'
      : 'Chỉ nhập CHỈ SỐ SAU và QR. Chỉ số trước hệ thống tự lấy; tiền mặt web tự tính.'));
    var r1=el('div','bc-row'); r1.style.marginTop='12px';
    var fN=el('label','bc-f'); fN.appendChild(el('span',null,'Ngày báo cáo'));
    var iN=el('input'); iN.type='date'; iN.value=NGAY; iN.max=BC.today||'';
    iN.onchange=function(){ NGAY=iN.value; if(LOC) selectLoc(LOC); refreshPhien(); };
    fN.appendChild(iN); r1.appendChild(fN);
    var fL=el('label','bc-f'); fL.appendChild(el('span',null,'Cơ sở'));
    var sL=el('select'); sL.appendChild(new Option('— Chọn cơ sở —',''));
    (BC.coso||[]).forEach(function(cs){ sL.appendChild(new Option(cs,cs)); });
    sL.onchange=function(){ LOC=sL.value; if(LOC) selectLoc(LOC); };
    fL.appendChild(sL); r1.appendChild(fL);
    c1.appendChild(r1);
    var lock=el('div','bc-warn'); lock.id='bc-lock'; lock.style.display='none'; c1.appendChild(lock);
    wrap.appendChild(c1);

    // bảng ghế
    var c2=el('div','bc-card');
    c2.appendChild(el('h3','bc-h','Số liệu từng ghế'));
    var sc=el('div','bc-scroll');
    var tb=el('table','bc-t');
    tb.innerHTML = GON
      ? '<thead><tr><th>Ghế</th><th>Chỉ số trước</th><th>Chỉ số sau</th><th>QR</th></tr></thead>'
      : '<thead><tr><th>Ghế</th><th>Chỉ số trước</th><th>Chỉ số sau</th><th>Actual</th><th>Tiền mặt</th><th>QR</th><th>Tăng/Giảm</th><th>Ghi chú</th></tr></thead>';
    var body=el('tbody'); body.id='bc-rows';
    body.appendChild(elEmptyRow('Chọn cơ sở để hiện ghế…'));
    tb.appendChild(body); sc.appendChild(tb); c2.appendChild(sc);
    // tổng — gọn: chỉ Tiền mặt + Thực thu; đầy đủ: cả 4 ô
    var tot=el('div','bc-tot');
    tot.innerHTML = GON
      ? '<div class="bc-tt"><span>Tiền mặt</span><b id="bc-s-cash">0</b></div>'
        +'<div class="bc-tt"><span>Thực thu</span><b id="bc-s-total">0</b></div>'
      : '<div class="bc-tt"><span>Actual</span><b id="bc-s-actual">0</b></div>'
        +'<div class="bc-tt"><span>Tiền mặt</span><b id="bc-s-cash">0</b></div>'
        +'<div class="bc-tt"><span>QR</span><b id="bc-s-qr">0</b></div>'
        +'<div class="bc-tt"><span>Thực thu</span><b id="bc-s-total">0</b></div>';
    c2.appendChild(tot);
    wrap.appendChild(c2);

    // nộp tiền — gọn: chỉ 1 ô Hình thức; đầy đủ: hình thức + số tiền + ghi chú
    var c3=el('div','bc-card');
    c3.appendChild(el('h3','bc-h','Nộp tiền'));
    var r3=el('div','bc-row'); r3.style.marginTop='10px';
    var fM=el('label','bc-f'); fM.appendChild(el('span',null,'Hình thức'));
    var sM=el('select'); sM.id='bc-method';
    sM.appendChild(new Option('Nộp tiền mặt','cash'));
    sM.appendChild(new Option('Chuyển khoản','transfer'));
    sM.appendChild(new Option('Chưa nộp','unpaid'));
    fM.appendChild(sM); r3.appendChild(fM);
    if(!GON){
      var fA=el('label','bc-f'); fA.appendChild(el('span',null,'Số tiền nộp (trống = đủ)'));
      var iA=el('input'); iA.id='bc-amt'; iA.type='text'; iA.inputMode='numeric'; iA.placeholder='Để trống = nộp đủ tiền mặt';
      fA.appendChild(iA); r3.appendChild(fA);
      var fNo=el('label','bc-f'); fNo.appendChild(el('span',null,'Ghi chú'));
      var iNo=el('input'); iNo.id='bc-paynote'; iNo.type='text';
      fNo.appendChild(iNo); r3.appendChild(fNo);
    }
    c3.appendChild(r3);
    wrap.appendChild(c3);

    // ảnh báo cáo — chỉ chế độ đầy đủ (điện thoại gọn thì bỏ cho nhẹ)
    if(!GON){
      var c4=el('div','bc-card');
      c4.appendChild(el('h3','bc-h','Ảnh báo cáo (tuỳ chọn)'));
      c4.appendChild(el('div','bc-mut','2 ảnh/ghế theo thứ tự ghế trong bảng. Ảnh được nén trước khi gửi.'));
      var iImg=el('input'); iImg.type='file'; iImg.id='bc-imgs'; iImg.accept='image/*'; iImg.multiple=true; iImg.style.marginTop='8px';
      c4.appendChild(iImg);
      var iPrf=el('input'); iPrf.type='file'; iPrf.id='bc-proofs'; iPrf.accept='image/*'; iPrf.multiple=true; iPrf.style.marginTop='8px';
      var lp=el('label','bc-f'); lp.style.marginTop='8px'; lp.appendChild(el('span',null,'Ảnh chứng từ nộp tiền (QR/bill)')); lp.appendChild(iPrf);
      c4.appendChild(lp);
      wrap.appendChild(c4);
    }

    // nút gửi + chốt sớm (+ đối chiếu ở chế độ đầy đủ)
    var bar=el('div','bc-card');
    var brow=el('div','bc-row');
    var bGui=el('button','bc-btn pri','Gửi báo cáo cơ sở này'); bGui.id='bc-gui'; bGui.onclick=guiBaoCao;
    var bChot=el('button','bc-btn warn','Xin chốt ca sớm'); bChot.onclick=chotSom;
    brow.appendChild(bGui); brow.appendChild(bChot);
    if(!GON){ var bDoi=el('button','bc-btn','Đối chiếu máy'); bDoi.onclick=doiChieu; brow.appendChild(bDoi); }
    bar.appendChild(brow);
    var msg=el('div','bc-msg'); msg.id='bc-msg'; bar.appendChild(msg);
    var doi=el('div'); doi.id='bc-doi'; doi.style.marginTop='10px'; bar.appendChild(doi);
    wrap.appendChild(bar);

    // các mục phụ — chỉ chế độ đầy đủ (điện thoại gọn thì ẩn hết cho gọn mắt)
    if(!GON){
      wrap.appendChild(boxId('bc-yc'));       // kế toán yêu cầu
      wrap.appendChild(boxId('bc-recent'));   // báo cáo 24h — sửa
      wrap.appendChild(boxId('bc-unpaid'));   // nộp bổ sung
      wrap.appendChild(boxId('bc-dn'));       // đề nghị đổi/xoá chỉ số
      wrap.appendChild(boxId('bc-hist'));     // lịch sử tháng
      wrap.appendChild(boxId('bc-hoidap'));   // hỏi đáp về web
    }

    app.appendChild(wrap);
    refreshPhien();
    if(!GON){ loadYeuCau(); loadRecent(); loadUnpaid(); veDenghi(); veHist(); veHoiDap(); }
    if(LOC && (BC.coso||[]).indexOf(LOC)>=0){ sL.value=LOC; selectLoc(LOC); }
    else if((BC.coso||[]).length===1){ sL.value=BC.coso[0]; LOC=BC.coso[0]; selectLoc(LOC); }
  }
  function boxId(id){ var d=el('div','bc-card'); d.id=id; return d; }

  function elEmptyRow(txt){ var tr=el('tr'); var td=el('td'); td.colSpan=8; td.style.cssText='text-align:center;color:#64748b;padding:22px'; td.textContent=txt; tr.appendChild(td); return tr; }

  function khoaNgay(loc,ngay){
    return (BC.khoa||[]).some(function(k){ return k.coso===loc && k.ngay===ngay; });
  }

  // ---------------- BẢNG GHẾ ----------------
  function selectLoc(loc){
    var body=$('bc-rows'); if(!body) return;
    var ghe=(BC.ghe||[]).filter(function(g){ return String(g.coso||'').trim()===String(loc).trim(); });
    var lk=$('bc-lock');
    if(lk){ if(khoaNgay(loc,NGAY)){ lk.textContent='Cơ sở '+loc+' ngày '+NGAY+' đang KHOÁ — nhờ kế toán mở.'; lk.style.display=''; } else lk.style.display='none'; }
    body.textContent='';
    if(!ghe.length){ body.appendChild(elEmptyRow('Cơ sở này chưa có ghế.')); tinhTong(); return; }
    var codes=ghe.map(function(g){ return g.ma; });
    // vẽ trước với chỉ số trước rỗng, rồi lấy chỉ số trước
    ghe.forEach(function(g){ body.appendChild(veDong(g,null)); });
    tinhTong();
    goi('bc_lastmeters',{codes:codes,ngay:NGAY},function(r){
      LAST=(r&&r.map)||{};
      body.textContent='';
      ghe.forEach(function(g){ body.appendChild(veDong(g, LAST[g.ma])); });
      tinhTong();
    });
  }

  function veDong(g,before){
    var tr=el('tr'); tr.dataset.ma=g.ma; tr.dataset.ten=g.ten||g.ma;
    var coBefore = (before!==null && before!==undefined && before!=='');
    tr.dataset.lock = coBefore ? '1':'0';
    tr.dataset.before = coBefore ? String(before) : '';
    // tên
    var tdN=el('td'); tdN.appendChild(el('b',null,g.ten||g.ma));
    var w=el('div','bc-warn'); w.style.display='none'; tdN.appendChild(w); tr.appendChild(tdN);
    // chỉ số trước
    var tdB=el('td');
    if(coBefore){ var sp=el('span','bc-ro'); sp.textContent=money(before); tdB.appendChild(sp); }
    else { var ib=inp('before','Nhập lần đầu'); tdB.appendChild(ib); }
    tr.appendChild(tdB);
    tr.appendChild(cell(inp('after','Chỉ số sau')));
    if(!GON){
      tr.appendChild(cellRo('actual'));
      tr.appendChild(cellRo('cash',true));
    }
    tr.appendChild(cell(inp('qr','QR')));
    if(!GON){
      tr.appendChild(cell(inp('adjust','VD 50000')));
      tr.appendChild(cell(inp('note','Lý do…',true)));
    }
    calc(tr);
    return tr;
  }
  function inp(cls,ph,isText){ var e=el('input',cls); e.type='text'; e.inputMode=isText?'text':'numeric'; e.placeholder=ph||''; return e; }
  function cell(c){ var td=el('td'); td.appendChild(c); return td; }
  function cellRo(cls,cash){ var td=el('td'); var s=el('span','bc-ro'+(cash?' bc-cash':'')); s.className='bc-ro'+(cash?' bc-cash':''); s.classList.add(cls); s.textContent='0'; td.appendChild(s); return td; }

  function beforeOf(tr){ if(tr.dataset.lock==='1'){ var v=tr.dataset.before; return v===''?'':Number(v); } return meterVal(tr.querySelector('.before').value); }

  function calc(tr){
    var before=beforeOf(tr);
    var after=meterVal(tr.querySelector('.after').value);
    var qEl=tr.querySelector('.qr'); var qr=qEl?snum(qEl.value):0;
    var aEl=tr.querySelector('.adjust'); var adjust=aEl?snum(aEl.value):0;   // gọn: không có cột này
    var dv=Number(BC.don_vi)||10000;
    var actual=(before===''||after==='')?0:(after-before)*dv;
    var cash=actual-qr+adjust;
    var elA=tr.querySelector('.actual'); if(elA) elA.textContent=money(actual);   // gọn: ẩn
    var elC=tr.querySelector('.cash');   if(elC) elC.textContent=money(cash);
    tr.dataset.actual=actual; tr.dataset.cash=cash;
    var w=tr.querySelector('.bc-warn');
    if(before!==''&&after!==''&&after<before){ w.textContent='Chỉ số sau nhỏ hơn trước'; w.style.display=''; }
    else w.style.display='none';
  }

  function tinhTong(){
    var a=0,c=0,q=0;
    document.querySelectorAll('#bc-rows tr[data-ma]').forEach(function(tr){
      a+=Number(tr.dataset.actual||0); c+=Number(tr.dataset.cash||0);
      q+=snum(tr.querySelector('.qr')?tr.querySelector('.qr').value:0);
    });
    if($('bc-s-actual')) $('bc-s-actual').textContent=money(a);
    if($('bc-s-cash')) $('bc-s-cash').textContent=money(c);
    if($('bc-s-qr')) $('bc-s-qr').textContent=money(q);
    if($('bc-s-total')) $('bc-s-total').textContent=money(c+q);
  }

  // gõ ô nào cũng tính lại (uỷ quyền sự kiện)
  document.addEventListener('input',function(ev){
    if(!$('bc-app')||$('bc-app').className.indexOf('mo')<0) return;
    var tr=ev.target.closest && ev.target.closest('#bc-rows tr[data-ma]');
    if(tr){ calc(tr); tinhTong(); }
  });

  // ---------------- GỬI ----------------
  function collect(){
    var out=[];
    document.querySelectorAll('#bc-rows tr[data-ma]').forEach(function(tr){
      if(!coThu(tr.querySelector('.after').value)) return;
      var qEl=tr.querySelector('.qr'), aEl=tr.querySelector('.adjust'), nEl=tr.querySelector('.note');
      out.push({ chairCode:tr.dataset.ma, chairName:tr.dataset.ten,
        meterBefore:beforeOf(tr), meterAfter:meterVal(tr.querySelector('.after').value),
        qr:qEl?snum(qEl.value):0, adjust:aEl?snum(aEl.value):0,
        note:nEl?(nEl.value||'').trim():'' });
    });
    return out;
  }

  function guiBaoCao(){
    var msg=$('bc-msg'); msg.className='bc-msg'; msg.textContent='';
    if(!NGAY){ msg.textContent='Chọn ngày.'; msg.className='bc-msg bc-err'; return; }
    if(!LOC){ msg.textContent='Chọn cơ sở.'; msg.className='bc-msg bc-err'; return; }
    var rows=collect();
    if(!rows.length){ msg.textContent='Chưa nhập chỉ số sau cho ghế nào.'; msg.className='bc-msg bc-err'; return; }
    for(var i=0;i<rows.length;i++){ var r=rows[i];
      if(r.meterBefore!==''&&r.meterAfter!==''&&Number(r.meterAfter)<Number(r.meterBefore)){
        msg.textContent='Ghế '+(r.chairName||r.chairCode)+': chỉ số sau nhỏ hơn trước. Máy vừa thay/đổi điểm thì gửi đề nghị đổi chỉ số.';
        msg.className='bc-msg bc-err'; return; } }
    var mEl=$('bc-method'); var method=mEl?mEl.value:'cash';
    var aEl=$('bc-amt'); var amtRaw=aEl?(aEl.value||'').trim():'';   // gọn: không có ô số tiền → nộp đủ
    var nEl=$('bc-paynote');
    var payment={ method:method, amount: amtRaw===''?'':snum(amtRaw), note:nEl?(nEl.value||'').trim():'' };
    if(GUI_DANG) return; GUI_DANG=true; $('bc-gui').disabled=true;
    msg.textContent='Đang đọc ảnh…';
    docAnh_('bc-imgs',function(images){
      docAnh_('bc-proofs',function(proofs){
        msg.textContent='Đang gửi…';
        goi('bc_submit',{ date:NGAY, loc:LOC, rows:rows, payment:payment, images:images, proofs:{qr:proofs} },function(r){
          GUI_DANG=false; $('bc-gui').disabled=false;
          if(!r||!r.ok){ msg.textContent=(r&&r.message)||(r&&r.error)||'Gửi không thành công.'; msg.className='bc-msg bc-err'; return; }
          msg.textContent=r.message||('Đã gửi báo cáo '+LOC+'.'); msg.className='bc-msg bc-ok';
          var iI=$('bc-imgs'), iP=$('bc-proofs'); if(iI) iI.value=''; if(iP) iP.value='';
          if(r.phien) veProg(r.phien);
          else refreshPhien();
        });
      });
    });
  }
  // nén ảnh trên máy (cạnh dài 1280, JPEG 0.6) rồi đọc base64 — nhẹ ~10 lần, tránh HTTP 400.
  function nenAnh_(file,cb){
    try{
      if(!file||!/^image\//.test(file.type)){ var f0=new FileReader(); f0.onload=function(){cb(String(f0.result));}; f0.onerror=function(){cb('');}; f0.readAsDataURL(file); return; }
      var url=URL.createObjectURL(file), img=new Image();
      img.onload=function(){ try{ var mx=1280,s=Math.min(1,mx/Math.max(img.width,img.height));
        var cv=document.createElement('canvas'); cv.width=Math.round(img.width*s); cv.height=Math.round(img.height*s);
        cv.getContext('2d').drawImage(img,0,0,cv.width,cv.height); URL.revokeObjectURL(url); cb(cv.toDataURL('image/jpeg',0.6)); }
        catch(e){ URL.revokeObjectURL(url); var fr=new FileReader(); fr.onload=function(){cb(String(fr.result));}; fr.onerror=function(){cb('');}; fr.readAsDataURL(file); } };
      img.onerror=function(){ URL.revokeObjectURL(url); var fr=new FileReader(); fr.onload=function(){cb(String(fr.result));}; fr.onerror=function(){cb('');}; fr.readAsDataURL(file); };
      img.src=url;
    }catch(e){ cb(''); }
  }
  function docAnh_(id,cb){
    var f=$(id); var files=(f&&f.files)?[].slice.call(f.files).slice(0,40):[];
    if(!files.length) return cb([]);
    var out=[],done=0;
    files.forEach(function(file,i){ nenAnh_(file,function(du){ out[i]=du?{name:file.name,dataUrl:du}:null; if(++done===files.length) cb(out.filter(Boolean)); }); });
  }

  // ---------------- PHIÊN / TIẾN ĐỘ ----------------
  function refreshPhien(){ goi('bc_phien',{ngay:NGAY},function(r){ if(r&&r.ok) veProg(r); }); }
  function veProg(p){
    var box=$('bc-prog'); if(!box) return; box.textContent=''; box.className='bc-prog'+(p.du?' du':'');
    var head=el('b',null, p.du ? ('✓ ĐỦ BÁO CÁO '+p.so_coso+'/'+p.so_coso+' cơ sở') : ('Tiến độ: '+p.so_coso_xong+'/'+p.so_coso+' cơ sở'));
    box.appendChild(head);
    (p.coso_xong||[]).forEach(function(c){ box.appendChild(el('span','bc-chip x',c)); });
    (p.coso_conlai||[]).forEach(function(c){ box.appendChild(el('span','bc-chip o',c)); });
    if(p.tong) box.appendChild(el('span',null,' · Tổng '+money(p.tong)+'đ'));
    if(p.trang_thai==='chot_som') box.appendChild(el('span',null,' · ĐÃ CHỐT SỚM'+(p.bo_qua&&p.bo_qua.length?(' (bỏ: '+p.bo_qua.join(', ')+')'):'')));
  }

  function chotSom(){
    var msg=$('bc-msg'); msg.className='bc-msg';
    var ly=window.prompt('Xin chốt ca sớm ngày '+NGAY+'.\nGhi rõ điểm nào chưa thu được và vì sao:');
    if(ly===null) return;
    ly=(ly||'').trim();
    if(!ly){ msg.textContent='Phải ghi lý do xin chốt sớm.'; msg.className='bc-msg bc-err'; return; }
    goi('bc_chot_som',{ngay:NGAY,ly_do:ly},function(r){
      if(!r||!r.ok){ msg.textContent=(r&&r.message)||'Không chốt được.'; msg.className='bc-msg bc-err'; return; }
      msg.textContent=r.message||'Đã chốt ca sớm.'; msg.className='bc-msg bc-ok';
      if(r.phien) veProg(r.phien);
    });
  }

  // ---------------- ĐỐI CHIẾU ----------------
  function doiChieu(){
    var box=$('bc-doi'); box.textContent='Đang đối chiếu…';
    goi('bc_doichieu',{ngay:NGAY},function(r){
      box.textContent='';
      if(!r||!r.ok){ box.textContent=(r&&r.message)||'Không đối chiếu được.'; return; }
      if(!r.so_ghe){ box.textContent='Ngày '+r.ngay+' chưa có ghế nào để đối chiếu.'; return; }
      var h=el('div','bc-mut', 'Đối chiếu ngày '+r.ngay+' — '+r.so_ghe+' ghế, '+r.so_lech+' ghế LỆCH so với máy online.');
      box.appendChild(h);
      var sc=el('div','bc-scroll'); var tb=el('table','bc-t');
      tb.innerHTML='<thead><tr><th>Ghế</th><th style="text-align:right">QR báo cáo</th><th style="text-align:right">QR máy</th><th style="text-align:right">Lệch QR</th><th style="text-align:right">Tiền mặt (máy đếm)</th><th style="text-align:right">Máy báo</th><th style="text-align:right">Lệch</th></tr></thead>';
      var bo=el('tbody');
      (r.ghe||[]).forEach(function(g){
        var tr=el('tr'); if(!g.khop) tr.className='bc-lech';
        tr.appendChild(tdT(g.ten||g.ma_may));
        tr.appendChild(tdN(g.bc_qr)); tr.appendChild(tdN(g.may_qr)); tr.appendChild(tdN(g.lech_qr,true));
        tr.appendChild(tdN(g.bc_actual)); tr.appendChild(tdN(g.may_cash)); tr.appendChild(tdN(g.lech_cash,true));
        bo.appendChild(tr);
      });
      tb.appendChild(bo); sc.appendChild(tb); box.appendChild(sc);
    });
  }
  function tdT(x){ var td=el('td'); td.appendChild(el('b',null,x)); return td; }
  function tdN(n,lech){ var td=el('td'); var s=el('span'); s.style.cssText='display:block;text-align:right;font-variant-numeric:tabular-nums'; if(lech&&Number(n)!==0){ s.style.color='#b91c1c'; s.style.fontWeight='700'; } else if(lech){ s.className='bc-khop'; } s.textContent=money(n); td.appendChild(s); return td; }

  // ---------------- KẾ TOÁN YÊU CẦU ----------------
  function loadYeuCau(){
    var box=$('bc-yc'); if(!box) return;
    goi('bc_yeucau',{},function(r){
      var rows=(r&&r.ds)||[]; box.textContent='';
      if(!rows.length){ box.style.display='none'; return; }
      box.style.display='';
      box.appendChild(el('h3','bc-h','Kế toán yêu cầu làm bổ sung / sửa'));
      rows.forEach(function(y){
        var it=el('div'); it.style.cssText='border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-top:8px';
        it.appendChild(el('b',null,(y.loaiChu||y.loai)+' · '+y.coSo+' · ngày '+y.ngay));
        it.appendChild(el('div','bc-mut',(y.noiDung||'')+(y.taoLuc?('  ('+y.taoLuc+')'):'')));
        var b=el('button','bc-btn','Làm ngay'); b.style.marginTop='6px';
        b.onclick=function(){
          NGAY=y.ngay;
          var iN=document.querySelector('#bc-app input[type=date]'); if(iN) iN.value=NGAY;
          var sL=document.querySelector('#bc-app select'); // ô cơ sở là select đầu tiên? an toàn: tìm theo option
          // đặt cơ sở: tìm select có option = y.coSo
          document.querySelectorAll('#bc-app select').forEach(function(s){
            for(var i=0;i<s.options.length;i++){ if(s.options[i].value===y.coSo){ s.value=y.coSo; } }
          });
          if((BC.coso||[]).indexOf(y.coSo)<0){ alert('Cơ sở "'+y.coSo+'" không thuộc phạm vi PIN của bạn.'); return; }
          LOC=y.coSo; selectLoc(LOC);
          window.scrollTo({top:0,behavior:'smooth'});
        };
        it.appendChild(b); box.appendChild(it);
      });
    });
  }

  // ---------------- BÁO CÁO 24H — SỬA ----------------
  function loadRecent(){
    var box=$('bc-recent'); if(!box) return;
    box.textContent=''; box.appendChild(el('h3','bc-h','Báo cáo trong 24h — sửa được'));
    var wrapl=el('div'); box.appendChild(wrapl);
    goi('bc_recent',{},function(r){
      var ds=(r&&r.ds)||[]; wrapl.textContent='';
      if(!ds.length){ wrapl.appendChild(el('div','bc-mut','Chưa có báo cáo nào trong 24 giờ qua.')); return; }
      ds.forEach(function(rp){ wrapl.appendChild(recentItem(rp)); });
    });
  }
  function recentItem(rp){
    var d=el('div'); d.style.cssText='border:1px solid #e2e8f0;border-radius:10px;padding:10px;margin-top:8px';
    var head=el('div'); head.style.cssText='display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center';
    head.appendChild(el('b',null,rp.date+' · '+rp.locName+' · '+rp.rows+' ghế · '+money(rp.total)+'đ'));
    var b=el('button','bc-btn','Sửa'); head.appendChild(b); d.appendChild(head);
    var body=el('div'); body.style.display='none'; body.style.marginTop='8px'; d.appendChild(body);
    b.onclick=function(){
      if(body.style.display===''){ body.style.display='none'; b.textContent='Sửa'; return; }
      body.style.display=''; b.textContent='Đóng';
      if(body.dataset.built==='1') return; body.dataset.built='1';
      (rp.chairs||[]).forEach(function(c){ body.appendChild(theGheSua(rp,c)); });
    };
    return d;
  }
  function theGheSua(rp,c){
    var card=el('div'); card.style.cssText='border:1px solid #e2e8f0;border-radius:9px;padding:9px;margin-top:6px';
    card.appendChild(el('b',null,c.chairName||c.chairCode));
    card.appendChild(el('div','bc-mut','Chỉ số trước: '+((c.meterBefore==null||c.meterBefore==='')?'—':money(c.meterBefore))+' (khoá)'));
    var g=el('div'); g.style.cssText='display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-top:6px';
    function f(lbl,cls,val){ var w=el('label','bc-f'); w.appendChild(el('span',null,lbl)); var i=inp(cls,''); i.value=(val==null?'':val); w.appendChild(i); return w; }
    g.appendChild(f('Chỉ số sau','e-after',c.meterAfter));
    g.appendChild(f('QR','e-qr',c.qr));
    g.appendChild(f('Tăng/Giảm','e-adjust',c.adjust));
    g.appendChild(f('Ghi chú','e-note',c.note));
    card.appendChild(g);
    var bar=el('div'); bar.style.cssText='display:flex;gap:8px;align-items:center;margin-top:6px';
    var s=el('button','bc-btn pri','Lưu ghế này'); var m=el('span','bc-mut');
    bar.appendChild(s); bar.appendChild(m); card.appendChild(bar);
    s.onclick=function(){
      var patch={};
      var af=meterVal(card.querySelector('.e-after').value);
      var qr=snum(card.querySelector('.e-qr').value);
      var ad=snum(card.querySelector('.e-adjust').value);
      var nt=(card.querySelector('.e-note').value||'').trim();
      if(String(c.meterAfter)!==String(af)) patch.meterAfter = af===''?'':af;
      if(Number(c.qr||0)!==qr) patch.qr=qr;
      if(Number(c.adjust||0)!==ad) patch.adjust=ad;
      if(String(c.note||'')!==nt) patch.note=nt;
      if(!Object.keys(patch).length){ m.textContent='Không có gì đổi.'; return; }
      s.disabled=true; m.textContent='Đang lưu…';
      goi('bc_edit',{report_id:rp.reportId,ma_may:c.chairCode,patch:patch},function(r){
        s.disabled=false;
        if(!r||!r.ok){ m.textContent=(r&&r.message)||'Lỗi.'; m.className='bc-mut bc-err'; return; }
        m.textContent='Đã lưu.'; m.className='bc-mut bc-ok';
        c.meterAfter=patch.meterAfter!==undefined?patch.meterAfter:c.meterAfter;
        c.qr=patch.qr!==undefined?patch.qr:c.qr; c.adjust=patch.adjust!==undefined?patch.adjust:c.adjust;
        c.note=patch.note!==undefined?patch.note:c.note;
        loadUnpaid();
      });
    };
    return card;
  }

  // ---------------- NỘP BỔ SUNG ----------------
  var UNPAID=[];
  function loadUnpaid(){
    var box=$('bc-unpaid'); if(!box) return;
    goi('bc_unpaid',{},function(r){
      UNPAID=(r&&r.ds)||[]; box.textContent='';
      box.appendChild(el('h3','bc-h','Nộp bổ sung (báo cáo hôm trước chưa nộp đủ)'));
      if(!UNPAID.length){ box.appendChild(el('div','bc-mut','Không còn báo cáo chưa nộp đủ.')); return; }
      var row=el('div','bc-row'); row.style.marginTop='8px';
      var fS=el('label','bc-f'); fS.appendChild(el('span',null,'Báo cáo còn thiếu'));
      var sel=el('select'); sel.id='bc-sup-sel';
      UNPAID.forEach(function(u){ sel.appendChild(new Option(u.date+' · '+u.locName+' · thiếu '+money(u.need-u.paid)+'đ', u.reportId)); });
      fS.appendChild(sel); row.appendChild(fS);
      var fA=el('label','bc-f'); fA.appendChild(el('span',null,'Số tiền (trống = đủ)'));
      var iA=el('input'); iA.id='bc-sup-amt'; iA.type='text'; iA.inputMode='numeric'; iA.placeholder='Để trống = đủ số còn thiếu';
      fA.appendChild(iA); row.appendChild(fA);
      var fM=el('label','bc-f'); fM.appendChild(el('span',null,'Hình thức'));
      var sM=el('select'); sM.id='bc-sup-ht'; sM.appendChild(new Option('Tiền mặt','cash')); sM.appendChild(new Option('Chuyển khoản','transfer'));
      fM.appendChild(sM); row.appendChild(fM);
      var b=el('button','bc-btn pri','Lưu nộp bổ sung'); b.style.alignSelf='flex-end';
      row.appendChild(b); box.appendChild(row);
      var m=el('div','bc-msg'); box.appendChild(m);
      b.onclick=function(){
        var rid=sel.value; if(!rid){ m.textContent='Chưa chọn báo cáo.'; m.className='bc-msg bc-err'; return; }
        var raw=(iA.value||'').trim();
        b.disabled=true; m.textContent='Đang lưu…'; m.className='bc-msg';
        goi('bc_supplement',{report_id:rid,ngay:NGAY,so_tien: raw===''?'':snum(raw),hinhthuc:sM.value},function(r){
          b.disabled=false;
          if(!r||!r.ok){ m.textContent=(r&&r.message)||'Lỗi.'; m.className='bc-msg bc-err'; return; }
          m.textContent='Đã ghi nộp bổ sung '+money(r.add)+'đ.'; m.className='bc-msg bc-ok';
          loadUnpaid();
        });
      };
    });
  }

  // ---------------- ĐỀ NGHỊ ĐỔI / XOÁ CHỈ SỐ ----------------
  function veDenghi(){
    var box=$('bc-dn'); if(!box) return; box.textContent='';
    box.appendChild(el('h3','bc-h','Đề nghị đổi / xoá chỉ số — kế toán duyệt'));
    box.appendChild(el('div','bc-mut','Dùng khi thay máy / đổi điểm / chỉ số sai. Bạn không tự đổi được — gửi đề nghị, kế toán duyệt mới có hiệu lực từ ngày áp dụng.'));
    var row=el('div','bc-row'); row.style.marginTop='8px';
    var fG=el('label','bc-f'); fG.appendChild(el('span',null,'Ghế'));
    var sG=el('select'); sG.id='bc-dn-ghe';
    (BC.ghe||[]).filter(function(g){ return !LOC || String(g.coso||'').trim()===LOC; })
      .forEach(function(g){ sG.appendChild(new Option((g.ten||g.ma)+' ('+g.ma+')', g.ma)); });
    if(!sG.options.length) (BC.ghe||[]).forEach(function(g){ sG.appendChild(new Option((g.ten||g.ma)+' ('+g.ma+')', g.ma)); });
    fG.appendChild(sG); row.appendChild(fG);
    var fL=el('label','bc-f'); fL.appendChild(el('span',null,'Loại'));
    var sL2=el('select'); sL2.id='bc-dn-loai';
    sL2.appendChild(new Option('Xoá chỉ số — về 0 (thay máy)','xoa'));
    sL2.appendChild(new Option('Đặt lại chỉ số cụ thể','dat_lai'));
    fL.appendChild(sL2); row.appendChild(fL);
    var fF=el('label','bc-f'); fF.appendChild(el('span',null,'Áp dụng từ'));
    var iF=el('input'); iF.id='bc-dn-from'; iF.type='date'; iF.value=BC.today||''; fF.appendChild(iF); row.appendChild(fF);
    var fS=el('label','bc-f'); fS.id='bc-dn-so-wrap'; fS.style.display='none'; fS.appendChild(el('span',null,'Chỉ số đề nghị'));
    var iS=el('input'); iS.id='bc-dn-so'; iS.type='text'; iS.inputMode='numeric'; fS.appendChild(iS); row.appendChild(fS);
    box.appendChild(row);
    var fLy=el('label','bc-f'); fLy.style.marginTop='8px'; fLy.appendChild(el('span',null,'Lý do (bắt buộc)'));
    var iLy=el('input'); iLy.id='bc-dn-ly'; iLy.type='text'; iLy.placeholder='VD: thay máy mới 03/08, chỉ số cũ 1240'; fLy.appendChild(iLy);
    box.appendChild(fLy);
    sL2.onchange=function(){ fS.style.display = sL2.value==='dat_lai' ? '' : 'none'; };
    var b=el('button','bc-btn pri','Gửi đề nghị'); b.style.marginTop='8px'; box.appendChild(b);
    var m=el('div','bc-msg'); box.appendChild(m);
    var list=el('div'); list.id='bc-dn-list'; list.style.marginTop='8px'; box.appendChild(list);
    b.onclick=function(){
      var code=sG.value, from=iF.value, ly=(iLy.value||'').trim(), loai=sL2.value;
      if(!code){ m.textContent='Chưa chọn ghế.'; m.className='bc-msg bc-err'; return; }
      if(!from){ m.textContent='Chưa chọn ngày.'; m.className='bc-msg bc-err'; return; }
      if(!ly){ m.textContent='Phải ghi lý do.'; m.className='bc-msg bc-err'; return; }
      var so=''; if(loai==='dat_lai'){ so=(iS.value||'').trim(); if(so===''){ m.textContent='Nhập chỉ số đề nghị.'; m.className='bc-msg bc-err'; return; } }
      b.disabled=true; m.textContent='Đang gửi…'; m.className='bc-msg';
      goi('bc_denghi_gui',{chairCode:code,fromDate:from,loai:loai,meterOpening:so,lyDo:ly},function(r){
        b.disabled=false;
        m.textContent=(r&&r.message)||((r&&r.ok)?'Đã gửi.':'Không gửi được.'); m.className='bc-msg '+((r&&r.ok)?'bc-ok':'bc-err');
        if(r&&r.ok){ iLy.value=''; iS.value=''; loadDenghiList(); }
      });
    };
    loadDenghiList();
  }
  function loadDenghiList(){
    var list=$('bc-dn-list'); if(!list) return; list.textContent='Đang tải…';
    goi('bc_denghi_ds',{coso:LOC||''},function(r){
      var ds=(r&&r.ds)||[]; list.textContent='';
      if(!ds.length){ list.appendChild(el('div','bc-mut','Chưa có đề nghị nào.')); return; }
      ds.forEach(function(d){
        var it=el('div'); it.style.cssText='border:1px solid #e2e8f0;border-radius:9px;padding:8px;margin-top:6px';
        var tt=d.trangThai==='da_duyet'?'Đã duyệt':(d.trangThai==='tu_choi'?'Từ chối':'Chờ duyệt');
        it.appendChild(el('b',null,(d.chairName||d.chairCode)+' · '+(d.loai==='xoa'?'Xoá về 0':'Đặt '+money(d.meterOpening))+' · từ '+d.fromDate+' · '+tt));
        it.appendChild(el('div','bc-mut','Lý do: '+(d.lyDo||'—')+(d.ghiChuKeToan?(' · KT: '+d.ghiChuKeToan):'')));
        list.appendChild(it);
      });
    });
  }

  // ---------------- LỊCH SỬ THÁNG ----------------
  function veHist(){
    var box=$('bc-hist'); if(!box) return; box.textContent='';
    box.appendChild(el('h3','bc-h','Lịch sử báo cáo trong tháng'));
    var row=el('div','bc-row'); row.style.marginTop='8px';
    var iM=el('input'); iM.type='month'; iM.id='bc-hist-m'; iM.value=(BC.today||'').slice(0,7);
    row.appendChild(iM);
    var b=el('button','bc-btn','Xem'); row.appendChild(b); box.appendChild(row);
    var out=el('div'); out.id='bc-hist-out'; out.style.marginTop='8px'; out.className='bc-mut'; box.appendChild(out);
    b.onclick=function(){
      out.textContent='Đang tải…';
      goi('bc_history',{thang:iM.value},function(r){
        var ds=(r&&r.ds)||[]; out.textContent=''; out.className='';
        if(!ds.length){ out.className='bc-mut'; out.textContent='Tháng này chưa có báo cáo.'; return; }
        var g={}, order=[], tot=0;
        ds.forEach(function(x){ var k=x.date+'|'+x.locName; if(!g[k]){ g[k]={date:x.date,loc:x.locName,cash:0,qr:0,total:0}; order.push(k); }
          g[k].cash+=Number(x.cash||0); g[k].qr+=Number(x.qr||0); g[k].total+=Number(x.total||0); });
        var sc=el('div','bc-scroll'); var tb=el('table','bc-t');
        tb.innerHTML='<thead><tr><th>Ngày</th><th>Cơ sở</th><th style="text-align:right">Tiền mặt</th><th style="text-align:right">QR</th><th style="text-align:right">Tổng</th></tr></thead>';
        var bo=el('tbody');
        order.forEach(function(k){ var o=g[k]; tot+=o.total; var tr=el('tr');
          tr.appendChild(tdCell(o.date)); tr.appendChild(tdCell(o.loc));
          tr.appendChild(tdCell(money(o.cash),1)); tr.appendChild(tdCell(money(o.qr),1)); tr.appendChild(tdCell(money(o.total),1));
          bo.appendChild(tr); });
        tb.appendChild(bo); sc.appendChild(tb); out.appendChild(sc);
        out.appendChild(el('div',null,'Tổng tháng: '+money(tot)+'đ · '+order.length+' lượt'));
      });
    };
  }
  function tdCell(x,r){ var td=el('td'); if(r){ td.style.textAlign='right'; td.style.fontVariantNumeric='tabular-nums'; } td.textContent=x; return td; }

  // ---------------- HỎI ĐÁP ----------------
  function veHoiDap(){
    var box=$('bc-hoidap'); if(!box) return; box.textContent='';
    box.appendChild(el('h3','bc-h','Hỏi đáp về web'));
    var thread=el('div'); thread.style.cssText='max-height:220px;overflow:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:8px;margin-top:6px'; box.appendChild(thread);
    var chips=el('div'); chips.style.marginTop='6px'; box.appendChild(chips);
    var row=el('div','bc-row'); row.style.marginTop='6px';
    var i=el('input'); i.type='text'; i.placeholder='Hỏi gì về web…'; i.style.flex='1';
    var b=el('button','bc-btn','Gửi'); row.appendChild(i); row.appendChild(b); box.appendChild(row);
    function them(ai,chu,tieu){ var d=el('div'); d.style.cssText='margin-bottom:6px;padding:7px 9px;border-radius:8px;font-size:13px;white-space:pre-wrap;'+(ai==='toi'?'background:#4f46e5;color:#fff;margin-left:auto;max-width:85%':'background:#fff;border:1px solid #e4e4e7;max-width:90%');
      if(tieu) d.appendChild(el('b',null,tieu)); d.appendChild(el('span',null,chu)); thread.appendChild(d); thread.scrollTop=thread.scrollHeight; return d; }
    function chipsVe(ds){ chips.textContent=''; (ds||[]).forEach(function(c){ var s=el('span'); s.textContent=c; s.style.cssText='display:inline-block;margin:3px 4px 0 0;padding:5px 9px;border-radius:14px;border:1px solid #cbd5e1;background:#fff;font-size:12px;cursor:pointer'; s.onclick=function(){ hoi(c); }; chips.appendChild(s); }); }
    function hoi(cau){ if(cau) them('toi',cau); var cho=them('web','Đang tìm…');
      goi('bc_hoidap',{cau:cau||''},function(r){ if(cho.parentNode) cho.parentNode.removeChild(cho);
        if(!r||!r.ok){ them('web','Không đọc được hỏi đáp.'); return; }
        if(cau){ if(r.truot) them('web','Câu này chưa có sẵn câu trả lời. Thử chọn một mục bên dưới, hoặc hỏi cách khác.'); else them('web',r.traLoi,r.cauHoi); }
        else them('web','Chọn một câu bên dưới, hoặc gõ câu hỏi.');
        chipsVe(r.goiY); }); }
    b.onclick=function(){ var v=(i.value||'').trim(); if(!v) return; i.value=''; hoi(v); };
    i.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); b.onclick(); } });
    hoi('');
  }

  window.VHG_BaoCao = { mo: moBaoCao };
})();
JS;
	}

	private static function css() {
		return <<<'CSS'
*{box-sizing:border-box}
/* ============================================================================================
 * NỀN ẢNH PHÒNG GHẾ.
 *
 * Lớp ảnh để `position:fixed` ở `body::before` chứ KHÔNG phải `background-attachment:fixed`
 * trên body: Safari trên iOS lờ hẳn thuộc tính đó, mà điện thoại mới là nơi trang này sống.
 * Ảnh còn được phủ một lớp tối; không phủ thì chữ trắng nằm trên vùng sáng của ảnh là không
 * đọc nổi, và ảnh nào cũng có một vùng sáng ở đâu đó.
 *
 * Chưa khai ảnh thì rơi về dải màu tự dựng — KHÔNG để trắng trơn và cũng không tải ảnh từ đâu
 * khác về: trang này mở trên 4G ở trung tâm thương mại, và một ảnh nền không tải được sẽ để lại
 * đúng cái nền trắng chữ trắng đó.
 * ============================================================================================ */
/* ============================================================================================
 * GIAO DIỆN THEO "QUẢN LÝ NHÂN SỰ V5.2" (HR): nền sáng, thẻ trắng, tiêu đề navy + vạch xanh,
 * nút phẳng (xanh dương chính, cam nhấn, xanh lá/đỏ/xám), bảng header xám nhạt. Anh Thắng 27/08.
 * Giữ NGUYÊN tên lớp để không phải sửa JS — chỉ đổi màu/kiểu.
 * ============================================================================================ */
:root{--bg:#eef1f5;--card:#ffffff;--line:#e3e8ef;--ink:#243a5e;--ink2:#4a5b78;--mut:#7a879e;
  --blue:#2f6fb0;--blue-d:#255c95;--blue-bg:#eef4fb;--navy:#213a5e;--amber:#e8912a;--amber-d:#cf7c19;
  --green:#2e9b57;--red:#d64545}
body::before{content:"";position:fixed;inset:0;z-index:-2;
  background:linear-gradient(180deg,#f3f5f9 0%,#eaeef4 100%);background-size:cover;background-position:center}
body.co-anh::before{background-image:var(--nen);background-size:cover;background-position:center}
/* Ảnh nền (nếu có) phủ MÀN SÁNG để chữ tối vẫn đọc được — HR vốn nền phẳng nên veil khá đặc. */
body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:linear-gradient(180deg,rgba(238,241,245,.90) 0%,rgba(238,241,245,.82) 40%,rgba(238,241,245,.92) 100%)}
body{margin:0;background:var(--bg);color:var(--ink2);min-height:100vh;
  font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1180px;margin:0 auto;padding:14px}
h1{font-size:19px;margin:0;color:var(--ink)}
h1 small{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);font-weight:400;margin-top:3px}

/* --- Dải đầu trang: khối kính, dính trên cùng ---
   Dính vì đây là chỗ có nút Thoát và đồng hồ; cuộn xuống bảng giao dịch dài rồi phải cuộn
   ngược lên mới thoát được là một cái phiền không đáng có. */
.top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 14px;
  position:sticky;top:0;z-index:20;padding:11px 14px;
  background:#fff;border:1px solid var(--line);border-radius:12px;
  box-shadow:0 2px 8px rgba(31,45,74,.06)}
.top .sp{flex:1}
.hieu{display:flex;align-items:center;gap:11px;min-width:0}
/* Ô biểu tượng: nền xanh nhạt, chữ xanh — đồng bộ với nhấn xanh dương của HR. */
.hieu-o{width:38px;height:38px;flex:none;display:flex;align-items:center;justify-content:center;
  border-radius:10px;font-size:19px;background:var(--blue-bg);
  border:1px solid #cfe0f2;color:var(--blue)}
.dh-top{font-variant-numeric:tabular-nums;font-weight:700;color:var(--blue);letter-spacing:.04em}
/* Nút đổi ngôn ngữ: hai ô dính nhau, ô đang chọn tô vàng. Để cạnh đồng hồ vì cả hai là thứ
   người ta liếc chứ không bấm thường xuyên. */
.nn{display:inline-flex;border:1px solid var(--line);border-radius:8px;overflow:hidden}
.nn button{border:0;border-radius:0;padding:6px 10px;font-size:12px;font-weight:600;letter-spacing:.06em;background:#fff;color:var(--ink2)}
.nn button+button{border-left:1px solid var(--line)}
.nn button.on{background:var(--navy);color:#fff}
.nn-doi{margin-top:14px;display:flex;justify-content:center}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
button{font:inherit;cursor:pointer;border-radius:8px;border:1px solid var(--line);
  background:#fff;color:var(--ink);padding:7px 13px;transition:background .15s,border-color .15s,box-shadow .15s}
button:hover{background:#f4f7fb;border-color:#c4d3e6}
button.on{background:var(--blue);border-color:var(--blue);color:#fff;font-weight:600}
button.on:hover{background:var(--blue-d)}
button.ghost{background:#fff}
input,select{font:inherit;border-radius:8px;border:1px solid #c9d3e0;
  background:#fff;color:var(--ink);padding:8px 11px;width:100%}
input:focus,select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(47,111,176,.12)}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:10px;margin-bottom:14px}
.kpi{background:#fff;border:1px solid var(--line);border-radius:10px;padding:12px 14px;
  box-shadow:0 1px 4px rgba(31,45,74,.05)}
.kpi .lb{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--mut)}
.kpi .vl{font-size:21px;font-weight:700;margin-top:3px;word-break:break-all;color:var(--ink)}
.kpi .sb{font-size:12px;color:var(--mut)}
.vl.a{color:var(--amber)}.vl.b{color:var(--blue)}.vl.c{color:var(--green)}.vl.d{color:var(--ink)}
.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:14px;
  box-shadow:0 2px 8px rgba(31,45,74,.06)}
/* Tiêu đề khối: chữ hoa navy, một vạch xanh bên trái — đúng phong cách HR. */
.card h2{font-size:12.5px;margin:0 0 12px;letter-spacing:.1em;text-transform:uppercase;
  color:var(--navy);font-weight:700;padding-left:10px;border-left:3px solid var(--blue);line-height:1.25}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--ink);font-weight:700;
  background:#eef2f7;padding:9px 10px 9px 8px;border-bottom:1px solid #dbe2ec}
td{padding:9px 8px;border-bottom:1px solid #eef1f5;vertical-align:middle;color:var(--ink2)}
tr:last-child td{border-bottom:0}
.r{text-align:right}
.pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600}
.p-ok{background:#e6f6ec;color:#1f8f4d}.p-run{background:#fdf0dc;color:#c07a12}
.p-wait{background:#e9f1fb;color:#2f6fb0}.p-off{background:#fbe6e6;color:#c23b3b}
.warn{background:#fdeaea;border:1px solid #f3c7c7;border-radius:12px;padding:12px 14px;margin-bottom:14px;color:#7a2e2e}
.warn b{color:#c23b3b}
.note{background:#fdf4e3;border:1px solid #f0d9ac;border-radius:12px;padding:12px 14px;margin-bottom:14px;color:#7a5a1e}
.note b{color:var(--amber-d)}
.mut{color:var(--mut);font-size:12px}
/* --- Biểu đồ dashboard (SVG donut + thanh ngang thuần CSS, không thư viện ngoài) --- */
.bd-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;margin-bottom:14px}
.bd-grid .card{margin-bottom:0}
.bd-donut-wrap{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.bd-donut{flex:none}
.bd-c1{font-size:15px;font-weight:700;fill:var(--ink)}
.bd-c2{font-size:9px;fill:var(--mut);letter-spacing:.08em;text-transform:uppercase}
.bd-legend{flex:1;min-width:140px;display:flex;flex-direction:column;gap:8px}
.bd-lg{display:flex;align-items:center;gap:8px;font-size:12.5px}
.bd-dot{width:11px;height:11px;border-radius:3px;flex:none}
.bd-lg-t{color:var(--ink2)}
.bd-lg-v{margin-left:auto;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums;white-space:nowrap}
.bd-cot{display:flex;flex-direction:column;gap:9px}
.bd-row{display:grid;grid-template-columns:minmax(72px,32%) 1fr auto;align-items:center;gap:9px}
.bd-lb{font-size:12.5px;color:var(--ink2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bd-track{height:15px;background:var(--blue-bg);border-radius:5px;overflow:hidden}
.bd-bar{height:100%;border-radius:5px;min-width:3px;transition:width .3s ease;overflow:hidden}
.bd-seg{height:100%}
.bd-val{font-size:12px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums;white-space:nowrap}
.bd-chu{display:flex;gap:16px;margin-bottom:9px;font-size:12px;color:var(--ink2)}
.bd-chu .bd-lg2{display:inline-flex;align-items:center;gap:6px}
.login{max-width:360px;margin:12vh auto;padding:28px 24px;background:#fff;
  border:1px solid var(--line);border-radius:16px;text-align:center;
  box-shadow:0 12px 40px rgba(31,45,74,.14)}
.login .hieu-o{margin:0 auto 12px;width:46px;height:46px;font-size:23px}
.login h1{margin-bottom:6px}
.login input{text-align:center;letter-spacing:.5em;font-size:21px;margin:16px 0 10px}
.err{color:var(--red);font-size:13px;min-height:19px;margin-top:8px}
.act{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
/* --- Menu chính: mobile = hàng ngang cuộn; desktop = SIDEBAR DỌC (xem @media cuối tệp) --- */
.nav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;border-bottom:2px solid var(--line);padding-bottom:10px}
.nav button{border-radius:8px 8px 0 0}
.side-brand{display:none;align-items:center;gap:9px;padding:4px 8px 12px;margin-bottom:8px;border-bottom:1px solid rgba(255,255,255,.14)}
.side-brand .hieu-o{width:34px;height:34px;font-size:17px;background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.22);color:#fff}
.side-brand-t b{color:#fff;font-size:15px;line-height:1.1;display:block}
.side-brand-t small{color:#9fb2cd;font-size:10px;letter-spacing:.08em;text-transform:uppercase}
/* --- Thẻ ghế (tab Điều khiển) ---
   Bảng hợp cho đối soát (so số theo cột), nhưng KHÔNG hợp cho điều khiển: người bấm đang đứng
   cạnh một con ghế cụ thể và cần thấy đúng nó, to và rõ, chứ không dò theo hàng. */
.ghe-luoi{display:grid;grid-template-columns:repeat(auto-fill,minmax(258px,1fr));gap:12px}
.ghe{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px;
  box-shadow:0 2px 8px rgba(31,45,74,.06)}
.ghe.dut{border-color:#f0c2c2;background:#fdf5f5}
/* Ghế đang chạy: viền xanh lá đậm để bắt mắt trong lưới nhiều thẻ trắng. */
.ghe.chay{border-color:#8fd3a9;box-shadow:0 0 0 1px rgba(46,155,87,.25),0 2px 10px rgba(31,45,74,.08)}
.ghe-dau{display:flex;align-items:center;gap:8px;margin-bottom:2px}
.ghe-ma{font-size:17px;font-weight:700;color:var(--ink)}
.ghe-cs{font-size:12px;color:var(--mut);margin-bottom:10px}
.ghe-dh{font-size:31px;font-weight:700;color:var(--green);margin:6px 0 2px;
  font-variant-numeric:tabular-nums;letter-spacing:.01em}
.ghe-tien-loi{margin:8px 0;padding:8px 10px;border-radius:8px;font-size:12px;line-height:1.45}
.ghe-tien-loi div{font-weight:400;margin-top:3px;opacity:.92}
.ghe-tien-loi.dang{background:#fbe6e6;border:1px solid #f1bcbc;color:#b02b2b;font-weight:700}
.ghe-tien-loi.cu{background:#fdf4e3;border:1px solid #f0d9ac;color:#8a6416;font-weight:600}
.ghe-nut{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:11px}
.ghe-nut button{padding:9px 6px;font-size:13px}
.b-bat{background:#e8f6ee;border-color:#9bd6b3;color:#1f7a44}
.b-tat{background:#fbe9e9;border-color:#eeb6b6;color:#b23636}
.b-kd{background:#eaf2fb;border-color:#b6d0ec;color:#2a5f9e}
.ghe-hang{display:flex;gap:6px;align-items:center;margin-top:8px}
.ghe-hang input{width:70px}
.ghe-hang label{font-size:11px;color:var(--mut)}
/* --- Bảng chốt ca thu tiền --- */
/* Khung camera quét QR. Vuông, bo góc, và cao vừa đủ để cầm một tay trong lúc tay kia giữ
   ngăn tiền — cao hơn nữa thì nút Đóng tụt xuống dưới mép màn trên điện thoại nhỏ. */
/* Nhóm ô tích phân quyền. Mỗi nhóm một khối có viền — ba nhóm nằm liền nhau không viền thì
   người đọc không biết ô tích nào thuộc câu hỏi nào, và tích nhầm nhóm là cấp nhầm quyền. */
/* Ô chỉ số trên thẻ ghế. Hai con số phải ĐỌC ĐƯỢC TỪ XA — người ta cầm điện thoại một tay,
   tay kia đang giữ ngăn tiền, và mắt thì đang nhìn qua nhìn lại giữa màn máy đếm và màn này. */
/* Dải báo lỗi. Đỏ, dính đầu trang, không cuộn mất — người dùng phải thấy nó mà không phải tìm. */
/* Số bản: nhỏ, mờ, cạnh đồng hồ — không tranh chỗ với số liệu, nhưng luôn đọc được. */
.ban-top{font-size:11px;color:var(--mut);letter-spacing:.04em;padding:0 4px}
.bao-loi{position:sticky;top:0;z-index:99;padding:10px 14px;background:#fbe6e6;color:#8a1f1f;
  font-size:13px;line-height:1.5;border-bottom:1px solid #eeb6b6}
.cs-hop{margin:8px 0 2px;padding:9px 11px;border-radius:10px;
  background:var(--blue-bg);border:1px solid #cfe0f2}
.cs-hop.chua{background:#f4f6f9;border-color:var(--line)}
.cs-nh{font-size:10.5px;letter-spacing:.07em;text-transform:uppercase;color:var(--mut)}
.cs-so{display:flex;align-items:baseline;gap:8px;margin:2px 0 1px;
  font-variant-numeric:tabular-nums}
.cs-so .cu{font-size:16px;color:var(--mut)}
.cs-so .mui{font-size:14px;color:var(--mut)}
.cs-so .moi{font-size:22px;font-weight:800;color:var(--blue)}
.cs-p{font-size:11.5px;color:var(--mut)}
.ph-nhom{padding:12px 13px;border-radius:10px;margin:0 0 10px;
  background:#f4f6f9;border:1px solid var(--line)}
.ph-nhom .nh{color:var(--ink);font-weight:700;font-size:14px}
.ph-o{display:flex;flex-wrap:wrap;gap:8px 16px}
.ph-tick{display:flex;align-items:center;gap:6px;font-size:13.5px;cursor:pointer;color:var(--ink2)}
.ph-tick.khoa{opacity:.6;cursor:not-allowed}
.quet-hop{margin-top:12px}
.quet-hop video{width:100%;max-height:280px;object-fit:cover;border-radius:12px;
  background:#000;border:1px solid var(--line)}
.quet-hop button{width:100%;margin-top:8px}
.man{position:fixed;inset:0;background:rgba(20,30,50,.55);display:flex;align-items:center;
  justify-content:center;padding:14px;z-index:50;overflow:auto}
.hop{background:#fff;border:1px solid var(--line);border-radius:14px;
  padding:18px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(31,45,74,.28)}
.hop h3{margin:0 0 2px;font-size:18px;color:var(--ink)}
.hop .cs{font-size:12px;color:var(--mut);margin-bottom:14px}
.so-hang{display:flex;justify-content:space-between;align-items:baseline;padding:9px 0;
  border-bottom:1px solid #eef1f5}
.so-hang:last-of-type{border-bottom:0}
.so-hang .nh{font-size:13px;color:var(--mut)}
.so-hang .gt{font-size:16px;font-weight:700;color:var(--ink)}
.so-hang.to{border-top:1px solid var(--line);margin-top:6px;padding-top:12px}
.so-hang.to .nh{color:var(--ink);font-weight:600}
.so-hang.to .gt{font-size:21px;color:var(--blue)}
.o-thu{margin:14px 0 8px}
.o-thu input{font-size:23px;text-align:right;padding:11px 13px;font-weight:700}
.phim{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:10px 0}
.phim button{padding:13px 0;font-size:17px}
.hop-nut{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.hop-nut button{padding:12px 0}
.canh{background:#fbe6e6;border:1px solid #eeb6b6;border-radius:9px;padding:9px 11px;
  font-size:12px;color:#b23636;margin-top:10px}
.act input{width:66px;padding:5px 7px}
.act select{font:inherit;border-radius:8px;border:1px solid #c9d3e0;background:#fff;color:var(--ink);padding:6px 8px;max-width:130px}
.note code{background:#fff;border:1px solid var(--line);padding:1px 5px;border-radius:5px;color:var(--ink)}
.act button{padding:5px 10px;font-size:12px}
@media(max-width:560px){.hide-sm{display:none}.wrap{padding:10px}}

/* ============================================================================================
 * MÀN MÁY TÍNH. Bản đầu chỉ ngắm điện thoại nên trên màn rộng nó bó vào một cột giữa, hai bên
 * bỏ trống hơn nửa màn hình — mà người ngồi văn phòng đối soát cuối ngày lại dùng đúng màn đó.
 *
 * Không chỉ nới bề ngang: xếp "Theo cơ sở" và "Theo ghế" NẰM CẠNH NHAU. Hai bảng đó là hai
 * cách nhìn cùng một số tiền, đặt cạnh nhau thì so được bằng mắt; xếp dọc thì phải cuộn qua
 * lại và người ta thôi không so nữa.
 * ============================================================================================ */
@media(min-width:1100px){
  .wrap{max-width:1400px;padding:20px 26px}
  body{font-size:15.5px}
  h1{font-size:22px}
  .kpis{gap:14px}
  .kpi{padding:14px 18px}
  .kpi .vl{font-size:25px}
  .card{padding:16px 18px}
  .card h2{font-size:15px}
  table{font-size:14px}
  td{padding:10px 10px 10px 0}
  /* Hai bảng tổng hợp nằm cạnh nhau */
  .doi{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
  .doi .card{margin-bottom:0}
  /* Ô nhập trong cột "Việc" đang dính sát nhau ở màn rộng — cho chúng thở */
  .act{gap:8px}
  .act input{width:78px}
}
@media(min-width:1500px){
  .wrap{max-width:1560px}
  .kpis{grid-template-columns:repeat(4,1fr)}
}
/* ============================================================================================
 * SIDEBAR DỌC (kiểu HR V5.2) — chỉ trên màn rộng. Đặt CUỐI để thắng các @media bên trên.
 * `.nav` cố định bên trái, nền navy; nội dung (`.wrap`) đẩy sang phải. Mobile giữ hàng ngang.
 * ============================================================================================ */
@media(min-width:901px){
  .nav{position:fixed;left:0;top:0;bottom:0;width:216px;z-index:30;display:flex;flex-direction:column;
    flex-wrap:nowrap;gap:2px;overflow:auto;margin:0;padding:14px 10px;border:0;border-radius:0;
    background:linear-gradient(180deg,#26406a 0%,#1b2d4b 100%);box-shadow:2px 0 12px rgba(20,30,50,.18)}
  .nav .side-brand{display:flex}
  .nav button{width:100%;text-align:left;border:0;border-radius:0 999px 999px 0;background:transparent;
    color:#c7d3e5;padding:10px 13px;font-size:13px;font-weight:500;white-space:nowrap}
  .nav button:hover{background:rgba(255,255,255,.08);color:#fff;border-color:transparent}
  .nav button.on{background:rgba(255,255,255,.15);color:#fff;font-weight:700;box-shadow:inset 3px 0 0 var(--amber)}
  .wrap{margin:0 0 0 216px;max-width:none;padding:16px 26px}
  .top{border-radius:12px}
}
@media(min-width:1500px){ .wrap{margin-left:216px;max-width:none} }
CSS;
	}

	private static function js() {
		return <<<'JS'
(function(){
var API = window.VHG_API, TOK = null, KY = 'today', D = null, ban = false;
var BAN_APP = window.VHG_BAN || '?';
var TT_Q = '', TT_PG = 0, TT_PER = 20;   // Tình trạng ghế: từ khoá tìm + trang

/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LỖI JAVASCRIPT PHẢI HIỆN RA MÀN, KHÔNG ĐƯỢC NUỐT IM.
 *
 * Anh Thắng 23/08/2026, hai lần liền: *"chưa chốt ca được phải không, chưa thấy ghi nhận"*, rồi
 * *"chốt ca vẫn không thấy phản hồi gì"*.
 *
 * Lần đầu là lỗi thật (`lam` khai lộn phạm vi). Nhưng cái làm mất cả buổi không phải bản thân
 * lỗi đó — mà là việc nó KHÔNG NÓI GÌ CẢ. Trình duyệt ghi vào console rồi thôi; người đứng ở
 * cửa hàng không mở console, nên với họ nút bấm chỉ đơn giản là "không ăn". Không có manh mối
 * nào để gửi cho người sửa, và người sửa thì chỉ còn cách đoán.
 *
 * Nên: mọi lỗi chưa bắt được đều hiện thành một dải đỏ ở đầu trang, kèm câu lỗi, tệp và dòng.
 * Chụp màn hình gửi đi là đủ để sửa — không phải đoán lần thứ ba.
 *
 * ⚠️ Kèm SỐ BẢN. Nửa số lần "vẫn không chạy" là máy chủ còn đang chạy bản cũ. Hiện số bản ngay
 *    trên dải lỗi và ở đầu trang thì câu đó trả lời được trong một giây, không phải hỏi vòng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function baoLoi(chu){
  try {
    var o = document.getElementById('bao-loi');
    if (!o) {
      o = document.createElement('div');
      o.id = 'bao-loi';
      o.className = 'bao-loi';
      document.body.insertBefore(o, document.body.firstChild);
    }
    o.textContent = '⚠️ Lỗi trên trang (bản ' + BAN_APP + '): ' + chu
      + ' — chụp màn hình này gửi cho kỹ thuật giúp em.';
  } catch (e) { /* hết đường rồi thì thôi, đừng để chính chỗ báo lỗi ném lỗi */ }
}
window.onerror = function(chu, tep, dong){
  baoLoi(String(chu) + ' (' + String(tep).split('/').pop() + ':' + dong + ')');
  return false;   // vẫn để trình duyệt ghi vào console cho người biết mở console
};
window.addEventListener('unhandledrejection', function(e){
  baoLoi('Lượt gọi hỏng: ' + ((e && e.reason && e.reason.message) || e.reason || '?'));
});

/* Bọc một hàm xử lý bấm: ném lỗi thì HIỆN RA, chứ không im. `window.onerror` bắt được lỗi ném
   thẳng, nhưng bọc ở đây thì câu báo nói rõ đang bấm cái gì. */
function bam(ten, f){
  return function(){
    try { return f.apply(this, arguments); }
    catch (e) { baoLoi(ten + ' — ' + (e && e.message ? e.message : e)); }
  };
}
var TEN_HT = window.VHG_TEN || 'POSH Massage';
/* Tab đang mở. Nhớ lại giữa các lần tải: người đang điều khiển ghế bấm ↻ mà bị đá về tab đối
   soát là mỗi lượt bấm mất thêm một cú bấm nữa. */
var TAB = 'doi-soat';
try { TAB = localStorage.getItem('vhg_tab') || 'doi-soat'; } catch(e) {}

/* ============================================================================================
 * HAI NGÔN NGỮ.
 *
 * Cặp Việt/Anh viết NGAY TẠI CHỖ dùng — `L('Đối soát','Reconciliation')` — chứ không gom vào
 * một bảng khoá kiểu `t('tab.doisoat')`. Bảng khoá đọc gọn hơn ở chỗ dùng, nhưng ở đây nó sai:
 * cả tệp này là những câu giải thích dài cho người đứng quầy, mà một câu tiếng Việt nằm cách
 * bản dịch của nó bốn trăm dòng thì sửa một bên quên bên kia là chuyện chắc chắn xảy ra. Để
 * cạnh nhau thì không sửa lệch được.
 *
 * ⚠️ CON SỐ KHÔNG DỊCH. Tiền vẫn định dạng kiểu Việt Nam ("50.000đ") ở cả hai ngôn ngữ: đây là
 *    tiền Việt đếm trong két Việt, và người nước ngoài đọc bảng này vẫn phải đối chiếu với tờ
 *    tiền thật trên tay. Đổi dấu chấm/phẩy theo tiếng Anh là mời người ta đọc nhầm 50.000 thành
 *    năm mươi.
 * ============================================================================================ */
var NN = 'vi';
try { NN = localStorage.getItem('vhg_nn') === 'en' ? 'en' : 'vi'; } catch(e) {}
function L(vi, en){ return NN === 'en' ? en : vi; }

/* ════════════════════════════════════════════════════════════════════════════════════════════
 * CÂU CÓ CHÈN SỐ.
 *
 * 🔴 LỖI 23/08/2026 — CẢ TRANG TRẮNG. Anh Thắng: *"chốt ca vẫn không thấy phản hồi gì"*, rồi dải
 *    báo lỗi chỉ đúng thủ phạm: `Uncaught ReferenceError: Lf is not defined`.
 *
 *    `Lf` có ở TRANG KHÁCH (class-vhg-shop.php) chứ không có ở đây. Viết `Lf(...)` trong tệp này
 *    là gọi một hàm không tồn tại — và vì nó nằm trong `veQuy()`, tức là trong lượt dựng màn,
 *    nên lỗi ném ra trước cả khi `#app` có nội dung. Toàn trang trắng, chỉ còn chân trang (chân
 *    trang dựng ở máy chủ nên nó sống sót — đúng lý do đặt nó ngoài JS).
 *
 *    Hai tệp có hai bộ dịch KHÁC HẲN NHAU: trang khách tra từ điển theo khoá tiếng Việt, trang
 *    này thì nhận thẳng hai chuỗi `L(vi, en)`. Chép một dòng mã từ tệp kia sang là chép luôn giả
 *    định của tệp kia.
 *
 * ⚠️ Tham số bắt đầu từ vị trí thứ BA (sau `vi` và `en`) — khác `Lf` của trang khách, nơi câu
 *    tiếng Việt vừa là khoá vừa là bản dịch nên chỉ có một chuỗi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function Lf(vi, en){
  var t = L(vi, en), a = arguments;
  return String(t).replace(/\{(\d)\}/g, function(_, i){
    var v = a[Number(i) + 2];
    return v === undefined ? '' : v;
  });
}
function nutNN(){
  return '<span class="nn">'
    + '<button data-nn="vi"' + (NN==='vi'?' class="on"':'') + '>VI</button>'
    + '<button data-nn="en"' + (NN==='en'?' class="on"':'') + '>EN</button></span>';
}
function noiNN(){
  [].forEach.call(document.querySelectorAll('[data-nn]'), function(b){
    b.onclick = function(){ datNN(b.getAttribute('data-nn')); };
  });
}
function datNN(n){
  NN = (n === 'en') ? 'en' : 'vi';
  try { localStorage.setItem('vhg_nn', NN); } catch(e) {}
  document.documentElement.setAttribute('lang', NN);
  /* Vẽ lại từ dữ liệu ĐANG CÓ. Gọi lại máy chủ chỉ để đổi chữ là bắt người ta chờ 4G cho một
     việc hoàn toàn nằm trong máy họ. */
  if (D) ve(); else veLogin('');
}

/* ============================================================================================
 * TỰ CẬP NHẬT.
 *
 * 🔴 Anh Thắng 22/08/2026: *"bấm điều khiển máy chạy, nhưng trên web thời gian chưa chạy"*. Đúng
 *    — trang chỉ tải khi mở hoặc khi bấm ↻. Người đứng cạnh ghế bấm Bật, ghế chạy thật, nhưng
 *    web vẫn nói "Rảnh"; họ tưởng lệnh không ăn nên bấm lần nữa — mà mỗi lần bấm Bật là CHO
 *    KHÔNG một lượt nữa.
 *
 * Hai nhịp khác nhau, cố ý:
 *   · Tab ĐIỀU KHIỂN 5 giây — người đang đứng đó chờ ghế phản hồi.
 *   · Tab ĐỐI SOÁT 30 giây — số tiền không đổi từng giây, mà trang này mở suốt ngày trên 4G.
 *
 * Và số đếm ngược tự trừ MỖI GIÂY giữa hai lượt hỏi, chứ không đứng im rồi nhảy 5 giây một
 * lần: một con số đứng im là dấu hiệu ghế treo, đừng để giao diện tự tạo ra dấu hiệu đó.
 * ============================================================================================ */
var NHIP_MS = { 'dieu-khien': 2000, 'doi-soat': 30000, 'ghe-loi': 5000, 'nhat-ky-may': 20000 };
/* Ví nhân viên vừa tra — giữ để lượt bấm "Trừ ví, chạy ghế" biết đang làm cho số nào. */
var NV_VI = null;
var QL_LOC = '';   // Tab Quản lý ghế: lọc theo cơ sở ('' = tất cả, '__none__' = chưa gán, còn lại = tên cơ sở)
var QL_PG = 0, QL_PER = 10;   // Quản lý ghế: trang danh sách ghế (10 ghế/trang)
var TM_PG = 0;   // Thu tiền: trang "từng lượt tiền mặt" (20/trang)
var DK_LOC = '';   // Tab Điều khiển: lọc theo cơ sở (cùng quy ước với QL_LOC)
var hen = null, demGiay = null;
try { TOK = localStorage.getItem('vhg_tok'); } catch(e) {}

var app = document.getElementById('app');
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN') + 'đ'; }
/* "còn 4 ngày 3 giờ" — câu người đọc là hiểu.
   ⚠️ Nói GIỐNG HỆT ba nơi kia: VHG_Ma::doc_con_cho() bên máy chủ, và docCho() bên trang khách.
      Cùng một khoảng thời gian mà ba nơi nói ba kiểu là nhân viên đọc một đằng, khách đọc một
      nẻo, rồi cãi nhau xem ai đúng. */
function docCho(giay){
  var g = Math.max(0, Number(giay) || 0);
  var ngay = Math.floor(g / 86400), gio = Math.floor((g % 86400) / 3600);
  if (ngay > 0) return ngay + L(' ngày',' days') + (gio > 0 ? ' ' + gio + L(' giờ',' h') : '');
  if (gio > 0)  return gio + L(' giờ',' hours');
  return Math.max(1, Math.ceil(g / 60)) + L(' phút',' min');
}
/* mm:ss có số 0 ở đầu — ĐÚNG KIỂU MÀN GHẾ VẼ (`snprintf("%02d:%02d")`). Ghế hiện "04:57" mà
   web hiện "4:57" thì cùng một con số ra hai kiểu, và người đối chiếu bằng mắt sẽ dừng lại một
   nhịp để tự hỏi hai chỗ có nói cùng một thứ không. Chiều rộng cố định còn đỡ nhảy chữ khi
   đếm qua mốc 10 phút. */
function mmss(s){ s=Math.max(0,Number(s)||0);
  return String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0'); }

function goi(viec, d, xong0){
  d = d || {}; d.token = TOK;
  var x = new XMLHttpRequest();
  /* 🔴 KHÔNG CÓ timeout/onerror TRƯỚC ĐÂY = TREO VĨNH VIỄN khi mạng rớt giữa chừng. Nút
     "Đang lưu…" (ktAct) đứng mãi vì readyState không bao giờ lên 4 nên xong() không bao giờ
     gọi — ban=true khoá luôn mọi nút khác, không cách nào tự phục hồi ngoài tải lại trang. */
  var xongMotLan=false; function xong(r){ if(xongMotLan) return; xongMotLan=true; xong0(r); }
  x.open('POST', API + (API.indexOf('?')<0?'?':'&') + 'api=' + viec, true);
  x.setRequestHeader('Content-Type','application/json');
  x.timeout = 25000;
  x.onreadystatechange = function(){
    if (x.readyState !== 4) return;
    var r = null;
    try { r = JSON.parse(x.responseText); } catch(e){}
    /* Máy chủ trả rác (tường lửa hosting chèn trang chặn, mạng đứt giữa chừng) KHÔNG được
       thành "hết phiên" — đá người ta ra rồi họ gõ lại PIN và gặp đúng lỗi đó. */
    if (!r) { xong({ ok:false, error:L('Không đọc được trả lời của máy chủ (mạng hoặc tường lửa).',
      'Could not read the server reply (network or firewall).') }); return; }
    if (r.ma === 'het_phien') { TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(L('Phiên đã hết — đăng nhập lại.','Session expired — please sign in again.')); return; }
    xong(r);
  };
  x.ontimeout = function(){ xong({ ok:false, error:L('Máy chủ không trả lời — mạng yếu hoặc quá tải. Thử lại.',
    'Server did not respond — weak network or overload. Try again.') }); };
  x.onerror = function(){ xong({ ok:false, error:L('Mất kết nối mạng khi gửi. Thử lại.',
    'Network connection lost while sending. Try again.') }); };
  x.send(JSON.stringify(d));
}

// ------------------------------------------------------------------ đăng nhập
function veLogin(loi){
  app.innerHTML =
    '<div class="login"><div class="hieu-o">💆</div>'
    + '<h1>' + esc(TEN_HT) + '<small>' + L('Doanh thu &amp; tình trạng ghế','Revenue &amp; chair status')
    + '</small></h1>'
    + '<input id="pin" type="tel" inputmode="numeric" maxlength="8" placeholder="PIN" autocomplete="off">'
    + '<button id="vao" class="on" style="width:100%">' + L('Vào','Sign in') + '</button>'
    /* Cửa RIÊNG cho nhân viên thu tiền: họ dùng PIN báo cáo (bc_pin), KHÔNG có token /ghe.
       Bấm là mở module báo cáo doanh thu tự chứa (cổng bc-PIN riêng). */
    + '<button id="vao-bc" class="ghost" style="width:100%;margin-top:8px">📋 '
      + L('Báo cáo doanh thu','Revenue report') + '</button>'
    + '<div class="err" id="e">' + esc(loi||'') + '</div>'
    + '<div class="nn-doi">' + nutNN() + '</div></div>';
  noiNN();
  var pin = document.getElementById('pin'), e = document.getElementById('e');
  var vbc = document.getElementById('vao-bc');
  if (vbc) { vbc.onclick = function(){ if (window.VHG_BaoCao) window.VHG_BaoCao.mo(); }; }
  function thu(){
    var v = (pin.value||'').trim();
    if (!v) { e.textContent = L('Chưa nhập PIN.','Please enter your PIN.'); return; }
    e.textContent = L('Đang kiểm…','Checking…');
    goi('login', { pin: v }, function(r){
      if (!r.ok) { e.textContent = r.error || L('PIN không đúng','Incorrect PIN'); pin.value=''; pin.focus(); return; }
      TOK = r.token; try{localStorage.setItem('vhg_tok',TOK);}catch(er){}
      tai();
    });
  }
  document.getElementById('vao').onclick = thu;
  pin.addEventListener('keydown', function(ev){ if (ev.key === 'Enter') thu(); });
  pin.focus();
}

// ------------------------------------------------------------------ màn chính
function tai(im){
  goi('so_lieu', { ky: KY }, function(r){
    if (!r.ok) { if (!im) veLogin(r.error || ''); return; }
    D = r; ve();
  });
}

/* Hẹn lượt hỏi kế tiếp. Luôn huỷ lượt cũ trước: không thì mỗi lần vẽ lại là thêm một đồng hồ,
   và sau mươi phút trang tự hỏi máy chủ vài chục lần một giây. */
function henLai(){
  if (hen) { clearTimeout(hen); hen = null; }
  if (!TOK) return;
  /* Tab QUẢN LÝ không tự hỏi lại: vẽ lại giữa chừng sẽ xoá ô đang gõ (thêm ghế/địa điểm).
     Dữ liệu quản lý ít đổi; muốn mới thì bấm ↻, còn mỗi thao tác thêm/xoá đã tự tải lại. */
  if (TAB === 'quan-ly') return;
  hen = setTimeout(function(){
    /* KHÔNG hỏi khi: người dùng đang chờ một lệnh chạy xong, đang mở bảng chốt ca (vẽ lại là
       xoá mất số họ đang gõ), đang GÕ vào một ô nào đó, hoặc trang đang ẩn (điện thoại trong túi
       — hỏi cũng không ai đọc, chỉ tốn 4G). */
    if (ban || CHOT || document.hidden || dangGoField()) { henLai(); return; }
    /* Tab Điều khiển: CHỈ cập nhật lưới ghế TẠI CHỖ (ghế chạy/mất kết nối/đếm ngược), KHÔNG vẽ
       lại cả trang — vẽ lại mỗi 2 giây là xoá số phút/tiền mặt anh vừa gõ trên thẻ ghế. */
    if (TAB === 'dieu-khien') { capNhatDieuKhien(); return; }
    tai(true);
  }, NHIP_MS[TAB] || 30000);
}

/* Có đang gõ vào ô nhập nào không (input/textarea/select đang được chọn). Đang gõ thì hoãn lượt
   làm mới tự động để không giật con trỏ / mất chữ. */
function dangGoField(){
  var a = document.activeElement;
  if (!a) return false;
  var t = (a.tagName || '').toUpperCase();
  return t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT';
}

/* Làm mới TAB ĐIỀU KHIỂN mà KHÔNG vẽ lại cả trang: chỉ thay lưới ghế, giữ nguyên ô Số phút/Tiền
   mặt đang gõ dở và form "tiêu ví hộ khách". */
function capNhatDieuKhien(){
  goi('so_lieu', { ky: KY }, function(r){
    if (!r || !r.ok) { henLai(); return; }
    D = r;
    var g = document.getElementById('dk-grid');
    if (TAB !== 'dieu-khien' || !g) { ve(); return; }   // đã đổi tab trong lúc chờ mạng → vẽ lại bình thường
    var keep = {};
    [].forEach.call(g.querySelectorAll('input[data-phut],input[data-tien]'), function(i){
      var k = i.getAttribute('data-phut') ? ('p:' + i.getAttribute('data-phut')) : ('t:' + i.getAttribute('data-tien'));
      keep[k] = i.value;
    });
    g.innerHTML = dkLuoiHtml();
    [].forEach.call(g.querySelectorAll('input[data-phut],input[data-tien]'), function(i){
      var k = i.getAttribute('data-phut') ? ('p:' + i.getAttribute('data-phut')) : ('t:' + i.getAttribute('data-tien'));
      if (keep[k] != null && keep[k] !== '') i.value = keep[k];
    });
    noi();   // gắn lại các nút trong lưới + hẹn lượt kế + đồng hồ đếm ngược
  });
}

/* Đồng hồ đếm ngược chạy TẠI CHỖ giữa hai lượt hỏi. Chỉ đụng vào phần chữ của con số, không
   vẽ lại cả trang — vẽ lại mỗi giây là mất luôn ô đang gõ dở và nút đang bấm. */
function chayDongHo(){
  if (demGiay) { clearInterval(demGiay); demGiay = null; }
  if (TAB !== 'dieu-khien' || !D) return;
  demGiay = setInterval(function(){
    if (!D || document.hidden) return;
    var co = false;
    D.may.forEach(function(m){
      if (m.tt !== 'running' || !m.song) return;
      if (m.con_lai > 0) { m.con_lai--; co = true; }
      var o = document.querySelector('[data-dh="' + m.ma + '"]');
      if (o) o.textContent = mmss(m.con_lai);
    });
    /* Hết giờ tại chỗ thì hỏi lại ngay, đừng đợi hết nhịp 5 giây: lúc đó trạng thái ghế vừa
       đổi và đó chính là thứ người ta đang chờ xem. */
    if (!co) { clearInterval(demGiay); demGiay = null; if (!ban && !CHOT) tai(true); }
  }, 1000);
}

function ve(){
  var t = D.tong, h = '';

  h += '<div class="wrap"><div class="top">'
    + '<div class="hieu"><div class="hieu-o">💆</div>'
    + '<h1>' + esc(TEN_HT) + '<small>' + esc(D.ai.name) + ' · ' + esc(D.ai.role) + '</small></h1></div>'
    + '<span class="sp"></span>'
    /* Đồng hồ chạy từng giây như bảng thiết kế. Lấy giờ MÁY CHỦ làm mốc rồi tự tích, không lấy
       giờ điện thoại: điện thoại nhân viên hay lệch, mà mọi con số khác trên trang này đều theo
       giờ máy chủ — hai loại giờ cạnh nhau là mời người ta đối chiếu nhầm. */
    + '<span class="dh-top" id="dh-top">' + esc(D.luc) + '</span>'
    + '<span class="ban-top" title="' + L('Bản plugin đang chạy','Running plugin version') + '">v'
      + esc(BAN_APP) + '</span>'
    + nutNN()
    + '<button id="lam-moi" class="ghost" title="' + L('Tải lại','Refresh') + '">↻</button>'
    + '<button id="thoat" class="ghost">' + L('Thoát','Sign out') + '</button></div>';

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * TAB HIỆN THEO QUYỀN.
   *
   * ⚠️ ĐÂY CHỈ LÀ DỌN MẮT, KHÔNG PHẢI CHỐT. Chốt thật nằm ở `VHG_Auth::VIEC_QUAN_TRI`, chặn ngay
   *    đầu cổng — ai đọc được gói tin thì gọi thẳng cổng được, và giấu nút không cản nổi ai cả.
   *    Máy chủ cũng KHÔNG GỬI số liệu của các tab này cho người thu (xem `so_lieu_nhan_vien`),
   *    nên kể cả sửa JS trên máy mình thì các tab đó cũng rỗng.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var QT = QUAN_TRI(), GK = GIUP_KHACH(), KT = CHOT_DS();
  var TABS = [];
  if (QT) TABS.push(['doi-soat', '📊 ' + L('Đối soát','Reconciliation')]);
  if (QT) TABS.push(['thu-tien', '💵 ' + L('Thu tiền','Cash collection')]);
  TABS.push(['quy', '🧾 ' + L('Quỹ &amp; nộp tiền','Cash float')]);
  /* Anh Thắng: hệ chốt ca quét QR tạm chưa chạy được — cho Người thu một lối vào MÀN BÁO CÁO
     DOANH THU ngay trong trang chính (không phải đăng xuất rồi bấm nút riêng ở màn đăng nhập).
     Tab này KHÔNG tự làm báo cáo — nó chỉ mở lại đúng module bc-app đã có (`moBaoCao()`, lộ ra
     ngoài qua `window.VHG_BaoCao.mo`), vẫn đòi PIN báo cáo (`bc_pin`) như cũ. Cố ý KHÔNG tự
     đăng nhập bằng token /ghe đang có — hai cổng PIN vẫn tách biệt, xem lý do ở đầu `VHG_Auth`. */
  TABS.push(['bc-doanhthu', '📋 ' + L('Báo cáo doanh thu','Revenue report')]);
  if (QT) TABS.push(['kich-hoat', '⚡ ' + L('Kích hoạt ghế','Chair activation')]);
  if (QT) TABS.push(['quan-ly', '🪑 ' + L('Quản lý ghế','Chairs &amp; sites')]);
  if (QT) TABS.push(['nhat-ky-may', '🔌 ' + L('Lịch sử tắt mở máy','Power on/off log')]);
  if (QT) TABS.push(['ma', '🎁 ' + L('Mã giảm giá','Discount codes')]);
  /* PIN nhân viên báo cáo — Admin hoặc vai trò Quản trị (cấp PIN cho nhân viên mình quản lý). */
  if (QT) TABS.push(['bc-pin', '📋 ' + L('PIN báo cáo','Report PINs')]);
  /* Trang kế toán: duyệt báo cáo doanh thu — vai trò Chốt doanh số / Quản lý / Admin. */
  if (QT || KT) TABS.push(['kt-duyet', '📈 ' + L('Duyệt báo cáo','Review reports')]);
  if (QT || KT) TABS.push(['kt-denghi', '⚖️ ' + L('Đề nghị &amp; yêu cầu','Requests')]);
  if (QT || KT) TABS.push(['kt-lichsu', '🏢 ' + L('Doanh thu địa điểm','Site revenue')]);
  if (QT || KT) TABS.push(['kt-tien', '💰 ' + L('Đối soát &amp; công nợ','Reconcile &amp; debt')]);
  if (QT || KT) TABS.push(['kt-xuat', '📤 ' + L('Xuất MISA','Export MISA')]);
  if (QT) TABS.push(['kt-nhap', '📥 ' + L('Nhập doanh thu cũ','Import old data')]);
  /* 🔴 Tab Điều khiển ghế theo quyền GIÚP KHÁCH, không theo quyền quản trị.
     Anh Thắng 23/08/2026: *"Đấy là bạn Hotline bật ghế cho khách chứ không phải nhân viên"*.
     Bạn Hotline phải vào được tab này mà KHÔNG được thấy doanh thu. */
  if (GK) TABS.push(['dieu-khien', '🎛 ' + L('Điều khiển ghế','Chair control')]);
  if (GK) TABS.push(['ghe-loi', '🚨 ' + L('Ghế lỗi','Faulty chairs')]);
  if (QT) TABS.push(['cau-hinh', '⚙️ ' + L('Cấu hình','Settings')]);
  h += '<div class="nav">'
    + '<div class="side-brand"><div class="hieu-o">💆</div><div class="side-brand-t"><b>POSH</b>'
      + '<small>' + L('Ghế massage','Massage chairs') + '</small></div></div>'
    + TABS.map(function(x){
        return '<button data-tab="' + x[0] + '"' + (TAB===x[0]?' class="on"':'') + '>' + x[1] + '</button>';
      }).join('')
    + '</div>';
  /* 🔴 TAB ĐANG CHỌN PHẢI NẰM TRONG DANH SÁCH ĐƯỢC PHÉP.
     Người thu chỉ có một tab; bạn Hotline có hai. Để `TAB` trỏ vào một tab họ không có là màn
     hình trắng — và họ sẽ tưởng app hỏng chứ không nghĩ là mình không đủ quyền. */
  var co_tab = false;
  TABS.forEach(function(x){ if (x[0] === TAB) co_tab = true; });
  if (!co_tab) { TAB = TABS.length ? TABS[0][0] : 'quy'; }

  /* Ba tab BÁO CÁO đều xem theo kỳ, nên bộ chọn kỳ hiện cho cả ba. Tab Điều khiển thì không:
     ở đó không có con số nào theo kỳ, để bộ chọn ra là mời người ta bấm rồi tự hỏi vừa đổi gì. */
  if (TAB === 'doi-soat' || TAB === 'thu-tien' || TAB === 'quy' || TAB === 'kich-hoat' || TAB === 'ma' || TAB === 'nhat-ky-may') {
    var kyThang = /^\d{4}-\d{2}$/.test(KY);   // đang xem một THÁNG cụ thể?
    h += '<div class="tabs" style="align-items:center">';
    [['today',L('Hôm nay','Today')],['week',L('Tuần này','This week')],['month',L('Tháng này','This month')],
     ['year',L('Năm nay','This year')],['all',L('Tất cả','All time')]]
      .forEach(function(k){ h += '<button data-ky="' + k[0] + '"' + (KY===k[0]?' class="on"':'') + '>' + k[1] + '</button>'; });
    h += '<span class="mut" style="margin-left:4px">' + L('hoặc tháng','or month') + '</span>'
      + '<input type="month" id="ky-thang" value="' + (kyThang ? KY : '') + '" style="width:auto;max-width:150px"'
      + (kyThang ? ' class="on"' : '') + '>';
    h += '</div>';
  }

  /* GHẾ CHỜ GÁN — trên cùng luôn, trên cả cảnh báo mất kết nối.
     Ghế vừa cắm điện xong là thứ người đang đứng cạnh nó cần thấy đầu tiên; và chừng nào chưa
     gán mã thì nó KHÔNG vẽ được QR, tức là không thu được đồng nào. */
  if (QT && D.choGan && D.choGan.length) {
    h += '<div class="note"><b>' + D.choGan.length + ' '
      + L('ghế vừa nối mạng, chưa có mã','chairs just came online with no code') + '</b> — '
      + L('ghế chưa gán mã thì không hiện được QR. Đặt mã ngắn (VD <code>AMTP01</code>): mã này đi '
          + 'vào nội dung chuyển khoản khách gõ tay.',
          'a chair with no code cannot show a QR. Give it a short code (e.g. <code>AMTP01</code>): '
          + 'this code goes into the transfer memo the customer types by hand.')
      + '<table style="margin-top:8px"><tr><th>MAC</th><th class="hide-sm">'
      + L('Tình trạng','Status') + '</th>'
      + '<th class="r">' + L('Gán mã + cơ sở','Assign code + branch') + '</th></tr>'
      + D.choGan.map(function(g, i){
          return '<tr><td><code>' + esc(g.mac) + '</code><br><span class="mut">' + esc(g.ma) + '</span></td>'
            + '<td class="hide-sm"><span class="pill ' + (g.song?'p-ok':'p-off') + '">'
            + (g.song?L('đang sống','online'):L('mất kết nối','offline')) + '</span></td>'
            + '<td class="r"><div class="act" style="justify-content:flex-end">'
            + '<input type="text" placeholder="AMTP01" data-gma="' + esc(g.ma) + '" style="width:96px">'
            + '<select data-gcs="' + esc(g.ma) + '"><option value="0">— '
            + L('cơ sở','branch') + ' —</option>'
            + (D.coso||[]).map(function(c){
                return '<option value="' + c.id + '">' + esc(c.ten) + '</option>'; }).join('')
            + '</select><button data-gan="' + esc(g.ma) + '">' + L('Gán','Assign')
            + '</button></div></td></tr>'; }).join('')
      + '</table></div>';
  }

  /* LUẬT 2: hỏng để TRÊN CÙNG, trên cả con số doanh thu. */
  var dut = D.may.filter(function(m){ return !m.song; });
  /* Chỉ hiện ghế mất kết nối của CƠ SỞ MÌNH QUẢN LÝ — anh Thắng: người quản lý một cơ sở mở
     Đối soát ra thấy cả trăm ghế mất kết nối của cơ sở khác thì không phân biệt được đâu là
     việc của mình. `D.ai.coso` rỗng = quản lý cả chuỗi (Admin…) — giữ nguyên xem hết, như cũ.
     Có giá trị thì lọc; tách ';' / ',' vì một người có thể phụ trách nhiều cơ sở (xem cách
     hồ sơ nhân sự đẩy "Cơ sở phụ" — cùng quy ước). */
  var coSoToi = (D.ai && D.ai.coso ? String(D.ai.coso) : '').split(/[;,]/)
    .map(function(s){ return s.trim().toLowerCase(); }).filter(Boolean);
  if (coSoToi.length) {
    dut = dut.filter(function(m){ return coSoToi.indexOf(String(m.coso||'').trim().toLowerCase()) >= 0; });
  }
  if (dut.length) {
    /* Danh sách dài (đang dựng hệ, chưa cắm ghế nào) thì cuộn trong ô nhỏ, không để tràn kín màn. */
    var dsDut = esc(dut.map(function(m){ return m.ma + (m.coso ? ' (' + m.coso + ')' : ''); }).join(', '));
    h += '<div class="warn"><b>' + dut.length + ' ' + L('ghế mất kết nối','chairs offline') + '</b> — '
      + L('Khách vẫn quét được tem QR, tiền vẫn vào, nhưng ghế KHÔNG chạy.',
          'Customers can still scan the QR, money still arrives, but the chair will NOT run.')
      + '<div style="max-height:56px;overflow:auto;margin-top:6px;font-size:12px;opacity:.85;'
      + 'line-height:1.5;padding:4px 8px;border-radius:8px;background:rgba(0,0,0,.18)">' + dsDut + '</div></div>';
  }
  if (D.cho.length) {
    h += '<div class="note"><b>' + D.cho.length + ' '
      + L('lượt đã trả tiền mà ghế chưa nhận','paid sessions the chair has not picked up') + '</b> — '
      + L('bình thường ghế lấy trong ~10 giây.','a chair normally picks one up within ~10 seconds.')
      + '<table style="margin-top:8px"><tr><th>' + L('Lúc','Time') + '</th><th>'
      + L('Ghế','Chair') + '</th>'
      + '<th class="r">' + L('Số tiền','Amount') + '</th></tr>'
      + D.cho.slice(0,8).map(function(c){
          return '<tr><td>' + esc(c.luc) + '</td><td>' + esc(c.ma_may) + '</td>'
            + '<td class="r">' + tien(c.so_tien) + '</td></tr>'; }).join('')
      + '</table></div>';
  }

  if (TAB === 'dieu-khien') { h += veDieuKhien() + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'ghe-loi')    { h += veGheLoi()    + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'thu-tien')   { h += veThuTien()   + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'quy')        { h += veQuy()       + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'bc-doanhthu'){ h += veBcDoanhThu() + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'cau-hinh')   { h += veCauHinh()  + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'bc-pin')     { h += veBcPin()    + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kt-duyet')   { h += veKtDuyet()  + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kt-denghi')  { h += veKtDenghi() + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kt-lichsu')  { h += veKtLichSu() + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kt-tien')    { h += veKtTien()   + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kt-xuat')    { h += veKtXuat()   + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kt-nhap')    { h += veKtNhap()   + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'kich-hoat')  { h += veKichHoat()  + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'quan-ly')    { h += veQuanLy()    + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'nhat-ky-may'){ h += veNhatKyMay() + '</div>'; app.innerHTML = h; noi(); return; }
  if (TAB === 'ma')        { h += veMa()        + '</div>'; app.innerHTML = h; noi(); return; }

  h += '<div class="kpis">'
    + kpi(L('Tổng doanh thu','Total revenue'), tien(t.tong), t.so_luot + ' ' + L('lượt','sessions'), 'a')
    + kpi(L('Chuyển khoản (QR)','Bank transfer (QR)'), tien(t.qr), t.qr_luot + ' ' + L('lượt','sessions'), 'b')
    + kpi(L('Tiền mặt','Cash'), tien(t.tien_mat), t.tien_mat_luot + ' ' + L('lượt','sessions'), 'c')
    + kpi(L('Đang chờ ghế nhận','Waiting for a chair'), String(D.cho.length),
        L('đã trả, chưa chạy','paid, not started'), 'd')
    + '</div>';

  // --- biểu đồ: cơ cấu tiền, theo khu vực, top cơ sở, top ghế (kỳ đang chọn).
  h += veBieuDo(t);

  // --- tình trạng ghế: LIỆT KÊ (tìm kiếm + phân trang 20). Bấm nút thì sang tab Điều khiển.
  h += '<div class="card"><h2>' + L('Tình trạng ghế','Chair status') + '</h2>'
    + '<div class="act" style="margin-bottom:10px"><input id="tt-q" placeholder="'
    + L('Tìm theo mã ghế hoặc cơ sở…','Search chair or branch…') + '" style="max-width:340px" value="' + esc(TT_Q) + '"></div>'
    + '<div id="tt-wrap"></div>'
    + '<p class="mut" style="margin:9px 0 0">'
    + L('Bật/tắt ghế ở tab <b>🎛 Điều khiển ghế</b>.',
        'Turn chairs on/off in the <b>🎛 Chair control</b> tab.') + '</p></div>';

  /* Hai bảng tổng hợp trong một khối: trên màn rộng chúng nằm cạnh nhau (xem .doi trong CSS),
     trên điện thoại vẫn xếp dọc như cũ. */
  h += '<div class="doi">';
  h += bang(L('Theo cơ sở','By branch'),
    [L('Cơ sở','Branch'),L('Lượt','Sessions'),'QR',L('Tiền mặt','Cash'),L('Tổng','Total')],
    Object.keys(t.theo_coso).map(function(k){ var c = t.theo_coso[k];
      return ['<b>' + esc(c.coso) + '</b>', c.so_luot, tien(c.qr), tien(c.tien_mat), '<b>' + tien(c.tong) + '</b>']; }));
  h += bang(L('Theo ghế','By chair'),
    [L('Ghế','Chair'),L('Cơ sở','Branch'),L('Lượt','Sessions'),'QR',L('Tiền mặt','Cash'),L('Tổng','Total')],
    Object.keys(t.theo_may).map(function(k){ var m = t.theo_may[k];
      return ['<b>' + esc(m.may) + '</b>', esc(m.coso), m.so_luot, tien(m.qr), tien(m.tien_mat), '<b>' + tien(m.tong) + '</b>']; }));
  h += '</div>';

  // --- giao dịch
  h += bang(L('Giao dịch gần đây','Recent transactions'),
    [L('Thời gian','Time'),L('Ghế','Chair'),L('Nguồn','Source'),L('Nội dung','Memo'),L('Số tiền','Amount')],
    D.gd.map(function(g){
      return [esc(g.luc), esc(g.may || '—'),
        '<span class="pill ' + (g.nguon === 'cash' ? 'p-ok' : 'p-wait') + '">'
          + (g.nguon === 'cash' ? L('Tiền mặt','Cash') : String(g.nguon).toUpperCase()) + '</span>',
        '<span class="mut">' + esc(g.noi_dung) + '</span>', tien(g.so_tien)]; }));

  h += '</div>';
  app.innerHTML = h;
  noi();
}

/* Tình trạng ghế: lọc theo mã ghế / cơ sở, phân trang 20 — vẽ tại chỗ vào #tt-wrap, giữ được
   từ khoá qua các lượt làm mới (TT_Q ở phạm vi module; auto-refresh hoãn khi đang gõ). */
function ttLoc(){
  var q = TT_Q.trim().toLowerCase(); var ds = (D && D.may) || [];
  if (!q) return ds;
  return ds.filter(function(m){ return ((m.ma||'') + ' ' + (m.coso||'')).toLowerCase().indexOf(q) >= 0; });
}
function ttRender(){
  var box = document.getElementById('tt-wrap'); if (!box) return;
  var ds = ttLoc(); var pages = Math.max(1, Math.ceil(ds.length / TT_PER));
  if (TT_PG >= pages) TT_PG = pages - 1; if (TT_PG < 0) TT_PG = 0;
  var from = TT_PG * TT_PER, to = Math.min(ds.length, from + TT_PER);
  var h = '<table><tr><th>' + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch')
    + '</th><th>' + L('Trạng thái','State') + '</th><th class="r">' + L('Còn lại','Remaining') + '</th></tr>';
  if (!ds.length) h += '<tr><td colspan="4" class="mut">' + L('Không có ghế khớp.','No chairs match.') + '</td></tr>';
  for (var i = from; i < to; i++){ var m = ds[i]; var p = trangThai(m);
    h += '<tr><td><b>' + esc(m.ma) + '</b></td><td class="hide-sm">' + esc(m.coso || L('(chưa gán)','(unassigned)'))
      + '</td><td><span class="pill ' + p[0] + '">' + p[1] + '</span></td><td class="r">'
      + ((m.tt === 'running' && m.song) ? mmss(m.con_lai) : '') + '</td></tr>';
  }
  h += '</table>';
  box.innerHTML = h;
  var pg = document.createElement('div'); pg.className = 'act'; pg.style.cssText = 'margin-top:8px;align-items:center';
  var bT = document.createElement('button'); bT.className = 'ghost'; bT.textContent = '‹ ' + L('Trước','Prev');
  bT.style.padding = '4px 10px'; bT.disabled = TT_PG <= 0; bT.onclick = function(){ TT_PG--; ttRender(); };
  var bS = document.createElement('button'); bS.className = 'ghost'; bS.textContent = L('Sau','Next') + ' ›';
  bS.style.padding = '4px 10px'; bS.disabled = TT_PG >= pages - 1; bS.onclick = function(){ TT_PG++; ttRender(); };
  var sp = document.createElement('span'); sp.className = 'mut';
  sp.textContent = L('Trang','Page') + ' ' + (TT_PG + 1) + '/' + pages + ' · ' + ds.length + ' ' + L('ghế','chairs');
  pg.appendChild(bT); pg.appendChild(sp); pg.appendChild(bS); box.appendChild(pg);
}
function ttWire(){
  var q = document.getElementById('tt-q');
  if (q) { q.oninput = function(){ TT_Q = q.value; TT_PG = 0; ttRender(); }; }
  ttRender();
}

/* Trạng thái một ghế -> [lớp CSS, chữ]. MỘT chỗ duy nhất: hai tab cùng hiện trạng thái này,
   khai hai nơi là sớm muộn một tab nói "Rảnh" còn tab kia nói "Đang chạy". */
function trangThai(m){
  if (!m.song)              return ['p-off',L('Mất kết nối','Offline')];
  if (m.tt === 'running')   return ['p-run',L('Đang chạy','Running')];
  if (m.tt === 'wait_pay')  return ['p-wait',L('Chờ trả tiền','Awaiting payment')];
  return ['p-ok',L('Rảnh','Idle')];
}

/* ============================================================================================
 * TAB LỊCH SỬ TẮT MỞ MÁY — ghế THẬT SỰ chạy/dừng, đo từ chân báo-chạy của bo ghế.
 *
 * Anh Thắng 27/08/2026: *"Nhật ký bật tắt máy, bật máy thì bộ QR gửi về, tắt thì từ lúc mất tín
 * hiệu QR. Trên wed thêm 1 tab Lịch sử tắt mở máy"*.
 *
 * 🔴 KHÁC TAB KÍCH HOẠT. Kích hoạt = ai đó BẤM cho chạy (ý định người). Tab này = ghế có chạy
 *    THẬT không, bất kể vì QR, tiền mặt hay bấm tay — để đối chiếu "web bảo bật mà ghế chạy chưa".
 *
 * Dữ liệu tải RIÊNG (api `nhat_ky_may`), không nằm trong `so_lieu`: nó chỉ cần khi mở tab này,
 * còn `so_lieu` chạy mỗi lần tải trang. Làm mới ~mỗi 20 giây (theo NHIP_MS) hoặc khi đổi kỳ. */
var NK = null, NK_LUC = 0, NK_DANG = false;

function chayLau(giay){
  var g = Math.max(0, Number(giay) || 0);
  if (g <= 0) return '';
  var ph = Math.floor(g / 60), gy = g % 60;
  if (ph > 0) return ph + L(' phút',' min') + (gy ? ' ' + gy + L(' giây',' s') : '');
  return gy + L(' giây',' s');
}

function veNhatKyMay(){
  /* Tải khi: chưa có, đổi kỳ, hoặc dữ liệu cũ hơn ~18 giây. Có dữ liệu cũ thì VẪN vẽ nó trong
     lúc tải bản mới — đừng nháy về "Đang tải…" mỗi lượt làm mới. */
  var moi = !NK || NK.ky !== KY || (Date.now() - NK_LUC > 18000);
  if (moi && !NK_DANG) {
    NK_DANG = true;
    goi('nhat_ky_may', { ky: KY }, function(r){
      NK_DANG = false;
      if (!r || !r.ok) { if (!NK) { NK = { ky: KY, ds: [], gom: [] }; NK_LUC = Date.now(); ve(); } return; }
      NK = r; NK_LUC = Date.now(); ve();
    });
    if (!NK) return '<div class="card"><p class="mut">' + L('Đang tải…','Loading…') + '</p></div>';
  }

  var gom = NK.gom || [], ds = NK.ds || [];
  var h = '';

  /* ---- 1. TỔNG THEO GHẾ ------------------------------------------------------------------- */
  h += '<div class="card"><h2>' + L('Tổng theo ghế','By chair') + '</h2>'
    + '<p class="mut" style="margin:0 0 8px">'
    + L('Số lần ghế chạy và tổng thời gian chạy thật trong kỳ. Đo từ chân báo-chạy của bo ghế.',
        'How many times each chair ran and total run time in the period, from the chair board\'s run pin.')
    + '</p><table><tr><th>' + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch')
    + '</th><th class="r">' + L('Số lần chạy','Runs') + '</th><th class="r">'
    + L('Tổng chạy','Total run') + '</th><th class="r hide-sm">' + L('Lần cuối','Last') + '</th></tr>';
  if (!gom.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa có ghi nhận nào trong kỳ này.','No records in this period yet.') + '</td></tr>';
  gom.forEach(function(m){
    h += '<tr><td><b>' + esc(m.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(m.coso || L('(chưa gán)','(unassigned)')) + '</td>'
      + '<td class="r">' + (Number(m.so_lan)||0) + '</td>'
      + '<td class="r">' + (Number(m.tong_phut)||0) + L(' phút',' min') + '</td>'
      + '<td class="r hide-sm mut">' + esc(m.lan_cuoi || '') + '</td></tr>';
  });
  h += '</table></div>';

  /* ---- 2. DÒNG THỜI GIAN ------------------------------------------------------------------ */
  h += '<div class="card"><h2>' + L('Dòng thời gian bật/tắt','On/off timeline') + '</h2>'
    + '<table><tr><th>' + L('Lúc','Time') + '</th><th>' + L('Ghế','Chair')
    + '</th><th class="hide-sm">' + L('Cơ sở','Branch') + '</th><th>' + L('Sự kiện','Event')
    + '</th><th class="r">' + L('Chạy được','Ran for') + '</th></tr>';
  if (!ds.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa có sự kiện bật/tắt nào.','No on/off events yet.') + '</td></tr>';
  ds.forEach(function(e){
    var bat = (e.su_kien === 'bat');
    h += '<tr><td class="mut">' + esc(e.luc) + '</td>'
      + '<td><b>' + esc(e.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(e.coso || '') + '</td>'
      + '<td><span class="pill ' + (bat ? 'p-run' : 'p-off') + '">'
        + (bat ? '▶ ' + L('BẬT','ON') : '⏹ ' + L('TẮT','OFF')) + '</span></td>'
      + '<td class="r">' + (bat ? '' : esc(chayLau(e.giay))) + '</td></tr>';
  });
  h += '</table></div>';

  return h;
}

/* TAB ĐIỀU KHIỂN — mỗi ghế một thẻ, không phải một hàng bảng.
   Người bấm đang đứng cạnh một con ghế cụ thể và cần thấy đúng nó, to và rõ; dò theo hàng
   trong bảng là nguồn của việc bấm nhầm sang ghế bên cạnh. */
function veDieuKhien(){
  if (!D.may.length) {
    return '<div class="card"><h2>' + L('Điều khiển ghế','Chair control') + '</h2><p class="mut">'
      + L('Chưa khai ghế nào. Cắm ghế lên là nó tự hiện ở khối <b>Ghế vừa nối mạng</b> trong tab Đối soát.',
          'No chairs registered yet. Power one on and it appears by itself under '
          + '<b>Chairs just came online</b> in the Reconciliation tab.') + '</p></div>';
  }
  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * TIÊU VÍ HỘ KHÁCH — ĐỨNG TRƯỚC lưới ghế.
   *
   * Anh Thắng 23/08/2026: *"khách không biết bấm nhiều lần, dẫn đến khóa 10p. Vậy nhân viên có
   * thể vào điều khiển ghế, nhập số điện thoại khách, hiện số dư và kích ghế giúp luôn"*.
   *
   * 🔴 KHÁC HẲN NÚT "BẬT" bên dưới: nút Bật là CHO KHÔNG một lượt, còn cái này TRỪ TIỀN của
   *    khách. Hai việc nhìn giống nhau mà hậu quả ngược nhau, nên để hai khối riêng và nói rõ.
   *
   * ⚠️ Không hỏi PIN của khách — người bấm đã qua cổng PIN nhân viên. Đổi lại, MỌI lượt bấm ở
   *    đây đều ghi tên người bấm vào sổ ví; xem VHG_Vi::tieu_nhan_vien().
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var h = '<div class="card"><h2>' + L('Tiêu ví hộ khách','Spend a customer wallet') + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Khách gõ nhầm PIN nhiều lần bị khoá 10 phút, hoặc không biết dùng — nhập số điện thoại '
        + 'của họ, chọn ghế và gói, hệ thống trừ ví và chạy ghế luôn. <b>Không cần PIN của khách</b>, '
        + 'nhưng mọi lượt bấm đều ghi tên anh/chị vào sổ ví.',
        'If a customer is locked out or unsure how to use it — enter their phone number, pick a '
        + 'chair and a package; the wallet is charged and the chair starts. <b>No customer PIN '
        + 'needed</b>, but every press is logged against your name.')
    + '</p>'
    + '<div class="act"><input id="nv-sdt" placeholder="0909 123 456" style="max-width:200px">'
    + '<button id="nv-tra" class="on">' + L('Xem số dư','Show balance') + '</button></div>'
    + '<div class="err" id="nv-e"></div><div id="nv-kq"></div></div>';

  /* Lọc theo cơ sở — gom từ chính D.may (không phụ thuộc D.coso, để cả role Giúp khách dùng được). */
  var dkLocs = {};
  D.may.forEach(function(m){ var k = m.coso || ''; dkLocs[k] = (dkLocs[k]||0) + 1; });
  var dkList = Object.keys(dkLocs).filter(function(k){ return k; }).sort();
  var dkChuaGan = dkLocs[''] || 0;
  var dsMay = D.may.filter(function(m){
    if (DK_LOC === '') return true;
    if (DK_LOC === '__none__') return !m.coso;
    return m.coso === DK_LOC;
  });
  var dkFilter = '';
  if (dkList.length + (dkChuaGan ? 1 : 0) > 1) {   // chỉ hiện lọc khi có >1 cơ sở
    dkFilter = '<div class="act" style="margin:0 0 12px"><label class="mut" style="align-self:center">'
      + L('Lọc cơ sở','Filter site') + ':</label><select id="dk-loc" style="flex:1;max-width:260px">'
      + '<option value="">' + L('Tất cả','All') + ' (' + D.may.length + ')</option>'
      + dkList.map(function(k){
          return '<option value="' + esc(k) + '"' + (DK_LOC === k ? ' selected' : '') + '>'
            + esc(k) + ' (' + dkLocs[k] + ')</option>'; }).join('')
      + (dkChuaGan ? '<option value="__none__"' + (DK_LOC === '__none__' ? ' selected' : '') + '>'
          + L('(chưa gán)','(unassigned)') + ' (' + dkChuaGan + ')</option>' : '')
      + '</select></div>';
  }

  h += '<div class="card"><h2>' + L('Quản lý ghế · Điều khiển','Chair management · Control')
    + ' — ' + dsMay.length + '/' + D.may.length + ' ' + L('ghế','chairs') + '</h2>'
    + '<p class="mut" style="margin:0 0 12px">'
    + L('Bật tay là <b>cho không một lượt</b> — hệ thống ghi lại ai bấm và lúc nào, để cuối tháng '
        + 'còn giải thích được vì sao một ghế chạy nhiều hơn số tiền thu.',
        'Turning a chair on by hand is <b>a free session</b> — the system records who pressed it and '
        + 'when, so at month end you can still explain why a chair ran more than it took in.')
    + '</p>' + dkFilter + '<div class="ghe-luoi" id="dk-grid">' + dkLuoiHtml() + '</div></div>';
  return h;
}

/* CHỈ phần lưới ghế — tách riêng để lượt làm mới 2 giây CHỈ vẽ lại lưới, giữ nguyên ô đang gõ
   (Số phút/Tiền mặt) và form "tiêu ví hộ khách" bên trên. Xem capNhatDieuKhien(). */
function dkLuoiHtml(){
  var dsMay = D.may.filter(function(m){
    if (DK_LOC === '') return true;
    if (DK_LOC === '__none__') return !m.coso;
    return m.coso === DK_LOC;
  });
  var h = '';
  dsMay.forEach(function(m){
    var p = trangThai(m);
    var lop = !m.song ? ' dut' : (m.tt === 'running' ? ' chay' : '');
    h += '<div class="ghe' + lop + '">'
      + '<div class="ghe-dau"><span class="ghe-ma">' + esc(m.ma) + '</span>'
      + '<span class="pill ' + p[0] + '">' + p[1] + '</span></div>'
      + '<div class="ghe-cs">' + esc(m.coso || L('(chưa gán cơ sở)','(no branch)')) + '</div>';

    /* 🔴 CỤC NHẬN TIỀN HỎNG — nằm ngay trên thẻ ghế, không giấu trong wp-admin. Người phải chạy
       ra xem cái máy là người đứng quầy, mà họ không có tài khoản WordPress. Nói luôn PHẢI LÀM
       GÌ chứ không phải một cái mã lỗi: "lỗi ket" thì ai cũng chịu. */
    if (m.tm) {
      h += '<div class="ghe-tien-loi dang">⚠ '
        + L('Cục nhận tiền đang hỏng','Bill acceptor is faulty right now')
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    } else if (m.tm_cu) {
      h += '<div class="ghe-tien-loi cu">'
        + L('Cục nhận tiền đã hỏng lúc trước','Bill acceptor failed earlier')
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    }

    /* Số đếm ngược to: đó là thứ người đứng cạnh ghế nhìn để biết còn bao lâu. */
    if (m.tt === 'running' && m.song) {
      h += '<div class="ghe-dh" data-dh="' + esc(m.ma) + '">' + mmss(m.con_lai)
        + '</div><div class="mut">' + L('còn lại','remaining') + '</div>';
    } else if (!m.song) {
      h += '<div class="mut" style="margin:8px 0">'
        + L('Ghế không gửi nhịp. Khách vẫn quét được tem QR trên ghế, <b>tiền vẫn vào nhưng ghế '
            + 'không chạy</b>.',
            'The chair is not sending a heartbeat. Customers can still scan the QR sticker on it, '
            + '<b>the money still arrives but the chair will not run</b>.') + '</div>';
    } else if (m.cho > 0) {
      h += '<div class="mut" style="margin:8px 0">' + m.cho + ' '
        + L('lượt đã trả tiền đang chờ ghế nhận.','paid sessions waiting for the chair to pick up.')
        + '</div>';
    } else {
      h += '<div class="mut" style="margin:8px 0">' + L('Sẵn sàng','Ready') + ' · ' + tien(m.gia)
        + ' = ' + m.phut + ' ' + L('phút','min') + '</div>';
    }

    /* ══════════════════════════════════════════════════════════════════════════════════════
     * CHỈ SỐ MÁY ĐẾM — CŨ VÀ MỚI, NGAY TRÊN THẺ GHẾ.
     *
     * Anh Thắng 23/08/2026: *"Hiện chỉ số máy — cũ và mới"*.
     *
     * 🔴 ĐỨNG CẠNH GHẾ MÀ SO ĐƯỢC NGAY. Người đi thu nhìn màn máy đếm trên ghế, rồi nhìn thẻ
     *    này: hệ thống ghi lần trước là bao nhiêu. Hiệu hai con số CHÍNH LÀ số tiền đang nằm
     *    trong ngăn — biết trước khi mở ngăn thì đếm xong là biết đủ hay thiếu ngay, không phải
     *    đợi tới lúc bấm chốt mới thấy con số đỏ.
     *
     * ⚠️ "Cũ" là chỉ số của lần chốt TRƯỚC lần gần nhất, "mới" là lần gần nhất — đúng cặp mà
     *    lần chốt gần nhất đã dùng để trừ ra tiền. Hiện một con số trần thì không ai biết nó là
     *    mốc hay là số đã trừ rồi.
     * ═════════════════════════════════════════════════════════════════════════════════════ */
    if (m.chot) {
      var c0 = m.chot;
      h += '<div class="cs-hop">'
        + '<div class="cs-nh">' + L('Chỉ số máy đếm','Note counter') + '</div>'
        + '<div class="cs-so">'
        + '<span class="cu">' + Number(c0.chi_so_truoc).toLocaleString('vi-VN') + '</span>'
        + '<span class="mui">→</span>'
        + '<span class="moi">' + Number(c0.chi_so).toLocaleString('vi-VN') + '</span>'
        + '</div>'
        + '<div class="cs-p">'
        + (c0.lan_dau
            ? L('lần chốt đầu tiên — chưa có mốc để trừ','first closing — no baseline yet')
            : Lf('lần chốt gần nhất: {0} · {1} · {2}', 'last closing: {0} · {1} · {2}',
                 tien(c0.tien_dem), esc(String(c0.tao_luc).slice(5, 16)), esc(c0.nguoi)))
        + '</div></div>';
    } else {
      h += '<div class="cs-hop chua"><div class="cs-nh">' + L('Chỉ số máy đếm','Note counter')
        + '</div><div class="cs-p">'
        + L('ghế này <b>chưa chốt lần nào</b> — lần chốt đầu chỉ đặt mốc',
            'never closed yet — the first closing only sets the baseline')
        + '</div></div>';
    }

    h += '<div class="ghe-hang"><label>' + L('Số phút','Minutes') + '</label>'
      + '<input type="number" min="1" max="60" value="' + m.phut + '" data-phut="' + esc(m.ma) + '">'
      + '<label>' + L('Tiền mặt','Cash') + '</label>'
      + '<input type="number" min="1000" step="1000" value="' + m.gia + '" data-tien="' + esc(m.ma) + '">'
      + '</div>';

    h += '<div class="ghe-nut">'
      + '<button class="b-bat" data-bat="' + esc(m.ma) + '">▶ ' + L('Bật','Start') + '</button>'
      + '<button class="b-tat" data-tat="' + esc(m.ma) + '">■ ' + L('Tắt','Stop') + '</button>'
      /* Tên nút phải nói đúng việc: nó KHÔNG ghi doanh thu nữa, nó chốt ngăn tiền. Giữ tên cũ
         là người bấm vẫn tưởng mình đang ghi một lượt bán hàng. */
      + '<button data-mat="' + esc(m.ma) + '">🧾 ' + L('Chốt ca / thu ngăn','Close shift') + '</button>'
      + '<button class="b-kd" data-kd="' + esc(m.ma) + '">⟳ ' + L('Khởi động lại','Reboot') + '</button>'
      + '<button' + ((m.khoa || m.tm === 'ghekhongchay' || m.tm === 'ghedungdotngot') ? ' class="on"' : '')
          + ' data-mokhoa="' + esc(m.ma) + '">🔓 ' + L('Mở khoá lỗi','Unlock') + '</button>'
      + testNut(m)
      + '</div></div>';
  });
  return h;
}

/* ============================================================================================
 * TAB GHẾ LỖI — cho HOTLINE theo dõi ghế nào đang lỗi gì, và MỞ KHOÁ từ xa.
 *
 * Anh Thắng 27/08/2026: *"Bổ sung Tab: Ghế cảnh báo Lỗi — ghế nào lỗi gì đẩy vào đó để Hotline
 * biết"*, và *"ghế lỗi -> màn hiện hotline + KHÓA; hotline mở lại từ xa mới cho chạy QR"*.
 *
 * Gom TẤT CẢ ghế bất thường: đang có mã lỗi (m.tm) HOẶC mất kết nối (!m.song).
 * Ghế bị KHÓA (firmware treo mã 'ghekhongchay'/'ghedungdotngot' bền) -> có nút "Mở khoá từ xa"
 * gửi lệnh 'mo_khoa'. Ghế kẹt tiền / mất kết nối -> nút Khởi động lại / Tắt.
 * ============================================================================================ */
/* Nút bật/tắt CHẾ ĐỘ KỸ THUẬT (test) cho 1 ghế. Ghế trong chế độ này KHÔNG khoá lỗi:
   dừng/lỗi thì báo ngay 5s rồi tự hết — để kỹ thuật test bật/tắt liên tục. Tự tắt sau 15 phút. */
function testNut(m){
  return m.kt
    ? '<button class="on" data-testoff="' + esc(m.ma) + '">🔧 '
        + L('Đang kiểm tra — Tắt','Testing — turn off') + '</button>'
    : '<button data-teston="' + esc(m.ma) + '">🔧 '
        + L('Ghế kỹ thuật (kiểm tra)','Technician test') + '</button>';
}

function veGheLoi(){
  var KHOA_MA = { 'ghekhongchay': 1, 'ghedungdotngot': 1 };   // mã tương ứng ghế bị KHÓA (dự phòng)
  var ds = (D.may || []).filter(function(m){ return m.khoa || m.tm || !m.song; });
  /* Sắp: ghế đang lỗi (còn kết nối) trước, rồi mất kết nối. */
  ds.sort(function(a,b){
    var la = (a.tm ? 0 : 1) + (a.song ? 0 : 2), lb = (b.tm ? 0 : 1) + (b.song ? 0 : 2);
    if (la !== lb) return la - lb;
    return (a.coso||'').localeCompare(b.coso||'') || (a.ma||'').localeCompare(b.ma||'');
  });
  var soKhoa = ds.filter(function(m){ return m.khoa || (m.tm && KHOA_MA[m.tm]); }).length;
  var soDut  = ds.filter(function(m){ return !m.song; }).length;

  var h = '<div class="card"><h2>🚨 ' + L('Ghế lỗi','Faulty chairs')
    + ' — ' + ds.length + ' ' + L('ghế','chairs') + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Ghế bị khoá lỗi tự chặn khách và hiện hotline trên màn. Hotline kiểm rồi bấm '
        + '<b>Mở khoá từ xa</b> để cho ghế nhận khách lại.',
        'A locked chair blocks customers and shows the hotline on its screen. After checking, '
        + 'the hotline presses <b>Unlock remotely</b> to let it serve customers again.')
    + '</p>'
    + '<div class="act" style="gap:16px;flex-wrap:wrap">'
    + '<span>🔒 ' + L('Khoá lỗi','Locked') + ': <b>' + soKhoa + '</b></span>'
    + '<span>📴 ' + L('Mất kết nối','Offline') + ': <b>' + soDut + '</b></span>'
    + '</div></div>';

  if (!ds.length) {
    return h + '<div class="card"><p class="mut" style="text-align:center;padding:20px 0">✅ '
      + L('Không có ghế nào đang lỗi.','No chairs are currently faulty.') + '</p></div>';
  }

  h += '<div class="card"><div class="ghe-luoi">';
  ds.forEach(function(m){
    var khoa = m.khoa || (m.tm && KHOA_MA[m.tm]);
    var lop = !m.song ? ' dut' : '';
    h += '<div class="ghe' + lop + '">'
      + '<div class="ghe-dau"><span class="ghe-ma">' + esc(m.ma) + '</span>'
      + '<span class="pill ' + (khoa ? 'p-off' : (!m.song ? 'p-off' : 'p-run')) + '">'
      + (khoa ? '🔒 ' + L('KHOÁ LỖI','LOCKED') : (!m.song ? '📴 ' + L('Mất kết nối','Offline')
                 : '⚠ ' + L('Lỗi','Error'))) + '</span></div>'
      + '<div class="ghe-cs">' + esc(m.coso || L('(chưa gán cơ sở)','(no branch)')) + '</div>';

    if (m.tm) {
      h += '<div class="ghe-tien-loi dang">⚠ ' + esc(m.tm)
        + '<div>' + esc(L(m.tm_chu, m.tm_chu_en || m.tm_chu)) + '</div></div>';
    } else if (!m.song) {
      h += '<div class="ghe-tien-loi cu">'
        + L('Ghế không gửi nhịp về máy chủ. Kiểm nguồn / 4G / SIM của ghế.',
            'The chair is not sending a heartbeat. Check its power / 4G / SIM.') + '</div>';
    }

    h += '<div class="ghe-nut">';
    if (khoa) {
      h += '<button class="on" data-mokhoa="' + esc(m.ma) + '">🔓 '
        + L('Mở khoá từ xa','Unlock remotely') + '</button>';
    }
    h += testNut(m)
      + '<button class="b-tat" data-tat="' + esc(m.ma) + '">■ ' + L('Tắt','Stop') + '</button>'
      + '<button class="b-kd" data-kd="' + esc(m.ma) + '">⟳ ' + L('Khởi động lại','Reboot') + '</button>'
      + '</div></div>';
  });
  return h + '</div></div>';
}

/* ============================================================================================
 * TAB THU TIỀN.
 *
 * 🔴 CÓ HAI ĐƯỜNG TIỀN MẶT, VÀ BẢNG DOANH THU KHÔNG PHÂN BIỆT ĐƯỢC CHÚNG:
 *      · "Ghế nuốt"  — khách nhét tờ tiền vào máy, ghế chạy ngay. Tiền còn nằm trong ghế.
 *      · "Người thu" — người đi thu mở ngăn, đếm được bao nhiêu ghi bấy nhiêu.
 *    Ghế có cục nhận tiền chạy tốt MÀ người thu vẫn bấm "Thu tiền mặt" là CỘNG ĐÔI: cùng một
 *    xấp tiền vào sổ hai lần. Doanh thu tháng phồng lên mà không ai thấy, vì hai dòng nhìn
 *    giống hệt nhau trong bảng giao dịch.
 *
 *    Không cấm — có ghế không lắp cục nhận tiền, ở đó nút bấm tay là đường DUY NHẤT. Nên tab này
 *    tách hai loại ra và KÊU LÊN khi một ghế có cả hai trong cùng kỳ.
 * ============================================================================================ */
/* ============================================================================================
 * TAB QUỸ & NỘP TIỀN.
 *
 * Anh Thắng 23/08/2026: *"giờ đến phần thu tiền của nhân viên"*, và *"nhập số tiền mặt, chỉ số
 * máy tiền mặt — trên máy có 1 màn hình đếm tiền mặt nữa, nên nhập vào để trừ chỉ số cho ngày
 * hôm sau"*.
 *
 * 🔴 THỨ TỰ CÁC KHỐI ĐI THEO THỨ TỰ VIỆC LÀM, KHÔNG THEO THỨ TỰ SỐ LIỆU:
 *      1. "Tôi đang cầm bao nhiêu" + nút Nộp        -> việc của người vừa đi thu về.
 *      2. Lượt nộp chờ xác nhận                     -> việc của quản lý, và nó CHẶN người kia.
 *      3. Ai đang cầm bao nhiêu                     -> câu hỏi mỗi sáng.
 *      4. Lượt chốt ca + lệch                       -> chỗ đi tìm khi con số không khớp.
 *      5. Báo cáo theo người                        -> cuối kỳ.
 *
 * ⚠️ Ô LỆCH TÔ ĐỎ CHỈ KHI KHÁC 0. Tô đỏ cả cột là mắt bỏ qua cả cột.
 * ============================================================================================ */
/* Đọc quyền từ gói tin máy chủ vừa gửi. Hàm chứ không phải biến: `D` được thay mới mỗi lượt
   tải lại, và một biến chụp lúc dựng trang sẽ giữ nguyên quyền cũ sau khi đổi tài khoản. */
function QUAN_TRI(){ return !!(D && D.quyen && D.quyen.quan_tri); }
function GIUP_KHACH(){ return !!(D && D.quyen && D.quyen.giup_khach); }
function CHOT_DS(){ return !!(D && D.quyen && D.quyen.chot_doanh_so); }

/* ============================================================================================
 * TAB CẤU HÌNH — QUẢN LÝ NHÂN SỰ NGAY TRÊN TRANG NÀY.
 *
 * Anh Thắng 23/08/2026: *"chưa thấy tab cấu hình trên wed"*, và trước đó *"bổ sung thêm phần
 * cấu hình để quản lý nhân viên"*.
 *
 * 🔴 VÌ SAO KHÔNG ĐỂ NGUYÊN TRONG wp-admin.
 *    Anh Thắng điều hành cả hệ này từ trang /ghe trên điện thoại. Bắt mở wp-admin trên điện
 *    thoại để thêm một người thu là việc sẽ không ai làm — rồi cả nhà dùng chung một tài khoản,
 *    và toàn bộ phân quyền vừa dựng thành số 0. Màn wp-admin VẪN CÒN, hai nơi gọi chung một
 *    hàm (`VHG_Admin::them_nguoi_dung`), không chép luật ra hai bản.
 *
 * ⚠️ KHÔNG BAO GIỜ IN PIN RA MÀN, kể cả cho Admin — chỉ nói dài mấy số. Một ảnh chụp màn hình
 *    gửi nhầm nhóm chat là cả chuỗi mất doanh thu.
 * ============================================================================================ */
var CH = null;   // số liệu cấu hình vừa tải

/* ============================================================================================
 * TAB PIN BÁO CÁO (Admin hoặc Quản trị) — cấp/sửa/xoá bc_pin cho nhân viên vào màn Báo cáo.
 * Gọi bc_pin_* qua `goi()` (kèm token) — endpoint gác token + quyền Quản trị ở máy chủ.
 * ============================================================================================ */
var BCP = null;     // danh sách PIN báo cáo vừa tải
var BCP_NS = [];    // danh sách nhân viên từ nhân sự (để chọn nhanh)
function veBcPin(){
  if (!BCP) {
    goi('bc_pin_ds', {}, function(r){
      if (!r || !r.ok) { alert((r && r.error) || L('Không tải được PIN (cần quyền Quản trị).',
        'Could not load PINs (needs Manage permission).')); BCP = []; ve(); return; }
      BCP = r.ds || []; BCP_NS = r.nhan_su || []; ve();
    });
    return '<div class="card"><p class="mut">' + L('Đang tải…','Loading…') + '</p></div>';
  }
  var h = '<div class="card"><h2>📋 ' + L('PIN nhân viên báo cáo','Report staff PINs') + '</h2>'
    + '<p class="mut">' + L('Dùng CHÍNH PIN nhân sự / chấm công của nhân viên (hai hệ đồng bộ) để vào '
      + 'màn Báo cáo doanh thu — không cần tài khoản /ghe. Tích cơ sở người này phụ trách. Admin/Quản trị sửa. '
      + 'PIN nhân viên đã có sẵn trong hệ nhân sự vẫn đăng nhập được dù chưa khai ở đây; khai ở đây khi '
      + 'muốn đổi phạm vi hoặc khoá riêng.',
      'Use the staff member\'s existing HR / attendance PIN (the two systems sync). Tick the branches '
      + 'they cover. Admin only.') + '</p>'
    + '<table><tr><th>PIN</th><th>' + L('Tên','Name') + '</th><th>' + L('Cơ sở','Branches')
    + '</th><th class="hide-sm">' + L('Ghế riêng','Chairs') + '</th><th>' + L('Bật','On')
    + '</th><th class="r"></th></tr>';
  if (!BCP.length) h += '<tr><td colspan="6" class="mut">' + L('Chưa có PIN nào.','No PINs yet.') + '</td></tr>';
  BCP.forEach(function(p){
    h += '<tr><td><b>' + esc(p.pin) + '</b></td><td>' + esc(p.ten || '') + '</td>'
      + '<td>' + esc(p.coso || L('(cả phạm vi)','(all)')) + '</td>'
      + '<td class="hide-sm mut">' + esc(p.ghe || '') + '</td>'
      + '<td>' + (p.active ? '✓' : '<span class="mut">' + L('tắt','off') + '</span>') + '</td>'
      + '<td class="r"><button data-bcpsua="' + esc(p.pin) + '" class="ghost">' + L('Sửa','Edit') + '</button> '
      + '<button data-bcpxoa="' + esc(p.pin) + '" class="ghost">' + L('Xoá','Del') + '</button></td></tr>';
  });
  var dsCoso = (D && D.coso) || [];
  var nsOpt = '<option value="">' + L('— Chọn nhân viên từ nhân sự —','— Pick staff from HR —') + '</option>'
    + (BCP_NS || []).map(function(u, i){ return '<option value="' + i + '">' + esc(u.ten)
        + (u.vaiTro ? ' · ' + esc(u.vaiTro) : '') + (u.coso ? ' · ' + esc(u.coso) : '') + '</option>'; }).join('');
  h += '</table>'
    + '<h3 style="margin:16px 0 8px">' + L('Thêm / sửa PIN','Add / edit PIN') + '</h3>'
    + '<div class="act" style="flex-wrap:wrap;margin-bottom:8px"><b>' + L('Nhân viên','Staff') + ':</b>'
    + '<select id="bcp-nv" style="flex:2;min-width:220px">' + nsOpt + '</select>'
    + '<span class="mut">' + L('chọn để tự điền PIN + tên + cơ sở','pick to auto-fill') + '</span></div>'
    + '<div class="act" style="flex-wrap:wrap">'
    + '<input id="bcp-pin" type="tel" inputmode="numeric" placeholder="PIN 3–10 ' + L('số','digits')
      + '" style="flex:1;min-width:110px">'
    + '<input id="bcp-ten" type="text" placeholder="' + L('Tên nhân viên','Staff name') + '" style="flex:2;min-width:150px">'
    + '</div>'
    /* Cơ sở dạng TÍCH CHỌN — nhanh, khỏi gõ, khỏi sai chính tả tên cơ sở. */
    + '<div class="ph-nhom" style="margin-top:10px">'
    + '<div class="act" style="margin-bottom:8px;flex-wrap:wrap;align-items:center"><b>' + L('Cơ sở phụ trách','Branches') + ':</b>'
    + '<input id="bcp-cs-q" placeholder="' + L('lọc cơ sở / tỉnh…','filter…') + '" style="max-width:200px">'
    + '<button type="button" id="bcp-cs-all" class="ghost" style="padding:5px 10px;font-size:12px">' + L('Chọn tất','All') + '</button>'
    + '<button type="button" id="bcp-cs-none" class="ghost" style="padding:5px 10px;font-size:12px">' + L('Bỏ tất','None') + '</button>'
    + '<span class="mut" id="bcp-cs-dem"></span></div>'
    + '<div id="bcp-coso-box" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:2px 10px;max-height:260px;overflow:auto;padding:6px;border:1px solid var(--line);border-radius:8px;background:#fff">'
    + (dsCoso.length
        ? dsCoso.map(function(c){ return '<label class="bcp-cs-cell" data-q="' + esc((c.ten + ' ' + (c.tinh || '')).toLowerCase())
            + '" style="display:flex;align-items:flex-start;gap:7px;padding:5px 6px;border-radius:6px;font-size:13px;cursor:pointer;line-height:1.25">'
            + '<input type="checkbox" class="bcp-cs" value="' + esc(c.ten) + '" style="width:auto;margin-top:2px;flex:none">'
            + '<span>' + esc(c.ten) + (c.tinh ? '<br><span class="mut" style="font-size:11px">📍 ' + esc(c.tinh) + '</span>' : '') + '</span></label>'; }).join('')
        : '<span class="mut">' + L('Chưa có cơ sở — thêm ở tab Quản lý ghế trước.','No branches yet — add them in Chairs & sites first.') + '</span>')
    + '</div></div>'
    + '<div class="act" style="flex-wrap:wrap;margin-top:10px">'
    + '<input id="bcp-ghe" type="text" placeholder="' + L('Ghế riêng (tuỳ chọn)','Specific chairs (optional)')
      + '" style="flex:2;min-width:150px">'
    + '<label class="ph-tick"><input type="checkbox" id="bcp-active" checked> ' + L('Bật','Active') + '</label>'
    + '<button id="bcp-luu" class="on">' + L('Lưu','Save') + '</button>'
    + '<button id="bcp-moi" class="ghost">' + L('Nhập mới','New') + '</button></div>'
    + '<p class="mut" style="margin-top:8px">' + L('Không tích cơ sở nào = nhận TOÀN BỘ phạm vi (dùng cho quản '
      + 'lý). Để trống "Ghế riêng" = toàn bộ ghế của các cơ sở đã tích; chỉ điền khi người này chỉ thu vài ghế.',
      'No branch ticked = full scope (for managers). Empty "Chairs" = all chairs of the ticked branches.') + '</p>'
    + '<div class="err" id="bcp-e"></div></div>';
  return h;
}

function bcpCosoTicked(){
  var cs = [];
  [].forEach.call(document.querySelectorAll('.bcp-cs:checked'), function(c){ cs.push(c.value); });
  return cs.join('; ');
}
function bcpDem(){
  var d = document.getElementById('bcp-cs-dem'); if (!d) return;
  var n = document.querySelectorAll('.bcp-cs:checked').length;
  var t = document.querySelectorAll('.bcp-cs').length;
  d.textContent = n ? (n + '/' + t + ' ' + L('cơ sở','sites')) : L('chưa tích = toàn phạm vi','none = full scope');
}
function bcpTickCoso(coso){
  var cur = String(coso || '').split(/[;,]/).map(function(s){ return s.trim().toLowerCase(); }).filter(Boolean);
  [].forEach.call(document.querySelectorAll('.bcp-cs'), function(c){
    c.checked = cur.indexOf(String(c.value).trim().toLowerCase()) >= 0;
  });
  bcpDem();
}
function noiBcPin(){
  var e = document.getElementById('bcp-e');
  var luu = document.getElementById('bcp-luu');
  var moi = document.getElementById('bcp-moi');
  var nv = document.getElementById('bcp-nv');
  if (nv) nv.onchange = function(){
    var u = (BCP_NS || [])[Number(nv.value)];
    if (!u) return;
    if (u.pin) document.getElementById('bcp-pin').value = u.pin;
    document.getElementById('bcp-ten').value = u.ten || '';
    bcpTickCoso(u.coso || '');   // cơ sở trống = không tích ô nào (toàn phạm vi)
    if (e) e.textContent = u.pin ? '' : L('Người này chưa có PIN trong nhân sự — nhập PIN tay.','No HR PIN — enter PIN manually.');
  };
  // Lọc + chọn tất/bỏ tất + đếm cho lưới cơ sở
  var csQ = document.getElementById('bcp-cs-q');
  if (csQ) csQ.oninput = function(){ var q = csQ.value.trim().toLowerCase();
    [].forEach.call(document.querySelectorAll('.bcp-cs-cell'), function(c){
      c.style.display = (!q || (c.getAttribute('data-q') || '').indexOf(q) >= 0) ? 'flex' : 'none'; }); };
  var csAll = document.getElementById('bcp-cs-all'), csNone = document.getElementById('bcp-cs-none');
  function csDatVisible(on){ [].forEach.call(document.querySelectorAll('.bcp-cs-cell'), function(c){
    if (c.style.display !== 'none') { var i = c.querySelector('.bcp-cs'); if (i) i.checked = on; } }); bcpDem(); }
  if (csAll) csAll.onclick = function(){ csDatVisible(true); };
  if (csNone) csNone.onclick = function(){ csDatVisible(false); };
  var csBox = document.getElementById('bcp-coso-box');
  if (csBox) csBox.addEventListener('change', bcpDem);
  bcpDem();
  if (moi) moi.onclick = function(){
    document.getElementById('bcp-pin').value = '';
    document.getElementById('bcp-ten').value = '';
    document.getElementById('bcp-ghe').value = '';
    document.getElementById('bcp-active').checked = true;
    if (nv) nv.value = '';
    bcpTickCoso('');
    if (e) e.textContent = '';
  };
  if (luu) luu.onclick = function(){
    var d = {
      pin:  (document.getElementById('bcp-pin').value || '').trim(),
      ten:  (document.getElementById('bcp-ten').value || '').trim(),
      coso: bcpCosoTicked(),
      ghe:  (document.getElementById('bcp-ghe').value || '').trim(),
      active: document.getElementById('bcp-active').checked ? 1 : 0
    };
    if (!/^[0-9]{3,10}$/.test(d.pin)) { e.textContent = L('PIN phải 3–10 chữ số.','PIN must be 3–10 digits.'); return; }
    if (!d.ten) { e.textContent = L('Thiếu tên nhân viên.','Missing staff name.'); return; }
    if (ban) return; ban = true;
    [].forEach.call(document.querySelectorAll('button'), function(b){ b.disabled = true; });
    goi('bc_pin_luu', d, function(r){
      ban = false;
      if (!r || r.ok === false) {
        alert((r && r.error) || L('Lưu không thành công.','Could not save.'));
        [].forEach.call(document.querySelectorAll('button'), function(b){ b.disabled = false; });
        return;
      }
      BCP = null; ve();
    });
  };
  [].forEach.call(document.querySelectorAll('[data-bcpxoa]'), function(b){
    b.onclick = function(){
      var pin = b.getAttribute('data-bcpxoa');
      if (!confirm(L('Xoá PIN ' + pin + '? Dữ liệu báo cáo cũ không đổi.',
        'Delete PIN ' + pin + '? Existing reports are unchanged.'))) return;
      if (ban) return; ban = true;
      goi('bc_pin_xoa', { pin_xoa: pin }, function(){ ban = false; BCP = null; ve(); });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-bcpsua]'), function(b){
    b.onclick = function(){
      var pin = b.getAttribute('data-bcpsua'), p = null;
      (BCP || []).forEach(function(x){ if (x.pin === pin) p = x; });
      if (!p) return;
      document.getElementById('bcp-pin').value = p.pin;
      document.getElementById('bcp-ten').value = p.ten || '';
      bcpTickCoso(p.coso || '');
      document.getElementById('bcp-ghe').value = p.ghe || '';
      document.getElementById('bcp-active').checked = !!p.active;
      document.getElementById('bcp-pin').focus();
    };
  });
}

/* ============================================================================================
 * TAB KẾ TOÁN — DUYỆT BÁO CÁO. Vai trò Chốt/Quản lý/Admin (có token). Gọi kt_* qua goi().
 * Danh sách theo tháng → bung chi tiết ghế → sửa / duyệt / khoá / xoá / đổi ngày.
 * ============================================================================================ */
var KTD_THANG = '';
var KTD_TRANG = 1;
var KTD_TRANG_CO = 10;   // anh Thắng: "Chỉ hiện 10 cơ sở 1 trang" — để nguyên cả tháng là lag.
var KTD_COSO = '';       // anh Thắng: "Chỗ lọc duyệt báo cáo, cho lọc theo cơ sở". '' = tất cả.
var KTU_TRANG = 1;       // anh Thắng: "Nhật ký cũng đẻ gọn 10 thông báo 1 trang".
function ktVnd(n){ return (Number(n)||0).toLocaleString('vi-VN'); }
/* Ảnh NHẬP DOANH THU CŨ giữ nguyên link Google Drive dán tay từ sheet cũ (xem
   VHCC_Ketoan::dong_moi_/dien_o_ nhận thẳng r0.images, không tải lại lên WP như luu_anh_()).
   Link "…/file/d/<id>/view" là trang xem của Drive, KHÔNG PHẢI ảnh — nhét thẳng vào <img> ra
   khung vỡ. Đổi sang cổng thumbnail chính chủ của Drive để hiện được (cần file chia sẻ "Bất kỳ
   ai có link"); <a href> vẫn giữ link gốc để bấm mở đúng trang xem Drive như cũ. */
function ktAnhSrc(u){
  u = String(u||'');
  var m = /drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?(?:export=[a-z]+&)?id=)([a-zA-Z0-9_-]+)/.exec(u);
  return m ? ('https://drive.google.com/thumbnail?id='+m[1]+'&sz=w200') : u;
}
function ktEl(t,c,tx){ var e=document.createElement(t); if(c)e.className=c; if(tx!=null)e.textContent=tx; return e; }
function veKtDuyet(){
  return '<div class="card"><h2>📈 ' + L('Duyệt báo cáo doanh thu','Review revenue reports') + '</h2>'
    + '<div class="act" style="flex-wrap:wrap"><input type="month" id="ktd-thang" style="max-width:170px">'
    + '<select id="ktd-coso" style="max-width:220px"><option value="">— '
      + L('Tất cả cơ sở','All branches') + ' —</option>'
      + (D.coso||[]).slice().sort(function(a,b){ return a.ten.localeCompare(b.ten); })
        .map(function(c){ return '<option value="'+esc(c.ten)+'">'+esc(c.ten)+'</option>'; }).join('')
      + '</select>'
    + '<button id="ktd-xem" class="on">' + L('Xem','Load') + '</button>'
    + '<span id="ktd-msg" class="mut"></span></div>'
    + '<div id="ktd-list" style="margin-top:12px"></div></div>'
    + '<div class="card"><h2>🗑 ' + L('Thùng rác','Trash') + '</h2>'
    + '<p class="mut">' + L('Ghế đã xoá — hoàn tác được.','Deleted chairs — restorable.') + '</p><div id="ktd-rac"></div></div>'
    + '<div class="card"><h2>↩ ' + L('Nhật ký hoàn tác','Undo log') + '</h2><div id="ktd-undo"></div></div>';
}
function ktdInit(){
  var iT=document.getElementById('ktd-thang');
  if(!KTD_THANG) KTD_THANG=(D&&D.luc?String(D.luc).slice(0,7):'');
  if(iT&&KTD_THANG) iT.value=KTD_THANG;
  var iCs=document.getElementById('ktd-coso');
  if(iCs) iCs.value=KTD_COSO;
  /* Lọc cơ sở đổi là xem ngay, khỏi bấm thêm Xem — người dùng đang xem đúng tháng rồi, chỉ
     muốn thu hẹp theo cơ sở. */
  if(iCs) iCs.onchange=function(){ KTD_COSO=iCs.value; KTD_TRANG=1; ktdLoad(); };
  document.getElementById('ktd-xem').onclick=function(){ KTD_THANG=iT.value; KTD_TRANG=1; ktdLoad(); };
  ktdLoad(); ktdRac(); ktdUndo();
}
function ktdRac(){
  var box=document.getElementById('ktd-rac'); if(!box) return; box.textContent='';
  goi('kt_rac_ds',{},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Thùng rác trống.','Empty.'))); return; }
    r.rows.forEach(function(x){
      var it=ktEl('div','act'); it.style.cssText='flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.08);padding:6px 0';
      var t=ktEl('div'); t.style.flex='1';
      t.appendChild(ktEl('b',null,(x.chairCode)+' · '+x.coso+' · '+x.ngay));
      t.appendChild(ktEl('div','mut',L('Lý do','Reason')+': '+(x.ly_do||'—')+(x.boi?(' · '+x.boi):'')));
      it.appendChild(t);
      var m=ktEl('span','mut'); var b=ktEl('button','ghost',L('Hoàn tác','Restore')); b.style.cssText='padding:4px 9px;font-size:12px';
      b.onclick=function(){ ktAct('kt_rac_hoan',{ids:[x.id]},m,function(){ ktdRac(); ktdLoad(); }); };
      it.appendChild(b); it.appendChild(m); box.appendChild(it);
    });
  });
}
var KTU_TRANG_CO = 10;
function ktdUndo(){
  var box=document.getElementById('ktd-undo'); if(!box) return; box.textContent='';
  goi('kt_undo_ds',{},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Chưa có thao tác nào.','Nothing yet.'))); return; }
    var ten={sua:'Sửa số liệu',qr:'Áp QR',doisoat:'Đối soát nộp',doi_ngay:'Đổi ngày',nhap:'Nhập doanh thu cũ'};
    /* Phân trang — anh Thắng: "Nhật ký cũng đẻ gọn 10 thông báo 1 trang". Danh sách server đã
       giới hạn gh=40 dòng, nên cắt trang ở đây là đủ, khỏi cần đổi cổng API thêm tham số trang. */
    var tongTrang = Math.max(1, Math.ceil(r.rows.length / KTU_TRANG_CO));
    if (KTU_TRANG > tongTrang) KTU_TRANG = tongTrang;
    if (KTU_TRANG < 1) KTU_TRANG = 1;
    var trangRows = r.rows.slice((KTU_TRANG-1)*KTU_TRANG_CO, KTU_TRANG*KTU_TRANG_CO);

    function veTrangU(){
      var p=ktEl('div','act'); p.style.cssText='flex-wrap:wrap;align-items:center;margin-bottom:6px';
      var bT=ktEl('button','ghost','‹ '+L('Trước','Prev')); bT.disabled = (KTU_TRANG<=1);
      bT.onclick=function(){ KTU_TRANG--; ktdUndo(); };
      var sp=ktEl('span','mut', L('Trang','Page')+' '+KTU_TRANG+'/'+tongTrang
        +' · '+r.rows.length+' '+L('thông báo','entries'));
      var bS=ktEl('button','ghost',L('Sau','Next')+' ›'); bS.disabled = (KTU_TRANG>=tongTrang);
      bS.onclick=function(){ KTU_TRANG++; ktdUndo(); };
      p.appendChild(bT); p.appendChild(sp); p.appendChild(bS);
      return p;
    }
    if (tongTrang > 1) box.appendChild(veTrangU());

    trangRows.forEach(function(x){
      var it=ktEl('div','act'); it.style.cssText='flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.08);padding:6px 0';
      var t=ktEl('div'); t.style.flex='1';
      t.appendChild(ktEl('b',null,(ten[x.viec]||x.viec)+(x.daHoanTac?' · ĐÃ HOÀN TÁC':'')));
      t.appendChild(ktEl('div','mut',(x.ly_do||'')+(x.boi?(' · '+x.boi):'')+' · '+x.luc));
      it.appendChild(t);
      if(!x.daHoanTac && (x.viec==='sua'||x.viec==='qr'||x.viec==='doisoat'||x.viec==='nhap')){
        var m=ktEl('span','mut'); var b=ktEl('button','ghost',L('Hoàn tác','Undo')); b.style.cssText='padding:4px 9px;font-size:12px';
        b.onclick=function(){ if(!confirm(x.viec==='nhap'?L('Gỡ toàn bộ báo cáo của lần nhập này?','Remove all reports from this import?'):L('Hoàn tác thao tác này?','Undo this?'))) return; ktAct('kt_undo',{id:x.id},m,function(){ ktdUndo(); ktdLoad(); }); };
        it.appendChild(b); it.appendChild(m);
      }
      box.appendChild(it);
    });
    if (tongTrang > 1) box.appendChild(veTrangU());
  });
}
function ktdLoad(){
  var box=document.getElementById('ktd-list'); if(!box) return;
  box.textContent=''; box.appendChild(ktEl('p','mut',L('Đang tải…','Loading…')));
  goi('kt_ds',{thang:KTD_THANG},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    KTD_THANG=r.thang;
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Tháng này chưa có báo cáo.','No reports this month.'))); return; }
    /* Lọc theo cơ sở — anh Thắng: "Chỗ lọc duyệt báo cáo, cho lọc theo cơ sở". Lọc TRƯỚC khi
       cắt trang, nên "Trang 1/N" luôn tính trên đúng tập đang lọc, không tính trên cả tháng. */
    var rows0 = KTD_COSO ? r.rows.filter(function(o){ return o.coso === KTD_COSO; }) : r.rows;
    if(!rows0.length){
      box.appendChild(ktEl('p','mut',L('Cơ sở này chưa có báo cáo trong tháng.','No reports for this branch this month.')));
      return;
    }
    /* Đơn chưa duyệt hết (confirmedChairs < chairs) lên đầu, để không phải lướt qua đơn đã
       xong mới tới đơn cần xử lý. Sort ổn định (Array.prototype.sort) nên trong mỗi nhóm vẫn
       giữ nguyên thứ tự server trả về. */
    var rows = rows0.slice().sort(function(a,b){
      var xa = a.confirmedChairs < a.chairs ? 0 : 1;
      var xb = b.confirmedChairs < b.chairs ? 0 : 1;
      return xa - xb;
    });
    /* Phân trang — anh Thắng: "Chỉ hiện 10 cơ sở 1 trang, nó đang để nguyên nên bị lag". Tháng
       đông cơ sở mà vẽ + tải hết một lượt (dù đã tuần tự) vẫn là hàng chục thẻ to cùng nằm
       trong DOM một lúc — cuộn/thao tác ì trên máy yếu. Cắt trang TRƯỚC khi tải, nên trang sau
       không hề gọi kt_ct cho các báo cáo trang trước. */
    var tongTrang = Math.max(1, Math.ceil(rows.length / KTD_TRANG_CO));
    if (KTD_TRANG > tongTrang) KTD_TRANG = tongTrang;
    if (KTD_TRANG < 1) KTD_TRANG = 1;
    var trangRows = rows.slice((KTD_TRANG-1)*KTD_TRANG_CO, KTD_TRANG*KTD_TRANG_CO);

    function veTrang(){
      var p=ktEl('div','act'); p.style.cssText='flex-wrap:wrap;align-items:center;margin-bottom:10px';
      var bT=ktEl('button','ghost','‹ '+L('Trước','Prev')); bT.disabled = (KTD_TRANG<=1);
      bT.onclick=function(){ KTD_TRANG--; ktdLoad(); };
      var sp=ktEl('span','mut', L('Trang','Page')+' '+KTD_TRANG+'/'+tongTrang
        +' · '+rows.length+' '+L('cơ sở','branches'));
      var bS=ktEl('button','ghost',L('Sau','Next')+' ›'); bS.disabled = (KTD_TRANG>=tongTrang);
      bS.onclick=function(){ KTD_TRANG++; ktdLoad(); };
      p.appendChild(bT); p.appendChild(sp); p.appendChild(bS);
      return p;
    }
    if (tongTrang > 1) box.appendChild(veTrang());

    /* Bung sẵn (anh Thắng) nhưng TẢI TUẦN TỰ từng báo cáo một — không bắn N lượt gọi kt_ct
       cùng lúc. Nhân viên ở cơ sở thường mạng yếu; N lượt chạy song song chiếm hết hàng đợi
       kết nối của trình duyệt (~6 đường/host), nên một cú bấm Duyệt của người dùng phải XẾP
       SAU cả đám tải nền đó — đúng cảm giác "luồng duyệt rất chậm" dù mỗi lượt gọi tự nó
       không chậm. Tải xong thẻ này mới xin thẻ kế, để lượt bấm tay của người dùng luôn có
       đường trống mà đi. */
    var i=0;
    function ke(){
      if(i>=trangRows.length){ if (tongTrang > 1) box.appendChild(veTrang()); return; }
      box.appendChild(ktdCard(trangRows[i++], ke));
    }
    ke();
  });
}
function ktdCard(o, xong){
  var d=ktEl('div'); d.style.cssText='border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:11px 13px;margin-bottom:10px';
  var head=ktEl('div'); head.style.cssText='display:flex;gap:10px;flex-wrap:wrap;align-items:center';
  var t=ktEl('div'); t.style.flex='1';
  t.appendChild(ktEl('b',null,o.coso+' · '+o.ngay));
  var sub=ktEl('div','mut', o.chairs+' ghế · '+ktVnd(o.total)+'đ · duyệt '+o.confirmedChairs+'/'+o.chairs
    +(o.chairsNoPhoto?(' · '+o.chairsNoPhoto+' ghế thiếu ảnh'):'')+(o.staff?(' · '+o.staff):''));
  t.appendChild(sub);
  head.appendChild(t);
  if(o.locked){ var lk=ktEl('span','pill p-off',L('KHOÁ','LOCKED')); head.appendChild(lk); }
  /* Bung sẵn ngay khi vẽ danh sách — anh Thắng: khỏi phải bấm Xem từng báo cáo một mới thấy
     chi tiết ghế. Nút chỉ còn tác dụng ĐÓNG BỚT lại cho gọn, không còn ý nghĩa "tải lần đầu". */
  var bXem=ktEl('button','ghost',L('Đóng','Close')); head.appendChild(bXem);
  d.appendChild(head);
  var body=ktEl('div'); body.style.cssText='margin-top:10px'; d.appendChild(body);
  body.dataset.built='1';
  bXem.onclick=function(){
    if('none'===body.style.display){ body.style.display=''; bXem.textContent=L('Đóng','Close'); return; }
    body.style.display='none'; bXem.textContent=L('Xem','Detail');
  };
  ktdDetail(o,body,xong);
  return d;
}
function ktdDetail(o,box,xong){
  box.textContent=''; box.appendChild(ktEl('p','mut',L('Đang tải…','Loading…')));
  goi('kt_ct',{coso:o.coso,ngay:o.ngay},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); if(xong) xong(); return; }
    // thanh thao tác báo cáo
    var bar=ktEl('div','act'); bar.style.cssText='flex-wrap:wrap;margin-bottom:8px';
    var bDuyet=ktEl('button','on',L('Duyệt cả báo cáo','Confirm all'));
    var bBo=ktEl('button','ghost',L('Bỏ duyệt cả','Unconfirm all'));
    var bKhoa=ktEl('button','ghost', r.locked?L('Mở ngày','Unlock day'):L('Khoá ngày','Lock day'));
    var bDoi=ktEl('button','ghost',L('Đổi ngày','Change date'));
    var m=ktEl('span','mut');
    bar.appendChild(bDuyet); bar.appendChild(bBo); bar.appendChild(bKhoa); bar.appendChild(bDoi); bar.appendChild(m);
    box.appendChild(bar);
    function reload(){ box.dataset.built=''; ktdDetail(o,box); ktdLoad(); }
    bDuyet.onclick=function(){ ktAct('kt_duyet_ngay',{coso:o.coso,ngay:o.ngay,on:1},m,reload); };
    bBo.onclick=function(){ ktAct('kt_duyet_ngay',{coso:o.coso,ngay:o.ngay,on:0},m,reload); };
    bKhoa.onclick=function(){ ktAct('kt_khoa',{ngay:o.ngay,coso:o.coso,on:r.locked?0:1},m,reload); };
    bDoi.onclick=function(){
      var nm=prompt(L('Đổi NGÀY báo cáo '+o.coso+' ('+o.ngay+') sang ngày mới (yyyy-mm-dd):',
        'New date for '+o.coso+' ('+o.ngay+') yyyy-mm-dd:'), o.ngay); if(nm===null) return;
      var ly=prompt(L('Lý do đổi ngày:','Reason:')); if(ly===null) return;
      ktAct('kt_doi_ngay',{coso:o.coso,ngay_cu:o.ngay,ngay_moi:nm.trim(),ly_do:ly},m,reload);
    };
    // bảng ghế
    var sc=ktEl('div','table-scroll');
    var tb=ktEl('table'); tb.style.minWidth='760px';
    var thR=' style="text-align:right"';
    tb.innerHTML='<tr><th>'+L('Ghế','Chair')+'</th><th'+thR+'>'+L('Trước','Before')+'</th><th'+thR+'>'+L('Sau','After')
      +'</th><th'+thR+'>Actual</th><th'+thR+'>'+L('Tiền mặt','Cash')+'</th><th'+thR+'>QR</th><th'+thR+'>'+L('Nộp','Paid')+'</th>'
      +'<th style="text-align:center">'+L('Duyệt','OK')+'</th><th></th></tr>';
    (r.rows||[]).forEach(function(c){ tb.appendChild(ktdRow(o,c,m,reload,r.locked)); });
    sc.appendChild(tb); box.appendChild(sc);
    var sum=r.sum||{};
    box.appendChild(ktEl('div','mut','Tổng: tiền mặt '+ktVnd(sum.cash)+'đ · QR '+ktVnd(sum.qr)+'đ · đã nộp '+ktVnd(sum.paid)+'đ'));
    if(xong) xong();
  });
}
function ktdRow(o,c,m,reload,locked){
  var tr=ktEl('tr'); tr.dataset.ma=c.chairCode;
  function td(x,r){ var e=ktEl('td',null,x); if(r){e.style.textAlign='right';e.style.fontVariantNumeric='tabular-nums';} return e; }
  var tdN=ktEl('td'); tdN.appendChild(ktEl('b',null,c.chairName||c.chairCode));
  /* Ảnh chỉ số hiện thumbnail ngay dưới tên ghế — khỏi bấm mới thấy, đỡ phải đoán ảnh nào
     đúng ghế trước khi mở tab mới. Bấm thumbnail vẫn mở ảnh gốc cỡ đầy đủ ở tab mới. */
  if(c.anh && c.anh.length){
    var wrapA=ktEl('div'); wrapA.style.cssText='display:flex;gap:4px;margin-top:4px;flex-wrap:wrap';
    c.anh.forEach(function(u){
      var a=document.createElement('a'); a.href=u; a.target='_blank'; a.title='Xem ảnh cỡ đầy đủ';
      var img=document.createElement('img'); img.src=ktAnhSrc(u); img.loading='lazy'; img.alt='';
      img.style.cssText='width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.15)';
      a.appendChild(img); wrapA.appendChild(a);
    });
    tdN.appendChild(wrapA);
  }
  tr.appendChild(tdN);
  tr.appendChild(td(c.meterBefore==null?'—':ktVnd(c.meterBefore),1));
  tr.appendChild(td(c.meterAfter==null?'—':ktVnd(c.meterAfter),1));
  tr.appendChild(td(ktVnd(c.actual),1));
  tr.appendChild(td(ktVnd(c.cash),1));
  tr.appendChild(td(ktVnd(c.qr),1));
  tr.appendChild(td(ktVnd(c.paid),1));
  var tdD=ktEl('td'); var cb=ktEl('input'); cb.type='checkbox'; cb.checked=!!c.confirmed;
  cb.onchange=function(){ ktAct('kt_duyet',{targets:[{report_id:c.reportId,ma_may:c.chairCode}],on:cb.checked?1:0},m,function(){}); };
  tdD.appendChild(cb); tr.appendChild(tdD);
  var tdA=ktEl('td');
  if(!locked){
    var bS=ktEl('button','ghost',L('Sửa','Edit')); bS.style.cssText='padding:4px 8px;font-size:12px';
    bS.onclick=function(){ ktdSuaRow(o,c,tr,m,reload); };
    var bX=ktEl('button','ghost',L('Xoá','Del')); bX.style.cssText='padding:4px 8px;font-size:12px;margin-left:4px';
    bX.onclick=function(){
      var ly=prompt(L('Xoá ghế '+(c.chairName||c.chairCode)+' khỏi báo cáo? Lý do:','Remove chair? Reason:')); if(ly===null) return;
      ktAct('kt_xoa',{targets:[{report_id:c.reportId,ma_may:c.chairCode}],ly_do:ly},m,reload);
    };
    tdA.appendChild(bS); tdA.appendChild(bX);
  }
  tr.appendChild(tdA);
  return tr;
}
function ktdSuaRow(o,c,tr,m,reload){
  var td=tr.lastChild; td.textContent='';
  var wrap=ktEl('div'); wrap.style.cssText='display:flex;gap:4px;flex-wrap:wrap';
  function inb(ph,val){ var i=document.createElement('input'); i.type='text'; i.inputMode='numeric'; i.placeholder=ph; i.style.cssText='width:70px'; i.value=(val==null?'':val); return i; }
  var iAf=inb('sau',c.meterAfter), iQr=inb('QR',c.qr), iAd=inb('±',c.adjust);
  var iNo=document.createElement('input'); iNo.type='text'; iNo.placeholder='ghi chú'; iNo.style.width='110px'; iNo.value=c.note||'';
  var bL=ktEl('button','on',L('Lưu','Save')); bL.style.cssText='padding:4px 8px;font-size:12px';
  wrap.appendChild(iAf); wrap.appendChild(iQr); wrap.appendChild(iAd); wrap.appendChild(iNo); wrap.appendChild(bL);
  td.appendChild(wrap);
  bL.onclick=function(){
    function sn(s){ s=String(s==null?'':s); var neg=/^\s*-/.test(s); var dd=s.replace(/[^0-9]/g,''); return dd===''?0:(neg?-1:1)*parseInt(dd,10); }
    function mv(s){ s=String(s==null?'':s).replace(/[^0-9]/g,''); return s===''?'':parseInt(s,10); }
    var patch={ meterAfter:mv(iAf.value), qr:sn(iQr.value), adjust:sn(iAd.value), note:(iNo.value||'').trim() };
    ktAct('kt_sua',{report_id:c.reportId,ma_may:c.chairCode,patch:patch},m,reload);
  };
}
function ktAct(viec,d,m,cb){
  if(ban) return; ban=true;
  if(m){ m.textContent=L('Đang lưu…','Saving…'); m.className='mut'; }
  goi(viec,d,function(r){
    ban=false;
    if(!r||r.ok===false){ if(m){ m.textContent=(r&&(r.message||r.error))||'Lỗi.'; m.className='mut err'; } return; }
    if(m){ m.textContent=(r.message||L('Xong.','Done.')); m.className='mut ok'; }
    if(cb) cb(r);
  });
}

/* ---- TAB KẾ TOÁN: ĐỀ NGHỊ CHỈ SỐ + YÊU CẦU CƠ SỞ ---- */
function veKtDenghi(){
  return '<div class="card"><h2>⚖️ ' + L('Đề nghị đổi / xoá chỉ số','Meter change requests') + '</h2>'
    + '<p class="mut">' + L('Nhân viên gửi từ màn Báo cáo. Duyệt = đặt mốc chỉ số hiệu lực từ ngày áp '
      + 'dụng (báo cáo cũ giữ nguyên).','Staff submit from the report screen. Approve sets the meter '
      + 'baseline from the effective date (old reports unchanged).') + '</p>'
    + '<div id="ktdn-list"></div></div>'
    + '<div class="card"><h2>' + L('Yêu cầu cơ sở làm bổ sung / sửa','Ask a branch to add / fix') + '</h2>'
    + '<div class="act" style="flex-wrap:wrap">'
    + '<input id="ktyc-coso" placeholder="' + L('Cơ sở','Branch') + '" style="flex:2;min-width:150px">'
    + '<input id="ktyc-ngay" type="date" style="max-width:170px">'
    + '<select id="ktyc-loai"><option value="bo_sung">' + L('Làm bổ sung','Add') + '</option>'
    + '<option value="sua">' + L('Sửa báo cáo','Fix') + '</option></select>'
    + '<input id="ktyc-nd" placeholder="' + L('Nội dung yêu cầu','What to do') + '" style="flex:3;min-width:180px">'
    + '<button id="ktyc-gui" class="on">' + L('Gửi yêu cầu','Send') + '</button>'
    + '<span id="ktyc-msg" class="mut"></span></div>'
    + '<div id="ktyc-list" style="margin-top:12px"></div></div>';
}
function ktdnInit(){
  function val(id){ return (document.getElementById(id).value||'').trim(); }
  document.getElementById('ktyc-gui').onclick=function(){
    var m=document.getElementById('ktyc-msg');
    ktAct('kt_yeucau_tao',{coso:val('ktyc-coso'),ngay:val('ktyc-ngay'),loai:val('ktyc-loai'),noi_dung:val('ktyc-nd')},m,function(){
      document.getElementById('ktyc-nd').value=''; ktycLoad(); });
  };
  ktdnLoad(); ktycLoad();
}
function ktdnLoad(){
  var box=document.getElementById('ktdn-list'); if(!box) return;
  box.textContent=''; box.appendChild(ktEl('p','mut',L('Đang tải…','Loading…')));
  goi('kt_denghi_ds',{},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Không có đề nghị nào đang chờ.','No pending requests.'))); return; }
    r.rows.forEach(function(d){
      var it=ktEl('div'); it.style.cssText='border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:10px;margin-top:8px';
      it.appendChild(ktEl('b',null,(d.chairName||d.chairCode)+' · '+(d.loai==='xoa'?'Xoá về 0':'Đặt '+ktVnd(d.meterOpening))+' · từ '+d.fromDate));
      it.appendChild(ktEl('div','mut',(d.coso||'')+' · '+(d.nhanVien||'')+' · '+L('Lý do','Reason')+': '+(d.lyDo||'—')
        +(d.banGhiChan?(' · ⚠ '+d.banGhiChan+' bản ghi từ ngày đó'):'')));
      var m=ktEl('span','mut');
      var bD=ktEl('button','on',L('Duyệt','Approve')); bD.style.cssText='padding:5px 10px;font-size:12px;margin-right:6px';
      var bT=ktEl('button','ghost',L('Từ chối','Reject')); bT.style.cssText='padding:5px 10px;font-size:12px';
      bD.onclick=function(){ var g=prompt(L('Ghi chú duyệt (tuỳ chọn):','Note (optional):'))||''; ktAct('kt_denghi_duyet',{id:d.id,ghi_chu:g},m,function(rr){ if(rr.canhBao) alert(rr.canhBao); ktdnLoad(); }); };
      bT.onclick=function(){ var g=prompt(L('Lý do từ chối (bắt buộc):','Reject reason (required):')); if(g===null) return; ktAct('kt_denghi_tuchoi',{id:d.id,ghi_chu:g},m,ktdnLoad); };
      var bar=ktEl('div'); bar.style.marginTop='6px'; bar.appendChild(bD); bar.appendChild(bT); bar.appendChild(m);
      it.appendChild(bar); box.appendChild(it);
    });
  });
}
function ktycLoad(){
  var box=document.getElementById('ktyc-list'); if(!box) return;
  box.textContent='';
  goi('kt_yeucau_ds',{},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Chưa có yêu cầu nào.','No requests yet.'))); return; }
    r.rows.forEach(function(y){
      var it=ktEl('div'); it.style.cssText='border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:9px 11px;margin-top:7px';
      var tt=y.trangThai==='cho_lam'?L('Đang chờ','Pending'):(y.trangThai==='da_lam'?L('Đã làm','Done'):L('Đã rút','Cancelled'));
      it.appendChild(ktEl('b',null,(y.loaiChu||y.loai)+' · '+y.coSo+' · '+y.ngay+' · '+tt));
      it.appendChild(ktEl('div','mut',(y.noiDung||'')+(y.taoBoi?(' · '+y.taoBoi):'')));
      if(y.trangThai==='cho_lam'){
        var m=ktEl('span','mut'); var b=ktEl('button','ghost',L('Rút lại','Cancel')); b.style.cssText='padding:4px 9px;font-size:12px;margin-top:5px';
        b.onclick=function(){ if(!confirm(L('Rút lại yêu cầu này?','Cancel this request?'))) return; ktAct('kt_yeucau_huy',{id:y.id},m,ktycLoad); };
        it.appendChild(b); it.appendChild(m);
      }
      box.appendChild(it);
    });
  });
}

/* ---- TAB KẾ TOÁN: ĐỐI SOÁT NỘP TIỀN + SỔ CÔNG NỢ ---- */
var KTI_THANG = '';
/* ══════════════════════════════ DOANH THU ĐỊA ĐIỂM (lịch sử nguyên năm, gọn theo tháng) ══════ */
var KLS_COSO = '', KLS_NAM = '', KLS_MO = {};   // cơ sở, năm, tháng nào đang mở
function veKtLichSu(){
  var coso = (D && D.coso) || [];
  if (!KLS_NAM) KLS_NAM = (D && D.luc ? String(D.luc).slice(0, 4) : '2026');
  var opt = '<option value="">' + L('Tất cả cơ sở','All sites') + '</option>'
    + coso.map(function(c){ return '<option value="' + esc(c.ten) + '"' + (KLS_COSO === c.ten ? ' selected' : '') + '>'
        + esc(c.ten) + (c.tinh ? ' · ' + esc(c.tinh) : '') + '</option>'; }).join('');
  return '<div class="card"><h2>🏢 ' + L('Doanh thu địa điểm — theo năm','Site revenue — by year') + '</h2>'
    + '<p class="mut">' + L('Xem một cơ sở/máy chạy xuyên suốt cả năm: doanh thu và chỉ số máy liên tục, gộp gọn theo tháng — bấm tháng để xem từng ghế (chỉ số đầu→cuối) và từng ngày.',
      'A site/chair across the whole year: revenue and continuous meter readings, grouped by month — click a month for per-chair (meter start→end) and per-day detail.') + '</p>'
    + '<div class="act" style="flex-wrap:wrap"><b>' + L('Cơ sở','Site') + ':</b>'
    + '<select id="kls-coso" style="max-width:280px">' + opt + '</select>'
    + '<b style="margin-left:6px">' + L('Năm','Year') + ':</b>'
    + '<input type="number" id="kls-nam" min="2020" max="2100" value="' + esc(KLS_NAM) + '" style="max-width:110px">'
    + '<button id="kls-xem" class="on">' + L('Xem','Load') + '</button></div>'
    + '<div id="kls-wrap" style="margin-top:12px"></div></div>';
}
function klsInit(){
  var s = document.getElementById('kls-coso'), n = document.getElementById('kls-nam'), b = document.getElementById('kls-xem');
  if (b) b.onclick = function(){ KLS_COSO = s ? s.value : ''; KLS_NAM = (n && n.value) ? n.value : KLS_NAM; KLS_MO = {}; klsLoad(); };
  klsLoad();
}
function klsLoad(){
  var box = document.getElementById('kls-wrap'); if (!box) return;
  box.textContent = ''; box.appendChild(ktEl('p', 'mut', L('Đang tải…','Loading…')));
  goi('kt_lichsu', { coso: KLS_COSO, nam: KLS_NAM }, function(r){
    box.textContent = '';
    if (!r || !r.ok) { box.appendChild(ktEl('p', 'mut', (r && r.error) || 'Lỗi.')); return; }
    var tn = r.tong_nam || { tong: 0, tien_mat: 0, qr: 0 };
    box.appendChild(ktEl('div', 'mut', L('Cả năm','Year') + ' ' + r.nam + (KLS_COSO ? (' · ' + KLS_COSO) : (' · ' + L('tất cả cơ sở','all sites')))
      + ' — ' + L('tổng','total') + ' ' + ktVnd(tn.tong) + 'đ · ' + L('tiền mặt','cash') + ' ' + ktVnd(tn.tien_mat) + 'đ · QR ' + ktVnd(tn.qr) + 'đ'));
    if (!r.thang || !r.thang.length) { box.appendChild(ktEl('p', 'mut', L('Năm này chưa có dữ liệu.','No data this year.'))); return; }
    /* Biểu đồ doanh thu theo tháng (giữ thứ tự tháng, không sắp theo giá trị) — nhìn cả năm một mắt. */
    var thangTang = r.thang.slice().sort(function(a,b){ return a.thang < b.thang ? -1 : (a.thang > b.thang ? 1 : 0); });
    var cd = ktEl('div', 'card'); cd.style.marginBottom = '12px';
    cd.innerHTML = '<h2>' + L('Doanh thu theo tháng','Revenue by month') + '</h2>'
      + bdCotStack(thangTang.map(function(T){ return { ten: T.thang.slice(5) + '/' + T.thang.slice(0,4),
          tm: Number(T.tien_mat)||0, qr: Number(T.qr)||0 }; }), 12);
    box.appendChild(cd);
    r.thang.forEach(function(T){ box.appendChild(klsThang(T)); });
  });
}
function klsThang(T){
  var mo = !!KLS_MO[T.thang];
  var card = ktEl('div'); card.style.cssText = 'border:1px solid var(--line);border-radius:10px;margin-bottom:8px;overflow:hidden';
  var head = ktEl('div', 'act'); head.style.cssText = 'cursor:pointer;padding:10px 12px;background:#f4f6f9;flex-wrap:wrap;align-items:center';
  var mm = T.thang.slice(5) + '/' + T.thang.slice(0, 4);
  head.appendChild(ktEl('b', null, (mo ? '▾ ' : '▸ ') + L('Tháng','Month') + ' ' + mm));
  head.appendChild(ktEl('span', 'mut', ' · ' + T.so_ghe + ' ' + L('ghế','chairs') + ' · ' + T.so_ngay + ' ' + L('ngày','days')));
  var sp = ktEl('span'); sp.style.flex = '1'; head.appendChild(sp);
  head.appendChild(ktEl('b', null, ktVnd(T.tong) + 'đ'));
  head.appendChild(ktEl('span', 'mut', ' (TM ' + ktVnd(T.tien_mat) + ' · QR ' + ktVnd(T.qr) + ')'));
  card.appendChild(head);
  var body = ktEl('div'); body.style.cssText = 'padding:' + (mo ? '10px 12px' : '0') + ';display:' + (mo ? 'block' : 'none');
  if (mo) klsBody(body, T);
  card.appendChild(body);
  head.onclick = function(){
    KLS_MO[T.thang] = !KLS_MO[T.thang];
    var on = KLS_MO[T.thang];
    body.style.display = on ? 'block' : 'none'; body.style.padding = on ? '10px 12px' : '0';
    head.firstChild.textContent = (on ? '▾ ' : '▸ ') + L('Tháng','Month') + ' ' + mm;
    if (on && !body.hasChildNodes()) klsBody(body, T);
  };
  return card;
}
function klsBody(body, T){
  /* Biểu đồ doanh thu theo NGÀY trong tháng (giữ thứ tự ngày), xếp chồng Tiền mặt/QR. */
  if ((T.ngay || []).length) {
    var cd = ktEl('div'); cd.style.marginBottom = '12px';
    cd.innerHTML = '<div class="mut" style="margin-bottom:6px">' + L('Doanh thu theo ngày','Revenue by day') + '</div>'
      + bdCotStack((T.ngay || []).map(function(n){
          var nh = /^\d{4}-\d{2}-\d{2}/.test(String(n.ngay)) ? (String(n.ngay).slice(8,10) + '/' + String(n.ngay).slice(5,7)) : String(n.ngay);
          return { ten: nh, tm: Number(n.tien_mat)||0, qr: Number(n.qr)||0 };
        }), 40);
    body.appendChild(cd);
  }
  body.appendChild(ktEl('div', 'mut', L('Theo ghế (chỉ số máy đầu → cuối tháng)','By chair (meter start → end of month)')));
  var sc1 = ktEl('div', 'table-scroll'); var t1 = ktEl('table'); t1.style.minWidth = '620px';
  t1.innerHTML = '<tr><th>' + L('Ghế','Chair') + '</th><th>' + L('Chỉ số đầu→cuối','Meter start→end')
    + '</th><th class="r">' + L('Ngày','Days') + '</th><th class="r">' + L('Tiền mặt','Cash') + '</th><th class="r">QR</th><th class="r">' + L('Tổng','Total') + '</th></tr>';
  (T.ghe || []).forEach(function(g){
    var cs = (g.cs_dau == null && g.cs_cuoi == null) ? '<span class="mut">—</span>'
      : ((g.cs_dau == null ? '?' : Number(g.cs_dau).toLocaleString('vi-VN')) + ' → ' + (g.cs_cuoi == null ? '?' : Number(g.cs_cuoi).toLocaleString('vi-VN')));
    var tr = ktEl('tr');
    tr.innerHTML = '<td><b>' + esc(g.ma) + '</b></td><td>' + cs + '</td>'
      + '<td class="r">' + g.so_ngay + '</td><td class="r">' + ktVnd(g.tien_mat) + '</td><td class="r">' + ktVnd(g.qr) + '</td><td class="r"><b>' + ktVnd(g.tong) + '</b></td>';
    t1.appendChild(tr);
  });
  sc1.appendChild(t1); body.appendChild(sc1);
  body.appendChild(ktEl('div', 'mut', L('Theo ngày','By day'))).style.marginTop = '10px';
  var sc2 = ktEl('div', 'table-scroll'); var t2 = ktEl('table'); t2.style.minWidth = '480px';
  t2.innerHTML = '<tr><th>' + L('Ngày','Date') + '</th><th class="r">' + L('Ghế','Chairs') + '</th><th class="r">'
    + L('Tiền mặt','Cash') + '</th><th class="r">QR</th><th class="r">' + L('Tổng','Total') + '</th></tr>';
  (T.ngay || []).forEach(function(n){
    var tr = ktEl('tr');
    tr.innerHTML = '<td>' + esc(n.ngay) + '</td><td class="r">' + n.so_ghe + '</td><td class="r">' + ktVnd(n.tien_mat)
      + '</td><td class="r">' + ktVnd(n.qr) + '</td><td class="r"><b>' + ktVnd(n.tong) + '</b></td>';
    t2.appendChild(tr);
  });
  sc2.appendChild(t2); body.appendChild(sc2);
}
function veKtTien(){
  return '<div class="card"><div class="act" style="flex-wrap:wrap"><b>' + L('Tháng','Month') + ':</b>'
    + '<input type="month" id="kti-thang" style="max-width:170px">'
    + '<button id="kti-xem" class="on">' + L('Xem','Load') + '</button></div></div>'
    + '<div class="card"><h2>' + L('Sổ công nợ','Debt ledger') + '</h2><div id="kti-congno"></div></div>'
    + '<div class="card"><h2>' + L('Xác nhận nộp tiền (tay)','Confirm cash-in (manual)') + '</h2>'
    + '<p class="mut">' + L('Cơ sở còn nợ tiền mặt — điền số đã nhận rồi ghi.','Branches still owing — fill received amount.') + '</p>'
    + '<div id="kti-cannop"></div></div>'
    + '<div class="card"><h2>' + L('Đối soát chuyển khoản (sao kê)','Reconcile bank transfers') + '</h2>'
    + '<p class="mut">' + L('Dán sao kê: mỗi dòng "ngày[tab]số tiền[tab]nội dung" (copy từ Excel). Nhận cơ sở qua bảng mã nộp tiền bên dưới.',
      'Paste statement: each line "date[tab]amount[tab]desc".') + '</p>'
    + '<textarea id="kti-ck" rows="5" style="width:100%;font:inherit;border-radius:9px;border:1px solid rgba(255,255,255,.15);background:rgba(10,12,22,.55);color:#e8ebff;padding:8px"></textarea>'
    + '<div class="act" style="margin-top:8px"><button id="kti-ck-xem" class="ghost">' + L('Xem trước','Preview') + '</button>'
    + '<button id="kti-ck-ap" class="on">' + L('Áp ghi nộp','Apply') + '</button><span id="kti-ck-msg" class="mut"></span></div>'
    + '<div id="kti-ck-kq" style="margin-top:8px"></div></div>'
    + '<div class="card"><h2>' + L('Đối chiếu QR (báo cáo ↔ webhook)','QR reconcile') + '</h2>'
    + '<p class="mut">' + L('QR ngân hàng đẩy về là số CHUẨN. Áp sửa = ghi đúng ô QR, TIỀN MẶT GIỮ NGUYÊN.',
      'Bank QR is authoritative. Apply writes the QR cell only; cash unchanged.') + '</p>'
    + '<div id="kti-qr"></div></div>'
    + '<div class="card"><h2>' + L('Mã nộp tiền (nội dung CK ↔ cơ sở)','Pay codes') + '</h2><div id="kti-manop"></div></div>';
}
function ktiInit(){
  var iT=document.getElementById('kti-thang');
  if(!KTI_THANG) KTI_THANG=(D&&D.luc?String(D.luc).slice(0,7):'');
  if(iT&&KTI_THANG) iT.value=KTI_THANG;
  document.getElementById('kti-xem').onclick=function(){ KTI_THANG=iT.value; ktiCongNo(); ktiCanNop(); ktiQr(); };
  document.getElementById('kti-ck-xem').onclick=function(){ ktiCk(false); };
  document.getElementById('kti-ck-ap').onclick=function(){ ktiCk(true); };
  ktiCongNo(); ktiCanNop(); ktiQr(); ktiMaNop();
}
function ktiQr(){
  var box=document.getElementById('kti-qr'); if(!box) return;
  box.textContent=''; box.appendChild(ktEl('p','mut',L('Đang tải…','Loading…')));
  goi('kt_qr_ds',{thang:KTI_THANG},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    box.appendChild(ktEl('div','mut', L('Khớp','Match')+' '+r.khop+' ghế · '+L('lệch','off')+' '+r.soLech
      +' ghế · QR báo cáo '+ktVnd(r.tongBc)+'đ / webhook '+ktVnd(r.tongWeb)+'đ'));
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Không có ghế lệch.','No mismatches.'))); return; }
    var rows=r.rows;
    /* Chọn mặc định HẾT (theo khoá report_id|ma_may) — giữ qua các trang, không mất khi lật trang. */
    var sel={}; rows.forEach(function(x){ sel[x.report_id+'|'+x.ma_may]=x; });
    var PER=20, QR_PG=0, pages=Math.max(1, Math.ceil(rows.length/PER));
    var sc=ktEl('div','table-scroll'); var tb=ktEl('table'); tb.style.minWidth='640px';
    var thR=' style="text-align:right"';
    var thead='<tr><th></th><th>'+L('Ghế','Chair')+'</th><th>'+L('Ngày','Date')
      +'</th><th'+thR+'>'+L('QR báo cáo','Reported')+'</th><th'+thR+'>'+L('QR webhook','Bank')
      +'</th><th'+thR+'>'+L('Lệch','Off')+'</th></tr>';
    sc.appendChild(tb); box.appendChild(sc);
    var pager=ktEl('div','act'); pager.style.cssText='margin-top:8px;align-items:center'; box.appendChild(pager);
    function veTrang(){
      if(QR_PG>=pages) QR_PG=pages-1; if(QR_PG<0) QR_PG=0;
      tb.innerHTML=thead;
      var from=QR_PG*PER, to=Math.min(rows.length, from+PER);
      for(var i=from;i<to;i++){ (function(x){
        var key=x.report_id+'|'+x.ma_may;
        var tr=ktEl('tr');
        var td0=ktEl('td'); var cb=document.createElement('input'); cb.type='checkbox'; cb.checked=(sel[key]!=null);
        cb.onchange=function(){ if(cb.checked) sel[key]=x; else delete sel[key]; }; td0.appendChild(cb); tr.appendChild(td0);
        var tn=ktEl('td'); tn.appendChild(ktEl('b',null,x.ten||x.ma_may)); tn.appendChild(ktEl('div','mut',x.coso)); tr.appendChild(tn);
        tr.appendChild(ktEl('td',null,x.ngay));
        function c(v){ var e=ktEl('td',null,ktVnd(v)); e.style.textAlign='right'; return e; }
        tr.appendChild(c(x.bcQr)); tr.appendChild(c(x.webQr));
        var el2=ktEl('td',null,ktVnd(x.lech)); el2.style.textAlign='right'; el2.style.color='#ff8087'; tr.appendChild(el2);
        tb.appendChild(tr);
      })(rows[i]); }
      pager.textContent='';
      var bTr=ktEl('button','ghost','‹ '+L('Trước','Prev')); bTr.style.cssText='padding:4px 10px'; bTr.disabled=(QR_PG<=0);
      bTr.onclick=function(){ QR_PG--; veTrang(); };
      var bSa=ktEl('button','ghost',L('Sau','Next')+' ›'); bSa.style.cssText='padding:4px 10px'; bSa.disabled=(QR_PG>=pages-1);
      bSa.onclick=function(){ QR_PG++; veTrang(); };
      pager.appendChild(bTr);
      pager.appendChild(ktEl('span','mut', L('Trang','Page')+' '+(QR_PG+1)+'/'+pages+' · '+rows.length+' '+L('ghế lệch','off')+' · '+L('dòng','rows')+' '+(from+1)+'–'+to));
      pager.appendChild(bSa);
    }
    veTrang();
    var bar=ktEl('div','act'); bar.style.marginTop='8px'; var m=ktEl('span','mut');
    var b=ktEl('button','on',L('Áp sửa QR (tất cả đã chọn)','Apply QR (all selected)'));
    b.onclick=function(){
      var ts=Object.keys(sel).map(function(k){ var x=sel[k]; return {report_id:x.report_id,ma_may:x.ma_may}; });
      if(!ts.length){ m.textContent=L('Chưa chọn ghế nào.','None selected.'); m.className='mut err'; return; }
      var ly=prompt(L('Lý do áp sửa QR:','Reason:')); if(ly===null) return;
      ktAct('kt_qr_ap',{targets:ts,ly_do:ly},m,function(rr){ if(rr.warn&&rr.warn.length) alert('⚠ '+rr.warn.length+' ghế (tiền mặt+QR)≠actual — kiểm lại.'); ktiQr(); ktiCongNo(); });
    };
    bar.appendChild(b); bar.appendChild(m); box.appendChild(bar);
  });
}
function ktiCongNo(){
  var box=document.getElementById('kti-congno'); if(!box) return;
  box.textContent=''; box.appendChild(ktEl('p','mut',L('Đang tải…','Loading…')));
  goi('kt_congno',{thang:KTI_THANG},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    KTI_THANG=r.thang;
    /* Ô lọc linh hoạt: tên cơ sở đã chứa cả CHUỖI (GO, AEON, CGV…) lẫn TỈNH/THÀNH (Bến Tre, Bình
       Dương…) nên gõ chuỗi hay tỉnh đều lọc được. */
    var iQ=document.createElement('input'); iQ.placeholder=L('Lọc cơ sở / chuỗi / tỉnh thành…','Filter branch / chain / province…');
    iQ.style.cssText='max-width:340px;margin-bottom:10px';
    box.appendChild(iQ);
    var sc=ktEl('div','table-scroll'); var tb=ktEl('table'); tb.style.minWidth='820px';
    tb.innerHTML='<tr><th>'+L('Cơ sở','Branch')+'</th><th class="r">'+L('Dư đầu','Opening')+'</th><th class="r">'+L('Phát sinh','Charged')
      +'</th><th class="r">'+L('Đã nhận TM','Cash in')+'</th><th class="r">'+L('Đã nhận CK','Transfer in')+'</th><th class="r">'+L('Chưa nộp','Unpaid')
      +'</th><th class="r">'+L('Dư cuối','Closing')+'</th></tr>';
    (r.rows||[]).forEach(function(o){
      var tr=ktEl('tr'); tr.dataset.q=((o.coso||'')+' '+(o.tinh||'')).toLowerCase();
      function c(x,red){ var e=ktEl('td',null,x); e.style.textAlign='right'; e.style.fontVariantNumeric='tabular-nums'; if(red&&x&&x!=='0') e.style.color='#ff8087'; return e; }
      var tdN=ktEl('td'); tdN.appendChild(ktEl('b',null,o.coso)); if(o.tinh){ tdN.appendChild(ktEl('div','mut',o.tinh)); } tr.appendChild(tdN);
      tr.appendChild(c(ktVnd(o.opening))); tr.appendChild(c(ktVnd(o.phaiThu)));
      tr.appendChild(c(ktVnd(o.daNhanTM))); tr.appendChild(c(ktVnd(o.daNhanCK)));
      tr.appendChild(c(ktVnd(o.chuaNop),1)); tr.appendChild(c(ktVnd(o.closing),o.closing>0?1:0));
      tb.appendChild(tr);
    });
    iQ.oninput=function(){ var q=iQ.value.trim().toLowerCase();
      [].forEach.call(tb.querySelectorAll('tr[data-q]'),function(tr){ tr.style.display=(!q||tr.dataset.q.indexOf(q)>=0)?'':'none'; }); };
    sc.appendChild(tb); box.appendChild(sc);
    var bar=ktEl('div','act'); bar.style.marginTop='8px';
    var m=ktEl('span','mut');
    var bC=ktEl('button','on',L('Chốt sổ → '+r.thangSau,'Close → '+r.thangSau));
    var bM=ktEl('button','ghost',L('Mở lại '+r.thangSau,'Reopen'));
    bC.onclick=function(){ var ly=prompt(L('Lý do chốt sổ:','Reason:')); if(ly===null) return; ktAct('kt_congno_chot',{thang:r.thang,ly_do:ly},m,ktiCongNo); };
    bM.onclick=function(){ if(!confirm(L('Mở lại '+r.thangSau+'? Số dư về tự tính lũy kế.','Reopen?'))) return; ktAct('kt_congno_mo',{thang:r.thang},m,ktiCongNo); };
    bar.appendChild(bC); bar.appendChild(bM); bar.appendChild(m);
    if(r.daChot) bar.appendChild(ktEl('span','pill p-off',L('ĐÃ CHỐT','CLOSED')));
    box.appendChild(bar);
  });
}
function ktiCanNop(){
  var box=document.getElementById('kti-cannop'); if(!box) return;
  box.textContent=''; box.appendChild(ktEl('p','mut',L('Đang tải…','Loading…')));
  goi('kt_can_nop',{thang:KTI_THANG},function(r){
    box.textContent='';
    if(!r||!r.ok){ box.appendChild(ktEl('p','mut',(r&&r.error)||'Lỗi.')); return; }
    if(!r.rows.length){ box.appendChild(ktEl('p','mut',L('Không còn cơ sở nợ tiền mặt.','No branches owing.'))); return; }
    var inputs=[];
    r.rows.forEach(function(o){
      var row=ktEl('div','act'); row.style.cssText='flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.08);padding:6px 0';
      var lbl=ktEl('div'); lbl.style.flex='2'; lbl.style.minWidth='180px';
      lbl.appendChild(ktEl('b',null,o.coso));
      lbl.appendChild(ktEl('span','mut',' · còn '+ktVnd(o.conLai)+'đ ('+o.soGhe+' ghế)'));
      row.appendChild(lbl);
      var iA=document.createElement('input'); iA.type='text'; iA.inputMode='numeric'; iA.placeholder=L('Số đã nhận','Received'); iA.style.maxWidth='140px';
      var sM=document.createElement('select'); sM.innerHTML='<option value="cash">TM</option><option value="transfer">CK</option>'; sM.style.maxWidth='90px';
      row.appendChild(iA); row.appendChild(sM);
      inputs.push({coso:o.coso,iA:iA,sM:sM});
      box.appendChild(row);
    });
    var bar=ktEl('div','act'); bar.style.marginTop='8px';
    var m=ktEl('span','mut');
    var bX=ktEl('button','ghost',L('Xem trước','Preview')); var bG=ktEl('button','on',L('Ghi nộp','Save'));
    function pheps(){ var ps=[]; inputs.forEach(function(x){ var v=(x.iA.value||'').replace(/[^0-9]/g,''); if(v) ps.push({coso:x.coso,amount:parseInt(v,10),method:x.sM.value}); }); return ps; }
    bX.onclick=function(){ ktAct('kt_nop_tay',{pheps:pheps(),thang:KTI_THANG,apply:0},m,function(){}); };
    bG.onclick=function(){ var ly=prompt(L('Lý do xác nhận tay:','Reason:')); if(ly===null) return; ktAct('kt_nop_tay',{pheps:pheps(),thang:KTI_THANG,apply:1,ly_do:ly},m,function(){ ktiCanNop(); ktiCongNo(); }); };
    bar.appendChild(bX); bar.appendChild(bG); bar.appendChild(m); box.appendChild(bar);
  });
}
function ktiCkParse(){
  var t=(document.getElementById('kti-ck').value||'').trim(); if(!t) return [];
  return t.split(/\n/).map(function(line){
    var p=line.split(/\t/); if(p.length<2) p=line.split(/\s{2,}|,/);
    return { date:(p[0]||'').trim(), amount:(p[1]||'').replace(/[^0-9]/g,''), desc:(p.slice(2).join(' ')||p[1]||'').trim() };
  }).filter(function(x){ return x.amount; });
}
function ktiCk(apply){
  var m=document.getElementById('kti-ck-msg'); var kq=document.getElementById('kti-ck-kq');
  var rows=ktiCkParse(); if(!rows.length){ m.textContent=L('Chưa dán dòng nào.','No rows.'); m.className='mut err'; return; }
  var d={rows:rows,thang:KTI_THANG,apply:apply?1:0};
  if(apply){ var ly=prompt(L('Lý do áp đối soát CK:','Reason:')); if(ly===null) return; d.ly_do=ly; }
  ktAct('kt_ck',d,m,function(r){
    kq.textContent='';
    kq.appendChild(ktEl('div','mut', (r.apply?('Đã ghi '+(r.soGheGhi||0)+' ghế. '):'')
      + (r.unknown?(r.unknown.length+' giao dịch không rõ mã · '):'') + (r.ambiguous?(r.ambiguous.length+' khớp nhiều mã'):'')));
    (r.rows||[]).forEach(function(x){ kq.appendChild(ktEl('div','mut','• '+x.coso+': vào '+ktVnd(x.bank)+'đ · cần '+ktVnd(x.need)+'đ'+(x.willWrite?(' · ghi '+x.willWrite+' ghế'):' · KHÔNG có ghế còn nợ'))); });
    if(!apply) ktiCongNo();
    else { ktiCongNo(); ktiCanNop(); }
  });
}
function ktiMaNop(){
  var box=document.getElementById('kti-manop'); if(!box) return;
  box.textContent='';
  goi('kt_ma_nop_ds',{},function(r){
    box.textContent='';
    var sc=ktEl('div','table-scroll'); var tb=ktEl('table'); tb.style.minWidth='520px';
    tb.innerHTML='<tr><th>'+L('Mã (nội dung CK)','Code')+'</th><th>'+L('Cơ sở','Branch')+'</th><th></th></tr>';
    (r&&r.rows||[]).forEach(function(x){
      var tr=ktEl('tr'); tr.appendChild(ktEl('td',null,x.code)); tr.appendChild(ktEl('td',null,x.coso));
      var td=ktEl('td'); var b=ktEl('button','ghost',L('Xoá','Del')); b.style.cssText='padding:4px 8px;font-size:12px';
      b.onclick=function(){ if(!confirm('Xoá mã '+x.code+'?')) return; goi('kt_ma_nop_xoa',{id:x.id},function(){ ktiMaNop(); }); };
      td.appendChild(b); tr.appendChild(td); tb.appendChild(tr);
    });
    sc.appendChild(tb); box.appendChild(sc);
    var row=ktEl('div','act'); row.style.marginTop='8px';
    var iC=document.createElement('input'); iC.placeholder=L('Mã / nội dung CK','Code'); iC.style.flex='1';
    var iL=document.createElement('input'); iL.placeholder=L('Cơ sở','Branch'); iL.style.flex='2';
    var b=ktEl('button','on',L('Thêm','Add')); var m=ktEl('span','mut');
    b.onclick=function(){ if(!(iC.value||'').trim()||!(iL.value||'').trim()){ m.textContent=L('Thiếu mã/cơ sở.','Missing.'); m.className='mut err'; return; }
      ktAct('kt_ma_nop_luu',{id:0,code:iC.value.trim(),coso:iL.value.trim()},m,function(){ iC.value=''; iL.value=''; ktiMaNop(); }); };
    row.appendChild(iC); row.appendChild(iL); row.appendChild(b); row.appendChild(m); box.appendChild(row);
  });
}

/* ---- TAB KẾ TOÁN: XUẤT MISA + BÁO CÁO NGÀY + UNIT ID ---- */
function ktCsvTaiVe(aoa, fileName){
  var esc2=function(v){ v=(v==null?'':String(v)); if(/[",\n]/.test(v)) v='"'+v.replace(/"/g,'""')+'"'; return v; };
  var csv=aoa.map(function(row){ return row.map(esc2).join(','); }).join('\r\n');
  var blob=new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'});
  var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=fileName||'export.csv';
  document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); },100);
}
function veKtXuat(){
  var thg=(D&&D.luc?String(D.luc).slice(0,7):'');
  return '<div class="card"><h2>' + L('Xuất chứng từ MISA','Export MISA vouchers') + '</h2>'
    + '<p class="mut">' + L('CHỈ ghế đã duyệt. Tiền mặt 1 dòng, QR 1 dòng. Tải file CSV (mở Excel rồi dán vào MISA).',
      'Confirmed chairs only. Cash + QR lines. Downloads CSV.') + '</p>'
    + '<div class="act" style="flex-wrap:wrap">'
    + '<label class="mut">' + L('Từ','From') + ' <input type="date" id="ktx-tu" style="max-width:150px"></label>'
    + '<label class="mut">' + L('Đến','To') + ' <input type="date" id="ktx-den" style="max-width:150px"></label>'
    + '<label class="mut">' + L('hoặc tháng','or month') + ' <input type="month" id="ktx-thang" value="' + thg + '" style="max-width:150px"></label>'
    + '<label class="mut">' + L('Số CT đầu','First voucher') + ' <input type="text" id="ktx-soct" placeholder="VD NVKMN1542" style="max-width:150px"></label>'
    + '<label class="mut"><input type="checkbox" id="ktx-chitm"> ' + L('chỉ tiền mặt','cash only') + '</label>'
    + '<button id="ktx-misa" class="on">' + L('Tải chứng từ','Download') + '</button>'
    + '<span id="ktx-misa-msg" class="mut"></span></div></div>'
    + '<div class="card"><h2>' + L('Báo cáo ngày (DAILY SALES)','Daily sales report') + '</h2>'
    + '<p class="mut">' + L('Chéo: mỗi dòng một cơ sở, mỗi cột một ngày. Cần Unit ID (bên dưới).',
      'Cross-tab: branch × day. Needs Unit IDs below.') + '</p>'
    + '<div class="act" style="flex-wrap:wrap">'
    + '<input type="month" id="ktx-bn-thang" value="' + thg + '" style="max-width:150px">'
    + '<label class="mut"><input type="checkbox" id="ktx-bn-duyet"> ' + L('chỉ đã duyệt','confirmed only') + '</label>'
    + '<button id="ktx-bn" class="on">' + L('Tải báo cáo ngày','Download') + '</button>'
    + '<span id="ktx-bn-msg" class="mut"></span></div></div>'
    + '<div class="card"><h2>' + L('Unit ID MISA (theo cơ sở)','MISA Unit IDs') + '</h2>'
    + '<div class="act"><button id="ktx-seed" class="ghost">' + L('Mồi từ danh mục ghế','Seed from chairs') + '</button>'
    + '<span id="ktx-seed-msg" class="mut"></span></div>'
    + '<div id="ktx-manop-wrap" style="margin-top:10px"></div></div>'
    + '<div class="card"><h2>🧪 ' + L('Kiểm tra nhanh (self-test)','Self-test') + '</h2>'
    + '<div class="act"><button id="ktx-test" class="ghost">' + L('Chạy kiểm tra','Run') + '</button>'
    + '<span id="ktx-test-msg" class="mut"></span></div><div id="ktx-test-kq" style="margin-top:8px"></div></div>';
}
function ktxInit(){
  document.getElementById('ktx-test').onclick=function(){
    var m=document.getElementById('ktx-test-msg'), kq=document.getElementById('ktx-test-kq');
    m.textContent=L('Đang chạy…','Running…'); m.className='mut'; kq.textContent='';
    goi('kt_selftest',{},function(r){
      if(!r||!r.ok){ m.textContent=(r&&r.error)||'Lỗi.'; m.className='mut err'; return; }
      m.textContent=r.passed+'/'+r.total+(r.failed?(' — '+r.failed+' LỖI'):' ĐẠT'); m.className='mut '+(r.failed?'err':'ok');
      (r.tests||[]).forEach(function(x){ kq.appendChild(ktEl('div',x.pass?'mut':'mut err',(x.pass?'✓ ':'✗ ')+x.name+(x.detail?(' — '+x.detail):''))); });
    });
  };
  document.getElementById('ktx-misa').onclick=function(){
    var m=document.getElementById('ktx-misa-msg'); m.textContent=L('Đang dựng…','Building…'); m.className='mut';
    goi('kt_misa',{from:document.getElementById('ktx-tu').value,to:document.getElementById('ktx-den').value,
      thang:document.getElementById('ktx-thang').value,chi_tien_mat:document.getElementById('ktx-chitm').checked?1:0,
      so_ct_dau:document.getElementById('ktx-soct').value},function(r){
      if(!r||!r.ok){ m.textContent=(r&&r.error)||'Lỗi.'; m.className='mut err'; return; }
      if(r.rows<=0){ m.textContent=L('Không có ghế đã duyệt trong khoảng này.','No confirmed chairs.'); m.className='mut err'; return; }
      ktCsvTaiVe(r.aoa,r.fileName);
      m.textContent=L('Đã tải: ','Downloaded: ')+r.rows+' dòng · tiền mặt '+ktVnd(r.tienMat)+'đ'+(r.chiTienMat?'':(' · QR '+ktVnd(r.tienQr)+'đ')); m.className='mut ok';
    });
  };
  document.getElementById('ktx-bn').onclick=function(){
    var m=document.getElementById('ktx-bn-msg'); m.textContent=L('Đang dựng…','Building…'); m.className='mut';
    goi('kt_baocao_ngay',{thang:document.getElementById('ktx-bn-thang').value,chi_da_duyet:document.getElementById('ktx-bn-duyet').checked?1:0},function(r){
      if(!r||!r.ok){ m.textContent=(r&&r.error)||'Lỗi.'; m.className='mut err'; return; }
      ktCsvTaiVe(r.aoa,r.fileName);
      var w=(r.thieuUnitId&&r.thieuUnitId.length)?(' · ⚠ '+r.thieuUnitId.length+' cơ sở thiếu Unit ID (dồn cuối)'):'';
      m.textContent=L('Đã tải: ','Downloaded: ')+r.soCoSo+' cơ sở · tổng '+ktVnd(r.tong)+'đ'+w; m.className='mut ok';
    });
  };
  document.getElementById('ktx-seed').onclick=function(){
    var m=document.getElementById('ktx-seed-msg');
    ktAct('kt_ma_misa_seed',{},m,function(){ ktxMaMisa(); });
  };
  ktxMaMisa();
}
function ktxMaMisa(){
  var box=document.getElementById('ktx-manop-wrap'); if(!box) return; box.textContent='';
  goi('kt_ma_misa_ds',{},function(r){
    box.textContent='';
    var sc=ktEl('div','table-scroll'); var tb=ktEl('table'); tb.style.minWidth='680px';
    tb.innerHTML='<tr><th>'+L('Cơ sở','Branch')+'</th><th>Unit ID</th><th>'+L('Tên MISA','MISA name')+'</th><th>'+L('Vùng','Region')+'</th><th>'+L('TT','#')+'</th><th></th></tr>';
    (r&&r.rows||[]).forEach(function(x){
      var tr=ktEl('tr'); tr.appendChild(ktEl('td',null,x.coso));
      function inp(v,w){ var i=document.createElement('input'); i.value=(v==null?'':v); i.style.width=(w||90)+'px'; return i; }
      var iU=inp(x.unit_id,90), iN=inp(x.unit_name,150), iV=inp(x.vung,110), iT=inp(x.thu_tu,50);
      [iU,iN,iV,iT].forEach(function(el){ var td=ktEl('td'); td.appendChild(el); tr.appendChild(td); });
      var td=ktEl('td'); var m=ktEl('span','mut'); var b=ktEl('button','on',L('Lưu','Save')); b.style.cssText='padding:4px 8px;font-size:12px';
      b.onclick=function(){ ktAct('kt_ma_misa_luu',{coso:x.coso,unit_id:iU.value,unit_name:iN.value,vung:iV.value,thu_tu:(iT.value||'').replace(/[^0-9]/g,'')||0},m,function(){}); };
      var bx=ktEl('button','ghost',L('Xoá','Del')); bx.style.cssText='padding:4px 8px;font-size:12px;margin-left:4px';
      bx.onclick=function(){ if(!confirm('Xoá '+x.coso+'?')) return; goi('kt_ma_misa_xoa',{coso_key:x.coso_key},function(){ ktxMaMisa(); }); };
      td.appendChild(b); td.appendChild(bx); td.appendChild(m); tr.appendChild(td); tb.appendChild(tr);
    });
    sc.appendChild(tb); box.appendChild(sc);
    if(!(r&&r.rows&&r.rows.length)) box.appendChild(ktEl('p','mut',L('Chưa có — bấm "Mồi từ danh mục ghế".','Empty — click Seed.')));
  });
}

/* ══════════════════════════════ NHẬP DOANH THU CŨ (dán CSV web cũ) ══════════════════════════
   Bảng xuất web THU TIỀN cũ. TIỀN chép nguyên (web cũ ×10000/đơn vị, hệ mới ×5000 — tính lại là
   sai). Parser theo RFC4180: ô có dấu " bọc dấu phẩy / xuống dòng / "" — vì cột `json` đầy dấu
   phẩy. Gộp 1 báo cáo / cơ sở / ngày ở server. */
var KTN_ROWS = null;   // đã parse, sẵn sàng gửi

function ktnParseCsv(text){
  /* Tự nhận cột ngăn bằng TAB (dán từ Google Sheets/Excel) hay dấu PHẨY (file .csv). Ô json có
     dấu phẩy nhưng không có tab → nếu dòng đầu có tab thì chắc chắn là TSV. */
  var firstLine=String(text).split('\n')[0]||'';
  var delim=(firstLine.indexOf('\t')>=0)?'\t':',';
  var rows=[], cur=[], val='', i=0, inQ=false, c, n=text.length;
  while(i<n){
    c=text[i];
    if(inQ){
      if(c==='"'){ if(text[i+1]==='"'){ val+='"'; i+=2; continue; } inQ=false; i++; continue; }
      val+=c; i++; continue;
    }
    if(c==='"'){ inQ=true; i++; continue; }
    if(c===delim){ cur.push(val); val=''; i++; continue; }
    if(c==='\r'){ i++; continue; }
    if(c==='\n'){ cur.push(val); rows.push(cur); cur=[]; val=''; i++; continue; }
    val+=c; i++;
  }
  if(val!==''||cur.length){ cur.push(val); rows.push(cur); }
  return rows.filter(function(r){ return r.length>1 || (r.length===1 && (r[0]||'').trim()!==''); });
}
function ktnNum(v){ var s=String(v==null?'':v).replace(/[^0-9-]/g,''); s=s.replace(/(?!^)-/g,''); return s===''||s==='-'?0:parseInt(s,10); }
/* Thứ tự cột chuẩn của bảng xuất web THU TIỀN cũ — dùng khi file/dán KHÔNG có dòng tiêu đề. */
var KTN_CANON=['date','locname','chairname','chaircode','staff','meterbefore','meterafter','actual',
  'cash','qr','adjust','total','cashsubmitstatus','cashpaidamount','cashpaiddate','note','reportid',
  'json','images','transferref','transferbank','proofimages'];
function ktnMapRows(aoa){
  if(!aoa.length) return {rows:[],err:L('File rỗng.','Empty.')};
  var head0=aoa[0].map(function(x){ return String(x||'').trim().toLowerCase(); });
  var coTieuDe = (head0.indexOf('date')>=0 && head0.indexOf('locname')>=0 && head0.indexOf('chaircode')>=0);
  var head = coTieuDe ? head0 : KTN_CANON;   // không có tiêu đề → coi theo thứ tự cột chuẩn
  var start = coTieuDe ? 1 : 0;
  var ix={}; head.forEach(function(h,j){ if(ix[h]==null) ix[h]=j; });
  function g(r,name){ var j=ix[name]; return (j==null||j>=r.length)?'':r[j]; }
  var out=[];
  for(var k=start;k<aoa.length;k++){
    var r=aoa[k]; if(!r||!r.length) continue;
    var loc=String(g(r,'locname')).trim(), date=String(g(r,'date')).trim(), code=String(g(r,'chaircode')).trim();
    if(!loc&&!date&&!code) continue;
    var method='', pstat=String(g(r,'cashsubmitstatus')).trim();
    var jc=String(g(r,'json')||'');
    /* Ưu tiên CỘT cashSubmitStatus (nhãn người: "Nộp thiếu (CK)" phân biệt được nộp thiếu) —
       json chỉ có token thô "paid_transfer" KHÔNG mã hoá "thiếu", nên chỉ dùng json khi cột rỗng.
       Riêng phương thức (cash/transfer) thì lấy từ json vì cột không có. */
    if(jc){ try{ var o=JSON.parse(jc); if(o){ if(o.cashPaymentMethod) method=String(o.cashPaymentMethod);
      if(!pstat&&o.cashSubmitStatus) pstat=String(o.cashSubmitStatus); } }catch(e){} }
    var imgs=String(g(r,'images')||'').split('|').map(function(s){return s.trim();}).filter(Boolean);
    out.push({
      date:date, loc:loc, chairCode:code, chairName:String(g(r,'chairname')).trim(), staff:String(g(r,'staff')).trim(),
      before:(String(g(r,'meterbefore')).trim()===''?null:ktnNum(g(r,'meterbefore'))),
      after:(String(g(r,'meterafter')).trim()===''?null:ktnNum(g(r,'meterafter'))),
      actual:ktnNum(g(r,'actual')), cash:ktnNum(g(r,'cash')), qr:ktnNum(g(r,'qr')),
      adjust:ktnNum(g(r,'adjust')), total:ktnNum(g(r,'total')),
      reportId:String(g(r,'reportid')).trim(), note:String(g(r,'note')).trim(),
      method:method, paidStatus:pstat, paidAmount:ktnNum(g(r,'cashpaidamount')),
      paidDate:String(g(r,'cashpaiddate')).trim(),
      ref:String(g(r,'transferref')).trim(), bank:String(g(r,'transferbank')).trim(),
      images:imgs
    });
  }
  return {rows:out,err:''};
}
function veKtNhap(){
  return '<div class="card"><h2>📥 ' + L('Nhập doanh thu cũ (CSV web cũ)','Import old revenue (CSV)') + '</h2>'
    + '<p class="mut">' + L('Chọn FILE .csv (nhanh nhất) hoặc dán bảng vào ô dưới. Có/không có dòng tiêu đề '
        + 'đều được — thiếu tiêu đề thì đọc theo thứ tự cột chuẩn. Tiền chép ĐÚNG như file — KHÔNG tính lại '
        + 'theo chỉ số (web cũ ×10.000, hệ mới ×5.000). Gộp 1 báo cáo mỗi cơ sở mỗi ngày.',
        'Pick a .csv file or paste below. Header row optional. Money copied verbatim — not recomputed. One report per branch/day.') + '</p>'
    + '<div class="act" style="flex-wrap:wrap;margin-bottom:8px">'
    + '<input type="file" id="ktn-file" accept=".csv,text/csv,text/plain">'
    + '<span id="ktn-file-msg" class="mut"></span></div>'
    + '<textarea id="ktn-csv" rows="8" style="width:100%;font-family:monospace;font-size:12px" '
    + 'placeholder="date,locName,chairName,chairCode,staff,meterBefore,meterAfter,actual,cash,qr,adjust,total,…"></textarea>'
    + '<p class="mut" style="margin-top:4px">' + L('Mặc định là BỔ SUNG: báo cáo cơ sở/ngày đã có thì chỉ '
        + 'điền thêm chỗ TRỐNG, không đè số đã nhập (nhập lại lần 2 không hỏng gì). Chỉ tick "Ghi đè hẳn" '
        + 'khi muốn thay hẳn báo cáo cũ.',
        'Default is SUPPLEMENT: existing reports only get blanks filled, never overwritten. Tick "Overwrite" to replace.') + '</p>'
    + '<div class="act" style="flex-wrap:wrap;margin-top:8px">'
    + '<button id="ktn-xem" class="ghost">' + L('Xem trước','Preview') + '</button>'
    + '<label class="mut"><input type="checkbox" id="ktn-taoghe" checked> ' + L('Thêm cơ sở &amp; ghế vào danh mục','Add branch &amp; chairs') + '</label>'
    + '<label class="mut"><input type="checkbox" id="ktn-ghide"> ' + L('Ghi đè hẳn (thay báo cáo cũ)','Overwrite existing') + '</label>'
    + '<label class="mut"><input type="checkbox" id="ktn-duyet"> ' + L('Đánh dấu đã duyệt','Mark confirmed') + '</label>'
    + '<button id="ktn-nap" class="on">' + L('Nhập vào hệ thống','Import') + '</button>'
    + '<span id="ktn-msg" class="mut"></span></div>'
    + '<div id="ktn-xem-kq" style="margin-top:10px"></div></div>';
}
function ktnInit(){
  var msg=document.getElementById('ktn-msg'), box=document.getElementById('ktn-xem-kq');
  function doiText(){ KTN_ROWS=null; }
  document.getElementById('ktn-csv').addEventListener('input',doiText);
  var fEl=document.getElementById('ktn-file');
  if(fEl) fEl.onchange=function(){
    var f=fEl.files&&fEl.files[0]; var fm=document.getElementById('ktn-file-msg');
    if(!f){ return; }
    fm.textContent=L('Đang đọc file…','Reading…'); fm.className='mut';
    var rd=new FileReader();
    rd.onerror=function(){ fm.textContent=L('Không đọc được file.','Read error.'); fm.className='mut err'; };
    rd.onload=function(){
      var t=String(rd.result||'');
      fm.textContent=f.name+' · '+Math.round(f.size/1024)+' KB';
      fm.className='mut ok';
      /* File to đổ vào ô soạn dễ treo trình duyệt — chỉ hiện lại khi nhỏ, còn lại xem trước thẳng. */
      document.getElementById('ktn-csv').value = (f.size<=300000) ? t : L('(File lớn đã nạp — xem bảng bên dưới)','(Large file loaded — see preview)');
      ktnPreview(t);
    };
    rd.readAsText(f);
  };
  function ktnPreview(t){
    t=String(t||'').trim();
    box.textContent=''; msg.textContent='';
    if(!t){ msg.textContent=L('Chưa có dữ liệu.','No data.'); msg.className='mut err'; KTN_ROWS=null; return; }
    var m=ktnMapRows(ktnParseCsv(t));
    if(m.err){ msg.textContent=m.err; msg.className='mut err'; KTN_ROWS=null; return; }
    if(!m.rows.length){ msg.textContent=L('Không có dòng dữ liệu nào.','No data rows.'); msg.className='mut err'; KTN_ROWS=null; return; }
    KTN_ROWS=m.rows;
    var cs={}, ngays={}, tm=0, qr=0, dmin='', dmax='';
    m.rows.forEach(function(x){ cs[x.loc]=1; ngays[x.date]=1; tm+=x.cash; qr+=x.qr;
      if(x.date){ if(!dmin||x.date<dmin)dmin=x.date; if(!dmax||x.date>dmax)dmax=x.date; } });
    box.appendChild(ktEl('div','ok', '✓ '+m.rows.length+' '+L('dòng ghế','chair rows')+' · '
      +Object.keys(cs).length+' '+L('cơ sở','branches')+' · '+Object.keys(ngays).length+' '+L('ngày','days')
      +(dmin?(' ('+dmin+' → '+dmax+')'):'')));
    box.appendChild(ktEl('div','mut', L('Tổng tiền mặt','Total cash')+' '+ktVnd(tm)+'đ · QR '+ktVnd(qr)+'đ'));
    var sc=ktEl('div','table-scroll'); var tb=ktEl('table'); tb.style.minWidth='720px';
    tb.innerHTML='<tr><th>'+L('Ngày','Date')+'</th><th>'+L('Cơ sở','Branch')+'</th><th>'+L('Ghế','Chair')
      +'</th><th class="r">'+L('CS trước','Meter before')+'</th><th class="r">'+L('CS sau','Meter after')
      +'</th><th class="r">'+L('T.mặt','Cash')+'</th><th class="r">QR</th><th class="r">'+L('Tổng','Total')+'</th></tr>';
    m.rows.slice(0,12).forEach(function(x){
      var tr=ktEl('tr');
      tr.appendChild(ktEl('td',null,x.date)); tr.appendChild(ktEl('td',null,x.loc));
      tr.appendChild(ktEl('td',null,x.chairCode));
      tr.appendChild(ktEl('td','r', x.before==null?'—':String(x.before)));
      tr.appendChild(ktEl('td','r', x.after==null?'—':String(x.after)));
      var a=ktEl('td','r',ktVnd(x.cash)), b=ktEl('td','r',ktVnd(x.qr)), c=ktEl('td','r',ktVnd(x.total));
      tr.appendChild(a); tr.appendChild(b); tr.appendChild(c); tb.appendChild(tr);
    });
    sc.appendChild(tb); box.appendChild(sc);
    if(m.rows.length>12) box.appendChild(ktEl('p','mut','… '+L('và','and')+' '+(m.rows.length-12)+' '+L('dòng nữa','more')));
    msg.textContent=L('Đã đọc xong — kiểm tra rồi bấm "Nhập vào hệ thống".','Parsed — review then Import.'); msg.className='mut ok';
  };
  document.getElementById('ktn-xem').onclick=function(){ ktnPreview(document.getElementById('ktn-csv').value); };
  document.getElementById('ktn-nap').onclick=function(){
    if(!KTN_ROWS){ ktnPreview(document.getElementById('ktn-csv').value); if(!KTN_ROWS) return; }
    if(!KTN_ROWS.length){ return; }
    var ghide=document.getElementById('ktn-ghide').checked;
    var taoghe=document.getElementById('ktn-taoghe').checked;
    if(!confirm(L('Nhập '+KTN_ROWS.length+' dòng ghế vào hệ thống?'+(ghide?'\n\n⚠ GHI ĐÈ HẲN: báo cáo cơ sở/ngày đã có sẽ bị THAY.':'\n\n(Bổ sung: chỉ điền chỗ trống, không đè số đã có.)'),
        'Import '+KTN_ROWS.length+' rows?'+(ghide?'\n\nOverwrite existing reports.':'\n\n(Supplement: fill blanks only.)')))) return;
    msg.textContent=L('Đang nhập…','Importing…'); msg.className='mut';
    goi('kt_import',{rows:KTN_ROWS,ghi_de:ghide?1:0,tao_ghe:taoghe?1:0,duyet:document.getElementById('ktn-duyet').checked?1:0},function(r){
      if(!r||!r.ok){ msg.textContent=(r&&r.error)||(r&&r.message)||'Lỗi.'; msg.className='mut err'; return; }
      msg.textContent='✓ '+r.message; msg.className='mut ok';
      box.textContent=''; box.appendChild(ktEl('div','ok',r.message));
      if(r.loi&&r.loi.length){ r.loi.forEach(function(e){ box.appendChild(ktEl('div','mut err','• '+e)); }); }
      box.appendChild(ktEl('p','mut',L('Gỡ lần nhập này ở tab Duyệt báo cáo → Nhật ký hoàn tác (mục "Nhập").',
        'Undo this import under Review → Undo log.')));
      KTN_ROWS=null;
    });
  };
}

function veCauHinh(){
  if (!CH) {
    goi('ch_xem', {}, function(r){
      if (!r.ok) { alert(r.error || L('Không tải được cấu hình.','Could not load settings.')); return; }
      CH = r; ve();
    });
    return '<div class="card"><p class="mut">' + L('Đang tải…','Loading…') + '</p></div>';
  }

  var h = '';
  if (CH.nguon !== 'rieng') {
    h += '<div class="warn"><b>' + L('Đang dùng danh sách người dùng của plugin Chi phí',
        'Using the Expenses plugin user list') + '</b> — '
      + L('thêm/xoá người ở đây sẽ <b>không có tác dụng</b>. Vào wp-admin → Ghế Massage → Trang '
          + 'ngoài, chọn <b>Danh sách riêng của plugin này</b> trước.',
          'adding or removing people here will have <b>no effect</b>. Go to wp-admin → Massage '
          + 'Chairs → Public page and pick <b>this plugin\'s own list</b> first.') + '</div>';
  }

  /* ---- 1. NGƯỜI DÙNG ---------------------------------------------------------------------- */
  h += '<div class="card"><h2>' + L('Nhân sự','People') + '</h2><table><tr><th>'
    + L('Tên','Name') + '</th><th>' + L('Vai trò','Role') + '</th><th>' + L('Cơ sở','Branch')
    + '</th><th class="hide-sm">PIN</th><th class="r"></th></tr>';
  if (!(CH.nguoi || []).length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa khai ai cả.','Nobody declared yet.') + '</td></tr>';
  /* 🔴 NÓI THẲNG AI ĐANG KHÔNG VÀO ĐƯỢC.
   *
   * Anh Thắng 23/08/2026: *"chưa thấy nhân viên chốt báo cáo ca"* — và ảnh chụp cho thấy ô
   * "Nhân viên" ở nhóm *Đăng nhập được trang này* chưa tích. Người đó đã khai đủ tên, PIN, cơ
   * sở, nằm ngay trong bảng, trông y như đã xong — mà gõ đúng PIN vẫn bị đá ra.
   *
   * Bảng chỉ liệt kê tên và vai trò thì không ai nối được hai chỗ đó với nhau: danh sách người
   * ở khối này, danh sách vai trò ở khối dưới. Nên đánh dấu NGAY TRÊN DÒNG người đó.
   */
  var ai_khong_vao = [];
  (CH.nguoi || []).forEach(function(n){
    var vao = (CH.vao || []).indexOf(n.vai_tro) >= 0;
    if (!vao) ai_khong_vao.push(n.ten);
    h += '<tr><td><b>' + esc(n.ten) + '</b>'
      + (vao ? '' : '<br><span class="pill p-off">' + L('không đăng nhập được','cannot sign in') + '</span>')
      + '</td><td>' + esc(n.vai_tro) + '</td>'
      + '<td>' + esc(n.coso || L('cả chuỗi','all branches')) + '</td>'
      + '<td class="hide-sm mut">' + n.pin_dai + ' ' + L('số','digits') + '</td>'
      + '<td class="r" style="white-space:nowrap">'
      + (n.la_ban ? '<span class="mut" style="margin-right:6px">' + L('bạn','you') + '</span>' : '')
      + '<button data-chxoa="' + n.i + '"' + (n.la_ban ? ' data-chban="1"' : '')
        + ' data-chten="' + esc(n.ten) + '" class="ghost">' + L('Xoá','Remove') + '</button>'
      + '</td></tr>';
  });
  h += '</table>';
  if (ai_khong_vao.length) {
    h += '<div class="warn" style="margin-top:10px"><b>' + ai_khong_vao.length + ' '
      + L('người gõ đúng PIN vẫn không vào được','people cannot sign in even with the right PIN')
      + '</b> — ' + esc(ai_khong_vao.join(', ')) + '. '
      + L('Vai trò của họ chưa được tích ở khối <b>Phân quyền → Đăng nhập được trang này</b> ngay '
          + 'bên dưới. Tích rồi bấm <b>Lưu phân quyền</b>.',
          'Their role is not ticked under <b>Permissions → Can sign in here</b> below. '
          + 'Tick it, then press <b>Save permissions</b>.')
      + '</div>';
  }

  h += '<h3 style="margin:16px 0 8px">' + L('Thêm người','Add a person') + '</h3>'
    + '<div class="act" style="flex-wrap:wrap">'
    + '<input id="ch-ten" type="text" placeholder="' + L('Họ tên','Full name') + '" style="flex:2;min-width:150px">'
    + '<input id="ch-pin" type="tel" inputmode="numeric" placeholder="PIN 4–8 ' + L('số','digits')
      + '" style="flex:1;min-width:110px">'
    + '<select id="ch-vt" style="flex:1;min-width:130px">'
    + (CH.vai_tro || []).map(function(v){
        return '<option value="' + esc(v) + '"' + (v === 'Nhân viên' ? ' selected' : '') + '>'
          + esc(v) + '</option>'; }).join('')
    + '</select>'
    + '<select id="ch-cs" style="flex:1;min-width:130px"><option value="">— '
      + L('cả chuỗi','all branches') + ' —</option>'
    + (CH.coso || []).map(function(c){ return '<option value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('')
    + '</select>'
    + '<button id="ch-them" class="on">' + L('Thêm','Add') + '</button></div>'
    /* Nói rõ CƠ SỞ quyết định gì — người khai không nhìn thấy hậu quả của ô này ở đâu khác. */
    + '<p class="mut" style="margin-top:10px">'
    + L('<b>Cơ sở</b> quyết định người đó chốt ca được ở đâu: gán cơ sở thì chỉ chốt được ghế của '
        + 'cơ sở đó. Chốt nhầm ghế ở cơ sở khác không chỉ ghi sai sổ — nó <b>đóng mốc chỉ số</b> '
        + 'của ghế đó, và người thu thật ở đấy hôm sau sẽ thấy quãng bị cắt mất.<br>'
        + 'Quên PIN thì xoá người đó rồi thêm lại — màn này <b>không in PIN</b> ra bao giờ.',
        '<b>Branch</b> decides which chairs that person can close: assign a branch and they can '
        + 'only close chairs there. Closing the wrong chair does not just mis-record — it '
        + '<b>seals the meter baseline</b> of that chair.<br>'
        + 'Forgot a PIN? Remove the person and add them again — this screen never prints PINs.')
    + '</p><div class="err" id="ch-e"></div></div>';

  /* ---- 2. PHÂN QUYỀN ---------------------------------------------------------------------- */
  var nhom = [
    ['vao',  L('Đăng nhập được trang này','Can sign in here'),
             L('Không tích = gõ đúng PIN vẫn không vào được.','Unticked = correct PIN still cannot enter.')],
    ['giup', L('Giúp khách (bật ghế, tiêu ví hộ)','Help customers (start chairs, spend wallet)'),
             L('Bạn trực Hotline: khách gọi tới vì ghế không chạy. Không thấy doanh thu.',
               'The hotline staff: customers call because a chair will not start. No revenue access.')],
    ['chot', L('Chốt doanh số (nhận tiền nhân viên nộp)','Close revenue (receive staff hand-ins)'),
             L('Kế toán xuống nhận tiền. Không kèm quyền huỷ mã hay gán ghế.',
               'The accountant receiving the cash. Does not include cancelling codes or assigning chairs.')],
    ['quantri', L('Quản trị (thêm/xoá cơ sở & ghế, cấp PIN báo cáo)',
                  'Manage (add/remove sites & chairs, issue report PINs)'),
             L('Vận hành cả chuỗi: thêm/xoá/sửa cơ sở & ghế, gán/huỷ mã, xem doanh thu toàn chuỗi, '
               + 'và cấp PIN báo cáo cho nhân viên mình quản lý. <b>KHÔNG</b> kèm quyền khai nhân sự '
               + 'hay sửa chính bảng phân quyền này — hai việc đó vẫn chỉ Admin.',
               'Chain-wide operations: add/remove/edit sites & chairs, assign/cancel codes, see '
               + 'chain-wide revenue, and issue report PINs to staff you manage. Does <b>not</b> '
               + 'include managing users or editing this permission table — both stay Admin-only.')]
  ];
  h += '<div class="card"><h2>' + L('Phân quyền','Permissions') + '</h2>';
  nhom.forEach(function(g){
    h += '<div class="ph-nhom"><div class="nh">' + g[1] + '</div>'
      + '<div class="mut" style="margin:2px 0 8px">' + g[2] + '</div><div class="ph-o">';
    (CH.vai_tro || []).forEach(function(v){
      var co = (CH[g[0]] || []).indexOf(v) >= 0;
      var la_admin = (v === 'Admin');
      h += '<label class="ph-tick' + (la_admin ? ' khoa' : '') + '">'
        + '<input type="checkbox" data-ph="' + g[0] + '" value="' + esc(v) + '"'
        + (co ? ' checked' : '') + (la_admin ? ' disabled' : '') + '> ' + esc(v)
        + (la_admin ? ' <span class="mut">(' + L('luôn có','always') + ')</span>' : '') + '</label>';
    });
    h += '</div></div>';
  });
  h += '<button id="ch-luu-vt" class="on" style="width:100%;margin-top:12px">'
    + L('Lưu phân quyền','Save permissions') + '</button>'
    /* ⚠️ Admin bị khoá ở cả ba nhóm — bỏ sót Admin là tự khoá mình ra khỏi chính màn này. */
    + '<p class="mut" style="margin-top:8px">'
    + L('Admin luôn có đủ bốn quyền — bỏ sót là tự khoá mình ra khỏi chính màn này, và không có '
        + 'đường tự mở lại ngoài cơ sở dữ liệu. Nhóm <b>Quản trị</b> chưa khai bao giờ = Admin + '
        + 'Quản lý (giữ như cũ); tích thêm vai trò để trao quyền vận hành cho họ.',
        'Admin always keeps all four — dropping it locks you out of this very screen, with no '
        + 'way back except the database. The <b>Manage</b> group, if never set, defaults to Admin '
        + '+ Manager; tick more roles to grant them operational rights.')
    + '</p><div class="err" id="ch-e2"></div></div>';

  /* ---- 3. CHỈ SỐ MÁY ĐẾM ------------------------------------------------------------------ */
  h += '<div class="card"><h2>' + L('Chỉ số máy đếm tiền','Note counter unit') + '</h2>'
    + '<div class="act">' + L('Mỗi <b>1 đơn vị</b> trên màn đếm =','Each <b>1 unit</b> on the counter =')
    + '<input id="ch-dv" type="tel" inputmode="numeric" value="' + esc(String(CH.don_vi))
    + '" style="width:120px;text-align:right"> đ'
    + '<button id="ch-luu-dv" class="on">' + L('Lưu','Save') + '</button></div>'
    + '<p class="mut" style="margin-top:10px">'
    + L('<b>Cách kiểm:</b> nhét một tờ ' + tien(CH.don_vi) + ' vào máy và xem màn đếm nhảy đúng '
        + '<b>1</b> đơn vị hay không. Nhảy 2 thì khai lại một nửa; hiện thẳng số tiền thì khai '
        + '<b>1</b>.<br>Khai sai là <b>mọi</b> lượt chốt ca sai theo cùng một hệ số — và nó sai '
        + 'một cách rất giống thật: bảng vẫn đầy số, vẫn cộng ra tổng.',
        '<b>How to check:</b> insert one ' + tien(CH.don_vi) + ' note and see whether the counter '
        + 'advances by exactly <b>1</b>. If it jumps 2, halve the value; if it shows the amount '
        + 'itself, enter <b>1</b>.<br>Get this wrong and <b>every</b> closing is wrong by the same '
        + 'factor — and it looks entirely plausible.')
    + '</p><div class="err" id="ch-e3"></div></div>';

  return h;
}

function veQuy(){
  var q = (D.quy || null);
  if (!q) return '<div class="card"><p class="mut">'
    + L('Chưa có số liệu quỹ.','No cash-float data.') + '</p></div>';

  var h = '<div class="kpis">'
    + kpi(L('Đang trên tay nhân viên','Held by staff'), tien(q.tong.tren_tay),
        L('chưa nộp về quầy','not handed in yet'), q.tong.tren_tay > 0 ? 'c' : 'd')
    + kpi(L('Chờ xác nhận','Awaiting confirmation'), tien(q.tong.cho_xac_nhan),
        q.tong.so_cho + ' ' + L('lượt nộp','hand-ins'), q.tong.so_cho > 0 ? 'a' : 'd')
    + kpi(L('Đã chốt trong kỳ','Collected this period'), tien(q.tong.chot_ky), '', 'b')
    + kpi(L('Sổ thiếu so với máy đếm','Missing vs meter'), tien(q.tong.lech_may),
        L('ghế nuốt mà không báo về được','swallowed but never reported'),
        q.tong.lech_may > 0 ? 'c' : 'd')
    + '</div>';

  /* ---- 0. CHỐT CA — LỐI VÀO CHÍNH CỦA NGƯỜI THU -------------------------------------------
   * 🔴 Người thu KHÔNG còn tab "Điều khiển ghế" (chỉ Admin/Quản lý), mà nút chốt ca vốn nằm ở
   *    đó. Không đưa lối vào lên đây thì cả vai trò ấy mở app ra và không bấm được gì cả.
   * ⚠️ Ghế mất kết nối vẫn chốt được, và phải nói ra: ghế mất mạng thì sổ ghi nhận thiếu so với
   *    máy đếm là CHUYỆN BÌNH THƯỜNG, không phải mất tiền — người thu cần biết trước khi hoảng.
   */
  h += '<div class="card"><h2>' + L('Chốt ca — quét QR trên ghế','Close a shift — scan the chair QR')
    + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Mở ngăn ghế, đọc chỉ số trên màn máy đếm, đếm tiền — rồi <b>quét mã QR dán trên chính '
        + 'cái ghế đó</b>.',
        'Open the cash box, read the note counter, count the cash — then <b>scan the QR sticker '
        + 'on that very chair</b>.')
    + '</p>'
    + '<button id="quet-mo" class="on" style="width:100%;padding:14px;font-size:16px">📷 '
    + L('Quét mã QR trên ghế','Scan the chair QR') + '</button>'
    /* ⚠️ LUÔN CÓ ĐƯỜNG GÕ TAY. Tem bong, tem mờ, máy không có camera, người dùng từ chối quyền
       camera — bốn chuyện đều xảy ra thật, và không có lối thứ hai thì người thu đứng đó với
       một ngăn tiền đã mở mà không ghi sổ được. */
    + '<div class="act" style="margin-top:10px">'
    + '<input id="quet-tay" type="text" placeholder="' + L('hoặc gõ mã ghế: AMTP01','or type the chair code')
    + '" autocapitalize="characters" autocomplete="off" style="flex:1">'
    + '<button id="quet-di">' + L('Chốt','Go') + '</button></div>'
    + '<div class="err" id="quet-e"></div>'
    + '<div id="quet-khung"></div></div>';

  /* ---- 1. BÁO CÁO CA + TÔI ĐANG CẦM -------------------------------------------------------
   * Anh Thắng 23/08/2026: *"chưa thấy nhân viên chốt báo cáo ca"*.
   *
   * 🔴 "CA" LÀ QUÃNG CHƯA NỘP, KHÔNG PHẢI "HÔM NAY". Người thu đi một vòng nhiều ghế rồi nộp
   *    một lần — quãng đó mới là cái họ phải giải trình, và nó vắt qua nửa đêm được (ca tối
   *    đóng cửa lúc 1 giờ sáng). Cắt theo ngày là ca đêm bị chẻ đôi, và cả hai nửa đều không
   *    khớp với xấp tiền đang cầm.
   * ⚠️ Liệt kê CẢ TỪNG GHẾ, không chỉ con số tổng: lệch quỹ thì câu hỏi đầu tiên luôn là
   *    "ghế nào", mà một con số tổng không trả lời được câu đó.
   */
  var ca = q.ca || null;
  if (ca && ca.so_ghe > 0) {
    h += '<div class="card"><h2>' + L('Báo cáo ca','Shift report') + ' — ' + esc(q.toi_la) + '</h2>'
      + '<p class="mut" style="margin:0 0 10px">'
      + Lf('Từ {0}, đã chốt {1} ghế. Ca tính từ lần nộp gần nhất — chưa nộp thì vẫn là ca này.',
           'Since {0}, {1} chairs closed. The shift runs from your last hand-in.',
           esc(String(ca.tu_luc).slice(0, 16)), ca.so_ghe)
      + '</p>'
      + '<div class="so-hang to"><span class="nh">' + L('Tiền đếm được từ ngăn ghế','Counted from chair boxes')
        + '</span><span class="gt">' + tien(ca.tien_dem) + '</span></div>'
      + hangSo(L('Máy đếm nói đã nuốt','Meters say they took'), tien(ca.theo_may))
      + hangSo(L('Sổ ghi nhận','On record'), tien(ca.theo_he_thong))
      + (ca.tu_quay > 0 ? hangSo(L('Khách trả tại quầy','Paid at the counter'), tien(ca.tu_quay)) : '');
    /* Hai con lệch chỉ hiện khi KHÁC 0 — hiện cả hai dòng 0đ mỗi ca là mắt bỏ qua cả hai. */
    if (ca.lech_dem !== 0) {
      h += '<div class="so-hang"><span class="nh" style="color:#ff8087">'
        + (ca.lech_dem < 0 ? L('Ngăn THIẾU so với máy đếm','Boxes SHORT vs meters')
                           : L('Ngăn THỪA so với máy đếm','Boxes OVER vs meters'))
        + '</span><span class="gt" style="color:#ff8087">' + tien(Math.abs(ca.lech_dem))
        + '</span></div>';
    }
    if (ca.lech_may !== 0) {
      h += '<div class="so-hang"><span class="nh" style="color:#ffb86b">'
        + L('Sổ thiếu so với máy đếm','Records short vs meters')
        + '</span><span class="gt" style="color:#ffb86b">' + tien(Math.abs(ca.lech_may))
        + '</span></div>'
        + '<div class="mut" style="margin-top:4px">'
        + L('Ghế nuốt tiền mà không báo về được (mất mạng, mất điện giữa chừng). Tiền vẫn trong '
            + 'ngăn — báo quản lý để đối chiếu, đừng tự bù.',
            'A chair took notes but could not report them (offline, power cut). The money is '
            + 'still in the box — tell a manager, do not cover it yourself.') + '</div>';
    }
    h += '<table style="margin-top:12px"><tr><th>' + L('Ghế','Chair') + '</th>'
      + '<th class="hide-sm">' + L('Lúc','Time') + '</th>'
      + '<th class="r">' + L('Chỉ số','Meter') + '</th>'
      + '<th class="r">' + L('Đếm được','Counted') + '</th></tr>';
    (ca.ds || []).forEach(function(c){
      h += '<tr><td><b>' + esc(c.ma_may) + '</b>'
        + (c.lan_dau ? ' <span class="pill p-run">' + L('lần đầu','first') + '</span>' : '')
        + '</td><td class="hide-sm mut">' + esc(String(c.tao_luc).slice(11, 16)) + '</td>'
        + '<td class="r mut">' + esc(String(c.chi_so_truoc)) + ' → ' + esc(String(c.chi_so)) + '</td>'
        + '<td class="r"' + (c.lech_dem !== 0 ? ' style="color:#ff8087"' : '') + '><b>'
          + tien(c.tien_dem) + '</b></td></tr>';
    });
    h += '</table></div>';
  }

  var toi = q.toi || { tong: 0, tu_ghe: 0, tu_quay: 0, so_dong: 0 };
  h += '<div class="card"><h2>' + L('Tôi đang cầm','I am holding') + ' — ' + esc(q.toi_la) + '</h2>';
  if (toi.tong > 0) {
    h += '<div class="so-hang to"><span class="nh">' + L('Tổng phải nộp','Total to hand in')
      + '</span><span class="gt">' + tien(toi.tong) + '</span></div>'
      + hangSo(L('Lấy từ ngăn ghế','From chair cash boxes'), tien(toi.tu_ghe))
      + hangSo(L('Khách trả tại quầy','Paid at the counter'), tien(toi.tu_quay))
      + '<div class="act" style="margin-top:12px">'
      + '<input id="nop-gc" type="text" placeholder="'
      + L('ghi chú (không bắt buộc)','note (optional)') + '" style="flex:1">'
      + '<button id="nop-ok" class="on">' + L('Nộp về quầy','Hand in') + '</button></div>'
      /* ⚠️ Nói rõ nộp là nộp HẾT, không nộp một phần. Nộp một phần thì con số "đang cầm" thành
         thứ người nộp tự chọn, và cái sổ này thôi không kiểm được gì nữa. */
      + '<div class="canh" style="margin-top:10px">'
      + L('Bấm Nộp là nộp <b>toàn bộ</b> ' + tien(toi.tong) + ' đang cầm (' + toi.so_dong
          + ' lượt). Quản lý đếm lại rồi xác nhận — con số hai bên đều được giữ trong sổ.',
          'Handing in covers <b>all</b> ' + tien(toi.tong) + ' you hold (' + toi.so_dong
          + ' entries). A manager counts it and confirms; both figures stay on the record.')
      + '</div>';
  } else {
    h += '<p class="mut">' + L('Không cầm đồng nào chưa nộp.','Nothing outstanding.') + '</p>';
  }
  h += '<div class="err" id="nop-e"></div></div>';

  /* ---- 2. CHỜ XÁC NHẬN -------------------------------------------------------------------- */
  if ((q.cho || []).length) {
    h += '<div class="card"><h2>' + L('Lượt nộp chờ xác nhận','Hand-ins awaiting confirmation')
      + '</h2>';
    if (!q.quyen_nhan) {
      h += '<p class="mut">' + L('Chỉ Admin hoặc Quản lý mới xác nhận được.',
                                 'Only an Admin or Manager can confirm these.') + '</p>';
    }
    h += '<table><tr><th>' + L('Lúc','Time') + '</th><th>' + L('Ai nộp','From') + '</th>'
      + '<th class="r">' + L('Sổ ghi','On record') + '</th><th class="r">'
      + L('Đếm lại được','Counted') + '</th></tr>';
    q.cho.forEach(function(n){
      h += '<tr><td>' + esc(n.tao_luc) + '</td><td><b>' + esc(n.nguoi) + '</b>'
        + (n.ghi_chu ? '<br><span class="mut">' + esc(n.ghi_chu) + '</span>' : '') + '</td>'
        + '<td class="r"><b>' + tien(n.so_tien) + '</b><br><span class="mut">'
        + n.so_dong + ' ' + L('lượt','entries') + '</span></td>'
        + '<td class="r">';
      if (q.quyen_nhan) {
        h += '<div class="act" style="justify-content:flex-end">'
          + '<input type="text" inputmode="numeric" data-nhan-so="' + n.id + '" value="'
          + esc(String(n.so_tien)) + '" style="width:110px;text-align:right">'
          + '<button data-nhan="' + n.id + '" class="on">' + L('Đã nhận','Received') + '</button>'
          + '<button data-nophuy="' + n.id + '" class="ghost">' + L('Huỷ','Cancel') + '</button>'
          + '</div>';
      } else { h += '<span class="mut">—</span>'; }
      h += '</td></tr>';
    });
    h += '</table></div>';
  }

  /* ---- 3. AI ĐANG CẦM — CHỈ QUẢN TRỊ -------------------------------------------------------
   * 🔴 Người thu không nhận số liệu này từ máy chủ (xem `so_lieu_nhan_vien`). Vẫn vẽ khung ra
   *    thì bảng rỗng sẽ nói "không ai đang cầm tiền" — mà đó là NÓI SAI: có người cầm, họ chỉ
   *    không được xem thôi. Một bảng rỗng trông hệt như một sự thật, và đó là kiểu nói dối tệ
   *    nhất vì không ai nghi ngờ nó.
   */
  if (QUAN_TRI() || CHOT_DS()) {
  h += '<div class="card"><h2>' + L('Ai đang cầm tiền','Who is holding cash') + '</h2><table><tr><th>'
    + L('Người','Person') + '</th><th class="r">' + L('Từ ngăn ghế','Chair boxes') + '</th>'
    + '<th class="r">' + L('Tại quầy','Counter') + '</th><th class="r">'
    + L('Tổng','Total') + '</th></tr>';
  if (!(q.cam || []).length) h += '<tr><td colspan="4" class="mut">'
    + L('Không ai đang cầm tiền chưa nộp.','Nobody is holding uncollected cash.') + '</td></tr>';
  (q.cam || []).forEach(function(c){
    h += '<tr><td><b>' + esc(c.nguoi) + '</b></td>'
      + '<td class="r">' + tien(c.tu_ghe) + '</td><td class="r">' + tien(c.tu_quay) + '</td>'
      + '<td class="r"><b>' + tien(c.tong) + '</b></td></tr>';
  });
  h += '</table></div>';

  }

  /* ---- 4. LƯỢT CHỐT CA — cả hai vai trò đều xem ------------------------------------------
     Người thu chỉ nhận về lượt của CHÍNH MÌNH; máy chủ đã lọc, giao diện không phải lọc lại. */
  h += '<div class="card"><h2>'
    + ((QUAN_TRI() || CHOT_DS()) ? L('Lượt chốt ca','Shift closings')
        : L('Lượt chốt ca của tôi','My shift closings'))
    + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Mỗi đơn vị trên màn đếm của máy tiền mặt = <b>' + tien(q.don_vi) + '</b>. '
        + 'Khai lại ở wp-admin → Ghế Massage → Máy &amp; cơ sở.',
        'One unit on the note counter display = <b>' + tien(q.don_vi) + '</b>. '
        + 'Change it in wp-admin → Massage Chairs → Machines &amp; branches.')
    + '</p><table><tr><th>' + L('Lúc','Time') + '</th><th>' + L('Ghế','Chair') + '</th>'
    + '<th class="hide-sm">' + L('Ai chốt','By') + '</th>'
    + '<th class="r">' + L('Chỉ số','Meter') + '</th>'
    + '<th class="r">' + L('Máy đếm','Meter says') + '</th>'
    + '<th class="r">' + L('Sổ ghi','On record') + '</th>'
    + '<th class="r">' + L('Đếm được','Counted') + '</th></tr>';
  if (!(q.chot || []).length) h += '<tr><td colspan="7" class="mut">'
    + L('Chưa có lượt chốt nào trong kỳ này.','No closings in this period.') + '</td></tr>';
  (q.chot || []).forEach(function(c){
    var do_dem = c.lech_dem !== 0, do_may = c.lech_may !== 0;
    h += '<tr><td>' + esc(c.tao_luc) + '</td><td><b>' + esc(c.ma_may) + '</b>'
      + (c.lan_dau ? ' <span class="pill p-run">' + L('lần đầu','first') + '</span>' : '')
      + '</td><td class="hide-sm">' + esc(c.nguoi) + '</td>'
      + '<td class="r"><span class="mut">' + esc(String(c.chi_so_truoc)) + ' →</span> '
        + esc(String(c.chi_so)) + '</td>'
      + '<td class="r">' + (c.lan_dau ? '—' : tien(c.theo_may)) + '</td>'
      + '<td class="r"' + (do_may ? ' style="color:#ff8087"' : '') + '>' + tien(c.theo_he_thong) + '</td>'
      + '<td class="r"' + (do_dem ? ' style="color:#ff8087"' : '') + '><b>' + tien(c.tien_dem) + '</b></td>'
      + '</tr>';
    if (c.canh_bao) {
      h += '<tr><td colspan="7" class="mut" style="color:#ffb86b;padding-top:0">⚠️ '
        + esc(c.canh_bao) + (c.ghi_chu ? ' · ' + esc(c.ghi_chu) : '') + '</td></tr>';
    }
  });
  h += '</table></div>';

  /* ---- 5. THEO NGƯỜI — chỉ quản trị, cùng lý do với bảng 3. -------------------------------- */
  if (!QUAN_TRI() && !CHOT_DS()) return h;
  h += '<div class="card"><h2>' + L('Theo người thu','By collector') + '</h2><table><tr><th>'
    + L('Người','Person') + '</th><th class="r">' + L('Từ ngăn ghế','Chair boxes') + '</th>'
    + '<th class="r">' + L('Tại quầy','Counter') + '</th>'
    + '<th class="r">' + L('Đã nộp','Handed in') + '</th>'
    + '<th class="r">' + L('Còn cầm','Still holding') + '</th>'
    + '<th class="r hide-sm">' + L('Lệch ngăn','Box diff') + '</th>'
    + '<th class="r hide-sm">' + L('Lệch nộp','Hand-in diff') + '</th></tr>';
  if (!(q.nguoi || []).length) h += '<tr><td colspan="7" class="mut">'
    + L('Chưa có ai thu tiền trong kỳ này.','Nobody collected cash in this period.') + '</td></tr>';
  (q.nguoi || []).forEach(function(n){
    h += '<tr><td><b>' + esc(n.nguoi) + '</b></td>'
      + '<td class="r">' + tien(n.tu_ghe) + '</td><td class="r">' + tien(n.tu_quay) + '</td>'
      + '<td class="r">' + tien(n.da_nop) + '</td>'
      + '<td class="r"' + (n.dang_cam > 0 ? ' style="color:#ffb86b"' : '') + '><b>'
        + tien(n.dang_cam) + '</b></td>'
      + '<td class="r hide-sm"' + (n.lech_dem !== 0 ? ' style="color:#ff8087"' : '') + '>'
        + tien(n.lech_dem) + '</td>'
      + '<td class="r hide-sm"' + (n.lech_nop !== 0 ? ' style="color:#ff8087"' : '') + '>'
        + tien(n.lech_nop) + '</td></tr>';
  });
  return h + '</table></div>';
}

/* Chỉ mở lại module bc-app đã có (`moBaoCao()` trong js_baocao(), lộ ra qua `window.VHG_BaoCao`)
   — không tự làm lại màn nhập báo cáo ở đây. Vẫn đòi PIN báo cáo riêng như khi bấm từ màn đăng
   nhập; tab này chỉ đỡ việc phải Thoát rồi bấm lại nút ở màn đăng nhập cho ai lỡ vào thẳng
   trang chính bằng token /ghe. */
function veBcDoanhThu(){
  return '<div class="card"><h2>📋 ' + L('Báo cáo doanh thu','Revenue report') + '</h2>'
    + '<p class="mut">' + L('Chọn cơ sở, nhập chỉ số/tiền mặt/QR cho từng ghế — không cần quét mã QR '
        + 'trên ghế để chốt ca.', 'Pick a branch, enter meter/cash/QR readings per chair — no need to '
        + 'scan the QR on the chair.') + '</p>'
    + '<button id="bc-mo-tai-day" class="on">' + L('Mở màn Báo cáo doanh thu','Open the revenue report screen')
    + '</button></div>';
}

function veThuTien(){
  var t = (D.thu || { ds: [], may: [] });
  var mat_ghe = 0, mat_ng = 0, qr = 0, lan = 0, canh = [];
  t.may.forEach(function(m){
    mat_ghe += m.mat_ghe; mat_ng += m.mat_nguoi; qr += m.qr; lan += m.so_lan_thu;
    if (m.cong_doi) canh.push(m.may);
  });

  var h = '';
  if (canh.length) {
    h += '<div class="warn"><b>' + canh.length + ' '
      + L('ghế có CẢ hai đường tiền mặt trong kỳ này','chairs took cash through BOTH paths this period')
      + '</b> — ' + esc(canh.join(', ')) + '. '
      + L('Ghế vừa tự nuốt tiền, vừa có người bấm "Thu tiền mặt". Nếu đó là cùng một xấp tiền thì '
          + 'doanh thu đang <b>cộng đôi</b>. Ghế mới lắp cục nhận tiền giữa kỳ thì bình thường — '
          + 'soi bảng dưới rồi huỷ dòng thừa ở tab Đối soát.',
          'The chair swallowed notes itself AND someone pressed "Collect cash". If that is the same '
          + 'pile of money, revenue is <b>double counted</b>. It is normal if the acceptor was '
          + 'fitted mid-period — check the table below and cancel the extra rows in Reconciliation.')
      + '</div>';
  }

  h += '<div class="kpis">'
    + kpi(L('Ghế tự nuốt tiền','Chair took notes'), tien(mat_ghe),
        L('khách nhét vào máy','customer inserted'), 'c')
    + kpi(L('Người đi thu ghi sổ','Recorded by staff'), tien(mat_ng),
        lan + ' ' + L('lần bấm thu','collections'), 'a')
    + kpi(L('Chuyển khoản (QR)','Bank transfer (QR)'), tien(qr), '', 'b')
    + kpi(L('Tổng kỳ này','Period total'), tien(mat_ghe + mat_ng + qr), '', 'd')
    + '</div>';

  h += '<div class="card"><h2>' + L('Theo ghế','By chair') + '</h2><table><tr><th>'
    + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch') + '</th>'
    + '<th class="r">' + L('Ghế nuốt','Acceptor') + '</th><th class="r">'
    + L('Người thu','Staff') + '</th><th class="r">QR</th><th class="r">'
    + L('Tổng','Total') + '</th></tr>';
  if (!t.may.length) h += '<tr><td colspan="6" class="mut">'
    + L('Chưa có đồng nào trong kỳ này.','No money in this period.') + '</td></tr>';
  t.may.forEach(function(m){
    h += '<tr><td><b>' + esc(m.may) + '</b>'
      + (m.cong_doi ? ' <span class="pill p-off">' + L('cần soi','check') + '</span>' : '') + '</td>'
      + '<td class="hide-sm">' + esc(m.coso || '—') + '</td>'
      + '<td class="r">' + tien(m.mat_ghe) + '</td>'
      + '<td class="r">' + tien(m.mat_nguoi) + '</td>'
      + '<td class="r">' + tien(m.qr) + '</td>'
      + '<td class="r"><b>' + tien(m.tong) + '</b></td></tr>';
  });
  h += '</table></div>';

  h += '<div class="card"><h2>' + L('Từng lượt tiền mặt','Every cash entry') + '</h2>'
    + '<div id="tm-wrap"></div></div>';
  return h;
}

/* Từng lượt tiền mặt (tab Thu tiền): 20 lượt/trang — vẽ tại chỗ vào #tm-wrap. */
function tmRender(){
  var box = document.getElementById('tm-wrap'); if (!box) return;
  var ds = ((D && D.thu && D.thu.ds) || []);
  var pages = Math.max(1, Math.ceil(ds.length / 20));
  if (TM_PG >= pages) TM_PG = pages - 1; if (TM_PG < 0) TM_PG = 0;
  var from = TM_PG * 20, to = Math.min(ds.length, from + 20);
  var h = '<table><tr><th>' + L('Lúc','Time') + '</th><th>' + L('Ghế','Chair') + '</th><th>'
    + L('Kiểu','Kind') + '</th><th class="hide-sm">' + L('Ai thu','Collected by') + '</th><th class="r">'
    + L('Số tiền','Amount') + '</th></tr>';
  if (!ds.length) h += '<tr><td colspan="5" class="mut">' + L('Chưa có lượt tiền mặt nào.','No cash entries yet.') + '</td></tr>';
  for (var i = from; i < to; i++){ var r = ds[i]; var ng = r.kieu === 'nguoi';
    h += '<tr><td>' + esc(r.luc) + '</td><td><b>' + esc(r.ma_may || '—') + '</b></td>'
      + '<td><span class="pill ' + (ng ? 'p-run' : 'p-ok') + '">'
        + (ng ? L('người thu','staff') : L('ghế nuốt','acceptor')) + '</span></td>'
      + '<td class="hide-sm">' + esc(r.nguoi || '—') + '</td>'
      + '<td class="r">' + tien(r.so_tien) + '</td></tr>';
  }
  h += '</table>';
  box.innerHTML = h;
  var pg = document.createElement('div'); pg.className = 'act'; pg.style.cssText = 'margin-top:8px;align-items:center';
  var bT = document.createElement('button'); bT.className = 'ghost'; bT.textContent = '‹ ' + L('Trước','Prev');
  bT.style.padding = '4px 10px'; bT.disabled = TM_PG <= 0; bT.onclick = function(){ TM_PG--; tmRender(); };
  var bS = document.createElement('button'); bS.className = 'ghost'; bS.textContent = L('Sau','Next') + ' ›';
  bS.style.padding = '4px 10px'; bS.disabled = TM_PG >= pages - 1; bS.onclick = function(){ TM_PG++; tmRender(); };
  var sp = document.createElement('span'); sp.className = 'mut';
  sp.textContent = L('Trang','Page') + ' ' + (TM_PG + 1) + '/' + pages + ' · ' + ds.length + ' ' + L('lượt','entries');
  pg.appendChild(bT); pg.appendChild(sp); pg.appendChild(bS); box.appendChild(pg);
}

/* ============================================================================================
 * TAB MÃ GIẢM GIÁ — quản lý mã đã bán.
 *
 * Bốn câu hỏi thật ở quầy, theo đúng thứ tự người ta hỏi:
 *   1. "Kỳ này bán được bao nhiêu mã, thu bao nhiêu?"  -> ô tổng
 *   2. "Đang nợ khách bao nhiêu?"                       -> ô nợ, tô riêng
 *   3. "Khách này quên PIN, còn mã nào không?"          -> ô tra theo số điện thoại
 *   4. "Mã này sao lại không dùng được?"                -> bảng, có cột đã dùng / đã huỷ
 * ============================================================================================ */
var MA_TRA = null;   // kết quả tra theo số điện thoại (null = chưa tra)

function veMa(){
  var M = D.ma || { tong:{ban:0,thu:0,menh:0,da_dung:0}, no:{so_ma:0,tong:0,da_thu:0}, ds:[], quyen_huy:0 };
  var h = '<div class="kpis">'
    + kpi(L('Bán trong kỳ','Sold this period'), String(M.tong.ban) + ' ' + L('mã','codes'),
        tien(M.tong.thu) + ' ' + L('đã thu','collected'), 'a')
    + kpi(L('Đã dùng','Redeemed'), String(M.tong.da_dung) + ' ' + L('mã','codes'), '', 'c')
    /* Khoản NỢ tô riêng: mã không hết hạn nên con số này chỉ cộng lên và không bao giờ tự đóng.
       Mỗi mã chưa dùng là một lượt massage còn nợ khách. */
    + kpi(L('ĐANG NỢ KHÁCH','OWED TO CUSTOMERS'), tien(M.no.tong),
        M.no.so_ma + ' ' + L('mã chưa dùng','unused codes'), 'd')
    + '</div>';

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * VÍ KHÁCH — đứng ngay dưới ô mã, không tách tab.
   *
   * 🔴 TỔNG NỢ THẬT = nợ mã + nợ ví. Nhìn riêng một vế là thấy một nửa sự thật, và cái nửa
   *    thiếu luôn là nửa làm con số đẹp lên. Nên hiện luôn cả tổng.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var V = D.vi || { no:{dung:0,cho:0,tong:0,so_vi:0}, ds:[], co_ban:0 };
  if (V.co_ban || V.no.tong > 0 || V.ds.length) {
    h += '<div class="kpis">'
      + kpi(L('SỐ DƯ VÍ KHÁCH','CUSTOMER WALLET BALANCE'), tien(V.no.tong),
          V.no.so_vi + ' ' + L('ví','wallets')
            + (V.no.cho > 0 ? ' · ' + tien(V.no.cho) + ' ' + L('đang chờ','on hold') : ''), 'd')
      + kpi(L('TỔNG NỢ KHÁCH','TOTAL OWED'), tien(M.no.tong + V.no.tong),
          L('mã chưa dùng + số dư ví','unused codes + wallet balance'), 'd')
      + '</div>';
  }

  h += '<div class="card"><h2>' + L('Khách quên PIN — tra hộ','Customer forgot PIN — look up')
    + '</h2><p class="mut" style="margin:0 0 10px">'
    + L('Nhập số điện thoại khách mua. Chỉ nhân viên tra được kiểu này — trang của khách vẫn '
        + 'phải có PIN.',
        'Enter the phone number the customer bought with. Only staff can look up this way — the '
        + 'customer page still requires the PIN.') + '</p>'
    + '<div class="act"><input id="ma-sdt" placeholder="0909 123 456" style="max-width:220px">'
    + '<button id="ma-tra" class="on">' + L('Tra','Look up') + '</button></div>'
    + '<div class="err" id="ma-e"></div>';
  if (MA_TRA) {
    /* Ví hiện TRƯỚC bảng mã: nếu khách có ví thì đó thường là thứ họ đang hỏi. */
    if (MA_TRA.vi) {
      h += '<div class="ok" style="margin-top:10px">'
        + L('Số dư ví tiêu được: ','Wallet available: ') + '<b>' + tien(MA_TRA.vi.dung) + '</b>'
        + (MA_TRA.vi.cho > 0
            ? '<br>⏳ ' + tien(MA_TRA.vi.cho) + ' ' + L('đang trong hạn chờ','on hold')
              + (MA_TRA.vi.con_cho > 0
                  ? ' — ' + L('dùng được sau ','available in ') + docCho(MA_TRA.vi.con_cho) : '')
            : '')
        + (MA_TRA.vi.khoa ? '<br><b style="color:#ff6b6b">' + L('VÍ ĐANG KHOÁ','WALLET LOCKED') + '</b>' : '')
        + '</div>';
    }
    if (!MA_TRA.ds.length) {
      h += '<p class="mut" style="margin-top:10px">'
        + (MA_TRA.vi ? L('Số này không có mã lẻ nào (chỉ có ví).','No individual codes (wallet only).')
                     : L('Số này chưa mua mã, cũng chưa có ví.','No codes and no wallet for this number.'))
        + '</p>';
    } else {
      h += bangMa(MA_TRA.ds, M.quyen_huy, true);
    }
  }
  h += '</div>';

  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * QUÀ CHỜ TRAO — ĐỨNG TRƯỚC BẢNG VÍ.
   *
   * Đây là VIỆC PHẢI LÀM của người đang đứng quầy, còn bảng ví là số liệu để tra. Việc phải làm
   * thì đứng trước; số liệu để tra thì đứng sau.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var Q = D.qua || { cho:[], tong:{so:0,tien:0,cho:0}, bat:0 };
  if (Q.cho.length) {
    h += '<div class="card"><h2>🎁 ' + L('Quà chờ trao','Gifts to hand over')
      + ' (' + Q.cho.length + ')</h2>'
      + '<p class="mut" style="margin:0 0 10px">'
      + L('Khách đọc số điện thoại — đối chiếu bốn số cuối rồi bấm Đã trao.',
          'The customer gives their phone number — match the last 4 digits, then tap Handed over.')
      + '</p><table><thead><tr><th>' + L('Số điện thoại','Phone') + '</th>'
      + '<th>' + L('Phần quà','Gift') + '</th>'
      + '<th>' + L('Đủ mốc lúc','Earned at') + '</th><th></th></tr></thead><tbody>';
    Q.cho.forEach(function(q){
      h += '<tr><td><b>' + esc(q.sdt_che) + '</b></td>'
        + '<td>' + esc(q.ghi_chu || L('Quà tri ân','Loyalty gift'))
        + ' <span class="mut">(' + Lf2(L('đủ {0} lượt','{0} stamps'), q.moc) + ')</span></td>'
        + '<td class="mut">' + esc(String(q.tao_luc).slice(0,16)) + '</td>'
        + '<td><button class="on" data-trao="' + q.id + '">'
        + L('Đã trao','Handed over') + '</button></td></tr>';
    });
    h += '</tbody></table></div>';
  }

  /* Danh sách ví còn tiền — nợ nằm ở đâu, ai giữ nhiều nhất.
     ⚠️ Số điện thoại đã CHE từ máy chủ (VHG_Ma::sdt_che). Màn này nhân viên ca nào cũng mở;
        in đủ số là biến bảng tiền thành danh bạ khách hàng, bôi đen là chép được cả nghìn số. */
  if (V.ds.length) {
    h += '<div class="card"><h2>' + L('Ví khách còn tiền','Wallets with balance') + '</h2>'
      /* ══════════════════════════════════════════════════════════════════════════════════
       * 🔴 THỨ TỰ CỘT LÀ MỘT PHÉP TÍNH, VÀ PHẢI ĐỌC RA ĐƯỢC.
       *
       * Anh Thắng 23/08/2026: *"Số tiền đang chờ và số tiền đã tiêu cộng lại sai với số đã
       * nạp"*. Con số KHÔNG sai — 30.000 + 120.000 + 90.000 = 240.000, khớp đúng. Anh cộng
       * hai cột và thiếu cột thứ ba.
       *
       * Nhưng đó là lỗi của cái bảng, không phải của người đọc. Bảng cũ xếp "Đã nạp" nằm GIỮA
       * "Đang chờ" và "Đã tiêu" — đặt một con số TỔNG vào giữa hai số HẠNG thì mắt tự nối hai
       * cái cạnh nhau lại rồi so với nó, và tất nhiên là lệch.
       *
       * Nay tổng đứng TRƯỚC, và tiêu đề mang luôn dấu `=` `+` `+`. Đọc từ trái sang là ra
       * đúng phép tính, không phải đoán.
       * ═════════════════════════════════════════════════════════════════════════════════════ */
      + '<table><thead><tr><th>' + L('Số điện thoại','Phone') + '</th>'
      + '<th>' + L('Đã nạp','Topped up') + '</th>'
      + '<th>= ' + L('Tiêu được','Available') + '</th>'
      + '<th>+ ' + L('Đang chờ','On hold') + '</th>'
      + '<th>+ ' + L('Đã tiêu','Spent') + '</th>'
      + '<th>' + L('Tình trạng','Status') + '</th></tr></thead><tbody>';
    V.ds.forEach(function(v){
      /* 🔴 TỰ KIỂM NGAY TRÊN BẢNG. Ba số hạng phải cộng đúng bằng tổng; lệch là có chuyện với
         tiền của khách, và phải HIỆN RA chứ không phải để ai đó tình cờ nhẩm ra. */
      var lech = (v.so_du_dung + v.so_du_cho + v.da_tieu) - v.da_nap;
      h += '<tr><td>' + esc(v.sdt_che) + '</td>'
        + '<td><b>' + tien(v.da_nap) + '</b>'
        + (lech !== 0 ? '<br><b style="color:#ff6b6b">' + L('lệch ','off by ') + tien(lech) + '</b>' : '')
        + '</td>'
        + '<td>' + tien(v.so_du_dung) + '</td>'
        + '<td>' + (v.so_du_cho > 0 ? tien(v.so_du_cho) : '—') + '</td>'
        + '<td>' + tien(v.da_tieu) + '</td>'
        + '<td>' + (v.khoa ? '<b style="color:#ff6b6b">' + L('ĐANG KHOÁ','LOCKED') + '</b>'
                           : '<span class="mut">' + L('bình thường','normal') + '</span>') + '</td></tr>';
    });
    h += '</tbody></table>'
      + '<p class="mut" style="margin:8px 0 0">'
      + L('<b>Đã nạp</b> = tổng mọi khoản CỘNG vào ví (gồm cả hoàn tiền và chỉnh tay), '
          + 'nên nó luôn bằng <b>tiêu được + đang chờ + đã tiêu</b>. Lệch là có chuyện — bảng sẽ báo đỏ.',
          '<b>Topped up</b> = every credit to the wallet (including refunds and manual adjustments), '
          + 'so it always equals <b>available + on hold + spent</b>. Any mismatch is flagged in red.')
      + '</p></div>';
  }

  h += '<div class="card"><h2>' + L('Mã đã bán trong kỳ','Codes sold this period') + '</h2>'
    + (M.ds.length ? bangMa(M.ds, M.quyen_huy, false)
        : '<p class="mut">' + L('Chưa bán mã nào trong kỳ này.','No codes sold in this period.')
          + '</p>')
    + '</div>';
  return h;
}

function bangMa(ds, quyen, hien_sdt){
  var h = '<table style="margin-top:8px"><tr><th>' + L('Mã','Code') + '</th>'
    + (hien_sdt ? '' : '<th class="hide-sm">' + L('Số ĐT','Phone') + '</th>')
    + '<th class="r">' + L('Mệnh giá','Value') + '</th><th class="r hide-sm">'
    + L('Khách trả','Paid') + '</th><th>' + L('Tình trạng','Status') + '</th>'
    + (quyen ? '<th></th>' : '') + '</tr>';
  ds.forEach(function(m){
    var tt, lop;
    if (m.huy)           { tt = L('đã huỷ','cancelled') + (m.huy_ly_do ? ' · ' + esc(m.huy_ly_do) : ''); lop = 'p-off'; }
    else if (m.dung_luc) { tt = L('đã dùng','used') + ' ' + esc(m.dung_luc)
                              + (m.dung_may ? ' · ' + esc(m.dung_may) : ''); lop = 'p-wait'; }
    else                 { tt = L('còn dùng được','usable'); lop = 'p-ok'; }
    h += '<tr><td><b style="font-variant-numeric:tabular-nums">' + esc(m.ma) + '</b>'
      + '<br><span class="mut">' + esc(m.tao_luc) + '</span></td>'
      + (hien_sdt ? '' : '<td class="hide-sm">' + esc(m.sdt) + '</td>')
      + '<td class="r">' + tien(m.menh_gia) + '</td>'
      + '<td class="r hide-sm">' + tien(m.gia_ban)
        + (m.giam_pt ? '<br><span class="mut">-' + m.giam_pt + '%</span>' : '') + '</td>'
      + '<td><span class="pill ' + lop + '">' + tt + '</span></td>';
    /* Nút huỷ CHỈ hiện cho mã còn dùng được. Mã đã dùng thì ghế chạy rồi — đánh dấu huỷ lúc đó
       là sổ nói dối theo hướng có lợi cho mình. */
    if (quyen) {
      h += '<td class="r">' + ( (!m.huy && !m.dung_luc)
        ? '<button data-mahuy="' + esc(m.ma) + '">' + L('Huỷ','Cancel') + '</button>' : '' ) + '</td>';
    }
    h += '</tr>';
  });
  return h + '</table>';
}

/* TAB KÍCH HOẠT GHẾ — ghế nào đã bật tay, mấy lần, tổng bao lâu, và vì sao. */
function veKichHoat(){
  var b = D.bat || { ky:{so_lan:0,tong_phut:0,so_ghe:0}, thang:{so_lan:0,tong_phut:0},
                     ngay:[], may:[], ds:[] };
  var h = '<div class="kpis">'
    + kpi(L('Kỳ đang xem','Selected period'), String(b.ky.so_lan) + ' ' + L('lần','times'),
        b.ky.tong_phut + ' ' + L('phút · trên','min · across') + ' ' + b.ky.so_ghe + ' '
        + L('ghế','chairs'), 'a')
    + kpi(L('TỔNG THÁNG NÀY','TOTAL THIS MONTH'), String(b.thang.so_lan) + ' ' + L('lần','times'),
        b.thang.tong_phut + ' ' + L('phút','min'), 'd')
    + '</div>';

  h += '<div class="card"><h2>' + L('Ghế đã kích hoạt','Chairs activated') + '</h2>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Mỗi lần bấm Bật là <b>cho không một lượt</b>: ghế chạy, điện tốn, mà sổ doanh thu không '
        + 'có đồng nào. Một ghế đứng đầu bảng tháng này qua tháng khác thì hoặc nó hỏng thật, '
        + 'hoặc có người đang quen tay — cả hai đều đáng biết.',
        'Every Start press is <b>a free session</b>: the chair runs, power is spent, and the revenue '
        + 'book shows nothing. A chair that tops this table month after month is either genuinely '
        + 'faulty or someone has got into the habit — both are worth knowing.') + '</p>';
  h += '<table><tr><th>' + L('Ghế','Chair') + '</th><th class="hide-sm">' + L('Cơ sở','Branch')
    + '</th><th class="r">' + L('Số lần','Times') + '</th><th class="r">'
    + L('Tổng phút','Total minutes') + '</th><th class="r hide-sm">' + L('Lần cuối','Last')
    + '</th></tr>';
  if (!b.may.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa ghế nào được bật tay trong kỳ này.','No chair was started by hand in this period.')
    + '</td></tr>';
  b.may.forEach(function(m){
    h += '<tr><td><b>' + esc(m.ma) + '</b></td><td class="hide-sm">' + esc(m.coso || '—') + '</td>'
      + '<td class="r">' + m.so_lan + '</td><td class="r"><b>' + m.tong_phut + '</b></td>'
      + '<td class="r hide-sm"><span class="mut">' + esc(m.lan_cuoi || '—') + '</span></td></tr>';
  });
  h += '</table></div>';

  if (b.ngay.length) {
    h += '<div class="card"><h2>' + L('Theo ngày','By day') + '</h2><table><tr><th>'
      + L('Ngày','Day') + '</th><th class="r">' + L('Số lần','Times') + '</th><th class="r">'
      + L('Tổng phút','Total minutes') + '</th></tr>';
    b.ngay.forEach(function(n){
      h += '<tr><td>' + esc(n.ngay) + '</td><td class="r">' + n.so_lan + '</td>'
        + '<td class="r"><b>' + n.tong_phut + '</b></td></tr>';
    });
    h += '</table></div>';
  }

  h += '<div class="card"><h2>' + L('Nhật ký kích hoạt','Activation log') + '</h2><table><tr><th>'
    + L('Lúc','Time') + '</th><th>' + L('Ghế','Chair') + '</th><th class="hide-sm">'
    + L('Ai bấm','Pressed by') + '</th><th>' + L('Lý do','Reason') + '</th><th class="r">'
    + L('Phút','Min') + '</th></tr>';
  if (!b.ds.length) h += '<tr><td colspan="5" class="mut">'
    + L('Chưa có lượt nào.','Nothing yet.') + '</td></tr>';
  b.ds.forEach(function(l){
    /* Lệnh ghế CHƯA LẤY phải hiện khác: "đã chạy" và "sẽ chạy khi ghế lên mạng" là hai thứ khác
       nhau khi đang đứng đối chiếu với sổ. */
    h += '<tr><td>' + esc(l.luc)
      + (l.da_gui ? '' : '<br><span class="pill p-wait">' + L('ghế chưa lấy','not picked up')
          + '</span>') + '</td>'
      + '<td><b>' + esc(l.ma) + '</b></td>'
      + '<td class="hide-sm">' + esc(l.nguoi || '—') + '</td>'
      + '<td>' + esc(l.ly_do || '—') + '</td>'
      + '<td class="r">' + l.phut + '</td></tr>';
  });
  return h + '</table></div>';
}

/* ============================================================================================
 * TAB QUẢN LÝ GHẾ — địa điểm (thêm/sửa/xoá), ghế (thêm/xoá/chuyển cơ sở), doanh thu theo địa điểm.
 * Dữ liệu lấy sẵn từ so_lieu: D.coso (danh sách), D.may (ghế), D.tong.theo_coso (doanh thu/kỳ).
 * ========================================================================================== */
function veQuanLy(){
  var coso = D.coso || [], may = D.may || [];
  var tc = (D.tong && D.tong.theo_coso) || [];
  var dt = {};                         // doanh thu theo TÊN cơ sở
  tc.forEach(function(c){ dt[c.coso] = c; });
  var demGhe = {}, chuaGan = 0;        // đếm ghế theo cơ sở
  may.forEach(function(m){
    if (!m.coso) { chuaGan++; } else { demGhe[m.coso] = (demGhe[m.coso]||0) + 1; }
  });

  var h = '<div class="kpis">'
    + kpi(L('Địa điểm','Sites'), String(coso.length), L('cơ sở','locations'), 'a')
    + kpi(L('Tổng ghế','Chairs'), String(may.length),
        (chuaGan ? chuaGan + ' ' + L('chưa gán','unassigned') : L('đã gán hết','all assigned')), 'b')
    + kpi(L('Doanh thu kỳ','Revenue'), tien(D.tong ? D.tong.tong : 0),
        L('kỳ đang xem','selected period'), 'd')
    + '</div>';

  /* ---- Địa điểm ---- */
  h += '<div class="card"><h2>' + L('Địa điểm','Sites') + '</h2>'
    + '<div class="act" style="flex-wrap:wrap;margin-bottom:12px">'
    + '<input id="cs-ten" type="text" maxlength="60" placeholder="'
      + L('Tên địa điểm mới','New site name') + '" style="flex:2;min-width:160px">'
    + '<input id="cs-tinh" type="text" maxlength="60" placeholder="'
      + L('Tỉnh/TP (VD Bình Dương)','Province') + '" style="flex:1;min-width:130px">'
    + '<button id="cs-them" class="on">＋ ' + L('Thêm địa điểm','Add site') + '</button></div>';
  h += '<table><tr><th>' + L('Địa điểm','Site') + '</th><th class="r">' + L('Số ghế','Chairs')
    + '</th><th class="r">' + L('Doanh thu','Revenue') + '</th><th class="r hide-sm">' + L('QR','QR')
    + '</th><th class="r hide-sm">' + L('Tiền mặt','Cash') + '</th><th class="r"></th></tr>';
  if (!coso.length) h += '<tr><td colspan="6" class="mut">'
    + L('Chưa có địa điểm nào — thêm ở trên.','No sites yet — add one above.') + '</td></tr>';
  coso.forEach(function(c){
    var r = dt[c.ten] || { tong:0, qr:0, tien_mat:0 };
    h += '<tr><td><b>' + esc(c.ten) + '</b>'
      + (c.tinh ? '<div class="mut">📍 ' + esc(c.tinh) + '</div>' : '')
      + '</td>'
      + '<td class="r">' + (demGhe[c.ten]||0) + '</td>'
      + '<td class="r"><b>' + tien(r.tong) + '</b>'
      + (r.nguon_bc ? ' <span class="pill p-wait" title="' + L('Chưa có webhook — lấy từ báo cáo doanh thu','No webhook — from revenue reports') + '">' + L('báo cáo','report') + '</span>' : '')
      + '</td>'
      + '<td class="r hide-sm">' + tien(r.qr) + '</td>'
      + '<td class="r hide-sm">' + tien(r.tien_mat) + '</td>'
      + '<td class="r" style="white-space:nowrap">'
      + '<button data-cssua="' + c.id + '" data-csten="' + esc(c.ten) + '" data-cstinh="' + esc(c.tinh||'') + '">✎</button> '
      + '<button data-csxoa="' + c.id + '" data-csnhan="' + esc(c.ten) + '">🗑</button></td></tr>';
  });
  if (chuaGan) {
    var rc = dt['(chưa gán)'] || { tong:0, qr:0, tien_mat:0 };
    h += '<tr><td class="mut">' + L('(chưa gán)','(unassigned)') + '</td>'
      + '<td class="r">' + chuaGan + '</td><td class="r">' + tien(rc.tong) + '</td>'
      + '<td class="r hide-sm">' + tien(rc.qr) + '</td><td class="r hide-sm">' + tien(rc.tien_mat)
      + '</td><td></td></tr>';
  }
  h += '</table><p class="mut" style="margin:8px 0 0">'
    + L('Xoá địa điểm KHÔNG xoá ghế — ghế thành "chưa gán". Doanh thu theo kỳ đang chọn ở đầu trang.',
        'Deleting a site does not delete its chairs — they become "unassigned". Revenue is for the selected period.')
    + '</p></div>';

  /* ---- Ghế ---- */
  /* Cơ sở id đang lọc (để nút Thêm ghế mặc định gán luôn vào cơ sở đang xem). */
  var locId = 0;
  coso.forEach(function(c){ if (c.ten === QL_LOC) locId = c.id; });
  var opt = '<option value="0"' + (locId ? '' : ' selected') + '>' + L('(chưa gán)','(unassigned)')
    + '</option>' + coso.map(function(c){
        return '<option value="' + c.id + '"' + (c.id === locId ? ' selected' : '') + '>'
          + esc(c.ten) + '</option>'; }).join('');
  /* Bộ lọc theo cơ sở — kèm số ghế mỗi cơ sở cho dễ nhìn. */
  var flt = '<option value="">' + L('Tất cả cơ sở','All sites') + ' (' + may.length + ')</option>'
    + coso.map(function(c){
        return '<option value="' + esc(c.ten) + '"' + (QL_LOC === c.ten ? ' selected' : '') + '>'
          + esc(c.ten) + ' (' + (demGhe[c.ten]||0) + ')</option>'; }).join('')
    + (chuaGan ? '<option value="__none__"' + (QL_LOC === '__none__' ? ' selected' : '') + '>'
        + L('(chưa gán)','(unassigned)') + ' (' + chuaGan + ')</option>' : '');

  var mayLoc = may.filter(function(m){
    if (QL_LOC === '') return true;
    if (QL_LOC === '__none__') return !m.coso;
    return m.coso === QL_LOC;
  });

  h += '<div class="card"><h2>' + L('Ghế','Chairs') + '</h2>'
    + '<div class="act" style="flex-wrap:wrap;margin-bottom:8px">'
    + '<input id="ma-moi" type="text" maxlength="20" placeholder="'
      + L('Mã ghế mới (vd AMTP02)','New chair code') + '" style="flex:2;min-width:160px">'
    + '<select id="ma-cs" style="flex:1;min-width:130px">' + opt + '</select>'
    + '<button id="ma-them" class="on">＋ ' + L('Thêm ghế','Add chair') + '</button></div>'
    + '<p class="mut" style="margin:0 0 10px">'
    + L('Mã đi vào nội dung chuyển khoản khách gõ — chỉ chữ và số, không dấu, không khoảng trắng.',
        'The code goes into the transfer memo the customer types — letters and digits only.') + '</p>'
    + '<div class="act" style="margin-bottom:10px"><label class="mut" style="align-self:center">'
    + L('Lọc cơ sở','Filter site') + ':</label>'
    + '<select id="ql-loc" style="flex:1;min-width:160px">' + flt + '</select></div>';
  h += '<div id="ql-wrap"></div>'
    + '<p class="mut" style="margin:8px 0 0">'
    + L('Đổi ô địa điểm để chuyển ghế sang cơ sở khác (lưu ngay). Xoá ghế chỉ xoá cấu hình — doanh thu đã ghi giữ nguyên.',
        'Change the site dropdown to move a chair (saves immediately). Deleting a chair removes only its config — recorded revenue is kept.')
    + '</p></div>';
  return h;
}

/* Danh sách ghế (tab Quản lý ghế): 10 ghế/trang, lọc theo QL_LOC — vẽ tại chỗ vào #ql-wrap,
   tự gắn lại select đổi cơ sở + nút xoá cho mỗi trang. */
function qlGheRender(){
  var box = document.getElementById('ql-wrap'); if (!box) return;
  var may = (D && D.may) || [], coso = (D && D.coso) || [];
  var list = may.filter(function(m){
    if (QL_LOC === '') return true;
    if (QL_LOC === '__none__') return !m.coso;
    return m.coso === QL_LOC;
  }).sort(function(a,b){ return String(a.ma).localeCompare(String(b.ma)); });
  var pages = Math.max(1, Math.ceil(list.length / QL_PER));
  if (QL_PG >= pages) QL_PG = pages - 1; if (QL_PG < 0) QL_PG = 0;
  var from = QL_PG * QL_PER, to = Math.min(list.length, from + QL_PER);
  var h = '<table><tr><th>' + L('Mã','Code') + '</th><th>' + L('Địa điểm','Site')
    + '</th><th class="r hide-sm">' + L('Trạng thái','Status') + '</th><th class="r"></th></tr>';
  if (!list.length) h += '<tr><td colspan="4" class="mut">'
    + (may.length ? L('Không có ghế ở cơ sở này.','No chairs at this site.')
                  : L('Chưa có ghế nào.','No chairs yet.')) + '</td></tr>';
  for (var i = from; i < to; i++){ var m = list[i];
    var csOpt = '<option value="0"' + (!m.coso ? ' selected' : '') + '>' + L('(chưa gán)','(unassigned)')
      + '</option>' + coso.map(function(c){
          return '<option value="' + c.id + '"' + (m.coso === c.ten ? ' selected' : '') + '>'
            + esc(c.ten) + '</option>'; }).join('');
    var tt = m.tt === 'running' ? '▶️' : (m.tt === 'wait_pay' ? '⏳' : (m.song ? '🟢' : '⚪'));
    h += '<tr><td><b>' + esc(m.ma) + '</b></td>'
      + '<td><select data-csma="' + esc(m.ma) + '" style="max-width:150px">' + csOpt + '</select></td>'
      + '<td class="r hide-sm mut">' + tt + '</td>'
      + '<td class="r"><button data-mxoa="' + esc(m.ma) + '">🗑 ' + L('Xoá','Delete') + '</button></td></tr>';
  }
  h += '</table>';
  box.innerHTML = h;
  // pager
  var pg = document.createElement('div'); pg.className = 'act'; pg.style.cssText = 'margin-top:8px;align-items:center';
  var bT = document.createElement('button'); bT.className = 'ghost'; bT.textContent = '‹ ' + L('Trước','Prev');
  bT.style.padding = '4px 10px'; bT.disabled = QL_PG <= 0; bT.onclick = function(){ QL_PG--; qlGheRender(); };
  var bS = document.createElement('button'); bS.className = 'ghost'; bS.textContent = L('Sau','Next') + ' ›';
  bS.style.padding = '4px 10px'; bS.disabled = QL_PG >= pages - 1; bS.onclick = function(){ QL_PG++; qlGheRender(); };
  var sp = document.createElement('span'); sp.className = 'mut';
  sp.textContent = L('Trang','Page') + ' ' + (QL_PG + 1) + '/' + pages + ' · ' + list.length + ' ' + L('ghế','chairs');
  pg.appendChild(bT); pg.appendChild(sp); pg.appendChild(bS); box.appendChild(pg);
  // gắn lại đổi cơ sở + xoá cho các dòng của trang này
  [].forEach.call(box.querySelectorAll('[data-csma]'), function(s){
    s.onchange = function(){ lam('may_coso', { ma: s.getAttribute('data-csma'), coso_id: s.value }); };
  });
  [].forEach.call(box.querySelectorAll('[data-mxoa]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-mxoa');
      if (!confirm(L('Xoá ghế ' + m + '? Chỉ xoá cấu hình, doanh thu đã ghi giữ nguyên.',
        'Delete chair ' + m + '? Config only; recorded revenue is kept.'))) return;
      lam('may_xoa', { ma: m });
    };
  });
}

function kpi(lb, vl, sb, m){
  return '<div class="kpi"><div class="lb">' + lb + '</div><div class="vl ' + m + '">' + vl
    + '</div><div class="sb">' + sb + '</div></div>';
}
function bang(ten, cot, hang){
  var h = '<div class="card"><h2>' + ten + '</h2><table><tr>'
    + cot.map(function(c,i){ return '<th' + (i>=cot.length-3?' class="r"':'') + '>' + c + '</th>'; }).join('')
    + '</tr>';
  if (!hang.length) h += '<tr><td colspan="' + cot.length + '" class="mut">'
    + L('Chưa có số liệu kỳ này.','No data for this period.') + '</td></tr>';
  hang.forEach(function(r){
    h += '<tr>' + r.map(function(o,i){ return '<td' + (i>=cot.length-3?' class="r"':'') + '>' + o + '</td>'; }).join('') + '</tr>';
  });
  return h + '</table></div>';
}

/* ============================================================================================
 * BIỂU ĐỒ DASHBOARD — doanh thu theo tổng số, cơ sở, ghế, khu vực.
 *
 * Anh Thắng 28/08/2026: *"Thêm một số biểu đồ dashboard… doanh thu theo điểm, theo ghế, theo cơ
 * sở, theo tổng số, theo khu vực"*.
 *
 * 🔴 KHÔNG THƯ VIỆN NGOÀI. Donut vẽ bằng SVG (cung tròn qua stroke-dasharray), thanh ngang vẽ
 *    bằng div CSS — nhẹ, hợp chủ đề (dùng biến màu --blue/--green/--amber), và không kéo theo
 *    một file JS ngoài vào một trang chạy trên host của khách. Số liệu lấy từ `t = D.tong` (kỳ
 *    đang chọn) và bản đồ cơ sở→tỉnh từ `D.coso`; không cần gọi thêm cổng nào.
 * ============================================================================================ */
function tienGon(n){
  n = Number(n) || 0; var s = n < 0 ? '-' : ''; n = Math.abs(n);
  if (n >= 1e9) return s + (n/1e9).toFixed(n >= 1e10 ? 1 : 2).replace('.', ',') + L(' tỷ',' bn');
  if (n >= 1e6) return s + (n/1e6).toFixed(n >= 1e8 ? 0 : 1).replace('.', ',') + L(' tr',' m');
  if (n >= 1e3) return s + Math.round(n/1e3) + 'k';
  return s + String(n);
}
/* Donut theo phần: parts = [{ten, gt, mau}]. Rỗng/tổng 0 → báo chưa có số liệu. */
function bdDonut(parts){
  var tong = parts.reduce(function(a,p){ return a + (Number(p.gt)||0); }, 0);
  if (tong <= 0) return '<p class="mut">' + L('Chưa có số liệu kỳ này.','No data for this period.') + '</p>';
  var r = 54, C = 2 * Math.PI * r, off = 0;
  var segs = parts.filter(function(p){ return (Number(p.gt)||0) > 0; }).map(function(p){
    var len = (Number(p.gt)/tong) * C;
    var s = '<circle cx="70" cy="70" r="' + r + '" fill="none" stroke="' + p.mau + '" stroke-width="21" '
      + 'stroke-dasharray="' + len.toFixed(2) + ' ' + (C - len).toFixed(2) + '" stroke-dashoffset="' + (-off).toFixed(2) + '"/>';
    off += len; return s;
  }).join('');
  var svg = '<svg viewBox="0 0 140 140" class="bd-donut" width="128" height="128" role="img">'
    + '<g transform="rotate(-90 70 70)">' + segs + '</g>'
    + '<text x="70" y="67" text-anchor="middle" class="bd-c1">' + tienGon(tong) + 'đ</text>'
    + '<text x="70" y="83" text-anchor="middle" class="bd-c2">' + L('tổng','total') + '</text></svg>';
  var leg = parts.map(function(p){
    var pc = tong ? Math.round((Number(p.gt)||0)/tong*100) : 0;
    return '<div class="bd-lg"><span class="bd-dot" style="background:' + p.mau + '"></span>'
      + '<span class="bd-lg-t">' + esc(p.ten) + '</span>'
      + '<span class="bd-lg-v">' + tienGon(p.gt) + 'đ · ' + pc + '%</span></div>';
  }).join('');
  return '<div class="bd-donut-wrap">' + svg + '<div class="bd-legend">' + leg + '</div></div>';
}
/* Thanh ngang XẾP CHỒNG Tiền mặt (xanh lá) + QR (xanh dương) trong một thanh.
 * rows = [{ten, tm, qr}]; GIỮ nguyên thứ tự truyền vào (để chuỗi thời gian không bị xáo). */
function bdCotStack(rows, gioi){
  rows = (rows || []).map(function(r){ var tm = Number(r.tm)||0, qr = Number(r.qr)||0;
      return { ten: r.ten, tm: tm, qr: qr, gt: tm + qr }; })
    .filter(function(r){ return r.gt > 0; });
  if (!rows.length) return '<p class="mut">' + L('Chưa có số liệu kỳ này.','No data for this period.') + '</p>';
  var them = (gioi && rows.length > gioi) ? (rows.length - gioi) : 0;
  if (them) rows = rows.slice(0, gioi);
  var max = rows.reduce(function(a,r){ return Math.max(a, r.gt); }, 0) || 1;
  var chu = '<div class="bd-chu"><span class="bd-lg2"><span class="bd-dot" style="background:var(--green)"></span>'
      + L('Tiền mặt','Cash') + '</span><span class="bd-lg2"><span class="bd-dot" style="background:var(--blue)"></span>QR</span></div>';
  var h = chu + '<div class="bd-cot">' + rows.map(function(r){
    var wTot = Math.max(3, Math.round(r.gt/max*100));
    var wTm = r.gt ? Math.round(r.tm/r.gt*100) : 0;
    return '<div class="bd-row"><div class="bd-lb" title="' + esc(r.ten) + '">' + esc(r.ten) + '</div>'
      + '<div class="bd-track"><div class="bd-bar" style="width:' + wTot + '%;background:var(--blue)">'
      + '<div class="bd-seg" style="width:' + wTm + '%;background:var(--green)"></div></div></div>'
      + '<div class="bd-val">' + tienGon(r.gt) + 'đ</div></div>';
  }).join('') + '</div>';
  if (them) h += '<p class="mut" style="margin:8px 0 0">' + L('… và ' + them + ' mục khác','… and ' + them + ' more') + '</p>';
  return h;
}
/* Khối biểu đồ của dashboard Đối soát — dựng từ t = D.tong (kỳ đang chọn). */
function veBieuDo(t){
  if (!t) return '';
  var cs = Object.keys(t.theo_coso || {}).map(function(k){ var c = t.theo_coso[k];
      return { ten: c.coso, tm: Number(c.tien_mat)||0, qr: Number(c.qr)||0, gt: Number(c.tong)||0 }; })
    .sort(function(a,b){ return b.gt - a.gt; });
  var may = Object.keys(t.theo_may || {}).map(function(k){ var m = t.theo_may[k];
      return { ten: m.may, tm: Number(m.tien_mat)||0, qr: Number(m.qr)||0, gt: Number(m.tong)||0 }; })
    .sort(function(a,b){ return b.gt - a.gt; });
  var mt = {}; ((D && D.coso) || []).forEach(function(c){ mt[c.ten] = c.tinh || ''; });
  var byT = {};
  cs.forEach(function(r){ var k = mt[r.ten] || L('(chưa gán tỉnh)','(no province)');
    if (!byT[k]) byT[k] = { ten: k, tm: 0, qr: 0, gt: 0 };
    byT[k].tm += r.tm; byT[k].qr += r.qr; byT[k].gt += r.gt; });
  var tinh = Object.keys(byT).map(function(k){ return byT[k]; })
    .sort(function(a,b){ return b.gt - a.gt; });

  var h = '<div class="bd-grid">';
  h += '<div class="card"><h2>' + L('Cơ cấu doanh thu','Revenue mix') + '</h2>'
    + bdDonut([
        { ten: L('Tiền mặt','Cash'), gt: t.tien_mat, mau: 'var(--green)' },
        { ten: L('Chuyển khoản (QR)','Bank transfer (QR)'), gt: t.qr, mau: 'var(--blue)' }
      ]) + '</div>';
  h += '<div class="card"><h2>' + L('Doanh thu theo khu vực','Revenue by region') + '</h2>'
    + bdCotStack(tinh, 12) + '</div>';
  h += '<div class="card"><h2>' + L('Top cơ sở theo doanh thu','Top branches by revenue') + '</h2>'
    + bdCotStack(cs, 10) + '</div>';
  h += '<div class="card"><h2>' + L('Top ghế theo doanh thu','Top chairs by revenue') + '</h2>'
    + bdCotStack(may, 10) + '</div>';
  h += '</div>';
  return h;
}

/* ============================================================================================
 * BẢNG CHỐT CA — MỞ NGĂN GHẾ, ĐỌC CHỈ SỐ, ĐẾM TIỀN.
 *
 * Anh Thắng 23/08/2026: *"Mở ứng dụng tới quét QR tại máy. Bấm thu tiền (chốt ca, dữ liệu chốt
 * ca). Nhập số tiền mặt, chỉ số máy tiền mặt — trên máy có 1 màn hình đếm tiền mặt nữa, nên nhập
 * vào để trừ chỉ số cho ngày hôm sau."*
 *
 * ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BẢN TRƯỚC GHI LƯỢT NÀY THÀNH DOANH THU — VÀ ĐÓ LÀ CỘNG ĐÔI.
 *
 *    Tiền trong ngăn ghế ĐÃ vào sổ từ lúc ghế nuốt từng tờ (đường `ND_GHE_NUOT`). Người đi thu
 *    mở ngăn ra đếm lại chính xấp tiền ấy — đó là một lần CHUYỂN TAY, không phải một lần bán
 *    hàng. Ghi nó thành doanh thu là cùng một xấp tiền vào sổ hai lần, đúng cái mà tab Thu tiền
 *    phải kêu lên mỗi kỳ ("ghế có CẢ hai đường tiền mặt").
 *
 *    Nay lượt này đi vào bảng `chot`: không đụng doanh thu, chỉ ghi ba con số để đối chiếu và
 *    cộng vào TIỀN TRÊN TAY người vừa chốt. Cảnh báo cộng đôi ở tab Thu tiền vì vậy sẽ tự vơi.
 *
 * 🔴 HAI Ô NHẬP, KHÔNG PHẢI MỘT — và ô CHỈ SỐ đứng trước.
 *    Chỉ số đọc trên màn máy đếm là thứ phải nhìn TRƯỚC khi thò tay vào ngăn: mở ngăn ra rồi,
 *    đếm xong rồi, thì không ai quay lại đọc màn nữa. Thứ tự trên màn hình là thứ tự tay làm.
 * ════════════════════════════════════════════════════════════════════════════════════════════ */
var CHOT = null;   // { ma, so, xem, go, gocs } — bảng đang mở

function bcQuet(msg){
  var e = document.getElementById('quet-e');
  if (e) e.textContent = msg || '';
}

/* Bóc mã ghế ra khỏi thứ vừa quét được.
   Tem trên ghế mang ĐỊA CHỈ TRANG KHÁCH (`https://…/mua-ma/AMTP01`), không mang mã trần — nó
   là tem cho khách quét, và mình dùng ké đúng cái tem đó chứ không dán thêm tem thứ hai. Nên
   phải bóc: lấy đoạn cuối đường dẫn, hoặc tham số `?ghe=`. */
function maGheTuQR(txt){
  var s = String(txt || '').trim();
  if (!s) return '';
  var m = s.match(/[?&]ghe=([A-Za-z0-9]+)/);
  if (m) return m[1].toUpperCase();
  /* Bỏ tham số và dấu / cuối, rồi lấy đoạn cuối. */
  s = s.split('?')[0].split('#')[0].replace(/\/+$/, '');
  var phan = s.split('/');
  var cuoi = phan[phan.length - 1] || '';
  return /^[A-Za-z0-9]{1,20}$/.test(cuoi) ? cuoi.toUpperCase() : '';
}

var QUET = null;   // { luong, video, det, hen } — phiên quét đang mở

function dongQuet(){
  if (!QUET) return;
  if (QUET.hen) { clearInterval(QUET.hen); }
  /* 🔴 TẮT CAMERA. Bỏ quên là đèn camera sáng suốt ca, máy nóng và pin tụt — và người thu sẽ
     tắt hẳn app đi thay vì dùng nó. */
  try { (QUET.luong.getTracks() || []).forEach(function(t){ t.stop(); }); } catch (e) {}
  var k = document.getElementById('quet-khung');
  if (k) k.innerHTML = '';
  QUET = null;
}

function moQuet(){
  if (QUET) { dongQuet(); return; }
  if (typeof BarcodeDetector === 'undefined') {
    bcQuet(L('Trình duyệt này không quét được mã QR — gõ mã ghế vào ô bên dưới giúp em. '
             + '(Chrome trên Android thì quét được.)',
             'This browser cannot scan QR codes — type the chair code below instead. '
             + '(Chrome on Android can scan.)'));
    var o1 = document.getElementById('quet-tay'); if (o1) o1.focus();
    return;
  }
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    bcQuet(L('Không mở được camera trên trình duyệt này — gõ mã ghế giúp em.',
             'Cannot open the camera in this browser — type the chair code instead.'));
    return;
  }
  bcQuet('');
  var k = document.getElementById('quet-khung');
  k.innerHTML = '<div class="quet-hop"><video id="quet-vid" playsinline muted></video>'
    + '<button id="quet-dong" class="ghost">' + L('Đóng camera','Close camera') + '</button></div>';
  document.getElementById('quet-dong').onclick = dongQuet;

  /* `facingMode: environment` = camera SAU. Không khai thì nhiều máy mở camera trước, và người
     ta soi cái tem bằng mặt mình. */
  navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
    .then(function(luong){
      var v = document.getElementById('quet-vid');
      if (!v) { (luong.getTracks()||[]).forEach(function(t){ t.stop(); }); return; }
      v.srcObject = luong; v.play();
      var det = new BarcodeDetector({ formats: ['qr_code'] });
      QUET = { luong: luong, video: v, det: det, hen: null };
      QUET.hen = setInterval(function(){
        if (!QUET) return;
        QUET.det.detect(QUET.video).then(function(ds){
          if (!QUET || !ds || !ds.length) return;
          var ma = maGheTuQR(ds[0].rawValue);
          if (!ma) { bcQuet(L('Mã này không phải tem ghế.','That is not a chair sticker.')); return; }
          dongQuet();
          moChotCa(ma);
        }).catch(function(){ /* khung hình lỗi thì bỏ qua, khung sau quét tiếp */ });
      }, 400);
    })
    .catch(function(){
      bcQuet(L('Chưa được phép dùng camera — cho phép trong cài đặt trình duyệt, hoặc gõ mã ghế.',
               'Camera permission denied — allow it in browser settings, or type the chair code.'));
    });
}

function moChotCa(ma){
  if (ban) return;
  /* 🔴 `chot_xem` LÀ BẮT BUỘC, `so_may` LÀ THÊM.
     `chot_xem` mang mốc chỉ số lần trước, sổ ghi nhận từ lần đó, cơ sở và tình trạng kết nối —
     tức là đủ để chốt ca. `so_may` chỉ thêm doanh thu tháng của ghế, và nó là việc CHỈ QUẢN TRỊ
     làm được (xem VHG_Auth::VIEC_QUAN_TRI). Người thu gọi nó sẽ bị cổng từ chối, nên đừng gọi —
     gọi rồi nuốt lỗi là màn chốt ca im lặng không mở ra, và người đứng ở ghế không hiểu vì sao. */
  var QT2 = QUAN_TRI();
  goi('chot_xem', { ma_may: ma }, function(x){
    if (!x.ok) { alert(x.error || L('Không lấy được mốc chốt ca.','Could not load the closing baseline.')); return; }
    if (!QT2) { CHOT = { ma: ma, so: null, xem: x, go: '', gocs: '' }; veChotCa(); return; }
    goi('so_may', { ma_may: ma }, function(r){
      CHOT = { ma: ma, so: (r && r.ok ? r : null), xem: x, go: '', gocs: '' };
      veChotCa();
    });
  });
}

function hangSo(nhan, gt, lop){
  return '<div class="so-hang' + (lop||'') + '"><span class="nh">' + nhan + '</span>'
    + '<span class="gt">' + gt + '</span></div>';
}

/* Bàn phím số dùng chung cho cả hai ô — ô nào đang được chọn thì gõ vào ô đó. */
function banPhimSo(){
  return '<div class="phim">'
    + ['1','2','3','4','5','6','7','8','9','000','0','⌫'].map(function(k){
        return '<button data-phim="' + k + '">' + k + '</button>'; }).join('')
    + '</div>';
}

function veChotCa(){
  var r = CHOT.so, x = CHOT.xem, cu = document.getElementById('man-chot');
  if (cu) cu.remove();
  var d = document.createElement('div');
  d.className = 'man'; d.id = 'man-chot';

  d.innerHTML = '<div class="hop">'
    + '<h3>' + L('Chốt ca','Close shift') + ' — ' + esc(CHOT.ma) + '</h3>'
    + '<div class="cs">' + esc(x.coso || L('(chưa gán cơ sở)','(no branch)'))
      + (x.song ? '' : ' · ' + L('⚠️ ghế đang mất kết nối','⚠️ chair offline')) + '</div>'

    /* ---- MỐC LẦN TRƯỚC. Đây là con số người ta đối chiếu, nên nó đứng trên cùng. ---- */
    + (x.lan_dau
        ? '<div class="canh">' + L('Ghế này <b>chưa chốt lần nào</b>. Lần này chỉ ghi lại chỉ số '
            + 'làm mốc; từ kỳ sau mới trừ ra được số tiền máy đếm đã nuốt.',
            'This chair has <b>never been closed</b>. This entry only records the baseline meter '
            + 'reading; from next time the difference becomes meaningful.') + '</div>'
        : hangSo(L('Chỉ số lần chốt trước','Meter at last closing'),
            Number(x.chi_so_truoc).toLocaleString('vi-VN')
            + (x.chot_truoc_luc ? ' <span class="mut">· ' + esc(String(x.chot_truoc_luc).slice(0,16))
                + (x.chot_truoc_ai ? ' · ' + esc(x.chot_truoc_ai) : '') + '</span>' : '')))
    + hangSo(L('Sổ ghi nhận từ lần chốt trước','On record since last closing'), tien(x.theo_he_thong))
    /* Doanh thu tháng của ghế chỉ hiện cho quản trị — người thu không cần, và không được xem. */
    + (r && r.thang
        ? hangSo(L('Tổng tháng này','Total this month') + ' · ' + r.thang.so_luot + ' '
            + L('lượt','sessions'), tien(r.thang.tong), ' to')
        : '')

    /* ---- Ô 1: CHỈ SỐ. Đọc trên màn máy đếm TRƯỚC khi mở ngăn. ---- */
    + '<div class="o-thu"><label class="mut">'
    + L('① Chỉ số trên màn máy đếm tiền','① Reading on the note counter')
    + '</label><input id="chot-cs" type="text" inputmode="numeric" value="'
    + esc(CHOT.gocs ? Number(CHOT.gocs).toLocaleString('vi-VN') : '') + '" placeholder="0"></div>'

    /* ---- Ô 2: TIỀN ĐẾM ĐƯỢC. ---- */
    + '<div class="o-thu"><label class="mut">'
    + L('② Tiền mặt đếm được trong ngăn','② Cash counted in the box') + '</label>'
    + '<input id="chot-tien" type="text" inputmode="numeric" value="'
    + esc(CHOT.go ? Number(CHOT.go).toLocaleString('vi-VN') : '') + '" placeholder="0"></div>'
    + banPhimSo()
    + '<div class="o-thu"><input id="chot-gc" type="text" placeholder="'
    + L('ghi chú — bắt buộc nếu vừa thay cục nhận tiền','note — required if the acceptor was replaced')
    + '"></div>'

    /* ⚠️ Nói thẳng hai điều người bấm hay hiểu nhầm: nút này KHÔNG mở ngăn, và nó KHÔNG cộng
       doanh thu. Bản trước thiếu vế thứ hai nên ai cũng tưởng bấm nhiều lần là mất tiền. */
    + '<div class="canh">'
    + L('Nút này <b>ghi sổ chốt ca</b>: nó không mở ngăn tiền, và <b>không cộng doanh thu</b> — '
        + 'tiền trong ngăn đã vào sổ từ lúc ghế nuốt. Số tiền đếm được sẽ tính vào phần '
        + '<b>anh/chị đang cầm</b> cho tới khi nộp về quầy.',
        'This <b>records a shift closing</b>: it does not open the cash box, and it does '
        + '<b>not add revenue</b> — that money entered the books when the chair swallowed it. '
        + 'The counted cash goes onto <b>what you are holding</b> until you hand it in.')
    + '</div>'
    + '<div class="hop-nut">'
    + '<button id="chot-huy" class="ghost">' + L('Thoát','Cancel') + '</button>'
    + '<button id="chot-ok" class="on">' + L('Chốt ca','Close shift') + '</button>'
    + '</div></div>';
  document.body.appendChild(d);

  var oCs = document.getElementById('chot-cs');
  var oTi = document.getElementById('chot-tien');
  var dang = oCs;                       // ô đang được gõ vào
  oCs.addEventListener('focus', function(){ dang = oCs; });
  oTi.addEventListener('focus', function(){ dang = oTi; });

  var doc_so = function (o) { return (o.value || '').replace(/\D/g, ''); };
  var ghi_so = function (o, v) {
    o.value = v ? Number(v).toLocaleString('vi-VN') : '';
    if (o === oCs) { CHOT.gocs = v; } else { CHOT.go = v; }
  };

  [].forEach.call(d.querySelectorAll('[data-phim]'), function(b){
    b.onclick = function(){
      var k = b.getAttribute('data-phim');
      var v = doc_so(dang);
      if (k === '⌫') v = v.slice(0, -1); else v = (v + k).slice(0, 12);
      ghi_so(dang, v);
      /* Gõ vào ô chỉ số thì con số "máy đếm nói đã nuốt" phải đổi theo ngay — đó là thứ cho
         người đứng đó biết mình vừa gõ nhầm một chữ số, TRƯỚC khi bấm chốt. */
      if (dang === oCs) veLaiDuKien();
    };
  });
  [oCs, oTi].forEach(function(o){
    o.addEventListener('input', function(){
      ghi_so(o, doc_so(o).slice(0, 12));
      if (o === oCs) veLaiDuKien();
    });
  });

  function veLaiDuKien(){
    var cu2 = d.querySelector('.du-kien');
    var v = Math.max(0, (Number(CHOT.gocs) || 0) - x.chi_so_truoc) * x.don_vi;
    if (cu2) cu2.remove();
    if (x.lan_dau || v <= 0) return;
    var e = document.createElement('div');
    e.className = 'so-hang du-kien';
    e.innerHTML = '<span class="nh">' + L('→ Máy đếm nói đã nuốt','→ Meter says it took')
      + '</span><span class="gt">' + tien(v) + '</span>';
    oCs.parentNode.parentNode.insertBefore(e, oCs.parentNode.nextSibling);
  }

  document.getElementById('chot-huy').onclick = dongChotCa;
  d.onclick = function(ev){ if (ev.target === d) dongChotCa(); };
  document.getElementById('chot-ok').onclick = bam('Chốt ca', function(){
    var cs  = Number(doc_so(oCs)) || 0;
    var dem = Number(doc_so(oTi)) || 0;
    var gc  = (document.getElementById('chot-gc').value || '').trim();
    if (cs <= 0) {
      alert(L('Chưa nhập chỉ số trên màn máy đếm tiền.','Enter the reading on the note counter.'));
      oCs.focus(); return;
    }
    /* Đếm được 0đ là chuyện CÓ THẬT (ca không ai dùng tiền mặt), nên không chặn — chỉ hỏi lại,
       vì nó cũng rất giống với "quên chưa gõ". */
    if (dem <= 0 && !confirm(L('Ngăn ghế ' + CHOT.ma + ' không có đồng nào?',
        'No cash at all in chair ' + CHOT.ma + '?'))) { oTi.focus(); return; }
    /* ⚠️ Chép mã ghế ra biến cục bộ TRƯỚC khi đóng màn: `dongChotCa()` đặt `CHOT = null`, nên
       đọc `CHOT.ma` sau đó là ném lỗi ngay giữa lượt ghi sổ. */
    var ma = CHOT.ma;
    dongChotCa();
    lam('chot_luu', { ma_may: ma, chi_so: cs, tien_dem: dem, ghi_chu: gc });
  });
  oCs.focus();
}

function dongChotCa(){
  var d = document.getElementById('man-chot');
  if (d) d.remove();
  CHOT = null;
}

/* Đồng hồ dải đầu trang. Mốc là giờ MÁY CHỦ (`D.luc`), sau đó tự tích từng giây — lấy giờ điện
   thoại thì nó lệch với mọi con số khác trên trang, mà hai loại giờ cạnh nhau là mời người ta
   đối chiếu nhầm. Mỗi lượt hỏi máy chủ lại đặt mốc, nên nó không trôi được. */
var dhTop = null;
function chayDongHoTop(){
  if (dhTop) { clearInterval(dhTop); dhTop = null; }
  var o = document.getElementById('dh-top');
  if (!o || !D || !D.luc) return;
  var m = String(D.luc).match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  if (!m) return;
  var g = Number(m[1]), p = Number(m[2]), gi = Number(m[3] || 0);
  var dau = String(D.luc).slice(0, m.index);
  function ve2(n){ return String(n).padStart(2,'0'); }
  dhTop = setInterval(function(){
    if (document.hidden) return;
    gi++; if (gi > 59) { gi = 0; p++; }
    if (p > 59) { p = 0; g = (g + 1) % 24; }
    o.textContent = dau + ve2(g) + ':' + ve2(p) + ':' + ve2(gi);
  }, 1000);
}

/* ════════════════════════════════════════════════════════════════════════════════════════════
 * GỬI MỘT LỆNH GHI, RỒI TẢI LẠI MÀN.
 *
 * 🔴 LỖI 23/08/2026 — BẤM "CHỐT CA" KHÔNG CÓ GÌ XẢY RA.
 *
 *    Anh Thắng: *"chưa chốt ca được phải không, chưa thấy ghi nhận"*.
 *
 *    Hàm này trước đây khai BÊN TRONG `noi()`. Mà `veChotCa()` — hàm dựng bảng chốt ca — nằm ở
 *    tầng ngoài, nên nó KHÔNG nhìn thấy `lam`. Bấm "Chốt ca" là JavaScript ném
 *    `ReferenceError: lam is not defined` vào console rồi im: bảng đóng lại (vì `dongChotCa()`
 *    chạy trước), không có lỗi nào hiện lên, và không có dòng nào vào sổ. Nhìn từ ngoài thì
 *    giống hệt "bấm xong không thấy ghi nhận".
 *
 *    Hàm khai trong một hàm khác chỉ sống trong đúng hàm đó — kể cả khi nơi gọi nó được BẮT ĐẦU
 *    từ bên trong hàm ấy. JavaScript đóng gói theo chỗ KHAI, không theo chỗ gọi.
 *
 * ⚠️ Nay khai ở tầng ngoài, cạnh `goi()` và `tai()` là hai thứ nó dùng. Và có phép thử quét mọi
 *    hàm khai lồng bên trong, đòi chúng không được gọi từ hàm khác — xem `kiem_pham_vi_js()`
 *    trong bộ thử.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function lam(viec, d){
  if (ban) return;
  ban = true;
  [].forEach.call(document.querySelectorAll('button'), function(b){ b.disabled = true; });
  goi(viec, d, function(r){
    ban = false;
    if (r && r.ok === false && r.error) alert(r.error);
    else if (r && r.thong_bao) alert(r.thong_bao);
    tai();
  });
}

function noi(){
  henLai();
  chayDongHo();
  chayDongHoTop();
  noiNN();
  document.getElementById('lam-moi').onclick = function(){ tai(); };
  document.getElementById('thoat').onclick = function(){
    goi('logout', {}, function(){ TOK = null; try{localStorage.removeItem('vhg_tok');}catch(e){} veLogin(''); });
  };
  [].forEach.call(document.querySelectorAll('[data-ky]'), function(b){
    b.onclick = function(){ KY = b.getAttribute('data-ky'); tai(); };
  });
  var kyTh = document.getElementById('ky-thang');
  if (kyTh) kyTh.onchange = function(){ if (/^\d{4}-\d{2}$/.test(kyTh.value)) { KY = kyTh.value; tai(); } };
  [].forEach.call(document.querySelectorAll('[data-tab]'), function(b){
    b.onclick = function(){
      TAB = b.getAttribute('data-tab');
      try { localStorage.setItem('vhg_tab', TAB); } catch(e) {}
      /* Vẽ lại từ dữ liệu ĐANG CÓ, không gọi lại máy chủ: đổi tab không phải đổi dữ liệu, và
         trên 4G mỗi lượt gọi thừa là một lần chờ. */
      ve();
    };
  });
  var bMoBc = document.getElementById('bc-mo-tai-day');
  if (bMoBc) bMoBc.onclick = function(){ if (window.VHG_BaoCao) window.VHG_BaoCao.mo(); };
  if (document.getElementById('bcp-luu') || document.querySelector('[data-bcpxoa]')) noiBcPin();
  if (document.getElementById('ktd-list')) ktdInit();
  if (document.getElementById('ktdn-list')) ktdnInit();
  if (document.getElementById('kti-congno')) ktiInit();
  if (document.getElementById('kls-wrap')) klsInit();
  if (document.getElementById('ktx-manop-wrap')) ktxInit();
  if (document.getElementById('ktn-csv')) ktnInit();
  if (document.getElementById('tt-wrap')) ttWire();
  if (document.getElementById('ql-wrap')) qlGheRender();
  if (document.getElementById('tm-wrap')) tmRender();
  [].forEach.call(document.querySelectorAll('[data-kd]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-kd');
      if (!confirm(L(
        'Khởi động lại ghế ' + m + '?\n\nGhế sẽ tự khởi động khi đang RẢNH — nếu có khách đang '
          + 'massage thì nó chờ hết lượt rồi mới khởi động, không cắt ngang.\n'
          + 'Sau khi khởi động, ghế mất khoảng 30 giây mới gửi nhịp lại.',
        'Reboot chair ' + m + '?\n\nThe chair reboots itself once it is IDLE — if someone is in it, '
          + 'it waits for the session to finish and does not cut in.\n'
          + 'After rebooting it takes about 30 seconds to send a heartbeat again.'))) return;
      lam('khoi_dong_lai', { ma_may: m });
    };
  });
  function so(attr, ma){
    var el = document.querySelector('[' + attr + '="' + ma + '"]');
    return el ? el.value : 0;
  }
  /* ⚠️ CHẶN BẤM HAI LẦN. Trên 4G một lượt bấm có thể mất 3 giây không thấy gì xảy ra, và phản
     xạ của mọi người là bấm lại. Với "Thu tiền mặt" thì bấm hai lần là GHI HAI LẦN — số tiền
     thật vào sổ gấp đôi. Khoá nút cho tới khi máy chủ trả lời. */
  [].forEach.call(document.querySelectorAll('[data-bat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-bat');
      var ly = prompt(L('Bật ghế ' + m + ' — đây là CHO KHÔNG một lượt.\nLý do:',
        'Start chair ' + m + ' — this is a FREE session.\nReason:')); if (ly === null) return;
      lam('bat', { ma_may: m, phut: so('data-phut', m), ly_do: ly }); };
  });
  [].forEach.call(document.querySelectorAll('[data-tat]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-tat');
      if (!confirm(L('Tắt ghế ' + m + ' ngay?','Stop chair ' + m + ' now?'))) return;
      lam('tat', { ma_may: m }); };
  });
  [].forEach.call(document.querySelectorAll('[data-mokhoa]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-mokhoa');
      if (!confirm(L('Mở khoá lỗi ghế ' + m + '?\n\nChỉ mở khi đã kiểm và ghế chạy lại bình thường '
          + '— mở khoá xong ghế nhận khách lại. Ghế nhận lệnh trong ~10 giây.',
          'Unlock chair ' + m + '?\n\nOnly unlock after checking the chair runs again — it will accept '
          + 'customers afterwards. The chair picks up the command in ~10s.'))) return;
      lam('mo_khoa', { ma_may: m }); };
  });
  [].forEach.call(document.querySelectorAll('[data-teston]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-teston');
      if (!confirm(L('BẬT chế độ kỹ thuật cho ghế ' + m + '?\n\nGhế sẽ KHÔNG khoá lỗi khi dừng — '
          + 'lỗi chỉ báo 5 giây rồi tự hết. Dùng khi đang sửa/test. Tự tắt sau 15 phút.',
          'Enable technician mode for chair ' + m + '?\n\nThe chair will NOT lock on stop — errors show '
          + 'for 5s then clear. Use while servicing/testing. Auto-off after 15 minutes.'))) return;
      lam('test', { ma_may: m, bat: 1 }); };
  });
  [].forEach.call(document.querySelectorAll('[data-testoff]'), function(b){
    b.onclick = function(){ var m = b.getAttribute('data-testoff');
      lam('test', { ma_may: m, bat: 0 }); };
  });
  [].forEach.call(document.querySelectorAll('[data-gan]'), function(b){
    b.onclick = function(){
      var cu = b.getAttribute('data-gan');
      var o  = document.querySelector('[data-gma="' + cu + '"]');
      var cs = document.querySelector('[data-gcs="' + cu + '"]');
      var moi = (o && o.value || '').trim();
      if (!moi) { alert(L('Chưa nhập mã ghế.','Enter a chair code.')); if (o) o.focus(); return; }
      /* Chặn ngay trên máy: mã đi vào nội dung chuyển khoản khách GÕ TAY, có dấu hay khoảng
         trắng là khách gõ sai và ghế không chạy. Máy chủ cũng chặn — chặn hai lớp vì câu báo
         lỗi ở đây tới ngay, còn đi một vòng máy chủ thì trên 4G là vài giây đứng nhìn. */
      if (!/^[A-Za-z0-9]{1,20}$/.test(moi)) {
        alert(L('Mã chỉ được gồm chữ và số, không dấu, không khoảng trắng.',
          'The code may contain letters and digits only — no accents, no spaces.')); return;
      }
      lam('gan_ma', { ma_cu: cu, ma_moi: moi, coso_id: cs ? cs.value : 0 });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-mat]'), function(b){
    b.onclick = function(){ moChotCa(b.getAttribute('data-mat')); };
  });

  /* ---- TAB QUẢN LÝ GHẾ: địa điểm + ghế ---- */
  var _e;
  if ((_e = document.getElementById('cs-them'))) _e.onclick = function(){
    var t = (document.getElementById('cs-ten').value || '').trim();
    var tinh = (document.getElementById('cs-tinh').value || '').trim();
    if (!t) { alert(L('Nhập tên địa điểm.','Enter a site name.')); return; }
    lam('coso_luu', { id: 0, ten: t, tinh: tinh });
  };
  [].forEach.call(document.querySelectorAll('[data-cssua]'), function(b){
    b.onclick = function(){
      var t = prompt(L('Đổi tên địa điểm:','Rename site:'), b.getAttribute('data-csten'));
      if (t === null) return; t = t.trim(); if (!t) return;
      var tinh = prompt(L('Tỉnh/TP của địa điểm (để lọc theo địa bàn):','Province/City:'), b.getAttribute('data-cstinh') || '');
      if (tinh === null) tinh = b.getAttribute('data-cstinh') || '';
      lam('coso_luu', { id: b.getAttribute('data-cssua'), ten: t, tinh: tinh.trim() });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-csxoa]'), function(b){
    b.onclick = function(){
      var nhan = b.getAttribute('data-csnhan');
      if (!confirm(L('Xoá địa điểm "' + nhan + '"?\nGhế của địa điểm này thành "chưa gán", KHÔNG bị xoá.',
        'Delete site "' + nhan + '"?\nIts chairs become "unassigned" — they are NOT deleted.'))) return;
      lam('coso_xoa', { id: b.getAttribute('data-csxoa') });
    };
  });
  if ((_e = document.getElementById('ma-them'))) _e.onclick = function(){
    var m = (document.getElementById('ma-moi').value || '').trim();
    if (!m) { alert(L('Nhập mã ghế.','Enter a chair code.')); return; }
    if (!/^[A-Za-z0-9]{1,20}$/.test(m)) {
      alert(L('Mã chỉ gồm chữ và số, không dấu, không khoảng trắng.',
        'The code may contain letters and digits only — no accents, no spaces.')); return;
    }
    lam('may_them', { ma: m, coso_id: document.getElementById('ma-cs').value });
  };
  [].forEach.call(document.querySelectorAll('[data-mxoa]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-mxoa');
      if (!confirm(L('Xoá ghế ' + m + '?\nChỉ xoá cấu hình ghế — doanh thu đã ghi giữ nguyên.',
        'Delete chair ' + m + '?\nOnly the chair config is removed — recorded revenue is kept.'))) return;
      lam('may_xoa', { ma: m });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-csma]'), function(s){
    s.onchange = function(){
      lam('may_coso', { ma: s.getAttribute('data-csma'), coso_id: s.value });  // đổi cơ sở, giữ giá
    };
  });
  if ((_e = document.getElementById('ql-loc'))) _e.onchange = function(){
    QL_LOC = _e.value; QL_PG = 0; qlGheRender();   // lọc + vẽ lại DANH SÁCH tại chỗ (không cả trang)
  };
  if ((_e = document.getElementById('dk-loc'))) _e.onchange = function(){
    DK_LOC = _e.value; ve();   // lọc lưới ghế tab Điều khiển
  };

  /* ---- QUỸ: nộp tiền về quầy -----------------------------------------------------------
     ⚠️ Hỏi lại TRƯỚC khi nộp, và nói rõ con số. Nộp là nộp hết, và sau đó chỉ quản lý mới gỡ
        ra được — một cú bấm nhầm ở đây là người nộp phải đi tìm quản lý. */
  /* ══════════════════════════════════════════════════════════════════════════════════════════
   * QUÉT QR TRÊN GHẾ ĐỂ CHỐT CA.
   *
   * Anh Thắng 23/08/2026: *"Nhân viên đăng nhập vẫn trang này, nhưng chỉ hiện mỗi chốt ca. Để
   * chốt ca ghế nào thì quét QR ghế đó."*
   *
   * 🔴 QUÉT CHỨ KHÔNG CHỌN TỪ DANH SÁCH. Danh sách ghế là mời người ta bấm nhầm — hai ghế cạnh
   *    nhau tên chỉ khác một chữ số, mà chốt nhầm ghế thì KHÔNG chỉ ghi sai sổ: nó đóng mốc chỉ
   *    số của ghế kia, và người thu thật ở đó hôm sau sẽ thấy quãng bị cắt mất.
   *    Quét cái tem dán trên chính cái ghế vừa mở ngăn thì không nhầm được.
   *
   * ⚠️ `BarcodeDetector` chỉ có trên Chrome/Android và vài trình duyệt khác. Không có thì nói
   *    thẳng ra và chỉ sang ô gõ tay — đừng để nút bấm vào không phản ứng gì, người ta sẽ bấm
   *    mười lần rồi tưởng máy hỏng.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  var quetMo = document.getElementById('quet-mo');
  if (quetMo) quetMo.onclick = bam('Quét QR', function(){ moQuet(); });
  var quetDi = document.getElementById('quet-di');
  if (quetDi) quetDi.onclick = bam('Mở bảng chốt ca', function(){
    var o = document.getElementById('quet-tay');
    var ma = ((o && o.value) || '').trim().toUpperCase();
    if (!ma) { bcQuet(L('Gõ mã ghế, hoặc bấm nút quét ở trên.','Type a chair code, or tap scan above.')); return; }
    moChotCa(ma);
  });
  var qt_ = document.getElementById('quet-tay');
  if (qt_) qt_.onkeydown = function(ev){ if (ev.key === 'Enter') quetDi.click(); };

  /* ---- CẤU HÌNH: thêm/xoá người, lưu phân quyền, lưu đơn vị chỉ số ----------------------
     ⚠️ Sau mỗi lượt ghi phải XOÁ `CH` rồi tải lại — giữ bảng cũ trên màn là người khai vừa thêm
        một người mà không thấy họ đâu, rồi thêm lần nữa. */
  var chThem = document.getElementById('ch-them');
  if (chThem) chThem.onclick = function(){
    if (ban) return;
    var e = document.getElementById('ch-e');
    var ten = (document.getElementById('ch-ten').value || '').trim();
    var pin = (document.getElementById('ch-pin').value || '').trim();
    if (!ten) { e.textContent = L('Chưa nhập họ tên.','Enter a full name.'); return; }
    if (!/^\d{4,8}$/.test(pin)) { e.textContent = L('PIN phải gồm 4–8 chữ số.','PIN must be 4–8 digits.'); return; }
    e.textContent = '';
    CH = null;
    lam('ch_them', { ten: ten, pin: pin,
      vai_tro: document.getElementById('ch-vt').value,
      coso: document.getElementById('ch-cs').value });
  };
  [].forEach.call(document.querySelectorAll('[data-chxoa]'), function(b){
    b.onclick = function(){
      if (ban) return;
      var ten = b.getAttribute('data-chten') || '';
      var laBan = b.getAttribute('data-chban') === '1';
      var hoi = laBan
        ? L('Xoá dòng "' + ten + '" — ĐÂY LÀ TÀI KHOẢN BẠN ĐANG ĐĂNG NHẬP.\nPhiên hiện tại vẫn chạy, nhưng PIN này sẽ không đăng nhập lại được. Chỉ xoá khi còn Admin khác. Tiếp tục?',
                'Remove "' + ten + '" — THIS IS YOUR OWN LOGIN. Your current session stays, but this PIN can no longer sign in. Only do this if another Admin exists. Continue?')
        : L('Xoá "' + ten + '"? Họ sẽ không đăng nhập được nữa.',
                'Remove "' + ten + '"? They will no longer be able to sign in.');
      if (!confirm(hoi)) return;
      CH = null;
      lam('ch_xoa', { i: Number(b.getAttribute('data-chxoa')) });
    };
  });
  var chVt = document.getElementById('ch-luu-vt');
  if (chVt) chVt.onclick = function(){
    if (ban) return;
    var g = { vao: [], giup: [], chot: [], quantri: [] };
    [].forEach.call(document.querySelectorAll('[data-ph]'), function(o){
      if (o.checked) g[o.getAttribute('data-ph')].push(o.value);
    });
    CH = null;
    lam('ch_vai_tro', g);
  };
  var chDv = document.getElementById('ch-luu-dv');
  if (chDv) chDv.onclick = function(){
    if (ban) return;
    var v = Number((document.getElementById('ch-dv').value || '').replace(/\D/g, '')) || 0;
    if (v <= 0) {
      document.getElementById('ch-e3').textContent =
        L('Mỗi đơn vị phải lớn hơn 0 đồng.','The unit must be greater than 0.');
      return;
    }
    CH = null;
    lam('ch_don_vi', { don_vi: v });
  };

  var nopOk = document.getElementById('nop-ok');
  if (nopOk) nopOk.onclick = function(){
    if (ban) return;
    var q = (D.quy && D.quy.toi) ? D.quy.toi : { tong: 0 };
    if (!confirm(L('Nộp toàn bộ ' + tien(q.tong) + ' đang cầm về quầy?',
                   'Hand in all ' + tien(q.tong) + ' you are holding?'))) return;
    var gc = (document.getElementById('nop-gc') || {}).value || '';
    lam('nop_tao', { ghi_chu: gc });
  };

  /* ---- QUỸ: quản lý xác nhận đã nhận ---------------------------------------------------
     🔴 Số tiền nhận lấy từ ô CẠNH ĐÚNG DÒNG ĐÓ, không lấy từ một ô chung. Bảng này có nhiều
        dòng, và một ô chung là xác nhận nhầm số của người khác. */
  [].forEach.call(document.querySelectorAll('[data-nhan]'), function(b){
    b.onclick = function(){
      if (ban) return;
      var id = Number(b.getAttribute('data-nhan'));
      var o  = document.querySelector('[data-nhan-so="' + id + '"]');
      var v  = Number(((o && o.value) || '').replace(/\D/g, '')) || 0;
      if (!confirm(L('Xác nhận đã nhận ' + tien(v) + '?','Confirm receipt of ' + tien(v) + '?'))) return;
      lam('nop_nhan', { id: id, so_tien_nhan: v });
    };
  });
  [].forEach.call(document.querySelectorAll('[data-nophuy]'), function(b){
    b.onclick = function(){
      if (ban) return;
      if (!confirm(L('Huỷ lượt nộp này? Tiền quay lại tay người nộp.',
                     'Cancel this hand-in? The cash returns to the person who submitted it.'))) return;
      lam('nop_huy', { id: Number(b.getAttribute('data-nophuy')) });
    };
  });

  /* 🔴 TRAO QUÀ: bấm xong TẢI LẠI danh sách, kể cả khi hỏng. Hỏng ở đây gần như luôn có nghĩa
     là người khác vừa trao xong phần quà đó — danh sách trên màn này đã cũ, giữ nguyên nó là
     nhân viên bấm tiếp vào những dòng không còn tồn tại. */
  [].forEach.call(document.querySelectorAll('[data-trao]'), function(b){
    b.onclick = function(){
      if (b.disabled) return;
      b.disabled = true; b.textContent = L('Đang ghi…','Saving…');
      goi('qua_trao', { id: Number(b.getAttribute('data-trao')) }, function(r){
        if (!r.ok) { alert(r.error || L('Không ghi được.','Could not save.')); }
        tai(true);
      });
    };
  });

  /* Tra ví hộ khách rồi vẽ ra các gói bấm được — cùng lối với trang khách, để nhân viên và
     khách nhìn thấy cùng một thứ và không cãi nhau về con số. */
  var nvTra = document.getElementById('nv-tra');
  if (nvTra) nvTra.onclick = function(){
    var e = document.getElementById('nv-e'), kq = document.getElementById('nv-kq');
    var sdt = (document.getElementById('nv-sdt').value || '').trim();
    e.textContent = ''; kq.innerHTML = L('Đang tra…','Looking up…');
    goi('vi_tra_nv', { sdt: sdt }, function(r){
      if (!r.ok) { kq.innerHTML = ''; e.textContent = r.error || L('Không tra được.','Lookup failed.'); return; }
      NV_VI = { sdt: sdt, sd: r.so_du };
      var sd = r.so_du || {};
      var hh = '<div class="ok" style="margin-top:10px">'
        + L('Số dư tiêu được: ','Available: ') + '<b>' + tien(sd.dung || 0) + '</b>'
        + (sd.cho > 0 ? '<br>⏳ ' + tien(sd.cho) + ' ' + L('đang trong hạn chờ','on hold') : '')
        + (sd.khoa ? '<br><b style="color:#ff6b6b">' + L('VÍ ĐANG KHOÁ','WALLET LOCKED') + '</b>' : '')
        + '</div>';
      /* Chọn ghế + gói. Ở đây PHẢI có ô chọn ghế: nhân viên đứng ở quầy, không quét tem nào cả. */
      hh += '<div class="act" style="margin-top:10px"><select id="nv-ghe">';
      (D.may || []).forEach(function(m){
        hh += '<option value="' + esc(m.ma) + '">' + esc(m.ma)
          + (m.coso ? ' · ' + esc(m.coso) : '') + '</option>';
      });
      hh += '</select><select id="nv-goi">';
      (D.goi || []).forEach(function(g){
        hh += '<option value="' + g.menh_gia + '"' + ((sd.dung || 0) < g.menh_gia ? ' disabled' : '')
          + '>' + esc(g.ten || '') + ' · ' + tien(g.menh_gia)
          + ((sd.dung || 0) < g.menh_gia ? ' — ' + L('chưa đủ số dư','not enough') : '') + '</option>';
      });
      hh += '</select><button id="nv-chay" class="on">'
        + L('Trừ ví, chạy ghế','Charge & start') + '</button></div>';
      kq.innerHTML = hh;
      noi();
    });
  };

  var nvChay = document.getElementById('nv-chay');
  if (nvChay) nvChay.onclick = function(){
    var e = document.getElementById('nv-e'), kq = document.getElementById('nv-kq');
    if (!NV_VI) { e.textContent = L('Tra số dư trước đã.','Look up the balance first.'); return; }
    var mg = Number((document.getElementById('nv-goi') || {}).value || 0);
    var may = (document.getElementById('nv-ghe') || {}).value || '';
    if (!mg || !may) { e.textContent = L('Chọn ghế và gói.','Pick a chair and a package.'); return; }
    /* 🔴 HỎI LẠI MỘT CÂU. Đây là trừ tiền của NGƯỜI KHÁC mà không có PIN của họ — một cú bấm
       nhầm ghế là khách mất tiền cho một cái ghế trống. Trang của khách thì không hỏi, vì ở đó
       chính chủ bấm và đã ngồi sẵn trên ghế. */
    if (!confirm(Lf2(L('Trừ {0} của ví này và chạy ghế ','Charge {0} from this wallet and start chair '), tien(mg)) + may + '?')) return;
    nvChay.disabled = true; e.textContent = '';
    goi('vi_tieu_nv', { sdt: NV_VI.sdt, menh_gia: mg, ma_may: may }, function(r){
      nvChay.disabled = false;
      if (!r.ok) { e.textContent = r.error || L('Không chạy được.','Could not start.'); return; }
      kq.innerHTML = '<div class="ok" style="margin-top:10px">' + esc(r.thong_bao) + '</div>';
      NV_VI = null;
      tai(true);
    });
  };

  var mtra = document.getElementById('ma-tra');
  if (mtra) mtra.onclick = function(){
    var e = document.getElementById('ma-e');
    e.textContent = L('Đang tra…','Looking up…');
    goi('ma_tra', { sdt: document.getElementById('ma-sdt').value }, function(r){
      if (!r.ok) { e.textContent = r.error || L('Không tra được.','Lookup failed.'); MA_TRA = null; return; }
      e.textContent = ''; MA_TRA = r; ve();
    });
  };
  [].forEach.call(document.querySelectorAll('[data-mahuy]'), function(b){
    b.onclick = function(){
      var m = b.getAttribute('data-mahuy');
      /* Bắt ghi LÝ DO, và nói thẳng là tiền KHÔNG tự hoàn — người bấm phải biết mình vừa làm
         gì và chưa làm gì. */
      var ly = prompt(L('Huỷ mã ' + m + '?\n\nTiền đã thu KHÔNG tự hoàn — hoàn ở ngân hàng, và '
          + 'huỷ dòng tiền ở tab Đối soát nếu cần.\nLý do huỷ:',
        'Cancel code ' + m + '?\n\nThe money collected is NOT refunded automatically — refund at '
          + 'the bank, and cancel the revenue row in Reconciliation if needed.\nReason:'));
      if (ly === null) return;
      lam('ma_huy', { ma: m, ly_do: ly });
    };
  });
}

/* Mở lại trang sau khi khoá màn: hỏi NGAY chứ đừng đợi hết nhịp. Người ta mở ra là để xem
   ngay bây giờ, không phải để nhìn số liệu của 30 giây trước. */
document.addEventListener('visibilitychange', function(){
  if (!document.hidden && TOK && !ban && !CHOT) tai(true);
});

if (TOK) tai(); else veLogin('');
})();
JS;
	}
}
