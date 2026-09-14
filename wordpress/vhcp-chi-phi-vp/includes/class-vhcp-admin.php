<?php
/** TRANG QUẢN TRỊ — link mở app, nhập dữ liệu cũ từ CSV, cài đặt. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPVP_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page( 'Vận Hành Chi Phí', 'Vận Hành Chi Phí', self::CAP, 'vhcpvp', array( __CLASS__, 'page_main' ), 'dashicons-money-alt', 58 );
		add_submenu_page( 'vhcpvp', 'Nhập dữ liệu từ Google Sheet', 'Nhập dữ liệu', self::CAP, 'vhcpvp-import', array( __CLASS__, 'page_import' ) );
		add_submenu_page( 'vhcpvp', 'Nạp cả bảng tính từ link', 'Nạp từ link Sheet', self::CAP, 'vhcpvp-sheet', array( __CLASS__, 'page_sheet' ) );
		add_submenu_page( 'vhcpvp', 'Cài đặt Vận Hành Chi Phí', 'Cài đặt', self::CAP, 'vhcpvp-settings', array( __CLASS__, 'page_settings' ) );
	}

	// ---------------------------------------------------------------- xử lý form

	public static function handle_post() {
		if ( ! isset( $_POST['vhcpvp_action'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		$action = sanitize_text_field( wp_unslash( $_POST['vhcpvp_action'] ) );
		check_admin_referer( 'vhcpvp_' . $action );

		if ( $action === 'settings' ) {
			$slug = isset( $_POST['vhcpvp_slug'] ) ? sanitize_title( wp_unslash( $_POST['vhcpvp_slug'] ) ) : 'chi-phi';
			if ( $slug === '' ) { $slug = 'chi-phi'; }
			$old = get_option( 'vhcpvp_slug' );
			update_option( 'vhcpvp_slug', $slug );
			if ( $old !== $slug ) { update_option( 'vhcpvp_flush_rewrite', 1 ); }

			$tz = isset( $_POST['vhcpvp_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcpvp_timezone'] ) ) : 'Asia/Bangkok';
			if ( in_array( $tz, timezone_identifiers_list(), true ) ) { update_option( 'vhcpvp_timezone', $tz ); }

			$secret = isset( $_POST['vhcpvp_sso_secret'] ) ? trim( (string) wp_unslash( $_POST['vhcpvp_sso_secret'] ) ) : '';
			VHCPVP_Meta::set( 'SSO_SECRET', $secret );

			/* ══════════════════════════════════════════════════════════════════════════════
			 * KHOÁ GITHUB — Ô TRỐNG LÀ GIỮ NGUYÊN, KHÔNG PHẢI XOÁ.
			 *
			 * Ô này KHÔNG BAO GIỜ hiện khoá đang lưu (xem `page_settings()`), nên "trống" là
			 * trạng thái BÌNH THƯỜNG của nó. Hiểu trống là xoá thì mỗi lượt đổi múi giờ hay
			 * đổi đường dẫn app là mất khoá, và lần cập nhật sau im lặng không thấy bản mới.
			 * Cùng đúng cái bẫy đã gặp với ô PIN bên trang nhân sự.
			 *
			 * Muốn bỏ hẳn khoá thì có ô tích riêng — một việc CÓ Ý, không phải hậu quả của
			 * việc để trống một ô.
			 * ══════════════════════════════════════════════════════════════════════════════ */
			if ( ! empty( $_POST['vhcpvp_gh_xoa'] ) ) {
				VHCPVP_TuCapNhat::xoa_khoa();
			} elseif ( isset( $_POST['vhcpvp_gh_token'] ) ) {
				VHCPVP_TuCapNhat::dat_khoa( wp_unslash( $_POST['vhcpvp_gh_token'] ) );
			}

			VHCPVP_Cfg::clear_cache();
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp-settings', 'vhcpvp_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $action === 'import' ) {
			$type    = isset( $_POST['vhcpvp_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcpvp_type'] ) ) : '';
			$text    = isset( $_POST['vhcpvp_csv'] ) ? (string) wp_unslash( $_POST['vhcpvp_csv'] ) : '';
			$opts    = array(
				'header'  => ! empty( $_POST['vhcpvp_header'] ),
				'replace' => ! empty( $_POST['vhcpvp_replace'] ),
				'ma'      => isset( $_POST['vhcpvp_ma'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcpvp_ma'] ) ) : '',
			);

			if ( ! empty( $_FILES['vhcpvp_file']['tmp_name'] ) && is_uploaded_file( $_FILES['vhcpvp_file']['tmp_name'] ) ) {
				$raw = file_get_contents( $_FILES['vhcpvp_file']['tmp_name'] );
				if ( $raw !== false && $raw !== '' ) { $text = $raw; }
			}

			$res = VHCPVP_Import::run( $type, $text, $opts );
			set_transient( 'vhcpvp_import_res_' . get_current_user_id(), $res, 60 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp-import' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $action === 'sheet' ) {
			$url = isset( $_POST['vhcpvp_url'] ) ? esc_url_raw( wp_unslash( $_POST['vhcpvp_url'] ) ) : '';
			$tabs_txt = isset( $_POST['vhcpvp_tabs'] ) ? (string) wp_unslash( $_POST['vhcpvp_tabs'] ) : '';
			$tabs     = array();
			foreach ( preg_split( '/[\r\n]+/', $tabs_txt ) as $t ) {
				$t = sanitize_text_field( $t );
				if ( trim( $t ) !== '' ) { $tabs[] = trim( $t ); }
			}
			$res = VHCPVP_Sheet::nap_ca_file( $url, array(
				'thu'     => ! empty( $_POST['vhcpvp_thu'] ),
				'replace' => ! empty( $_POST['vhcpvp_replace'] ),
				'taoCha'  => ! empty( $_POST['vhcpvp_taocha'] ),
				'tabs'    => $tabs,
			) );
			set_transient( 'vhcpvp_sheet_tabs_' . get_current_user_id(), $tabs_txt, 3600 );
			set_transient( 'vhcpvp_sheet_res_' . get_current_user_id(), $res, 120 );
			set_transient( 'vhcpvp_sheet_url_' . get_current_user_id(), $url, 3600 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp-sheet' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'doiten' ) {
			$cu  = isset( $_POST['vhcpvp_cu'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcpvp_cu'] ) ) : '';
			$moi = isset( $_POST['vhcpvp_moi'] ) ? sanitize_text_field( wp_unslash( $_POST['vhcpvp_moi'] ) ) : '';
			$thu = ! empty( $_POST['vhcpvp_thu'] );
			$res = VHCPVP_Upload::doi_ten_mien( $cu, $moi, $thu );
			set_transient( 'vhcpvp_doiten_res_' . get_current_user_id(), $res, 60 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'xoadl' ) {
			$xn = isset( $_POST['vhcpvp_xacnhan'] ) ? trim( (string) wp_unslash( $_POST['vhcpvp_xacnhan'] ) ) : '';
			if ( mb_strtoupper( $xn ) !== 'XOA' ) {
				set_transient( 'vhcpvp_xoadl_' . get_current_user_id(), array( 'loi' => 'Chưa xóa — phải gõ đúng chữ XOA để xác nhận.' ), 60 );
			} else {
				set_transient( 'vhcpvp_xoadl_' . get_current_user_id(), array( 'ok' => VHCPVP_DB::xoa_du_lieu() ), 60 );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'mokhoa' ) {
			$n = VHCPVP_Auth::mo_khoa();
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp', 'vhcpvp_msg' => 'mokhoa', 'vhcpvp_n' => $n ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( $action === 'flush' ) {
			update_option( 'vhcpvp_flush_rewrite', 1 );
			VHCPVP_DB::install();
			wp_safe_redirect( add_query_arg( array( 'page' => 'vhcpvp', 'vhcpvp_msg' => 'flushed' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	// ---------------------------------------------------------------- trang

	public static function page_main() {
		$url = VHCPVP_App::app_url();
		echo '<div class="wrap"><h1>Vận Hành Chi Phí</h1>';

		if ( isset( $_GET['vhcpvp_msg'] ) && $_GET['vhcpvp_msg'] === 'flushed' ) {
			echo '<div class="notice notice-success"><p>Đã kiểm tra lại bảng dữ liệu và làm mới đường dẫn.</p></div>';
		}
		if ( isset( $_GET['vhcpvp_msg'] ) && $_GET['vhcpvp_msg'] === 'mokhoa' ) {
			echo '<div class="notice notice-success"><p>Đã mở khóa đăng nhập — vào app nhập PIN lại được ngay.</p></div>';
		}

		// Còn chạy trên tên miền tạm của Hostinger -> cảnh báo trước khi có ai up ảnh
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( $host && preg_match( '/hostingersite\.com$/i', $host ) ) {
			echo '<div class="notice notice-warning"><p><b>Web đang chạy trên tên miền tạm</b> (' . esc_html( $host ) . ').'
				. ' Ảnh hóa đơn lưu theo địa chỉ đầy đủ, nên hãy đổi sang tên miền thật <b>trước khi</b> có ai up ảnh —'
				. ' đổi sau thì mọi ảnh đã up sẽ trỏ về tên miền chết (có công cụ sửa ở dưới, nhưng làm trước thì khỏi phải sửa).</p>'
				. '<p>Đổi ở: <b>hPanel → Trang web → Tên miền → Đổi tên miền của trang web</b>, rồi quay lại đây bấm'
				. ' <b>Làm mới đường dẫn</b>.</p></div>';
		}

		$dt = get_transient( 'vhcpvp_doiten_res_' . get_current_user_id() );
		if ( $dt ) {
			delete_transient( 'vhcpvp_doiten_res_' . get_current_user_id() );
			if ( empty( $dt['success'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( isset( $dt['error'] ) ? $dt['error'] : 'Lỗi' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>'
					. ( ! empty( $dt['thu'] ) ? 'Thử: sẽ đổi ' : 'Đã đổi ' )
					. '<b>' . (int) $dt['doi'] . '</b> chỗ · ' . esc_html( $dt['cu'] ) . ' → ' . esc_html( $dt['moi'] )
					. ( count( (array) $dt['chiTiet'] ) ? ' · ' . esc_html( implode( ' · ', (array) $dt['chiTiet'] ) ) : '' )
					. '</p></div>';
			}
		}

		echo '<h2>Đổi tên miền trong link ảnh đã lưu</h2>';
		echo '<p>Dùng khi đã đổi tên miền web mà ảnh hóa đơn cũ vẫn trỏ về tên miền cũ. Tích <b>Chỉ thử</b> để xem sẽ đổi bao nhiêu chỗ mà chưa ghi gì.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vhcpvp' ) ) . '">';
		wp_nonce_field( 'vhcpvp_doiten' );
		echo '<input type="hidden" name="vhcpvp_action" value="doiten">';
		echo '<table class="form-table"><tr><th scope="row">Tên miền cũ</th><td><input name="vhcpvp_cu" class="regular-text" placeholder="khaki-scorpion-706230.hostingersite.com"></td></tr>';
		echo '<tr><th scope="row">Tên miền mới</th><td><input name="vhcpvp_moi" class="regular-text" placeholder="' . esc_attr( (string) $host ) . '"> <span class="description">để trống = tên miền hiện tại</span></td></tr>';
		echo '<tr><th scope="row">Chỉ thử</th><td><label><input type="checkbox" name="vhcpvp_thu" value="1" checked> chỉ đếm, chưa ghi</label></td></tr></table>';
		submit_button( 'Đổi tên miền trong link ảnh', 'secondary', 'submit', false );
		echo '</form>';

		$xd = get_transient( 'vhcpvp_xoadl_' . get_current_user_id() );
		if ( $xd ) {
			delete_transient( 'vhcpvp_xoadl_' . get_current_user_id() );
			if ( ! empty( $xd['loi'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $xd['loi'] ) . '</p></div>';
			} else {
				$ds = (array) $xd['ok'];
				$txt = array();
				foreach ( $ds as $b => $n ) { $txt[] = $b . ': ' . (int) $n; }
				echo '<div class="notice notice-success"><p>Đã xóa sạch dữ liệu nghiệp vụ'
					. ( count( $txt ) ? ' — ' . esc_html( implode( ' · ', $txt ) ) : ' (vốn đã trống)' )
					. '. Cấu hình, người dùng, danh mục loại chi phí và ma trận mã <b>vẫn còn</b>.</p></div>';
			}
		}

		echo '<h2>Xóa sạch dữ liệu để nạp lại</h2>';
		echo '<p>Xóa <b>đơn · dòng chi · sổ chi phí · dự án · marketing · công tác/setup · nhật ký</b>.'
			. ' <b>Giữ</b> cấu hình, người dùng, danh mục loại chi phí và ma trận mã — phần khai tay mất công nhất.</p>';
		echo '<p style="color:#b32d2e"><b>Không hoàn lại được.</b> Muốn chắc thì hPanel → phpMyAdmin → Export một file .sql trước.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vhcpvp' ) ) . '">';
		wp_nonce_field( 'vhcpvp_xoadl' );
		echo '<input type="hidden" name="vhcpvp_action" value="xoadl">';
		echo '<table class="form-table"><tr><th scope="row">Gõ chữ <code>XOA</code> để xác nhận</th><td>'
			. '<input name="vhcpvp_xacnhan" class="regular-text" placeholder="XOA" autocomplete="off"></td></tr></table>';
		submit_button( 'Xóa sạch dữ liệu nghiệp vụ', 'delete', 'submit', false );
		echo '</form>';

		echo '<h2>Bị khóa vì nhập sai PIN?</h2>';
		echo '<p>Nhập sai 10 lần thì app khóa theo địa chỉ mạng, tự mở sau 10 phút. Không muốn chờ thì bấm đây:</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vhcpvp' ) ) . '">';
		wp_nonce_field( 'vhcpvp_mokhoa' );
		echo '<input type="hidden" name="vhcpvp_action" value="mokhoa">';
		submit_button( 'Mở khóa đăng nhập ngay', 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>Mở app</h2><p><a class="button button-primary" target="_blank" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
		echo '<p>Nhúng vào 1 trang WordPress bằng shortcode: <code>[vhcpvp_app height="900"]</code>. ';
		echo 'Nhúng vào trang tổng K&amp;H: đặt biến <code>CHIPHI_URL</code> = đường dẫn trên (thêm <code>?sso=&lt;token&gt;</code> nếu dùng đăng nhập một lần).</p>';

		echo '<h2>Dữ liệu đang có</h2><table class="widefat striped" style="max-width:560px"><tbody>';
		foreach ( VHCPVP_Import::counts() as $label => $c ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td style="text-align:right"><b>' . esc_html( number_format_i18n( $c ) ) . '</b></td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Bảo trì</h2><form method="post">';
		wp_nonce_field( 'vhcpvp_flush' );
		echo '<input type="hidden" name="vhcpvp_action" value="flush">';
		echo '<p><button class="button">Kiểm tra lại bảng dữ liệu + làm mới đường dẫn</button> ';
		echo '<span class="description">Chạy khi mới cập nhật plugin hoặc mở app bị lỗi 404.</span></p></form>';
		echo '</div>';
	}

	public static function page_sheet() {
		$uid = get_current_user_id();
		$res = get_transient( 'vhcpvp_sheet_res_' . $uid );
		if ( $res ) { delete_transient( 'vhcpvp_sheet_res_' . $uid ); }
		$url  = (string) get_transient( 'vhcpvp_sheet_url_' . $uid );
		$tabs = (string) get_transient( 'vhcpvp_sheet_tabs_' . $uid );

		echo '<div class="wrap"><h1>Nạp cả bảng tính từ link Google Sheet</h1>';
		echo '<p>Dán link bảng tính, plugin tự tải <b>mọi tab</b>, tự nhận tab nào là bảng gì (theo tên cột),'
			. ' tự nạp <b>danh mục trước — dòng chi sau</b>, và tự tạo dự án / đợt còn thiếu để dòng chi không bị mồ côi.</p>';
		echo '<p><b>Bảng tính phải cho xem bằng link:</b> mở bảng tính → <em>Chia sẻ → Bất kỳ ai có đường liên kết → Người xem</em>.'
			. ' App chỉ ĐỌC, không ghi gì vào bảng tính của anh.</p>';

		if ( $res ) {
			if ( empty( $res['success'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( isset( $res['error'] ) ? $res['error'] : 'Lỗi' ) . '</p></div>';
			} else {
				echo '<div class="notice ' . ( ! empty( $res['thu'] ) ? 'notice-info' : 'notice-success' ) . '"><p>'
					. ( ! empty( $res['thu'] ) ? '<b>Chỉ thử — chưa ghi gì.</b> ' : '' )
					. 'Đọc ' . (int) $res['soTab'] . ' tab'
					. ( ! empty( $res['cach'] ) ? ' (danh sách tab lấy bằng: ' . esc_html( $res['cach'] ) . ')' : '' )
					. ' · nạp <b>' . (int) $res['tong'] . '</b> dòng'
					. ' · bỏ qua ' . (int) $res['boQua'] . ' · chưa ra mã tài khoản ' . (int) $res['thieuMa'] . '</p></div>';

				echo '<table class="widefat striped" style="max-width:1200px"><thead><tr><th>Tab</th><th>Nhận là</th>'
					. '<th>Nhận nhờ</th><th>Kết quả</th><th>Cột app không dùng</th><th>Dòng mồ côi</th></tr></thead><tbody>';
				foreach ( (array) $res['baoCao'] as $b ) {
					$la = isset( $b['cotLa'] ) ? (array) $b['cotLa'] : array();
					$mc = isset( $b['moCoi'] ) ? (array) $b['moCoi'] : array();
					$kv = isset( $b['khopVoi'] ) ? (array) $b['khopVoi'] : array();
					$kv_txt = array();
					foreach ( $kv as $field => $cols ) { $kv_txt[] = $field . ' ← ' . implode( ' / ', (array) $cols ); }
					$nhan = isset( $b['cachNhan'] ) ? $b['cachNhan'] : '';
					if ( $nhan === 'tên cột' && isset( $b['cotKhop'] ) && $b['cotKhop'] !== '' ) { $nhan .= ' (' . (int) $b['cotKhop'] . ' cột)'; }
					echo '<tr><td><code>' . esc_html( $b['tab'] ) . '</code></td>'
						. '<td>' . esc_html( isset( $b['bang'] ) ? $b['bang'] : '—' ) . '</td>'
						. '<td>' . esc_html( $nhan !== '' ? $nhan : '—' ) . '</td>'
						. '<td>' . esc_html( isset( $b['ketQua'] ) ? $b['ketQua'] : '' )
						. ( ! empty( $b['dongDau'] ) ? '<br><small style="color:#777">dòng đầu: <code>' . esc_html( $b['dongDau'] ) . '</code></small>' : '' )
						. ( count( $kv_txt ) ? '<br><small style="color:#777">đọc: ' . esc_html( implode( ' · ', $kv_txt ) ) . '</small>' : '' )
						. ( ! empty( $b['xoaTruoc'] ) ? '<br><small style="color:#996800">🗑 đã xoá dữ liệu cũ của ' . esc_html( $b['xoaTruoc'] ) . ' (chỉ 1 lần cho cả lượt nạp)</small>' : '' )
						. ( ! empty( $b['gopVao'] ) ? '<br><small style="color:#996800">🔗 tab nhân bản — gộp vào dự án <b>' . esc_html( $b['gopVao'] ) . '</b></small>' : '' )
						. ( ! empty( $b['canhBao'] ) ? '<br><small style="color:#b32d2e">⚠ ' . esc_html( $b['canhBao'] ) . '</small>' : '' )
						. '</td>'
						. '<td>' . esc_html( count( $la ) ? implode( ' · ', $la ) : '—' ) . '</td>'
						. '<td>' . esc_html( count( $mc ) ? implode( ' · ', $mc ) : '—' ) . '</td></tr>';
				}
				echo '</tbody></table>';

				if ( count( (array) $res['tuTao'] ) ) {
					echo '<div class="notice notice-warning"><p><b>App tự tạo dòng cha còn thiếu — kiểm lại mấy chỗ này:</b></p><ul style="margin-left:18px;list-style:disc">';
					foreach ( (array) $res['tuTao'] as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; }
					echo '</ul></div>';
				}
				if ( empty( $res['thu'] ) && (int) $res['thieuMa'] > 0 ) {
					echo '<div class="notice notice-warning"><p>Có <b>' . (int) $res['thieuMa'] . '</b> dòng chưa ra được TK Nợ.'
						. ' Vào app → ⚙️ Cấu hình khai mã cho loại chi phí (và điền địa điểm cho đợt vừa tạo), rồi bấm'
						. ' <b>🔗 Gán mã cho dòng cũ</b>.</p></div>';
				}
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vhcpvp-sheet' ) ) . '">';
		wp_nonce_field( 'vhcpvp_sheet' );
		echo '<input type="hidden" name="vhcpvp_action" value="sheet">';
		echo '<table class="form-table"><tr><th scope="row">Link bảng tính</th><td>'
			. '<input name="vhcpvp_url" class="large-text" value="' . esc_attr( $url ) . '" placeholder="https://docs.google.com/spreadsheets/d/…"></td></tr>';
		echo '<tr><th scope="row">Tên các tab</th><td>'
			. '<textarea name="vhcpvp_tabs" rows="6" class="large-text" placeholder="VH_Index&#10;VH_Line&#10;CT_ChiTiet&#10;DA SNOW NHÀ TUYẾT BÌNH DƯƠNG">' . esc_textarea( $tabs ) . '</textarea>'
			. '<p class="description">Mỗi dòng một tên tab, <b>gõ đúng như trên bảng tính</b>. Để trống thì app tự đọc danh sách tab;'
			. ' Google hay đổi giao diện nên đọc tự động có lúc tắc — khi đó gõ tay vào đây là chắc chắn nhất.</p></td></tr>';
		echo '<tr><th scope="row">Chỉ thử</th><td><label><input type="checkbox" name="vhcpvp_thu" value="1" checked>'
			. ' chỉ xem sẽ nạp gì, chưa ghi vào database</label></td></tr>';
		echo '<tr><th scope="row">Xóa dữ liệu cũ</th><td><label><input type="checkbox" name="vhcpvp_replace" value="1">'
			. ' xóa sạch bảng tương ứng trước khi nạp (chỉ tích cho lượt nạp đầu)</label></td></tr>';
		echo '<tr><th scope="row">Tự tạo dòng cha</th><td><label><input type="checkbox" name="vhcpvp_taocha" value="1" checked>'
			. ' tạo dự án / đợt còn thiếu để dòng chi không bị bỏ (app sẽ liệt kê ra để anh kiểm)</label></td></tr></table>';
		submit_button( 'Đọc bảng tính' );
		echo '</form></div>';
	}

	public static function page_import() {
		$res = get_transient( 'vhcpvp_import_res_' . get_current_user_id() );
		if ( $res ) { delete_transient( 'vhcpvp_import_res_' . get_current_user_id() ); }

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
		wp_nonce_field( 'vhcpvp_import' );
		echo '<input type="hidden" name="vhcpvp_action" value="import">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="vhcpvp_type">Tab đang nạp</label></th><td><select name="vhcpvp_type" id="vhcpvp_type" required>';
		foreach ( VHCPVP_Import::types() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['label'] ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="vhcpvp_ma">Dự án / Đợt nhận dòng</label></th><td><select name="vhcpvp_ma" id="vhcpvp_ma"><option value="">— chỉ cần khi nạp tab của 1 dự án / 1 đợt —</option>';
		$da = VHCPVP_DuAn::list_du_an();
		foreach ( (array) $da['items'] as $x ) {
			echo '<option value="' . esc_attr( $x['maDA'] ) . '">🔧 ' . esc_html( $x['ten'] . ' · ' . $x['loai'] ) . '</option>';
		}
		$bp = VHCPVP_BP::list_bp( 'all' );
		foreach ( (array) $bp['items'] as $x ) {
			echo '<option value="' . esc_attr( $x['ma'] ) . '">' . ( $x['loai'] === 'Công tác' ? '✈️ ' : '🛠️ ' ) . esc_html( $x['ten'] ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">File CSV</th><td><input type="file" name="vhcpvp_file" accept=".csv,.tsv,.txt,text/csv">'
			. '<p class="description"><b>Phải là file .csv</b> — nạp thẳng .xlsx / .xls / .zip sẽ bị từ chối vì đó là file nhị phân, đọc ra ký tự rác. '
			. 'Trong Google Sheet: <em>Tệp → Tải xuống → Giá trị được phân tách bằng dấu phẩy (.csv)</em>. '
			. 'Trong Excel: <em>Tệp → Lưu dưới dạng → CSV UTF-8</em>.</p></td></tr>';
		echo '<tr><th scope="row"><label for="vhcpvp_csv">…hoặc dán nội dung</label></th><td><textarea name="vhcpvp_csv" id="vhcpvp_csv" rows="8" style="width:100%;font-family:monospace"></textarea></td></tr>';
		echo '<tr><th scope="row">Tùy chọn</th><td>';
		echo '<label><input type="checkbox" name="vhcpvp_header" value="1" checked> Dòng đầu là tiêu đề (bỏ qua)</label><br>';
		echo '<label><input type="checkbox" name="vhcpvp_replace" value="1"> Xóa dữ liệu cũ của bảng này trước khi nạp</label>';
		echo '</td></tr>';
		echo '</tbody></table>';
		submit_button( 'Nạp dữ liệu' );
		echo '</form></div>';
	}

	public static function page_settings() {
		$slug    = get_option( 'vhcpvp_slug', 'chi-phi' );
		$tz     = get_option( 'vhcpvp_timezone', 'Asia/Bangkok' );
		$secret = (string) VHCPVP_Meta::get( 'SSO_SECRET', '' );

		echo '<div class="wrap"><h1>Cài đặt Vận Hành Chi Phí</h1>';
		if ( isset( $_GET['vhcpvp_msg'] ) && $_GET['vhcpvp_msg'] === 'saved' ) {
			echo '<div class="notice notice-success"><p>Đã lưu.</p></div>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'vhcpvp_settings' );
		echo '<input type="hidden" name="vhcpvp_action" value="settings">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="vhcpvp_slug">Đường dẫn app</label></th><td>' . esc_html( home_url( '/' ) ) . '<input name="vhcpvp_slug" id="vhcpvp_slug" value="' . esc_attr( $slug ) . '" class="regular-text"> /<p class="description">Mặc định <code>chi-phi</code>. Đổi xong hãy mở lại app 1 lần để đường dẫn được nạp.</p></td></tr>';
		echo '<tr><th scope="row"><label for="vhcpvp_timezone">Múi giờ</label></th><td><select name="vhcpvp_timezone" id="vhcpvp_timezone">';
		foreach ( timezone_identifiers_list() as $z ) {
			echo '<option value="' . esc_attr( $z ) . '"' . selected( $z, $tz, false ) . '>' . esc_html( $z ) . '</option>';
		}
		echo '</select><p class="description">App cũ chạy múi <code>Asia/Bangkok</code> (GMT+7).</p></td></tr>';
		echo '<tr><th scope="row"><label for="vhcpvp_sso_secret">SSO_SECRET</label></th><td><input name="vhcpvp_sso_secret" id="vhcpvp_sso_secret" value="' . esc_attr( $secret ) . '" class="regular-text code"><p class="description">Chuỗi bí mật dùng chung với trang tổng K&amp;H để đăng nhập một lần (<code>?sso=&lt;token&gt;</code>). Để trống nếu chỉ đăng nhập bằng PIN.</p></td></tr>';

		/* ══════════════════════════════════════════════════════════════════════════════════
		 * 🔴 KHÔNG BAO GIỜ ĐỔ KHOÁ ĐANG LƯU RA `value`.
		 *
		 * Cùng luật đã đặt cho PIN và khoá máy chấm công: trang chạy ngoài internet, một ảnh
		 * chụp màn hình là mất khoá. Ô chỉ NÓI CÓ HAY KHÔNG và nhận khoá mới dán đè.
		 * ══════════════════════════════════════════════════════════════════════════════════ */
		$co_khoa = VHCPVP_TuCapNhat::co_khoa();
		echo '<tr><th scope="row"><label for="vhcpvp_gh_token">Khoá GitHub</label></th><td>';
		echo '<input type="password" name="vhcpvp_gh_token" id="vhcpvp_gh_token" value="" autocomplete="new-password" class="regular-text code" placeholder="'
			. ( $co_khoa ? 'đã có khoá — dán khoá mới để thay' : 'chưa khai' ) . '">';
		echo '<p class="description">'
			. ( $co_khoa
				? '<b style="color:#16a34a">Đã khai khoá.</b> Trang sẽ tự thấy bản mới trên GitHub và hiện nút <b>Cập nhật</b> ở màn Plugin.'
				: '<b style="color:#b45309">Chưa khai.</b> Khai xong thì mỗi bản mới hiện ngay ở màn Plugin, khỏi tải tệp .zip về nữa.' )
			. '<br>Tạo ở GitHub → Settings → Developer settings → <b>Fine-grained tokens</b>: chọn đúng kho <code>'
			. esc_html( VHCPVP_TuCapNhat::REPO ) . '</code>, mục <b>Contents</b> để <b>Read-only</b>. '
			. 'Khoá chỉ đọc nên lỡ lộ cũng không ai ghi được gì vào mã.<br>'
			. '<em>Ô này không bao giờ hiện khoá đang lưu — để trống là giữ nguyên.</em></p>';
		if ( $co_khoa ) {
			echo '<p><label><input type="checkbox" name="vhcpvp_gh_xoa" value="1"> Xoá hẳn khoá đang lưu</label></p>';
		}
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form></div>';
	}
}
