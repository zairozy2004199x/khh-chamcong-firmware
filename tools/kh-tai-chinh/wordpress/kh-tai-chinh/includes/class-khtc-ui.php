<?php
/**
 * Mảnh giao diện dùng chung: thanh công ty, thẻ số, bảng, tiền.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_UI {

	public static function tien( $n ) {
		return number_format( (int) $n, 0, ',', '.' ) . ' đ';
	}

	/**
	 * Ngày đầu và ngày cuối của tháng đang chạy, dạng YYYY-MM-DD.
	 *
	 * Phải là ngày CÓ THẬT. Ghép tay "tháng . '-31'" thì lọc trong SQL vẫn đúng
	 * (so sánh chuỗi), nhưng <input type="date" value="2026-09-31"> bị trình
	 * duyệt coi là không hợp lệ và hiện Ô TRỐNG — người dùng thấy bộ lọc trống
	 * trong khi nó đang lọc, bấm Lọc một cái là nới rộng cả kỳ.
	 */
	public static function thang_nay() {
		$dau = current_time( 'Y-m' ) . '-01';
		return array( $dau, gmdate( 'Y-m-t', strtotime( $dau . ' 00:00:00 UTC' ) ) );
	}

	public static function ngay( $s ) {
		if ( empty( $s ) || '0000-00-00' === $s ) { return '—'; }
		return mysql2date( 'd/m/Y', $s );
	}

	/** Đầu trang: tên màn hình, công ty đang xem và nút đổi công ty. */
	public static function dau_trang( $tieu_de ) {
		$dang = KHTC_Cty::dang_chon();
		echo '<div class="khtc-head">';
		echo '<div><h1>' . esc_html( $tieu_de ) . ' — ' . esc_html( KHTC_Cty::ten() ) . '</h1>';
		echo '<p class="khtc-sub">' . esc_html( KHTC_Cty::ten_day_du() ) . '</p></div>';
		if ( '' !== KHTC_Cty::chi_duoc() ) {
			// Chỉ được một bên: không có gì để chọn, nói rõ thay vì hiện nút bấm không ăn.
			echo '<span class="khtc-sub khtc-chi-cty">Tài khoản này chỉ vào sổ ' . esc_html( KHTC_Cty::ten() ) . '</span></div>';
			return;
		}
		echo '<form method="post" class="khtc-cty">';
		wp_nonce_field( 'khtc_cty' );
		foreach ( KHTC_Cty::ds() as $k => $v ) {
			printf(
				'<button type="submit" name="khtc_cty" value="%s" class="khtc-cty-nut%s">%s</button>',
				esc_attr( $k ),
				$dang === $k ? ' dang-chon' : '',
				esc_html( $v['ten'] )
			);
		}
		echo '</form></div>';
	}

	/** Dải thẻ số. $muc = [ [nhãn, giá trị, loại], ... ] với loại: '', 'thu', 'chi'. */
	public static function the_so( $muc ) {
		echo '<div class="khtc-cards">';
		foreach ( $muc as $m ) {
			$loai = isset( $m[2] ) ? $m[2] : '';
			echo '<div class="khtc-card' . ( $loai ? ' ' . esc_attr( $loai ) : '' ) . '">';
			echo '<div class="k">' . esc_html( $m[0] ) . '</div>';
			echo '<div class="v">' . esc_html( $m[1] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	public static function thong_bao( $loai, $chu ) {
		if ( ! $chu ) { return; }
		printf( '<div class="khtc-bao %s">%s</div>', esc_attr( $loai ), esc_html( $chu ) );
	}

	/** Nhận đổi công ty ở mọi màn hình — gọi ở đầu mỗi trang, trước khi đọc dữ liệu. */
	public static function nhan_doi_cty() {
		if ( isset( $_POST['khtc_cty'] ) && check_admin_referer( 'khtc_cty' ) ) {
			KHTC_Cty::chon( sanitize_text_field( wp_unslash( $_POST['khtc_cty'] ) ) );
		} elseif ( isset( $_GET['khtc_cty'] ) ) {
			// Đường link "Sinh hoá đơn KH Cũ" sau nạp lô mang sẵn pháp nhân. Chỉ là
			// chọn bên nào để XEM, không ghi gì, nên không cần nonce.
			KHTC_Cty::chon( sanitize_text_field( wp_unslash( $_GET['khtc_cty'] ) ) );
		}
	}
}
