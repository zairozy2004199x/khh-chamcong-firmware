<?php
/**
 * KIỂM BỘ ĐỌC .XLSX — cửa nhận tệp do người ngoài đưa vào.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CANH NẶNG
 * =================================================================================================
 * Anh Thắng 18/09/2026: *"nạp bằng file .xlsx cho chuẩn nhé"* — quy trình sửa bảng công theo tuần
 * nhận tệp cửa hàng trưởng gửi lên. Đây là chỗ DUY NHẤT trong cả hệ chấm công mà một tệp nhị phân
 * từ bên ngoài được mở ra và phân tích. Ba chỗ hỏng, xếp theo mức đắt:
 *
 *   1. XXE — tệp khai một thực thể ngoài trỏ tới `wp-config.php`, nội dung ấy chui vào một ô, và
 *      người soát đơn đọc được mật khẩu cơ sở dữ liệu ngay trên màn duyệt.
 *   2. LỆCH CỘT — Excel bỏ hẳn thẻ của ô trống. Đọc thô thì cột sau tụt vào chỗ cột trước, cả
 *      bảng sai một cột mà không có gì báo, và số giờ vào nhầm người.
 *   3. MẤT CHỮ — bộ ghi dùng `inlineStr`, còn Excel lưu lại thì viết theo `sharedStrings`. Bộ đọc
 *      thiếu nhánh ấy thì đọc tệp mình vừa xuất thì ngon, đọc tệp người ta gửi về thì mọi ô chữ
 *      đều rỗng. Đây là cái bẫy dễ sập nhất vì bộ thử vòng-tròn tự-xuất-tự-đọc KHÔNG bắt được.
 *
 * Chạy: php tools/test/kiem-doc-xlsx.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "BỎ QUA: máy chạy bộ thử không có php-zip.\n";
	exit( 0 );
}

$tam = array();
function tep( $noi_dung ) {
	global $tam;
	$d = tempnam( sys_get_temp_dir(), 'kiemxlsx' );
	file_put_contents( $d, $noi_dung );
	$tam[] = $d;
	return $d;
}
/** Dựng tay một .xlsx theo kiểu EXCEL viết ra — tức là có `sharedStrings`. */
function xlsx_kieu_excel( $sheet_xml, $shared = null, $them = array() ) {
	global $tam;
	$d = tempnam( sys_get_temp_dir(), 'kiemxlsx' );
	$tam[] = $d;
	$z = new ZipArchive();
	$z->open( $d, ZipArchive::OVERWRITE );
	$z->addFromString( '[Content_Types].xml',
		'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
	$z->addFromString( 'xl/workbook.xml',
		'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
		. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<sheets><sheet name="Tuan" sheetId="1" r:id="rId7"/></sheets></workbook>' );
	$z->addFromString( 'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId7" Target="worksheets/sheet9.xml"/></Relationships>' );
	$z->addFromString( 'xl/worksheets/sheet9.xml',
		'<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<sheetData>' . $sheet_xml . '</sheetData></worksheet>' );
	if ( null !== $shared ) { $z->addFromString( 'xl/sharedStrings.xml', $shared ); }
	foreach ( $them as $ten => $nd ) { $z->addFromString( $ten, $nd ); }
	$z->close();
	return $d;
}

/* ================================================================= vòng tròn với bộ ghi */

$nd = VHCC_Xuat::xlsx( array( array( 'ten' => 'Tuan', 'hang' => array(
	array( 'Mã NV', 'Họ tên', 'Ngày', 'Giờ' ),
	array( VHCC_Xuat::chu( '0029' ), 'Nguyễn Văn A', '2026-09-14', 9 ),
	array( VHCC_Xuat::chu( 'MNNV2MTD0025' ), 'Trần Thị B', '2026-09-15', 7.5 ),
) ) ) );
t( 'bộ ghi dựng được tệp', is_string( $nd ) && '' !== $nd );

$r = VHCC_DocXlsx::doc( tep( $nd ) );
t( 'đọc lại được tệp mình vừa xuất', ! empty( $r['ok'] ), $r );
teq( 'đúng bốn dòng kể cả dòng tiêu đề', 3, count( $r['hang'] ) );
teq( 'ô tiêu đề đọc đúng', 'Mã NV', $r['hang'][0][0] );
/* 🔴 Mã `0029` phải còn nguyên số 0 ở đầu. Excel tự đoán kiểu là nó thành số 29, và lúc ấy giờ
   công vào nhầm người mà không có gì báo — xem khối cảnh báo ở `VHCC_Xuat::o()`. */
teq( '🔴 mã NV giữ nguyên số 0 ở đầu', '0029', $r['hang'][1][0] );
teq( 'họ tên có dấu đọc đúng', 'Nguyễn Văn A', $r['hang'][1][1] );
teq( 'số giờ đọc ra số', '9', $r['hang'][1][3] );
teq( 'số lẻ cũng đúng', '7.5', $r['hang'][2][3] );

/* ================================================================= tệp do EXCEL lưu lại */

/* 🔴 ĐÂY LÀ CẢNH THẬT: cửa hàng trưởng tải tệp về, mở Excel, sửa, bấm Lưu. Excel viết lại toàn
   bộ chuỗi sang `sharedStrings.xml`. Vòng tròn tự-xuất-tự-đọc ở trên KHÔNG bắt được cảnh này. */
$shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">'
	. '<si><t>Mã NV</t></si><si><t>0029</t></si><si><t>Nguyễn Văn A</t></si></sst>';
$sheet = '<row r="1"><c r="A1" t="s"><v>0</v></c></row>'
	. '<row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2" t="s"><v>2</v></c><c r="C2"><v>9</v></c></row>';
$r = VHCC_DocXlsx::doc( xlsx_kieu_excel( $sheet, $shared ) );
t( '🔴 đọc được tệp kiểu Excel (sharedStrings)', ! empty( $r['ok'] ), $r );
teq( '🔴 và ô chữ KHÔNG rỗng', '0029', $r['hang'][1][0] );
teq( 'tên có dấu qua bảng chuỗi chung vẫn đúng', 'Nguyễn Văn A', $r['hang'][1][1] );
teq( 'số vẫn là số', '9', $r['hang'][1][2] );

/* Ô chữ bị Excel chẻ thành nhiều mẩu vì có đoạn in đậm. */
$shared2 = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
	. '<si><r><t xml:space="preserve">Nguyễn </t></r><r><t>Văn A</t></r></si></sst>';
$r = VHCC_DocXlsx::doc( xlsx_kieu_excel( '<row r="1"><c r="A1" t="s"><v>0</v></c></row>', $shared2 ) );
teq( '🔴 ô chữ chẻ làm nhiều mẩu được ghép lại đủ', 'Nguyễn Văn A', $r['hang'][0][0] );

/* ================================================================= giữ chỗ trống */

/* 🔴 Excel BỎ HẲN thẻ của ô trống. Hàng dưới có A và C, không có B. */
$sheet = '<row r="1"><c r="A1" t="inlineStr"><is><t>a</t></is></c>'
	. '<c r="C1" t="inlineStr"><is><t>c</t></is></c>'
	. '<c r="E1" t="inlineStr"><is><t>e</t></is></c></row>';
$r = VHCC_DocXlsx::doc( xlsx_kieu_excel( $sheet ) );
teq( '🔴 ô trống giữ chỗ, cột sau KHÔNG tụt vào', array( 'a', '', 'c', '', 'e' ), $r['hang'][0] );

/* Dòng trống ở giữa cũng phải giữ chỗ — không thì ngày công lệch hàng. */
$sheet = '<row r="1"><c r="A1" t="inlineStr"><is><t>mot</t></is></c></row>'
	. '<row r="3"><c r="A3" t="inlineStr"><is><t>ba</t></is></c></row>';
$r = VHCC_DocXlsx::doc( xlsx_kieu_excel( $sheet ) );
teq( '🔴 dòng trống ở giữa cũng giữ chỗ', 3, count( $r['hang'] ) );
teq( 'và dòng ba vẫn ở đúng chỗ thứ ba', 'ba', $r['hang'][2][0] );

/* Cột quá Z — AA, AB. */
$sheet = '<row r="1"><c r="AA1" t="inlineStr"><is><t>x</t></is></c></row>';
$r = VHCC_DocXlsx::doc( xlsx_kieu_excel( $sheet ) );
teq( 'cột AA đọc đúng chỉ số 26', 'x', $r['hang'][0][26] );
teq( 'A -> 0',   0,  VHCC_DocXlsx::cot_so( 'A1' ) );
teq( 'Z -> 25',  25, VHCC_DocXlsx::cot_so( 'Z9' ) );
teq( 'AA -> 26', 26, VHCC_DocXlsx::cot_so( 'AA1' ) );
teq( 'BC -> 54', 54, VHCC_DocXlsx::cot_so( 'BC12' ) );
teq( 'rác -> -1', -1, VHCC_DocXlsx::cot_so( '12' ) );

/* ================================================================= tờ đầu theo workbook */

/* 🔴 Tờ đầu là tờ workbook khai đầu tiên, KHÔNG phải `sheet1.xml`. Bộ dựng ở trên cố ý đặt tên
   tệp là `sheet9.xml` để bắt đúng lỗi đọc theo tên. */
$r = VHCC_DocXlsx::doc( xlsx_kieu_excel( '<row r="1"><c r="A1" t="inlineStr"><is><t>dung-to</t></is></c></row>',
	null, array( 'xl/worksheets/sheet1.xml' =>
		'<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>SAI-TO</t></is></c></row></sheetData></worksheet>' ) ) );
teq( '🔴 đọc tờ workbook khai đầu, không đọc bừa sheet1.xml', 'dung-to', $r['hang'][0][0] );

/* ================================================================= chặn XXE */

/* 🔴 Tệp khai thực thể ngoài trỏ vào tệp trên đĩa. Phải CHỐI, không phải đọc được rồi lọc. */
$bi_mat = tep( "MAT-KHAU-CSDL-KHONG-DUOC-LO" );
$xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file://' . $bi_mat . '">]>'
	. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
	. '<row r="1"><c r="A1" t="inlineStr"><is><t>&x;</t></is></c></row></sheetData></worksheet>';
$d = tempnam( sys_get_temp_dir(), 'kiemxlsx' );
$tam[] = $d;
$z = new ZipArchive();
$z->open( $d, ZipArchive::OVERWRITE );
$z->addFromString( 'xl/workbook.xml',
	'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>' );
$z->addFromString( 'xl/worksheets/sheet1.xml', $xxe );
$z->close();
$r = VHCC_DocXlsx::doc( $d );
t( '🔴 tệp có DOCTYPE thì CHỐI THẲNG', empty( $r['ok'] ), $r );
$het = json_encode( $r, JSON_UNESCAPED_UNICODE );
t( '🔴 và KHÔNG có mẩu nội dung tệp bí mật nào lọt ra',
	false === strpos( $het, 'MAT-KHAU-CSDL' ), $het );

t( 'soat_xml chối DOCTYPE', ! VHCC_DocXlsx::soat_xml( '<?xml version="1.0"?><!DOCTYPE a><a/>' ) );
t( 'soat_xml chối ENTITY',  ! VHCC_DocXlsx::soat_xml( '<?xml version="1.0"?><!ENTITY b "c"><a/>' ) );
t( 'soat_xml chối chuỗi rỗng', ! VHCC_DocXlsx::soat_xml( '' ) );
t( 'soat_xml cho qua XML lành', VHCC_DocXlsx::soat_xml( '<?xml version="1.0"?><a/>' ) );

/* ================================================================= chối tử tế */

$r = VHCC_DocXlsx::doc( tep( 'day khong phai zip, chi la mot dong chu' ) );
t( 'tệp không phải .xlsx thì chối', empty( $r['ok'] ), $r );
t( 'và câu chối chỉ đúng đường cho người gửi nhầm',
	false !== mb_strpos( $r['error'], 'Google Sheets' ), $r['error'] );

$r = VHCC_DocXlsx::doc( tep( '' ) );
t( 'tệp rỗng thì chối', empty( $r['ok'] ), $r );

$r = VHCC_DocXlsx::doc( '/khong/co/tep/nay.xlsx' );
t( 'đường dẫn không có thì chối, không ném Error', empty( $r['ok'] ), $r );

$r = VHCC_DocXlsx::doc( tep( str_repeat( 'x', VHCC_DocXlsx::TEP_TOI_DA + 10 ) ) );
t( '🔴 tệp quá nặng thì chối TRƯỚC khi mở', empty( $r['ok'] ), $r );
t( 'và nói ra trần bao nhiêu MB', false !== mb_strpos( $r['error'], 'MB' ), $r['error'] );

/* Zip hợp lệ nhưng không có tờ nào. */
$d = tempnam( sys_get_temp_dir(), 'kiemxlsx' );
$tam[] = $d;
$z = new ZipArchive();
$z->open( $d, ZipArchive::OVERWRITE );
$z->addFromString( 'doc.txt', 'khong phai bang tinh' );
$z->close();
$r = VHCC_DocXlsx::doc( $d );
t( 'zip không có tờ nào thì chối', empty( $r['ok'] ), $r );

/* ================================================================= dọn */

foreach ( $tam as $x ) { @unlink( $x ); }

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — đọc được cả tệp mình xuất lẫn tệp Excel lưu lại, và chối tệp có mùi.\n";
