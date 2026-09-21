<?php
/**
 * MÀN CÀI ĐẶT — Cài đặt → App Chấm Công.
 *
 * Ngắn gọn có chủ ý: chỉ hai ô đổi được (đường dẫn, tên hiện dưới biểu tượng) và một khối tự soát.
 *
 * 🔴 KHỐI TỰ SOÁT LÀ PHẦN QUAN TRỌNG NHẤT CỦA MÀN NÀY.
 *    PWA hỏng gần như luôn hỏng IM LẶNG: không có HTTPS thì trình duyệt lặng lẽ không cho đăng ký
 *    thợ nền; chưa bật đường dẫn đẹp thì `/cc/` ra trang 404; chưa cài Chấm Công thì app mở ra
 *    một trang trống. Ba ca ấy trên máy người dùng nhìn giống hệt nhau: *"bấm vào không thấy gì"*.
 *    Khối này trả lời ba câu đó bằng chữ, trước khi có ai phải đoán qua ảnh chụp màn hình.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCAPP_CaiDat {

	const TRANG = 'ccapp';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ccapp_luu', array( __CLASS__, 'luu' ) );
	}

	public static function menu() {
		add_options_page( 'App Chấm Công', 'App Chấm Công', 'manage_options', self::TRANG,
			array( __CLASS__, 've' ) );
	}

	public static function luu() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không đủ quyền.' ); }
		check_admin_referer( 'ccapp_luu' );

		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$ten  = isset( $_POST['ten'] ) ? sanitize_text_field( wp_unslash( $_POST['ten'] ) ) : '';

		update_option( CCAPP_App::O_SLUG, $slug );
		update_option( CCAPP_App::O_TEN, $ten );

		/* Đổi đường dẫn thì bảng luật cũ vẫn trỏ về địa chỉ cũ. Không nạp lại là địa chỉ mới ra
		   404 còn địa chỉ cũ vẫn chạy — và người ta kết luận "lưu không ăn". */
		CCAPP_App::luat();
		flush_rewrite_rules();

		wp_safe_redirect( add_query_arg( array( 'page' => self::TRANG, 'xong' => '1' ),
			admin_url( 'options-general.php' ) ) );
		exit;
	}

	/** Một dòng trong khối tự soát. */
	private static function dong( $nhan, $dat, $khi_dat, $khi_khong ) {
		echo '<li style="margin:.4em 0">'
			. ( $dat ? '<span style="color:#1a7f37">✔</span> ' : '<span style="color:#b32d2e">✘</span> ' )
			. '<b>' . esc_html( $nhan ) . '</b> — '
			. esc_html( $dat ? $khi_dat : $khi_khong )
			. '</li>';
	}

	public static function ve() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$url  = CCAPP_App::url();
		$https = is_ssl() || 0 === stripos( (string) home_url(), 'https://' );
		$dep   = (bool) get_option( 'permalink_structure' );

		echo '<div class="wrap"><h1>App Chấm Công</h1>';

		if ( isset( $_GET['xong'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Đã lưu.</p></div>';
		}

		echo '<p>Bộ này khoác lớp vỏ app lên trang chấm công có sẵn. Nhân viên mở địa chỉ dưới đây '
			. 'trên điện thoại rồi thêm vào màn hình chính là xong — không phải cài gì từ App Store.</p>';

		echo '<p style="font-size:18px"><b>Địa chỉ app:</b> <a href="' . esc_url( $url ) . '" target="_blank">'
			. esc_html( $url ) . '</a></p>';

		echo '<h2>Tự soát</h2><ul style="max-width:760px">';
		/* Không có HTTPS thì thợ nền KHÔNG BAO GIỜ đăng ký được — đây là luật của trình duyệt,
		   không phải tuỳ chọn, và nó không báo lỗi ra màn hình. Để đầu danh sách. */
		self::dong( 'HTTPS', $https,
			'trang chạy https, cài app được.',
			'trang chưa chạy https — điện thoại sẽ KHÔNG cài được app. Bật SSL trước.' );
		self::dong( 'Đường dẫn tĩnh', $dep,
			'đang bật, địa chỉ app gọn.',
			'đang ở dạng ?p=123 — app vẫn chạy nhưng địa chỉ xấu. Cài đặt → Đường dẫn tĩnh.' );
		self::dong( 'Plugin Chấm Công', CCAPP_App::co_tram(),
			'đã cài và đang bật.',
			'chưa cài hoặc đang tắt — app sẽ mở ra trang báo lỗi. Cài plugin Chấm Công trước.' );
		echo '</ul>';

		echo '<p>Soi kỹ hơn: <a href="' . esc_url( $url . 'manifest.json' ) . '" target="_blank">manifest.json</a>'
			. ' · <a href="' . esc_url( $url . 'sw.js' ) . '" target="_blank">sw.js</a>'
			. ' — mở ra thấy nội dung là đúng; ra trang 404 thì vào Cài đặt → Đường dẫn tĩnh bấm Lưu một lượt.</p>';

		echo '<h2>Cài đặt</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ccapp_luu' );
		echo '<input type="hidden" name="action" value="ccapp_luu">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="ccapp-slug">Đường dẫn</label></th><td>'
			. esc_html( trailingslashit( home_url() ) )
			. '<input name="slug" id="ccapp-slug" type="text" class="regular-text" style="width:10em" value="'
			. esc_attr( CCAPP_App::slug() ) . '"> /'
			. '<p class="description">Để trống là dùng lại <code>' . esc_html( CCAPP_App::SLUG_MD ) . '</code>. '
			. 'Ngắn thì nhân viên gõ tay được.</p></td></tr>';

		echo '<tr><th scope="row"><label for="ccapp-ten">Tên dưới biểu tượng</label></th><td>'
			. '<input name="ten" id="ccapp-ten" type="text" class="regular-text" value="'
			. esc_attr( CCAPP_App::ten() ) . '">'
			/* iOS cắt tên dài thành "Chấm Cô…" ngay trên màn hình chính — nói trước còn hơn để
			   người ta gõ một cái tên đẹp rồi ngạc nhiên. */
			. '<p class="description">iPhone chỉ bày được khoảng 12 ký tự, dài hơn là bị cắt.</p></td></tr>';

		echo '</tbody></table>';
		submit_button( 'Lưu' );
		echo '</form>';

		echo '<h2>Gửi cho nhân viên</h2>'
			. '<p>Chép đoạn dưới gửi vào nhóm Zalo:</p>'
			. '<textarea readonly rows="7" style="width:100%;max-width:760px;font-family:inherit" onclick="this.select()">'
			. esc_textarea(
				"Cài app chấm công:\n"
				. "1. Mở đường dẫn này bằng Safari (iPhone) hoặc Chrome (Android): " . $url . "\n"
				. "2. iPhone: bấm nút Chia sẻ ở thanh dưới → chọn \"Thêm vào MH chính\" → Thêm.\n"
				. "   Android: bấm nút Cài app hiện ở cuối màn hình (hoặc menu ⋮ → Cài ứng dụng).\n"
				. "3. Từ lần sau mở thẳng biểu tượng \"" . CCAPP_App::ten() . "\" trên màn hình chính.\n"
				. "Lưu ý: lần đầu mở từ biểu tượng phải đăng nhập lại bằng PIN — bình thường."
			)
			. '</textarea>';

		echo '</div>';
	}
}
