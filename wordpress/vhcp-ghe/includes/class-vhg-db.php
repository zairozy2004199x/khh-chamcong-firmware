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
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
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

		return $b;
	}

	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
