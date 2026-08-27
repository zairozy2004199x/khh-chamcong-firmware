<?php
/**
 * SƠ ĐỒ BẢNG — Ghế massage QR.
 *
 * =============================================================================================
 * MỘT LUẬT DUY NHẤT CHI PHỐI CẢ TỆP NÀY: KHÔNG ĐƯỢC ĐẾM TIỀN HAI LẦN, VÀ KHÔNG ĐƯỢC MẤT TIỀN.
 * =============================================================================================
 * Bên Apps Script, doanh thu ghi vào `/ghe/revenue/<ref>` — tức KHOÁ LÀ MÃ THAM CHIẾU của ngân
 * hàng. Nhờ vậy webhook bắn lại, hay nhập lại đúng file Excel Tingo đó, cũng chỉ ghi đè lên một
 * chỗ. Đó là thứ làm cho "nhập lại cho chắc" trở thành việc an toàn.
 *
 * Ở đây giữ nguyên luật: `ref` là UNIQUE, và mọi lượt ghi đi qua `VHG_Thu::ghi()` — không có
 * đường ghi thứ hai. Bỏ ràng buộc đó là tháng sau không ai dựng lại được con số thật.
 *
 * ⚠️ MẶT KIA CỦA CÙNG MỘT LUẬT: gói webhook KHÔNG đọc được thì vẫn phải GIỮ LẠI (bảng
 *    `ghe_nhat_ky`, kèm thân thô). Bỏ đi là mất tiền thật chỉ vì mình chưa đoán ra tên trường
 *    của bên gửi — mà bên gửi thì đổi tên trường lúc nào không báo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_DB {

	public static function t( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'vhg_' . $ten;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		foreach ( self::bang() as $ten => $cot ) {
			dbDelta( 'CREATE TABLE ' . self::t( $ten ) . " (\n" . $cot . "\n) $c" );
		}
	}

	public static function bang() {
		$b = array();

		/* ===== 1. CƠ SỞ ===================================================================== */
		$b['coso'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ten VARCHAR(190) NOT NULL,
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY ten (ten)";

		/* ===== 2. MÁY (GHẾ) ================================================================
		   `ma` là mã ghế dùng trong nội dung chuyển khoản: "GHE<ma> <code>". Nên nó phải NGẮN và
		   KHÔNG dấu — người ta gõ tay nội dung CK khi quét QR bằng app ngân hàng.
		   `so_tk` là số tài khoản/VA nhận tiền của riêng máy đó. Mỗi máy một VA thì đối soát
		   không cần đọc nội dung; nhưng nhiều máy dùng chung một TK cũng chạy được, lúc đó nội
		   dung CK là thứ duy nhất phân biệt. */
		/* `mac` là DANH TÍNH PHẦN CỨNG của bo ESP32 trong ghế đó.
		   Vì sao cần: bản .bin do CI build là MỘT bản dùng cho MỌI ghế (nó nằm ở chỗ tải công
		   khai, không được chứa gì riêng của ghế nào). Nếu mã ghế phải nạp cứng lúc biên dịch thì
		   mỗi ghế một bản .bin, và cập nhật từ xa mất hết ý nghĩa. Nên ghế tự khai MAC, còn bảng
		   này nói MAC đó là ghế số mấy — sửa trên web, không phải nạp lại firmware. */
		$b['may'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(40) NOT NULL,
			mac VARCHAR(40) NOT NULL DEFAULT '',
			coso_id BIGINT(20) NOT NULL DEFAULT 0,
			gia INT NOT NULL DEFAULT 10000,
			phut INT NOT NULL DEFAULT 6,
			so_tk VARCHAR(60) NOT NULL DEFAULT '',
			ten_tk VARCHAR(190) NOT NULL DEFAULT '',
			bank_bin VARCHAR(20) NOT NULL DEFAULT '',
			ten_khai VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			cap_nhat DATETIME NULL,
			moc_chiso BIGINT(20) NULL,
			moc_chiso_ngay DATE NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma (ma),
			KEY mac (mac),
			KEY coso_id (coso_id)";

		/* ===== 2b. PHIÊN ĐĂNG NHẬP TRANG NGOÀI ===============================================
		   Trang `/ghe` cho nhân viên cửa hàng xem trên điện thoại — họ KHÔNG có tài khoản
		   WordPress, nên phải có cổng PIN riêng.

		   ⚠️ Phiên RIÊNG với plugin chấm công, dù dùng chung danh sách người. Hai hệ thống riêng
		      thì thu hồi phiên bên này không được kéo bên kia xuống theo — mà đây là màn có
		      DOANH THU, khả năng phải đá một người ra gấp là có thật. */
		$b['phien'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			token CHAR(64) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			het_han DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY het_han (het_han)";

		/* ===== 3. DOANH THU ================================================================
		   🔴 `ref` UNIQUE là RÀNG BUỘC QUAN TRỌNG NHẤT CỦA CẢ PLUGIN. Xem khối đầu tệp.

		   `ma_may` có thể RỖNG: giao dịch nội dung mơ hồ ("PaymentForOrder") thì lúc nhận chưa
		   biết máy nào. Không được vì thế mà bỏ — tiền đã vào tài khoản rồi. Giữ lại, rồi gán
		   máy sau bằng bản đồ máy (bảng `ban_do`).

		   `ten_khai` giữ NGUYÊN VĂN tên máy đọc được từ nội dung/cột của bên gửi, chưa chuẩn hoá
		   — để còn đối chiếu khi bản đồ máy sai.

		   🔴 `huy` — ĐÁNH DẤU, KHÔNG XOÁ. Ghi nhầm thì phải gỡ được khỏi báo cáo, nhưng gỡ bằng
		   DELETE là hỏng cả hai đường: (1) mất chỗ duy nhất trả lời "sao hôm đó lệch 10.000đ",
		   (2) `ref` là UNIQUE và cũng là thứ chặn cộng đôi — xoá dòng đi thì đúng giao dịch đó
		   bắn lại lần sau lại vào sổ như mới. Đánh dấu thì `ref` còn nguyên, chặn vẫn còn, và
		   dòng vẫn nằm đó cho người đối soát nhìn thấy kèm lý do. */
		/* ⚠️ `nop_id` — lượt thu tiền mặt tại quầy này đã được NỘP về quỹ trong lượt nộp nào.
		      0 = tiền còn trên tay người thu. Đây là cột LIÊN KẾT, không phải cột tiền: số tiền
		      vẫn nằm ở `so_tien` và không ai được sửa nó khi nộp. Xem VHG_Quy.
		   ⚠️ Dòng đã HUỶ thì không tính vào tiền trên tay — huỷ nghĩa là lượt đó không có thật. */
		$b['thu'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ref VARCHAR(120) NOT NULL,
			luc DATETIME NOT NULL,
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			ma_lenh VARCHAR(40) NOT NULL DEFAULT '',
			so_tien BIGINT(20) NOT NULL DEFAULT 0,
			nguon VARCHAR(20) NOT NULL DEFAULT '',
			noi_dung VARCHAR(255) NOT NULL DEFAULT '',
			ten_khai VARCHAR(190) NOT NULL DEFAULT '',
			vvb VARCHAR(60) NOT NULL DEFAULT '',
			ma_ch VARCHAR(60) NOT NULL DEFAULT '',
			ghi_luc DATETIME NULL,
			huy TINYINT(1) NOT NULL DEFAULT 0,
			huy_ly_do VARCHAR(190) NOT NULL DEFAULT '',
			nop_id BIGINT(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY nop (nop_id),
			KEY luc (luc),
			KEY huy (huy,luc),
			KEY may (ma_may,luc),
			KEY nguon (nguon,luc)";

		/* ===== 4. TIỀN ĐÃ VÀO, CHỜ GHẾ NHẬN =================================================
		   Bản Firebase là `/ghe/pay/<ghế>/<code>`: ESP thấy node thì chạy rồi tự xoá. Ở đây KHÔNG
		   xoá — đánh dấu `nhan_luc`. Xoá là mất chỗ duy nhất trả lời câu "khách trả tiền rồi mà
		   ghế không chạy, lúc mấy giờ". Mà câu đó là câu người ta hỏi lúc đang cãi nhau ở quầy. */
		$b['cho'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_may VARCHAR(40) NOT NULL,
			ma_lenh VARCHAR(40) NOT NULL DEFAULT '',
			so_tien BIGINT(20) NOT NULL DEFAULT 0,
			ref VARCHAR(120) NOT NULL DEFAULT '',
			noi_dung VARCHAR(255) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			nhan_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY o (ma_may,ma_lenh),
			KEY cho (ma_may,nhan_luc)";

		/* ===== 5. NHỊP SỐNG CỦA GHẾ ========================================================
		   Một hàng một máy, ĐÈ lên — đây là "máy còn sống không", không phải nhật ký. */
		/* `fw` để 80 chứ không 40: chuỗi phiên bản firmware có cả ngày và câu mô tả ngắn, dài
		   quá 50 ký tự. Cắt cụt ở 40 là mất đúng phần nói bản đó khác bản trước chỗ nào — mà đó
		   là lý do duy nhất người ta đọc cột này.

		   `nd_tien_to` = tiền tố nội dung GHẾ ĐANG THẬT SỰ DÙNG, ghế tự khai lên mỗi lượt nhịp.
		   Đối chứng cho ô khai trên web: ghế còn nạp firmware cũ thì không biết tiền tố, mà từ
		   web không có cách nào nhìn ra điều đó — người ta sửa ô trên web rồi tưởng xong, còn
		   ghế vẫn dựng nội dung thiếu tiền tố và tiền vẫn biến mất y như cũ.

		   ⚠️ Lời giải thích để NGOÀI chuỗi SQL: nhét khối chú thích vào giữa danh sách cột thì
		      dbDelta không đọc nổi, và dấu nháy kép trong đó còn làm hỏng luôn chuỗi PHP.
		      (Viết chính câu này cũng vừa dính bẫy: một dấu đóng chú thích nằm lọt trong lời
		      giải thích là nó tự đóng khối sớm, và PHP báo lỗi ở một dòng chẳng liên quan.)

		   `tre_ms` = lượt gọi TRƯỚC của ghế mất bao nhiêu mili giây. Ghế tính `con_lai` rồi mới
		   đi gọi; máy chủ đóng dấu `luc` lúc NHẬN. Cả quãng bắt tay TLS + đẩy gói nằm giữa hai
		   mốc đó, nên `con_lai` già hơn `luc` đúng bằng nửa quãng đi — và phép trừ tuổi dữ liệu
		   không nhìn thấy phần này. Đó là 4-5 giây lệch giữa đồng hồ trên ghế và trên web.

		   `tm_*` = cục nhận tiền ICT L70. `tm_loi` là lỗi ĐANG diễn ra ngay lúc ghế gửi nhịp
		   (rỗng = đang bình thường); `tm_cuoi` + `tm_lan` + `tm_luc` là chuyện ĐÃ QUA. Hai thứ
		   khác nhau và phải để riêng: gộp lại thì một lần kẹt ba giây hồi sáng cứ nằm đó báo đỏ
		   cả ngày, còn ghế đang thật sự kẹt lúc này thì lẫn vào đám cũ không ai nhìn ra.

		   `tm_to` = lần cuối nhận được một tờ tiền hợp lệ. Là SỐ LIỆU để nhìn, KHÔNG phải cờ
		   báo lỗi: cả ngày không ai trả tiền mặt là chuyện bình thường ở nhiều cửa hàng. */
		$b['nhip'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_may VARCHAR(40) NOT NULL,
			trang_thai VARCHAR(20) NOT NULL DEFAULT '',
			nguon VARCHAR(20) NOT NULL DEFAULT '',
			con_lai INT NOT NULL DEFAULT 0,
			ip VARCHAR(60) NOT NULL DEFAULT '',
			fw VARCHAR(80) NOT NULL DEFAULT '',
			nd_tien_to VARCHAR(20) NOT NULL DEFAULT '',
			tre_ms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			tm_loi VARCHAR(12) NOT NULL DEFAULT '',
			tm_cuoi VARCHAR(12) NOT NULL DEFAULT '',
			tm_lan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			tm_luc DATETIME NULL,
			tm_to DATETIME NULL,
			khoa TINYINT(1) NOT NULL DEFAULT 0,
			kt TINYINT(1) NOT NULL DEFAULT 0,
			chay TINYINT(1) NOT NULL DEFAULT 0,
			luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_may (ma_may),
			KEY luc (luc)";

		/* ===== 6. LỆNH GỬI XUỐNG GHẾ (bật/tắt tay) ==========================================
		   ⚠️ Đây là đường CHO KHÔNG một lượt massage. Nên nó phải để lại dấu: ai đặt, lúc nào,
		      máy nhận chưa. Không có cột `nguoi` thì tháng sau không ai giải thích được vì sao
		      một máy chạy 40 lượt mà thu có 12. */
		$b['lenh'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_may VARCHAR(40) NOT NULL,
			viec VARCHAR(20) NOT NULL DEFAULT '',
			phut INT NOT NULL DEFAULT 0,
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			ly_do VARCHAR(255) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			gui_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY hang (ma_may,gui_luc,id)";

		/* ===== 7. NHẬT KÝ WEBHOOK ==========================================================
		   Ghi MỌI lượt bắn tới, KỂ CẢ lượt bị từ chối vì sai khoá. Đó là cách duy nhất phân biệt
		   "bên gửi chưa bắn" với "bắn rồi mà mình chặn" — hai ca đó đi sửa ở hai nơi khác hẳn.
		   `tho` giữ 2000 ký tự đầu của thân thật: khi bên gửi đổi tên trường, đây là thứ duy nhất
		   cho biết họ đang gửi gì. */
		$b['nhat_ky'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			luc DATETIME NOT NULL,
			nguon VARCHAR(30) NOT NULL DEFAULT '',
			so_tien BIGINT(20) NOT NULL DEFAULT 0,
			noi_dung VARCHAR(255) NOT NULL DEFAULT '',
			ref VARCHAR(120) NOT NULL DEFAULT '',
			khop TINYINT(1) NOT NULL DEFAULT 0,
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			ma_lenh VARCHAR(40) NOT NULL DEFAULT '',
			ten_khai VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			tho TEXT NULL,
			PRIMARY KEY  (id),
			KEY luc (luc)";

		/* ===== 8. BẢN ĐỒ MÁY: mã điểm bán / mã cửa hàng -> tên máy =========================
		   Tingo gửi "PaymentForOrder" cho phần lớn giao dịch — nội dung không nói máy nào. Thứ
		   duy nhất phân biệt là Mã điểm bán (VVB…) hoặc Mã cửa hàng. Bảng này học từ những giao
		   dịch CÓ tên trong nội dung, rồi áp cho những giao dịch không có. */
		$b['ban_do'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			khoa VARCHAR(60) NOT NULL,
			ten_may VARCHAR(190) NOT NULL DEFAULT '',
			vvb VARCHAR(60) NOT NULL DEFAULT '',
			ma_ch VARCHAR(60) NOT NULL DEFAULT '',
			tu_hoc TINYINT(1) NOT NULL DEFAULT 0,
			cap_nhat DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY khoa (khoa)";

		/* ===== 9. MÃ MUA TRƯỚC ==============================================================
		   Khách mua mã hôm nay (giá đã giảm), dùng hôm khác, ở BẤT KỲ ghế nào.

		   🔴 `ma` là UNIQUE và đó là toàn bộ hàng rào chống dùng hai lần. `dung_luc` khác NULL là
		      đã dùng; đánh dấu chứ KHÔNG xoá — xoá dòng đi thì mã ấy dùng lại được, và mình mất
		      luôn chỗ duy nhất trả lời "hôm đó ghế chạy vì cái gì".

		   `cc_bam` là băm của **BỐN SỐ CUỐI** căn cước, KHÔNG PHẢI cả số. Khách nhập cả số, hệ
		   thống lấy bốn số cuối rồi vứt phần còn lại — không ghi, không log, không đi đâu cả.
		   🔴 Vì sao không lưu cả số: một bảng ghép "số điện thoại + căn cước đầy đủ" bị lộ thì
		      thiệt hại lớn hơn hẳn việc lộ mã giảm giá massage, và đó là dữ liệu Nghị định
		      13/2023 bắt phải bảo vệ chặt. Bốn số cuối đủ để phân biệt hai người ở quầy, mà tự
		      nó KHÔNG tra ngược ra ai — đúng mức cần cho việc này, không hơn.

		   `pin_bam` là băm bcrypt, KHÔNG lưu PIN thô. PIN 4 số thì không gian rất nhỏ, nhưng nó
		   canh cho một thứ có thật: số điện thoại của khách là thứ người khác đoán ra được, mà
		   biết số là tra ra mã của người ta. Băm + hãm thử ở tầng trên.

		   `gia_ban` khác `menh_gia` là chỗ giảm giá nằm: mua 100.000đ với giá 85.000đ thì doanh
		   thu ghi 85.000đ (tiền thật vào két), còn ghế chạy theo 100.000đ. Giữ cả hai con số vì
		   cuối tháng phải giải thích được vì sao ghế chạy nhiều hơn tiền thu. */
		$b['ma'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(20) NOT NULL,
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			pin_bam VARCHAR(255) NOT NULL DEFAULT '',
			cc_bam VARCHAR(255) NOT NULL DEFAULT '',
			menh_gia BIGINT(20) NOT NULL DEFAULT 0,
			gia_ban BIGINT(20) NOT NULL DEFAULT 0,
			giam_pt INT NOT NULL DEFAULT 0,
			cho_ngay INT NOT NULL DEFAULT 0,
			ref_ban VARCHAR(120) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			dung_luc DATETIME NULL,
			dung_may VARCHAR(40) NOT NULL DEFAULT '',
			huy TINYINT(1) NOT NULL DEFAULT 0,
			huy_luc DATETIME NULL,
			huy_ly_do VARCHAR(255) NOT NULL DEFAULT '',
			huy_ai VARCHAR(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY ma (ma),
			KEY nguoi (sdt,dung_luc),
			KEY ban (tao_luc)";

		/* ===== 10. ĐƠN MUA MÃ ĐANG CHỜ TRẢ TIỀN ============================================
		   Khách bấm mua -> tạo đơn -> quét QR trả tiền -> webhook khớp `MUA<mã đơn>` -> phát mã.

		   ⚠️ ĐƠN PHẢI CÓ TRƯỚC KHI CÓ TIỀN. Không thể phát mã ngay lúc bấm mua (chưa trả tiền),
		      cũng không thể chờ tiền rồi mới hỏi số điện thoại (lúc đó khách đã đi khỏi trang).
		      Nên số điện thoại, PIN, mệnh giá và giá bán được CHỐT ở đây, và webhook chỉ việc
		      tra ra rồi phát. Giá chốt lúc đặt đơn, không tính lại lúc tiền về: đổi bảng giảm
		      giá giữa chừng mà tính lại là khách trả một đằng nhận một nẻo. */
		/* 🔴 MỘT BẢNG ĐƠN CHO CẢ HAI KIỂU BÁN, không phải hai bảng — xem cột `loai` dưới đây.
		     `loai = 'ma'`  -> trả tiền xong thì PHÁT MÃ  (VHG_Ma::phat_ma)
		     `loai = 'nap'` -> trả tiền xong thì CỘNG VÍ  (VHG_Vi::nap)
		   Tách hai bảng là phải tách luôn cả đường webhook, mà đường đó là chỗ tiền đi vào —
		   chẻ đôi nó ra để lấy sự gọn gàng là đổi nhầm thứ.
		   ⚠️ Ba giá trị: '' hoặc 'ma' = mua mã lẻ · 'nap' = nạp ví · 'ghe' = TRẢ THẲNG CHO MỘT
		      LƯỢT GHẾ bằng chuyển khoản. Đơn 'ghe' mang thêm `ma_may`, và nội dung chuyển khoản
		      của nó là "GHE<ghế> <mã đơn>" chứ không phải "MUA<mã đơn>" — nhờ vậy webhook cũ
		      nhận ra nó là một lượt ghế và cho ghế chạy mà KHÔNG phải sửa gì ở đường tiền.
		   ⚠️ Đơn ĐẶT TRƯỚC bản này không có cột đó, đọc ra RỖNG — và rỗng phải hiểu là 'ma',
		      đúng như hệ thống chạy trước đây. Đọc rỗng thành 'nap' là mọi đơn cũ chưa trả tiền
		      biến thành đơn nạp, khách trả tiền xong không nhận được mã.
		   ⚠️ MỌI CHÚ THÍCH PHẢI NẰM NGOÀI CHUỖI dưới đây. Nhét một khối chú thích PHP vào giữa
		      chuỗi SQL là nó thành SQL chứ không thành chú thích, và `CREATE TABLE` gãy ngay lúc
		      cài plugin. Đã dính đúng một lần lúc thêm cột `loai` này. */
		$b['don_ma'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_don VARCHAR(20) NOT NULL,
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			pin_bam VARCHAR(255) NOT NULL DEFAULT '',
			cc_bam VARCHAR(255) NOT NULL DEFAULT '',
			menh_gia BIGINT(20) NOT NULL DEFAULT 0,
			gia_ban BIGINT(20) NOT NULL DEFAULT 0,
			giam_pt INT NOT NULL DEFAULT 0,
			cho_ngay INT NOT NULL DEFAULT 0,
			so_luong INT NOT NULL DEFAULT 1,
			phai_tra BIGINT(20) NOT NULL DEFAULT 0,
			loai VARCHAR(10) NOT NULL DEFAULT '',
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			nhan_tien BIGINT(20) NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			xong_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_don (ma_don),
			KEY cho (xong_luc,tao_luc)";

		/* ===== 11. VÍ — SỐ DƯ CỦA KHÁCH ====================================================
		   Gói nạp: nạp 100k được 120k. Khoản chênh đó là KHUYẾN MÃI ĐÃ HỨA, và tiền khách đã
		   trả rồi — nên số dư chưa tiêu là một khoản NỢ của cửa hàng, không phải doanh thu.
		   Xem `VHG_Vi::tong_no()`; màn quản trị hiện nó ra để không ai tưởng đã ăn xong.

		   🔴 HAI CỘT SỐ DƯ, KHÔNG PHẢI MỘT.
		      · `so_du_dung` — tiêu được NGAY.
		      · `so_du_cho`  — còn trong hạn chờ (mua trước 5 ngày mới dùng được).
		      Gộp một cột là mất hẳn khả năng nói "anh có 120k nhưng 120k đó ngày mai mới tiêu
		      được", mà đó đúng là câu khách sẽ hỏi.

		   ⚠️ TIÊU TIỀN PHẢI QUA `UPDATE ... WHERE so_du_dung >= x`. Đọc số dư rồi trừ trong PHP
		      là hai máy cùng bấm một lúc thì cùng đọc thấy đủ tiền, và ví âm. Xem `VHG_Vi::tru()`. */
		$b['vi'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			sdt VARCHAR(20) NOT NULL,
			pin_bam VARCHAR(255) NOT NULL DEFAULT '',
			cc_bam VARCHAR(255) NOT NULL DEFAULT '',
			so_du_dung BIGINT(20) NOT NULL DEFAULT 0,
			so_du_cho BIGINT(20) NOT NULL DEFAULT 0,
			da_nap BIGINT(20) NOT NULL DEFAULT 0,
			da_tieu BIGINT(20) NOT NULL DEFAULT 0,
			tich INT NOT NULL DEFAULT 0,
			tich_tong INT NOT NULL DEFAULT 0,
			khoa TINYINT(1) NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY sdt (sdt),
			KEY con (so_du_dung,so_du_cho)";

		/* ===== 12. SỔ VÍ — MỌI THAY ĐỔI SỐ DƯ ĐỀU CÓ MỘT DÒNG =============================
		   🔴 BẢNG NÀY MỚI LÀ LỜI GIẢI THÍCH, cột `so_du_dung` chỉ là con số hiện ra.

		      Khách hỏi "sao em còn có 90k?" mà chỉ có một con số thì không ai trả lời được, và
		      lúc đó cửa hàng phải chọn giữa mất khách hoặc cho không. Có sổ thì mở ra đọc: nạp
		      120k ngày ấy, tiêu 10k ở ghế AMTP01 lúc ấy, tiêu 20k ở ghế kia lúc ấy.

		   ⚠️ `da_chin = 0` là dòng nạp CÒN TRONG HẠN CHỜ. Tới hạn thì `VHG_Vi::chin()` lật cờ
		      và chuyển tiền từ `so_du_cho` sang `so_du_dung`. Lật cờ bằng
		      `UPDATE ... WHERE id=x AND da_chin=0` nên chỉ MỘT lượt gọi chuyển được tiền, dù
		      có mười lượt chạy cùng lúc. */
		$b['vi_so'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			sdt VARCHAR(20) NOT NULL,
			thay_doi BIGINT(20) NOT NULL DEFAULT 0,
			so_du_sau BIGINT(20) NOT NULL DEFAULT 0,
			loai VARCHAR(20) NOT NULL DEFAULT '',
			dung_duoc_tu DATETIME NULL,
			da_chin TINYINT(1) NOT NULL DEFAULT 1,
			ref VARCHAR(120) NOT NULL DEFAULT '',
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			ai VARCHAR(190) NOT NULL DEFAULT '',
			luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY nguoi (sdt,luc),
			KEY chin (da_chin,dung_duoc_tu),
			KEY moi (ref)";

		/* ===== 13. QUÀ TÍCH LƯỢT ======================================================
		   Anh Thắng 23/08/2026: *"sau 10 lượt, khách được ưu đãi tặng quà"*, và *"cả 2"* —
		   vừa lượt miễn phí vừa quà tri ân.

		   🔴 MỘT DÒNG CHO MỖI PHẦN QUÀ, không phải một con số đếm trên ví.
		      Quà vật lý phải có người TRAO và có lúc trao. Giữ bằng một con số "còn 2 phần quà"
		      thì không ai trả lời được *"phần quà tháng trước ai đưa, đưa lúc nào"* — mà đó
		      chính là câu sẽ được hỏi khi có tranh cãi. Mỗi phần quà một dòng thì mở sổ ra đọc.

		   ⚠️ `luot_da_cong` = phần thưởng KIỂU LƯỢT đã cộng vào ví hay chưa. Tách khỏi
		      `nhan_luc` (lúc nhân viên trao quà vật lý): một phần quà "cả hai" có thể đã cộng
		      tiền vào ví mà quà vật lý thì tuần sau khách mới ghé lấy. */
		$b['vi_qua'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			sdt VARCHAR(20) NOT NULL,
			kieu VARCHAR(10) NOT NULL DEFAULT '',
			moc INT NOT NULL DEFAULT 0,
			gia_tri BIGINT(20) NOT NULL DEFAULT 0,
			luot_da_cong TINYINT(1) NOT NULL DEFAULT 0,
			nhan_luc DATETIME NULL,
			nhan_ai VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY nguoi (sdt,nhan_luc),
			KEY cho (nhan_luc,tao_luc)";

		/* ===== 14. CHỐT CA THEO GHẾ — ĐỌC CHỈ SỐ MÁY ĐẾM TIỀN ==============================
		   Anh Thắng 23/08/2026: *"Mở ứng dụng tới quét QR tại máy. Bấm thu tiền (chốt ca, dữ
		   liệu chốt ca). Nhập số tiền mặt, chỉ số máy tiền mặt — trên máy có 1 màn hình đếm tiền
		   mặt nữa, nên nhập vào để trừ chỉ số cho ngày hôm sau"*.

		   🔴 BA CON SỐ CHO CÙNG MỘT XẤP TIỀN, VÀ CHÚNG PHẢI KHỚP NHAU.
		      · `theo_may`      = (chỉ số lần này − chỉ số lần trước) × đồng mỗi đơn vị.
		                          Máy đếm tiền tự nói nó đã nuốt bao nhiêu. Đây là con số KHÔNG
		                          phụ thuộc vào ESP32, vào mạng, vào điện — nó nằm trong phần
		                          cứng của cục nhận tiền.
		      · `theo_he_thong` = tổng các lượt ghế BÁO VỀ máy chủ trong cùng quãng đó.
		      · `tien_dem`      = tiền mặt người thu đếm được thật trong ngăn.

		   Hai kiểu lệch, hai nguyên nhân KHÁC HẲN nhau — nên tách thành hai cột chứ không gộp:
		      · `lech_dem` (đếm ≠ máy)        -> thiếu/thừa tiền trong ngăn. Chuyện của NGƯỜI.
		      · `lech_may` (máy ≠ hệ thống)   -> ghế nuốt tiền mà không báo được về (mất mạng,
		                                        mất điện giữa chừng, ESP32 sót xung). Chuyện của
		                                        MÁY, và là doanh thu đang thiếu trong sổ.
		      Gộp hai con số này lại thành một "chênh lệch" là mất đúng thông tin để đi sửa.

		   🔴 `ma_lan` — MÃ LƯỢT DO ĐIỆN THOẠI SINH, ĐỂ GỬI LẠI KHÔNG GHI HAI LẦN.
		      App Android chốt ca ở chỗ sóng yếu: gửi đi, chờ mãi không thấy trả lời, rồi gửi lại.
		      Không có mã này thì mỗi lần gửi lại là một lượt chốt mới — chỉ số nhảy hai lần, tiền
		      trên tay cộng đôi, và người thu bỗng nợ gấp đôi số họ đang cầm.
		      Khoá UNIQUE nhận NULL nhiều lần (đúng luật MySQL), nên trang web gửi không kèm mã
		      vẫn ghi được bình thường — chỉ app mới cần tới nó.

		   🔴 QUÃNG THỜI GIAN ĐÁNH DẤU BẰNG **SỐ DÒNG**, KHÔNG BẰNG ĐỒNG HỒ.
		      `tu_id`/`den_id` là khoảng id trên bảng `thu` mà lượt chốt này bao trùm. Cắt theo
		      `luc > <giờ chốt trước>` nghe hợp lý hơn, nhưng nó hỏng ở đúng hai chỗ:
		        · Hai lượt rơi vào CÙNG MỘT GIÂY thì `>` bỏ mất dòng, còn `>=` thì đếm nó hai lần.
		          Ghế nuốt tờ tiền ngay lúc người thu bấm chốt là chuyện xảy ra thật.
		        · Giờ máy chủ đang lệch múi (site chạy UTC, lệch 7 tiếng) — đã cắn hệ này rồi. Bất
		          kỳ phép cắt nào dựa vào đồng hồ đều đi theo cái lệch đó.
		      Số dòng thì không có hai chuyện ấy: mỗi đồng nằm trong đúng một quãng, không sót,
		      không lặp.

		   ⚠️ `chi_so_truoc` CHÉP LẠI chứ không tra ngược mỗi lần đọc. Ghế bị thay cục nhận tiền,
		      hay ai đó xoá một dòng chốt, thì tra ngược cho ra một con số khác với con số người
		      đứng đó đã nhìn thấy và đã ký. Sổ phải giữ nguyên cái đã ghi. */
		$b['chot'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			chi_so BIGINT(20) NOT NULL DEFAULT 0,
			chi_so_truoc BIGINT(20) NOT NULL DEFAULT 0,
			don_vi BIGINT(20) NOT NULL DEFAULT 0,
			theo_may BIGINT(20) NOT NULL DEFAULT 0,
			theo_he_thong BIGINT(20) NOT NULL DEFAULT 0,
			tien_dem BIGINT(20) NOT NULL DEFAULT 0,
			lech_dem BIGINT(20) NOT NULL DEFAULT 0,
			lech_may BIGINT(20) NOT NULL DEFAULT 0,
			lan_dau TINYINT(1) NOT NULL DEFAULT 0,
			tu_id BIGINT(20) NOT NULL DEFAULT 0,
			den_id BIGINT(20) NOT NULL DEFAULT 0,
			tu_luc DATETIME NULL,
			tao_luc DATETIME NULL,
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			nop_id BIGINT(20) NOT NULL DEFAULT 0,
			ma_lan VARCHAR(40) NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_lan (ma_lan),
			KEY may (ma_may,id),
			KEY nguoi (nguoi,nop_id),
			KEY nop (nop_id)";

		/* ===== 15. NỘP TIỀN VỀ QUỸ =========================================================
		   Tiền trong tay người thu là tiền của cửa hàng đang nằm ở chỗ không ai nhìn thấy. Bảng
		   này là chỗ nó chuyển tay, và là chỗ DUY NHẤT trả lời được câu "ai đang cầm bao nhiêu".

		   🔴 HAI CON SỐ, KHÔNG PHẢI MỘT.
		      · `so_tien`     = tổng các dòng ĐÃ GẮN vào lượt nộp này. Máy tự cộng, không ai gõ.
		      · `so_tien_nhan`= người nhận đếm lại được bao nhiêu. Người nhận gõ.
		      Bằng nhau là xong. Lệch là có chuyện, và phải thấy được CẢ HAI con số mới biết lệch
		      bao nhiêu — ghi đè một con số lên con số kia là xoá mất bằng chứng.

		   ⚠️ `so_tien` tính SAU khi gắn dòng, từ chính những dòng gắn được — không cộng trước
		      rồi gắn sau. Hai người cùng bấm nộp một lúc thì người thứ hai gắn được 0 dòng, và
		      một lượt nộp 0 đồng phải bị từ chối chứ không được ghi vào sổ. */
		$b['nop'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			so_tien BIGINT(20) NOT NULL DEFAULT 0,
			so_tien_nhan BIGINT(20) NOT NULL DEFAULT 0,
			so_dong INT NOT NULL DEFAULT 0,
			trang_thai VARCHAR(10) NOT NULL DEFAULT 'cho',
			tao_luc DATETIME NULL,
			nhan_luc DATETIME NULL,
			nhan_ai VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			ma_lan VARCHAR(40) NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_lan (ma_lan),
			KEY nguoi (nguoi,trang_thai),
			KEY cho (trang_thai,tao_luc)";

		/* ===== 16. NHẬT KÝ BẬT/TẮT GHẾ — GHI TỪ CHÂN BÁO-CHẠY CỦA BO GHẾ ====================
		   Anh Thắng 27/08/2026: *"Nhật ký bật tắt máy, bật máy thì bộ QR gửi về, tắt thì từ lúc
		   mất tín hiệu QR"*.

		   🔴 ĐÂY LÀ NHẬT KÝ VẬN HÀNH THẬT, KHÁC HẲN BẢNG `lenh`.
		      · `lenh`   = có người BẤM cho ghế chạy (cho không một lượt). Ý định của người.
		      · `bat_tat`= ghế THẬT SỰ chạy/dừng, đo từ chân báo-chạy của bo ghế (GHECHAY_PIN).
		        Không quan tâm vì sao chạy — QR trả tiền, tiền mặt, hay ai đó bấm tay đều vào đây.
		      Ghép hai thứ là mất đúng cái để đối chiếu: "web bảo bật mà ghế có chạy thật không".

		   🔴 MỖI LẦN CHUYỂN TRẠNG THÁI MỘT DÒNG, không đè. Đây là nhật ký (chỉ thêm), khác bảng
		      `nhip` (một hàng một máy, đè lên). Cần để xem lại cả chuỗi bật/tắt trong ngày.

		   `luc` = thời điểm chuyển trạng thái THẬT — ghế khai TUỔI (mấy giây trước), máy chủ đổi ra
		   giờ tuyệt đối của mình, y hệt cách `tm_luc` làm. Ghế không có đồng hồ thật.

		   `giay` = chỉ điền cho dòng 'tat': ghế vừa chạy được bao nhiêu giây (từ 'bat' gần nhất tới
		   'tat' này). Tính SẴN lúc ghi để bảng lịch sử khỏi phải ghép cặp bật–tắt mỗi lần mở. */
		$b['bat_tat'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			su_kien VARCHAR(10) NOT NULL DEFAULT '',
			luc DATETIME NULL,
			giay INT NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY may (ma_may,id),
			KEY luc (luc)";

		/* ===== 17. BÁO CÁO DOANH THU THEO CƠ SỞ (port từ web Apps Script "thu tiền") ===========
		   Anh Thắng 27/08/2026: đưa app thu-tiền-nhập-báo-cáo của nhân viên vào web ghế.

		   🔴 CÔNG THỨC BẤT BIẾN (giữ y app gốc): actual=(chỉ số sau − trước)×đơn_vị ;
		      tiền_mặt = actual − QR ± điều_chỉnh ; tổng = tiền_mặt + QR. Server tự tính, ép chỉ số
		      trước — KHÔNG tin số client. (App gốc cứng ×10000; ở đây dùng đơn_vị chốt-ca cấu hình
		      sẵn để KHỚP với chốt ca máy trạm — hai bên cùng một máy đếm.)

		   🔴 1 BÁO CÁO / CƠ SỞ / NGÀY: UNIQUE(coso_key, ngay) chặn ở tầng CSDL; gửi lại = cập nhật.
		   `coso_key` = tên cơ sở đã bỏ dấu/khoảng trắng (so khớp bất kể cách gõ), `coso` giữ tên hiện.

		   TÁCH HEADER (`bc`) / DÒNG GHẾ (`bc_dong`) — sạch hơn bản Sheet phẳng: tiền nộp nằm ở
		   header (không còn mẹo "nhét vào dòng đầu"), mỗi ghế một dòng chi tiết. */
		$b['bc'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			report_id VARCHAR(40) NOT NULL,
			ngay DATE NOT NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			coso_key VARCHAR(190) NOT NULL DEFAULT '',
			nhan_vien VARCHAR(190) NOT NULL DEFAULT '',
			nop_hinhthuc VARCHAR(20) NOT NULL DEFAULT '',
			nop_trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			nop_so_tien BIGINT(20) NOT NULL DEFAULT 0,
			nop_ngay DATE NULL,
			nop_ghichu VARCHAR(255) NOT NULL DEFAULT '',
			unpaid_lydo VARCHAR(255) NOT NULL DEFAULT '',
			ck_ref VARCHAR(120) NOT NULL DEFAULT '',
			ck_bank VARCHAR(60) NOT NULL DEFAULT '',
			chung_tu TEXT NULL,
			kt_doi_soat TINYINT(1) NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY report_id (report_id),
			UNIQUE KEY coso_ngay (coso_key,ngay),
			KEY ngay (ngay)";

		$b['bc_dong'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			report_id VARCHAR(40) NOT NULL,
			ma_may VARCHAR(40) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			chi_so_truoc BIGINT(20) NULL,
			chi_so_sau BIGINT(20) NULL,
			actual BIGINT(20) NOT NULL DEFAULT 0,
			tien_mat BIGINT(20) NOT NULL DEFAULT 0,
			qr BIGINT(20) NOT NULL DEFAULT 0,
			dieu_chinh BIGINT(20) NOT NULL DEFAULT 0,
			tong BIGINT(20) NOT NULL DEFAULT 0,
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			anh TEXT NULL,
			nop_so_tien BIGINT(20) NOT NULL DEFAULT 0,
			nop_trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			nop_hinhthuc VARCHAR(20) NOT NULL DEFAULT '',
			nop_ngay DATE NULL,
			kt_duyet TINYINT(1) NOT NULL DEFAULT 0,
			kt_duyet_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dong (report_id,ma_may),
			KEY may_ngay (ma_may,ngay)";

		/* Đề nghị đổi/xoá chỉ số — nhân viên gửi, kế toán duyệt (trang kế toán làm sau). Duyệt xong
		   ghi `moc_chiso`/`moc_chiso_ngay` vào bảng `may`; `chi_so_truoc()` tự áp từ ngày hiệu lực. */
		$b['bc_denghi'] = "
			id VARCHAR(40) NOT NULL,
			tao_luc DATETIME NULL,
			nhan_vien VARCHAR(190) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			ten VARCHAR(190) NOT NULL DEFAULT '',
			tu_ngay DATE NULL,
			chi_so BIGINT(20) NULL,
			loai VARCHAR(10) NOT NULL DEFAULT 'dat_lai',
			ly_do VARCHAR(255) NOT NULL DEFAULT '',
			trang_thai VARCHAR(20) NOT NULL DEFAULT 'cho_duyet',
			duyet_boi VARCHAR(190) NOT NULL DEFAULT '',
			duyet_luc DATETIME NULL,
			ghi_chu_kt VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY may_tt (ma_may,trang_thai),
			KEY coso (coso)";

		/* Ngày KHOÁ theo cơ sở (kế toán ghi ở trang kế toán; nhân viên chỉ đọc). Khoá thì chặn
		   gửi/sửa/nộp bổ sung đúng cơ sở+ngày đó. */
		$b['bc_khoa'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			coso_key VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			khoa_luc DATETIME NULL,
			boi VARCHAR(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY coso_ngay (coso_key,ngay)";

		/* Yêu cầu của kế toán (làm bổ sung / sửa). Nhân viên gửi/sửa đúng cơ sở+ngày là tự đóng. */
		$b['bc_yeucau'] = "
			id VARCHAR(40) NOT NULL,
			tao_luc DATETIME NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			coso_key VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NULL,
			loai VARCHAR(20) NOT NULL DEFAULT 'bo_sung',
			noi_dung VARCHAR(500) NOT NULL DEFAULT '',
			tao_boi VARCHAR(190) NOT NULL DEFAULT '',
			trang_thai VARCHAR(20) NOT NULL DEFAULT 'cho_lam',
			xong_luc DATETIME NULL,
			xong_boi VARCHAR(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY loc (coso_key,ngay,trang_thai)";

		/* PIN nhân viên báo cáo — DANH TÍNH RIÊNG, KHÔNG dùng token /ghe. Mỗi PIN: tên + danh sách
		   cơ sở (`coso`, nhiều mục ngăn bởi , hoặc ;) + ghế riêng (`ghe`) nếu cần vượt cơ sở.
		   Đăng nhập báo cáo = nhập PIN. Về sau nối `pin` này sang PIN chấm công K&H là một mối
		   (chấm công + nộp báo cáo + ghi chi phí chung một danh tính) — anh Thắng 27/08/2026.
		   ⛔ REPO CÔNG KHAI → KHÔNG seed PIN trong mã; Admin tự nhập ở màn quản lý. */
		$b['bc_pin'] = "
			pin VARCHAR(20) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			coso VARCHAR(2000) NOT NULL DEFAULT '',
			ghe VARCHAR(1000) NOT NULL DEFAULT '',
			active TINYINT(1) NOT NULL DEFAULT 1,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (pin)";

		/* PHIÊN THU MỘT NGÀY của một nhân viên (theo PIN). Anh Thắng 27/08/2026: nhập tới máy cuối
		   thì hệ thống báo ĐỦ BÁO CÁO rồi gộp cả ngày gửi kế toán; còn 1–2 điểm chưa thu được thì
		   xin CHỐT CA SỚM để chốt luôn. Một dòng / (pin, ngày).
		   trang_thai: 'dang_thu' | 'da_gui' (đủ cơ sở) | 'chot_som' (chốt khi còn thiếu điểm).
		   bo_qua = danh sách cơ sở chưa thu lúc chốt sớm (ngăn bởi phẩy). */
		$b['bc_phien'] = "
			pin VARCHAR(20) NOT NULL,
			ngay DATE NOT NULL,
			nhan_vien VARCHAR(190) NOT NULL DEFAULT '',
			trang_thai VARCHAR(20) NOT NULL DEFAULT 'dang_thu',
			chot_som TINYINT(1) NOT NULL DEFAULT 0,
			ly_do VARCHAR(255) NOT NULL DEFAULT '',
			bo_qua VARCHAR(1000) NOT NULL DEFAULT '',
			so_coso INT NOT NULL DEFAULT 0,
			so_coso_xong INT NOT NULL DEFAULT 0,
			tong_tien_mat BIGINT(20) NOT NULL DEFAULT 0,
			tong_qr BIGINT(20) NOT NULL DEFAULT 0,
			tong BIGINT(20) NOT NULL DEFAULT 0,
			gui_luc DATETIME NULL,
			tao_luc DATETIME NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY  (pin,ngay),
			KEY ngay (ngay)";

		/* ===== TRANG KẾ TOÁN (chặng 2) ===== */

		/* Mã nộp tiền (nội dung chuyển khoản) ↔ cơ sở — cho đối soát CK. Kế toán nhập/sửa. */
		$b['bc_ma_nop'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			code VARCHAR(120) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			coso_key VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY code (code),
			KEY coso (coso_key)";

		/* Unit ID MISA ↔ cơ sở kế toán — cho báo cáo ngày (DAILY SALES). Kế toán nhập; có mồi. */
		$b['bc_ma_misa'] = "
			coso_key VARCHAR(190) NOT NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			unit_id VARCHAR(40) NOT NULL DEFAULT '',
			unit_name VARCHAR(190) NOT NULL DEFAULT '',
			vung VARCHAR(80) NOT NULL DEFAULT '',
			thu_tu INT NOT NULL DEFAULT 0,
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (coso_key)";

		/* Dư đầu kỳ công nợ (số chốt) — sổ công nợ lấy làm gốc rồi cộng lũy kế các tháng sau. */
		$b['bc_congno_dau'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			thang VARCHAR(7) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			coso_key VARCHAR(190) NOT NULL DEFAULT '',
			so_tien BIGINT(20) NOT NULL DEFAULT 0,
			chot_luc DATETIME NULL,
			boi VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY coso_thang (coso_key,thang)";

		/* Nhật ký thao tác kế toán CÓ THỂ HOÀN TÁC (sửa ô, áp QR…) — giữ giá trị cũ dạng JSON. */
		$b['bc_undo'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			viec VARCHAR(40) NOT NULL DEFAULT '',
			ly_do VARCHAR(255) NOT NULL DEFAULT '',
			chi_tiet LONGTEXT NULL,
			da_hoan_tac TINYINT(1) NOT NULL DEFAULT 0,
			boi VARCHAR(190) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY luc (tao_luc)";

		/* THÙNG RÁC: dòng bc_dong đã xoá — GIỮ trọn dạng JSON để hoàn tác (bài học mất 1279 dòng). */
		$b['bc_rac'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			report_id VARCHAR(40) NOT NULL DEFAULT '',
			ma_may VARCHAR(40) NOT NULL DEFAULT '',
			ngay DATE NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			snapshot LONGTEXT NULL,
			ly_do VARCHAR(255) NOT NULL DEFAULT '',
			boi VARCHAR(190) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			hoan_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY luc (tao_luc)";

		/* Hỏi-đáp hướng dẫn dùng web (nhân viên). Web tự trả lời từ bảng này (khớp từ khoá),
		   KHÔNG gọi ra ngoài. Chỉ hướng dẫn thao tác, KHÔNG tra số liệu. */
		$b['bc_hoidap'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			tu_khoa VARCHAR(255) NOT NULL DEFAULT '',
			cau_hoi VARCHAR(255) NOT NULL DEFAULT '',
			tra_loi TEXT NULL,
			thu_tu INT NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY tt (active,thu_tu)";

		return $b;
	}

	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
