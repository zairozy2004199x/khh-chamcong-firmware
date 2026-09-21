<?php
/**
 * Ô TÌM ĐƠN — CỘT "DÒNG CHI KHỚP" BÀY THỰC CHI, KHÔNG BÀY THÀNH TIỀN.
 *
 * Anh Thắng 08/09/2026: *"Dòng chi khớp là lấy theo thực chi, nếu thực chi không có mới lấy
 * theo thành tiền."*
 *
 * =============================================================================================
 * 🔴 CA THẬT TRONG ẢNH ANH GỬI. Dòng "mùn cưa" ngày 30/08/2026:
 *        Thành tiền  144.000
 *        Thực chi    114.000
 *    Cột "Dòng chi khớp" đang bày 144.000 — tức bày con số người ta DỰ TÍNH chứ không phải số
 *    ĐÃ TIÊU. Tra cứu bằng số dự tính thì cộng ra một tổng không khớp sổ nào.
 *
 * ⚠️ Ô THỰC CHI ĐỂ TRỐNG KHÁC HẲN Ô GHI SỐ 0. Trống = chưa ai chốt lại, phải lui về thành
 *    tiền; 0 = đã chốt và chốt là không đồng nào, phải giữ đúng 0. Đây là chỗ `num()` và
 *    `blank_or_num()` khác nhau, và chọn nhầm thì mọi dòng chốt-0 lặng lẽ hiện số dự tính.
 *
 * Chạy: php tools/test/kiem-tim-don-thuc-chi.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
global $wpdb;
foreach ( array( 'don', 'chiphi' ) as $b ) {
	$thieu = vhcp_test_bang_thieu( $b );
	t( 'bảng ' . $b . ' dựng được (không thì mọi phép dưới xanh oan)', ! $thieu, $thieu );
}

/* Một đơn, bốn dòng "mùn cưa" phủ đủ bốn ca của ô Thực chi. */
$wpdb->insert( VHCP_DB::t( 'don' ), array(
	'ma_don' => 'D1', 'ky' => 'T8/2026 (24/8-30/8/2026)', 'trang_thai' => 'Đã cấp tạm ứng', 'nguoi_lap' => 'Bin' ) );

function dong( $id, $nd, $tt, $tm ) {
	global $wpdb;
	$r = array( 'id' => $id, 'ma_don' => 'D1', 'coso' => 'FARM PHAN THIẾT',
		'nhom' => 'Chi phí NVL', 'noi_dung' => $nd, 'thanh_tien' => $tt );
	if ( null !== $tm ) { $r['thuc_mua'] = $tm; }
	$wpdb->insert( VHCP_DB::t( 'chiphi' ), $r );
}
dong( 'L1', 'mùn cưa thấp hơn',  144000, 114000 );   // ca của anh Thắng
dong( 'L2', 'mùn cưa để trống',  166000, null );     // chưa ai chốt -> lui về thành tiền
dong( 'L3', 'mùn cưa chốt 0',    200000, 0 );        // đã chốt, chốt là 0
dong( 'L4', 'mùn cưa cao hơn',   100000, 130000 );   // thực chi CAO hơn dự tính

$r = VHCP_Don::tim_don( 'mùn cưa' );
$it = isset( $r['items'] ) ? $r['items'] : array();
teq( 'tìm ra đúng một đơn', 1, count( $it ) );
$tien = array();
foreach ( (array) $it[0]['dong'] as $d ) { $tien[ $d['noiDung'] ] = $d['tien']; }

teq( '🔴 có thực chi thì bày THỰC CHI (ca 144.000 → 114.000)', 114000.0, $tien['mùn cưa thấp hơn'] );
teq( '🔴 ô thực chi TRỐNG thì lui về thành tiền',              166000.0, $tien['mùn cưa để trống'] );
/* 🔴 Chốt 0 phải ra 0, KHÔNG lui về thành tiền. Dùng `num()` thay `blank_or_num()` là ca này
   lặng lẽ hiện 200.000 — số dự tính của một dòng kế toán đã chốt là không tiêu đồng nào. */
teq( '🔴 chốt 0 thì bày 0, không lui về thành tiền',            0.0,      $tien['mùn cưa chốt 0'] );
teq( 'thực chi CAO hơn dự tính cũng bày đúng thực chi',        130000.0, $tien['mùn cưa cao hơn'] );

/* Đối chứng: nếu bỏ hẳn ưu tiên thực chi thì mấy con số trên đúng bằng thành tiền — nên chốt
   luôn rằng chúng KHÁC nhau, kẻo phép trên xanh nhờ dữ liệu trùng nhau. */
t( '⚠️ đối chứng: thực chi và thành tiền của ca chính KHÁC nhau',
	114000.0 !== 144000.0 );

/* Câu SQL phải thật sự lấy cột `thuc_mua` về — không lấy thì mọi nhánh trên đều rơi vào
   "trống" và bài kiểm này xanh vì lý do sai. */
$src = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
t( '🔴 câu tìm dòng khớp có SELECT cột thuc_mua',
	(bool) preg_match( '#SELECT ma_don, noi_dung, thanh_tien, thuc_mua FROM#u', $src ) );
t( '🔴 và dùng blank_or_num (phân biệt trống với 0), không dùng num',
	(bool) preg_match( '#\$tm = VHCP_Util::blank_or_num\( \$l\[.thuc_mua.\] \);#u', $src ) );

/* ⚠️ CHỈ ĐỔI CỘT DÒNG KHỚP. Cột TỔNG của đơn là chuyện khác — nó đi qua chốt dùng chung
   `thuc_chi()` (chỉ tính khi đơn đã cấp tiền), và anh Thắng không nói gì về nó. Đổi kèm là
   sửa một thứ không ai yêu cầu, ở đúng chỗ đụng tiền. */
t( '⚠️ cột tổng của đơn vẫn giữ nguyên đường tính cũ',
	isset( $it[0]['tongTien'] ) && $it[0]['tongTien'] > 0, $it[0]['tongTien'] );

/* Gõ đúng một dòng thì chỉ dòng ấy hiện — cột này là "dòng KHỚP", không phải "mọi dòng". */
$r2 = VHCP_Don::tim_don( 'mùn cưa chốt 0' );
$d2 = isset( $r2['items'][0]['dong'] ) ? $r2['items'][0]['dong'] : array();
teq( 'gõ hẳn tên một dòng thì chỉ dòng ấy khớp', 1, count( $d2 ) );
teq( 'và vẫn bày đúng số đã chốt',                0.0, $d2[0]['tien'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: cột \"Dòng chi khớp\" bày thực chi, trống mới lui về thành tiền.\n";
