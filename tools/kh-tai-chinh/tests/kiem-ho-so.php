<?php
/**
 * Kiểm hồ sơ — sổ lưu chứng từ.
 *
 *   php tools/kh-tai-chinh/tests/kiem-ho-so.php
 *
 * Chỗ đáng kiểm nhất là phép DÒ NỐI. Nối nhầm còn tệ hơn không nối: bảng
 * "bút toán chưa có chứng từ" sẽ báo là đã có, cho một bút toán thật ra chưa
 * có giấy nào — đúng lúc kiểm toán hỏi thì không ai biết.
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
/** Số bút toán loại $l còn thiếu chứng từ trong kỳ. */
function thieu( $l ) {
	return count( KHTC_HoSo::thieu_chung_tu( $l, '2026-08-01', '2026-08-31' ) );
}

KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them( array( 'ten' => 'VCB', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );

// Sổ sách: 3 hoá đơn đầu vào, 2 khoản chi chuyển khoản, 1 khoản tiền mặt.
KHTC_HoaDonVao::them( array( 'ngay' => '05/08/2026', 'so_hd' => 'V001', 'nha_cung_cap' => 'EVN', 'mst' => '0300942001', 'chua_vat' => '10.000.000', 'thue_suat' => '8' ) );
KHTC_HoaDonVao::them( array( 'ngay' => '08/08/2026', 'so_hd' => 'V002', 'nha_cung_cap' => 'SAWACO', 'mst' => '0300476888', 'chua_vat' => '2.000.000', 'thue_suat' => '5' ) );
KHTC_HoaDonVao::them( array( 'ngay' => '12/08/2026', 'so_hd' => 'V003', 'nha_cung_cap' => 'VPP', 'mst' => '0301111222', 'chua_vat' => '1.000.000', 'thue_suat' => '8' ) );
KHTC_ChiPhi::dan_hang_loat( "05/08/2026\tVăn phòng\tTiền điện\tEVN\t10.800.000\tC001\n08/08/2026\tVăn phòng\tTiền nước\tSAWACO\t2.100.000\tC002\n", $nh );
KHTC_ChiPhi::them( array( 'ngay' => '09/08/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '300.000', 'hinh_thuc' => 'tien_mat', 'so_ct' => 'TM01' ) );

// ------------------------------------------------------------ ràng buộc
kiem( 'không có số CT lẫn tên file thì từ chối', is_wp_error( KHTC_HoSo::them( array( 'loai' => 'khac', 'ngay' => '05/08/2026' ) ) ), true );
kiem( 'ngày sai thì từ chối', is_wp_error( KHTC_HoSo::them( array( 'loai' => 'khac', 'so_ct' => 'X', 'ngay' => 'hom qua' ) ) ), true );
kiem( 'chỉ có tên file thì vẫn lưu được', is_int( KHTC_HoSo::them( array( 'loai' => 'khac', 'ngay' => '05/08/2026', 'ten_file' => 'scan-lung-tung.pdf' ) ) ), true );

// -------------------------------------------- chiều ngược: thiếu chứng từ
kiem( 'ban đầu cả 3 hoá đơn vào đều thiếu chứng từ', thieu( 'hd_vao' ), 3 );
// Chi tiền mặt không soi — kiểm toán lần theo sao kê, tiền mặt lặt vặt không có chứng từ riêng.
kiem( 'chỉ soi chi phí chuyển khoản', thieu( 'chi_phi' ), 2 );

// ---------------------------------------------------------------- dò nối
$a = KHTC_HoSo::them( array( 'loai' => 'hd_vao', 'so_ct' => 'V001', 'ngay' => '05/08/2026', 'doi_tac' => 'EVN', 'so_tien' => '10.800.000', 'ten_file' => 'hd-evn.pdf' ) );
$b = KHTC_HoSo::them( array( 'loai' => 'chi_phi', 'so_ct' => 'C001', 'ngay' => '05/08/2026', 'doi_tac' => 'EVN', 'so_tien' => '10.800.000' ) );
$d = KHTC_HoSo::them( array( 'loai' => 'hd_vao', 'so_ct' => 'KHONG-CO', 'ngay' => '10/08/2026', 'doi_tac' => 'Ai do' ) );

kiem( 'dò nối được 2 chứng từ', KHTC_HoSo::do_noi(), 2 );
kiem( 'hồ sơ V001 nối đúng bảng', KHTC_HoSo::mot( $a )->gan_bang, 'hd_vao' );
kiem( 'và nối đúng bút toán', (int) KHTC_HoSo::mot( $a )->gan_id > 0, true );
kiem( 'hồ sơ C001 nối sang bảng chi phí', KHTC_HoSo::mot( $b )->gan_bang, 'chi_phi' );
kiem( 'số chứng từ không có trong sổ thì không nối', (int) KHTC_HoSo::mot( $d )->gan_id, 0 );
kiem( 'sau khi nối, hoá đơn vào còn thiếu 2', thieu( 'hd_vao' ), 2 );
kiem( 'chi phí còn thiếu 1', thieu( 'chi_phi' ), 1 );

// Chạy lại không nối thêm lần nữa, cũng không nhân đôi.
kiem( 'dò lại không nối thêm gì', KHTC_HoSo::do_noi(), 0 );
kiem( 'và số thiếu không đổi', thieu( 'hd_vao' ), 2 );

// Nối SAI là tệ hơn không nối: hai bút toán cùng số thì phải bỏ qua.
// Số hoá đơn đầu vào duy nhất theo cặp (số, MST) nên hai NCC dùng chung số V009.
KHTC_HoaDonVao::them( array( 'ngay' => '15/08/2026', 'so_hd' => 'V009', 'nha_cung_cap' => 'NCC A', 'mst' => '0311111111', 'chua_vat' => '1.000.000', 'thue_suat' => '8' ) );
KHTC_HoaDonVao::them( array( 'ngay' => '16/08/2026', 'so_hd' => 'V009', 'nha_cung_cap' => 'NCC B', 'mst' => '0322222222', 'chua_vat' => '2.000.000', 'thue_suat' => '8' ) );
$e = KHTC_HoSo::them( array( 'loai' => 'hd_vao', 'so_ct' => 'V009', 'ngay' => '15/08/2026', 'doi_tac' => 'NCC A' ) );
kiem( 'hai bút toán cùng số thì không đoán bừa', KHTC_HoSo::do_noi(), 0 );
kiem( 'và hồ sơ đó vẫn chưa nối', (int) KHTC_HoSo::mot( $e )->gan_id, 0 );

// Loại không nối được sang sổ nào thì bỏ qua, không lỗi.
KHTC_HoSo::them( array( 'loai' => 'bien_ban', 'so_ct' => 'V001', 'ngay' => '05/08/2026' ) );
kiem( 'loại không nối được thì bỏ qua', KHTC_HoSo::do_noi(), 0 );

// Gỡ nối rồi dò lại thì nối lại được.
kiem( 'gỡ nối được', KHTC_HoSo::go_noi( $a ), true );
kiem( 'gỡ xong thì chưa nối', (int) KHTC_HoSo::mot( $a )->gan_id, 0 );
// Lúc này sổ có 5 hoá đơn vào (V001, V002, V003 và hai V009 cùng số khác MST).
kiem( 'và bút toán quay lại danh sách thiếu', thieu( 'hd_vao' ), 5 );
kiem( 'dò lại nối được đúng cái vừa gỡ', KHTC_HoSo::do_noi(), 1 );

// ------------------------------------- cờ hạch toán tách khỏi việc nối
$ky = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );
$l  = KHTC_HoSo::loc( $ky );
kiem( 'đếm đúng số chứng từ', $l['so_ct'], 6 );
kiem( 'đếm đúng số đã nối', $l['da_noi'], 2 );
kiem( 'chưa ai bật cờ hạch toán', $l['da_ht'], 0 );
kiem( 'nên chưa có chỗ lệch nào', $l['lech'], 0 );

// Bật cờ cho một hồ sơ CHƯA nối được — đây là chỗ lệch phải hiện ra.
kiem( 'bật cờ hạch toán được', KHTC_HoSo::dat_hach_toan( $d, true ), true );
$l = KHTC_HoSo::loc( $ky );
kiem( 'đã đánh dấu 1', $l['da_ht'], 1 );
kiem( 'và đúng 1 chỗ lệch: đánh dấu mà chưa nối', $l['lech'], 1 );
kiem( 'số đã nối không đổi theo cờ', $l['da_noi'], 2 );

// Bật cờ cho hồ sơ ĐÃ nối thì không tính là lệch.
KHTC_HoSo::dat_hach_toan( $a, true );
$l = KHTC_HoSo::loc( $ky );
kiem( 'đánh dấu cái đã nối thì không lệch thêm', $l['lech'], 1 );
kiem( 'tổng đã đánh dấu lên 2', $l['da_ht'], 2 );
kiem( 'tắt cờ được', KHTC_HoSo::dat_hach_toan( $d, false ), true );
kiem( 'tắt xong hết lệch', KHTC_HoSo::loc( $ky )['lech'], 0 );
kiem( 'đổi cờ có vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'sua' ) )['so_dong'] >= 3, true );

// ----------------------------------------------------------------- lọc
kiem( 'lọc riêng phần chưa nối', KHTC_HoSo::loc( $ky + array( 'da_noi' => '0' ) )['so_ct'], 4 );
kiem( 'lọc riêng phần đã nối', KHTC_HoSo::loc( $ky + array( 'da_noi' => '1' ) )['so_ct'], 2 );
kiem( 'lọc theo loại', KHTC_HoSo::loc( $ky + array( 'loai' => 'hd_vao' ) )['so_ct'], 3 );
kiem( 'lọc theo cờ hạch toán', KHTC_HoSo::loc( $ky + array( 'hach_toan' => '1' ) )['so_ct'], 1 );
kiem( 'tìm theo tên file', KHTC_HoSo::loc( $ky + array( 'tim' => 'hd-evn' ) )['so_ct'], 1 );
kiem( 'tìm theo đối tác', KHTC_HoSo::loc( $ky + array( 'tim' => 'SAWACO' ) )['so_ct'], 0 );

// --------------------------------------------------------------- dán bảng
$kq = KHTC_HoSo::dan_hang_loat(
	"Loại\tSố chứng từ\tNgày\n"
	. "Hoá đơn đầu vào\tV002\t08/08/2026\tSAWACO\t2.100.000\thd-sawaco.pdf\n"
	. "Chứng từ chi phí\tC002\t08/08/2026\tSAWACO\t2.100.000\n"
	. "Loại la lung\tZZZ\t09/08/2026\n"
);
kiem( 'dán 3 dòng, bỏ dòng tiêu đề', $kq['them'], 3 );
kiem( 'dán không lỗi dòng nào', $kq['loi'], array() );
kiem( 'loại ghi sai thì vào nhóm Khác', KHTC_HoSo::loc( $ky + array( 'tim' => 'ZZZ' ) )['rows'][0]->loai, 'khac' );
kiem( 'dán xong dò nối được 2', KHTC_HoSo::do_noi(), 2 );
// Còn V003 và hai V009 — hai cái sau không nối được vì trùng số.
kiem( 'hoá đơn vào còn 3 cái thiếu chứng từ', thieu( 'hd_vao' ), 3 );
kiem( 'chi phí hết thiếu', thieu( 'chi_phi' ), 0 );
$it = KHTC_HoSo::dan_hang_loat( "Khác\tX\n" );
kiem( 'dòng thiếu cột bị báo lỗi', array( $it['them'], count( $it['loi'] ) ), array( 0, 1 ) );

// Nhãn bút toán đã gắn đọc được, và bút toán bị xoá thì nói thẳng.
$hs_a = KHTC_HoSo::mot( $a );
co( 'nhãn bút toán đã gắn nêu số chứng từ', KHTC_HoSo::nhan_gan( $hs_a ), 'V001' );
kiem( 'hồ sơ chưa nối thì nhãn rỗng', KHTC_HoSo::nhan_gan( KHTC_HoSo::mot( $e ) ), '' );
global $wpdb;
$wpdb->query( 'DELETE FROM wp_khtc_hd_vao WHERE id = ' . (int) $hs_a->gan_id );
kiem( 'bút toán bị xoá thì nói thẳng chứ không im', KHTC_HoSo::nhan_gan( KHTC_HoSo::mot( $a ) ), 'bút toán đã bị xoá' );

// -------------------------------------------------------------- khoá sổ
KHTC_Khoa::dat( '31/08/2026' );
kiem( 'không thêm được vào kỳ khoá', is_wp_error( KHTC_HoSo::them( array( 'loai' => 'khac', 'so_ct' => 'LUI', 'ngay' => '20/08/2026' ) ) ), true );
kiem( 'không xoá được trong kỳ khoá', is_wp_error( KHTC_HoSo::xoa( $b ) ), true );
kiem( 'không đổi cờ hạch toán trong kỳ khoá', is_wp_error( KHTC_HoSo::dat_hach_toan( $b, true ) ), true );
KHTC_Khoa::dat( '' );
kiem( 'mở khoá rồi thì xoá được', KHTC_HoSo::xoa( $b ), true );

// --------------------------------------------------------------- sao lưu
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu có bảng hồ sơ', isset( $sl['bang']['ho_so'] ), true );
kiem( 'mọi bảng đều được gom', array_keys( $sl['bang'] ), KHTC_SaoLuu::bang() );

// ------------------------------------------------------ dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = $ky;
$_POST = array();
$t = dung( array( 'KHTC_Trang', 'ho_so' ) );
co( 'trang có bảng chiều ngược', $t, 'Bút toán chưa có chứng từ lưu' );
co( 'nói rõ đây là câu kiểm toán hỏi', $t, 'kiểm toán sẽ hỏi' );
co( 'nói rõ hai cờ để riêng', $t, 'cố ý để riêng' );
co( 'nói rõ chỉ nối khi duy nhất', $t, 'đúng một' );
co( 'hiện tên file đã lưu', $t, 'hd-evn.pdf' );
co( 'có nút dò nối lại', $t, 'Dò nối lại toàn bộ' );
kiem( 'trang đóng đủ thẻ table', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );
kiem( 'trang đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
