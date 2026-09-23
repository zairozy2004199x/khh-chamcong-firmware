<?php
/**
 * MÀN "BẢNG LƯƠNG" — tab riêng, không còn nằm dưới đuôi màn Bảng công.
 *
 * Anh Thắng 18/09/2026: *"Với chuyển nó ra 1 tab như tính năng, vì sau để bên báo cáo họ lấy dữ
 * liệu lương cho dễ"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO TÁCH RA MỘT MÀN, KHÔNG PHẢI CHỈ DỜI CHỖ CHO GỌN
 * =============================================================================================
 * Lý do anh nói ra là bên BÁO CÁO sẽ lấy dữ liệu lương. Một khối nằm lọt giữa màn Bảng công thì
 * địa chỉ của nó là địa chỉ của màn ấy — muốn trỏ ai đó tới đúng bảng lương tháng 9 của một cơ
 * sở là phải dặn thêm "cuộn xuống dưới cùng". Tách ra thì có một đường đi thẳng, ổn định:
 *
 *     ?man=luong&lcs=<mã cơ sở>&lth=<YYYY-MM>
 *
 * Đường ấy là thứ hệ báo cáo (và cả người ta, khi gửi link cho nhau) cầm được. Nút xuất .xlsx
 * cũng nằm ngay trong màn, nên "lấy dữ liệu" không nhất thiết phải là đọc màn hình.
 *
 * ⚠️ KHÔNG NỚI QUYỀN MỘT LI. Màn chỉ vẽ; mọi chốt vẫn nằm trong `VHCC_Web::the_bang_luong_cs()`
 *    (`cong_coso` + `co_quyen_coso` cho đúng cơ sở đang xem) và trong `VHCC_ChotLuong::QUYEN`
 *    cho quyền GÕ khoản cộng/trừ. Thêm một cửa vào mà quên chốt là mở toang cả bảng lương chuỗi
 *    — nên ở đây cố ý KHÔNG tự viết lại chốt nào, chỉ gọi đúng hàm đã có chốt.
 *
 * ⚠️ MÃ THAM SỐ DÙNG CHUNG VỚI MÀN LỊCH SỬ (`lcs` · `lth`). Cùng một ý nghĩa thì cùng một tên:
 *    người ta đổi `man=` trên thanh địa chỉ là sang màn khác mà vẫn đúng cơ sở, đúng tháng.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_WebLuong {

	/** Cùng cửa với màn Bảng công — ai đọc được bảng công thì đọc được bảng lương của nó. */
	const QUYEN = 'cong_coso';

	public static function man( $ky, $toi ) {
		echo '<div class="the"><h2>💵 Bảng lương</h2>';
		echo '<p class="mo">Bảng lương của một cơ sở trong một tháng, <b>đúng bố cục file kế toán '
			. 'đang dùng</b>. Số giờ lấy thẳng từ bảng công; đơn giá lấy từ sổ đơn giá. Cột nào '
			. 'cả bảng không có số thì tự ẩn — kế toán gõ khoản nào, cột ấy hiện ra.</p></div>';

		if ( ! VHCC_Vai::duoc( $toi, self::QUYEN ) ) {
			echo '<div class="the"><div class="bao" style="margin:0">'
				. esc_html( VHCC_Vai::loi( $toi, self::QUYEN, 'Xem bảng lương cơ sở' ) )
				. '</div></div>';
			return;
		}

		$ds_cs = VHCC_Web::ds_coso_xem( $toi );
		$cs    = isset( $_GET['lcs'] ) ? VHCC_NhanSu::chuan_coso( wp_unslash( $_GET['lcs'] ) ) : '';
		if ( '' === $cs ) { $cs = VHCC_Web::coso_mac_dinh( $toi, $ds_cs ); }
		$th = isset( $_GET['lth'] ) ? sanitize_text_field( wp_unslash( $_GET['lth'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $th ) ) { $th = substr( (string) current_time( 'Y-m-d' ), 0, 7 ); }

		self::o_loc( $ds_cs, $cs, $th, $toi );

		if ( '' === $cs ) {
			echo '<div class="the"><p class="mo" style="margin:0">Chọn một cơ sở ở trên để xem.</p></div>';
			return;
		}
		/* 🔴 CHỐI RA CHỐI, ĐỪNG VẼ MỘT MÀN TRỐNG. `the_bang_luong_cs()` gặp cơ sở ngoài tầm là
		   `return` lặng lẽ — đúng cho một khối nằm giữa màn khác, nhưng ở đây nó LÀ cả màn, và
		   một màn trắng không câu nào là thứ người dùng không sửa được. */
		if ( ! VHCC_NhanSu::co_quyen_coso( $toi, $cs ) ) {
			echo '<div class="the"><div class="bao canh" style="margin:0">👤 <b>'
				. esc_html( $cs ) . '</b> không phải cơ sở anh/chị quản lý, nên không có bảng lương '
				. 'ở đây. Lương của <b>chính anh/chị</b> nằm ở <b>Phiếu lương</b> trên trạm chấm '
				. 'công — tháng nào kế toán đã công bố thì xem được.</div></div>';
			return;
		}

		VHCC_Web::the_bang_luong_cs( $toi, $cs, $th, $ky );
	}

	private static function o_loc( $ds_cs, $cs, $th, $toi ) {
		echo '<div class="the"><form method="get" class="hang" style="gap:10px;margin:0">';
		if ( ! get_option( 'permalink_structure' ) ) { echo '<input type="hidden" name="vhcc_qt" value="1">'; }
		echo '<input type="hidden" name="man" value="luong">';
		echo '<div><label for="lcs">Cơ sở</label>';
		VHCC_Web::o_chon_coso( 'lcs', $cs, $toi, $ds_cs, '— chọn —' );
		echo '</div>';
		echo '<div><label for="lth">Tháng</label>'
			. '<input id="lth" name="lth" type="month" value="' . esc_attr( $th ) . '"></div>';
		echo '<div style="align-self:flex-end"><button class="chinh">Xem</button></div>';
		echo '</form></div>';
	}
}
