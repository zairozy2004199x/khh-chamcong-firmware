<?php
/**
 * Kiểm báo cáo.
 *
 *   php tools/kh-tai-chinh/tests/kiem-bao-cao.php
 *
 * Hai điều phải đúng tuyệt đối:
 *
 *   1. Báo cáo KHÔNG được dùng "hôm nay" làm mốc. Chạy báo cáo tháng 8 vào
 *      tháng 10 phải ra đúng số của tháng 8 — kể cả số dư ngân hàng và công nợ.
 *   2. Mọi con số phải cộng khớp với chính chứng từ gốc, vì báo cáo không có
 *      bảng riêng: sai ở đây là sai ở phép cộng, không phải sai dữ liệu.
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

KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them( array( 'ten' => 'Vietcombank 0123', 'so_du_dau' => 100000000, 'ngay_dau' => '2026-07-01' ) );

// Tháng 7: thu 20tr, chi 5tr → cuối kỳ 115tr
KHTC_GiaoDich::dan_hang_loat( $nh, "10/07/2026\tThu T7\t20.000.000\tThu\n20/07/2026\tChi T7\t-5.000.000\tChi\n" );
// Tháng 8: thu 50tr, chi 30tr → đầu 115tr, cuối 135tr
KHTC_GiaoDich::dan_hang_loat( $nh, "05/08/2026\tThu T8 dot 1\t30.000.000\tThu\n15/08/2026\tThu T8 dot 2\t20.000.000\tThu\n20/08/2026\tChi T8\t-30.000.000\tChi\n" );
// Tháng 9: thêm nữa, để chắc báo cáo tháng 8 không dính số tháng 9
KHTC_GiaoDich::dan_hang_loat( $nh, "10/09/2026\tThu T9\t70.000.000\tThu\n" );

// ------------------------------------------------- số dư tính đến một ngày
$b = KHTC_NganHang::mot( $nh );
kiem( 'số dư đến hết 30/06 là số dư đầu', KHTC_NganHang::so_du_den( $b, '2026-06-30' ), 100000000 );
kiem( 'số dư đến hết 31/07', KHTC_NganHang::so_du_den( $b, '2026-07-31' ), 115000000 );
kiem( 'số dư đến hết 31/08', KHTC_NganHang::so_du_den( $b, '2026-08-31' ), 135000000 );
kiem( 'số dư hiện tại tính cả tháng 9', KHTC_NganHang::so_du( $b ), 205000000 );
kiem( 'hôm trước của 01/08 là 31/07', KHTC_BaoCao::hom_truoc( '2026-08-01' ), '2026-07-31' );
kiem( 'hôm trước của 01/01 lùi sang năm trước', KHTC_BaoCao::hom_truoc( '2026-01-01' ), '2025-12-31' );
kiem( 'biên tháng 2 năm thường', KHTC_BaoCao::bien_thang( '2026-02' ), array( '2026-02-01', '2026-02-28' ) );
kiem( 'biên tháng 2 năm nhuận', KHTC_BaoCao::bien_thang( '2028-02' ), array( '2028-02-01', '2028-02-29' ) );

// ------------------------------------------------------------ dòng tiền
$dt = KHTC_BaoCao::dong_tien( '2026-08-01', '2026-08-31' );
$r  = $dt['rows'][0];
kiem( 'đầu kỳ tháng 8', $r['dau'], 115000000 );
kiem( 'thu trong kỳ', $r['thu'], 50000000 );
kiem( 'chi trong kỳ', $r['chi'], 30000000 );
kiem( 'cuối kỳ', $r['cuoi'], 135000000 );
// Hai đường tính độc lập phải gặp nhau. Lệch khác 0 là số dư đang sai ở đâu đó.
kiem( 'hai đường tính số dư khớp nhau', $r['lech'], 0 );
kiem( 'cuối kỳ = đầu kỳ + thu − chi', $r['dau'] + $r['thu'] - $r['chi'], $r['cuoi'] );
kiem( 'tổng cộng đúng khi chỉ có một tài khoản', $dt['tong']['cuoi'], $r['cuoi'] );

// Thêm tài khoản thứ hai để chắc phần tổng cộng thật sự cộng.
$nh2 = KHTC_NganHang::them( array( 'ten' => 'MB Bank 8899', 'so_du_dau' => 50000000, 'ngay_dau' => '2026-07-01' ) );
KHTC_GiaoDich::dan_hang_loat( $nh2, "12/08/2026\tThu MB\t8.000.000\tThu\n" );
$dt2 = KHTC_BaoCao::dong_tien( '2026-08-01', '2026-08-31' );
kiem( 'hai tài khoản: tổng đầu kỳ', $dt2['tong']['dau'], 165000000 );
kiem( 'hai tài khoản: tổng cuối kỳ', $dt2['tong']['cuoi'], 193000000 );
kiem( 'hai tài khoản: tổng vẫn khớp phép cộng', $dt2['tong']['dau'] + $dt2['tong']['thu'] - $dt2['tong']['chi'], $dt2['tong']['cuoi'] );
kiem( 'không tài khoản nào lệch', array_sum( array_column( $dt2['rows'], 'lech' ) ), 0 );

// --------------------------------------------------------------- cả gói
KHTC_HoaDonRa::them( array( 'ngay' => '10/08/2026', 'so_hd' => 'RA01', 'khach' => 'CONG TY ABC', 'chua_vat' => '60.000.000', 'thue_suat' => '8', 'khu_vuc' => 'HCM', 'dich_vu' => 'KVC' ) );
KHTC_HoaDonRa::them( array( 'ngay' => '18/08/2026', 'so_hd' => 'RA02', 'khach' => 'CONG TY XYZ', 'chua_vat' => '20.000.000', 'thue_suat' => '8', 'khu_vuc' => 'HN', 'dich_vu' => 'GM' ) );
KHTC_HoaDonVao::them( array( 'ngay' => '12/08/2026', 'so_hd' => 'VAO01', 'nha_cung_cap' => 'EVN', 'mst' => '0300942001', 'chua_vat' => '10.000.000', 'thue_suat' => '8' ) );
KHTC_ChiPhi::dan_hang_loat( "12/08/2026\tKhu vui chơi\tTiền điện\tEVN\t10.800.000\tVAO01\n20/08/2026\tVăn phòng\tLương\tBang luong\t25.000.000\n", $nh );

$bc = KHTC_BaoCao::ky( '2026-08-01', '2026-08-31' );
kiem( 'doanh thu chưa VAT', $bc['doanh_thu']['chua_vat'], 80000000 );
kiem( 'VAT đầu ra', $bc['doanh_thu']['vat'], 6400000 );
kiem( 'chi phí trong kỳ', $bc['chi_phi']['tong'], 35800000 );
kiem( 'kết quả tạm tính = doanh thu − chi phí', $bc['lai_tam'], 80000000 - 35800000 );
kiem( 'thuế: VAT đầu vào khấu trừ', $bc['thue']['vat_kt'], 800000 );
kiem( 'thuế: phải nộp', $bc['thue']['phai_nop'], 6400000 - 800000 );
kiem( 'gom khu vực cộng lại bằng tổng doanh thu', array_sum( array_map( function ( $x ) { return (int) $x->chua_vat; }, $bc['doanh_thu']['khu_vuc'] ) ), $bc['doanh_thu']['chua_vat'] );
kiem( 'bảng cộng chéo chi phí khớp tổng', $bc['chi_phi']['cheo']['tong'], $bc['chi_phi']['tong'] );

// ------------------------- báo cáo kỳ cũ KHÔNG được đổi khi có dữ liệu mới
$truoc = KHTC_BaoCao::ky( '2026-08-01', '2026-08-31' );
KHTC_GiaoDich::dan_hang_loat( $nh, "25/10/2026\tThu thang 10\t99.000.000\tThu\n" );
KHTC_HoaDonRa::them( array( 'ngay' => '25/10/2026', 'so_hd' => 'RA99', 'khach' => 'Khach moi', 'chua_vat' => '77.000.000', 'thue_suat' => '8' ) );
KHTC_ChiPhi::them( array( 'ngay' => '26/10/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '5.000.000' ) );
$sau = KHTC_BaoCao::ky( '2026-08-01', '2026-08-31' );
kiem( 'thêm dữ liệu tháng 10: dòng tiền tháng 8 không đổi', $sau['dong_tien']['tong'], $truoc['dong_tien']['tong'] );
kiem( 'thêm dữ liệu tháng 10: doanh thu tháng 8 không đổi', $sau['doanh_thu']['chua_vat'], $truoc['doanh_thu']['chua_vat'] );
kiem( 'thêm dữ liệu tháng 10: chi phí tháng 8 không đổi', $sau['chi_phi']['tong'], $truoc['chi_phi']['tong'] );
kiem( 'thêm dữ liệu tháng 10: thuế tháng 8 không đổi', $sau['thue'], $truoc['thue'] );
kiem( 'thêm dữ liệu tháng 10: công nợ cuối tháng 8 không đổi', $sau['cong_no'], $truoc['cong_no'] );
kiem( 'kết quả tạm tính tháng 8 không đổi', $sau['lai_tam'], $truoc['lai_tam'] );

// Công nợ phải tính ĐẾN NGÀY CUỐI KỲ, không phải đến hôm nay.
kiem( 'công nợ cuối tháng 8 không gồm hoá đơn tháng 10', $sau['cong_no']['thu']['tong'], 86400000 );

// ------------------------------------------------------------ 12 tháng
$ch = KHTC_BaoCao::chuoi_thang( '2026-10-31', 12 );
kiem( 'chuỗi ra đúng 12 tháng', count( $ch ), 12 );
kiem( 'tháng cuối chuỗi là tháng của ngày đưa vào', $ch[11]['thang'], '2026-10' );
kiem( 'tháng đầu chuỗi lùi đúng 11 tháng', $ch[0]['thang'], '2025-11' );
$theo = array();
foreach ( $ch as $x ) { $theo[ $x['thang'] ] = $x; }
kiem( 'tháng 7 thu đúng', $theo['2026-07']['thu'], 20000000 );
kiem( 'tháng 8 thu đúng', $theo['2026-08']['thu'], 58000000 );
kiem( 'tháng 8 chi đúng', $theo['2026-08']['chi'], 30000000 );
kiem( 'tháng 8 doanh thu đúng', $theo['2026-08']['doanh_thu'], 80000000 );
kiem( 'tháng không có gì thì bằng 0', $theo['2026-01']['thu'], 0 );
kiem( 'chuỗi lùi qua năm không vỡ', KHTC_BaoCao::chuoi_thang( '2026-01-31', 3 )[0]['thang'], '2025-11' );

// --------------------------------------------------------------- tải CSV
$hang = KHTC_BaoCao::hang_csv( $sau );
$phang = array();
foreach ( $hang as $h ) { $phang[] = implode( '|', array_map( 'strval', $h ) ); }
$text = implode( "\n", $phang );
co( 'CSV có phần dòng tiền', $text, 'DÒNG TIỀN' );
co( 'CSV có phần doanh thu', $text, 'DOANH THU' );
co( 'CSV có phần chi phí', $text, 'CHI PHÍ' );
co( 'CSV có phần thuế', $text, 'THUẾ GTGT' );
co( 'CSV có phần công nợ', $text, 'CÔNG NỢ CUỐI KỲ' );
co( 'CSV nói rõ kết quả chỉ là tạm tính', $text, 'TẠM TÍNH' );
co( 'CSV cảnh báo thiếu khấu hao', $text, 'khấu hao' );

// ------------------------------------------------------ dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );
$_POST = array();
$t = dung( array( 'KHTC_Trang', 'bao_cao' ) );
co( 'trang có dòng tiền', $t, 'Số dư đầu kỳ' );
co( 'trang có bảng 12 tháng', $t, 'Mười hai tháng gần nhất' );
co( 'trang nói rõ kết quả là tạm tính', $t, 'Đừng đem đi nộp' );
co( 'trang có thuế GTGT', $t, 'VAT đầu vào được khấu trừ' );
co( 'trang có công nợ cuối kỳ', $t, 'Công nợ cuối kỳ' );
co( 'trang có nút kỳ nhanh', $t, 'Tháng trước' );
co( 'thanh biểu đồ có nhãn khi rê chuột', $t, 'title="Thu ' );
co( 'thanh chênh lệch nêu rõ chiều', $t, 'Thu nhiều hơn chi' );
// .khtc-thanh là thanh điều hướng trên cùng. Ô biểu đồ mà mang tên đó thì nav
// sụp thành cột dọc và ô ăn màu nền đen của nav — đã xảy ra thật một lần.
co( 'ô biểu đồ dùng đúng tên lớp riêng', $t, 'class="khtc-vach"' );
kiem( 'không ô biểu đồ nào đội tên lớp của thanh điều hướng', substr_count( $t, 'class="khtc-thanh"' ), 0 );
kiem( 'trang đóng đủ thẻ table', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );
kiem( 'trang đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );
// Thanh dài nhất phải đúng 100%, nếu không thước đo bị sai.
kiem( 'có đúng một thanh đạt 100%', substr_count( $t, 'width:100.00%' ), 1 );
// Thu và chi chung một thước: thanh chi tháng 8 phải ngắn hơn thanh thu tháng 8.
// Không thanh nào dài quá khung. 100.00% là hợp lệ nên phải loại trừ riêng.
kiem( 'không có thanh nào vượt 100%', preg_match( '/width:(?!100\.00%)1[0-9]{2}\.[0-9]{2}%/', $t ), 0 );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
