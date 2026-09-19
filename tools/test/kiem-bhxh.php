<?php
/**
 * SỔ BHXH — ai đóng, mỗi tháng trừ bao nhiêu.
 *
 * =================================================================================================
 * 🔴 BÀI NÀY CANH TIỀN, KHÔNG CANH MÀN
 * =================================================================================================
 * Anh Thắng 19/09/2026, kèm ảnh tờ lương thật: *"Đối với nhân viên cố định sẽ có thêm bảo hiểm xã
 * hội. Bổ sung tab bên Phân Quyền Kế toán để kế toán chốt BHXH bạn nào đóng sẽ được thêm vào
 * bảng"*.
 *
 * Con số trong ảnh: Trần Ngọc Minh Truyền — lương cơ bản 4.000.000, BHXH 596.610, tổng lương
 * **3.403.390**. Bài này gieo đúng cảnh ấy rồi đi hết đường: bảng lương → cột TOTAL SALARY → tờ
 * .xlsx → phiếu lương nhân viên tự xem. Bốn nơi phải ra CÙNG một con số; lệch một nơi là tờ lương
 * hứa một đằng, tài khoản nhận một nẻo.
 *
 * ⚠️ HAI QUYẾT ĐỊNH CỦA ANH THẮNG ĐƯỢC KHOÁ LẠI Ở ĐÂY:
 *    · kế toán GÕ THẲNG số tiền — máy không tự nhân tỉ lệ nào;
 *    · khai một lần, TỰ LẶP hằng tháng — nhưng chỉ từ tháng bắt đầu trở đi.
 *
 * Chạy: php tools/test/kiem-bhxh.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 400 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

global $wpdb;
$KT  = array( 'name' => 'Chị Kế Toán', 'role' => VHCC_Vai::KE_TOAN, 'ma_nv' => 'BHKT' );
$CHT = array( 'name' => 'Anh Trưởng', 'role' => VHCC_Vai::CHT, 'coso' => 'BH_SHOP', 'ma_nv' => 'BHCHT' );
$NV  = array( 'name' => 'Em NV', 'role' => VHCC_Vai::NV, 'ma_nv' => 'BHNV' );

$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'BH_SHOP', 'bo_phan' => 'Khu vui chơi' ) );
VHCC_GiaGio::dat_coso( $KT, 'BH_SHOP', array( 'Nhân Viên' => 22000 ) );

/* Người ăn LƯƠNG THÁNG — đúng cảnh trong ảnh: lương cb 4.000.000, đủ 26 công. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BH_TRUYEN',
	'ho_ten' => 'Người Lương Tháng', 'cua_hang' => 'BH_SHOP', 'chuc_vu' => 'Nhân Viên',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
/* Người tính theo GIỜ — để canh rằng ai KHÔNG trong sổ thì không bị trừ gì. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BH_GIO',
	'ho_ten' => 'Người Theo Giờ', 'cua_hang' => 'BH_SHOP', 'chuc_vu' => 'Nhân Viên',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
foreach ( array( 'BH_TRUYEN', 'BH_GIO' ) as $ma_x ) {
	for ( $i = 1; $i <= 26; $i++ ) {
		$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => $ma_x, 'ho_ten' => '',
			'coso' => 'BH_SHOP', 'ngay' => sprintf( '2026-08-%02d', $i ),
			'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 16 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
	}
}
VHCC_ChotLuong::dat_thang( $KT, 'BH_SHOP', '2026-08', 'BH_TRUYEN', true, '4000000', '26' );

/* ====================================================================== 1. quyền */

echo "— ai sửa được sổ —\n";
t( '🔴 nhân viên thường KHÔNG sửa được sổ BHXH',
	empty( VHCC_Bhxh::dat( $NV, 'BH_TRUYEN', '596610' )['ok'] ) );
/* ⚠️ CỬA HÀNG TRƯỞNG CŨNG KHÔNG. Anh Thắng nói rõ "tab bên Phân Quyền Kế toán" — và đây là một
   khoản TRỪ tự lặp mọi tháng, gõ một lần trừ mãi. */
t( '🔴 cửa hàng trưởng cũng KHÔNG — đây là cửa kế toán',
	empty( VHCC_Bhxh::dat( $CHT, 'BH_TRUYEN', '596610' )['ok'] ) );

/* 🔴 MÃ KHÔNG CÓ THẬT THÌ CHỐI. Trừ tiền nhầm người thì tháng sau mới lộ, và lúc ấy tiền đã đi. */
$r_la = VHCC_Bhxh::dat( $KT, 'KHONG_CO_AI', '500000' );
t( '🔴 mã không có trong sổ nhân sự thì chối', empty( $r_la['ok'] ), $r_la );
t( '   và nói rõ vì sao', false !== mb_strpos( $r_la['error'], 'nhân sự' ), $r_la );

$r_to = VHCC_Bhxh::dat( $KT, 'BH_TRUYEN', '99000000' );
t( '🔴 số tiền quá lớn thì chối (gõ dư số 0)', empty( $r_to['ok'] ), $r_to );

/* ====================================================================== 2. chưa khai = không đổi gì */

echo "— chưa khai thì không ai bị trừ —\n";
$b0 = VHCC_BangLuong::dung( 'BH_SHOP', '2026-08' );
$d0 = null;
foreach ( $b0['dong'] as $x ) { if ( 'BH_TRUYEN' === $x['ma'] ) { $d0 = $x; } }
teq( 'lương chính 4.000.000 như cũ', 4000000.0, $d0['luongChinh'] );
teq( '🔴 chưa khai thì BHXH = 0', 0.0, $d0['bhxh'] );

/* ====================================================================== 3. khai và trừ */

echo "— khai rồi thì trừ đúng —\n";
$r = VHCC_Bhxh::dat( $KT, 'BH_TRUYEN', '596610', '2026-08' );
t( 'kế toán khai được', ! empty( $r['ok'] ), $r );
teq( '   nhớ đúng số tiền', 596610.0, $r['tien'] );
teq( '   và tháng bắt đầu', '2026-08', $r['tu'] );

$b = VHCC_BangLuong::dung( 'BH_SHOP', '2026-08' );
$d = null; $d_g = null;
foreach ( $b['dong'] as $x ) {
	if ( 'BH_TRUYEN' === $x['ma'] ) { $d = $x; }
	if ( 'BH_GIO' === $x['ma'] ) { $d_g = $x; }
}
teq( '🔴 dòng ấy mang đúng 596.610', 596610.0, $d['bhxh'] );
/* ⚠️ KHÔNG TRỪ VÀO LƯƠNG CHÍNH. Cột Lương chính là tiền công làm ra; BHXH là cột RIÊNG của tờ
   nộp, và tổng lương trừ nó ở bước sau. Trộn hai cột là tờ in ra lệch mẫu kế toán. */
teq( '🔴 lương chính KHÔNG bị trừ — BHXH là cột riêng', 4000000.0, $d['luongChinh'] );
/* 🔴 ĐÂY LÀ CON SỐ TRONG ẢNH: 4.000.000 − 596.610 = 3.403.390. */
teq( '🔴 người KHÔNG trong sổ thì vẫn 0', 0.0, $d_g['bhxh'] );

/* ---- cột TOTAL SALARY trên màn ---- */
$_GET = array( 'man' => 'luong', 'lcs' => 'BH_SHOP', 'lth' => '2026-08' );
$_POST = array();
$_COOKIE = array( VHCC_Web::COOKIE => VHCC_Auth::phat_token( 'KT', 'Kế toán', 'BH_SHOP', 'BHKT' ) );
ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
$_GET = array(); $_COOKIE = array();
t( '🔴 màn bảng lương hiện 596.610 ở cột BHXH',
	false !== strpos( $h, '596.610' ), 'không thấy 596.610' );
t( '🔴 và TOTAL SALARY là 3.403.390 — đúng tờ anh Thắng gửi',
	false !== strpos( $h, '3.403.390' ), 'không thấy 3.403.390' );
/* ⚠️ `4.000.000` VẪN CÓ MẶT ĐÚNG HAI LẦN, và đúng như vậy: cột **Lương cb** và cột **Lương
   chính**. BHXH là cột riêng, không trừ vào hai cột ấy. Thứ phải đổi là cột TOTAL SALARY —
   canh ngay trên. Canh "chỉ còn một lần" là canh nhầm thứ, và nó sẽ đỏ vì một lý do đúng. */
teq( '   Lương cb và Lương chính vẫn là 4.000.000 — BHXH không trừ vào hai cột ấy',
	2, substr_count( $h, '>4.000.000<' ) );
/* Và con số cuối hàng KHÔNG được là 4.000.000 nữa. Bóc đúng ô cuối cùng có `dam` (TOTAL
   SALARY in đậm) của hàng ấy. */
t( '🔴 ô TOTAL SALARY không còn mang số chưa trừ',
	false === strpos( $h, '<b>4.000.000</b></td><td class="mo">' ), 'ô tổng vẫn là 4.000.000' );

/* ---- tờ .xlsx ---- */
$x = VHCC_BangLuong::to_xlsx( 'BH_SHOP', '2026-08' );
t( 'dựng được tờ xuất', ! empty( $x['ok'] ), $x );
$so_x = array();
foreach ( $x['to'][0]['hang'] as $h_x ) {
	foreach ( (array) $h_x as $o_x ) {
		if ( is_array( $o_x ) ) {
			if ( isset( $o_x['v'] ) && ! is_array( $o_x['v'] ) ) { $so_x[] = (string) $o_x['v']; }
		} else { $so_x[] = (string) $o_x; }
	}
}
t( '🔴 tờ .xlsx mang số BHXH thật ở ô L', in_array( '596610', $so_x, true ), $so_x );
/* Công thức của tệp là `M = I + K − L` rồi `Z = M + U − Y`; giá trị kèm theo mỗi ô phải khớp
   chính công thức ấy, không thì mở bằng hai ứng dụng ra hai con số. */
t( '🔴 và giá trị kèm ô tổng lương đã trừ BHXH', in_array( '3403390', $so_x, true ), $so_x );

/* ---- phiếu lương nhân viên tự xem ---- */
VHCC_PhieuLuong::cong_bo( $KT, 'BH_SHOP', '2026-08', true );
$U_TR = array( 'name' => 'Người Lương Tháng', 'role' => VHCC_Vai::NV,
	'coso' => 'BH_SHOP', 'ma_nv' => 'BH_TRUYEN' );
$pl = VHCC_PhieuLuong::phieu( $U_TR, 'BH_SHOP', '2026-08' );
t( 'lấy được phiếu lương', ! empty( $pl['ok'] ), $pl );
if ( ! empty( $pl['ok'] ) ) {
	teq( '🔴 phiếu lương kể ra khoản BHXH', 596610.0, $pl['bhxh'] );
	teq( '🔴 và số cuối cùng khớp bảng lương', 3403390.0, $pl['tong'] );
	/* ⚠️ BHXH thôi nằm trong danh sách "ngoài hệ" — để lại là nói dối theo chiều ngược: người
	   đọc tưởng còn một khoản trừ chưa tính, trong khi đã trừ rồi. */
	t( '🔴 BHXH KHÔNG còn bị kể là "ngoài hệ"',
		! in_array( 'BHXH', (array) $pl['ngoaiHe'], true ), $pl['ngoaiHe'] );
}

/* ====================================================================== 4. tự lặp, và tháng bắt đầu */

echo "— tự lặp hằng tháng, từ tháng bắt đầu —\n";
for ( $i = 1; $i <= 26; $i++ ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'BH_TRUYEN', 'ho_ten' => '',
		'coso' => 'BH_SHOP', 'ngay' => sprintf( '2026-09-%02d', $i ),
		'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 16 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
}
VHCC_ChotLuong::dat_thang( $KT, 'BH_SHOP', '2026-09', 'BH_TRUYEN', true, '4000000', '26' );
$b9 = VHCC_BangLuong::dung( 'BH_SHOP', '2026-09' );
foreach ( $b9['dong'] as $x ) { if ( 'BH_TRUYEN' === $x['ma'] ) { $d9 = $x; } }
/* 🔴 KHAI MỘT LẦN, THÁNG SAU TỰ CÓ — đúng lựa chọn của anh Thắng. */
teq( '🔴 tháng sau tự trừ, không phải gõ lại', 596610.0, $d9['bhxh'] );

/* 🔴 NHƯNG THÁNG TRƯỚC THÁNG BẮT ĐẦU THÌ KHÔNG. Không có chốt này thì khai hôm nay là bảng lương
   của mọi tháng đã trả tiền xong cũng mọc thêm một khoản trừ — viết lại quá khứ. */
teq( '🔴 tháng TRƯỚC tháng bắt đầu thì KHÔNG trừ', 0.0, VHCC_Bhxh::cua( 'BH_TRUYEN', '2026-07' ) );
teq( '   đúng tháng bắt đầu thì có', 596610.0, VHCC_Bhxh::cua( 'BH_TRUYEN', '2026-08' ) );
teq( '   tháng sau nữa cũng có', 596610.0, VHCC_Bhxh::cua( 'BH_TRUYEN', '2027-03' ) );

/* ---- bỏ khỏi sổ ---- */
echo "— bỏ khỏi sổ —\n";
/* Gõ 0 cũng là bỏ — một dòng 0đ nằm trong sổ trông như đã xét xong và bằng không. */
t( 'gõ 0 là bỏ khỏi sổ', ! empty( VHCC_Bhxh::dat( $KT, 'BH_TRUYEN', '0' )['bo'] ) );
teq( '   và thôi trừ ngay', 0.0, VHCC_Bhxh::cua( 'BH_TRUYEN', '2026-08' ) );
VHCC_Bhxh::dat( $KT, 'BH_TRUYEN', '596610', '2026-08' );
t( 'nút Xoá cũng bỏ được', ! empty( VHCC_Bhxh::xoa( $KT, 'BH_TRUYEN' )['ok'] ) );
t( '🔴 xoá người không có trong sổ thì chối, không im lặng',
	empty( VHCC_Bhxh::xoa( $KT, 'BH_GIO' )['ok'] ) );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — BHXH trừ đúng, ở cả bốn nơi.\n";
