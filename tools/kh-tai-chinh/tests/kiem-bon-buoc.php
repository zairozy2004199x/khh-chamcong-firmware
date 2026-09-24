<?php
/**
 * Kiểm CẢ BỐN BƯỚC một lượt, đi qua đúng màn hình chứ không gọi tắt.
 *
 *   php tools/kh-tai-chinh/tests/kiem-bon-buoc.php
 *
 * Mỗi mảng đều có phép kiểm riêng rồi, nhưng chúng nối vào nhau mới là chỗ
 * hỏng: dán xong mà mã cửa hàng không tới được bước gom, hoặc gom xong mà
 * tiền không khớp tiền đã vào sổ. Phép kiểm này đi hết một buổi sáng thật:
 * dán bốn tệp của bốn cổng → xem trước → tạo hoá đơn → soát sổ.
 *
 * Câu hỏi chốt vẫn là một: CÓ ĐỒNG NÀO BIẾN MẤT GIỮA ĐƯỜNG KHÔNG.
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
function man() { ob_start(); KHTC_Trang::dan_tho(); return ob_get_clean(); }

global $wpdb;
KHTC_Cty::chon( 'kh_cu' );

// ---------------------------------------------------- nền: danh mục + tài khoản
KHTC_Diem::dan_hang_loat(
	  "QRA\tQR máy 1\t\tGian Một\tM1\tHCM\tKVC\t\n"
	. "PAYA\tPayoo gian 1\t\tGian Một\tM1\tHCM\tKVC\t\n"
	. "VNPA\tVNPay gian 1\t\tGian Một\tM1\tHCM\tKVC\t\n"
	. "MOMOA\tMoMo gian 1\t\tGian Một\tM1\tHCM\tKVC\t\n"
	. "QRB\tQR máy 2\t\tGian Hai\tM2\tHà Nội\tghế\t\n"
);
$nh = KHTC_NganHang::them( array( 'ten' => 'TK bốn bước', 'so_tk' => '4B', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-04' ) );
kiem( 'nền: 5 điểm trong danh mục', KHTC_Diem::dem()['tong'], 5 );

// ------------------------------------------------- bốn tệp thô, đúng hình dạng thật
$qr = "\t\t999\t\t\t\t\t\t\t\t\t\t\n"
	. "STT\tThời gian TT\tSố tiền đến (VND)\tSố tiền đi (VND)\tLoại\tTrạng thái\tMã tham chiếu\tMã đơn hàng\tMã điểm bán\tMã cửa hàng\tTài khoản nhận\tThời gian tạo\tNội dung TT\tGhi chú\n"
	. "1\t05-08-2026 09:00:00\t100000\t0\tGiao dịch đến\tThành công\tFT1\tX\tVVB\tQRA\t4B\t05-08-2026\tQR mot\t-\n"
	. "2\t05-08-2026 10:00:00\t200000\t0\tGiao dịch đến\tThành công\tFT2\tX\tVVB\tQRB\t4B\t05-08-2026\tQR hai\t-\n"
	. "3\t05-08-2026 11:00:00\t700000\t0\tGiao dịch đến\tThành công\tFT3\tX\tVVB\tLA_QR\t4B\t05-08-2026\tQR la\t-\n";

$payoo = "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t999\t9\t99\n"
	. "STT\tCửa hàng\tNgày giao dịch\tNgày TK\tLoại tác nghiệp\tLoại GD\tHình thức\tChi tiết\tNH\tLoại thẻ\tHT phát hành\tĐặc điểm\tMã QR\tSố hóa đơn\tMã chuẩn chi\tSố tham chiếu\tSố thẻ\tTên chủ thẻ\tMã GD Payoo\tMã thiết bị\tMã NV\tSố tiền thanh toán (₫)\tPhí xử lý giao dịch (₫)\tThành tiền (₫)\n"
	. "1\tPAYA\t05/08/2026\t\tThanh toán\tBán hàng\tQR\tTK\t\t\t\t\tQ1\tPAY1\t\t\t\t\t\tD1\t1\t300000\t1650\t298350\n";

$vnpay = "DỮ LIỆU\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t999\t9\n"
	. "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\n"
	. "STT\tLọc ngày\tThời gian GD\tMã giao dịch\tChi nhánh\tMã điểm thu\tĐiểm thu\tSố hóa đơn\tSố hợp đồng\tMã đơn hàng\tMã trừ tiền\tMã tham chiếu\tMã thiết bị\tMã KM\tSĐT\tTên KH\tc16\tc17\tc18\tc19\tc20\tc21\tSố tiền trước KM\tSố tiền phí thu hộ\n"
	. "1\t5\t05/08/2026 12:00:00\tVN1\tCN\tVNPA\tDiem VNPay\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t400000\t2000\n";

$momo = "\t\t\t\t\t999\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\n"
	. "lọc ngày\tSTT\tMS.TransID\tMSl.ParentID\tMS.SĐT TK\tMS.Total Amount\tMS.Nợ\tMS.Có\tMS.Ngày HT\tc9\tMS.Loại GD\tMS.Mã HĐơn\tMS.Phân loại\tMS.Mã SP\tMS.Mã cửa hàng\tMS.Tên KH\tMS.Mã đối tác\tc17\tTrạng thái\tc19\tc20\tc21\tc22\tc23\tThời gian\tMã\tc26\n"
	. "5\t1\tT1\t\t\t500000\t\t\t\t\tCK\t\t\t\tMOMOA\t\t\t\tThành công\t\t\t\t\t\t05-08-2026 13:00:00\tMM1\t\n"
	. "5\t2\tT2\t\t\t900000\t\t\t\t\tCK\t\t\t\tMOMOA\t\t\t\tHuỷ\t\t\t\t\t\t05-08-2026 14:00:00\tMM2\t\n";

// -------------------------------------------------------------- bốn bước dán
$buoc = array(
	array( $qr,    'khtc_nap_sao_ke', 3 ),   // 3 dòng, một mang mã lạ
	array( $payoo, 'khtc_nap_cong',   1 ),
	array( $vnpay, 'khtc_nap_cong',   1 ),
	array( $momo,  'khtc_nap_cong',   1 ),   // dòng "Huỷ" bị loại
);
foreach ( $buoc as $i => $b ) {
	$_POST = array( 'tho' => $b[0], '_wpnonce' => 'test', $b[1] => 1, 'nh' => $nh, 'dot' => -1, 'nh_dot' => $nh, 'ten_dot' => 'Đợt ' . ( $i + 1 ) );
	$h = man();
	kiem( sprintf( 'bước %d nạp đúng %d dòng', $i + 1, $b[2] ), (bool) preg_match( '/Đã nạp ' . $b[2] . ' dòng/u', $h ), true );
	kiem( sprintf( 'bước %d có lối đi tiếp', $i + 1 ), false !== strpos( $h, 'Xong bước 1' ), true );
}
$_POST = array();
kiem( 'sao kê vào 3 dòng', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ngan_hang_id=%d', $nh ) ), 3 );
kiem( 'ba đợt cổng được tạo', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), 3 );
kiem( 'dòng MoMo "Huỷ" không vào sổ', (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ds_dong' ) ), 3 );

// ------------------------------------------------------------ xem trước
$dots = array_map( fn( $d ) => (int) $d->id, KHTC_DoiSoat::ds_dot( 'kh_cu' ) );
$ky   = array( 'tu' => '2026-08-05', 'den' => '2026-08-05', 'nh' => array( $nh ), 'dot' => $dots, 'tach' => 'ngay' );
$g    = KHTC_SinhHD::gom( $ky );
kiem( 'gom ra 2 điểm', count( $g['diem'] ), 2 );
// Gian Một gom cả bốn nguồn: QR 100k + Payoo 300k + VNPay 400k + MoMo 500k.
$theo = array();
foreach ( $g['diem'] as $x ) { $theo[ $x['ten_diem'] ] = $x; }
kiem( 'MỘT tờ gom đủ bốn nguồn', $theo['Gian Một']['tien'], 1300000 );
kiem( 'và đếm đủ bốn dòng', $theo['Gian Một']['so_dong'], 4 );
kiem( 'Gian Hai chỉ có QR', $theo['Gian Hai']['tien'], 200000 );
kiem( 'mã lạ được tách ra', $g['tong_la'], 700000 );
kiem( 'và liệt kê đúng mã', isset( $g['la']['LA_QR'] ), true );

// KHÔNG ĐỒNG NÀO BIẾN MẤT giữa bốn bước.
$tt = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'giao_dich' ) . " WHERE ngan_hang_id=%d AND loai='thu'", $nh ) );
$tc = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'ds_dong' ) );
kiem( 'tiền vào sổ đúng như đã dán', $tt + $tc, 2200000 );
kiem( 'vào hoá đơn + bỏ qua + mã lạ = tiền vào sổ', $g['tong'] + $g['tong_bo'] + $g['tong_la'], $tt + $tc );

// -------------------------------------------------------------- tạo hoá đơn
$r = KHTC_SinhHD::tao( $ky + array( 'ngay_hd' => '05/08/2026', 'bat_dau' => 500, 'thue_suat' => '8' ) );
kiem( 'tạo được', is_wp_error( $r ), false );
kiem( 'đúng 2 hoá đơn', $r['tao'], 2 );
kiem( 'số liên tiếp 500–501', $r['so_cuoi'], 501 );
$l = KHTC_HoaDonRa::loc( array( 'tu' => '2026-08-05', 'den' => '2026-08-05' ) );
kiem( 'tổng có VAT bằng đúng tiền gom', $l['co_vat'], $g['tong'] );
kiem( 'chưa VAT + VAT = có VAT', $l['chua_vat'] + $l['vat'], $l['co_vat'] );
// Số cấp theo thứ tự tên điểm, nên tra theo TÊN chứ không đoán tờ nào số nào.
$hd = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KHTC_DB::bang( 'hd_ra' ) . ' WHERE ma_diem=%s', 'Gian Một' ) );
kiem( 'hoá đơn mang mã Misa', $hd->ma_misa, 'M1' );
kiem( 'mang khu vực', $hd->khu_vuc, 'HCM' );
kiem( 'mang dịch vụ', $hd->dich_vu, 'KVC' );
kiem( 'và mang đúng tiền gom bốn nguồn', (int) $hd->co_vat, 1300000 );
// Ghi chú phải truy lại được ngày doanh thu.
kiem( 'ghi chú nói ngày doanh thu', false !== strpos( $hd->ghi_chu, '05/08/2026' ), true );

// ------------------------------- làm lại cả bốn bước: không được nhân đôi gì
// Mỗi file dán lại vào đúng đợt của kênh nó.
$dot_kenh = array();
foreach ( KHTC_DoiSoat::ds_dot( 'kh_cu' ) as $d ) { $dot_kenh[ $d->kenh ] = (int) $d->id; }
foreach ( $buoc as $i => $b ) {
	$kenh = KHTC_DanTho::nhan_dang( $b[0] )[0];
	$_POST = array( 'tho' => $b[0], '_wpnonce' => 'test', $b[1] => 1, 'nh' => $nh, 'dot' => $dot_kenh[ $kenh ] ?? -1, 'nh_dot' => $nh );
	man();
}
$_POST = array();
// Dán file Payoo vào đợt VNPay (chọn nhầm) → từ chối, không ghi gì.
$_POST = array( 'tho' => $payoo, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => $dot_kenh['vnpay'], 'nh_dot' => $nh );
$h_nham = man();
$_POST = array();
kiem( 'nạp Payoo vào đợt VNPay bị từ chối', false !== strpos( $h_nham, 'là đợt VNPay' ), true );
kiem( 'và không ghi dòng nào vào đợt đó', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE dot_id=%d', $dot_kenh['vnpay'] ) ), 1 );
$tt2 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'giao_dich' ) . " WHERE ngan_hang_id=%d AND loai='thu'", $nh ) );
kiem( 'dán lại cả bốn tệp: sao kê không nhân đôi', $tt2, $tt );
kiem( 'và số hoá đơn trong sổ không đổi', KHTC_HoaDonRa::loc( array( 'tu' => '2026-08-05', 'den' => '2026-08-05' ) )['so_hd'], 2 );
$tc2 = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'ds_dong' ) );
kiem( 'dòng cổng cũng không nhân đôi', $tc2, $tc );

// ------------------- nạp lại file cổng mà chọn "tạo đợt mới": không ra đợt thứ hai
// Lỗi thật 24/09/2026: cùng một file Payoo nạp bốn lần → bốn đợt y nhau; tick
// cả bốn là tiền nhân bốn.
$so_dot_truoc = count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) );
$_POST = array( 'tho' => $payoo, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => -1, 'nh_dot' => $nh, 'ten_dot' => 'Đợt lặp' );
$h = man();
$_POST = array();
kiem( 'không tạo thêm đợt', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), $so_dot_truoc );
kiem( 'nói thẳng là file đã nạp rồi', false !== strpos( $h, 'File này đã nạp rồi' ), true );
kiem( 'và kể tên đợt đang giữ', false !== strpos( $h, '“Đợt 2”' ), true );
kiem( 'vẫn có lối đi tiếp sang đợt đó', false !== strpos( $h, 'Xong bước 1' ), true );
kiem( 'tiền cổng không đổi', (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'ds_dong' ) ), $tc );

// Nạp file có một nửa mới, một nửa đã có ở đợt khác → đợt mới chỉ giữ nửa mới, báo rõ nửa kia ở đâu
$payoo_nua = $payoo . "2\tPAYA\t06/08/2026\t\tThanh toán\tBán hàng\tQR\tTK\t\t\t\t\tQ2\tPAY2\t\t\t\t\t\tD1\t1\t150000\t825\t149175\n";
$_POST = array( 'tho' => $payoo_nua, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => -1, 'nh_dot' => $nh, 'ten_dot' => 'Đợt nửa' );
$h = man();
$_POST = array();
kiem( 'nửa mới → có đợt mới', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), $so_dot_truoc + 1 );
kiem( 'báo nạp 1 dòng, bỏ 1 trùng', (bool) preg_match( '/Đã nạp 1 dòng.*Bỏ qua 1 dòng trùng mã/u', $h ), true );
kiem( 'và chỉ ra dòng trùng đang ở đợt nào', false !== strpos( $h, 'đang ở đợt “Đợt 2”' ), true );

// ------------------- hai đợt đã tick cùng giữ một mã (đợt nạp đôi từ bản cũ): chỉ tính một lần
$dot_doi = KHTC_DoiSoat::tao_dot( array( 'ten' => 'Đợt đôi', 'kenh' => 'payoo', 'tu' => '2026-08-05', 'den' => '2026-08-05', 'ngan_hang_id' => $nh ) );
$wpdb->insert( KHTC_DB::bang( 'ds_dong' ), array( 'dot_id' => $dot_doi, 'ngay' => '2026-08-05', 'ma_gd' => 'PAY1', 'so_tien' => 300000, 'phi' => 1650, 'dien_giai' => 'PAYA', 'ma_cua_hang' => 'PAYA' ) );
$g_doi = KHTC_SinhHD::gom( array( 'tu' => '2026-08-05', 'den' => '2026-08-05', 'nh' => array(), 'dot' => array_merge( $dots, array( $dot_doi ) ), 'tach' => 'ngay' ) );
kiem( 'mã trùng giữa hai đợt chỉ tính một lần', $g_doi['trung'], array( 'tien' => 300000, 'dong' => 1 ) );
kiem( 'nên không đề xuất thêm tờ nào (dòng gốc đã vào hoá đơn)', $g_doi['diem'], array() );
$_GET = array( 'tu' => '2026-08-05', 'den' => '2026-08-05', 'dot' => array_merge( $dots, array( $dot_doi ) ) ); $_REQUEST = $_GET; $_POST = array();
ob_start(); KHTC_Trang::sinh_hoa_don(); $h = ob_get_clean();
kiem( 'màn hình bảo xoá đợt thừa', false !== strpos( $h, 'cùng một file cổng nạp thành hai đợt' ), true );
$_GET = array(); $_REQUEST = array();
kiem( 'xoá được đợt đôi (chưa vào hoá đơn)', KHTC_DoiSoat::xoa_dot( $dot_doi ), true );

// ------------------- đợt THÁNG: file mỗi ngày dồn vào một đợt "Kênh KH989 tháng m/Y"
$so_dot_0 = count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) );
$payoo_d1 = str_replace( array( '05/08/2026', 'PAY1', 'Q1' ), array( '03/09/2026', 'PAY91', 'Q91' ), $payoo );
$_POST = array( 'tho' => $payoo_d1, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => -2, 'nh_dot' => $nh );
$h = man(); $_POST = array();
kiem( 'đợt tháng: tạo đúng một đợt', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), $so_dot_0 + 1 );
kiem( 'tên đợt theo kênh + pháp nhân + tháng', false !== strpos( $h, 'Payoo KH989 tháng 9/2026' ), true );
$dt = null; foreach ( KHTC_DoiSoat::ds_dot( 'kh_cu' ) as $d ) { if ( 'Payoo KH989 tháng 9/2026' === $d->ten ) { $dt = $d; } }
kiem( 'kỳ của đợt tháng ôm cả tháng', array( $dt->tu, $dt->den ), array( '2026-09-01', '2026-09-30' ) );
// ngày hôm sau, nạp tiếp → cùng đợt, không thêm đợt
$payoo_d2 = str_replace( array( '05/08/2026', 'PAY1', 'Q1' ), array( '04/09/2026', 'PAY92', 'Q92' ), $payoo );
$_POST = array( 'tho' => $payoo_d2, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => -2, 'nh_dot' => $nh );
$h = man(); $_POST = array();
kiem( 'nạp ngày kế: vẫn một đợt', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), $so_dot_0 + 1 );
kiem( 'đợt tháng có 2 dòng', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE dot_id=%d', $dt->id ) ), 2 );
kiem( 'báo đúng tên đợt đã vào', false !== strpos( $h, 'dòng vào “Payoo KH989 tháng 9/2026”' ), true );
// nạp lại file hôm qua → không thêm gì, báo đã có
$_POST = array( 'tho' => $payoo_d1, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => -2, 'nh_dot' => $nh );
$h = man(); $_POST = array();
kiem( 'nạp lại: không đợt mới', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), $so_dot_0 + 1 );
kiem( 'nạp lại: vẫn 2 dòng', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE dot_id=%d', $dt->id ) ), 2 );
kiem( 'nạp lại: nói thẳng đã nạp rồi', false !== strpos( $h, 'File này đã nạp rồi' ), true );
// file trải hai tháng → hai đợt, mỗi tháng một
$payoo_2thang = $payoo_d1 . str_replace( array( '05/08/2026', 'PAY1', 'Q1' ), array( '30/10/2026', 'PAY93', 'Q93' ), explode( "\n", $payoo )[2] ) . "\n";
$_POST = array( 'tho' => $payoo_2thang, '_wpnonce' => 'test', 'khtc_nap_cong' => 1, 'nh' => $nh, 'dot' => -2, 'nh_dot' => $nh );
$h = man(); $_POST = array();
kiem( 'hai tháng → thêm đúng một đợt tháng 10', count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) ), $so_dot_0 + 2 );
kiem( 'và có tên tháng 10', false !== strpos( $h, 'Payoo KH989 tháng 10/2026' ), true );
kiem( 'dòng tháng 9 trong file đó bị bỏ vì đã có', false !== strpos( $h, 'Bỏ qua 1 dòng trùng mã' ), true );
// form mặc định chọn đợt tháng và nói trước tên
$_POST = array( 'tho' => $payoo_d2, '_wpnonce' => 'test' );
$h = man(); $_POST = array();
kiem( 'form: mặc định đợt tháng', false !== strpos( $h, 'value="-2" selected>Đợt tháng (tự xếp): Payoo KH989 tháng 9/2026' ), true );
kiem( 'form: không liệt kê đợt khác kênh', false !== strpos( $h, '>Đợt 3<' ), false );

// ------------------- nạp MỘT LƯỢT nhiều tệp, hai pháp nhân lẫn nhau
// QR có "Tài khoản nhận" → tài khoản bên nào thì vào bên đó; tệp cổng theo mã cửa hàng; đơn app cho cả hai.
KHTC_Cty::chon( 'kh_moi' );
KHTC_Diem::them( array( 'ma_cua_hang' => 'MOI1', 'ten_diem' => 'Điểm bên Mới', 'ma_misa' => 'MM', 'khu_vuc' => 'HN', 'dich_vu' => 'ghế' ) );
$nh_m = KHTC_NganHang::them( array( 'ten' => 'BIDV 8690077021', 'so_tk' => '8690077021', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
KHTC_Cty::chon( 'kh_cu' );
$nh_c = KHTC_NganHang::them( array( 'ten' => 'MB 11521268', 'so_tk' => '11521268', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
$qr_cu  = "STT\tThời gian TT\tSố tiền đến (VND)\tSố tiền đi (VND)\tLoại\tTrạng thái\tMã tham chiếu\tMã đơn hàng\tMã điểm bán\tMã cửa hàng\tTài khoản nhận\tThời gian tạo\tNội dung TT\n"
	. "1\t10-09-2026 09:00:00\t120000\t0\tGiao dịch đến\tThành công\tFTLO1\tX\tVVB\tQRA\t11521268 - MB\t10-09-2026\tQR\n";
$qr_moi = str_replace( array( 'FTLO1', '11521268 - MB', 'QRA' ), array( 'FTLO2', '8690077021 - BIDV', 'MOI1' ), $qr_cu );
$qr_la  = str_replace( array( 'FTLO1', '11521268 - MB' ), array( 'FTLO3', '99999999 - ACB' ), $qr_cu );
$payoo_moi = str_replace( array( 'PAYA', 'PAY1', '05/08/2026' ), array( 'MOI1', 'PAYLO1', '10/09/2026' ), $payoo );
$lo = KHTC_NapLo::nap( array(
	array( 'ten' => 'qr-cu.xlsx',  'trang' => array( array( 'ten' => 'S', 'van_ban' => $qr_cu,  'so_dong' => 2 ) ) ),
	array( 'ten' => 'qr-moi.xlsx', 'trang' => array( array( 'ten' => 'S', 'van_ban' => $qr_moi, 'so_dong' => 2 ) ) ),
	array( 'ten' => 'qr-la.xlsx',  'trang' => array( array( 'ten' => 'S', 'van_ban' => $qr_la,  'so_dong' => 2 ) ) ),
	array( 'ten' => 'payoo-moi.xlsx', 'trang' => array( array( 'ten' => 'S', 'van_ban' => $payoo_moi, 'so_dong' => 3 ) ) ),
	array( 'ten' => 'don.xls', 'trang' => array( array( 'ten' => 'S', 'van_ban' => "Mã đơn hàng\tNgày đặt hàng\tTrạng thái đơn hàng\tTrạng thái thanh toán\tTổng tiền phải trả\tTên sản phẩm\n#5551\t10/09/2026\tĐã giao\tĐã thanh toán\t100000\tCƠ SỞ A\n", 'so_dong' => 2 ) ) ),
	array( 'ten' => 'rac.xlsx', 'trang' => array( array( 'ten' => 'S', 'van_ban' => "a\tb\n1\t2\n", 'so_dong' => 2 ) ) ),
) );
$kq_lo = array(); foreach ( $lo['ket_qua'] as $x ) { $kq_lo[ $x['ten'] ] = $x; }
kiem( 'lô: QR tài khoản KH Cũ vào KH Cũ', array( $kq_lo['qr-cu.xlsx']['cty'], $kq_lo['qr-cu.xlsx']['them'] ), array( 'kh_cu', 1 ) );
kiem( 'lô: QR tài khoản KH Mới vào KH Mới dù đang đứng ở KH Cũ', array( $kq_lo['qr-moi.xlsx']['cty'], $kq_lo['qr-moi.xlsx']['them'] ), array( 'kh_moi', 1 ) );
kiem( 'lô: QR tài khoản chưa khai → bỏ, nói rõ', $kq_lo['qr-la.xlsx']['bo'] && false !== strpos( $kq_lo['qr-la.xlsx']['ghi_chu'], '99999999' ), true );
kiem( 'lô: Payoo mã bên Mới → đợt tháng bên Mới', array( $kq_lo['payoo-moi.xlsx']['cty'], $kq_lo['payoo-moi.xlsx']['them'] ), array( 'kh_moi', 1 ) );
kiem( 'lô: tên đợt tháng đúng pháp nhân', false !== strpos( $kq_lo['payoo-moi.xlsx']['ghi_chu'], 'Payoo KH705 tháng 9/2026' ), true );
kiem( 'lô: đơn app lưu cho cả hai', array( $kq_lo['don.xls']['cty'], KHTC_DonApp::dem( 'kh_cu' ) > 0, KHTC_DonApp::dem( 'kh_moi' ) > 0 ), array( 'cả hai', true, true ) );
kiem( 'lô: tệp rác bị bỏ', $kq_lo['rac.xlsx']['bo'], true );
kiem( 'lô: đứng lại ở pháp nhân ban đầu', KHTC_Cty::dang_chon(), 'kh_cu' );
kiem( 'lô: kỳ để đi tiếp có cả hai bên', array_keys( $lo['cty_da_nap'] ), array( 'kh_cu', 'kh_moi' ) );
kiem( 'lô: bên Mới mang tài khoản và đợt vừa nạp', array( count( $lo['cty_da_nap']['kh_moi']['nh'] ), count( $lo['cty_da_nap']['kh_moi']['dot'] ) ), array( 1, 1 ) );
// nạp lại y bộ → 0 dòng mới
$lo2 = KHTC_NapLo::nap( array(
	array( 'ten' => 'qr-cu.xlsx',  'trang' => array( array( 'ten' => 'S', 'van_ban' => $qr_cu,  'so_dong' => 2 ) ) ),
	array( 'ten' => 'payoo-moi.xlsx', 'trang' => array( array( 'ten' => 'S', 'van_ban' => $payoo_moi, 'so_dong' => 3 ) ) ),
) );
kiem( 'nạp lại cả bộ: 0 dòng mới, báo trùng', array( $lo2['ket_qua'][0]['them'], $lo2['ket_qua'][0]['trung'], $lo2['ket_qua'][1]['them'], $lo2['ket_qua'][1]['trung'] ), array( 0, 1, 0, 1 ) );

// ------------------- gộp đợt lẻ theo ngày về đợt tháng
// Dựng lại hình dạng bản cũ: ba đợt "Payoo 23/09/2026" y nhau + một đợt VNPay lẻ.
$le = array();
for ( $i = 0; $i < 3; $i++ ) {
	$id = KHTC_DoiSoat::tao_dot( array( 'ten' => 'Payoo 23/09/2026', 'kenh' => 'payoo', 'tu' => '2026-09-23', 'den' => '2026-09-23', 'ngan_hang_id' => $nh ) );
	$wpdb->insert( KHTC_DB::bang( 'ds_dong' ), array( 'dot_id' => $id, 'ngay' => '2026-09-23', 'ma_gd' => 'PAYLE1', 'so_tien' => 100000, 'phi' => 0, 'dien_giai' => 'x', 'ma_cua_hang' => 'PAYA' ) );
	$wpdb->insert( KHTC_DB::bang( 'ds_dong' ), array( 'dot_id' => $id, 'ngay' => '2026-09-23', 'ma_gd' => 'PAYLE2', 'so_tien' => 50000, 'phi' => 0, 'dien_giai' => 'x', 'ma_cua_hang' => 'PAYA' ) );
	$le[] = $id;
}
$vn_le = KHTC_DoiSoat::tao_dot( array( 'ten' => 'VNPay 23/09/2026', 'kenh' => 'vnpay', 'tu' => '2026-09-23', 'den' => '2026-09-23', 'ngan_hang_id' => $nh ) );
$wpdb->insert( KHTC_DB::bang( 'ds_dong' ), array( 'dot_id' => $vn_le, 'ngay' => '2026-09-23', 'ma_gd' => 'VNLE1', 'so_tien' => 70000, 'phi' => 0, 'dien_giai' => 'x', 'ma_cua_hang' => 'VNPA' ) );
$tien_truoc = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' d JOIN ' . KHTC_DB::bang( 'doi_soat' ) . " o ON o.id=d.dot_id WHERE o.cty='kh_cu'" );
$so_dot_truoc = count( KHTC_DoiSoat::ds_dot( 'kh_cu' ) );
$gop = KHTC_DoiSoat::gop_ve_thang( 'kh_cu' );
kiem( 'gộp: bỏ 4 dòng trùng của hai đợt đôi', $gop['trung'], 4 );
kiem( 'gộp: xoá hết đợt lẻ rỗng (4 đợt 23/09 + các đợt thử tháng 8)', $gop['xoa'] >= 4, true );
$con_le = 0; foreach ( KHTC_DoiSoat::ds_dot( 'kh_cu' ) as $d ) { if ( 'khac' !== $d->kenh && ! preg_match( '/ KH\d+ tháng \d+\/\d{4}$/u', $d->ten ) ) { $con_le++; } }
kiem( 'gộp: mọi đợt còn lại đều là đợt tháng', $con_le, 0 );
kiem( 'gộp: có đợt tháng Payoo và VNPay', in_array( 'Payoo KH989 tháng 9/2026', $gop['dich'], true ) && in_array( 'VNPay KH989 tháng 9/2026', $gop['dich'], true ), true );
kiem( 'không còn đợt lẻ theo ngày', (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'doi_soat' ) . " WHERE ten LIKE '%23/09/2026'" ), 0 );
$tien_sau = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' d JOIN ' . KHTC_DB::bang( 'doi_soat' ) . " o ON o.id=d.dot_id WHERE o.cty='kh_cu'" );
kiem( 'tiền sau gộp = tiền trước trừ đúng phần trùng (2 đợt đôi × 150k)', $tien_sau, $tien_truoc - 300000 );
$dt9 = null; foreach ( KHTC_DoiSoat::ds_dot( 'kh_cu' ) as $d ) { if ( 'Payoo KH989 tháng 9/2026' === $d->ten ) { $dt9 = $d; } }
kiem( 'đợt tháng 9 Payoo có đủ dòng: 2 cũ + 2 dồn', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE dot_id=%d', $dt9->id ) ), 4 );
kiem( 'gộp lần hai: không có gì để làm', KHTC_DoiSoat::gop_ve_thang( 'kh_cu' ), array( 'don' => 0, 'trung' => 0, 'xoa' => 0, 'dich' => array() ) );
$_POST = array( 'khtc_gop_thang' => 1 ); $_GET = array(); $_REQUEST = array();
ob_start(); KHTC_Trang::doi_soat(); $h = ob_get_clean(); $_POST = array();
kiem( 'màn hình có nút gộp và báo kết quả', false !== strpos( $h, 'Đã gộp về đợt tháng' ) && false !== strpos( $h, 'name="khtc_gop_thang"' ), true );

// ------------------- sao kê nạp nhầm TÀI KHOẢN: vẫn chặn, và nói đang ở tài khoản nào
$nh2 = KHTC_NganHang::them( array( 'ten' => 'BIDV 8660077020', 'so_tk' => '8660077020', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
$tt_truoc = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'giao_dich' ) );
$_POST = array( 'tho' => $qr, '_wpnonce' => 'test', 'khtc_nap_sao_ke' => 1, 'nh' => $nh2 );
$h = man(); $_POST = array();
kiem( 'sao kê cũ nạp vào tài khoản khác: không thêm đồng nào', (int) $wpdb->get_var( 'SELECT COALESCE(SUM(so_tien),0) FROM ' . KHTC_DB::bang( 'giao_dich' ) ), $tt_truoc );
kiem( 'nói thẳng file đã nạp rồi', false !== strpos( $h, 'File này đã nạp rồi' ), true );
kiem( 'và chỉ tài khoản đang giữ', false !== strpos( $h, 'ở tài khoản “Vietcombank' ) || false !== strpos( $h, 'ở tài khoản “' ), true );
kiem( 'tài khoản mới vẫn trống', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ngan_hang_id=%d', $nh2 ) ), 0 );
// pháp nhân khác thì KHÔNG chặn (sổ tách bạch)
KHTC_Cty::chon( 'kh_moi' );
$nh_moi = KHTC_NganHang::them( array( 'ten' => 'MB bên kia', 'so_tk' => '02865168', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
$r_moi = KHTC_GiaoDich::dan_hang_loat( $nh_moi, "05/08/2026\tQR\t100.000\tthu\tFT1\tQRA\n" );
kiem( 'cùng mã ở pháp nhân khác vẫn vào (sổ tách bạch)', $r_moi['them'], 1 );
KHTC_Cty::chon( 'kh_cu' );

// ------------------- đợt đã vào hoá đơn thì không xoá được
$dot_co_hd = (int) $wpdb->get_var( 'SELECT dot_id FROM ' . KHTC_DB::bang( 'ds_dong' ) . ' WHERE hd_ra_id > 0 LIMIT 1' );
kiem( 'dòng đã vào hoá đơn vẫn giữ liên kết sau khi gộp', $dot_co_hd > 0, true );
$kq_xoa = KHTC_DoiSoat::xoa_dot( $dot_co_hd );
kiem( 'đợt có dòng trong hoá đơn: chặn xoá', is_wp_error( $kq_xoa ), true );
kiem( 'và đợt vẫn còn', KHTC_DoiSoat::mot_dot( $dot_co_hd ) !== null, true );

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
