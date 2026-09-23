<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỐC GỬI XIN TẠM ỨNG (`don.ngay_gui`) — ghi lúc bấm Gửi, đọc ra `guiLuc` xếp được.
 *
 * Anh Thắng 23/09/2026: *"Sắp xếp theo người gửi, Ai Gửi Sớm nhất nằm trên"*. Sổ đơn trước đó
 * không có mốc "gửi xin tạm ứng" (chỉ có ngày tạo · ngày duyệt · ngày gửi quyết toán…), nên
 * thêm cột, sơ đồ lên 1.16.0.
 *
 * 🔴 CHẠY THẬT trên sổ giả (wp-stub): lập đơn → gửi → đọc lại `ngay_gui` và `guiLuc`.
 * 🔴 ĐƠN CŨ (chưa có mốc) lui về NGÀY TẠO, không rỗng — rỗng là cả trăm đơn cũ rơi xuống cuối
 *    bảng theo thứ tự tuỳ ý.
 *
 * Chạy: php tools/test/kiem-ngay-gui-don.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 1. SƠ ĐỒ ═══════════════════════════════════════════════════════════════════════════ */
$db = (string) file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-db.php' );
$i  = strpos( $db, "self::t( 'don' ) . \" (" );
$don_ddl = false === $i ? '' : substr( $db, $i, strpos( $db, ') $c";', $i ) - $i );
t( '🔴 bảng `don` có cột `ngay_gui DATETIME`', false !== strpos( $don_ddl, 'ngay_gui DATETIME NULL' ) );
t( '🔴 `SCHEMA_VERSION` >= 1.16.0 (không nâng là cột không mọc trên site đang chạy)',
	version_compare( VHCP_DB::SCHEMA_VERSION, '1.16.0', '>=' ), VHCP_DB::SCHEMA_VERSION );

/* ═══ 2. `gui_luc_xep()` — lui đúng bậc ══════════════════════════════════════════════════ */
teq( 'có `ngay_gui` → lấy nó', '2026-09-21 09:10:00', VHCP_Don::gui_luc_xep( array( 'ngay_gui' => '2026-09-21 09:10:00', 'ngay_tao' => '2026-09-20 08:00:00' ) ) );
teq( '🔴 đơn cũ không có `ngay_gui` → lui về NGÀY TẠO, không rỗng', '2026-09-20 08:00:00', VHCP_Don::gui_luc_xep( array( 'ngay_tao' => '2026-09-20 08:00:00' ) ) );
teq( '   `ngay_gui` rỗng cũng lui về ngày tạo', '2026-09-20 08:00:00', VHCP_Don::gui_luc_xep( array( 'ngay_gui' => '', 'ngay_tao' => '2026-09-20 08:00:00' ) ) );
teq( '   `0000-00-00…` coi như rỗng', '2026-09-20 08:00:00', VHCP_Don::gui_luc_xep( array( 'ngay_gui' => '0000-00-00 00:00:00', 'ngay_tao' => '2026-09-20 08:00:00' ) ) );
teq( '   không mốc nào → chuỗi rỗng', '', VHCP_Don::gui_luc_xep( array() ) );
t( '   trả NGUYÊN chuỗi Y-m-d H:i:s (so chuỗi = so thời gian), không đổi sang d/m/Y',
	1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', VHCP_Don::gui_luc_xep( array( 'ngay_gui' => '2026-09-21 09:10:00' ) ) ) );

/* ═══ 3. 🔴 CHẠY THẬT: lập → gửi → có mốc ══════════════════════════════════════════════ */
vhcp_test_dat_gio( '2026-09-21 09:10:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$r = VHCP_Don::tao_don_moi( 'T9/2026 (21/9-27/9/2026)', 'Huỳnh Thị Thu Thảo' );
t( 'lập được đơn', ! empty( $r['success'] ) && ! empty( $r['maDon'] ), $r );
$ma = (string) $r['maDon'];
global $wpdb;
$t_tu = VHCP_DB::t( 'tamung' );
$wpdb->insert( $t_tu, array( 'ma_don' => $ma, 'coso' => 'TÀU GÒ VẤP', 'so' => 64000 ) );
$truoc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . VHCP_DB::t( 'don' ) . " WHERE ma_don=%s", $ma ), ARRAY_A );
teq( '   trước khi gửi: `ngay_gui` trống', '', trim( (string) ( isset( $truoc['ngay_gui'] ) ? $truoc['ngay_gui'] : '' ) ) );
$g = VHCP_Don::gui_duyet_tam_ung( $ma );
t( 'gửi được', ! empty( $g['success'] ), $g );
$sau = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . VHCP_DB::t( 'don' ) . " WHERE ma_don=%s", $ma ), ARRAY_A );
teq( '   trạng thái sang Chờ duyệt tạm ứng', 'Chờ duyệt tạm ứng', (string) $sau['trang_thai'] );
t( '🔴 sau khi gửi: `ngay_gui` có mốc dạng Y-m-d H:i:s', 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $sau['ngay_gui'] ), $sau['ngay_gui'] );
/* Và danh sách đơn đọc ra đúng mốc ấy. */
$ds = VHCP_Don::list_dons();
$hang = null;
foreach ( (array) $ds as $x ) { if ( isset( $x['maDon'] ) && $x['maDon'] === $ma ) { $hang = $x; break; } }
t( 'danh sách đơn có đơn vừa gửi', null !== $hang, count( (array) $ds ) );
teq( '🔴 danh sách mang `guiLuc` = đúng `ngay_gui` vừa ghi', (string) $sau['ngay_gui'], null !== $hang && isset( $hang['guiLuc'] ) ? $hang['guiLuc'] : null );

/* Thứ tự ghi: đổi trạng thái TRƯỚC, mốc gửi là câu RIÊNG sau — site chưa kịp dbDelta thì chỉ mất mốc. */
$src = (string) file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
$i   = strpos( $src, 'function gui_duyet_tam_ung(' );
$than = substr( $src, $i, strpos( $src, "\n\t}\n", $i ) - $i );
$than_sach = preg_replace( '#/\*.*?\*/#su', '', $than );
t( '🔴 mốc gửi ghi bằng câu RIÊNG, không gộp vào câu đổi trạng thái',
	1 === preg_match( "/upd_don\(\s*\\\$ma_don,\s*array\(\s*'trang_thai'\s*=>\s*'Chờ duyệt tạm ứng'\s*\)\s*\)/u", $than_sach )
	&& 1 === preg_match( "/upd_don\(\s*\\\$ma_don,\s*array\(\s*'ngay_gui'\s*=>\s*VHCP_Util::now_sql\(\)\s*\)\s*\)/u", $than_sach ), $than_sach );
t( '   và câu trạng thái đứng TRƯỚC câu mốc', strpos( $than_sach, "'trang_thai' => 'Chờ duyệt tạm ứng'" ) < strpos( $than_sach, "'ngay_gui' => VHCP_Util::now_sql()" ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: bấm Gửi là có mốc `ngay_gui`, đọc ra `guiLuc`; đơn cũ lui về ngày tạo.\n";
