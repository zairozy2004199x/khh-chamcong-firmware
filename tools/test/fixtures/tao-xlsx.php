<?php
/**
 * Dựng lại file mẫu nha-ma-ba-ria.xlsx cho bộ test.
 *
 * File mẫu phải giống HÀNG THẬT của K&H ở 3 điểm đã từng làm nạp sai:
 *  1. dòng banner + dòng tổng hợp phía trên, tiêu đề nằm ở DÒNG 4;
 *  2. ô lỗi #REF! (t="e") ở dòng tổng hợp;
 *  3. số của Google xuất ra đủ độ chính xác float (2405000.0000000005) và
 *     đuôi hàng trăm dòng chỉ có ĐỊNH DẠNG, không có chữ nào.
 *
 * Chạy: php tools/test/fixtures/tao-xlsx.php
 */

$ss = array(
	'🏗 SETUP LẮP ĐẶT: NHÀ MA BÀ RỊA',   // 0
	'Nội dung hạng mục',                  // 1
	'Chi phí dự toán',                    // 2
	'Chi phí thực tế',                    // 3
	'Số lượng',                           // 4
	'Đơn giá',                            // 5
	'Thành tiền',                         // 6
	'Thuộc hạng mục lớn',                 // 7
	'Vật tư Khánh Thảo',                  // 8
	'Tủ Điện 24 tép',                     // 9
	'',                                   // 10 — công thức =IFERROR(...,"") trả rỗng
);

$rows = array();
$rows[] = '<row r="1"><c r="A1" t="s"><v>0</v></c></row>';
// A2/B2 là ô RỖNG kiểu thẻ tự đóng — dạng đã từng làm ô rỗng ăn giá trị của ô sau
$rows[] = '<row r="2"><c r="A2" s="3"/><c r="B2" s="3"/>'
	. '<c r="C2"><v>760127194</v></c><c r="E2" t="e"><v>#REF!</v></c></row>';
$rows[] = '<row r="3"></row>';
$rows[] = '<row r="4">'
	. '<c r="A4" t="s"><v>1</v></c><c r="B4" t="s"><v>2</v></c><c r="C4" t="s"><v>3</v></c>'
	. '<c r="D4" t="s"><v>4</v></c><c r="E4" t="s"><v>5</v></c><c r="F4" t="s"><v>6</v></c>'
	. '<c r="G4" t="s"><v>7</v></c></row>';
// Dòng hạng mục lớn: số thực chi để 0 khi nạp (tránh đếm hai lần)
$rows[] = '<row r="5"><c r="A5" t="s"><v>8</v></c><c r="B5"><v>4800000</v></c>'
	. '<c r="C5"><v>2405000.0000000005</v></c><c r="D5"><v>1</v></c>'
	. '<c r="E5"><v>2405000</v></c><c r="F5"><v>2405000.0000000005</v></c></row>';
// Dòng con: số của Google có đuôi float, không được biến thành 8,25 triệu tỉ
$rows[] = '<row r="6"><c r="A6" t="s"><v>9</v></c><c r="B6"><v>0</v></c>'
	. '<c r="C6"><v>825000.0000000001</v></c><c r="D6"><v>1</v></c>'
	. '<c r="E6"><v>825000</v></c><c r="F6"><v>825000.0000000001</v></c>'
	. '<c r="G6" t="s"><v>8</v></c></row>';
// Đuôi bảng: 40 dòng chỉ có định dạng / công thức trả rỗng -> phải bị cắt hết
for ( $r = 7; $r <= 46; $r++ ) {
	$rows[] = '<row r="' . $r . '" s="3" customFormat="1">'
		. '<c r="A' . $r . '" s="3"/>'
		. '<c r="B' . $r . '" s="3" t="s"><v>10</v></c>'
		. '<c r="C' . $r . '" s="3" t="str"><v></v></c></row>';
}

$sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
	. '<sheetData>' . implode( '', $rows ) . '</sheetData></worksheet>';

$si = '';
foreach ( $ss as $t ) { $si .= '<si><t>' . htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ) . '</t></si>'; }
$shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
	. count( $ss ) . '" uniqueCount="' . count( $ss ) . '">' . $si . '</sst>';

$wb = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
	. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
	. '<sheet name="DA NHÀ MA BÀ RỊA" sheetId="1" r:id="rId1"/></sheets></workbook>';

$rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
	. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
	. ' Target="worksheets/sheet1.xml"/></Relationships>';

$out = __DIR__ . '/nha-ma-ba-ria.xlsx';
@unlink( $out );
$zip = new ZipArchive();
if ( $zip->open( $out, ZipArchive::CREATE ) !== true ) { fwrite( STDERR, "không mở được zip\n" ); exit( 1 ); }
$zip->addFromString( 'xl/workbook.xml', $wb );
$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels );
$zip->addFromString( 'xl/sharedStrings.xml', $shared );
$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
$zip->close();
echo 'đã dựng ' . $out . ' (' . filesize( $out ) . " byte)\n";
