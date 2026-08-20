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

	public static function clear_cache() {
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
		return $did;
	}

	// ---------------------------------------------------------------- cấu hình tĩnh

	/** Bản dịch của _cfgStatic() (cache 5 phút như CacheService). */
	public static function cfg_static() {
		$hit = get_transient( 'vhcp_cfgstatic' );
		if ( is_array( $hit ) ) { return $hit; }

		$all = self::read_all();
		if ( self::seed_from( $all ) ) { $all = self::read_all(); }   // chỉ đọc lại khi thực sự có seed

		$out = array( 'coso' => array(), 'nhom' => array(), 'tkNoMatrix' => array(), 'phanloai' => array(), 'dtCfg' => array(), 'qr' => array( 'stk' => '', 'bank' => '', 'ten' => '' ) );

		foreach ( self::rows_of( $all, self::COSO ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['coso'][] = array( 'ten' => $r[0], 'maDonVi' => $r[1], 'phanLoaiLon' => $r[2], 'tenMisa' => $r[3] );
		}
		foreach ( self::rows_of( $all, self::NHOM ) as $r ) {
			if ( trim( (string) $r[0] ) === '' ) { continue; }
			$out['nhom'][] = array( 'ten' => $r[0], 'loai' => ( $r[1] !== '' ? $r[1] : 'canhan' ), 'tkNo' => $r[2], 'boPhan' => $r[3] );
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
			$out['users'][] = array( 'ten' => $r[0], 'pin' => (string) $r[1], 'vaiTro' => ( $r[2] !== '' ? $r[2] : 'Nhân viên' ), 'coso' => $r[3], 'tkCo' => $r[4], 'maDt' => $r[5], 'boPhan' => $r[6] );
		}

		set_transient( 'vhcp_cfgstatic', $out, 300 );
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
			$rows = array();
			foreach ( $cfg['users'] as $x ) {
				$x = (array) $x;
				$rows[] = array( $g( $x, 'ten' ), $g( $x, 'pin' ), ( $g( $x, 'vaiTro' ) !== '' ? $g( $x, 'vaiTro' ) : 'Nhân viên' ), $g( $x, 'coso' ), $g( $x, 'tkCo' ), $g( $x, 'maDt' ), $g( $x, 'boPhan' ) );
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

	public static function get_users() {
		$s = self::cfg_static();   // đã gồm bảng người dùng, có cache 5 phút
		return isset( $s['users'] ) ? $s['users'] : array();
	}
}
