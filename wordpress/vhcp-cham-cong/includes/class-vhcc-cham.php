<?php
/**
 * BẢNG CHẤM CÔNG · CỜ CẦN KIỂM · TĂNG CƯỜNG · QUY ĐỔI CƠ SỞ · THỐNG KÊ ĐẨY.
 *
 * ⚠️ Lớp này CHỈ ĐỌC bảng `cham_cong`, không sửa giờ. Sửa giờ chấm công chỉ có đúng hai đường:
 *    cổng nhận từ máy (VHCC_Nhan) và chấm công online (VHCC_Online). Mở thêm đường thứ ba để
 *    "sửa cho nhanh" là mở đường sửa lương bằng tay mà không có dấu vết.
 *    Muốn ghi chú một ngày sai thì dùng CỜ (bảng `ghi_chu`) — nó nằm cạnh, không đè lên giờ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Cham {

	/**
	 * Bảng chấm công của một cơ sở trong một tháng, kèm cờ cần kiểm.
	 * Bản dịch `getSheetDataVaFlags` — trả CẢ HAI trong một lượt vì giao diện hiện chung một bảng;
	 * gọi hai lượt là hai lần đọc cho một màn hình.
	 */
	public static function bang_cham_cong( $u, $coso, $thang ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		$hang = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			$hang[] = array(
				'ngay' => $r['ngay'], 'maNV' => $r['ma_nv'], 'hauTo' => (string) $r['hau_to'],
				'hoTen' => $r['ho_ten'],
				'vao' => VHCC_DB::hhmmss( $r['gio_vao_giay'] ),
				'ra'  => VHCC_DB::hhmmss( $r['gio_ra_giay'] ),
			);
		}
		return array( 'ok' => true, 'coSo' => $coso, 'thang' => $tt, 'hang' => $hang,
			'co' => self::ds_ghi_chu( $u, $coso, $tt ) );
	}

	// ======================================================================= cờ cần kiểm

	public static function ds_ghi_chu( $u, $coso = '', $thang = '' ) {
		global $wpdb;
		$dk = array( '1=1' );
		$tv = array();
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' !== $coso ) { $dk[] = 'LOWER(coso)=LOWER(%s)'; $tv[] = $coso; }
		if ( '' !== $thang ) {
			$tt = VHCC_Luong::tien_to_thang( $thang );
			if ( '' !== $tt ) { $dk[] = 'ngay LIKE %s'; $tv[] = $tt . '-%'; }
		}
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY tao_luc DESC';
		$out = array();
		foreach ( VHCC_DB::rows( $tv ? $wpdb->prepare( $sql, $tv ) : $sql ) as $r ) {
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Gắn một cờ "cần kiểm" lên một ngày của một người.
	 * ⚠️ Cờ KHÔNG đụng vào giờ. Nó là ghi chú nằm CẠNH, để người có quyền xem rồi tự quyết —
	 *    chứ không phải để app tự sửa công.
	 */
	public static function luu_ghi_chu( $u, $dat ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		$ngay = trim( isset( $dat['ngay'] ) ? (string) $dat['ngay'] : '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		$noi_dung = trim( isset( $dat['ghi_chu'] ) ? (string) $dat['ghi_chu'] : '' );
		if ( '' === $noi_dung ) {
			return array( 'ok' => false, 'error' => 'Cờ rỗng thì không nói lên điều gì — ghi rõ cần kiểm gì.' );
		}
		$id = isset( $dat['flag_id'] ) ? trim( (string) $dat['flag_id'] ) : '';
		if ( '' === $id ) { $id = 'CO' . gmdate( 'YmdHis', (int) current_time( 'timestamp' ) ) . wp_rand( 100, 999 ); }
		$ghi = array( 'flag_id' => $id, 'coso' => $coso, 'ngay' => $ngay,
			'ma_nv' => trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' ),
			'ho_ten' => trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' ),
			'ghi_chu' => $noi_dung,
			'nguoi_gan' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'trang_thai' => 'Cần kiểm', 'tao_luc' => current_time( 'mysql' ) );
		$cu = $wpdb->get_row( $wpdb->prepare(
			'SELECT id FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE flag_id=%s', $id ), ARRAY_A );
		if ( $cu ) { $wpdb->update( VHCC_DB::t( 'ghi_chu' ), $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( VHCC_DB::t( 'ghi_chu' ), $ghi ); }
		return array( 'ok' => true, 'flagId' => $id );
	}

	public static function xu_ly_ghi_chu( $u, $flag_id, $ket_luan = '' ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' ) . ' WHERE flag_id=%s', trim( (string) $flag_id ) ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy cờ này.' ); }
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		/* GIỮ nguyên nội dung cờ, chỉ thêm kết luận. Ghi đè nội dung là mất lý do vì sao nó được
		   gắn — mà đó là thứ duy nhất giải thích được một ngày công bất thường về sau. */
		$wpdb->update( VHCC_DB::t( 'ghi_chu' ), array(
			'trang_thai' => 'Đã xử lý',
			'ghi_chu' => $r['ghi_chu'] . ( '' !== trim( (string) $ket_luan )
				? "\n— kết luận: " . trim( (string) $ket_luan ) : '' ),
			'xu_ly_luc' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return array( 'ok' => true );
	}

	/**
	 * Ngày CÓ giờ vào mà THIẾU giờ ra — quên check-out.
	 * ⚠️ Chỉ CẢNH BÁO, không tự điền giờ ra. Điền là bịa giờ làm cho một ngày mà không ai biết
	 *    người ta làm bao lâu; mà cái đó thành tiền.
	 */
	public static function canh_bao_thieu_gio_ra( $u, $coso, $thang ) {
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) { return array(); }
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array(); }
		$out = array();
		foreach ( VHCC_Luong::doc_thang( $coso, $tt ) as $r ) {
			if ( null === $r['gio_vao_giay'] || '' === $r['gio_vao_giay'] ) { continue; }
			if ( null !== $r['gio_ra_giay'] && '' !== $r['gio_ra_giay'] ) { continue; }
			$out[] = array( 'ngay' => $r['ngay'], 'maNV' => $r['ma_nv'],
				'hauTo' => (string) $r['hau_to'], 'hoTen' => $r['ho_ten'],
				'vao' => VHCC_DB::hhmmss( $r['gio_vao_giay'] ) );
		}
		return $out;
	}

	// ======================================================================= tăng cường

	/**
	 * Người của cơ sở khác sang làm ở cơ sở này.
	 * ⚠️ Ngày đã KHOÁ (chốt kỳ) thì không khai thêm được — khai thêm vào kỳ đã chốt là số công
	 *    đổi sau khi bảng lương đã in ra.
	 */
	public static function them_tang_cuong( $u, $dat ) {
		global $wpdb;
		$den = VHCC_NhanSu::chuan_coso( isset( $dat['coso_den'] ) ? $dat['coso_den'] : '' );
		if ( ! VHCC_NhanSu::co_quyen_coso( $u, $den ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở nhận người.' );
		}
		$ngay = trim( isset( $dat['ngay'] ) ? (string) $dat['ngay'] : '' );
		$ma   = trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Thiếu ngày hoặc mã NV.' );
		}
		$bang = VHCC_DB::t( 'tang_cuong' );
		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, khoa FROM $bang WHERE ngay=%s AND LOWER(coso_den)=LOWER(%s) AND ma_nv=%s",
			$ngay, $den, $ma ), ARRAY_A );
		if ( $cu && (int) $cu['khoa'] ) {
			return array( 'ok' => false, 'error' => 'Ngày này đã CHỐT KỲ — không sửa được nữa. '
				. 'Sửa sau khi chốt là số công đổi sau khi bảng lương đã in.' );
		}
		$hs = VHCC_NhanSu::ho_so( $ma );
		$ghi = array( 'ngay' => $ngay, 'coso_den' => $den, 'ma_nv' => $ma,
			'coso_goc' => $hs ? VHCC_NhanSu::chuan_coso( $hs['cua_hang'] ) : '',
			'ho_ten' => $hs ? (string) $hs['ho_ten'] : trim( isset( $dat['ho_ten'] ) ? (string) $dat['ho_ten'] : '' ),
			'ghi_chu' => trim( isset( $dat['ghi_chu'] ) ? (string) $dat['ghi_chu'] : '' ),
			'nguoi_khai' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'tao_luc' => current_time( 'mysql' ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true );
	}

	/** CHỐT KỲ tăng cường của một (cơ sở, tháng). Chỉ Admin/Quản lý — chốt là không sửa được nữa. */
	public static function khoa_tang_cuong( $u, $coso, $thang, $khoa = true ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Chốt kỳ tăng cường — ' . VHCC_NhanSu::LOI_QT );
		}
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }
		$so = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'tang_cuong' ) . ' SET khoa=%d'
			. ' WHERE LOWER(coso_den)=LOWER(%s) AND ngay LIKE %s',
			$khoa ? 1 : 0, $coso, $tt . '-%' ) );
		return array( 'ok' => true, 'so' => (int) $so, 'khoa' => (bool) $khoa );
	}

	public static function ds_tang_cuong( $coso, $thang ) {
		global $wpdb;
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array(); }
		return VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'tang_cuong' )
			. ' WHERE LOWER(coso_den)=LOWER(%s) AND ngay LIKE %s ORDER BY ngay, ho_ten',
			VHCC_NhanSu::chuan_coso( $coso ), $tt . '-%' ) );
	}

	// ======================================================================= quy đổi cơ sở

	/**
	 * Tên cơ sở máy khai -> tên cơ sở thật.
	 * ⚠️ Chỉ Admin/Quản lý: quy ước này dùng cho CẢ CHUỖI, và khai sai một dòng là chấm công của
	 *    một cơ sở chảy sang cơ sở khác.
	 */
	public static function luu_quy_doi_coso( $u, $tu, $den, $ghi_chu = '' ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Quy đổi cơ sở dùng cho cả chuỗi — ' . VHCC_NhanSu::LOI_QT );
		}
		$tu  = VHCC_NhanSu::chuan_coso( $tu );
		$den = VHCC_NhanSu::chuan_coso( $den );
		if ( '' === $tu ) { return array( 'ok' => false, 'error' => 'Thiếu tên cần quy đổi.' ); }
		if ( strtolower( $tu ) === strtolower( $den ) ) {
			return array( 'ok' => false, 'error' => 'Quy đổi về chính nó thì không có tác dụng gì.' );
		}
		$bang = VHCC_DB::t( 'quy_doi_coso' );
		/* Chặn chuỗi quy đổi A->B->C: bên đọc chỉ tra MỘT bước, nên chuỗi hai bước là im lặng sai. */
		$tiep = $wpdb->get_var( $wpdb->prepare( "SELECT den FROM $bang WHERE LOWER(tu)=LOWER(%s)", $den ) );
		if ( $tiep ) {
			return array( 'ok' => false, 'error' => '"' . $den . '" lại đang được quy đổi sang "'
				. $tiep . '". Quy đổi chỉ tra MỘT bước, nên chuỗi hai bước là sai im lặng — '
				. 'quy đổi "' . $tu . '" thẳng về "' . $tiep . '".' );
		}
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $bang WHERE LOWER(tu)=LOWER(%s)", $tu ), ARRAY_A );
		$ghi = array( 'tu' => $tu, 'den' => $den, 'ghi_chu' => trim( (string) $ghi_chu ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true );
	}

	public static function ds_quy_doi() {
		return VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'quy_doi_coso' ) . ' ORDER BY tu' );
	}

	// ======================================================================= thống kê đẩy

	/**
	 * Thống kê lượt máy đẩy lên: đếm theo cơ sở và theo nguồn.
	 * Đây là chỗ nhìn ra "cơ sở nào tự nhiên im" — mà im lặng chính là kiểu hỏng khó thấy nhất.
	 */
	public static function thong_ke_day( $u, $thang ) {
		global $wpdb;
		$tt = VHCC_Luong::tien_to_thang( $thang );
		if ( '' === $tt ) { return array(); }
		$out = array();
		foreach ( VHCC_DB::rows( $wpdb->prepare(
			'SELECT coso, nguon, COUNT(*) so, MIN(ngay) tu_ngay, MAX(ngay) den_ngay FROM '
			. VHCC_DB::t( 'cham_cong' ) . ' WHERE ngay LIKE %s GROUP BY coso, nguon ORDER BY coso',
			$tt . '-%' ) ) as $r ) {
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Dọn thống kê. ⚠️ CHỈ dọn bảng CHỜ GÁN đã xử lý xong — TUYỆT ĐỐI không chạm `cham_cong`.
	 *    Bên Apps Script `xoaThongKeDay` xoá sheet thống kê riêng; ở đây không có sheet đó, và
	 *    thống kê được tính trực tiếp từ chấm công nên "dọn thống kê" mà xoá chấm công là xoá
	 *    tiền lương. Nên hàm này cố ý làm việc KHÁC và hẹp hơn hẳn.
	 */
	public static function xoa_thong_ke_day( $u, $truoc_ngay ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Dọn dữ liệu — ' . VHCC_NhanSu::LOI_QT );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $truoc_ngay ) ) ) {
			return array( 'ok' => false, 'error' => 'Cần một ngày mốc dạng yyyy-MM-dd.' );
		}
		$so = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'cho_gan' ) . " WHERE da_chuyen <> '' AND nhan_luc < %s",
			trim( (string) $truoc_ngay ) . ' 00:00:00' ) );
		return array( 'ok' => true, 'so' => (int) $so,
			'ghiChu' => 'Chỉ dọn lượt CHỜ GÁN đã xử lý xong. Bảng chấm công không bị chạm.' );
	}
}
