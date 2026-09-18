<?php
/**
 * ĐỌC LƯƠNG TỪ PLUGIN CHẤM CÔNG / NHÂN SỰ (khmatrix.com/quan-tri-cham-cong).
 *
 * Anh Thắng 18/09/2026: *"giờ Lương lấy từ trang nhân sự theo cơ sở"*.
 *
 * =================================================================================================
 * 🔴 GỌI HÀM CỦA CHÍNH BÊN ẤY, KHÔNG TỰ TÍNH LẠI.
 *
 * Plugin Chấm công KHÔNG có bảng lương — lương tính tại chỗ từ giờ công × giá giờ, và luật tính
 * khác nhau theo từng loại cơ sở (máy tự động tính theo giờ + ngày lễ; văn phòng tính theo ngày
 * công tháng và lương cơ bản). Chép lại luật ấy sang đây là nhận nuôi một bản sao sẽ lệch dần mỗi
 * lần bên kia sửa — mà lệch về TIỀN LƯƠNG thì không ai phát hiện bằng mắt.
 *
 * Nên gọi thẳng `VHCC_Luong::bang_cong_va_luong( $coso, 'YYYY-MM' )`, đúng hàm mà màn Bảng công
 * của họ dùng để vẽ ra con số kế toán vẫn nhìn.
 *
 * =================================================================================================
 * ⚠️ KHU VUI CHƠI CHƯA CÓ LƯƠNG — VÀ PHẢI NÓI RA, KHÔNG ĐƯỢC TRẢ VỀ 0.
 *
 * Hàm bên ấy trả ba dạng:
 *     · `kieu:'mtd'` — Máy tự động (Posh, JP): có lương, tổng ở `mtd.tong.tong`
 *     · `kieu:'vp'`  — Văn phòng: có lương, tổng ở `vp.tien.tongTien`
 *     · `kieu:'tho'` — Khu vui chơi (Funzone, Tutu, Event, Farm, Pinball): `coLuong = false`,
 *                      CHỈ CÓ GIỜ CÔNG, chưa khai giá giờ nên chưa ra tiền
 *
 * Ảnh anh Thắng gửi (FZ_LTVT) đúng dạng thứ ba: cột LƯƠNG ghi "thiếu giá" ở mọi dòng. Nếu ở đây
 * trả 0 cho những cơ sở ấy thì báo cáo chi phí thiếu nguyên phần lương của cả Khu vui chơi, mà số
 * 0 nhìn y hệt "tháng này không có lương" — không dòng nào báo. Nên mỗi cơ sở kèm `co_luong` và
 * `ghi_chu`, và giao diện bày LÝ DO thay vì bày số 0.
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
		return class_exists( 'VHCC_Luong' ) && method_exists( 'VHCC_Luong', 'bang_cong_va_luong' );
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
			return array( 'ok' => false, 'error' => 'Site này chưa cài plugin "Chấm công" (không thấy lớp VHCC_Luong).' );
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
		foreach ( (array) $cs as $ten ) {
			$ten = (string) $ten;
			$r = VHCC_Luong::bang_cong_va_luong( $ten, $tt );
			if ( ! is_array( $r ) || empty( $r['ok'] ) ) {
				$ds[] = self::dong( $ten, 0, false, isset( $r['error'] ) ? (string) $r['error'] : 'Không đọc được bảng công.', '' );
				continue;
			}
			$bp = isset( $r['boPhan'] ) ? (string) $r['boPhan'] : '';
			if ( empty( $r['coLuong'] ) ) {
				$ds[] = self::dong( $ten, 0, false, 'Chưa khai giá giờ bên Chấm công nên chưa tính ra tiền.', $bp );
				continue;
			}
			$kieu = isset( $r['kieu'] ) ? (string) $r['kieu'] : '';
			$tien = 0;
			$ghi  = '';
			if ( 'mtd' === $kieu ) {
				$tien = isset( $r['mtd']['tong']['tong'] ) ? (float) $r['mtd']['tong']['tong'] : 0;
				/* Bên ấy tự đánh dấu người chưa khai giá; báo lại để kế toán biết số còn thiếu ai. */
				$chua = isset( $r['mtd']['chuaKhaiGia'] ) ? (array) $r['mtd']['chuaKhaiGia'] : array();
				if ( $chua ) { $ghi = count( $chua ) . ' người chưa khai giá giờ — số này còn thiếu.'; }
			} elseif ( 'vp' === $kieu ) {
				$tien = isset( $r['vp']['tien']['tongTien'] ) ? (float) $r['vp']['tien']['tongTien'] : 0;
				if ( ! empty( $r['vp']['tien']['chuaKhaiNgayCong'] ) ) { $ghi = 'Chưa khai ngày công tháng — số này chưa đủ.'; }
			}
			/* 🔴 TIỀN RA 0 THÌ KHÔNG PHẢI "LƯƠNG BẰNG 0", MÀ LÀ EM ĐỌC SAI CHỖ.
			   Cơ sở có người chấm công cả tháng thì không thể hết 0 đồng. Ghi 0 vào báo cáo là mất
			   nguyên phần lương của cơ sở ấy mà tổng vẫn cộng đẹp. Nên coi như CHƯA ĐỌC ĐƯỢC và
			   nói rõ chỗ em đã tìm, kèm lối bấm 🔧 xem cấu trúc thật. */
			if ( $tien <= 0 ) {
				$duong = ( 'mtd' === $kieu ) ? 'mtd.tong.tong' : ( ( 'vp' === $kieu ) ? 'vp.tien.tongTien' : '?' );
				$ds[] = self::dong( $ten, 0, false,
					'Đọc được bảng công (kiểu ' . $kieu . ') nhưng tổng tiền ở "' . $duong . '" = 0 — nhiều khả năng đọc sai chỗ. Bấm 🔧 để xem cấu trúc thật.',
					$bp );
				continue;
			}
			$ds[] = self::dong( $ten, $tien, true, $ghi, $bp );
		}
		$tong = 0.0;
		foreach ( $ds as $x ) { $tong += $x['thanh_tien']; }
		return array( 'ok' => true, 'ds' => $ds, 'thang' => $tt,
			'so_cua_hang' => count( $ds ), 'tong' => $tong );
	}

	/**
	 * CHẨN ĐOÁN — liệt kê mọi SỐ trong kết quả `bang_cong_va_luong()` kèm đường dẫn của nó.
	 *
	 * Anh Thắng 18/09/2026 gửi ảnh: cơ sở FZ_SC_VIVO_T4 bên Nhân sự có lương 52.287.040, mà báo
	 * cáo này lại ghi "chưa khai giá giờ". Nghĩa là em đang đọc SAI CHỖ ĐỂ TIỀN. Không có mã nguồn
	 * plugin Chấm công trong tay thì đoán tiếp là đoán về tiền lương — việc không được phép đoán.
	 * Nút này in ra đúng cấu trúc thật để sửa cho trúng, rồi sẽ bỏ đi.
	 *
	 * 🔴 KHÔNG IN CHUỖI. Bảng công có họ tên và CCCD của nhân viên — dữ liệu cá nhân, không có việc
	 *    gì phải chạy qua màn hình kế toán hay đi vào ảnh chụp màn hình. Chỉ in SỐ và tên khoá; mọi
	 *    chuỗi thành "(chuỗi)", trừ vài khoá cấu trúc vô hại.
	 */
	public static function chuan_doan( $coso, $thang, $nam ) {
		if ( ! self::co_nguon() ) {
			return array( 'ok' => false, 'error' => 'Site này chưa cài plugin "Chấm công".' );
		}
		$m  = max( 1, min( 12, (int) $thang ) );
		$y  = (int) $nam;
		$tt = sprintf( '%04d-%02d', $y, $m );
		$r  = VHCC_Luong::bang_cong_va_luong( (string) $coso, $tt );
		if ( ! is_array( $r ) ) { return array( 'ok' => false, 'error' => 'Hàm bên Chấm công không trả về mảng.' ); }
		$so  = array();
		$khs = array();
		self::di( $r, '', $so, $khs );
		/* Số to thì nhiều khả năng là tiền — xếp lên đầu cho dễ nhìn. */
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
