<?php
/**
 * SỰ CỐ — việc hỏng ngoài hiện trường, có người phụ trách và có hạn.
 *
 * 🔴 BẮT BUỘC CÓ NGƯỜI PHỤ TRÁCH VÀ HẠN XỬ LÝ. Sự cố không gắn tên ai thì thành cái danh sách
 *    ai cũng đọc và không ai làm; không có hạn thì không bao giờ trễ, nên cũng không bao giờ
 *    được nhắc. Đây là hai ô duy nhất bắt buộc ngoài tiêu đề.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_SuCo {

	const MUC = array( 'nhe' => 'Nhẹ', 'canh_bao' => 'Cảnh báo', 'nang' => 'Nghiêm trọng' );

	public static function them( $u, $d ) {
		global $wpdb;
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		if ( '' === $coso ) { return array( 'ok' => false, 'error' => 'Thiếu cơ sở.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }

		$tieu = isset( $d['tieu_de'] ) ? mb_substr( sanitize_text_field( (string) $d['tieu_de'] ), 0, 240 ) : '';
		if ( '' === trim( $tieu ) ) { return array( 'ok' => false, 'error' => 'Nhập tiêu đề sự cố.' ); }

		$giao_ten = isset( $d['giao_ten'] ) ? mb_substr( sanitize_text_field( (string) $d['giao_ten'] ), 0, 190 ) : '';
		if ( '' === trim( $giao_ten ) ) { return array( 'ok' => false, 'error' => 'Chọn người phụ trách xử lý.' ); }

		$han = isset( $d['han'] ) ? trim( (string) $d['han'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $han ) ) {
			return array( 'ok' => false, 'error' => 'Chọn hạn xử lý.' );
		}

		$muc = isset( $d['muc'] ) ? (string) $d['muc'] : 'canh_bao';
		if ( ! isset( self::MUC[ $muc ] ) ) { $muc = 'canh_bao'; }

		$wpdb->insert( VHVH_DB::t( 'su_co' ), array(
			'coso'       => $coso,
			'tieu_de'    => $tieu,
			'mo_ta'      => isset( $d['mo_ta'] ) ? mb_substr( sanitize_textarea_field( (string) $d['mo_ta'] ), 0, 4000 ) : '',
			'muc'        => $muc,
			'tt'         => 'mo',
			'bao_ma_nv'  => (string) $u['ma_nv'],
			'bao_ten'    => (string) $u['ten'],
			'giao_ma_nv' => isset( $d['giao_ma_nv'] ) ? mb_substr( sanitize_text_field( (string) $d['giao_ma_nv'] ), 0, 40 ) : '',
			'giao_ten'   => $giao_ten,
			'han'        => $han,
			'tao'        => current_time( 'mysql' ),
			'sua'        => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * Đóng / mở lại một sự cố.
	 *
	 * Ai cũng đóng được sự cố của cơ sở mình — người sửa xong cái đèn là người biết nó đã xong,
	 * bắt họ chờ quản lý bấm thì danh sách lúc nào cũng đỏ và không ai tin nó nữa.
	 */
	public static function doi_tt( $u, $id, $viec ) {
		global $wpdb;
		$id = (int) $id;
		$t  = VHVH_DB::t( 'su_co' );
		$r  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy sự cố này.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $r['coso'] ) ) { return VHVH_Auth::choi(); }

		if ( 'dong' === $viec ) {
			$wpdb->update( $t, array(
				'tt' => 'dong', 'dong_luc' => current_time( 'mysql' ),
				'dong_boi' => (string) $u['ten'], 'sua' => current_time( 'mysql' ),
			), array( 'id' => $id ) );
		} elseif ( 'mo_lai' === $viec ) {
			$wpdb->update( $t, array(
				'tt' => 'mo', 'dong_luc' => null, 'dong_boi' => '', 'sua' => current_time( 'mysql' ),
			), array( 'id' => $id ) );
		} else {
			return array( 'ok' => false, 'error' => 'Việc không hợp lệ.' );
		}
		return array( 'ok' => true );
	}

	/** Danh sách; `$tt` rỗng là lấy cả đang mở lẫn đã đóng. */
	public static function ds( $u, $coso = '', $tt = 'mo' ) {
		global $wpdb;
		$dk  = array( '1=1' );
		$gt  = array();
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) {
			if ( ! $cho ) { return array(); }
			$dk[] = 'coso IN (' . implode( ',', array_fill( 0, count( $cho ), '%s' ) ) . ')';
			$gt   = array_merge( $gt, $cho );
		}
		$coso = trim( (string) $coso );
		if ( '' !== $coso ) {
			if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return array(); }
			$dk[] = 'coso=%s'; $gt[] = $coso;
		}
		if ( 'mo' === $tt || 'dong' === $tt ) { $dk[] = 'tt=%s'; $gt[] = $tt; }

		$sql = "SELECT * FROM " . VHVH_DB::t( 'su_co' ) . ' WHERE ' . implode( ' AND ', $dk )
			/* Đang mở lên trước, rồi tới hạn gần nhất — thứ sắp trễ phải nằm trên đầu màn hình. */
			. " ORDER BY (tt='mo') DESC, han ASC, id DESC LIMIT 300";
		$r = $gt ? $wpdb->get_results( $wpdb->prepare( $sql, $gt ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );
		$hnay = current_time( 'Y-m-d' );
		return array_map( function ( $x ) use ( $hnay ) {
			$x['id']  = (int) $x['id'];
			/* Trễ hạn tính ở máy chủ: máy khách có thể sai ngày, và một cái đồng hồ lệch là cả
			   màn hình đỏ hoặc cả màn hình xanh sai. */
			$x['tre'] = ( 'mo' === $x['tt'] && $x['han'] && $x['han'] < $hnay ) ? 1 : 0;
			return $x;
		}, $r ? $r : array() );
	}

	/** Số sự cố đang mở — cho chấm đỏ trên thanh điều hướng. */
	public static function dem_mo( $u ) {
		return count( self::ds( $u, '', 'mo' ) );
	}
}
