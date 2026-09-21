<?php
/**
 * ĐĂNG NHẬP — PIN 4 số như app cũ, thêm 2 thứ Apps Script không có:
 *   1) phiên có token: mọi lệnh gọi API (trừ `login`) phải kèm token còn hạn;
 *   2) hãm thử PIN: quá 10 lần sai trong 10 phút từ 1 IP thì chặn tạm.
 *
 * PIN vẫn lưu nguyên văn trong bảng cấu hình vì tab "⚙️ Cấu hình" của giao diện
 * hiện & sửa PIN từng người (giữ đúng cách vận hành cũ). Xem phần "Bảo mật" trong
 * docs/HUONG-DAN-CAI-DAT-WORDPRESS.md nếu muốn siết thêm.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPMTD_Auth {

	const TTL = 2592000;   // 30 ngày — giao diện nhớ phiên trong localStorage (token, KHÔNG phải PIN)
	/* Còn dưới 7 ngày thì gia hạn về đủ 30 — xem `ai_dang_dang_nhap()`. */
	const TTL_GIA_HAN = 604800;

	/**
	 * Vai trò của người đang gọi trong lượt request này.
	 *
	 * Bảng hàm gọi thẳng vào VHCPMTD_Cfg::save_config() nên bên đó không biết ai đang lưu.
	 * Cần biết để chặn người không phải Admin sửa tài khoản Admin — chặn ở MÁY CHỦ, chứ
	 * khoá ô nhập trên giao diện chỉ là lớp sơn.
	 */
	private static $vai_tro = '';   // VAI GỐC — mọi phép kiểm quyền dùng cái này
	private static $vai_hien = '';  // tên vai NHƯ NGƯỜI TA KHAI (có thể là vai tự tạo)
	private static $nguoi   = '';
	/**
	 * CƠ SỞ NGƯỜI ĐANG GỌI PHỤ TRÁCH — chuỗi thô như đã khai, ví dụ "FARM PHAN THIẾT, ADV GO".
	 *
	 * 🔴 MỘT NGƯỜI PHỤ TRÁCH NHIỀU CƠ SỞ. Ô khai ở màn Cấu hình là hộp tích nhiều lựa chọn, lưu
	 *    xuống thành một chuỗi ngăn bằng dấu phẩy — nên chỗ nào đọc nó cũng phải TÁCH RA, đừng
	 *    so bằng `===`. So nguyên chuỗi là người khai ba cơ sở thì không khớp cơ sở nào cả.
	 */
	private static $coso    = '';
	/* PHÒNG BAN của người đang gọi — đọc từ chính TÀI KHOẢN, không phải từ vai. Xem khối dài
	   ở `bo_phan_bo()` để biết vì sao đổi. */
	private static $bo_phan = '';
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * MÃ NV — KHOÁ THỨ HAI, chỉ để PHÂN BIỆT NGƯỜI TRÙNG TÊN. Không phải khoá nối dữ liệu.
	 *
	 * Anh Thắng 13/09/2026: *"nếu đẩy từ nhân sự sang, mà nhân viên này trùng với nhân viên
	 * tạo trực tiếp trên trang chi phí thì sao, làm sao để gộp lại"*.
	 *
	 * 🔴 ĐỌC KỸ TRƯỚC KHI ĐỊNH DÙNG NÓ THAY TÊN. Mọi đơn đã lập mang `nguoi_lap` là TÊN, mọi
	 *    dòng sổ chi mang `nguoi_nhap` là TÊN — hàng chục nghìn dòng, không dòng nào có mã NV.
	 *    Nên mã NV KHÔNG tra ngược ra được đơn cũ, và đổi khoá nối sang nó là mọi đơn cũ mồ
	 *    côi cùng lúc. Việc của nó chỉ có hai:
	 *      · bảng phân quyền bày ra để người ta biết "Nguyễn Văn A" nào trong hai người;
	 *      · `login()` dùng nó để nối một hàng bên Nhân sự vào đúng dòng bên Chi phí.
	 *    Ngoài hai việc đó, TÊN vẫn là khoá.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	private static $ma_nv = '';
	/**
	 * 🔴 QUY VỀ VAI GỐC NGAY TẠI ĐÂY, một chỗ duy nhất.
	 *
	 * Vai tự tạo ("Nhân viên văn phòng") kế thừa quyền của một vai gốc ("Nhân viên"). Nếu để
	 * mỗi nơi tự quy đổi thì chỉ cần MỘT nơi quên là thủng: `la_nhan_vien()` so với chuỗi
	 * 'Nhân viên' sẽ trả false cho vai tự tạo, và người đó thấy đơn của cả công ty.
	 *
	 * Quy ở cửa vào nên mọi chỗ phía sau không cần biết vai tự tạo là gì.
	 */
	public static function dat_vai_tro( $r, $ten = '', $coso = '', $bo_phan = '', $ma_nv = '' ) {
		self::$vai_hien = (string) $r;
		self::$vai_tro  = class_exists( 'VHCPMTD_Cfg' ) ? VHCPMTD_Cfg::vai_goc( (string) $r ) : (string) $r;
		self::$nguoi    = (string) $ten;
		self::$coso     = (string) $coso;
		self::$bo_phan  = (string) $bo_phan;
		self::$ma_nv    = (string) $ma_nv;
	}
	/** Mã NV của người đang gọi — '' nếu tài khoản chưa khai. Xem khối dài ở `$ma_nv`. */
	public static function ma_nv() { return trim( (string) self::$ma_nv ); }
	/**
	 * BỘ PHẬN mà người đang gọi bị bó vào — '' = không bó (thấy mọi bộ phận).
	 *
	 * Anh Thắng 08/09/2026: *"thêm vai trò kế toán máy tự động (để chỉ thực hiện công việc bên
	 * bộ phận máy tự động)"*. Bó gắn với VAI, không với ô "Bộ phận" trên tài khoản — xem chốt
	 * dài ở `VHCPMTD_Cfg::bo_phan_cua_nguoi()`.
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * PHÒNG BAN BÓ NGƯỜI ĐANG GỌI — ĐỌC TỪ TÀI KHOẢN, KHÔNG TỪ VAI.
	 *
	 * Anh Thắng 13/09/2026: *"anh sẽ tạo ban bệ phòng ban sẵn, ai thuộc bộ phận nào thì thêm
	 * vào, tránh sai vai hay tự tạo vai lạ"*.
	 *
	 * 🔴 CÙNG MỘT THÔNG TIN TỪNG KHAI BA NƠI. Vai "Nhân viên kỹ thuật" mang chữ "kỹ thuật"
	 *    trong TÊN VAI, lại khai lần nữa ở cột "Chỉ làm bộ phận" của bảng Vai trò, rồi khai
	 *    lần thứ ba ở cột "Bộ phận" của từng tài khoản. Ba nơi thì sớm muộn lệch, và lệch ở
	 *    đây nghĩa là người ta thấy hoặc không thấy chi phí của mảng khác mà chẳng ai giải
	 *    thích nổi. Nay chỉ còn MỘT nơi: ô Bộ phận của tài khoản.
	 *
	 * 🔴 ADMIN VÀ GIÁM ĐỐC KHÔNG BỊ BÓ, dù ô Bộ phận của họ có khai gì. Anh Thắng: *"Giám đốc:
	 *    Toàn Quyền Xem · Admin: Toàn Quyền"*. Không thoát ở đây là một ô khai nhầm trên tài
	 *    khoản giám đốc cắt mất tầm nhìn toàn cục — đúng thứ vai ấy sinh ra để có.
	 *
	 * ⚠️ Ô ĐỂ TRỐNG = KHÔNG BÓ, giữ nguyên hành vi cũ. Phần lớn tài khoản đang bỏ trống ô này;
	 *    hiểu ngược lại là ngày bản này lên, gần như cả công ty mở màn ra thấy trắng.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	public static function bo_phan_bo() {
		if ( 'Admin' === self::$vai_tro || 'Giám đốc' === self::$vai_tro ) { return ''; }
		return trim( (string) self::$bo_phan );
	}

	/* ⚠️ ĐÃ BỎ `xem_duoc_bo_phan( $bp )` — so thẳng tên bộ phận với bộ phận đang bó. Viết ra
	   "cho chắc ăn" rồi KHÔNG chỗ nào gọi: phá thử chỉ nó ra ngay, vì đục cho nó luôn trả
	   `true` mà không phép nào đỏ. Nhánh không ai đi tới thì không ai biết nó còn đúng, và
	   nó cũng không bảo vệ được gì. Mọi chỗ cần hỏi đều đi qua `xem_duoc_loai()` dưới đây —
	   dòng tiền mang TÊN LOẠI chứ không mang tên bộ phận. */

	/**
	 * Người đang gọi có được đọc một dòng chi mang LOẠI CHI PHÍ này không.
	 *
	 * 🔴 LOẠI CHƯA KHAI BỘ PHẬN THÌ CHO QUA. Danh mục loại chi phí của anh Thắng dựng từ sổ cũ,
	 *    rất nhiều dòng còn bỏ trống ô Bộ phận. Chặn chúng lại là ngày bản này lên, kế toán bó
	 *    bộ phận mở màn ra thấy gần như trắng — và họ sẽ kết luận là mất dữ liệu chứ không
	 *    đoán ra là do một ô chưa khai ở màn Cấu hình.
	 */
	public static function xem_duoc_loai( $ten_loai ) {
		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 LỌC THEO VAI TRÒ, KHÔNG CÒN THEO BỘ PHẬN.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 21/09/2026: *"bỏ tích bộ phận đi, mà tích theo vai trò"*, sau khi cột Bộ
		 * phận đã rời khỏi bảng Người dùng. Trước bản này hàm đọc `bo_phan_bo()` — mà ô ấy nay
		 * không ai sửa được nữa, nên nó sẽ lọc bằng một giá trị cũ không ai thấy và không ai
		 * đổi được. Đó là kiểu hỏng tệ nhất: có thật, im lặng, và không có đường vào để sửa.
		 *
		 * ⚠️ SO BẰNG VAI ĐANG MANG, KHÔNG PHẢI VAI GỐC. Anh Thắng khai vai con rất cụ thể
		 *    ("Kế Toán Máy Tự Động", "Nhân Viên Kỹ Thuật Khu Vui Chơi") — chính mấy cái tên ấy
		 *    là thứ được tích ở bảng Loại chi phí. Quy về vai gốc là mọi vai con cùng nhánh
		 *    hoá một, và cả nhánh nhìn thấy sổ của nhau.
		 *
		 * 🔴 ADMIN KHÔNG BAO GIỜ BỊ LỌC — giữ đúng luật cũ của `bo_phan_bo()`.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$vai = trim( (string) self::$vai_hien );
		if ( '' === $vai ) { $vai = trim( (string) self::$vai_tro ); }
		if ( '' === $vai || 'Admin' === $vai || 'Giám đốc' === $vai ) { return true; }
		if ( ! class_exists( 'VHCPMTD_Cfg' ) ) { return true; }
		return VHCPMTD_Cfg::loai_thuoc_vai( $ten_loai, $vai );
	}

	public static function vai_tro() { return self::$vai_tro; }
	public static function vai_hien() { return self::$vai_hien; }
	public static function nguoi() { return self::$nguoi; }

	/**
	 * CÁC CƠ SỞ NGƯỜI ĐANG GỌI PHỤ TRÁCH, đã tách sẵn thành mảng.
	 *
	 * Anh Thắng 30/08/2026: *"Nhân viên được cấu hình 3 cơ sở, nhưng đơn chỉ hiện 1 cơ sở"*.
	 *
	 * 🔴 HÀM THUẦN, MỘT CHỖ TÁCH DUY NHẤT. Trước đây chuỗi này chỉ được nhét vào thẻ phiên rồi
	 *    thôi — không chỗ nào ở máy chủ đọc tới, nên khai ba cơ sở hay ba mươi cũng như nhau.
	 *    Nay có chỗ đọc thì phải tách ở ĐÚNG MỘT NƠI: mỗi nơi tự `explode` lấy là sớm muộn một
	 *    nơi quên `trim`, và " ADV GO" (thừa một dấu cách) không khớp "ADV GO".
	 *
	 * @return array danh sách tên cơ sở; rỗng nghĩa là KHÔNG khai cơ sở nào.
	 */
	public static function coso_ds() {
		$ra = array();
		foreach ( explode( ',', (string) self::$coso ) as $x ) {
			$x = trim( $x );
			if ( '' !== $x ) { $ra[] = self::doi_ma_sang_ten( $x ); }
		}
		return $ra;
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * Ô CƠ SỞ NHẬN CẢ MÃ ĐƠN VỊ VÀ TÊN THEO MISA, KHÔNG CHỈ TÊN THƯỜNG GỌI.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026, tài khoản "Ung Nguyễn Thùy Dương · Nhân viên · TUTU_BD": *"vẫn không
	 * xem được đơn cũ của bạn NV cùng cơ sở làm trước"*. Ô Cơ sở khai `TUTU_BD` — đó là MÃ ĐƠN VỊ
	 * của cơ sở, còn đơn thì ghi TÊN THƯỜNG GỌI. Chốt phạm vi so tên với tên nên trượt sạch.
	 *
	 * 🔴 ĐÂY LÀ TRA SỔ, KHÔNG PHẢI NỚI TAY. Khác nhau ở chỗ: so khớp mờ (khớp một phần, bỏ dấu)
	 *    thì "Tân An" trúng luôn cả "VR Tân An" lẫn "TuTu Tân An" — mở sổ tiền của gian khác cho
	 *    người không phụ trách. Còn ở đây: mã phải CÓ THẬT trong danh mục cơ sở, và mỗi mã chỉ
	 *    dẫn tới ĐÚNG MỘT tên. Không có mã ấy thì trả nguyên chuỗi, chốt vẫn chối như cũ.
	 *
	 * ⚠️ MÃ TRÙNG NHAU THÌ KHÔNG DỊCH. Hai cơ sở lỡ khai chung một mã đơn vị thì dịch sang cái
	 *    nào cũng là đoán — mà đoán ở chốt phân quyền là mở nhầm cửa. Thà chối, rồi người khai
	 *    thấy màn trống và sửa lại sổ.
	 *
	 * ⚠️ Danh mục CHƯA nạp được (bảng rỗng, bản mới cài) thì trả nguyên chuỗi — không được biến
	 *    một ô đang khai đúng thành rỗng.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	public static function doi_ma_sang_ten( $x ) {
		$k = mb_strtolower( trim( (string) $x ) );
		if ( '' === $k ) { return (string) $x; }

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * SỔ MÃ GỌI TẮT — tra TRƯỚC danh mục, vì đây là chỗ người ta khai TAY cho đúng ca lệch.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 14/09/2026: ô Cơ sở khai `TUTU_BD`, còn đơn ghi `TÀU BÌNH DƯƠNG` — *"rất
		 * nhiều đơn chi tutu bd"*. Hai chuỗi ấy là CÙNG MỘT GIAN, nhưng không quy tắc chuỗi nào
		 * suy ra được (`TÀU` không phải `TUTU`, `BD` không nằm trong tên). Máy phải được một
		 * người nói cho biết, đúng một lần — và đó là sổ này.
		 *
		 * 🔴 TRA SỔ NÀY TRƯỚC `so_coso()`. Danh mục cơ sở ở nhiều bản còn rỗng (*"làm gì có danh
		 *    mục cơ sở"*), nên nếu để sau thì `if ( ! $ds ) return` ở dưới thoát sớm và sổ này
		 *    không bao giờ được hỏi tới — đúng cái bẫy khiến bản 1.175.0 không cứu được ca này.
		 *
		 * ⚠️ Sổ khai trong Cấu hình chi phí, là CẤU HÌNH NỘI BỘ của trang này — không đụng danh
		 *    mục nào bên ngoài, đúng như anh chốt.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$tat = self::so_ma_tat();
		if ( isset( $tat[ $k ] ) ) { return $tat[ $k ]; }

		$ds = self::so_coso();
		if ( ! $ds ) { return (string) $x; }
		/* Trùng đúng TÊN thường gọi thì thôi, khỏi tra gì thêm. */
		foreach ( $ds as $c ) {
			if ( mb_strtolower( $c['ten'] ) === $k ) { return $c['ten']; }
		}
		$trung = array();
		foreach ( $ds as $c ) {
			$ma = mb_strtolower( trim( (string) $c['ma'] ) );
			$tm = mb_strtolower( trim( (string) $c['tenMisa'] ) );
			if ( ( '' !== $ma && $ma === $k ) || ( '' !== $tm && $tm === $k ) ) {
				if ( ! in_array( $c['ten'], $trung, true ) ) { $trung[] = $c['ten']; }
			}
		}
		return ( 1 === count( $trung ) ) ? $trung[0] : (string) $x;
	}

	/* Dịch CẢ CHUỖI "A, TUTU_BD" sang tên thường gọi — dùng cho thứ gửi xuống MÀN.
	   🔴 MÀN PHẢI NHẬN GIÁ TRỊ ĐÃ DỊCH, không tự dịch lại: nó lọc danh sách đơn trước khi hỏi
	      máy chủ (`_trongCoSoToi`), nên hai bên dịch riêng là sớm muộn hai bên lệch — và lệch ở
	      đây nghĩa là màn giấu mất đơn mà máy chủ vẫn cho xem, hoặc ngược lại. */
	public static function coso_hien( $chuoi ) {
		$ra = array();
		foreach ( explode( ',', (string) $chuoi ) as $x ) {
			$x = trim( $x );
			if ( '' !== $x ) { $ra[] = self::doi_ma_sang_ten( $x ); }
		}
		return implode( ', ', $ra );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * SỔ MÃ GỌI TẮT CỦA CƠ SỞ — "TUTU_BD = TÀU BÌNH DƯƠNG".
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Mỗi dòng một cặp `mã|tên cơ sở`, cất trong bảng meta của chính trang chi phí.
	 *
	 * ⚠️ KHOÁ LÀ MÃ ĐÃ HẠ CHỮ THƯỜNG, để `TUTU_BD`, `tutu_bd`, ` Tutu_Bd ` cùng trúng một dòng —
	 *    ô Cơ sở là chỗ gõ tay, và người gõ tay thì gõ mỗi lúc một kiểu.
	 *
	 * ⚠️ MỘT MÃ CHỈ DẪN TỚI MỘT TÊN. Khai hai dòng cùng mã thì dòng sau đè dòng trước — thà đè
	 *    hẳn còn hơn giữ cả hai rồi phải đoán, mà đoán ở chốt phân quyền là mở nhầm cửa.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const O_MA_TAT = 'ma_tat_coso';
	private static $so_tat = null;
	public static function so_ma_tat() {
		if ( null !== self::$so_tat ) { return self::$so_tat; }
		self::$so_tat = array();
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! class_exists( 'VHCPMTD_Meta' ) || ! method_exists( 'VHCPMTD_Meta', 'get' ) ) { return self::$so_tat; }
		$raw = (string) call_user_func( array( 'VHCPMTD_Meta', 'get' ), self::O_MA_TAT, '' );
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $dong ) {
			$dong = trim( (string) $dong );
			if ( '' === $dong || false === mb_strpos( $dong, '|' ) ) { continue; }
			list( $ma, $ten ) = array_pad( explode( '|', $dong, 2 ), 2, '' );
			$ma  = mb_strtolower( trim( $ma ) );
			$ten = trim( $ten );
			if ( '' === $ma || '' === $ten ) { continue; }
			self::$so_tat[ $ma ] = $ten;
		}
		return self::$so_tat;
	}

	/** Ghi lại sổ mã gọi tắt. Nhận nguyên khối chữ, mỗi dòng `mã|tên cơ sở`. */
	public static function dat_ma_tat( $raw ) {
		$sach = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $dong ) {
			$dong = trim( (string) $dong );
			if ( '' === $dong || false === mb_strpos( $dong, '|' ) ) { continue; }
			list( $ma, $ten ) = array_pad( explode( '|', $dong, 2 ), 2, '' );
			$ma  = trim( $ma );
			$ten = trim( $ten );
			if ( '' === $ma || '' === $ten ) { continue; }
			$sach[] = $ma . '|' . $ten;
		}
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( class_exists( 'VHCPMTD_Meta' ) && method_exists( 'VHCPMTD_Meta', 'set' ) ) {
			call_user_func( array( 'VHCPMTD_Meta', 'set' ), self::O_MA_TAT, implode( "\n", $sach ) );
		}
		self::$so_tat = null;   // đọc lại ở lượt sau
		return count( $sach );
	}

	/* Hai cổng cho màn Cấu hình. Trả nguyên khối chữ đúng như người ta đã gõ, để sửa tiếp được. */
	public static function doc_ma_tat_api() {
		$ds = array();
		foreach ( self::so_ma_tat() as $ma => $ten ) { $ds[] = $ma . '|' . $ten; }
		return array( 'success' => true, 'raw' => implode( "\n", $ds ), 'so' => count( $ds ) );
	}
	public static function luu_ma_tat_api( $raw ) {
		$n = self::dat_ma_tat( $raw );
		return array( 'success' => true, 'so' => $n, 'message' => 'Đã lưu ' . $n . ' mã gọi tắt.' );
	}

	/** Danh mục cơ sở rút gọn: tên · mã đơn vị · tên theo MISA. Đọc một lần mỗi lượt gọi. */
	private static $so_cs = null;
	public static function so_coso() {
		if ( null !== self::$so_cs ) { return self::$so_cs; }
		self::$so_cs = array();
		/* ⚠️ ĐỌC `cfg_static()`, KHÔNG ĐỌC `get_config()`. Hàm này chạy trên MỌI lượt lọc đơn,
		   mà `get_config()` kéo cả bảng chi phí về chỉ để dựng danh sách đối tượng —
		   `cfg_static()` có nhớ tạm và chỉ đọc mấy bảng danh mục.
		   ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( class_exists( 'VHCPMTD_Cfg' ) && method_exists( 'VHCPMTD_Cfg', 'cfg_static' ) ) {
			$cfg = (array) call_user_func( array( 'VHCPMTD_Cfg', 'cfg_static' ) );
			foreach ( (array) ( isset( $cfg['coso'] ) ? $cfg['coso'] : array() ) as $c ) {
				$c = (array) $c;
				$ten = trim( (string) ( isset( $c['ten'] ) ? $c['ten'] : '' ) );
				if ( '' === $ten ) { continue; }
				self::$so_cs[] = array(
					'ten'     => $ten,
					'ma'      => isset( $c['maDonVi'] ) ? (string) $c['maDonVi'] : '',
					'tenMisa' => isset( $c['tenMisa'] ) ? (string) $c['tenMisa'] : '',
				);
			}
		}
		return self::$so_cs;
	}

	/**
	 * Tên cơ sở này có nằm trong phạm vi người đang gọi phụ trách không.
	 *
	 * ⚠️ So KHÔNG PHÂN BIỆT HOA THƯỜNG và bỏ khoảng trắng thừa. Tên cơ sở do người gõ tay ở
	 *    nhiều màn khác nhau, "Funzone Vũng Tàu" và "FUNZONE VŨNG TÀU" là một chỗ.
	 */
	public static function trong_coso( $ten ) {
		$ten = mb_strtolower( trim( (string) $ten ) );
		if ( '' === $ten ) { return false; }
		foreach ( self::coso_ds() as $c ) {
			if ( mb_strtolower( $c ) === $ten ) { return true; }
		}
		return false;
	}

	/* Nhân viên ĐÃ ĐƯỢC PHÂN CƠ SỞ — anh Thắng 14/09/2026: *"giống cơ chế admin thôi, admin thì
	   full, nv thì cấp quyền cơ sở nào thì nhìn thấy cơ sở đó thôi, mọi đơn"*.
	   🔴 "MỌI ĐƠN" nghĩa là không chốt nào khác cắt thêm trong cơ sở ấy — kể cả chốt BỘ PHẬN,
	      vốn sinh ra cho vai kế toán chuyên mảng ("Kế toán máy tự động chỉ làm mảng MTD"), chứ
	      không phải cho người đứng cửa hàng. Cửa hàng nhập đủ thứ chi phí; bó họ theo bộ phận là
	      chính đơn đồng nghiệp cùng quầy cũng biến mất khỏi màn của họ. */
	public static function nv_co_coso() {
		return self::la_nhan_vien() && (bool) self::coso_ds();
	}

	/** Người đang gọi là NHÂN VIÊN (chỉ được thấy / sửa đơn của chính mình)? */
	public static function la_nhan_vien() {
		return ( self::$vai_tro === 'Nhân viên' && trim( self::$nguoi ) !== '' );
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * MỘT BẢN GHI CÓ NẰM TRONG TẦM NHÌN CỦA NGƯỜI ĐANG GỌI KHÔNG.
	 *
	 * Anh Thắng 12/09/2026 vạch cơ cấu:
	 *   · *"Cửa hàng trưởng trở xuống — chỉ xem được cơ sở mình quản lý"*
	 *   · *"Quản Lý — chỉ xem, nhập dữ liệu được bộ phận cơ sở mình quản lý"*
	 *
	 * 🔴 HAI VAI, HAI CÁCH HIỂU Ô CƠ SỞ RỖNG. Đây là chỗ dễ làm sai nhất, nên nói thẳng:
	 *      · NHÂN VIÊN bỏ trống ô Cơ sở  -> BÓ CHẶT, chỉ còn đơn do chính họ lập. Hiểu ngược
	 *        ("rỗng = tất cả") là người quên khai đọc được sổ cả công ty mà không ai nhận ra.
	 *      · QUẢN LÝ bỏ trống ô Cơ sở    -> MỞ, cả nhà. Vì với vai này "Tất cả cơ sở" là một
	 *        lựa chọn CÓ CHỦ Ý và đang dùng thật (chị Phương Hòa khai đúng như vậy) — bó lại
	 *        là sáng hôm sau quản lý mở màn ra thấy trắng.
	 *    Cùng một ô trống, hai nghĩa ngược nhau, vì hai vai khai nó theo hai ý khác nhau.
	 *
	 * 🔴 CHỈ TRẢ LỜI "CÓ ĐƯỢC ĐỌC KHÔNG". Chốt đơn vị nằm chỗ khác (`VHCPMTD_DonVi::duoc_xem`),
	 *    chốt bộ phận ở `xem_duoc_loai()`, chốt sửa ở `loi_khong_phai_don_minh()`. Gộp cả vào
	 *    đây là một hàm trả lời bốn câu, và sửa một câu thì ba câu kia lệch theo.
	 *
	 * ⚠️ VẪN GIỮ VẾ "ĐƠN CỦA MÌNH" cho cả hai vai. Bỏ đi là người lập đơn cho một cơ sở vừa
	 *    bị gỡ khỏi danh sách phụ trách mất luôn chính cái đơn mình đang làm dở.
	 *
	 * 🔴 DÙNG CHO CẢ ĐƠN LẪN DÒNG SỔ CHI PHÍ. Hai sổ, một câu hỏi — nên một hàm. Chép luật ra
	 *    hai nơi là sớm muộn hai nơi lệch, và lệch ở đây nghĩa là một màn lọc còn màn kia thì
	 *    không (đúng chuyện đã xảy ra với sổ chi phí, soi ra 12/09/2026).
	 *
	 * @param string $nguoi_tao Tên người lập đơn — hoặc người nhập dòng, với sổ chi phí.
	 * @param string $coso      Chuỗi cơ sở — có thể là "A, B" gom từ nhiều dòng chi.
	 *                          Chỉ cần MỘT cơ sở nằm trong tầm là đọc được.
	 * @return bool
	 */
	public static function trong_tam( $nguoi_tao, $coso ) {
		$la_nv = self::la_nhan_vien();
		$la_ql = ( self::$vai_tro === 'Quản lý' );
		/* 🔴 GIÁM ĐỐC THOÁT SỚM, cùng chỗ với Admin và Kế toán. Anh Thắng 13/09/2026: *"Giám
		   đốc: Toàn Quyền Xem"*. Vai này mới nên chưa lọt vào chốt nào — nhưng để nó rơi
		   xuống nhánh dưới thì một ô Cơ sở khai nhầm là cắt mất tầm nhìn toàn cục. */
		if ( ! $la_nv && ! $la_ql ) { return true; }   // Admin · Giám đốc · Kế toán: không bó theo cơ sở

		$ds = self::coso_ds();
		/* Quản lý chưa khai cơ sở nào = trông cả nhà. Nhân viên thì KHÔNG được nới như vậy. */
		if ( $la_ql && ! $ds ) { return true; }

		$toi = mb_strtolower( trim( (string) self::nguoi() ) );
		if ( '' !== $toi && mb_strtolower( trim( (string) $nguoi_tao ) ) === $toi ) { return true; }
		foreach ( explode( ',', (string) $coso ) as $cs ) {
			if ( self::trong_coso( $cs ) ) { return true; }
		}
		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 CÙNG Ô CƠ SỞ TRONG CẤU HÌNH = CÙNG CHỖ LÀM, DÙ ĐƠN GHI CHỮ GÌ.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 14/09/2026: *"2 nhân viên cùng cơ sở thì làm việc như nhau, nhìn thấy nội
		 * dung như nhau, chức năng quyền hạn như nhau"*, *"có quyền làm tiếp đơn cũ của người
		 * cũ"*, và chốt lại: *"cấu hình nội bộ chi phí mà, không liên quan bên ngoài"* ·
		 * *"phân cơ sở thì toàn quyền"*.
		 *
		 * 🔴 ĐÂY LÀ GỐC RỄ CỦA CẢ CHUỖI HỎI NGÀY 14/09. Hai vế trên chỉ so ô Cơ sở của NGƯỜI
		 *    ĐANG XEM với chuỗi cơ sở ghi TRÊN ĐƠN. Nên hai người cùng được phân `TUTU_BD` vẫn
		 *    không thấy nhau, vì dòng chi của họ ghi "TÀU BÌNH DƯƠNG" — hai chuỗi khác nhau cho
		 *    cùng một chỗ làm. Bạn mới nhận việc mở trang ra thấy trắng, và không ai hiểu vì sao.
		 *
		 *    Vế này hỏi thẳng câu đáng hỏi: NGƯỜI LẬP ĐƠN có được phân cùng cơ sở với mình
		 *    không. Hỏi trong BẢNG NGƯỜI DÙNG của chính trang chi phí — cấu hình nội bộ, không
		 *    liên quan danh mục hay hệ thống nào bên ngoài, đúng như anh nói.
		 *
		 * ⚠️ KHÔNG NỚI CHO Ô RỖNG. Người chưa được phân cơ sở thì ô của họ rỗng; coi "rỗng gặp
		 *    rỗng" là cùng chỗ làm thì mọi người chưa khai bỗng thấy đơn của nhau — mở toang
		 *    bằng đúng cái ô mà người ta quên điền.
		 *
		 * ⚠️ VÀ CHỈ CẦN CHẠM MỘT CƠ SỞ CHUNG. Người phụ trách hai gian, người kia một gian: chỉ
		 *    cần một gian trùng là cùng làm việc ở đó.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		return self::cung_coso_khai( $nguoi_tao );
	}

	/**
	 * Người này và người đang gọi có được phân chung ít nhất một cơ sở không (bảng Người dùng).
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 VẾ NÀY PHỤ THUỘC TÀI KHOẢN NGƯỜI LẬP CÒN TRONG SỔ — VÀ ĐÓ LÀ GIỚI HẠN CỦA NÓ.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026 tự soi ra: *"khoá nhân viên cũ là nó ép không cho hiện, anh mở nhân
	 * viên cũ là nó tự hiện cả 2 người luôn"*. Đúng như vậy, và đúng theo thiết kế: nó tra ô Cơ
	 * sở của NGƯỜI LẬP trong bảng Người dùng, nên tài khoản ấy bị gỡ là không còn gì để tra.
	 *
	 * Cay ở chỗ: ca cần nhất lại chính là ca ấy — *"bạn cũ nghỉ, bạn mới nhận việc"*. Người ta
	 * nghỉ thì tài khoản bị khoá, và đơn họ để lại biến mất khỏi mắt người kế nhiệm.
	 *
	 * ⚠️ ĐỪNG CHỮA BẰNG CÁCH GIỮ TÀI KHOẢN CŨ SỐNG. Một tài khoản còn hiệu lực là một PIN còn
	 *    vào được sổ tiền; giữ nó chỉ để danh sách đơn đẹp là đổi một lỗ bảo mật lấy một tiện
	 *    nghi hiển thị.
	 *
	 * 🔴 ĐƯỜNG BỀN LÀ SỔ MÃ GỌI TẮT (`so_ma_tat()`). Khai `TUTU_BD|TÀU BÌNH DƯƠNG` một lần thì
	 *    ô Cơ sở của người đang xem dịch thẳng ra tên gian, và vế CHÍNH — so với cơ sở ghi trên
	 *    ĐƠN — khớp ngay. Đơn là thứ không bao giờ biến mất khi một tài khoản bị khoá, nên lối
	 *    ấy đứng vững qua mọi lượt người vào người ra. Vế dưới đây chỉ là lưới đỡ cho lúc chưa
	 *    kịp khai sổ.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function cung_coso_khai( $nguoi_tao ) {
		$ten = mb_strtolower( trim( (string) $nguoi_tao ) );
		if ( '' === $ten ) { return false; }
		$cua_toi = self::coso_ds();
		if ( ! $cua_toi ) { return false; }   // mình chưa được phân cơ sở -> không ghép với ai
		$ho = self::coso_cua_nguoi( $nguoi_tao );
		if ( ! $ho ) { return false; }        // họ chưa được phân -> cũng không
		foreach ( $ho as $a ) {
			foreach ( $cua_toi as $b ) {
				if ( mb_strtolower( trim( $a ) ) === mb_strtolower( trim( $b ) ) ) { return true; }
			}
		}
		return false;
	}

	/** Ô Cơ sở của một người trong bảng Người dùng — đã tách theo dấu phẩy và dịch mã sang tên. */
	public static function coso_cua_nguoi( $ten ) {
		$k = mb_strtolower( trim( (string) $ten ) );
		if ( '' === $k ) { return array(); }
		/* ⚠️ Gác CÙNG HÀM với lời gọi — luật `tools/test/kiem-goi-cheo.php`. */
		if ( ! class_exists( 'VHCPMTD_Cfg' ) || ! method_exists( 'VHCPMTD_Cfg', 'get_users' ) ) { return array(); }
		foreach ( (array) call_user_func( array( 'VHCPMTD_Cfg', 'get_users' ) ) as $u ) {
			$u = (array) $u;
			if ( mb_strtolower( trim( (string) ( isset( $u['ten'] ) ? $u['ten'] : '' ) ) ) !== $k ) { continue; }
			$ra = array();
			foreach ( explode( ',', (string) ( isset( $u['coso'] ) ? $u['coso'] : '' ) ) as $x ) {
				$x = trim( $x );
				if ( '' !== $x ) { $ra[] = self::doi_ma_sang_ten( $x ); }
			}
			return $ra;
		}
		return array();
	}

	/** login(pin) */
	public static function login( $pin ) {
		$pin = trim( (string) $pin );
		// 4–8 chữ số: PIN dài hơn 4 số vẫn phải đăng nhập được, không thì cấp PIN 6 số
		// là khoá luôn tài khoản đó (chặn ngay ở đây, chưa kịp so PIN).
		if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) { return array( 'ok' => false, 'error' => 'PIN phải gồm 4–8 chữ số' ); }
		if ( self::is_locked() ) { return array( 'ok' => false, 'error' => 'Nhập sai quá nhiều lần — thử lại sau 10 phút' ); }

		$u = null;
		foreach ( VHCPMTD_Cfg::get_users() as $x ) {   // get_users() tự seed nếu cấu hình còn trống
			if ( trim( (string) $x['pin'] ) === $pin ) { $u = $x; break; }
		}

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * MỘT PIN VÀO CẢ HAI TRANG — anh Thắng 13/09/2026: *"vì khi nhân viên dùng tk từ nhân
		 * sự thì dữ liệu không có"*.
		 *
		 * Trước bản này hai trang có hai kho PIN rời hẳn nhau: trang Chi phí đọc bảng người
		 * dùng của riêng nó, trang Nhân sự đọc cột `pin_dang_nhap` của bảng hồ sơ. Không một
		 * dòng mã nào nối hai bên — nên PIN cấp bên Nhân sự gõ vào đây chỉ nhận về "PIN không
		 * đúng hoặc chưa được cấp", đúng như anh gặp.
		 *
		 * 🔴 CHỈ MƯỢN MẬT KHẨU, KHÔNG MƯỢN QUYỀN. Khớp được PIN bên Nhân sự vẫn CHƯA đủ để vào:
		 *    người ấy phải có sẵn một dòng trong bảng người dùng của trang Chi phí, và vai ·
		 *    cơ sở · phòng ban lấy từ ĐÚNG DÒNG ĐÓ. Làm ngược lại — lấy vai bên Nhân sự sang —
		 *    là cả sổ nhân viên tự nhiên có quyền trên trang tiền, mà không ai bấm nút nào.
		 *    Chưa có dòng thì chối, kèm câu nói rõ vì sao, để khỏi ngồi thử lại PIN.
		 *
		 * 🔴 THỨ TỰ: BẢNG CHI PHÍ TRƯỚC. Hai kho có thể trùng PIN nhau (4 chữ số, vài chục
		 *    người). Tra bên Nhân sự trước là một PIN trùng sẽ mở nhầm tài khoản của người
		 *    khác — đường vòng giành mất chỗ của đường chính.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		if ( ! $u ) {
			$qua = self::qua_nhan_su( $pin );
			if ( isset( $qua['error'] ) ) {
				/* Đếm là một lượt sai: PIN ấy CÓ thật bên Nhân sự, nên nếu không đếm thì đây
				   thành cái cửa dò PIN không giới hạn lượt. */
				self::bump_fails();
				return array( 'ok' => false, 'error' => (string) $qua['error'] );
			}
			if ( isset( $qua['user'] ) ) { $u = $qua['user']; }
		}

		if ( $u ) {
			self::clear_fails();
			$tok = self::issue_token( $u['ten'], ( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ), $u['coso'], $u['boPhan'] );
			return array(
				'ok'     => true,
				'name'   => $u['ten'],
				'role'   => ( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ),
				'roleGoc' => VHCPMTD_Cfg::vai_goc( $u['vaiTro'] !== '' ? $u['vaiTro'] : 'Nhân viên' ),
				'coso'   => self::coso_hien( $u['coso'] ),
				'boPhan' => $u['boPhan'],
				'maNv'   => isset( $u['maNv'] ) ? (string) $u['maNv'] : '',
				'token'  => $tok,
			);
		}
		self::bump_fails();
		return array( 'ok' => false, 'error' => 'PIN không đúng hoặc chưa được cấp' );
	}

	/**
	 * PIN NÀY CÓ PHẢI CỦA MỘT HỒ SƠ BÊN NHÂN SỰ KHÔNG — và nếu phải thì nó ứng với dòng nào
	 * trong bảng người dùng của trang Chi phí.
	 *
	 * @return array  array()                 — không dính dáng gì tới bên Nhân sự, đi tiếp
	 *                array('error' => …)      — PIN có thật bên ấy nhưng KHÔNG cho vào, kèm lý do
	 *                array('user'  => dòng)   — nối được, cấp thẻ theo dòng này
	 *
	 * ⚠️ MỌI ĐƯỜNG CHỐI ĐỀU PHẢI NÓI RÕ LÝ DO. Trả về một câu "PIN không đúng" cho người có PIN
	 *    đúng là họ gõ lại năm lần rồi bị khoá mười phút, trong khi việc cần làm chỉ là khai
	 *    thêm một dòng ở màn Cấu hình.
	 */
	private static function qua_nhan_su( $pin ) {
		global $wpdb;
		/* ⚠️ GÁC BẰNG `method_exists`, KHÔNG CHỈ `class_exists`. Bốn plugin cài độc lập, bản có
		   thể lệch nhau — lớp có mặt mà hàm chưa có là gọi hụt, và gọi hụt ở ĐÂY thì trắng
		   nguyên cổng đăng nhập, tức không ai vào được trang. */
		if ( ! method_exists( 'VHCC_DB', 't' ) ) { return array(); }   // trang Nhân sự chưa cài
		$t = VHCC_DB::t( 'nhan_vien' );
		/* Bảng chưa dựng (plugin vừa bật, chưa chạy install) — hỏi thẳng là một câu lỗi SQL
		   vào nhật ký ở MỌI lượt đăng nhập sai. */
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return array(); }

		$ds = $wpdb->get_results( $wpdb->prepare(
			"SELECT ma_nv, ho_ten, trang_thai_lam_viec FROM $t WHERE pin_dang_nhap = %s", $pin ), ARRAY_A );
		if ( ! $ds ) { return array(); }

		/* 🔴 HAI HỒ SƠ CÙNG PIN -> CHỐI, KHÔNG ĐOÁN. Cột `pin_dang_nhap` bên ấy không có
		   UNIQUE, nên hai người mang cùng PIN là chuyện có thật. Đoán lấy hàng đầu là cho
		   người này vào bằng tài khoản của người kia. */
		if ( count( $ds ) > 1 ) {
			return array( 'error' => 'PIN này đang khai cho ' . count( $ds ) . ' người bên trang '
				. 'Nhân sự, nên không biết là ai. Nhờ quản trị đổi PIN cho từng người rồi thử lại.' );
		}
		$ns  = $ds[0];
		$mnv = trim( (string) $ns['ma_nv'] );
		$ten = trim( (string) $ns['ho_ten'] );

		if ( method_exists( 'VHCC_NhanSu', 'da_nghi' ) && VHCC_NhanSu::da_nghi( $ns['trang_thai_lam_viec'] ) ) {
			return array( 'error' => 'Hồ sơ "' . $ten . '" đang để trạng thái đã nghỉ việc bên '
				. 'trang Nhân sự, nên PIN này không mở được trang Chi phí.' );
		}

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * NỐI HAI BÊN: MÃ NV TRƯỚC, TÊN SAU.
		 *
		 * Mã NV là khoá chắc chắn — mỗi hồ sơ một mã, `UNIQUE KEY ma_nv` bên ấy lo phần đó, và
		 * ô mã NV bên này đã chặn hai người cùng mã lúc Lưu.
		 *
		 * Tên là khoá LỎNG, chỉ dùng khi chưa ai khai mã: gõ lệch một dấu là trượt, và hai
		 * người trùng tên thật thì nó nối vào nhầm người. Nhưng bỏ nó đi thì ngày đầu chưa
		 * khai mã NV cho ai, không một tài khoản nào vào được — nên vẫn giữ, và đặt SAU.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		$x = self::dong_chi_phi_cua( $mnv, $ten );
		if ( $x ) { return array( 'user' => $x ); }

		return array( 'error' => 'PIN đúng bên trang Nhân sự, nhưng "' . $ten . '"'
			. ( '' !== $mnv ? ' (' . $mnv . ')' : '' ) . ' chưa được cấp quyền ở trang Chi phí. '
			. 'Nhờ quản trị thêm một dòng cho người này ở màn Cấu hình → Người dùng & Phân quyền.' );
	}

	/**
	 * DÒNG NGƯỜI DÙNG BÊN CHI PHÍ ỨNG VỚI MỘT NGƯỜI BÊN NHÂN SỰ — null nếu chưa có.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * NỐI HAI BÊN: MÃ NV TRƯỚC, TÊN SAU.
	 *
	 * Mã NV là khoá chắc chắn — mỗi hồ sơ một mã, `UNIQUE KEY ma_nv` bên ấy lo phần đó, và ô
	 * mã NV bên này đã chặn hai người cùng mã lúc Lưu.
	 *
	 * Tên là khoá LỎNG, chỉ dùng khi chưa ai khai mã: gõ lệch một dấu là trượt, và hai người
	 * trùng tên thật thì nó nối vào nhầm người. Nhưng bỏ nó đi thì ngày đầu chưa khai mã NV
	 * cho ai, không một tài khoản nào vào được — nên vẫn giữ, và đặt SAU.
	 *
	 * ⚠️ TÁCH RA KHỎI `qua_nhan_su()` ĐỂ DÙNG CHUNG VỚI ĐƯỜNG VÉ / SSO. Hai cửa vào cùng một hệ
	 *    mà nối người theo hai cách khác nhau thì cùng một người vào bằng hai cửa lại ra hai
	 *    quyền — đúng thứ `vai_chi_phi()` bên chấm công đã ghi chú mà vẫn xảy ra.
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function dong_chi_phi_cua( $ma_nv, $ten ) {
		$mnv      = trim( (string) $ma_nv );
		$k_ten    = mb_strtolower( trim( (string) $ten ) );
		$theo_ten = null;
		foreach ( VHCPMTD_Cfg::get_users() as $x ) {
			$m = trim( (string) ( isset( $x['maNv'] ) ? $x['maNv'] : '' ) );
			if ( '' !== $mnv && '' !== $m && mb_strtolower( $m ) === mb_strtolower( $mnv ) ) {
				return $x;
			}
			if ( null === $theo_ten && '' !== $k_ten && mb_strtolower( trim( (string) $x['ten'] ) ) === $k_ten ) {
				$theo_ten = $x;
			}
		}
		return $theo_ten;
	}

	/** changePin(name, oldPin, newPin) */
	public static function change_pin( $name, $old, $new ) {
		$name = trim( (string) $name );
		$old  = trim( (string) $old );
		$new  = trim( (string) $new );
		if ( ! preg_match( '/^\d{4,8}$/', $new ) ) { return VHCPMTD_Util::err( 'PIN mới phải gồm 4–8 chữ số' ); }
		VHCPMTD_Cfg::seed();
		$rows = VHCPMTD_Cfg::read( VHCPMTD_Cfg::USER );
		$my   = -1;
		foreach ( $rows as $i => $r ) {
			if ( trim( (string) $r[0] ) === $name && trim( (string) $r[1] ) === $old ) { $my = $i; break; }
		}
		if ( $my < 0 ) { return VHCPMTD_Util::err( 'PIN hiện tại không đúng' ); }
		foreach ( $rows as $j => $r ) {
			if ( $j !== $my && trim( (string) $r[1] ) === $new ) { return VHCPMTD_Util::err( 'PIN này đã có người dùng khác — chọn PIN khác' ); }
		}
		VHCPMTD_Cfg::set_cell( VHCPMTD_Cfg::USER, $my, 1, $new );
		return VHCPMTD_Util::ok();
	}

	// ---------------------------------------------------------------- phiên

	public static function issue_token( $ten, $role, $coso, $bo_phan ) {
		global $wpdb;
		self::gc( $ten );
		$tok = bin2hex( random_bytes( 32 ) );
		$wpdb->insert( VHCPMTD_DB::t( 'session' ), array(
			'token'   => $tok,
			'ten'     => (string) $ten,
			'vai_tro' => (string) $role,
			'coso'    => (string) $coso,
			'bo_phan' => (string) $bo_phan,
			'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
		) );
		return $tok;
	}

	/** Người dùng của token (null nếu sai/hết hạn). */
	public static function user_by_token( $token ) {
		global $wpdb;
		$token = (string) $token;
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $token ) ) { return null; }
		$t = VHCPMTD_DB::t( 'session' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token=%s AND het_han > UTC_TIMESTAMP()", $token ), ARRAY_A );
		if ( ! $r ) { return null; }
		/* ══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 VAI · CƠ SỞ · BỘ PHẬN ĐỌC LẠI TỪ BẢNG NGƯỜI DÙNG, KHÔNG TIN THẺ PHIÊN.
		 *
		 * Thẻ phiên ghi vai LÚC ĐĂNG NHẬP và thẻ sống 30 ngày. Nên đổi vai của một người ở màn
		 * Cấu hình KHÔNG có hiệu lực gì cho tới khi họ tự đăng xuất — và không ai bảo họ phải
		 * làm thế.
		 *
		 * Đã cắn thật 08/09/2026: anh Thắng gán vai "Kế toán máy tự động" cho một tài khoản,
		 * bấm Lưu, rồi hỏi *"đã phân qua kế toán máy tự động, tại sao vẫn nhìn được nội dung
		 * của bộ phận khác"*. Màn của người ấy vẫn ghi vai CŨ trên thanh tiêu đề — đúng thứ
		 * đang nằm trong thẻ phiên.
		 *
		 * Chiều nguy hiểm hơn nhiều: THU HỒI quyền cũng không ăn. Hạ một người từ Kế toán
		 * xuống Nhân viên, hay siết tầm nhìn đơn vị của họ, mà phiên đang mở vẫn giữ quyền cũ
		 * suốt 30 ngày.
		 *
		 * Thẻ phiên từ nay chỉ trả lời đúng một câu: NGƯỜI NÀY LÀ AI. Còn họ được làm gì thì
		 * hỏi bảng người dùng, mỗi lượt gọi.
		 *
		 * ⚠️ Tên không còn trong bảng (đã xoá tài khoản) -> dùng nguyên thẻ như cũ. Chối thẳng
		 *    thì một lượt `get_users()` lỗi là đá văng mọi người đang đăng nhập.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		$ten  = (string) $r['ten'];
		$vai  = (string) $r['vai_tro'];
		$cs   = (string) $r['coso'];
		$bp   = (string) $r['bo_phan'];
		$mnv  = '';
		$khoa = mb_strtolower( trim( $ten ) );
		if ( '' !== $khoa && class_exists( 'VHCPMTD_Cfg' ) ) {
			foreach ( VHCPMTD_Cfg::get_users() as $u ) {
				if ( mb_strtolower( trim( (string) $u['ten'] ) ) !== $khoa ) { continue; }
				$vai = ( trim( (string) $u['vaiTro'] ) !== '' ) ? (string) $u['vaiTro'] : 'Nhân viên';
				$cs  = (string) $u['coso'];
				$bp  = (string) $u['boPhan'];
				/* Mã NV KHÔNG nằm trong thẻ phiên — thẻ không có cột ấy, và cũng không cần:
				   đã đọc lại vai/cơ sở/bộ phận từ bảng người dùng mỗi lượt thì đọc thêm một ô
				   nữa không tốn gì, mà khai mã NV cho ai đó là ăn ngay, khỏi đăng nhập lại. */
				$mnv = isset( $u['maNv'] ) ? trim( (string) $u['maNv'] ) : '';
				break;
			}
		}
		/* 🔴 GỬI KÈM VAI GỐC. Giao diện dựng danh sách tab bằng một bảng tra theo TÊN VAI —
		   vai tự tạo không có trong bảng đó nên rơi vào nhánh mặc định và chỉ còn đúng một tab.
		   Vai vừa tạo ra mà gần như không dùng được thì tính năng tạo vai coi như vô nghĩa. */
		return array( 'name' => $ten, 'role' => $vai,
			'roleGoc' => VHCPMTD_Cfg::vai_goc( $vai ),
			'coso' => $cs, 'boPhan' => $bp, 'maNv' => $mnv );
	}

	/**
	 * TÔI LÀ AI — danh tính của phiên đang cầm token, để trang tự vào lại khỏi hỏi PIN.
	 *
	 * =========================================================================================
	 * 🔴 KHÔNG BAO GIỜ LƯU PIN Ở MÁY KHÁCH
	 * =========================================================================================
	 * Anh Thắng 07/09/2026: *"trang chưa tự lưu mật khẩu để lần sau… khỏi đăng nhập lại"*.
	 * Cách làm đúng KHÔNG phải là nhớ PIN trong trình duyệt: PIN nằm trong `localStorage` thì
	 * bất cứ ai mượn máy, hoặc bất cứ đoạn mã lạ nào chạy trên trang, đều đọc được nó — mà PIN
	 * ấy còn mở được cả trang chấm công và trang nội bộ. Trang chạy ngoài internet.
	 *
	 * Thứ được nhớ là TOKEN PHIÊN: một chuỗi ngẫu nhiên, chỉ dùng được cho đúng phiên này, thu
	 * hồi được từ máy chủ (bấm Đăng xuất), và tự chết sau 30 ngày.
	 *
	 * ⚠️ VÌ SAO PHẢI CÓ HÀM NÀY: token vốn đã sống 30 ngày trong `localStorage`, nhưng DANH TÍNH
	 *    (tên · vai · cơ sở) lại nằm ở `sessionStorage` — mà `sessionStorage` chết ngay khi đóng
	 *    trình duyệt. Nên mở lại là trang không biết mình là ai, bày cổng PIN, dù token còn
	 *    nguyên. Hỏi máy chủ một câu là xong, và danh tính lấy từ máy chủ thì luôn đúng: đổi vai
	 *    trò hay đổi cơ sở cho ai đó là lần mở trang sau họ nhận ngay, không phải đăng xuất.
	 *
	 * ⚠️ GIA HẠN LĂN: còn dưới 7 ngày thì đẩy hạn về đủ 30 ngày. Người dùng hằng ngày sẽ không
	 *    bao giờ chạm mốc hết hạn; người bỏ trang 30 ngày thì vẫn phải nhập lại — đúng ý.
	 */
	public static function ai_dang_dang_nhap( $token = '' ) {
		global $wpdb;
		$token = (string) $token;
		$u = self::user_by_token( $token );
		if ( ! $u ) { return array( 'ok' => false, 'error' => 'Phiên đã hết hạn' ); }

		/* ⚠️ ĐO KHOẢNG THỜI GIAN BẰNG PHP, KHÔNG NHỜ SQL. `TIMESTAMPDIFF()` là hàm của MySQL —
		   đúng trên host thật, nhưng bệ đỡ thử chạy SQLite thì nó trả 0, và 0 thì lần nào cũng
		   rơi vào nhánh gia hạn. Nghĩa là phép kiểm sẽ xanh mà chẳng canh được gì. Đọc ra rồi
		   so bằng `strtotime()` thì hai nơi cùng một kết quả. */
		$t   = VHCPMTD_DB::t( 'session' );
		$hh  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT het_han FROM $t WHERE token=%s", $token ) );
		$con = $hh !== '' ? ( strtotime( $hh . ' UTC' ) - time() ) : 0;
		if ( $con < self::TTL_GIA_HAN ) {
			$wpdb->update( $t, array( 'het_han' => gmdate( 'Y-m-d H:i:s', time() + self::TTL ) ),
				array( 'token' => $token ) );
		}
		return array( 'ok' => true, 'name' => $u['name'], 'role' => $u['role'],
			'roleGoc' => $u['roleGoc'], 'coso' => self::coso_hien( $u['coso'] ), 'boPhan' => $u['boPhan'] );
	}

	public static function logout( $token ) {
		global $wpdb;
		$wpdb->delete( VHCPMTD_DB::t( 'session' ), array( 'token' => (string) $token ) );
		return VHCPMTD_Util::ok();
	}

	private static function gc( $ten = '' ) {
		global $wpdb;
		$t = VHCPMTD_DB::t( 'session' );
		$wpdb->query( "DELETE FROM $t WHERE het_han < UTC_TIMESTAMP()" );
		// SSO phát token mỗi lần tải trang -> giữ tối đa 20 phiên còn sống cho mỗi người.
		if ( $ten !== '' ) {
			$keep = $wpdb->get_col( $wpdb->prepare( "SELECT token FROM $t WHERE ten=%s ORDER BY het_han DESC LIMIT 20", (string) $ten ) );
			if ( is_array( $keep ) && count( $keep ) >= 20 ) {
				$in = implode( ',', array_map( array( __CLASS__, 'quote_token' ), $keep ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE ten=%s AND token NOT IN ($in)", (string) $ten ) );
			}
		}
	}

	private static function quote_token( $t ) {
		return "'" . preg_replace( '/[^0-9a-f]/', '', (string) $t ) . "'";
	}

	// ---------------------------------------------------------------- hãm thử PIN

	private static function fail_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		return 'vhcpmtd_fail_' . md5( $ip );
	}

	private static function is_locked() {
		return (int) get_transient( self::fail_key() ) >= 10;
	}

	private static function bump_fails() {
		$k = self::fail_key();
		set_transient( $k, (int) get_transient( $k ) + 1, 600 );
	}

	private static function clear_fails() {
		delete_transient( self::fail_key() );
	}

	/**
	 * MỞ KHÓA ĐĂNG NHẬP (gọi từ wp-admin).
	 *
	 * Khóa đếm theo IP và tự hết sau 10 phút, nhưng người bị khóa thì đang cần vào ngay.
	 * Ai vào được wp-admin thì đã là quản trị WordPress, cho mở khóa luôn khỏi phải chờ.
	 * Xóa cả khóa của IP đang gọi lẫn mọi khóa còn hạn trong bảng transient.
	 */
	public static function mo_khoa() {
		global $wpdb;
		delete_transient( self::fail_key() );
		$n = 0;
		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_vhcpmtd\\_fail\\_%'" );
		foreach ( (array) $rows as $o ) {
			$key = preg_replace( '/^_transient_/', '', (string) $o );
			if ( $key !== '' ) { delete_transient( $key ); $n++; }
		}
		return $n;
	}

	// ---------------------------------------------------------------- SSO từ trang tổng K&H

	public static function sso_secret() {
		$s = VHCPMTD_Meta::get( 'SSO_SECRET' );
		if ( ! $s ) { $s = get_option( 'vhcpmtd_sso_secret' ); }
		return $s ? $s : '';
	}

	/**
	 * verifySsoToken(): base64url(payload).base64url(HMAC-SHA256) — cùng thuật toán
	 * với app Apps Script cũ nên trang tổng K&H không phải sửa gì.
	 */
	public static function verify_sso_token( $token ) {
		$secret = self::sso_secret();
		if ( ! $secret || ! $token ) { return null; }
		$parts = explode( '.', (string) $token );
		if ( count( $parts ) !== 2 ) { return null; }
		list( $p, $sig ) = $parts;
		$expect = self::b64url( hash_hmac( 'sha256', $p, $secret, true ) );
		if ( ! hash_equals( $expect, $sig ) ) { return null; }
		$json = self::b64url_decode( $p );
		$obj  = json_decode( $json, true );
		if ( ! is_array( $obj ) || empty( $obj['x'] ) ) { return null; }
		if ( ( time() * 1000 ) > (float) $obj['x'] ) { return null; }
		return $obj;
	}

	/** resolveSsoUser(): vai trò trang tổng → vai trò Chi Phí, có bảng override theo email. */
	/**
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 VÀO BẰNG VÉ / SSO CHỈ MƯỢN DANH TÍNH — QUYỀN LẤY TỪ BẢNG NGƯỜI DÙNG BÊN NÀY
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 20/09/2026: *"việc đẩy nhân sự sang chỉ là để đăng nhập. Sau phân quyền cho
	 * bên chi phí quyết định. Để tránh râu ông này cắm bà kia"*.
	 *
	 * 🔴 BẢN CŨ LẤY THẲNG VAI VÀ CƠ SỞ TỪ BÊN KIA (`$ident['r']`, `$ident['b']`) và KHÔNG hề
	 *    tra bảng người dùng bên này. Nên:
	 *      · ai là Quản lý bên chấm công thì bấm sang là Quản lý trên trang TIỀN — duyệt được
	 *        chi của mọi cơ sở, mà không ai bên chi phí bấm nút nào;
	 *      · cơ sở cũng bê nguyên sang, tức lại là MÃ cửa hàng (`TUTU_BD`) chứ không phải TÊN
	 *        gian hàng — cùng cái lỗi đã sửa ở lượt đẩy hôm qua, chỉ khác cửa;
	 *      · và nó cấp lại ở MỖI LƯỢT ĐĂNG NHẬP, không để lại dòng nào cho kế toán nhìn thấy
	 *        mà sửa. Lỗi ở đây im hơn hẳn lượt đẩy.
	 *
	 *    Chính `login()` ngay trên kia đã chốt luật đúng từ lâu — *"CHỈ MƯỢN MẬT KHẨU, KHÔNG
	 *    MƯỢN QUYỀN… người ấy phải có sẵn một dòng trong bảng người dùng của trang Chi phí, và
	 *    vai · cơ sở · phòng ban lấy từ ĐÚNG DÒNG ĐÓ"*. Cửa vé thì chưa theo. Nay theo.
	 *
	 * ⚠️ CHƯA CÓ DÒNG THÌ TRẢ `null` — không tự đẻ ra một danh tính. Gọi bên ngoài hiểu `null`
	 *    là "không vào được bằng đường này", y như `qua_nhan_su()` chối PIN với đúng lý do ấy.
	 *    Đẻ bừa một tài khoản 'Nhân viên' là mở cửa cho cả sổ nhân sự bước vào trang tiền.
	 *
	 * ⚠️ BẢNG `sso_overrides()` VẪN GIỮ và vẫn thắng — nó là bảng khai BÊN NÀY (màn Cấu hình),
	 *    tức chính là "bên chi phí quyết định", không phải dữ liệu mượn từ bên kia.
	 *
	 * ⚠️ `$ident['r']` / `$ident['b']` NAY CHỈ DÙNG ĐỂ… không dùng gì cả. Giữ trong chữ ký vì
	 *    bên gọi vẫn gửi, nhưng cố ý KHÔNG đọc: đọc là mở lại đúng cái cửa vừa đóng.
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function resolve_sso_user( $ident ) {
		$email = trim( (string) ( isset( $ident['e'] ) ? $ident['e'] : '' ) );
		$ten   = trim( (string) ( isset( $ident['n'] ) ? $ident['n'] : '' ) );
		$ma_nv = trim( (string) ( isset( $ident['m'] ) ? $ident['m'] : '' ) );

		$x = self::dong_chi_phi_cua( $ma_nv, $ten );
		if ( ! $x ) { return null; }

		$role = trim( (string) $x['vaiTro'] );
		if ( '' === $role ) { $role = 'Nhân viên'; }
		$coso = (string) $x['coso'];

		/* Bảng đè theo email — khai ở màn Cấu hình bên này, nên nó thắng. */
		$ov = self::sso_overrides();
		$k  = strtolower( $email );
		if ( isset( $ov[ $k ] ) ) {
			if ( ! empty( $ov[ $k ]['role'] ) ) { $role = $ov[ $k ]['role']; }
			if ( ! empty( $ov[ $k ]['coso'] ) ) { $coso = $ov[ $k ]['coso']; }
		}
		return array(
			'name'    => (string) $x['ten'],
			'role'    => $role,
			'roleGoc' => VHCPMTD_Cfg::vai_goc( $role ),
			'coso'    => self::coso_hien( $coso ),
			'boPhan'  => isset( $x['boPhan'] ) ? (string) $x['boPhan'] : '',
		);
	}

	private static function sso_overrides() {
		$hit = get_transient( 'vhcpmtd_ssomap' );
		if ( is_array( $hit ) ) { return $hit; }
		$out = array();
		foreach ( VHCPMTD_Cfg::read( VHCPMTD_Cfg::SSO ) as $r ) {
			$em = strtolower( trim( (string) $r[0] ) );
			if ( $em === '' ) { continue; }
			$out[ $em ] = array( 'role' => trim( (string) $r[1] ), 'coso' => trim( (string) $r[2] ) );
		}
		set_transient( 'vhcpmtd_ssomap', $out, 300 );
		return $out;
	}

	private static function b64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( $s ) {
		$s .= str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 );
		return base64_decode( strtr( $s, '-_', '+/' ) );
	}
}
