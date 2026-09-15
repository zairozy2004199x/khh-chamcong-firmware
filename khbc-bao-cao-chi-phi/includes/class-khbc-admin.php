<?php
/** TRANG QUẢN TRỊ WORDPRESS — link mở app, cài đặt, khoá GitHub tự cập nhật, mở khoá đăng nhập. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHBC_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page( 'Báo cáo chi phí', 'Báo cáo chi phí', self::CAP, 'khbc', array( __CLASS__, 'page_main' ), 'dashicons-chart-pie', 59 );
		add_submenu_page( 'khbc', 'Cài đặt Báo cáo chi phí', 'Cài đặt', self::CAP, 'khbc-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'khbc', 'Tự cập nhật', 'Tự cập nhật', self::CAP, 'khbc-capnhat', array( __CLASS__, 'page_capnhat' ) );
	}

	public static function handle_post() {
		if ( ! isset( $_POST['khbc_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['khbc_action'] ) );
		check_admin_referer( 'khbc_' . $action );

		if ( $action === 'settings' ) {
			update_option( 'khbc_ten_trang', isset( $_POST['khbc_ten_trang'] ) ? sanitize_text_field( wp_unslash( $_POST['khbc_ten_trang'] ) ) : '' );
			$slug = isset( $_POST['khbc_slug'] ) ? sanitize_title( wp_unslash( $_POST['khbc_slug'] ) ) : '';
			if ( $slug === '' ) { $slug = KHBC_App::SLUG_MAC_DINH; }
			if ( get_option( 'khbc_slug' ) !== $slug ) { update_option( 'khbc_flush_rewrite', 1 ); }
			update_option( 'khbc_slug', $slug );
			$tz = isset( $_POST['khbc_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['khbc_timezone'] ) ) : 'Asia/Bangkok';
			if ( in_array( $tz, timezone_identifiers_list(), true ) ) { update_option( 'khbc_timezone', $tz ); }
			if ( isset( $_POST['khbc_sso_secret'] ) ) { update_option( 'khbc_sso_secret', trim( (string) wp_unslash( $_POST['khbc_sso_secret'] ) ) ); }
			if ( isset( $_POST['khbc_gh_nhanh'] ) ) { KHBC_TuCapNhat::dat_nhanh( wp_unslash( $_POST['khbc_gh_nhanh'] ) ); }
			// Khoá GitHub: ô trống = giữ nguyên (ô không bao giờ hiện khoá đang lưu).
			if ( ! empty( $_POST['khbc_gh_xoa'] ) ) { KHBC_TuCapNhat::xoa_khoa(); }
			elseif ( isset( $_POST['khbc_gh_token'] ) ) { KHBC_TuCapNhat::dat_khoa( wp_unslash( $_POST['khbc_gh_token'] ) ); }
			wp_safe_redirect( add_query_arg( array( 'page' => 'khbc-settings', 'khbc_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'kiemtra' ) {
			KHBC_TuCapNhat::ban_moi( true );
			delete_site_transient( 'update_plugins' ); // để màn Plugin hỏi lại ngay
			wp_safe_redirect( add_query_arg( array( 'page' => 'khbc-capnhat', 'khbc_msg' => 'kiemtra' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'opcache' ) {
			khbc_xoa_opcache();
			wp_safe_redirect( add_query_arg( array( 'page' => 'khbc-capnhat', 'khbc_msg' => 'opcache' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'resetpin' ) {
			// Cứu hộ: đặt lại PIN cho Admin đầu tiên khi quên PIN.
			global $wpdb;
			$pin = isset( $_POST['khbc_pin'] ) ? trim( (string) wp_unslash( $_POST['khbc_pin'] ) ) : '';
			$t   = KHBC_DB::t( 'nguoi_dung' );
			$id  = (int) $wpdb->get_var( "SELECT id FROM $t WHERE vai='Admin' ORDER BY id ASC LIMIT 1" );
			$msg = 'Sai định dạng PIN (4–8 chữ số).';
			if ( preg_match( '/^\d{4,8}$/', $pin ) ) {
				if ( ! $id ) { KHBC_DB::seed_admin(); $id = (int) $wpdb->get_var( "SELECT id FROM $t WHERE vai='Admin' ORDER BY id ASC LIMIT 1" ); }
				if ( KHBC_Auth::pin_trung( $pin, $id ) ) { $msg = 'PIN này đã có người khác dùng.'; }
				else {
					$wpdb->update( $t, array( 'pin_hash' => password_hash( $pin, PASSWORD_DEFAULT ), 'hoat_dong' => 1, 'sua_luc' => KHBC_Util::now_sql() ), array( 'id' => $id ) );
					KHBC_Store::log_as( 'WP Admin', 'Admin', 'resetpin', 'Đặt lại PIN Admin từ wp-admin' );
					$msg = 'Đã đặt lại PIN cho tài khoản Admin (id ' . $id . ').';
				}
			}
			set_transient( 'khbc_resetpin_' . get_current_user_id(), $msg, 60 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'khbc' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function page_main() {
		global $wpdb;
		$n_user = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHBC_DB::t( 'nguoi_dung' ) );
		$n_khoan = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHBC_DB::t( 'khoan' ) . ' WHERE da_xoa=0' );
		$n_cho = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHBC_DB::t( 'khoan' ) . " WHERE da_xoa=0 AND trang_thai='cho_duyet'" );
		$kys = KHBC_Store::list_periods();
		$msg = get_transient( 'khbc_resetpin_' . get_current_user_id() );
		if ( $msg ) { delete_transient( 'khbc_resetpin_' . get_current_user_id() ); }
		?>
		<div class="wrap">
			<h1>Báo cáo chi phí (K&amp;H) <small style="color:#64748b;font-size:13px">bản <?php echo esc_html( KHBC_VERSION ); ?></small></h1>
			<?php if ( $msg ) : ?><div class="notice notice-info"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<div style="display:flex;gap:16px;flex-wrap:wrap;margin:14px 0">
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:300px;flex:1">
					<h2 style="margin-top:0">Mở ứng dụng</h2>
					<p><a class="button button-primary button-hero" target="_blank" href="<?php echo esc_url( KHBC_App::app_url( 'app' ) ); ?>">📊 Trang kế toán</a>
					   <a class="button button-hero" target="_blank" href="<?php echo esc_url( KHBC_App::app_url( 'nhap' ) ); ?>">📝 Trang nhân viên nhập chi phí</a></p>
					<p class="description">Đăng nhập bằng PIN. Lần đầu: tài khoản <b>Admin</b>, PIN <b>1111</b> — vào rồi đổi ngay ở nút 👤 Tài khoản. Nhúng vào bài viết: <code>[khbc_app]</code> hoặc <code>[khbc_app trang="nhap"]</code>.</p>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:260px">
					<h2 style="margin-top:0">Số liệu</h2>
					<p>Người dùng: <b><?php echo $n_user; ?></b> · Khoản chi phí: <b><?php echo $n_khoan; ?></b> · Chờ duyệt: <b><?php echo $n_cho; ?></b></p>
					<p>Kỳ đã có: <?php echo $kys ? esc_html( implode( ', ', $kys ) ) : '<i>chưa có</i>'; ?></p>
				</div>
			</div>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;max-width:640px">
				<h2 style="margin-top:0">Quên PIN Admin?</h2>
				<form method="post"><?php wp_nonce_field( 'khbc_resetpin' ); ?><input type="hidden" name="khbc_action" value="resetpin">
					<input name="khbc_pin" type="text" inputmode="numeric" placeholder="PIN mới 4–8 số" pattern="\d{4,8}" required>
					<button class="button">Đặt lại PIN cho tài khoản Admin đầu tiên</button>
					<p class="description">Chỉ quản trị WordPress mới thấy trang này. Người dùng khác đổi PIN trong app (Admin đặt PIN ở mục Người dùng).</p>
				</form>
			</div>
		</div>
		<?php
	}

	public static function page_settings() {
		$kt = get_transient( 'khbc_kiemtra_' . get_current_user_id() );
		if ( $kt ) { delete_transient( 'khbc_kiemtra_' . get_current_user_id() ); }
		?>
		<div class="wrap">
			<h1>Cài đặt Báo cáo chi phí</h1>
			<?php if ( isset( $_GET['khbc_msg'] ) && $_GET['khbc_msg'] === 'saved' ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p>Đã lưu.</p></div><?php endif; ?>
			<?php if ( is_array( $kt ) ) : ?>
				<div class="notice notice-info"><p><?php echo isset( $kt['ver'] ) ? 'Có bản mới <b>' . esc_html( $kt['ver'] ) . '</b> (' . esc_html( $kt['ngay'] ) . ') — vào <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugin</a> để cập nhật.' : 'Đang chạy bản mới nhất (hoặc GitHub chưa trả lời — kiểm tra khoá truy cập nếu repo riêng tư).'; ?></p></div>
			<?php endif; ?>
			<form method="post" style="max-width:720px">
				<?php wp_nonce_field( 'khbc_settings' ); ?><input type="hidden" name="khbc_action" value="settings">
				<table class="form-table">
					<tr><th>Tên trang</th><td><input class="regular-text" name="khbc_ten_trang" value="<?php echo esc_attr( get_option( 'khbc_ten_trang', '' ) ); ?>" placeholder="<?php echo esc_attr( KHBC_App::TEN_MAC_DINH ); ?>"></td></tr>
					<tr><th>Đường dẫn app</th><td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input name="khbc_slug" value="<?php echo esc_attr( KHBC_App::slug() ); ?>" style="width:220px"><code>/</code> &nbsp; trang nhân viên: <code>…/<?php echo esc_html( KHBC_App::slug() ); ?>/nhap/</code></td></tr>
					<tr><th>Múi giờ</th><td><input class="regular-text" name="khbc_timezone" value="<?php echo esc_attr( get_option( 'khbc_timezone', 'Asia/Bangkok' ) ); ?>"></td></tr>
					<tr><th>Bí mật SSO</th><td><input class="regular-text" type="password" name="khbc_sso_secret" value="<?php echo esc_attr( get_option( 'khbc_sso_secret', '' ) ); ?>" autocomplete="off"><p class="description">Để trống thì dùng chung <code>SSO_SECRET</code> của plugin Vận Hành Chi Phí (nếu có). Trang tổng mở app bằng <code>?sso=&lt;token&gt;</code>.</p></td></tr>
					<tr><th>Nhánh GitHub lấy bản mới</th><td><input class="regular-text" name="khbc_gh_nhanh" value="<?php echo esc_attr( KHBC_TuCapNhat::nhanh() ); ?>" placeholder="main"><p class="description">Repo công khai <code><?php echo esc_html( KHBC_TuCapNhat::REPO ); ?></code> — đẩy code lên nhánh này là WordPress thấy bản mới, không cần token. Xem trang <a href="<?php echo esc_url( admin_url( 'admin.php?page=khbc-capnhat' ) ); ?>">Tự cập nhật</a>.</p></td></tr>
					<tr><th>Khoá GitHub (tự cập nhật)</th><td>
						<input class="regular-text" type="password" name="khbc_gh_token" value="" autocomplete="off" placeholder="<?php echo KHBC_TuCapNhat::co_khoa() ? 'Đã có khoá — dán khoá mới để đổi' : 'Chỉ cần khi repo riêng tư'; ?>">
						<label style="margin-left:8px"><input type="checkbox" name="khbc_gh_xoa" value="1"> Xoá khoá</label>
						<p class="description">Repo <code><?php echo esc_html( KHBC_TuCapNhat::REPO ); ?></code>, tag <code><?php echo esc_html( KHBC_TuCapNhat::TIEN_TO ); ?>x.y.z</code>. Khoá chỉ cần quyền đọc (fine-grained token, Contents: Read-only). Không bao giờ hiện lại khoá đã lưu.</p>
					</td></tr>
				</table>
				<?php submit_button( 'Lưu cài đặt' ); ?>
			</form>
		</div>
		<?php
	}

	/** Trang "Tự cập nhật" — giống plugin Ghế Massage: bản đang chạy · bản mới trên GitHub · Kiểm tra ngay. */
	public static function page_capnhat() {
		$kq   = KHBC_TuCapNhat::ket_qua_gan_nhat();
		$luc  = (int) get_option( 'khbc_gh_kiem_luc', 0 );
		$msg  = isset( $_GET['khbc_msg'] ) ? sanitize_key( wp_unslash( $_GET['khbc_msg'] ) ) : ''; // phpcs:ignore
		$moi  = $kq && isset( $kq['ver'] ) ? $kq : null;
		$opc  = function_exists( 'opcache_get_status' );
		?>
		<div class="wrap">
			<h1>Tự cập nhật khbc-bao-cao-chi-phi</h1>
			<?php if ( $msg === 'kiemtra' ) : ?><div class="notice notice-success is-dismissible"><p>Đã hỏi GitHub xong.</p></div><?php endif; ?>
			<?php if ( $msg === 'opcache' ) : ?><div class="notice notice-success is-dismissible"><p>Đã xoá bộ đệm mã PHP (opcache) của plugin.</p></div><?php endif; ?>
			<table class="widefat" style="max-width:720px;margin:12px 0">
				<tr><th style="width:220px">Bản đang chạy</th><td>v<?php echo esc_html( KHBC_VERSION ); ?> <span class="description">(tệp PHP: <?php echo esc_html( date( 'd/m H:i', (int) filemtime( KHBC_FILE ) ) ); ?>)</span></td></tr>
				<tr><th>Bản mới trên GitHub</th><td>
					<?php if ( $moi ) : ?>
						<b style="color:#b45309">v<?php echo esc_html( $moi['ver'] ); ?></b> từ <?php echo $moi['nguon'] === 'nhanh' ? 'nhánh <code>' . esc_html( $moi['nhanh'] ) . '</code>' : 'Releases'; ?>
						— <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( plugin_basename( KHBC_FILE ) ) ), 'upgrade-plugin_' . plugin_basename( KHBC_FILE ) ) ); ?>">Cập nhật ngay lên v<?php echo esc_html( $moi['ver'] ); ?></a>
					<?php elseif ( $kq && isset( $kq['loi'] ) ) : ?>
						<span style="color:#b91c1c">Không hỏi được: <?php echo esc_html( $kq['loi'] ); ?></span>
					<?php elseif ( $kq ) : ?>
						Đang là bản mới nhất (không có bản nào cao hơn để nâng).<?php if ( isset( $kq['ver_xa'] ) ) : ?> <span class="description">Trên nhánh <code><?php echo esc_html( $kq['nhanh'] ); ?></code> là v<?php echo esc_html( $kq['ver_xa'] ); ?>.</span><?php endif; ?>
					<?php else : ?>
						<span class="description">Chưa hỏi. Bấm Kiểm tra ngay.</span>
					<?php endif; ?>
				</td></tr>
				<tr><th>Lần hỏi gần nhất</th><td><?php echo $luc ? esc_html( date( 'd/m/Y H:i', $luc ) ) : '—'; ?> <span class="description">(tự hỏi lại mỗi 6 giờ)</span></td></tr>
			</table>
			<form method="post" style="display:inline-block;margin-right:8px"><?php wp_nonce_field( 'khbc_kiemtra' ); ?><input type="hidden" name="khbc_action" value="kiemtra"><button class="button button-secondary">↻ Kiểm tra ngay</button></form>
			<form method="post" style="display:inline-block"><?php wp_nonce_field( 'khbc_opcache' ); ?><input type="hidden" name="khbc_action" value="opcache"><button class="button" <?php echo $opc ? '' : 'disabled title="Hosting không bật opcache"'; ?>>🧹 Xoá bộ đệm mã PHP</button></form>
			<p class="description" style="margin-top:12px">Bộ này CHỈ nâng lên bản cao hơn, không bao giờ tự hạ bản. Nguồn: nhánh <code><?php echo esc_html( KHBC_TuCapNhat::nhanh() ); ?></code> của repo công khai <code><?php echo esc_html( KHBC_TuCapNhat::REPO ); ?></code> — không cần token (đổi nhánh ở <a href="<?php echo esc_url( admin_url( 'admin.php?page=khbc-settings' ) ); ?>">Cài đặt</a>). Không thấy trên nhánh thì hỏi thêm GitHub Releases tag <code><?php echo esc_html( KHBC_TuCapNhat::TIEN_TO ); ?>x.y.z</code>.</p>
			<p class="description">Sau khi cập nhật, plugin tự xoá bộ đệm mã PHP. Nếu trang app vẫn báo "máy chủ đang chạy bản khác", bấm "Xoá bộ đệm mã PHP" hoặc nhờ hosting khởi động lại PHP.</p>
		</div>
		<?php
	}
}
