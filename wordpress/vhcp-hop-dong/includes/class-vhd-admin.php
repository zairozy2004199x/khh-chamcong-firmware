<?php
/**
 * Trang Cài đặt trong wp-admin: khai /exec, sinh khoá, thử cầu nối, chọn nguồn người dùng.
 *
 * Mọi thông báo phải NÓI RÕ SAI Ở BƯỚC NÀO. Cầu nối có 4 chỗ hỏng được (chưa dán CauNoi.gs,
 * sai WEB_KEY, chưa Deploy bản mới, sai URL) mà nếu chỉ báo "lỗi" thì phải mò cả bốn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHD_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page(
			'Thư Viện Hợp Đồng', 'Thư Viện Hợp Đồng', self::CAP, 'vhd', array( __CLASS__, 'page' ),
			'dashicons-portfolio', 57
		);
	}

	public static function handle_post() {
		if ( ! isset( $_POST['vhd_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['vhd_action'] ) );
		check_admin_referer( 'vhd_' . $action );

		if ( $action === 'luu' ) {
			$slug = isset( $_POST['vhd_slug'] ) ? sanitize_title( wp_unslash( $_POST['vhd_slug'] ) ) : 'hop-dong';
			if ( $slug === '' ) { $slug = 'hop-dong'; }
			$cu = get_option( 'vhd_slug' );
			update_option( 'vhd_slug', $slug );
			if ( $cu !== $slug ) { update_option( 'vhd_flush_rewrite', 1 ); }

			$url = isset( $_POST['vhd_exec_url'] ) ? esc_url_raw( wp_unslash( $_POST['vhd_exec_url'] ) ) : '';
			update_option( 'vhd_exec_url', $url );

			$nguon = ( isset( $_POST['vhd_nguon'] ) && $_POST['vhd_nguon'] === 'rieng' ) ? 'rieng' : 'chung';
			update_option( 'vhd_nguon_nguoidung', $nguon );

			VHD_CauNoi::bao_dam_khoa();
			VHD_CauNoi::xoa_cache_giao_dien();   // đổi cấu hình thì lấy lại giao diện, khỏi dùng bản nhớ tạm
			self::ve( 'saved' );
		}

		if ( $action === 'khoamoi' ) {
			update_option( 'vhd_web_key', bin2hex( random_bytes( 24 ) ) );
			self::ve( 'khoamoi' );
		}

		if ( $action === 'lammoi' ) {
			VHD_CauNoi::xoa_cache_giao_dien();
			self::ve( 'lammoi' );
		}

		if ( $action === 'thu' ) {
			set_transient( 'vhd_thu_' . get_current_user_id(), VHD_CauNoi::thu(), 120 );
			self::ve( 'thu' );
		}

		if ( $action === 'mokhoa' ) {
			VHD_Auth::mo_khoa();
			self::ve( 'mokhoa' );
		}
	}

	private static function ve( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'vhd', 'vhd_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function page() {
		$slug  = get_option( 'vhd_slug', 'hop-dong' );
		$exec  = VHD_CauNoi::url();
		$khoa  = VHD_CauNoi::bao_dam_khoa();
		$nguon = VHD_Auth::nguon();
		$msg   = isset( $_GET['vhd_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['vhd_msg'] ) ) : '';

		echo '<div class="wrap"><h1>Thư Viện Hợp Đồng</h1>';

		$loi_nhan = array(
			'saved'   => 'Đã lưu.',
			'khoamoi' => 'Đã sinh khoá mới — <b>nhớ cập nhật WEB_KEY trong Script Properties của Apps Script rồi Deploy lại</b>, không thì cầu nối đứt.',
			'lammoi'  => 'Đã xoá nhớ tạm giao diện — lần mở trang tới sẽ lấy bản mới nhất từ Apps Script.',
			'mokhoa'  => 'Đã mở khoá đăng nhập.',
		);
		if ( isset( $loi_nhan[ $msg ] ) ) {
			echo '<div class="notice notice-success"><p>' . wp_kses_post( $loi_nhan[ $msg ] ) . '</p></div>';
		}

		if ( $msg === 'thu' ) {
			$r = get_transient( 'vhd_thu_' . get_current_user_id() );
			if ( is_array( $r ) && ! empty( $r['ok'] ) ) {
				$d = (array) $r['data'];
				echo '<div class="notice notice-success"><p><b>Cầu nối sống.</b> App cho gọi '
					. (int) ( isset( $d['soHam'] ) ? $d['soHam'] : 0 ) . ' hàm · file giao diện: <code>'
					. esc_html( isset( $d['giaoDien'] ) ? $d['giaoDien'] : '?' ) . '</code></p></div>';
			} elseif ( is_array( $r ) ) {
				echo '<div class="notice notice-error"><p><b>Chưa nối được:</b> '
					. esc_html( isset( $r['error'] ) ? $r['error'] : '?' ) . '</p></div>';
			}
		}

		echo '<h2>Mở thư viện hợp đồng</h2><p><a class="button button-primary" target="_blank" href="'
			. esc_url( VHD_Trang::url() ) . '">' . esc_html( VHD_Trang::url() ) . '</a></p>';

		echo '<div class="notice notice-info"><p><b>Plugin này không giữ dữ liệu hợp đồng.</b> '
			. 'Hợp đồng vẫn nằm trong Google Sheet và toàn bộ nghiệp vụ vẫn ở app Apps Script. '
			. 'WordPress chỉ lo cổng PIN, giữ khoá bí mật và phục vụ giao diện gốc.</p></div>';

		echo '<form method="post">';
		wp_nonce_field( 'vhd_luu' );
		echo '<input type="hidden" name="vhd_action" value="luu">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="vhd_slug">Đường dẫn trang</label></th><td>'
			. esc_html( home_url( '/' ) ) . '<input name="vhd_slug" id="vhd_slug" value="' . esc_attr( $slug )
			. '" class="regular-text"> /<p class="description">Mặc định <code>hop-dong</code>.</p></td></tr>';

		echo '<tr><th scope="row"><label for="vhd_exec_url">Địa chỉ /exec của app hợp đồng</label></th><td>'
			. '<input name="vhd_exec_url" id="vhd_exec_url" value="' . esc_attr( $exec ) . '" class="large-text code" '
			. 'placeholder="https://script.google.com/…/exec">'
			. '<p class="description">Lấy ở Apps Script → Deploy → Manage deployments → Web app URL.</p></td></tr>';

		echo '<tr><th scope="row">Khoá cầu nối (WEB_KEY)</th><td><code style="user-select:all">'
			. esc_html( $khoa ) . '</code>'
			. '<p class="description">Dán đúng chuỗi này vào Apps Script → Project Settings → Script Properties, '
			. 'tên thuộc tính <code>WEB_KEY</code>. Đây là thứ duy nhất chặn người lạ ghi vào sheet hợp đồng — '
			. 'đừng dán vào chỗ nào khác.</p></td></tr>';

		echo '<tr><th scope="row">Nguồn người dùng &amp; PIN</th><td>';
		echo '<label><input type="radio" name="vhd_nguon" value="chung"' . checked( $nguon, 'chung', false ) . '> '
			. 'Dùng chung với plugin <b>Vận hành chi phí</b> (khuyến nghị)</label><br>';
		echo '<label><input type="radio" name="vhd_nguon" value="rieng"' . checked( $nguon, 'rieng', false ) . '> '
			. 'Danh sách riêng của plugin này</label>';
		echo '<p class="description">Dùng chung thì thêm/sửa/xoá nhân sự vẫn làm ở tab ⚙️ Cấu hình của app chi phí — '
			. 'khai một lần cho cả hai hệ thống. Khai hai nơi là sớm muộn xoá một nơi quên nơi kia.</p>';
		if ( $nguon === 'chung' ) {
			$u = VHD_Auth::users();
			if ( is_wp_error( $u ) ) {
				echo '<p style="color:#b32d2e"><b>' . esc_html( $u->get_error_message() ) . '</b></p>';
			} else {
				$duoc = 0;
				foreach ( $u as $x ) {
					if ( in_array( $x['vaiTro'], VHD_Auth::VAI_TRO_VAO, true ) ) { $duoc++; }
				}
				echo '<p>Đang đọc được <b>' . count( $u ) . '</b> người, trong đó <b>' . $duoc
					. '</b> người vào được thư viện hợp đồng (Kế toán · Quản lý · Admin).</p>';
			}
		}
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( 'Lưu cài đặt' );
		echo '</form>';

		echo '<hr><h2>Bảo trì</h2><p>';
		foreach ( array(
			'thu'     => 'Thử cầu nối',
			'lammoi'  => 'Làm mới giao diện',
			'mokhoa'  => 'Mở khoá đăng nhập',
			'khoamoi' => 'Sinh khoá mới',
		) as $act => $nhan ) {
			echo '<form method="post" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( 'vhd_' . $act );
			echo '<input type="hidden" name="vhd_action" value="' . esc_attr( $act ) . '">';
			echo '<button class="button">' . esc_html( $nhan ) . '</button></form>';
		}
		echo '</p><p class="description">"Làm mới giao diện" dùng khi vừa sửa Index.html bên Apps Script mà trang web '
			. 'còn hiện bản cũ (plugin nhớ tạm giao diện 10 phút).</p>';

		echo '<hr><h2>File cần dán sang Apps Script</h2>';
		echo '<p>Mở project Apps Script của app hợp đồng → File → New → Script file, đặt tên <code>CauNoi</code>, '
			. 'dán toàn bộ nội dung file <code>apps-script/cau-noi.gs</code> trong plugin này. '
			. 'Hướng dẫn từng bước nằm ngay đầu file đó.</p>';
		$f = VHD_DIR . 'apps-script/cau-noi.gs';
		if ( is_readable( $f ) ) {
			echo '<textarea readonly rows="14" style="width:100%;font-family:Consolas,monospace;font-size:12px">'
				. esc_textarea( file_get_contents( $f ) ) . '</textarea>';
		}
		echo '</div>';
	}
}
