<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐỌC TỆP SAO KÊ TẢI LÊN — .xlsx và .csv
 *
 * Anh Thắng 11/09/2026, sau khi nhìn bảng cổng có hàng loạt dòng "chưa rõ máy" mà trên VietQR
 * thì tra ra cửa hàng: *"sẽ tải sao kê trên vietQR hệ thống tự đối chiếu ... upload lên bằng
 * file excel"*.
 *
 * =============================================================================================
 * 🔴 MỌI LỖI Ở ĐÂY ĐỀU LÀ LỆCH CỘT, VÀ LỆCH CỘT LÀ TIỀN VÀO SAI CƠ SỞ.
 *    Bài kiểm này dựng .xlsx THẬT (zip + XML đúng khuôn Excel) rồi đọc lại, vì ba cái bẫy của
 *    .xlsx chỉ lộ ra khi đọc tệp thật:
 *      · ô trống KHÔNG có trong XML -> đọc tuần tự là dồn cột;
 *      · chuỗi nằm ở `sharedStrings.xml`, ô chỉ giữ chỉ số;
 *      · trang tính đầu tiên không nhất thiết là `sheet1.xml`.
 *
 * 🔴 VÀ `is_uploaded_file` LÀ CHỐT AN NINH, KHÔNG PHẢI THỦ TỤC. Thiếu nó thì một lượt gửi khéo
 *    tay trỏ `tmp_name` vào `wp-config.php` và màn xem trước in khoá ra màn hình.
 *
 * Chạy: php tools/test/kiem-doc-tep-sao-ke.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
/* Plugin ghế nạp thủ công như `test-ghe.php` — `vhcp_test_boot()` chỉ dành cho plugin chi phí.
   Ở đây chỉ cần lớp đọc tệp và `VHG_Nhap::cot()`; cả hai đều là hàm thuần, không đụng CSDL. */
define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
foreach ( array( 'db', 'doc', 'tep', 'nhap' ) as $f ) {
	require_once VHG_DIR . 'includes/class-vhg-' . $f . '.php';
}

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$TMP = sys_get_temp_dir() . '/vhg-kiem-' . getmypid();
@mkdir( $TMP, 0777, true );
$DON = array();
function tep_tam( $ten, $noi_dung ) {
	global $TMP, $DON;
	$d = $TMP . '/' . $ten;
	file_put_contents( $d, $noi_dung );
	$DON[] = $d;
	return $d;
}

/**
 * Dựng một .xlsx THẬT từ bảng hai chiều. Ô rỗng CỐ Ý bỏ khỏi XML — đúng như Excel làm.
 * Mọi ô chữ đi qua `sharedStrings.xml`, mọi ô số để trần: hai đường khác nhau, kiểm cả hai.
 */
function lam_xlsx( $ten_tep, $bang, $ten_sheet_tep = 'sheet1.xml', $lam_lech_rels = false ) {
	global $TMP, $DON;
	$chuoi = array(); $map = array();
	foreach ( $bang as $h ) {
		foreach ( $h as $v ) {
			if ( '' === $v || is_numeric( $v ) ) { continue; }
			if ( ! isset( $map[ $v ] ) ) { $map[ $v ] = count( $chuoi ); $chuoi[] = $v; }
		}
	}
	$cot_ten = function ( $i ) {
		$s = '';
		$i++;
		while ( $i > 0 ) { $i--; $s = chr( 65 + ( $i % 26 ) ) . $s; $i = intdiv( $i, 26 ); }
		return $s;
	};
	$rows = '';
	foreach ( $bang as $r => $h ) {
		$rows .= '<row r="' . ( $r + 1 ) . '">';
		foreach ( $h as $c => $v ) {
			if ( '' === $v ) { continue; }   // 🔴 ô trống KHÔNG ghi ra — đúng như Excel
			$dc = $cot_ten( $c ) . ( $r + 1 );
			if ( is_numeric( $v ) ) {
				$rows .= '<c r="' . $dc . '"><v>' . $v . '</v></c>';
			} else {
				$rows .= '<c r="' . $dc . '" t="s"><v>' . $map[ $v ] . '</v></c>';
			}
		}
		$rows .= '</row>';
	}
	$sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<sheetData>' . $rows . '</sheetData></worksheet>';
	$ss = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
		. count( $chuoi ) . '">';
	foreach ( $chuoi as $x ) { $ss .= '<si><t>' . htmlspecialchars( $x, ENT_XML1 ) . '</t></si>'; }
	$ss .= '</sst>';

	$d = $TMP . '/' . $ten_tep;
	$z = new ZipArchive();
	$z->open( $d, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$z->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
	$z->addFromString( 'xl/sharedStrings.xml', $ss );
	$z->addFromString( 'xl/worksheets/' . $ten_sheet_tep, $sheet );
	/* Trang tính đầu trỏ tới đúng tệp trên; `$lam_lech_rels` dựng thêm một sheet1.xml RÁC để
	   bắt lỗi "cứ lấy sheet1.xml cho nhanh". */
	if ( $lam_lech_rels ) {
		$z->addFromString( 'xl/worksheets/sheet1.xml',
			'<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>TRANG RÁC</t></is></c></row></sheetData></worksheet>' );
	}
	$z->addFromString( 'xl/workbook.xml', '<?xml version="1.0"?><workbook '
		. 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
		. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<sheets><sheet name="Sao ke" sheetId="1" r:id="rId9"/></sheets></workbook>' );
	$z->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships '
		. 'xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId9" Target="worksheets/' . $ten_sheet_tep . '"/></Relationships>' );
	$z->close();
	$DON[] = $d;
	return $d;
}

/** Giả một lượt tải lên đã qua `is_uploaded_file` — bài kiểm chạy CLI nên hàm ấy luôn false. */
function nhu_tai_len( $duong, $ten ) {
	return array( 'tmp_name' => $duong, 'name' => $ten, 'size' => filesize( $duong ), 'error' => 0 );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. ĐỊA CHỈ Ô -> CHỈ SỐ CỘT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'A1 là cột 0',   0,  VHG_Tep::cot_tu_o( 'A1' ) );
teq( 'B7 là cột 1',   1,  VHG_Tep::cot_tu_o( 'B7' ) );
teq( 'Z1 là cột 25',  25, VHG_Tep::cot_tu_o( 'Z1' ) );
teq( '🔴 AA1 là cột 26 (hai chữ cái)', 26, VHG_Tep::cot_tu_o( 'AA1' ) );
teq( '🔴 AB100 là cột 27',             27, VHG_Tep::cot_tu_o( 'AB100' ) );
teq( 'chữ thường vẫn đọc được',        1,  VHG_Tep::cot_tu_o( 'b3' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. SỐ NGÀY CỦA EXCEL
 *
 * 🔴 MỐC 1899-12-30. Đếm từ 1900-01-01 là lệch ĐÚNG HAI NGÀY — đủ để một buổi đối soát không
 *    bao giờ khớp mà không ai hiểu vì sao.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 45911 là 11/09/2025', '2025-09-11', substr( VHG_Tep::ngay_excel( 45911 ), 0, 10 ) );
teq( 'kèm phần lẻ ra giờ',     '2025-09-11 12:00:00', VHG_Tep::ngay_excel( 45911.5 ) );
teq( '🔴 số tiền 20000 KHÔNG bị hiểu thành ngày', '', VHG_Tep::ngay_excel( 20000 ) );
teq( 'chuỗi không phải số thì thôi',              '', VHG_Tep::ngay_excel( 'AMBD 12' ) );
teq( 'số quá lớn cũng thôi',                      '', VHG_Tep::ngay_excel( 999999 ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. ĐỌC .xlsx THẬT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$BANG = array(
	array( 'Mã tham chiếu', 'Mã điểm bán', 'Mã cửa hàng', 'Số tiền đến (VND)', 'Nội dung TT', 'Thời gian tạo' ),
	array( 'VPBg37hatYHLg', 'VVB001', 'KH705MTDMN0020', '20000', 'PaymentForOrder', '11/09/2026 17:55' ),
	/* 🔴 HAI Ô TRỐNG Ở GIỮA — đúng ca làm lệch cột nếu đọc tuần tự. */
	array( 'VPBHt5PFZJdDr', '', '', '20000', 'AMBD 12', '11/09/2026 17:53' ),
	array( 'VPB2yGYPamz3U', 'VVB005', 'KH705MTDMN0005', '50000', 'ESTELA 06', '11/09/2026 17:44' ),
);
$d = lam_xlsx( 'sao-ke.xlsx', $BANG );
$kq = VHG_Tep::doc( nhu_tai_len( $d, 'sao-ke.xlsx' ) );
t( '🔴 CHỐT AN NINH: CLI không qua `is_uploaded_file` nên PHẢI bị chối',
	empty( $kq['ok'] ) && false !== mb_strpos( (string) $kq['error'], 'không phải tệp vừa tải lên' ), $kq );

/* Bỏ qua chốt ấy để kiểm phần đọc — bọc lại bằng một lớp con chỉ thay đúng chốt đó. */
eval( 'class VHG_Tep_Thu extends VHG_Tep {
	public static function doc_thu( $duong, $ten ) {
		$r = new ReflectionClass( "VHG_Tep" );
		$dau = (string) file_get_contents( $duong, false, null, 0, 4 );
		if ( 0 === strpos( $dau, "PK\x03\x04" ) ) { $m = $r->getMethod( "doc_xlsx" ); }
		else { $m = $r->getMethod( "doc_csv" ); }
		$m->setAccessible( true );
		return $m->invoke( null, $duong );
	}
}' );

$kq = VHG_Tep_Thu::doc_thu( $d, 'sao-ke.xlsx' );
t( 'đọc được tệp .xlsx', ! empty( $kq['ok'] ), $kq );
$b = isset( $kq['bang'] ) ? $kq['bang'] : array();
teq( 'đủ bốn hàng (tiêu đề + ba dòng)', 4, count( $b ) );
teq( '🔴 chuỗi tra đúng từ sharedStrings', 'Mã tham chiếu', $b[0][0] );
teq( '   cột cuối của tiêu đề',            'Thời gian tạo', $b[0][5] );
/* 🔴 CA CHÍNH: hàng có hai ô trống ở giữa. */
teq( '🔴 ô trống giữ đúng chỗ · cột 2 rỗng', '', $b[2][1] );
teq( '🔴                     · cột 3 rỗng', '', $b[2][2] );
teq( '🔴 SỐ TIỀN KHÔNG BỊ DỒN LÊN',         '20000', $b[2][3] );
teq( '🔴 nội dung vẫn đúng cột',            'AMBD 12', $b[2][4] );
teq( 'hàng đủ ô thì không xê dịch',         'KH705MTDMN0005', $b[3][2] );

/* 🔴 Trang tính đầu tiên đọc theo workbook.xml, không phải theo tên tệp. */
$d2 = lam_xlsx( 'lech.xlsx', $BANG, 'sheet7.xml', true );
$kq2 = VHG_Tep_Thu::doc_thu( $d2, 'lech.xlsx' );
teq( '🔴 lấy đúng trang khai trong workbook, không vớ sheet1.xml',
	'Mã tham chiếu', isset( $kq2['bang'][0][0] ) ? $kq2['bang'][0][0] : '(không có)' );
t( '   và KHÔNG đọc nhầm trang rác',
	! isset( $kq2['bang'][0][0] ) || 'TRANG RÁC' !== $kq2['bang'][0][0], $kq2['bang'][0] );

/* 🔴 Ô CHỮ VỠ THÀNH NHIỀU ĐOẠN. Người ta bôi đậm một chữ giữa ô là Excel tách ô ấy thành
   `<si><r><t>…</t></r><r><t>…</t></r></si>`. Lấy mỗi đoạn đầu là tên cửa hàng cụt mất nửa sau,
   và bản đồ máy học vào một cái tên không có thật. */
$dz = $TMP . '/doan.xlsx';
$z = new ZipArchive();
$z->open( $dz, ZipArchive::CREATE | ZipArchive::OVERWRITE );
$z->addFromString( 'xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
	. '<si><r><t>AEON MALL </t></r><r><t>BÌNH DƯƠNG</t></r></si></sst>' );
$z->addFromString( 'xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
	. '<sheetData><row r="1"><c r="A1" t="s"><v>0</v></c></row></sheetData></worksheet>' );
$z->close();
$DON[] = $dz;
$kqz = VHG_Tep_Thu::doc_thu( $dz, 'doan.xlsx' );
teq( '🔴 ô chữ vỡ nhiều đoạn phải nối lại đủ', 'AEON MALL BÌNH DƯƠNG', $kqz['bang'][0][0] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. ĐỌC .csv — BOM, dấu chấm phẩy, ô có dấu phẩy bên trong
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$csv = "\xEF\xBB\xBF" . "Mã tham chiếu,Mã cửa hàng,Số tiền đến (VND),Nội dung TT\n"
	. "VPB001,KH0020,20000,PaymentForOrder\n"
	. "VPB002,KH0005,50000,\"ESTELA 06, tầng 3\"\n";
$kq3 = VHG_Tep_Thu::doc_thu( tep_tam( 'a.csv', $csv ), 'a.csv' );
teq( '🔴 BOM bị cắt khỏi tên cột đầu', 'Mã tham chiếu', $kq3['bang'][0][0] );
teq( '   ba hàng',                     3, count( $kq3['bang'] ) );
teq( '🔴 ô có dấu phẩy bên trong KHÔNG vỡ thành hai cột', 'ESTELA 06, tầng 3', $kq3['bang'][2][3] );
teq( '   và hàng ấy vẫn đúng 4 cột',   4, count( $kq3['bang'][2] ) );

/* Excel bản tiếng Việt xuất .csv ngăn bằng dấu CHẤM PHẨY. */
$csv2 = "Mã tham chiếu;Mã cửa hàng;Số tiền đến (VND)\nVPB001;KH0020;20000\n";
$kq4 = VHG_Tep_Thu::doc_thu( tep_tam( 'b.csv', $csv2 ), 'b.csv' );
teq( '🔴 nhận ra dấu ngăn là chấm phẩy', 3, count( $kq4['bang'][0] ) );
teq( '   và đọc đúng ô cuối',            '20000', $kq4['bang'][1][2] );

/* TAB — dán từ Excel. */
$csv3 = "Mã tham chiếu\tMã cửa hàng\tSố tiền đến\nVPB001\tKH0020\t20000\n";
$kq5 = VHG_Tep_Thu::doc_thu( tep_tam( 'c.tsv', $csv3 ), 'c.tsv' );
teq( 'nhận ra dấu ngăn là TAB', 3, count( $kq5['bang'][0] ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. CỘT NHẬN RA ĐƯỢC — `VHG_Nhap::cot()` phải khớp trên chính tiêu đề đọc từ tệp
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$h = $b[0];
teq( 'nhận ra cột Mã tham chiếu', 0, VHG_Nhap::cot( $h, array( 'mã tham chiếu', 'tham chiếu' ) ) );
teq( 'nhận ra cột Mã cửa hàng',   2, VHG_Nhap::cot( $h, array( 'mã cửa hàng', 'cửa hàng' ) ) );
teq( 'nhận ra cột Số tiền',       3, VHG_Nhap::cot( $h, array( 'số tiền đến', 'số tiền' ) ) );
teq( 'nhận ra cột Nội dung',      4, VHG_Nhap::cot( $h, array( 'nội dung' ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. CA HỎNG — nói rõ hỏng gì, đừng nuốt
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = VHG_Tep::doc( null );
t( 'không chọn tệp → chối và nói rõ', empty( $r['ok'] ) && false !== mb_strpos( $r['error'], 'Chưa chọn tệp' ), $r );
$r = VHG_Tep::doc( array( 'tmp_name' => '', 'name' => 'x.xlsx' ) );
t( 'tmp_name rỗng → chối',            empty( $r['ok'] ), $r );

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
foreach ( $DON as $x ) { @unlink( $x ); }
@rmdir( $TMP );

echo "\n";
if ( $TRUOT ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — đọc được .xlsx và .csv, ô trống không làm lệch cột\n";
