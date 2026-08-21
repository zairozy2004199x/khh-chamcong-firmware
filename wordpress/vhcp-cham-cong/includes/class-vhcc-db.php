<?php
/**
 * Bảng MySQL của app Chấm công — dữ liệu Ở TRÊN HOSTINGER.
 *
 * Anh Thắng chốt: *"app chấm công là anh lưu trữ tiếp trên hostinger luôn"* + *"nên không cần
 * cầu nối"*. Khác app Hợp đồng (dữ liệu vẫn ở Sheet, WordPress chỉ chuyển tiếp): ở đây MySQL là
 * nguồn sự thật, không có bản sao nào bên Sheet để lệch.
 *
 * ⚠️ CẦU NỐI CHƯA BỎ. Máy Hikvision ngoài cơ sở vẫn đang POST vào /exec của Apps Script và vẫn
 *    đọc Firebase trực tiếp (firmware có 45 chỗ dùng OTA, 12 chỗ đọc /queue). Bỏ cầu nối trước
 *    khi nạp firmware mới cho TỪNG máy = mất chấm công của cơ sở đó, không ai biết cho tới cuối
 *    tháng. Nên thứ tự là: dựng bảng này → Apps Script ghi song song hai nơi vài ngày → đối số
 *    hàng → mới nạp firmware trỏ về WordPress.
 *
 * ---------------------------------------------------------------------------------------------
 * BA CHỖ SƠ ĐỒ NÀY CỐ Ý KHÁC SHEET, vì Sheet làm vậy là do giới hạn của Sheet chứ không phải
 * do nghiệp vụ:
 *
 * 1. `CS_<cơ sở>` là bảng NGANG — mỗi ngày chiếm 5 cột `[Giờ vào][Ảnh vào][Giờ ra][Ảnh ra][Chuẩn]`,
 *    các tháng xếp DỌC thành khối cách nhau 2 hàng trống, nhận ra khối bằng chữ
 *    "Giờ Vào / Checkin" ở cột C. Ở MySQL thành bảng DỌC: một hàng cho một
 *    (cơ sở, mã NV, hậu tố, ngày). Hết chuyện đếm cột, hết chuyện dò khối tháng, và câu
 *    "tháng này cơ sở này ai chấm bao nhiêu" thành một `WHERE` thay vì đọc cả sheet.
 *
 * 2. Giờ lưu bằng SỐ GIÂY TỪ 00:00 (`INT`), không phải `TIME`.
 *    · Vì sao là SỐ, không phải `TIME`: ca đêm chạy trên "trục phẳng" — giờ trước `demDen` được
 *      cộng một ngày để 01:30 nằm SAU 22:00 chứ không phải trước. `TIME` không diễn tả được
 *      chuyện đó mà không thêm cột ngày thứ hai. Cho phép giá trị > 86400 là cố ý.
 *    · Vì sao là GIÂY, không phải PHÚT: `secOf` bên Code.gs so giờ ở mức GIÂY, và ô giờ vào/ra
 *      trong sheet giữ đủ `HH:mm:ss` (chỉ ô "Thời gian trong ngày" mới cắt còn `HH:mm`). Lưu
 *      phút là hai lượt bấm cách nhau 30 giây bị nhập thành một — mà đúng lúc ĐỐI SỐ HÀNG giữa
 *      Sheet và MySQL thì lệch đó không giải thích được. Ba engine lương tính bằng phút, nhưng
 *      phút suy ra từ giây được, giây không suy ra từ phút được.
 *
 * 3. Luật ghi giờ "KHÔNG BAO GIỜ THU HẸP" của `_ghiGioVaoRa` (giữ cặp [sớm nhất, muộn nhất], nạp
 *    lại theo thứ tự nào cũng ra một kết quả) ở đây là `LEAST`/`GREATEST` trong câu upsert, dựa
 *    trên khoá duy nhất `(coso, ngay, ma_nv, hau_to)`. Khoá đó cũng là thứ chặn lỗi hai hàng
 *    trùng — lỗi mà bảng ngang không có cách nào chặn.
 * ---------------------------------------------------------------------------------------------
 *
 * Bảng NGƯỜI DÙNG WordPress vẫn dùng chung với plugin Vận hành chi phí (xem class-vhcc-auth.php)
 * để nhân sự chỉ khai một lần.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_DB {

	const SCHEMA_VERSION = '2.0.0';

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'vhcc_' . $name;
	}

	const NGAY_GIAY = 86400;

	/**
	 * 'HH:mm' hoặc 'HH:mm:ss' -> số giây từ 00:00. NULL = không đọc được / chưa chấm.
	 * Bản dịch của `secOf` bên Code.gs. Thiếu phần giây thì coi là 0 giây, y như `parseInt(p[2])||0`.
	 */
	public static function giay( $gio ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim( (string) $gio ), $m ) ) { return null; }
		return (int) $m[1] * 3600 + (int) $m[2] * 60 + ( isset( $m[3] ) ? (int) $m[3] : 0 );
	}

	/** Ngược lại, đủ giây: 5400 -> '01:30:00'. Đây là dạng ô Giờ vào / Giờ ra của sheet. */
	public static function hhmmss( $giay ) {
		if ( $giay === null || $giay === '' ) { return ''; }
		$g = ( (int) $giay ) % self::NGAY_GIAY;
		if ( $g < 0 ) { $g += self::NGAY_GIAY; }
		return sprintf( '%02d:%02d:%02d', intdiv( $g, 3600 ), intdiv( $g % 3600, 60 ), $g % 60 );
	}

	/** Cắt còn 'HH:mm' — bản dịch của `hhmm` bên Code.gs, dùng cho ô "Thời gian trong ngày". */
	public static function hhmm( $giay ) {
		$s = self::hhmmss( $giay );
		return $s === '' ? '' : substr( $s, 0, 5 );
	}

	/** Số phút (ba engine lương tính bằng phút). Suy từ giây, KHÔNG lưu riêng một cột. */
	public static function phut( $giay ) {
		return ( $giay === null || $giay === '' ) ? null : intdiv( (int) $giay, 60 );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = array();
		foreach ( self::bang() as $ten => $cot ) {
			dbDelta( 'CREATE TABLE ' . self::t( $ten ) . " (\n" . $cot . "\n) $c" );
			$t[] = $ten;
		}
		update_option( 'vhcc_db_version', self::SCHEMA_VERSION );
		return $t;
	}

	/**
	 * Toàn bộ sơ đồ, một chỗ. Trả về mảng tên-bảng => thân CREATE TABLE.
	 * Để đây (chứ không rải trong install) vì phép thử phải soi được sơ đồ mà không cần MySQL.
	 */
	public static function bang() {
		$b = array();

		/* ===== 1. PHIÊN ĐĂNG NHẬP ============================================================ */
		$b['session'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			token CHAR(64) NOT NULL,
			ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			coso VARCHAR(190) NOT NULL DEFAULT '',
			het_han DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY het_han (het_han)";

		/* ===== 2. PHÂN QUYỀN (sheet PhanQuyen) ===============================================
		   `vai_tro` CỐ Ý là VARCHAR chứ không ENUM. Bên Apps Script `saveRole` chỉ `.toUpperCase()`
		   rồi ghi thẳng — chuỗi tự do. Đổi thành ENUM ở đây là làm hỏng dữ liệu đang có: vai trò
		   nào anh Thắng từng gõ sai chính tả sẽ bị MySQL đổi thành '' và người đó mất hết quyền.
		   Chặn danh sách là việc của lớp kiểm tra quyền, không phải của cột. */
		$b['phan_quyen'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			pin VARCHAR(20) NOT NULL,
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			vai_tro VARCHAR(60) NOT NULL DEFAULT '',
			cua_hang TEXT NULL,
			ma_cc_online VARCHAR(40) NOT NULL DEFAULT '',
			coso_cc_online VARCHAR(120) NOT NULL DEFAULT '',
			cap_nhat DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY pin (pin),
			KEY ma_cc_online (ma_cc_online)";

		/* ===== 3. NHÂN VIÊN (sheet NhanVien, 26 cột) =========================================
		   Sheet phải thêm cột MỚI vào CUỐI vì vòng đọc/ghi dùng chỉ số `7 + k`. MySQL gọi theo
		   TÊN cột nên ràng buộc đó biến mất — thêm cột ở đâu cũng được. Đây là chỗ Sheet bắt
		   người ta cẩn thận mà MySQL không cần. */
		$b['nhan_vien'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_nv VARCHAR(40) NOT NULL,
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			cua_hang VARCHAR(120) NOT NULL DEFAULT '',
			pin_may VARCHAR(20) NOT NULL DEFAULT '',
			photo_file_id VARCHAR(190) NOT NULL DEFAULT '',
			trang_thai_dong_bo VARCHAR(40) NOT NULL DEFAULT '',
			cap_nhat DATETIME NULL,
			sdt VARCHAR(40) NOT NULL DEFAULT '',
			ngay_sinh DATE NULL,
			gioi_tinh VARCHAR(20) NOT NULL DEFAULT '',
			cccd VARCHAR(30) NOT NULL DEFAULT '',
			dia_chi VARCHAR(255) NOT NULL DEFAULT '',
			nguoi_lien_he_khan VARCHAR(190) NOT NULL DEFAULT '',
			sdt_khan VARCHAR(40) NOT NULL DEFAULT '',
			chuc_vu VARCHAR(120) NOT NULL DEFAULT '',
			ngay_vao_lam DATE NULL,
			trang_thai_lam_viec VARCHAR(40) NOT NULL DEFAULT '',
			loai_hop_dong VARCHAR(60) NOT NULL DEFAULT '',
			luong_co_ban DECIMAL(12,0) NOT NULL DEFAULT 0,
			so_tai_khoan VARCHAR(60) NOT NULL DEFAULT '',
			ngan_hang VARCHAR(120) NOT NULL DEFAULT '',
			cccd_file_id VARCHAR(190) NOT NULL DEFAULT '',
			hop_dong_file_id VARCHAR(190) NOT NULL DEFAULT '',
			nhiem_vu VARCHAR(60) NOT NULL DEFAULT '',
			coso_phu TEXT NULL,
			pin_dang_nhap VARCHAR(20) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY ma_nv (ma_nv),
			KEY cua_hang (cua_hang),
			KEY cccd (cccd),
			KEY trang_thai_lam_viec (trang_thai_lam_viec)";

		/* ===== 4. MÃ CHẠY SONG SONG (sheet MaSongSong) =======================================
		   Hai mã của cùng một người, PHẢI KHAI chứ không đoán: tên người Việt trùng rất nhiều,
		   đoán sai là gộp lương hai người khác nhau. */
		$b['ma_song_song'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_a VARCHAR(40) NOT NULL,
			ma_b VARCHAR(40) NOT NULL,
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			ly_do VARCHAR(255) NOT NULL DEFAULT '',
			nguoi_khai VARCHAR(190) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cap (ma_a,ma_b),
			KEY ma_b (ma_b)";

		/* ===== 5. CHẤM CÔNG — bảng thay cho toàn bộ họ sheet `CS_<cơ sở>` ====================
		   MỘT hàng = một (cơ sở, mã NV, hậu tố, ngày). Xem ghi chú (1)(2)(3) ở đầu tệp.

		   `hau_to` giữ ĐÚNG kết quả của `_tachMaNhiemVu` bên Code.gs, không tách nhỏ hơn:
		     ''   hàng chính (nhiệm vụ mặc định = Thu Tiền)
		     TT   Thu Tiền (khai rõ)          TG  Trực Ghế (tính theo GIỜ, đơn giá khác)
		     CD   tăng ca / ca đêm (hàng 2)   CT  công tối — CŨ, không ghi mới, giữ để hàng lỡ tạo còn đọc được
		     TC   tăng cường (người của cơ sở khác sang làm)
		   Cố ý KHÔNG bung thành 3 cột boolean: ba cách tính lương đều đọc hậu tố như một nhãn,
		   bung ra là phải sửa cả ba engine mà không được gì.

		   `chuan` = cột "Thời gian trong ngày" của sheet, chuỗi tự do người ta gõ tay. */
		$b['cham_cong'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(120) NOT NULL,
			ngay DATE NOT NULL,
			ma_nv VARCHAR(40) NOT NULL,
			hau_to VARCHAR(4) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			gio_vao_giay INT NULL,
			gio_ra_giay INT NULL,
			anh_vao VARCHAR(190) NOT NULL DEFAULT '',
			anh_ra VARCHAR(190) NOT NULL DEFAULT '',
			chuan VARCHAR(190) NOT NULL DEFAULT '',
			nguon VARCHAR(20) NOT NULL DEFAULT '',
			ghi_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY o (coso,ngay,ma_nv,hau_to),
			KEY thang (coso,ngay),
			KEY nguoi (ma_nv,ngay)";

		/* ===== 6. NHIỆM VỤ THEO NGÀY (sheet ChamCongNhiemVu) ================================= */
		$b['cham_cong_nhiem_vu'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ngay DATE NOT NULL,
			coso VARCHAR(120) NOT NULL,
			ma_nv VARCHAR(40) NOT NULL,
			nhiem_vu VARCHAR(60) NOT NULL DEFAULT '',
			ghi_luc DATETIME NULL,
			nguoi_ghi VARCHAR(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY o (ngay,coso,ma_nv),
			KEY coso_ngay (coso,ngay)";

		/* ===== 7. GHI CHÚ / CỜ CẦN KIỂM (sheet GhiChuChamCong) =============================== */
		$b['ghi_chu'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			flag_id VARCHAR(40) NOT NULL,
			coso VARCHAR(120) NOT NULL DEFAULT '',
			ngay DATE NULL,
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu TEXT NULL,
			nguoi_gan VARCHAR(190) NOT NULL DEFAULT '',
			trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			xu_ly_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY flag_id (flag_id),
			KEY tra (coso,ngay),
			KEY trang_thai (trang_thai)";

		/* ===== 8. TĂNG CƯỜNG (sheet TangCuong) ==============================================
		   Người của cơ sở A sang làm ở cơ sở B. `khoa` = đã CHỐT KỲ, không sửa được nữa. */
		$b['tang_cuong'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ngay DATE NOT NULL,
			coso_den VARCHAR(120) NOT NULL,
			coso_goc VARCHAR(120) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL,
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			khoa TINYINT(1) NOT NULL DEFAULT 0,
			nguoi_khai VARCHAR(190) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY o (ngay,coso_den,ma_nv),
			KEY coso_den (coso_den,ngay)";

		/* ===== 9. QUY ĐỔI CƠ SỞ (sheet QuyDoiCoSo) ==========================================
		   Tên cơ sở trên máy ≠ tên cơ sở trên sheet. Bảng này ánh xạ. */
		$b['quy_doi_coso'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			tu VARCHAR(190) NOT NULL,
			den VARCHAR(190) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY tu (tu)";

		/* ===== 10. BỘ PHẬN THEO CƠ SỞ (sheet BoPhanCoSo) ===================================== */
		$b['bo_phan_coso'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(120) NOT NULL,
			bo_phan VARCHAR(120) NOT NULL DEFAULT '',
			theo_gio TINYINT(1) NOT NULL DEFAULT 0,
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY o (coso,bo_phan),
			KEY coso (coso)";

		/* ===== 11. MÁY CHẤM CÔNG (sheet MayChamCong) ========================================
		   ⚠️ Bảng này KHÔNG thay Firebase được. Firmware đọc /queue, /hb, /roster, /ota TRỰC TIẾP
		      từ Firebase RTDB — dựng bảng MySQL rồi tưởng đã điều khiển được máy là máy hoá điếc.
		      Bảng này chỉ là BẢN GHI phía web cho tới khi firmware được nạp bản trỏ về WordPress. */
/* Cột đúng theo MAY_H của Code.gs. Khoá nghiệp vụ là SERIAL ĐẦU ĐỌC, không phải MAC — thay bo
		   ESP32 thì đầu đọc vẫn là đầu đọc đó. Nhưng KHÔNG đặt UNIQUE trên serial: firmware nhớ serial
		   trong NVS và khai lại serial CŨ khi chưa với tới đầu đọc mới, nên hai dòng cùng serial là
		   chuyện có thật, phải giữ được cả hai cho người ta xử chứ không phải để MySQL chặn.
		   `ghi_chu` là nơi ghi dấu khi phần cứng đổi — CHỈ ghi dấu, KHÔNG tự sửa: "thay bo" và "mang
		   bo sang cửa hàng khác" nhìn từ máy chủ giống hệt nhau, đoán sai là chấm công cửa hàng mới
		   chảy vào cơ sở cũ, sai người sai lương mà không ai thấy. */
		$b['may'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			serial VARCHAR(120) NOT NULL DEFAULT '',
			mac VARCHAR(40) NOT NULL DEFAULT '',
			cua_hang VARCHAR(120) NOT NULL DEFAULT '',
			model VARCHAR(120) NOT NULL DEFAULT '',
			ten_tu_khai VARCHAR(190) NOT NULL DEFAULT '',
			lan_cuoi_thay DATETIME NULL,
			ghi_chu TEXT NULL,
			sim VARCHAR(60) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY serial (serial),
			KEY mac (mac),
			KEY cua_hang (cua_hang)";

		/* ===== 12. CHẤM CÔNG CHỜ GẮN (sheet ChamCongChoGan) =================================
		   Máy gửi về một mã KHÔNG có trong hồ sơ -> không được bỏ, phải giữ ở đây chờ người gắn.
		   Bỏ là mất công của người thật chỉ vì hồ sơ chưa khai. */
		$b['cho_gan'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			nhan_luc DATETIME NULL,
			serial VARCHAR(120) NOT NULL DEFAULT '',
			mac VARCHAR(40) NOT NULL DEFAULT '',
			ten_tu_khai VARCHAR(190) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			thoi_diem VARCHAR(40) NOT NULL DEFAULT '',
			co_anh TINYINT(1) NOT NULL DEFAULT 0,
			da_chuyen VARCHAR(120) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY may (serial,mac),
			KEY da_chuyen (da_chuyen),
			KEY nhan_luc (nhan_luc)";

		/* ===== 13. HÀNG ĐỢI ĐẨY ẢNH / LỆNH MÁY (sheet Queue) ================================= */
		$b['queue'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			op_id VARCHAR(40) NOT NULL,
			action VARCHAR(40) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			pin_may VARCHAR(20) NOT NULL DEFAULT '',
			photo_file_id VARCHAR(190) NOT NULL DEFAULT '',
			cua_hang VARCHAR(120) NOT NULL DEFAULT '',
			trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			tao_luc DATETIME NULL,
			ket_qua TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY op_id (op_id),
			KEY tra (cua_hang,trang_thai)";

		/* ===== 14. LỊCH CÔNG VIỆC + XIN ĐỔI LỊCH (LichCongViec, DoiLichCV) =================== */
		$b['lich_cv'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(120) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NOT NULL,
			ca VARCHAR(60) NOT NULL DEFAULT '',
			viec TEXT NULL,
			nguoi_xep VARCHAR(190) NOT NULL DEFAULT '',
			cap_nhat DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY o (coso,ngay,ma_nv,ca),
			KEY coso_ngay (coso,ngay)";

		$b['doi_lich_cv'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_yc VARCHAR(40) NOT NULL,
			coso VARCHAR(120) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			ngay DATE NULL,
			ca VARCHAR(60) NOT NULL DEFAULT '',
			viec_moi TEXT NULL,
			doi_sang_ngay DATE NULL,
			ly_do TEXT NULL,
			trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			nguoi_xin VARCHAR(190) NOT NULL DEFAULT '',
			nguoi_duyet VARCHAR(190) NOT NULL DEFAULT '',
			luc_xin DATETIME NULL,
			luc_duyet DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_yc (ma_yc),
			KEY tra (coso,trang_thai)";

		/* ===== 15. YÊU CẦU NHÂN VIÊN (sheet YeuCauNV) ======================================== */
		$b['yeu_cau_nv'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			ma_yc VARCHAR(40) NOT NULL,
			loai VARCHAR(60) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ho_ten VARCHAR(190) NOT NULL DEFAULT '',
			coso VARCHAR(120) NOT NULL DEFAULT '',
			noi_dung LONGTEXT NULL,
			trang_thai VARCHAR(30) NOT NULL DEFAULT '',
			nguoi_xin VARCHAR(190) NOT NULL DEFAULT '',
			nguoi_duyet VARCHAR(190) NOT NULL DEFAULT '',
			luc_xin DATETIME NULL,
			luc_duyet DATETIME NULL,
			ghi_chu TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ma_yc (ma_yc),
			KEY tra (coso,trang_thai)";

		/* ===== 16. SỐ NGÀY CÔNG CHUẨN CỦA THÁNG (sheet VP_NgayCong) =========================
		   Mẫu số quy lương tháng ra tiền một công: `round(lcb * tong_cong / ngay_cong)`.
		   ⚠️ KHÔNG có mặc định, KHÔNG mượn số của tháng khác. Chưa khai thì bảng hiện "—" và báo
		      thiếu. Đoán mẫu số là sai tiền của MỌI người cùng lúc, mà bảng vẫn có số nên chẳng
		      ai nghi — nên cột này để NULL được là CỐ Ý, `0`/`DEFAULT 26` là bẫy. */
		$b['vp_ngay_cong'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			coso VARCHAR(120) NOT NULL,
			thang CHAR(7) NOT NULL,
			ngay_cong DECIMAL(5,2) NULL,
			nguoi_khai VARCHAR(190) NOT NULL DEFAULT '',
			cap_nhat DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY o (coso,thang)";

		/* ===== 17. CÀI ĐẶT (sheet CaiDat + Script Properties) ===============================
		   Gộp cả hai vào một bảng khoá-giá trị. Bên Apps Script chúng nằm hai chỗ vì Script
		   Property không hiện ra cho anh Thắng sửa, còn sheet CaiDat thì hiện — ở WordPress cả
		   hai đều sửa được trong trang quản trị nên không cần chia.
		   Đây là nơi ở của: MTD_DON_GIA, MTD_NGAY_LE, MTD_CO_SO_THEO_GIO, VP_CONG_CFG,
		   LUONG_DS_CO_SO, WAGE_MAP. `gia_tri` là JSON.
		   ⚠️ Bí mật (khoá Firebase, mật khẩu máy) KHÔNG vào đây — vào wp-config.php. Bảng này
		      đọc được từ app; wp-config thì không. */
		$b['cai_dat'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			khoa VARCHAR(120) NOT NULL,
			gia_tri LONGTEXT NULL,
			cap_nhat DATETIME NULL,
			nguoi_sua VARCHAR(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY khoa (khoa)";

		/* ===== 18. NHẬT KÝ TRA PIN (sheet NhatKyTraPin) =====================================
		   Ghi CCCD đã CHE và KHÔNG BAO GIỜ ghi PIN. Nhật ký là chỗ rò rỉ dễ nhất: người xem được
		   nhật ký thường nhiều hơn người được xem PIN. */
		$b['nhat_ky_tra_pin'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			luc DATETIME NOT NULL,
			cccd_che VARCHAR(40) NOT NULL DEFAULT '',
			ket_qua VARCHAR(30) NOT NULL DEFAULT '',
			ma_nv VARCHAR(40) NOT NULL DEFAULT '',
			ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY luc (luc)";

		/* ===== 19. ĐẾM NHỊP ĐỘ CHỐNG DÒ (thay CacheService) =================================
		   Bên Apps Script ba bộ đếm này sống trong CacheService: PIN sai (PIN_DO_NGUONG=8 lần /
		   10 phút, phạt tới 8 giây), đổi PIN đụng mã người khác (5 lần / 10 phút), tra PIN theo
		   CCCD (5 lần mỗi số + 30 lần toàn hệ thống / 10 phút).
		   ⚠️ CỐ Ý là BẢNG chứ không phải transient. Transient nằm trên object cache — cache bị
		      xoá hoặc đầy là bộ đếm về 0, tức hình phạt tự bỏ đúng lúc kẻ dò đang dò. Đếm cho
		      bảo mật thì phải nằm chỗ không ai xoá hộ.
		   Chỉ đếm LẦN TRƯỢT; đăng nhập đúng KHÔNG xoá bộ đếm (đúng như Code.gs) — nếu không thì
		   kẻ dò chỉ cần một PIN đúng bất kỳ là xoá sạch tiền sử. */
		$b['nhip_do'] = "
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			khoa VARCHAR(190) NOT NULL,
			so_lan INT NOT NULL DEFAULT 0,
			cua_so_tu DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY khoa (khoa),
			KEY cua_so_tu (cua_so_tu)";

		return $b;
	}

	public static function rows( $sql ) {
		global $wpdb;
		$r = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $r ) ? $r : array();
	}
}
