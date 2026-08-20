<?php
/** TRANG QUẢN TRỊ — link mở app, nhập dữ liệu cũ từ CSV, cài đặt. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCP_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page( 'Vận Hành Chi Phí', 'Vận Hành Chi Phí', self::CAP, 'vhcp', array( __CLASS__, 'page_main' ), 'dashicons-money-alt', 58 );
		add_submenu_page( 'vhcp', 'Nhập dữ liệu từ Google Sheet', 'Nhập dữ liệu', self::CAP, 'vhcp-import', array( __CLASS__, 'page_import' ) );
		add_submenu_page( 'vhcp', 'Cài đặt Vận Hành Chi Phí', 'Cài đặt', self::CAP, 'vhcp-settings', array( __CLASS__, 'page_settings' ) );
	}

	// ---------------------------------------------------------------- xử lý form

	public static function handle_post() {
		if ( ! isset( $_POST['vhcp_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['vhcp_action'] ) );
		check_admin_referer( 'vhcp_' . $action );

		if ( $action === 'settings' ) {
			$slug = isset( $_POST['vhcp_slug'] ) ? sanitize_title( wp_unslash( $_POST['vhcp_slug'] ) ) : 'chi-phi';
			if ( $slug === '' ) { $slug = 'chi-phi'; }
			$old = get_option( 'vhcp_slug' );
			update_option( 'vhcp_slug', $slug );
			if ( $old !== $slug ) { update_option( 'vhcp_flush_rewrite', 1 ); }

			$tz = isset( $_POST['vhcp_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcp_timezone'] ) ) : 'Asia/Bangkok';
			if ( in_array( $tz, timezone_identifiers_list(), true ) ) { update_option( 'vhcp_timezone', $tz ); }

			$secret = isset( $_POST['vhcp_sso_secret'] ) ? trim( (string) wp_unslash( $_POST['vhcp_sso_secret'] ) ) : '';
			VHCP_Meta::set( 'SSO_SECRET', $secret );

			VHCP_Cfg::clear_cache();
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcp-settings', 'vhcp_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $action === 'import' ) {
			$type    = isset( $_POST['vhcp_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcp_type'] ) ) : '';
			$text    = isset( $_POST['vhcp_csv'] ) ? (string) wp_unslash( $_POST['vhcp_csv'] ) : '';
			$opts    = array(
				'header'  => ! empty( $_POST['vhcp_header'] ),
				'replace' => ! empty( $_POST['vhcp_replace'] ),
				'ma'      => isset( $_POST['vhcp_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcp_ma'] ) ) : '',
			);

			if ( ! empty( $_FILES['vhcp_file']['tmp_name'] ) && is_uploaded_file( $_FILES['vhcp_file']['tmp_name'] ) ) {
				$raw = file_get_contents( $_FILES['vhcp_file']['tmp_name'] );
				if ( $raw !== false && $raw !== '' ) { $text = $raw; }
			}

			$res = VHCP_Import::run( $type, $text, $opts );
			set_transient( 'vhcp_import_res_' . get_current_user_id(), $res, 60 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcp-import' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $action === 'flush' ) {
			update_option( 'vhcp_flush_rewrite', 1 );
			VHCP_DB::install();
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcp', 'vhcp_msg' => 'flushed' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	// ---------------------------------------------------------------- trang

	public static function page_main() {
		$url = VHCP_App::app_url();
		echo '<div class="wrap"><h1>Vận Hành Chi Phí</h1>';

		if ( isset( $_GET['vhcp_msg'] ) && $_GET['vhcp_msg'] === 'flushed' ) {
			echo '<div class="notice notice-success"><p>Đã kiểm tra lại bảng dữ liệu và làm mới đường dẫn.</p></div>';
		}

		echo '<h2>Mở app</h2><p><a class="button button-primary" target="_blank" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
		echo '<p>Nhúng vào 1 trang WordPress bằng shortcode: <code>[vhcp_app height="900"]</code>. ';
		echo 'Nhúng vào trang tổng K&amp;H: đặt biến <code>CHIPHI_URL</code> = đường dẫn trên (thêm <code>?sso=&lt;token&gt;</code> nếu dùng đăng nhập một lần).</p>';

		echo '<h2>Dữ liệu đang có</h2><table class="widefat striped" style="max-width:560px"><tbody>';
		foreach ( VHCP_Import::counts() as $label => $c ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td style="text-align:right"><b>' . esc_html( number_format_i18n( $c ) ) . '</b></td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Bảo trì</h2><form method="post">';
		wp_nonce_field( 'vhcp_flush' );
		echo '<input type="hidden" name="vhcp_action" value="flush">';
		echo '<p><button class="button">Kiểm tra lại bảng dữ liệu + làm mới đường dẫn</button> ';
		echo '<span class="description">Chạy khi mới cập nhật plugin hoặc mở app bị lỗi 404.</span></p></form>';
		echo '</div>';
	}

	public static function page_import() {
		$res = get_transient( 'vhcp_import_res_' . get_current_user_id() );
		if ( $res ) { delete_transient( 'vhcp_import_res_' . get_current_user_id() ); }

		echo '<div class="wrap"><h1>Nhập dữ liệu từ Google Sheet</h1>';
		if ( is_array( $res ) ) {
			if ( ! empty( $res['success'] ) ) {
				echo '<div class="notice notice-success"><p>Đã nạp <b>' . (int) $res['inserted'] . '</b> dòng';
				if ( ! empty( $res['skipped'] ) ) { echo ', bỏ qua ' . (int) $res['skipped'] . ' dòng trống/không hợp lệ'; }
				echo '. Mã tài khoản được gán ngay khi nạp theo danh mục Loại chi phí.</p>';
				if ( ! empty( $res['thieuMa'] ) ) {
					echo '<p><b>⚠ ' . (int) $res['thieuMa'] . ' dòng chưa có TK Nợ</b> — do loại chi phí chưa khai mã, hoặc file không có cột "Loại chi phí". '
						. 'Khai mã ở app (⚙️ Cấu hình → 🆕 Khai mã chi phí) rồi bấm <b>🔗 Gán mã cho dòng cũ</b>; xem chỗ nào còn thiếu ở tab <b>🔎 Tra theo mã</b>.</p>';
				}
				echo '</div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( isset( $res['error'] ) ? $res['error'] : 'Lỗi không rõ' ) . '</p></div>';
			}
		}

		echo '<p><b>Cách làm:</b> mở bảng tính Google → chọn từng tab → <em>Tệp → Tải xuống → Giá trị được phân tách bằng dấu phẩy (.csv)</em> → tải file lên đây. ';
		echo 'Nạp theo thứ tự: các tab <code>CH_*</code> (cấu hình) → <code>DonHang</code> → <code>TamUng</code> → <code>ChiPhi</code> → <code>DA_Index</code> → từng tab dự án → <code>MK_Don</code>/<code>MK_Line</code> → <code>BP_Index</code> → từng tab đợt.</p>';
		echo '<p><b>Lưu ý ngày tháng:</b> file CSV được đọc theo kiểu Việt Nam (ngày trước — 20/08/2026). Nếu bảng tính của anh đang xuất kiểu Mỹ (tháng trước) thì đổi Locale của bảng tính sang Việt Nam trước khi tải xuống.</p>';

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'vhcp_import' );
		echo '<input type="hidden" name="vhcp_action" value="import">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="vhcp_type">Tab đang nạp</label></th><td><select name="vhcp_type" id="vhcp_type" required>';
		foreach ( VHCP_Import::types() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['label'] ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="vhcp_ma">Dự án / Đợt nhận dòng</label></th><td><select name="vhcp_ma" id="vhcp_ma"><option value="">— chỉ cần khi nạp tab của 1 dự án / 1 đợt —</option>';
		$da = VHCP_DuAn::list_du_an();
		foreach ( (array) $da['items'] as $x ) {
			echo '<option value="' . esc_attr( $x['maDA'] ) . '">🔧 ' . esc_html( $x['ten'] . ' · ' . $x['loai'] ) . '</option>';
		}
		$bp = VHCP_BP::list_bp( 'all' );
		foreach ( (array) $bp['items'] as $x ) {
			echo '<option value="' . esc_attr( $x['ma'] ) . '">' . ( $x['loai'] === 'Công tác' ? '✈️ ' : '🛠️ ' ) . esc_html( $x['ten'] ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">File CSV</th><td><input type="file" name="vhcp_file" accept=".csv,.tsv,.txt,text/csv">'
			. '<p class="description"><b>Phải là file .csv</b> — nạp thẳng .xlsx / .xls / .zip sẽ bị từ chối vì đó là file nhị phân, đọc ra ký tự rác. '
			. 'Trong Google Sheet: <em>Tệp → Tải xuống → Giá trị được phân tách bằng dấu phẩy (.csv)</em>. '
			. 'Trong Excel: <em>Tệp → Lưu dưới dạng → CSV UTF-8</em>.</p></td></tr>';
		echo '<tr><th scope="row"><label for="vhcp_csv">…hoặc dán nội dung</label></th><td><textarea name="vhcp_csv" id="vhcp_csv" rows="8" style="width:100%;font-family:monospace"></textarea></td></tr>';
		echo '<tr><th scope="row">Tùy chọn</th><td>';
		echo '<label><input type="checkbox" name="vhcp_header" value="1" checked> Dòng đầu là tiêu đề (bỏ qua)</label><br>';
		echo '<label><input type="checkbox" name="vhcp_replace" value="1"> Xóa dữ liệu cũ của bảng này trước khi nạp</label>';
		echo '</td></tr>';
		echo '</tbody></table>';
		submit_button( 'Nạp dữ liệu' );
		echo '</form></div>';
	}

	public static function page_settings() {
		$slug   = get_option( 'vhcp_slug', 'chi-phi' );
		$tz     = get_option( 'vhcp_timezone', 'Asia/Bangkok' );
		$secret = (string) VHCP_Meta::get( 'SSO_SECRET', '' );

		echo '<div class="wrap"><h1>Cài đặt Vận Hành Chi Phí</h1>';
		if ( isset( $_GET['vhcp_msg'] ) && $_GET['vhcp_msg'] === 'saved' ) {
			echo '<div class="notice notice-success"><p>Đã lưu.</p></div>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'vhcp_settings' );
		echo '<input type="hidden" name="vhcp_action" value="settings">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="vhcp_slug">Đường dẫn app</label></th><td>' . esc_html( home_url( '/' ) ) . '<input name="vhcp_slug" id="vhcp_slug" value="' . esc_attr( $slug ) . '" class="regular-text"> /<p class="description">Mặc định <code>chi-phi</code>. Đổi xong hãy mở lại app 1 lần để đường dẫn được nạp.</p></td></tr>';
		echo '<tr><th scope="row"><label for="vhcp_timezone">Múi giờ</label></th><td><select name="vhcp_timezone" id="vhcp_timezone">';
		foreach ( timezone_identifiers_list() as $z ) {
			echo '<option value="' . esc_attr( $z ) . '"' . selected( $z, $tz, false ) . '>' . esc_html( $z ) . '</option>';
		}
		echo '</select><p class="description">App cũ chạy múi <code>Asia/Bangkok</code> (GMT+7).</p></td></tr>';
		echo '<tr><th scope="row"><label for="vhcp_sso_secret">SSO_SECRET</label></th><td><input name="vhcp_sso_secret" id="vhcp_sso_secret" value="' . esc_attr( $secret ) . '" class="regular-text code"><p class="description">Chuỗi bí mật dùng chung với trang tổng K&amp;H để đăng nhập một lần (<code>?sso=&lt;token&gt;</code>). Để trống nếu chỉ đăng nhập bằng PIN.</p></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form></div>';
	}
}
