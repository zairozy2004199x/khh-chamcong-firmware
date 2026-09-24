<?php
/**
 * Bảng MySQL và việc nâng cấp cấu trúc.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_DB {

	/** Tăng số này mỗi lần đổi cấu trúc bảng thì bản đang chạy tự nâng cấp. */
	const SCHEMA = 11;

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
		$nhat_ky   = self::bang( 'nhat_ky' );
		$thanh_toan = self::bang( 'thanh_toan' );
		$hd_vao    = self::bang( 'hd_vao' );
		$hop_dong  = self::bang( 'hop_dong' );
		$ho_so     = self::bang( 'ho_so' );
		$diem      = self::bang( 'diem' );

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
				ma_cua_hang VARCHAR(80) NOT NULL DEFAULT '',
				hd_ra_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY cty_ngay (cty, ngay),
				KEY ngan_hang_id (ngan_hang_id),
				KEY ma_gd (ma_gd),
				KEY ma_cua_hang (ma_cua_hang),
				KEY hd_ra_id (hd_ra_id)
			) $collate;"
		);

		// Danh mục điểm: cầu nối giữa MÃ CỬA HÀNG trong sao kê và ĐIỂM XUẤT
		// HOÁ ĐƠN. Không có bảng này thì một dòng QR về ngân hàng chỉ là một
		// khoản tiền không tên, không biết thuộc gian nào, không gom được thành
		// hoá đơn. Đây là thứ duy nhất nối nửa đầu với nửa sau của cả quy trình.
		//
		// ma_cua_hang duy nhất trong mỗi pháp nhân: một mã trỏ hai điểm thì
		// doanh thu chia sai mà không có gì báo, nên chặn ở tầng bảng.
		dbDelta(
			"CREATE TABLE $diem (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ma_cua_hang VARCHAR(80) NOT NULL,
				ma_diem_ban VARCHAR(80) NOT NULL DEFAULT '',
				ten_gian VARCHAR(190) NOT NULL DEFAULT '',
				ten_diem VARCHAR(190) NOT NULL DEFAULT '',
				ma_misa VARCHAR(120) NOT NULL DEFAULT '',
				khu_vuc VARCHAR(60) NOT NULL DEFAULT '',
				dich_vu VARCHAR(60) NOT NULL DEFAULT '',
				so_tk VARCHAR(60) NOT NULL DEFAULT '',
				bo_qua TINYINT NOT NULL DEFAULT 0,
				ghi_chu TEXT NULL,
				tao_luc DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY cty_ma (cty, ma_cua_hang),
				KEY cty_diem (cty, ten_diem)
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
				ma_cua_hang VARCHAR(80) NOT NULL DEFAULT '',
				hd_ra_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (id),
				KEY dot_id (dot_id),
				KEY dot_tien (dot_id, so_tien),
				KEY ma_gd (ma_gd),
				KEY hd_ra_id (hd_ra_id)
			) $collate;"
		);

		// Chi phí. Chỉ mục (cty, ngay) gánh mọi màn hình lọc theo kỳ; thêm
		// (cty, bo_phan) vì bảng cộng chéo và mọi báo cáo đều gom theo bộ phận.
		dbDelta(
			"CREATE TABLE $chi_phi (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ngay DATE NOT NULL,
				han_tt DATE NULL,
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
				han_tt DATE NULL,
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

		// Nhật ký thay đổi. du_lieu giữ nguyên văn bản ghi lúc bị xoá nên phải
		// LONGTEXT — một hoá đơn đủ 22 trường vượt TEXT là chuyện có thật.
		dbDelta(
			"CREATE TABLE $nhat_ky (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				luc DATETIME NOT NULL,
				ai VARCHAR(120) NOT NULL DEFAULT '',
				viec VARCHAR(20) NOT NULL DEFAULT '',
				bang VARCHAR(30) NOT NULL DEFAULT '',
				ban_ghi_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				tom_tat TEXT NULL,
				du_lieu LONGTEXT NULL,
				PRIMARY KEY (id),
				KEY cty_luc (cty, luc),
				KEY cty_viec (cty, viec)
			) $collate;"
		);

		// Sổ thanh toán — nguồn sự thật DUY NHẤT cho "đã trả bao nhiêu". Một
		// chứng từ có thể có nhiều dòng (trả làm nhiều đợt); một dòng sao kê có
		// thể sinh ra nhiều dòng (một lần chuyển trả nhiều hoá đơn). Vì vậy nó
		// là bảng riêng chứ không phải một cột trên chứng từ.
		dbDelta(
			"CREATE TABLE $thanh_toan (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				bang VARCHAR(20) NOT NULL DEFAULT '',
				chung_tu_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				giao_dich_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				ngay DATE NOT NULL,
				so_tien BIGINT NOT NULL DEFAULT 0,
				ghi_chu TEXT NULL,
				tu_dong TINYINT NOT NULL DEFAULT 0,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY chung_tu (bang, chung_tu_id),
				KEY cty_ngay (cty, ngay),
				KEY giao_dich_id (giao_dich_id)
			) $collate;"
		);

		// Hoá đơn đầu vào. UNIQUE theo CẶP (cty, so_hd, mst): số hoá đơn do bên
		// bán đánh nên hai nhà cung cấp cùng phát hành số 00000001 là bình
		// thường — chặn theo mình số hoá đơn thì nhà cung cấp thứ hai không
		// nhập được hoá đơn hợp lệ của họ.
		dbDelta(
			"CREATE TABLE $hd_vao (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				ngay DATE NOT NULL,
				so_hd VARCHAR(60) NOT NULL DEFAULT '',
				nha_cung_cap VARCHAR(190) NOT NULL DEFAULT '',
				mst VARCHAR(30) NOT NULL DEFAULT '',
				dia_chi TEXT NULL,
				noi_dung TEXT NULL,
				chua_vat BIGINT NOT NULL DEFAULT 0,
				thue_suat VARCHAR(10) NOT NULL DEFAULT '0',
				vat BIGINT NOT NULL DEFAULT 0,
				co_vat BIGINT NOT NULL DEFAULT 0,
				hinh_thuc VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan',
				khau_tru TINYINT NOT NULL DEFAULT 1,
				ly_do TEXT NULL,
				chi_phi_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				ghi_chu TEXT NULL,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				UNIQUE KEY cty_so_mst (cty, so_hd, mst),
				KEY cty_ngay (cty, ngay),
				KEY cty_khau_tru (cty, khau_tru)
			) $collate;"
		);

		// Sổ hợp đồng — dùng chung cho thuê gian hàng và nhà cung cấp, phân biệt
		// bằng cột `loai`. Hai loại khác nhau vài cột nhưng giống nhau ở phần
		// cốt lõi (đối tác, MST, ngày hết hạn, bản scan), và mọi việc dùng chung
		// — cảnh báo sắp hết hạn, đếm hợp đồng thiếu dấu — chỉ phải viết một lần.
		//
		// ngay_het_han là cột NGÀY thật, khác bản gốc lưu thời hạn thành chữ tự
		// do: chỉ khi nó là ngày thì máy mới trả lời được "hợp đồng nào sắp hết".
		dbDelta(
			"CREATE TABLE $hop_dong (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				loai VARCHAR(10) NOT NULL DEFAULT 'thue',
				doi_tac VARCHAR(190) NOT NULL DEFAULT '',
				mst VARCHAR(30) NOT NULL DEFAULT '',
				dai_dien VARCHAR(190) NOT NULL DEFAULT '',
				chuc_vu VARCHAR(120) NOT NULL DEFAULT '',
				so_hd VARCHAR(120) NOT NULL DEFAULT '',
				ngay_ky DATE NULL,
				ngay_bat_dau DATE NULL,
				ngay_het_han DATE NULL,
				gia_tri BIGINT NOT NULL DEFAULT 0,
				noi_dung TEXT NULL,
				gian VARCHAR(190) NOT NULL DEFAULT '',
				ma_diem VARCHAR(190) NOT NULL DEFAULT '',
				ma_misa VARCHAR(190) NOT NULL DEFAULT '',
				khu_vuc VARCHAR(120) NOT NULL DEFAULT '',
				hinh_thuc VARCHAR(120) NOT NULL DEFAULT '',
				trang_thai VARCHAR(20) NOT NULL DEFAULT 'hoat_dong',
				loai_chia_se VARCHAR(20) NOT NULL DEFAULT '',
				phan_tram DECIMAL(6,2) NOT NULL DEFAULT 0,
				mien TINYINT NOT NULL DEFAULT 0,
				link_chua_dau TEXT NULL,
				link_du_dau TEXT NULL,
				ghi_chu TEXT NULL,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY cty_loai (cty, loai),
				KEY cty_het_han (cty, ngay_het_han),
				KEY ma_diem (ma_diem)
			) $collate;"
		);

		// Sổ lưu chứng từ. gan_bang + gan_id trỏ sang bút toán trong một sổ
		// khác; chỉ mục (gan_bang, gan_id) gánh câu hỏi ngược "bút toán này đã
		// có chứng từ chưa", vốn là một LEFT JOIN chạy trên cả kỳ.
		dbDelta(
			"CREATE TABLE $ho_so (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cty VARCHAR(20) NOT NULL DEFAULT 'kh_cu',
				loai VARCHAR(20) NOT NULL DEFAULT 'khac',
				so_ct VARCHAR(120) NOT NULL DEFAULT '',
				ngay DATE NOT NULL,
				doi_tac VARCHAR(190) NOT NULL DEFAULT '',
				so_tien BIGINT NOT NULL DEFAULT 0,
				ten_file VARCHAR(190) NOT NULL DEFAULT '',
				link_file TEXT NULL,
				gan_bang VARCHAR(20) NOT NULL DEFAULT '',
				gan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				da_hach_toan TINYINT NOT NULL DEFAULT 0,
				ghi_chu TEXT NULL,
				tao_luc DATETIME NOT NULL,
				tao_boi VARCHAR(120) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				KEY cty_ngay (cty, ngay),
				KEY cty_loai (cty, loai),
				KEY gan (gan_bang, gan_id),
				KEY so_ct (so_ct)
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
