<?php
/**
 * BẢNG DỮ LIỆU — tiền tố `{prefix}khunc_`.
 *
 *   nguoi_dung  người dùng + PIN (băm) + vai (Admin · Kế toán · Xem)
 *   phien       thẻ phiên đăng nhập (token 64 hex, sống 30 ngày)
 *   theo_doi    TRẠNG THÁI KẾ TOÁN ĐÁNH DẤU cho từng khoản — thứ quý nhất ở đây
 *   kho         các khối JSON lớn: danh sách đọc từ Excel, nhật ký nhập, thông tin công ty
 *   nhat_ky     ai làm gì lúc nào
 *
 * VÌ SAO TÁCH `theo_doi` RA BẢNG RIÊNG mà `recs` thì để nguyên khối JSON trong `kho`:
 * danh sách khoản là ẢNH CHỤP của file Excel — nhập file mới là thay cả khối, không ai sửa lẻ
 * từng dòng, và giao diện lọc/tính toàn bộ ở máy khách nên máy chủ không cần truy vấn vào trong.
 * Còn trạng thái "đã đi tiền" là dữ liệu NGƯỜI TA GÕ VÀO, mất là mất hẳn: phải là bản ghi riêng,
 * ghi từng dòng một, không bị đè khi hai người cùng đánh dấu, và nhập lại Excel KHÔNG đụng tới.
 *
 * 🔴 KHÔNG VIẾT CHÚ THÍCH BÊN TRONG CHUỖI `CREATE TABLE` — dbDelta() hiểu nhầm thành cột.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_DB {

	const SCHEMA_VERSION = '1.0.0';

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'khunc_' . $name;
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
			vai VARCHAR(40) NOT NULL DEFAULT 'Xem',
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

		$sql[] = "CREATE TABLE " . self::t( 'theo_doi' ) . " (
			id VARCHAR(48) NOT NULL,
			trang_thai VARCHAR(20) NOT NULL DEFAULT '',
			ngay_di DATE NULL,
			so_unc VARCHAR(60) NOT NULL DEFAULT '',
			nguoi_cap_nhat VARCHAR(120) NOT NULL DEFAULT '',
			cap_nhat_luc DATETIME NULL,
			ghi_chu TEXT NULL,
			PRIMARY KEY  (id),
			KEY trang_thai (trang_thai),
			KEY ngay_di (ngay_di)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'kho' ) . " (
			khoa VARCHAR(40) NOT NULL,
			phien_ban INT(11) NOT NULL DEFAULT 0,
			cap_nhat_luc DATETIME NULL,
			nguoi_cap_nhat VARCHAR(120) NOT NULL DEFAULT '',
			du_lieu LONGTEXT NULL,
			PRIMARY KEY  (khoa)
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
		update_option( 'khunc_db_version', self::SCHEMA_VERSION );

		self::seed_admin();
	}

	/**
	 * Chưa có người dùng nào → tạo Admin với PIN mặc định 1111 (đổi ngay ở hộp Tài khoản).
	 * Cùng cách khởi động với plugin Báo Cáo Chi Phí để anh Thắng không phải học lại.
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
			'tao_luc'   => KHUNC_Util::now_sql(),
			'sua_luc'   => KHUNC_Util::now_sql(),
		) );
	}

	/**
	 * Xoá số liệu, GIỮ người dùng.
	 * `theo_doi` chỉ xoá khi gọi rõ — xoá nhầm là mất hết công đánh dấu, không lấy lại được.
	 */
	public static function xoa_du_lieu( $ca_theo_doi = false ) {
		global $wpdb;
		$n = (int) $wpdb->query( 'DELETE FROM ' . self::t( 'kho' ) );
		if ( $ca_theo_doi ) {
			$n += (int) $wpdb->query( 'DELETE FROM ' . self::t( 'theo_doi' ) );
		}
		return $n;
	}
}
