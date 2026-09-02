<?php
/**
 * NẠP FILE FIRMWARE GHẾ — lưu .bin lên web để các máy TỰ tải, khỏi mang thẻ SD.
 *
 * =============================================================================================
 * VÌ SAO ĐỂ TRONG UPLOADS, KHÔNG QUA REWRITE
 * =============================================================================================
 * File .bin và manifest ghi thẳng vào  wp-content/uploads/vhg-firmware/  rồi phục vụ bằng
 * ĐƯỜNG DẪN UPLOADS BÌNH THƯỜNG (máy chủ web tự trả tệp tĩnh). Không cần luật rewrite, không
 * cần nạp lại permalink — nên KHÔNG bao giờ có cảnh "vừa khai luật mà chưa flush -> 404".
 *
 * Ba tệp phục vụ ra ngoài:
 *   1. latest-ghe.json      -> cho CON THỢ NẠP (esp32_ota_updater, ô "Link firmware GHE"):
 *                              { "ver":..., "url": <app.bin>, "loai":"ghe" }  (đúng taiFirmware()).
 *   2. firmware-ghe-app.bin -> ẢNH APP (Update.h ghi vào phân vùng app) — dùng cho OTA/thợ nạp.
 *   3. firmware-ghe-merged.bin + manifest-usb.json -> cho TRANG NẠP USB (esp-web-tools, #1).
 *
 * ⚠️ BÍ MẬT: .bin đã biên dịch có chứa SSID/URL/KEY. Repo công khai nhưng đây là UPLOADS trên
 *    máy chủ cửa hàng — vẫn là tệp công khai ai có link tải được. Chỉ đưa link cho người trong
 *    nhà. (Mã ghế KHÔNG nằm cứng: ghế khai MAC, web gán số -> một .bin cho mọi ghế.)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHG_Fw {

	const SUB      = 'vhg-firmware';      // thư mục con trong uploads
	const OPT      = 'vhg_fw_ghe';        // option lưu metadata
	const APP_BIN  = 'firmware-ghe-app.bin';
	const MRG_BIN  = 'firmware-ghe-merged.bin';
	const JSON_OTA = 'latest-ghe.json';
	const JSON_USB = 'manifest-usb.json';
	const MAX_MB   = 6;                   // trần một tệp .bin

	private static function dir() {
		$u = wp_upload_dir();
		return trailingslashit( $u['basedir'] ) . self::SUB;
	}
	private static function base_url() {
		$u = wp_upload_dir();
		return trailingslashit( $u['baseurl'] ) . self::SUB;
	}
	public static function meta() {
		$m = get_option( self::OPT, array() );
		return is_array( $m ) ? $m : array();
	}

	/** URL công khai của từng tệp (rỗng nếu tệp chưa có). */
	public static function url_app()    { return self::url_neu_co( self::APP_BIN ); }
	public static function url_merged() { return self::url_neu_co( self::MRG_BIN ); }
	public static function url_json_ota() { return self::url_neu_co( self::JSON_OTA ); }
	public static function url_json_usb() { return self::url_neu_co( self::JSON_USB ); }

	private static function url_neu_co( $ten ) {
		$p = trailingslashit( self::dir() ) . $ten;
		return file_exists( $p ) ? ( trailingslashit( self::base_url() ) . $ten ) : '';
	}

	/** Bảo đảm thư mục tồn tại + có index.html trống (chặn liệt kê thư mục). */
	private static function bao_dam_thu_muc() {
		$d = self::dir();
		if ( ! is_dir( $d ) ) { wp_mkdir_p( $d ); }
		$idx = trailingslashit( $d ) . 'index.html';
		if ( is_dir( $d ) && ! file_exists( $idx ) ) {
			@file_put_contents( $idx, '<!-- POSH firmware ghe -->' );
		}
		return is_dir( $d );
	}

	/**
	 * Xử lý biểu mẫu nạp: phiên bản (bắt buộc) + tối đa 2 tệp (.bin app, .bin merged).
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

		$ver = isset( $post['fw_ver'] ) ? sanitize_text_field( wp_unslash( $post['fw_ver'] ) ) : '';
		$ver = trim( $ver );

		$co_app = self::co_tep( $files, 'fw_app' );
		$co_mrg = self::co_tep( $files, 'fw_merged' );

		if ( ! $co_app && ! $co_mrg && '' === $ver ) {
			return array( array( 'ok' => false, 'error' => 'Chưa chọn tệp .bin nào và cũng chưa nhập phiên bản.' ) );
		}

		$meta = self::meta();

		if ( $co_app ) {
			$r = self::luu_tep( $files['fw_app'], self::APP_BIN );
			if ( $r['ok'] ) { $bao[] = array( 'ok' => true, 'thong_bao' => 'Đã nạp firmware GHẾ (app .bin) ' . $r['kb'] . ' KB.' ); }
			else { $bao[] = array( 'ok' => false, 'error' => 'App .bin: ' . $r['error'] ); }
		}
		if ( $co_mrg ) {
			$r = self::luu_tep( $files['fw_merged'], self::MRG_BIN );
			if ( $r['ok'] ) { $bao[] = array( 'ok' => true, 'thong_bao' => 'Đã nạp firmware GHẾ (merged .bin) ' . $r['kb'] . ' KB.' ); }
			else { $bao[] = array( 'ok' => false, 'error' => 'Merged .bin: ' . $r['error'] ); }
		}

		if ( '' !== $ver ) { $meta['ver'] = $ver; }
		if ( empty( $meta['ver'] ) ) { $meta['ver'] = 'ghe-' . gmdate( 'Ymd' ); }
		$meta['cap_nhat'] = current_time( 'mysql' );
		$meta['nguoi']    = wp_get_current_user() ? wp_get_current_user()->user_login : '';
		update_option( self::OPT, $meta );

		self::viet_manifest();
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
		/* Ảnh ESP32 mở đầu bằng magic 0xE9. Chặn nhầm tệp ngay, khỏi đẩy rác cho máy nạp. */
		$fh = fopen( $f['tmp_name'], 'rb' );
		$b0 = $fh ? fread( $fh, 1 ) : '';
		if ( $fh ) { fclose( $fh ); }
		if ( '' === $b0 || 0xE9 !== ord( $b0[0] ) ) {
			return array( 'ok' => false, 'error' => 'không phải ảnh ESP32 (thiếu magic 0xE9).' );
		}
		$dest = trailingslashit( self::dir() ) . $dest_ten;
		if ( ! @move_uploaded_file( $f['tmp_name'], $dest ) ) {
			return array( 'ok' => false, 'error' => 'ghi vào uploads thất bại (quyền thư mục?).' );
		}
		@chmod( $dest, 0644 );
		return array( 'ok' => true, 'kb' => (int) round( $size / 1024 ) );
	}

	/** Viết latest-ghe.json (thợ nạp) + manifest-usb.json (esp-web-tools) theo tệp đang có. */
	public static function viet_manifest() {
		$meta = self::meta();
		$ver  = isset( $meta['ver'] ) ? (string) $meta['ver'] : 'ghe';
		$app  = self::url_app();
		$mrg  = self::url_merged();

		if ( '' !== $app ) {
			$ota = array( 'name' => 'Ghe Massage QR', 'ver' => $ver, 'url' => $app, 'loai' => 'ghe' );
			@file_put_contents( trailingslashit( self::dir() ) . self::JSON_OTA,
				wp_json_encode( $ota, JSON_UNESCAPED_SLASHES ) );
		}
		if ( '' !== $mrg ) {
			$usb = array(
				'name'    => 'Ghe Massage QR',
				'version' => $ver,
				'new_install_prompt_erase' => true,
				'builds'  => array( array(
					'chipFamily' => 'ESP32',
					'parts'      => array( array( 'path' => $mrg, 'offset' => 0 ) ),
				) ),
			);
			@file_put_contents( trailingslashit( self::dir() ) . self::JSON_USB,
				wp_json_encode( $usb, JSON_UNESCAPED_SLASHES ) );
		}
	}

	/** Xoá toàn bộ firmware ghế đã nạp (app + merged + manifest + option). */
	public static function xoa() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( array( 'ok' => false, 'error' => 'Không đủ quyền.' ) );
		}
		foreach ( array( self::APP_BIN, self::MRG_BIN, self::JSON_OTA, self::JSON_USB ) as $t ) {
			$p = trailingslashit( self::dir() ) . $t;
			if ( file_exists( $p ) ) { @unlink( $p ); }
		}
		delete_option( self::OPT );
		return array( array( 'ok' => true, 'thong_bao' => 'Đã xoá firmware ghế trên web.' ) );
	}
}
