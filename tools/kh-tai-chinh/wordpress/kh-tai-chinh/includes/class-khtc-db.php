<?php
/**
 * Bảng MySQL và việc nâng cấp cấu trúc.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_DB {

	/** Tăng số này mỗi lần đổi cấu trúc bảng thì bản đang chạy tự nâng cấp. */
	const SCHEMA = 4;

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
		$doi_soat  = self::bang( 'doi_soat' );
		$ds_dong   = self::bang( 'ds_dong' );
		$chi_phi   = self::bang( 'chi_phi' );
		$hd_ra     = self::bang( 'hd_ra' );

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

		// Một ĐỢT đối soát: kênh nào, về tài khoản nào, kỳ nào.
		dbDelta(
			"CREATE TABLE $doi_soat (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ten VARCHAR(190) NOT NULL DEFAULT '',
				kenh VARCHAR(20) NOT NULL DEFAULT 'khac',
				ngan_hang_id BIGINT UNSIGNED NOT NULL,
				tu DATE NOT NULL,
				den DATE NOT NULL,
				chay_luc DATETIME NULL,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY cty (cty)
			) $collate;"
		);

		// Từng dòng cổng thanh toán gửi về. khop_gd_id trỏ sang bảng giao dịch;
		// 0 nghĩa là chưa ghép được với dòng sao kê nào.
		dbDelta(
			"CREATE TABLE $ds_dong (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				dot_id BIGINT UNSIGNED NOT NULL,
				ngay DATE NOT NULL,
				ma_gd VARCHAR(120) NOT NULL DEFAULT '',
				so_tien BIGINT NOT NULL DEFAULT 0,
				phi BIGINT NOT NULL DEFAULT 0,
				dien_giai TEXT NULL,
				khop_gd_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				kieu_khop VARCHAR(20) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY dot_id (dot_id),
				KEY dot_tien (dot_id, so_tien),
				KEY ma_gd (ma_gd)
			) $collate;"
		);

		// Chi phí. Chỉ mục (cty, ngay) gánh mọi màn hình lọc theo kỳ; thêm
		// (cty, bo_phan) vì bảng cộng chéo và mọi báo cáo đều gom theo bộ phận.
		dbDelta(
			"CREATE TABLE $chi_phi (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ngay DATE NOT NULL,
				bo_phan VARCHAR(120) NOT NULL DEFAULT '',
				khoan_muc VARCHAR(120) NOT NULL DEFAULT '',
				nha_cung_cap VARCHAR(190) NOT NULL DEFAULT '',
				dien_giai TEXT NULL,
				so_tien BIGINT NOT NULL DEFAULT 0,
				so_ct VARCHAR(120) NOT NULL DEFAULT '',
				hinh_thuc VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan',
				ngan_hang_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				giao_dich_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				kieu_khop VARCHAR(20) NOT NULL DEFAULT '',
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY cty_ngay (cty, ngay),
				KEY cty_bo_phan (cty, bo_phan),
				KEY giao_dich_id (giao_dich_id)
			) $collate;"
		);

		// Hoá đơn đầu ra. Cột đặt theo đúng file Đối soát VAT đang dùng để dán
		// vào và xuất ra không phải sắp lại. UNIQUE (cty, so_hd): xuất trùng số
		// hoá đơn là sai luật, chặn ở tầng bảng thì không lệ thuộc màn hình nào.
		dbDelta(
			"CREATE TABLE $hd_ra (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ngay DATE NOT NULL,
				so_hd VARCHAR(60) NOT NULL DEFAULT '',
				khach VARCHAR(190) NOT NULL DEFAULT '',
				mst VARCHAR(30) NOT NULL DEFAULT '',
				dia_chi_kh TEXT NULL,
				email VARCHAR(190) NOT NULL DEFAULT '',
				noi_dung TEXT NULL,
				so_luong VARCHAR(30) NOT NULL DEFAULT '',
				dvt VARCHAR(30) NOT NULL DEFAULT '',
				thanh_tien BIGINT NOT NULL DEFAULT 0,
				chua_vat BIGINT NOT NULL DEFAULT 0,
				thue_suat VARCHAR(10) NOT NULL DEFAULT '0',
				vat BIGINT NOT NULL DEFAULT 0,
				co_vat BIGINT NOT NULL DEFAULT 0,
				khu_vuc VARCHAR(120) NOT NULL DEFAULT '',
				dich_vu VARCHAR(120) NOT NULL DEFAULT '',
				so_hop_dong VARCHAR(120) NOT NULL DEFAULT '',
				ma_diem VARCHAR(190) NOT NULL DEFAULT '',
				ma_misa VARCHAR(190) NOT NULL DEFAULT '',
				ghi_chu TEXT NULL,
				dia_chi TEXT NULL,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				UNIQUE KEY cty_so_hd (cty, so_hd),
				KEY cty_ngay (cty, ngay),
				KEY cty_thue_suat (cty, thue_suat)
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
