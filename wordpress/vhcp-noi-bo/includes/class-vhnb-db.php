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
		$b['bai'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			nhom VARCHAR(60) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(30) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			ghim TINYINT(1) NOT NULL DEFAULT 0,
			so_tim INT NOT NULL DEFAULT 0,
			so_bl INT NOT NULL DEFAULT 0,
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY moi (ghim,tao_luc),
			KEY theo_nhom (nhom,tao_luc),
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
