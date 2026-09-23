<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ADMIN BỔ SUNG DOANH THU TỔNG THEO THÁNG — KHÔNG ĐẾM HAI LẦN, KHÔNG LỌT VÀO SỔ NGÀY
 *
 * Anh Thắng 23/09/2026: *"ngày trước đang thiếu. Cho phép admin bổ sung lại doanh thu tổng theo
 * tháng/năm của từng cơ sở trước. Còn bổ sung máy theo ngày thì sau. Chỉ áp dụng admin."*
 *
 * 🔴 HAI CHỖ DỄ SAI NHẤT, cả hai đều câm:
 *    1. Tháng đã có báo cáo ngày mà còn cộng tổng tay → đếm hai lần. Chặn lúc lưu; và nếu dữ liệu
 *       ngày về SAU, `lich_su()` phải BỎ QUA số tay và KÊU — cộng là sai, lặng lẽ bỏ cũng sai.
 *    2. Suy TM/QR từ Tổng: thiếu vế nào suy vế ấy; TM+QR ≠ Tổng thì chối, không tự "sửa" một vế.
 *
 * ⚠️ BỐC THẲNG HÀM RA CHẠY với `$wpdb` giả rồi soi câu SQL + kết quả trộn. Dò chuỗi không nói được
 *    "tháng có dữ liệu ngày thì số tay có bị cộng vào tong_nam không".
 *
 * Chạy: php tools/test/kiem-bo-sung-tong-thang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
class VHG_BaoCao { public static function so_chiso_( $v ) { return null === $v ? null : (float) $v; } }
function current_time( $f ) { return 'Y-m' === $f ? '2026-09' : ( 'Y' === $f ? '2026' : '2026-09-23 10:00:00' ); }
class WpdbGia {
	public $sql = array(); public $co_ngay = 0; public $id_cu = 0; public $insert_id = 77; public $rows = array(); public $bs = array();
	public function prepare( $q, ...$a ) { if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; } foreach ( $a as $v ) { $q = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . $v . "'", $q, 1 ); } return $q; }
	public function get_var( $q ) { $this->sql[] = $q; if ( false !== strpos( $q, 'SHOW TABLES' ) ) { return 'wp_vhg_bc_thang_bs'; } if ( false !== strpos( $q, 'COUNT(*)' ) ) { return $this->co_ngay; } return $this->id_cu; }
	public function get_row( $q, $o = null ) { $this->sql[] = $q; return array( 'coso' => 'GO X', 'thang' => '2026-03', 'tong' => 5 ); }
	public function get_results( $q, $o = null ) { $this->sql[] = $q; return ( false !== strpos( $q, 'bc_thang_bs' ) ) ? $this->bs : $this->rows; }
	public function insert( $t, $d ) { $this->sql[] = 'INSERT ' . $t . ' ' . json_encode( $d, JSON_UNESCAPED_UNICODE ); return 1; }
	public function update( $t, $d, $w ) { $this->sql[] = 'UPDATE ' . $t . ' ' . json_encode( $d, JSON_UNESCAPED_UNICODE ) . ' WHERE ' . json_encode( $w ); return 1; }
	public function delete( $t, $w ) { $this->sql[] = 'DELETE ' . $t . ' ' . json_encode( $w ); return 1; }
}
$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$f1 = boc( $src, 'public static function thang_bs_luu(' ); $f2 = boc( $src, 'public static function thang_bs_xoa(' );
$f3 = boc( $src, 'private static function thang_bs_(' ); $f4 = boc( $src, 'public static function lich_su( ' );
t( 'bốc được thang_bs_luu / xoa / thang_bs_ / lich_su', '' !== $f1 && '' !== $f2 && '' !== $f3 && '' !== $f4 ); if ( '' === $f1 || '' === $f4 ) { exit( 1 ); }
eval( 'class VHG_KeToan { public static function squash( $s ) { return preg_replace( "/[^A-Z0-9]/", "", strtoupper( $s ) ); }
	public static function ngay_( $v ) { return substr( (string) $v, 0, 10 ); } ' . $f1 . "\n" . $f2 . "\n" . $f3 . "\n" . $f4 . ' }' );
global $wpdb;

echo "── Lưu: kiểm dữ liệu vào ──────────────────────────────────────\n";
$wpdb = new WpdbGia();
$r = VHG_KeToan::thang_bs_luu( '', '2026-03', '1000', '', '', '', 'Admin' );          t( 'thiếu cơ sở → chối', empty( $r['ok'] ) );
$r = VHG_KeToan::thang_bs_luu( 'GO X', '3/2026', '1000', '', '', '', 'Admin' );      t( 'tháng sai dạng → chối', empty( $r['ok'] ) );
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-12', '1000', '', '', '', 'Admin' );     t( '🔴 tháng chưa tới → chối', empty( $r['ok'] ) );
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-03', '', '', '', '', 'Admin' );         t( 'không có số nào → chối', empty( $r['ok'] ) );
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-03', '10.000.000', '7.000.000', '2.000.000', '', 'Admin' );
t( '🔴 TM + QR ≠ Tổng → chối, không tự sửa một vế', empty( $r['ok'] ) && false !== strpos( $r['error'], 'phải bằng' ), $r );

echo "── Lưu: suy vế thiếu ──────────────────────────────────────────\n";
$wpdb = new WpdbGia();
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-03', '10.000.000', '', '', 'sổ cũ', 'Admin' );
t( 'chỉ Tổng → TM = Tổng, QR = 0, và NÓI RA trong thông báo', ! empty( $r['ok'] ) && 10000000 === $r['tien_mat'] && 0 === $r['qr'] && false !== strpos( $r['thong_bao'], 'toàn tiền mặt' ), $r );
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-03', '', '7.000.000', '3.000.000', '', 'Admin' );
t( 'TM + QR không Tổng → Tổng = 10.000.000', ! empty( $r['ok'] ) && 10000000 === $r['tong'] );
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-03', '10.000.000', '', '4.000.000', '', 'Admin' );
t( 'Tổng + QR → TM = 6.000.000', ! empty( $r['ok'] ) && 6000000 === $r['tien_mat'] );
t( 'dòng mới → INSERT vào bc_thang_bs kèm ai + lúc', (bool) preg_grep( '/^INSERT wp_vhg_bc_thang_bs .*"boi":"Admin".*"luc":"2026-09-23/', $wpdb->sql ) );
$wpdb = new WpdbGia(); $wpdb->id_cu = 12;
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-03', '5.000.000', '', '', '', 'Admin' );
t( 'đã có dòng cùng cơ sở × tháng → UPDATE (ghi đè), không chèn dòng hai', (bool) preg_grep( '/^UPDATE wp_vhg_bc_thang_bs .*WHERE \{"id":12\}/', $wpdb->sql ) && ! preg_grep( '/^INSERT/', $wpdb->sql ) );

echo "── Lưu: KHÔNG chồng lên tháng đã có dữ liệu ngày ─────────────\n";
$wpdb = new WpdbGia(); $wpdb->co_ngay = 41;
$r = VHG_KeToan::thang_bs_luu( 'GO X', '2026-08', '10.000.000', '', '', '', 'Admin' );
t( '🔴 tháng đã có 41 dòng báo cáo ngày → chối, kể đúng con số', empty( $r['ok'] ) && false !== strpos( $r['error'], '41 dòng' ), $r );
t( '   và không INSERT/UPDATE gì', ! preg_grep( '/^(INSERT|UPDATE)/', $wpdb->sql ) );

echo "── lich_su(): trộn số bổ sung vào năm ─────────────────────────\n";
$wpdb = new WpdbGia();
$wpdb->rows = array(   // tháng 08 có dữ liệu ngày thật
	array( 'coso' => 'GO X', 'ngay' => '2026-08-05', 'ma_may' => '80013', 'ten' => 'GX-1', 'chi_so_truoc' => 10, 'chi_so_sau' => 20, 'actual' => 100000, 'tien_mat' => 60000, 'qr' => 40000, 'tong' => 100000 ),
);
$wpdb->bs = array(
	array( 'id' => 1, 'coso_key' => 'GOX', 'coso' => 'GO X', 'thang' => '2026-03', 'tong' => 10000000, 'tien_mat' => 10000000, 'qr' => 0, 'ghi_chu' => 'sổ cũ', 'boi' => 'Admin', 'luc' => '2026-09-23 10:00:00' ),
	array( 'id' => 2, 'coso_key' => 'GOX', 'coso' => 'GO X', 'thang' => '2026-08', 'tong' => 999999, 'tien_mat' => 999999, 'qr' => 0, 'ghi_chu' => '', 'boi' => 'Admin', 'luc' => '' ),  // chồng tháng có ngày
);
$r = VHG_KeToan::lich_su( 'GO X', '2026' );
$theo = array(); foreach ( $r['thang'] as $T ) { $theo[ $T['thang'] ] = $T; }
t( '🔴 tháng 03 (không có ngày) xuất hiện từ số bổ sung, cờ bo_sung, 0 ghế 0 ngày',
	isset( $theo['2026-03'] ) && 10000000 === $theo['2026-03']['tong'] && 1 === count( $theo['2026-03']['bo_sung'] ) && 0 === $theo['2026-03']['so_ghe'] && 0 === $theo['2026-03']['so_ngay'], isset( $theo['2026-03'] ) ? $theo['2026-03']['tong'] : null );
t( '🔴 tháng 08 (đã có ngày): số bổ sung 999.999 bị BỎ QUA, tổng tháng vẫn = dữ liệu ngày',
	100000 === $theo['2026-08']['tong'] && 1 === count( $theo['2026-08']['bs_bo_qua'] ) && empty( $theo['2026-08']['bo_sung'] ), $theo['2026-08']['tong'] );
t( '🔴 tong_nam = ngày thật + bổ sung hợp lệ, KHÔNG có số bị bỏ qua (100.000 + 10.000.000)', 10100000 === $r['tong_nam']['tong'], $r['tong_nam'] );
t( 'trả danh sách bs_bo_qua ở gốc để màn hình kêu', 1 === count( $r['bs_bo_qua'] ) && 2 === $r['bs_bo_qua'][0]['id'] );
t( 'tháng mới nhất lên trước (08 trước 03)', '2026-08' === $r['thang'][0]['thang'] );

echo "── Cổng: chỉ admin ────────────────────────────────────────────\n";
$tr = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );
t( '🔴 kt_thang_bs_luu/xoa kiểm la_quan_tri() TRƯỚC khi gọi', (bool) preg_match( "/'kt_thang_bs_luu' === \\\$viec \|\| 'kt_thang_bs_xoa' === \\\$viec[^}]*?if \( ! VHG_Auth::la_quan_tri\( \\\$ai\['role'\] \) \)/s", $tr ) );
t( 'form chỉ vẽ khi QUAN_TRI() (và máy chủ kiểm lại)', (bool) preg_match( "/\(QUAN_TRI\(\) \? \('<details id=\"kls-bs\"/", $tr ) );
t( 'MISA / Báo cáo tổng KHÔNG đọc bảng bổ sung (chỉ lich_su đọc thang_bs_)', 1 === substr_count( $src, 'self::thang_bs_(' ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
