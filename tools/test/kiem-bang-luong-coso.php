<?php
/**
 * KIỂM BẢNG LƯƠNG CƠ SỞ — dựng lại đúng mấy con số trong file kế toán đang dùng.
 *
 * =============================================================================================
 * Anh Thắng 15/09/2026 gửi `LƯƠNG CƠ SỞ - T08.2026 - Tháng.xlsx`: *"mỗi cơ sở sẽ xuất bảng công
 * giờ ra theo mẫu file như này"*.
 *
 * 🔴 BÀI NÀY KHÔNG TỰ NGHĨ RA SỐ ĐỂ THỬ. Mọi con số dưới đây LẤY THẲNG TỪ FILE ẤY — tên thật,
 *    giờ thật, đơn giá thật, và kết quả thật mà kế toán đã trả tháng 8. Thử bằng số mình tự bịa
 *    thì chỉ chứng minh mã khớp với chính nó; thử bằng số kế toán đã trả thì mới biết mã có
 *    thay được cái file làm tay hay không.
 *
 * Chạy: php tools/test/kiem-bang-luong-coso.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . ( is_scalar( $mong ) ? $mong : wp_json_encode( $mong ) ) . ')',
		$mong === $thuc, is_scalar( $thuc ) ? $thuc : wp_json_encode( $thuc ) );
}
function ket_luan() {
	global $dat, $truot;
	echo "\n";
	if ( $truot ) {
		echo '✗ TRƯỢT ' . count( $truot ) . ' / ' . ( $dat + count( $truot ) ) . ":\n";
		foreach ( $truot as $x ) { echo '    · ' . $x . "\n"; }
		exit( 1 );
	}
	echo "✓ ĐẠT — $dat phép: bảng lương cơ sở ra đúng số kế toán đã trả, và chưa khai giá thì nói ra.\n";
}

global $wpdb;

$U_KT = array( 'name' => 'Chị Kế Toán', 'role' => 'Kế toán', 'coso' => '' );
$U_CHT = array( 'name' => 'Trưởng Cửa Hàng', 'role' => 'Cửa hàng trưởng', 'coso' => 'AEON_BT' );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. SỔ ĐƠN GIÁ — BA TẦNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* 🔴 CHƯA KHAI GÌ THÌ TRẢ 0 VÀ NÓI LÀ CHƯA KHAI. Đây là phép quan trọng nhất của cả bài: nếu
   `tra()` lặng lẽ trả một con số nào đó, mọi phép còn lại vẫn xanh mà lương thì sai. */
$g0 = VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu', 'X1' );
teq( '🔴 chưa khai đơn giá -> trả 0', 0.0, $g0['gia'] );
teq( '🔴 và NÓI RÕ là chưa khai, không im lặng', 'khong', $g0['tu'] );

/* Tầng chung — cả chuỗi, theo chức vụ. Số lấy từ khối Aeon Tân Phú trong file. */
$r = VHCC_GiaGio::dat_chung( $U_KT, array( 'TN' => 24000, 'NV' => 22000, 'LT' => 24000 ) );
t( 'kế toán khai được bảng đơn giá chung', ! empty( $r['ok'] ), $r );
teq( 'TN ăn 24.000 theo bảng chung', 24000.0, VHCC_GiaGio::tra( 'AEON_TP', 'TN' )['gia'] );
teq( 'và nói rõ giá đến từ bảng chung', 'chung', VHCC_GiaGio::tra( 'AEON_TP', 'TN' )['tu'] );

/* Tầng cơ sở — Aeon Bình Tân: Lái Tàu 23.000, Lơ Tàu 21.000 (đúng file). */
$r = VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000, 'Lơ Tàu' => 21000 ) );
t( 'khai được đơn giá riêng của một cơ sở', ! empty( $r['ok'] ), $r );
teq( 'Lái Tàu ở Bình Tân: 23.000', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );
teq( 'Lơ Tàu ở Bình Tân: 21.000', 21000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lơ Tàu' )['gia'] );

/* 🔴 CÙNG CHỨC VỤ, KHÁC CƠ SỞ, KHÁC GIÁ — chính là lý do sổ phải có tầng cơ sở.
   Trong file: "NV" ở Aeon Tân Phú 22.000, ở Lotte Gò Vấp 23.000. */
VHCC_GiaGio::dat_coso( $U_KT, 'LOTTE_GV', array( 'NV' => 23000 ) );
teq( '🔴 NV ở Tân Phú vẫn 22.000 (bảng chung)', 22000.0, VHCC_GiaGio::tra( 'AEON_TP', 'NV' )['gia'] );
teq( '🔴 NV ở Gò Vấp là 23.000 (bảng cơ sở đè lên)', 23000.0, VHCC_GiaGio::tra( 'LOTTE_GV', 'NV' )['gia'] );

/* 🔴 NGƯỜI ĐÈ LÊN CƠ SỞ — Lotte Gò Vấp có ba "NV" ăn 23.000, riêng Bùi Xuân Thuận 25.000. */
VHCC_GiaGio::dat_nguoi( $U_KT, 'GV_THUAN', array( 'NV' => 25000 ) );
teq( '🔴 người khai riêng thì ăn giá riêng', 25000.0,
	VHCC_GiaGio::tra( 'LOTTE_GV', 'NV', 'GV_THUAN' )['gia'] );
teq( 'và nói rõ giá đến từ khai riêng', 'nguoi',
	VHCC_GiaGio::tra( 'LOTTE_GV', 'NV', 'GV_THUAN' )['tu'] );
teq( 'người khác cùng cơ sở KHÔNG bị ảnh hưởng', 23000.0,
	VHCC_GiaGio::tra( 'LOTTE_GV', 'NV', 'GV_TIEN' )['gia'] );

/* ⚠️ KHOÁ BỎ DẤU, BỎ HOA THƯỜNG. Hồ sơ gõ tay nên cùng một việc có đủ kiểu viết. */
teq( 'gõ "LAI TAU" vẫn tra ra Lái Tàu', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'LAI TAU' )['gia'] );
teq( 'gõ "lái tàu " (thừa khoảng trắng) cũng vậy', 23000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'lái tàu ' )['gia'] );
teq( 'mã cơ sở có tiền tố CS_ vẫn tra đúng sổ', 23000.0,
	VHCC_GiaGio::tra( 'CS_AEON_BT', 'Lái Tàu' )['gia'] );

/* 🔴 Ô ĐỂ TRỐNG = XOÁ KHỎI SỔ, KHÔNG PHẢI GHI 0. Ghi 0 thì tầng dưới không đỡ được nữa mà màn
   thì trông như đã xoá — người khai đinh ninh đã bỏ khai riêng, thực ra vừa khoá luôn. */
VHCC_GiaGio::dat_nguoi( $U_KT, 'GV_THUAN', array( 'NV' => '' ) );
teq( '🔴 xoá khai riêng thì rơi về giá của cơ sở', 23000.0,
	VHCC_GiaGio::tra( 'LOTTE_GV', 'NV', 'GV_THUAN' )['gia'] );
teq( 'và nguồn giá nay là cơ sở', 'coso', VHCC_GiaGio::tra( 'LOTTE_GV', 'NV', 'GV_THUAN' )['tu'] );

/* 🔴 SỐ GÕ BẬY BỊ CHỐI, KHÔNG GHI BỪA. Gõ dư một số 0 là nhân mười lương cả cơ sở. */
$r = VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 0 ) );
t( '🔴 đơn giá 0 bị chối', empty( $r['ok'] ), $r );
$r = VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => -5000 ) );
t( '🔴 đơn giá âm bị chối', empty( $r['ok'] ), $r );
$r = VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000000 ) );
t( '🔴 đơn giá vô lý (23 triệu/giờ — gõ dư số 0) bị chối', empty( $r['ok'] ), $r );
teq( 'và sổ vẫn giữ nguyên giá cũ', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );

/* 🔴 CỬA HÀNG TRƯỞNG KHÔNG KHAI ĐƯỢC ĐƠN GIÁ. Họ xuất bảng, không định giá. */
$r = VHCC_GiaGio::dat_coso( $U_CHT, 'AEON_BT', array( 'Lái Tàu' => 99000 ) );
t( '🔴 cửa hàng trưởng KHÔNG khai được đơn giá', empty( $r['ok'] ), $r );
teq( 'và giá không đổi', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. DỰNG BẢNG — ĐỐI CHIẾU VỚI CHÍNH SỐ KẾ TOÁN ĐÃ TRẢ THÁNG 8/2026
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* Gieo giờ sao cho tổng ra ĐÚNG số giờ trong file, rồi soi tiền.
   Lâm Tú Lanh (Aeon Bình Tân): Lái Tàu 19,2h × 23.000 = 441.600 · Lơ Tàu 49,15h × 21.000 =
   1.032.150 — hai dòng, hai giá, cùng một người. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BT_LANH', 'ho_ten' => 'Lâm Tú Lanh',
	'cccd' => '094301002660', 'cua_hang' => 'AEON_BT', 'chuc_vu' => 'Lái Tàu', 'vai_tro' => 'Nhân viên' ) );

$gieo = function ( $ma, $cs, $ngay, $vao_g, $so_phut, $hau_to = '' ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'ma_nv' => $ma, 'ho_ten' => '', 'coso' => $cs, 'ngay' => $ngay,
		'gio_vao_giay' => $vao_g, 'gio_ra_giay' => $vao_g + (int) round( $so_phut * 60 ),
		'hau_to' => $hau_to, 'nguon' => 'may' ) );
};
/* 19,2 giờ = 1152 phút, chia hai ngày cho giống thật. */
$gieo( 'BT_LANH', 'AEON_BT', '2026-08-01', 8 * 3600, 600 );
$gieo( 'BT_LANH', 'AEON_BT', '2026-08-02', 8 * 3600, 552 );
/* 49,15 giờ = 2949 phút, dòng Lơ Tàu (hậu tố TT). */
$gieo( 'BT_LANH', 'AEON_BT', '2026-08-03', 8 * 3600, 1500, 'TT' );
$gieo( 'BT_LANH', 'AEON_BT', '2026-08-04', 8 * 3600, 1449, 'TT' );

/* Hậu tố TT vốn mang tên "Thu tiền" — ở cơ sở này nó là Lơ Tàu, nên khai giá cho đúng cái tên
   mà bảng in ra. Đây chính là lý do màn khai phải liệt kê chức vụ ĐANG DÙNG THẬT của tháng. */
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT',
	array( 'Lái Tàu' => 23000, 'Lơ Tàu' => 21000, 'Thu tiền' => 21000 ) );

$b = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
t( 'dựng được bảng', ! empty( $b['ok'] ), $b );
teq( '🔴 một người hai chức vụ ra HAI dòng', 2, count( $b['dong'] ) );

$d_chinh = null; $d_tt = null;
foreach ( $b['dong'] as $d ) {
	if ( '' === $d['hauTo'] ) { $d_chinh = $d; } else { $d_tt = $d; }
}
teq( '🔴 dòng ca chính: 19,2 giờ đúng như file', 19.2, $d_chinh['gio'] );
teq( 'ăn đơn giá Lái Tàu 23.000', 23000.0, $d_chinh['gia'] );
teq( '🔴 lương chính 441.600đ — đúng số kế toán đã trả', 441600.0, $d_chinh['luongChinh'] );
teq( '🔴 dòng thứ hai: 49,15 giờ', 49.15, $d_tt['gio'] );
teq( 'ăn đơn giá 21.000, KHÔNG phải 23.000 của dòng kia', 21000.0, $d_tt['gia'] );
teq( '🔴 lương chính 1.032.150đ — đúng số kế toán đã trả', 1032150.0, $d_tt['luongChinh'] );
teq( 'tên lấy từ hồ sơ', 'Lâm Tú Lanh', $d_chinh['ten'] );
teq( 'và số căn cước cũng vậy', '094301002660', $d_chinh['cccd'] );
teq( 'hai dòng của cùng một người đứng liền nhau', 1, $d_chinh['stt'] );

/* ---- Lối THEO THÁNG: Trần Ngọc Minh Truyền, 4.000.000 × 26/26 = 4.000.000 ---- */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TP_TRUYEN',
	'ho_ten' => 'Trần Ngọc Minh Truyền', 'cua_hang' => 'AEON_TP', 'chuc_vu' => 'NV',
	'luong_co_ban' => 4000000, 'vai_tro' => 'Nhân viên' ) );
for ( $i = 1; $i <= 26; $i++ ) {
	$gieo( 'TP_TRUYEN', 'AEON_TP', sprintf( '2026-08-%02d', $i ), 8 * 3600, 480 );
}
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array( 'ngayCongThang' => 26 ), $U_KT );

$b2 = VHCC_BangLuong::dung( 'AEON_TP', '2026-08' );
$d2 = $b2['dong'][0];
teq( '🔴 người có lương cơ bản thì tính THEO THÁNG', 'thang', $d2['cheDo'] );
teq( 'lương cơ bản vào đúng cột của nó', 4000000.0, $d2['luongCb'] );
teq( 'số công yêu cầu lấy từ cấu hình', 26.0, $d2['congYc'] );
teq( 'số công thực = số ngày có chấm', 26, $d2['congThuc'] );
teq( '🔴 lương chính 4.000.000đ — đúng số kế toán đã trả', 4000000.0, $d2['luongChinh'] );
teq( '🔴 lối theo tháng KHÔNG dùng đơn giá giờ', null, $d2['gia'] );

/* 🔴 CHƯA KHAI SỐ CÔNG CHUẨN THÌ KHÔNG ĐOÁN MẪU SỐ — cùng luật với khối Văn phòng.
   Đoán 26 là sai tiền của mọi người ăn lương tháng cùng lúc, mà bảng vẫn có số. */
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array(), $U_KT );
$b3 = VHCC_BangLuong::dung( 'AEON_TP', '2026-08' );
teq( '🔴 chưa khai số công chuẩn -> lương chính để TRỐNG, không đoán', null, $b3['dong'][0]['luongChinh'] );
t( '🔴 và bảng NÓI RA là thiếu số công chuẩn', ! empty( $b3['thieu']['congChuan'] ), $b3['thieu'] );
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array( 'ngayCongThang' => 26 ), $U_KT );

/* ---- 🔴 CHƯA KHAI ĐƠN GIÁ: ĐỂ TRỐNG VÀ ĐẾM, TUYỆT ĐỐI KHÔNG NHÂN VỚI 0 ---- */
/* ⚠️ MÃ CƠ SỞ KHÔNG ĐƯỢC BẮT ĐẦU BẰNG `CS_` TRONG BÀI THỬ. `bang_cong_va_luong()` và
   `VHCC_BangLuong::dung()` đều CẮT tiền tố ấy (mã cũ bên Sheets mang nó), nên đặt tên
   `CS_LA` thì hàng gieo vào cơ sở `CS_LA` còn hàm đi tìm cơ sở `LA` — bảng rỗng, và bài
   thử đỏ vì lý do chẳng liên quan gì tới thứ nó định canh. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'LA_AI', 'ho_ten' => 'Người Chưa Khai Giá',
	'cua_hang' => 'KHO_LA', 'chuc_vu' => 'Việc Chưa Có Trong Sổ', 'vai_tro' => 'Nhân viên' ) );
$gieo( 'LA_AI', 'KHO_LA', '2026-08-05', 8 * 3600, 480 );
$b4 = VHCC_BangLuong::dung( 'KHO_LA', '2026-08' );
teq( 'vẫn đếm đúng giờ', 8.0, $b4['dong'][0]['gio'] );
teq( '🔴 chưa khai giá -> lương chính để TRỐNG (null), KHÔNG phải 0đ', null, $b4['dong'][0]['luongChinh'] );
teq( 'và nói rõ vì sao', 'khong', $b4['dong'][0]['giaTu'] );
teq( '🔴 bảng ĐẾM ĐƯỢC có bao nhiêu dòng chưa khai giá', 1, $b4['thieu']['gia'] );
teq( 'tổng lương chính KHÔNG cộng dòng chưa khai vào', 0.0, $b4['tong']['luongChinh'] );

/* ---- 🔴 THIẾU MỘT ĐẦU GIỜ: KHÔNG TÍNH, VÀ BÁO ---- */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'LA_AI', 'ho_ten' => '',
	'coso' => 'KHO_LA', 'ngay' => '2026-08-06', 'gio_vao_giay' => 8 * 3600,
	'gio_ra_giay' => null, 'hau_to' => '', 'nguon' => 'may' ) );
$b5 = VHCC_BangLuong::dung( 'KHO_LA', '2026-08' );
teq( '🔴 ngày quên chấm ra KHÔNG cộng phút nào vào', 8.0, $b5['dong'][0]['gio'] );
teq( '🔴 nhưng bảng ĐẾM và nói ra', 1, $b5['thieu']['gio'] );

/* ---- Người ăn lương tháng mà có thêm dòng hậu tố ---- */
$gieo( 'TP_TRUYEN', 'AEON_TP', '2026-08-27', 22 * 3600, 300, 'CD' );
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_TP', array( 'Ca đêm / tăng ca' => 30000 ) );
$b6 = VHCC_BangLuong::dung( 'AEON_TP', '2026-08' );
teq( 'ra hai dòng', 2, count( $b6['dong'] ) );
$d_cd = null; $d_ch = null;
foreach ( $b6['dong'] as $d ) { if ( 'CD' === $d['hauTo'] ) { $d_cd = $d; } else { $d_ch = $d; } }
teq( '🔴 dòng ca chính vẫn ăn trọn lương tháng', 4000000.0, $d_ch['luongChinh'] );
teq( '🔴 dòng ca đêm tính THEO GIỜ, không nhân lương tháng lần hai', 'gio', $d_cd['cheDo'] );
teq( 'ca đêm 5 giờ × 30.000 = 150.000', 150000.0, $d_cd['luongChinh'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. TỆP XUẤT RA — ĐÚNG DẠNG FILE KẾ TOÁN, VÀ MỞ ĐƯỢC
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* Thêm vào chính cơ sở này một người CHƯA KHAI ĐƠN GIÁ — để soi cái dòng nguy hiểm nhất của
   tệp xuất ra: dòng mà hệ không ra được tiền. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BT_NOGIA',
	'ho_ten' => 'Người Chưa Khai Giá', 'cccd' => '079304016348', 'cua_hang' => 'AEON_BT',
	'chuc_vu' => 'Việc Chưa Có Trong Sổ', 'vai_tro' => 'Nhân viên' ) );
$gieo( 'BT_NOGIA', 'AEON_BT', '2026-08-05', 8 * 3600, 480 );

$x = VHCC_BangLuong::to_xlsx( 'AEON_BT', '2026-08', 'TRAIN AEON BÌNH TÂN' );
t( 'dựng được tờ xuất', ! empty( $x['ok'] ), $x );
$to = $x['to'][0];
$h  = $to['hang'];

/* Lấy giá trị thô của một ô, bất kể nó được bọc kiểu gì. */
$gv = function ( $dong, $cot ) use ( $h ) {
	$o = isset( $h[ $dong ][ $cot ] ) ? $h[ $dong ][ $cot ] : null;
	if ( is_array( $o ) && isset( $o['ct'] ) ) { return '=' . $o['ct']; }
	if ( is_array( $o ) && array_key_exists( 'v', $o ) ) { $o = $o['v']; }
	if ( is_array( $o ) && isset( $o['chu'] ) ) { return (string) $o['chu']; }
	return $o;
};

teq( 'dòng 1 là tên công ty', 'K&H CO. LTD', $gv( 0, 0 ) );
teq( 'có tựa đúng nguyên văn file', 'BẢNG TÍNH - THANH TOÁN TIỀN LƯƠNG', $gv( 3, 0 ) );
teq( '🔴 ngày là NGÀY CUỐI THÁNG, không phải hôm nay', '31/08/2026', $gv( 4, 0 ) );
teq( 'và nói rõ cơ sở nào', 'TRAIN AEON BÌNH TÂN', $gv( 5, 0 ) );

/* Hai dòng tiêu đề, đúng vị trí cột như file — kế toán đối chiếu bằng mắt theo vị trí. */
teq( 'A7 = STT',  'STT', $gv( 6, 0 ) );
teq( 'C7 = CCCD', 'CCCD', $gv( 6, 2 ) );
teq( 'H7 = Tiền/h', 'Tiền/h', $gv( 6, 7 ) );
teq( 'M7 = Tổng lương', 'Tổng lương', $gv( 6, 12 ) );
teq( 'Z7 = TOTAL SALARY', 'TOTAL SALARY', $gv( 6, 25 ) );
teq( 'F8 = Số công YC (nằm ở dòng hai)', 'Số công YC', $gv( 7, 5 ) );
teq( 'U8 = Tổng (của nhóm cộng)', 'Tổng', $gv( 7, 20 ) );

/* Dòng dữ liệu đầu tiên nằm ở dòng 9, đúng như file. */
teq( 'B9 = tên người', 'Lâm Tú Lanh', $gv( 8, 1 ) );
teq( '🔴 số căn cước GIỮ SỐ 0 ĐẦU — nó đi vào hồ sơ bảo hiểm', '094301002660', $gv( 8, 2 ) );
teq( 'G9 = số giờ', 19.2, $gv( 8, 6 ) );
teq( 'H9 = đơn giá', 23000.0, $gv( 8, 7 ) );
teq( '🔴 I9 là CÔNG THỨC, không phải số chết', '=G9*H9', $gv( 8, 8 ) );
teq( 'M9 = I+K−L, đúng công thức đọc từ file', '=I9+K9-L9', $gv( 8, 12 ) );
teq( 'U9 = tổng nhóm cộng', '=SUM(N9:T9)', $gv( 8, 20 ) );
teq( 'Y9 = tổng nhóm trừ', '=SUM(V9:X9)', $gv( 8, 24 ) );
teq( 'Z9 = M+U−Y, đúng công thức đọc từ file', '=M9+U9-Y9', $gv( 8, 25 ) );

/* 🔴 CỘT J K L N..Y ĐỂ TRỐNG — anh Thắng chốt kế toán điền. Có số 0 ở đấy là nói dối rằng hệ
   đã xét tới chúng. */
foreach ( array( 9 => 'J', 10 => 'K', 11 => 'L', 13 => 'N', 21 => 'V', 23 => 'X' ) as $ci => $ten ) {
	teq( '🔴 cột ' . $ten . ' để TRỐNG cho kế toán điền, không phải số 0', null, $gv( 8, $ci ) );
}

/* 🔴 DÒNG CHƯA KHAI GIÁ: KHÔNG MỘT CÔNG THỨC NÀO, và nói thẳng vì sao.
   Để `M=I+K−L` chạy trên dòng I trống thì M ra 0 — trông y như người này tháng nay không có
   lương, chứ không phải "chưa ai khai đơn giá". */
$d_kg = null;
for ( $i = 8; $i < count( $h ); $i++ ) {
	if ( 'Người Chưa Khai Giá' === $gv( $i, 1 ) ) { $d_kg = $i; }
}
t( 'tìm thấy dòng chưa khai giá', null !== $d_kg );
teq( 'vẫn có đủ số giờ', 8.0, $gv( $d_kg, 6 ) );
teq( '🔴 cột Lương chính để TRỐNG', null, $gv( $d_kg, 8 ) );
teq( '🔴 cột Tổng lương KHÔNG có công thức (không ra số 0)', null, $gv( $d_kg, 12 ) );
teq( '🔴 cột TOTAL SALARY cũng vậy', null, $gv( $d_kg, 25 ) );
t( '🔴 và ghi chú NÓI THẲNG vì sao trống',
	false !== strpos( (string) $gv( $d_kg, 26 ), 'CHƯA KHAI ĐƠN GIÁ' ), $gv( $d_kg, 26 ) );

/* Dòng tổng cộng bằng SUM, để kế toán sửa một ô là tổng theo ngay. */
$d_tong = count( $h ) - 1;
t( 'dòng cuối là dòng TỔNG', false !== strpos( (string) $gv( $d_tong, 1 ), 'TỔNG' ), $gv( $d_tong, 1 ) );
teq( 'tổng cột Z cộng bằng SUM', '=SUM(Z9:Z11)', $gv( $d_tong, 25 ) );

/* Ô gộp và độ rộng cột — lấy theo đúng file, để mở ra trông y hệt cái kế toán đang dùng. */
t( 'có gộp ô tiêu đề nhóm cộng (N7:U7)', in_array( 'N7:U7', $to['gop'], true ), $to['gop'] );
t( 'có gộp ô tiêu đề nhóm trừ (V7:X7)', in_array( 'V7:X7', $to['gop'], true ), $to['gop'] );
teq( 'khai đủ độ rộng cho 27 cột A..AA', 27, count( VHCC_BangLuong::RONG_COT ) );
teq( 'mọi dòng đều đủ 27 cột', true, ( function () use ( $h ) {
	foreach ( $h as $d ) { if ( count( $d ) !== 27 ) { return false; } }
	return true;
} )() );

/* ---- Tệp .xlsx THẬT: dựng ra và mở lại được ---- */
if ( VHCC_Xuat::co_xlsx() ) {
	$noi = VHCC_Xuat::xlsx( $x['to'] );
	t( '🔴 dựng được tệp .xlsx thật', is_string( $noi ) && strlen( $noi ) > 1000, strlen( (string) $noi ) );
	/* Mở lại bằng chính ZipArchive: thiếu một phần là Excel báo "unreadable content" và KHÔNG
	   mở tệp — chứ không bỏ qua phần nó không hiểu. */
	$tam = VHCC_DB::tep_tam( 'vhcc-thu-xuat' );
	file_put_contents( $tam, $noi );
	$z = new ZipArchive();
	t( 'tệp mở lại được như một gói zip hợp lệ', true === $z->open( $tam ) );
	foreach ( array( '[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml',
		'xl/worksheets/sheet1.xml' ) as $phan ) {
		t( 'có đủ phần ' . $phan, false !== $z->locateName( $phan ), $phan );
	}
	$sx = $z->getFromName( 'xl/worksheets/sheet1.xml' );
	/* ⚠️ Excel BẮT đúng thứ tự: <cols> trước <sheetData>, <mergeCells> sau. Sai thứ tự là tệp
	   không mở được — nên canh bằng vị trí, không chỉ canh "có mặt". */
	t( '🔴 <cols> đứng TRƯỚC <sheetData>', strpos( $sx, '<cols>' ) < strpos( $sx, '<sheetData>' ) );
	t( '🔴 <mergeCells> đứng SAU </sheetData>',
		strpos( $sx, '<mergeCells' ) > strpos( $sx, '</sheetData>' ) );
	t( 'công thức được ghi bằng thẻ <f>', false !== strpos( $sx, '<f>G9*H9</f>' ), 'thiếu <f>' );
	/* 🔴 Công thức KHÔNG được kèm <v> đoán sẵn — Excel sẽ hiện số đoán ấy cho tới khi ai đó bấm
	   tính lại, tức một con số trông như thật mà sai. */
	t( '🔴 công thức KHÔNG kèm <v> đoán sẵn',
		false === strpos( $sx, '<f>G9*H9</f><v>' ), 'có <v> đi kèm công thức' );
	$st = $z->getFromName( 'xl/styles.xml' );
	t( 'có định dạng tiền #,##0', false !== strpos( $st, '#,##0' ) );
	t( 'có định dạng giờ 0.00 — 19,2 giờ làm tròn lên 19 là lệch tiền', false !== strpos( $st, '0.00' ) );
	$z->close();
	@unlink( $tam );
}

/* 🔴 HỢP ĐỒNG CŨ CỦA BẢNG KIỂU Ô KHÔNG ĐƯỢC ĐỔI. Chỉ số 1 = đậm, dùng cho dòng tiêu đề của mọi
   bảng xuất khác đang chạy. Chèn kiểu mới vào giữa là mọi tiêu đề cũ đổi kiểu mà không ai sửa gì. */
teq( '🔴 kiểu 1 vẫn là ĐẬM (hợp đồng cũ)', 1, VHCC_Xuat::DAM );

ket_luan();
