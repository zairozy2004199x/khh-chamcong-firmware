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
