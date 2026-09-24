<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHỨNG TỪ NHẬN PDF — anh Thắng 24/09/2026: *"Cho upload cả file pdf nhé"* (ảnh: toast "Chỉ
 * nhận ảnh (JPG/PNG/GIF/WEBP/HEIC)" khi đính hoá đơn điện tử vào dòng đơn Chi Phí Chung VP).
 *
 * 🔴 CHẠY THẬT `VHCP_Upload::upload_image` trên sổ giả: PDF thật → ghi .pdf; PDF giả (đuôi .pdf,
 *    ruột không phải PDF) → chối; ảnh vẫn như cũ; loại khác vẫn chối.
 *
 * Chạy: php tools/test/kiem-tep-pdf-chung-tu.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}

VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$r = VHCP_Don::tao_don_moi( 'T9/2026 (21/9-27/9/2026)', 'Huỳnh Quang Thắng' );
$ma = (string) $r['maDon'];

/* PDF thật, nhỏ nhất có thể — chỉ cần chữ ký đầu tệp. */
$pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
$u = VHCP_Upload::upload_image( array( 'base64' => 'data:application/pdf;base64,' . base64_encode( $pdf ), 'type' => 'application/pdf' ), $ma, 'VĂN PHÒNG' );
t( '🔴 PDF thật → nhận', ! empty( $u['success'] ), $u );
t( '🔴 tên tệp ghi ra mang đuôi .pdf', ! empty( $u['url'] ) && 1 === preg_match( '/\.pdf$/', (string) $u['url'] ), $u );
$duong = isset( $u['url'] ) ? $u['url'] : '';
/* Tệp có thật trên "hosting" giả và ruột đúng là PDF vừa gửi. */
$up = wp_upload_dir();
$tep = rtrim( $up['basedir'], '/' ) . '/' . rawurldecode( substr( $duong, strlen( rtrim( $up['baseurl'], '/' ) ) + 1 ) );
t( '   tệp nằm trên đĩa', is_file( $tep ), $tep );
t( '   ruột tệp đúng là PDF vừa gửi', is_file( $tep ) && file_get_contents( $tep ) === $pdf );

/* PDF GIẢ: đuôi/type nói pdf, ruột là chữ bất kỳ. */
$g = VHCP_Upload::upload_image( array( 'base64' => base64_encode( 'MZ-day-la-exe-doi-duoi' ), 'type' => 'application/pdf' ), $ma, 'VĂN PHÒNG' );
t( '🔴 type pdf mà thiếu chữ ký %PDF- → CHỐI', empty( $g['success'] ) && false !== mb_strpos( (string) $g['error'], 'PDF' ), $g );

/* Ảnh vẫn như cũ. */
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );
$a = VHCP_Upload::upload_image( array( 'base64' => base64_encode( $png ), 'type' => 'image/png' ), $ma, 'VĂN PHÒNG' );
t( '   ảnh PNG vẫn nhận, đuôi .png', ! empty( $a['success'] ) && 1 === preg_match( '/\.png$/', (string) $a['url'] ), $a );

/* Loại khác vẫn chối, và câu chối nay nói có PDF. */
$x = VHCP_Upload::upload_image( array( 'base64' => base64_encode( 'PK-zip' ), 'type' => 'application/zip' ), $ma, 'VĂN PHÒNG' );
t( '🔴 zip → chối', empty( $x['success'] ), $x );
t( '   câu chối nói rõ "hoặc PDF" để người ta biết PDF được', false !== mb_strpos( (string) $x['error'], 'PDF' ), $x );
$d = VHCP_Upload::upload_image( array( 'base64' => base64_encode( 'doc' ), 'type' => 'application/msword' ), $ma, 'VĂN PHÒNG' );
t( '   doc → chối (hồ sơ dự án có cửa riêng)', empty( $d['success'] ), $d );

/* Ba ô chọn tệp bên màn nhận .pdf. */
$html = (string) file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
foreach ( array( 'imgFile', 'hoaDonFile', 'scImgFile' ) as $id ) {
	t( "🔴 ô chọn tệp `$id` nhận cả .pdf", 1 === preg_match( '/id="' . $id . '"[^>]*accept="image\/\*,\.pdf,application\/pdf"|accept="image\/\*,\.pdf,application\/pdf"[^>]*id="' . $id . '"/', $html ), $id );
}
t( '   không còn ô chứng từ nào chỉ nhận ảnh', 0 === preg_match( '/type="file"[^>]*accept="image\/\*"[^>]*>/', $html ) );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: chứng từ nhận PDF thật, chối PDF giả, ảnh và cửa chối khác không đổi.\n";
