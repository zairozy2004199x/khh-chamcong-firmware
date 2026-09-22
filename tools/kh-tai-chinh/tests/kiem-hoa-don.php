<?php
/**
 * Kiểm hoá đơn đầu ra — nặng nhất ở phép tính VAT.
 *
 *   php tools/kh-tai-chinh/tests/kiem-hoa-don.php
 *
 * Quy tắc phải đúng tuyệt đối, từng dòng một:  chưa VAT + VAT = có VAT.
 * Lệch 1 đồng thì số vẫn hợp lệ, bảng vẫn in ra, nhưng Misa từ chối cả tệp và
 * không nói vì sao — kiểu sai đắt nhất vì không ai thấy cho tới lúc nộp.
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

// ------------------------------------------------------------- phép tính VAT
$t = array( 'KHTC_HoaDonRa', 'tinh' );

kiem( 'chưa VAT + thuế suất 8%', array_slice( call_user_func( $t, 1000000, 0, 0, '8' ), 0, 4 ), array( 1000000, 80000, 1080000, '8' ) );
kiem( 'chưa VAT + thuế suất 10%', array_slice( call_user_func( $t, 1000000, 0, 0, '10' ), 0, 4 ), array( 1000000, 100000, 1100000, '10' ) );
kiem( 'có VAT ngược về chưa VAT', array_slice( call_user_func( $t, 0, 0, 1080000, '8' ), 0, 4 ), array( 1000000, 80000, 1080000, '8' ) );
kiem( 'thuế suất 0%', array_slice( call_user_func( $t, 500000, 0, 0, '0' ), 0, 4 ), array( 500000, 0, 500000, '0' ) );
kiem( 'KCT tính như 0% nhưng giữ nhãn riêng', array_slice( call_user_func( $t, 500000, 0, 0, 'KCT' ), 0, 4 ), array( 500000, 0, 500000, 'KCT' ) );

// Số lẻ: đây là chỗ làm tròn hai lần sẽ lệch.
foreach ( array( 333333, 1, 7, 99999, 12345679, 555555, 1000001 ) as $co ) {
	foreach ( array( '5', '8', '10' ) as $ts ) {
		$r = call_user_func( $t, 0, 0, $co, $ts );
		if ( $r[0] + $r[1] !== $r[2] ) {
			$hong[] = "tách ngược $co @ $ts%: {$r[0]} + {$r[1]} ≠ {$r[2]}";
		} else { $dat++; }
		if ( $r[2] !== $co ) {
			$hong[] = "tách ngược $co @ $ts%: có VAT đổi thành {$r[2]}";
		} else { $dat++; }
	}
}
foreach ( array( 333333, 1, 7, 99999, 12345679 ) as $chua ) {
	foreach ( array( '5', '8', '10' ) as $ts ) {
		$r = call_user_func( $t, $chua, 0, 0, $ts );
		if ( $r[0] + $r[1] !== $r[2] ) {
			$hong[] = "tính xuôi $chua @ $ts%: {$r[0]} + {$r[1]} ≠ {$r[2]}";
		} else { $dat++; }
	}
}

// Đủ cả ba số mà file gốc lệch: CÓ VAT thắng, chênh lệch dồn vào VAT.
//
// Có VAT là tiền khách đã trả thật; chưa VAT và VAT là số suy ra rồi làm tròn.
// Sửa có VAT là sửa số tiền đã thu. Bản đầu làm ngược và dữ liệu thật 2025 của
// công ty bắt được: 234 / 2.781 hoá đơn bị đổi tổng đi 1 đồng.
$r = call_user_func( $t, 1000000, 80000, 1080001 );
kiem( 'lệch 1 đồng thì giữ tổng, bù vào VAT', array_slice( $r, 0, 3 ), array( 1000000, 80001, 1080001 ) );
kiem( 'và báo là có lệch', $r[4], true );
$r = call_user_func( $t, 1000000, 80000, 1080000 );
kiem( 'file gốc khớp thì không báo lệch', $r[4], false );

// Đúng hai dòng thật trong file VAT đầu ra 2025, cả hai chiều làm tròn. File
// tính VAT = round(chưa VAT × 8%) nên làm tròn hai lần và lệch 1 đồng; hoá đơn
// phải giữ đúng số tiền tròn mà khách đã trả.
$r = call_user_func( $t, 61356481, 4908518, 66265000 );   // HĐ 2576 KH989, hụt 1
kiem( 'HĐ thật hụt 1 đồng: giữ 66.265.000', array_slice( $r, 0, 3 ), array( 61356481, 4908519, 66265000 ) );
$r = call_user_func( $t, 1218519, 97482, 1316000 );       // HĐ 2693 KH989, dư 1
kiem( 'HĐ thật dư 1 đồng: giữ 1.316.000', array_slice( $r, 0, 3 ), array( 1218519, 97481, 1316000 ) );

// Không có cột tổng thì chưa VAT + VAT vẫn là tất cả những gì biết.
kiem( 'thiếu cột có VAT thì cộng lại', array_slice( call_user_func( $t, 1000000, 80000, 0 ), 0, 3 ), array( 1000000, 80000, 1080000 ) );

// Thuế suất tự suy khi file không ghi.
kiem( 'tự suy 8%', call_user_func( $t, 1000000, 80000, 0 )[3], '8' );
kiem( 'tự suy 10%', call_user_func( $t, 1000000, 100000, 0 )[3], '10' );
kiem( 'tự suy 5%', call_user_func( $t, 1000000, 50000, 0 )[3], '5' );
kiem( 'tự suy 0% khi không có thuế', call_user_func( $t, 1000000, 0, 0 )[3], '0' );
// 7,99% do làm tròn không được thành một bậc thuế mới trong bảng tờ khai.
kiem( 'lệch do làm tròn vẫn về đúng bậc 8%', call_user_func( $t, 333333, 26667, 0 )[3], '8' );

// ------------------------------------------------------------------- ghi sổ
KHTC_Cty::chon( 'kh_cu' );
kiem( 'thiếu số hoá đơn thì từ chối', is_wp_error( KHTC_HoaDonRa::them( array( 'ngay' => '05/08/2026', 'chua_vat' => '1.000.000' ) ) ), true );
kiem( 'không có tiền thì từ chối', is_wp_error( KHTC_HoaDonRa::them( array( 'ngay' => '05/08/2026', 'so_hd' => 'X1' ) ) ), true );
kiem( 'ngày sai thì từ chối', is_wp_error( KHTC_HoaDonRa::them( array( 'ngay' => 'mai', 'so_hd' => 'X2', 'co_vat' => '100.000' ) ) ), true );

$id = KHTC_HoaDonRa::them( array( 'ngay' => '05/08/2026', 'so_hd' => '00000001', 'khach' => 'CONG TY ABC', 'co_vat' => '1.080.000', 'thue_suat' => '8' ) );
kiem( 'ghi được hoá đơn', is_int( $id ) && $id > 0, true );
kiem( 'trùng số hoá đơn bị từ chối', is_wp_error( KHTC_HoaDonRa::them( array( 'ngay' => '06/08/2026', 'so_hd' => '00000001', 'co_vat' => '500.000' ) ) ), true );
// Số hoá đơn chỉ duy nhất trong MỘT pháp nhân — hai công ty đánh số riêng.
KHTC_Cty::chon( 'kh_moi' );
kiem( 'pháp nhân khác vẫn dùng được số đó', is_int( KHTC_HoaDonRa::them( array( 'ngay' => '06/08/2026', 'so_hd' => '00000001', 'co_vat' => '500.000' ) ) ), true );
KHTC_Cty::chon( 'kh_cu' );

// -------------------------------------------------- dán đúng 22 cột file VAT
// Cột: STT, Ngày HĐ, Số HĐ, Tên KH, MST, Địa chỉ KH, Email, Nội dung, Số lượng,
// ĐVT, Thành tiền, Chưa VAT, VAT, Có VAT, Khu vực, Dịch vụ, Số HĐ (hợp đồng),
// Mã điểm nội bộ, Mã điểm misa, Ghi chú, (trống), Địa chỉ.
$h = "STT\tNgày HĐ\tSố HĐ\tTên khách hàng\tMã số thuế khách hàng\tĐịa chỉ khách hàng\tEmail nhận hóa đơn\tNội dung xuất hóa đơn\tSố lượng\tĐVT\tThành tiền\tChưa VAT\tVAT\tCó VAT\tKhu vực\tDịch vụ\tSố hợp đồng (nếu có)\tMã điểm nội bộ\tMã điểm misa\tGhi chú\t\tĐịa chỉ\n";
$b = $h
	. "1\t05/08/2026\t00000010\tCONG TY TNHH ABC\t0301234567\t12 Le Loi\tab@abc.vn\tDich vu vui choi\t1\tLan\t1.000.000\t1.000.000\t80.000\t1.080.000\tHCM\tKVC\t\tKVC-01\tMS-01\t\t\t12 Le Loi\n"
	. "2\t06/08/2026\t00000011\tCONG TY XYZ\t0309876543\t\t\tGhe massage\t1\tLan\t2.000.000\t2.000.000\t200.000\t2.200.000\tHCM\tGM\t\tGM-02\tMS-02\t\t\t\n"
	. "3\t07/08/2026\t00000012\tKhach le\t\t\t\tVe vao cong\t1\tVe\t500.000\t500.000\t0\t500.000\tHN\tKVC\t\tKVC-09\tMS-09\t\t\t\n";
$kq = KHTC_HoaDonRa::dan_hang_loat( $b );
kiem( 'dán 3 hoá đơn, bỏ dòng tiêu đề', $kq['them'], 3 );
kiem( 'dán không lỗi dòng nào', $kq['loi'], array() );
kiem( 'dán lại lần hai bị chặn hết', KHTC_HoaDonRa::dan_hang_loat( $b )['trung'], 3 );

$ky = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );
$l  = KHTC_HoaDonRa::loc( $ky );
kiem( 'tổng doanh thu chưa VAT', $l['chua_vat'], 4500000 );
kiem( 'tổng VAT đầu ra', $l['vat'], 360000 );
kiem( 'tổng có VAT', $l['co_vat'], 4860000 );
kiem( 'tổng có VAT bằng chưa VAT cộng VAT', $l['chua_vat'] + $l['vat'], $l['co_vat'] );
kiem( 'đếm đúng 4 hoá đơn', $l['so_hd'], 4 );
kiem( 'lấy được mã điểm misa từ cột 19', $l['rows'][0]->ma_misa, 'MS-09' );

// --------------------------------------------------------- gom theo nhóm
$g = KHTC_HoaDonRa::gom_theo( 'thue_suat', $ky );
$theo = array();
foreach ( $g as $r ) { $theo[ $r->nhan ] = array( (int) $r->so_hd, (int) $r->chua_vat, (int) $r->vat ); }
kiem( 'bậc 8% gom đúng', $theo['8'], array( 2, 2000000, 160000 ) );
kiem( 'bậc 10% gom đúng', $theo['10'], array( 1, 2000000, 200000 ) );
kiem( 'bậc 0% gom đúng', $theo['0'], array( 1, 500000, 0 ) );
kiem( 'tổng các bậc bằng tổng chung', array_sum( array_column( array_values( $theo ), 1 ) ), $l['chua_vat'] );

$kv = KHTC_HoaDonRa::gom_theo( 'khu_vuc', $ky );
kiem( 'gom theo khu vực: khu vực lớn nhất xếp trước', $kv[0]->nhan, 'HCM' );
kiem( 'gom theo dịch vụ ra 3 nhóm', count( KHTC_HoaDonRa::gom_theo( 'dich_vu', $ky ) ), 3 );
kiem( 'cột lạ bị từ chối gom', KHTC_HoaDonRa::gom_theo( 'khach', $ky ), array() );

kiem( 'giá trị khu vực đang có trong sổ', KHTC_HoaDonRa::gia_tri_co( 'khu_vuc' ), array( 'HCM', 'HN' ) );

// Lọc theo bậc thuế phải khớp bảng gom.
kiem( 'lọc bậc 8% ra đúng số', KHTC_HoaDonRa::loc( $ky + array( 'thue_suat' => '8' ) )['chua_vat'], 2000000 );
kiem( 'lọc theo khu vực HN', KHTC_HoaDonRa::loc( $ky + array( 'khu_vuc' => 'HN' ) )['so_hd'], 1 );
kiem( 'tìm theo MST', KHTC_HoaDonRa::loc( $ky + array( 'tim' => '0301234567' ) )['so_hd'], 1 );
kiem( 'tìm theo số hoá đơn', KHTC_HoaDonRa::loc( $ky + array( 'tim' => '00000011' ) )['so_hd'], 1 );

// Dòng thiếu cột.
$it = KHTC_HoaDonRa::dan_hang_loat( "9\t08/08/2026\t00000099\tKhach\n" );
kiem( 'dòng thiếu cột bị báo lỗi', array( $it['them'], count( $it['loi'] ) ), array( 0, 1 ) );

// ------------------------------- xuất ra rồi dán lại phải ra y hệt (khứ hồi)
$goc  = KHTC_HoaDonRa::loc( $ky )['rows'];
$hang = KHTC_HoaDonRa::hang_csv( $goc );
kiem( 'dòng đầu tệp xuất là tiêu đề 22 cột', count( $hang[0] ), 22 );
kiem( 'tiêu đề khớp đúng tên cột file VAT', $hang[0], KHTC_HoaDonRa::cot() );
kiem( 'mọi dòng đều đủ 22 cột', count( array_unique( array_map( 'count', $hang ) ) ), 1 );

// Dán lại vào pháp nhân khác: cùng số hoá đơn nhưng sổ riêng nên không đụng nhau.
$tsv = '';
foreach ( $hang as $h3 ) { $tsv .= implode( "\t", $h3 ) . "\n"; }
KHTC_Cty::chon( 'kh_moi' );
// Sổ KH Mới đã có sẵn một hoá đơn số 00000001 với số tiền khác, nên dòng đó bị
// chặn trùng. So phần CHÊNH chứ không so tổng — tổng còn mang cả số cũ.
$truoc = KHTC_HoaDonRa::loc( $ky );
$da_co = array();
foreach ( $truoc['rows'] as $r1 ) { $da_co[ $r1->so_hd ] = true; }
$mong = array( 0, 0, 0 );
foreach ( $goc as $r1 ) {
	if ( isset( $da_co[ $r1->so_hd ] ) ) { continue; }
	$mong[0] += (int) $r1->chua_vat;
	$mong[1] += (int) $r1->vat;
	$mong[2] += (int) $r1->co_vat;
}
$lai = KHTC_HoaDonRa::dan_hang_loat( $tsv );
kiem( 'dán lại tệp vừa xuất: nạp đủ', $lai['them'] + $lai['trung'], count( $goc ) );
kiem( 'dán lại không lỗi dòng nào', $lai['loi'], array() );
kiem( 'đúng một dòng bị chặn vì trùng số hoá đơn', $lai['trung'], 1 );
$sau = KHTC_HoaDonRa::loc( $ky );
kiem( 'khứ hồi giữ nguyên doanh thu chưa VAT', $sau['chua_vat'] - $truoc['chua_vat'], $mong[0] );
kiem( 'khứ hồi giữ nguyên VAT', $sau['vat'] - $truoc['vat'], $mong[1] );
kiem( 'khứ hồi giữ nguyên có VAT', $sau['co_vat'] - $truoc['co_vat'], $mong[2] );
kiem( 'khứ hồi không làm lệch chưa VAT + VAT = có VAT', $sau['chua_vat'] + $sau['vat'], $sau['co_vat'] );
$mot = null;
foreach ( $sau['rows'] as $r2 ) { if ( '00000010' === $r2->so_hd ) { $mot = $r2; } }
kiem( 'khứ hồi giữ đúng tên khách', $mot->khach, 'CONG TY TNHH ABC' );
kiem( 'khứ hồi giữ đúng MST', $mot->mst, '0301234567' );
kiem( 'khứ hồi giữ đúng mã điểm nội bộ', $mot->ma_diem, 'KVC-01' );
kiem( 'khứ hồi giữ đúng mã điểm misa', $mot->ma_misa, 'MS-01' );
kiem( 'khứ hồi giữ đúng địa chỉ ở cột 22', $mot->dia_chi, '12 Le Loi' );
kiem( 'khứ hồi giữ đúng khu vực', $mot->khu_vuc, 'HCM' );
KHTC_Cty::chon( 'kh_cu' );

// ------------------------------------------------- sao lưu phải mang theo HĐ
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu có bảng hoá đơn đầu ra', isset( $sl['bang']['hd_ra'] ), true );
kiem( 'sao lưu giữ đủ hoá đơn cả hai pháp nhân', count( $sl['bang']['hd_ra'] ), 8 );

// Nhập đè lên sổ đã có: UNIQUE chặn, phải đếm vào nhóm "bị bỏ", không báo là đã nhập.
$nhap = KHTC_SaoLuu::nhap( wp_json_encode( $sl ) );
kiem( 'hoá đơn trùng số bị từ chối chứ không nhân đôi', $nhap['them']['hd_ra'], 0 );
kiem( 'và được báo là bị bỏ', $nhap['bo']['hd_ra'], 8 );
kiem( 'số hoá đơn trong sổ không đổi', KHTC_HoaDonRa::loc( $ky )['so_hd'], 4 );

// ------------------------------------------------------- dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = $ky;
$_POST = array();
$t2 = dung( array( 'KHTC_Trang', 'hoa_don_ra' ) );
co( 'trang có bảng tờ khai theo thuế suất', $t2, 'số để điền tờ khai GTGT' );
co( 'trang hiện tên khách', $t2, 'CONG TY TNHH ABC' );
co( 'trang hiện tổng VAT đầu ra', $t2, '360.000 đ' );
co( 'trang có nút tải CSV 22 cột', $t2, '22 cột như file VAT' );
co( 'trang nêu đủ thứ tự cột để dán', $t2, 'Mã điểm misa' );
// PHP đổi khoá mảng '8' thành số nguyên 8; so nghiêm ngặt với chuỗi thì trượt
// và ô chọn rơi về bậc đầu tiên (0%) — sai lặng lẽ, mỗi hoá đơn ghi tay mất thuế.
co( 'ô thuế suất mặc định đúng bậc 8%', $t2, '<option value="8" selected>8%</option>' );
kiem( 'chỉ đúng một bậc được chọn sẵn', substr_count( $t2, ' selected>' ), 1 );
kiem( 'trang đóng đủ thẻ table', substr_count( $t2, '<table' ), substr_count( $t2, '</table>' ) );
kiem( 'trang đóng đủ thẻ div', substr_count( $t2, '<div' ), substr_count( $t2, '</div>' ) );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h2 ) { echo "  ✗ $h2\n"; }
exit( $hong ? 1 : 0 );
