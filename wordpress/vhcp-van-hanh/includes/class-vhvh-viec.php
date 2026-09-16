<?php
/**
 * THÔNG BÁO & GIAO VIỆC.
 *
 * Hai thứ khác nhau, cố ý để cạnh nhau:
 * · **Thông báo** — nói với nhiều người, không đòi ai làm gì.
 * · **Giao việc**  — gắn tên MỘT người và MỘT hạn.
 *
 * 🔴 ĐỪNG DÙNG THÔNG BÁO ĐỂ GIAO VIỆC. "Nhờ mọi người làm X trước thứ Sáu" gửi cho mười người là
 *    việc của không ai cả: không ai thấy tên mình, không có gì để đánh dấu xong, và đến thứ Sáu
 *    không có cách nào biết nó có được làm hay không. Nên màn Giao việc BẮT BUỘC có người nhận
 *    và hạn, y như Sự cố.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_Viec {

	/* ═════════════════════════════════════════════════════════════════════ THÔNG BÁO ═══════ */

	/**
	 * Đăng thông báo — cửa hàng trưởng trở lên.
	 * Nhân viên đăng được thì bảng tin thành chỗ tán gẫu, và thông báo thật chìm mất.
	 */
	public static function tb_dang( $u, $d ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ) { return VHVH_Auth::choi(); }
		$tieu = isset( $d['tieu_de'] ) ? mb_substr( sanitize_text_field( (string) $d['tieu_de'] ), 0, 240 ) : '';
		if ( '' === trim( $tieu ) ) { return array( 'ok' => false, 'error' => 'Nhập tiêu đề.' ); }

		/* Cơ sở rỗng = gửi cho TOÀN HỆ. Chỉ quản lý được làm vậy — cửa hàng trưởng gửi toàn hệ
		   thì bảng tin đầy thông báo nội bộ của một cơ sở. */
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		if ( '' === $coso ) {
			if ( ! VHVH_Auth::du_quyen( $u, 'quan_ly' ) ) {
				return array( 'ok' => false, 'error' => 'Chỉ Quản lý gửi được thông báo cho toàn hệ.' );
			}
		} elseif ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) {
			return VHVH_Auth::choi();
		}

		$wpdb->insert( VHVH_DB::t( 'thong_bao' ), array(
			'coso'    => $coso,
			'tieu_de' => $tieu,
			'than'    => isset( $d['than'] ) ? mb_substr( sanitize_textarea_field( (string) $d['than'] ), 0, 4000 ) : '',
			'nguoi'   => (string) $u['ten'],
			'vai_tro' => (string) $u['vai'],
			'tao'     => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	public static function tb_xoa( $u, $id ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'quan_ly' ) ) { return VHVH_Auth::choi(); }
		$wpdb->delete( VHVH_DB::t( 'thong_bao' ), array( 'id' => (int) $id ) );
		return array( 'ok' => true );
	}

	/** Thông báo của cơ sở mình + thông báo toàn hệ. */
	public static function tb_ds( $u ) {
		global $wpdb;
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true === $cho ) {
			$r = $wpdb->get_results( 'SELECT * FROM ' . VHVH_DB::t( 'thong_bao' )
				. ' ORDER BY id DESC LIMIT 200', ARRAY_A );
		} else {
			if ( ! $cho ) { $cho = array( '__khong_co__' ); }
			$cho_s = implode( ',', array_fill( 0, count( $cho ), '%s' ) );
			/* `coso=''` là thông báo toàn hệ — ai cũng thấy. */
			$r = $wpdb->get_results( $wpdb->prepare(
				'SELECT * FROM ' . VHVH_DB::t( 'thong_bao' )
				. " WHERE coso='' OR coso IN ($cho_s) ORDER BY id DESC LIMIT 200", $cho ), ARRAY_A );
		}
		return array_map( function ( $x ) { $x['id'] = (int) $x['id']; return $x; }, $r ? $r : array() );
	}

	/* ══════════════════════════════════════════════════════════════════════ GIAO VIỆC ══════ */

	public static function giao( $u, $d ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ) { return VHVH_Auth::choi(); }
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }

		$tieu = isset( $d['tieu_de'] ) ? mb_substr( sanitize_text_field( (string) $d['tieu_de'] ), 0, 240 ) : '';
		if ( '' === trim( $tieu ) ) { return array( 'ok' => false, 'error' => 'Nhập nội dung việc.' ); }

		/* 🔴 BẮT BUỘC người nhận và hạn — xem chú thích đầu tệp. */
		$nhan = isset( $d['giao_ten'] ) ? mb_substr( sanitize_text_field( (string) $d['giao_ten'] ), 0, 190 ) : '';
		if ( '' === trim( $nhan ) ) { return array( 'ok' => false, 'error' => 'Chọn người nhận việc.' ); }
		$han = isset( $d['han'] ) ? trim( (string) $d['han'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $han ) ) {
			return array( 'ok' => false, 'error' => 'Chọn hạn hoàn thành.' );
		}

		$wpdb->insert( VHVH_DB::t( 'viec' ), array(
			'coso' => $coso, 'tieu_de' => $tieu,
			'mo_ta' => isset( $d['mo_ta'] ) ? mb_substr( sanitize_textarea_field( (string) $d['mo_ta'] ), 0, 4000 ) : '',
			'giao_ma_nv' => isset( $d['giao_ma_nv'] ) ? mb_substr( sanitize_text_field( (string) $d['giao_ma_nv'] ), 0, 40 ) : '',
			'giao_ten' => $nhan, 'nguoi_giao' => (string) $u['ten'],
			'han' => $han, 'tt' => 'chua',
			'tao' => current_time( 'mysql' ), 'sua' => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * Đánh dấu xong / mở lại.
	 * Người NHẬN việc tự đánh dấu xong được — bắt chờ quản lý bấm thì danh sách lúc nào cũng đỏ
	 * và không ai tin nó nữa. Cùng luật với Sự cố.
	 */
	public static function doi_tt( $u, $id, $lam ) {
		global $wpdb;
		$t = VHVH_DB::t( 'viec' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", (int) $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy việc này.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $r['coso'] ) ) { return VHVH_Auth::choi(); }

		if ( 'xong' === $lam ) {
			$wpdb->update( $t, array( 'tt' => 'xong', 'xong_luc' => current_time( 'mysql' ),
				'xong_boi' => (string) $u['ten'], 'sua' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		} elseif ( 'mo_lai' === $lam ) {
			$wpdb->update( $t, array( 'tt' => 'chua', 'xong_luc' => null, 'xong_boi' => '',
				'sua' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		} else {
			return array( 'ok' => false, 'error' => 'Việc không hợp lệ.' );
		}
		return array( 'ok' => true );
	}

	public static function ds( $u, $coso = '', $tt = 'chua' ) {
		global $wpdb;
		$dk  = array( '1=1' );
		$gt  = array();
		$cho = VHVH_Auth::coso_duoc( $u );
		if ( true !== $cho ) {
			if ( ! $cho ) { return array(); }
			$dk[] = 'coso IN (' . implode( ',', array_fill( 0, count( $cho ), '%s' ) ) . ')';
			$gt   = array_merge( $gt, $cho );
		}
		if ( '' !== trim( (string) $coso ) ) {
			if ( ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return array(); }
			$dk[] = 'coso=%s'; $gt[] = trim( (string) $coso );
		}
		if ( 'chua' === $tt || 'xong' === $tt ) { $dk[] = 'tt=%s'; $gt[] = $tt; }

		$sql = 'SELECT * FROM ' . VHVH_DB::t( 'viec' ) . ' WHERE ' . implode( ' AND ', $dk )
			. " ORDER BY (tt='chua') DESC, han ASC, id DESC LIMIT 300";
		$r = $gt ? $wpdb->get_results( $wpdb->prepare( $sql, $gt ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );
		$hnay = current_time( 'Y-m-d' );
		return array_map( function ( $x ) use ( $hnay ) {
			$x['id']  = (int) $x['id'];
			/* Trễ tính ở máy chủ — máy khách có thể sai ngày. */
			$x['tre'] = ( 'chua' === $x['tt'] && $x['han'] && $x['han'] < $hnay ) ? 1 : 0;
			return $x;
		}, $r ? $r : array() );
	}

	/** Số việc quá hạn — một mảnh của điểm sức khoẻ cơ sở. */
	public static function dem_tre( $coso ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'viec' )
			. " WHERE coso=%s AND tt='chua' AND han IS NOT NULL AND han < %s",
			$coso, current_time( 'Y-m-d' ) ) );
	}
}
