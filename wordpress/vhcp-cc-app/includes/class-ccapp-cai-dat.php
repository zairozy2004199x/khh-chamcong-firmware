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

		/* Lịch nhắc. Cả khối gửi lên một lượt, và ô tích "bật" KHÔNG gửi gì khi không tích —
		   nên đọc bằng `isset`, không bằng giá trị. */
		CCAPP_Nhac::dat_lich( array(
			'bat'  => isset( $_POST['nhac_bat'] ),
			'vao'  => isset( $_POST['nhac_vao'] ) ? sanitize_text_field( wp_unslash( $_POST['nhac_vao'] ) ) : '',
			'ra'   => isset( $_POST['nhac_ra'] ) ? sanitize_text_field( wp_unslash( $_POST['nhac_ra'] ) ) : '',
			'ngay' => isset( $_POST['nhac_ngay'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['nhac_ngay'] ) ) : array(),
			'coso' => self::doc_coso_post(),
		) );

		/* Đổi đường dẫn thì bảng luật cũ vẫn trỏ về địa chỉ cũ. Không nạp lại là địa chỉ mới ra
		   404 còn địa chỉ cũ vẫn chạy — và người ta kết luận "lưu không ăn". */
		CCAPP_App::luat();
		flush_rewrite_rules();

		wp_safe_redirect( add_query_arg( array( 'page' => self::TRANG, 'xong' => '1' ),
			admin_url( 'options-general.php' ) ) );
		exit;
	}

	/** Giờ nhắc khai riêng cho từng cơ sở, đọc từ hai mảng song song của biểu mẫu. */
	private static function doc_coso_post() {
		$v = isset( $_POST['cs_vao'] ) ? (array) wp_unslash( $_POST['cs_vao'] ) : array();
		$r = isset( $_POST['cs_ra'] ) ? (array) wp_unslash( $_POST['cs_ra'] ) : array();
		$ra = array();
		foreach ( $v as $cs => $gio ) {
			$cs = sanitize_text_field( (string) $cs );
			$ra[ $cs ] = array(
				'vao' => sanitize_text_field( is_array( $gio ) ? '' : (string) $gio ),
				'ra'  => isset( $r[ $cs ] ) ? sanitize_text_field( (string) $r[ $cs ] ) : '',
			);
		}
		return $ra;
	}

	/** Một dòng trong khối tự soát. */
	private static function dong( $nhan, $dat, $khi_dat, $khi_khong ) {
		echo '<li style="margin:.4em 0">'
			. ( $dat ? '<span style="color:#1a7f37">✔</span> ' : '<span style="color:#b32d2e">✘</span> ' )
			. '<b>' . esc_html( $nhan ) . '</b> — '
			. esc_html( $dat ? $khi_dat : $khi_khong )
			. '</li>';
	}

	/**
	 * KHỐI NHẮC CHẤM CÔNG. Nằm TRONG cùng cái form với hai ô kia — cố ý.
	 *
	 * 🔴 KHÔNG LỒNG <form> TRONG <form>. HTML không cho, và trình duyệt không báo lỗi: nó lặng lẽ
	 *    vứt thẻ trong đi rồi gộp mọi ô nhập vào form ngoài. Bộ Chấm Công đã trả giá cho đúng lỗi
	 *    này ngày 22/08/2026 (xem `VHCC_Admin::page`). Một form, một nút Lưu.
	 */
	private static function khoi_nhac() {
		$l   = CCAPP_Nhac::lich();
		$sso = CCAPP_Khoa::pub();

		echo '<h2>Nhắc chấm công</h2>';
		echo '<p style="max-width:760px">Tới giờ mà <b>chưa thấy lượt chấm nào</b> thì app báo một '
			. 'thông báo lên điện thoại. Người đã chấm rồi thì không nhận gì — nhắc cả người đã chấm '
			. 'là tới tuần thứ hai không ai còn đọc thông báo của app này nữa.</p>';

		echo '<ul style="max-width:760px">';
		self::dong( 'Khoá VAPID', '' !== $sso,
			'đã có, máy đăng ký nhận nhắc được.',
			'máy chủ không sinh được khoá (thiếu OpenSSL đường cong P-256) — hỏi hosting.' );
		/* 🔴 CÂU NÀY PHẢI Ở ĐÂY, KHÔNG PHẢI TRONG MỘT TỆP HƯỚNG DẪN. WP-Cron không phải cron: nó
		   chỉ chạy ăn theo một lượt tải trang. Website ít khách thì 7h50 không ai vào, lời nhắc
		   đi lúc 9h — và người ta kết luận bộ nhắc hỏng, trong khi nó chưa từng được gọi. */
		$tat_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		self::dong( 'Lịch quét', (bool) wp_next_scheduled( CCAPP_Nhac::HOOK ),
			'đã đặt, quét 5 phút một lượt.',
			'chưa đặt được — tắt rồi bật lại plugin.' );
		echo '<li style="margin:.4em 0">'
			. ( $tat_cron ? '<span style="color:#1a7f37">✔</span> ' : '<span style="color:#b32d2e">✘</span> ' )
			. '<b>Cron thật ở hosting</b> — '
			. ( $tat_cron
				? esc_html( 'DISABLE_WP_CRON đang bật, nghĩa là anh/chị đã đặt cron thật. Đúng cách làm.' )
				: esc_html( 'CHƯA đặt. WP-Cron chỉ chạy khi có người mở trang website, nên lời nhắc '
					. '7h50 có thể đi lúc 9h hoặc không đi. Vào cPanel → Cron Jobs, đặt lệnh chạy '
					. '5 phút một lượt gọi wp-cron.php, rồi thêm DISABLE_WP_CRON vào wp-config.php.' ) )
			. '</li>';
		echo '</ul>';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">Bật nhắc</th><td><label><input type="checkbox" name="nhac_bat" value="1"'
			. checked( ! empty( $l['bat'] ), true, false ) . '> Gửi nhắc cho máy đã đăng ký</label>'
			. '<p class="description">Tắt là im hẳn; sổ máy đã đăng ký vẫn giữ nguyên.</p></td></tr>';

		echo '<tr><th scope="row"><label for="nhac-vao">Giờ nhắc chấm VÀO</label></th><td>'
			. '<input id="nhac-vao" name="nhac_vao" type="time" value="' . esc_attr( $l['vao'] ) . '">'
			. '<p class="description">Đặt <b>trước</b> giờ vào ca chừng 10 phút — nhắc đúng lúc đã '
			. 'muộn thì nhắc để làm gì.</p></td></tr>';

		echo '<tr><th scope="row"><label for="nhac-ra">Giờ nhắc chấm RA</label></th><td>'
			. '<input id="nhac-ra" name="nhac_ra" type="time" value="' . esc_attr( $l['ra'] ) . '">'
			. '<p class="description">Chỉ gửi cho người hôm nay <b>chưa có giờ ra</b>. Thiếu một đầu '
			. 'giờ là ngày ấy không tính công, nên đây là lời nhắc đáng giá nhất.</p></td></tr>';

		$ten_thu = array( 0 => 'CN', 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7' );
		echo '<tr><th scope="row">Ngày trong tuần</th><td>';
		foreach ( $ten_thu as $i => $tt ) {
			echo '<label style="margin-right:12px"><input type="checkbox" name="nhac_ngay[]" value="' . (int) $i . '"'
				. checked( in_array( (int) $i, array_map( 'intval', (array) $l['ngay'] ), true ), true, false )
				. '> ' . esc_html( $tt ) . '</label>';
		}
		echo '</td></tr>';
		echo '</tbody></table>';

		/* Giờ riêng của từng cơ sở — cửa hàng mở 9h và văn phòng mở 8h không thể chung một giờ
		   nhắc. Để trống là theo giờ chung ở trên. */
		$ds_cs = ( class_exists( 'VHCC_NhanSu' ) && method_exists( 'VHCC_NhanSu', 'ds_coso' ) )
			? (array) VHCC_NhanSu::ds_coso() : array();
		if ( $ds_cs ) {
			echo '<h3>Giờ riêng theo cơ sở</h3>';
			echo '<p class="description" style="max-width:760px">Để trống là theo giờ chung ở trên. '
				. 'Khai riêng khi cơ sở ấy mở cửa khác giờ.</p>';
			echo '<table class="widefat striped" style="max-width:560px"><thead><tr>'
				. '<th>Cơ sở</th><th>Nhắc vào</th><th>Nhắc ra</th></tr></thead><tbody>';
			foreach ( $ds_cs as $cs ) {
				$cs = (string) $cs;
				$o  = isset( $l['coso'][ $cs ] ) ? $l['coso'][ $cs ] : array( 'vao' => '', 'ra' => '' );
				$id = 'cs_' . preg_replace( '/[^A-Za-z0-9]+/', '_', $cs );
				echo '<tr><td>' . esc_html( $cs ) . '</td>'
					. '<td><label class="screen-reader-text" for="' . esc_attr( $id ) . '_v">Giờ nhắc vào của '
					. esc_html( $cs ) . '</label><input id="' . esc_attr( $id ) . '_v" type="time" name="cs_vao['
					. esc_attr( $cs ) . ']" value="' . esc_attr( isset( $o['vao'] ) ? $o['vao'] : '' ) . '"></td>'
					. '<td><label class="screen-reader-text" for="' . esc_attr( $id ) . '_r">Giờ nhắc ra của '
					. esc_html( $cs ) . '</label><input id="' . esc_attr( $id ) . '_r" type="time" name="cs_ra['
					. esc_attr( $cs ) . ']" value="' . esc_attr( isset( $o['ra'] ) ? $o['ra'] : '' ) . '"></td></tr>';
			}
			echo '</tbody></table>';
		}

		global $wpdb;
		$t  = CCAPP_Nhac::bang();
		$so = 0;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			$so = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
		}
		echo '<p><b>Máy đã đăng ký nhận nhắc: ' . (int) $so . '</b>'
			. ' <span class="description">Nhân viên bật ở chính app: mở app đã cài lên màn hình '
			. 'chính, dải "Bật nhắc" hiện ở cuối màn hình. iPhone <b>chỉ hỏi quyền một lần</b> — '
			. 'từ chối rồi thì phải vào Cài đặt của máy bật tay.</span></p>';
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

		self::khoi_nhac();

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
