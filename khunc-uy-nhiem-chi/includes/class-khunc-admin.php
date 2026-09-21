<?php
/** TRANG QUẢN TRỊ WORDPRESS — link mở app, cài đặt, khoá GitHub tự cập nhật, mở khoá đăng nhập. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KHUNC_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page( 'Ủy nhiệm chi & Công nợ', 'Ủy nhiệm chi & Công nợ', self::CAP, 'khunc', array( __CLASS__, 'page_main' ), 'dashicons-money-alt', 59 );
		add_submenu_page( 'khunc', 'Cài đặt Ủy nhiệm chi & Công nợ', 'Cài đặt', self::CAP, 'khunc-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'khunc', 'Tự cập nhật', 'Tự cập nhật', self::CAP, 'khunc-capnhat', array( __CLASS__, 'page_capnhat' ) );
	}

	public static function handle_post() {
		if ( ! isset( $_POST['khunc_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['khunc_action'] ) );
		check_admin_referer( 'khunc_' . $action );

		if ( $action === 'settings' ) {
			update_option( 'khunc_ten_trang', isset( $_POST['khunc_ten_trang'] ) ? sanitize_text_field( wp_unslash( $_POST['khunc_ten_trang'] ) ) : '' );
			$slug = isset( $_POST['khunc_slug'] ) ? sanitize_title( wp_unslash( $_POST['khunc_slug'] ) ) : '';
			if ( $slug === '' ) { $slug = KHUNC_App::SLUG_MAC_DINH; }
			if ( get_option( 'khunc_slug' ) !== $slug ) { update_option( 'khunc_flush_rewrite', 1 ); }
			update_option( 'khunc_slug', $slug );
			$tz = isset( $_POST['khunc_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['khunc_timezone'] ) ) : 'Asia/Bangkok';
			if ( in_array( $tz, timezone_identifiers_list(), true ) ) { update_option( 'khunc_timezone', $tz ); }
			if ( isset( $_POST['khunc_sso_secret'] ) ) { update_option( 'khunc_sso_secret', trim( (string) wp_unslash( $_POST['khunc_sso_secret'] ) ) ); }
			if ( isset( $_POST['khunc_gh_nhanh'] ) ) { KHUNC_TuCapNhat::dat_nhanh( wp_unslash( $_POST['khunc_gh_nhanh'] ) ); }
			// Khoá GitHub: ô trống = giữ nguyên (ô không bao giờ hiện khoá đang lưu).
			if ( ! empty( $_POST['khunc_gh_xoa'] ) ) { KHUNC_TuCapNhat::xoa_khoa(); }
			elseif ( isset( $_POST['khunc_gh_token'] ) ) { KHUNC_TuCapNhat::dat_khoa( wp_unslash( $_POST['khunc_gh_token'] ) ); }
			wp_safe_redirect( add_query_arg( array( 'page' => 'khunc-settings', 'khunc_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'kiemtra' ) {
			KHUNC_TuCapNhat::ban_moi( true );
			delete_site_transient( 'update_plugins' ); // để màn Plugin hỏi lại ngay
			wp_safe_redirect( add_query_arg( array( 'page' => 'khunc-capnhat', 'khunc_msg' => 'kiemtra' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'opcache' ) {
			khunc_xoa_opcache();
			wp_safe_redirect( add_query_arg( array( 'page' => 'khunc-capnhat', 'khunc_msg' => 'opcache' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'resetpin' ) {
			// Cứu hộ: đặt lại PIN cho Admin đầu tiên khi quên PIN.
			global $wpdb;
			$pin = isset( $_POST['khunc_pin'] ) ? trim( (string) wp_unslash( $_POST['khunc_pin'] ) ) : '';
			$t   = KHUNC_DB::t( 'nguoi_dung' );
			$id  = (int) $wpdb->get_var( "SELECT id FROM $t WHERE vai='Admin' ORDER BY id ASC LIMIT 1" );
			$msg = 'Sai định dạng PIN (4–8 chữ số).';
			if ( preg_match( '/^\d{4,8}$/', $pin ) ) {
				if ( ! $id ) { KHUNC_DB::seed_admin(); $id = (int) $wpdb->get_var( "SELECT id FROM $t WHERE vai='Admin' ORDER BY id ASC LIMIT 1" ); }
				if ( KHUNC_Auth::pin_trung( $pin, $id ) ) { $msg = 'PIN này đã có người khác dùng.'; }
				else {
					$wpdb->update( $t, array( 'pin_hash' => password_hash( $pin, PASSWORD_DEFAULT ), 'hoat_dong' => 1, 'sua_luc' => KHUNC_Util::now_sql() ), array( 'id' => $id ) );
					KHUNC_Store::log_as( 'WP Admin', 'Admin', 'resetpin', 'Đặt lại PIN Admin từ wp-admin' );
					$msg = 'Đã đặt lại PIN cho tài khoản Admin (id ' . $id . ').';
				}
			}
			set_transient( 'khunc_resetpin_' . get_current_user_id(), $msg, 60 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'khunc' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function page_main() {
		global $wpdb;
		$n_user = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHUNC_DB::t( 'nguoi_dung' ) );
		$tk = KHUNC_Store::thong_ke();
		$msg = get_transient( 'khunc_resetpin_' . get_current_user_id() );
		if ( $msg ) { delete_transient( 'khunc_resetpin_' . get_current_user_id() ); }
		?>
		<div class="wrap">
			<h1>Ủy nhiệm chi & Công nợ (K&amp;H) <small style="color:#64748b;font-size:13px">bản <?php echo esc_html( KHUNC_VERSION ); ?></small></h1>
			<?php if ( $msg ) : ?><div class="notice notice-info"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<div style="display:flex;gap:16px;flex-wrap:wrap;margin:14px 0">
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:300px;flex:1">
					<h2 style="margin-top:0">Mở ứng dụng</h2>
					<p><a class="button button-primary button-hero" target="_blank" href="<?php echo esc_url( KHUNC_App::app_url( 'app' ) ); ?>">📊 Trang kế toán</a>
					   </p>
					<p class="description">Đăng nhập bằng PIN. Lần đầu: tài khoản <b>Admin</b>, PIN <b>1111</b> — vào rồi đổi ngay ở nút 👤 Tài khoản. Nhúng vào bài viết: <code>[khunc_app]</code>.</p>
					<p class="description">Vai trò: <b>Admin</b> (mọi thứ + quản lý người dùng) · <b>Kế toán</b> (nhập Excel, đánh dấu đã đi tiền) · <b>Xem</b> (chỉ tra cứu và in, mọi nút ghi tự ẩn).</p>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:260px">
					<h2 style="margin-top:0">Số liệu</h2>
					<p>Người dùng: <b><?php echo (int) $n_user; ?></b>
					 · Khoản đọc từ Excel: <b><?php echo (int) $tk['soKhoan']; ?></b>
					 · Đã đánh dấu: <b><?php echo (int) $tk['soDanhDau']; ?></b>
					 (đã đi tiền: <b><?php echo (int) $tk['daDi']; ?></b>)</p>
					<p>Lần nhập Excel gần nhất:
					<?php echo $tk['nhapLuc'] ? esc_html( $tk['nhapLuc'] . ( $tk['nhapBoi'] ? ' — ' . $tk['nhapBoi'] : '' ) ) : '<i>chưa nhập lần nào</i>'; ?></p>
				</div>
			</div>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;max-width:640px">
				<h2 style="margin-top:0">Quên PIN Admin?</h2>
				<form method="post"><?php wp_nonce_field( 'khunc_resetpin' ); ?><input type="hidden" name="khunc_action" value="resetpin">
					<input name="khunc_pin" type="text" inputmode="numeric" placeholder="PIN mới 4–8 số" pattern="\d{4,8}" required>
					<button class="button">Đặt lại PIN cho tài khoản Admin đầu tiên</button>
					<p class="description">Chỉ quản trị WordPress mới thấy trang này. Người dùng khác đổi PIN trong app (Admin đặt PIN ở mục Người dùng).</p>
				</form>
			</div>
		</div>
		<?php
	}

	public static function page_settings() {
		$kt = get_transient( 'khunc_kiemtra_' . get_current_user_id() );
		if ( $kt ) { delete_transient( 'khunc_kiemtra_' . get_current_user_id() ); }
		?>
		<div class="wrap">
			<h1>Cài đặt Ủy nhiệm chi & Công nợ</h1>
			<?php if ( isset( $_GET['khunc_msg'] ) && $_GET['khunc_msg'] === 'saved' ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p>Đã lưu.</p></div><?php endif; ?>
			<?php if ( is_array( $kt ) ) : ?>
				<div class="notice notice-info"><p><?php echo isset( $kt['ver'] ) ? 'Có bản mới <b>' . esc_html( $kt['ver'] ) . '</b> (' . esc_html( $kt['ngay'] ) . ') — vào <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugin</a> để cập nhật.' : 'Đang chạy bản mới nhất (hoặc GitHub chưa trả lời — kiểm tra khoá truy cập nếu repo riêng tư).'; ?></p></div>
			<?php endif; ?>
			<form method="post" style="max-width:720px">
				<?php wp_nonce_field( 'khunc_settings' ); ?><input type="hidden" name="khunc_action" value="settings">
				<table class="form-table">
					<tr><th>Tên trang</th><td><input class="regular-text" name="khunc_ten_trang" value="<?php echo esc_attr( get_option( 'khunc_ten_trang', '' ) ); ?>" placeholder="<?php echo esc_attr( KHUNC_App::TEN_MAC_DINH ); ?>"></td></tr>
					<tr><th>Đường dẫn app</th><td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input name="khunc_slug" value="<?php echo esc_attr( KHUNC_App::slug() ); ?>" style="width:220px"><code>/</code></td></tr>
					<tr><th>Múi giờ</th><td><input class="regular-text" name="khunc_timezone" value="<?php echo esc_attr( get_option( 'khunc_timezone', 'Asia/Bangkok' ) ); ?>"></td></tr>
					<tr><th>Bí mật SSO</th><td><input class="regular-text" type="password" name="khunc_sso_secret" value="<?php echo esc_attr( get_option( 'khunc_sso_secret', '' ) ); ?>" autocomplete="off"><p class="description">Để trống thì dùng chung <code>SSO_SECRET</code> của plugin Vận Hành Chi Phí (nếu có). Trang tổng mở app bằng <code>?sso=&lt;token&gt;</code>.</p></td></tr>
					<tr><th>Nhánh GitHub lấy bản mới</th><td><input class="regular-text" name="khunc_gh_nhanh" value="<?php echo esc_attr( KHUNC_TuCapNhat::nhanh() ); ?>" placeholder="main"><p class="description">Repo công khai <code><?php echo esc_html( KHUNC_TuCapNhat::REPO ); ?></code> — đẩy code lên nhánh này là WordPress thấy bản mới, không cần token. Xem trang <a href="<?php echo esc_url( admin_url( 'admin.php?page=khunc-capnhat' ) ); ?>">Tự cập nhật</a>.</p></td></tr>
					<tr><th>Khoá GitHub (tự cập nhật)</th><td>
						<input class="regular-text" type="password" name="khunc_gh_token" value="" autocomplete="off" placeholder="<?php echo KHUNC_TuCapNhat::co_khoa() ? 'Đã có khoá — dán khoá mới để đổi' : 'Chỉ cần khi repo riêng tư'; ?>">
						<label style="margin-left:8px"><input type="checkbox" name="khunc_gh_xoa" value="1"> Xoá khoá</label>
						<p class="description">Repo <code><?php echo esc_html( KHUNC_TuCapNhat::REPO ); ?></code>, tag <code><?php echo esc_html( KHUNC_TuCapNhat::TIEN_TO ); ?>x.y.z</code>. Khoá chỉ cần quyền đọc (fine-grained token, Contents: Read-only). Không bao giờ hiện lại khoá đã lưu.</p>
					</td></tr>
				</table>
				<?php submit_button( 'Lưu cài đặt' ); ?>
			</form>
		</div>
		<?php
	}

	/** Trang "Tự cập nhật" — giống plugin Ghế Massage: bản đang chạy · bản mới trên GitHub · Kiểm tra ngay. */
	public static function page_capnhat() {
		$kq   = KHUNC_TuCapNhat::ket_qua_gan_nhat();
		$luc  = (int) get_option( 'khunc_gh_kiem_luc', 0 );
		$msg  = isset( $_GET['khunc_msg'] ) ? sanitize_key( wp_unslash( $_GET['khunc_msg'] ) ) : ''; // phpcs:ignore
		$moi  = $kq && isset( $kq['ver'] ) ? $kq : null;
		$opc  = function_exists( 'opcache_get_status' );
		?>
		<div class="wrap">
			<h1>Tự cập nhật khunc-uy-nhiem-chi</h1>
			<?php if ( $msg === 'kiemtra' ) : ?><div class="notice notice-success is-dismissible"><p>Đã hỏi GitHub xong.</p></div><?php endif; ?>
			<?php if ( $msg === 'opcache' ) : ?><div class="notice notice-success is-dismissible"><p>Đã xoá bộ đệm mã PHP (opcache) của plugin.</p></div><?php endif; ?>
			<table class="widefat" style="max-width:720px;margin:12px 0">
				<tr><th style="width:220px">Bản đang chạy</th><td>v<?php echo esc_html( KHUNC_VERSION ); ?> <span class="description">(tệp PHP: <?php echo esc_html( date( 'd/m H:i', (int) filemtime( KHUNC_FILE ) ) ); ?>)</span></td></tr>
				<tr><th>Bản mới trên GitHub</th><td>
					<?php if ( $moi ) : ?>
						<b style="color:#b45309">v<?php echo esc_html( $moi['ver'] ); ?></b> từ <?php echo $moi['nguon'] === 'nhanh' ? 'nhánh <code>' . esc_html( $moi['nhanh'] ) . '</code>' : 'Releases'; ?>
						— <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( plugin_basename( KHUNC_FILE ) ) ), 'upgrade-plugin_' . plugin_basename( KHUNC_FILE ) ) ); ?>">Cập nhật ngay lên v<?php echo esc_html( $moi['ver'] ); ?></a>
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
			<form method="post" style="display:inline-block;margin-right:8px"><?php wp_nonce_field( 'khunc_kiemtra' ); ?><input type="hidden" name="khunc_action" value="kiemtra"><button class="button button-secondary">↻ Kiểm tra ngay</button></form>
			<form method="post" style="display:inline-block"><?php wp_nonce_field( 'khunc_opcache' ); ?><input type="hidden" name="khunc_action" value="opcache"><button class="button" <?php echo $opc ? '' : 'disabled title="Hosting không bật opcache"'; ?>>🧹 Xoá bộ đệm mã PHP</button></form>
			<p class="description" style="margin-top:12px">Bộ này CHỈ nâng lên bản cao hơn, không bao giờ tự hạ bản. Nguồn: nhánh <code><?php echo esc_html( KHUNC_TuCapNhat::nhanh() ); ?></code> của repo công khai <code><?php echo esc_html( KHUNC_TuCapNhat::REPO ); ?></code> — không cần token (đổi nhánh ở <a href="<?php echo esc_url( admin_url( 'admin.php?page=khunc-settings' ) ); ?>">Cài đặt</a>). Không thấy trên nhánh thì hỏi thêm GitHub Releases tag <code><?php echo esc_html( KHUNC_TuCapNhat::TIEN_TO ); ?>x.y.z</code>.</p>
			<p class="description">Sau khi cập nhật, plugin tự xoá bộ đệm mã PHP. Nếu trang app vẫn báo "máy chủ đang chạy bản khác", bấm "Xoá bộ đệm mã PHP" hoặc nhờ hosting khởi động lại PHP.</p>
		</div>
		<?php
	}
}
