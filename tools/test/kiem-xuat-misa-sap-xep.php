<?php
/**
 * KIỂM THỨ TỰ DÒNG CỦA TỆP XUẤT MISA (anh Thắng 07/09/2026).
 *
 * =============================================================================================
 * 🔴 ĐẦU BÀI
 * =============================================================================================
 * *"Chỗ phần xuất misa. Sắp xếp theo cùng phân loại lớn."*
 *
 * Ảnh anh gửi là tệp Excel xuất ra: dòng 2-10 mảng FZ, dòng 11 GHOST, 12-13 TUTU, 14 VR, rồi
 * 15 trở đi lại FZ. Xen kẽ như thế vì bản trước gom theo LOẠI chi phí, mà "Chi phí cơ sở" trải
 * khắp mọi mảng — nên trong một nhóm loại, các dòng xếp theo NGÀY và mảng nào cũng lẫn vào.
 *
 * Kế toán vào MISA soát theo TỪNG MẢNG, nên đang phải lọc lại bằng tay ở Excel trước khi nhập.
 *
 * =============================================================================================
 * ⚠️ VÌ SAO PHẢI CÓ BÀI RIÊNG
 * =============================================================================================
 * `test-flows.php` có đủ phép về MISA (số cột, số dòng, mã tài khoản, số tiền) nhưng KHÔNG
 * phép nào canh THỨ TỰ dòng. Đảo tung thứ tự lên nó vẫn xanh — nghĩa là đúng thứ thay đổi lần
 * này không có ai canh. Bài này lấp đúng chỗ ấy.
 *
 * 🔴 VÀ CANH CẢ "KHÔNG MẤT DÒNG NÀO". Sắp xếp lại là lúc dễ đánh rơi nhất: một khoá gom viết
 *    hụt thì mấy dòng lặng lẽ đè lên nhau, tổng tiền tụt mà tệp trông vẫn bình thường.
 *
 * Chạy: php tools/test/kiem-xuat-misa-sap-xep.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

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

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-07 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
global $wpdb;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * BỐI CẢNH — bốn mảng như ngoài đời, cộng một cơ sở CHƯA KHAI mảng
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$KY = 'T8/2026 (24/8-30/8/2026)';
/* Tên mảng cố ý KHÔNG theo thứ tự bảng chữ cái lúc khai, và cố ý trùng chữ đầu ở hai cái
   (TUTU / TAU) — có thế mới thấy phép sắp xếp thật sự chạy chứ không ăn may theo thứ tự khai. */
$CS = array(
	'EVENT FZ MN'    => 'FUNZONE',
	'EVENT GHOST MN' => 'GHOST',
	'TUTU MN'        => 'TUTU',
	'EVENT VR MN'    => 'VR',
	'KHO CHUA KHAI'  => '',           // 🔴 chưa khai mảng — phải xuống cuối, không lẫn vào
);
$ds_coso = array();
foreach ( $CS as $ten => $pll ) {
	$ds_coso[] = array( 'ten' => $ten, 'maDonVi' => strtoupper( substr( md5( $ten ), 0, 6 ) ),
		'phanLoaiLon' => $pll, 'tenMisa' => $ten );
}
VHCP_Cfg::save_config( array(
	'coso'       => $ds_coso,
	'tkNoMatrix' => array(
		array( 'nhom' => 'Chi phí cơ sở',    'pll' => 'FUNZONE', 'tkNo' => '64196' ),
		array( 'nhom' => 'Chi phí cơ sở',    'pll' => 'GHOST',   'tkNo' => '64191' ),
		array( 'nhom' => 'Chi phí cơ sở',    'pll' => 'TUTU',    'tkNo' => '64126' ),
		array( 'nhom' => 'Chi phí cơ sở',    'pll' => 'VR',      'tkNo' => '64131' ),
		array( 'nhom' => 'Chi phí cơ sở',    'pll' => '',        'tkNo' => '64199' ),
		array( 'nhom' => 'NVL đồ ăn - Mua lẻ', 'pll' => 'FUNZONE', 'tkNo' => '6421' ),
	),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Nguyễn Thị Phương Hòa', 'pin' => '2222', 'vaiTro' => 'Quản lý',
			'tkCo' => '3341', 'maDt' => 'NV_PH' ),
	),
) );

/**
 * Một đơn đi hết quy trình tới "Đã quyết toán" (điều kiện để lọt vào tệp MISA).
 * `$dong` = mảng [ngày, loại chi phí, nội dung, tiền].
 */
function don_xong( $ky, $coso, $dong ) {
	$d = VHCP_Don::create_don( $ky, 'Nguyễn Thị Phương Hòa' );
	$m = $d['maDon'];
	$tong = 0;
	foreach ( $dong as $x ) {
		VHCP_Don::add_line( $m, array( 'coso' => $coso, 'ngay' => $x[0],
			'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => $x[1], 'noiDung' => $x[2],
			'soLuong' => 1, 'donGia' => $x[3], 'thanhTien' => $x[3] ) );
		$tong += $x[3];
	}
	VHCP_Don::set_tam_ung( $m, $coso, $tong );
	VHCP_Don::gui_duyet_tam_ung( $m );
	VHCP_Don::duyet_tam_ung( $m, 'Nguyễn Thị Phương Hòa', '' );
	VHCP_Don::cap_tam_ung( $m, 'Nguyễn Thị Phương Hòa', 'Tiền mặt' );
	VHCP_Don::gui_quyet_toan( $m );
	VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m ), 'Nguyễn Thị Phương Hòa' );
	return $m;
}

/* 🔴 DỰNG ĐÚNG CẢNH TRONG ẢNH: các mảng XEN KẼ theo ngày. Nếu dựng sẵn theo thứ tự mảng thì
   bỏ hẳn phép sắp xếp đi bài vẫn xanh — thứ tự tình cờ đúng. */
don_xong( $KY, 'EVENT FZ MN',    array( array( '2026-08-16', 'Chi phí cơ sở', 'khăn lau', 50000 ) ) );
don_xong( $KY, 'EVENT GHOST MN', array( array( '2026-08-24', 'Chi phí cơ sở', 'công tác', 90000 ) ) );
don_xong( $KY, 'TUTU MN',        array( array( '2026-08-24', 'Chi phí cơ sở', 'kẹo bắn súng', 30000 ) ) );
don_xong( $KY, 'EVENT VR MN',    array( array( '2026-08-24', 'Chi phí cơ sở', 'xịt phòng', 20000 ) ) );
don_xong( $KY, 'EVENT FZ MN',    array(
	array( '2026-08-30', 'Chi phí cơ sở', 'cước', 11000 ),
	array( '2026-08-30', 'Chi phí cơ sở', 'mùn cưa', 12000 ),
	/* Loại chi phí KHÁC trong cùng một mảng — để canh tầng gom thứ hai. */
	array( '2026-08-30', 'NVL đồ ăn - Mua lẻ', 'bánh mì', 13000 ),
) );
don_xong( $KY, 'TUTU MN',        array( array( '2026-08-30', 'Chi phí cơ sở', 'móc dán', 40000 ) ) );
/* 🔴 LOẠI KHÁC NẰM GIỮA HAI NGÀY CỦA LOẠI KIA — cùng mảng FUNZONE, ngày 18/8 kẹp giữa 16/8 và
   30/8. Không có ca này thì bỏ hẳn tầng gom theo loại đi bài vẫn xanh: thứ tự chèn tình cờ đã
   để mấy dòng cùng loại nằm liền nhau. */
don_xong( $KY, 'EVENT FZ MN',    array( array( '2026-08-18', 'NVL đồ ăn - Mua lẻ', 'trà sữa', 14000 ) ) );
/* 🔴 ĐƠN NHẬP MUỘN MÀ MANG NGÀY CŨ — nhập sau cùng, ngày 19/8, tức KẸP GIỮA mấy dòng đã có.
   Vòng gom chạy theo thứ tự CHÈN của bảng chi phí, không theo ngày, nên không có ca này thì
   phép "ngày tăng dần" xanh nhờ may: dữ liệu thử tình cờ được nhập đúng thứ tự thời gian.
   Ca này cũng là ca duy nhất bắt được tầng gom theo LOẠI: nó đẩy một dòng "Chi phí cơ sở" ra
   sau hai dòng NVL trong cùng mảng. */
don_xong( $KY, 'EVENT FZ MN',    array( array( '2026-08-19', 'Chi phí cơ sở', 'giẻ lau', 15000 ) ) );
don_xong( $KY, 'KHO CHUA KHAI',  array( array( '2026-08-18', 'Chi phí cơ sở', 'hàng lẻ', 70000 ) ) );

$ex = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
t( 'xuất được tệp', is_array( $ex ) && isset( $ex['rows'] ), $ex );
teq( '🔴 đủ 11 dòng hạch toán, không mất dòng nào', 11, (int) $ex['count'] );
teq( 'và đếm khớp với số dòng thật', 11, count( $ex['rows'] ) );

/* Tổng tiền không đổi — sắp xếp lại là lúc dễ đánh rơi dòng nhất, mà mất dòng thì tệp vẫn
   trông bình thường, chỉ có tổng tụt. */
$tong = 0;
foreach ( $ex['rows'] as $r ) { $tong += (int) VHCP_Util::num( $r[7] ); }
teq( '🔴 tổng tiền đúng bằng tổng đã nhập', 365000, $tong );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 DÒNG CÙNG MỘT MẢNG PHẢI NẰM LIỀN NHAU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* Mảng đọc từ cột "Diễn giải (Hạch toán)" — nơi `export_misa()` ghép tên mảng vào. Dò theo
   TÊN MẢNG THẬT, không dò theo tên cơ sở: hai thứ đó khác nhau, mà cái đang canh là mảng. */
$day = array();
foreach ( $ex['rows'] as $r ) {
	$dg2 = (string) $r[4];
	$m = '(chưa khai)';
	foreach ( array( 'FUNZONE', 'GHOST', 'TUTU', 'VR' ) as $p ) {
		if ( false !== mb_strpos( $dg2, ' ' . $p . ' ' ) || false !== mb_strpos( $dg2, '_' . $p . '_' ) ) { $m = $p; }
	}
	$day[] = $m;
}
t( 'đối chứng: nhận ra mảng của từng dòng', count( array_unique( $day ) ) >= 4, $day );

/* Đi dọc danh sách, mỗi lần đổi mảng thì ghi lại. Mảng nào xuất hiện HAI LẦN RỜI NHAU nghĩa là
   nó bị cắt quãng — đúng cái anh Thắng thấy trong ảnh. */
$khuc = array(); $truoc = null;
foreach ( $day as $m ) { if ( $m !== $truoc ) { $khuc[] = $m; $truoc = $m; } }
teq( '🔴 không mảng nào bị cắt quãng', count( $khuc ), count( array_unique( $khuc ) ) );
t( '   (thứ tự các khúc)', true, $khuc );

/* ⚠️ MẢNG CHƯA KHAI XẾP CUỐI. Cơ sở chưa khai `phanLoaiLon` là chuyện cần THẤY, không phải
   chuyện cần giấu — dồn xuống đáy thì nhìn phát ra ngay còn bao nhiêu dòng chưa khai. */
teq( '🔴 mảng chưa khai nằm CUỐI cùng', '(chưa khai)', $day[ count( $day ) - 1 ] );
teq( 'và chỉ có đúng một dòng như thế', 1, count( array_filter( $day, function ( $x ) { return $x === '(chưa khai)'; } ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * TRONG MỘT MẢNG: LOẠI CHI PHÍ CŨNG PHẢI GOM
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$fz = array();
foreach ( $ex['rows'] as $i => $r ) {
	if ( $day[ $i ] !== 'FUNZONE' ) { continue; }
	$fz[] = ( false !== mb_strpos( (string) $r[4], 'NVL' ) ) ? 'NVL' : 'CS';
}
teq( 'mảng FUNZONE có 6 dòng', 6, count( $fz ) );
$khuc_fz = array(); $tr2 = null;
foreach ( $fz as $x ) { if ( $x !== $tr2 ) { $khuc_fz[] = $x; $tr2 = $x; } }
teq( '🔴 trong một mảng, loại chi phí cũng gom liền nhau', count( $khuc_fz ), count( array_unique( $khuc_fz ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ TRONG MỘT NHÓM, THỨ TỰ NGÀY GIỮ NGUYÊN
 *
 * Kế toán đối chiếu theo ngày. Gom theo mảng là để họ soát từng mảng một, KHÔNG phải để đảo
 * lộn ngày bên trong.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$ngay_cs_fz = array();
foreach ( $ex['rows'] as $i => $r ) {
	if ( $day[ $i ] !== 'FUNZONE' ) { continue; }
	if ( false !== mb_strpos( (string) $r[4], 'NVL' ) ) { continue; }
	$ngay_cs_fz[] = (string) $r[0];
}
$sap = $ngay_cs_fz;
usort( $sap, function ( $a, $b ) {
	$ka = VHCP_Util::vh_parse_dmy( $a ); $kb = VHCP_Util::vh_parse_dmy( $b );
	return ( $ka && $kb ) ? ( VHCP_Util::vh_ymd( $ka ) - VHCP_Util::vh_ymd( $kb ) ) : 0;
} );
teq( '🔴 trong nhóm, ngày tăng dần', $sap, $ngay_cs_fz );
t( 'đối chứng: nhóm ấy có hơn một ngày (nếu không thì phép trên vô nghĩa)',
	count( array_unique( $ngay_cs_fz ) ) > 1, $ngay_cs_fz );
/* 🔴 VÀ ĐỐI CHỨNG MẠNH HƠN: nhóm ấy PHẢI chứa dòng nhập muộn mang ngày cũ (19/8). Không có nó
   thì thứ tự chèn đã trùng thứ tự ngày, và phép trên không canh được gì. */
t( '🔴 đối chứng: nhóm có dòng nhập muộn mang ngày cũ',
	in_array( '19/08/2026', $ngay_cs_fz, true ), $ngay_cs_fz );
teq( 'và nó KHÔNG nằm cuối nhóm (đã được xếp về đúng chỗ theo ngày)',
	false, '19/08/2026' === $ngay_cs_fz[ count( $ngay_cs_fz ) - 1 ] );

/* ⚠️ CÙNG NGÀY THÌ GIỮ NGUYÊN THỨ TỰ NHẬP. Hai dòng "cước" và "mùn cưa" cùng ngày 30/8, nhập
   liền nhau trong một đơn — chúng phải ra đúng thứ tự ấy, không đảo.

   🔴 CHỖ MÙ CỦA BÀI KIỂM, NÓI RA CHO RÕ: từ PHP 8.0 `usort` vốn đã ổn định, nên phép này KHÔNG
      bắt được việc bỏ nhánh so `i` (phá thử 07/09/2026 xác nhận: đục ra vẫn xanh). Nó vẫn đáng
      có vì host WordPress có thể chạy PHP 7.4, nơi `usort` đảo thứ tự các phần tử bằng nhau —
      và khi ấy hai lần xuất cùng một dữ liệu ra hai tệp khác nhau, kế toán đối chiếu thì lệch. */
$nd_cs_fz = array();
foreach ( $ex['rows'] as $i => $r ) {
	if ( $day[ $i ] !== 'FUNZONE' ) { continue; }
	if ( false !== mb_strpos( (string) $r[4], 'NVL' ) ) { continue; }
	if ( (string) $r[0] !== '30/08/2026' ) { continue; }
	$nd_cs_fz[] = ( false !== mb_strpos( (string) $r[4], 'cước' ) ) ? 'cước'
		: ( ( false !== mb_strpos( (string) $r[4], 'mùn cưa' ) ) ? 'mùn cưa' : '?' );
}
teq( '⚠️ cùng ngày thì giữ nguyên thứ tự nhập', array( 'cước', 'mùn cưa' ), $nd_cs_fz );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ MÃ TÀI KHOẢN VẪN ĐI THEO ĐÚNG DÒNG CỦA NÓ
 *
 * Sắp xếp lại mà mã tài khoản lệch một dòng thì cả tệp sai mà nhìn không ra — mỗi mảng có mã
 * TK Nợ riêng, nên đây là phép bắt được chuyện đó.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$tk_theo_mang = array( 'FUNZONE' => '64196', 'GHOST' => '64191', 'TUTU' => '64126', 'VR' => '64131' );
$sai = array();
foreach ( $ex['rows'] as $i => $r ) {
	$m = $day[ $i ];
	if ( ! isset( $tk_theo_mang[ $m ] ) ) { continue; }
	if ( false !== mb_strpos( (string) $r[4], 'NVL' ) ) { continue; }   // loại khác, mã khác
	if ( (string) $r[5] !== $tk_theo_mang[ $m ] ) { $sai[] = $m . ' -> ' . $r[5]; }
}
teq( '🔴 mã TK Nợ vẫn khớp mảng của chính dòng ấy', array(), $sai );

if ( count( $truot ) ) {
	echo "\n=== XUẤT MISA: THỨ TỰ DÒNG ===\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat   TRƯỢT: " . count( $truot ) . "\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: tệp MISA gom theo mảng, không mất dòng, ngày trong nhóm giữ nguyên.\n";
