<?php
/**
 * MÀN "ĐƠN TỪ" — bốn khối đơn của cửa hàng, gom về một tab.
 *
 * Anh Thắng 18/09/2026: *"Chuyển cái này ra 1 tab riêng ( Đơn từ )"*, kèm ảnh bốn dòng gập nằm
 * dưới đuôi màn Bảng công: Lệnh đi trễ · Đơn xin nghỉ · Đơn xin bù giờ · Sửa bảng công tháng
 * bằng Excel.
 *
 * =============================================================================================
 * 🔴 BỐN KHỐI NÀY CÙNG MỘT LOẠI VIỆC, VÀ NÓ KHÔNG PHẢI VIỆC CỦA MÀN BẢNG CÔNG
 * =============================================================================================
 * Bảng công trả lời *"tháng này ai làm bao nhiêu"*. Bốn khối trên trả lời *"có ai đang chờ mình
 * duyệt không"* — một câu hỏi người ta hỏi mỗi sáng, và hỏi TRƯỚC khi mở bảng công. Nằm dưới
 * đuôi một màn cuộn dài thì câu ấy chỉ được trả lời khi người ta tình cờ cuộn tới.
 *
 * 🔴 VÀ CHÚNG ĐANG BỊ NUỐT MẤT Ở CƠ SỞ TÍNH THEO CÔNG.
 *    Ở chỗ cũ cả bốn nằm trong `if ( 'cong' !== VHCC_Luong::cach_tinh( $cs ) )` — cái chốt ấy
 *    sinh ra cho khối KHAI CA (cơ sở Văn phòng không có ca nên không khai), rồi bốn khối đơn
 *    mọc dần vào trong nó theo thời gian. Hậu quả: cửa hàng trưởng của một cơ sở tính THEO CÔNG
 *    không có cửa nào để duyệt đơn đi trễ, đơn xin nghỉ hay đơn bù giờ của người mình — mà
 *    không một dòng nào nói ra. Dời sang màn riêng là hết luôn chuyện ấy: cách tính công không
 *    còn dính gì tới việc duyệt đơn.
 *
 * ⚠️ KHÔNG NỚI QUYỀN. Mỗi khối tự gác lấy như cũ (`lich_lam` + `co_quyen_coso` cho hai khối đơn;
 *    `VHCC_TuanCong::QUYEN_*` cho hai khối tuần). Màn này chỉ quyết CÓ HIỆN TAB HAY KHÔNG.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebDonTu {

	/**
	 * HAI CỬA, MỘT TAB — cùng lối với tab Khuôn mặt.
	 *
	 * Hai khối đơn gác `lich_lam`, hai khối tuần gác `cong_coso`. Hỏi mỗi một cửa thì người có
	 * việc ở nửa kia lại không thấy tab. Bên trong mỗi khối vẫn tự gác, nên mở tab rộng hơn
	 * không cho ai thêm quyền gì.
	 */
	public static function duoc_vao( $toi ) {
		return VHCC_Vai::duoc( $toi, 'lich_lam' ) || VHCC_Vai::duoc( $toi, 'cong_coso' );
	}

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>📨 Đơn từ</h2>';
		echo '<p class="mo">Mọi thứ cửa hàng gửi lên hoặc chờ anh/chị duyệt, ở một chỗ: '
			. '<b>đi trễ</b> · <b>xin nghỉ</b> · <b>xin bù giờ</b> · <b>sửa bảng công tháng bằng '
			. 'Excel</b>. Khối nào đang có đơn chờ thì tự mở sẵn.</p></div>';

		if ( ! self::duoc_vao( $toi ) ) {
			echo '<div class="the"><div class="bao" style="margin:0">'
				. esc_html( VHCC_Vai::loi( $toi, 'cong_coso', 'Xem đơn từ của cửa hàng' ) )
				. '</div></div>';
			return;
		}

		$ds_cs = VHCC_Web::ds_coso_xem( $toi );
		$cs    = isset( $_GET['lcs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['lcs'] ) ) : '';
		if ( '' === $cs ) { $cs = VHCC_Web::coso_mac_dinh( $toi, $ds_cs ); }

		self::o_loc( $ds_cs, $cs, $toi );

		if ( '' === $cs ) {
			echo '<div class="the"><p class="mo" style="margin:0">Chọn một cơ sở ở trên để xem.</p></div>';
			return;
		}
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) {
			echo '<div class="the"><div class="bao canh" style="margin:0">👤 <b>'
				. esc_html( $cs ) . '</b> là cơ sở anh/chị <b>chấm công</b>, không phải cơ sở anh/chị '
				. 'quản lý — nên không có đơn nào của người khác để duyệt ở đây. Đơn của '
				. '<b>chính anh/chị</b> gửi ở trạm chấm công, nhóm <b>Của tôi</b>.</div></div>';
			return;
		}

		/* Thứ tự: hai khối đơn lẻ trước (việc hằng ngày), rồi hai khối của cả tuần. */
		VHCC_Web::the_lenh_tre( $cs, $ky, $toi );
		VHCC_Web::the_don_nghi( $cs, $ky, $toi );
		VHCC_WebDonTuan::khoi_bu_cht( $ky, $toi, $cs );
		VHCC_WebDonTuan::khoi_cua_hang( $ky, $toi, $cs );
	}

	private static function o_loc( $ds_cs, $cs, $toi ) {
		echo '<div class="the"><form method="get" class="hang" style="gap:10px;margin:0">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<input type="hidden" name="man" value="don_tu">';
		echo '<div><label for="lcs">Cơ sở</label>';
		VHCC_Web::o_chon_coso( 'lcs', $cs, $toi, $ds_cs, '— chọn —' );
		echo '</div>';
		echo '<div style="align-self:flex-end"><button class="chinh">Xem</button></div>';
		echo '</form></div>';
	}
}
