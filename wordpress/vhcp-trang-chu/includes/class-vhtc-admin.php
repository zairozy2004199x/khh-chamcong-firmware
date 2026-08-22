<?php
/**
 * Màn Cài đặt: đổi tên hiển thị, đổi đường dẫn, và xem thử trang.
 *
 * Cố ý ÍT ô. Trang này chỉ có ba đường dẫn — thêm mỗi tuỳ chọn là thêm một thứ có thể đặt sai
 * mà không ai nhận ra, đổi lấy một trang vốn chỉ cần bấm được là xong.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHTC_Admin {

	const CAP = 'manage_options';

	public static function menu() {
		add_menu_page( 'Trang Vận Hành', 'Trang Vận Hành', self::CAP, 'vhtc',
			array( __CLASS__, 'page' ), 'dashicons-screenoptions', 56 );
	}

	public static function handle_post() {
		if ( ! isset( $_POST['vhtc_luu'] ) ) { return; }
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		check_admin_referer( 'vhtc_luu' );

		$ten = isset( $_POST['vhtc_ten'] ) ? sanitize_text_field( wp_unslash( $_POST['vhtc_ten'] ) ) : '';
		update_option( 'vhtc_ten', $ten !== '' ? $ten : 'Vận Hành K&H' );

		$slug_cu  = VHTC_Trang::slug();
		$slug_moi = isset( $_POST['vhtc_slug'] ) ? sanitize_title( wp_unslash( $_POST['vhtc_slug'] ) ) : '';
		update_option( 'vhtc_slug', $slug_moi !== '' ? $slug_moi : 'van-hanh' );
		/* Đổi đường dẫn thì PHẢI nạp lại luật, không thì đường mới ra 404 mà màn hình vẫn báo
		   "Đã lưu" — đúng kiểu hỏng im lặng. */
		if ( $slug_cu !== VHTC_Trang::slug() ) { update_option( 'vhtc_flush', 1 ); }

		wp_safe_redirect( add_query_arg( array( 'page' => 'vhtc', 'vhtc_msg' => 'ok' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'Không đủ quyền.' ); }
		echo '<div class="wrap"><h1>Trang Vận Hành K&amp;H</h1>';

		if ( isset( $_GET['vhtc_msg'] ) ) {
			echo '<div class="notice notice-success"><p>Đã lưu. Đổi đường dẫn thì mở thử trang một '
				. 'lần để WordPress nạp lại.</p></div>';
		}

		echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( VHTC_Trang::url() )
			. '">' . esc_html( VHTC_Trang::url() ) . '</a></p>';

		echo '<form method="post"><input type="hidden" name="vhtc_luu" value="1" />';
		wp_nonce_field( 'vhtc_luu' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="vhtc_ten">Tên hiển thị</label></th><td>'
			. '<input name="vhtc_ten" id="vhtc_ten" class="regular-text" value="'
			. esc_attr( get_option( 'vhtc_ten', 'Vận Hành K&H' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="vhtc_slug">Đường dẫn</label></th><td>'
			. esc_html( home_url( '/' ) ) . '<input name="vhtc_slug" id="vhtc_slug" value="'
			. esc_attr( VHTC_Trang::slug() ) . '" class="regular-text"> /'
			. '<p class="description">Mặc định <code>van-hanh</code>.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Lưu' );
		echo '</form>';

		/* Bảng này là phần đáng giá nhất của màn Cài đặt: nó cho biết trang cổng ĐANG trỏ đi
		   đâu, và app nào chưa cài. Không có nó thì phải mở trang thật ra bấm thử từng cái. */
		echo '<hr><h2>App trên trang cổng</h2>';
		echo '<table class="widefat striped" style="max-width:760px"><thead><tr>'
			. '<th>App</th><th>Tình trạng</th><th>Đường dẫn đang trỏ</th></tr></thead><tbody>';
		foreach ( VHTC_Trang::ds_app() as $a ) {
			echo '<tr><td><b>' . esc_html( $a['ten'] ) . '</b></td><td>'
				. ( $a['co'] ? '<span style="color:#046b2d">✔️ đã cài</span>'
					: '<span style="color:#b32d2e">chưa cài</span>' )
				. '</td><td>' . ( $a['url'] ? '<code>' . esc_html( $a['url'] ) . '</code>' : '—' )
				. '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">Đường dẫn lấy thẳng từ chính mỗi app, không khai lại ở đây — '
			. 'nên đổi đường dẫn bên app nào thì trang cổng tự theo.</p>';
		echo '</div>';
	}
}
