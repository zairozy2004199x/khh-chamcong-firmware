<?php
/**
 * Kiểm dữ liệu mẫu.
 *
 *   php tools/kh-tai-chinh/tests/kiem-mau.php
 *
 * Điều phải đúng tuyệt đối: dữ liệu mẫu KHÔNG BAO GIỜ lẫn vào sổ thật, và xoá
 * mẫu KHÔNG BAO GIỜ chạm vào dòng người dùng tự nhập. Một hoá đơn mẫu lọt vào
 * tờ khai GTGT đem nộp là chuyện không sửa lại được.
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
function dem( $bang ) {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM wp_khtc_$bang WHERE cty = '" . KHTC_Cty::dang_chon() . "'" );
}

KHTC_Cty::chon( 'kh_moi' );

// ------------------------------------------------------------ sổ trắng
kiem( 'sổ trắng thì không có dữ liệu thật', KHTC_Mau::du_lieu_that(), array() );
kiem( 'và chưa có dòng mẫu nào', KHTC_Mau::dem_mau(), 0 );

$so = KHTC_Mau::nap();
kiem( 'nạp được', is_int( $so ) && $so > 0, true );
kiem( 'hai tài khoản ngân hàng', dem( 'ngan_hang' ), 2 );
kiem( '13 tháng sao kê hai tài khoản', dem( 'giao_dich' ), 13 * 4 + 3 + 2 );
kiem( 'năm hoá đơn đầu ra', dem( 'hd_ra' ), 5 );
kiem( 'bốn hoá đơn đầu vào', dem( 'hd_vao' ), 4 );
kiem( 'sáu khoản chi phí', dem( 'chi_phi' ), 6 );
kiem( 'một đợt đối soát', dem( 'doi_soat' ), 1 );
kiem( 'năm hợp đồng', dem( 'hop_dong' ), 5 );
kiem( 'ba hồ sơ', dem( 'ho_so' ), 3 );
kiem( 'số dòng đếm được khớp số báo về', KHTC_Mau::dem_mau(), $so );
kiem( 'sau khi nạp vẫn không có dữ liệu "thật" nào', KHTC_Mau::du_lieu_that(), array() );

// Cả lượt nạp chỉ để lại MỘT dòng nhật ký, không phải vài trăm.
kiem( 'nạp chỉ ghi một dòng nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'nap' ) )['so_dong'], 1 );

// ------------------------------- số phải ĂN KHỚP để mọi màn hình ra kết quả
list( $k1, $k2 ) = KHTC_BaoCao::bien_thang( current_time( 'Y-m' ) );
$ky = array( 'tu' => $k1, 'den' => $k2 );

kiem( 'hoá đơn đầu ra tháng này có số', KHTC_HoaDonRa::loc( $ky )['so_hd'] > 0, true );
kiem( 'hoá đơn đầu vào tháng này có số', KHTC_HoaDonVao::loc( $ky )['so_hd'] > 0, true );
kiem( 'tờ khai ra số phải nộp hoặc chuyển kỳ', KHTC_HoaDonVao::to_khai( $k1, $k2 )['vat_ra'] > 0, true );

// Đối soát chi phí phải ghép được — chứng từ và sao kê dựng để khớp nhau.
$ds = KHTC_ChiPhi::doi_soat( $k1, $k2, KHTC_NganHang::ds()[1]->id );
kiem( 'đối soát chi phí ghép được ít nhất 2 khoản', count( $ds['khop'] ) >= 2, true );

// Đối soát cổng: cố tình một dòng chưa về, để người dùng thấy nhóm "Thiếu".
$dot = KHTC_DoiSoat::ds_dot()[0];
$kq  = KHTC_DoiSoat::ket_qua( $dot->id );
kiem( 'đối soát cổng có dòng khớp', count( $kq['khop'] ) >= 1, true );
kiem( 'và cố tình để một dòng chưa về', count( $kq['thieu'] ), 1 );

// Hồ sơ nối được với bút toán.
kiem( 'hồ sơ nối được ít nhất 2 chứng từ', KHTC_HoSo::loc( $ky )['da_noi'] >= 2, true );

// Hợp đồng: có cái sắp hết hạn và cái đã quá hạn, để bảng cảnh báo có gì mà hiện.
$het = KHTC_PhapDanh::sap_het_han( 'thue' );
kiem( 'có hợp đồng sắp hết hạn', count( $het ) >= 2, true );
kiem( 'trong đó có cái đã quá hạn', $het[0]->_con_ngay < 0, true );

// Doanh thu chia sẻ ghép được qua mã điểm.
$cs = KHTC_PhapDanh::doanh_thu_chia_se( $k1, $k2 );
kiem( 'doanh thu chia sẻ ra số thật', $cs['tong_chia'] > 0, true );

// Bảng xu hướng 12 tháng phải có số ở mọi tháng, không phải chỉ tháng này.
$ch = KHTC_BaoCao::chuoi_thang( $k2, 12 );
$co_so = 0;
foreach ( $ch as $x ) { if ( $x['thu'] > 0 ) { $co_so++; } }
kiem( 'cả 12 tháng đều có dòng tiền', $co_so, 12 );

// ------------------------------------------ không nạp đè lên sổ có dữ liệu
kiem( 'nạp lần hai bị chặn', is_wp_error( KHTC_Mau::nap() ), true );

// Pháp nhân khác vẫn trắng và nạp được.
KHTC_Cty::chon( 'kh_cu' );
kiem( 'pháp nhân khác chưa có mẫu', KHTC_Mau::dem_mau(), 0 );
kiem( 'và nạp được bình thường', is_int( KHTC_Mau::nap() ), true );
KHTC_Cty::chon( 'kh_moi' );

// Sổ đang khoá thì không nạp.
KHTC_Mau::xoa();
KHTC_Khoa::dat( '31/12/2030' );
kiem( 'sổ đang khoá thì không nạp mẫu', is_wp_error( KHTC_Mau::nap() ), true );
KHTC_Khoa::dat( '' );

// ------------------------------- xoá mẫu KHÔNG được đụng dòng người dùng nhập
kiem( 'nạp lại được sau khi xoá', is_int( KHTC_Mau::nap() ), true );
$nh_that = KHTC_NganHang::them( array( 'ten' => 'TK THAT CUA TOI', 'so_du_dau' => 1000000, 'ngay_dau' => $k1 ) );
$hd_that = KHTC_HoaDonRa::them( array( 'ngay' => mysql2date( 'd/m/Y', $k1 ), 'so_hd' => 'THAT-001', 'khach' => 'Khach that', 'chua_vat' => '5.000.000', 'thue_suat' => '8' ) );
KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh_that, 'ngay' => mysql2date( 'd/m/Y', $k1 ), 'dien_giai' => 'Thu that', 'so_tien' => '2.000.000', 'loai' => 'thu' ) );

$that = KHTC_Mau::du_lieu_that();
kiem( 'nhận ra có dữ liệu thật xen vào', isset( $that['ngan_hang'] ) && 1 === $that['ngan_hang'], true );
kiem( 'và đếm đúng hoá đơn thật', $that['hd_ra'], 1 );

$truoc_nh = dem( 'ngan_hang' );
KHTC_Mau::xoa();
kiem( 'xoá mẫu xong tài khoản thật vẫn còn', dem( 'ngan_hang' ), 1 );
kiem( 'và đúng là tài khoản của người dùng', KHTC_NganHang::ds()[0]->ten, 'TK THAT CUA TOI' );
kiem( 'hoá đơn thật vẫn còn', dem( 'hd_ra' ), 1 );
kiem( 'giao dịch thật vẫn còn', dem( 'giao_dich' ), 1 );
kiem( 'không còn dòng mẫu nào', KHTC_Mau::dem_mau(), 0 );
kiem( 'xoá mẫu có vào nhật ký', KHTC_NhatKy::loc( array( 'viec' => 'xoa' ) )['so_dong'] >= 1, true );

// Giờ sổ đã có dữ liệu thật nên không nạp mẫu được nữa.
kiem( 'có dữ liệu thật thì không nạp mẫu', is_wp_error( KHTC_Mau::nap() ), true );

// ------------------------------------------------------ dựng thật màn hình
$GLOBALS['khtc_qv'] = array();
$_GET  = array();
$_POST = array();
$t = dung( array( 'KHTC_Trang', 'sao_luu' ) );
co( 'trang sao lưu có mục dữ liệu mẫu', $t, 'Dữ liệu mẫu để chạy thử' );
co( 'và giải thích vì sao chặn', $t, 'không sửa lại được' );
kiem( 'trang đóng đủ thẻ div', substr_count( $t, '<div' ), substr_count( $t, '</div>' ) );

printf( "%d kiểm tra đạt, %d lỗi  (%d câu SQL)\n", $dat, count( $hong ), $GLOBALS['wpdb']->so_cau );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
