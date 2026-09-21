<?php
/**
 * Kiểm hoá đơn đầu vào và tờ khai GTGT.
 *
 *   php tools/kh-tai-chinh/tests/kiem-hoa-don-vao.php
 *
 * Con số quan trọng nhất là VAT PHẢI NỘP. Nó là hiệu của hai màn hình hoá đơn,
 * nên phải kiểm rằng nó thật sự lấy từ hai bên chứ không cộng nhầm, và rằng
 * phần không được khấu trừ KHÔNG bị trừ vào.
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
function khong_co( $ten, $html, $chuoi ) {
	global $dat, $hong;
	if ( false === strpos( $html, $chuoi ) ) { $dat++; return; }
	$hong[] = sprintf( '%s: KHÔNG được có "%s" trong trang', $ten, $chuoi );
}
function dung( $ham ) {
	ob_start();
	try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; }
	return ob_get_clean();
}

KHTC_Cty::chon( 'kh_cu' );
$ky = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );

// ------------------------------------------------- phép tính dùng chung
// Dùng lại đúng KHTC_HoaDonRa::tinh(), nên phải ra đúng như bên đầu ra.
$id = KHTC_HoaDonVao::them( array( 'ngay' => '05/08/2026', 'so_hd' => '00012345', 'nha_cung_cap' => 'EVN HCMC', 'mst' => '0300942001', 'noi_dung' => 'Tien dien T7', 'chua_vat' => '2.400.000', 'thue_suat' => '8' ) );
kiem( 'ghi được hoá đơn vào', is_int( $id ) && $id > 0, true );
$l = KHTC_HoaDonVao::loc( $ky );
kiem( 'VAT tính đúng như bên đầu ra', $l['vat'], 192000 );
kiem( 'có VAT bằng tổng', $l['co_vat'], 2592000 );
kiem( 'chưa VAT + VAT = có VAT', $l['chua_vat'] + $l['vat'], $l['co_vat'] );

// ------------------------------------------------------------- chặn trùng
kiem( 'trùng số HĐ cùng MST bị chặn', is_wp_error( KHTC_HoaDonVao::them( array( 'ngay' => '06/08/2026', 'so_hd' => '00012345', 'mst' => '0300942001', 'co_vat' => '100.000' ) ) ), true );
// Số hoá đơn do bên bán đánh — hai nhà cung cấp cùng số là bình thường.
kiem( 'cùng số HĐ nhưng khác MST thì cho qua', is_int( KHTC_HoaDonVao::them( array( 'ngay' => '06/08/2026', 'so_hd' => '00012345', 'nha_cung_cap' => 'SAWACO', 'mst' => '0300476888', 'noi_dung' => 'Tien nuoc T7', 'chua_vat' => '800.000', 'thue_suat' => '5' ) ) ), true );
kiem( 'thiếu số HĐ bị chặn', is_wp_error( KHTC_HoaDonVao::them( array( 'ngay' => '06/08/2026', 'chua_vat' => '100.000' ) ) ), true );
kiem( 'không có tiền bị chặn', is_wp_error( KHTC_HoaDonVao::them( array( 'ngay' => '06/08/2026', 'so_hd' => 'X9' ) ) ), true );
kiem( 'ngày sai bị chặn', is_wp_error( KHTC_HoaDonVao::them( array( 'ngay' => 'hom qua', 'so_hd' => 'X8', 'co_vat' => '100.000' ) ) ), true );

// ----------------------------------------------------- ngưỡng tiền mặt
$nguong = KHTC_HoaDonVao::NGUONG_TIEN_MAT;
kiem( 'dưới ngưỡng, tiền mặt vẫn khấu trừ', KHTC_HoaDonVao::mac_dinh_khau_tru( $nguong - 1, 'tien_mat' )[0], true );
kiem( 'đúng ngưỡng, tiền mặt thì không', KHTC_HoaDonVao::mac_dinh_khau_tru( $nguong, 'tien_mat' )[0], false );
kiem( 'trên ngưỡng, tiền mặt thì không', KHTC_HoaDonVao::mac_dinh_khau_tru( $nguong + 1, 'tien_mat' )[0], false );
kiem( 'trên ngưỡng nhưng chuyển khoản thì vẫn khấu trừ', KHTC_HoaDonVao::mac_dinh_khau_tru( $nguong * 10, 'chuyen_khoan' )[0], true );
kiem( 'bị chặn thì có nêu lý do', '' !== KHTC_HoaDonVao::mac_dinh_khau_tru( $nguong, 'tien_mat' )[1], true );
kiem( 'được khấu trừ thì lý do rỗng', KHTC_HoaDonVao::mac_dinh_khau_tru( 1000, 'tien_mat' )[1], '' );

$tm = KHTC_HoaDonVao::them( array( 'ngay' => '10/08/2026', 'so_hd' => 'TM001', 'nha_cung_cap' => 'Cua hang VLXD', 'mst' => '0312345678', 'noi_dung' => 'Vat tu sua chua', 'chua_vat' => '25.000.000', 'thue_suat' => '8', 'hinh_thuc' => 'tien_mat' ) );
$l = KHTC_HoaDonVao::loc( $ky );
kiem( 'hoá đơn tiền mặt lớn không vào phần khấu trừ', $l['vat_kt'], 192000 + 40000 );
kiem( 'và nằm ở phần không khấu trừ', $l['vat_khong'], 2000000 );
kiem( 'tổng VAT vẫn là tổng của cả hai', $l['vat'], $l['vat_kt'] + $l['vat_khong'] );

// Kế toán bật lại được — đây là nhắc, không phải phán quyết.
kiem( 'bật lại khấu trừ được', KHTC_HoaDonVao::dat_khau_tru( $tm, true ), true );
kiem( 'bật xong thì vào phần khấu trừ', KHTC_HoaDonVao::loc( $ky )['vat_kt'], 2232000 );
kiem( 'tắt lại cũng được', KHTC_HoaDonVao::dat_khau_tru( $tm, false, 'Tra tien mat tren 20 trieu' ), true );
kiem( 'tắt xong quay lại như cũ', KHTC_HoaDonVao::loc( $ky )['vat_kt'], 232000 );
kiem( 'đổi khấu trừ có vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'sua' ) )['so_dong'], 2 );

// ----------------------------------------------------------------- lọc
kiem( 'lọc riêng phần không khấu trừ', KHTC_HoaDonVao::loc( $ky + array( 'khau_tru' => '0' ) )['so_hd'], 1 );
kiem( 'lọc riêng phần được khấu trừ', KHTC_HoaDonVao::loc( $ky + array( 'khau_tru' => '1' ) )['so_hd'], 2 );
kiem( 'lọc theo thuế suất', KHTC_HoaDonVao::loc( $ky + array( 'thue_suat' => '5' ) )['chua_vat'], 800000 );
kiem( 'tìm theo MST', KHTC_HoaDonVao::loc( $ky + array( 'tim' => '0300942001' ) )['so_hd'], 1 );
kiem( 'tìm theo tên nhà cung cấp', KHTC_HoaDonVao::loc( $ky + array( 'tim' => 'SAWACO' ) )['so_hd'], 1 );

$g = KHTC_HoaDonVao::gom_theo( 'thue_suat', $ky );
$theo = array();
foreach ( $g as $r ) { $theo[ $r->nhan ] = array( (int) $r->vat, (int) $r->vat_kt ); }
kiem( 'gom bậc 8%: tổng VAT và phần khấu trừ khác nhau', $theo['8'], array( 2192000, 192000 ) );
kiem( 'gom bậc 5%', $theo['5'], array( 40000, 40000 ) );
kiem( 'gom theo nhà cung cấp ra 3 nhóm', count( KHTC_HoaDonVao::gom_theo( 'nha_cung_cap', $ky ) ), 3 );
kiem( 'cột lạ bị từ chối gom', KHTC_HoaDonVao::gom_theo( 'mst', $ky ), array() );

// --------------------------------------------------------------- dán bảng
$b = "Ngày HĐ\tSố HĐ\tNhà cung cấp\tMST\tNội dung\tChưa VAT\tVAT\tCó VAT\tHình thức\n"
	. "15/08/2026\t00099001\tCTY VAN PHONG PHAM\t0301111222\tGiay in muc\t1.000.000\t100.000\t1.100.000\tChuyen khoan\n"
	. "16/08/2026\t00099002\tCTY VAN TAI ABC\t0302222333\tCuoc van chuyen\t3.000.000\t240.000\t3.240.000\tChuyen khoan\n";
$kq = KHTC_HoaDonVao::dan_hang_loat( $b );
kiem( 'dán 2 hoá đơn, bỏ dòng tiêu đề', $kq['them'], 2 );
kiem( 'dán không lỗi dòng nào', $kq['loi'], array() );
kiem( 'dán lại bị chặn hết', KHTC_HoaDonVao::dan_hang_loat( $b )['trung'], 2 );
$it = KHTC_HoaDonVao::dan_hang_loat( "20/08/2026\t00099003\tThieu cot\n" );
kiem( 'dòng thiếu cột bị báo lỗi', array( $it['them'], count( $it['loi'] ) ), array( 0, 1 ) );

// ---------------------------------------------------------- xuất khứ hồi
$hang = KHTC_HoaDonVao::hang_csv( KHTC_HoaDonVao::loc( $ky )['rows'] );
kiem( 'dòng đầu là tiêu đề', $hang[0], KHTC_HoaDonVao::cot() );
kiem( 'mọi dòng cùng số cột', count( array_unique( array_map( 'count', $hang ) ) ), 1 );
kiem( 'cột khấu trừ ghi rõ Có / Không', in_array( $hang[1][11], array( 'Có', 'Không' ), true ), true );

// ------------------------------------------------------------- tờ khai
// Đầu ra: 1 hoá đơn 100.000.000 chưa VAT, 8% → VAT ra 8.000.000
KHTC_HoaDonRa::them( array( 'ngay' => '20/08/2026', 'so_hd' => 'RA001', 'khach' => 'CONG TY ABC', 'chua_vat' => '100.000.000', 'thue_suat' => '8' ) );
$tk = KHTC_HoaDonVao::to_khai( '2026-08-01', '2026-08-31' );
kiem( 'tờ khai: VAT đầu ra', $tk['vat_ra'], 8000000 );
kiem( 'tờ khai: doanh thu đầu ra', $tk['dt_ra'], 100000000 );
// Đầu vào được khấu trừ: 192.000 + 40.000 + 100.000 + 240.000 = 572.000
kiem( 'tờ khai: VAT đầu vào được khấu trừ', $tk['vat_kt'], 572000 );
kiem( 'tờ khai: phần không khấu trừ tách riêng', $tk['vat_khong'], 2000000 );
kiem( 'tờ khai: phải nộp = ra − khấu trừ', $tk['phai_nop'], 8000000 - 572000 );
kiem( 'tờ khai: phần không khấu trừ KHÔNG bị trừ vào', $tk['phai_nop'], 7428000 );
kiem( 'tờ khai: không có gì chuyển kỳ sau', $tk['chuyen_ky'], 0 );

// Đầu vào lớn hơn đầu ra → chuyển kỳ sau, không phải nhà nước trả lại.
KHTC_HoaDonVao::them( array( 'ngay' => '25/08/2026', 'so_hd' => 'BIG001', 'nha_cung_cap' => 'CTY THIET BI', 'mst' => '0309999888', 'noi_dung' => 'Mua thiet bi', 'chua_vat' => '500.000.000', 'thue_suat' => '8' ) );
$tk2 = KHTC_HoaDonVao::to_khai( '2026-08-01', '2026-08-31' );
kiem( 'đầu vào lớn hơn: phải nộp về 0', $tk2['phai_nop'], 0 );
kiem( 'và phần dư thành chuyển kỳ sau', $tk2['chuyen_ky'], 40000000 - 8000000 + 572000 );
kiem( 'hai số không bao giờ cùng dương', min( $tk2['phai_nop'], $tk2['chuyen_ky'] ), 0 );

// Kỳ khác thì tờ khai về 0, không dính số kỳ này.
$tk3 = KHTC_HoaDonVao::to_khai( '2026-07-01', '2026-07-31' );
kiem( 'kỳ không có gì thì tờ khai bằng 0', array( $tk3['vat_ra'], $tk3['vat_kt'], $tk3['phai_nop'] ), array( 0, 0, 0 ) );

// -------------------------------------------------------------- khoá sổ
KHTC_Khoa::dat( '31/08/2026' );
kiem( 'không thêm được vào kỳ khoá', is_wp_error( KHTC_HoaDonVao::them( array( 'ngay' => '28/08/2026', 'so_hd' => 'LUI1', 'mst' => 'X', 'co_vat' => '100.000' ) ) ), true );
kiem( 'không xoá được trong kỳ khoá', is_wp_error( KHTC_HoaDonVao::xoa( $id ) ), true );
kiem( 'không đổi khấu trừ được trong kỳ khoá', is_wp_error( KHTC_HoaDonVao::dat_khau_tru( $id, false, 'thu' ) ), true );
KHTC_Khoa::dat( '' );
kiem( 'mở khoá rồi thì xoá được', KHTC_HoaDonVao::xoa( $id ), true );

// -------------------------------------------------------------- sao lưu
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu có bảng hoá đơn đầu vào', isset( $sl['bang']['hd_vao'] ), true );
kiem( 'mọi bảng đều được gom', array_keys( $sl['bang'] ), KHTC_SaoLuu::bang() );

// ------------------------------------------------------ dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = $ky;
$_POST = array();
$t = dung( array( 'KHTC_Trang', 'hoa_don_vao' ) );
co( 'trang có bảng tờ khai', $t, 'Tờ khai GTGT trong kỳ' );
co( 'hiện nhà cung cấp', $t, 'EVN HCMC' );
co( 'tách rõ VAT được khấu trừ', $t, 'VAT được khấu trừ' );
co( 'nêu ngưỡng tiền mặt', $t, '20.000.000 đ' );
co( 'có nút đổi trạng thái khấu trừ', $t, 'bỏ khấu trừ' );
// Đầu vào đang lớn hơn đầu ra nên phải hiện "chuyển kỳ sau", không hiện "phải nộp".
co( 'hiện chuyển kỳ sau', $t, 'Khấu trừ chuyển sang kỳ sau' );
khong_co( 'không hiện dòng phải nộp cùng lúc', $t, '<strong>VAT phải nộp</strong>' );
kiem( 'trang đóng đủ thẻ table', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );
kiem( 'trang đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
