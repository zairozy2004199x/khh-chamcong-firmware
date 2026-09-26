<?php
/**
 * ĐẾM KÉT = 0 MÀ ĐÃ KHAI NỘP QUỸ > 0 THÌ LẤY NỘP QUỸ LÀM SỐ ĐÃ XÁC NHẬN.
 *
 * Anh Thắng 26/09/2026, ảnh tab Nhập báo cáo (Tutu Train - Aeon Bình Tân 25/09): cơ sở để trống
 * "Tiền mặt đếm trong két" nhưng khai "Tiền thực nộp về quỹ" = 4.540.000 kèm ghi chú *"Số tiền
 * lệch 240.000 do nhân viên chốt lệch visa"*. Máy POS ghi tiền mặt 4.780.000. *"Tiền mặt lệch và
 * nhân viên đã xác nhận 4540. nhưng bên đối soát vẫn lấy theo máy... tiền treo vẫn đang cộng theo
 * 4780 chứ không phải xác nhận 4540"*.
 *
 * 🔴 TRƯỚC BẢN NÀY: `phai_nop = $co ? $dem : $tm` chỉ nhìn "Đếm két" — để trống (0) thì "Phải nộp"
 *    tụt về 0, và "Lệch" (0 − 4.780.000 = −4.780.000) bằng NGUYÊN CẢ CỤC tiền mặt POS. Một khoản
 *    lệch nhỏ 240.000 đã được cơ sở giải trình lại HIỆN RA như mất cả 4,78 triệu.
 *
 * Anh Thắng chọn: đếm két=0 mà nộp quỹ>0 thì LẤY NỘP QUỸ làm số đã xác nhận (không phải giữ
 * nguyên hành vi cũ, không phải chỉ đổi cách hiển thị).
 *
 * Chạy: php tools/test/kiem-dem-ket-tu-nop.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt'; }
}
if ( ! function_exists( 'khh_dt_json' ) ) {
	function khh_dt_json( $raw, $mac_dinh ) { $v = json_decode( (string) $raw, true ); return is_array( $v ) ? $v : $mac_dinh; }
}
if ( ! function_exists( 'khh_dt_so' ) ) {
	function khh_dt_so( $s ) { $s = preg_replace( '/[^\d\-\.]/', '', (string) $s ); return '' === $s ? 0 : (float) $s; }
}
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_quan_tri' ) ) { function khh_dt_duoc_quan_tri() { return true; } }
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require_once $goc . '/sao-ke.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
foreach ( array( khh_dt_bang(), khh_dt_bang_bc(), khh_dt_bang_sk() ) as $b ) {
	$wpdb->exec_raw( "DROP TABLE IF EXISTS $b" );
}
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0,
	chiet_khau REAL DEFAULT 0, so_hd INTEGER DEFAULT 0, so_mon REAL DEFAULT 0, so_ve REAL DEFAULT 0,
	pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, ck_thuc_thu REAL DEFAULT 0,
	tien_nop REAL DEFAULT 0, so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0,
	tong_khach INTEGER DEFAULT 0, ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL,
	nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0, chot INTEGER DEFAULT 0, lich_su TEXT DEFAULT '[]',
	sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_sk() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ma_gd TEXT DEFAULT '', ngay TEXT NOT NULL, gio INTEGER DEFAULT 0, ngay_tinh TEXT NOT NULL,
	so_tien REAL DEFAULT 0, noi_dung TEXT DEFAULT '', tai_khoan TEXT DEFAULT '', cua_hang TEXT DEFAULT '' )" );

$CS = 'Tutu Train - Aeon Bình Tân';
$wpdb->query( $wpdb->prepare(
	'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-25', $CS, 5780000, 70, 86,
	wp_json_encode( array( array( 'n' => 'Tiền mặt', 'r' => 4780000 ), array( 'n' => 'Chuyển khoản', 'r' => 1000000 ) ) ),
	'[]'
) );

/* Đúng ca thật: đếm két để TRỐNG (gửi '' -> khh_dt_so('') = 0), chỉ khai Nộp quỹ. */
khh_dt_rest_bc_luu( new WP_REST_Request( array(
	'ngay' => '2026-09-25', 'cua_hang' => $CS, 'tien_mat_dem' => '', 'tien_nop' => '4540000',
	'ghi_chu' => 'Số tiền lệch 240.000 do nhân viên chốt lệch visa', 'chot' => '1',
) ) );

$r  = khh_dt_rest_doi_soat( new WP_REST_Request( array( 'tu' => '2026-09-25', 'den' => '2026-09-25', 'cua_hang' => '*' ) ) );
$ds = isset( $r['dong'] ) ? $r['dong'] : array();
phep( 'có đúng 1 dòng', 1 === count( $ds ) );
$x = $ds ? $ds[0] : array();

phep( '🔴 Đếm két hiện GIỮ NGUYÊN 0 (đúng số cơ sở đã gõ, không bịa)', isset( $x['dem'] ) && 0.0 === (float) $x['dem'] );
phep( '🔴 cờ dem_tu_nop bật lên — màn biết mà chú thích', ! empty( $x['dem_tu_nop'] ) );
phep( '🔴 Phải nộp = 4.540.000 (theo Nộp quỹ), KHÔNG PHẢI 0', isset( $x['phai_nop'] ) && 4540000.0 === (float) $x['phai_nop'] );
phep( '🔴 Lệch tiền mặt = −240.000 (đúng khoản lệch cơ sở đã giải trình), KHÔNG PHẢI −4.780.000',
	isset( $x['lech_tm'] ) && -240000.0 === (float) $x['lech_tm'] );

/* Đối chứng: đếm két=0 và KHÔNG khai nộp quỹ (nop=0 luôn) thì vẫn giữ hành vi CŨ — phải nộp=0,
   không tự suy ra một số nào khác. Chỉ bù khi có nộp quỹ thật, không phải mọi lúc đếm két rỗng. */
$wpdb->query( $wpdb->prepare(
	'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-24', $CS, 2000000, 10, 10, wp_json_encode( array( array( 'n' => 'Tiền mặt', 'r' => 2000000 ) ) ), '[]'
) );
khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => '2026-09-24', 'cua_hang' => $CS, 'tien_mat_dem' => '', 'chot' => '1' ) ) );
$r2 = khh_dt_rest_doi_soat( new WP_REST_Request( array( 'tu' => '2026-09-24', 'den' => '2026-09-24', 'cua_hang' => '*' ) ) );
$x2 = ( isset( $r2['dong'] ) && $r2['dong'] ) ? $r2['dong'][0] : array();
phep( '🔴 đếm két=0 VÀ nộp quỹ cũng=0 thì KHÔNG bù — phải nộp vẫn 0 như hành vi cũ',
	isset( $x2['phai_nop'] ) && 0.0 === (float) $x2['phai_nop'] && empty( $x2['dem_tu_nop'] ) );

/* Đối chứng 2: đếm két đã khai một số THẬT (khác 0) — dù nộp quỹ khai một số khác, đếm két vẫn
   phải thắng. Chỉ bù khi đếm két RỖNG, không phải mỗi khi hai số lệch nhau. */
$wpdb->query( $wpdb->prepare(
	'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-23', $CS, 3000000, 15, 15, wp_json_encode( array( array( 'n' => 'Tiền mặt', 'r' => 3000000 ) ) ), '[]'
) );
khh_dt_rest_bc_luu( new WP_REST_Request( array(
	'ngay' => '2026-09-23', 'cua_hang' => $CS, 'tien_mat_dem' => '2900000', 'tien_nop' => '2500000', 'chot' => '1',
) ) );
$r3 = khh_dt_rest_doi_soat( new WP_REST_Request( array( 'tu' => '2026-09-23', 'den' => '2026-09-23', 'cua_hang' => '*' ) ) );
$x3 = ( isset( $r3['dong'] ) && $r3['dong'] ) ? $r3['dong'][0] : array();
phep( '🔴 đếm két đã khai (2.900.000) THẮNG nộp quỹ (2.500.000) — không bù khi đếm két không rỗng',
	isset( $x3['phai_nop'] ) && 2900000.0 === (float) $x3['phai_nop'] && empty( $x3['dem_tu_nop'] ) );

echo "\n" . ( $hong ? "ĐỎ " . count( $hong ) . "/" . ( $dat + count( $hong ) ) . " phép:\n" . implode( "\n", array_map( function ( $t ) { return "  ✗ $t"; }, $hong ) ) . "\n" : "✓ SẠCH — $dat phép: đếm két rỗng mà nộp quỹ đã khai thì lấy nộp quỹ, không bịa cột Đếm két.\n" );
exit( $hong ? 1 : 0 );
