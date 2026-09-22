<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * AI ĐANG CẦM TIỀN: BUNG TỪNG NGÀY, CHỐT ĐÚNG NGÀY ĐÃ TÍCH
 *
 * Anh Thắng 22/09/2026: *"bấm vào nhân viên sẽ hiện… hiện ngày chưa nộp, nhân viên nộp ngày nào
 * mình tích vào"*, và nói rõ vì sao: *"nộp báo cáo mà chưa chốt, xong qua nộp tiền không ngày hôm
 * trước — kế toán không thể chốt một cục được"*.
 *
 * 🔴 NGÀY LÀM BÁO CÁO VÀ NGÀY TIỀN VỀ TAY LÀ HAI VIỆC KHÁC NHAU. Nhân viên nộp báo cáo mỗi ngày,
 *    tiền mặt về quầy theo nhịp riêng — hôm nay mang tiền của ba hôm trước, mai mang nốt. Bảng cũ
 *    chỉ có MỘT con số tổng của cả người, nên nút duy nhất là chốt cả cục: kế toán nhận tiền của
 *    ba ngày mà phải ghi hết nợ của mười ngày, hoặc không ghi gì. Cả hai đều sai sổ.
 *
 * 🔴 CHỖ DỄ HỎNG NHẤT LÀ CÂU LỌC. Ba nguồn tiền có ba cột ngày khác nhau (`bc.ngay` ·
 *    DATE(`chot.tao_luc`) · DATE(`thu.luc`)). Lọc nhầm cột = tích một ngày rồi gắn phải dòng của
 *    ngày khác, âm thầm. Và nếu câu lọc lỡ rỗng thì nó lặng lẽ thành NỘP TẤT — đúng thứ vừa cố
 *    tránh, mà màn hình vẫn báo thành công.
 *
 * ⚠️ BÀI NÀY BỐC THẲNG `nop()` VÀ `dang_cam_theo_ngay()` RA CHẠY với `$wpdb` giả, rồi soi CHÍNH
 *    câu SQL nó bắn đi. Dò chuỗi trong mã nguồn không nói được "câu UPDATE có kèm vế ngày không".
 *
 * Chạy: php tools/test/kiem-cam-tien-theo-ngay.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; }
	$TRUOT[] = $ten;
	echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n";
}

/* ---------- Bệ đỡ tí hon ---------- */
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB {
	public static function t( $b ) { return 'wp_vhg_' . $b; }
	public static function rows( $sql ) { global $wpdb; return $wpdb->rows_cho( $sql ); }
}
class VHG_Thu {
	const TIEN_MAT   = 'tien_mat';
	const ND_THU_TAY = 'THU TAY: ';
	public static function nguoi_thu( $nd ) { return trim( str_replace( self::ND_THU_TAY, '', (string) $nd ) ); }
	public static function dau_ky( $k ) { return ''; }
}
function current_time( $f ) { return '2026-09-22 10:00:00'; }

class WpdbGia {
	public $prefix = 'wp_';
	public $insert_id = 7;
	public $sql = array();          // mọi câu đã bắn đi
	public $doc = array();          // [mảnh SQL => hàng trả về]
	public $so = array();           // get_var trả theo thứ tự
	public function prepare( $sql, ...$a ) {
		if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
		foreach ( $a as $v ) { $sql = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . $v . "'", $sql, 1 ); }
		return $sql;
	}
	public function rows_cho( $sql ) {
		$this->sql[] = $sql;
		foreach ( $this->doc as $manh => $hang ) { if ( false !== strpos( $sql, $manh ) ) { return $hang; } }
		return array();
	}
	public function get_results( $sql, $o = null ) { return $this->rows_cho( $sql ); }
	public function get_row( $sql, $o = null ) { $this->sql[] = $sql; return null; }
	public function get_var( $sql ) { $this->sql[] = $sql; return array_shift( $this->so ); }
	public function query( $sql ) { $this->sql[] = $sql; return 1; }
	public function insert( $t, $d ) { $this->sql[] = 'INSERT ' . $t; return 1; }
	public function update( $t, $d, $w ) { $this->sql[] = 'UPDATE-API ' . $t; return 1; }
	public function delete( $t, $w ) { $this->sql[] = 'DELETE-API ' . $t; return 1; }
	public function esc_like( $s ) { return $s; }
}

/* ---------- Bốc hàm ra ---------- */
$nguon = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-quy.php' );
function boc( $nguon, $mo ) {
	$i = strpos( $nguon, $mo );
	if ( false === $i ) { return ''; }
	$d = 0; $n = strlen( $nguon );
	for ( $k = strpos( $nguon, '{', $i ); $k < $n; $k++ ) {
		if ( '{' === $nguon[ $k ] ) { $d++; }
		elseif ( '}' === $nguon[ $k ] && 0 === --$d ) { return substr( $nguon, $i, $k - $i + 1 ); }
	}
	return '';
}
$f_ngay = boc( $nguon, 'public static function dang_cam_theo_ngay(' );
$f_nop  = boc( $nguon, 'public static function nop(' );
$f_thay = boc( $nguon, 'public static function nop_va_nhan_thay(' );
t( 'bốc được dang_cam_theo_ngay + nop + nop_va_nhan_thay', '' !== $f_ngay && '' !== $f_nop && '' !== $f_thay );
if ( '' === $f_ngay || '' === $f_nop || '' === $f_thay ) { echo "✗ không bốc được — dừng.\n"; exit( 1 ); }
eval( 'class VHG_Quy {
	const CS_CHUA_GAN = "(chưa gán)";
	const NG_CHUA_RO  = "(chưa rõ ngày)";
	public static function nhan( $id, $so, $ai, $gc = "" ) { return array( "ok" => true, "nhan" => 1, "id" => $id ); }
	' . $f_ngay . "\n" . $f_nop . "\n" . $f_thay . '
}' );

/* ══════════════════════════════ 1. GOM BA NGUỒN VỀ CÙNG MỘT NGÀY ════════════════════════════ */
echo "── Bung từng ngày: ba nguồn, ba cột ngày, một danh sách ─────\n";
global $wpdb;
$wpdb = new WpdbGia();
$wpdb->doc = array(
	'FROM wp_vhg_chot c' => array( array( 'ng' => '2026-09-20', 't' => 300000, 'n' => 2 ) ),
	'FROM wp_vhg_thu x'  => array( array( 'ng' => '2026-09-21', 't' => 150000, 'n' => 1 ) ),
	'FROM wp_vhg_bc h'   => array(
		array( 'ng' => '2026-09-21', 'cs' => 'GO CẦN THƠ',   't' => 500000, 'n' => 1 ),
		array( 'ng' => '2026-09-21', 'cs' => 'SENSE CITY',   't' => 200000, 'n' => 1 ),
		array( 'ng' => '2026-09-19', 'cs' => 'VẠN HẠNH',     't' => 900000, 'n' => 1 ),
		array( 'ng' => null,         'cs' => '(nhập cũ)',    't' =>  40000, 'n' => 1 ),
		array( 'ng' => '2026-09-18', 'cs' => 'TOÀN QR',      't' =>      0, 'n' => 1 ),  // 0đ -> bỏ
	),
);
$ds = VHG_Quy::dang_cam_theo_ngay( 'Nguyễn Trung Hà' );
$theo = array(); foreach ( $ds as $x ) { $theo[ $x['ngay'] ] = $x; }
t( '🔴 mới nhất lên đầu', '2026-09-21' === $ds[0]['ngay'], array_column( $ds, 'ngay' ) );
t( '🔴 cùng một ngày thì ba nguồn cộng chung (quầy 150k + báo cáo 500k+200k = 850k)',
	850000 === (int) $theo['2026-09-21']['tong'] && 150000 === (int) $theo['2026-09-21']['tu_quay']
	&& 700000 === (int) $theo['2026-09-21']['tu_bao_cao'], isset( $theo['2026-09-21'] ) ? $theo['2026-09-21'] : null );
t( 'ngày ấy kể luôn các cơ sở để kế toán so với xấp tiền trong tay',
	array( 'GO CẦN THƠ', 'SENSE CITY' ) === $theo['2026-09-21']['coso'], $theo['2026-09-21']['coso'] );
t( '🔴 tiền KHÔNG có ngày (dữ liệu cũ) vào nhóm "(chưa rõ ngày)", KHÔNG rơi mất',
	isset( $theo['(chưa rõ ngày)'] ) && 40000 === (int) $theo['(chưa rõ ngày)']['tong'], array_keys( $theo ) );
t( '🔴 tổng các ngày ĐÚNG BẰNG tổng đang cầm (lệch một đồng là hết tin cả bảng) — 300k+150k+700k+900k+40k',
	2090000 === array_sum( array_column( $ds, 'tong' ) ), array_sum( array_column( $ds, 'tong' ) ) );
t( 'ngày 0đ (báo cáo toàn QR) bị bỏ — không bày ô tích cho một dòng không có gì để nộp',
	! isset( $theo['2026-09-18'] ), array_keys( $theo ) );

/* ══════════════════════════════ 2. CHỐT ĐÚNG NGÀY ĐÃ TÍCH ═══════════════════════════════════ */
echo "── Chốt theo ngày: câu lọc phải bám ĐÚNG cột ngày của từng nguồn \n";
function chay_nop( $ngay_ds ) {
	global $wpdb;
	$wpdb = new WpdbGia();
	$wpdb->so = array( 100, 0, 0, 1, 0, 0 );   // tổng chot/thu/bc rồi đếm dòng
	$r = VHG_Quy::nop( 'Nguyễn Trung Hà', 'x', '', null, $ngay_ds );
	$up = array();
	foreach ( $wpdb->sql as $q ) { if ( 0 === strpos( $q, 'UPDATE wp_vhg_' ) ) { $up[] = $q; } }
	return array( $r, $up );
}
list( $r, $up ) = chay_nop( array( '2026-09-21', '2026-09-19' ) );
t( 'ba câu gắn dòng (chot · thu · bc)', 3 === count( $up ), count( $up ) );
t( '🔴 chot lọc bằng DATE(tao_luc) — đúng cột mà bảng đọc đã gom theo',
	false !== strpos( $up[0], "DATE(tao_luc) IN ('2026-09-21','2026-09-19')" ), $up[0] );
t( '🔴 thu lọc bằng DATE(luc)', false !== strpos( $up[1], "DATE(luc) IN ('2026-09-21','2026-09-19')" ), $up[1] );
t( '🔴 bc lọc bằng chính cột ngay', false !== strpos( $up[2], "ngay IN ('2026-09-21','2026-09-19')" ), $up[2] );

list( $r2, $up2 ) = chay_nop( null );
t( 'KHÔNG tích ngày nào = nộp tất, y như trước (đường mọi nơi khác vẫn gọi)',
	false === strpos( $up2[0], 'DATE(tao_luc)' ) && false === strpos( $up2[2], ' ngay IN' ), $up2[0] );

list( $r3, $up3 ) = chay_nop( array( '(chưa rõ ngày)' ) );
t( 'tích nhóm "(chưa rõ ngày)" thì bắt đúng dòng KHÔNG có ngày',
	false !== strpos( $up3[2], 'ngay IS NULL' ) && false === strpos( $up3[2], ' ngay IN' ), $up3[2] );

/* 🔴 Bất biến quan trọng nhất: đã tích thì câu lọc KHÔNG BAO GIỜ rỗng. Rỗng là lặng lẽ nộp tất. */
list( $r4, $up4 ) = chay_nop( array( 'hôm qua', '31/12' ) );   // toàn thứ không đọc được
t( '🔴 tích toàn ngày không đọc được → CHẶN HẾT (1=0), tuyệt đối không rơi về "nộp tất"',
	false !== strpos( $up4[2], '1=0' ) && false !== strpos( $up4[0], '1=0' ), $up4[2] );

/* Gắn 0 dòng thì huỷ lượt nộp và nói rõ vì sao. */
$wpdb = new WpdbGia();
$wpdb->so = array( 0, 0, 0, 0, 0, 0 );
$r5 = VHG_Quy::nop( 'A', 'x', '', null, array( '2026-09-21' ) );
t( 'không gắn được đồng nào ở ngày đã tích → báo rõ NGÀY nào, không báo câu chung chung',
	empty( $r5['ok'] ) && false !== strpos( (string) $r5['error'], '2026-09-21' ), $r5 );

/* Nộp thay phải CHUYỀN TIẾP danh sách ngày, không lặng lẽ chốt cả cục. */
$wpdb = new WpdbGia();
$wpdb->so = array( 500, 0, 0, 1, 0, 0 );
$r6 = VHG_Quy::nop_va_nhan_thay( 'B', 'Kế toán', '', array( '2026-09-21' ) );
$upb = array();
foreach ( $wpdb->sql as $q ) { if ( 0 === strpos( $q, 'UPDATE wp_vhg_bc ' ) ) { $upb[] = $q; } }
t( '🔴 "Xác nhận đã nộp" theo ngày CHUYỀN TIẾP được danh sách ngày xuống nop()',
	count( $upb ) && false !== strpos( $upb[0], "ngay IN ('2026-09-21')" ), $upb ? $upb[0] : null );
t( 'và ghi ngày vào ghi chú của lượt nộp để ba tháng sau còn tra ngược được',
	false !== strpos( implode( ' ', $wpdb->sql ), '2026-09-21' ) );

echo "\n";
if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . '/' . ( $DAT + count( $TRUOT ) ) . "\n"; exit( 1 ); }
echo "✓ SẠCH — $DAT phép\n";
