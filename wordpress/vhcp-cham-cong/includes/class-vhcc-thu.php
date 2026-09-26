<?php
/**
 * GỬI THƯ QUA SMTP — để email (bộ hồ sơ nhận việc, phiếu lương, xác nhận ký) THẬT SỰ tới nơi.
 *
 * Anh Thắng 26/09/2026: *"Các bước đều ok, chỉ là chưa nhận được mail"*. `wp_mail()` trả `true`
 * chỉ có nghĩa WordPress đã giao thư cho hàm `mail()` của hosting — nhiều hosting nhận rồi bỏ,
 * hoặc Gmail chặn vì thư đi từ `wordpress@<tên miền>` mà tên miền chưa khai SPF. Nên màn báo
 * "email ✓" mà hộp thư trống trơn.
 *
 * Lớp này cho khai MỘT hộp thư thật (Gmail/Google Workspace với App Password, hoặc email công ty)
 * làm cửa gửi SMTP, kèm nút gửi thử và ghi lại lỗi gửi thật (`wp_mail_failed`).
 *
 * ⚠️ CHƯA KHAI MÁY CHỦ SMTP THÌ ĐỨNG IM — không đụng gì tới cách gửi đang có (kể cả khi site đã
 *    có plugin SMTP riêng như WP Mail SMTP: để trống ô máy chủ ở đây là nhường cho plugin ấy).
 * ⚠️ Mật khẩu KHÔNG BAO GIỜ in lại ra màn — ô để trống khi lưu nghĩa là giữ mật khẩu cũ.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Thu {

	const O = 'vhcc_smtp';
	const O_LOI = 'vhcc_thu_loi';

	/** Khai SMTP = cầm chìa khoá hộp thư công ty → bậc Admin. */
	const QUYEN = 'he_thong';

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'cai' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'ghi_loi' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'tu_email' ), 20 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'tu_ten' ), 20 );
	}

	public static function cfg() {
		$c = get_option( self::O, array() );
		$c = is_array( $c ) ? $c : array();
		return array_merge( array( 'host' => '', 'port' => 587, 'bao_mat' => 'tls', 'tk' => '', 'mk' => '',
			'tu_email' => '', 'tu_ten' => '' ), $c );
	}

	public static function co() { $c = self::cfg(); return '' !== trim( (string) $c['host'] ); }

	/** `phpmailer_init` — chuyển sang SMTP khi đã khai máy chủ. */
	public static function cai( $pm ) {
		$c = self::cfg();
		if ( '' === trim( (string) $c['host'] ) || ! is_object( $pm ) ) { return; }
		$pm->isSMTP();
		$pm->Host       = (string) $c['host'];
		$pm->Port       = (int) $c['port'];
		$pm->SMTPSecure = in_array( $c['bao_mat'], array( 'tls', 'ssl' ), true ) ? $c['bao_mat'] : '';
		$pm->SMTPAutoTLS = ( 'tls' === $c['bao_mat'] );
		$pm->SMTPAuth   = '' !== (string) $c['tk'];
		$pm->Username   = (string) $c['tk'];
		$pm->Password   = (string) $c['mk'];
		$pm->Timeout    = 15;
	}

	/** Địa chỉ "Từ": địa chỉ đã khai; không khai mà có tài khoản SMTP thì dùng chính tài khoản ấy
	    (Gmail đổi "Từ" về tài khoản đăng nhập nếu khác — khai lệch chỉ làm thư bị gắn cờ). */
	public static function tu_email( $e ) {
		$c = self::cfg();
		if ( ! self::co() ) { return $e; }
		if ( is_email( (string) $c['tu_email'] ) ) { return (string) $c['tu_email']; }
		return is_email( (string) $c['tk'] ) ? (string) $c['tk'] : $e;
	}

	public static function tu_ten( $t ) {
		$c = self::cfg();
		if ( '' !== trim( (string) $c['tu_ten'] ) ) { return (string) $c['tu_ten']; }
		return self::co() ? VHCC_Pdf::ten_cong_ty() : $t;
	}

	/** `wp_mail_failed` — giữ lỗi gửi gần nhất để màn nói ra được vì sao thư không đi. */
	public static function ghi_loi( $err ) {
		$chu = ( is_object( $err ) && method_exists( $err, 'get_error_message' ) ) ? $err->get_error_message() : (string) $err;
		update_option( self::O_LOI, array( 'luc' => current_time( 'mysql' ), 'loi' => substr( $chu, 0, 500 ) ) );
	}

	public static function loi_gan_nhat() {
		$l = get_option( self::O_LOI, null );
		return is_array( $l ) ? $l : null;
	}

	public static function dat( $u, $dat ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Cấu hình gửi email' ) );
		}
		$c = self::cfg();
		$g = function ( $k ) use ( $dat ) { return trim( (string) ( isset( $dat[ $k ] ) ? $dat[ $k ] : '' ) ); };
		$c['host'] = $g( 'host' );
		$c['port'] = (int) $g( 'port' );
		if ( '' !== $c['host'] && ( $c['port'] < 1 || $c['port'] > 65535 ) ) {
			return array( 'ok' => false, 'error' => 'Cổng SMTP không hợp lệ (thường là 587 cho TLS, 465 cho SSL).' );
		}
		$c['bao_mat'] = in_array( $g( 'bao_mat' ), array( 'tls', 'ssl', 'khong' ), true ) ? $g( 'bao_mat' ) : 'tls';
		$c['tk'] = $g( 'tk' );
		if ( '' !== $g( 'mk' ) ) { $c['mk'] = str_replace( ' ', '', $g( 'mk' ) ); }   // App Password Gmail hay có dấu cách
		if ( ! empty( $dat['xoa_mk'] ) ) { $c['mk'] = ''; }
		$c['tu_email'] = strtolower( $g( 'tu_email' ) );
		if ( '' !== $c['tu_email'] && ! is_email( $c['tu_email'] ) ) { return array( 'ok' => false, 'error' => 'Email gửi đi không đúng dạng.' ); }
		$c['tu_ten'] = $g( 'tu_ten' );
		update_option( self::O, $c );
		return array( 'ok' => true );
	}

	/** Gửi một thư thử. Trả kèm lỗi thật nếu hỏng. */
	public static function thu( $u, $to ) {
		if ( ! VHCC_Vai::duoc( $u, self::QUYEN ) ) {
			return array( 'ok' => false, 'error' => VHCC_Vai::loi( $u, self::QUYEN, 'Gửi thư thử' ) );
		}
		$to = strtolower( trim( (string) $to ) );
		if ( ! is_email( $to ) ) { return array( 'ok' => false, 'error' => 'Email nhận thư thử không đúng dạng.' ); }
		delete_option( self::O_LOI );
		$ok = wp_mail( $to, 'Thư thử — ' . VHCC_Pdf::ten_cong_ty(),
			'<p>Đây là thư thử từ hệ thống chấm công. Nhận được thư này nghĩa là email bộ hồ sơ nhận việc và phiếu lương sẽ tới nơi.</p>'
			. '<p>Cách gửi: ' . ( self::co() ? 'SMTP ' . esc_html( self::cfg()['host'] ) : 'mặc định của hosting (hàm mail)' ) . '.</p>',
			array( 'Content-Type: text/html; charset=UTF-8' ) );
		$l = self::loi_gan_nhat();
		if ( ! $ok ) {
			return array( 'ok' => false, 'error' => 'Gửi KHÔNG được' . ( $l ? ': ' . $l['loi'] : '.' ) );
		}
		return array( 'ok' => true, 'smtp' => self::co() );
	}
}
