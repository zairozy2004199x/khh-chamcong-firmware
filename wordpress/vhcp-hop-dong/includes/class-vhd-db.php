<?php
/**
 * Bảng của plugin: phiên đăng nhập, và (từ 1.1.0) KHO HỢP ĐỒNG.
 *
 * 🔴 TRƯỚC 1.1.0 PLUGIN CỐ Ý KHÔNG GIỮ GÌ — dữ liệu ở Google Sheet, plugin chỉ là cầu nối, nhờ
 *    vậy không bao giờ có hai nguồn sự thật lệch nhau. Anh Thắng 02/09/2026 hỏi đẩy thư viện lên
 *    web và chạy nội dung trên đó, nên nay CÓ bản sao — và cái rủi ro cũ quay lại. Nó được chặn
 *    bằng ba luật cứng ghi ở đầu `VHD_Kho`: Sheet vẫn là nguồn, host là bản sao ĐỌC (không một
 *    đường nào ghi ngược); mỗi lần kéo là chép lại toàn bộ; và dòng gốc được giữ nguyên văn.
 *
 * Bảng NGƯỜI DÙNG thì dùng chung với plugin Vận hành chi phí (xem class-vhd-auth.php) để nhân sự
 * chỉ khai một lần — thêm/xoá người ở hai nơi là kiểu lỗ hổng "xoá một nơi quên nơi kia".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_DB {

	const SCHEMA_VERSION = '1.1.0';

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'vhd_' . $name;
	}

	/**
	 * THÂN CỦA MỌI BẢNG — KHAI MỘT NƠI DUY NHẤT.
	 *
	 * 🔴 Bài kiểm dựng bảng bằng SQLite từ CHÍNH mảng này (xem `vhd_test_boot`), nên sơ đồ thật
	 *    và sơ đồ bài kiểm không thể lệch nhau. Trước bản này bảng `session` được gõ tay lần thứ
	 *    hai trong `wp-stub.php` — đúng cái bẫy "khai hai nơi" mà plugin chấm công đã sập một
	 *    lần: thêm cột vào sơ đồ thật thì bài kiểm chết bằng một lỗi trông như lỗi của plugin.
	 */
	public static function bang() {
		return array(
			'session' => "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			token CHAR(64) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			het_han DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY het_han (het_han)",

			/* Kho hợp đồng — xem ba luật cứng ở đầu `VHD_Kho`. Cột `lo` là cách "thay toàn bộ"
			   mà không bao giờ để kho trống; `du_lieu` giữ nguyên văn dòng gốc của Sheet. */
			'hd' => "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(190) NOT NULL DEFAULT '',
			ten TEXT NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ben_a VARCHAR(255) NOT NULL DEFAULT '',
			ben_b VARCHAR(255) NOT NULL DEFAULT '',
			ngay_ky DATE NULL,
			ngay_het DATE NULL,
			tien BIGINT(20) NOT NULL DEFAULT 0,
			link TEXT NULL,
			du_lieu LONGTEXT NULL,
			hang INT(11) NOT NULL DEFAULT 0,
			lo VARCHAR(32) NOT NULL DEFAULT '',
			cap_nhat DATETIME NULL,
			PRIMARY KEY  (id),
			KEY ma (ma),
			KEY coso (coso),
			KEY ngay_het (ngay_het),
			KEY lo (lo)",
		);
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		foreach ( self::bang() as $ten => $than ) {
			dbDelta( 'CREATE TABLE ' . self::t( $ten ) . " ($than\n\t\t) $c" );
		}
		update_option( 'vhd_db_version', self::SCHEMA_VERSION );
	}

	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
