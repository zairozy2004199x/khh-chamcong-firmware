<?php
/**
 * Plugin Name:       POSH · Bán vé (Zalo Mini App)
 * Plugin URI:        https://github.com/zairozy2004199x/khh-chamcong-firmware
 * Description:       Bán vé/dịch vụ khu vui chơi trả trước qua Zalo Mini App. Quản lý dịch vụ (ảnh/giá/mô tả), nhận đơn từ Zalo, dựng VietQR. ĐỘC LẬP với plugin ghế massage.
 * Version:           1.13.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            K&H
 * License:           GPL-2.0-or-later
 *
 * ⚠️ Repo CÔNG KHAI: KHÔNG hardcode số tài khoản/khoá vào mã. Số TK nhận tiền khai trong trang
 *    admin của plugin này (hoặc tự lấy lại từ plugin ghế nếu đã khai ở đó). Số TK vốn công khai
 *    trên QR nên không phải bí mật; nhưng vẫn để ở cấu hình, không nhét chuỗi cụ thể vào source.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class POSH_Ve {

	const NS      = 'posh/v1';
	const VER_TBL = '4';

	/* Hạng thành viên mặc định (điểm mốc). 1 điểm = 1.000đ chi tiêu. Sửa trong admin. */
	const HANG_MAC_DINH = array(
		array( 'ten' => 'Thành viên', 'moc' => 0 ),
		array( 'ten' => 'Bạc',        'moc' => 1500 ),
		array( 'ten' => 'Vàng',       'moc' => 3000 ),
		array( 'ten' => 'Kim cương',  'moc' => 10000 ),
	);

	const VE_MAC_DINH = array(
		array( 'id' => 1, 'ten' => 'Vé vào cửa', 'gia' => 50000,  'gia_goc' => 0,      'nhom' => 'Vé lẻ',  'mo_ta' => 'Vé vào cửa 1 người', 'anh' => '', 'thoi_luong' => '', 'hien' => 1 ),
		array( 'id' => 2, 'ten' => 'Vé 1 giờ',   'gia' => 80000,  'gia_goc' => 100000, 'nhom' => 'Vé lẻ',  'mo_ta' => 'Chơi trong 1 giờ',    'anh' => '', 'thoi_luong' => '60 phút', 'hien' => 1 ),
		array( 'id' => 3, 'ten' => 'Vé cả ngày', 'gia' => 150000, 'gia_goc' => 0,      'nhom' => 'Vé lẻ',  'mo_ta' => 'Chơi không giới hạn trong ngày', 'anh' => '', 'thoi_luong' => 'Cả ngày', 'hien' => 1 ),
		array( 'id' => 4, 'ten' => 'Combo 2 bé', 'gia' => 140000, 'gia_goc' => 200000, 'nhom' => 'Combo', 'mo_ta' => '2 bé chơi cả ngày', 'anh' => '', 'thoi_luong' => 'Cả ngày', 'hien' => 1 ),
	);

	/* Bảng BIN ngân hàng (đủ cho hiển thị tên). Không có thì trả rỗng — thà "không rõ" còn hơn đoán. */
	const NGAN_HANG = array(
		'970418' => 'BIDV', '970436' => 'Vietcombank', '970415' => 'VietinBank', '970422' => 'MB',
		'970407' => 'Techcombank', '970416' => 'ACB', '970448' => 'OCB', '970432' => 'VPBank',
		'970405' => 'Agribank', '970423' => 'TPBank', '970443' => 'SHB', '970441' => 'VIB',
		'970426' => 'MSB', '970403' => 'Sacombank', '970431' => 'Eximbank', '970437' => 'HDBank',
	);

	public static function init() {
		self::bao_dam_bang();
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_shortcode( 'posh_ve', array( __CLASS__, 'shortcode' ) );
	}

	// ───────────────────────────── VietQR (tự chứa) ─────────────────────────────
	private static function tlv( $ma, $v ) { $v = (string) $v; return $ma . substr( '0' . strlen( $v ), -2 ) . $v; }
	private static function crc16( $s ) {
		$crc = 0xFFFF; $n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) { $crc ^= ( ord( $s[ $i ] ) & 0xFF ) << 8;
			for ( $b = 0; $b < 8; $b++ ) { $crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 ); $crc &= 0xFFFF; } }
		return substr( '000' . strtoupper( dechex( $crc ) ), -4 );
	}
	public static function vietqr( $bin, $so_tk, $so_tien, $noi_dung ) {
		$s  = self::tlv( '00', '01' ) . self::tlv( '01', $so_tien ? '12' : '11' );
		$ben = self::tlv( '00', (string) $bin ) . self::tlv( '01', (string) $so_tk );
		$s .= self::tlv( '38', self::tlv( '00', 'A000000727' ) . self::tlv( '01', $ben ) . self::tlv( '02', 'QRIBFTTA' ) );
		$s .= self::tlv( '53', '704' );
		if ( $so_tien ) { $s .= self::tlv( '54', (string) (int) $so_tien ); }
		$s .= self::tlv( '58', 'VN' );
		if ( '' !== (string) $noi_dung ) { $s .= self::tlv( '62', self::tlv( '08', (string) $noi_dung ) ); }
		$s .= '6304'; return $s . self::crc16( $s );
	}
	private static function ten_nh( $bin ) {
		$bin = preg_replace( '/\D+/', '', (string) $bin );
		return isset( self::NGAN_HANG[ $bin ] ) ? self::NGAN_HANG[ $bin ] : '';
	}

	/** TK nhận tiền: ưu tiên cấu hình RIÊNG của plugin vé; trống thì lấy lại từ plugin ghế (nếu có). */
	private static function bank() {
		$bin = trim( (string) get_option( 'pve_bin', '' ) );
		$tk  = trim( (string) get_option( 'pve_so_tk', '' ) );
		$ten = trim( (string) get_option( 'pve_ten_tk', '' ) );
		if ( '' === $bin ) { $bin = trim( (string) get_option( 'vhg_bin', '' ) ); }
		if ( '' === $tk )  { $tk  = trim( (string) get_option( 'vhg_so_tk', '' ) ); }
		if ( '' === $ten ) { $ten = trim( (string) get_option( 'vhg_ten_tk', '' ) ); }
		return array( 'bin' => $bin, 'so_tk' => $tk, 'ten_tk' => $ten, 'ten_nh' => self::ten_nh( $bin ) );
	}

	// ───────────────────────────── Danh mục dịch vụ ─────────────────────────────
	private static function chuan_hoa( $v, $auto_id ) {
		return array(
			'id'         => (int) ( ! empty( $v['id'] ) ? $v['id'] : $auto_id ),
			'ten'        => trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ),
			'gia'        => (int) ( isset( $v['gia'] ) ? $v['gia'] : 0 ),
			'gia_goc'    => (int) ( isset( $v['gia_goc'] ) ? $v['gia_goc'] : 0 ),   // giá gốc (gạch ngang) — 0 = không sale
			'nhom'       => trim( (string) ( isset( $v['nhom'] ) ? $v['nhom'] : '' ) ),
			'khu_vuc'    => trim( (string) ( isset( $v['khu_vuc'] ) ? $v['khu_vuc'] : '' ) ),   // cơ sở/khu vực (HN/HCM…), trống = mọi khu vực
			'mo_ta'      => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ),
			'anh'        => esc_url_raw( (string) ( isset( $v['anh'] ) ? $v['anh'] : '' ) ),
			'thoi_luong' => trim( (string) ( isset( $v['thoi_luong'] ) ? $v['thoi_luong'] : '' ) ),
			'so_luong'   => isset( $v['so_luong'] ) && '' !== $v['so_luong'] ? (int) $v['so_luong'] : -1,   // -1 = không giới hạn; >=0 = số vé còn
			'hien'       => empty( $v['hien'] ) ? 0 : 1,
		);
	}
	/* Trừ tồn kho (n vé) cho dịch vụ có giới hạn. Bỏ qua nếu không giới hạn (-1). */
	private static function giam_ton( $id, $n ) {
		$ds = self::ds_tatca(); $thay = false;
		foreach ( $ds as $k => $v ) {
			if ( (int) $v['id'] === (int) $id && $v['so_luong'] >= 0 ) {
				$ds[ $k ]['so_luong'] = max( 0, (int) $v['so_luong'] - (int) $n );
				$thay = true; break;
			}
		}
		if ( $thay ) { self::luu_ds( $ds ); }
	}
	public static function ds_tatca() {
		$ds = get_option( 'pve_dichvu' );
		if ( ! is_array( $ds ) || ! $ds ) {
			$mac = array(); foreach ( self::VE_MAC_DINH as $v ) { $mac[] = self::chuan_hoa( $v, $v['id'] ); }
			return $mac;   // chuẩn hoá để có đủ trường (so_luong = -1 = không giới hạn)
		}
		$ra = array(); $auto = 1;
		foreach ( $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		return $ra ? $ra : self::VE_MAC_DINH;
	}
	private static function ds() { $r = array(); foreach ( self::ds_tatca() as $v ) { if ( $v['hien'] ) { $r[] = $v; } } return $r; }
	private static function theo_id( $id ) { foreach ( self::ds() as $v ) { if ( (int) $v['id'] === (int) $id ) { return $v; } } return null; }
	private static function luu_ds( $ds ) {
		$ra = array(); $auto = 1;
		foreach ( (array) $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		update_option( 'pve_dichvu', array_values( $ra ) );
	}

	// ───────────────────────────── Bảng vé ─────────────────────────────
	public static function tbl() { global $wpdb; return $wpdb->prefix . 'pve_ve'; }
	public static function tbl_tv() { global $wpdb; return $wpdb->prefix . 'pve_tv'; }
	public static function bao_dam_bang() {
		if ( get_option( 'pve_tbl' ) === self::VER_TBL ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tbl = self::tbl(); $col = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $tbl (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ma_ve VARCHAR(24) NOT NULL,
			dv_ten VARCHAR(120) NOT NULL DEFAULT '',
			so_tien INT NOT NULL DEFAULT 0,
			ten_khach VARCHAR(80) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			noi_dung VARCHAR(40) NOT NULL DEFAULT '',
			chi_tiet TEXT NULL,
			nguon VARCHAR(10) NOT NULL DEFAULT 'web',
			trang_thai VARCHAR(12) NOT NULL DEFAULT 'cho',
			da_cong_diem TINYINT NOT NULL DEFAULT 0,
			tao_luc DATETIME NOT NULL,
			tt_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY ma_ve (ma_ve), KEY trang_thai (trang_thai)
		) $col;" );
		$tv = self::tbl_tv();
		dbDelta( "CREATE TABLE $tv (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sdt VARCHAR(20) NOT NULL,
			ten VARCHAR(80) NOT NULL DEFAULT '',
			diem INT NOT NULL DEFAULT 0,
			tong_chi BIGINT NOT NULL DEFAULT 0,
			so_don INT NOT NULL DEFAULT 0,
			tao_luc DATETIME NOT NULL,
			sua_luc DATETIME NULL,
			PRIMARY KEY (id), UNIQUE KEY sdt (sdt), KEY diem (diem)
		) $col;" );
		update_option( 'pve_tbl', self::VER_TBL );
	}

	/* Bảo đảm có sẵn 1 trang bán vé chứa [posh_ve]. Trả về ID trang (0 nếu lỗi). */
	public static function bao_dam_trang() {
		$pid = (int) get_option( 'pve_page_id' );
		if ( $pid && ( $p = get_post( $pid ) ) && 'trash' !== $p->post_status ) { return $pid; }
		global $wpdb;
		$co = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status IN ('publish','draft','pending') AND post_content LIKE '%[posh_ve]%' ORDER BY ID ASC LIMIT 1" );
		if ( $co ) { update_option( 'pve_page_id', $co ); return $co; }
		$moi = wp_insert_post( array(
			'post_title'   => 'Mua vé khu vui chơi',
			'post_name'    => 'mua-ve',
			'post_content' => '[posh_ve]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
		if ( $moi && ! is_wp_error( $moi ) ) { update_option( 'pve_page_id', (int) $moi ); return (int) $moi; }
		return 0;
	}

	// ───────────────────────────── Điểm & hạng thành viên (1 điểm = 1.000đ) ─────────────────────────────
	/* Cơ sở + toạ độ (để app gợi ý khu vực theo định vị). ten khớp với "khu_vuc" của vé. */
	public static function ds_coso() {
		$c = get_option( 'pve_coso' ); if ( ! is_array( $c ) ) { return array(); }
		$ra = array();
		foreach ( $c as $x ) {
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ra[] = array( 'ten' => $ten,
				'lat' => (float) ( isset( $x['lat'] ) ? $x['lat'] : 0 ),
				'lng' => (float) ( isset( $x['lng'] ) ? $x['lng'] : 0 ) );
		}
		return $ra;
	}
	public static function ds_hang() {
		$h = get_option( 'pve_hang' );
		if ( ! is_array( $h ) || ! $h ) { return self::HANG_MAC_DINH; }
		$ra = array();
		foreach ( $h as $x ) {
			$ten = trim( (string) ( isset( $x['ten'] ) ? $x['ten'] : '' ) );
			if ( '' === $ten ) { continue; }
			$ra[] = array( 'ten' => $ten, 'moc' => max( 0, (int) ( isset( $x['moc'] ) ? $x['moc'] : 0 ) ) );
		}
		if ( ! $ra ) { return self::HANG_MAC_DINH; }
		usort( $ra, function ( $a, $b ) { return $a['moc'] - $b['moc']; } );
		return $ra;
	}
	/* Hạng hiện tại + hạng kế + điểm còn thiếu để lên hạng. */
	public static function hang_cua( $diem ) {
		$ds = self::ds_hang(); $cur = $ds[0]; $ke = null;
		foreach ( $ds as $h ) {
			if ( $diem >= $h['moc'] ) { $cur = $h; } elseif ( null === $ke ) { $ke = $h; }
		}
		return array(
			'hang'      => $cur['ten'],
			'hang_ke'   => $ke ? $ke['ten'] : '',
			'con_thieu' => $ke ? max( 0, $ke['moc'] - (int) $diem ) : 0,
		);
	}
	/* Cộng điểm cho 1 số điện thoại (1 điểm = 1.000đ). */
	public static function cong_diem( $sdt, $ten, $so_tien ) {
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $sdt );
		if ( '' === $sdt ) { return; }
		global $wpdb; $tv = self::tbl_tv();
		$diem = (int) floor( (int) $so_tien / 1000 );
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT id, diem, tong_chi, so_don FROM $tv WHERE sdt=%s", $sdt ), ARRAY_A );
		if ( $cu ) {
			$wpdb->update( $tv, array(
				'ten' => mb_substr( (string) $ten, 0, 80 ),
				'diem' => (int) $cu['diem'] + $diem,
				'tong_chi' => (int) $cu['tong_chi'] + (int) $so_tien,
				'so_don' => (int) $cu['so_don'] + 1,
				'sua_luc' => current_time( 'mysql' ),
			), array( 'id' => (int) $cu['id'] ) );
		} else {
			$wpdb->insert( $tv, array(
				'sdt' => mb_substr( $sdt, 0, 20 ), 'ten' => mb_substr( (string) $ten, 0, 80 ),
				'diem' => $diem, 'tong_chi' => (int) $so_tien, 'so_don' => 1,
				'tao_luc' => current_time( 'mysql' ), 'sua_luc' => current_time( 'mysql' ),
			) );
		}
	}

	// ───────────────────────────── REST ─────────────────────────────
	public static function dang_ky() {
		register_rest_route( self::NS, '/ve/goi', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_goi' ) ) );
		register_rest_route( self::NS, '/ve/dat', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_dat' ) ) );
		register_rest_route( self::NS, '/ve/dat-gio', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_dat_gio' ) ) );
		register_rest_route( self::NS, '/ve/trangthai', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_trangthai' ) ) );
		register_rest_route( self::NS, '/tin', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_tin' ) ) );
		register_rest_route( self::NS, '/tv', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_tv' ) ) );
		register_rest_route( self::NS, '/uudai', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_uudai' ) ) );
		register_rest_route( self::NS, '/zalo/sdt', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_zalo_sdt' ) ) );
		// Khu quản lý (nhân viên) — bảo vệ bằng PIN khai ở admin (không hardcode).
		register_rest_route( self::NS, '/ql/dangnhap', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_dangnhap' ) ) );
		register_rest_route( self::NS, '/ql/baocao', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_baocao' ) ) );
		register_rest_route( self::NS, '/ql/donhang', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_donhang' ) ) );
		register_rest_route( self::NS, '/ql/capnhat', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'r_ql_capnhat' ) ) );
	}
	public static function cors( $served, $result, $request, $server ) {
		if ( $request && 0 === strpos( (string) $request->get_route(), '/' . self::NS ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
		}
		return $served;
	}
	public static function r_goi() {
		$goi = array();
		foreach ( self::ds() as $g ) {
			$goi[] = array( 'ma' => (int) $g['id'], 'ten' => $g['ten'], 'tien' => (int) $g['gia'],
				'gia_goc' => (int) $g['gia_goc'], 'nhom' => (string) $g['nhom'], 'khu_vuc' => (string) $g['khu_vuc'],
				'mo_ta' => (string) $g['mo_ta'], 'anh' => (string) $g['anh'], 'thoi_luong' => (string) $g['thoi_luong'],
				'so_luong' => (int) $g['so_luong'] );
		}
		$b = self::bank();
		return array( 'ok' => true, 'goi' => $goi, 'co_so' => self::ds_coso(),
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	public static function r_dat( $req ) {
		$id  = (int) $req->get_param( 'id' );
		$ten = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		$goi = self::theo_id( $id );
		if ( ! $goi ) {                                  // tương thích app cũ gửi `tien`
			$t = (int) $req->get_param( 'tien' );
			if ( $t > 0 ) { foreach ( self::ds() as $v ) { if ( (int) $v['gia'] === $t ) { $goi = $v; break; } } }
		}
		if ( ! $goi ) { return new WP_Error( 'goi', 'Vé không hợp lệ.', array( 'status' => 400 ) ); }
		if ( $goi['so_luong'] >= 0 && $goi['so_luong'] < 1 ) { return new WP_Error( 'het', 'Vé "' . $goi['ten'] . '" đã hết.', array( 'status' => 409 ) ); }
		if ( '' === $ten || '' === $sdt ) { return new WP_Error( 'thieu', 'Cần tên và số điện thoại.', array( 'status' => 400 ) ); }
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		if ( ! self::nhip_ok() ) { return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) ); }

		global $wpdb;
		$ma_ve = self::ma_ve_moi(); $tien = (int) $goi['gia']; $noidung = 'VE' . $ma_ve;
		$wpdb->insert( self::tbl(), array(
			'ma_ve' => $ma_ve, 'dv_ten' => $goi['ten'], 'so_tien' => $tien,
			'ten_khach' => mb_substr( $ten, 0, 80 ), 'sdt' => mb_substr( $sdt, 0, 20 ),
			'noi_dung' => $noidung, 'nguon' => ( 'zalo' === $req->get_param( 'nguon' ) ? 'zalo' : 'web' ),
			'trang_thai' => 'cho', 'tao_luc' => current_time( 'mysql' ),
		) );
		self::giam_ton( $goi['id'], 1 );
		$qr = self::vietqr( $b['bin'], $b['so_tk'], $tien, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tien, 'goi_ten' => $goi['ten'],
			'noi_dung' => $noidung, 'qr' => $qr, 'trang_thai' => 'cho',
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	/* Đặt nhiều vé trong 1 giỏ -> 1 đơn, 1 mã QR tổng. items = [{id, sl}, ...] */
	public static function r_dat_gio( $req ) {
		$items = $req->get_param( 'items' );
		$ten   = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt   = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		if ( ! is_array( $items ) || ! $items ) { return new WP_Error( 'gio', 'Giỏ hàng trống.', array( 'status' => 400 ) ); }
		if ( '' === $ten || '' === $sdt ) { return new WP_Error( 'thieu', 'Cần tên và số điện thoại.', array( 'status' => 400 ) ); }
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) { return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) ); }
		if ( ! self::nhip_ok() ) { return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) ); }

		$tong = 0; $mota = array(); $ct = array(); $can = array();
		foreach ( $items as $it ) {
			$id = (int) ( isset( $it['id'] ) ? $it['id'] : 0 );
			$sl = max( 1, (int) ( isset( $it['sl'] ) ? $it['sl'] : 1 ) );
			$g  = self::theo_id( $id );
			if ( ! $g ) { continue; }
			$can[ $id ] = ( isset( $can[ $id ] ) ? $can[ $id ] : 0 ) + $sl;
			if ( $g['so_luong'] >= 0 && $g['so_luong'] < $can[ $id ] ) {
				return new WP_Error( 'het', 'Vé "' . $g['ten'] . '" không đủ số lượng.', array( 'status' => 409 ) );
			}
			$tong  += (int) $g['gia'] * $sl;
			$mota[] = $sl . 'x ' . $g['ten'];
			$ct[]   = array( 'id' => $id, 'ten' => $g['ten'], 'gia' => (int) $g['gia'], 'sl' => $sl );
		}
		if ( $tong < 1000 ) { return new WP_Error( 'gio', 'Giỏ hàng không hợp lệ.', array( 'status' => 400 ) ); }

		global $wpdb;
		$ma_ve = self::ma_ve_moi(); $noidung = 'VE' . $ma_ve; $tomtat = implode( ', ', $mota );
		$wpdb->insert( self::tbl(), array(
			'ma_ve' => $ma_ve, 'dv_ten' => mb_substr( $tomtat, 0, 120 ), 'so_tien' => $tong,
			'ten_khach' => mb_substr( $ten, 0, 80 ), 'sdt' => mb_substr( $sdt, 0, 20 ),
			'noi_dung' => $noidung, 'chi_tiet' => wp_json_encode( $ct ),
			'nguon' => ( 'zalo' === $req->get_param( 'nguon' ) ? 'zalo' : 'web' ),
			'trang_thai' => 'cho', 'tao_luc' => current_time( 'mysql' ),
		) );
		foreach ( $can as $id => $sl ) { self::giam_ton( $id, $sl ); }
		$qr = self::vietqr( $b['bin'], $b['so_tk'], $tong, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tong, 'goi_ten' => $tomtat,
			'noi_dung' => $noidung, 'qr' => $qr, 'trang_thai' => 'cho', 'chi_tiet' => $ct,
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}
	public static function r_trangthai( $req ) {
		global $wpdb;
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT ma_ve, dv_ten, so_tien, trang_thai, tao_luc, tt_luc FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		return array( 'ok' => true, 'ma_ve' => $r['ma_ve'], 'goi_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
			'trang_thai' => $r['trang_thai'], 'tao_luc' => $r['tao_luc'], 'tt_luc' => $r['tt_luc'] );
	}

	public static function r_tin() {
		$q = new WP_Query( array(
			'post_type' => 'post', 'post_status' => 'publish',
			'posts_per_page' => 12, 'ignore_sticky_posts' => true,
			'no_found_rows' => true,
		) );
		$ra = array();
		foreach ( $q->posts as $p ) {
			$anh = get_the_post_thumbnail_url( $p->ID, 'medium' );
			$xem = (int) get_post_meta( $p->ID, 'post_views_count', true );
			$ra[] = array(
				'id'       => (int) $p->ID,
				'tieu_de'  => get_the_title( $p ),
				'anh'      => $anh ? $anh : '',
				'ngay'     => get_the_date( 'H:i, d/m/Y', $p ),
				'luot_xem' => $xem,
				'link'     => get_permalink( $p ),
			);
		}
		wp_reset_postdata();
		return array( 'ok' => true, 'tin' => $ra );
	}

	/* Tra điểm/hạng thành viên theo SĐT (cho tab Cá nhân). */
	public static function r_tv( $req ) {
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );
		if ( '' === $sdt ) { return new WP_Error( 'sdt', 'Thiếu số điện thoại.', array( 'status' => 400 ) ); }
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT ten, diem, tong_chi, so_don FROM ' . self::tbl_tv() . ' WHERE sdt=%s', $sdt ), ARRAY_A );
		$diem = $r ? (int) $r['diem'] : 0;
		$h = self::hang_cua( $diem );
		return array(
			'ok' => true, 'sdt' => $sdt, 'ten' => $r ? $r['ten'] : '',
			'diem' => $diem, 'tong_chi' => $r ? (int) $r['tong_chi'] : 0, 'so_don' => $r ? (int) $r['so_don'] : 0,
			'hang' => $h['hang'], 'hang_ke' => $h['hang_ke'], 'con_thieu' => $h['con_thieu'],
			'moc' => self::ds_hang(),
		);
	}

	// ───────────────────────────── Ưu đãi (voucher/khuyến mãi) ─────────────────────────────
	private static function chuan_hoa_uu( $v, $auto ) {
		return array(
			'id'    => (int) ( ! empty( $v['id'] ) ? $v['id'] : $auto ),
			'ten'   => trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ),
			'mo_ta' => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ),
			'anh'   => esc_url_raw( (string) ( isset( $v['anh'] ) ? $v['anh'] : '' ) ),
			'hang'  => trim( (string) ( isset( $v['hang'] ) ? $v['hang'] : '' ) ),   // hạng tối thiểu (trống = mọi khách)
			'han'   => trim( (string) ( isset( $v['han'] ) ? $v['han'] : '' ) ),     // hạn dùng (text tự do)
			'hien'  => empty( $v['hien'] ) ? 0 : 1,
		);
	}
	public static function ds_uudai_tatca() {
		$ds = get_option( 'pve_uudai' ); if ( ! is_array( $ds ) ) { return array(); }
		$ra = array(); $auto = 1;
		foreach ( $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa_uu( $v, $auto ); if ( '' === $m['ten'] ) { continue; }
			$ra[] = $m;
		}
		return $ra;
	}
	private static function ds_uudai() { $r = array(); foreach ( self::ds_uudai_tatca() as $v ) { if ( $v['hien'] ) { $r[] = $v; } } return $r; }
	private static function luu_uudai( $ds ) {
		$ra = array(); $auto = 1;
		foreach ( (array) $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa_uu( $v, $auto ); if ( '' === $m['ten'] ) { continue; }
			$ra[] = $m;
		}
		update_option( 'pve_uudai', array_values( $ra ) );
	}
	public static function r_uudai() {
		$ra = array();
		foreach ( self::ds_uudai() as $u ) {
			$ra[] = array( 'id' => (int) $u['id'], 'ten' => $u['ten'], 'mo_ta' => $u['mo_ta'],
				'anh' => $u['anh'], 'hang' => $u['hang'], 'han' => $u['han'] );
		}
		return array( 'ok' => true, 'uudai' => $ra );
	}

	/* Giải mã SĐT từ token đăng nhập Zalo (getPhoneNumber). Secret Key khai ở admin, KHÔNG hardcode. */
	public static function r_zalo_sdt( $req ) {
		$secret = (string) get_option( 'pve_zalo_secret', '' );
		if ( '' === $secret ) { return new WP_Error( 'cfg', 'Chưa cấu hình Zalo Secret Key.', array( 'status' => 409 ) ); }
		$token = (string) $req->get_param( 'token' );
		$at    = (string) $req->get_param( 'access_token' );
		if ( '' === $token || '' === $at ) { return new WP_Error( 'thieu', 'Thiếu token đăng nhập.', array( 'status' => 400 ) ); }
		$res = wp_remote_get( 'https://graph.zalo.me/v2.0/me/info', array(
			'timeout' => 10,
			'headers' => array( 'access_token' => $at, 'code' => $token, 'secret_key' => $secret ),
		) );
		if ( is_wp_error( $res ) ) { return new WP_Error( 'kn', 'Không gọi được Zalo.', array( 'status' => 502 ) ); }
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$sdt  = isset( $body['data']['number'] ) ? preg_replace( '/[^0-9]/', '', (string) $body['data']['number'] ) : '';
		if ( '' === $sdt ) { return new WP_Error( 'sdt', 'Không lấy được số điện thoại.', array( 'status' => 400 ) ); }
		if ( 0 === strpos( $sdt, '84' ) ) { $sdt = '0' . substr( $sdt, 2 ); }   // 84xxx -> 0xxx
		return array( 'ok' => true, 'sdt' => $sdt );
	}

	// ───────────────────────────── Bảo vệ khu quản lý bằng PIN ─────────────────────────────
	private static function pin_hople( $req ) {
		$pin_luu = (string) get_option( 'pve_pin', '' );
		if ( '' === $pin_luu ) { return false; }                    // chưa khai PIN => khoá hẳn khu quản lý
		$pin = (string) $req->get_param( 'pin' );
		return hash_equals( $pin_luu, $pin );
	}
	private static function pin_chan() {
		// Chặn dò PIN: tối đa ~1 lần/giây/IP cho khu /ql.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : 'x';
		$k = 'pve_pinchan_' . md5( $ip );
		if ( get_transient( $k ) ) { return true; }
		set_transient( $k, 1, 1 ); return false;
	}
	private static function loi_pin() { return new WP_Error( 'pin', 'Sai mã PIN hoặc chưa cấu hình.', array( 'status' => 401 ) ); }
	public static function r_ql_dangnhap( $req ) {
		if ( self::pin_chan() ) { return new WP_Error( 'nhip', 'Thử lại sau giây lát.', array( 'status' => 429 ) ); }
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		return array( 'ok' => true );
	}
	public static function r_ql_baocao( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl(); $paid = "trang_thai IN ('da_tt','da_dung')";
		$hnay = current_time( 'Y-m-d' ); $thang = current_time( 'Y-m' );
		$top = array();
		foreach ( $wpdb->get_results( "SELECT dv_ten, COUNT(*) sl, COALESCE(SUM(so_tien),0) dt FROM $tbl WHERE $paid GROUP BY dv_ten ORDER BY sl DESC LIMIT 100", ARRAY_A ) as $r ) {
			$top[] = array( 'ten' => $r['dv_ten'], 'sl' => (int) $r['sl'], 'dt' => (int) $r['dt'] );
		}
		return array( 'ok' => true,
			'dt_hnay'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $hnay ) ),
			'dt_thang' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE_FORMAT(tao_luc,'%%Y-%%m')=%s", $thang ) ),
			've_ban'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid" ),
			've_cho'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE trang_thai='cho'" ),
			've_hnay'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $hnay ) ),
			// Tách theo kênh bán (zalo / web)
			've_zalo'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid AND nguon='zalo'" ),
			've_web'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid AND nguon<>'zalo'" ),
			'dt_zalo'  => (int) $wpdb->get_var( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND nguon='zalo'" ),
			'dt_web'   => (int) $wpdb->get_var( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND nguon<>'zalo'" ),
			'top'      => $top );
	}
	public static function r_ql_donhang( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$loc = sanitize_key( (string) $req->get_param( 'loc' ) );
		$tim = trim( (string) $req->get_param( 'tim' ) );
		$where = array(); $args = array();
		if ( in_array( $loc, array( 'cho', 'da_tt', 'da_dung', 'huy' ), true ) ) { $where[] = 'trang_thai=%s'; $args[] = $loc; }
		if ( '' !== $tim ) { $like = '%' . $wpdb->esc_like( $tim ) . '%'; $where[] = '(ma_ve LIKE %s OR sdt LIKE %s OR ten_khach LIKE %s)'; array_push( $args, $like, $like, $like ); }
		if ( in_array( $loc, array( 'zalo', 'web' ), true ) ) {
			// cho phép lọc theo kênh: loc=zalo / loc=web
			$where[] = ( 'zalo' === $loc ? "nguon='zalo'" : "nguon<>'zalo'" );
		}
		$sql = "SELECT ma_ve, dv_ten, so_tien, ten_khach, sdt, trang_thai, nguon, tao_luc FROM $tbl";
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY id DESC LIMIT 60';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$ds = array();
		foreach ( (array) $rows as $r ) {
			$ds[] = array( 'ma_ve' => $r['ma_ve'], 'dv_ten' => $r['dv_ten'], 'so_tien' => (int) $r['so_tien'],
				'ten_khach' => $r['ten_khach'], 'sdt' => $r['sdt'], 'trang_thai' => $r['trang_thai'],
				'nguon' => $r['nguon'], 'tao_luc' => $r['tao_luc'] );
		}
		return array( 'ok' => true, 'don' => $ds );
	}
	public static function r_ql_capnhat( $req ) {
		if ( ! self::pin_hople( $req ) ) { return self::loi_pin(); }
		global $wpdb; $tbl = self::tbl();
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		$xin = sanitize_key( (string) $req->get_param( 'trang_thai' ) );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		if ( ! in_array( $xin, array( 'da_tt', 'da_dung', 'huy' ), true ) ) { return new WP_Error( 'tt', 'Trạng thái không hợp lệ.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT sdt, ten_khach, so_tien, da_cong_diem FROM $tbl WHERE ma_ve=%s", $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		$wpdb->update( $tbl, array( 'trang_thai' => $xin, 'tt_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
		$diem = 0;
		if ( 'da_tt' === $xin && ! (int) $r['da_cong_diem'] ) {
			self::cong_diem( $r['sdt'], $r['ten_khach'], $r['so_tien'] );
			$wpdb->update( $tbl, array( 'da_cong_diem' => 1 ), array( 'ma_ve' => $ma ) );
			$diem = (int) floor( (int) $r['so_tien'] / 1000 );
		}
		return array( 'ok' => true, 'ma_ve' => $ma, 'trang_thai' => $xin, 'diem_cong' => $diem );
	}

	private static function ma_ve_moi() {
		global $wpdb; $bang = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$s = ''; for ( $i = 0; $i < 8; $i++ ) { $s .= $bang[ random_int( 0, strlen( $bang ) - 1 ) ]; }
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE ma_ve=%s', $s ) ) ) { return $s; }
		}
		return 'V' . substr( (string) time(), -7 );
	}
	private static function nhip_ok() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : 'x';
		$k  = 'pve_nhip_' . md5( $ip );
		if ( get_transient( $k ) ) { return false; }
		set_transient( $k, 1, 3 ); return true;
	}

	// ───────────────────────────── Trang bán vé công khai ([posh_ve]) ─────────────────────────────
	/* Dán [posh_ve] vào 1 trang trên khmatrix.com. Đặt vé ở đây đẩy thẳng vào cùng bảng
	 * pve_ve mà Zalo App + trang admin đọc — nên vé bán ở web/Zalo đều thấy chung một chỗ. */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tieu_de'  => '',                              // tiêu đề nhỏ trong lưới (để trống = không lặp)
			'hero'     => 'Khu vui chơi POSH',             // tiêu đề banner hero
			'hero_phu' => 'Mua vé trước – nhận mã QR – vào cửa nhanh, không xếp hàng',
			'anh_nen'  => '',                              // ảnh nền hero (URL). Trống = nền gradient vàng
		), $atts, 'posh_ve' );
		$rest = esc_url_raw( rest_url( self::NS ) );

		// Gom dịch vụ theo nhóm + gom danh sách khu vực (để hiện màn chọn khu vực).
		$nhom = array(); $kvucs = array();
		foreach ( self::ds() as $g ) {
			$k = trim( (string) $g['nhom'] ) !== '' ? $g['nhom'] : 'Vé';
			$nhom[ $k ][] = $g;
			$kv = trim( (string) $g['khu_vuc'] );
			if ( '' !== $kv && ! in_array( $kv, $kvucs, true ) ) { $kvucs[] = $kv; }
		}

		ob_start();
		?>
		<div class="pve-page">
		<div class="pve-hero"<?php echo $atts['anh_nen'] ? ' style="background-image:linear-gradient(rgba(180,120,10,.55),rgba(140,90,10,.75)),url(' . esc_url( $atts['anh_nen'] ) . ')"' : ''; ?>>
			<div class="pve-hero-in">
				<h1 class="pve-hero-t"><?php echo esc_html( $atts['hero'] ); ?></h1>
				<?php if ( $atts['hero_phu'] ) : ?><p class="pve-hero-p"><?php echo esc_html( $atts['hero_phu'] ); ?></p><?php endif; ?>
				<a href="#pve-ds" class="pve-hero-btn">Mua vé ngay ↓</a>
			</div>
		</div>
		<div class="pve-wrap" id="pve-ds">
			<?php if ( $kvucs ) : ?><div class="pve-kvbar" hidden>📍 Khu vực: <b class="pve-kvbar-ten"></b> <a href="#" class="pve-kvbar-doi">Đổi khu vực</a></div><?php endif; ?>

			<div class="pve-qf">
				<div class="pve-qf-h">🎟️ Đặt vé nhanh</div>
				<div class="pve-qf-grid">
					<input class="pve-qf-ten" placeholder="Họ và tên" autocomplete="name">
					<input class="pve-qf-sdt" inputmode="tel" placeholder="Số điện thoại" autocomplete="tel">
					<?php if ( $kvucs ) : ?>
					<select class="pve-qf-cs">
						<option value="">Tất cả cơ sở</option>
						<?php foreach ( $kvucs as $kv ) : ?><option value="<?php echo esc_attr( $kv ); ?>"><?php echo esc_html( $kv ); ?></option><?php endforeach; ?>
					</select>
					<?php endif; ?>
					<select class="pve-qf-ve"><option value="">-- Chọn loại vé --</option></select>
					<input class="pve-qf-sl" type="number" min="1" value="1" title="Số lượng">
				</div>
				<button type="button" class="pve-qf-go">Mua vé ngay</button>
				<div class="pve-qf-err" hidden></div>
				<div class="pve-qf-note">Hoặc chọn loại vé ở danh mục bên dưới ↓</div>
			</div>
			<?php if ( '' !== trim( (string) $atts['tieu_de'] ) ) : ?><h2 class="pve-title"><?php echo esc_html( $atts['tieu_de'] ); ?></h2><?php endif; ?>
			<?php if ( empty( $nhom ) ) : ?>
				<p class="pve-empty">Chưa có vé nào đang mở bán.</p>
			<?php endif; ?>
			<?php foreach ( $nhom as $ten_nhom => $ds ) : ?>
				<div class="pve-sec">
					<div class="pve-sec-h"><?php echo esc_html( $ten_nhom ); ?></div>
					<div class="pve-grid">
						<?php foreach ( $ds as $g ) :
							$sale = ( (int) $g['gia_goc'] > (int) $g['gia'] )
								? round( ( 1 - $g['gia'] / $g['gia_goc'] ) * 100 ) : 0;
								$het = ( $g['so_luong'] >= 0 && $g['so_luong'] < 1 ); ?>
							<div class="pve-card<?php echo $het ? ' pve-het' : ''; ?>"
								data-id="<?php echo (int) $g['id']; ?>"
								data-kv="<?php echo esc_attr( trim( (string) $g['khu_vuc'] ) ); ?>"
								data-ten="<?php echo esc_attr( $g['ten'] ); ?>"
								data-gia="<?php echo (int) $g['gia']; ?>">
								<div class="pve-img">
									<?php if ( $g['anh'] ) : ?>
										<img src="<?php echo esc_url( $g['anh'] ); ?>" alt="<?php echo esc_attr( $g['ten'] ); ?>" loading="lazy">
									<?php else : ?><span class="pve-noimg">🎟️</span><?php endif; ?>
									<?php if ( $sale > 0 ) : ?><span class="pve-sale">-<?php echo (int) $sale; ?>%</span><?php endif; ?>
								</div>
								<div class="pve-body">
									<div class="pve-ten"><?php echo esc_html( $g['ten'] ); ?></div>
									<?php if ( $g['thoi_luong'] ) : ?><div class="pve-tl">⏱ <?php echo esc_html( $g['thoi_luong'] ); ?></div><?php endif; ?>
									<?php if ( $g['mo_ta'] ) : ?><div class="pve-mota"><?php echo esc_html( $g['mo_ta'] ); ?></div><?php endif; ?>
									<?php if ( $g['so_luong'] >= 0 ) : ?><div class="pve-con"><?php echo $het ? 'Hết vé' : 'Còn ' . (int) $g['so_luong'] . ' vé'; ?></div><?php endif; ?>
									<div class="pve-foot">
										<div class="pve-gia-wrap">
											<span class="pve-gia"><?php echo esc_html( number_format_i18n( $g['gia'] ) ); ?>đ</span>
											<?php if ( $sale > 0 ) : ?><span class="pve-goc"><?php echo esc_html( number_format_i18n( $g['gia_goc'] ) ); ?>đ</span><?php endif; ?>
										</div>
										<?php if ( $het ) : ?><button type="button" class="pve-buy" disabled>Hết vé</button>
										<?php else : ?><button type="button" class="pve-buy">Đặt vé</button><?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		</div>

		<?php if ( $kvucs ) : ?>
		<div class="pve-wel" hidden>
			<div class="pve-wel-box">
				<div class="pve-wel-h">Chào mừng bạn! Vui lòng chọn khu vực</div>
				<div class="pve-wel-list">
					<?php foreach ( $kvucs as $kv ) : ?>
						<button type="button" class="pve-wel-kv" data-kv="<?php echo esc_attr( $kv ); ?>"><?php echo esc_html( $kv ); ?></button>
					<?php endforeach; ?>
				</div>
				<button type="button" class="pve-wel-ok">Xác nhận</button>
				<div class="pve-wel-note">Chọn khu vực để xem đúng vé đang mở bán tại đó.</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="pve-mask" hidden>
			<div class="pve-modal">
				<button type="button" class="pve-x" aria-label="Đóng">×</button>

				<div class="pve-step pve-step-form">
					<div class="pve-m-ten"></div>
					<div class="pve-m-gia"></div>
					<label class="pve-lb">Họ tên</label>
					<input class="pve-in pve-f-ten" placeholder="Tên người mua" autocomplete="name">
					<label class="pve-lb">Số điện thoại</label>
					<input class="pve-in pve-f-sdt" placeholder="Số Zalo/điện thoại" inputmode="tel" autocomplete="tel">
					<button type="button" class="pve-go">Tạo mã thanh toán</button>
					<div class="pve-err" hidden></div>
					<p class="pve-note">Bấm để tạo vé và hiện mã QR chuyển khoản. Vé được xác nhận sau khi nhận đủ tiền.</p>
				</div>

				<div class="pve-step pve-step-qr" hidden>
					<div class="pve-badge cho">⏳ Chờ thanh toán</div>
					<div class="pve-qr"></div>
					<div class="pve-kv"><span>Mã vé</span><b class="pve-r-mave"></b></div>
					<div class="pve-kv"><span>Gói</span><b class="pve-r-goi"></b></div>
					<div class="pve-kv"><span>Số tiền</span><b class="pve-r-tien"></b></div>
					<div class="pve-kv"><span>Ngân hàng</span><b class="pve-r-nh"></b></div>
					<div class="pve-kv"><span>Số TK</span><b class="pve-r-stk"></b></div>
					<div class="pve-kv"><span>Chủ TK</span><b class="pve-r-ctk"></b></div>
					<div class="pve-kv pve-copy"><span>Nội dung</span><b class="pve-r-nd"></b> <em>(chạm để copy)</em></div>
					<p class="pve-note">Quét mã bằng app ngân hàng. Giữ đúng <b>nội dung</b> để hệ thống tự khớp. Trang sẽ tự cập nhật khi đã nhận tiền.</p>
				</div>
			</div>
		</div>

		<script>
		(function(){
			var REST = <?php echo wp_json_encode( $rest ); ?>;
			var mask = document.querySelector('.pve-mask');
			try { document.body.appendChild(mask); } catch(e){}  // đưa popup ra body để nền mờ phủ kín (khỏi lỗi theme bọc transform)
			var mFor = null, timer = null;
			function tien(n){ try{ return (Number(n)||0).toLocaleString('vi-VN')+'đ'; }catch(e){ return n+'đ'; } }
			function qs(s){ return mask.querySelector(s); }
			function show(step){ qs('.pve-step-form').hidden = (step!=='form'); qs('.pve-step-qr').hidden = (step!=='qr'); }
			function loadQR(cb){
				if (window.QRCode){ cb(); return; }
				var s=document.createElement('script');
				s.src='https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
				s.onload=cb; s.onerror=function(){ cb('x'); }; document.head.appendChild(s);
			}
			function moModal(card){
				mFor = card;
				qs('.pve-m-ten').textContent = card.dataset.ten;
				qs('.pve-m-gia').textContent = tien(card.dataset.gia);
				qs('.pve-err').hidden = true; qs('.pve-f-ten').value=''; qs('.pve-f-sdt').value='';
				show('form'); mask.hidden = false;
			}
			function dong(){ mask.hidden = true; if(timer){ clearInterval(timer); timer=null; } }

			// ----- Màn chào mừng: chọn khu vực rồi lọc vé -----
			var wel = document.querySelector('.pve-wel');
			if (wel) {
				try { document.body.appendChild(wel); } catch(e){}
				var KVKEY = 'posh_kvuc';
				var okBtn = wel.querySelector('.pve-wel-ok');
				var kvBtns = [].slice.call(wel.querySelectorAll('.pve-wel-kv'));
				var bar = document.querySelector('.pve-kvbar');
				var kvChon = kvBtns.length ? kvBtns[0].getAttribute('data-kv') : '';
				function chon(kv){
					kvChon = kv;
					kvBtns.forEach(function(x){ x.classList.toggle('on', x.getAttribute('data-kv') === kv); });
				}
				function locKV(kv){
					[].slice.call(document.querySelectorAll('.pve-sec')).forEach(function(s){
						var vis = 0;
						[].slice.call(s.querySelectorAll('.pve-card')).forEach(function(c){
							var k = c.getAttribute('data-kv') || '', ok = (!k || k === kv);
							c.style.display = ok ? '' : 'none'; if (ok) vis++;
						});
						s.style.display = vis ? '' : 'none';
					});
					if (bar){ var t = bar.querySelector('.pve-kvbar-ten'); if (t) t.textContent = kv; bar.hidden = false; }
				}
				kvBtns.forEach(function(b){ b.onclick = function(){ chon(b.getAttribute('data-kv')); }; });
				okBtn.onclick = function(){ wel.hidden = true; try{ sessionStorage.setItem(KVKEY, kvChon); }catch(e){} locKV(kvChon); };
				if (bar){ var d = bar.querySelector('.pve-kvbar-doi'); if (d) d.onclick = function(e){ e.preventDefault(); wel.hidden = false; }; }
				var daChon = ''; try{ daChon = sessionStorage.getItem(KVKEY) || ''; }catch(e){}
				var hopLe = kvBtns.some(function(b){ return b.getAttribute('data-kv') === daChon; });
				if (daChon && hopLe) { chon(daChon); locKV(daChon); wel.hidden = true; }
				else { chon(kvChon); wel.hidden = false; }
			}

			// ----- Đặt vé nhanh (form trên hero) -----
			(function(){
				var qf = document.querySelector('.pve-qf'); if(!qf) return;
				var selVe = qf.querySelector('.pve-qf-ve');
				var selCs = qf.querySelector('.pve-qf-cs');
				var cards = [].slice.call(document.querySelectorAll('.pve-card')).map(function(c){
					return { id:c.getAttribute('data-id'), ten:c.getAttribute('data-ten'), gia:c.getAttribute('data-gia'), kv:c.getAttribute('data-kv')||'', het:c.classList.contains('pve-het') };
				});
				function fillVe(){
					var cs = selCs ? selCs.value : '';
					selVe.innerHTML = '<option value="">-- Chọn loại vé --</option>';
					cards.forEach(function(c){
						if (c.het) return;
						if (cs && c.kv && c.kv !== cs) return;
						var o = document.createElement('option'); o.value = c.id; o.textContent = c.ten + ' — ' + tien(c.gia); selVe.appendChild(o);
					});
				}
				if (selCs){ var kv0=''; try{ kv0 = sessionStorage.getItem('posh_kvuc')||''; }catch(e){} if(kv0){ selCs.value = kv0; } selCs.addEventListener('change', fillVe); }
				fillVe();
				qf.querySelector('.pve-qf-go').addEventListener('click', function(){
					var ten = qf.querySelector('.pve-qf-ten').value.trim();
					var sdt = qf.querySelector('.pve-qf-sdt').value.trim();
					var id = Number(selVe.value || 0);
					var sl = Math.max(1, Number(qf.querySelector('.pve-qf-sl').value || 1));
					var err = qf.querySelector('.pve-qf-err');
					if(!id){ err.textContent='Vui lòng chọn loại vé.'; err.hidden=false; return; }
					if(!ten || !sdt){ err.textContent='Nhập tên và số điện thoại.'; err.hidden=false; return; }
					err.hidden = true; var btn = this; btn.disabled = true; btn.textContent = 'Đang tạo…';
					fetch(REST+'/ve/dat-gio', { method:'POST', headers:{'Content-Type':'application/json'},
						body: JSON.stringify({ items:[{ id:id, sl:sl }], ten:ten, sdt:sdt }) })
					.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
					.then(function(o){ btn.disabled=false; btn.textContent='Mua vé ngay';
						if(!o.ok || o.d.ok===false){ err.textContent = (o.d && (o.d.message||o.d.code)) || 'Lỗi tạo vé.'; err.hidden=false; return; }
						hienQR(o.d);
					})
					.catch(function(){ btn.disabled=false; btn.textContent='Mua vé ngay'; err.textContent='Lỗi kết nối máy chủ.'; err.hidden=false; });
				});
			})();

			document.querySelectorAll('.pve-card .pve-buy').forEach(function(b){
				b.addEventListener('click', function(){ moModal(b.closest('.pve-card')); });
			});
			mask.querySelector('.pve-x').addEventListener('click', dong);
			mask.addEventListener('click', function(e){ if(e.target===mask) dong(); });

			qs('.pve-go').addEventListener('click', function(){
				var ten = qs('.pve-f-ten').value.trim(), sdt = qs('.pve-f-sdt').value.trim();
				var err = qs('.pve-err');
				if(!ten || !sdt){ err.textContent='Nhập tên và số điện thoại.'; err.hidden=false; return; }
				err.hidden = true; this.disabled = true; this.textContent='Đang tạo…';
				var btn = this;
				fetch(REST+'/ve/dat', { method:'POST', headers:{'Content-Type':'application/json'},
					body: JSON.stringify({ id: Number(mFor.dataset.id), ten: ten, sdt: sdt }) })
				.then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
				.then(function(o){
					btn.disabled=false; btn.textContent='Tạo mã thanh toán';
					if(!o.ok || o.d.ok===false){ err.textContent = (o.d && (o.d.message||o.d.code)) || 'Lỗi tạo vé.'; err.hidden=false; return; }
					hienQR(o.d);
				})
				.catch(function(){ btn.disabled=false; btn.textContent='Tạo mã thanh toán'; err.textContent='Lỗi kết nối máy chủ.'; err.hidden=false; });
			});

			function hienQR(v){
				mask.hidden = false;   // mở popup (dùng cho cả form đặt nhanh)
				qs('.pve-r-mave').textContent = v.ma_ve;
				qs('.pve-r-goi').textContent  = v.goi_ten;
				qs('.pve-r-tien').textContent = tien(v.so_tien);
				qs('.pve-r-nh').textContent   = (v.bank&&v.bank.ten_nh)||'';
				qs('.pve-r-stk').textContent  = (v.bank&&v.bank.so_tk)||'';
				qs('.pve-r-ctk').textContent  = (v.bank&&v.bank.ten_tk)||'';
				qs('.pve-r-nd').textContent   = v.noi_dung;
				var box = qs('.pve-qr'); box.innerHTML='';
				loadQR(function(loi){
					if(loi){ box.textContent='(Không tải được mã QR — dùng nội dung CK bên dưới)'; return; }
					new QRCode(box, { text: v.qr, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
				});
				show('qr');
				var badge = qs('.pve-badge');
				qs('.pve-copy').onclick = function(){ try{ navigator.clipboard.writeText(v.noi_dung); }catch(e){} };
				if(timer) clearInterval(timer);
				timer = setInterval(function(){
					fetch(REST+'/ve/trangthai?ma_ve='+encodeURIComponent(v.ma_ve)).then(function(r){return r.json();}).then(function(d){
						if(d && d.trang_thai==='da_tt'){ badge.className='pve-badge da_tt'; badge.textContent='✅ Đã thanh toán'; clearInterval(timer); timer=null; }
						else if(d && d.trang_thai==='huy'){ badge.className='pve-badge huy'; badge.textContent='✖ Đã huỷ'; clearInterval(timer); timer=null; }
					}).catch(function(){});
				}, 5000);
			}
		})();
		</script>

		<style>
		/* Full-bleed: phá khung theme để trang trải hết chiều ngang */
		.pve-page{ width:100vw; margin-left:calc(50% - 50vw); background:#faf6ee; color:#1f2937; overflow:hidden; }
		.pve-hero{ background:linear-gradient(135deg,#e6b32e,#c1901b); background-size:cover; background-position:center; padding:56px 20px 60px; text-align:center; }
		.pve-hero-in{ max-width:760px; margin:0 auto; }
		.pve-hero-t{ color:#fff; font-size:clamp(26px,5vw,44px); font-weight:900; margin:0 0 12px; text-shadow:0 2px 12px rgba(0,0,0,.25); line-height:1.15; }
		.pve-hero-p{ color:#fff; opacity:.95; font-size:clamp(14px,2.4vw,18px); margin:0 0 22px; text-shadow:0 1px 6px rgba(0,0,0,.25); }
		.pve-hero-btn{ display:inline-block; background:#1f2937; color:#fff; font-weight:800; font-size:16px; padding:13px 30px; border-radius:999px; text-decoration:none; box-shadow:0 6px 18px rgba(0,0,0,.2); }
		.pve-hero-btn:hover{ background:#111827; color:#fff; }
		.pve-wrap{ max-width:1040px; margin:0 auto; padding:26px 16px 40px; }
		.pve-title{ font-size:22px; font-weight:800; margin:6px 0 14px; }
		.pve-empty{ color:#64748b; }
		.pve-sec{ margin-bottom:30px; }
		.pve-sec-h{ font-size:22px; font-weight:900; color:#1f2937; margin:0 0 14px; padding-left:12px; border-left:5px solid #cf9f22; }
		.pve-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; }
		.pve-card{ background:#fff; border:1px solid #eee; border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 4px 14px rgba(0,0,0,.06); }
		.pve-img{ position:relative; aspect-ratio:1/1; background:#f1f5f9; }
		.pve-img img{ width:100%; height:100%; object-fit:cover; }
		.pve-noimg{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:44px; }
		.pve-sale{ position:absolute; top:0; left:0; background:#cf9f22; color:#fff; font-weight:800; font-size:13px; padding:4px 10px; border-bottom-right-radius:12px; }
		.pve-body{ padding:11px 12px 12px; display:flex; flex-direction:column; gap:5px; flex:1; }
		.pve-ten{ font-weight:700; font-size:15px; color:#1f2937; line-height:1.3; }
		.pve-tl{ color:#64748b; font-size:12px; }
		.pve-mota{ color:#64748b; font-size:12px; line-height:1.4; }
		.pve-foot{ display:flex; justify-content:space-between; align-items:flex-end; margin-top:auto; padding-top:6px; }
		.pve-gia{ font-size:18px; font-weight:900; color:#c2410c; }
		.pve-goc{ font-size:12px; color:#9ca3af; text-decoration:line-through; margin-left:6px; }
		.pve-buy{ border:none; background:#cf9f22; color:#fff; font-weight:700; font-size:13px; padding:9px 14px; border-radius:999px; cursor:pointer; }
		.pve-buy[disabled]{ background:#cbd5e1; cursor:not-allowed; }
		.pve-con{ font-size:12px; color:#64748b; font-weight:600; }
		.pve-het{ opacity:.72; } .pve-het .pve-con{ color:#991b1b; }
		.pve-mask[hidden], .pve-wel[hidden]{ display:none !important; }   /* [hidden] phải thắng display:flex */
		.pve-mask{ position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; padding:16px; z-index:99999; }
		.pve-modal{ background:#fff; border-radius:18px; padding:20px; width:100%; max-width:380px; max-height:90vh; overflow:auto; position:relative; }
		.pve-x{ position:absolute; top:10px; right:12px; border:none; background:none; font-size:26px; line-height:1; color:#94a3b8; cursor:pointer; }
		.pve-m-ten{ font-weight:800; font-size:18px; color:#1f2937; }
		.pve-m-gia{ font-weight:900; font-size:20px; color:#c2410c; margin:2px 0 14px; }
		.pve-lb{ display:block; font-size:13px; color:#475569; margin:10px 0 4px; font-weight:600; }
		.pve-in{ width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:10px; padding:11px 13px; font-size:15px; }
		.pve-go{ width:100%; margin-top:16px; border:none; background:#cf9f22; color:#fff; font-weight:800; font-size:16px; padding:13px; border-radius:12px; cursor:pointer; }
		.pve-err{ color:#b91c1c; font-size:13px; margin-top:10px; }
		.pve-note{ color:#64748b; font-size:12px; line-height:1.5; margin-top:12px; }
		.pve-qr{ display:flex; justify-content:center; margin:6px 0 14px; }
		.pve-qr img,.pve-qr canvas{ display:block; }
		.pve-badge{ display:inline-block; font-weight:800; padding:6px 14px; border-radius:999px; font-size:14px; margin-bottom:12px; }
		.pve-badge.cho{ background:#fef3c7; color:#92600a; } .pve-badge.da_tt{ background:#dcfce7; color:#166534; } .pve-badge.huy{ background:#fee2e2; color:#991b1b; }
		.pve-kv{ display:flex; justify-content:space-between; gap:10px; font-size:14px; padding:6px 0; border-bottom:1px dashed #e2e8f0; }
		.pve-kv b{ color:#1f2937; text-align:right; word-break:break-all; }
		.pve-copy{ cursor:pointer; } .pve-copy em{ color:#94a3b8; font-size:11px; font-style:normal; }
		/* Màn chào mừng chọn khu vực */
		.pve-wel{ position:fixed; inset:0; background:rgba(15,23,42,.72); display:flex; align-items:center; justify-content:center; padding:16px; z-index:100000; }
		.pve-wel-box{ background:#fff; border-radius:18px; padding:26px 22px; width:100%; max-width:440px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.35); }
		.pve-wel-h{ font-size:20px; font-weight:900; color:#1f2937; margin-bottom:18px; }
		.pve-wel-list{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
		.pve-wel-kv{ border:2px solid #e2e8f0; background:#fff; color:#1f2937; font-weight:700; font-size:15px; padding:14px 10px; border-radius:12px; cursor:pointer; transition:.15s; }
		.pve-wel-kv:hover{ border-color:#cf9f22; }
		.pve-wel-kv.on{ border-color:#cf9f22; background:#fcf3d9; color:#8a6a10; }
		.pve-wel-ok{ width:100%; border:none; background:#cf9f22; color:#fff; font-weight:800; font-size:16px; padding:14px; border-radius:12px; cursor:pointer; }
		.pve-wel-ok:disabled{ background:#cbd5e1; cursor:not-allowed; }
		.pve-wel-note{ color:#94a3b8; font-size:12px; margin-top:12px; }
		.pve-kvbar{ background:#fff; border:1px solid #f0e7d2; border-radius:12px; padding:9px 14px; margin-bottom:16px; font-size:14px; color:#475569; }
		.pve-kvbar b{ color:#1f2937; }
		.pve-kvbar-doi{ float:right; color:#b8871a; font-weight:700; text-decoration:none; }
		/* Form đặt vé nhanh */
		.pve-qf{ background:#fff; border:1px solid #f0e7d2; border-radius:16px; padding:16px; margin:0 0 20px; box-shadow:0 6px 18px rgba(0,0,0,.07); }
		.pve-qf-h{ font-weight:900; font-size:18px; color:#1f2937; margin-bottom:12px; }
		.pve-qf-grid{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
		.pve-qf-grid input, .pve-qf-grid select{ width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:10px; padding:11px 12px; font-size:14px; background:#fff; }
		.pve-qf-ve{ grid-column:1 / -1; }
		.pve-qf-sl{ max-width:100%; }
		.pve-qf-go{ width:100%; margin-top:12px; border:none; background:#cf9f22; color:#fff; font-weight:800; font-size:16px; padding:13px; border-radius:12px; cursor:pointer; }
		.pve-qf-err{ color:#b91c1c; font-size:13px; margin-top:10px; }
		.pve-qf-note{ color:#94a3b8; font-size:12px; text-align:center; margin-top:10px; }
		@media(max-width:520px){ .pve-qf-grid{ grid-template-columns:1fr; } }
		</style>
		<?php
		return ob_get_clean();
	}

	// ───────────────────────────── Admin (menu top-level riêng) ─────────────────────────────
	public static function admin_menu() {
		add_menu_page( 'Vé khu vui chơi', 'Vé khu vui chơi', 'manage_options', 'posh-ve',
			array( __CLASS__, 'trang_admin' ), 'dashicons-tickets-alt', 27 );
	}
	public static function trang_admin() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb; wp_enqueue_media();

		if ( isset( $_POST['pve_bank'] ) && check_admin_referer( 'pve_bank' ) ) {
			update_option( 'pve_bin', preg_replace( '/\D+/', '', (string) $_POST['bin'] ) );
			update_option( 'pve_so_tk', sanitize_text_field( wp_unslash( $_POST['so_tk'] ) ) );
			update_option( 'pve_ten_tk', sanitize_text_field( wp_unslash( $_POST['ten_tk'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu tài khoản.</p></div>';
		}
		if ( isset( $_POST['pve_luu'] ) && check_admin_referer( 'pve_luu' ) ) {
			$id = (int) $_POST['id'];
			$moi = array( 'id' => $id, 'ten' => sanitize_text_field( wp_unslash( $_POST['ten'] ) ),
				'gia' => (int) preg_replace( '/\D+/', '', (string) $_POST['gia'] ),
				'gia_goc' => (int) preg_replace( '/\D+/', '', (string) ( isset( $_POST['gia_goc'] ) ? $_POST['gia_goc'] : '' ) ),
				'nhom' => sanitize_text_field( wp_unslash( isset( $_POST['nhom'] ) ? $_POST['nhom'] : '' ) ),
				'khu_vuc' => sanitize_text_field( wp_unslash( isset( $_POST['khu_vuc'] ) ? $_POST['khu_vuc'] : '' ) ),
				'mo_ta' => sanitize_textarea_field( wp_unslash( $_POST['mo_ta'] ) ),
				'anh' => esc_url_raw( wp_unslash( $_POST['anh'] ) ),
				'thoi_luong' => sanitize_text_field( wp_unslash( $_POST['thoi_luong'] ) ),
				'so_luong' => ( ! isset( $_POST['so_luong'] ) || '' === trim( (string) $_POST['so_luong'] ) ) ? -1 : max( 0, (int) preg_replace( '/\D+/', '', (string) $_POST['so_luong'] ) ),
				'hien' => empty( $_POST['hien'] ) ? 0 : 1 );
			$ds = self::ds_tatca(); $thay = false;
			if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
			if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }
			self::luu_ds( $ds );
			echo '<div class="notice notice-success"><p>Đã lưu dịch vụ.</p></div>';
		}
		if ( isset( $_POST['pve_xoa'] ) && check_admin_referer( 'pve_xoa' ) ) {
			$id = (int) $_POST['id'];
			self::luu_ds( array_filter( self::ds_tatca(), function ( $v ) use ( $id ) { return (int) $v['id'] !== $id; } ) );
			echo '<div class="notice notice-success"><p>Đã xoá dịch vụ.</p></div>';
		}
		if ( isset( $_POST['pve_tt'] ) && check_admin_referer( 'pve_tt' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $_POST['ma_ve'] ) );
			$xin = sanitize_key( (string) $_POST['pve_tt'] );
			$hople = array( 'cho', 'da_tt', 'da_dung', 'huy' );
			$tt = in_array( $xin, $hople, true ) ? $xin : 'da_tt';
			$wpdb->update( self::tbl(), array( 'trang_thai' => $tt, 'tt_luc' => current_time( 'mysql' ) ), array( 'ma_ve' => $ma ) );
			$bs = '';
			if ( 'da_tt' === $tt ) {
				$r = $wpdb->get_row( $wpdb->prepare( 'SELECT sdt, ten_khach, so_tien, da_cong_diem FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
				if ( $r && ! (int) $r['da_cong_diem'] ) {
					self::cong_diem( $r['sdt'], $r['ten_khach'], $r['so_tien'] );
					$wpdb->update( self::tbl(), array( 'da_cong_diem' => 1 ), array( 'ma_ve' => $ma ) );
					$bs = ' (+' . number_format( (int) floor( (int) $r['so_tien'] / 1000 ), 0, ',', '.' ) . ' điểm cho ' . esc_html( $r['sdt'] ) . ')';
				}
			}
			$nh = array( 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'da_dung' => 'Đã dùng (đã soát)', 'huy' => 'Đã huỷ' );
			echo '<div class="notice notice-success"><p>Vé <b>' . esc_html( $ma ) . '</b> → ' . esc_html( $nh[ $tt ] ) . $bs . '.</p></div>';
		}

		if ( isset( $_POST['pve_hang'] ) && check_admin_referer( 'pve_hang' ) ) {
			$tens = isset( $_POST['hang_ten'] ) ? (array) $_POST['hang_ten'] : array();
			$mocs = isset( $_POST['hang_moc'] ) ? (array) $_POST['hang_moc'] : array();
			$moi = array();
			foreach ( $tens as $i => $t ) {
				$t = sanitize_text_field( wp_unslash( $t ) );
				if ( '' === trim( $t ) ) { continue; }
				$moi[] = array( 'ten' => $t, 'moc' => max( 0, (int) preg_replace( '/\D+/', '', (string) ( isset( $mocs[ $i ] ) ? $mocs[ $i ] : 0 ) ) ) );
			}
			update_option( 'pve_hang', $moi );
			echo '<div class="notice notice-success"><p>Đã lưu hạng thành viên.</p></div>';
		}
		if ( isset( $_POST['pve_coso_luu'] ) && check_admin_referer( 'pve_coso' ) ) {
			$tens = isset( $_POST['cs_ten'] ) ? (array) $_POST['cs_ten'] : array();
			$lats = isset( $_POST['cs_lat'] ) ? (array) $_POST['cs_lat'] : array();
			$lngs = isset( $_POST['cs_lng'] ) ? (array) $_POST['cs_lng'] : array();
			$moi = array();
			foreach ( $tens as $i => $t ) {
				$t = sanitize_text_field( wp_unslash( $t ) );
				if ( '' === trim( $t ) ) { continue; }
				$moi[] = array( 'ten' => $t,
					'lat' => (float) ( isset( $lats[ $i ] ) ? str_replace( ',', '.', (string) $lats[ $i ] ) : 0 ),
					'lng' => (float) ( isset( $lngs[ $i ] ) ? str_replace( ',', '.', (string) $lngs[ $i ] ) : 0 ) );
			}
			update_option( 'pve_coso', $moi );
			echo '<div class="notice notice-success"><p>Đã lưu cơ sở &amp; toạ độ.</p></div>';
		}
		if ( isset( $_POST['pve_pin_luu'] ) && check_admin_referer( 'pve_pin' ) ) {
			update_option( 'pve_pin', preg_replace( '/\s+/', '', (string) wp_unslash( $_POST['pin'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu PIN khu quản lý.</p></div>';
		}
		if ( isset( $_POST['pve_zalo_luu'] ) && check_admin_referer( 'pve_zalo' ) ) {
			update_option( 'pve_zalo_secret', trim( (string) wp_unslash( $_POST['zalo_secret'] ) ) );
			echo '<div class="notice notice-success"><p>Đã lưu Zalo Secret Key.</p></div>';
		}
		if ( isset( $_POST['pve_uu_luu'] ) && check_admin_referer( 'pve_uu' ) ) {
			$id = (int) $_POST['id'];
			$moi = array( 'id' => $id, 'ten' => sanitize_text_field( wp_unslash( $_POST['ten'] ) ),
				'mo_ta' => sanitize_textarea_field( wp_unslash( $_POST['mo_ta'] ) ),
				'anh' => esc_url_raw( wp_unslash( $_POST['anh'] ) ),
				'hang' => sanitize_text_field( wp_unslash( isset( $_POST['hang'] ) ? $_POST['hang'] : '' ) ),
				'han' => sanitize_text_field( wp_unslash( isset( $_POST['han'] ) ? $_POST['han'] : '' ) ),
				'hien' => empty( $_POST['hien'] ) ? 0 : 1 );
			$ds = self::ds_uudai_tatca(); $thay = false;
			if ( $id > 0 ) { foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } } }
			if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }
			self::luu_uudai( $ds );
			echo '<div class="notice notice-success"><p>Đã lưu ưu đãi.</p></div>';
		}
		if ( isset( $_POST['pve_uu_xoa'] ) && check_admin_referer( 'pve_uu_xoa' ) ) {
			$id = (int) $_POST['id'];
			self::luu_uudai( array_filter( self::ds_uudai_tatca(), function ( $v ) use ( $id ) { return (int) $v['id'] !== $id; } ) );
			echo '<div class="notice notice-success"><p>Đã xoá ưu đãi.</p></div>';
		}

		$b = self::bank(); $ds = self::ds_tatca();
		$url = esc_url( home_url( '/wp-json/' . self::NS . '/ve/goi' ) );
		$sua = null; $sid = isset( $_GET['sua'] ) ? (int) $_GET['sua'] : 0;
		if ( $sid ) { foreach ( $ds as $v ) { if ( (int) $v['id'] === $sid ) { $sua = $v; break; } } }

		$page_id   = self::bao_dam_trang();
		$page_link = $page_id ? get_permalink( $page_id ) : '';
		$page_edit = $page_id ? get_edit_post_link( $page_id ) : '';

		echo '<div class="wrap"><h1>Vé khu vui chơi</h1>';
		if ( $page_link ) {
			echo '<div class="notice notice-info inline" style="margin:10px 0"><p><b>Trang bán vé trên web đã sẵn:</b> '
				. '<a href="' . esc_url( $page_link ) . '" target="_blank">' . esc_html( $page_link ) . '</a> '
				. '&nbsp; <a class="button button-small" href="' . esc_url( $page_link ) . '" target="_blank">Mở trang</a> '
				. '<a class="button button-small" href="' . esc_url( $page_edit ) . '">Sửa (đổi tên/đường dẫn)</a></p></div>';
		}
		echo '<p>API cho Zalo Mini App: <code>' . $url . '</code> · Muốn thêm trang bán khác: dán shortcode <code>[posh_ve]</code> vào trang bất kỳ.</p>';

		/* ── Tổng quan ── */
		$tbl   = self::tbl();
		$paid  = "trang_thai IN ('da_tt','da_dung')";
		$hnay  = current_time( 'Y-m-d' );
		$thang = current_time( 'Y-m' );
		$dt_hnay  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE(tao_luc)=%s", $hnay ) );
		$dt_thang = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND DATE_FORMAT(tao_luc,'%%Y-%%m')=%s", $thang ) );
		$sl_ban   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid" );
		$sl_cho   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE trang_thai='cho'" );
		$cards = array(
			array( 'Doanh thu hôm nay', number_format( $dt_hnay, 0, ',', '.' ) . 'đ', '#166534' ),
			array( 'Doanh thu tháng này', number_format( $dt_thang, 0, ',', '.' ) . 'đ', '#1d4ed8' ),
			array( 'Vé đã bán', number_format( $sl_ban, 0, ',', '.' ), '#0f172a' ),
			array( 'Vé đang chờ TT', number_format( $sl_cho, 0, ',', '.' ), '#92600a' ),
		);
		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0 6px">';
		foreach ( $cards as $c ) {
			echo '<div style="flex:1;min-width:170px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(0,0,0,.04)">'
				. '<div style="color:#64748b;font-size:13px">' . esc_html( $c[0] ) . '</div>'
				. '<div style="font-size:24px;font-weight:800;color:' . esc_attr( $c[2] ) . ';margin-top:4px">' . esc_html( $c[1] ) . '</div></div>';
		}
		echo '</div>';
		$top = $wpdb->get_results( "SELECT dv_ten, COUNT(*) sl, COALESCE(SUM(so_tien),0) dt FROM $tbl WHERE $paid GROUP BY dv_ten ORDER BY sl DESC LIMIT 5", ARRAY_A );
		if ( $top ) {
			echo '<p style="margin:12px 0 4px"><b>Vé bán chạy</b></p><table class="widefat striped" style="max-width:640px"><thead><tr><th>Dịch vụ</th><th style="width:110px">Đã bán</th><th style="width:150px">Doanh thu</th></tr></thead><tbody>';
			foreach ( $top as $t ) {
				echo '<tr><td>' . esc_html( $t['dv_ten'] ) . '</td><td>' . (int) $t['sl'] . '</td><td>' . esc_html( number_format( $t['dt'], 0, ',', '.' ) ) . 'đ</td></tr>';
			}
			echo '</tbody></table>';
		}

		// Tách theo kênh bán (Zalo / Web)
		$ve_zalo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid AND nguon='zalo'" );
		$ve_web  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE $paid AND nguon<>'zalo'" );
		$dt_zalo = (int) $wpdb->get_var( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND nguon='zalo'" );
		$dt_web  = (int) $wpdb->get_var( "SELECT COALESCE(SUM(so_tien),0) FROM $tbl WHERE $paid AND nguon<>'zalo'" );
		echo '<p style="margin:14px 0 4px"><b>Bán theo kênh</b></p>';
		echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>Kênh</th><th style="width:120px">Vé đã bán</th><th style="width:160px">Doanh thu</th></tr></thead><tbody>';
		echo '<tr><td>📱 Zalo Mini App</td><td>' . $ve_zalo . '</td><td>' . esc_html( number_format( $dt_zalo, 0, ',', '.' ) ) . 'đ</td></tr>';
		echo '<tr><td>🌐 Web (khmatrix.com)</td><td>' . $ve_web . '</td><td>' . esc_html( number_format( $dt_web, 0, ',', '.' ) ) . 'đ</td></tr>';
		echo '</tbody></table>';
		echo '<hr>';

		/* Tài khoản nhận tiền */
		echo '<h2>Tài khoản nhận tiền</h2><form method="post"><table class="form-table">';
		wp_nonce_field( 'pve_bank' );
		echo '<tr><th>Số tài khoản</th><td><input name="so_tk" class="regular-text code" value="' . esc_attr( get_option( 'pve_so_tk', '' ) ) . '"></td></tr>';
		echo '<tr><th>BIN ngân hàng</th><td><input name="bin" class="regular-text code" value="' . esc_attr( get_option( 'pve_bin', '' ) ) . '" placeholder="VD 970415 = VietinBank"></td></tr>';
		echo '<tr><th>Chủ tài khoản</th><td><input name="ten_tk" class="regular-text" value="' . esc_attr( get_option( 'pve_ten_tk', '' ) ) . '"></td></tr>';
		echo '</table><p class="description">Để trống cả 3 ô = tự dùng lại tài khoản đã khai ở plugin ghế. Đang dùng: <b>'
			. ( $b['so_tk'] ? esc_html( $b['so_tk'] . ' · ' . $b['ten_nh'] . ' · ' . $b['ten_tk'] ) : 'CHƯA CÓ — khách sẽ không tạo được QR' ) . '</b></p>';
		echo '<p><button class="button button-primary" name="pve_bank" value="1">Lưu tài khoản</button></p></form><hr>';

		/* Bảng dịch vụ */
		echo '<h2>Danh sách dịch vụ</h2><table class="widefat striped"><thead><tr><th style="width:70px">Ảnh</th>'
			. '<th>Tên</th><th>Nhóm</th><th>Giá</th><th>Còn</th><th>Thời lượng</th><th>Hiện</th><th>Thao tác</th></tr></thead><tbody>';
		foreach ( $ds as $v ) {
			$gia_html = esc_html( number_format( $v['gia'], 0, ',', '.' ) ) . 'đ';
			if ( $v['gia_goc'] > $v['gia'] ) { $gia_html .= ' <s style="color:#999">' . esc_html( number_format( $v['gia_goc'], 0, ',', '.' ) ) . 'đ</s>'; }
			echo '<tr><td>' . ( $v['anh'] ? '<img src="' . esc_url( $v['anh'] ) . '" style="width:56px;height:56px;object-fit:cover;border-radius:8px">' : '—' ) . '</td>'
				. '<td><b>' . esc_html( $v['ten'] ) . '</b>' . ( $v['mo_ta'] !== '' ? '<br><small>' . esc_html( $v['mo_ta'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( $v['nhom'] ) . ( $v['khu_vuc'] !== '' ? '<br><small>📍 ' . esc_html( $v['khu_vuc'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . $gia_html . '</td>'
				. '<td>' . ( $v['so_luong'] < 0 ? '∞' : ( $v['so_luong'] > 0 ? (int) $v['so_luong'] : '<b style="color:#991b1b">Hết</b>' ) ) . '</td>'
				. '<td>' . esc_html( $v['thoi_luong'] ) . '</td><td>' . ( $v['hien'] ? '✅' : '⛔' ) . '</td><td>'
				. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve&sua=' . $v['id'] ) ) . '">Sửa</a> '
				. '<form method="post" style="display:inline" onsubmit="return confirm(\'Xoá?\')">';
			wp_nonce_field( 'pve_xoa' );
			echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '"><button class="button" name="pve_xoa" value="1">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* Form thêm/sửa */
		echo '<h2>' . ( $sua ? 'Sửa dịch vụ' : 'Thêm dịch vụ' ) . '</h2><form method="post">';
		wp_nonce_field( 'pve_luu' );
		echo '<input type="hidden" name="id" value="' . (int) ( $sua ? $sua['id'] : 0 ) . '"><table class="form-table">';
		echo '<tr><th>Tên</th><td><input name="ten" class="regular-text" required value="' . esc_attr( $sua ? $sua['ten'] : '' ) . '"></td></tr>';
		echo '<tr><th>Giá bán (đ)</th><td><input name="gia" type="number" min="1000" step="1000" required value="' . esc_attr( $sua ? $sua['gia'] : '' ) . '"></td></tr>';
		echo '<tr><th>Giá gốc (đ)</th><td><input name="gia_goc" type="number" min="0" step="1000" value="' . esc_attr( $sua ? $sua['gia_goc'] : '' ) . '"> <span class="description">để 0 hoặc trống nếu không giảm giá. Lớn hơn giá bán → hiện badge % + giá gạch.</span></td></tr>';
		echo '<tr><th>Nhóm</th><td><input name="nhom" class="regular-text" placeholder="VD Vé lẻ / Combo / Funzone Aeon…" value="' . esc_attr( $sua ? $sua['nhom'] : '' ) . '"> <span class="description">gom các vé cùng nhóm thành một hàng trên app.</span></td></tr>';
		echo '<tr><th>Khu vực / Cơ sở</th><td><input name="khu_vuc" class="regular-text" placeholder="VD Hà Nội / Hồ Chí Minh / Aeon Long Biên…" value="' . esc_attr( $sua ? $sua['khu_vuc'] : '' ) . '"> <span class="description">Khách sẽ chọn khu vực khi vào trang bán; để <b>trống</b> = hiện ở mọi khu vực.</span></td></tr>';
		echo '<tr><th>Số lượng vé</th><td><input name="so_luong" type="number" min="0" step="1" value="' . esc_attr( $sua && $sua['so_luong'] >= 0 ? $sua['so_luong'] : '' ) . '"> <span class="description">số vé còn bán — để <b>trống</b> = không giới hạn. Mỗi vé bán (web/Zalo) tự trừ 1.</span></td></tr>';
		echo '<tr><th>Thời lượng</th><td><input name="thoi_luong" class="regular-text" placeholder="VD 60 phút / Cả ngày" value="' . esc_attr( $sua ? $sua['thoi_luong'] : '' ) . '"></td></tr>';
		echo '<tr><th>Mô tả</th><td><textarea name="mo_ta" rows="3" class="large-text">' . esc_textarea( $sua ? $sua['mo_ta'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th>Ảnh</th><td><input type="text" name="anh" id="pve-anh" class="large-text code" value="' . esc_attr( $sua ? $sua['anh'] : '' ) . '"><br>'
			. '<button type="button" class="button" id="pve-chon" style="margin-top:6px">Chọn ảnh từ thư viện</button> '
			. '<img id="pve-xem" src="' . esc_url( $sua ? $sua['anh'] : '' ) . '" style="' . ( $sua && $sua['anh'] ? '' : 'display:none;' ) . 'height:80px;border-radius:8px;margin-left:10px;vertical-align:middle"></td></tr>';
		echo '<tr><th>Hiện</th><td><label><input type="checkbox" name="hien" value="1" ' . checked( $sua ? $sua['hien'] : 1, 1, false ) . '> Cho hiện trên app</label></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_luu" value="1">' . ( $sua ? 'Lưu thay đổi' : 'Thêm dịch vụ' ) . '</button>'
			. ( $sua ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Huỷ</a>' : '' ) . '</p></form>';
		echo '<script>jQuery(function($){var f;$("#pve-chon").on("click",function(e){e.preventDefault();'
			. 'if(f){f.open();return;}f=wp.media({title:"Chọn ảnh",multiple:false,library:{type:"image"}});'
			. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#pve-anh").val(a.url);$("#pve-xem").attr("src",a.url).show();});f.open();});});</script>';

		/* ── Đơn vé (bộ lọc trạng thái + tìm + soát vé) ── */
		echo '<hr><h2>Đơn vé</h2>';

		// Soát vé nhanh: nhập/quét mã -> lọc đúng vé đó.
		echo '<form method="get" style="margin:6px 0 12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
			. '<input type="hidden" name="page" value="posh-ve">'
			. '<input type="text" name="tim" value="' . esc_attr( isset( $_GET['tim'] ) ? wp_unslash( $_GET['tim'] ) : '' ) . '" placeholder="Soát vé: nhập mã vé / SĐT" class="regular-text code" style="max-width:280px">'
			. '<button class="button button-primary">Tìm / Soát vé</button>';
		if ( isset( $_GET['tim'] ) && '' !== trim( (string) $_GET['tim'] ) ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Xoá lọc</a>';
		}
		echo '</form>';

		$nhan = array( 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'da_dung' => 'Đã dùng', 'huy' => 'Đã huỷ' );
		$loc  = isset( $_GET['loc'] ) ? sanitize_key( $_GET['loc'] ) : '';
		$tim  = isset( $_GET['tim'] ) ? trim( (string) wp_unslash( $_GET['tim'] ) ) : '';

		// Tabs lọc trạng thái (đếm nhanh).
		$tabs = array( '' => 'Tất cả', 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'da_dung' => 'Đã dùng', 'huy' => 'Đã huỷ' );
		echo '<h2 class="nav-tab-wrapper" style="margin-bottom:12px">';
		foreach ( $tabs as $k => $ten ) {
			$dem = '' === $k ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl" )
				: (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl WHERE trang_thai=%s", $k ) );
			$au  = add_query_arg( array( 'page' => 'posh-ve', 'loc' => $k ), admin_url( 'admin.php' ) );
			echo '<a class="nav-tab' . ( $loc === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $au ) . '">' . esc_html( $ten ) . ' (' . $dem . ')</a>';
		}
		echo '</h2>';

		// Truy vấn có lọc + tìm.
		$where = array(); $args = array();
		if ( '' !== $loc ) { $where[] = 'trang_thai=%s'; $args[] = $loc; }
		if ( '' !== $tim ) {
			$like = '%' . $wpdb->esc_like( $tim ) . '%';
			$where[] = '(ma_ve LIKE %s OR sdt LIKE %s OR ten_khach LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		$sql = "SELECT ma_ve, dv_ten, so_tien, ten_khach, sdt, trang_thai, nguon, tao_luc, tt_luc FROM $tbl";
		if ( $where ) { $sql .= ' WHERE ' . implode( ' AND ', $where ); }
		$sql .= ' ORDER BY id DESC LIMIT 100';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		if ( ! $rows ) { echo '<p>Không có vé nào khớp.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Mã vé</th><th>Dịch vụ</th><th>Kênh</th><th>Số tiền</th><th>Khách</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$mau = array( 'cho' => '#92600a', 'da_tt' => '#166534', 'da_dung' => '#1d4ed8', 'huy' => '#991b1b' );
				$c   = isset( $mau[ $r['trang_thai'] ] ) ? $mau[ $r['trang_thai'] ] : '#334155';
				$kenh = ( 'zalo' === $r['nguon'] ) ? '📱 Zalo' : '🌐 Web';
				echo '<tr><td>' . esc_html( $r['tao_luc'] ) . '</td><td><code>' . esc_html( $r['ma_ve'] ) . '</code></td><td>' . esc_html( $r['dv_ten'] ) . '</td>'
					. '<td>' . $kenh . '</td>'
					. '<td>' . esc_html( number_format( $r['so_tien'], 0, ',', '.' ) ) . 'đ</td>'
					. '<td>' . esc_html( $r['ten_khach'] ) . '<br><small>' . esc_html( $r['sdt'] ) . '</small></td>'
					. '<td><b style="color:' . esc_attr( $c ) . '">' . esc_html( isset( $nhan[ $r['trang_thai'] ] ) ? $nhan[ $r['trang_thai'] ] : $r['trang_thai'] ) . '</b></td><td>';
				echo '<form method="post" style="display:inline">'; wp_nonce_field( 'pve_tt' );
				echo '<input type="hidden" name="ma_ve" value="' . esc_attr( $r['ma_ve'] ) . '">';
				if ( 'cho' === $r['trang_thai'] ) {
					echo '<button class="button button-primary" name="pve_tt" value="da_tt">Đã thanh toán</button> '
						. '<button class="button" name="pve_tt" value="huy">Huỷ</button>';
				} elseif ( 'da_tt' === $r['trang_thai'] ) {
					echo '<button class="button button-primary" name="pve_tt" value="da_dung">Đã dùng (soát)</button> '
						. '<button class="button" name="pve_tt" value="huy">Huỷ</button>';
				} else { echo '—'; }
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">“Đã dùng (soát)” dùng khi khách vào cổng. Bộ lọc + ô soát vé giúp tìm nhanh 1 vé bằng mã hoặc SĐT.</p>';
		}

		/* ── Thành viên & tích điểm ── */
		echo '<hr><h2>Thành viên &amp; tích điểm</h2>';
		echo '<p class="description">Quy đổi: <b>1 điểm = 1.000đ</b> chi tiêu. Điểm cộng tự động khi bấm “Đã thanh toán” cho vé.</p>';

		// Cấu hình hạng.
		$hang = self::ds_hang();
		echo '<h3>Hạng thành viên (theo điểm)</h3><form method="post">'; wp_nonce_field( 'pve_hang' );
		echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>Tên hạng</th><th style="width:200px">Điểm tối thiểu</th></tr></thead><tbody>';
		$rows_hang = $hang; for ( $i = count( $rows_hang ); $i < 6; $i++ ) { $rows_hang[] = array( 'ten' => '', 'moc' => '' ); }
		foreach ( $rows_hang as $h ) {
			echo '<tr><td><input name="hang_ten[]" class="regular-text" value="' . esc_attr( $h['ten'] ) . '" placeholder="VD Bạc / Vàng / Kim cương"></td>'
				. '<td><input name="hang_moc[]" type="number" min="0" step="100" value="' . esc_attr( '' === $h['moc'] ? '' : (int) $h['moc'] ) . '"></td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="pve_hang" value="1">Lưu hạng</button> <span class="description">Bỏ trống tên = xoá hạng đó.</span></p></form>';

		// Danh sách thành viên.
		$tvs = $wpdb->get_results( 'SELECT sdt, ten, diem, tong_chi, so_don, sua_luc FROM ' . self::tbl_tv() . ' ORDER BY diem DESC LIMIT 100', ARRAY_A );
		echo '<h3>Khách tích điểm</h3>';
		if ( ! $tvs ) { echo '<p>Chưa có khách nào tích điểm. (Điểm cộng khi vé chuyển sang “Đã thanh toán”.)</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>SĐT</th><th>Tên</th><th>Điểm</th><th>Hạng</th><th>Tổng chi</th><th>Số đơn</th><th>Cập nhật</th></tr></thead><tbody>';
			foreach ( $tvs as $t ) {
				$hc = self::hang_cua( (int) $t['diem'] );
				echo '<tr><td><code>' . esc_html( $t['sdt'] ) . '</code></td><td>' . esc_html( $t['ten'] ) . '</td>'
					. '<td><b>' . esc_html( number_format( $t['diem'], 0, ',', '.' ) ) . '</b></td>'
					. '<td>' . esc_html( $hc['hang'] ) . '</td>'
					. '<td>' . esc_html( number_format( $t['tong_chi'], 0, ',', '.' ) ) . 'đ</td>'
					. '<td>' . (int) $t['so_don'] . '</td><td>' . esc_html( $t['sua_luc'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		/* ── Cơ sở & toạ độ (gợi ý theo định vị) ── */
		echo '<hr><h2>Cơ sở &amp; toạ độ (gợi ý vé theo định vị)</h2>';
		echo '<p class="description">Tên cơ sở phải khớp <b>đúng</b> với ô “Khu vực / Cơ sở” của vé. Toạ độ lấy từ Google Maps: chuột phải điểm cần → bấm cặp số để copy (dạng <code>10.776,106.700</code>).</p>';
		$coso = self::ds_coso();
		echo '<form method="post">'; wp_nonce_field( 'pve_coso' );
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>Tên cơ sở / khu vực</th><th style="width:170px">Vĩ độ (lat)</th><th style="width:170px">Kinh độ (lng)</th></tr></thead><tbody>';
		$rows_cs = $coso; for ( $i = count( $rows_cs ); $i < 8; $i++ ) { $rows_cs[] = array( 'ten' => '', 'lat' => '', 'lng' => '' ); }
		foreach ( $rows_cs as $c ) {
			$lat = ( is_numeric( $c['lat'] ) && $c['lat'] ) ? $c['lat'] : '';
			$lng = ( is_numeric( $c['lng'] ) && $c['lng'] ) ? $c['lng'] : '';
			echo '<tr><td><input name="cs_ten[]" class="regular-text" value="' . esc_attr( $c['ten'] ) . '" placeholder="VD Hà Nội / Hồ Chí Minh"></td>'
				. '<td><input name="cs_lat[]" class="regular-text code" value="' . esc_attr( $lat ) . '" placeholder="10.7769"></td>'
				. '<td><input name="cs_lng[]" class="regular-text code" value="' . esc_attr( $lng ) . '" placeholder="106.7009"></td></tr>';
		}
		echo '</tbody></table><p><button class="button button-primary" name="pve_coso_luu" value="1">Lưu cơ sở</button> <span class="description">Bỏ trống tên = xoá dòng đó.</span></p></form>';

		/* ── PIN khu quản lý (Zalo) ── */
		echo '<hr><h2>Khu quản lý trên Zalo (Báo cáo / Đơn / Soát vé)</h2>';
		echo '<form method="post"><table class="form-table">'; wp_nonce_field( 'pve_pin' );
		echo '<tr><th>Mã PIN nhân viên</th><td><input name="pin" class="regular-text code" value="' . esc_attr( get_option( 'pve_pin', '' ) ) . '" placeholder="VD 4–8 chữ số"> '
			. '<span class="description">Nhập PIN này trong app Zalo (mục Quản lý) để xem báo cáo, xác nhận/huỷ, soát vé. Để trống = KHOÁ khu quản lý.</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_pin_luu" value="1">Lưu PIN</button></p></form>';

		/* ── Đăng nhập Zalo (lấy SĐT khách) ── */
		echo '<hr><h2>Đăng nhập Zalo – lấy số điện thoại khách</h2>';
		echo '<p class="description">Để app tự lấy SĐT khi khách đăng nhập Zalo. Lấy <b>Secret Key</b> ở Zalo Mini App console: <i>Thông tin ứng dụng → App Secret Key</i>. Cần bật quyền <i>“Số điện thoại”</i> cho Mini App. Secret lưu tại đây (DB), không nằm trong mã nguồn.</p>';
		$zs = (string) get_option( 'pve_zalo_secret', '' );
		echo '<form method="post"><table class="form-table">'; wp_nonce_field( 'pve_zalo' );
		echo '<tr><th>Zalo App Secret Key</th><td><input name="zalo_secret" class="regular-text code" value="' . esc_attr( $zs ) . '" placeholder="Dán Secret Key">'
			. ' <span class="description">' . ( $zs ? 'Đang có (' . esc_html( strlen( $zs ) ) . ' ký tự)' : 'Chưa cấu hình — app sẽ để khách nhập SĐT tay' ) . '</span></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_zalo_luu" value="1">Lưu Secret</button></p></form>';

		/* ── Ưu đãi (hiện trên Zalo) ── */
		echo '<hr><h2>Ưu đãi (hiện trên Zalo)</h2>';
		$uus = self::ds_uudai_tatca();
		$uu_sua = null; $uid = isset( $_GET['sua_uu'] ) ? (int) $_GET['sua_uu'] : 0;
		if ( $uid ) { foreach ( $uus as $v ) { if ( (int) $v['id'] === $uid ) { $uu_sua = $v; break; } } }
		echo '<table class="widefat striped"><thead><tr><th style="width:70px">Ảnh</th><th>Tên</th><th>Hạng cần</th><th>Hạn</th><th>Hiện</th><th>Thao tác</th></tr></thead><tbody>';
		if ( ! $uus ) { echo '<tr><td colspan="6">Chưa có ưu đãi nào.</td></tr>'; }
		foreach ( $uus as $v ) {
			echo '<tr><td>' . ( $v['anh'] ? '<img src="' . esc_url( $v['anh'] ) . '" style="width:56px;height:56px;object-fit:cover;border-radius:8px">' : '—' ) . '</td>'
				. '<td><b>' . esc_html( $v['ten'] ) . '</b>' . ( $v['mo_ta'] ? '<br><small>' . esc_html( $v['mo_ta'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( $v['hang'] !== '' ? $v['hang'] : 'Mọi khách' ) . '</td>'
				. '<td>' . esc_html( $v['han'] ) . '</td><td>' . ( $v['hien'] ? '✅' : '⛔' ) . '</td><td>'
				. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve&sua_uu=' . $v['id'] . '#uu' ) ) . '">Sửa</a> '
				. '<form method="post" style="display:inline" onsubmit="return confirm(\'Xoá ưu đãi?\')">';
			wp_nonce_field( 'pve_uu_xoa' );
			echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '"><button class="button" name="pve_uu_xoa" value="1">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3 id="uu">' . ( $uu_sua ? 'Sửa ưu đãi' : 'Thêm ưu đãi' ) . '</h3><form method="post">';
		wp_nonce_field( 'pve_uu' );
		echo '<input type="hidden" name="id" value="' . (int) ( $uu_sua ? $uu_sua['id'] : 0 ) . '"><table class="form-table">';
		echo '<tr><th>Tên ưu đãi</th><td><input name="ten" class="regular-text" required value="' . esc_attr( $uu_sua ? $uu_sua['ten'] : '' ) . '"></td></tr>';
		echo '<tr><th>Mô tả</th><td><textarea name="mo_ta" rows="3" class="large-text">' . esc_textarea( $uu_sua ? $uu_sua['mo_ta'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th>Hạng cần (tối thiểu)</th><td><input name="hang" class="regular-text" placeholder="VD Vàng / Kim cương — trống = mọi khách" value="' . esc_attr( $uu_sua ? $uu_sua['hang'] : '' ) . '"></td></tr>';
		echo '<tr><th>Hạn dùng</th><td><input name="han" class="regular-text" placeholder="VD đến 31/12 / còn 10 ngày" value="' . esc_attr( $uu_sua ? $uu_sua['han'] : '' ) . '"></td></tr>';
		echo '<tr><th>Ảnh</th><td><input type="text" name="anh" id="pve-uu-anh" class="large-text code" value="' . esc_attr( $uu_sua ? $uu_sua['anh'] : '' ) . '"><br>'
			. '<button type="button" class="button" id="pve-uu-chon" style="margin-top:6px">Chọn ảnh từ thư viện</button> '
			. '<img id="pve-uu-xem" src="' . esc_url( $uu_sua ? $uu_sua['anh'] : '' ) . '" style="' . ( $uu_sua && $uu_sua['anh'] ? '' : 'display:none;' ) . 'height:70px;border-radius:8px;margin-left:10px;vertical-align:middle"></td></tr>';
		echo '<tr><th>Hiện</th><td><label><input type="checkbox" name="hien" value="1" ' . checked( $uu_sua ? $uu_sua['hien'] : 1, 1, false ) . '> Cho hiện trên Zalo</label></td></tr>';
		echo '</table><p><button class="button button-primary" name="pve_uu_luu" value="1">' . ( $uu_sua ? 'Lưu thay đổi' : 'Thêm ưu đãi' ) . '</button>'
			. ( $uu_sua ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=posh-ve' ) ) . '">Huỷ</a>' : '' ) . '</p></form>';
		echo '<script>jQuery(function($){var f;$("#pve-uu-chon").on("click",function(e){e.preventDefault();'
			. 'if(f){f.open();return;}f=wp.media({title:"Chọn ảnh",multiple:false,library:{type:"image"}});'
			. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#pve-uu-anh").val(a.url);$("#pve-uu-xem").attr("src",a.url).show();});f.open();});});</script>';

		echo '</div>';
	}
}

register_activation_hook( __FILE__, function () { POSH_Ve::bao_dam_bang(); POSH_Ve::bao_dam_trang(); flush_rewrite_rules(); } );
add_action( 'init', array( 'POSH_Ve', 'init' ), 6 );
