<?php
/**
 * LỆCH CHUYỂN KHOẢN — RIÊNG SỔ, KHÔNG ĐƯỢC GỘP VỚI LỆCH TIỀN MẶT.
 *
 * Anh Thắng 26/09/2026: *"nhiều khi lệch ngược giữa chuyển khoản và tiền mặt… kiểu nhân viên cho
 * khách bấm vé chuyển khoản nhưng khách đưa tiền mặt hoặc ngược lại, nên cần nhân viên nhập số
 * thực"*.
 *
 * Máy POS ghi hình thức thanh toán theo NÚT nhân viên bấm lúc bán, không theo tiền thật sự cầm
 * trên tay. Bấm nhầm nút thì `tien_mat (POS)` và `ck (POS)` sai NGƯỢC CHIỀU nhau — thiếu bên này
 * đúng bằng thừa bên kia. Bên tiền mặt đã có ô "Tiền mặt đếm trong két" (`tien_mat_dem` ->
 * `lech_tm`); bài này khoá phần mới thêm cho chuyển khoản: `ck_thuc_thu` -> `lech_ck`.
 *
 * 🔴 PHẢI LÀ CỘT RIÊNG. Test chính: đếm két đúng máy (lệch_tm = 0) NHƯNG chuyển khoản thực thu
 *    lệch hẳn với máy (lệch_ck ≠ 0) — nếu ai đó lỡ tay gộp hai cột lại (cộng `lech_tm + lech_ck`
 *    rồi chỉ trả một số), bài dưới bắt được ngay vì tổng gộp sẽ không khớp `lech_ck` mong đợi.
 *
 * Chạy: php tools/test/kiem-lech-chuyen-khoan.php
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
/* Bảng sao kê ngân hàng — rỗng trong bài này, chỉ cần TỒN TẠI để khh_dt_nop_bank() không lỗi. */
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_sk() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ma_gd TEXT DEFAULT '', ngay TEXT NOT NULL, gio INTEGER DEFAULT 0, ngay_tinh TEXT NOT NULL,
	so_tien REAL DEFAULT 0, noi_dung TEXT DEFAULT '', tai_khoan TEXT DEFAULT '', cua_hang TEXT DEFAULT '' )" );

$CS = 'TuTu Train - Aeon Tân Phú';
/* Ngày POS: 6.000.000đ, hai hình thức PTTT — tiền mặt 4.000.000 · chuyển khoản 2.000.000. */
$wpdb->query( $wpdb->prepare(
	'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-25', $CS, 6000000, 40, 40,
	wp_json_encode( array( array( 'n' => 'Tiền mặt', 'r' => 4000000 ), array( 'n' => 'Chuyển khoản', 'r' => 2000000 ) ) ),
	'[]'
) );

/* Cơ sở khai: đếm két KHỚP máy (4.000.000, lệch_tm = 0) NHƯNG chuyển khoản thực thu lệch hẳn
   (2.700.000 so với máy 2.000.000, tức khách đưa thêm 700.000 tiền mặt mà nhân viên lỡ bấm nút
   chuyển khoản) — case đúng như anh Thắng mô tả: một bên khớp, một bên lệch, KHÔNG PHẢI cả hai
   cùng lệch một lượng như nhau. */
khh_dt_rest_bc_luu( new WP_REST_Request( array(
	'ngay' => '2026-09-25', 'cua_hang' => $CS, 'tien_mat_dem' => '4000000', 'ck_thuc_thu' => '2700000',
) ) );

$r  = khh_dt_rest_doi_soat( new WP_REST_Request( array( 'tu' => '2026-09-25', 'den' => '2026-09-25', 'cua_hang' => '*' ) ) );
$ds = isset( $r['dong'] ) ? $r['dong'] : array();
phep( 'có đúng 1 dòng', 1 === count( $ds ) );
$x = $ds ? $ds[0] : array();

phep( '🔴 lệch tiền mặt = 0 (đếm két đúng khớp máy)', isset( $x['lech_tm'] ) && 0.0 === (float) $x['lech_tm'] );
phep( '🔴 lệch chuyển khoản = +700.000, KHÔNG PHẢI 0 — không được lây khớp từ cột tiền mặt',
	isset( $x['lech_ck'] ) && 700000.0 === (float) $x['lech_ck'] );
phep( 'ck_tt trả nguyên số cơ sở khai (để màn hiện kèm)', isset( $x['ck_tt'] ) && 2700000.0 === (float) $x['ck_tt'] );
phep( 'pos_ck vẫn đúng số máy ghi (không bị số khai đè)', isset( $x['pos_ck'] ) && 2000000.0 === (float) $x['pos_ck'] );

/* 🔴 CHƯA NHẬP BÁO CÁO THÌ lech_ck PHẢI LÀ null, không phải 0 — '0' nghĩa là "đã khai, khớp máy",
   còn "chưa ai khai gì" là một câu trả lời khác hẳn (giống hệt luật đã có của lech_tm). */
$wpdb->query( $wpdb->prepare(
	'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-26', $CS, 1000000, 5, 5, wp_json_encode( array( array( 'n' => 'Tiền mặt', 'r' => 1000000 ) ) ), '[]'
) );
$r2  = khh_dt_rest_doi_soat( new WP_REST_Request( array( 'tu' => '2026-09-26', 'den' => '2026-09-26', 'cua_hang' => '*' ) ) );
$ds2 = isset( $r2['dong'] ) ? $r2['dong'] : array();
$x2  = $ds2 ? $ds2[0] : array();
phep( '🔴 ngày chưa nhập báo cáo: lech_ck là null, không phải 0',
	array_key_exists( 'lech_ck', $x2 ) && null === $x2['lech_ck'] );
phep( 'và co_bao_cao = false đúng như trước nay', empty( $x2['co_bao_cao'] ) );

echo "\n" . ( $hong ? "ĐỎ " . count( $hong ) . "/" . ( $dat + count( $hong ) ) . " phép:\n" . implode( "\n", array_map( function ( $t ) { return "  ✗ $t"; }, $hong ) ) . "\n" : "✓ SẠCH — $dat phép: chuyển khoản thực thu lệch riêng sổ, không lây khớp/lệch từ tiền mặt.\n" );
exit( $hong ? 1 : 0 );
