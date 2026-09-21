<?php
/**
 * Kiểm khoá sổ và nhật ký.
 *
 *   php tools/kh-tai-chinh/tests/kiem-khoa-nhat-ky.php
 *
 * Khoá sổ chỉ có giá trị nếu KHÔNG CÓ ĐƯỜNG VÒNG. Một lối ghi quên kiểm tra là
 * cả tính năng thành trang trí: tờ khai đã nộp vẫn bị đổi số sau lưng. Nên bộ
 * này đi thử từng lối ghi một, kể cả lối gián tiếp (xoá tài khoản kéo theo xoá
 * giao dịch, nhập sao lưu, phục hồi từ nhật ký).
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
function so_dong( $bang ) {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_khtc_' . $bang );
}

KHTC_Cty::chon( 'kh_cu' );
$nh = KHTC_NganHang::them( array( 'ten' => 'Vietcombank 0123', 'so_du_dau' => 100000000, 'ngay_dau' => '2026-07-01' ) );

// ------------------------------------------------------------- nhật ký ghi
kiem( 'thêm tài khoản có vào nhật ký', so_dong( 'nhat_ky' ), 1 );
$nk = KHTC_NhatKy::loc( array() );
kiem( 'nhật ký ghi đúng việc', $nk['rows'][0]->viec, 'them' );
kiem( 'nhật ký ghi đúng người', $nk['rows'][0]->ai, 'Kế toán' );
co( 'nhật ký ghi tên tài khoản', $nk['rows'][0]->tom_tat, 'Vietcombank 0123' );

KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh, 'ngay' => '15/07/2026', 'dien_giai' => 'Thu thang 7', 'so_tien' => '5.000.000', 'loai' => 'thu' ) );
kiem( 'thêm giao dịch có vào nhật ký', so_dong( 'nhat_ky' ), 2 );

// Dán hàng loạt chỉ ghi MỘT dòng tổng kết, không ghi từng dòng.
$truoc = so_dong( 'nhat_ky' );
KHTC_GiaoDich::dan_hang_loat(
	$nh,
	"01/08/2026\tThu A\t1.000.000\tThu\n02/08/2026\tThu B\t2.000.000\tThu\n"
	. "03/08/2026\tChi C\t-500.000\tChi\n04/08/2026\tThu D\t3.000.000\tThu\n05/08/2026\tThu E\t4.000.000\tThu\n"
);
kiem( 'dán 5 dòng chỉ ghi 1 dòng nhật ký', so_dong( 'nhat_ky' ) - $truoc, 1 );
$nk = KHTC_NhatKy::loc( array() );
kiem( 'dòng đó là việc nạp', $nk['rows'][0]->viec, 'nap' );
co( 'và nêu đúng số dòng', $nk['rows'][0]->tom_tat, 'Nạp 5 giao dịch' );
kiem( 'cờ lô đã được đóng lại', KHTC_NhatKy::trong_lo(), false );

// ------------------------------------------------------------- khoá sổ
kiem( 'chưa khoá thì ngày trả về rỗng', KHTC_Khoa::ngay(), '' );
kiem( 'chưa khoá thì không chặn gì', KHTC_Khoa::bi_khoa( '2026-07-15' ), false );

KHTC_Khoa::dat( '31/07/2026' );
kiem( 'đặt khoá lưu đúng ngày', KHTC_Khoa::ngay(), '2026-07-31' );
kiem( 'ngày trước khoá bị chặn', KHTC_Khoa::bi_khoa( '2026-07-15' ), true );
kiem( 'đúng ngày khoá cũng bị chặn', KHTC_Khoa::bi_khoa( '2026-07-31' ), true );
kiem( 'ngày sau khoá không bị chặn', KHTC_Khoa::bi_khoa( '2026-08-01' ), false );
kiem( 'đặt khoá có vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'khoa' ) )['so_dong'], 1 );

// Khoá tách theo pháp nhân: khoá KH Cũ không ảnh hưởng KH Mới.
KHTC_Cty::chon( 'kh_moi' );
kiem( 'pháp nhân khác không bị khoá lây', KHTC_Khoa::ngay(), '' );
KHTC_Cty::chon( 'kh_cu' );

// ------------------------------- mọi lối ghi vào kỳ khoá đều phải bị chặn
$gd = KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh, 'ngay' => '20/07/2026', 'dien_giai' => 'lui ngay', 'so_tien' => '9.000.000', 'loai' => 'thu' ) );
kiem( 'không thêm được giao dịch lùi vào kỳ khoá', is_wp_error( $gd ), true );

$cp = KHTC_ChiPhi::them( array( 'ngay' => '20/07/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '100.000' ) );
kiem( 'không thêm được chi phí lùi vào kỳ khoá', is_wp_error( $cp ), true );

$hd = KHTC_HoaDonRa::them( array( 'ngay' => '20/07/2026', 'so_hd' => 'LUI001', 'co_vat' => '1.080.000', 'thue_suat' => '8' ) );
kiem( 'không thêm được hoá đơn lùi vào kỳ khoá', is_wp_error( $hd ), true );
kiem( 'hoá đơn bị chặn thì không nằm trong sổ', KHTC_HoaDonRa::da_co( 'LUI001' ), false );

// Ngày sau khoá vẫn ghi được bình thường.
kiem( 'ngày sau khoá vẫn thêm được', is_int( KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh, 'ngay' => '10/08/2026', 'dien_giai' => 'binh thuong', 'so_tien' => '1.000.000', 'loai' => 'thu' ) ) ), true );

// Xoá một dòng NẰM TRONG kỳ khoá cũng phải bị chặn.
global $wpdb;
$cu = (int) $wpdb->get_var( "SELECT id FROM wp_khtc_giao_dich WHERE ngay = '2026-07-15'" );
kiem( 'không xoá được giao dịch trong kỳ khoá', is_wp_error( KHTC_GiaoDich::xoa( $cu ) ), true );
kiem( 'và nó vẫn còn trong sổ', (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_khtc_giao_dich WHERE id = $cu" ), 1 );

// Lối vòng nguy hiểm nhất: xoá tài khoản sẽ kéo theo xoá hết giao dịch của nó.
kiem( 'không xoá được tài khoản còn giao dịch trong kỳ khoá', is_wp_error( KHTC_NganHang::xoa( $nh ) ), true );
kiem( 'tài khoản vẫn còn', count( KHTC_NganHang::ds() ), 1 );
kiem( 'giao dịch trong kỳ khoá vẫn còn nguyên', (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_khtc_giao_dich WHERE ngay <= '2026-07-31'" ), 1 );

// --------------------------------------------------- xoá rồi phục hồi lại
KHTC_Khoa::dat( '' );
kiem( 'mở khoá xong ngày về rỗng', KHTC_Khoa::ngay(), '' );

$id_hd = KHTC_HoaDonRa::them( array( 'ngay' => '10/08/2026', 'so_hd' => 'HD777', 'khach' => 'CONG TY ABC', 'co_vat' => '1.080.000', 'thue_suat' => '8', 'khu_vuc' => 'HCM' ) );
$ky    = array( 'tu' => '2026-08-01', 'den' => '2026-08-31' );
kiem( 'hoá đơn đã vào sổ', KHTC_HoaDonRa::loc( $ky )['so_hd'], 1 );

kiem( 'xoá được', KHTC_HoaDonRa::xoa( $id_hd ), true );
kiem( 'sổ không còn hoá đơn đó', KHTC_HoaDonRa::loc( $ky )['so_hd'], 0 );

$log = KHTC_NhatKy::loc( array( 'viec' => 'xoa' ) );
kiem( 'lần xoá có vào nhật ký', $log['so_dong'], 1 );
kiem( 'và giữ lại cả bản ghi để phục hồi', '' !== (string) $log['rows'][0]->du_lieu, true );
co( 'tóm tắt nêu đủ số hoá đơn', $log['rows'][0]->tom_tat, 'HD777' );

$ph = KHTC_NhatKy::phuc_hoi( (int) $log['rows'][0]->id );
kiem( 'phục hồi trả về đúng id cũ', $ph, $id_hd );
kiem( 'hoá đơn trở lại sổ', KHTC_HoaDonRa::loc( $ky )['so_hd'], 1 );
$lai = KHTC_HoaDonRa::loc( $ky )['rows'][0];
kiem( 'phục hồi giữ đúng id', (int) $lai->id, $id_hd );
kiem( 'phục hồi giữ đúng khách', $lai->khach, 'CONG TY ABC' );
kiem( 'phục hồi giữ đúng số tiền', (int) $lai->co_vat, 1080000 );
kiem( 'phục hồi giữ đúng VAT', (int) $lai->vat, 80000 );

kiem( 'phục hồi lần hai bị chặn vì đã có', is_wp_error( KHTC_NhatKy::phuc_hoi( (int) $log['rows'][0]->id ) ), true );
kiem( 'phục hồi có ghi vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'phuc_hoi' ) )['so_dong'], 1 );

// Phục hồi vào kỳ đã khoá phải bị chặn.
$id2 = KHTC_HoaDonRa::them( array( 'ngay' => '05/09/2026', 'so_hd' => 'HD888', 'co_vat' => '540.000', 'thue_suat' => '8' ) );
KHTC_HoaDonRa::xoa( $id2 );
$log2 = KHTC_NhatKy::loc( array( 'viec' => 'xoa' ) );
KHTC_Khoa::dat( '30/09/2026' );
kiem( 'không phục hồi được vào kỳ đã khoá', is_wp_error( KHTC_NhatKy::phuc_hoi( (int) $log2['rows'][0]->id ) ), true );
KHTC_Khoa::dat( '' );
kiem( 'mở khoá rồi thì phục hồi được', KHTC_NhatKy::phuc_hoi( (int) $log2['rows'][0]->id ), $id2 );

kiem( 'dòng nhật ký không phải lần xoá thì không phục hồi được', is_wp_error( KHTC_NhatKy::phuc_hoi( 1 ) ), true );
kiem( 'id nhật ký không có thì báo lỗi', is_wp_error( KHTC_NhatKy::phuc_hoi( 999999 ) ), true );

// Chạy đối soát cũng để lại vết — cả cổng lẫn chi phí.
KHTC_ChiPhi::them( array( 'ngay' => '10/08/2026', 'bo_phan' => 'Văn phòng', 'khoan_muc' => 'Khác', 'so_tien' => '1.000.000' ) );
KHTC_ChiPhi::doi_soat( '2026-08-01', '2026-08-31', $nh );
kiem( 'chạy đối soát chi phí có vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'doi_soat' ) )['so_dong'], 1 );

// --------------------------------------- sao lưu cũng phải mang theo nhật ký
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu có bảng nhật ký', isset( $sl['bang']['nhat_ky'] ), true );
kiem( 'mọi bảng trong danh sách đều được gom', array_keys( $sl['bang'] ), KHTC_SaoLuu::bang() );
$nhap = KHTC_SaoLuu::nhap( wp_json_encode( $sl ) );
kiem( 'nhập lại có xử lý bảng nhật ký', isset( $nhap['them']['nhat_ky'] ), true );
kiem( 'lần nhập tự nó cũng để lại vết', KHTC_NhatKy::loc( array( 'viec' => 'nhap' ) )['so_dong'] > 0, true );

// ----------------------------------------------------- dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = array();
$_POST = array();
$t = dung( array( 'KHTC_Trang', 'nhat_ky' ) );
co( 'trang có phần khoá sổ', $t, 'Khoá đến hết ngày' );
co( 'chưa khoá thì cảnh báo', $t, 'Chưa khoá kỳ nào' );
co( 'trang liệt kê nhật ký', $t, 'Nhật ký thay đổi' );
co( 'có nút phục hồi cho dòng xoá', $t, 'Phục hồi</button>' );
kiem( 'trang đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );
kiem( 'trang đóng đủ thẻ table', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );

KHTC_Khoa::dat( '31/08/2026' );
$t = dung( array( 'KHTC_Trang', 'nhat_ky' ) );
co( 'đã khoá thì hiện đúng ngày', $t, 'Đang khoá đến hết' );
co( 'và nêu ngày cụ thể', $t, '31/08/2026' );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
