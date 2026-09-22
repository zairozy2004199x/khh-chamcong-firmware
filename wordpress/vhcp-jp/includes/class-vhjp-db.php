<?php
/**
 * SƠ ĐỒ BẢNG — JP Capsule.
 *
 * =============================================================================================
 * BẢN GỐC LÀ GOOGLE SHEETS. BẢN NÀY LÀ MySQL. LƯỢC ĐỒ PHẢI KHỚP TỪNG CỘT.
 * =============================================================================================
 * Mã gốc giữ ở `goc/jp-capsule-v2/` — 23 tab sheet khai trong `JP2_00_Config.gs`, hằng `JP_TABS`.
 * Mỗi tab ở đây thành một bảng, và **mọi cột bên kia phải có chỗ bên này**. Thiếu một cột là
 * chuyển dữ liệu sang xong thì mất đúng cột ấy, im lặng, không ai thấy cho tới lúc đối soát.
 * `tools/test/kiem-jp-luoc-do.php` đọc THẲNG tệp gốc rồi đối chiếu, nên trôi là bài kiểm đỏ.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 `id` LÀ CHUỖI, KHÔNG PHẢI SỐ TỰ TĂNG.
 * ---------------------------------------------------------------------------------------------
 * `jpNextId_()` (JP2_01_Core.gs) sinh mã dạng `RP20260731-0007` — tiền tố + ngày + số thứ tự.
 * Dữ liệu đang chạy ngoài đời mang đúng mấy mã ấy, và chúng nằm trong mọi khoá ngoại lẫn mọi
 * ảnh chụp màn hình kế toán đã lưu. Đổi sang BIGINT tự tăng là phải đánh số lại toàn bộ và
 * mọi liên kết cũ đứt. Nên: VARCHAR(32), và bản chuyển dữ liệu bê nguyên mã cũ sang.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 `bank_gd.refId` LÀ UNIQUE — CÙNG MỘT LUẬT VỚI `ghe_thu.ref`.
 * ---------------------------------------------------------------------------------------------
 * Đó là mã tham chiếu của ngân hàng. Khoá duy nhất trên nó là thứ khiến "nhập lại sao kê cho
 * chắc" thành việc AN TOÀN: lượt thứ hai đè lên đúng hàng cũ thay vì cộng thêm một khoản. Bỏ
 * ràng buộc ấy là tháng sau không ai dựng lại được con số thật. Bên Ghế đã học bài này rồi.
 *
 * ---------------------------------------------------------------------------------------------
 * ⚠️ TIỀN `DECIMAL(15,2)`, SỐ LƯỢNG `DECIMAL(15,3)` — KHÔNG DÙNG FLOAT.
 * ---------------------------------------------------------------------------------------------
 * Sheets để mọi ô là số thực. Bê nguyên sang FLOAT là mỗi lượt cộng dồn lại lệch vài đồng, và
 * bảng đối soát cuối tháng không bao giờ về 0 — không ai tìm ra vì chẳng dòng nào sai cả.
 *
 * ⚠️ `rows` (sổ đối soát) PHẢI BỌC DẤU HUYỀN. MySQL 8 giữ chỗ từ ấy; để trần là câu CREATE
 *    TABLE chết ngay lúc kích hoạt plugin, mà thông báo lỗi thì chỉ nói "syntax error".
 *
 * ⚠️ `coDhTrung` / `chonGiaXung` giữ VARCHAR chứ không đổi sang TINYINT: bên kia lưu `'Y'`/`'N'`
 *    và **ô trống có nghĩa riêng** (xem chú thích ở `JP_TABS.LOCATIONS`). Ép sang 0/1 là nuốt
 *    mất nghĩa thứ ba ấy.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_DB {

	/** Tên bảng thật. Tiền tố `vhjp_` để không đụng bộ nào khác trên cùng WordPress. */
	public static function t( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'vhjp_' . $ten;
	}

	/** Bản đồ tab sheet -> bảng MySQL. Bài kiểm đọc chính bản đồ này để đối chiếu. */
	public static function ban_do() {
		return array(
			'JP_Users' => 'users',
			'JP_Locations' => 'coso',
			'JP_Clusters' => 'cum',
			'JP_Machines' => 'may',
			'JP_Items' => 'hang',
			'JP_Reports' => 'bao_cao',
			'JP_Zones' => 'khu',
			'JP_Rows' => 'dong',
			'JP_DeNghi' => 'de_nghi',
			'JP_Photos' => 'anh',
			'JP_Audit' => 'nhat_ky',
			'JP_Payments' => 'nop_tien',
			'JP_KhoNhap' => 'kho_nhap',
			'JP_KhoNhapCT' => 'kho_nhap_ct',
			'JP_KhoDieuChuyen' => 'kho_dc',
			'JP_KhoDieuChuyenCT' => 'kho_dc_ct',
			'JP_KhoLop' => 'kho_lop',
			'JP_KhoXuat' => 'kho_xuat',
			'JP_KhoKiemKe' => 'kho_kk',
			'JP_KhoKiemKeCT' => 'kho_kk_ct',
			'JP_KhoTraNcc' => 'kho_tra_ncc',
			'JP_BankGD' => 'bank_gd',
			'JP_ReconLog' => 'doi_soat_log',
		);
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		foreach ( self::bang() as $ten => $cot ) {
			dbDelta( 'CREATE TABLE ' . self::t( $ten ) . " (\n" . $cot . "\n) $c" );
		}
	}

	public static function bang() {
		$b = array();

		/* ===== 1. JP_Users -> users (12 cột) ===== */
		/* `maNV` — Mã nhân viên bên CHẤM CÔNG. Đây là sợi dây DUY NHẤT nối hai hệ, và nó
		   phải là một cột khai tay: dò theo họ tên là hai người trùng tên thì một người
		   đăng nhập được vào tài khoản của người kia. Rỗng = chưa nối, và rỗng KHÔNG
		   BAO GIỜ khớp với ai — xem `VHJP_Auth::sso_cham_cong()`. */
		$b['users'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			username VARCHAR(64) NOT NULL DEFAULT '',
			password VARCHAR(255) NOT NULL DEFAULT '',
			pin VARCHAR(255) NOT NULL DEFAULT '',
			maNV VARCHAR(64) NOT NULL DEFAULT '',
			hoTen VARCHAR(190) NOT NULL DEFAULT '',
			role VARCHAR(64) NOT NULL DEFAULT '',
			machineType VARCHAR(64) NOT NULL DEFAULT '',
			locationIds VARCHAR(500) NOT NULL DEFAULT '',
			active TINYINT(1) NOT NULL DEFAULT 0,
			createdAt DATETIME NULL,
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id)";

		/* ===== 2. JP_Locations -> coso (14 cột) ===== */
		$b['coso'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			code VARCHAR(64) NOT NULL DEFAULT '',
			name VARCHAR(190) NOT NULL DEFAULT '',
			maKH VARCHAR(64) NOT NULL DEFAULT '',
			unitCode VARCHAR(64) NOT NULL DEFAULT '',
			khuVuc VARCHAR(64) NOT NULL DEFAULT '',
			machineType VARCHAR(64) NOT NULL DEFAULT '',
			photoDefault VARCHAR(64) NOT NULL DEFAULT '',
			active TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NOT NULL DEFAULT '',
			bcMau VARCHAR(64) NOT NULL DEFAULT '',
			coDhTrung VARCHAR(4) NOT NULL DEFAULT '',
			chonGiaXung VARCHAR(4) NOT NULL DEFAULT '',
			maDinhDanh VARCHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id)";

		/* ===== 3. JP_Clusters -> cum (8 cột) ===== */
		$b['cum'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			name VARCHAR(190) NOT NULL DEFAULT '',
			payboxSerial VARCHAR(64) NOT NULL DEFAULT '',
			hasQR TINYINT(1) NOT NULL DEFAULT 0,
			photoCount INT(11) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY locationid (locationId)";

		/* ===== 4. JP_Machines -> may (9 cột) ===== */
		$b['may'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			clusterId VARCHAR(32) NOT NULL DEFAULT '',
			code VARCHAR(64) NOT NULL DEFAULT '',
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemMisa VARCHAR(64) NOT NULL DEFAULT '',
			photoCount INT(11) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY locationid (locationId),
			KEY clusterid (clusterId)";

		/* ===== 5. JP_Items -> hang (7 cột) ===== */
		$b['hang'] = "
			code VARCHAR(64) NOT NULL DEFAULT '',
			misa VARCHAR(64) NOT NULL DEFAULT '',
			name VARCHAR(190) NOT NULL DEFAULT '',
			price DECIMAL(15,2) NULL,
			dvt VARCHAR(64) NOT NULL DEFAULT '',
			active TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (code)";

		/* ===== 6. JP_Reports -> bao_cao (45 cột) ===== */
		$b['bao_cao'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			locationName VARCHAR(190) NOT NULL DEFAULT '',
			maKH VARCHAR(64) NOT NULL DEFAULT '',
			machineType VARCHAR(64) NOT NULL DEFAULT '',
			fromDate DATE NULL,
			toDate DATE NULL,
			userId VARCHAR(32) NOT NULL DEFAULT '',
			userName VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(64) NOT NULL DEFAULT '',
			bcMau VARCHAR(64) NOT NULL DEFAULT '',
			revMeter DECIMAL(15,2) NULL,
			revBank DECIMAL(15,2) NULL,
			revCashMeter DECIMAL(15,2) NULL,
			revHang DECIMAL(15,2) NULL,
			lechTienHang DECIMAL(15,2) NULL,
			adjMachine DECIMAL(15,2) NULL,
			adjMachineNote VARCHAR(500) NOT NULL DEFAULT '',
			refundCustomer DECIMAL(15,2) NULL,
			refundNote VARCHAR(500) NOT NULL DEFAULT '',
			refundRows INT(11) NOT NULL DEFAULT 0,
			cashActual DECIMAL(15,2) NULL,
			totalSubmit DECIMAL(15,2) NULL,
			submittedAt DATETIME NULL,
			apprBy VARCHAR(64) NOT NULL DEFAULT '',
			apprAt DATETIME NULL,
			apprRevBy VARCHAR(64) NOT NULL DEFAULT '',
			apprRevAt DATETIME NULL,
			apprStockBy VARCHAR(64) NOT NULL DEFAULT '',
			apprStockAt DATETIME NULL,
			rejectBy VARCHAR(64) NOT NULL DEFAULT '',
			rejectAt DATETIME NULL,
			rejectPart VARCHAR(64) NOT NULL DEFAULT '',
			rejectReason VARCHAR(500) NOT NULL DEFAULT '',
			nvPaid TINYINT(1) NOT NULL DEFAULT 0,
			nvPaidDate DATE NULL,
			nvPayStatus VARCHAR(64) NOT NULL DEFAULT '',
			nvPayNote VARCHAR(500) NOT NULL DEFAULT '',
			paid TINYINT(1) NOT NULL DEFAULT 0,
			paidDate DATE NULL,
			payStatus VARCHAR(64) NOT NULL DEFAULT '',
			warnCount INT(11) NOT NULL DEFAULT 0,
			remark VARCHAR(500) NOT NULL DEFAULT '',
			photoWarnJson LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY locationid (locationId),
			KEY userid (userId)";

		/* ===== 7. JP_Zones -> khu (6 cột) ===== */
		$b['khu'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			seq INT(11) NOT NULL DEFAULT 0,
			name VARCHAR(190) NOT NULL DEFAULT '',
			clusterId VARCHAR(32) NOT NULL DEFAULT '',
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY reportid (reportId),
			KEY clusterid (clusterId)";

		/* ===== 8. JP_Rows -> dong (46 cột) ===== */
		$b['dong'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			zoneId VARCHAR(32) NOT NULL DEFAULT '',
			seq INT(11) NOT NULL DEFAULT 0,
			rowKind VARCHAR(64) NOT NULL DEFAULT '',
			machineId VARCHAR(32) NOT NULL DEFAULT '',
			machineCode VARCHAR(64) NOT NULL DEFAULT '',
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemMisa VARCHAR(64) NOT NULL DEFAULT '',
			itemName VARCHAR(190) NOT NULL DEFAULT '',
			price DECIMAL(15,2) NULL,
			mBefore DECIMAL(15,3) NULL,
			mAfter DECIMAL(15,3) NULL,
			mActual DECIMAL(15,3) NULL,
			collection DECIMAL(15,3) NULL,
			amount DECIMAL(15,2) NULL,
			cBefore DECIMAL(15,3) NULL,
			cAfter DECIMAL(15,3) NULL,
			cActual DECIMAL(15,3) NULL,
			cash DECIMAL(15,2) NULL,
			bank DECIMAL(15,2) NULL,
			hOpen DECIMAL(15,3) NULL,
			hBefore DECIMAL(15,3) NULL,
			hAfter DECIMAL(15,3) NULL,
			soldQty DECIMAL(15,3) NULL,
			hLeft DECIMAL(15,3) NULL,
			stockOut DECIMAL(15,3) NULL,
			topupNote VARCHAR(500) NOT NULL DEFAULT '',
			giaXu DECIMAL(15,2) NULL,
			xuDaysJson LONGTEXT NULL,
			xuTong DECIMAL(15,2) NULL,
			xuLa DECIMAL(15,3) NULL,
			addQty1 DECIMAL(15,3) NULL,
			addQty2 DECIMAL(15,3) NULL,
			stockOpen DECIMAL(15,3) NULL,
			stockLeftCalc DECIMAL(15,3) NULL,
			stockActual DECIMAL(15,3) NULL,
			defectQty DECIMAL(15,3) NULL,
			returnQty DECIMAL(15,3) NULL,
			refundAmt DECIMAL(15,2) NULL,
			refundQty DECIMAL(15,3) NULL,
			refundRowNote VARCHAR(500) NOT NULL DEFAULT '',
			cashReal DECIMAL(15,2) NULL,
			giaXung DECIMAL(15,2) NULL,
			warnJson LONGTEXT NULL,
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY reportid (reportId),
			KEY zoneid (zoneId),
			KEY machineid (machineId)";

		/* ===== 9. JP_DeNghi -> de_nghi (19 cột) ===== */
		$b['de_nghi'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			loai VARCHAR(64) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			rowId VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			locationName VARCHAR(190) NOT NULL DEFAULT '',
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemMisa VARCHAR(64) NOT NULL DEFAULT '',
			itemName VARCHAR(190) NOT NULL DEFAULT '',
			soCu DECIMAL(15,3) NULL,
			soMoi DECIMAL(15,3) NULL,
			lyDo VARCHAR(500) NOT NULL DEFAULT '',
			userId VARCHAR(32) NOT NULL DEFAULT '',
			userName VARCHAR(190) NOT NULL DEFAULT '',
			guiLuc DATETIME NULL,
			trangThai VARCHAR(64) NOT NULL DEFAULT '',
			ktBy VARCHAR(64) NOT NULL DEFAULT '',
			ktLuc DATETIME NULL,
			ktGhiChu VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY reportid (reportId),
			KEY rowid (rowId),
			KEY locationid (locationId),
			KEY userid (userId)";

		/* ===== 10. JP_Photos -> anh (10 cột) ===== */
		$b['anh'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			scope VARCHAR(64) NOT NULL DEFAULT '',
			refId VARCHAR(32) NOT NULL DEFAULT '',
			kind VARCHAR(64) NOT NULL DEFAULT '',
			fileId VARCHAR(32) NOT NULL DEFAULT '',
			url VARCHAR(500) NOT NULL DEFAULT '',
			takenAt DATETIME NULL,
			uploadedAt DATETIME NULL,
			bytes INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY reportid (reportId),
			KEY refid (refId),
			KEY fileid (fileId)";

		/* ===== 11. JP_Audit -> nhat_ky (7 cột) ===== */
		$b['nhat_ky'] = "
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			at DATETIME NULL,
			who VARCHAR(64) NOT NULL DEFAULT '',
			role VARCHAR(64) NOT NULL DEFAULT '',
			action VARCHAR(64) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			target VARCHAR(64) NOT NULL DEFAULT '',
			detail VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (stt),
			KEY reportid (reportId)";

		/* ===== 12. JP_Payments -> nop_tien (12 cột) ===== */
		$b['nop_tien'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			amount DECIMAL(15,2) NULL,
			payDate DATE NULL,
			method VARCHAR(64) NOT NULL DEFAULT '',
			note VARCHAR(500) NOT NULL DEFAULT '',
			photoId VARCHAR(32) NOT NULL DEFAULT '',
			photoUrl VARCHAR(500) NOT NULL DEFAULT '',
			isSupplement TINYINT(1) NOT NULL DEFAULT 0,
			createdBy VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			PRIMARY KEY  (id),
			KEY reportid (reportId),
			KEY locationid (locationId),
			KEY photoid (photoId)";

		/* ===== 13. JP_KhoNhap -> kho_nhap (17 cột) ===== */
		$b['kho_nhap'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			soChungTu VARCHAR(64) NOT NULL DEFAULT '',
			ngay DATE NULL,
			loaiNhap VARCHAR(64) NOT NULL DEFAULT '',
			khoId VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			locationName VARCHAR(190) NOT NULL DEFAULT '',
			nccMa VARCHAR(64) NOT NULL DEFAULT '',
			nccTen VARCHAR(190) NOT NULL DEFAULT '',
			soDong INT(11) NOT NULL DEFAULT 0,
			tongTien DECIMAL(15,2) NULL,
			ghiChu VARCHAR(500) NOT NULL DEFAULT '',
			createdBy VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			huyBy VARCHAR(64) NOT NULL DEFAULT '',
			huyAt DATETIME NULL,
			huyReason VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY khoid (khoId),
			KEY locationid (locationId)";

		/* ===== 14. JP_KhoNhapCT -> kho_nhap_ct (11 cột) ===== */
		$b['kho_nhap_ct'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			nhapId VARCHAR(32) NOT NULL DEFAULT '',
			seq INT(11) NOT NULL DEFAULT 0,
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemName VARCHAR(190) NOT NULL DEFAULT '',
			dvt VARCHAR(64) NOT NULL DEFAULT '',
			qty DECIMAL(15,3) NULL,
			unitCost DECIMAL(15,2) NULL,
			amount DECIMAL(15,2) NULL,
			lotNo VARCHAR(64) NOT NULL DEFAULT '',
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY nhapid (nhapId)";

		/* ===== 15. JP_KhoDieuChuyen -> kho_dc (16 cột) ===== */
		$b['kho_dc'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			soChungTu VARCHAR(64) NOT NULL DEFAULT '',
			ngay DATE NULL,
			loai VARCHAR(64) NOT NULL DEFAULT '',
			khoTu VARCHAR(64) NOT NULL DEFAULT '',
			khoDen VARCHAR(64) NOT NULL DEFAULT '',
			locationName VARCHAR(190) NOT NULL DEFAULT '',
			soDong INT(11) NOT NULL DEFAULT 0,
			tongSL DECIMAL(15,3) NULL,
			tongTien DECIMAL(15,2) NULL,
			ghiChu VARCHAR(500) NOT NULL DEFAULT '',
			createdBy VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			huyBy VARCHAR(64) NOT NULL DEFAULT '',
			huyAt DATETIME NULL,
			huyReason VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id)";

		/* ===== 16. JP_KhoDieuChuyenCT -> kho_dc_ct (12 cột) ===== */
		$b['kho_dc_ct'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			dcId VARCHAR(32) NOT NULL DEFAULT '',
			seq INT(11) NOT NULL DEFAULT 0,
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemName VARCHAR(190) NOT NULL DEFAULT '',
			qty DECIMAL(15,3) NULL,
			unitCost DECIMAL(15,2) NULL,
			amount DECIMAL(15,2) NULL,
			layerId VARCHAR(32) NOT NULL DEFAULT '',
			layerMoi TINYINT(1) NOT NULL DEFAULT 0,
			thieuLop TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY dcid (dcId),
			KEY layerid (layerId)";

		/* ===== 17. JP_KhoLop -> kho_lop (11 cột) ===== */
		$b['kho_lop'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			khoId VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			ngay DATE NULL,
			qtyInit DECIMAL(15,3) NULL,
			qtyRemaining DECIMAL(15,3) NULL,
			unitCost DECIMAL(15,2) NULL,
			nguon VARCHAR(64) NOT NULL DEFAULT '',
			lotNo VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			PRIMARY KEY  (id),
			KEY khoid (khoId),
			KEY locationid (locationId)";

		/* ===== 18. JP_KhoXuat -> kho_xuat (22 cột) ===== */
		$b['kho_xuat'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			soChungTu VARCHAR(64) NOT NULL DEFAULT '',
			ngay DATE NULL,
			loai VARCHAR(64) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			dcId VARCHAR(32) NOT NULL DEFAULT '',
			khoId VARCHAR(32) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			locationName VARCHAR(190) NOT NULL DEFAULT '',
			maKH VARCHAR(64) NOT NULL DEFAULT '',
			unitCode VARCHAR(64) NOT NULL DEFAULT '',
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemName VARCHAR(190) NOT NULL DEFAULT '',
			qty DECIMAL(15,3) NULL,
			unitCost DECIMAL(15,2) NULL,
			amount DECIMAL(15,2) NULL,
			tkNo VARCHAR(64) NOT NULL DEFAULT '',
			tkCo VARCHAR(64) NOT NULL DEFAULT '',
			layerId VARCHAR(32) NOT NULL DEFAULT '',
			thieuLop TINYINT(1) NOT NULL DEFAULT 0,
			createdBy VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			PRIMARY KEY  (id),
			KEY reportid (reportId),
			KEY dcid (dcId),
			KEY khoid (khoId),
			KEY locationid (locationId),
			KEY layerid (layerId)";

		/* ===== 19. JP_KhoKiemKe -> kho_kk (17 cột) ===== */
		$b['kho_kk'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			soChungTu VARCHAR(64) NOT NULL DEFAULT '',
			ngay DATE NULL,
			khoId VARCHAR(32) NOT NULL DEFAULT '',
			locationName VARCHAR(190) NOT NULL DEFAULT '',
			cheDoThua TINYINT(1) NOT NULL DEFAULT 0,
			soDong INT(11) NOT NULL DEFAULT 0,
			slThua DECIMAL(15,3) NULL,
			slThieu DECIMAL(15,3) NULL,
			tienThua DECIMAL(15,2) NULL,
			tienThieu DECIMAL(15,2) NULL,
			slGiamXuat DECIMAL(15,3) NULL,
			tienGiamXuat DECIMAL(15,2) NULL,
			nhapId VARCHAR(32) NOT NULL DEFAULT '',
			ghiChu VARCHAR(500) NOT NULL DEFAULT '',
			createdBy VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			PRIMARY KEY  (id),
			KEY khoid (khoId),
			KEY nhapid (nhapId)";

		/* ===== 20. JP_KhoKiemKeCT -> kho_kk_ct (15 cột) ===== */
		$b['kho_kk_ct'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			kkId VARCHAR(32) NOT NULL DEFAULT '',
			seq INT(11) NOT NULL DEFAULT 0,
			itemCode VARCHAR(64) NOT NULL DEFAULT '',
			itemName VARCHAR(190) NOT NULL DEFAULT '',
			tonSo DECIMAL(15,3) NULL,
			tonThuc DECIMAL(15,3) NULL,
			lech DECIMAL(15,3) NULL,
			unitCost DECIMAL(15,2) NULL,
			amount DECIMAL(15,2) NULL,
			cheDo VARCHAR(64) NOT NULL DEFAULT '',
			ctGoc VARCHAR(64) NOT NULL DEFAULT '',
			tkNo VARCHAR(64) NOT NULL DEFAULT '',
			tkCo VARCHAR(64) NOT NULL DEFAULT '',
			note VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY kkid (kkId)";

		/* ===== 21. JP_KhoTraNcc -> kho_tra_ncc (13 cột) ===== */
		$b['kho_tra_ncc'] = "
			id VARCHAR(32) NOT NULL DEFAULT '',
			soChungTu VARCHAR(64) NOT NULL DEFAULT '',
			ngay DATE NULL,
			nccMa VARCHAR(64) NOT NULL DEFAULT '',
			nccTen VARCHAR(190) NOT NULL DEFAULT '',
			soTien DECIMAL(15,2) NULL,
			hinhThuc VARCHAR(64) NOT NULL DEFAULT '',
			ghiChu VARCHAR(500) NOT NULL DEFAULT '',
			createdBy VARCHAR(64) NOT NULL DEFAULT '',
			createdAt DATETIME NULL,
			huyBy VARCHAR(64) NOT NULL DEFAULT '',
			huyAt DATETIME NULL,
			huyReason VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id)";

		/* ===== 22. JP_BankGD -> bank_gd (12 cột) ===== */
		$b['bank_gd'] = "
			refId VARCHAR(32) NOT NULL DEFAULT '',
			ngay DATE NULL,
			soTien DECIMAL(15,2) NULL,
			noiDung VARCHAR(500) NOT NULL DEFAULT '',
			locationId VARCHAR(32) NOT NULL DEFAULT '',
			trangThai VARCHAR(64) NOT NULL DEFAULT '',
			reportId VARCHAR(32) NOT NULL DEFAULT '',
			paymentId VARCHAR(32) NOT NULL DEFAULT '',
			docLuc DATETIME NULL,
			apLuc DATETIME NULL,
			apBoi VARCHAR(64) NOT NULL DEFAULT '',
			lyDo VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (refId),
			KEY locationid (locationId),
			KEY reportid (reportId),
			KEY paymentid (paymentId)";

		/* ===== 23. JP_ReconLog -> doi_soat_log (13 cột) ===== */
		$b['doi_soat_log'] = "
			stt BIGINT(20) NOT NULL AUTO_INCREMENT,
			at DATETIME NULL,
			who VARCHAR(64) NOT NULL DEFAULT '',
			batchId VARCHAR(32) NOT NULL DEFAULT '',
			kind VARCHAR(64) NOT NULL DEFAULT '',
			`rows` INT(11) NOT NULL DEFAULT 0,
			matched INT(11) NOT NULL DEFAULT 0,
			ambiguous INT(11) NOT NULL DEFAULT 0,
			amount DECIMAL(15,2) NULL,
			note VARCHAR(500) NOT NULL DEFAULT '',
			allocJson LONGTEXT NULL,
			undoneAt DATETIME NULL,
			undoneBy VARCHAR(64) NOT NULL DEFAULT '',
			undoneReason VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (stt),
			KEY batchid (batchId)";

		return $b;
	}
}
