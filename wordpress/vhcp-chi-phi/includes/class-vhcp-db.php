<?php
/**
 * Schema MySQL — mỗi sheet của app cũ thành 1 bảng.
 *
 *   DonHang   -> vhcp_don            TamUng  -> vhcp_tamung        ChiPhi  -> vhcp_chiphi
 *   DA_Index  -> vhcp_da_index       <sheet dự án> -> vhcp_da_line (khóa theo ma_da + row_no)
 *   MK_Don    -> vhcp_mk_don         MK_Line -> vhcp_mk_line
 *   BP_Index  -> vhcp_bp_index       <sheet đợt>   -> vhcp_bp_line (khóa theo ma + row_no)
 *   CH_*      -> vhcp_cfg (bảng dùng chung, mỗi dòng là 1 hàng sheet dạng JSON)
 *   NhatKy    -> vhcp_log
 *   Document/Script Properties -> vhcp_meta
 *
 * row_no giữ nguyên cách đánh số hàng của Sheet (dữ liệu bắt đầu ở hàng 5) vì giao diện
 * vẫn gọi updateDuAnLine(maDA, row, rec) theo số hàng. Khác Sheet ở một điểm CÓ LỢI:
 * xóa 1 dòng KHÔNG dồn số hàng của dòng khác, nên không có chuyện sửa nhầm dòng.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_DB {

	const SCHEMA_VERSION = '1.3.0';
	const DATA_ROW       = 5;   // DA_DATA_ROW / BP_DATA_ROW của app cũ

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'vhcp_' . $name;
	}

	/**
	 * XÓA SẠCH DỮ LIỆU NGHIỆP VỤ, GIỮ CẤU HÌNH.
	 *
	 * Dùng khi nạp dữ liệu cũ bị sai và muốn làm lại từ đầu: xóa đơn, dòng chi, sổ chi
	 * phí, dự án, marketing, công tác/setup, nhật ký — nhưng GIỮ cấu hình, người dùng,
	 * danh mục loại chi phí và ma trận mã, vì đó là phần khai tay mất công nhất.
	 *
	 * @return array [bảng => số dòng đã xóa]
	 */
	public static function xoa_du_lieu() {
		global $wpdb;
		$bang = array( 'don', 'tamung', 'chiphi', 'so_chi', 'da_index', 'da_line',
			'mk_don', 'mk_line', 'bp_index', 'bp_line', 'log' );
		$out = array();
		foreach ( $bang as $b ) {
			$t = self::t( $b );
			$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
			$wpdb->query( "DELETE FROM $t" );
			if ( $n ) { $out[ $b ] = $n; }
		}
		// meta của dự án (ngày duyệt, ghi nhận chi tiền) đi kèm dữ liệu nên xóa luôn
		foreach ( array( 'daApp_', 'daPay_' ) as $p ) {
			foreach ( VHCP_Meta::get_prefix( $p ) as $k => $v ) { VHCP_Meta::del( $k ); }
		}
		VHCP_Cfg::clear_cache();
		return $out;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE " . self::t( 'don' ) . " (
			ma_don VARCHAR(40) NOT NULL,
			ky VARCHAR(120) NOT NULL DEFAULT '',
			nguoi_lap VARCHAR(120) NOT NULL DEFAULT '',
			ngay_tao DATETIME NULL,
			trang_thai VARCHAR(40) NOT NULL DEFAULT 'Nháp',
			ghi_chu TEXT NULL,
			nguoi_duyet VARCHAR(120) NOT NULL DEFAULT '',
			ngay_duyet DATETIME NULL,
			nguoi_qt VARCHAR(120) NOT NULL DEFAULT '',
			ngay_qt DATETIME NULL,
			chenh_lech_qt DECIMAL(18,2) NOT NULL DEFAULT 0,
			xu_ly VARCHAR(60) NOT NULL DEFAULT '',
			so_tien_thuc_mua DECIMAL(18,2) NULL,
			hinh_thuc_tt VARCHAR(60) NOT NULL DEFAULT '',
			hoa_don_qt TEXT NULL,
			ngay_xuat_cn DATETIME NULL,
			nguoi_qt_ncc VARCHAR(120) NOT NULL DEFAULT '',
			ngay_qt_ncc DATETIME NULL,
			ngay_xuat_ncc DATETIME NULL,
			tam_ung_duyet DECIMAL(18,2) NULL,
			nguoi_cap VARCHAR(120) NOT NULL DEFAULT '',
			ngay_cap DATETIME NULL,
			ht_cap VARCHAR(60) NOT NULL DEFAULT '',
			anh_cap TEXT NULL,
			tat_toan VARCHAR(120) NOT NULL DEFAULT '',
			ngay_tat_toan DATETIME NULL,
			du_phong DECIMAL(18,2) NULL,
			bu_tru DECIMAL(18,2) NULL,
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (ma_don),
			UNIQUE KEY stt (stt),
			KEY trang_thai (trang_thai),
			KEY ky (ky)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'tamung' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_don VARCHAR(40) NOT NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			so DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY don_coso (ma_don,coso)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'chiphi' ) . " (
			id VARCHAR(40) NOT NULL,
			ma_don VARCHAR(40) NOT NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NULL,
			phan_loai_tt VARCHAR(60) NOT NULL DEFAULT '',
			doi_tuong VARCHAR(190) NOT NULL DEFAULT '',
			nhom VARCHAR(190) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			dvt VARCHAR(60) NOT NULL DEFAULT '',
			so_luong DECIMAL(18,3) NULL,
			don_gia DECIMAL(18,2) NULL,
			thanh_tien DECIMAL(18,2) NOT NULL DEFAULT 0,
			ghi_chu TEXT NULL,
			anh TEXT NULL,
			tao_luc DATETIME NULL,
			thue_suat DECIMAL(8,2) NULL,
			tien_thue DECIMAL(18,2) NULL,
			thuc_mua DECIMAL(18,2) NULL,
			cn_xu_ly TINYINT(1) NOT NULL DEFAULT 1,
			phat_sinh TINYINT(1) NOT NULL DEFAULT 0,
			tk_no VARCHAR(20) NOT NULL DEFAULT '',
			tk_co VARCHAR(20) NOT NULL DEFAULT '',
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (id),
			UNIQUE KEY stt (stt),
			KEY ma_don (ma_don),
			KEY coso (coso),
			KEY ngay (ngay)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'so_chi' ) . " (
			id VARCHAR(40) NOT NULL,
			ngay DATE NULL,
			ky VARCHAR(60) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			loai VARCHAR(190) NOT NULL DEFAULT '',
			tk_no VARCHAR(20) NOT NULL DEFAULT '',
			tk_co VARCHAR(20) NOT NULL DEFAULT '',
			ma_dt VARCHAR(60) NOT NULL DEFAULT '',
			doi_tuong VARCHAR(190) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			dvt VARCHAR(60) NOT NULL DEFAULT '',
			so_luong DECIMAL(18,3) NULL,
			don_gia DECIMAL(18,2) NULL,
			so_tien DECIMAL(18,2) NOT NULL DEFAULT 0,
			hinh_thuc VARCHAR(60) NOT NULL DEFAULT '',
			vat VARCHAR(60) NOT NULL DEFAULT '',
			thue_suat DECIMAL(8,2) NULL,
			tien_thue DECIMAL(18,2) NULL,
			ghi_chu TEXT NULL,
			anh TEXT NULL,
			ma_du_an VARCHAR(60) NOT NULL DEFAULT '',
			hang_muc VARCHAR(190) NOT NULL DEFAULT '',
			du_toan DECIMAL(18,2) NULL,
			ho_so TEXT NULL,
			nguoi_nhap VARCHAR(120) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			ngay_xuat DATETIME NULL,
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (id),
			UNIQUE KEY stt (stt),
			KEY ngay (ngay),
			KEY coso (coso),
			KEY loai (loai),
			KEY tk_no (tk_no),
			KEY ma_du_an (ma_du_an),
			KEY ngay_xuat (ngay_xuat)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'da_index' ) . " (
			ma_da VARCHAR(40) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			loai VARCHAR(60) NOT NULL DEFAULT '',
			trang_thai VARCHAR(60) NOT NULL DEFAULT 'Đang làm',
			ngay_tao DATETIME NULL,
			nguoi_tao VARCHAR(120) NOT NULL DEFAULT '',
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (ma_da),
			UNIQUE KEY stt (stt),
			KEY loai (loai)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'da_line' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_da VARCHAR(40) NOT NULL,
			row_no INT(11) NOT NULL DEFAULT 5,
			noi_dung TEXT NULL,
			du_toan DECIMAL(18,2) NOT NULL DEFAULT 0,
			thuc_te DECIMAL(18,2) NOT NULL DEFAULT 0,
			so_luong DECIMAL(18,3) NOT NULL DEFAULT 0,
			don_gia DECIMAL(18,2) NOT NULL DEFAULT 0,
			thanh_tien DECIMAL(18,2) NOT NULL DEFAULT 0,
			vat VARCHAR(60) NOT NULL DEFAULT '',
			anh TEXT NULL,
			gian VARCHAR(190) NOT NULL DEFAULT '',
			note TEXT NULL,
			cap_cha VARCHAR(190) NOT NULL DEFAULT '',
			hinh_thuc VARCHAR(60) NOT NULL DEFAULT '',
			ho_so TEXT NULL,
			loai_cp VARCHAR(190) NOT NULL DEFAULT '',
			tk_no VARCHAR(20) NOT NULL DEFAULT '',
			tk_co VARCHAR(20) NOT NULL DEFAULT '',
			ma_dt VARCHAR(60) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY da_row (ma_da,row_no),
			KEY tk_no (tk_no)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'mk_don' ) . " (
			ma VARCHAR(40) NOT NULL,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ten VARCHAR(190) NOT NULL DEFAULT '',
			ky VARCHAR(60) NOT NULL DEFAULT '',
			kenh VARCHAR(190) NOT NULL DEFAULT '',
			trang_thai VARCHAR(40) NOT NULL DEFAULT 'Đang chạy',
			ngay_tao VARCHAR(40) NOT NULL DEFAULT '',
			nguoi_tao VARCHAR(120) NOT NULL DEFAULT '',
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (ma),
			UNIQUE KEY stt (stt),
			KEY coso (coso)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'mk_line' ) . " (
			id VARCHAR(40) NOT NULL,
			ma_don VARCHAR(40) NOT NULL,
			kenh VARCHAR(190) NOT NULL DEFAULT '',
			noi_dung TEXT NULL,
			du_toan DECIMAL(18,2) NOT NULL DEFAULT 0,
			thuc_te DECIMAL(18,2) NOT NULL DEFAULT 0,
			hinh_thuc VARCHAR(60) NOT NULL DEFAULT '',
			vat VARCHAR(60) NOT NULL DEFAULT '',
			ket_qua DECIMAL(18,2) NOT NULL DEFAULT 0,
			ngay VARCHAR(40) NOT NULL DEFAULT '',
			note TEXT NULL,
			ho_so TEXT NULL,
			loai_cp VARCHAR(190) NOT NULL DEFAULT '',
			tk_no VARCHAR(20) NOT NULL DEFAULT '',
			tk_co VARCHAR(20) NOT NULL DEFAULT '',
			ma_dt VARCHAR(60) NOT NULL DEFAULT '',
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (id),
			UNIQUE KEY stt (stt),
			KEY ma_don (ma_don),
			KEY tk_no (tk_no)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'bp_index' ) . " (
			ma VARCHAR(40) NOT NULL,
			loai VARCHAR(40) NOT NULL DEFAULT '',
			ten VARCHAR(190) NOT NULL DEFAULT '',
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			dia_diem VARCHAR(190) NOT NULL DEFAULT '',
			ky VARCHAR(60) NOT NULL DEFAULT '',
			trang_thai VARCHAR(40) NOT NULL DEFAULT 'Đang xử lý',
			ngay_tao VARCHAR(40) NOT NULL DEFAULT '',
			nguoi_tao VARCHAR(120) NOT NULL DEFAULT '',
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY  (ma),
			UNIQUE KEY stt (stt),
			KEY loai (loai)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'bp_line' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma VARCHAR(40) NOT NULL,
			row_no INT(11) NOT NULL DEFAULT 5,
			noi_dung TEXT NULL,
			so_luong DECIMAL(18,3) NOT NULL DEFAULT 0,
			don_gia DECIMAL(18,2) NOT NULL DEFAULT 0,
			thanh_tien DECIMAL(18,2) NOT NULL DEFAULT 0,
			du_toan DECIMAL(18,2) NOT NULL DEFAULT 0,
			thuc_te DECIMAL(18,2) NOT NULL DEFAULT 0,
			hinh_thuc VARCHAR(60) NOT NULL DEFAULT '',
			vat VARCHAR(60) NOT NULL DEFAULT '',
			ngay VARCHAR(40) NOT NULL DEFAULT '',
			note TEXT NULL,
			ho_so TEXT NULL,
			loai_cp VARCHAR(190) NOT NULL DEFAULT '',
			tk_no VARCHAR(20) NOT NULL DEFAULT '',
			tk_co VARCHAR(20) NOT NULL DEFAULT '',
			ma_dt VARCHAR(60) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY bp_row (ma,row_no),
			KEY tk_no (tk_no)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'cfg' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			bang VARCHAR(40) NOT NULL,
			stt INT(11) NOT NULL DEFAULT 0,
			cols LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY bang_stt (bang,stt)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'meta' ) . " (
			k VARCHAR(190) NOT NULL,
			v LONGTEXT NULL,
			PRIMARY KEY  (k)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'log' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			tg DATETIME NULL,
			nguoi VARCHAR(120) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			hanh_dong VARCHAR(190) NOT NULL DEFAULT '',
			doi_tuong VARCHAR(190) NOT NULL DEFAULT '',
			chi_tiet TEXT NULL,
			PRIMARY KEY  (id),
			KEY tg (tg)
		) $c";

		$sql[] = "CREATE TABLE " . self::t( 'session' ) . " (
			token CHAR(64) NOT NULL,
			ten VARCHAR(120) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			coso TEXT NULL,
			bo_phan VARCHAR(60) NOT NULL DEFAULT '',
			het_han DATETIME NULL,
			PRIMARY KEY  (token),
			KEY het_han (het_han)
		) $c";

		foreach ( $sql as $q ) { dbDelta( $q ); }

		update_option( 'vhcp_db_version', self::SCHEMA_VERSION );
		update_option( 'vhcp_flush_rewrite', 1 );   // để VHCP_App::init nạp lại đường dẫn /chi-phi/
		VHCP_Cfg::seed();
	}

	/** SELECT trả mảng kết hợp. */
	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}

	public static function row( $sql ) {
		global $wpdb;
		$r = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $r ) ? $r : null;
	}
}
