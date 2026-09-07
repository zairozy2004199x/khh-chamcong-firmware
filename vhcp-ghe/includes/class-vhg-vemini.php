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

	/* DỊCH VỤ (vé) KHU VUI CHƠI. Admin quản lý ở "Cài đặt → Dịch vụ (bán vé)".
	   Mỗi dịch vụ: id (ổn định), ten, gia, mo_ta, anh (URL), thoi_luong, hien. Đây là ví dụ
	   mặc định để chạy thử — anh sửa lại cho đúng khu của mình. */
	const VE_MAC_DINH = array(
		array( 'id' => 1, 'ten' => 'Vé vào cửa', 'gia' => 50000,  'mo_ta' => 'Vé vào cửa 1 người', 'anh' => '', 'thoi_luong' => '', 'hien' => 1 ),
		array( 'id' => 2, 'ten' => 'Vé 1 giờ',   'gia' => 80000,  'mo_ta' => 'Chơi trong 1 giờ',    'anh' => '', 'thoi_luong' => '60 phút', 'hien' => 1 ),
		array( 'id' => 3, 'ten' => 'Vé cả ngày', 'gia' => 150000, 'mo_ta' => 'Chơi không giới hạn trong ngày', 'anh' => '', 'thoi_luong' => 'Cả ngày', 'hien' => 1 ),
	);

	public static function init() {
		self::bao_dam_bang();
		add_action( 'rest_api_init', array( __CLASS__, 'dang_ky' ) );
		/* CORS chỉ cho các route bán vé — mini app Zalo gọi từ origin khác. */
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	/** Chuẩn hoá 1 dịch vụ (gán id nếu thiếu). */
	private static function chuan_hoa( $v, $auto_id ) {
		return array(
			'id'         => (int) ( ! empty( $v['id'] ) ? $v['id'] : $auto_id ),
			'ten'        => trim( (string) ( isset( $v['ten'] ) ? $v['ten'] : '' ) ),
			'gia'        => (int) ( isset( $v['gia'] ) ? $v['gia'] : 0 ),
			'mo_ta'      => trim( (string) ( isset( $v['mo_ta'] ) ? $v['mo_ta'] : '' ) ),
			'anh'        => esc_url_raw( (string) ( isset( $v['anh'] ) ? $v['anh'] : '' ) ),
			'thoi_luong' => trim( (string) ( isset( $v['thoi_luong'] ) ? $v['thoi_luong'] : '' ) ),
			'hien'       => empty( $v['hien'] ) ? 0 : 1,
		);
	}

	/** TẤT CẢ dịch vụ (kể cả đang ẩn) — cho trang admin. */
	public static function ds_ve_tatca() {
		$ds = get_option( 'vhg_ve_goi' );
		if ( ! is_array( $ds ) || ! $ds ) { return self::VE_MAC_DINH; }
		$ra = array(); $auto = 1;
		foreach ( $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		return $ra ? $ra : self::VE_MAC_DINH;
	}

	/** Dịch vụ ĐANG HIỆN (bán) — cho REST/mini app. */
	public static function ds_ve() {
		$ra = array();
		foreach ( self::ds_ve_tatca() as $v ) { if ( $v['hien'] ) { $ra[] = $v; } }
		return $ra;
	}

	/** Tìm 1 dịch vụ theo id (trong danh sách đang hiện). */
	private static function ve_theo_id( $id ) {
		foreach ( self::ds_ve() as $v ) { if ( (int) $v['id'] === (int) $id ) { return $v; } }
		return null;
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
		foreach ( self::ds_ve() as $g ) {
			$goi[] = array(
				'ma'         => (int) $g['id'],     // id ỔN ĐỊNH của dịch vụ
				'ten'        => $g['ten'],
				'tien'       => (int) $g['gia'],
				'mo_ta'      => (string) $g['mo_ta'],
				'anh'        => (string) $g['anh'],
				'thoi_luong' => (string) $g['thoi_luong'],
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

		$goi = self::ve_theo_id( $id );
		if ( ! $goi ) { return new WP_Error( 'goi', 'Vé không hợp lệ.', array( 'status' => 400 ) ); }
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
		add_submenu_page( 'options-general.php', 'Dịch vụ (bán vé)', 'Dịch vụ (bán vé)',
			'manage_options', 'vhg-ve', array( __CLASS__, 'trang_admin' ) );
	}

	/** Lưu lại toàn bộ danh mục dịch vụ (chuẩn hoá, gán id còn thiếu). */
	private static function luu_ds( $ds ) {
		$ra = array(); $auto = 1;
		foreach ( (array) $ds as $v ) {
			foreach ( $ra as $x ) { if ( $x['id'] >= $auto ) { $auto = $x['id'] + 1; } }
			$m = self::chuan_hoa( $v, $auto );
			if ( '' === $m['ten'] || $m['gia'] < 1000 ) { continue; }
			$ra[] = $m;
		}
		update_option( 'vhg_ve_goi', array_values( $ra ) );
	}

	public static function trang_admin() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;
		wp_enqueue_media();   // để nút "Chọn ảnh" mở thư viện WordPress

		/* Thêm / sửa 1 dịch vụ. */
		if ( isset( $_POST['vhg_dv_luu'] ) && check_admin_referer( 'vhg_dv_luu' ) ) {
			$id = (int) $_POST['id'];
			$moi = array(
				'id'         => $id,
				'ten'        => sanitize_text_field( wp_unslash( $_POST['ten'] ) ),
				'gia'        => (int) preg_replace( '/\D+/', '', (string) $_POST['gia'] ),
				'mo_ta'      => sanitize_textarea_field( wp_unslash( $_POST['mo_ta'] ) ),
				'anh'        => esc_url_raw( wp_unslash( $_POST['anh'] ) ),
				'thoi_luong' => sanitize_text_field( wp_unslash( $_POST['thoi_luong'] ) ),
				'hien'       => empty( $_POST['hien'] ) ? 0 : 1,
			);
			$ds = self::ds_ve_tatca(); $thay = false;
			if ( $id > 0 ) {
				foreach ( $ds as $k => $v ) { if ( (int) $v['id'] === $id ) { $ds[ $k ] = $moi; $thay = true; break; } }
			}
			if ( ! $thay ) { $moi['id'] = 0; $ds[] = $moi; }   // id=0 -> luu_ds gán id mới
			self::luu_ds( $ds );
			echo '<div class="notice notice-success"><p>Đã lưu dịch vụ.</p></div>';
		}
		/* Xoá 1 dịch vụ. */
		if ( isset( $_POST['vhg_dv_xoa'] ) && check_admin_referer( 'vhg_dv_xoa' ) ) {
			$id = (int) $_POST['id'];
			$ds = array_filter( self::ds_ve_tatca(), function ( $v ) use ( $id ) { return (int) $v['id'] !== $id; } );
			self::luu_ds( $ds );
			echo '<div class="notice notice-success"><p>Đã xoá dịch vụ.</p></div>';
		}
		/* Xác nhận / huỷ một vé đã đặt (test khép vòng — Stage 2 nối đối soát tự động). */
		if ( isset( $_POST['vhg_ve_tt'] ) && check_admin_referer( 'vhg_ve_tt' ) ) {
			$ma = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $_POST['ma_ve'] ) );
			$tt = 'huy' === $_POST['vhg_ve_tt'] ? 'huy' : 'da_tt';
			$wpdb->update( self::tbl(),
				array( 'trang_thai' => $tt, 'tt_luc' => current_time( 'mysql' ) ),
				array( 'ma_ve' => $ma ) );
			echo '<div class="notice notice-success"><p>Vé ' . esc_html( $ma ) . ' → ' . esc_html( $tt ) . '.</p></div>';
		}

		$b   = self::bank();
		$url = esc_url( home_url( '/wp-json/' . self::NS . '/ve/goi' ) );
		$ds  = self::ds_ve_tatca();

		// Dịch vụ đang sửa (nếu bấm "Sửa").
		$sua = null;
		$sid = isset( $_GET['sua'] ) ? (int) $_GET['sua'] : 0;
		if ( $sid ) { foreach ( $ds as $v ) { if ( (int) $v['id'] === $sid ) { $sua = $v; break; } } }

		echo '<div class="wrap"><h1>Dịch vụ (bán vé qua Zalo Mini App)</h1>';
		echo '<p>API cho mini app: <code>' . $url . '</code></p>';
		if ( '' === $b['so_tk'] || '' === $b['bin'] ) {
			echo '<div class="notice notice-warning"><p><b>Chưa cấu hình tài khoản nhận tiền</b> — khai ở mục cấu hình TK chung của plugin, không thì khách không tạo được mã QR.</p></div>';
		} else {
			echo '<p>Nhận tiền về: <b>' . esc_html( $b['so_tk'] ) . '</b> · ' . esc_html( $b['ten_nh'] ) . ' · ' . esc_html( $b['ten_tk'] ) . '</p>';
		}

		/* Bảng dịch vụ */
		echo '<h2>Danh sách dịch vụ</h2><table class="widefat striped"><thead><tr><th style="width:70px">Ảnh</th>'
			. '<th>Tên</th><th>Giá</th><th>Thời lượng</th><th>Hiện</th><th>Thao tác</th></tr></thead><tbody>';
		foreach ( $ds as $v ) {
			echo '<tr><td>' . ( $v['anh'] ? '<img src="' . esc_url( $v['anh'] ) . '" style="width:56px;height:56px;object-fit:cover;border-radius:8px">' : '—' ) . '</td>'
				. '<td><b>' . esc_html( $v['ten'] ) . '</b>' . ( $v['mo_ta'] !== '' ? '<br><small>' . esc_html( $v['mo_ta'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( number_format( $v['gia'], 0, ',', '.' ) ) . 'đ</td>'
				. '<td>' . esc_html( $v['thoi_luong'] ) . '</td>'
				. '<td>' . ( $v['hien'] ? '✅' : '⛔' ) . '</td><td>'
				. '<a class="button" href="' . esc_url( admin_url( 'options-general.php?page=vhg-ve&sua=' . $v['id'] ) ) . '">Sửa</a> '
				. '<form method="post" style="display:inline" onsubmit="return confirm(\'Xoá dịch vụ này?\')">';
			wp_nonce_field( 'vhg_dv_xoa' );
			echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '">'
				. '<button class="button" name="vhg_dv_xoa" value="1">Xoá</button></form></td></tr>';
		}
		echo '</tbody></table>';

		/* Form thêm / sửa */
		echo '<h2>' . ( $sua ? 'Sửa dịch vụ' : 'Thêm dịch vụ' ) . '</h2><form method="post">';
		wp_nonce_field( 'vhg_dv_luu' );
		echo '<input type="hidden" name="id" value="' . (int) ( $sua ? $sua['id'] : 0 ) . '">';
		echo '<table class="form-table"><tr><th>Tên dịch vụ</th><td><input name="ten" class="regular-text" required value="'
			. esc_attr( $sua ? $sua['ten'] : '' ) . '"></td></tr>';
		echo '<tr><th>Giá (đ)</th><td><input name="gia" type="number" min="1000" step="1000" required value="'
			. esc_attr( $sua ? $sua['gia'] : '' ) . '"></td></tr>';
		echo '<tr><th>Thời lượng</th><td><input name="thoi_luong" class="regular-text" placeholder="VD 60 phút / Cả ngày" value="'
			. esc_attr( $sua ? $sua['thoi_luong'] : '' ) . '"></td></tr>';
		echo '<tr><th>Mô tả</th><td><textarea name="mo_ta" rows="3" class="large-text">'
			. esc_textarea( $sua ? $sua['mo_ta'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th>Ảnh</th><td>'
			. '<input type="text" name="anh" id="vhg-anh" class="large-text code" placeholder="URL ảnh" value="' . esc_attr( $sua ? $sua['anh'] : '' ) . '"><br>'
			. '<button type="button" class="button" id="vhg-chon-anh" style="margin-top:6px">Chọn ảnh từ thư viện</button> '
			. '<img id="vhg-anh-xem" src="' . esc_url( $sua ? $sua['anh'] : '' ) . '" style="' . ( $sua && $sua['anh'] ? '' : 'display:none;' ) . 'height:80px;border-radius:8px;margin-left:10px;vertical-align:middle"></td></tr>';
		echo '<tr><th>Hiện (bán)</th><td><label><input type="checkbox" name="hien" value="1" '
			. checked( $sua ? $sua['hien'] : 1, 1, false ) . '> Cho hiện trên app</label></td></tr>';
		echo '</table><p><button class="button button-primary" name="vhg_dv_luu" value="1">'
			. ( $sua ? 'Lưu thay đổi' : 'Thêm dịch vụ' ) . '</button>'
			. ( $sua ? ' <a class="button" href="' . esc_url( admin_url( 'options-general.php?page=vhg-ve' ) ) . '">Huỷ sửa</a>' : '' )
			. '</p></form>';

		/* JS: nút chọn ảnh mở thư viện WordPress */
		echo '<script>jQuery(function($){var f;$("#vhg-chon-anh").on("click",function(e){e.preventDefault();'
			. 'if(f){f.open();return;}f=wp.media({title:"Chọn ảnh dịch vụ",multiple:false,library:{type:"image"}});'
			. 'f.on("select",function(){var a=f.state().get("selection").first().toJSON();'
			. '$("#vhg-anh").val(a.url);$("#vhg-anh-xem").attr("src",a.url).show();});f.open();});});</script>';

		/* Vé đã đặt (đẩy về từ Zalo app) */
		$rows = $wpdb->get_results( 'SELECT ma_ve, goi_ten, so_tien, ten_khach, sdt, trang_thai, tao_luc FROM '
			. self::tbl() . ' ORDER BY id DESC LIMIT 40', ARRAY_A );
		echo '<h2>Vé đã đặt (từ Zalo)</h2>';
		if ( ! $rows ) { echo '<p>Chưa có vé nào.</p>'; }
		else {
			echo '<table class="widefat striped"><thead><tr><th>Lúc</th><th>Mã vé</th><th>Dịch vụ</th><th>Số tiền</th>'
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
