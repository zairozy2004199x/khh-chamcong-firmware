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

	/** Xoá nhiều hồ sơ. Từng cái đi qua ĐÚNG chốt của xoa_ho_so — không có đường tắt hàng loạt. */
	public static function xoa_nhieu_ho_so( $u, $ds_ma ) {
		$xong = array();
		$bo = array();
		foreach ( (array) $ds_ma as $ma ) {
			$r = self::xoa_ho_so( $u, $ma );
			if ( ! empty( $r['ok'] ) ) { $xong[] = trim( (string) $ma ); }
			else { $bo[] = trim( (string) $ma ) . ': ' . $r['error']; }
		}
		return array( 'ok' => true, 'xong' => $xong, 'bo' => $bo );
	}

	/**
	 * CHO NGHỈ VIỆC — đường ĐÚNG thay cho xoá hồ sơ.
	 * Giữ nguyên hồ sơ và toàn bộ chấm công; chỉ đổi trạng thái. Nhờ vậy bảng lương tháng cũ vẫn
	 * tra ra tên, mà người đó không còn hiện trong danh sách đang làm.
	 */
	public static function dat_nghi_viec( $u, $ma_nv, $ngay_nghi = '', $ly_do = '' ) {
		global $wpdb;
		$ma = trim( (string) $ma_nv );
		$cu = self::ho_so( $ma );
		if ( ! $cu ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ ' . $ma . '.' ); }
		if ( ! self::co_sua_ho_so( $u ) || ! self::co_quyen_coso( $u, $cu['cua_hang'] ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền với hồ sơ này.' );
		}
		$gc = 'Đã nghỉ';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $ngay_nghi ) ) ) {
			$gc .= ' từ ' . trim( (string) $ngay_nghi );
		}
		if ( '' !== trim( (string) $ly_do ) ) { $gc .= ' — ' . trim( (string) $ly_do ); }
		$wpdb->update( VHCC_DB::t( 'nhan_vien' ),
			array( 'trang_thai_lam_viec' => $gc, 'cap_nhat' => current_time( 'mysql' ) ),
			array( 'ma_nv' => $ma ) );
		return array( 'ok' => true, 'trangThai' => $gc );
	}

	/**
	 * XEM TRƯỚC khi đổi mã NV — hàm CHỈ ĐỌC.
	 *
	 * ⚠️ Phải có bước xem trước vì đổi mã là sửa MỌI hàng chấm công đã có của người đó. Đổi rồi mới
	 *    thấy sai thì không có đường lùi: hàng cũ đã mang mã mới, không phân biệt được với hàng
	 *    vốn thuộc mã mới.
	 */
	public static function xem_truoc_doi_ma( $u, $ma_cu, $ma_moi ) {
		global $wpdb;
		$cu  = trim( (string) $ma_cu );
		$moi = trim( (string) $ma_moi );
		if ( '' === $cu || '' === $moi ) { return array( 'ok' => false, 'error' => 'Thiếu mã cũ hoặc mã mới.' ); }
		if ( strtolower( $cu ) === strtolower( $moi ) ) {
			return array( 'ok' => false, 'error' => 'Hai mã giống nhau.' );
		}
		$hs = self::ho_so( $cu );
		if ( ! $hs ) { return array( 'ok' => false, 'error' => 'Không thấy hồ sơ mã ' . $cu . '.' ); }
		if ( self::ho_so( $moi ) ) {
			return array( 'ok' => false, 'error' => 'Mã mới "' . $moi . '" ĐÃ có hồ sơ khác dùng. '
				. 'Đổi vào là gộp công hai người.' );
		}
		$so_cc = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $cu ) );
		$coso_lq = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT coso FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $cu ) );
		$so_lich = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'lich_cv' ) . ' WHERE ma_nv=%s', $cu ) );
		$so_pq = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'phan_quyen' ) . ' WHERE LOWER(ma_cc_online)=LOWER(%s)', $cu ) );
		/* Mã song song: nếu mã cũ đang được khai là chạy song song với mã khác thì đổi mã làm hỏng
		   cặp đó. Nói ra trước, đừng để phát hiện sau. */
		$ss = VHCC_DB::rows( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE LOWER(ma_a)=LOWER(%s) OR LOWER(ma_b)=LOWER(%s)', $cu, $cu ) );
		return array( 'ok' => true, 'maCu' => $cu, 'maMoi' => $moi, 'hoTen' => $hs['ho_ten'],
			'soHangChamCong' => $so_cc, 'coSoLienQuan' => array_values( (array) $coso_lq ),
			'soOLich' => $so_lich, 'soDongPhanQuyen' => $so_pq, 'maSongSong' => $ss,
			'canhBao' => $ss ? 'Mã này đang khai chạy song song — đổi mã sẽ làm cặp mã đó trỏ sai.' : '' );
	}

	/**
	 * ĐỔI MÃ NV. Chỉ Admin/Quản lý, và phải có quyền trên MỌI cơ sở người đó có mặt.
	 *
	 * ⚠️ Người làm nhiều cơ sở: đổi mã phải sửa cả những cơ sở kia. Cho người chỉ quản một cơ sở
	 *    làm là họ ghi được vào dữ liệu cơ sở khác.
	 * ⚠️ KHÔNG đụng máy chấm công. Bên Apps Script hàm này còn xoá/tạo lại người trên máy Hikvision
	 *    (nên nó đòi có ảnh trước khi xoá). Ở đây phần máy vẫn do Apps Script + Firebase lo, nên
	 *    hàm này CHỈ đổi dữ liệu web — và phải nói rõ, không thì người dùng tưởng máy cũng đã đổi.
	 */
	public static function doi_ma_nv( $u, $ma_cu, $ma_moi ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Đổi mã NV ảnh hưởng mọi hàng chấm công đã có — '
				. self::LOI_QT );
		}
		$xt = self::xem_truoc_doi_ma( $u, $ma_cu, $ma_moi );
		if ( empty( $xt['ok'] ) ) { return $xt; }
		$cu  = $xt['maCu'];
		$moi = $xt['maMoi'];
		foreach ( $xt['coSoLienQuan'] as $cs ) {
			if ( ! self::co_quyen_coso( $u, $cs ) ) {
				return array( 'ok' => false, 'error' => 'Người này còn có mặt ở ' . $cs
					. ' — đổi mã phải sửa cả nơi đó, nên chỉ Admin làm được.' );
			}
		}
		foreach ( array( 'nhan_vien' => 'ma_nv', 'cham_cong' => 'ma_nv', 'lich_cv' => 'ma_nv',
			'doi_lich_cv' => 'ma_nv', 'cham_cong_nhiem_vu' => 'ma_nv', 'tang_cuong' => 'ma_nv',
			'ghi_chu' => 'ma_nv' ) as $bang => $cot ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . VHCC_DB::t( $bang ) . " SET $cot=%s WHERE $cot=%s", $moi, $cu ) );
		}
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . VHCC_DB::t( 'phan_quyen' ) . ' SET ma_cc_online=%s WHERE LOWER(ma_cc_online)=LOWER(%s)',
			$moi, $cu ) );
		return array( 'ok' => true, 'maCu' => $cu, 'maMoi' => $moi,
			'daSua' => $xt['soHangChamCong'] . ' hàng chấm công, ' . $xt['soOLich'] . ' ô lịch',
			'canhBao' => 'Chỉ đổi dữ liệu trên web. Người trên MÁY chấm công vẫn mang mã cũ — '
				. 'xoá/tạo lại trên máy làm ở màn "Máy & Firmware".' );
	}

	/**
	 * XEM TRƯỚC nhập nhân sự hàng loạt — CHỈ ĐỌC, không ghi một dòng nào.
	 * Trả từng dòng kèm việc sẽ làm (thêm / cập nhật / bỏ) và lý do bỏ.
	 */
	public static function xem_truoc_nhap( $u, $ds ) {
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Nhập nhân sự toàn chuỗi — ' . self::LOI_QT );
		}
		$ra = array();
		$thay = array();
		foreach ( (array) $ds as $i => $d ) {
			$d = (array) $d;
			$ma = trim( isset( $d['ma_nv'] ) ? (string) $d['ma_nv'] : '' );
			$dong = array( 'dong' => (int) $i + 1, 'maNV' => $ma,
				'hoTen' => trim( isset( $d['ho_ten'] ) ? (string) $d['ho_ten'] : '' ),
				'cuaHang' => self::chuan_coso( isset( $d['cua_hang'] ) ? $d['cua_hang'] : '' ) );
			if ( '' === $ma ) { $dong['viec'] = 'bỏ'; $dong['vaoSao'] = 'thiếu Mã NV'; $ra[] = $dong; continue; }
			/* Trùng mã TRONG CHÍNH tệp nhập: hai dòng cùng mã là một cái ghi đè cái kia mà không
			   ai thấy. Bắt ở bước xem trước, đừng để chạy xong mới biết mất một dòng. */
			if ( isset( $thay[ strtolower( $ma ) ] ) ) {
				$dong['viec'] = 'bỏ';
				$dong['vaoSao'] = 'trùng mã với dòng ' . $thay[ strtolower( $ma ) ] . ' trong cùng tệp';
				$ra[] = $dong; continue;
			}
			$thay[ strtolower( $ma ) ] = (int) $i + 1;
			$dong['viec'] = self::ho_so( $ma ) ? 'cập nhật' : 'thêm';
			$ra[] = $dong;
		}
		$dem = array( 'them' => 0, 'capNhat' => 0, 'bo' => 0 );
		foreach ( $ra as $x ) {
			if ( 'thêm' === $x['viec'] ) { $dem['them']++; }
			elseif ( 'cập nhật' === $x['viec'] ) { $dem['capNhat']++; }
			else { $dem['bo']++; }
		}
		return array( 'ok' => true, 'dong' => $ra, 'dem' => $dem );
	}

	/**
	 * NHẬP NHÂN SỰ HÀNG LOẠT. Từng dòng đi qua ĐÚNG `luu_ho_so` — không có đường ghi tắt.
	 * ⚠️ Đòi chạy `xem_truoc_nhap` sạch trước: `$xac_nhan` phải là số dòng mà bước xem trước đếm
	 *    được. Lệch số nghĩa là tệp đã đổi giữa hai bước, và lúc đó người bấm không biết mình đang
	 *    nhập cái gì.
	 */
	public static function nhap_hang_loat( $u, $ds, $xac_nhan = null ) {
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Nhập nhân sự toàn chuỗi — ' . self::LOI_QT );
		}
		$xt = self::xem_truoc_nhap( $u, $ds );
		if ( empty( $xt['ok'] ) ) { return $xt; }
		$se_ghi = $xt['dem']['them'] + $xt['dem']['capNhat'];
		if ( null !== $xac_nhan && (int) $xac_nhan !== $se_ghi ) {
			return array( 'ok' => false, 'error' => 'Số dòng sẽ ghi (' . $se_ghi . ') khác số đã xem '
				. 'trước (' . (int) $xac_nhan . ') — tệp đã đổi giữa hai bước. Xem lại rồi nhập.' );
		}
		$xong = array();
		$bo = array();
		$thay = array();
		foreach ( (array) $ds as $i => $d ) {
			$d = (array) $d;
			$ma = trim( isset( $d['ma_nv'] ) ? (string) $d['ma_nv'] : '' );
			if ( '' === $ma || isset( $thay[ strtolower( $ma ) ] ) ) {
				$bo[] = 'dòng ' . ( (int) $i + 1 ) . ( '' === $ma ? ': thiếu Mã NV' : ': trùng mã trong tệp' );
				continue;
			}
			$thay[ strtolower( $ma ) ] = 1;
			$r = self::luu_ho_so( $u, $d );
			if ( ! empty( $r['ok'] ) ) { $xong[] = $ma; }
			else { $bo[] = 'dòng ' . ( (int) $i + 1 ) . ' (' . $ma . '): ' . $r['error']; }
		}
		return array( 'ok' => true, 'xong' => $xong, 'bo' => $bo );
	}

	/** Bộ phận + nhóm lương của MỌI cơ sở — một bảng cho màn quản trị. */
	public static function bo_phan_va_coso() {
		$out = array();
		foreach ( self::ds_coso() as $cs ) {
			$nh = VHCC_Luong::nhom_coso( $cs );
			$out[] = array( 'coSo' => $cs, 'boPhan' => VHCC_Luong::bo_phan_cua( $cs ),
				'nhom' => $nh ? $nh['ten'] : '', 'theoGio' => VHCC_Luong::coso_tinh_theo_gio( $cs ),
				'laVanPhong' => VHCC_Luong::la_van_phong( $cs ) );
		}
		return $out;
	}

	/**
	 * Nhiệm vụ của một người TẠI một cơ sở, cho một ngày.
	 * ⚠️ Nhiệm vụ chỉ có nghĩa ở Nhóm Máy Tự Động — cơ sở khác thì từ chối, đừng ghi một giá trị
	 *    không ảnh hưởng gì rồi để người ta tưởng đã khai xong.
	 */
	public static function dat_nhiem_vu( $u, $ngay, $coso, $ma_nv, $nhiem_vu ) {
		global $wpdb;
		$coso = self::chuan_coso( $coso );
		if ( ! self::co_sua_ho_so( $u ) || ! self::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Không có quyền cơ sở này.' );
		}
		if ( ! VHCC_Luong::la_may_tu_dong( $coso ) ) {
			return array( 'ok' => false, 'error' => 'Nhiệm vụ chỉ có nghĩa ở Nhóm Máy Tự Động. '
				. 'Cơ sở "' . $coso . '" không thuộc nhóm đó nên khai vào cũng không đổi cách tính công.' );
		}
		$ngay = trim( (string) $ngay );
		$ma = trim( (string) $ma_nv );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) || '' === $ma ) {
			return array( 'ok' => false, 'error' => 'Thiếu ngày hoặc mã NV.' );
		}
		$nv = trim( (string) $nhiem_vu );
		$bang = VHCC_DB::t( 'cham_cong_nhiem_vu' );
		$cu = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $bang WHERE ngay=%s AND LOWER(coso)=LOWER(%s) AND ma_nv=%s", $ngay, $coso, $ma ) );
		$ghi = array( 'ngay' => $ngay, 'coso' => $coso, 'ma_nv' => $ma, 'nhiem_vu' => $nv,
			'ghi_luc' => current_time( 'mysql' ),
			'nguoi_ghi' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		// Ghi ĐÈ, không thêm dòng thứ hai cho cùng (ngày, cơ sở, mã).
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true, 'nhiemVu' => $nv );
	}

	/** Mã đã CHẤM CÔNG mà chưa có hồ sơ — người thật, công thật, mà bảng lương không tra ra tên. */
	public static function ds_chua_co_ho_so( $u ) {
		global $wpdb;
		$out = array();
		foreach ( VHCC_DB::rows(
			'SELECT c.coso, c.ma_nv, MAX(c.ho_ten) ho_ten, COUNT(*) so, MAX(c.ngay) ngay_cuoi FROM '
			. VHCC_DB::t( 'cham_cong' ) . ' c LEFT JOIN ' . VHCC_DB::t( 'nhan_vien' ) . ' n'
			. ' ON n.ma_nv = c.ma_nv WHERE n.id IS NULL GROUP BY c.coso, c.ma_nv ORDER BY so DESC' ) as $r ) {
			if ( ! self::co_quyen_coso( $u, $r['coso'] ) ) { continue; }
			$out[] = $r;
		}
		return $out;
	}

	public static function ds_ma_song_song() {
		return VHCC_DB::rows( 'SELECT * FROM ' . VHCC_DB::t( 'ma_song_song' ) . ' ORDER BY id DESC' );
	}

	public static function bo_ma_song_song( $u, $ma_a, $ma_b ) {
		global $wpdb;
		if ( ! self::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Mã song song ảnh hưởng cả chuỗi — ' . self::LOI_QT );
		}
		$so = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . VHCC_DB::t( 'ma_song_song' )
			. ' WHERE (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))'
			. ' OR (LOWER(ma_a)=LOWER(%s) AND LOWER(ma_b)=LOWER(%s))',
			$ma_a, $ma_b, $ma_b, $ma_a ) );
		return ( 0 === (int) $so )
			? array( 'ok' => false, 'error' => 'Không thấy cặp mã này.' )
			: array( 'ok' => true );
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
