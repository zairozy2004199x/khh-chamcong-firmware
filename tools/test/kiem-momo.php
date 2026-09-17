<?php
/**
 * ĐỌC FILE "MoMo payments" CỦA FABi — bằng chính file thật anh Thắng gửi 16/09/2026.
 *
 * File ấy là nền của cả phép đối soát MoMo, và nó có ba cái bẫy đã sập ngay lần đọc đầu:
 *
 *   1. MỖI TRANG TÍNH LÀ MỘT CỬA HÀNG, và tên trang bị Excel CẮT Ở 31 KÝ TỰ:
 *      "FUNZONE CITY VŨNG TÀU ( Dịch Vụ" — thiếu đuôi so với tên cơ sở trong số liệu POS. Giữ
 *      nguyên tên cụt là mọi giao dịch của quán ấy nằm riêng một cơ sở không tồn tại.
 *   2. MỖI TRANG CÓ HAI KHỐI NẰM CẠNH NHAU — QR Tĩnh (cột A–F) và QR Động (cột H–L). Đọc một
 *      khối là mất nguyên một dòng tiền mà tổng vẫn trông hợp lý.
 *   3. Cột "Mã đối tác" chính là mã giao dịch MoMo — thứ để ghép hai sổ. Mất nó thì chỉ còn ghép
 *      mờ theo ngày + số tiền.
 *
 * 🔴 BÀI NÀY TỰ DỰNG FILE MẪU, KHÔNG MANG FILE THẬT VÀO KHO.
 *    Kho này CÔNG KHAI. File anh Thắng gửi có 3.851 giao dịch thật, kèm mã hoá đơn và số tiền của
 *    từng quán — đưa vào đây là công bố doanh thu chi tiết của cả chuỗi để đổi lấy một bài kiểm.
 *    Bản 3.88.0 vừa phải đi gỡ số căn cước và họ tên thật khỏi kho vì đúng lý do ấy; không lặp lại.
 *
 *    Nên file mẫu được dựng ngay trong bài, GIỮ NGUYÊN HÌNH DẠNG của file thật (12 trang, tên
 *    trang bị cắt ở 31 ký tự, hai khối tĩnh/động, cột Mã đối tác) mà số liệu thì bịa.
 *
 * Chạy: php tools/test/kiem-momo.php
 */

require_once __DIR__ . '/fw/bo-do-khh-dt.php';

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/sao-ke.php';
require_once $goc . '/momo.php';

$dat  = 0;
$hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) {
		$dat++;
	} else {
		$hong[] = $ten;
	}
}

/* Danh sách cơ sở như trong kho POS — tên ĐẦY ĐỦ, để phép ghép tên cụt có chỗ mà về. */
$GLOBALS['KHH_DT_TEST_CH'] = array(
	'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )',
	'TuTu Train - Aeon Tân Phú ( Dịch Vụ K&H )',
	'(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ ( Dịch Vụ và Giải Trí K&H )',
	'Tutu Train - Estella ( Dịch vụ K&H )',
	'Tutu Train - Bình Dương ( Dịch Vụ và Giải Trí K&H )',
	'ECO FARM LOTTE PHAN THIẾT ( Dịch Vụ K&H )',
	'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )',
	'TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )',
	'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )',
	'Tutu Train - Aeon Tân An ( Dịch vụ K&H )',
	'VR Fun Aeon Tân An ( Dịch Vụ K&H )',
	'COFFE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )',
);

/**
 * Dựng một file .xlsx đúng hình dạng file FABi xuất ra.
 *
 * @param array $quan [ tên trang (đã cắt 31 ký tự) => [ [ma_dt, ma_hd, so_hd, luc, tien, loai], … ] ]
 */
function dung_file_momo( $quan ) {
	$tep = tempnam( sys_get_temp_dir(), 'momo' ) . '.xlsx';
	$zip = new ZipArchive();
	$zip->open( $tep, ZipArchive::CREATE | ZipArchive::OVERWRITE );

	$ten_trang = array_keys( $quan );
	$sheets    = '';
	$rels      = '';
	foreach ( $ten_trang as $i => $t ) {
		$n       = $i + 1;
		$sheets .= '<sheet name="' . htmlspecialchars( $t, ENT_XML1 ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
		$rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
	}
	$zip->addFromString(
		'[Content_Types].xml',
		'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
		. '<Default Extension="xml" ContentType="application/xml"/>'
		. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/></Types>'
	);
	$zip->addFromString(
		'_rels/.rels',
		'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rIdWB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'
	);
	$zip->addFromString(
		'xl/workbook.xml',
		'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
		. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
		. $sheets . '</sheets></workbook>'
	);
	$zip->addFromString(
		'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. $rels . '</Relationships>'
	);

	$o = function ( $ref, $gt ) {
		/* Ghi thẳng chuỗi vào ô (inlineStr) — khỏi phải dựng sharedStrings. */
		return '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars( (string) $gt, ENT_XML1 ) . '</t></is></c>';
	};
	foreach ( $ten_trang as $i => $t ) {
		$x  = '<row r="1">' . $o( 'A1', 'Giao dịch QR Tĩnh' ) . $o( 'H1', 'Giao dịch QR Động' ) . '</row>';
		$x .= '<row r="2">';
		foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F' ) as $j => $c ) {
			$x .= $o( $c . '2', array( 'Mã hoá đơn', 'Số HĐ', 'Ngày bán', 'Mã đối tác', 'Tổng tiền', 'Trạng thái' )[ $j ] );
		}
		foreach ( array( 'H', 'I', 'J', 'K', 'L' ) as $j => $c ) {
			$x .= $o( $c . '2', array( 'Mã hoá đơn', 'Số HĐ', 'Ngày bán', 'Mã đối tác', 'Tổng tiền' )[ $j ] );
		}
		$x .= '</row>';
		$hang = 3;
		foreach ( $quan[ $t ] as $g ) {
			list( $ma_dt, $ma_hd, $so_hd, $luc, $tien, $loai, $tt ) = $g;
			$c = ( 'tinh' === $loai ) ? array( 'A', 'B', 'C', 'D', 'E', 'F' ) : array( 'H', 'I', 'J', 'K', 'L' );
			$x .= '<row r="' . $hang . '">'
				. $o( $c[0] . $hang, $ma_hd ) . $o( $c[1] . $hang, $so_hd ) . $o( $c[2] . $hang, $luc )
				. $o( $c[3] . $hang, $ma_dt ) . $o( $c[4] . $hang, number_format( $tien, 0, ',', ',' ) . ' ₫' )
				. ( 'tinh' === $loai ? $o( 'F' . $hang, $tt ) : '' )
				. '</row>';
			$hang++;
		}
		$zip->addFromString(
			'xl/worksheets/sheet' . ( $i + 1 ) . '.xml',
			'<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
			. $x . '</sheetData></worksheet>'
		);
	}
	$zip->close();
	return $tep;
}

/* Tên trang CẮT ĐÚNG 31 KÝ TỰ, y như Excel làm với file thật. */
function cat31( $s ) {
	return mb_substr( $s, 0, 31 );
}

$tep = dung_file_momo(
	array(
		cat31( 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )' ) => array(
			array( '146972800768', 'NGGPG2NYGY22BV48ZOO4OFFF', '190148', '15/09/2026 19:56', 20000, 'dong', '' ),
			array( '146971558848', 'NGGPG2NYGY2216PMHVP0JN8C', '190146', '15/09/2026 19:51', 100000, 'dong', '' ),
			array( '146967077411', 'NGGPG2NYGY22BV48X8KDP8GK', '190124', '14/09/2026 19:33', 80000, 'dong', '' ),
		),
		cat31( 'TuTu Train - Lotte Gò Vấp ( Dịch vụ K&H )' )        => array(
			array( '146986275513', 'JE74BKKP1WKL16PMJ8685772', '150083', '15/09/2026 21:23', 120000, 'dong', '' ),
			/* Một dòng khối QR TĨNH, có cả cột Trạng thái — khối này hay bị bỏ quên. */
			array( '146986000001', 'JE74BKKP1WKLTINH00000001', '150001', '15/09/2026 09:10', 50000, 'tinh', 'Thành công' ),
			array( '146986000002', 'JE74BKKP1WKLTINH00000002', '150002', '15/09/2026 09:20', 30000, 'tinh', 'Thất bại' ),
		),
	)
);
phep( 'dựng được file mẫu', file_exists( $tep ) );

$kq = khh_dt_doc_momo_pos( $tep );
phep( 'đọc được file', ! is_wp_error( $kq ) );
if ( is_wp_error( $kq ) ) {
	echo "\n✗ " . $kq->get_error_message() . "\n";
	exit( 1 );
}

phep( 'đọc đủ số trang tính', 2 === (int) $kq['so_trang'] );
phep( 'đọc đủ giao dịch của CẢ HAI khối', 6 === count( $kq['dong'] ) );
phep( 'không bỏ sót dòng nào', 0 === (int) $kq['bo_qua'] );

$co_tinh = false;
$co_dong = false;
foreach ( $kq['dong'] as $x ) {
	if ( 'tinh' === $x['loai'] ) { $co_tinh = true; }
	if ( 'dong' === $x['loai'] ) { $co_dong = true; }
}
phep( 'đọc được khối QR Tĩnh', $co_tinh );
phep( 'đọc được khối QR Động', $co_dong );

$tong = 0;
$theo = array();
foreach ( $kq['dong'] as $d ) {
	$tong += $d['so_tien'];
	$theo[ $d['cua_hang'] ] = ( isset( $theo[ $d['cua_hang'] ] ) ? $theo[ $d['cua_hang'] ] : 0 ) + $d['so_tien'];
}
phep( 'cộng đúng tổng tiền', 400000.0 === (float) $tong );
phep( 'ra đúng số cơ sở', 2 === count( $theo ) );

/* 🔴 TÊN TRANG BỊ CẮT PHẢI VỀ ĐÚNG TÊN CƠ SỞ ĐẦY ĐỦ. */
phep( 'tên trang cụt ghép về tên cơ sở đầy đủ',
	isset( $theo['FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )'] )
	&& 200000.0 === (float) $theo['FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )'] );
phep( 'không còn cơ sở nào mang tên cụt',
	! isset( $theo[ cat31( 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )' ) ] ) );

wp_delete_file( $tep );
$d = $kq['dong'][0];
phep( 'giữ được mã đối tác (mã giao dịch MoMo)', preg_match( '/^\d{10,}$/', $d['ma_doi_tac'] ) );
phep( 'giữ được mã hoá đơn của FABi', '' !== $d['ma_hd'] );
phep( 'đọc đúng ngày', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['ngay'] ) );
phep( 'đọc đúng giờ', $d['gio'] >= 0 && $d['gio'] <= 23 );
phep( 'đọc được số tiền có dấu phẩy và ký hiệu ₫', $d['so_tien'] > 0 );

/* Mã đối tác phải DUY NHẤT — nó là khoá ghép hai sổ, trùng là ghép nhầm. */
$ma = array();
foreach ( $kq['dong'] as $x ) {
	if ( '' !== $x['ma_doi_tac'] ) {
		$ma[ $x['ma_doi_tac'] ] = true;
	}
}
phep( 'mã đối tác không trùng nhau', count( $ma ) === count( $kq['dong'] ) );

/* ================================================================== *
 * SAO KÊ MOMO (.csv tải từ trang MoMo)
 *
 * Ba cái bẫy của file thật, đã sập đủ cả ba lần đọc đầu:
 *   1. FILE CÓ BOM UTF-8. Không cắt BOM thì tên cột đầu thành "\u{feff}Thời gian" và mọi phép
 *      dò cột trượt hết — báo "không thấy cột Thời gian" trong khi cột ấy nằm ngay đó.
 *   2. MOMO GHI DẤU TÁCH RỜI ở vài tên cột ("gốc" = g + ô + dấu sắc rời). Bỏ dấu kiểu bảng tra
 *      ký tự dựng sẵn không đụng tới được, phải gỡ dấu rời trước.
 *   3. Cột "Mã giao dịch" CHÍNH LÀ "Mã đối tác" bên FABi — khoá ghép hai sổ. Đo trên hai file
 *      thật ngày 16/09/2026: 995 trong 1.008 mã MoMo có mặt trong file FABi.
 * ================================================================== */

$csv = get_temp_dir() . '/kiem-momo-sk-' . wp_generate_password( 8, false ) . '.csv';
$h   = "\xEF\xBB\xBF" . "Thời gian,Mã đơn hàng,Mã đơn hàng go\xCC\x81c,Mã giao dịch,Trạng thái," .
	"Tên khách hàng,Số điện thoại khách hàng,Loại giao dịch,Số tiền,Số tiền giảm giá," .
	"Kênh thanh toán,Phương thức thanh toán,Nguồn tiền,Mô tả giao dịch,Mã cửa hàng,Tên cửa hàng\n";
$h .= "16-09-2026 14:44:48,DH01,,147078195272,Thành công,Khách A,09xxxx,Thanh toán,\"90,000\",0," .
	"QR,QR Code,Ví MoMo,Mua hàng,KHTUTU2,Tutu Train - Estella\n";
$h .= "15-09-2026 09:10:00,DH02,,147078195273,Thành công,Khách B,09xxxx,Thanh toán,\"120,000\",0," .
	"QR,QR Code,Ví MoMo,Mua hàng,KHECO2,ECO FARM LOTTE PHAN THIẾT\n";
$h .= "15-09-2026 10:00:00,DH03,,147078195274,Thất bại,Khách C,09xxxx,Thanh toán,0,0," .
	"QR,QR Code,Ví MoMo,Mua hàng,KHECO2,ECO FARM LOTTE PHAN THIẾT\n";
file_put_contents( $csv, $h );

$sk = khh_dt_doc_momo_sk( $csv );
phep( 'đọc được file sao kê MoMo có BOM', ! is_wp_error( $sk ) );
if ( ! is_wp_error( $sk ) ) {
	phep( 'đếm đủ dòng', 3 === (int) $sk['so_dong'] );
	phep( 'bỏ dòng số tiền bằng 0', 1 === (int) $sk['bo_qua'] && 2 === count( $sk['dong'] ) );
	phep( 'cộng đúng tiền có dấu phẩy', 210000.0 === (float) array_sum( wp_list_pluck( $sk['dong'], 'so_tien' ) ) );
	phep( 'ra đủ mã cửa hàng MoMo', 2 === count( $sk['quan'] ) && isset( $sk['quan']['KHTUTU2'] ) );
	$d0 = $sk['dong'][0];
	phep( 'đọc đúng ngày kiểu 16-09-2026', '2026-09-16' === $d0['ngay'] );
	phep( 'đọc đúng giờ', 14 === (int) $d0['gio'] );
	phep( 'giữ mã giao dịch để ghép với Mã đối tác bên FABi', '147078195272' === $d0['ma_gd'] );
	phep( 'giữ trạng thái để lọc giao dịch hỏng', 'Thành công' === $d0['trang_thai'] );
}
wp_delete_file( $csv );

/* Dấu tách rời phải được gỡ, nếu không cột "gốc" trượt và bài trên đã bắt được rồi. */
phep( 'bỏ được dấu tách rời của MoMo', 'ma don hang goc' === khh_dt_khong_dau( "Mã đơn hàng go\xCC\x81c" ) );

/* ================================================================== *
 * PHẠM VI SỔ MOMO
 *
 * 🔴 SỔ MOMO KHÔNG PHỦ HẾT CHUỖI. File thật có 4 quán, kho POS có 12. So thẳng là đẻ ra hàng
 *    nghìn dòng "MoMo thiếu tiền" của những quán vốn không nằm trong sổ.
 * ================================================================== */

$sk_mau = array(
	array( 'ngay' => '2026-09-15', 'tien' => 90000, 'ten' => 'Tutu Train - Estella', 'ma' => 'M1', 'ma_ch' => 'KHTUTU2' ),
	array( 'ngay' => '2026-09-16', 'tien' => 90000, 'ten' => 'Tutu Train - Estella', 'ma' => 'M2', 'ma_ch' => 'KHTUTU2' ),
);
$pv = khh_dt_pham_vi_momo_sk( $sk_mau );
phep( 'lấy đúng ngày đầu của sổ MoMo', '2026-09-15' === $pv['ngay_dau'] );
phep( 'lấy đúng ngày cuối của sổ MoMo', '2026-09-16' === $pv['ngay_cuoi'] );

/* Ngược chiều: sổ MoMo chạy dài hơn bản xuất FABi thì mấy ngày dôi ra không phải "máy bỏ sót". */
$pvp = khh_dt_pham_vi_momo_pos(
	array(
		array( 'ngay' => '2026-09-01' ),
		array( 'ngay' => '2026-09-15' ),
	)
);
phep( 'lấy đúng kỳ của kho POS', '2026-09-01' === $pvp['ngay_dau'] && '2026-09-15' === $pvp['ngay_cuoi'] );

$pv2 = array( 'ngay_dau' => '2026-09-15', 'ngay_cuoi' => '2026-09-16', 'co_so' => array( 'Quán A' => true ) );
phep( 'quán trong sổ thì đem ra kết luận',
	khh_dt_trong_pham_vi_momo( array( 'ngay' => '2026-09-15', 'cua_hang' => 'Quán A' ), $pv2 ) );
phep( 'quán NGOÀI sổ thì không kể là lệch',
	! khh_dt_trong_pham_vi_momo( array( 'ngay' => '2026-09-15', 'cua_hang' => 'Quán B' ), $pv2 ) );
phep( 'ngày ngoài kỳ của sổ cũng không kể là lệch',
	! khh_dt_trong_pham_vi_momo( array( 'ngay' => '2026-09-01', 'cua_hang' => 'Quán A' ), $pv2 ) );
phep( 'chưa biết sổ phủ quán nào thì đừng loại ai',
	khh_dt_trong_pham_vi_momo(
		array( 'ngay' => '2026-09-15', 'cua_hang' => 'Quán B' ),
		array( 'ngay_dau' => '', 'ngay_cuoi' => '', 'co_so' => array() )
	) );

/* ================================================================== *
 * HÚT FILE MOMO CÓ SẴN TRÊN MÁY CHỦ
 *
 * 🔴 ANH THẮNG HỎI: "NẠP TRÊN SAO KÊ RỒI, SAO PHẢI NẠP LẠI LẦN 2?" — nên nếu file gốc còn nằm
 *    trong thư mục tải lên thì phải nhặt được nó. Nhặt SAI thì tệ hơn: đọc bừa file khác trong
 *    uploads là đem dữ liệu chẳng liên quan vào kho tiền.
 * ================================================================== */

$up  = wp_upload_dir();
$thu = rtrim( (string) $up['basedir'], '/' ) . '/kiem-momo-' . wp_generate_password( 8, false );
mkdir( $thu . '/2026/09', 0777, true );
file_put_contents( $thu . '/2026/09/Transaction_report_16_09_2026.csv', 'x' );
file_put_contents( $thu . '/2026/09/transaction-report-01.csv', 'x' );
file_put_contents( $thu . '/2026/09/anh-cua-hang.jpg', 'x' );
file_put_contents( $thu . '/2026/09/bao-cao-ban-hang.csv', 'x' );

$san = khh_dt_file_momo_san();
$ten = wp_list_pluck( $san, 'ten' );
phep( 'nhặt được file MoMo nằm sâu trong thư mục con', in_array( 'Transaction_report_16_09_2026.csv', $ten, true ) );
phep( 'nhặt cả bản tên viết gạch nối', in_array( 'transaction-report-01.csv', $ten, true ) );
phep( 'không đụng tới ảnh', ! in_array( 'anh-cua-hang.jpg', $ten, true ) );
/* 🔴 CÁI NÀY QUAN TRỌNG NHẤT: file .csv khác trong uploads KHÔNG ĐƯỢC nhặt nhầm. */
phep( 'không nhặt nhầm file .csv khác', ! in_array( 'bao-cao-ban-hang.csv', $ten, true ) );
phep( 'chỉ ra đúng hai file', 2 === count( $san ) );
phep( 'không đưa đường dẫn thật ra ngoài qua REST',
	! array_key_exists( 'duong', khh_dt_rest_momo_file()['file_ds'][0] ) );

array_map( 'unlink', glob( $thu . '/2026/09/*' ) );
rmdir( $thu . '/2026/09' );
rmdir( $thu . '/2026' );
rmdir( $thu );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
   LÕI GHÉP HAI SỔ — `khh_dt_doi_soat_momo_lam()`

   🔴 TRƯỚC 1.30.0 HÀM NÀY KHÔNG CÓ MỘT PHÉP THỬ NÀO. Bài này canh hai hàm ĐỌC FILE, còn hàm
   KẾT LUẬN VỀ TIỀN — "giao dịch nào thiếu, giao dịch nào lệch" — thì không ai chạm, vì muốn
   gọi nó phải dựng cả MySQL. Nay lõi đã tách khỏi phần đọc database nên thử được bằng con số.
   ══════════════════════════════════════════════════════════════════════════════════════════ */

/* Hai tên cơ sở dưới đây LẤY ĐÚNG từ danh sách ở đầu bài (`KHH_DT_TEST_CH`).
   ⚠️ ĐỪNG bịa tên: `khh_dt_ten_co_so_gan()` trả RỖNG cho tên nó không nhận ra, và lõi ghép thì
      bỏ qua chốt cơ sở khi tên rỗng — nên tên bịa làm phép "không bắc cầu sang quán khác" xanh
      hay đỏ tuỳ may, không nói được gì về mã. Đã hụt đúng vậy một lần lúc viết bài này. */
define( 'CH_A', 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )' );
define( 'CH_B', 'TuTu Train - Aeon Tân Phú ( Dịch Vụ K&H )' );

/** Một dòng máy POS. Chỉ khai những trường mà lõi ghép thật sự đọc. */
function gd_pos( $ma, $ngay, $tien, $ch = CH_A, $tt = 'Thành công' ) {
	return array(
		'ma_doi_tac' => $ma,
		'ngay'       => $ngay,
		'so_tien'    => $tien,
		'cua_hang'   => $ch,
		'trang_thai' => $tt,
	);
}
/** Một dòng sổ MoMo. */
function gd_sk( $ma, $ngay, $tien, $ten = CH_A ) {
	return array( 'ma' => $ma, 'ngay' => $ngay, 'tien' => $tien, 'ten' => $ten );
}

/* ---- Ca cơ bản: khớp · lệch tiền · chỉ một bên · giao dịch lỗi ---- */
$r = khh_dt_doi_soat_momo_lam(
	array(
		gd_pos( 'M1', '2026-09-12', 50000 ),
		gd_pos( 'M2', '2026-09-12', 70000 ),          // sổ ghi 71.000 -> lệch
		gd_pos( 'M3', '2026-09-12', 30000 ),          // sổ không có   -> chỉ bên máy
		gd_pos( 'M9', '2026-09-12', 90000, CH_A, 'Thất bại' ),
	),
	array(
		gd_sk( 'M1', '2026-09-12 08:00', 50000 ),
		gd_sk( 'M2', '2026-09-12 09:00', 71000 ),
		gd_sk( 'M8', '2026-09-12 10:00', 12000 ),     // máy không có -> chỉ bên MoMo
	)
);
phep( 'ghép mã: 1 khớp', 1 === $r['so_khop'] );
phep( 'lệch tiền đếm riêng, không gọi là thiếu', 1 === $r['so_lech'] );
phep( 'lệch tiền kể đúng số bên sổ', ! empty( $r['lech'] ) && 71000.0 === (float) $r['lech'][0]['sk_tien'] );
phep( 'chỉ có bên máy: 1', 1 === $r['so_chi_pos'] );
phep( 'chỉ có bên MoMo: 1', 1 === $r['so_chi_sk'] );
phep( 'giao dịch LỖI ở máy đếm riêng, không dồn vào thiếu', 1 === $r['so_loi_pos'] );
phep( 'giao dịch lỗi KHÔNG bị tính là chỉ-bên-máy', 1 === $r['so_chi_pos'] );

/* ══ CA CHỐT CỦA 1.30.0: GHÉP MỜ KHÔNG ĐƯỢC TRANH DÒNG MÀ MÃ ĐÃ NHẬN ══════════════════════
   Trước 1.30.0 lõi chạy MỘT lượt: giao dịch KHÔNG có mã đi trước ghép mờ ngay, ăn đúng dòng sổ
   mà giao dịch CÓ MÃ đứng sau cần.

   ⚠️ CA NÀY (hai bên đủ dòng để ghép chéo) BẢN CŨ CŨNG XANH — đã đo. Nó ở đây làm chốt bất
      biến: ghép chéo hay ghép đúng thì tổng vẫn phải là "khớp hết, không thiếu bên nào". Ca
      THẬT SỰ phân biệt được hai bản nằm ngay dưới ($r_che).
   ⚠️ Thứ tự hai dòng POS CÓ NGHĨA: dòng không mã phải đứng TRƯỚC. */
$r2 = khh_dt_doi_soat_momo_lam(
	array(
		gd_pos( '',   '2026-09-12', 50000 ),          // không mã, đứng TRƯỚC
		gd_pos( 'M2', '2026-09-12', 50000 ),          // có mã, đứng SAU
	),
	array(
		gd_sk( 'M2', '2026-09-12 09:00', 50000 ),
		gd_sk( '',   '2026-09-12 08:00', 50000 ),
	)
);
phep( '🔴 hai bên khớp hết, KHÔNG bịa ra cặp lệch', 2 === $r2['so_khop'] );
phep( '🔴 không có dòng nào bị kể là chỉ-bên-máy', 0 === $r2['so_chi_pos'] );
phep( '🔴 không có dòng nào bị kể là chỉ-bên-MoMo', 0 === $r2['so_chi_sk'] );
phep( 'và không có dòng nào bị kể là lệch tiền', 0 === $r2['so_lech'] );

/* ══ 🔴 CA ĐẮT NHẤT — BẢN MỘT LƯỢT CHE MẤT KHOẢN LỆCH TIỀN ═════════════════════════════════
   Đo thật bằng cách cho bản cũ (trích nguyên văn từ git) và bản mới chạy cùng dữ kiện:

       bản CŨ   khớp=1 · lệch=0 · chỉ-bên-máy=1      ← khoản lệch 1.000đ BỊ CHE
       bản MỚI  khớp=0 · lệch=1 · chỉ-bên-máy=1      ← nêu đúng mã, đúng số

   Máy POS ghi 70.000 cho mã M2, sổ MoMo trả 71.000 cho đúng mã ấy — đúng thứ cả module này
   sinh ra để bắt. Bản cũ để một giao dịch không mã (tình cờ đúng 71.000) ăn trước dòng M2, nên
   cặp M2 ↔ M2 không bao giờ được đem so với nhau, và 1.000đ chênh biến mất khỏi báo cáo. */
$r_che = khh_dt_doi_soat_momo_lam(
	array(
		gd_pos( '',   '2026-09-12', 71000 ),   // không mã, tình cờ đúng số tiền của sổ
		gd_pos( 'M2', '2026-09-12', 70000 ),   // có mã, máy ghi THIẾU 1.000đ
	),
	array( gd_sk( 'M2', '2026-09-12 09:00', 71000 ) )
);
phep( '🔴 lệch tiền trên đúng mã KHÔNG bị che', 1 === $r_che['so_lech'] );
phep( '🔴 và không bị kể thành "khớp"', 0 === $r_che['so_khop'] );
phep( 'số bên sổ kể đúng 71.000',
	! empty( $r_che['lech'] ) && 71000.0 === (float) $r_che['lech'][0]['sk_tien'] );
phep( 'dòng được nêu lệch là dòng CÓ MÃ M2',
	! empty( $r_che['lech'] ) && 'M2' === $r_che['lech'][0]['pos']['ma_doi_tac'] );

/* Cùng ca ấy nhưng sổ CHỈ có một dòng: phần thiếu là thật, phải kể ra. */
$r3 = khh_dt_doi_soat_momo_lam(
	array(
		gd_pos( '',   '2026-09-12', 50000 ),
		gd_pos( 'M2', '2026-09-12', 50000 ),
	),
	array( gd_sk( 'M2', '2026-09-12 09:00', 50000 ) )
);
phep( 'thiếu thật thì vẫn kể: 1 khớp', 1 === $r3['so_khop'] );
phep( 'thiếu thật thì vẫn kể: 1 chỉ-bên-máy', 1 === $r3['so_chi_pos'] );
phep( 'và dòng CÓ MÃ là dòng được khớp, không phải dòng không mã',
	! empty( $r3['khop'] ) || 1 === $r3['so_khop'] );

/* ---- Mỗi dòng sổ chỉ được ghép MỘT lần: hai giao dịch giống nhau không nhân đôi ---- */
$r4 = khh_dt_doi_soat_momo_lam(
	array(
		gd_pos( '', '2026-09-12', 50000 ),
		gd_pos( '', '2026-09-12', 50000 ),
	),
	array( gd_sk( '', '2026-09-12 08:00', 50000 ) )
);
phep( 'một dòng sổ ghép đúng một lần', 1 === $r4['so_khop'] && 1 === $r4['so_chi_pos'] );

/* ---- Hai mốc cắt khác nhau: ngày ngoài phạm vi sổ kia thì KHÔNG kết luận ---- */
$r5 = khh_dt_doi_soat_momo_lam(
	array(
		gd_pos( 'M1', '2026-09-12', 50000 ),
		gd_pos( 'M5', '2026-09-16', 60000 ),          // sổ MoMo dừng ở 12/09
	),
	array( gd_sk( 'M1', '2026-09-12 08:00', 50000 ) )
);
phep( 'ngày ngoài phạm vi sổ MoMo -> nhóm "ngoài", không phải "MoMo thiếu"',
	1 === $r5['so_ngoai'] && 0 === $r5['so_chi_pos'] );
$r6 = khh_dt_doi_soat_momo_lam(
	array( gd_pos( 'M1', '2026-09-12', 50000 ) ),
	array(
		gd_sk( 'M1', '2026-09-12 08:00', 50000 ),
		gd_sk( 'M7', '2026-09-16 08:00', 60000 ),     // kho POS dừng ở 12/09
	)
);
phep( 'ngày ngoài phạm vi kho POS -> nhóm "ngoài", không phải "máy bỏ sót"',
	1 === $r6['so_ngoai_sk'] && 0 === $r6['so_chi_sk'] );

/* ---- Ghép mờ phải cùng CƠ SỞ ---- */
$r7 = khh_dt_doi_soat_momo_lam(
	array( gd_pos( '', '2026-09-12', 50000, CH_A ) ),
	array( gd_sk( '', '2026-09-12 08:00', 50000, CH_B ) )
);
phep( 'ghép mờ KHÔNG bắc cầu sang quán khác', 0 === $r7['so_khop'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
   NẠP SAI THẺ: CÂU BÁO LỖI PHẢI CHỈ SANG THẺ ĐÚNG, KHÔNG ĐỔ CHO NGƯỜI NẠP
   ═══════════════════════════════════════════════════════════════════════════════════════════
   17/09/2026 anh Thắng thả `MoMo-payments.xlsx` vào thẻ "Sao kê MoMo" và nhận câu *"Anh tải
   đúng bản Transaction report của MoMo giúp em."* File ấy KHÔNG sai — nó là file FABi, hợp lệ,
   3.851 giao dịch đọc ra sạch — chỉ thuộc thẻ bên cạnh. Bảo người ta đi tải lại một file họ
   đang cầm trong tay là đẩy họ đi một vòng vô ích; lần sau họ sẽ tin chỗ nạp bị hỏng.

   Bốn ca dưới đây khoá cả hai chiều: chỉ đúng đường khi nhầm thẻ, VÀ không báo oan file đúng. */

$tep_fabi = dung_file_momo(
	array(
		'Tutu Train - Aeon Tan An ( Dich v' => array(
			array( 'M1', 'HD1', '1', '15/09/2026 19:56', 20000, 'tinh', 'Thành công' ),
		),
	)
);
$r_nham = khh_dt_doc_momo_sk( $tep_fabi );
phep( 'nạp file FABi vào thẻ sổ MoMo thì BÁO LỖI', is_wp_error( $r_nham ) );
if ( is_wp_error( $r_nham ) ) {
	$m = $r_nham->get_error_message();
	phep( 'câu lỗi NHẬN RA đây là file FABi', false !== strpos( $m, 'FABi' ) );
	phep( 'câu lỗi CHỈ SANG thẻ đúng', false !== strpos( $m, 'Giao dịch MoMo' ) );
	phep( 'câu lỗi KHÔNG bảo đi tải lại file (file đang cầm là đúng)',
		false === stripos( $m, 'tải đúng bản' ) );
}

/* Một .xlsx khác hẳn — không phải FABi. Vẫn phải nói ra "thẻ này chỉ đọc .csv", vì ô thả ghi
   "nhận .xlsx, .csv" nên người nạp không có cách nào tự biết. */
$tep_la = tempnam( sys_get_temp_dir(), 'la' ) . '.xlsx';
$z_la   = new ZipArchive();
$z_la->open( $tep_la, ZipArchive::CREATE | ZipArchive::OVERWRITE );
$z_la->addFromString( 'xl/sharedStrings.xml', '<sst><si><t>Ho ten</t></si></sst>' );
$z_la->close();
$r_la = khh_dt_doc_momo_sk( $tep_la );
phep( 'xlsx lạ cũng báo lỗi', is_wp_error( $r_la ) );
if ( is_wp_error( $r_la ) ) {
	$m = $r_la->get_error_message();
	phep( 'câu lỗi nói rõ thẻ này chỉ đọc .csv', false !== strpos( $m, '.csv' ) );
	phep( 'KHÔNG nhận oan xlsx lạ là file FABi', false === strpos( $m, 'FABi' ) );
}

/* .csv thật mà thiếu cột — đây mới là ca câu cũ nói đúng, phải giữ. */
$tep_thieu = tempnam( sys_get_temp_dir(), 'thieu' ) . '.csv';
file_put_contents( $tep_thieu, "Ho ten,Dia chi\nA,B\n" );
$r_thieu = khh_dt_doc_momo_sk( $tep_thieu );
phep( 'csv thiếu cột thì báo thiếu cột', is_wp_error( $r_thieu )
	&& false !== strpos( $r_thieu->get_error_message(), 'Thời gian' ) );
phep( 'csv thiếu cột KHÔNG bị đoán là file FABi', is_wp_error( $r_thieu )
	&& false === strpos( $r_thieu->get_error_message(), 'FABi' ) );

/* 🔴 CHỐT NGƯỢC — không có nó thì cả khối trên vẫn xanh khi hàm đọc chối MỌI file. */
$tep_dung = tempnam( sys_get_temp_dir(), 'dung' ) . '.csv';
file_put_contents(
	$tep_dung,
	"Thoi gian,Ma giao dich,So tien,Ma cua hang,Ten cua hang,Trang thai\n"
	. "16-09-2026 14:44:48,146972800768,20000,CH01,Quan A,Thanh cong\n"
);
$r_dung = khh_dt_doc_momo_sk( $tep_dung );
phep( 'sổ MoMo ĐÚNG dạng vẫn đọc được, không báo oan',
	! is_wp_error( $r_dung ) && 1 === count( $r_dung['dong'] ) );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép:\n";
	foreach ( $hong as $h ) {
		echo "   · $h\n";
	}
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: đọc file MoMo (máy POS + sao kê) và LÕI GHÉP hai sổ\n";
