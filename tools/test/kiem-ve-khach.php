<?php
/**
 * BÓC TÁCH VÉ → KHÁCH VÀO — CHẠY THẬT.
 *
 * Anh Thắng 23/09/2026: *"nếu vé là combo VÉ TRẺ EM + NGƯỜI LỚN tính là 2 người, còn nếu nó là trẻ
 * hoặc người lớn riêng thì là 1 người"* — *"bóc tách sẵn cho nhân viên, áp dụng cho gian Tàu trước"*.
 *
 * Chốt:
 *   1. 🔴 Khách máy = Σ số vé × khách mỗi vé; combo tính 2, vé lẻ tính 1; món không khai bỏ qua.
 *   2. 🔴 Vé chưa khai TẠM TÍNH 1 khách/vé, không bỏ qua (anh Thắng 23/09: Gò Vấp *"phải 28 chứ"*);
 *      ngày không có vé nào (khai hay chưa) -> NULL, không phải 0.
 *   3. 🔴 Vé có bán mà chưa khai phải được KỂ TÊN kèm phần tạm tính — để khai 2 cho combo là số lên đúng.
 *   4. Gợi ý: có dấu "+" -> 2, vé lẻ -> 1, không phải vé -> null; nhận vé qua tên hoặc nhóm món.
 *   5. Bảng khai: ghi số nguyên, '' là xoá, số âm bị chối; giữ nguyên tên khác đang có.
 *   6. `khh_dt_so_pos()` mang `khach_may` + `ve_chua_tach` xuống màn Nhập báo cáo.
 *   7. Danh sách món cho màn Quản trị: vé xếp trước, kèm số đã bán, gợi ý, số đã khai.
 *
 * Chạy: php tools/test/kiem-ve-khach.php
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
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0,
	chiet_khau REAL DEFAULT 0, so_hd INTEGER DEFAULT 0, so_mon REAL DEFAULT 0, so_ve REAL DEFAULT 0,
	pttt TEXT DEFAULT '[]', mon TEXT NOT NULL DEFAULT '[]', UNIQUE(ngay,cua_hang) )" );
delete_option( 'khh_dt_ve_khach' );

$CS = 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )';
function ngay_ban( $ngay, $cs, $mon, $so_ve = 0 ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT OR REPLACE INTO ' . khh_dt_bang() . ' (ngay,cua_hang,so_ve,pttt,mon) VALUES (%s,%s,%f,%s,%s)',
		$ngay, $cs, $so_ve, '[]', wp_json_encode( $mon ) ) );
}
$MON = array(
	array( 'n' => 'VÉ TRẺ EM + NGƯỜI LỚN',        'g' => 'VÉ TUTU TRAIN', 'q' => 10, 'r' => 1500000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM',     'g' => 'VÉ TUTU TRAIN', 'q' => 5,  'r' => 400000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN',  'g' => 'VÉ TUTU TRAIN', 'q' => 7,  'r' => 630000 ),
	array( 'n' => 'Nước suối',                     'g' => 'ĐỒ UỐNG',       'q' => 20, 'r' => 200000 ),
	array( 'n' => 'Vé Online',                     'g' => '',              'q' => 3,  'r' => 0 ),
);
ngay_ban( '2026-09-22', $CS, $MON, 25 );

/* ── 4. gợi ý ── */
phep( 'combo có "+" gợi ý 2', 2 === khh_dt_ve_khach_goi_y( 'VÉ TRẺ EM + NGƯỜI LỚN' ) );
phep( 'vé lẻ gợi ý 1', 1 === khh_dt_ve_khach_goi_y( 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' ) );
phep( 'không phải vé -> null', null === khh_dt_ve_khach_goi_y( 'Nước suối', 'ĐỒ UỐNG' ) );
phep( 'nhận vé qua NHÓM món dù tên không có chữ vé', 1 === khh_dt_ve_khach_goi_y( 'Người lớn', 'Vé cổng' ) );
phep( '"Vé Online" cũng là vé (gợi ý 1, quản trị tự đặt 0 nếu muốn)', 1 === khh_dt_ve_khach_goi_y( 'Vé Online' ) );
phep( 'đếm chữ chỉ người: "TRẺ EM + NGƯỜI LỚN + THẠCH" -> 2 (thạch không phải người)', 2 === khh_dt_ve_khach_goi_y( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH', 'VÉ COMBO.' ) );
phep( '"TRẺ EM + NGƯỜI LỚN + BIM BIM" (nhận là vé qua nhóm VÉ COMBO.) -> 2', 2 === khh_dt_ve_khach_goi_y( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM', 'VÉ COMBO.' ) );
phep( 'tên không có chữ "vé" và không có nhóm thì không phải vé -> null', null === khh_dt_ve_khach_goi_y( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM' ) );
/* 24/09/2026 anh Thắng: "sai, combo này là 4" — vé bán theo lố X2. */
phep( '🔴 "TRẺ EM + NGƯỜI LỚN + BIM BIM X2" -> 4', 4 === khh_dt_ve_khach_goi_y( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM X2', 'VÉ COMBO.' ) );
phep( '"VÉ TRẺ EM X2" -> 2; "VÉ NGƯỜI LỚN x 3" -> 3', 2 === khh_dt_ve_khach_goi_y( 'VÉ TUTU TRAIN: VÉ TRẺ EM X2' ) && 3 === khh_dt_ve_khach_goi_y( 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN x 3' ) );
phep( 'chữ x trong tên thường ("Vé Xe điện", "Vé Xanh") không phải bội số', 1 === khh_dt_ve_khach_goi_y( 'Vé Xe điện' ) && 1 === khh_dt_ve_khach_goi_y( 'Vé Xanh 2 chiều' ) );
phep( '"VÉ GIA ĐÌNH: 2 NGƯỜI LỚN + 1 TRẺ EM" đếm được 2 cụm -> 2 (số đứng trước không đọc, quản trị sửa tay)', 2 === khh_dt_ve_khach_goi_y( 'VÉ GIA ĐÌNH: 2 NGƯỜI LỚN + 1 TRẺ EM' ) );
phep( 'chữ "ve" nằm giữa từ khác không phải vé', null === khh_dt_ve_khach_goi_y( 'Cà phê Việt', 'ĐỒ UỐNG' ) );

/* ── 2 + 3. chưa khai: tạm tính THEO GỢI Ý (không còn cứng 1) ── */
$k = khh_dt_khach_may( '2026-09-22', $CS );
/* 🔴 26/09/2026: anh Thắng — "Điền sẵn thì phải áp dụng luôn chứ, chứ đợi quản lý vào điền à".
   Combo "VÉ TRẺ EM + NGƯỜI LỚN" chưa khai giờ tạm theo gợi ý 2 (không phải cứng 1): 10×2 + 5×1 +
   7×1 + 3×1 = 35, không còn 25 như trước bản này. */
phep( '🔴 chưa khai vé nào -> tạm tính THEO GỢI Ý = 10×2+5×1+7×1+3×1 = 35, chắc 0, chưa đủ', 35 === $k['khach'] && 35 === $k['tam'] && 0 === $k['chac'] && 0 === $k['da_tach'] && false === $k['du'] );
phep( '🔴 kể tên đủ 4 loại vé có bán mà chưa khai (không kể Nước suối)',
	array( 'VÉ TRẺ EM + NGƯỜI LỚN', 'VÉ TUTU TRAIN: VÉ TRẺ EM', 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN', 'Vé Online' ) === array_keys( $k['chua_tach'] ) );
$pos = khh_dt_so_pos( '2026-09-22', $CS );
phep( 'so_pos: khach_may 35 kèm khach_tam 35 để màn ghi "tạm tính"', 35 === $pos['khach_may'] && 35 === $pos['khach_tam'] );
phep( 'so_pos: vẫn có so_ve như cũ', 25.0 === (float) $pos['so_ve'] );
/* Ngày chỉ bán đồ uống, không có vé nào -> NULL (không phải 0). */
ngay_ban( '2026-09-21', $CS, array( array( 'n' => 'Nước suối', 'g' => 'ĐỒ UỐNG', 'q' => 3, 'r' => 30000 ) ) );
phep( '🔴 ngày không có vé nào -> khách máy NULL, không phải 0', null === khh_dt_khach_may( '2026-09-21', $CS )['khach'] );
/* 🔴 Đúng ca Lotte Gò Vấp 23/09/2026: vé lẻ đã khai (1), hai combo chưa khai -> 12 + 2 + 10 + 4 = 28.
   LUẬT TỪ 1.64.4 (anh Thắng 24/09): khai theo TÊN VÉ dùng cho mọi quán (bảng chung '*'); quán nào vé ấy khác thì
   "cơ sở đó chủ động tự set" — số riêng đè số chung ở đúng quán ấy. */
$GV = 'TuTu Train - Lotte Gò Vấp';
$BT = 'Tutu Train - Aeon Bình Tân';
khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1, 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => 1 ) );   // chung
ngay_ban( '2026-09-23', $GV, array(
	array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH',  'g' => 'VÉ COMBO.', 'q' => 10, 'r' => 800000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM',                      'g' => 'VÉ LẺ.',    'q' => 12, 'r' => 480000 ),
	array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM','g' => 'VÉ COMBO.', 'q' => 4,  'r' => 320000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN',                   'g' => 'VÉ LẺ.',    'q' => 2,  'r' => 100000 ),
	array( 'n' => 'NƯỚC SUỐI DANASI',                              'g' => 'ĐÓNG SẴN',  'q' => 7,  'r' => 70000 ),
) );
ngay_ban( '2026-09-24', $BT, array(
	array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH', 'g' => 'VÉ COMBO.', 'q' => 20, 'r' => 1600000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM',                     'g' => 'VÉ LẺ.',    'q' => 3,  'r' => 120000 ),
) );
$k = khh_dt_khach_may( '2026-09-23', $GV );
/* 🔴 26/09/2026: hai combo THẠCH/BIM BIM chưa khai giờ tạm theo gợi ý 2/vé (đếm "trẻ em"+"người
   lớn" trong tên), không còn cứng 1/vé: chắc 12+2=14 (hai vé lẻ đã khai, không đổi) + tạm
   10×2+4×2=28 = 42, không phải 28 (tạm cứng 1) hay 14 (bỏ qua hẳn combo, lỗi gốc 23/09). */
phep( '🔴 Gò Vấp: combo chưa khai tạm THEO GỢI Ý (2/vé) -> 42, không phải 28 (tạm cứng 1) hay 14 (bỏ qua)', 42 === $k['khach'] && 28 === $k['tam'] && 14 === $k['chac'] );
phep( 'chi tiết cách tính: từng vé kèm số vé, khách/vé (combo chưa khai = gợi ý 2, không phải 1), cờ tạm tính', 4 === count( $k['chi_tiet'] ) && 1 === count( array_filter( $k['chi_tiet'], function ( $c ) { return 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' === $c['n'] && 10 === $c['q'] && 2 === $c['k'] && true === $c['tam']; } ) ) && 1 === count( array_filter( $k['chi_tiet'], function ( $c ) { return 'VÉ TUTU TRAIN: VÉ TRẺ EM' === $c['n'] && 12 === $c['q'] && 1 === $c['k'] && false === $c['tam']; } ) ) );
/* Khai chung (REST, cua_hang = '*') như nút "Lưu cho tất cả cửa hàng" bấm khi đang xem Gò Vấp. */
$GLOBALS['VHCP_CO_QUYEN'] = true; $GLOBALS['VHCP_DANG_NHAP_WP'] = true;
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'cua_hang' => '*', 'xem_cua_hang' => $GV, 'bang' => wp_json_encode( array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' => 2, 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM' => 2 ) ) ) ) );
phep( 'REST cua_hang="*" ghi bảng chung, trả về theo quán đang xem (xem_cua_hang)', ! is_wp_error( $r ) && $GV === $r['cua_hang'] && 2 === $r['bang_chung']['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH'] && array() === $r['bang_rieng'] );
$k = khh_dt_khach_may( '2026-09-23', $GV );
phep( 'khai combo = 2 -> 20 + 12 + 8 + 2 = 42, đủ', 42 === $k['khach'] && 0 === $k['tam'] && true === $k['du'] );
/* 🔴 KHAI CHUNG DÙNG CHO MỌI QUÁN: Bình Tân cùng bán combo THẠCH -> 20 × 2 + 3 = 43, không phải 23 (ảnh anh Thắng 24/09). */
$k = khh_dt_khach_may( '2026-09-24', $BT );
phep( '🔴 Bình Tân thấy ngay combo khai ở Gò Vấp: 43 khách, không tạm tính', 43 === $k['khach'] && 0 === $k['tam'] );
phep( 'bảng của Tân Phú cũng thấy', 2 === khh_dt_ve_khach_bang( $CS )['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH'] );
/* Quán tự set riêng: Bình Tân bảo combo THẠCH ở quán mình là 3 người (nút "Lưu riêng cho quán này" chỉ gửi ô khác chung). */
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'cua_hang' => $BT, 'xem_cua_hang' => $BT, 'bang' => wp_json_encode( array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' => 3, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => '' ) ) ) ) );
phep( '🔴 số riêng đè số chung Ở QUÁN ẤY: Bình Tân 20 × 3 + 3 = 63', 63 === khh_dt_khach_may( '2026-09-24', $BT )['khach'] && array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' => 3 ) === $r['bang_rieng'] && 3 === $r['bang']['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH'] && 2 === $r['bang_chung']['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH'] );
phep( '🔴 quán khác KHÔNG bị ảnh hưởng bởi số riêng của Bình Tân: Gò Vấp vẫn 42', 42 === khh_dt_khach_may( '2026-09-23', $GV )['khach'] && array() === khh_dt_ve_khach_bang_rieng( $GV ) );
$mc = khh_dt_ve_khach_mon_cua( $BT, 3650 );
$m0 = array_values( array_filter( $mc, function ( $x ) { return 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' === $x['ten']; } ) )[0];
phep( 'danh sách món của quán cắm cờ rieng + kèm số chung để màn bày "(chung: 2)"', true === $m0['rieng'] && 3 === $m0['khach'] && 2 === $m0['khach_chung'] );
/* Bỏ set riêng -> quán thừa lại số chung. */
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'cua_hang' => $BT, 'xoa_rieng' => '1' ) ) );
phep( '🔴 xoa_rieng: Bình Tân về số chung, 43 khách', ! is_wp_error( $r ) && array() === $r['bang_rieng'] && 43 === khh_dt_khach_may( '2026-09-24', $BT )['khach'] );
phep( 'xoa_rieng với "*" -> 400', is_wp_error( khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'cua_hang' => '*', 'xoa_rieng' => '1' ) ) ) ) );
/* Sổ phẳng 1.59.0 vẫn hiểu là bảng chung. */
update_option( 'khh_dt_ve_khach', array( 'Vé Online' => 0, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ) );
phep( 'sổ phẳng cũ tự hiểu là bảng chung "*"', array( '*' => array( 'Vé Online' => 0, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ) ) === khh_dt_ve_khach_so() );
/* 🔴 GỘP MỘT LẦN sổ 1.60–1.64.3 (khai theo quán) về bảng chung: "khai linh tinh rồi quán có quán không". */
update_option( 'khh_dt_ve_khach', array(
	'*'  => array( 'Vé Online' => 0, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ),
	$GV  => array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' => 2, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 3 ),
	'Tutu Train - Estella' => array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM' => 2 ),
) );
update_option( 'khh_dt_ve_phu', array( $GV => array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' => 20000 ) ) );
delete_option( 'khh_dt_ve_gop_1644' );
$n = khh_dt_ve_gop_mot_lan();
$so = khh_dt_ve_khach_so();
phep( '🔴 gộp: 3 số dồn về chung (2 combo + phụ), bảng chung giữ số của mình khi trùng, hết khoá theo quán', 3 === $n && array_keys( $so ) === array( '*' ) && 2 === $so['*']['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH'] && 2 === $so['*']['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM'] && 1 === $so['*']['VÉ TUTU TRAIN: VÉ TRẺ EM'] && 20000 === khh_dt_ve_phu_bang( $CS )['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH'] );
phep( 'gộp chỉ chạy một lần', 0 === khh_dt_ve_gop_mot_lan() && false !== get_option( 'khh_dt_ve_gop_1644', false ) );
phep( 'khh-doanh-thu.php gọi gộp lúc nâng cấp', false !== strpos( preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $goc . '/khh-doanh-thu.php' ) ), 'khh_dt_ve_gop_mot_lan();' ) );
/* Quán nào còn vé chưa khai (để màn nhắc). Sau gộp: Tân Phú còn combo TRẺ EM + NGƯỜI LỚN, VÉ NGƯỜI LỚN -> 2; Gò Vấp còn người lớn -> 1. */
$ct = khh_dt_ve_khach_chua_khai( 3650 );
phep( 'Tân Phú còn 2 loại vé chưa khai, Gò Vấp còn 1', isset( $ct[ $CS ] ) && 2 === $ct[ $CS ] && 1 === $ct[ $GV ] );
$rx = khh_dt_rest_ve_khach_xem( new WP_REST_Request( array( 'cua_hang' => $GV ) ) );
phep( 'REST GET theo cửa hàng: bang, bang_chung, bang_rieng (rỗng), con_thieu', 1 === $rx['bang']['VÉ TUTU TRAIN: VÉ TRẺ EM'] && 0 === $rx['bang_chung']['Vé Online'] && array() === $rx['bang_rieng'] && isset( $rx['con_thieu'][ $CS ] ) );
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => wp_json_encode( array( 'X' => 1 ) ) ) ) );
phep( 'REST POST thiếu cửa hàng thì chối', is_wp_error( $r ) );
/* Sale phụ theo TÊN vé — cùng sổ, REST gửi kèm 'phu'; ghi chung. */
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => '{}', 'phu' => wp_json_encode( array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM' => '15000', 'X' => -1 ) ), 'cua_hang' => '*', 'xem_cua_hang' => $GV ) ) );
phep( 'REST ghi phụ chung theo tên vé (bỏ số âm), quán khác cũng thấy', 15000 === $r['phu_chung']['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM'] && 15000 === khh_dt_ve_phu_bang( $CS )['COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM'] );
$mc = khh_dt_ve_khach_mon_cua( $GV, 3650 );
$m1 = array_values( array_filter( $mc, function ( $x ) { return 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' === $x['ten']; } ) )[0];
phep( 'danh sách món mang phu (20.000 từ gộp), phu_rieng false, phu_nhom (nhóm chưa khai -> null)', 20000 === $m1['phu'] && false === $m1['phu_rieng'] && null === $m1['phu_nhom'] );
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => '{}', 'cua_hang' => '*', 'xem_cua_hang' => $GV ) ) );
phep( 'không gửi phu thì giữ nguyên phụ đã khai', 2 === count( $r['phu_chung'] ) );
/* 🔴 TÊN VÉ lệch dấu cách giữa FABi và bảng khai vẫn phải tra ra (anh Thắng: "Hiện đủ vé. Nhập 2 mà vẫn cứ báo sai"). */
ngay_ban( '2026-09-25', $GV, array( array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN +  THẠCH', 'g' => 'VÉ COMBO.', 'q' => 5, 'r' => 400000 ) ) );
$k = khh_dt_khach_may( '2026-09-25', $GV );
phep( '🔴 FABi ghi tên có hai dấu cách, bảng khai một dấu cách -> vẫn tính 2/vé: 10 khách, không tạm tính', 10 === $k['khach'] && 0 === $k['tam'] );
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => wp_json_encode( array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN +  THẠCH' => 2 ) ), 'cua_hang' => '*', 'xem_cua_hang' => $GV ) ) );
phep( 'ghi lại bằng tên hai dấu cách -> đè đúng dòng đang có, không sinh dòng thứ hai', 1 === count( array_filter( array_keys( $r['bang_chung'] ), function ( $t ) { return false !== strpos( $t, 'THẠCH' ); } ) ) );
$GLOBALS['VHCP_CO_QUYEN'] = false; $GLOBALS['VHCP_DANG_NHAP_WP'] = false;
delete_option( 'khh_dt_ve_phu' );
delete_option( 'khh_dt_ve_khach' );
phep( 'dọn lại bảng khai cho các phép sau', array() === khh_dt_ve_khach_bang() );

/* ── 5 + 1. khai rồi tính ── */
$b = khh_dt_ve_khach_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => '2', 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1, 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => '1' ) );
phep( 'bảng khai ghi số nguyên', array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 2, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1, 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => 1 ) === $b );
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( '🔴 khách máy = 10×2 + 5×1 + 7×1 = 32 chắc, + 3 tạm (Vé Online) = 35', 35 === $k['khach'] && 32 === $k['chac'] && 3 === $k['tam'] && 3 === $k['da_tach'] );
phep( 'vé chưa khai còn đúng "Vé Online"', array( 'Vé Online' => 3.0 ) === array_map( 'floatval', $k['chua_tach'] ) );
$pos = khh_dt_so_pos( '2026-09-22', $CS );
phep( 'so_pos mang khach_may 35, khach_tam 3 và kể "Vé Online" chưa tách', 35 === $pos['khach_may'] && 3 === $pos['khach_tam'] && array( 'Vé Online' ) === $pos['ve_chua_tach'] && 3 === $pos['ve_da_tach'] );

khh_dt_ve_khach_dat( array( 'Vé Online' => 0 ) );
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( 'khai 0 cho vé online: không cộng khách, hết tạm tính, đủ', 32 === $k['khach'] && 0 === $k['tam'] && array() === $k['chua_tach'] && true === $k['du'] );

khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => '' ) );
phep( "'' là xoá tên ấy, tên khác giữ nguyên", ! isset( khh_dt_ve_khach_bang()['VÉ TUTU TRAIN: VÉ TRẺ EM'] ) && 2 === khh_dt_ve_khach_bang()['VÉ TRẺ EM + NGƯỜI LỚN'] );
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( 'xoá xong thì vé ấy lại bị kể là chưa tách: chắc 27 + tạm 5 = 32', 32 === $k['khach'] && 27 === $k['chac'] && 5 === $k['tam'] && array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' ) === array_keys( $k['chua_tach'] ) );
khh_dt_ve_khach_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => -3 ) );
phep( 'số âm bị chối, giữ giá trị cũ', 2 === khh_dt_ve_khach_bang()['VÉ TRẺ EM + NGƯỜI LỚN'] );
khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ) );

/* Ngày không có món -> null, không nổ. */
phep( 'ngày không có số máy -> null', null === khh_dt_khach_may( '2026-09-23', $CS )['khach'] );

/* ── 7. danh sách cho màn Quản trị ── */
$ds = khh_dt_ve_khach_mon_cua( $CS, 3650 );
phep( 'liệt kê đủ 5 món', 5 === count( $ds ) );
phep( 'vé xếp trước, nhiều nhất trước', 'VÉ TRẺ EM + NGƯỜI LỚN' === $ds[0]['ten'] && 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' === $ds[1]['ten'] );
phep( 'món không phải vé nằm cuối', 'Nước suối' === $ds[4]['ten'] && false === $ds[4]['la_ve'] );
phep( 'kèm số đã bán, số đã khai, gợi ý', 10.0 === (float) $ds[0]['so_luong'] && 2 === $ds[0]['khach'] && 2 === $ds[0]['goi_y'] );
phep( 'cơ sở khác chưa bán gì -> rỗng', array() === khh_dt_ve_khach_mon_cua( 'Quán khác', 3650 ) );

/* ── REST ── */
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => 'không phải json', 'cua_hang' => $CS ) ) );
phep( 'REST: bảng hỏng thì chối 400', is_wp_error( $r ) );
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => wp_json_encode( array( 'Nước suối' => '1' ) ), 'cua_hang' => $CS ) ) );
phep( 'REST: khai được cả món không phải vé (nếu quản trị muốn), ghi RIÊNG cho cửa hàng gửi lên', ! is_wp_error( $r ) && 1 === $r['bang']['Nước suối'] && 5 === count( $r['mon'] ) && 1 === khh_dt_ve_khach_bang_rieng( $CS )['Nước suối'] && ! isset( khh_dt_ve_khach_bang()['Nước suối'] ) );
$src = file_get_contents( $goc . '/ve-khach.php' );
$src = preg_replace( '~/\*.*?\*/~s', '', $src );
phep( '🔴 mã plugin KHÔNG dựng `new WP_REST_Request( array(` (kiểu của bộ thử) — WordPress thật nổ 500 (anh Thắng 24/09/2026)', 0 === preg_match( '~new WP_REST_Request\s*\(\s*array~', implode( '', array_map( function ( $f ) { return preg_replace( '~/\*.*?\*/~s', '', file_get_contents( $f ) ); }, glob( $goc . '/*.php' ) ) ) ) );
phep( "🔴 route /ve-khach gác bằng khh_dt_duoc_nap (văn phòng), cả GET lẫn POST", 2 === substr_count( $src, "'permission_callback' => 'khh_dt_duoc_nap'" ) && false === strpos( $src, '__return_true' ) );

/* ── "lúc thì tự lưu, lúc thì không lưu" (anh Thắng 24/09/2026, Estella): tên quán có HAI dấu cách. ── */
$CS_E = 'Tutu Train - Estella ( Dịch vụ  và Giải trí )';   // nguyên văn trong kho POS: hai dấu cách
ngay_ban( '2026-09-23', $CS_E, $MON, 25 );
/* Quán set riêng dưới khoá tên gộp dấu cách -> đọc bằng tên nguyên văn (khoá lỏng) vẫn thấy; ghi thì dồn về khoá nguyên văn. */
update_option( 'khh_dt_ve_khach', array( 'Tutu Train - Estella ( Dịch vụ và Giải trí )' => array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 2 ) ) );
phep( '🔴 bảng riêng lưu dưới khoá thiếu dấu cách vẫn đọc ra bằng tên nguyên văn', 2 === (int) khh_dt_ve_khach_bang( $CS_E )['VÉ TRẺ EM + NGƯỜI LỚN'] && array() !== khh_dt_ve_khach_bang_rieng( $CS_E ) );
khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ), $CS_E );
$so_e = khh_dt_ve_so_cua( 'khh_dt_ve_khach' );
phep( '🔴 ghi bằng tên nguyên văn thì chỉ còn MỘT khoá cho quán, giữ cả số cũ', isset( $so_e[ $CS_E ] ) && ! isset( $so_e['Tutu Train - Estella ( Dịch vụ và Giải trí )'] ) && 2 === (int) $so_e[ $CS_E ]['VÉ TRẺ EM + NGƯỜI LỚN'] && 1 === (int) $so_e[ $CS_E ]['VÉ TUTU TRAIN: VÉ TRẺ EM'] );
/* REST: POST rồi GET với tên gộp dấu cách (như màn gửi) -> trả về tên nguyên văn và thấy số vừa lưu. */
$GLOBALS['VHCP_CO_QUYEN'] = true; $GLOBALS['VHCP_DANG_NHAP_WP'] = true;
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'cua_hang' => 'Tutu Train - Estella ( Dịch vụ và Giải trí )', 'bang' => wp_json_encode( array( 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => 1 ) ), 'phu' => wp_json_encode( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 20000 ) ) ) ) );
phep( '🔴 REST lưu riêng với tên quán gộp dấu cách: trả về tên nguyên văn, khai đủ 3 vé + phụ 20.000', ! is_wp_error( $r ) && $CS_E === $r['cua_hang'] && 1 === (int) $r['bang']['VÉ TUTU TRAIN: VÉ NGƯỜI LỚN'] && 20000 === (int) $r['phu']['VÉ TRẺ EM + NGƯỜI LỚN'] && 1 === (int) $r['bang_rieng']['VÉ TUTU TRAIN: VÉ NGƯỜI LỚN'] );
$r = khh_dt_rest_ve_khach_xem( new WP_REST_Request( array( 'cua_hang' => $CS_E ) ) );
phep( 'GET bằng tên nguyên văn thấy đúng bảng vừa lưu (không còn "chưa khai")', 20000 === (int) $r['phu']['VÉ TRẺ EM + NGƯỜI LỚN'] && 1 === (int) $r['bang']['VÉ TUTU TRAIN: VÉ NGƯỜI LỚN'] );
$GLOBALS['VHCP_CO_QUYEN'] = false; $GLOBALS['VHCP_DANG_NHAP_WP'] = false;

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: vé bóc tách ra khách, combo tính 2, vé chưa khai được kể tên.\n";
