<?php
/**
 * CẤU HÌNH — bản dịch của các sheet CH_* + ma trận phân quyền + người dùng PIN + SSO.
 *
 * Mỗi "sheet cấu hình" là các dòng trong bảng vhcp_cfg (cột `bang` = tên sheet cũ,
 * `cols` = JSON mảng ô của hàng đó) nên giữ đúng thứ tự và số cột như bản Google Sheet.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Cfg {

	const COSO  = 'CH_CoSo';
	const NHOM  = 'CH_Nhom';
	const PL    = 'CH_PhanLoai';
	const DT    = 'CH_DoiTuong';
	const QR    = 'CH_QR';
	const USER  = 'CH_NguoiDung';
	const TKNO  = 'CH_TKNo';
	const SSO   = 'CH_SSO';
	const BP    = 'CH_BoPhan';       // BỘ PHẬN — khai được, xem `bo_phan_ds()`
	const QUYEN = 'CH_Quyen';
	const LOAI  = 'CH_LoaiChiPhi';   // DANH MỤC LOẠI CHI PHÍ — mỗi loại gắn sẵn mã tài khoản
	const TK    = 'CH_TaiKhoan';     // HỆ THỐNG TÀI KHOẢN của kế toán (nạp từ file Excel/CSV)
	const MANG  = 'CH_MangTK';       // MẢNG KINH DOANH -> nhóm tài khoản 641x + từ khóa trong tên TK
	const VAI   = 'CH_VaiTro';       // VAI TRÒ TỰ TẠO — mỗi vai kế thừa quyền của một vai gốc

	public static function headers( $bang ) {
		$h = array(
			// Cột "Đóng cửa": ngày kế toán làm lệnh đóng gian hàng. Đóng rồi thì thôi luân
			// chuyển bù trừ sang kỳ sau — lúc đóng là đã tất toán bằng tiền.
			/* 🔴 CỘT "ĐƠN VỊ" (cuối) LÀ K&H · POSH — KHÔNG PHẢI cột "Mã đơn vị" (thứ hai).
			   Hai chữ gần giống nhau nằm cùng một bảng, nên nói cho rõ một lần: "Mã đơn vị"
			   là mã của MISA, đi thẳng vào tệp xuất; "Đơn vị" là NHÀ của cơ sở — nó quyết
			   định bên nào nhìn thấy chi phí của cơ sở ấy. Đổi tên cột "Mã đơn vị" thì tệp
			   MISA của anh Thắng gãy, nên để nguyên và đặt cột mới ở cuối.

			   Anh Thắng 08/09/2026: *"danh mục cơ sở nhập vẫn trường thông tin đó luôn, cơ sở
			   lấy từ bên posh là các cơ sở posh đang hoạt động"* — dùng chung một danh mục,
			   cơ sở POSH khai thêm vào đây, cột này nói nó thuộc bên nào.

			   ⚠️ Ô để trống = K&H (nhà mặc định). Mọi cơ sở khai trước bản này đều rỗng, mà
			      trước khi có POSH thì cơ sở nào cũng là cơ sở K&H. */
			self::COSO  => array( 'Cơ sở', 'Mã đơn vị', 'Phân loại lớn', 'Tên MISA', 'Đóng cửa', 'Đơn vị' ),
			self::NHOM  => array( 'Nhóm mặt hàng', 'Loại', 'TK Nợ', 'Bộ phận' ),
			self::PL    => array( 'Phân loại TT', 'TK Có' ),
			/* Bộ phận — trước bản này gõ cứng ở hai nơi (hằng dưới + `BOPHAN_LIST` trong
			   app.html), nên thêm một mảng là phải sửa mã. Nay khai được ở Cấu hình. */
			self::BP    => array( 'Bộ phận' ),
			self::DT    => array( 'Đối tượng', 'Mã đối tượng', 'Loại (NV/NCC)' ),
			self::QR    => array( 'Khóa', 'Giá trị' ),
			/* Hai cột cuối là ĐƠN VỊ (K&H · POSH) — xem `VHCP_DonVi`. "Đơn vị" là NHÀ (đơn
			   người ấy lập rơi về đâu); "Xem đơn vị" là những đơn vị người ấy ĐƯỢC ĐỌC, cách
			   nhau dấu phẩy, để trống = theo mặc định của vai. Hai việc khác nhau nên hai cột:
			   nhà thì phải là một, còn tầm nhìn thì có thể là nhiều. */
			/* ⚠️ 'Mã NV' và 'Khối' là hai ô CUỐI. 'Mã NV' đã được ghi xuống ô 10 từ lâu mà
			   thiếu tên ở đây; bổ sung luôn cho bảng nhãn khớp đúng số ô thật. */
			self::USER  => array( 'Tên', 'PIN', 'Vai trò', 'Cơ sở', 'TK Có', 'Mã đối tượng', 'Bộ phận', 'Đơn vị', 'Xem đơn vị', 'Mã NV', 'Khối' ),
			self::TKNO  => array( 'Nhóm mặt hàng', 'Phân loại lớn', 'TK Nợ' ),
			self::SSO   => array( 'Email', 'Vai trò Chi Phí', 'Cơ sở' ),
			/* 🔴 CỘT 9 `Đơn vị` VÀ CỘT 10 `Khối` PHẢI CÓ MẶT Ở ĐÂY.
			   `Đơn vị` thêm 12/09/2026 mà QUÊN khai vào hàng này — `read()` đệm theo
			   `count(headers())` nên mọi dòng cũ chỉ được đệm tới 8 ô, và cột thứ 9 sống sót
			   chỉ nhờ `isset($r[8])` rải khắp nơi. Khai đủ thì hết phải rào.

			   `Khối` thêm 21/09/2026 — anh Thắng: *"chỗ loại chi phí, chia ra 3 bảng của 3
			   khối, để tránh dùng chung"*, và *"đơn vị nào sẽ dùng khối của đơn vị đó"*.
			   Một loại chi phí thuộc ĐÚNG MỘT khối. Hai khối cùng cần "Chi phí khác" thì mỗi
			   bên một dòng riêng — đó chính là ý "tránh dùng chung": sửa mã bên KVC không được
			   đụng tới sổ của Văn phòng. */
			/* Cột 11 `Vai trò` thêm 21/09/2026 — anh Thắng: *"bỏ tích bộ phận đi, mà tích theo
			   vai trò"*. Ai ĐƯỢC DÙNG loại chi phí này, khai bằng TÊN VAI (ngăn bằng dấu phẩy).
			   Trống = mọi vai, giữ đúng nghĩa ô trống của cột Bộ phận nó thay thế.
			   ⚠️ Cột `Bộ phận` (thứ 5) GIỮ NGUYÊN trong sổ, chỉ thôi dùng: dữ liệu đã khai của
			      anh Thắng còn đó, và xoá một cột là không lấy lại được. */
			self::LOAI  => array( 'Loại chi phí', 'TK Nợ', 'TK Có', 'Mã đối tượng', 'Bộ phận', 'Ghi chú', 'Tên MISA', 'Loại', 'Đơn vị', 'Khối', 'Vai trò' ),
			self::TK    => array( 'Số hiệu', 'Tên tài khoản', 'Tính chất' ),
			self::MANG  => array( 'Phân loại lớn', 'Nhóm TK', 'Từ khóa trong tên TK', 'Ghi chú' ),
		);
		if ( isset( $h[ $bang ] ) ) { return $h[ $bang ]; }
		if ( $bang === self::QUYEN ) { return array_merge( array( 'Mã', 'Hành động' ), self::roles() ); }
		return array();
	}

	/**
	 * CƠ SỞ ĐÃ ĐÓNG CỬA CHƯA (và đóng ngày nào).
	 *
	 * Đóng gian hàng là lúc kế toán tất toán hết bằng tiền, nên từ đó KHÔNG luân chuyển
	 * bù trừ sang kỳ sau nữa — không thì kỳ sau lại trừ tiếp một khoản đã thu xong.
	 *
	 * @return string ngày đóng (dd/MM/yyyy) · '' nếu còn hoạt động
	 */
	public static function coso_dong_cua( $ten ) {
		$k = mb_strtolower( trim( (string) $ten ) );
		if ( $k === '' ) { return ''; }
		foreach ( self::cfg_static()['coso'] as $x ) {
			if ( mb_strtolower( trim( (string) $x['ten'] ) ) === $k ) {
				return isset( $x['dongCua'] ) ? trim( (string) $x['dongCua'] ) : '';
			}
		}
		return '';
	}

	/**
	 * LỆNH ĐÓNG / MỞ LẠI GIAN HÀNG (việc của kế toán).
	 *
	 * Đóng: ghi ngày đóng, và đánh dấu TẤT TOÁN mọi đơn của cơ sở đó chưa tất toán — đóng
	 * gian là chốt sổ với người ta, nên không còn gì luân chuyển. Mở lại: xóa ngày đóng
	 * (không tự bỏ đánh dấu tất toán, vì tiền đã thu/bù thật rồi).
	 *
	 * @return array [ 'coso', 'ngay', 'soDonTatToan' ]
	 */
	public static function dong_cua_coso( $ten, $dong = true, $nguoi = '' ) {
		$ten = trim( (string) $ten );
		if ( $ten === '' ) { return VHCP_Util::err( 'Chọn cơ sở' ); }
		$k = mb_strtolower( $ten );

		$rows = array(); $thay = false;
		$ngay = $dong ? VHCP_Util::now()->format( 'd/m/Y' ) : '';
		foreach ( self::read( self::COSO ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 5 );
			for ( $i = count( $row ); $i < 5; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			if ( mb_strtolower( trim( (string) $row[0] ) ) === $k ) { $row[4] = $ngay; $thay = true; }
			$rows[] = $row;
		}
		if ( ! $thay ) { return VHCP_Util::err( 'Không thấy cơ sở "' . $ten . '" trong bảng cơ sở' ); }
		self::write( self::COSO, $rows );
		self::clear_cache();

		// Đóng gian thì chốt luôn các đơn còn treo của gian đó
		$n = 0;
		if ( $dong ) {
			foreach ( VHCP_Don::list_dons() as $d ) {
				if ( mb_strtolower( trim( (string) $d['coso'] ) ) !== $k ) { continue; }
				if ( ! empty( $d['tatToan'] ) ) { continue; }
				VHCP_Don::set_tat_toan_tuan( $d['maDon'], true, ( $nguoi !== '' ? $nguoi : 'Đóng gian hàng' ) );
				$n++;
			}
		}
		VHCP_Log::log_action( array(
			'actor'  => (string) $nguoi,
			'action' => $dong ? 'Đóng cửa gian hàng' : 'Mở lại gian hàng',
			'target' => $ten,
			'detail' => $dong ? ( 'ngày ' . $ngay . ' · tất toán ' . $n . ' đơn còn treo' ) : 'bỏ ngày đóng',
		) );
		return VHCP_Util::ok( array( 'coso' => $ten, 'ngay' => $ngay, 'soDonTatToan' => $n ) );
	}

	/** Danh sách cơ sở mặc định (COSO_LIST của app cũ). */
	public static function default_coso() {
		return array( 'FUNZONE ADVENTURE', 'FUNZONE VŨNG TÀU', 'FARM PHAN THIẾT', 'EVENT FARM NHA TRANG', 'TÀU TÂN PHÚ', 'TÀU BÌNH TÂN', 'TÀU BÌNH DƯƠNG', 'TÀU GÒ VẤP', 'TÀU ESTELLA', 'VR SORA', 'VR BÌNH DƯƠNG', 'FUNFEST SC VIVO', 'TUTU TẤN AN', 'ADV TÂN PHÚ' );
	}

	/** NHOM_LIST của app cũ. */
	public static function default_nhom() {
		return array(
			array( 'SP Đồ uống - NCC', 'ncc' ),
			array( 'SP Đồ ăn - NCC', 'ncc' ),
			array( 'Vật dụng - NCC - Kho', 'ncc' ),
			array( 'NVL đồ uống - NCC', 'ncc' ),
			array( 'NVL đồ ăn - NCC', 'ncc' ),
			array( 'NVL đồ ăn - Mua lẻ', 'canhan' ),
			array( 'NVL đồ uống - Mua lẻ', 'canhan' ),
			array( 'Chi phí cơ sở', 'canhan' ),
			array( 'MKT - Hoạt náo', 'canhan' ),
			array( 'Nuôi thú', 'canhan' ),
			array( 'Phát sinh', 'canhan' ),
		);
	}

	/**
	 * BỐN VAI GỐC — cứng, không xóa được. Mọi vai tự tạo đều phải kế thừa MỘT trong bốn vai này.
	 *
	 * 🔴 'Admin' KHÔNG có trong danh sách kế thừa. Cho kế thừa Admin là ai vào được Cấu hình
	 *    cũng tự đúc cho mình một vai Admin trá hình — thành cái cửa sau mở sẵn.
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * CHỨC DANH — DANH SÁCH ĐÓNG, KHÔNG AI ĐẺ THÊM ĐƯỢC.
	 *
	 * Anh Thắng 13/09/2026: *"anh sẽ tạo ban bệ phòng ban sẵn, ai thuộc bộ phận nào thì thêm
	 * vào, tránh sai vai hay tự tạo vai lạ"*, kèm thang quyền:
	 *   · Nhân viên (cơ sở · kỹ thuật) — chỉ xem cơ sở mình quản lý trở xuống
	 *   · Quản lý                      — xem bộ phận mình trở xuống
	 *   · Kế toán bộ phận              — xem bộ phận mình quản lý
	 *   · Giám đốc                     — toàn quyền xem
	 *   · Admin                        — toàn quyền
	 *
	 * 🔴 'Giám đốc' THÊM 13/09/2026, đứng TRÊN Quản lý. Trước nay ai cần nhìn toàn cục phải
	 *    mang vai Admin — tức trao luôn quyền sửa cấu hình, đổi PIN, xoá đơn. Giám đốc tách hẳn
	 *    hai thứ ấy: nhìn cả hệ, nhưng không phải người quản trị.
	 *
	 * 🔴 HAI VAI KẾ TOÁN GIỮ NGUYÊN. Anh Thắng gọi chung là "kế toán bộ phận", nhưng 'Kế toán
	 *    cá nhân' và 'Kế toán NCC' chia VIỆC (ai chốt dòng 141, ai chốt dòng 331) chứ không
	 *    chia PHẠM VI — hai chuyện vuông góc nhau. Gộp lại là đụng thẳng luồng duyệt NCC đang
	 *    chạy. Phạm vi "chỉ xem bộ phận mình" cộng thêm, không thay.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	const VAI_GOC = array( 'Giám đốc', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' );

	/**
	 * CÁC BỘ PHẬN CHI PHÍ — chốt DUY NHẤT, phía máy chủ.
	 *
	 * Anh Thắng 08/09/2026: *"thêm vai trò kế toán máy tự động (để chỉ thực hiện công việc bên
	 * bộ phận máy tự động)"*, và trước đó gọi tắt mảng ấy là *"mảng mtd"*.
	 *
	 * 🔴 "MÁY TỰ ĐỘNG" KHÔNG PHẢI TÊN MỚI — bên chấm công nó đã là một bộ phận thật từ lâu
	 *    (`VHCC_Luong::BP_DS`: Máy tự động · Khu vui chơi · Văn phòng · Part time), và sổ nhân
	 *    sự của anh Thắng có sẵn người mang chức vụ ấy. Bên chi phí thì chưa, nên tiền của mảng
	 *    máy tự động trước giờ nằm lẫn vào bộ phận khác.
	 *
	 * ⚠️ TRƯỚC BẢN NÀY DANH SÁCH CHỈ CÓ Ở JAVASCRIPT (`BOPHAN_LIST` trong app.html). Máy chủ
	 *    không biết bộ phận nào có thật, nên không gác được gì theo bộ phận — ô ấy chỉ là chữ.
	 *    Nay chốt ở đây và giao diện đọc xuống, để hai bên không lệch.
	 */
	const BO_PHAN_DS = array( 'Cơ sở', 'Văn phòng', 'Kỹ thuật', 'Marketing', 'Công tác', 'Setup', 'Máy tự động' );

	/**
	 * BỘ PHẬN ĐANG CÓ — đọc từ bảng cấu hình, KHÔNG còn gõ cứng.
	 *
	 * Anh Thắng 10/09/2026 muốn thêm mảng cho trang chi phí. Danh sách trước đây gõ cứng ở HAI
	 * nơi (hằng trên và `BOPHAN_LIST` trong app.html) nên mảng thứ tám nào cũng phải chờ sửa mã
	 * — mà hai nơi gõ cứng là hai nơi có thể lệch nhau.
	 *
	 * 🔴 ĐỌC THẲNG BẢNG, KHÔNG QUA `cfg_static()`. Hàm ấy gọi `vai_tuy_bien()`, mà hàm ấy gọi
	 *    `bo_phan_chuan()`, mà hàm ấy gọi hàm này — vòng gọi không đáy, trang trắng ngay lượt
	 *    tải đầu. Đọc `read()` thì cắt hẳn vòng.
	 *
	 * 🔴 RỖNG THÌ NGÃ VỀ DANH SÁCH MẶC ĐỊNH, không trả mảng rỗng. Danh sách rỗng nghĩa là
	 *    `bo_phan_chuan()` chối MỌI tên -> mọi ô Bộ phận thành trống -> trống nghĩa là "không
	 *    bó gì", tức mọi kế toán bỗng nhìn thấy sổ của mọi mảng. Hỏng theo hướng NỚI QUYỀN, và
	 *    im lặng. Bảng chưa gieo (site vừa nâng cấp, lượt tải trước khi `seed_from()` chạy) là
	 *    ca thật, không phải giả định.
	 *
	 * ⚠️ Nhớ trong một lượt chạy: `bo_phan_chuan()` bị gọi trong vòng lặp qua từng vai, từng
	 *    loại chi phí — mỗi lượt một câu đọc bảng là phí không cần thiết.
	 */
	private static $bp_memo = null;
	public static function bo_phan_ds() {
		if ( null !== self::$bp_memo ) { return self::$bp_memo; }
		$ra = array();
		foreach ( self::read( self::BP ) as $r ) {
			$t = trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) );
			if ( '' !== $t && ! in_array( $t, $ra, true ) ) { $ra[] = $t; }
		}
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG trên đường gói khởi động — ghi lại để lần sau khỏi đuổi theo.
		   Gỡ dòng này mà `kiem-goi-khoi-dong-bo-phan.php` vẫn XANH, vì `seed()` GIEO LẠI danh
		   mục bộ phận mỗi khi nó rỗng: tới lúc `bo_phan_ds()` chạy thì bảng không bao giờ trống.
		   Giữ dòng này vì nó đỡ cho những lượt gọi xảy ra TRƯỚC khi gieo (lượt kích hoạt plugin,
		   lượt nạp dữ liệu, lượt gọi thẳng API) — ở đó trả mảng rỗng là mọi ô chọn bộ phận trắng
		   trơn trong khi máy chủ vẫn nhận bảy tên ấy. */
		if ( ! $ra ) { $ra = self::BO_PHAN_DS; }
		self::$bp_memo = $ra;
		return $ra;
	}

	/** Tên bộ phận đã chuẩn hoá về đúng chữ đang khai; không khớp -> '' (= mọi bộ phận). */
	public static function bo_phan_chuan( $x ) {
		$x = trim( (string) $x );
		if ( '' === $x ) { return ''; }
		/* 🔴 `mb_strtolower`, KHÔNG PHẢI `strcasecmp`. Hàm so không phân biệt hoa thường của
		   PHP chỉ biết bảng chữ ASCII, nên "MÁY TỰ ĐỘNG" và "Máy tự động" là hai chuỗi khác
		   nhau với nó. Đúng cái bẫy đã cắn một lần ở phân quyền 25/08/2026 (`strtoupper` không
		   nâng được chữ có dấu) và làm mọi vai tiếng Việt bị chối im lặng suốt nhiều tháng.
		   Ở đây hậu quả nhẹ hơn nhưng cùng kiểu: khai hoa một chữ là ô ấy coi như để trống,
		   tức vai không bó gì và người mang nó nhìn thấy sổ của mọi mảng. */
		$k = mb_strtolower( $x );
		foreach ( self::bo_phan_ds() as $b ) {
			if ( mb_strtolower( $b ) === $k ) { return $b; }
		}
		return '';
	}

	/** Vai tự tạo: [ ['ten'=>…, 'goc'=>…], … ]. Bỏ dòng trùng tên vai gốc / Admin. */
	public static function vai_tuy_bien() {
		$out = array();
		$da  = array();
		foreach ( self::read( self::VAI ) as $r ) {
			$r = array_values( (array) $r );
			$t = trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) );
			$g = trim( (string) ( isset( $r[1] ) ? $r[1] : '' ) );
			if ( '' === $t || 'Admin' === $t || in_array( $t, self::VAI_GOC, true ) ) { continue; }
			if ( isset( $da[ mb_strtolower( $t ) ] ) ) { continue; }
			$da[ mb_strtolower( $t ) ] = 1;
			/* Kế thừa lung tung thì quy về vai thấp nhất, KHÔNG quy về vai cao. Khai sai một ô
			   mà tự động thành Quản lý là mất quyền kiểm soát; thành Nhân viên thì cùng lắm là
			   bị chặn rồi có người kêu. */
			if ( ! in_array( $g, self::VAI_GOC, true ) ) { $g = 'Nhân viên'; }
			/* ══════════════════════════════════════════════════════════════════════════════
			 * 🔴 VAI TRÒ KHÔNG CÒN MANG BỘ PHẬN — anh Thắng 21/09/2026: *"đang có sự xung đột
			 *    giữa vai trò và bộ phận, dẫn đến set cái này thì mất cái kia"*, rồi chốt:
			 *    *"bỏ vai trò đi, cho bộ phận dùng chung"*.
			 * ══════════════════════════════════════════════════════════════════════════════
			 * Cột thứ ba từng là BỘ PHẬN mà vai này bị bó vào (08/09/2026, cho vai "Kế toán
			 * máy tự động"). Nhưng bộ phận CÒN ĐƯỢC KHAI Ở HÀNG NGƯỜI DÙNG nữa — hai nơi khai
			 * cùng một sự thật, và người ta phải đoán nơi nào thắng. Ảnh anh Thắng gửi cho
			 * thấy hậu quả: một dãy vai tự tạo mang tên đúng bằng tên bộ phận ("Nhân Viên Kỹ
			 * Thuật", "Nhân Viên Marketing"…) đứng cạnh một cột Bộ phận nói y hệt.
			 *
			 * 🔴 CHỈ CÒN MỘT TRỤC: bộ phận của một người lấy từ HÀNG NGƯỜI DÙNG, và chỉ từ đó
			 *    (`VHCP_Auth::dat_vai_tro()` vốn đã đọc đúng chỗ ấy — cột của vai chưa từng
			 *    được mã chạy dùng tới, nó chỉ sống trong bài kiểm). Vai trò từ nay trả lời
			 *    đúng một câu: LÀM ĐƯỢC GÌ. Bộ phận trả lời câu kia: LÀM Ở MẢNG NÀO.
			 *
			 * ⚠️ Ô cũ trong sổ KHÔNG bị xoá, chỉ thôi đọc. Ai đã khai thì dữ liệu còn đó; lượt
			 *    Lưu bảng Vai trò kế tiếp sẽ dọn nó đi một cách tự nhiên. */
			$out[] = array( 'ten' => $t, 'goc' => $g );
		}
		return $out;
	}

	/**
	 * Danh sách vai để dựng CỘT của ma trận phân quyền.
	 *
	 * 🔴 BỐN VAI GỐC LUÔN ĐỨNG ĐẦU, ĐÚNG THỨ TỰ ĐÓ. Bảng CH_Quyen lưu theo CHỈ SỐ CỘT
	 *    (cột 2+i là vai thứ i), nên chèn một vai vào giữa là mọi ô đã tích trượt sang vai
	 *    khác — cả bảng phân quyền sai mà không có gì báo. Vai mới chỉ được NỐI VÀO CUỐI.
	 */
	public static function roles() {
		$r = self::VAI_GOC;
		foreach ( self::vai_tuy_bien() as $v ) { $r[] = $v['ten']; }
		return $r;
	}

	/** Vai này thực chất là vai gốc nào? Vai gốc / Admin / rỗng thì trả về chính nó. */
	public static function vai_goc( $ten ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten || 'Admin' === $ten || in_array( $ten, self::VAI_GOC, true ) ) { return $ten; }
		foreach ( self::vai_tuy_bien() as $v ) {
			if ( $v['ten'] === $ten ) { return $v['goc']; }
		}
		/* Vai lạ (dòng người dùng còn giữ tên vai đã xóa) -> coi như Nhân viên, không phải
		   "không rõ". Trả rỗng là lọt qua mọi phép kiểm `in_array` rỗng. */
		return 'Nhân viên';
	}

	/* ⚠️ `bo_phan_cua_nguoi( $ten_vai )` ĐÃ BỎ — anh Thắng 21/09/2026: *"bỏ vai trò đi, cho
	   bộ phận dùng chung"*. Nó tra bộ phận theo TÊN VAI, tức trục thứ hai đã gây xung đột.
	   Và nó chưa từng được mã chạy gọi tới: chỉ bài kiểm gọi, nên suốt thời gian tồn tại nó
	   canh một luật mà sản phẩm không hề thi hành. Bộ phận nay đọc thẳng từ hàng người dùng
	   (`VHCP_Auth::dat_vai_tro()`), một nơi duy nhất. */


	/** QUYEN_ACTIONS của app cũ (giữ nguyên thứ tự + mặc định). */
	public static function actions() {
		return array(
			/* HAI LOẠI ĐƠN — anh Thắng 25/08/2026: "chi phí / dự án, chứ nó không phải là gom theo".
			   Trước đây ai vào được loại nào khai CỨNG trong mã theo bộ phận. Giờ khai ở đây, đúng
			   chỗ người ta đi tìm. Vẫn CỘNG THÊM với luật bộ phận cũ chứ không thay: đổi thẳng là
			   nhân viên Kỹ thuật mất tab Dự án ngay lúc cài đè, trước khi kịp tích lại. */
			array( 'key' => 'donCoSo',   'ten' => 'Lên đơn Chi phí cơ sở (theo tuần)', 'def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'donDuAn',   'ten' => 'Lên đơn Dự án (gian thi công)',     'def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1 ) ),
			array( 'key' => 'kyTuDo',    'ten' => 'Chọn khoảng ngày tự do khi tạo đơn','def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1 ) ),
			array( 'key' => 'duyetTU',   'ten' => 'Duyệt tạm ứng',                'def' => array( 'Quản lý' => 1 ) ),
			array( 'key' => 'capTU',     'ten' => 'Cấp (gửi) tạm ứng',            'def' => array( 'Kế toán cá nhân' => 1 ) ),
			// XIN TẠM ỨNG là việc của NHÂN VIÊN: lên đơn rồi gửi lên xin. Trước đây nút gửi
			// bị gác bởi 'suaTU' — mà 'suaTU' là quyền SỬA SỐ tiền, khác hẳn. Ai bỏ tích
			// "sửa số" là nhân viên mất luôn khả năng gửi đơn của chính mình.
			array( 'key' => 'xinTU',     'ten' => 'Xin tạm ứng (gửi đơn lên)',    'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'suaTU',     'ten' => 'Sửa số tạm ứng (lúc Nháp)',    'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'suaDong',   'ten' => 'Sửa / thêm / xóa dòng chi',    'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'guiQT',     'ten' => 'Gửi quyết toán / gửi hóa đơn', 'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'gom',       'ten' => 'Gom & đẩy cho kế toán',        'def' => array( 'Quản lý' => 1 ) ),
			/* 🔴 CẢ HAI VAI KẾ TOÁN ĐỀU QUYẾT TOÁN ĐƯỢC.
			   Anh Thắng 28/08/2026 gửi hai ảnh cùng một khối "Chờ quyết toán": bên tài khoản
			   của anh có nút ✔ Duyệt, bên tài khoản kế toán chỉ còn "Chi tiết" — *"Sao tài
			   khoản kế toán lại không được quyết toán. bật kế toán quyết toán."*
			   Mặc định cũ chỉ mở cho "Kế toán cá nhân", nên người mang vai "Kế toán NCC" mở
			   đúng cái tab sinh ra cho mình mà không bấm được gì. Chữ "(cá nhân)" trong tên
			   nói về PHẦN TIỀN của đơn (đối ứng 141 cá nhân, khác phần 331 nhà cung cấp),
			   không phải nói về vai — hai thứ ấy trùng chữ nên dễ đọc nhầm, và đã đọc nhầm. */
			array( 'key' => 'xacNhanQT', 'ten' => 'Xác nhận quyết toán (phần cá nhân của đơn)',
				'def' => array( 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'duyetNCC',  'ten' => 'Duyệt NCC',                    'def' => array( 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'traDon',    'ten' => 'Trả lại đơn',                  'def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'xuatMISA',  'ten' => 'Xuất / chốt MISA',             'def' => array( 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'khongDung', 'ten' => 'Đánh dấu "Không dùng"',        'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'tichCN',    'ten' => 'Tích / bỏ tích Cá nhân↔NCC',   'def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
		);
	}

	// ---------------------------------------------------------------- đọc/ghi bảng cấu hình

	/** Mọi hàng của 1 bảng cấu hình, đã đệm đủ số cột. */
	public static function read( $bang ) {
		global $wpdb;
		$t    = VHCP_DB::t( 'cfg' );
		$n    = count( self::headers( $bang ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", $bang ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$a = json_decode( $r['cols'], true );
			if ( ! is_array( $a ) ) { $a = array(); }
			for ( $i = count( $a ); $i < $n; $i++ ) { $a[ $i ] = ''; }
			$out[] = $a;
		}
		return $out;
	}

	/**
	 * Đọc MỌI bảng cấu hình trong ĐÚNG 1 LỆNH DB -> [ tên bảng => các hàng ].
	 * Trước đây cấu hình tĩnh đọc 6 bảng = 6 lệnh, cộng 4 lệnh đếm dòng để seed.
	 */
	public static function read_all() {
		$t    = VHCP_DB::t( 'cfg' );
		$out  = array();
		foreach ( VHCP_DB::rows( "SELECT bang, cols FROM $t ORDER BY bang ASC, stt ASC, id ASC" ) as $r ) {
			$bang = (string) $r['bang'];
			$a    = json_decode( $r['cols'], true );
			if ( ! is_array( $a ) ) { $a = array(); }
			$n = count( self::headers( $bang ) );
			for ( $i = count( $a ); $i < $n; $i++ ) { $a[ $i ] = ''; }
			$out[ $bang ][] = $a;
		}
		return $out;
	}

	private static function rows_of( $all, $bang ) {
		return isset( $all[ $bang ] ) ? $all[ $bang ] : array();
	}

	/** Ghi đè toàn bộ 1 bảng cấu hình (bỏ hàng có ô đầu trống, giống _writeCfg). */
	public static function write( $bang, $rows, $snapshot = true ) {
		global $wpdb;
		$t = VHCP_DB::t( 'cfg' );
		if ( $snapshot ) {
			VHCP_Meta::set_json( 'cfg_undo', array( 'name' => $bang, 'data' => self::read( $bang ) ) );
		}
		$wpdb->delete( $t, array( 'bang' => $bang ) );
		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 DANH MỤC CƠ SỞ KHÔNG ĐƯỢC CÓ HAI DÒNG CÙNG TÊN — gác ngay ở cửa ghi.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 14/09/2026, bản Văn phòng vừa cài: *"Chi phí văn phòng, bấm lưu thì nó lưu
		 * tại tính sinh ra tiếp"* — 14 cơ sở bấm Lưu một cái thành 25, tên lặp lại.
		 *
		 * `nhan_coso_ngoai()` có chốt chống trùng, nhưng đó là cửa ĐẨY TỪ GHẾ SANG. Cửa LƯU TAY
		 * thì trước nay không ai gác: danh sách gửi lên sao thì ghi xuống vậy. Bất kể dòng trùng
		 * sinh ra từ đâu — bấm Lưu hai lượt, một lượt hút từ Ghế chồng lên hạt giống, hay trình
		 * duyệt gửi lại biểu mẫu — cửa này phải chối nó.
		 *
		 * 🔴 VÌ SAO TRÙNG TÊN LÀ HỎNG THẬT, KHÔNG PHẢI XẤU MẮT: cơ sở ở đây được nhận ra bằng
		 *    CHUỖI TÊN, không bằng mã (xem khối dài ở `coso_la()`). Hai dòng cùng tên là tiền của
		 *    một gian hàng tách làm đôi ở mọi bảng gom, và hai ô "Mã đơn vị MISA" khác nhau cho
		 *    cùng một chỗ — xuất MISA ra thì không ai biết dòng nào đúng.
		 *
		 * ⚠️ GIỮ DÒNG ĐẦU, BỎ DÒNG SAU. Dòng đầu là dòng người ta đã khai mấy ô MISA; dòng sau
		 *    gần như luôn là dòng vừa sinh thêm, còn trắng. Giữ dòng sau là xoá công khai tay.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$da_co = array();
		$i = 0;
		foreach ( (array) $rows as $r ) {
			$r = array_values( (array) $r );
			if ( ! isset( $r[0] ) || trim( (string) $r[0] ) === '' ) { continue; }
			if ( self::COSO === $bang ) {
				$k_ten = mb_strtolower( trim( (string) $r[0] ) );
				if ( isset( $da_co[ $k_ten ] ) ) { continue; }
				$da_co[ $k_ten ] = 1;
			}
			$i++;
			$wpdb->insert( $t, array( 'bang' => $bang, 'stt' => $i, 'cols' => wp_json_encode( $r ) ) );
		}
		self::clear_cache();
	}

	public static function append( $bang, $row ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'cfg' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(stt) FROM $t WHERE bang=%s", $bang ) );
		$wpdb->insert( $t, array( 'bang' => $bang, 'stt' => $max + 1, 'cols' => wp_json_encode( array_values( (array) $row ) ) ) );
		self::clear_cache();
	}

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * CƠ SỞ ĐẨY SANG TỪ MỘT PLUGIN KHÁC (hiện là plugin Ghế massage — mảng POSH)
	 *
	 * Anh Thắng 08/09/2026: *"tự đẩy lấy dữ liệu qua luôn, khi tạo cơ sở mới bên ghế, hệ thống
	 * tự đẩy cơ sở sang luôn"*.
	 *
	 * 🔴 CHỈ THÊM. KHÔNG SỬA, KHÔNG XOÁ, KHÔNG ĐÈ. Ba lý do, mỗi lý do đủ để một mình nó chốt:
	 *   · Cơ sở đã có thì mấy ô MISA (Mã đơn vị · Tên MISA · Phân loại lớn) là thứ kế toán
	 *     ngồi khai tay. Đè lên bằng dòng trắng của bên ghế là xoá công của họ, và xoá lặng lẽ.
	 *   · Đổi tên bên ghế -> thêm dòng MỚI, dòng cũ giữ nguyên. Đơn cũ vẫn mang tên cũ, mà
	 *     `cua_coso()` tra theo TÊN — xoá dòng cũ là mấy đơn ấy rơi về nhà mặc định, tức số của
	 *     POSH nhảy sang sổ K&H.
	 *   · Xoá bên ghế -> giữ nguyên bên này. Cùng lý do trên, và tiền đã chi thì không biến mất
	 *     theo cái gian hàng đã đóng.
	 *
	 * ⚠️ Ô "Đơn vị" CHỈ ĐẶT CHO DÒNG MỚI. Người ta có thể đã tự sửa đơn vị của một cơ sở cũ;
	 *    mỗi lượt đồng bộ lại kéo nó về là sửa xong hôm nay, mai lại về chỗ cũ.
	 *
	 * @return bool có thêm dòng mới hay không.
	 */
	public static function nhan_coso_ngoai( $ten, $don_vi ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten ) { return false; }
		/* So không phân biệt hoa thường: bên ghế gõ "Aeon Bình Tân", bên này có "AEON BÌNH TÂN"
		   — thêm nữa là hai dòng cho cùng một gian, và tiền của nó tách làm đôi. */
		$k = mb_strtolower( $ten );
		/* ⚠️ `read( COSO )` TRẢ THẲNG CÁC HÀNG CỦA BẢNG ẤY. Bản nháp đầu của hàm này bọc thêm
		   `rows_of( read( COSO ), COSO )` — mà `rows_of()` mong một mảng GỒM MỌI BẢNG, nên nó
		   tra khoá 'CH_CoSo' trong danh sách hàng, không thấy, và trả rỗng. Kết quả: hàm này
		   không bao giờ nhận ra cơ sở đã có, và mỗi lượt đẩy sinh thêm một dòng trùng — tiền
		   của một gian tách làm đôi ở mọi bảng gom. Bài kiểm bắt ngay lượt chạy đầu. */
		foreach ( self::read( self::COSO ) as $r ) {
			if ( mb_strtolower( trim( (string) $r[0] ) ) === $k ) { return false; }
		}
		/* Cột: Cơ sở · Mã đơn vị · Phân loại lớn · Tên MISA · Đóng cửa · Đơn vị.
		   Mấy ô giữa để trống — anh Thắng 08/09/2026: *"misa anh sẽ set sau"*. */
		self::append( self::COSO, array( $ten, '', '', '', '', VHCP_DonVi::chuan( $don_vi ) ) );
		return true;
	}

	/**
	 * Hút toàn bộ cơ sở đang có bên plugin Ghế sang danh mục này.
	 *
	 * Dùng cho lượt ĐẦU (bên ghế đã có sẵn hàng chục địa điểm trước khi có móc tự đẩy) và cho
	 * nút bấm tay ở màn Cấu hình, phòng khi một lượt đẩy nào đó rơi mất.
	 *
	 * ⚠️ GỌI QUA LỚP CỦA PLUGIN KIA, KHÔNG ĐỌC THẲNG BẢNG. Đọc thẳng `wp_vhg_coso` là ngày bên
	 *    ấy đổi sơ đồ bảng thì bên này gãy — mà gãy ở một đường chạy ngầm, không ai bấm để thấy.
	 *    Chưa cài plugin ghế thì `class_exists` false và hàm này lặng lẽ trả 0, đúng như phải thế.
	 *
	 * @return int số dòng THÊM MỚI.
	 */
	public static function hut_coso_ghe() {
		if ( ! self::lay_coso_ghe() ) { return 0; }   // bản không dùng ghế — xem hằng ấy
		if ( ! class_exists( 'VHG_May' ) || ! method_exists( 'VHG_May', 'ds_coso' ) ) { return 0; }
		$n = 0;
		foreach ( (array) VHG_May::ds_coso() as $c ) {
			$ten = isset( $c['ten'] ) ? $c['ten'] : '';
			if ( self::nhan_coso_ngoai( $ten, self::don_vi_ghe() ) ) { $n++; }
		}
		return $n;
	}

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * CƠ SỞ HÚT TỪ BÊN GHẾ THUỘC ĐƠN VỊ NÀO
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"chi phí [máy] tự động lấy cơ sở từ ghế, còn chi phí văn phòng lấy
	 * từ đó, chỉnh lại"*.
	 *
	 * 🔴 GẮN CỨNG 'POSH' LÀ ĐÚNG CHO BẢN KHU VUI CHƠI VÀ SAI CHO MỌI BẢN KHÁC. Bên KVC, gian
	 *    ghế là của nhà POSH nên cơ sở hút về phải mang tên nhà ấy. Nhưng bản Máy tự động và bản
	 *    Văn phòng có nhà riêng của mình; cơ sở hút về mà mang 'POSH' thì:
	 *      · người dùng nhà mặc định của bản ấy KHÔNG NHÌN THẤY nó (lọc theo đơn vị), nên họ mở
	 *        hộp chọn cơ sở ra thấy trống trơn dù danh mục đầy;
	 *      · và mọi báo cáo theo nhà của bản ấy hụt đúng phần tiền của những gian này.
	 *    Hỏng im lặng cả hai đường.
	 *
	 * ⚠️ MẶC ĐỊNH RỖNG NGHĨA LÀ "NHÀ MẶC ĐỊNH CỦA CHÍNH BẢN NÀY" (`VHCP_DonVi::chuan()` lo phần
	 *    ấy), chứ không phải "không có nhà". Bản gốc giữ 'POSH'; script tách đặt rỗng cho bản
	 *    vùng. Khai lại được bằng khoá `vhcp_dv_ghe` nếu ngày nào cần khác.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const DON_VI_GHE = 'POSH';

	/**
	 * BẢN NÀY CÓ LẤY CƠ SỞ TỪ BÊN GHẾ KHÔNG.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026: *"VP không dùng cơ sở ghế, ghế chỉ mỗi MTD thôi"*.
	 *
	 * 🔴 KHÔNG PHẢI MẢNG NÀO CŨNG CÓ GHẾ. Máy tự động chính là mảng ghế massage nên danh mục gian
	 *    của nó đúng bằng danh mục bên Ghế. Văn phòng thì không: gian ở đó là chỗ làm việc, không
	 *    liên quan. Hút sang là mỗi lần bên Ghế mở thêm một điểm đặt máy, danh mục Văn phòng lại
	 *    dài thêm một dòng lạ — rồi người nhập chọn nhầm, và tiền văn phòng rơi vào một gian ghế.
	 *
	 * ⚠️ TẮT LÀ TẮT CẢ BA LỐI: lượt hút tự động, tai nghe móc từ bên Ghế, và nút bấm tay. Tắt
	 *    hai để sót một thì cơ sở vẫn chảy sang, chỉ là chậm hơn và khó truy hơn.
	 *
	 * 🔴 BẢN KHU VUI CHƠI (bản gốc này) ĐÃ TẮT, TỪ 14/09/2026.
	 *    Anh Thắng gửi ảnh khối "🏢 ĐƠN VỊ POSH · 67 cơ sở" — AEON MALL, CGV, Bệnh viện 175…
	 *    tức điểm đặt ghế massage — rồi hỏi *"tại sao xóa không được"*, *"nó thuộc bộ phận
	 *    khác"*, *"bỏ vào đây là người khác khai sai"*.
	 *
	 *    Xóa KHÔNG ĐƯỢC là vì bật hằng này thì `vhcp_maybe_upgrade()` hút lại đủ 67 gian
	 *    ẤY MỖI LẦN ĐỔI PHIÊN BẢN PLUGIN — xóa xong, cài bản sau là chúng về nguyên, không
	 *    một câu báo nào. Cộng thêm hai đường nữa: móc `vhg_coso_da_luu` và nút bấm tay.
	 *
	 *    Mà gian ghế là của nhà POSH, không phải của khu vui chơi: để chúng trong danh mục ở
	 *    đây chỉ tổ làm hộp chọn cơ sở dài thêm 67 dòng để người nhập chọn nhầm — đúng câu
	 *    *"người khác khai sai"*. Anh đã chốt hướng này ngay hôm ấy: *"ghế chỉ mỗi MTD thôi"*.
	 *
	 * ⚠️ TẮT Ở ĐÂY KHÔNG XÓA DÒNG NÀO ĐANG CÓ. Nó chỉ thôi kéo thêm. 67 gian đã nằm trong sổ
	 *    vẫn ở đó cho tới khi có người xóa tay ở màn Cấu hình — và từ bản này, xóa là ở yên.
	 *
	 * ⚠️ TẮT cũng tắt luôn đầu PHÁT `vhcp_coso_posh_da_luu` (xem `bao_coso_posh_`). Hiện
	 *    KHÔNG CÓ AI NGHE hành động ấy trong cả bộ mã, nên không mất gì; ngày nào bên Ghế cần
	 *    nghe thì phải tách đầu phát ra khỏi hằng này, đừng bật lại cả ba đường hút.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 */
	/* ═══════════════════════════════════════════════════════════════════════════════════
	 * 🔴 BẬT LẠI Ở BẢN GỐC TỪ 21/09/2026 — VÀ CHỈ CHIỀU HÚT.
	 *
	 * Anh Thắng 21/09/2026: *"đẩy cơ sở bên ghế sang nhé"*, *"cơ sở bên ghế thuộc MTĐ"*, và khi
	 * em hỏi hai chiều hay một: *"bật 1 chiều từ ghế sang"*.
	 *
	 * ⚠️ ĐIỀU GÌ ĐÃ ĐỔI SO VỚI 14/09. Hôm ấy tắt vì 67 gian ghế là của NHÀ KHÁC, lạc vào danh
	 *    mục của khu vui chơi: *"nó thuộc bộ phận khác"*, *"bỏ vào đây là người khác khai sai"*. Nay
	 *    ba khối nằm chung MỘT bản cài, mỗi gian mang cột **Khối** của riêng nó, và gian ghế rơi vào
	 *    khối Máy tự động (đơn vị POSH → `mtd`). Nó không còn là dòng lạ trong danh mục người
	 *    khác nữa — nó ở đúng bảng của nó.
	 *
	 * 🔴 VÀ CÁI ĐÃ LÀM ANH KHỔ HÔM ẤY ĐÃ ĐƯỢC GỠ RIÊNG, KHÔNG ĐỂ NÓ QUAY LẠI. *"Tại sao xóa
	 *    không được"* là vì lượt hút tự động chạy LẠI MỖI PHIÊN BẢN plugin: xóa xong, cài bản sau
	 *    là 67 gian về nguyên, không một câu báo. Từ bản này lượt ấy chỉ chạy ĐÚNG MỘT LẦN cho cả
	 *    đời site (xem cờ `vhcp_hut_coso_ghe` ở `vhcp-chi-phi.php`). Xóa là ở yên; muốn hút lại thì
	 *    bấm nút 🚛 Hút cơ sở từ Ghế ở màn Cấu hình.
	 *
	 * ⚠️ HẰNG NÀY CHỈ CÒN GÁC CHIỀU HÚT (ghế → chi phí): lượt hút đầu, tai nghe móc
	 *    `vhg_coso_da_luu`, và nút bấm tay. ĐẦU PHÁT ngược lại (chi phí → ghế) nay có hằng riêng
	 *    `BAO_COSO_GHE`, và bản gốc để TẮT — anh chỉ xin một chiều. Chính chốt ⚠ cũ ở khối này đã
	 *    dặn: ngày nào cần thì TÁCH ĐẦU PHÁT ra, đừng bật chung một hằng.
	 * ════════════════════════════════════════════════════════════════════════════════════ */
	const LAY_COSO_GHE = true;

	/**
	 * BẢN NÀY CÓ BÁO NGƯỢC CƠ SỞ SANG BÊN GHẾ KHÔNG — chiều PHÁT, tách hẳn khỏi chiều hút.
	 *
	 * Anh Thắng 21/09/2026: *"bật 1 chiều từ ghế sang"* — nên bản gốc để TẮT.
	 *
	 * 🔴 TÁCH RA LÀM HAI HẰNG CHÍNH VÌ CÂU ẤY. Trước 21/09 một hằng gác cả hai chiều, nên bật
	 *    hút là bật luôn phát: mỗi lượt Lưu cấu hình sẽ bắn danh sách gian MTĐ sang plugin Ghế.
	 *    Đó là chạm vào dữ liệu của một hệ khác đang chạy thật — không được làm kèm.
	 */
	const BAO_COSO_GHE = false;

	/** Bản này có báo ngược sang Ghế không — đọc lúc chạy, cùng nếp với `lay_coso_ghe()`. */
	public static function bao_coso_ghe() {
		$v = get_option( 'vhcp_bao_coso_ghe', null );
		if ( null === $v || '' === $v ) { return self::BAO_COSO_GHE; }
		return (bool) (int) $v;
	}

	/**
	 * BẢN NÀY CÓ LẤY CƠ SỞ TỪ GHẾ KHÔNG — đọc lúc chạy, không đọc hằng thẳng.
	 *
	 * Cùng một nếp với `don_vi_ghe()` ngay dưới: hằng là NẾP CỦA BẢN, khoá cấu hình là lối
	 * đổi cho một site cụ thể mà không phải dựng bản mới.
	 *
	 * 🔴 MỌI CHỐT PHẢI GỌI HÀM NÀY, đừng đọc `self::LAY_COSO_GHE` thẳng. Đọc hằng thẳng thì
	 *    khoá cấu hình chỉ ăn ở nửa số lối, và "tắt rồi mà cơ sở vẫn chảy sang" là một câu
	 *    không ai dò ra nổi.
	 *
	 * ⚠️ CHỈ NHẬN GIÁ TRỊ ĐÃ KHAI. `get_option` trả `null` khi chưa ai đặt — lúc ấy phải theo
	 *    hằng, chứ ép `(bool) null` là mọi site đều tắt, kể cả bản Máy tự động vốn sống nhờ
	 *    đường này.
	 */
	public static function lay_coso_ghe() {
		$v = get_option( 'vhcp_lay_coso_ghe', null );
		if ( null === $v || '' === $v ) { return self::LAY_COSO_GHE; }
		return (bool) (int) $v;
	}

	/** Đơn vị gắn cho cơ sở hút từ Ghế — khai được, mặc định lấy hằng trên. */
	public static function don_vi_ghe() {
		$v = get_option( 'vhcp_dv_ghe', null );
		return is_string( $v ) ? trim( $v ) : self::DON_VI_GHE;
	}

	/**
	 * Tai nghe cho móc `vhg_coso_da_luu` của plugin Ghế — xem chỗ đăng ký ở `vhcp-chi-phi.php`.
	 *
	 * ⚠️ ĐỂ Ở ĐÂY, không viết thành hàm rời trong tệp bootstrap: tệp ấy `define` mấy hằng nên
	 *    bài kiểm không nạp lại được, và một tai nghe không bài kiểm nào chạm tới là một tai
	 *    nghe không ai biết còn đúng hay không.
	 */
	public static function moc_coso_ghe( $ten ) {
		if ( ! self::lay_coso_ghe() ) { return; }   // bản không dùng ghế — xem hằng ấy
		self::nhan_coso_ngoai( $ten, self::don_vi_ghe() );
	}

	/**
	 * Nút bấm tay ở màn Cấu hình — hút lại ngay, không phải chờ bản sau.
	 *
	 * Lượt hút tự động chỉ chạy một lần cho mỗi phiên bản plugin. Nếu một lượt đẩy rơi mất (bên
	 * ghế thêm cơ sở lúc plugin này đang tắt, chẳng hạn) thì không có đường nào tự lành trong
	 * cả tháng — nút này là đường ấy.
	 */
	public static function hut_coso_ghe_api() {
		if ( ! self::lay_coso_ghe() ) {
			return array( 'ok' => false, 'error' => 'Mảng này không lấy cơ sở từ bên Ghế. '
				. 'Gian của mảng khai thẳng ở bảng Cơ sở bên dưới.' );
		}
		if ( ! class_exists( 'VHG_May' ) ) {
			return array( 'ok' => false, 'error' => 'Chưa cài plugin Ghế massage trên site này.' );
		}
		$n = self::hut_coso_ghe();
		return array( 'ok' => true, 'them' => $n, 'thongBao' => $n
			? ( 'Đã thêm ' . $n . ' cơ sở từ bên Ghế, gắn đơn vị ' . VHCP_DonVi::chuan( self::don_vi_ghe() ) . '.' )
			: 'Danh mục đã đủ — không có cơ sở nào bên Ghế còn thiếu.' );
	}

	/**
	 * BÁO SANG PLUGIN GHẾ: mấy cơ sở thuộc đơn vị POSH vừa được lưu ở màn Cấu hình.
	 *
	 * Anh Thắng 09/09/2026: *"chỉ đẩy sang nếu nó là đơn vị posh thôi"* — chốt sau khi nghe
	 * rằng đẩy hết cả danh mục sang sẽ nhét đầy ô chọn cơ sở bên ghế bằng những chỗ không bao
	 * giờ có ghế nào.
	 *
	 * 🔴 LỌC THEO ĐƠN VỊ, KHÔNG ĐẨY CẢ BẢNG. Danh mục bên này ôm toàn bộ K&H — khu vui chơi,
	 *    văn phòng, kỹ thuật, công tác. Chỉ mảng POSH mới là chỗ có ghế.
	 *
	 * 🔴 BÁO CHO MỌI DÒNG POSH, KHÔNG CHỈ DÒNG VỪA ĐỔI. Nghe thì thừa, nhưng bên kia CHỈ THÊM
	 *    khi chưa có nên báo thừa không sinh ra gì. Còn lọc "chỉ dòng mới" thì mọi cơ sở POSH
	 *    khai TRƯỚC ngày có tính năng này vĩnh viễn không bao giờ sang, mà chẳng có gì báo là
	 *    chúng thiếu — bấm Lưu lại cũng không cứu được, vì lúc ấy chúng đâu có "vừa đổi". Đổi
	 *    lại là mỗi lượt lưu tốn thêm một câu tra cho mỗi gian POSH; lưu cấu hình là việc hoạ
	 *    hoằn, còn một gian mất tích thì im lặng mãi mãi.
	 *
	 * ⚠️ KHÔNG PHÁT TỪ `nhan_coso_ngoai()`. Hàm ấy là ĐẦU NHẬN của chiều ngược lại (ghế -> đây);
	 *    phát ở đó là hai plugin ném qua ném lại một cái tên không dứt. Đầu phát chỉ nằm ở đây,
	 *    trên đúng đường người ta bấm Lưu.
	 */
	private static function bao_coso_posh_( $rows ) {
		/* 🔴 GÁC BẰNG `bao_coso_ghe()`, KHÔNG BẰNG `lay_coso_ghe()`. Từ 21/09/2026 hai chiều có
		   hai hằng riêng — anh Thắng: *"bật 1 chiều từ ghế sang"*. Gác chung một hằng thì ngày
		   bật chiều hút là mỗi lượt Lưu cấu hình lại bắn danh sách gian sang plugin Ghế — chạm
		   vào dữ liệu của một hệ khác đang chạy thật, mà không ai xin điều đó. */
		if ( ! self::bao_coso_ghe() ) { return; }
		foreach ( (array) $rows as $r ) {
			$r  = array_values( (array) $r );
			$tn = trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) );
			$dv = VHCP_DonVi::chuan( isset( $r[5] ) ? $r[5] : '' );
			/* ⚠️ SO VỚI ĐƠN VỊ ĐANG DÙNG, không so với hằng. Bản vùng đặt đơn vị ghế là nhà mặc
			   định của nó; so với hằng 'POSH' thì đầu phát này im hẳn, và bên Ghế không bao giờ
			   biết cơ sở vừa được khai. */
			if ( '' === $tn || VHCP_DonVi::chuan( self::don_vi_ghe() ) !== $dv ) { continue; }
			do_action( 'vhcp_coso_posh_da_luu', $tn );
		}
	}

	public static function count_rows( $bang ) {
		global $wpdb;
		$t = VHCP_DB::t( 'cfg' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE bang=%s", $bang ) );
	}

	/** Sửa 1 ô (dùng cho seed bổ sung Bộ phận Kỹ thuật). */
	public static function set_cell( $bang, $index0, $col0, $value ) {
		global $wpdb;
		$t    = VHCP_DB::t( 'cfg' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", $bang ), ARRAY_A );
		if ( ! isset( $rows[ $index0 ] ) ) { return; }
		$a = json_decode( $rows[ $index0 ]['cols'], true );
		if ( ! is_array( $a ) ) { $a = array(); }
		for ( $i = count( $a ); $i <= $col0; $i++ ) { $a[ $i ] = ''; }
		$a[ $col0 ] = $value;
		$wpdb->update( $t, array( 'cols' => wp_json_encode( $a ) ), array( 'id' => $rows[ $index0 ]['id'] ) );
		self::clear_cache();
	}

	/** Cấu hình tĩnh của lượt request hiện tại (xóa cùng lúc với cache). */
	private static $memo = null;

	public static function clear_cache() {
		self::$bp_memo = null;
		self::$memo = null;
		wp_cache_delete( 'vhcp_cfgstatic', 'vhcp' );
		wp_cache_delete( 'vhcp_quyen', 'vhcp' );
		wp_cache_delete( 'vhcp_ssomap', 'vhcp' );
		delete_transient( 'vhcp_cfgstatic' );
		delete_transient( 'vhcp_quyen' );
		delete_transient( 'vhcp_ssomap' );
	}

	// ---------------------------------------------------------------- seed

	/** Bản dịch của _seedConfig(). */
	public static function seed() {
		self::seed_from( self::read_all() );
	}

	/**
	 * Như _seedConfig() nhưng dùng dữ liệu ĐÃ ĐỌC SẴN (khỏi 4 lệnh đếm dòng).
	 * Trả về true nếu có thêm/ sửa gì -> nơi gọi biết là phải đọc lại.
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 "BẢNG RỖNG" KHÔNG CÓ NGHĨA LÀ "CHƯA GIEO BAO GIỜ".
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 14/09/2026, bản Văn phòng: *"trang chi phí văn phòng không xóa được cơ sở chi phí
	 * kvc"*. Bản VP gieo sẵn 14 cơ sở của K&H; anh xoá hết rồi bấm Lưu — và chúng quay lại ngay
	 * lượt tải sau.
	 *
	 * Vì gác của hàm này chỉ hỏi "bảng có rỗng không". Rỗng thì gieo. Mà người ta vừa CỐ Ý dọn
	 * sạch cũng cho ra một bảng rỗng — không phân biệt được hai chuyện ấy thì mọi lượt dọn sạch
	 * đều bị hoàn tác, và người dọn không có cách nào thắng.
	 *
	 * ⚠️ HẠT GIỐNG LÀ MỒI CHO LƯỢT ĐẦU, KHÔNG PHẢI LUẬT VĨNH VIỄN. Nay mỗi danh mục có một dấu
	 *    "đã gieo rồi": gieo đúng một lần, sau đó bảng rỗng là ý của người dùng và phải được tôn
	 *    trọng — kể cả khi rỗng là do họ xoá nhầm, vì còn có nút Khôi phục cho chuyện ấy.
	 *
	 * 🔴 BẢNG NGƯỜI DÙNG CỐ Ý KHÔNG THEO LUẬT NÀY — xem chỗ gieo `USER` bên dưới.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	/**
	 * GIEO ĐÚNG MỘT LẦN, CẢ ĐỜI PLUGIN.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 VÌ SAO BẢN VÁ NGÀY 14/09 CHƯA CỨU ĐƯỢC ANH THẮNG — *"Tiếp tục không xóa được"*.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Bản trước đã có dấu "đã gieo rồi", nhưng đặt dấu Ở TRONG thân nhánh gieo:
	 *
	 *     if ( bảng rỗng && chưa có dấu ) { gieo...; đặt dấu; }
	 *
	 * Đọc thì thấy đúng. Nhưng trên MỘT SITE ĐÃ CÀI TỪ TRƯỚC thì bảng KHÔNG rỗng — nhánh không
	 * chạy — nên dấu KHÔNG BAO GIỜ ĐƯỢC ĐẶT. Rồi anh Thắng dọn sạch bảng:
	 *
	 *     bảng rỗng ✓  ·  chưa có dấu ✓   ->  gieo lại nguyên danh mục.
	 *
	 * Tức bản vá chỉ cứu được máy cài MỚI TINH; còn đúng những site đang đau thì lượt dọn sạch
	 * ĐẦU TIÊN vẫn bị hoàn tác y như cũ. (Lượt thứ hai mới ăn — vì lần hoàn tác ấy có đặt dấu.
	 * Nghĩa là người dùng phải xoá hai lần mới thắng, mà không ai đoán ra luật đó.)
	 *
	 * ⚠️ NAY: THẤY BẢNG CÓ DỮ LIỆU LÀ ĐÓNG DẤU NGAY, không đợi tới lượt gieo. Bảng đang có dữ
	 *    liệu nghĩa là hạt giống đã làm xong việc của nó — dù do lượt gieo cũ hay do người dùng
	 *    tự khai. Từ đó trở đi bảng rỗng là Ý CỦA NGƯỜI DÙNG và phải được tôn trọng.
	 *
	 * @param array    $all  dữ liệu đã đọc sẵn
	 * @param string   $bang tên bảng cấu hình
	 * @param string   $dau  khoá meta làm dấu "đã gieo rồi"
	 * @param callable $lam  việc gieo, chỉ chạy khi thật sự cần
	 * @return bool   true nếu có gieo (nơi gọi phải đọc lại)
	 */
	private static function gieo_mot_lan( $all, $bang, $dau, $lam ) {
		if ( VHCP_Meta::get( $dau ) ) { return false; }          // đã gieo (hoặc đã đóng dấu) rồi
		VHCP_Meta::set( $dau, '1' );                             // đóng dấu TRƯỚC, cả hai lối đi
		if ( count( self::rows_of( $all, $bang ) ) ) { return false; }   // site cũ: coi như đã gieo
		call_user_func( $lam );
		return true;
	}

	private static function seed_from( $all ) {
		$did = false;
		if ( self::gieo_mot_lan( $all, self::COSO, 'seeded_coso_v1', function () {
			foreach ( VHCP_Cfg::default_coso() as $c ) { VHCP_Cfg::append( VHCP_Cfg::COSO, array( $c, '', '', '', '', '' ) ); }
		} ) ) { $did = true; }
		if ( self::gieo_mot_lan( $all, self::NHOM, 'seeded_nhom_v1', function () {
			foreach ( VHCP_Cfg::default_nhom() as $n ) { VHCP_Cfg::append( VHCP_Cfg::NHOM, array( $n[0], $n[1], '', '' ) ); }
		} ) ) { $did = true; }
		if ( ! count( self::rows_of( $all, self::PL ) ) ) {
			self::append( self::PL, array( 'Thanh toán cá nhân', '141' ) );
			self::append( self::PL, array( 'Nhà cung cấp', '331' ) );
			$did = true;
		}
		/* Gieo BỘ PHẬN từ danh sách mặc định. Không gieo thì `bo_phan_ds()` ngã về hằng —
		   vẫn chạy đúng, nhưng màn Cấu hình bày bảng rỗng và người dùng tưởng chưa có gì. */
		if ( ! count( self::rows_of( $all, self::BP ) ) ) {
			foreach ( self::BO_PHAN_DS as $b ) { self::append( self::BP, array( $b ) ); }
			$did = true;
		}
		/* 🔴 BẢNG NGƯỜI DÙNG GIEO LẠI MỖI KHI RỖNG, CỐ Ý KHÔNG CÓ DẤU "đã gieo rồi".
		   Xoá sạch người dùng là tự khoá mình ngoài cửa VĨNH VIỄN — không còn PIN nào vào được
		   để mà sửa. Dòng Admin gieo lại là đường cứu duy nhất, và nó phải luôn có mặt.
		   ⚠️ Đổi lại: PIN mặc định nằm công khai trong mã. Đổi PIN Admin ngay sau khi cài. */
		if ( ! count( self::rows_of( $all, self::USER ) ) ) {
			self::append( self::USER, array( 'Admin', '1111', 'Admin', '', '', '', '' ) );
			$did = true;
		}
		// Bổ sung 1 lần 2 nhóm chi phí kỹ thuật (tháo dỡ / setup) — gán Bộ phận "Kỹ thuật".
		if ( ! VHCP_Meta::get( 'seeded_thaodo_setup_v2' ) ) {
			$did  = true;
			$rows = self::read( self::NHOM );
			$want = array(
				array( 'Chi phí tháo dỡ', 'canhan', '', 'Kỹ thuật' ),
				array( 'Chi phí setup lắp đặt gian hàng mới', 'canhan', '', 'Kỹ thuật' ),
			);
			foreach ( $want as $x ) {
				$idx = -1;
				foreach ( $rows as $i => $r ) {
					if ( mb_strtolower( trim( (string) $r[0] ) ) === mb_strtolower( $x[0] ) ) { $idx = $i; break; }
				}
				if ( $idx >= 0 ) { self::set_cell( self::NHOM, $idx, 3, 'Kỹ thuật' ); }
				else { self::append( self::NHOM, $x ); $rows = self::read( self::NHOM ); }
			}
			VHCP_Meta::set( 'seeded_thaodo_setup_v2', '1' );
		}

		/* LOẠI "Chi phí cơ sở" MỞ THÊM CHO BỘ PHẬN KỸ THUẬT — anh Thắng 11/09/2026:
		   *"bổ sung loại chi phí ( chi phí cơ sở )"*, sau khi ô Loại chi phí của đơn Kỹ thuật
		   chỉ xổ ra đúng hai loại tháo dỡ / setup.

		   Kỹ thuật nay lên được ĐƠN CHI PHÍ CƠ SỞ (một đơn nhiều gian), mà loại chi phí đúng
		   cho nó lại đang khai riêng cho bộ phận khác, nên ô chọn của họ không có dòng nào
		   dùng được — và không có gì trên màn nói vì sao.

		   🔴 CỘNG THÊM BỘ PHẬN, KHÔNG THAY. Ghi đè cột ấy thành "Kỹ thuật" là cắt loại này khỏi
		      chính những người đang dùng nó hằng tuần.
		   ⚠️ LOẠI CHƯA KHAI BỘ PHẬN NÀO THÌ ĐỂ YÊN: bỏ trống nghĩa là DÙNG CHUNG cho mọi bộ
		      phận (xem `loai_thuoc_bo_phan()`), nên điền "Kỹ thuật" vào là BÓ nó lại — đúng
		      ngược điều đang cần. */
		if ( ! VHCP_Meta::get( 'seeded_coso_kythuat_v1' ) ) {
			$did  = true;
			$rows = self::read( self::LOAI );
			foreach ( $rows as $i => $r ) {
				$r = array_values( (array) $r );
				if ( mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) !== mb_strtolower( 'Chi phí cơ sở' ) ) { continue; }
				$bp = self::bo_phan_tach( isset( $r[4] ) ? $r[4] : '' );
				if ( count( $bp ) && ! in_array( 'Kỹ thuật', $bp, true ) ) {
					$bp[] = 'Kỹ thuật';
					self::set_cell( self::LOAI, $i, 4, implode( ', ', $bp ) );
				}
				break;
			}
			VHCP_Meta::set( 'seeded_coso_kythuat_v1', '1' );
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * LẤP KHỐI CHO MỌI LOẠI CHI PHÍ CÓ TỪ TRƯỚC — chạy MỌI LƯỢT, không phải một lần.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Anh Thắng 21/09/2026: *"chỗ loại chi phí, chia ra 3 bảng của 3 khối, để tránh dùng
		 * chung"*. Từ bản này mỗi loại thuộc đúng một khối.
		 *
		 * 🔴 Ô KHỐI RỖNG LÀ LOẠI KHÔNG BẢNG NÀO CHỨA — nó biến mất khỏi cả ba bảng, khỏi ô chọn
		 *    lúc nhập đơn, và khỏi mọi cột mã ở bảng dưới. Tiền vẫn nằm trong sổ mang tên loại
		 *    ấy, mà màn hình thì như chưa từng có nó. Đúng cái bẫy `lap_khoi()` của bảng đơn.
		 *
		 * ⚠️ VÌ SAO KHÔNG DÙNG `gieo_mot_lan()`: dấu "đã gieo" chỉ nói lượt trước đã chạy, không
		 *    nói HÔM NAY còn ô rỗng nào không. Một dòng thêm tay qua đường nạp dữ liệu, hay một
		 *    lượt khôi phục bảng cũ, là lại có ô rỗng — mà dấu thì đã đóng. Quét lại mỗi lượt
		 *    rẻ hơn nhiều so với một loại chi phí tàng hình.
		 *
		 * Khối mặc định là khối của CHÍNH BẢN ĐANG CHẠY (`VHCP_DB::khoi()`): dữ liệu đang có ở
		 * kho nào thì thuộc khối ấy — bản gốc là `kvc`, bản vùng là mã vùng của nó.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 KHÔNG CÓ LƯỢT TỰ XOÁ VAI TRÒ Ở ĐÂY. ĐỪNG THÊM LẠI.
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * Bản 1.229.0 từng có: nạp plugin lên là nó tự dời người về vai gốc rồi dọn sạch bảng
		 * vai tự tạo. Anh Thắng chối ngay: *"như xóa vai trò là đang sai"* — sau khi chính anh
		 * bảo *"xóa luôn mấy vai trò đó đi"*. Hai câu ấy không mâu thuẫn: anh muốn mấy vai KIA
		 * biến khỏi ô chọn, chứ không muốn MÁY tự ý đụng vào bảng phân quyền.
		 *
		 * 🔴 VÌ SAO MỘT LƯỢT DỌN TỰ ĐỘNG LÀ SAI, kể cả khi nó "đúng ý":
		 *   · Nó chạy lúc NẠP PLUGIN, không phải lúc người ta bấm nút. Không ai kịp xem trước,
		 *     không ai bấm đồng ý, và không có nút hoàn tác.
		 *   · Nó đụng vào hai bảng cùng lúc — vai trò VÀ vai của từng tài khoản. Sai một nước
		 *     là quyền của cả công ty lệch đi, mà cái lệch ấy im lặng.
		 *   · Bảng phân quyền `CH_Quyen` lưu theo CHỈ SỐ CỘT, mỗi vai một cột. Xoá vai là cột
		 *     ấy mồ côi — thứ chỉ lộ ra nhiều ngày sau, ở một màn khác.
		 *
		 * ⚠️ MUỐN DỌN THÌ DỌN BẰNG TAY, ở bảng 🎭 Vai trò: xoá dòng rồi bấm Lưu. Đường ấy đã có
		 *    sẵn chốt đếm-trước-rồi-hỏi (liệt kê ai đang mang vai sắp xoá). Người bấm là người
		 *    quyết, và họ nhìn thấy cái giá trước khi trả.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */

		$rows_l = self::read( self::LOAI );
		$khoi_n = VHCP_DB::khoi();
		foreach ( $rows_l as $i => $r ) {
			$r = array_values( (array) $r );
			if ( '' === trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) { continue; }
			if ( '' !== trim( (string) ( isset( $r[9] ) ? $r[9] : '' ) ) ) { continue; }
			$did = true;
			self::set_cell( self::LOAI, $i, 9, $khoi_n );
		}

		/* VAI "KẾ TOÁN MÁY TỰ ĐỘNG" — ĐÃ BỎ (anh Thắng 14/09/2026: *"bỏ cái này, vì
		   phân quyền trang nên không cần nữa"*).

		   Vai này sinh ra ngày 08/09 để bó một kế toán vào riêng bộ phận "Máy tự động", hồi
		   ba mảng còn chung MỘT trang chi phí. Nay mỗi mảng đã có TRANG RIÊNG — mỗi trang một
		   bộ bảng và một sổ người dùng riêng — vào đúng trang là đã chỉ thấy mảng ấy, nên vai bó
		   bộ phận không còn việc gì.

		   (Không viết thẳng đường dẫn của các mảng ra đây: bài kiểm `kiem-tach-ban-vung.php` chốt
		    bản GỐC không được dính một chữ nào của bản vùng, kể cả trong chú thích — dính là dấu
		    hiệu script tách đã lây ngược, nên phép ấy cố ý không nể chú thích.)

		   ⚠️ Chỉ bỏ việc TỰ DỰNG SẴN, không tự đi xoá: trên bản đang chạy có thể đang có tài khoản
		      mang vai này, xoá ngầm là họ mất quyền giữa chừng mà không ai biết vì sao. Anh bấm ✕
		      rồi Lưu là nó đi hẳn — cờ đã seed ('seeded_vai_...') đã đóng nên không có gì dựng lại.
		      Cơ chế vai tự tạo có bó bộ phận VẪN GIỮ (`bo_phan_cua_nguoi()`); chỉ mỗi vai dựng sẵn
		      này là thôi. */

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * Danh mục LOẠI CHI PHÍ: lần đầu dựng từ nhóm mặt hàng đang có (giữ luôn TK Nợ + Bộ
		 * phận) để khỏi phải khai lại; sau đó sửa độc lập trong tab ⚙️ Cấu hình.
		 *
		 * 🔴 CHỈ DỰNG MỘT LẦN — anh Thắng 14/09/2026: *"bấm dọn mã thì được, chứ bấm x thì lại
		 *    không được"*. Chi tiết ấy chính là manh mối, và nó chỉ thẳng vào đây:
		 *
		 *      · "🧹 Dọn loại chưa khai mã" GIỮ LẠI loại đã khai mã -> bảng KHÔNG rỗng -> nhánh
		 *        này không chạy -> xoá ăn.
		 *      · Bấm ✕ từng dòng cho tới hết rồi Lưu -> bảng RỖNG -> nhánh này dựng lại nguyên
		 *        danh mục từ NHOM -> "không xoá được".
		 *
		 *    Hai nút, hai kết quả, cùng một nguyên nhân: gác chỉ hỏi "bảng có rỗng không", mà
		 *    người vừa CỐ Ý dọn sạch cũng cho ra một bảng rỗng.
		 *
		 * ⚠️ Đây là bảng THỨ BA mắc cùng một bệnh (sau COSO và NHOM, vá cùng ngày). Lần trước
		 *    vá hai chỗ mà bỏ sót chỗ này — nên nay mỗi nhánh "rỗng thì dựng lại" đều phải có
		 *    dấu riêng, và bài kiểm canh cả ba.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		if ( self::gieo_mot_lan( $all, self::LOAI, 'seeded_loai_v1', function () {
			// Đọc THẲNG từ bảng, không dùng bản đã nạp sẵn: bảng Nhóm có thể vừa được gieo
			// ngay trong lượt này, lúc ấy bản nạp sẵn còn rỗng.
			foreach ( VHCP_Cfg::read( VHCP_Cfg::NHOM ) as $r ) {
				$r = array_values( (array) $r );
				if ( trim( (string) $r[0] ) === '' ) { continue; }
				VHCP_Cfg::append( VHCP_Cfg::LOAI, array( $r[0], isset( $r[2] ) ? $r[2] : '', '', '', isset( $r[3] ) ? $r[3] : '', '' ) );
			}
		} ) ) { $did = true; }
		return $did;
	}

	// ---------------------------------------------------------------- cấu hình tĩnh

	/** Bản dịch của _cfgStatic() (cache 5 phút như CacheService). */
	public static function cfg_static() {
		if ( is_array( self::$memo ) ) { return self::$memo; }
		$hit = get_transient( 'vhcp_cfgstatic' );
		if ( is_array( $hit ) ) { self::$memo = $hit; return $hit; }

		$all = self::read_all();
		if ( self::seed_from( $all ) ) { $all = self::read_all(); }   // chỉ đọc lại khi thực sự có seed

		$out = array( 'coso' => array(), 'nhom' => array(), 'loaiChiPhi' => array(), 'tkNoMatrix' => array(), 'phanloai' => array(), 'dtCfg' => array(), 'qr' => array( 'stk' => '', 'bank' => '', 'ten' => '' ) );

		foreach ( self::rows_of( $all, self::COSO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			// MÃ SỐ bị bảng tính thêm đuôi ".0" ("64196.0", "6329.0"): mã đó không khớp hệ
			// thống tài khoản và xuất MISA ra sai. Rửa ngay lúc ĐỌC nên dòng đã nạp lệch tự
			// về đúng, khỏi phải sửa tay từng ô.
			$out['coso'][] = array( 'ten' => $r[0], 'maDonVi' => VHCP_Util::ma_so( $r[1] ), 'phanLoaiLon' => $r[2], 'tenMisa' => $r[3], 'dongCua' => isset( $r[4] ) ? (string) $r[4] : '',
				'donVi' => VHCP_DonVi::chuan( isset( $r[5] ) ? $r[5] : '' ),
				/* TỈNH / THÀNH — anh Thắng 11/09/2026: *"thêm cột phân loại theo tỉnh"*.
				   ⚠️ KHÔNG qua `chuan()` như cột Đơn vị: để trống là CHƯA KHAI, không phải
				      "về tỉnh mặc định" — gán bừa một tỉnh là báo cáo theo vùng sai ngay. */
				'tinh' => trim( (string) ( isset( $r[6] ) ? $r[6] : '' ) ) );
		}
		foreach ( self::rows_of( $all, self::NHOM ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['nhom'][] = array( 'ten' => $r[0], 'loai' => ( $r[1] !== '' ? $r[1] : 'canhan' ), 'tkNo' => VHCP_Util::ma_so( $r[2] ), 'boPhan' => $r[3] );
		}
		foreach ( self::rows_of( $all, self::LOAI ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			/* Cột 9 `donVi` thêm 12/09/2026 — anh Thắng: *"Đối với POSH sẽ có cột Chi Phí Khác,
			   Chi Phí Chung, Chi Phí Cơ Sở, Chi Phí Setup"*. Mỗi nhà một bộ loại chi phí riêng;
			   bảng 81 mảng của POSH trước nay phải bày cả chín cột của KVC, toàn dấu "—".
			   ⚠️ `isset()` cho cột mới: mọi dòng cũ chỉ có 8 cột, đọc thẳng `$r[8]` là cảnh báo
			      PHP ở MỌI lượt nạp cấu hình. Rỗng = mọi đơn vị, giữ đúng hành vi cũ. */
			/* `khoi` rỗng = loại có từ trước lượt chia ba bảng; `lap_khoi_loai()` lấp nốt ngay
			   lúc nạp cấu hình, nên ô rỗng chỉ tồn tại đúng một khoảnh khắc. Vẫn phải rào
			   `isset()`: dòng vừa thêm tay có thể chưa đủ ô. */
			$out['loaiChiPhi'][] = array( 'ten' => $r[0], 'tkNo' => VHCP_Util::ma_so( $r[1] ), 'tkCo' => VHCP_Util::ma_so( $r[2] ), 'maDt' => VHCP_Util::ma_so( $r[3] ), 'boPhan' => $r[4], 'note' => $r[5], 'tenMisa' => isset( $r[6] ) ? $r[6] : '', 'loaiTt' => isset( $r[7] ) ? $r[7] : '', 'donVi' => isset( $r[8] ) ? $r[8] : '', 'khoi' => isset( $r[9] ) ? $r[9] : '', 'vaiTro' => isset( $r[10] ) ? $r[10] : '', 'dauMuc' => isset( $r[11] ) ? $r[11] : '', 'cha' => isset( $r[12] ) ? $r[12] : '' );
		}
		foreach ( self::rows_of( $all, self::TKNO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['tkNoMatrix'][] = array( 'nhom' => $r[0], 'pll' => $r[1], 'tkNo' => VHCP_Util::ma_so( $r[2] ) );
		}
		foreach ( self::rows_of( $all, self::PL ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['phanloai'][] = array( 'ten' => $r[0], 'tkCo' => $r[1] );
		}
		foreach ( self::rows_of( $all, self::DT ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['dtCfg'][] = array( 'ten' => $r[0], 'ma' => $r[1], 'loai' => $r[2] );
		}
		foreach ( self::rows_of( $all, self::QR ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['qr'][ $r[0] ] = $r[1];
		}

		$out['sso'] = array();
		foreach ( self::rows_of( $all, self::SSO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['sso'][] = array( 'email' => $r[0], 'role' => $r[1], 'coso' => $r[2] );
		}
		/* 🔴 LẤY TỪ `bo_phan_ds()`, KHÔNG đọc thẳng `rows_of($all, BP)`. Hàm ấy mới có nhánh
		   "rỗng thì ngã về mặc định" — đọc thẳng là site chưa gieo sẽ gửi xuống màn một danh
		   sách rỗng, ô chọn Bộ phận trắng trơn, mà máy chủ thì vẫn nhận 7 tên cũ. Hai bên lệch
		   nhau đúng kiểu bản này sinh ra để bỏ. */
		$out['boPhanDs'] = self::bo_phan_ds();
		$out['users'] = array();
		foreach ( self::rows_of( $all, self::USER ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			// Dòng cũ đã nạp lệch (PIN "2222.0") thì rửa ngay lúc ĐỌC, khỏi phải sửa tay
			// từng người mới đăng nhập lại được.
			/* ⚠️ GÁC `isset` CHO HAI Ô CUỐI. Bảng người dùng đang chạy chỉ có 7 ô; đọc thẳng
			   `$r[7]` là cảnh báo tràn nhật ký lỗi ở MỌI lượt tải trang, và trang trắng nếu
			   site bật WP_DEBUG — cho một cột vừa mới thêm mà chưa ai kịp khai. */
			$out['users'][] = array( 'ten' => $r[0], 'pin' => VHCP_Util::pin_sach( $r[1] ), 'vaiTro' => ( $r[2] !== '' ? $r[2] : 'Nhân viên' ), 'coso' => $r[3], 'tkCo' => VHCP_Util::ma_so( $r[4] ), 'maDt' => VHCP_Util::ma_so( $r[5] ), 'boPhan' => $r[6],
				'donVi' => isset( $r[7] ) ? trim( (string) $r[7] ) : '',
				'xemDonVi' => isset( $r[8] ) ? trim( (string) $r[8] ) : '',
				/* MÃ NV — khoá thứ hai, xem khối dài ở `VHCP_Auth::login()`. Ô cuối cùng nên
				   dòng cũ chín ô không có nó; `isset` lo phần ấy. */
				'maNv' => isset( $r[9] ) ? VHCP_Util::ma_so( $r[9] ) : '',
				/* KHỐI — anh Thắng 21/09/2026: *"chỗ đơn vị thay bằng khối, tích nếu 1 người làm 2
				   khối thì chọn 2, vì có thể nv chung sẽ làm việc với 2 khối"*. Chuỗi ngăn phẩy,
				   VÀ Ô TRỐNG LÀ NGHĨA CHUNG: chưa khai thì ngã về ánh xạ cũ từ đơn vị, không phải
				   "không thuộc khối nào" — xem `VHCP_DonVi::khoi_xem_duoc()`. */
				'khoi' => isset( $r[10] ) ? trim( (string) $r[10] ) : '' );
		}

		// Bảng tra nhanh cho việc chốt TK Nợ: cơ sở -> phân loại lớn, và
		// ma trận [loại chi phí][phân loại lớn] -> TK Nợ (khóa đã hạ chữ thường).
		$out['cosoPll'] = array();
		/* Cơ sở -> ĐƠN VỊ. Tra nhanh, khoá đã hạ chữ thường — mọi màn hỏi "dòng chi này của
		   bên nào" đều đi qua bảng này, xem `VHCP_DonVi::cua_coso()`. */
		$out['cosoDonVi'] = array();
		foreach ( $out['coso'] as $x ) {
			$k = mb_strtolower( trim( (string) $x['ten'] ) );
			if ( $k !== '' ) {
				$out['cosoPll'][ $k ]   = trim( (string) $x['phanLoaiLon'] );
				$out['cosoDonVi'][ $k ] = VHCP_DonVi::chuan( isset( $x['donVi'] ) ? $x['donVi'] : '' );
			}
		}
		// Một ô có thể khai NHIỀU mã (cách nhau bởi "|") khi cùng một tên gọi chi phí ở
		// cùng một mảng lại hạch toán vào 2 tài khoản khác nhau. Khi đó app KHÔNG tự chọn:
		// ô "Loại chi phí" lúc nhập sẽ tách thành từng dòng mã để người nhập chỉ đúng một.
		$out['tkNoMx'] = array();
		foreach ( $out['tkNoMatrix'] as $x ) {
			$kn = mb_strtolower( trim( (string) $x['nhom'] ) );
			$kp = mb_strtolower( trim( (string) $x['pll'] ) );
			if ( $kn === '' || $kp === '' ) { continue; }
			$ds = array();
			foreach ( explode( '|', (string) $x['tkNo'] ) as $m ) {
				$m = trim( $m );
				if ( $m !== '' && ! in_array( $m, $ds, true ) ) { $ds[] = $m; }
			}
			if ( ! count( $ds ) ) { continue; }
			$out['tkNoMx'][ $kn ][ $kp ] = $ds;
		}

		set_transient( 'vhcp_cfgstatic', $out, 300 );
		self::$memo = $out;
		return $out;
	}

	/**
	 * getConfig(): cấu hình tĩnh + đối tượng hợp nhất từ dòng chi phí + bảng SSO.
	 * $cp_data = mảng dòng chiphi đã đọc sẵn (tiết kiệm 1 lượt đọc như app cũ).
	 */
	public static function get_config( $cp_data = null ) {
		$s  = self::cfg_static();
		$dt = array();
		$seen = array();
		foreach ( $s['dtCfg'] as $d ) {
			$dt[] = array( 'ten' => $d['ten'], 'ma' => $d['ma'], 'loai' => $d['loai'] );
			$seen[ mb_strtolower( (string) $d['ten'] ) ] = 1;
		}
		if ( $cp_data === null ) { $cp_data = VHCP_Don::cp_rows(); }
		foreach ( $cp_data as $r ) {
			$t = trim( (string) $r['doi_tuong'] );
			if ( $t === '' ) { continue; }
			$k = mb_strtolower( $t );
			if ( isset( $seen[ $k ] ) ) { continue; }
			$seen[ $k ] = 1;
			$dt[] = array( 'ten' => $t, 'ma' => '', 'loai' => ( $r['phan_loai_tt'] === 'Nhà cung cấp' ? 'NCC' : 'NV' ) );
		}
		$sso = isset( $s['sso'] ) ? $s['sso'] : array();
		return array(
			'vaiGoc'     => self::VAI_GOC,
			'vaiTro'     => self::vai_tuy_bien(),
			/* Danh sách BỘ PHẬN — giao diện dựng ô chọn từ đây, không gõ cứng lại. Gõ cứng ở
			   hai nơi là hai nơi lệch nhau: máy chủ chối một tên mà ô chọn vẫn bày ra nó. */
			'boPhanDs'   => isset( $s['boPhanDs'] ) ? $s['boPhanDs'] : self::BO_PHAN_DS,
			/* 🔴 DANH MỤC CƠ SỞ GỬI XUỐNG ĐÃ LỌC THEO ĐƠN VỊ.
			   Anh Thắng 08/09/2026: *"Mỗi đơn vị tách 1 bảng riêng, để kế toán bộ phận đó tự
			   nhìn thấy cơ sở của mình và tự thêm sửa mã misa"*. Kế toán POSH mở màn Cấu hình
			   ra chỉ thấy gian POSH, và tự khai mã MISA cho chúng.

			   ⚠️ ĐƯỜNG LƯU PHẢI HỢP NHẤT, KHÔNG GHI ĐÈ — xem chốt 🔴 ở `save_config()`. Lọc
			      một chiều mà quên chiều kia là kế toán POSH bấm Lưu một cái, bảng gửi lên
			      đúng một dòng, và toàn bộ cơ sở K&H biến mất. */
			'coso'       => self::coso_theo_don_vi( $s['coso'] ),
			'nhom'       => $s['nhom'],
			'loaiChiPhi' => isset( $s['loaiChiPhi'] ) ? $s['loaiChiPhi'] : array(),
			'tkNoMatrix' => $s['tkNoMatrix'],
			'phanloai'   => $s['phanloai'],
			'doiTuong'   => $dt,
			'qr'         => $s['qr'],
			'sso'        => $sso,
		);
	}

	/**
	 * Lọc danh mục cơ sở theo đơn vị người đang gọi. Xem cả -> trả nguyên.
	 *
	 * Tách thành hàm riêng vì cả đường ĐỌC lẫn đường GHI đều cần đúng một phép chia này; hai
	 * bản chép tay là sớm muộn lệch, mà lệch ở đây nghĩa là ghi đè mất dữ liệu của bên kia.
	 */
	public static function coso_theo_don_vi( $ds ) {
		if ( ! class_exists( 'VHCP_DonVi' ) || null === VHCP_DonVi::xem_duoc() ) { return $ds; }
		$ra = array();
		foreach ( (array) $ds as $x ) {
			if ( VHCP_DonVi::duoc_xem( isset( $x['donVi'] ) ? $x['donVi'] : '' ) ) { $ra[] = $x; }
		}
		return $ra;
	}

	public static function save_config( $cfg ) {
		$cfg = (array) $cfg;
		$g   = function ( $row, $key ) { return isset( $row[ $key ] ) ? (string) $row[ $key ] : ''; };

		/* ==================================================================================
		 * 🔴 CHỐT CHẶN XÓA TRẮNG BẢNG NGƯỜI DÙNG — kiểm TRƯỚC KHI ghi bất cứ bảng nào.
		 *
		 * Chuyện đã xảy ra thật ngày 25/08/2026: màn Cấu hình mở ra, bảng "Người dùng & Phân
		 * quyền" trống trơn, toàn bộ phân quyền từng nhân viên biến mất.
		 *
		 * Đường đi của tai nạn: `getUsers` lỗi một nhịp (mạng, 403 của tường lửa, hết phiên)
		 * -> giao diện nuốt lỗi và vẽ bảng RỖNG, trông y hệt "chưa khai ai" -> người ta bấm
		 * 💾 Lưu -> gửi lên `users: []` -> ghi đè sạch bảng.
		 *
		 * Một lần bấm nhầm không được phép xóa hết tài khoản của cả công ty. Muốn xóa thật
		 * thì phải nói rõ bằng cờ `usersXoaHet` — cờ đó chỉ đặt được khi người ta bấm qua một
		 * hộp xác nhận riêng, không phải nút Lưu thường ngày.
		 *
		 * Kiểm ở ĐẦU hàm, không phải tới lượt ghi bảng users: các bảng khác đã ghi xong rồi
		 * mới báo lỗi là nửa lưu nửa không, còn tệ hơn.
		 * ================================================================================== */
		if ( isset( $cfg['users'] ) && is_array( $cfg['users'] ) ) {
			$co_ten = 0;
			foreach ( $cfg['users'] as $x0 ) {
				$x0 = (array) $x0;
				if ( isset( $x0['ten'] ) && trim( (string) $x0['ten'] ) !== '' ) { $co_ten++; }
			}
			$dang_co = self::count_rows( self::USER );
			if ( 0 === $co_ten && $dang_co > 0 && empty( $cfg['usersXoaHet'] ) ) {
				return VHCP_Util::err(
					'Không lưu: danh sách người dùng gửi lên đang RỖNG trong khi bảng có ' . $dang_co
					. ' người. Nhiều khả năng bảng chưa tải xong. Tải lại trang rồi thử lại — '
					. 'dữ liệu cũ vẫn còn nguyên.'
				);
			}
			/* ══════════════════════════════════════════════════════════════════════════════
			 * HAI DÒNG CÙNG MỘT TÊN, HOẶC CÙNG MỘT MÃ NV — CHỐI THẲNG.
			 *
			 * 🔴 TÊN: `user_by_token()` duyệt bảng người dùng và lấy dòng ĐẦU TIÊN khớp tên,
			 *    rồi `break`. Hai dòng cùng tên nghĩa là dòng thứ hai không bao giờ tới lượt:
			 *    khai cho người ấy vai gì, cơ sở nào cũng vô nghĩa, mà màn hình thì vẫn bày ra
			 *    đủ hai dòng như thể cả hai đều đang chạy. Và vì TÊN là khoá nối mọi đơn cũ,
			 *    hai người trùng tên thật sẽ dùng chung sổ đơn của nhau.
			 *
			 * 🔴 MÃ NV: đó là thứ `login()` dùng để nối một hàng bên Nhân sự vào đúng dòng bên
			 *    Chi phí. Hai dòng cùng mã là câu hỏi "PIN này của ai" có hai đáp án — và một
			 *    câu hỏi đăng nhập có hai đáp án thì phải chối, không được đoán.
			 *
			 * ⚠️ Ô RỖNG KHÔNG TÍNH LÀ TRÙNG. Phần lớn tài khoản chưa khai mã NV; gom chúng lại
			 *    thành "mười người trùng mã rỗng" là chặn luôn mọi lượt Lưu.
			 * ══════════════════════════════════════════════════════════════════════════════ */
			$da_ten = array(); $da_mnv = array();
			foreach ( $cfg['users'] as $x0 ) {
				$x0 = (array) $x0;
				$t0 = mb_strtolower( trim( (string) ( isset( $x0['ten'] ) ? $x0['ten'] : '' ) ) );
				if ( '' !== $t0 ) {
					if ( isset( $da_ten[ $t0 ] ) ) {
						return VHCP_Util::err( 'Không lưu: có HAI dòng cùng tên "'
							. trim( (string) $x0['ten'] ) . '". Tên là thứ nối người này với đơn '
							. 'họ đã lập, nên hai dòng cùng tên sẽ dùng chung sổ đơn của nhau — '
							. 'và chỉ dòng trên cùng có tác dụng. Xoá dòng thừa, hoặc nếu đúng là '
							. 'hai người khác nhau thì phải đổi tên một người cho khác đi.' );
					}
					$da_ten[ $t0 ] = 1;
				}
				$m0 = trim( (string) ( isset( $x0['maNv'] ) ? $x0['maNv'] : '' ) );
				if ( '' !== $m0 ) {
					$mk = mb_strtolower( $m0 );
					if ( isset( $da_mnv[ $mk ] ) ) {
						return VHCP_Util::err( 'Không lưu: mã NV "' . $m0 . '" đang khai cho HAI '
							. 'người (' . $da_mnv[ $mk ] . ' và ' . trim( (string) $x0['ten'] ) . '). '
							. 'Mã NV là thứ dùng để nối PIN bên Nhân sự sang đây, nên mỗi mã chỉ '
							. 'được thuộc về một người.' );
					}
					$da_mnv[ $mk ] = trim( (string) ( isset( $x0['ten'] ) ? $x0['ten'] : '?' ) );
				}
			}

			/* Còn dữ liệu thì cất một bản trước khi đè. Bản lưu của `cfg_undo` chỉ có MỘT ô và
			   bị bảng ghi sau giành mất, nên không tin được cho việc này. */
			if ( $dang_co > 0 ) { self::sao_luu_users(); }
		}

		if ( isset( $cfg['coso'] ) && is_array( $cfg['coso'] ) ) {
			$rows = array();
			// Giữ lại ngày ĐÓNG CỬA khi dữ liệu gửi lên không mang theo — bảng cơ sở trên
			// giao diện không có cột đó, lưu bảng là mất trạng thái đóng của mọi gian.
			$dong_cu = array();
			/* Cột ĐƠN VỊ giữ y hệt lý do: một bản giao diện cũ (hoặc một lượt nạp .csv thiếu
			   cột) gửi lên bảng cơ sở không có ô ấy, mà ghi đè bằng rỗng là MỌI cơ sở POSH
			   lặng lẽ về K&H — tức kế toán K&H nhìn thấy toàn bộ chi phí của POSH, đúng thứ
			   đang phải tách. Không có ô thì giữ nguyên ô đang lưu. */
			/* Cột TỈNH cũng vậy — thêm sau, nên mọi bản giao diện cũ và mọi tệp .csv cũ đều
			   không có ô ấy. Ghi đè bằng rỗng là xoá sạch phân loại vùng vừa khai cả buổi. */
			$dv_cu = array(); $tinh_cu = array();
			foreach ( self::read( self::COSO ) as $r0 ) {
				$r0 = array_values( (array) $r0 );
				$t0 = isset( $r0[0] ) ? mb_strtolower( trim( (string) $r0[0] ) ) : '';
				if ( $t0 === '' ) { continue; }
				if ( isset( $r0[4] ) && trim( (string) $r0[4] ) !== '' ) { $dong_cu[ $t0 ] = (string) $r0[4]; }
				if ( isset( $r0[5] ) && trim( (string) $r0[5] ) !== '' ) { $dv_cu[ $t0 ] = (string) $r0[5]; }
				if ( isset( $r0[6] ) && trim( (string) $r0[6] ) !== '' ) { $tinh_cu[ $t0 ] = (string) $r0[6]; }
			}
			foreach ( $cfg['coso'] as $x ) {
				$x  = (array) $x;
				$tn = $g( $x, 'ten' );
				$dc = $g( $x, 'dongCua' );
				if ( $dc === '' && ! array_key_exists( 'dongCua', $x ) ) {
					$k0 = mb_strtolower( trim( $tn ) );
					if ( isset( $dong_cu[ $k0 ] ) ) { $dc = $dong_cu[ $k0 ]; }
				}
				$dv = $g( $x, 'donVi' );
				if ( $dv === '' && ! array_key_exists( 'donVi', $x ) ) {
					$k1 = mb_strtolower( trim( $tn ) );
					if ( isset( $dv_cu[ $k1 ] ) ) { $dv = $dv_cu[ $k1 ]; }
				}
				$tinh = $g( $x, 'tinh' );
				if ( $tinh === '' && ! array_key_exists( 'tinh', $x ) ) {
					$k2 = mb_strtolower( trim( $tn ) );
					if ( isset( $tinh_cu[ $k2 ] ) ) { $tinh = $tinh_cu[ $k2 ]; }
				}
				$rows[] = array( $tn, VHCP_Util::ma_so( $g( $x, 'maDonVi' ) ), $g( $x, 'phanLoaiLon' ), $g( $x, 'tenMisa' ), $dc, $dv, $tinh );
			}

			/* ══════════════════════════════════════════════════════════════════════════════
			 * 🔴 HỢP NHẤT, KHÔNG GHI ĐÈ — GIỮ NGUYÊN CƠ SỞ CỦA ĐƠN VỊ NGƯỜI NÀY KHÔNG THẤY.
			 *
			 * Từ 08/09/2026 màn Cấu hình chỉ bày cho mỗi người danh mục cơ sở của ĐƠN VỊ họ.
			 * Nên bảng gửi lên KHÔNG PHẢI toàn bộ danh mục — kế toán POSH gửi lên đúng mấy
			 * gian POSH. Ghi đè bằng chừng ấy dòng là toàn bộ cơ sở K&H biến mất trong một
			 * lần bấm Lưu, và cùng với chúng là mã MISA, phân loại lớn, ngày đóng gian.
			 *
			 * Đây đúng loại tai nạn đã xảy ra thật với bảng người dùng ngày 25/08/2026 (xem
			 * chốt 🔴 ở đầu hàm). Lần đó là do giao diện vẽ bảng rỗng; lần này thì bảng rỗng
			 * là ĐÚNG THEO THIẾT KẾ, nên nguy hiểm hơn hẳn — không có gì trông bất thường để
			 * ai đó kịp dừng tay.
			 *
			 * Giữ lại theo TÊN cơ sở, không theo vị trí: người ta có thể thêm, xoá, đổi thứ
			 * tự trong phần của mình.
			 * ══════════════════════════════════════════════════════════════════════════════ */
			if ( class_exists( 'VHCP_DonVi' ) && null !== VHCP_DonVi::xem_duoc() ) {
				$giu = array();
				foreach ( self::read( self::COSO ) as $r0 ) {
					$r0 = array_values( (array) $r0 );
					$t0 = trim( (string) ( isset( $r0[0] ) ? $r0[0] : '' ) );
					if ( '' === $t0 ) { continue; }
					if ( ! VHCP_DonVi::duoc_xem( isset( $r0[5] ) ? $r0[5] : '' ) ) { $giu[] = $r0; }
				}
				/* Dòng của bên kia đứng TRƯỚC: giao diện gom theo đơn vị rồi mới vẽ, nên thứ
				   tự lưu không đổi cách bày, mà giữ nguyên khối cũ ở đầu thì `set_cell()` của
				   những lượt seed cũ (đánh theo chỉ số) không trượt lung tung. */
				$rows = array_merge( $giu, $rows );
			}
			self::write( self::COSO, $rows );
			self::bao_coso_posh_( $rows );
		}
		if ( isset( $cfg['nhom'] ) && is_array( $cfg['nhom'] ) ) {
			$rows = array();
			foreach ( $cfg['nhom'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), ( $g( $x, 'loai' ) !== '' ? $g( $x, 'loai' ) : 'canhan' ), $g( $x, 'tkNo' ), $g( $x, 'boPhan' ) ); }
			self::write( self::NHOM, $rows );
		}
		if ( isset( $cfg['loaiChiPhi'] ) && is_array( $cfg['loaiChiPhi'] ) ) {
			// GIỮ LẠI GHI CHÚ CŨ khi dữ liệu gửi lên không mang theo.
			// Bảng ma trận trên giao diện không có cột Ghi chú, nên lưu bảng đó là ghi chú
			// của mọi dòng bị xóa trắng — mất luôn dấu "(nạp từ dữ liệu cũ)" dùng để phân
			// biệt loại thật với tên hạng mục lỡ nạp vào.
			$note_cu = array();
			foreach ( self::read( self::LOAI ) as $r0 ) {
				$r0 = array_values( (array) $r0 );
				$t0 = isset( $r0[0] ) ? mb_strtolower( trim( (string) $r0[0] ) ) : '';
				if ( $t0 !== '' && isset( $r0[5] ) && trim( (string) $r0[5] ) !== '' ) { $note_cu[ $t0 ] = (string) $r0[5]; }
			}
			$rows = array();
			foreach ( $cfg['loaiChiPhi'] as $x ) {
				$x  = (array) $x;
				$tn = $g( $x, 'ten' );
				$nt = $g( $x, 'note' );
				if ( $nt === '' && ! array_key_exists( 'note', $x ) ) {
					$k0 = mb_strtolower( trim( $tn ) );
					if ( isset( $note_cu[ $k0 ] ) ) { $nt = $note_cu[ $k0 ]; }
				}
				/* 🔴 KHỐI RỖNG THÌ LẤP BẰNG KHỐI CỦA BẢN ĐANG CHẠY, ĐỪNG GHI RỖNG XUỐNG.
				   Giao diện luôn gửi khối lên (mỗi bảng một khối), nhưng cửa này còn nhận cả
				   lượt nạp từ tệp và lượt gọi thẳng API. Một dòng khối rỗng là một loại chi
				   phí KHÔNG BẢNG NÀO CHỨA: nó rơi khỏi cả ba bảng và khỏi ô chọn lúc nhập
				   đơn, trong khi tiền mang tên nó vẫn nằm trong sổ. */
				$kh = trim( (string) $g( $x, 'khoi' ) );
				if ( '' === $kh ) { $kh = VHCP_DB::khoi(); }
				$rows[] = array( $tn, VHCP_Util::ma_so( $g( $x, 'tkNo' ) ), VHCP_Util::ma_so( $g( $x, 'tkCo' ) ), VHCP_Util::ma_so( $g( $x, 'maDt' ) ), $g( $x, 'boPhan' ), $nt, $g( $x, 'tenMisa' ), $g( $x, 'loaiTt' ), $g( $x, 'donVi' ), $kh, $g( $x, 'vaiTro' ), $g( $x, 'dauMuc' ), $g( $x, 'cha' ) );
			}
			self::write( self::LOAI, $rows );
		}
		// Cột "Loại" (mua của NCC / cá nhân ứng tiền) khai ngay trong bảng ma trận, nên lưu
		// riêng lẻ: chỉ vá đúng cột đó theo tên loại, không đụng TK Nợ / Tên MISA đã khai.
		if ( isset( $cfg['loaiTt'] ) && is_array( $cfg['loaiTt'] ) ) {
			$moi = array();
			foreach ( $cfg['loaiTt'] as $x ) {
				$x = (array) $x;
				$t = mb_strtolower( trim( $g( $x, 'ten' ) ) );
				if ( $t !== '' ) { $moi[ $t ] = $g( $x, 'loaiTt' ); }
			}
			$rows = array();
			foreach ( self::read( self::LOAI ) as $r ) {
				$row = array_slice( array_values( (array) $r ), 0, 8 );
				for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
				if ( trim( (string) $row[0] ) === '' ) { continue; }
				$t = mb_strtolower( trim( (string) $row[0] ) );
				if ( isset( $moi[ $t ] ) ) { $row[7] = $moi[ $t ]; }
				$rows[] = $row;
			}
			self::write( self::LOAI, $rows );
		}
		if ( isset( $cfg['mangTk'] ) && is_array( $cfg['mangTk'] ) ) {
			$rows = array();
			foreach ( $cfg['mangTk'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'pll' ), $g( $x, 'nhomTk' ), $g( $x, 'tuKhoa' ), $g( $x, 'note' ) ); }
			self::write( self::MANG, $rows );
		}
		if ( isset( $cfg['tkNoMatrix'] ) && is_array( $cfg['tkNoMatrix'] ) ) {
			$rows = array();
			foreach ( $cfg['tkNoMatrix'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'nhom' ), $g( $x, 'pll' ), VHCP_Util::ma_so( $g( $x, 'tkNo' ) ) ); }
			self::write( self::TKNO, $rows );
		}
		if ( isset( $cfg['phanloai'] ) && is_array( $cfg['phanloai'] ) ) {
			$rows = array();
			foreach ( $cfg['phanloai'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), $g( $x, 'tkCo' ) ); }
			self::write( self::PL, $rows );
		}
		if ( isset( $cfg['doiTuong'] ) && is_array( $cfg['doiTuong'] ) ) {
			$rows = array();
			foreach ( $cfg['doiTuong'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), $g( $x, 'ma' ), $g( $x, 'loai' ) ); }
			self::write( self::DT, $rows );
		}
		if ( isset( $cfg['qr'] ) && is_array( $cfg['qr'] ) ) {
			$q = (array) $cfg['qr'];
			self::write( self::QR, array( array( 'stk', $g( $q, 'stk' ) ), array( 'bank', $g( $q, 'bank' ) ), array( 'ten', $g( $q, 'ten' ) ) ) );
		}
		if ( isset( $cfg['users'] ) && is_array( $cfg['users'] ) ) {
			// CHỈ ADMIN SỬA ĐƯỢC TÀI KHOẢN ADMIN. Kế toán / Quản lý vào Cấu hình làm mọi
			// việc khác, nhưng không đổi tên, PIN, vai trò của Admin, cũng không tự phong
			// mình làm Admin. Giữ nguyên các dòng Admin đang có, bỏ mọi dòng Admin gửi lên.
			if ( VHCP_Auth::vai_tro() !== '' && VHCP_Auth::vai_tro() !== 'Admin' ) {
				$admin_cu = array();
				foreach ( self::get_users() as $u0 ) {
					if ( (string) $u0['vaiTro'] === 'Admin' ) { $admin_cu[] = $u0; }
				}
				$con_lai = array();
				foreach ( $cfg['users'] as $x0 ) {
					$x0 = (array) $x0;
					if ( ( isset( $x0['vaiTro'] ) ? (string) $x0['vaiTro'] : '' ) === 'Admin' ) { continue; }
					$con_lai[] = $x0;
				}
				$cfg['users'] = array_merge( $admin_cu, $con_lai );
			}
			$rows = array();
			foreach ( $cfg['users'] as $x ) {
				$x = (array) $x;
				// PIN / TK Có / mã đối tượng là MÃ SỐ: bảng tính xuất ra "2222.0", "141.0".
				// Rửa ngay lúc lưu, đừng để PIN mang dấu chấm rồi không ai đăng nhập được.
				$rows[] = array(
					$g( $x, 'ten' ),
					VHCP_Util::pin_sach( $g( $x, 'pin' ) ),
					( $g( $x, 'vaiTro' ) !== '' ? $g( $x, 'vaiTro' ) : 'Nhân viên' ),
					$g( $x, 'coso' ),
					VHCP_Util::ma_so( $g( $x, 'tkCo' ) ),
					VHCP_Util::ma_so( $g( $x, 'maDt' ) ),
					$g( $x, 'boPhan' ),
					$g( $x, 'donVi' ),
					$g( $x, 'xemDonVi' ),
					VHCP_Util::ma_so( $g( $x, 'maNv' ) ),
					$g( $x, 'khoi' )
				);
			}
			self::write( self::USER, $rows );
		}
		if ( isset( $cfg['vaiTro'] ) && is_array( $cfg['vaiTro'] ) ) {
			/* 🔴 CHỈ ADMIN. Ai sửa được bảng này là tự đúc cho mình một vai kế thừa Quản lý. */
			if ( 'Admin' !== VHCP_Auth::vai_tro() ) {
				return VHCP_Util::err( 'Chỉ Admin mới thêm/sửa vai trò được.' );
			}
			$rows = array();
			foreach ( $cfg['vaiTro'] as $x ) {
				$x = (array) $x;
				$t = trim( $g( $x, 'ten' ) );
				$b = trim( $g( $x, 'goc' ) );
				if ( '' === $t || 'Admin' === $t || in_array( $t, self::VAI_GOC, true ) ) { continue; }
				if ( ! in_array( $b, self::VAI_GOC, true ) ) { $b = 'Nhân viên'; }
				/* Hai ô, không còn ô bộ phận — xem chốt ở `vai_tuy_bien()`. Ghi hai ô là lượt
				   Lưu này cũng dọn luôn ô thứ ba của dòng cũ. */
				$rows[] = array( $t, $b );
			}
			self::write( self::VAI, $rows );
		}
		if ( isset( $cfg['boPhanDs'] ) && is_array( $cfg['boPhanDs'] ) ) {
			/* 🔴 CHỈ ADMIN. Bộ phận là thứ bó tầm nhìn của kế toán (xem `vai_tuy_bien()`), nên
			   ai sửa được bảng này là tự nới hoặc siết quyền người khác. */
			if ( 'Admin' !== VHCP_Auth::vai_tro() ) {
				return VHCP_Util::err( 'Chỉ Admin mới thêm/sửa bộ phận được.' );
			}
			$rows = array(); $da = array();
			foreach ( $cfg['boPhanDs'] as $x ) {
				$t = trim( (string) ( is_array( $x ) ? ( isset( $x['ten'] ) ? $x['ten'] : '' ) : $x ) );
				if ( '' === $t ) { continue; }
				/* Trùng tên (bỏ qua hoa thường) thì bỏ dòng sau: `bo_phan_chuan()` trả về tên
				   ĐẦU TIÊN khớp, nên hai dòng "Setup" và "setup" chỉ có một cái được dùng —
				   giữ cả hai là bày ra một lựa chọn không bao giờ tới lượt. */
				$k = mb_strtolower( $t );
				if ( isset( $da[ $k ] ) ) { continue; }
				$da[ $k ] = 1;
				$rows[]   = array( $t );
			}
			/* 🔴 KHÔNG CHO LƯU BẢNG RỖNG. Rỗng thì `bo_phan_ds()` ngã về mặc định, nên hệ vẫn
			   chạy — nhưng người vừa xoá sạch tưởng mình đã bỏ hết bộ phận, trong khi màn vẫn
			   bày đủ bảy cái. Chối thẳng còn hơn để họ tin vào một thứ không xảy ra. */
			if ( ! $rows ) {
				return VHCP_Util::err( 'Phải còn ít nhất một bộ phận. Xoá hết thì hệ tự dùng lại danh sách mặc định, không phải "không có bộ phận nào".' );
			}
			self::write( self::BP, $rows );
			self::$bp_memo = null;
		}
		if ( isset( $cfg['sso'] ) && is_array( $cfg['sso'] ) ) {
			$rows = array();
			foreach ( $cfg['sso'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'email' ), $g( $x, 'role' ), $g( $x, 'coso' ) ); }
			self::write( self::SSO, $rows );
		}
		self::clear_cache();
		return VHCP_Util::ok();
	}

	/** undoConfig(): trả bảng cấu hình về ngay trước lần lưu gần nhất (1 mức). */
	public static function undo_config() {
		$snap = VHCP_Meta::get_json( 'cfg_undo', null );
		if ( ! $snap || empty( $snap['name'] ) ) { return VHCP_Util::err( 'Chưa có lần lưu nào để hồi lại' ); }
		self::write( $snap['name'], isset( $snap['data'] ) ? $snap['data'] : array(), false );
		VHCP_Meta::del( 'cfg_undo' );
		self::clear_cache();
		return VHCP_Util::ok( array( 'name' => $snap['name'] ) );
	}

	// ---------------------------------------------------------------- phân quyền

	/** getQuyen(). */

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 *  BẢNG PHÂN QUYỀN ĐỌC THEO VỊ TRÍ — THÊM MỘT VAI LÀ MỌI CỘT TRƯỢT
	 *
	 *  Anh Thắng 13/09/2026 gửi ảnh màn Duyệt tạm ứng của một tài khoản vai **Quản lý**: hai
	 *  đơn "Chờ duyệt tạm ứng" chỉ còn nút 👁 Xem và ↩ Trả lại, **mất hẳn nút ✔ Duyệt tạm ứng**.
	 *
	 *  🔴 VÌ SAO. Một hàng của `CH_Quyen` là [ mã, tên, <ô vai 1>, <ô vai 2>, … ] — thứ tự lấy
	 *     từ `roles()`, và KHÔNG có gì trong hàng nói ô nào thuộc vai nào. Bản 1.154.0 chèn
	 *     'Giám đốc' vào ĐẦU `VAI_GOC`, nên mọi ô của bảng đã lưu trượt sang phải một vai:
	 *
	 *         ô của Quản lý          -> đọc thành Giám đốc
	 *         ô của Kế toán cá nhân  -> đọc thành Quản lý
	 *         ô của Kế toán NCC      -> đọc thành Kế toán cá nhân
	 *         ô của Nhân viên        -> đọc thành Kế toán NCC
	 *
	 *     `duyetTU` lưu Quản lý=1, Kế toán cá nhân=0 -> Quản lý nhận số 0: mất nút Duyệt.
	 *     `traDon`  lưu cả hai =1                    -> Quản lý nhận số 1: nút Trả lại còn.
	 *     Đúng hai nút trên ảnh, không sai cái nào. Và cùng phép trượt ấy làm Kế toán cá nhân
	 *     mất "Cấp tạm ứng", Kế toán NCC mất "Xác nhận quyết toán" — cả dây chuyền tiền đứng
	 *     lại mà màn hình không báo gì.
	 *
	 *  🔴 KHÔNG PHẢI CHUYỆN RIÊNG CỦA 'GIÁM ĐỐC'. Xoá một vai tự tạo ở GIỮA danh sách cũng gây
	 *     đúng phép trượt ấy. Nên bản vá này không đi chữa một ca, nó dựng lại hàng theo TÊN
	 *     VAI — và từ nay mỗi lượt ghi bảng đều cất kèm danh sách vai ứng với các cột, để lượt
	 *     đọc sau biết cột nào vốn của ai.
	 *
	 *  ⚠️ PHẢI ĐỌC THÔ, KHÔNG QUA `read()`. Hàm ấy tự đệm hàng cho đủ số cột hiện tại, nên hàng
	 *     lưu theo danh sách vai cũ đọc ra vẫn "đủ ô" — chỉ là lệch. Đệm xong thì không còn dấu
	 *     vết nào để nhận ra.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */

	/** Meta cất danh sách vai ứng với các cột của bảng quyền ĐANG LƯU. */
	const QUYEN_COT_O = 'quyen_cot_vai';

	/**
	 * Vai gốc TRƯỚC khi 'Giám đốc' được chèn (bản 1.154.0, 13/09/2026).
	 *
	 * ⚠️ ĐỪNG SỬA MẢNG NÀY KHI THÊM VAI MỚI. Nó không phải "danh sách vai" — nó là ẢNH CHỤP
	 *    thứ tự cột của những site đã lưu bảng quyền từ trước bản ấy và chưa có mốc `QUYEN_COT_O`.
	 *    Sửa nó là đọc sai chính những bảng mà nó sinh ra để đọc đúng.
	 */
	const VAI_GOC_TRUOC_GD = array( 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' );

	/**
	 * Vai gốc MỚI thì thừa hưởng ô của vai nào.
	 *
	 * Anh Thắng 13/09/2026: *"Giám Đốc: Toàn Quyền Xem"* — nên cho theo Quản lý, chứ để trắng
	 * tay thì vai vừa dựng ra không bấm được gì và người ta tưởng nó hỏng.
	 */
	const VAI_MOI_THEO = array( 'Giám đốc' => 'Quản lý' );

	/** Danh sách vai ứng với các cột của bảng quyền đang lưu — suy ra nếu chưa có mốc. */
	private static function quyen_cot_dang_luu() {
		$m = VHCP_Meta::get_json( self::QUYEN_COT_O, array() );
		if ( is_array( $m ) && $m ) { return array_values( $m ); }
		/* Chưa có mốc = bảng lưu từ trước bản 1.154.0. Vai tự tạo vẫn nối vào sau như cũ. */
		$ra = self::VAI_GOC_TRUOC_GD;
		foreach ( self::vai_tuy_bien() as $v ) { $ra[] = $v['ten']; }
		return $ra;
	}

	/**
	 * DỜI CỘT BẢNG QUYỀN VỀ ĐÚNG VAI. Chạy ở `plugins_loaded`, mọi lượt tải trang.
	 *
	 * 🔴 CHẠY LẠI PHẢI KHÔNG ĐỔI GÌ. Dời hai lần là cột trượt tiếp một nhịp nữa, và lần ấy thì
	 *    không ai lần ra nguyên nhân. Chốt nằm ở chính cái mốc: dời xong thì mốc bằng `roles()`,
	 *    và lượt sau thấy bằng nhau là trả về ngay.
	 *
	 * @return int số hàng đã dời (0 = không phải làm gì).
	 */
	public static function va_cot_quyen_them_vai() {
		global $wpdb;
		$roles = self::roles();
		$cu    = self::quyen_cot_dang_luu();
		if ( $cu === $roles ) { return 0; }

		$t    = VHCP_DB::t( 'cfg' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, cols FROM $t WHERE bang=%s", self::QUYEN ), ARRAY_A );
		/* Bảng chưa có hàng nào (site mới) — chỉ cần đặt mốc, `get_quyen()` tự lấy mặc định. */
		if ( ! $rows ) {
			VHCP_Meta::set_json( self::QUYEN_COT_O, $roles );
			return 0;
		}

		/* Vai tự tạo thừa hưởng vai gốc của nó; vai gốc mới thì theo bảng `VAI_MOI_THEO`. */
		$theo = self::VAI_MOI_THEO;
		foreach ( self::vai_tuy_bien() as $v ) { $theo[ $v['ten'] ] = $v['goc']; }

		$doi = 0;
		foreach ( $rows as $r ) {
			$a = json_decode( $r['cols'], true );
			if ( ! is_array( $a ) ) { continue; }
			$a = array_values( $a );
			/* Đọc ô cũ theo TÊN vai, từ đúng vị trí nó từng nằm. */
			$o = array();
			foreach ( $cu as $i => $vai ) {
				$o[ $vai ] = array_key_exists( 2 + $i, $a ) ? $a[ 2 + $i ] : null;
			}
			$moi = array(
				isset( $a[0] ) ? $a[0] : '',
				isset( $a[1] ) ? $a[1] : '',
			);
			foreach ( $roles as $vai ) {
				if ( array_key_exists( $vai, $o ) && null !== $o[ $vai ] ) {
					$moi[] = $o[ $vai ];
					continue;
				}
				/* Vai MỚI: thừa hưởng ô của vai nó theo, chứ không để trắng tay. */
				$g = isset( $theo[ $vai ] ) ? $theo[ $vai ] : '';
				$moi[] = ( '' !== $g && isset( $o[ $g ] ) && null !== $o[ $g ] ) ? $o[ $g ] : '';
			}
			if ( wp_json_encode( $moi ) === wp_json_encode( $a ) ) { continue; }
			$wpdb->update( $t, array( 'cols' => wp_json_encode( $moi ) ), array( 'id' => $r['id'] ) );
			$doi++;
		}

		VHCP_Meta::set_json( self::QUYEN_COT_O, $roles );
		self::clear_cache();
		delete_transient( 'vhcp_quyen' );
		return $doi;
	}

	public static function get_quyen() {
		$hit = get_transient( 'vhcp_quyen' );
		if ( is_array( $hit ) ) { return $hit; }
		$roles = self::roles();
		/* Vai tự tạo -> vai gốc. Ô nào của vai tự tạo CHƯA từng được tích riêng thì lấy đúng ô
		   của vai gốc: đó chính là nghĩa của "kế thừa". Không có bước này thì vai vừa tạo ra
		   không có quyền gì cả, và người mang vai đó bị chặn giữa chừng mà chẳng hiểu vì sao. */
		$goc = array();
		foreach ( self::vai_tuy_bien() as $v ) { $goc[ $v['ten'] ] = $v['goc']; }

		$saved = array();
		foreach ( self::read( self::QUYEN ) as $r ) {
			$key = trim( (string) $r[0] );
			if ( $key === '' ) { continue; }
			$o = array();
			foreach ( $roles as $i => $role ) {
				$co = array_key_exists( 2 + $i, $r );
				$o[ $role ] = $co ? VHCP_Util::quyen_truthy( $r[ 2 + $i ] ) : null;   // null = chưa khai
			}
			$saved[ $key ] = $o;
		}
		$out = array();
		foreach ( self::actions() as $a ) {
			if ( isset( $saved[ $a['key'] ] ) ) {
				$o = $saved[ $a['key'] ];
			} else {
				$o = array();
				foreach ( $roles as $role ) {
					$o[ $role ] = array_key_exists( $role, (array) $a['def'] )
						? ! empty( $a['def'][ $role ] )
						: ( isset( $goc[ $role ] ) ? null : false );
				}
			}
			foreach ( $goc as $vai => $g ) {
				if ( ! isset( $o[ $vai ] ) || null === $o[ $vai ] ) {
					$o[ $vai ] = ! empty( $o[ $g ] );
				}
			}
			foreach ( $o as $k => $vv ) { if ( null === $vv ) { $o[ $k ] = false; } }
			$out[ $a['key'] ] = $o;
		}
		set_transient( 'vhcp_quyen', $out, 300 );
		return $out;
	}

	public static function get_quyen_config() {
		$q   = self::get_quyen();
		$out = array();
		foreach ( self::actions() as $a ) {
			$out[] = array( 'key' => $a['key'], 'ten' => $a['ten'], 'perms' => $q[ $a['key'] ] );
		}
		return array( 'roles' => self::roles(), 'actions' => $out );
	}

	/**
	 * VÁ MỘT LẦN: bật "Xác nhận quyết toán" cho MỌI VAI KẾ TOÁN trong bảng quyền ĐANG LƯU.
	 *
	 * Đổi `def` ở trên chỉ ăn với site chưa từng lưu bảng quyền — site của anh Thắng đã lưu,
	 * nên `def` bị bỏ qua hoàn toàn và nút Duyệt vẫn không hiện. Đó là chỗ mà "sửa mặc định"
	 * trông như đã sửa xong mà thực ra chưa đụng tới gì.
	 *
	 * 🔴 CHỈ BẬT, KHÔNG BAO GIỜ TẮT. Đây là nới một quyền theo lệnh của chủ hệ thống; tắt bớt
	 *    thì phải do người ta tự tích. Và chỉ đụng đúng MỘT hành động, đúng các vai có gốc kế
	 *    toán — mọi ô khác giữ nguyên từng con số.
	 *
	 * ⚠️ CHẠY ĐÚNG MỘT LẦN (có cờ). Chạy lại mỗi lần nạp trang thì ai bỏ tích đi hôm nay,
	 *    sáng mai nó lại tự bật — và không ai hiểu vì sao bảng phân quyền không nghe lời.
	 */
	const CO_VA_QT = 'vhcp_va_qt_ke_toan';

	public static function va_quyen_quyet_toan() {
		if ( get_option( self::CO_VA_QT ) ) { return 0; }
		update_option( self::CO_VA_QT, 1, false );

		/* Site CHƯA TỪNG lưu bảng quyền thì `read()` trả rỗng, vòng dưới chạy 0 lượt, `$so`
		   ở nguyên 0 và không có lượt ghi nào — `def` mới đã đủ cho site ấy. Cố ý KHÔNG thêm
		   một dòng `if ( ! $rows ) return 0;`: nó không đổi hành vi, mà một dòng không phép
		   thử nào phân biệt được là một dòng không ai dám sửa về sau. */
		$rows  = self::read( self::QUYEN );
		$roles = self::roles();
		$mo    = array();
		foreach ( $roles as $i => $role ) {
			$g = self::vai_goc( $role );
			if ( 'Kế toán cá nhân' === $g || 'Kế toán NCC' === $g ) { $mo[] = 2 + $i; }
		}
		if ( ! $mo ) { return 0; }

		$so  = 0;
		$moi = array();
		foreach ( $rows as $r ) {
			$r = array_values( (array) $r );
			if ( 'xacNhanQT' === trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) ) {
				foreach ( $mo as $c ) {
					if ( empty( $r[ $c ] ) ) { $r[ $c ] = 1; $so++; }
				}
			}
			$moi[] = $r;
		}
		/* `write()` tự dọn bộ nhớ đệm rồi — gọi thêm `clear_cache()` ở đây là một dòng không
		   phép thử nào phân biệt được, tức một dòng không ai dám sửa về sau. */
		if ( $so ) { self::write( self::QUYEN, $moi, false ); }
		return $so;
	}

	public static function set_quyen( $matrix ) {
		$matrix = (array) $matrix;
		$roles  = self::roles();
		$rows   = array();
		foreach ( self::actions() as $a ) {
			$m   = isset( $matrix[ $a['key'] ] ) ? (array) $matrix[ $a['key'] ] : array();
			$row = array( $a['key'], $a['ten'] );
			foreach ( $roles as $role ) { $row[] = ( ! empty( $m[ $role ] ) ) ? 1 : 0; }
			$rows[] = $row;
		}
		self::write( self::QUYEN, $rows, false );
		/* Cất kèm danh sách vai ứng với các cột vừa ghi — xem khối dài ở
		   `va_cot_quyen_them_vai()`. Không có mốc này thì lượt thêm/xoá vai kế tiếp lại làm
		   mọi ô trượt chỗ, y như ca 'Giám đốc' ngày 13/09/2026. */
		VHCP_Meta::set_json( self::QUYEN_COT_O, $roles );
		self::clear_cache();
		return VHCP_Util::ok();
	}

	/**
	 * ĐẶT LẠI PHÂN QUYỀN VỀ MẶC ĐỊNH.
	 *
	 * Bảng CH_Quyen nạp từ bảng tính cũ có thể lệch cột hoặc thiếu hành động mới, nên một
	 * vai trò mất quyền mà không ai biết — biểu hiện là "nhập xong không thấy nút gửi".
	 * Bấm một nút để về mặc định rồi tinh chỉnh lại, khỏi tích tay cả ma trận.
	 *
	 * @return array [ 'soHanhDong' => số hành động đã đặt lại ]
	 */
	public static function reset_quyen() {
		$roles = self::roles();
		$rows  = array();
		foreach ( self::actions() as $a ) {
			$row = array( $a['key'], $a['ten'] );
			foreach ( $roles as $role ) { $row[] = ! empty( $a['def'][ $role ] ) ? 1 : 0; }
			$rows[] = $row;
		}
		self::write( self::QUYEN, $rows, false );
		VHCP_Meta::set_json( self::QUYEN_COT_O, $roles );   // xem `va_cot_quyen_them_vai()`
		self::clear_cache();
		return VHCP_Util::ok( array( 'soHanhDong' => count( $rows ) ) );
	}

	// ---------------------------------------------------------------- người dùng

	/**
	 * Tra danh mục LOẠI CHI PHÍ theo tên -> mã tài khoản đã gắn.
	 * Đây là nơi duy nhất quyết định "chi phí này là chi phí gì": không còn dò
	 * ma trận nhóm × phân loại lớn nữa, nên số nào cũng dò được bằng mắt.
	 */
	public static function loai_map() {
		$s   = self::cfg_static();
		$out = array();
		foreach ( (array) ( isset( $s['loaiChiPhi'] ) ? $s['loaiChiPhi'] : array() ) as $x ) {
			$out[ mb_strtolower( trim( (string) $x['ten'] ) ) ] = $x;
		}
		return $out;
	}

	/**
	 * BỘ PHẬN của một loại chi phí — '' nếu chưa khai hoặc loại không có trong danh mục.
	 *
	 * Đây là cách một DÒNG TIỀN biết mình thuộc mảng nào: dòng chi ghi tên loại, loại khai bộ
	 * phận. Không có đường nào khác — bảng chi phí không có cột bộ phận, và thêm cột thì lại
	 * là một chỗ nữa phải nhớ ghi.
	 *
	 * ⚠️ '' KHÔNG có nghĩa "không thuộc ai". Loại chưa khai bộ phận là chuyện thường (danh mục
	 *    dựng từ sổ cũ), và những dòng ấy phải hiện cho MỌI kế toán chứ không biến mất — tiền
	 *    có thật mà không ai nhìn thấy thì tệ hơn hẳn việc nó hiện ở cả hai màn. Chốt gọi bên
	 *    dưới xử đúng như thế.
	 */
	public static function bo_phan_cua_loai( $ten_loai ) {
		$ds = self::bo_phan_ds_cua_loai( $ten_loai );
		return $ds ? $ds[0] : '';
	}

	/**
	 * MỘT LOẠI CHI PHÍ THUỘC ĐƯỢC NHIỀU BỘ PHẬN.
	 *
	 * Anh Thắng 10/09/2026: *"Cho phép loại chi phí chọn theo bộ phận, nhiều bộ phận sẽ chọn
	 * loại chi phí đó cùng tên, chỉ là mỗi cơ sở khác mã thôi"*. Ô Bộ phận giữ nhiều tên,
	 * ngăn bằng dấu phẩy; dữ liệu cũ chỉ có một tên nên vẫn đọc ra đúng như trước.
	 *
	 * 🔴 KHÔNG ĐƯỢC ĐEM CẢ Ô ĐI `bo_phan_chuan()`. Hàm ấy so nguyên chuỗi với danh sách bộ
	 *    phận, nên "Kỹ thuật, Setup" không khớp tên nào và nó trả về '' — mà '' ở đây nghĩa
	 *    là "loại này không bó bộ phận nào", tức HIỆN CHO MỌI KẾ TOÁN. Khai thêm một bộ phận
	 *    thứ hai lại thành nới quyền cho tất cả, và hỏng im lặng: nhìn màn thì thấy nhiều số
	 *    hơn chứ không thấy lỗi. Phải tách trước, chuẩn hoá TỪNG tên.
	 *
	 * ⚠️ Mảng rỗng vẫn giữ nguyên nghĩa cũ: "chưa khai bộ phận" — và loại như thế hiện cho
	 *    mọi người, vì danh mục dựng từ sổ cũ còn rất nhiều dòng bỏ trống ô này.
	 */
	public static function bo_phan_ds_cua_loai( $ten_loai ) {
		$x = self::loai_tk( $ten_loai );
		return self::bo_phan_tach( isset( $x['boPhan'] ) ? $x['boPhan'] : '' );
	}

	/** Tách ô Bộ phận (nhiều tên, ngăn bằng dấu phẩy) thành danh sách tên đã chuẩn hoá. */
	public static function bo_phan_tach( $x ) {
		$ra = array();
		foreach ( preg_split( '/\s*,\s*/u', (string) $x ) as $t ) {
			$c = self::bo_phan_chuan( $t );
			if ( '' !== $c && ! in_array( $c, $ra, true ) ) { $ra[] = $c; }
		}
		return $ra;
	}

	/**
	 * LOẠI CHI PHÍ NÀY VAI ẤY CÓ ĐƯỢC DÙNG KHÔNG.
	 *
	 * Anh Thắng 21/09/2026: *"bỏ tích bộ phận đi, mà tích theo vai trò"*. Từ bản này ô tích ở
	 * bảng Loại chi phí là TÊN VAI, không còn là tên bộ phận.
	 *
	 * 🔴 CHƯA TÍCH VAI NÀO = MỌI VAI ĐỀU DÙNG ĐƯỢC. Giữ đúng nghĩa ô trống của cột Bộ phận nó
	 *    thay thế, và vì đúng lý do cũ: danh mục của anh Thắng dựng từ sổ cũ, gần như mọi dòng
	 *    còn bỏ trống. Hiểu ngược lại là ngày bản này lên, mở màn ra thấy gần như trắng — và
	 *    người ta kết luận là mất dữ liệu chứ không đoán ra là do một ô chưa khai.
	 */
	public static function loai_thuoc_vai( $ten_loai, $vai ) {
		$k = mb_strtolower( trim( (string) $vai ) );
		if ( '' === $k ) { return true; }
		$x  = self::loai_tk( $ten_loai );
		$ds = isset( $x['vaiTro'] ) ? (string) $x['vaiTro'] : '';
		if ( '' === trim( $ds ) ) { return true; }
		foreach ( preg_split( '/\s*,\s*/u', $ds ) as $t ) {
			if ( mb_strtolower( trim( (string) $t ) ) === $k ) { return true; }
		}
		return false;
	}

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * ĐẦU MỤC CHI PHÍ — GOM, KHÔNG LỌC.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 22/09/2026: *"phân loại để lên chi phí dễ nhất, các bộ phận nhập được"*, và
	 * *"phân theo đầu mục chi phí lớn"*.
	 *
	 * 🔴 HAI HỆ LỚN NHẤT ĐỀU KHÔNG LỌC DANH MỤC THEO NGƯỜI — đo trên chính mã nguồn của họ:
	 *      · ERPNext: `Expense Claim Type` có ĐÚNG BỐN trường (tên · mô tả · bảng tài khoản ·
	 *        cờ trả trước). Không một trường nào hạn chế vai trò hay bộ phận. Phân quyền nằm ở
	 *        cấp "ai được tạo đơn", không ở từng loại.
	 *      · Odoo: app Chi phí ship ĐÚNG SÁU danh mục, phẳng, mọi người thấy hết.
	 *    Thay vào đó họ để trục "ai/ở đâu" thành TRƯỜNG RIÊNG trên đơn (Bộ phận · Trung tâm
	 *    chi phí · Dự án), và Bộ phận thì TỰ ĐIỀN từ hồ sơ nhân viên.
	 *
	 * 🔴 CHỖ HỎNG CỦA BẢN CŨ: một dòng Loại chi phí mang BỐN cột lọc (Bộ phận đã chết · Đơn vị ·
	 *    Khối · Vai trò) và KHÔNG MỘT CỘT NÀO ĐỂ GOM. Người nhập sai vai là không thấy ô của
	 *    mình — đúng câu *"chọn nhân viên sẽ ra chi phí đó"*. Ngược hẳn hai hệ kia: họ không
	 *    lọc, chỉ gom; mình lọc bốn tầng, không gom.
	 *
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 ĐẦU MỤC LÀ DANH MỤC CHA. MỘT LOẠI CHI PHÍ NẰM DƯỚI ĐÚNG MỘT ĐẦU MỤC.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 22/09/2026: *"Sai cơ bản với nhau rồi. Loại chi phí là chi phí chi tiết, còn
	 * đầu mục là Danh mục chính của chi phí"*, và cho hai ví dụ chốt lại:
	 *
	 *      Chi phí NVL đồ ăn  ->  Chi phí cơ sở
	 *      Chi phí cơ sở      ->  Chi phí cơ sở
	 *
	 * 🔴 HAI VÍ DỤ ẤY LOẠI BỎ HẲN MỘT CÁCH HIỂU. Bản 1.259–1.260 để bảng này là MƯỜI HAI mục —
	 *    em lấy cả ba gốc LẪN các nhánh con của sơ đồ rồi trải phẳng vào một danh sách. Nếu
	 *    thế thì "NVL đồ ăn" phải rơi vào "Cơ sở · Nguyên vật liệu" còn "Chi phí cơ sở" rơi
	 *    vào "Cơ sở · Cơ sở tự mua" — HAI đầu mục khác nhau. Anh nói cả hai CÙNG một. Vậy đầu
	 *    mục chính là BA GỐC, còn mọi thứ dưới gốc đều là loại chi phí chi tiết.
	 *
	 * ⚠️ VÀ VÌ LÀ QUAN HỆ CHA–CON nên mỗi loại chỉ mang MỘT đầu mục. Bản 1.260 từng cho tích
	 *    nhiều; anh gạt đi. Cây thì mỗi con một cha — cho nhiều cha là thứ khác hẳn (gắn thẻ),
	 *    và nó làm mờ đúng cái tầng mà bảng này sinh ra để dựng.
	 *
	 * ⚠️ KHÔNG CÒN LUẬT "8–12 ĐẦU MỤC" Ở ĐÂY. Con số ấy em lấy từ thực hành chung, đúng cho
	 *    một danh sách PHẲNG mà người ta phải chọn một. Ở đây danh mục cha chỉ có ba gốc, và
	 *    ba là đúng — ép cho đủ tám là dựng thêm tầng giả. Số lượng phải theo sơ đồ của anh,
	 *    không theo sách.
	 *
	 * ⚠️ ĐÂY LÀ ĐƯỜNG LUI, KHÔNG PHẢI BẢN CHỐT. Kế toán khai danh sách thật ở Cấu hình; bảng
	 *    này chỉ để site chưa khai gì vẫn có cái mà chọn. Cùng lối với `bo_phan_ds()`.
	 */
	const DAU_MUC_MAC_DINH = array(
		'Chi phí chung',       // CPC VP (bổ 50/50) · CPC vận hành & cơ sở
		'Chi phí cơ sở',       // MKT/VH/KT mua cho cơ sở · cơ sở tự mua · NVL · hàng hoá nhập kho
		'Chi phí tiền thuê',   // thuê mall · trả tiền mall · điện, nước, phụ phí
		'Khác',                // ô hứng — thiếu nó là người ta nhét bừa vào ô gần giống
	);

	/**
	 * Danh sách đầu mục. Chưa khai thì rơi về bảng mặc định.
	 *
	 * ⚠️ RƠI VỀ, KHÔNG TRẢ RỖNG. Danh sách rỗng thì ô chọn trống trơn và người nhập kẹt cứng —
	 *    mà họ không có cách nào tự chữa, vì khai danh mục là việc của kế toán.
	 */
	public static function dau_muc_ds() {
		$ds = get_option( 'vhcp_dau_muc_ds', null );
		if ( is_string( $ds ) ) { $ds = array_map( 'trim', explode( "\n", str_replace( "\r", '', $ds ) ) ); }
		$ra = array();
		foreach ( (array) $ds as $x ) {
			$t = trim( (string) $x );
			if ( '' !== $t && ! in_array( $t, $ra, true ) ) { $ra[] = $t; }
		}
		return $ra ? $ra : self::DAU_MUC_MAC_DINH;
	}

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * LỌC LOẠI CHI PHÍ THEO VAI TRÒ — BẬT/TẮT THEO VÙNG.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 BẬT (mặc định) = hành vi cũ của Khu vui chơi, KHÔNG ĐỔI MỘT LY.
	 * 🔴 TẮT = mọi vai thấy đủ loại, và đầu mục là thứ dẫn đường thay cho bộ lọc. Bản Hà Nội
	 *    chạy ở chế độ này (anh Thắng 22/09/2026: *"các bộ phận nhập được"*).
	 *
	 * ⚠️ VÌ SAO LÀ CỜ CHỨ KHÔNG PHẢI ĐỔI THẲNG: bản vùng được SINH LẠI từ bản gốc, nên sửa
	 *    riêng một bản là lượt sinh sau mất sạch. Đặt ở bản gốc kèm cờ thì trình sinh giữ
	 *    được — đúng nếp đã dùng cho `LAY_COSO_GHE`.
	 *
	 * ⚠️ CỜ NÀY KHÔNG PHẢI CỔNG QUYỀN. Nó chỉ quyết định ô chọn bày bao nhiêu dòng. Ai xem
	 *    được đơn nào vẫn do ĐƠN VỊ và CƠ SỞ gác, ở máy chủ, không đụng tới.
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 CÓ LỌC LOẠI CHI PHÍ THEO KHỐI KHÔNG.
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 22/09/2026: *"Khối là dùng chung, vì đã phân theo vai trò rồi, Khối là liên
	 * quan Miền Bắc và Miền Nam thôi"*.
	 *
	 * Cắn thật ngay sau đó: bản Hà Nội mở ra, ô Loại chi phí RỖNG, kèm câu "Khối HN chưa có
	 * loại chi phí nào (danh mục đang có 27 loại, nhưng của khối khác)". Hai bảy loại nằm đó
	 * mà không dùng được cái nào — vì chúng khai ở khối 'kvc', còn bản này đứng ở khối 'hn'.
	 *
	 * 🔴 KHỐI VÀ VAI TRÒ TRẢ LỜI HAI CÂU KHÁC NHAU, và chỉ một câu là chuyện phân quyền:
	 *      · VAI TRÒ — AI được dùng loại này. Đó là cổng thật, đã có `loc_loai_theo_vai()`.
	 *      · KHỐI    — dữ liệu này thuộc MIỀN nào. Đó là chuyện gom sổ, không phải chuyện cấm.
	 *    Lấy khối làm cổng thứ hai là bắt kế toán khai lại cả danh mục cho từng miền, trong
	 *    khi "Chi phí điện nước" ở đâu cũng là chi phí điện nước.
	 *
	 * ⚠️ BẬT (mặc định) = HÀNH VI CŨ CỦA KHU VUI CHƠI, KHÔNG ĐỔI MỘT LY. Ở bản gốc, khối là
	 *    MẢNG KINH DOANH (KVC · MTĐ · VP) chứ không phải miền, và ba bảng danh mục tách nhau
	 *    là cố ý. Chỉ bản vùng — nơi khối thật sự là miền — mới tắt.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	const LOC_LOAI_THEO_KHOI = true;
	public static function loc_loai_theo_khoi() {
		$v = get_option( 'vhcp_loc_loai_theo_khoi', null );
		if ( null === $v || '' === $v ) { return self::LOC_LOAI_THEO_KHOI; }
		return (bool) (int) $v;
	}

	const LOC_LOAI_THEO_VAI = true;

	/** Vùng này có lọc loại chi phí theo vai trò không. Ô cấu hình thắng hằng. */
	public static function loc_loai_theo_vai() {
		$v = get_option( 'vhcp_loc_loai_theo_vai', null );
		if ( null === $v || '' === $v ) { return self::LOC_LOAI_THEO_VAI; }
		return (bool) (int) $v;
	}

	/* ═══════════════════════════════════════════════════════════════════════════════════════
	 * KHỐI CỦA MỘT VAI TRÒ — ĐỌC RA TỪ CHÍNH CÁI TÊN, KHÔNG KHAI THÊM CỘT NÀO.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 21/09/2026: *"Loại chi phí theo Khối, Ai có ở khối nào mới hiện ra"*. Bảng loại chi phí
	 * của Khu vui chơi đang bày cả "Quản Lý Máy Tự Động", "Kế Toán VP Chung"… — mười lăm ô tích,
	 * quá nửa không bao giờ dùng tới, và tích nhầm một cái là mở sổ cho cả một khối khác.
	 *
	 * 🔴 KHÔNG ĐẺ THÊM MỘT CỘT "KHỐI" TRÊN BẢNG VAI TRÒ. Chính 21/09 vừa gỡ cột Bộ phận khỏi
	 *    bảng vai trò và khỏi bảng người dùng, vì *"dùng hết trên vai trò cha, con rồi"*. Thêm một ô
	 *    khai khối là dựng lại y hệt cái trục thừa ấy, chỉ đổi tên — và hai nơi khai thì có ngày lệch:
	 *    vai tên "Máy Tự Động" mà ô khối để "Khu vui chơi", không ai biết bên nào đúng.
	 *    Tên vai CHÍNH LÀ nơi anh Thắng đã khai mảng; đọc lại từ đó thì không có gì để lệch.
	 *
	 * ⚠️ KHÔNG ĐOÁN ĐƯỢC = THUỘC MỌI KHỐI, không phải "không thuộc khối nào". "Nhân Viên
	 *    Marketing", "Kế toán NCC", "Quản lý" — những vai chạy ngang cả công ty — không mang tên khối
	 *    nào. Hiểu ngược là chúng biến khỏi cả ba bảng, và không còn ô nào để tích cho họ nữa.
	 *    Hỏng theo hướng bày thừa một ô, không phải hướng giấu mất người.
	 *
	 * ⚠️ XÉT "vp" SAU CÙNG và xét theo TỪ, không theo chuỗi con. "Quản Lý VP Chung" có "vp" thật,
	 *    nhưng một ngày nào đó có vai tên kèm chữ "TVP" hay "VPC" thì chứa chuỗi con mà không
	 *    phải văn phòng. Và "kvc"/"mtd" phải đi trước vì chúng cụ thể hơn.
	 * ═══════════════════════════════════════════════════════════════════════════════════════ */
	const KHOI_THEO_TEN_VAI = array(
		'kvc' => array( 'khu vui choi', 'kvc' ),
		'mtd' => array( 'may tu dong', 'mtd', 'posh', 'ghe massage' ),
		'vp'  => array( 'van phong', 'vp' ),
	);

	/** Mã khối đọc ra từ tên một vai trò — '' = vai chạy ngang, thuộc MỌI khối. */
	public static function khoi_cua_vai( $ten ) {
		$t = ' ' . preg_replace( '/\s+/u', ' ', trim( self::bo_dau( $ten ) ) ) . ' ';
		if ( ' ' === $t ) { return ''; }
		foreach ( self::KHOI_THEO_TEN_VAI as $ma => $ds ) {
			foreach ( $ds as $x ) {
				if ( false !== mb_strpos( $t, ' ' . $x . ' ' ) ) { return $ma; }
			}
		}
		return '';
	}

	/**
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * KHỐI NÀO KHÔNG TỰ XUẤT MISA — ĐƠN BÀN GIAO CHO KẾ TOÁN KVC TỔNG KẾT.
	 *
	 * Anh Thắng 21/09/2026: *"Chỗ phần kế toán máy tự động duyệt xong sẽ đẩy qua kế toán KVC
	 * tổng kết (vì kế toán máy tự động chỉ check chứ ko đẩy misa)"*.
	 *
	 * 🔴 KHÔNG CÓ CỘT "ĐÃ BÀN GIAO" NÀO CẢ, VÀ CỐ Ý. Bàn giao xảy ra TỰ ĐỘNG lúc kế toán MTĐ
	 *    duyệt quyết toán, nên nó đã được nói trọn vẹn bởi hai thứ CÓ SẴN: đơn mang khối 'mtd',
	 *    và trạng thái đã sang 'Đã quyết toán'. Đẻ thêm một cột cờ thì phải: nới bảng, lấp cho
	 *    mấy chục đơn đã ở 'Đã quyết toán' từ trước, và đóng dấu ở CẢ HAI hàm quyết toán
	 *    (`xac_nhan_quyet_toan_cn` và `..._ncc`) — quên một chỗ là đơn duyệt xong mà nằm im,
	 *    không ai bên KVC biết mà xuất. Suy ra thì không có gì để quên, và cũng không có gì
	 *    lệch được.
	 *    Ai bàn giao, lúc nào: `nguoi_qt` / `ngay_qt` đã ghi sẵn, không mất mát gì.
	 *
	 * 🔴 CHỈ MÁY TỰ ĐỘNG. Anh Thắng chốt 21/09/2026 khi em hỏi lại: *"Chỉ MTĐ, Văn phòng tự
	 *    xuất MISA"*. Nên đây là DANH SÁCH, không phải phép "khác kvc thì chặn" — viết kiểu
	 *    kia là ngày mai thêm một khối thứ tư nó bị chặn oan mà không ai khai gì.
	 *
	 * ⚠️ ĐỌC TÊN VAI ĐANG MANG, KHÔNG PHẢI VAI GỐC. "Kế Toán Máy Tự Động" kế thừa "Kế toán cá
	 *    nhân" — quy về vai gốc là cả nhánh kế toán mất quyền xuất MISA, kể cả kế toán KVC.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 DANH SÁCH NÀY NAY RỖNG — TẮT TỪ 22/09/2026.
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng: *"Hiện tại chi phí máy tự động áp dụng web riêng nên không dùng chung nữa"*.
	 * Cả luật "kế toán MTĐ chỉ soát, đơn bàn giao sang kế toán KVC xuất MISA" (1.245.0) sinh ra
	 * CHỈ VÌ hai khối chung một app. MTĐ ra riêng thì bên ấy tự xuất MISA của mình, và bên này
	 * không còn ai mang vai MTĐ để mà chặn.
	 *
	 * 🔴 ĐỂ RỖNG CHỨ KHÔNG XOÁ CẢ CƠ CHẾ, và đây là lựa chọn có cân nhắc:
	 *      · Rỗng là TẮT THẬT — `xuat_misa_duoc()` trả `true` cho mọi vai, cổng API thôi chặn,
	 *        dải bàn giao trên màn thôi hiện. Không còn hành vi nào sót lại.
	 *      · Còn đơn MTĐ CŨ nằm lại kho này thì kế toán KVC xuất nốt được — nếu xoá cơ chế
	 *        bằng cách chặn kiểu khác thì đám đơn ấy kẹt.
	 *      · Khối VP thì anh Thắng bảo *"Chưa chốt"* (22/09). Ngày nào cần bật lại cho một
	 *        khối nào đó thì thêm đúng một mã vào đây, không phải dựng lại sáu chỗ.
	 *
	 * ⚠️ RỖNG LÀ "MỌI KHỐI ĐỀU XUẤT ĐƯỢC", không phải "chặn hết". Viết `! in_array(...)` nên
	 *    danh sách rỗng cho qua tất — đọc nhầm chiều là sửa thành chặn cả nhà.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	const KHOI_KHONG_XUAT_MISA = array();

	/** Người đang gọi có được xuất / chốt MISA không. Admin không bao giờ bị chặn. */
	public static function xuat_misa_duoc() {
		if ( 'Admin' === VHCP_Auth::vai_tro() ) { return true; }
		$k = self::khoi_cua_vai( VHCP_Auth::vai_hien() );
		return ! in_array( $k, self::KHOI_KHONG_XUAT_MISA, true );
	}

	/** Tên khối cho câu báo lỗi / nhãn trên màn. */
	public static function ten_khoi( $ma ) {
		$m = array( 'kvc' => 'Khu vui chơi', 'mtd' => 'Máy tự động', 'vp' => 'Văn phòng' );
		$k = mb_strtolower( trim( (string) $ma ) );
		return isset( $m[ $k ] ) ? $m[ $k ] : $ma;
	}

	/**
	 * Vai trò này có được bày ở bảng loại chi phí của khối $khoi không.
	 *
	 * Bản song sinh ở màn là `_vaiOKhoi()`. Hai bên PHẢI cùng luật: lệch một vế là bài kiểm
	 * xanh mà người khai nhìn thấy một danh sách khác hẳn.
	 */
	public static function vai_o_khoi( $ten, $khoi ) {
		$k = self::khoi_cua_vai( $ten );
		if ( '' === $k ) { return true; }                       // vai chạy ngang — mọi khối
		return $k === mb_strtolower( trim( (string) $khoi ) );
	}

	/** Loại chi phí này có thuộc bộ phận $bp không. Loại chưa khai bộ phận -> thuộc MỌI bộ phận. */
	public static function loai_thuoc_bo_phan( $ten_loai, $bp ) {
		$k = mb_strtolower( trim( (string) $bp ) );
		if ( '' === $k ) { return true; }
		$ds = self::bo_phan_ds_cua_loai( $ten_loai );
		if ( ! $ds ) { return true; }
		foreach ( $ds as $b ) { if ( mb_strtolower( $b ) === $k ) { return true; } }
		return false;
	}

	/**
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * DÒNG DANH MỤC ĐÚNG CHO MỘT LOẠI CHI PHÍ, CÓ PHÂN BIỆT KHỐI.
	 *
	 * 🔴 `loai_map()` KHOÁ THEO TÊN, NÊN DÒNG SAU ĐÈ DÒNG TRƯỚC. Từ 1.239.0 mỗi khối có bảng mã
	 *    riêng, nên hai khối hoàn toàn có thể cùng có một loại tên "Chi phí cơ sở" — và bảng tra
	 *    ấy chỉ giữ lại MỘT dòng. Không khai khối thì mọi phép tra mã (TK Nợ, TK Có, mã đối
	 *    tượng, tên MISA) của cả hai khối cùng đọc ra mã của khối nào tình cờ đứng sau.
	 *
	 * 🔴 KHÔNG ĐỔI HÀNH VI CỦA NGƯỜI GỌI CŨ. `$khoi` rỗng thì hàm này trả về đúng dòng mà
	 *    `loai_map()` giữ lại, y như trước — thêm một tham số mà làm đổi câu trả lời của mọi
	 *    người gọi cũ là hỏng ngầm trên toàn bộ sổ.
	 *
	 * ⚠️ DÒNG KHÔNG KHAI KHỐI LÀ DÒNG DÙNG CHUNG, và nó đứng SAU dòng khai đúng khối chứ không
	 *    thay thế. Danh mục dựng từ sổ cũ còn nhiều dòng bỏ trống ô Khối; bỏ chúng đi là loại có
	 *    thật mà tra ra rỗng, rồi báo "thiếu TK" cho một thứ đã khai từ lâu.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function loai_row( $ten, $khoi = '' ) {
		$k  = mb_strtolower( trim( (string) $ten ) );
		if ( '' === $k ) { return null; }
		$kh = mb_strtolower( trim( (string) $khoi ) );
		if ( '' === $kh ) {
			$m = self::loai_map();
			return isset( $m[ $k ] ) ? $m[ $k ] : null;
		}
		/* ═══════════════════════════════════════════════════════════════════════════════════
		   KHỚP ĐÚNG KHỐI, HOẶC KHÔNG CÓ. Không ngã về dòng của khối khác, và KHÔNG có nhánh
		   "dòng dùng chung".

		   🔴 VÌ Ô KHỐI CỦA BẢNG NÀY KHÔNG BAO GIỜ RỖNG. `VHCP_Cfg::write()` lấp ô trống thành
		      'kvc' (chốt ở `kiem-loai-theo-khoi.php`: *"rỗng = loại KHÔNG BẢNG NÀO CHỨA"*), nên
		      mỗi loại thuộc ĐÚNG MỘT khối. Lượt đầu em có viết thêm một nhánh `$chung` cho
		      "dòng chưa khai khối" — nghe chắc ăn, nhưng phá thử chỉ ra ngay là KHÔNG lượt chạy
		      nào tới được nó. Nhánh không ai đi tới thì không ai biết nó còn đúng, và nó cũng
		      chẳng bảo vệ được gì; cùng lý do `xem_duoc_bo_phan()` đã bị gỡ.

		   🔴 HỎI "LOẠI X CỦA VP" MÀ CHỈ CÓ DÒNG X CỦA MTĐ THÌ CÂU TRẢ LỜI LÀ KHÔNG CÓ, chứ không
		      phải mã của MTĐ — trả bừa chính là cái "đè lên nhau" mà hàm này sinh ra để chặn,
		      chỉ khác là lặng lẽ hơn. Người gọi (`tkco_xuat`) hiểu `null` là "chưa khai" và rơi
		      xuống bậc sau, tức về đúng hành vi cũ — hướng hỏng an toàn.
		   ═══════════════════════════════════════════════════════════════════════════════════ */
		$s = self::cfg_static();
		foreach ( (array) ( isset( $s['loaiChiPhi'] ) ? $s['loaiChiPhi'] : array() ) as $x ) {
			if ( mb_strtolower( trim( (string) $x['ten'] ) ) !== $k ) { continue; }
			if ( mb_strtolower( trim( (string) ( isset( $x['khoi'] ) ? $x['khoi'] : '' ) ) ) === $kh ) { return $x; }
		}
		return null;
	}

	/** Mã tài khoản của 1 loại chi phí (rỗng nếu chưa khai). `$khoi` = '' giữ nguyên luật cũ. */
	public static function loai_tk( $ten, $khoi = '' ) {
		$x = self::loai_row( $ten, $khoi );
		if ( ! $x ) { return array( 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'tenMisa' => '', 'loaiTt' => '', 'vaiTro' => '', 'dauMuc' => '', 'cha' => '' ); }
		return array(
			'loaiTt'  => isset( $x['loaiTt'] ) ? (string) $x['loaiTt'] : '',
			'tkNo'    => (string) $x['tkNo'],
			'tkCo'    => (string) $x['tkCo'],
			'maDt'    => (string) $x['maDt'],
			'boPhan'  => isset( $x['boPhan'] ) ? (string) $x['boPhan'] : '',
			'tenMisa' => isset( $x['tenMisa'] ) ? (string) $x['tenMisa'] : '',
			/* Ai được dùng loại này — xem `loai_thuoc_vai()`. Trống = mọi vai. */
			'vaiTro'  => isset( $x['vaiTro'] ) ? (string) $x['vaiTro'] : '',
			/* Đầu mục lớn — chỉ để GOM ô chọn, không gác ai cả. Xem `dau_muc_ds()`. */
			'dauMuc'  => isset( $x['dauMuc'] ) ? (string) $x['dauMuc'] : '',
			/* Tên loại CHA — dòng này là chi phí con của nó. Rỗng = dòng ở tầng trên cùng.
			   Xem chốt ở `VHCP_Cfg::DAU_MUC_MAC_DINH` về ba tầng của cây. */
			'cha'     => isset( $x['cha'] ) ? (string) $x['cha'] : '',
		);
	}

	/** Phân loại lớn (mảng kinh doanh) của 1 cơ sở / gian / địa điểm. */
	public static function pll_of( $coso ) {
		$s = self::cfg_static();
		$k = mb_strtolower( trim( (string) $coso ) );
		if ( $k === '' || ! isset( $s['cosoPll'][ $k ] ) ) { return ''; }
		return (string) $s['cosoPll'][ $k ];
	}

	/**
	 * TK Nợ theo MA TRẬN cho 1 loại chi phí tại 1 cơ sở. Dò từ hẹp ra rộng:
	 *   1) khai riêng cho ĐÚNG cơ sở đó   (trường hợp ngoại lệ)
	 *   2) khai cho MẢNG KINH DOANH của cơ sở (phân loại lớn) — dùng chung cho mọi cơ sở
	 *      cùng mảng, vì cùng loại chi phí mà khác mảng là khác mã ("Chi phí cơ sở":
	 *      EVENT 64196 · FARM 64166 · FZ 64126 · TUTU 64106)
	 * Trả '' khi chưa khai -> để bước sau lấy mã cố định của loại.
	 */
	public static function tkno_mx_list( $loai, $coso ) {
		$s  = self::cfg_static();
		$k  = mb_strtolower( trim( (string) $loai ) );
		if ( $k === '' || ! isset( $s['tkNoMx'][ $k ] ) ) { return array(); }
		$row = $s['tkNoMx'][ $k ];

		$c = mb_strtolower( trim( (string) $coso ) );
		if ( $c !== '' && isset( $row[ $c ] ) ) { return (array) $row[ $c ]; }

		$pll = self::pll_of( $coso );
		if ( $pll === '' ) { return array(); }
		$p = mb_strtolower( $pll );
		return isset( $row[ $p ] ) ? (array) $row[ $p ] : array();
	}

	/**
	 * MỌI TK NỢ ĐÃ KHAI trong hệ — gom từ ma trận [loại × mảng] và cột `tkNo` của danh mục.
	 *
	 * Dùng để gác ô "kế toán chỉnh TK Nợ của một dòng" (`VHCP_Don::set_line_tk_no`): kế toán
	 * chọn trong "các số lập sẵn", không gõ tự do.
	 *
	 * ⚠️ MÃ MA CHỈ LỘ RA Ở MISA. Nhận bừa một chuỗi số là dòng chi mang mã không có trong hệ
	 *    thống tài khoản, và chỗ phát hiện ra là lúc kế toán nhập tệp vào MISA — sau khi kỳ đã
	 *    chốt, và không còn ai nhớ dòng ấy là khoản gì.
	 */
	public static function tkno_da_khai() {
		$s  = self::cfg_static();
		$ra = array();
		foreach ( (array) $s['tkNoMx'] as $row ) {
			foreach ( (array) $row as $ds ) {
				foreach ( (array) $ds as $m ) {
					$m = trim( (string) $m );
					if ( '' !== $m ) { $ra[ $m ] = 1; }
				}
			}
		}
		foreach ( (array) $s['loaiChiPhi'] as $x ) {
			$m = trim( (string) $x['tkNo'] );
			if ( '' !== $m ) { $ra[ $m ] = 1; }
		}
		/* 🔴 ÉP VỀ CHUỖI. Khoá mảng PHP nuốt mọi chuỗi số chính tắc thành SỐ NGUYÊN: gán
		   `$ra['64166']` thì `array_keys()` trả về `64166` (int), và bên gọi so bằng
		   `in_array( $tk, …, true )` — so ngặt — nên KHÔNG BAO GIỜ khớp. Kết quả: mọi mã hợp lệ
		   đều bị từ chối, và câu từ chối lại bảo kế toán "đi khai mã ở Cấu hình" cho một mã
		   đang nằm sờ sờ ở đó. Bẫy này `export_misa()` đã dính một lần rồi. */
		return array_map( 'strval', array_keys( $ra ) );
	}

	/**
	 * Mã này là một TK CÓ đã biết? — 141 (tạm ứng NV) · 331 (phải trả NCC) · mọi TK Có khai ở
	 * ⚙️ Cấu hình → 💳 TK Có theo Phân loại thanh toán và cột TK Có của danh mục loại chi phí.
	 *
	 * 🔴 CÙNG MỘT TẬP VỚI `_tapTkCo()` BÊN GIAO DIỆN. Anh Thắng 10/09/2026: *"loại chi phí nó là
	 *    tài khoản nợ chứ"* — ô chọn hôm ấy bày "TK 331" làm TK Nợ. Hai bên đo khác nhau thì giao
	 *    diện chặn một đằng, máy chủ nhận một nẻo.
	 */
	public static function la_tk_co( $ma ) {
		$ma = trim( (string) $ma );
		if ( '' === $ma ) { return false; }
		$s = self::cfg_static();
		$t = array( '141' => 1, '331' => 1 );
		foreach ( (array) $s['phanloai'] as $x ) {
			$m = trim( (string) $x['tkCo'] );
			if ( '' !== $m ) { $t[ $m ] = 1; }
		}
		foreach ( (array) $s['loaiChiPhi'] as $x ) {
			$m = trim( (string) $x['tkCo'] );
			if ( '' !== $m ) { $t[ $m ] = 1; }
		}
		return isset( $t[ $ma ] );
	}

	/**
	 * Mã đang có trên dòng còn hợp lệ không? Trả lại chính nó nếu còn, ngược lại ''.
	 *
	 * Dùng khi áp lại mã cho dòng cũ: ô nào khai NHIỀU mã thì máy không chọn được hộ,
	 * nhưng mã người nhập đã chọn tay vẫn đúng — phải giữ, không được xóa thành trống.
	 */
	public static function ma_con_hop_le( $loai, $coso, $tk_hien_tai ) {
		$tk = trim( (string) $tk_hien_tai );
		if ( $tk === '' ) { return ''; }
		$ds = self::tkno_mx_list( $loai, $coso );
		if ( count( $ds ) && in_array( $tk, array_map( 'strval', $ds ), true ) ) { return $tk; }
		if ( ! count( $ds ) && self::loai_tk( $loai )['tkNo'] === $tk ) { return $tk; }
		return '';
	}

	/**
	 * Đúng 1 mã thì trả mã đó. Ô khai 2 mã trở lên thì trả '' — người nhập phải chỉ rõ
	 * mã nào (ô chọn loại chi phí tách sẵn từng mã), app không tự đoán hộ.
	 */
	public static function tkno_mx( $loai, $coso ) {
		$ds = self::tkno_mx_list( $loai, $coso );
		return ( count( $ds ) === 1 ) ? (string) $ds[0] : '';
	}

	/** Bỏ đuôi "- NCC" / "- Mua lẻ" khỏi tên nhóm chi phí (đuôi chỉ để lọc, không phải tên loại). */
	public static function bo_duoi_nhom( $nhom ) {
		$s = preg_replace( '/\s*-\s*NCC/iu', '', (string) $nhom );
		$s = preg_replace( '/\s*-\s*Mua l\x{1EBB}/iu', '', $s );
		$s = preg_replace( '/\s{2,}/u', ' ', $s );
		return trim( $s );
	}

	/** Tên nhóm trên dòng chi có thể kèm đuôi -> thử cả dạng gốc lẫn dạng đã bỏ đuôi. */
	public static function ten_nhom_thu( $nhom ) {
		$raw = trim( (string) $nhom );
		$sach = self::bo_duoi_nhom( $raw );
		$out = array();
		if ( $raw !== '' ) { $out[] = $raw; }
		if ( $sach !== '' && $sach !== $raw ) { $out[] = $sach; }
		return $out;
	}

	/**
	 * TK NỢ CỦA MỘT LOẠI CHI PHÍ — đây là NGUỒN THẬT của cột TK Nợ khi xuất MISA.
	 *
	 * Nợ là "chi phí gì", nên phải tra ở danh mục loại chi phí, KHÔNG lấy mã tạm ứng
	 * (141) hay công nợ (331) của bên trả tiền. Dò từ hẹp ra rộng: ma trận
	 * [loại × mảng kinh doanh của cơ sở] trước (cùng loại mà khác mảng thì khác mã),
	 * rồi mới tới mã cố định khai ở danh mục. Không có thì trả '' để chỗ gọi BÁO THIẾU
	 * — không đoán, để không âm thầm hạch toán sai.
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * LOẠI CHI PHÍ NÀY CÓ DÙNG CHO ĐƠN VỊ ẤY KHÔNG.
	 *
	 * Anh Thắng 12/09/2026: *"Đối với POSH sẽ có cột Chi Phí Khác, Chi Phí Chung, Chi Phí Cơ
	 * Sở, Chi Phí Setup"*. Mỗi nhà một bộ loại chi phí riêng — bảng 81 mảng của POSH trước nay
	 * phải bày cả chín cột của KVC, toàn dấu "—", kéo ngang mãi không hết.
	 *
	 * 🔴 CHƯA KHAI ĐƠN VỊ THÌ CHO QUA, y như luật của cột Bộ phận. Danh mục dựng từ sổ cũ nên
	 *    gần như mọi dòng còn bỏ trống ô này; hiểu ngược lại là ngày bản này lên, MỌI bảng mã
	 *    trắng trơn và không ai đoán ra vì sao.
	 *
	 * ⚠️ MỘT LOẠI DÙNG CHO NHIỀU NHÀ được — ngăn nhau bằng dấu phẩy, y như cột Bộ phận. "Chi
	 *    phí Setup" là loại chung, POSH lẫn KVC đều xài.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	public static function loai_thuoc_don_vi( $ten_loai, $don_vi ) {
		$k = mb_strtolower( trim( (string) $ten_loai ) );
		if ( '' === $k ) { return true; }
		foreach ( self::cfg_static_raw_loai() as $x ) {
			if ( mb_strtolower( trim( (string) $x['ten'] ) ) !== $k ) { continue; }
			$dv = trim( (string) ( isset( $x['donVi'] ) ? $x['donVi'] : '' ) );
			if ( '' === $dv ) { return true; }               // chưa khai -> mọi nhà
			foreach ( explode( ',', $dv ) as $d ) {
				if ( '' !== trim( $d ) && VHCP_DonVi::bang( $d, $don_vi ) ) { return true; }
			}
			return false;
		}
		return true;                                        // loại lạ -> không chặn
	}
	/** Danh mục loại chi phí, dạng thô — tách riêng cho `loai_thuoc_don_vi()` khỏi vòng vo. */
	private static function cfg_static_raw_loai() {
		$c = self::get_config();
		return (array) ( isset( $c['loaiChiPhi'] ) ? $c['loaiChiPhi'] : array() );
	}

	public static function tkno_loai( $nhom, $coso = '' ) {
		$ds = self::ten_nhom_thu( $nhom );
		foreach ( $ds as $ten ) {
			$tk = self::tkno_mx( $ten, $coso );
			if ( $tk !== '' ) { return $tk; }
		}
		foreach ( $ds as $ten ) {
			$tk = self::loai_tk( $ten )['tkNo'];
			if ( trim( (string) $tk ) !== '' ) { return trim( (string) $tk ); }
		}
		return '';
	}

	/**
	 * Mã này có phải TÀI KHOẢN CỦA BÊN TRẢ TIỀN không (141 tạm ứng / 331 phải trả NCC)?
	 * Loại mã đó chỉ được nằm ở cột TK Có. Rơi vào cột TK Nợ là hạch toán sai
	 * (bút toán ra "Nợ 141 · Có 141" — đúng thứ anh Thắng thấy trên bảng xuất).
	 */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * MÃ TÀI KHOẢN LÀ MỘT CÂY — LỌC CHA PHẢI ĂN CẢ CON.
	 *
	 * Anh Thắng 12/09/2026: *"cần tìm mã 641 bao nhiêu, thì hệ thống sẽ cộng 6411, 6412,
	 * 6413. Cơ chế nó vậy"*.
	 *
	 * 🔴 KHÔNG CẦN BẢNG CHA–CON. Hệ tài khoản Việt Nam đã mã hoá sẵn quan hệ ấy vào chính con
	 *    số: 641 › 6415 › 64151. Nên "thuộc cây 641" chỉ là "bắt đầu bằng 641". Dựng thêm một
	 *    bảng khai cha–con là thêm một nơi phải khai đúng, và khai lệch thì tiền cộng sai mà
	 *    không ai nhìn ra.
	 *
	 * 🔴 DỌN ĐUÔI `.0` TRƯỚC KHI SO. Bảng tính trả "141.0" cho mã 141; so thẳng thì `'141.0'`
	 *    không bắt đầu bằng `'1411'` mà lại bắt đầu bằng `'141'` — nửa đúng nửa sai tuỳ mã,
	 *    đúng kiểu lỗi không ai truy ra. `VHCP_Util::ma_so()` là chỗ dọn duy nhất.
	 *
	 * ⚠️ CHỈ ĂN THEO ĐỐT, KHÔNG ĂN GIỮA CHỪNG. "64" KHÔNG được coi là cha của "6415" ở đây —
	 *    nghe thì hợp lý, nhưng hệ thống tài khoản không có tài khoản "64", nên cho nó khớp là
	 *    mở đường cho những mã nửa vời do gõ thiếu số. Cha phải là một mã CÓ THẬT: người dùng
	 *    chọn từ ô xổ, và ô ấy chỉ bày mã dựng từ sổ (xem `tk_cha_ds()`).
	 *
	 * @param string $tk  Mã trên dòng chi.
	 * @param string $loc Mã người dùng đang lọc.
	 * @return bool
	 */
	public static function tk_thuoc_cay( $tk, $loc ) {
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ `ma_so()` ở VẾ TRÁI
		   KHÔNG đổi kết quả ca nào — đuôi ".0" nằm ở CUỐI chuỗi, mà phép so tiền tố chỉ nhìn
		   phần ĐẦU, nên "6411.0" vẫn khớp cây "641" y như "6411". Giữ nó vì hai lẽ: nó nói
		   thẳng ra luật "dọn rồi mới so", và nó cứu vế `$a === $b` cùng những đuôi khác ".0"
		   mà bảng tính có thể đẻ ra sau này. VẾ PHẢI thì KHÔNG tương đương — bỏ là lọc
		   "141.0" mất sạch dòng 1411, và bài kiểm đỏ ngay. */
		$a = VHCP_Util::ma_so( $tk );
		$b = VHCP_Util::ma_so( $loc );
		if ( '' === $a || '' === $b ) { return false; }
		if ( $a === $b ) { return true; }
		return 0 === strpos( $a, $b );
	}

	/**
	 * MỌI MÃ CHA suy ra được từ một rổ mã có thật — để ô lọc bày cả `641` chứ không chỉ `6415`.
	 *
	 * 🔴 CHỈ SINH CHA TỪ 3 CHỮ SỐ TRỞ LÊN. Tài khoản cấp 1 của hệ Việt Nam là 3 chữ số; cắt
	 *    ngắn hơn ra "64" hay "6" là bịa ra những mã không tồn tại, và chúng sẽ nằm trong ô xổ
	 *    như thể có thật.
	 *
	 * ⚠️ KHÔNG trả lại chính những mã đã có trong rổ — nơi gọi tự gộp hai danh sách. Trộn sẵn ở
	 *    đây là nơi gọi không còn phân biệt được đâu là mã thật, đâu là mã cha suy ra, mà màn
	 *    cần biết điều đó để ghi chú "gồm cả cây con".
	 *
	 * @param array $ds Danh sách mã có thật.
	 * @return array Mã cha, đã sắp, không trùng, không lẫn mã đã có trong $ds.
	 */
	public static function tk_cha_ds( $ds ) {
		$co  = array();
		foreach ( (array) $ds as $x ) {
			$x = VHCP_Util::ma_so( $x );
			if ( '' !== $x ) { $co[ $x ] = 1; }
		}
		/* 🔴 ÉP LẠI CHUỖI Ở MỌI LƯỢT `array_keys()`. PHP tự đổi khoá mảng toàn số thành int,
		   nên "641" chui ra thành 641 — `ctype_digit(641)` cảnh báo, và `in_array('641', $ds,
		   true)` ở nơi gọi trả false vì lệch kiểu. Đã cắn đúng lượt viết đầu, bài kiểm đỏ. */
		$ra = array();
		foreach ( array_keys( $co ) as $ma ) {
			$ma = (string) $ma;
			if ( ! ctype_digit( $ma ) ) { continue; }   // mã có chữ thì không phải cây số
			for ( $n = 3; $n < strlen( $ma ); $n++ ) {
				$cha = substr( $ma, 0, $n );
				if ( ! isset( $co[ $cha ] ) ) { $ra[ $cha ] = 1; }
			}
		}
		$out = array_map( 'strval', array_keys( $ra ) );
		sort( $out, SORT_NATURAL );
		return $out;
	}

	public static function la_tk_ben_tra( $tk ) {
		$s = trim( (string) $tk );
		return ( $s !== '' && ( strpos( $s, '141' ) === 0 || strpos( $s, '331' ) === 0 ) );
	}

	/**
	 * TK NỢ LÚC XUẤT MISA — luật CHẶT HƠN lúc nhập, và dùng chung cho CẢ 5 ĐƯỜNG XUẤT
	 * (đơn vận hành · sổ chi phí · kỹ thuật · marketing · công tác/setup).
	 *
	 * Vì sao khác lúc nhập: mã nằm trên dòng lúc xuất KHÔNG còn chắc là người nhập gõ tay.
	 * Phần lớn là bản sao chụp danh mục tại thời điểm nhập — kế toán đổi mã của loại chi
	 * phí thì bản sao đó thành cũ; riêng đơn vận hành thì bản sao đó vốn mang sẵn 141 và
	 * đè lên tài khoản chi phí, ra bút toán "Nợ 141 · Có 141".
	 *
	 * Nên: giữ mã trên dòng khi nó CÒN nằm trong các mã danh mục cho phép ở loại đó (ô ma
	 * trận khai 2–3 mã thì người nhập chọn mã nào là chủ ý, phải giữ). Ngoài ra thì lấy mã
	 * hiện hành của loại chi phí. Loại chưa khai mã ở đâu cả mới đành giữ mã trên dòng.
	 * Không có gì thì trả '' để chỗ gọi BÁO THIẾU — không đoán.
	 */
	public static function tkno_xuat( $loai, $coso, $tk_dong ) {
		$tay = trim( (string) $tk_dong );
		if ( self::la_tk_ben_tra( $tay ) ) { $tay = ''; }
		foreach ( self::ten_nhom_thu( $loai ) as $ten ) {
			if ( $tay !== '' && self::ma_con_hop_le( $ten, $coso, $tay ) !== '' ) { return $tay; }
		}
		$tk = self::tkno_loai( $loai, $coso );
		if ( $tk !== '' ) { return $tk; }
		return $tay;
	}

	/**
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 * TK ĐỐI ỨNG (TK CÓ) LÚC XUẤT MISA — ĐỐI XỨNG VỚI `tkno_xuat()` NGAY TRÊN.
	 *
	 * Anh Thắng 21/09/2026: *"MTĐ tùy loại sẽ có TK đối ứng khác"*, kèm ảnh bản MISA mẫu ghi
	 * Nợ 64136 / Có **331** — không phải 141 như đường tạm ứng bên Khu vui chơi.
	 *
	 * 🔴 LỖI NẰM Ở CHỖ HAI CỘT ĐI HAI LUẬT NGƯỢC NHAU. Lúc xuất, TK **Nợ** đọc lại từ DANH MỤC
	 *    và coi mã gắn trên dòng chỉ là bản sao chụp (xem `tkno_xuat()`), còn TK **Có** thì
	 *    ngược hẳn: bản sao trên dòng thắng, danh mục không được hỏi lấy một câu. Nên kế toán
	 *    khai TK đối ứng cho một loại xong, mọi dòng ĐÃ NHẬP TRƯỚC ĐÓ vẫn xuất ra mã cũ — mà
	 *    đúng mấy dòng ấy mới là thứ cần sửa (67 cơ sở MTĐ nạp từ sổ cũ).
	 *
	 * 🔴 KHAI TK ĐỐI ỨNG CHO MỘT LOẠI LÀ MỘT LỜI TUYÊN BỐ, nên nó thắng. Nghĩa của ô ấy là
	 *    "loại này luôn đối ứng vào tài khoản này, bất kể chi bằng hình thức gì" — bỏ trống mới
	 *    là "cứ theo hình thức chi". Đó là lý do nó đứng TRƯỚC bản sao trên dòng.
	 *
	 * ⚠️ BỎ TRỐNG THÌ KHÔNG ĐỔI GÌ CẢ. Loại chưa khai ô này (gần như toàn bộ bên Khu vui chơi)
	 *    rơi xuống đúng hai bậc cũ: bản sao trên dòng, rồi bảng Phân loại thanh toán. Thêm một
	 *    bậc mà làm đổi mã của sổ đang chạy là sai hàng loạt bút toán đã đối chiếu xong.
	 * ⚠️ HỎI DANH MỤC THEO KHỐI CỦA ĐƠN — xem chốt ở `loai_row()`. Hai khối cùng có một loại
	 *    trùng tên là chuyện có thật từ 1.239.0, và tra không phân biệt khối thì TK đối ứng của
	 *    Máy tự động đè lên dòng của Khu vui chơi.
	 * ═══════════════════════════════════════════════════════════════════════════════════════
	 */
	public static function tkco_xuat( $loai, $khoi, $tk_dong, $tk_phan_loai = '' ) {
		$cat = self::loai_row( $loai, $khoi );
		/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: bỏ `trim()` ở dòng dưới
		   KHÔNG đổi kết quả — `VHCP_Cfg::write()` đã cho ô này qua `VHCP_Util::ma_so()`, nên giá
		   trị đọc lên từ kho không bao giờ còn khoảng trắng thừa. Giữ `trim()` vì nó rẻ và vì
		   hàm này là hàm công khai: người gọi sau có thể đưa vào một dòng danh mục dựng tay,
		   chưa qua đường ghi. `kiem-tk-doi-ung-theo-loai.php` có ghi lại phép đột biến ấy. */
		$khai = $cat ? trim( (string) ( isset( $cat['tkCo'] ) ? $cat['tkCo'] : '' ) ) : '';
		if ( '' !== $khai ) { return $khai; }
		$tay = trim( (string) $tk_dong );
		if ( '' !== $tay ) { return $tay; }
		return trim( (string) $tk_phan_loai );
	}

	/** Các cơ sở cùng mảng với cơ sở đã chọn (dùng để báo "mã này áp cho những cơ sở nào"). */
	public static function coso_cung_mang( $coso ) {
		$pll = self::pll_of( $coso );
		$out = array();
		foreach ( self::cfg_static()['coso'] as $x ) {
			if ( $pll === '' ) { continue; }
			if ( mb_strtolower( trim( (string) $x['phanLoaiLon'] ) ) === mb_strtolower( $pll ) ) { $out[] = (string) $x['ten']; }
		}
		if ( ! count( $out ) && trim( (string) $coso ) !== '' ) { $out[] = trim( (string) $coso ); }
		return $out;
	}

	/**
	 * KHAI NHANH: kế toán chọn cơ sở, gõ tên chi phí + số tài khoản là xong.
	 *
	 * Không phải khai trước bảng mảng, không phải mở ma trận: app tự lấy MẢNG KINH DOANH
	 * của cơ sở (cột "Phân loại lớn") rồi ghi mã vào đúng ô ma trận, nên mọi cơ sở cùng
	 * mảng dùng luôn mã đó. Cơ sở chưa khai phân loại lớn thì mã ghi riêng cho cơ sở đó.
	 * Muốn mã chỉ áp cho một cơ sở duy nhất thì đặt $rec['rieng'] = true.
	 *
	 * Dòng chi đã nhập trước đó KHÔNG đổi mã (đã chốt lúc nhập) — muốn áp lại thì bấm
	 * "🔗 Gán mã cho dòng cũ".
	 */
	/* ==========================================================================================
	 *  LOẠI CHI PHÍ THEO CƠ SỞ — nhìn từ phía CƠ SỞ
	 *
	 *  Màn "Khai mã chi phí" đi chiều: MỘT loại chi phí -> tích nhiều cơ sở. Đúng khi khai một
	 *  khoản mới cho cả hệ. Nhưng lúc MỞ GIAN MỚI thì câu hỏi ngược lại: "gian này dùng những
	 *  loại nào?" — và đi chiều kia là phải mở từng loại một, tích lại từng cái.
	 *
	 *  Hậu quả thật 25/08/2026: gian ADV GO! AN LẠC vừa mở, ô "Loại chi phí" lúc nhập đơn chỉ
	 *  có đúng một dòng, nhân viên không nhập được gì mà cũng không biết vì sao.
	 *
	 *  Ma trận CH_TKNo lưu [tên loại][CỘT] -> mã TK, trong đó CỘT là tên MẢNG hoặc tên CƠ SỞ.
	 *  Ô theo cơ sở ĐÈ ô theo mảng (xem _tkNoList bên giao diện).
	 * ========================================================================================== */

	/** Mảng kinh doanh của một cơ sở. */
	public static function mang_cua( $coso ) {
		$k = mb_strtolower( trim( (string) $coso ) );
		$m = self::cfg_static();
		return isset( $m['cosoPll'][ $k ] ) ? (string) $m['cosoPll'][ $k ] : '';
	}

	/**
	 * loaiCuaCoSo(cơ sở): mọi loại chi phí, kèm loại nào cơ sở này đang dùng và mã nào.
	 *
	 * `nguon`:  'coso' = khai riêng cho gian này · 'mang' = ăn theo mảng · '' = chưa dùng.
	 */
	public static function loai_cua_coso( $coso ) {
		$coso = trim( (string) $coso );
		if ( '' === $coso ) { return VHCP_Util::err( 'Chưa chọn cơ sở' ); }
		$mang = self::mang_cua( $coso );
		$kc   = mb_strtolower( $coso );
		$km   = mb_strtolower( $mang );

		$o_coso = array();
		$o_mang = array();
		foreach ( self::read( self::TKNO ) as $r ) {
			$r  = array_values( (array) $r );
			$n  = mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) );
			$c  = mb_strtolower( trim( (string) ( isset( $r[1] ) ? $r[1] : '' ) ) );
			$v  = trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) );
			if ( '' === $n || '' === $c ) { continue; }
			if ( $c === $kc ) { $o_coso[ $n ] = $v; }
			elseif ( '' !== $km && $c === $km ) { $o_mang[ $n ] = $v; }
		}

		$ds = array();
		foreach ( self::cfg_static()['loaiChiPhi'] as $l ) {
			$ten = (string) $l['ten'];
			$k   = mb_strtolower( trim( $ten ) );
			if ( '' === $k ) { continue; }
			$co_cs = array_key_exists( $k, $o_coso );
			$ds[]  = array(
				'ten'     => $ten,
				'boPhan'  => isset( $l['boPhan'] ) ? $l['boPhan'] : '',
				'tenMisa' => isset( $l['tenMisa'] ) ? $l['tenMisa'] : '',
				'ma'      => $co_cs ? $o_coso[ $k ] : ( isset( $o_mang[ $k ] ) ? $o_mang[ $k ] : '' ),
				'maMang'  => isset( $o_mang[ $k ] ) ? $o_mang[ $k ] : '',
				'nguon'   => $co_cs ? 'coso' : ( isset( $o_mang[ $k ] ) ? 'mang' : '' ),
			);
		}
		return array( 'coso' => $coso, 'mang' => $mang, 'ds' => $ds );
	}

	/**
	 * datLoaiChoCoSo(cơ sở, danh sách): ghi lại loại nào gian này dùng, mã nào.
	 *
	 * 🔴 CHỈ ĐỤNG Ô CỦA CHÍNH CƠ SỞ NÀY. Ô của MẢNG là của cả mảng — sửa ở đây là lặng lẽ đổi
	 *    cho mọi gian cùng mảng. Loại nào đang ăn theo mảng mà bỏ tích thì KHÔNG bỏ được ở đây;
	 *    trả về danh sách đó để màn hình nói thẳng "phải bỏ ở mảng", thay vì bấm xong không
	 *    thấy gì đổi rồi tưởng hệ hỏng.
	 */
	public static function dat_loai_cho_coso( $coso, $ds ) {
		$coso = trim( (string) $coso );
		if ( '' === $coso ) { return VHCP_Util::err( 'Chưa chọn cơ sở' ); }
		$kc   = mb_strtolower( $coso );
		$mang = self::mang_cua( $coso );
		$km   = mb_strtolower( $mang );

		/* Muốn gì cho từng loại. */
		$muon = array();
		foreach ( (array) $ds as $x ) {
			$x = (array) $x;
			$t = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $t ) { continue; }
			$muon[ mb_strtolower( $t ) ] = array(
				'ten'  => $t,
				'dung' => ! empty( $x['dung'] ),
				'ma'   => trim( (string) ( isset( $x['ma'] ) ? $x['ma'] : '' ) ),
			);
		}

		$mx      = array();
		$o_mang  = array();
		$da_sua  = array();
		$them = 0; $doi = 0; $bo = 0;

		foreach ( self::read( self::TKNO ) as $r ) {
			$r = array_values( (array) $r );
			$n = trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) );
			$c = trim( (string) ( isset( $r[1] ) ? $r[1] : '' ) );
			$v = trim( (string) ( isset( $r[2] ) ? $r[2] : '' ) );
			if ( '' === $n || '' === $c ) { continue; }
			$kn = mb_strtolower( $n );
			if ( '' !== $km && mb_strtolower( $c ) === $km ) { $o_mang[ $kn ] = $v; }

			if ( mb_strtolower( $c ) !== $kc || ! isset( $muon[ $kn ] ) ) { $mx[] = array( $n, $c, $v ); continue; }

			$w = $muon[ $kn ];
			$da_sua[ $kn ] = 1;
			if ( ! $w['dung'] ) { $bo++; continue; }              // bỏ hẳn dòng của cơ sở này
			if ( '' === $w['ma'] ) { $mx[] = array( $n, $c, $v ); continue; }
			if ( $w['ma'] !== $v ) { $doi++; }
			$mx[] = array( $n, $c, $w['ma'] );
		}

		/* Loại được tích mà chưa có ô riêng -> thêm ô mới cho cơ sở này. */
		$thieu_ma = array();
		foreach ( $muon as $kn => $w ) {
			if ( isset( $da_sua[ $kn ] ) || ! $w['dung'] ) { continue; }
			$ma = ( '' !== $w['ma'] ) ? $w['ma'] : ( isset( $o_mang[ $kn ] ) ? $o_mang[ $kn ] : '' );
			if ( '' === $ma ) { $thieu_ma[] = $w['ten']; continue; }
			/* Đang ăn theo mảng và mã y hệt -> khỏi đẻ thêm ô riêng, để cơ sở tiếp tục theo mảng. */
			if ( isset( $o_mang[ $kn ] ) && $o_mang[ $kn ] === $ma ) { continue; }
			$mx[] = array( $w['ten'], $coso, $ma );
			$them++;
		}

		/* Bỏ tích một loại đang ăn theo MẢNG -> ô đó không thuộc cơ sở này, không bỏ được ở đây. */
		$khong_bo = array();
		foreach ( $muon as $kn => $w ) {
			if ( $w['dung'] || ! isset( $o_mang[ $kn ] ) ) { continue; }
			$khong_bo[] = $w['ten'] ;
		}

		self::write( self::TKNO, $mx );
		self::clear_cache();
		return VHCP_Util::ok( array(
			'them'     => $them,
			'doi'      => $doi,
			'bo'       => $bo,
			'mang'     => $mang,
			'thieuMa'  => $thieu_ma,
			'khongBo'  => $khong_bo,
		) );
	}

	public static function khai_cho_coso( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? trim( (string) $rec[ $k ] ) : ''; };
		$ten  = $g( 'ten' );
		$tkno = $g( 'tkNo' );
		if ( $ten === '' ) { return VHCP_Util::err( 'Nhập tên gọi chi phí' ); }
		if ( $tkno === '' ) { return VHCP_Util::err( 'Nhập số tài khoản (TK Nợ)' ); }

		// Cột ma trận sẽ ghi: từng CƠ SỞ được tích, và/hoặc cả MẢNG (áp luôn cho cơ sở mở sau)
		$lay = function ( $key ) use ( $rec ) {
			$out = array();
			foreach ( (array) ( isset( $rec[ $key ] ) ? $rec[ $key ] : array() ) as $v ) {
				$v = trim( (string) $v );
				if ( $v !== '' ) { $out[ $v ] = 1; }
			}
			return array_keys( $out );
		};
		$cosos = $lay( 'cosos' );
		$mangs = $lay( 'mangs' );
		if ( $g( 'coso' ) !== '' ) { $cosos[] = $g( 'coso' ); }   // tương thích lời gọi 1 cơ sở
		if ( ! count( $cosos ) && ! count( $mangs ) ) { return VHCP_Util::err( 'Tích ít nhất 1 cơ sở (hoặc 1 mảng) để áp mã' ); }

		$k = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };

		// Cơ sở nào đã nằm trong mảng được tích thì khỏi ghi riêng cho nó nữa
		$mang_set = array();
		foreach ( $mangs as $m ) { $mang_set[ $k( $m ) ] = 1; }
		$giu = array();
		foreach ( $cosos as $c ) {
			if ( isset( $mang_set[ $k( self::pll_of( $c ) ) ] ) ) { continue; }
			$giu[] = $c;
		}
		$cosos = $giu;

		// Số tài khoản này có trong hệ thống tài khoản không? Tên của nó là tên TK NỘI BỘ.
		$ten_tk = '';
		foreach ( self::tai_khoan() as $x ) {
			if ( (string) $x['ma'] === $tkno ) { $ten_tk = $x['ten']; break; }
		}

		// 1) danh mục loại chi phí: thêm nếu chưa có, điền ô trống, KHÔNG ghi đè
		$rows = array(); $vt = null;
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			if ( $k( $row[0] ) === $k( $ten ) ) { $vt = count( $rows ); }
			$rows[] = $row;
		}
		$loai_moi = false;
		$tenmisa  = $g( 'tenMisa' ) !== '' ? $g( 'tenMisa' ) : $ten_tk;
		if ( $vt === null ) {
			$rows[]   = array( $ten, '', $g( 'tkCo' ), $g( 'maDt' ), $g( 'boPhan' ), '', $tenmisa, $g( 'loaiTt' ) );
			$loai_moi = true;
		} else {
			// Tên MISA gõ tay thì ghi đè (đây là chỗ chỉnh nội dung xuất MISA), còn lại chỉ điền ô trống
			if ( $g( 'tenMisa' ) !== '' ) { $rows[ $vt ][6] = $g( 'tenMisa' ); }
			foreach ( array( 2 => $g( 'tkCo' ), 3 => $g( 'maDt' ), 4 => $g( 'boPhan' ), 6 => $tenmisa ) as $i => $v ) {
				if ( $v !== '' && trim( (string) $rows[ $vt ][ $i ] ) === '' ) { $rows[ $vt ][ $i ] = $v; }
			}
		}
		self::write( self::LOAI, $rows );

		// 2) ma trận: 1 ô cho mỗi cột được tích — khai rõ ràng nên ghi đè được
		$mx = array(); $vi_tri = array();
		foreach ( self::read( self::TKNO ) as $r ) {
			$n0 = trim( (string) $r[0] ); $p0 = trim( (string) $r[1] ); $v0 = trim( (string) $r[2] );
			if ( $n0 === '' || $p0 === '' ) { continue; }
			$vi_tri[ $k( $n0 ) . '|' . $k( $p0 ) ] = count( $mx );
			$mx[] = array( $n0, $p0, $v0 );
		}
		$them  = ! empty( $rec['them'] );   // thêm mã nữa cho ô đó (1 chi phí 2 mã), không thay mã cũ
		$ma_cu = array(); $o_moi = 0; $o_doi = 0; $o_them = 0;
		foreach ( array_merge( $mangs, $cosos ) as $cot ) {
			$key = $k( $ten ) . '|' . $k( $cot );
			if ( isset( $vi_tri[ $key ] ) ) {
				$cu = trim( (string) $mx[ $vi_tri[ $key ] ][2] );
				$ds = array();
				foreach ( explode( '|', $cu ) as $m ) { $m = trim( $m ); if ( $m !== '' ) { $ds[] = $m; } }
				if ( $them ) {
					if ( in_array( $tkno, $ds, true ) ) { continue; }   // ô đã có mã này
					$ds[] = $tkno;
					$mx[ $vi_tri[ $key ] ] = array( $ten, $cot, implode( ' | ', $ds ) );
					if ( count( $ds ) > 1 ) { $o_them++; } else { $o_moi++; }
					continue;
				}
				// Không tích "thêm": ô chỉ còn đúng mã vừa khai (kể cả ô đang có nhiều mã)
				if ( count( $ds ) === 1 && $ds[0] === $tkno ) { continue; }
				if ( $cu !== '' ) { $ma_cu[ $cu ] = 1; $o_doi++; }
				else { $o_moi++; }
				$mx[ $vi_tri[ $key ] ] = array( $ten, $cot, $tkno );
				continue;
			}
			$mx[] = array( $ten, $cot, $tkno );
			$vi_tri[ $key ] = count( $mx ) - 1;
			$o_moi++;
		}
		self::write( self::TKNO, $mx );
		self::clear_cache();

		// Danh sách cơ sở thật sự ăn mã này (gồm cơ sở thuộc các mảng được tích)
		$ap_dung = array();
		foreach ( self::cfg_static()['coso'] as $x ) {
			$ten_cs = (string) $x['ten'];
			if ( isset( $mang_set[ $k( $x['phanLoaiLon'] ) ] ) ) { $ap_dung[ $ten_cs ] = 1; continue; }
			foreach ( $cosos as $c ) { if ( $k( $c ) === $k( $ten_cs ) ) { $ap_dung[ $ten_cs ] = 1; } }
		}
		foreach ( $cosos as $c ) { if ( ! isset( $ap_dung[ $c ] ) ) { $ap_dung[ $c ] = 1; } }

		return VHCP_Util::ok( array(
			'loai'        => $ten,
			'loaiMoi'     => $loai_moi,
			'tkNo'        => $tkno,
			'tenTaiKhoan' => $ten_tk,
			'tenMisa'     => self::ten_misa_loai( $ten ),
			'laTkLa'      => ( $ten_tk === '' && count( self::tai_khoan() ) > 0 ),
			'cot'         => array_merge( $mangs, $cosos ),
			'oMoi'        => $o_moi,
			'oDoi'        => $o_doi,
			'oThem'       => $o_them,
			'maCu'        => array_map( 'strval', array_keys( $ma_cu ) ),
			'apDung'      => array_keys( $ap_dung ),
		) );
	}

	// ------------------------------------------------ hệ thống tài khoản của kế toán

	/** Hệ thống tài khoản đã nạp: [ ['ma'=>, 'ten'=>, 'tinhChat'=>], ... ] */
	public static function tai_khoan() {
		$out = array();
		foreach ( self::read( self::TK ) as $r ) {
			$ma = trim( (string) $r[0] );
			if ( $ma === '' ) { continue; }
			$out[] = array( 'ma' => $ma, 'ten' => trim( (string) $r[1] ), 'tinhChat' => trim( (string) $r[2] ) );
		}
		return $out;
	}

	/** Hệ thống tài khoản + bảng mảng, cho tab Cấu hình (gợi ý mã khi khai danh mục). */
	public static function get_tai_khoan() {
		return VHCP_Util::ok( array( 'taiKhoan' => self::tai_khoan(), 'mangTk' => self::mang_tk() ) );
	}

	/** Bảng mảng kinh doanh: phân loại lớn -> nhóm TK (VD 6412) + từ khóa trong tên TK (VD Funzone). */
	public static function mang_tk() {
		$out = array();
		foreach ( self::read( self::MANG ) as $r ) {
			$pll = trim( (string) $r[0] );
			if ( $pll === '' ) { continue; }
			$out[] = array( 'pll' => $pll, 'nhomTk' => trim( (string) $r[1] ), 'tuKhoa' => trim( (string) $r[2] ), 'note' => trim( (string) $r[3] ) );
		}
		return $out;
	}

	/** Bỏ dấu + hạ chữ + gom khoảng trắng, để so tên mảng với tên phân loại lớn. */
	public static function kd( $s ) {
		$s = mb_strtolower( trim( (string) $s ) );
		$map = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $map as $plain => $accented ) {
			$chars = preg_split( '//u', $accented, -1, PREG_SPLIT_NO_EMPTY );
			$s     = str_replace( $chars, $plain, $s );
		}
		return trim( preg_replace( '/\s{2,}/u', ' ', $s ) );
	}

	/**
	 * DÒ BẢNG MẢNG KINH DOANH TỪ HỆ THỐNG TÀI KHOẢN — khỏi khai tay.
	 *
	 * Trong file kế toán, mỗi mảng là 1 tài khoản cha có tài khoản con bên dưới:
	 *   6412 Chi phí Funzone -> 64121 Chi phí lương Funzone · 64125 Chi phí setup Funzone…
	 * Nên bỏ chữ "Chi phí" khỏi tên tài khoản cha là ra TỪ KHÓA của mảng ("Funzone"),
	 * và số hiệu cha là NHÓM TK ("6412"). Phần app không tự biết được là mảng đó ứng với
	 * "Phân loại lớn" nào của cơ sở, nên chỗ nào ghép được theo tên thì điền sẵn, chỗ nào
	 * không thì để trống cho người khai chọn (không đoán bừa mã hạch toán).
	 *
	 * KHÔNG ghi vào cấu hình — chỉ trả đề xuất để xem rồi bấm Lưu.
	 */
	public static function do_mang_tu_tk( $goc = '641' ) {
		$chart = self::tai_khoan();
		if ( ! count( $chart ) ) { return VHCP_Util::err( 'Chưa nạp hệ thống tài khoản (wp-admin → Vận Hành Chi Phí → Nhập dữ liệu → CH_TaiKhoan)' ); }
		$goc = trim( (string) $goc );

		// Tài khoản cha = có ít nhất 1 tài khoản con trong hệ thống.
		// Giữ danh sách mã dạng LIST (không dùng làm khóa mảng): khóa mảng PHP tự đổi
		// "64121" thành số nguyên, so sánh với chuỗi sẽ luôn khác nhau -> dòng con tự
		// nhận là cha của chính nó.
		$ma_list = array();
		foreach ( $chart as $x ) { $ma_list[] = (string) $x['ma']; }
		$cha = array();
		foreach ( $chart as $x ) {
			$ma = (string) $x['ma'];
			if ( $goc !== '' && strpos( $ma, $goc ) !== 0 ) { continue; }
			if ( $ma === $goc ) { continue; }
			$co_con = false;
			foreach ( $ma_list as $m2 ) {
				if ( $m2 !== $ma && strpos( $m2, $ma ) === 0 ) { $co_con = true; break; }
			}
			if ( ! $co_con ) { continue; }
			$tu = trim( preg_replace( '/^\s*chi\s*ph[íi]\s*/iu', '', $x['ten'] ) );
			if ( $tu === '' ) { continue; }
			$cha[] = array( 'nhomTk' => $ma, 'tuKhoa' => $tu, 'tenTk' => $x['ten'] );
		}
		if ( ! count( $cha ) ) { return VHCP_Util::err( 'Không thấy nhóm tài khoản nào dưới ' . $goc . ' có tài khoản con' ); }

		// Danh sách phân loại lớn đang khai ở bảng Cơ sở
		$plls = array();
		foreach ( self::read( self::COSO ) as $r ) {
			$v = trim( (string) $r[2] );
			if ( $v !== '' ) { $plls[ $v ] = 1; }
		}
		$plls = array_keys( $plls );

		// Đã khai rồi thì không đề xuất lại
		$da_co = array();
		foreach ( self::mang_tk() as $m ) { $da_co[ self::kd( $m['pll'] ) . '|' . $m['nhomTk'] ] = 1; }

		$rows = array(); $chua_ghep = array();
		foreach ( $cha as $c ) {
			$tu_kd = self::kd( $c['tuKhoa'] );
			$hit   = array();
			foreach ( $plls as $pll ) {
				$p = self::kd( $pll );
				// khớp khi tên phân loại lớn có chứa từ khóa mảng (hoặc ngược lại)
				if ( $tu_kd !== '' && ( strpos( $p, $tu_kd ) !== false || strpos( $tu_kd, $p ) !== false ) ) { $hit[] = $pll; }
			}
			if ( ! count( $hit ) ) {
				$chua_ghep[] = $c['nhomTk'] . ' · ' . $c['tuKhoa'];
				$rows[] = array( 'pll' => '', 'nhomTk' => $c['nhomTk'], 'tuKhoa' => $c['tuKhoa'], 'note' => 'chọn mảng cho ' . $c['tenTk'] );
				continue;
			}
			foreach ( $hit as $pll ) {
				if ( isset( $da_co[ self::kd( $pll ) . '|' . $c['nhomTk'] ] ) ) { continue; }
				$rows[] = array( 'pll' => $pll, 'nhomTk' => $c['nhomTk'], 'tuKhoa' => $c['tuKhoa'], 'note' => $c['tenTk'] );
			}
		}

		return VHCP_Util::ok( array(
			'rows'      => $rows,
			'chuaGhep'  => $chua_ghep,
			'soNhom'    => count( $cha ),
			'soPll'     => count( $plls ),
		) );
	}

	/**
	 * GHÉP HỆ THỐNG TÀI KHOẢN VÀO DANH MỤC LOẠI CHI PHÍ.
	 *
	 * Tài khoản của kế toán đặt tên theo kiểu "Chi phí <hạng mục> <mảng>"
	 * (VD 64121 Chi phí lương Funzone · 64161 Chi phí lương Farm), nên:
	 *   - Bỏ từ khóa mảng khỏi tên -> ra TÊN LOẠI CHI PHÍ dùng chung ("Chi phí lương").
	 *   - Số hiệu tài khoản của từng mảng -> 1 ô trong MA TRẬN [loại] × [phân loại lớn].
	 * Tài khoản KHÔNG thuộc nhóm mảng nào (6423 đồ dùng văn phòng, 6427 dịch vụ mua ngoài,
	 * 811 chi phí khác…) thì tên giữ nguyên và TK Nợ là mã cố định, mảng nào cũng dùng chung.
	 *
	 * Chỉ THÊM và ĐIỀN Ô TRỐNG: loại chi phí anh tự thêm và mã anh đã sửa tay không bị đụng.
	 *
	 * @param array $opts ['dungChung' => array các số hiệu TK dùng chung cần thêm]
	 */
	public static function ghep_he_thong_tk( $opts = array() ) {
		$opts = (array) $opts;
		$chart = self::tai_khoan();
		$mang  = self::mang_tk();
		if ( ! count( $chart ) ) { return VHCP_Util::err( 'Chưa nạp hệ thống tài khoản (⚙️ Cấu hình → nạp CSV → CH_TaiKhoan)' ); }
		if ( ! count( $mang ) ) { return VHCP_Util::err( 'Chưa khai bảng "Mảng kinh doanh → nhóm TK" nên chưa biết tài khoản nào thuộc mảng nào' ); }

		$k = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };

		// 1) Từ hệ thống TK + bảng mảng -> các ô ma trận cần có.
		$mx_new    = array();   // [loại chi phí] [phân loại lớn] = số hiệu
		$ten_cua   = array();   // khóa hạ chữ -> tên loại chi phí hiển thị
		$bo_qua_tk = 0;
		foreach ( $mang as $m ) {
			$nhom  = $m['nhomTk'];
			$tu    = $m['tuKhoa'];
			if ( $nhom === '' || $tu === '' ) { continue; }
			foreach ( $chart as $tk ) {
				if ( $tk['ma'] === $nhom || strpos( $tk['ma'], $nhom ) !== 0 ) { continue; }   // chỉ tài khoản con
				$ten = $tk['ten'];
				// bỏ từ khóa mảng ở bất kỳ đâu trong tên, rồi dọn dấu và khoảng trắng dư
				$sach = preg_replace( '/\s*' . preg_quote( $tu, '/' ) . '\s*/iu', ' ', $ten );
				$sach = trim( preg_replace( '/\s{2,}/u', ' ', (string) $sach ) );
				$sach = trim( $sach, " -–—_" );
				if ( $sach === '' || $k( $sach ) === $k( $ten ) ) { $bo_qua_tk++; continue; }   // không nhận ra mảng trong tên
				$kk = $k( $sach );
				if ( ! isset( $ten_cua[ $kk ] ) ) { $ten_cua[ $kk ] = $sach; }
				$mx_new[ $kk ][ $k( $m['pll'] ) ] = array( 'pll' => $m['pll'], 'ma' => $tk['ma'] );
			}
		}

		// 2) Các tài khoản dùng chung (không theo mảng) -> loại chi phí có mã cố định.
		$chung = array();
		$want  = array();
		foreach ( (array) ( isset( $opts['dungChung'] ) ? $opts['dungChung'] : array() ) as $x ) {
			$x = trim( (string) $x );
			if ( $x !== '' ) { $want[ $x ] = 1; }
		}
		foreach ( $chart as $tk ) {
			if ( ! isset( $want[ $tk['ma'] ] ) ) { continue; }
			if ( $tk['ten'] === '' ) { continue; }
			$chung[ $k( $tk['ten'] ) ] = array( 'ten' => $tk['ten'], 'ma' => $tk['ma'] );
		}

		// 3) Bổ sung danh mục loại chi phí (giữ nguyên dòng đã có).
		$rows = array(); $co = array();
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			$co[ $k( $row[0] ) ] = count( $rows );
			$rows[] = $row;
		}
		$them = 0; $sua = 0;
		foreach ( $ten_cua as $kk => $ten ) {
			if ( isset( $co[ $kk ] ) ) { continue; }
			$rows[] = array( $ten, '', '', '', '', '', '', '' );   // TK Nợ trống: lấy theo ma trận
			$co[ $kk ] = count( $rows ) - 1;
			$them++;
		}
		foreach ( $chung as $kk => $x ) {
			if ( isset( $co[ $kk ] ) ) {
				$i = $co[ $kk ];
				if ( trim( (string) $rows[ $i ][1] ) === '' ) { $rows[ $i ][1] = $x['ma']; $sua++; }
				continue;
			}
			$rows[] = array( $x['ten'], $x['ma'], '', '', '', '', '', '' );
			$co[ $kk ] = count( $rows ) - 1;
			$them++;
		}
		if ( $them || $sua ) { self::write( self::LOAI, $rows ); }

		// 4) Bổ sung ma trận (không ghi đè ô đã có mã).
		$cu = array(); $mx_rows = array();
		foreach ( self::read( self::TKNO ) as $r ) {
			$n0 = trim( (string) $r[0] ); $p0 = trim( (string) $r[1] ); $v0 = trim( (string) $r[2] );
			if ( $n0 === '' || $p0 === '' ) { continue; }
			$cu[ $k( $n0 ) . '|' . $k( $p0 ) ] = count( $mx_rows );
			$mx_rows[] = array( $n0, $p0, $v0 );
		}
		$o_them = 0;
		foreach ( $mx_new as $kk => $per_pll ) {
			$ten = $ten_cua[ $kk ];
			foreach ( $per_pll as $kp => $x ) {
				$key = $kk . '|' . $kp;
				if ( isset( $cu[ $key ] ) ) {
					if ( trim( (string) $mx_rows[ $cu[ $key ] ][2] ) !== '' ) { continue; }   // đã khai tay -> giữ
					$mx_rows[ $cu[ $key ] ][2] = $x['ma'];
					$o_them++;
					continue;
				}
				$mx_rows[] = array( $ten, $x['pll'], $x['ma'] );
				$cu[ $key ] = count( $mx_rows ) - 1;
				$o_them++;
			}
		}
		if ( $o_them ) { self::write( self::TKNO, $mx_rows ); }
		self::clear_cache();

		return VHCP_Util::ok( array(
			'themLoai'    => $them,
			'suaLoai'     => $sua,
			'oMaTran'     => $o_them,
			'tongLoai'    => count( $rows ),
			'boQuaTaiKhoan' => $bo_qua_tk,
		) );
	}

	/**
	 * THÊM LOẠI CHI PHÍ CÒN THIẾU VÀO DANH MỤC (chỉ tên, chưa có mã).
	 *
	 * Nạp dữ liệu cũ sinh ra tên loại chi phí lấy từ chính bảng tính ("Nhân Công",
	 * "Vật tư Khánh Thảo"…). Nếu không đưa vào danh mục thì chúng KHÔNG hiện ở bảng ma
	 * trận lẫn ô chọn lúc nhập — nghĩa là không có cách nào khai mã cho chúng, và nút
	 * "Gán mã cho dòng cũ" cũng chẳng có gì để dò. Vào danh mục rồi thì khai mã như
	 * bình thường.
	 *
	 * @return int số loại vừa thêm
	 */
	public static function them_loai_neu_thieu( $tens ) {
		$k  = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };
		$co = array();
		$rows = array();
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			$co[ $k( $row[0] ) ] = 1;
			$rows[] = $row;
		}
		// Bỏ tiền tố "Chi phí " để nhận ra TRÙNG: bảng tính cũ ghi hạng mục là "Tháo dỡ",
		// "Vận hành", trong khi danh mục đã có "Chi phí tháo dỡ", "Chi phí vận hành". Thêm
		// cả hai là danh mục có hai dòng cùng nghĩa, nhân viên chọn lộn mà kế toán phải khai
		// mã hai lần. So khớp chính xác sau khi bỏ tiền tố, không dò mờ — "Chi phí khác" và
		// "Chi phí khác - Event" vẫn là hai loại riêng.
		$goc = function ( $v ) use ( $k ) {
			$x = $k( $v );
			return preg_replace( '/^chi\s*ph[ií]\s+/u', '', $x );
		};
		$co_goc = array();
		foreach ( array_keys( $co ) as $ten_co ) { $co_goc[ $goc( $ten_co ) ] = 1; }

		$them = 0;
		foreach ( (array) $tens as $t ) {
			$t = trim( (string) $t );
			if ( $t === '' || isset( $co[ $k( $t ) ] ) ) { continue; }
			if ( isset( $co_goc[ $goc( $t ) ] ) ) { continue; }   // đã có loại cùng nghĩa
			$co[ $k( $t ) ] = 1;
			$co_goc[ $goc( $t ) ] = 1;
			$rows[] = array( $t, '', '', '', '', '(nạp từ dữ liệu cũ)', '', '' );
			$them++;
		}
		if ( $them ) {
			self::write( self::LOAI, $rows );
			self::clear_cache();
		}
		return $them;
	}

	/** Loại này đã được khai mã ở BẤT KỲ mảng nào trong ma trận chưa? */
	private static function loai_co_ma_trong_mx( $loai ) {
		$s = self::cfg_static();
		$k = mb_strtolower( trim( (string) $loai ) );
		if ( $k === '' || ! isset( $s['tkNoMx'][ $k ] ) ) { return false; }
		foreach ( (array) $s['tkNoMx'][ $k ] as $ds ) {
			foreach ( (array) $ds as $ma ) { if ( trim( (string) $ma ) !== '' ) { return true; } }
		}
		return false;
	}

	/**
	 * DỌN CÁC LOẠI CHI PHÍ CHƯA KHAI MÃ.
	 *
	 * Nạp dữ liệu cũ thì mỗi tên hạng mục lạ đều được thêm vào danh mục để còn khai mã
	 * được cho nó. Phần lớn không phải loại chi phí ("Nguyễn Hữu Thọ, Nguyễn Bá Tuấn",
	 * "Cấp Mạng VNPT") nên danh mục phình ra vài trăm dòng rác.
	 *
	 * Chỉ xóa dòng chưa có mã nào (cả mã cố định lẫn mã trong ma trận). Đã khai mã nghĩa
	 * là kế toán đã nhận nó là loại thật — giữ lại. Dòng chi phí cũ không bị ảnh hưởng:
	 * tên loại vẫn nằm trên từng dòng, xóa danh mục chỉ là dọn ô chọn.
	 *
	 * @return array [ 'xoa' => số dòng đã xóa, 'giu' => số dòng giữ lại, 'ten' => tên đã xóa ]
	 */
	public static function xoa_loai_tu_tao() {
		$rows = array(); $xoa = array(); $giu = 0;
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			$ten = trim( (string) $row[0] );
			if ( $ten === '' ) { continue; }
			// Mốc là CHƯA CÓ MÃ, không dựa vào ghi chú "(nạp từ dữ liệu cũ)": bảng ma trận
			// trên giao diện không có cột Ghi chú nên lưu bảng đó một lần là dấu đó bay hết.
			// Loại chưa có mã ở đâu cả thì chọn cũng ra dòng không có TK Nợ — chưa dùng được.
			$co_ma = ( trim( (string) $row[1] ) !== '' ) || self::loai_co_ma_trong_mx( $ten );
			if ( ! $co_ma ) { $xoa[] = $ten; continue; }
			$rows[] = $row;
			$giu++;
		}
		if ( count( $xoa ) ) {
			self::write( self::LOAI, $rows );
			self::clear_cache();
		}
		return array( 'xoa' => count( $xoa ), 'giu' => $giu, 'ten' => array_slice( $xoa, 0, 40 ) );
	}

	/** Tên dùng cho diễn giải MISA của 1 loại chi phí (để trống = dùng chính tên loại). */
	public static function ten_misa_loai( $loai ) {
		$t = self::loai_tk( $loai );
		return $t['tenMisa'] !== '' ? $t['tenMisa'] : trim( (string) $loai );
	}

	/**
	 * CHỐT MÃ TÀI KHOẢN cho 1 dòng chi ở BẤT KỲ mảng nào (sổ chi phí, đơn vận hành,
	 * kỹ thuật, marketing, công tác/setup).
	 *
	 * TK Nợ theo thứ tự ưu tiên:
	 *   1) mã người nhập gõ tay trên dòng ($override)
	 *   2) MA TRẬN [loại chi phí] × [phân loại lớn của cơ sở] — cùng loại chi phí mà
	 *      khác mảng kinh doanh thì khác mã, nên đây là mã sát nhất
	 *   3) mã cố định khai ở danh mục LOẠI CHI PHÍ (dùng cho loại mảng nào cũng 1 mã)
	 *   4) để trống + báo thiếu (KHÔNG đoán, để không âm thầm hạch toán sai)
	 *
	 * TK Có / Mã đối tượng: gõ tay -> danh mục -> mặc định theo hình thức chi
	 * ("Trực tiếp…" -> 331 · còn lại -> 141).
	 */
	public static function resolve_tk( $loai, $hinh_thuc = '', $override = array(), $coso = '' ) {
		$override = (array) $override;
		$ov = function ( $k ) use ( $override ) { return isset( $override[ $k ] ) ? trim( (string) $override[ $k ] ) : ''; };
		$cat = self::loai_tk( $loai );

		// LÚC NHẬP: mã người nhập GÕ TAY thắng — họ đang ngồi trước màn hình và biết dòng
		// này đặc thù. Chỉ chặn mã của BÊN TRẢ TIỀN (141/331): mã đó thuộc cột Có, lọt vào
		// cột Nợ là bút toán vô nghĩa, không ai cố ý gõ vào đây cả.
		// (Lúc XUẤT thì luật chặt hơn — xem tkno_xuat().)
		$tay   = $ov( 'tkNo' );
		if ( self::la_tk_ben_tra( $tay ) ) { $tay = ''; }
		$tk_no = $tay;
		if ( $tk_no === '' ) { $tk_no = self::tkno_loai( $loai, $coso ); }
		$tk_co = $ov( 'tkCo' ) !== '' ? $ov( 'tkCo' ) : $cat['tkCo'];
		$ma_dt = $ov( 'maDt' ) !== '' ? $ov( 'maDt' ) : $cat['maDt'];

		if ( $tk_co === '' ) {
			$is_tt = ( mb_strpos( trim( (string) $hinh_thuc ), 'Trực tiếp' ) === 0 );
			$pl    = $is_tt ? 'Nhà cung cấp' : 'Thanh toán cá nhân';
			$s     = self::cfg_static();
			foreach ( (array) $s['phanloai'] as $x ) {
				if ( trim( (string) $x['ten'] ) === $pl ) { $tk_co = (string) $x['tkCo']; break; }
			}
			if ( $tk_co === '' ) { $tk_co = $is_tt ? '331' : '141'; }
		}
		return array( 'tk_no' => $tk_no, 'tk_co' => $tk_co, 'ma_dt' => $ma_dt );
	}

	/**
	 * LẤY MÃ SẴN CÓ CHO DANH MỤC LOẠI CHI PHÍ.
	 *
	 * Cấu hình cũ đã khai TK Nợ ở 2 chỗ: cột "TK Nợ" của CH_Nhom và ma trận CH_TKNo
	 * (nhóm × phân loại lớn). Hàm này copy các mã đó sang danh mục LOẠI CHI PHÍ để
	 * anh không phải gõ lại 13 dòng — chỉ điền vào ô ĐANG TRỐNG, không ghi đè mã đã khai.
	 * Ma trận chỉ dùng được khi mọi phân loại lớn của nhóm đó cùng 1 mã (khác nhau thì
	 * để trống và báo thiếu, để không âm thầm hạch toán sai).
	 */
	public static function dong_bo_tk_loai() {
		$all  = self::read_all();
		$loai = self::rows_of( $all, self::LOAI );
		if ( ! count( $loai ) ) { return VHCP_Util::ok( array( 'updated' => 0, 'thieuMa' => 0, 'tong' => 0 ) ); }

		$k = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };

		$tk = array();   // tên nhóm -> TK Nợ
		$bp = array();   // tên nhóm -> Bộ phận
		foreach ( self::rows_of( $all, self::NHOM ) as $r ) {
			$key = $k( $r[0] );
			if ( $key === '' ) { continue; }
			if ( ! isset( $tk[ $key ] ) && trim( (string) $r[2] ) !== '' ) { $tk[ $key ] = trim( (string) $r[2] ); }
			if ( ! isset( $bp[ $key ] ) && trim( (string) $r[3] ) !== '' ) { $bp[ $key ] = trim( (string) $r[3] ); }
		}

		// Ma trận: chỉ hạ thành "mã cố định" khi loại đó khai ĐỦ mọi phân loại lớn và
		// cùng một mã. Ô trống trong ma trận nghĩa là "mảng đó không dùng loại này",
		// hạ xuống mã cố định sẽ biến ô trống thành hạch toán sai.
		$so_pll = array();
		foreach ( self::rows_of( $all, self::COSO ) as $r ) {
			$v = trim( (string) $r[2] );
			if ( $v !== '' ) { $so_pll[ mb_strtolower( $v ) ] = 1; }
		}
		$so_pll = count( $so_pll );

		$mt = array();   // tên loại -> [ mã => số ô ]
		foreach ( self::rows_of( $all, self::TKNO ) as $r ) {
			$key = $k( $r[0] );
			$v   = trim( (string) $r[2] );
			if ( $key === '' || $v === '' ) { continue; }
			if ( ! isset( $mt[ $key ] ) ) { $mt[ $key ] = array(); }
			if ( ! isset( $mt[ $key ][ $v ] ) ) { $mt[ $key ][ $v ] = 0; }
			$mt[ $key ][ $v ]++;
		}

		$rows = array(); $upd = 0; $thieu = 0; $changed = false;
		foreach ( $loai as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			$key = $k( $row[0] );

			if ( trim( (string) $row[1] ) === '' ) {
				$v = '';
				if ( isset( $tk[ $key ] ) ) { $v = $tk[ $key ]; }
				elseif ( isset( $mt[ $key ] ) && count( $mt[ $key ] ) === 1 && $so_pll > 0 ) {
					$ma = array_map( 'strval', array_keys( $mt[ $key ] ) );
					if ( (int) $mt[ $key ][ $ma[0] ] >= $so_pll ) { $v = $ma[0]; }
				}
				if ( $v !== '' ) { $row[1] = $v; $upd++; $changed = true; }
			}
			if ( trim( (string) $row[4] ) === '' && isset( $bp[ $key ] ) ) { $row[4] = $bp[ $key ]; $changed = true; }
			if ( trim( (string) $row[1] ) === '' ) { $thieu++; }
			$rows[] = $row;
		}

		if ( $changed ) { self::write( self::LOAI, $rows ); }
		else { self::clear_cache(); }
		return VHCP_Util::ok( array( 'updated' => $upd, 'thieuMa' => $thieu, 'tong' => count( $rows ) ) );
	}

	public static function get_users() {
		$s = self::cfg_static();   // đã gồm bảng người dùng, có cache 5 phút
		return isset( $s['users'] ) ? $s['users'] : array();
	}

	/* ==========================================================================================
	 *  CƠ SỞ LẠ — tên gian hàng có trên DỮ LIỆU mà không có trong BẢNG CẤU HÌNH
	 *
	 *  🔴 VÌ SAO CẦN: cơ sở ở đây được nhận ra bằng CHUỖI TÊN, không phải bằng mã. Sửa ô
	 *  "Tên thường gọi" trong Cấu hình là mọi đơn cũ mang tên cũ lập tức mồ côi — chúng vẫn
	 *  nằm đó, vẫn cộng tiền, nhưng không còn thuộc cơ sở nào trong danh sách. Hộp chọn cơ sở
	 *  ở bảng phân quyền dựng từ bảng Cấu hình, nên gán quyền kiểu gì cũng không với tới chúng.
	 *
	 *  Ca thật 25/08/2026: đơn ghi "ADV GO AN LAC" trong khi bảng Cấu hình khai tên thường gọi
	 *  là "EVENT FZ MN" (trùng luôn với Phân loại lớn). Nhìn hai màn hình thì thấy hai cái tên,
	 *  không có gì nối chúng lại, và cũng không có gì báo là chúng đã lệch.
	 *
	 *  Bốn bảng có cột cơ sở: tạm ứng · chi phí · sổ chi · đơn mua. Quét đủ cả bốn, vì tên lệch
	 *  chỉ ở một bảng cũng đủ làm số liệu không khớp.
	 * ========================================================================================== */
	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * MỌI CHỖ CÓ TÊN CƠ SỞ NẰM TRONG DỮ LIỆU — bảng => TÊN CỘT.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 CỘT CỦA DỰ ÁN TÊN LÀ `gian`, KHÔNG PHẢI `coso` — VÀ NÓ TỪNG BỊ BỎ SÓT. Hằng này trước
	 *    đây chỉ là danh sách TÊN BẢNG, ngầm hiểu cột nào cũng tên `coso`. Bảng `da_line` giữ
	 *    đúng loại giá trị ấy (tên gian hàng, chọn từ cùng một danh mục) nhưng dưới tên cột
	 *    khác, nên nó rơi ra ngoài cả hai đường:
	 *      · `coso_la()` không bao giờ thấy một cơ sở lạ chỉ dùng ở dự án -> không ai biết nó có;
	 *      · `doi_ten_coso()` đổi xong vẫn để nguyên dòng dự án -> tiền của một gian tách làm
	 *        đôi, nửa mang tên mới nửa mang tên cũ, mà màn nào cũng trông như đã đổi xong.
	 *    Hỏng im lặng theo hướng tệ nhất: người ta TIN là đã dọn sạch.
	 *
	 * ⚠️ `bp_index.dia_diem` CỐ Ý KHÔNG CÓ TRONG ĐÂY. Nó là chỗ người ta ĐI TỚI (một tỉnh, một
	 *    hội chợ), gõ tự do, không lấy từ danh mục cơ sở — xem chốt ở `VHCP_BP::don_vi_cua()`.
	 *    Gộp nó vào là một lượt đổi tên cơ sở đi sửa cả địa điểm công tác.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const COSO_BANG = array(
		'tamung'  => 'coso',
		'chiphi'  => 'coso',
		'so_chi'  => 'coso',
		'mk_don'  => 'coso',
		'da_line' => 'gian',
	);

	/** cosoLa(): [ ['ten'=>…, 'dong'=>['chiphi'=>12,…], 'tong'=>12], … ] */
	public static function coso_la() {
		global $wpdb;
		$khai = array();
		foreach ( self::cfg_static()['coso'] as $x ) {
			$k = mb_strtolower( trim( (string) $x['ten'] ) );
			if ( '' !== $k ) { $khai[ $k ] = 1; }
		}
		$gom = array();
		foreach ( self::COSO_BANG as $b => $cot ) {
			$t = VHCP_DB::t( $b );
			foreach ( VHCP_DB::rows( "SELECT $cot AS coso, COUNT(*) AS n FROM $t WHERE $cot<>'' GROUP BY $cot" ) as $r ) {
				$ten = trim( (string) $r['coso'] );
				$k   = mb_strtolower( $ten );
				if ( '' === $k || isset( $khai[ $k ] ) ) { continue; }
				if ( ! isset( $gom[ $k ] ) ) { $gom[ $k ] = array( 'ten' => $ten, 'dong' => array(), 'tong' => 0 ); }
				$gom[ $k ]['dong'][ $b ] = (int) $r['n'];
				$gom[ $k ]['tong']      += (int) $r['n'];
			}
		}
		$out = array_values( $gom );
		usort( $out, function ( $a, $b ) { return $b['tong'] - $a['tong']; } );
		return $out;
	}

	/**
	 * doiTenCoSo(cũ, mới): đổi tên một cơ sở TRÊN MỌI CHỖ cùng lúc.
	 *
	 * Dùng cho hai việc, cùng một đường:
	 *   · đổi tên một cơ sở đã khai  -> đổi cả dòng trong bảng Cấu hình
	 *   · gộp một cơ sở lạ về cơ sở đã khai -> chỉ đổi dữ liệu, không thêm dòng nào
	 *
	 * Anh Thắng 19/09/2026: *"nhân viên lỡ tạo cơ sở ảo, giờ làm sao chuyển qua cơ sở, vì đã
	 * nhập dữ liệu"* — đúng việc thứ hai. Cơ sở ảo ở đây sinh ra từ lượt đẩy nhân sự cũ, mang
	 * MÃ cửa hàng (`FZ_SC_VIVO_T4`) thay vì TÊN gian hàng.
	 *
	 * 🔴 KHÔNG để người ta sửa ô tên trong bảng rồi tự đi sửa dữ liệu sau. Sửa ô tên là việc
	 *    một giây, còn dữ liệu cũ thì nằm ở bốn bảng cộng danh sách cơ sở của từng nhân viên —
	 *    làm tay kiểu gì cũng sót một chỗ, và chỗ sót đó im lặng cho tới lúc đối chiếu tiền.
	 */
	public static function doi_ten_coso( $cu, $moi ) {
		global $wpdb;
		$cu  = trim( (string) $cu );
		$moi = trim( (string) $moi );
		if ( '' === $cu || '' === $moi ) { return VHCP_Util::err( 'Thiếu tên cũ hoặc tên mới' ); }
		if ( mb_strtolower( $cu ) === mb_strtolower( $moi ) ) { return VHCP_Util::err( 'Hai tên giống nhau' ); }

		$dem = array();
		foreach ( self::COSO_BANG as $b => $cot ) {
			$n = $wpdb->update( VHCP_DB::t( $b ), array( $cot => $moi ), array( $cot => $cu ) );
			$dem[ $b ] = (int) $n;
		}

		/* Danh sách cơ sở của từng nhân viên là chuỗi ngăn bằng dấu phẩy — phải tách ra rồi
		   thay đúng phần tử, chứ str_replace cả chuỗi là "GO AN LAC" nuốt luôn "GO AN LAC 2". */
		$u_doi = 0;
		$rows  = self::read( self::USER );
		foreach ( $rows as $i => $r ) {
			$r  = array_values( (array) $r );
			$ds = array_map( 'trim', explode( ',', (string) ( isset( $r[3] ) ? $r[3] : '' ) ) );
			$co = false;
			foreach ( $ds as $j => $x ) {
				if ( '' !== $x && mb_strtolower( $x ) === mb_strtolower( $cu ) ) { $ds[ $j ] = $moi; $co = true; }
			}
			if ( $co ) {
				$r[3]       = implode( ', ', array_filter( $ds, 'strlen' ) );
				$rows[ $i ] = $r;
				$u_doi++;
			}
		}
		if ( $u_doi ) { self::sao_luu_users(); self::write( self::USER, $rows ); }

		/* Dòng trong chính bảng Cấu hình — chỉ đụng khi tên cũ CÓ trong bảng. Gộp cơ sở lạ thì
		   không có dòng nào để đổi, và cũng không được đẻ thêm dòng. */
		$c_doi = 0;
		$crows = self::read( self::COSO );
		foreach ( $crows as $i => $r ) {
			$r = array_values( (array) $r );
			if ( isset( $r[0] ) && mb_strtolower( trim( (string) $r[0] ) ) === mb_strtolower( $cu ) ) {
				$r[0]        = $moi;
				$crows[ $i ] = $r;
				$c_doi++;
			}
		}
		if ( $c_doi ) { self::write( self::COSO, $crows ); }

		self::clear_cache();
		return VHCP_Util::ok( array( 'dong' => $dem, 'nguoi' => $u_doi, 'cauHinh' => $c_doi ) );
	}

	/* ==========================================================================================
	 *  BẢN LƯU RIÊNG CHO BẢNG NGƯỜI DÙNG
	 *
	 *  `cfg_undo` chỉ giữ được MỘT bảng — bảng nào ghi sau thì giành mất ô đó. Lưu cấu hình
	 *  thường ghi liền mấy bảng, nên tới lúc cần hồi lại người dùng thì ô ấy đang giữ bảng SSO.
	 *  Bảng người dùng là thứ mất đi thì không ai đăng nhập được nữa, nên nó có ô riêng, giữ
	 *  NĂM bản gần nhất kèm mốc thời gian.
	 * ========================================================================================== */
	const BAK_MAX = 5;

	private static function sao_luu_users() {
		$rows = self::read( self::USER );
		if ( ! count( $rows ) ) { return; }
		$ds = VHCP_Meta::get_json( 'users_bak', array() );
		if ( ! is_array( $ds ) ) { $ds = array(); }
		/* Bản mới nhất lên đầu. Trùng y hệt bản đầu thì bỏ qua — bấm Lưu ba lần không đẩy
		   ba bản giống nhau vào rồi hất bản cũ thật ra khỏi danh sách. */
		if ( isset( $ds[0]['rows'] ) && wp_json_encode( $ds[0]['rows'] ) === wp_json_encode( $rows ) ) { return; }
		array_unshift( $ds, array(
			'luc'   => current_time( 'mysql' ),
			'boi'   => (string) VHCP_Auth::nguoi(),
			'so'    => count( $rows ),
			'rows'  => $rows,
		) );
		VHCP_Meta::set_json( 'users_bak', array_slice( $ds, 0, self::BAK_MAX ) );
	}

	/** listUserBak(): các bản lưu đang có — KHÔNG kèm PIN, chỉ đủ để chọn. */
	public static function list_user_bak() {
		$ds  = VHCP_Meta::get_json( 'users_bak', array() );
		$out = array();
		foreach ( (array) $ds as $i => $b ) {
			$ten = array();
			foreach ( (array) ( isset( $b['rows'] ) ? $b['rows'] : array() ) as $r ) {
				$r = array_values( (array) $r );
				if ( isset( $r[0] ) && trim( (string) $r[0] ) !== '' ) { $ten[] = (string) $r[0]; }
			}
			$out[] = array(
				'i'   => $i,
				'luc' => isset( $b['luc'] ) ? $b['luc'] : '',
				'boi' => isset( $b['boi'] ) ? $b['boi'] : '',
				'so'  => count( $ten ),
				'ten' => array_slice( $ten, 0, 12 ),
			);
		}
		return $out;
	}

	/**
	 * khoiPhucUsers(): đưa bảng người dùng về một bản lưu.
	 *
	 * GỘP chứ không đè: giữ nguyên người đang có, chỉ thêm lại những ai trong bản lưu mà giờ
	 * không còn. Đè thẳng là người mới khai sau lần lưu đó lại biến mất — vá một lỗ thủng bằng
	 * cách đào một lỗ khác.
	 */
	public static function khoi_phuc_users( $i = 0 ) {
		$ds = VHCP_Meta::get_json( 'users_bak', array() );
		$i  = (int) $i;
		if ( ! isset( $ds[ $i ]['rows'] ) ) { return VHCP_Util::err( 'Không có bản lưu số ' . $i ); }

		$hien = self::read( self::USER );
		$co   = array();
		foreach ( $hien as $r ) {
			$r = array_values( (array) $r );
			$k = mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) );
			if ( '' !== $k ) { $co[ $k ] = 1; }
		}
		$them = 0;
		foreach ( (array) $ds[ $i ]['rows'] as $r ) {
			$r = array_values( (array) $r );
			$k = mb_strtolower( trim( (string) ( isset( $r[0] ) ? $r[0] : '' ) ) );
			if ( '' === $k || isset( $co[ $k ] ) ) { continue; }
			$hien[] = $r;
			$co[ $k ] = 1;
			$them++;
		}
		if ( ! $them ) { return VHCP_Util::ok( array( 'them' => 0, 'tong' => count( $hien ) ) ); }
		self::write( self::USER, $hien );
		self::clear_cache();
		return VHCP_Util::ok( array( 'them' => $them, 'tong' => count( $hien ) ) );
	}

	/* ==========================================================================================
	 *  SOÁT TRÙNG NHÂN SỰ ↔ CHI PHÍ
	 *
	 *  Anh Thắng 13/09/2026: *"nếu đẩy từ nhân sự sang, mà nhân viên này trùng với nhân viên
	 *  tạo trực tiếp trên trang chi phí thì sao, làm sao để gộp lại"*.
	 *
	 *  🔴 HAI HỆ KHOÁ NGƯỜI KHÁC NHAU, VÀ ĐÓ LÀ CẢ CÂU CHUYỆN:
	 *      · bên Nhân sự  khoá là MÃ NV (`UNIQUE KEY ma_nv`) — hai người trùng tên vẫn là hai hàng
	 *      · bên Chi phí  khoá là TÊN   — trùng tên là MỘT người, dùng chung sổ đơn
	 *
	 *  Nên đẩy sổ nhân sự sang đây có ba ngả, và chỉ một ngả là lành:
	 *      · trùng tên ĐÚNG TỪNG CHỮ  -> tự gộp, không phải làm gì
	 *      · lệch một dấu / một dấu cách -> thành HAI người, người mới vào thấy trống trơn còn
	 *        đơn cũ mồ côi, mà KHÔNG có câu lỗi nào
	 *      · hai người trùng tên THẬT -> gộp nhầm làm một, chung đơn chung tiền
	 *
	 *  Màn này bày cả ba ra TRƯỚC khi đẩy. Nó chỉ ĐỌC và ĐẾM, không tự sửa gì: đổi tên một người
	 *  là đụng khoá nối của mọi đơn họ đã lập, nên việc ấy phải do người bấm nút quyết, từng
	 *  trường hợp một.
	 * ========================================================================================== */

	/** Bỏ dấu tiếng Việt — CHỈ để so sánh, không bao giờ để lưu hay bày ra. */
	public static function bo_dau( $s ) {
		$n = array(
			'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
			'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
		);
		$s = mb_strtolower( (string) $s, 'UTF-8' );
		foreach ( $n as $thay => $bo ) {
			foreach ( preg_split( '//u', $bo, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
				$s = str_replace( $c, $thay, $s );
			}
		}
		return $s;
	}

	/**
	 * Khoá so LỎNG của một cái tên: bỏ dấu, hạ chữ thường, gộp mọi khoảng trắng làm một.
	 *
	 * ⚠️ ĐÂY KHÔNG PHẢI KHOÁ NỐI DỮ LIỆU. Khoá nối vẫn là tên nguyên văn (đã `trim` + hạ chữ
	 *    thường) như `user_by_token()` dùng. Khoá lỏng chỉ để NGỜ: "hai cái tên này có khi là
	 *    một người". Dùng nó để nối thật là "Lê Văn Tuấn" và "Lê Văn Tuân" thành một.
	 */
	public static function khoa_long( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', self::bo_dau( $s ) ) );
	}

	/** Khoá CHẶT — đúng thứ `user_by_token()` và mọi đơn đang dùng để nhận nhau. */
	private static function khoa_chat( $s ) {
		return mb_strtolower( trim( (string) $s ) );
	}

	/**
	 * ĐẾM ĐƠN CŨ THEO TÊN — mỗi cái tên đang gánh bao nhiêu dòng dữ liệu.
	 *
	 * 🔴 CON SỐ NÀY LÀ THỨ QUYẾT ĐỊNH ĐƯỢC PHÉP ĐỔI TÊN HAY KHÔNG. Đổi tên một người chưa lập
	 *    đơn nào là việc vô hại; đổi tên người đang gánh 300 dòng là dời 300 dòng ấy sang một
	 *    cái tên khác. Bày số ra cạnh mỗi nút, để không ai bấm mà không biết mình đang bấm gì.
	 *
	 * Quét đủ MỌI cột mang tên người, không chỉ `nguoi_lap`: một người có thể chưa lập đơn nào
	 * mà đã duyệt hàng trăm cái.
	 */
	const NGUOI_COT = array(
		'don'      => array( 'nguoi_lap', 'nguoi_duyet', 'nguoi_qt', 'nguoi_qt_ncc', 'nguoi_cap' ),
		'so_chi'   => array( 'nguoi_nhap' ),
		'da_index' => array( 'nguoi_tao' ),
		'mk_don'   => array( 'nguoi_tao' ),
		'bp_index' => array( 'nguoi_tao' ),
		'log'      => array( 'nguoi' ),
		'thungrac' => array( 'nguoi' ),
		'lenh_tu'  => array( 'nguoi' ),
	);

	/**
	 * @return array khoá chặt của tên => tổng số dòng đang mang tên ấy.
	 *
	 * ⚠️ GOM THEO KHOÁ CHẶT chứ không theo chuỗi thô: sổ cũ có cả " Nguyễn Văn A" lẫn "nguyễn
	 *    văn a", mà `user_by_token()` coi chúng là một người — đếm tách ra là bày sai.
	 */
	private static function dem_don_theo_ten() {
		global $wpdb;
		$dem = array();
		foreach ( self::NGUOI_COT as $bang => $cot ) {
			$t = VHCP_DB::t( $bang );
			foreach ( $cot as $c ) {
				/* Tên bảng và tên cột đến từ hằng ngay trên, không từ dữ liệu — không có gì để
				   `prepare()` ở đây, và `prepare()` cũng không nhận tên cột làm tham số. */
				$rows = $wpdb->get_results( "SELECT `$c` AS ten, COUNT(*) AS n FROM $t WHERE `$c` <> '' GROUP BY `$c`", ARRAY_A );
				foreach ( (array) $rows as $r ) {
					$k = self::khoa_chat( $r['ten'] );
					if ( '' === $k ) { continue; }
					$dem[ $k ] = ( isset( $dem[ $k ] ) ? $dem[ $k ] : 0 ) + (int) $r['n'];
				}
			}
		}
		return $dem;
	}

	/** Sổ nhân sự bên trang Chấm công — mảng rỗng nếu trang ấy chưa cài. */
	private static function ho_so_nhan_su() {
		global $wpdb;
		/* ⚠️ `method_exists` chứ không `class_exists` — xem `da_nghi_ns()` ngay dưới. */
		if ( ! method_exists( 'VHCC_DB', 't' ) ) { return null; }
		$t = VHCC_DB::t( 'nhan_vien' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return null; }
		/* ══════════════════════════════════════════════════════════════════════════════════
		 * CỘT PHÒNG BAN BÊN NHÂN SỰ TÊN LÀ `bo_phan` — sơ đồ tổ chức, khai ở màn nhân sự.
		 *
		 * ⚠️ HỎI SƠ ĐỒ TRƯỚC KHI SELECT. Bốn plugin cài độc lập nên bản có thể lệch nhau bất cứ
		 *    lúc nào (13/09/2026: chấm công trên host là 3.73.0 trong khi nhánh này mới 3.43.0).
		 *    Hỏi thẳng một cột bản kia chưa có là câu lỗi SQL ở MỌI lượt soát.
		 *
		 * ⚠️ VÀ NÓ KHÔNG CÙNG VỐN TỪ VỚI `BO_PHAN_DS` BÊN NÀY. Bên nhân sự là *Phòng Kỹ Thuật ·
		 *    Phòng Marketing · Khối Nhân Viên Cơ Sở…*; bên này là *Kỹ thuật · Marketing · Cơ sở ·
		 *    Setup…*. Màn soát chỉ ĐỐI CHIẾU hai chuỗi và bày chỗ lệch ra — nó không tự dịch,
		 *    vì dịch sai một phòng là cắt mất đúng mảng chi phí người ta cần.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		$co_pb = false;
		foreach ( (array) $wpdb->get_col( "SHOW COLUMNS FROM $t" ) as $c ) {
			if ( 'bo_phan' === $c ) { $co_pb = true; break; }
		}
		$cot = 'ma_nv, ho_ten, cua_hang, chuc_vu, trang_thai_lam_viec, pin_dang_nhap'
			. ( $co_pb ? ', bo_phan AS phong_ban' : ", '' AS phong_ban" );
		return (array) $wpdb->get_results( "SELECT $cot FROM $t ORDER BY ho_ten", ARRAY_A );
	}

	/**
	 * soatNhanSu(): đối chiếu sổ nhân sự với bảng người dùng của trang Chi phí.
	 *
	 * Năm nhóm, xếp theo mức cần xử lý giảm dần:
	 *   trungTen  — HAI hồ sơ nhân sự cùng một tên. Nguy nhất: đẩy sang là chúng chập làm một.
	 *   lech      — tên "gần giống" nhau (khớp sau khi bỏ dấu / gộp khoảng trắng) nhưng KHÔNG
	 *               khớp từng chữ. Đây là chỗ đơn cũ mồ côi.
	 *   thieuCp   — có bên Nhân sự, chưa có tài khoản bên Chi phí.
	 *   thieuNs   — có bên Chi phí, không thấy bên Nhân sự (người cũ đã nghỉ, hoặc gõ sai tên).
	 *   khop      — khớp đúng từng chữ. Không phải làm gì; bày ra để biết đã soát tới.
	 *
	 * ⚠️ NGƯỜI ĐÃ NGHỈ VẪN ĐƯỢC BÀY, có nhãn riêng. Họ vẫn gánh đơn cũ, và tên họ vẫn phải giữ
	 *    nguyên — lọc họ ra khỏi màn là mất dấu vì sao một cái tên bên Chi phí không có cặp.
	 */
	public static function soat_nhan_su() {
		$hs = self::ho_so_nhan_su();
		if ( null === $hs ) {
			return VHCP_Util::ok( array( 'coNhanSu' => false, 'nhom' => array(), 'tong' => array() ) );
		}
		$dem = self::dem_don_theo_ten();
		$don = function ( $ten ) use ( $dem ) {
			$k = self::khoa_chat( $ten );
			return isset( $dem[ $k ] ) ? (int) $dem[ $k ] : 0;
		};

		/* Bảng người dùng, tra được bằng cả hai khoá. */
		$cp_chat = array(); $cp_long = array();
		foreach ( self::get_users() as $u ) {
			$ten = trim( (string) $u['ten'] );
			if ( '' === $ten ) { continue; }
			$cp_chat[ self::khoa_chat( $ten ) ] = $u;
			$l = self::khoa_long( $ten );
			if ( '' !== $l ) {
				if ( ! isset( $cp_long[ $l ] ) ) { $cp_long[ $l ] = array(); }
				$cp_long[ $l ][] = $u;
			}
		}

		/* Hồ sơ nhân sự trùng tên nhau — gom trước, vì một hồ sơ có thể vừa trùng tên đồng
		   nghiệp vừa khớp một dòng bên Chi phí, và khi đó nhóm "trùng tên" mới là nhóm đúng. */
		$ns_chat = array();
		foreach ( $hs as $r ) {
			$k = self::khoa_chat( $r['ho_ten'] );
			if ( '' === $k ) { continue; }
			if ( ! isset( $ns_chat[ $k ] ) ) { $ns_chat[ $k ] = array(); }
			$ns_chat[ $k ][] = $r;
		}

		$nhom = array( 'trungTen' => array(), 'lech' => array(), 'lechPb' => array(),
			'thieuCp' => array(), 'thieuNs' => array(), 'khop' => array() );
		$da_dung_cp = array();   // dòng Chi phí nào đã tìm được cặp

		foreach ( $ns_chat as $k => $ds ) {
			$ten   = trim( (string) $ds[0]['ho_ten'] );
			$so_don = $don( $ten );

			if ( count( $ds ) > 1 ) {
				$nhom['trungTen'][] = array(
					'ten'  => $ten,
					'don'  => $so_don,
					'coCp' => isset( $cp_chat[ $k ] ),
					'hoSo' => array_map( function ( $r ) {
						return array( 'maNv' => (string) $r['ma_nv'], 'coso' => (string) $r['cua_hang'],
							'chucVu' => (string) $r['chuc_vu'], 'nghi' => self::da_nghi_ns( $r['trang_thai_lam_viec'] ) );
					}, $ds ),
				);
				if ( isset( $cp_chat[ $k ] ) ) { $da_dung_cp[ $k ] = 1; }
				continue;
			}

			$r = $ds[0];
			if ( isset( $cp_chat[ $k ] ) ) {
				$da_dung_cp[ $k ] = 1;
				$u = $cp_chat[ $k ];
				$pb_ns = trim( (string) ( isset( $r['phong_ban'] ) ? $r['phong_ban'] : '' ) );
				$pb_cp = trim( (string) $u['boPhan'] );
				$nhom['khop'][] = array(
					'ten' => $ten, 'maNv' => (string) $r['ma_nv'], 'don' => $so_don,
					'vaiTro' => (string) $u['vaiTro'],
					'maNvCp' => isset( $u['maNv'] ) ? (string) $u['maNv'] : '',
					'pbNs' => $pb_ns, 'pbCp' => $pb_cp,
					'nghi' => self::da_nghi_ns( $r['trang_thai_lam_viec'] ),
				);
				/* ══════════════════════════════════════════════════════════════════════════
				 * LỆCH PHÒNG BAN — anh Thắng 13/09/2026: *"quyết định bộ phận do nhân sự quyết
				 * định, bên chi phí chỉ biết bộ phận đó có được quyền không thôi"*.
				 *
				 * Từ nay sổ nhân sự là NGUỒN THẬT. Nhưng anh chốt cùng ngày là CHƯA KHOÁ ô bên
				 * này vội — hai bên chạy song song một thời gian để đối chiếu, khi nào khớp hết
				 * mới khoá. Nhóm này chính là cái bảng đối chiếu ấy.
				 *
				 * ⚠️ CHỈ TÍNH LỆCH KHI CẢ HAI BÊN ĐỀU CÓ. Một bên trống là "chưa khai", không
				 *    phải "khai khác" — gom chung vào là bảng đầy những dòng không có gì để sửa,
				 *    và chỗ lệch thật lẫn mất trong đó. Bên nào trống thì đếm riêng.
				 * ══════════════════════════════════════════════════════════════════════════ */
				if ( '' !== $pb_ns && '' !== $pb_cp && mb_strtolower( $pb_ns ) !== mb_strtolower( $pb_cp ) ) {
					$nhom['lechPb'][] = array(
						'ten' => $ten, 'maNv' => (string) $r['ma_nv'],
						'pbNs' => $pb_ns, 'pbCp' => $pb_cp, 'don' => $so_don,
					);
				}
				continue;
			}

			/* Chưa khớp chặt — thử khoá lỏng. Khớp lỏng nghĩa là gần như chắc chắn cùng một
			   người mà tên gõ lệch, và đó đúng là ca anh Thắng hỏi. */
			$l = self::khoa_long( $ten );
			if ( '' !== $l && isset( $cp_long[ $l ] ) ) {
				foreach ( $cp_long[ $l ] as $u ) {
					$kc = self::khoa_chat( $u['ten'] );
					if ( isset( $da_dung_cp[ $kc ] ) ) { continue; }
					$da_dung_cp[ $kc ] = 1;
					$nhom['lech'][] = array(
						'tenNs' => $ten, 'maNv' => (string) $r['ma_nv'],
						'tenCp' => trim( (string) $u['ten'] ),
						'donNs' => $so_don, 'donCp' => $don( $u['ten'] ),
						'vaiTro' => (string) $u['vaiTro'],
						'nghi' => self::da_nghi_ns( $r['trang_thai_lam_viec'] ),
					);
					continue 2;
				}
			}

			$nhom['thieuCp'][] = array(
				'ten' => $ten, 'maNv' => (string) $r['ma_nv'], 'coso' => (string) $r['cua_hang'],
				'chucVu' => (string) $r['chuc_vu'], 'don' => $so_don,
				'coPin' => trim( (string) $r['pin_dang_nhap'] ) !== '',
				'nghi' => self::da_nghi_ns( $r['trang_thai_lam_viec'] ),
			);
		}

		foreach ( $cp_chat as $k => $u ) {
			if ( isset( $da_dung_cp[ $k ] ) ) { continue; }
			$nhom['thieuNs'][] = array(
				'ten' => trim( (string) $u['ten'] ), 'vaiTro' => (string) $u['vaiTro'],
				'coso' => (string) $u['coso'], 'don' => $don( $u['ten'] ),
				'maNvCp' => isset( $u['maNv'] ) ? (string) $u['maNv'] : '',
			);
		}

		$tong = array();
		foreach ( $nhom as $ten_nhom => $ds ) { $tong[ $ten_nhom ] = count( $ds ); }
		/* Hai con số cho biết còn bao nhiêu việc phải khai, tách khỏi con số "khai khác nhau". */
		$chua_ns = 0; $chua_cp = 0;
		foreach ( $nhom['khop'] as $x ) {
			if ( '' === $x['pbNs'] ) { $chua_ns++; }
			if ( '' === $x['pbCp'] ) { $chua_cp++; }
		}
		return VHCP_Util::ok( array(
			'coNhanSu' => true, 'nhom' => $nhom, 'tong' => $tong,
			'soHoSo' => count( $hs ), 'soTaiKhoan' => count( $cp_chat ),
			'pbChuaNs' => $chua_ns, 'pbChuaCp' => $chua_cp,
		) );
	}

	/**
	 * Hỏi bên Nhân sự "đã nghỉ chưa" — có trang ấy thì hỏi nó, không có thì tự đọc theo cùng luật.
	 *
	 * ⚠️ GÁC BẰNG `method_exists`, KHÔNG CHỈ `class_exists`. Bốn plugin cài độc lập nên bản có
	 *    thể lệch nhau: lớp có mặt mà hàm chưa có là gọi hụt, và gọi hụt một hàm tĩnh thì
	 *    trắng cả trang WordPress. Bản dự phòng ngay dưới đọc theo ĐÚNG luật của `da_nghi()`
	 *    (có chữ "nghỉ" là nghỉ; ô trống là đang làm) nên hai đường cho cùng một kết quả.
	 */
	private static function da_nghi_ns( $tt ) {
		if ( method_exists( 'VHCC_NhanSu', 'da_nghi' ) ) { return (bool) VHCC_NhanSu::da_nghi( $tt ); }
		$t = trim( (string) $tt );
		return '' !== $t && false !== strpos( mb_strtolower( $t ), 'nghỉ' );
	}

	/* ==========================================================================================
	 *  ĐỔI TÊN MỘT NGƯỜI TRÊN MỌI CHỖ CÙNG LÚC — anh em sinh đôi của `doi_ten_coso()`.
	 *
	 *  🔴 KHÔNG ĐỂ NGƯỜI TA SỬA Ô TÊN TRONG BẢNG RỒI ĐI SỬA DỮ LIỆU SAU. Sửa ô tên là việc một
	 *     giây; còn tên cũ thì nằm rải ở tám bảng, mười ba cột. Làm tay kiểu gì cũng sót, và chỗ
	 *     sót im lặng cho tới lúc người ấy mở trang lên thấy sổ đơn của mình trống trơn.
	 *
	 *  🔴 ĐỔI TÊN LÀ GỘP, KHÔNG PHẢI ĐỔI NHÃN. Đổi "Nguyen Van A" thành "Nguyễn Văn A" trong khi
	 *     đã có một dòng mang tên "Nguyễn Văn A" nghĩa là hai sổ đơn nhập làm một — và không có
	 *     đường về. Nên khi đích đã tồn tại thì phải nói thẳng con số của cả hai bên ra trước,
	 *     và chỉ đi tiếp khi người bấm khai rõ là muốn gộp (`gop`).
	 * ========================================================================================== */
	public static function doi_ten_nguoi( $cu, $moi, $gop = false ) {
		global $wpdb;
		$cu  = trim( (string) $cu );
		$moi = trim( (string) $moi );
		if ( '' === $cu || '' === $moi ) { return VHCP_Util::err( 'Thiếu tên cũ hoặc tên mới' ); }
		if ( $cu === $moi ) { return VHCP_Util::err( 'Hai tên giống hệt nhau — không có gì để đổi' ); }

		$k_cu  = self::khoa_chat( $cu );
		$k_moi = self::khoa_chat( $moi );

		/* Dòng đích đã có sẵn trong bảng người dùng -> đây là một cú GỘP. */
		$co_dich = false;
		foreach ( self::get_users() as $u ) {
			if ( self::khoa_chat( $u['ten'] ) === $k_moi ) { $co_dich = true; break; }
		}
		if ( $co_dich && $k_cu !== $k_moi && ! $gop ) {
			$dem = self::dem_don_theo_ten();
			return VHCP_Util::err( 'Tên "' . $moi . '" đã có tài khoản. Đổi "' . $cu . '" thành tên đó '
				. 'là GỘP hai sổ đơn làm một: '
				. ( isset( $dem[ $k_cu ] ) ? (int) $dem[ $k_cu ] : 0 ) . ' dòng của "' . $cu . '" sẽ nhập vào '
				. ( isset( $dem[ $k_moi ] ) ? (int) $dem[ $k_moi ] : 0 ) . ' dòng của "' . $moi . '", và không có đường về. '
				. 'Nếu đúng ý thì bấm lại và xác nhận gộp.' );
		}

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 ĐỔI THEO KHOÁ CHẶT, KHÔNG `WHERE cot = $cu`. Sổ cũ có cả " Nguyễn Văn A" lẫn
		 *    "NGUYỄN VĂN A" — `user_by_token()` coi chúng là MỘT người, nên đổi tên mà bỏ sót
		 *    chúng là để lại đúng những dòng mồ côi mà việc này sinh ra để dọn.
		 *
		 * 🔴 VÌ SAO LIỆT KÊ BIẾN THỂ RỒI MỚI ĐỔI, thay vì một câu `WHERE LOWER(TRIM(cot)) = …`:
		 *      · `LOWER()` của cả MySQL lẫn SQLite chỉ hạ chữ ASCII — "NGUYỄN" ra "nguyỄn",
		 *        nên câu ấy trượt đúng những cái tên tiếng Việt mà nó cần bắt;
		 *      · còn `WHERE TRIM(cot) = 'tên'` trần thì dựa vào COLLATION của cột để bỏ qua
		 *        hoa thường. Đúng trên host thật, sai trên bệ đỡ thử — nghĩa là phép kiểm xanh
		 *        mà thứ nó kiểm thì không phải thứ đang chạy, đúng loại lỗi tệ nhất.
		 *    Liệt kê các chuỗi CÓ THẬT trong cột rồi lọc bằng `khoa_chat()` thì hai nơi cùng
		 *    một luật, và luật ấy chính là luật `user_by_token()` đang dùng để nhận người.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		$dem = array();
		$doi_cot = function ( $t, $c ) use ( $wpdb, $k_cu, $moi ) {
			$n = 0;
			foreach ( (array) $wpdb->get_col( "SELECT DISTINCT `$c` FROM $t WHERE `$c` <> ''" ) as $v ) {
				if ( self::khoa_chat( $v ) !== $k_cu || (string) $v === $moi ) { continue; }
				$n += (int) $wpdb->query( $wpdb->prepare( "UPDATE $t SET `$c` = %s WHERE `$c` = %s", $moi, $v ) );
			}
			return $n;
		};
		foreach ( self::NGUOI_COT as $bang => $cot ) {
			$t = VHCP_DB::t( $bang );
			foreach ( $cot as $c ) {
				$n = $doi_cot( $t, $c );
				if ( $n ) { $dem[ $bang . '.' . $c ] = $n; }
			}
		}

		/* Thẻ phiên đang mở cũng mang tên — bỏ qua là người ấy vẫn đăng nhập được bằng tên CŨ
		   suốt 30 ngày, và `user_by_token()` không tìm ra dòng nào khớp nên rơi về vai trong
		   thẻ, tức là vai đông cứng từ lúc đăng nhập. */
		$doi_cot( VHCP_DB::t( 'session' ), 'ten' );

		/* Bảng người dùng: đổi tên dòng cũ; nếu là cú gộp thì XOÁ dòng cũ, giữ dòng đích. */
		$rows  = self::read( self::USER );
		$giu   = array();
		$u_doi = 0; $u_bo = 0;
		foreach ( $rows as $r ) {
			$r = array_values( (array) $r );
			$k = self::khoa_chat( isset( $r[0] ) ? $r[0] : '' );
			if ( $k !== $k_cu ) { $giu[] = $r; continue; }
			if ( $co_dich ) { $u_bo++; continue; }   // gộp -> dòng đích đã có, bỏ dòng này
			$r[0] = $moi;
			$giu[] = $r;
			$u_doi++;
		}
		if ( $u_doi || $u_bo ) { self::sao_luu_users(); self::write( self::USER, $giu ); }

		self::clear_cache();
		return VHCP_Util::ok( array( 'dong' => $dem, 'doiDong' => array_sum( $dem ),
			'sua' => $u_doi, 'goBo' => $u_bo, 'gop' => $co_dich ) );
	}
}
