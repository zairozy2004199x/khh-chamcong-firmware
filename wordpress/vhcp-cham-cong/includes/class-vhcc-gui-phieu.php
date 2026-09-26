<?php
/**
 * GỬI PHIẾU LƯƠNG KHI CÔNG BỐ — cả hai kênh: chuông trong app (kèm đẩy ra điện thoại) và email.
 *
 * Anh Thắng 26/09/2026: *"đến ngày công bố lương, mỗi người sẽ được 1 bản gửi tự động … dạng
 * pdf"* → chốt *"cả 2 kênh"*. Mỗi người nhận LINK tới trang in phiếu của chính mình
 * (`VHCC_PhieuLuong::link_in()` — link có chữ ký, chỉ mở khi tháng còn đang công bố).
 *
 * ⚠️ TÁCH KHỎI `VHCC_PhieuLuong` CỐ Ý: lớp ấy được canh "chỉ đọc, không đường ghi"
 *    (`tools/test/kiem-phieu-luong.php`). Lượt gửi thì phải đọc email trong hồ sơ và ghi nhật
 *    ký gửi — để nó ở đây, lớp phiếu vẫn sạch.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VHCC_GuiPhieu {

	/** Nhật ký các lượt gửi: [ khoá cơ sở => [ tháng => [ luc, boi, bao, mail, khongMail, hongMail ] ] ]. */
	const O_GUI = 'PHIEU_LUONG_GUI';

	/**
	 * GỬI PHIẾU CHO MỌI NGƯỜI CÓ DÒNG LƯƠNG — cả hai kênh. Gọi ngay sau khi Công bố.
	 *
	 * ⚠️ Không bao giờ để lượt gửi làm hỏng lượt công bố: công bố đã ghi xong trước khi tới đây.
	 * @return array [ 'bao' => số người nhận thông báo, 'mail' => số thư đã gửi,
	 *                 'khongMail' => số người chưa có email, 'hongMail' => số thư gửi hỏng ]
	 */
	public static function gui_cong_bo( $u, $coso, $thang ) {
		global $wpdb;
		$tt = VHCC_Luong::tien_to_thang( $thang );
		$ra = array( 'bao' => 0, 'mail' => 0, 'khongMail' => 0, 'hongMail' => 0 );
		if ( '' === $tt || ! VHCC_PhieuLuong::da_cong_bo( $coso, $tt ) ) { return $ra; }
		$b = VHCC_BangLuong::dung( $coso, $tt );
		if ( empty( $b['ok'] ) ) { return $ra; }
		$ds = array();
		foreach ( $b['dong'] as $d ) { $ds[ strtolower( trim( (string) $d['ma'] ) ) ] = (string) $d['ten']; }
		$nhan = (int) substr( $tt, 5, 2 ) . '/' . substr( $tt, 0, 4 );
		foreach ( $ds as $ma => $ten ) {
			if ( '' === $ma ) { continue; }
			$link = VHCC_PhieuLuong::link_in( $ma, $coso, $tt );
			$chu  = 'Phiếu lương tháng ' . $nhan . ' đã có — bấm để xem và lưu PDF.';
			$ok_bao = VHCC_Chuong::bao( $ma, $chu, 'phieu_luong_' . VHCC_PhieuLuong::khoa_cs( $coso ) . '_' . $tt, '', $link );
			if ( ! $ok_bao && class_exists( 'VHCC_Push' ) && method_exists( 'VHCC_Push', 'gui' ) ) {
				$ok_bao = (bool) VHCC_Push::gui( $ma, 'Phiếu lương tháng ' . $nhan, $chu, $link );
			}
			if ( $ok_bao ) { $ra['bao']++; }
			$email = (string) $wpdb->get_var( $wpdb->prepare(
				'SELECT email FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE LOWER(ma_nv)=%s LIMIT 1', $ma ) );
			if ( '' === trim( $email ) || ! is_email( $email ) ) { $ra['khongMail']++; continue; }
			$than = '<p>Chào ' . esc_html( $ten ) . ',</p><p>Phiếu lương tháng ' . esc_html( $nhan ) . ' của bạn tại '
				. esc_html( VHCC_NhanSu::ten_coso( $coso ) ) . ' đã được công bố.</p>'
				. '<p><a href="' . esc_url( $link ) . '">Bấm vào đây để xem phiếu lương và lưu thành PDF</a></p>'
				. '<p><small>Link chỉ mở được phiếu của riêng bạn. Đừng chuyển tiếp thư này.</small></p>'
				. '<p>' . esc_html( VHCC_Pdf::ten_cong_ty() ) . '</p>';
			$gui = wp_mail( $email, 'Phiếu lương tháng ' . $nhan . ' — ' . VHCC_Pdf::ten_cong_ty(), $than,
				array( 'Content-Type: text/html; charset=UTF-8' ) );
			if ( $gui ) { $ra['mail']++; } else { $ra['hongMail']++; }
		}
		$so = VHCC_Luong::cai_dat( self::O_GUI, null );
		$so = is_array( $so ) ? $so : array();
		$so[ VHCC_PhieuLuong::khoa_cs( $coso ) ][ $tt ] = array_merge( $ra, array( 'luc' => current_time( 'mysql' ),
			'boi' => isset( $u['name'] ) ? (string) $u['name'] : '' ) );
		VHCC_Luong::dat_cai_dat( self::O_GUI, $so, $u );
		return $ra;
	}

	/** Lượt gửi gần nhất của (cơ sở, tháng), hoặc null. */
	public static function lan_gui( $coso, $tt ) {
		$so = VHCC_Luong::cai_dat( self::O_GUI, null );
		$k = VHCC_PhieuLuong::khoa_cs( $coso );
		return ( is_array( $so ) && isset( $so[ $k ][ $tt ] ) ) ? $so[ $k ][ $tt ] : null;
	}

}
