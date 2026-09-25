<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƠN ĐÓNG DẤU KHỐI THEO NGƯỜI LẬP — BẮC–NAM DÙNG CHUNG MỘT BẢN.
 *
 * Anh Thắng 23/09/2026: *"Nếu bắc nam dùng chung thì sao. Và Nhân viên miền bắc thì thấy miền
 * bắc, Nhân viên miền nam thì thấy miền nam"* → chốt: dùng chung, khối theo NGƯỜI LẬP.
 *
 * 🔴 CÁI HỎNG NGUY: ĐƠN MẤT TÍCH. Đơn đóng dấu một khối mà tab của người lập không bày khối ấy
 *    thì họ lập xong không thấy đơn mình ở đâu. Nên (a) dấu phải theo người, (b) đường lui về
 *    khối bản cài phải giữ cho mọi site chưa khai cột Khối — không đơn nào đổi chỗ.
 *
 * Chạy: php tools/test/kiem-khoi-theo-nguoi-lap.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* Bảng Người dùng: cột 11 = Khối (tích tay, ngăn phẩy). */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	//     tên          pin  vai            cơ sở tkCó mãĐT bộ phận đơn vị xemĐV mãNV KHỐI
	array( 'NV Bắc',    '1', 'Nhân viên',   '',   '',  '',  '',     '',    '',   '',  'mb' ),
	array( 'NV Nam',    '2', 'Nhân viên',   '',   '',  '',  '',     '',    '',   '',  'mn' ),
	array( 'KT Hai Miền','3','Kế toán cá nhân','','',  '',  '',     '',    '',   '',  'mb, mn' ),
	array( 'Người Cũ',  '4', 'Nhân viên',   '',   '',  '',  '',     '',    '',   '',  '' ),
	array( 'Gõ Bậy',    '5', 'Nhân viên',   '',   '',  '',  '',     '',    '',   '',  'xyz' ),
), false );
VHCP_Cfg::clear_cache();
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin', '', '' );
$BAN = VHCP_DB::khoi();

/* ═══ 1. `khoi_cho_don()` ═════════════════════════════════════════════════════════════ */
teq( '🔴 người tích MỘT khối (mb) → đơn về mb', 'mb', VHCP_Don::khoi_cho_don( 'NV Bắc' ) );
teq( '🔴 người tích mn → mn', 'mn', VHCP_Don::khoi_cho_don( 'NV Nam' ) );
/* ⚠️ MÀN KHÔNG ÉP ĐƯỢC KHỐI KHÁC NGƯỜI LẬP — khối là của người, không của cái tab đang mở. */
teq( '🔴 người tích mb mà màn gửi mn → VẪN mb', 'mb', VHCP_Don::khoi_cho_don( 'NV Bắc', 'mn' ) );
teq( '🔴 tích HAI khối + màn đứng ở mn → mn', 'mn', VHCP_Don::khoi_cho_don( 'KT Hai Miền', 'mn' ) );
teq( '   tích hai khối + màn đứng ở mb → mb', 'mb', VHCP_Don::khoi_cho_don( 'KT Hai Miền', 'MB' ) );
teq( '🔴 tích hai khối + màn KHÔNG gửi → khối bản cài (không đoán)', $BAN, VHCP_Don::khoi_cho_don( 'KT Hai Miền', '' ) );
teq( '   tích hai khối + màn gửi khối họ KHÔNG tích (vp) → khối bản cài', $BAN, VHCP_Don::khoi_cho_don( 'KT Hai Miền', 'vp' ) );
/* 🔴 ĐƯỜNG LUI — site chưa khai cột Khối phải chạy y như hôm qua. */
teq( '🔴 người chưa tích gì → khối bản cài như cũ', $BAN, VHCP_Don::khoi_cho_don( 'Người Cũ' ) );
teq( '   chưa tích + màn gửi mb → vẫn khối bản cài (không nhận từ màn khi người chưa khai)', $BAN, VHCP_Don::khoi_cho_don( 'Người Cũ', 'mb' ) );
teq( '   mã lạ trong cột Khối bị bỏ → khối bản cài', $BAN, VHCP_Don::khoi_cho_don( 'Gõ Bậy' ) );
teq( '   tên không có trong bảng → khối bản cài', $BAN, VHCP_Don::khoi_cho_don( 'Ai Đó' ) );

/* ═══ 2. CHẠY THẬT `create_don` — dấu vào sổ ═════════════════════════════════════════ */
function _khoi_cua_don( $r ) { $d = VHCP_Don::don_row( $r['maDon'] ); return $d ? (string) $d['khoi'] : '(không có)'; }
teq( '🔴 lập đơn cho NV Bắc → sổ ghi khối mb', 'mb', _khoi_cua_don( VHCP_Don::create_don( 'T9/2026', 'NV Bắc', '' ) ) );
teq( '🔴 lập đơn cho NV Nam → mn', 'mn', _khoi_cua_don( VHCP_Don::create_don( 'T9/2026', 'NV Nam', '' ) ) );
teq( '🔴 KT hai miền đứng ở tab mn → đơn về mn', 'mn', _khoi_cua_don( VHCP_Don::create_don( 'T9/2026', 'KT Hai Miền', '', 'mn' ) ) );
teq( '🔴 người cũ → khối bản cài, y như hôm qua', $BAN, _khoi_cua_don( VHCP_Don::create_don( 'T9/2026', 'Người Cũ', '' ) ) );
/* Cửa API `createDon` → `tao_don_moi` phải truyền được tham số thứ tư xuống. */
teq( '🔴 `tao_don_moi()` truyền khối màn xuống', 'mb', _khoi_cua_don( VHCP_Don::tao_don_moi( 'T9/2026', 'KT Hai Miền', '', 'mb' ) ) );

/* ═══ 3. MÀN GỬI KHỐI ĐANG ĐỨNG ═══════════════════════════════════════════════════════ */
$HTML = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 `createDon` gửi kèm `KHOI_DANG`', false !== strpos( $HTML, '.createDon(ky,nv,ND_LUONG,KHOI_DANG);' ) );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: đơn đóng dấu khối theo người lập, chưa khai thì về khối bản cài như cũ.\n";
