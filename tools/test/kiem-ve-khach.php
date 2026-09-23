<?php
/**
 * BÓC TÁCH VÉ → KHÁCH VÀO — CHẠY THẬT.
 *
 * Anh Thắng 23/09/2026: *"nếu vé là combo VÉ TRẺ EM + NGƯỜI LỚN tính là 2 người, còn nếu nó là trẻ
 * hoặc người lớn riêng thì là 1 người"* — *"bóc tách sẵn cho nhân viên, áp dụng cho gian Tàu trước"*.
 *
 * Chốt:
 *   1. 🔴 Khách máy = Σ số vé × khách mỗi vé; combo tính 2, vé lẻ tính 1; món không khai bỏ qua.
 *   2. 🔴 Chưa khai vé nào khớp -> khách là NULL (chưa bóc tách), không phải 0.
 *   3. 🔴 Vé có bán mà chưa khai phải được KỂ TÊN — cộng thiếu một loại là lệch đổ oan cho nhân viên.
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
phep( 'chữ "ve" nằm giữa từ khác không phải vé', null === khh_dt_ve_khach_goi_y( 'Cà phê Việt', 'ĐỒ UỐNG' ) );

/* ── 2 + 3. chưa khai ── */
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( '🔴 chưa khai vé nào -> khách máy là NULL, không phải 0', null === $k['khach'] && 0 === $k['da_tach'] );
phep( '🔴 kể tên đủ 4 loại vé có bán mà chưa khai (không kể Nước suối)',
	array( 'VÉ TRẺ EM + NGƯỜI LỚN', 'VÉ TUTU TRAIN: VÉ TRẺ EM', 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN', 'Vé Online' ) === array_keys( $k['chua_tach'] ) );
$pos = khh_dt_so_pos( '2026-09-22', $CS );
phep( 'so_pos: khach_may null khi chưa bóc tách', array_key_exists( 'khach_may', $pos ) && null === $pos['khach_may'] );
phep( 'so_pos: vẫn có so_ve như cũ', 25.0 === (float) $pos['so_ve'] );

/* ── 5 + 1. khai rồi tính ── */
$b = khh_dt_ve_khach_dat( array( 'VÉ TRẺ EM + NGƯỜI LỚN' => '2', 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1, 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => '1' ) );
phep( 'bảng khai ghi số nguyên', array( 'VÉ TRẺ EM + NGƯỜI LỚN' => 2, 'VÉ TUTU TRAIN: VÉ TRẺ EM' => 1, 'VÉ TUTU TRAIN: VÉ NGƯỜI LỚN' => 1 ) === $b );
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( '🔴 khách máy = 10×2 + 5×1 + 7×1 = 32', 32 === $k['khach'] && 3 === $k['da_tach'] );
phep( 'vé chưa khai còn đúng "Vé Online"', array( 'Vé Online' => 3.0 ) === array_map( 'floatval', $k['chua_tach'] ) );
$pos = khh_dt_so_pos( '2026-09-22', $CS );
phep( 'so_pos mang khach_may = 32 và kể "Vé Online" chưa tách', 32 === $pos['khach_may'] && array( 'Vé Online' ) === $pos['ve_chua_tach'] && 3 === $pos['ve_da_tach'] );

khh_dt_ve_khach_dat( array( 'Vé Online' => 0 ) );
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( 'khai 0 cho vé online: không cộng khách, và hết bị kể là chưa tách', 32 === $k['khach'] && array() === $k['chua_tach'] );

khh_dt_ve_khach_dat( array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' => '' ) );
phep( "'' là xoá tên ấy, tên khác giữ nguyên", ! isset( khh_dt_ve_khach_bang()['VÉ TUTU TRAIN: VÉ TRẺ EM'] ) && 2 === khh_dt_ve_khach_bang()['VÉ TRẺ EM + NGƯỜI LỚN'] );
$k = khh_dt_khach_may( '2026-09-22', $CS );
phep( 'xoá xong thì vé ấy lại bị kể là chưa tách và khách bớt 5', 27 === $k['khach'] && array( 'VÉ TUTU TRAIN: VÉ TRẺ EM' ) === array_keys( $k['chua_tach'] ) );
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
phep( 'REST: khai được cả món không phải vé (nếu quản trị muốn)', ! is_wp_error( $r ) && 1 === $r['bang']['Nước suối'] && 5 === count( $r['mon'] ) );
$src = file_get_contents( $goc . '/ve-khach.php' );
$src = preg_replace( '~/\*.*?\*/~s', '', $src );
phep( "🔴 route /ve-khach gác bằng khh_dt_duoc_nap (văn phòng), cả GET lẫn POST", 2 === substr_count( $src, "'permission_callback' => 'khh_dt_duoc_nap'" ) && false === strpos( $src, '__return_true' ) );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: vé bóc tách ra khách, combo tính 2, vé chưa khai được kể tên.\n";
