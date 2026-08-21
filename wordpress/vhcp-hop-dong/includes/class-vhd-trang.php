<?php
/**
 * TRANG /hop-dong/ — phục vụ GIAO DIỆN GỐC của app Apps Script.
 *
 * Không chép Index.html vào plugin. Mỗi lần mở trang (hoặc sau 10 phút nhớ tạm) plugin lấy
 * giao diện thẳng từ project Apps Script rồi chèn thêm đúng một khối <script> để
 * `google.script.run` chạy qua WordPress. Nhờ vậy:
 *   - sửa giao diện bên Apps Script là trang web có bản mới, không phải cập nhật plugin;
 *   - không tồn tại "bản chép" để lệch với bản gốc.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_Trang {

	public static function slug() {
		$s = get_option( 'vhd_slug' );
		$s = $s ? sanitize_title( $s ) : 'hop-dong';
		return $s ? $s : 'hop-dong';
	}

	public static function url() {
		if ( get_option( 'permalink_structure' ) ) { return home_url( '/' . self::slug() . '/' ); }
		return add_query_arg( 'vhd', 'app', home_url( '/' ) );
	}

	public static function init() {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?vhd_app=1', 'top' );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function query_vars( $v ) {
		$v[] = 'vhd_app';
		return $v;
	}

	public static function maybe_render() {
		$is = ( (int) get_query_var( 'vhd_app' ) === 1 );
		if ( ! $is && isset( $_GET['vhd'] ) && $_GET['vhd'] === 'app' ) { $is = true; }
		if ( ! $is ) { return; }

		if ( isset( $_GET['vhd_api'] ) ) {
			VHD_API::trang();
			exit;
		}
		self::render();
		exit;
	}

	/**
	 * Danh sách hàm giao diện được gọi — ĐỌC TỪ CHÍNH file cau-noi.gs trong plugin.
	 *
	 * Khai hai nơi (PHP và .gs) thì sớm muộn lệch: thêm hàm ở .gs mà quên PHP là giao diện
	 * không có phương thức đó, bấm nút không xảy ra gì và cũng không báo lỗi. Nên chỉ có MỘT
	 * nơi khai, ở file .gs mà anh phải dán sang Apps Script.
	 */
	public static function ds_ham() {
		$file = VHD_DIR . 'apps-script/cau-noi.gs';
		$out  = array();
		if ( is_readable( $file ) ) {
			$src = file_get_contents( $file );
			if ( preg_match( '/CN_CHO_PHEP\s*=\s*\[(.*?)\]/s', $src, $m ) ) {
				if ( preg_match_all( "/'([A-Za-z_][A-Za-z0-9_]*)'/", $m[1], $m2 ) ) {
					$out = $m2[1];
				}
			}
		}
		$out[] = 'login';
		$out[] = 'vhdLogout';
		return array_values( array_unique( $out ) );
	}

	private static function khoi_head() {
		$cfg = array(
			'endpoint' => esc_url_raw( rest_url( 'vhd/v1/call' ) ),
			'trang'    => esc_url_raw( add_query_arg( 'vhd_api', '1', self::url() ) ),
			'fns'      => self::ds_ham(),
			'ver'      => VHD_VERSION,
		);
		$out  = '<script>window.VHD_CFG=' . wp_json_encode( $cfg ) . ';</script>' . "\n";
		$out .= '<script src="' . esc_url( VHD_URL . 'assets/js/cau-noi.js' ) . '?ver='
			. rawurlencode( VHD_VERSION ) . '"></script>' . "\n";
		return $out;
	}

	public static function render() {
		$r = VHD_CauNoi::giao_dien();
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header_remove( 'X-Frame-Options' );

		if ( empty( $r['ok'] ) ) {
			status_header( 500 );
			echo self::trang_loi( isset( $r['error'] ) ? $r['error'] : 'Không rõ' );
			return;
		}

		$html = (string) $r['html'];
		$head = self::khoi_head();

		// Chèn TRƯỚC </head> để shim có mặt sớm hơn mọi script của app gốc. Không có </head>
		// (giao diện Apps Script đôi khi chỉ là một mảnh) thì chèn lên đầu.
		$vt = stripos( $html, '</head>' );
		if ( $vt !== false ) {
			$html = substr( $html, 0, $vt ) . $head . substr( $html, $vt );
		} else {
			$html = $head . $html;
		}

		echo $html;
	}

	/** Trang lỗi nói rõ sai ở bước nào — không để màn hình trắng. */
	private static function trang_loi( $loi ) {
		$exec = VHD_CauNoi::url();
		$h  = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
		$h .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
		$h .= '<title>Thư viện Hợp đồng — chưa nối được</title></head>';
		$h .= '<body style="font:14px/1.6 \'Segoe UI\',Arial,sans-serif;background:#f0f4f8;color:#0f172a;margin:0;padding:40px 20px">';
		$h .= '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;padding:26px 28px;box-shadow:0 1px 6px rgba(0,0,0,.08)">';
		$h .= '<h1 style="font-size:19px;margin:0 0 6px">Chưa nối được với app Thư viện Hợp đồng</h1>';
		$h .= '<div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:11px 13px;margin:14px 0">'
			. esc_html( $loi ) . '</div>';
		$h .= '<p><b>Địa chỉ /exec đang khai:</b> ' . ( $exec ? '<code>' . esc_html( $exec ) . '</code>' : '<i>chưa khai</i>' ) . '</p>';
		$h .= '<p>Kiểm theo thứ tự:</p><ol>';
		$h .= '<li>Đã dán file <code>CauNoi.gs</code> vào project Apps Script của app hợp đồng chưa?</li>';
		$h .= '<li>Script Properties đã có <code>WEB_KEY</code> đúng bằng khoá trong Cài đặt của plugin chưa?</li>';
		$h .= '<li>Đã <b>Deploy → New version</b> sau khi dán chưa? (dán mà không deploy thì bản đang chạy vẫn là bản cũ)</li>';
		$h .= '<li>Vào <b>Cài đặt → Thư viện hợp đồng</b> bấm <b>Thử cầu nối</b> để xem thông báo chi tiết.</li>';
		$h .= '</ol>';
		$h .= '<p style="color:#64748b;font-size:12.5px">Dữ liệu hợp đồng không bị ảnh hưởng — nó nằm trong Google Sheet, '
			. 'app Apps Script vẫn mở được như trước.</p>';
		$h .= '</div></body></html>';
		return $h;
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'height' => '1000' ), $atts, 'vhd_hop_dong' );
		$hh = preg_replace( '/[^0-9a-z%]/i', '', (string) $a['height'] );
		if ( $hh === '' ) { $hh = '1000'; }
		if ( is_numeric( $hh ) ) { $hh .= 'px'; }
		return '<iframe src="' . esc_url( self::url() ) . '" style="width:100%;height:' . esc_attr( $hh )
			. ';border:0;display:block" loading="lazy" title="Thư viện Hợp đồng"></iframe>';
	}
}
