<?php
/**
 * MÀN CÀI ĐẶT TRONG WP-ADMIN — đường dẫn trang, tên các mảng, và khoá GitHub.
 *
 * 🔴 SLUG MENU RIÊNG (`vhcpt`). Bốn plugin chi phí cài chung một site; trùng slug menu là chung
 *    cả màn Cài đặt, và form của bản này POST sang màn của bản kia.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCPT_Admin {

	const CAP  = 'manage_options';
	const TRANG = 'vhcpt';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'nhan_luu' ) );
	}

	public static function menu() {
		add_menu_page( 'Chi Phí Tổng', 'Chi Phí Tổng', self::CAP, self::TRANG,
			array( __CLASS__, 'trang' ), 'dashicons-chart-pie', 58 );
	}

	public static function nhan_luu() {
		if ( ! isset( $_POST['vhcpt_action'] ) || 'luu' !== $_POST['vhcpt_action'] ) { return; }
		if ( ! current_user_can( self::CAP ) ) { return; }
		check_admin_referer( 'vhcpt_luu' );

		if ( isset( $_POST['vhcpt_slug'] ) ) {
			$s = sanitize_title( wp_unslash( $_POST['vhcpt_slug'] ) );
			if ( '' === $s ) { $s = VHCPT_App::SLUG_MAC_DINH; }
			update_option( 'vhcpt_slug', $s );
			/* Đổi đường dẫn mà không nạp lại luật thì trang mới trả 404 cho tới lượt lưu
			   permalink kế tiếp — mà người đổi thì đóng trang và tin là xong. */
			flush_rewrite_rules( false );
		}

		if ( isset( $_POST['vhcpt_ten'] ) && is_array( $_POST['vhcpt_ten'] ) ) {
			foreach ( wp_unslash( $_POST['vhcpt_ten'] ) as $khoa => $ten ) {
				VHCPT_Ban::dat_ten( sanitize_key( $khoa ), sanitize_text_field( $ten ) );
			}
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 Ô TRỐNG = GIỮ NGUYÊN, KHÔNG PHẢI = XOÁ.
		 * Ô này KHÔNG BAO GIỜ hiện khoá đang lưu, nên "trống" là trạng thái BÌNH THƯỜNG của nó.
		 * Hiểu trống là xoá thì mỗi lượt đổi đường dẫn hay đổi tên mảng là mất khoá, và lần cập
		 * nhật sau im lặng không thấy bản mới. Muốn bỏ hẳn thì có ô tích riêng — một việc CÓ Ý.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		if ( ! empty( $_POST['vhcpt_gh_xoa'] ) ) {
			VHCPT_TuCapNhat::xoa_khoa();
		} elseif ( isset( $_POST['vhcpt_gh_token'] ) ) {
			VHCPT_TuCapNhat::dat_khoa( wp_unslash( $_POST['vhcpt_gh_token'] ) );
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * NGƯỜI VÀO TRANG TỔNG — lưu NGOẠI LỆ, không lưu cả danh sách.
		 *
		 * 🔴 Luật nền là theo QUYỀN (xem `VHCPT_Auth::duoc_vao()`), nên chỗ này chỉ ghi lại hai
		 *    sổ: ai bị chặn dù có quyền, ai được cho vào dù không có quyền. Lưu cả danh sách tên
		 *    thì tuyển một kế toán mới là trang tổng chối họ cho tới khi có người nhớ vào đây
		 *    thêm tên — thứ không nên nằm trong một quy trình tiền bạc.
		 *
		 * ⚠️ CHỈ GHI KHI FORM CÓ GỬI BẢNG ẤY (`vhcpt_co_bang`). Không có gác này thì mỗi lượt
		 *    Lưu ở form đường dẫn — form không mang ô tích nào — sẽ xoá sạch cả hai sổ.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		if ( ! empty( $_POST['vhcpt_co_bang'] ) ) {
			$tich = isset( $_POST['vhcpt_vao'] ) && is_array( $_POST['vhcpt_vao'] )
				? array_map( 'strval', array_keys( wp_unslash( $_POST['vhcpt_vao'] ) ) ) : array();
			$tich = array_map( 'mb_strtolower', array_map( 'trim', $tich ) );
			$them = array(); $chan = array();
			foreach ( VHCPT_Auth::moi_nguoi() as $k => $ng ) {
				$duoc = in_array( $k, $tich, true );
				if ( $duoc === (bool) $ng['coViec'] ) { continue; }   // trùng luật nền -> khỏi ghi
				if ( $duoc ) { $them[] = $k; } else { $chan[] = $k; }
			}
			VHCPT_Auth::dat_so( VHCPT_Auth::O_THEM, $them );
			VHCPT_Auth::dat_so( VHCPT_Auth::O_CHAN, $chan );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::TRANG, 'vhcpt_msg' => 'saved' ),
			admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function trang() {
		if ( ! current_user_can( self::CAP ) ) { return; }
		echo '<div class="wrap"><h1>Chi Phí Tổng — gom đơn ba mảng</h1>';
		if ( isset( $_GET['vhcpt_msg'] ) && 'saved' === $_GET['vhcpt_msg'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>Đã lưu.</p></div>';
		}

		$ds = VHCPT_Ban::ds();
		echo '<h2>Các mảng trang này đang gom</h2>';
		if ( ! $ds ) {
			echo '<div class="notice notice-warning"><p><b>Chưa thấy bản chi phí nào.</b> '
				. 'Trang tổng đọc đơn của các plugin chi phí đang <b>kích hoạt</b> trên site này — '
				. 'chưa bật bản nào thì nó không có gì để gom.</p></div>';
		} else {
			echo '<table class="widefat striped" style="max-width:820px"><thead><tr>'
				. '<th>Khoá</th><th>Tên hiện ra</th><th>Bảng</th></tr></thead><tbody>';
			foreach ( $ds as $khoa => $b ) {
				echo '<tr><td><code>' . esc_html( $khoa ) . '</code></td>'
					. '<td>' . esc_html( $b['ten'] ) . '</td>'
					. '<td><code>' . esc_html( VHCPT_Ban::tien_to_bang( $khoa ) . 'don' ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<form method="post" action="">';
		wp_nonce_field( 'vhcpt_luu' );
		echo '<input type="hidden" name="vhcpt_action" value="luu">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="vhcpt_slug">Đường dẫn trang</label></th><td>'
			. '<code>' . esc_html( home_url( '/' ) ) . '</code>'
			. '<input type="text" name="vhcpt_slug" id="vhcpt_slug" class="regular-text code" value="'
			. esc_attr( VHCPT_App::slug() ) . '">'
			. '<p class="description">Mặc định <code>' . esc_html( VHCPT_App::SLUG_MAC_DINH ) . '</code>. '
			. '<b>Dấu <code>&amp;</code> không dùng được</b> — WordPress bỏ nó khi rửa đường dẫn, và '
			. 'trong URL thì <code>&amp;</code> là ký tự mở phần tham số nên link dán vào chat hay '
			. 'Excel sẽ đứt ở đúng chỗ ấy.</p></td></tr>';

		foreach ( $ds as $khoa => $b ) {
			echo '<tr><th scope="row">Tên mảng «' . esc_html( $khoa ) . '»</th><td>'
				. '<input type="text" name="vhcpt_ten[' . esc_attr( $khoa ) . ']" class="regular-text" value="'
				. esc_attr( $b['ten'] ) . '"></td></tr>';
		}

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 KHÔNG BAO GIỜ ĐỔ KHOÁ ĐANG LƯU RA `value`. Cùng luật đã đặt cho PIN và khoá máy
		 *    chấm công: trang chạy ngoài internet, một ảnh chụp màn hình là mất khoá. Ô chỉ NÓI
		 *    CÓ HAY KHÔNG và nhận khoá mới dán đè.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$co_khoa = VHCPT_TuCapNhat::co_khoa();
		echo '<tr><th scope="row"><label for="vhcpt_gh_token">Khoá GitHub</label></th><td>';
		echo '<input type="password" name="vhcpt_gh_token" id="vhcpt_gh_token" value="" autocomplete="new-password" class="regular-text code" placeholder="'
			. ( $co_khoa ? 'đã có khoá — dán khoá mới để thay' : 'chưa khai' ) . '">';
		echo '<p class="description">'
			. ( $co_khoa
				? '<b style="color:#16a34a">Đã khai khoá.</b> Trang sẽ tự thấy bản mới trên GitHub và hiện nút <b>Cập nhật</b> ở màn Plugin.'
				: '<b style="color:#b45309">Chưa khai.</b> Khai xong thì mỗi bản mới hiện ngay ở màn Plugin, khỏi tải tệp .zip về nữa.' )
			. '<br>Tạo ở GitHub → Settings → Developer settings → <b>Fine-grained tokens</b>: chọn đúng kho <code>'
			. esc_html( VHCPT_TuCapNhat::REPO ) . '</code>, mục <b>Contents</b> để <b>Read-only</b>. '
			. 'Khoá chỉ đọc nên lỡ lộ cũng không ai ghi được gì vào mã.<br>'
			. '<em>Ô này không bao giờ hiện khoá đang lưu — để trống là giữ nguyên.</em></p>';
		if ( $co_khoa ) {
			echo '<p><label><input type="checkbox" name="vhcpt_gh_xoa" value="1"> Xoá hẳn khoá đang lưu</label></p>';
		}
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button();
		/* ⚠️ ĐÓNG FORM Ở ĐÂY. Bảng "Người vào trang tổng" bên dưới là một form RIÊNG, mà HTML
		   không cho lồng form trong form: trình duyệt lặng lẽ bỏ form trong, và nút Lưu của nó
		   gửi sang form ngoài — tức bấm Lưu bảng người thì lưu đường dẫn, còn ô tích rơi đâu mất. */
		echo '</form>';

		/* ══════════════════════════════════════════════════════════════════════════════════════
		 * BẢNG "NGƯỜI VÀO TRANG TỔNG"
		 * ══════════════════════════════════════════════════════════════════════════════════════
		 * 🔴 KHÔNG IN PIN RA MÀN. Màn này nằm trong wp-admin, nhưng luật vẫn là luật: PIN không
		 *    đi vào HTML, không nằm trong ảnh chụp màn hình người ta gửi cho nhau.
		 * ══════════════════════════════════════════════════════════════════════════════════════ */
		$nguoi = VHCPT_Auth::moi_nguoi();
		echo '<hr><h2>Người vào trang tổng</h2>';
		echo '<p class="description" style="max-width:820px">Trang tổng là chỗ <b>duyệt và xử lý đơn</b>. '
			. 'Mặc định ai có ít nhất một quyền <b>duyệt · cấp tiền · trả lại · quyết toán · xuất MISA</b> '
			. 'ở bất kỳ mảng nào thì vào được — tức quản lý và kế toán. <b>Nhân viên chỉ lập đơn thì không '
			. 'cần vào</b>, và cũng không vào được. Quyền khai ở bảng <b>Phân quyền</b> của từng trang mảng; '
			. 'ô tích dưới đây chỉ dùng cho ngoại lệ.</p>';

		if ( ! $nguoi ) {
			echo '<div class="notice notice-warning inline"><p>Chưa đọc được sổ người dùng của mảng nào.</p></div>';
		} else {
			echo '<form method="post" action="">';
			wp_nonce_field( 'vhcpt_luu' );
			echo '<input type="hidden" name="vhcpt_action" value="luu">';
			echo '<input type="hidden" name="vhcpt_co_bang" value="1">';
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
				. '<th style="width:70px">Vào</th><th>Tên</th><th>Vai ở từng mảng</th>'
				. '<th>Làm được việc gì</th><th style="width:150px">Vì sao</th></tr></thead><tbody>';

			$ten_viec = array( 'duyet' => 'duyệt', 'cap' => 'cấp tiền', 'traLai' => 'trả lại',
				'qtCn' => 'quyết toán CN', 'qtNcc' => 'quyết toán NCC', 'misa' => 'xuất MISA' );

			foreach ( $nguoi as $k => $ng ) {
				$vais = array(); $viecs = array();
				foreach ( $ng['bans'] as $khoa => $b ) {
					$vais[] = VHCPT_Ban::ten( $khoa ) . ': ' . ( '' !== $b['vai'] ? $b['vai'] : '(chưa có vai)' );
					foreach ( $ten_viec as $v => $tv ) {
						if ( ! empty( $b['quyen'][ $v ] ) && ! in_array( $tv, $viecs, true ) ) { $viecs[] = $tv; }
					}
				}
				/* Vì sao người này vào được (hay không) — nói ra, để khỏi ai phải đoán. */
				if ( in_array( $k, VHCPT_Auth::so( VHCPT_Auth::O_CHAN ), true ) ) {
					$vi = '<b style="color:#b91c1c">bị chặn tay</b>';
				} elseif ( in_array( $k, VHCPT_Auth::so( VHCPT_Auth::O_THEM ), true ) ) {
					$vi = '<b style="color:#0369a1">cho vào tay</b>';
				} elseif ( $ng['coViec'] ) {
					$vi = 'có quyền xử lý đơn';
				} else {
					$vi = '<span style="color:#6b7280">chỉ lập đơn</span>';
				}

				echo '<tr><td style="text-align:center"><input type="checkbox" name="vhcpt_vao['
					. esc_attr( $k ) . ']" value="1"' . checked( $ng['vao'], true, false ) . '></td>'
					. '<td><b>' . esc_html( $ng['ten'] ) . '</b></td>'
					. '<td class="description">' . esc_html( implode( ' · ', $vais ) ) . '</td>'
					. '<td class="description">' . ( $viecs ? esc_html( implode( ', ', $viecs ) )
						: '<span style="color:#6b7280">— không việc nào —</span>' ) . '</td>'
					. '<td class="description">' . $vi . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">Bỏ tích một người ĐANG có quyền = <b>chặn</b> họ vào trang tổng '
				. '(quyền bên trang mảng giữ nguyên). Tích một người KHÔNG có quyền = cho vào để <b>xem</b>, '
				. 'họ vẫn không bấm được việc nào.</p>';
			submit_button( 'Lưu người vào trang tổng' );
			echo '</form>';
		}
		echo '<p>Trang tổng: <a href="' . esc_url( VHCPT_App::app_url() ) . '" target="_blank"><code>'
			. esc_html( VHCPT_App::app_url() ) . '</code></a></p>';
		echo '</div>';
	}
}
