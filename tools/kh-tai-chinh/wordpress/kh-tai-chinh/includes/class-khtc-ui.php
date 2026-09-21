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
		}
	}
}
