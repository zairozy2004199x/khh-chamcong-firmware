<?php
/**
 * Kiểm `VHG_May::xoa_han_may()` — hàm DUY NHẤT trong plugin xoá hẳn một mã ghế.
 * Giả lập `$wpdb` nên chạy được không cần WordPress lẫn MySQL.
 *
 * Ba chốt phải đúng, vì sai là mất dữ liệu thật:
 *   1. chỉ mã `coso_id = 0`
 *   2. chỉ mã KHÔNG còn dòng nào ở mọi bảng có cột `ma_may`
 *   3. `that = false` thì TUYỆT ĐỐI không chạy câu DELETE nào
 */
error_reporting( E_ALL );
define( 'ARRAY_A', 'ARRAY_A' );   // hằng của WordPress — gia lập vì test chạy ngoài WP

class FakeWpdb {
	public $prefix = 'wp_';
	public $may = array();      // ma => coso_id
	public $ref = array();      // ten_bang_ngan => array( ma => so_dong )
	public $da_delete = array();
	public $cau_sql = array();

	public function prepare( $q, ...$a ) {
		foreach ( $a as $v ) {
			$q = preg_replace( '/%d/', (string) (int) $v, $q, 1 );
			$q = preg_replace( '/%s/', "'" . $v . "'", $q, 1 );
		}
		return $q;
	}
	public function get_col( $q ) {
		$this->cau_sql[] = $q;
		$ra = array();
		foreach ( array_keys( $this->ref ) as $b ) { $ra[] = $this->prefix . 'vhg_' . $b; }
		return $ra;
	}
	public function get_row( $q, $out = null ) {
		$this->cau_sql[] = $q;
		if ( ! preg_match( "/ma='([^']*)'/", $q, $m ) ) { return null; }
		$ma = $m[1];
		if ( ! array_key_exists( $ma, $this->may ) ) { return null; }
		return array( 'ma' => $ma, 'coso_id' => $this->may[ $ma ] );
	}
	public function get_var( $q ) {
		$this->cau_sql[] = $q;
		if ( ! preg_match( '/FROM `' . $this->prefix . 'vhg_([a-z_]+)`/', $q, $b ) ) { return 0; }
		if ( ! preg_match( "/ma_may='([^']*)'/", $q, $m ) ) { return 0; }
		$bang = $b[1]; $ma = $m[1];
		return isset( $this->ref[ $bang ][ $ma ] ) ? $this->ref[ $bang ][ $ma ] : 0;
	}
	public function query( $q ) {
		$this->cau_sql[] = $q;
		if ( stripos( $q, 'DELETE' ) === 0 ) {
			preg_match( "/ma='([^']*)'/", $q, $m );
			$this->da_delete[] = $m[1];
			return 1;
		}
		return 0;
	}
}

$wpdb = new FakeWpdb();
class VHG_DB { public static function t( $x ) { global $wpdb; return $wpdb->prefix . 'vhg_' . $x; } }

/* Nạp ĐÚNG hàm thật từ nguồn — không chép lại. Chép lại là kiểm một bản sao, bản thật sửa gì
   cũng không ai biết. */
$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
$i = strpos( $src, 'public static function xoa_han_may(' );
$j = strpos( $src, 'public static function xoa_may(' );
if ( false === $i || false === $j ) { fwrite( STDERR, "Khong tim thay ham trong nguon\n" ); exit( 2 ); }
eval( 'class VHG_May { ' . substr( $src, $i, $j - $i ) . ' }' );

$loi = 0;
function ok( $t, $dk ) { global $loi; if ( ! $dk ) { echo "  ✗ $t\n"; $loi++; } else { echo "  ✓ $t\n"; } }

$wpdb->may = array( 'AMBT01' => 0, 'AMBT02' => 0, '80840' => 0, 'AM-BD-1' => 3 );
$wpdb->ref = array( 'bc_dong' => array( '80840' => 5 ), 'thu' => array( '80840' => 2 ), 'lenh' => array(), 'nhip' => array() );

echo "— Xem trước KHÔNG được đụng vào dữ liệu —\n";
$r = VHG_May::xoa_han_may( array( 'AMBT01', 'AMBT02', '80840', 'AM-BD-1' ), false );
ok( 'khong chay DELETE nao', count( $wpdb->da_delete ) === 0 );
ok( 'ma sach thi vao danh sach se xoa', $r['se_xoa'] === array( 'AMBT01', 'AMBT02' ) );
ok( 'ma con du lieu bi GIU LAI', count( array_filter( $r['giu_lai'], fn( $g ) => '80840' === $g['ma'] ) ) === 1 );
ok( 'ma dang thuoc co so bi GIU LAI', count( array_filter( $r['giu_lai'], fn( $g ) => 'AM-BD-1' === $g['ma'] ) ) === 1 );
$ly = array_values( array_filter( $r['giu_lai'], fn( $g ) => '80840' === $g['ma'] ) )[0]['ly_do'];
ok( 'ly do NOI RO bang nao, bao nhieu dong', str_contains( $ly, 'bc_dong (5)' ) && str_contains( $ly, 'thu (2)' ) );
$ly2 = array_values( array_filter( $r['giu_lai'], fn( $g ) => 'AM-BD-1' === $g['ma'] ) )[0]['ly_do'];
ok( 'ly do co so noi ro la "chua gan"', str_contains( $ly2, 'CHƯA GÁN' ) );

echo "— Xoá thật —\n";
$r2 = VHG_May::xoa_han_may( array( 'AMBT01', 'AMBT02', '80840', 'AM-BD-1' ), true );
ok( 'xoa DUNG 2 ma', $r2['da_xoa'] === 2 );
ok( 'xoa dung AMBT01 + AMBT02', $wpdb->da_delete === array( 'AMBT01', 'AMBT02' ) );
ok( 'KHONG xoa ma con du lieu', ! in_array( '80840', $wpdb->da_delete, true ) );
ok( 'KHONG xoa ma dang thuoc co so', ! in_array( 'AM-BD-1', $wpdb->da_delete, true ) );
$co_dk = false;
foreach ( $wpdb->cau_sql as $q ) { if ( stripos( $q, 'DELETE' ) === 0 && stripos( $q, 'coso_id=0' ) !== false ) { $co_dk = true; } }
ok( 'cau DELETE van kem dieu kien coso_id=0 (chot thu hai)', $co_dk );

echo "— Đường biên —\n";
$wpdb->da_delete = array();
$r3 = VHG_May::xoa_han_may( array(), true );
ok( 'danh sach rong -> bao loi, khong xoa', empty( $r3['ok'] ) && count( $wpdb->da_delete ) === 0 );
$r4 = VHG_May::xoa_han_may( array( 'KHONG-CO-THAT' ), true );
ok( 'ma khong ton tai -> vao khong_thay, khong xoa', $r4['khong_thay'] === array( 'KHONG-CO-THAT' ) && count( $wpdb->da_delete ) === 0 );
$r5 = VHG_May::xoa_han_may( array( 'AMBT01', 'AMBT01', '  AMBT01  ' ), false );
ok( 'ma trung/thua khoang trang duoc gom lam mot', $r5['se_xoa'] === array( 'AMBT01' ) );

echo $loi ? "\n❌ $loi phep kiem hong\n" : "\n✅ Tat ca phep kiem dat\n";
exit( $loi ? 1 : 0 );
