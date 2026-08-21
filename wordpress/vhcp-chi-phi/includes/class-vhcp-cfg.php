<?php
/**
 * CẤU HÌNH — bản dịch của các sheet CH_* + ma trận phân quyền + người dùng PIN + SSO.
 *
 * Mỗi "sheet cấu hình" là các dòng trong bảng vhcp_cfg (cột `bang` = tên sheet cũ,
 * `cols` = JSON mảng ô của hàng đó) nên giữ đúng thứ tự và số cột như bản Google Sheet.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Cfg {

	const COSO  = 'CH_CoSo';
	const NHOM  = 'CH_Nhom';
	const PL    = 'CH_PhanLoai';
	const DT    = 'CH_DoiTuong';
	const QR    = 'CH_QR';
	const USER  = 'CH_NguoiDung';
	const TKNO  = 'CH_TKNo';
	const SSO   = 'CH_SSO';
	const QUYEN = 'CH_Quyen';
	const LOAI  = 'CH_LoaiChiPhi';   // DANH MỤC LOẠI CHI PHÍ — mỗi loại gắn sẵn mã tài khoản
	const TK    = 'CH_TaiKhoan';     // HỆ THỐNG TÀI KHOẢN của kế toán (nạp từ file Excel/CSV)
	const MANG  = 'CH_MangTK';       // MẢNG KINH DOANH -> nhóm tài khoản 641x + từ khóa trong tên TK

	public static function headers( $bang ) {
		$h = array(
			self::COSO  => array( 'Cơ sở', 'Mã đơn vị', 'Phân loại lớn', 'Tên MISA' ),
			self::NHOM  => array( 'Nhóm mặt hàng', 'Loại', 'TK Nợ', 'Bộ phận' ),
			self::PL    => array( 'Phân loại TT', 'TK Có' ),
			self::DT    => array( 'Đối tượng', 'Mã đối tượng', 'Loại (NV/NCC)' ),
			self::QR    => array( 'Khóa', 'Giá trị' ),
			self::USER  => array( 'Tên', 'PIN', 'Vai trò', 'Cơ sở', 'TK Có', 'Mã đối tượng', 'Bộ phận' ),
			self::TKNO  => array( 'Nhóm mặt hàng', 'Phân loại lớn', 'TK Nợ' ),
			self::SSO   => array( 'Email', 'Vai trò Chi Phí', 'Cơ sở' ),
			self::LOAI  => array( 'Loại chi phí', 'TK Nợ', 'TK Có', 'Mã đối tượng', 'Bộ phận', 'Ghi chú', 'Tên MISA', 'Loại' ),
			self::TK    => array( 'Số hiệu', 'Tên tài khoản', 'Tính chất' ),
			self::MANG  => array( 'Phân loại lớn', 'Nhóm TK', 'Từ khóa trong tên TK', 'Ghi chú' ),
		);
		if ( isset( $h[ $bang ] ) ) { return $h[ $bang ]; }
		if ( $bang === self::QUYEN ) { return array_merge( array( 'Mã', 'Hành động' ), self::roles() ); }
		return array();
	}

	/** Danh sách cơ sở mặc định (COSO_LIST của app cũ). */
	public static function default_coso() {
		return array( 'FUNZONE ADVENTURE', 'FUNZONE VŨNG TÀU', 'FARM PHAN THIẾT', 'EVENT FARM NHA TRANG', 'TÀU TÂN PHÚ', 'TÀU BÌNH TÂN', 'TÀU BÌNH DƯƠNG', 'TÀU GÒ VẤP', 'TÀU ESTELLA', 'VR SORA', 'VR BÌNH DƯƠNG', 'FUNFEST SC VIVO', 'TUTU TẤN AN', 'ADV TÂN PHÚ' );
	}

	/** NHOM_LIST của app cũ. */
	public static function default_nhom() {
		return array(
			array( 'SP Đồ uống - NCC', 'ncc' ),
			array( 'SP Đồ ăn - NCC', 'ncc' ),
			array( 'Vật dụng - NCC - Kho', 'ncc' ),
			array( 'NVL đồ uống - NCC', 'ncc' ),
			array( 'NVL đồ ăn - NCC', 'ncc' ),
			array( 'NVL đồ ăn - Mua lẻ', 'canhan' ),
			array( 'NVL đồ uống - Mua lẻ', 'canhan' ),
			array( 'Chi phí cơ sở', 'canhan' ),
			array( 'MKT - Hoạt náo', 'canhan' ),
			array( 'Nuôi thú', 'canhan' ),
			array( 'Phát sinh', 'canhan' ),
		);
	}

	public static function roles() {
		return array( 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' );
	}

	/** QUYEN_ACTIONS của app cũ (giữ nguyên thứ tự + mặc định). */
	public static function actions() {
		return array(
			array( 'key' => 'duyetTU',   'ten' => 'Duyệt tạm ứng',                'def' => array( 'Quản lý' => 1 ) ),
			array( 'key' => 'capTU',     'ten' => 'Cấp (gửi) tạm ứng',            'def' => array( 'Kế toán cá nhân' => 1 ) ),
			array( 'key' => 'suaTU',     'ten' => 'Sửa số tạm ứng (lúc Nháp)',    'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'suaDong',   'ten' => 'Sửa / thêm / xóa dòng chi',    'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'guiQT',     'ten' => 'Gửi quyết toán / gửi hóa đơn', 'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'gom',       'ten' => 'Gom & đẩy cho kế toán',        'def' => array( 'Quản lý' => 1 ) ),
			array( 'key' => 'xacNhanQT', 'ten' => 'Xác nhận quyết toán (cá nhân)','def' => array( 'Kế toán cá nhân' => 1 ) ),
			array( 'key' => 'duyetNCC',  'ten' => 'Duyệt NCC',                    'def' => array( 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'traDon',    'ten' => 'Trả lại đơn',                  'def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'xuatMISA',  'ten' => 'Xuất / chốt MISA',             'def' => array( 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
			array( 'key' => 'khongDung', 'ten' => 'Đánh dấu "Không dùng"',        'def' => array( 'Quản lý' => 1, 'Nhân viên' => 1 ) ),
			array( 'key' => 'tichCN',    'ten' => 'Tích / bỏ tích Cá nhân↔NCC',   'def' => array( 'Quản lý' => 1, 'Kế toán cá nhân' => 1, 'Kế toán NCC' => 1 ) ),
		);
	}

	// ---------------------------------------------------------------- đọc/ghi bảng cấu hình

	/** Mọi hàng của 1 bảng cấu hình, đã đệm đủ số cột. */
	public static function read( $bang ) {
		global $wpdb;
		$t    = VHCP_DB::t( 'cfg' );
		$n    = count( self::headers( $bang ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", $bang ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$a = json_decode( $r['cols'], true );
			if ( ! is_array( $a ) ) { $a = array(); }
			for ( $i = count( $a ); $i < $n; $i++ ) { $a[ $i ] = ''; }
			$out[] = $a;
		}
		return $out;
	}

	/**
	 * Đọc MỌI bảng cấu hình trong ĐÚNG 1 LỆNH DB -> [ tên bảng => các hàng ].
	 * Trước đây cấu hình tĩnh đọc 6 bảng = 6 lệnh, cộng 4 lệnh đếm dòng để seed.
	 */
	public static function read_all() {
		$t    = VHCP_DB::t( 'cfg' );
		$out  = array();
		foreach ( VHCP_DB::rows( "SELECT bang, cols FROM $t ORDER BY bang ASC, stt ASC, id ASC" ) as $r ) {
			$bang = (string) $r['bang'];
			$a    = json_decode( $r['cols'], true );
			if ( ! is_array( $a ) ) { $a = array(); }
			$n = count( self::headers( $bang ) );
			for ( $i = count( $a ); $i < $n; $i++ ) { $a[ $i ] = ''; }
			$out[ $bang ][] = $a;
		}
		return $out;
	}

	private static function rows_of( $all, $bang ) {
		return isset( $all[ $bang ] ) ? $all[ $bang ] : array();
	}

	/** Ghi đè toàn bộ 1 bảng cấu hình (bỏ hàng có ô đầu trống, giống _writeCfg). */
	public static function write( $bang, $rows, $snapshot = true ) {
		global $wpdb;
		$t = VHCP_DB::t( 'cfg' );
		if ( $snapshot ) {
			VHCP_Meta::set_json( 'cfg_undo', array( 'name' => $bang, 'data' => self::read( $bang ) ) );
		}
		$wpdb->delete( $t, array( 'bang' => $bang ) );
		$i = 0;
		foreach ( (array) $rows as $r ) {
			$r = array_values( (array) $r );
			if ( ! isset( $r[0] ) || trim( (string) $r[0] ) === '' ) { continue; }
			$i++;
			$wpdb->insert( $t, array( 'bang' => $bang, 'stt' => $i, 'cols' => wp_json_encode( $r ) ) );
		}
		self::clear_cache();
	}

	public static function append( $bang, $row ) {
		global $wpdb;
		$t   = VHCP_DB::t( 'cfg' );
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(stt) FROM $t WHERE bang=%s", $bang ) );
		$wpdb->insert( $t, array( 'bang' => $bang, 'stt' => $max + 1, 'cols' => wp_json_encode( array_values( (array) $row ) ) ) );
		self::clear_cache();
	}

	public static function count_rows( $bang ) {
		global $wpdb;
		$t = VHCP_DB::t( 'cfg' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE bang=%s", $bang ) );
	}

	/** Sửa 1 ô (dùng cho seed bổ sung Bộ phận Kỹ thuật). */
	public static function set_cell( $bang, $index0, $col0, $value ) {
		global $wpdb;
		$t    = VHCP_DB::t( 'cfg' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, cols FROM $t WHERE bang=%s ORDER BY stt ASC, id ASC", $bang ), ARRAY_A );
		if ( ! isset( $rows[ $index0 ] ) ) { return; }
		$a = json_decode( $rows[ $index0 ]['cols'], true );
		if ( ! is_array( $a ) ) { $a = array(); }
		for ( $i = count( $a ); $i <= $col0; $i++ ) { $a[ $i ] = ''; }
		$a[ $col0 ] = $value;
		$wpdb->update( $t, array( 'cols' => wp_json_encode( $a ) ), array( 'id' => $rows[ $index0 ]['id'] ) );
		self::clear_cache();
	}

	/** Cấu hình tĩnh của lượt request hiện tại (xóa cùng lúc với cache). */
	private static $memo = null;

	public static function clear_cache() {
		self::$memo = null;
		wp_cache_delete( 'vhcp_cfgstatic', 'vhcp' );
		wp_cache_delete( 'vhcp_quyen', 'vhcp' );
		wp_cache_delete( 'vhcp_ssomap', 'vhcp' );
		delete_transient( 'vhcp_cfgstatic' );
		delete_transient( 'vhcp_quyen' );
		delete_transient( 'vhcp_ssomap' );
	}

	// ---------------------------------------------------------------- seed

	/** Bản dịch của _seedConfig(). */
	public static function seed() {
		self::seed_from( self::read_all() );
	}

	/**
	 * Như _seedConfig() nhưng dùng dữ liệu ĐÃ ĐỌC SẴN (khỏi 4 lệnh đếm dòng).
	 * Trả về true nếu có thêm/ sửa gì -> nơi gọi biết là phải đọc lại.
	 */
	private static function seed_from( $all ) {
		$did = false;
		if ( ! count( self::rows_of( $all, self::COSO ) ) ) {
			foreach ( self::default_coso() as $c ) { self::append( self::COSO, array( $c, '', '', '' ) ); }
			$did = true;
		}
		if ( ! count( self::rows_of( $all, self::NHOM ) ) ) {
			foreach ( self::default_nhom() as $n ) { self::append( self::NHOM, array( $n[0], $n[1], '', '' ) ); }
			$did = true;
		}
		if ( ! count( self::rows_of( $all, self::PL ) ) ) {
			self::append( self::PL, array( 'Thanh toán cá nhân', '141' ) );
			self::append( self::PL, array( 'Nhà cung cấp', '331' ) );
			$did = true;
		}
		if ( ! count( self::rows_of( $all, self::USER ) ) ) {
			self::append( self::USER, array( 'Admin', '1111', 'Admin', '', '', '', '' ) );
			$did = true;
		}
		// Bổ sung 1 lần 2 nhóm chi phí kỹ thuật (tháo dỡ / setup) — gán Bộ phận "Kỹ thuật".
		if ( ! VHCP_Meta::get( 'seeded_thaodo_setup_v2' ) ) {
			$did  = true;
			$rows = self::read( self::NHOM );
			$want = array(
				array( 'Chi phí tháo dỡ', 'canhan', '', 'Kỹ thuật' ),
				array( 'Chi phí setup lắp đặt gian hàng mới', 'canhan', '', 'Kỹ thuật' ),
			);
			foreach ( $want as $x ) {
				$idx = -1;
				foreach ( $rows as $i => $r ) {
					if ( mb_strtolower( trim( (string) $r[0] ) ) === mb_strtolower( $x[0] ) ) { $idx = $i; break; }
				}
				if ( $idx >= 0 ) { self::set_cell( self::NHOM, $idx, 3, 'Kỹ thuật' ); }
				else { self::append( self::NHOM, $x ); $rows = self::read( self::NHOM ); }
			}
			VHCP_Meta::set( 'seeded_thaodo_setup_v2', '1' );
		}

		// Danh mục LOẠI CHI PHÍ: lần đầu dựng từ nhóm mặt hàng đang có (giữ luôn TK Nợ + Bộ phận)
		// để anh không phải khai lại; sau đó sửa độc lập trong tab ⚙️ Cấu hình.
		if ( ! count( self::rows_of( $all, self::LOAI ) ) ) {
			$nhom = self::rows_of( $all, self::NHOM );
			if ( ! count( $nhom ) ) { $nhom = self::read( self::NHOM ); }   // vừa seed trong lượt này -> đọc lại
			foreach ( $nhom as $r ) {
				if ( trim( (string) $r[0] ) === '' ) { continue; }
				self::append( self::LOAI, array( $r[0], isset( $r[2] ) ? $r[2] : '', '', '', isset( $r[3] ) ? $r[3] : '', '' ) );
			}
			$did = true;
		}
		return $did;
	}

	// ---------------------------------------------------------------- cấu hình tĩnh

	/** Bản dịch của _cfgStatic() (cache 5 phút như CacheService). */
	public static function cfg_static() {
		if ( is_array( self::$memo ) ) { return self::$memo; }
		$hit = get_transient( 'vhcp_cfgstatic' );
		if ( is_array( $hit ) ) { self::$memo = $hit; return $hit; }

		$all = self::read_all();
		if ( self::seed_from( $all ) ) { $all = self::read_all(); }   // chỉ đọc lại khi thực sự có seed

		$out = array( 'coso' => array(), 'nhom' => array(), 'loaiChiPhi' => array(), 'tkNoMatrix' => array(), 'phanloai' => array(), 'dtCfg' => array(), 'qr' => array( 'stk' => '', 'bank' => '', 'ten' => '' ) );

		foreach ( self::rows_of( $all, self::COSO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['coso'][] = array( 'ten' => $r[0], 'maDonVi' => $r[1], 'phanLoaiLon' => $r[2], 'tenMisa' => $r[3] );
		}
		foreach ( self::rows_of( $all, self::NHOM ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['nhom'][] = array( 'ten' => $r[0], 'loai' => ( $r[1] !== '' ? $r[1] : 'canhan' ), 'tkNo' => $r[2], 'boPhan' => $r[3] );
		}
		foreach ( self::rows_of( $all, self::LOAI ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['loaiChiPhi'][] = array( 'ten' => $r[0], 'tkNo' => $r[1], 'tkCo' => $r[2], 'maDt' => $r[3], 'boPhan' => $r[4], 'note' => $r[5], 'tenMisa' => isset( $r[6] ) ? $r[6] : '', 'loaiTt' => isset( $r[7] ) ? $r[7] : '' );
		}
		foreach ( self::rows_of( $all, self::TKNO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['tkNoMatrix'][] = array( 'nhom' => $r[0], 'pll' => $r[1], 'tkNo' => $r[2] );
		}
		foreach ( self::rows_of( $all, self::PL ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['phanloai'][] = array( 'ten' => $r[0], 'tkCo' => $r[1] );
		}
		foreach ( self::rows_of( $all, self::DT ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['dtCfg'][] = array( 'ten' => $r[0], 'ma' => $r[1], 'loai' => $r[2] );
		}
		foreach ( self::rows_of( $all, self::QR ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['qr'][ $r[0] ] = $r[1];
		}

		$out['sso'] = array();
		foreach ( self::rows_of( $all, self::SSO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['sso'][] = array( 'email' => $r[0], 'role' => $r[1], 'coso' => $r[2] );
		}
		$out['users'] = array();
		foreach ( self::rows_of( $all, self::USER ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			// Dòng cũ đã nạp lệch (PIN "2222.0") thì rửa ngay lúc ĐỌC, khỏi phải sửa tay
			// từng người mới đăng nhập lại được.
			$out['users'][] = array( 'ten' => $r[0], 'pin' => VHCP_Util::pin_sach( $r[1] ), 'vaiTro' => ( $r[2] !== '' ? $r[2] : 'Nhân viên' ), 'coso' => $r[3], 'tkCo' => VHCP_Util::ma_so( $r[4] ), 'maDt' => VHCP_Util::ma_so( $r[5] ), 'boPhan' => $r[6] );
		}

		// Bảng tra nhanh cho việc chốt TK Nợ: cơ sở -> phân loại lớn, và
		// ma trận [loại chi phí][phân loại lớn] -> TK Nợ (khóa đã hạ chữ thường).
		$out['cosoPll'] = array();
		foreach ( $out['coso'] as $x ) {
			$k = mb_strtolower( trim( (string) $x['ten'] ) );
			if ( $k !== '' ) { $out['cosoPll'][ $k ] = trim( (string) $x['phanLoaiLon'] ); }
		}
		// Một ô có thể khai NHIỀU mã (cách nhau bởi "|") khi cùng một tên gọi chi phí ở
		// cùng một mảng lại hạch toán vào 2 tài khoản khác nhau. Khi đó app KHÔNG tự chọn:
		// ô "Loại chi phí" lúc nhập sẽ tách thành từng dòng mã để người nhập chỉ đúng một.
		$out['tkNoMx'] = array();
		foreach ( $out['tkNoMatrix'] as $x ) {
			$kn = mb_strtolower( trim( (string) $x['nhom'] ) );
			$kp = mb_strtolower( trim( (string) $x['pll'] ) );
			if ( $kn === '' || $kp === '' ) { continue; }
			$ds = array();
			foreach ( explode( '|', (string) $x['tkNo'] ) as $m ) {
				$m = trim( $m );
				if ( $m !== '' && ! in_array( $m, $ds, true ) ) { $ds[] = $m; }
			}
			if ( ! count( $ds ) ) { continue; }
			$out['tkNoMx'][ $kn ][ $kp ] = $ds;
		}

		set_transient( 'vhcp_cfgstatic', $out, 300 );
		self::$memo = $out;
		return $out;
	}

	/**
	 * getConfig(): cấu hình tĩnh + đối tượng hợp nhất từ dòng chi phí + bảng SSO.
	 * $cp_data = mảng dòng chiphi đã đọc sẵn (tiết kiệm 1 lượt đọc như app cũ).
	 */
	public static function get_config( $cp_data = null ) {
		$s  = self::cfg_static();
		$dt = array();
		$seen = array();
		foreach ( $s['dtCfg'] as $d ) {
			$dt[] = array( 'ten' => $d['ten'], 'ma' => $d['ma'], 'loai' => $d['loai'] );
			$seen[ mb_strtolower( (string) $d['ten'] ) ] = 1;
		}
		if ( $cp_data === null ) { $cp_data = VHCP_Don::cp_rows(); }
		foreach ( $cp_data as $r ) {
			$t = trim( (string) $r['doi_tuong'] );
			if ( $t === '' ) { continue; }
			$k = mb_strtolower( $t );
			if ( isset( $seen[ $k ] ) ) { continue; }
			$seen[ $k ] = 1;
			$dt[] = array( 'ten' => $t, 'ma' => '', 'loai' => ( $r['phan_loai_tt'] === 'Nhà cung cấp' ? 'NCC' : 'NV' ) );
		}
		$sso = isset( $s['sso'] ) ? $s['sso'] : array();
		return array(
			'coso'       => $s['coso'],
			'nhom'       => $s['nhom'],
			'loaiChiPhi' => isset( $s['loaiChiPhi'] ) ? $s['loaiChiPhi'] : array(),
			'tkNoMatrix' => $s['tkNoMatrix'],
			'phanloai'   => $s['phanloai'],
			'doiTuong'   => $dt,
			'qr'         => $s['qr'],
			'sso'        => $sso,
		);
	}

	public static function save_config( $cfg ) {
		$cfg = (array) $cfg;
		$g   = function ( $row, $key ) { return isset( $row[ $key ] ) ? (string) $row[ $key ] : ''; };

		if ( isset( $cfg['coso'] ) && is_array( $cfg['coso'] ) ) {
			$rows = array();
			foreach ( $cfg['coso'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), $g( $x, 'maDonVi' ), $g( $x, 'phanLoaiLon' ), $g( $x, 'tenMisa' ) ); }
			self::write( self::COSO, $rows );
		}
		if ( isset( $cfg['nhom'] ) && is_array( $cfg['nhom'] ) ) {
			$rows = array();
			foreach ( $cfg['nhom'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), ( $g( $x, 'loai' ) !== '' ? $g( $x, 'loai' ) : 'canhan' ), $g( $x, 'tkNo' ), $g( $x, 'boPhan' ) ); }
			self::write( self::NHOM, $rows );
		}
		if ( isset( $cfg['loaiChiPhi'] ) && is_array( $cfg['loaiChiPhi'] ) ) {
			// GIỮ LẠI GHI CHÚ CŨ khi dữ liệu gửi lên không mang theo.
			// Bảng ma trận trên giao diện không có cột Ghi chú, nên lưu bảng đó là ghi chú
			// của mọi dòng bị xóa trắng — mất luôn dấu "(nạp từ dữ liệu cũ)" dùng để phân
			// biệt loại thật với tên hạng mục lỡ nạp vào.
			$note_cu = array();
			foreach ( self::read( self::LOAI ) as $r0 ) {
				$r0 = array_values( (array) $r0 );
				$t0 = isset( $r0[0] ) ? mb_strtolower( trim( (string) $r0[0] ) ) : '';
				if ( $t0 !== '' && isset( $r0[5] ) && trim( (string) $r0[5] ) !== '' ) { $note_cu[ $t0 ] = (string) $r0[5]; }
			}
			$rows = array();
			foreach ( $cfg['loaiChiPhi'] as $x ) {
				$x  = (array) $x;
				$tn = $g( $x, 'ten' );
				$nt = $g( $x, 'note' );
				if ( $nt === '' && ! array_key_exists( 'note', $x ) ) {
					$k0 = mb_strtolower( trim( $tn ) );
					if ( isset( $note_cu[ $k0 ] ) ) { $nt = $note_cu[ $k0 ]; }
				}
				$rows[] = array( $tn, $g( $x, 'tkNo' ), $g( $x, 'tkCo' ), $g( $x, 'maDt' ), $g( $x, 'boPhan' ), $nt, $g( $x, 'tenMisa' ), $g( $x, 'loaiTt' ) );
			}
			self::write( self::LOAI, $rows );
		}
		// Cột "Loại" (mua của NCC / cá nhân ứng tiền) khai ngay trong bảng ma trận, nên lưu
		// riêng lẻ: chỉ vá đúng cột đó theo tên loại, không đụng TK Nợ / Tên MISA đã khai.
		if ( isset( $cfg['loaiTt'] ) && is_array( $cfg['loaiTt'] ) ) {
			$moi = array();
			foreach ( $cfg['loaiTt'] as $x ) {
				$x = (array) $x;
				$t = mb_strtolower( trim( $g( $x, 'ten' ) ) );
				if ( $t !== '' ) { $moi[ $t ] = $g( $x, 'loaiTt' ); }
			}
			$rows = array();
			foreach ( self::read( self::LOAI ) as $r ) {
				$row = array_slice( array_values( (array) $r ), 0, 8 );
				for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
				if ( trim( (string) $row[0] ) === '' ) { continue; }
				$t = mb_strtolower( trim( (string) $row[0] ) );
				if ( isset( $moi[ $t ] ) ) { $row[7] = $moi[ $t ]; }
				$rows[] = $row;
			}
			self::write( self::LOAI, $rows );
		}
		if ( isset( $cfg['mangTk'] ) && is_array( $cfg['mangTk'] ) ) {
			$rows = array();
			foreach ( $cfg['mangTk'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'pll' ), $g( $x, 'nhomTk' ), $g( $x, 'tuKhoa' ), $g( $x, 'note' ) ); }
			self::write( self::MANG, $rows );
		}
		if ( isset( $cfg['tkNoMatrix'] ) && is_array( $cfg['tkNoMatrix'] ) ) {
			$rows = array();
			foreach ( $cfg['tkNoMatrix'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'nhom' ), $g( $x, 'pll' ), $g( $x, 'tkNo' ) ); }
			self::write( self::TKNO, $rows );
		}
		if ( isset( $cfg['phanloai'] ) && is_array( $cfg['phanloai'] ) ) {
			$rows = array();
			foreach ( $cfg['phanloai'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), $g( $x, 'tkCo' ) ); }
			self::write( self::PL, $rows );
		}
		if ( isset( $cfg['doiTuong'] ) && is_array( $cfg['doiTuong'] ) ) {
			$rows = array();
			foreach ( $cfg['doiTuong'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'ten' ), $g( $x, 'ma' ), $g( $x, 'loai' ) ); }
			self::write( self::DT, $rows );
		}
		if ( isset( $cfg['qr'] ) && is_array( $cfg['qr'] ) ) {
			$q = (array) $cfg['qr'];
			self::write( self::QR, array( array( 'stk', $g( $q, 'stk' ) ), array( 'bank', $g( $q, 'bank' ) ), array( 'ten', $g( $q, 'ten' ) ) ) );
		}
		if ( isset( $cfg['users'] ) && is_array( $cfg['users'] ) ) {
			// CHỈ ADMIN SỬA ĐƯỢC TÀI KHOẢN ADMIN. Kế toán / Quản lý vào Cấu hình làm mọi
			// việc khác, nhưng không đổi tên, PIN, vai trò của Admin, cũng không tự phong
			// mình làm Admin. Giữ nguyên các dòng Admin đang có, bỏ mọi dòng Admin gửi lên.
			if ( VHCP_Auth::vai_tro() !== '' && VHCP_Auth::vai_tro() !== 'Admin' ) {
				$admin_cu = array();
				foreach ( self::get_users() as $u0 ) {
					if ( (string) $u0['vaiTro'] === 'Admin' ) { $admin_cu[] = $u0; }
				}
				$con_lai = array();
				foreach ( $cfg['users'] as $x0 ) {
					$x0 = (array) $x0;
					if ( ( isset( $x0['vaiTro'] ) ? (string) $x0['vaiTro'] : '' ) === 'Admin' ) { continue; }
					$con_lai[] = $x0;
				}
				$cfg['users'] = array_merge( $admin_cu, $con_lai );
			}
			$rows = array();
			foreach ( $cfg['users'] as $x ) {
				$x = (array) $x;
				// PIN / TK Có / mã đối tượng là MÃ SỐ: bảng tính xuất ra "2222.0", "141.0".
				// Rửa ngay lúc lưu, đừng để PIN mang dấu chấm rồi không ai đăng nhập được.
				$rows[] = array(
					$g( $x, 'ten' ),
					VHCP_Util::pin_sach( $g( $x, 'pin' ) ),
					( $g( $x, 'vaiTro' ) !== '' ? $g( $x, 'vaiTro' ) : 'Nhân viên' ),
					$g( $x, 'coso' ),
					VHCP_Util::ma_so( $g( $x, 'tkCo' ) ),
					VHCP_Util::ma_so( $g( $x, 'maDt' ) ),
					$g( $x, 'boPhan' )
				);
			}
			self::write( self::USER, $rows );
		}
		if ( isset( $cfg['sso'] ) && is_array( $cfg['sso'] ) ) {
			$rows = array();
			foreach ( $cfg['sso'] as $x ) { $x = (array) $x; $rows[] = array( $g( $x, 'email' ), $g( $x, 'role' ), $g( $x, 'coso' ) ); }
			self::write( self::SSO, $rows );
		}
		self::clear_cache();
		return VHCP_Util::ok();
	}

	/** undoConfig(): trả bảng cấu hình về ngay trước lần lưu gần nhất (1 mức). */
	public static function undo_config() {
		$snap = VHCP_Meta::get_json( 'cfg_undo', null );
		if ( ! $snap || empty( $snap['name'] ) ) { return VHCP_Util::err( 'Chưa có lần lưu nào để hồi lại' ); }
		self::write( $snap['name'], isset( $snap['data'] ) ? $snap['data'] : array(), false );
		VHCP_Meta::del( 'cfg_undo' );
		self::clear_cache();
		return VHCP_Util::ok( array( 'name' => $snap['name'] ) );
	}

	// ---------------------------------------------------------------- phân quyền

	/** getQuyen(). */
	public static function get_quyen() {
		$hit = get_transient( 'vhcp_quyen' );
		if ( is_array( $hit ) ) { return $hit; }
		$roles = self::roles();
		$saved = array();
		foreach ( self::read( self::QUYEN ) as $r ) {
			$key = trim( (string) $r[0] );
			if ( $key === '' ) { continue; }
			$o = array();
			foreach ( $roles as $i => $role ) { $o[ $role ] = VHCP_Util::quyen_truthy( isset( $r[ 2 + $i ] ) ? $r[ 2 + $i ] : '' ); }
			$saved[ $key ] = $o;
		}
		$out = array();
		foreach ( self::actions() as $a ) {
			if ( isset( $saved[ $a['key'] ] ) ) { $out[ $a['key'] ] = $saved[ $a['key'] ]; continue; }
			$o = array();
			foreach ( $roles as $role ) { $o[ $role ] = ! empty( $a['def'][ $role ] ); }
			$out[ $a['key'] ] = $o;
		}
		set_transient( 'vhcp_quyen', $out, 300 );
		return $out;
	}

	public static function get_quyen_config() {
		$q   = self::get_quyen();
		$out = array();
		foreach ( self::actions() as $a ) {
			$out[] = array( 'key' => $a['key'], 'ten' => $a['ten'], 'perms' => $q[ $a['key'] ] );
		}
		return array( 'roles' => self::roles(), 'actions' => $out );
	}

	public static function set_quyen( $matrix ) {
		$matrix = (array) $matrix;
		$roles  = self::roles();
		$rows   = array();
		foreach ( self::actions() as $a ) {
			$m   = isset( $matrix[ $a['key'] ] ) ? (array) $matrix[ $a['key'] ] : array();
			$row = array( $a['key'], $a['ten'] );
			foreach ( $roles as $role ) { $row[] = ( ! empty( $m[ $role ] ) ) ? 1 : 0; }
			$rows[] = $row;
		}
		self::write( self::QUYEN, $rows, false );
		self::clear_cache();
		return VHCP_Util::ok();
	}

	// ---------------------------------------------------------------- người dùng

	/**
	 * Tra danh mục LOẠI CHI PHÍ theo tên -> mã tài khoản đã gắn.
	 * Đây là nơi duy nhất quyết định "chi phí này là chi phí gì": không còn dò
	 * ma trận nhóm × phân loại lớn nữa, nên số nào cũng dò được bằng mắt.
	 */
	public static function loai_map() {
		$s   = self::cfg_static();
		$out = array();
		foreach ( (array) ( isset( $s['loaiChiPhi'] ) ? $s['loaiChiPhi'] : array() ) as $x ) {
			$out[ mb_strtolower( trim( (string) $x['ten'] ) ) ] = $x;
		}
		return $out;
	}

	/** Mã tài khoản của 1 loại chi phí (rỗng nếu chưa khai). */
	public static function loai_tk( $ten ) {
		$m = self::loai_map();
		$k = mb_strtolower( trim( (string) $ten ) );
		if ( ! isset( $m[ $k ] ) ) { return array( 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'tenMisa' => '', 'loaiTt' => '' ); }
		$x = $m[ $k ];
		return array(
			'loaiTt'  => isset( $x['loaiTt'] ) ? (string) $x['loaiTt'] : '',
			'tkNo'    => (string) $x['tkNo'],
			'tkCo'    => (string) $x['tkCo'],
			'maDt'    => (string) $x['maDt'],
			'boPhan'  => isset( $x['boPhan'] ) ? (string) $x['boPhan'] : '',
			'tenMisa' => isset( $x['tenMisa'] ) ? (string) $x['tenMisa'] : '',
		);
	}

	/** Phân loại lớn (mảng kinh doanh) của 1 cơ sở / gian / địa điểm. */
	public static function pll_of( $coso ) {
		$s = self::cfg_static();
		$k = mb_strtolower( trim( (string) $coso ) );
		if ( $k === '' || ! isset( $s['cosoPll'][ $k ] ) ) { return ''; }
		return (string) $s['cosoPll'][ $k ];
	}

	/**
	 * TK Nợ theo MA TRẬN cho 1 loại chi phí tại 1 cơ sở. Dò từ hẹp ra rộng:
	 *   1) khai riêng cho ĐÚNG cơ sở đó   (trường hợp ngoại lệ)
	 *   2) khai cho MẢNG KINH DOANH của cơ sở (phân loại lớn) — dùng chung cho mọi cơ sở
	 *      cùng mảng, vì cùng loại chi phí mà khác mảng là khác mã ("Chi phí cơ sở":
	 *      EVENT 64196 · FARM 64166 · FZ 64126 · TUTU 64106)
	 * Trả '' khi chưa khai -> để bước sau lấy mã cố định của loại.
	 */
	public static function tkno_mx_list( $loai, $coso ) {
		$s  = self::cfg_static();
		$k  = mb_strtolower( trim( (string) $loai ) );
		if ( $k === '' || ! isset( $s['tkNoMx'][ $k ] ) ) { return array(); }
		$row = $s['tkNoMx'][ $k ];

		$c = mb_strtolower( trim( (string) $coso ) );
		if ( $c !== '' && isset( $row[ $c ] ) ) { return (array) $row[ $c ]; }

		$pll = self::pll_of( $coso );
		if ( $pll === '' ) { return array(); }
		$p = mb_strtolower( $pll );
		return isset( $row[ $p ] ) ? (array) $row[ $p ] : array();
	}

	/**
	 * Mã đang có trên dòng còn hợp lệ không? Trả lại chính nó nếu còn, ngược lại ''.
	 *
	 * Dùng khi áp lại mã cho dòng cũ: ô nào khai NHIỀU mã thì máy không chọn được hộ,
	 * nhưng mã người nhập đã chọn tay vẫn đúng — phải giữ, không được xóa thành trống.
	 */
	public static function ma_con_hop_le( $loai, $coso, $tk_hien_tai ) {
		$tk = trim( (string) $tk_hien_tai );
		if ( $tk === '' ) { return ''; }
		$ds = self::tkno_mx_list( $loai, $coso );
		if ( count( $ds ) && in_array( $tk, array_map( 'strval', $ds ), true ) ) { return $tk; }
		if ( ! count( $ds ) && self::loai_tk( $loai )['tkNo'] === $tk ) { return $tk; }
		return '';
	}

	/**
	 * Đúng 1 mã thì trả mã đó. Ô khai 2 mã trở lên thì trả '' — người nhập phải chỉ rõ
	 * mã nào (ô chọn loại chi phí tách sẵn từng mã), app không tự đoán hộ.
	 */
	public static function tkno_mx( $loai, $coso ) {
		$ds = self::tkno_mx_list( $loai, $coso );
		return ( count( $ds ) === 1 ) ? (string) $ds[0] : '';
	}

	/** Các cơ sở cùng mảng với cơ sở đã chọn (dùng để báo "mã này áp cho những cơ sở nào"). */
	public static function coso_cung_mang( $coso ) {
		$pll = self::pll_of( $coso );
		$out = array();
		foreach ( self::cfg_static()['coso'] as $x ) {
			if ( $pll === '' ) { continue; }
			if ( mb_strtolower( trim( (string) $x['phanLoaiLon'] ) ) === mb_strtolower( $pll ) ) { $out[] = (string) $x['ten']; }
		}
		if ( ! count( $out ) && trim( (string) $coso ) !== '' ) { $out[] = trim( (string) $coso ); }
		return $out;
	}

	/**
	 * KHAI NHANH: kế toán chọn cơ sở, gõ tên chi phí + số tài khoản là xong.
	 *
	 * Không phải khai trước bảng mảng, không phải mở ma trận: app tự lấy MẢNG KINH DOANH
	 * của cơ sở (cột "Phân loại lớn") rồi ghi mã vào đúng ô ma trận, nên mọi cơ sở cùng
	 * mảng dùng luôn mã đó. Cơ sở chưa khai phân loại lớn thì mã ghi riêng cho cơ sở đó.
	 * Muốn mã chỉ áp cho một cơ sở duy nhất thì đặt $rec['rieng'] = true.
	 *
	 * Dòng chi đã nhập trước đó KHÔNG đổi mã (đã chốt lúc nhập) — muốn áp lại thì bấm
	 * "🔗 Gán mã cho dòng cũ".
	 */
	public static function khai_cho_coso( $rec ) {
		$rec = (array) $rec;
		$g   = function ( $k ) use ( $rec ) { return isset( $rec[ $k ] ) ? trim( (string) $rec[ $k ] ) : ''; };
		$ten  = $g( 'ten' );
		$tkno = $g( 'tkNo' );
		if ( $ten === '' ) { return VHCP_Util::err( 'Nhập tên gọi chi phí' ); }
		if ( $tkno === '' ) { return VHCP_Util::err( 'Nhập số tài khoản (TK Nợ)' ); }

		// Cột ma trận sẽ ghi: từng CƠ SỞ được tích, và/hoặc cả MẢNG (áp luôn cho cơ sở mở sau)
		$lay = function ( $key ) use ( $rec ) {
			$out = array();
			foreach ( (array) ( isset( $rec[ $key ] ) ? $rec[ $key ] : array() ) as $v ) {
				$v = trim( (string) $v );
				if ( $v !== '' ) { $out[ $v ] = 1; }
			}
			return array_keys( $out );
		};
		$cosos = $lay( 'cosos' );
		$mangs = $lay( 'mangs' );
		if ( $g( 'coso' ) !== '' ) { $cosos[] = $g( 'coso' ); }   // tương thích lời gọi 1 cơ sở
		if ( ! count( $cosos ) && ! count( $mangs ) ) { return VHCP_Util::err( 'Tích ít nhất 1 cơ sở (hoặc 1 mảng) để áp mã' ); }

		$k = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };

		// Cơ sở nào đã nằm trong mảng được tích thì khỏi ghi riêng cho nó nữa
		$mang_set = array();
		foreach ( $mangs as $m ) { $mang_set[ $k( $m ) ] = 1; }
		$giu = array();
		foreach ( $cosos as $c ) {
			if ( isset( $mang_set[ $k( self::pll_of( $c ) ) ] ) ) { continue; }
			$giu[] = $c;
		}
		$cosos = $giu;

		// Số tài khoản này có trong hệ thống tài khoản không? Tên của nó là tên TK NỘI BỘ.
		$ten_tk = '';
		foreach ( self::tai_khoan() as $x ) {
			if ( (string) $x['ma'] === $tkno ) { $ten_tk = $x['ten']; break; }
		}

		// 1) danh mục loại chi phí: thêm nếu chưa có, điền ô trống, KHÔNG ghi đè
		$rows = array(); $vt = null;
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			if ( $k( $row[0] ) === $k( $ten ) ) { $vt = count( $rows ); }
			$rows[] = $row;
		}
		$loai_moi = false;
		$tenmisa  = $g( 'tenMisa' ) !== '' ? $g( 'tenMisa' ) : $ten_tk;
		if ( $vt === null ) {
			$rows[]   = array( $ten, '', $g( 'tkCo' ), $g( 'maDt' ), $g( 'boPhan' ), '', $tenmisa, $g( 'loaiTt' ) );
			$loai_moi = true;
		} else {
			// Tên MISA gõ tay thì ghi đè (đây là chỗ chỉnh nội dung xuất MISA), còn lại chỉ điền ô trống
			if ( $g( 'tenMisa' ) !== '' ) { $rows[ $vt ][6] = $g( 'tenMisa' ); }
			foreach ( array( 2 => $g( 'tkCo' ), 3 => $g( 'maDt' ), 4 => $g( 'boPhan' ), 6 => $tenmisa ) as $i => $v ) {
				if ( $v !== '' && trim( (string) $rows[ $vt ][ $i ] ) === '' ) { $rows[ $vt ][ $i ] = $v; }
			}
		}
		self::write( self::LOAI, $rows );

		// 2) ma trận: 1 ô cho mỗi cột được tích — khai rõ ràng nên ghi đè được
		$mx = array(); $vi_tri = array();
		foreach ( self::read( self::TKNO ) as $r ) {
			$n0 = trim( (string) $r[0] ); $p0 = trim( (string) $r[1] ); $v0 = trim( (string) $r[2] );
			if ( $n0 === '' || $p0 === '' ) { continue; }
			$vi_tri[ $k( $n0 ) . '|' . $k( $p0 ) ] = count( $mx );
			$mx[] = array( $n0, $p0, $v0 );
		}
		$them  = ! empty( $rec['them'] );   // thêm mã nữa cho ô đó (1 chi phí 2 mã), không thay mã cũ
		$ma_cu = array(); $o_moi = 0; $o_doi = 0; $o_them = 0;
		foreach ( array_merge( $mangs, $cosos ) as $cot ) {
			$key = $k( $ten ) . '|' . $k( $cot );
			if ( isset( $vi_tri[ $key ] ) ) {
				$cu = trim( (string) $mx[ $vi_tri[ $key ] ][2] );
				$ds = array();
				foreach ( explode( '|', $cu ) as $m ) { $m = trim( $m ); if ( $m !== '' ) { $ds[] = $m; } }
				if ( $them ) {
					if ( in_array( $tkno, $ds, true ) ) { continue; }   // ô đã có mã này
					$ds[] = $tkno;
					$mx[ $vi_tri[ $key ] ] = array( $ten, $cot, implode( ' | ', $ds ) );
					if ( count( $ds ) > 1 ) { $o_them++; } else { $o_moi++; }
					continue;
				}
				// Không tích "thêm": ô chỉ còn đúng mã vừa khai (kể cả ô đang có nhiều mã)
				if ( count( $ds ) === 1 && $ds[0] === $tkno ) { continue; }
				if ( $cu !== '' ) { $ma_cu[ $cu ] = 1; $o_doi++; }
				else { $o_moi++; }
				$mx[ $vi_tri[ $key ] ] = array( $ten, $cot, $tkno );
				continue;
			}
			$mx[] = array( $ten, $cot, $tkno );
			$vi_tri[ $key ] = count( $mx ) - 1;
			$o_moi++;
		}
		self::write( self::TKNO, $mx );
		self::clear_cache();

		// Danh sách cơ sở thật sự ăn mã này (gồm cơ sở thuộc các mảng được tích)
		$ap_dung = array();
		foreach ( self::cfg_static()['coso'] as $x ) {
			$ten_cs = (string) $x['ten'];
			if ( isset( $mang_set[ $k( $x['phanLoaiLon'] ) ] ) ) { $ap_dung[ $ten_cs ] = 1; continue; }
			foreach ( $cosos as $c ) { if ( $k( $c ) === $k( $ten_cs ) ) { $ap_dung[ $ten_cs ] = 1; } }
		}
		foreach ( $cosos as $c ) { if ( ! isset( $ap_dung[ $c ] ) ) { $ap_dung[ $c ] = 1; } }

		return VHCP_Util::ok( array(
			'loai'        => $ten,
			'loaiMoi'     => $loai_moi,
			'tkNo'        => $tkno,
			'tenTaiKhoan' => $ten_tk,
			'tenMisa'     => self::ten_misa_loai( $ten ),
			'laTkLa'      => ( $ten_tk === '' && count( self::tai_khoan() ) > 0 ),
			'cot'         => array_merge( $mangs, $cosos ),
			'oMoi'        => $o_moi,
			'oDoi'        => $o_doi,
			'oThem'       => $o_them,
			'maCu'        => array_map( 'strval', array_keys( $ma_cu ) ),
			'apDung'      => array_keys( $ap_dung ),
		) );
	}

	// ------------------------------------------------ hệ thống tài khoản của kế toán

	/** Hệ thống tài khoản đã nạp: [ ['ma'=>, 'ten'=>, 'tinhChat'=>], ... ] */
	public static function tai_khoan() {
		$out = array();
		foreach ( self::read( self::TK ) as $r ) {
			$ma = trim( (string) $r[0] );
			if ( $ma === '' ) { continue; }
			$out[] = array( 'ma' => $ma, 'ten' => trim( (string) $r[1] ), 'tinhChat' => trim( (string) $r[2] ) );
		}
		return $out;
	}

	/** Hệ thống tài khoản + bảng mảng, cho tab Cấu hình (gợi ý mã khi khai danh mục). */
	public static function get_tai_khoan() {
		return VHCP_Util::ok( array( 'taiKhoan' => self::tai_khoan(), 'mangTk' => self::mang_tk() ) );
	}

	/** Bảng mảng kinh doanh: phân loại lớn -> nhóm TK (VD 6412) + từ khóa trong tên TK (VD Funzone). */
	public static function mang_tk() {
		$out = array();
		foreach ( self::read( self::MANG ) as $r ) {
			$pll = trim( (string) $r[0] );
			if ( $pll === '' ) { continue; }
			$out[] = array( 'pll' => $pll, 'nhomTk' => trim( (string) $r[1] ), 'tuKhoa' => trim( (string) $r[2] ), 'note' => trim( (string) $r[3] ) );
		}
		return $out;
	}

	/** Bỏ dấu + hạ chữ + gom khoảng trắng, để so tên mảng với tên phân loại lớn. */
	public static function kd( $s ) {
		$s = mb_strtolower( trim( (string) $s ) );
		$map = array(
			'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
			'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
		);
		foreach ( $map as $plain => $accented ) {
			$chars = preg_split( '//u', $accented, -1, PREG_SPLIT_NO_EMPTY );
			$s     = str_replace( $chars, $plain, $s );
		}
		return trim( preg_replace( '/\s{2,}/u', ' ', $s ) );
	}

	/**
	 * DÒ BẢNG MẢNG KINH DOANH TỪ HỆ THỐNG TÀI KHOẢN — khỏi khai tay.
	 *
	 * Trong file kế toán, mỗi mảng là 1 tài khoản cha có tài khoản con bên dưới:
	 *   6412 Chi phí Funzone -> 64121 Chi phí lương Funzone · 64125 Chi phí setup Funzone…
	 * Nên bỏ chữ "Chi phí" khỏi tên tài khoản cha là ra TỪ KHÓA của mảng ("Funzone"),
	 * và số hiệu cha là NHÓM TK ("6412"). Phần app không tự biết được là mảng đó ứng với
	 * "Phân loại lớn" nào của cơ sở, nên chỗ nào ghép được theo tên thì điền sẵn, chỗ nào
	 * không thì để trống cho người khai chọn (không đoán bừa mã hạch toán).
	 *
	 * KHÔNG ghi vào cấu hình — chỉ trả đề xuất để xem rồi bấm Lưu.
	 */
	public static function do_mang_tu_tk( $goc = '641' ) {
		$chart = self::tai_khoan();
		if ( ! count( $chart ) ) { return VHCP_Util::err( 'Chưa nạp hệ thống tài khoản (wp-admin → Vận Hành Chi Phí → Nhập dữ liệu → CH_TaiKhoan)' ); }
		$goc = trim( (string) $goc );

		// Tài khoản cha = có ít nhất 1 tài khoản con trong hệ thống.
		// Giữ danh sách mã dạng LIST (không dùng làm khóa mảng): khóa mảng PHP tự đổi
		// "64121" thành số nguyên, so sánh với chuỗi sẽ luôn khác nhau -> dòng con tự
		// nhận là cha của chính nó.
		$ma_list = array();
		foreach ( $chart as $x ) { $ma_list[] = (string) $x['ma']; }
		$cha = array();
		foreach ( $chart as $x ) {
			$ma = (string) $x['ma'];
			if ( $goc !== '' && strpos( $ma, $goc ) !== 0 ) { continue; }
			if ( $ma === $goc ) { continue; }
			$co_con = false;
			foreach ( $ma_list as $m2 ) {
				if ( $m2 !== $ma && strpos( $m2, $ma ) === 0 ) { $co_con = true; break; }
			}
			if ( ! $co_con ) { continue; }
			$tu = trim( preg_replace( '/^\s*chi\s*ph[íi]\s*/iu', '', $x['ten'] ) );
			if ( $tu === '' ) { continue; }
			$cha[] = array( 'nhomTk' => $ma, 'tuKhoa' => $tu, 'tenTk' => $x['ten'] );
		}
		if ( ! count( $cha ) ) { return VHCP_Util::err( 'Không thấy nhóm tài khoản nào dưới ' . $goc . ' có tài khoản con' ); }

		// Danh sách phân loại lớn đang khai ở bảng Cơ sở
		$plls = array();
		foreach ( self::read( self::COSO ) as $r ) {
			$v = trim( (string) $r[2] );
			if ( $v !== '' ) { $plls[ $v ] = 1; }
		}
		$plls = array_keys( $plls );

		// Đã khai rồi thì không đề xuất lại
		$da_co = array();
		foreach ( self::mang_tk() as $m ) { $da_co[ self::kd( $m['pll'] ) . '|' . $m['nhomTk'] ] = 1; }

		$rows = array(); $chua_ghep = array();
		foreach ( $cha as $c ) {
			$tu_kd = self::kd( $c['tuKhoa'] );
			$hit   = array();
			foreach ( $plls as $pll ) {
				$p = self::kd( $pll );
				// khớp khi tên phân loại lớn có chứa từ khóa mảng (hoặc ngược lại)
				if ( $tu_kd !== '' && ( strpos( $p, $tu_kd ) !== false || strpos( $tu_kd, $p ) !== false ) ) { $hit[] = $pll; }
			}
			if ( ! count( $hit ) ) {
				$chua_ghep[] = $c['nhomTk'] . ' · ' . $c['tuKhoa'];
				$rows[] = array( 'pll' => '', 'nhomTk' => $c['nhomTk'], 'tuKhoa' => $c['tuKhoa'], 'note' => 'chọn mảng cho ' . $c['tenTk'] );
				continue;
			}
			foreach ( $hit as $pll ) {
				if ( isset( $da_co[ self::kd( $pll ) . '|' . $c['nhomTk'] ] ) ) { continue; }
				$rows[] = array( 'pll' => $pll, 'nhomTk' => $c['nhomTk'], 'tuKhoa' => $c['tuKhoa'], 'note' => $c['tenTk'] );
			}
		}

		return VHCP_Util::ok( array(
			'rows'      => $rows,
			'chuaGhep'  => $chua_ghep,
			'soNhom'    => count( $cha ),
			'soPll'     => count( $plls ),
		) );
	}

	/**
	 * GHÉP HỆ THỐNG TÀI KHOẢN VÀO DANH MỤC LOẠI CHI PHÍ.
	 *
	 * Tài khoản của kế toán đặt tên theo kiểu "Chi phí <hạng mục> <mảng>"
	 * (VD 64121 Chi phí lương Funzone · 64161 Chi phí lương Farm), nên:
	 *   - Bỏ từ khóa mảng khỏi tên -> ra TÊN LOẠI CHI PHÍ dùng chung ("Chi phí lương").
	 *   - Số hiệu tài khoản của từng mảng -> 1 ô trong MA TRẬN [loại] × [phân loại lớn].
	 * Tài khoản KHÔNG thuộc nhóm mảng nào (6423 đồ dùng văn phòng, 6427 dịch vụ mua ngoài,
	 * 811 chi phí khác…) thì tên giữ nguyên và TK Nợ là mã cố định, mảng nào cũng dùng chung.
	 *
	 * Chỉ THÊM và ĐIỀN Ô TRỐNG: loại chi phí anh tự thêm và mã anh đã sửa tay không bị đụng.
	 *
	 * @param array $opts ['dungChung' => array các số hiệu TK dùng chung cần thêm]
	 */
	public static function ghep_he_thong_tk( $opts = array() ) {
		$opts = (array) $opts;
		$chart = self::tai_khoan();
		$mang  = self::mang_tk();
		if ( ! count( $chart ) ) { return VHCP_Util::err( 'Chưa nạp hệ thống tài khoản (⚙️ Cấu hình → nạp CSV → CH_TaiKhoan)' ); }
		if ( ! count( $mang ) ) { return VHCP_Util::err( 'Chưa khai bảng "Mảng kinh doanh → nhóm TK" nên chưa biết tài khoản nào thuộc mảng nào' ); }

		$k = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };

		// 1) Từ hệ thống TK + bảng mảng -> các ô ma trận cần có.
		$mx_new    = array();   // [loại chi phí] [phân loại lớn] = số hiệu
		$ten_cua   = array();   // khóa hạ chữ -> tên loại chi phí hiển thị
		$bo_qua_tk = 0;
		foreach ( $mang as $m ) {
			$nhom  = $m['nhomTk'];
			$tu    = $m['tuKhoa'];
			if ( $nhom === '' || $tu === '' ) { continue; }
			foreach ( $chart as $tk ) {
				if ( $tk['ma'] === $nhom || strpos( $tk['ma'], $nhom ) !== 0 ) { continue; }   // chỉ tài khoản con
				$ten = $tk['ten'];
				// bỏ từ khóa mảng ở bất kỳ đâu trong tên, rồi dọn dấu và khoảng trắng dư
				$sach = preg_replace( '/\s*' . preg_quote( $tu, '/' ) . '\s*/iu', ' ', $ten );
				$sach = trim( preg_replace( '/\s{2,}/u', ' ', (string) $sach ) );
				$sach = trim( $sach, " -–—_" );
				if ( $sach === '' || $k( $sach ) === $k( $ten ) ) { $bo_qua_tk++; continue; }   // không nhận ra mảng trong tên
				$kk = $k( $sach );
				if ( ! isset( $ten_cua[ $kk ] ) ) { $ten_cua[ $kk ] = $sach; }
				$mx_new[ $kk ][ $k( $m['pll'] ) ] = array( 'pll' => $m['pll'], 'ma' => $tk['ma'] );
			}
		}

		// 2) Các tài khoản dùng chung (không theo mảng) -> loại chi phí có mã cố định.
		$chung = array();
		$want  = array();
		foreach ( (array) ( isset( $opts['dungChung'] ) ? $opts['dungChung'] : array() ) as $x ) {
			$x = trim( (string) $x );
			if ( $x !== '' ) { $want[ $x ] = 1; }
		}
		foreach ( $chart as $tk ) {
			if ( ! isset( $want[ $tk['ma'] ] ) ) { continue; }
			if ( $tk['ten'] === '' ) { continue; }
			$chung[ $k( $tk['ten'] ) ] = array( 'ten' => $tk['ten'], 'ma' => $tk['ma'] );
		}

		// 3) Bổ sung danh mục loại chi phí (giữ nguyên dòng đã có).
		$rows = array(); $co = array();
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			$co[ $k( $row[0] ) ] = count( $rows );
			$rows[] = $row;
		}
		$them = 0; $sua = 0;
		foreach ( $ten_cua as $kk => $ten ) {
			if ( isset( $co[ $kk ] ) ) { continue; }
			$rows[] = array( $ten, '', '', '', '', '', '', '' );   // TK Nợ trống: lấy theo ma trận
			$co[ $kk ] = count( $rows ) - 1;
			$them++;
		}
		foreach ( $chung as $kk => $x ) {
			if ( isset( $co[ $kk ] ) ) {
				$i = $co[ $kk ];
				if ( trim( (string) $rows[ $i ][1] ) === '' ) { $rows[ $i ][1] = $x['ma']; $sua++; }
				continue;
			}
			$rows[] = array( $x['ten'], $x['ma'], '', '', '', '', '', '' );
			$co[ $kk ] = count( $rows ) - 1;
			$them++;
		}
		if ( $them || $sua ) { self::write( self::LOAI, $rows ); }

		// 4) Bổ sung ma trận (không ghi đè ô đã có mã).
		$cu = array(); $mx_rows = array();
		foreach ( self::read( self::TKNO ) as $r ) {
			$n0 = trim( (string) $r[0] ); $p0 = trim( (string) $r[1] ); $v0 = trim( (string) $r[2] );
			if ( $n0 === '' || $p0 === '' ) { continue; }
			$cu[ $k( $n0 ) . '|' . $k( $p0 ) ] = count( $mx_rows );
			$mx_rows[] = array( $n0, $p0, $v0 );
		}
		$o_them = 0;
		foreach ( $mx_new as $kk => $per_pll ) {
			$ten = $ten_cua[ $kk ];
			foreach ( $per_pll as $kp => $x ) {
				$key = $kk . '|' . $kp;
				if ( isset( $cu[ $key ] ) ) {
					if ( trim( (string) $mx_rows[ $cu[ $key ] ][2] ) !== '' ) { continue; }   // đã khai tay -> giữ
					$mx_rows[ $cu[ $key ] ][2] = $x['ma'];
					$o_them++;
					continue;
				}
				$mx_rows[] = array( $ten, $x['pll'], $x['ma'] );
				$cu[ $key ] = count( $mx_rows ) - 1;
				$o_them++;
			}
		}
		if ( $o_them ) { self::write( self::TKNO, $mx_rows ); }
		self::clear_cache();

		return VHCP_Util::ok( array(
			'themLoai'    => $them,
			'suaLoai'     => $sua,
			'oMaTran'     => $o_them,
			'tongLoai'    => count( $rows ),
			'boQuaTaiKhoan' => $bo_qua_tk,
		) );
	}

	/**
	 * THÊM LOẠI CHI PHÍ CÒN THIẾU VÀO DANH MỤC (chỉ tên, chưa có mã).
	 *
	 * Nạp dữ liệu cũ sinh ra tên loại chi phí lấy từ chính bảng tính ("Nhân Công",
	 * "Vật tư Khánh Thảo"…). Nếu không đưa vào danh mục thì chúng KHÔNG hiện ở bảng ma
	 * trận lẫn ô chọn lúc nhập — nghĩa là không có cách nào khai mã cho chúng, và nút
	 * "Gán mã cho dòng cũ" cũng chẳng có gì để dò. Vào danh mục rồi thì khai mã như
	 * bình thường.
	 *
	 * @return int số loại vừa thêm
	 */
	public static function them_loai_neu_thieu( $tens ) {
		$k  = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };
		$co = array();
		$rows = array();
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			if ( trim( (string) $row[0] ) === '' ) { continue; }
			$co[ $k( $row[0] ) ] = 1;
			$rows[] = $row;
		}
		$them = 0;
		foreach ( (array) $tens as $t ) {
			$t = trim( (string) $t );
			if ( $t === '' || isset( $co[ $k( $t ) ] ) ) { continue; }
			$co[ $k( $t ) ] = 1;
			$rows[] = array( $t, '', '', '', '', '(nạp từ dữ liệu cũ)', '', '' );
			$them++;
		}
		if ( $them ) {
			self::write( self::LOAI, $rows );
			self::clear_cache();
		}
		return $them;
	}

	/** Loại này đã được khai mã ở BẤT KỲ mảng nào trong ma trận chưa? */
	private static function loai_co_ma_trong_mx( $loai ) {
		$s = self::cfg_static();
		$k = mb_strtolower( trim( (string) $loai ) );
		if ( $k === '' || ! isset( $s['tkNoMx'][ $k ] ) ) { return false; }
		foreach ( (array) $s['tkNoMx'][ $k ] as $ds ) {
			foreach ( (array) $ds as $ma ) { if ( trim( (string) $ma ) !== '' ) { return true; } }
		}
		return false;
	}

	/**
	 * DỌN CÁC LOẠI CHI PHÍ CHƯA KHAI MÃ.
	 *
	 * Nạp dữ liệu cũ thì mỗi tên hạng mục lạ đều được thêm vào danh mục để còn khai mã
	 * được cho nó. Phần lớn không phải loại chi phí ("Nguyễn Hữu Thọ, Nguyễn Bá Tuấn",
	 * "Cấp Mạng VNPT") nên danh mục phình ra vài trăm dòng rác.
	 *
	 * Chỉ xóa dòng chưa có mã nào (cả mã cố định lẫn mã trong ma trận). Đã khai mã nghĩa
	 * là kế toán đã nhận nó là loại thật — giữ lại. Dòng chi phí cũ không bị ảnh hưởng:
	 * tên loại vẫn nằm trên từng dòng, xóa danh mục chỉ là dọn ô chọn.
	 *
	 * @return array [ 'xoa' => số dòng đã xóa, 'giu' => số dòng giữ lại, 'ten' => tên đã xóa ]
	 */
	public static function xoa_loai_tu_tao() {
		$rows = array(); $xoa = array(); $giu = 0;
		foreach ( self::read( self::LOAI ) as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			$ten = trim( (string) $row[0] );
			if ( $ten === '' ) { continue; }
			// Mốc là CHƯA CÓ MÃ, không dựa vào ghi chú "(nạp từ dữ liệu cũ)": bảng ma trận
			// trên giao diện không có cột Ghi chú nên lưu bảng đó một lần là dấu đó bay hết.
			// Loại chưa có mã ở đâu cả thì chọn cũng ra dòng không có TK Nợ — chưa dùng được.
			$co_ma = ( trim( (string) $row[1] ) !== '' ) || self::loai_co_ma_trong_mx( $ten );
			if ( ! $co_ma ) { $xoa[] = $ten; continue; }
			$rows[] = $row;
			$giu++;
		}
		if ( count( $xoa ) ) {
			self::write( self::LOAI, $rows );
			self::clear_cache();
		}
		return array( 'xoa' => count( $xoa ), 'giu' => $giu, 'ten' => array_slice( $xoa, 0, 40 ) );
	}

	/** Tên dùng cho diễn giải MISA của 1 loại chi phí (để trống = dùng chính tên loại). */
	public static function ten_misa_loai( $loai ) {
		$t = self::loai_tk( $loai );
		return $t['tenMisa'] !== '' ? $t['tenMisa'] : trim( (string) $loai );
	}

	/**
	 * CHỐT MÃ TÀI KHOẢN cho 1 dòng chi ở BẤT KỲ mảng nào (sổ chi phí, đơn vận hành,
	 * kỹ thuật, marketing, công tác/setup).
	 *
	 * TK Nợ theo thứ tự ưu tiên:
	 *   1) mã người nhập gõ tay trên dòng ($override)
	 *   2) MA TRẬN [loại chi phí] × [phân loại lớn của cơ sở] — cùng loại chi phí mà
	 *      khác mảng kinh doanh thì khác mã, nên đây là mã sát nhất
	 *   3) mã cố định khai ở danh mục LOẠI CHI PHÍ (dùng cho loại mảng nào cũng 1 mã)
	 *   4) để trống + báo thiếu (KHÔNG đoán, để không âm thầm hạch toán sai)
	 *
	 * TK Có / Mã đối tượng: gõ tay -> danh mục -> mặc định theo hình thức chi
	 * ("Trực tiếp…" -> 331 · còn lại -> 141).
	 */
	public static function resolve_tk( $loai, $hinh_thuc = '', $override = array(), $coso = '' ) {
		$override = (array) $override;
		$ov = function ( $k ) use ( $override ) { return isset( $override[ $k ] ) ? trim( (string) $override[ $k ] ) : ''; };
		$cat = self::loai_tk( $loai );

		$tk_no = $ov( 'tkNo' );
		if ( $tk_no === '' && trim( (string) $loai ) !== '' ) { $tk_no = self::tkno_mx( $loai, $coso ); }
		if ( $tk_no === '' ) { $tk_no = $cat['tkNo']; }
		$tk_co = $ov( 'tkCo' ) !== '' ? $ov( 'tkCo' ) : $cat['tkCo'];
		$ma_dt = $ov( 'maDt' ) !== '' ? $ov( 'maDt' ) : $cat['maDt'];

		if ( $tk_co === '' ) {
			$is_tt = ( mb_strpos( trim( (string) $hinh_thuc ), 'Trực tiếp' ) === 0 );
			$pl    = $is_tt ? 'Nhà cung cấp' : 'Thanh toán cá nhân';
			$s     = self::cfg_static();
			foreach ( (array) $s['phanloai'] as $x ) {
				if ( trim( (string) $x['ten'] ) === $pl ) { $tk_co = (string) $x['tkCo']; break; }
			}
			if ( $tk_co === '' ) { $tk_co = $is_tt ? '331' : '141'; }
		}
		return array( 'tk_no' => $tk_no, 'tk_co' => $tk_co, 'ma_dt' => $ma_dt );
	}

	/**
	 * LẤY MÃ SẴN CÓ CHO DANH MỤC LOẠI CHI PHÍ.
	 *
	 * Cấu hình cũ đã khai TK Nợ ở 2 chỗ: cột "TK Nợ" của CH_Nhom và ma trận CH_TKNo
	 * (nhóm × phân loại lớn). Hàm này copy các mã đó sang danh mục LOẠI CHI PHÍ để
	 * anh không phải gõ lại 13 dòng — chỉ điền vào ô ĐANG TRỐNG, không ghi đè mã đã khai.
	 * Ma trận chỉ dùng được khi mọi phân loại lớn của nhóm đó cùng 1 mã (khác nhau thì
	 * để trống và báo thiếu, để không âm thầm hạch toán sai).
	 */
	public static function dong_bo_tk_loai() {
		$all  = self::read_all();
		$loai = self::rows_of( $all, self::LOAI );
		if ( ! count( $loai ) ) { return VHCP_Util::ok( array( 'updated' => 0, 'thieuMa' => 0, 'tong' => 0 ) ); }

		$k = function ( $v ) { return mb_strtolower( trim( (string) $v ) ); };

		$tk = array();   // tên nhóm -> TK Nợ
		$bp = array();   // tên nhóm -> Bộ phận
		foreach ( self::rows_of( $all, self::NHOM ) as $r ) {
			$key = $k( $r[0] );
			if ( $key === '' ) { continue; }
			if ( ! isset( $tk[ $key ] ) && trim( (string) $r[2] ) !== '' ) { $tk[ $key ] = trim( (string) $r[2] ); }
			if ( ! isset( $bp[ $key ] ) && trim( (string) $r[3] ) !== '' ) { $bp[ $key ] = trim( (string) $r[3] ); }
		}

		// Ma trận: chỉ hạ thành "mã cố định" khi loại đó khai ĐỦ mọi phân loại lớn và
		// cùng một mã. Ô trống trong ma trận nghĩa là "mảng đó không dùng loại này",
		// hạ xuống mã cố định sẽ biến ô trống thành hạch toán sai.
		$so_pll = array();
		foreach ( self::rows_of( $all, self::COSO ) as $r ) {
			$v = trim( (string) $r[2] );
			if ( $v !== '' ) { $so_pll[ mb_strtolower( $v ) ] = 1; }
		}
		$so_pll = count( $so_pll );

		$mt = array();   // tên loại -> [ mã => số ô ]
		foreach ( self::rows_of( $all, self::TKNO ) as $r ) {
			$key = $k( $r[0] );
			$v   = trim( (string) $r[2] );
			if ( $key === '' || $v === '' ) { continue; }
			if ( ! isset( $mt[ $key ] ) ) { $mt[ $key ] = array(); }
			if ( ! isset( $mt[ $key ][ $v ] ) ) { $mt[ $key ][ $v ] = 0; }
			$mt[ $key ][ $v ]++;
		}

		$rows = array(); $upd = 0; $thieu = 0; $changed = false;
		foreach ( $loai as $r ) {
			$row = array_slice( array_values( (array) $r ), 0, 8 );
			for ( $i = count( $row ); $i < 8; $i++ ) { $row[ $i ] = ''; }
			$key = $k( $row[0] );

			if ( trim( (string) $row[1] ) === '' ) {
				$v = '';
				if ( isset( $tk[ $key ] ) ) { $v = $tk[ $key ]; }
				elseif ( isset( $mt[ $key ] ) && count( $mt[ $key ] ) === 1 && $so_pll > 0 ) {
					$ma = array_map( 'strval', array_keys( $mt[ $key ] ) );
					if ( (int) $mt[ $key ][ $ma[0] ] >= $so_pll ) { $v = $ma[0]; }
				}
				if ( $v !== '' ) { $row[1] = $v; $upd++; $changed = true; }
			}
			if ( trim( (string) $row[4] ) === '' && isset( $bp[ $key ] ) ) { $row[4] = $bp[ $key ]; $changed = true; }
			if ( trim( (string) $row[1] ) === '' ) { $thieu++; }
			$rows[] = $row;
		}

		if ( $changed ) { self::write( self::LOAI, $rows ); }
		else { self::clear_cache(); }
		return VHCP_Util::ok( array( 'updated' => $upd, 'thieuMa' => $thieu, 'tong' => count( $rows ) ) );
	}

	public static function get_users() {
		$s = self::cfg_static();   // đã gồm bảng người dùng, có cache 5 phút
		return isset( $s['users'] ) ? $s['users'] : array();
	}
}
