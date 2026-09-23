<?php
/**
 * REST API — 1 cửa duy nhất: POST /wp-json/vhcpmtd/v1/call  {fn, args:[...], token}
 *
 * Giao diện vẫn gọi google.script.run.<tên hàm>(...) như cũ; assets/js/gas-shim.js
 * dịch mỗi lệnh gọi đó thành 1 request tới đây. Nhờ vậy toàn bộ Index.html của app
 * Apps Script chạy nguyên vẹn trên WordPress, không phải viết lại giao diện.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPMTD_API {

	/** Hàm chạy được KHI CHƯA đăng nhập. */
	/* Hàm chạy được KHI CHƯA đăng nhập.

	   `aiDangDangNhap` phải nằm đây: nó CHÍNH LÀ hàm hỏi "phiên còn sống không", nên bắt nó đi
	   qua chốt phiên là vòng tròn. Để ngoài danh sách này thì token chết sẽ ăn 401 `no_session`,
	   mà gặp mã ấy giao diện TỰ TẢI LẠI TRANG — người dùng mở trang lên thấy nó chớp một cái
	   rồi mới ra ô PIN, không hiểu vừa xảy ra chuyện gì.

	   ⚠️ An toàn không đổi: hàm chỉ nhận một token 64 ký tự hex ngẫu nhiên và tự tra bảng
	      phiên; sai thì trả "Phiên đã hết hạn", không lộ gì. Đoán được token ấy thì cũng đã gọi
	      được mọi hàm khác rồi. */
	private static $public_fns = array( 'login', 'aiDangDangNhap' );

	/**
	 * Hàm chỉ dành cho vai trò nhất định — chặn ngay ở máy chủ, không tin giao diện.
	 * (App Apps Script cũ KHÔNG có lớp này: ai có link đều gọi được getUsers để đọc PIN
	 *  của mọi người. Danh sách dưới khớp đúng những tab mà giao diện vốn chỉ cho
	 *  Admin/Quản lý thấy, nên người dùng không thấy khác gì.)
	 */
	/* Sáu cửa MISA — mọi lối làm ra tệp MISA hoặc đóng dấu "đã xuất". Thêm hàm MISA mới thì
	   khai vào đây, không thì nó là cái lỗ duy nhất trong luật trên. */
	private static $misa_fns = array( 'exportMisa', 'exportMisaKyThuat', 'exportMisaMarketing',
		'exportMisaBP', 'exportMisaSoChi', 'markExported' );

	private static function required_roles( $fn ) {
		// Sửa hàng loạt NGÀY của dòng chi là đụng thẳng vào số liệu kế toán (ngày quyết định
		// kỳ hạch toán). Chốt ở máy chủ, không tin mỗi giao diện.
		/* 🔴 SỔ MÃ GỌI TẮT LÀ MỘT CỬA PHÂN QUYỀN. Khai thêm một dòng `MÃ|CƠ SỞ` là mở cho mọi tài
		   khoản đang giữ mã ấy nhìn thấy sổ tiền của gian ấy — nên chỉ Admin, ngang hàng với
		   mấy việc đụng thẳng vào số liệu bên dưới. */
		$admin_only = array( 'luuMaTatCoso',
			/* 🔴 BẢNG ĐỐI CHIẾU BA KHO — chỉ Admin, dù nó CHỈ ĐỌC.
			   Nó đọc xuyên qua kho bảng của CẢ BA bản (gốc + hai bản vùng), tức là bày ra số tiền và
			   danh sách người dùng của hai mảng KHÁC — đúng cái ranh giới mà thanh khối dựng
			   lên để *"tránh râu ông này cắm bà kia"*. Kế toán KVC không có việc gì phải nhìn
			   sổ Văn phòng, kể cả nhìn suông. */
			'soatGop',
			'deleteDonAdmin', 'unmarkExportedSoChi', 'suaNamVoLy', 'suaNgayHong', 'suaKyHong', 'setDonNgay',
			/* Sửa TIỀN hàng loạt trên đơn đã duyệt — chỉ Admin, và chỉ sau khi xem trước. */
			'donBuTruCu',
			/* Đặt lại mốc một tuần ("tuần này chạy từ ngày nào đến ngày nào") — anh Thắng chốt
			   08/09/2026: *"admin có quyền chỉnh tuần đó"*. Đổi kỳ là đổi HÀNG LOẠT đơn cùng
			   lúc và có thể GỘP hai kỳ làm một, nên không mở cho kế toán. */
			'doiMocKy',
			/* Hạ tạm ứng của đơn ĐÃ đánh dấu cấp tiền về 0 — anh Thắng chốt 01/09/2026: chỉ
			   Admin. Đụng vào con số đã có lượt "cấp tiền" đứng sau, nên không mở cho kế toán. */
			'haTamUngVe0',
			/* Admin sửa lại số tạm ứng của đơn ĐÃ cấp về đúng con số (tổng quát hơn haTamUngVe0)
			   — anh Thắng 01/09/2026: chỉ Admin, vì đụng số đã có lượt cấp tiền đứng sau. */
			'suaTamUngDaCap',
			/* Admin trả ngược "Đã cấp" → "Chờ cấp tạm ứng" để làm lại đơn sai — chỉ Admin. */
			'goCapTamUng',
			// Khôi phục bảng người dùng là đụng thẳng vào ai đăng nhập được — chỉ Admin.
			'listUserBak', 'khoiPhucUsers',
			// Đổi tên cơ sở là sửa hàng loạt trên bốn bảng dữ liệu — chỉ Admin.
			'doiTenCoSo',
			/* Soát trùng nhân sự bày ra cả sổ hồ sơ bên trang Nhân sự (tên · mã NV · cơ sở ·
			   chức vụ · ai đã có PIN) — đó là dữ liệu nhân sự của cả công ty, không phải việc
			   của kế toán. Và `doiTenNguoi` thì sửa hàng loạt trên tám bảng cộng thẻ phiên,
			   đụng thẳng vào khoá nối của mọi đơn cũ. Cả hai: chỉ Admin. */
			'soatNhanSu', 'doiTenNguoi' );
		// Việc của NGƯỜI DUYỆT / KẾ TOÁN — nhân viên KHÔNG được gọi, bất kể bảng phân quyền
		// khai gì. Bảng đó nạp từ bảng tính cũ có thể lệch cột, mà đây là chỗ đụng tới tiền
		// của người khác nên phải chốt ở máy chủ.
		$nguoi_duyet = array(
			'duyetTamUng', 'capTamUng', 'duyetTamUngNhieu', 'capTamUngNhieu',
			/* Dựng lệnh bù ghi thẳng vào sổ lệnh — không phải việc của nhân viên. */
			'dungLenhBu',
			'traLaiDon', 'traLaiDonNhieu', 'xacNhanQuyetToanCN', 'xacNhanQuyetToanNCC',
			'xacNhanQtCnNhieu', 'setTatToanTuan', 'setSoDuDauKy', 'dongCuaCoSo',
			/* Đánh dấu ĐÃ THANH TOÁN (bước riêng của MTĐ/VP) là khai rằng tiền đã ra khỏi két —
			   việc của kế toán, không phải của người lập đơn. */
			'danhDauThanhToan', 'danhDauThanhToanNhieu',
			/* 🔴 `setLineThucMua` ĐÃ RỜI KHỎI ĐÂY — anh Thắng 01/09/2026, ảnh đơn FUNZONE VŨNG TÀU:
			   *"nhân viên được phép nhập và sửa lại đơn chính xác trước khi quyết toán, nhưng
			   nhập vào ô thực mua lại báo lỗi nhân viên không được chỉnh sửa"*.

			   Đúng là mâu thuẫn với chính màn hình: dải xanh trên đơn bảo nhân viên *"Mua xong
			   thì nhập Thực chi + ảnh chứng từ rồi bấm Gửi quyết toán"*, mà cổng lại chối. Nhập
			   thực chi LÀ việc của người đi mua — không phải việc của người duyệt.

			   Chốt không mất đi, chỉ chuyển vào lõi `set_line_thuc_mua()`: nhân viên chỉ sửa
			   được đơn CỦA MÌNH và chỉ khi đơn còn ở "Đã cấp tạm ứng"; sang "Chờ quyết toán" là
			   kế toán đang soát, lúc ấy chỉ người duyệt/kế toán được đụng. Gác ở lõi thì mọi
			   đường vào đều đi qua, kể cả bản giao diện cũ còn nằm trong bộ nhớ đệm. */
			'setLineCN',
			/* 🔴 GẮN MÃ HẠCH TOÁN LÀ VIỆC CỦA KẾ TOÁN. Anh Thắng 18/09/2026: *"kế toán có thể
			   [sửa] loại chi phí nếu nó sai"*. Loại chi phí suy ra TK Nợ, nên người nhập đổi được
			   là con số nhảy tài khoản sau lưng kế toán — và cái sai chỉ lộ ra lúc xuất MISA.
			   Lõi `set_line_nhom()` cũng gác, đây là lớp thứ hai ở cổng. */
			/* 🔴 VÀ CHỈNH THẲNG TK NỢ CỦA MỘT DÒNG — anh Thắng 22/09/2026: *"Sau khi quyết toán,
			   thì kế toán có quyền điều chỉnh tk nợ theo nhu cầu"*. Cùng hạng với `setLineNhom`:
			   nó đụng đúng con số đi vào sổ, chỉ khác là đổi thẳng mã thay vì đổi loại rồi suy
			   ra mã. Lõi `set_line_tk_no()` cũng gác vai, đây là lớp thứ hai ở cổng. */
			'setLineNhom', 'setLineTkNo', 'datLoaiCpDuAnLine',
			/* Đẩy tiền sang sổ của đơn vị khác — không phải việc của nhân viên. */
			'chuyenDonVi',
			/* 🔴 NHẢY ĐƠN SANG TUẦN KHÁC — anh Thắng 31/08/2026: *"kế toán sẽ gửi lệnh nhảy đơn
			   cho tuần tiếp theo (hoặc tuần chỉ định)"*. Đây là dời chỗ chốt của một khoản tạm
			   ứng đã cấp, tức đụng vào báo cáo của HAI tuần cùng lúc — người lập đơn không được
			   tự làm, kẻo tuần nào sắp bị soi thì đơn lặng lẽ trôi sang tuần sau. */
			'chuyenKy',
			/* 🔴 ĐỔI CƠ SỞ CỦA CẢ ĐƠN — anh Thắng 19/09/2026: *"cho quyền admin đổi đơn sang cơ
			   sở khác là được"*, để dọn mấy đơn đã lỡ lập dưới một cơ sở ảo. Nó dời tiền đã
			   nhập sang sổ của gian khác nên đứng cùng nhóm với `chuyenKy`: người lập đơn không
			   được tự làm. Chính hàm còn chặn thêm một tầng — chỉ Admin. */
			'doiCoSoDon',
			'datKhoangKyDon',
			/* Đổi con số tiền quản lý đã duyệt — việc của chính người duyệt, không phải người xin. */
			'duyetLaiTamUng',
		);
		if ( in_array( $fn, $nguoi_duyet, true ) ) {
			return array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' );
		}
		$cau_hinh   = array( 'getUsers', 'cosoLa', 'dsKyDangCo', 'saveConfig', 'undoConfig', 'setQuyen', 'resetQuyen', 'getQuyenConfig', 'migrateOldImages', 'ganMaTaiKhoanSoChi', 'ganMaTaiKhoanDon', 'ganMaTaiKhoanTatCa', 'dongBoTkLoai', 'xoaLoaiTuTao', 'getTaiKhoan', 'ghepHeThongTk', 'doMangTuTaiKhoan', 'khaiChiPhiChoCoSo', 'loaiCuaCoSo', 'datLoaiChoCoSo', 'hutCoSoGhe' );
		if ( in_array( $fn, $admin_only, true ) ) { return array( 'Admin' ); }
		// Kế toán cũng phải vào được Cấu hình (khai mã tài khoản, tên MISA, mã đơn vị là
		// việc của kế toán). Riêng tài khoản Admin thì chỉ Admin sửa — chặn trong
		// VHCPMTD_Cfg::save_config() theo vai trò người đang gọi.
		if ( in_array( $fn, $cau_hinh, true ) )   { return array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ); }
		return array();
	}

	public static function register_routes() {
		register_rest_route( 'vhcpmtd/v1', '/call', array(
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

		$req = new WP_REST_Request( 'POST', '/vhcpmtd/v1/call' );
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
	 * Nhận lệnh ngay trên URL của app (…/chi-phi/?vhcpmtd_api=1).
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
		if ( $tok === '' && isset( $_SERVER['HTTP_X_VHCPMTD_TOKEN'] ) ) {
			$tok = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_VHCPMTD_TOKEN'] ) );
		}

		// GET không kèm lệnh: dùng để giao diện thử xem đường này có đi được không.
		if ( $fn === '' ) {
			status_header( 200 );
			echo wp_json_encode( array( 'ok' => true, 'data' => array( 'song' => true, 'ver' => VHCPMTD_VERSION ) ) );
			exit;
		}

		$req = new WP_REST_Request( 'POST', '/vhcpmtd/v1/call' );
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
			'login'                 => array( 'VHCPMTD_Auth', 'login' ),
			/* Trang tự vào lại bằng TOKEN, không bằng PIN nhớ sẵn — xem `ai_dang_dang_nhap()`.
			   Vẫn đi qua cổng xác thực như mọi hàm khác: token hỏng thì cổng trả 401 trước khi
			   tới đây, và giao diện bày lại ô PIN. */
			'aiDangDangNhap'        => array( 'VHCPMTD_Auth', 'ai_dang_dang_nhap' ),
			'changePin'             => array( 'VHCPMTD_Auth', 'change_pin' ),
			'vhcpmtdLogout'            => array( 'VHCPMTD_Auth', 'logout' ),
			'logAction'             => array( 'VHCPMTD_Log', 'log_action' ),
			'getLog'                => array( 'VHCPMTD_Log', 'get_log' ),
			'getDonLog'             => array( 'VHCPMTD_Don', 'nhat_ky_don' ),
			'getDuAnLog'            => array( 'VHCPMTD_DuAn', 'nhat_ky_du_an' ),
			'timDon'                => array( 'VHCPMTD_Don', 'tim_don' ),
			'dsLoaiChiPhi'          => array( 'VHCPMTD_Don', 'ds_loai_chi_phi' ),

			// khởi động & cấu hình
			'getBootstrap'          => array( 'VHCPMTD_Don', 'get_bootstrap' ),
			'getConfig'             => array( 'VHCPMTD_Cfg', 'get_config' ),
			'saveConfig'            => array( 'VHCPMTD_Cfg', 'save_config' ),
			'doiMocKy'              => array( 'VHCPMTD_Don', 'doi_moc_ky' ),
			'dsKyDangCo'            => array( 'VHCPMTD_Don', 'ds_ky_dang_co' ),
			'undoConfig'            => array( 'VHCPMTD_Cfg', 'undo_config' ),
			'getUsers'              => array( 'VHCPMTD_Cfg', 'get_users' ),
			/* Sổ mã gọi tắt của cơ sở — "TUTU_BD = TÀU BÌNH DƯƠNG". Xem khối dài ở
			   `VHCPMTD_Auth::so_ma_tat()`. */
			'docMaTatCoso'          => array( 'VHCPMTD_Auth', 'doc_ma_tat_api' ),
			'luuMaTatCoso'          => array( 'VHCPMTD_Auth', 'luu_ma_tat_api' ),
			'listUserBak'           => array( 'VHCPMTD_Cfg', 'list_user_bak' ),
			'khoiPhucUsers'         => array( 'VHCPMTD_Cfg', 'khoi_phuc_users' ),
			'soatGop'               => array( 'VHCPMTD_Gop', 'soat' ),
			'cosoLa'                => array( 'VHCPMTD_Cfg', 'coso_la' ),
			'doiTenCoSo'            => array( 'VHCPMTD_Cfg', 'doi_ten_coso' ),
			'soatNhanSu'            => array( 'VHCPMTD_Cfg', 'soat_nhan_su' ),
			'doiTenNguoi'           => array( 'VHCPMTD_Cfg', 'doi_ten_nguoi' ),
			'getQuyen'              => array( 'VHCPMTD_Cfg', 'get_quyen' ),
			'getQuyenConfig'        => array( 'VHCPMTD_Cfg', 'get_quyen_config' ),
			'setQuyen'              => array( 'VHCPMTD_Cfg', 'set_quyen' ),
			'resetQuyen'            => array( 'VHCPMTD_Cfg', 'reset_quyen' ),

			'dongCuaCoSo'           => array( 'VHCPMTD_Cfg', 'dong_cua_coso' ),
			'hutCoSoGhe'            => array( 'VHCPMTD_Cfg', 'hut_coso_ghe_api' ),

			// đơn vận hành
			'listDons'              => array( 'VHCPMTD_Don', 'list_dons' ),
			'createDon'             => array( 'VHCPMTD_Don', 'tao_don_moi' ),
			'getDon'                => array( 'VHCPMTD_Don', 'get_don' ),
			'setTamUng'             => array( 'VHCPMTD_Don', 'set_tam_ung' ),
			'setDuPhong'            => array( 'VHCPMTD_Don', 'set_du_phong' ),
			'setTuExtra'            => array( 'VHCPMTD_Don', 'set_tu_extra' ),
			'addLine'               => array( 'VHCPMTD_Don', 'add_line' ),
			'updateLine'            => array( 'VHCPMTD_Don', 'update_line' ),
			'setLineThucMua'        => array( 'VHCPMTD_Don', 'set_line_thuc_mua' ),
			'setLineCN'             => array( 'VHCPMTD_Don', 'set_line_cn' ),
			'setLineAnh'            => array( 'VHCPMTD_Don', 'set_line_anh' ),
			'setLineNgay'           => array( 'VHCPMTD_Don', 'set_line_ngay' ),
			'setLineNhom'           => array( 'VHCPMTD_Don', 'set_line_nhom' ),
			'setLineTkNo'           => array( 'VHCPMTD_Don', 'set_line_tk_no' ),
			'setDonNgay'            => array( 'VHCPMTD_Don', 'set_don_ngay' ),
			'suaNamVoLy'            => array( 'VHCPMTD_Don', 'sua_nam_vo_ly' ),
			'suaNgayHong'           => array( 'VHCPMTD_Don', 'sua_ngay_hong' ),
			'suaKyHong'             => array( 'VHCPMTD_Don', 'sua_ky_hong' ),
			'deleteLine'            => array( 'VHCPMTD_Don', 'delete_line' ),
			'duplicateLine'         => array( 'VHCPMTD_Don', 'duplicate_line' ),
			'guiDuyetTamUng'        => array( 'VHCPMTD_Don', 'gui_duyet_tam_ung' ),
			'guiQuyetToan'          => array( 'VHCPMTD_Don', 'gui_quyet_toan' ),
			'saveQuyetToan'         => array( 'VHCPMTD_Don', 'save_quyet_toan' ),
			'setHoaDonQT'           => array( 'VHCPMTD_Don', 'set_hoa_don_qt' ),
			/* Đi qua bản BỌC để mỗi lượt duyệt lẻ cũng ghi một lệnh tạm ứng — xem
			   `duyet_tam_ung_ghi_lenh()`. Lõi `duyet_tam_ung()` giữ nguyên, không ghi lệnh,
			   vì nó còn bị `duyet_tam_ung_nhieu()` gọi trong vòng lặp. */
			'duyetTamUng'           => array( 'VHCPMTD_Don', 'duyet_tam_ung_ghi_lenh' ),
			'capTamUng'             => array( 'VHCPMTD_Don', 'cap_tam_ung' ),
			'xacNhanQuyetToanCN'    => array( 'VHCPMTD_Don', 'xac_nhan_quyet_toan_cn' ),
			'xacNhanQuyetToanNCC'   => array( 'VHCPMTD_Don', 'xac_nhan_quyet_toan_ncc' ),
			'traLaiDon'             => array( 'VHCPMTD_Don', 'tra_lai_don' ),
			'deleteDon'             => array( 'VHCPMTD_Don', 'delete_don' ),
			'deleteDonAdmin'        => array( 'VHCPMTD_Don', 'delete_don_admin' ),
			'setTatToanTuan'        => array( 'VHCPMTD_Don', 'set_tat_toan_tuan' ),
			/* Đẩy đơn / dòng chi sang đơn vị khác — kế toán POSH gửi cho kế toán cá nhân. */
			'chuyenDonVi'           => array( 'VHCPMTD_Don', 'chuyen_don_vi' ),
			/* Nhảy đơn sang tuần khác khi không quyết toán kịp trong tuần của nó. */
			'chuyenKy'              => array( 'VHCPMTD_Don', 'chuyen_ky' ),
			'doiCoSoDon'            => array( 'VHCPMTD_Don', 'doi_coso_don' ),
			'datKhoangKyDon'        => array( 'VHCPMTD_Don', 'dat_khoang_ky' ),
			'dsKyQuanh'             => array( 'VHCPMTD_Don', 'ds_ky_quanh_api' ),
			/* Tổng xin đổi sau khi duyệt (nhân viên sửa hạng mục, hoặc luật tính đổi) — cho
			   quản lý chốt lại số, miễn là chưa cấp tiền. */
			'duyetLaiTamUng'        => array( 'VHCPMTD_Don', 'duyet_lai_tam_ung' ),
			'donBuTruCu'            => array( 'VHCPMTD_Don', 'don_bu_tru_cu' ),
			/* Lượt cấp tiền chỉ là bấm nhầm, tiền chưa ra khỏi két -> hạ tạm ứng về 0. Khác hẳn
			   `khongDungTamUng` (tiền ĐÃ ra, giữ số, NV trả lại) — xem chú thích ở lõi. */
			'haTamUngVe0'           => array( 'VHCPMTD_Don', 'ha_tam_ung_ve_0' ),
			/* Admin sửa lại số tạm ứng của đơn đã cấp về đúng con số (duyệt/cấp nhầm số). */
			'suaTamUngDaCap'        => array( 'VHCPMTD_Don', 'sua_tam_ung_da_cap' ),
			/* Admin trả ngược "Đã cấp" → "Chờ cấp tạm ứng" để làm lại đơn sai. */
			'goCapTamUng'           => array( 'VHCPMTD_Don', 'go_cap_tam_ung' ),
			/* Thùng rác: xoá nhầm đơn hoặc dòng chi thì hoàn lại được. Cố ý KHÔNG khai vào
			   nhóm người-duyệt: người lập tự xoá nháp của mình thì cũng phải tự hoàn lại được.
			   Chốt "chỉ hoàn thao tác của chính mình" nằm trong `VHCPMTD_Don::hoan_tac()`, cùng
			   chỗ với chốt đơn vị — gác ở lõi thì mọi đường vào đều đi qua. */
			'dsThungRac'            => array( 'VHCPMTD_Don', 'ds_thung_rac' ),
			'hoanTac'               => array( 'VHCPMTD_Don', 'hoan_tac' ),
			'dsDonVi'               => array( 'VHCPMTD_DonVi', 'ds' ),
			'duyetTamUngNhieu'      => array( 'VHCPMTD_Don', 'duyet_tam_ung_nhieu' ),
			'dsLenhTU'              => array( 'VHCPMTD_Don', 'ds_lenh_tu' ),
			'chanDoanLenhTU'        => array( 'VHCPMTD_Don', 'chan_doan_lenh_tu' ),
			'dungLenhBu'            => array( 'VHCPMTD_Don', 'dung_lenh_bu' ),
			'capTamUngNhieu'        => array( 'VHCPMTD_Don', 'cap_tam_ung_nhieu' ),
			'traLaiDonNhieu'        => array( 'VHCPMTD_Don', 'tra_lai_don_nhieu' ),
			'khongDungTamUng'       => array( 'VHCPMTD_Don', 'khong_dung_tam_ung' ),
			'xacNhanQTCNNhieu'      => array( 'VHCPMTD_Don', 'xac_nhan_qt_cn_nhieu' ),
			'getSoDuDauKy'          => array( 'VHCPMTD_Don', 'get_so_du_dau_ky' ),
			'setSoDuDauKy'          => array( 'VHCPMTD_Don', 'set_so_du_dau_ky' ),

			// sổ chi phí (nhập phẳng: chọn loại chi phí -> nhập)
			'listSoChi'             => array( 'VHCPMTD_SoChi', 'list_chi' ),
			'addSoChi'              => array( 'VHCPMTD_SoChi', 'add' ),
			'updateSoChi'           => array( 'VHCPMTD_SoChi', 'update' ),
			'deleteSoChi'           => array( 'VHCPMTD_SoChi', 'delete' ),
			'exportMisaSoChi'       => array( 'VHCPMTD_SoChi', 'export_misa' ),
			'markExportedSoChi'     => array( 'VHCPMTD_SoChi', 'mark_exported' ),
			'unmarkExportedSoChi'   => array( 'VHCPMTD_SoChi', 'unmark_exported' ),
			'ganMaTaiKhoanSoChi'    => array( 'VHCPMTD_SoChi', 'gan_ma_tai_khoan' ),
			'ganMaTaiKhoanDon'      => array( 'VHCPMTD_Don', 'gan_ma_tai_khoan' ),

			// chi phí kỹ thuật (dự án)
			'createDuAn'            => array( 'VHCPMTD_DuAn', 'create_du_an' ),
			'ensureCoSoChung'       => array( 'VHCPMTD_DuAn', 'ensure_co_so_chung' ),
			'listDuAn'              => array( 'VHCPMTD_DuAn', 'list_du_an' ),
			'renameDuAn'            => array( 'VHCPMTD_DuAn', 'rename_du_an' ),
			'getDuAn'               => array( 'VHCPMTD_DuAn', 'get_du_an' ),
			'addDuAnLine'           => array( 'VHCPMTD_DuAn', 'add_line' ),
			'datDuToanDuAn'         => array( 'VHCPMTD_DuAn', 'set_du_toan_da' ),
			'datTrangThaiHangMuc'   => array( 'VHCPMTD_DuAn', 'dat_hm' ),
			'listDonHangMuc'        => array( 'VHCPMTD_DuAn', 'list_don_hm' ),
			'xinTamUngDuAn'         => array( 'VHCPMTD_DuAn', 'xin_tam_ung_dot' ),
			'datTrangThaiLenhDuAn'  => array( 'VHCPMTD_DuAn', 'dat_tt_dot' ),
			'capTienPhanDuAn'       => array( 'VHCPMTD_DuAn', 'cap_tien_phan' ),
			'listLenhDuAn'          => array( 'VHCPMTD_DuAn', 'list_lenh_da' ),
			'xinQuyetToanDuAn'      => array( 'VHCPMTD_DuAn', 'xin_quyet_toan_dot' ),
			'datTrangThaiQTDuAn'    => array( 'VHCPMTD_DuAn', 'dat_tt_qt' ),
			'datAnhDuAnLine'        => array( 'VHCPMTD_DuAn', 'dat_anh_line' ),
			'datODuAnLine'          => array( 'VHCPMTD_DuAn', 'dat_o_line' ),
			'datLoaiCpDuAnLine'     => array( 'VHCPMTD_DuAn', 'dat_loai_cp_line' ),
			'goAnhDuAnLine'         => array( 'VHCPMTD_DuAn', 'go_anh_line' ),
			'themHoSoDuAnLine'      => array( 'VHCPMTD_DuAn', 'them_ho_so_line' ),
			'datKyDuAn'             => array( 'VHCPMTD_DuAn', 'set_ky_da' ),
			'updateDuAnLine'        => array( 'VHCPMTD_DuAn', 'update_line' ),
			'deleteDuAnLine'        => array( 'VHCPMTD_DuAn', 'delete_line' ),
			'submitDuAn'            => array( 'VHCPMTD_DuAn', 'submit' ),
			'approveDuAn'           => array( 'VHCPMTD_DuAn', 'approve' ),
			'returnDuAn'            => array( 'VHCPMTD_DuAn', 'ret' ),
			'closeDuAn'             => array( 'VHCPMTD_DuAn', 'close' ),
			'reopenDuAn'            => array( 'VHCPMTD_DuAn', 'reopen' ),
			'deleteDuAn'            => array( 'VHCPMTD_DuAn', 'delete' ),
			'confirmDuAnPay'        => array( 'VHCPMTD_DuAn', 'confirm_pay' ),
			'unconfirmDuAnPay'      => array( 'VHCPMTD_DuAn', 'unconfirm_pay' ),

			// marketing
			'createMkDon'           => array( 'VHCPMTD_MK', 'create_don' ),
			'listMkDon'             => array( 'VHCPMTD_MK', 'list_don' ),
			'getMkDon'              => array( 'VHCPMTD_MK', 'get_don' ),
			'addMkDonLine'          => array( 'VHCPMTD_MK', 'add_line' ),
			'updateMkDonLine'       => array( 'VHCPMTD_MK', 'update_line' ),
			'deleteMkDonLine'       => array( 'VHCPMTD_MK', 'delete_line' ),
			'editMkDon'             => array( 'VHCPMTD_MK', 'edit_don' ),
			'closeMkDon'            => array( 'VHCPMTD_MK', 'close_don' ),
			'reopenMkDon'           => array( 'VHCPMTD_MK', 'reopen_don' ),
			'deleteMkDon'           => array( 'VHCPMTD_MK', 'delete_don' ),

			// công tác / setup
			'createBP'              => array( 'VHCPMTD_BP', 'create' ),
			'listBP'                => array( 'VHCPMTD_BP', 'list_bp' ),
			'getBP'                 => array( 'VHCPMTD_BP', 'get' ),
			'addBPLine'             => array( 'VHCPMTD_BP', 'add_line' ),
			'updateBPLine'          => array( 'VHCPMTD_BP', 'update_line' ),
			'deleteBPLine'          => array( 'VHCPMTD_BP', 'delete_line' ),
			'renameBP'              => array( 'VHCPMTD_BP', 'rename' ),
			'closeBP'               => array( 'VHCPMTD_BP', 'close' ),
			'reopenBP'              => array( 'VHCPMTD_BP', 'reopen' ),
			'deleteBP'              => array( 'VHCPMTD_BP', 'delete' ),

			// tra theo mã tài khoản (gom mọi mảng theo mã, thay cho việc gom số)
			'traTheoMa'             => array( 'VHCPMTD_TraMa', 'search' ),
			'ganMaTaiKhoanTatCa'    => array( 'VHCPMTD_TraMa', 'gan_ma_tat_ca' ),
			'dongBoTkLoai'          => array( 'VHCPMTD_Cfg', 'dong_bo_tk_loai' ),
			'getTaiKhoan'           => array( 'VHCPMTD_Cfg', 'get_tai_khoan' ),
			'ghepHeThongTk'         => array( 'VHCPMTD_Cfg', 'ghep_he_thong_tk' ),
			'doMangTuTaiKhoan'      => array( 'VHCPMTD_Cfg', 'do_mang_tu_tk' ),
			'xoaLoaiTuTao'          => array( 'VHCPMTD_Cfg', 'xoa_loai_tu_tao' ),
			'khaiChiPhiChoCoSo'     => array( 'VHCPMTD_Cfg', 'khai_cho_coso' ),
			'loaiCuaCoSo'           => array( 'VHCPMTD_Cfg', 'loai_cua_coso' ),
			'datLoaiChoCoSo'        => array( 'VHCPMTD_Cfg', 'dat_loai_cho_coso' ),

			// báo cáo
			'getFinanceReport'      => array( 'VHCPMTD_Report', 'finance' ),
			'getPendingModules'     => array( 'VHCPMTD_Report', 'pending_modules' ),
			'getGianReport'         => array( 'VHCPMTD_Report', 'gian_report' ),
			'getVanHanhTuan'        => array( 'VHCPMTD_Report', 'van_hanh_tuan' ),

			// xuất MISA
			'exportMisa'            => array( 'VHCPMTD_Misa', 'export_misa' ),
			'exportMisaKyThuat'     => array( 'VHCPMTD_Misa', 'export_ky_thuat' ),
			'exportMisaMarketing'   => array( 'VHCPMTD_Misa', 'export_marketing' ),
			'exportMisaBP'          => array( 'VHCPMTD_Misa', 'export_bp' ),
			'markExported'          => array( 'VHCPMTD_Misa', 'mark_exported' ),
			/* Bước thanh toán riêng của MTĐ/VP — xem `VHCPMTD_Don::danh_dau_thanh_toan()`. */
			'danhDauThanhToan'      => array( 'VHCPMTD_Don', 'danh_dau_thanh_toan' ),
			'danhDauThanhToanNhieu' => array( 'VHCPMTD_Don', 'danh_dau_thanh_toan_nhieu' ),

			// tệp
			'uploadImage'           => array( 'VHCPMTD_Upload', 'upload_image' ),
			'uploadDuAnDoc'         => array( 'VHCPMTD_Upload', 'upload_doc' ),
			'migrateOldImages'      => array( 'VHCPMTD_Upload', 'migrate_old_images' ),
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
		VHCPMTD_Auth::dat_vai_tro( '', '' );

		if ( ! in_array( $fn, self::$public_fns, true ) ) {
			$token = (string) $req->get_param( 'token' );
			if ( $token === '' ) { $token = (string) $req->get_header( 'x_vhcpmtd_token' ); }
			$user     = VHCPMTD_Auth::user_by_token( $token );
			$wp_admin = current_user_can( 'manage_options' );
			if ( ! $user && ! $wp_admin ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phiên đã hết — đăng nhập lại bằng PIN', 'code' => 'no_session' ), 401 );
			}
			$role_ht = $user ? (string) $user['role'] : ( $wp_admin ? 'Admin' : '' );
			/* Cơ sở phụ trách đi kèm luôn: `list_dons()` cần nó để mở phạm vi đơn cho nhân viên
			   phụ trách nhiều cơ sở (anh Thắng 30/08/2026). Không truyền thì `coso_ds()` rỗng,
			   và mọi thứ rơi về đúng hành vi cũ — chỉ thấy đơn của chính mình. */
			/* Phòng ban đi kèm luôn (13/09/2026): từ nay nó bó theo TÀI KHOẢN chứ không theo
			   vai, nên không truyền là mọi chốt phòng ban im lặng mở toang. */
			/* Mã NV đi kèm luôn: `soatNhanSu()` và `doiTenNguoi()` cần biết người đang gọi là
			   ai bên Nhân sự. Không truyền là chúng đọc ra chuỗi rỗng và im lặng bỏ qua. */
			VHCPMTD_Auth::dat_vai_tro( $role_ht, $user ? (string) $user['name'] : '',
				$user && isset( $user['coso'] ) ? (string) $user['coso'] : '',
				$user && isset( $user['boPhan'] ) ? (string) $user['boPhan'] : '',
				$user && isset( $user['maNv'] ) ? (string) $user['maNv'] : '' );
			$need = self::required_roles( $fn );
			if ( $need ) {
				/* So bằng VAI GỐC, không phải tên vai người ta khai. Vai tự tạo "Nhân viên văn
				   phòng" kế thừa "Nhân viên" — so tên thật thì nó không khớp danh sách nào và
				   bị chặn hết, mà so vai gốc thì đúng bằng quyền nó được kế thừa. */
				$role = VHCPMTD_Auth::vai_tro();
				if ( ! in_array( $role, $need, true ) ) {
					return new WP_REST_Response( array(
						'ok'    => false,
						/* Báo TÊN VAI NGƯỜI TA KHAI, kèm vai gốc khi hai cái khác nhau. Chỉ báo vai
						   gốc là người mang vai "Nhân viên văn phòng" đọc thấy "Nhân viên" rồi
						   tưởng hệ đọc sai vai của mình. */
						'error' => 'Vai trò "' . ( VHCPMTD_Auth::vai_hien() !== '' ? VHCPMTD_Auth::vai_hien() : 'không rõ' )
							. ( ( $role !== '' && $role !== VHCPMTD_Auth::vai_hien() ) ? ' (kế thừa ' . $role . ')' : '' )
							. '" không được phép dùng chức năng này',
						'code'  => 'forbidden',
					), 403 );
				}
			}
		}

		/* ═══════════════════════════════════════════════════════════════════════════════════
		   KẾ TOÁN MÁY TỰ ĐỘNG KHÔNG ĐẨY MISA — CHỐT Ở MÁY CHỦ, KHÔNG CHỈ GIẤU TAB.

		   Anh Thắng 21/09/2026: *"kế toán máy tự động chỉ check chứ ko đẩy misa"*. Giấu tab là
		   đủ cho người dùng bình thường, nhưng `markExported` ghi thẳng 'Đã xuất MISA' vào sổ —
		   một lượt gọi tay là đơn của cả tháng bị đánh dấu đã xuất trong khi chưa tệp nào đi ra.
		   Chốt ở đây thì hàm MISA viết sau này cũng tự được gác.

		   ⚠️ KHÔNG GỘP VÀO `required_roles()`: hàm ấy so bằng VAI GỐC (cố ý, để vai con thừa
		      hưởng quyền của vai cha). Luật này thì ngược lại — nó phân biệt ĐÚNG hai vai con
		      cùng cha: "Kế Toán Máy Tự Động" và "Kế Toán Khu Vui Chơi" đều kế thừa "Kế toán cá
		      nhân". Nhét vào đó là hoặc chặn cả hai, hoặc chặn không ai.
		   ═══════════════════════════════════════════════════════════════════════════════════ */
		if ( in_array( $fn, self::$misa_fns, true ) && ! VHCPMTD_Cfg::xuat_misa_duoc() ) {
			return new WP_REST_Response( array(
				'ok'    => false,
				'error' => 'Vai trò "' . ( VHCPMTD_Auth::vai_hien() !== '' ? VHCPMTD_Auth::vai_hien() : 'không rõ' )
					. '" chỉ soát và duyệt quyết toán. Đơn duyệt xong tự chuyển sang kế toán Khu vui chơi để xuất MISA.',
				'code'  => 'forbidden',
			), 403 );
		}

		/* 🔴 CHỐT ĐƠN VỊ (K&H · POSH) — MỘT LƯỢT CHO MỌI HÀM CÓ MÃ ĐƠN.
		   Đơn vị vuông góc với vai: Quản lý POSH vẫn là Quản lý, vẫn qua được chốt vai ở trên,
		   chỉ là không được đụng vào đơn của K&H. Chốt ở đây thì hàm viết sau này cũng tự được
		   gác — xem `VHCPMTD_DonVi::chan_theo_ham()` cho lý do không rải chốt vào từng hàm.
		   Đứng SAU chốt vai và TRƯỚC lời gọi: chối vì sai vai là chuyện của vai, chối vì sai
		   đơn vị là chuyện của đơn vị, và không lượt gọi nào chạy trước khi qua cả hai. */
		$loi_dv = VHCPMTD_DonVi::chan_theo_ham( $map[ $fn ], $args );
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
