<?php
/**
 * TIẾP NHẬN NHÂN SỰ TỰ ĐỘNG — từ lúc nhận người tới lúc nhân viên cầm được mọi thứ để đi làm.
 *
 * Anh Thắng 26/09/2026: *"1 nhân viên đến xin việc. Công ty sẽ thiết lập mức lương. Chức vụ. Hệ
 * thống sẽ tự set tất cả tính năng cho nhân viên có chức vụ đó, kèm tk, mk và quyền hạn đủ hết,
 * cả tk chấm công, và hợp đồng, bảo hiểm đủ hết của 1 nhân viên. Tự động gửi email bản pdf để
 * nhân viên chỉ dùng thôi"*. Chốt: PIN hệ thống tự cấp và ghi trong PDF · mã NV = tiền tố bộ phận
 * + số chạy · hợp đồng theo mẫu chuẩn Bộ luật Lao động 2019 · BHXH tự tính 10,5% lương đóng BH.
 *
 * =================================================================================================
 * MÔ HÌNH (theo Frappe HRMS "Employee Onboarding Template" / ChiefOnboarding)
 * =================================================================================================
 *   1. MẪU THEO CHỨC VỤ (`O_MAU`) — khai MỘT LẦN: tiền tố mã NV, bộ phận/mảng, vai trò, quyền
 *      vào trang, app được đẩy sang, cách tính lương + mức mặc định, thử việc, loại hợp đồng,
 *      BHXH.
 *   2. TIẾP NHẬN — thông tin người mới + chọn mẫu + cơ sở + ngày vào (+ sửa lương nếu cần).
 *   3. `tao()` CHẠY MỘT DÃY BƯỚC, mỗi bước ghi ✔/✖ + một câu (`buoc`), bước hỏng chạy lại được
 *      (`chay_lai()`), không làm đổ các bước khác.
 *   4. BỘ HỒ SƠ NHẬN VIỆC (thư chào mừng có mã NV + PIN, HĐ thử việc, HĐLĐ) là TRANG IN A4 qua
 *      link có chữ ký — gửi email + chuông.
 *
 * 🔴 HỢP ĐỒNG LÀ BẢN CHỤP. Số liệu đưa vào hợp đồng (lương, chức vụ, ngày…) chụp lại ngay lúc
 *    tạo (`hd`), không đọc lại hồ sơ mỗi lần mở: sửa hồ sơ năm sau không được viết lại một hợp
 *    đồng đã ký. Sổ tiếp nhận không bao giờ tự xoá.
 * 🔴 PIN IN TRONG BỘ HỒ SƠ — ANH THẮNG CHỌN SỰ TIỆN. Để bớt rủi ro: chỉ HIỆN khi PIN trong hồ sơ
 *    vẫn đúng là PIN đã cấp (nhân viên đổi rồi thì thôi hiện) và trong `PIN_HIEN_NGAY` ngày đầu.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_TiepNhan {

	const O_MAU = 'TN_MAU';
	const O_CTY = 'TN_CTY';
	const O_SO  = 'TN_SO';

	/** Tiếp nhận = tạo hồ sơ có lương → bậc Kế toán, và phải xem được lương hồ sơ. */
	const QUYEN = 'ho_so';

	/** Phần người lao động đóng BHXH + BHYT + BHTN (8% + 1,5% + 1%). */
	const TY_LE_BH = 0.105;

	/** Bao nhiêu ngày đầu bộ hồ sơ còn hiện PIN. */
	const PIN_HIEN_NGAY = 30;

	/** App đẩy sang được — cột ⇒ lớp. Chỉ lớp nào ĐANG NẠP mới được bày ra. */
	const APP = array(
		'chi_phi'     => 'VHCC_DayChiPhi',
		'chi_phi_vp'  => 'VHCC_DayChiPhiVP',
		'chi_phi_mtd' => 'VHCC_DayChiPhiMTD',
		'ghe'         => 'VHCC_DayGhe',
		'bao_cao'     => 'VHCC_DayBaoCao',
	);

	/** Tên các bước — thứ tự này là thứ tự chạy và thứ tự hiện. */
	const BUOC = array(
		'ho_so'  => 'Hồ sơ · mã NV · PIN · vai trò · cơ sở',
		'quyen'  => 'Quyền vào trang',
		'app'    => 'Đẩy sang app',
		'may'    => 'Máy chấm công',
		'luong'  => 'Lương',
		'bhxh'   => 'BHXH',
		'giayto' => 'Hợp đồng & thư chào mừng',
		'gui'    => 'Gửi email + chuông',
	);

	/* ============================================================== mẫu chức vụ */

	public static function mau_trong() {
		return array(
			'ten' => '', 'tien_to' => '', 'bo_phan' => '', 'mang' => '', 'vai_tro' => 'Nhân viên',
			'quyen' => array(), 'app' => array(), 'may' => 1,
			'cach' => 'thang', 'luong' => 0, 'cong_chuan' => '', 'don_gia' => 0,
			'thu_viec' => 0, 'tv_pt' => 85, 'loai_hd' => 'xac_dinh', 'thoi_han' => 12,
			'bhxh' => 1, 'luong_bh' => 0, 'mo_ta' => '', 'dieu_them' => '',
		);
	}

	public static function ds_mau() {
		$d = VHCC_Luong::cai_dat( self::O_MAU, null );
		return is_array( $d ) ? $d : array();
	}

	public static function mau( $k ) {
		$ds = self::ds_mau();
		return isset( $ds[ $k ] ) ? array_merge( self::mau_trong(), (array) $ds[ $k ] ) : null;
	}

	private static function khoa_mau( $ten ) {
		return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '_', VHCC_Luong::bo_chu( (string) $ten ) ), '_' ) );
	}

	private static function gac( $u ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) || ! VHCC_Vai::duoc( $u, 'xem_luong_hs' ) ) {
			return VHCC_Vai::loi( $u, self::QUYEN, 'Tiếp nhận nhân sự' );
		}
		return '';
	}

	/** Lưu một mẫu. `$k` rỗng = mẫu mới (khoá lấy từ tên chức vụ). */
	public static function dat_mau( $u, $k, $dat ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$m = self::mau_trong();
		$ten = trim( (string) ( isset( $dat['ten'] ) ? $dat['ten'] : '' ) );
		if ( '' === $ten ) { return array( 'ok' => false, 'error' => 'Thiếu tên chức vụ.' ); }
		$m['ten'] = $ten;
		$m['tien_to'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( isset( $dat['tien_to'] ) ? $dat['tien_to'] : '' ) ) );
		if ( '' === $m['tien_to'] ) {
			return array( 'ok' => false, 'error' => 'Thiếu tiền tố mã NV (VD MNNV2KVC) — mã người mới = tiền tố + số chạy.' );
		}
		foreach ( array( 'bo_phan', 'mang', 'mo_ta' ) as $o ) {
			$m[ $o ] = trim( (string) ( isset( $dat[ $o ] ) ? $dat[ $o ] : '' ) );
		}
		$m['dieu_them'] = trim( (string) ( isset( $dat['dieu_them'] ) ? $dat['dieu_them'] : '' ) );
		$vai = trim( (string) ( isset( $dat['vai_tro'] ) ? $dat['vai_tro'] : '' ) );
		if ( ! in_array( $vai, VHCC_Vai::ds_ten(), true ) ) {
			return array( 'ok' => false, 'error' => 'Vai trò "' . $vai . '" không có trong danh sách vai.' );
		}
		$m['vai_tro'] = $vai;
		$m['quyen'] = array();
		foreach ( (array) ( isset( $dat['quyen'] ) ? $dat['quyen'] : array() ) as $tr => $v ) {
			if ( isset( VHCC_Cong::SO[ $tr ] ) && in_array( $v, array( 'mo', 'khoa' ), true ) ) { $m['quyen'][ $tr ] = $v; }
		}
		$m['app'] = array_values( array_intersect( array_keys( self::APP ), (array) ( isset( $dat['app'] ) ? $dat['app'] : array() ) ) );
		$m['may'] = empty( $dat['may'] ) ? 0 : 1;
		$m['cach'] = ( isset( $dat['cach'] ) && 'gio' === $dat['cach'] ) ? 'gio' : 'thang';
		$m['luong'] = VHCC_NhanSu::so_tien( isset( $dat['luong'] ) ? $dat['luong'] : 0 );
		$m['don_gia'] = VHCC_NhanSu::so_tien( isset( $dat['don_gia'] ) ? $dat['don_gia'] : 0 );
		$m['cong_chuan'] = trim( (string) ( isset( $dat['cong_chuan'] ) ? $dat['cong_chuan'] : '' ) );
		$m['luong_bh'] = VHCC_NhanSu::so_tien( isset( $dat['luong_bh'] ) ? $dat['luong_bh'] : 0 );
		$m['thu_viec'] = max( 0, min( 180, (int) ( isset( $dat['thu_viec'] ) ? $dat['thu_viec'] : 0 ) ) );
		$m['tv_pt'] = (int) ( isset( $dat['tv_pt'] ) && '' !== (string) $dat['tv_pt'] ? $dat['tv_pt'] : 85 );
		/* BLLĐ 2019 Điều 26: lương thử việc ít nhất 85% lương của công việc đó. */
		if ( $m['tv_pt'] < 85 || $m['tv_pt'] > 100 ) {
			return array( 'ok' => false, 'error' => 'Lương thử việc phải từ 85% tới 100% lương chính thức (Điều 26 BLLĐ 2019).' );
		}
		/* Điều 25: thử việc không quá 60 ngày (trừ quản lý doanh nghiệp 180 ngày). */
		if ( $m['thu_viec'] > 60 && ! in_array( $vai, array( 'Quản lý', 'Admin', 'Kế toán' ), true ) ) {
			return array( 'ok' => false, 'error' => 'Thử việc quá 60 ngày chỉ áp cho vị trí quản lý (Điều 25 BLLĐ 2019).' );
		}
		$m['loai_hd'] = ( isset( $dat['loai_hd'] ) && 'khong_xac_dinh' === $dat['loai_hd'] ) ? 'khong_xac_dinh' : 'xac_dinh';
		$m['thoi_han'] = max( 1, min( 36, (int) ( isset( $dat['thoi_han'] ) ? $dat['thoi_han'] : 12 ) ) );
		$m['bhxh'] = empty( $dat['bhxh'] ) ? 0 : 1;
		if ( 'thang' === $m['cach'] && $m['luong'] <= 0 ) {
			return array( 'ok' => false, 'error' => 'Chức vụ ăn lương tháng cần mức lương cơ bản.' );
		}
		if ( 'gio' === $m['cach'] && $m['don_gia'] <= 0 ) {
			return array( 'ok' => false, 'error' => 'Chức vụ ăn lương giờ cần đơn giá / giờ.' );
		}
		$ds = self::ds_mau();
		$k = '' !== trim( (string) $k ) ? (string) $k : self::khoa_mau( $ten );
		if ( '' === $k ) { return array( 'ok' => false, 'error' => 'Tên chức vụ không hợp lệ.' ); }
		$ds[ $k ] = $m;
		VHCC_Luong::dat_cai_dat( self::O_MAU, $ds, $u );
		return array( 'ok' => true, 'khoa' => $k );
	}

	public static function xoa_mau( $u, $k ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$ds = self::ds_mau();
		unset( $ds[ (string) $k ] );
		VHCC_Luong::dat_cai_dat( self::O_MAU, $ds, $u );
		return array( 'ok' => true );
	}

	/* ============================================================== thông tin công ty */

	public static function cty() {
		$d = VHCC_Luong::cai_dat( self::O_CTY, null );
		$d = is_array( $d ) ? $d : array();
		return array_merge( array( 'ten' => VHCC_Pdf::ten_cong_ty(), 'dia_chi' => '', 'mst' => '',
			'dai_dien' => '', 'chuc_vu_dd' => 'Giám đốc', 'sdt' => '' ), $d );
	}

	public static function dat_cty( $u, $dat ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$o = array();
		foreach ( array( 'ten', 'dia_chi', 'mst', 'dai_dien', 'chuc_vu_dd', 'sdt' ) as $k ) {
			$o[ $k ] = trim( (string) ( isset( $dat[ $k ] ) ? $dat[ $k ] : '' ) );
		}
		VHCC_Luong::dat_cai_dat( self::O_CTY, $o, $u );
		return array( 'ok' => true );
	}

	/* ============================================================== mã NV · PIN */

	/**
	 * Mã kế tiếp của một tiền tố: số lớn nhất đang có + 1, giữ độ dài số của mã cũ (mặc định 4).
	 * ⚠️ Soát cả mã trong lịch sử chấm công — mã của người đã xoá hồ sơ vẫn nằm ở đó, cấp lại
	 *    là ghép giờ công cũ của người ta vào người mới.
	 */
	public static function sinh_ma( $tien_to ) {
		global $wpdb;
		$tt = strtoupper( trim( (string) $tien_to ) );
		$max = 0; $rong = 4;
		foreach ( array( array( 'nhan_vien', 'ma_nv' ), array( 'cham_cong', 'ma_nv' ) ) as $x ) {
			$ds = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT ' . $x[1] . ' FROM ' . VHCC_DB::t( $x[0] )
				. ' WHERE UPPER(' . $x[1] . ') LIKE %s', $wpdb->esc_like( $tt ) . '%' ) );
			foreach ( $ds as $ma ) {
				$duoi = substr( strtoupper( (string) $ma ), strlen( $tt ) );
				if ( ! preg_match( '/^\d+$/', $duoi ) ) { continue; }
				if ( (int) $duoi > $max ) { $max = (int) $duoi; }
				$rong = max( $rong, strlen( $duoi ) );
			}
		}
		return $tt . str_pad( (string) ( $max + 1 ), $rong, '0', STR_PAD_LEFT );
	}

	/** PIN 6 số: đúng luật `VHCC_Quyen::pin_hop_le()` và chưa ai dùng. */
	public static function sinh_pin() {
		for ( $i = 0; $i < 200; $i++ ) {
			$p = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			if ( '' === VHCC_Quyen::pin_hop_le( $p ) && '' === VHCC_NhanSu::pin_dang_dung( $p ) ) { return $p; }
		}
		return '';
	}

	/* ============================================================== sổ tiếp nhận */

	public static function so() {
		$d = VHCC_Luong::cai_dat( self::O_SO, null );
		return is_array( $d ) ? $d : array();
	}

	public static function ban_ghi( $ma ) {
		$so = self::so();
		$k = strtolower( trim( (string) $ma ) );
		return isset( $so[ $k ] ) ? $so[ $k ] : null;
	}

	private static function ghi_ban( $u, $bg ) {
		$so = self::so();
		$so[ strtolower( $bg['ma'] ) ] = $bg;
		VHCC_Luong::dat_cai_dat( self::O_SO, $so, $u );
	}

	private static function ngay_cong( $ngay, $so_ngay ) {
		$t = strtotime( $ngay . ' 00:00:00' );
		return gmdate( 'Y-m-d', $t + (int) $so_ngay * 86400 );
	}

	/**
	 * TIẾP NHẬN MỘT NGƯỜI.
	 *
	 * @param array $dat ho_ten, cccd, ngay_sinh, gioi_tinh, sdt, email, dia_chi, so_tai_khoan,
	 *                   ngan_hang, mau, coso, ngay_vao, (luong, don_gia, luong_bh — để trống = theo mẫu)
	 * @return array { ok, ma, pin, buoc, link } — `buoc` là kết quả từng bước.
	 */
	public static function tao( $u, $dat ) {
		global $wpdb;
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$g = function ( $k ) use ( $dat ) { return trim( (string) ( isset( $dat[ $k ] ) ? $dat[ $k ] : '' ) ); };

		$m = self::mau( $g( 'mau' ) );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Chưa chọn mẫu chức vụ (hoặc mẫu đã bị xoá).' ); }
		$ho_ten = $g( 'ho_ten' );
		if ( '' === $ho_ten ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }
		$cccd = preg_replace( '/\D+/', '', $g( 'cccd' ) );
		if ( ! preg_match( '/^\d{9,12}$/', $cccd ) ) {
			return array( 'ok' => false, 'error' => 'Số CCCD phải 9–12 chữ số — hợp đồng cần số này.' );
		}
		$trung = VHCC_NhanSu::ho_so_theo_cccd( $cccd );
		if ( $trung ) {
			return array( 'ok' => false, 'error' => 'CCCD này đã có hồ sơ: ' . $trung['ma_nv'] . ' — ' . $trung['ho_ten']
				. ' (' . $trung['cua_hang'] . '). Không tạo trùng người.' );
		}
		$email = strtolower( $g( 'email' ) );
		if ( '' !== $email && ! is_email( $email ) ) { return array( 'ok' => false, 'error' => 'Email không đúng dạng.' ); }
		$coso = VHCC_NhanSu::chuan_coso( $g( 'coso' ) );
		if ( '' === $coso || ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở, hoặc cơ sở ngoài phạm vi của anh/chị.' );
		}
		$ngay_vao = $g( 'ngay_vao' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay_vao ) ) { $ngay_vao = (string) current_time( 'Y-m-d' ); }
		$ngay_sinh = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'ngay_sinh' ) ) ? $g( 'ngay_sinh' ) : null;

		$luong   = '' !== $g( 'luong' ) ? VHCC_NhanSu::so_tien( $g( 'luong' ) ) : (float) $m['luong'];
		$don_gia = '' !== $g( 'don_gia' ) ? VHCC_NhanSu::so_tien( $g( 'don_gia' ) ) : (float) $m['don_gia'];
		$luong_bh = '' !== $g( 'luong_bh' ) ? VHCC_NhanSu::so_tien( $g( 'luong_bh' ) )
			: ( (float) $m['luong_bh'] > 0 ? (float) $m['luong_bh'] : $luong );
		if ( 'thang' === $m['cach'] && $luong <= 0 ) { return array( 'ok' => false, 'error' => 'Thiếu mức lương.' ); }
		if ( 'gio' === $m['cach'] && $don_gia <= 0 ) { return array( 'ok' => false, 'error' => 'Thiếu đơn giá giờ.' ); }

		$ma  = self::sinh_ma( $m['tien_to'] );
		$pin = self::sinh_pin();
		if ( '' === $pin ) { return array( 'ok' => false, 'error' => 'Không sinh được PIN chưa ai dùng — thử lại.' ); }

		/* Ngày của hợp đồng. */
		$tv = (int) $m['thu_viec'];
		$het_tv = $tv > 0 ? self::ngay_cong( $ngay_vao, $tv - 1 ) : '';
		$bd_hd  = $tv > 0 ? self::ngay_cong( $ngay_vao, $tv ) : $ngay_vao;
		$kt_hd  = '';
		if ( 'xac_dinh' === $m['loai_hd'] ) {
			$kt_hd = gmdate( 'Y-m-d', strtotime( $bd_hd . ' +' . (int) $m['thoi_han'] . ' months -1 day' ) );
		}
		$so_bg = self::so();
		$so_hd = sprintf( '%03d/%s/HĐLĐ', count( $so_bg ) + 1, substr( $bd_hd, 0, 4 ) );

		/* ---- bước 1: hồ sơ ---- */
		$ghi = array(
			'ma_nv' => $ma, 'ho_ten' => $ho_ten, 'cua_hang' => $coso, 'chuc_vu' => $m['ten'],
			'bo_phan' => $m['bo_phan'], 'mang' => $m['mang'], 'vai_tro' => $m['vai_tro'],
			'pin_dang_nhap' => $pin, 'email' => $email, 'sdt' => $g( 'sdt' ), 'cccd' => $cccd,
			'ngay_sinh' => $ngay_sinh, 'gioi_tinh' => $g( 'gioi_tinh' ), 'dia_chi' => $g( 'dia_chi' ),
			'so_tai_khoan' => $g( 'so_tai_khoan' ), 'ngan_hang' => $g( 'ngan_hang' ),
			'luong_co_ban' => 'thang' === $m['cach'] ? $luong : 0,
			'ngay_vao_lam' => $ngay_vao, 'trang_thai_lam_viec' => 'Đang làm',
			'loai_hop_dong' => $tv > 0 ? 'Thử việc' : ( 'xac_dinh' === $m['loai_hd'] ? 'Xác định thời hạn' : 'Không xác định thời hạn' ),
			'cap_nhat' => current_time( 'mysql' ),
		);
		$loi_pin = VHCC_NhanSu::pin_trung_loi( $ghi, $ma, array( $coso ) );
		if ( '' !== (string) $loi_pin ) { return array( 'ok' => false, 'error' => $loi_pin ); }
		$ok = $wpdb->insert( VHCC_DB::t( 'nhan_vien' ), $ghi );
		if ( ! $ok ) { return array( 'ok' => false, 'error' => 'Không ghi được hồ sơ ' . $ma . '.' ); }

		$bg = array(
			'ma' => $ma, 'luc' => current_time( 'mysql' ), 'boi' => isset( $u['name'] ) ? (string) $u['name'] : '',
			'mau' => $m, 'pinHash' => self::bam_pin( $ma, $pin ),
			'hd' => array(
				'so' => $so_hd, 'ho_ten' => $ho_ten, 'cccd' => $cccd, 'ngay_sinh' => (string) $ngay_sinh,
				'gioi_tinh' => $g( 'gioi_tinh' ), 'dia_chi' => $g( 'dia_chi' ), 'sdt' => $g( 'sdt' ), 'email' => $email,
				'chuc_vu' => $m['ten'], 'coso' => $coso, 'ten_coso' => VHCC_NhanSu::ten_coso( $coso ),
				'cach' => $m['cach'], 'luong' => $luong, 'don_gia' => $don_gia, 'cong_chuan' => $m['cong_chuan'],
				'tv' => $tv, 'tv_pt' => (int) $m['tv_pt'], 'ngay_vao' => $ngay_vao, 'het_tv' => $het_tv,
				'bd_hd' => $bd_hd, 'kt_hd' => $kt_hd, 'loai_hd' => $m['loai_hd'], 'thoi_han' => (int) $m['thoi_han'],
				'bhxh' => (int) $m['bhxh'], 'luong_bh' => $luong_bh, 'mo_ta' => $m['mo_ta'], 'dieu_them' => $m['dieu_them'],
				'stk' => $g( 'so_tai_khoan' ), 'ngan_hang' => $g( 'ngan_hang' ), 'cty' => self::cty(),
			),
			'buoc' => array( 'ho_so' => array( 'ok' => true, 'chu' => 'Mã ' . $ma . ' · PIN đã cấp · vai ' . $m['vai_tro'] . ' · ' . $coso ) ),
			/* Công ty "ký" khi người có thẩm quyền duyệt tiếp nhận — ghi ai duyệt, lúc nào. Nhân viên
			   ký sau qua bộ hồ sơ (`ky_hd()`). */
			'ctyKy' => array( 'ten' => (string) self::cty()['dai_dien'], 'boi' => isset( $u['name'] ) ? (string) $u['name'] : '',
				'luc' => current_time( 'mysql' ) ),
		);
		self::ghi_ban( $u, $bg );

		foreach ( array_keys( self::BUOC ) as $b ) {
			if ( 'ho_so' === $b ) { continue; }
			self::chay_buoc( $u, $ma, $b );
		}
		$bg = self::ban_ghi( $ma );
		return array( 'ok' => true, 'ma' => $ma, 'pin' => $pin, 'buoc' => $bg['buoc'], 'link' => self::link_bo( $ma ) );
	}

	/** Chạy (lại) MỘT bước cho một người đã tiếp nhận. */
	public static function chay_buoc( $u, $ma, $b ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$bg = self::ban_ghi( $ma );
		if ( ! $bg ) { return array( 'ok' => false, 'error' => 'Không có bản ghi tiếp nhận của ' . $ma . '.' ); }
		if ( ! isset( self::BUOC[ $b ] ) || 'ho_so' === $b ) { return array( 'ok' => false, 'error' => 'Bước không hợp lệ.' ); }
		$ma = $bg['ma']; $m = $bg['mau']; $hd = $bg['hd'];
		$ok = true; $chu = array();
		try {
			if ( 'quyen' === $b ) {
				if ( ! $m['quyen'] ) { $chu[] = 'theo vai trò, không khai riêng'; }
				foreach ( (array) $m['quyen'] as $tr => $v ) {
					$r = VHCC_Cong::dat( $u, $ma, $tr, $v );
					if ( empty( $r['ok'] ) ) { $ok = false; $chu[] = $tr . ': ' . $r['error']; }
					else { $chu[] = ( isset( VHCC_Cong::SO[ $tr ] ) ? VHCC_Cong::SO[ $tr ]['ten'] : $tr ) . ( 'mo' === $v ? ' mở' : ' khoá' ); }
				}
			} elseif ( 'app' === $b ) {
				if ( ! $m['app'] ) { $chu[] = 'không đẩy app nào'; }
				foreach ( (array) $m['app'] as $c ) {
					$lop = self::APP[ $c ];
					if ( ! class_exists( $lop ) || ! method_exists( $lop, 'dat' ) ) { $ok = false; $chu[] = $c . ': chưa cài'; continue; }
					$r = call_user_func( array( $lop, 'dat' ), $u, $ma, 'mo' );
					if ( empty( $r['ok'] ) ) { $ok = false; $chu[] = $c . ': ' . ( isset( $r['error'] ) ? $r['error'] : 'hỏng' ); }
					else { $chu[] = $c . ' ✔'; }
				}
			} elseif ( 'may' === $b ) {
				if ( empty( $m['may'] ) ) { $chu[] = 'mẫu không đẩy lên máy'; }
				else {
					$r = VHCC_NhanSu::day_ho_so_moi_len_may( $u, $ma );
					if ( empty( $r['ok'] ) ) { $ok = false; $chu[] = $r['error']; }
					else { $chu[] = isset( $r['thong_bao'] ) ? $r['thong_bao'] : 'đã xếp lệnh lên máy'; }
				}
			} elseif ( 'luong' === $b ) {
				if ( 'gio' === $hd['cach'] ) {
					$r = VHCC_GiaGio::dat_nguoi( $u, $ma, array( $hd['chuc_vu'] => $hd['don_gia'] ) );
					if ( empty( $r['ok'] ) ) { $ok = false; $chu[] = $r['error']; }
					else { $chu[] = 'đơn giá ' . number_format( (float) $hd['don_gia'], 0, ',', '.' ) . 'đ/giờ'; }
				} else {
					$chu[] = 'lương tháng ' . number_format( (float) $hd['luong'], 0, ',', '.' ) . 'đ (hồ sơ)';
					/* Tháng còn thử việc (thử việc phủ tới ngày 15 trở đi của tháng ấy) ăn lương thử việc. */
					if ( $hd['tv'] > 0 ) {
						$lt = round( (float) $hd['luong'] * (int) $hd['tv_pt'] / 100 );
						$th = substr( $hd['ngay_vao'], 0, 7 );
						$th_het = substr( $hd['het_tv'], 0, 7 );
						for ( $i = 0; $i < 7 && $th <= $th_het; $i++ ) {
							if ( $th < $th_het || (int) substr( $hd['het_tv'], 8, 2 ) >= 15 ) {
								$r = VHCC_ChotLuong::dat_thang( $u, $hd['coso'], $th, $ma, true, (string) $lt, (string) $hd['cong_chuan'] );
								if ( empty( $r['ok'] ) ) { $ok = false; $chu[] = $th . ': ' . $r['error']; }
								else { $chu[] = $th . ' thử việc ' . number_format( $lt, 0, ',', '.' ) . 'đ'; }
							}
							$th = gmdate( 'Y-m', strtotime( $th . '-01 +1 month' ) );
						}
					}
				}
			} elseif ( 'bhxh' === $b ) {
				if ( empty( $hd['bhxh'] ) ) { $chu[] = 'chức vụ không đóng BHXH'; }
				else {
					$tien = round( (float) $hd['luong_bh'] * self::TY_LE_BH );
					/* Đóng từ tháng HĐLĐ chính thức bắt đầu; bắt đầu sau ngày 15 thì từ tháng sau —
					   tháng ấy làm chưa đủ nửa tháng theo hợp đồng chính thức (cùng mốc với lương thử
					   việc ở bước Lương, để hai bước không vênh nhau). */
					$tu = substr( $hd['bd_hd'], 0, 7 );
					if ( (int) substr( $hd['bd_hd'], 8, 2 ) > 15 ) { $tu = gmdate( 'Y-m', strtotime( $tu . '-01 +1 month' ) ); }
					$r = VHCC_Bhxh::dat( $u, $ma, $tien, $tu );
					if ( empty( $r['ok'] ) ) { $ok = false; $chu[] = $r['error']; }
					else { $chu[] = number_format( $tien, 0, ',', '.' ) . 'đ/tháng (10,5% × ' . number_format( (float) $hd['luong_bh'], 0, ',', '.' ) . 'đ) từ ' . $tu; }
				}
			} elseif ( 'giayto' === $b ) {
				$chu[] = 'HĐ số ' . $hd['so'] . ( $hd['tv'] > 0 ? ' + HĐ thử việc ' . $hd['tv'] . ' ngày' : '' );
			} elseif ( 'gui' === $b ) {
				$r = self::gui( $ma );
				$ok = $r['ok'];
				$chu[] = $r['chu'];
			}
		} catch ( \Throwable $e ) {
			$ok = false; $chu[] = 'lỗi: ' . $e->getMessage();
		}
		$bg = self::ban_ghi( $ma );
		$bg['buoc'][ $b ] = array( 'ok' => $ok, 'chu' => implode( ' · ', $chu ), 'luc' => current_time( 'mysql' ) );
		self::ghi_ban( $u, $bg );
		return array( 'ok' => true, 'buocOk' => $ok );
	}

	/* ============================================================== bộ hồ sơ + gửi */

	private static function bam_pin( $ma, $pin ) {
		return hash_hmac( 'sha256', strtolower( (string) $ma ) . '|' . (string) $pin, wp_salt( 'auth' ) . '|vhcc-tn-pin' );
	}

	private static function ky( $ma ) {
		return substr( hash_hmac( 'sha256', strtolower( trim( (string) $ma ) ) . '|nhan-viec', wp_salt( 'auth' ) . '|vhcc-tn' ), 0, 32 );
	}

	public static function link_bo( $ma ) {
		return add_query_arg( array( 'vhcc_nhanviec' => '1', 'm' => strtolower( trim( (string) $ma ) ),
			'k' => self::ky( $ma ) ), home_url( '/' ) );
	}

	/** Gửi link bộ hồ sơ qua chuông + email. */
	public static function gui( $ma ) {
		global $wpdb;
		$bg = self::ban_ghi( $ma );
		if ( ! $bg ) { return array( 'ok' => false, 'chu' => 'không có bản ghi' ); }
		$hd = $bg['hd']; $link = self::link_bo( $bg['ma'] );
		$chu = array(); $ok = true;
		if ( VHCC_Chuong::bao( $bg['ma'], 'Chào mừng ' . $hd['ho_ten'] . ' — bộ hồ sơ nhận việc (mã NV, PIN, hợp đồng) đã sẵn sàng.',
			'nhan_viec_' . strtolower( $bg['ma'] ), '', $link ) ) {
			$chu[] = 'chuông ✔';
		}
		$email = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . VHCC_DB::t( 'nhan_vien' )
			. ' WHERE ma_nv=%s', $bg['ma'] ) );
		if ( '' === trim( $email ) || ! is_email( $email ) ) {
			$ok = false; $chu[] = 'chưa có email — in bộ hồ sơ trao tay, hoặc thêm email rồi gửi lại';
		} else {
			$cty = VHCC_Pdf::ten_cong_ty();
			$than = '<p>Chào ' . esc_html( $hd['ho_ten'] ) . ',</p>'
				. '<p>' . esc_html( $cty ) . ' chào mừng bạn nhận việc <b>' . esc_html( $hd['chuc_vu'] ) . '</b> tại '
				. esc_html( $hd['ten_coso'] ) . ' từ ngày ' . esc_html( self::ngay_vn( $hd['ngay_vao'] ) ) . '.</p>'
				. '<p><a href="' . esc_url( $link ) . '">Bấm vào đây để mở bộ hồ sơ nhận việc</a> — gồm mã nhân viên, '
				. 'mật khẩu (PIN) đăng nhập chấm công, hướng dẫn sử dụng và hợp đồng. Mở ra bấm "In / Lưu thành PDF" để lưu lại.</p>'
				. '<p><small>Link chỉ dành cho bạn. Đừng chuyển tiếp thư này. Đăng nhập xong nên đổi PIN.</small></p>'
				. '<p>' . esc_html( $cty ) . '</p>';
			if ( wp_mail( $email, 'Chào mừng nhận việc — ' . $cty, $than, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
				$chu[] = 'email ✔ ' . $email;
			} else { $ok = false; $chu[] = 'gửi email KHÔNG được — kiểm tra cấu hình gửi thư của site'; }
		}
		return array( 'ok' => $ok, 'chu' => implode( ' · ', $chu ) );
	}

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 1 );
	}

	public static function maybe_render() {
		$uv = ! empty( $_GET['vhcc_ungvien'] );
		if ( empty( $_GET['vhcc_nhanviec'] ) && ! $uv ) { return; }
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::phuc_vu_cong_khai( $_GET, $_POST, isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '',
			isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '' );
		exit;
	}

	/** Ruột của hai trang công khai (bộ hồ sơ + ký · ứng viên tự điền). Tách để bộ thử gọi được. */
	public static function phuc_vu_cong_khai( $get, $post, $ip = '', $ua = '' ) {
		$g = function ( $a, $k ) { return isset( $a[ $k ] ) && ! is_array( $a[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $a[ $k ] ) ) : ''; };
		if ( ! empty( $get['vhcc_ungvien'] ) ) {
			$t = $g( $get, 't' );
			if ( ! empty( $post['uv'] ) && is_array( $post['uv'] ) ) {
				$d = array();
				foreach ( $post['uv'] as $k => $v ) { if ( ! is_array( $v ) ) { $d[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) ); } }
				$r = self::nop( $t, $d );
				return self::trang_ung_vien( $t, empty( $r['ok'] ) ? $r['error'] : '', ! empty( $r['ok'] ) );
			}
			return self::trang_ung_vien( $t );
		}
		$m = $g( $get, 'm' ); $k = $g( $get, 'k' );
		$bao = '';
		if ( ! empty( $post['ky_hd'] ) ) {
			$r = self::ky_hd( $m, $k, array(
				'ten'     => $g( $post, 'ky_ten' ),
				'dong_y'  => ! empty( $post['ky_dong_y'] ),
				'anh'     => isset( $post['ky_anh'] ) && ! is_array( $post['ky_anh'] ) ? (string) wp_unslash( $post['ky_anh'] ) : '',
			), $ip, $ua );
			$bao = empty( $r['ok'] ) ? '✖ ' . $r['error'] : '✔ Đã ký hợp đồng điện tử. Bản xác nhận đã gửi về email (nếu có).';
		}
		return self::trang_bo( $m, $k, $bao );
	}

	private static function ngay_vn( $d ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $d, $x ) ) { return '…'; }
		return $x[3] . '/' . $x[2] . '/' . $x[1];
	}

	private static function tien( $v ) { return number_format( (float) $v, 0, ',', '.' ) . ' đồng'; }

	/** HTML bộ hồ sơ nhận việc: thư chào mừng + HĐ thử việc (nếu có) + HĐLĐ. Sai chữ ký → trang lỗi. */
	public static function trang_bo( $ma, $k, $bao = '' ) {
		$dau = '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow"><title>Bộ hồ sơ nhận việc</title><style>'
			. '@page{size:A4 portrait;margin:15mm 15mm}'
			. 'body{font-family:"Times New Roman",Times,serif;color:#111;margin:0;padding:16px;font-size:14px;line-height:1.5;background:#fff}'
			. '.to{max-width:780px;margin:0 auto}.trang{page-break-after:always;padding-bottom:24px}.trang:last-child{page-break-after:auto}'
			. 'h1{font-size:18px;text-align:center;margin:8px 0}h2{font-size:15px;margin:14px 0 4px}'
			. '.giua{text-align:center}.qh{text-align:center;font-weight:700}.mo{color:#555;font-size:12.5px}'
			. 'table{width:100%;border-collapse:collapse;margin:8px 0}td,th{border:1px solid #999;padding:6px 8px;text-align:left}'
			. '.pin{font-size:26px;letter-spacing:6px;font-weight:700}.thanh{text-align:center;margin:0 0 12px}'
			. '.thanh button{font-size:15px;padding:8px 16px}.ky{display:flex;justify-content:space-between;margin-top:30px;text-align:center}'
			. '.ky div{width:45%}.ky img{max-height:70px;max-width:100%}.xac{font-size:11.5px;color:#555}'
			. '.bao{border:1px solid #bbb;background:#f6f6f6;padding:8px 10px;margin:0 0 12px;border-radius:6px}'
			. '.kyf{border:2px solid #333;border-radius:8px;padding:12px;margin:16px 0}.kyf input[type=text]{width:100%;font-size:16px;padding:6px}'
			. '#kv{border:1px dashed #999;width:100%;max-width:600px;height:160px;touch-action:none;background:#fff;display:block}'
			. '.kyf button{font-size:16px;padding:8px 16px;margin-top:8px}'
			. '@media print{.thanh,.kyf,.bao{display:none}body{padding:0}}'
			. '</style></head><body><div class="to">';
		$cuoi = '</div></body></html>';
		$bg = self::ban_ghi( $ma );
		if ( ! $bg || '' === trim( (string) $ma ) || ! hash_equals( self::ky( $ma ), (string) $k ) ) {
			return $dau . '<h1>Bộ hồ sơ nhận việc</h1><p class="giua">Link không hợp lệ.</p>' . $cuoi;
		}
		$hd = $bg['hd']; $cty = $hd['cty']; $mau = $bg['mau'];
		$hs = VHCC_NhanSu::ho_so( $bg['ma'] );
		$pin = $hs ? (string) $hs['pin_dang_nhap'] : '';
		$con_ngay = ( strtotime( (string) current_time( 'mysql' ) ) - strtotime( (string) $bg['luc'] ) ) < self::PIN_HIEN_NGAY * 86400;
		$hien_pin = '' !== $pin && $con_ngay && hash_equals( (string) $bg['pinHash'], self::bam_pin( $bg['ma'], $pin ) );
		$e = function ( $s ) { return esc_html( (string) $s ); };

		$h = $dau . ( '' !== $bao ? '<div class="bao">' . $e( $bao ) . '</div>' : '' )
			. '<div class="thanh"><button onclick="window.print()">In / Lưu thành PDF</button></div>';
		$nv_ky = isset( $bg['nvKy'] ) ? $bg['nvKy'] : null;
		$cty_ky = isset( $bg['ctyKy'] ) ? $bg['ctyKy'] : null;
		$ma_xt = strtoupper( implode( '-', str_split( substr( self::bam_hd( $bg ), 0, 16 ), 4 ) ) );

		/* ---- 1. thư chào mừng ---- */
		$h .= '<div class="trang"><div><b>' . $e( $cty['ten'] ) . '</b>' . ( '' !== $cty['dia_chi'] ? '<br>' . $e( $cty['dia_chi'] ) : '' ) . '</div>';
		$h .= '<h1>THƯ CHÀO MỪNG NHẬN VIỆC</h1>';
		$h .= '<p>Chào <b>' . $e( $hd['ho_ten'] ) . '</b>,</p><p>' . $e( $cty['ten'] ) . ' chào mừng bạn gia nhập đội ngũ với vị trí <b>'
			. $e( $hd['chuc_vu'] ) . '</b> tại <b>' . $e( $hd['ten_coso'] ) . '</b>, bắt đầu từ ngày <b>' . $e( self::ngay_vn( $hd['ngay_vao'] ) ) . '</b>.</p>';
		$h .= '<table><tr><th>Mã nhân viên</th><td><b>' . $e( $bg['ma'] ) . '</b></td></tr>'
			. '<tr><th>Mật khẩu (PIN) đăng nhập</th><td>' . ( $hien_pin ? '<span class="pin">' . $e( $pin ) . '</span>'
				: '<i>đã ẩn (đã đổi PIN hoặc quá ' . self::PIN_HIEN_NGAY . ' ngày) — dùng "Quên PIN" trên trang chấm công</i>' ) . '</td></tr>'
			. '<tr><th>Vai trò trên hệ thống</th><td>' . $e( $mau['vai_tro'] ) . '</td></tr>'
			. '<tr><th>Trang chấm công</th><td>' . $e( VHCC_Tram::url() ) . '</td></tr></table>';
		$h .= '<h2>Bắt đầu sử dụng</h2><ol>'
			. '<li>Mở trang chấm công ở trên bằng điện thoại, bấm "Thêm vào màn hình chính" để dùng như một ứng dụng.</li>'
			. '<li>Đăng nhập bằng PIN 6 số ở trên. Nên đổi PIN riêng của bạn ngay lần đầu (tab <b>Tôi</b>).</li>'
			. '<li>Mỗi ca làm: bấm <b>Chấm vào</b> khi bắt đầu và <b>Chấm ra</b> khi kết thúc, tại đúng cơ sở.</li>'
			. '<li>Xin nghỉ, đi trễ, xin bù giờ: gửi đơn ngay trên ứng dụng.</li>'
			. '<li>Phiếu lương hằng tháng gửi về ứng dụng và email khi kế toán công bố.</li></ol>';
		$h .= '<p class="mo">Giữ kín mật khẩu. Ai biết PIN của bạn có thể chấm công thay bạn — đó là vi phạm kỷ luật.</p></div>';

		$ben = function () use ( $e, $cty, $hd ) {
			return '<p><b>Bên A (Người sử dụng lao động): ' . $e( $cty['ten'] ) . '</b><br>'
				. 'Địa chỉ: ' . $e( '' !== $cty['dia_chi'] ? $cty['dia_chi'] : '…………' ) . '<br>'
				. 'Mã số thuế: ' . $e( '' !== $cty['mst'] ? $cty['mst'] : '…………' ) . ( '' !== $cty['sdt'] ? ' · Điện thoại: ' . $e( $cty['sdt'] ) : '' ) . '<br>'
				. 'Đại diện: ' . $e( '' !== $cty['dai_dien'] ? $cty['dai_dien'] : '…………' ) . ' · Chức vụ: ' . $e( $cty['chuc_vu_dd'] ) . '</p>'
				. '<p><b>Bên B (Người lao động): ' . $e( $hd['ho_ten'] ) . '</b><br>'
				. 'Ngày sinh: ' . $e( self::ngay_vn( $hd['ngay_sinh'] ) ) . ( '' !== $hd['gioi_tinh'] ? ' · Giới tính: ' . $e( $hd['gioi_tinh'] ) : '' ) . '<br>'
				. 'Số CCCD: ' . $e( $hd['cccd'] ) . '<br>'
				. 'Nơi cư trú: ' . $e( '' !== $hd['dia_chi'] ? $hd['dia_chi'] : '…………' ) . '<br>'
				. 'Điện thoại: ' . $e( '' !== $hd['sdt'] ? $hd['sdt'] : '…………' ) . ( '' !== $hd['stk'] ? ' · Tài khoản nhận lương: ' . $e( $hd['stk'] . ' ' . $hd['ngan_hang'] ) : '' ) . '</p>';
		};
		$luong_chu = function ( $pt ) use ( $hd ) {
			if ( 'gio' === $hd['cach'] ) {
				return self::tien( round( (float) $hd['don_gia'] * $pt / 100 ) ) . '/giờ làm việc thực tế theo dữ liệu chấm công';
			}
			return self::tien( round( (float) $hd['luong'] * $pt / 100 ) ) . '/tháng'
				. ( '' !== (string) $hd['cong_chuan'] ? ', tính theo số ngày công thực tế trên ' . $hd['cong_chuan'] . ' ngày công chuẩn' : ', tính theo ngày công thực tế' );
		};
		$ky = function ( $ten_b ) use ( $e, $cty, $nv_ky, $cty_ky, $ma_xt ) {
			$nv = $nv_ky
				? ( '' !== (string) $nv_ky['anh'] ? '<img alt="chữ ký" src="' . esc_attr( $nv_ky['anh'] ) . '">' : '<br><br>' )
					. '<div class="xac">✔ Đã ký điện tử ' . $e( $nv_ky['luc'] ) . '<br>mã xác thực ' . $e( $ma_xt ) . '</div>'
				: '<br><br><br><br>';
			$ct = $cty_ky
				? '<br><br><div class="xac">✔ Đã ký điện tử khi duyệt tiếp nhận ' . $e( $cty_ky['luc'] ) . '</div>'
				: '<br><br><br><br>';
			return '<div class="ky"><div><b>NGƯỜI LAO ĐỘNG</b><br><i>(Ký, ghi rõ họ tên)</i>' . $nv . $e( $ten_b ) . '</div>'
				. '<div><b>NGƯỜI SỬ DỤNG LAO ĐỘNG</b><br><i>(Ký tên, đóng dấu)</i>' . $ct . $e( $cty['dai_dien'] ) . '</div></div>';
		};
		$quoc_hieu = '<p class="qh">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM<br>Độc lập – Tự do – Hạnh phúc</p><p class="giua">———————</p>';

		/* ---- 2. hợp đồng thử việc (Điều 24–27 BLLĐ 2019) ---- */
		if ( $hd['tv'] > 0 ) {
			$h .= '<div class="trang">' . $quoc_hieu . '<h1>HỢP ĐỒNG THỬ VIỆC</h1><p class="giua">Số: TV-' . $e( $hd['so'] ) . '</p>';
			$h .= '<p>Căn cứ Bộ luật Lao động số 45/2019/QH14 ngày 20/11/2019; hôm nay, ngày ' . $e( self::ngay_vn( substr( $bg['luc'], 0, 10 ) ) ) . ', chúng tôi gồm:</p>' . $ben();
			$h .= '<h2>Điều 1. Công việc và địa điểm</h2><p>Bên B thử việc vị trí <b>' . $e( $hd['chuc_vu'] ) . '</b> tại <b>' . $e( $hd['ten_coso'] ) . '</b>.'
				. ( '' !== $hd['mo_ta'] ? ' Nội dung công việc: ' . $e( $hd['mo_ta'] ) . '.' : ' Nội dung công việc theo phân công của quản lý trực tiếp.' ) . '</p>';
			$h .= '<h2>Điều 2. Thời gian thử việc</h2><p>' . (int) $hd['tv'] . ' ngày, từ ngày ' . $e( self::ngay_vn( $hd['ngay_vao'] ) )
				. ' đến hết ngày ' . $e( self::ngay_vn( $hd['het_tv'] ) ) . ' (không quá thời hạn tại Điều 25 BLLĐ 2019).</p>';
			$h .= '<h2>Điều 3. Tiền lương thử việc</h2><p>' . $luong_chu( (int) $hd['tv_pt'] ) . ' — bằng ' . (int) $hd['tv_pt']
				. '% mức lương chính thức (không thấp hơn 85% theo Điều 26 BLLĐ 2019). Trả bằng chuyển khoản, một lần mỗi tháng.</p>';
			$h .= '<h2>Điều 4. Thời giờ làm việc</h2><p>Theo ca được xếp trên hệ thống chấm công và nội quy lao động của Bên A; giờ làm việc ghi nhận bằng dữ liệu chấm công.</p>';
			$h .= '<h2>Điều 5. Kết thúc thử việc</h2><p>Trong thời gian thử việc, mỗi bên có quyền hủy bỏ thỏa thuận mà không cần báo trước và không phải bồi thường. '
				. 'Khi kết thúc thử việc, Bên A thông báo kết quả cho Bên B; nếu đạt, hai bên tiếp tục thực hiện hợp đồng lao động số ' . $e( $hd['so'] ) . ' (Điều 27 BLLĐ 2019).</p>';
			$h .= '<h2>Điều 6. Điều khoản chung</h2><p>Hợp đồng lập thành 02 bản có giá trị như nhau, mỗi bên giữ 01 bản.</p>' . $ky( $hd['ho_ten'] ) . '</div>';
		}

		/* ---- 3. hợp đồng lao động (Điều 20, 21 BLLĐ 2019) ---- */
		$h .= '<div class="trang">' . $quoc_hieu . '<h1>HỢP ĐỒNG LAO ĐỘNG</h1><p class="giua">Số: ' . $e( $hd['so'] ) . '</p>';
		$h .= '<p>Căn cứ Bộ luật Lao động số 45/2019/QH14 ngày 20/11/2019 và các văn bản hướng dẫn; căn cứ nhu cầu và năng lực của hai bên, chúng tôi gồm:</p>' . $ben();
		$h .= '<p>Hai bên thỏa thuận ký kết hợp đồng lao động với các điều khoản sau:</p>';
		$h .= '<h2>Điều 1. Loại hợp đồng và thời hạn</h2><p>' . ( 'xac_dinh' === $hd['loai_hd']
			? 'Hợp đồng lao động xác định thời hạn ' . (int) $hd['thoi_han'] . ' tháng, từ ngày ' . $e( self::ngay_vn( $hd['bd_hd'] ) ) . ' đến ngày ' . $e( self::ngay_vn( $hd['kt_hd'] ) ) . '.'
			: 'Hợp đồng lao động không xác định thời hạn, từ ngày ' . $e( self::ngay_vn( $hd['bd_hd'] ) ) . '.' )
			. ( $hd['tv'] > 0 ? ' Hợp đồng có hiệu lực khi Bên B đạt yêu cầu thử việc theo Hợp đồng thử việc số TV-' . $e( $hd['so'] ) . '.' : '' ) . '</p>';
		$h .= '<h2>Điều 2. Công việc và địa điểm làm việc</h2><p>Chức danh: <b>' . $e( $hd['chuc_vu'] ) . '</b>. Địa điểm: <b>' . $e( $hd['ten_coso'] ) . '</b>'
			. ' và các cơ sở khác của Bên A khi được điều động phù hợp với Điều 29 BLLĐ 2019. '
			. ( '' !== $hd['mo_ta'] ? 'Nội dung công việc: ' . $e( $hd['mo_ta'] ) . '.' : 'Nội dung công việc theo mô tả vị trí và phân công của quản lý trực tiếp.' ) . '</p>';
		$h .= '<h2>Điều 3. Thời giờ làm việc, thời giờ nghỉ ngơi</h2><p>Thời giờ làm việc bình thường không quá 08 giờ/ngày và 48 giờ/tuần, theo ca được xếp trên hệ thống chấm công. '
			. 'Làm thêm giờ theo thỏa thuận và giới hạn của Điều 107. Nghỉ hằng tuần, nghỉ lễ, Tết, nghỉ hằng năm (12 ngày/năm làm việc đủ 12 tháng) và nghỉ việc riêng theo Điều 111–115 BLLĐ 2019.</p>';
		$h .= '<h2>Điều 4. Tiền lương, phụ cấp và hình thức trả lương</h2><p>Mức lương: <b>' . $luong_chu( 100 ) . '</b>. '
			. 'Các khoản phụ cấp, hỗ trợ và thưởng (nếu có) theo quy chế của Bên A và được ghi trên phiếu lương hằng tháng. '
			. 'Hình thức trả: chuyển khoản' . ( '' !== $hd['stk'] ? ' vào tài khoản ' . $e( $hd['stk'] . ' ' . $hd['ngan_hang'] ) : '' ) . ', mỗi tháng một lần. '
			. 'Tiền làm thêm giờ, làm việc ban đêm, ngày lễ được trả theo Điều 98 BLLĐ 2019. Việc nâng lương xét theo năng lực, hiệu quả công việc và quy chế của Bên A.</p>';
		$h .= '<h2>Điều 5. Bảo hiểm xã hội, bảo hiểm y tế, bảo hiểm thất nghiệp</h2><p>' . ( ! empty( $hd['bhxh'] )
			? 'Hai bên tham gia BHXH, BHYT, BHTN bắt buộc theo quy định kể từ khi hợp đồng lao động này có hiệu lực. Mức tiền lương đóng bảo hiểm: ' . self::tien( $hd['luong_bh'] ) . '/tháng; phần Bên B đóng (10,5%) được khấu trừ vào lương hằng tháng, phần Bên A đóng theo quy định.'
			: 'Hai bên thực hiện BHXH, BHYT, BHTN theo quy định của pháp luật hiện hành đối với loại hợp đồng này.' ) . '</p>';
		$h .= '<h2>Điều 6. Quyền và nghĩa vụ của Bên B</h2><p>Được trả lương đầy đủ, đúng hạn; được bảo đảm an toàn, vệ sinh lao động; được nghỉ theo chế độ; được đơn phương chấm dứt hợp đồng theo Điều 35 BLLĐ 2019. '
			. 'Có nghĩa vụ hoàn thành công việc được giao; chấp hành nội quy, quy chế, sự điều hành hợp pháp của Bên A; chấm công trung thực; bảo vệ tài sản và bí mật kinh doanh của Bên A; bồi thường thiệt hại do lỗi của mình theo quy định.</p>';
		$h .= '<h2>Điều 7. Quyền và nghĩa vụ của Bên A</h2><p>Có quyền điều hành, phân công, khen thưởng, xử lý kỷ luật theo nội quy; đơn phương chấm dứt hợp đồng theo Điều 36 BLLĐ 2019. '
			. 'Có nghĩa vụ trả lương đúng hạn, đóng bảo hiểm cho Bên B theo quy định, bảo đảm điều kiện làm việc, cung cấp dữ liệu chấm công và phiếu lương minh bạch cho Bên B.</p>';
		$h .= '<h2>Điều 8. Chấm dứt hợp đồng</h2><p>Hợp đồng chấm dứt trong các trường hợp tại Điều 34 BLLĐ 2019. Bên muốn đơn phương chấm dứt phải báo trước đúng thời hạn luật định '
			. '(tối thiểu 30 ngày với hợp đồng xác định thời hạn, 45 ngày với hợp đồng không xác định thời hạn, trừ các trường hợp không phải báo trước).</p>';
		if ( '' !== $hd['dieu_them'] ) {
			$h .= '<h2>Điều 9. Thỏa thuận khác</h2><p>' . nl2br( $e( $hd['dieu_them'] ) ) . '</p>';
		}
		$h .= '<h2>Điều ' . ( '' !== $hd['dieu_them'] ? '10' : '9' ) . '. Điều khoản thi hành</h2><p>Những vấn đề không ghi trong hợp đồng thực hiện theo nội quy lao động, thỏa ước (nếu có) và pháp luật lao động. '
			. 'Hợp đồng lập thành 02 bản có giá trị pháp lý như nhau, mỗi bên giữ 01 bản.</p>' . $ky( $hd['ho_ten'] ) . '</div>';

		/* ---- 4. ký điện tử ---- */
		if ( $nv_ky ) {
			$h .= '<p class="xac">Hợp đồng đã được ký điện tử (Luật Giao dịch điện tử 2023): người lao động ký lúc ' . $e( $nv_ky['luc'] )
				. ' · mã xác thực nội dung ' . $e( $ma_xt ) . '. Mã này đổi nếu nội dung hợp đồng bị sửa.</p>';
		} else {
			$h .= '<form method="post" class="kyf"><h2>✍ Ký hợp đồng điện tử</h2>'
				. '<p>Ký một lần cho cả ' . ( $hd['tv'] > 0 ? 'Hợp đồng thử việc và ' : '' ) . 'Hợp đồng lao động ở trên. Chữ ký điện tử có giá trị như chữ ký tay theo Luật Giao dịch điện tử 2023.</p>'
				. '<label>Gõ đúng họ tên của bạn: <input type="text" name="ky_ten" autocomplete="name" placeholder="' . esc_attr( $hd['ho_ten'] ) . '"></label>'
				. '<p>Vẽ chữ ký (ngón tay hoặc chuột):</p><canvas id="kv" width="600" height="160"></canvas>'
				. '<button type="button" id="kxoa">Vẽ lại</button><input type="hidden" name="ky_anh" id="kanh" value="">'
				. '<p><label><input type="checkbox" name="ky_dong_y" value="1"> Tôi đã đọc kỹ và đồng ý toàn bộ nội dung hợp đồng.</label></p>'
				. '<input type="hidden" name="ky_hd" value="1"><button type="submit">Ký hợp đồng</button></form>'
				. '<script>(function(){var c=document.getElementById("kv"),o=document.getElementById("kanh");if(!c||!c.getContext){return;}'
				. 'var x=c.getContext("2d"),v=false,co=false;x.lineWidth=2.5;x.lineCap="round";x.strokeStyle="#111";'
				. 'function p(e){var r=c.getBoundingClientRect(),t=e.touches?e.touches[0]:e;return[(t.clientX-r.left)*c.width/r.width,(t.clientY-r.top)*c.height/r.height];}'
				. 'function d(e){v=true;var q=p(e);x.beginPath();x.moveTo(q[0],q[1]);e.preventDefault();}'
				. 'function m(e){if(!v){return;}var q=p(e);x.lineTo(q[0],q[1]);x.stroke();co=true;e.preventDefault();}'
				. 'function u(){if(v&&co){o.value=c.toDataURL("image/png");}v=false;}'
				. 'c.addEventListener("mousedown",d);c.addEventListener("mousemove",m);window.addEventListener("mouseup",u);'
				. 'c.addEventListener("touchstart",d,{passive:false});c.addEventListener("touchmove",m,{passive:false});c.addEventListener("touchend",u);'
				. 'document.getElementById("kxoa").onclick=function(){x.clearRect(0,0,c.width,c.height);o.value="";co=false;};})();</script>';
		}
		return $h . $cuoi;
	}

	/* ============================================================== ký điện tử */

	/** Băm nội dung hợp đồng (bản chụp `hd` + mã) — mã xác thực in trên hợp đồng đã ký. */
	public static function bam_hd( $bg ) {
		return hash( 'sha256', wp_json_encode( $bg['hd'] ) . '|' . $bg['ma'] );
	}

	/**
	 * NHÂN VIÊN KÝ HỢP ĐỒNG ĐIỆN TỬ qua bộ hồ sơ.
	 *
	 * 🔴 Ký MỘT LẦN, không sửa, không ký lại — chữ ký đã ghi là bằng chứng. Ghi kèm: tên gõ, ảnh
	 *    chữ ký (nếu vẽ), giờ, IP, thiết bị, và mã băm nội dung lúc ký (`bam`) — nội dung đổi thì
	 *    mã in trên hợp đồng không còn khớp bản đã ký.
	 * ⚠️ Tên gõ phải khớp họ tên trên hợp đồng (bỏ dấu, không phân biệt hoa/thường, gộp khoảng
	 *    trắng) — người ký phải biết mình đang ký cho ai.
	 */
	public static function ky_hd( $ma, $k, $dat, $ip = '', $ua = '' ) {
		global $wpdb;
		$bg = self::ban_ghi( $ma );
		if ( ! $bg || '' === trim( (string) $ma ) || ! hash_equals( self::ky( $ma ), (string) $k ) ) {
			return array( 'ok' => false, 'error' => 'Link không hợp lệ.' );
		}
		if ( ! empty( $bg['nvKy'] ) ) { return array( 'ok' => false, 'error' => 'Hợp đồng đã được ký lúc ' . $bg['nvKy']['luc'] . '.' ); }
		$chuan = function ( $s ) { return preg_replace( '/\s+/', ' ', VHCC_Luong::bo_chu( (string) $s ) ); };
		if ( '' === trim( (string) $dat['ten'] ) || $chuan( $dat['ten'] ) !== $chuan( $bg['hd']['ho_ten'] ) ) {
			return array( 'ok' => false, 'error' => 'Họ tên gõ vào không khớp họ tên trên hợp đồng (' . $bg['hd']['ho_ten'] . ').' );
		}
		if ( empty( $dat['dong_y'] ) ) { return array( 'ok' => false, 'error' => 'Chưa tích "Tôi đã đọc kỹ và đồng ý".' ); }
		$anh = (string) $dat['anh'];
		if ( '' !== $anh && ( 0 !== strpos( $anh, 'data:image/png;base64,' ) || strlen( $anh ) > 400000
			|| false === base64_decode( substr( $anh, 22 ), true ) ) ) {
			$anh = '';
		}
		$bg['nvKy'] = array( 'ten' => trim( (string) $dat['ten'] ), 'anh' => $anh, 'luc' => current_time( 'mysql' ),
			'ip' => substr( (string) $ip, 0, 64 ), 'ua' => substr( (string) $ua, 0, 250 ), 'bam' => self::bam_hd( $bg ) );
		self::ghi_ban( array( 'name' => 'NV tự ký: ' . $bg['hd']['ho_ten'] ), $bg );

		$email = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', $bg['ma'] ) );
		if ( '' !== trim( $email ) && is_email( $email ) ) {
			$cty = VHCC_Pdf::ten_cong_ty();
			wp_mail( $email, 'Xác nhận đã ký hợp đồng — ' . $cty, '<p>Chào ' . esc_html( $bg['hd']['ho_ten'] ) . ',</p>'
				. '<p>Bạn đã ký điện tử hợp đồng số ' . esc_html( $bg['hd']['so'] ) . ' lúc ' . esc_html( $bg['nvKy']['luc'] ) . '.</p>'
				. '<p><a href="' . esc_url( self::link_bo( $bg['ma'] ) ) . '">Xem lại và lưu bản hợp đồng đã ký (PDF)</a></p>'
				. '<p>' . esc_html( $cty ) . '</p>', array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
		return array( 'ok' => true, 'luc' => $bg['nvKy']['luc'] );
	}

	/* ============================================================== link ứng viên tự điền */

	const O_MOI = 'TN_MOI';

	/** Link mời còn hạn bao nhiêu ngày. */
	const MOI_NGAY = 7;

	public static function ds_moi() {
		$d = VHCC_Luong::cai_dat( self::O_MOI, null );
		$d = is_array( $d ) ? $d : array();
		uasort( $d, function ( $a, $b ) { return strcmp( (string) $b['luc'], (string) $a['luc'] ); } );
		return $d;
	}

	private static function ghi_moi( $u, $t, $x ) {
		$d = VHCC_Luong::cai_dat( self::O_MOI, null );
		$d = is_array( $d ) ? $d : array();
		$d[ $t ] = $x;
		VHCC_Luong::dat_cai_dat( self::O_MOI, $d, $u );
	}

	public static function link_moi( $t ) {
		return add_query_arg( array( 'vhcc_ungvien' => '1', 't' => (string) $t ), home_url( '/' ) );
	}

	/**
	 * Tạo LINK MỜI: công ty chốt sẵn chức vụ (mẫu), cơ sở, ngày vào, lương — ứng viên chỉ điền
	 * thông tin cá nhân. `tu_tao` = ứng viên gửi xong là chạy tiếp nhận luôn, nhân danh người
	 * tạo link; không tích thì chờ người tạo bấm Duyệt.
	 */
	public static function tao_moi( $u, $dat ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$m = self::mau( isset( $dat['mau'] ) ? $dat['mau'] : '' );
		if ( ! $m ) { return array( 'ok' => false, 'error' => 'Chưa chọn mẫu chức vụ.' ); }
		$coso = VHCC_NhanSu::chuan_coso( isset( $dat['coso'] ) ? $dat['coso'] : '' );
		if ( '' === $coso || ! VHCC_NhanSu::co_quyen_coso( $u, $coso ) ) {
			return array( 'ok' => false, 'error' => 'Chưa chọn cơ sở, hoặc cơ sở ngoài phạm vi của anh/chị.' );
		}
		$t = bin2hex( random_bytes( 16 ) );
		$x = array(
			't' => $t, 'mau' => (string) $dat['mau'], 'ten_mau' => $m['ten'], 'coso' => $coso,
			'ngay_vao' => isset( $dat['ngay_vao'] ) ? (string) $dat['ngay_vao'] : '',
			'luong_sua' => isset( $dat['luong_sua'] ) ? (string) $dat['luong_sua'] : '',
			'luong_bh' => isset( $dat['luong_bh'] ) ? (string) $dat['luong_bh'] : '',
			'tu_tao' => empty( $dat['tu_tao'] ) ? 0 : 1, 'tt' => 'cho',
			'luc' => current_time( 'mysql' ), 'het' => gmdate( 'Y-m-d H:i:s', strtotime( (string) current_time( 'mysql' ) ) + self::MOI_NGAY * 86400 ),
			'boi' => isset( $u['name'] ) ? (string) $u['name'] : '',
			/* Nhân danh ai khi tự tạo — chụp đúng mấy khoá phiên cần cho các chốt quyền. */
			'u' => array( 'name' => isset( $u['name'] ) ? (string) $u['name'] : '', 'role' => isset( $u['role'] ) ? (string) $u['role'] : '',
				'coso' => isset( $u['coso'] ) ? (string) $u['coso'] : '', 'ma_nv' => isset( $u['ma_nv'] ) ? (string) $u['ma_nv'] : '' ),
			'du_lieu' => array(), 'ma' => '', 'loi' => '',
		);
		self::ghi_moi( $u, $t, $x );
		return array( 'ok' => true, 't' => $t, 'link' => self::link_moi( $t ) );
	}

	private static function du_lieu_tao( $x ) {
		$d = (array) $x['du_lieu'];
		$d['mau'] = $x['mau']; $d['coso'] = $x['coso']; $d['ngay_vao'] = $x['ngay_vao']; $d['luong_bh'] = $x['luong_bh'];
		$m = self::mau( $x['mau'] );
		if ( '' !== trim( (string) $x['luong_sua'] ) && $m ) { $d[ 'gio' === $m['cach'] ? 'don_gia' : 'luong' ] = $x['luong_sua']; }
		return $d;
	}

	/** Ứng viên gửi thông tin. */
	public static function nop( $t, $dat ) {
		$ds = self::ds_moi();
		$x = isset( $ds[ (string) $t ] ) ? $ds[ (string) $t ] : null;
		if ( ! $x || 'huy' === $x['tt'] ) { return array( 'ok' => false, 'error' => 'Link không hợp lệ hoặc đã bị huỷ.' ); }
		if ( 'cho' !== $x['tt'] ) { return array( 'ok' => false, 'error' => 'Link này đã được dùng — thông tin đã gửi rồi.' ); }
		if ( strtotime( (string) current_time( 'mysql' ) ) > strtotime( (string) $x['het'] ) ) {
			return array( 'ok' => false, 'error' => 'Link đã hết hạn — xin liên hệ công ty để nhận link mới.' );
		}
		$g = function ( $k ) use ( $dat ) { return trim( (string) ( isset( $dat[ $k ] ) ? $dat[ $k ] : '' ) ); };
		if ( '' === $g( 'ho_ten' ) ) { return array( 'ok' => false, 'error' => 'Thiếu họ tên.' ); }
		$cccd = preg_replace( '/\D+/', '', $g( 'cccd' ) );
		if ( ! preg_match( '/^\d{9,12}$/', $cccd ) ) { return array( 'ok' => false, 'error' => 'Số CCCD phải 9–12 chữ số.' ); }
		if ( VHCC_NhanSu::ho_so_theo_cccd( $cccd ) ) {
			return array( 'ok' => false, 'error' => 'Số CCCD này đã có hồ sơ ở công ty — xin liên hệ người phụ trách.' );
		}
		if ( '' === $g( 'sdt' ) ) { return array( 'ok' => false, 'error' => 'Thiếu số điện thoại.' ); }
		if ( '' !== $g( 'email' ) && ! is_email( $g( 'email' ) ) ) { return array( 'ok' => false, 'error' => 'Email không đúng dạng.' ); }
		$du = array();
		foreach ( array( 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'dia_chi', 'so_tai_khoan', 'ngan_hang' ) as $k ) { $du[ $k ] = $g( $k ); }
		$du['cccd'] = $cccd;
		$x['du_lieu'] = $du; $x['tt'] = 'da_dien'; $x['luc_nop'] = current_time( 'mysql' );
		if ( ! empty( $x['tu_tao'] ) ) {
			$r = self::tao( $x['u'], self::du_lieu_tao( $x ) );
			if ( ! empty( $r['ok'] ) ) { $x['tt'] = 'da_tao'; $x['ma'] = $r['ma']; $x['loi'] = ''; }
			else { $x['loi'] = $r['error']; }
		}
		self::ghi_moi( array( 'name' => 'Ứng viên: ' . $du['ho_ten'] ), $t, $x );
		if ( '' !== (string) $x['u']['ma_nv'] ) {
			VHCC_Chuong::bao( $x['u']['ma_nv'], 'Ứng viên ' . $du['ho_ten'] . ' đã điền thông tin (' . $x['ten_mau'] . ')'
				. ( 'da_tao' === $x['tt'] ? ' — đã tự tạo mã ' . $x['ma'] . '.' : ' — chờ duyệt.' ), 'tn_moi_' . $t, '',
				add_query_arg( array( 'man' => 'tiep_nhan' ), VHCC_Web::url() ) );
		}
		return array( 'ok' => true, 'tt' => $x['tt'], 'ma' => $x['ma'] );
	}

	/** Người tạo duyệt một link ứng viên đã điền (khi không tự tạo, hoặc tự tạo bị lỗi). */
	public static function duyet_moi( $u, $t ) {
		$ds = self::ds_moi();
		$x = isset( $ds[ (string) $t ] ) ? $ds[ (string) $t ] : null;
		if ( ! $x || 'da_dien' !== $x['tt'] ) { return array( 'ok' => false, 'error' => 'Link này không ở trạng thái chờ duyệt.' ); }
		$r = self::tao( $u, self::du_lieu_tao( $x ) );
		if ( empty( $r['ok'] ) ) {
			$x['loi'] = $r['error']; self::ghi_moi( $u, $t, $x );
			return $r;
		}
		$x['tt'] = 'da_tao'; $x['ma'] = $r['ma']; $x['loi'] = '';
		self::ghi_moi( $u, $t, $x );
		return $r;
	}

	public static function huy_moi( $u, $t ) {
		$chan = self::gac( $u );
		if ( '' !== $chan ) { return array( 'ok' => false, 'error' => $chan ); }
		$ds = self::ds_moi();
		if ( ! isset( $ds[ (string) $t ] ) ) { return array( 'ok' => false, 'error' => 'Không có link này.' ); }
		$x = $ds[ (string) $t ];
		if ( 'da_tao' === $x['tt'] ) { return array( 'ok' => false, 'error' => 'Đã tạo hồ sơ rồi — không huỷ được link.' ); }
		$x['tt'] = 'huy';
		self::ghi_moi( $u, $t, $x );
		return array( 'ok' => true );
	}

	/** Trang công khai ứng viên điền thông tin. */
	public static function trang_ung_vien( $t, $loi = '', $xong = false ) {
		$cty = self::cty();
		$dau = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow"><title>Thông tin nhận việc</title><style>'
			. 'body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f4;color:#111;margin:0;padding:16px}'
			. '.to{max-width:560px;margin:0 auto;background:#fff;border-radius:10px;padding:18px}h1{font-size:20px;margin:0 0 6px}'
			. 'label{display:block;margin:10px 0 3px;font-weight:600;font-size:14px}input,select{width:100%;box-sizing:border-box;font-size:16px;padding:8px;border:1px solid #bbb;border-radius:6px}'
			. 'button{margin-top:16px;width:100%;font-size:17px;padding:12px;border:0;border-radius:8px;background:#1a56db;color:#fff}'
			. '.loi{background:#fde8e8;border:1px solid #f5b5b5;padding:8px 10px;border-radius:6px;margin:10px 0}'
			. '.ok{background:#e6f4ea;border:1px solid #a8d5b5;padding:10px;border-radius:6px}.mo{color:#555;font-size:13px}'
			. '</style></head><body><div class="to">';
		$cuoi = '</div></body></html>';
		$ds = self::ds_moi();
		$x = isset( $ds[ (string) $t ] ) ? $ds[ (string) $t ] : null;
		if ( $xong ) {
			return $dau . '<h1>Cảm ơn bạn!</h1><div class="ok">Thông tin đã gửi tới ' . esc_html( $cty['ten'] ) . '. '
				. 'Bạn sẽ nhận email (hoặc tin nhắn từ người phụ trách) kèm mã nhân viên, mật khẩu chấm công và hợp đồng để ký.</div>' . $cuoi;
		}
		if ( ! $x || 'huy' === $x['tt'] ) { return $dau . '<h1>Thông tin nhận việc</h1><div class="loi">Link không hợp lệ hoặc đã bị huỷ.</div>' . $cuoi; }
		if ( 'cho' !== $x['tt'] ) { return $dau . '<h1>Thông tin nhận việc</h1><div class="ok">Link này đã được dùng — thông tin đã gửi rồi.</div>' . $cuoi; }
		if ( strtotime( (string) current_time( 'mysql' ) ) > strtotime( (string) $x['het'] ) ) {
			return $dau . '<h1>Thông tin nhận việc</h1><div class="loi">Link đã hết hạn — xin liên hệ công ty để nhận link mới.</div>' . $cuoi;
		}
		$h = $dau . '<h1>Thông tin nhận việc — ' . esc_html( $cty['ten'] ) . '</h1>'
			. '<p class="mo">Vị trí: <b>' . esc_html( $x['ten_mau'] ) . '</b> · ' . esc_html( VHCC_NhanSu::ten_coso( $x['coso'] ) )
			. ( '' !== $x['ngay_vao'] ? ' · bắt đầu ' . esc_html( self::ngay_vn( $x['ngay_vao'] ) ) : '' ) . '</p>'
			. '<p class="mo">Điền đúng như trên CCCD — thông tin này dùng để lập hợp đồng lao động và đóng bảo hiểm.</p>'
			. ( '' !== $loi ? '<div class="loi">' . esc_html( $loi ) . '</div>' : '' )
			. '<form method="post">';
		$o = function ( $k, $n, $kieu = 'text', $bat = false ) {
			return '<label for="uv_' . $k . '">' . $n . ( $bat ? ' *' : '' ) . '</label><input id="uv_' . $k . '" name="uv[' . $k . ']" type="' . $kieu . '"'
				. ( $bat ? ' required' : '' ) . ' value="' . ( isset( $_POST['uv'][ $k ] ) && ! is_array( $_POST['uv'][ $k ] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_POST['uv'][ $k ] ) ) ) : '' ) . '">';
		};
		$h .= $o( 'ho_ten', 'Họ và tên', 'text', true ) . $o( 'cccd', 'Số CCCD', 'text', true ) . $o( 'ngay_sinh', 'Ngày sinh', 'date' )
			. '<label for="uv_gt">Giới tính</label><select id="uv_gt" name="uv[gioi_tinh]"><option value="">—</option><option>Nam</option><option>Nữ</option></select>'
			. $o( 'sdt', 'Số điện thoại', 'tel', true ) . $o( 'email', 'Email (nhận hợp đồng, mật khẩu)', 'email' ) . $o( 'dia_chi', 'Nơi cư trú' )
			. $o( 'so_tai_khoan', 'Số tài khoản ngân hàng (nhận lương)' ) . $o( 'ngan_hang', 'Ngân hàng' )
			. '<button type="submit">Gửi thông tin</button></form>';
		return $h . $cuoi;
	}

	/** Mấy người tiếp nhận gần nhất (mới nhất trước). */
	public static function gan_day( $so_luong = 30 ) {
		$ds = array_values( self::so() );
		usort( $ds, function ( $a, $b ) { return strcmp( (string) $b['luc'], (string) $a['luc'] ); } );
		return array_slice( $ds, 0, $so_luong );
	}
}
