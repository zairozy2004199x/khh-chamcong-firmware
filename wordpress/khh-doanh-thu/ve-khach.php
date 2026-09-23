<?php
/**
 * BÓC TÁCH VÉ → KHÁCH VÀO: mỗi loại vé trên máy POS tính bao nhiêu người.
 *
 * Anh Thắng 23/09/2026: *"mình sẽ bóc tách sẵn cho nhân viên, giờ áp dụng cho gian Tàu trước"* —
 * *"Tổng khách vào: nếu vé là combo VÉ TRẺ EM + NGƯỜI LỚN tính là 2 người, còn nếu nó là trẻ hoặc
 * người lớn riêng thì là 1 người"*.
 *
 * Trước đó ô "Tổng khách vào" (nhân viên đếm ở cửa) chỉ so được với "Số vé bán" — mà một vé combo
 * là HAI người qua cửa, nên số máy luôn thấp hơn số đếm và cột lệch đỏ oan mỗi ngày. Chỗ này cho
 * quản trị khai MỘT LẦN mỗi tên vé tính mấy khách; máy tự ra "Khách vào (POS)" = Σ số lượng × số
 * khách mỗi vé, và nhân viên chỉ còn việc đếm.
 *
 * ⚠️ KHAI THEO TÊN MÓN, dùng chung cho mọi cơ sở bán món tên ấy — gian Tàu ở Aeon Tân Phú và ở
 *    Vũng Tàu cùng bán "VÉ TRẺ EM + NGƯỜI LỚN" thì khai một lần là đủ cả hai. Cơ sở chưa có vé nào
 *    được khai thì "Khách vào (POS)" là RỖNG (chưa bóc tách), không phải 0 — 0 là "không ai vào".
 *
 * ⚠️ VÉ CHƯA KHAI THÌ PHẢI NÓI RA. Cộng thiếu một loại vé là số máy thấp hơn thật, lệch trông như
 *    nhân viên đếm dư — sai im lặng đúng kiểu người ta không đi tìm. Nên mỗi ngày trả kèm danh sách
 *    vé (theo tên hoặc nhóm món bắt đầu bằng "Vé") có bán mà chưa được khai.
 *
 * @package khh-doanh-thu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bảng khai: [ tên món => số khách mỗi vé (int ≥ 0) ]. */
function khh_dt_ve_khach_bang() {
	$b = get_option( 'khh_dt_ve_khach', array() );
	if ( ! is_array( $b ) ) {
		return array();
	}
	$ra = array();
	foreach ( $b as $ten => $so ) {
		$ten = trim( (string) $ten );
		if ( '' !== $ten && is_numeric( $so ) ) {
			$ra[ $ten ] = (int) $so;
		}
	}
	return $ra;
}

/**
 * Ghi thêm/sửa/xoá vào bảng khai. Giá trị '' hay null là XOÁ tên ấy; số âm bị chối (giữ nguyên).
 * Trả về bảng sau khi ghi.
 */
function khh_dt_ve_khach_dat( $bang ) {
	$cu = khh_dt_ve_khach_bang();
	foreach ( (array) $bang as $ten => $so ) {
		$ten = trim( (string) $ten );
		if ( '' === $ten ) {
			continue;
		}
		if ( null === $so || '' === trim( (string) $so ) ) {
			unset( $cu[ $ten ] );
			continue;
		}
		if ( ! is_numeric( $so ) || (float) $so < 0 ) {
			continue;
		}
		$cu[ $ten ] = (int) round( (float) $so );
	}
	update_option( 'khh_dt_ve_khach', $cu, false );
	return $cu;
}

/** Món này trông như một loại VÉ (tên hay nhóm món có chữ "Vé" đứng đầu một từ). */
function khh_dt_ve_la_ve( $ten, $nhom = '' ) {
	foreach ( array( $ten, $nhom ) as $s ) {
		$k = khh_dt_khong_dau( (string) $s );
		if ( '' !== $k && preg_match( '/(^|[\s:(\/\-])ve(\s|$|:)/', $k ) ) {
			return true;
		}
	}
	return false;
}

/** Gợi ý số khách cho một tên vé: có dấu "+" là vé ghép -> 2; vé lẻ -> 1; không phải vé -> null. */
function khh_dt_ve_khach_goi_y( $ten, $nhom = '' ) {
	if ( ! khh_dt_ve_la_ve( $ten, $nhom ) ) {
		return null;
	}
	return false !== strpos( (string) $ten, '+' ) ? 2 : 1;
}

/**
 * Tính khách từ danh sách món của một ngày ([ {n, g, q, r} ... ]).
 *
 * @return array khach (int|null — null khi chưa có vé nào được khai khớp), da_tach (số loại vé đã
 *               cộng), chua_tach ([ tên => số lượng ] vé có bán mà chưa khai).
 */
function khh_dt_khach_may_tu_mon( $mon, $bang = null ) {
	$bang = null === $bang ? khh_dt_ve_khach_bang() : (array) $bang;
	$khach = 0;
	$n     = 0;
	$chua  = array();
	foreach ( (array) $mon as $m ) {
		$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
		$sl  = isset( $m['q'] ) ? (float) $m['q'] : 0;
		if ( '' === $ten ) {
			continue;
		}
		if ( array_key_exists( $ten, $bang ) ) {
			$khach += $sl * (int) $bang[ $ten ];
			$n++;
			continue;
		}
		if ( $sl > 0 && khh_dt_ve_la_ve( $ten, isset( $m['g'] ) ? (string) $m['g'] : '' ) ) {
			$chua[ $ten ] = ( isset( $chua[ $ten ] ) ? $chua[ $ten ] : 0 ) + $sl;
		}
	}
	return array(
		'khach'     => $n ? (int) round( $khach ) : null,
		'da_tach'   => $n,
		'chua_tach' => $chua,
	);
}

/** Khách vào theo máy cho một ngày, một cơ sở — đọc thẳng cột `mon` của kho số FABi. */
function khh_dt_khach_may( $ngay, $cua_hang ) {
	global $wpdb;
	$bang = khh_dt_bang();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$mon = $wpdb->get_var( $wpdb->prepare( "SELECT mon FROM $bang WHERE ngay = %s AND cua_hang = %s", $ngay, $cua_hang ) );
	$ds  = json_decode( (string) $mon, true );
	return khh_dt_khach_may_tu_mon( is_array( $ds ) ? $ds : array() );
}

/**
 * Những món một cơ sở đã bán trong N ngày gần nhất — để màn Quản trị bày ra cho khai.
 *
 * @return array [ { ten, nhom, so_luong, khach (đã khai | null), goi_y, la_ve } ], vé xếp trước,
 *               rồi theo số lượng giảm dần.
 */
function khh_dt_ve_khach_mon_cua( $cua_hang, $lui = 90 ) {
	global $wpdb;
	$bang = khh_dt_bang();
	$den  = current_time( 'Y-m-d' );
	$tu   = gmdate( 'Y-m-d', strtotime( $den . ' -' . max( 1, (int) $lui ) . ' days' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ds = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT mon FROM $bang WHERE ngay >= %s AND ngay <= %s AND cua_hang = %s", $tu, $den, $cua_hang ),
		ARRAY_A
	);
	$gom = array();
	foreach ( $ds as $r ) {
		$mon = json_decode( (string) $r['mon'], true );
		foreach ( is_array( $mon ) ? $mon : array() as $m ) {
			$ten = isset( $m['n'] ) ? trim( (string) $m['n'] ) : '';
			if ( '' === $ten ) {
				continue;
			}
			if ( ! isset( $gom[ $ten ] ) ) {
				$gom[ $ten ] = array( 'q' => 0, 'g' => isset( $m['g'] ) ? (string) $m['g'] : '' );
			}
			$gom[ $ten ]['q'] += isset( $m['q'] ) ? (float) $m['q'] : 0;
		}
	}
	$khai = khh_dt_ve_khach_bang();
	$ra   = array();
	foreach ( $gom as $ten => $x ) {
		$ra[] = array(
			'ten'      => $ten,
			'nhom'     => $x['g'],
			'so_luong' => $x['q'],
			'khach'    => array_key_exists( $ten, $khai ) ? $khai[ $ten ] : null,
			'goi_y'    => khh_dt_ve_khach_goi_y( $ten, $x['g'] ),
			'la_ve'    => khh_dt_ve_la_ve( $ten, $x['g'] ),
		);
	}
	usort(
		$ra,
		function ( $a, $b ) {
			if ( $a['la_ve'] !== $b['la_ve'] ) {
				return $a['la_ve'] ? -1 : 1;
			}
			if ( $a['so_luong'] === $b['so_luong'] ) {
				return strcmp( $a['ten'], $b['ten'] );
			}
			return $a['so_luong'] < $b['so_luong'] ? 1 : -1;
		}
	);
	return $ra;
}

/* ================================================================== *
 * REST
 * ================================================================== */

add_action( 'rest_api_init', 'khh_dt_ve_khach_route' );
function khh_dt_ve_khach_route() {
	register_rest_route(
		'khh-dt/v1',
		'/ve-khach',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'khh_dt_rest_ve_khach_xem',
				/* Bóc tách là việc của văn phòng (vai duyệt / tài khoản biên tập) — cùng cửa với nạp file.
				   Cửa hàng chỉ đếm, không tự định vé của mình tính mấy người. */
				'permission_callback' => 'khh_dt_duoc_nap',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'khh_dt_rest_ve_khach_dat',
				'permission_callback' => 'khh_dt_duoc_nap',
			),
		)
	);
}

function khh_dt_rest_ve_khach_xem( $req ) {
	$ch = (string) $req->get_param( 'cua_hang' );
	return array(
		'bang'     => khh_dt_ve_khach_bang(),
		'cua_hang' => $ch,
		'mon'      => '' !== $ch ? khh_dt_ve_khach_mon_cua( $ch ) : array(),
	);
}

function khh_dt_rest_ve_khach_dat( $req ) {
	$tho  = $req->get_param( 'bang' );
	$bang = is_array( $tho ) ? $tho : json_decode( (string) $tho, true );
	if ( ! is_array( $bang ) ) {
		return new WP_Error( 'khh_dt_ve_khach', 'Không đọc được bảng khai gửi lên.', array( 'status' => 400 ) );
	}
	$sach = array();
	foreach ( $bang as $ten => $so ) {
		$sach[ sanitize_text_field( (string) $ten ) ] = null === $so ? '' : sanitize_text_field( (string) $so );
	}
	khh_dt_ve_khach_dat( $sach );
	return khh_dt_rest_ve_khach_xem( $req );
}
