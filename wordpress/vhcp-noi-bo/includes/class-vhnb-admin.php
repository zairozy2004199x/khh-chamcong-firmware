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

			/* Trang chủ + phần công khai. Anh Thắng 30/08/2026: *"cho trang này là trang chủ
			   luôn, nhân viên đăng nhập vào sẽ thấy trang này"* và *"thấy trang chủ, nhưng
			   thông tin chung… như hướng dẫn sử dụng chẳng hạn"*. */
			$lam_tc = empty( $_POST['trang_chu'] ) ? 0 : 1;
			update_option( 'vhnb_trang_chu', $lam_tc );
			/* 🔴 MỘT TRANG CHỦ CHỈ CÓ MỘT CHỦ.
			   Plugin Trang cổng cũng có ô "dùng làm trang chủ", cũng móc `template_redirect`,
			   cũng kiểm `is_front_page()`. Bật cả hai thì cái nào chạy trước thắng — mà thứ tự
			   ấy do thứ tự nạp plugin quyết định, tức là do TÊN THƯ MỤC. Không ai đoán được, và
			   đổi tên thư mục một ngày nào đó là trang chủ đổi theo mà chẳng ai hiểu vì sao.
			   Nên: bật cái này thì TẮT cái kia, và nói ra ngay. */
			if ( $lam_tc && get_option( 'vhtc_trang_chu' ) ) {
				update_option( 'vhtc_trang_chu', 0 );
				$bao_them = ' Đã tắt "dùng làm trang chủ" của <b>Trang cổng</b> — một trang chủ '
					. 'chỉ có một chủ. Trang cổng vẫn mở được ở đường dẫn riêng của nó.';
			}
			update_option( 'vhnb_loi_chao', isset( $_POST['loi_chao'] )
				? sanitize_text_field( wp_unslash( $_POST['loi_chao'] ) ) : '' );
			/* 🔴 HƯỚNG DẪN LÀ VĂN BẢN THUẦN, KHÔNG PHẢI HTML. Ô này in ra TRANG CHỦ CÔNG KHAI —
			   ai trên internet cũng đọc được. `wp_kses_post` vẫn cho qua khá nhiều thẻ; ở đây
			   không cần thẻ nào cả, nên cắt sạch bằng `sanitize_textarea_field` và để phần vẽ
			   tự bẻ dòng thành gạch đầu dòng. */
			update_option( 'vhnb_huong_dan', isset( $_POST['huong_dan'] )
				? sanitize_textarea_field( wp_unslash( $_POST['huong_dan'] ) ) : '' );
			$bao = 'Đã lưu.';
			if ( isset( $bao_them ) ) { $bao .= $bao_them; }
		}

		$cf = VHNB_Quyen::cai_dat();
		echo '<div class="wrap"><h1>Nội bộ K&amp;H</h1>';
		if ( '' !== $bao ) {
			/* Lời báo có thể kèm một chữ <b> (tên plugin vừa bị tắt) — cho đúng thẻ ấy, không
			   mở cửa cho thẻ nào khác. */
			echo '<div class="notice notice-success"><p>'
				. wp_kses( $bao, array( 'b' => array() ) ) . '</p></div>';
		}

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
		if ( ! VHNB_Trang::lam_trang_chu() && get_option( 'vhtc_trang_chu' ) ) {
			echo '<div class="notice notice-info inline" style="margin:12px 0"><p>'
				. '<b>Trang cổng</b> đang giữ trang chủ. Tích ô dưới đây là Nội bộ nhận trang chủ '
				. 'và Trang cổng tự nhường — không phải vào bên kia tắt tay.</p></div>';
		}
		echo '<tr><th scope="row">Dùng làm trang chủ</th><td>'
			. '<label><input type="checkbox" name="trang_chu" value="1"'
			. checked( VHNB_Trang::lam_trang_chu(), true, false ) . '> Vào '
			. esc_html( home_url( '/' ) ) . ' là ra thẳng trang Nội bộ</label>'
			. '<p class="description">Người <b>chưa đăng nhập</b> thấy phần công khai: lời chào, '
			. 'hướng dẫn sử dụng và nút đăng nhập — <b>không</b> thấy bài đăng, nhóm hay tên ai. '
			. 'Đăng nhập rồi mới ra bảng tin.<br>'
			/* Câu này để anh Thắng khỏi sợ bật rồi không lùi được — nỗi sợ đó làm người ta không
			   dám bấm, rồi tính năng nằm đó không ai dùng. */
			. '<b>Bật nhầm không sao:</b> wp-admin không bị ảnh hưởng, vào lại đây bỏ tích là xong. '
			. 'Đường dẫn <code>/' . esc_html( VHNB_Trang::slug() ) . '</code> vẫn dùng được như cũ.</p>'
			. '</td></tr>';

		echo '<tr><th scope="row"><label for="loi_chao">Lời chào</label></th><td>'
			. '<input id="loi_chao" name="loi_chao" class="large-text" value="'
			. esc_attr( (string) get_option( 'vhnb_loi_chao', '' ) ) . '" placeholder="'
			. esc_attr( VHNB_Trang::loi_chao() ) . '">'
			. '<p class="description">Một câu dưới tiêu đề ở phần công khai. Để trống = dùng câu mặc định.</p>'
			. '</td></tr>';

		$hd_dang = get_option( 'vhnb_huong_dan', null );
		echo '<tr><th scope="row"><label for="huong_dan">Hướng dẫn sử dụng</label></th><td>'
			. '<textarea id="huong_dan" name="huong_dan" class="large-text" rows="7">'
			. esc_textarea( null === $hd_dang ? VHNB_Trang::HD_MAC_DINH : (string) $hd_dang )
			. '</textarea>'
			. '<p class="description"><b>Mỗi dòng là một gạch đầu dòng</b> trên trang. '
			. 'Chỉ chữ thường, không dùng thẻ HTML — khối này hiện trên trang chủ công khai. '
			. 'Xoá hết rồi lưu = bỏ hẳn khối hướng dẫn.</p>'
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
