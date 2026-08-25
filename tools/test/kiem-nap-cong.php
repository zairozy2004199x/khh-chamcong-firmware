<?php
/**
 * Kiểm bộ NẠP CSV của bản chấm công dựng mới — chạy trên CHÍNH hai tệp thật anh Thắng xuất ra
 * từ Google Sheets ngày 25/08/2026, không phải dữ liệu bịa.
 *
 * VÌ SAO PHẢI DÙNG TỆP THẬT
 * Cả buổi dò giao thức ghế tối 24/08 cho thấy khoảng cách giữa "đúng theo tưởng tượng" và "đúng
 * trên máy thật" là rất xa. Dữ liệu bịa luôn sạch: không có tiêu đề `Cột 7`, không có hàng tiêu
 * đề thiếu ô cột A, không có ba cách viết cho một loại hợp đồng. Tệp thật có đủ.
 *
 * Chạy: php tools/test/kiem-nap-cong.php
 */

define( 'VCG_TEST', true );
require __DIR__ . '/../../wordpress/vhcp-cong/includes/class-vcg-nap.php';

$hong = 0;
$dat  = 0;

function la( $ten, $mong, $duoc ) {
	global $hong, $dat;
	if ( $mong === $duoc ) { $dat++; return; }
	printf( "  HỎNG %-52s mong %s · được %s\n", $ten,
		var_export( $mong, true ), var_export( $duoc, true ) );
	$hong++;
}
function that( $ten, $dieu ) { la( $ten, true, (bool) $dieu ); }

function doc_csv( $duong ) {
	$f = fopen( $duong, 'r' );
	$ds = array();
	while ( false !== ( $r = fgetcsv( $f, 0, ',' ) ) ) { $ds[] = $r; }
	fclose( $f );
	/* Bỏ dấu BOM ở ô đầu tiên — Sheets luôn kèm, và nó làm mọi phép so tên cột trượt. */
	if ( isset( $ds[0][0] ) ) { $ds[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', $ds[0][0] ); }
	return $ds;
}

/* ======================= GỘP CÁCH VIẾT ======================= */
echo "— gộp cách viết —\n";
la( 'Full time  -> Full-time',   'Full-time', VCG_Nap::gop( 'Full time' ) );
la( 'Full-time  -> Full-time',   'Full-time', VCG_Nap::gop( 'Full-time' ) );
la( 'FULL-TIME  -> Full-time',   'Full-time', VCG_Nap::gop( 'FULL-TIME' ) );
la( 'Part-time  -> Part-time',   'Part-time', VCG_Nap::gop( 'Part-time' ) );
la( 'Máy Tự Động -> Máy tự động', 'Máy tự động', VCG_Nap::gop( 'Máy Tự Động' ) );
la( 'Máy tự động giữ nguyên',     'Máy tự động', VCG_Nap::gop( 'Máy tự động' ) );
la( 'Khu vui chơi giữ nguyên',    'Khu vui chơi', VCG_Nap::gop( 'Khu vui chơi' ) );
/* Giá trị lạ KHÔNG được ép về gì cả — ép bừa là sửa dữ liệu của người ta mà không ai biết. */
la( 'giá trị lạ giữ nguyên',      'Thời vụ', VCG_Nap::gop( '  Thời vụ  ' ) );
la( 'ô trống -> rỗng',            '', VCG_Nap::gop( '   ' ) );

/* ======================= NGÀY ======================= */
echo "— ngày —\n";
la( 'dd/mm/yyyy',           '2004-01-01', VCG_Nap::ngay( '01/01/2004' ) );
la( 'yyyy-mm-dd',           '2026-07-01', VCG_Nap::ngay( '2026-07-01' ) );
la( 'dd/mm kèm giờ',        '2026-07-23', VCG_Nap::ngay( '23/07/2026 21:10:29' ) );
/* 🔴 Chốt CHIỀU đọc. Đọc nhầm thành mm/dd thì mọi ngày có ngày ≤ 12 đều sai mà không báo gì. */
la( '25/09/2004 là 25 THÁNG 9', '2004-09-25', VCG_Nap::ngay( '25/09/2004' ) );
la( '03/04/2020 là 3 THÁNG 4',  '2020-04-03', VCG_Nap::ngay( '03/04/2020' ) );
la( 'ngày không có thật',    null, VCG_Nap::ngay( '31/02/2020' ) );
la( 'không phải ngày',       null, VCG_Nap::ngay( 'Giờ Vào / Checkin' ) );
la( 'ô trống',               null, VCG_Nap::ngay( '' ) );

/* ======================= GIỜ -> GIÂY ======================= */
echo "— giờ —\n";
la( '00:00', 0,     VCG_Nap::giay( '00:00' ) );
la( '07:58', 28680, VCG_Nap::giay( '07:58' ) );
la( '17:30:15', 63015, VCG_Nap::giay( '17:30:15' ) );
la( '23:59', 86340, VCG_Nap::giay( '23:59' ) );
la( 'giờ quá 23',  null, VCG_Nap::giay( '24:00' ) );
la( 'phút quá 59', null, VCG_Nap::giay( '10:75' ) );
la( 'không phải giờ', null, VCG_Nap::giay( 'Ảnh Checkin' ) );

/* ======================= TỆP NHÂN VIÊN THẬT ======================= */
echo "— tệp NV_NguonCongT.csv (thật) —\n";
$nv = doc_csv( __DIR__ . '/fixtures/cong/NV_NguonCongT.csv' );
la( 'đọc đủ 245 dòng + 1 tiêu đề', 246, count( $nv ) );

$kq = VCG_Nap::doc_nhan_vien( $nv );
la( 'ra đúng 215 người',   215, count( $kq['nguoi'] ) );
la( 'không bỏ dòng nào',     0, $kq['bo_qua'] );

/* 🔴 CHỖ QUAN TRỌNG NHẤT. Anh Thắng: "nhân viên có thể làm ở nhiều gian qua cột đơn vị làm
   việc". Lấy mã NV làm khoá duy nhất rồi ghi đè là mỗi người còn đúng MỘT cơ sở, và mất hết
   phần còn lại — không có lỗi nào hiện ra, chỉ là mai mốt thấy thiếu người ở đâu đó. */
$dem = array();
foreach ( $kq['gan'] as $g ) {
	if ( ! isset( $dem[ $g['ma_nv'] ] ) ) { $dem[ $g['ma_nv'] ] = 0; }
	$dem[ $g['ma_nv'] ]++;
}
$nhieu = count( array_filter( $dem, function ( $n ) { return $n > 1; } ) );
la( '21 người làm nhiều cơ sở', 21, $nhieu );
that( 'số dòng gán > số người',  count( $kq['gan'] ) > count( $kq['nguoi'] ) );

/* Một ca cụ thể, đối chiếu tận mắt với ảnh chụp Sheet. */
$dv = array();
foreach ( $kq['gan'] as $g ) { if ( 'MNNV2MTD0007' === $g['ma_nv'] ) { $dv[] = $g['don_vi']; } }
sort( $dv );
la( 'MNNV2MTD0007 giữ đủ 2 đơn vị', array( 'Posh Bệnh viện Ung Bướu HCM', 'Posh Go Dĩ An' ), $dv );

/* Gộp cách viết phải ăn trên dữ liệu thật, không chỉ trên ví dụ. */
$loai = array();
foreach ( $kq['nguoi'] as $p ) { $loai[ $p['loai_hop_dong'] ] = 1; }
la( 'loại hợp đồng gộp còn 2', array( 'Full-time', 'Part-time' ),
	( function ( $l ) { $k = array_keys( $l ); sort( $k ); return $k; } )( $loai ) );

$lh = array();
foreach ( $kq['nguoi'] as $p ) { $lh[ $p['loai_hinh'] ] = 1; }
la( 'loại hình gộp còn 2', array( 'Khu vui chơi', 'Máy tự động' ),
	( function ( $l ) { $k = array_keys( $l ); sort( $k ); return $k; } )( $lh ) );

/* Ngày sinh phải ra ngày thật, không phải chuỗi nguyên trạng. */
$p1 = $kq['nguoi'][0];
la( 'người đầu: mã',       'MNNV2MTD0001', $p1['ma_nv'] );
la( 'người đầu: tên',      'Nguyễn Thu Hiền', $p1['ho_ten'] );
la( 'người đầu: ngày sinh','2004-01-01', $p1['ngay_sinh'] );
la( 'người đầu: CCCD',     '049304007231', $p1['cccd'] );

/* ======================= TỆP CƠ SỞ THẬT ======================= */
echo "— tệp CS_TUTU_TP.csv (thật) —\n";
$cs = doc_csv( __DIR__ . '/fixtures/cong/CS_TUTU_TP.csv' );
la( 'tên cơ sở lấy từ tên tệp', 'TUTU_TP', VCG_Nap::co_so_tu_ten( 'CS_TUTU_TP.csv' ) );

$khoi = VCG_Nap::tim_khoi( $cs );
/* 🔴 Phải ra ĐỦ HAI khối. Khối đầu có ô cột A TRỐNG ở hàng tiêu đề — dựa vào cột A để nhận ra
   khối là bỏ sót nguyên tháng 7, tức mất cả một tháng chấm công mà không lỗi nào hiện ra. */
la( 'tìm đủ 2 khối tháng', 2, count( $khoi ) );
la( 'khối 1: hàng tiêu đề', 1, $khoi[0]['tieu_de'] );
la( 'khối 1: hàng ngày',    0, $khoi[0]['ngay'] );
la( 'khối 2: hàng tiêu đề', 12, $khoi[1]['tieu_de'] );
that( 'khối 1 kết thúc TRƯỚC hàng ngày của khối 2', $khoi[0]['r2'] < $khoi[1]['ngay'] );

$ngay1 = VCG_Nap::ngay( $cs[0][2] );
la( 'khối 1 bắt đầu 2026-07-01', '2026-07-01', $ngay1 );
la( 'khối 2 bắt đầu 2026-08-01', '2026-08-01', VCG_Nap::ngay( $cs[11][2] ) );

/* Số lượt trên tệp thật. Con số 204 KHÔNG do bản PHP tự khai — nó được đếm lại bằng một bản
   Python độc lập, viết riêng, đọc cùng tệp. Hai bản khác nhau ra cùng một số thì mới tin được;
   lấy chính đầu ra của bản đang thử làm chuẩn thì chỉ chứng minh nó nhất quán với chính nó.
   Khối 1 (tháng 7): 31 ngày, 115 lượt · khối 2 (tháng 8): 25 ngày, 89 lượt. */
$luot = VCG_Nap::doc_co_so( $cs, 'TUTU_TP' );
la( 'tệp thật -> 204 lượt', 204, count( $luot ) );

/* Ô TRỐNG KHÔNG ĐƯỢC SINH DÒNG. Sheet 31 ngày × 20 người là 620 ô, phần lớn trống; ghi hết
   xuống bảng là phình dữ liệu vô ích và làm mọi câu truy vấn chậm đi. */
$rong = 0;
foreach ( $luot as $x ) {
	if ( null === $x['vao'] && null === $x['ra'] && '' === $x['anh_vao'] && '' === $x['anh_ra'] ) { $rong++; }
}
la( 'không lượt nào rỗng hoàn toàn', 0, $rong );

/* Đối chiếu tận mắt với tệp: hàng 13, khối tháng 8, ngày đầu. */
$mot = null;
foreach ( $luot as $x ) {
	if ( 'MNNV2KVC0106' === $x['ma_nv'] && '2026-08-01' === $x['ngay'] ) { $mot = $x; break; }
}
that( 'tìm thấy lượt 2026-08-01 của MNNV2KVC0106', null !== $mot );
if ( $mot ) {
	la( '  giờ vào 08:40:43', 31243, $mot['vao'] );
	la( '  giờ ra  22:01:39', 79299, $mot['ra'] );
	la( '  cơ sở',            'TUTU_TP', $mot['co_so'] );
}

/* Mọi lượt phải có ngày hợp lệ — một ô ngày đọc hỏng là cả cột đi sai chỗ. */
$ngay_hong = 0;
foreach ( $luot as $x ) { if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $x['ngay'] ) ) { $ngay_hong++; } }
la( 'mọi lượt có ngày hợp lệ', 0, $ngay_hong );

/* Dựng một tệp cơ sở CÓ dữ liệu, đúng hình dạng Sheet, để kiểm phần đọc lượt. */
$gia = array(
	array( 'Họ và Tên', 'ID', '2026-07-01', '', '', '', '', '2026-07-02', '', '', '', '' ),
	array( '', '', 'Giờ Vào / Checkin', 'Ảnh Checkin', 'Giờ Ra / CheckOut', 'Ảnh Checkout',
	       'Thời gian trong ngày', 'Giờ Vào / Checkin', 'Ảnh Checkin', 'Giờ Ra / CheckOut',
	       'Ảnh Checkout', 'Thời gian trong ngày' ),
	array( 'Trần Thị Thúy Vy', 'MNNV2KVC0106', '07:58', 'a.jpg', '17:30', 'b.jpg', '9:32',
	       '', '', '', '', '' ),
	array( 'MAI QUOC HUONG', 'TP15', '', '', '', '', '', '08:05', '', '', '', '' ),
);
$l2 = VCG_Nap::doc_co_so( $gia, 'TUTU_TP' );
la( 'ra đúng 2 lượt', 2, count( $l2 ) );
la( 'lượt 1: ngày',   '2026-07-01', $l2[0]['ngay'] );
la( 'lượt 1: giờ vào', 28680, $l2[0]['vao'] );
la( 'lượt 1: giờ ra',  63000, $l2[0]['ra'] );
la( 'lượt 1: mã NV',  'MNNV2KVC0106', $l2[0]['ma_nv'] );
la( 'lượt 2: mã lạ vẫn nhận', 'TP15', $l2[1]['ma_nv'] );
la( 'lượt 2: ngày 2',  '2026-07-02', $l2[1]['ngay'] );
la( 'lượt 2: chỉ có giờ vào', null, $l2[1]['ra'] );
/* Ngày 2 của người thứ nhất trống hoàn toàn -> KHÔNG được sinh dòng. */
foreach ( $l2 as $x ) {
	if ( 'MNNV2KVC0106' === $x['ma_nv'] && '2026-07-02' === $x['ngay'] ) {
		echo "  HỎNG sinh dòng cho ô trống\n"; $hong++;
	}
}

echo "\n";
if ( $hong ) { printf( "🔴 HỎNG: %d | ĐẠT: %d\n", $hong, $dat ); exit( 1 ); }
printf( "✓ SẠCH — %d phép, chạy trên tệp CSV THẬT\n", $dat );
