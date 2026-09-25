<?php
/**
 * PHIẾU NHẬP HÀNG — lập tay hay đẩy lên qua REST, lưu xong là cột "Nhập" của sổ kho ngày ấy theo phiếu.
 *
 * Anh Thắng 25/09/2026: *"Tạo phiếu nhập hàng, khi có phiếu nhập hàng nhập vào hoặc đẩy lên nó sẽ đẩy vào
 * dữ liệu kho hàng"*. Trước đó cột Nhập ở tab Kho phải gõ tay từng món, không có chứng từ nào đứng sau.
 *
 * Cách nối vào kho — cố ý đơn giản:
 *   · Mỗi phiếu = một chứng từ (số phiếu, ngày, cơ sở, nhà cung cấp, từng mặt hàng + số lượng + đơn giá).
 *   · Với một (ngày, cơ sở, mặt hàng), cột Nhập của sổ kho = TỔNG số lượng các phiếu ngày ấy. Lưu / xoá
 *     phiếu là hệ tính lại và ghi vào sổ kho qua đúng `khh_dt_kho_ghi()` (có sổ ghi động, có người, có giờ),
 *     GIỮ NGUYÊN số đếm, hàng huỷ, tồn đầu đặt lại, ghi chú của dòng ấy — chỉ đổi ô Nhập.
 *   · Mặt hàng trên phiếu chưa có trong danh mục kho của quán thì thêm vào (đúng như FABi sẽ ghi).
 *   · Ngày có phiếu thì ô Nhập ở tab Kho khoá lại và ghi "theo phiếu NH…" — sửa số nhập là sửa ở phiếu,
 *     để sổ kho không bao giờ khác chứng từ.
 *
 * "Đẩy lên": cùng cổng POST /khh-dt/v1/phieu-nhap, thân JSON { ngay, co_so, ncc, ghi_chu, so_phieu, dong:[{mh, sl, gia}] }
 * — hệ khác (phần mềm mua hàng, file) gọi vào là thành phiếu, cùng phép gác quyền như người lập tay.
 */

defined( 'ABSPATH' ) || exit;

function khh_dt_bang_pn() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_phieu_nhap';
}

function khh_dt_tao_bang_pn() {
	global $wpdb;
	$bang    = khh_dt_bang_pn();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $bang (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			so_phieu varchar(40) NOT NULL DEFAULT '',
			ngay date NOT NULL,
			co_so varchar(190) NOT NULL DEFAULT '',
			ncc varchar(190) NOT NULL DEFAULT '',
			ghi_chu text NULL,
			dong longtext NULL,
			tong_sl double NOT NULL DEFAULT 0,
			tong_tien double NOT NULL DEFAULT 0,
			nguoi varchar(120) NOT NULL DEFAULT '',
			luc datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY so_phieu (so_phieu),
			KEY ngay_cs (ngay,co_so(60))
		) $charset;"
	);
}

function khh_dt_pn_co_bang() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', khh_dt_bang_pn() ) );
}

/** Tên cơ sở về tên nguyên văn trong kho POS (cùng phép với báo cáo ngày). */
function khh_dt_pn_ten_cua( $t ) {
	return function_exists( 'khh_dt_bc_ten_cua' ) ? khh_dt_bc_ten_cua( $t ) : sanitize_text_field( (string) $t );
}

/** Số phiếu kế tiếp trong ngày: NH<yyyymmdd>-<NN>. */
function khh_dt_pn_so_moi( $ngay ) {
	global $wpdb;
	$dau = 'NH' . str_replace( '-', '', (string) $ngay ) . '-';
	$n   = 0;
	if ( khh_dt_pn_co_bang() ) {
		$bang = khh_dt_bang_pn();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$ds = (array) $wpdb->get_col( $wpdb->prepare( "SELECT so_phieu FROM $bang WHERE so_phieu LIKE %s", $wpdb->esc_like( $dau ) . '%' ) );
		foreach ( $ds as $s ) {
			$k = (int) substr( (string) $s, strlen( $dau ) );
			if ( $k > $n ) {
				$n = $k;
			}
		}
	}
	return $dau . sprintf( '%02d', $n + 1 );
}

/**
 * Làm sạch dòng phiếu: [ {mh, sl, gia} ]. Bỏ dòng thiếu tên hay số lượng ≤ 0; cùng tên thì cộng dồn.
 */
function khh_dt_pn_dong_sach( $tho ) {
	$ds = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	$ra = array();
	foreach ( is_array( $ds ) ? $ds : array() as $d ) {
		if ( ! is_array( $d ) ) {
			continue;
		}
		$mh  = isset( $d['mh'] ) ? trim( (string) $d['mh'] ) : ( isset( $d['mat_hang'] ) ? trim( (string) $d['mat_hang'] ) : '' );
		$mh  = mb_substr( $mh, 0, 190 );
		$sl  = isset( $d['sl'] ) ? $d['sl'] : ( isset( $d['so_luong'] ) ? $d['so_luong'] : null );
		$sl  = function_exists( 'khh_dt_so' ) ? (float) khh_dt_so( (string) $sl ) : (float) $sl;
		$gia = isset( $d['gia'] ) ? ( function_exists( 'khh_dt_so' ) ? (float) khh_dt_so( (string) $d['gia'] ) : (float) $d['gia'] ) : 0.0;
		if ( '' === $mh || $sl <= 0 ) {
			continue;
		}
		$k = function_exists( 'khh_dt_kho_long' ) ? khh_dt_kho_long( $mh ) : mb_strtolower( $mh );
		if ( isset( $ra[ $k ] ) ) {
			$ra[ $k ]['sl'] += $sl;
			if ( $gia > 0 ) {
				$ra[ $k ]['gia'] = $gia;
			}
			continue;
		}
		$ra[ $k ] = array( 'mh' => $mh, 'sl' => $sl, 'gia' => max( 0.0, $gia ) );
	}
	return array_values( $ra );
}

/** Đọc một phiếu ra mảng sạch (dòng đã decode). */
function khh_dt_pn_doc( $r ) {
	if ( ! is_array( $r ) ) {
		return null;
	}
	$dong = json_decode( (string) ( isset( $r['dong'] ) ? $r['dong'] : '' ), true );
	return array(
		'id'        => (int) $r['id'],
		'so_phieu'  => (string) $r['so_phieu'],
		'ngay'      => (string) $r['ngay'],
		'co_so'     => (string) $r['co_so'],
		'ncc'       => (string) $r['ncc'],
		'ghi_chu'   => (string) ( isset( $r['ghi_chu'] ) ? $r['ghi_chu'] : '' ),
		'dong'      => is_array( $dong ) ? $dong : array(),
		'tong_sl'   => (float) $r['tong_sl'],
		'tong_tien' => (float) $r['tong_tien'],
		'nguoi'     => (string) $r['nguoi'],
		'luc'       => (string) $r['luc'],
	);
}

/**
 * LƯU MỘT PHIẾU rồi đồng bộ sổ kho.
 *
 * @param array $hs { ngay, co_so, ncc, ghi_chu, so_phieu (trống = tự đánh), dong }
 * @return array|WP_Error Phiếu vừa lưu.
 */
function khh_dt_pn_luu( $hs ) {
	global $wpdb;
	if ( ! khh_dt_pn_co_bang() ) {
		khh_dt_tao_bang_pn();
	}
	$ngay  = preg_replace( '/[^0-9\-]/', '', (string) ( isset( $hs['ngay'] ) ? $hs['ngay'] : '' ) );
	$co_so = khh_dt_pn_ten_cua( isset( $hs['co_so'] ) ? $hs['co_so'] : '' );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) ) {
		return new WP_Error( 'khh_dt_pn', 'Thiếu ngày nhập (yyyy-mm-dd).', array( 'status' => 400 ) );
	}
	if ( '' === $co_so || '*' === $co_so ) {
		return new WP_Error( 'khh_dt_pn', 'Thiếu cơ sở nhận hàng.', array( 'status' => 400 ) );
	}
	$dong = khh_dt_pn_dong_sach( isset( $hs['dong'] ) ? $hs['dong'] : array() );
	if ( ! $dong ) {
		return new WP_Error( 'khh_dt_pn', 'Phiếu chưa có dòng nào (tên mặt hàng + số lượng > 0).', array( 'status' => 400 ) );
	}
	$so = trim( sanitize_text_field( (string) ( isset( $hs['so_phieu'] ) ? $hs['so_phieu'] : '' ) ) );
	$so = mb_substr( $so, 0, 40 );
	if ( '' === $so ) {
		$so = khh_dt_pn_so_moi( $ngay );
	} else {
		$bang = khh_dt_bang_pn();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $bang WHERE so_phieu = %s", $so ) ) ) {
			return new WP_Error( 'khh_dt_pn', 'Số phiếu ' . $so . ' đã có. Mỗi phiếu một số.', array( 'status' => 400 ) );
		}
	}
	$tong_sl = 0.0;
	$tong_t  = 0.0;
	foreach ( $dong as $d ) {
		$tong_sl += $d['sl'];
		$tong_t  += $d['sl'] * $d['gia'];
	}
	$row = array(
		'so_phieu'  => $so,
		'ngay'      => $ngay,
		'co_so'     => $co_so,
		'ncc'       => mb_substr( sanitize_text_field( (string) ( isset( $hs['ncc'] ) ? $hs['ncc'] : '' ) ), 0, 190 ),
		'ghi_chu'   => sanitize_textarea_field( (string) ( isset( $hs['ghi_chu'] ) ? $hs['ghi_chu'] : '' ) ),
		'dong'      => wp_json_encode( $dong, JSON_UNESCAPED_UNICODE ),
		'tong_sl'   => $tong_sl,
		'tong_tien' => $tong_t,
		'nguoi'     => function_exists( 'khh_dt_ten_ghi_so' ) ? khh_dt_ten_ghi_so() : '',
		'luc'       => current_time( 'mysql' ),
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( false === $wpdb->insert( khh_dt_bang_pn(), $row ) ) {
		return new WP_Error( 'khh_dt_pn', 'Không ghi được phiếu vào cơ sở dữ liệu.', array( 'status' => 500 ) );
	}
	$row['id']   = (int) $wpdb->insert_id;
	$row['dong'] = $dong;

	/* Mặt hàng mới thì đưa vào danh mục kho của quán để có dòng ở tab Kho. */
	if ( function_exists( 'khh_dt_kho_them_mh' ) ) {
		foreach ( $dong as $d ) {
			khh_dt_kho_them_mh( $co_so, khh_dt_pn_ten_mh( $co_so, $d['mh'] ) );
		}
	}
	khh_dt_pn_dong_bo( $ngay, $co_so, wp_list_pluck( $dong, 'mh' ) );
	return $row;
}

/** Tên mặt hàng trên phiếu về đúng tên trong danh mục kho của quán (khớp lỏng), không có thì giữ nguyên. */
function khh_dt_pn_ten_mh( $co_so, $mh ) {
	if ( function_exists( 'khh_dt_kho_ten_mh_chuan' ) && function_exists( 'khh_dt_kho_mh_cua' ) ) {
		return khh_dt_kho_ten_mh_chuan( $mh, khh_dt_kho_mh_cua( $co_so ) );
	}
	return trim( (string) $mh );
}

/** Tổng số lượng nhập theo phiếu của một ngày × cơ sở: [ tên mặt hàng (danh mục) => số lượng ]. */
function khh_dt_pn_tong_ngay( $ngay, $co_so ) {
	$ra = array();
	foreach ( khh_dt_pn_cua_ngay_ds( $ngay, $co_so ) as $p ) {
		foreach ( $p['dong'] as $d ) {
			$mh = khh_dt_pn_ten_mh( $co_so, $d['mh'] );
			$k  = function_exists( 'khh_dt_kho_khoa_long' ) ? khh_dt_kho_khoa_long( $ra, $mh ) : ( isset( $ra[ $mh ] ) ? $mh : null );
			$mh = null !== $k ? $k : $mh;
			$ra[ $mh ] = ( isset( $ra[ $mh ] ) ? $ra[ $mh ] : 0 ) + (float) $d['sl'];
		}
	}
	return $ra;
}

/** Các phiếu của một ngày × cơ sở. */
function khh_dt_pn_cua_ngay_ds( $ngay, $co_so ) {
	global $wpdb;
	if ( ! khh_dt_pn_co_bang() ) {
		return array();
	}
	$bang = khh_dt_bang_pn();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $bang WHERE ngay = %s AND co_so = %s ORDER BY id ASC", $ngay, $co_so ), ARRAY_A );
	return array_values( array_filter( array_map( 'khh_dt_pn_doc', $ds ) ) );
}

/** [ tên mặt hàng => [ số phiếu, … ] ] của một ngày × cơ sở — để tab Kho khoá ô Nhập và ghi "theo phiếu". */
function khh_dt_pn_cua_ngay( $ngay, $co_so ) {
	$ra = array();
	foreach ( khh_dt_pn_cua_ngay_ds( $ngay, $co_so ) as $p ) {
		foreach ( $p['dong'] as $d ) {
			$mh = khh_dt_pn_ten_mh( $co_so, $d['mh'] );
			$ra[ $mh ][] = $p['so_phieu'];
		}
	}
	return $ra;
}

/**
 * ĐỒNG BỘ SỔ KHO: với từng mặt hàng nêu tên, ô Nhập của (ngày, cơ sở) = tổng theo phiếu; các ô khác của dòng
 * (đếm, huỷ, tồn đầu đặt lại, ghi chú) giữ nguyên. Đi qua `khh_dt_kho_ghi()` để sổ ghi động có vết.
 *
 * @param array $mh_ds Tên mặt hàng cần tính lại (mặt hàng của phiếu vừa lưu / vừa xoá).
 * @return int Số dòng kho đã ghi.
 */
function khh_dt_pn_dong_bo( $ngay, $co_so, $mh_ds ) {
	global $wpdb;
	if ( ! function_exists( 'khh_dt_kho_ghi' ) || ! function_exists( 'khh_dt_bang_kho' ) ) {
		return 0;
	}
	$tong = khh_dt_pn_tong_ngay( $ngay, $co_so );
	$bang = khh_dt_bang_kho();
	$n    = 0;
	$xong = array();
	foreach ( (array) $mh_ds as $mh ) {
		$mh = khh_dt_pn_ten_mh( $co_so, $mh );
		$k  = function_exists( 'khh_dt_kho_khoa_long' ) ? khh_dt_kho_khoa_long( $tong, $mh ) : ( isset( $tong[ $mh ] ) ? $mh : null );
		$mh = null !== $k ? $k : $mh;
		if ( isset( $xong[ $mh ] ) ) {
			continue;
		}
		$xong[ $mh ] = true;
		$nhap = isset( $tong[ $mh ] ) ? (float) $tong[ $mh ] : 0.0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$cu = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE ngay = %s AND co_so = %s AND mat_hang = %s", $ngay, $co_so, $mh ), ARRAY_A );
		$o  = array(
			'nhap'      => $nhap,
			'ban_khai'  => $cu ? $cu['ban_khai'] : null,
			'combo_tay' => $cu ? $cu['combo_tay'] : 0,
			'dem'       => $cu ? $cu['dem'] : null,
			'dat_dau'   => $cu ? $cu['dat_dau'] : null,
			'huy'       => $cu ? $cu['huy'] : 0,
			'ghi_chu'   => $cu ? (string) $cu['ghi_chu'] : '',
		);
		$n += khh_dt_kho_ghi( $ngay, $co_so, $mh, $o ) ? 1 : 0;
	}
	return $n;
}

/** Xoá một phiếu rồi tính lại ô Nhập của các mặt hàng trên phiếu. */
function khh_dt_pn_xoa( $id ) {
	global $wpdb;
	if ( ! khh_dt_pn_co_bang() ) {
		return false;
	}
	$bang = khh_dt_bang_pn();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$p = khh_dt_pn_doc( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bang WHERE id = %d", (int) $id ), ARRAY_A ) );
	if ( ! $p ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $bang, array( 'id' => (int) $id ), array( '%d' ) );
	khh_dt_pn_dong_bo( $p['ngay'], $p['co_so'], wp_list_pluck( $p['dong'], 'mh' ) );
	return $p;
}

/** Phiếu gần đây của một cơ sở, mới nhất trước. */
function khh_dt_pn_ds( $co_so, $lui = 90, $gioi_han = 100 ) {
	global $wpdb;
	if ( ! khh_dt_pn_co_bang() ) {
		return array();
	}
	$bang = khh_dt_bang_pn();
	$tu   = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . max( 1, (int) $lui ) . ' days' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $bang WHERE co_so = %s AND ngay >= %s ORDER BY ngay DESC, id DESC LIMIT %d", $co_so, $tu, (int) $gioi_han ),
		ARRAY_A
	);
	return array_values( array_filter( array_map( 'khh_dt_pn_doc', $ds ) ) );
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_pn_route' );
function khh_dt_pn_route() {
	register_rest_route(
		'khh-dt/v1',
		'/phieu-nhap',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_pn_xem',
				'permission_callback' => 'khh_dt_duoc_xem',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_pn_luu',
				'permission_callback' => 'khh_dt_duoc_ghi',
			),
		)
	);
}

/** Người xem có được đụng quán này không (phạm vi cơ sở, không dính quyền ghi) — so lỏng như báo cáo ngày. */
function khh_dt_pn_duoc_xem_cua( $co_so ) {
	$ds = function_exists( 'khh_dt_co_so_ds' ) ? khh_dt_co_so_ds() : array();
	if ( ! $ds || in_array( $co_so, $ds, true ) ) {
		return true;
	}
	if ( function_exists( 'khh_dt_bc_long' ) ) {
		$k = khh_dt_bc_long( $co_so );
		foreach ( $ds as $d ) {
			if ( khh_dt_bc_long( $d ) === $k ) {
				return true;
			}
		}
	}
	return false;
}

function khh_dt_pn_goi_ve( $co_so, $ngay ) {
	return array(
		'co_so'   => $co_so,
		'ngay'    => $ngay,
		'so_moi'  => khh_dt_pn_so_moi( $ngay ),
		'ds'      => khh_dt_pn_ds( $co_so ),
		'xoa_duoc' => function_exists( 'khh_dt_duoc_nap' ) && true === khh_dt_duoc_nap(),
	);
}

function khh_dt_rest_pn_xem( $req ) {
	$co_so = khh_dt_pn_ten_cua( $req->get_param( 'co_so' ) );
	$ngay  = preg_replace( '/[^0-9\-]/', '', (string) $req->get_param( 'ngay' ) );
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ngay ) ) {
		$ngay = current_time( 'Y-m-d' );
	}
	if ( '' === $co_so ) {
		return new WP_Error( 'khh_dt_pn', 'Chưa chọn cơ sở.', array( 'status' => 400 ) );
	}
	if ( ! khh_dt_pn_duoc_xem_cua( $co_so ) ) {
		return new WP_Error( 'khh_dt_pn', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}
	return khh_dt_pn_goi_ve( $co_so, $ngay );
}

function khh_dt_rest_pn_luu( $req ) {
	/* Xoá: chỉ văn phòng (người được nạp file). */
	$xoa = (int) $req->get_param( 'xoa' );
	if ( $xoa > 0 ) {
		if ( ! function_exists( 'khh_dt_duoc_nap' ) || true !== khh_dt_duoc_nap() ) {
			return new WP_Error( 'khh_dt_pn', 'Chỉ văn phòng mới xoá được phiếu nhập.', array( 'status' => 403 ) );
		}
		$p = khh_dt_pn_xoa( $xoa );
		if ( ! $p ) {
			return new WP_Error( 'khh_dt_pn', 'Không thấy phiếu để xoá.', array( 'status' => 404 ) );
		}
		$r = khh_dt_pn_goi_ve( $p['co_so'], $p['ngay'] );
		$r['da_xoa'] = $p['so_phieu'];
		return $r;
	}
	$co_so = khh_dt_pn_ten_cua( $req->get_param( 'co_so' ) );
	if ( '' === $co_so ) {
		return new WP_Error( 'khh_dt_pn', 'Thiếu cơ sở nhận hàng.', array( 'status' => 400 ) );
	}
	if ( function_exists( 'khh_dt_duoc_cua_hang' ) && ! khh_dt_duoc_cua_hang( $co_so ) ) {
		return new WP_Error( 'khh_dt_pn', 'Anh/chị không phụ trách cơ sở này.', array( 'status' => 403 ) );
	}
	$p = khh_dt_pn_luu(
		array(
			'ngay'     => $req->get_param( 'ngay' ),
			'co_so'    => $co_so,
			'ncc'      => $req->get_param( 'ncc' ),
			'ghi_chu'  => $req->get_param( 'ghi_chu' ),
			'so_phieu' => $req->get_param( 'so_phieu' ),
			'dong'     => $req->get_param( 'dong' ),
		)
	);
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$r = khh_dt_pn_goi_ve( $co_so, $p['ngay'] );
	$r['phieu'] = $p;
	return $r;
}
