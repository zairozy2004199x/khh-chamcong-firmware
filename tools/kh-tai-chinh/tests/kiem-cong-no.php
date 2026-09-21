<?php
/**
 * Kiểm công nợ phải thu / phải trả.
 *
 *   php tools/kh-tai-chinh/tests/kiem-cong-no.php
 *
 * Điều phải đúng tuyệt đối: còn nợ = tổng chứng từ − tổng đã trả, không bao
 * giờ âm, và không có con số "đã trả" nào được lưu sẵn ở chỗ thứ hai.
 */

error_reporting( E_ALL );
set_error_handler(
	function ( $n, $s, $f, $l ) {
		throw new ErrorException( $s, 0, $n, $f, $l );
	}
);

require __DIR__ . '/gia-lap-wp.php';

$dat  = 0;
$hong = array();

function kiem( $ten, $that, $mong ) {
	global $dat, $hong;
	if ( $that === $mong ) { $dat++; return; }
	$hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) );
}
function co( $ten, $html, $chuoi ) {
	global $dat, $hong;
	if ( false !== strpos( $html, $chuoi ) ) { $dat++; return; }
	$hong[] = sprintf( '%s: không thấy "%s" trong trang', $ten, $chuoi );
}
function dung( $ham ) {
	ob_start();
	try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; }
	return ob_get_clean();
}
/** Còn nợ của một chứng từ, đọc lại từ đầu qua con_no(). */
function con( $loai, $id ) {
	foreach ( KHTC_CongNo::con_no( $loai, '2026-09-30' ) as $r ) {
		if ( (int) $r->id === (int) $id ) { return (int) $r->_con; }
	}
	return 0;
}

KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them( array( 'ten' => 'Vietcombank 0123', 'so_du_dau' => 0, 'ngay_dau' => '2026-07-01' ) );

// -------------------------------------------------------------- phải thu
$a = KHTC_HoaDonRa::them( array( 'ngay' => '01/08/2026', 'so_hd' => 'HD001', 'khach' => 'CONG TY ABC', 'co_vat' => '10.800.000', 'thue_suat' => '8' ) );
$b = KHTC_HoaDonRa::them( array( 'ngay' => '20/08/2026', 'so_hd' => 'HD002', 'khach' => 'CONG TY ABC', 'co_vat' => '5.400.000', 'thue_suat' => '8' ) );
$d = KHTC_HoaDonRa::them( array( 'ngay' => '10/09/2026', 'so_hd' => 'HD003', 'khach' => 'CONG TY XYZ', 'co_vat' => '3.240.000', 'thue_suat' => '8' ) );

$t = KHTC_CongNo::tong( 'thu', '2026-09-30' );
kiem( 'chưa trả gì thì nợ bằng tổng hoá đơn', $t['tong'], 19440000 );
kiem( 'đếm đúng 3 chứng từ', $t['so_ct'], 3 );
kiem( 'gom thành 2 khách', count( KHTC_CongNo::theo_doi_tac( 'thu', '2026-09-30' ) ), 2 );

$theo = KHTC_CongNo::theo_doi_tac( 'thu', '2026-09-30' );
kiem( 'khách nợ nhiều xếp trên', $theo[0]['ten'], 'CONG TY ABC' );
kiem( 'tổng của khách đó đúng', $theo[0]['tong'], 16200000 );
kiem( 'tổng các đối tác cộng lại bằng tổng chung', array_sum( array_column( $theo, 'tong' ) ), $t['tong'] );
kiem( 'tổng các mốc cộng lại bằng tổng chung', array_sum( $t['moc'] ), $t['tong'] );

// -------------------------------------------------------- trả làm nhiều đợt
kiem( 'ghi trả một phần', is_int( KHTC_CongNo::ghi( 'hd_ra', $a, '05/09/2026', '4.000.000' ) ), true );
kiem( 'còn nợ trừ đúng phần đã trả', con( 'thu', $a ), 6800000 );
kiem( 'ghi tiếp đợt hai', is_int( KHTC_CongNo::ghi( 'hd_ra', $a, '10/09/2026', '6.800.000' ) ), true );
kiem( 'trả đủ thì biến khỏi danh sách còn nợ', con( 'thu', $a ), 0 );
kiem( 'da_tra cộng đúng hai đợt', KHTC_CongNo::da_tra( 'hd_ra', $a ), 10800000 );
kiem( 'hai dòng thanh toán đều được giữ', count( KHTC_CongNo::ds_thanh_toan( 'hd_ra', $a ) ), 2 );

// Trả quá số còn nợ phải bị chặn, kể cả khi đã trả đủ.
kiem( 'trả thêm khi đã trả đủ bị chặn', is_wp_error( KHTC_CongNo::ghi( 'hd_ra', $a, '11/09/2026', '1.000' ) ), true );
kiem( 'trả quá phần còn lại bị chặn', is_wp_error( KHTC_CongNo::ghi( 'hd_ra', $b, '11/09/2026', '5.400.001' ) ), true );
kiem( 'trả đúng bằng phần còn lại thì được', is_int( KHTC_CongNo::ghi( 'hd_ra', $b, '11/09/2026', '5.400.000' ) ), true );
kiem( 'số 0 bị chặn', is_wp_error( KHTC_CongNo::ghi( 'hd_ra', $d, '11/09/2026', '0' ) ), true );
kiem( 'chứng từ không có bị chặn', is_wp_error( KHTC_CongNo::ghi( 'hd_ra', 999999, '11/09/2026', '1.000' ) ), true );

// Xoá dòng thanh toán thì nợ quay lại.
$ds = KHTC_CongNo::ds_thanh_toan( 'hd_ra', $b );
kiem( 'xoá được dòng thanh toán', KHTC_CongNo::xoa( (int) $ds[0]->id ), true );
kiem( 'nợ quay lại đủ', con( 'thu', $b ), 5400000 );

// ------------------------------------------------------------- tuổi nợ
kiem( 'mốc 0: nợ 10 ngày', KHTC_CongNo::o_moc( 10 ), 0 );
kiem( 'mốc 0: đúng 30 ngày', KHTC_CongNo::o_moc( 30 ), 0 );
kiem( 'mốc 1: 31 ngày', KHTC_CongNo::o_moc( 31 ), 1 );
kiem( 'mốc 2: 90 ngày', KHTC_CongNo::o_moc( 90 ), 2 );
kiem( 'mốc cuối: 91 ngày', KHTC_CongNo::o_moc( 91 ), 3 );
kiem( 'tên mốc đọc được', KHTC_CongNo::ten_moc(), array( '1–30 ngày', '31–60 ngày', '61–90 ngày', 'trên 90 ngày' ) );

// HD002 ngày 20/08, tính đến 30/09 là 41 ngày → mốc 1.
$mot = null;
foreach ( KHTC_CongNo::con_no( 'thu', '2026-09-30' ) as $r ) { if ( (int) $r->id === $b ) { $mot = $r; } }
kiem( 'tuổi nợ tính đúng số ngày', $mot->_tuoi, 41 );
kiem( 'và rơi đúng mốc 31–60', $mot->_moc, 1 );

// Hạn thanh toán ghi riêng thì tuổi nợ tính từ hạn, không từ ngày chứng từ.
global $wpdb;
$wpdb->query( "UPDATE wp_khtc_hd_ra SET han_tt = '2026-09-25' WHERE id = $b" );
foreach ( KHTC_CongNo::con_no( 'thu', '2026-09-30' ) as $r ) { if ( (int) $r->id === $b ) { $mot = $r; } }
kiem( 'có hạn thì tính từ hạn', $mot->_tuoi, 5 );
kiem( 'và về lại mốc trong hạn đầu', $mot->_moc, 0 );
$wpdb->query( "UPDATE wp_khtc_hd_ra SET han_tt = NULL WHERE id = $b" );

// Chứng từ chưa tới hạn thì tuổi nợ bằng 0, không âm.
foreach ( KHTC_CongNo::con_no( 'thu', '2026-08-25' ) as $r ) { if ( (int) $r->id === $d ) { $mot = $r; } }
kiem( 'chưa tới hạn thì tuổi nợ bằng 0', $mot->_tuoi, 0 );

// ------------------------------------- phép đoán ghép (hàm thuần, kiểm riêng)
function ct( $id, $ngay, $so_ct, $con ) {
	return (object) array( 'id' => $id, 'ngay' => $ngay, 'so_ct' => $so_ct, 'con' => $con );
}
function sk( $id, $ngay, $ma, $tien, $dien_giai = '' ) {
	return (object) array( 'id' => $id, 'ngay' => $ngay, 'ma_gd' => $ma, 'so_tien' => $tien, 'dien_giai' => $dien_giai );
}
$D = array( 'KHTC_CongNo', 'doan_ghep' );

$r = call_user_func( $D, array( ct( 1, '2026-08-01', 'HD001', 5000000 ) ), array( sk( 9, '2026-09-20', '', 5000000 ) ) );
kiem( 'trả sau 50 ngày vẫn ghép được', $r, array( 1 => 9 ) );

$r = call_user_func( $D, array( ct( 1, '2026-08-01', 'HD001', 5000000 ) ), array( sk( 9, '2026-07-20', '', 5000000 ) ) );
kiem( 'không trả cho chứng từ chưa phát sinh', $r, array() );

$r = call_user_func( $D, array( ct( 1, '2026-08-01', 'HD001', 5000000 ) ), array( sk( 9, '2026-08-01', '', 5000000 ) ) );
kiem( 'trả đúng ngày chứng từ thì được', $r, array( 1 => 9 ) );

$r = call_user_func( $D, array( ct( 1, '2026-08-01', 'HD001', 5000000 ) ), array( sk( 9, '2026-09-20', '', 4999999 ) ) );
kiem( 'lệch 1 đồng thì không ghép', $r, array() );

// Hai chứng từ cùng số tiền: luật 2 phải im lặng, luật 1 vẫn cứu được nếu có số CT.
$hai = array( ct( 1, '2026-08-01', 'HD001', 5000000 ), ct( 2, '2026-08-05', 'HD002', 5000000 ) );
$r = call_user_func( $D, $hai, array( sk( 9, '2026-09-20', '', 5000000 ) ) );
kiem( 'hai chứng từ cùng số tiền thì không đoán bừa', $r, array() );

$r = call_user_func( $D, $hai, array( sk( 9, '2026-09-20', '', 5000000, 'CTY ABC TT HD002 THANG 8' ) ) );
kiem( 'nhưng nêu số chứng từ trong diễn giải thì ghép đúng cái đó', $r, array( 2 => 9 ) );

$r = call_user_func( $D, $hai, array( sk( 9, '2026-09-20', 'HD001', 5000000 ) ) );
kiem( 'số chứng từ nằm ở mã giao dịch cũng nhận ra', $r, array( 1 => 9 ) );

// Dấu cách và ký tự lạ trong diễn giải không được làm hỏng việc dò.
$r = call_user_func( $D, $hai, array( sk( 9, '2026-09-20', '', 5000000, 'TT hoa don HD-002/2026' ) ) );
kiem( 'bỏ qua gạch ngang và dấu cách khi dò số chứng từ', $r, array( 2 => 9 ) );

// Một dòng sao kê không được nhận hai lần.
$r = call_user_func( $D, array( ct( 1, '2026-08-01', 'HD001', 3000000 ), ct( 2, '2026-08-02', 'HD002', 3000000 ) ), array( sk( 9, '2026-09-20', '', 3000000, 'TT HD001 va HD002' ) ) );
kiem( 'một dòng sao kê chỉ đóng được một chứng từ', count( $r ), 1 );

// Chứng từ cũ nhất được cấn trước.
$r = call_user_func(
	$D,
	array( ct( 2, '2026-08-20', '', 1000000 ), ct( 1, '2026-08-01', '', 1000000 ) ),
	array( sk( 9, '2026-09-20', '', 1000000 ) )
);
kiem( 'hai chứng từ cùng tiền vẫn không đoán, kể cả khác ngày', $r, array() );

$r = call_user_func(
	$D,
	array( ct( 1, '2026-08-01', '', 1000000 ), ct( 2, '2026-08-20', '', 2000000 ) ),
	array( sk( 9, '2026-09-20', '', 2000000 ), sk( 8, '2026-09-21', '', 1000000 ) )
);
kiem( 'số tiền khác nhau thì ghép được cả hai', $r, array( 1 => 8, 2 => 9 ) );

kiem( 'không có chứng từ thì không ghép gì', call_user_func( $D, array(), array( sk( 9, '2026-09-20', '', 1000 ) ) ), array() );
kiem( 'không có sao kê thì không ghép gì', call_user_func( $D, array( ct( 1, '2026-08-01', 'X', 1000 ) ), array() ), array() );

// --------------------------------------------------------------- tự ghép
// Ba dòng thu: một đúng bằng HD002, một đúng bằng HD003, một số lạ.
KHTC_GiaoDich::dan_hang_loat(
	$nh,
	"12/09/2026\tABC thanh toan HD002\t5.400.000\tThu\n"
	. "15/09/2026\tXYZ thanh toan\t3.240.000\tThu\n"
	. "16/09/2026\tTien la khong ro\t777.000\tThu\n"
);
$g = KHTC_CongNo::tu_ghep( 'thu', '2026-09-01', '2026-09-30', $nh );
kiem( 'tự ghép bắt được 2 chứng từ', $g['ghep'], 2 );
kiem( 'tổng tiền ghép đúng', $g['tien'], 8640000 );
kiem( 'HD002 hết nợ', con( 'thu', $b ), 0 );
kiem( 'HD003 hết nợ', con( 'thu', $d ), 0 );
kiem( 'không còn khoản phải thu nào', KHTC_CongNo::tong( 'thu', '2026-09-30' )['tong'], 0 );

// Chạy lại KHÔNG được ghi đôi.
$g2 = KHTC_CongNo::tu_ghep( 'thu', '2026-09-01', '2026-09-30', $nh );
kiem( 'chạy lại vẫn ghép 2, không cộng dồn', $g2['ghep'], 2 );
kiem( 'HD002 vẫn chỉ có đúng 1 dòng thanh toán', count( KHTC_CongNo::ds_thanh_toan( 'hd_ra', $b ) ), 1 );
kiem( 'tổng đã trả HD002 không nhân đôi', KHTC_CongNo::da_tra( 'hd_ra', $b ), 5400000 );

// Dòng gõ tay phải sống sót qua lần tự ghép sau.
$e = KHTC_HoaDonRa::them( array( 'ngay' => '20/09/2026', 'so_hd' => 'HD004', 'khach' => 'CONG TY ABC', 'co_vat' => '2.000.000', 'thue_suat' => '0' ) );
KHTC_CongNo::ghi( 'hd_ra', $e, '21/09/2026', '500.000', 0, 'khach tra truoc' );
KHTC_CongNo::tu_ghep( 'thu', '2026-09-01', '2026-09-30', $nh );
$ds = KHTC_CongNo::ds_thanh_toan( 'hd_ra', $e );
kiem( 'dòng gõ tay không bị tự ghép dọn mất', count( $ds ), 1 );
kiem( 'và vẫn đúng số tiền', (int) $ds[0]->so_tien, 500000 );
kiem( 'phần còn nợ vẫn đúng', con( 'thu', $e ), 1500000 );

// ------------------------------------------------------------- phải trả
KHTC_ChiPhi::dan_hang_loat(
	"05/09/2026\tVăn phòng\tTiền điện\tEVN HCMC\t2.000.000\tCT01\n"
	. "06/09/2026\tVăn phòng\tVật tư — tiêu hao\tVP Hong Ha\t1.500.000\tCT02\n",
	$nh,
	'chuyen_khoan'
);
// Chi tiền mặt trả ngay tại chỗ, không phải công nợ.
KHTC_ChiPhi::them( array( 'ngay' => '07/09/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '300.000', 'hinh_thuc' => 'tien_mat' ) );

kiem( 'phải trả chỉ tính khoản chuyển khoản', KHTC_CongNo::tong( 'tra', '2026-09-30' )['tong'], 3500000 );
kiem( 'khoản tiền mặt không vào sổ công nợ', count( KHTC_CongNo::con_no( 'tra', '2026-09-30' ) ), 2 );

KHTC_GiaoDich::dan_hang_loat( $nh, "08/09/2026\tTT tien dien EVN\t-2.000.000\tChi\tCT01\n" );
$g3 = KHTC_CongNo::tu_ghep( 'tra', '2026-09-01', '2026-09-30', $nh );
kiem( 'phải trả ghép được 1 khoản', $g3['ghep'], 1 );
kiem( 'còn lại đúng khoản chưa chi', KHTC_CongNo::tong( 'tra', '2026-09-30' )['tong'], 1500000 );

// ---------------------- đối soát chi phí và công nợ phải nói CÙNG một con số
$ky = array( 'tu' => '2026-09-01', 'den' => '2026-09-30' );
$l  = KHTC_ChiPhi::loc( $ky );
kiem( 'màn hình Chi phí và Công nợ khớp nhau', $l['chua_tra'], KHTC_CongNo::tong( 'tra', '2026-09-30' )['tong'] + 300000 );
KHTC_ChiPhi::doi_soat( '2026-09-01', '2026-09-30', $nh );
$l2 = KHTC_ChiPhi::loc( $ky );
kiem( 'chạy đối soát chi phí không làm đã-trả nhân đôi', $l2['da_tra'], 2000000 );
kiem( 'và công nợ phải trả không đổi', KHTC_CongNo::tong( 'tra', '2026-09-30' )['tong'], 1500000 );

// ------------------------------------------------------------ khoá sổ
KHTC_Khoa::dat( '30/09/2026' );
kiem( 'không ghi được thanh toán vào kỳ đã khoá', is_wp_error( KHTC_CongNo::ghi( 'hd_ra', $e, '25/09/2026', '100.000' ) ), true );
kiem( 'không tự ghép được vào kỳ đã khoá', is_wp_error( KHTC_CongNo::tu_ghep( 'thu', '2026-09-01', '2026-09-30', $nh ) ), true );
$ds = KHTC_CongNo::ds_thanh_toan( 'hd_ra', $e );
kiem( 'không xoá được thanh toán trong kỳ khoá', is_wp_error( KHTC_CongNo::xoa( (int) $ds[0]->id ) ), true );
KHTC_Khoa::dat( '' );

// -------------------------------- ngày mặc định phải là ngày CÓ THẬT
// "tháng . '-31'" lọc trong SQL vẫn đúng nhưng <input type=date> coi là không
// hợp lệ và hiện ô trống — người dùng thấy bộ lọc trống trong khi nó đang lọc.
list( $d1, $d2 ) = KHTC_UI::thang_nay();
kiem( 'ngày đầu tháng hợp lệ', (bool) strtotime( $d1 ), true );
kiem( 'ngày cuối tháng hợp lệ', (bool) strtotime( $d2 ), true );
kiem( 'ngày cuối đúng là ngày cuối tháng', gmdate( 'Y-m-d', strtotime( $d2 . ' +1 day UTC' ) ), gmdate( 'Y-m', strtotime( $d2 . ' +1 day UTC' ) ) . '-01' );
kiem( 'hai ngày cùng một tháng', substr( $d1, 0, 7 ), substr( $d2, 0, 7 ) );

// --------------------------------------------------------------- sao lưu
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu có bảng thanh toán', isset( $sl['bang']['thanh_toan'] ), true );
kiem( 'mọi bảng đều được gom', array_keys( $sl['bang'] ), KHTC_SaoLuu::bang() );

// ------------------------------------------------------ dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = array( 'loai' => 'thu', 'den' => '2026-09-30' );
$_POST = array();
$t2 = dung( array( 'KHTC_Trang', 'cong_no' ) );
co( 'trang phải thu có bảng theo khách', $t2, 'Theo khách hàng' );
co( 'hiện tên khách còn nợ', $t2, 'CONG TY ABC' );
co( 'có cột tuổi nợ', $t2, 'Tuổi nợ' );
co( 'có nút tự ghép', $t2, 'Tự ghép' );
kiem( 'trang đóng đủ thẻ table', substr_count( $t2, '<table' ), substr_count( $t2, '</table>' ) );
kiem( 'trang đóng đủ thẻ div', substr_count( $t2, '<div' ), substr_count( $t2, '</div>' ) );

$_GET = array( 'loai' => 'tra', 'den' => '2026-09-30' );
$t3 = dung( array( 'KHTC_Trang', 'cong_no' ) );
co( 'trang phải trả đổi nhãn sang nhà cung cấp', $t3, 'Theo nhà cung cấp' );
co( 'hiện nhà cung cấp còn nợ', $t3, 'VP Hong Ha' );
co( 'nêu rõ tiền mặt không vào sổ này', $t3, 'tiền mặt không vào sổ này' );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
