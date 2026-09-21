<?php
/**
 * Kiểm phần đọc số và đọc ngày của KHTC_GiaoDich — chạy không cần WordPress.
 *
 *   php tools/kh-tai-chinh/tests/kiem-so-ngay.php
 *
 * Hai hàm này quyết định mọi con số vào sổ. Sai ở đây thì không có gì báo:
 * "20.000 ₫" đọc thành 20 vẫn là số hợp lệ, vẫn cộng được, chỉ là sai 1000 lần.
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../wordpress/kh-tai-chinh/includes/class-khtc-giao-dich.php';

$dat = 0;
$hong = array();

function kiem( $ten, $that, $mong ) {
	global $dat, $hong;
	if ( $that === $mong ) { $dat++; return; }
	$hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) );
}

// ---------------------------------------------------------------- số tiền
kiem( 'số trơn', KHTC_GiaoDich::doc_so( '1500000' ), 1500000 );
kiem( 'phân cách nghìn kiểu Việt', KHTC_GiaoDich::doc_so( '1.500.000' ), 1500000 );
kiem( 'phân cách nghìn kiểu Mỹ', KHTC_GiaoDich::doc_so( '1,500,000' ), 1500000 );
kiem( 'kèm ký hiệu tiền', KHTC_GiaoDich::doc_so( '20.000 ₫' ), 20000 );
kiem( 'một dấu chấm, nhóm sau 3 số = nghìn', KHTC_GiaoDich::doc_so( '20.000' ), 20000 );
kiem( 'một dấu phẩy, nhóm sau 3 số = nghìn', KHTC_GiaoDich::doc_so( '20,000' ), 20000 );
kiem( 'thập phân kiểu Việt', KHTC_GiaoDich::doc_so( '1.234,56' ), 1235 );
kiem( 'thập phân kiểu Mỹ', KHTC_GiaoDich::doc_so( '1,234.56' ), 1235 );
kiem( 'số âm', KHTC_GiaoDich::doc_so( '-850.000' ), -850000 );
kiem( 'số âm trong ngoặc kèm chữ', KHTC_GiaoDich::doc_so( '- 850.000 VND' ), -850000 );
kiem( 'ô trống', KHTC_GiaoDich::doc_so( '' ), 0 );
kiem( 'chữ không phải số', KHTC_GiaoDich::doc_so( 'không có' ), 0 );

// ------------------------------------------------------------------ ngày
kiem( 'ngày kiểu Việt', KHTC_GiaoDich::doc_ngay( '20/07/2026' ), '2026-07-20' );
kiem( 'ngày gạch nối', KHTC_GiaoDich::doc_ngay( '20-07-2026' ), '2026-07-20' );
kiem( 'ngày một chữ số', KHTC_GiaoDich::doc_ngay( '5/7/2026' ), '2026-07-05' );
kiem( 'ngày kiểu ISO', KHTC_GiaoDich::doc_ngay( '2026-07-20' ), '2026-07-20' );
kiem( 'ngày kèm giờ', KHTC_GiaoDich::doc_ngay( '20/07/2026 14:30' ), '2026-07-20' );
kiem( 'ô trống', KHTC_GiaoDich::doc_ngay( '' ), '' );
kiem( 'không phải ngày', KHTC_GiaoDich::doc_ngay( 'hôm qua' ), '' );

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
