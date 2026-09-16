<?php
/**
 * DOANH THU & CHI PHÍ.
 *
 * =================================================================================================
 * 🔴 TỔNG TIỀN LÀ VIỆC CỦA MÁY CHỦ, TRANG CHỈ VẼ
 * =================================================================================================
 * Trình duyệt gửi lên SỐ LƯỢNG từng loại vé. Đơn giá và mọi phép cộng đều làm ở đây, đọc từ danh
 * mục trong kho. Nhận `tong_thu` do trang gửi lên là mở cửa cho bất kỳ ai gọi thẳng API khai một
 * ngày 500 vé mà tổng thu 0đ — và sổ kế toán sẽ nói dối đúng con số người ta mang đi đối chiếu
 * ngân hàng. Đây cũng là chỗ bản Firebase làm ngược: nó tính ở máy khách rồi ghi thẳng.
 *
 * =================================================================================================
 * 🔴 MỘT CƠ SỞ · MỘT NGÀY · MỘT BẢN GHI
 * =================================================================================================
 * Khoá duy nhất `(coso, ngay)` nằm ở CSDL, không phải ở lớp PHP. Hai người cùng bấm "Lưu báo cáo"
 * lúc giao ca thì người sau ghi đè người trước — đúng một dòng, không sinh dòng thứ hai. Nếu chỉ
 * kiểm "đã có chưa" bằng PHP rồi mới ghi thì hai lượt chạy song song đều thấy "chưa có" và cùng
 * chèn, ra hai dòng cho một ngày, và mọi báo cáo tháng từ đó gấp đôi.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_Tien {

	/**
	 * Danh mục vé mặc định — lấy nguyên từ bản đang chạy, kể cả mấy loại giá 0đ.
	 *
	 * `vao_thu` = có tính vào doanh thu của cơ sở không. Vé online và vé voucher Mall thì KHÔNG:
	 * tiền ấy khách trả cho sàn/đối tác chứ không vào két cơ sở, cộng vào là cuối tháng đối soát
	 * thiếu tiền mặt mà không ai hiểu vì sao.
	 * `vao_khach` = có tính là một lượt khách không. Bùa chú và trích cam thì không — đó là món
	 * bán thêm cho người đã vào, đếm nữa là nhân đôi số khách.
	 */
	public static function ve_mac_dinh() {
		return array(
			array( 'khoa' => 've_1luot',       'nhan' => 'Vé 1 lượt',              'gia' => 100000, 'nhom' => 'le',     'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 've_quaylai2',    'nhan' => 'Vé quay lại lần 2',      'gia' => 90000,  'nhom' => 'le',     'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 've_quaylai3',    'nhan' => 'Vé quay lại lần 3',      'gia' => 80000,  'nhom' => 'le',     'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 've_sv',          'nhan' => 'Vé ưu đãi tân sinh viên','gia' => 70000,  'nhom' => 'le',     'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 've_2k8',         'nhan' => 'Vé ưu đãi 2k8',          'gia' => 80000,  'nhom' => 'le',     'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 'combo_ve_bua',   'nhan' => 'Combo vé + bùa',         'gia' => 120000, 'nhom' => 'combo',  'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 've_le_1luot',    'nhan' => 'Vé 1 lượt (lễ)',         'gia' => 120000, 'nhom' => 'le_tet', 'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 've_le_combo',    'nhan' => 'Combo vé + bùa (lễ)',    'gia' => 140000, 'nhom' => 'le_tet', 'vao_thu' => 1, 'vao_khach' => 1 ),
			array( 'khoa' => 'online_1luot',   'nhan' => 'Vé 1 lượt online',       'gia' => 100000, 'nhom' => 'online', 'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'online_bua',     'nhan' => 'Vé bùa chú online',      'gia' => 30000,  'nhom' => 'online', 'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'online_90k',     'nhan' => 'Vé online 90.000',       'gia' => 90000,  'nhom' => 'online', 'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'online_voucher', 'nhan' => 'Vé voucher Mall',        'gia' => 0,      'nhom' => 'online', 'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'online_giam20',  'nhan' => 'Vé online giảm 20%',     'gia' => 80000,  'nhom' => 'online', 'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'online_giam40',  'nhan' => 'Vé online giảm 40%',     'gia' => 60000,  'nhom' => 'online', 'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'free_1luot',     'nhan' => 'Vé 1 lượt Free',         'gia' => 0,      'nhom' => 'free',   'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'free_bua',       'nhan' => 'Vé bùa chú Free',        'gia' => 0,      'nhom' => 'free',   'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'free_trichcam',  'nhan' => 'Vé trích cam Free',      'gia' => 0,      'nhom' => 'free',   'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'free_combo',     'nhan' => 'Combo vé + bùa Free',    'gia' => 0,      'nhom' => 'free',   'vao_thu' => 0, 'vao_khach' => 1 ),
			array( 'khoa' => 'bua_le',         'nhan' => 'Bùa chú (lẻ)',           'gia' => 30000,  'nhom' => 'them',   'vao_thu' => 1, 'vao_khach' => 0 ),
			array( 'khoa' => 'trich_cam',      'nhan' => 'Trích cam',              'gia' => 50000,  'nhom' => 'them',   'vao_thu' => 1, 'vao_khach' => 0 ),
		);
	}

	const NHOM_NHAN = array(
		'le'     => 'Vé lẻ',
		'combo'  => 'Combo',
		'le_tet' => 'Ngày lễ',
		'online' => 'Vé online (không vào doanh thu cơ sở)',
		'free'   => 'Vé miễn phí',
		'them'   => 'Bán thêm',
	);

	/** Danh mục vé đang dùng — người dùng sửa được, chưa sửa thì dùng bản mặc định. */
	public static function ds_ve() {
		global $wpdb;
		$r = $wpdb->get_var( $wpdb->prepare(
			'SELECT noi_dung FROM ' . VHVH_DB::t( 'danh_muc' ) . ' WHERE loai=%s AND pham_vi=%s',
			've', ''
		) );
		if ( $r ) {
			$j = json_decode( (string) $r, true );
			if ( is_array( $j ) && $j ) { return self::rua_ds_ve( $j ); }
		}
		return self::ve_mac_dinh();
	}

	/**
	 * Soát danh mục vé đọc từ kho.
	 *
	 * ⚠️ Danh mục nằm trong CSDL nên có thể bị sửa tay hoặc bị một bản cũ ghi vào thiếu ô. Thiếu
	 *    `gia` mà cứ dùng thì cả ngày hôm ấy doanh thu ra 0đ và không ai biết vì sao. Nên ở đây
	 *    ép kiểu hết, và bỏ những dòng không có khoá.
	 */
	public static function rua_ds_ve( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $v ) {
			if ( ! is_array( $v ) ) { continue; }
			$khoa = isset( $v['khoa'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $v['khoa'] ) ) : '';
			if ( '' === $khoa ) { continue; }
			$ra[] = array(
				'khoa'      => $khoa,
				'nhan'      => isset( $v['nhan'] ) ? mb_substr( sanitize_text_field( (string) $v['nhan'] ), 0, 120 ) : $khoa,
				'gia'       => isset( $v['gia'] ) ? max( 0, (int) $v['gia'] ) : 0,
				'nhom'      => isset( $v['nhom'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $v['nhom'] ) ) : 'le',
				'vao_thu'   => ! empty( $v['vao_thu'] ) ? 1 : 0,
				'vao_khach' => ! empty( $v['vao_khach'] ) ? 1 : 0,
			);
		}
		return $ra;
	}

	/**
	 * TÍNH TIỀN. Nhận số lượng vé, trả về tổng đã tính từ đơn giá trong kho.
	 *
	 * @param array $so_ve  khoá vé => số lượng (thứ trang gửi lên).
	 * @param array $chi    mảng array( 'nhan' =>, 'tien' => ).
	 * @param array $khac   thu khác, cùng dạng với chi.
	 */
	public static function tinh( $so_ve, $chi = array(), $khac = array() ) {
		$ds   = self::ds_ve();
		$ve   = array();
		$thu  = 0;
		$khach = 0;
		foreach ( $ds as $v ) {
			$n = isset( $so_ve[ $v['khoa'] ] ) ? (int) $so_ve[ $v['khoa'] ] : 0;
			/* Số âm là gõ nhầm hoặc ai đó thử bớt doanh thu — kẹp về 0, không tin. */
			if ( $n < 0 ) { $n = 0; }
			if ( 0 === $n ) { continue; }
			$ve[ $v['khoa'] ] = $n;
			if ( $v['vao_thu'] ) { $thu += $n * $v['gia']; }
			if ( $v['vao_khach'] ) { $khach += $n; }
		}
		$thu_khac = self::rua_dong( $khac );
		foreach ( $thu_khac as $d ) { $thu += $d['tien']; }

		$chi_ra = self::rua_dong( $chi );
		$tong_chi = 0;
		foreach ( $chi_ra as $d ) { $tong_chi += $d['tien']; }

		return array(
			've'        => $ve,
			'thu_khac'  => $thu_khac,
			'chi'       => $chi_ra,
			'tong_thu'  => $thu,
			'tong_chi'  => $tong_chi,
			'khach'     => $khach,
		);
	}

	/** Dòng tiền tự do (chi phí / thu khác): bỏ dòng trống, kẹp số âm. */
	public static function rua_dong( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $d ) {
			if ( ! is_array( $d ) ) { continue; }
			$nhan = isset( $d['nhan'] ) ? mb_substr( sanitize_text_field( (string) $d['nhan'] ), 0, 120 ) : '';
			$tien = isset( $d['tien'] ) ? (int) $d['tien'] : 0;
			if ( $tien < 0 ) { $tien = 0; }
			if ( '' === $nhan && 0 === $tien ) { continue; }
			$ra[] = array( 'nhan' => $nhan, 'tien' => $tien );
		}
		return $ra;
	}

	/** Khách theo khung giờ — chỉ để xem, không dính vào phép cộng tiền. */
	public static function rua_gio( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $d ) {
			if ( ! is_array( $d ) ) { continue; }
			$gio = isset( $d['gio'] ) ? trim( (string) $d['gio'] ) : '';
			if ( '' !== $gio && ! preg_match( '/^\d{1,2}:\d{2}$/', $gio ) ) { $gio = ''; }
			$so = isset( $d['so'] ) ? max( 0, (int) $d['so'] ) : 0;
			if ( '' === $gio && 0 === $so ) { continue; }
			$ra[] = array( 'gio' => $gio, 'so' => $so );
		}
		return $ra;
	}

	/** Ba ô tiền mặt / chuyển khoản / momo. */
	public static function rua_tra( $d ) {
		$d = (array) $d;
		$ra = array();
		foreach ( array( 'mat', 'ck', 'momo' ) as $k ) {
			$ra[ $k ] = isset( $d[ $k ] ) ? max( 0, (int) $d[ $k ] ) : 0;
		}
		return $ra;
	}

	/**
	 * Ghi báo cáo một ngày.
	 *
	 * 🔴 THU NGÂN NỘP THÌ Ở TRẠNG THÁI `cho`, KHÔNG TỰ DUYỆT. Người ghi tiền và người duyệt tiền
	 *    là hai người — bỏ chốt ấy thì sổ không còn ai soát, và ai cũng có thể sửa doanh thu đã
	 *    chốt của ngày hôm qua.
	 * 🔴 BÁO CÁO ĐÃ DUYỆT THÌ KHOÁ. Muốn sửa phải mở lại (chỉ `quan_ly`), và lượt mở lại có ghi
	 *    tên người mở. Cho sửa thẳng là con số kế toán đã mang đi đối chiếu ngân hàng có thể đổi
	 *    sau lưng họ.
	 */
	public static function luu( $u, $d ) {
		global $wpdb;
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		$ngay = isset( $d['ngay'] ) ? trim( (string) $d['ngay'] ) : '';
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) {
			return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' );
		}
		if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }
		if ( ! VHVH_Auth::du_quyen( $u, 'thu_ngan' ) ) { return VHVH_Auth::choi(); }

		$t  = VHVH_DB::t( 'doanh_thu' );
		$cu = $wpdb->get_row( $wpdb->prepare(
			"SELECT id,tt FROM $t WHERE coso=%s AND ngay=%s", $coso, $ngay
		), ARRAY_A );
		if ( $cu && 'duyet' === $cu['tt'] ) {
			return array( 'ok' => false, 'error' => 'Báo cáo ngày này đã duyệt — phải mở lại trước khi sửa.' );
		}

		$tinh = self::tinh(
			isset( $d['ve'] ) ? (array) $d['ve'] : array(),
			isset( $d['chi'] ) ? (array) $d['chi'] : array(),
			isset( $d['thu_khac'] ) ? (array) $d['thu_khac'] : array()
		);

		/* Cửa hàng trưởng trở lên thì báo cáo vào thẳng trạng thái đã duyệt — họ chính là người
		   duyệt. Thu ngân nộp thì phải chờ. */
		$tt = VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ? 'duyet' : 'cho';

		$hang = array(
			'coso'      => $coso,
			'ngay'      => $ngay,
			've'        => wp_json_encode( $tinh['ve'] ),
			'tra_tien'  => wp_json_encode( self::rua_tra( isset( $d['tra_tien'] ) ? $d['tra_tien'] : array() ) ),
			'khach_gio' => wp_json_encode( self::rua_gio( isset( $d['khach_gio'] ) ? $d['khach_gio'] : array() ) ),
			'chi'       => wp_json_encode( $tinh['chi'] ),
			'thu_khac'  => wp_json_encode( $tinh['thu_khac'] ),
			'tong_thu'  => $tinh['tong_thu'],
			'tong_chi'  => $tinh['tong_chi'],
			'khach'     => $tinh['khach'],
			'ghi'       => isset( $d['ghi'] ) ? mb_substr( sanitize_textarea_field( (string) $d['ghi'] ), 0, 2000 ) : '',
			'tt'        => $tt,
			'nguoi_nop' => (string) $u['ten'],
			'sua'       => current_time( 'mysql' ),
		);
		if ( 'duyet' === $tt ) {
			$hang['nguoi_duyet'] = (string) $u['ten'];
			$hang['duyet_luc']   = current_time( 'mysql' );
		}

		if ( $cu ) {
			$wpdb->update( $t, $hang, array( 'id' => (int) $cu['id'] ) );
		} else {
			$hang['tao'] = current_time( 'mysql' );
			/* Khoá duy nhất (coso,ngay) ở CSDL lo phần hai lượt chạy song song: lượt sau hỏng,
			   và mình đọc lại rồi cập nhật thay vì sinh dòng thứ hai. */
			$ok = $wpdb->insert( $t, $hang );
			if ( ! $ok ) {
				$lai = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM $t WHERE coso=%s AND ngay=%s", $coso, $ngay
				) );
				if ( $lai ) { $wpdb->update( $t, $hang, array( 'id' => (int) $lai ) ); }
				else { return array( 'ok' => false, 'error' => 'Không ghi được báo cáo.' ); }
			}
		}
		return array( 'ok' => true, 'ban' => self::doc( $coso, $ngay ) );
	}

	/** Đọc báo cáo một ngày, đã giải mã JSON. */
	public static function doc( $coso, $ngay ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHVH_DB::t( 'doanh_thu' ) . ' WHERE coso=%s AND ngay=%s',
			$coso, $ngay
		), ARRAY_A );
		return $r ? self::mo( $r ) : null;
	}

	/** Giải JSON của một dòng. Dòng hỏng JSON thì trả mảng rỗng chứ không nổ. */
	public static function mo( $r ) {
		foreach ( array( 've', 'tra_tien', 'khach_gio', 'chi', 'thu_khac' ) as $k ) {
			$j = json_decode( isset( $r[ $k ] ) ? (string) $r[ $k ] : '', true );
			$r[ $k ] = is_array( $j ) ? $j : array();
		}
		$r['tong_thu'] = (int) $r['tong_thu'];
		$r['tong_chi'] = (int) $r['tong_chi'];
		$r['khach']    = (int) $r['khach'];
		return $r;
	}

	/**
	 * Duyệt / từ chối / mở lại.
	 *
	 * `mo_lai` chỉ dành cho `quan_ly`: nó gỡ khoá một con số đã chốt.
	 */
	public static function doi_tt( $u, $coso, $ngay, $viec ) {
		global $wpdb;
		if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }
		$can = ( 'mo_lai' === $viec ) ? 'quan_ly' : 'cua_hang_truong';
		if ( ! VHVH_Auth::du_quyen( $u, $can ) ) { return VHVH_Auth::choi(); }

		$map = array( 'duyet' => 'duyet', 'tu_choi' => 'tu_choi', 'mo_lai' => 'cho' );
		if ( ! isset( $map[ $viec ] ) ) { return array( 'ok' => false, 'error' => 'Việc không hợp lệ.' ); }

		$t = VHVH_DB::t( 'doanh_thu' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $t WHERE coso=%s AND ngay=%s", $coso, $ngay ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Chưa có báo cáo ngày này.' ); }

		$hang = array( 'tt' => $map[ $viec ], 'sua' => current_time( 'mysql' ) );
		if ( 'duyet' === $viec ) {
			$hang['nguoi_duyet'] = (string) $u['ten'];
			$hang['duyet_luc']   = current_time( 'mysql' );
		} elseif ( 'mo_lai' === $viec ) {
			/* Mở lại thì XOÁ dấu duyệt cũ. Giữ lại là màn hình nói "đã duyệt bởi X" trong khi
			   bản ghi đang mở cho người khác sửa — X sẽ chịu trách nhiệm cho con số họ không ký. */
			$hang['nguoi_duyet'] = '';
			$hang['duyet_luc']   = null;
		}
		$wpdb->update( $t, $hang, array( 'id' => (int) $r['id'] ) );
		return array( 'ok' => true, 'ban' => self::doc( $coso, $ngay ) );
	}

	/**
	 * Danh sách theo khoảng ngày. Lọc cơ sở theo quyền NGAY TRONG CÂU SQL, không lọc sau khi
	 * đã lấy về — lọc sau thì dữ liệu cơ sở khác vẫn đã rời khỏi CSDL, và chỉ cần một lượt sửa
	 * cẩu thả sau này là nó lọt ra màn hình.
	 */
	public static function ds( $u, $tu, $den, $coso = '' ) {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $tu ) ) { $tu = gmdate( 'Y-m-01' ); }
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $den ) ) { $den = gmdate( 'Y-m-d' ); }

		$dk  = array( 'ngay BETWEEN %s AND %s' );
		$gt  = array( $tu, $den );
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) {
			if ( ! $cho ) { return array(); }
			$dk[] = 'coso IN (' . implode( ',', array_fill( 0, count( $cho ), '%s' ) ) . ')';
			$gt   = array_merge( $gt, $cho );
		}
		$coso = trim( (string) $coso );
		if ( '' !== $coso ) {
			if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return array(); }
			$dk[] = 'coso=%s';
			$gt[] = $coso;
		}
		$sql = 'SELECT * FROM ' . VHVH_DB::t( 'doanh_thu' ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY ngay DESC, coso ASC LIMIT 400';
		$r = $wpdb->get_results( $wpdb->prepare( $sql, $gt ), ARRAY_A );
		return array_map( array( __CLASS__, 'mo' ), $r ? $r : array() );
	}

	/** Bốn con số cho màn Tổng quan, cộng bằng SQL chứ không đọc JSON ra PHP. */
	public static function tom_tat( $u, $tu, $den ) {
		global $wpdb;
		$dk  = array( 'ngay BETWEEN %s AND %s' );
		$gt  = array( $tu, $den );
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) {
			if ( ! $cho ) { return array( 'thu' => 0, 'chi' => 0, 'khach' => 0, 'cho_duyet' => 0 ); }
			$dk[] = 'coso IN (' . implode( ',', array_fill( 0, count( $cho ), '%s' ) ) . ')';
			$gt   = array_merge( $gt, $cho );
		}
		$w = implode( ' AND ', $dk );
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT COALESCE(SUM(tong_thu),0) thu, COALESCE(SUM(tong_chi),0) chi, '
			. 'COALESCE(SUM(khach),0) khach, '
			. "COALESCE(SUM(CASE WHEN tt='cho' THEN 1 ELSE 0 END),0) cho_duyet "
			. 'FROM ' . VHVH_DB::t( 'doanh_thu' ) . " WHERE $w", $gt
		), ARRAY_A );
		return array(
			'thu'       => (int) $r['thu'],
			'chi'       => (int) $r['chi'],
			'khach'     => (int) $r['khach'],
			'cho_duyet' => (int) $r['cho_duyet'],
		);
	}
}
