<?php
/**
 * Kiểm hoán đổi pháp nhân — sửa bản dữ liệu 1.2–1.3 gán ngược KH Cũ / KH Mới.
 *
 *   php tools/kh-tai-chinh/tests/kiem-hoan-doi.php
 *
 * Phép kiểm cốt lõi: chỉ đổi NHÃN, không mất không thêm dòng nào; danh mục
 * điểm đứng yên trừ khi bảo kèm; bấm hai lần là về như cũ.
 */
error_reporting( E_ALL );
set_error_handler( function ( $n, $s, $f, $l ) { throw new ErrorException( $s, 0, $n, $f, $l ); } );
require __DIR__ . '/gia-lap-wp.php';
$dat = 0; $hong = array();
function kiem( $ten, $that, $mong ) { global $dat, $hong; if ( $that === $mong ) { $dat++; return; } $hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) ); }
function dung( $ham ) { ob_start(); try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; } return ob_get_clean(); }

// Dựng đúng hình dạng lỗi: sổ KH989 nằm dưới nhãn KH Mới, danh mục KH989 nạp đúng nhãn KH Cũ.
KHTC_Cty::chon( 'kh_moi' );
$nh989 = KHTC_NganHang::them( array( 'ten' => 'QR 11521268 - MB', 'so_tk' => '11521268', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
KHTC_GiaoDich::dan_hang_loat( $nh989, "23/09/2026\tTHANH TOAN QR PAY\t50.000\tthu\tVN4\tGHOSTDN1\n" );
$dot989 = KHTC_DoiSoat::tao_dot( array( 'ten' => 'VNPay KH989 tháng 8', 'kenh' => 'vnpay', 'tu' => '2026-08-01', 'den' => '2026-08-31', 'ngan_hang_id' => $nh989 ) );
KHTC_HoaDonRa::them( array( 'ngay' => '31/08/2026', 'so_hd' => '2575', 'co_vat' => '1.000.000' ) );
KHTC_DonApp::nap( array( array( 'ma_don' => '141819', 'ngay' => '2026-09-23', 'co_so' => 'X', 'tien' => 1 ) ) );
update_option( 'khtc_dm_bo_phan_kh_moi', array( 'Bộ phận của KH989' ) );
KHTC_Cty::chon( 'kh_cu' );
$nh705 = KHTC_NganHang::them( array( 'ten' => 'QR 02865168 - MB', 'so_tk' => '02865168', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
KHTC_GiaoDich::dan_hang_loat( $nh705, "23/09/2026\tQR\t80.000\tthu\tVN5\tAAA\n23/09/2026\tQR\t20.000\tthu\tVN6\tBBB\n" );
// Danh mục theo đúng nhãn: GHOSTDN1 thuộc KH989 → KH Cũ
KHTC_Diem::them( array( 'ma_cua_hang' => 'GHOSTDN1', 'ten_diem' => 'GHOST BRIDE MEGA ĐÀ NẴNG' ) );

$g0 = KHTC_SinhHD::gom( array( 'tu' => '2026-09-23', 'den' => '2026-09-23', 'nh' => array( $nh705 ) ) );
kiem( 'triệu chứng: danh mục ở KH Cũ nhưng tiền GHOSTDN1 nằm ở KH Mới → KH Cũ không thấy nó', $g0['tong'] + $g0['tong_la'], 100000 );

$tt = KHTC_SaoLuu::tom_tat_cty();
kiem( 'bảng soát: KH Mới đang giữ 11521268 (sai)', $tt['kh_moi']['tai_khoan'], array( 'QR 11521268 - MB' ) );
kiem( 'bảng soát: đếm hoá đơn KH Mới', $tt['kh_moi']['so']['hd_ra'], 1 );
kiem( 'bảng soát: danh mục KH Cũ 1 điểm', $tt['kh_cu']['so']['diem'], 1 );

global $wpdb;
$dem = function () use ( $wpdb ) { $r = array(); foreach ( array( 'ngan_hang', 'giao_dich', 'doi_soat', 'hd_ra', 'don_app', 'diem' ) as $b ) { $r[ $b ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( $b ) ); } return $r; };
$truoc = $dem();
$doi = KHTC_SaoLuu::hoan_doi_cty();
kiem( 'đổi đủ các bảng có dòng', array( $doi['ngan_hang'], $doi['giao_dich'], $doi['doi_soat'], $doi['hd_ra'], $doi['don_app'] ), array( 2, 3, 1, 1, 1 ) );
kiem( 'không mất không thêm dòng nào', $dem(), $truoc );
$tt2 = KHTC_SaoLuu::tom_tat_cty();
kiem( 'sau đổi: KH Cũ giữ 11521268', $tt2['kh_cu']['tai_khoan'], array( 'QR 11521268 - MB' ) );
kiem( 'sau đổi: KH Mới giữ 02865168', $tt2['kh_moi']['tai_khoan'], array( 'QR 02865168 - MB' ) );
kiem( 'danh mục điểm đứng yên ở KH Cũ', $tt2['kh_cu']['so']['diem'], 1 );
kiem( 'đợt KH989 về KH Cũ', KHTC_DoiSoat::mot_dot( $dot989 )->cty, 'kh_cu' );
kiem( 'đơn app theo sổ về KH Cũ', KHTC_DonApp::dem( 'kh_cu' ), 1 );
kiem( 'danh mục chi phí theo sổ', get_option( 'khtc_dm_bo_phan_kh_cu' ), array( 'Bộ phận của KH989' ) );
kiem( 'và bên kia trống', get_option( 'khtc_dm_bo_phan_kh_moi', null ), null );
// Giờ sổ và danh mục khớp: GHOSTDN1 gom được ở KH Cũ
KHTC_Cty::chon( 'kh_cu' );
$g1 = KHTC_SinhHD::gom( array( 'tu' => '2026-09-23', 'den' => '2026-09-23', 'nh' => array( $nh989 ) ) );
kiem( 'sau đổi: GHOSTDN1 gom được thành hoá đơn', $g1['diem'][0]['ten_diem'] ?? null, 'GHOST BRIDE MEGA ĐÀ NẴNG' );
kiem( 'số hoá đơn kế tiếp theo đúng sổ KH989', KHTC_SinhHD::so_tiep(), 2576 );
// Bấm lần hai → về như cũ
KHTC_SaoLuu::hoan_doi_cty();
kiem( 'bấm hai lần về như cũ', KHTC_SaoLuu::tom_tat_cty()['kh_moi']['tai_khoan'], array( 'QR 11521268 - MB' ) );
// Kèm danh mục
KHTC_SaoLuu::hoan_doi_cty( true );
kiem( 'kèm danh mục: điểm đi theo', KHTC_SaoLuu::tom_tat_cty()['kh_moi']['so']['diem'], 1 );
KHTC_SaoLuu::hoan_doi_cty( true );
// Màn hình
$_POST = array(); $_GET = array();
$h = dung( fn() => KHTC_Trang::sao_luu() );
kiem( 'màn hình Sao lưu có bảng hai pháp nhân', false !== strpos( $h, 'Hai pháp nhân đang giữ gì' ), true );
kiem( 'và nút hoán đổi', false !== strpos( $h, 'name="khtc_hoan_doi"' ), true );
$_POST = array( 'khtc_hoan_doi' => 1 );
$h2 = dung( fn() => KHTC_Trang::sao_luu() );
kiem( 'bấm nút: báo đã đổi', false !== strpos( $h2, 'Đã hoán đổi KH Cũ ↔ KH Mới' ), true );
kiem( 'và dữ liệu đổi thật', KHTC_SaoLuu::tom_tat_cty()['kh_cu']['tai_khoan'], array( 'QR 11521268 - MB' ) );
$_POST = array();

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
