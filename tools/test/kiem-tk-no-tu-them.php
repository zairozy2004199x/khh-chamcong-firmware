<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KẾ TOÁN TỰ THÊM MÃ TK NỢ CHO MỘT DÒNG — `set_line_tk_no( $id, $tk, $them )`.
 * Anh Thắng 24/09/2026 (ảnh ô chọn TK Nợ): *"Tạo mã này hay, để kế toán tự gán, nó chuẩn hơn,
 * với nếu thiếu có thể thêm mã để kế toán tự tạo số mới đúng"*.
 * Cờ `them` mở cửa cho mã CHƯA khai ở ma trận, nhưng: phải là số 3–10 chữ số, không được là TK Có,
 * chỉ kế toán, và nhật ký ghi rõ "tự thêm". Không cờ → luật cũ y nguyên.
 * Chạy: php tools/test/kiem-tk-no-tu-them.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-24 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

VHCP_Cfg::save_config( array(
	'coso' => array( array( 'ten' => 'FARM NHA TRANG', 'maDonVi' => 'FARM', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => 'FARM MN' ) ),
	'loaiChiPhi' => array(
		array( 'ten' => 'Chi phí cơ sở', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'khoi' => 'mn' ),
	),
	'tkNoMatrix' => array( array( 'nhom' => 'Chi phí cơ sở', 'pll' => 'FARM MN', 'tkNo' => '64166' ) ),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Kế Toán A', 'pin' => '2222', 'vaiTro' => 'Kế toán cá nhân' ),
	),
) );
VHCP_Cfg::clear_cache();

$d = VHCP_Don::create_don( 'T9/2026 (21/9-27/9/2026)', 'Kế Toán A' );
$m = $d['maDon'];
VHCP_Don::add_line( $m, array( 'coso' => 'FARM NHA TRANG', 'ngay' => '2026-09-22', 'phanLoaiTT' => 'Thanh toán cá nhân',
	'nhom' => 'Chi phí cơ sở', 'noiDung' => 'cỏ', 'soLuong' => 1, 'donGia' => 50000, 'thanhTien' => 50000 ) );
$don = VHCP_Don::get_don( $m );
$id  = (string) $don['lines'][0]['id'];
t( '⚠️ có dòng để thử', '' !== $id, $don );
teq( '   mã tự động ban đầu từ ma trận', '64166', (string) $don['lines'][0]['tkNo'] );

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
/* ═══ 1. Không cờ → luật cũ ═══ */
$r = VHCP_Don::set_line_tk_no( $id, '64211' );
t( '🔴 không cờ, mã chưa khai → chối như cũ và chỉ đường sang "＋ Mã khác"', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'Mã khác — kế toán tự thêm' ), $r );
/* ═══ 2. Có cờ → nhận ═══ */
$r = VHCP_Don::set_line_tk_no( $id, '64211', 1 );
teq( '🔴 có cờ them → nhận mã chưa khai', '64211', isset( $r['tkNo'] ) ? $r['tkNo'] : $r );
teq( '   dòng mang mã mới', '64211', (string) VHCP_Don::get_don( $m )['lines'][0]['tkNo'] );
$log = VHCP_Log::get_log( 50 );
$co_log = false;
foreach ( (array) $log as $lg ) {
	$s = json_encode( $lg, JSON_UNESCAPED_UNICODE );
	if ( false !== mb_strpos( $s, '64211' ) && false !== mb_strpos( $s, 'kế toán tự thêm' ) ) { $co_log = true; }
}
t( '🔴 nhật ký ghi rõ "kế toán tự thêm"', $co_log, $log );
/* ═══ 3. Cờ không mở cửa cho mã sai ═══ */
$r = VHCP_Don::set_line_tk_no( $id, '64a1', 1 );
t( '🔴 them mà mã không phải số → chối', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], '3–10 chữ số' ), $r );
$r = VHCP_Don::set_line_tk_no( $id, '141', 1 );
t( '🔴 them mà là TK Có (141) → vẫn chối', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'TK CÓ' ), $r );
teq( '   dòng vẫn giữ 64211 sau hai lượt chối', '64211', (string) VHCP_Don::get_don( $m )['lines'][0]['tkNo'] );
/* ═══ 4. Ô trống → trả về tự động, cờ không đổi gì ═══ */
$r = VHCP_Don::set_line_tk_no( $id, '', 1 );
teq( '   trống + cờ → vẫn trả về mã tự động 64166', '64166', isset( $r['tkNo'] ) ? $r['tkNo'] : $r );
/* ═══ 5. Không phải kế toán → chối dù có cờ ═══ */
VHCP_Auth::dat_vai_tro( 'Quản lý', 'Kế Toán A' );
$r = VHCP_Don::set_line_tk_no( $id, '64211', 1 );
t( '🔴 Quản lý không được tự thêm mã', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'Chỉ kế toán' ), $r );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: cờ them cho kế toán gắn mã chưa khai lên MỘT dòng; vẫn soi số, gạt TK Có, ghi nhật ký; không cờ thì luật cũ.\n";
