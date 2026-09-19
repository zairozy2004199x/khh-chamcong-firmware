<?php
/**
 * ĐẨY NGƯỜI TỪ SỔ NHÂN SỰ SANG HỆ VẬN HÀNH CHI PHÍ.
 *
 * =================================================================================================
 * Anh Thắng 28/08/2026: *"bên quản lý nhân sự chưa cho đẩy nhân sự sang vận hành chi phí"*, rồi
 * *"Đồng bộ nhân sự với hệ thống vận hành chi phí luôn nhé em"*.
 *
 * Bảng "Ai vào được trang nào" đã có bốn cột — Quản trị chấm công · Trạm · Nội bộ · Ghế massage —
 * mà thiếu đúng cột chi phí. Nên bên ấy vẫn phải khai tay từng người ở màn 🔐 Người dùng & Phân
 * quyền: gõ lại tên, gõ lại PIN, chọn lại cơ sở. Hai sổ chép tay là hai sổ lệch nhau.
 *
 * =================================================================================================
 * 🔴 KHÁC HỆ GHẾ Ở MỘT ĐIỂM CỐT TỬ: SỔ BÊN CHI PHÍ CÓ NGƯỜI THẬT ĐANG DÙNG.
 * =================================================================================================
 * Hệ ghế có một sổ riêng do chính mình dựng ra. Còn `CH_NguoiDung` bên chi phí là sổ ĐANG CHẠY —
 * ảnh anh gửi có Admin, hai Kế toán, Quản lý, và hai chục nhân viên cơ sở, mỗi người một PIN mà
 * họ đang gõ hằng ngày. Nên:
 *
 * ⚠️ CHỈ THÊM VÀ SỬA HÀNG CỦA NGƯỜI ĐƯỢC ĐẨY. Không đụng một hàng nào khác, không sắp xếp lại,
 *    không dọn "cho gọn". Ghi đè cả sổ là xoá sạch PIN của những người bên ấy tự khai — và họ
 *    chỉ phát hiện ra lúc đứng gõ PIN vào sáng hôm sau.
 *
 * ⚠️ GIỮ NGUYÊN NHỮNG CỘT BÊN ẤY TỰ KHAI: TK Có · Mã đối tượng · Đơn vị · Xem đơn vị. Đó là
 *    thông tin KẾ TOÁN, sổ nhân sự không biết và không được đoán. Đẩy mà xoá chúng là kế toán
 *    mất bảng khai của mình mà không ai báo.
 *
 * ⚠️ KHOÁ THEO MÃ NV, KHÔNG THEO TÊN. Sổ bên chi phí khoá theo tên, nhưng 400 nhân sự thì trùng
 *    tên là chuyện có thật (bảng nhân sự đang có 14 hồ sơ trùng tên). Nên giữ mã NV ở một sổ
 *    riêng bên này — đó là sợi dây duy nhất nối một hàng bên ấy về đúng một người.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DayChiPhi {

	/** Tên cột trên bảng "Ai vào được trang nào". */
	const COT = 'chi_phi';

	/** Sổ ghi mã NV của những người đã đẩy: [ maNV => tên đã ghi sang ]. */
	const O_DA_DAY = 'vhcc_day_chi_phi';

	/**
	 * 🔴 BẬC ADMIN, cùng bậc với đẩy sang hệ ghế và vì cùng một lý do: màn chi phí có ngăn TIỀN
	 *    (duyệt chi, quyết toán), và PIN đẩy sang là PIN chấm công dùng chung. Đẩy nhầm một
	 *    người là mở cửa buồng tiền cho họ.
	 */
	const QUYEN = 'he_thong';

	/** Vị trí các cột trong `CH_NguoiDung` — xem `VHCP_Cfg::headers()`. */
	const C_TEN = 0;
	const C_PIN = 1;
	const C_VAI = 2;
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 Ô NÀY CỐ Ý KHÔNG BAO GIỜ ĐƯỢC ĐẨY — CHỈ ĐỌC ĐỂ BÀY RA CHO NGƯỜI KHAI NHÌN
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 19/09/2026: *"cơ sở chấm công thì nó không liên quan đến chi phí. Khi đẩy nhân sự
	 * qua thì đẩy THÔNG TIN qua, chứ không phải đẩy QUYỀN QUẢN LÝ qua rồi chèn đi cơ sở hiện
	 * có"*.
	 *
	 * 🔴 HAI DANH MỤC KHÁC HẲN NHAU, CHUNG MỖI CÁI TÊN GỌI "CƠ SỞ".
	 *      · bên nhân sự: MÃ cửa hàng — `TUTU_BD`, `FZ_LTVT`, `FZ_SC_VIVO_T4`, `VP_KH-HCM`
	 *      · bên chi phí: TÊN gian hàng — `NHÀ MA BÌNH DƯƠNG`, `FARM PHAN THIẾT`, `VR TÂN AN`
	 *    Chốt phạm vi bên chi phí (`VHCP_Auth::trong_coso()`) so CHUỖI BẰNG NHAU, nên một mã
	 *    cửa hàng ghi vào đó không khớp gian nào. Hậu quả không phải "hiện thiếu" mà là HIỆN
	 *    TRẮNG: người ấy mở app ra không thấy đơn nào, kể cả đơn chính họ vừa lập — và đơn họ
	 *    lập cũng không ai mở lại được.
	 *
	 *    Đã xảy ra thật, hai lần cùng một nguyên nhân: "Ung Nguyễn Thùy Dương · TUTU_BD · chưa
	 *    thấy đơn" (14/09/2026), rồi cả loạt `FZ_SC_VIVO_T4` · `TUTU_TA` · `VP_KH-HCM`
	 *    (19/09/2026). Lần đầu chỉ gỡ cảnh báo ở giao diện chứ không chặn nguồn, nên nó về lại.
	 *
	 * 🔴 VÀ ĐÂY LÀ Ô QUYỀN, KHÔNG PHẢI Ô THÔNG TIN. Nó quyết định người ta ĐỌC ĐƯỢC SỔ TIỀN CỦA
	 *    GIAN NÀO — cùng nhóm với TK Có · Mã đối tượng · Đơn vị · Xem đơn vị, tức bảng khai của
	 *    kế toán. Sổ nhân sự ghi người ấy làm ở cửa hàng nào; nó không nói người ấy được xem
	 *    tiền của gian nào, và hai câu ấy chưa bao giờ là một.
	 *
	 * ⚠️ MÃ CỬA HÀNG VẪN ĐI CÙNG — DƯỚI DẠNG THÔNG TIN. `$hs['coso']` vẫn dựng và vẫn bày ở màn
	 *    soát ("Cơ sở · chức vụ") để kế toán biết người này làm ở đâu mà chọn đúng gian. Bày ra
	 *    thì giúp; ghi xuống thì hỏng.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const C_COSO = 3;
	const C_BO_PHAN = 6;
	/* Ô Mã NV — thêm bên chi phí 13/09/2026 (bản 1.155.0). Đây là sợi dây nối một hàng bên ấy
	   về đúng một người bên này, và nó chính là thứ cho phép một PIN vào được cả hai trang.
	   Đẩy mà bỏ trống ô này là hai bên lại phải nhận nhau bằng TÊN — đúng chỗ vốn hay lệch. */
	const C_MA_NV = 9;
	/** Số ô của một hàng `CH_NguoiDung`: ten·pin·vai·coso·tkCo·maDt·boPhan·donVi·xemDonVi·maNv */
	const SO_O = 10;

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * BỘ NỐI SANG APP ĐÍCH — NĂM ĐIỂM, VÀ CHỈ NĂM ĐIỂM NÀY BIẾT TÊN LỚP BÊN KIA
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 15/09/2026: *"Tạo tab lệnh để đẩy dữ liệu nv sang 1 trang chi phí văn phòng
	 * trước"*. Chi phí nay có bốn bản chạy song song (khu vui chơi · VP · MTD · HN), mỗi bản
	 * một bộ lớp riêng — `VHCP_Cfg`, `VHCPVP_Cfg`, `VHCPMTD_Cfg`…
	 *
	 * 🔴 CHÉP CẢ LỚP NÀY RA BỐN BẢN LÀ SAI. Nó dài hơn 500 dòng và chứa đúng những luật khó:
	 *    PIN lấy ở `nhan_vien`, bộ phận lấy ở `bo_phan` chứ không phải `chuc_vu`, PIN trùng thì
	 *    xoá PIN chứ không xoá hàng, sửa bốn ô chứ không ghi đè cả hàng. Bốn bản sao là bốn chỗ
	 *    phải nhớ sửa mỗi lần — tức sớm muộn chúng lệch nhau, và bản lệch sẽ là bản ít người dùng
	 *    nhất, nên không ai phát hiện.
	 *
	 * 🔴 NHƯNG CŨNG KHÔNG GỌI BẰNG TÊN LỚP ĐỘNG (`$c::read()`). `tools/test/kiem-goi-cheo.php`
	 *    soi bằng cách tìm chuỗi `LOP::ham` rồi đòi có `method_exists` gác cùng thân hàm. Tên lớp
	 *    nằm trong biến thì bộ soi KHÔNG THẤY — lời gọi chéo lọt khỏi lưới, mà chính cái lưới ấy
	 *    sinh ra sau một lần trắng cả trang (23/08/2026).
	 *
	 * NÊN: thân lớp không nhắc tên lớp bên kia nữa; chỉ NĂM hàm dưới đây nhắc, mỗi hàm gác
	 * `method_exists` NGAY TRONG THÂN NÓ. Bản cho một app chi phí khác chỉ việc kế thừa và viết
	 * lại đúng năm hàm này — xem `VHCC_DayChiPhiVP`.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Tên app đích, dùng trong câu báo cho người đọc. */
	public static function ten_he() { return 'Vận hành chi phí'; }

	/** Có app đích trên site này không, và bản bên ấy có đủ hàm để ghi không. */
	public static function co_he() {
		return class_exists( 'VHCP_Cfg' )
			&& method_exists( 'VHCP_Cfg', 'read' )
			&& method_exists( 'VHCP_Cfg', 'write' )
			&& defined( 'VHCP_Cfg::USER' );
	}

	/** Đọc sổ người dùng bên app đích. Chưa cài / bản cũ -> mảng rỗng. */
	public static function doc_user() {
		if ( ! class_exists( 'VHCP_Cfg' ) || ! method_exists( 'VHCP_Cfg', 'read' )
			|| ! defined( 'VHCP_Cfg::USER' ) ) {
			return array();
		}
		return (array) VHCP_Cfg::read( VHCP_Cfg::USER );
	}

	/** Ghi sổ người dùng bên app đích. Trả false nếu không ghi được. */
	public static function ghi_user( $rows ) {
		if ( ! class_exists( 'VHCP_Cfg' ) || ! method_exists( 'VHCP_Cfg', 'write' )
			|| ! defined( 'VHCP_Cfg::USER' ) ) {
			return false;
		}
		VHCP_Cfg::write( VHCP_Cfg::USER, $rows );
		return true;
	}

	/** Tên bộ phận mà app đích THẬT SỰ hiểu; không hiểu -> ''. */
	public static function bp_chuan( $x ) {
		if ( ! class_exists( 'VHCP_Cfg' ) || ! method_exists( 'VHCP_Cfg', 'bo_phan_chuan' ) ) {
			return '';
		}
		return (string) VHCP_Cfg::bo_phan_chuan( $x );
	}

	/** Danh sách bộ phận app đích đang có. */
	public static function bp_ds() {
		if ( ! class_exists( 'VHCP_Cfg' ) || ! defined( 'VHCP_Cfg::BO_PHAN_DS' ) ) { return array(); }
		return array_values( (array) VHCP_Cfg::BO_PHAN_DS );
	}

	/* ====================================================================== hỏi trạng thái */

	/**
	 * Có plugin chi phí trên site này không, và nó có đủ hàm để ghi không.
	 *
	 * ⚠️ Dò TỪNG HÀM, không dò mỗi tên lớp: bốn plugin cài độc lập nên bản có thể lệch nhau, và
	 *    gọi một hàm không tồn tại là Fatal — trắng cả trang, không phải một ô hỏng.
	 */
	public static function co_he_chi_phi() { return static::co_he(); }

	/** Sổ mã NV đã đẩy: [ maNV(chữ hoa) => tên đã ghi sang ]. */
	public static function da_day_ds() {
		$x = get_option( static::O_DA_DAY );
		return is_array( $x ) ? $x : array();
	}

	public static function da_day( $ma_nv ) {
		$ma = strtoupper( trim( (string) $ma_nv ) );
		if ( '' === $ma ) { return false; }
		$ds = static::da_day_ds();
		return isset( $ds[ $ma ] );
	}

	/** Ô trên bảng: 'mo' nếu đã đẩy, '' nếu chưa. */
	public static function o( $ma_nv ) {
		return static::da_day( $ma_nv ) ? 'mo' : '';
	}

	/* ====================================================================== ánh xạ vai */

	/**
	 * VAI BÊN CHẤM CÔNG -> VAI BÊN CHI PHÍ.
	 *
	 * Đi theo đúng bảng mà chính app chi phí đang dùng cho đăng nhập một lần
	 * (`VHCP_Auth::resolve_sso_user`) — hai đường vào cùng một hệ mà ánh xạ khác nhau thì cùng
	 * một người vào bằng hai cửa lại ra hai quyền.
	 *
	 * ⚠️ CỬA HÀNG TRƯỞNG -> 'Nhân viên', KHÔNG phải 'Quản lý'. Bên chi phí, 'Quản lý' duyệt được
	 *    chi của mọi cơ sở. Cửa hàng trưởng là người ĐỀ NGHỊ chi, không phải người duyệt.
	 */
	public static function vai_chi_phi( $vai_cc ) {
		$ma = class_exists( 'VHCC_Vai' ) && method_exists( 'VHCC_Vai', 'ma' )
			? VHCC_Vai::ma( $vai_cc ) : '';
		$b  = array(
			'ADMIN'   => 'Admin',
			'QUAN_LY' => 'Quản lý',
			'KE_TOAN' => 'Kế toán cá nhân',
		);
		return isset( $b[ $ma ] ) ? $b[ $ma ] : 'Nhân viên';
	}

	/* ====================================================================== dựng hàng */

	/**
	 * Hồ sơ để đẩy: [ ho_ten, pin, vai_cc, coso, bo_phan ]. null nếu không đẩy được.
	 *
	 * ⚠️ PIN Ở `nhan_vien.pin_dang_nhap`, KHÔNG ở `phan_quyen.pin`. Đã tra nhầm một lần
	 *    (28/08/2026, anh Thắng: *"Anh thấy lưu mà bên Posh chưa qua"*) — bảng `phan_quyen` là
	 *    sổ CŨ nạp từ Sheets, còn hồ sơ nhân sự mới là nơi cấp PIN bây giờ.
	 */
	public static function ho_so_day( $ma_nv ) {
		$ma = trim( (string) $ma_nv );
		if ( '' === $ma ) { return null; }
		$hs = VHCC_NhanSu::ho_so( $ma );
		if ( ! $hs ) { return null; }

		$pin = trim( (string) $hs['pin_dang_nhap'] );
		/* Sổ cũ ghi PIN từ Google Sheets nên có hàng ra "1234.0" — rửa đuôi, không thì bên kia
		   nhận một chuỗi không ai gõ được. */
		if ( preg_match( '/^(\d+)\.0*$/', $pin, $m ) ) { $pin = $m[1]; }
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) { return null; }

		$ten = trim( (string) $hs['ho_ten'] );
		if ( '' === $ten ) { return null; }

		/* ═════════════════════════════════════════════════════════════════════════════════
		 * 🔴 PHÒNG BAN LẤY TỪ CỘT `bo_phan`, KHÔNG PHẢI `chuc_vu`.
		 *
		 * Bản trước lấy thẳng `chuc_vu` nhét vào ô Bộ phận bên chi phí. Nhưng chức vụ là
		 * *Thu ngân · Ca trưởng · Giám sát · Bảo vệ · Pha chế* — việc người ta LÀM; còn ô Bộ
		 * phận bên kia là thứ quyết định họ thấy MẢNG CHI PHÍ nào. Hai bộ từ vựng không dính
		 * dáng gì nhau, nên đẩy một Thu ngân sang là ô Bộ phận của họ thành "Thu ngân", chốt
		 * phòng ban bên ấy đi tìm loại chi phí thuộc "Thu ngân", không thấy cái nào, và màn chi
		 * phí của người ấy gần như trắng — không một câu lỗi nào chỉ ra vì sao.
		 *
		 * Anh Thắng chốt luật 13/09/2026: *"quyết định bộ phận do nhân sự quyết định, bên chi
		 * phí chỉ biết bộ phận đó có được quyền không thôi"*. Sổ nhân sự đã có cột `bo_phan`
		 * (sơ đồ tổ chức — xem `VHCC_NhanSu::ds_bo_phan()`), nên đẩy đúng cột ấy sang.
		 *
		 * ⚠️ HAI DANH MỤC VẪN CHƯA CÙNG VỐN TỪ: bên này là *Phòng Kỹ Thuật · Phòng Marketing…*,
		 *    bên chi phí là *Kỹ thuật · Marketing · Cơ sở · Setup…*. Chuỗi đẩy sang mà không có
		 *    trong danh mục bên ấy thì chốt phòng ban của họ không khớp được gì. Màn soát bên
		 *    chi phí (Cấu hình → Người dùng → 🔍 Soát trùng) bày thẳng những ca ấy ra để xử.
		 *
		 * ⚠️ CHƯA KHAI THÌ ĐỂ RỖNG, ĐỪNG ĐOÁN. Rỗng bên chi phí nghĩa là "không bó phòng ban",
		 *    tức thấy mọi mảng — rộng hơn ý muốn, nhưng KHÔNG làm ai mất việc. Đoán bừa thì cắt
		 *    mất đúng mảng họ cần mà chẳng ai biết.
		 * ═════════════════════════════════════════════════════════════════════════════════ */
		return array(
			'ho_ten'  => $ten,
			'ma_nv'   => trim( (string) $hs['ma_nv'] ),
			'pin'     => $pin,
			'vai_cc'  => (string) $hs['vai_tro'],
			'coso'    => VHCC_NhanSu::chuan_coso( (string) $hs['cua_hang'] ),
			'bo_phan' => static::bo_phan_day( $hs ),
		);
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * BỘ PHẬN GỬI SANG CHI PHÍ — ĐƯỜNG NỚI QUYỀN ÂM THẦM, VÁ Ở ĐÂY
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 LỖI CŨ: gửi thẳng ô **Chức vụ** của hồ sơ chấm công sang cột Bộ phận của sổ người dùng
	 *    bên chi phí, KHÔNG kiểm gì. Mà bên ấy, `VHCP_Cfg::bo_phan_chuan()` quy mọi tên nó không
	 *    nhận ra về CHUỖI RỖNG — và chuỗi rỗng ở đó nghĩa là **KHÔNG BÓ BỘ PHẬN**, tức người ấy
	 *    nhìn thấy sổ chi phí của MỌI mảng.
	 *
	 * Nên ô Chức vụ — một ô chữ tự do, ai sửa hồ sơ cũng gõ được — là một đường NỚI QUYỀN. Gõ
	 *    đúng "Máy tự động" thì bị bó; gõ "Kế toán MTD", "máy tự động " thừa dấu cách, hay bất kỳ
	 *    tên phòng ban mới nào thì hết bó. Không một dòng nào báo, ở cả hai bên.
	 *
	 * ⚠️ VÀ NÓ SẮP BẬT THÀNH THƯỜNG XUYÊN. Anh Thắng 13/09/2026 đang sắp xếp lại phòng ban theo
	 *    mảng ("KVC · Phòng Kế Toán"…). Mỗi cái tên mới là một chuỗi bên chi phí không biết, tức
	 *    mỗi lượt đẩy là một người hết bị bó.
	 *
	 * VÁ: chỉ gửi tên mà bên kia THẬT SỰ hiểu. Thứ tự tra, hẹp trước rộng sau:
	 *   1. Ô **Bộ phận** của hồ sơ khớp một bộ phận của chi phí -> dùng luôn;
	 *   1b. Ô Bộ phận có trong BẢN ĐỒ PHÒNG BAN -> dùng tên đã khai;
	 *   2. Chức vụ khớp đúng một bộ phận của chi phí  -> dùng luôn (giữ nguyên nết cũ khi nó đúng);
	 *   3. MẢNG của người ấy có bản đồ sang bộ phận chi phí -> dùng bản đồ;
	 *   4. Không ra gì -> gửi CHUỖI RỖNG, và đó là điều phải nói ra ở màn hình.
	 *
	 * 🔴 BƯỚC 1 ĐỨNG TRƯỚC BƯỚC 2, KHÔNG ĐẢO ĐƯỢC. Anh Thắng 13/09/2026: *"quyết định bộ phận do
	 *    nhân sự quyết định, bên chi phí chỉ biết bộ phận đó có được quyền không thôi, chứ không
	 *    can thiệp được đổi bộ phận"*. Ô Bộ phận là ô CHÍNH THỨC của hệ nhân sự; Chức vụ là chữ
	 *    mô tả việc (Thu ngân, Ca trưởng) và vốn không phải bộ phận. Một hồ sơ khai đủ cả hai mà
	 *    tra Chức vụ trước thì cái chức vụ đè mất bộ phận thật — đúng thứ hệ nhân sự vừa quyết.
	 *
	 * 🔴 BƯỚC 3 KHÔNG PHẢI LÀ VÁ — nó vẫn là "không bó". Không có cách nào đoán hộ một bộ phận
	 *    mà không đoán sai, và bịa một bộ phận cho người ta còn tệ hơn: họ mất đường vào đúng
	 *    phần việc của mình. Cái vá thật là **nói ra ai đang không bị bó** — dải cảnh báo ở màn
	 *    Quản lý nhân sự, và bản đồ mảng -> bộ phận để anh Thắng khai một lần cho hết.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Khoá lưu bản đồ: [ mảng chấm công => bộ phận của app chi phí ]. */
	const O_BAN_DO = 'vhcc_mang_sang_chi_phi';

	/** Bản đồ mảng -> bộ phận chi phí. Rỗng = chưa khai gì. */
	public static function ban_do() {
		$v = get_option( self::O_BAN_DO, null );
		if ( ! is_array( $v ) ) { return array(); }
		$ra = array();
		foreach ( $v as $m => $b ) {
			$m = trim( (string) $m );
			$b = static::bo_phan_hop_le( $b );
			if ( '' !== $m && '' !== $b ) { $ra[ $m ] = $b; }
		}
		return $ra;
	}

	/** Khai một dòng bản đồ. Bộ phận rỗng = bỏ khai. */
	public static function dat_ban_do( $u, $mang, $bo_phan ) {
		if ( ! VHCC_Vai::duoc( $u, static::QUYEN ) ) {
			return array( 'ok' => false, 'error' => 'Khai bản đồ sang app chi phí cần vai Admin.' );
		}
		$m = trim( (string) $mang );
		if ( '' === $m ) { return array( 'ok' => false, 'error' => 'Thiếu mảng.' ); }
		$b = trim( (string) $bo_phan );
		if ( '' !== $b && '' === static::bo_phan_hop_le( $b ) ) {
			return array( 'ok' => false, 'error' => 'App chi phí không có bộ phận "' . $b . '".' );
		}
		$v = get_option( self::O_BAN_DO, array() );
		$v = is_array( $v ) ? $v : array();
		if ( '' === $b ) { unset( $v[ $m ] ); } else { $v[ $m ] = $b; }
		update_option( self::O_BAN_DO, $v );
		return array( 'ok' => true, 'mang' => $m, 'bo_phan' => $b );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * BẢN ĐỒ THỨ HAI: PHÒNG BAN (bên nhân sự) -> BỘ PHẬN (bên chi phí)
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 HAI DANH MỤC KHÔNG TRÙNG NHAU, VÀ ĐÓ MỚI LÀ LÝ DO CHÍNH KHIẾN NGƯỜI TA KHÔNG BỊ BÓ.
	 *    Sổ nhân sự khai *Phòng Kỹ Thuật · Phòng Marketing · Khối Nhân Viên Cơ Sở*; app chi phí
	 *    chỉ biết bảy tên *Cơ sở · Văn phòng · Kỹ thuật · Marketing · Công tác · Setup · Máy tự
	 *    động*. "Phòng Kỹ Thuật" và "Kỹ thuật" là HAI CHUỖI KHÁC NHAU — `bo_phan_chuan()` so chữ,
	 *    không đoán nghĩa, nên nó trả rỗng, mà rỗng bên ấy nghĩa là KHÔNG BÓ.
	 *
	 * ⚠️ Bản đồ MẢNG có sẵn không đỡ được chỗ này: mảng là *Khu Vui Chơi · Máy Tự Động*, hạt to
	 *    hơn hẳn phòng ban. Một mảng có cả Kế toán lẫn Kỹ thuật, ép chung một bộ phận chi phí là
	 *    bó sai — bó sai còn tệ hơn không bó, vì người ta mất đúng phần việc của mình mà không
	 *    có dòng nào báo.
	 *
	 * 🔴 KHÔNG TỰ ĐOÁN BẰNG CÁCH CẮT CHỮ "Phòng ". "Phòng Kho Hàng" cắt ra "Kho Hàng" vẫn không
	 *    khớp gì, còn "Phòng Kế Toán - Tài Chính" thì cắt kiểu nào cũng ra tên bên kia không có.
	 *    Đoán trúng vài ca rồi trượt phần còn lại là thứ tệ nhất: người khai tin là xong.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */

	/** Khoá lưu bản đồ: [ phòng ban nhân sự => bộ phận của app chi phí ]. */
	const O_BAN_DO_BP = 'vhcc_bophan_sang_chi_phi';

	/** Bản đồ phòng ban -> bộ phận chi phí. Rỗng = chưa khai gì. */
	public static function ban_do_bp() {
		$v = get_option( self::O_BAN_DO_BP, null );
		if ( ! is_array( $v ) ) { return array(); }
		$ra = array();
		foreach ( $v as $bp => $b ) {
			$bp = trim( (string) $bp );
			$b  = static::bo_phan_hop_le( $b );
			if ( '' !== $bp && '' !== $b ) { $ra[ $bp ] = $b; }
		}
		return $ra;
	}

	/** Khai một dòng bản đồ phòng ban. Bộ phận rỗng = bỏ khai. */
	public static function dat_ban_do_bp( $u, $bo_phan_ns, $bo_phan_cp ) {
		if ( ! VHCC_Vai::duoc( $u, static::QUYEN ) ) {
			return array( 'ok' => false, 'error' => 'Khai bản đồ sang app chi phí cần vai Admin.' );
		}
		$n = trim( (string) $bo_phan_ns );
		if ( '' === $n ) { return array( 'ok' => false, 'error' => 'Thiếu phòng ban.' ); }
		$b = trim( (string) $bo_phan_cp );
		if ( '' !== $b && '' === static::bo_phan_hop_le( $b ) ) {
			return array( 'ok' => false, 'error' => 'App chi phí không có bộ phận "' . $b . '".' );
		}
		$v = get_option( self::O_BAN_DO_BP, array() );
		$v = is_array( $v ) ? $v : array();
		if ( '' === $b ) { unset( $v[ $n ] ); } else { $v[ $n ] = $b; }
		update_option( self::O_BAN_DO_BP, $v );
		return array( 'ok' => true, 'bo_phan_ns' => $n, 'bo_phan' => $b );
	}

	/** Tên bộ phận mà app chi phí THẬT SỰ hiểu; không hiểu -> ''. */
	public static function bo_phan_hop_le( $x ) {
		$x = trim( (string) $x );
		if ( '' === $x ) { return ''; }
		/* Chưa cài app đích thì không có gì để đối chiếu — mà đã không đối chiếu được thì
		   ĐỪNG GỬI. Gửi bừa là đúng cái lỗi đang vá. `bp_chuan()` tự trả '' trong ca ấy. */
		return static::bp_chuan( $x );
	}

	/** Danh sách bộ phận app chi phí đang có. */
	public static function ds_bo_phan_chi_phi() { return static::bp_ds(); }

	/** Bộ phận sẽ gửi sang cho một hồ sơ. '' = không bó bộ phận (và phải nói ra). */
	public static function bo_phan_day( $hs ) {
		$hs = (array) $hs;
		$bp = static::bo_phan_hop_le( isset( $hs['bo_phan'] ) ? $hs['bo_phan'] : '' );
		if ( '' !== $bp ) { return $bp; }
		$bp_tho = trim( (string) ( isset( $hs['bo_phan'] ) ? $hs['bo_phan'] : '' ) );
		if ( '' !== $bp_tho ) {
			$bd_bp = static::ban_do_bp();
			if ( isset( $bd_bp[ $bp_tho ] ) ) { return $bd_bp[ $bp_tho ]; }
		}
		$cv = static::bo_phan_hop_le( isset( $hs['chuc_vu'] ) ? $hs['chuc_vu'] : '' );
		if ( '' !== $cv ) { return $cv; }
		if ( ! class_exists( 'VHCC_NhanSu' ) || ! method_exists( 'VHCC_NhanSu', 'mang_bo_phan_cua' ) ) {
			return '';
		}
		$bd = static::ban_do();
		if ( ! $bd ) { return ''; }
		$mb = VHCC_NhanSu::mang_bo_phan_cua( $hs );
		/* Người làm NHIỀU mảng: lấy mảng đầu tiên CÓ khai bản đồ. Gộp nhiều bộ phận vào một ô là
		   bên kia không hiểu, mà bỏ trống thì lại về đúng cái lỗi đang vá. */
		foreach ( (array) $mb['dsMang'] as $m ) {
			if ( isset( $bd[ $m ] ) ) { return $bd[ $m ]; }
		}
		return '';
	}

	/* ====================================================================== đẩy / gỡ */

	public static function dat( $u, $ma_nv, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, static::QUYEN ) ) {
			return array( 'ok' => false,
				'error' => 'Đẩy người sang hệ ' . static::ten_he() . ' cần vai Admin — màn ấy có ngăn tiền.' );
		}
		/* ⚠️ GÁC ĐỨNG TRƯỚC MỌI LƯỢT ĐỌC/GHI. Lời gọi chéo thật nằm trong bộ nối (`co_he()`,
		   `doc_user()`, `ghi_user()`), mỗi hàm tự gác `method_exists` trong thân nó — xem khối
		   dài ở đầu lớp. Ở đây chỉ cần hỏi một câu và dừng sớm với câu nói được cho người đọc. */
		if ( ! static::co_he() ) {
			return array( 'ok' => false,
				'error' => 'Chưa cài plugin ' . static::ten_he() . ' trên site này (hoặc bản bên ấy quá cũ).' );
		}
		$ma  = strtoupper( trim( (string) $ma_nv ) );
		$dat = (string) $dat;
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }

		$ds_day = static::da_day_ds();
		$rows   = static::doc_user();

		/* --- GỠ --- */
		if ( 'mo' !== $dat ) {
			if ( ! isset( $ds_day[ $ma ] ) ) { return array( 'ok' => true, 'doi' => 0 ); }
			$ten_cu = (string) $ds_day[ $ma ];
			$moi    = array();
			foreach ( $rows as $r ) {
				$r = (array) $r;
				if ( 0 === strcasecmp( trim( (string) ( isset( $r[ self::C_TEN ] ) ? $r[ self::C_TEN ] : '' ) ), $ten_cu ) ) {
					continue;
				}
				$moi[] = $r;
			}
			static::ghi_user( $moi );
			unset( $ds_day[ $ma ] );
			update_option( static::O_DA_DAY, $ds_day, false );
			return array( 'ok' => true, 'doi' => 1, 'viec' => 'go' );
		}

		/* --- ĐẨY --- */
		$hs = static::ho_so_day( $ma );
		if ( ! $hs ) {
			return array( 'ok' => false, 'error' => 'Người mang mã ' . $ma . ' chưa có PIN chấm công '
				. '(4–8 số) — cấp PIN cho họ ở màn Hồ sơ & tài khoản rồi đẩy lại.' );
		}
		/* =====================================================================================
		 * 🔴 PIN TRÙNG NGƯỜI KHÁC: SỔ NHÂN SỰ THẮNG, HÀNG BÊN KIA BỊ XOÁ PIN.
		 * =====================================================================================
		 * Anh Thắng 28/08/2026: *"Sau khi đẩy qua sẽ lấy PIN bên nhân sự luôn"* — và khi được
		 * hỏi phải làm gì với hàng bị trùng, anh chốt: xoá PIN của hàng bên kia.
		 *
		 * Vì sao không để hai hàng cùng PIN: cổng đăng nhập bên ấy tra THEO PIN, nên ai gõ vào
		 * cũng rơi vào hàng đứng trước — một trong hai người mất tài khoản mà không có gì báo.
		 * Bản trước chối cả lượt đẩy; anh phải tự đi tìm và sửa từng ca.
		 *
		 * ⚠️ XOÁ PIN, KHÔNG XOÁ HÀNG. Hàng ấy có thể mang TK Có, Mã đối tượng, Đơn vị do kế
		 *    toán khai — xoá hàng là mất bảng khai ấy. Xoá PIN thì người đó tạm thời không đăng
		 *    nhập được, còn mọi thứ khác nguyên vẹn; cấp PIN mới là dùng lại được ngay.
		 *
		 * ⚠️ PHẢI KỂ TÊN NGƯỜI BỊ XOÁ PIN RA. Im lặng là sáng hôm sau có người gõ PIN không vào
		 *    được và không ai biết vì sao — đúng kiểu hỏng mà cả khối này sinh ra để tránh.
		 */
		$ten_cu   = isset( $ds_day[ $ma ] ) ? (string) $ds_day[ $ma ] : $hs['ho_ten'];
		$mat_pin  = array();
		foreach ( $rows as $i_r => $r ) {
			$r = (array) $r;
			$t = trim( (string) ( isset( $r[ self::C_TEN ] ) ? $r[ self::C_TEN ] : '' ) );
			$p = trim( (string) ( isset( $r[ self::C_PIN ] ) ? $r[ self::C_PIN ] : '' ) );
			if ( $p !== $hs['pin'] ) { continue; }
			if ( 0 === strcasecmp( $t, $ten_cu ) || 0 === strcasecmp( $t, $hs['ho_ten'] ) ) { continue; }
			$r[ self::C_PIN ] = '';
			$rows[ $i_r ]     = $r;
			$mat_pin[]        = $t;
		}

		$thay = false;
		foreach ( $rows as $i => $r ) {
			$r = (array) $r;
			$t = trim( (string) ( isset( $r[ self::C_TEN ] ) ? $r[ self::C_TEN ] : '' ) );
			if ( 0 !== strcasecmp( $t, $ten_cu ) && 0 !== strcasecmp( $t, $hs['ho_ten'] ) ) { continue; }
			/* 🔴 SỬA ĐÚNG BA Ô, GIỮ NGUYÊN PHẦN CÒN LẠI. TK Có · Mã đối tượng · Đơn vị · Xem
			   đơn vị — VÀ CƠ SỞ PHỤ TRÁCH — là bảng khai của KẾ TOÁN; sổ nhân sự không biết và
			   không được đoán. */
			$r[ self::C_TEN ]     = $hs['ho_ten'];
			$r[ self::C_PIN ]     = $hs['pin'];
			$r[ self::C_VAI ]     = static::vai_chi_phi( $hs['vai_cc'] );
			/* 🔴 KHÔNG GHI Ô CƠ SỞ — xem chốt dài ở `C_COSO`. Dòng `$r[ C_COSO ] = $hs['coso']`
			   ở đây chính là thứ đè mất phân công của kế toán mỗi lượt đẩy. */
			if ( '' !== $hs['bo_phan'] ) { $r[ self::C_BO_PHAN ] = $hs['bo_phan']; }
			/* ⚠️ MÃ NV GHI ĐÈ LUÔN, không gác "chỉ ghi khi rỗng" như ô Bộ phận. Mã là danh
			   tính, không phải lựa chọn của kế toán: hàng này vừa được nhận ra là của người
			   mang mã ấy, nên mã ấy đúng theo định nghĩa. */
			$r[ self::C_MA_NV ] = $hs['ma_nv'];
			$rows[ $i ] = $r;
			$thay = true;
			break;
		}
		if ( ! $thay ) {
			/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG: hạ `self::SO_O` xuống 9 KHÔNG đổi kết quả — phép gán
			   `$hang[9]` ngay dưới tự nới mảng, và khoá vẫn liên tục 0..9 nên `array_values`
			   giữ nguyên thứ tự. Vẫn khai đúng số ô vì nó nói ra ý định. */
			$hang = array_fill( 0, self::SO_O, '' );
			$hang[ self::C_TEN ]     = $hs['ho_ten'];
			$hang[ self::C_PIN ]     = $hs['pin'];
			$hang[ self::C_VAI ]     = static::vai_chi_phi( $hs['vai_cc'] );
			/* Ô cơ sở để TRỐNG, không nhét mã cửa hàng vào — xem chốt ở `C_COSO`. Trống là an
			   toàn: `VHCP_Auth::nv_co_coso()` đòi CÓ cơ sở mới mở tầm nhìn theo gian, nên người
			   vừa đẩy sang chỉ thấy đơn của chính mình cho tới khi kế toán phân. Nhét một mã
			   không khớp gian nào thì họ không thấy GÌ, kể cả đơn mình vừa lập. */
			$hang[ self::C_BO_PHAN ] = $hs['bo_phan'];
			$hang[ self::C_MA_NV ]   = $hs['ma_nv'];
			$rows[] = $hang;
		}
		static::ghi_user( array_values( $rows ) );
		$ds_day[ $ma ] = $hs['ho_ten'];
		update_option( static::O_DA_DAY, $ds_day, false );
		return array( 'ok' => true, 'doi' => 1, 'viec' => $thay ? 'capnhat' : 'them',
			'mat_pin' => $mat_pin );
	}

	/**
	 * BẢN SAO BÊN CHI PHÍ PHẢI THEO BẢN GỐC — gọi sau mỗi lần sửa hồ sơ / đổi vai / chuyển cơ sở.
	 *
	 * 🔴 CỐ Ý KHÔNG KIỂM QUYỀN ĐẨY, y như `VHCC_DayGhe::dong_bo()`. Hàm này không MỞ đường cho
	 *    ai: nó chỉ giữ cho bản sao của một người ĐÃ ĐƯỢC ĐẨY khớp với bản gốc. Bắt nó đòi vai
	 *    Admin thì Cửa hàng trưởng đổi PIN cho nhân viên mình xong, bản sao đứng im — cái chốt
	 *    ấy không bảo vệ được gì mà chỉ đẻ ra lệch.
	 *
	 * ⚠️ HỒ SƠ MẤT PIN THÌ GỠ LUÔN. Xoá PIN của một người thường là để chặn họ đăng nhập; giữ
	 *    bản sao mang PIN cũ là để hở đúng cánh cửa vừa định đóng.
	 */
	/**
	 * Đẩy / gỡ một loạt theo bảng vừa gửi lên: [ maNV => 'mo' | '' ].
	 *
	 * ⚠️ BỎ QUA NGƯỜI KHÔNG ĐỔI. Bảng gửi lên có cả trăm hàng mà thường chỉ vài hàng đổi; ghi
	 *    lại cả sổ chi phí cho mỗi hàng là hàng trăm lượt đọc-ghi cho một cú bấm.
	 */
	public static function luu_nhieu( $u, $bang ) {
		if ( ! VHCC_Vai::duoc( $u, static::QUYEN ) ) {
			return array( 'ok' => false, 'doi' => 0,
				'error' => 'Đẩy người sang hệ ' . static::ten_he() . ' cần vai Admin.' );
		}
		$doi     = 0;
		$loi     = array();
		$mat_pin = array();
		foreach ( (array) $bang as $ma => $dat ) {
			$ma  = trim( (string) $ma );
			$dat = (string) $dat;
			if ( '' === $ma ) { continue; }
			if ( static::o( $ma ) === ( 'mo' === $dat ? 'mo' : '' ) ) { continue; }
			$kq = static::dat( $u, $ma, $dat );
			if ( empty( $kq['ok'] ) ) { $loi[] = $kq['error']; continue; }
			$doi += (int) ( isset( $kq['doi'] ) ? $kq['doi'] : 0 );
			foreach ( (array) ( isset( $kq['mat_pin'] ) ? $kq['mat_pin'] : array() ) as $t_mp ) {
				$mat_pin[ $t_mp ] = true;
			}
		}
		return array( 'ok' => true, 'doi' => $doi, 'loi' => $loi,
			'mat_pin' => array_keys( $mat_pin ) );
	}

	public static function dong_bo( $ma_nv ) {
		$ma = strtoupper( trim( (string) $ma_nv ) );
		if ( '' === $ma || ! static::da_day( $ma ) || ! static::co_he_chi_phi() ) {
			return array( 'ok' => true, 'doi' => 0 );
		}
		$gia_admin = array( 'name' => 'dong_bo', 'role' => 'Admin' );
		$hs = static::ho_so_day( $ma );
		return static::dat( $gia_admin, $ma, $hs ? 'mo' : '' );
	}
}
