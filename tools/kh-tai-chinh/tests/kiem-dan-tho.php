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
$_POST = array();

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
