<?php
/**
 * Kiểm pháp danh — sổ hợp đồng.
 *
 *   php tools/kh-tai-chinh/tests/kiem-phap-danh.php
 *
 * Hai chỗ đáng kiểm nhất:
 *   1. "Sắp hết hạn" — lý do tồn tại của cả sổ. Bản gốc lưu thời hạn thành chữ
 *      tự do nên không trả lời được; ở đây phải trả lời đúng, kể cả trường hợp
 *      đã quá hạn và trường hợp đúng ngày biên.
 *   2. Doanh thu chia sẻ — ghép theo mã điểm. Ghép sai là trả nhầm tiền cho
 *      người khác, nên hợp đồng chưa gắn mã điểm phải ra 0 chứ không đoán.
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

// ------------------------------------------------------------- ràng buộc
kiem( 'thuê mà thiếu gian thì từ chối', is_wp_error( KHTC_PhapDanh::them( array( 'loai' => 'thue', 'doi_tac' => 'A' ) ) ), true );
kiem( 'thiếu đối tác thì từ chối', is_wp_error( KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'A1' ) ) ), true );
kiem( 'NCC không cần gian', is_int( KHTC_PhapDanh::them( array( 'loai' => 'ncc', 'doi_tac' => 'CTY BAO TRI', 'gia_tri' => '120.000.000' ) ) ), true );
kiem( 'bắt đầu sau hết hạn thì từ chối', is_wp_error( KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'X', 'doi_tac' => 'Y', 'ngay_bat_dau' => '01/12/2026', 'ngay_het_han' => '01/01/2026' ) ) ), true );
kiem( 'tỷ lệ chia sẻ trên 100% bị từ chối', is_wp_error( KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'X', 'doi_tac' => 'Y', 'phan_tram' => '150' ) ) ), true );
kiem( 'tỷ lệ âm bị từ chối', is_wp_error( KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'X', 'doi_tac' => 'Y', 'phan_tram' => '-5' ) ) ), true );
$x100 = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'X100', 'doi_tac' => 'Y', 'phan_tram' => '100' ) );
kiem( 'đúng 100% thì được', is_int( $x100 ), true );
// Có tỷ lệ mà chưa chọn hình thức là trạng thái mâu thuẫn — phải tự về giữ tiền,
// giống hệt ô dán bảng, nếu không hợp đồng mang 15% mà không bao giờ hiện ra.
kiem( 'có tỷ lệ mà chưa chọn hình thức thì tự đặt giữ tiền', KHTC_PhapDanh::mot( $x100 )->loai_chia_se, 'giu_tien' );
kiem( 'không tỷ lệ thì vẫn để trống', KHTC_PhapDanh::mot( KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'X0', 'doi_tac' => 'Y' ) ) )->loai_chia_se, '' );

// -------------------------------------------------------------- sắp hết hạn
$hom_nay = current_time( 'Y-m-d' );
function ngay_cong( $n ) {
	return gmdate( 'd/m/Y', strtotime( current_time( 'Y-m-d' ) . ' 00:00:00 UTC' ) + $n * 86400 );
}
$a = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'A1-05', 'doi_tac' => 'CTY BDS An Phu', 'mst' => '0301234567', 'khu_vuc' => 'HCM', 'ma_diem' => 'KVC-CRESCENT', 'gia_tri' => '35.000.000', 'ngay_het_han' => ngay_cong( 10 ), 'link_du_dau' => 'https://vi.du/hd1.pdf' ) );
$b = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'B2-11', 'doi_tac' => 'CTY VINCOM', 'khu_vuc' => 'HN', 'ma_diem' => 'GM-VINCOM', 'gia_tri' => '28.000.000', 'ngay_het_han' => ngay_cong( -20 ) ) );
$d = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'C3-02', 'doi_tac' => 'CTY AEON', 'khu_vuc' => 'HCM', 'ma_diem' => 'MTD-AEON', 'gia_tri' => '12.000.000', 'ngay_het_han' => ngay_cong( 400 ) ) );
$e = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'D4-09', 'doi_tac' => 'CTY ROYAL', 'khu_vuc' => 'HN', 'gia_tri' => '9.000.000' ) );

$het = KHTC_PhapDanh::sap_het_han( 'thue' );
$id_het = array_map( function ( $r ) { return (int) $r->id; }, $het );
kiem( 'bắt được hợp đồng còn 10 ngày', in_array( $a, $id_het, true ), true );
kiem( 'bắt được hợp đồng đã quá hạn', in_array( $b, $id_het, true ), true );
kiem( 'không bắt hợp đồng còn 400 ngày', in_array( $d, $id_het, true ), false );
kiem( 'không bắt hợp đồng chưa điền hạn', in_array( $e, $id_het, true ), false );
kiem( 'sắp hết hạn đúng 2 dòng', count( $het ), 2 );
kiem( 'đã quá hạn xếp trước', (int) $het[0]->id, $b );
kiem( 'số ngày còn lại mang dấu âm khi quá hạn', $het[0]->_con_ngay, -20 );
kiem( 'số ngày còn lại dương khi chưa tới', $het[1]->_con_ngay, 10 );

// Biên: đúng mốc 60 ngày thì vẫn tính là sắp hết; 61 ngày thì không.
$m60 = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'M60', 'doi_tac' => 'Bien 60', 'ngay_het_han' => ngay_cong( KHTC_PhapDanh::SAP_HET ) ) );
$m61 = KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'M61', 'doi_tac' => 'Bien 61', 'ngay_het_han' => ngay_cong( KHTC_PhapDanh::SAP_HET + 1 ) ) );
$id2 = array_map( function ( $r ) { return (int) $r->id; }, KHTC_PhapDanh::sap_het_han( 'thue' ) );
kiem( 'đúng mốc 60 ngày vẫn tính là sắp hết', in_array( $m60, $id2, true ), true );
kiem( '61 ngày thì chưa tính', in_array( $m61, $id2, true ), false );

// Hợp đồng đã đóng thì rơi khỏi danh sách, kể cả quá hạn.
kiem( 'đổi trạng thái được', KHTC_PhapDanh::doi_trang_thai( $b, 'da_dong' ), true );
kiem( 'đã đóng thì không còn trong sắp hết hạn', in_array( $b, array_map( function ( $r ) { return (int) $r->id; }, KHTC_PhapDanh::sap_het_han( 'thue' ) ), true ), false );
kiem( 'đổi trạng thái có vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'sua' ) )['so_dong'], 1 );

// Hai sổ tách nhau: hợp đồng NCC không lẫn sang sổ thuê.
kiem( 'sắp hết hạn của sổ NCC tính riêng', count( KHTC_PhapDanh::sap_het_han( 'ncc' ) ), 0 );

// --------------------------------------------------------------- tổng hợp
$l = KHTC_PhapDanh::loc( array( 'loai' => 'thue' ) );
kiem( 'đếm đủ hợp đồng thuê', $l['so_hd'], 8 );
kiem( 'đang hoạt động không tính cái đã đóng', $l['dang_chay'], 7 );
// 35 + 12 + 9 + 0 (X100) + 0 (M60) + 0 (M61) — cái đã đóng (28tr) không tính.
kiem( 'tổng tiền thuê chỉ cộng hợp đồng đang chạy', $l['gia_tri'], 56000000 );
kiem( 'đếm hợp đồng chưa có bản đủ dấu', $l['thieu_dau'], 7 );

// Hợp đồng được miễn thì không cộng vào tổng tiền thuê.
KHTC_PhapDanh::them( array( 'loai' => 'thue', 'gian' => 'MIEN1', 'doi_tac' => 'Doi tac mien', 'gia_tri' => '99.000.000', 'mien' => 1 ) );
kiem( 'hợp đồng được miễn không cộng vào tổng', KHTC_PhapDanh::loc( array( 'loai' => 'thue' ) )['gia_tri'], 56000000 );
kiem( 'nhưng vẫn đếm là đang hoạt động', KHTC_PhapDanh::loc( array( 'loai' => 'thue' ) )['dang_chay'], 8 );

kiem( 'lọc theo khu vực', KHTC_PhapDanh::loc( array( 'loai' => 'thue', 'khu_vuc' => 'HCM' ) )['so_hd'], 2 );
kiem( 'lọc theo trạng thái đã đóng', KHTC_PhapDanh::loc( array( 'loai' => 'thue', 'trang_thai' => 'da_dong' ) )['so_hd'], 1 );
kiem( 'tìm theo MST', KHTC_PhapDanh::loc( array( 'loai' => 'thue', 'tim' => '0301234567' ) )['so_hd'], 1 );
kiem( 'tìm theo tên gian', KHTC_PhapDanh::loc( array( 'loai' => 'thue', 'tim' => 'A1-05' ) )['so_hd'], 1 );
kiem( 'sổ NCC đếm riêng', KHTC_PhapDanh::loc( array( 'loai' => 'ncc' ) )['so_hd'], 1 );
kiem( 'giá trị có sẵn để lọc', KHTC_PhapDanh::gia_tri_co( 'khu_vuc', 'thue' ), array( 'HCM', 'HN' ) );
kiem( 'cột lạ bị từ chối', KHTC_PhapDanh::gia_tri_co( 'doi_tac', 'thue' ), array() );

// --------------------------------------------------- doanh thu chia sẻ
$ky = array( '2026-08-01', '2026-08-31' );
KHTC_HoaDonRa::them( array( 'ngay' => '10/08/2026', 'so_hd' => 'R1', 'khach' => 'Khach', 'chua_vat' => '100.000.000', 'thue_suat' => '8', 'ma_diem' => 'KVC-CRESCENT' ) );
KHTC_HoaDonRa::them( array( 'ngay' => '20/08/2026', 'so_hd' => 'R2', 'khach' => 'Khach', 'chua_vat' => '40.000.000', 'thue_suat' => '8', 'ma_diem' => 'KVC-CRESCENT' ) );
KHTC_HoaDonRa::them( array( 'ngay' => '15/08/2026', 'so_hd' => 'R3', 'khach' => 'Khach', 'chua_vat' => '60.000.000', 'thue_suat' => '8', 'ma_diem' => 'MTD-AEON' ) );

// Mới chỉ X100 có chia sẻ (100%), mà nó chưa gắn mã điểm.
$cs = KHTC_PhapDanh::doanh_thu_chia_se( $ky[0], $ky[1] );
kiem( 'chỉ hợp đồng có đặt chia sẻ mới vào bảng', count( $cs['rows'] ), 1 );
kiem( 'hợp đồng chưa gắn mã điểm ra doanh thu 0', $cs['rows'][0]['dt'], 0 );
kiem( 'và được đếm là chưa gắn mã', $cs['chua_gan'], 1 );
kiem( 'phần chia cũng bằng 0, không đoán bừa', $cs['rows'][0]['chia'], 0 );

// Gắn chia sẻ 15% cho gian A1-05 (mã KVC-CRESCENT, doanh thu 140tr).
global $wpdb;
$wpdb->query( "UPDATE wp_khtc_hop_dong SET loai_chia_se = 'giu_tien', phan_tram = 15 WHERE id = $a" );
$wpdb->query( "UPDATE wp_khtc_hop_dong SET loai_chia_se = 'xuat_hoa_don', phan_tram = 10 WHERE id = $d" );
$cs = KHTC_PhapDanh::doanh_thu_chia_se( $ky[0], $ky[1] );
$theo = array();
foreach ( $cs['rows'] as $r ) { $theo[ (int) $r['hd']->id ] = $r; }
kiem( 'doanh thu gom đúng theo mã điểm', $theo[ $a ]['dt'], 140000000 );
kiem( 'gộp đúng 2 hoá đơn cùng mã điểm', $theo[ $a ]['so_hd'], 2 );
kiem( 'phần chia 15%', $theo[ $a ]['chia'], 21000000 );
kiem( 'phần mình giữ lại', $theo[ $a ]['giu_lai'], 119000000 );
kiem( 'mã điểm khác tính riêng', $theo[ $d ]['dt'], 60000000 );
kiem( 'phần chia 10%', $theo[ $d ]['chia'], 6000000 );
kiem( 'tổng doanh thu cộng đúng', $cs['tong_dt'], 200000000 );
kiem( 'tổng phần chia cộng đúng', $cs['tong_chia'], 27000000 );
kiem( 'chia + giữ lại = doanh thu, từng dòng', $theo[ $a ]['chia'] + $theo[ $a ]['giu_lai'], $theo[ $a ]['dt'] );

// Miễn thì doanh thu vẫn hiện nhưng phần chia bằng 0 — để còn đối chiếu.
$wpdb->query( "UPDATE wp_khtc_hop_dong SET mien = 1 WHERE id = $a" );
$cs = KHTC_PhapDanh::doanh_thu_chia_se( $ky[0], $ky[1] );
foreach ( $cs['rows'] as $r ) { if ( (int) $r['hd']->id === $a ) { $m = $r; } }
kiem( 'miễn: doanh thu vẫn hiện', $m['dt'], 140000000 );
kiem( 'miễn: phần chia về 0', $m['chia'], 0 );
kiem( 'miễn: tổng phần chia giảm tương ứng', $cs['tong_chia'], 6000000 );
$wpdb->query( "UPDATE wp_khtc_hop_dong SET mien = 0 WHERE id = $a" );

// Kỳ khác thì doanh thu bằng 0, không dính kỳ này.
kiem( 'kỳ không có hoá đơn thì chia sẻ bằng 0', KHTC_PhapDanh::doanh_thu_chia_se( '2026-07-01', '2026-07-31' )['tong_dt'], 0 );

// --------------------------------------------------------------- dán bảng
$kq = KHTC_PhapDanh::dan_hang_loat(
	'thue',
	"Gian\tBên cho thuê\tMST\n"
	. "E5-01\tCTY LOTTE\t0305550001\tHCM\tKVC-LOTTE\tThue co dinh\t22.000.000\t01/01/2026\t31/12/2026\t0\n"
	. "E5-02\tCTY LOTTE\t0305550001\tHCM\tKVC-LOTTE2\tChia se\t0\t01/01/2026\t31/12/2026\t12\n"
);
kiem( 'dán 2 hợp đồng, bỏ dòng tiêu đề', $kq['them'], 2 );
kiem( 'dán không lỗi dòng nào', $kq['loi'], array() );
$moi = KHTC_PhapDanh::loc( array( 'loai' => 'thue', 'tim' => 'E5-02' ) )['rows'][0];
kiem( 'có % chia sẻ thì tự đặt hình thức giữ tiền', $moi->loai_chia_se, 'giu_tien' );
kiem( 'và lưu đúng tỷ lệ', (float) $moi->phan_tram, 12.0 );
$moi1 = KHTC_PhapDanh::loc( array( 'loai' => 'thue', 'tim' => 'E5-01' ) )['rows'][0];
kiem( 'không có % thì không đặt chia sẻ', $moi1->loai_chia_se, '' );
kiem( 'ngày trong bảng dán đọc đúng', $moi1->ngay_het_han, '2026-12-31' );
$it = KHTC_PhapDanh::dan_hang_loat( 'thue', "ChiCoMotCot\n" );
kiem( 'dòng thiếu cột bị báo lỗi', array( $it['them'], count( $it['loi'] ) ), array( 0, 1 ) );

// ----------------------------------------------------------------- CSV
$hang = KHTC_PhapDanh::hang_csv( 'thue', KHTC_PhapDanh::loc( array( 'loai' => 'thue' ) )['rows'] );
kiem( 'CSV thuê có 16 cột', count( $hang[0] ), 16 );
kiem( 'CSV mọi dòng cùng số cột', count( array_unique( array_map( 'count', $hang ) ) ), 1 );
$hang2 = KHTC_PhapDanh::hang_csv( 'ncc', KHTC_PhapDanh::loc( array( 'loai' => 'ncc' ) )['rows'] );
kiem( 'CSV NCC có bộ cột riêng', count( $hang2[0] ), 13 );

// ---------------------------------------------------------- xoá và sao lưu
kiem( 'xoá được hợp đồng', KHTC_PhapDanh::xoa( $m61 ), true );
kiem( 'xoá xong có thể phục hồi', '' !== (string) KHTC_NhatKy::loc( array( 'viec' => 'xoa' ) )['rows'][0]->du_lieu, true );
$sl = KHTC_SaoLuu::gom();
kiem( 'sao lưu có bảng hợp đồng', isset( $sl['bang']['hop_dong'] ), true );
kiem( 'mọi bảng đều được gom', array_keys( $sl['bang'] ), KHTC_SaoLuu::bang() );

// ------------------------------------------------------ dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = array( 'loai' => 'thue', 'cs_tu' => '2026-08-01', 'cs_den' => '2026-08-31' );
$_POST = array();
$t = dung( array( 'KHTC_Trang', 'phap_danh' ) );
co( 'trang có bảng sắp hết hạn', $t, 'Sắp hết hạn trong 60 ngày' );
co( 'hiện tên gian', $t, 'A1-05' );
co( 'có bảng doanh thu chia sẻ', $t, 'Doanh thu chia sẻ' );
co( 'nêu rõ ghép theo mã điểm', $t, 'mã điểm nội bộ' );
co( 'cảnh báo hợp đồng chưa gắn mã điểm', $t, 'chưa gắn mã điểm' );
co( 'nêu rõ hết hạn là ô ngày thật', $t, 'ô ngày thật' );
kiem( 'trang đóng đủ thẻ table', substr_count( $t, '<table' ), substr_count( $t, '</table>' ) );
kiem( 'trang đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

$_GET = array( 'loai' => 'ncc' );
$t2 = dung( array( 'KHTC_Trang', 'phap_danh' ) );
co( 'sổ NCC đổi nhãn đối tác', $t2, 'Nhà cung cấp' );
co( 'sổ NCC đổi nhãn giá trị', $t2, 'Giá trị hợp đồng' );
kiem( 'sổ NCC không có bảng chia sẻ', substr_count( $t2, 'Doanh thu chia sẻ' ), 0 );
kiem( 'sổ NCC đóng đủ thẻ div', substr_count( $t2, '<div' ), substr_count( $t2, '</div>' ) );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
