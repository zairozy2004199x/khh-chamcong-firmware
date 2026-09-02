<?php
/**
 * NẠP FILE FIRMWARE — lưu .bin lên web để các máy TỰ tải, khỏi mang thẻ SD.
 *
 * =============================================================================================
 * NHIỀU LOẠI MÁY, KHÔNG CHỈ GHẾ
 * =============================================================================================
 * Quản trị tải .bin cho TỪNG loại máy (ghế, chấm công, thợ nạp, máy thu tiền, POSH QR). Mỗi loại
 * có tệp riêng trong  wp-content/uploads/vhg-firmware/  và phục vụ bằng ĐƯỜNG DẪN UPLOADS BÌNH
 * THƯỜNG (máy chủ web tự trả tệp tĩnh). Không cần luật rewrite, không nạp lại permalink — nên
 * KHÔNG bao giờ có cảnh "vừa khai luật mà chưa flush -> 404".
 *
 * Tên tệp theo loại (loai = ghe | cham-cong | tho-nap | may-tram | posh-qr):
 *   firmware-<loai>-app.bin      -> ẢNH APP (Update.h ghi vào phân vùng app) — cho OTA/thợ nạp.
 *   firmware-<loai>-merged.bin   -> ẢNH GỘP full-flash — cho TRANG NẠP USB (esp-web-tools).
 *   latest-<loai>.json           -> cho CON THỢ NẠP: { "ver":..., "url": <app.bin>, "loai":<loai> }.
 *   manifest-usb-<loai>.json     -> cho nút nạp USB (esp-web-tools), trỏ merged.bin ở offset 0.
 *
 * 🔴 TƯƠNG THÍCH NGƯỢC: bản cũ chỉ có GHẾ, tên tệp y hệt (firmware-ghe-app.bin,
 *    firmware-ghe-merged.bin, latest-ghe.json) nên firmware ghế ĐÃ tải lên vẫn còn nguyên. Chỉ
 *    manifest USB đổi tên (manifest-usb.json -> manifest-usb-ghe.json); tệp cũ bỏ lại vô hại,
 *    lần lưu kế tiếp sinh tệp mới. Link thợ nạp (latest-ghe.json) GIỮ NGUYÊN — đó là link đã
 *    dán vào con thợ nạp, đổi là hỏng.
 *
 * ⚠️ BÍ MẬT: .bin đã biên dịch có chứa SSID/URL/KEY. Repo công khai nhưng đây là UPLOADS trên
 *    máy chủ cửa hàng — vẫn là tệp công khai ai có link tải được. Chỉ đưa link cho người trong
 *    nhà. (Mã ghế KHÔNG nằm cứng: ghế khai MAC, web gán số -> một .bin cho mọi ghế.)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Fw {

	const SUB    = 'vhg-firmware';   // thư mục con trong uploads
	const OPT    = 'vhg_fw_meta';    // option lưu metadata theo loại: { loai => { ver, cap_nhat, nguoi } }
	const OPT_CU = 'vhg_fw_ghe';     // option CŨ (chỉ ghế) — di trú 1 lần
	const MAX_MB = 6;                // trần một tệp .bin

	/** Danh mục loại firmware. key = loai (dùng trong tên tệp). */
	public static function loai_ds() {
		return array(
			'ghe'       => array( 'ten' => 'Ghế Massage QR', 'icon' => '🪑', 'mo_ta' => 'Bộ QR ghế (CYD)' ),
			'cham-cong' => array( 'ten' => 'Máy chấm công',  'icon' => '⏱️', 'mo_ta' => 'Máy chấm công CYD' ),
			'tho-nap'   => array( 'ten' => 'Thợ nạp OTA',    'icon' => '🔧', 'mo_ta' => 'Nạp chấm công + POSH qua AP' ),
			'may-tram'  => array( 'ten' => 'Máy thu tiền',   'icon' => '💰', 'mo_ta' => 'Máy trạm thu tiền / chốt ca' ),
			'posh-qr'   => array( 'ten' => 'POSH QR',        'icon' => '🎫', 'mo_ta' => 'Hộp QR POSH đời trước' ),
		);
	}
	public static function la_loai( $loai ) {
		$d = self::loai_ds();
		return is_string( $loai ) && isset( $d[ $loai ] );
	}
	public static function ten_loai( $loai ) {
		$d = self::loai_ds();
		return isset( $d[ $loai ] ) ? $d[ $loai ]['ten'] : $loai;
	}

	private static function dir() {
		$u = wp_upload_dir();
		return trailingslashit( $u['basedir'] ) . self::SUB;
	}
	private static function base_url() {
		$u = wp_upload_dir();
		return trailingslashit( $u['baseurl'] ) . self::SUB;
	}

	/* Tên tệp theo loại. */
	private static function ten_app( $loai )    { return 'firmware-' . $loai . '-app.bin'; }
	private static function ten_merged( $loai ) { return 'firmware-' . $loai . '-merged.bin'; }
	private static function ten_ota( $loai )    { return 'latest-' . $loai . '.json'; }
	private static function ten_usb( $loai )    { return 'manifest-usb-' . $loai . '.json'; }

	/** URL công khai của từng tệp (rỗng nếu tệp chưa có). */
	public static function url_app( $loai = 'ghe' )      { return self::url_neu_co( self::ten_app( $loai ) ); }
	public static function url_merged( $loai = 'ghe' )   { return self::url_neu_co( self::ten_merged( $loai ) ); }
	public static function url_json_ota( $loai = 'ghe' ) { return self::url_neu_co( self::ten_ota( $loai ) ); }
	public static function url_json_usb( $loai = 'ghe' ) { return self::url_neu_co( self::ten_usb( $loai ) ); }

	private static function url_neu_co( $ten ) {
		$p = trailingslashit( self::dir() ) . $ten;
		return file_exists( $p ) ? ( trailingslashit( self::base_url() ) . $ten ) : '';
	}

	/** Metadata mọi loại (di trú option cũ chỉ-ghế lần đầu). */
	public static function meta_all() {
		$m = get_option( self::OPT, array() );
		if ( ! is_array( $m ) ) { $m = array(); }
		if ( empty( $m['ghe'] ) ) {
			$cu = get_option( self::OPT_CU, array() );
			if ( is_array( $cu ) && ! empty( $cu ) ) { $m['ghe'] = $cu; }
		}
		return $m;
	}
	public static function meta( $loai = 'ghe' ) {
		$m = self::meta_all();
		return isset( $m[ $loai ] ) && is_array( $m[ $loai ] ) ? $m[ $loai ] : array();
	}

	/** Danh sách loại ĐÃ có bin (app hoặc merged) — cho nút chọn ở app nhân viên. */
	public static function ds_da_co() {
		$out = array();
		foreach ( self::loai_ds() as $loai => $info ) {
			$app = self::url_app( $loai );
			$mrg = self::url_merged( $loai );
			if ( '' === $app && '' === $mrg ) { continue; }
			$meta = self::meta( $loai );
			$out[] = array(
				'loai'   => $loai,
				'ten'    => $info['ten'],
				'icon'   => $info['icon'],
				'mo_ta'  => $info['mo_ta'],
				'app'    => $app,
				'merged' => $mrg,
				'ota'    => self::url_json_ota( $loai ),
				'usb'    => self::url_json_usb( $loai ),
				'ver'    => isset( $meta['ver'] ) ? (string) $meta['ver'] : '',
			);
		}
		return $out;
	}

	/** Bảo đảm thư mục tồn tại + có index.html trống (chặn liệt kê thư mục). */
	private static function bao_dam_thu_muc() {
		$d = self::dir();
		if ( ! is_dir( $d ) ) { wp_mkdir_p( $d ); }
		$idx = trailingslashit( $d ) . 'index.html';
		if ( is_dir( $d ) && ! file_exists( $idx ) ) {
			@file_put_contents( $idx, '<!-- POSH firmware -->' );
		}
		return is_dir( $d );
	}

	/**
	 * Xử lý biểu mẫu nạp: loại (bắt buộc, mặc định ghe) + phiên bản + tối đa 2 tệp (.bin app, merged).
	 * Trả mảng thông báo theo kiểu ve_bao() của VHG_Admin.
	 */
	public static function xu_ly( array $post, array $files ) {
		$bao = array();
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( array( 'ok' => false, 'error' => 'Không đủ quyền.' ) );
		}
		if ( ! self::bao_dam_thu_muc() ) {
			return array( array( 'ok' => false, 'error' => 'Không tạo được thư mục uploads/' . self::SUB . '.' ) );
		}

		$loai = isset( $post['fw_loai'] ) ? sanitize_key( wp_unslash( $post['fw_loai'] ) ) : 'ghe';
		if ( ! self::la_loai( $loai ) ) {
			return array( array( 'ok' => false, 'error' => 'Loại firmware không hợp lệ.' ) );
		}
		$ten_loai = self::ten_loai( $loai );

		$ver = isset( $post['fw_ver'] ) ? sanitize_text_field( wp_unslash( $post['fw_ver'] ) ) : '';
		$ver = trim( $ver );

		$co_app = self::co_tep( $files, 'fw_app' );
		$co_mrg = self::co_tep( $files, 'fw_merged' );

		if ( ! $co_app && ! $co_mrg && '' === $ver ) {
			return array( array( 'ok' => false, 'error' => 'Chưa chọn tệp .bin nào và cũng chưa nhập phiên bản.' ) );
		}

		if ( $co_app ) {
			$r = self::luu_tep( $files['fw_app'], self::ten_app( $loai ) );
			if ( $r['ok'] ) { $bao[] = array( 'ok' => true, 'thong_bao' => 'Đã nạp ' . $ten_loai . ' (app .bin) ' . $r['kb'] . ' KB.' ); }
			else { $bao[] = array( 'ok' => false, 'error' => $ten_loai . ' — app .bin: ' . $r['error'] ); }
		}
		if ( $co_mrg ) {
			$r = self::luu_tep( $files['fw_merged'], self::ten_merged( $loai ) );
			if ( $r['ok'] ) { $bao[] = array( 'ok' => true, 'thong_bao' => 'Đã nạp ' . $ten_loai . ' (merged .bin) ' . $r['kb'] . ' KB.' ); }
			else { $bao[] = array( 'ok' => false, 'error' => $ten_loai . ' — merged .bin: ' . $r['error'] ); }
		}

		$all  = self::meta_all();
		$meta = isset( $all[ $loai ] ) && is_array( $all[ $loai ] ) ? $all[ $loai ] : array();
		if ( '' !== $ver ) { $meta['ver'] = $ver; }
		if ( empty( $meta['ver'] ) ) { $meta['ver'] = $loai . '-' . gmdate( 'Ymd' ); }
		$meta['cap_nhat'] = current_time( 'mysql' );
		$meta['nguoi']    = wp_get_current_user() ? wp_get_current_user()->user_login : '';
		$all[ $loai ]     = $meta;
		update_option( self::OPT, $all );

		self::viet_manifest( $loai );
		return $bao;
	}

	private static function co_tep( $files, $field ) {
		return isset( $files[ $field ] ) && is_array( $files[ $field ] )
			&& isset( $files[ $field ]['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $files[ $field ]['error']
			&& '' !== (string) $files[ $field ]['name'];
	}

	/** Kiểm + chép một tệp tải lên vào uploads. KHÔNG dùng wp_handle_upload (chặn .bin theo MIME). */
	private static function luu_tep( $f, $dest_ten ) {
		$err = (int) $f['error'];
		if ( UPLOAD_ERR_OK !== $err ) {
			return array( 'ok' => false, 'error' => 'lỗi tải lên (mã ' . $err . ').' );
		}
		if ( ! isset( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return array( 'ok' => false, 'error' => 'tệp không hợp lệ.' );
		}
		$ten = (string) $f['name'];
		if ( ! preg_match( '/\.bin$/i', $ten ) ) {
			return array( 'ok' => false, 'error' => 'phải là tệp .bin.' );
		}
		$size = (int) $f['size'];
		if ( $size <= 0 ) { return array( 'ok' => false, 'error' => 'tệp rỗng.' ); }
		if ( $size > self::MAX_MB * 1024 * 1024 ) {
			return array( 'ok' => false, 'error' => 'tệp quá ' . self::MAX_MB . ' MB.' );
		}
		/* Ảnh ESP32 có magic 0xE9. HAI trường hợp hợp lệ:
		   - Ảnh APP/bootloader lẻ  -> 0xE9 ở BYTE ĐẦU (offset 0).
		   - Ảnh GỘP full-flash (merge_bin) -> byte đầu là 0xFF (padding), bootloader nằm ở
		     offset 0x1000 nên 0xE9 ở đó. (ESP32 cổ điển đặt bootloader tại 0x1000.)
		   Nhận CẢ HAI, khỏi báo nhầm file .ino.merged.bin của Arduino. */
		$fh = fopen( $f['tmp_name'], 'rb' );
		$head = $fh ? fread( $fh, 0x1001 ) : '';
		if ( $fh ) { fclose( $fh ); }
		$ok_magic = ( strlen( $head ) >= 1 && 0xE9 === ord( $head[0] ) )                    // app/bootloader lẻ
			|| ( strlen( $head ) >= 0x1001 && 0xE9 === ord( $head[0x1000] ) );              // ảnh gộp full-flash
		if ( ! $ok_magic ) {
			return array( 'ok' => false, 'error' => 'không phải ảnh ESP32 (không thấy magic 0xE9 ở đầu hay ở offset 0x1000).' );
		}
		$dest = trailingslashit( self::dir() ) . $dest_ten;
		if ( ! @move_uploaded_file( $f['tmp_name'], $dest ) ) {
			return array( 'ok' => false, 'error' => 'ghi vào uploads thất bại (quyền thư mục?).' );
		}
		@chmod( $dest, 0644 );
		return array( 'ok' => true, 'kb' => (int) round( $size / 1024 ) );
	}

	/** Viết latest-<loai>.json (thợ nạp) + manifest-usb-<loai>.json (esp-web-tools) theo tệp đang có. */
	public static function viet_manifest( $loai = 'ghe' ) {
		if ( ! self::la_loai( $loai ) ) { return; }
		$meta = self::meta( $loai );
		$ver  = isset( $meta['ver'] ) ? (string) $meta['ver'] : $loai;
		$ten  = self::ten_loai( $loai );
		$app  = self::url_app( $loai );
		$mrg  = self::url_merged( $loai );

		if ( '' !== $app ) {
			$ota = array( 'name' => $ten, 'ver' => $ver, 'url' => $app, 'loai' => $loai );
			@file_put_contents( trailingslashit( self::dir() ) . self::ten_ota( $loai ),
				wp_json_encode( $ota, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
		if ( '' !== $mrg ) {
			$usb = array(
				'name'    => $ten,
				'version' => $ver,
				'new_install_prompt_erase' => true,
				'builds'  => array( array(
					'chipFamily' => 'ESP32',
					'parts'      => array( array( 'path' => $mrg, 'offset' => 0 ) ),
				) ),
			);
			@file_put_contents( trailingslashit( self::dir() ) . self::ten_usb( $loai ),
				wp_json_encode( $usb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}

	/** Xoá toàn bộ firmware của MỘT loại (app + merged + manifest + option của loại đó). */
	public static function xoa( $loai = 'ghe' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( array( 'ok' => false, 'error' => 'Không đủ quyền.' ) );
		}
		if ( ! self::la_loai( $loai ) ) {
			return array( array( 'ok' => false, 'error' => 'Loại firmware không hợp lệ.' ) );
		}
		foreach ( array( self::ten_app( $loai ), self::ten_merged( $loai ), self::ten_ota( $loai ), self::ten_usb( $loai ) ) as $t ) {
			$p = trailingslashit( self::dir() ) . $t;
			if ( file_exists( $p ) ) { @unlink( $p ); }
		}
		$all = self::meta_all();
		if ( isset( $all[ $loai ] ) ) { unset( $all[ $loai ] ); update_option( self::OPT, $all ); }
		return array( array( 'ok' => true, 'thong_bao' => 'Đã xoá firmware ' . self::ten_loai( $loai ) . ' trên web.' ) );
	}
}
