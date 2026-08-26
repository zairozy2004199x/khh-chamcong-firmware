<?php
/**
 * PHÂN LỊCH LÀM VIỆC + XIN ĐỔI LỊCH — trên MySQL.
 *
 * Dịch từ `saveWorkSchedule` / `_upsertSched` / `xinDoiLich` / `duyetDoiLich`.
 *
 * =============================================================================================
 * KHOÁ CỦA MỘT Ô LỊCH LÀ (cơ sở, ngày, mã NV, CA) — BỐN thứ, không phải ba
 * =============================================================================================
 * `_upsertSched` bên Code.gs dựng khoá `station|day|empNo|ca`. Bỏ `ca` ra khỏi khoá là người làm
 * hai ca trong một ngày chỉ giữ được ca sau — ca trước bị GHI ĐÈ mất, và mất im lặng vì ô vẫn có
 * dữ liệu. Bảng MySQL đã có UNIQUE đúng bốn cột này (xem class-vhcc-db.php, bảng lich_cv), nên
 * chỗ này chỉ cần upsert đúng khoá đó.
 *
 * =============================================================================================
 * PHÂN LỊCH ≠ CHẤM CÔNG. ĐỪNG ĐỂ MỘT CÁI GHI VÀO CÁI KIA
 * =============================================================================================
 * Bên Apps Script, `saveWorkSchedule` gọi thêm `_syncSchedToAttendance` để tạo sẵn CỘT NGÀY trên
 * sheet `CS_` và ghi ghi-chú lên ô tiêu đề. Việc đó CẦN ở Sheet vì cột ngày phải tồn tại trước
 * khi có gì ghi vào.
 *
 * ⚠️ Ở MySQL thì KHÔNG port bước đó, và cố ý: bảng `cham_cong` là bảng dọc, hàng sinh ra khi có
 *    lượt bấm thật, không cần "tạo sẵn" gì. Nếu phân lịch mà chèn hàng vào `cham_cong` thì bảng
 *    lương sẽ thấy những ngày CÓ HÀNG mà không có giờ — tức là những ngày "đã xếp lịch" trông
 *    giống "đã đi làm mà quên chấm". Lịch là DỰ ĐỊNH, chấm công là THỰC TẾ; trộn hai thứ đó là
 *    trả tiền theo dự định.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Lich {

	const CHO_DUYET = 'Chờ duyệt';
	const DA_DUYET  = 'Đã duyệt';
	const TU_CHOI   = 'Từ chối';

	/**
	 * Xếp lịch: **Cửa hàng trưởng trở lên**, và phải có quyền trên cơ sở đó.
	 *
	 * ⚠️ Hỏi `lich_lam`, KHÔNG hỏi `co_sua_ho_so`. Hai việc khác hẳn nhau mà bản cũ gộp làm
	 *    một: mô hình anh Thắng chốt giao lịch cho cửa hàng trưởng (*"lên lịch làm cho cửa
	 *    hàng"*) nhưng KHÔNG giao hồ sơ nhân sự. Buộc lịch theo quyền hồ sơ là cửa hàng trưởng
	 *    hết xếp được lịch của chính cửa hàng mình.
	 */
	public static function co_xep_lich( $u, $coso ) {
		return VHCC_Vai::duoc( $u, 'lich_lam' ) && VHCC_NhanSu::co_quyen_coso( $u, $coso );
	}

	/** Duyệt xin đổi lịch: cùng bậc — đúng `duyetDoiLich` bản gốc. */
	public static function co_duyet( $u, $coso ) {
		return self::co_xep_lich( $u, $coso );
	}

	// ======================================================================= lịch công việc

	public static function ds_lich( $coso, $tu, $den ) {
		global $wpdb;
		return VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'lich_cv' )
			. ' WHERE coso=%s AND ngay >= %s AND ngay <= %s ORDER BY ngay, ho_ten, ca',
			VHCC_NhanSu::chuan_coso( $coso ), $tu, $den ) );
	}

	/**
	 * Xếp / sửa lịch. `$o` = array( array('ngay','ma_nv','ho_ten','ca','viec'), … ).
	 * Trả array('ok', 'so' => số ô đã ghi, 'error').
	 */
	public static function xep_lich( $u, $coso, $o ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		if ( ! self::co_xep_lich( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền xếp lịch cơ sở này.' );
		}
		$bang = VHCC_DB::t( 'lich_cv' );
		$so = 0;
		foreach ( (array) $o as $c ) {
			$ngay = trim( isset( $c['ngay'] ) ? (string) $c['ngay'] : '' );
			$ma   = trim( isset( $c['ma_nv'] ) ? (string) $c['ma_nv'] : '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || '' === $ma ) { continue; }
			$ca   = trim( isset( $c['ca'] ) ? (string) $c['ca'] : '' );
			$ghi  = array(
				'coso' => $coso, 'ngay' => $ngay, 'ma_nv' => $ma, 'ca' => $ca,
				'ho_ten' => trim( isset( $c['ho_ten'] ) ? (string) $c['ho_ten'] : '' ),
				'viec' => trim( isset( $c['viec'] ) ? (string) $c['viec'] : '' ),
				'nguoi_xep' => isset( $u['name'] ) ? (string) $u['name'] : '',
				'cap_nhat' => current_time( 'mysql' ),
			);
			/* Upsert theo ĐÚNG bốn cột khoá. Tra rồi ghi thay vì INSERT..ON DUPLICATE để câu lệnh
			   đọc được và chạy giống nhau trên MySQL lẫn SQLite của bộ phép thử. */
			$cu = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $bang WHERE coso=%s AND ngay=%s AND ma_nv=%s AND ca=%s",
				$coso, $ngay, $ma, $ca ) );
			if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
			else       { $wpdb->insert( $bang, $ghi ); }
			$so++;
		}
		return array( 'ok' => true, 'so' => $so );
	}

	public static function xoa_o_lich( $u, $coso, $ngay, $ma_nv, $ca ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( $coso );
		if ( ! self::co_xep_lich( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền xếp lịch cơ sở này.' );
		}
		$wpdb->delete( VHCC_DB::t( 'lich_cv' ), array(
			'coso' => $coso, 'ngay' => $ngay, 'ma_nv' => trim( (string) $ma_nv ), 'ca' => (string) $ca ) );
		return array( 'ok' => true );
	}

	// ======================================================================= cấu hình lịch

	/** Ca và loại việc dùng CHUNG toàn chuỗi; cơ sở bật lịch thì khai riêng. */
	public static function cau_hinh( $u ) {
		return array(
			'ca'        => (array) VHCC_Luong::cai_dat( 'LICH_CA', array( 'Sáng', 'Chiều', 'Tối' ) ),
			'loaiViec'  => (array) VHCC_Luong::cai_dat( 'LICH_LOAI_VIEC', array() ),
			'coSoBatLich' => (array) VHCC_Luong::cai_dat( 'LICH_CO_SO', array() ),
			'moiCoSo'   => VHCC_NhanSu::ds_coso(),
			'coSoCuaToi' => VHCC_NhanSu::co_quan_tri_nv( $u )
				? VHCC_NhanSu::ds_coso() : VHCC_NhanSu::ds_coso_cua( $u ),
			'suaDuocCauHinh' => VHCC_Vai::duoc( $u, 'ngoai_coso' ),
		);
	}

	/**
	 * Cơ sở nào BẬT phân lịch.
	 * ⚠️ Tắt lịch của một cơ sở KHÔNG xoá ô lịch nào đã xếp — chỉ ẩn màn xếp lịch. Xoá là mất lịch
	 *    đã xếp cho những ngày sắp tới, mà bật lại thì không dựng lại được.
	 */
	public static function dat_coso_bat_lich( $u, $ds ) {
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Bật/tắt phân lịch theo cơ sở — ' . VHCC_NhanSu::LOI_QT );
		}
		$sach = array();
		foreach ( (array) $ds as $x ) {
			$x = VHCC_NhanSu::chuan_coso( $x );
			if ( '' !== $x && ! in_array( $x, $sach, true ) ) { $sach[] = $x; }
		}
		return self::luu( 'LICH_CO_SO', $sach, $u );
	}

	/**
	 * Danh sách CA. Dùng chung toàn chuỗi.
	 * ⚠️ ĐỔI TÊN một ca KHÔNG đổi tên trong những ô lịch đã xếp — `ca` là một phần KHOÁ của ô lịch.
	 *    Nên đổi tên là những ô cũ giữ tên cũ và trông như ca lạ. Hàm này báo ra số ô đang dùng tên
	 *    bị bỏ, chứ không lặng lẽ để đó.
	 */
	public static function dat_ca( $u, $ds ) {
		global $wpdb;
		/* Danh sách ca dùng CHUNG cho mọi cơ sở — sửa ở đây là đổi lịch của cả chuỗi, nên
		   hỏi `ngoai_coso` chứ không phải `lich_lam`. Cửa hàng trưởng xếp lịch cửa hàng mình,
		   nhưng không đặt lại tên ca cho 20 cơ sở khác. */
		if ( ! VHCC_Vai::duoc( $u, 'ngoai_coso' ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền sửa danh sách ca (dùng chung mọi cơ sở).' );
		}
		$sach = array();
		foreach ( (array) $ds as $x ) {
			$x = trim( (string) $x );
			if ( '' !== $x && ! in_array( $x, $sach, true ) ) { $sach[] = $x; }
		}
		if ( ! $sach ) { return array( 'ok' => false, 'error' => 'Phải có ít nhất một ca.' ); }
		$mo_coi = array();
        foreach ( VHCC_DB::rows( 'SELECT ca, COUNT(*) so FROM ' . VHCC_DB::t( 'lich_cv' )
			. " WHERE ca <> '' GROUP BY ca" ) as $r ) {
			if ( ! in_array( $r['ca'], $sach, true ) ) { $mo_coi[ $r['ca'] ] = (int) $r['so']; }
		}
		$kq = self::luu( 'LICH_CA', $sach, $u );
		$kq['oMoCoi'] = $mo_coi;      // ô lịch đang dùng tên ca vừa bị bỏ — nói ra, đừng để im
		return $kq;
	}

	public static function dat_loai_viec( $u, $ds ) {
		if ( ! VHCC_Vai::duoc( $u, 'ngoai_coso' ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền sửa loại công việc (dùng chung mọi cơ sở).' );
		}
		$sach = array();
		foreach ( (array) $ds as $x ) {
			$x = trim( (string) $x );
			if ( '' !== $x && ! in_array( $x, $sach, true ) ) { $sach[] = $x; }
		}
		return self::luu( 'LICH_LOAI_VIEC', $sach, $u );
	}

	private static function luu( $khoa, $gia_tri, $u ) {
		global $wpdb;
		$bang = VHCC_DB::t( 'cai_dat' );
		$ghi = array( 'khoa' => $khoa, 'gia_tri' => wp_json_encode( $gia_tri ),
			'cap_nhat' => current_time( 'mysql' ),
			'nguoi_sua' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		$cu = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE khoa=%s", $khoa ) );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true );
	}

	// ======================================================================= xin đổi lịch

	/**
	 * Nhân viên xin đổi lịch. KHÔNG đòi quyền quản lý — chính họ xin cho mình.
	 * ⚠️ Nhưng phải có `ma_nv`: yêu cầu không có mã thì lúc duyệt không biết xếp cho ai.
	 */
	public static function xin_doi_lich( $u, $req ) {
		global $wpdb;
		$coso = VHCC_NhanSu::chuan_coso( isset( $req['coso'] ) ? $req['coso'] : '' );
		$ma   = trim( isset( $req['ma_nv'] ) ? (string) $req['ma_nv'] : '' );
		if ( '' === $coso || '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Thiếu cơ sở hoặc mã NV.' );
		}
		$ngay = trim( isset( $req['ngay'] ) ? (string) $req['ngay'] : '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		$sang = trim( isset( $req['doi_sang_ngay'] ) ? (string) $req['doi_sang_ngay'] : '' );
		if ( '' !== $sang && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sang ) ) {
			return array( 'ok' => false, 'error' => 'Ngày đổi sang không hợp lệ.' );
		}
		$ma_yc = VHCC_DB::ma_moi( 'YC', 'doi_lich_cv', 'ma_yc' );
		if ( '' === $ma_yc ) {
			return array( 'ok' => false, 'error' => 'Không cấp được mã yêu cầu, thử lại giúp em.' );
		}
		$ok = $wpdb->insert( VHCC_DB::t( 'doi_lich_cv' ), array(
			'ma_yc' => $ma_yc, 'coso' => $coso, 'ma_nv' => $ma,
			'ho_ten' => trim( isset( $req['ho_ten'] ) ? (string) $req['ho_ten'] : '' ),
			'ngay' => $ngay, 'ca' => trim( isset( $req['ca'] ) ? (string) $req['ca'] : '' ),
			'viec_moi' => trim( isset( $req['viec_moi'] ) ? (string) $req['viec_moi'] : '' ),
			'doi_sang_ngay' => ( '' !== $sang ? $sang : null ),
			'ly_do' => trim( isset( $req['ly_do'] ) ? (string) $req['ly_do'] : '' ),
			'trang_thai' => self::CHO_DUYET,
			'nguoi_xin' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'luc_xin' => current_time( 'mysql' ) ) );
		return ( false === $ok )
			? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
			: array( 'ok' => true, 'maYc' => $ma_yc );
	}

	public static function ds_doi_lich( $u, $chi_cho_duyet = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'doi_lich_cv' );
		if ( $chi_cho_duyet ) { $sql .= $wpdb->prepare( ' WHERE trang_thai=%s', self::CHO_DUYET ); }
		$sql .= ' ORDER BY luc_xin DESC';
		$out = array();
		foreach ( VHCC_DB::rows( $sql ) as $r ) {
			// Lọc theo cơ sở người xem phụ trách — đúng `getDoiLichList` bản gốc.
			if ( ! VHCC_NhanSu::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Duyệt / từ chối một yêu cầu.
	 *
	 * ⚠️ DUYỆT thì phải GHI THẬT vào lịch, không chỉ đổi trạng thái. Bản gốc gọi `_upsertSched`
	 *    ngay trong nhánh duyệt; bỏ bước đó là yêu cầu hiện "Đã duyệt" mà lịch không đổi — người
	 *    xin tưởng xong, người xếp tưởng xong, và không ai thấy sai cho tới hôm đó.
	 * ⚠️ CÓ `doi_sang_ngay` thì phải ghi HAI ô: ngày cũ thành TRỐNG việc, ngày mới nhận việc. Chỉ
	 *    ghi ngày mới là người đó bị xếp cả hai ngày.
	 * ⚠️ Yêu cầu đã xử lý rồi thì KHÔNG xử lại — duyệt hai lần là ghi lịch hai lần.
	 */
	public static function duyet( $u, $ma_yc, $dong_y ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'doi_lich_cv' ) . ' WHERE ma_yc=%s', trim( (string) $ma_yc ) ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không tìm thấy yêu cầu.' ); }
		if ( ! self::co_duyet( $u, $r['coso'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền duyệt cơ sở này.' );
		}
		if ( self::CHO_DUYET !== $r['trang_thai'] ) {
			return array( 'ok' => false, 'error' => 'Yêu cầu đã xử lý rồi (' . $r['trang_thai'] . ').' );
		}

		if ( $dong_y ) {
			$o = array();
			if ( ! empty( $r['doi_sang_ngay'] ) ) {
				// Ngày cũ: giữ ô nhưng BỎ việc. Xoá hẳn ô thì mất dấu là hôm đó vốn có xếp lịch.
				$o[] = array( 'ngay' => $r['ngay'], 'ma_nv' => $r['ma_nv'], 'ho_ten' => $r['ho_ten'],
					'ca' => $r['ca'], 'viec' => '' );
				$o[] = array( 'ngay' => $r['doi_sang_ngay'], 'ma_nv' => $r['ma_nv'], 'ho_ten' => $r['ho_ten'],
					'ca' => $r['ca'], 'viec' => (string) $r['viec_moi'] );
			} else {
				$o[] = array( 'ngay' => $r['ngay'], 'ma_nv' => $r['ma_nv'], 'ho_ten' => $r['ho_ten'],
					'ca' => $r['ca'], 'viec' => (string) $r['viec_moi'] );
			}
			$kq = self::xep_lich( $u, $r['coso'], $o );
			if ( empty( $kq['ok'] ) ) { return $kq; }        // ghi lịch trượt -> KHÔNG đổi trạng thái
		}

		$wpdb->update( VHCC_DB::t( 'doi_lich_cv' ), array(
			'trang_thai' => $dong_y ? self::DA_DUYET : self::TU_CHOI,
			'nguoi_duyet' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'luc_duyet' => current_time( 'mysql' ) ), array( 'id' => (int) $r['id'] ) );
		return array( 'ok' => true, 'trangThai' => $dong_y ? self::DA_DUYET : self::TU_CHOI );
	}
}
