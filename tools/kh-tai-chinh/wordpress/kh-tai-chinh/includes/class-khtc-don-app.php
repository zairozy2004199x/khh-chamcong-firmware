<?php
/**
 * Đơn hàng Zalo mini app — BẢNG TRA, không phải tiền.
 *
 * Mini app bán vé cho nhiều cơ sở cùng lúc; tiền về qua VNPay/MoMo dưới một
 * mã điểm thu chung (FUNZONE1 "FUNZONE MINI APP"). Nhìn dòng tiền không biết
 * của cơ sở nào — cơ sở nằm trong tên sản phẩm của ĐƠN. Dòng VNPay ghi "thanh
 * toan don hang 141819 tu funzone"; tra 141819 ở đây ra "VINCOM PHAN VĂN TRỊ -
 * SALE 50% ...", và tên đó đi vào danh mục điểm như một mã cửa hàng.
 *
 * Tiền vẫn tính từ VNPay/MoMo (tiền thật đã về). Bảng này chỉ trả lời "đơn đó
 * của cơ sở nào", nên nạp bao nhiêu lần cũng không đếm đôi đồng nào.
 */

defined( 'ABSPATH' ) || exit;

class KHTC_DonApp {

	/**
	 * Lưu (đè) danh sách đơn. Cùng mã đơn thì cập nhật, không nhân đôi.
	 *
	 * @param array $rows [ ['ma_don','ngay'(iso),'co_so','tien','tt_don','tt_tt'] ]
	 * @return array them, sua
	 */
	public static function nap( $rows ) {
		global $wpdb;
		$cty  = KHTC_Cty::dang_chon();
		$b    = KHTC_DB::bang( 'don_app' );
		$them = 0; $sua = 0;
		$ma_ds = array();
		foreach ( $rows as $r ) { if ( '' !== (string) $r['ma_don'] ) { $ma_ds[] = (string) $r['ma_don']; } }
		$co = array();
		foreach ( array_chunk( array_unique( $ma_ds ), 500 ) as $khuc ) {
			$rs = $wpdb->get_results( $wpdb->prepare( "SELECT id, ma_don FROM $b WHERE cty = %s AND ma_don IN (" . implode( ',', array_fill( 0, count( $khuc ), '%s' ) ) . ')', array_merge( array( $cty ), $khuc ) ) );
			foreach ( $rs as $x ) { $co[ $x->ma_don ] = (int) $x->id; }
		}
		foreach ( $rows as $r ) {
			$ma = (string) $r['ma_don'];
			if ( '' === $ma ) { continue; }
			$d = array(
				'cty'    => $cty,
				'ma_don' => $ma,
				'ngay'   => $r['ngay'] ?: null,
				'co_so'  => mb_substr( (string) $r['co_so'], 0, 190 ),
				'tien'   => (int) $r['tien'],
				'tt_don' => mb_substr( (string) ( $r['tt_don'] ?? '' ), 0, 40 ),
				'tt_tt'  => mb_substr( (string) ( $r['tt_tt'] ?? '' ), 0, 40 ),
			);
			if ( isset( $co[ $ma ] ) ) {
				$wpdb->update( $b, $d, array( 'id' => $co[ $ma ] ), array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
				$sua++;
			} else {
				$d['tao_luc'] = current_time( 'mysql' );
				$wpdb->insert( $b, $d, array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );
				$co[ $ma ] = (int) $wpdb->insert_id;
				$them++;
			}
		}
		KHTC_NhatKy::ghi( 'nap', 'don_app', 0, sprintf( 'Nạp %d đơn mini app%s', $them, $sua ? ', cập nhật ' . $sua : '' ) );
		return array( 'them' => $them, 'sua' => $sua );
	}

	/** Bảng tra: mã đơn → tên sản phẩm (cơ sở). */
	public static function tra( $cty = null ) {
		global $wpdb;
		$cty = $cty ?: KHTC_Cty::dang_chon();
		$rs  = $wpdb->get_results( $wpdb->prepare( 'SELECT ma_don, co_so FROM ' . KHTC_DB::bang( 'don_app' ) . ' WHERE cty = %s', $cty ) );
		$ra  = array();
		foreach ( $rs as $r ) { $ra[ $r->ma_don ] = $r->co_so; }
		return $ra;
	}

	public static function dem( $cty = null ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'don_app' ) . ' WHERE cty = %s', $cty ?: KHTC_Cty::dang_chon() ) );
	}

	/**
	 * Rút mã đơn từ diễn giải của dòng tiền: "thanh toan don hang 141819 tu funzone".
	 * Không thấy thì trả ''. Bốn chữ số trở lên để không bắt nhầm số lẻ.
	 */
	public static function ma_don_trong( $dien_giai ) {
		if ( preg_match( '/(?:don\s*hang|đơn\s*hàng|order)\s*#?\s*(\d{4,})/iu', (string) $dien_giai, $m ) ) { return $m[1]; }
		return '';
	}
}
