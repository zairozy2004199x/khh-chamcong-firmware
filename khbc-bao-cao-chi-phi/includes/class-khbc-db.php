<?php
/**
 * BẢNG DỮ LIỆU — tiền tố `{prefix}khbc_`.
 *
 *   nguoi_dung  người dùng + PIN (băm) + vai (Admin · Kế toán · Nhân viên)
 *   phien       thẻ phiên đăng nhập (token 64 hex, sống 30 ngày)
 *   khoan       khoản chi phí theo kỳ (nhân viên nhập → kế toán duyệt)
 *   ky          cấu hình + số liệu còn lại của từng kỳ (JSON), có số phiên bản và cờ chốt kỳ
 *   nhat_ky     ai làm gì lúc nào
 *
 * 🔴 KHÔNG VIẾT CHÚ THÍCH BÊN TRONG CHUỖI `CREATE TABLE` — dbDelta() hiểu nhầm thành cột.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_DB {

	const SCHEMA_VERSION = '1.0.0';

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'khbc_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c   = $wpdb->get_charset_collate();
		$sql = array();

		$sql[] = "CREATE TABLE " . self::t( 'nguoi_dung' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ten VARCHAR(120) NOT NULL DEFAULT '',
			pin_hash VARCHAR(255) NOT NULL DEFAULT '',
			vai VARCHAR(40) NOT NULL DEFAULT 'Nhân viên',
			bo_phan VARCHAR(120) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			hoat_dong TINYINT(1) NOT NULL DEFAULT 1,
			tao_luc DATETIME NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY  (id),
			KEY ten (ten),
			KEY email (email)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'phien' ) . " (
			token CHAR(64) NOT NULL,
			user_id BIGINT(20) NOT NULL DEFAULT 0,
			ten VARCHAR(120) NOT NULL DEFAULT '',
			het_han DATETIME NOT NULL,
			PRIMARY KEY  (token),
			KEY het_han (het_han),
			KEY user_id (user_id)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'khoan' ) . " (
			id VARCHAR(40) NOT NULL,
			ky VARCHAR(7) NOT NULL DEFAULT '',
			trang_thai VARCHAR(20) NOT NULL DEFAULT 'cho_duyet',
			tao_luc DATETIME NULL,
			nguoi_tao VARCHAR(120) NOT NULL DEFAULT '',
			sua_luc DATETIME NULL,
			nguoi_sua VARCHAR(120) NOT NULL DEFAULT '',
			loai VARCHAR(20) NOT NULL DEFAULT 'company',
			ten TEXT NULL,
			misa_chung TEXT NULL,
			misa_chi_tiet TEXT NULL,
			tai_khoan VARCHAR(60) NOT NULL DEFAULT '',
			ma_doi_tuong VARCHAR(60) NOT NULL DEFAULT '',
			tong DECIMAL(18,2) NOT NULL DEFAULT 0,
			kieu_chia VARCHAR(40) NOT NULL DEFAULT 'equal',
			chia_nhom TEXT NULL,
			loai_tru TEXT NULL,
			gop_cot VARCHAR(60) NOT NULL DEFAULT '',
			phuong_phap VARCHAR(20) NOT NULL DEFAULT '',
			ghi_chu TEXT NULL,
			tep TEXT NULL,
			nguoi_duyet VARCHAR(120) NOT NULL DEFAULT '',
			duyet_luc DATETIME NULL,
			da_xoa TINYINT(1) NOT NULL DEFAULT 0,
			thu_tu BIGINT(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY ky (ky),
			KEY trang_thai (trang_thai),
			KEY da_xoa (da_xoa)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'ky' ) . " (
			ky VARCHAR(7) NOT NULL,
			phien_ban INT(11) NOT NULL DEFAULT 0,
			cap_nhat_luc DATETIME NULL,
			nguoi_cap_nhat VARCHAR(120) NOT NULL DEFAULT '',
			khoa TINYINT(1) NOT NULL DEFAULT 0,
			du_lieu LONGTEXT NULL,
			PRIMARY KEY  (ky)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'nhat_ky' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			luc DATETIME NULL,
			nguoi VARCHAR(120) NOT NULL DEFAULT '',
			vai VARCHAR(40) NOT NULL DEFAULT '',
			hanh_dong VARCHAR(60) NOT NULL DEFAULT '',
			chi_tiet TEXT NULL,
			PRIMARY KEY  (id),
			KEY luc (luc)
		) $c";

		foreach ( $sql as $q ) { dbDelta( $q ); }
		update_option( 'khbc_db_version', self::SCHEMA_VERSION );

		self::seed_admin();
	}

	/**
	 * Chưa có người dùng nào → tạo Admin với PIN mặc định 1111 (đổi ngay ở màn Tài khoản).
	 * Cùng cách khởi động với plugin Vận Hành Chi Phí để anh Thắng không phải học lại.
	 */
	public static function seed_admin() {
		global $wpdb;
		$t = self::t( 'nguoi_dung' );
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
		if ( $n > 0 ) { return; }
		$wpdb->insert( $t, array(
			'ten'       => 'Admin',
			'pin_hash'  => password_hash( '1111', PASSWORD_DEFAULT ),
			'vai'       => 'Admin',
			'hoat_dong' => 1,
			'tao_luc'   => KHBC_Util::now_sql(),
			'sua_luc'   => KHBC_Util::now_sql(),
		) );
	}

	public static function xoa_du_lieu() {
		global $wpdb;
		$n = 0;
		foreach ( array( 'khoan', 'ky', 'nhat_ky', 'phien' ) as $t ) {
			$n += (int) $wpdb->query( 'DELETE FROM ' . self::t( $t ) );
		}
		return $n;
	}
}
