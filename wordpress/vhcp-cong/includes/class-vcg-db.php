<?php
/**
 * VCG_DB — bảng dữ liệu của bản chấm công dựng mới, và luật gộp giờ.
 *
 * BA BẢNG, KHÔNG PHẢI MỘT
 *   cong_nv        hồ sơ người   — một dòng một người
 *   cong_nv_donvi  gán cơ sở     — một dòng một cặp người–đơn vị
 *   cong_ngay      chấm công     — một dòng một người một ngày một cơ sở
 *
 * Tách `cong_nv_donvi` ra riêng vì anh Thắng 25/08/2026: *"nhân viên có thể làm ở nhiều gian
 * qua cột đơn vị làm việc"*. Nhét cơ sở vào một ô trong `cong_nv` là mỗi người còn đúng một
 * cơ sở, và tệp thật có 21 người mất dữ liệu ngay lần nạp đầu.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { if ( ! defined( 'VCG_TEST' ) ) { exit; } }

class VCG_DB {

	const PHIEN_BAN = '1.0.0';

	public static function bang( $ten ) {
		global $wpdb;
		return $wpdb->prefix . 'cong_' . $ten;
	}

	/**
	 * Luật gộp GIỜ VÀO / GIỜ RA — chỗ duy nhất quyết định TIỀN LƯƠNG.
	 *
	 * Ô giờ vào và giờ ra là cặp **[sớm nhất, muộn nhất] của ngày**, và **chỉ được NỚI RỘNG,
	 * không bao giờ THU HẸP**.
	 *
	 * 🔴 Vì sao luật này bắt buộc: anh Thắng nạp CSV, và người ta nạp lại. Nạp lại cả tháng,
	 *    nạp đè tệp cũ, nạp hai tệp chồng lấn nhau, nạp lúc nửa chừng rồi nạp tiếp. Nếu phép
	 *    gộp không cho ra CÙNG MỘT kết quả bất kể thứ tự và số lần, thì mỗi lần nạp lại là một
	 *    bảng công khác, và không ai biết bảng nào đúng. Luật "chỉ nới rộng" làm phép gộp không
	 *    phụ thuộc thứ tự — nạp kiểu gì cũng ra một kết quả.
	 *
	 * Tách thành hàm THUẦN, không đụng cơ sở dữ liệu, để thử được bằng con số.
	 * Xem tools/test/kiem-nap-cong.php.
	 *
	 * @param int|null $cu_vao  giờ vào đang có (giây), null nếu chưa có
	 * @param int|null $cu_ra   giờ ra đang có (giây), null nếu chưa có
	 * @param int|null $m_vao   giờ vào của lượt mới
	 * @param int|null $m_ra    giờ ra của lượt mới
	 * @return array{vao:?int,ra:?int,doi:bool}
	 */
	public static function gop_gio( $cu_vao, $cu_ra, $m_vao, $m_ra ) {
		/* Gom mọi mốc giờ có mặt rồi lấy sớm nhất / muộn nhất. Cách này đúng theo định nghĩa của
		   hai ô đó, và tự nhiên không phụ thuộc thứ tự — không cần bốn nhánh if lồng nhau, cũng
		   không có nhánh nào để quên. */
		$moc = array();
		foreach ( array( $cu_vao, $cu_ra, $m_vao, $m_ra ) as $g ) {
			if ( null !== $g ) { $moc[] = (int) $g; }
		}
		if ( ! $moc ) { return array( 'vao' => null, 'ra' => null, 'doi' => false ); }
		$vao = min( $moc );
		$ra  = max( $moc );
		/* Một mốc duy nhất trong ngày -> đó là GIỜ VÀO, ô giờ ra còn trống. Đặt bằng nhau là
		   sinh ra ca "làm 0 phút" trông như đã ra ca, và bảng công cộng thiếu một buổi. */
		if ( $vao === $ra ) { $ra = null; }
		$doi = ( $vao !== $cu_vao ) || ( $ra !== $cu_ra );
		return array( 'vao' => $vao, 'ra' => $ra, 'doi' => $doi );
	}

	/**
	 * Trải phẳng trục thời gian cho CA ĐÊM.
	 *
	 * Giờ sau nửa đêm mà thuộc ca hôm trước thì cộng 86400 giây TRƯỚC khi vào `gop_gio`. Nhờ vậy
	 * 06:00 hôm sau nằm SAU 22:00 hôm trước trên cùng một trục, và một luật chạy đúng cho cả ca
	 * ngày lẫn ca đêm.
	 *
	 * Bản Apps Script phải viết hẳn một hàm ghi riêng cho ca đêm (`_ghiGioDem`) vì ô sheet giữ
	 * chuỗi 'HH:mm:ss' nên 06:00 luôn "sớm hơn" 22:00 — ca đêm bị đảo thành 16 tiếng ban ngày.
	 * Ở đây giờ là SỐ GIÂY nên chỉ cần trải trục. Một luật thay vì hai, đúng điều `Code.gs` tự
	 * cảnh báo: hai bản tính giờ lệch nhau là lệch tiền lương.
	 *
	 * @param int  $giay   giờ trong ngày
	 * @param int  $moc_dem  giờ dưới mốc này thì coi là thuộc ca đêm hôm trước (mặc định 06:00)
	 */
	public static function trai_dem( $giay, $moc_dem = 21600 ) {
		/* ⚠️ NHỎ HƠN HOẶC BẰNG, không phải nhỏ hơn. Mốc 06:00 CHẴN chính là giờ ra ca đêm hay
		   gặp nhất — ca 22:00–06:00. Dùng dấu nhỏ hơn thì đúng cái giờ phổ biến nhất lại không
		   được trải, và ca đêm đó bị tính thành 16 tiếng ban ngày. Phép thử bắt được đúng chỗ
		   này; biên là nơi lỗi thích nấp. */
		return ( null !== $giay && $giay <= $moc_dem ) ? ( (int) $giay + 86400 ) : $giay;
	}

	/** Lệnh dựng bảng. Gọi qua dbDelta nên phải theo đúng khuôn WordPress đòi. */
	public static function lenh_tao( $charset ) {
		$nv    = self::bang( 'nv' );
		$dv    = self::bang( 'nv_donvi' );
		$ngay  = self::bang( 'ngay' );
		return array(
			/* Hồ sơ người. `ma_nv` là khoá chính: một người một dòng.
			   Mã dạng chuỗi dài (MNNV2KVC0106) và có cả mã ngắn tự đặt (TP15, TUTP02) trong
			   cùng một cơ sở — nên VARCHAR rộng, tuyệt đối không ép về số. */
			"CREATE TABLE $nv (
				ma_nv VARCHAR(64) NOT NULL,
				ho_ten VARCHAR(191) NOT NULL DEFAULT '',
				mien VARCHAR(64) NOT NULL DEFAULT '',
				phong_ban VARCHAR(128) NOT NULL DEFAULT '',
				loai_hinh VARCHAR(64) NOT NULL DEFAULT '',
				gioi_tinh VARCHAR(16) NOT NULL DEFAULT '',
				ngay_sinh DATE NULL,
				sdt VARCHAR(32) NOT NULL DEFAULT '',
				cccd VARCHAR(32) NOT NULL DEFAULT '',
				ngay_cap_cccd DATE NULL,
				loai_hop_dong VARCHAR(32) NOT NULL DEFAULT '',
				tao_luc VARCHAR(32) NOT NULL DEFAULT '',
				sua_luc DATETIME NULL,
				PRIMARY KEY (ma_nv),
				KEY ho_ten (ho_ten),
				KEY cccd (cccd)
			) $charset;",

			/* Gán cơ sở. Khoá là CẶP mã+đơn vị — đó là chỗ giữ được "một người nhiều gian". */
			"CREATE TABLE $dv (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ma_nv VARCHAR(64) NOT NULL,
				don_vi VARCHAR(191) NOT NULL,
				tinh_thanh VARCHAR(64) NOT NULL DEFAULT '',
				tao_luc VARCHAR(32) NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				UNIQUE KEY nguoi_donvi (ma_nv, don_vi),
				KEY don_vi (don_vi)
			) $charset;",

			/* Chấm công. Khoá duy nhất là bộ ba cơ sở+ngày+người — nạp lại cùng một tệp thì
			   cập nhật đúng dòng cũ chứ không đẻ thêm dòng mới. Cùng lý do với luật nới rộng:
			   nạp lại phải ra cùng một bảng công. */
			"CREATE TABLE $ngay (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				co_so VARCHAR(64) NOT NULL,
				ngay DATE NOT NULL,
				ma_nv VARCHAR(64) NOT NULL,
				ho_ten VARCHAR(191) NOT NULL DEFAULT '',
				vao INT NULL,
				ra INT NULL,
				anh_vao TEXT NULL,
				anh_ra TEXT NULL,
				nguon VARCHAR(16) NOT NULL DEFAULT 'csv',
				sua_luc DATETIME NULL,
				PRIMARY KEY (id),
				UNIQUE KEY mot_luot (co_so, ngay, ma_nv),
				KEY theo_nguoi (ma_nv, ngay),
				KEY theo_co_so (co_so, ngay)
			) $charset;",
		);
	}
}
