<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🧪 CỜ TÍNH NĂNG — BA MỨC, MẶC ĐỊNH "CHỈ ADMIN XEM TRƯỚC", ADMIN BẬT MỚI ÁP CHO MỌI NGƯỌI.
 * Anh Thắng 24/09/2026: *"giao diện đó admin sẽ xem trước, rồi admin cấu hình xong bấm thay đổi thì
 * nó áp dụng luôn, chứ nạp lên, nó thay đổi danh mục, các nhân viên đang đăng nhập nó mất và chưa
 * kịp set"*.
 * 🔴 CHẠY THẬT: bảng rỗng → mặc định; Admin thấy, Nhân viên không; bật → cả hai; tắt → không ai;
 *    mã lạ → bật; lưu chối sai mã/trạng thái/không phải Admin; gói khởi động chở đúng theo vai.
 * Chạy: php tools/test/kiem-co-tinh-nang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }
$MA = 'phanLoaiHaiBac';

/* ═══ 1. Bản mới cài lên: bảng rỗng → mặc định 'admin' ═══════════════════════════════════ */
vai( 'Admin', 'Anh Thắng' );
VHCP_Cfg::write( VHCP_Cfg::TN, array() ); VHCP_Cfg::clear_cache();
t( '⚠️ có tính năng phanLoaiHaiBac trong sổ, mặc định "admin", ghi bản', isset( VHCP_Cfg::TINH_NANG[ $MA ] ) && 'admin' === VHCP_Cfg::TINH_NANG[ $MA ]['mac_dinh'] && '' !== VHCP_Cfg::TINH_NANG[ $MA ]['ban'] );
teq( '🔴 chưa lưu gì → trạng thái = mặc định "admin"', 'admin', VHCP_Cfg::tinh_nang_trang_thai( $MA ) );
t( '🔴 Admin THẤY đường mới', VHCP_Cfg::tinh_nang_bat( $MA ) === true );
vai( 'Nhân viên', 'NV' );
t( '🔴 Nhân viên KHÔNG thấy (vẫn đường cũ)', VHCP_Cfg::tinh_nang_bat( $MA ) === false );
vai( 'Kế toán cá nhân', 'KT' );
t( '   Kế toán cũng không (chỉ Admin)', VHCP_Cfg::tinh_nang_bat( $MA ) === false );
t( '🔴 mã lạ → luôn BẬT với mọi người', VHCP_Cfg::tinh_nang_bat( 'khongCoThat' ) === true );
$b = VHCP_Don::get_bootstrap();
t( '🔴 gói khởi động của Kế toán: tinhNang.phanLoaiHaiBac = false', isset( $b['tinhNang'] ) && false === $b['tinhNang'][ $MA ], isset( $b['tinhNang'] ) ? $b['tinhNang'] : array_keys( $b ) );
vai( 'Admin', 'Anh Thắng' );
$b = VHCP_Don::get_bootstrap();
t( '🔴 gói khởi động của Admin: true', true === $b['tinhNang'][ $MA ] );
$c = VHCP_Cfg::get_config();
t( '🔴 gói Cấu hình chở tinhNangDs với trạng thái hiệu lực', isset( $c['tinhNangDs'][0] ) && $MA === $c['tinhNangDs'][0]['ma'] && 'admin' === $c['tinhNangDs'][0]['trangThai'] && '' !== $c['tinhNangDs'][0]['ten'], $c['tinhNangDs'] );

/* ═══ 2. Admin bật cho mọi người ═══════════════════════════════════════════════════════ */
$r = VHCP_Cfg::save_config( array( 'tinhNang' => array( $MA => 'BAT' ) ) );   // hoa → chuẩn thường
t( '🔴 Admin lưu "bat" được', ! empty( $r['success'] ), $r );
VHCP_Cfg::clear_cache();
teq( '   trạng thái = bat', 'bat', VHCP_Cfg::tinh_nang_trang_thai( $MA ) );
vai( 'Nhân viên', 'NV' );
t( '🔴 bật rồi → Nhân viên thấy đường mới', VHCP_Cfg::tinh_nang_bat( $MA ) === true );

/* ═══ 3. Tắt hẳn → không ai thấy, kể cả Admin ═════════════════════════════════════════ */
vai( 'Admin', 'Anh Thắng' );
VHCP_Cfg::save_config( array( 'tinhNang' => array( $MA => 'tat' ) ) ); VHCP_Cfg::clear_cache();
t( '🔴 tắt → Admin cũng đường cũ', VHCP_Cfg::tinh_nang_bat( $MA ) === false );
vai( 'Nhân viên', 'NV' );
t( '   tắt → Nhân viên đường cũ', VHCP_Cfg::tinh_nang_bat( $MA ) === false );

/* ═══ 4. Cửa chối ═════════════════════════════════════════════════════════════════════ */
$r = VHCP_Cfg::save_config( array( 'tinhNang' => array( $MA => 'bat' ) ) );
t( '🔴 KHÔNG phải Admin → chối, không đổi', empty( $r['success'] ) && 'tat' === VHCP_Cfg::tinh_nang_trang_thai( $MA ), $r );
vai( 'Admin', 'Anh Thắng' );
$r = VHCP_Cfg::save_config( array( 'tinhNang' => array( 'maLa' => 'bat' ) ) );
t( '🔴 mã không có trong bản này → chối', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'maLa' ), $r );
$r = VHCP_Cfg::save_config( array( 'tinhNang' => array( $MA => 'mo-mo' ) ) );
t( '🔴 trạng thái lạ → chối', empty( $r['success'] ), $r );
teq( '   sổ vẫn "tat"', 'tat', VHCP_Cfg::tinh_nang_trang_thai( $MA ) );
/* Dòng rác trong sổ (mã đã gỡ khỏi bản) không làm nổ, bị bỏ qua. */
VHCP_Cfg::write( VHCP_Cfg::TN, array( array( 'maDaGo', 'bat' ), array( $MA, 'admin' ), array( $MA, 'sai' ) ) ); VHCP_Cfg::clear_cache();
teq( '   sổ có mã đã gỡ + trạng thái sai → chỉ giữ dòng hợp lệ', array( $MA => 'admin' ), VHCP_Cfg::tinh_nang_luu() );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: cờ ba mức, mặc định chỉ Admin, bật mới áp mọi người, chối sai mã/vai.\n";
