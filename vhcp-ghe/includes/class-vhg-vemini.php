<?php
/**
 * BÁN VÉ TRƯỚC (Zalo Mini App) — API REST công khai cho mini app đặt vé/gói massage trả trước.
 *
 * Mini app chạy trên Zalo (khác origin) nên phải là REST công khai + CORS. KHÔNG lộ bí mật gì:
 *   · Số tài khoản nhận tiền vốn in công khai trên QR — lấy từ cấu hình chung (VHG_May).
 *   · Gói/mệnh giá lấy từ VHG_May::menh_gia() (đúng bộ gói đang chạy trên ghế/web).
 *   · Chuỗi VietQR dựng bằng VHG_QR::dung() (đã có, đã kiểm CRC).
 *
 * Luồng: khách chọn gói -> POST /ve/dat -> tạo 1 VÉ (mã VE******) ở trạng thái "cho" + trả về
 * chuỗi VietQR mang nội dung = mã vé. Khách chuyển khoản (nội dung = mã vé) -> kế toán/hệ thống
 * xác nhận (đối soát) -> vé "da_tt". Mini app hỏi /ve/trangthai để biết đã thanh toán chưa.
 *
 * ⚠️ Endpoint CÔNG KHAI (không đăng nhập) — chỉ ĐỌC gói + TẠO vé chờ + ĐỌC trạng thái vé theo mã.
 *    Không cho sửa/huỷ/đọc danh sách vé của người khác. Ghi có giới hạn nhịp theo IP (chống spam).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Vemini {

	const NS       = 'vhg/v1';
	const VER_TBL  = '1';                 // tăng khi đổi cấu trúc bảng bc_ve

	public static function init() {
		self::bao_dam_bang();
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
		/* CORS chỉ cho các route bán vé — mini app Zalo gọi từ origin khác. */
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
	}

	/** Tên bảng vé. */
	private static function tbl() { return VHG_DB::t( 'bc_ve' ); }

	/** Tạo bảng bc_ve nếu chưa có (dbDelta, chỉ chạy lại khi đổi version). */
	private static function bao_dam_bang() {
		if ( get_option( 'vhg_ve_tbl' ) === self::VER_TBL ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tbl = self::tbl();
		$col = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $tbl (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ma_ve VARCHAR(24) NOT NULL,
			goi_ten VARCHAR(80) NOT NULL DEFAULT '',
			goi_tien INT NOT NULL DEFAULT 0,
			phut INT NOT NULL DEFAULT 0,
			ten_khach VARCHAR(80) NOT NULL DEFAULT '',
			sdt VARCHAR(20) NOT NULL DEFAULT '',
			so_tien INT NOT NULL DEFAULT 0,
			noi_dung VARCHAR(40) NOT NULL DEFAULT '',
			trang_thai VARCHAR(12) NOT NULL DEFAULT 'cho',
			tao_luc DATETIME NOT NULL,
			tt_luc DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY ma_ve (ma_ve),
			KEY trang_thai (trang_thai)
		) $col;" );
		update_option( 'vhg_ve_tbl', self::VER_TBL );
	}

	public static function dang_ky() {
		register_rest_route( self::NS, '/ve/goi', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'r_goi' ),
		) );
		register_rest_route( self::NS, '/ve/dat', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'r_dat' ),
		) );
		register_rest_route( self::NS, '/ve/trangthai', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'r_trangthai' ),
		) );
	}

	/** Gắn header CORS cho riêng các route /ve/*. */
	public static function cors( $served, $result, $request, $server ) {
		if ( $request && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/ve' ) ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
		}
		return $served;
	}

	/** Cấu hình TK nhận tiền (dùng chung với ghế/web). */
	private static function bank() {
		$tk = VHG_May::nhan_tien_chung();
		return array(
			'bin'    => (string) $tk['bin'],
			'so_tk'  => (string) $tk['so_tk'],
			'ten_tk' => (string) $tk['ten_tk'],
			'ten_nh' => VHG_QR::ten_ngan_hang( $tk['bin'] ),
		);
	}

	/** GET /ve/goi — danh sách gói + thông tin TK để mini app dựng màn mua. */
	public static function r_goi() {
		$goi = array();
		foreach ( VHG_May::menh_gia() as $i => $g ) {
			$goi[] = array(
				'ma'    => (int) $i,
				'ten'   => $g['ten'] !== '' ? $g['ten'] : ( number_format( $g['tien'], 0, ',', '.' ) . 'đ' ),
				'tien'  => (int) $g['tien'],
				'phut'  => (int) $g['phut'],
				'mo_ta' => (string) ( isset( $g['mo_ta'] ) ? $g['mo_ta'] : '' ),
				'vip'   => empty( $g['vip'] ) ? 0 : 1,
			);
		}
		$b = self::bank();
		return array( 'ok' => true, 'goi' => $goi, 'bank' => array(
			'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}

	/** POST /ve/dat {tien, ten, sdt} — tạo vé chờ + trả VietQR. */
	public static function r_dat( $req ) {
		$tien = (int) $req->get_param( 'tien' );
		$ten  = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt  = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );

		/* Gói phải khớp đúng một mệnh giá đang bán — không nhận số tiền tuỳ ý (chống gõ bừa). */
		$goi = null;
		foreach ( VHG_May::menh_gia() as $g ) { if ( (int) $g['tien'] === $tien ) { $goi = $g; break; } }
		if ( ! $goi ) { return new WP_Error( 'goi', 'Gói không hợp lệ.', array( 'status' => 400 ) ); }
		if ( '' === $ten || '' === $sdt ) {
			return new WP_Error( 'thieu', 'Cần tên và số điện thoại.', array( 'status' => 400 ) );
		}
		$b = self::bank();
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) {
			return new WP_Error( 'tk', 'Chưa cấu hình tài khoản nhận tiền.', array( 'status' => 409 ) );
		}
		if ( ! self::nhip_ok() ) {
			return new WP_Error( 'nhip', 'Thao tác quá nhanh, thử lại sau giây lát.', array( 'status' => 429 ) );
		}

		global $wpdb;
		$ma_ve   = self::ma_ve_moi();
		$noidung = 'VE' . $ma_ve;                       // nội dung CK = mã vé (alnum, viết hoa)
		$phut    = VHG_May::phut_goi( $goi, (int) get_option( 'vhg_gia', 10000 ), (int) get_option( 'vhg_phut', 6 ) );
		$wpdb->insert( self::tbl(), array(
			'ma_ve'     => $ma_ve,
			'goi_ten'   => $goi['ten'] !== '' ? $goi['ten'] : ( number_format( $tien, 0, ',', '.' ) . 'đ' ),
			'goi_tien'  => $tien,
			'phut'      => (int) $phut,
			'ten_khach' => mb_substr( $ten, 0, 80 ),
			'sdt'       => mb_substr( $sdt, 0, 20 ),
			'so_tien'   => $tien,
			'noi_dung'  => $noidung,
			'trang_thai'=> 'cho',
			'tao_luc'   => current_time( 'mysql' ),
		) );
		$qr = VHG_QR::dung( $b['bin'], $b['so_tk'], $tien, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tien, 'phut' => (int) $phut,
			'goi_ten' => $goi['ten'], 'noi_dung' => $noidung, 'qr' => $qr, 'trang_thai' => 'cho',
			'bank' => array( 'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}

	/** GET /ve/trangthai?ma_ve= — trạng thái một vé theo mã. */
	public static function r_trangthai( $req ) {
		global $wpdb;
		$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $req->get_param( 'ma_ve' ) ) );
		if ( '' === $ma ) { return new WP_Error( 'ma', 'Thiếu mã vé.', array( 'status' => 400 ) ); }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT ma_ve, goi_ten, so_tien, phut, trang_thai, tao_luc, tt_luc FROM ' . self::tbl() . ' WHERE ma_ve=%s', $ma ), ARRAY_A );
		if ( ! $r ) { return new WP_Error( 'khong_co', 'Không tìm thấy vé.', array( 'status' => 404 ) ); }
		return array( 'ok' => true, 'ma_ve' => $r['ma_ve'], 'goi_ten' => $r['goi_ten'],
			'so_tien' => (int) $r['so_tien'], 'phut' => (int) $r['phut'],
			'trang_thai' => $r['trang_thai'], 'tao_luc' => $r['tao_luc'], 'tt_luc' => $r['tt_luc'] );
	}

	/** Sinh mã vé duy nhất — 8 ký tự, bỏ ký tự dễ nhầm (O/0, I/1). */
	private static function ma_ve_moi() {
		global $wpdb;
		$bang = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $lan = 0; $lan < 12; $lan++ ) {
			$s = '';
			for ( $i = 0; $i < 8; $i++ ) { $s .= $bang[ random_int( 0, strlen( $bang ) - 1 ) ]; }
			$co = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE ma_ve=%s', $s ) );
			if ( ! $co ) { return $s; }
		}
		return 'V' . substr( (string) time(), -7 );
	}

	/** Giới hạn nhịp tạo vé theo IP: tối đa ~1 vé / 3 giây. */
	private static function nhip_ok() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) $_SERVER['REMOTE_ADDR'] ) : 'x';
		$key = 'vhg_ve_nhip_' . md5( $ip );
		if ( get_transient( $key ) ) { return false; }
		set_transient( $key, 1, 3 );
		return true;
	}
}
