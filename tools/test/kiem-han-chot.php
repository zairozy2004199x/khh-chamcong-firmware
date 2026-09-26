<?php
/**
 * QUÁ HẠN CHỐT — KHOÁ RIÊNG NÚT "CHỐT", KHÔNG KHOÁ VIỆC LƯU/SỬA SỐ.
 *
 * Anh Thắng 26/09/2026: *"Cho lưu và chốt nhiều lần, tới 8h sáng ngày hôm nó sẽ khóa, không cho
 * chốt nữa"*. Đọc ra hai vế: (1) lưu/chốt lại thoải mái, không giới hạn số lần TRƯỚC hạn — vốn đã
 * tự do sẵn, không đụng; (2) QUA giờ hạn (`khh_dt_qt_han_ts()`, đã có sẵn từ tính năng "quy trình
 * báo cáo hằng ngày" 24/09/2026, mặc định 10:00 sáng hôm sau, chỉnh được ở màn Quy trình) mà ngày
 * đó CHƯA TỪNG chốt thì không cho CHỐT MỚI nữa — chặn "chốt trễ" sau khi văn phòng đã tổng hợp.
 *
 * Bốn chốt bài này canh:
 *   1. 🔴 Trước hạn: chốt được, và CHỐT LẠI nhiều lần vẫn được (không giới hạn số lần).
 *   2. 🔴 Qua hạn, ngày CHƯA từng chốt -> chốt (chot=1) bị máy chủ CHỐI, nêu rõ giờ hạn đã qua.
 *      Nhưng LƯU (không tích nút Chốt) vẫn bình thường — chỉ khoá riêng hành động CHỐT, không
 *      khoá cả form như "Đã nộp tiền".
 *   3. 🔴 Ngày ĐÃ chốt trước hạn: qua hạn rồi, sửa số rồi bấm "Lưu báo cáo" (không tích Chốt) vẫn
 *      lưu được VÀ cờ chốt KHÔNG bị bỏ (bài học: trước bản này `chot` viết thẳng theo tham số gửi
 *      lên, "Lưu báo cáo" thường sau khi đã chốt sẽ âm thầm bỏ chốt).
 *   4. Ngày đã chốt, qua hạn, bấm "Chốt" lại lần nữa (chot=1) vẫn được — không phải "chốt mới".
 *
 * Chạy: php tools/test/kiem-han-chot.php
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
if ( ! function_exists( 'khh_dt_duoc_nap' ) ) { function khh_dt_duoc_nap() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_quan_tri' ) ) { function khh_dt_duoc_quan_tri() { return true; } }
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require_once $goc . '/quy-trinh.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
update_option( KHH_DT_QT_OPT, array( 'han' => '08:00' ) );   // đúng giờ anh Thắng chốt: "8h sáng"
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_bc() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, ck_thuc_thu REAL DEFAULT 0, tien_nop REAL DEFAULT 0,
	so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0, tong_khach INTEGER DEFAULT 0,
	ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, da_nop INTEGER DEFAULT 0, da_nop_boi TEXT DEFAULT '', da_nop_luc TEXT NULL,
	lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );
if ( ! function_exists( 'khh_dt_so_pos' ) ) { function khh_dt_so_pos( $ngay, $ch ) { return null; } }

$NGAY = '2026-09-25'; $CS = 'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )';
$GLOBALS['VHCP_DANG_NHAP_WP'] = true;
// Hạn của ngày 2026-09-25 = 08:00 ngày 2026-09-26 (giờ VN, UTC+7) = 01:00 UTC 2026-09-26.
$TRUOC_HAN = '2026-09-26 00:30:00 UTC';
$QUA_HAN   = '2026-09-26 02:00:00 UTC';

echo "── Ngày cũ chưa từng chốt ──\n";
vhcp_test_dat_gio( $TRUOC_HAN );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '100000' ) ) );
phep( 'lưu (chưa chốt) trước hạn thành công', ! is_wp_error( $r ) && 0 === (int) $r['bao_cao']['chot'] );

$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '100000', 'chot' => '1' ) ) );
phep( '🔴 chốt trước hạn thành công', ! is_wp_error( $r ) && 1 === (int) $r['bao_cao']['chot'] );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '150000', 'chot' => '1' ) ) );
phep( '🔴 CHỐT LẠI (lần hai) trước hạn vẫn được, không giới hạn số lần', ! is_wp_error( $r ) && 1 === (int) $r['bao_cao']['chot'] && 150000 === (int) $r['bao_cao']['tien_mat_dem'] );

echo "\n── Qua hạn, ngày KHÁC chưa từng chốt ──\n";
$NGAY2 = '2026-09-25'; $CS2 = 'Estella';
vhcp_test_dat_gio( $QUA_HAN );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY2, 'cua_hang' => $CS2, 'tien_mat_dem' => '200000' ) ) );
phep( 'qua hạn, LƯU (không chốt) vẫn bình thường — không khoá cả form', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY2, 'cua_hang' => $CS2, 'tien_mat_dem' => '200000', 'chot' => '1' ) ) );
phep( '🔴 qua hạn, CHỐT MỚI bị chối', is_wp_error( $r ) );
phep( 'lý do nêu rõ đã quá hạn chốt', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'quá hạn chốt' ) );
$sau = $wpdb->get_row( $wpdb->prepare( 'SELECT chot, tien_mat_dem FROM ' . khh_dt_bang_bc() . ' WHERE ngay=%s AND cua_hang=%s', $NGAY2, $CS2 ), ARRAY_A );
phep( 'lần chốt bị chối không đổi gì trong bảng (vẫn chưa chốt)', 0 === (int) $sau['chot'] );

echo "\n── Ngày ĐÃ chốt trước hạn, rồi qua hạn ──\n";
vhcp_test_dat_gio( $TRUOC_HAN );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY2, 'cua_hang' => $CS2, 'tien_mat_dem' => '300000', 'chot' => '1' ) ) );
phep( 'chốt trước hạn thành công (chuẩn bị bài dưới)', ! is_wp_error( $r ) && 1 === (int) $r['bao_cao']['chot'] );
vhcp_test_dat_gio( $QUA_HAN );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY2, 'cua_hang' => $CS2, 'tien_mat_dem' => '333000' ) ) );
phep( '🔴 đã chốt rồi, qua hạn, sửa số + Lưu (không tích Chốt) vẫn lưu được', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
phep( '🔴 cờ chốt KHÔNG bị âm thầm bỏ (vẫn 1, không phải bài học cũ tái diễn)', ! is_wp_error( $r ) && 1 === (int) $r['bao_cao']['chot'] );
phep( 'số tiền vẫn đổi đúng theo lần lưu (333000)', ! is_wp_error( $r ) && 333000 === (int) $r['bao_cao']['tien_mat_dem'] );

$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY2, 'cua_hang' => $CS2, 'tien_mat_dem' => '333000', 'chot' => '1' ) ) );
phep( '🔴 ngày ĐÃ chốt, qua hạn, bấm Chốt lại lần nữa KHÔNG bị chối (không phải "chốt mới")', ! is_wp_error( $r ) && 1 === (int) $r['bao_cao']['chot'] );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: chốt/lưu lại thoải mái trước hạn, qua hạn chặn chốt mới nhưng không khoá cả form, không âm thầm bỏ chốt khi lưu lại.\n";
