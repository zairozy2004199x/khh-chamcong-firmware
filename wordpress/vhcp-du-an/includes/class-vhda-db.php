<?php
/**
 * BẢNG CỦA HỆ DỰ ÁN & TIẾN ĐỘ.
 *
 * ⚠️ Tiền tố riêng `vhda_` — KHÔNG dùng chung bảng với plugin nào khác. Bốn plugin cài độc lập,
 *    gỡ độc lập; dùng chung bảng thì gỡ một cái là làm hỏng cái kia.
 *
 * ⚠️ KHÔNG ĐẶT CHÚ THÍCH BÊN TRONG CHUỖI SQL. Bộ khung thử dựng bảng SQLite bằng cách đọc từng
 *    DÒNG của chuỗi này; một dòng chú thích rơi vào đó thì nó tưởng là một cột và cả bảng dựng
 *    hỏng — "table has no column named ...", một lỗi trông y như lỗi của plugin.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHDA_DB {

	public static function t( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'vhda_' . $ten;
	}

	public static function bang() {
		$b = array();

		/* ===== DỰ ÁN =====
		   `coso` là TÊN cơ sở, giống mọi bảng khác của hệ — cơ sở ở đây được nhận ra bằng chuỗi
		   tên, không có id riêng, và bịa ra một id là hai nơi phải đồng bộ với nhau mãi.

		   `chang` giữ MÃ chặng (`VHDA_Luong`), không giữ tên tiếng Việt: đổi tên hiện ra màn thì
		   sửa một hằng, còn đổi mã là mọi dự án đã lưu trỏ vào chặng không còn tồn tại.

		   Hai cột ngày `ngay_thi_cong` / `ngay_mo_cua` để NULL được: chúng chỉ có sau chặng
		   "Chốt ngày", và ép 0000-00-00 vào đó là ngày rác lọt lên báo cáo. */
		$b['du_an'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(40) NOT NULL,
			ten VARCHAR(255) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			khach VARCHAR(190) NOT NULL DEFAULT '',
			so_hop_dong VARCHAR(80) NOT NULL DEFAULT '',
			gia_tri BIGINT(20) NOT NULL DEFAULT 0,
			chang VARCHAR(30) NOT NULL DEFAULT 'hop_dong',
			chang_truoc VARCHAR(30) NOT NULL DEFAULT '',
			phuong_an TEXT NULL,
			ngay_thi_cong DATE NULL,
			ngay_mo_cua DATE NULL,
			nguoi_tao VARCHAR(190) NOT NULL DEFAULT '',
			ma_nv_tao VARCHAR(40) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma (ma),
			KEY theo_chang (chang,sua_luc),
			KEY theo_coso (coso)";

		/* ===== VIỆC BÀN GIAO CHO TỪNG BỘ PHẬN =====
		   Một dự án bàn giao xuống nhiều bộ phận; mỗi bộ phận một dòng, tự cập nhật phần trăm
		   của mình. `bo_phan` lưu TÊN bộ phận — cùng lý do với `coso`.

		   `phan_tram` là 0..100 do bộ phận tự khai. KHÔNG tự tính từ số việc con: mỗi bộ phận
		   một kiểu việc, và ép họ chẻ nhỏ ra mới báo được tiến độ thì họ sẽ không báo. */
		$b['viec'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			du_an_id BIGINT(20) NOT NULL,
			bo_phan VARCHAR(120) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			han DATE NULL,
			phan_tram SMALLINT NOT NULL DEFAULT 0,
			xong TINYINT(1) NOT NULL DEFAULT 0,
			nguoi_giao VARCHAR(190) NOT NULL DEFAULT '',
			giao_luc DATETIME NULL,
			cap_nhat_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_bo_phan (du_an_id,bo_phan),
			KEY theo_du_an (du_an_id)";

		/* ===== NHẬT KÝ =====
		   Mọi lượt chuyển chặng, giao việc, cập nhật tiến độ đều để lại một dòng. Đây là thứ
		   trả lời được câu "ai chốt ngày này, lúc nào" ba tháng sau — mà không có nó thì câu ấy
		   chỉ còn cách hỏi mồm. */
		$b['nhat_ky'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			du_an_id BIGINT(20) NOT NULL,
			viec VARCHAR(40) NOT NULL DEFAULT '',
			tu_chang VARCHAR(30) NOT NULL DEFAULT '',
			den_chang VARCHAR(30) NOT NULL DEFAULT '',
			bo_phan VARCHAR(120) NOT NULL DEFAULT '',
			chi_tiet TEXT NULL,
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY theo_du_an (du_an_id,id)";

		/* ===== ĐƠN CHI PHÍ GÁN VÀO DỰ ÁN =====
		   🔴 CHỈ LƯU MÃ ĐƠN, KHÔNG CHÉP TIỀN SANG ĐÂY. Chép số tiền là hai kho cùng giữ một con
		      số, và sớm muộn chúng lệch nhau — lúc ấy không ai biết kho nào đúng. Tiền hỏi thẳng
		      plugin chi phí mỗi lần hiện (xem `VHDA_Tien`). */
		$b['don'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			du_an_id BIGINT(20) NOT NULL,
			ma_don VARCHAR(60) NOT NULL DEFAULT '',
			nguoi_gan VARCHAR(190) NOT NULL DEFAULT '',
			gan_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mot_don (du_an_id,ma_don),
			KEY theo_du_an (du_an_id)";

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
}
