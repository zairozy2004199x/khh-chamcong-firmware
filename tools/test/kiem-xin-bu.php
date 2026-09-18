<?php
/**
 * KIỂM ĐƠN XIN BÙ GIỜ — nhân viên gửi, cửa hàng trưởng duyệt, kế toán duyệt lần cuối.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CẦN
 * =================================================================================================
 * Anh Thắng 18/09/2026 thu quyền bù của cửa hàng trưởng và thay bằng quy trình hai cấp. Bốn chỗ
 * hỏng, và cả bốn đều làm hỏng đúng thứ quy trình này sinh ra để giữ:
 *
 *   1. CẤP MỘT GHI THẲNG — nếu `duyet_cht()` ghi vào bảng công thì cửa hàng trưởng vừa lấy lại
 *      đúng cái quyền bù vừa bị thu, bằng đường vòng qua một cái đơn do nhân viên mình gửi.
 *   2. GIỜ CHƯA DUYỆT LỌT VÀO LƯƠNG — giờ đang xin phải nằm ở bảng riêng, không bao giờ nằm tạm
 *      trong `cham_cong`. Nằm tạm là mọi phép cộng lương phải nhớ loại nó ra.
 *   3. TỰ GỬI TỰ DUYỆT — cửa hàng trưởng cũng chấm công như mọi người.
 *   4. GHI TRƯỢT MÀ ĐƠN VẪN BIẾN MẤT — đơn nằm chờ mấy ngày, trong lúc ấy máy có thể đã ghi được
 *      giờ thật. Lúc ấy `VHCC_Bu::ghi()` chối, và đơn PHẢI ở nguyên chỗ cũ.
 *
 * Chạy: php tools/test/kiem-xin-bu.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
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
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

global $wpdb;

$CS_A = 'XB_SHOP_A';
$CS_B = 'XB_SHOP_B';
$HOM  = (string) current_time( 'Y-m-d' );
$QUA  = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 86400 );
$KIA  = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 2 * 86400 );

foreach ( array(
	array( 'XBNV1',  'An Nhân Viên',   $CS_A, 'Nhân viên' ),
	array( 'XBCHTA', 'Trưởng A',       $CS_A, 'Cửa hàng trưởng' ),
	array( 'XBCHTB', 'Trưởng B',       $CS_B, 'Cửa hàng trưởng' ),
	array( 'XBKT1',  'Kế Toán',        $CS_A, 'Kế toán' ),
) as $x ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $x[0], 'ho_ten' => $x[1],
		'cua_hang' => $x[2], 'vai_tro' => $x[3], 'trang_thai_lam_viec' => 'Đang làm' ) );
}

$NV    = array( 'name' => 'An Nhân Viên', 'role' => VHCC_Vai::NV,      'coso' => $CS_A, 'ma_nv' => 'XBNV1' );
$CHT_A = array( 'name' => 'Trưởng A',     'role' => VHCC_Vai::CHT,     'coso' => $CS_A, 'ma_nv' => 'XBCHTA' );
$CHT_B = array( 'name' => 'Trưởng B',     'role' => VHCC_Vai::CHT,     'coso' => $CS_B, 'ma_nv' => 'XBCHTB' );
$KT    = array( 'name' => 'Kế Toán',      'role' => VHCC_Vai::KE_TOAN, 'coso' => '',    'ma_nv' => 'XBKT1' );

/* ================================================================= cửa hàng trưởng hết bù */

echo "— bậc quyền —\n";
t( '🔴 cửa hàng trưởng KHÔNG còn quyền bù', ! VHCC_Vai::duoc( $CHT_A, 'cham_bu' ) );
t( '🔴 và không còn quyền sửa giờ',          ! VHCC_Vai::duoc( $CHT_A, 'sua_gio' ) );
t( 'nhưng VẪN duyệt được cấp một',            VHCC_Vai::duoc( $CHT_A, VHCC_XinBu::QUYEN_CHT ) );
t( 'kế toán duyệt được cấp hai',              VHCC_Vai::duoc( $KT, VHCC_XinBu::QUYEN_KT ) );
t( '🔴 cửa hàng trưởng KHÔNG duyệt được cấp hai', ! VHCC_Vai::duoc( $CHT_A, VHCC_XinBu::QUYEN_KT ) );
t( 'nhân viên gửi được đơn',                  VHCC_Vai::duoc( $NV, VHCC_XinBu::QUYEN_GUI ) );

$r = VHCC_Bu::ghi( $CHT_A, array( 'coso' => $CS_A, 'ngay' => $QUA, 'ma_nv' => 'XBNV1',
	'vao' => '08:00', 'ra' => '17:00', 'ly_do' => 'thử bù thẳng xem có lọt không' ) );
t( '🔴 cửa hàng trưởng bù thẳng thì bị chối', empty( $r['ok'] ), $r );
teq( '🔴 và bảng công KHÔNG có gì', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );

/* ================================================================= gửi đơn */

echo "— gửi đơn —\n";
$r = VHCC_XinBu::gui( $NV, $QUA, '08:00', '17:00', 'x' );
t( '🔴 lý do quá ngắn thì chối', empty( $r['ok'] ), $r );

$r = VHCC_XinBu::gui( $NV, $QUA, '17:00', '08:00', 'máy hỏng sáng hôm ấy, có camera' );
t( 'giờ ra trước giờ vào thì chối', empty( $r['ok'] ), $r );

$r = VHCC_XinBu::gui( $NV, $QUA, '08:00', '', 'máy hỏng sáng hôm ấy, có camera' );
t( 'thiếu giờ ra thì chối', empty( $r['ok'] ), $r );

$mai = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) + 86400 );
$r = VHCC_XinBu::gui( $NV, $mai, '08:00', '17:00', 'xin trước cho ngày mai' );
t( '🔴 xin cho ngày mai thì chối', empty( $r['ok'] ), $r );

$xa = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 200 * 86400 );
$r = VHCC_XinBu::gui( $NV, $xa, '08:00', '17:00', 'xin bù từ hồi năm ngoái' );
t( 'xin quá xa thì chối', empty( $r['ok'] ), $r );

$r = VHCC_XinBu::gui( $NV, $QUA, '08:00', '17:00', 'máy hỏng sáng hôm ấy, có camera' );
t( 'gửi được', ! empty( $r['ok'] ), $r );
teq( 'và nằm ở cấp một', VHCC_XinBu::CHO_CHT, $r['trangThai'] );
$id = (int) $r['id'];

/* 🔴 GỬI ĐƠN KHÔNG ĐƯỢC CHẠM VÀO BẢNG CÔNG. */
teq( '🔴 gửi xong bảng công vẫn rỗng', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );

$r = VHCC_XinBu::gui( $NV, $QUA, '09:00', '18:00', 'gửi lại lần nữa cho ngày ấy' );
t( 'ngày đã có đơn treo thì chối gửi tiếp', empty( $r['ok'] ), $r );

/* Ngày ĐÃ CÓ giờ chấm thì không xin bù — đó là SỬA, đi đường tệp tuần. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS_A, 'ngay' => $KIA,
	'ma_nv' => 'XBNV1', 'ho_ten' => 'An Nhân Viên', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
$r = VHCC_XinBu::gui( $NV, $KIA, '07:00', '16:00', 'muốn đổi giờ ngày ấy' );
t( '🔴 ngày đã có giờ chấm thì KHÔNG xin bù được', empty( $r['ok'] ), $r );
t( 'và câu chối chỉ sang đường sửa', false !== mb_strpos( $r['error'], 'bảng công tuần' ), $r['error'] );

/* ================================================================= cấp một */

echo "— cấp một: cửa hàng trưởng —\n";
$r = VHCC_XinBu::duyet_cht( $NV, $id, true );
t( '🔴 nhân viên KHÔNG tự duyệt đơn mình', empty( $r['ok'] ), $r );

$r = VHCC_XinBu::duyet_cht( $CHT_B, $id, true );
t( '🔴 trưởng cơ sở KHÁC không duyệt được', empty( $r['ok'] ), $r );

$r = VHCC_XinBu::duyet_kt( $KT, $id, true );
t( '🔴 kế toán KHÔNG nhảy cóc qua cấp một', empty( $r['ok'] ), $r );
t( 'và nói rõ chưa tới bước của mình', false !== mb_strpos( $r['error'], 'chưa tới bước' ), $r['error'] );

$r = VHCC_XinBu::duyet_cht( $CHT_A, $id, false, 'x' );
t( 'chối mà không nói vì sao thì không cho', empty( $r['ok'] ), $r );

$r = VHCC_XinBu::duyet_cht( $CHT_A, $id, true );
t( 'trưởng A duyệt được', ! empty( $r['ok'] ), $r );
teq( 'đơn chuyển sang chờ kế toán', VHCC_XinBu::CHO_KT, $r['quyet'] );

/* 🔴 CẤP MỘT KHÔNG GHI MỘT Ô NÀO. Đây là chốt giữ cho cửa hàng trưởng không lấy lại được quyền
   bù bằng đường vòng qua đơn của chính nhân viên mình. */
teq( '🔴 cấp một duyệt xong, bảng công VẪN chưa có dòng của ngày ấy', null,
	$wpdb->get_var( $wpdb->prepare( 'SELECT gio_vao_giay FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS_A, $QUA, 'XBNV1' ) ) );

$r = VHCC_XinBu::duyet_cht( $CHT_A, $id, true );
t( 'duyệt lại cấp một thì chối', empty( $r['ok'] ), $r );

/* ================================================================= vẽ màu vàng */

echo "— giờ đang xin hiện trên lưới —\n";
$treo = VHCC_XinBu::treo_thang( $CS_A, substr( $QUA, 0, 7 ) );
$ngay_so = (int) substr( $QUA, 8, 2 );
t( '🔴 đơn đang chờ có mặt để lưới vẽ', isset( $treo['XBNV1'][ $ngay_so ] ), $treo );
teq( 'và mang đúng số giờ xin', 9.0, $treo['XBNV1'][ $ngay_so ]['gio'] );
teq( 'kèm trạng thái để nói rõ đang chờ ai', VHCC_XinBu::CHO_KT,
	$treo['XBNV1'][ $ngay_so ]['trangThai'] );

/* ================================================================= cấp hai */

echo "— cấp hai: kế toán —\n";
$r = VHCC_XinBu::duyet_kt( $CHT_A, $id, true );
t( '🔴 cửa hàng trưởng KHÔNG duyệt được cấp hai', empty( $r['ok'] ), $r );
teq( '🔴 và bảng công vẫn chưa có gì', null,
	$wpdb->get_var( $wpdb->prepare( 'SELECT gio_vao_giay FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS_A, $QUA, 'XBNV1' ) ) );

$r = VHCC_XinBu::duyet_kt( $KT, $id, true );
t( 'kế toán duyệt được', ! empty( $r['ok'] ), $r );
teq( 'đơn khép lại', VHCC_XinBu::DUYET, $r['quyet'] );

/* 🔴 ĐÂY LÀ CHỖ CẢ QUY TRÌNH TỒN TẠI VÌ NÓ. */
$d = VHCC_Bu::gio_hien_tai( $CS_A, $QUA, 'XBNV1' );
teq( '🔴 giờ vào đã lên bảng công', '08:00', $d['vao'] );
teq( '🔴 và giờ ra cũng vậy',        '17:00', $d['ra'] );

/* Đi qua `VHCC_Bu::ghi()` thật — kiểm bằng dấu vết, không bằng lời hứa. */
$nk = VHCC_Bu::ds_nhat_ky( array( 'role' => VHCC_Vai::ADMIN, 'name' => 'QT', 'ma_nv' => 'QT1' ), $CS_A );
$co = false;
foreach ( $nk as $x ) {
	if ( false !== mb_strpos( json_encode( $x, JSON_UNESCAPED_UNICODE ), 'Đơn bù #' ) ) { $co = true; }
}
t( '🔴 nhật ký ghi rõ ô ấy đến từ đơn bù nào', $co, $nk );

/* Duyệt xong thì ô không còn vàng nữa — nó là giờ thật. */
$treo2 = VHCC_XinBu::treo_thang( $CS_A, substr( $QUA, 0, 7 ) );
t( '🔴 duyệt xong thì ô hết vàng (đơn rời hàng chờ)',
	! isset( $treo2['XBNV1'][ $ngay_so ] ), $treo2 );

$r = VHCC_XinBu::duyet_kt( $KT, $id, true );
t( 'duyệt lại lần hai thì chối', empty( $r['ok'] ), $r );

/* ================================================================= ghi trượt thì đơn ở lại */

echo "— ghi trượt thì đơn ở nguyên chỗ —\n";
/* 🔴 Đơn nằm chờ mấy ngày; trong lúc ấy máy ghi được giờ thật cho ngày đó. `VHCC_Bu::ghi()`
   chối (bù chỉ điền ô TRỐNG), và lúc ấy đơn PHẢI ở nguyên `cho_kt` — đánh dấu duyệt rồi mới
   biết không ghi được là đơn biến mất khỏi hàng chờ mà bảng công không có gì. */
$ng3 = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 3 * 86400 );
$r = VHCC_XinBu::gui( $NV, $ng3, '08:00', '17:00', 'quên bấm máy hôm ấy, có camera' );
$id3 = (int) $r['id'];
VHCC_XinBu::duyet_cht( $CHT_A, $id3, true );
/* Máy sống lại, ghi giờ thật cho đúng ngày ấy. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS_A, 'ngay' => $ng3,
	'ma_nv' => 'XBNV1', 'ho_ten' => 'An Nhân Viên', 'gio_vao_giay' => 30600,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
$r = VHCC_XinBu::duyet_kt( $KT, $id3, true );
t( '🔴 ghi trượt thì báo lỗi, không báo thành công', empty( $r['ok'] ), $r );
teq( '🔴 và đơn Ở NGUYÊN chỗ chờ kế toán', VHCC_XinBu::CHO_KT,
	VHCC_XinBu::mot( $id3 )['trang_thai'] );
teq( '🔴 giờ máy ghi KHÔNG bị đè', 30600,
	(int) $wpdb->get_var( $wpdb->prepare( 'SELECT gio_vao_giay FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS_A, $ng3, 'XBNV1' ) ) );

/* ================================================================= chối */

echo "— chối —\n";
$ng4 = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 4 * 86400 );
$r = VHCC_XinBu::gui( $NV, $ng4, '08:00', '17:00', 'quên bấm máy hôm ấy' );
$id4 = (int) $r['id'];
$r = VHCC_XinBu::duyet_cht( $CHT_A, $id4, false, 'hôm ấy em không có mặt ở cửa hàng' );
t( 'cấp một chối được', ! empty( $r['ok'] ), $r );
teq( 'đơn khép lại ở trạng thái không duyệt', VHCC_XinBu::TU_CHOI,
	VHCC_XinBu::mot( $id4 )['trang_thai'] );
teq( 'và không có dòng chấm công nào cho ngày ấy', null,
	$wpdb->get_var( $wpdb->prepare( 'SELECT gio_vao_giay FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS_A, $ng4, 'XBNV1' ) ) );
/* Chối rồi thì gửi lại được — đó là điểm của việc chối. */
$r = VHCC_XinBu::gui( $NV, $ng4, '13:00', '22:00', 'em làm ca chiều hôm ấy, có camera' );
t( 'chối rồi thì gửi lại được', ! empty( $r['ok'] ), $r );

/* ================================================================= không đi tắt */

echo "— không đi tắt —\n";
$src = '';
foreach ( token_get_all( file_get_contents(
	$goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-xin-bu.php' ) ) as $tk ) {
	if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
	$src .= is_array( $tk ) ? $tk[1] : $tk;
}
t( '🔴 lớp này KHÔNG tự ghi vào bảng cham_cong',
	! preg_match( '#(UPDATE|INSERT|DELETE)[^;]*cham_cong#i', $src )
	&& false === strpos( $src, "VHCC_DB::t( 'cham_cong' )" ) );
t( '🔴 mà đi qua VHCC_Bu::ghi()', false !== strpos( $src, 'VHCC_Bu::ghi(' ) );

/* 🔴 `duyet_cht()` KHÔNG được gọi `VHCC_Bu::ghi()`. Soi riêng thân hàm — soi cả tệp thì luôn
   thấy vì `duyet_kt()` có gọi, và đó mới là chỗ đúng. */
preg_match( '#function duyet_cht\(.*?\n\t\}#s', $src, $m1 );
t( 'tách được thân duyet_cht()', ! empty( $m1 ) );
t( '🔴 cấp một KHÔNG gọi VHCC_Bu::ghi — nếu gọi là cửa hàng trưởng lấy lại được quyền bù',
	! empty( $m1 ) && false === strpos( $m1[0], 'VHCC_Bu::ghi(' ) );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — hai cấp duyệt, và giờ chỉ vào bảng công ở cấp hai.\n";
