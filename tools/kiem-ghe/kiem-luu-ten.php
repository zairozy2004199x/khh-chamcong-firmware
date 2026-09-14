<?php
/**
 * Kiểm đường LƯU TÊN MỘT LÔ (2.82.0) — `VHG_May::dat_ten_lo()`, không cần WordPress/MySQL.
 *
 * Mỗi mệnh đề ở đây tương ứng một cách hỏng đã thật sự xảy ra:
 *   1. gói chỉ có `ten_goi` -> KHÔNG được đụng `ten_khai`. Đụng là ghi đè tên trên sao kê,
 *      mà tên đó đi vào nội dung chuyển khoản: hỏng là tiền cũ thôi ghép được vào ghế.
 *   2. `ten_goi` = '' -> VẪN PHẢI GHI. Xoá trắng một cái tên là ý định hợp lệ; dùng
 *      `isset`/`empty` ở đây là báo "đã lưu" trong khi tên vẫn còn nguyên.
 *   3. trả về `da[ma]` chứa ĐÚNG giá trị sau chuẩn hoá, và chỉ những khoá đã ghi — màn hình
 *      dựa vào đó để biết ô nào đã khớp máy chủ.
 *   4. gói rỗng -> báo lỗi, không lặng lẽ "đã lưu 0 ghế".
 */
error_reporting( E_ALL );

class FakeWpdb {
	public $prefix = 'wp_';
	public $ghi = array();   // [ ma => data ]
	public function update( $t, $d, $w ) { $this->ghi[ $w['ma'] ] = $d; return 1; }
}
$wpdb = new FakeWpdb();
class VHG_DB { public static function t( $x ) { global $wpdb; return $wpdb->prefix . 'vhg_' . $x; } }
/* `chuan_ten()` thật có sửa chữ người gõ (gộp khoảng trắng, cắt bớt). Giả lập bằng một thay đổi
   THẤY ĐƯỢC, để bài kiểm bắt được nếu hàm quên trả bản đã chuẩn hoá về. */
class VHG_Doc { public static function chuan_ten( $s ) { return trim( preg_replace( '/\s+/u', ' ', $s ) ) . '~n'; } }
function current_time( $x ) { return '2026-09-14 08:00:00'; }

/* Nạp ĐÚNG hàm thật: cắt `dat_ten_lo()` ra khỏi nguồn thay vì chép lại. Chép lại thì bài kiểm
   xanh cho một bản không còn nằm trong plugin. */
$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
$i = strpos( $src, 'public static function dat_ten_lo(' );
if ( false === $i ) { fwrite( STDERR, "KHONG THAY dat_ten_lo()\n" ); exit( 2 ); }
$j = strpos( $src, "\n\tpublic static function ", $i + 10 );
eval( 'class VHG_May { ' . substr( $src, $i, $j - $i ) . ' }' );

$pass = array(); $fail = array();
function ok( $t, $c, $ghi = '' ) { global $pass, $fail;
	$d = $t . ( '' !== $ghi ? ' — ' . $ghi : '' );
	if ( $c ) { $pass[] = $d; } else { $fail[] = $d; } }

// 1 — chỉ tên thường gọi
$wpdb->ghi = array();
$r = VHG_May::dat_ten_lo( array( array( 'ma' => '80047', 'ten_goi' => 'Ghế đầu dãy' ) ) );
ok( 'Chỉ ten_goi: KHÔNG đụng ten_khai',
	! array_key_exists( 'ten_khai', $wpdb->ghi['80047'] ), json_encode( $wpdb->ghi['80047'], 256 ) );
ok( 'Chỉ ten_goi: ghi đúng giá trị', 'Ghế đầu dãy' === $wpdb->ghi['80047']['ten_goi'] );
ok( 'Chỉ ten_goi: da[] chỉ có ten_goi',
	array( 'ten_goi' => 'Ghế đầu dãy' ) === $r['da']['80047'], json_encode( $r['da'], 256 ) );

// 2 — chỉ tên ghế
$wpdb->ghi = array();
$r = VHG_May::dat_ten_lo( array( array( 'ma' => '80052', 'ten' => 'ESTELLA-6B' ) ) );
ok( 'Chỉ ten: KHÔNG đụng ten_goi', ! array_key_exists( 'ten_goi', $wpdb->ghi['80052'] ) );
ok( 'Chỉ ten: qua chuan_ten()', 'ESTELLA-6B~n' === $wpdb->ghi['80052']['ten_khai'] );
ok( 'Chỉ ten: da[] trả bản ĐÃ chuẩn hoá',
	array( 'ten' => 'ESTELLA-6B~n' ) === $r['da']['80052'], json_encode( $r['da'], 256 ) );

// 3 — xoá trắng tên thường gọi
$wpdb->ghi = array();
$r = VHG_May::dat_ten_lo( array( array( 'ma' => '80049', 'ten_goi' => '' ) ) );
ok( 'Xoá trắng ten_goi VẪN ghi (không bị empty() nuốt)',
	isset( $wpdb->ghi['80049'] ) && '' === $wpdb->ghi['80049']['ten_goi'],
	json_encode( $wpdb->ghi, 256 ) );
ok( 'Xoá trắng: đếm là 1 ghế đã lưu', 1 === $r['xong'] );

// 4 — cả hai khoá trong một gói, nhiều ghế
$wpdb->ghi = array();
$r = VHG_May::dat_ten_lo( array(
	array( 'ma' => '80047', 'ten_goi' => 'A' ),
	array( 'ma' => '80048', 'ten' => 'B', 'ten_goi' => 'C' ),
	array( 'ma' => '80050' ),                      // không sửa gì -> bỏ qua, không tính
) );
ok( 'Lô nhiều ghế: ghi đúng 2 ghế', 2 === count( $wpdb->ghi ) && 2 === $r['xong'],
	json_encode( array_keys( $wpdb->ghi ) ) );
ok( 'Lô nhiều ghế: ghế không sửa gì thì KHÔNG ghi', ! isset( $wpdb->ghi['80050'] ) );
ok( 'Lô nhiều ghế: ghế sửa cả hai thì ghi cả hai',
	'B~n' === $wpdb->ghi['80048']['ten_khai'] && 'C' === $wpdb->ghi['80048']['ten_goi'] );

// 5 — gói rỗng / thiếu mã
$r = VHG_May::dat_ten_lo( array() );
ok( 'Gói rỗng: báo lỗi, không báo "đã lưu"', empty( $r['ok'] ) && ! empty( $r['error'] ) );
$wpdb->ghi = array();
$r = VHG_May::dat_ten_lo( array( array( 'ten_goi' => 'X' ) ) );
ok( 'Thiếu mã ghế: không ghi, có ghi nhận lỗi',
	! count( $wpdb->ghi ) && count( $r['loi'] ) === 1, json_encode( $r, 256 ) );

// 6 — cắt đúng 190 ký tự (cột VARCHAR(190))
$wpdb->ghi = array();
VHG_May::dat_ten_lo( array( array( 'ma' => 'M', 'ten_goi' => str_repeat( 'ế', 250 ) ) ) );
ok( 'ten_goi dài quá bị cắt về 190 ký tự',
	190 === mb_strlen( $wpdb->ghi['M']['ten_goi'] ), mb_strlen( $wpdb->ghi['M']['ten_goi'] ) );

/* 7 — 🔴 GÁC PAYLOAD. Đây là gốc thật của *"Không lưu được tên thường gọi"*: CSDL lưu đúng,
   `may_ten_goi` chạy đúng, chỉ có payload `so_lieu` không gửi cột đó ra nên ô luôn vẽ lại rỗng.
   Một lỗi không để lại dấu vết nào ở bảng dữ liệu lẫn ở log — nên phải có người gác đúng chỗ nó
   xảy ra: MỌI chỗ dựng danh sách ghế gửi cho màn hình đều phải kèm `ten_goi`. */
$tr = file( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );
$thieu = array();
foreach ( $tr as $n => $d ) {
	if ( false === strpos( $d, "'ten'" ) || false === strpos( $d, "ten_khai" ) ) { continue; }
	$co = false;
	for ( $k = max( 0, $n - 8 ); $k < min( count( $tr ), $n + 9 ); $k++ ) {
		if ( false !== strpos( $tr[ $k ], "'ten_goi'" ) ) { $co = true; break; }
	}
	if ( ! $co ) { $thieu[] = $n + 1; }
}
ok( 'Mọi payload danh sách ghế đều gửi kèm ten_goi',
	! count( $thieu ), 'dòng thiếu: ' . implode( ', ', $thieu ) );

echo 'PASS ' . count( $pass ) . ' / FAIL ' . count( $fail ) . "\n";
foreach ( $pass as $t ) { echo "  ✓ $t\n"; }
foreach ( $fail as $t ) { echo "  ✗ $t\n"; }
exit( count( $fail ) ? 1 : 0 );
