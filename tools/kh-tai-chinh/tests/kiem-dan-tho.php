<?php
/**
 * Kiểm dán thô — nhận dạng file của cổng và lấy đúng cột.
 *
 *   php tools/kh-tai-chinh/tests/kiem-dan-tho.php
 *
 * Chỗ chết người ở đây là LẤY NHẦM CỘT: số vẫn vào sổ, tổng vẫn cộng, chỉ là
 * tiền nằm sai chỗ và không ai thấy. Nên mọi phép kiểm dưới đây đều soi từng
 * ô của dòng đầu, không chỉ đếm số dòng.
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

// Dựng lại đúng hình dạng tệp thật: có DÒNG RÁC trên tiêu đề, và dòng tổng ở cuối.
$qr = "\t\t93790000\t\t\t\t\t\t\t\t\t\t\n"
	. "STT\tThời gian TT\tSố tiền đến (VND)\tSố tiền đi (VND)\tLoại\tTrạng thái\tMã tham chiếu\tMã đơn hàng\tMã điểm bán\tMã cửa hàng\tTài khoản nhận\tThời gian tạo\tNội dung TT\tGhi chú\n"
	. "1\t02-08-2026 23:56:03\t50000\t0\tGiao dịch đến\tThành công\tFT001\tVPB1\tVVB322043\tW7DNR0ARCX\t11521268 - MB\t02-08-2026\tPaymentForOrder\t-\n"
	. "2\t03-08-2026 10:00:00\t200000\t0\tGiao dịch đến\tThành công\tFT002\tVPB2\tVVB322043\tW7DNR0ARCX\t11521268 - MB\t03-08-2026\tThanh toan\t-\n"
	. "3\t03-08-2026 11:00:00\t70000\t0\tGiao dịch đến\tHuỷ\tFT003\tVPB3\tVVB322043\tW7DNR0ARCX\t11521268 - MB\t03-08-2026\tHuy\t-\n"
	. "Tổng\t\t250000\n";

$kq = KHTC_DanTho::doc( $qr );
kiem( 'nhận ra sao kê QR', $kq['dinh_dang'], 'qr' );
kiem( 'đích là sao kê', $kq['dich'], 'sao_ke' );
kiem( 'bỏ được dòng rác trên tiêu đề', count( $kq['rows'] ), 2 );
kiem( 'bỏ dòng trạng thái Huỷ', $kq['bo_loc'], 1 );
kiem( 'bỏ dòng tổng cuối bảng', $kq['thieu_cot'], 1 );
kiem( 'tổng tiền đúng', $kq['tong'], 250000 );
// Soi TỪNG Ô của dòng đầu — đây mới là phép kiểm thật.
$r = $kq['rows'][0];
kiem( 'lấy đúng cột ngày', $r['ngay'], '02/08/2026' );
kiem( 'lấy đúng cột số tiền', $r['so_tien'], 50000 );
kiem( 'lấy đúng cột mã giao dịch', $r['ma_gd'], 'FT001' );
kiem( 'lấy đúng cột MÃ CỬA HÀNG (không nhầm mã điểm bán)', $r['ma_cua_hang'], 'W7DNR0ARCX' );
kiem( 'lấy đúng cột nội dung', $r['dien_giai'], 'PaymentForOrder' );
kiem( 'nhận đúng là khoản thu', $r['loai'], 'thu' );

// Payoo: số tiền ở cột 21, phí cột 22 — lệch một cột là sai hẳn con số.
$pa = "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t311286008\t2378445\t308907563\n"
	. "STT\tCửa hàng\tNgày giao dịch\tNgày tổng kết\tLoại tác nghiệp\tLoại giao dịch\tHình thức\tChi tiết\tNgân hàng\tLoại thẻ\tHình thức phát hành\tĐặc điểm\tMã QR\tSố hóa đơn\tMã chuẩn chi\tSố tham chiếu\tSố thẻ\tTên chủ thẻ\tMã giao dịch Payoo\tMã thiết bị\tMã nhân viên\tSố tiền thanh toán (₫)\tPhí xử lý giao dịch (₫)\tThành tiền (₫)\n"
	. "1\tDVGIAITRIKH_FZ_IPH\t02/08/2026\t\tThanh toán\tBán hàng\tQuét mã QR\tTK ngân hàng\t\t\t\t\tQR5U\tFUYXQG\t\t\t\t\t\tP9A1\t8989\t20000\t110\t19890\n";
$kp = KHTC_DanTho::doc( $pa );
kiem( 'nhận ra Payoo', $kp['dinh_dang'], 'payoo' );
kiem( 'đích là đợt cổng', $kp['dich'], 'cong' );
kiem( 'Payoo: số tiền là tiền THANH TOÁN, không phải thành tiền', $kp['rows'][0]['so_tien'], 20000 );
kiem( 'Payoo: lấy đúng phí', $kp['rows'][0]['phi'], 110 );
kiem( 'Payoo: mã là số hoá đơn', $kp['rows'][0]['ma_gd'], 'FUYXQG' );
kiem( 'Payoo: mã cửa hàng lấy từ cột Cửa hàng', $kp['rows'][0]['ma_cua_hang'], 'DVGIAITRIKH_FZ_IPH' );

// Không nhận ra thì phải nói thẳng, không được đoán bừa.
kiem( 'bảng lạ thì từ chối', is_wp_error( KHTC_DanTho::doc( "a\tb\tc\n1\t2\t3\n" ) ), true );
kiem( 'bảng rỗng thì từ chối', is_wp_error( KHTC_DanTho::doc( '' ) ), true );
// Thiếu dòng tiêu đề là lỗi hay gặp nhất — chỉ copy phần số.
kiem(
	'copy thiếu dòng tiêu đề thì từ chối',
	is_wp_error( KHTC_DanTho::doc( "1\t02-08-2026\t50000\t0\tGiao dịch đến\tThành công\tFT001\n" ) ),
	true
);

// Chuyển sang bảng dán rồi nạp thật — phải đi qua đường chặn trùng.
KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them( array( 'ten' => 'TK thô', 'so_tk' => 'THO1', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
$n1 = KHTC_GiaoDich::dan_hang_loat( $nh, KHTC_DanTho::ra_sao_ke( $kq['rows'] ) );
kiem( 'nạp được vào sao kê', $n1['them'], 2 );
$n2 = KHTC_GiaoDich::dan_hang_loat( $nh, KHTC_DanTho::ra_sao_ke( $kq['rows'] ) );
kiem( 'nạp lần hai bị chặn trùng hết', $n2['them'], 0 );
kiem( 'và báo đúng số dòng trùng', $n2['trung'], 2 );
global $wpdb;
kiem(
	'mã cửa hàng vào tới bảng giao dịch',
	$wpdb->get_var( $wpdb->prepare( 'SELECT ma_cua_hang FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ma_gd = %s', 'FT001' ) ),
	'W7DNR0ARCX'
);

// Bảng dán cho đợt cổng: đúng 6 cột, đúng thứ tự.
$d = explode( "\t", KHTC_DanTho::ra_cong( $kp['rows'] ) );
kiem( 'bảng đợt cổng đủ 6 cột', count( $d ), 6 );
kiem( 'cột 3 là số tiền', $d[2], '20000' );
kiem( 'cột 4 là phí', $d[3], '110' );
kiem( 'cột 6 là mã cửa hàng', trim( $d[5] ), 'DVGIAITRIKH_FZ_IPH' );

// ------------------------------------------------------------ màn hình
$_POST = array(); $_GET = array(); $GLOBALS['khtc_qv'] = array();
$h = dung( array( 'KHTC_Trang', 'dan_tho' ) );
kiem( 'màn hình dựng được', false !== strpos( $h, 'Dán nguyên cả sheet' ), true );
kiem( 'kể tên các định dạng nhận được', false !== strpos( $h, 'Sao kê QR ngân hàng' ), true );
kiem( 'nhắc phải copy cả dòng tiêu đề', false !== strpos( $h, 'dòng tiêu đề' ), true );
$_POST = array( 'tho' => $qr, '_wpnonce' => 'test' );
$h2 = dung( array( 'KHTC_Trang', 'dan_tho' ) );
kiem( 'dán vào thì hiện nhận dạng', false !== strpos( $h2, 'Nhận ra:' ), true );
kiem( 'và hiện bảng xem trước', false !== strpos( $h2, 'Soát 10 dòng đầu' ), true );
kiem( 'có nút nạp vào sao kê', false !== strpos( $h2, 'khtc_nap_sao_ke' ), true );
kiem( 'KHÔNG tự ghi khi mới xem trước', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ngan_hang_id = %d', $nh ) ), 2 );
kiem( 'dan-tho là đường dẫn hợp lệ', isset( KHTC_Web::man_hinh()['dan-tho'] ), true );

// Nạp xong phải chỉ thẳng sang bước tiếp, mang sẵn kỳ đúng bằng khoảng ngày
// vừa dán — nhưng KHÔNG được tự sinh hoá đơn.
$truoc_hd = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'hd_ra' ) );
$_POST = array( 'tho' => $qr, '_wpnonce' => 'test', 'khtc_nap_sao_ke' => 1, 'nh' => $nh );
$h3 = dung( array( 'KHTC_Trang', 'dan_tho' ) );
kiem( 'nạp xong có lối đi tiếp', false !== strpos( $h3, 'Xong bước 1' ), true );
kiem( 'mang đúng kỳ vừa dán', false !== strpos( $h3, '02/08/2026' ) && false !== strpos( $h3, '03/08/2026' ), true );
kiem( 'nói rõ dán chưa sinh hoá đơn', false !== strpos( $h3, 'Dán chưa sinh hoá đơn' ), true );
kiem( 'nhắc dán nốt file cổng khác trong ngày', false !== strpos( $h3, 'cổng khác' ), true );
kiem( 'và TUYỆT ĐỐI không tự sinh hoá đơn', (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'hd_ra' ) ), $truoc_hd );
// Lần này dán lại nên phải bị chặn trùng hết, không thêm giao dịch nào.
kiem( 'nạp lại qua màn hình cũng chặn trùng', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ngan_hang_id = %d', $nh ) ), 2 );
$_POST = array();
$_POST = array();

// ---------------------------------------------------------------- tìm cột THEO TÊN, không theo vị trí
// Lỗi thật 24/09/2026: file VNPay tải thẳng từ cổng KHÔNG có cột "Lọc ngày"
// (cột đó kế toán chèn thêm ở file tháng 8). Mọi cột lệch một → máy đọc 0 dòng.
$vn_goc = "DỮ LIỆU BÁO CÁO PHÍ THEO GD THANH TOÁN\n\tTổng số giao dịch:\t122\n"
	. "STT\tThời gian GD\tMã giao dịch\tChi nhánh\tMã điểm thu\tĐiểm thu\tSố hóa đơn\tSố hợp đồng\tMã đơn hàng\tMã trừ tiền/Mã chuẩn chi\tMã tham chiếu\tMã thiết bị\tMã khuyến mại\tSố điện thoại\tTên khách hàng\tSố TK/Thẻ\tLoại giao dịch\tMID Bank\tTID Bank\tMCC\tKênh thanh toán KTS\tSố tiền trước KM\tSố tiền khuyến mại\tSố tiền sau KM\tSố tiền hạch toán thu hộ\tNgày hạch toán thu hộ\tSố tiền phí thu hộ\tSố tiền sau khi trừ phí\tNgày hạch toán phí thu hộ\tChi tiết sản phẩm\tThông tin đặt hàng\tNgân hàng\tLoại thẻ/Tài khoản\tLoại phát hành\tNguồn tiền\tDịch vụ\tKênh thanh toán\tYC trả góp\tTrạng thái trả góp\tKỳ hạn\tSố tiền phí trả góp\tNgày hạch toán phí trả góp\tTrạng thái\tThời gian đối soát\tGhi chú\n"
	. "1\t23/09/2026 23:29:34\t340506317\tCTY\tFUNZONE1\tFUNZONE MINI APP\t491880919\t\t260923_1\t651230206\t\t\t\t034x\tNGO\txxx612\tGiao dịch thường\t\t\t\tVNPAY\t200000\t\t200000\t200000\t23/09/2026 23:29:34\t3850\t\t23/09/2026\t\tthanh toan\tMBBANK\tTK\tNội địa\tThẻ\tCTT\tQR\tKhông\t\t\t0\t\tThành công\t24/09/2026\tghi chu\n"
	// có khuyến mại: trước KM 240k, sau KM 220k, cổng hạch toán trả đủ 240k → lấy 240k
	. "2\t23/09/2026 20:00:00\t340506318\tCTY\tGHOSTVCT\tGHOST VC TIMES CITY\t491880920\t\t260923_2\t651230207\t\t\t\t\t\t\tGiao dịch thường\t\t\t\tVNPAY\t240000\t\t220000\t240000\t23/09/2026\t4620\t\t23/09/2026\t\t\tMBBANK\tTK\tNội địa\tThẻ\tCTT\tQR\tKhông\t\t\t0\t\tThành công\t24/09/2026\t\n"
	. "3\t23/09/2026 21:00:00\t340506319\tCTY\tGHOSTDN1\tGHOST BRIDE MEGA DN\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tVNPAY\t100000\t\t100000\t100000\t\t1925\t\t\t\t\t\t\t\t\t\t\t\t\t\t0\t\tThất bại\t\t\n";
$kv = KHTC_DanTho::doc( $vn_goc );
kiem( 'VNPay gốc (không Lọc ngày): nhận ra', $kv['dinh_dang'], 'vnpay' );
kiem( 'VNPay gốc: đọc đủ dòng thành công', count( $kv['rows'] ), 2 );
kiem( 'VNPay gốc: dòng Thất bại bị loại', $kv['bo_loc'], 1 );
kiem( 'VNPay gốc: ngày đúng cột', $kv['rows'][0]['ngay'], '23/09/2026' );
kiem( 'VNPay gốc: mã GD đúng cột', $kv['rows'][0]['ma_gd'], '340506317' );
kiem( 'VNPay gốc: mã điểm thu đúng cột', $kv['rows'][0]['ma_cua_hang'], 'FUNZONE1' );
kiem( 'VNPay gốc: tiền = trước KM', $kv['rows'][0]['so_tien'], 200000 );
kiem( 'VNPay gốc: phí = phí thu hộ (không phải khuyến mại)', $kv['rows'][0]['phi'], 3850 );
kiem( 'VNPay có KM: vẫn lấy trước KM vì cổng hạch toán trả đủ', $kv['rows'][1]['so_tien'], 240000 );
kiem( 'VNPay gốc: tổng', $kv['tong'], 440000 );
kiem( 'VNPay gốc: tổng phí', $kv['tong_phi'], 8470 );
kiem( 'báo tên cột đã lấy cho tiền', $kv['theo_ten']['thu'], 'Số tiền trước KM' );

// Cùng file, kế toán chèn "Lọc ngày" vào cột B → phải ra y hệt
$dong_vn = explode( "\n", $vn_goc );
foreach ( $dong_vn as $i => $d ) {
	if ( $i >= 2 && '' !== $d ) { $o = explode( "\t", $d ); array_splice( $o, 1, 0, 2 === $i ? 'Lọc ngày' : '23' ); $dong_vn[ $i ] = implode( "\t", $o ); }
}
$kv2 = KHTC_DanTho::doc( implode( "\n", $dong_vn ) );
kiem( 'VNPay chèn cột Lọc ngày: cùng số dòng', count( $kv2['rows'] ), 2 );
kiem( 'VNPay chèn cột: cùng tổng', $kv2['tong'], 440000 );
kiem( 'VNPay chèn cột: cùng phí', $kv2['tong_phi'], 8470 );
kiem( 'VNPay chèn cột: cùng mã điểm', $kv2['rows'][0]['ma_cua_hang'], 'FUNZONE1' );

// MoMo: file có "lọc ngày" ở cột A (tháng 8) và file bỏ cột đó → như nhau
$mm_a = "lọc ngày\tSTT\tMS.TransID\tMSl.ParentID\tMS.SĐT TK\tMS.Total Amount\tMS.Nợ\tMS.Có\tMS.Ngày hoàn thành\t\tMS.Loại GD\tMS.Mã HĐơn\tMS.Phân loại\tMS.Mã sản phẩm\tMS.Mã cửa hàng\tMS.Tên khách hàng\tMS.Mã đối tác\tMS.Mã khác\tMS.Trạng Thái GD\tVoucher\tTài trợ\tPaylater\t\t\tThời gian\tMã đơn hàng\tMã đơn hàng gốc\n"
	. "5\t1\tT1\t\t\t500000\t\t\t\t\tCK\t\t\t\tMOMOA\t\t\t\tThành công\t\t\t\t\t\t05-08-2026 13:00:00\tMM1\t\n"
	. "5\t2\tT2\t\t\t900000\t\t\t\t\tCK\t\t\t\tMOMOA\t\t\t\tHuỷ\t\t\t\t\t\t05-08-2026 14:00:00\tMM2\t\n";
$mm_b = implode( "\n", array_map( function ( $d ) { $o = explode( "\t", $d ); array_shift( $o ); return implode( "\t", $o ); }, explode( "\n", $mm_a ) ) );
$ka = KHTC_DanTho::doc( $mm_a ); $kb = KHTC_DanTho::doc( $mm_b );
kiem( 'MoMo có cột lọc ngày: 1 dòng thành công', count( $ka['rows'] ), 1 );
kiem( 'MoMo bỏ cột lọc ngày: vẫn 1 dòng', count( $kb['rows'] ), 1 );
kiem( 'MoMo hai bản đều loại dòng Huỷ', array( $ka['bo_loc'], $kb['bo_loc'] ), array( 1, 1 ) );
kiem( 'MoMo hai bản cùng mã cửa hàng', array( $ka['rows'][0]['ma_cua_hang'], $kb['rows'][0]['ma_cua_hang'] ), array( 'MOMOA', 'MOMOA' ) );
kiem( 'MoMo hai bản cùng mã GD', array( $ka['rows'][0]['ma_gd'], $kb['rows'][0]['ma_gd'] ), array( 'MM1', 'MM1' ) );
kiem( 'MoMo bỏ cột: ngày vẫn đúng', $kb['rows'][0]['ngay'], '05/08/2026' );

// Không có cột trạng thái → không lọc bừa
$qr_khong_tt = "STT\tThời gian TT\tSố tiền đến (VND)\tSố tiền đi (VND)\tLoại\tMã tham chiếu\tMã đơn hàng\tMã điểm bán\tMã cửa hàng\tTK\tThời gian tạo\tNội dung TT\n"
	. "1\t02-08-2026 23:56:03\t50000\t0\tGiao dịch đến\tFT001\tVPB1\tVVB\tW7D\tMB\t02-08-2026\tNoi dung\n";
$kq0 = KHTC_DanTho::doc( $qr_khong_tt );
kiem( 'QR thiếu cột Trạng thái: không lọc, vẫn đọc', count( $kq0['rows'] ), 1 );
kiem( 'QR thiếu cột: mã cửa hàng vẫn đúng theo tên', $kq0['rows'][0]['ma_cua_hang'], 'W7D' );
kiem( 'QR thiếu cột: mã GD theo tên', $kq0['rows'][0]['ma_gd'], 'FT001' );
kiem( 'chuẩn tên bỏ đơn vị', KHTC_DanTho::chuan_ten( ' Số tiền đến (VND) ' ), 'số tiền đến' );
kiem( 'chuẩn tên bỏ (₫)', KHTC_DanTho::chuan_ten( 'Phí xử lý giao dịch (₫)' ), 'phí xử lý giao dịch' );

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
