<?php
/**
 * BẢNG CÔNG + LƯƠNG — đọc từ MySQL.
 *
 * Dịch từ `luongBangCongVaLuong` và hai engine của nó. Đây là chỗ sai một ly là sai lương cả
 * chuỗi, nên mọi ngưỡng, mọi mốc, mọi phép so đều lấy ĐÚNG từ Code.gs, không "làm cho gọn".
 *
 * =============================================================================================
 * ĐỊNH TUYẾN: MỘT CƠ SỞ DÙNG ENGINE NÀO
 * =============================================================================================
 *   Nhóm Máy Tự Động  -> engine MTD   (tên khớp ^POSH|^JP, HOẶC được tích "tính theo giờ")
 *   Bộ phận Văn phòng -> engine VP    (bộ phận KHỚP ĐÚNG 'Văn phòng')
 *   còn lại           -> BẢNG THÔ, `coLuong = false`
 *
 * ⚠️ "Còn lại" gồm Khu vui chơi và Chưa xếp, và nó KHÔNG được đoán một công thức nào. Anh Thắng
 *    chưa chốt công thức cho nhóm đó; bịa ra một cách tính "cho có số" là đưa ra một con số tiền
 *    mà không ai biết nó từ đâu, rồi có người được trả theo nó. Trả giờ vào/ra thô và nói thẳng
 *    là chưa có công thức.
 *
 * ⚠️ `la_van_phong` phải KHỚP ĐÚNG chuỗi 'Văn phòng'. Bộ phận nào không nằm trong danh sách thì
 *    chuẩn hoá thành 'Chưa xếp'. So kiểu LIKE là cơ sở khai "Văn phòng phụ" bị áp trọn công thức
 *    Văn phòng trong khi bản gốc không tính lương cho nó — tự sinh công thức là tự sinh tiền.
 *
 * =============================================================================================
 * KHÁC APPS SCRIPT MỘT CHỖ, VÀ ĐÂY LÀ CHỖ PHẢI NÓI RÕ
 * =============================================================================================
 * Bên Code.gs, engine Văn phòng có đường lùi `suyDoan`: dữ liệu CŨ (trước khi có hàng tách -CD)
 * không có hàng 2, nên nó suy ca tối/ca đêm từ hàng chính bằng phần giao nhau với từng khung giờ,
 * rồi đánh dấu là số suy đoán.
 *
 * Ở đây KHÔNG có đường lùi đó, và cố ý: MySQL chỉ có dữ liệu TỪ NGÀY CHUYỂN TRỞ ĐI, mà từ ngày đó
 * mọi lượt ca tối / ca đêm đều đã vào hàng `-CD` riêng. Không có "dữ liệu cũ thiếu hàng tách" nào
 * để mà suy. Viết một nhánh suy đoán không bao giờ chạy là để sẵn một đường sai không ai kiểm.
 * ⚠️ Nếu về sau có nhập lịch sử từ sheet vào MySQL thì PHẢI thêm nhánh đó lại — dữ liệu nhập vào
 *    sẽ có ngày không có hàng -CD, và lúc đó engine này tính THIẾU công tối / công đêm của họ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Luong {

	/** Bộ phận hợp lệ. Ngoài danh sách này -> 'Chưa xếp'. Giữ đúng BO_PHAN_DS của Code.gs. */
	const BP_DS = array( 'Máy tự động', 'Khu vui chơi', 'Văn phòng' );
	const BP_CHUA_XEP = 'Chưa xếp';

	/* Nhóm cơ sở xét bằng CHÍNH CÁI TÊN, đúng NHOM_CS của Code.gs. */
	const NHOM = array(
		array( 'ma' => 'TAU', 'ten' => 'Nhóm Tàu', 'mau' => '/^TUTU(_|$)/i' ),
		array( 'ma' => 'MTD', 'ten' => 'Nhóm Máy Tự Động', 'mau' => '/^(POSH|JP)(_|$)/i' ),
	);

	/**
	 * CỔNG QUYỀN của màn lương — bản dịch `loginLuong`: chỉ ADMIN và KE_TOAN.
	 *
	 * ⚠️ KHÔNG lọc theo cơ sở, khác hẳn màn Chấm công. Kế toán quản lý lương CẢ CHUỖI, không phải
	 *    một cơ sở, nên chặn theo cơ sở ở đây là kế toán không xem được đúng việc của họ. Cổng duy
	 *    nhất cần là danh sách vai trò này.
	 * ⚠️ Vai trò QUAN_LY và CUA_HANG_TRUONG KHÔNG vào được: bản gốc cố ý không cho, vì màn này là
	 *    TIỀN của cả chuỗi. Nới ra một vai trò là mở lương toàn chuỗi cho từng cửa hàng trưởng.
	 */
	public static function co_quyen( $vai_tro ) {
		$v = strtoupper( trim( (string) $vai_tro ) );
		return 'ADMIN' === $v || 'KE_TOAN' === $v;
	}

	// ======================================================================= tra cứu cấu hình

	public static function cai_dat( $khoa, $mac_dinh = null ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT gia_tri FROM ' . VHCC_DB::t( 'cai_dat' ) . ' WHERE khoa=%s', $khoa ) );
		if ( null === $v || '' === $v ) { return $mac_dinh; }
		$d = json_decode( $v, true );
		return null === $d ? $v : $d;
	}

	public static function bo_chu( $s ) {
		$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
		$cap = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $cap as $tron => $co ) {
			$s = str_replace( preg_split( '//u', $co, -1, PREG_SPLIT_NO_EMPTY ), $tron, $s );
		}
		return preg_replace( '/[^a-z0-9]+/', '', $s );
	}

	/** Bộ phận của một cơ sở, ĐÃ chuẩn hoá về danh sách hợp lệ. */
	public static function bo_phan_cua( $coso ) {
		global $wpdb;
		$coso = trim( preg_replace( '/^CS_/', '', (string) $coso ) );
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT bo_phan FROM ' . VHCC_DB::t( 'bo_phan_coso' )
			. ' WHERE LOWER(coso)=LOWER(%s) LIMIT 1', $coso ) );
		$bp = trim( (string) $v );
		return in_array( $bp, self::BP_DS, true ) ? $bp : self::BP_CHUA_XEP;
	}

	/** Khớp ĐÚNG 'Văn phòng' — xem khối cảnh báo ở đầu tệp. */
	public static function la_van_phong( $coso ) {
		return 'Văn phòng' === self::bo_phan_cua( $coso );
	}

	/** Cơ sở được tích "tính theo giờ". Khoá so sánh đã bỏ dấu để gõ kiểu nào cũng trúng. */
	public static function coso_tinh_theo_gio( $coso ) {
		$k = self::bo_chu( preg_replace( '/^CS_/', '', (string) $coso ) );
		if ( '' === $k ) { return false; }
		$ds = self::cai_dat( 'MTD_CO_SO_THEO_GIO', array() );
		if ( ! is_array( $ds ) ) { return false; }
		foreach ( $ds as $x ) { if ( self::bo_chu( $x ) === $k ) { return true; } }
		return false;
	}

	public static function nhom_coso( $coso ) {
		$s = trim( preg_replace( '/^CS_/', '', (string) $coso ) );
		if ( '' === $s ) { return null; }
		foreach ( self::NHOM as $n ) {
			if ( preg_match( $n['mau'], $s ) ) { return array( 'ma' => $n['ma'], 'ten' => $n['ten'] ); }
		}
		/* Cơ sở được tích "tính theo giờ" thì thuộc luôn Nhóm Máy Tự Động dù tên không phải
		   POSH/JP. Không có chỗ này thì đặt tên `CS_VE_SINH_GHE` là bảng lương từ chối thẳng, tức
		   để CÁCH ĐẶT TÊN quyết định cách tính tiền. */
		if ( self::coso_tinh_theo_gio( $s ) ) { return array( 'ma' => 'MTD', 'ten' => 'Nhóm Máy Tự Động' ); }
		return null;
	}

	public static function la_may_tu_dong( $coso ) {
		$n = self::nhom_coso( $coso );
		return $n && 'MTD' === $n['ma'];
	}

	// ======================================================================= định tuyến

	/** 'yyyy-MM' từ 'Tháng MM-yyyy' hoặc 'yyyy-MM'. Rỗng = tháng không hợp lệ. */
	public static function tien_to_thang( $nhan ) {
		$s = trim( str_replace( 'Tháng ', '', (string) $nhan ) );
		if ( preg_match( '/^(\d{4})-(\d{2})$/', $s, $m ) ) {
			return ( (int) $m[2] >= 1 && (int) $m[2] <= 12 ) ? $s : '';
		}
		if ( preg_match( '/^(\d{1,2})-(\d{4})$/', $s, $m ) ) {
			$mm = (int) $m[1];
			return ( $mm >= 1 && $mm <= 12 ) ? ( $m[2] . '-' . sprintf( '%02d', $mm ) ) : '';
		}
		return '';
	}

	public static function bang_cong_va_luong( $coso, $thang ) {
		$coso = trim( preg_replace( '/^CS_/', '', (string) $coso ) );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		$tt = self::tien_to_thang( $thang );
		if ( '' === $tt ) { return array( 'ok' => false, 'error' => 'Tháng không hợp lệ.' ); }

		if ( self::la_may_tu_dong( $coso ) ) {
			$r = self::mtd_tinh_luong( $coso, $tt );
			if ( isset( $r['error'] ) ) { return array( 'ok' => false, 'error' => $r['error'] ); }
			return array( 'ok' => true, 'coLuong' => true, 'boPhan' => 'Máy tự động', 'kieu' => 'mtd', 'mtd' => $r );
		}
		if ( self::la_van_phong( $coso ) ) {
			$r = self::vp_bang_cong_va_luong( $coso, $tt );
			if ( isset( $r['error'] ) ) { return array( 'ok' => false, 'error' => $r['error'] ); }
			return array( 'ok' => true, 'coLuong' => true, 'boPhan' => 'Văn phòng', 'kieu' => 'vp', 'vp' => $r );
		}
		return array( 'ok' => true, 'coLuong' => false, 'boPhan' => self::bo_phan_cua( $coso ),
			'kieu' => 'tho', 'tho' => self::bang_cong_tho( $coso, $tt ) );
	}

	// ======================================================================= đọc dữ liệu

	/** Mọi hàng chấm công của (cơ sở, tháng). Giờ trả về là SỐ GIÂY (đã trải phẳng nếu là -CD). */
	public static function doc_thang( $coso, $tt ) {
		global $wpdb;
		return VHCC_DB::rows( $wpdb->prepare(
			'SELECT ngay, ma_nv, hau_to, ho_ten, gio_vao_giay, gio_ra_giay FROM '
			. VHCC_DB::t( 'cham_cong' )
			. ' WHERE coso=%s AND ngay LIKE %s ORDER BY ngay, ma_nv, hau_to',
			$coso, $tt . '-%' ) );
	}

	// ======================================================================= engine MTD

	public static function mtd_gia() {
		$o = array( 'congThuong' => 0, 'congCuoiTuan' => 0, 'congLe' => 0,
			'gioThuong' => 0, 'gioCuoiTuan' => 0, 'gioLe' => 0 );
		$luu = self::cai_dat( 'MTD_DON_GIA', array() );
		if ( is_array( $luu ) ) {
			foreach ( $o as $k => $v ) {
				if ( isset( $luu[ $k ] ) && is_numeric( $luu[ $k ] ) && (float) $luu[ $k ] > 0 ) {
					$o[ $k ] = (float) $luu[ $k ];
				}
			}
		}
		return $o;
	}

	/** Ngày lễ: 'yyyy-MM-dd' (một lần) hoặc 'MM-dd' (lặp hằng năm). */
	public static function mtd_ngay_le() {
		$ds = self::cai_dat( 'MTD_NGAY_LE', array() );
		return is_array( $ds ) ? $ds : array();
	}

	public static function mtd_la_le( $ngay, $ds_le ) {
		$d = trim( (string) $ngay );
		if ( strlen( $d ) < 10 ) { return false; }
		$md = substr( $d, 5 );
		foreach ( (array) $ds_le as $x ) {
			$x = trim( (string) $x );
			if ( $x === $d || ( 5 === strlen( $x ) && $x === $md ) ) { return true; }
		}
		return false;
	}

	public static function la_cuoi_tuan( $ngay ) {
		$g = (int) gmdate( 'w', strtotime( $ngay . ' 00:00:00 UTC' ) );
		return 0 === $g || 6 === $g;
	}

	/** 'le' | 'cuoiTuan' | 'thuong'. Lễ ĐÈ cuối tuần: lễ rơi vào chủ nhật thì ăn giá lễ. */
	public static function mtd_loai_ngay( $ngay, $ds_le ) {
		if ( self::mtd_la_le( $ngay, $ds_le ) ) { return 'le'; }
		return self::la_cuoi_tuan( $ngay ) ? 'cuoiTuan' : 'thuong';
	}

	/** Nhiệm vụ suy từ hậu tố hàng. Hàng chính -> việc đầu trong hồ sơ, rỗng thì mặc định. */
	public static function nhiem_vu_cua_hang( $hau_to, $ds_nv ) {
		if ( 'TG' === $hau_to ) { return 'Trực Ghế Posh - JP'; }
		if ( 'TT' === $hau_to ) { return 'Thu Tiền - Vệ Sinh'; }
		return ( is_array( $ds_nv ) && $ds_nv ) ? $ds_nv[0] : '';
	}

	public static function mtd_tinh_luong( $coso, $tt ) {
		$gia   = self::mtd_gia();
		$ds_le = self::mtd_ngay_le();
		$cs_gio = self::coso_tinh_theo_gio( $coso );
		$ho_so = self::ho_so_nhiem_vu();

		$by = array();
		$thu_tu = array();
		$chi_tiet = array();
		foreach ( self::doc_thang( $coso, $tt ) as $r ) {
			// Thiếu vào HOẶC thiếu ra -> KHÔNG tính. Đúng bản gốc: không đoán nửa ngày.
			if ( null === $r['gio_vao_giay'] || null === $r['gio_ra_giay'] ) { continue; }
			$vao_m = intdiv( (int) $r['gio_vao_giay'], 60 );
			$ra_m  = intdiv( (int) $r['gio_ra_giay'], 60 );
			$ma    = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$hau_to = strtoupper( trim( (string) $r['hau_to'] ) );
			$nv     = self::nhiem_vu_cua_hang( $hau_to, isset( $ho_so[ strtolower( $ma ) ] ) ? $ho_so[ strtolower( $ma ) ] : array() );

			/* HAI đường vào "tính theo giờ", chỉ cần MỘT đúng: cả cơ sở được tích theo giờ, HOẶC
			   chính người đó khai nhiệm vụ Trực Ghế. Giữ cả hai — bỏ đường cũ là mọi người đang
			   khai nhiệm vụ đó lập tức bị tính theo CÔNG, sai tiền ngay tháng này mà không có gì báo. */
			$theo_gio = $cs_gio || ( 'Trực Ghế Posh - JP' === $nv );
			$loai = self::mtd_loai_ngay( $r['ngay'], $ds_le );
			$hoa  = strtoupper( substr( $loai, 0, 1 ) ) . substr( $loai, 1 );

			if ( ! isset( $by[ $ma ] ) ) {
				$by[ $ma ] = array( 'ma' => $ma, 'ten' => (string) $r['ho_ten'],
					'cong' => array( 'thuong' => 0, 'cuoiTuan' => 0, 'le' => 0 ),
					'gio'  => array( 'thuong' => 0.0, 'cuoiTuan' => 0.0, 'le' => 0.0 ),
					'tienCong' => 0, 'tienGio' => 0, 'tong' => 0 );
				$thu_tu[] = $ma;
			}
			if ( '' === $by[ $ma ]['ten'] ) { $by[ $ma ]['ten'] = $ma; }

			if ( $theo_gio ) {
				/* Ca qua đêm (ra < vào) cộng trọn một vòng 24h. Không xử thì ra số ÂM và trừ thẳng
				   vào lương người ta. Hàng -CD đã trải phẳng nên ra > vào, nhánh này không chạm. */
				$phut = ( $ra_m > $vao_m ) ? ( $ra_m - $vao_m ) : ( $ra_m + 1440 - $vao_m );
				$so   = round( $phut / 60, 2 );
				$dg   = $gia[ 'gio' . $hoa ];
				$by[ $ma ]['gio'][ $loai ] += $so;
				$by[ $ma ]['tienGio']      += (int) round( $so * $dg );
			} else {
				$so = 1;                                  // đủ vào + ra = 1 công, không xét dài ngắn
				$dg = $gia[ 'cong' . $hoa ];
				$by[ $ma ]['cong'][ $loai ] += 1;
				$by[ $ma ]['tienCong']      += (int) round( $dg );
			}
			$by[ $ma ]['tong'] = $by[ $ma ]['tienCong'] + $by[ $ma ]['tienGio'];
			$chi_tiet[] = array( 'date' => $r['ngay'], 'ma' => $ma . ( '' !== $hau_to ? '-' . $hau_to : '' ),
				'ten' => $by[ $ma ]['ten'], 'nhiemVu' => $nv, 'loaiNgay' => $loai,
				'vao' => VHCC_DB::hhmmss( $r['gio_vao_giay'] ), 'ra' => VHCC_DB::hhmmss( $r['gio_ra_giay'] ),
				'theoGio' => $theo_gio, 'soLuong' => $so, 'donGia' => $dg, 'tien' => (int) round( $so * $dg ) );
		}

		$rows = array();
		foreach ( $thu_tu as $k ) { $rows[] = $by[ $k ]; }
		usort( $rows, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		usort( $chi_tiet, function ( $a, $b ) {
			if ( $a['date'] !== $b['date'] ) { return strcmp( $a['date'], $b['date'] ); }
			return strcmp( $a['ten'], $b['ten'] );
		} );

		$tong = array( 'tienCong' => 0, 'tienGio' => 0, 'tong' => 0, 'soCong' => 0, 'soGio' => 0.0 );
		foreach ( $rows as $r ) {
			$tong['tienCong'] += $r['tienCong'];
			$tong['tienGio']  += $r['tienGio'];
			$tong['tong']     += $r['tong'];
			$tong['soCong']   += $r['cong']['thuong'] + $r['cong']['cuoiTuan'] + $r['cong']['le'];
			$tong['soGio']    += $r['gio']['thuong'] + $r['gio']['cuoiTuan'] + $r['gio']['le'];
		}
		$tong['soGio'] = round( $tong['soGio'], 2 );

		$chua_khai = true;
		foreach ( $gia as $v ) { if ( $v > 0 ) { $chua_khai = false; } }
		return array( 'station' => $coso, 'month' => $tt, 'gia' => $gia, 'ngayLe' => $ds_le,
			'rows' => $rows, 'detail' => $chi_tiet, 'tong' => $tong, 'chuaKhaiGia' => $chua_khai,
			'theoGioCaCoSo' => $cs_gio );
	}

	/** {mã NV (chữ thường): [nhiệm vụ,…]} từ hồ sơ. */
	public static function ho_so_nhiem_vu() {
		global $wpdb;
		$out = array();
		foreach ( VHCC_DB::rows( 'SELECT ma_nv, nhiem_vu FROM ' . VHCC_DB::t( 'nhan_vien' ) ) as $r ) {
			$ds = array();
			foreach ( explode( ',', (string) $r['nhiem_vu'] ) as $x ) {
				$x = trim( $x );
				if ( '' !== $x ) { $ds[] = $x; }
			}
			$out[ strtolower( trim( (string) $r['ma_nv'] ) ) ] = $ds;
		}
		return $out;
	}

	// ======================================================================= engine Văn phòng

	public static function vp_cfg() {
		$mac_dinh = array(
			'ngayTu' => '08:30', 'ngayDen' => '17:00', 'ngayMin' => 7, 'ngayMax' => 9,
			'duoiMin' => 'tyle', 'gioChuan' => 8,
			'bacNua' => 4, 'bacMot' => 9, 'bacRuoi' => 12,
			'demToiThieuGio' => 0, 'nuaTuGio' => 4, 'graceRaPhut' => 60,
			'ktThu7Tu' => '08:30', 'ktThu7Den' => '12:00', 'ktThu7Min' => 3,
			'ktMaNV' => array(), 'ktChuNhatNghi' => true,
			'demTu' => '21:00', 'demDen' => '06:00',
			'demCong' => 1, 'demCongBu' => 1, 'demBuKhiDaLam' => 1, 'tangCaCong' => 0.5,
			'ngayCongThang' => 0,
		);
		$luu = self::cai_dat( 'VP_CONG_CFG', array() );
		if ( is_array( $luu ) ) {
			foreach ( $mac_dinh as $k => $v ) {
				if ( isset( $luu[ $k ] ) && '' !== $luu[ $k ] && null !== $luu[ $k ] ) { $mac_dinh[ $k ] = $luu[ $k ]; }
			}
		}
		$kt = $mac_dinh['ktMaNV'];
		if ( ! is_array( $kt ) ) { $kt = explode( ',', (string) $kt ); }
		$sach = array();
		foreach ( $kt as $x ) {
			$x = strtolower( trim( (string) $x ) );
			if ( '' !== $x ) { $sach[] = $x; }
		}
		$mac_dinh['ktMaNV'] = $sach;
		return $mac_dinh;
	}

	/**
	 * Phút làm của một cặp {vao, ra} (PHÚT, có thể đã trải phẳng) nằm trong khung [tu, den].
	 *
	 * ⚠️ Khung vắt qua nửa đêm (21:00–06:00) phải cộng một ngày vào mốc cuối, không thì phần giao
	 *    luôn ra 0 và công đêm mất sạch. Cặp giờ cũng vậy — nhưng hàng -CD trong MySQL ĐÃ trải
	 *    phẳng nên `ra > vao` sẵn, nhánh cộng bù chỉ chạm hàng chính vắt qua nửa đêm.
	 */
	public static function vp_phut_trong_khung( $vao_m, $ra_m, $tu, $den ) {
		if ( null === $vao_m || null === $ra_m ) { return 0; }
		$w1 = VHCC_DB::giay( $tu );
		$w2 = VHCC_DB::giay( $den );
		if ( null === $w1 || null === $w2 ) { return 0; }
		$w1 = intdiv( $w1, 60 );
		$w2 = intdiv( $w2, 60 );
		if ( $ra_m <= $vao_m ) { $ra_m += 1440; }
		if ( $w2 <= $w1 ) { $w2 += 1440; }
		return max( 0, min( $ra_m, $w2 ) - max( $vao_m, $w1 ) );
	}

	/**
	 * Số công ngày từ số phút — bản dịch `_vpCongNgayTuPhut`.
	 *
	 * ⚠️ BẬC THANG xét TRƯỚC chốt `gio >= min`, vì mốc 12h nằm TRÊN `min` (7–9 tiếng): để sau thì
	 *    người làm 10 tiếng bị chốt kia trả 1 công và bậc 1.5 không bao giờ chạm tới.
	 * ⚠️ `bacMot` mặc định là 9, KHÔNG phải 8. Khung ca ngày chuẩn 08:30–17:00 dài 8.5 tiếng —
	 *    với mốc 8 thì NGÀY LÀM BÌNH THƯỜNG rơi vào bậc "<12h" = 1.5 công, tức lương CẢ CƠ SỞ
	 *    tăng 50%, không riêng người thiếu giờ. Anh Thắng chốt 9 sau khi xem bảng đối chiếu.
	 */
	public static function vp_cong_ngay_tu_phut( $phut, $min, $cfg ) {
		if ( null === $phut || $phut <= 0 ) { return 0.0; }
		$gio = $phut / 60;
		if ( 'bacthang' === $cfg['duoiMin'] ) {
			$b1 = (float) ( $cfg['bacNua'] ? $cfg['bacNua'] : 4 );
			$b2 = (float) ( $cfg['bacMot'] ? $cfg['bacMot'] : 8 );
			$b3 = (float) ( $cfg['bacRuoi'] ? $cfg['bacRuoi'] : 12 );
			if ( $gio < $b1 ) { return 0.5; }
			if ( $gio < $b2 ) { return 1.0; }
			if ( $gio < $b3 ) { return 1.5; }
			return 1.5;                       // từ b3 trở lên: giữ trần 1.5, không thưởng thêm
		}
		if ( $gio >= (float) $min ) { return 1.0; }
		if ( 'tron' === $cfg['duoiMin'] ) { return 1.0; }
		if ( 'khong' === $cfg['duoiMin'] ) { return 0.0; }
		if ( 'nua' === $cfg['duoiMin'] ) {
			return ( $gio >= (float) ( $cfg['nuaTuGio'] ? $cfg['nuaTuGio'] : 4 ) ) ? 0.5 : 0.0;
		}
		$g = (float) ( $cfg['gioChuan'] ? $cfg['gioChuan'] : 8 );
		if ( ! ( $g > 0 ) ) { $g = 8; }
		return round( $gio / $g, 2 );                                            // 'tyle'
	}

	/**
	 * HÀNG 2 (-CD) là ca gì — bản dịch `_vpCaHang2`. Chỉ cần MỘT giờ là kết luận được.
	 *   'dem'    có giờ từ demTu trở đi, HOẶC có giờ trước demDen (sau nửa đêm)
	 *   'tangca' mọi giờ nằm trong [ngayDen, demTu)
	 *   'la'     có giờ nhưng nằm TRONG ca ngày (< ngayDen) -> KHÔNG tính, để người ta soi
	 *   ''       không có giờ nào
	 *
	 * ⚠️ Trả 'la' thay vì tính bừa: giờ ca ngày lọt vào hàng 2 là dấu hiệu sửa tay hoặc chấm sai
	 *    chỗ. Tính thành tăng ca là tự cộng tiền cho một cái sai.
	 * ⚠️ Xét trên GIỜ TRONG NGÀY (chia dư một ngày), không trên trục đã trải phẳng — để giống
	 *    hệt bản gốc, nơi phép so chạy trên chuỗi 'HH:mm:ss' thô.
	 */
	public static function vp_ca_hang2( $cfg, $vao_giay, $ra_giay ) {
		$gio = array();
		foreach ( array( $vao_giay, $ra_giay ) as $g ) {
			if ( null !== $g && '' !== $g ) { $gio[] = intdiv( ( (int) $g ) % VHCC_DB::NGAY_GIAY, 60 ); }
		}
		if ( ! $gio ) { return array( 'loai' => '', 'gio' => array() ); }
		$dem_tu   = intdiv( VHCC_DB::giay( $cfg['demTu'] ), 60 );
		$dem_den  = intdiv( VHCC_DB::giay( $cfg['demDen'] ), 60 );
		$ngay_den = intdiv( VHCC_DB::giay( $cfg['ngayDen'] ), 60 );
		foreach ( $gio as $m ) {
			if ( $m >= $dem_tu || $m < $dem_den ) { return array( 'loai' => 'dem', 'gio' => $gio ); }
		}
		foreach ( $gio as $m ) {
			if ( $m < $ngay_den ) { return array( 'loai' => 'la', 'gio' => $gio ); }
		}
		return array( 'loai' => 'tangca', 'gio' => $gio );
	}

	public static function ngay_sau( $ngay ) { return gmdate( 'Y-m-d', strtotime( $ngay . ' 12:00:00 UTC +1 day' ) ); }
	public static function la_thu7( $ngay ) { return 6 === (int) gmdate( 'w', strtotime( $ngay . ' 00:00:00 UTC' ) ); }
	public static function la_chu_nhat( $ngay ) { return 0 === (int) gmdate( 'w', strtotime( $ngay . ' 00:00:00 UTC' ) ); }

	/**
	 * Công CẢ THÁNG của MỘT người. Hàm THUẦN — không đọc bảng nào.
	 *
	 * ⚠️ PHẢI tính theo THÁNG, không tính từng ngày rời: ca đêm ở hàng 2 ngày D cho công vào NGÀY
	 *    D+1, nên một ngày có thể nhận công từ ngày HÔM TRƯỚC. Tính từng ngày độc lập là không bao
	 *    giờ ra đúng.
	 *
	 * `$theo_ngay` = array( 'yyyy-MM-dd' => array( 'chinh' => [vao,ra], 'dem' => [vao,ra] ) ) — giây.
	 */
	public static function vp_tinh_nguoi( $cfg, $la_ke_toan, $theo_ngay ) {
		$out = array();
		$o = function ( $ngay ) use ( &$out ) {
			if ( ! isset( $out[ $ngay ] ) ) {
				$out[ $ngay ] = array( 'ngay' => $ngay, 'congNgay' => 0.0, 'congTangCa' => 0.0,
					'congDem' => 0.0, 'congBu' => 0.0, 'tong' => 0.0, 'phutNgay' => 0, 'khung' => '',
					'kt7' => false, 'ktCnNghi' => false, 'caLa' => false, 'demTuNgay' => '',
					'demSangNgay' => '', 'demThieuGio' => false, 'demChuaDuCap' => false, 'gioDemThuc' => 0.0 );
			}
			return $ngay;
		};

		$ngay_ds = array_keys( $theo_ngay );
		sort( $ngay_ds );
		foreach ( $ngay_ds as $ngay ) {
			$h = $theo_ngay[ $ngay ];
			$o( $ngay );

			/* ----- HÀNG 1: công ngày ----- */
			$kt7  = $la_ke_toan && self::la_thu7( $ngay );
			$ktcn = $la_ke_toan && $cfg['ktChuNhatNghi'] && self::la_chu_nhat( $ngay );
			$tu   = $kt7 ? $cfg['ktThu7Tu'] : $cfg['ngayTu'];
			$den  = $kt7 ? $cfg['ktThu7Den'] : $cfg['ngayDen'];
			$min  = $kt7 ? (float) $cfg['ktThu7Min'] : (float) $cfg['ngayMin'];
			$out[ $ngay ]['kt7'] = $kt7;
			$out[ $ngay ]['ktCnNghi'] = $ktcn;
			$out[ $ngay ]['khung'] = $tu . '-' . $den;
			$chinh = isset( $h['chinh'] ) ? $h['chinh'] : null;
			$out[ $ngay ]['phutNgay'] = $chinh
				? self::vp_phut_trong_khung( self::pm( $chinh[0] ), self::pm( $chinh[1] ), $tu, $den ) : 0;
			/* Kế toán CHỦ NHẬT: lịch nghỉ -> 0 công ngày. Vẫn GIỮ số phút để giao diện hiện được
			   "đi làm chủ nhật nhưng chủ nhật là ngày nghỉ", không xoá dấu vết. */
			$out[ $ngay ]['congNgay'] = $ktcn ? 0.0
				: self::vp_cong_ngay_tu_phut( $out[ $ngay ]['phutNgay'], $min, $cfg );

			/* ----- HÀNG 2: tăng ca (cùng ngày) hoặc ca đêm (dồn sang NGÀY HÔM SAU) ----- */
			$dem = isset( $h['dem'] ) ? $h['dem'] : null;
			$ca  = self::vp_ca_hang2( $cfg, $dem ? $dem[0] : null, $dem ? $dem[1] : null );
			if ( 'tangca' === $ca['loai'] ) {
				$out[ $ngay ]['congTangCa'] += (float) $cfg['tangCaCong'];
			} elseif ( 'la' === $ca['loai'] ) {
				$out[ $ngay ]['caLa'] = true;
			} elseif ( 'dem' === $ca['loai'] ) {
				$toi_thieu = (float) $cfg['demToiThieuGio'];
				$du_cap = $dem && null !== $dem[0] && null !== $dem[1];
				$out[ $ngay ]['gioDemThuc'] = $du_cap
					? round( self::vp_phut_trong_khung( self::pm( $dem[0] ), self::pm( $dem[1] ),
						$cfg['demTu'], $cfg['demDen'] ) / 60, 2 ) : 0.0;
				/* ⚠️ CHỈ CÓ MỘT GIỜ (quên chấm ra) thì không cách nào biết ca dài bao lâu. KHÔNG
				   được lấy cớ đó để cắt công — cắt ngầm là trừ tiền một người vì cái máy lỗi. Vẫn
				   tính, nhưng đánh dấu để người ta tự soi. */
				$out[ $ngay ]['demChuaDuCap'] = ( $toi_thieu > 0 && ! $du_cap );
				$out[ $ngay ]['demThieuGio']  = ( $toi_thieu > 0 && $du_cap
					&& $out[ $ngay ]['gioDemThuc'] < $toi_thieu );
				if ( ! $out[ $ngay ]['demThieuGio'] ) {
					$sau = self::ngay_sau( $ngay );
					$o( $sau );
					$out[ $sau ]['congDem']  += (float) $cfg['demCong'];
					$out[ $sau ]['demTuNgay'] = $ngay;
					/* GIỮ ngày bắt đầu ca đêm dù nó 0 công. Xoá đi thì trên bảng chỉ thấy ngày hôm
					   sau tự nhiên có 2 công mà KHÔNG BIẾT TỪ ĐÂU RA — không soi được là không
					   kiểm được lương. */
					$out[ $ngay ]['demSangNgay'] = $sau;
				}
			}
		}

		// Công BÙ — làm sau cùng vì cần biết ngày đó đã có công ngày chưa.
		foreach ( array_keys( $out ) as $ngay ) {
			if ( ! $out[ $ngay ]['congDem'] ) { continue; }
			$da_lam = ( $out[ $ngay ]['congNgay'] > 0 || $out[ $ngay ]['congTangCa'] > 0 );
			if ( ! $da_lam || (int) $cfg['demBuKhiDaLam'] ) {
				$out[ $ngay ]['congBu'] = (float) $cfg['demCongBu'];
			}
		}
		foreach ( array_keys( $out ) as $ngay ) {
			$r = &$out[ $ngay ];
			$r['tong'] = round( $r['congNgay'] + $r['congTangCa'] + $r['congDem'] + $r['congBu'], 2 );
			/* `demThieuGio` cũng phải GIỮ dòng lại: đó là ngày người ta CÓ đi làm đêm mà không được
			   công. Xoá đi thì ca đêm bị loại biến mất khỏi bảng, không ai biết mà kiểm. */
			if ( $r['tong'] <= 0 && ! $r['caLa'] && '' === $r['demSangNgay'] && ! $r['demThieuGio']
				&& ! ( $r['ktCnNghi'] && $r['phutNgay'] > 0 ) ) {
				unset( $out[ $ngay ] );
			}
			unset( $r );
		}
		ksort( $out );
		return $out;
	}

	/** Giây -> phút, giữ null. */
	private static function pm( $giay ) {
		return ( null === $giay || '' === $giay ) ? null : intdiv( (int) $giay, 60 );
	}

	/** Số ngày công CHUẨN của đúng (cơ sở, tháng). 0 = CHƯA KHAI — không mượn tháng khác. */
	public static function vp_nc_lay( $coso, $tt ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT ngay_cong FROM ' . VHCC_DB::t( 'vp_ngay_cong' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND thang=%s', $coso, $tt ) );
		$n = ( null === $v ) ? 0 : (float) $v;
		return $n > 0 ? $n : 0;
	}

	/** GỢI Ý cho ô nhập: tháng gần nhất ĐÃ khai tại CHÍNH cơ sở đó. KHÔNG dùng để tính tiền. */
	public static function vp_nc_goi_y( $coso, $tt ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT ngay_cong FROM ' . VHCC_DB::t( 'vp_ngay_cong' )
			. ' WHERE LOWER(coso)=LOWER(%s) AND thang < %s AND ngay_cong > 0'
			. ' ORDER BY thang DESC LIMIT 1', $coso, $tt ) );
		return null === $v ? null : (float) $v;
	}

	public static function vp_luong_co_ban() {
		global $wpdb;
		$out = array();
		foreach ( VHCC_DB::rows( 'SELECT ma_nv, luong_co_ban FROM ' . VHCC_DB::t( 'nhan_vien' ) ) as $r ) {
			$out[ strtolower( trim( (string) $r['ma_nv'] ) ) ] = (float) $r['luong_co_ban'];
		}
		return $out;
	}

	/**
	 * Gắn tiền vào từng dòng người.
	 *   đơn giá 1 công = lương cơ bản tháng ÷ số ngày công
	 *   tiền           = lương cơ bản × TỔNG CÔNG ÷ số ngày công
	 *
	 * ⚠️ NHÂN TRƯỚC RỒI CHIA, đúng bản gốc `Math.round(lcb * e.tong / nc)`. Chia trước rồi nhân là
	 *    làm tròn đơn giá một lần rồi nhân lên, lệch tới cả nghìn đồng mỗi người.
	 * ⚠️ `nc = 0` (chưa khai) thì tiền là 0 và có cờ báo thiếu — KHÔNG đoán 26. Đoán mẫu số là sai
	 *    tiền của MỌI người cùng lúc, mà bảng vẫn có số nên chẳng ai nghi.
	 */
	public static function vp_gan_tien( &$rows, $luong, $nc ) {
		$nc = (float) $nc;
		$thieu = array();
		$tong_tien = 0;
		foreach ( $rows as &$e ) {
			$lcb = isset( $luong[ strtolower( $e['ma'] ) ] ) ? (float) $luong[ strtolower( $e['ma'] ) ] : 0.0;
			$e['luongThang'] = $lcb;
			$e['donGiaCong'] = ( $nc > 0 && $lcb > 0 ) ? (int) round( $lcb / $nc ) : 0;
			$e['tien']       = ( $nc > 0 && $lcb > 0 ) ? (int) round( $lcb * $e['tong'] / $nc ) : 0;
			if ( ! $lcb ) { $thieu[] = $e['ten'] . ' (' . $e['ma'] . ')'; }
			$tong_tien += $e['tien'];
		}
		unset( $e );
		return array( 'ngayCongThang' => $nc, 'chuaKhaiNgayCong' => ! ( $nc > 0 ),
			'thieuLuong' => $thieu, 'tongTien' => $tong_tien );
	}

	public static function vp_bang_cong_va_luong( $coso, $tt ) {
		$cfg = self::vp_cfg();

		/* Gom theo NGƯỜI rồi theo NGÀY. Hàng chính -> 'chinh', hàng -CD -> 'dem'.
		   ⚠️ Hàng -CT (công tối, hậu tố CŨ không còn ghi mới) cũng gom vào 'dem': bản gốc giữ nó
		      để hàng lỡ tạo vẫn đọc được, bỏ đi là mất công của ngày đó. */
		$nguoi = array();
		$ten   = array();
		foreach ( self::doc_thang( $coso, $tt ) as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$hau_to = strtoupper( trim( (string) $r['hau_to'] ) );
			$khe    = ( 'CD' === $hau_to || 'CT' === $hau_to ) ? 'dem' : ( '' === $hau_to ? 'chinh' : null );
			if ( null === $khe ) { continue; }         // hậu tố nhiệm vụ MTD -> không thuộc Văn phòng
			if ( ! isset( $nguoi[ $ma ] ) ) { $nguoi[ $ma ] = array(); }
			if ( ! isset( $ten[ $ma ] ) || '' === $ten[ $ma ] ) { $ten[ $ma ] = (string) $r['ho_ten']; }
			$nguoi[ $ma ][ $r['ngay'] ][ $khe ] = array( $r['gio_vao_giay'], $r['gio_ra_giay'] );
		}

		$rows = array();
		$chi_tiet = array();
		$tong = array( 'congNgay' => 0.0, 'congTangCa' => 0.0, 'congDem' => 0.0, 'congBu' => 0.0, 'tong' => 0.0 );
		foreach ( $nguoi as $ma => $theo_ngay ) {
			$la_kt = in_array( strtolower( $ma ), $cfg['ktMaNV'], true );
			$ngay_ds = self::vp_tinh_nguoi( $cfg, $la_kt, $theo_ngay );
			$e = array( 'ma' => $ma, 'ten' => ( '' !== $ten[ $ma ] ? $ten[ $ma ] : $ma ), 'laKeToan' => $la_kt,
				'congNgay' => 0.0, 'congTangCa' => 0.0, 'congDem' => 0.0, 'congBu' => 0.0, 'tong' => 0.0,
				'soNgayCaLa' => 0, 'soNgayDemThieuGio' => 0, 'soNgayDemChuaDuCap' => 0 );
			foreach ( $ngay_ds as $ngay => $d ) {
				// Chỉ cộng công của ngày TRONG tháng đang xem: ca đêm ngày cuối tháng đẩy công sang
				// ngày 1 tháng sau, cộng vào đây là tính hai lần khi xem tháng sau.
				if ( 0 !== strpos( $ngay, $tt . '-' ) ) { continue; }
				$e['congNgay']   += $d['congNgay'];
				$e['congTangCa'] += $d['congTangCa'];
				$e['congDem']    += $d['congDem'];
				$e['congBu']     += $d['congBu'];
				if ( $d['caLa'] ) { $e['soNgayCaLa']++; }
				if ( $d['demThieuGio'] ) { $e['soNgayDemThieuGio']++; }
				if ( $d['demChuaDuCap'] ) { $e['soNgayDemChuaDuCap']++; }
				$chi_tiet[] = array_merge( array( 'ma' => $ma, 'ten' => $e['ten'] ), $d );
			}
			$e['tong'] = round( $e['congNgay'] + $e['congTangCa'] + $e['congDem'] + $e['congBu'], 2 );
			$rows[] = $e;
		}
		usort( $rows, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		usort( $chi_tiet, function ( $a, $b ) {
			if ( $a['ngay'] !== $b['ngay'] ) { return strcmp( $a['ngay'], $b['ngay'] ); }
			return strcmp( $a['ten'], $b['ten'] );
		} );
		foreach ( $rows as $r ) {
			foreach ( array( 'congNgay', 'congTangCa', 'congDem', 'congBu', 'tong' ) as $k ) {
				$tong[ $k ] = round( $tong[ $k ] + $r[ $k ], 2 );
			}
		}

		$nc   = self::vp_nc_lay( $coso, $tt );
		$tien = self::vp_gan_tien( $rows, self::vp_luong_co_ban(), $nc );
		return array( 'station' => $coso, 'month' => $tt, 'cfg' => $cfg, 'rows' => $rows,
			'detail' => $chi_tiet, 'tong' => $tong, 'tien' => $tien,
			'chuaKhaiKeToan' => ( 0 === count( $cfg['ktMaNV'] ) ),
			'ncThang' => $tt, 'ncSo' => $nc,
			'ncGoiY' => ( $nc > 0 ? null : self::vp_nc_goi_y( $coso, $tt ) ) );
	}

	// ======================================================================= bảng thô

	/**
	 * BẢNG CÔNG THÔ — giờ vào/ra từng ngày, gộp theo người. KHÔNG tính công, KHÔNG tính tiền.
	 * Dùng cho cơ sở CHƯA có công thức lương. Xem khối cảnh báo ở đầu tệp.
	 */
	public static function bang_cong_tho( $coso, $tt ) {
		$by = array();
		$thu_tu = array();
		foreach ( self::doc_thang( $coso, $tt ) as $r ) {
			$ma = trim( (string) $r['ma_nv'] );
			if ( '' === $ma ) { continue; }
			$hau_to = strtoupper( trim( (string) $r['hau_to'] ) );
			$khoa   = $ma . ( '' !== $hau_to ? '-' . $hau_to : '' );
			if ( ! isset( $by[ $khoa ] ) ) {
				$by[ $khoa ] = array( 'ma' => $khoa,
					'ten' => ( '' !== (string) $r['ho_ten'] ? (string) $r['ho_ten'] : $khoa ), 'ngay' => array() );
				$thu_tu[] = $khoa;
			}
			$by[ $khoa ]['ngay'][] = array( 'date' => $r['ngay'],
				'vao' => VHCC_DB::hhmmss( $r['gio_vao_giay'] ), 'ra' => VHCC_DB::hhmmss( $r['gio_ra_giay'] ) );
		}
		$rows = array();
		foreach ( $thu_tu as $k ) { $rows[] = $by[ $k ]; }
		usort( $rows, function ( $a, $b ) { return strcmp( $a['ten'], $b['ten'] ); } );
		return array( 'station' => $coso, 'month' => $tt, 'rows' => $rows );
	}
}
