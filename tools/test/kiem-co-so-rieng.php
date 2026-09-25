<?php
/**
 * CƠ SỞ GÁN RIÊNG CHO TỪNG NGƯỜI — ĐÈ BẢNG GHÉP THEO MÃ — CHẠY THẬT.
 *
 * Anh Thắng 25/09/2026, ảnh bảng Ghép cơ sở của FZ_ADV_TP (Trí 3 cơ sở, Thảo) và ảnh ô chọn cơ sở của một cửa hàng
 * trưởng thấy 5 quán: *"tách riêng nhân viên, nó gộp dẫn đến nhân viên chung cơ sở"*.
 *
 * Chốt:
 *   1. 🔴 Hai người cùng mã: chưa gán riêng thì cùng cụm quán theo bảng ghép; gán riêng cho một người thì người ấy
 *      thấy ĐÚNG quán đã tích, người kia vẫn theo mã.
 *   2. Bỏ gán (danh sách rỗng) -> về theo mã. Tên lưu nguyên văn tên POS (gõ lệch dấu cách vẫn trúng).
 *   3. Vai duyệt vẫn xem tổng dù có gán riêng. Đẩy lại từ Nhân sự không xoá gán riêng; gỡ người thì xoá.
 *   4. REST nguoi-vai: có `co_so` thì gán, không gửi thì giữ; JSON hỏng -> 400. ds_nguoi_pin trả coso_rieng/hieu_luc.
 *
 * Chạy: php tools/test/kiem-co-so-rieng.php
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
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_quan_tri' ) ) { function khh_dt_duoc_quan_tri() { return ! empty( $GLOBALS['VHCP_CO_QUYEN'] ); } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return isset( $GLOBALS['VHCP_META'][ $k ] ) ? $GLOBALS['VHCP_META'][ $k ] : ''; } }
/* bao-cao-ngay.php nạp TRƯỚC bộ đồ: hàm khh_dt_ds_cua_hang() thật (đọc bảng POS) thay cho bản giả của bộ đồ. */
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require __DIR__ . '/fw/bo-do-khh-dt.php';
$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

khh_dt_test_dung_bang();
global $wpdb;
delete_option( 'khh_dt_nguoi_coso' );
delete_option( 'khh_dt_ghep_coso' );
/* Kho POS có ba quán, một quán mang hai dấu cách như FABi thật. */
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$FZ = 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )';
$CF = 'COFFE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )';
$ES = 'Tutu Train -  Estella ( Dịch vụ K&H )';
foreach ( array( $FZ, $CF, $ES ) as $c ) {
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu) VALUES (%s,%s,%f)', '2026-09-24', $c, 1000 ) );
}

khh_dt_day_vao( array( 'ma_nv' => 'NV171', 'ho_ten' => 'Nguyễn Công Trí', 'pin' => '1711', 'coso' => 'FZ_ADV_TP' ) );
khh_dt_day_vao( array( 'ma_nv' => 'NV173', 'ho_ten' => 'Huỳnh Thị Thảo', 'pin' => '1733', 'coso' => 'FZ_ADV_TP' ) );
khh_dt_dat_vai( 'NV171', 'nhap' );
khh_dt_dat_vai( 'NV173', 'nhap' );
khh_dt_dat_ghep( array( 'FZ_ADV_TP' => array( $FZ, $CF, $ES ) ) );

$vao = function ( $pin ) {
	$dn = khh_dt_pin_dang_nhap( $pin );
	khh_dt_test_dat_the( $dn['token'] );
	khh_dt_test_quen_phien();
};
$vao( '1733' );
phep( 'chưa gán riêng: Thảo thấy cả 3 quán của mã (gộp)', array( $FZ, $CF, $ES ) === khh_dt_phien_co_so_ds() );

/* gán riêng cho Thảo: chỉ Estella — gõ tên một dấu cách */
$kq = khh_dt_nguoi_coso_dat( 'nv173', array( 'Tutu Train - Estella ( Dịch vụ K&H )' ) );
phep( '🔴 gán riêng lưu NGUYÊN VĂN tên POS (hai dấu cách) dù gõ một dấu cách', array( $ES ) === $kq && array( $ES ) === khh_dt_nguoi_coso_rieng( 'NV173' ) );
khh_dt_test_quen_phien();
phep( '🔴 Thảo giờ chỉ thấy Estella', array( $ES ) === khh_dt_phien_co_so_ds() );
phep( 'và được ghi báo cáo Estella, không được Gò An Lạc', khh_dt_duoc_cua_hang( $ES ) && ! khh_dt_duoc_cua_hang( $FZ ) );
$vao( '1711' );
phep( '🔴 Trí cùng mã vẫn theo bảng ghép: 3 quán', array( $FZ, $CF, $ES ) === khh_dt_phien_co_so_ds() );

/* ds_nguoi_pin + rest_ghep bày đúng */
$pin = array();
foreach ( khh_dt_ds_nguoi_pin() as $x ) { $pin[ $x['ma_nv'] ] = $x; }
phep( 'ds_nguoi_pin: Thảo coso_rieng=[Estella], hiệu lực=[Estella]; Trí rỗng, hiệu lực = 3 quán theo mã', array( $ES ) === $pin['NV173']['coso_rieng'] && array( $ES ) === $pin['NV173']['coso_hieu_luc'] && array() === $pin['NV171']['coso_rieng'] && 3 === count( $pin['NV171']['coso_hieu_luc'] ) );
$GLOBALS['VHCP_CO_QUYEN'] = true;
$g = khh_dt_rest_ghep();
$ng = array();
foreach ( $g['nguoi'] as $x ) { $ng[ $x['ma_nv'] ] = $x; }
phep( 'rest_ghep: người mang coso_rieng để bảng Ghép nói "gán riêng"', array( $ES ) === $ng['NV173']['coso_rieng'] );

/* bỏ gán -> về theo mã */
khh_dt_nguoi_coso_dat( 'NV173', array() );
$vao( '1733' );
phep( 'bỏ gán riêng (danh sách rỗng) -> Thảo lại theo mã, 3 quán; option không còn khoá', array( $FZ, $CF, $ES ) === khh_dt_phien_co_so_ds() && ! isset( khh_dt_nguoi_coso_bang()['NV173'] ) );

/* vai duyệt xem tổng dù có gán riêng */
khh_dt_nguoi_coso_dat( 'NV173', array( $ES ) );
khh_dt_dat_vai( 'NV173', 'duyet' );
khh_dt_test_quen_phien();
phep( 'vai duyệt vẫn xem tổng (rỗng = mọi cơ sở) dù có gán riêng', array() === khh_dt_phien_co_so_ds() );
khh_dt_dat_vai( 'NV173', 'nhap' );
khh_dt_test_quen_phien();
phep( 'về vai nhập -> lại chỉ Estella', array( $ES ) === khh_dt_phien_co_so_ds() );

/* đẩy lại từ Nhân sự không xoá gán riêng; gỡ người thì xoá */
khh_dt_day_vao( array( 'ma_nv' => 'NV173', 'ho_ten' => 'Huỳnh Thị Thảo', 'pin' => '1733', 'coso' => 'FZ_ADV_TP, TUTU_TP' ) );
khh_dt_test_quen_phien();
phep( '🔴 đẩy lại (đổi mã cơ sở) KHÔNG xoá gán riêng', array( $ES ) === khh_dt_nguoi_coso_rieng( 'NV173' ) && array( $ES ) === khh_dt_phien_co_so_ds() );
khh_dt_day_ra( 'NV173' );
phep( 'gỡ người -> gán riêng cũng xoá', array() === khh_dt_nguoi_coso_rieng( 'NV173' ) );
khh_dt_day_vao( array( 'ma_nv' => 'NV173', 'ho_ten' => 'Huỳnh Thị Thảo', 'pin' => '1733', 'coso' => 'FZ_ADV_TP' ) );
khh_dt_dat_vai( 'NV173', 'nhap' );

/* tên lạ (quán chưa có số liệu) vẫn giữ (đã rửa); tên rỗng bỏ; trùng bỏ */
$kq = khh_dt_nguoi_coso_dat( 'NV173', array( ' ', $FZ, $FZ, 'Quán mới <b>x</b>' ) );
phep( 'rửa danh sách: bỏ rỗng, bỏ trùng, tên lạ giữ sau khi rửa thẻ', array( $FZ, 'Quán mới x' ) === $kq );

/* REST nguoi-vai */
$r = khh_dt_rest_dat_vai( new WP_REST_Request( array( 'ma_nv' => 'NV173', 'vai' => 'nhap', 'co_so' => wp_json_encode( array( $CF ) ) ) ) );
phep( 'REST: vai + co_so JSON -> gán riêng, trả coso_rieng', ! is_wp_error( $r ) && array( $CF ) === $r['coso_rieng'] && array( $CF ) === khh_dt_nguoi_coso_rieng( 'NV173' ) );
$r = khh_dt_rest_dat_vai( new WP_REST_Request( array( 'ma_nv' => 'NV173', 'vai' => 'nhap' ) ) );
phep( 'REST: không gửi co_so -> giữ nguyên gán riêng', ! is_wp_error( $r ) && ! isset( $r['coso_rieng'] ) && array( $CF ) === khh_dt_nguoi_coso_rieng( 'NV173' ) );
$r = khh_dt_rest_dat_vai( new WP_REST_Request( array( 'ma_nv' => 'NV173', 'vai' => 'nhap', 'co_so' => '[]' ) ) );
phep( 'REST: co_so=[] -> bỏ gán riêng', ! is_wp_error( $r ) && array() === khh_dt_nguoi_coso_rieng( 'NV173' ) );
$r = khh_dt_rest_dat_vai( new WP_REST_Request( array( 'ma_nv' => 'NV173', 'vai' => 'nhap', 'co_so' => '{hỏng' ) ) );
phep( 'REST: co_so JSON hỏng -> 400', is_wp_error( $r ) );

echo $hong ? '✗ HỎNG ' . count( $hong ) . ' / ' . ( $dat + count( $hong ) ) . " phép:\n  · " . implode( "\n  · ", $hong ) . "\n"
	: '✓ SẠCH — ' . $dat . " phép: cơ sở gán riêng từng người đè bảng ghép theo mã.\n";
exit( $hong ? 1 : 0 );
