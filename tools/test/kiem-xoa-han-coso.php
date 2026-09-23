<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * XOÁ HẲN CƠ SỞ: GHẾ ẨN XOÁ THEO, GHẾ SỐNG CHẶN, SỔ GIỮ NGUYÊN — VÀ CƠ SỞ ĐÓNG CỬA HẾT DÒNG 0đ
 *
 * Anh Thắng 23/09/2026: *"một số cơ sở đã ẩn, nhưng khi xuất misa vẫn nhảy vào, nên anh cần xoá
 * hẳn điểm đó luôn"* — kèm ảnh ghế 80107 · CGV-PLZ-01 · CGV PEAR PLAZA · "đã ẩn".
 *
 * 🔴 GỐC. `xoa_coso()` đếm CẢ ghế đã ẩn → cơ sở còn một ghế ẩn là bị chối "còn 1 ghế". Ghế ẩn thì
 *    từ 2.115.0 không ẩn/xoá mềm được nữa, xoá hẳn ghế lại ở màn khác. Anh đi vòng không ra, và
 *    cơ sở "đã ẩn" vẫn nằm trong danh mục nên Báo cáo tổng vẫn in nó thành một dòng 0đ.
 *
 * 🔴 HAI CHỐT KHÔNG ĐƯỢC ĐỔI CHIỀU:
 *    1. Ghế ĐANG CHẠY vẫn chặn tuyệt đối — xoá cơ sở còn ghế sống là cả loạt rơi khỏi màn nhập.
 *    2. Chỉ xoá DANH MỤC (coso · bc_ma_misa · ghế ẩn), KHÔNG xoá SỔ (bc · bc_dong · thu · chot).
 *       Tổng tiền các tháng đã chốt không được đổi vì một cú dọn danh mục.
 *
 * ⚠️ BỐC THẲNG HÀM RA CHẠY với `$wpdb` giả rồi soi CHÍNH câu SQL nó bắn đi — "không xoá sổ" là
 *    phát biểu về câu DELETE nào KHÔNG được xuất hiện, dò chuỗi mã nguồn không nói được điều đó.
 *
 * Chạy: php tools/test/kiem-xoa-han-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; }
	$TRUOT[] = $ten;
	echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n";
}

define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }
class VHG_BaoCao { public static function squash( $s ) { return preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $s ) ); } }

class WpdbGia {
	public $sql = array(); public $coso = null; public $ghe = array(); public $so = array();
	public function prepare( $sql, ...$a ) {
		if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
		foreach ( $a as $v ) { $sql = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . $v . "'", $sql, 1 ); }
		return $sql;
	}
	public function get_row( $sql, $o = null ) { $this->sql[] = $sql; return $this->coso; }
	public function get_results( $sql, $o = null ) { $this->sql[] = $sql; return $this->ghe; }
	public function get_var( $sql ) { $this->sql[] = $sql; return array_shift( $this->so ); }
	public function query( $sql ) { $this->sql[] = $sql; return 1; }
	public function delete( $t, $w ) { $this->sql[] = 'DELETE-API ' . $t . ' ' . json_encode( $w ); return 1; }
}

$nguon = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
function boc( $nguon, $mo ) {
	$i = strpos( $nguon, $mo ); if ( false === $i ) { return ''; }
	$d = 0; $n = strlen( $nguon );
	for ( $k = strpos( $nguon, '{', $i ); $k < $n; $k++ ) {
		if ( '{' === $nguon[ $k ] ) { $d++; } elseif ( '}' === $nguon[ $k ] && 0 === --$d ) { return substr( $nguon, $i, $k - $i + 1 ); }
	}
	return '';
}
$f = boc( $nguon, 'public static function xoa_han_coso(' );
t( 'bốc được xoa_han_coso', '' !== $f );
if ( '' === $f ) { echo "✗ dừng\n"; exit( 1 ); }
eval( 'class VHG_May { private static function quen_dem_reset_() {} ' . $f . ' }' );

function dung( $ghe, $so, $that ) {
	global $wpdb;
	$wpdb = new WpdbGia();
	$wpdb->coso = array( 'id' => 9, 'ten' => 'CGV Pear Plaza', 'dong_cua' => 1 );
	$wpdb->ghe = $ghe; $wpdb->so = $so;
	$r = VHG_May::xoa_han_coso( 9, $that );
	$del = array_values( array_filter( $wpdb->sql, function ( $q ) { return 0 === strpos( $q, 'DELETE' ); } ) );
	return array( $r, $del );
}

echo "── Chốt 1: ghế đang chạy CHẶN, ghế ẩn KHÔNG chặn ─────────────\n";
list( $r, $del ) = dung( array( array( 'ma' => '80107', 'an' => 1 ), array( 'ma' => '80108', 'an' => 0 ) ), array( 3, 500000, 1 ), true );
t( '🔴 còn ghế ĐANG CHẠY → chối, kể đúng mã ghế sống', empty( $r['ok'] ) && false !== strpos( $r['error'], '80108' ), $r );
t( '   và KHÔNG bắn một câu DELETE nào', 0 === count( $del ), $del );

list( $r, $del ) = dung( array( array( 'ma' => '80107', 'an' => 1 ) ), array( 3, 500000, 1 ), false );
t( '🔴 chỉ còn ghế ẨN → xem trước OK, kể ghế ẩn + số báo cáo còn trong sổ',
	! empty( $r['ok'] ) && ! empty( $r['xem_truoc'] ) && array( '80107' ) === $r['ghe_an'] && 3 === (int) $r['so_bao_cao'] && 500000 === (int) $r['tien_bao_cao'], $r );
t( '   xem trước KHÔNG xoá gì', 0 === count( $del ), $del );

echo "── Chốt 2: xoá thật chỉ đụng DANH MỤC, không đụng SỔ ──────────\n";
list( $r, $del ) = dung( array( array( 'ma' => '80107', 'an' => 1 ) ), array( 3, 500000, 1 ), true );
$tat = implode( "\n", $del );
t( '🔴 xoá thật: OK, báo đã xoá 1 ghế ẩn theo', ! empty( $r['ok'] ) && ! empty( $r['da_xoa'] ) && 1 === (int) $r['da_xoa_ghe'], $r );
t( '🔴 xoá ghế ẩn có điều kiện an=1 VÀ đúng coso_id (không xoá lạc ghế cùng mã nơi khác)',
	false !== strpos( $tat, "WHERE ma='80107' AND coso_id=9 AND an=1" ), $del );
t( 'xoá dòng bc_ma_misa theo coso_key', false !== strpos( $tat, 'wp_vhg_bc_ma_misa' ) && false !== strpos( $tat, 'CGVPEARPLAZA' ), $del );
t( 'xoá dòng coso theo id', false !== strpos( $tat, 'wp_vhg_coso {"id":9}' ), $del );
t( '🔴 KHÔNG có câu DELETE nào chạm bc / bc_dong / thu / chot / nop (sổ giữ nguyên)',
	false === strpos( $tat, 'wp_vhg_bc ' ) && false === strpos( $tat, 'wp_vhg_bc_dong' ) && false === strpos( $tat, 'wp_vhg_thu' )
	&& false === strpos( $tat, 'wp_vhg_chot' ) && false === strpos( $tat, 'wp_vhg_nop' ), $del );
t( 'thông báo nói rõ báo cáo cũ còn trong sổ và ĐỪNG tạo lại trùng tên',
	false !== strpos( $r['thong_bao'], '3 báo cáo cũ' ) && false !== strpos( $r['thong_bao'], 'ĐỪNG tạo lại' ), $r['thong_bao'] );

echo "── Báo cáo tổng: cơ sở ĐÓNG CỬA hết dòng 0đ, có tiền thì vẫn hiện \n";
$kt = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$i = strpos( $kt, "\t\t\$ds_cs = array();\n\t\tforeach ( array_keys( \$ma_kh ) as \$cs ) {" );
$j = strpos( $kt, "\t\tsort( \$ds_cs );", $i );
t( 'bốc được khối dựng danh sách cơ sở của Báo cáo tổng', false !== $i && $j > $i );
$khoi = substr( $kt, $i, $j - $i );
$loc = eval( 'return function ( $ma_kh, $dong, $o, $muc ) { ' . $khoi . ' sort( $ds_cs ); return $ds_cs; };' );
$ma_kh = array( 'A MỞ' => '', 'B ĐÓNG KHÔNG TIỀN' => '', 'C ĐÓNG CÓ TIỀN' => '' );
$dong  = array( 'B ĐÓNG KHÔNG TIỀN' => true, 'C ĐÓNG CÓ TIỀN' => true );
$o     = array( 'C ĐÓNG CÓ TIỀN' => array( '2026-09-01' => 5000 ), 'D CHỈ CÓ TRONG SỔ' => array( '2026-09-01' => 1 ) );
$ds = $loc( $ma_kh, $dong, $o, 'coso' );
t( '🔴 cơ sở đóng cửa KHÔNG có tiền trong kỳ → bỏ khỏi bảng (hết dòng 0đ)', ! in_array( 'B ĐÓNG KHÔNG TIỀN', $ds, true ), $ds );
t( '🔴 cơ sở đóng cửa mà CÓ tiền trong kỳ → VẪN HIỆN (tiền thật không bảng nào được giấu)', in_array( 'C ĐÓNG CÓ TIỀN', $ds, true ), $ds );
t( 'cơ sở đang mở không có tiền → vẫn hiện (luật cũ: chỗ không ra tiền là thứ đáng thấy)', in_array( 'A MỞ', $ds, true ), $ds );
t( 'cơ sở chỉ còn trong sổ (đã xoá khỏi danh mục) mà có tiền → vẫn hiện', in_array( 'D CHỈ CÓ TRONG SỔ', $ds, true ), $ds );
$ds2 = $loc( $ma_kh, $dong, array( 'C ĐÓNG CÓ TIỀN|80107' => array( '2026-09-01' => 5000 ) ), 'ghe' );
t( 'gộp theo TỪNG GHẾ: khoá "cơ sở|mã" vẫn tách đúng tên cơ sở để xét', in_array( 'C ĐÓNG CÓ TIỀN', $ds2, true ) && ! in_array( 'B ĐÓNG KHÔNG TIỀN', $ds2, true ), $ds2 );

echo "\n";
if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . '/' . ( $DAT + count( $TRUOT ) ) . "\n"; exit( 1 ); }
echo "✓ SẠCH — $DAT phép\n";
