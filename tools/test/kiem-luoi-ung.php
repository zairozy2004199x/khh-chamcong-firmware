<?php
/**
 * KIỂM LƯỚI ỨNG DỤNG — ô nhỏ ba cột, chia nhóm.
 *
 * =================================================================================================
 * VIỆC NÓ GIẢI QUYẾT
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"tính năng nhiều thì ô chức năng nhỏ lại như này. Nhiều tính năng thì
 * tách phân loại theo từng tính năng"*, kèm ảnh một app chia mục WORKPLACE / HRM.
 *
 * Lưới cũ là hai cột ô to, mỗi ô mang theo tên + mô tả + lời nhắc + câu "chưa được cấp". Bốn
 * dòng chữ trong một ô thì bốn ô đã hết màn hình — và số ô chỉ có tăng.
 *
 * =================================================================================================
 * 🔴 BA THỨ KHÔNG ĐƯỢC MẤT KHI ĐỔI BỐ CỤC
 * =================================================================================================
 * 1. Ô KHOÁ VẪN PHẢI KHÔNG BẤM ĐƯỢC. Đây là phép gác thật, không phải trang trí: `VHCC_Ung::o()`
 *    bỏ hẳn `url` của ô khoá, và giao diện dựng `<div>` thay vì `<a>`. Ô nhỏ lại mà lỡ thành
 *    `<a>` kèm class mờ là năm cái ô khoá thành năm cái link sống.
 *
 * 2. CÂU "XIN QUẢN LÝ ĐẨY SANG…" VẪN PHẢI ĐỌC ĐƯỢC. Ô nhỏ không chứa nổi câu ấy, nên nó dời
 *    xuống danh sách chú thích dưới lưới. DỜI, không phải BỎ — cả điểm của việc bày ô khoá ra
 *    là để người ta biết thứ ấy tồn tại mà đi xin. Bỏ câu ấy thì ô mờ chỉ còn là một ô mờ.
 *
 * 3. THỨ TỰ NHÓM DO MÁY CHỦ ĐẶT. Để trình duyệt tự gom thì thứ tự ra theo thứ tự gặp ô, tức
 *    thêm một ô ở giữa là cả trang đổi bố cục — người dùng nhớ chỗ bằng mắt, không đọc lại
 *    tiêu đề mỗi lần.
 *
 * Chạy: php tools/test/kiem-luoi-ung.php
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

/* =============================================================== 1. THỨ TỰ NHÓM */

$nhom = VHCC_Ung::NHOM;
t( 'có khai danh sách nhóm', is_array( $nhom ) && count( $nhom ) >= 2, $nhom );

/* 🔴 SẮP THEO DANH SÁCH KHAI, KHÔNG THEO THỨ TỰ GẶP Ô. Gieo cố ý NGƯỢC thứ tự khai — nếu hàm
   chỉ gom theo thứ tự gặp thì phép thử này xanh giả, nên phải gieo ngược mới bắt được. */
$gieo = array(
	array( 'ten' => 'c', 'nhom' => $nhom[ count( $nhom ) - 1 ] ),
	array( 'ten' => 'a', 'nhom' => $nhom[0] ),
);
t( '🔴 nhóm ra đúng thứ tự KHAI, không theo thứ tự gặp ô',
	array( $nhom[0], $nhom[ count( $nhom ) - 1 ] ) === VHCC_Ung::ds_nhom( $gieo ),
	VHCC_Ung::ds_nhom( $gieo ) );

/* ⚠️ NHÓM LẠ RƠI XUỐNG CUỐI, KHÔNG BỊ NUỐT. Thà thừa một nhóm lạ ở cuối còn hơn mất một ô. */
$la = VHCC_Ung::ds_nhom( array(
	array( 'ten' => 'x', 'nhom' => 'Nhóm Chưa Khai' ),
	array( 'ten' => 'y', 'nhom' => $nhom[0] ),
) );
t( '⚠️ nhóm chưa khai KHÔNG bị nuốt', in_array( 'Nhóm Chưa Khai', $la, true ), $la );
t( 'và nó đứng SAU nhóm đã khai',
	array_search( 'Nhóm Chưa Khai', $la, true ) > array_search( $nhom[0], $la, true ), $la );

t( 'ô không khai nhóm thì về nhóm đầu',
	array( $nhom[0] ) === VHCC_Ung::ds_nhom( array( array( 'ten' => 'z' ) ) ),
	VHCC_Ung::ds_nhom( array( array( 'ten' => 'z' ) ) ) );
t( 'nhóm rỗng thì trả mảng rỗng', array() === VHCC_Ung::ds_nhom( array() ) );
t( 'hai ô cùng nhóm chỉ ra MỘT tiêu đề',
	1 === count( VHCC_Ung::ds_nhom( array(
		array( 'ten' => 'a', 'nhom' => $nhom[0] ), array( 'ten' => 'b', 'nhom' => $nhom[0] ) ) ) ) );

/* =============================================================== 2. MỌI Ô ĐỀU KHAI NHÓM */

/* Ô quên khai nhóm thì vẫn hiện (rơi về nhóm đầu) nên MẮT KHÔNG THẤY SAI — chỉ có nó nằm nhầm
   mục. Đúng loại lỗi phải bắt bằng phép thử chứ không bắt bằng nhìn màn hình. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-ung.php' );
$i_ds = strpos( $src, 'public static function ds(' );
$than_ds = substr( $src, $i_ds, strpos( $src, 'private static function co_lop' ) - $i_ds );
$so_ten  = preg_match_all( "/'ten'\s*=>/", $than_ds );
$so_nhom = preg_match_all( "/'nhom'\s*=>/", $than_ds );
t( 'ds() có dựng ô, nếu không thì phép thử dưới vô nghĩa', $so_ten > 0, $so_ten );
t( '🔴 MỌI ô trong ds() đều khai nhóm', $so_ten === $so_nhom,
	array( 'ten' => $so_ten, 'nhom' => $so_nhom ) );

preg_match_all( "/'nhom'\s*=>\s*'([^']+)'/", $than_ds, $m_n );
foreach ( array_unique( $m_n[1] ) as $x ) {
	t( '🔴 nhóm "' . $x . '" có trong danh sách khai NHOM', in_array( $x, $nhom, true ), $nhom );
}

/* =============================================================== 2b. Ô MỞ MÀN TRONG TRẠM */

/* Anh Thắng 17/09/2026: *"Chuyển sang thêm nhân sự là 1 tính năng"*, kèm ảnh khoanh đúng ô
   trống trong lưới. Trước bản này lưới CHỈ biết ô dẫn sang app khác (`url`); nay biết thêm ô
   mở một màn ngay trong trạm (`man`). */
$u_cht = array( 'name' => 'Trưởng', 'role' => VHCC_Vai::CHT, 'coso' => 'LU_SHOP', 'ma_nv' => 'LU1' );
$u_nv  = array( 'name' => 'NV',     'role' => VHCC_Vai::NV,  'coso' => 'LU_SHOP', 'ma_nv' => 'LU2' );

function o_ten( $ds, $ten ) {
	foreach ( (array) $ds as $x ) { if ( $ten === $x['ten'] ) { return $x; } }
	return null;
}
$o_them = o_ten( VHCC_Ung::ds( $u_cht ), 'Thêm nhân sự' );
t( '🔴 cửa hàng trưởng có ô "Thêm nhân sự"', is_array( $o_them ), $o_them );
t( 'ô ấy mở một MÀN trong trạm, không dẫn đi đâu',
	$o_them && 'mThemNv' === $o_them['man'] && ! isset( $o_them['url'] ), $o_them );
t( 'và nằm đúng nhóm Quản lý cửa hàng',
	$o_them && 'Quản lý cửa hàng' === $o_them['nhom'], $o_them );

/* 🔴 GÁC BẰNG ĐÚNG QUYỀN MÀ CỬA THẬT ĐÒI (`them_nv`). Gác bằng một quyền khác là hai luật, và
   hai luật thì lệch — bày ô cho người gõ xong mới bị chối. */
t( '🔴 nhân viên thường KHÔNG thấy ô Thêm nhân sự',
	null === o_ten( VHCC_Ung::ds( $u_nv ), 'Thêm nhân sự' ), VHCC_Ung::ds( $u_nv ) );
t( 'quyền gác ô đúng bằng quyền của cửa thật',
	VHCC_Vai::duoc( $u_cht, 'them_nv' ) && ! VHCC_Vai::duoc( $u_nv, 'them_nv' ) );

/* =============================================================== 3. Ô KHOÁ VẪN KHOÁ */

/* 🔴 Xem chốt 1 đầu tệp. Đổi bố cục là lúc dễ đánh rơi phép gác nhất, vì mắt chỉ soi cái mới. */
$rf = new ReflectionMethod( 'VHCC_Ung', 'o' );
$rf->setAccessible( true );
$khoa = $rf->invokeArgs( null, array( false, array(
	'ten' => 'Thử', 'url' => 'https://vi.du/', 'ghi_chu' => 'nhắc gì đó',
	'xin' => 'Xin quản lý mở quyền.', 'nhom' => $nhom[0] ) ) );
t( '🔴 ô khoá BỎ HẲN url, không chỉ gắn một cái cờ', ! isset( $khoa['url'] ), $khoa );
/* 🔴 Ô MỞ MÀN CŨNG PHẢI MẤT ĐƯỜNG MỞ. Bỏ mỗi `url` mà quên `man` là ô khoá trông thì mờ nhưng
   bấm vẫn ra màn — đúng cái lỗi mà cả phép gác này sinh ra để chặn. */
$khoa_m = $rf->invokeArgs( null, array( false, array(
	'ten' => 'Thử màn', 'man' => 'mThemNv', 'nhom' => $nhom[0] ) ) );
t( '🔴 ô khoá cũng BỎ HẲN man', ! isset( $khoa_m['man'] ), $khoa_m );
t( 'ô khoá bỏ luôn lời nhắc (chỉ có nghĩa khi vào được)', ! isset( $khoa['ghi_chu'] ), $khoa );
t( '⚠️ nhưng GIỮ câu xin quyền — xem chốt 2', ! empty( $khoa['xin'] ), $khoa );
t( 'ô khoá giữ nhóm để vẫn nằm đúng mục', $nhom[0] === $khoa['nhom'], $khoa );

$mo = $rf->invokeArgs( null, array( true, array(
	'ten' => 'Thử', 'url' => 'https://vi.du/', 'nhom' => $nhom[0] ) ) );
t( 'ô mở thì giữ url', ! empty( $mo['url'] ), $mo );

/* =============================================================== 4. CỬA TRẠM */

$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
$i = strpos( $tram, "'ung' === \$viec" );
t( 'trạm có cửa ung', false !== $i );
$khoi = substr( $tram, $i, 600 );
t( '🔴 cửa ung trả kèm thứ tự nhóm — xem chốt 3',
	false !== strpos( $khoi, 'VHCC_Ung::ds_nhom(' ), $khoi );
t( 'và vẫn trả danh sách ô', false !== strpos( $khoi, "'ds'" ), $khoi );

/* =============================================================== 5. MÀN HÌNH */

$tpl  = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$css  = substr( $tpl, strpos( $tpl, '<style' ), strpos( $tpl, '</style>' ) - strpos( $tpl, '<style' ) );
$than = substr( $tpl, 0, strpos( $tpl, '<style' ) ) . substr( $tpl, strpos( $tpl, '</style>' ) );

t( '🔴 lưới ba cột', false !== strpos( $css, 'grid-template-columns:repeat(3,minmax(0,1fr))' ), $css );
t( 'có kiểu cho tiêu đề nhóm', false !== strpos( $css, '.nhom-ten{' ), $css );
t( 'có kiểu cho danh sách chú thích dưới lưới', false !== strpos( $css, '.ghi-ung{' ), $css );

/* ⚠️ VÙNG CHẠM 48px — ngưỡng của Apple. Ô nhỏ lại là đúng chỗ dễ tụt xuống dưới ngưỡng nhất. */
$i_o = strpos( $css, '.o-ung{' );
t( '⚠️ ô vẫn giữ vùng chạm tối thiểu 48px',
	false !== $i_o && false !== strpos( substr( $css, $i_o, 400 ), 'min-height:48px' ),
	substr( $css, $i_o, 400 ) );

t( 'JS gom ô theo nhóm máy chủ gửi', false !== strpos( $than, 'j.nhom' ), $than );
t( '🔴 nhóm máy chủ quên kể vẫn được vẽ, không bị nuốt',
	false !== strpos( $than, "nhom.indexOf(tn) < 0" ), $than );
t( '🔴 ô khoá vẫn dựng bằng <div>, không phải <a>',
	false !== strpos( $than, "'<div class=\"o-ung o-khoa\">'" ), $than );
/* 🔴 Ô MỞ MÀN DỰNG BẰNG <button>, KHÔNG PHẢI <a href="#">. Thẻ <a> rỗng thì bấm là nhảy lên
   đầu trang và trên iOS còn đổi cả địa chỉ — người dùng thấy trang giật một cái rồi không có
   gì. `<button type="button">` không có hành vi mặc định nào. */
t( '🔴 ô mở màn dựng bằng <button type="button">',
	false !== strpos( $than, "'<button type=\"button\" class=\"o-ung o-man\"" ), $than );
t( 'và có kiểu gỡ nét mặc định của nút', false !== strpos( $tpl, 'button.o-ung{border:0' ), $tpl );
t( 'lưới gài sự kiện cho ô mở màn',
	false !== strpos( $than, "querySelectorAll('.o-man')" ), $than );
/* Danh sách trắng khi mở màn: tên lạ thì `el()` trả null và `hien()` nổ, chết cả khối JS sau. */
t( '🔴 mở màn theo danh sách trắng, không mở bừa theo chuỗi máy chủ gửi',
	false !== strpos( $than, "if('mThemNv' === ten){ moThemNv(); }" ), $than );
t( '🔴 câu xin quyền vẫn được vẽ ra, chỉ dời chỗ',
    false !== strpos( $than, 'chưa được cấp.' ) && false !== strpos( $than, 'x.xin' ), $than );
t( 'lời nhắc của ô mở cũng dời xuống chú thích',
	false !== strpos( $than, 'x.ghi_chu' ), $than );

/* Ô nhỏ chỉ còn icon + tên. Còn sót lớp mô tả trong ô là chữ tràn ra ngoài ba cột. */
$i_ruot = strpos( $than, "var ruot = '<span class=\"o-icon" );
t( 'ruột ô dựng được', false !== $i_ruot );
$ruot = substr( $than, $i_ruot, 260 );
t( '🔴 ô chỉ còn ICON + TÊN, không nhét mô tả vào trong ô',
	false === strpos( $ruot, 'o-mo' ), $ruot );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — lưới nhỏ lại và chia nhóm, mà ô khoá vẫn khoá và câu xin quyền"
	. " vẫn đọc được.\n";
