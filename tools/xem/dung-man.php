<?php
/**
 * DỰNG MÀN THẬT RA TỆP HTML ĐỂ XEM TRƯỚC KHI CÀI.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CẦN — BỘ THỬ KHÔNG NHÌN ĐƯỢC MÀN HÌNH
 * =============================================================================================
 * 13/09/2026, bản 3.69.0 xanh cả 80 phép thử của màn nhân sự. Mở ra xem thì lòi ra hai lỗi mà
 * không phép thử nào bắt nổi, vì cả hai đều không phải chuyện nội dung:
 *
 *   1. Dải đếm nói *"8 người đang trôi theo cơ sở — đó là trạng thái ĐÚNG cho nhân viên quầy"*,
 *      trong khi 2 trong 8 người ấy là người hệ KHÔNG suy ra mảng. Con số không sai; CÂU CHỮ
 *      sai — màn hình đang trấn an về đúng những trường hợp cần đụng tay.
 *   2. Ô xổ bị `max-width:170px` cắt cụt đuôi, che mất đúng phần nhãn «theo cơ sở → …» vốn là
 *      lý do cái nhãn ấy tồn tại.
 *
 * Phép thử đếm chuỗi thì cả hai đều xanh: chuỗi CÓ trong HTML. Nó chỉ không đọc được, hoặc đọc
 * được mà nói sai về ai. Khoảng cách giữa "mã có mặt" và "người dùng hiểu đúng" chỉ đóng được
 * bằng cách MỞ RA NHÌN.
 *
 * ⚠️ GỌI ĐÚNG HÀM CỦA TRANG THẬT (`VHCC_TrangNS::phuc_vu()`), không chép lại giao diện. Chép ra
 *    đây là dựng một bản sao, và bản sao thì đẹp kể cả khi trang thật đã hỏng.
 *
 * Chạy: bash tools/xem/xem-man.sh     (tệp này lo phần dựng HTML)
 * =============================================================================================
 */

$goc = dirname( dirname( __DIR__ ) );
require_once $goc . '/tools/test/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
global $wpdb;

/* ---- mảng kinh doanh của từng cơ sở ---- */
foreach ( array(
	'FZ_LTVT' => 'Khu vui chơi',   'FZ_SC_VIVO_T4' => 'Khu vui chơi',
	'GO_AN_LAC' => 'Khu vui chơi', 'POSH_Q1' => 'Máy tự động',
	'JP_AEON_TP' => 'Máy tự động', 'FARM_PT' => 'Máy tự động',
) as $cs => $m ) {
	$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $cs, 'bo_phan' => $m ) );
}

/* ---- Nhân sự mẫu: MỖI DÒNG LÀ MỘT CẢNH ĐÃ TỪNG SAI, không phải dữ liệu cho đẹp ---- */
$nv = function ( $ma, $ten, $cs, $phu = '', $ql = '', $vai = 'Nhân viên', $mang = '', $bp = '' ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $cs, 'coso_phu' => $phu,
		'coso_ql' => $ql, 'vai_tro' => $vai, 'mang' => $mang, 'bo_phan' => $bp,
		'trang_thai_lam_viec' => 'Đang làm' ) );
};
$nv( 'MNNV2KVC0119', 'Nguyễn Thị Mai Anh', 'FZ_LTVT' );                       // một cơ sở, một mảng
$nv( 'MNNV2KVC0024', 'NGUYỄN HOÀNG ANH', 'FZ_SC_VIVO_T4' );
$nv( 'MNNV2KVC0036', 'TRẦN QUỐC BẢO', 'FZ_LTVT', 'POSH_Q1' );                 // 🔴 LÀM HAI MẢNG
$nv( 'MNNV2MTD0025', 'NGUYỄN THỊ MAI ANH', 'FARM_PT', 'JP_AEON_TP' );         // hai cơ sở, MỘT mảng
$nv( 'MNQL2KVC0003', 'LÊ VĂN CƯỜNG', 'FZ_LTVT', 'GO_AN_LAC', '', 'Quản lý' ); // quản lý một mảng
$nv( 'MNQL2KVC0007', 'PHẠM THU HÀ', 'FZ_LTVT', 'POSH_Q1', 'POSH_Q1', 'Cửa hàng trưởng' ); // 🔴 chỉ QL không kéo mảng
$nv( 'MNVP2CTY0001', 'HUỲNH QUANG THẮNG', '', '', '', 'Admin', 'Văn phòng', 'Tổng Giám Đốc (CEO)' );
$nv( 'MNVP2CTY0011', 'TRẦN THỊ KẾ TOÁN', '', '', '', 'Kế toán', 'Văn phòng', 'Phòng Kế Toán - Tài Chính' );
$nv( 'MNNV2XXX0099', 'VÕ MINH KHOA', 'CS_MOI_CHUA_KHAI' );                    // 🔴 cơ sở chưa khai mảng
$nv( 'MNNV2XXX0100', 'ĐẶNG VĂN LỘC', '' );                                     // 🔴 chưa gắn cơ sở

/* ---- Thẻ phiên Admin, rồi gọi ĐÚNG trang thật ---- */
$tok = VHCC_Auth::phat_token( 'Huỳnh Quang Thắng', 'Admin', '', 'MNVP2CTY0001' );
$_GET = array(); $_POST = array();
$_COOKIE = array( VHCC_Web::COOKIE => $tok );
ob_start(); VHCC_TrangNS::phuc_vu(); $h = ob_get_clean();

$ra = isset( $argv[1] ) ? $argv[1] : ( sys_get_temp_dir() . '/man-nhan-su.html' );
file_put_contents( $ra, $h );
echo $ra . "\n";
