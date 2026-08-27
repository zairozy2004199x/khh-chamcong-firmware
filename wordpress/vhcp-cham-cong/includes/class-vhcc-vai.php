<?php
/**
 * VAI TRÒ & QUYỀN — MỘT NƠI DUY NHẤT TRẢ LỜI "AI ĐƯỢC LÀM GÌ".
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY — HỆ ĐANG TỰ CHỐI CHÍNH NGƯỜI CÓ QUYỀN
 * =============================================================================================
 * Anh Thắng: *"hiện tại hệ thống đang xung đột phân quyền nên cần lại lại theo hướng đơn giản"*.
 * Xung đột ấy có thật, và đây là chỗ nó nằm:
 *
 *   • Thẻ phiên mang vai trò dạng TÊN TIẾNG VIỆT — `'Quản lý'`, `'Kế toán cá nhân'`.
 *   • `VHCC_NhanSu` lại so bằng MÃ HOA — `'QUAN_LY'`, `'KE_TOAN'`, qua `strtoupper()`.
 *   • `strtoupper('Quản lý')` cho ra `'QUảN Lý'` — PHP không hạ/nâng được chữ có dấu. Nên phép
 *     so ấy TRƯỢT với mọi vai trừ đúng một vai: `'Admin'` -> `'ADMIN'`, tình cờ khớp.
 *
 * Hậu quả: Quản lý, Kế toán, Cửa hàng trưởng bị chối ở mọi cửa hỏi qua `VHCC_NhanSu`, mà màn
 * hình chỉ nói "không đủ quyền" — không nói rằng hai nửa hệ thống đang gọi cùng một vai bằng
 * hai cái tên khác nhau. Đây đúng loại lỗi im lặng: không có gì đỏ, chỉ có người dùng đúng
 * quyền mà không vào được.
 *
 * Thêm ba nơi nữa tự quyết định lấy: `VHCC_Auth::vai_tro_vao()` (danh sách vai vào cổng),
 * `VHCC_Web::VAI_TRO` (hai vai cứng trong mã), `VHCC_Tram` (vai sentinel riêng). Bốn nơi, bốn
 * bộ luật, và không bộ nào biết ba bộ kia.
 *
 * =============================================================================================
 * MÔ HÌNH ANH THẮNG CHỐT — NĂM BẬC, BẬC TRÊN LÀM ĐƯỢC MỌI VIỆC CỦA BẬC DƯỚI
 * =============================================================================================
 *   1. Nhân viên          — chấm công online của mình, xem công của mình.
 *   2. Cửa hàng trưởng    — thêm: chấm công bù cho nhân viên, xem công cơ sở mình, lên lịch làm
 *                           cho cửa hàng, báo lỗi lên trên.
 *   3. Quản lý            — thêm: xem công MỌI cơ sở, xử lý lỗi cơ sở báo lên, báo xuống cho
 *                           cửa hàng trưởng / nhân viên chấm sai.
 *   4. Kế toán            — thêm: lương, đơn giá, ngày nghỉ lễ, hồ sơ nhân sự, cấp PIN.
 *                           *"full quyền ngoài admin"*.
 *   5. Admin              — thêm: máy chấm công, cài đặt hệ thống, xem PIN người khác.
 *
 * Bậc thang chứ không phải ô tích rời: anh mô tả đúng một thang, và thang thì không có tổ hợp
 * nào lệch được. Ô tích rời cho 5 vai × 12 quyền là 60 ô, và ngày nào đó có người tích nhầm
 * cho cả cơ sở xem lương.
 *
 * =============================================================================================
 * ⚠️ MẶC ĐỊNH LÀ BẬC THẤP NHẤT, KHÔNG PHẢI BẬC CAO
 * =============================================================================================
 * Vai trò ghi sai chính tả, vai lạ, ô trống -> Nhân viên. Đoán lên cao một lần là mở bảng lương
 * cho một dòng gõ nhầm. `ma()` nhận mọi kiểu viết mà sổ sách thực tế đang có, nhưng cái gì
 * không nhận ra thì rơi xuống đáy chứ không lên đỉnh.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Vai {

	const NV      = 'NHAN_VIEN';
	const CHT     = 'CUA_HANG_TRUONG';
	const QL      = 'QUAN_LY';
	const KE_TOAN = 'KE_TOAN';
	const ADMIN   = 'ADMIN';

	/** Bậc — số lớn hơn làm được mọi việc của số nhỏ hơn. */
	const BAC = array(
		self::NV      => 1,
		self::CHT     => 2,
		self::QL      => 3,
		self::KE_TOAN => 4,
		self::ADMIN   => 5,
	);

	/** Tên hiện ra màn hình. Mã là thứ để so; tên là thứ để đọc. Đừng so bằng tên. */
	const TEN = array(
		self::NV      => 'Nhân viên',
		self::CHT     => 'Cửa hàng trưởng',
		self::QL      => 'Quản lý',
		self::KE_TOAN => 'Kế toán',
		self::ADMIN   => 'Admin',
	);

	/**
	 * QUYỀN -> BẬC TỐI THIỂU.
	 *
	 * 🔴 Thêm việc mới thì PHẢI khai vào đây. `duoc()` chối mọi quyền chưa khai — quên khai là
	 *    bị chối, không phải được lọt. Danh sách trắng, mặc định CHỐI: một quyền quên khai mà
	 *    mặc định cho qua thì không ai phát hiện ra, vì mọi thứ vẫn chạy.
	 */
	const QUYEN = array(
		/* --- bậc 1: ai cũng có --- */
		'cham_online'  => self::NV,       // tự chấm công bằng điện thoại
		'cong_minh'    => self::NV,       // xem bảng công của CHÍNH mình

		/* --- bậc 2: cửa hàng trưởng --- */
		'cong_coso'    => self::CHT,      // xem bảng công cơ sở mình (còn phải qua co_quyen_coso)
		'cham_bu'      => self::CHT,      // chấm công bù cho nhân viên cơ sở mình
		'lich_lam'     => self::CHT,      // lên lịch làm việc cho cửa hàng
		'bao_loi'      => self::CHT,      // gắn cờ / báo lỗi lên trên
		'ho_so_xem'    => self::CHT,      // xem hồ sơ người CỦA CƠ SỞ MÌNH (không có ô lương)

		/* --- bậc 3: quản lý --- */
		'cong_tat_ca'  => self::QL,       // xem công MỌI cơ sở, không cần khai cơ sở
		'xu_ly_loi'    => self::QL,       // kết luận / đóng cờ cơ sở báo lên
		'nap_cong'     => self::QL,       // nạp cả tháng công từ .csv Sheets cũ.
		                                  // ⚠️ Cố ý ĐẶT TRÊN cham_bu: bù là sửa MỘT ô của một
		                                  // người, còn nạp là đổ hàng nghìn lượt vào cả một
		                                  // tháng của cả cơ sở. Cùng gọi là "thêm giờ", nhưng
		                                  // hai bậc rủi ro khác hẳn nhau.
		'ngoai_coso'   => self::QL,       // việc ẢNH HƯỞNG NGOÀI phạm vi một cửa hàng:
		                                  // tăng cường, khoá bảng, xoá thống kê, cấu hình lịch

		/* --- bậc 4: kế toán ("full quyền ngoài admin") --- */
		'luong'        => self::KE_TOAN,  // bảng lương, đơn giá, ngày công chuẩn
		'ngay_le'      => self::KE_TOAN,  // lịch nghỉ lễ
		'ho_so'        => self::KE_TOAN,  // sửa hồ sơ nhân sự, cấp PIN, cho nghỉ việc
		'xem_luong_hs' => self::KE_TOAN,  // ô Lương cơ bản / số tài khoản trong hồ sơ

		/* --- bậc 5: admin --- */
		'he_thong'     => self::ADMIN,    // máy chấm công, cài đặt, nguồn người dùng
		'sua_gio'      => self::ADMIN,    // SỬA ĐÈ lên giờ đã có (kể cả giờ máy ghi), và xoá giờ
		                                  // ⚠️ Cố ý ĐẶT TRÊN cả `nap_cong`. Bù và nạp chỉ THÊM
		                                  // vào ô trống; việc này ĐÈ LÊN thứ máy đã ghi, tức là
		                                  // xoá mất bằng chứng gốc. Anh Thắng 26/08 chốt: *"admin
		                                  // có quyền chỉnh sửa lại giờ công cho nhân viên"* —
		                                  // đúng chữ ADMIN, không nới xuống Quản lý.
		'xem_pin'      => self::ADMIN,    // nhìn thấy PIN của người khác
		'doi_ma_nv'    => self::ADMIN,    // ĐỔI Mã NV của một người
		                                  // 🔴 Anh Thắng 27/08/2026: *"Mã NV thì cố định chỉ có
		                                  // admin chỉnh được thôi"*. Trước đây gác bằng
		                                  // `ngoai_coso` (bậc Quản lý) — tức Quản lý đổi được.
		                                  // Mã NV là KHOÁ: mọi lượt chấm công, mọi dòng lương,
		                                  // mọi bảng có cột ma_nv đều trỏ vào nó. Đổi sai một
		                                  // mã là công của người này rơi sang người kia, và chỉ
		                                  // lộ ra ở bảng lương cuối tháng.
		'xoa_ho_so'    => self::ADMIN,    // XOÁ hẳn một hồ sơ khỏi sổ
		                                  // ⚠️ Cùng bậc với đổi mã, cùng một lý do: cả hai đều
		                                  // làm dữ liệu cũ mất chỗ bám. `VHCC_NhanSu::xoa_ho_so`
		                                  // vẫn chặn thêm khi người đó CÒN chấm công — hai
		                                  // tầng, không thay nhau.
	);

	/* ====================================================================== vai tự tạo */

	/**
	 * VAI TỰ TẠO — tên riêng của công ty, quyền thì kế thừa một VAI GỐC.
	 *
	 * =============================================================================================
	 * Anh Thắng 27/08/2026: *"muốn thêm bảng vai trò: vì sau anh cần vai trò kế toán nhân sự,
	 * kế toán Posh"*.
	 * =============================================================================================
	 * 🔴 KẾ THỪA MỘT VAI GỐC, KHÔNG KHAI LẠI TỪNG QUYỀN.
	 * Cách trông có vẻ "đầy đủ" hơn là cho mỗi vai mới một bảng 19 ô tích. Đừng. Thang năm bậc
	 * là thứ KHÔNG CÓ TỔ HỢP NÀO LỆCH ĐƯỢC — bậc trên làm được mọi việc bậc dưới, hết. Mở ra ô
	 * tích rời thì tới vai thứ tư là có người tích cho một vai vừa xem được bảng lương vừa không
	 * xem được bảng công của chính cơ sở mình, và không ai giải thích nổi vì sao.
	 *
	 * "Kế toán POSH" và "Kế toán nhân sự" khác nhau ở chỗ nào? Ở TÊN — để điều phối, để biết đơn
	 * này của ai. Còn quyền thì cả hai đều là Kế toán. Chỗ nào thật sự cần lệch thì đã có bảng
	 * mở/khoá từng trang cho từng người ở `VHCC_Cong` — lệch có tên, có chỗ soát lại.
	 *
	 * ⚠️ Nhớ tạm trong biến static: `ma()` được gọi mỗi hàng bảng, mà một màn có 50 hàng × 3 cột.
	 *    Đọc `get_option` mỗi lượt là 150 lượt đọc cho một trang.
	 */
	const O_THEM  = 'vhcc_vai_them';
	/** Trần số vai tự tạo. Quá con số này thì vấn đề không còn là thiếu vai nữa. */
	const THEM_TOI_DA = 30;

	private static $nho_them = null;

	/** Bảng vai tự tạo: [ 'Tên vai' => MÃ VAI GỐC ]. */
	public static function them() {
		if ( null === self::$nho_them ) {
			$x = get_option( self::O_THEM );
			$ra = array();
			foreach ( (array) $x as $ten => $goc ) {
				$ten = trim( (string) $ten );
				$goc = (string) $goc;
				if ( '' === $ten || ! isset( self::BAC[ $goc ] ) ) { continue; }
				$ra[ $ten ] = $goc;
			}
			self::$nho_them = $ra;
		}
		return self::$nho_them;
	}

	/** Quên phần nhớ tạm — gọi sau mỗi lượt ghi, và bộ thử cũng cần. */
	public static function quen_nho() { self::$nho_them = null; }

	/** Mọi tên vai đang dùng được: vai gốc trước, vai tự tạo sau. */
	public static function ds_ten() {
		$ra = array();
		/* ⚠️ Gác `class_exists` cùng hàm với lời gọi. `VHCC_Vai` nạp TRƯỚC `VHCC_Auth` trong tệp
		   plugin — gọi hụt một hằng của lớp chưa nạp là Fatal error, trắng cả trang. */
		if ( class_exists( 'VHCC_Auth' ) && defined( 'VHCC_Auth::VAI_TRO_TAT_CA' ) ) {
			foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $t ) { $ra[] = $t; }
		} else {
			foreach ( self::TEN as $t ) { $ra[] = $t; }
		}
		foreach ( array_keys( self::them() ) as $t ) {
			if ( ! in_array( $t, $ra, true ) ) { $ra[] = $t; }
		}
		return $ra;
	}

	/** Khoá so sánh của một tên vai — bỏ dấu, hạ chữ, bỏ mọi thứ không phải chữ/số. */
	public static function khoa_ten( $s ) {
		$x = self::bo_dau( trim( (string) $s ) );
		return trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^a-z0-9]+/', ' ', $x ) ) );
	}

	/**
	 * Thêm / sửa một vai tự tạo.
	 *
	 * @param string $ten Tên hiện ra màn hình, giữ nguyên dấu.
	 * @param string $goc Mã vai gốc mà nó kế thừa quyền.
	 */
	public static function dat_them( $u, $ten, $goc ) {
		if ( ! self::duoc( $u, 'ho_so' ) ) {
			return array( 'ok' => false, 'error' => 'Khai vai trò cần vai Kế toán trở lên.' );
		}
		$ten = trim( (string) $ten );
		$goc = (string) $goc;
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên vai.' ); }
		if ( mb_strlen( $ten ) > 40 ) {
			return array( 'ok' => false, 'error' => 'Tên vai dài quá 40 ký tự.' );
		}
		if ( ! isset( self::BAC[ $goc ] ) ) {
			return array( 'ok' => false, 'error' => 'Vai gốc không hợp lệ.' );
		}
		/* 🔴 KHÔNG TẠO VAI CÓ GỐC CAO HƠN BẬC MÌNH. Thiếu chốt này thì một Kế toán tạo vai
		   "Trợ lý" gốc Admin, rồi gán cho một người khác, rồi nhờ người ấy nâng mình lên — ba
		   bước, không bước nào bị chối, và không dòng nhật ký nào. */
		if ( self::BAC[ $goc ] > self::bac( $u ) ) {
			return array( 'ok' => false, 'error' => 'Không tạo được vai kế thừa quyền cao hơn vai của '
				. 'chính mình (' . self::ten( $u ) . ').' );
		}
		/* ⚠️ TRÙNG TÊN VAI GỐC LÀ ĐÈ LÊN LUẬT GỐC. `ma()` tra bảng tự tạo TRƯỚC, nên một dòng
		   tên "Quản lý" gốc Nhân viên là hạ toàn bộ Quản lý của công ty xuống bậc 1 — im lặng. */
		$k = self::khoa_ten( $ten );
		foreach ( self::TEN as $t_goc ) {
			if ( self::khoa_ten( $t_goc ) === $k ) {
				return array( 'ok' => false, 'error' => '"' . $ten . '" trùng tên một vai gốc của hệ.' );
			}
		}
		if ( class_exists( 'VHCC_Auth' ) && defined( 'VHCC_Auth::VAI_TRO_TAT_CA' ) ) {
			foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $t_goc ) {
				if ( self::khoa_ten( $t_goc ) === $k ) {
					return array( 'ok' => false, 'error' => '"' . $ten . '" trùng tên một vai gốc của hệ.' );
				}
			}
		}
		$b = self::them();
		if ( ! isset( $b[ $ten ] ) && count( $b ) >= self::THEM_TOI_DA ) {
			return array( 'ok' => false, 'error' => 'Đã có ' . self::THEM_TOI_DA
				. ' vai tự tạo — nhiều hơn thế thì vấn đề không còn nằm ở chỗ thiếu vai nữa.' );
		}
		/* Trùng tên một vai tự tạo KHÁC (chỉ khác dấu / hoa thường) thì cũng chối: hai dòng
		   trông như một trong bảng là sớm muộn có người sửa nhầm dòng. */
		foreach ( $b as $t_cu => $x ) {
			if ( $t_cu !== $ten && self::khoa_ten( $t_cu ) === $k ) {
				return array( 'ok' => false, 'error' => '"' . $ten . '" trùng với vai đã có: "' . $t_cu . '".' );
			}
		}
		$b[ $ten ] = $goc;
		update_option( self::O_THEM, $b );
		self::quen_nho();
		return array( 'ok' => true );
	}

	/**
	 * Xoá một vai tự tạo.
	 *
	 * 🔴 CHẶN KHI CÒN NGƯỜI MANG VAI ẤY. Xoá đi thì `ma()` không tra ra nữa và họ rơi xuống đáy
	 *    thang — mất sạch quyền, im lặng, và chỉ lộ ra khi từng người kêu lên. Đổi vai cho họ
	 *    trước rồi hẵng xoá.
	 */
	public static function xoa_them( $u, $ten ) {
		if ( ! self::duoc( $u, 'ho_so' ) ) {
			return array( 'ok' => false, 'error' => 'Khai vai trò cần vai Kế toán trở lên.' );
		}
		$ten = trim( (string) $ten );
		$b   = self::them();
		if ( ! isset( $b[ $ten ] ) ) { return array( 'ok' => false, 'error' => 'Không có vai "' . $ten . '".' ); }
		$so = self::dem_nguoi( $ten );
		if ( $so > 0 ) {
			return array( 'ok' => false, 'error' => 'Còn ' . $so . ' người đang mang vai "' . $ten
				. '" — đổi vai cho họ trước, kẻo xoá xong là họ rơi xuống Nhân viên mà không ai báo.' );
		}
		unset( $b[ $ten ] );
		update_option( self::O_THEM, $b );
		self::quen_nho();
		return array( 'ok' => true );
	}

	/** Bao nhiêu hồ sơ đang mang vai này. */
	public static function dem_nguoi( $ten ) {
		global $wpdb;
		if ( ! class_exists( 'VHCC_DB' ) || ! method_exists( 'VHCC_DB', 't' ) ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE vai_tro=%s', trim( (string) $ten ) ) );
	}

	/**
	 * Mọi kiểu viết vai trò -> MÃ.
	 *
	 * Sổ sách thực tế đang có đủ kiểu, vì dữ liệu tới từ bốn nguồn khác nhau (sổ PhanQuyen của
	 * app cũ dùng mã hoa, hồ sơ nhân sự người ta gõ tay, danh sách riêng của plugin dùng tên
	 * tiếng Việt, app chi phí có thêm "Kế toán NCC"). Nhận hết ở MỘT chỗ, để chỗ khác chỉ việc
	 * so mã với mã.
	 *
	 * ⚠️ Bỏ dấu trước khi so. Không bỏ dấu thì `'Quản lý'` và `'Quan ly'` là hai vai khác nhau,
	 *    mà người gõ hồ sơ thì lúc có dấu lúc không.
	 */
	public static function ma( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) { return self::NV; }
		$s = self::bo_dau( $s );                       // 'Quản lý' -> 'quan ly'
		$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );  // '_' và '-' cũng thành khoảng trắng
		$s = trim( preg_replace( '/\s+/', ' ', $s ) );

		/* 🔴 VAI TỰ TẠO TRA TRƯỚC. Đặt sau thì một vai tên "Kế toán POSH" rơi vào nhánh
		   `strpos($s,'ke toan')===0` ở dưới và thành Kế toán — đúng bậc, nhưng chỉ ĐÚNG TÌNH CỜ.
		   Ngày anh Thắng tạo một vai tên "Điều phối POSH" gốc Quản lý thì nhánh dưới không nhận
		   ra, và người ấy rơi xuống Nhân viên mà không có gì báo. */
		$them = self::them();
		if ( $them ) {
			foreach ( $them as $t_them => $g_them ) {
				if ( self::khoa_ten( $t_them ) === $s ) { return $g_them; }
			}
		}

		if ( 'admin' === $s || 'quan tri' === $s || 'super admin' === $s ) { return self::ADMIN; }
		if ( 'quan ly' === $s || 'manager' === $s ) { return self::QL; }
		if ( 'cua hang truong' === $s || 'cht' === $s || 'store manager' === $s ) { return self::CHT; }
		/* Mọi thứ bắt đầu bằng "ke toan" -> Kế toán: sổ đang có "Kế toán", "Kế toán cá nhân",
		   "Kế toán NCC". Anh Thắng chốt hệ chấm công chỉ có MỘT loại kế toán, và loại đó full
		   quyền ngoài admin. (Bên app chi phí "Kế toán NCC" vẫn là vai riêng — bảng này chỉ
		   nói về quyền TRONG hệ chấm công.) */
		if ( 0 === strpos( $s, 'ke toan' ) || 'accountant' === $s ) { return self::KE_TOAN; }
		if ( 'nhan vien' === $s || 'staff' === $s || 'employee' === $s ) { return self::NV; }
		return self::NV;   // lạ -> đáy thang. KHÔNG đoán lên cao.
	}

	/** Bỏ dấu tiếng Việt + hạ chữ thường. Tự làm, không nhờ iconv: host thiếu locale trả về '?'. */
	public static function bo_dau( $s ) {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
		$b = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $b as $thay => $bo ) {
			$s = str_replace( self::tach_chu( $bo ), $thay, $s );
		}
		return $s;
	}

	/** Chuỗi UTF-8 -> mảng từng ký tự. `str_split` cắt theo BYTE nên xé nát chữ có dấu. */
	private static function tach_chu( $s ) {
		$r = preg_split( '//u', (string) $s, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Vai trò của người đang đăng nhập, dạng MÃ.
	 *
	 * ⚠️ Đọc CẢ `role` lẫn `vai_tro`: `user_by_token()` trả khoá `role`, còn dòng đọc thẳng từ
	 *    bảng thì trả `vai_tro`. Chỉ đọc một khoá là nửa số chỗ gọi nhận về rỗng — tức Nhân
	 *    viên — mà không có lỗi nào phát ra.
	 */
	public static function cua( $u ) {
		if ( is_string( $u ) ) { return self::ma( $u ); }
		$u = (array) $u;
		if ( isset( $u['role'] ) && '' !== trim( (string) $u['role'] ) ) { return self::ma( $u['role'] ); }
		if ( isset( $u['vai_tro'] ) ) { return self::ma( $u['vai_tro'] ); }
		return self::NV;
	}

	public static function bac( $u ) {
		$m = self::cua( $u );
		return isset( self::BAC[ $m ] ) ? self::BAC[ $m ] : 1;
	}

	/** Tên hiện ra màn hình của vai người này. */
	public static function ten( $u ) {
		$m = self::cua( $u );
		return isset( self::TEN[ $m ] ) ? self::TEN[ $m ] : self::TEN[ self::NV ];
	}

	/**
	 * Người này có quyền `$quyen` không?
	 *
	 * 🔴 Quyền KHÔNG khai trong bảng QUYEN -> CHỐI. Xem chú thích ở bảng.
	 */
	public static function duoc( $u, $quyen ) {
		$q = (string) $quyen;
		if ( ! isset( self::QUYEN[ $q ] ) ) { return false; }
		$can = self::QUYEN[ $q ];
		$can = isset( self::BAC[ $can ] ) ? self::BAC[ $can ] : 99;
		return self::bac( $u ) >= $can;
	}

	/** Câu báo khi bị chối — nói RÕ cần bậc nào, để người ta biết đi xin ai. */
	public static function loi( $u, $quyen, $viec = '' ) {
		$q   = (string) $quyen;
		$can = isset( self::QUYEN[ $q ] ) ? self::QUYEN[ $q ] : self::ADMIN;
		$ten = isset( self::TEN[ $can ] ) ? self::TEN[ $can ] : 'Admin';
		return ( '' !== $viec ? $viec . ' — ' : '' ) . 'tài khoản của anh/chị đang là '
			. self::ten( $u ) . ', việc này cần bậc ' . $ten . ' trở lên.';
	}

	/** Mọi mã vai, từ thấp lên cao — để màn Cài đặt vẽ ô chọn theo đúng thứ tự thang. */
	public static function tat_ca() {
		return array( self::NV, self::CHT, self::QL, self::KE_TOAN, self::ADMIN );
	}
}
