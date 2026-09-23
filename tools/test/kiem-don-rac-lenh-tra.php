<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ADMIN DỌN RÁC — XOÁ HẲN LỆNH TẠM ỨNG ĐÃ BỊ TRẢ LẠI.
 * Anh Thắng 23/09/2026: *"Cho admin có quyền dọn rác"* — bảng lệnh của dự án đầy dòng "— đã trả".
 *
 * 🔴 HAI CHỐT KHÔNG ĐƯỢC NỚI: chỉ Admin, chỉ lệnh 'tra'. Xoá lệnh đang chạy là mất dấu một khoản
 *    tiền thật; nhân viên tự xoá lệnh bị trả là xoá luôn lý do người duyệt ghi.
 * 🔴 HẠNG MỤC KHÔNG ĐƯỢC MẤT CHỦ: sau khi dọn, không dòng nào còn trỏ vào lệnh đã xoá.
 * Chạy: php tools/test/kiem-don-rac-lenh-tra.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }

vai( 'Admin', 'KT' );
$ma = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian dọn rác', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thợ Phụ', 'duToan' => 10000000, 'thucTe' => 10000000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Vật tư',  'duToan' => 20000000, 'thucTe' => 20000000 ) );
$R = array(); foreach ( VHCP_DuAn::get_du_an( $ma )['lines'] as $l ) { $R[ $l['noiDung'] ] = (int) $l['row']; }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Thợ Phụ'], $R['Vật tư'] ), array(), '' );   // lệnh 1

/* ═══ 1. CHỈ LỆNH ĐÃ BỊ TRẢ ════════════════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
$r = VHCP_DuAn::xoa_dot_tra( $ma, 1 );
t( '🔴 lệnh đang XIN → không dọn được', empty( $r['success'] ), $r );
t( '   và câu chối chỉ đường: Trả trước rồi dọn', isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'Trả' ), $r );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
vai( 'Admin', 'KT' );
t( '🔴 lệnh ĐÃ DUYỆT → cũng không', empty( VHCP_DuAn::xoa_dot_tra( $ma, 1 )['success'] ) );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 30000000 ) );
vai( 'Admin', 'KT' );
t( '🔴 lệnh ĐÃ CẤP → cũng không (tiền đã ra khỏi két)', empty( VHCP_DuAn::xoa_dot_tra( $ma, 1 )['success'] ) );

/* Thu hồi (Admin) → lệnh về 'tra'. */
$r = VHCP_DuAn::dat_tt_dot( $ma, 1, 'tra', array( 'lyDo' => 'Cấp nhầm' ) );
t( '⚠️ thu hồi được → lệnh về tra', ! empty( $r['success'] ) && 'tra' === VHCP_DuAn::dot_cua( $ma, 1 )['tt'], $r );
teq( '⚠️ lúc trả, hạng mục đã được gỡ khỏi lệnh (dot = 0)', 0, (int) VHCP_DuAn::hm_cua( $ma, $R['Thợ Phụ'] )['dot'] );

/* ═══ 2. CHỈ ADMIN ══════════════════════════════════════════════════════════════════════ */
foreach ( array( 'Nhân viên', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ) as $v ) {
	vai( $v, 'X' );
	t( "🔴 $v KHÔNG dọn được lệnh bị trả", empty( VHCP_DuAn::xoa_dot_tra( $ma, 1 )['success'] ) );
}
t( '   lệnh vẫn còn sau mấy lượt bị chối', null !== VHCP_DuAn::dot_cua( $ma, 1 ) );

/* ═══ 3. ADMIN DỌN → MẤT KHỎI BẢNG, HẠNG MỤC CÒN NGUYÊN ════════════════════════════════ */
vai( 'Admin', 'KT' );
$r = VHCP_DuAn::xoa_dot_tra( $ma, 1 );
t( '🔴 Admin dọn lệnh bị trả → xong', ! empty( $r['success'] ), $r );
teq( '   báo đúng lệnh đã xoá', 1, (int) $r['daXoa'] );
t( '🔴 lệnh KHÔNG còn trong sổ đợt', null === VHCP_DuAn::dot_cua( $ma, 1 ) );
teq( '🔴 danh sách lệnh của dự án rỗng', 0, count( VHCP_DuAn::ds_dot( $ma ) ) );
teq( '🔴 hạng mục vẫn còn đủ hai dòng', 2, count( VHCP_DuAn::get_du_an( $ma )['lines'] ) );
teq( '   và không dòng nào trỏ vào lệnh đã xoá', 0, (int) VHCP_DuAn::hm_cua( $ma, $R['Vật tư'] )['dot'] );
t( '   dọn lại lần hai → nói không tìm thấy, không nổ', empty( VHCP_DuAn::xoa_dot_tra( $ma, 1 )['success'] ) );
/* 🔴 DỌN MỘT LỆNH, KHÔNG PHẢI CẢ SỔ. Dựng hai lệnh (một bị trả, một đang xin), dọn cái bị trả
   → cái đang xin PHẢI còn. Phá thử 23/09/2026 lọt đúng chỗ này khi bài chỉ có một lệnh. */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Thợ Phụ'] ), array(), '' );   // lệnh mới (số 2)
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Vật tư'] ), array(), '' );    // lệnh mới (số 3)
$ds = VHCP_DuAn::ds_dot( $ma ); $so = array_map( function ( $d ) { return (int) $d['dot']; }, $ds );
teq( '⚠️ có đúng hai lệnh mới', 2, count( $so ) );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, $so[0], 'tra', array( 'lyDo' => 'Sai hạng mục' ) );
vai( 'Admin', 'KT' );
$r = VHCP_DuAn::xoa_dot_tra( $ma, $so[0] );
t( '🔴 dọn lệnh bị trả (trong hai lệnh) → xong', ! empty( $r['success'] ), $r );
t( '🔴 lệnh ĐANG XIN kia VẪN CÒN — không xoá cả sổ', null !== VHCP_DuAn::dot_cua( $ma, $so[1] ) && 'xin' === VHCP_DuAn::dot_cua( $ma, $so[1] )['tt'] );
teq( '   sổ còn đúng một lệnh', 1, count( VHCP_DuAn::ds_dot( $ma ) ) );
teq( '   `conLai` báo đúng', 1, (int) $r['conLai'] );
/* Dọn nốt: trả lệnh còn lại rồi xoá, để mục dưới xin lại từ bảng sạch. */
vai( 'Quản lý', 'QL' ); VHCP_DuAn::dat_tt_dot( $ma, $so[1], 'tra', array( 'lyDo' => 'x' ) );
vai( 'Admin', 'KT' ); VHCP_DuAn::xoa_dot_tra( $ma, $so[1] );

/* Xin lại được sau khi dọn — bảng sạch, lệnh mới là lệnh 1 hiện ra. */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Thợ Phụ'] ), array(), '' );
t( '🔴 sau khi dọn vẫn xin lại được', ! empty( $x['success'] ), $x );
teq( '   và lệnh mới đứng số hiện 1 (không kế thừa số của rác)', 1, (int) VHCP_DuAn::ds_dot( $ma )[0]['soHien'] );
VHCP_DuAn::delete( $ma );

/* ═══ 4. CỬA + MÀN ═════════════════════════════════════════════════════════════════════ */
$API = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( '🔴 đăng ký cửa `xoaLenhTraDuAn`', false !== strpos( $API, "'xoaLenhTraDuAn'        => array( 'VHCP_DuAn', 'xoa_dot_tra' )" ) );
$ND = substr( $API, strpos( $API, '$nguoi_duyet = array(' ) ); $ND = substr( $ND, 0, strpos( $ND, ');' ) );
t( '🔴 và nằm trong danh sách người duyệt (không phải cửa của nhân viên)', false !== strpos( $ND, "'xoaLenhTraDuAn'" ) );
$HTML = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );
$NUT = substr( $HTML, strpos( $HTML, '  function lenhNutChung(' ) ); $NUT = substr( $NUT, 0, strpos( $NUT, "\n  }" ) );
t( '🔴 nút 🗑 chỉ mọc khi lệnh ở tra VÀ người xem là Admin',
	1 === preg_match( "/if\(tt==='tra' && String\(\(CURUSER&&CURUSER\.role\)\|\|''\)==='Admin'\)\s*\n\s*out\+=.*lenhXoaRac/u", $NUT ), $NUT );
$XR = substr( $HTML, strpos( $HTML, '  function lenhXoaRac(' ) ); $XR = substr( $XR, 0, strpos( $XR, "\n  }" ) );
t( '🔴 bấm 🗑 có hỏi xác nhận', false !== strpos( $XR, 'confirm(' ) );
t( '   và gọi đúng cửa', false !== strpos( $XR, '.xoaLenhTraDuAn(maDA, dot)' ) );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: Admin dọn được lệnh bị trả, không ai khác, không đụng hạng mục.\n";
