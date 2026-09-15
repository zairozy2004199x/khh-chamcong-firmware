<?php
/**
 * TỰ CẬP NHẬT TỪ GITHUB — hiện nút "Cập nhật ngay" ở màn Plugin, trang "Tự cập nhật" ở menu.
 *
 * Hai nguồn, thử theo thứ tự:
 *   1. NHÁNH của repo công khai (mặc định `main`, đổi được ở Cài đặt) — KHÔNG cần token.
 *      Đọc số phiên bản từ raw.githubusercontent.com/<repo>/<nhánh>/khbc-bao-cao-chi-phi/khbc-bao-cao-chi-phi.php,
 *      gói tải là zip cả nhánh (codeload.github.com); sau khi giải nén, lọc ra đúng thư mục
 *      `khbc-bao-cao-chi-phi/` rồi mới đưa vào wp-content/plugins (xem chon_thu_muc()).
 *      Cùng cách với plugin Ghế Massage (vhcp-ghe) đang chạy: đẩy code lên nhánh là WordPress thấy bản mới.
 *   2. GitHub Releases với tag `khbc-bao-cao-chi-phi-vX.Y.Z` (workflow plugin-release.yml). Repo riêng tư
 *      thì cần khoá đọc (dùng chung `vhcp_gh_token` với plugin Vận Hành Chi Phí trên cùng site).
 *
 * CHỈ NÂNG LÊN BẢN CAO HƠN, không bao giờ tự hạ bản. Khoá truy cập là bí mật — không in ra màn hình.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_TuCapNhat {

	const REPO      = 'zairozy2004199x/khh-chamcong-firmware';
	const THU_MUC   = 'khbc-bao-cao-chi-phi';
	const TIEN_TO   = 'khbc-bao-cao-chi-phi-v';
	const NHANH_MAC_DINH = 'main';
	const O_KHOA    = 'vhcp_gh_token';
	const O_NHANH   = 'khbc_gh_nhanh';
	const O_NHO     = 'khbc_gh_ban_moi';
	const NHO_LAU   = 21600; // 6 giờ

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'chen_ban_moi' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'chi_tiet' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'chon_thu_muc' ), 10, 4 );
	}

	public static function nhanh() {
		$n = trim( (string) get_option( self::O_NHANH, '' ) );
		return $n !== '' ? $n : self::NHANH_MAC_DINH;
	}
	public static function dat_nhanh( $n ) {
		$n = trim( (string) $n );
		$n = preg_replace( '#[^A-Za-z0-9._/\-]#', '', $n );
		update_option( self::O_NHANH, $n );
		delete_transient( self::O_NHO );
	}
	public static function co_khoa() { return '' !== trim( (string) get_option( self::O_KHOA, '' ) ); }
	public static function dat_khoa( $khoa ) {
		$khoa = trim( (string) $khoa );
		if ( '' === $khoa ) { return; }
		update_option( self::O_KHOA, $khoa );
		delete_transient( self::O_NHO );
	}
	public static function xoa_khoa() { delete_option( self::O_KHOA ); delete_transient( self::O_NHO ); }
	public static function quen_nho() { delete_transient( self::O_NHO ); }

	private static function duong() { return plugin_basename( KHBC_DIR . 'khbc-bao-cao-chi-phi.php' ); }

	private static function dau() {
		$dau  = array( 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'khbc-tu-cap-nhat' ), 'timeout' => 15 );
		$khoa = trim( (string) get_option( self::O_KHOA, '' ) );
		if ( '' !== $khoa ) { $dau['headers']['Authorization'] = 'Bearer ' . $khoa; }
		return $dau;
	}

	/**
	 * Hỏi GitHub có bản mới không.
	 * @return array|null [ 'ver', 'zip', 'ghi_chu', 'ngay', 'nguon' => 'nhanh'|'release', 'loi' ]
	 */
	public static function ban_moi( $bo_qua_nho = false ) {
		if ( ! $bo_qua_nho ) {
			$nho = get_transient( self::O_NHO );
			if ( is_array( $nho ) ) { return isset( $nho['ver'] ) ? $nho : null; }
		}
		$kq = self::tu_nhanh();
		if ( ! $kq || ! isset( $kq['ver'] ) ) {
			$kq2 = self::tu_release();
			if ( $kq2 && isset( $kq2['ver'] ) ) { $kq = $kq2; }
			elseif ( $kq2 && isset( $kq2['loi'] ) && ( ! $kq || ! isset( $kq['loi'] ) ) ) { $kq = $kq2; }
		}
		// nhớ 6 giờ nếu hỏi được (kể cả "không có bản mới"), 15 phút nếu lỗi mạng
		$loi = $kq && isset( $kq['loi'] );
		set_transient( self::O_NHO, $kq ? $kq : array( 'khong' => 1 ), $loi ? 900 : self::NHO_LAU );
		update_option( 'khbc_gh_kiem_luc', time() );
		return ( $kq && isset( $kq['ver'] ) ) ? $kq : null;
	}

	/** Kết quả lần hỏi gần nhất (kể cả lỗi) để bày lên trang Tự cập nhật. */
	public static function ket_qua_gan_nhat() {
		$nho = get_transient( self::O_NHO );
		return is_array( $nho ) ? $nho : null;
	}

	/** Nguồn 1: đọc Version trong file plugin trên nhánh. */
	private static function tu_nhanh() {
		$nhanh = self::nhanh();
		$url = 'https://raw.githubusercontent.com/' . self::REPO . '/' . rawurlencode( $nhanh ) . '/' . self::THU_MUC . '/khbc-bao-cao-chi-phi.php';
		$r = wp_remote_get( $url, self::dau() );
		if ( is_wp_error( $r ) ) { return array( 'loi' => 'Nhánh ' . $nhanh . ': ' . $r->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( 200 !== $code ) { return array( 'loi' => 'Nhánh ' . $nhanh . ': GitHub trả ' . $code . ' (nhánh chưa có thư mục plugin, hoặc repo riêng tư cần khoá).' ); }
		if ( ! preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9.]*)/m', (string) wp_remote_retrieve_body( $r ), $m ) ) {
			return array( 'loi' => 'Nhánh ' . $nhanh . ': không đọc được số phiên bản trong file plugin.' );
		}
		$ver = $m[1];
		if ( version_compare( $ver, KHBC_VERSION, '<=' ) ) { return array( 'khong' => 1, 'ver_xa' => $ver, 'nguon' => 'nhanh', 'nhanh' => $nhanh ); }
		return array(
			'ver'     => $ver,
			'zip'     => 'https://codeload.github.com/' . self::REPO . '/zip/refs/heads/' . rawurlencode( $nhanh ),
			'ghi_chu' => 'Bản ' . $ver . ' trên nhánh ' . $nhanh . ' của ' . self::REPO . '.',
			'ngay'    => '',
			'nguon'   => 'nhanh',
			'nhanh'   => $nhanh,
		);
	}

	/** Nguồn 2: GitHub Releases theo tiền tố tag. */
	private static function tu_release() {
		$r = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases?per_page=60', self::dau() );
		if ( is_wp_error( $r ) ) { return array( 'loi' => 'Releases: ' . $r->get_error_message() ); }
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) { return array( 'loi' => 'Releases: GitHub trả ' . (int) wp_remote_retrieve_response_code( $r ) ); }
		$ds = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $ds ) ) { return array( 'loi' => 'Releases: dữ liệu lạ' ); }
		$tot = null;
		foreach ( $ds as $rel ) {
			if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) { continue; }
			$tag = isset( $rel['tag_name'] ) ? (string) $rel['tag_name'] : '';
			if ( 0 !== strpos( $tag, self::TIEN_TO ) ) { continue; }
			$ver = substr( $tag, strlen( self::TIEN_TO ) );
			if ( ! preg_match( '/^\d+(\.\d+)*$/', $ver ) ) { continue; }
			if ( version_compare( $ver, KHBC_VERSION, '<=' ) ) { continue; }
			if ( $tot && version_compare( $ver, $tot['ver'], '<=' ) ) { continue; }
			$zip = '';
			foreach ( (array) ( isset( $rel['assets'] ) ? $rel['assets'] : array() ) as $a ) {
				if ( isset( $a['name'] ) && substr( (string) $a['name'], -4 ) === '.zip' ) { $zip = (string) $a['url']; break; }
			}
			if ( '' === $zip ) { continue; }
			$tot = array( 'ver' => $ver, 'zip' => $zip, 'ghi_chu' => isset( $rel['body'] ) ? (string) $rel['body'] : '', 'ngay' => isset( $rel['published_at'] ) ? substr( (string) $rel['published_at'], 0, 10 ) : '', 'nguon' => 'release' );
		}
		return $tot ? $tot : array( 'khong' => 1, 'nguon' => 'release' );
	}

	public static function chen_ban_moi( $tr ) {
		if ( ! is_object( $tr ) ) { return $tr; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $tr; }
		$duong = self::duong();
		$o = new stdClass();
		$o->slug = dirname( $duong ); $o->plugin = $duong; $o->new_version = $moi['ver'];
		$o->url = 'https://github.com/' . self::REPO; $o->package = self::goi_tai( $moi );
		if ( ! isset( $tr->response ) || ! is_array( $tr->response ) ) { $tr->response = array(); }
		$tr->response[ $duong ] = $o;
		return $tr;
	}

	/** Địa chỉ tải gói. Release của repo riêng tư mang khoá trong URL (bí mật — không log). */
	private static function goi_tai( $moi ) {
		$zip  = $moi['zip'];
		$khoa = trim( (string) get_option( self::O_KHOA, '' ) );
		if ( '' === $khoa || ( isset( $moi['nguon'] ) && $moi['nguon'] === 'nhanh' ) ) { return $zip; }
		return preg_replace( '#^https://#', 'https://' . rawurlencode( $khoa ) . '@', $zip );
	}

	public static function chi_tiet( $ket_qua, $viec, $args ) {
		if ( 'plugin_information' !== $viec ) { return $ket_qua; }
		if ( ! isset( $args->slug ) || $args->slug !== dirname( self::duong() ) ) { return $ket_qua; }
		$moi = self::ban_moi();
		if ( ! $moi ) { return $ket_qua; }
		$o = new stdClass();
		$o->name = 'Báo Cáo Chi Phí (K&H)'; $o->slug = $args->slug; $o->version = $moi['ver'];
		$o->last_updated = $moi['ngay']; $o->download_link = self::goi_tai( $moi );
		$o->sections = array( 'changelog' => wpautop( esc_html( $moi['ghi_chu'] ) ) );
		return $o;
	}

	/**
	 * Sau khi giải nén: zip cả nhánh giải ra `khh-chamcong-firmware-main/` chứa nhiều thư mục;
	 * lấy đúng `khbc-bao-cao-chi-phi/` bên trong đưa lên làm nguồn cài. Zip của Release thì đã đúng
	 * tên; zip tải tay tên khác thì đổi tên về đúng tên plugin để không cài thành bản song song.
	 */
	public static function chon_thu_muc( $nguon, $nguon_xa, $nang_cap, $dau = array() ) {
		global $wp_filesystem;
		if ( ! isset( $dau['plugin'] ) || $dau['plugin'] !== self::duong() ) { return $nguon; }
		if ( ! $wp_filesystem ) { return $nguon; }
		$can = trailingslashit( $nguon_xa ) . self::THU_MUC;
		$con = trailingslashit( $nguon ) . self::THU_MUC;
		if ( $wp_filesystem->exists( trailingslashit( $con ) . 'khbc-bao-cao-chi-phi.php' ) ) {
			// zip cả repo: đưa thư mục con ra ngoài
			if ( $wp_filesystem->exists( $can ) && trailingslashit( $can ) !== trailingslashit( $con ) ) { $wp_filesystem->delete( $can, true ); }
			if ( $wp_filesystem->move( $con, $can, true ) ) { return trailingslashit( $can ); }
			return new WP_Error( 'khbc_move', 'Không tách được thư mục plugin khỏi gói tải về.' );
		}
		if ( trailingslashit( $nguon ) === trailingslashit( $can ) ) { return $nguon; }
		if ( $wp_filesystem->exists( trailingslashit( $nguon ) . 'khbc-bao-cao-chi-phi.php' ) && $wp_filesystem->move( $nguon, $can, true ) ) {
			return trailingslashit( $can );
		}
		return $nguon;
	}
}
