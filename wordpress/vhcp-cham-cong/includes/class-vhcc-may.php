<?php
/**
 * MÁY CHẤM CÔNG + CẬP NHẬT FIRMWARE — màn ở WordPress, dữ liệu vẫn ở Firebase.
 *
 * =============================================================================================
 * VÌ SAO ĐI QUA CẦU NỐI CHỨ KHÔNG NÓI TRỰC TIẾP VỚI FIREBASE
 * =============================================================================================
 * Anh Thắng chốt Firebase giữ nguyên cho phần điều khiển máy. Nên lớp này KHÔNG có một dòng nào
 * gọi Firebase: nó gọi 22 hàm của Apps Script qua cầu nối, và Apps Script vẫn là nơi DUY NHẤT
 * ghi Firebase. Ba cái được từ chuyện đó:
 *   · một người ghi Firebase, không có hai người tranh nhau;
 *   · khoá Firebase không phải sao thêm một bản sang wp-config (mỗi bản sao là một chỗ lộ);
 *   · sheet `MayChamCong` vẫn là NGUỒN THẬT của "máy nào thuộc cơ sở nào".
 *
 * =============================================================================================
 * 🔴 CHỖ NGUY NHẤT, VÀ NÓ DO CHÍNH VIỆC NÀY SINH RA — ĐỌC KỸ
 * =============================================================================================
 * Bây giờ có HAI nơi trả lời câu "máy này thuộc cơ sở nào":
 *      sheet `MayChamCong`   -> `doPost` của Apps Script dùng, để ghi vào sheet `CS_<cơ sở>`
 *      bảng MySQL `may`      -> cổng nhận của WordPress dùng, để ghi vào bảng `cham_cong`
 *
 * Trong giai đoạn ghi song song, MỘT lượt bấm đi qua CẢ HAI. Nếu hai nơi trả lời KHÁC NHAU thì
 * cùng một lần bấm rơi vào HAI cơ sở khác nhau — và không có gì báo, vì mỗi bên đều thấy mình
 * đúng. Đến cuối tháng thì một cơ sở thừa công, một cơ sở thiếu công.
 *
 * Nên luật ở đây là: **SHEET LÀ NGUỒN THẬT, MySQL PHẢI THEO.**
 *   · `gan_may()` gọi Apps Script ghi sheet TRƯỚC. Sheet ghi trượt thì KHÔNG chạm MySQL —
 *     thà cả hai đều cũ còn hơn hai bên lệch nhau.
 *   · Sheet ghi xong mới soi lại MySQL cho khớp.
 *   · `doi_chieu()` là lưới an toàn: kéo danh sách máy từ sheet về, so với MySQL, chỉ ra từng
 *     chỗ lệch. Chạy trước khi tin bảng lương của một tháng.
 *
 * ⚠️ Máy KHÔNG có trong MySQL `may` thì cổng nhận đưa lượt bấm vào `cho_gan` — vô hại, giữ lại
 *    chờ người gán. Ca NGUY là máy CÓ trong cả hai mà cơ sở KHÁC nhau. `doi_chieu()` tách rõ
 *    hai ca đó, vì cách xử khác nhau hẳn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_May {

	/** PIN admin để cầu nối chuyển xuống Apps Script (mấy hàm đó tự kiểm `u.isAdmin`). */
	public static function pin() {
		return defined( 'VHCC_PIN_ADMIN' ) ? (string) VHCC_PIN_ADMIN : (string) get_option( 'vhcc_pin_admin', '' );
	}

	/** Gọi một hàm của Apps Script, PIN luôn là tham số đầu — đúng khuôn mọi hàm trong Code.gs. */
	public static function goi( $fn, $them = array() ) {
		$pin = self::pin();
		if ( '' === $pin ) {
			return array( 'ok' => false, 'error' => 'Chưa khai PIN admin để gọi app chấm công. '
				. 'Thêm vào wp-config.php: define( \'VHCC_PIN_ADMIN\', \'…\' );' );
		}
		return VHCC_CauNoi::goi( $fn, array_merge( array( $pin ), array_values( (array) $them ) ) );
	}

	// ======================================================================= chỉ đọc

	public static function ds_may()        { return self::goi( 'getDanhSachMay' ); }
	public static function trang_thai()    { return self::goi( 'getMachineStatus' ); }
	public static function roster( $tram ) { return self::goi( 'getMachineRoster', array( $tram ) ); }
	public static function chan_doan( $tram ) { return self::goi( 'chanDoanMay', array( $tram ) ); }
	public static function queue( $tram )  { return self::goi( 'getQueueMay', array( $tram ) ); }
	public static function hang_tai_lai()  { return self::goi( 'getHangDoiTaiLai' ); }
	public static function khoi_test( $coso ) { return self::goi( 'xemKhoiTest', array( $coso ) ); }
	public static function fw_moi_nhat()   { return self::goi( 'getFwMoiNhat' ); }
	public static function ota_dang_dat()  { return self::goi( 'getOtaTarget' ); }

	// ======================================================================= ghi

	/**
	 * GÁN máy vào cơ sở. Sheet trước, MySQL theo sau — xem khối 🔴 ở đầu tệp.
	 */
	public static function gan_may( $hang, $coso ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu tên cơ sở.' ); }
		$r = self::goi( 'ganMayVaoCuaHang', array( $hang, $coso ) );
		if ( empty( $r['ok'] ) ) { return $r; }              // sheet trượt -> KHÔNG chạm MySQL
		$r['mysql'] = self::soi_lai_mysql();
		return $r;
	}

	public static function bo_gan( $hang ) {
		$r = self::goi( 'boGanMay', array( $hang ) );
		if ( ! empty( $r['ok'] ) ) { $r['mysql'] = self::soi_lai_mysql(); }
		return $r;
	}

	public static function luu_sim( $hang, $sim )  { return self::goi( 'luuSimMay', array( $hang, $sim ) ); }
	public static function yeu_cau_quet( $tram )   { return self::goi( 'requestMachineScan', array( $tram ) ); }
	public static function xoa_lenh( $tram, $id )  { return self::goi( 'xoaLenhQueue', array( $tram, $id ) ); }
	public static function xoa_tai_lai( $id )      { return self::goi( 'xoaLenhTaiLai', array( $id ) ); }
	public static function dung_tai_lai( $tram )   { return self::goi( 'dungTaiLai', array( $tram ) ); }
	public static function don_khoi_test( $coso, $that ) {
		return self::goi( 'donKhoiTest', array( $coso, $that ? true : false ) );
	}

	/**
	 * Link .bin có dùng được cho OTA không.
	 *
	 * ⚠️ ĐÂY LÀ CHỖ HAI LỚP GÁC KIA KHÔNG CHE. Cầu nối kiểm khoá, Apps Script kiểm quyền Admin —
	 *    nhưng không ai kiểm cái link. Module 4G A7680C chết ở khoảng 532 ký tự, mà link release
	 *    của GitHub trả HTTP 302 rồi chuyển hướng dài 943 ký tự (ghi trong .github/workflows).
	 *    Đẩy một link như vậy = mọi máy 4G KHÔNG BAO GIỜ tải được bản mới, tức MẤT LUÔN đường sửa
	 *    từ xa của cả chuỗi và phải đi từng cửa hàng cắm USB. Sai một lần là đi 26 chỗ.
	 *
	 * Nên chỉ nhận link TRẢ THẲNG 200, không chuyển hướng, và ngắn.
	 */
	public static function ota_url_hop_le( $url ) {
		$u = trim( (string) $url );
		if ( '' === $u ) { return 'Thiếu link .bin.'; }
		if ( 0 !== strpos( $u, 'https://' ) ) { return 'Link phải bắt đầu bằng https://'; }
		if ( strlen( $u ) > 300 ) {
			return 'Link dài ' . strlen( $u ) . ' ký tự. Module 4G chết ở khoảng 532 ký tự và link '
				. 'chuyển hướng còn dài hơn nữa — dùng link raw ngắn.';
		}
		if ( '.bin' !== substr( $u, -4 ) ) { return 'Link phải kết thúc bằng .bin'; }
		/* Link release của GitHub (`/releases/download/…`) trả 302 -> chuyển hướng ~943 ký tự ->
		   máy 4G chết. Bản build đẩy .bin lên nhánh `bin` chính là để có link raw không chuyển
		   hướng — nên chặn thẳng dạng release, đừng để ai dán vào rồi cả tuần sau mới biết. */
		if ( false !== strpos( $u, '/releases/download/' ) ) {
			return 'Đây là link RELEASE của GitHub — nó trả HTTP 302 rồi chuyển hướng dài ~943 ký tự, '
				. 'máy 4G sẽ KHÔNG BAO GIỜ tải được. Dùng link raw của nhánh "bin" '
				. '(raw.githubusercontent.com/…/bin/…), nó trả 200 thẳng.';
		}
		return '';                                            // rỗng = dùng được
	}

	/**
	 * ĐẨY CẬP NHẬT FIRMWARE cho cả chuỗi. Kiểm link TRƯỚC khi gọi Apps Script.
	 * `$xac_nhan` phải đúng chữ để không ai bấm nhầm — đây là lệnh tác động lên MỌI máy.
	 */
	public static function dat_ota( $ver, $url, $xac_nhan ) {
		if ( 'DONG Y' !== trim( (string) $xac_nhan ) ) {
			return array( 'ok' => false, 'error' => 'Đây là lệnh nạp firmware cho MỌI máy trong chuỗi. '
				. 'Gõ đúng chữ "DONG Y" vào ô xác nhận nếu thật sự muốn đẩy.' );
		}
		$ver = trim( (string) $ver );
		if ( '' === $ver ) { return array( 'ok' => false, 'error' => 'Thiếu số phiên bản.' ); }
		$loi = self::ota_url_hop_le( $url );
		if ( '' !== $loi ) { return array( 'ok' => false, 'error' => $loi ); }
		return self::goi( 'setOtaTarget', array( $ver, trim( (string) $url ) ) );
	}

	public static function go_ota() { return self::goi( 'clearOtaTarget' ); }

	// ======================================================================= chống lệch

	/**
	 * ĐỐI CHIẾU sheet `MayChamCong` với bảng MySQL `may`.
	 *
	 * Trả ba nhóm, và ba nhóm này xử khác nhau hẳn — đừng gộp:
	 *   `lech`     máy có ở CẢ HAI mà CƠ SỞ KHÁC NHAU  -> NGUY: một lượt bấm rơi vào hai cơ sở.
	 *   `thieu`    có trong sheet, chưa có trong MySQL   -> vô hại: cổng nhận giữ vào `cho_gan`.
	 *   `du`       có trong MySQL, không có trong sheet   -> máy cũ đã bỏ, hoặc gõ tay vào MySQL.
	 */
	public static function doi_chieu() {
		global $wpdb;
		$r = self::ds_may();
		if ( empty( $r['ok'] ) ) { return $r; }
		$sheet = array();
		foreach ( (array) ( is_array( $r['data'] ) ? $r['data'] : array() ) as $m ) {
			$serial = isset( $m['serial'] ) ? trim( (string) $m['serial'] ) : '';
			$mac    = isset( $m['mac'] ) ? trim( (string) $m['mac'] ) : '';
			$khoa   = '' !== $serial ? strtolower( $serial ) : strtolower( $mac );
			if ( '' === $khoa ) { continue; }
			$sheet[ $khoa ] = array( 'serial' => $serial, 'mac' => $mac,
				'coso' => VHCC_NhanSu::chuan_coso( isset( $m['cuaHang'] ) ? $m['cuaHang'] : ( isset( $m['coSo'] ) ? $m['coSo'] : '' ) ) );
		}
		$my = array();
		foreach ( VHCC_DB::rows( 'SELECT serial, mac, cua_hang FROM ' . VHCC_DB::t( 'may' ) ) as $m ) {
			$khoa = '' !== trim( (string) $m['serial'] ) ? strtolower( trim( $m['serial'] ) ) : strtolower( trim( (string) $m['mac'] ) );
			if ( '' === $khoa ) { continue; }
			$my[ $khoa ] = array( 'serial' => $m['serial'], 'mac' => $m['mac'],
				'coso' => VHCC_NhanSu::chuan_coso( $m['cua_hang'] ) );
		}

		$lech = array();
		$thieu = array();
		$du = array();
		foreach ( $sheet as $k => $s ) {
			if ( ! isset( $my[ $k ] ) ) { $thieu[] = $s; continue; }
			if ( strtolower( $s['coso'] ) !== strtolower( $my[ $k ]['coso'] ) ) {
				$lech[] = array( 'serial' => $s['serial'], 'mac' => $s['mac'],
					'sheet' => $s['coso'], 'mysql' => $my[ $k ]['coso'] );
			}
		}
		foreach ( $my as $k => $m ) {
			if ( ! isset( $sheet[ $k ] ) ) { $du[] = $m; }
		}
		return array( 'ok' => true, 'lech' => $lech, 'thieu' => $thieu, 'du' => $du,
			'soSheet' => count( $sheet ), 'soMysql' => count( $my ) );
	}

	/**
	 * Soi lại MySQL cho khớp sheet. CHỈ đi một chiều: sheet -> MySQL.
	 * ⚠️ KHÔNG bao giờ ghi ngược MySQL -> sheet: sheet là nguồn thật, và ghi ngược là hai nơi
	 *    cùng ghi rồi không ai biết bên nào mới.
	 */
	public static function soi_lai_mysql() {
		global $wpdb;
		$d = self::doi_chieu();
		if ( empty( $d['ok'] ) ) { return $d; }
		$sua = 0;
		$them = 0;
		foreach ( $d['lech'] as $x ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . VHCC_DB::t( 'may' ) . ' SET cua_hang=%s WHERE LOWER(serial)=LOWER(%s)'
				. ' OR (serial=%s AND LOWER(mac)=LOWER(%s))',
				$x['sheet'], $x['serial'], '', $x['mac'] ) );
			$sua++;
		}
		foreach ( $d['thieu'] as $x ) {
			$wpdb->insert( VHCC_DB::t( 'may' ), array( 'serial' => $x['serial'], 'mac' => $x['mac'],
				'cua_hang' => $x['coso'], 'ghi_chu' => 'soi từ sheet MayChamCong lúc ' . current_time( 'mysql' ) ) );
			$them++;
		}
		/* `du` thì KHÔNG xoá: máy có trong MySQL mà không có trong sheet có thể là máy vừa gửi lượt
		   đầu tiên và sheet chưa kịp có dòng. Xoá là mất chỗ gán. Chỉ báo ra. */
		return array( 'ok' => true, 'sua' => $sua, 'them' => $them, 'du' => count( $d['du'] ) );
	}
}
