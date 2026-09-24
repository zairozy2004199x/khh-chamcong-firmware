<?php
/**
 * Kiểm danh mục điểm và sinh hoá đơn từ sao kê.
 *
 *   php tools/kh-tai-chinh/tests/kiem-sinh-hd.php
 *
 * Câu hỏi sống còn ở đây chỉ có một: CÓ ĐỒNG NÀO BIẾN MẤT KHÔNG. Gom sai điểm
 * thì người ta còn thấy; gom thiếu một mã rồi im lặng thì doanh thu hụt mà
 * không ai biết cho tới lúc quyết toán.
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
function dung( $ham ) { ob_start(); try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; } return ob_get_clean(); }

global $wpdb;
KHTC_Cty::chon( 'kh_cu' );

// ------------------------------------------------------------ danh mục điểm
kiem( 'thiếu mã cửa hàng thì từ chối', is_wp_error( KHTC_Diem::them( array( 'ten_diem' => 'X' ) ) ), true );
$d1 = KHTC_Diem::them( array( 'ma_cua_hang' => 'AAA', 'ten_gian' => 'Gian 1', 'ten_diem' => 'Điểm Một', 'ma_misa' => 'M1', 'khu_vuc' => 'HCM', 'dich_vu' => 'KVC' ) );
kiem( 'thêm được điểm', is_int( $d1 ), true );
kiem( 'mã trùng bị chặn', is_wp_error( KHTC_Diem::them( array( 'ma_cua_hang' => 'AAA' ) ) ), true );
// Hai máy POS khác nhau, cùng một điểm xuất hoá đơn.
KHTC_Diem::them( array( 'ma_cua_hang' => 'BBB', 'ten_gian' => 'Gian 1b', 'ten_diem' => 'Điểm Một', 'ma_misa' => 'M1', 'khu_vuc' => 'HCM', 'dich_vu' => 'KVC' ) );
KHTC_Diem::them( array( 'ma_cua_hang' => 'CCC', 'ten_gian' => 'Gian 2', 'ten_diem' => 'Điểm Hai', 'ma_misa' => 'M2', 'khu_vuc' => 'Hà Nội', 'dich_vu' => 'ghế' ) );
KHTC_Diem::them( array( 'ma_cua_hang' => 'TEST', 'ten_diem' => 'Máy test', 'bo_qua' => 1 ) );
kiem( 'đếm đúng 4 điểm', KHTC_Diem::dem()['tong'], 4 );
kiem( 'trong đó 1 điểm bỏ qua', KHTC_Diem::dem()['bo_qua'], 1 );
// Pháp nhân khác dùng lại được đúng mã đó.
KHTC_Cty::chon( 'kh_moi' );
kiem( 'pháp nhân khác dùng lại được mã AAA', is_int( KHTC_Diem::them( array( 'ma_cua_hang' => 'AAA', 'ten_diem' => 'Điểm bên kia' ) ) ), true );
kiem( 'và danh mục hai bên tách bạch', KHTC_Diem::dem()['tong'], 1 );
KHTC_Cty::chon( 'kh_cu' );

// Dán hàng loạt: mã đã có thì cập nhật đè, KHÔNG được xoá cờ bỏ qua.
$kq = KHTC_Diem::dan_hang_loat( "AAA\tGian 1 doi ten\tMB1\tĐiểm Một\tM1\tHCM\tKVC\t123\nDDD\tGian 4\t\tĐiểm Bốn\tM4\tHCM\tKVC\t123\n" );
kiem( 'dán: thêm 1 mã mới', $kq['them'], 1 );
kiem( 'dán: cập nhật 1 mã cũ', $kq['sua'], 1 );
kiem( 'cập nhật đè đúng tên gian', KHTC_Diem::mot( $d1 )->ten_gian, 'Gian 1 doi ten' );
$test = $wpdb->get_row( "SELECT * FROM " . KHTC_DB::bang('diem') . " WHERE ma_cua_hang='TEST' AND cty='kh_cu'" );
kiem( 'nạp lại danh mục không xoá mất cờ bỏ qua', (int) $test->bo_qua, 1 );
kiem( 'dán bỏ qua dòng tiêu đề', KHTC_Diem::dan_hang_loat( "Mã cửa hàng\tTên gian\n" )['them'], 0 );

// ------------------------------------------------------------- gom sao kê
$nh = KHTC_NganHang::them( array( 'ten' => 'TK QR', 'so_tk' => 'QR1', 'so_du_dau' => 0, 'ngay_dau' => '2026-07-31' ) );
KHTC_GiaoDich::dan_hang_loat( $nh,
	  "05/08/2026\tQR\t100.000\tthu\tF1\tAAA\n"
	. "06/08/2026\tQR\t200.000\tthu\tF2\tBBB\n"    // cùng điểm với AAA
	. "07/08/2026\tQR\t300.000\tthu\tF3\tCCC\n"
	. "08/08/2026\tQR\t400.000\tthu\tF4\tTEST\n"   // điểm bỏ qua
	. "09/08/2026\tQR\t500.000\tthu\tF5\tZZZ\n"    // mã lạ
	. "10/08/2026\tQR\t600.000\tthu\tF6\t\n"       // không có mã
	. "15/09/2026\tQR\t700.000\tthu\tF7\tAAA\n"    // NGOÀI kỳ
);
$ky = array( 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => array( $nh ) );
$g  = KHTC_SinhHD::gom( $ky );

kiem( 'gom ra 2 điểm', count( $g['diem'] ), 2 );
$theo = array();
foreach ( $g['diem'] as $x ) { $theo[ $x['ten_diem'] ] = $x; }
kiem( 'hai mã cùng một điểm được cộng vào một tờ', $theo['Điểm Một']['tien'], 300000 );
kiem( 'và đếm đúng 2 dòng', $theo['Điểm Một']['so_dong'], 2 );
kiem( 'điểm hai đúng tiền', $theo['Điểm Hai']['tien'], 300000 );
kiem( 'mang theo mã Misa', $theo['Điểm Hai']['ma_misa'], 'M2' );
kiem( 'mang theo khu vực', $theo['Điểm Hai']['khu_vuc'], 'Hà Nội' );
kiem( 'tổng vào hoá đơn', $g['tong'], 600000 );

// Ba loại tiền KHÔNG vào hoá đơn, mỗi loại phải đếm riêng và nói ra.
kiem( 'điểm bỏ qua tách riêng', $g['tong_bo'], 400000 );
kiem( 'mã lạ tách riêng', $g['tong_la'], 1100000 );   // ZZZ 500k + dòng không mã 600k
kiem( 'mã lạ liệt kê được từng mã', isset( $g['la']['ZZZ'] ), true );
kiem( 'dòng không có mã cũng vào nhóm lạ chứ không biến mất', isset( $g['la'][''] ), true );

// KHÔNG ĐỒNG NÀO ĐƯỢC BIẾN MẤT. Đây là phép kiểm quan trọng nhất của cả tệp.
$tong_sao_ke = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang('giao_dich') . " WHERE ngan_hang_id=%d AND loai='thu' AND ngay>=%s AND ngay<=%s",
	$nh, '2026-08-01', '2026-08-31' ) );
kiem( 'tổng vào + bỏ qua + lạ = đúng tiền trong kỳ', $g['tong'] + $g['tong_bo'] + $g['tong_la'], $tong_sao_ke );
kiem( 'và tiền ngoài kỳ không lọt vào', $tong_sao_ke, 2100000 );

// ------------------------------------------------ tách theo ngày doanh thu
//
// Hoá đơn thật của công ty dùng cả hai kiểu: KH705 gần như một tờ cho mỗi
// (điểm × ngày xuất), KH989 dồn 294 tờ vào một ngày xuất. Nên độ mịn là lựa
// chọn, không phải giả định.
$gn = KHTC_SinhHD::gom( $ky + array( 'tach' => 'ngay' ) );
kiem( 'tách theo ngày: ba tờ thay vì hai', count( $gn['diem'] ), 3 );
// AAA 05/08 (100k) và BBB 06/08 (200k) cùng "Điểm Một" nhưng khác ngày → hai tờ.
$ng = array();
foreach ( $gn['diem'] as $x ) { $ng[] = array( $x['ngay'], $x['ten_diem'], $x['tien'] ); }
kiem( 'xếp theo ngày tăng dần', $ng[0][0], '2026-08-05' );
kiem( 'tờ đầu đúng điểm', $ng[0][1], 'Điểm Một' );
kiem( 'tờ đầu đúng tiền', $ng[0][2], 100000 );
kiem( 'cùng điểm khác ngày thì tách ra', $ng[1][1], 'Điểm Một' );
kiem( 'và mang ngày khác', $ng[1][0], '2026-08-06' );

// Dù tách hay không, TỔNG TIỀN phải y hệt — tách chỉ đổi cách chia tờ.
kiem( 'tách không làm đổi tổng tiền', $gn['tong'], $g['tong'] );
kiem( 'không làm đổi tiền bỏ qua', $gn['tong_bo'], $g['tong_bo'] );
kiem( 'không làm đổi tiền mã lạ', $gn['tong_la'], $g['tong_la'] );
kiem( 'và tổng số dòng vẫn thế', array_sum( array_column( $gn['diem'], 'so_dong' ) ), array_sum( array_column( $g['diem'], 'so_dong' ) ) );
// Gộp thì không mang ngày, để màn hình biết có hiện cột Ngày hay không.
kiem( 'gộp cả kỳ thì không mang ngày', $g['diem'][0]['ngay'], '' );

// -------------------------------------------------------- tạo hoá đơn
kiem( 'thiếu ngày hoá đơn thì từ chối', is_wp_error( KHTC_SinhHD::tao( $ky + array( 'bat_dau' => 1 ) ) ), true );
kiem( 'thiếu số bắt đầu thì từ chối', is_wp_error( KHTC_SinhHD::tao( $ky + array( 'ngay_hd' => '31/08/2026' ) ) ), true );
kiem( 'kỳ rỗng thì từ chối', is_wp_error( KHTC_SinhHD::tao( array( 'tu' => '2027-01-01', 'den' => '2027-01-31', 'nh' => array( $nh ), 'ngay_hd' => '31/01/2027', 'bat_dau' => 1 ) ) ), true );

$t = KHTC_SinhHD::tao( $ky + array( 'ngay_hd' => '31/08/2026', 'bat_dau' => 5001, 'thue_suat' => '8' ) );
kiem( 'tạo được', is_wp_error( $t ), false );
kiem( 'đúng 2 hoá đơn', $t['tao'], 2 );
kiem( 'số cuối đúng', $t['so_cuoi'], 5002 );
kiem( 'tiền đúng', $t['tien'], 600000 );

$l = KHTC_HoaDonRa::loc( array() );
kiem( 'tổng có VAT bằng đúng tiền gom vào', $l['co_vat'], $g['tong'] );
kiem( 'chưa VAT + VAT = có VAT', $l['chua_vat'] + $l['vat'], $l['co_vat'] );
$hd = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang('hd_ra') . ' WHERE so_hd=%s', '5001' ) );
kiem( 'hoá đơn mang tên điểm', $hd->ma_diem, 'Điểm Hai' );
kiem( 'và mã Misa', $hd->ma_misa, 'M2' );
kiem( 'ghi rõ là sinh từ sao kê', false !== strpos( $hd->ghi_chu, 'Sinh từ sao kê' ), true );

// Số trùng thì DỪNG HẲN, không ghi nửa vời — số hoá đơn nhảy cóc là thứ
// cơ quan thuế hỏi đầu tiên.
$truoc = KHTC_HoaDonRa::loc( array() )['so_hd'];
$t2 = KHTC_SinhHD::tao( $ky + array( 'ngay_hd' => '31/08/2026', 'bat_dau' => 5002 ) );
kiem( 'số bắt đầu đụng số đã có thì từ chối', is_wp_error( $t2 ), true );
kiem( 'và KHÔNG ghi thêm dòng nào', KHTC_HoaDonRa::loc( array() )['so_hd'], $truoc );
// Số trùng nằm GIỮA dải cũng phải chặn, không phải chỉ số đầu.
KHTC_HoaDonRa::them( array( 'ngay' => '31/08/2026', 'so_hd' => '7002', 'co_vat' => '1.000' ) );
$t3 = KHTC_SinhHD::tao( $ky + array( 'ngay_hd' => '31/08/2026', 'bat_dau' => 7001 ) );
kiem( 'số trùng ở giữa dải cũng chặn', is_wp_error( $t3 ), true );
kiem( 'vẫn không ghi thêm dòng nào', KHTC_HoaDonRa::loc( array() )['so_hd'], $truoc + 1 );

// ------------------------------------------- mỗi dòng tiền chỉ vào MỘT tờ
// Lỗi suýt xảy ra trên dữ liệu thật 23/09/2026: tạo xong, thêm mã vào danh
// mục rồi xem lại cùng ngày — máy bày lại y nguyên các tờ đã xuất.
$id_5001 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang('hd_ra') . ' WHERE so_hd=%s', '5001' ) );
kiem( 'dòng sao kê đã đứng tên tờ 5001', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang('giao_dich') . ' WHERE hd_ra_id=%d', $id_5001 ) ) > 0, true );
$g_lai = KHTC_SinhHD::gom( $ky );
kiem( 'chạy lại cùng kỳ: không đề xuất lại tờ nào', $g_lai['diem'], array() );
kiem( 'nhưng nói rõ đã xuất bao nhiêu tiền', $g_lai['da_xuat']['tien'], 600000 );
kiem( 'và trong mấy tờ', $g_lai['da_xuat']['to'], 2 );
kiem( 'tiền bỏ qua / mã lạ vẫn hiện như cũ', array( $g_lai['tong_bo'], $g_lai['tong_la'] ), array( $g['tong_bo'], $g['tong_la'] ) );
kiem( 'tạo lại thì từ chối vì không còn gì', is_wp_error( KHTC_SinhHD::tao( $ky + array( 'ngay_hd' => '31/08/2026', 'bat_dau' => 9001 ) ) ), true );
// Xoá một tờ → tiền của nó quay về, tờ kia vẫn đứng.
KHTC_HoaDonRa::xoa( $id_5001 );
$g_xoa = KHTC_SinhHD::gom( $ky );
kiem( 'xoá tờ 5001 thì điểm của nó quay lại đề xuất', count( $g_xoa['diem'] ), 1 );
kiem( 'đúng là điểm của tờ đã xoá', $g_xoa['diem'][0]['ten_diem'], 'Điểm Hai' );
kiem( 'tờ còn lại vẫn tính là đã xuất', $g_xoa['da_xuat']['to'], 1 );
kiem( 'không đồng nào biến mất: đề xuất + đã xuất = tổng ban đầu', $g_xoa['tong'] + $g_xoa['da_xuat']['tien'], $g['tong'] );
// Trả nốt về để kiểm phần tách ngày trên dữ liệu sạch
$id_5002 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang('hd_ra') . ' WHERE so_hd=%s', '5002' ) );
KHTC_HoaDonRa::xoa( $id_5002 );
kiem( 'xoá hết thì không dòng nào còn đứng tên tờ', (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang('giao_dich') . ' WHERE hd_ra_id > 0' ), 0 );

// Số kế tiếp = số lớn nhất trong sổ + 1 (7002 đã thêm tay ở trên)
kiem( 'số kế tiếp là số lớn nhất + 1', KHTC_SinhHD::so_tiep(), 7003 );

// Tạo theo kiểu tách ngày: ghi chú phải nói rõ NGÀY DOANH THU, vì ngày hoá đơn
// là ngày xuất — hai thứ khác nhau và kế toán cần truy lại được.
$t4 = KHTC_SinhHD::tao( $ky + array( 'tach' => 'ngay', 'ngay_hd' => '31/08/2026', 'bat_dau' => 8001 ) );
kiem( 'tạo theo ngày ra 3 tờ', $t4['tao'], 3 );
kiem( 'báo cả số đầu', $t4['so_dau'], 8001 );
$hd4 = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang('hd_ra') . ' WHERE so_hd=%s', '8001' ) );
kiem( 'ghi chú nói ngày doanh thu', false !== strpos( $hd4->ghi_chu, 'doanh thu ngày' ), true );
kiem( 'chọn một ngày thì ngày hoá đơn là ngày đã chọn', $hd4->ngay, '2026-08-31' );

// Ngày hoá đơn THEO NGÀY DOANH THU của từng tờ — "mỗi điểm mỗi ngày một tờ" đúng nghĩa.
foreach ( array( '8001', '8002', '8003' ) as $so ) { KHTC_HoaDonRa::xoa( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . KHTC_DB::bang('hd_ra') . ' WHERE so_hd=%s', $so ) ) ); }
kiem( 'gộp cả kỳ mà đòi ngày theo tờ thì từ chối', is_wp_error( KHTC_SinhHD::tao( $ky + array( 'tach' => '', 'ngay_hd' => 'theo_ngay', 'bat_dau' => 8001 ) ) ), true );
$t5 = KHTC_SinhHD::tao( $ky + array( 'tach' => 'ngay', 'ngay_hd' => 'theo_ngay', 'bat_dau' => 8001 ) );
kiem( 'theo ngày doanh thu: tạo được 3 tờ', $t5['tao'], 3 );
$to5 = $wpdb->get_results( 'SELECT so_hd, ngay, ghi_chu FROM ' . KHTC_DB::bang('hd_ra') . " WHERE so_hd IN ('8001','8002','8003') ORDER BY so_hd" );
$ngay5 = array_map( fn( $r ) => $r->ngay, $to5 );
$ngay_dt = $wpdb->get_col( 'SELECT DISTINCT ngay FROM ' . KHTC_DB::bang('giao_dich') . ' WHERE hd_ra_id > 0 ORDER BY ngay' );
kiem( 'mỗi tờ mang đúng ngày doanh thu của dòng tiền trong nó', $ngay5, $ngay_dt );
kiem( 'ba tờ là ba ngày khác nhau', count( array_unique( $ngay5 ) ), 3 );
kiem( 'ngày không giảm khi số tăng', $ngay5, ( function ( $a ) { sort( $a ); return $a; } )( $ngay5 ) );
kiem( 'ngày tờ đầu là ngày doanh thu sớm nhất, không phải cuối kỳ', $ngay5[0] < '2026-08-31', true );
foreach ( $to5 as $r ) { kiem( "tờ {$r->so_hd}: ghi chú khớp ngày hoá đơn", false !== strpos( $r->ghi_chu, mysql2date( 'd/m/Y', $r->ngay ) ), true ); }

// ------------------------------------------- tiền mini app: tra cơ sở qua bảng đơn
// Dòng VNPay ghi "thanh toan don hang 141819 tu funzone" dưới mã điểm thu chung
// FUNZONE1; cơ sở nằm trong đơn. Bảng đơn là bảng TRA, không phải tiền.
$nh_app = KHTC_NganHang::them( array( 'ten' => 'TK app', 'so_tk' => 'APP1', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-31' ) );
KHTC_GiaoDich::dan_hang_loat( $nh_app,
	"23/09/2026\tthanh toan don hang 141819 tu funzone tri gia 200 000 d\t200.000\tthu\tVN1\tFUNZONE1\n"
	. "23/09/2026\tthanh toan don hang 141815 tu funzone\t150.000\tthu\tVN2\tFUNZONE1\n"
	. "23/09/2026\tthanh toan don hang 999999 tu funzone\t70.000\tthu\tVN3\tFUNZONE1\n"   // đơn không có trong bảng
	. "23/09/2026\tTHANH TOAN QR PAY\t50.000\tthu\tVN4\tGHOSTVCT\n" );
$ky_app = array( 'tu' => '2026-09-23', 'den' => '2026-09-23', 'nh' => array( $nh_app ), 'tach' => 'ngay' );
$g0 = KHTC_SinhHD::gom( $ky_app );
kiem( 'chưa có bảng đơn: cả 4 dòng là mã lạ theo mã cổng', array_keys( $g0['la'] ), array( 'FUNZONE1', 'GHOSTVCT' ) );
kiem( 'chưa tra được gì', $g0['qua_app']['dong'], 0 );
$r_don = KHTC_DonApp::nap( array(
	array( 'ma_don' => '141819', 'ngay' => '2026-09-23', 'co_so' => 'VINCOM PHAN VĂN TRỊ - SALE 50% VÉ NHÀ MA ÂM PHỦ', 'tien' => 200000, 'tt_don' => 'Đã giao', 'tt_tt' => 'Đã thanh toán' ),
	array( 'ma_don' => '141815', 'ngay' => '2026-09-23', 'co_so' => 'AEON MALL TÂN PHÚ - SALE 50% VÉ NHÀ MA ÂM PHỦ', 'tien' => 150000, 'tt_don' => 'Đã giao', 'tt_tt' => 'Đã thanh toán' ),
) );
kiem( 'lưu 2 đơn', $r_don['them'], 2 );
$r_don2 = KHTC_DonApp::nap( array( array( 'ma_don' => '141819', 'ngay' => '2026-09-23', 'co_so' => 'VINCOM PHAN VĂN TRỊ - SALE 50% VÉ NHÀ MA ÂM PHỦ', 'tien' => 200000 ) ) );
kiem( 'nạp lại đơn cũ: cập nhật, không nhân đôi', array( $r_don2['them'], $r_don2['sua'], KHTC_DonApp::dem() ), array( 0, 1, 2 ) );
kiem( 'rút mã đơn từ diễn giải', KHTC_DonApp::ma_don_trong( 'thanh toan don hang  141819 tu funzone tri gia 200 000 d' ), '141819' );
kiem( 'không rút bừa số ngắn', KHTC_DonApp::ma_don_trong( 'don hang 12 tu abc' ), '' );
$g1 = KHTC_SinhHD::gom( $ky_app );
kiem( 'tra được 2 dòng qua đơn', $g1['qua_app'], array( 'tien' => 350000, 'dong' => 2 ) );
kiem( 'cơ sở (tên sản phẩm) thành mã lạ chờ gán', isset( $g1['la']['VINCOM PHAN VĂN TRỊ - SALE 50% VÉ NHÀ MA ÂM PHỦ'] ), true );
kiem( 'gợi ý ghi rõ là đơn app', $g1['la_ten']['VINCOM PHAN VĂN TRỊ - SALE 50% VÉ NHÀ MA ÂM PHỦ'], 'đơn app #141819' );
kiem( 'đơn không có trong bảng vẫn nằm ở mã cổng', $g1['la']['FUNZONE1'], 70000 );
kiem( 'không đồng nào mất', $g1['tong'] + $g1['tong_la'] + $g1['tong_bo'], 470000 );
// gán tên sản phẩm vào điểm → tiền về đúng điểm; mã dài hơn 80 ký tự vẫn vào danh mục
$ten_dai = 'LOTTE Phan Thiết - VÉ GIẢM 40% (áp dụng khi đặt từ 2 vé trở lên) - ECOKIDS FARM';
kiem( 'mã dài > 80 ký tự vào được danh mục', is_int( KHTC_Diem::them( array( 'ma_cua_hang' => $ten_dai, 'ten_diem' => 'Farm Lotte Phan Thiết' ) ) ), true );
KHTC_Diem::them( array( 'ma_cua_hang' => 'VINCOM PHAN VĂN TRỊ - SALE 50% VÉ NHÀ MA ÂM PHỦ', 'ten_diem' => 'Nhà ma Vincom Phan Văn Trị', 'ma_misa' => 'GHOST PVT', 'khu_vuc' => 'HCM', 'dich_vu' => 'KVC' ) );
$g2 = KHTC_SinhHD::gom( $ky_app );
$diem_app = null; foreach ( $g2['diem'] as $x ) { if ( 'Nhà ma Vincom Phan Văn Trị' === $x['ten_diem'] ) { $diem_app = $x; } }
kiem( 'tiền đơn app về đúng điểm đã gán', $diem_app['tien'] ?? 0, 200000 );
kiem( 'điểm nhận nguồn là sao kê', isset( $diem_app['nguon']['sao kê'] ), true );
$_GET = array( 'tu' => '2026-09-23', 'den' => '2026-09-23', 'nh' => array( $nh_app ) ); $_REQUEST = $_GET; $_POST = array();
$h_app = dung( fn() => KHTC_Trang::sinh_hoa_don() );
kiem( 'màn hình báo dòng tra qua đơn app', false !== strpos( $h_app, 'là đơn Zalo mini app' ), true );
kiem( 'mã lạ hiện gợi ý đơn app', false !== strpos( $h_app, 'đơn app #141815' ), true );
$_GET = array(); $_REQUEST = array();

// ---------------------------------------------------------- dựng màn hình
$_GET = array( 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => array( $nh ) );
$_REQUEST = $_GET; $_POST = array();
$h = dung( fn() => KHTC_Trang::sinh_hoa_don() );
// Không truyền tach thì màn hình phải mặc định TÁCH THEO NGÀY — đó là luật
// của công ty, và bắt chọn lại mỗi lần là mời người ta chọn nhầm.
kiem( 'mặc định là tách theo ngày', false !== strpos( $h, 'mỗi điểm mỗi ngày một hoá đơn' ), true );
kiem( 'và nút tách theo ngày được chọn sẵn', false !== strpos( $h, 'value="ngay" checked' ), true );
kiem( 'có cột Ngày doanh thu', false !== strpos( $h, 'Ngày doanh thu' ), true );
kiem( 'báo tiền đã xuất trong kỳ', false !== strpos( $h, 'Đã xuất rồi trong kỳ này' ), true );
// Màn hình này chạy hằng ngày (~160 tờ mỗi lần), nên phải có nút kỳ nhanh
// theo NGÀY chứ không phải theo tháng như màn hình Báo cáo.
kiem( 'có nút Hôm qua', false !== strpos( $h, 'Hôm qua' ), true );
kiem( 'có nút 7 ngày qua', false !== strpos( $h, '7 ngày qua' ), true );
// Nút kỳ nhanh phải GIỮ LẠI nguồn tiền đã tick, nếu không bấm một cái là mất
// hết lựa chọn và bảng xem trước rỗng.
kiem( 'nút kỳ nhanh giữ nguồn tiền đã tick', false !== strpos( $h, 'nh%5B0%5D=' ) || false !== strpos( $h, 'nh[0]=' ), true );
kiem( 'và giữ cả lựa chọn tách theo ngày', false !== strpos( $h, 'tach=ngay' ), true );
// Chọn gộp cả kỳ thì đổi theo.
$_GET['tach'] = ''; $_REQUEST = $_GET;
$hg = dung( fn() => KHTC_Trang::sinh_hoa_don() );
kiem( 'chọn gộp thì đổi tiêu đề', false !== strpos( $hg, 'Xem trước — mỗi điểm một hoá đơn' ), true );
kiem( 'và bỏ cột Ngày doanh thu', false !== strpos( $hg, 'Ngày doanh thu' ), false );
unset( $_GET['tach'] ); $_REQUEST = $_GET;
kiem( 'và cảnh báo mã chưa có trong danh mục', false !== strpos( $h, 'không có trong danh mục điểm' ), true );
kiem( 'liệt kê mã lạ ra tận nơi', false !== strpos( $h, 'ZZZ' ), true );
// Thêm mã lạ vào danh mục NGAY TẠI TRANG — khỏi chạy qua Danh mục rồi quay lại.
kiem( 'có nút thêm mã tại chỗ', false !== strpos( $h, 'name="khtc_them_ma"' ), true );
kiem( 'có ô chọn điểm có sẵn', false !== strpos( $h, 'Điểm Một · M1' ), true );
kiem( 'gợi ý tên cổng ghi kèm', false !== strpos( $h, '<th>Cổng ghi tên</th>' ), true );
$la_truoc = KHTC_SinhHD::gom( $ky )['tong_la'];
// thiếu tên, không bỏ qua → từ chối
$_POST = array( 'khtc_them_ma' => 1, 'ma' => 'ZZZ', 'diem_id' => 0, 'ten_diem' => '', 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => array( $nh ) );
$_REQUEST = $_POST;
$h_loi = dung( fn() => KHTC_Trang::sinh_hoa_don() );
kiem( 'không tên không bỏ qua → báo', false !== strpos( $h_loi, 'Chưa có tên điểm xuất hoá đơn cho mã ZZZ' ), true );
kiem( 'và chưa thêm gì', KHTC_SinhHD::gom( $ky )['tong_la'], $la_truoc );
// gắn vào điểm có sẵn → kế thừa Misa/khu vực/dịch vụ, lưu tên gian gợi ý
$_POST = array( 'khtc_them_ma' => 1, 'ma' => 'ZZZ', 'diem_id' => $d1, 'ten_gian' => 'QR', 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => array( $nh ) );
$_REQUEST = $_POST;
$h_ok = dung( fn() => KHTC_Trang::sinh_hoa_don() );
kiem( 'báo đã thêm', false !== strpos( $h_ok, 'Đã thêm mã ZZZ → Điểm Một' ), true );
$zzz = KHTC_Diem::bang_tra()['ZZZ'] ?? null;
kiem( 'ZZZ vào danh mục', null !== $zzz, true );
kiem( 'kế thừa Misa của điểm gắn', $zzz->ma_misa, 'M1' );
kiem( 'kế thừa khu vực', $zzz->khu_vuc, 'HCM' );
kiem( 'tên gian = tên cổng ghi kèm', $zzz->ten_gian, 'QR' );
$g_sau = KHTC_SinhHD::gom( $ky );
kiem( 'mã lạ giảm đúng 500k', $la_truoc - $g_sau['tong_la'], 500000 );
kiem( 'ZZZ không còn trong mã lạ', isset( $g_sau['la']['ZZZ'] ), false );
kiem( 'bảng dưới tính lại ngay trong cùng lần hiện', false !== strpos( $h_ok, 'Đã thêm mã ZZZ' ) && false === strpos( $h_ok, '<code>ZZZ</code>' ), true );
// mã trùng → báo lỗi danh mục, không nổ
$_POST = array( 'khtc_them_ma' => 1, 'ma' => 'ZZZ', 'diem_id' => $d1, 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => array( $nh ) );
$_REQUEST = $_POST;
$h_trung = dung( fn() => KHTC_Trang::sinh_hoa_don() );
kiem( 'thêm lại mã đã có → báo trùng', false !== strpos( $h_trung, 'đã có trong danh mục' ), true );
$_POST = array(); $_REQUEST = array( 'tu' => '2026-08-01', 'den' => '2026-08-31', 'nh' => array( $nh ) ); $_GET = $_REQUEST;
$h2 = dung( fn() => KHTC_Trang::danh_muc_diem() );
kiem( 'màn hình danh mục dựng được', false !== strpos( $h2, 'Điểm Một' ), true );
kiem( 'hai màn hình là đường dẫn hợp lệ', isset( KHTC_Web::man_hinh()['sinh-hoa-don'], KHTC_Web::man_hinh()['danh-muc-diem'] ), true );

// Danh mục trống thì nói thẳng chứ không hiện bảng rỗng khó hiểu.
$wpdb->query( "DELETE FROM " . KHTC_DB::bang('diem') . " WHERE cty='kh_cu'" );
$h3 = dung( fn() => KHTC_Trang::sinh_hoa_don() );
kiem( 'danh mục trống thì chỉ đường đi nạp', false !== strpos( $h3, 'Danh mục điểm còn trống' ), true );

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
