<?php
/**
 * Kiểm `VHG_KeToan::sua()` cho đường SỬA THẲNG TRONG Ô (2.78.0).
 * Giả lập `$wpdb` — chạy được không cần WordPress lẫn MySQL.
 *
 * Ba mệnh đề phải đúng, vì sai là TIỀN sai mà tổng vẫn khớp nên đối chiếu không bắt được:
 *   1. gõ Tiền mặt  -> ghi đè, ghi chú mang dấu "Thực thu ghi đè"
 *   2. gõ QR trên dòng ĐANG ghi đè -> GIỮ NGUYÊN số ghi đè (không rơi về công thức)
 *   3. xoá trắng Tiền mặt (`bo_ghi_de`) -> về công thức actual−QR VÀ dọn dấu trong ghi chú
 */
error_reporting( E_ALL );
define( 'ARRAY_A', 'ARRAY_A' );

class FakeWpdb {
	public $prefix = 'wp_';
	public $bc   = array( 'report_id' => 'R1', 'coso' => 'CS', 'ngay' => '2026-09-14' );
	public $dong = array();
	public $ghi  = null;      // data truyền vào update() cuối cùng
	public function prepare( $q, ...$a ) {
		foreach ( $a as $v ) { $q = preg_replace( '/%d/', (string) (int) $v, $q, 1 ); $q = preg_replace( '/%s/', "'" . $v . "'", $q, 1 ); }
		return $q;
	}
	public function get_row( $q, $o = null ) {
		if ( false !== stripos( $q, 'vhg_bc_dong' ) ) { return $this->dong; }
		if ( false !== stripos( $q, 'vhg_bc' ) ) { return $this->bc; }
		return null;
	}
	public function get_var( $q ) { return 0; }
	public function query( $q ) { return 0; }
	public function insert( $t, $d ) { return 1; }
	public function update( $t, $d, $w ) { if ( false !== stripos( $t, 'bc_dong' ) ) { $this->ghi = $d; } return 1; }
}
$wpdb = new FakeWpdb();
class VHG_DB { public static function t( $x ) { global $wpdb; return $wpdb->prefix . 'vhg_' . $x; } }
class VHG_BaoCao {
	public static function chi_so_truoc( $ma, $ngay ) { return null; }
	public static function so_chiso_( $v ) { return ( '' === $v || null === $v ) ? null : (float) str_replace( ',', '.', str_replace( '.', '', (string) $v ) ); }
	/* `sua()` gọi sang để nối chỉ số ngày sau — ở đây không kiểm phần đó nên để rỗng. */
	public static function noi_tiep( ...$a ) { return 0; }
	public static function quen_reset_memo() {}
	public static function squash( $s ) { return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $s ) ); }
}
class VHG_Nhat_Ky { public static function ghi( $x ) {} }
/* Hàm của WordPress mà `sua()` gọi tới — giả lập tối thiểu. */
function current_time( $t ) { return '2026-09-14 17:14:00'; }
function number_format_i18n( $n ) { return (string) $n; }

/* Nạp ĐÚNG hàm thật từ nguồn — không chép lại. */
$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$i = strpos( $src, 'public static function sua(' );
if ( false === $i ) { fwrite( STDERR, "Khong tim thay sua()\n" ); exit( 2 ); }
/* Cắt tới hàm kế tiếp cùng cấp. */
$j = strpos( $src, "\n\tpublic static function ", $i + 10 );
$than = substr( $src, $i, $j - $i );
/* Những phụ trợ `sua()` gọi tới — giả lập tối thiểu. */
$phu = '
	public static function don_vi() { return 5000; }
	public static function dang_khoa( $cs, $ng ) { return false; }
	private static function undo_ghi_( $a, $b, $c, $d ) {}
	private static function nop_theo_( $x ) { return 0; }
';
eval( 'class VHG_KeToan { ' . $than . $phu . ' }' );

$loi = 0;
function ok( $t, $dk ) { global $loi; if ( ! $dk ) { echo "  ✗ $t\n"; $loi++; } else { echo "  ✓ $t\n"; } }
function dat( $ghi_chu = '', $tien_mat = 0, $dieu_chinh = 0, $qr = 0 ) {
	global $wpdb;
	$wpdb->ghi = null;
	$wpdb->dong = array( 'report_id' => 'R1', 'ma_may' => 'AM-TP-25', 'chi_so_truoc' => '1406', 'chi_so_sau' => '1488',
		'actual' => 410000, 'tien_mat' => $tien_mat, 'qr' => $qr, 'dieu_chinh' => $dieu_chinh, 'tong' => 0,
		'ghi_chu' => $ghi_chu, 'nop_so_tien' => 0, 'moc_tay' => 0,
		/* Các cột `sua()` có đụng tới — thiếu là PHP cảnh báo rồi giá trị ra 0, kiểm không còn
		   phản ánh đúng hàm thật. */
		'id' => 1, 'nop_trang_thai' => '', 'nop_luc' => null, 'nguoi' => 'nv' );
}

echo "— Gõ thẳng ô TIỀN MẶT = ghi đè —\n";
dat( '', 80000, 0, 330000 );
VHG_KeToan::sua( 'R1', 'AM-TP-25', array( 'actualOverride' => 340000 ), 'admin' );
ok( 'tien mat = dung so vua go', 340000 === (int) $wpdb->ghi['tien_mat'] );
ok( 'ghi chu mang dau "Thuc thu ghi de"', str_contains( $wpdb->ghi['ghi_chu'], 'Thực thu ghi đè' ) );
ok( 'QR khong bi dung toi', 330000 === (int) $wpdb->ghi['qr'] );

echo "— Gõ QR trên dòng ĐANG ghi đè: KHÔNG được mất số ghi đè —\n";
dat( 'Thực thu ghi đè: 340.000đ', 340000, 340000, 260000 );
VHG_KeToan::sua( 'R1', 'AM-TP-25', array( 'qr' => 330000 ), 'admin' );
ok( 'QR nhan so moi', 330000 === (int) $wpdb->ghi['qr'] );
ok( 'TIEN MAT giu nguyen so ghi de (khong roi ve cong thuc)', 340000 === (int) $wpdb->ghi['tien_mat'] );
ok( 'ghi chu VAN con dau ghi de', str_contains( $wpdb->ghi['ghi_chu'], 'Thực thu ghi đè' ) );

echo "— Xoá trắng ô Tiền mặt = GỠ ghi đè —\n";
dat( 'Thực thu ghi đè: 340.000đ', 340000, 340000, 330000 );
VHG_KeToan::sua( 'R1', 'AM-TP-25', array( 'bo_ghi_de' => 1 ), 'admin' );
ok( 'tien mat ve CONG THUC actual - QR', ( 410000 - 330000 ) === (int) $wpdb->ghi['tien_mat'] );
ok( 'dieu_chinh ve 0', 0 === (int) $wpdb->ghi['dieu_chinh'] );
ok( 'dau "Thuc thu ghi de" da DON khoi ghi chu', ! str_contains( $wpdb->ghi['ghi_chu'], 'Thực thu ghi đè' ) );

echo "— Dòng thường, không ghi đè —\n";
dat( '', 80000, 0, 330000 );
VHG_KeToan::sua( 'R1', 'AM-TP-25', array( 'qr' => 100000 ), 'admin' );
ok( 'tien mat tinh lai theo cong thuc', ( 410000 - 100000 ) === (int) $wpdb->ghi['tien_mat'] );
ok( 'khong tu nhien sinh dau ghi de', ! str_contains( $wpdb->ghi['ghi_chu'], 'Thực thu ghi đè' ) );

echo $loi ? "\n❌ $loi phep kiem hong\n" : "\n✅ Tat ca phep kiem dat\n";
exit( $loi ? 1 : 0 );
