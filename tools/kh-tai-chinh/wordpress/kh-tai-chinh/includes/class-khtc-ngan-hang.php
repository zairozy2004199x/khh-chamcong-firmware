<?php
/**
 * Tài khoản ngân hàng và cách tính số dư.
 *
 * @package KHTC
 */

defined( 'ABSPATH' ) || exit;

class KHTC_NganHang {

	public static function ds( $cty = null ) {
		global $wpdb;
		$cty = $cty ? $cty : KHTC_Cty::dang_chon();
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( 'ngan_hang' ) . ' WHERE cty = %s ORDER BY ten', $cty )
		);
	}

	public static function mot( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( 'ngan_hang' ) . ' WHERE id = %d', $id )
		);
	}

	public static function them( $d ) {
		global $wpdb;
		$ten = trim( (string) ( $d['ten'] ?? '' ) );
		if ( '' === $ten ) {
			return new WP_Error( 'thieu_ten', 'Chưa nhập tên tài khoản / ngân hàng.' );
		}
		$wpdb->insert(
			KHTC_DB::bang( 'ngan_hang' ),
			array(
				'cty'       => KHTC_Cty::dang_chon(),
				'ten'       => $ten,
				'so_tk'     => trim( (string) ( $d['so_tk'] ?? '' ) ),
				'so_du_dau' => (int) round( (float) ( $d['so_du_dau'] ?? 0 ) ),
				'ngay_dau'  => ! empty( $d['ngay_dau'] ) ? $d['ngay_dau'] : null,
				'ghi_chu'   => (string) ( $d['ghi_chu'] ?? '' ),
				'tao_luc'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function xoa( $id ) {
		global $wpdb;
		// Xoá ngân hàng mà để lại giao dịch của nó thì số tổng vẫn cộng những dòng
		// không còn thuộc về đâu — xoá cả hai trong một lượt.
		$wpdb->delete( KHTC_DB::bang( 'giao_dich' ), array( 'ngan_hang_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( KHTC_DB::bang( 'ngan_hang' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Số dư = số dư đầu + thu − chi, chỉ tính giao dịch TỪ ngay_dau trở đi.
	 *
	 * Giao dịch trước ngay_dau coi như đã nằm sẵn trong so_du_dau; cộng lại là
	 * đếm hai lần. Bản gốc ghi rõ điều này và đây là chỗ dễ sai nhất của cả màn
	 * hình, nên tính một lần ở đây thay vì rải ra từng trang.
	 */
	public static function so_du( $ngan_hang ) {
		global $wpdb;
		$b   = KHTC_DB::bang( 'giao_dich' );
		$sql = "SELECT
					COALESCE( SUM( CASE WHEN loai = 'thu' THEN so_tien ELSE 0 END ), 0 ) AS thu,
					COALESCE( SUM( CASE WHEN loai = 'chi' THEN so_tien ELSE 0 END ), 0 ) AS chi
				FROM $b WHERE ngan_hang_id = %d";
		$args = array( (int) $ngan_hang->id );
		if ( ! empty( $ngan_hang->ngay_dau ) ) {
			$sql   .= ' AND ngay >= %s';
			$args[] = $ngan_hang->ngay_dau;
		}
		$r = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );
		return (int) $ngan_hang->so_du_dau + (int) $r->thu - (int) $r->chi;
	}

	/** Danh sách kèm số dư đã tính sẵn — dùng cho bảng và cho ô chọn. */
	public static function ds_kem_so_du( $cty = null ) {
		$ra = array();
		foreach ( self::ds( $cty ) as $nh ) {
			$nh->so_du = self::so_du( $nh );
			$ra[]      = $nh;
		}
		return $ra;
	}
}
