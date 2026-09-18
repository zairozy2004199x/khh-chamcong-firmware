<?php
/**
 * HAI TAB MỚI: "BẢNG LƯƠNG" VÀ "ĐƠN TỪ".
 *
 * Anh Thắng 18/09/2026, hai câu liền nhau:
 *   · *"Với chuyển nó ra 1 tab như tính năng, vì sau để bên báo cáo họ lấy dữ liệu lương cho
 *     dễ"* — bảng lương ra khỏi đuôi màn Bảng công;
 *   · *"Chuyển cái này ra 1 tab riêng ( Đơn từ )"* — bốn khối đơn (đi trễ · xin nghỉ · xin bù
 *     giờ · sửa bảng công tháng bằng Excel) cũng vậy.
 *
 * =============================================================================================
 * 🔴 BÀI NÀY CANH BA THỨ, VÀ THỨ THỨ BA MỚI LÀ THỨ HAY HỎNG
 * =============================================================================================
 *   1. Tab có trong cột dọc, và có địa chỉ đi thẳng (`?man=luong&lcs=…&lth=…`).
 *   2. Nội dung THẬT SỰ vẽ ra ở màn mới, và KHÔNG còn ở màn cũ.
 *   3. DỜI KHÔNG ĐƯỢC NỚI QUYỀN. Thêm một cửa vào là thêm một chỗ phải gác; quên một chỗ là
 *      nhân viên bậc 1 đọc được bảng lương cả cửa hàng. Nên có phép thử cho đúng chuyện đó.
 *
 * ⚠️ VÀ MỘT LỖ CŨ ĐƯỢC VÁ NHỜ DỜI. Ở chỗ cũ bốn khối đơn nằm TRONG chốt
 *    `'cong' !== VHCC_Luong::cach_tinh( $cs )` — chốt ấy sinh ra cho khối KHAI CA, rồi bốn khối
 *    đơn mọc dần vào trong nó. Hậu quả: cửa hàng trưởng của một cơ sở tính THEO CÔNG không có
 *    cửa nào duyệt đơn, và không một dòng nào nói ra. Bài này canh cả hai cách tính.
 *
 * Chạy: php tools/test/kiem-tab-luong-don-tu.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 300 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}

global $wpdb;

$CS  = 'TAB_SHOP';
$TH  = '2026-08';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CS, 'bo_phan' => 'Khu vui chơi' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TAB_CHT', 'ho_ten' => 'Chị Trưởng Tab',
	'vai_tro' => 'Cửa hàng trưởng', 'cua_hang' => $CS, 'coso_quan' => $CS, 'chuc_vu' => 'Partime',
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TAB_NV', 'ho_ten' => 'Em Nhân Viên Tab',
	'vai_tro' => 'Nhân viên', 'cua_hang' => $CS, 'chuc_vu' => 'Partime',
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $TH . '-03',
	'ma_nv' => 'TAB_NV', 'ho_ten' => 'Em Nhân Viên Tab', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );

$U_AD  = array( 'name' => 'Admin', 'role' => VHCC_Vai::ADMIN, 'coso' => '', 'ma_nv' => '' );
VHCC_GiaGio::dat_coso( $U_AD, $CS, array( 'Partime' => 26000 ) );

$U_CHT = array( 'name' => 'Chị Trưởng Tab', 'role' => VHCC_Vai::CHT, 'coso' => $CS, 'ma_nv' => 'TAB_CHT' );
$U_NV  = array( 'name' => 'Em Nhân Viên Tab', 'role' => VHCC_Vai::NV, 'coso' => $CS, 'ma_nv' => 'TAB_NV' );

function tab_man( $ma_nv, $vai, $coso, $get ) {
	$_GET = $get; $_POST = array();
	$_COOKIE = array( VHCC_Web::COOKIE => VHCC_Auth::phat_token( 'Người ' . $ma_nv, $vai, $coso, $ma_nv ) );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_COOKIE = array();
	return $h;
}

/* ================================================================== 1. TAB CÓ TRONG CỘT DỌC */

echo "— tab có mặt —\n";
$man_cht = VHCC_Web::man_cua( $U_CHT );
t( '🔴 cửa hàng trưởng có tab Bảng lương', isset( $man_cht['luong'] ), array_keys( $man_cht ) );
t( '🔴 cửa hàng trưởng có tab Đơn từ', isset( $man_cht['don_tu'] ), array_keys( $man_cht ) );
$man_nv = VHCC_Web::man_cua( $U_NV );
t( '🔴 nhân viên bậc 1 KHÔNG có tab Bảng lương', ! isset( $man_nv['luong'] ), array_keys( $man_nv ) );

/* Mỗi tab phải có biểu tượng và một câu mô tả — thiếu thì Trang chính vẽ ra một ô câm. */
foreach ( array( 'luong', 'don_tu' ) as $m ) {
	t( 'tab ' . $m . ' có biểu tượng', '▪' !== VHCC_Web::bieu_man( $m ), VHCC_Web::bieu_man( $m ) );
	t( 'tab ' . $m . ' có câu mô tả', '' !== VHCC_Web::chu_man( $m ), VHCC_Web::chu_man( $m ) );
}

/* ============================================================= 2. NỘI DUNG VẼ Ở MÀN MỚI */

echo "— nội dung sang màn mới —\n";
$h_luong = tab_man( 'TAB_CHT', 'Cửa hàng trưởng', $CS,
	array( 'man' => 'luong', 'lcs' => $CS, 'lth' => $TH ) );
t( '🔴 màn Bảng lương có bảng lương', false !== mb_strpos( $h_luong, 'Bảng lương cơ sở' ), $h_luong );
t( '   và có tên người trong bảng', false !== mb_strpos( $h_luong, 'Em Nhân Viên Tab' ) );
t( '   và có nút xuất .xlsx', false !== mb_strpos( $h_luong, 'xuat=luong' ) );
t( '   và có khối đơn giá đi kèm', false !== mb_strpos( $h_luong, 'Đơn giá giờ' ), $h_luong );

/* 🔴 ĐƯỜNG ĐI THẲNG PHẢI THẬT SỰ TỚI NƠI — đây là lý do anh Thắng xin tab này. Bí danh cũ
   `luong -> cham` từng nuốt đúng địa chỉ này về màn Bảng công, mà màn vẫn vẽ ra nên không ai
   thấy hỏng. Nên hỏi thẳng: mở `?man=luong` có ra MÀN BẢNG LƯƠNG không, hay ra màn khác. */
t( '🔴 ?man=luong ra đúng màn Bảng lương, không rơi về Bảng công',
	false !== mb_strpos( $h_luong, '💵 Bảng lương' )
	&& false === mb_strpos( $h_luong, 'Lưới cả tháng' ), mb_substr( $h_luong, 0, 200 ) );

$h_don = tab_man( 'TAB_CHT', 'Cửa hàng trưởng', $CS, array( 'man' => 'don_tu', 'lcs' => $CS ) );
foreach ( array( 'id="lenhtre"' => 'Lệnh đi trễ', 'Đơn xin nghỉ' => 'Đơn xin nghỉ',
	'Đơn xin bù giờ' => 'Đơn xin bù giờ',
	'Sửa bảng công tháng bằng Excel' => 'Sửa bảng công tháng' ) as $dau => $ten ) {
	t( '🔴 màn Đơn từ có khối "' . $ten . '"', false !== mb_strpos( $h_don, $dau ), $ten );
}

/* ====================================================== 3. KHÔNG CÒN Ở MÀN CŨ (dời, không chép) */

echo "— màn cũ đã sạch —\n";
$h_cham = tab_man( 'TAB_CHT', 'Cửa hàng trưởng', $CS,
	array( 'man' => 'cham', 'ccs' => $CS, 'cth' => $TH ) );
t( '🔴 màn Bảng công KHÔNG còn bảng lương (dời, không phải chép làm hai bản)',
	false === mb_strpos( $h_cham, 'Bảng lương cơ sở' ) );
t( '🔴 màn Bảng công KHÔNG còn khối Lệnh đi trễ',
	false === mb_strpos( $h_cham, 'id="lenhtre"' ) );
t( '🔴 màn Bảng công KHÔNG còn khối Sửa bảng công tháng',
	false === mb_strpos( $h_cham, 'Sửa bảng công tháng bằng Excel' ) );
/* Nhưng vẫn còn LƯỚI — dời hai khối không được kéo theo thứ khác. */
t( 'màn Bảng công vẫn còn lưới cả tháng', false !== mb_strpos( $h_cham, 'Lưới cả tháng' ) );
/* 🔴 VÀ PHẢI CÓ DÒNG CHỈ ĐƯỜNG. Dời việc mà im lặng thì người quen tay cuộn xuống không thấy,
   rồi kết luận là hệ hỏng — họ không có cách nào biết nó sang đâu. */
t( '🔴 màn Bảng công chỉ đường sang hai tab mới',
	false !== mb_strpos( $h_cham, 'man=luong' ) && false !== mb_strpos( $h_cham, 'man=don_tu' ), $h_cham );

/* ================================================== 4. DỜI KHÔNG ĐƯỢC NỚI QUYỀN */

echo "— dời không nới quyền —\n";
/* Nhân viên bậc 1 mở thẳng địa chỉ: không được thấy một đồng nào của người khác. */
$h_nv = tab_man( 'TAB_NV', 'Nhân viên', $CS, array( 'man' => 'luong', 'lcs' => $CS, 'lth' => $TH ) );
t( '🔴 nhân viên gõ thẳng ?man=luong: KHÔNG ra bảng lương',
	false === mb_strpos( $h_nv, 'TOTAL SALARY' ), $h_nv );
t( '   và KHÔNG thấy tên người khác kèm tiền',
	false === mb_strpos( $h_nv, 'Bảng lương cơ sở' ), $h_nv );

/* Cửa hàng trưởng gõ mã cơ sở KHÔNG phải của mình: chối, và chối RA TIẾNG (không phải màn
   trắng — màn trắng là thứ người dùng không sửa được). */
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'TAB_LA', 'bo_phan' => 'Khu vui chơi' ) );
$h_la = tab_man( 'TAB_CHT', 'Cửa hàng trưởng', $CS,
	array( 'man' => 'luong', 'lcs' => 'TAB_LA', 'lth' => $TH ) );
t( '🔴 cơ sở KHÔNG quản: không ra bảng lương', false === mb_strpos( $h_la, 'TOTAL SALARY' ), $h_la );
t( '   và nói ra vì sao, không để màn trắng',
	false !== mb_strpos( $h_la, 'không phải cơ sở anh/chị quản lý' ), $h_la );

$h_la_d = tab_man( 'TAB_CHT', 'Cửa hàng trưởng', $CS, array( 'man' => 'don_tu', 'lcs' => 'TAB_LA' ) );
t( '🔴 màn Đơn từ cũng chối cơ sở không quản', false === mb_strpos( $h_la_d, 'id="lenhtre"' ), $h_la_d );

/* ============================ 5. LỖ CŨ: CƠ SỞ TÍNH THEO CÔNG TỪNG KHÔNG CÓ CỬA DUYỆT ĐƠN */

echo "— cơ sở THEO CÔNG cũng duyệt được đơn —\n";
VHCC_Luong::dat_cach_tinh( $U_AD, array( $CS => 'cong' ) );
t( 'dựng được cảnh cách tính cong', 'cong' === VHCC_Luong::cach_tinh( $CS ) );
$h_don_c = tab_man( 'TAB_CHT', 'Cửa hàng trưởng', $CS, array( 'man' => 'don_tu', 'lcs' => $CS ) );
t( '🔴 cơ sở THEO CÔNG vẫn có khối Lệnh đi trễ — lỗ cũ, vá nhờ dời tab',
	false !== mb_strpos( $h_don_c, 'id="lenhtre"' ), $h_don_c );
t( '🔴 và vẫn có khối Sửa bảng công tháng',
	false !== mb_strpos( $h_don_c, 'Sửa bảng công tháng bằng Excel' ), $h_don_c );
VHCC_Luong::dat_cach_tinh( $U_AD, array( $CS => 'gio' ) );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — Bảng lương và Đơn từ là hai tab thật, có đường đi thẳng, và không nới quyền.\n";
