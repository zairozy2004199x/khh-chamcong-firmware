<?php
/**
 * NHÂN SỰ: hồ sơ · cơ sở · bộ phận — đọc/ghi trên MySQL.
 *
 * =============================================================================================
 * QUYỀN CHIA HAI BẬC, VÀ ĐỪNG GỘP LẠI
 * =============================================================================================
 * Anh Thắng 07/08/2026: *"quyền quản lý nhân viên cửa hàng anh sẽ bàn giao cho cửa đó luôn"*, rồi
 * *"chọn cht phân quyền theo mức"*. Nên có ĐÚNG hai cửa, và ranh giới không phải cho gọn:
 *
 *   `co_sua_ho_so`   Admin · Quản lý · Cửa hàng trưởng
 *       Việc HÀNG NGÀY trong phạm vi cửa hàng mình: sửa SĐT, địa chỉ, chức vụ, nhiệm vụ, trạng
 *       thái làm việc của người ĐANG ở cửa hàng đó.
 *
 *   `co_quan_tri_nv` Admin · Quản lý
 *       Việc ra NGOÀI phạm vi một cửa hàng:
 *         · TẠO hồ sơ mới      — Mã NV dùng chung CẢ CHUỖI, cấp trùng là gộp công hai người;
 *         · ĐỔI cửa hàng       — chuyển người giữa hai cửa hàng là chuyển cả công và lương;
 *         · XOÁ hồ sơ, mã song song, nhập hàng loạt, duyệt yêu cầu.
 *
 *   `co_xem_luong`   Admin · Quản lý
 *       Ô Lương cơ bản + số tài khoản + ngân hàng. Cửa hàng trưởng KHÔNG thấy — họ sửa hồ sơ
 *       người của mình được, nhưng lương thì không.
 *
 * ⚠️ Cửa hàng trưởng sửa được hồ sơ KHÔNG có nghĩa là sửa được của bất kỳ ai: còn phải qua
 *    `co_quyen_coso`. Thiếu chốt đó thì một cửa hàng trưởng sửa hồ sơ người cửa hàng khác.
 * ⚠️ Vai trò NHÂN VIÊN không xem được hồ sơ ai, kể cả cửa hàng ghi trong dòng phân quyền của họ —
 *    cửa hàng đó chỉ để biết chấm công online ghi vào đâu, KHÔNG phải quyền xem.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_NhanSu {

	const R_ADMIN   = 'ADMIN';
	const R_QUAN_LY = 'QUAN_LY';
	const R_CHT     = 'CUA_HANG_TRUONG';
	const R_NV      = 'NHAN_VIEN';
	const R_KE_TOAN = 'KE_TOAN';

	const LOI_QT = 'Việc này chỉ Admin / Quản lý làm được (ảnh hưởng ngoài phạm vi cửa hàng).';

	/** Ô chỉ Admin/Quản lý được thấy và sửa. */
	const O_LUONG = array( 'luong_co_ban', 'so_tai_khoan', 'ngan_hang' );

	private static function vt( $u ) {
		return strtoupper( trim( isset( $u['role'] ) ? (string) $u['role'] : '' ) );
	}

	public static function co_sua_ho_so( $u ) {
		$v = self::vt( $u );
		return self::R_ADMIN === $v || self::R_QUAN_LY === $v || self::R_CHT === $v;
	}

	public static function co_quan_tri_nv( $u ) {
		$v = self::vt( $u );
		return self::R_ADMIN === $v || self::R_QUAN_LY === $v;
	}

	public static function co_xem_luong( $u ) {
		$v = self::vt( $u );
		return self::R_ADMIN === $v || self::R_QUAN_LY === $v;
	}

	/**
	 * Người này có quyền trên cơ sở đó không.
	 * ⚠️ NHÂN VIÊN trả false LUÔN — trước cả phép so danh sách cơ sở.
	 */
	public static function co_quyen_coso( $u, $coso ) {
		$v = self::vt( $u );
		if ( self::R_NV === $v ) { return false; }
		if ( self::R_ADMIN === $v || self::R_QUAN_LY === $v ) { return true; }
		$coso = self::chuan_coso( $coso );
		if ( '' === $coso ) { return false; }
		foreach ( self::ds_coso_cua( $u ) as $x ) {
			if ( strtolower( $x ) === strtolower( $coso ) ) { return true; }
		}
		return false;
	}

	public static function ds_coso_cua( $u ) {
		$ds = array();
		foreach ( explode( ',', isset( $u['coso'] ) ? (string) $u['coso'] : '' ) as $x ) {
			$x = self::chuan_coso( $x );
			if ( '' !== $x ) { $ds[] = $x; }
		}
		return $ds;
	}

	public static function chuan_coso( $s ) {
		return trim( preg_replace( '/^CS_/', '', (string) $s ) );
	}

	/**
	 * Đọc một ô TIỀN người ta gõ tay — bản dịch `_vpSoTien`.
	 *
	 * ⚠️ PHẢI phân biệt kiểu VIỆT và kiểu ANH, không được vét sạch dấu chấm rồi thôi:
	 *      13.000.000  (kiểu Việt: chấm là phân cách nghìn)  -> 13000000
	 *      13,000,000  (kiểu Anh:  phẩy là phân cách nghìn)  -> 13000000
	 *      13,5        (kiểu Việt: phẩy là dấu thập phân)    -> 13.5
	 *    Bản đầu của hàm này em viết `preg_replace('/[^0-9.]/','')` rồi `(float)` — nghĩa là
	 *    "13.000.000" thành **13 đồng**. Lương một người thành 13 đồng, mà ô vẫn có số nên bảng
	 *    lương trông vẫn bình thường. Đây đúng loại sai mà cả việc này phải tránh.
	 */
	public static function so_tien( $v ) {
		if ( is_int( $v ) || is_float( $v ) ) { return is_finite( (float) $v ) ? (float) $v : 0.0; }
		$s = trim( (string) $v );
		if ( '' === $s ) { return 0.0; }
		$s = preg_replace( '/[^\d.,-]/', '', $s );                       // bỏ 'đ', 'VND', khoảng trắng
		if ( preg_match( '/^-?\d{1,3}(\.\d{3})+$/', $s ) ) {
			$s = str_replace( '.', '', $s );                              // 13.000.000  (kiểu VN)
		} elseif ( preg_match( '/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s ) ) {
			$s = str_replace( ',', '', $s );                              // 13,000,000.5 (kiểu Anh)
		} else {
			$s = str_replace( ',', '.', $s );                             // '13,5' -> 13.5
		}
		return is_numeric( $s ) ? (float) $s : 0.0;
	}

	// ======================================================================= đọc

	/** Danh sách hồ sơ. Ô lương bị BỎ HẲN khỏi kết quả khi không có quyền, không chỉ ẩn trên màn. */
	public static function ds_nhan_vien( $u, $coso = '', $tim = '' ) {
		global $wpdb;
		$dk = array( '1=1' );
		$tv = array();
		$coso = self::chuan_coso( $coso );
		if ( '' !== $coso ) { $dk[] = 'LOWER(cua_hang)=LOWER(%s)'; $tv[] = $coso; }
		if ( '' !== trim( (string) $tim ) ) {
			$like = '%' . $wpdb->esc_like( trim( $tim ) ) . '%';
			$dk[] = '(ma_nv LIKE %s OR ho_ten LIKE %s OR sdt LIKE %s OR cccd LIKE %s)';
			$tv = array_merge( $tv, array( $like, $like, $like, $like ) );
		}
		$sql = 'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY cua_hang, ho_ten';
		$rows = VHCC_DB::rows( $tv ? $wpdb->prepare( $sql, $tv ) : $sql );

		$xem_luong = self::co_xem_luong( $u );
		$out = array();
		foreach ( $rows as $r ) {
			// Cửa hàng trưởng chỉ thấy người của cửa hàng mình.
			if ( ! self::co_quyen_coso( $u, $r['cua_hang'] ) ) { continue; }
			if ( ! $xem_luong ) {
				/* BỎ khỏi dữ liệu, không phải ẩn bằng CSS. Ẩn trên màn thì số vẫn đi xuống trình
				   duyệt và ai mở công cụ nhà phát triển là đọc được. */
				foreach ( self::O_LUONG as $o ) { unset( $r[ $o ] ); }
			}
			$out[] = $r;
		}
		return $out;
	}

	public static function ho_so( $ma_nv ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', trim( (string) $ma_nv ) ), ARRAY_A );
	}

	/** Cơ sở đã biết: gộp từ bảng máy, bảng chấm công và hồ sơ — không tự tạo cơ sở nào. */
	public static function ds_coso() {
		global $wpdb;
		$ds = array();
		foreach ( array( 'may' => 'cua_hang', 'cham_cong' => 'coso', 'nhan_vien' => 'cua_hang',
			'bo_phan_coso' => 'coso' ) as $bang => $cot ) {
			foreach ( (array) $wpdb->get_col( "SELECT DISTINCT $cot FROM " . VHCC_DB::t( $bang ) ) as $x ) {
				$x = self::chuan_coso( $x );
				if ( '' !== $x && ! in_array( $x, $ds, true ) ) { $ds[] = $x; }
			}
		}
		sort( $ds );
		return $ds;
	}

	// ======================================================================= ghi

	/**
	 * Lưu hồ sơ. Trả array('ok'=>bool, 'error'=>..., 'tao_moi'=>bool).
	 *
	 * ⚠️ Bốn chốt, mỗi chốt một lý do khác nhau — bỏ chốt nào cũng mất một thứ khác nhau.
	 */
	public static function luu_ho_so( $u, $dat ) {
		global $wpdb;
		$ma = trim( isset( $dat['ma_nv'] ) ? (string) $dat['ma_nv'] : '' );
		if ( '' === $ma ) { return array( 'ok' => false, 'error' => 'Thiếu Mã NV.' ); }
		$cu = self::ho_so( $ma );
		$coso_moi = self::chuan_coso( isset( $dat['cua_hang'] ) ? $dat['cua_hang'] : '' );

		// Chốt 1: bậc dưới cùng.
		if ( ! self::co_sua_ho_so( $u ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền sửa hồ sơ nhân sự.' );
		}
		// Chốt 2: TẠO MỚI là cấp Mã NV dùng chung cả chuỗi -> chỉ Admin/Quản lý.
		if ( ! $cu && ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false,
				'error' => 'Tạo hồ sơ mới cần cấp Mã NV — mã dùng chung cả chuỗi nên ' . self::LOI_QT );
		}
		// Chốt 3: ĐỔI cửa hàng là chuyển cả công và lương giữa hai cửa hàng -> chỉ Admin/Quản lý.
		if ( $cu ) {
			$coso_cu = self::chuan_coso( $cu['cua_hang'] );
			if ( '' !== $coso_moi && strtolower( $coso_cu ) !== strtolower( $coso_moi )
				&& ! self::co_quan_tri_nv( $u ) ) {
				return array( 'ok' => false,
					'error' => 'Đổi cửa hàng của một người là chuyển công và lương giữa hai cửa hàng — '
						. self::LOI_QT . ' Cần người này làm thêm ở cửa hàng bạn thì khai vào ô Cơ sở phụ.' );
			}
			// Chốt 4: cửa hàng trưởng chỉ sửa người ĐANG ở cửa hàng mình.
			if ( ! self::co_quyen_coso( $u, $coso_cu ) ) {
				return array( 'ok' => false, 'error' => 'Hồ sơ này không thuộc cơ sở bạn phụ trách.' );
			}
		} elseif ( '' !== $coso_moi && ! self::co_quyen_coso( $u, $coso_moi ) ) {
			return array( 'ok' => false, 'error' => 'Bạn không phụ trách cơ sở "' . $coso_moi . '".' );
		}

		$cho_phep = array( 'ho_ten', 'cua_hang', 'pin_may', 'sdt', 'ngay_sinh', 'gioi_tinh', 'cccd',
			'dia_chi', 'nguoi_lien_he_khan', 'sdt_khan', 'chuc_vu', 'ngay_vao_lam',
			'trang_thai_lam_viec', 'loai_hop_dong', 'nhiem_vu', 'coso_phu', 'pin_dang_nhap' );
		if ( self::co_xem_luong( $u ) ) {
			$cho_phep = array_merge( $cho_phep, self::O_LUONG );
		}
		/* Danh sách CHO PHÉP, không phải danh sách CHẶN. Với danh sách chặn thì mỗi cột mới thêm
		   vào bảng là một ô người ta ghi được mà không ai nhớ ra phải chặn. */
		$ghi = array();
		foreach ( $cho_phep as $o ) {
			if ( ! array_key_exists( $o, $dat ) ) { continue; }
			$v = $dat[ $o ];
			if ( in_array( $o, array( 'ngay_sinh', 'ngay_vao_lam' ), true ) ) {
				$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $v ) ) ? trim( (string) $v ) : null;
			} elseif ( 'luong_co_ban' === $o ) {
				$v = self::so_tien( $v );
			} else {
				$v = trim( (string) $v );
			}
			$ghi[ $o ] = $v;
		}
		if ( isset( $ghi['cua_hang'] ) ) { $ghi['cua_hang'] = self::chuan_coso( $ghi['cua_hang'] ); }
		$ghi['cap_nhat'] = current_time( 'mysql' );

		if ( $cu ) {
			$ok = $wpdb->update( VHCC_DB::t( 'nhan_vien' ), $ghi, array( 'ma_nv' => $ma ) );
			return ( false === $ok )
				? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
				: array( 'ok' => true, 'tao_moi' => false );
		}
		$ghi['ma_nv'] = $ma;
		$ok = $wpdb->insert( VHCC_DB::t( 'nhan_vien' ), $ghi );
		return ( false === $ok )
			? array( 'ok' => false, 'error' => 'MySQL: ' . $wpdb->last_error )
			: array( 'ok' => true, 'tao_moi' => true );
	}

	/**
	 * XOÁ hồ sơ.
	 * ⚠️ CHẶN khi người đó CÒN chấm công. Xoá hồ sơ mà giữ lại chấm công là bảng lương có mã
	 *    không tra ra được tên — người thật, công thật, mà không biết trả cho ai. Muốn cho nghỉ
	 *    thì đổi "Trạng thái làm việc", đừng xoá.
	 */
	public static function xoa_ho_so( $u, $ma_nv ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Xoá hồ sơ — ' . self::LOI_QT );
		}
		$ma = trim( (string) $ma_nv );
		$so = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $ma ) );
		if ( $so > 0 ) {
			return array( 'ok' => false, 'error' => 'Người này còn ' . $so . ' lượt chấm công. '
				. 'Xoá hồ sơ là bảng lương có mã mà không tra ra tên. Muốn cho nghỉ thì đổi '
				. '"Trạng thái làm việc" thành Đã nghỉ.' );
		}
		$wpdb->delete( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma ) );
		return array( 'ok' => true );
	}

	/** Xếp bộ phận cho một cơ sở. Bộ phận quyết định công thức lương -> chỉ Admin/Quản lý. */
	public static function xep_bo_phan( $u, $coso, $bo_phan, $theo_gio = null ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false,
				'error' => 'Bộ phận quyết định CÔNG THỨC LƯƠNG của cả cơ sở — ' . self::LOI_QT );
		}
		$coso = self::chuan_coso( $coso );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$bp = trim( (string) $bo_phan );
		/* Chỉ nhận đúng danh sách. Bộ phận lạ chuẩn hoá thành 'Chưa xếp' = KHÔNG có công thức
		   lương — đó là hành vi bản gốc, và nó đúng: thà không tính còn hơn tính bằng công thức
		   của bộ phận khác. Nhưng ở ĐÂY thì phải nói ra, đừng lặng lẽ đổi thành Chưa xếp. */
		if ( '' !== $bp && ! in_array( $bp, VHCC_Luong::BP_DS, true ) ) {
			return array( 'ok' => false, 'error' => 'Bộ phận "' . $bp . '" không có trong danh sách ('
				. implode( ' · ', VHCC_Luong::BP_DS ) . '). Khai tên khác là cơ sở đó thành "Chưa xếp" '
				. 'và KHÔNG được tính lương.' );
		}
		$cu = $wpdb->get_row( $wpdb->prepare(
			'SELECT id FROM ' . VHCC_DB::t( 'bo_phan_coso' ) . ' WHERE LOWER(coso)=LOWER(%s) LIMIT 1',
			$coso ), ARRAY_A );
		$ghi = array( 'coso' => $coso, 'bo_phan' => $bp );
		if ( null !== $theo_gio ) { $ghi['theo_gio'] = $theo_gio ? 1 : 0; }
		if ( $cu ) { $wpdb->update( VHCC_DB::t( 'bo_phan_coso' ), $ghi, array( 'id' => (int) $cu['id'] ) ); }
		else       { $wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), $ghi ); }
		return array( 'ok' => true );
	}

	/**
	 * Khai MÃ SONG SONG (một người, hai mã: máy cũ chưa nhận lệnh đổi mã).
	 * ⚠️ PHẢI KHAI, hệ thống KHÔNG được tự suy "hai mã này chắc là một người" từ tên — tên người
	 *    Việt trùng rất nhiều, đoán sai là gộp lương hai người khác nhau.
	 */
	public static function khai_ma_song_song( $u, $ma_a, $ma_b, $ho_ten, $ly_do ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Mã song song ảnh hưởng cả chuỗi — ' . self::LOI_QT );
		}
		$a = trim( (string) $ma_a );
		$b = trim( (string) $ma_b );
		if ( '' === $a || '' === $b ) { return array( 'ok' => false, 'error' => 'Thiếu một trong hai mã.' ); }
		if ( strtolower( $a ) === strtolower( $b ) ) {
			return array( 'ok' => false, 'error' => 'Hai mã giống nhau — không phải mã song song.' );
		}
		$da = $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))'
			. ' OR (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s)) LIMIT 1', $a, $b, $b, $a ) );
		if ( $da ) { return array( 'ok' => false, 'error' => 'Cặp mã này đã khai rồi.' ); }
		$wpdb->insert( VHCC_DB::t( 'ma_song_song' ), array(
			'ma_a' => $a, 'ma_b' => $b, 'ho_ten' => trim( (string) $ho_ten ),
			'ly_do' => trim( (string) $ly_do ),
			'nguoi_khai' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'tao_luc' => current_time( 'mysql' ) ) );
		return array( 'ok' => true );
	}
}
