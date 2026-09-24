<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CỘT BỘ PHẬN (KVC · MTĐ · VP) CỦA BẢNG CƠ SỞ — thay cột Tỉnh trên màn, để đầu mục "có cơ sở"
 * xổ đúng gian của bộ phận ấy. Anh Thắng 24/09/2026: *"Chỗ Tỉnh, Bỏ thay vào đó là Bộ Phận (MTD,
 * KVC, VP). Mục đích là khi chọn loại chi phí, chọn Bộ phận thì nó ra cơ sở của bộ phận đó"*.
 * 🔴 CHẠY THẬT save_config / cfg_static / get_bootstrap. Chạy: php tools/test/kiem-coso-bo-phan.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }
function cs( $ten ) { foreach ( VHCP_Cfg::get_config()['coso'] as $x ) { if ( $x['ten'] === $ten ) { return $x; } } return null; }
function bp( $ten ) { $m = VHCP_Cfg::cfg_static()['cosoBoPhan']; $k = mb_strtolower( $ten ); return isset( $m[ $k ] ) ? $m[ $k ] : null; }

/* ═══ 1. Lưu bộ phận, đọc lại, chuẩn hoá ═══════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array() ); VHCP_Cfg::clear_cache();
$r = VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'TÀU TÂN PHÚ',    'maDonVi' => 'TTP',  'phanLoaiLon' => 'TÀU MN',      'tenMisa' => 'Tau Tan Phu', 'donVi' => 'MN', 'tinh' => 'TP HCM', 'boPhan' => 'MTĐ' ),
	array( 'ten' => 'ADV GO! AN LẠC', 'maDonVi' => 'EVFZ', 'phanLoaiLon' => 'EVENT FZ MN', 'tenMisa' => 'ADV Go An Lac', 'donVi' => 'MN', 'boPhan' => 'kvc' ),
	array( 'ten' => 'VP HCM',         'maDonVi' => 'VP',   'phanLoaiLon' => '',            'tenMisa' => '', 'donVi' => 'MN', 'boPhan' => 'Văn phòng' ),
	array( 'ten' => 'GIAN CHƯA KHAI', 'maDonVi' => 'X',    'phanLoaiLon' => '',            'tenMisa' => '', 'donVi' => 'MN', 'boPhan' => '' ),
	array( 'ten' => 'GIAN GÕ LẠ',     'maDonVi' => 'Y',    'phanLoaiLon' => '',            'tenMisa' => '', 'donVi' => 'MN', 'boPhan' => 'bộ phận gì đó' ),
	/* Gian cũ: cột Khối còn là khối cũ KVC, chưa khai bộ phận → phải tự suy = kvc. */
	array( 'ten' => 'AEON TÂN PHÚ CŨ', 'maDonVi' => 'ATP', 'phanLoaiLon' => 'FZ MN',       'tenMisa' => '', 'donVi' => 'KVC' ),
) ) );
t( '🔴 lưu được', ! empty( $r['success'] ), $r );
VHCP_Cfg::clear_cache();
teq( '🔴 "MTĐ" (có dấu) → mã mtd', 'mtd', cs( 'TÀU TÂN PHÚ' )['boPhan'] );
teq( '🔴 "kvc" → kvc', 'kvc', cs( 'ADV GO! AN LẠC' )['boPhan'] );
teq( '🔴 "Văn phòng" → vp', 'vp', cs( 'VP HCM' )['boPhan'] );
teq( '🔴 rỗng → chưa khai', '', cs( 'GIAN CHƯA KHAI' )['boPhan'] );
teq( '🔴 giá trị lạ → không đoán, rỗng', '', cs( 'GIAN GÕ LẠ' )['boPhan'] );
teq( '   cột Tỉnh vẫn lưu được khi có gửi', 'TP HCM', cs( 'TÀU TÂN PHÚ' )['tinh'] );
/* Soi thẳng SỔ: lưu phải ghi MÃ (mtd), không ghi chữ người gõ — gói nhân bản / CSV đọc sổ, không đọc màn. */
$tho = array(); foreach ( VHCP_Cfg::read( VHCP_Cfg::COSO ) as $r0 ) { $tho[ (string) $r0[0] ] = isset( $r0[7] ) ? (string) $r0[7] : null; }
teq( '🔴 sổ ghi mã "mtd", không ghi "MTĐ"', 'mtd', $tho['TÀU TÂN PHÚ'] );
teq( '   sổ ghi "vp" cho "Văn phòng"', 'vp', $tho['VP HCM'] );
/* Và ĐỌC cũng chuẩn hoá: sổ cũ / gói nhập có thể mang chữ thô. */
$rows = VHCP_Cfg::read( VHCP_Cfg::COSO );
foreach ( $rows as $i => $r0 ) { if ( 'GIAN CHƯA KHAI' === (string) $r0[0] ) { $rows[ $i ][7] = 'POSH'; } }
VHCP_Cfg::write( VHCP_Cfg::COSO, $rows ); VHCP_Cfg::clear_cache();
teq( '🔴 sổ mang chữ thô "POSH" → đọc ra mtd', 'mtd', cs( 'GIAN CHƯA KHAI' )['boPhan'] );
foreach ( $rows as $i => $r0 ) { if ( 'GIAN CHƯA KHAI' === (string) $r0[0] ) { $rows[ $i ][7] = ''; } }
VHCP_Cfg::write( VHCP_Cfg::COSO, $rows ); VHCP_Cfg::clear_cache();

/* ═══ 2. Bản đồ cơ sở → bộ phận (khoá hạ chữ thường), suy từ khối cũ khi chưa khai ══════ */
teq( '🔴 cosoBoPhan: gian khai MTĐ → mtd', 'mtd', bp( 'TÀU TÂN PHÚ' ) );
teq( '🔴 cosoBoPhan: chưa khai nhưng cột Khối là khối cũ KVC → suy kvc (94 gian cũ không phải khai lại)', 'kvc', bp( 'AEON TÂN PHÚ CŨ' ) );
teq( '🔴 cosoBoPhan: chưa khai, khối là MIỀN (MN) → rỗng, không suy bừa', '', bp( 'GIAN CHƯA KHAI' ) );
$b = VHCP_Don::get_bootstrap();
t( '🔴 gói khởi động chở cosoBoPhan (form lọc ô Cơ sở theo đầu mục)', isset( $b['cosoBoPhan'] ) && 'mtd' === $b['cosoBoPhan']['tàu tân phú'] && 'kvc' === $b['cosoBoPhan']['aeon tân phú cũ'], isset( $b['cosoBoPhan'] ) ? $b['cosoBoPhan'] : array_keys( $b ) );

/* ═══ 3. Không gửi ô → giữ cũ; gửi rỗng có chủ ý → xoá ═══════════════════════════════ */
$r = VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'TÀU TÂN PHÚ',    'maDonVi' => 'TTP', 'phanLoaiLon' => 'TÀU MN', 'tenMisa' => 'Tau Tan Phu', 'donVi' => 'MN' ),   // không có khoá boPhan, không có tinh
	array( 'ten' => 'ADV GO! AN LẠC', 'maDonVi' => 'EVFZ', 'phanLoaiLon' => 'EVENT FZ MN', 'tenMisa' => 'ADV Go An Lac', 'donVi' => 'MN', 'boPhan' => '' ),
	array( 'ten' => 'VP HCM',         'maDonVi' => 'VP', 'phanLoaiLon' => '', 'tenMisa' => '', 'donVi' => 'MN', 'boPhan' => 'vp' ),
	array( 'ten' => 'GIAN CHƯA KHAI', 'maDonVi' => 'X', 'phanLoaiLon' => '', 'tenMisa' => '', 'donVi' => 'MN' ),
	array( 'ten' => 'GIAN GÕ LẠ',     'maDonVi' => 'Y', 'phanLoaiLon' => '', 'tenMisa' => '', 'donVi' => 'MN' ),
	array( 'ten' => 'AEON TÂN PHÚ CŨ', 'maDonVi' => 'ATP', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => '', 'donVi' => 'KVC' ),
) ) );
t( '   lưu lượt hai', ! empty( $r['success'] ), $r ); VHCP_Cfg::clear_cache();
teq( '🔴 màn cũ không gửi ô Bộ phận → GIỮ mtd', 'mtd', cs( 'TÀU TÂN PHÚ' )['boPhan'] );
teq( '🔴 và không gửi ô Tỉnh (màn mới thôi bày) → tỉnh đã khai vẫn còn', 'TP HCM', cs( 'TÀU TÂN PHÚ' )['tinh'] );
teq( '🔴 gửi rỗng có chủ ý → xoá bộ phận', '', cs( 'ADV GO! AN LẠC' )['boPhan'] );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: cột Bộ phận của cơ sở lưu/đọc/chuẩn hoá đúng, suy từ khối cũ, xuống gói khởi động; tỉnh không mất.\n";
