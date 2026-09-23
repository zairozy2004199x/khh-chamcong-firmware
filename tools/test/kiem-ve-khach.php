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
phep( '"VÉ GIA ĐÌNH: 2 NGƯỜI LỚN + 1 TRẺ EM" đếm được 2 cụm -> 2 (số đứng trước không đọc, quản trị sửa tay)', 2 === khh_dt_ve_khach_goi_y( 'VÉ GIA ĐÌNH: 2 NGƯỜI LỚN + 1 TRẺ EM' ) );
phep( 'chữ "ve" nằm giữa từ khác không phải vé', null === khh_dt_ve_khach_goi_y( 'Cà phê Việt', 'ĐỒ UỐNG' ) );

/* ── 2 + 3. chưa khai: tạm tính 1 khách/vé ── */
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( '🔴 chưa khai vé nào -> tạm tính 1 khách/vé = 10+5+7+3 = 25, chắc 0, chưa đủ', 25 === $k['khach'] && 25 === $k['tam'] && 0 === $k['chac'] && 0 === $k['da_tach'] && false === $k['du'] );
phep( '🔴 kể tên đủ 4 loại vé có bán mà chưa khai (không kể Nước suối)',
	array( 'VÉ TRẺ EM + NGƯỜI LỚN', 'VÉ TUTU TRAIN: VÉ TRẺ EM', 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN', 'Vé Online' ) === array_keys( $k['chua_tach'] ) );
$pos = khh_dt_so_pos( '2026-09-22', $CS );
phep( 'so_pos: khach_may 25 kèm khach_tam 25 để màn ghi "tạm tính"', 25 === $pos['khach_may'] && 25 === $pos['khach_tam'] );
phep( 'so_pos: vẫn có so_ve như cũ', 25.0 === (float) $pos['so_ve'] );
/* Ngày chỉ bán đồ uống, không có vé nào -> NULL (không phải 0). */
ngay_ban( '2026-09-21', $CS, array( array( 'n' => 'Nước suối', 'g' => 'ĐỒ UỐNG', 'q' => 3, 'r' => 30000 ) ) );
phep( '🔴 ngày không có vé nào -> khách máy NULL, không phải 0', null === khh_dt_khach_may( '2026-09-21', $CS )['khach'] );
/* 🔴 Đúng ca Lotte Gò Vấp 23/09/2026 — KHAI RIÊNG CHO GÒ VẤP (anh Thắng: "mỗi cửa hàng 1 cấu hình"):
   vé lẻ đã khai (1), hai combo chưa khai -> 12 + 2 + 10 + 4 = 28. */
$GV = 'TuTu Train - Lotte Gò Vấp';
khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1, 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => 1 ), $GV );
ngay_ban( '2026-09-23', $GV, array(
	array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH',  'g' => 'VÉ COMBO.', 'q' => 10, 'r' => 800000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ TRẺ EM',                      'g' => 'VÉ LẺ.',    'q' => 12, 'r' => 480000 ),
	array( 'n' => 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM','g' => 'VÉ COMBO.', 'q' => 4,  'r' => 320000 ),
	array( 'n' => 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN',                   'g' => 'VÉ LẺ.',    'q' => 2,  'r' => 100000 ),
	array( 'n' => 'NƯỚC SUỐI DANASI',                              'g' => 'ĐÓNG SẴN',  'q' => 7,  'r' => 70000 ),
) );
$k = khh_dt_khach_may( '2026-09-23', $GV );
phep( '🔴 Gò Vấp: combo chưa khai tạm 1/vé -> 28, không phải 14', 28 === $k['khach'] && 14 === $k['tam'] );
khh_dt_ve_khach_dat( array( 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + THẠCH' => 2, 'COMBO TUTU TRAIN: TRẺ EM + NGƯỜI LỚN + BIM BIM' => 2 ), $GV );
$k = khh_dt_khach_may( '2026-09-23', $GV );
phep( 'khai combo = 2 -> 20 + 12 + 8 + 2 = 42, đủ', 42 === $k['khach'] && 0 === $k['tam'] && true === $k['du'] );
/* 🔴 KHAI RIÊNG KHÔNG LẪN SANG QUÁN KHÁC: Tân Phú cùng bán "VÉ TUTU TRAIN: VÉ TRẺ EM" mà bảng của Tân Phú vẫn rỗng. */
phep( '🔴 bảng của Tân Phú không thấy gì Gò Vấp khai', array() === khh_dt_ve_khach_bang( $CS ) );
phep( 'bảng riêng của Gò Vấp có 4 vé', 4 === count( khh_dt_ve_khach_bang_rieng( $GV ) ) );
/* Bảng CHUNG ('*', bản 1.59.0 lưu phẳng) làm mặc định cho quán chưa khai riêng, và bảng riêng đè lên. */
update_option( 'khh_dt_ve_khach', array( 'Vé Online' => 0, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ) );   // dạng phẳng cũ
phep( 'sổ phẳng cũ tự hiểu là bảng chung "*"', array( '*' => array( 'Vé Online' => 0, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ) ) === khh_dt_ve_khach_so() );
phep( 'quán chưa khai riêng thừa bảng chung', array( 'Vé Online' => 0, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1 ) === khh_dt_ve_khach_bang( $CS ) );
khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 3 ), $GV );
phep( 'bảng riêng đè lên bảng chung ở quán ấy, quán khác giữ chung', 3 === khh_dt_ve_khach_bang( $GV )['VÉ TUTU TRAIN: VÉ TRẺ EM'] && 1 === khh_dt_ve_khach_bang( $CS )['VÉ TUTU TRAIN: VÉ TRẺ EM'] );
phep( 'sổ chung không bị mất khi ghi riêng', isset( khh_dt_ve_khach_so()['*'] ) && isset( khh_dt_ve_khach_so()[ $GV ] ) );
/* Quán nào còn vé chưa khai (để màn nhắc đổi ô chọn cửa hàng). */
$ct = khh_dt_ve_khach_chua_khai( 3650 );
/* Bước "sổ phẳng cũ" ở trên đã ghi đè cả sổ: Gò Vấp mất 4 vé riêng, chỉ khai lại 1 -> còn 3 (2 combo + người lớn). */
phep( 'Tân Phú còn 2 loại vé chưa khai (combo + người lớn), Gò Vấp còn 3', isset( $ct[ $CS ] ) && 2 === $ct[ $CS ] && 3 === $ct[ $GV ] );
$rx = khh_dt_rest_ve_khach_xem( new WP_REST_Request( array( 'cua_hang' => $GV ) ) );
phep( 'REST GET theo cửa hàng: bang (riêng đè chung), bang_rieng, con_thieu', 3 === $rx['bang']['VÉ TUTU TRAIN: VÉ TRẺ EM'] && 0 === $rx['bang']['Vé Online'] && array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 3 ) === $rx['bang_rieng'] && isset( $rx['con_thieu'][ $CS ] ) && 3 === $rx['con_thieu'][ $GV ] );
$r = khh_dt_rest_ve_khach_dat( new WP_REST_Request( array( 'bang' => wp_json_encode( array( 'X' => 1 ) ) ) ) );
phep( '🔴 REST POST thiếu cửa hàng thì chối (khai riêng từng quán)', is_wp_error( $r ) );
$mc = khh_dt_ve_khach_mon_cua( $GV, 3650 );
$m0 = array_values( array_filter( $mc, function ( $x ) { return 'VÉ TUTU TRAIN: VÉ TRẺ EM' === $x['ten']; } ) )[0];
phep( 'danh sách món của quán cắm cờ rieng cho ô quán tự khai', true === $m0['rieng'] && 3 === $m0['khach'] );
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
phep( 'REST: khai được cả món không phải vé (nếu quản trị muốn), ghi RIÊNG cho cửa hàng gửi lên', ! is_wp_error( $r ) && 1 === $r['bang']['Nước suối'] && 5 === count( $r['mon'] ) && 1 === khh_dt_ve_khach_bang_rieng( $CS )['Nước suối'] );
$src = file_get_contents( $goc . '/ve-khach.php' );
$src = preg_replace( '~/\*.*?\*/~s', '', $src );
phep( "🔴 route /ve-khach gác bằng khh_dt_duoc_nap (văn phòng), cả GET lẫn POST", 2 === substr_count( $src, "'permission_callback' => 'khh_dt_duoc_nap'" ) && false === strpos( $src, '__return_true' ) );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: vé bóc tách ra khách, combo tính 2, vé chưa khai được kể tên.\n";
