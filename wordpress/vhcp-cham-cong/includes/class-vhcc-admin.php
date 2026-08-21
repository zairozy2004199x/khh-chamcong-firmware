<?php
/**
 * Trang Cài đặt trong wp-admin: khai /exec, sinh khoá, thử cầu nối, chọn nguồn người dùng.
 *
 * Mọi thông báo phải NÓI RÕ SAI Ở BƯỚC NÀO. Cầu nối có 4 chỗ hỏng được (chưa dán CauNoiChamCong.gs,
 * sai WEB_KEY, chưa Deploy bản mới, sai URL) mà nếu chỉ báo "lỗi" thì phải mò cả bốn.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page(
			'Chấm Công', 'Chấm Công', self::CAP, 'vhcc', array( __CLASS__, 'page' ),
			'dashicons-portfolio', 57
		);
	}

	public static function handle_post() {
		if ( ! isset( $_POST['vhcc_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['vhcc_action'] ) );
		check_admin_referer( 'vhcc_' . $action );

		if ( $action === 'luu' ) {
			$slug = isset( $_POST['vhcc_slug'] ) ? sanitize_title( wp_unslash( $_POST['vhcc_slug'] ) ) : 'cham-cong';
			if ( $slug === '' ) { $slug = 'cham-cong'; }
			$cu = get_option( 'vhcc_slug' );
			update_option( 'vhcc_slug', $slug );
			if ( $cu !== $slug ) { update_option( 'vhcc_flush_rewrite', 1 ); }

			$url = isset( $_POST['vhcc_exec_url'] ) ? esc_url_raw( wp_unslash( $_POST['vhcc_exec_url'] ) ) : '';
			update_option( 'vhcc_exec_url', $url );

			$nguon = ( isset( $_POST['vhcc_nguon'] ) && $_POST['vhcc_nguon'] === 'rieng' ) ? 'rieng' : 'chung';
			update_option( 'vhcc_nguon_nguoidung', $nguon );

			$vt = array();
			foreach ( (array) ( isset( $_POST['vhcc_vai_tro'] ) ? $_POST['vhcc_vai_tro'] : array() ) as $x ) {
				$x = sanitize_text_field( wp_unslash( $x ) );
				if ( in_array( $x, VHCC_Auth::VAI_TRO_TAT_CA, true ) ) { $vt[] = $x; }
			}
			update_option( 'vhcc_vai_tro_vao', $vt );   // rỗng -> vai_tro_vao() tự về mặc định

			VHCC_CauNoi::bao_dam_khoa();
			VHCC_CauNoi::xoa_cache_giao_dien();   // đổi cấu hình thì lấy lại giao diện, khỏi dùng bản nhớ tạm
			self::ve( 'saved' );
		}

		if ( $action === 'khoamoi' ) {
			update_option( 'vhcc_web_key', bin2hex( random_bytes( 24 ) ) );
			self::ve( 'khoamoi' );
		}

		if ( $action === 'lammoi' ) {
			VHCC_CauNoi::xoa_cache_giao_dien();
			self::ve( 'lammoi' );
		}

		if ( $action === 'thu' ) {
			set_transient( 'vhcc_thu_' . get_current_user_id(), VHCC_CauNoi::thu(), 120 );
			self::ve( 'thu' );
		}

		if ( $action === 'mokhoa' ) {
			VHCC_Auth::mo_khoa();
			self::ve( 'mokhoa' );
		}
	}

	private static function ve( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'vhcc', 'vhcc_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function page() {
		$slug  = get_option( 'vhcc_slug', 'cham-cong' );
		$exec  = VHCC_CauNoi::url();
		$khoa  = VHCC_CauNoi::bao_dam_khoa();
		$nguon = VHCC_Auth::nguon();
		$msg   = isset( $_GET['vhcc_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['vhcc_msg'] ) ) : '';

		echo '<div class="wrap"><h1>Chấm Công</h1>';

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
			$r = get_transient( 'vhcc_thu_' . get_current_user_id() );
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

		echo '<h2>Mở hệ thống chấm công</h2><p><a class="button button-primary" target="_blank" href="'
			. esc_url( VHCC_Trang::url() ) . '">' . esc_html( VHCC_Trang::url() ) . '</a></p>';

		echo '<div class="notice notice-info"><p><b>Plugin này không giữ dữ liệu chấm công.</b> '
			. 'Hợp đồng vẫn nằm trong Google Sheet và toàn bộ nghiệp vụ vẫn ở app Apps Script. '
			. 'WordPress chỉ lo cổng PIN, giữ khoá bí mật và phục vụ giao diện gốc.</p></div>';

		echo '<form method="post">';
		wp_nonce_field( 'vhcc_luu' );
		echo '<input type="hidden" name="vhcc_action" value="luu">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="vhcc_slug">Đường dẫn trang</label></th><td>'
			. esc_html( home_url( '/' ) ) . '<input name="vhcc_slug" id="vhcc_slug" value="' . esc_attr( $slug )
			. '" class="regular-text"> /<p class="description">Mặc định <code>cham-cong</code>.</p></td></tr>';

		echo '<tr><th scope="row"><label for="vhcc_exec_url">Địa chỉ /exec của app chấm công</label></th><td>'
			. '<input name="vhcc_exec_url" id="vhcc_exec_url" value="' . esc_attr( $exec ) . '" class="large-text code" '
			. 'placeholder="https://script.google.com/…/exec">'
			. '<p class="description">Lấy ở Apps Script → Deploy → Manage deployments → Web app URL.</p></td></tr>';

		echo '<tr><th scope="row">Khoá cầu nối (WEB_KEY)</th><td><code style="user-select:all">'
			. esc_html( $khoa ) . '</code>'
			. '<p class="description">Dán đúng chuỗi này vào Apps Script → Project Settings → Script Properties, '
			. 'tên thuộc tính <code>WEB_KEY</code>. Đây là thứ duy nhất chặn người lạ ghi vào sheet chấm công — '
			. 'đừng dán vào chỗ nào khác.</p></td></tr>';

		echo '<tr><th scope="row">Nguồn người dùng &amp; PIN</th><td>';
		echo '<label><input type="radio" name="vhcc_nguon" value="chung"' . checked( $nguon, 'chung', false ) . '> '
			. 'Dùng chung với plugin <b>Vận hành chi phí</b> (khuyến nghị)</label><br>';
		echo '<label><input type="radio" name="vhcc_nguon" value="rieng"' . checked( $nguon, 'rieng', false ) . '> '
			. 'Danh sách riêng của plugin này</label>';
		echo '<p class="description">Dùng chung thì thêm/sửa/xoá nhân sự vẫn làm ở tab ⚙️ Cấu hình của app chi phí — '
			. 'khai một lần cho cả hai hệ thống. Khai hai nơi là sớm muộn xoá một nơi quên nơi kia.</p>';
		if ( $nguon === 'chung' ) {
			$u = VHCC_Auth::users();
			if ( is_wp_error( $u ) ) {
				echo '<p style="color:#b32d2e"><b>' . esc_html( $u->get_error_message() ) . '</b></p>';
			} else {
				$duoc = 0;
				foreach ( $u as $x ) {
					if ( in_array( $x['vaiTro'], VHCC_Auth::VAI_TRO_VAO, true ) ) { $duoc++; }
				}
				echo '<p>Đang đọc được <b>' . count( $u ) . '</b> người, trong đó <b>' . $duoc
					. '</b> người vào được hệ thống chấm công (' . esc_html( implode( ' · ', VHCC_Auth::vai_tro_vao() ) ) . ').</p>';
			}
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">Vai trò vào được</th><td>';
		$dang_cho = VHCC_Auth::vai_tro_vao();
		foreach ( VHCC_Auth::VAI_TRO_TAT_CA as $vt ) {
			echo '<label style="margin-right:16px"><input type="checkbox" name="vhcc_vai_tro[]" value="'
				. esc_attr( $vt ) . '"' . checked( in_array( $vt, $dang_cho, true ), true, false ) . '> '
				. esc_html( $vt ) . '</label>';
		}
		echo '<p class="description">Dữ liệu chấm công là căn cứ tính lương nên mặc định chỉ '
			. esc_html( implode( ' · ', VHCC_Auth::VAI_TRO_MAC_DINH ) ) . '. Cần cho cửa hàng trưởng xem '
			. 'bảng công cơ sở mình thì tích thêm <b>Nhân viên</b> — nhưng lưu ý app gốc có thể '
			. 'KHÔNG lọc theo người, tức là tích vào là họ xem được bảng công của mọi người. '
			. 'Bỏ tích hết thì quay về mặc định (không bao giờ để rỗng — rỗng là không ai vào được, '
			. 'kể cả Admin).</p></td></tr>';

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
			wp_nonce_field( 'vhcc_' . $act );
			echo '<input type="hidden" name="vhcc_action" value="' . esc_attr( $act ) . '">';
			echo '<button class="button">' . esc_html( $nhan ) . '</button></form>';
		}
		echo '</p><p class="description">"Làm mới giao diện" dùng khi vừa sửa Index.html bên Apps Script mà trang web '
			. 'còn hiện bản cũ (plugin nhớ tạm giao diện 10 phút).</p>';

		echo '<hr><h2>File cần dán sang Apps Script</h2>';
		echo '<p>Mở project Apps Script của app chấm công → File → New → Script file, đặt tên <code>CauNoi</code>, '
			. 'dán toàn bộ nội dung file <code>apps-script/cau-noi.gs</code> trong plugin này. '
			. 'Hướng dẫn từng bước nằm ngay đầu file đó.</p>';
		$f = VHCC_DIR . 'apps-script/cau-noi.gs';
		if ( is_readable( $f ) ) {
			echo '<textarea readonly rows="14" style="width:100%;font-family:Consolas,monospace;font-size:12px">'
				. esc_textarea( file_get_contents( $f ) ) . '</textarea>';
		}
		echo '</div>';
	}
}
