<?php
/**
 * KINH DOANH PHỤ — thống kê TikTok và trích cam.
 *
 * Hai sổ nhỏ, cùng một khuôn: nhân viên tự khai, quản lý xem và chốt. Gộp một tệp vì nếu tách
 * thành hai lớp thì hai tệp giống nhau tới chín phần mười, và sửa một chỗ lại phải nhớ sửa chỗ
 * kia.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

class VHVH_KD {

	/**
	 * Bậc thưởng theo lượt xem. Đọc từ trên xuống, bậc đầu tiên mà `tu` <= lượt xem thì lấy.
	 *
	 * ⚠️ XẾP GIẢM DẦN, và phép tra phải đi từ bậc CAO xuống. Xếp tăng dần rồi lấy bậc khớp đầu
	 *    tiên là clip 1 triệu view cũng chỉ được mức của bậc thấp nhất.
	 */
	public static function bac_mac_dinh() {
		return array(
			array( 'tu' => 1000000, 'tien' => 500000 ),
			array( 'tu' => 500000,  'tien' => 300000 ),
			array( 'tu' => 100000,  'tien' => 150000 ),
			array( 'tu' => 50000,   'tien' => 80000 ),
			array( 'tu' => 10000,   'tien' => 30000 ),
			array( 'tu' => 1000,    'tien' => 10000 ),
		);
	}

	public static function bac() {
		global $wpdb;
		$r = $wpdb->get_var( $wpdb->prepare(
			'SELECT noi_dung FROM ' . VHVH_DB::t( 'danh_muc' ) . ' WHERE loai=%s AND pham_vi=%s',
			'tiktok_bac', '' ) );
		if ( $r ) {
			$j = json_decode( (string) $r, true );
			if ( is_array( $j ) && $j ) {
				$ra = array();
				foreach ( $j as $b ) {
					if ( ! is_array( $b ) || ! isset( $b['tu'] ) ) { continue; }
					$ra[] = array( 'tu' => max( 0, (int) $b['tu'] ), 'tien' => max( 0, (int) $b['tien'] ) );
				}
				if ( $ra ) {
					usort( $ra, function ( $a, $b ) { return $b['tu'] - $a['tu']; } );
					return $ra;
				}
			}
		}
		return self::bac_mac_dinh();
	}

	/** Tiền thưởng cho một con số lượt xem. Chưa khai lượt xem thì 0. */
	public static function thuong( $luot ) {
		if ( null === $luot ) { return 0; }
		$luot = (int) $luot;
		foreach ( self::bac() as $b ) {
			if ( $luot >= $b['tu'] ) { return (int) $b['tien']; }
		}
		return 0;
	}

	/* ══════════════════════════════════════════════════════════════════════ TIKTOK ══════════ */

	public static function tk_them( $u, $d ) {
		global $wpdb;
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }
		$kenh = isset( $d['kenh'] ) ? mb_substr( sanitize_text_field( (string) $d['kenh'] ), 0, 180 ) : '';
		$link = isset( $d['duong_dan'] ) ? esc_url_raw( trim( (string) $d['duong_dan'] ) ) : '';
		if ( '' === trim( $kenh ) ) { return array( 'ok' => false, 'error' => 'Nhập tên kênh.' ); }
		/* 🔴 ĐÒI ĐƯỜNG DẪN THẬT. Không có link thì không ai kiểm được clip có tồn tại không, mà
		   khoản thưởng lại trả theo lượt xem tự khai. */
		if ( '' === $link ) { return array( 'ok' => false, 'error' => 'Dán đường dẫn clip.' ); }

		$ngay = isset( $d['ngay_dang'] ) ? trim( (string) $d['ngay_dang'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { $ngay = current_time( 'Y-m-d' ); }

		$wpdb->insert( VHVH_DB::t( 'tiktok' ), array(
			'coso' => $coso, 'ma_nv' => (string) $u['ma_nv'], 'ten' => (string) $u['ten'],
			'kenh' => $kenh, 'duong_dan' => $link, 'ngay_dang' => $ngay,
			'luot_xem' => null, 'tien' => 0,
			'tao' => current_time( 'mysql' ), 'sua' => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * Khai lượt xem → máy chủ tra bậc rồi tự tính tiền.
	 * 🔴 Nhận `tien` do trang gửi thì ai cũng tự khai cho mình 5 triệu một clip.
	 */
	public static function tk_luot( $u, $id, $luot ) {
		global $wpdb;
		$t = VHVH_DB::t( 'tiktok' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", (int) $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy clip này.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $r['coso'] ) ) { return VHVH_Auth::choi(); }
		if ( $r['chot_luc'] && ! VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ) {
			return array( 'ok' => false, 'error' => 'Clip đã chốt — nhờ quản lý mở lại.' );
		}
		$luot = max( 0, (int) $luot );
		$wpdb->update( $t, array(
			'luot_xem' => $luot, 'tien' => self::thuong( $luot ), 'sua' => current_time( 'mysql' ),
		), array( 'id' => (int) $id ) );
		return array( 'ok' => true, 'tien' => self::thuong( $luot ) );
	}

	/** Chốt / mở lại — chỉ cửa hàng trưởng trở lên. Chốt rồi thì nhân viên không sửa lượt xem nữa. */
	public static function tk_chot( $u, $id, $chot ) {
		global $wpdb;
		if ( ! VHVH_Auth::du_quyen( $u, 'cua_hang_truong' ) ) { return VHVH_Auth::choi(); }
		$t = VHVH_DB::t( 'tiktok' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT coso FROM $t WHERE id=%d", (int) $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy clip này.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $r['coso'] ) ) { return VHVH_Auth::choi(); }
		$wpdb->update( $t, array(
			'chot_luc' => $chot ? current_time( 'mysql' ) : null, 'sua' => current_time( 'mysql' ),
		), array( 'id' => (int) $id ) );
		return array( 'ok' => true );
	}

	public static function tk_ds( $u, $ky, $coso = '' ) {
		return self::doc_ky( $u, 'tiktok', 'ngay_dang', $ky, $coso );
	}

	/* ═══════════════════════════════════════════════════════════════════ TRÍCH CAM ══════════ */

	public static function tc_them( $u, $d ) {
		global $wpdb;
		$coso = isset( $d['coso'] ) ? trim( sanitize_text_field( (string) $d['coso'] ) ) : '';
		if ( '' === $coso || ! VHVH_Auth::duoc_coso( $u, $coso ) ) { return VHVH_Auth::choi(); }
		$hd = isset( $d['so_hd'] ) ? mb_substr( sanitize_text_field( (string) $d['so_hd'] ), 0, 60 ) : '';
		if ( '' === trim( $hd ) ) { return array( 'ok' => false, 'error' => 'Nhập số hoá đơn.' ); }

		/* 🔴 CHẶN TRÙNG SỐ HOÁ ĐƠN TRONG CÙNG CƠ SỞ. Một hoá đơn đăng ký hai lần là một lượt
		   trích cam đếm đôi — và khoản ấy có tính vào thành tích của nhân viên. */
		$trung = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . VHVH_DB::t( 'trich_cam' ) . ' WHERE coso=%s AND so_hd=%s',
			$coso, $hd ) );
		if ( $trung ) {
			return array( 'ok' => false, 'error' => 'Hoá đơn ' . $hd . ' đã đăng ký rồi.' );
		}

		$ngay = isset( $d['ngay_dk'] ) ? trim( (string) $d['ngay_dk'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ngay ) ) { $ngay = current_time( 'Y-m-d' ); }

		$wpdb->insert( VHVH_DB::t( 'trich_cam' ), array(
			'coso' => $coso, 'ma_nv' => (string) $u['ma_nv'], 'ten' => (string) $u['ten'],
			'so_hd' => $hd,
			'sdt' => isset( $d['sdt'] ) ? preg_replace( '/[^0-9+]/', '', (string) $d['sdt'] ) : '',
			'ngay_dk' => $ngay, 'xong' => 0, 'tao' => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id );
	}

	public static function tc_xong( $u, $id, $xong ) {
		global $wpdb;
		$t = VHVH_DB::t( 'trich_cam' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT coso FROM $t WHERE id=%d", (int) $id ), ARRAY_A );
		if ( ! $r ) { return array( 'ok' => false, 'error' => 'Không thấy dòng này.' ); }
		if ( ! VHVH_Auth::duoc_coso( $u, $r['coso'] ) ) { return VHVH_Auth::choi(); }
		$wpdb->update( $t, array(
			'xong' => $xong ? 1 : 0,
			'xong_luc' => $xong ? current_time( 'mysql' ) : null,
		), array( 'id' => (int) $id ) );
		return array( 'ok' => true );
	}

	public static function tc_ds( $u, $ky, $coso = '' ) {
		return self::doc_ky( $u, 'trich_cam', 'ngay_dk', $ky, $coso );
	}

	/* ═════════════════════════════════════════════════════════════════════ dùng chung ══════ */

	/**
	 * Đọc một bảng theo kỳ `YYYY-MM`, đã lọc cơ sở theo quyền NGAY TRONG SQL.
	 * Lọc sau khi lấy về thì dữ liệu cơ sở khác vẫn đã rời khỏi CSDL.
	 */
	private static function doc_ky( $u, $bang, $cot_ngay, $ky, $coso ) {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $ky ) ) { $ky = current_time( 'Y-m' ); }
		$tu  = $ky . '-01';
		$den = gmdate( 'Y-m-t', strtotime( $tu . ' 00:00:00 UTC' ) );

		$dk  = array( "$cot_ngay BETWEEN %s AND %s" );
		$gt  = array( $tu, $den );
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
		$r = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . VHVH_DB::t( $bang ) . ' WHERE ' . implode( ' AND ', $dk )
			. ' ORDER BY id DESC LIMIT 500', $gt ), ARRAY_A );
		return array_map( function ( $x ) {
			$x['id'] = (int) $x['id'];
			if ( isset( $x['tien'] ) ) { $x['tien'] = (int) $x['tien']; }
			if ( isset( $x['luot_xem'] ) ) { $x['luot_xem'] = ( null === $x['luot_xem'] ) ? null : (int) $x['luot_xem']; }
			if ( isset( $x['xong'] ) ) { $x['xong'] = (int) $x['xong']; }
			return $x;
		}, $r ? $r : array() );
	}
}
