<?php
/**
 * Schema MySQL — mỗi sheet của app cũ thành 1 bảng.
 *
 *   DonHang   -> vhcpvp_don            TamUng  -> vhcpvp_tamung        ChiPhi  -> vhcpvp_chiphi
 *   DA_Index  -> vhcpvp_da_index       <sheet dự án> -> vhcpvp_da_line (khóa theo ma_da + row_no)
 *   MK_Don    -> vhcpvp_mk_don         MK_Line -> vhcpvp_mk_line
 *   BP_Index  -> vhcpvp_bp_index       <sheet đợt>   -> vhcpvp_bp_line (khóa theo ma + row_no)
 *   CH_*      -> vhcpvp_cfg (bảng dùng chung, mỗi dòng là 1 hàng sheet dạng JSON)
 *   NhatKy    -> vhcpvp_log
 *   Document/Script Properties -> vhcpvp_meta
 *
 * row_no giữ nguyên cách đánh số hàng của Sheet (dữ liệu bắt đầu ở hàng 5) vì giao diện
 * vẫn gọi updateDuAnLine(maDA, row, rec) theo số hàng. Khác Sheet ở một điểm CÓ LỢI:
 * xóa 1 dòng KHÔNG dồn số hàng của dòng khác, nên không có chuyện sửa nhầm dòng.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPVP_DB {

	/* 1.10.0: thêm cột `ngay_gui_qt` — mốc NHÂN VIÊN BẤM GỬI quyết toán. Khác hẳn `ngay_qt`
	   (mốc KẾ TOÁN xác nhận), và trước bản này không có gì ghi lại lượt gửi, nên bảng "Chờ
	   quyết toán" không xếp được theo "ai gửi trước xử trước". */
	const SCHEMA_VERSION = '1.13.0';   // 1.9.0: bảng lenh_tu · 1.10.0: don.ngay_gui_qt · 1.11.0: da_line.tao_luc · 1.12.0: cột `mang` · 1.13.0: đổi tên `mang` → `khoi`
	const DATA_ROW       = 5;   // DA_DATA_ROW / BP_DATA_ROW của app cũ

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	 * MẢNG — KVC · MTD · VP TRONG CÙNG MỘT KHO.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * Anh Thắng 19/09/2026: *"Chia 3 tab (KVC, MTD, VP)"*, và chọn **gộp thật làm một app** chứ
	 * không phải ba đường dẫn đặt cạnh nhau. Đây là BƯỚC 1: mở chỗ trong sơ đồ bảng, chưa đổi
	 * một nét nào trên màn.
	 *
	 * 🔴 CỘT NÀY CHỈ ĐẶT Ở BẢN GHI ĐẦU (đơn · dự án · marketing · công tác · lệnh tạm ứng · sổ
	 *    chi · nhật ký · thùng rác). DÒNG CON KHÔNG CÓ — `chiphi`, `da_line`, `mk_line`,
	 *    `bp_line`, `tamung` đều đã khoá theo mã của bản ghi cha, nên mảng của chúng suy ra từ
	 *    cha. Đặt thêm một bản sao ở dòng con là hai nơi cùng giữ MỘT sự thật, và ngày chúng
	 *    lệch nhau thì tiền của một hạng mục nằm ở mảng này còn đơn chứa nó nằm ở mảng kia —
	 *    không màn nào cộng ra đúng nữa, mà cũng không màn nào báo sai.
	 *    `so_chi` thì CÓ, vì nó là sổ đứng riêng: dòng của nó không treo vào đơn nào.
	 *
	 * 🔴 KHÔNG BAO GIỜ ĐỂ RỖNG. Rỗng nghĩa là "không biết mảng nào", mà mọi màn sẽ lọc theo
	 *    mảng — một bản ghi rỗng là một bản ghi KHÔNG TAB NÀO THẤY: tiền có thật, đơn có thật,
	 *    mà mở app ra thì như chưa từng tồn tại. Nên cột khai `NOT NULL DEFAULT` và `install()`
	 *    còn quét lại một lượt lấp nốt (xem `lap_khoi()`).
	 *
	 * ⚠️ HẰNG NÀY ĐỔI THEO TỪNG BẢN. `tools/tach-ban-vung.sh` viết lại nó thành mã vùng khi
	 *    sinh bản Máy Tự Động / Văn Phòng, y như `TEN_MAC_DINH`. Để nguyên 'kvc' ở bản mảng
	 *    khác thì dữ liệu họ nhập vào lúc này mang nhãn sai, và bước dời dữ liệu về sau sẽ dời
	 *    nhầm chỗ.
	 * ══════════════════════════════════════════════════════════════════════════════════════════ */
	const KHOI = 'vp';

	/** Các bảng mang cột `mang` — dùng cho cả `install()` lẫn bài kiểm, để hai bên không lệch. */
	const BANG_CO_KHOI = array( 'don', 'so_chi', 'da_index', 'mk_don', 'bp_index', 'lenh_tu', 'log', 'thungrac' );

	/** Mảng của bản đang chạy. Bước 2 sẽ cho nó trả về mảng đang chọn trên thanh tab. */
	public static function khoi() {
		return self::KHOI;
	}

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'vhcpvp_' . $name;
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
		// KHÔNG có 'hopdong' ở đây: bảng đó của bản cũ, giữ nguyên chứ không xoá.
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
			foreach ( VHCPVP_Meta::get_prefix( $p ) as $k => $v ) { VHCPVP_Meta::del( $k ); }
		}
		VHCPVP_Cfg::clear_cache();
		return $out;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$m = self::KHOI;   /* hằng lớp không nội suy vào chuỗi nháy kép — phải qua biến */

		$sql = array();

		/* Cột `don_vi` = ĐƠN VỊ (K&H · POSH) — xem `VHCPVP_DonVi`. Ghi MỘT LẦN lúc lập đơn, theo
		   nhà của người lập, rồi KHÔNG đổi nữa: đổi đơn vị của một đơn đã chạy là chuyển cả
		   tiền sang sổ bên kia mà không có vết. Muốn chuyển thì lập đơn mới ở bên ấy.
		   Đơn cũ có ô rỗng — đúng, vì trước khi có POSH thì mọi đơn đều là đơn K&H.

		   🔴 CHÚ THÍCH Ở ĐÂY, NGOÀI CHUỖI SQL — xem chốt cuối `install()`. */
		$sql[] = "CREATE TABLE " . self::t( 'don' ) . " (
			ma_don VARCHAR(40) NOT NULL,
			ky VARCHAR(120) NOT NULL DEFAULT '',
			nguoi_lap VARCHAR(120) NOT NULL DEFAULT '',
			don_vi VARCHAR(60) NOT NULL DEFAULT '',
			ngay_tao DATETIME NULL,
			trang_thai VARCHAR(40) NOT NULL DEFAULT 'Nháp',
			ghi_chu TEXT NULL,
			nguoi_duyet VARCHAR(120) NOT NULL DEFAULT '',
			ngay_duyet DATETIME NULL,
			nguoi_qt VARCHAR(120) NOT NULL DEFAULT '',
			ngay_qt DATETIME NULL,
			ngay_gui_qt DATETIME NULL,
			chenh_lech_qt DECIMAL(18,2) NOT NULL DEFAULT 0,
			xu_ly VARCHAR(60) NOT NULL DEFAULT '',
			so_tien_thuc_mua DECIMAL(18,2) NULL,
			hinh_thuc_tt VARCHAR(60) NOT NULL DEFAULT '',
			hoa_don_qt TEXT NULL,
			hoa_don_qt2 TEXT NULL,
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
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (ma_don),
			KEY khoi (khoi),
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
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (id),
			KEY khoi (khoi),
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
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (ma_da),
			KEY khoi (khoi),
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
			tao_luc DATETIME NULL,
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
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (ma),
			KEY khoi (khoi),
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
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (ma),
			KEY khoi (khoi),
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

		// BẢNG hopdong (bản 1.15–1.17) KHÔNG còn được tạo nữa: thư viện hợp đồng đã tách sang
		// plugin riêng "Thư Viện Hợp Đồng", dữ liệu nằm ở Google Sheet của app Apps Script. Bảng cũ
		// nếu đã có thì GIỮ NGUYÊN, không xoá — ai đã nhập hợp đồng vào đó thì dữ liệu vẫn còn.

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
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (id),
			KEY khoi (khoi),
			KEY tg (tg)
		) $c";

		/* 🔴 THÙNG RÁC — anh Thắng 31/08/2026: *"Bổ sung thêm nút hoàn tác vụ, cho lỡ xóa nhầm
		   đơn hoặc chi phí thì hoàn lại lệnh đó."*

		   Trước bản này xoá là XOÁ THẬT: `purge_don()` gọi ba lượt DELETE, và không có đường
		   nào lấy lại. Nhật ký ghi được "ai xoá, tổng bao nhiêu tiền" nhưng KHÔNG ghi nội dung
		   từng dòng — nên nó trả lời được câu "mất bao nhiêu", không trả lời được "mất những
		   gì" và không dựng lại được.

		   `du_lieu` giữ nguyên văn hàng đã xoá (đơn + mọi dòng chi + mọi dòng tạm ứng) dưới
		   dạng JSON. Không tách thành cột: khuôn bảng `don` còn đổi theo thời gian, mà bản sao
		   thì phải dựng lại được ĐÚNG những gì đã có lúc xoá, kể cả cột nay không còn dùng.

		   Cột `da_hoan`: hoàn rồi thì KHÔNG hoàn lần hai — hoàn hai lần là đẻ ra bản sao của
		   cùng một khoản chi, tức tiền đếm đôi. Giữ dòng lại (không xoá) để nhật ký còn nguyên.

		   🔴 CHÚ THÍCH Ở ĐÂY, NGOÀI CHUỖI SQL — xem chốt cuối `install()`. */
		$sql[] = "CREATE TABLE " . self::t( 'thungrac' ) . " (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			luc DATETIME NULL,
			loai VARCHAR(20) NOT NULL DEFAULT '',
			khoa VARCHAR(190) NOT NULL DEFAULT '',
			nhan TEXT NULL,
			du_lieu LONGTEXT NULL,
			nguoi VARCHAR(120) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			don_vi VARCHAR(60) NOT NULL DEFAULT '',
			da_hoan TINYINT(1) NOT NULL DEFAULT 0,
			hoan_luc DATETIME NULL,
			hoan_nguoi VARCHAR(120) NOT NULL DEFAULT '',
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (id),
			KEY khoi (khoi),
			KEY luc (luc),
			KEY khoa (khoa)
		) $c";

		/* 🔴 LỆNH TẠM ỨNG — anh Thắng 07/09/2026: *"khi quản lý duyệt 1 lần đơn tạm ứng, sẽ tạo
		   1 lệnh tạm ứng phía dưới cuối trang (tạm ứng bao nhiêu, mấy cơ sở tạm ứng, cơ sở nào
		   tạm ứng)"*.

		   Mỗi LƯỢT DUYỆT là một lệnh — không phải mỗi đơn. Quản lý tích chín đơn rồi bấm một
		   cái, thì cái kế toán cần cầm là MỘT tờ lệnh: tổng bấy nhiêu, chia cho mấy cơ sở,
		   những cơ sở nào. Đọc ngược từ bảng đơn thì không dựng lại được lượt bấm ấy: mấy đơn
		   duyệt cùng lúc trông y hệt mấy đơn duyệt rải rác trong ngày.

		   `chi_tiet` giữ JSON từng cơ sở (tên · số tiền · mã đơn). Không tách thành bảng con:
		   lệnh là ẢNH CHỤP tại thời điểm duyệt, và nó phải giữ nguyên con số lúc ấy kể cả khi
		   sau này đơn bị trả lại, sửa số, hay xoá — đúng lý do bảng `thungrac` cũng lưu JSON.

		   `stt` để SẮP XẾP, không dùng `luc` + `id`: `luc` chỉ tới GIÂY, mà quản lý duyệt hai
		   lô liền tay thì cả hai rơi cùng một giây; còn `id` là base36 thời gian nối base36
		   ngẫu nhiên KHÔNG đệm 0 (`..._abc1` so với `..._abcxyz`), nên so chuỗi ra thứ tự lẫn
		   lộn. Sổ lệnh đảo thứ tự thì người cầm tiền phát nhầm tờ.

		   🔴 CHÚ THÍCH PHẢI Ở ĐÂY, NGOÀI CHUỖI SQL — xem chốt cuối `install()`. */
		$sql[] = "CREATE TABLE " . self::t( 'lenh_tu' ) . " (
			id VARCHAR(40) NOT NULL,
			luc DATETIME NULL,
			nguoi VARCHAR(120) NOT NULL DEFAULT '',
			don_vi VARCHAR(60) NOT NULL DEFAULT '',
			ky VARCHAR(120) NOT NULL DEFAULT '',
			tong DECIMAL(18,2) NOT NULL DEFAULT 0,
			so_don INT NOT NULL DEFAULT 0,
			so_coso INT NOT NULL DEFAULT 0,
			chi_tiet LONGTEXT NULL,
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			khoi VARCHAR(20) NOT NULL DEFAULT '$m',
			PRIMARY KEY  (id),
			KEY khoi (khoi),
			UNIQUE KEY stt (stt),
			KEY luc (luc)
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

		/* 🔴 KHÔNG ĐƯỢC VIẾT CHÚ THÍCH BÊN TRONG CHUỖI `CREATE TABLE`.
		   Mấy câu trên là chuỗi PHP nhiều dòng, nên một khối `/* … *' . '/` đặt lọt vào giữa
		   KHÔNG phải chú thích của PHP — nó là văn bản nằm trong chính câu SQL. `dbDelta()`
		   tách câu theo TỪNG DÒNG rồi dò tên cột bằng biểu thức chính quy, gặp mấy dòng chữ ấy
		   là hiểu nhầm thành cột, sinh ra câu sai, MySQL chối — mà `dbDelta()` KHÔNG ném lỗi ra
		   ngoài. Kết quả: bảng lặng lẽ không được tạo, và mọi lượt ghi vào nó trả false im lặng.

		   Đã sập đúng bẫy này ngày 07/09/2026 với bảng `lenh_tu`: anh Thắng cài xong, tắt bật
		   lại plugin, vẫn *"chưa được"* — vì lần nào `dbDelta()` cũng bỏ qua đúng bảng ấy.
		   Chú thích để NGOÀI, ngay trên dòng `$sql[] =`. `tools/test/kiem-so-do-bang.php` canh
		   chỗ này. */
		foreach ( $sql as $q ) { dbDelta( $q ); }

		self::doi_ten_mang_thanh_khoi();
		self::lap_khoi();
		self::bo_khau_gom();

		update_option( 'vhcpvp_db_version', self::SCHEMA_VERSION );
		update_option( 'vhcpvp_flush_rewrite', 1 );   // để vhcpvp_flush_rewrite() nạp lại /chi-phi/ và /hop-dong/
		VHCPVP_Cfg::seed();
		/* Bật "Xác nhận quyết toán" cho các vai kế toán trên bảng quyền ĐANG LƯU — xem chú
		   thích dài ở `VHCPVP_Cfg::va_quyen_quyet_toan()`. Đặt SAU `seed()` vì bảng quyền phải
		   có mặt trước đã. */
		if ( method_exists( 'VHCPVP_Cfg', 'va_quyen_quyet_toan' ) ) {
			VHCPVP_Cfg::va_quyen_quyet_toan();
		}
	}

	/**
	 * DI CƯ: CỘT `mang` CỦA BẢN 1.217.x → `khoi`.
	 *
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 VÌ SAO ĐỔI TÊN. Chữ "mảng" trong hệ này đã mang HAI nghĩa từ trước:
	 *      · `VHCPVP_Cfg::MANG` (= 'CH_MangTK') — MẢNG KINH DOANH: Event · Farm · Funzone · TuTu,
	 *        thứ quyết định mã tài khoản 641x của một dòng chi;
	 *      · `VHCPVP_TraMa` — NGUỒN của một dòng: 'sochi' · 'don' · 'kt' · 'mkt'.
	 *    Bản 1.217.x thêm nghĩa thứ BA cho cùng chữ ấy (KVC · MTĐ · VP), và hằng mới
	 *    `VHCPVP_DB::MANG` chỉ khác `VHCPVP_Cfg::MANG` đúng một tên lớp. Ở đây đọc nhầm nghĩa là
	 *    tiền chạy sang sổ khác.
	 *    Anh Thắng 20/09/2026 chốt: dùng chữ **KHỐI** cho trục KVC · MTĐ · VP.
	 *
	 * ⚠️ CHÉP RỒI MỚI BỎ, VÀ CHỈ BỎ KHI CHÉP XONG. Bản 1.217.x đã cài lên host nên cột `mang`
	 *    ngoài đó ĐANG CÓ DỮ LIỆU. Bỏ thẳng là mất dấu khối của mọi bản ghi đã đóng dấu.
	 *
	 * ⚠️ `dbDelta()` KHÔNG BAO GIỜ BỎ CỘT — nó chỉ thêm và nới. Nên phải tự gọi `DROP COLUMN`,
	 *    không thì cột chết nằm lại mãi, và nó là đúng cái tên gây nhầm mà việc này đi dọn.
	 * ══════════════════════════════════════════════════════════════════════════════════════════
	 *
	 * @return array [bảng => số dòng đã chép]
	 */
	public static function doi_ten_mang_thanh_khoi() {
		global $wpdb;
		$ra = array();
		foreach ( self::BANG_CO_KHOI as $ten ) {
			$t = self::t( $ten );
			if ( (string) $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) { continue; }
			$cot = (array) $wpdb->get_col( "SHOW COLUMNS FROM $t" );
			if ( ! in_array( 'mang', $cot, true ) ) { continue; }   // chưa từng cài 1.217.x
			if ( ! in_array( 'khoi', $cot, true ) ) { continue; }   // dbDelta chưa thêm kịp — lượt sau
			$n = $wpdb->query( "UPDATE $t SET khoi = mang WHERE khoi = '' AND mang <> ''" );
			if ( $n ) { $ra[ $ten ] = (int) $n; }
			$wpdb->query( "ALTER TABLE $t DROP COLUMN mang" );
		}
		return $ra;
	}

	/**
	 * LẤP KHỐI CHO MỌI BẢN GHI CHƯA CÓ.
	 *
	 * 🔴 RỖNG LÀ MẤT TÍCH, KHÔNG PHẢI "CHƯA KHAI". Từ bước 2 trở đi mọi màn lọc theo mảng, nên
	 *    một bản ghi mang chuỗi rỗng là bản ghi KHÔNG TAB NÀO THẤY: đơn có thật, tiền có thật,
	 *    mà mở app ra thì như chưa từng tồn tại — và không câu lỗi nào báo, vì đứng về phía máy
	 *    thì bộ lọc chạy đúng y như được bảo.
	 *
	 * ⚠️ VÌ SAO VẪN QUÉT DÙ CỘT ĐÃ CÓ `DEFAULT`. `dbDelta()` thêm cột NOT NULL kèm mặc định thì
	 *    MySQL tự lấp cho dòng cũ — đúng, NHƯNG chỉ ở lượt THÊM CỘT. Cột đã có sẵn từ một bản
	 *    nửa vời trước đó (hoặc ai đó thêm tay, hoặc `dbDelta` đã chạy rồi mà đổi mặc định sau)
	 *    thì dòng cũ giữ nguyên chuỗi rỗng và không ai lấp hộ. Quét một lượt là rẻ: chạy đúng
	 *    lúc đổi số sơ đồ bảng, và cột đã đánh chỉ mục.
	 *
	 * ⚠️ GÁC `SHOW TABLES`: lượt cài mới chạy `install()` khi bảng vừa dựng xong, nhưng một
	 *    bảng lỡ trượt `dbDelta()` (xem chốt "không viết chú thích trong chuỗi CREATE TABLE")
	 *    thì UPDATE vào bảng không có là một câu lỗi MySQL đổ ra giữa trang quản trị.
	 *
	 * @return array [bảng => số dòng vừa lấp]
	 */
	public static function lap_khoi() {
		global $wpdb;
		$ra = array();
		foreach ( self::BANG_CO_KHOI as $ten ) {
			$t = self::t( $ten );
			if ( (string) $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) { continue; }
			/* 🔴 HỎI CỘT TRƯỚC KHI GHI. Gác `SHOW TABLES` ở trên chỉ chắc có BẢNG; nhưng
			   `dbDelta()` có thể thêm bảng mà TRƯỢT một cột — nó tách câu theo từng dòng rồi dò
			   bằng biểu thức, gặp chỗ nó không hiểu là bỏ qua và KHÔNG ném lỗi ra ngoài (xem
			   chốt dài cuối `install()`, đã cắn thật với bảng `lenh_tu` ngày 07/09/2026).
			   Lúc ấy câu UPDATE dưới đây thành "Unknown column 'mang'", mà `$wpdb` trong
			   wp-admin thì IN THẲNG lỗi SQL ra màn hình — người ta cài xong plugin và thấy một
			   trang đỏ, trong khi chuyện duy nhất hỏng là một cột chưa kịp thêm. Thiếu cột thì
			   bỏ qua bảng ấy: lượt `install()` sau sẽ thêm được và lấp nốt. */
			$co_cot = false;
			foreach ( (array) $wpdb->get_col( "SHOW COLUMNS FROM $t" ) as $c ) {
				if ( 'khoi' === $c ) { $co_cot = true; break; }
			}
			if ( ! $co_cot ) { continue; }
			$n = $wpdb->query( $wpdb->prepare( "UPDATE $t SET khoi=%s WHERE khoi=''", self::KHOI ) );
			if ( $n ) { $ra[ $ten ] = (int) $n; }
		}
		return $ra;
	}

	/**
	 * BỎ KHÂU "QUẢN LÝ GOM HÓA ĐƠN".
	 *
	 * Luồng cũ: NV gửi -> "Chờ quản lý gom" -> quản lý đẩy -> "Chờ quyết toán" -> kế toán.
	 * Thực tế chạy khác: cấp tạm ứng xong thì NV bổ sung hóa đơn, chốt chi phí rồi gửi
	 * THẲNG cho kế toán — khâu gom chỉ là một chặng dừng không ai làm gì, đơn nằm đó chờ.
	 *
	 * Bỏ khâu đó thì phải dời NỐT các đơn đang mắc kẹt ở trạng thái này sang "Chờ quyết
	 * toán", nếu không màn Gom mất đi mà đơn vẫn ở đó — không tab nào thấy, không ai duyệt
	 * được nữa, tiền tạm ứng treo luôn.
	 */
	public static function bo_khau_gom() {
		global $wpdb;
		$t = self::t( 'don' );
		if ( (string) $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) { return 0; }
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET trang_thai=%s WHERE trang_thai=%s", 'Chờ quyết toán', 'Chờ quản lý gom'
		) );
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
