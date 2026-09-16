<?php
/**
 * KIỂM BẢNG LƯƠNG CƠ SỞ — dựng lại đúng mấy con số trong file kế toán đang dùng.
 *
 * =============================================================================================
 * Anh Thắng 15/09/2026 gửi `LƯƠNG CƠ SỞ - T08.2026 - Tháng.xlsx`: *"mỗi cơ sở sẽ xuất bảng công
 * giờ ra theo mẫu file như này"*.
 *
 * 🔴 TÊN VÀ SỐ CĂN CƯỚC LÀ ĐỒ BỊA — CHỈ CON SỐ LÀ THẬT.
 *    Kho này CÔNG KHAI. Bản đầu của bài kiểm này (15/09/2026) chép thẳng họ tên và số căn cước
 *    thật từ file lương của anh Thắng vào đây — em sai, và một phiên làm việc khác bắt được
 *    ở bản 3.87.0. Giờ đều là tên bịa và số bịa (`0000...`, trông-là-biết-giả).
 *    SỐ GIỜ · ĐƠN GIÁ · KẾT QUẢ thì giữ nguyên của file thật — đó là bằng chứng công thức,
 *    và một con số 19,2 × 23.000 tách khỏi tên người thì không còn là thông tin của ai.
 *
 * 🔴 BÀI NÀY KHÔNG TỰ NGHĨ RA SỐ ĐỂ THỬ. Mọi con số dưới đây LẤY THẲNG TỪ FILE ẤY — giờ thật,
 *    đơn giá thật, và kết quả thật mà kế toán đã trả tháng 8. Thử bằng số mình tự bịa
 *    thì chỉ chứng minh mã khớp với chính nó; thử bằng số kế toán đã trả thì mới biết mã có
 *    thay được cái file làm tay hay không.
 *
 * Chạy: php tools/test/kiem-bang-luong-coso.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

/* 🔴 PHẢI KHAI `VHCC_TEST`, KHÔNG THÌ BÀI KIỂM CHẾT CÂM.
   `VHCC_Web::ve()` (chuyển hướng sau mỗi lượt POST) gọi `exit` — trừ khi hằng số này có mặt.
   Thiếu nó thì lượt POST đầu tiên giết luôn cả tiến trình, mà vì đang nằm trong `ob_start()`
   nên mọi thứ đã in ra bị nuốt sạch: màn hình TRỐNG TRƠN, mã thoát 0, trông y như bài kiểm
   chạy xong không có gì để nói. Mất mười phút mới lần ra. */
define( 'VHCC_TEST', 1 );

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

/** Dựng một màn của trang quản trị bằng phiên của một người cụ thể. */
function vhcc_man( $ma_nv, $vai, $coso, $get = array() ) {
	$_GET = $get; $_POST = array();
	$_COOKIE = array( VHCC_Web::COOKIE => VHCC_Auth::phat_token( 'Người ' . $ma_nv, $vai, $coso, $ma_nv ) );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_COOKIE = array();
	return $h;
}

/* ⚠️ CƠ SỞ PHẢI CÓ TRONG DANH MỤC (`bo_phan_coso`) THÌ MỚI VÀO Ô CHỌN Ở MÀN CẤU HÌNH.
   `ds_coso()` đọc từ bảng ấy và bảng `may`, KHÔNG đọc từ `nhan_vien` — nên gieo mỗi hồ sơ là
   ô chọn rỗng, `$cs` bị xoá về '' và khối khai đơn giá riêng không vẽ ra. */
foreach ( array( 'AEON_BT' => 'Khu vui chơi', 'AEON_TP' => 'Khu vui chơi',
	'LOTTE_GV' => 'Khu vui chơi', 'KHO_LA' => 'Khu vui chơi' ) as $_cs => $_bp ) {
	$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $_cs, 'bo_phan' => $_bp ) );
}

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

/* 🔴 NGƯỜI ĐÈ LÊN CƠ SỞ — Lotte Gò Vấp có ba "NV" ăn 23.000, riêng một người 25.000. */
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

/* 🔴 LUẬT MỚI, 16/09/2026 — GIỜ TỔNG LÀ GIỜ CHÍNH, TRỪ ĐI GIỜ ĂN GIÁ KHÁC.
   Anh Thắng: *"trên chấm công sẽ chỉ có giờ tổng"* và *"kế toán sẽ làm 1 việc, nhập giờ lương
   khác, nó sẽ trừ giờ tổng đi"*.

   Bản trước của em tách dòng theo HẬU TỐ CA rồi đặt tên bằng một bảng cố định. Đối chiếu file
   T08 thật thì MƯỜI dòng ra 0đ: mảng Khu vui chơi gọi hậu tố `TT` là **Lơ Tàu**, còn bảng cố
   định nói là "Thu tiền" — tra đơn giá bằng một cái tên không ai khai. Chính lượt đối chiếu ấy
   bắt được, chứ không phép thử nào trước đó thấy.

   Cảnh dưới đây lấy đúng từ ảnh anh Thắng gửi: một người 126 giờ chấm công, trong đó 2 giờ MC
   và 6 giờ Hỗ Trợ, còn lại 118 giờ Partime. 118 + 2 + 6 = 126. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BT_MAN', 'ho_ten' => 'Người Ba Loại Việc',
	'cccd' => '000000000011', 'cua_hang' => 'AEON_BT', 'chuc_vu' => 'Partime', 'vai_tro' => 'Nhân viên' ) );

$gieo = function ( $ma, $cs, $ngay, $vao_g, $so_phut, $hau_to = '' ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'ma_nv' => $ma, 'ho_ten' => '', 'coso' => $cs, 'ngay' => $ngay,
		'gio_vao_giay' => $vao_g, 'gio_ra_giay' => $vao_g + (int) round( $so_phut * 60 ),
		'hau_to' => $hau_to, 'nguon' => 'may' ) );
};
/* 126 giờ = 7560 phút, rải nhiều ngày. Cố ý cho MỘT ngày mang hậu tố ca đêm: hậu tố là chuyện
   của lưới chấm công, KHÔNG được đẻ ra một dòng lương riêng nữa. */
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-01', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-02', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-03', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-04', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-05', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-06', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-07', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-08', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-09', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-10', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-11', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-12', 8 * 3600, 600 );
$gieo( 'BT_MAN', 'AEON_BT', '2026-08-13', 22 * 3600, 360, 'CD' );   // hậu tố, vẫn cộng vào tổng

VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT',
	array( 'Partime' => 20000, 'MC' => 30000, 'Hỗ Trợ' => 25000, 'Lái Tàu' => 23000 ) );

/* Chưa nhập giờ khác: cả 126 giờ là giờ chính, MỘT dòng duy nhất. */
$b0 = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$d0 = null;
foreach ( $b0['dong'] as $d ) { if ( 'BT_MAN' === $d['ma'] ) { $d0 = $d; } }
teq( '🔴 chưa nhập giờ khác thì ra ĐÚNG MỘT dòng', 126.0, $d0['gio'] );
teq( 'và hậu tố ca đêm KHÔNG đẻ ra dòng lương riêng', true, (bool) $d0['laChinh'] );
teq( 'ăn đơn giá của chức vụ trong hồ sơ', 20000.0, $d0['gia'] );
teq( 'lương chính 126 × 20.000', 2520000.0, $d0['luongChinh'] );

/* 🔴 NHẬP HAI DÒNG GIỜ KHÁC — hệ tự trừ ra. */
$r_gk = VHCC_ChotLuong::dat( $U_CHT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => '2' ), array( 'viec' => 'Hỗ Trợ', 'gio' => '6' ) ), 126 );
t( '🔴 cửa hàng trưởng nhập được giờ lương khác', ! empty( $r_gk['ok'] ), $r_gk );
teq( 'hệ tự tính giờ chính còn lại', 118.0, $r_gk['chinh'] );

$b1 = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$ds = array();
foreach ( $b1['dong'] as $d ) { if ( 'BT_MAN' === $d['ma'] ) { $ds[ $d['cv'] ] = $d; } }
teq( '🔴 ra BA dòng: chính + hai loại việc', 3, count( $ds ) );
teq( '🔴 Partime 118 giờ — đúng ảnh anh Thắng gửi', 118.0, $ds['Partime']['gio'] );
teq( '   × 20.000 = 2.360.000', 2360000.0, $ds['Partime']['luongChinh'] );
teq( '🔴 MC 2 giờ', 2.0, $ds['MC']['gio'] );
teq( '   × 30.000 = 60.000', 60000.0, $ds['MC']['luongChinh'] );
teq( '🔴 Hỗ Trợ 6 giờ', 6.0, $ds['Hỗ Trợ']['gio'] );
teq( '   × 25.000 = 150.000', 150000.0, $ds['Hỗ Trợ']['luongChinh'] );
teq( 'dòng chính được đánh dấu là chính', true, (bool) $ds['Partime']['laChinh'] );
teq( 'hai dòng kia thì không', false, (bool) $ds['MC']['laChinh'] );
/* Ba dòng cộng lại vẫn đúng 126 giờ — không tạo ra giờ từ hư không, không đánh rơi giờ nào. */
teq( '🔴 ba dòng cộng lại = giờ chấm công', 126.0,
	round( $ds['Partime']['gio'] + $ds['MC']['gio'] + $ds['Hỗ Trợ']['gio'], 2 ) );

/* 🔴 GÕ QUÁ GIỜ CHẤM CÔNG THÌ BỊ CHỐI — không để giờ chính ra âm. */
$r_qua = VHCC_ChotLuong::dat( $U_CHT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => '200' ) ), 126 );
t( '🔴 tổng giờ khác vượt giờ chấm công: BỊ CHỐI', empty( $r_qua['ok'] ), $r_qua );
t( 'và câu chối nói rõ đang lệch bao nhiêu',
	false !== strpos( (string) $r_qua['error'], '126' ), $r_qua );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CÁC KHOẢN CỘNG / TRỪ — cửa hàng trưởng nhập (anh Thắng 16/09/2026)
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/
$r_t = VHCC_ChotLuong::dat_tien( $U_CHT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( 'traTN' => '2000000', 'hoanCoc' => '300000' ), array( 'datCoc' => '100000' ) );
t( '🔴 cửa hàng trưởng nhập được khoản cộng / trừ', ! empty( $r_t['ok'] ), $r_t );
teq( 'tổng cộng', 2300000.0, $r_t['cong'] );
teq( 'tổng trừ', 100000.0, $r_t['tru'] );

$b2 = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$dc = null;
foreach ( $b2['dong'] as $d ) { if ( 'BT_MAN' === $d['ma'] && $d['laChinh'] ) { $dc = $d; } }
teq( '🔴 khoản tiền gắn vào DÒNG CHÍNH', 2300000.0, $dc['tongCong'] );
teq( 'và khoản trừ cũng vậy', 100000.0, $dc['tongTru'] );
/* 🔴 KHÔNG RẢI RA MỌI DÒNG. Một cái cọc chỉ trừ MỘT LẦN, dù người ấy có ba dòng việc. */
teq( '🔴 dòng giờ khác KHÔNG mang khoản tiền nào', 0.0, $ds['MC']['tongCong'] );

/* Số âm bị chối — muốn trừ thì gõ vào nhóm giảm trừ, không phải gõ số âm vào nhóm cộng. */
$r_am = VHCC_ChotLuong::dat_tien( $U_CHT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( 'target' => '-500000' ), array() );
t( '🔴 số âm ở nhóm cộng bị chối', empty( $r_am['ok'] ), $r_am );

/* Cơ sở khác thì không đụng được — mở cửa cho cửa hàng trưởng không kéo theo mở phạm vi. */
$r_xa = VHCC_ChotLuong::dat_tien( $U_CHT, 'LOTTE_GV', '2026-08', 'BT_MAN',
	array( 'target' => '500000' ), array() );
t( '🔴 cửa hàng trưởng KHÔNG nhập được cho cơ sở khác', empty( $r_xa['ok'] ), $r_xa );

/* ---- Lối THEO THÁNG: 4.000.000 × 26/26 = 4.000.000 ---- */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TP_TRUYEN',
	'ho_ten' => 'Người Lương Tháng', 'cua_hang' => 'AEON_TP', 'chuc_vu' => 'NV',
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

/* ---- Người ăn lương tháng: hậu tố ca KHÔNG còn đẻ ra dòng lương riêng ----
   Luật 16/09/2026. Trước đây một ngày mang hậu tố `CD` sinh ra dòng lương thứ hai tính theo
   giờ; nay hậu tố chỉ là chuyện của lưới chấm công, và ngày ấy đếm vào số công như mọi ngày. */
$gieo( 'TP_TRUYEN', 'AEON_TP', '2026-08-27', 22 * 3600, 300, 'CD' );
$b6 = VHCC_BangLuong::dung( 'AEON_TP', '2026-08' );
$d_ch = null;
foreach ( $b6['dong'] as $d ) { if ( 'TP_TRUYEN' === $d['ma'] ) { $d_ch = $d; } }
teq( '🔴 vẫn ĐÚNG MỘT dòng cho người ấy', 1,
	count( array_filter( $b6['dong'], function ( $x ) { return 'TP_TRUYEN' === $x['ma']; } ) ) );
teq( 'ngày mang hậu tố đếm vào số công như mọi ngày', 27, $d_ch['congThuc'] );
teq( '🔴 lương tháng theo số công thực: 4.000.000 × 27/26', 4153846.15, $d_ch['luongChinh'] );

/* 🔴 KẾ TOÁN CŨNG NHẬP ĐƯỢC, KHÔNG CHỈ CỬA HÀNG TRƯỞNG.
   Anh Thắng 16/09/2026: *"kế toán và cửa hàng trưởng chứ em, anh nói là từ dưới đi lên"* —
   cửa hàng trưởng gõ trước, kế toán soi lại và sửa. Cửa gác là `cong_coso` (bậc Cửa hàng
   trưởng); Kế toán bậc cao hơn nên qua luôn — phép này canh đúng điều ấy, để ngày ai siết
   `cong_coso` lên bậc khác thì biết là đã chặn mất kế toán. */
$r_kt_gio = VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => '3' ) ), 126 );
t( '🔴 kế toán nhập được giờ lương khác', ! empty( $r_kt_gio['ok'] ), $r_kt_gio );
$r_kt_tien = VHCC_ChotLuong::dat_tien( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( 'traTN' => '2000000', 'hoanCoc' => '300000' ), array( 'datCoc' => '100000' ) );
t( 'và sửa được khoản tiền cửa hàng trưởng vừa gõ', ! empty( $r_kt_tien['ok'] ), $r_kt_tien );
/* Trả lại đúng cảnh cũ cho mấy phép phía sau. */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => '2' ), array( 'viec' => 'Hỗ Trợ', 'gio' => '6' ) ), 126 );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. TỆP XUẤT RA — ĐÚNG DẠNG FILE KẾ TOÁN, VÀ MỞ ĐƯỢC
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* Thêm vào chính cơ sở này một người CHƯA KHAI ĐƠN GIÁ — để soi cái dòng nguy hiểm nhất của
   tệp xuất ra: dòng mà hệ không ra được tiền. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BT_NOGIA',
	'ho_ten' => 'Người Chưa Khai Giá', 'cccd' => '000000000022', 'cua_hang' => 'AEON_BT',
	'chuc_vu' => 'Việc Chưa Có Trong Sổ', 'vai_tro' => 'Nhân viên' ) );
$gieo( 'BT_NOGIA', 'AEON_BT', '2026-08-05', 8 * 3600, 480 );

$x = VHCC_BangLuong::to_xlsx( 'AEON_BT', '2026-08', 'Cơ SỞ Thử Bình Tân' );
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
teq( 'và nói rõ cơ sở nào', 'Cơ SỞ Thử Bình Tân', $gv( 5, 0 ) );

/* Hai dòng tiêu đề, đúng vị trí cột như file — kế toán đối chiếu bằng mắt theo vị trí. */
teq( 'A7 = STT',  'STT', $gv( 6, 0 ) );
teq( 'C7 = CCCD', 'CCCD', $gv( 6, 2 ) );
teq( 'H7 = Tiền/h', 'Tiền/h', $gv( 6, 7 ) );
teq( 'M7 = Tổng lương', 'Tổng lương', $gv( 6, 12 ) );
teq( 'Z7 = TOTAL SALARY', 'TOTAL SALARY', $gv( 6, 25 ) );
teq( 'F8 = Số công YC (nằm ở dòng hai)', 'Số công YC', $gv( 7, 5 ) );
teq( 'U8 = Tổng (của nhóm cộng)', 'Tổng', $gv( 7, 20 ) );

/* Dòng dữ liệu đầu tiên nằm ở dòng 9, đúng như file. */
/* ⚠️ TÌM DÒNG THEO TÊN, KHÔNG ĐÓNG CỨNG CHỈ SỐ. Bảng xếp theo tên, nên đổi một cái tên
   trong đồ thử là mọi chỉ số trượt đi một dòng — và bài kiểm đỏ vì lý do chẳng liên quan
   gì tới thứ nó định canh (đã vấp đúng thế lúc bỏ tên thật ra khỏi bài này). */
$tim = function ( $ten ) use ( $h, $gv ) {
	for ( $i = 8; $i < count( $h ); $i++ ) { if ( $ten === $gv( $i, 1 ) ) { return $i; } }
	return -1;
};
$d1 = $tim( 'Người Ba Loại Việc' );
t( 'tìm thấy dòng của người ba loại việc', $d1 >= 8, $d1 );
$r1 = $d1 + 1;   // số dòng trong Excel (1-indexed)
teq( 'cột tên đúng người', 'Người Ba Loại Việc', $gv( $d1, 1 ) );
teq( '🔴 số căn cước GIỮ SỐ 0 ĐẦU — nó đi vào hồ sơ bảo hiểm', '000000000011', $gv( $d1, 2 ) );
teq( 'cột G = số giờ', 118.0, $gv( $d1, 6 ) );
teq( 'cột H = đơn giá', 20000.0, $gv( $d1, 7 ) );
teq( '🔴 cột I là CÔNG THỨC, không phải số chết', '=G' . $r1 . '*H' . $r1, $gv( $d1, 8 ) );
teq( 'cột M = I+K−L, đúng công thức đọc từ file', '=I' . $r1 . '+K' . $r1 . '-L' . $r1, $gv( $d1, 12 ) );
teq( 'cột U = tổng nhóm cộng', '=SUM(N' . $r1 . ':T' . $r1 . ')', $gv( $d1, 20 ) );
teq( 'cột Y = tổng nhóm trừ', '=SUM(V' . $r1 . ':X' . $r1 . ')', $gv( $d1, 24 ) );
teq( 'cột Z = M+U−Y, đúng công thức đọc từ file', '=M' . $r1 . '+U' . $r1 . '-Y' . $r1, $gv( $d1, 25 ) );

/* 🔴 CỘT J K L N..Y ĐỂ TRỐNG — anh Thắng chốt kế toán điền. Có số 0 ở đấy là nói dối rằng hệ
   đã xét tới chúng. */
/* 🔴 LUẬT ĐỔI 16/09/2026: mấy cột cộng/trừ nay DO NGƯỜI GÕ TRÊN TRANG, không để trống chờ kế
   toán nữa. Nhưng luật "ô chưa gõ thì để TRỐNG, không ghi 0" thì GIỮ NGUYÊN — một tờ lương đầy
   số 0 trông như đã xét hết mọi khoản, trong khi chưa ai gõ gì. */
foreach ( array( 9 => 'J', 10 => 'K', 11 => 'L', 13 => 'N', 21 => 'V' ) as $ci => $ten ) {
	teq( '🔴 cột ' . $ten . ' chưa ai gõ thì để TRỐNG, không phải số 0', null, $gv( $d1, $ci ) );
}
/* Ô ĐÃ gõ thì đổ thẳng số vào tờ xuất — đây là chỗ khác hẳn bản hôm qua. */
teq( '🔴 cột X (Đặt cọc) mang đúng số đã gõ trên trang', 100000.0, $gv( $d1, 23 ) );
teq( 'cột S (Trả TN) cũng vậy', 2000000.0, $gv( $d1, 18 ) );
teq( 'cột T (Hoàn cọc) cũng vậy', 300000.0, $gv( $d1, 19 ) );

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
/* ⚠️ Vùng SUM phải trải ĐÚNG số dòng dữ liệu đang có — đóng cứng Z9:Z11 là mỗi lần đồ thử thêm
   một người thì phép này đỏ vì lý do chẳng liên quan. Tính từ chính số dòng. */
teq( 'tổng cột Z cộng bằng SUM trải đủ mọi dòng',
	'=SUM(Z9:Z' . ( $d_tong ) . ')', $gv( $d_tong, 25 ) );

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
	t( 'công thức được ghi bằng thẻ <f>',
		false !== strpos( $sx, '<f>G' . $r1 . '*H' . $r1 . '</f>' ), 'thiếu <f>' );
	/* 🔴 Công thức KHÔNG được kèm <v> đoán sẵn — Excel sẽ hiện số đoán ấy cho tới khi ai đó bấm
	   tính lại, tức một con số trông như thật mà sai. */
	t( '🔴 công thức KHÔNG kèm <v> đoán sẵn',
		false === strpos( $sx, '<f>G' . $r1 . '*H' . $r1 . '</f><v>' ), 'có <v> đi kèm công thức' );
	$st = $z->getFromName( 'xl/styles.xml' );
	t( 'có định dạng tiền #,##0', false !== strpos( $st, '#,##0' ) );
	t( 'có định dạng giờ 0.00 — 19,2 giờ làm tròn lên 19 là lệch tiền', false !== strpos( $st, '0.00' ) );
	$z->close();
	@unlink( $tam );
}

/* 🔴 HỢP ĐỒNG CŨ CỦA BẢNG KIỂU Ô KHÔNG ĐƯỢC ĐỔI. Chỉ số 1 = đậm, dùng cho dòng tiêu đề của mọi
   bảng xuất khác đang chạy. Chèn kiểu mới vào giữa là mọi tiêu đề cũ đổi kiểu mà không ai sửa gì. */
teq( '🔴 kiểu 1 vẫn là ĐẬM (hợp đồng cũ)', 1, VHCC_Xuat::DAM );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. MÀN HÌNH — KHỐI CÓ VẼ RA, NÚT CÓ ĂN, VÀ QUYỀN KHÔNG RỘNG HƠN Ý ĐỊNH
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* 🔴 CANH ĐƯỜNG NGƯỜI DÙNG ĐI, KHÔNG CHỈ CANH LÕI. Bài học 15/09/2026 (`sua_gio`): quyền đúng,
   lõi đúng, khối vẽ đúng — mà tên việc không có trong danh sách trắng nên bấm nút không ăn, và
   5162 phép vẫn xanh vì chúng đều gọi thẳng vào lõi. */
teq( '🔴 việc khai đơn giá có tên trong danh sách việc của trang',
	true, in_array( 'gia_gio', VHCC_Web::VIEC_CHAM, true ) );
teq( '🔴 "luong" là một kiểu xuất hợp lệ', '',
	VHCC_Web::vi_sao_khong_xuat( array( 'name' => 'T', 'role' => 'Cửa hàng trưởng',
		'coso' => 'AEON_BT', 'ma_nv' => 'X' ), 'luong', 'AEON_BT' ) );
t( '🔴 và nó cần ZipArchive (để báo đúng câu khi hosting thiếu)',
	VHCC_Web::xuat_can_zip( 'luong' ) );

/* 🔴 CƠ SỞ KHÁC VẪN CHỐI. Mở cửa xuất cho cửa hàng trưởng không được kéo theo mở phạm vi. */
t( '🔴 cửa hàng trưởng KHÔNG xuất được bảng lương cơ sở khác',
	'' !== VHCC_Web::vi_sao_khong_xuat( array( 'name' => 'T', 'role' => 'Cửa hàng trưởng',
		'coso' => 'AEON_BT', 'ma_nv' => 'X' ), 'luong', 'LOTTE_GV' ) );
/* Nhân viên bậc 1 thì không xuất được gì cả. */
t( '🔴 nhân viên bậc 1 KHÔNG xuất được bảng lương',
	'' !== VHCC_Web::vi_sao_khong_xuat( array( 'name' => 'N', 'role' => 'Nhân viên',
		'coso' => 'AEON_BT', 'ma_nv' => 'Y' ), 'luong', 'AEON_BT' ) );

/* ---- Khối trên màn Bảng công: cửa hàng trưởng PHẢI thấy ---- */
$h_cht = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
t( '🔴 cửa hàng trưởng thấy khối Bảng lương cơ sở',
	false !== strpos( $h_cht, 'Bảng lương cơ sở' ), substr( $h_cht, 0, 400 ) );
t( 'có nút xuất bảng lương', false !== strpos( $h_cht, 'xuat=luong' ), 'thiếu nút xuất' );
t( 'bảng hiện tên người và số giờ',
	false !== strpos( $h_cht, 'Người Ba Loại Việc' ) && false !== strpos( $h_cht, '118,00' ), $h_cht );
/* 🔴 DÒNG CHƯA KHAI GIÁ PHẢI KÊU TO, TRÊN ĐẦU BẢNG — không phải nằm lẫn ở cột ghi chú cuối. */
t( '🔴 màn cảnh báo ngay đầu khối về dòng chưa khai đơn giá',
	false !== strpos( $h_cht, 'chưa khai đơn giá giờ' ), $h_cht );

/* 🔴 NHÂN VIÊN BẬC 1 KHÔNG THẤY MỘT ĐỒNG NÀO. Khối này in tiền công của người khác. */
$h_nv = vhcc_man( 'NV_BL', 'Nhân viên', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
t( '🔴 nhân viên bậc 1 KHÔNG thấy khối Bảng lương cơ sở',
	false === strpos( $h_nv, 'Bảng lương cơ sở' ), 'lộ bảng lương cho bậc 1' );
t( '🔴 và KHÔNG thấy đơn giá của ai', false === strpos( $h_nv, '23.000' ), 'lộ đơn giá' );

/* ---- Khối khai đơn giá: chỉ Kế toán trở lên ---- */
$h_kt = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'man' => 'cau_hinh', 'ccs' => 'AEON_BT' ) );
t( '🔴 kế toán thấy khối khai đơn giá',
	false !== strpos( $h_kt, 'Đơn giá giờ của cơ sở' ), substr( $h_kt, 0, 400 ) );
t( 'khối liệt kê chức vụ đang dùng thật của tháng, không bắt gõ tay',
	false !== strpos( $h_kt, 'name="gg_cs[' ), $h_kt );
t( 'và có bảng chung cả chuỗi', false !== strpos( $h_kt, 'name="gg_chung[' ), $h_kt );

$h_cht_ch = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cau_hinh', 'ccs' => 'AEON_BT' ) );
t( '🔴 cửa hàng trưởng KHÔNG thấy khối khai đơn giá',
	false === strpos( $h_cht_ch, 'Đơn giá giờ của cơ sở' ), 'cửa hàng trưởng khai được đơn giá' );

/* ---- 🔴 GỬI THẬT MỘT LƯỢT POST. Khối vẽ đúng mà bộ điều phối không nhận việc thì bấm Lưu
        xong không có gì xảy ra — và mọi phép soi HTML ở trên vẫn xanh. ---- */
$tok_kt = VHCC_Auth::phat_token( 'Chị Kế Toán', 'Kế toán', '', 'KT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_kt );
$_GET  = array( 'man' => 'cau_hinh', 'ccs' => 'AEON_BT' );
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_kt ), 'ccs' => 'AEON_BT',
	'gg_cs' => array( 'Lái Tàu' => '26500' ) );
ob_start(); VHCC_Web::phuc_vu(); $h_post = ob_get_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 bấm Lưu trên màn thì đơn giá vào sổ thật', 26500.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );
t( 'và màn KHÔNG báo câu chối về màn Hồ sơ',
	false === strpos( $h_post, 'thuộc màn Hồ sơ' ), substr( $h_post, 0, 600 ) );

/* Ba dòng trống ở cuối bảng chung phải ĐỌC ĐƯỢC — vẽ ô mà không nhận là người ta gõ xong,
   thấy báo "đã lưu", rồi chức vụ biến mất không dấu vết. */
$_COOKIE = array( VHCC_Web::COOKIE => $tok_kt );
$_GET  = array( 'man' => 'cau_hinh' );
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_kt ),
	'gg_chung' => array( 'tn' => '24000' ),
	'gg_chung_moi_ten' => array( 0 => 'Thu ngân mới' ),
	'gg_chung_moi'     => array( 0 => '28000' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 dòng trống gõ thêm chức vụ mới thì LƯU THẬT', 28000.0,
	VHCC_GiaGio::tra( 'CS_KHONG_KHAI', 'Thu ngân mới' )['gia'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. CHỐT CHỐNG DÁN SỐ CĂN CƯỚC THẬT VÀO KHO CÔNG KHAI
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/
/* 🔴 LỖI NÀY ĐÃ XẢY RA THẬT, 15/09/2026 — nên phải có chốt, không phải chỉ một lời hứa.
   Em chép số căn cước thật từ file lương của anh Thắng vào chú thích mã VÀ vào đồ thử. Kho này
   công khai; một số căn cước lộ ra thì không đổi lại được như đổi mật khẩu.

   Luật: trong hai tệp của tính năng này, MỌI dãy 9 chữ số liền trở lên phải bắt đầu bằng `0000`
   — tức trông-là-biết-giả. Số thật không bao giờ có dạng ấy, nên chốt này bắt được cả lần sau
   ai đó "chỉ dán tạm một số cho giống thật". */
foreach ( array(
	'wordpress/vhcp-cham-cong/includes/class-vhcc-bang-luong.php',
	'tools/test/kiem-bang-luong-coso.php',
) as $_tep ) {
	$_src = (string) file_get_contents( dirname( dirname( __DIR__ ) ) . '/' . $_tep );
	preg_match_all( '/\\d{9,}/', $_src, $_m );
	$_xau = array();
	foreach ( $_m[0] as $_so ) {
		if ( 0 !== strpos( $_so, '0000' ) ) { $_xau[] = $_so; }
	}
	t( '🔴 ' . basename( $_tep ) . ': không có dãy số nào trông như căn cước thật',
		empty( $_xau ), implode( ' · ', array_unique( $_xau ) ) );
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. ĐƯỜNG NGƯỜI DÙNG ĐI — KHỐI NHẬP CÓ VẼ RA, VÀ NÚT CÓ ĂN
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 Đúng bài học `sua_gio` hôm 15/09: quyền đúng, lõi đúng, khối vẽ đúng — mà tên việc không
 *    có trong danh sách trắng thì bấm nút không ăn, và 5000 phép vẫn xanh vì chúng gọi thẳng
 *    vào lõi. Nên mục này đi đúng đường người dùng đi.
 */
teq( '🔴 việc chốt lương có tên trong danh sách việc của màn Bảng công',
	true, in_array( 'chot_luong', VHCC_Web::VIEC_CHAM, true ) );

$h_bl = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
t( '🔴 có đường mở khối nhập trên mỗi hàng', false !== strpos( $h_bl, 'clm=' ), 'thiếu nút nhập' );
t( 'bảng có cột Cộng / Trừ / Thực nhận',
	false !== strpos( $h_bl, '<th>Cộng</th>' ) && false !== strpos( $h_bl, '<th>Thực nhận</th>' ), $h_bl );

$h_mo = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08', 'clm' => 'BT_MAN' ) );
t( '🔴 bấm vào thì hiện khối nhập', false !== strpos( $h_mo, 'value="chot_luong"' ), 'thiếu khối nhập' );
t( 'có ô tên việc và ô số giờ',
	false !== strpos( $h_mo, 'name="cl_viec[0]"' ) && false !== strpos( $h_mo, 'name="cl_gio[0]"' ), $h_mo );
foreach ( array_keys( VHCC_ChotLuong::CONG ) as $k_c ) {
	t( 'có ô cộng "' . VHCC_ChotLuong::CONG[ $k_c ] . '"',
		false !== strpos( $h_mo, 'name="cl_cong[' . $k_c . ']"' ), $k_c );
}
foreach ( array_keys( VHCC_ChotLuong::TRU ) as $k_t ) {
	t( 'có ô trừ "' . VHCC_ChotLuong::TRU[ $k_t ] . '"',
		false !== strpos( $h_mo, 'name="cl_tru[' . $k_t . ']"' ), $k_t );
}
t( 'khối nhập nói rõ giờ chấm công của người ấy', false !== strpos( $h_mo, '126' ), $h_mo );

/* 🔴 NHÂN VIÊN BẬC 1 KHÔNG THẤY KHỐI NÀY — nó sửa được tiền. */
$h_nv_bl = vhcc_man( 'NV_BL', 'Nhân viên', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08', 'clm' => 'BT_MAN' ) );
t( '🔴 nhân viên bậc 1 KHÔNG mở được khối nhập',
	false === strpos( $h_nv_bl, 'value="chot_luong"' ), 'lộ khối nhập cho bậc 1' );

/* ---- 🔴 GỬI THẬT MỘT LƯỢT POST ---- */
$tok_bl = VHCC_Auth::phat_token( 'Trưởng BL', 'Cửa hàng trưởng', 'AEON_BT', 'CHT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_bl );
$_GET  = array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' );
$_POST = array( 'viec' => 'chot_luong', 'ky' => VHCC_Web::chu_ky( $tok_bl ),
	'ccs' => 'AEON_BT', 'cth' => '2026-08', 'cl_ma' => 'BT_MAN',
	'cl_viec' => array( 0 => 'MC', 1 => 'Hỗ Trợ' ), 'cl_gio' => array( 0 => '2', 1 => '6' ),
	'cl_cong' => array( 'target' => '500000' ), 'cl_tru' => array( 'phat' => '150000' ) );
ob_start(); VHCC_Web::phuc_vu(); $h_post_bl = ob_get_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 bấm Lưu trên màn thì giờ khác vào sổ thật', 8.0,
	VHCC_ChotLuong::tong_cua( 'AEON_BT', '2026-08', 'BT_MAN' ) );
$tt_bl = VHCC_ChotLuong::tong_tien( 'AEON_BT', '2026-08', 'BT_MAN' );
teq( 'và khoản cộng cũng vậy', 500000.0, $tt_bl['cong'] );
teq( 'và khoản trừ cũng vậy', 150000.0, $tt_bl['tru'] );
t( 'màn KHÔNG báo câu chối về màn Hồ sơ',
	false === strpos( $h_post_bl, 'thuộc màn Hồ sơ' ), substr( $h_post_bl, 0, 500 ) );

/* 🔴 Ô ẨN "giờ chấm công" KHÔNG ĐƯỢC TIN. Sửa HTML gửi lên 900 giờ thì vẫn phải bị chối bằng
   con số máy chủ tự đọc lại, chứ không phải bằng con số trong biểu mẫu. */
$_COOKIE = array( VHCC_Web::COOKIE => $tok_bl );
$_GET  = array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' );
$_POST = array( 'viec' => 'chot_luong', 'ky' => VHCC_Web::chu_ky( $tok_bl ),
	'ccs' => 'AEON_BT', 'cth' => '2026-08', 'cl_ma' => 'BT_MAN',
	'cl_gio_cham' => '9999',
	'cl_viec' => array( 0 => 'MC' ), 'cl_gio' => array( 0 => '900' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 gõ 900 giờ kèm ô ẩn giả: KHÔNG ăn, sổ giữ nguyên', 8.0,
	VHCC_ChotLuong::tong_cua( 'AEON_BT', '2026-08', 'BT_MAN' ) );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 7. KHỐI ĐƠN GIÁ NẰM NGAY DƯỚI BẢNG LƯƠNG — AI SỬA, AI CHỈ XEM, VÀ LƯU KHÔNG MẤT SỔ
 *
 * Anh Thắng 16/09/2026, sau khi mở bảng lương thấy bảy dòng "CHƯA KHAI ĐƠN GIÁ": *"Chỗ set giờ
 * lương chỗ nào"*, rồi *"Giá là giá theo từng cơ sở, nên chọn cơ sở sẽ có giá đó. để tính dễ
 * dàng hơn"*, rồi *"Kế toán, quản lý chinhar sửa được, còn cửa hàng trưởng chỉ xem được"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

$U_QL = array( 'name' => 'Anh Quản Lý', 'role' => 'Quản lý', 'coso' => '' );

/* 🔴 QUẢN LÝ SỬA ĐƯỢC — ĐÂY LÀ PHẦN MỚI. Trước bản này `GiaGio::QUYEN` là `luong` (bậc 4) nên
   Quản lý bị chối y như cửa hàng trưởng. */
$r = VHCC_GiaGio::dat_coso( $U_QL, 'KHO_LA', array( 'Bốc xếp' => 27000 ) );
t( '🔴 QUẢN LÝ khai được đơn giá (mới)', ! empty( $r['ok'] ), $r );
teq( 'và giá vào sổ thật', 27000.0, VHCC_GiaGio::tra( 'KHO_LA', 'Bốc xếp' )['gia'] );

/* 🔴 CỬA HÀNG TRƯỞNG VẪN KHÔNG SỬA ĐƯỢC. Nới cho Quản lý không được kéo theo bậc dưới.
   ⚠️ Chụp giá TRƯỚC rồi so lại, chứ đừng viết cứng một con số: mấy mục trên đã đổi giá của
      AEON_BT vài lượt, và một phép thử so với con số bịa thì đỏ vì lý do chẳng liên quan. */
$gia_truoc = VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'];
t( 'gieo: AEON_BT đang có giá Lái Tàu để mà thử', $gia_truoc > 0, $gia_truoc );
$r = VHCC_GiaGio::dat_coso( $U_CHT, 'AEON_BT', array( 'Lái Tàu' => 99000 ) );
t( '🔴 cửa hàng trưởng VẪN bị chối khi sửa đơn giá', empty( $r['ok'] ), $r );
teq( 'và sổ không suy suyển', $gia_truoc, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );

/* 🔴 TÊN GÕ SAO ĐỌC LẠI Y VẬY. Bản trước vẽ thẳng cái khoá ra màn nên "Lái tàu" đọc lại thành
   "laitau" — đúng ba dòng anh Thắng nhìn thấy. */
VHCC_GiaGio::dat_chung( $U_KT, array( 'Lái tàu' => 23000, 'Lơ tàu' => 21000, 'CHT' => 26000 ) );
teq( '🔴 "Lái tàu" đọc lại vẫn là "Lái tàu", không phải "laitau"',
	'Lái tàu', VHCC_GiaGio::ten_cua( VHCC_GiaGio::khoa_cv( 'Lái tàu' ) ) );
teq( 'và "CHT" vẫn là "CHT", không thành "cht"',
	'CHT', VHCC_GiaGio::ten_cua( VHCC_GiaGio::khoa_cv( 'CHT' ) ) );
teq( 'khoá chưa từng khai thì trả lại chính khoá, không trả rỗng',
	'chuacokhai', VHCC_GiaGio::ten_cua( 'chuacokhai' ) );

/* ---- màn: ai thấy ô gõ, ai chỉ thấy số ---- */
$g_bl = array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' );
$h_gg_cht = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_bl );
$h_gg_kt  = vhcc_man( 'KT_BL', 'Kế toán', '', $g_bl );
$h_gg_ql  = vhcc_man( 'QL_BL', 'Quản lý', '', $g_bl );

t( 'cửa hàng trưởng THẤY khối đơn giá ngay dưới bảng lương',
	false !== strpos( $h_gg_cht, 'id="giagio"' ), 'không vẽ khối cho CHT' );
t( '🔴 nhưng KHÔNG có ô gõ nào',
	false === strpos( $h_gg_cht, 'name="gg_cs[' ), 'lộ ô gõ đơn giá cho cửa hàng trưởng' );
t( 'và màn nói rõ ai mới sửa được',
	false !== strpos( $h_gg_cht, 'xem được' ), $h_gg_cht );
t( '🔴 kế toán CÓ ô gõ', false !== strpos( $h_gg_kt, 'name="gg_cs[' ), 'kế toán không gõ được' );
t( '🔴 quản lý CÓ ô gõ', false !== strpos( $h_gg_ql, 'name="gg_cs[' ), 'quản lý không gõ được' );

/* Khối phải bám đúng cặp (cơ sở, tháng) đang xem — không đẻ thêm ô chọn cơ sở thứ hai để chọn
   lệch, đó là chính cái bẫy đã làm anh Thắng khai nhầm vào bảng chung. */
t( 'biểu mẫu mang sẵn đúng cơ sở đang xem',
	false !== strpos( $h_gg_kt, 'name="ccs" value="AEON_BT"' ), $h_gg_kt );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LƯU KHÔNG ĐƯỢC LÀM MẤT CHỨC VỤ THÁNG NÀY KHÔNG CÓ GIỜ
 *
 * `dat_coso()` THAY CẢ bảng của cơ sở bằng đúng những gì biểu mẫu gửi lên. Khối chỉ liệt kê
 * chức vụ có giờ THÁNG ĐANG XEM. Nên nếu biểu mẫu không chở theo mấy chức vụ đã khai mà tháng
 * này nghỉ, thì mở bảng lương bấm Lưu một cái là chúng bay khỏi sổ — im lặng.
 *
 * Phép này KHÔNG tự bịa nội dung biểu mẫu: nó BÓC đúng mấy ô `gg_cs[...]` mà màn vừa vẽ ra rồi
 * gửi lại y hệt, tức là mô phỏng đúng cú bấm Lưu của người thật.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array(
	'Lái Tàu' => 23000, 'Lơ Tàu' => 21000,
	'Nghỉ Hè'  => 19000,   // chức vụ CÓ KHAI mà tháng 8 không xếp ca ai
) );
teq( 'gieo: chức vụ tháng này không có giờ vẫn đang ở trong sổ', 19000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Nghỉ Hè' )['gia'] );

$h_rt = vhcc_man( 'KT_BL', 'Kế toán', '', $g_bl );
t( '🔴 màn CÓ vẽ ra dòng cho chức vụ tháng này không có giờ',
	false !== strpos( $h_rt, 'name="gg_cs[Nghỉ Hè]"' ), 'không vẽ -> bấm Lưu là mất sổ' );

/* Bóc mọi ô gg_cs[...] kèm value ra khỏi HTML vừa dựng — đúng thứ trình duyệt sẽ gửi đi. */
preg_match_all( '/name="gg_cs\[([^\]]*)\]"[^>]*?value="([^"]*)"/', $h_rt, $m_rt, PREG_SET_ORDER );
$gui_rt = array();
foreach ( $m_rt as $x_rt ) { $gui_rt[ html_entity_decode( $x_rt[1], ENT_QUOTES ) ] = $x_rt[2]; }
t( 'bóc được ít nhất ba ô đơn giá từ màn', count( $gui_rt ) >= 3, $gui_rt );

$tok_rt = VHCC_Auth::phat_token( 'Chị KT', 'Kế toán', '', 'KT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_rt );
$_GET  = $g_bl;
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_rt ),
	'ccs' => 'AEON_BT', 'gg_cs' => $gui_rt );
ob_start(); VHCC_Web::phuc_vu(); $h_rt_post = ob_get_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();

teq( '🔴 bấm Lưu: chức vụ tháng này KHÔNG có giờ vẫn còn nguyên giá', 19000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Nghỉ Hè' )['gia'] );
teq( 'và chức vụ có giờ cũng giữ nguyên', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );  // gieo lại ngay trên
t( 'lượt Lưu không bị chối bằng câu về màn Hồ sơ',
	false === strpos( $h_rt_post, 'thuộc màn Hồ sơ' ), substr( $h_rt_post, 0, 400 ) );

/* 🔴 CỬA HÀNG TRƯỞNG GỬI THẲNG POST THÌ VẪN PHẢI BỊ CHỐI. Màn không vẽ ô chỉ là không mời. */
$tok_c2 = VHCC_Auth::phat_token( 'Trưởng BL', 'Cửa hàng trưởng', 'AEON_BT', 'CHT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_c2 );
$_GET  = $g_bl;
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_c2 ),
	'ccs' => 'AEON_BT', 'gg_cs' => array( 'Lái Tàu' => '99000' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 CHT gõ tay POST đơn giá: KHÔNG ăn', 23000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );  // vẫn là giá vừa gieo, không phải 99.000

/* 🔴 LƯU BẢNG CHUNG KHÔNG ĐƯỢC ĐÈ TÊN THÀNH KHOÁ. Ô tên của dòng cũ là ô CHỈ ĐỌC mang khoá làm
   khoá mảng; bộ xử lý phải đọc `gg_chung_ten[...]` chứ không nhận bừa cái khoá làm tên. */
$tok_ch = VHCC_Auth::phat_token( 'Chị KT', 'Kế toán', '', 'KT_BL' );
$k_lt = VHCC_GiaGio::khoa_cv( 'Lái tàu' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_ch );
$_GET  = array( 'man' => 'cau_hinh', 'ccs' => 'AEON_BT' );
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_ch ),
	'gg_chung' => array( $k_lt => '23500' ),
	'gg_chung_ten' => array( $k_lt => 'Lái tàu' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( 'lưu bảng chung thì giá đổi', 23500.0, VHCC_GiaGio::tra( 'KHO_LA', 'Lái tàu' )['gia'] );
teq( '🔴 và TÊN KHÔNG bị đè thành khoá', 'Lái tàu', VHCC_GiaGio::ten_cua( $k_lt ) );

ket_luan();
