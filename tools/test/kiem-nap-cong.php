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
	while ( false !== ( $r = fgetcsv( $f, 0, ',', '"', '\\' ) ) ) { $ds[] = $r; }
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
/* 29, KHÔNG PHẢI 21. Con số cũ 21 chỉ đếm những người có NHIỀU DÒNG trong sheet. Còn 8 người
   nữa khai nhiều đơn vị trong CÙNG MỘT Ô, ngăn bằng dấu phẩy:
       "Funfest SC Vivo, Pinball SC Vivo"
       "Funzone ADV Aeon Mall Tân Phú, Tutu Lotte Mart Gò Vấp, … " (sáu đơn vị)
   Giữ nguyên cả chuỗi là đẻ ra một "đơn vị" ảo không có thật, và tám người đó không thuộc về
   cơ sở nào trong số các cơ sở họ thật sự làm. 21 + 8 = 29. */
la( '29 người làm nhiều cơ sở', 29, $nhieu );
that( 'số dòng gán > số người',  count( $kq['gan'] ) > count( $kq['nguoi'] ) );

/* Không được còn ô đơn vị nào dính dấu phẩy — dấu phẩy sót lại là ô chưa tách. */
$dinh_phay = 0;
foreach ( $kq['gan'] as $g ) { if ( false !== strpos( $g['don_vi'], ',' ) ) { $dinh_phay++; } }
la( 'không đơn vị nào còn dấu phẩy', 0, $dinh_phay );

/* Ca sáu đơn vị trong MỘT ô — HUỲNH THỊ THU THẢO, dòng có nhiều đơn vị nhất trong tệp thật. */
$sau = array();
foreach ( $kq['gan'] as $g ) { if ( 'MNNV2KVC0173' === $g['ma_nv'] ) { $sau[] = $g['don_vi']; } }
sort( $sau );
la( 'MNNV2KVC0173: ô 6 đơn vị -> 6 dòng gán', array(
	'Funzone ADV Aeon Mall Tân Phú',
	'Funzone ADV Go An Lạc',
	'Snow Fun Aeon Mall Tân Phú',
	'Tutu Lotte Mart Gò Vấp',
	'Tutu Train Aeon Mall Tân An',
	'VR Aeon Mall Tân An',
), $sau );

la( 'tách đơn vị: một tên thường',  array( 'Posh Go Dĩ An' ), VCG_Nap::tach_don_vi( 'Posh Go Dĩ An' ) );
la( 'tách đơn vị: hai tên',
	array( 'Funfest SC Vivo', 'Pinball SC Vivo' ),
	VCG_Nap::tach_don_vi( 'Funfest SC Vivo, Pinball SC Vivo' ) );
la( 'tách đơn vị: ô trống -> mảng rỗng', array(), VCG_Nap::tach_don_vi( '   ' ) );
la( 'tách đơn vị: trùng thì gộp', array( 'JP' ), VCG_Nap::tach_don_vi( 'JP,  JP ' ) );

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

/* ======================= TÊN TỆP -> MÃ CƠ SỞ ======================= */
/* 🔴 ĐÂY LÀ CHỖ ĐÃ CHẶN ANH THẮNG THẬT. Tệp tên `CS_VP_KH-HCM.csv` — có DẤU GẠCH NGANG. Bản đầu
   khớp `[A-Za-z0-9_]+` nên trả rỗng, và màn nạp chặn ngay ở cửa: "Không đoán được cơ sở từ tên
   tệp". Không phải dữ liệu hỏng, không phải quyền — chỉ là một dấu gạch ngang không nằm trong
   danh sách ký tự em cho phép.
   Bài học: liệt kê trước những ký tự nào được phép thì kiểu gì cũng sót. Giờ lấy TẤT CẢ phần
   sau `CS_` rồi mới chặn cái thật sự không dùng được. */
echo "— tên tệp -> mã cơ sở —\n";
$sheets = '( Đang chạy ) Hệ Thống Chấm Công Cơ Sở - ';
la( 'CÓ DẤU GẠCH NGANG (ca thật của anh Thắng)',
	'VP_KH-HCM', VCG_Nap::co_so_tu_ten( $sheets . 'CS_VP_KH-HCM.csv' ) );
la( 'tên thường',        'TUTU_TP',    VCG_Nap::co_so_tu_ten( $sheets . 'CS_TUTU_TP.csv' ) );
la( 'gạch dưới + số',    'VP_KHHCM_1', VCG_Nap::co_so_tu_ten( $sheets . 'CS_VP_KHHCM_1.csv' ) );
la( 'không có phần đầu', 'TUTU_TP',    VCG_Nap::co_so_tu_ten( 'CS_TUTU_TP.csv' ) );
/* Trình duyệt tải lần hai thì thêm " (1)" vào tên — rất hay gặp, và đủ để tắc lần nữa. */
la( 'bản sao (1) của trình duyệt', 'VP_KH-HCM',
	VCG_Nap::co_so_tu_ten( $sheets . 'CS_VP_KH-HCM (1).csv' ) );
la( 'đuôi CSV viết hoa', 'TUTU_TP', VCG_Nap::co_so_tu_ten( 'CS_TUTU_TP.CSV' ) );
la( 'khoảng trắng -> gạch dưới', 'VP_KH_HCM', VCG_Nap::co_so_tu_ten( 'CS_VP KH HCM.csv' ) );
la( 'có đường dẫn',      'TUTU_TP', VCG_Nap::co_so_tu_ten( '/tmp/tai ve/CS_TUTU_TP.csv' ) );
/* Vẫn phải TỪ CHỐI khi không có gì để lấy — trả bừa một mã cơ sở kỳ quặc rồi ghi xuống bảng là
   dữ liệu nằm sai chỗ vĩnh viễn, vì mã cơ sở là khoá. */
la( 'không có CS_ -> rỗng', '', VCG_Nap::co_so_tu_ten( 'bang cong thang 7.csv' ) );
la( 'có dấu tiếng Việt -> rỗng', '', VCG_Nap::co_so_tu_ten( 'CS_Cơ Sở Mới.csv' ) );
la( 'CS_ rồi hết -> rỗng',  '', VCG_Nap::co_so_tu_ten( 'CS_.csv' ) );

/* 🔴 CA THẬT THỨ HAI, 25/08/2026: `CS_(PART TIME )_POSH+JP.csv` — có DẤU NGOẶC, KHOẢNG TRẮNG và
   DẤU CỘNG. Lại bị chặn ở cửa y như ca dấu gạch ngang hôm trước. Đó là LẦN THỨ HAI cùng một
   kiểu sai: liệt kê trước "ký tự nào được phép" thì lần nào cũng sót một ký tự mới.
   Giờ đổi hướng — DỌN chuỗi về khuôn dùng được, chỉ từ chối khi dọn xong vẫn còn thứ không đặt
   làm mã được. */
la( 'CÓ NGOẶC + KHOẢNG TRẮNG + DẤU CỘNG (ca thật)',
	'PART_TIME_POSH+JP', VCG_Nap::co_so_tu_ten( $sheets . 'CS_(PART TIME )_POSH+JP.csv' ) );
la( '  gõ tay ra CÙNG một mã',
	'PART_TIME_POSH+JP', VCG_Nap::chuan_co_so( '(PART TIME )_POSH+JP' ) );
/* Gộp gạch dưới liên tiếp: không gộp thì ra `PART_TIME__POSH` — nhìn giống hệt nhưng là MÃ
   KHÁC, và cơ sở đó có công nằm hai chỗ. */
la( 'gộp gạch dưới liên tiếp', 'A_B', VCG_Nap::chuan_co_so( 'A___B' ) );
la( 'cắt gạch dưới hai đầu',   'AB',  VCG_Nap::chuan_co_so( '__AB__' ) );
la( 'ngoặc vuông cũng dọn',    'A_B', VCG_Nap::chuan_co_so( '[A] B' ) );
la( 'dấu & giữ nguyên',        'K&H', VCG_Nap::chuan_co_so( 'K&H' ) );
/* Dọn xong mà rỗng thì phải trả rỗng, không được trả '_' hay chuỗi rác. */
la( 'chỉ toàn ngoặc -> rỗng',  '', VCG_Nap::co_so_tu_ten( 'CS_( ).csv' ) );
/* Hậu tố bản sao cắt TRƯỚC khi dọn ngoặc — không thì " (2)" thành "_2" dính vào mã và tệp tải
   lần hai đẻ ra một cơ sở khác. */
la( 'bản sao (2) không dính vào mã', 'VP_KH-HCM',
	VCG_Nap::co_so_tu_ten( $sheets . 'CS_VP_KH-HCM (2).csv' ) );

/* Ô tự gõ phải đi qua CÙNG khuôn — nếu không thì gõ tay ra một mã, tên tệp ra một mã khác, và
   cùng một cơ sở có công nằm hai nơi. */
la( 'gõ tay: bình thường', 'VP_KH-HCM', VCG_Nap::chuan_co_so( ' VP_KH-HCM ' ) );
la( 'gõ tay: khoảng trắng -> gạch dưới', 'VP_KH_HCM', VCG_Nap::chuan_co_so( 'VP KH HCM' ) );
la( 'gõ tay: dấu tiếng Việt -> rỗng', '', VCG_Nap::chuan_co_so( 'Cơ sở 1' ) );
la( 'gõ tay: rỗng -> rỗng', '', VCG_Nap::chuan_co_so( '   ' ) );
that( 'gõ tay và tên tệp ra CÙNG một mã',
	VCG_Nap::chuan_co_so( 'VP_KH-HCM' ) === VCG_Nap::co_so_tu_ten( $sheets . 'CS_VP_KH-HCM.csv' ) );

/* ======================= TỆP CƠ SỞ THỨ HAI — CS_VP_KHHCM_1 ======================= */
/* Anh Thắng gửi tệp này kèm câu "cơ sở này chưa nạp được". Nó KHÔNG hỏng ở chỗ đọc — nó hỏng ở
   bốn chỗ trong chính dữ liệu nguồn mà bản đầu nuốt im lặng. Giữ nguyên tệp làm bộ thử để bốn
   chỗ đó không quay lại. */
echo "— tệp CS_VP_KHHCM_1.csv (thật) —\n";
$vp = doc_csv( __DIR__ . '/fixtures/cong/CS_VP_KHHCM_1.csv' );
la( 'tên cơ sở từ tên tệp dài của Sheets', 'VP_KHHCM_1',
	VCG_Nap::co_so_tu_ten( 'Đang chạy_ Hệ Thống Chấm Công Cơ Sở - CS_VP_KHHCM_1.csv' ) );
la( 'tìm đủ 2 khối tháng', 2, count( VCG_Nap::tim_khoi( $vp ) ) );

$cb   = null;
$lvp  = VCG_Nap::doc_co_so( $vp, 'VP_KHHCM_1', $cb );
/* 722 đếm lại bằng một bản Python độc lập đọc cùng tệp — 374 của tháng 7 + 348 của tháng 8. */
la( 'tệp thật -> 722 lượt', 722, count( $lvp ) );

$t = array();
foreach ( $lvp as $x ) { $k = substr( $x['ngay'], 0, 7 ); $t[ $k ] = isset( $t[ $k ] ) ? $t[ $k ] + 1 : 1; }
ksort( $t );
la( 'chia đúng hai tháng', array( '2026-07' => 374, '2026-08' => 348 ), $t );

/* 🔴 CHỖ HỎNG SỐ 1 — Ô GIỜ CÓ HAI MỐC.
   Dòng của Huỳnh Thị Ngọc Nhiên ngày 2026-08-01 ghi `08:30 13:23:38` trong ô Giờ Vào. Bản đầu
   khớp cả chuỗi nên trả null và CẢ BUỔI LÀM của người đó biến mất khỏi bảng công — không lỗi,
   không dấu vết. Giờ phải ra đủ hai mốc, và phải kèm cảnh báo để anh Thắng sửa gốc. */
$hai = null;
foreach ( $lvp as $x ) {
	if ( 'KHKT1CTY0001' === $x['ma_nv'] && '2026-08-01' === $x['ngay'] ) { $hai = $x; break; }
}
that( 'tìm thấy lượt 2026-08-01 của KHKT1CTY0001', null !== $hai );
if ( $hai ) {
	la( '  ô "08:30 13:23:38" -> vào 08:30', 8 * 3600 + 30 * 60, $hai['vao'] );
	la( '  ô "08:30 13:23:38" -> ra 13:23:38', 13 * 3600 + 23 * 60 + 38, $hai['ra'] );
}
la( 'moc_gio: ô hai mốc', array( 30600, 48218 ), VCG_Nap::moc_gio( '08:30 13:23:38' ) );
la( 'moc_gio: ô sạch',    array( 30600 ), VCG_Nap::moc_gio( '08:30:00' ) );
la( 'moc_gio: ô trống',   array(), VCG_Nap::moc_gio( '  ' ) );
la( 'moc_gio: rác không ra mốc', array(), VCG_Nap::moc_gio( 'nghỉ' ) );
/* Giờ một chữ số và giờ không có giây — tệp này có cả hai, `8:30` và `08:30`. */
la( 'giờ một chữ số 8:30',  30600, VCG_Nap::giay( '8:30' ) );
la( 'giờ không có giây',    59400, VCG_Nap::giay( '16:30' ) );

/* 🔴 CHỖ HỎNG SỐ 2 — MÃ NV LÀ SỐ TRẦN. Năm người trong tệp này có ô ID ghi `1`, `2`, `15`,
   `17`, `24` thay vì mã chuẩn. Mã số trần đụng nhau giữa các cơ sở và không nối được với sheet
   nhân viên, nên phải BÁO chứ không nuốt. */
$so_tran = array();
foreach ( $cb as $c ) { if ( 'ma_so_tran' === $c['kieu'] ) { $so_tran[ $c['ma_nv'] ] = 1; } }
$k = array_keys( $so_tran ); sort( $k );
la( 'báo đủ 5 mã số trần', array( 1, 2, 15, 17, 24 ), $k );

/* 🔴 CHỖ HỎNG SỐ 3 — MỘT NGƯỜI HAI MÃ. Nguyễn Hữu Thọ nằm hai dòng trong CÙNG khối tháng 8,
   một dòng mã `2` một dòng mã `15`. Không báo thì công của anh ấy bị chia đôi thành hai người
   trong bảng, và bảng lương trả thiếu mà nhìn vẫn thấy bình thường. */
$hai_ma = array();
foreach ( $cb as $c ) { if ( 'mot_nguoi_nhieu_ma' === $c['kieu'] ) { $hai_ma[ $c['ho_ten'] ] = $c['ma_nv']; } }
la( 'báo đúng một người mang nhiều mã', 1, count( $hai_ma ) );
la( 'Nguyễn Hữu Thọ: mã 2 và 15', '2 / 15', isset( $hai_ma['Nguyễn Hữu Thọ'] ) ? $hai_ma['Nguyễn Hữu Thọ'] : '' );
/* ⚠️ Chỉ báo MỘT dòng cho một người, không phải hai dòng theo hai chiều `2/15` và `15/2`.
   Cảnh báo lặp là người ta thôi đọc cảnh báo, và rồi bỏ qua luôn cái thật. */

/* 🔴 CHỖ HỎNG SỐ 4 — Ô NHIỀU MỐC vẫn phải để lại dấu vết dù máy đã tự xử được. */
$nhieu_moc = 0;
foreach ( $cb as $c ) { if ( 'o_nhieu_moc' === $c['kieu'] ) { $nhieu_moc++; } }
la( 'báo đúng 2 ô hai mốc', 2, $nhieu_moc );

/* Cảnh báo KHÔNG được lặp: cùng một người ở hai khối tháng chỉ báo một lần. */
$khoa = array();
foreach ( $cb as $c ) { $khoa[ implode( "\x1f", $c ) ] = 1; }
la( 'cảnh báo không trùng nhau', count( $cb ), count( $khoa ) );

/* CS_TUTU_TP cũng có chỗ hỏng, ít hơn — hoá ra tệp đầu tiên cũng không sạch như tưởng:
       ô ID ghi `1`             (Huỳnh Quang Thắng)
       ô ID ghi `855146747`     — số điện thoại lọt vào cột ID, ô tên trống
       Nguyễn Thành Pháp mang cả `MNNV2KVC0107` lẫn `TUTP05`
   Ba chỗ này nằm im từ đầu tới giờ. Đó chính là lý do phải có kênh cảnh báo, chứ không phải chỉ
   để đỡ cho riêng tệp VP. */
$cb2 = null;
VCG_Nap::doc_co_so( $cs, 'TUTU_TP', $cb2 );
la( 'CS_TUTU_TP -> 3 cảnh báo', 3, count( $cb2 ) );
$kieu2 = array();
foreach ( $cb2 as $c ) { $kieu2[] = $c['kieu']; }
sort( $kieu2 );
la( 'đúng loại cảnh báo', array( 'ma_so_tran', 'ma_so_tran', 'mot_nguoi_nhieu_ma' ), $kieu2 );
/* Không có ô giờ lạ nào -> không được đẻ cảnh báo giờ. Cảnh báo giả là người ta thôi đọc. */
$gio_la = 0;
foreach ( $cb2 as $c ) { if ( 'gio_la' === $c['kieu'] || 'o_nhieu_moc' === $c['kieu'] ) { $gio_la++; } }
la( 'CS_TUTU_TP: 0 cảnh báo về giờ', 0, $gio_la );

/* ======================= LUẬT GỘP GIỜ ======================= */
echo "— luật gộp giờ (chỗ quyết định TIỀN LƯƠNG) —\n";
require __DIR__ . '/../../wordpress/vhcp-cong/includes/class-vcg-db.php';

function gop( $cv, $cr, $mv, $mr ) {
	$k = VCG_DB::gop_gio( $cv, $cr, $mv, $mr );
	return array( $k['vao'], $k['ra'] );
}

la( 'ô trống + lượt đầu -> giờ vào',      array( 28680, null ),  gop( null, null, 28680, null ) );
la( 'có giờ vào + lượt muộn -> nới ra',   array( 28680, 63000 ), gop( 28680, null, 63000, null ) );
la( 'lượt SỚM hơn giờ vào -> vào mới',    array( 25000, 28680 ), gop( 28680, null, 25000, null ) );
la( 'lượt nằm GIỮA -> không đụng gì',     array( 28680, 63000 ), gop( 28680, 63000, 40000, null ) );
la( 'lượt trùng giờ vào -> không đổi',    array( 28680, 63000 ), gop( 28680, 63000, 28680, null ) );
la( 'lượt trùng giờ ra -> không đổi',     array( 28680, 63000 ), gop( 28680, 63000, 63000, null ) );
la( 'lượt MUỘN hơn -> nới giờ ra',        array( 28680, 70000 ), gop( 28680, 63000, 70000, null ) );
la( 'cả cặp mới, rộng hơn -> nới cả hai', array( 20000, 80000 ), gop( 28680, 63000, 20000, 80000 ) );

/* 🔴 KHÔNG BAO GIỜ THU HẸP. Đây là chốt quan trọng nhất: nạp lại một tệp cũ, hoặc nạp một tệp
   chỉ có nửa ngày, không được cắt mất giờ đã ghi. Thu hẹp là ăn mất công của người ta. */
la( 'lượt HẸP hơn -> giữ nguyên khoảng cũ', array( 20000, 80000 ), gop( 20000, 80000, 40000, 50000 ) );
la( 'chỉ giờ vào hẹp hơn -> giữ nguyên',    array( 20000, 80000 ), gop( 20000, 80000, 30000, null ) );

/* Một mốc duy nhất -> đó là GIỜ VÀO, ô giờ ra để TRỐNG. Đặt bằng nhau là sinh ca "làm 0 phút"
   trông như đã ra ca, và bảng công cộng thiếu một buổi. */
la( 'một mốc duy nhất -> giờ ra TRỐNG', array( 28680, null ), gop( null, null, 28680, 28680 ) );
la( 'không có mốc nào',                array( null, null ),  gop( null, null, null, null ) );

/* Cờ `doi` phải trung thực: báo đổi khi không đổi gì là mỗi lần nạp lại ghi đè cả bảng, mất
   dấu vết `sua_luc` và làm mọi phép đối chiếu vô nghĩa. */
$k1 = VCG_DB::gop_gio( 28680, 63000, 40000, null );
la( 'nằm giữa -> KHÔNG báo đổi', false, $k1['doi'] );
$k2 = VCG_DB::gop_gio( 28680, 63000, 70000, null );
la( 'nới rộng -> CÓ báo đổi',    true,  $k2['doi'] );

/* KHÔNG PHỤ THUỘC THỨ TỰ. Ba lượt trong ngày, nạp theo mọi thứ tự, phải ra cùng một cặp giờ.
   Đây chính là thứ làm cho "nạp lại CSV bao nhiêu lần cũng được" có nghĩa. */
$ba = array( 28680, 45000, 63000 );
$chuan = null;
foreach ( array( array(0,1,2), array(2,1,0), array(1,0,2), array(2,0,1), array(1,2,0), array(0,2,1) ) as $tt ) {
	$v = null; $r = null;
	foreach ( $tt as $i ) { $k = VCG_DB::gop_gio( $v, $r, $ba[ $i ], null ); $v = $k['vao']; $r = $k['ra']; }
	if ( null === $chuan ) { $chuan = array( $v, $r ); }
	la( '  thứ tự ' . implode( '', $tt ) . ' ra cùng kết quả', $chuan, array( $v, $r ) );
}
la( 'kết quả đó là [sớm nhất, muộn nhất]', array( 28680, 63000 ), $chuan );

/* Ca đêm: trải phẳng trục rồi mới gộp. Không trải thì 06:00 "sớm hơn" 22:00 và ca đêm bị đảo
   thành 16 tiếng ban ngày — đúng lỗi bản Apps Script phải viết hàm riêng để né. */
echo "— ca đêm —\n";
$vao_dem = VCG_DB::trai_dem( VCG_Nap::giay( '22:00' ) );   // 22:00 không trải
$ra_dem  = VCG_DB::trai_dem( VCG_Nap::giay( '06:00' ) );   // 06:00 -> +86400
la( '22:00 giữ nguyên', 79200, $vao_dem );
la( '06:00 trải sang hôm sau', 108000, $ra_dem );
$kd = VCG_DB::gop_gio( null, null, $vao_dem, $ra_dem );
la( 'ca đêm: vào 22:00', 79200, $kd['vao'] );
la( 'ca đêm: ra 06:00 hôm sau', 108000, $kd['ra'] );
la( 'ca đêm dài 8 tiếng', 28800, $kd['ra'] - $kd['vao'] );

/* Nếu QUÊN trải trục thì ca đêm ra 16 tiếng ban ngày — ghi ra đây để thấy hậu quả bằng số. */
$sai = VCG_DB::gop_gio( null, null, 79200, 21600 );
la( 'quên trải trục -> sai 16 tiếng', 57600, $sai['ra'] - $sai['vao'] );

/* ======================= SỐ GIỜ MỘT LƯỢT (BẢNG CÔNG) ======================= */
/* 🔴 Đây là con số đi thẳng vào bảng lương. Ca đêm là chỗ duy nhất dễ sai, và sai thì ra số ÂM
   — bảng tổng tháng TRỪ mất mười mấy tiếng của người ta thay vì cộng vào. */
echo "— số giờ một lượt —\n";
function gio( $v, $r ) { return VCG_DB::so_gio( $v, $r ); }
la( 'ca ngày 08:00-17:00 = 9 tiếng', 9 * 3600, gio( 8 * 3600, 17 * 3600 ) );
la( 'ca 08:30-17:15',                8 * 3600 + 45 * 60, gio( 8 * 3600 + 30 * 60, 17 * 3600 + 15 * 60 ) );
/* Ca đêm: ra 06:00 hôm sau, vào 22:00 hôm trước. Không cộng 24h là ra -16 tiếng. */
la( 'CA ĐÊM 22:00-06:00 = 8 tiếng',  8 * 3600, gio( 22 * 3600, 6 * 3600 ) );
la( 'CA ĐÊM 23:30-07:30 = 8 tiếng',  8 * 3600, gio( 23 * 3600 + 30 * 60, 7 * 3600 + 30 * 60 ) );
that( 'ca đêm KHÔNG BAO GIỜ ra số âm', gio( 22 * 3600, 6 * 3600 ) > 0 );
la( 'thiếu giờ ra -> null',          null, gio( 8 * 3600, null ) );
la( 'thiếu giờ vào -> null',         null, gio( null, 17 * 3600 ) );
la( 'trống cả hai -> null',          null, gio( null, null ) );
la( 'vào bằng ra -> 0',              0, gio( 8 * 3600, 8 * 3600 ) );
/* Lượt đã được trải phẳng lúc nạp (giờ ra > 86400) thì trừ thẳng, không cộng thêm lần nữa. */
la( 'giờ đã trải phẳng: 22:00 -> 30:00', 8 * 3600, gio( 22 * 3600, 30 * 3600 ) );

/* ======================= NGÀY TRỐNG (MÀN RÀ SOÁT) ======================= */
/* 🔴 Đây là chỗ dễ đẻ BÁO ĐỘNG GIẢ nhất, mà báo động giả thì người ta thôi nhìn bảng rà soát,
   rồi bỏ qua luôn cái thiếu thật. Hai luật phải giữ chặt:
     · đếm TỪ NGÀY ĐẦU CÓ DỮ LIỆU, không từ mùng 1 — cơ sở mở giữa tháng không phải lúc nào
       cũng "thiếu 14 ngày"
     · dừng ở MỐC CUỐI (hôm nay), không tới cuối tháng — tháng đang chạy thì ngày mai chưa thiếu */
echo "— ngày trống —\n";
function trong( $co, $moc ) { return VCG_DB::ngay_trong( $co, $moc ); }

la( 'liên tục -> không thiếu ngày nào', array(),
	trong( array( '2026-08-10', '2026-08-11', '2026-08-12' ), '2026-08-12' ) );
la( 'hụt một ngày ở giữa', array( '2026-08-11' ),
	trong( array( '2026-08-10', '2026-08-12' ), '2026-08-12' ) );
la( 'hụt hai ngày liền', array( '2026-08-11', '2026-08-12' ),
	trong( array( '2026-08-10', '2026-08-13' ), '2026-08-13' ) );

/* 🔴 KHÔNG đếm ngược về mùng 1. Cơ sở này bắt đầu có dữ liệu từ 15/8. */
la( 'mở giữa tháng: không thiếu ngày 1-14', array(),
	trong( array( '2026-08-15', '2026-08-16' ), '2026-08-16' ) );

/* 🔴 DỪNG Ở MỐC CUỐI. Hôm nay là 12, ngày 13-31 chưa tới nên chưa thiếu. */
la( 'không đếm ngày chưa tới', array(),
	trong( array( '2026-08-10', '2026-08-11', '2026-08-12' ), '2026-08-12' ) );
la( 'đếm tới mốc cuối, kể cả ngày cuối hụt', array( '2026-08-12' ),
	trong( array( '2026-08-10', '2026-08-11' ), '2026-08-12' ) );

la( 'chưa có dữ liệu -> không báo gì', array(), trong( array(), '2026-08-20' ) );
la( 'mốc cuối trước ngày đầu -> rỗng', array(), trong( array( '2026-08-20' ), '2026-08-10' ) );
la( 'mốc cuối hỏng -> rỗng', array(), trong( array( '2026-08-10' ), 'hôm nay' ) );
la( 'ngày rác bị bỏ qua', array( '2026-08-11' ),
	trong( array( '2026-08-10', 'xxx', '', '2026-08-12' ), '2026-08-12' ) );
/* Vắt tháng: dữ liệu từ 30/7, mốc cuối 02/8 -> thiếu 31/7 và 01/8. */
la( 'vắt tháng vẫn đếm đúng', array( '2026-07-31', '2026-08-01' ),
	trong( array( '2026-07-30', '2026-08-02' ), '2026-08-02' ) );
/* Ngày trùng nhau trong đầu vào không được đẻ ra ngày trống giả. */
la( 'ngày lặp không ảnh hưởng', array(),
	trong( array( '2026-08-10', '2026-08-10', '2026-08-11' ), '2026-08-11' ) );

/* ======================= QUYỀN ======================= */
echo "— quyền —\n";
require __DIR__ . '/../../wordpress/vhcp-cong/includes/class-vcg-quyen.php';

/* Nạp sheet NHÂN VIÊN: chỉ Admin. Đây là dữ liệu chung của mọi plugin. */
la( 'Admin nạp được sheet NV',        true,  VCG_Quyen::nap_nhan_vien( 'ADMIN' ) );
la( 'Quản lý vùng KHÔNG nạp sheet NV', false, VCG_Quyen::nap_nhan_vien( 'QUAN_LY' ) );
la( 'Kế toán KHÔNG nạp sheet NV',      false, VCG_Quyen::nap_nhan_vien( 'KE_TOAN' ) );
la( 'CHT KHÔNG nạp sheet NV',          false, VCG_Quyen::nap_nhan_vien( 'CUA_HANG_TRUONG' ) );

/* Nạp sheet CƠ SỞ: Admin, quản lý vùng, cửa hàng trưởng. Kế toán KHÔNG — giữ lớp soát chéo. */
la( 'Admin nạp được sheet cơ sở',     true,  VCG_Quyen::nap_co_so( 'ADMIN' ) );
la( 'Quản lý vùng nạp được',          true,  VCG_Quyen::nap_co_so( 'QUAN_LY' ) );
la( 'CHT nạp được',                   true,  VCG_Quyen::nap_co_so( 'CUA_HANG_TRUONG' ) );
la( '🔴 Kế toán KHÔNG nạp cơ sở',      false, VCG_Quyen::nap_co_so( 'KE_TOAN' ) );

/* Vai LẠ không được coi là hợp lệ. Vai trò trong hệ cũ là chuỗi tự do nên rác lọt vào được. */
la( 'vai rỗng không nạp được',        false, VCG_Quyen::nap_co_so( '' ) );
la( 'vai lạ không nạp được',          false, VCG_Quyen::nap_co_so( 'NHAN_VIEN' ) );
la( 'vai bịa không nạp được',         false, VCG_Quyen::nap_co_so( 'SIEU_ADMIN' ) );
la( 'vai rỗng không xem được',        false, VCG_Quyen::xem( '' ) );

/* Cả bốn vai đều XEM được — khác nhau ở phạm vi, không ở việc có xem được hay không. */
foreach ( array( 'ADMIN', 'QUAN_LY', 'KE_TOAN', 'CUA_HANG_TRUONG' ) as $v ) {
	la( "  $v xem được", true, VCG_Quyen::xem( $v ) );
}

/* Chuẩn hoá tên vai: hệ cũ ghi chuỗi tự do nên phải chịu được đủ kiểu viết.
   Trượt về phía TỪ CHỐI thì người ta báo ngay; trượt về phía CHO PHÉP thì không ai báo. */
la( 'admin thường -> ADMIN',      'ADMIN', VCG_Quyen::chuan( 'admin' ) );
la( 'có khoảng trắng thừa',       'ADMIN', VCG_Quyen::chuan( '  Admin  ' ) );
la( 'CHT -> CUA_HANG_TRUONG',     'CUA_HANG_TRUONG', VCG_Quyen::chuan( 'cht' ) );
la( 'cua hang truong (cách)',     'CUA_HANG_TRUONG', VCG_Quyen::chuan( 'cua hang truong' ) );
la( 'quan-ly (gạch ngang)',       'QUAN_LY', VCG_Quyen::chuan( 'quan-ly' ) );
la( 'ke toan',                    'KE_TOAN', VCG_Quyen::chuan( 'ke toan' ) );
la( 'admin viết hoa sẵn',         'ADMIN', VCG_Quyen::nap_nhan_vien( 'admin' ) ? 'ADMIN' : 'x' );

/* PHẠM VI CƠ SỞ. Trả true = mọi cơ sở; trả mảng = chỉ những cơ sở trong mảng. */
echo "— phạm vi cơ sở —\n";
la( 'Admin: mọi cơ sở',   true, VCG_Quyen::pham_vi( 'ADMIN', array() ) );
la( 'Kế toán: mọi cơ sở', true, VCG_Quyen::pham_vi( 'KE_TOAN', array() ) );
la( 'CHT: đúng cơ sở mình', array( 'TUTU_TP' ),
	VCG_Quyen::pham_vi( 'CUA_HANG_TRUONG', array( 'TUTU_TP' ) ) );
la( 'QL vùng: nhiều cơ sở', array( 'TUTU_TP', 'FF_SC' ),
	VCG_Quyen::pham_vi( 'QUAN_LY', array( 'TUTU_TP', ' FF_SC ' ) ) );
la( 'CHT chưa gán cơ sở nào -> RỖNG', array(),
	VCG_Quyen::pham_vi( 'CUA_HANG_TRUONG', array() ) );

/* 🔴 Chốt quan trọng nhất của khối này: người chưa được gán cơ sở nào thì KHÔNG vào được cơ
   sở nào cả. Nếu nhầm mảng rỗng thành "mọi cơ sở" thì mọi tài khoản chưa cấu hình xong đều
   mở toang toàn hệ — và không ai phát hiện ra vì màn hình vẫn hiện bình thường. */
la( 'CHT chưa gán -> KHÔNG vào được', false,
	VCG_Quyen::duoc_co_so( 'CUA_HANG_TRUONG', array(), 'TUTU_TP' ) );
la( 'CHT vào đúng cơ sở mình',  true,
	VCG_Quyen::duoc_co_so( 'CUA_HANG_TRUONG', array( 'TUTU_TP' ), 'TUTU_TP' ) );
la( 'CHT KHÔNG vào cơ sở khác', false,
	VCG_Quyen::duoc_co_so( 'CUA_HANG_TRUONG', array( 'TUTU_TP' ), 'FF_SC' ) );
la( 'Admin vào cơ sở bất kỳ',   true,
	VCG_Quyen::duoc_co_so( 'ADMIN', array(), 'CO_SO_MOI_TINH' ) );


echo "\n";
if ( $hong ) { printf( "🔴 HỎNG: %d | ĐẠT: %d\n", $hong, $dat ); exit( 1 ); }
printf( "✓ SẠCH — %d phép, chạy trên tệp CSV THẬT\n", $dat );
