<?php
/**
 * Kiểm đọc tệp nạp lên (.xlsx / .csv / .tsv) → văn bản tab, và các ô dán
 * nhận tệp thay cho dán.
 *
 *   php tools/kh-tai-chinh/tests/kiem-tep.php
 *
 * Tệp .xlsx dùng để kiểm được DỰNG NGAY TRONG KIỂM bằng ZipArchive, đúng cấu
 * trúc Excel ghi ra (sharedStrings, styles có numFmt ngày, ô số, ô công thức
 * lỗi, dòng trống ở giữa). Không kèm tệp thật của công ty vào kho mã.
 */

error_reporting( E_ALL );
set_error_handler( function ( $n, $s, $f, $l ) { throw new ErrorException( $s, 0, $n, $f, $l ); } );
require __DIR__ . '/gia-lap-wp.php';

$dat = 0; $hong = array();
function kiem( $ten, $that, $mong ) {
	global $dat, $hong;
	if ( $that === $mong ) { $dat++; return; }
	$hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) );
}
function co( $ten, $html, $chuoi ) { kiem( $ten, strpos( $html, $chuoi ) !== false, true ); }
function dung( $ham ) { ob_start(); try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; } return ob_get_clean(); }

$tam = sys_get_temp_dir() . '/khtc-kiem-tep-' . getmypid();
@mkdir( $tam );

/**
 * Dựng .xlsx tối giản nhưng đúng chuẩn.
 * $sheets = [ 'Tên sheet' => [ [ô, ô, ...], ... ] ]; ô là chuỗi, số (int/float),
 * hoặc ['ngay' => 46235.5] (số Excel, kiểu ngày), ['loi' => '#N/A'].
 */
function dung_xlsx( $duong, $sheets ) {
	$ss  = array(); $ss_idx = array();
	$ssi = function ( $s ) use ( &$ss, &$ss_idx ) {
		if ( ! isset( $ss_idx[ $s ] ) ) { $ss_idx[ $s ] = count( $ss ); $ss[] = $s; }
		return $ss_idx[ $s ];
	};
	$cot = function ( $n ) { $s = ''; while ( $n > 0 ) { $m = ( $n - 1 ) % 26; $s = chr( 65 + $m ) . $s; $n = intdiv( $n - 1, 26 ); } return $s; };
	$sheet_xml = array(); $i = 0;
	foreach ( $sheets as $ten => $dong ) {
		$i++;
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		$r = 0;
		foreach ( $dong as $d ) {
			$r++;
			if ( null === $d ) { continue; } // dòng trống — Excel không ghi <row>
			$x .= '<row r="' . $r . '">';
			$c = 0;
			foreach ( $d as $o ) {
				$c++;
				if ( null === $o ) { continue; }
				$ref = $cot( $c ) . $r;
				if ( is_array( $o ) && isset( $o['ngay'] ) ) {
					$x .= '<c r="' . $ref . '" s="1"><v>' . $o['ngay'] . '</v></c>';
				} elseif ( is_array( $o ) && isset( $o['gio'] ) ) {
					$x .= '<c r="' . $ref . '" s="2"><v>' . $o['gio'] . '</v></c>';
				} elseif ( is_array( $o ) && isset( $o['loi'] ) ) {
					$x .= '<c r="' . $ref . '" t="e"><f>VLOOKUP(1,2,3)</f><v>' . $o['loi'] . '</v></c>';
				} elseif ( is_int( $o ) || is_float( $o ) ) {
					$x .= '<c r="' . $ref . '"><v>' . $o . '</v></c>';
				} else {
					$x .= '<c r="' . $ref . '" t="s"><v>' . $ssi( (string) $o ) . '</v></c>';
				}
			}
			$x .= '</row>';
		}
		$x .= '</sheetData></worksheet>';
		$sheet_xml[ $i ] = array( $ten, $x );
	}
	$zip = new ZipArchive();
	$zip->open( $duong, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>' );
	$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
	$wb = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
	$rel = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
	foreach ( $sheet_xml as $n => $sx ) {
		$wb  .= '<sheet name="' . htmlspecialchars( $sx[0], ENT_XML1 ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
		$rel .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', $sx[1] );
	}
	$k = count( $sheet_xml );
	$rel .= '<Relationship Id="rId' . ( $k + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
	$rel .= '<Relationship Id="rId' . ( $k + 2 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
	$wb  .= '</sheets></workbook>';
	$zip->addFromString( 'xl/workbook.xml', $wb );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rel );
	$sst = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $ss ) . '" uniqueCount="' . count( $ss ) . '">';
	foreach ( $ss as $s ) { $sst .= '<si><t xml:space="preserve">' . htmlspecialchars( $s, ENT_XML1 ) . '</t></si>'; }
	$zip->addFromString( 'xl/sharedStrings.xml', $sst . '</sst>' );
	// s=0 General; s=1 numFmt 14 (ngày dựng sẵn); s=2 numFmt tự đặt "m/d/yy h:mm" (Payoo)
	$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="m/d/yy h:mm"/></numFmts><cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/><xf numFmtId="164" applyNumberFormat="1"/></cellXfs></styleSheet>' );
	$zip->close();
}

// 46235 = 01/08/2026 ; 46236.90967 ≈ 02/08/2026 21:49:56
$ngay_0108 = 46235;
$gio_0208  = 46236 + ( 21 * 3600 + 49 * 60 + 56 ) / 86400;

// ---------------------------------------------------------------- 1. ngày Excel
kiem( 'ngày Excel 46235 → 01/08/2026', KHTC_Tep::ngay_excel( 46235 ), '01/08/2026' );
kiem( 'ngày có giờ', KHTC_Tep::ngay_excel( $gio_0208 ), '02/08/2026 21:49:56' );
kiem( 'ngày 1 (01/01/1900)', KHTC_Tep::ngay_excel( 1 ), '01/01/1900' );
kiem( 'ngày 61 (01/03/1900, sau ngày 29/2 ma)', KHTC_Tep::ngay_excel( 61 ), '01/03/1900' );
kiem( 'mốc 1904', KHTC_Tep::ngay_excel( 44773, true ), '01/08/2026' );
kiem( 'doc_ngay đọc được ngày có giờ', KHTC_GiaoDich::doc_ngay( '02/08/2026 21:49:56' ), '2026-08-02' );

// ---------------------------------------------------------------- 2. số
kiem( 'số nguyên giữ nguyên', KHTC_Tep::so_chu( '1500000' ), '1500000' );
kiem( 'bỏ .0', KHTC_Tep::so_chu( '1500000.0' ), '1500000' );
kiem( 'giữ phần lẻ', KHTC_Tep::so_chu( '11000.5' ), '11000.5' );
kiem( 'mũ → số dài', KHTC_Tep::so_chu( '1.2345678901E+10' ), '12345678901' );
kiem( 'mã GD số dài không mất chữ số', KHTC_Tep::so_chu( '1006997907123456' ), '1006997907123456' );
kiem( 'cột AB = 28', KHTC_Tep::cot_so( 'AB12' ), 28 );
kiem( 'cột Z = 26', KHTC_Tep::cot_so( 'Z1' ), 26 );

// ---------------------------------------------------------------- 3. csv → tab
kiem( 'csv phẩy → tab', KHTC_Tep::ve_tab( "a,b,c\n1,\"x, y\",3" ), "a\tb\tc\n1\tx, y\t3" );
kiem( 'csv chấm phẩy (Excel VN) → tab', KHTC_Tep::ve_tab( "a;b;c\n1;2,5;3" ), "a\tb\tc\n1\t2,5\t3" );
kiem( 'đã tab thì để nguyên', KHTC_Tep::ve_tab( "a\tb,c\n1\t2" ), "a\tb,c\n1\t2" );
kiem( 'bỏ BOM UTF-8', KHTC_Tep::ve_utf8( "\xEF\xBB\xBFxin chào" ), 'xin chào' );
kiem( 'UTF-16LE (Excel "Unicode Text") → UTF-8', KHTC_Tep::ve_utf8( "\xFF\xFE" . mb_convert_encoding( 'Sao kê', 'UTF-16LE', 'UTF-8' ) ), 'Sao kê' );

// ---------------------------------------------------------------- 4. xlsx một sheet, dạng sao kê QR
$qr = $tam . '/qr.xlsx';
dung_xlsx( $qr, array(
	'Sheet1' => array(
		array( null, null, 250000 ),                                        // dòng tổng rác trên tiêu đề
		array( 'STT', 'Thời gian TT', 'Số tiền đến (VND)', 'Số tiền đi (VND)', 'Loại', 'Trạng thái', 'Mã tham chiếu', 'Mã đơn hàng', 'Mã điểm bán', 'Mã cửa hàng', 'Tài khoản nhận', 'Thời gian tạo', 'Nội dung TT' ),
		array( 1, array( 'gio' => $gio_0208 ), 50000, 0, 'Giao dịch đến', 'Thành công', 'FT001', 'VPB1', 'VVB1', 'W7DNR0ARCX', '11521268 - MB', array( 'ngay' => 46236 ), 'PaymentForOrder' ),
		null,                                                               // dòng trống giữa bảng
		array( 2, array( 'ngay' => $ngay_0108 ), 200000, 0, 'Giao dịch đến', 'Thành công', 'FT002', 'VPB2', 'VVB1', 'W7DNR0ARCX', '11521268 - MB', array( 'ngay' => 46235 ), 'Thanh toan' ),
		array( 3, array( 'ngay' => $ngay_0108 ), 70000, 0, 'Giao dịch đến', 'Huỷ', 'FT003', 'VPB3', 'VVB1', 'W7DNR0ARCX', '11521268 - MB', array( 'ngay' => 46235 ), 'Huy' ),
		array( null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, array( 'loi' => '#N/A' ) ),   // đuôi rác chỉ có công thức lỗi
		array( null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, array( 'loi' => '#N/A' ) ),
	),
) );
$ds = KHTC_Tep::doc_xlsx( $qr );
kiem( 'đọc được xlsx', is_wp_error( $ds ), false );
kiem( 'một sheet', count( $ds ), 1 );
kiem( 'tên sheet', $ds[0]['ten'], 'Sheet1' );
kiem( 'bỏ đuôi rác chỉ có #N/A', count( $ds[0]['dong'] ), 6 );
kiem( 'dòng trống giữa bảng giữ chỗ', $ds[0]['dong'][3], array() );
kiem( 'ô ngày+giờ ra như trên màn hình', $ds[0]['dong'][2][1], '02/08/2026 21:49:56' );
kiem( 'ô ngày kiểu 14 ra dd/mm/yyyy', $ds[0]['dong'][4][1], '01/08/2026' );
kiem( 'ô số nguyên', $ds[0]['dong'][2][2], '50000' );
kiem( 'ô chuỗi có dấu', $ds[0]['dong'][2][4], 'Giao dịch đến' );
kiem( 'ô trống trong dòng đầu giữ vị trí cột', $ds[0]['dong'][0], array( '', '', '250000' ) );

$bang = KHTC_Tep::doc_bang( $qr, 'qr.xlsx' );
kiem( 'doc_bang không lỗi', is_wp_error( $bang ), false );
$kq = KHTC_DanTho::doc( $bang['van_ban'] );
kiem( 'dán thô nhận ra QR từ văn bản đọc từ xlsx', $kq['dinh_dang'], 'qr' );
kiem( 'đủ 2 dòng thành công', count( $kq['rows'] ), 2 );
kiem( 'tổng tiền đúng', $kq['tong'], 250000 );
kiem( 'ngày dòng đầu (dán thô cắt phần giờ)', $kq['rows'][0]['ngay'], '02/08/2026' );
kiem( 'mã cửa hàng đúng cột', $kq['rows'][0]['ma_cua_hang'], 'W7DNR0ARCX' );
kiem( 'doc_ngay ra ISO', KHTC_GiaoDich::doc_ngay( $kq['rows'][0]['ngay'] ), '2026-08-02' );

// ---------------------------------------------------------------- 5. xlsx nhiều sheet — chọn sheet
$nhieu = $tam . '/nhieu.xlsx';
dung_xlsx( $nhieu, array(
	'Trống'        => array(),
	'DS xuất HĐ'   => array( array( 'STT', 'Ngày HĐ', 'Số HĐ' ), array( 1, array( 'ngay' => 46235 ), '00000123' ) ),
	'QR Posh HN'   => array(
		array( 'STT', 'Thời gian TT', 'Số tiền đến (VND)', 'Số tiền đi (VND)', 'Loại', 'Trạng thái', 'Mã tham chiếu', 'Mã đơn hàng', 'Mã điểm bán', 'Mã cửa hàng' ),
		array( 1, array( 'ngay' => 46235 ), 50000, 0, 'Giao dịch đến', 'Thành công', 'FT001', 'VPB1', 'VVB1', 'W7DNR0ARCX' ),
	),
	'VNPay'        => array(
		array( 'STT', 'Lọc ngày', 'Thời gian GD', 'Mã giao dịch', 'Chi nhánh', 'Mã điểm thu', 'Điểm thu' ),
		array( 1, 5, array( 'ngay' => 46235 ), '1006997907', 'FARM', 'FARMHUE1', 'FARM AEON MALL HUE' ),
	),
) );
$b = KHTC_Tep::doc_bang( $nhieu, 'nhieu.xlsx' );
kiem( 'mặc định lấy sheet có dữ liệu đầu tiên (bỏ sheet trống)', $b['trang'][1]['dung'], true );
kiem( 'ghi chú nêu sheet đã đọc', strpos( $b['ghi_chu'], 'Đọc sheet "DS xuất HĐ"' ), 0 );
co( 'ghi chú kể sheet còn lại', $b['ghi_chu'], 'QR Posh HN · VNPay' );
$b = KHTC_Tep::doc_bang( $nhieu, 'nhieu.xlsx', 'vnpay' );
kiem( 'chọn sheet theo tên, không phân biệt hoa thường', $b['trang'][3]['dung'], true );
co( 'văn bản đúng sheet đã chọn', $b['van_ban'], 'FARMHUE1' );
$b = KHTC_Tep::doc_bang( $nhieu, 'nhieu.xlsx', '3' );
kiem( 'chọn sheet theo số thứ tự', $b['trang'][2]['dung'], true );
$b = KHTC_Tep::doc_bang( $nhieu, 'nhieu.xlsx', 'Không có' );
kiem( 'sheet không có → lỗi', is_wp_error( $b ), true );
co( 'lỗi kể tên các sheet', $b->get_error_message(), 'Trống · DS xuất HĐ · QR Posh HN · VNPay' );

$moi = KHTC_Tep::doc_moi_trang( $nhieu, 'nhieu.xlsx' );
kiem( 'doc_moi_trang trả đủ 4 sheet', count( $moi['trang'] ), 4 );
$ung = array();
foreach ( $moi['trang'] as $t ) { $nd = KHTC_DanTho::nhan_dang( $t['van_ban'] ); if ( $nd ) { $ung[] = $t['ten'] . '=' . $nd[0]; } }
kiem( 'dán thô soi từng sheet: 2 sheet là bảng cổng, bỏ sheet hoá đơn', $ung, array( 'QR Posh HN=qr', 'VNPay=vnpay' ) );

// ---------------------------------------------------------------- 6. lỗi tệp
$b = KHTC_Tep::doc_bang( $tam . '/khong-co.xlsx', 'x.xlsx' );
kiem( 'tệp không có → lỗi', is_wp_error( $b ), true );
file_put_contents( $tam . '/a.xls', 'abc' );
$b = KHTC_Tep::doc_bang( $tam . '/a.xls', 'a.xls' );
co( '.xls đời cũ → bảo lưu thành .xlsx', $b->get_error_message(), 'Lưu thành .xlsx' );
file_put_contents( $tam . '/hong.xlsx', 'không phải zip' );
$b = KHTC_Tep::doc_bang( $tam . '/hong.xlsx', 'hong.xlsx' );
kiem( 'xlsx hỏng → lỗi, không nổ', is_wp_error( $b ), true );
file_put_contents( $tam . '/rong.csv', "\n\n" );
$b = KHTC_Tep::doc_bang( $tam . '/rong.csv', 'rong.csv' );
co( 'csv rỗng → báo rỗng', $b->get_error_message(), 'rỗng' );
file_put_contents( $tam . '/sk.csv', "\xEF\xBB\xBFNgày,Nội dung,Số tiền,Loại\n20/07/2026,\"Thu tien, khach ABC\",1500000,Thu\n" );
$b = KHTC_Tep::doc_bang( $tam . '/sk.csv', 'sk.csv' );
kiem( 'csv có BOM + ô ngoặc kép → tab', $b['van_ban'], "Ngày\tNội dung\tSố tiền\tLoại\n20/07/2026\tThu tien, khach ABC\t1500000\tThu\n" );

// ---------------------------------------------------------------- 7. lay(): không tệp → lấy ô dán; tệp lỗi → báo
$_FILES = array();
$_POST  = array( 'sao_ke' => "20/07/2026\tThu\t1000\tThu" );
$l = KHTC_Tep::lay( 'sao_ke' );
kiem( 'không tệp → lấy ô dán', $l['nguon'], 'dan' );
kiem( 'văn bản ô dán', $l['van_ban'], "20/07/2026\tThu\t1000\tThu" );
$_FILES = array( 'tep_sao_ke' => array( 'name' => 'a.xlsx', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0 ) );
$l = KHTC_Tep::lay( 'sao_ke' );
co( 'tệp quá lớn → câu tiếng người', $l['loi'], 'quá lớn' );
kiem( 'tệp lỗi thì KHÔNG rơi về ô dán', $l['van_ban'], '' );
$_FILES = array( 'tep_sao_ke' => array( 'name' => 'a.xlsx', 'tmp_name' => $qr, 'error' => 0, 'size' => 10 ) );
$l = KHTC_Tep::lay( 'sao_ke' );
co( 'tmp không phải upload thật → báo, không đọc bừa', $l['loi'], 'không lên được' );
$_FILES = array( 'tep_sao_ke' => array( 'name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0 ) );
$l = KHTC_Tep::lay( 'sao_ke' );
kiem( 'ô file để trống (error 4) → coi như không tệp, lấy ô dán', $l['nguon'], 'dan' );
kiem( 'co_tep sai khi để trống', KHTC_Tep::co_tep( 'tep_sao_ke' ), false );
$_FILES = array();

// ---------------------------------------------------------------- 8. màn hình: mọi ô dán đều có ô nạp tệp + form multipart
KHTC_Cty::chon( 'kh_cu' );
KHTC_Mau::nap();
$_POST = array(); $_GET = array();
$man = array(
	'giao_dich'    => array( 'tep_sao_ke' ),
	'doi_soat'     => array( 'tep_bang_cong' ),
	'chi_phi'      => array( 'tep_bang_chi_phi' ),
	'dan_tho'      => array( 'tep_tho' ),
	'danh_muc_diem'=> array( 'tep_bang_diem' ),
	'hoa_don_ra'   => array( 'tep_bang_hd' ),
	'hoa_don_vao'  => array( 'tep_bang_hdv' ),
	'phap_danh'    => array( 'tep_bang_pd' ),
	'ho_so'        => array( 'tep_bang_hs' ),
);
foreach ( $man as $ham => $o ) {
	if ( 'doi_soat' === $ham ) {
		// ô dán bảng cổng chỉ hiện khi đang mở một đợt
		global $wpdb; $dot = (int) $wpdb->get_var( 'SELECT id FROM ' . KHTC_DB::bang( 'doi_soat' ) . ' ORDER BY id LIMIT 1' );
		$_GET = array( 'dot' => $dot );
	}
	$html = dung( function () use ( $ham ) { KHTC_Trang::$ham(); } );
	$_GET = array();
	foreach ( $o as $ten ) {
		co( "$ham có ô nạp tệp $ten", $html, 'name="' . $ten . '"' );
	}
	co( "$ham form nhận tệp (multipart)", $html, 'enctype="multipart/form-data"' );
	co( "$ham nhận .xlsx", $html, 'accept=".xlsx' );
}
// dán thô không có ô Sheet (tự soi từng sheet), các ô khác có
$html = dung( function () { KHTC_Trang::dan_tho(); } );
kiem( 'dán thô không hỏi sheet', strpos( $html, 'name="trang_tho"' ), false );
$html = dung( function () { KHTC_Trang::hoa_don_vao(); } );
co( 'hoá đơn đầu vào có ô Sheet', $html, 'name="trang_bang_hdv"' );

// ---------------------------------------------------------------- 9. nạp qua tệp: tệp lỗi thì khối nạp không chạy, không "Đã nạp 0 dòng"
$_POST  = array( 'khtc_dan_hdv' => 1, 'bang_hdv' => '' );
$_FILES = array( 'tep_bang_hdv' => array( 'name' => 'x.xlsx', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0 ) );
$html = dung( function () { KHTC_Trang::hoa_don_vao(); } );
co( 'báo lỗi tệp lên chưa hết', $html, 'lên chưa hết' );
kiem( 'không có câu "Đã nạp"', strpos( $html, 'Đã nạp' ), false );
$_FILES = array(); $_POST = array( 'khtc_dan_hdv' => 1, 'bang_hdv' => '   ' );
$html = dung( function () { KHTC_Trang::hoa_don_vao(); } );
co( 'không tệp không dán → báo rõ', $html, 'Chưa chọn tệp, cũng chưa dán bảng' );
$_POST = array();

// ---------------------------------------------------------------- 9b. nạp nhầm file cổng vào ô khác → chỉ sang Dán thô, không "Đã nạp 0"
$payoo = "\t\t\t\t\t\tSố lượng\tSố tiền (₫)\n\t\t\t\tTổng cộng\t\t46\t8.191.000₫\n\n"
	. "STT\tCửa hàng\tNgày giao dịch\tNgày tổng kết GD trên POS\tLoại tác nghiệp\tLoại giao dịch\tHình thức thanh toán\tChi tiết H.Thức T.Toán\tNgân hàng phát hành\tLoại thẻ thanh toán\tHình thức phát hành thẻ\tĐặc điểm giao dịch\tMã QR\tSố hóa đơn\tMã chuẩn chi\tMã đơn hàng\tMã khách hàng\tSố tiền thanh toán\tPhí xử lý giao dịch\n"
	. "1\tDVGIAITRIKH_FZ_IPH\t23/09/2026 21:51:41\t\tThanh toán\tBán hàng\tQuét mã QR\t\t\t\t\t\tQR5G4T5W\tFG4T5W\t\t\t\t30000\t165\n";
foreach ( array( 'hoa_don_vao' => array( 'khtc_dan_hdv', 'bang_hdv' ), 'giao_dich' => array( 'khtc_dan', 'sao_ke' ), 'chi_phi' => array( 'khtc_dan_cp', 'bang_chi_phi' ) ) as $ham => $o ) {
	$_POST = array( $o[0] => 1, $o[1] => $payoo, 'dan_ngan_hang_id' => 1 );
	$html  = dung( function () use ( $ham ) { KHTC_Trang::$ham(); } );
	co( "$ham: nhận ra là file Payoo", $html, 'là file Payoo' );
	co( "$ham: chỉ sang Dán thô", $html, 'Dán thô' );
	kiem( "$ham: không có câu Đã nạp", strpos( $html, 'Đã nạp' ), false );
}
$_POST = array();
// còn ở đúng chỗ (Dán thô) thì vẫn nhận bình thường
$_POST = array( 'tho' => $payoo );
$html  = dung( function () { KHTC_Trang::dan_tho(); } );
co( 'Dán thô vẫn nhận Payoo', $html, 'Nhận ra:' );
$_POST = array();

// ---------------------------------------------------------------- 10. dán thô với tệp nhiều sheet cổng → bảng chọn sheet (giả lập qua doc_moi_trang)
// is_uploaded_file không giả được, nên kiểm phần chọn sheet bằng đúng dữ liệu mà lay_moi_trang trả về.
$moi = KHTC_Tep::doc_moi_trang( $nhieu, 'nhieu.xlsx' );
$ung = array();
foreach ( $moi['trang'] as $t ) { $nd = KHTC_DanTho::nhan_dang( $t['van_ban'] ); if ( $nd ) { $ung[] = $nd[1]['ten']; } }
kiem( 'hai sheet cổng → phải hỏi người chọn', count( $ung ) > 1, true );
// sheet đã chọn đưa vào ô "tho" như dán → xem trước bình thường
$_POST = array( 'tho' => $moi['trang'][2]['van_ban'] );
$html  = dung( function () { KHTC_Trang::dan_tho(); } );
co( 'xem trước nhận ra QR', $html, 'Nhận ra:' );
$_POST = array();

// ---------------------------------------------------------------- 11. nút đổi KH Cũ / KH Mới phải có tác dụng ở MỌI màn hình
// Lỗi thật: Danh mục điểm, Dán thô, Sinh từ sao kê, Người dùng không nhận nút,
// bấm KH Mới đứng nguyên KH Cũ — người dùng không biết vì sao.
$r = new ReflectionClass( 'KHTC_Trang' );
$man_hinh = array();
foreach ( $r->getMethods( ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC ) as $m ) {
	if ( $m->isPublic() && $m->isStatic() && $m->getNumberOfParameters() === 0 && ! in_array( $m->getName(), array( 'url', 'url_form' ), true ) ) { $man_hinh[] = $m->getName(); }
}
kiem( 'liệt kê được các màn hình', count( $man_hinh ) >= 18, true );
foreach ( $man_hinh as $ham ) {
	KHTC_Cty::chon( 'kh_cu' );
	$_POST = array( 'khtc_cty' => 'kh_moi' ); $_GET = array();
	try { dung( function () use ( $ham ) { KHTC_Trang::$ham(); } ); } catch ( Throwable $e ) { $hong[] = "$ham nổ khi đổi cty: " . $e->getMessage(); continue; }
	kiem( "$ham: bấm KH Mới thì sang KH Mới", KHTC_Cty::dang_chon(), 'kh_moi' );
}
$_POST = array(); KHTC_Cty::chon( 'kh_cu' );
// khung web chung cũng nhận
$kw = new ReflectionMethod( 'KHTC_Web', 'khung' ); $kw->setAccessible( true );
$_POST = array( 'khtc_cty' => 'kh_moi' ); $GLOBALS['khtc_qv'] = array( 'khtc_man' => 'danh-muc-diem' );
dung( function () use ( $kw ) { $kw->invoke( null, 'danh-muc-diem' ); } );
kiem( 'khung web: bấm KH Mới ở Danh mục điểm → sang KH Mới', KHTC_Cty::dang_chon(), 'kh_moi' );
$_POST = array(); KHTC_Cty::chon( 'kh_cu' );

foreach ( glob( $tam . '/*' ) as $f ) { @unlink( $f ); }
@rmdir( $tam );

echo $dat, " đạt\n";
if ( $hong ) { echo "HỎNG:\n  ", implode( "\n  ", $hong ), "\n"; exit( 1 ); }
