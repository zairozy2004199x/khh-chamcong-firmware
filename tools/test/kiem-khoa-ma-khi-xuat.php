<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KHOÁ MÃ TK KHI CHỐT XUẤT MISA · LỌC TK NỢ THEO CÂY · NÚT GÁN MÃ KHÔNG ĐỤNG ĐƠN ĐÃ XUẤT.
 * Anh Thắng 24/09/2026: *"sau khi bấm xuất misa thì nó sẽ khóa chi phí đó theo tk nợ được cài sẵn…
 * đổi tk nợ cho loại chi phí thì chỉ thay đổi chi phí đang diễn ra, chứ không được thay đổi chi phí
 * cũ, như sáng bị lỗi 1 lần"* · *"641 là cha của 6412 và cháu là 64122, lọc 641 nó sẽ ra cả con và cháu"*.
 * Chạy: php tools/test/kiem-khoa-ma-khi-xuat.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-24 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; } $TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

function cau_hinh( $ma_coso, $ma_vh ) {
	VHCP_Cfg::save_config( array(
		'coso' => array( array( 'ten' => 'FARM NHA TRANG', 'maDonVi' => 'FARM', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => 'FARM NHA TRANG' ) ),
		'loaiChiPhi' => array(
			array( 'ten' => 'Chi phí cơ sở', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
			array( 'ten' => 'Chi phí vận hành', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
			array( 'ten' => 'Chi phí lương', 'tkNo' => '6421', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
		),
		'tkNoMatrix' => array(
			array( 'nhom' => 'Chi phí cơ sở', 'pll' => 'FARM MN', 'tkNo' => $ma_coso ),
			array( 'nhom' => 'Chi phí vận hành', 'pll' => 'FARM MN', 'tkNo' => $ma_vh ),
		),
		'phanloai' => array( array( 'ten' => 'Thanh toán cá nhân', 'tkCo' => '141' ), array( 'ten' => 'Nhà cung cấp', 'tkCo' => '331' ) ),
		'users' => array( array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ), array( 'ten' => 'Kế Toán A', 'pin' => '2222', 'vaiTro' => 'Kế toán cá nhân' ) ),
	) );
	VHCP_Cfg::clear_cache();
}
function don_xong( $ky, $dong ) {
	$d = VHCP_Don::create_don( $ky, 'Kế Toán A' ); $m = $d['maDon']; $tong = 0;
	foreach ( $dong as $x ) { VHCP_Don::add_line( $m, array( 'coso' => 'FARM NHA TRANG', 'ngay' => '2026-09-22', 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => $x[0], 'noiDung' => $x[1], 'soLuong' => 1, 'donGia' => $x[2], 'thanhTien' => $x[2] ) ); $tong += $x[2]; }
	VHCP_Don::set_tam_ung( $m, 'FARM NHA TRANG', $tong ); VHCP_Don::gui_duyet_tam_ung( $m ); VHCP_Don::duyet_tam_ung( $m, 'Kế Toán A', '' );
	VHCP_Don::cap_tam_ung( $m, 'Kế Toán A', 'Tiền mặt' ); VHCP_Don::gui_quyet_toan( $m ); VHCP_Don::xac_nhan_qt_cn_nhieu( array( $m ), 'Kế Toán A' ); return $m;
}
function dong_tk( $m ) { $ra = array(); foreach ( VHCP_Don::get_don( $m )['lines'] as $l ) { $ra[ $l['noiDung'] ] = (string) $l['tkNo']; } return $ra; }
function tk_trong_tep( $ex, $nd ) { foreach ( $ex['rows'] as $r ) { if ( false !== mb_strpos( (string) $r[4], $nd ) ) { return (string) $r[5]; } } return null; }

/* ═══ 1. Xuất và chốt → mã đóng vào dòng ═══ */
cau_hinh( '64166', '6412' );
$m1 = don_xong( 'T9/2026 (14/9-20/9/2026)', array( array( 'Chi phí cơ sở', 'cỏ', 50000 ), array( 'Chi phí vận hành', 'điện', 300000 ), array( 'Chi phí lương', 'lương', 1000000 ) ) );
$ex = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
teq( '⚠️ trước chốt: tệp tra bảng mã hiện tại', array( '64166', '6412', '6421' ), array( tk_trong_tep( $ex, 'cỏ' ), tk_trong_tep( $ex, 'điện' ), tk_trong_tep( $ex, 'lương' ) ) );
$r = VHCP_Misa::mark_exported( array( $m1 ), 'all' );
teq( '🔴 chốt đã xuất → báo 3 dòng có mã đóng', 3, isset( $r['dongMa'] ) ? $r['dongMa'] : $r );
teq( '🔴 mã đóng vào từng dòng', array( 'cỏ' => '64166', 'điện' => '6412', 'lương' => '6421' ), dong_tk( $m1 ) );
/* Dòng nhập lúc bảng mã còn khác → lúc CHỐT ghi lại theo bảng đang có, đúng cái tệp vừa xuất. */
$m1b = don_xong( 'T9/2026 (7/9-13/9/2026)', array( array( 'Chi phí cơ sở', 'cỏ b', 10000 ) ) );
global $wpdb; $wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'tk_no' => '64100' ), array( 'ma_don' => $m1b ) );
teq( '   trước chốt dòng mang mã cũ 64100 (không còn trong bảng)', '64100', dong_tk( $m1b )['cỏ b'] );
VHCP_Misa::mark_exported( array( $m1b ), 'all' );
teq( '🔴 chốt xuất ghi lại mã theo bảng LÚC CHỐT (64166), thay mã cũ lệch', '64166', dong_tk( $m1b )['cỏ b'] );
teq( '   đơn sang Đã xuất MISA', 'Đã xuất MISA', (string) VHCP_Don::get_don( $m1 )['don']['trangThai'] );

/* ═══ 2. Đổi bảng mã → đơn đã xuất KHÔNG đổi, đơn mới đổi ═══ */
cau_hinh( '64199', '6413' );
$ex_cu = VHCP_Misa::export_misa( 'all', 'daxuat', 'all' );
teq( '🔴 xuất lại đơn đã xuất: vẫn mã cũ đã đóng, không theo bảng mới', array( '64166', '6412' ), array( tk_trong_tep( $ex_cu, 'cỏ' ), tk_trong_tep( $ex_cu, 'điện' ) ) );
$m2 = don_xong( 'T9/2026 (21/9-27/9/2026)', array( array( 'Chi phí cơ sở', 'cỏ mới', 70000 ) ) );
$ex_moi = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
teq( '🔴 đơn đang chạy → theo bảng mới', '64199', tk_trong_tep( $ex_moi, 'cỏ mới' ) );

/* ═══ 3. Kế toán không sửa được TK Nợ của dòng đã xuất ═══ */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
$id1 = (string) VHCP_Don::get_don( $m1 )['lines'][0]['id'];
$r = VHCP_Don::set_line_tk_no( $id1, '6413', 1 );
t( '🔴 kế toán chỉnh TK Nợ dòng đã xuất → chối, nói rõ đã xuất MISA và chỉ Admin', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'đã xuất MISA' ) && false !== mb_strpos( (string) $r['error'], 'Chỉ Admin' ), $r );
teq( '   dòng vẫn giữ mã đóng', '64166', dong_tk( $m1 )['cỏ'] );
/* Admin sửa được (anh Thắng: "sau admin sửa được không, ví dụ lần đầu gán mã sai") — có nhật ký riêng. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$r = VHCP_Don::set_line_tk_no( $id1, '64188', 1 );
teq( '🔴 Admin sửa dòng đã xuất → nhận, kèm cờ daXuat', array( '64188', 1 ), array( isset( $r['tkNo'] ) ? $r['tkNo'] : $r, isset( $r['daXuat'] ) ? $r['daXuat'] : null ) );
teq( '   dòng mang mã Admin vừa sửa', '64188', dong_tk( $m1 )['cỏ'] );
$lg = json_encode( VHCP_Log::get_log( 20 ), JSON_UNESCAPED_UNICODE );
t( '🔴 nhật ký ghi "SỬA TK Nợ SAU KHI ĐÃ XUẤT MISA" và nhắc sửa tay MISA', false !== mb_strpos( $lg, 'SỬA TK Nợ SAU KHI ĐÃ XUẤT MISA' ) && false !== mb_strpos( $lg, 'sửa tay bên MISA' ), $lg );
$ex_sua = VHCP_Misa::export_misa( 'all', 'daxuat', 'all' );
teq( '   xuất lại đơn đã xuất → mã Admin đã sửa (không lùi về bảng)', '64188', tk_trong_tep( $ex_sua, 'cỏ' ) );
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
$id2 = (string) VHCP_Don::get_don( $m2 )['lines'][0]['id'];
teq( '   đơn chưa xuất vẫn chỉnh được', '6413', VHCP_Don::set_line_tk_no( $id2, '6413' )['tkNo'] );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

/* ═══ 4. "Gán mã cho dòng cũ" không đụng đơn đã xuất ═══ */
$g = VHCP_Don::gan_ma_tai_khoan( true );
teq( '🔴 gán lại mọi dòng: bỏ qua 4 dòng của hai đơn đã xuất', 4, isset( $g['boQuaDaXuat'] ) ? $g['boQuaDaXuat'] : $g );
teq( '   đơn đã xuất giữ nguyên mã đóng (kể cả mã Admin vừa sửa)', array( 'cỏ' => '64188', 'điện' => '6412', 'lương' => '6421' ), dong_tk( $m1 ) );
teq( '   đơn đang chạy được áp bảng mới (6413 tay → 64199 theo bảng)', '64199', dong_tk( $m2 )['cỏ mới'] );

/* ═══ 5. Lọc TK Nợ theo cây ═══ */
teq( '🔴 641 chứa 6412 và 64122; 642 không', array( true, true, false, false ), array( VHCP_Misa::thuoc_cay_tk( '6412', '641' ), VHCP_Misa::thuoc_cay_tk( '64122', '641' ), VHCP_Misa::thuoc_cay_tk( '6421', '641' ), VHCP_Misa::thuoc_cay_tk( '', '641' ) ) );
$cay = VHCP_Misa::cay_tk( array( '64166', '6412', '6421' ) );
$ma = array_map( function ( $x ) { return $x['ma'] . ( $x['thuc'] ? '' : '*' ); }, $cay );
teq( '🔴 cây để đổ ô lọc: mã có dòng + mọi mã cha (đánh * = cha không có dòng riêng)', array( '641*', '6412', '6416*', '64166', '642*', '6421' ), $ma );
$m3 = don_xong( 'T9/2026 (21/9-27/9/2026)', array( array( 'Chi phí cơ sở', 'phân', 20000 ), array( 'Chi phí vận hành', 'nước', 40000 ), array( 'Chi phí lương', 'lương 2', 500000 ) ) );
$loc = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_CHUAN, '641' );
$co = array(); foreach ( $loc['rows'] as $r ) { $co[ (string) $r[5] ] = 1; } ksort( $co ); $co = array_map( 'strval', array_keys( $co ) );
teq( '🔴 mẫu 10 cột lọc 641 → ra 64199 (cơ sở) và 6413/64199… là con; 6421 (lương) bị loại', array( '6413', '64199' ), $co );
t( '   mẫu 10 cột vẫn 10 cột khi lọc', 10 === count( $loc['cols'] ) );
$soct_cha = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_SOCT, '641' );
teq( '🔴 sổ chi tiết lọc mã CHA trúng 2 mã con → thêm cột TK Nợ (14 cột)', 14, count( $soct_cha['cols'] ) );
teq( '   cột đầu là TK Nợ của từng dòng', 'TK Nợ', $soct_cha['cols'][0] );
$soct_la = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', VHCP_Misa::MAU_SOCT, '6421' );
teq( '   lọc mã lá → đúng 13 cột', 13, count( $soct_la['cols'] ) );
t( '   trả tkCay cho màn', isset( $loc['tkCay'] ) && count( $loc['tkCay'] ) >= 3 && isset( $loc['tkCay'][0]['ma'] ), $loc['tkCay'] );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: chốt xuất đóng mã vào dòng, đổi bảng mã không đụng đơn đã xuất; lọc TK Nợ theo cây cha/con.\n";
