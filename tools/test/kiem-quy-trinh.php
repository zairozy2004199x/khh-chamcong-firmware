<?php
/**
 * QUY TRÌNH BÁO CÁO CƠ SỞ HẰNG NGÀY — CHẠY THẬT.
 *
 * Anh Thắng 24/09/2026: *"Làm quy trình báo cáo hằng ngày tự động"* — *"Báo cáo cơ sở thôi"*.
 *
 * Chốt:
 *   1. 🔴 Bốn trạng thái: chưa có số máy / chưa nộp / đã lưu / đã chốt — suy từ kho POS + bảng báo cáo.
 *   2. 🔴 Hạn = giờ `han` của NGÀY KẾ TIẾP theo múi giờ site; qua hạn mà chưa chốt thì `qua_han`.
 *   3. Danh sách việc: chỉ ngày chưa chốt, ngày cũ trước; ngày cũ không số máy không ai nhập thì bỏ,
 *      riêng hôm qua chưa có số máy vẫn kể; lọc đúng cơ sở người xem.
 *   4. Tổng hợp một ngày đếm đúng từng trạng thái; thư nêu tên quán chưa nộp, két lệch, món lệch.
 *   5. 🔴 Lượt chạy: ghi nhật ký, gửi thư ĐÚNG địa chỉ hợp lệ; không địa chỉ thì không gửi mà vẫn ghi.
 *   6. Lịch: bật -> đặt mốc 'daily' đúng giờ hạn; tắt -> gỡ. REST: cấu hình chỉ quản trị thấy; email sai báo 400.
 *   7. 🔴 KHÔNG tự điền số cho cơ sở: sau lượt chạy bảng báo cáo không thêm dòng nào.
 *
 * Chạy: php tools/test/kiem-quy-trinh.php
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
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return isset( $GLOBALS['VHCP_META'][ $k ] ) ? $GLOBALS['VHCP_META'][ $k ] : ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require_once $goc . '/kho.php';
require_once $goc . '/quy-trinh.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang() );
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_bc() );
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_kho() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0,
	chiet_khau REAL DEFAULT 0, so_hd INTEGER DEFAULT 0, so_mon REAL DEFAULT 0, so_ve REAL DEFAULT 0,
	pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, ck_thuc_thu REAL DEFAULT 0, tien_nop REAL DEFAULT 0,
	so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0, tong_khach INTEGER DEFAULT 0,
	ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_kho() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, co_so TEXT NOT NULL DEFAULT '', mat_hang TEXT NOT NULL DEFAULT '', nhap REAL DEFAULT 0,
	ban_khai REAL NULL, combo_tay REAL DEFAULT 0, dem REAL NULL, dat_dau REAL NULL, huy REAL DEFAULT 0,
	ghi_chu TEXT DEFAULT '', nguoi TEXT DEFAULT '', luc INTEGER DEFAULT 0, UNIQUE(ngay,co_so,mat_hang) )" );
delete_option( KHH_DT_QT_OPT );
delete_option( KHH_DT_QT_NHAT );
$GLOBALS['VHCP_LICH'] = array();
$GLOBALS['VHCP_MAIL'] = array();

$TP = 'TuTu Train - Aeon Tân Phú';
$GV = 'TuTu Train - Lotte Gò Vấp';
$VT = 'Funzone City Vũng Tàu';
$MON = array(
	array( 'n' => 'VÉ COMBO.', 'g' => 'Vé',      'q' => 36, 'r' => 2880000 ),
	array( 'n' => 'NƯỚC SUỐI', 'g' => 'Đồ uống', 'q' => 4,  'r' => 40000 ),
);
function pos_ngay( $ngay, $cs, $tien_mat = 1000000 ) {
	global $wpdb, $MON;
	$wpdb->query( $wpdb->prepare( 'INSERT OR REPLACE INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
		$ngay, $cs, 2920000, 40, 36, wp_json_encode( array( array( 'n' => 'Tiền mặt', 'r' => $tien_mat ), array( 'n' => 'Chuyển khoản', 'r' => 2920000 - $tien_mat ) ) ), wp_json_encode( $MON ) ) );
}
function bc_ngay( $ngay, $cs, $chot, $dem = 0, $mon_thuc = '{}' ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT OR REPLACE INTO ' . khh_dt_bang_bc() . ' (ngay,cua_hang,tien_mat_dem,mon_thuc,nguoi,chot,sua_luc) VALUES (%s,%s,%f,%s,%s,%d,%s)',
		$ngay, $cs, $dem, $mon_thuc, 'Thảo', $chot, $ngay . ' 21:30:00' ) );
}
/* "Bây giờ" = 24/09/2026 09:00 giờ Việt Nam -> hôm qua là 23/09. */
$tz  = new DateTimeZone( 'Asia/Ho_Chi_Minh' );
$BAY = ( new DateTime( '2026-09-24 09:00:00', $tz ) )->getTimestamp();
$SAU = ( new DateTime( '2026-09-24 10:30:00', $tz ) )->getTimestamp();

/* ── 2. hạn ── */
phep( 'hôm nay theo giờ site là 24/09', '2026-09-24' === khh_dt_qt_hom_nay( $BAY ) );
phep( '🔴 hạn của ngày 23/09 = 24/09 10:00 giờ Việt Nam', ( new DateTime( '2026-09-24 10:00:00', $tz ) )->getTimestamp() === khh_dt_qt_han_ts( '2026-09-23', '10:00' ) );
phep( 'giờ hạn hỏng thì lùi về 10:00', khh_dt_qt_han_ts( '2026-09-23', 'xx' ) === khh_dt_qt_han_ts( '2026-09-23', '10:00' ) );
/* Ca UTC: máy chủ mới cài để múi giờ UTC — hạn vẫn tính theo giờ SITE (ở đây là UTC), không theo hằng cứng. */
update_option( 'timezone_string', 'UTC' );
phep( 'múi giờ site đổi thì mốc hạn đổi theo', khh_dt_qt_han_ts( '2026-09-23', '10:00' ) === ( new DateTime( '2026-09-24 10:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp() );
update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );

/* ── 1. bốn trạng thái ── */
pos_ngay( '2026-09-23', $TP );
pos_ngay( '2026-09-23', $GV, 800000 );
pos_ngay( '2026-09-22', $TP );
pos_ngay( '2026-09-22', $GV );
pos_ngay( '2026-09-22', $VT );
pos_ngay( '2026-09-20', $VT );
bc_ngay( '2026-09-23', $TP, 1, 1000000 );                                   // TP: chốt, két khớp
bc_ngay( '2026-09-23', $GV, 0, 850000, wp_json_encode( array( 'VÉ COMBO.' => 30 ) ) ); // GV: lưu chưa chốt, két +50k, 1 món lệch
bc_ngay( '2026-09-22', $TP, 1 );
bc_ngay( '2026-09-22', $VT, 0 );
$wpdb->query( "INSERT INTO " . khh_dt_bang_kho() . " (ngay,co_so,mat_hang,nhap) VALUES ('2026-09-23','$TP','Nước suối',10)" );

$t = khh_dt_qt_tinh_trang( '2026-09-23', $TP, $BAY );
phep( '🔴 TP 23/09: đã chốt, bốn bước đều xong', 'da_chot' === $t['trang_thai'] && $t['buoc']['fabi'] && $t['buoc']['khai'] && true === $t['buoc']['kho'] && $t['buoc']['chot'] );
phep( 'đã chốt thì không quá hạn, kể cả sau giờ hạn', ! khh_dt_qt_tinh_trang( '2026-09-23', $TP, $SAU )['qua_han'] );
phep( 'két khớp -> lech_ket = 0; không món lệch', 0.0 === $t['lech_ket'] && 0 === $t['mon_lech'] && 'Thảo' === $t['nguoi'] );
$t = khh_dt_qt_tinh_trang( '2026-09-23', $GV, $BAY );
phep( '🔴 GV 23/09: đã lưu chưa chốt; kho chưa lưu; két +50.000; 1 món lệch máy', 'da_luu' === $t['trang_thai'] && false === $t['buoc']['kho'] && 50000.0 === $t['lech_ket'] && 1 === $t['mon_lech'] );
phep( 'trước giờ hạn thì chưa quá hạn', ! $t['qua_han'] && '2026-09-24 10:00' === $t['han'] );
phep( '🔴 sau giờ hạn mà chưa chốt -> quá hạn', khh_dt_qt_tinh_trang( '2026-09-23', $GV, $SAU )['qua_han'] );
$t = khh_dt_qt_tinh_trang( '2026-09-23', $VT, $BAY );
phep( 'VT 23/09: chưa có số máy -> chua_fabi, doanh_thu null', 'chua_fabi' === $t['trang_thai'] && null === $t['doanh_thu'] && ! $t['buoc']['fabi'] );
$t = khh_dt_qt_tinh_trang( '2026-09-22', $GV, $BAY );
phep( 'GV 22/09: có số máy, không ai nhập -> chua_nop, két null (chưa đếm khác 0)', 'chua_nop' === $t['trang_thai'] && null === $t['lech_ket'] && '' === $t['nguoi'] );
/* Dòng báo cáo lưu với két = 0 (chưa đếm) không được ra "lệch −1.000.000". */
bc_ngay( '2026-09-20', $VT, 0, 0 );
phep( '🔴 két 0 = chưa đếm -> lech_ket null, không phải −tiền máy', null === khh_dt_qt_tinh_trang( '2026-09-20', $VT, $BAY )['lech_ket'] );

/* ── 3. danh sách việc ── */
$v = khh_dt_qt_viec( array(), $BAY, 7 );
$khoa = array_map( function ( $x ) { return $x['ngay'] . '|' . $x['cua_hang'] . '|' . $x['trang_thai']; }, $v );
phep( '🔴 việc: không có dòng đã chốt', ! in_array( '2026-09-23|' . $TP . '|da_chot', $khoa, true ) && ! in_array( '2026-09-22|' . $TP . '|da_chot', $khoa, true ) );
phep( 'GV 23/09 đã lưu chưa chốt -> có', in_array( '2026-09-23|' . $GV . '|da_luu', $khoa, true ) );
phep( 'GV 22/09 chưa nộp -> có; VT 22/09 đã lưu -> có', in_array( '2026-09-22|' . $GV . '|chua_nop', $khoa, true ) && in_array( '2026-09-22|' . $VT . '|da_luu', $khoa, true ) );
/* Anh Thắng 25/09/2026: "Ngày nào bấm nộp sẽ hiện xanh, chứ không phải ẩn" — lịch đủ kể cả ngày đã chốt. */
$l = khh_dt_qt_viec( array(), $BAY, 7, true );
$khoa_l = array_map( function ( $x ) { return $x['ngay'] . '|' . $x['cua_hang'] . '|' . $x['trang_thai']; }, $l );
phep( '🔴 ke_ca_chot: lịch có cả TP 23/09 và 22/09 đã chốt, và vẫn có mọi việc treo', in_array( '2026-09-23|' . $TP . '|da_chot', $khoa_l, true ) && in_array( '2026-09-22|' . $TP . '|da_chot', $khoa_l, true ) && ! array_diff( $khoa, $khoa_l ) && count( $l ) === count( $v ) + 2 );
phep( '🔴 hôm qua (23/09) VT chưa có số máy VẪN kể — việc của văn phòng', in_array( '2026-09-23|' . $VT . '|chua_fabi', $khoa, true ) );
phep( '🔴 ngày cũ (21/09) không số máy, không ai nhập -> KHÔNG kể', ! in_array( '2026-09-21|' . $TP . '|chua_fabi', $khoa, true ) && ! in_array( '2026-09-21|' . $VT . '|chua_fabi', $khoa, true ) );
phep( 'VT 20/09 đã lưu chưa chốt (trong 7 ngày) -> có', in_array( '2026-09-20|' . $VT . '|da_luu', $khoa, true ) );
phep( 'xếp ngày cũ trước', $v[0]['ngay'] <= $v[ count( $v ) - 1 ]['ngay'] && '2026-09-20' === $v[0]['ngay'] );
$v = khh_dt_qt_viec( array( $GV ), $BAY, 7 );
phep( '🔴 lọc theo cơ sở người xem: chỉ Gò Vấp', count( $v ) > 0 && ! array_filter( $v, function ( $x ) use ( $GV ) { return $x['cua_hang'] !== $GV; } ) );
$v = khh_dt_qt_viec( array( 'Quán chưa nạp file' ), $BAY, 7 );
phep( 'cơ sở chưa có trong kho POS vẫn ra dòng hôm qua "chưa có số máy"', 1 === count( $v ) && 'chua_fabi' === $v[0]['trang_thai'] && '2026-09-23' === $v[0]['ngay'] );
phep( 'lùi 1 ngày thì chỉ còn hôm qua', ! array_filter( khh_dt_qt_viec( array(), $BAY, 1 ), function ( $x ) { return '2026-09-23' !== $x['ngay']; } ) );

/* ── 4. tổng hợp + thư ── */
$th = khh_dt_qt_tong_hop( '2026-09-23', $BAY );
phep( '🔴 tổng hợp 23/09: 1 chốt, 1 lưu, 0 chưa nộp, 1 chưa số máy, tổng 3', 1 === $th['dem']['da_chot'] && 1 === $th['dem']['da_luu'] && 0 === $th['dem']['chua_nop'] && 1 === $th['dem']['chua_fabi'] && 3 === $th['tong'] );
$thu = khh_dt_qt_thu( $th );
phep( 'tiêu đề: "Báo cáo cơ sở 23/09/2026: 1/3 đã chốt — 2 chưa xong"', 'Báo cáo cơ sở 23/09/2026: 1/3 đã chốt — 2 chưa xong' === $thu['tieu_de'] );
phep( 'thư nêu Gò Vấp két lệch +50.000 và 1 món lệch máy', false !== strpos( $thu['noi_dung'], $GV . ' — máy 2.920.000 ₫ · két lệch +50.000 ₫ · 1 món lệch máy · Thảo' ) );
phep( 'thư nêu Vũng Tàu ở nhóm CHƯA CÓ SỐ MÁY POS', preg_match( '~CHƯA CÓ SỐ MÁY POS \(1\)\n  - ' . preg_quote( $VT, '~' ) . '~u', $thu['noi_dung'] ) === 1 );
$thu2 = khh_dt_qt_thu( khh_dt_qt_tong_hop( '2026-09-23', $SAU ) );
phep( 'sau giờ hạn thư ghi QUÁ HẠN 10:00 cạnh quán chưa chốt', false !== strpos( $thu2['noi_dung'], $GV . ' — ' ) && false !== strpos( $thu2['noi_dung'], 'QUÁ HẠN 10:00' ) );

/* ── 5. lượt chạy ── */
$so_bc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . khh_dt_bang_bc() );
$d = khh_dt_qt_chay( $BAY );
phep( 'chưa có địa chỉ -> không gửi, vẫn ghi nhật ký', false === $d['gui'] && array() === $d['toi'] && 1 === count( khh_dt_qt_nhat_ky() ) && '2026-09-23' === khh_dt_qt_nhat_ky()[0]['ngay'] );
phep( '🔴 KHÔNG tự điền số: bảng báo cáo không thêm dòng nào', $so_bc === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . khh_dt_bang_bc() ) );
update_option( KHH_DT_QT_OPT, array( 'email' => 'ketoan@example.test, sai-dia-chi, Ketoan@example.test; sep@example.test' ) );
phep( 'lọc địa chỉ: bỏ sai, bỏ trùng không phân biệt hoa thường', array( 'ketoan@example.test', 'sep@example.test' ) === khh_dt_qt_email_ds( khh_dt_qt_cf()['email'] ) );
$d = khh_dt_qt_chay( $BAY );
phep( '🔴 gửi đúng 2 địa chỉ, đúng tiêu đề', true === $d['gui'] && 1 === count( $GLOBALS['VHCP_MAIL'] ) && array( 'ketoan@example.test', 'sep@example.test' ) === $GLOBALS['VHCP_MAIL'][0]['to'] && $thu['tieu_de'] === $GLOBALS['VHCP_MAIL'][0]['subject'] );
phep( 'nhật ký mới nhất đứng đầu, tối đa giữ 30', 2 === count( khh_dt_qt_nhat_ky() ) && true === khh_dt_qt_nhat_ky()[0]['gui'] );
$GLOBALS['VHCP_MAIL_HONG'] = true;
$d = khh_dt_qt_chay( $BAY );
phep( 'wp_mail hỏng thì nhật ký nói ra', false === $d['gui'] && false !== strpos( $d['loi'], 'wp_mail' ) );
unset( $GLOBALS['VHCP_MAIL_HONG'] );

/* ── 6. lịch + REST ── */
update_option( KHH_DT_QT_OPT, array( 'bat' => true, 'han' => '10:00' ) );
khh_dt_qt_dat_lich();
phep( '🔴 bật -> đặt lịch daily', isset( $GLOBALS['VHCP_LICH'][ KHH_DT_QT_MOC ] ) && 'daily' === $GLOBALS['VHCP_LICH'][ KHH_DT_QT_MOC ]['nhip'] );
$ts = $GLOBALS['VHCP_LICH'][ KHH_DT_QT_MOC ]['ts'];
$dm = new DateTime( '@' . $ts ); $dm->setTimezone( $tz );
phep( 'mốc là 10:00 giờ Việt Nam, còn ở tương lai', '10:00' === $dm->format( 'H:i' ) && $ts > time() );
update_option( KHH_DT_QT_OPT, array( 'bat' => false ) );
khh_dt_qt_dat_lich();
phep( 'tắt -> gỡ lịch', ! isset( $GLOBALS['VHCP_LICH'][ KHH_DT_QT_MOC ] ) );

$GLOBALS['VHCP_CO_QUYEN'] = false;
$GLOBALS['VHCP_META'] = array( 'khh_dt_co_so' => $GV );
$r = khh_dt_rest_qt_xem();
phep( '🔴 cửa hàng trưởng: chỉ việc của quán mình, KHÔNG có cấu hình/nhật ký/tổng hợp', ! isset( $r['cf'] ) && ! isset( $r['nhat_ky'] ) && ! isset( $r['tong_hop'] ) && count( $r['viec'] ) > 0 && ! array_filter( $r['viec'], function ( $x ) use ( $GV ) { return $x['cua_hang'] !== $GV; } ) );
phep( 'kèm giờ hạn để màn nói "chốt trước 10:00"', '10:00' === $r['han'] );
$GLOBALS['VHCP_META'] = array( 'khh_dt_co_so' => $TP );
$r = khh_dt_rest_qt_xem();
phep( '🔴 REST trả `lich` (có ngày đã chốt) và `viec` (không có) — cùng quán', (bool) array_filter( $r['lich'], function ( $x ) { return 'da_chot' === $x['trang_thai']; } ) && ! array_filter( $r['viec'], function ( $x ) { return 'da_chot' === $x['trang_thai']; } ) && count( $r['lich'] ) > count( $r['viec'] ) );
$GLOBALS['VHCP_META'] = array( 'khh_dt_co_so' => $GV );
$GLOBALS['VHCP_CO_QUYEN'] = true;
$GLOBALS['VHCP_META'] = array();
$r = khh_dt_rest_qt_luu( new WP_REST_Request( array( 'bat' => '1', 'han' => '09:30', 'email' => 'ketoan@example.test', 'lui' => '5' ) ) );
phep( 'quản trị lưu: cấu hình về, lịch đặt lại theo 09:30', ! is_wp_error( $r ) && '09:30' === $r['cf']['han'] && 5 === $r['cf']['lui'] && 'ketoan@example.test' === $r['cf']['email'] && isset( $GLOBALS['VHCP_LICH'][ KHH_DT_QT_MOC ] ) );
phep( 'quản trị thấy tổng hợp hôm qua + nhật ký', isset( $r['tong_hop']['dem'] ) && isset( $r['nhat_ky'] ) );
$r = khh_dt_rest_qt_luu( new WP_REST_Request( array( 'bat' => '1', 'han' => '09:30', 'email' => 'khong-phai-email' ) ) );
phep( '🔴 địa chỉ thư sai -> 400, không lặng lẽ bỏ', is_wp_error( $r ) && 400 === (int) $r->get_error_data()['status'] );
$r = khh_dt_rest_qt_luu( new WP_REST_Request( array( 'bat' => '1', 'han' => '25:99', 'email' => '' ) ) );
phep( 'giờ hạn không hợp lệ thì giữ giờ cũ; email trống = chỉ ghi nhật ký', ! is_wp_error( $r ) && '09:30' === $r['cf']['han'] && '' === $r['cf']['email'] );
$r = khh_dt_rest_qt_chay();
phep( 'REST chạy ngay trả về dòng vừa chạy', isset( $r['vua_chay']['ngay'] ) && isset( $r['nhat_ky'][0] ) && $r['vua_chay']['luc'] === $r['nhat_ky'][0]['luc'] );

/* ── 7. màn Nhập báo cáo nhận `quy_trinh` ── */
$GLOBALS['VHCP_DANG_NHAP_WP'] = true;
$r = khh_dt_rest_bc_lay( new WP_REST_Request( array( 'ngay' => '2026-09-23', 'cua_hang' => $GV ) ) );
phep( 'GET bao-cao-ngay kèm quy_trinh với bốn bước', ! is_wp_error( $r ) && isset( $r['quy_trinh']['buoc']['kho'] ) && 'da_luu' === $r['quy_trinh']['trang_thai'] );

/* ── Cửa hàng trưởng Estella bấm Lưu báo cáo bị "không phụ trách cơ sở này" (anh Thắng 24/09/2026): tên POS có hai
      dấu cách, đường ghi gộp còn một rồi so chặt với hồ sơ. Nay tra tên nguyên văn + so lỏng. ── */
$CS_E2 = 'Tutu Train - Estella ( Dịch vụ  và Giải trí )';
pos_ngay( '2026-09-23', $CS_E2 );
$GLOBALS['VHCP_CO_QUYEN'] = false;
$GLOBALS['VHCP_META'] = array( 'khh_dt_co_so' => 'Tutu Train - Estella ( Dịch vụ và Giải trí )' );   // hồ sơ lưu bản một dấu cách
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => '2026-09-23', 'cua_hang' => $CS_E2, 'tien_mat_dem' => '500000' ) ) );
phep( '🔴 Lưu báo cáo với tên quán nguyên văn (hai dấu cách) trong khi hồ sơ ghi một dấu cách -> KHÔNG bị chối', ! is_wp_error( $r ) && $CS_E2 === $r['bao_cao']['cua_hang'] );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => '2026-09-23', 'cua_hang' => 'Tutu Train - Estella ( Dịch vụ và Giải trí )', 'tien_mat_dem' => '600000' ) ) );
phep( 'gửi bản một dấu cách cũng ghi vào đúng dòng tên nguyên văn (không sinh dòng thứ hai)', ! is_wp_error( $r ) && $CS_E2 === $r['bao_cao']['cua_hang'] && 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . khh_dt_bang_bc() . " WHERE ngay = '2026-09-23' AND cua_hang LIKE '%Estella%'" ) );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => '2026-09-23', 'cua_hang' => $GV, 'tien_mat_dem' => '1' ) ) );
phep( 'quán KHÁC thì vẫn chối 403', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );
$GLOBALS['VHCP_META'] = array();
$GLOBALS['VHCP_CO_QUYEN'] = true;

/* ── Tab Nhập: món là thành phần combo phải thấy "lẻ 2 + 6 theo combo → rời kho 8" (anh Thắng 24/09/2026). ── */
$CS_K = 'Quán thử theo combo';
khh_dt_kho_mh_dat( $CS_K, array( 'Nước suối Danasi', 'Thạch trái cây' ) );
delete_option( 'khh_dt_kho_combo_ls' );
khh_dt_kho_combo_dat( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + NƯỚC SUỐI', array( 'Nước suối Danasi' => 1 ), '2026-09-01' );
khh_dt_kho_combo_dat( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH', array( 'Thạch trái cây' => 1 ), '2026-09-01' );
$wpdb->query( $wpdb->prepare( 'INSERT OR REPLACE INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-24', $CS_K, 1820000, 20, 20, '[]', wp_json_encode( array(
		array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + NƯỚC SUỐI ', 'g' => 'VÉ COMBO.', 'q' => 6,  'r' => 540000 ),
		array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH',      'g' => 'VÉ COMBO.', 'q' => 14, 'r' => 1260000 ),
		array( 'n' => 'NƯỚC SUỐI DANASI',                                  'g' => 'ĐÓNG SẴN',  'q' => 2,  'r' => 20000 ),
	) ) ) );
$pos = khh_dt_so_pos( '2026-09-24', $CS_K );
$ns  = array_values( array_filter( $pos['mon'], function ( $m ) { return 'NƯỚC SUỐI DANASI' === $m['n']; } ) );
phep( '🔴 dòng Nước suối: q = 2 (lẻ), kho_combo = 6, kho_tong = 8', 1 === count( $ns ) && 2.0 === $ns[0]['q'] && 6.0 === $ns[0]['kho_combo'] && 8.0 === $ns[0]['kho_tong'] );
phep( 'dòng combo không mang kho_combo', ! isset( array_values( array_filter( $pos['mon'], function ( $m ) { return false !== strpos( $m['n'], 'THẠCH' ); } ) )[0]['kho_combo'] ) );
$tc = array(); foreach ( $pos['theo_combo'] as $x ) { $tc[ $x['n'] ] = $x; }
phep( '🔴 theo_combo kể cả thành phần không có dòng FABi: Thạch trái cây 14 (lẻ 0), Nước suối Danasi 6 (lẻ 2)', 14.0 === $tc['Thạch trái cây']['combo'] && 0.0 === $tc['Thạch trái cây']['le'] && 6.0 === $tc['Nước suối Danasi']['combo'] && 2.0 === $tc['Nước suối Danasi']['le'] );
delete_option( 'khh_dt_kho_combo_ls' );
delete_option( 'khh_dt_kho_mat_hang' );

/* Mã nguồn: plugin nạp module, đặt lịch lúc nâng cấp, gỡ lúc tắt. */
$src = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/khh-doanh-thu.php' ) );
phep( 'khh-doanh-thu.php nạp quy-trinh.php và gọi khh_dt_qt_dat_lich() lúc kích hoạt', false !== strpos( $src, "require_once KHH_DT_DIR . 'quy-trinh.php';" ) && false !== strpos( $src, 'khh_dt_qt_dat_lich();' ) && false !== strpos( $src, 'khh_dt_qt_go_lich();' ) );

delete_option( KHH_DT_QT_OPT );
delete_option( KHH_DT_QT_NHAT );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: quy trình theo dõi bốn bước, hạn theo giờ site, thư tổng hợp đúng người, không bịa số.\n";
