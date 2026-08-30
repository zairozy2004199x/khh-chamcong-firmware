<?php
/**
 * BẢNG CỦA TRANG NỘI BỘ.
 *
 * ⚠️ Tiền tố riêng `vhnb_` — KHÔNG dùng chung bảng với plugin chấm công. Hai plugin cài độc lập,
 *    gỡ độc lập; dùng chung bảng thì gỡ một cái là làm hỏng cái kia.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_DB {

	public static function t( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'vhnb_' . $ten;
	}

	public static function bang() {
		$b = array();

		/* ===== BÀI ĐĂNG =====
		   `nhom` = phạm vi bài: '' là toàn công ty, còn lại là tên bộ phận. Lưu TÊN chứ không
		   lưu id: bộ phận ở hệ chấm công là một chuỗi trong bảng `bo_phan_coso`, không có id
		   riêng — bịa ra một id ở đây là hai nơi phải đồng bộ với nhau mãi. */
		/* Cột `anh` giữ ĐỊA CHỈ ảnh kèm bài — xem `VHNB_Anh`. Một bài một ảnh: nhiều ảnh thì
		   phải có bảng riêng, mà bảng tin nội bộ chưa cần tới mức ấy.

		   ⚠️ KHÔNG ĐẶT CHÚ THÍCH BÊN TRONG CHUỖI SQL. Bộ khung thử dựng bảng SQLite bằng cách
		      đọc từng DÒNG của chuỗi này; một dòng chú thích rơi vào đó thì nó tưởng là một cột
		      và cả bảng dựng hỏng — "table has no column named anh", một lỗi trông y như lỗi
		      của plugin. Đã sập đúng bẫy này lúc thêm cột `anh` (26/08/2026). */
		$b['bai'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			nhom VARCHAR(60) NOT NULL DEFAULT '',
			nhom_id BIGINT(20) NOT NULL DEFAULT 0,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(30) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			anh VARCHAR(255) NOT NULL DEFAULT '',
			ghim TINYINT(1) NOT NULL DEFAULT 0,
			so_tim INT NOT NULL DEFAULT 0,
			so_bl INT NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY moi (ghim,tao_luc),
			KEY theo_nhom (nhom,tao_luc),
			KEY theo_nhom_id (nhom_id,ghim,tao_luc),
			KEY nguoi (ma_nv)";

		$b['binh_luan'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			bai_id BIGINT(20) NOT NULL,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY cua_bai (bai_id,tao_luc)";

		/* ===== THẢ TIM =====
		   UNIQUE trên (bai_id, ma_nv): một người một bài đúng một tim. Không có khoá này thì bấm
		   hai lần là hai tim, và con số dưới bài thành vô nghĩa. */
		$b['tim'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			bai_id BIGINT(20) NOT NULL,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_nguoi_mot_tim (bai_id,ma_nv)";

		/* ===== NHÓM TỰ TẠO =====
		   Anh Thắng 26/08/2026: *"nhớ thêm tạo nhóm chat nội bộ cho từng nhóm cho ai tự tạo ra
		   nhé, để mời ai vào thì thêm nv đó vào thôi"*.

		   🔴 KHÁC HẲN CỘT `nhom` Ở TRÊN. Cột ấy là BỘ PHẬN — một danh sách cố định lấy từ hệ
		      chấm công, ai cũng đọc được. Nhóm ở đây do người dùng tự lập, và CHỈ THÀNH VIÊN
		      đọc được. Hai thứ khác nhau nên hai chỗ khác nhau: nhét chung một cột thì một
		      nhóm tự tạo tên "Văn phòng" sẽ nuốt luôn bài của cả bộ phận Văn phòng. */
		$b['nhom'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ten VARCHAR(120) NOT NULL DEFAULT '',
			mo_ta VARCHAR(255) NOT NULL DEFAULT '',
			ma_nv_tao VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten_tao VARCHAR(190) NOT NULL DEFAULT '',
			so_tv INT NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY nguoi_tao (ma_nv_tao)";

		/* ===== THÀNH VIÊN NHÓM =====
		   UNIQUE trên (nhom_id, ma_nv): thêm một người hai lần vẫn là một người. Không có khoá
		   này thì đếm thành viên ra số vô nghĩa, và bỏ ra một lần vẫn còn trong nhóm. */
		$b['thanh_vien'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			nhom_id BIGINT(20) NOT NULL,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			vai VARCHAR(10) NOT NULL DEFAULT 'tv',
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_nguoi_mot_lan (nhom_id,ma_nv),
			KEY cua_nguoi (ma_nv)";

		/* ===== HỘP THƯ / CHUÔNG =====
		   Nhận tin từ CHÍNH trang này và từ plugin khác (chấm công · chi phí) — xem `VHNB_Bao`.

		   🔴 UNIQUE (ma_nv, khoa) LÀ THỨ LÀM NÊN VIỆC GỘP. Không có khoá này thì một bài được
		      20 người bình luận đẻ ra 20 dòng, và chuông thành chỗ không ai mở. Có nó thì lượt
		      thứ hai trở đi rơi vào đúng dòng cũ để cộng dồn.
		   ⚠️ `khoa` để VARCHAR(120) chứ không TEXT: MySQL không đánh chỉ mục UNIQUE trên TEXT. */
		$b['bao'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			nguon VARCHAR(30) NOT NULL DEFAULT '',
			khoa VARCHAR(120) NOT NULL DEFAULT '',
			chu VARCHAR(320) NOT NULL DEFAULT '',
			duong_dan VARCHAR(255) NOT NULL DEFAULT '',
			so_lan INT NOT NULL DEFAULT 1,
			da_doc TINYINT(1) NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_viec (ma_nv,khoa),
			KEY hop_thu (ma_nv,da_doc,tao_luc)";

		/* ===== TIN NHẮN RIÊNG (chat mini) =====
		   Anh Thắng 30/08/2026: *"bổ sung tab chat mini bên dưới để chat với thành viên"*.

		   🔴 MỘT HÀNG MỘT CHIỀU, KHÔNG PHẢI MỘT HÀNG MỘT CUỘC TRÒ CHUYỆN. `tu`/`den` là hai mã cố
		   định của MỘT tin — đọc cả cuộc trò chuyện thì hỏi `(tu=A AND den=B) OR (tu=B AND den=A)`.
		   Cách này khỏi phải bịa một "id cuộc trò chuyện" riêng rồi lo hai người nhắn nhau lần đầu
		   thì tạo cuộc trò chuyện ở đâu, ai tạo trước.

		   `tu_ten`/`den_ten` chép lại tên NGAY LÚC GỬI — cùng lý do với `ho_ten` ở bảng `bai`: đổi
		   tên trong hồ sơ sau này không viết lại lịch sử tin nhắn cũ. */
		$b['tin_nhan'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			tu VARCHAR(40) NOT NULL DEFAULT '',
			tu_ten VARCHAR(190) NOT NULL DEFAULT '',
			den VARCHAR(40) NOT NULL DEFAULT '',
			den_ten VARCHAR(190) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			da_doc TINYINT(1) NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY cuoc_di (tu,den,id),
			KEY cuoc_ve (den,tu,id),
			KEY hop_thu (den,da_doc)";

		return $b;
	}

	public static function dung_bang() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cs = $wpdb->get_charset_collate();
		foreach ( self::bang() as $ten => $than ) {
			dbDelta( 'CREATE TABLE ' . self::t( $ten ) . " (\n" . $than . "\n) $cs;" );
		}
	}

	/** Đọc nhiều hàng, luôn trả mảng — nơi gọi khỏi phải kiểm null. */
	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
