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

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép:\n";
	foreach ( $hong as $h ) {
		echo "   · $h\n";
	}
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép đọc file MoMo (máy POS + sao kê MoMo)\n";
