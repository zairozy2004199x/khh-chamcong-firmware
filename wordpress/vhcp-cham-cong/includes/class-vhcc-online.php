<?php
/**
 * CHẤM CÔNG ONLINE (điện thoại) + TRANG CHẤM CÔNG PHỤ — CHẠY THẲNG TRÊN WEB.
 *
 * Anh Thắng: *"còn đối với chấm công online qua điện thoại và trang chấm công phụ thì chạy trực
 * tiếp wed luôn"*. Khác đường máy: không hàng đợi, không Apps Script, không cầu nối. Trình duyệt
 * gọi thẳng WordPress, WordPress ghi thẳng MySQL. Làm được vì bên này không có firmware nào phải
 * nạp lại — chỉ là đổi địa chỉ trang.
 *
 * =============================================================================================
 * ⚠️ HỆ QUẢ PHẢI BIẾT TRƯỚC — ĐỌC TRƯỚC KHI BẬT CHO CƠ SỞ NÀO
 * =============================================================================================
 * Từ lúc một cơ sở chuyển chấm công online sang đây, lượt online KHÔNG còn vào sheet `CS_<cơ sở>`
 * nữa. Trong khi đó lượt MÁY vẫn vào sheet (rồi mới sao sang MySQL qua hàng đợi). Nghĩa là:
 *
 *      MySQL  = lượt máy (sao lại)  +  lượt online     -> ĐỦ
 *      Sheet  = lượt máy                                -> THIẾU lượt online
 *
 * Nên với cơ sở đã bật, LƯƠNG PHẢI TÍNH TỪ MySQL. Tính từ sheet là thiếu công của đúng những
 * người chấm bằng điện thoại — mà bộ phận Văn phòng gần như chỉ chấm bằng điện thoại.
 * Đây là lý do `nguon` trên mỗi hàng phải giữ đúng: phép đối số hàng giữa hai bên chỉ được đếm
 * lượt của MÁY, không thì nó luôn báo lệch và cái báo lệch đó vô nghĩa.
 *
 * =============================================================================================
 * BỐN CHỖ GÁC, KHÔNG ĐƯỢC BỎ MỘT CHỖ NÀO
 * =============================================================================================
 * 1. GIỜ LẤY Ở MÁY CHỦ, tuyệt đối không nhận giờ từ điện thoại. Nhận giờ của client là ai cũng
 *    tự khai mình đến từ 8 giờ sáng.
 * 2. CƠ SỞ đi lên từ client (người làm nhiều cửa hàng tự chọn) -> PHẢI đối chiếu với danh sách
 *    cơ sở người đó thật sự có. Không kiểm thì bất kỳ tài khoản nhân viên nào cũng ghi giờ vào
 *    cơ sở khác.
 * 3. NHIỆM VỤ cũng đi lên từ client -> cũng phải đối chiếu với hồ sơ. Không kiểm thì ai cũng tự
 *    gán cho mình việc có đơn giá cao hơn.
 * 4. Chỉ tài khoản CÓ KHAI "Mã NV chấm công online" mới chấm được. Đây là cách phân biệt nhân
 *    viên cơ sở (chỉ theo dõi) với nhân viên văn phòng (vừa theo dõi vừa chấm) — bản gốc cố ý
 *    KHÔNG thêm vai trò mới cho việc này, nên ở đây cũng không thêm.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Online {

	/** Hậu tố hàng 2 của Văn phòng: chứa CẢ tăng ca lẫn ca đêm, phân biệt bằng GIỜ khi tính công. */
	const DUOI_CD = 'CD';

	/**
	 * Cấu hình công Văn phòng. Đọc từ bảng `cai_dat`, trộn với mặc định.
	 * Giữ đúng tên khoá của Code.gs để hai bên đọc cùng một bộ số.
	 */
	public static function vp_cfg( $coso = '' ) {
		/* 🔴 HỎI `VHCC_Luong` TRƯỚC — nó là nơi DUY NHẤT biết đủ ba lớp cấu hình (mặc định ·
		   bản chung · bản riêng của khối). Bản rút gọn ở dưới chỉ đọc bản CHUNG, nên từ lúc có
		   cấu hình riêng cho khối Văn phòng (26/08/2026) mà vẫn dùng nó thì chỗ ĐỊNH TUYẾN lượt
		   chấm đọc một khung giờ, chỗ TÍNH CÔNG đọc một khung giờ khác. Hai chỗ lệch nhau kiểu
		   đó không báo lỗi — chỉ có mấy lượt chấm rơi nhầm hàng. */
		if ( method_exists( 'VHCC_Luong', 'vp_cfg' ) ) {
			return VHCC_Luong::vp_cfg( $coso );
		}
		$mac_dinh = array(
			'ngayTu' => '08:30', 'ngayDen' => '17:00',
			'demTu' => '21:00', 'demDen' => '06:00',
			'graceRaPhut' => 60,
		);
		$luu = self::cai_dat( 'VP_CONG_CFG' );
		if ( is_array( $luu ) ) {
			foreach ( $mac_dinh as $k => $v ) {
				if ( isset( $luu[ $k ] ) && '' !== $luu[ $k ] && null !== $luu[ $k ] ) { $mac_dinh[ $k ] = $luu[ $k ]; }
			}
		}
		return $mac_dinh;
	}

	public static function cai_dat( $khoa ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT gia_tri FROM ' . VHCC_DB::t( 'cai_dat' ) . ' WHERE khoa=%s', $khoa ) );
		if ( null === $v || '' === $v ) { return null; }
		$d = json_decode( $v, true );
		return null === $d ? $v : $d;
	}

	/**
	 * Cơ sở này có thuộc bộ phận Văn phòng không — uỷ cho VHCC_Luong để CHỈ CÓ MỘT định nghĩa.
	 *
	 * ⚠️ Bản đầu của hàm này em viết `LIKE '%van phong%'` và đó là SAI. Bên Code.gs phép so là
	 *    KHỚP ĐÚNG chuỗi 'Văn phòng' trên một bảng đã chuẩn hoá: bộ phận nào không nằm trong
	 *    ['Máy tự động','Khu vui chơi','Văn phòng'] thì thành 'Chưa xếp', tức KHÔNG CÓ công thức
	 *    lương nào. Với LIKE thì một cơ sở khai "Văn phòng phụ" bị áp trọn công thức Văn phòng —
	 *    định tuyến hàng 2, ân hạn, công đêm — trong khi bản gốc không tính lương cho nó. Tự sinh
	 *    ra công thức cho một cơ sở chưa được xếp là tự sinh ra tiền.
	 */
	public static function la_van_phong( $coso ) {
		return VHCC_Luong::la_van_phong( $coso );
	}

	/**
	 * TRẢI PHẲNG trục hàng 2 — bản dịch `traiPhang` trong `_ghiGioDem`.
	 *
	 * ⚠️ Mốc trải phẳng là `ngayDen` (17:00), KHÔNG phải `demTu` (21:00). Hàng 2 chứa CẢ tăng ca
	 *    lẫn ca đêm; lấy mốc 21:00 thì lượt tăng ca 18:00 trả null -> BỊ BỎ ÂM THẦM, nhân viên
	 *    bấm mà không có gì được ghi. Chú thích bên Code.gs ghi rõ đây là lỗi đã từng mắc.
	 *
	 * Trả null nghĩa là giờ đó thuộc ca ngày, không thuộc hàng 2.
	 */
	public static function trai_phang( $giay, $cfg ) {
		$ngay_den = VHCC_DB::giay( $cfg['ngayDen'] );
		$dem_den  = VHCC_DB::giay( $cfg['demDen'] );
		if ( null === $giay || null === $ngay_den || null === $dem_den ) { return null; }
		if ( $giay >= $ngay_den ) { return $giay; }
		if ( $giay < $dem_den ) { return $giay + VHCC_DB::NGAY_GIAY; }
		return null;
	}

	/**
	 * Lượt chấm online của cơ sở Văn phòng thuộc HÀNG nào, NGÀY nào — bản dịch `_vpDinhTuyen`.
	 * Trả null = hàng 1, ngày hôm nay (mọi cơ sở KHÔNG phải Văn phòng cũng trả null).
	 *
	 * ⚠️ Lượt 00:00–06:00 lùi về khối ngày HÔM TRƯỚC, để giờ ra nằm CÙNG hàng 2 với giờ vào tối
	 *    hôm trước. Không lùi thì ca đêm bị chẻ đôi giữa hai ngày, mỗi bên một đầu, và không tính
	 *    được công nào. (Chỗ GHI và chỗ TÍNH công là hai việc khác nhau: công thì dồn sang ngày
	 *    hôm sau, nhưng ghi thì lùi về hôm trước.)
	 */
	public static function dinh_tuyen( $coso, $ngay, $giay, $chinh_chua_ra = false ) {
		if ( ! self::la_van_phong( $coso ) ) { return null; }
		$cfg      = self::vp_cfg( $coso );
		$dem_den  = VHCC_DB::giay( $cfg['demDen'] );
		$ngay_den = VHCC_DB::giay( $cfg['ngayDen'] );
		if ( null === $giay || null === $dem_den || null === $ngay_den ) { return null; }

		if ( $giay < $dem_den ) {
			return array( 'ngay' => self::ngay_truoc( $ngay ), 'duoi' => self::DUOI_CD, 'dem' => true );
		}
		/* Biên `ngayDen`: lượt ĐÚNG 17:00:00 là tan làm ca ngày -> so `>` chứ không `>=`. Và trong
		   ân hạn sau đó, nếu hàng 1 chưa có giờ ra thì cũng là tan làm, KHÔNG phải mở hàng 2:
		   không có chỗ này thì người tan làm bấm 17:05 bị đẩy sang hàng 2 -> hàng 1 thiếu giờ ra
		   -> MẤT TRỌN 1 CÔNG NGÀY. */
		$an_han = (int) $cfg['graceRaPhut'] * 60;
		if ( $chinh_chua_ra && $giay >= $ngay_den && $giay <= $ngay_den + $an_han ) { return null; }
		if ( $giay > $ngay_den ) {
			return array( 'ngay' => $ngay, 'duoi' => self::DUOI_CD, 'dem' => true );
		}
		return null;
	}

	public static function ngay_truoc( $ngay ) {
		$t = strtotime( $ngay . ' -1 day' );
		return false === $t ? $ngay : gmdate( 'Y-m-d', $t );
	}

	/**
	 * Cơ sở người này thật sự có mặt: cơ sở chính + các cơ sở phụ trong hồ sơ.
	 *
	 * 🔴 `$mac_dinh` PHẢI ĐƯỢC TÁCH THEO DẤU PHẨY. Nó thường là `coso` của thẻ phiên, mà thẻ
	 *    phiên của người làm hai nơi mang cả hai tên nối bằng `', '` (xem `VHCC_Auth::users_cua`).
	 *    Nhét nguyên khối vào danh sách thì người có 2 cơ sở hiện ra 3 — đúng dòng
	 *    `POSH_HCM, (PART TIME )_POSH+JP · POSH_HCM · (PART TIME )_POSH+JP` anh Thắng gửi
	 *    30/08/2026 ở màn "Công của tôi". Và cái phần tử ma ấy đi tiếp vào câu `coso IN (...)`
	 *    của `bang_thang()`, nên nó kéo về đúng những hàng chấm công đã lỡ ghi sai cơ sở.
	 */
	public static function ds_coso_cua_nv( $ma_nv, $mac_dinh ) {
		global $wpdb;
		$ds = array();
		foreach ( explode( ',', (string) $mac_dinh ) as $x_md ) {
			$x_md = trim( $x_md );
			if ( '' !== $x_md ) { $ds[] = $x_md; }
		}
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT cua_hang, coso_phu FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', $ma_nv ), ARRAY_A );
		if ( $r ) {
			if ( '' !== trim( (string) $r['cua_hang'] ) ) { $ds[] = trim( $r['cua_hang'] ); }
			foreach ( explode( ',', (string) $r['coso_phu'] ) as $x ) {
				$x = trim( preg_replace( '/^CS_/', '', $x ) );
				if ( '' !== $x ) { $ds[] = $x; }
			}
		}
		$sach = array();
		foreach ( $ds as $x ) {
			$x = trim( preg_replace( '/^CS_/', '', $x ) );
			if ( '' !== $x && ! in_array( $x, $sach, true ) ) { $sach[] = $x; }
		}
		return $sach;
	}

	/** Nhiệm vụ người này ĐƯỢC KHAI trong hồ sơ. Rỗng = chỉ được nhiệm vụ mặc định. */
	public static function nhiem_vu_cua_nv( $ma_nv ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			'SELECT nhiem_vu FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', $ma_nv ) );
		$ds = array();
		foreach ( explode( ',', (string) $v ) as $x ) {
			$x = trim( $x );
			if ( '' !== $x ) { $ds[] = $x; }
		}
		return $ds;
	}

	/**
	 * CHẤM CÔNG ONLINE — bản dịch `chamCongOnline`.
	 *
	 * `$u` là người đã đăng nhập: array('pin','ma_nv','ho_ten','coso'). Giờ KHÔNG nhận từ tham số.
	 */
	public static function cham_cong( $u, $anh_data_url = '', $gps = null, $coso_chon = '', $nhiem_vu_chon = '' ) {
		if ( empty( $u['ma_nv'] ) ) {
			return array( 'ok' => false, 'error' => 'Tài khoản này chưa bật chấm công online.' );
		}
		$ma_nv    = trim( (string) $u['ma_nv'] );
		/* 🔴 HAI GIÁ TRỊ KHÁC NHAU, ĐỪNG GỘP LÀM MỘT.
		   `$the_coso` là NGUYÊN thẻ phiên — có thể mang nhiều cơ sở nối bằng `', '` (người làm
		   ở hai nơi). Nó đi vào `ds_coso_cua_nv()`, nơi biết tách.
		   `$coso` là MỘT tên để GHI XUỐNG BẢNG, nên phải qua `chuan_coso()` (cắt ở dấu phẩy đầu).
		   Trước 30/08/2026 hai thứ này là một biến, nên lượt chấm công không chọn cơ sở ghi
		   thẳng cả chuỗi `"POSH_HCM, (PART TIME )_POSH+JP"` vào cột `coso` — sinh ra một cơ sở
		   không có thật, và nó hiện luôn trong ô xổ cơ sở của màn quản trị. */
		$the_coso = isset( $u['coso'] ) ? (string) $u['coso'] : '';
		$mac_dinh = VHCC_NhanSu::chuan_coso( $the_coso );
		$coso     = $mac_dinh;

		// Gác 2: cơ sở đi lên từ client -> đối chiếu với danh sách người đó thật sự có.
		$chon = trim( preg_replace( '/^CS_/', '', (string) $coso_chon ) );
		if ( '' !== $chon ) {
			$duoc = self::ds_coso_cua_nv( $ma_nv, $the_coso );
			$ok   = false;
			foreach ( $duoc as $x ) { if ( strtolower( $x ) === strtolower( $chon ) ) { $ok = true; $coso = $x; } }
			if ( ! $ok ) {
				return array( 'ok' => false, 'error' => 'Bạn không có ở cơ sở "' . $chon . '". Chọn lại cơ sở.' );
			}
		}
		if ( '' === $coso ) {
			return array( 'ok' => false, 'error' => 'Chưa khai "Cơ sở chấm công online" cho tài khoản này.' );
		}

		// Gác 3: nhiệm vụ cũng đi lên từ client -> cũng đối chiếu với hồ sơ.
		$nv = '';
		$nv_raw = trim( (string) $nhiem_vu_chon );
		if ( '' !== $nv_raw ) {
			$cua = self::nhiem_vu_cua_nv( $ma_nv );
			$hop = false;
			foreach ( $cua as $x ) { if ( strtolower( $x ) === strtolower( $nv_raw ) ) { $hop = true; $nv = $x; } }
			if ( ! $hop ) {
				return array( 'ok' => false,
					'error' => 'Bạn không được khai nhiệm vụ "' . $nv_raw . '". Nhờ Admin bổ sung ở hồ sơ.' );
			}
		}

		// Gác 1: GIỜ LẤY Ở ĐÂY. Không có tham số nào cho client truyền giờ vào.
		$ngay = current_time( 'Y-m-d' );
		$giay = VHCC_DB::giay( current_time( 'H:i:s' ) );

		/* Định tuyến Văn phòng. Phải kiểm "hàng 1 đã có giờ vào mà chưa có giờ ra" TRƯỚC khi quyết
		   định, vì đó là điều kiện của ân hạn tan làm. */
		$chinh_chua_ra = false;
		$cfg = self::vp_cfg( $coso );
		$ngay_den = VHCC_DB::giay( $cfg['ngayDen'] );
		if ( null !== $giay && null !== $ngay_den
			&& $giay >= $ngay_den && $giay <= $ngay_den + (int) $cfg['graceRaPhut'] * 60 ) {
			$h = self::hang( $coso, $ngay, $ma_nv, '' );
			$chinh_chua_ra = ( $h && null !== $h['gio_vao_giay'] && null === $h['gio_ra_giay'] );
		}
		$tuyen = self::dinh_tuyen( $coso, $ngay, $giay, $chinh_chua_ra );

		$ma_ghi = $ma_nv;
		$giay_ghi = $giay;
		if ( $tuyen ) {
			$ngay     = $tuyen['ngay'];
			$ma_ghi   = $ma_nv . '-' . $tuyen['duoi'];
			$giay_ghi = self::trai_phang( $giay, $cfg );
			if ( null === $giay_ghi ) {
				/* Giờ thuộc ca ngày mà lại định tuyến sang hàng 2 -> KHÔNG ghi bừa. Bên Code.gs
				   chỗ này trả 'bo'; ghi bừa vào hàng 2 là công ngày biến thành tăng ca. */
				return array( 'ok' => false, 'error' => 'Giờ này không thuộc hàng tăng ca / ca đêm.' );
			}
		} elseif ( '' !== $nv ) {
			/* Nhiệm vụ chỉ có nghĩa ở Nhóm Máy Tự Động: người kiêm ≥2 việc thì việc THÊM ghi vào
			   hàng riêng. Hậu tố theo bảng của Code.gs: Thu Tiền -TT, Trực Ghế -TG. */
			$duoi = self::duoi_nhiem_vu( $nv );
			if ( '' !== $duoi ) { $ma_ghi = $ma_nv . '-' . $duoi; }
		}

		// Ảnh: chấp cả data-URL (`data:image/jpeg;base64,...`) như bản gốc.
		$b64 = (string) $anh_data_url;
		if ( false !== strpos( $b64, 'base64,' ) ) { $b64 = substr( $b64, strpos( $b64, 'base64,' ) + 7 ); }

		// GPS: bản gốc ghi làm GHI CHÚ trên ô giờ. Ở đây có cột riêng.
		$ghi_chu = self::gps_thanh_chu( $gps );

		$kq = VHCC_Nhan::ghi_gio( $coso, $ngay, $ma_ghi, (string) $u['ho_ten'], $giay_ghi, $b64, 'online', $ghi_chu );
		if ( isset( $kq['loi'] ) ) { return array( 'ok' => false, 'error' => $kq['loi'] ); }

		return array( 'ok' => true, 'loai' => $kq['loai'], 'coSo' => $coso, 'ngay' => $ngay,
			'gio' => VHCC_DB::hhmmss( $giay ), 'ma' => $ma_ghi, 'img' => $kq['anh'] );
	}

	/** Hậu tố hàng của một nhiệm vụ. Không khớp -> rỗng = ghi vào hàng chính. */
	public static function duoi_nhiem_vu( $nv ) {
		$s = self::bo_dau( $nv );
		if ( false !== strpos( $s, 'truc ghe' ) ) { return 'TG'; }
		if ( false !== strpos( $s, 'thu tien' ) ) { return 'TT'; }
		return '';
	}

	private static function bo_dau( $s ) {
		$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
		$cap = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $cap as $tron => $co ) {
			$ds = preg_split( '//u', $co, -1, PREG_SPLIT_NO_EMPTY );
			$s  = str_replace( $ds, $tron, $s );
		}
		return $s;
	}

	/** Sai số (mét) từ đó trở lên thì toạ độ KHÔNG còn xác nhận được ai đứng ở đâu. */
	const GPS_THO = 2000;

	/**
	 * GPS -> dòng chữ đóng vào ghi chú của lượt chấm.
	 *
	 * 🔴 NÓI RÕ KHI TOẠ ĐỘ CHỈ LÀ ƯỚC LƯỢNG. Trình duyệt vẫn trả về một cặp số trông rất thật
	 *    khi chưa bắt được vệ tinh — nó đoán theo địa chỉ mạng, sai số có khi 200 KILÔMÉT. Trên
	 *    máy anh Thắng đúng là như vậy: 10.775500,106.702100 (giữa Quận 1) ±200000m.
	 *
	 *    Ghi mỗi "GPS 10.7755,106.7021 ±200000m" thì người đọc phiếu ba tháng sau chỉ nhìn cặp
	 *    toạ độ, mở bản đồ ra, thấy đúng trung tâm thành phố, và kết luận người này có mặt —
	 *    trong khi dữ liệu chỉ nói "đâu đó ở miền Nam". Dấu ± nhỏ quá, không ai đọc.
	 *
	 *    Nên: dán thẳng chữ vào, không bắt ai suy từ con số.
	 */
	private static function gps_thanh_chu( $gps ) {
		if ( ! is_array( $gps ) || ! isset( $gps['lat'], $gps['lng'] ) ) { return ''; }
		$la = is_numeric( $gps['lat'] ) ? (float) $gps['lat'] : null;
		$ln = is_numeric( $gps['lng'] ) ? (float) $gps['lng'] : null;
		if ( null === $la || null === $ln ) { return ''; }
		$acc = ( isset( $gps['acc'] ) && is_numeric( $gps['acc'] ) ) ? (float) $gps['acc'] : null;

		if ( null !== $acc && $acc >= self::GPS_THO ) {
			return 'VỊ TRÍ ƯỚC LƯỢNG THEO MẠNG (không phải GPS, KHÔNG dùng để xác nhận có mặt) '
				. round( $la, 6 ) . ',' . round( $ln, 6 ) . ' ±' . self::do_dai( $acc );
		}
		$s = 'GPS ' . round( $la, 6 ) . ',' . round( $ln, 6 );
		if ( null !== $acc ) { $s .= ' ±' . self::do_dai( $acc ); }
		return $s;
	}

	/** 200000 -> "200km". "±200000m" thì không ai thấy nó to cỡ nào. */
	private static function do_dai( $m ) {
		$m = (int) round( (float) $m );
		if ( $m < 1000 ) { return $m . 'm'; }
		return ( $m < 10000 ? number_format( $m / 1000, 1 ) : (string) (int) round( $m / 1000 ) ) . 'km';
	}

	/** GIỜ MÁY CHỦ — trang chấm công phụ hiện đồng hồ theo giờ này, không theo giờ điện thoại.
	 *  Nếu hiện giờ điện thoại thì người ta thấy 08:29 rồi bấm, mà máy chủ ghi 08:31. */
	public static function gio_may_chu() {
		return array( 'ok' => true, 'ngay' => current_time( 'Y-m-d' ),
			'gio' => current_time( 'H:i:s' ), 'moc' => (int) current_time( 'timestamp' ) );
	}

	/**
	 * Thông tin để trang chấm công online dựng màn: mình là ai, được chấm ở cơ sở nào, được khai
	 * nhiệm vụ nào, và HÔM NAY đã chấm gì.
	 */
	public static function thong_tin( $u ) {
		$ma_nv = trim( isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' );
		if ( '' === $ma_nv ) {
			return array( 'ok' => true, 'bat' => false,
				'ghiChu' => 'Tài khoản này chưa bật chấm công online.' );
		}
		$mac_dinh = VHCC_NhanSu::chuan_coso( isset( $u['coso'] ) ? $u['coso'] : '' );
		$ds_coso = self::ds_coso_cua_nv( $ma_nv, $mac_dinh );
		$hn = array();
		foreach ( $ds_coso as $cs ) { $hn[ $cs ] = self::hom_nay( $cs, $ma_nv ); }
		return array( 'ok' => true, 'bat' => true, 'maNV' => $ma_nv, 'hoTen' => (string) $u['ho_ten'],
			'coSoMacDinh' => $mac_dinh, 'dsCoSo' => $ds_coso,
			'dsNhiemVu' => self::nhiem_vu_cua_nv( $ma_nv ),
			'homNay' => $hn, 'gio' => self::gio_may_chu() );
	}

	/**
	 * Ảnh mẫu thẻ 3×4 — hình mẫu để nhân viên biết chụp thế nào cho đúng.
	 * ⚠️ Trả `{ok:false}` khi chưa khai, để trang tự dùng hình vẽ sẵn. KHÔNG trả ảnh rỗng: ảnh rỗng
	 *    hiện ra là một ô đen, và người ta tưởng hệ thống hỏng.
	 */
	public static function anh_mau_the() {
		$v = self::cai_dat( 'ANH_MAU_THE' );
		if ( ! is_string( $v ) || strlen( $v ) < 100 ) { return array( 'ok' => false ); }
		return array( 'ok' => true, 'dataUri' => $v );
	}

	public static function anh_mau_the_info() {
		$v = self::cai_dat( 'ANH_MAU_THE' );
		return array( 'ok' => true, 'daKhai' => ( is_string( $v ) && strlen( $v ) >= 100 ),
			'soByte' => is_string( $v ) ? strlen( $v ) : 0 );
	}

	public static function dat_anh_mau_the( $u, $data_uri ) {
		global $wpdb;
		if ( ! VHCC_NhanSu::co_quan_tri_nv( $u ) ) {
			return array( 'ok' => false, 'error' => 'Đặt ảnh mẫu thẻ — ' . VHCC_NhanSu::LOI_QT );
		}
		$v = trim( (string) $data_uri );
		if ( '' !== $v && 0 !== strpos( $v, 'data:image/' ) ) {
			return array( 'ok' => false, 'error' => 'Phải là ảnh dạng data:image/… (dán từ ô tải ảnh).' );
		}
		/* Giới hạn kích cỡ: ảnh mẫu đi kèm MỌI lượt mở trang chấm công, ảnh vài trăm KB là làm
		   trang nặng cho mọi người chỉ vì một hình minh hoạ. */
		if ( strlen( $v ) > 200000 ) {
			return array( 'ok' => false, 'error' => 'Ảnh mẫu quá lớn ('
				. round( strlen( $v ) / 1024 ) . ' KB). Nó tải kèm MỌI lượt mở trang chấm công — '
				. 'nén xuống dưới 150 KB.' );
		}
		$bang = VHCC_DB::t( 'cai_dat' );
		$cu = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE khoa=%s", 'ANH_MAU_THE' ) );
		$ghi = array( 'khoa' => 'ANH_MAU_THE', 'gia_tri' => wp_json_encode( $v ),
			'cap_nhat' => current_time( 'mysql' ),
			'nguoi_sua' => isset( $u['name'] ) ? (string) $u['name'] : '' );
		if ( $cu ) { $wpdb->update( $bang, $ghi, array( 'id' => (int) $cu ) ); }
		else       { $wpdb->insert( $bang, $ghi ); }
		return array( 'ok' => true );
	}

	public static function hang( $coso, $ngay, $ma_nv, $hau_to ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s',
			$coso, $ngay, $ma_nv, $hau_to ), ARRAY_A );
	}

	/**
	 * Giờ vào / giờ ra HÔM NAY của một người — để trang chấm công phụ hiện trạng thái.
	 * Đọc CẢ hàng chính và hàng 2, vì người văn phòng buổi tối nằm ở hàng 2.
	 */
	public static function hom_nay( $coso, $ma_nv, $ngay = null ) {
		global $wpdb;
		if ( null === $ngay ) { $ngay = current_time( 'Y-m-d' ); }
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT hau_to, gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
			. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s ORDER BY hau_to',
			$coso, $ngay, $ma_nv ), ARRAY_A );
		$out = array();
		foreach ( (array) $r as $x ) {
			$out[] = array(
				'hauTo' => (string) $x['hau_to'],
				'vao'   => VHCC_DB::hhmmss( $x['gio_vao_giay'] ),
				'ra'    => VHCC_DB::hhmmss( $x['gio_ra_giay'] ),
			);
		}
		return $out;
	}

	/**
	 * BẢNG CÔNG MỘT THÁNG của CHÍNH người đang đăng nhập — để họ tự soát trên điện thoại.
	 *
	 * Anh Thắng: *"còn nhân viên vào check chấm công của mình thì sao"*. Trước đó trạm chỉ có
	 * "hôm nay" và 60 dòng gần nhất — đủ để biết mình đã chấm chưa, KHÔNG đủ để trả lời câu
	 * người ta thật sự hỏi cuối tháng: *tháng này tôi được bao nhiêu công, ngày nào thiếu*.
	 *
	 * =========================================================================================
	 * 🔴 KHÔNG IN TIỀN, VÀ KHÔNG TÍNH CÔNG THEO LUẬT LƯƠNG
	 * =========================================================================================
	 * Màn này đếm **lượt có mặt** và **giờ thô**, không nhân đơn giá, không xét ngày lễ, không
	 * quy ra "công". Hai lý do, cả hai đều nặng:
	 *
	 *   1. Vai `CC_ONLINE` là bậc thấp nhất của hệ. Mở tiền ở đây là mở lương cho bất kỳ ai có
	 *      PIN, trên một trang chạy ngoài internet.
	 *   2. Số công dùng để trả lương do `VHCC_Luong` tính, có ngày lễ, hàng -CD, ân hạn, nhiệm
	 *      vụ. Nếu màn này cũng in ra một con số gọi là "công" thì đó là CÔNG THỨC THỨ HAI cho
	 *      cùng một việc, và ngày nó lệch với bảng lương là ngày nhân viên cầm điện thoại lên
	 *      cãi với kế toán — hai bên đều đang đọc số do chính hệ thống in ra.
	 *
	 * Nên: "lượt", "giờ có mặt", "ngày thiếu giờ ra". Ba con số nhân viên tự kiểm chứng được
	 * bằng trí nhớ, và không con nào giả vờ là bảng lương.
	 *
	 * ⚠️ Chỉ lấy đúng mã NV của người đang đăng nhập và đúng danh sách cơ sở của họ — cùng phép
	 *    gác với `lich_su()`.
	 *
	 * @param string $thang 'YYYY-MM'. Sai định dạng -> tháng hiện tại theo GIỜ MÁY CHỦ.
	 */
	public static function bang_thang( $ma_nv, $ds_coso, $thang = '' ) {
		global $wpdb;
		$ma_nv = trim( (string) $ma_nv );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $thang ) ) { $thang = current_time( 'Y-m' ); }
		$rong = array( 'ok' => true, 'thang' => $thang, 'dong' => array(),
			'tong' => array( 'ngay' => 0, 'luot' => 0, 'phut' => 0, 'thieuRa' => 0 ) );
		if ( '' === $ma_nv || ! $ds_coso ) { return $rong; }

		$oc = implode( ',', array_fill( 0, count( $ds_coso ), '%s' ) );
		$tv = array_merge( array( $ma_nv, $thang . '-01', $thang . '-31' ), $ds_coso );
		$r  = $wpdb->get_results( $wpdb->prepare(
			'SELECT coso, ngay, hau_to, gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE ma_nv=%s AND ngay BETWEEN %s AND %s AND coso IN ($oc)"
			. ' ORDER BY ngay ASC, coso ASC, hau_to ASC', $tv ), ARRAY_A );

		$dong    = array();
		$co_ngay = array();
		$phut    = 0;
		$thieu   = 0;
		foreach ( (array) $r as $x ) {
			$v = $x['gio_vao_giay'];
			$a = $x['gio_ra_giay'];
			$p = null;
			if ( null !== $v && '' !== $v && null !== $a && '' !== $a ) {
				$p = VHCC_Luong::phut_ca( intdiv( (int) $v, 60 ), intdiv( (int) $a, 60 ) );
				$phut += $p;
			} elseif ( null !== $v && '' !== $v ) {
				/* Có vào mà không có ra. Đếm riêng — đây chính là thứ người ta cần thấy để đi
				   xin bổ sung TRƯỚC khi kế toán chốt tháng, chứ không phải sau khi nhận lương. */
				$thieu++;
			}
			$co_ngay[ (string) $x['ngay'] ] = true;
			$dong[] = array(
				'ngay'  => (string) $x['ngay'],
				'coSo'  => (string) $x['coso'],
				'hauTo' => (string) $x['hau_to'],
				'vao'   => VHCC_DB::hhmmss( $v ),
				'ra'    => VHCC_DB::hhmmss( $a ),
				'phut'  => $p,
			);
		}
		return array( 'ok' => true, 'thang' => $thang, 'dong' => $dong,
			'tong' => array( 'ngay' => count( $co_ngay ), 'luot' => count( $dong ),
				'phut' => $phut, 'thieuRa' => $thieu ) );
	}

	/**
	 * Lịch sử chấm công của CHÍNH người đang đăng nhập.
	 * ⚠️ Lọc theo ĐÚNG mã NV của họ: vai trò Nhân viên không được thấy chấm công của ai khác.
	 */
	public static function lich_su( $ma_nv, $ds_coso, $so_dong = 60 ) {
		global $wpdb;
		$ma_nv = trim( (string) $ma_nv );
		if ( '' === $ma_nv || ! $ds_coso ) { return array(); }
		$n  = (int) $so_dong > 0 ? (int) $so_dong : 60;
		$oc = implode( ',', array_fill( 0, count( $ds_coso ), '%s' ) );
		$tv = array_merge( array( $ma_nv ), $ds_coso );
		$r  = $wpdb->get_results( $wpdb->prepare(
			'SELECT coso, ngay, hau_to, gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
			. " WHERE ma_nv=%s AND coso IN ($oc) ORDER BY ngay DESC, coso ASC, hau_to ASC LIMIT $n",
			$tv ), ARRAY_A );
		$out = array();
		foreach ( (array) $r as $x ) {
			$out[] = array( 'ngay' => $x['ngay'], 'coSo' => $x['coso'], 'hauTo' => (string) $x['hau_to'],
				'vao' => VHCC_DB::hhmmss( $x['gio_vao_giay'] ), 'ra' => VHCC_DB::hhmmss( $x['gio_ra_giay'] ) );
		}
		return $out;
	}
}
