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

	/* Vé KHU VUI CHƠI (không phải gói ghế massage). Admin tự sửa ở trang "Vé khu vui chơi".
	   Đây chỉ là ví dụ mặc định để chạy thử — anh sửa lại tên/giá cho đúng khu của mình. */
	const VE_MAC_DINH = array(
		array( 'ten' => 'Vé vào cửa', 'gia' => 50000,  'mo_ta' => 'Vé vào cửa 1 bé' ),
		array( 'ten' => 'Vé 1 giờ',   'gia' => 80000,  'mo_ta' => 'Chơi trong 1 giờ' ),
		array( 'ten' => 'Vé cả ngày', 'gia' => 150000, 'mo_ta' => 'Chơi không giới hạn trong ngày' ),
		array( 'ten' => 'Combo 2 bé', 'gia' => 140000, 'mo_ta' => '2 bé chơi cả ngày' ),
	);

	public static function init() {
		self::bao_dam_bang();
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
		/* CORS chỉ cho các route bán vé — mini app Zalo gọi từ origin khác. */
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	/** Danh mục vé (option vhg_ve_goi). Trả list [{ten,gia,mo_ta}] đã lọc, giá tăng dần. */
	public static function ds_ve() {
		$ds = get_option( 'vhg_ve_goi' );
		if ( ! is_array( $ds ) || ! $ds ) { return self::VE_MAC_DINH; }
		$ra = array();
		foreach ( $ds as $v ) {
			$gia = (int) ( isset( $v['gia'] ) ? $v['gia'] : 0 );
			$ten = trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) );
			if ( $gia < 1000 || '' === $ten ) { continue; }
			$ra[] = array( 'ten' => $ten, 'gia' => $gia,
				'mo_ta' => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ) );
		}
		return $ra ? $ra : self::VE_MAC_DINH;
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

	/** GET /ve/goi — danh sách VÉ khu vui chơi + thông tin TK để mini app dựng màn mua. */
	public static function r_goi() {
		$goi = array();
		foreach ( self::ds_ve() as $i => $g ) {
			$goi[] = array(
				'ma'    => (int) $i,               // id = vị trí trong danh mục (ổn định trong 1 lần tải)
				'ten'   => $g['ten'],
				'tien'  => (int) $g['gia'],
				'mo_ta' => (string) $g['mo_ta'],
			);
		}
		$b = self::bank();
		return array( 'ok' => true, 'goi' => $goi, 'bank' => array(
			'ten_nh' => $b['ten_nh'], 'so_tk' => $b['so_tk'], 'ten_tk' => $b['ten_tk'] ) );
	}

	/** POST /ve/dat {id, ten, sdt} — tạo vé chờ + trả VietQR. `id` = vị trí vé trong /ve/goi. */
	public static function r_dat( $req ) {
		$id  = (int) $req->get_param( 'id' );
		$ten = sanitize_text_field( (string) $req->get_param( 'ten' ) );
		$sdt = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'sdt' ) );

		$ds  = self::ds_ve();
		if ( ! isset( $ds[ $id ] ) ) { return new WP_Error( 'goi', 'Vé không hợp lệ.', array( 'status' => 400 ) ); }
		$goi  = $ds[ $id ];
		$tien = (int) $goi['gia'];
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
		$wpdb->insert( self::tbl(), array(
			'ma_ve'     => $ma_ve,
			'goi_ten'   => $goi['ten'],
			'goi_tien'  => $tien,
			'phut'      => 0,
			'ten_khach' => mb_substr( $ten, 0, 80 ),
			'sdt'       => mb_substr( $sdt, 0, 20 ),
			'so_tien'   => $tien,
			'noi_dung'  => $noidung,
			'trang_thai'=> 'cho',
			'tao_luc'   => current_time( 'mysql' ),
		) );
		$qr = VHG_QR::dung( $b['bin'], $b['so_tk'], $tien, $noidung );
		return array( 'ok' => true, 'ma_ve' => $ma_ve, 'so_tien' => $tien,
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

	// ══════════════════════════════════════════════════════════════════ TRANG ADMIN
	public static function admin_menu() {
		add_submenu_page( 'options-general.php', 'Vé khu vui chơi', 'Vé khu vui chơi',
			'manage_options', 'vhg-ve', array( __CLASS__, 'trang_admin' ) );
	}

	public static function trang_admin() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;

		/* Lưu danh mục vé: mỗi dòng "Tên | Giá | Mô tả". */
		if ( isset( $_POST['vhg_ve_luu'] ) && check_admin_referer( 'vhg_ve_luu' ) ) {
			$txt = (string) wp_unslash( $_POST['ds'] );
			$ds  = array();
			foreach ( preg_split( '/\r?\n/', $txt ) as $dong ) {
				$dong = trim( $dong );
				if ( '' === $dong ) { continue; }
				$p = array_map( 'trim', explode( '|', $dong ) );
				$gia = isset( $p[1] ) ? (int) preg_replace( '/\D+/', '', $p[1] ) : 0;
				if ( '' === $p[0] || $gia < 1000 ) { continue; }
				$ds[] = array( 'ten' => $p[0], 'gia' => $gia, 'mo_ta' => isset( $p[2] ) ? $p[2] : '' );
			}
			update_option( 'vhg_ve_goi', $ds );
			echo '<div class="notice notice-success"><p>Đã lưu danh mục vé.</p></div>';
		}
		/* Xác nhận / huỷ một vé (test khép vòng — Stage 2 sẽ nối đối soát tự động). */
		if ( isset( $_POST['vhg_ve_tt'] ) && check_admin_referer( 'vhg_ve_tt' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $_POST['ma_ve'] ) );
			$tt = 'huy' === $_POST['vhg_ve_tt'] ? 'huy' : 'da_tt';
			$wpdb->update( self::tbl(),
				array( 'trang_thai' => $tt, 'tt_luc' => current_time( 'mysql' ) ),
				array( 'ma_ve' => $ma ) );
			echo '<div class="notice notice-success"><p>Vé ' . esc_html( $ma ) . ' → ' . esc_html( $tt ) . '.</p></div>';
		}

		$ds_txt = '';
		foreach ( self::ds_ve() as $g ) {
			$ds_txt .= $g['ten'] . ' | ' . $g['gia'] . ( $g['mo_ta'] !== '' ? ' | ' . $g['mo_ta'] : '' ) . "\n";
		}
		$b   = self::bank();
		$url = esc_url( home_url( '/wp-json/' . self::NS . '/ve/goi' ) );
		echo '<div class="wrap"><h1>Vé khu vui chơi (Zalo Mini App)</h1>';
		echo '<p>API cho mini app: <code>' . $url . '</code> — mở thử phải ra JSON danh sách vé.</p>';
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) {
			echo '<div class="notice notice-warning"><p><b>Chưa cấu hình tài khoản nhận tiền</b> — vào mục cấu hình TK chung của plugin để khai, không thì khách không tạo được mã QR.</p></div>';
		} else {
			echo '<p>Nhận tiền về: <b>' . esc_html( $b['so_tk'] ) . '</b> · ' . esc_html( $b['ten_nh'] ) . ' · ' . esc_html( $b['ten_tk'] ) . '</p>';
		}

		echo '<h2>Danh mục vé</h2><form method="post">';
		wp_nonce_field( 'vhg_ve_luu' );
		echo '<p class="description">Mỗi dòng một vé: <code>Tên | Giá | Mô tả</code> (Mô tả không bắt buộc).</p>';
		echo '<textarea name="ds" rows="8" style="width:100%;max-width:640px;font-family:monospace">'
			. esc_textarea( $ds_txt ) . '</textarea><br>';
		echo '<p><button class="button button-primary" name="vhg_ve_luu" value="1">Lưu danh mục</button></p></form>';

		/* Vé gần đây */
		$rows = $wpdb->get_results( 'SELECT ma_ve, goi_ten, so_tien, ten_khach, sdt, trang_thai, tao_luc FROM '
			. self::tbl() . ' ORDER BY id DESC LIMIT 40', ARRAY_A );
		echo '<h2>Vé gần đây</h2>';
		if ( ! $rows ) { echo '<p>Chưa có vé nào.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Mã vé</th><th>Vé</th><th>Số tiền</th>'
				. '<th>Khách</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$nhan = array( 'cho' => 'Chờ', 'da_tt' => 'Đã thanh toán', 'huy' => 'Đã huỷ' );
				echo '<tr><td>' . esc_html( $r['tao_luc'] ) . '</td><td><code>' . esc_html( $r['ma_ve'] ) . '</code></td>'
					. '<td>' . esc_html( $r['goi_ten'] ) . '</td><td>' . esc_html( number_format( $r['so_tien'], 0, ',', '.' ) ) . 'đ</td>'
					. '<td>' . esc_html( $r['ten_khach'] ) . '<br><small>' . esc_html( $r['sdt'] ) . '</small></td>'
					. '<td>' . esc_html( isset( $nhan[ $r['trang_thai'] ] ) ? $nhan[ $r['trang_thai'] ] : $r['trang_thai'] ) . '</td><td>';
				if ( 'cho' === $r['trang_thai'] ) {
					echo '<form method="post" style="display:inline">';
					wp_nonce_field( 'vhg_ve_tt' );
					echo '<input type="hidden" name="ma_ve" value="' . esc_attr( $r['ma_ve'] ) . '">'
						. '<button class="button button-primary" name="vhg_ve_tt" value="da_tt">Đã thanh toán</button> '
						. '<button class="button" name="vhg_ve_tt" value="huy">Huỷ</button></form>';
				} else { echo '—'; }
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
