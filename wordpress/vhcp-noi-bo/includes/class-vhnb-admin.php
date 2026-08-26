<?php
/**
 * MÀN CẤU HÌNH TRONG WP-ADMIN — chỗ khai "ai được làm gì" của trang Nội bộ.
 *
 * Anh Thắng 26/08/2026: *"phần quyền người vào chỗ nào"*. Đây là chỗ đó.
 *
 * 🔴 ĐẶT Ở WP-ADMIN, KHÔNG ĐẶT TRÊN CHÍNH TRANG NỘI BỘ.
 *    Nếu khai quyền ngay trên trang nội bộ thì có một cách tự khoá mình ra ngoài: hạ bậc "Vào
 *    trang" lên Admin trong khi mình không phải Admin, và từ đó không ai mở lại được nữa vì
 *    cái nút mở nằm sau đúng cánh cửa vừa khoá. wp-admin là cửa riêng, không bị khoá bởi chính
 *    bảng này.
 *
 * ⚠️ MÀN NÀY KHÔNG CÓ MỘT DÒNG SCRIPT NÀO — cùng luật với màn quản trị chấm công.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHNB_Admin {

	const NONCE = 'vhnb_cfg';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu() {
		add_menu_page(
			'Nội bộ K&H', 'Nội bộ K&H', 'manage_options',
			'vhnb-cau-hinh', array( __CLASS__, 've' ), 'dashicons-groups', 58
		);
	}

	public static function ve() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không đủ quyền.' ); }
		$bao = '';

		if ( isset( $_POST['vhnb_luu'] ) ) {
			check_admin_referer( self::NONCE );
			$map = array();
			foreach ( VHNB_Quyen::VIEC as $k => $_v ) {
				$map[ $k ] = isset( $_POST['q'][ $k ] ) ? sanitize_text_field( wp_unslash( $_POST['q'][ $k ] ) ) : '';
			}
			VHNB_Quyen::dat( $map );
			/* Đường dẫn trang: để trống thì `VHNB_Trang::slug()` tự về mặc định, nên KHÔNG cần
			   chặn rỗng ở đây — chặn hai nơi là hai luật, và hai luật thì lệch. */
			update_option( 'vhnb_slug', isset( $_POST['slug'] )
				? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '' );
			update_option( 'vhnb_rw', 1 );   // đổi đường dẫn -> phải ghi lại bộ luật đường
			$bao = 'Đã lưu.';
		}

		$cf = VHNB_Quyen::cai_dat();
		echo '<div class="wrap"><h1>Nội bộ K&amp;H</h1>';
		if ( '' !== $bao ) { echo '<div class="notice notice-success"><p>' . esc_html( $bao ) . '</p></div>'; }

		echo '<p>Trang nội bộ dùng chung mã PIN với hệ chấm công, và dùng chung <b>thang năm bậc</b> '
			. 'của hệ đó: Nhân viên → Cửa hàng trưởng → Quản lý → Kế toán → Admin. '
			. 'Mỗi việc dưới đây khai <b>bậc thấp nhất</b> được làm; bậc trên luôn làm được việc của bậc dưới.</p>';

		if ( ! class_exists( 'VHCC_Vai' ) ) {
			echo '<div class="notice notice-warning"><p><b>Chưa cài plugin Chấm Công.</b> '
				. 'Không có bộ đo bậc nên các chốt dưới đây tạm thời <b>cho qua hết</b> — '
				. 'khai gì cũng chưa có tác dụng cho tới khi cài plugin ấy.</p></div>';
		}

		echo '<form method="post">';
		wp_nonce_field( self::NONCE );
		echo '<table class="form-table"><tbody>';
		foreach ( VHNB_Quyen::VIEC as $k => $v ) {
			echo '<tr><th scope="row"><label for="q_' . esc_attr( $k ) . '">'
				. esc_html( $v['nhan'] ) . '</label></th><td>'
				. '<select id="q_' . esc_attr( $k ) . '" name="q[' . esc_attr( $k ) . ']">';
			foreach ( VHNB_Quyen::BAC_DS as $ma => $ten ) {
				echo '<option value="' . esc_attr( $ma ) . '"' . selected( $ma, $cf[ $k ], false ) . '>'
					. esc_html( $ten ) . ' trở lên</option>';
			}
			echo '</select>';
			if ( $v['md'] !== $cf[ $k ] ) {
				/* Nói ra chỗ đã khác mặc định. Sáu tháng sau không ai nhớ mình đã đổi gì, và
				   "vì sao người này không đăng được bài" bắt đầu từ đúng câu hỏi ấy. */
				echo ' <span style="color:#b45309">— đã đổi khác mặc định ('
					. esc_html( VHNB_Quyen::BAC_DS[ $v['md'] ] ) . ')</span>';
			}
			echo '</td></tr>';
		}
		echo '<tr><th scope="row"><label for="slug">Đường dẫn trang</label></th><td>'
			. '<code>' . esc_html( home_url( '/' ) ) . '</code>'
			. '<input id="slug" name="slug" value="' . esc_attr( (string) get_option( 'vhnb_slug' ) ) . '" '
			. 'placeholder="noi-bo" style="width:180px"> <code>/</code>'
			. '<p class="description">Để trống = <code>noi-bo</code>. Đổi xong WordPress tự ghi lại bộ luật đường.</p>'
			. '</td></tr>';
		echo '</tbody></table>';
		submit_button( 'Lưu', 'primary', 'vhnb_luu' );
		echo '</form>';

		echo '<h2>Thông báo</h2>';
		echo '<p>Chuông ở đầu trang nội bộ nhận tin từ <b>chính trang này</b> (ai bình luận / thả tim '
			. 'bài của bạn, ai mời bạn vào nhóm, có bài mới trong nhóm bạn ở) và từ <b>plugin khác '
			. 'đẩy sang</b> — chấm công, chi phí. Tin giữ ' . (int) VHNB_Bao::NGAY_GIU . ' ngày rồi tự dọn.</p>';
		echo '</div>';
	}
}
