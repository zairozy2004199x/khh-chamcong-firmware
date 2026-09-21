<?php
/**
 * Chạy thật bốn màn hình trên bộ giả lập và kiểm con số in ra.
 *
 *   php tools/kh-tai-chinh/tests/kiem-man-hinh.php
 *
 * Kịch bản dựng đúng cảnh dùng thật: một tài khoản có số dư đầu, một tháng sao
 * kê, một đợt đối soát Payoo có cả dòng khớp, dòng về trễ, dòng chưa về và dòng
 * ngân hàng cổng không báo.
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
	try {
		$ham();
	} catch ( Throwable $e ) {
		ob_end_clean();
		throw $e;
	}
	return ob_get_clean();
}

// ------------------------------------------------------------- dữ liệu
KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them(
	array( 'ten' => 'Vietcombank 0123', 'so_tk' => '0123456789', 'so_du_dau' => 100000000, 'ngay_dau' => '2026-08-01' )
);
kiem( 'thêm được tài khoản', is_int( $nh ) && $nh > 0, true );

// Sao kê: 3 dòng khớp thẳng, 1 dòng về trễ 2 ngày, 1 dòng cổng không báo, 1 chi.
$sao_ke = "05/08/2026\tPayoo QR A\t988.900\tThu\tPAY001\n"
	. "05/08/2026\tPayoo QR B\t494.450\tThu\tPAY002\n"
	. "05/08/2026\tPayoo the C\t197.780\tThu\n"
	. "07/08/2026\tPayoo QR D ve tre\t296.670\tThu\n"
	. "06/08/2026\tTien mat nop vao\t5.000.000\tThu\n"
	. "10/08/2026\tTra tien dien\t-2.400.000\tChi\n";
$kq = KHTC_GiaoDich::dan_hang_loat( $nh, $sao_ke );
kiem( 'nạp sao kê 6 dòng', $kq['them'], 6 );
kiem( 'nạp sao kê không lỗi dòng nào', $kq['loi'], array() );

// Số dư = 100.000.000 + (988.900+494.450+197.780+296.670+5.000.000) − 2.400.000
$mong_du = 100000000 + 988900 + 494450 + 197780 + 296670 + 5000000 - 2400000;
kiem( 'số dư tính đúng', KHTC_NganHang::so_du( KHTC_NganHang::mot( $nh ) ), $mong_du );

// Giao dịch TRƯỚC ngay_dau không được cộng lại vào số dư.
KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh, 'ngay' => '20/07/2026', 'dien_giai' => 'truoc ky', 'so_tien' => '9.000.000', 'loai' => 'thu' ) );
kiem( 'giao dịch trước ngày đầu không đội số dư', KHTC_NganHang::so_du( KHTC_NganHang::mot( $nh ) ), $mong_du );

// ------------------------------------------------------------- đối soát
$dot = KHTC_DoiSoat::tao_dot( array( 'kenh' => 'payoo', 'ngan_hang_id' => $nh, 'tu' => '01/08/2026', 'den' => '08/08/2026' ) );
kiem( 'tạo được đợt đối soát', is_int( $dot ) && $dot > 0, true );

// Bảng Payoo gửi về: 4 dòng gộp cả phí. Dòng E cổng báo mà ngân hàng chưa về.
$bang = "05/08/2026\tPAY001\t1.000.000\t11.100\tQR A\n"
	. "05/08/2026\tPAY002\t500.000\t5.550\tQR B\n"
	. "05/08/2026\tPAY003\t200.000\t2.220\tThe C\n"
	. "05/08/2026\tPAY004\t300.000\t3.330\tQR D ve tre\n"
	. "05/08/2026\tPAY005\t750.000\t8.325\tQR E chua ve\n";
$np = KHTC_DoiSoat::nap_dong( $dot, $bang );
kiem( 'nạp 5 dòng cổng', $np['them'], 5 );

// Dán lại y nguyên: phải bị chặn hết, nếu không tổng cổng gấp đôi.
$np2 = KHTC_DoiSoat::nap_dong( $dot, $bang );
kiem( 'dán lại lần hai bị chặn hết', array( $np2['them'], $np2['trung'] ), array( 0, 5 ) );

$r = KHTC_DoiSoat::chay( $dot );
kiem( 'khớp 4 dòng', count( $r['khop'] ), 4 );
kiem( 'thiếu 1 dòng (PAY005 chưa về)', count( $r['thieu'] ), 1 );
kiem( 'dòng thiếu đúng là PAY005', $r['thieu'][0]->ma_gd, 'PAY005' );
kiem( 'thừa 1 dòng (tiền mặt nộp vào)', count( $r['thua'] ), 1 );
kiem( 'dòng thừa đúng là tiền mặt', $r['thua'][0]->so_tien, 5000000 );
kiem( 'không có dòng lệch tiền', count( $r['lech'] ), 0 );
kiem( 'tổng cổng báo về', $r['tong_cong'], 2750000 );
kiem( 'tổng phí cổng', $r['tong_phi'], 30525 );
kiem( 'tiền thiếu', $r['tien_thieu'], 750000 );
kiem( 'tiền thừa', $r['tien_thua'], 5000000 );

// Hai dòng đầu mang mã GD nên phải ghép theo mã; hai dòng sau ghép nhờ trừ phí.
$kieu = array();
foreach ( $r['khop'] as $d ) { $kieu[ $d->ma_gd ] = $d->kieu_khop; }
kiem( 'PAY001 ghép theo mã', $kieu['PAY001'], 'khop_ma' );
kiem( 'PAY002 ghép theo mã', $kieu['PAY002'], 'khop_ma' );
kiem( 'PAY003 ghép nhờ trừ phí', $kieu['PAY003'], 'khop_tru_phi' );
kiem( 'PAY004 về trễ, vẫn ghép nhờ trừ phí', $kieu['PAY004'], 'khop_tru_phi' );

// Chạy lại không được nhân đôi kết quả.
$r2 = KHTC_DoiSoat::chay( $dot );
kiem( 'chạy lại cho cùng kết quả', array( count( $r2['khop'] ), count( $r2['thieu'] ), count( $r2['thua'] ) ), array( 4, 1, 1 ) );

// ---------------------------------------------------- dựng thật bốn trang
$GLOBALS['khtc_qv'] = array();  // đứng trong wp-admin
$_GET = array();
$_POST = array();

$t = dung( array( 'KHTC_Trang', 'tong_quan' ) );
co( 'tổng quan có tên tài khoản', $t, 'Vietcombank 0123' );
co( 'tổng quan hiện số dư đúng', $t, number_format( $mong_du, 0, ',', '.' ) . ' đ' );
co( 'tổng quan có bảng đối soát gần đây', $t, 'Đối soát gần đây' );

$t = dung( array( 'KHTC_Trang', 'ngan_hang' ) );
co( 'trang ngân hàng có form thêm', $t, 'Thêm tài khoản' );
kiem( 'trang ngân hàng đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

$t = dung( array( 'KHTC_Trang', 'giao_dich' ) );
co( 'trang giao dịch có ô lọc', $t, 'Tìm diễn giải' );
co( 'trang giao dịch liệt kê dòng chi', $t, 'Tra tien dien' );
co( 'trang giao dịch có ô dán sao kê', $t, 'Dán sao kê hàng loạt' );
co( 'trong wp-admin, nút Bỏ lọc trỏ về admin.php', $t, 'wp-admin/admin.php?page=khtc-giao-dich' );

$_GET = array( 'dot' => $dot );
$t = dung( array( 'KHTC_Trang', 'doi_soat' ) );
co( 'trang đối soát có nhóm Thiếu', $t, 'cổng báo có, ngân hàng chưa về' );
co( 'trang đối soát có nhóm Thừa', $t, 'ngân hàng có, cổng không báo' );
co( 'trang đối soát nêu đúng mã còn thiếu', $t, 'PAY005' );
co( 'trang đối soát có nút tải CSV', $t, 'Tải CSV kết quả' );
kiem( 'trang đối soát không để lọt thẻ chưa đóng', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );
kiem( 'trang đối soát đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

// --------------------------------------------- địa chỉ đổi theo nơi đang đứng
kiem( 'trong wp-admin dùng admin.php', KHTC_Trang::url( 'doi-soat' ), 'https://vi.du/wp-admin/admin.php?page=khtc-doi-soat' );
$GLOBALS['khtc_qv'] = array( 'khtc_man' => 'doi-soat' );
update_option( 'permalink_structure', '/%postname%/' );
kiem( 'ngoài web dùng đường dẫn đẹp', KHTC_Trang::url( 'doi-soat' ), 'https://vi.du/tai-chinh/doi-soat/' );
kiem( 'ngoài web, tổng quan là gốc', KHTC_Trang::url( '' ), 'https://vi.du/tai-chinh/' );
update_option( 'permalink_structure', '' );
kiem( 'permalink thường thì rơi về ?khtc_man=', KHTC_Trang::url( 'doi-soat' ), 'https://vi.du/?khtc_man=doi-soat' );

// Trang dựng ngoài web phải ra cùng số với trong wp-admin.
$_GET = array();
$GLOBALS['khtc_qv'] = array( 'khtc_man' => 'tong-quan' );
$t = dung( array( 'KHTC_Trang', 'tong_quan' ) );
co( 'ngoài web vẫn ra đúng số dư', $t, number_format( $mong_du, 0, ',', '.' ) . ' đ' );
co( 'ngoài web liên kết trỏ về /tai-chinh/', $t, 'khtc_man=doi-soat' );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
