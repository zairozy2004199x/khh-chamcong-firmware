<?php
/**
 * HÀNG BÁN THEO MÁY — CƠ SỞ SOÁT, LỆCH MỚI NHẬP, KẾ TOÁN XÁC NHẬN.
 *
 * Anh Thắng 23/09/2026: *"hiện số lượng hàng bán và thành tiền để nhân viên kiểm kho bán được và chốt
 * bán thực tế đúng máy POS không, nếu lệch nhân viên mới nhập, đúng rồi thì để nguyên, chốt đúng
 * xong thì bấm lưu và chốt, để kế toán xác nhận bạn tại cửa hàng chốt bán đúng như vậy"*.
 *
 * Chốt:
 *   1. `khh_dt_so_pos()` mang danh sách món máy ghi (tên, nhóm, SL, tiền), tiền nhiều xếp trước.
 *   2. 🔴 Rửa số thực: giữ 0 (có nghĩa), bỏ âm / chữ / mảng lồng / tên rỗng.
 *   3. 🔴 Lệch = so lại với máy, không tin màn: gửi dòng khớp thì không tính là lệch.
 *   4. 🔴 Phân biệt "chưa soát" (chưa có mon_thuc) với "soát rồi, khớp hết" ('{}').
 *   5. Lưu báo cáo ghi mon_thuc, đọc lại ra mảng; sửa mon_thuc cũng vào lịch sử sửa.
 *
 * Chạy: php tools/test/kiem-hang-ban-chot.php
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
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
if ( ! function_exists( 'khh_dt_ten_long' ) && file_exists( $goc . '/nguoi.php' ) ) {
	/* `khh_dt_ten_pos_chuan` (tra tên POS nguyên văn) nằm ở nguoi.php. */
	if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) { function khh_dt_phien_nguoi() { return false; } }
}
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang() );
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_bc() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0,
	chiet_khau REAL DEFAULT 0, so_hd INTEGER DEFAULT 0, so_mon REAL DEFAULT 0, so_ve REAL DEFAULT 0,
	pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, tien_nop REAL DEFAULT 0,
	so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0, tong_khach INTEGER DEFAULT 0,
	ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );

$CS  = 'TuTu Train - Aeon Tân Phú';
$MON = array(
	array( 'n' => 'VÉ LẺ.',    'g' => 'Vé',      'q' => 11, 'r' => 440000 ),
	array( 'n' => 'VÉ COMBO.', 'g' => 'Vé',      'q' => 36, 'r' => 2880000 ),
	array( 'n' => 'ĐÓNG SẴN',  'g' => 'Đồ uống', 'q' => 4,  'r' => 40000 ),
);
$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	'2026-09-22', $CS, 3590000, 43, 49, '[]', wp_json_encode( $MON ) ) );

/* 0. 🔴 Tách sale vé / bán lẻ theo cột "Loại món" của FABi; dòng cũ không có 'l' thì đoán qua tên/nhóm. */
$MON_L = array(
	array( 'n' => 'VÉ TRẺ EM + NGƯỜI LỚN',       'g' => 'VÉ COMBO.', 'l' => 'Vé',      'q' => 36, 'r' => 2880000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM',    'g' => 'VÉ LẺ.',    'l' => 'Vé',      'q' => 11, 'r' => 440000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN', 'g' => 'VÉ LẺ.',    'l' => 'Vé',      'q' => 2,  'r' => 100000 ),
	array( 'n' => 'KẸO CỨNG',                     'g' => 'ĐÓNG SẴN',  'l' => 'Đồ ăn',   'q' => 4,  'r' => 80000 ),
	array( 'n' => 'KẸO ĐỒ CHƠI',                  'g' => 'ĐÓNG SẴN',  'l' => 'Đồ ăn',   'q' => 1,  'r' => 50000 ),
	array( 'n' => 'NƯỚC SUỐI DANASI',             'g' => 'ĐÓNG SẴN',  'l' => 'Đồ uống', 'q' => 4,  'r' => 40000 ),
);
delete_option( 'khh_dt_nhom_ve' );
delete_option( 'khh_dt_nhom_phu' );
phep( 'chưa cấu hình -> khh_dt_nhom_ve_ds() là false', false === khh_dt_nhom_ve_ds() && false === khh_dt_nhom_phu_ds() );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( 'chưa cấu hình: tạm theo cột Loại món — vé = 3.420.000, bán lẻ = 170.000; sale phụ = 0 (không đoán)', 3420000.0 === $t['ve'] && 170000.0 === $t['le'] && 0.0 === $t['phu'] );
/* 🔴 Anh Thắng (23/09/2026, ba ảnh): Sale vé = VÉ COMBO. + VÉ LẺ.; Sale bán lẻ = ĐÓNG SẴN;
   Sale phụ = VÉ LẺ. + ĐÓNG SẴN (mọi thứ trừ vé combo chính). */
khh_dt_nhom_ve_dat( array( 'VÉ COMBO.', 'VÉ LẺ.', '', ' VÉ COMBO. ' ) );
phep( 'lưu nhóm: rửa trống, bỏ trùng', array( 'VÉ COMBO.', 'VÉ LẺ.' ) === khh_dt_nhom_ve_ds() );
/* 🔴 Sale phụ (anh Thắng chỉnh lần cuối 23/09/2026): "chiết khấu 20k cho 1 đơn vé combo 80k" -> số vé × đ/vé. */
khh_dt_nhom_phu_dat( array( 'VÉ COMBO.' => '20000' ) );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( '🔴 sale vé = VÉ COMBO. + VÉ LẺ. = 3.420.000', 3420000.0 === $t['ve'] );
phep( '🔴 bán lẻ = ĐÓNG SẴN = 170.000', 170000.0 === $t['le'] );
phep( '🔴 sale phụ = 36 combo × 20.000 = 720.000', 720000.0 === $t['phu'] );
phep( 'sale phụ KHÔNG đụng sale vé / bán lẻ (vé vẫn 3.420.000)', 3420000.0 === $t['ve'] );
/* 🔴 Theo TÊN VÉ đè theo NHÓM (anh Thắng 23/09/2026: "Sale … = loại vé (sl) × tiền tại mỗi cửa hàng"). */
khh_dt_ve_phu_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 15000 ) );          // bảng chung
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( '🔴 tên vé khai 15.000 đè nhóm 20.000: 36 × 15.000 = 540.000', 540000.0 === $t['phu'] );
khh_dt_ve_phu_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 5000 ) );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( 'vé lẻ (nhóm không có phụ) khai riêng 5.000: + 11 × 5.000 = 595.000', 595000.0 === $t['phu'] );
khh_dt_ve_phu_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 0 ) );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( '🔴 khai 0 cho tên vé là LOẠI vé ấy ra dù nhóm có 20.000: còn 55.000', 55000.0 === $t['phu'] );
khh_dt_ve_phu_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => '', 'VÉ TUTU TRAIN: VÉ TRẺ EM' => '' ) );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( 'xoá khai theo tên -> về theo nhóm 720.000', 720000.0 === $t['phu'] );
/* Riêng từng quán: B khai 25.000 cho combo, C thừa nhóm. */
khh_dt_ve_phu_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 25000 ), 'Quán B' );
phep( 'theo tên vé riêng quán B: 36 × 25.000 = 900.000; quán C vẫn 720.000', 900000.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán B' )['phu'] && 720000.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán C' )['phu'] );
delete_option( 'khh_dt_ve_phu' );
/* Danh sách ô tích của bản 1.59.0/1.59.1 (khoá số) phải bị bỏ, không hiểu nhầm thành đ/vé. */
phep( 'danh sách ô tích cũ rửa ra rỗng', array() === khh_dt_nhom_phu_sach( array( 'VÉ LẺ.', 'ĐÓNG SẴN' ) ) );
phep( 'rửa: bỏ số âm, chữ, 0; giữ số > 0', array( 'VÉ COMBO.' => 20000.0 ) === khh_dt_nhom_phu_sach( array( 'VÉ COMBO.' => '20000', 'VÉ LẺ.' => 0, 'X' => -5, 'Y' => 'abc' ) ) );
/* Tích khác đi: chỉ combo là vé. */
khh_dt_nhom_ve_dat( array( 'VÉ COMBO.' ) );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( 'chỉ tích VÉ COMBO.: vé 2.880.000, bán lẻ 710.000, phụ vẫn 720.000 (cột riêng)', 2880000.0 === $t['ve'] && 710000.0 === $t['le'] && 720000.0 === $t['phu'] );
khh_dt_nhom_ve_dat( array() );
khh_dt_nhom_phu_dat( array() );
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000 );
phep( 'tích rỗng (đã cấu hình) -> vé 0, phụ 0, KHÔNG lùi về đoán', 0.0 === $t['ve'] && 3590000.0 === $t['le'] && 0.0 === $t['phu'] );
/* 🔴 THEO TỪNG CỬA HÀNG (anh Thắng 23/09/2026: "Mỗi cửa hàng 1 cấu hình đi"): bảng chung làm nền, quán khai riêng thì theo quán. */
update_option( 'khh_dt_nhom_ve', array( 'VÉ COMBO.', 'VÉ LẺ.' ) );   // dạng phẳng bản 1.59.0 = bảng chung
delete_option( 'khh_dt_nhom_phu' );
phep( 'sổ phẳng cũ tự hiểu là bảng chung', array( 'VÉ COMBO.', 'VÉ LẺ.' ) === khh_dt_nhom_ve_ds( 'Quán B' ) );
khh_dt_nhom_ve_dat( array( 'VÉ COMBO.' ), 'Quán B' );
phep( 'quán B khai riêng: chỉ VÉ COMBO.', array( 'VÉ COMBO.' ) === khh_dt_nhom_ve_ds( 'Quán B' ) && true === khh_dt_nhom_co_rieng( 'khh_dt_nhom_ve', 'Quán B' ) );
phep( 'quán khác vẫn thừa bảng chung', array( 'VÉ COMBO.', 'VÉ LẺ.' ) === khh_dt_nhom_ve_ds( 'Quán C' ) && false === khh_dt_nhom_co_rieng( 'khh_dt_nhom_ve', 'Quán C' ) );
phep( '🔴 tách tiền theo quán: B vé 2.880.000, C vé 3.420.000', 2880000.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán B' )['ve'] && 3420000.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán C' )['ve'] );
phep( 'chưa có phụ ở đâu -> phụ = 0 ở cả hai quán', 0.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán B' )['phu'] && 0.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán C' )['phu'] );
khh_dt_nhom_phu_dat( array( 'VÉ COMBO.' => 20000 ), 'Quán B' );
phep( 'phụ khai riêng cho B: 720.000; C vẫn 0', 720000.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán B' )['phu'] && 0.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON_L ), 3590000, 'Quán C' )['phu'] );
delete_option( 'khh_dt_nhom_ve' );
delete_option( 'khh_dt_nhom_phu' );
/* Món bán lẻ mà tên có chữ "Vé đồ chơi" nhưng Loại món = Đồ ăn -> theo CỘT, không theo tên. */
$t = khh_dt_bc_tach_tien( wp_json_encode( array( array( 'n' => 'Vé đồ chơi', 'g' => 'ĐÓNG SẴN', 'l' => 'Đồ ăn', 'q' => 1, 'r' => 50000 ) ) ), 50000 );
phep( 'có cột Loại món thì theo cột, không đoán tên', 0.0 === $t['ve'] && 50000.0 === $t['le'] );
/* Dòng nạp trước 1.59.0 không có 'l' -> đoán qua tên/nhóm. */
$t = khh_dt_bc_tach_tien( wp_json_encode( $MON ), 3590000 );
phep( 'dòng cũ không có l: VÉ LẺ./VÉ COMBO. nhận là vé qua nhóm', 3320000.0 === $t['ve'] && 270000.0 === $t['le'] );
phep( 'danh sách món trần hụt dòng thì bán lẻ vẫn = doanh thu − vé (không âm)', 0.0 === khh_dt_bc_tach_tien( wp_json_encode( $MON ), 1000 )['le'] );
/* Bảng nhóm cho Quản trị: gom mọi cơ sở, kèm loại món và tiền. */
$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)',
	gmdate( 'Y-m-d' ), 'Quán B', 3590000, 43, 49, '[]', wp_json_encode( $MON_L ) ) );
khh_dt_nhom_ve_dat( array( 'VÉ COMBO.' ) );
khh_dt_nhom_phu_dat( array( 'VÉ COMBO.' => 20000 ) );
$nh = khh_dt_nhom_mon_thay( 3650 );
$ten_nhom = array_map( function ( $x ) { return $x['nhom']; }, $nh );
$theo = function ( $ten ) use ( $nh ) { foreach ( $nh as $x ) { if ( $ten === $x['nhom'] ) { return $x; } } return null; };
phep( 'liệt kê đủ nhóm mọi cơ sở', in_array( 'VÉ COMBO.', $ten_nhom, true ) && in_array( 'ĐÓNG SẴN', $ten_nhom, true ) && in_array( 'Vé', $ten_nhom, true ) );
phep( 'tiền nhiều xếp trước (nhóm "Vé" 3.320.000 đứng trên VÉ COMBO. 2.880.000)', 'Vé' === $ten_nhom[0] && 'VÉ COMBO.' === $ten_nhom[1] );
phep( 'nhóm kèm loại món, tiền, cờ vé và đ/vé phụ', array( 'Vé' ) === $theo( 'VÉ COMBO.' )['loai'] && true === $theo( 'VÉ COMBO.' )['ve'] && 20000.0 === $theo( 'VÉ COMBO.' )['phu'] && 2880000.0 === $theo( 'VÉ COMBO.' )['tien'] );
phep( 'VÉ LẺ.: không là vé (chưa tích), không có phụ (null)', false === $theo( 'VÉ LẺ.' )['ve'] && null === $theo( 'VÉ LẺ.' )['phu'] );
$dong_san = $theo( 'ĐÓNG SẴN' );
phep( 'ĐÓNG SẴN gom hai loại món Đồ ăn, Đồ uống; không là vé; không có phụ', array( 'Đồ ăn', 'Đồ uống' ) === $dong_san['loai'] && false === $dong_san['ve'] && null === $dong_san['phu'] );
$r = khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'nhom_ve' => 'hỏng', 'cua_hang' => 'Quán B' ) ) );
phep( 'REST nhom-ve: JSON hỏng -> chối', is_wp_error( $r ) );
$r = khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'nhom_ve' => '[]' ) ) );
phep( '🔴 REST nhom-ve: thiếu cửa hàng -> chối', is_wp_error( $r ) );
$r = khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'nhom_ve' => wp_json_encode( array( 'VÉ COMBO.', 'VÉ LẺ.' ) ), 'cua_hang' => 'Quán B' ) ) );
phep( 'REST nhom-ve: lưu riêng quán B và trả bảng của quán B; không gửi nhom_phu thì giữ phụ cũ (chung)', true === $r['da_cau_hinh'] && true === $r['rieng'] && 'Quán B' === $r['cua_hang'] && array( 'VÉ COMBO.', 'VÉ LẺ.' ) === $r['nhom_ve'] && array( 'VÉ COMBO.' => 20000.0 ) === $r['nhom_phu'] && 3 === count( $r['nhom'] ) );
$r = khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'nhom_ve' => '[]', 'nhom_phu' => wp_json_encode( array( 'VÉ COMBO.' => 15000, 'ĐÓNG SẴN' => 0 ) ), 'cua_hang' => 'Quán B' ) ) );
phep( 'REST nhom-ve: gửi cả hai thì lưu cả hai cho quán ấy (đ/vé, bỏ 0)', array() === $r['nhom_ve'] && array( 'VÉ COMBO.' => 15000.0 ) === $r['nhom_phu'] );
$rc = khh_dt_rest_nhom_ve_xem( new WP_REST_Request( array( 'cua_hang' => $CS ) ) );
phep( 'quán khác chỉ thấy nhóm của mình (Vé, Đồ uống) và vẫn thừa bảng chung', false === $rc['rieng'] && array( 'VÉ COMBO.' ) === $rc['nhom_ve'] && 2 === count( $rc['nhom'] ) );
$src_bc = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/bao-cao-ngay.php' ) );
phep( "🔴 route /nhom-ve gác khh_dt_duoc_nap cả GET và POST", 2 <= substr_count( substr( $src_bc, strpos( $src_bc, "'/nhom-ve'" ) ), "'permission_callback' => 'khh_dt_duoc_nap'" ) );
/* ── 24/09/2026 anh Thắng: "có là đều hết chứ" — lưu MỘT LẦN cho mọi quán (bảng chung), bỏ khai riêng, và tên quán
      có hai dấu cách phải tra ra đúng quán (ảnh: tiêu đề Tân An, ô chọn nhảy về Tân Phú, "chưa có nhóm món nào"). ── */
$r = khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'nhom_ve' => wp_json_encode( array( 'VÉ COMBO.', 'VÉ LẺ.' ) ), 'nhom_phu' => wp_json_encode( array( 'VÉ COMBO.' => 20000 ) ), 'cua_hang' => '*' ) ) );
phep( '🔴 cua_hang = "*" là lưu BẢNG CHUNG: không lỗi, trả về cờ chung', ! is_wp_error( $r ) && true === $r['chung'] );
$rc = khh_dt_rest_nhom_ve_xem( new WP_REST_Request( array( 'cua_hang' => $CS ) ) );
phep( '🔴 quán chưa khai riêng thừa ngay bảng chung: 2 nhóm vé, phụ 20.000', array( 'VÉ COMBO.', 'VÉ LẺ.' ) === $rc['nhom_ve'] && array( 'VÉ COMBO.' => 20000.0 ) === $rc['nhom_phu'] && false === $rc['rieng'] && true === $rc['chung'] );
$rb = khh_dt_rest_nhom_ve_xem( new WP_REST_Request( array( 'cua_hang' => 'Quán B' ) ) );
phep( 'quán B vẫn giữ bảng riêng của nó (phụ 15.000, không nhóm vé)', true === $rb['rieng'] && array() === $rb['nhom_ve'] && array( 'VÉ COMBO.' => 15000.0 ) === $rb['nhom_phu'] );
$r = khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'xoa_rieng' => '1', 'cua_hang' => 'Quán B' ) ) );
phep( '🔴 "Bỏ khai riêng": quán B thừa lại bảng chung cả vé lẫn phụ', ! is_wp_error( $r ) && false === $r['rieng'] && array( 'VÉ COMBO.', 'VÉ LẺ.' ) === $r['nhom_ve'] && array( 'VÉ COMBO.' => 20000.0 ) === $r['nhom_phu'] );
phep( 'bỏ khai riêng mà không nêu quán / nêu "*" -> 400', is_wp_error( khh_dt_rest_nhom_ve_dat( new WP_REST_Request( array( 'xoa_rieng' => '1', 'cua_hang' => '*' ) ) ) ) );
/* Tên quán có HAI dấu cách trong kho POS: gửi lên bản một dấu cách (hay ngược lại) vẫn phải tra ra đúng quán. */
$CS2 = 'Tutu Train - Aeon Tân An ( Dịch vụ  và Giải trí )';
$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,so_hd,so_ve,pttt,mon) VALUES (%s,%s,%f,%d,%f,%s,%s)', '2026-09-23', $CS2, 100000, 1, 1, '[]', wp_json_encode( $MON_L ) ) );
$rc = khh_dt_rest_nhom_ve_xem( new WP_REST_Request( array( 'cua_hang' => 'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )' ) ) );
phep( '🔴 tên gửi lên khác dấu cách vẫn tra ra tên NGUYÊN VĂN và thấy nhóm món của quán', $CS2 === $rc['cua_hang'] && count( $rc['nhom'] ) > 0 );
phep( 'bản có "*" giữa dấu cách vẫn là bảng chung', '*' === khh_dt_bc_ten_cua( ' * ' ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . khh_dt_bang() . ' WHERE cua_hang = %s', $CS2 ) );
$wpdb->query( "DELETE FROM " . khh_dt_bang() . " WHERE cua_hang = 'Quán B'" );
delete_option( 'khh_dt_nhom_ve' );
delete_option( 'khh_dt_nhom_phu' );
$src_df = preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/doc-file.php' ) );
phep( "trình đọc file ghi 'l' (loại món) vào từng món", false !== strpos( $src_df, "'l' => isset( \$o['mon_l'][ \$ten_mon ] )" ) && false !== strpos( $src_df, "\$lay( 'loai_mon' )" ) );

/* 1 */
$pos = khh_dt_so_pos( '2026-09-22', $CS );
phep( 'so_pos có danh sách món', isset( $pos['mon'] ) && 3 === count( $pos['mon'] ) );
phep( 'tiền nhiều xếp trước', 'VÉ COMBO.' === $pos['mon'][0]['n'] && 'ĐÓNG SẴN' === $pos['mon'][2]['n'] );
phep( 'mang đủ tên, nhóm, SL, tiền, cờ vé', 'Vé' === $pos['mon'][0]['g'] && 36.0 === $pos['mon'][0]['q'] && 2880000.0 === $pos['mon'][0]['r'] && true === $pos['mon'][0]['ve'] && false === $pos['mon'][2]['ve'] );
/* Bán lẻ = doanh thu máy − vé (270.000, vì dữ liệu thử có doanh thu máy lớn hơn tổng món); sale phụ CỘNG THEO MÓN
   không phải vé -> 40.000. Hai cách cố ý khác nhau: bán lẻ phải cộng với vé ra đúng doanh thu máy. */
phep( 'so_pos mang tien_ve / tien_le / tien_phu (chưa khai phụ -> 0)', 3320000.0 === $pos['tien_ve'] && 270000.0 === $pos['tien_le'] && 0.0 === $pos['tien_phu'] );

/* 2 */
$s = khh_dt_bc_mon_thuc_sach( wp_json_encode( array( 'VÉ LẺ.' => '9', 'ĐÓNG SẴN' => 0, 'X' => -1, 'Y' => 'abc', 'Z' => array( 1 ), '' => 5 ) ) );
phep( '🔴 giữ số ≥ 0 kể cả 0; bỏ âm, chữ, mảng, tên rỗng', array( 'VÉ LẺ.' => 9.0, 'ĐÓNG SẴN' => 0.0 ) === $s );
phep( 'JSON hỏng -> rỗng, không nổ', array() === khh_dt_bc_mon_thuc_sach( 'không json' ) );

/* 3 + 4 */
$l = khh_dt_bc_mon_lech( wp_json_encode( $MON ), wp_json_encode( array( 'VÉ LẺ.' => 9, 'VÉ COMBO.' => 36 ) ) );
phep( '🔴 chỉ dòng KHÁC máy mới là lệch (COMBO gửi 36 = máy -> không tính)', 1 === $l['n'] && 'VÉ LẺ.' === $l['ds'][0]['n'] && 11.0 === $l['ds'][0]['may'] && 9.0 === $l['ds'][0]['thuc'] );
phep( 'có mon_thuc -> đã soát', true === $l['da_chot'] );
$l = khh_dt_bc_mon_lech( wp_json_encode( $MON ), '{}' );
phep( "🔴 '{}' là soát rồi, khớp hết", 0 === $l['n'] && true === $l['da_chot'] );
$l = khh_dt_bc_mon_lech( wp_json_encode( $MON ), null );
phep( '🔴 null là CHƯA soát', 0 === $l['n'] && false === $l['da_chot'] );
$l = khh_dt_bc_mon_lech( wp_json_encode( $MON ), wp_json_encode( array( 'Món lạ' => 2 ) ) );
phep( 'món máy không ghi mà cơ sở khai bán -> lệch với máy = 0', 1 === $l['n'] && 0.0 === $l['ds'][0]['may'] );

/* 5 */
$GLOBALS['KHO_PHAM_VI'] = array();
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => '2026-09-22', 'cua_hang' => $CS, 'tien_mat_dem' => '2100000',
	'mon_thuc' => wp_json_encode( array( 'VÉ LẺ.' => 9 ) ), 'chot' => '1' ) ) );
phep( 'lưu được kèm mon_thuc', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
$g = khh_dt_rest_bc_lay( new WP_REST_Request( array( 'ngay' => '2026-09-22', 'cua_hang' => $CS ) ) );
phep( 'đọc lại ra mảng [tên => SL]', array( 'VÉ LẺ.' => 9.0 ) === $g['bao_cao']['mon_thuc'] && 1 === (int) $g['bao_cao']['chot'] );
phep( 'GET vẫn mang pos.mon cho màn vẽ bảng', 3 === count( $g['pos']['mon'] ) );
/* Anh Thắng 25/09/2026: "Cho báo cáo hàng để nhân viên gửi báo cáo hằng ngày, qua bên này chỉ hiện không sửa" — GET mang
   sổ kho của ngày (mảng; không có module kho thì rỗng), cùng hàm với tab Kho. */
phep( 'GET mang `kho` = sổ kho của ngày (mảng)', isset( $g['kho'] ) && is_array( $g['kho'] ) && ( ! function_exists( 'khh_dt_kho_bang_ngay' ) || $g['kho'] === khh_dt_kho_bang_ngay( '2026-09-22', $CS ) ) );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => '2026-09-22', 'cua_hang' => $CS, 'tien_mat_dem' => '2100000', 'chot' => '1' ) ) );
phep( 'lưu lại KHÔNG gửi mon_thuc -> {} (khớp hết), và đổi mon_thuc vào lịch sử sửa', 1 === (int) $r['sua_lan'] && '{}' === $r['bao_cao']['mon_thuc'] );
$g = khh_dt_rest_bc_lay( new WP_REST_Request( array( 'ngay' => '2026-09-22', 'cua_hang' => $CS ) ) );
phep( 'đọc lại: đã soát, khớp hết', array() === $g['bao_cao']['mon_thuc'] );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: hàng bán máy bày ra, cơ sở chốt lệch mới nhập, kế toán biết khớp hay lệch.\n";
