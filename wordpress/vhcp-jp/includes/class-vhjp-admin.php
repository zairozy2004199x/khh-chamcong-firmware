<?php
/**
 * MÀN TRONG wp-admin — nói thẳng đường dẫn, và TỰ CHẨN khi không vào được.
 *
 * 🔴 VÌ SAO CÓ MÀN NÀY: anh Thắng cài plugin, bật lên, rồi *"anh chưa truy cập được"*. Lúc ấy
 *    không có chỗ nào nói đường dẫn là gì, cũng không có cách nào biết vì sao hỏng — bảng chưa
 *    dựng? đường chưa khai? site chưa bật đường dẫn đẹp? Ba nguyên nhân ấy hỏng giống hệt nhau
 *    từ phía người dùng: một trang 404.
 *
 * ⚠️ NÓI CẢ HAI DẠNG ĐỊA CHỈ. Site chưa bật đường dẫn đẹp thì `/jp` luôn 404, và không ai đoán
 *    ra là phải dùng `?vhjp_app=nv`. Bày sẵn cả hai thì thử một cái là xong.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHJP_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VHJP_FILE ),
			array( __CLASS__, 'lien_ket' ) );
	}

	/** Thêm "Mở trang" ngay cạnh nút Vô hiệu hoá ở màn Plugin — chỗ người ta nhìn đầu tiên. */
	public static function lien_ket( $ds ) {
		array_unshift( $ds,
			'<a href="' . esc_url( admin_url( 'admin.php?page=vhjp' ) ) . '">Đường dẫn &amp; kiểm tra</a>' );
		return $ds;
	}

	public static function menu() {
		add_menu_page( 'JP Capsule', 'JP Capsule', 'manage_options', 'vhjp',
			array( __CLASS__, 'man' ), 'dashicons-chart-area', 58 );
	}

	/** Mọi thứ cần biết để trả lời "vì sao chưa vào được". */
	public static function soat() {
		$thieu = array();
		foreach ( VHJP_DB::ban_do() as $tab => $ten ) {
			if ( ! VHJP_Nguon::co_bang( $tab ) ) { $thieu[] = $ten; }
		}
		$luat = get_option( 'rewrite_rules' );
		$luat = is_array( $luat ) ? $luat : array();
		$co_duong = false;
		foreach ( array_keys( $luat ) as $m ) {
			if ( false !== strpos( $m, VHJP_Trang::slug_nv() ) ) { $co_duong = true; break; }
		}
		return array(
			'thieu_bang' => $thieu,
			'dep'        => (bool) get_option( 'permalink_structure' ),
			'co_duong'   => $co_duong,
			'so_nguoi'   => count( VHJP_Nguon::doc( 'JP_Users' ) ),
			'da_chuyen'  => count( VHJP_Cong::map() ),
			'tong_lenh'  => count( VHJP_Cong::map() ) + count( VHJP_Cong::chua_lam() ),
		);
	}

	public static function man() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		/* Nút mở lại đường dẫn — cách chữa cho đúng một trong ba nguyên nhân hay gặp nhất. */
		if ( isset( $_POST['vhjp_mo_lai'] ) && check_admin_referer( 'vhjp_mo_lai' ) ) {
			VHJP_DB::install();
			VHJP_Auth::cap_tai_khoan_dau();
			VHJP_Trang::them_duong();
			flush_rewrite_rules();
			echo '<div class="notice notice-success"><p>Đã dựng lại bảng và mở lại đường dẫn.</p></div>';
		}

		$s  = self::soat();
		$nv = VHJP_Trang::dia_chi( false );
		$kt = VHJP_Trang::dia_chi( true );
		$nv_lui = home_url( '/?vhjp_app=nv' );
		$kt_lui = home_url( '/?vhjp_app=kt' );

		echo '<div class="wrap"><h1>JP Capsule</h1>';

		echo '<h2>Đường dẫn</h2><table class="widefat striped" style="max-width:820px"><tbody>';
		printf( '<tr><td style="width:150px"><strong>Nhân viên</strong></td><td><a href="%s" target="_blank">%s</a></td></tr>',
			esc_url( $nv ), esc_html( $nv ) );
		printf( '<tr><td><strong>Kế toán</strong></td><td><a href="%s" target="_blank">%s</a></td></tr>',
			esc_url( $kt ), esc_html( $kt ) );
		if ( $s['dep'] ) {
			/* Bày sẵn đường lùi kể cả khi site đã bật đường dẫn đẹp: nhiều hosting chặn
			   rewrite, và lúc ấy đường này vẫn đi được. */
			printf( '<tr><td>Đường lùi</td><td><a href="%s" target="_blank">%s</a> · <a href="%s" target="_blank">%s</a><br><em>Dùng khi hai đường trên trả 404.</em></td></tr>',
				esc_url( $nv_lui ), esc_html( $nv_lui ), esc_url( $kt_lui ), esc_html( $kt_lui ) );
		}
		echo '</tbody></table>';

		echo '<h2>Kiểm tra</h2><table class="widefat striped" style="max-width:820px"><tbody>';
		self::dong( 'Bảng dữ liệu',
			! $s['thieu_bang'],
			$s['thieu_bang'] ? 'Thiếu ' . count( $s['thieu_bang'] ) . ' bảng: '
				. esc_html( implode( ', ', array_slice( $s['thieu_bang'], 0, 6 ) ) )
				. ' — bấm nút bên dưới.'
				: 'Đủ ' . count( VHJP_DB::bang() ) . ' bảng.' );
		self::dong( 'Đường dẫn đẹp', $s['dep'],
			$s['dep'] ? 'Đã bật.'
				: 'CHƯA bật — nên <code>/' . esc_html( VHJP_Trang::slug_nv() )
					. '</code> sẽ trả 404. Dùng đường lùi ở trên, hoặc vào '
					. '<em>Cài đặt → Đường dẫn tĩnh</em> chọn một kiểu khác "Mặc định".' );
		self::dong( 'Đường của JP đã khai', $s['co_duong'],
			$s['co_duong'] ? 'Có trong bảng định tuyến.'
				: 'CHƯA có — bấm nút bên dưới để mở lại.' );
		/* 🔴 Nhắc số PIN mặc định CHỈ khi nó còn đúng, và chỉ ở đây — màn này đã đứng sau
		   quyền `manage_options`. Đổi rồi thì thôi nhắc: số cũ không còn đúng, nhắc chỉ làm
		   người ta gõ nhầm rồi tưởng hỏng. */
		$pin_md = VHJP_Auth::con_pin_mac_dinh();
		self::dong( 'Tài khoản', $s['so_nguoi'] > 0,
			$s['so_nguoi'] > 0
				? $s['so_nguoi'] . ' tài khoản.'
					. ( '' !== $pin_md
						? ' <strong>Đăng nhập lần đầu bằng PIN <code>' . esc_html( $pin_md )
							. '</code></strong> — hệ đóng mọi cửa cho tới khi đổi sang số khác.'
						: ' PIN đã được đổi khỏi số mặc định.' )
				: 'CHƯA có tài khoản nào — bấm nút bên dưới để hệ tự cấp một tài khoản kế toán.' );
		printf( '<tr><td><strong>Tiến độ chuyển</strong></td><td>%d / %d lệnh máy chủ</td></tr>',
			(int) $s['da_chuyen'], (int) $s['tong_lenh'] );
		echo '</tbody></table>';

		echo '<form method="post" style="margin-top:16px">';
		wp_nonce_field( 'vhjp_mo_lai' );
		echo '<button class="button button-primary" name="vhjp_mo_lai" value="1">'
			. 'Dựng lại bảng &amp; mở lại đường dẫn</button>';
		echo ' <span class="description">Bấm khi trang trả 404 hoặc thiếu bảng. An toàn, không mất dữ liệu.</span>';
		echo '</form></div>';
	}

	private static function dong( $ten, $ok, $chu ) {
		printf( '<tr><td style="width:180px"><strong>%s</strong></td><td>%s %s</td></tr>',
			esc_html( $ten ),
			$ok ? '<span style="color:#166534">✓</span>' : '<span style="color:#b45309">⚠</span>',
			$chu );
	}
}
