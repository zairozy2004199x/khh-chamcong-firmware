<?php
/**
 * SƠ ĐỒ BẢNG — plugin Vận Hành.
 *
 * ⚠️ CHÚ THÍCH ĐỂ NGOÀI THÂN `CREATE TABLE`, không để trong chuỗi. Hai lý do đã cắn một lần ở
 *    plugin chấm công: (1) thân bảng nằm trong chuỗi nháy kép của PHP, chú thích có nháy kép là
 *    chuỗi đóng sớm và cả tệp không dịch được; (2) phép thử đếm cột đọc thẳng chuỗi này.
 *
 * 🔴 KHÔNG DỰNG LẠI NHỮNG BẢNG PLUGIN CHẤM CÔNG ĐÃ CÓ. Sổ nhân sự, phân quyền, phiên đăng nhập,
 *    chấm công, xin đi muộn, lịch công việc — `vhcp-cham-cong` giữ hết. Dựng bản thứ hai là hai
 *    sổ nhân sự song song: nghỉ việc một người phải nhớ xoá hai nơi, quên một nơi là người đã
 *    nghỉ vẫn đăng nhập được. Plugin này CHỈ thêm những thứ chấm công chưa có.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_DB {

	/** Đổi số này khi sơ đồ đổi — plugin so với `vhvh_so_do` rồi tự chạy dbDelta. */
	const SO_DO = '1.0.0';

	public static function tien_to() {
		global $wpdb;
		return $wpdb->prefix . 'vhvh_';
	}

	public static function t( $ten ) {
		return self::tien_to() . $ten;
	}

	/**
	 * Toàn bộ sơ đồ, một chỗ. Trả về tên-bảng => thân CREATE TABLE.
	 * Để đây (chứ không rải trong phần cài) vì phép thử phải soi được sơ đồ mà không cần MySQL.
	 */
	public static function bang() {
		$b = array();

		/* ===== DOANH THU & CHI PHÍ THEO NGÀY ==================================================
		   Một cơ sở một ngày đúng MỘT bản ghi → khoá duy nhất (coso, ngay). Thiếu khoá này thì
		   hai người cùng nộp báo cáo cuối ca là sổ có hai dòng cho một ngày, và mọi phép cộng
		   doanh thu từ đó trở đi đều gấp đôi mà không ai thấy sai ở đâu.

		   `ve`, `tra_tien`, `khach_gio`, `chi` để JSON: số loại vé thay đổi theo mùa (vé lễ, vé
		   ưu đãi tân sinh viên…), mỗi lần thêm một loại mà phải thêm một cột là mỗi lần phải sửa
		   plugin rồi chờ cài lại lên host. Danh mục vé nằm ở `danh_muc`, người dùng tự thêm.

		   `tong_thu` và `khach` là số ĐÃ TÍNH SẴN, cố ý lặp lại thứ nằm trong JSON: báo cáo tháng
		   phải cộng được bằng SQL. Cộng bằng cách đọc JSON ra PHP thì một tháng sáu cơ sở là sáu
		   trăm lượt giải mã JSON cho một con số. */
		$b['doanh_thu'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			ve LONGTEXT NULL,
			tra_tien LONGTEXT NULL,
			khach_gio LONGTEXT NULL,
			chi LONGTEXT NULL,
			thu_khac LONGTEXT NULL,
			tong_thu BIGINT(20) NOT NULL DEFAULT 0,
			tong_chi BIGINT(20) NOT NULL DEFAULT 0,
			khach INT(11) NOT NULL DEFAULT 0,
			ghi TEXT NULL,
			tt VARCHAR(20) NOT NULL DEFAULT 'cho',
			nguoi_nop VARCHAR(190) NOT NULL DEFAULT '',
			nguoi_duyet VARCHAR(190) NOT NULL DEFAULT '',
			duyet_luc DATETIME NULL,
			tao DATETIME NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY coso_ngay (coso,ngay),
			KEY ngay (ngay),
			KEY tt (tt)";

		/* ===== SỰ CỐ ==========================================================================
		   `han` là hạn xử lý. Để DATE chứ không DATETIME: người giao việc nghĩ theo ngày, và một
		   hạn 17:30 thì đến 17:31 đã đỏ trong khi người ta vẫn đang làm. */
		$b['su_co'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			tieu_de VARCHAR(255) NOT NULL DEFAULT '',
			mo_ta TEXT NULL,
			muc VARCHAR(20) NOT NULL DEFAULT 'canh_bao',
			tt VARCHAR(20) NOT NULL DEFAULT 'mo',
			bao_ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			bao_ten VARCHAR(190) NOT NULL DEFAULT '',
			giao_ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			giao_ten VARCHAR(190) NOT NULL DEFAULT '',
			han DATE NULL,
			dong_luc DATETIME NULL,
			dong_boi VARCHAR(190) NOT NULL DEFAULT '',
			tao DATETIME NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			KEY coso_tt (coso,tt),
			KEY han (han)";

		/* ===== CHECKLIST ĐẦU/CUỐI NGÀY ========================================================
		   Một cơ sở · một ngày · một buổi (đầu/cuối) đúng một bản ghi. `muc` giữ JSON trạng thái
		   từng mục theo khoá, để danh mục đổi mà bản ghi cũ vẫn đọc được. */
		$b['checklist'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			buoi VARCHAR(20) NOT NULL DEFAULT 'dau_ngay',
			muc LONGTEXT NULL,
			xong INT(11) NOT NULL DEFAULT 0,
			tong INT(11) NOT NULL DEFAULT 0,
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			tao DATETIME NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY coso_ngay_buoi (coso,ngay,buoi),
			KEY ngay (ngay)";

		/* ===== KIỂM KHO ======================================================================= */
		$b['kho'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			tuan_tu DATE NOT NULL,
			muc LONGTEXT NULL,
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			tao DATETIME NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY coso_tuan (coso,tuan_tu)";

		/* ===== VI PHẠM / KHEN (đánh giá nhân viên) ============================================
		   `lan_thu` là lần vi phạm thứ mấy TRONG KỲ của đúng người ấy với đúng lỗi ấy — mức phạt
		   theo bảng phạt tăng dần theo lần 1/2/3. Tính sẵn lúc ghi chứ không tính lúc đọc: đọc
		   mà tính thì xoá một bản ghi cũ là mọi bản sau nó đổi mức phạt, trong khi biên bản đã ký. */
		$b['danh_gia'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ten VARCHAR(190) NOT NULL DEFAULT '',
			ky VARCHAR(7) NOT NULL DEFAULT '',
			loai VARCHAR(20) NOT NULL DEFAULT 'vi_pham',
			khoa VARCHAR(60) NOT NULL DEFAULT '',
			nhan VARCHAR(255) NOT NULL DEFAULT '',
			nhom VARCHAR(60) NOT NULL DEFAULT '',
			muc VARCHAR(20) NOT NULL DEFAULT 'nhe',
			lan_thu INT(11) NOT NULL DEFAULT 1,
			diem INT(11) NOT NULL DEFAULT 0,
			phat VARCHAR(255) NOT NULL DEFAULT '',
			ghi TEXT NULL,
			nguoi_ghi VARCHAR(190) NOT NULL DEFAULT '',
			tao DATETIME NULL,
			PRIMARY KEY  (id),
			KEY nv_ky (ma_nv,ky),
			KEY coso_ky (coso,ky)";

		/* ===== TIKTOK ========================================================================= */
		$b['tiktok'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ten VARCHAR(190) NOT NULL DEFAULT '',
			kenh VARCHAR(190) NOT NULL DEFAULT '',
			duong_dan TEXT NULL,
			ngay_dang DATE NULL,
			luot_xem BIGINT(20) NULL,
			tien BIGINT(20) NOT NULL DEFAULT 0,
			chot_luc DATETIME NULL,
			tao DATETIME NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			KEY coso_ngay (coso,ngay_dang)";

		/* ===== TRÍCH CAM ====================================================================== */
		$b['trich_cam'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ten VARCHAR(190) NOT NULL DEFAULT '',
			so_hd VARCHAR(60) NOT NULL DEFAULT '',
			sdt VARCHAR(30) NOT NULL DEFAULT '',
			ngay_dk DATE NULL,
			xong TINYINT(1) NOT NULL DEFAULT 0,
			xong_luc DATETIME NULL,
			tao DATETIME NULL,
			PRIMARY KEY  (id),
			KEY coso_ngay (coso,ngay_dk),
			KEY so_hd (so_hd)";

		/* ===== THÔNG BÁO ====================================================================== */
		$b['thong_bao'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(190) NOT NULL DEFAULT '',
			tieu_de VARCHAR(255) NOT NULL DEFAULT '',
			than TEXT NULL,
			nguoi VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			tao DATETIME NULL,
			PRIMARY KEY  (id),
			KEY tao (tao)";

		/* ===== DANH MỤC (checklist, kho, vi phạm, loại vé) =====================================
		   Một bảng cho mọi danh mục thay vì bốn bảng gần giống nhau. `pham_vi` cho phép mỗi cơ sở
		   tự có danh mục riêng: để rỗng là dùng chung toàn hệ thống. */
		$b['danh_muc'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			loai VARCHAR(40) NOT NULL DEFAULT '',
			pham_vi VARCHAR(190) NOT NULL DEFAULT '',
			noi_dung LONGTEXT NULL,
			sua DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY loai_pham_vi (loai,pham_vi)";

		return $b;
	}

	/** Chạy dbDelta cho mọi bảng. Gọi lúc kích hoạt và mỗi khi SO_DO đổi. */
	public static function cai_dat() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		foreach ( self::bang() as $ten => $cot ) {
			dbDelta( 'CREATE TABLE ' . self::t( $ten ) . " (\n" . $cot . "\n) $c" );
		}
		update_option( 'vhvh_so_do', self::SO_DO );
	}

	/** Gọi mỗi lượt nạp plugin — chỉ đụng CSDL khi số sơ đồ lệch. */
	public static function bao_dam() {
		if ( get_option( 'vhvh_so_do' ) !== self::SO_DO ) { self::cai_dat(); }
	}
}
