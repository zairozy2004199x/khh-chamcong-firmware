<?php
/**
 * CỔNG PIN CỦA TRANG NGOÀI `/ghe`.
 *
 * =============================================================================================
 * VÌ SAO PHẢI CÓ, KHI ĐÃ CÓ MÀN TRONG wp-admin
 * =============================================================================================
 * Màn wp-admin đòi tài khoản WordPress. Nhân viên đứng quầy ở Aeon Tân Phú thì không có, và
 * cũng không nên có — cấp tài khoản WordPress cho 26 cửa hàng là cấp luôn đường vào phần quản
 * trị website. Bản Apps Script cũ giải đúng bài này bằng một trang mở bằng PIN trên điện thoại;
 * bản này giữ nguyên cách đó.
 *
 * DÙNG CHUNG DANH SÁCH NGƯỜI với plugin Vận hành chi phí (bảng `{prefix}vhcp_cfg`, hàng
 * `CH_NguoiDung`) — khai một lần dùng cho cả ba hệ thống. Không có bảng đó thì rơi về danh
 * sách riêng của plugin (option `vhg_nguoidung`), và NÓI RÕ trên màn Cài đặt chứ không âm thầm:
 * đổi nguồn danh tính trong im lặng là kiểu lỗi tệ nhất của một cổng đăng nhập.
 *
 * ⚠️ PHIÊN RIÊNG, KHÔNG DÙNG LẠI TOKEN CỦA PLUGIN CHẤM CÔNG. Đây là màn có doanh thu; khả năng
 *    phải đá một người ra ngay lập tức là có thật, và lúc đó không được kéo theo cả app kia.
 *
 * ⚠️ RỬA ĐUÔI ".0" CỦA BẢNG TÍNH. Google Sheets coi PIN là SỐ nên `571394` xuất ra thành
 *    `"571394.0"` — tám KÝ TỰ, không phải tám CHỮ SỐ, nên trượt luật 4–8 chữ số ngay dòng đầu.
 *    Đúng lỗi đã khoá cửa toàn bộ người dùng trang chấm công ngày 22/08/2026. Rửa lúc ĐỌC.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Auth {

	const TTL = 2592000;   // 30 ngày — nhân viên mở trên điện thoại, bắt gõ PIN mỗi ca là họ sẽ
	                       // ghi PIN lên tờ giấy dán cạnh ghế.

	/**
	 * Vai trò vào được — MẶC ĐỊNH HẸP, mở rộng bằng Cài đặt chứ không sửa code.
	 *
	 * Cửa hàng trưởng CÓ trong mặc định ở đây (khác plugin chấm công): người đứng quầy chính là
	 * người cần biết ghế nào đang đứng, khách trả tiền rồi mà ghế chưa chạy. Bảng lương thì
	 * không — nên hai plugin có hai danh sách khác nhau là ĐÚNG, không phải quên đồng bộ.
	 */
	/* 🔴 'Nhân viên' CÓ trong danh sách vào được, kể từ 23/08/2026.
	   Anh Thắng: *"Nhân viên đăng nhập vẫn trang này, nhưng chỉ hiện mỗi chốt ca"*. Trước bản
	   này vai trò đó không đăng nhập nổi, nên cả phần chốt ca dựng cho họ là dựng cho một người
	   không vào được cửa. Họ vào rồi thì chỉ thấy đúng tab Quỹ — xem `VIEC_QUAN_TRI` và
	   `VHG_Trang::so_lieu_nhan_vien()`. */
	const VAI_TRO_MAC_DINH = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC',
		'Cửa hàng trưởng', 'Hotline', 'Nhân viên' );

	/* 🔴 'Hotline' — 23/08/2026. Anh Thắng: *"Đấy là bạn Hotline bật ghế cho khách chứ không
	   phải nhân viên. Nhân viên là các bạn thu tiền tại máy"*.
	   Hai việc khác hẳn nhau, và trước bản này không có vai trò nào cho việc thứ nhất: muốn bật
	   ghế hộ khách là phải cấp quyền Quản lý — tức là cấp luôn quyền xem doanh thu cả chuỗi,
	   huỷ mã khách đã trả tiền và gán mã ghế. */
	const VAI_TRO_TAT_CA = array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC',
		'Cửa hàng trưởng', 'Hotline', 'Nhân viên' );

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * PHÂN QUYỀN TRÊN CỔNG /ghe — MỘT DANH SÁCH DUY NHẤT.
	 *
	 * Anh Thắng 23/08/2026: *"Vậy có phân quyền giữa tài khoản nhân viên và tài khoản quản lý
	 * chưa"*, rồi chốt: nhân viên KHÔNG xem tiền của người khác, KHÔNG xem tổng doanh thu,
	 * KHÔNG gán mã ghế, KHÔNG huỷ giao dịch, và KHÔNG bật/tắt ghế hay tiêu ví hộ khách.
	 *
	 * 🔴 DANH SÁCH NẰM MỘT CHỖ, VÀ CHỐT ĐẶT TRƯỚC MỌI NHÁNH XỬ LÝ.
	 *    Rải `if role` vào từng việc là kiểu lỗi chỉ lộ ra ở việc BỊ QUÊN — mà việc bị quên thì
	 *    theo định nghĩa không ai nghĩ tới lúc đọc lại. Thêm một việc mới vào cổng mà quên khai
	 *    ở đây thì nó rơi vào nhóm "ai cũng làm được", nên danh sách này phải là thứ đọc được
	 *    trong một màn hình — và có phép thử canh đúng chuyện đó.
	 *
	 * ⚠️ GIẤU NÚT KHÔNG PHẢI LÀ CHẶN. Giao diện ẩn tab đi cho gọn mắt, nhưng ai đọc được gói tin
	 *    thì gọi thẳng cổng được. Chốt thật nằm ở đây, trước khi vào bất kỳ nhánh nào.
	 * ═════════════════════════════════════════════════════════════════════════════════════════ */

	/** Vai trò được làm mọi việc. */
	const QUAN_TRI = array( 'Admin', 'Quản lý' );

	/**
	 * Việc CHỈ quản trị mới làm được. Mọi việc khác: ai đăng nhập cũng làm được.
	 *
	 *   · bật / tắt / khởi động lại — nút Bật cho ghế chạy KHÔNG tính tiền.
	 *   · gán mã ghế                — khai ghế mới; gán sai là tiền chạy sang ghế khác.
	 *   · vi_tra_nv / vi_tieu_nv    — xem số dư và tiêu ví của khách MÀ KHÔNG CẦN PIN của họ.
	 *   · ma_huy                    — huỷ mã khách đã trả tiền.
	 *   · nop_nhan / nop_huy        — xác nhận đã nhận tiền nộp; người nộp không tự xác nhận.
	 *   · so_may                    — số liệu doanh thu của một ghế theo ngày/tuần/tháng.
	 */
	const VIEC_QUAN_TRI = array(
		'gan_ma',
		'doi_mac',       // đổi board (MAC) cho mã ghế đã có — thay ESP32, giữ chỉ số
		'ma_huy',
		'so_may',
		'nhat_ky_may',   // lịch sử bật/tắt ghế — số liệu vận hành cả chuỗi, chỉ quản trị

		/* Tab QUẢN LÝ GHẾ: thêm/xoá địa điểm, thêm/xoá ghế. Chỉ quản trị. */
		'coso_luu',
		'coso_xoa',
		'may_them',
		'may_xoa',
		'may_coso',
	);

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * BA NHÓM QUYỀN, KHÔNG PHẢI HAI — VÌ CÓ BA CÔNG VIỆC THẬT.
	 *
	 * Anh Thắng 23/08/2026: *"Đấy là bạn Hotline bật ghế cho khách chứ không phải nhân viên.
	 * Nhân viên là các bạn thu tiền tại máy"*.
	 *
	 *   · GIÚP KHÁCH   — bạn trực Hotline: khách gọi tới vì ghế không chạy, vì bấm nhiều lần bị
	 *                    hãm 10 phút. Cần bật ghế, tra ví, tiêu ví hộ. KHÔNG cần thấy doanh thu.
	 *   · CHỐT DOANH SỐ— kế toán: nhận tiền nhân viên nộp về và xác nhận đủ.
	 *   · QUẢN TRỊ     — khai ghế, huỷ mã khách đã trả tiền, xem doanh thu cả chuỗi.
	 *
	 * 🔴 GỘP LÀM MỘT LÀ CẤP THỪA QUYỀN. Trước bản này bật ghế nằm trong nhóm quản trị, nên muốn
	 *    bạn Hotline bật ghế hộ khách là phải cấp cho họ quyền Quản lý — kèm theo đó là xem
	 *    doanh thu 26 cửa hàng, huỷ mã khách đã trả tiền, và gán lại mã ghế. Không ai định cấp
	 *    những thứ đó; nó đi kèm vì danh sách chỉ có một nhóm.
	 * ═════════════════════════════════════════════════════════════════════════════════════════ */

	/** Việc của người GIÚP KHÁCH — bật ghế, mở khoá ghế lỗi, và tiêu ví hộ khách. */
	const VIEC_GIUP_KHACH = array(
		'bat', 'tat', 'khoi_dong_lai', 'mo_khoa', 'test',
		'vi_tra_nv', 'vi_tieu_nv',
	);

	/** Việc CHỐT DOANH SỐ — kế toán làm được mà không cần quyền Quản lý.
	 * `quy_nop_thay` (29/08/2026): xác nhận LUÔN một lượt "đang cầm" thành hết nợ, không cần
	 * đợi chính người đó tự bấm Nộp trước — dùng cho dữ liệu CŨ/ĐÃ NHẬP (`kt_nhap`, nhân viên
	 * không còn phiên nào để tự bấm) mà tiền thật đã về tay ngoài đời từ trước khi cột `nop_id`
	 * ra đời, xem VHG_Quy::nop_va_nhan_thay(). */
	const VIEC_CHOT_DOANH_SO = array( 'nop_nhan', 'nop_huy', 'quy_nop_thay' );

	/**
	 * Vai trò được giúp khách. Khai được, cùng lý do với `vai_tro_chot()`.
	 * ⚠️ Admin và Quản lý LUÔN có: họ là người trực thay khi bạn Hotline nghỉ.
	 */
	public static function vai_tro_giup_khach() {
		$ds = get_option( 'vhg_vai_tro_giup' );
		$ra = array( 'Admin', 'Quản lý' );
		if ( ! is_array( $ds ) ) { $ra[] = 'Hotline'; return $ra; }
		foreach ( $ds as $v ) {
			$v = (string) $v;
			if ( in_array( $v, self::VAI_TRO_TAT_CA, true ) && ! in_array( $v, $ra, true ) ) {
				$ra[] = $v;
			}
		}
		return $ra;
	}

	public static function duoc_giup_khach( $vai_tro ) {
		return in_array( (string) $vai_tro, self::vai_tro_giup_khach(), true );
	}

	/**
	 * Bộ quyền của một vai trò — MỘT NƠI TRẢ LỜI, dùng cho cả cổng lẫn gói số liệu lẫn giao diện.
	 *
	 * ⚠️ Ba nơi tự suy ra quyền theo ba cách là ba cách suy sai khác nhau: cổng chặn, giao diện
	 *    vẫn vẽ nút, và gói tin vẫn kèm số liệu. Hỏi chung một chỗ thì ba nơi không lệch được.
	 */
	public static function quyen_cua( $vai_tro ) {
		return array(
			'quan_tri'      => self::la_quan_tri( $vai_tro ) ? 1 : 0,
			'giup_khach'    => self::duoc_giup_khach( $vai_tro ) ? 1 : 0,
			'chot_doanh_so' => self::duoc_chot_doanh_so( $vai_tro ) ? 1 : 0,
		);
	}

	/**
	 * Vai trò QUẢN TRỊ — khai được, cùng lối với `vai_tro_chot()` / `vai_tro_giup_khach()`.
	 *
	 * Anh Thắng 28/08/2026: *"Bổ sung thêm phân quyền — Quản lý được, Xoá, Thêm cơ sở, set được
	 * sử dụng tính năng nào (PIN Báo Cáo), và cấp một số quyền khác cho nhân viên mình quản lý"*.
	 *
	 * → Nhóm này cấp quyền VẬN HÀNH cho vai trò ngoài Admin/Quản lý (ví dụ Cửa hàng trưởng quản lý
	 *   cơ sở của mình): thêm/xoá/sửa cơ sở & ghế, gán/huỷ mã, xem doanh thu cả chuỗi, và cấp PIN
	 *   báo cáo cho nhân viên (`bc_pin_*`).
	 *
	 * ⚠️ CHƯA KHAI BAO GIỜ = Admin + Quản lý, đúng như hệ đang chạy trước bản này (const QUAN_TRI).
	 * ⚠️ Admin LUÔN nằm trong danh sách dù khai kiểu gì — khai sót Admin là tự khoá mình khỏi mọi
	 *    việc quản trị, kể cả màn sửa chính danh sách này, và không có đường mở lại ngoài CSDL.
	 * 🔴 KHAI NHÂN SỰ và SỬA CHÍNH BẢNG PHÂN QUYỀN vẫn CHỈ Admin (gác ở lớp `ch_` của VHG_Trang):
	 *    người được cấp quyền quản trị KHÔNG tự nâng quyền cho mình hay người khác được.
	 */
	public static function vai_tro_quan_tri() {
		$ds = get_option( 'vhg_vai_tro_quantri' );
		if ( ! is_array( $ds ) ) { return self::QUAN_TRI; }   // chưa khai = Admin + Quản lý
		$ra = array( 'Admin' );
		foreach ( $ds as $v ) {
			$v = (string) $v;
			if ( in_array( $v, self::VAI_TRO_TAT_CA, true ) && ! in_array( $v, $ra, true ) ) {
				$ra[] = $v;
			}
		}
		return $ra;
	}

	public static function la_quan_tri( $vai_tro ) {
		return in_array( (string) $vai_tro, self::vai_tro_quan_tri(), true );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * AI ĐƯỢC CHỐT DOANH SỐ (xác nhận đã nhận tiền nhân viên nộp).
	 *
	 * Anh Thắng 23/08/2026: *"Để cấu hình tài khoản kế toán vào chốt doanh số sau khi nhân viên
	 * thu tiền"*.
	 *
	 * 🔴 KHAI ĐƯỢC, KHÔNG NHÉT CỨNG. Mỗi chuỗi tổ chức khác nhau: nơi thì quản lý cửa hàng nhận
	 *    tiền, nơi thì kế toán xuống nhận, nơi thì cả hai. Nhét cứng "Admin + Quản lý" là hoặc
	 *    kế toán không làm được việc của mình, hoặc phải cấp cho kế toán quyền Quản lý — tức là
	 *    cấp luôn quyền huỷ mã, gán ghế và tiêu ví của khách.
	 *
	 * ⚠️ Admin LUÔN nằm trong danh sách, dù khai kiểu gì. Khai sót Admin là không còn ai chốt
	 *    được doanh số, và không có đường nào tự mở lại ngoài cơ sở dữ liệu.
	 */
	public static function vai_tro_chot() {
		$ds = get_option( 'vhg_vai_tro_chot' );
		$ra = array( 'Admin' );
		if ( is_array( $ds ) ) {
			foreach ( $ds as $v ) {
				$v = (string) $v;
				if ( in_array( $v, self::VAI_TRO_TAT_CA, true ) && ! in_array( $v, $ra, true ) ) {
					$ra[] = $v;
				}
			}
		}
		/* Chưa khai bao giờ = Admin + Quản lý, đúng như hệ đang chạy trước bản này. */
		if ( 1 === count( $ra ) && ! is_array( $ds ) ) { $ra[] = 'Quản lý'; }
		return $ra;
	}

	public static function duoc_chot_doanh_so( $vai_tro ) {
		return in_array( (string) $vai_tro, self::vai_tro_chot(), true );
	}

	public static function duoc_lam( $vai_tro, $viec ) {
		$v = (string) $viec;
		if ( in_array( $v, self::VIEC_CHOT_DOANH_SO, true ) ) {
			return self::duoc_chot_doanh_so( $vai_tro );
		}
		if ( in_array( $v, self::VIEC_GIUP_KHACH, true ) ) {
			return self::duoc_giup_khach( $vai_tro );
		}
		if ( ! in_array( $v, self::VIEC_QUAN_TRI, true ) ) { return true; }
		return self::la_quan_tri( $vai_tro );
	}

	public static function vai_tro_vao() {
		$ds = get_option( 'vhg_vai_tro_vao' );
		if ( ! is_array( $ds ) || ! count( $ds ) ) { return self::VAI_TRO_MAC_DINH; }
		$ra = array();
		foreach ( $ds as $v ) {
			$v = (string) $v;
			if ( in_array( $v, self::VAI_TRO_TAT_CA, true ) && ! in_array( $v, $ra, true ) ) { $ra[] = $v; }
		}
		/* Rỗng sau khi lọc thì về mặc định. Rỗng là KHÔNG AI vào được, kể cả Admin, và không có
		   đường nào tự mở lại ngoài cơ sở dữ liệu. */
		return count( $ra ) ? $ra : self::VAI_TRO_MAC_DINH;
	}

	/**
	 * PIN sạch — CẮT ĐUÔI ".0" TRƯỚC KHI bỏ ký tự lạ.
	 *
	 * ⚠️ THỨ TỰ HAI PHÉP LÀ QUAN TRỌNG. Bỏ ký tự lạ trước thì `"571394.0"` thành `"5713940"` —
	 *    bảy chữ số, vẫn khớp luật 4–8, nên KHÔNG báo lỗi ở đâu cả, chỉ là không ai gõ trúng.
	 *    Sai âm thầm còn tệ hơn sai ồn ào.
	 * Không đụng số 0 đứng đầu: `"0123"` giữ nguyên, vì đó là PIN thật của người ta.
	 */
	public static function pin_sach( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) { return ''; }
		if ( preg_match( '/^(\d+)\.0*$/', $s, $m ) ) { $s = $m[1]; }
		return preg_replace( '/\D+/', '', $s );
	}

	/** Nguồn người dùng đang dùng: 'chung' | 'rieng'. */
	public static function nguon() {
		return 'rieng' === get_option( 'vhg_nguon_nguoidung' ) ? 'rieng' : 'chung';
	}

	public static function co_bang_chung() {
		global $wpdb;
		$t = $wpdb->prefix . 'vhcp_cfg';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * Danh sách người dùng: [ ['ten','pin','vaiTro','coso'], … ]
	 * @return array|WP_Error
	 */
	public static function users() {
		global $wpdb;

		if ( 'rieng' === self::nguon() ) {
			$out = array();
			foreach ( (array) get_option( 'vhg_nguoidung' ) as $u ) {
				$u = (array) $u;
				$ten = trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) );
				if ( '' === $ten ) { continue; }
				$out[] = array(
					'ten'    => $ten,
					'pin'    => self::pin_sach( isset( $u['pin'] ) ? $u['pin'] : '' ),
					'vaiTro' => (string) ( isset( $u['vaiTro'] ) ? $u['vaiTro'] : 'Cửa hàng trưởng' ),
					'coso'   => (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ),
				);
			}
			return $out;
		}

		if ( ! self::co_bang_chung() ) {
			return new WP_Error( 'thieu_bang', 'Đang đặt nguồn người dùng là "dùng chung với Vận hành '
				. 'chi phí" nhưng không thấy bảng ' . $wpdb->prefix . 'vhcp_cfg. Vào Ghế Massage → '
				. 'Cài đặt trang ngoài, chuyển sang "danh sách riêng", hoặc cài plugin Vận hành chi phí.' );
		}

		$t    = $wpdb->prefix . 'vhcp_cfg';
		$rows = VHG_DB::rows( $wpdb->prepare(
			"SELECT cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", 'CH_NguoiDung' ) );
		$out = array();
		foreach ( $rows as $r ) {
			$a = json_decode( (string) $r['cols'], true );
			if ( ! is_array( $a ) ) { continue; }
			$ten = isset( $a[0] ) ? trim( (string) $a[0] ) : '';
			if ( '' === $ten ) { continue; }
			$out[] = array(
				'ten'    => $ten,
				'pin'    => isset( $a[1] ) ? self::pin_sach( $a[1] ) : '',
				'vaiTro' => isset( $a[2] ) ? trim( (string) $a[2] ) : '',
				'coso'   => isset( $a[3] ) ? trim( (string) $a[3] ) : '',
			);
		}
		return $out;
	}

	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) {
			return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số' );
		}
		if ( self::bi_khoa() ) {
			return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút' );
		}
		$users = self::users();
		if ( is_wp_error( $users ) ) {
			return array( 'ok' => false, 'error' => $users->get_error_message() );
		}
		foreach ( $users as $u ) {
			if ( '' === $u['pin'] || $u['pin'] !== $pin ) { continue; }
			self::xoa_dem_sai();
			$role = '' !== $u['vaiTro'] ? $u['vaiTro'] : 'Nhân viên';
			if ( ! in_array( $role, self::vai_tro_vao(), true ) ) {
				/* Nói rõ "không đủ quyền", KHÔNG nói "PIN sai": PIN đúng mà báo sai thì người ta
				   gõ lại mười lần rồi tự khoá mình, và đi tìm một cái PIN mới vốn không tồn tại. */
				return array( 'ok' => false, 'error' => 'Tài khoản ' . $u['ten'] . ' (' . $role
					. ') không được xem doanh thu ghế.' );
			}
			return array( 'ok' => true, 'name' => $u['ten'], 'role' => $role, 'coso' => $u['coso'],
				'token' => self::phat_token( $u['ten'], $role, $u['coso'], $u['pin'] ) );
		}
		self::dem_sai();
		return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp' );
	}

	/**
	 * $pin — PIN vừa xác thực đúng ở `login()`, ghi kèm vào phiên để `boot_tu_ai()` dùng lại
	 * thẳng qua `pin_phien_tu_token()`, khỏi phải dò ngược theo (tên, cơ sở). Xem khối 🔴 ở
	 * bảng `phien` (class-vhg-db.php). Rỗng vẫn nhận (tương thích chỗ gọi cũ nếu có) —
	 * `pin_phien_tu_token()` trả rỗng, và `boot_tu_ai()` tự rớt về đường dò cũ trong ca đó.
	 */
	public static function phat_token( $ten, $role, $coso, $pin = '' ) {
		global $wpdb;
		$t = VHG_DB::t( 'phien' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( $t, array( 'token' => $tok, 'ten' => (string) $ten,
			'vai_tro' => (string) $role, 'coso' => (string) $coso, 'pin' => (string) $pin,
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ) ) );
		return $tok;
	}

	/**
	 * Ai đang cầm token này.
	 *
	 * ⚠️ XÉT LẠI VAI TRÒ MỖI LƯỢT, không tin vai trò đã ghi lúc phát token. Bỏ một vai trò khỏi
	 *    danh sách vào được mà phiên cũ vẫn chạy tiếp 30 ngày thì phép "đóng cửa" không đóng gì.
	 */
	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = VHG_DB::t( 'phien' );
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token ), ARRAY_A );
		if ( ! $r ) { return null; }
		if ( ! in_array( (string) $r['vai_tro'], self::vai_tro_vao(), true ) ) { return null; }
		return array( 'name' => $r['ten'], 'role' => $r['vai_tro'], 'coso' => $r['coso'] );
	}

	/**
	 * PIN đã dùng để mở phiên này — CHỈ cho `bc_boot_tu_token` (xem VHG_BaoCao::boot_tu_ai()),
	 * KHÔNG gộp vào `user_by_token()`. Lý do tách riêng: kết quả `user_by_token()` (`$ai`) được
	 * nhúng thẳng vào JSON trả cho trình duyệt ở `so_lieu()` (mọi lượt tải trang) — gộp PIN vào
	 * đó là in PIN ra network tab của MỌI người, mọi lượt, đúng thứ cả tệp này tránh từ đầu.
	 * Hàm này đứng riêng, chỉ gọi tường minh đúng một chỗ.
	 */
	public static function pin_phien_tu_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return ''; }
		$t = VHG_DB::t( 'phien' );
		$p = $wpdb->get_var( $wpdb->prepare(
			"SELECT pin FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token ) );
		return null === $p ? '' : (string) $p;
	}

	public static function logout( $token ) {
		global $wpdb;
		$wpdb->delete( VHG_DB::t( 'phien' ), array( 'token' => (string) $token ) );
		return array( 'ok' => true );
	}

	// ------------------------------------------------------------ hãm thử PIN
	/* PIN 4–8 số là không gian rất nhỏ. Không hãm thì một máy dò hết 6 số trong vài phút, và
	   phần thưởng là toàn bộ doanh thu 26 cửa hàng. */
	private static function khoa_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhg_fail_' . md5( $ip );
	}
	private static function bi_khoa()     { return (int) get_transient( self::khoa_key() ) >= 10; }
	private static function dem_sai()     { $k = self::khoa_key(); set_transient( $k, (int) get_transient( $k ) + 1, 600 ); }
	private static function xoa_dem_sai() { delete_transient( self::khoa_key() ); }
	public static function mo_khoa()      { delete_transient( self::khoa_key() ); }
}
