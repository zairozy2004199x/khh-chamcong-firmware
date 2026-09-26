<?php
/**
 * KIỂM "/noi-bo/ LÀ TRANG CHẤM CÔNG, CHẠY SONG SONG /cham-cong-online/".
 *
 * Anh Thắng 26/09/2026: *"Trang nội bộ hiện tại không dùng, anh muốn lấy link nội bộ làm link
 * chấm công để chạy song song 2 link"*.
 *
 * Chạy: php tools/test/kiem-noi-bo-la-cham-cong.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
$thu = function ( $uri, $get = array() ) {
	$_SERVER['REQUEST_URI'] = $uri;
	$_GET = $get;
	$kq = VHCC_Tram::la_duong_noi_bo();
	$_GET = array();
	return $kq;
};

t( '🔴 /noi-bo/ -> trang chấm công', $thu( '/noi-bo/' ) );
t( '   /noi-bo (không gạch chéo cuối) cũng vậy', $thu( '/noi-bo' ) );
t( '   /noi-bo/?viec=... (cổng lệnh) cũng vậy', $thu( '/noi-bo/?viec=boot' ) );
t( '   ?vhnb=1 (site chưa bật đường dẫn đẹp) cũng vậy', $thu( '/', array( 'vhnb' => '1' ) ) );
t( '🔴 /cham-cong-online/ KHÔNG bị nhánh này nuốt (nó đi đường riêng của nó)', ! $thu( '/cham-cong-online/' ) );
t( '   trang khác không dính', ! $thu( '/quan-tri-cham-cong/' ) && ! $thu( '/noi-bo-khac/' ) && ! $thu( '/' ) );
t( '   đường có tiền tố cùng tên không dính', ! $thu( '/abc/noi-bo/' ) );

/* Cùng MỘT trang: `render()` của trạm vẽ ra đúng trang chấm công (có cổng lệnh trỏ về trạm). */
ob_start(); VHCC_Tram::render(); $h = ob_get_clean();
t( 'trang vẽ ra là trang Chấm công', false !== strpos( $h, 'Chấm công' ) );
t( '   cổng lệnh vẫn trỏ về địa chỉ trạm — chạy được từ cả hai địa chỉ',
	false !== strpos( $h, str_replace( '/', '\/', VHCC_Tram::url() ) ) || false !== strpos( $h, VHCC_Tram::url() ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — /noi-bo/ chạy trang chấm công song song /cham-cong-online/.\n";
