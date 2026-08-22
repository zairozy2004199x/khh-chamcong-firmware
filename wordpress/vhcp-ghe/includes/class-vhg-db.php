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
		$b['may'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(40) NOT NULL,
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
			KEY coso_id (coso_id)";

		/* ===== 3. DOANH THU ================================================================
		   🔴 `ref` UNIQUE là RÀNG BUỘC QUAN TRỌNG NHẤT CỦA CẢ PLUGIN. Xem khối đầu tệp.

		   `ma_may` có thể RỖNG: giao dịch nội dung mơ hồ ("PaymentForOrder") thì lúc nhận chưa
		   biết máy nào. Không được vì thế mà bỏ — tiền đã vào tài khoản rồi. Giữ lại, rồi gán
		   máy sau bằng bản đồ máy (bảng `ban_do`).

		   `ten_khai` giữ NGUYÊN VĂN tên máy đọc được từ nội dung/cột của bên gửi, chưa chuẩn hoá
		   — để còn đối chiếu khi bản đồ máy sai. */
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
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY luc (luc),
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
		$b['nhip'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_may VARCHAR(40) NOT NULL,
			trang_thai VARCHAR(20) NOT NULL DEFAULT '',
			nguon VARCHAR(20) NOT NULL DEFAULT '',
			con_lai INT NOT NULL DEFAULT 0,
			ip VARCHAR(60) NOT NULL DEFAULT '',
			fw VARCHAR(40) NOT NULL DEFAULT '',
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

		return $b;
	}

	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
