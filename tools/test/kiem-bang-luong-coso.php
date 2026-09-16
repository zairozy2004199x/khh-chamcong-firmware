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

/* 🔴 TẦNG "BẢNG CHUNG CẢ CHUỖI" ĐÃ BỎ — anh Thắng 16/09/2026: *"mỗi cơ sở 1 mức giá lương khác
   nhau"*. Cửa cũ phải CHỐI THẲNG, không được lặng lẽ ghi vào một nhánh chẳng ai đọc nữa. */
$r = VHCC_GiaGio::dat_chung( $U_KT, array( 'TN' => 24000 ) );
t( '🔴 khai bảng chung bị CHỐI (tầng ấy đã bỏ)', empty( $r['ok'] ), $r );
t( 'và câu chối chỉ đúng chỗ khai mới', false !== strpos( $r['error'], 'mỗi cơ sở' ), $r );

/* Mỗi cơ sở khai của mình. Số lấy từ khối Aeon Tân Phú trong file. */
$r = VHCC_GiaGio::dat_coso( $U_KT, 'AEON_TP', array( 'TN' => 24000, 'NV' => 22000, 'LT' => 24000 ) );
t( 'kế toán khai được đơn giá của Tân Phú', ! empty( $r['ok'] ), $r );
teq( 'TN ở Tân Phú ăn 24.000', 24000.0, VHCC_GiaGio::tra( 'AEON_TP', 'TN' )['gia'] );
teq( 'và nói rõ giá đến từ bảng của cơ sở', 'coso', VHCC_GiaGio::tra( 'AEON_TP', 'TN' )['tu'] );

/* 🔴 CƠ SỞ CHƯA KHAI THÌ TRỐNG, không mượn giá của cơ sở khác. Đây là cả lý do bỏ tầng chung:
   trước đây Bình Tân chưa ai khai vẫn ra tiền, ra bằng giá của Tân Phú, mà bảng vẫn đầy số. */
teq( '🔴 cơ sở KHÁC chưa khai thì vẫn CHƯA KHAI, không mượn giá Tân Phú', 'khong',
	VHCC_GiaGio::tra( 'KHO_LA', 'TN' )['tu'] );

/* Tầng cơ sở — Aeon Bình Tân: Lái Tàu 23.000, Lơ Tàu 21.000 (đúng file). */
$r = VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000, 'Lơ Tàu' => 21000 ) );
t( 'khai được đơn giá riêng của một cơ sở', ! empty( $r['ok'] ), $r );
teq( 'Lái Tàu ở Bình Tân: 23.000', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );
teq( 'Lơ Tàu ở Bình Tân: 21.000', 21000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lơ Tàu' )['gia'] );

/* 🔴 CÙNG CHỨC VỤ, KHÁC CƠ SỞ, KHÁC GIÁ — chính là lý do sổ phải có tầng cơ sở.
   Trong file: "NV" ở Aeon Tân Phú 22.000, ở Lotte Gò Vấp 23.000. */
VHCC_GiaGio::dat_coso( $U_KT, 'LOTTE_GV', array( 'NV' => 23000 ) );
teq( '🔴 NV ở Tân Phú vẫn 22.000 (bảng của Tân Phú)', 22000.0, VHCC_GiaGio::tra( 'AEON_TP', 'NV' )['gia'] );
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
/* Hai tầng, hết — không còn tầng nào đỡ bên dưới cơ sở nữa. */
VHCC_GiaGio::dat_coso( $U_KT, 'LOTTE_GV', array() );
teq( '🔴 cơ sở bỏ khai thì rơi thẳng về CHƯA KHAI, không có tầng nào đỡ', 'khong',
	VHCC_GiaGio::tra( 'LOTTE_GV', 'NV', 'GV_THUAN' )['tu'] );
VHCC_GiaGio::dat_coso( $U_KT, 'LOTTE_GV', array( 'NV' => 23000 ) );

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

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 WORKBOOK PHẢI BẢO EXCEL TÍNH LẠI LÚC MỞ.
	 *
	 * Anh Thắng 16/09/2026: *"xuất file lương, cột lương chính chưa nhân"* · *"total vẫn chưa
	 * có"* — kèm ảnh có thanh công thức hiện `=G9*H9` mà ô thì trống. Công thức có, giá trị
	 * đệm thì cố ý không có (phép ngay trên khoá luật ấy), nên thiếu `<calcPr>` là Excel đọc
	 * đệm, không thấy gì, in ô trống. Năm ô mỗi hàng: I · M · U · Y · Z.
	 *
	 * ⚠️ CANH CẢ VỊ TRÍ, không chỉ "có mặt". Lược đồ `CT_Workbook` xếp `calcPr` SAU `</sheets>`;
	 *    đặt sai chỗ là Excel từ chối mở cả tệp mà không nói vì sao — đúng cái bẫy mà mấy phép
	 *    `<cols>`/`<mergeCells>` ở trên đã dựng hàng rào.
	 * ══════════════════════════════════════════════════════════════════════════════════════*/
	$wbx = $z->getFromName( 'xl/workbook.xml' );
	t( '🔴 workbook bảo Excel tính lại lúc mở (fullCalcOnLoad)',
		false !== strpos( $wbx, 'fullCalcOnLoad="1"' ), $wbx );
	t( '🔴 <calcPr> đứng SAU </sheets>, đúng thứ tự lược đồ',
		strpos( $wbx, '<calcPr' ) > strpos( $wbx, '</sheets>' ), $wbx );

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
	false !== strpos( $h_kt, 'name="gg_cs_ten[' ), $h_kt );
t( '🔴 KHÔNG còn bảng chung cả chuỗi trên màn',
	false === strpos( $h_kt, 'name="gg_chung_ten[' ), 'bảng chung còn sót' );
t( 'mà nói thẳng là nó đã bỏ', false !== strpos( $h_kt, 'chung cả chuỗi đã bỏ' ), $h_kt );

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
	'gg_cs_ten' => array( 0 => 'Lái Tàu' ), 'gg_cs_gia' => array( 0 => '26500' ) );
ob_start(); VHCC_Web::phuc_vu(); $h_post = ob_get_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 bấm Lưu trên màn thì đơn giá vào sổ thật', 26500.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );
t( 'và màn KHÔNG báo câu chối về màn Hồ sơ',
	false === strpos( $h_post, 'thuộc màn Hồ sơ' ), substr( $h_post, 0, 600 ) );

/* Ba dòng trống ở cuối bảng của CƠ SỞ phải ĐỌC ĐƯỢC — vẽ ô mà không nhận là người ta gõ xong,
   thấy báo "đã lưu", rồi chức vụ biến mất không dấu vết. */
$_COOKIE = array( VHCC_Web::COOKIE => $tok_kt );
$_GET  = array( 'man' => 'cau_hinh', 'ccs' => 'AEON_BT' );
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_kt ), 'ccs' => 'AEON_BT',
	'gg_cs_ten' => array( 0 => 'Lái Tàu', 1 => 'Thu ngân mới' ),
	'gg_cs_gia' => array( 0 => '26500', 1 => '28000' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 dòng trống gõ thêm chức vụ mới thì LƯU THẬT', 28000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Thu ngân mới' )['gia'] );

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
VHCC_GiaGio::dat_coso( $U_KT, 'KHO_LA', array( 'Lái tàu' => 23000, 'Lơ tàu' => 21000, 'CHT' => 26000 ) );
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
	false === strpos( $h_gg_cht, 'name="gg_cs_gia[' ), 'lộ ô gõ đơn giá cho cửa hàng trưởng' );
t( 'và màn nói rõ ai mới sửa được',
	false !== strpos( $h_gg_cht, 'xem được' ), $h_gg_cht );
t( '🔴 kế toán CÓ ô gõ', false !== strpos( $h_gg_kt, 'name="gg_cs_gia[' ), 'kế toán không gõ được' );
t( '🔴 quản lý CÓ ô gõ', false !== strpos( $h_gg_ql, 'name="gg_cs_gia[' ), 'quản lý không gõ được' );

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
 * Phép này KHÔNG tự bịa nội dung biểu mẫu: nó BÓC đúng mấy cặp ô (tên, giá) mà màn vừa vẽ ra
 * rồi gửi lại y hệt, tức là mô phỏng đúng cú bấm Lưu của người thật.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array(
	'Lái Tàu' => 23000, 'Lơ Tàu' => 21000,
	'Nghỉ Hè'  => 19000,   // chức vụ CÓ KHAI mà tháng 8 không xếp ca ai
) );
teq( 'gieo: chức vụ tháng này không có giờ vẫn đang ở trong sổ', 19000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Nghỉ Hè' )['gia'] );

$h_rt = vhcc_man( 'KT_BL', 'Kế toán', '', $g_bl );
t( '🔴 màn CÓ vẽ ra dòng cho chức vụ tháng này không có giờ',
	false !== strpos( $h_rt, 'value="Nghỉ Hè"' ), 'không vẽ -> bấm Lưu là mất sổ' );

/* Bóc mọi cặp (gg_cs_ten[i], gg_cs_gia[i]) ra khỏi HTML vừa dựng — đúng thứ trình duyệt gửi. */
preg_match_all( '/name="gg_cs_ten\[(\d+)\]" value="([^"]*)"/', $h_rt, $m_t, PREG_SET_ORDER );
preg_match_all( '/name="gg_cs_gia\[(\d+)\]"[^>]*?value="([^"]*)"/', $h_rt, $m_g, PREG_SET_ORDER );
$ten_rt = array(); $gia_rt = array();
foreach ( $m_t as $x ) { $ten_rt[ (int) $x[1] ] = html_entity_decode( $x[2], ENT_QUOTES ); }
foreach ( $m_g as $x ) { $gia_rt[ (int) $x[1] ] = html_entity_decode( $x[2], ENT_QUOTES ); }
t( 'bóc được ít nhất ba dòng đơn giá từ màn', count( $ten_rt ) >= 3, $ten_rt );

$tok_rt = VHCC_Auth::phat_token( 'Chị KT', 'Kế toán', '', 'KT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_rt );
$_GET  = $g_bl;
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_rt ),
	'ccs' => 'AEON_BT', 'cth' => '2026-08',
	'gg_cs_ten' => $ten_rt, 'gg_cs_gia' => $gia_rt );
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
	'ccs' => 'AEON_BT',
	'gg_cs_ten' => array( 0 => 'Lái Tàu' ), 'gg_cs_gia' => array( 0 => '99000' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 CHT gõ tay POST đơn giá: KHÔNG ăn', 23000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );  // vẫn là giá vừa gieo, không phải 99.000

/* 🔴 LƯU BẢNG CƠ SỞ KHÔNG ĐƯỢC ĐÈ TÊN THÀNH KHOÁ. Ô tên gõ được và mang CÁCH VIẾT của người —
   bộ xử lý phải lấy đúng nó, không nhận bừa cái khoá làm tên. */
$tok_ch = VHCC_Auth::phat_token( 'Chị KT', 'Kế toán', '', 'KT_BL' );
$k_lt = VHCC_GiaGio::khoa_cv( 'Lái tàu' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_ch );
$_GET  = array( 'man' => 'cau_hinh', 'ccs' => 'KHO_LA' );
$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok_ch ), 'ccs' => 'KHO_LA',
	'gg_cs_ten' => array( 0 => 'Lái tàu' ), 'gg_cs_gia' => array( 0 => '23500' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( 'lưu bảng cơ sở thì giá đổi', 23500.0, VHCC_GiaGio::tra( 'KHO_LA', 'Lái tàu' )['gia'] );
teq( '🔴 và TÊN KHÔNG bị đè thành khoá', 'Lái tàu', VHCC_GiaGio::ten_cua( $k_lt ) );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 8. THÊM · XOÁ · SỬA TÊN ngay trong bảng đơn giá
 *
 * Anh Thắng 16/09/2026: *"thêm xóa , sửa tên đơn giá"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/** Gửi một lượt Lưu bảng đơn giá riêng của cơ sở, như trình duyệt gửi. */
function vhcc_luu_gia( $ma_nv, $vai, $coso, $cs, $ten, $gia, $xoa = array(), $th = '2026-08' ) {
	$tok = VHCC_Auth::phat_token( 'Người ' . $ma_nv, $vai, $coso, $ma_nv );
	$_COOKIE = array( VHCC_Web::COOKIE => $tok );
	$_GET  = array( 'man' => 'cham', 'ccs' => $cs, 'cth' => $th );
	$_POST = array( 'viec' => 'gia_gio', 'ky' => VHCC_Web::chu_ky( $tok ), 'ccs' => $cs,
		'cth' => $th, 'gg_cs_ten' => $ten, 'gg_cs_gia' => $gia, 'gg_cs_xoa' => $xoa );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_POST = array(); $_GET = array(); $_COOKIE = array();
	return $h;
}

/* ---- THÊM: dòng trống ở cuối bảng phải lưu thật ---- */
vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Lái Tàu', 1 => 'Bảo vệ đêm' ), array( 0 => '23000', 1 => '31000' ) );
teq( '🔴 THÊM: chức vụ gõ ở dòng trống vào sổ thật', 31000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Bảo vệ đêm' )['gia'] );
teq( 'và dòng cũ không suy suyển', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );

/* ---- SỬA TÊN: đổi tên là đổi khoá tra — khoá cũ đi, khoá mới tới ---- */
vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Lái Tàu', 1 => 'Bảo vệ ca đêm' ), array( 0 => '23000', 1 => '31000' ) );
teq( '🔴 SỬA TÊN: tên mới tra ra đúng giá cũ', 31000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Bảo vệ ca đêm' )['gia'] );
teq( '🔴 và tên CŨ không còn khai riêng nữa', 'khong',
	VHCC_GiaGio::tra( 'AEON_BT', 'Bảo vệ đêm' )['tu'] );
teq( 'tên đọc lại đúng cách viết mới', 'Bảo vệ ca đêm',
	VHCC_GiaGio::ten_cua( VHCC_GiaGio::khoa_cv( 'Bảo vệ ca đêm' ) ) );

/* ⚠️ ĐỔI HOA THƯỜNG / DẤU CÁCH KHÔNG PHẢI ĐỔI KHOÁ — chỉ là làm đẹp cách đọc, giá vẫn y nguyên. */
vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Lái Tàu', 1 => 'BẢO VỆ CA ĐÊM' ), array( 0 => '23000', 1 => '31000' ) );
teq( 'gõ lại hoa thường khác: giá KHÔNG đổi', 31000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'bảo vệ ca đêm' )['gia'] );

/* ---- XOÁ: tick ô Xoá thì dòng ấy rời sổ, dù ô giá vẫn còn số ---- */
vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Lái Tàu', 1 => 'BẢO VỆ CA ĐÊM' ), array( 0 => '23000', 1 => '31000' ),
	array( 1 => '1' ) );
teq( '🔴 XOÁ: tick Xoá thì bỏ khai riêng, DÙ ô giá vẫn còn số', 'khong',
	VHCC_GiaGio::tra( 'AEON_BT', 'Bảo vệ ca đêm' )['tu'] );
teq( 'dòng không tick thì còn nguyên', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );

/* 🔴 XOÁ Ở TẦNG CƠ SỞ LÀ RƠI VỀ "CHƯA KHAI", KHÔNG PHẢI THÀNH 0đ — và cũng không còn tầng chung
   nào đỡ bên dưới nữa (16/09/2026). */
vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Gác cổng' ), array( 0 => '25000' ) );
teq( 'gieo: cơ sở đang khai giá', 25000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Gác cổng' )['gia'] );
vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Gác cổng' ), array( 0 => '25000' ), array( 0 => '1' ) );
teq( '🔴 xoá thì về CHƯA KHAI, không thành 0đ', 'khong',
	VHCC_GiaGio::tra( 'AEON_BT', 'Gác cổng' )['tu'] );
teq( 'và tiền là 0 kèm lời nói rõ, chứ không phải một con số đoán', 0.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Gác cổng' )['gia'] );

/* ---- màn: ô tên có gõ được không, và ô Xoá có vẽ không ---- */
/* Gieo lại dòng không-giờ đã bị mấy phép xoá ở trên dọn đi, để phần soi màn có đủ hai loại. */
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000, 'Nghỉ Hè' => 19000 ) );
$b_rt_kiem = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$h_sx = vhcc_man( 'KT_BL', 'Kế toán', '', $g_bl );
t( 'có ô tick Xoá', false !== strpos( $h_sx, 'name="gg_cs_xoa[' ), $h_sx );
t( 'có ba dòng trống để THÊM chức vụ mới',
	substr_count( $h_sx, 'placeholder="+ chức vụ mới"' ) === 3, $h_sx );

/* 🔴 TÊN LẤY TỪ HỒ SƠ THÌ KHÔNG CHO GÕ ĐÈ.
   Dòng có giờ tháng này mang tên của HỒ SƠ. Gõ đè ở đây không đổi hồ sơ của ai — nó chỉ làm đơn
   giá quay sang một cái tên không ai mang, tức âm thầm bỏ giá của đúng mấy người đang hiện trên
   bảng lương. Nên ô ấy phải là ô ẨN, không phải ô gõ. */
/* ⚠️ BẮT CẢ THẺ, ĐỪNG BẮT TỪ `name=`. `type="hidden"` đứng TRƯỚC `name=` trong thẻ, nên một
   biểu thức khởi từ `name=` sẽ không bao giờ thấy nó — phép thử đỏ vì chính nó viết hụt, và
   nếu viết ngược lại (khẳng định "không ẩn") thì nó XANH trong khi mã đúng. */
$cv_hs = '';
foreach ( $b_rt_kiem['dong'] as $d_sx ) {
	if ( '' !== $d_sx['cv'] && 'thang' !== $d_sx['cheDo'] ) { $cv_hs = $d_sx['cv']; break; }
}
t( 'gieo: tìm được một chức vụ CÓ giờ để soi', '' !== $cv_hs, $cv_hs );
preg_match( '/<input[^>]*value="' . preg_quote( htmlspecialchars( $cv_hs, ENT_QUOTES ), '/' )
	. '"[^>]*>/', $h_sx, $m_sx );
t( '🔴 dòng CÓ giờ: ô tên là ô ẩn, không gõ đè được',
	isset( $m_sx[0] ) && false !== strpos( $m_sx[0], 'type="hidden"' ),
	isset( $m_sx[0] ) ? $m_sx[0] : 'không thấy ô tên của ' . $cv_hs );
/* Và ngược lại: dòng KHÔNG có giờ thì ô tên PHẢI gõ được — không có phép này thì phép trên
   vẫn xanh cả khi mọi ô tên đều bị khoá cứng. */
preg_match( '/<input[^>]*value="Nghỉ Hè"[^>]*>/u', $h_sx, $m_sx2 );
t( '🔴 dòng KHÔNG có giờ: ô tên GÕ ĐƯỢC',
	isset( $m_sx2[0] ) && false === strpos( $m_sx2[0], 'type="hidden"' ),
	isset( $m_sx2[0] ) ? $m_sx2[0] : 'không thấy ô tên của Nghỉ Hè' );
t( 'và màn chỉ đường sang Hồ sơ để đổi tên ấy',
	false !== strpos( $h_sx, 'tên lấy từ hồ sơ' ), $h_sx );

/* 🔴 CỬA HÀNG TRƯỞNG KHÔNG CÓ Ô XOÁ. */
$h_sx_cht = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_bl );
t( '🔴 cửa hàng trưởng KHÔNG thấy ô Xoá',
	false === strpos( $h_sx_cht, 'name="gg_cs_xoa[' ), 'lộ ô xoá cho cửa hàng trưởng' );

/* 🔴 SỬA TÊN LỆCH THÌ PHẢI NÓI RA, không chỉ báo "Đã lưu".
   Gõ tên chức vụ khác với hồ sơ là bảng lương mất tiền của cả nhóm mà câu báo vẫn xanh. */
$h_lech = vhcc_luu_gia( 'KT_BL', 'Kế toán', '', 'AEON_BT',
	array( 0 => 'Lai Tau Go Nham' ), array( 0 => '23000' ) );
t( '🔴 lưu xong màn CẢNH BÁO còn dòng chưa có đơn giá',
	false !== strpos( $h_lech, 'CHƯA có đơn giá' ), substr( $h_lech, 0, 900 ) );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 9. Ô "TÊN VIỆC" CHỌN SẴN TỪ SỔ ĐƠN GIÁ
 *
 * Anh Thắng 16/09/2026: *"Tên việc giờ chọn sẵn từ đơn giá"*.
 * Chữ trong ô ấy là KHOÁ TRA đơn giá, không phải cái nhãn — gõ lệch một chữ là dòng giờ ấy
 * lặng lẽ thành 0đ trong khi màn vẫn báo "đã lưu".
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* ---- lõi: gộp bảng cơ sở + bảng chung, KHÔNG gộp giá khai riêng người ---- */
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT',
	array( 'Lái Tàu' => 23000, 'Lơ Tàu' => 21000, 'MC' => 30000 ) );
VHCC_GiaGio::dat_coso( $U_KT, 'KHO_LA', array( 'Việc Của Kho' => 33000 ) );
VHCC_GiaGio::dat_nguoi( $U_KT, 'BT_MAN', array( 'Việc Của Riêng Mẫn' => 40000 ) );
$ds_tk = VHCC_GiaGio::ten_khai_cho( 'AEON_BT' );
t( 'có tên của bảng CƠ SỞ', in_array( 'Lái Tàu', $ds_tk, true ), $ds_tk );
/* 🔴 CHỈ CỦA CƠ SỞ NÀY. Tầng chung đã bỏ, nên tên khai ở cơ sở khác KHÔNG được lọt vào ô chọn
   của cơ sở này — lọt là mời người ta gán giờ theo giá của cửa hàng khác. */
t( '🔴 KHÔNG có tên khai ở cơ sở khác',
	! in_array( 'Việc Của Kho', $ds_tk, true ), $ds_tk );
t( '🔴 KHÔNG có giá khai riêng của một người',
	! in_array( 'Việc Của Riêng Mẫn', $ds_tk, true ),
	'giá riêng của người lọt vào danh sách việc của cả cửa hàng' );

/* ---- màn: là ô CHỌN, và nói luôn giá ---- */
$g_mo = array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08', 'clm' => 'BT_MAN' );
$h_cl = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_mo );
t( '🔴 ô tên việc là ô CHỌN, không phải ô gõ tay',
	false !== strpos( $h_cl, '<select name="cl_viec[0]"' ), $h_cl );
t( 'và bày đúng tên đã khai giá', false !== strpos( $h_cl, '>Lái Tàu — 23.000đ/h<' ), $h_cl );
/* ⚠️ Dòng của bảng chung nay mang thêm nhãn "· bảng chung" — xem mục 11. */
t( 'và tên nào cũng chỉ mang giá, không còn nhãn nguồn nào khác',
	false !== strpos( $h_cl, '>MC — 30.000đ/h<' ), $h_cl );
t( 'có dòng "— chọn việc —" để bỏ trống',
	false !== strpos( $h_cl, '<option value="">— chọn việc —</option>' ), $h_cl );
t( '🔴 giá khai riêng của người KHÔNG lọt vào ô chọn',
	false === strpos( $h_cl, 'Việc Của Riêng Mẫn' ), 'lộ giá riêng của người vào ô chọn' );

/* ---- 🔴 DÒNG ĐÃ LƯU MANG TÊN NGOÀI SỔ VẪN PHẢI CÓ TRONG Ô CHỌN ----
   Nếu thiếu, mở khối ra là ô chọn nhảy về "— chọn việc —", bấm Lưu một cái là MẤT dòng giờ ấy
   — mất một thứ người ta chưa hề đụng tới. */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'Việc Cũ Đã Bỏ', 'gio' => 3 ) ), 126.0 );
teq( 'gieo: dòng giờ mang tên ngoài sổ đã vào sổ chốt lương', 3.0,
	VHCC_ChotLuong::tong_cua( 'AEON_BT', '2026-08', 'BT_MAN' ) );
$h_cl2 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_mo );
/* ⚠️ CẮT LẤY ĐÚNG Ô CHỌN RỒI MỚI SOI. Quét cả trang thì "Việc Cũ Đã Bỏ" vẫn thấy — nó nằm
   trong chính bảng lương ở trên — nên phép thử xanh cả khi ô chọn rỗng trơn. */
preg_match( '/<select name="cl_viec\[0\]".*?<\/select>/us', $h_cl2, $m_sel );
$o_chon = isset( $m_sel[0] ) ? $m_sel[0] : '';
t( 'cắt được ô chọn ra để soi', '' !== $o_chon, substr( $h_cl2, 0, 300 ) );
t( '🔴 tên ngoài sổ VẪN có trong ô chọn (không thì bấm Lưu là mất dòng)',
	false !== strpos( $o_chon, 'Việc Cũ Đã Bỏ' ), $o_chon );
t( 'và nó đang được chọn sẵn',
	1 === preg_match( '/<option value="Việc Cũ Đã Bỏ"[^>]*selected/u', $o_chon ), $o_chon );
t( 'kèm lời nói thẳng là tên ấy chưa có giá',
	false !== strpos( $o_chon, 'Việc Cũ Đã Bỏ — CHƯA KHAI GIÁ' ), $o_chon );

/* ---- gửi thật: chọn một việc rồi Lưu thì vào sổ ---- */
$tok_cl = VHCC_Auth::phat_token( 'Trưởng BL', 'Cửa hàng trưởng', 'AEON_BT', 'CHT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_cl );
$_GET  = $g_mo;
$_POST = array( 'viec' => 'chot_luong', 'ky' => VHCC_Web::chu_ky( $tok_cl ),
	'ccs' => 'AEON_BT', 'cth' => '2026-08', 'cl_ma' => 'BT_MAN',
	'cl_viec' => array( 0 => 'Lơ Tàu' ), 'cl_gio' => array( 0 => '5' ) );
ob_start(); VHCC_Web::phuc_vu(); ob_end_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 chọn việc rồi Lưu: vào sổ thật', 5.0,
	VHCC_ChotLuong::tong_cua( 'AEON_BT', '2026-08', 'BT_MAN' ) );
$d_cl = VHCC_ChotLuong::cua( 'AEON_BT', '2026-08', 'BT_MAN' );
teq( 'và lưu đúng cái tên đã chọn', 'Lơ Tàu', $d_cl[0]['viec'] );

/* ---- ⚠️ CHƯA KHAI GIÁ NÀO THÌ QUAY VỀ Ô GÕ TAY ----
   Một ô chọn rỗng là khối chết: mở ra không chọn được gì, không câu nào nói vì sao. */
VHCC_GiaGio::dat_chung( $U_KT, array() );
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array() );
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0 );
teq( 'gieo: sổ đơn giá của cơ sở này nay rỗng', 0, count( VHCC_GiaGio::ten_khai_cho( 'AEON_BT' ) ) );
$h_cl3 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_mo );
t( '🔴 sổ rỗng thì trả lại ô GÕ TAY, không bày ô chọn rỗng',
	false !== strpos( $h_cl3, 'name="cl_viec[0]" placeholder="tên việc' )
	&& false === strpos( $h_cl3, '<select name="cl_viec[0]"' ), $h_cl3 );
t( 'và nói rõ vì sao, chỉ đúng chỗ đi khai',
	false !== strpos( $h_cl3, 'chưa khai đơn giá nào' ), $h_cl3 );

/* Trả sổ về như cũ cho phép thử sau (nếu có ai thêm) khỏi chạy trên sổ rỗng. */
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000 ) );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 10. VIỆC CHÍNH — chọn một cái, phần giờ còn lại tự ăn theo giá của nó
 *
 * Anh Thắng 16/09/2026: *"Mặc định nhân viên nếu làm 1 công việc thì chọn xong, giờ tự chốt,
 * hoặc chọn cái đầu tiên làm giờ chính, cái giờ sau nhập thêm thì giờ chính giảm đi"*.
 * Và, trước cột Ghi chú bảy dòng "CHƯA KHAI ĐƠN GIÁ" y hệt nhau: *"Chỗ này không cần khai"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000, 'MC' => 30000 ) );
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0, '' );

/* 🔴 CHƯA CHỌN THÌ VẪN LÙI VỀ TÊN HỒ SƠ — đây chính là cảnh anh Thắng đang gặp: hồ sơ ghi tên
   MẢNG ("Khu vui chơi"), không ai khai giá cho nó, nên dòng chính đứng im ở CHƯA KHAI. */
$b_vc0 = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$d_vc0 = null;
foreach ( $b_vc0['dong'] as $x ) { if ( $x['ma'] === 'BT_MAN' && $x['laChinh'] ) { $d_vc0 = $x; break; } }
t( 'gieo: tìm được dòng chính của BT_MAN', null !== $d_vc0, 'không thấy dòng chính' );
teq( 'chưa chọn việc chính: tên lấy từ hồ sơ', VHCC_BangLuong::chuc_vu_chinh(
	VHCC_NhanSu::ho_so( 'BT_MAN' ) ), $d_vc0['cv'] );

/* ---- 🔴 "LÀM 1 CÔNG VIỆC THÌ CHỌN XONG, GIỜ TỰ CHỐT" ---- */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0, 'Lái Tàu' );
teq( 'việc chính vào sổ', 'Lái Tàu', VHCC_ChotLuong::viec_chinh( 'AEON_BT', '2026-08', 'BT_MAN' ) );
$b_vc1 = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$d_vc1 = null;
foreach ( $b_vc1['dong'] as $x ) { if ( $x['ma'] === 'BT_MAN' && $x['laChinh'] ) { $d_vc1 = $x; break; } }
teq( '🔴 dòng chính mang TÊN VỪA CHỌN, không phải tên mảng trong hồ sơ', 'Lái Tàu', $d_vc1['cv'] );
teq( '🔴 và CẢ giờ chấm công dồn vào đó', 126.0, $d_vc1['gio'] );
teq( '🔴 nên nó tra ra giá thật, hết CHƯA KHAI', 23000.0, $d_vc1['gia'] );
teq( 'lương chính = 126 × 23.000', 2898000.0, $d_vc1['luongChinh'] );
teq( 'và bảng KHÔNG còn đếm dòng nào thiếu giá cho người này', 'coso', $d_vc1['giaTu'] );

/* ---- 🔴 "CÁI GIỜ SAU NHẬP THÊM THÌ GIỜ CHÍNH GIẢM ĐI" ---- */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => 6 ) ), 126.0, 'Lái Tàu' );
$b_vc2 = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$d_vc2 = null; $d_mc = null;
foreach ( $b_vc2['dong'] as $x ) {
	if ( $x['ma'] !== 'BT_MAN' ) { continue; }
	if ( $x['laChinh'] ) { $d_vc2 = $x; } elseif ( 'MC' === $x['cv'] ) { $d_mc = $x; }
}
teq( '🔴 giờ chính co lại đúng 6 giờ', 120.0, $d_vc2['gio'] );
teq( 'và dòng MC ăn 6 giờ', 6.0, $d_mc['gio'] );
teq( 'MC ăn giá của MC', 30000.0, $d_mc['gia'] );
teq( 'lương việc chính = 120 × 23.000', 2760000.0, $d_vc2['luongChinh'] );
teq( 'lương MC = 6 × 30.000', 180000.0, $d_mc['luongChinh'] );

/* ---- ⚠️ `null` = KHÔNG NÓI GÌ VỀ VIỆC CHÍNH, đừng coi là bỏ khai ----
   Mọi lượt lưu giờ khác mà cũng xoá luôn việc chính thì gõ thêm một dòng MC là mất giá của cả
   phần giờ còn lại — mất tiền vì một thứ người ta không hề đụng tới. */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => 8 ) ), 126.0 );
teq( '🔴 lưu giờ khác mà không gửi ô việc chính: việc chính CÒN NGUYÊN', 'Lái Tàu',
	VHCC_ChotLuong::viec_chinh( 'AEON_BT', '2026-08', 'BT_MAN' ) );
/* Còn gửi chuỗi rỗng thì đúng là bỏ khai — hai ý khác nhau, hai giá trị khác nhau. */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0, '' );
teq( 'gửi chuỗi rỗng thì mới là bỏ khai', '',
	VHCC_ChotLuong::viec_chinh( 'AEON_BT', '2026-08', 'BT_MAN' ) );

/* ---- màn: ô chọn việc chính, và ô giờ của nó KHÔNG gõ tay được ---- */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => 6 ) ), 126.0, 'Lái Tàu' );
$h_vc = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08', 'clm' => 'BT_MAN' ) );
t( '🔴 có ô CHỌN việc chính', false !== strpos( $h_vc, '<select name="cl_chinh"' ), $h_vc );
preg_match( '/<select name="cl_chinh".*?<\/select>/us', $h_vc, $m_vc );
$o_vc = isset( $m_vc[0] ) ? $m_vc[0] : '';
t( 'và việc đang khai được chọn sẵn',
	1 === preg_match( '/<option value="Lái Tàu"[^>]*selected/u', $o_vc ), $o_vc );
t( '🔴 ô giờ của việc chính là ô CHỈ ĐỌC — nó là kết quả, không phải thứ gõ vào',
	false !== strpos( $h_vc, 'value="120,00" readonly' ), $h_vc );

/* ---- 🔴 CỘT GHI CHÚ THÔI LẶP "CHƯA KHAI ĐƠN GIÁ" ----
   Cùng một sự thật đang nói ba lần: dải đỏ đầu bảng, dòng nhuộm đỏ, ô Tiền/h gạch ngang. */
$h_gc = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
preg_match( '/<table class="b">(?:(?!<\/table>).)*Thực nhận.*?<\/table>/us', $h_gc, $m_gc );
$bang_gc = isset( $m_gc[0] ) ? $m_gc[0] : $h_gc;
t( '🔴 trong bảng lương KHÔNG còn dòng nào lặp chữ "CHƯA KHAI ĐƠN GIÁ"',
	false === strpos( $bang_gc, 'CHƯA KHAI ĐƠN GIÁ' ), substr( $bang_gc, 0, 400 ) );
/* ⚠️ NHƯNG KHÔNG PHẢI GIẤU ĐI. Dải đỏ đầu bảng vẫn phải đếm và vẫn phải kêu — không có phép
   này thì phép trên xanh cả khi mã bịt miệng luôn cả lời cảnh báo. */
t( '🔴 dải cảnh báo đầu bảng VẪN đếm và VẪN kêu',
	false !== strpos( $h_gc, 'dòng chưa khai đơn giá giờ' ), substr( $h_gc, 0, 400 ) );
t( 'và ghi chú riêng của từng dòng vẫn còn (lượt thiếu giờ)',
	false !== strpos( $h_gc, 'lượt thiếu giờ' ) || false === strpos( $h_gc, 'thieuGio' ), $bang_gc );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 11. MỖI CƠ SỞ MỘT MỨC GIÁ — KHÔNG CƠ SỞ NÀO MƯỢN GIÁ CỦA CƠ SỞ NÀO
 *
 * Anh Thắng 16/09/2026 mở ô chọn việc, thấy CẢ BA dòng đều mang nhãn `· bảng chung`:
 * *"mỗi cơ sở 1 mức giá lương khác nhau"*. Tầng chung cả chuỗi bỏ từ đây.
 *
 * Trước đó anh đã hỏi *"sinh thừa từ đâu"* khi thấy `cht` đứng cạnh `Cửa Hàng Trưởng`: hai
 * chuỗi khác nhau ra hai khoá khác nhau (`cht` ≠ `cuahangtruong`) nên thành hai dòng, còn
 * `laitau` và `Lái Tàu` cùng khoá nên gộp. Phép canh khoá vẫn giữ — nó không dính tầng nào.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

teq( '🔴 "cht" và "Cửa Hàng Trưởng" ra HAI khoá khác nhau',
	false, VHCC_GiaGio::khoa_cv( 'cht' ) === VHCC_GiaGio::khoa_cv( 'Cửa Hàng Trưởng' ) );
teq( '⚠️ còn "laitau" và "Lái Tàu" thì CÙNG khoá — nên chúng gộp, không nhân đôi',
	VHCC_GiaGio::khoa_cv( 'laitau' ), VHCC_GiaGio::khoa_cv( 'Lái Tàu' ) );

/* Hai cơ sở, hai mức giá cho CÙNG một chức vụ — đúng câu anh Thắng nói. */
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000 ) );
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_TP', array( 'Lái Tàu' => 27000 ) );
teq( 'Lái Tàu ở Bình Tân: 23.000', 23000.0, VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );
teq( 'Lái Tàu ở Tân Phú: 27.000', 27000.0, VHCC_GiaGio::tra( 'AEON_TP', 'Lái Tàu' )['gia'] );

/* 🔴 CƠ SỞ THỨ BA CHƯA KHAI THÌ TRỐNG — không mượn của bên nào, dù cả hai bên đều có giá. */
VHCC_GiaGio::dat_coso( $U_KT, 'KHO_LA', array() );
teq( '🔴 cơ sở chưa khai: CHƯA KHAI, không mượn giá của ai', 'khong',
	VHCC_GiaGio::tra( 'KHO_LA', 'Lái Tàu' )['tu'] );
teq( 'và tiền là 0, không phải một con số đoán', 0.0,
	VHCC_GiaGio::tra( 'KHO_LA', 'Lái Tàu' )['gia'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 DỮ LIỆU CŨ CÒN NẰM TRONG NHÁNH `chung` PHẢI BỊ LỜ ĐI HOÀN TOÀN.
 *
 * Đây là phép quan trọng nhất của cả mục này, và là phép mà bản đầu KHÔNG có: sổ thật của anh
 * Thắng ĐANG có `cht · laitau · lotau` nằm trong nhánh ấy. Nếu `tra()` còn ngó tới, thì mọi cơ
 * sở chưa khai vẫn lặng lẽ ăn giá cũ — đúng cái vừa bỏ, chỉ khác là không còn chỗ nào nhìn thấy
 * để mà sửa.
 *
 * ⚠️ PHẢI GHI THẲNG VÀO SỔ, KHÔNG QUA `dat_chung()`. Cửa ấy nay chối, nên đi qua nó thì nhánh
 *    `chung` luôn rỗng và phép thử xanh mà chẳng chứng minh gì — đột biến trả lại tầng chung
 *    trong `tra()` VẪN xanh. Đã thử đúng như vậy, nó không đỏ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/
$so_thuong = VHCC_GiaGio::so();
$so_thuong['chung'] = array( VHCC_GiaGio::khoa_cv( 'Lái Tàu' ) => 99000 );
VHCC_Luong::dat_cai_dat( VHCC_GiaGio::O, $so_thuong, $U_KT );
teq( 'gieo: nhánh chung cũ có số thật trong sổ', 99000.0,
	(float) VHCC_GiaGio::so()['chung'][ VHCC_GiaGio::khoa_cv( 'Lái Tàu' ) ] );
teq( '🔴 cơ sở CHƯA khai vẫn là CHƯA KHAI, không ăn số cũ trong nhánh chung', 'khong',
	VHCC_GiaGio::tra( 'KHO_LA', 'Lái Tàu' )['tu'] );
teq( 'và tiền vẫn là 0', 0.0, VHCC_GiaGio::tra( 'KHO_LA', 'Lái Tàu' )['gia'] );
teq( '🔴 cơ sở ĐÃ khai thì ăn giá CỦA NÓ, không bị số cũ đè lên', 23000.0,
	VHCC_GiaGio::tra( 'AEON_BT', 'Lái Tàu' )['gia'] );
/* Và số cũ ấy KHÔNG lọt vào ô chọn việc của cơ sở nào. */
t( '🔴 số cũ không lọt vào danh sách việc của cơ sở chưa khai',
	array() === VHCC_GiaGio::ten_khai_cho( 'KHO_LA' ), VHCC_GiaGio::ten_khai_cho( 'KHO_LA' ) );
/* ⚠️ NHƯNG KHÔNG XOÁ NÓ ĐI. Bỏ một tầng là việc của mã; xoá con số người ta đã gõ là việc khác. */
teq( '⚠️ và số cũ vẫn nằm im trong sổ, không bị xoá', 99000.0,
	(float) VHCC_GiaGio::so()['chung'][ VHCC_GiaGio::khoa_cv( 'Lái Tàu' ) ] );
teq( 'màn Cấu hình kể nó ra để còn khai lại', 99000.0,
	(float) VHCC_GiaGio::chung_cu()['Lái Tàu'] );

/* Trên màn: ô chọn việc không còn nhãn nguồn nào, vì chỉ còn một nguồn. */
$h_td = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08', 'clm' => 'BT_MAN' ) );
t( '🔴 ô chọn KHÔNG còn nhãn "· bảng chung"',
	false === strpos( $h_td, '· bảng chung' ), 'nhãn cũ còn sót' );
t( 'khối đơn giá KHÔNG còn kể "nhận từ bảng chung"',
	false === strpos( $h_td, 'từ <b>bảng chung cả chuỗi</b>' ), 'đoạn kể cũ còn sót' );
/* ⚠️ Nút Lưu chỉ vẽ cho người SỬA được đơn giá — cửa hàng trưởng chỉ xem, nên phải dựng bằng
   phiên Kế toán. Dùng nhầm phiên thì phép thử đỏ vì một lý do chẳng liên quan tới điều đang canh. */
$h_td_kt = vhcc_man( 'KT_BL', 'Kế toán', '',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
t( 'và nút Lưu nói rõ đây là TOÀN BỘ giá của cơ sở',
	false !== strpos( $h_td_kt, 'TOÀN BỘ đơn giá đang' ), $h_td_kt );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 12. CỘT LƯƠNG NGAY SAU CỘT TỔNG CỦA LƯỚI
 *
 * Anh Thắng 16/09/2026: *"Sau cột tổng à tổng lương tháng này"*.
 * Nhìn 149h8m mà không biết nó ra bao nhiêu tiền thì vẫn phải cuộn xuống bảng lương, mà cuộn
 * xuống rồi lại mất dấu người mình đang soi.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

VHCC_GiaGio::dat_chung( $U_KT, array() );
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000 ) );
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0, 'Lái Tàu' );

$h_lc = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
t( '🔴 lưới có cột LƯƠNG ngay sau cột TỔNG',
	1 === preg_match( '/<th>TỔNG<div[^>]*>giờ<\/div><\/th><th>LƯƠNG<\/th>/u', $h_lc ),
	substr( $h_lc, 0, 300 ) );
/* 🔴 ĐƠN VỊ PHẢI ĐỨNG Ở ĐẦU CỘT. Ô chỉ ghi `211,50` — không nói đơn vị thì người ta đọc thành
   "211 giờ 50 phút", đúng cái nhầm anh Thắng đã mắc. */
t( '🔴 đầu cột TỔNG nói rõ đơn vị là "giờ"',
	false !== strpos( $h_lc, '<th>TỔNG<div style="font-weight:400;opacity:.7">giờ</div></th>' ),
	'đầu cột TỔNG không ghi đơn vị' );
t( '🔴 và người đã đủ giá hiện ra TIỀN', false !== strpos( $h_lc, '2.898.000đ' ), 'không thấy tiền của BT_MAN' );

/* 🔴 CHƯA ĐỦ GIÁ THÌ NÓI "THIẾU GIÁ", ĐỪNG IN MỘT CON SỐ NHỎ HƠN SỰ THẬT.
   Cộng bừa mấy dòng đã có giá rồi in ra là một con số trông rất bình thường — và nó THIẾU tiền
   của dòng chưa khai. Không ai nghi một ô có số. */
t( '🔴 người còn dòng chưa khai giá thì ô ghi "thiếu giá", không ghi số',
	false !== strpos( $h_lc, '>thiếu giá<' ), $h_lc );

/* 🔴 SỐ CỘT CỦA MỌI HÀNG PHẢI BẰNG SỐ CỘT TIÊU ĐỀ.
   Thêm một cột mà quên một nhánh vẽ hàng là bảng lệch cột — trình duyệt vẫn dựng ra, chỉ là mọi
   con số trượt sang một ô, và không có gì báo. Đây là cách hỏng kinh điển của việc thêm cột. */
preg_match( '/<table class="cc">.*?<\/table>/us', $h_lc, $m_lc );
$bang_lc = isset( $m_lc[0] ) ? $m_lc[0] : '';
t( 'cắt được lưới ra để đếm cột', '' !== $bang_lc, substr( $h_lc, 0, 200 ) );
/* ⚠️ ĐẾM BẰNG `<th ` HOẶC `<th>`, ĐỪNG ĐẾM CHUỖI `<th` TRẦN — `<thead>` cũng chứa nó, nên phép
   thử đội thêm một cột rồi báo "lệch" trong khi bảng cân. Chính nó làm em tưởng mã hỏng. */
$so_th = preg_match_all( '/<th[ >]/', $bang_lc );
preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/us', $bang_lc, $m_tr, PREG_SET_ORDER );
$lech_cot = array();
foreach ( $m_tr as $tr_x ) {
	if ( false !== strpos( $tr_x[1], '<th' ) ) { continue; }          // hàng tiêu đề
	if ( false !== strpos( $tr_x[1], 'colspan' ) ) { continue; }      // hàng sửa / hàng tổng
	$n_td = preg_match_all( '/<td[ >]/', $tr_x[1] );
	if ( 0 === $n_td ) { continue; }
	if ( $n_td !== $so_th ) { $lech_cot[] = $n_td; }
}
teq( '🔴 không hàng nào lệch cột so với tiêu đề (' . $so_th . ' cột)', 0, count( $lech_cot ) );

/* Hàng tổng cuối bảng cũng phải có ô tiền — không thì thêm cột xong bảng thọt một ô ở đáy. */
/* ⚠️ Ô ấy in TIỀN khi đủ giá, và in chữ "chưa đủ giá" khi chưa — nhận cả hai, vì thứ đang canh
   là "hàng tổng có ô thứ tư", không phải "ô ấy ghi gì". */
t( 'hàng tổng cuối lưới có ô lương',
	1 === preg_match( '/<tr class="tong">.*?người.*?(đ<\/b>|chưa đủ giá)/us', $bang_lc ), $bang_lc );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 13. BẤM TÊN LÀ MỞ KHỐI GIỜ NGAY DƯỚI HÀNG — tách theo từng ca
 *
 * Anh Thắng 16/09/2026: *"Chọn tên nhân viên ra giờ làm và các tổng giờ các ca luôn được không,
 * chứ bấm nhả qua nhảy lại khá nhức mặt"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

VHCC_Ca::luu( $U_KT, 'AEON_BT', array(
	array( 'ten' => 'Ca 1', 'tu' => '06:00', 'den' => '14:00', 'tuW' => '', 'denW' => '' ),
	array( 'ten' => 'Ca 2', 'tu' => '14:00', 'den' => '22:00', 'tuW' => '', 'denW' => '' ),
) );

$g_xn = array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' );
$h_x0 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_xn );
t( 'chưa bấm ai thì KHÔNG có khối giờ nào mở sẵn',
	false === strpos( $h_x0, 'id="xn' ), 'khối mở sẵn khi chưa bấm' );
t( '🔴 tên người là liên kết mở khối giờ, mang đúng mã',
	1 === preg_match( '/<a class="ten-nv" href="[^"]*xng=BT_MAN[^"]*"/', $h_x0 ), $h_x0 );

$h_x1 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_xn + array( 'xng' => 'BT_MAN' ) );
t( '🔴 bấm tên thì khối giờ mở ra', false !== strpos( $h_x1, 'id="xn' ), $h_x1 );
t( 'và nó mở ĐÚNG người vừa bấm',
	false !== strpos( $h_x1, 'id="xn' . substr( md5( 'BT_MAN' ), 0, 8 ) . '"' ), $h_x1 );

/* ⚠️ Cắt lấy đúng khối rồi mới soi — tên người và số giờ cũng nằm rải khắp lưới. */
preg_match( '/<tr class="hang-sua">.*?<\/tr>/us', $h_x1, $m_xn );
$khoi_xn = isset( $m_xn[0] ) ? $m_xn[0] : '';
t( 'cắt được khối ra để soi', '' !== $khoi_xn, substr( $h_x1, 0, 200 ) );
t( '🔴 khối kể TỔNG GIỜ TỪNG CA — Ca 1',
	false !== strpos( $khoi_xn, 'Ca 1' ), $khoi_xn );
t( '🔴 và Ca 2', false !== strpos( $khoi_xn, 'Ca 2' ), $khoi_xn );
t( 'kèm số ngày có chấm', 1 === preg_match( '/\d+ ngày có chấm/u', $khoi_xn ), $khoi_xn );

/* 🔴 CHỈ MỞ MỘT NGƯỜI. Mở hết là lưới dài gấp đôi và mất luôn ý nghĩa của việc bấm. */
teq( '🔴 mỗi lượt chỉ MỘT khối mở ra', 1, substr_count( $h_x1, 'class="hang-sua"' ) );

/* 🔴 BẤM LẠI LÀ ĐÓNG — không thì mở ra rồi phải đi tìm cách tắt. */
t( 'đang mở thì liên kết đổi thành đường ĐÓNG (bỏ xng)',
	1 === preg_match( '/<a class="ten-nv" href="[^"]*"[^>]*title="Đóng khối giờ"/u', $h_x1 ), $h_x1 );

/* 🔴 HỒ SƠ VẪN TỚI ĐƯỢC, nhưng chỉ cho ai có quyền — cửa hàng trưởng thì không. */
t( '🔴 cửa hàng trưởng KHÔNG thấy đường sang hồ sơ',
	false === strpos( $h_x1, 'man=ho_so' ), 'lộ đường hồ sơ cho cửa hàng trưởng' );
$h_x_kt = vhcc_man( 'KT_BL', 'Kế toán', '', $g_xn + array( 'xng' => 'BT_MAN' ) );
t( 'kế toán thì thấy, và trỏ đúng người',
	1 === preg_match( '/href="[^"]*man=ho_so[^"]*sua=BT_MAN[^"]*"[^>]*>hồ sơ/u', $h_x_kt ), $h_x_kt );

/* 🔴 PHÚT NGOÀI MỌI CA PHẢI KỂ RIÊNG, KHÔNG NHÉT VÀO CA NÀO.
   Cộng nó vào một ca là bịa; bỏ đi thì mấy ca cộng lại KHÁC tổng của hàng, và người đọc mất
   mười phút đi tìm xem thiếu ở đâu. Ca của AEON_BT là 06:00–22:00, nên một lượt 23:00→23:45
   nằm ngoài cả hai. */
/** Bóc số "Ngoài ca" của khối bấm-tên. Trả null khi không thấy — để phép thử nói được là
 *  nó KHÔNG thấy, thay vì lặng lẽ coi như 0 rồi xanh nhầm. */
function vhcc_ngoai_ca( $h ) {
	if ( ! preg_match( '/Ngoài ca<\/label><div><b[^>]*>([^<]+)<\/b>/u', $h, $m ) ) { return null; }
	return (float) str_replace( ',', '.', str_replace( '.', '', trim( $m[1] ) ) );
}
/* ⚠️ ĐO BẰNG HIỆU SỐ, KHÔNG VIẾT CỨNG. Cơ sở này đã có sẵn mấy lượt ngoài ca từ mấy mục trên;
   khẳng định "Ngoài ca = 0,75" là khẳng định về CẢ FIXTURE chứ không phải về lượt vừa gieo, và
   nó sẽ vỡ mỗi lần mục khác thêm một lượt. */
$h_ng_truoc = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_xn + array( 'xng' => 'BT_MAN' ) );
$ngoai_truoc = vhcc_ngoai_ca( $h_ng_truoc );

$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'BT_MAN', 'ho_ten' => '', 'coso' => 'AEON_BT', 'ngay' => '2026-08-28',
	'gio_vao_giay' => 23 * 3600, 'gio_ra_giay' => 23 * 3600 + 45 * 60,
	'hau_to' => '', 'nguon' => 'may' ) );
$h_ngoai = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_xn + array( 'xng' => 'BT_MAN' ) );
preg_match( '/<tr class="hang-sua">.*?<\/tr>/us', $h_ngoai, $m_ng2 );
$khoi_ng = isset( $m_ng2[0] ) ? $m_ng2[0] : '';
t( '🔴 phút ngoài mọi ca được kể RIÊNG ở mục "Ngoài ca"',
	false !== strpos( $khoi_ng, 'Ngoài ca' ), $khoi_ng );
$ngoai_sau = vhcc_ngoai_ca( $h_ngoai );
t( 'bóc được số Ngoài ca ở cả hai lần vẽ', null !== $ngoai_truoc && null !== $ngoai_sau,
	"truoc=" . var_export( $ngoai_truoc, true ) . " sau=" . var_export( $ngoai_sau, true ) );
teq( '🔴 và kể đúng 45 phút = 0,75 giờ', 0.75,
	round( (float) $ngoai_sau - (float) $ngoai_truoc, 2 ) );
/* 🔴 IN THẬP PHÂN, KHÔNG PHẢI `Xh Ym` — đây là con số đem đối chiếu với bảng lương. */
t( '🔴 khối bấm tên in giờ THẬP PHÂN, không còn "45m"',
	false === strpos( $khoi_ng, '45m' ), $khoi_ng );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 14. ẨN MỘT MÃ KHỎI BẢNG CÔNG — rác thử máy, thứ "chờ trả về" không đụng tới được
 *
 * Anh Thắng 16/09/2026: *"1 số nhân viên chạy test trên máy chấm công cũ... mình sẽ ẩn nó đi,
 * khi ẩn thì nó không ảnh hưởng đến bảng công"*, rồi: *"bấm chờ trả về thì nó không được, nên
 * cần ẩn đi"* — kèm ảnh **"Không xong. Không tìm thấy hồ sơ 0000000777."**
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

/* 🔴 GIEO ĐÚNG CẢNH THẬT: một mã máy thô, KHÔNG có hồ sơ, có lượt chấm.
   ⚠️ MÃ BỊA (`0000...`), không chép mã thật trong ảnh anh Thắng gửi — kho này CÔNG KHAI, và
      chính bài kiểm ở mục 5 đã bắt em khi vừa dán mã thật vào. Cảnh thử không cần mã thật:
      thứ đang thử là "mã KHÔNG có hồ sơ", không phải mã nào. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => '0000000777', 'ho_ten' => '', 'coso' => 'AEON_BT', 'ngay' => '2026-08-03',
	'gio_vao_giay' => 7 * 3600, 'gio_ra_giay' => 12 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
t( 'gieo: mã rác KHÔNG có hồ sơ', ! VHCC_NhanSu::ho_so( '0000000777' ), 'mã rác lại có hồ sơ' );

$U_CHT_BT = array( 'name' => 'Trưởng BL', 'role' => 'Cửa hàng trưởng', 'coso' => 'AEON_BT' );
$g_an = array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' );

$h_a0 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_an );
t( 'chưa ẩn: mã rác CÓ trong lưới', false !== strpos( $h_a0, '0000000777' ), 'không thấy mã rác' );
t( '🔴 và có nút Ẩn cho nó', false !== strpos( $h_a0, 'name="an_ma" value="0000000777"' ), $h_a0 );

/* 🔴 ẨN ĐƯỢC DÙ KHÔNG CÓ HỒ SƠ — cả vấn đề nằm ở đây. */
$r_an = VHCC_An::dat( $U_CHT_BT, 'AEON_BT', '0000000777', true );
t( '🔴 ẩn được một mã KHÔNG có hồ sơ', ! empty( $r_an['ok'] ), $r_an );
t( 'sổ ghi nhận', VHCC_An::la_an( 'AEON_BT', '0000000777' ), 'sổ không ghi' );

$h_a1 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_an );
preg_match( '/<table class="cc">.*?<\/table>/us', $h_a1, $m_a1 );
$luoi_a = isset( $m_a1[0] ) ? $m_a1[0] : '';
t( 'cắt được lưới ra để soi', '' !== $luoi_a, substr( $h_a1, 0, 200 ) );
t( '🔴 ẩn xong: mã rác KHÔNG còn trong lưới',
	false === strpos( $luoi_a, '0000000777' ), 'vẫn còn trong lưới' );

/* 🔴 "KHÔNG ẢNH HƯỞNG ĐẾN BẢNG CÔNG" = RA KHỎI CẢ BẢNG LƯƠNG, không chỉ khuất mắt. */
$b_an = VHCC_BangLuong::dung( 'AEON_BT', '2026-08' );
$co_rac = false;
foreach ( $b_an['dong'] as $d_a ) { if ( '0000000777' === $d_a['ma'] ) { $co_rac = true; } }
t( '🔴 và KHÔNG còn trong bảng lương (hết đòi đơn giá)', ! $co_rac, 'vẫn đứng trong bảng lương' );

/* 🔴 ẨN KHÔNG PHẢI XOÁ — lượt chấm vẫn nằm nguyên trong sổ. */
$con = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='0000000777'" );
teq( '🔴 lượt chấm VẪN CÒN NGUYÊN trong sổ, không bị xoá', 1, $con );

/* 🔴 PHẢI CÓ CHỖ NHÌN THẤY THỨ ĐANG ẨN — không thì ẩn đúng bằng xoá. */
t( '🔴 màn kể ra mã đang ẩn', false !== strpos( $h_a1, 'mã đang ẩn' ), $h_a1 );
t( 'và gọi đúng tên nó', false !== strpos( $h_a1, '<code>0000000777</code>' ), $h_a1 );
/* 🔴 IN ĐÚNG MỘT LẦN. Khoá mảng PHP tự ép chuỗi toàn số về kiểu SỐ, nên so `!==` với tên (vẫn
   là chuỗi) luôn đúng và màn in cái mã ra HAI LẦN liền nhau — anh Thắng nhìn thấy đúng dòng ấy.
   ⚠️ Mã gieo ở trên (`0000000777`) có số 0 đứng đầu nên PHP KHÔNG ép về số; phải thử thêm một
      mã toàn số KHÔNG có số 0 đầu thì mới đúng cảnh hỏng. Và phải NGẮN — chốt chống dán số căn
      cước ở mục 5 chối mọi dãy 9+ chữ số không mở đầu bằng `0000`. */
/* ⚠️ `ho_ten` PHẢI BẰNG ĐÚNG CÁI MÃ — đó mới là cảnh thật: máy đẩy về một mã không có hồ sơ,
   lưới không có tên nào để hiện nên lấy chính cái mã làm tên. Gieo `ho_ten` rỗng thì không có
   gì để in lặp, và phép thử xanh cả khi lỗi còn nguyên (đã thử đột biến, nó KHÔNG đỏ). */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => '777001', 'ho_ten' => '777001', 'coso' => 'AEON_BT', 'ngay' => '2026-08-04',
	'gio_vao_giay' => 7 * 3600, 'gio_ra_giay' => 9 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
VHCC_An::dat( $U_CHT_BT, 'AEON_BT', '777001', true );
$h_a1b = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_an );
t( 'gieo: mã ấy có ra dòng đang ẩn', false !== strpos( $h_a1b, '<code>777001</code>' ), $h_a1b );
teq( '🔴 mã toàn số in ĐÚNG MỘT LẦN, không lặp', 0,
	substr_count( $h_a1b, '<code>777001</code> 777001' ) );
VHCC_An::dat( $U_CHT_BT, 'AEON_BT', '777001', false );
t( 'kèm nút bỏ ẩn', false !== strpos( $h_a1, 'hiện lại' ), $h_a1 );

/* 🔴 BỎ ẨN LÀ HIỆN LẠI ĐỦ SỐ. */
VHCC_An::dat( $U_CHT_BT, 'AEON_BT', '0000000777', false );
$h_a2 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_an );
preg_match( '/<table class="cc">.*?<\/table>/us', $h_a2, $m_a2 );
t( '🔴 bỏ ẩn thì hàng hiện lại',
	isset( $m_a2[0] ) && false !== strpos( $m_a2[0], '0000000777' ), 'không hiện lại' );

/* ---- 🔴 GỬI THẬT MỘT LƯỢT POST: nút vẽ đúng mà bộ điều phối không nhận thì bấm xong không
        có gì xảy ra, và mọi phép soi HTML ở trên vẫn xanh. ---- */
$tok_an = VHCC_Auth::phat_token( 'Trưởng BL', 'Cửa hàng trưởng', 'AEON_BT', 'CHT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_an );
$_GET  = $g_an;
$_POST = array( 'viec' => 'an_ma', 'ky' => VHCC_Web::chu_ky( $tok_an ),
	'ccs' => 'AEON_BT', 'an_ma' => '0000000777', 'an_bat' => '1' );
ob_start(); VHCC_Web::phuc_vu(); $h_an_post = ob_get_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
t( '🔴 bấm nút Ẩn trên màn thì vào sổ thật', VHCC_An::la_an( 'AEON_BT', '0000000777' ),
	substr( $h_an_post, 0, 400 ) );
t( 'và KHÔNG bị chối bằng câu về màn Hồ sơ',
	false === strpos( $h_an_post, 'thuộc màn Hồ sơ' ), substr( $h_an_post, 0, 400 ) );

/* 🔴 KHÔNG ĐƯỢC ẨN SANG CƠ SỞ NGƯỜI KHÁC. Ẩn là giấu một hàng khỏi bảng của người khác quản. */
$r_lam = VHCC_An::dat( $U_CHT_BT, 'LOTTE_GV', 'BT_MAN', true );
t( '🔴 cửa hàng trưởng KHÔNG ẩn được ở cơ sở ngoài phạm vi', empty( $r_lam['ok'] ), $r_lam );
t( 'và sổ của cơ sở ấy không suy suyển', ! VHCC_An::la_an( 'LOTTE_GV', 'BT_MAN' ), 'ẩn lọt' );

/* 🔴 NHÂN VIÊN BẬC 1 KHÔNG ẨN ĐƯỢC. */
$r_nv_an = VHCC_An::dat( array( 'name' => 'NV', 'role' => 'Nhân viên', 'coso' => 'AEON_BT' ),
	'AEON_BT', 'BT_MAN', true );
t( '🔴 nhân viên bậc 1 bị chối', empty( $r_nv_an['ok'] ), $r_nv_an );

VHCC_An::dat( $U_CHT_BT, 'AEON_BT', '0000000777', false );

/* 🔴 CHƯA ĐỦ GIÁ THÌ HÀNG TỔNG KHÔNG IN SỐ — kể cả khi nó ra số ÂM.
   Anh Thắng gửi ảnh hàng tổng ghi -240.000đ: chưa khai đơn giá nào nên lương chính là 0, còn
   mấy khoản TRỪ đã gõ — cộng lại ra số âm. Phép tính không sai, nhưng nó KHÔNG PHẢI tổng lương
   của ai cả, mà lại nằm đúng ô người ta liếc vào để biết tháng này trả bao nhiêu. */
VHCC_GiaGio::dat_chung( $U_KT, array() );
VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array() );
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0, '' );
VHCC_ChotLuong::dat_tien( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array(), array( 'phat' => 240000 ) );
$h_am = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
preg_match( '/<tr class="tong">.*?<\/tr>/us', $h_am, $m_am );
$hang_tong = isset( $m_am[0] ) ? $m_am[0] : '';
t( 'cắt được hàng tổng ra để soi', '' !== $hang_tong, substr( $h_am, 0, 200 ) );
t( '🔴 hàng tổng KHÔNG in số âm', false === strpos( $hang_tong, '-240.000đ' ), $hang_tong );
t( 'mà nói thẳng là chưa đủ giá', false !== strpos( $hang_tong, 'chưa đủ giá' ), $hang_tong );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 15. KHỐI BẤM-TÊN CHO GÕ LUÔN, KHÔNG CHỈ ĐỌC
 *
 * Anh Thắng 16/09/2026, trước khối chỉ kể giờ theo ca: *"Chưa cho sửa giờ theo công việc làm
 * trong tháng"*. Ca là KHUNG GIỜ của cửa hàng, việc là thứ người ta LÀM — một người chạy Ca 2
 * có thể vừa Lái Tàu vừa MC. Đọc được mà không gõ được thì xem xong vẫn phải đi tìm chỗ khác.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000, 'MC' => 30000 ) );
$h_gt = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT', $g_xn + array( 'xng' => 'BT_MAN' ) );
preg_match( '/<tr class="hang-sua">.*?<\/tr>/us', $h_gt, $m_gt );
$khoi_gt = isset( $m_gt[0] ) ? $m_gt[0] : '';
t( 'cắt được khối bấm-tên ra để soi', '' !== $khoi_gt, substr( $h_gt, 0, 200 ) );
t( '🔴 khối có ô chọn VIỆC CHÍNH', false !== strpos( $khoi_gt, 'name="cl_chinh"' ), $khoi_gt );
t( '🔴 và ô gõ giờ ăn đơn giá khác', false !== strpos( $khoi_gt, 'name="cl_viec[0]"' ), $khoi_gt );
t( 'và mấy ô khoản cộng/trừ', false !== strpos( $khoi_gt, 'name="cl_tru[phat]"' ), $khoi_gt );
t( 'vẫn giữ phần giờ THEO CA ở trên', false !== strpos( $khoi_gt, 'Ca 1' ), $khoi_gt );

/* 🔴 LƯU XONG QUAY VỀ ĐÚNG KHỐI VỪA GÕ, không nhảy sang khối dưới bảng lương. */
t( '🔴 biểu mẫu quay về đúng khối bấm-tên (xng), không phải khối bảng lương (clm)',
	1 === preg_match( '/<form method="post" action="[^"]*xng=BT_MAN[^"]*#xn/u', $khoi_gt ), $khoi_gt );

/* ---- 🔴 GỬI THẬT: gõ từ khối bấm-tên phải vào sổ ---- */
$tok_gt = VHCC_Auth::phat_token( 'Trưởng BL', 'Cửa hàng trưởng', 'AEON_BT', 'CHT_BL' );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_gt );
$_GET  = $g_xn + array( 'xng' => 'BT_MAN' );
$_POST = array( 'viec' => 'chot_luong', 'ky' => VHCC_Web::chu_ky( $tok_gt ),
	'ccs' => 'AEON_BT', 'cth' => '2026-08', 'cl_ma' => 'BT_MAN',
	'cl_chinh' => 'Lái Tàu',
	'cl_viec' => array( 0 => 'MC' ), 'cl_gio' => array( 0 => '4' ) );
ob_start(); VHCC_Web::phuc_vu(); $h_gt_post = ob_get_clean();
$_POST = array(); $_GET = array(); $_COOKIE = array();
teq( '🔴 gõ từ khối bấm-tên: việc chính vào sổ', 'Lái Tàu',
	VHCC_ChotLuong::viec_chinh( 'AEON_BT', '2026-08', 'BT_MAN' ) );
teq( 'và giờ khác cũng vậy', 4.0, VHCC_ChotLuong::tong_cua( 'AEON_BT', '2026-08', 'BT_MAN' ) );
t( 'không bị chối bằng câu về màn Hồ sơ',
	false === strpos( $h_gt_post, 'thuộc màn Hồ sơ' ), substr( $h_gt_post, 0, 400 ) );

/* 🔴 MỘT BIỂU MẪU, HAI CHỖ GỌI — không phải hai bản chép.
   Khối dưới bảng lương phải có y hệt mấy ô ấy; lệch nhau là có ngày sửa một bên quên bên kia. */
$h_gt2 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08', 'clm' => 'BT_MAN' ) );
foreach ( array( 'name="cl_chinh"', 'name="cl_viec[0]"', 'name="cl_tru[phat]"' ) as $o_kh ) {
	t( 'khối dưới bảng lương cũng có ' . $o_kh, false !== strpos( $h_gt2, $o_kh ), $o_kh );
}
t( 'và nó quay về đúng khối CỦA NÓ (clm), không phải xng',
	1 === preg_match( '/<form method="post" action="[^"]*clm=BT_MAN[^"]*#cl/u', $h_gt2 ), $h_gt2 );

/* 🔴 NHÂN VIÊN BẬC 1 xem được giờ nhưng KHÔNG gõ được. */
$h_gt_nv = vhcc_man( 'NV_BL', 'Nhân viên', 'AEON_BT', $g_xn + array( 'xng' => 'BT_MAN' ) );
t( '🔴 bậc 1 KHÔNG thấy ô nhập trong khối bấm-tên',
	false === strpos( $h_gt_nv, 'name="cl_chinh"' ), 'lộ ô nhập cho bậc 1' );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 16. RÊ CHUỘT VÀO Ô TỔNG → GIỜ CHIA CHO TỪNG CÔNG VIỆC
 *
 * Anh Thắng 16/09/2026: *"Rê chuột vào tổng giờ, sẽ ra được từng tổng giờ theo công việc"*.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

VHCC_GiaGio::dat_coso( $U_KT, 'AEON_BT', array( 'Lái Tàu' => 23000, 'MC' => 30000 ) );
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN',
	array( array( 'viec' => 'MC', 'gio' => 6 ) ), 126.0, 'Lái Tàu' );

$h_rc = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );

/** Bóc (title, chữ trong ô) của ô TỔNG mang đúng chú giải cần soi. */
function vhcc_o_tong( $h, $chua ) {
	preg_match_all( '/<td class="tong" title="([^"]*)"><b>([^<]*)<\/b>/u', $h, $m, PREG_SET_ORDER );
	foreach ( $m as $x ) {
		if ( false !== strpos( $x[1], $chua ) ) {
			return array( html_entity_decode( $x[1], ENT_QUOTES ), html_entity_decode( $x[2], ENT_QUOTES ) );
		}
	}
	return array( '', '' );
}
list( $tt_rc, $so_rc ) = vhcc_o_tong( $h_rc, 'Lái Tàu' );
t( '🔴 ô TỔNG có chú giải rê chuột', '' !== $tt_rc, substr( $h_rc, 0, 200 ) );
t( 'chú giải kể việc chính', false !== strpos( $tt_rc, 'Lái Tàu' ), $tt_rc );
t( '🔴 và kể cả dòng giờ ăn đơn giá khác', false !== strpos( $tt_rc, 'MC' ), $tt_rc );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHÉP QUAN TRỌNG NHẤT: SỐ TRONG CHÚ GIẢI CỘNG LẠI PHẢI BẰNG SỐ IN TRONG Ô.
 * Canh bằng PHÉP CỘNG THẬT, không so chuỗi — một chú giải nói khác con số nó đang giải thích
 * thì tệ hơn không có chú giải nào.
 * ══════════════════════════════════════════════════════════════════════════════════════════*/
/* ⚠️ BẢN 4.7.0 ĐỔI ĐƠN VỊ: chú giải và ô TỔNG giờ in GIỜ THẬP PHÂN (`126,75`), không còn
   `Xh Ym`. Phép canh vẫn y nguyên — cộng lại phải bằng — chỉ bộ bóc số là đổi. */
function vhcc_gio_tu_chu( $chu ) {
	$g = 0.0;
	if ( preg_match_all( '/(\d{1,3}(?:\.\d{3})*),(\d{2})\b/u', $chu, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) { $g += (float) ( str_replace( '.', '', $x[1] ) . '.' . $x[2] ); }
	}
	return round( $g, 2 );
}
$p_chu = vhcc_gio_tu_chu( $tt_rc );
$p_o   = vhcc_gio_tu_chu( $so_rc );
t( 'bóc được số giờ từ cả hai chỗ', $p_chu > 0 && $p_o > 0, "chu=$p_chu o=$p_o" );
teq( '🔴 chú giải cộng lại ĐÚNG BẰNG con số trong ô', $p_o, $p_chu );
t( '🔴 ô TỔNG in THẬP PHÂN, không còn "Xh Ym"', 0 === preg_match( '/\dh( \d+m)?/u', $so_rc ), $so_rc );
t( '🔴 chú giải cũng in THẬP PHÂN', 0 === preg_match( '/\dh( \d+m)?/u', $tt_rc ), $tt_rc );

/* 🔴 CHƯA CHỌN VIỆC CHÍNH THÌ NÓI THẲNG, ĐỪNG BỊA TÊN. */
VHCC_ChotLuong::dat( $U_KT, 'AEON_BT', '2026-08', 'BT_MAN', array(), 126.0, '' );
$h_rc2 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
list( $tt_rc2, ) = vhcc_o_tong( $h_rc2, 'chưa chọn việc chính' );
t( '🔴 chưa chọn việc chính: chú giải nói thẳng, không bịa tên', '' !== $tt_rc2, $h_rc2 );

/* 🔴 NGƯỜI BỊ ẨN KHÔNG CÓ HÀNG, NÊN KHÔNG CÓ CHÚ GIẢI NÀO CỦA HỌ. */
$U_CHT_BT2 = array( 'name' => 'Trưởng BL', 'role' => 'Cửa hàng trưởng', 'coso' => 'AEON_BT' );
VHCC_An::dat( $U_CHT_BT2, 'AEON_BT', 'BT_MAN', true );
$h_rc3 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
preg_match( '/<table class="cc">.*?<\/table>/us', $h_rc3, $m_rc3 );
t( '🔴 người bị ẩn: không còn hàng nào, nên không chú giải nào',
	isset( $m_rc3[0] ) && false === strpos( $m_rc3[0], 'BT_MAN' ), 'còn sót hàng người bị ẩn' );
VHCC_An::dat( $U_CHT_BT2, 'AEON_BT', 'BT_MAN', false );

/* 🔴 GIEO MỘT NGƯỜI KHÔNG CÓ LƯỢT CHẤM NÀO. Lưới vẫn vẽ hàng cho họ (đọc từ hồ sơ), nhưng
   `BangLuong::dung()` KHÔNG có dòng nào — đó chính là cảnh làm `$chu_viec` rỗng. Thiếu người
   này thì nhánh "không gắn title" không bao giờ chạy, và phép dưới xanh mà chẳng canh gì (đã
   thử đột biến gắn title rỗng: nó KHÔNG đỏ). */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BT_KHONGCHAM',
	'ho_ten' => 'Người Chưa Chấm Lượt Nào', 'cua_hang' => 'AEON_BT',
	'chuc_vu' => 'Partime', 'vai_tro' => 'Nhân viên' ) );

/* ⚠️ KHÔNG GẮN `title` RỖNG. Một chú giải rỗng vẫn bật ra một khung trống khi rê chuột —
   người ta tưởng hỏng. Người không có dòng lương thì ô TỔNG phải KHÔNG mang title. */
$h_rc4 = vhcc_man( 'CHT_BL', 'Cửa hàng trưởng', 'AEON_BT',
	array( 'man' => 'cham', 'ccs' => 'AEON_BT', 'cth' => '2026-08' ) );
t( 'gieo: người ấy CÓ hàng trong lưới', false !== strpos( $h_rc4, 'Người Chưa Chấm Lượt Nào' ),
	'không thấy hàng của người chưa chấm' );
teq( '🔴 không ô TỔNG nào mang title rỗng', 0,
	substr_count( $h_rc4, '<td class="tong" title="">' ) );

/* Và màn phải NÓI RA là rê được — chú giải không ai đoán ra là có. */
t( 'màn nói rõ là rê chuột được', false !== strpos( $h_rc4, 'Rê chuột vào ô TỔNG' ), $h_rc4 );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 17. CA VẮT QUA NỬA ĐÊM: `ra < vao` LÀ 4 TIẾNG, KHÔNG PHẢI HÀNG HỎNG
 *
 * Anh Thắng 16/09/2026, nói thẳng luật: *"qua đêm hệ thống sẽ hiểu: 22h00 - 2h00 là 4 tiếng,
 * vì 22h ngày 16 và 2h ngày 17, thì coi như nó sẽ tính 22h00-24h00, sau đó quay lại
 * 00h00 - 2h00"*.
 *
 * 🔴 MỤC NÀY TỪNG KHẲNG ĐỊNH NGƯỢC LẠI (bản 4.6.0) — và nó SAI.
 *    Em tin rằng ca đêm luôn được trải phẳng lúc ghi nên `ra < vao` chỉ còn là rác, rồi hỏi
 *    anh Thắng *"hàng ra < vào mà KHÔNG phải ca đêm thì làm gì"*, nhận về *"tính 0 giờ và kêu
 *    lên"*, và viết hẳn một mục thử khoá cái sai ấy lại. Anh trả lời đúng câu được hỏi; TIỀN
 *    ĐỀ của câu hỏi mới là thứ hỏng — chỉ văn phòng chấm qua cổng online mới trải phẳng.
 *    Một mục thử phát biểu SAI luật là thứ khiến bản sau không ai dám sửa lại cho đúng.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

$cs_h  = 'KHO_LA';
$th_h  = '2026-08';
$ma_h  = 'HONG1';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma_h, 'ho_ten' => 'Người Ca Đêm Thô',
	'cua_hang' => $cs_h, 'chuc_vu' => 'Partime', 'vai_tro' => 'Nhân viên' ) );
/* Một ngày LÀNH làm mốc — 08:00 → 17:00 = 9 giờ. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_h, 'ho_ten' => '', 'coso' => $cs_h, 'ngay' => '2026-08-05',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );

$b_h0 = VHCC_BangLuong::dung( $cs_h, $th_h );
$g_h0 = null;
foreach ( $b_h0['dong'] as $x ) { if ( $x['ma'] === $ma_h && $x['laChinh'] ) { $g_h0 = $x; } }
t( 'gieo: tìm được dòng của người ấy', null !== $g_h0, 'không thấy dòng' );
teq( 'gieo: ngày lành cho đúng 9 giờ', 9.0, (float) $g_h0['gioTong'] );

/* 🔴 PHÉP CHÍNH: 22:00 → 02:00 trên MỘT hàng, chưa trải phẳng, phải ra ĐÚNG 4 GIỜ. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_h, 'ho_ten' => '', 'coso' => $cs_h, 'ngay' => '2026-08-06',
	'gio_vao_giay' => 22 * 3600, 'gio_ra_giay' => 2 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );

$b_h1 = VHCC_BangLuong::dung( $cs_h, $th_h );
$g_h1 = null;
foreach ( $b_h1['dong'] as $x ) { if ( $x['ma'] === $ma_h && $x['laChinh'] ) { $g_h1 = $x; } }
teq( '🔴 22:00 → 02:00 cho đúng 4 giờ (9 + 4 = 13)', 13.0, (float) $g_h1['gioTong'] );
/* ⚠️ Và KHÔNG phải 0: nói thẳng cái sai của bản 4.6.0 ra để không ai lặng lẽ trả nó lại. */
t( '⚠️ KHÔNG bị chặn thành 0 giờ (lỗi của bản 4.6.0)',
	(float) $g_h1['gioTong'] > 9.0, $g_h1['gioTong'] );
/* ⚠️ Cũng KHÔNG phải 20 giờ — cộng bù đúng một vòng, không phải lấy hiệu thô. */
t( '⚠️ và KHÔNG thành ~20 giờ', (float) $g_h1['gioTong'] < 16.0, $g_h1['gioTong'] );

/* 🔴 HAI LỐI GHI, CÙNG MỘT KẾT QUẢ ĐÚNG.
   Ca đêm ghi qua cổng online được TRẢI PHẲNG (05:30 hôm sau = 21600 + 86400). Lối ấy phải ra
   đúng số giờ y như lối ghi thô ở trên — nếu không thì cùng một ca, chấm bằng hai đường, ra
   hai số tiền. 22:00 → 06:00 = 8 giờ. */
$ma_d = 'DEM1';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma_d, 'ho_ten' => 'Người Ca Đêm Phẳng',
	'cua_hang' => $cs_h, 'chuc_vu' => 'Partime', 'vai_tro' => 'Nhân viên' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_d, 'ho_ten' => '', 'coso' => $cs_h, 'ngay' => '2026-08-07',
	'gio_vao_giay' => 22 * 3600, 'gio_ra_giay' => 6 * 3600 + VHCC_DB::NGAY_GIAY,
	'hau_to' => 'CD', 'nguon' => 'may' ) );
$b_h2 = VHCC_BangLuong::dung( $cs_h, $th_h );
$g_h2 = null;
foreach ( $b_h2['dong'] as $x ) { if ( $x['ma'] === $ma_d && $x['laChinh'] ) { $g_h2 = $x; } }
t( 'gieo: tìm được dòng người ca đêm trải phẳng', null !== $g_h2, 'không thấy dòng' );
teq( '🔴 ca đêm TRẢI PHẲNG cũng ra đủ 8 giờ', 8.0, (float) $g_h2['gioTong'] );

/* 🔴 VÀ MÀN KHÔNG ĐƯỢC KÊU OAN. Dải "N hàng ghi sai" của bản 4.6.0 đã gỡ — nó bôi đỏ đúng
   những ca đêm lành lặn, và người ta sẽ đi "sửa" chúng. */
$h_h = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'man' => 'cham', 'ccs' => $cs_h, 'cth' => $th_h ) );
t( '🔴 màn KHÔNG còn bôi đỏ ca đêm là "hàng ghi sai"',
	false === strpos( $h_h, 'giờ ra SỚM HƠN giờ vào' ), 'dải cảnh báo sai còn sót' );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 18. MỘT LỐI IN GIỜ DUY NHẤT — LƯỚI VÀ BẢNG LƯƠNG PHẢI RA ĐÚNG CÙNG MỘT CHUỖI
 *
 * Anh Thắng 16/09/2026 gửi ảnh `211,50` nằm cạnh `211h 30m` trên cùng một màn: *"sửa lại đúng
 * số giờ đồng nhất cho đối chiếu chứ"*. Cùng một đại lượng, hai lối viết, nên mắt không đối
 * chiếu được — và `211,50` còn bị đọc nhầm thành "211 giờ 50 phút".
 *
 * ⚠️ SO CHUỖI BÓC ĐƯỢC VỚI CHUỖI BÓC ĐƯỢC, KHÔNG VIẾT CỨNG MỘT CON SỐ. Viết cứng `17,25` thì
 *    phép này vẫn xanh cả khi hai màn cùng sai như nhau — mà thứ đang canh chính là "hai màn
 *    có nói cùng một câu không".
 *
 * ⚠️ SỐ PHẢI LẺ. Chọn 17,25 giờ chứ không phải 17 giờ chẵn: `17h` và `17,00` chỉ lệch nhau ở
 *    phần thập phân, nên một giờ chẵn sẽ KHÔNG bắt được lỗi quên đổi đơn vị.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

$cs_d = 'KHO_DC';
$th_d = '2026-08';
$ma_dc = 'DC1';
$ten_dc = 'Người Đối Chiếu';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma_dc, 'ho_ten' => $ten_dc,
	'cua_hang' => $cs_d, 'chuc_vu' => 'Partime', 'vai_tro' => 'Nhân viên' ) );
/* 09:30 (9,5 giờ) + 07:45 (7,75 giờ) = 17,25 giờ. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_dc, 'ho_ten' => '', 'coso' => $cs_d, 'ngay' => '2026-08-03',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600 + 30 * 60, 'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_dc, 'ho_ten' => '', 'coso' => $cs_d, 'ngay' => '2026-08-04',
	'gio_vao_giay' => 9 * 3600, 'gio_ra_giay' => 16 * 3600 + 45 * 60, 'hau_to' => '', 'nguon' => 'may' ) );
VHCC_GiaGio::dat_coso( $U_KT, $cs_d, array( 'Partime' => 25000 ) );

$b_d = VHCC_BangLuong::dung( $cs_d, $th_d );
$g_d = null;
foreach ( $b_d['dong'] as $x ) { if ( $x['ma'] === $ma_dc && $x['laChinh'] ) { $g_d = $x; } }
t( 'gieo: tìm được dòng của người đối chiếu', null !== $g_d, 'không thấy dòng' );
teq( 'gieo: engine cho đúng 17,25 giờ', 17.25, (float) $g_d['gioTong'] );

$h_d = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'man' => 'cham', 'ccs' => $cs_d, 'cth' => $th_d ) );

/** Bóc ô TỔNG của lưới (`table.cc`) ở hàng mang tên người ấy. */
function vhcc_tong_luoi( $h, $ten ) {
	if ( ! preg_match( '/<table class="cc">.*?<\/table>/us', $h, $mt ) ) { return null; }
	foreach ( explode( '<tr', $mt[0] ) as $hang ) {
		if ( false === strpos( $hang, $ten ) ) { continue; }
		if ( preg_match( '/<td class="tong"[^>]*><b>([^<]*)<\/b>/u', $hang, $m ) ) {
			return html_entity_decode( trim( $m[1] ), ENT_QUOTES );
		}
	}
	return null;
}
/** Bóc cột "Số giờ" (ô thứ 4) của bảng lương (`table.b`) ở hàng mang tên người ấy. */
function vhcc_so_gio_bang( $h, $ten ) {
	if ( ! preg_match( '/<table class="b">.*?<\/table>/us', $h, $mt ) ) { return null; }
	foreach ( explode( '<tr', $mt[0] ) as $hang ) {
		if ( false === strpos( $hang, $ten ) ) { continue; }
		preg_match_all( '/<td[^>]*>(.*?)<\/td>/us', $hang, $m );
		if ( isset( $m[1][3] ) ) {
			return html_entity_decode( trim( wp_strip_all_tags( $m[1][3] ) ), ENT_QUOTES );
		}
	}
	return null;
}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 HÀNG LƯỚI PHẢI CÓ TÊN, KỂ CẢ KHI LƯỢT CHẤM KHÔNG MANG TÊN.
 * Mấy lượt gieo ở trên cố ý để `ho_ten => ''` — đúng như máy chấm công / nạp .csv vẫn ghi.
 * Trước bản 4.7.0 lưới để trống ô tên (chỉ còn mấy cái nút) trong khi BẢNG LƯƠNG ngay dưới
 * lại in đủ tên: cùng một người, hai bảng trên cùng một màn, không đối chiếu nổi.
 * ══════════════════════════════════════════════════════════════════════════════════════════*/
preg_match( '/<table class="cc">.*?<\/table>/us', $h_d, $m_lt );
$luoi_d = isset( $m_lt[0] ) ? $m_lt[0] : '';
t( '🔴 lưới in TÊN của người dù lượt chấm để trống ho_ten',
	false !== strpos( $luoi_d, $ten_dc ), substr( $luoi_d, 0, 400 ) );
t( 'gieo: bảng lương thì vẫn luôn có tên', false !== strpos( $h_d, '<td>' . $ten_dc . '</td>' ),
	'bảng lương không in tên — fixture sai' );

$o_luoi  = vhcc_tong_luoi( $h_d, $ten_dc );
$o_bang  = vhcc_so_gio_bang( $h_d, $ten_dc );
t( 'bóc được ô TỔNG của lưới', null !== $o_luoi && '' !== $o_luoi, var_export( $o_luoi, true ) );
t( 'bóc được cột Số giờ của bảng lương', null !== $o_bang && '' !== $o_bang, var_export( $o_bang, true ) );
teq( '🔴 ô TỔNG của lưới và cột Số giờ của bảng lương ra ĐÚNG CÙNG MỘT CHUỖI', $o_bang, $o_luoi );
t( '🔴 và đó là lối thập phân, không phải "Xh Ym"',
	0 === preg_match( '/\dh( \d+m)?/u', (string) $o_luoi ), $o_luoi );

/* 🔴 HÀNG TỔNG CUỐI LƯỚI CŨNG PHẢI THEO LUẬT ẤY. Cơ sở này chỉ có một người, nên tổng cả cơ
   sở phải trùng đúng ô TỔNG của người ấy — bắt được cả lỗi quên đổi riêng hàng cuối. */
if ( preg_match( '/<tr class="tong"><td>1 người<\/td>.*?<td><b>([^<]*)<\/b><\/td>/us', $h_d, $m_hc ) ) {
	$o_cuoi = html_entity_decode( trim( $m_hc[1] ), ENT_QUOTES );
} else {
	$o_cuoi = null;
}
t( 'bóc được hàng tổng cuối lưới', null !== $o_cuoi, 'không thấy hàng tổng' );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 `the_tong_cham()` — BẢNG "TỔNG GIỜ LÀM THEO NHÂN VIÊN" — CŨNG PHẢI THEO LUẬT NÀY.
 *
 * ⚠️ LỜI GỌI NÓ ĐÃ BỊ BỎ KHỎI MÀN, hàm thì còn. Chú thích ở `class-vhcc-web.php` bảo "vẫn có
 *    bài kiểm" — nhưng không có: `grep the_tong_cham tools/test/` chỉ ra đúng một dòng CHÚ
 *    THÍCH. Nên phải GỌI THẲNG, không dò trên trang: dò trên trang thì bảng không có ở đó,
 *    phép thử đỏ vì một lý do sai, mà bỏ hẳn thì lối in trong hàm ấy không ai canh — và ngày
 *    nó được bật lại, nó sẽ là bảng DUY NHẤT trên màn còn ghi `17h 15m`.
 * ══════════════════════════════════════════════════════════════════════════════════════════*/
$rm_tgl = new ReflectionMethod( 'VHCC_Web', 'the_tong_cham' );
$rm_tgl->setAccessible( true );
$b_tgl   = VHCC_Cham::bang_cham_cong( $U_KT, $cs_d, $th_d );
$loc_tgl = (array) $b_tgl['hang'];
ob_start();
$rm_tgl->invoke( null, $loc_tgl, $th_d, $cs_d, $th_d );
$h_tgl = ob_get_clean();
preg_match_all( '/<td><b>([^<]*)<\/b><\/td>/u', $h_tgl, $m_tgo );
$o_tgl = isset( $m_tgo[1][0] ) ? html_entity_decode( trim( $m_tgo[1][0] ), ENT_QUOTES ) : null;
t( 'bóc được ô của bảng Tổng giờ làm', null !== $o_tgl && '' !== $o_tgl,
	var_export( $o_tgl, true ) . ' | ' . substr( $h_tgl, 0, 400 ) );
teq( '🔴 bảng Tổng giờ làm in CÙNG MỘT CHUỖI với ô TỔNG của lưới', $o_luoi, $o_tgl );
t( '🔴 và đầu cột ấy cũng nói rõ đơn vị',
	false !== strpos( $h_tgl, '<th>Tổng giờ làm<div style="font-weight:400;opacity:.7">giờ</div></th>' ),
	'đầu cột Tổng giờ làm không ghi đơn vị' );
teq( '🔴 hàng tổng cuối lưới cũng in thập phân, trùng ô TỔNG của người duy nhất', $o_luoi, $o_cuoi );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHÚ THÍCH RÊ CHUỘT CỦA TỪNG Ô NGÀY VẪN PHẢI LÀ `Xh Ym` — ĐỪNG ĐỔI NHẦM CHỖ.
 * Ô ngày nói về MỘT ca cụ thể: `9h 30m` đọc tự nhiên hơn `9,50`, và nó không phải con số đem
 * đi đối chiếu với bảng lương. Chỉ mấy chỗ TỔNG mới phải đổi.
 * ══════════════════════════════════════════════════════════════════════════════════════════*/
/* ⚠️ PHẢI CANH ĐÚNG DÒNG GIỜ CỦA LƯỢT CHẤM, không phải "có chữ `h` ở đâu đó".
   Chú thích ô ngày còn mấy dòng `Ca 1 6h (06:00–14:00)` do `VHCC_Ca::chu()` in ra — mục này
   không đụng tới chúng. Bắt hớ vào chúng thì phép thử xanh cả khi dòng giờ của lượt chấm đã
   bị đổi nhầm sang thập phân (đã thử đột biến: nó KHÔNG đỏ). Nên: bắt đúng DÒNG ĐỨNG RIÊNG
   ngay dưới dòng `vào → ra`. */
$dong_gio_o = null;
if ( preg_match_all( '/<td class="o[^"]*" title="([^"]*)"/u', $h_d, $m_o, PREG_SET_ORDER ) ) {
	foreach ( $m_o as $x ) {
		$dong = explode( "\n", html_entity_decode( $x[1], ENT_QUOTES ) );
		foreach ( $dong as $i => $d ) {
			if ( $i > 0 && preg_match( '/^\d{2}:\d{2}:\d{2} → \d{2}:\d{2}:\d{2}$/u', trim( $d ) )
				&& isset( $dong[ $i + 1 ] ) ) {
				$dong_gio_o = trim( $dong[ $i + 1 ] );
				break 2;
			}
		}
	}
}
t( 'bóc được dòng giờ trong chú thích ô ngày', null !== $dong_gio_o, 'không thấy dòng giờ nào' );
t( '🔴 chú thích rê chuột của Ô NGÀY vẫn giữ lối "Xh Ym"',
	null !== $dong_gio_o && 1 === preg_match( '/^\d+h( \d+m)?$/u', $dong_gio_o ),
	var_export( $dong_gio_o, true ) );


/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 19. ẨN THÌ ẨN Ở MỌI NƠI, VÀ LUÔN CÒN ĐƯỜNG BỎ ẨN
 *
 * Anh Thắng 16/09/2026: *"nếu ẩn thì ẩn luôn, không hiện tất cả các tháng"*.
 *
 * Sổ ẩn vốn ĐÃ toàn cục theo cơ sở (không có tham số tháng nào). Chỗ hỏng là chỉ HAI trong
 * khoảng TÁM nơi vẽ ra màn có hỏi nó — và một trong sáu chỗ quên nằm NGAY DƯỚI cái lưới vừa
 * ẩn. Mục 14 ở trên đã canh phần "ẩn khỏi lưới và bảng lương"; mục này canh phần còn lại.
 * ═════════════════════════════════════════════════════════════════════════════════════════════*/

$cs_e  = 'KHO_AN';
$ma_e  = '0000000888';                 /* mã bịa, KHÔNG có hồ sơ — kho này công khai */
$U_QL_E = array( 'name' => 'Anh Quản Lý', 'role' => 'Quản lý', 'coso' => '' );

/* Lượt chấm CHỈ ở tháng 8. Sang tháng 9 người này không có lượt nào — đó chính là cảnh làm
   dòng "đang ẩn" biến mất ở bản trước. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_e, 'ho_ten' => 'Rác Thử Máy', 'coso' => $cs_e, 'ngay' => '2026-08-04',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
t( 'gieo: mã ẩn KHÔNG có hồ sơ', ! VHCC_NhanSu::ho_so( $ma_e ), 'mã rác lại có hồ sơ' );

/* ⚠️ GIEO THÊM MỘT NGƯỜI KHÔNG BỊ ẨN. Cơ sở chỉ có đúng một người mà người ấy bị ẩn thì lưới
   lẫn bảng "Tổng giờ theo ca" đều in "Chưa có dữ liệu" và KHÔNG vẽ bảng nào — lúc đó phép thử
   xanh vì chẳng có gì để soi, chứ không phải vì phép ẩn chạy đúng. Cảnh thật cũng vậy: người
   ta ẩn một mã rác giữa một cơ sở đang có người đi làm. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'AN_BINHTHUONG', 'ho_ten' => 'Người Vẫn Hiện', 'coso' => $cs_e, 'ngay' => '2026-08-04',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'AN_BINHTHUONG', 'ho_ten' => 'Người Vẫn Hiện', 'coso' => $cs_e, 'ngay' => '2026-09-04',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );

$g_e8 = array( 'man' => 'cham', 'ccs' => $cs_e, 'cth' => '2026-08' );
$g_e9 = array( 'man' => 'cham', 'ccs' => $cs_e, 'cth' => '2026-09' );

$h_e0 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_e8 );
t( 'gieo: chưa ẩn thì mã CÓ trong lưới tháng 8', false !== strpos( $h_e0, $ma_e ), 'không thấy mã' );

$r_e = VHCC_An::dat( $U_QL_E, $cs_e, $ma_e, true );
t( 'ẩn được', ! empty( $r_e['ok'] ), $r_e );

/* ───── 1. 🔴 PHÉP CHÍNH: SANG THÁNG KHÁC VẪN ẨN, VÀ VẪN CÒN NÚT BỎ ẨN ───── */
$h_e9 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_e9 );
preg_match( '/<table class="cc">.*?<\/table>/us', $h_e9, $m_e9 );
t( '🔴 tháng 9: mã ẩn không có hàng nào trong lưới',
	isset( $m_e9[0] ) && false === strpos( $m_e9[0], $ma_e ), 'còn sót hàng của mã ẩn' );
/* 🔴 Đây là nửa thứ hai của câu anh Thắng, và là nửa dễ quên: ẩn mà không còn chỗ nào nhìn
   thấy thì đúng bằng xoá. Bản trước dựng danh sách này TỪ bản đồ tên của tháng đang xem, nên
   ở tháng 9 (mã không có lượt chấm nào) nó rỗng và cả dòng biến mất. */
t( '🔴 tháng 9 VẪN có dòng "mã đang ẩn"', false !== strpos( $h_e9, 'mã đang ẩn' ), $h_e9 );
t( '🔴 và dòng ấy kể đích danh mã', false !== strpos( $h_e9, '<code>' . $ma_e . '</code>' ), $h_e9 );
t( '🔴 và có nút bỏ ẩn để bấm', false !== strpos( $h_e9, 'hiện lại' ), $h_e9 );

/* ───── 2. 🔴 BẢNG "TỔNG GIỜ THEO CA" NGAY DƯỚI LƯỚI ───── */
/* ⚠️ BÓC RIÊNG KHỐI ẤY RỒI MỚI SOI. Soi cả trang thì bắt trúng chính dòng "mã đang ẩn" vừa
   thêm ở trên — và phép thử sẽ đỏ vì một chỗ hoàn toàn đúng. */
$h_e8 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_e8 );
function vhcc_khoi_tong_ca( $h ) {
	$i = strpos( $h, 'Tổng giờ theo ca' );
	if ( false === $i ) { return null; }
	if ( ! preg_match( '/<table class="cc">.*?<\/table>/us', substr( $h, $i ), $m ) ) { return null; }
	return $m[0];
}
$tc_e = vhcc_khoi_tong_ca( $h_e8 );
t( 'bóc được bảng "Tổng giờ theo ca"', null !== $tc_e, 'không thấy khối ấy trên màn' );
t( '🔴 mã ẩn KHÔNG còn trong bảng "Tổng giờ theo ca"',
	null !== $tc_e && false === strpos( $tc_e, 'Rác Thử Máy' ), $tc_e );

/* ───── 3. 🔴 CƠ SỞ TÍNH THEO CÔNG (lưới khác hẳn, trước nay ẩn KHÔNG có tác dụng gì) ───── */
$cs_ec = 'VP_AN';
$ma_ec = '0000000889';
/* ⚠️ `'cong'` MỚI LÀ LƯỚI VĂN PHÒNG (`ve_luoi_vp`). `'ngay'` là "có đi là được" và vẫn do
   `ve_luoi_gio` vẽ — gieo nhầm `'ngay'` thì mục này canh lại đúng cái lưới mục trên đã canh,
   còn `ve_luoi_vp` thì không ai đụng tới. Đã thử đột biến: gỡ phép lọc của `ve_luoi_vp` ra mà
   bài kiểm vẫn xanh, và đó là cách phát hiện ra chỗ gieo sai này. */
VHCC_Luong::dat_cach_tinh( $U_QL_E, array( $cs_ec => 'cong' ) );
teq( 'gieo: cơ sở ấy dùng LƯỚI VĂN PHÒNG', 'cong', VHCC_Luong::cach_tinh( $cs_ec ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_ec, 'ho_ten' => 'Rác Văn Phòng', 'coso' => $cs_ec, 'ngay' => '2026-08-05',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'VPAN_BT', 'ho_ten' => 'Văn Phòng Vẫn Hiện', 'coso' => $cs_ec, 'ngay' => '2026-08-05',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$g_ec = array( 'man' => 'cham', 'ccs' => $cs_ec, 'cth' => '2026-08' );
$h_ec0 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_ec );
t( 'gieo: lưới theo công CÓ hàng ấy khi chưa ẩn',
	false !== strpos( $h_ec0, 'Rác Văn Phòng' ), 'lưới vp không có hàng — fixture sai' );
VHCC_An::dat( $U_QL_E, $cs_ec, $ma_ec, true );
$h_ec1 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_ec );
t( '🔴 lưới THEO CÔNG cũng ẩn được', false === strpos( $h_ec1, 'Rác Văn Phòng' ), $h_ec1 );

/* 🔴 VÀ VÒNG DỰNG HÀNG TRỐNG TỪ HỒ SƠ CỦA LƯỚI THEO CÔNG cũng phải lọc — nó là một khối mã
   RIÊNG, không dùng chung với lưới theo giờ. Thiếu phép thử này thì gỡ phép lọc bên ấy ra bài
   kiểm vẫn xanh (đã thử đột biến, và nó KHÔNG đỏ cho tới khi có mấy dòng dưới đây). */
$ma_ecs = 'VPAN_COHS';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma_ecs,
	'ho_ten' => 'Văn Phòng Ẩn Có Hồ Sơ', 'cua_hang' => $cs_ec, 'chuc_vu' => 'Partime',
	'vai_tro' => 'Nhân viên' ) );
$h_ecs0 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_ec );
/* ⚠️ SOI TRONG LƯỚI, không soi cả trang: tên người ấy còn hiện ở khối "thiếu ảnh thẻ" và mấy
   khối khác, nên phép gieo soi cả trang sẽ xanh CẢ KHI lưới chẳng dựng hàng nào — và lúc đó
   phép thử chính bên dưới canh một cảnh không tồn tại. */
preg_match( '/<table class="cc">.*?<\/table>/us', $h_ecs0, $m_ecs0 );
t( 'gieo: lưới theo công CÓ hàng trống của người có hồ sơ',
	isset( $m_ecs0[0] ) && false !== strpos( $m_ecs0[0], 'Văn Phòng Ẩn Có Hồ Sơ' ),
	isset( $m_ecs0[0] ) ? substr( $m_ecs0[0], 0, 400 ) : '(không có lưới)' );
VHCC_An::dat( $U_QL_E, $cs_ec, $ma_ecs, true );
$h_ecs1 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_ec );
/* ⚠️ BÓC LƯỚI RA RỒI MỚI SOI. Soi cả trang thì bắt trúng chính dòng "🚫 N mã đang ẩn" ở cuối
   lưới — dòng ấy CÓ NHIỆM VỤ kể tên người bị ẩn, nên phép thử đỏ về một chỗ hoàn toàn đúng.
   Đã vấp đúng thế một lần ở mục này. */
preg_match( '/<table class="cc">.*?<\/table>/us', $h_ecs1, $m_ecs );
t( 'bóc được lưới theo công sau khi ẩn', isset( $m_ecs[0] ), 'lưới biến mất' );
t( '🔴 lưới THEO CÔNG: hàng trống dựng từ HỒ SƠ cũng ẩn được',
	isset( $m_ecs[0] ) && false === strpos( $m_ecs[0], 'Văn Phòng Ẩn Có Hồ Sơ' ),
	isset( $m_ecs[0] ) ? substr( $m_ecs[0], 0, 300 ) : '(không có lưới)' );

/* ───── 4. 🔴 MÃ ẨN MÀ **CÓ HỒ SƠ**: vòng dựng hàng trống không đi qua cửa lọc ───── */
/* Đây là chỗ dễ sót nhất: `ds_nhan_vien()` đọc thẳng sổ nhân sự, không qua `doc_thang()`. */
$ma_eh = 'AN_COHS';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $ma_eh,
	'ho_ten' => 'Người Ẩn Có Hồ Sơ', 'cua_hang' => $cs_e, 'chuc_vu' => 'Partime',
	'vai_tro' => 'Nhân viên' ) );
$h_eh0 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_e8 );
t( 'gieo: người có hồ sơ mà chưa chấm VẪN có hàng trống',
	false !== strpos( $h_eh0, 'Người Ẩn Có Hồ Sơ' ), 'không có hàng trống — fixture sai' );
VHCC_An::dat( $U_QL_E, $cs_e, $ma_eh, true );
$h_eh1 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_e8 );
preg_match( '/<table class="cc">.*?<\/table>/us', $h_eh1, $m_eh );
t( 'bóc được lưới sau khi ẩn', isset( $m_eh[0] ), 'lưới biến mất — cơ sở không còn ai không ẩn?' );
t( '🔴 ẩn xong thì hàng trống dựng từ HỒ SƠ cũng mất',
	isset( $m_eh[0] ) && false === strpos( $m_eh[0], 'Người Ẩn Có Hồ Sơ' ),
	isset( $m_eh[0] ) ? $m_eh[0] : '(không có lưới)' );

/* ───── 5. 🔴 ẨN Ở CƠ SỞ CHA CỦA MỘT CHÙM THÌ BẢNG CƠ SỞ CON CŨNG ẨN ───── */
$cs_cha = 'CHUM_CHA';
$cs_con = 'CHUM_CON';
$ma_ch  = '0000000890';
VHCC_Luong::dat_ghep( $U_QL_E, array( $cs_con => $cs_cha ) );
t( 'gieo: đã ghép con vào cha',
	in_array( $cs_con, (array) VHCC_Luong::chum_cua( $cs_cha ), true ), VHCC_Luong::chum_cua( $cs_cha ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => $ma_ch, 'ho_ten' => 'Rác Trong Chùm', 'coso' => $cs_con, 'ngay' => '2026-08-06',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CHUM_BT', 'ho_ten' => 'Chùm Vẫn Hiện', 'coso' => $cs_con, 'ngay' => '2026-08-06',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$g_con = array( 'man' => 'cham', 'ccs' => $cs_con, 'cth' => '2026-08' );
$h_con0 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_con );
t( 'gieo: bảng cơ sở CON có hàng ấy', false !== strpos( $h_con0, 'Rác Trong Chùm' ), 'fixture sai' );
/* Ẩn từ bảng CHA — đúng cảnh thật: người ta mở bảng gộp, thấy rác, bấm ẩn ở đó. */
VHCC_An::dat( $U_QL_E, $cs_cha, $ma_ch, true );
$h_con1 = vhcc_man( 'KT_BL', 'Kế toán', '', $g_con );
preg_match( '/<table class="cc">.*?<\/table>/us', $h_con1, $m_con );
t( '🔴 ẩn ở bảng CHA thì bảng CON cũng không còn hàng ấy',
	isset( $m_con[0] ) && false === strpos( $m_con[0], 'Rác Trong Chùm' ),
	isset( $m_con[0] ) ? substr( $m_con[0], 0, 300 ) : '(không có lưới)' );

/* ───── 6. TỜ IN A4 — SQL riêng, không đi qua cửa lọc ───── */
$h_in = vhcc_man( 'KT_BL', 'Kế toán', '', array( 'to_in' => '1', 'ics' => $cs_e,
	'itu' => '2026-08-01', 'iden' => '2026-08-31' ) );
t( 'gieo: tờ in dựng ra được', false !== strpos( $h_in, '2026' ) && strlen( $h_in ) > 500,
	substr( $h_in, 0, 300 ) );
t( '🔴 tờ in A4 cũng không còn mã ẩn', false === strpos( $h_in, 'Rác Thử Máy' ), $h_in );

/* ───── 7. ẨN KHÔNG PHẢI XOÁ — công vẫn còn nguyên trong sổ ───── */
teq( '🔴 lượt chấm của mã ẩn VẪN nằm trong bảng', 1, (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s', $ma_e ) ) );

/* ───── Dọn: bỏ ẩn để mấy mục sau không thừa hưởng trạng thái này ───── */
VHCC_An::dat( $U_QL_E, $cs_e, $ma_eh, false );

ket_luan();
