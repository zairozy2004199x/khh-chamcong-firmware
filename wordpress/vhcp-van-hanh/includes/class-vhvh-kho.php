<?php
/**
 * KIỂM KHO — cơ sở vật chất, đếm theo TUẦN.
 *
 * =================================================================================================
 * 🔴 HAI KIỂU THEO DÕI, VÀ ĐỪNG GỘP LÀM MỘT
 * =================================================================================================
 * · `dem`      — chỉ đếm số lượng (tivi, loa, chổi). Mất là mất, không có "mất một nửa".
 * · `dem_tinh` — đếm số lượng KÈM phần trăm còn dùng được (tủ thờ, tủ trang điểm). Mấy món ấy
 *                hao mòn dần: vẫn còn đủ 2 cái, nhưng một cái sắp bung. Chỉ đếm số lượng thì
 *                đến lúc nó gãy giữa buổi diễn mới biết, mà sổ vẫn ghi "đủ 2".
 *
 * 🔴 TUẦN BẮT ĐẦU TỪ THỨ HAI, VÀ QUY VỀ THỨ HAI Ở MÁY CHỦ.
 * Nhận thẳng chuỗi ngày do trang gửi thì hai người kiểm cùng một tuần mà gửi hai ngày khác nhau
 * là ra hai bản ghi cho một tuần — khoá duy nhất (coso, tuan_tu) không cứu được, vì hai giá trị
 * `tuan_tu` khác nhau thật. Quy về thứ Hai trước khi ghi thì mọi ngày trong tuần đều rơi vào
 * đúng một bản.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_Kho {

	const KIEU = array( 'dem' => 'Đếm số lượng', 'dem_tinh' => 'Đếm + % còn dùng được' );

	/** Danh mục mặc định — lấy nguyên từ bản đang chạy. */
	public static function mac_dinh() {
		return array(
			array( 'khoa' => 'decor', 'ten' => 'Đồ decor', 'muc' => array(
				array( 'khoa' => 'xuong_gia', 'ten' => 'Xương giả', 'kieu' => 'dem' ),
				array( 'khoa' => 'xuong_silicon', 'ten' => 'Xương silicon', 'kieu' => 'dem' ),
				array( 'khoa' => 'tu_tho', 'ten' => 'Tủ thờ', 'kieu' => 'dem_tinh' ),
				array( 'khoa' => 'tu_trang_diem', 'ten' => 'Tủ trang điểm', 'kieu' => 'dem_tinh' ),
			) ),
			array( 'khoa' => 'ki_thuat', 'ten' => 'Đồ kĩ thuật', 'muc' => array(
				array( 'khoa' => 'tivi', 'ten' => 'Tivi', 'kieu' => 'dem' ),
				array( 'khoa' => 'loa', 'ten' => 'Loa', 'kieu' => 'dem' ),
				array( 'khoa' => 'may_chieu', 'ten' => 'Máy chiếu', 'kieu' => 'dem' ),
			) ),
			array( 'khoa' => 'van_hanh', 'ten' => 'Đồ vận hành', 'muc' => array(
				array( 'khoa' => 'so_cam_ket', 'ten' => 'Sổ cam kết', 'kieu' => 'dem' ),
				array( 'khoa' => 'choi', 'ten' => 'Chổi', 'kieu' => 'dem' ),
				array( 'khoa' => 'may_hut_bui', 'ten' => 'Máy hút bụi', 'kieu' => 'dem' ),
			) ),
		);
	}

	public static function danh_muc( $coso = '' ) {
		global $wpdb;
		$t = VHVH_DB::t( 'danh_muc' );
		foreach ( array( (string) $coso, '' ) as $pv ) {
			$r = $wpdb->get_var( $wpdb->prepare(
				"SELECT noi_dung FROM $t WHERE loai=%s AND pham_vi=%s", 'kho', $pv ) );
			if ( $r ) {
				$j = json_decode( (string) $r, true );
				if ( is_array( $j ) && $j ) { return self::rua( $j ); }
			}
		}
		return self::mac_dinh();
	}

	public static function rua( $ds ) {
		$ra = array();
		foreach ( (array) $ds as $n ) {
			if ( ! is_array( $n ) ) { continue; }
			$khoa = isset( $n['khoa'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $n['khoa'] ) ) : '';
			$muc  = array();
			foreach ( (array) ( isset( $n['muc'] ) ? $n['muc'] : array() ) as $m ) {
				if ( ! is_array( $m ) ) { continue; }
				$mk = isset( $m['khoa'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $m['khoa'] ) ) : '';
				if ( '' === $mk ) { continue; }
				$kieu = ( isset( $m['kieu'] ) && isset( self::KIEU[ $m['kieu'] ] ) ) ? $m['kieu'] : 'dem';
				$muc[] = array(
					'khoa' => $mk,
					'ten'  => isset( $m['ten'] ) ? mb_substr( sanitize_text_field( (string) $m['ten'] ), 0, 120 ) : $mk,
					'kieu' => $kieu,
				);
			}
			if ( '' === $khoa || ! $muc ) { continue; }
			$ra[] = array(
				'khoa' => $khoa,
				'ten'  => isset( $n['ten'] ) ? mb_substr( sanitize_text_field( (string) $n['ten'] ), 0, 120 ) : $khoa,
				'muc'  => $muc,
			);
		}
		return $ra;
	}

	/**
	 * Quy một ngày bất kỳ về thứ Hai của tuần chứa nó.
	 * Dùng UTC để khỏi dính giờ mùa hè — ở đây chỉ cần đếm ngày, không cần giờ địa phương.
	 */
	public static function dau_tuan( $ngay ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ngay ) ) { return ''; }
		$ts  = strtotime( $ngay . ' 00:00:00 UTC' );
		if ( false === $ts ) { return ''; }
		$thu = (int) gmdate( 'N', $ts ); /* 1 = thứ Hai … 7 = Chủ nhật */
		return gmdate( 'Y-m-d', $ts - ( $thu - 1 ) * 86400 );
	}

	public static function doc( $coso, $ngay ) {
		global $wpdb;
		$tuan = self::dau_tuan( $ngay );
		if ( '' === $tuan ) { return null; }
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . VHVH_DB::t( 'kho' ) . ' WHERE coso=%s AND tuan_tu=%s', $coso, $tuan ), ARRAY_A );
		if ( ! $r ) { return null; }
		$j = json_decode( (string) $r['muc'], true );
		$r['muc'] = is_array( $j ) ? $j : array();
		return $r;
	}

	public static function luu( $u, $d ) {
		global $wpdb;
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		$tuan = self::dau_tuan( isset( $d['ngay'] ) ? (string) $d['ngay'] : '' );
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		if ( '' === $tuan ) { return array( 'ok' => false, 'error' => 'Ngày không hợp lệ.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }

		$dm  = self::danh_muc( $coso );
		$hop = array();
		foreach ( $dm as $n ) {
			foreach ( $n['muc'] as $m ) { $hop[ $m['khoa'] ] = $m['kieu']; }
		}

		$ghi = array();
		foreach ( (array) ( isset( $d['muc'] ) ? $d['muc'] : array() ) as $k => $v ) {
			$k = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) );
			/* Khoá lạ bị bỏ — cùng lý do với checklist: nhận khoá bịa là sổ kho có món không tồn tại. */
			if ( ! isset( $hop[ $k ] ) || ! is_array( $v ) ) { continue; }
			$so = isset( $v['so'] ) ? max( 0, (int) $v['so'] ) : 0;
			$o  = array( 'so' => $so );
			if ( 'dem_tinh' === $hop[ $k ] ) {
				/* Kẹp 0–100: người ta gõ nhầm 1000 thì món ấy thành "tốt hơn cả mới". */
				$o['tinh'] = isset( $v['tinh'] ) ? max( 0, min( 100, (int) $v['tinh'] ) ) : 100;
			}
			if ( isset( $v['ghi'] ) ) {
				$o['ghi'] = mb_substr( sanitize_text_field( (string) $v['ghi'] ), 0, 240 );
			}
			$ghi[ $k ] = $o;
		}

		$hang = array(
			'coso'    => $coso,
			'tuan_tu' => $tuan,
			'muc'     => wp_json_encode( $ghi ),
			'nguoi'   => (string) $u['ten'],
			'sua'     => current_time( 'mysql' ),
		);
		$t  = VHVH_DB::t( 'kho' );
		$cu = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $t WHERE coso=%s AND tuan_tu=%s", $coso, $tuan ) );
		if ( $cu ) {
			$wpdb->update( $t, $hang, array( 'id' => (int) $cu ) );
		} else {
			$hang['tao'] = current_time( 'mysql' );
			if ( ! $wpdb->insert( $t, $hang ) ) {
				$lai = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM $t WHERE coso=%s AND tuan_tu=%s", $coso, $tuan ) );
				if ( ! $lai ) { return array( 'ok' => false, 'error' => 'Không ghi được sổ kho.' ); }
				$wpdb->update( $t, $hang, array( 'id' => (int) $lai ) );
			}
		}
		return array( 'ok' => true, 'ban' => self::doc( $coso, $tuan ) );
	}

	/**
	 * So tuần này với tuần trước — thứ người ta thật sự muốn biết: **mất mát** và **xuống cấp**.
	 * Chỉ nhìn con số tuần này thì không ai phát hiện tuần trước 6 cái chổi giờ còn 2.
	 */
	public static function so_sanh( $coso, $ngay ) {
		$tuan = self::dau_tuan( $ngay );
		if ( '' === $tuan ) { return array(); }
		$truoc = gmdate( 'Y-m-d', strtotime( $tuan . ' 00:00:00 UTC' ) - 7 * 86400 );
		$nay   = self::doc( $coso, $tuan );
		$cu    = self::doc( $coso, $truoc );
		if ( ! $nay || ! $cu ) { return array(); }
		$ra = array();
		foreach ( $nay['muc'] as $k => $v ) {
			if ( ! isset( $cu['muc'][ $k ] ) ) { continue; }
			$lech = (int) $v['so'] - (int) $cu['muc'][ $k ]['so'];
			$hao  = ( isset( $v['tinh'] ) && isset( $cu['muc'][ $k ]['tinh'] ) )
				? (int) $v['tinh'] - (int) $cu['muc'][ $k ]['tinh'] : null;
			if ( 0 !== $lech || ( null !== $hao && 0 !== $hao ) ) {
				$ra[ $k ] = array( 'lech' => $lech, 'hao' => $hao );
			}
		}
		return $ra;
	}
}
