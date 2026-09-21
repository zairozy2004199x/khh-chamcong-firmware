<?php
/**
 * Bảng MySQL và việc nâng cấp cấu trúc.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_DB {

	/** Tăng số này mỗi lần đổi cấu trúc bảng thì bản đang chạy tự nâng cấp. */
	const SCHEMA = 1;

	public static function bang( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'khtc_' . $ten;
	}

	public static function tao_bang() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();

		$ngan_hang = self::bang( 'ngan_hang' );
		$giao_dich = self::bang( 'giao_dich' );

		// so_du_dau = số dư TÍNH ĐẾN ngay_dau; giao dịch trước ngày đó coi như đã
		// gộp sẵn vào số dư này, không cộng lại lần nữa (giữ đúng cách bản gốc tính).
		dbDelta(
			"CREATE TABLE $ngan_hang (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ten VARCHAR(190) NOT NULL,
				so_tk VARCHAR(60) NOT NULL DEFAULT '',
				so_du_dau BIGINT NOT NULL DEFAULT 0,
				ngay_dau DATE NULL,
				ghi_chu TEXT NULL,
				tao_luc DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY cty (cty)
			) $collate;"
		);

		// Chỉ mục (cty, ngay) là chỉ mục gánh mọi màn hình: lọc theo kỳ của một
		// công ty. Thiếu nó thì 219.000 dòng phải quét toàn bảng mỗi lần mở trang.
		dbDelta(
			"CREATE TABLE $giao_dich (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ngan_hang_id BIGINT UNSIGNED NOT NULL,
				ngay DATE NOT NULL,
				dien_giai TEXT NULL,
				so_tien BIGINT NOT NULL DEFAULT 0,
				loai VARCHAR(10) NOT NULL DEFAULT 'thu',
				ma_gd VARCHAR(120) NOT NULL DEFAULT '',
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY cty_ngay (cty, ngay),
				KEY ngan_hang_id (ngan_hang_id),
				KEY ma_gd (ma_gd)
			) $collate;"
		);

		update_option( 'khtc_schema', self::SCHEMA );
	}

	/** Cài đè bản mới mà cấu trúc đã đổi thì chạy lại dbDelta, khỏi phải gỡ cài lại. */
	public static function nang_cap_neu_can() {
		if ( (int) get_option( 'khtc_schema', 0 ) !== self::SCHEMA ) {
			self::tao_bang();
		}
	}
}
