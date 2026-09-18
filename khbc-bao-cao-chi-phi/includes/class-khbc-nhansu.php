<?php
/**
 * ĐỌC LƯƠNG TỪ PLUGIN CHẤM CÔNG / NHÂN SỰ (khmatrix.com/quan-tri-cham-cong).
 *
 * Anh Thắng 18/09/2026: *"giờ Lương lấy từ trang nhân sự theo cơ sở"*.
 *
 * =================================================================================================
 * 🔴 GỌI HÀM CỦA CHÍNH BÊN ẤY, KHÔNG TỰ TÍNH LẠI.
 *
 * Plugin Chấm công KHÔNG có bảng lương lưu sẵn — lương dựng tại chỗ từ giờ công × đơn giá, và
 * luật tính khác nhau theo từng người (có `luong_co_ban` thì tính theo tháng, không thì theo giờ),
 * còn cộng thêm ca vắt qua nửa đêm, ca gãy, lượt thiếu giờ, giờ ăn giá khác, khoản cộng / trừ do
 * kế toán nhập. Chép luật ấy sang đây là nhận nuôi một bản sao sẽ lệch dần mỗi lần bên kia sửa —
 * mà lệch về TIỀN LƯƠNG thì không ai phát hiện bằng mắt.
 *
 * Hàm đúng là **`VHCC_BangLuong::dung( $coso, 'YYYY-MM' )`** — chính hàm dựng ra màn "Bảng lương
 * cơ sở" mà cửa hàng trưởng xuất nộp kế toán.
 *
 * ⚠️ TRƯỚC ĐÂY GỌI NHẦM `VHCC_Luong::bang_cong_va_luong()`. Hàm ấy chỉ trả GIỜ VÀO / GIỜ RA thô;
 *    với Khu vui chơi nó còn trả thẳng `coLuong = false`. Kết quả: cơ sở FZ_SC_VIVO_T4 có lương
 *    52.287.040 mà báo cáo ghi "chưa khai giá giờ", còn Posh / JP thì ra 0. (Anh Thắng 18/09/2026
 *    gửi ảnh — hai nút 🔧 và 🔍 trong tệp này là để dò ra đúng chỗ ấy.)
 *
 * =================================================================================================
 * TỔNG CỦA MỘT CƠ SỞ = cộng cột TOTAL SALARY, đúng công thức bên ấy:
 *
 *     z = Lương chính + Tổng các khoản cộng − Tổng các khoản trừ
 *
 * BHXH và "lương giờ thêm" bên ấy CỐ Ý để trống cho kế toán điền (hệ không có dữ liệu), nên số
 * lấy về đây là **lương chính đã gồm các khoản kế toán đã nhập**, chưa gồm BHXH và phụ cấp gõ
 * tay thẳng vào tệp .xlsx.
 *
 * =================================================================================================
 * ⚠️ HAI CÁI BẪY ĐÃ TRÁNH, ĐỌC KỸ TRƯỚC KHI SỬA
 *
 *   1. **Cơ sở PHỤ ghép vào cơ sở CHÍNH.** `VHCC_BangLuong::dung()` của cơ sở chính đã gộp sẵn cả
 *      chùm (`VHCC_Luong::chum_cua`). Liệt kê thêm cơ sở phụ là một phần lương bị đếm HAI LẦN, mà
 *      tổng vẫn ra con số trông bình thường. Nên bỏ qua mọi cơ sở có `ghep_vao()` khác rỗng.
 *
 *   2. **Dòng chưa khai đơn giá có `luongChinh = null`, KHÔNG phải 0.** Bên ấy cố ý để trống —
 *      "ô trống thì người đọc dừng lại hỏi; số 0 thì người đọc tin". Cộng chúng như 0 là bê đúng
 *      cái sai ấy sang đây thành một tổng thiếu tiền mà trông vẫn đủ. Nên loại ra khỏi tổng và
 *      ĐẾM để báo.
 *
 * ⚠️ TỔNG RA 0 THÌ BÁO LÀ CHƯA CÓ, KHÔNG GHI 0. Cơ sở có người chấm công cả tháng thì không thể
 *    hết 0 đồng; ghi 0 là mất nguyên phần lương của cơ sở ấy mà tổng vẫn cộng đẹp.
 *
 * ⚠️ CHỈ ĐỌC. Không bao giờ ghi sang dữ liệu chấm công.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_NhanSu {

	/** Bảng chấm công của plugin kia — chỉ dùng để LIỆT KÊ cơ sở có dữ liệu trong tháng. */
	public static function bang_cham() {
		global $wpdb;
		return $wpdb->prefix . 'vhcc_cham_cong';
	}

	/** Plugin Chấm công có trên site này và đủ hàm để hỏi không. */
	public static function co_nguon() {
		return class_exists( 'VHCC_BangLuong' ) && method_exists( 'VHCC_BangLuong', 'dung' )
			&& class_exists( 'VHCC_Luong' );
	}

	/**
	 * Lương theo cơ sở của MỘT THÁNG.
	 * @return array {ok, ds:[{cua_hang, thanh_tien, so_ngay, co_luong, ghi_chu, bo_phan}], thang}
	 */
	public static function theo_ky( $thang, $nam ) {
		global $wpdb;
		$m = max( 1, min( 12, (int) $thang ) );
		$y = (int) $nam;
		if ( $y < 2000 || $y > 2100 ) { return array( 'ok' => false, 'error' => 'Năm không hợp lệ.' ); }
		if ( ! self::co_nguon() ) {
			return array( 'ok' => false, 'error' => 'Site này chưa cài plugin "Chấm công" (không thấy lớp VHCC_BangLuong).' );
		}
		$tt = sprintf( '%04d-%02d', $y, $m );
		$b  = self::bang_cham();

		/* Danh sách cơ sở lấy từ CHÍNH dữ liệu chấm công của tháng — cơ sở nào tháng này không ai
		   chấm công thì cũng chẳng có lương để lấy, đưa vào chỉ tổ làm dài bảng xem trước. */
		$cs = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT coso FROM $b WHERE coso <> '' AND ngay LIKE %s ORDER BY coso ASC",
			$tt . '-%'
		) );
		if ( null === $cs ) { $cs = array(); }

		$ds = array();
		$co_ghep = method_exists( 'VHCC_Luong', 'ghep_vao' );
		foreach ( (array) $cs as $ten ) {
			$ten = (string) $ten;
			/* 🔴 CƠ SỞ PHỤ THÌ BỎ QUA — KHÔNG THÌ CỘNG ĐÔI LƯƠNG.
			   Bên Chấm công, một cơ sở có thể được GHÉP vào cơ sở khác (`VHCC_Luong::ghep_vao`),
			   và `VHCC_BangLuong::dung()` của cơ sở CHÍNH đã gộp sẵn cả chùm. Liệt kê thêm cơ sở
			   phụ ở đây là đúng một phần lương được đếm hai lần — mà tổng vẫn ra một con số trông
			   bình thường, không ô nào đỏ. */
			if ( $co_ghep && '' !== VHCC_Luong::ghep_vao( $ten ) ) { continue; }

			$r = VHCC_BangLuong::dung( $ten, $tt );
			$bp = ( method_exists( 'VHCC_Luong', 'bo_phan_cua' ) ) ? (string) VHCC_Luong::bo_phan_cua( $ten ) : '';
			if ( ! is_array( $r ) || empty( $r['ok'] ) ) {
				$ds[] = self::dong( $ten, 0, false, isset( $r['error'] ) ? (string) $r['error'] : 'Không dựng được bảng lương.', $bp );
				continue;
			}

			/* ═══════════════════════════════════════════════════════════════════════════════════
			 * TỔNG = cộng cột TOTAL SALARY, đúng công thức của chính bảng lương bên ấy:
			 *     z = Lương chính + Tổng các khoản cộng − Tổng các khoản trừ
			 * (`VHCC_Web` dựng cột 'z' y hệt; BHXH và giờ thêm bên ấy để trống cho kế toán điền
			 * nên không vào đây.)
			 *
			 * 🔴 DÒNG CHƯA KHAI ĐƠN GIÁ CÓ `luongChinh = null` — KHÔNG ĐƯỢC COI LÀ 0.
			 *    Bên ấy cố ý để trống chứ không điền 0, vì "ô trống thì người đọc dừng lại hỏi;
			 *    số 0 thì người đọc tin". Cộng chúng như 0 là bê đúng cái sai ấy sang đây dưới
			 *    dạng một tổng thiếu tiền mà trông vẫn đủ. Nên: bỏ ra khỏi tổng, và ĐẾM để báo.
			 * ═══════════════════════════════════════════════════════════════════════════════════ */
			$tong = 0.0;
			$gio  = 0.0;
			$thieu_gia = 0;
			foreach ( (array) $r['dong'] as $d ) {
				$lc = isset( $d['luongChinh'] ) ? $d['luongChinh'] : null;
				if ( null === $lc ) { $thieu_gia++; continue; }
				$tong += (float) $lc
					+ (float) ( isset( $d['tongCong'] ) ? $d['tongCong'] : 0 )
					- (float) ( isset( $d['tongTru'] ) ? $d['tongTru'] : 0 );
			}
			if ( isset( $r['tong']['gio'] ) ) { $gio = (float) $r['tong']['gio']; }

			$ghi = array();
			if ( $thieu_gia > 0 ) { $ghi[] = $thieu_gia . ' dòng chưa khai đơn giá — số này còn thiếu.'; }
			if ( ! empty( $r['thieu']['gio'] ) ) { $ghi[] = (int) $r['thieu']['gio'] . ' lượt thiếu giờ vào/ra — chưa tính được.'; }
			if ( ! empty( $r['thieu']['congChuan'] ) ) { $ghi[] = 'Chưa khai Số công chuẩn của cơ sở — người ăn lương tháng chưa ra tiền.'; }

			/* Tổng ra 0 thì nói thẳng là CHƯA CÓ, đừng ghi 0 vào báo cáo — xem khối đầu tệp. */
			if ( $tong <= 0 ) {
				$ds[] = self::dong( $ten, 0, false,
					$ghi ? implode( ' ', $ghi ) : 'Bảng lương tháng này chưa ra tiền (chưa khai đơn giá).', $bp );
				continue;
			}
			$d = self::dong( $ten, $tong, true, implode( ' ', $ghi ), $bp );
			$d['so_ngay'] = (int) round( $gio );   /* cột "Số công thực" — để đối chiếu bằng mắt */
			$ds[] = $d;
		}
		$tong = 0;
		foreach ( $ds as $x ) { $tong += $x['thanh_tien']; }
		return array( 'ok' => true, 'ds' => $ds, 'thang' => $tt,
			'so_cua_hang' => count( $ds ), 'tong' => $tong );
	}

	/**
	 * CHẨN ĐOÁN — liệt kê mọi SỐ trong kết quả của một hàm bên Chấm công, kèm đường dẫn.
	 *
	 * Chính nút này đã chỉ ra `bang_cong_va_luong()` không có đồng tiền nào (xem khối đầu tệp).
	 * Giữ lại để lần sau bên ấy đổi hình dạng thì còn chỗ soi, thay vì đoán.
	 *
	 * 🔴 KHÔNG IN CHUỖI. Bảng công có họ tên và CCCD nhân viên — dữ liệu cá nhân, không có việc gì
	 *    phải chạy qua màn hình kế toán hay đi vào ảnh chụp màn hình. Chỉ in SỐ và tên khoá.
	 */
	public static function chuan_doan( $coso, $thang, $nam ) {
		$m  = max( 1, min( 12, (int) $thang ) );
		$y  = (int) $nam;
		$tt = sprintf( '%04d-%02d', $y, $m );
		$r  = null;
		/* Soi CẢ HAI: hàm đang dùng thật, và hàm cũ từng gọi nhầm — để đối chiếu được. */
		if ( class_exists( 'VHCC_BangLuong' ) && method_exists( 'VHCC_BangLuong', 'dung' ) ) {
			$r = array( 'VHCC_BangLuong::dung' => VHCC_BangLuong::dung( (string) $coso, $tt ) );
		}
		if ( class_exists( 'VHCC_Luong' ) && method_exists( 'VHCC_Luong', 'bang_cong_va_luong' ) ) {
			$r = is_array( $r ) ? $r : array();
			$r['VHCC_Luong::bang_cong_va_luong'] = VHCC_Luong::bang_cong_va_luong( (string) $coso, $tt );
		}
		if ( ! is_array( $r ) ) { return array( 'ok' => false, 'error' => 'Site này chưa cài plugin "Chấm công".' ); }
		$so  = array();
		$khs = array();
		self::di( $r, '', $so, $khs );
		usort( $so, function ( $a, $b ) { return $b['gia_tri'] <=> $a['gia_tri']; } );
		return array(
			'ok'      => true,
			'coso'    => (string) $coso,
			'thang'   => $tt,
			'khoa'    => array_slice( $khs, 0, 200 ),
			'so'      => array_slice( $so, 0, 200 ),
			'ghi_chu' => 'Chỉ in SỐ và tên khoá. Mọi chuỗi (họ tên, CCCD…) đã bị giấu.',
		);
	}

	/**
	 * KHÁM PLUGIN CHẤM CÔNG — liệt kê lớp, hàm công khai và bảng dữ liệu của nó.
	 *
	 * Anh Thắng 18/09/2026 bấm 🔧 ở FZ_SC_VIVO_T4: `kieu = tho`, `coLuong = false`, và trong dữ
	 * liệu CHỈ CÓ `tho.rows[].ngay[].date/vao/ra` — giờ vào giờ ra thô, không một con số tiền nào.
	 * Nghĩa là `bang_cong_va_luong()` KHÔNG phải hàm tính ra 52.287.040 trên màn "Bảng lương cơ
	 * sở"; màn ấy tính bằng chỗ khác (giờ công × đơn giá lấy từ sổ đơn giá).
	 *
	 * Tự cộng giờ vào/ra rồi nhân đơn giá ở bên này chính là chép lại luật tính lương — đúng thứ
	 * đã hứa không làm, vì luật của họ còn làm tròn, lượt thiếu giờ, ngày lễ, phụ cấp. Nên việc
	 * cần là TÌM ĐÚNG HÀM của họ mà gọi. Nút này in ra danh sách để tìm.
	 *
	 * ⚠️ CHỈ IN TÊN: tên lớp, tên hàm, tên bảng, số dòng. Không đọc nội dung bảng nào.
	 */
	public static function kham() {
		global $wpdb;
		$lop = array();
		foreach ( get_declared_classes() as $c ) {
			if ( stripos( $c, 'vhcc' ) !== 0 && stripos( $c, 'vhcp' ) !== 0 ) { continue; }
			$ham = array();
			foreach ( get_class_methods( $c ) as $m ) {
				try {
					$rm = new ReflectionMethod( $c, $m );
					if ( ! $rm->isPublic() ) { continue; }
					$ts = array();
					foreach ( $rm->getParameters() as $pr ) { $ts[] = '$' . $pr->getName(); }
					$ham[] = ( $rm->isStatic() ? '::' : '->' ) . $m . '(' . implode( ', ', $ts ) . ')';
				} catch ( Exception $e ) { $ham[] = $m . '(?)'; }
			}
			sort( $ham );
			$lop[] = array( 'lop' => $c, 'ham' => $ham );
		}
		$bang = array();
		$ds = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'vhcc%' ) );
		foreach ( (array) $ds as $t ) {
			$bang[] = array( 'bang' => $t, 'so_dong' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" ) );
		}
		return array( 'ok' => true, 'lop' => $lop, 'bang' => $bang,
			'ghi_chu' => 'Chỉ in TÊN lớp / hàm / bảng và số dòng. Không đọc nội dung bảng nào.' );
	}

	/** Đi khắp mảng, gom số kèm đường dẫn. Chuỗi chỉ ghi nhận tên khoá. */
	private static function di( $x, $duong, &$so, &$khs, $sau = 0 ) {
		if ( $sau > 6 || count( $so ) > 400 ) { return; }
		if ( is_array( $x ) ) {
			$i = 0;
			foreach ( $x as $k => $v ) {
				/* Mảng danh sách nhân viên có thể rất dài — 3 phần tử đầu là đủ thấy hình dạng. */
				if ( is_int( $k ) && $i++ >= 3 ) { break; }
				self::di( $v, '' === $duong ? (string) $k : $duong . '.' . $k, $so, $khs, $sau + 1 );
			}
			return;
		}
		if ( is_int( $x ) || is_float( $x ) ) {
			$so[] = array( 'duong' => $duong, 'gia_tri' => (float) $x );
			return;
		}
		if ( is_bool( $x ) || null === $x ) {
			$so[] = array( 'duong' => $duong, 'gia_tri' => null === $x ? 'null' : ( $x ? 'true' : 'false' ) );
			return;
		}
		/* Chuỗi: chỉ mấy khoá cấu trúc mới in ra, còn lại giấu. */
		$cuoi = strtolower( substr( strrchr( '.' . $duong, '.' ), 1 ) );
		$cho  = array( 'kieu', 'bophan', 'error', 'loai', 'type' );
		$khs[] = array( 'duong' => $duong,
			'gia_tri' => in_array( $cuoi, $cho, true ) ? (string) $x : '(chuỗi)' );
	}

	/** Cùng hình dạng khoá với KHBC_FABi / KHBC_Ghe để giao diện dùng chung một đường. */
	private static function dong( $ten, $tien, $co_luong, $ghi_chu, $bo_phan ) {
		return array(
			'cua_hang'   => $ten,
			'thanh_tien' => (float) $tien,
			'so_ngay'    => 0,
			'co_luong'   => (bool) $co_luong,
			'ghi_chu'    => (string) $ghi_chu,
			'bo_phan'    => (string) $bo_phan,
		);
	}
}
