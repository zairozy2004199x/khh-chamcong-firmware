<?php
/**
 * Nối thẳng với plugin Báo Cáo Doanh Thu FABi (khh-doanh-thu) cùng site.
 *
 * Bảng `{prefix}khh_dt_ngay` giữ doanh thu từng ngày của từng cửa hàng. Nền tảng
 * đọc thẳng để đặt cạnh giờ công của cơ sở — ra ngay chi phí nhân công / doanh
 * thu từng ngày, ý mượn từ báo cáo ngày của Kintai. Chỉ đọc, không chép.
 *
 * @package khh-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KHH_DT_CO    = 'khh_dt_co';
const KHH_DT_CACHE = 'khh_dt_doc';
const KHH_DT_VER   = 'khh_dt_ver';
const KHH_DT_NHIP  = 300;

function khh_dt_bang_ngay() {
	global $wpdb;
	return $wpdb->prefix . 'khh_dt_ngay';
}

/** Plugin doanh thu có mặt không — nhìn bảng, không nhìn tên plugin. */
function khh_dt_co() {
	global $wpdb;
	$c = get_transient( KHH_DT_CO );
	if ( false !== $c ) {
		return (bool) $c;
	}
	$t  = khh_dt_bang_ngay();
	$co = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t; // phpcs:ignore
	set_transient( KHH_DT_CO, $co ? 1 : 0, 10 * MINUTE_IN_SECONDS );
	return $co;
}

add_action( 'activated_plugin', 'khh_dt_quen_do' );
add_action( 'deactivated_plugin', 'khh_dt_quen_do' );
function khh_dt_quen_do() {
	delete_transient( KHH_DT_CO );
	delete_transient( KHH_DT_CACHE );
}

/** Mã bản ghi: ngày + cửa hàng, gọn và không lệ thuộc dấu tiếng Việt. */
function khh_dt_id( $ngay, $cua_hang ) {
	return 'dt_' . str_replace( '-', '', $ngay ) . '_' . substr( md5( strtolower( trim( $cua_hang ) ) ), 0, 8 );
}

/**
 * Doanh thu theo (ngày, cửa hàng) trong cửa sổ N tháng gần nhất.
 *
 * @return array|null array( docs, ver, stat ) hoặc null nếu chưa có plugin.
 */
function khh_dt_song( $thang = 3 ) {
	global $wpdb;
	if ( ! khh_dt_co() ) {
		return null;
	}
	$c = get_transient( KHH_DT_CACHE );
	if ( is_array( $c ) ) {
		return $c;
	}
	$thang = max( 1, min( 12, (int) $thang ) );
	$moc   = current_time( 'timestamp' ); // phpcs:ignore
	$tu    = gmdate( 'Y-m-01', strtotime( '-' . ( $thang - 1 ) . ' month', $moc ) );
	$den   = gmdate( 'Y-m-t', $moc );

	$rows = $wpdb->get_results( // phpcs:ignore
		$wpdb->prepare(
			'SELECT ngay, cua_hang, SUM(doanh_thu) dt, SUM(thanh_tien) tt, SUM(so_hd) hd FROM ' . khh_dt_bang_ngay()
			. " WHERE ngay BETWEEN %s AND %s AND cua_hang <> '' GROUP BY ngay, cua_hang",
			$tu,
			$den
		),
		ARRAY_A
	);
	$docs = array();
	foreach ( (array) $rows as $r ) {
		$docs[] = array(
			'id'   => khh_dt_id( $r['ngay'], $r['cua_hang'] ),
			'ngay' => (string) $r['ngay'],
			'coso' => trim( (string) $r['cua_hang'] ),
			'dt'   => (float) $r['dt'],
			'tt'   => (float) $r['tt'],
			'hd'   => (int) $r['hd'],
		);
	}

	$hash = md5( (string) wp_json_encode( $docs ) );
	$v    = get_option( KHH_DT_VER, array() );
	$v    = is_array( $v ) ? $v : array();
	if ( ! isset( $v['hash'] ) || $v['hash'] !== $hash ) {
		$cu = isset( $v['ver'] ) ? (int) $v['ver'] : 0;
		$v  = array(
			'hash' => $hash,
			'ver'  => max( (int) round( microtime( true ) * 1000 ) + 1, $cu + 1 ),
		);
		update_option( KHH_DT_VER, $v );
	}
	$kq = array(
		'docs' => $docs,
		'ver'  => (int) $v['ver'],
		'stat' => array(
			'rows' => count( $docs ),
			'tu'   => $tu,
			'den'  => $den,
		),
		'at'   => time(),
	);
	set_transient( KHH_DT_CACHE, $kq, KHH_DT_NHIP );
	return $kq;
}
