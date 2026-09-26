<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * AI BẤM ĐƯỢC NÚT "＋ TẠO ĐƠN MỚI" — thêm khoá quyền `taoDon`.
 *
 * Anh Thắng 26/09/2026: *"giờ check kế toán sao chưa có nút tạo đơn"* — vai "Kế Toán Khu Vui
 * Chơi" đã tích đúng ở bảng Loại chi phí (⚙️ Cấu hình → Loại chi phí × Mảng kinh doanh) nhưng
 * vẫn không tạo được đơn "Chi Phí Vận Hành", vì nút "＋ Tạo đơn mới" trên màn gác CỨNG theo VAI
 * GỐC (`Nhân viên`/`Quản lý`/`Admin`) — không đọc bảng Loại chi phí, không đọc ma trận Phân
 * quyền, kế toán không bao giờ bấm được dù tích gì.
 *
 * Chốt: thêm khoá `taoDon` vào ma trận Phân quyền (`VHCP_Cfg::actions()`), mặc định GIỮ ĐÚNG
 * hành vi cũ (Quản lý + Nhân viên; Admin luôn qua, không cần bảng này) — không site nào đổi
 * hành vi khi lên bản. Admin muốn mở cho MỘT vai kế toán cụ thể thì tự tích ở bảng Phân quyền,
 * không mở tràn cho mọi vai kế toán.
 *
 * Máy chủ (`VHCP_Api::required_roles()`) VỐN KHÔNG gác vai nào ở `createDon` — mọi vai đã gọi
 * được từ trước, nên khoá mới này chỉ là MÀN HÌNH (không cần đổi gì phía máy chủ).
 *
 * Chạy: php tools/test/kiem-quyen-tao-don.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

$dat = 0; $truot = array();
function t( $ten, $ok, $them = null ) {
	global $dat, $truot;
	if ( $ok ) { $dat++; return; }
	$truot[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* ── 0. Khoá quyền có trong danh mục, mặc định giữ đúng hành vi CŨ ─────────────────────────── */
$acts = array(); foreach ( VHCP_Cfg::actions() as $a ) { $acts[ $a['key'] ] = $a; }
t( '🔴 có khoá quyền taoDon', isset( $acts['taoDon'] ), array_keys( $acts ) );
teq( '🔴 mặc định: Quản lý + Nhân viên (đúng ba vai gốc cũ trừ Admin — Admin luôn qua, không cần bảng này)',
	array( 'Quản lý' => 1, 'Nhân viên' => 1 ), $acts['taoDon']['def'] );
t( '   Kế toán cá nhân / Kế toán NCC KHÔNG có trong mặc định', empty( $acts['taoDon']['def']['Kế toán cá nhân'] ) && empty( $acts['taoDon']['def']['Kế toán NCC'] ), $acts['taoDon']['def'] );

/* ── 1. Bản cài cũ (chưa từng lưu ma trận Phân quyền) vẫn ra đúng mặc định ─────────────────── */
$qc = VHCP_Cfg::get_quyen_config(); $qtd = null;
foreach ( (array) $qc['actions'] as $a ) { if ( 'taoDon' === $a['key'] ) { $qtd = $a['perms']; } }
t( '🔴 bản cài cũ: Quản lý + Nhân viên bấm được, Kế toán cá nhân thì không', $qtd && ! empty( $qtd['Quản lý'] ) && ! empty( $qtd['Nhân viên'] ) && empty( $qtd['Kế toán cá nhân'] ), $qtd );

/* ── 2. Vai con kế thừa vai gốc khi CHƯA tự tích riêng ─────────────────────────────────────── */
VHCP_Cfg::save_config( array(
	'vaiTro' => array( array( 'ten' => 'Kế Toán Khu Vui Chơi', 'goc' => 'Kế toán cá nhân' ) ),
) );
VHCP_Cfg::clear_cache();
$q1 = VHCP_Cfg::get_quyen();
t( '🔴 vai con MỚI (chưa tự tích) → kế thừa vai gốc: Kế Toán Khu Vui Chơi cũng KHÔNG bấm được',
	empty( $q1['taoDon']['Kế Toán Khu Vui Chơi'] ), $q1['taoDon'] );

/* ── 3. Admin tự tích RIÊNG cho vai con này → chỉ vai này bấm được, các vai kế toán khác vẫn không ── */
VHCP_Cfg::save_config( array(
	'vaiTro' => array(
		array( 'ten' => 'Kế Toán Khu Vui Chơi', 'goc' => 'Kế toán cá nhân' ),
		array( 'ten' => 'Kế Toán Máy Tự Động', 'goc' => 'Kế toán cá nhân' ),
	),
) );
$mx = array();
foreach ( VHCP_Cfg::roles() as $r ) { $mx[ $r ] = ! empty( VHCP_Cfg::get_quyen()['taoDon'][ $r ] ); }
$mx['Kế Toán Khu Vui Chơi'] = true;   // Admin tự tích riêng cho vai này
VHCP_Cfg::set_quyen( array( 'taoDon' => $mx ) );
VHCP_Cfg::clear_cache();
$q2 = VHCP_Cfg::get_quyen();
teq( '🔴 chỉ "Kế Toán Khu Vui Chơi" bấm được, "Kế Toán Máy Tự Động" (cùng cha) vẫn không — không mở tràn',
	array( true, false ), array( ! empty( $q2['taoDon']['Kế Toán Khu Vui Chơi'] ), ! empty( $q2['taoDon']['Kế Toán Máy Tự Động'] ) ) );
t( '   vai gốc Nhân viên/Quản lý không đổi', ! empty( $q2['taoDon']['Nhân viên'] ) && ! empty( $q2['taoDon']['Quản lý'] ), $q2['taoDon'] );

/* ── 4. Máy chủ KHÔNG gác vai nào ở createDon — mọi vai đã gọi được từ trước, khoá mới chỉ là màn hình ── */
$rf = new ReflectionClass( 'VHCP_Api' );
$m  = $rf->getMethod( 'required_roles' ); $m->setAccessible( true );
teq( '🔴 required_roles(createDon) rỗng — máy chủ không gác vai, khoá mới không đụng gì phía máy chủ', array(), $m->invoke( null, 'createDon' ) );

if ( $truot ) { echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n"; foreach ( $truot as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: khoá quyền taoDon cho nút Tạo đơn mới, mặc định giữ nguyên, tích riêng theo từng vai con.\n";
