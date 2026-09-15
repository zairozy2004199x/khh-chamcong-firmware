<?php
/**
 * KHO DỮ LIỆU — khoản chi phí, cấu hình theo kỳ, người dùng, tệp đính kèm, nhật ký.
 * Cùng hợp đồng dữ liệu với bản Google Apps Script (backend/apps-script/Code.gs) để giao diện
 * web dùng chung một app.js.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_Store {

	const STATUSES = array( 'cho_duyet', 'da_duyet', 'tu_choi' );
	const MAX_FILE = 8 * 1024 * 1024; // 8MB

	// ---------------------------------------------------------------- nhật ký

	public static function log( $hanh_dong, $chi_tiet = '' ) {
		self::log_as( KHBC_Auth::ten(), KHBC_Auth::vai(), $hanh_dong, $chi_tiet );
	}
	public static function log_as( $nguoi, $vai, $hanh_dong, $chi_tiet = '' ) {
		global $wpdb;
		$wpdb->insert( KHBC_DB::t( 'nhat_ky' ), array(
			'luc'       => KHBC_Util::now_sql(),
			'nguoi'     => KHBC_Util::s( $nguoi, 120 ),
			'vai'       => KHBC_Util::s( $vai, 40 ),
			'hanh_dong' => KHBC_Util::s( $hanh_dong, 60 ),
			'chi_tiet'  => KHBC_Util::s( $chi_tiet, 2000 ),
		) );
	}
	public static function get_log( $opts = array() ) {
		global $wpdb;
		$opts  = (array) $opts;
		$limit = isset( $opts['limit'] ) ? max( 1, min( 500, (int) $opts['limit'] ) ) : 200;
		$t     = KHBC_DB::t( 'nhat_ky' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		$out   = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( 'id' => (int) $r['id'], 'luc' => KHBC_Util::iso( $r['luc'] ), 'nguoi' => $r['nguoi'], 'vai' => $r['vai'], 'hanhDong' => $r['hanh_dong'], 'chiTiet' => $r['chi_tiet'] );
		}
		return KHBC_Util::ok( array( 'items' => $out ) );
	}

	// ---------------------------------------------------------------- khoản chi phí

	private static function row_to_item( $r ) {
		return array(
			'id'          => $r['id'],
			'period'      => $r['ky'],
			'status'      => $r['trang_thai'] ? $r['trang_thai'] : 'cho_duyet',
			'createdAt'   => KHBC_Util::iso( $r['tao_luc'] ),
			'createdBy'   => $r['nguoi_tao'],
			'updatedAt'   => KHBC_Util::iso( $r['sua_luc'] ),
			'updatedBy'   => $r['nguoi_sua'],
			'kind'        => $r['loai'] === 'personal' ? 'personal' : 'company',
			'name'        => (string) $r['ten'],
			'misaGeneral' => (string) $r['misa_chung'],
			'misaDetail'  => (string) $r['misa_chi_tiet'],
			'account'     => $r['tai_khoan'],
			'objectCode'  => $r['ma_doi_tuong'],
			'total'       => (float) $r['tong'],
			'split'       => $r['kieu_chia'] ? $r['kieu_chia'] : 'equal',
			'shares'      => (object) KHBC_Util::json_decode_arr( $r['chia_nhom'], array() ),
			'excludeDepts' => array_values( KHBC_Util::json_decode_arr( $r['loai_tru'], array() ) ),
			'groupKey'    => $r['gop_cot'],
			'method'      => $r['phuong_phap'],
			'note'        => (string) $r['ghi_chu'],
			'attachments' => array_values( KHBC_Util::json_decode_arr( $r['tep'], array() ) ),
			'approvedBy'  => $r['nguoi_duyet'],
			'approvedAt'  => KHBC_Util::iso( $r['duyet_luc'] ),
			'sortOrder'   => (int) $r['thu_tu'],
		);
	}

	public static function read_costs( $ky ) {
		global $wpdb;
		$t    = KHBC_DB::t( 'khoan' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE ky=%s AND da_xoa=0 ORDER BY thu_tu ASC, tao_luc ASC", $ky ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) { $out[] = self::row_to_item( $r ); }
		return $out;
	}

	private static function cost_row( $id ) {
		global $wpdb;
		$t = KHBC_DB::t( 'khoan' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%s", (string) $id ), ARRAY_A );
	}

	private static function sanitize_item( $raw ) {
		$raw = (array) $raw;
		$g   = function ( $k, $max = 500 ) use ( $raw ) { return isset( $raw[ $k ] ) ? KHBC_Util::s( $raw[ $k ], $max ) : ''; };
		$shares = isset( $raw['shares'] ) && ( is_array( $raw['shares'] ) || is_object( $raw['shares'] ) ) ? (array) $raw['shares'] : array();
		$sh = array();
		foreach ( $shares as $k => $v ) { $sh[ KHBC_Util::s( $k, 40 ) ] = KHBC_Util::num( $v ); }
		$ex = isset( $raw['excludeDepts'] ) && is_array( $raw['excludeDepts'] ) ? array_values( array_map( function ( $x ) { return KHBC_Util::s( $x, 60 ); }, $raw['excludeDepts'] ) ) : array();
		$tep = isset( $raw['attachments'] ) && is_array( $raw['attachments'] ) ? array_values( array_filter( array_map( function ( $x ) { return esc_url_raw( (string) $x ); }, $raw['attachments'] ) ) ) : array();
		$out = array(
			'loai'          => ( isset( $raw['kind'] ) && $raw['kind'] === 'personal' ) ? 'personal' : 'company',
			'ten'           => $g( 'name', 500 ),
			'misa_chung'    => $g( 'misaGeneral', 500 ),
			'misa_chi_tiet' => $g( 'misaDetail', 500 ),
			'tai_khoan'     => $g( 'account', 60 ),
			'ma_doi_tuong'  => $g( 'objectCode', 60 ),
			'tong'          => isset( $raw['total'] ) ? KHBC_Util::num( $raw['total'] ) : 0,
			'kieu_chia'     => $g( 'split', 40 ) !== '' ? $g( 'split', 40 ) : 'equal',
			'chia_nhom'     => wp_json_encode( $sh ),
			'loai_tru'      => wp_json_encode( $ex ),
			'gop_cot'       => $g( 'groupKey', 60 ),
			'phuong_phap'   => $g( 'method', 20 ),
			'ghi_chu'       => $g( 'note', 2000 ),
			'tep'           => wp_json_encode( array_slice( $tep, 0, 10 ) ),
		);
		if ( isset( $raw['sortOrder'] ) ) { $out['thu_tu'] = (int) $raw['sortOrder']; }
		return $out;
	}

	public static function period_locked( $ky ) {
		global $wpdb;
		$t = KHBC_DB::t( 'ky' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT khoa FROM $t WHERE ky=%s", $ky ) ) === 1;
	}

	public static function upsert_cost( $item ) { return self::upsert_costs( array( $item ) ); }

	public static function upsert_costs( $items ) {
		global $wpdb;
		if ( ! is_array( $items ) || ! $items ) { return KHBC_Util::err( 'Không có khoản nào' ); }
		$t     = KHBC_DB::t( 'khoan' );
		$now   = KHBC_Util::now_sql();
		$user  = KHBC_Auth::ten();
		$role  = KHBC_Auth::vai_app();
		$saved = array();
		$denied = array();
		$locked = array();
		foreach ( $items as $raw ) {
			$raw = (array) $raw;
			$id  = isset( $raw['id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $raw['id'] ) : '';
			$ky  = isset( $raw['period'] ) ? KHBC_Util::ky( $raw['period'] ) : '';
			if ( $id === '' || $ky === '' ) { continue; }
			if ( ! KHBC_Auth::la_admin() && self::period_locked( $ky ) ) { $locked[] = $id; continue; }
			$cur = self::cost_row( $id );
			if ( $role === 'nhap' && $cur && ( $cur['nguoi_tao'] !== $user || $cur['trang_thai'] !== 'cho_duyet' ) ) { $denied[] = $id; continue; }
			$data = self::sanitize_item( $raw );
			$data['ky']       = $ky;
			$data['da_xoa']   = 0;
			$data['sua_luc']  = $now;
			$data['nguoi_sua'] = $user;
			$st = isset( $raw['status'] ) ? (string) $raw['status'] : '';
			if ( ! $cur ) {
				$data['id']        = $id;
				$data['tao_luc']   = $now;
				$data['nguoi_tao'] = $user;
				$data['trang_thai'] = $role === 'ketoan' ? ( in_array( $st, self::STATUSES, true ) ? $st : 'da_duyet' ) : 'cho_duyet';
				if ( empty( $data['thu_tu'] ) ) { $data['thu_tu'] = (int) round( microtime( true ) * 1000 ); }
				if ( $data['trang_thai'] !== 'cho_duyet' ) { $data['nguoi_duyet'] = $user; $data['duyet_luc'] = $now; }
				$wpdb->insert( $t, $data );
			} else {
				if ( $role === 'ketoan' && in_array( $st, self::STATUSES, true ) && $st !== $cur['trang_thai'] ) {
					$data['trang_thai']  = $st;
					$data['nguoi_duyet'] = $user;
					$data['duyet_luc']   = $now;
				}
				$wpdb->update( $t, $data, array( 'id' => $id ) );
			}
			$saved[] = $id;
		}
		$names = array();
		foreach ( $items as $i ) { if ( is_array( $i ) && isset( $i['name'] ) ) { $names[] = (string) $i['name']; } elseif ( is_object( $i ) && isset( $i->name ) ) { $names[] = (string) $i->name; } }
		self::log( 'upsertCosts', count( $saved ) . ' khoản: ' . mb_substr( implode( ', ', $names ), 0, 300 ) );
		$out = KHBC_Util::ok( array( 'saved' => $saved, 'denied' => $denied ) );
		if ( $locked ) { $out['locked'] = $locked; $out['warning'] = 'Kỳ đã chốt — ' . count( $locked ) . ' khoản không được ghi.'; }
		return $out;
	}

	public static function delete_cost( $id ) {
		global $wpdb;
		$id  = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
		$cur = self::cost_row( $id );
		if ( ! $cur ) { return KHBC_Util::ok( array( 'deleted' => false ) ); }
		if ( ! KHBC_Auth::la_admin() && self::period_locked( $cur['ky'] ) ) { return KHBC_Util::err( 'Kỳ đã chốt, không xoá được.' ); }
		if ( KHBC_Auth::vai_app() === 'nhap' && ( $cur['nguoi_tao'] !== KHBC_Auth::ten() || $cur['trang_thai'] !== 'cho_duyet' ) ) {
			return KHBC_Util::err( 'Chỉ xoá được khoản của mình khi còn chờ duyệt' );
		}
		$wpdb->update( KHBC_DB::t( 'khoan' ), array( 'da_xoa' => 1, 'sua_luc' => KHBC_Util::now_sql(), 'nguoi_sua' => KHBC_Auth::ten() ), array( 'id' => $id ) );
		self::log( 'deleteCost', $id . ' ' . $cur['ten'] );
		return KHBC_Util::ok( array( 'deleted' => true ) );
	}

	public static function set_cost_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( (string) $status, self::STATUSES, true ) ) { return KHBC_Util::err( 'Trạng thái không hợp lệ' ); }
		$id  = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
		$cur = self::cost_row( $id );
		if ( ! $cur ) { return KHBC_Util::err( 'Không tìm thấy khoản' ); }
		$now = KHBC_Util::now_sql();
		$wpdb->update( KHBC_DB::t( 'khoan' ), array( 'trang_thai' => (string) $status, 'nguoi_duyet' => KHBC_Auth::ten(), 'duyet_luc' => $now, 'sua_luc' => $now ), array( 'id' => $id ) );
		self::log( 'setCostStatus', $id . ' → ' . $status );
		return KHBC_Util::ok();
	}

	public static function set_all_status( $period, $status ) {
		global $wpdb;
		if ( ! in_array( (string) $status, self::STATUSES, true ) ) { return KHBC_Util::err( 'Trạng thái không hợp lệ' ); }
		$ky = KHBC_Util::ky( $period );
		if ( $ky === '' ) { return KHBC_Util::err( 'Thiếu kỳ' ); }
		$t   = KHBC_DB::t( 'khoan' );
		$now = KHBC_Util::now_sql();
		$n   = $wpdb->query( $wpdb->prepare( "UPDATE $t SET trang_thai=%s, nguoi_duyet=%s, duyet_luc=%s, sua_luc=%s WHERE ky=%s AND da_xoa=0 AND trang_thai='cho_duyet'", (string) $status, KHBC_Auth::ten(), $now, $now, $ky ) );
		self::log( 'setAllStatus', $ky . ' → ' . $status . ' (' . (int) $n . ')' );
		return KHBC_Util::ok( array( 'count' => (int) $n ) );
	}

	// ---------------------------------------------------------------- kỳ (state)

	private static function ky_row( $ky ) {
		global $wpdb;
		$t = KHBC_DB::t( 'ky' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE ky=%s", $ky ), ARRAY_A );
	}

	public static function list_periods() {
		global $wpdb;
		$a = $wpdb->get_col( 'SELECT ky FROM ' . KHBC_DB::t( 'ky' ) );
		$b = $wpdb->get_col( 'SELECT DISTINCT ky FROM ' . KHBC_DB::t( 'khoan' ) . ' WHERE da_xoa=0' );
		$all = array_unique( array_merge( (array) $a, (array) $b ) );
		sort( $all );
		return array_values( array_filter( $all ) );
	}

	public static function get_period( $period ) {
		$ky = KHBC_Util::ky( $period );
		if ( $ky === '' ) { return KHBC_Util::err( 'Thiếu kỳ' ); }
		$row   = self::ky_row( $ky );
		$state = $row ? KHBC_Util::json_decode_arr( $row['du_lieu'], null ) : null;
		$costs = self::read_costs( $ky );
		$role  = KHBC_Auth::vai_app();
		$locked = $row ? (int) $row['khoa'] === 1 : false;
		if ( $role === 'nhap' ) {
			$s = is_array( $state ) ? $state : array();
			$deps = array();
			foreach ( (array) ( isset( $s['departments'] ) ? $s['departments'] : array() ) as $d ) {
				$deps[] = array( 'id' => isset( $d['id'] ) ? $d['id'] : '', 'name' => isset( $d['name'] ) ? $d['name'] : '', 'group' => isset( $d['group'] ) ? $d['group'] : '' );
			}
			return KHBC_Util::ok( array( 'period' => $ky, 'role' => $role, 'costs' => $costs, 'locked' => $locked,
				'groups' => isset( $s['groups'] ) ? array_values( (array) $s['groups'] ) : array(), 'departments' => $deps ) );
		}
		return KHBC_Util::ok( array(
			'period'    => $ky,
			'role'      => $role,
			'state'     => $state,
			'version'   => $row ? (int) $row['phien_ban'] : 0,
			'updatedAt' => $row ? KHBC_Util::iso( $row['cap_nhat_luc'] ) : '',
			'updatedBy' => $row ? $row['nguoi_cap_nhat'] : '',
			'locked'    => $locked,
			'costs'     => $costs,
			'periods'   => self::list_periods(),
		) );
	}

	public static function save_state( $period, $state, $version = null ) {
		global $wpdb;
		$ky = KHBC_Util::ky( $period );
		if ( $ky === '' ) { return KHBC_Util::err( 'Thiếu kỳ' ); }
		$state = (array) $state;
		unset( $state['costItems'] );
		$json = wp_json_encode( $state );
		if ( $json === false ) { return KHBC_Util::err( 'Dữ liệu không mã hoá được' ); }
		if ( strlen( $json ) > 12 * 1024 * 1024 ) { return KHBC_Util::err( 'Dữ liệu kỳ quá lớn (>12MB)' ); }
		$cur = self::ky_row( $ky );
		if ( $cur && (int) $cur['khoa'] === 1 && ! KHBC_Auth::la_admin() ) { return KHBC_Util::err( 'Kỳ đã chốt — Admin mới mở lại được.', array( 'locked' => true ) ); }
		if ( $cur && $version !== null && (int) $version !== (int) $cur['phien_ban'] ) {
			return KHBC_Util::err( 'Kỳ này vừa được ' . $cur['nguoi_cap_nhat'] . ' lưu lúc ' . KHBC_Util::iso( $cur['cap_nhat_luc'] ) . '. Tải lại rồi lưu.', array( 'conflict' => true, 'current' => array( 'version' => (int) $cur['phien_ban'], 'updatedBy' => $cur['nguoi_cap_nhat'] ) ) );
		}
		$ver = ( $cur ? (int) $cur['phien_ban'] : 0 ) + 1;
		$now = KHBC_Util::now_sql();
		$data = array( 'phien_ban' => $ver, 'cap_nhat_luc' => $now, 'nguoi_cap_nhat' => KHBC_Auth::ten(), 'du_lieu' => $json );
		if ( $cur ) { $wpdb->update( KHBC_DB::t( 'ky' ), $data, array( 'ky' => $ky ) ); }
		else { $data['ky'] = $ky; $wpdb->insert( KHBC_DB::t( 'ky' ), $data ); }
		self::log( 'saveState', $ky . ' v' . $ver . ' (' . strlen( $json ) . ' ký tự)' );
		return KHBC_Util::ok( array( 'version' => $ver, 'updatedAt' => KHBC_Util::iso( $now ) ) );
	}

	public static function lock_period( $period, $locked ) {
		global $wpdb;
		$ky = KHBC_Util::ky( $period );
		if ( $ky === '' ) { return KHBC_Util::err( 'Thiếu kỳ' ); }
		$cur = self::ky_row( $ky );
		if ( ! $cur ) { $wpdb->insert( KHBC_DB::t( 'ky' ), array( 'ky' => $ky, 'phien_ban' => 0, 'du_lieu' => '' ) ); }
		$wpdb->update( KHBC_DB::t( 'ky' ), array( 'khoa' => $locked ? 1 : 0 ), array( 'ky' => $ky ) );
		self::log( 'lockPeriod', $ky . ( $locked ? ' CHỐT' : ' mở lại' ) );
		return KHBC_Util::ok( array( 'locked' => (bool) $locked ) );
	}

	// ---------------------------------------------------------------- người dùng (Admin)

	public static function list_users() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . KHBC_DB::t( 'nguoi_dung' ) . ' ORDER BY vai ASC, ten ASC', ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$o = KHBC_Auth::out_user( $r );
			$o['coPin'] = $r['pin_hash'] !== '';
			$o['taoLuc'] = KHBC_Util::iso( $r['tao_luc'] );
			$out[] = $o;
		}
		return KHBC_Util::ok( array( 'users' => $out ) );
	}

	/** Thêm / sửa người dùng. $u: {id?, ten, vai, boPhan, email, hoatDong, pin?} */
	public static function save_user( $u ) {
		global $wpdb;
		$u   = (array) $u;
		$t   = KHBC_DB::t( 'nguoi_dung' );
		$id  = isset( $u['id'] ) ? (int) $u['id'] : 0;
		$ten = KHBC_Util::s( isset( $u['ten'] ) ? $u['ten'] : '', 120 );
		$vai = KHBC_Util::s( isset( $u['vai'] ) ? $u['vai'] : 'Nhân viên', 40 );
		if ( $ten === '' ) { return KHBC_Util::err( 'Thiếu tên' ); }
		if ( ! in_array( $vai, KHBC_Auth::VAI, true ) ) { return KHBC_Util::err( 'Vai không hợp lệ' ); }
		$data = array(
			'ten'       => $ten,
			'vai'       => $vai,
			'bo_phan'   => KHBC_Util::s( isset( $u['boPhan'] ) ? $u['boPhan'] : '', 120 ),
			'email'     => strtolower( KHBC_Util::s( isset( $u['email'] ) ? $u['email'] : '', 190 ) ),
			'hoat_dong' => ( ! isset( $u['hoatDong'] ) || $u['hoatDong'] ) ? 1 : 0,
			'sua_luc'   => KHBC_Util::now_sql(),
		);
		$pin = isset( $u['pin'] ) ? trim( (string) $u['pin'] ) : '';
		if ( $pin !== '' ) {
			if ( ! preg_match( '/^\d{4,8}$/', $pin ) ) { return KHBC_Util::err( 'PIN phải gồm 4–8 chữ số' ); }
			if ( KHBC_Auth::pin_trung( $pin, $id ) ) { return KHBC_Util::err( 'PIN này đã có người dùng khác' ); }
			$data['pin_hash'] = password_hash( $pin, PASSWORD_DEFAULT );
		}
		if ( $id ) {
			$me = KHBC_Auth::nguoi();
			if ( $me && (int) $me['id'] === $id && ( $vai !== 'Admin' || ! $data['hoat_dong'] ) ) { return KHBC_Util::err( 'Không tự hạ vai hoặc khoá chính mình.' ); }
			$wpdb->update( $t, $data, array( 'id' => $id ) );
		} else {
			if ( $pin === '' ) { return KHBC_Util::err( 'Người mới cần có PIN' ); }
			$data['tao_luc'] = KHBC_Util::now_sql();
			$wpdb->insert( $t, $data );
			$id = (int) $wpdb->insert_id;
		}
		self::log( 'saveUser', $ten . ' (' . $vai . ')' . ( $pin !== '' ? ' — đặt PIN' : '' ) );
		return KHBC_Util::ok( array( 'user' => KHBC_Auth::out_user( KHBC_Auth::user_row( $id ) ) ) );
	}

	public static function delete_user( $id ) {
		global $wpdb;
		$id = (int) $id;
		$me = KHBC_Auth::nguoi();
		if ( $me && (int) $me['id'] === $id ) { return KHBC_Util::err( 'Không xoá chính mình.' ); }
		$row = KHBC_Auth::user_row( $id );
		if ( ! $row ) { return KHBC_Util::err( 'Không tìm thấy' ); }
		$wpdb->delete( KHBC_DB::t( 'nguoi_dung' ), array( 'id' => $id ) );
		$wpdb->delete( KHBC_DB::t( 'phien' ), array( 'user_id' => $id ) );
		self::log( 'deleteUser', $row['ten'] );
		return KHBC_Util::ok();
	}

	// ---------------------------------------------------------------- tệp đính kèm

	/** uploadFile({name, type, base64}) → {url} — lưu ở wp-content/uploads/khbc/<kỳ>/ */
	public static function upload_file( $data, $period = '' ) {
		$data = (array) $data;
		$b64  = isset( $data['base64'] ) ? (string) $data['base64'] : '';
		if ( strpos( $b64, 'base64,' ) !== false ) { $b64 = substr( $b64, strpos( $b64, 'base64,' ) + 7 ); }
		$bin = base64_decode( $b64, true );
		if ( $bin === false || $bin === '' ) { return KHBC_Util::err( 'Tệp trống' ); }
		if ( strlen( $bin ) > self::MAX_FILE ) { return KHBC_Util::err( 'Tệp quá lớn (tối đa 8MB)' ); }
		$raw = isset( $data['name'] ) ? (string) $data['name'] : 'tep';
		$ext = strtolower( pathinfo( $raw, PATHINFO_EXTENSION ) );
		$ok  = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf', 'xlsx', 'xls', 'docx', 'doc', 'csv', 'txt' );
		if ( $ext === '' ) {
			$mime = isset( $data['type'] ) ? (string) $data['type'] : '';
			$map  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf' );
			$ext  = isset( $map[ $mime ] ) ? $map[ $mime ] : '';
		}
		if ( ! in_array( $ext, $ok, true ) ) { return KHBC_Util::err( 'Loại tệp không được phép: .' . $ext ); }
		$up  = wp_upload_dir();
		$ky  = KHBC_Util::ky( $period );
		$sub = '/khbc/' . ( $ky !== '' ? $ky : 'khac' );
		$dir = $up['basedir'] . $sub;
		if ( ! wp_mkdir_p( $dir ) ) { return KHBC_Util::err( 'Không tạo được thư mục uploads' ); }
		if ( ! file_exists( $dir . '/index.html' ) ) { @file_put_contents( $dir . '/index.html', '' ); }
		$base = sanitize_file_name( pathinfo( $raw, PATHINFO_FILENAME ) );
		if ( $base === '' ) { $base = 'tep'; }
		$name = mb_substr( $base, 0, 60 ) . '_' . time() . '_' . wp_generate_password( 6, false, false ) . '.' . $ext;
		if ( false === file_put_contents( $dir . '/' . $name, $bin ) ) { return KHBC_Util::err( 'Không ghi được tệp lên hosting' ); }
		@chmod( $dir . '/' . $name, 0644 );
		$url = $up['baseurl'] . $sub . '/' . rawurlencode( $name );
		self::log( 'uploadFile', $name . ' (' . round( strlen( $bin ) / 1024 ) . ' KB)' );
		return KHBC_Util::ok( array( 'url' => $url, 'name' => $name ) );
	}
}
