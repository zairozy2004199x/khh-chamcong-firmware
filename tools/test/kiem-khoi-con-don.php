<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * "KHỐI NÀO CÒN ĐƠN TRONG KHO NÀY" — CHẠY THẬT `VHCP_Don::khoi_con_don()`.
 *
 * Sinh ra từ lượt 22/09/2026, khi anh Thắng báo *"chi phí máy tự động áp dụng web riêng nên
 * không dùng chung nữa"*. Nút MTĐ rời thanh khối, NHƯNG chỉ rời khi kho này thật sự hết đơn
 * MTĐ — đơn cũ là chứng từ kế toán, có đơn chưa xuất MISA, và mọi màn đều lọc theo khối đang
 * đứng. Hàm này là thứ quyết định nút ấy còn hay mất.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI LÀ PHP, KHÔNG PHẢI JS ĐỌC MÃ NGUỒN
 * =============================================================================================
 * Bài `kiem-mtd-ra-web-rieng.js` có một phép canh "hỏi TOÀN KHO, không lọc theo người đang
 * xem", viết bằng cách dò chuỗi `SELECT DISTINCT khoi FROM $t`. Phá thử bằng cách thêm
 * ` WHERE nguoi_lap = 'x'` vào chính câu ấy — tức biến nó thành câu hỏi theo người — mà bài
 * vẫn XANH: chuỗi kia vẫn nằm nguyên trong câu dài hơn.
 *
 * Đó là một chốt THẬT: đếm theo người đang xem thì Admin thấy nút MTĐ, kế toán không, trên cùng
 * một kho — hai người nhìn hai hệ thống khác nhau, và người không thấy nút sẽ kết luận là mất
 * dữ liệu. Nên phép chốt chuyển sang đây, gọi thẳng hàm thật với sổ thật.
 *
 * Chạy: php tools/test/kiem-khoi-con-don.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . var_export( $them, true ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }
function sap( $a ) { $a = (array) $a; sort( $a ); return $a; }

global $wpdb;
$T = VHCP_DB::t( 'don' );
$wpdb->query( "DELETE FROM $T" );

/* ═══ 1. KHO RỖNG ════════════════════════════════════════════════════════════════ */
teq( '🔴 kho chưa có đơn nào → không khối nào', array(), sap( VHCP_Don::khoi_con_don() ) );

/* ═══ 2. MỖI KHỐI MỘT ĐƠN, CỦA NHỮNG NGƯỜI KHÁC NHAU ════════════════════════════ */
$wpdb->insert( $T, array( 'ma_don' => 'D_KVC', 'ky' => 'T9/2026', 'nguoi_lap' => 'NV KVC',
	'trang_thai' => 'Nháp', 'khoi' => 'kvc' ) );
$wpdb->insert( $T, array( 'ma_don' => 'D_MTD', 'ky' => 'T9/2026', 'nguoi_lap' => 'NV MTĐ',
	'trang_thai' => 'Đã quyết toán', 'khoi' => 'mtd' ) );
teq( '🔴 hai khối có đơn → trả về đủ hai', array( 'kvc', 'mtd' ), sap( VHCP_Don::khoi_con_don() ) );

/* ═══ 3. 🔴 TOÀN KHO, KHÔNG THEO NGƯỜI ĐANG XEM ═════════════════════════════════
 * Đây là chốt mà bài .js không canh nổi. Kế toán chỉ phụ trách một nhà vẫn phải thấy rằng kho
 * này CÒN sổ MTĐ — nếu không, nút MTĐ biến mất với họ mà còn với Admin, và họ báo mất dữ liệu.
 * ═════════════════════════════════════════════════════════════════════════════════════════ */
$vai_cu = VHCP_Auth::vai_tro();
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'KT POSH', '222222', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'NV KVC',  '444444', 'Nhân viên',       '', '', '', '', 'K&H',  '' ),
), false );
VHCP_Cfg::clear_cache();
foreach ( array(
	array( 'Admin', 'Sếp' ),
	array( 'Kế toán cá nhân', 'KT POSH' ),
	array( 'Nhân viên', 'NV KVC' ),
) as $ai ) {
	VHCP_Auth::dat_vai_tro( $ai[0], $ai[1] );
	teq( '🔴 "' . $ai[1] . '" (' . $ai[0] . ') cũng thấy đủ hai khối',
		array( 'kvc', 'mtd' ), sap( VHCP_Don::khoi_con_don() ) );
}
VHCP_Auth::dat_vai_tro( $vai_cu );

/* ═══ 4. Ô `khoi` RỖNG TÍNH LÀ KVC ══════════════════════════════════════════════
 * ⚠️ Cùng luật với mặc định của cột và với `lap_khoi()`. Bỏ qua chúng là một nhúm đơn cũ
 *    không khối nào nhận — và không khối nào nhận nghĩa là không màn nào hiện. */
$wpdb->query( "DELETE FROM $T" );
$wpdb->insert( $T, array( 'ma_don' => 'D_CU', 'ky' => 'T8/2026', 'nguoi_lap' => 'NV Cũ',
	'trang_thai' => 'Nháp', 'khoi' => '' ) );
teq( '🔴 đơn chưa có dấu khối → tính là kvc, không rơi ra ngoài',
	array( 'kvc' ), sap( VHCP_Don::khoi_con_don() ) );

/* ⚠️ Hoa thường và khoảng trắng: dữ liệu nạp từ sổ cũ có đủ kiểu. Trả về 'MTD' thay vì 'mtd'
   là giao diện so không khớp, nút biến mất, và lại là cảnh "tưởng mất dữ liệu". */
$wpdb->insert( $T, array( 'ma_don' => 'D_HOA', 'ky' => 'T8/2026', 'nguoi_lap' => 'x',
	'trang_thai' => 'Nháp', 'khoi' => ' MTD ' ) );
teq( '⚠️ khối viết hoa / thừa khoảng trắng vẫn về đúng mã thường',
	array( 'kvc', 'mtd' ), sap( VHCP_Don::khoi_con_don() ) );

/* ⚠️ KHÔNG TRÙNG LẶP. Hai đơn cùng khối phải ra một mã, không phải hai. */
$wpdb->insert( $T, array( 'ma_don' => 'D_HOA2', 'ky' => 'T8/2026', 'nguoi_lap' => 'x',
	'trang_thai' => 'Nháp', 'khoi' => 'mtd' ) );
teq( '⚠️ nhiều đơn cùng khối → vẫn một mã', array( 'kvc', 'mtd' ), sap( VHCP_Don::khoi_con_don() ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( count( $TRUOT ) ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: `khoi_con_don()` hỏi toàn kho, không lọc theo người đang xem.\n";
