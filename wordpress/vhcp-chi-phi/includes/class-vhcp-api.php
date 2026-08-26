<?php
/**
 * REST API — 1 cửa duy nhất: POST /wp-json/vhcp/v1/call  {fn, args:[...], token}
 *
 * Giao diện vẫn gọi google.script.run.<tên hàm>(...) như cũ; assets/js/gas-shim.js
 * dịch mỗi lệnh gọi đó thành 1 request tới đây. Nhờ vậy toàn bộ Index.html của app
 * Apps Script chạy nguyên vẹn trên WordPress, không phải viết lại giao diện.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_API {

	/** Hàm chạy được KHI CHƯA đăng nhập. */
	private static $public_fns = array( 'login' );

	/**
	 * Hàm chỉ dành cho vai trò nhất định — chặn ngay ở máy chủ, không tin giao diện.
	 * (App Apps Script cũ KHÔNG có lớp này: ai có link đều gọi được getUsers để đọc PIN
	 *  của mọi người. Danh sách dưới khớp đúng những tab mà giao diện vốn chỉ cho
	 *  Admin/Quản lý thấy, nên người dùng không thấy khác gì.)
	 */
	private static function required_roles( $fn ) {
		// Sửa hàng loạt NGÀY của dòng chi là đụng thẳng vào số liệu kế toán (ngày quyết định
		// kỳ hạch toán). Chốt ở máy chủ, không tin mỗi giao diện.
		$admin_only = array( 'deleteDonAdmin', 'unmarkExportedSoChi', 'suaNamVoLy', 'suaNgayHong', 'suaKyHong', 'setDonNgay',
			// Khôi phục bảng người dùng là đụng thẳng vào ai đăng nhập được — chỉ Admin.
			'listUserBak', 'khoiPhucUsers',
			// Đổi tên cơ sở là sửa hàng loạt trên bốn bảng dữ liệu — chỉ Admin.
			'doiTenCoSo' );
		// Việc của NGƯỜI DUYỆT / KẾ TOÁN — nhân viên KHÔNG được gọi, bất kể bảng phân quyền
		// khai gì. Bảng đó nạp từ bảng tính cũ có thể lệch cột, mà đây là chỗ đụng tới tiền
		// của người khác nên phải chốt ở máy chủ.
		$nguoi_duyet = array(
			'duyetTamUng', 'capTamUng', 'duyetTamUngNhieu', 'capTamUngNhieu',
			'traLaiDon', 'traLaiDonNhieu', 'xacNhanQuyetToanCN', 'xacNhanQuyetToanNCC',
			'xacNhanQtCnNhieu', 'setTatToanTuan', 'setSoDuDauKy', 'dongCuaCoSo',
			'setLineThucMua', 'setLineCN',
			/* Đẩy tiền sang sổ của đơn vị khác — không phải việc của nhân viên. */
			'chuyenDonVi',
		);
		if ( in_array( $fn, $nguoi_duyet, true ) ) {
			return array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' );
		}
		$cau_hinh   = array( 'getUsers', 'cosoLa', 'saveConfig', 'undoConfig', 'setQuyen', 'resetQuyen', 'getQuyenConfig', 'migrateOldImages', 'ganMaTaiKhoanSoChi', 'ganMaTaiKhoanDon', 'ganMaTaiKhoanTatCa', 'dongBoTkLoai', 'xoaLoaiTuTao', 'getTaiKhoan', 'ghepHeThongTk', 'doMangTuTaiKhoan', 'khaiChiPhiChoCoSo', 'loaiCuaCoSo', 'datLoaiChoCoSo' );
		if ( in_array( $fn, $admin_only, true ) ) { return array( 'Admin' ); }
		// Kế toán cũng phải vào được Cấu hình (khai mã tài khoản, tên MISA, mã đơn vị là
		// việc của kế toán). Riêng tài khoản Admin thì chỉ Admin sửa — chặn trong
		// VHCP_Cfg::save_config() theo vai trò người đang gọi.
		if ( in_array( $fn, $cau_hinh, true ) )   { return array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ); }
		return array();
	}

	public static function register_routes() {
		register_rest_route( 'vhcp/v1', '/call', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'handle' ),
		) );
	}

	/**
	 * CỔNG DỰ PHÒNG QUA admin-ajax.php.
	 *
	 * Kha khá hosting (LiteSpeed/ModSecurity) và plugin bảo mật chặn thẳng /wp-json/ rồi
	 * trả 403 kèm trang HTML — app không gọi được gì mà cũng không biết vì sao. Cổng này
	 * dùng CHUNG bộ xử lý với REST, chỉ khác đường vào; giao diện tự chuyển sang đây khi
	 * gặp 403/404/405 hoặc phản hồi không phải JSON.
	 */
	public static function ajax() {
		$fn   = isset( $_POST['fn'] ) ? sanitize_text_field( wp_unslash( $_POST['fn'] ) ) : '';
		$tok  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$args = array();
		if ( isset( $_POST['args'] ) ) {
			$tmp = json_decode( (string) wp_unslash( $_POST['args'] ), true );
			if ( is_array( $tmp ) ) { $args = $tmp; }
		}

		$req = new WP_REST_Request( 'POST', '/vhcp/v1/call' );
		$req->set_param( 'fn', $fn );
		$req->set_param( 'args', $args );
		$req->set_param( 'token', $tok );

		$res = self::handle( $req );
		status_header( (int) $res->get_status() );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $res->get_data() );
		wp_die();
	}

	/**
	 * Nhận lệnh ngay trên URL của app (…/chi-phi/?vhcp_api=1).
	 *
	 * Cloudflare và tường lửa hosting chặn theo ĐƯỜNG DẪN: /wp-json/ và
	 * /wp-admin/admin-ajax.php trả 403 "Checking your browser", còn trang app thì mở
	 * được. Đường này dùng đúng đường dẫn đã mở được đó nên không bị chặn.
	 *
	 * Nhận cả body JSON và form-data để giao diện gửi kiểu nào cũng xong.
	 */
	public static function trang() {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );

		$fn = ''; $tok = ''; $args = array();

		$raw = file_get_contents( 'php://input' );
		$j   = ( $raw !== '' && $raw !== false ) ? json_decode( (string) $raw, true ) : null;
		if ( is_array( $j ) ) {
			$fn  = isset( $j['fn'] ) ? sanitize_text_field( (string) $j['fn'] ) : '';
			$tok = isset( $j['token'] ) ? sanitize_text_field( (string) $j['token'] ) : '';
			if ( isset( $j['args'] ) && is_array( $j['args'] ) ) { $args = $j['args']; }
		} else {
			$fn  = isset( $_POST['fn'] ) ? sanitize_text_field( wp_unslash( $_POST['fn'] ) ) : '';
			$tok = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			if ( isset( $_POST['args'] ) ) {
				$tmp = json_decode( (string) wp_unslash( $_POST['args'] ), true );
				if ( is_array( $tmp ) ) { $args = $tmp; }
			}
		}
		if ( $tok === '' && isset( $_SERVER['HTTP_X_VHCP_TOKEN'] ) ) {
			$tok = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_VHCP_TOKEN'] ) );
		}

		// GET không kèm lệnh: dùng để giao diện thử xem đường này có đi được không.
		if ( $fn === '' ) {
			status_header( 200 );
			echo wp_json_encode( array( 'ok' => true, 'data' => array( 'song' => true, 'ver' => VHCP_VERSION ) ) );
			exit;
		}

		$req = new WP_REST_Request( 'POST', '/vhcp/v1/call' );
		$req->set_param( 'fn', $fn );
		$req->set_param( 'args', $args );
		$req->set_param( 'token', $tok );

		$res = self::handle( $req );
		status_header( (int) $res->get_status() );
		echo wp_json_encode( $res->get_data() );
		exit;
	}

	/** Bảng tên hàm (như bên Apps Script) → callable PHP. */
	public static function map() {
		return array(
			// phiên & nhật ký
			'login'                 => array( 'VHCP_Auth', 'login' ),
			'changePin'             => array( 'VHCP_Auth', 'change_pin' ),
			'vhcpLogout'            => array( 'VHCP_Auth', 'logout' ),
			'logAction'             => array( 'VHCP_Log', 'log_action' ),
			'getLog'                => array( 'VHCP_Log', 'get_log' ),
			'getDonLog'             => array( 'VHCP_Don', 'nhat_ky_don' ),
			'timDon'                => array( 'VHCP_Don', 'tim_don' ),
			'dsLoaiChiPhi'          => array( 'VHCP_Don', 'ds_loai_chi_phi' ),

			// khởi động & cấu hình
			'getBootstrap'          => array( 'VHCP_Don', 'get_bootstrap' ),
			'getConfig'             => array( 'VHCP_Cfg', 'get_config' ),
			'saveConfig'            => array( 'VHCP_Cfg', 'save_config' ),
			'undoConfig'            => array( 'VHCP_Cfg', 'undo_config' ),
			'getUsers'              => array( 'VHCP_Cfg', 'get_users' ),
			'listUserBak'           => array( 'VHCP_Cfg', 'list_user_bak' ),
			'khoiPhucUsers'         => array( 'VHCP_Cfg', 'khoi_phuc_users' ),
			'cosoLa'                => array( 'VHCP_Cfg', 'coso_la' ),
			'doiTenCoSo'            => array( 'VHCP_Cfg', 'doi_ten_coso' ),
			'getQuyen'              => array( 'VHCP_Cfg', 'get_quyen' ),
			'getQuyenConfig'        => array( 'VHCP_Cfg', 'get_quyen_config' ),
			'setQuyen'              => array( 'VHCP_Cfg', 'set_quyen' ),
			'resetQuyen'            => array( 'VHCP_Cfg', 'reset_quyen' ),

			'dongCuaCoSo'           => array( 'VHCP_Cfg', 'dong_cua_coso' ),

			// đơn vận hành
			'listDons'              => array( 'VHCP_Don', 'list_dons' ),
			'createDon'             => array( 'VHCP_Don', 'create_don' ),
			'getDon'                => array( 'VHCP_Don', 'get_don' ),
			'setTamUng'             => array( 'VHCP_Don', 'set_tam_ung' ),
			'setDuPhong'            => array( 'VHCP_Don', 'set_du_phong' ),
			'setTuExtra'            => array( 'VHCP_Don', 'set_tu_extra' ),
			'addLine'               => array( 'VHCP_Don', 'add_line' ),
			'updateLine'            => array( 'VHCP_Don', 'update_line' ),
			'setLineThucMua'        => array( 'VHCP_Don', 'set_line_thuc_mua' ),
			'setLineCN'             => array( 'VHCP_Don', 'set_line_cn' ),
			'setLineAnh'            => array( 'VHCP_Don', 'set_line_anh' ),
			'setLineNgay'           => array( 'VHCP_Don', 'set_line_ngay' ),
			'setDonNgay'            => array( 'VHCP_Don', 'set_don_ngay' ),
			'suaNamVoLy'            => array( 'VHCP_Don', 'sua_nam_vo_ly' ),
			'suaNgayHong'           => array( 'VHCP_Don', 'sua_ngay_hong' ),
			'suaKyHong'             => array( 'VHCP_Don', 'sua_ky_hong' ),
			'deleteLine'            => array( 'VHCP_Don', 'delete_line' ),
			'duplicateLine'         => array( 'VHCP_Don', 'duplicate_line' ),
			'guiDuyetTamUng'        => array( 'VHCP_Don', 'gui_duyet_tam_ung' ),
			'guiQuyetToan'          => array( 'VHCP_Don', 'gui_quyet_toan' ),
			'saveQuyetToan'         => array( 'VHCP_Don', 'save_quyet_toan' ),
			'setHoaDonQT'           => array( 'VHCP_Don', 'set_hoa_don_qt' ),
			'duyetTamUng'           => array( 'VHCP_Don', 'duyet_tam_ung' ),
			'capTamUng'             => array( 'VHCP_Don', 'cap_tam_ung' ),
			'xacNhanQuyetToanCN'    => array( 'VHCP_Don', 'xac_nhan_quyet_toan_cn' ),
			'xacNhanQuyetToanNCC'   => array( 'VHCP_Don', 'xac_nhan_quyet_toan_ncc' ),
			'traLaiDon'             => array( 'VHCP_Don', 'tra_lai_don' ),
			'deleteDon'             => array( 'VHCP_Don', 'delete_don' ),
			'deleteDonAdmin'        => array( 'VHCP_Don', 'delete_don_admin' ),
			'setTatToanTuan'        => array( 'VHCP_Don', 'set_tat_toan_tuan' ),
			/* Đẩy đơn / dòng chi sang đơn vị khác — kế toán POSH gửi cho kế toán cá nhân. */
			'chuyenDonVi'           => array( 'VHCP_Don', 'chuyen_don_vi' ),
			'dsDonVi'               => array( 'VHCP_DonVi', 'ds' ),
			'duyetTamUngNhieu'      => array( 'VHCP_Don', 'duyet_tam_ung_nhieu' ),
			'capTamUngNhieu'        => array( 'VHCP_Don', 'cap_tam_ung_nhieu' ),
			'traLaiDonNhieu'        => array( 'VHCP_Don', 'tra_lai_don_nhieu' ),
			'khongDungTamUng'       => array( 'VHCP_Don', 'khong_dung_tam_ung' ),
			'xacNhanQTCNNhieu'      => array( 'VHCP_Don', 'xac_nhan_qt_cn_nhieu' ),
			'getSoDuDauKy'          => array( 'VHCP_Don', 'get_so_du_dau_ky' ),
			'setSoDuDauKy'          => array( 'VHCP_Don', 'set_so_du_dau_ky' ),

			// sổ chi phí (nhập phẳng: chọn loại chi phí -> nhập)
			'listSoChi'             => array( 'VHCP_SoChi', 'list_chi' ),
			'addSoChi'              => array( 'VHCP_SoChi', 'add' ),
			'updateSoChi'           => array( 'VHCP_SoChi', 'update' ),
			'deleteSoChi'           => array( 'VHCP_SoChi', 'delete' ),
			'exportMisaSoChi'       => array( 'VHCP_SoChi', 'export_misa' ),
			'markExportedSoChi'     => array( 'VHCP_SoChi', 'mark_exported' ),
			'unmarkExportedSoChi'   => array( 'VHCP_SoChi', 'unmark_exported' ),
			'ganMaTaiKhoanSoChi'    => array( 'VHCP_SoChi', 'gan_ma_tai_khoan' ),
			'ganMaTaiKhoanDon'      => array( 'VHCP_Don', 'gan_ma_tai_khoan' ),

			// chi phí kỹ thuật (dự án)
			'createDuAn'            => array( 'VHCP_DuAn', 'create_du_an' ),
			'ensureCoSoChung'       => array( 'VHCP_DuAn', 'ensure_co_so_chung' ),
			'listDuAn'              => array( 'VHCP_DuAn', 'list_du_an' ),
			'renameDuAn'            => array( 'VHCP_DuAn', 'rename_du_an' ),
			'getDuAn'               => array( 'VHCP_DuAn', 'get_du_an' ),
			'addDuAnLine'           => array( 'VHCP_DuAn', 'add_line' ),
			'updateDuAnLine'        => array( 'VHCP_DuAn', 'update_line' ),
			'deleteDuAnLine'        => array( 'VHCP_DuAn', 'delete_line' ),
			'submitDuAn'            => array( 'VHCP_DuAn', 'submit' ),
			'approveDuAn'           => array( 'VHCP_DuAn', 'approve' ),
			'returnDuAn'            => array( 'VHCP_DuAn', 'ret' ),
			'closeDuAn'             => array( 'VHCP_DuAn', 'close' ),
			'reopenDuAn'            => array( 'VHCP_DuAn', 'reopen' ),
			'deleteDuAn'            => array( 'VHCP_DuAn', 'delete' ),
			'confirmDuAnPay'        => array( 'VHCP_DuAn', 'confirm_pay' ),
			'unconfirmDuAnPay'      => array( 'VHCP_DuAn', 'unconfirm_pay' ),

			// marketing
			'createMkDon'           => array( 'VHCP_MK', 'create_don' ),
			'listMkDon'             => array( 'VHCP_MK', 'list_don' ),
			'getMkDon'              => array( 'VHCP_MK', 'get_don' ),
			'addMkDonLine'          => array( 'VHCP_MK', 'add_line' ),
			'updateMkDonLine'       => array( 'VHCP_MK', 'update_line' ),
			'deleteMkDonLine'       => array( 'VHCP_MK', 'delete_line' ),
			'editMkDon'             => array( 'VHCP_MK', 'edit_don' ),
			'closeMkDon'            => array( 'VHCP_MK', 'close_don' ),
			'reopenMkDon'           => array( 'VHCP_MK', 'reopen_don' ),
			'deleteMkDon'           => array( 'VHCP_MK', 'delete_don' ),

			// công tác / setup
			'createBP'              => array( 'VHCP_BP', 'create' ),
			'listBP'                => array( 'VHCP_BP', 'list_bp' ),
			'getBP'                 => array( 'VHCP_BP', 'get' ),
			'addBPLine'             => array( 'VHCP_BP', 'add_line' ),
			'updateBPLine'          => array( 'VHCP_BP', 'update_line' ),
			'deleteBPLine'          => array( 'VHCP_BP', 'delete_line' ),
			'renameBP'              => array( 'VHCP_BP', 'rename' ),
			'closeBP'               => array( 'VHCP_BP', 'close' ),
			'reopenBP'              => array( 'VHCP_BP', 'reopen' ),
			'deleteBP'              => array( 'VHCP_BP', 'delete' ),

			// tra theo mã tài khoản (gom mọi mảng theo mã, thay cho việc gom số)
			'traTheoMa'             => array( 'VHCP_TraMa', 'search' ),
			'ganMaTaiKhoanTatCa'    => array( 'VHCP_TraMa', 'gan_ma_tat_ca' ),
			'dongBoTkLoai'          => array( 'VHCP_Cfg', 'dong_bo_tk_loai' ),
			'getTaiKhoan'           => array( 'VHCP_Cfg', 'get_tai_khoan' ),
			'ghepHeThongTk'         => array( 'VHCP_Cfg', 'ghep_he_thong_tk' ),
			'doMangTuTaiKhoan'      => array( 'VHCP_Cfg', 'do_mang_tu_tk' ),
			'xoaLoaiTuTao'          => array( 'VHCP_Cfg', 'xoa_loai_tu_tao' ),
			'khaiChiPhiChoCoSo'     => array( 'VHCP_Cfg', 'khai_cho_coso' ),
			'loaiCuaCoSo'           => array( 'VHCP_Cfg', 'loai_cua_coso' ),
			'datLoaiChoCoSo'        => array( 'VHCP_Cfg', 'dat_loai_cho_coso' ),

			// báo cáo
			'getFinanceReport'      => array( 'VHCP_Report', 'finance' ),
			'getPendingModules'     => array( 'VHCP_Report', 'pending_modules' ),
			'getGianReport'         => array( 'VHCP_Report', 'gian_report' ),
			'getVanHanhTuan'        => array( 'VHCP_Report', 'van_hanh_tuan' ),

			// xuất MISA
			'exportMisa'            => array( 'VHCP_Misa', 'export_misa' ),
			'exportMisaKyThuat'     => array( 'VHCP_Misa', 'export_ky_thuat' ),
			'exportMisaMarketing'   => array( 'VHCP_Misa', 'export_marketing' ),
			'exportMisaBP'          => array( 'VHCP_Misa', 'export_bp' ),
			'markExported'          => array( 'VHCP_Misa', 'mark_exported' ),

			// tệp
			'uploadImage'           => array( 'VHCP_Upload', 'upload_image' ),
			'uploadDuAnDoc'         => array( 'VHCP_Upload', 'upload_doc' ),
			'migrateOldImages'      => array( 'VHCP_Upload', 'migrate_old_images' ),
		);
	}

	public static function handle( WP_REST_Request $req ) {
		$fn   = (string) $req->get_param( 'fn' );
		$args = $req->get_param( 'args' );
		if ( ! is_array( $args ) ) { $args = array(); }
		$args = array_values( $args );

		$map = self::map();
		if ( ! isset( $map[ $fn ] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Hàm không tồn tại: ' . $fn ), 400 );
		}

		// Xóa vai trò của lượt gọi TRƯỚC. Biến static sống suốt request, mà hàm công khai
		// (login) thì không đi qua đoạn xác thực bên dưới — để sót là lượt sau thừa hưởng
		// vai trò của lượt trước, đúng loại lỗi phân quyền khó thấy nhất.
		VHCP_Auth::dat_vai_tro( '', '' );

		if ( ! in_array( $fn, self::$public_fns, true ) ) {
			$token = (string) $req->get_param( 'token' );
			if ( $token === '' ) { $token = (string) $req->get_header( 'x_vhcp_token' ); }
			$user     = VHCP_Auth::user_by_token( $token );
			$wp_admin = current_user_can( 'manage_options' );
			if ( ! $user && ! $wp_admin ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phiên đã hết — đăng nhập lại bằng PIN', 'code' => 'no_session' ), 401 );
			}
			$role_ht = $user ? (string) $user['role'] : ( $wp_admin ? 'Admin' : '' );
			VHCP_Auth::dat_vai_tro( $role_ht, $user ? (string) $user['name'] : '' );
			$need = self::required_roles( $fn );
			if ( $need ) {
				/* So bằng VAI GỐC, không phải tên vai người ta khai. Vai tự tạo "Nhân viên văn
				   phòng" kế thừa "Nhân viên" — so tên thật thì nó không khớp danh sách nào và
				   bị chặn hết, mà so vai gốc thì đúng bằng quyền nó được kế thừa. */
				$role = VHCP_Auth::vai_tro();
				if ( ! in_array( $role, $need, true ) ) {
					return new WP_REST_Response( array(
						'ok'    => false,
						/* Báo TÊN VAI NGƯỜI TA KHAI, kèm vai gốc khi hai cái khác nhau. Chỉ báo vai
						   gốc là người mang vai "Nhân viên văn phòng" đọc thấy "Nhân viên" rồi
						   tưởng hệ đọc sai vai của mình. */
						'error' => 'Vai trò "' . ( VHCP_Auth::vai_hien() !== '' ? VHCP_Auth::vai_hien() : 'không rõ' )
							. ( ( $role !== '' && $role !== VHCP_Auth::vai_hien() ) ? ' (kế thừa ' . $role . ')' : '' )
							. '" không được phép dùng chức năng này',
						'code'  => 'forbidden',
					), 403 );
				}
			}
		}

		/* 🔴 CHỐT ĐƠN VỊ (K&H · POSH) — MỘT LƯỢT CHO MỌI HÀM CÓ MÃ ĐƠN.
		   Đơn vị vuông góc với vai: Quản lý POSH vẫn là Quản lý, vẫn qua được chốt vai ở trên,
		   chỉ là không được đụng vào đơn của K&H. Chốt ở đây thì hàm viết sau này cũng tự được
		   gác — xem `VHCP_DonVi::chan_theo_ham()` cho lý do không rải chốt vào từng hàm.
		   Đứng SAU chốt vai và TRƯỚC lời gọi: chối vì sai vai là chuyện của vai, chối vì sai
		   đơn vị là chuyện của đơn vị, và không lượt gọi nào chạy trước khi qua cả hai. */
		$loi_dv = VHCP_DonVi::chan_theo_ham( $map[ $fn ], $args );
		if ( '' !== $loi_dv ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $loi_dv, 'code' => 'not_found' ), 404 );
		}

		try {
			$out = call_user_func_array( $map[ $fn ], $args );
		} catch ( Throwable $e ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $e->getMessage() ), 500 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $e->getMessage() ), 500 );
		}

		return new WP_REST_Response( array( 'ok' => true, 'data' => $out ), 200 );
	}
}
