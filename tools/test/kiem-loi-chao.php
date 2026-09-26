<?php
/**
 * KIỂM "LỜI CHÀO NGÀY MỚI" trên trạm — anh Thắng 26/09/2026: *"gửi lời chào ngày mới (kiểu trẻ
 * trung năng lượng)"*, chốt *"làm cả A, B, C luôn"*.
 *
 * Chạy: php tools/test/kiem-loi-chao.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 400 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'LC1', 'ho_ten' => 'Huỳnh Quang Tâm', 'cua_hang' => 'LC_CS', 'ngay_sinh' => '1998-09-26' ) );
$hn = (string) current_time( 'Y-m-d' );
for ( $i = 1; $i <= 4; $i++ ) {   // hôm qua … 4 ngày trước, liên tiếp
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'LC_CS', 'ngay' => gmdate( 'Y-m-d', strtotime( $hn . " -$i days" ) ),
		'ma_nv' => 'LC1', 'hau_to' => '', 'ho_ten' => 'x', 'gio_vao_giay' => 28800, 'gio_ra_giay' => 57600, 'nguon' => 'may' ) );
}
/* Khoảng trống ở ngày thứ 5 rồi lại có ngày thứ 6 — không được tính vào chuỗi. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'LC_CS', 'ngay' => gmdate( 'Y-m-d', strtotime( $hn . ' -6 days' ) ),
	'ma_nv' => 'LC1', 'hau_to' => '', 'ho_ten' => 'x', 'gio_vao_giay' => 28800, 'gio_ra_giay' => 57600, 'nguon' => 'may' ) );
$c = VHCC_Tram::du_lieu_chao( 'LC1' );
t( '🔴 ngày sinh chỉ gửi tháng-ngày (không lộ năm)', '09-26' === $c['ngaySinh'], $c );
t( '🔴 hôm nay chưa chấm -> chuỗi tính tới hôm qua: 4 ngày', 4 === $c['chuoi'], $c );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'LC_CS', 'ngay' => $hn, 'ma_nv' => 'LC1', 'hau_to' => '', 'ho_ten' => 'x',
	'gio_vao_giay' => 28800, 'gio_ra_giay' => null, 'nguon' => 'online' ) );
t( '   hôm nay đã chấm vào -> 5 ngày', 5 === VHCC_Tram::du_lieu_chao( 'LC1' )['chuoi'] );
t( '   mã rỗng -> không lỗi, chuỗi 0', 0 === VHCC_Tram::du_lieu_chao( '' )['chuoi'] );

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( '🔴 tab Chấm công có thẻ lời chào', false !== strpos( $tpl, '<div id="loiChao" class="lc an"' ) );
t( '🔴 tab Tôi có chỗ chọn kiểu: tự đổi · Nắng sớm · Tin nhắn · Chuỗi lửa', false !== strpos( $tpl, 'data-kieu="tu"' )
	&& false !== strpos( $tpl, 'data-kieu="a"' ) && false !== strpos( $tpl, 'data-kieu="b"' ) && false !== strpos( $tpl, 'data-kieu="c"' ) );
t( '   đủ năm khung giờ + bốn dịp', 5 === preg_match_all( "/\\{k:'(sang|trua|chieu|toi|dem)'/", $tpl ) && false !== strpos( $tpl, "sn:  {chao:'Chúc mừng sinh nhật" ) );
t( '   máy chủ gửi `chao` trong lượt `toi`', false !== strpos( file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' ), "\$tt['chao'] = self::du_lieu_chao(" ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — lời chào ngày mới (A · B · C).\n";
