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
		$admin_only = array( 'deleteDonAdmin', 'unmarkExportedSoChi' );
		$cau_hinh   = array( 'getUsers', 'saveConfig', 'undoConfig', 'setQuyen', 'getQuyenConfig', 'migrateOldImages', 'ganMaTaiKhoanSoChi', 'ganMaTaiKhoanDon', 'ganMaTaiKhoanTatCa', 'dongBoTkLoai' );
		if ( in_array( $fn, $admin_only, true ) ) { return array( 'Admin' ); }
		if ( in_array( $fn, $cau_hinh, true ) )   { return array( 'Admin', 'Quản lý' ); }
		return array();
	}

	public static function register_routes() {
		register_rest_route( 'vhcp/v1', '/call', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'handle' ),
		) );
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

			// khởi động & cấu hình
			'getBootstrap'          => array( 'VHCP_Don', 'get_bootstrap' ),
			'getConfig'             => array( 'VHCP_Cfg', 'get_config' ),
			'saveConfig'            => array( 'VHCP_Cfg', 'save_config' ),
			'undoConfig'            => array( 'VHCP_Cfg', 'undo_config' ),
			'getUsers'              => array( 'VHCP_Cfg', 'get_users' ),
			'getQuyen'              => array( 'VHCP_Cfg', 'get_quyen' ),
			'getQuyenConfig'        => array( 'VHCP_Cfg', 'get_quyen_config' ),
			'setQuyen'              => array( 'VHCP_Cfg', 'set_quyen' ),

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
			'deleteLine'            => array( 'VHCP_Don', 'delete_line' ),
			'duplicateLine'         => array( 'VHCP_Don', 'duplicate_line' ),
			'guiDuyetTamUng'        => array( 'VHCP_Don', 'gui_duyet_tam_ung' ),
			'guiQuyetToan'          => array( 'VHCP_Don', 'gui_quyet_toan' ),
			'saveQuyetToan'         => array( 'VHCP_Don', 'save_quyet_toan' ),
			'duyetTamUng'           => array( 'VHCP_Don', 'duyet_tam_ung' ),
			'capTamUng'             => array( 'VHCP_Don', 'cap_tam_ung' ),
			'xacNhanQuyetToanCN'    => array( 'VHCP_Don', 'xac_nhan_quyet_toan_cn' ),
			'xacNhanQuyetToanNCC'   => array( 'VHCP_Don', 'xac_nhan_quyet_toan_ncc' ),
			'traLaiDon'             => array( 'VHCP_Don', 'tra_lai_don' ),
			'deleteDon'             => array( 'VHCP_Don', 'delete_don' ),
			'deleteDonAdmin'        => array( 'VHCP_Don', 'delete_don_admin' ),
			'setTatToanTuan'        => array( 'VHCP_Don', 'set_tat_toan_tuan' ),
			'duyetTamUngNhieu'      => array( 'VHCP_Don', 'duyet_tam_ung_nhieu' ),
			'capTamUngNhieu'        => array( 'VHCP_Don', 'cap_tam_ung_nhieu' ),
			'traLaiDonNhieu'        => array( 'VHCP_Don', 'tra_lai_don_nhieu' ),
			'khongDungTamUng'       => array( 'VHCP_Don', 'khong_dung_tam_ung' ),
			'dayChoKeToan'          => array( 'VHCP_Don', 'day_cho_ke_toan' ),
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

		if ( ! in_array( $fn, self::$public_fns, true ) ) {
			$token = (string) $req->get_param( 'token' );
			if ( $token === '' ) { $token = (string) $req->get_header( 'x_vhcp_token' ); }
			$user     = VHCP_Auth::user_by_token( $token );
			$wp_admin = current_user_can( 'manage_options' );
			if ( ! $user && ! $wp_admin ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phiên đã hết — đăng nhập lại bằng PIN', 'code' => 'no_session' ), 401 );
			}
			$need = self::required_roles( $fn );
			if ( $need ) {
				$role = $user ? (string) $user['role'] : ( $wp_admin ? 'Admin' : '' );
				if ( ! in_array( $role, $need, true ) ) {
					return new WP_REST_Response( array(
						'ok'    => false,
						'error' => 'Vai trò "' . ( $role !== '' ? $role : 'không rõ' ) . '" không được phép dùng chức năng này',
						'code'  => 'forbidden',
					), 403 );
				}
			}
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
