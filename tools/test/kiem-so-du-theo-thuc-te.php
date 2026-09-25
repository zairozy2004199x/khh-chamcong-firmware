<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SỐ DƯ CỦA LỆNH TẠM ỨNG SO VỚI THỰC TẾ, KHÔNG SO VỚI SỐ XIN.
 *
 * Anh Thắng 24/09/2026: *"Số dư là số còn lại nếu có chênh lệch thực tế thì lấy cột thực tế chứ
 * không lấy tạm ứng nữa"*. Ảnh: lệnh xin 53.810.000đ, kế toán đưa 30tr + 40tr = 70tr, bảng báo
 * "dư 16.190.000đ" — trong khi 11 hạng mục đã nhập thực tế 54.210.000đ, phần NV phải hoàn là
 * 15.790.000đ.
 *
 * 🔴 CHẠY THẬT: dựng dự án, xin lệnh (thực tế còn 0 → số lệnh = dự toán), duyệt, NHẬP THỰC TẾ
 *    SAU, rồi soi `tienNay` ở trang dự án + màn Duyệt, và `du` máy chủ báo về sau khi cấp.
 *
 * Chạy: php tools/test/kiem-so-du-theo-thuc-te.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }
function lenh_da( $ma ) { $DA = VHCP_DuAn::get_du_an( $ma ); return $DA['lenh'][0]; }
function lenh_duyet( $ma ) {
	foreach ( VHCP_DuAn::list_lenh_da( 'tu' )['items'] as $x ) { if ( (string) $x['maDA'] === (string) $ma && 1 === (int) $x['dot'] ) { return $x; } }
	return null;
}

/* Dự án hai hạng mục, CHỈ có dự toán 20tr + 30tr (thực tế chưa nhập) → lệnh 50tr. */
vai( 'Admin', 'KT' );
$ma = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian dư theo thực tế', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Hạng A', 'duToan' => 20000000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Hạng B', 'duToan' => 30000000 ) );
$R = array();
foreach ( VHCP_DuAn::get_du_an( $ma )['lines'] as $l ) { $R[ $l['noiDung'] ] = (int) $l['row']; }
$ra = $R['Hạng A']; $rb = $R['Hạng B'];
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $ra, $rb ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );

/* ═══ 1. Chưa có thực tế → tienNay = số lệnh (bảng đọc như cũ) ═════════════════════════ */
$L = lenh_da( $ma );
teq( '⚠️ lệnh 50tr dựng đúng', 50000000, (int) $L['soTien'] );
teq( '🔴 chưa nhập thực tế → `tienNay` = số lệnh 50tr (lùi về dự toán)', 50000000, (int) $L['tienNay'] );

/* ═══ 2. 🔴 Nhập thực tế SAU khi xin → tienNay đổi, soTien không đổi ═══════════════════ */
vai( 'Nhân viên', 'NV' );
$u1 = VHCP_DuAn::update_line( $ma, $ra, array( 'noiDung' => 'Hạng A', 'duToan' => 20000000, 'thucTe' => 22000000 ) );
$u2 = VHCP_DuAn::update_line( $ma, $rb, array( 'noiDung' => 'Hạng B', 'duToan' => 30000000, 'thucTe' => 32210000 ) );
t( '   sửa được thực tế hai hạng mục khi lệnh đang chờ cấp', ! empty( $u1['success'] ) && ! empty( $u2['success'] ), array( $u1, $u2 ) );
$L = lenh_da( $ma );
teq( '🔴 trang dự án: `tienNay` = 22tr + 32,21tr = 54.210.000đ theo thực tế', 54210000, (int) $L['tienNay'] );
teq( '🔴 `soTien` của lệnh VẪN 50tr — mốc đã xin/duyệt không đổi theo', 50000000, (int) $L['soTien'] );
$D = lenh_duyet( $ma );
t( '⚠️ màn Duyệt thấy lệnh', null !== $D, $D );
teq( '🔴 màn Duyệt cũng mang `tienNay` 54.210.000đ — hai màn không lệch', 54210000, (int) ( $D ? $D['tienNay'] : 0 ) );
teq( '   `tien_lenh_nay` gọi thẳng cũng ra số ấy', 54210000, (int) VHCP_DuAn::tien_lenh_nay( $ma, VHCP_DuAn::dot_cua( $ma, 1 ) ) );

/* ═══ 3. 🔴 Cấp 70tr → toast báo dư so với THỰC TẾ (15,79tr), không phải so với lệnh (20tr) ═ */
vai( 'Kế toán cá nhân', 'KTCN' );
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 70000000 ) );
t( '   đưa 70tr cho lệnh 50tr → nhận, đủ', ! empty( $r['success'] ) && ! empty( $r['xong'] ), $r );
teq( '🔴 `du` báo về = 70tr − 54,21tr = 15.790.000đ (KHÔNG phải 70 − 50 = 20tr)', 15790000, (int) $r['du'] );
$L = VHCP_DuAn::dot_cua( $ma, 1 );
teq( '   dòng sổ cấp vẫn ghi phần lượt này vượt số lệnh (20tr) — chuyện của lượt cấp, không đổi', 20000000, (int) $L['daCap'][0]['du'] );
teq( '   tổng đã cấp là 70tr thật', 70000000, (int) VHCP_DuAn::da_cap_tong( $L ) );

/* ═══ 4. Thực tế VƯỢT tiền đã đưa → du = 0 (màn nói "thiếu", không nói dư) ═════════════ */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::update_line( $ma, $rb, array( 'noiDung' => 'Hạng B', 'duToan' => 30000000, 'thucTe' => 60000000 ) );
$L = lenh_da( $ma );
teq( '   thực tế nay 22tr + 60tr = 82tr', 82000000, (int) $L['tienNay'] );
t( '   (đã đưa 70tr < 82tr → màn phải nói thiếu 12tr; xem bài -man.js)', 70000000 < (int) $L['tienNay'] );
VHCP_DuAn::delete( $ma );

/* (Không có ca "mục con": chi phí dự án nay chỉ có hạng mục lớn — `add_line` chối `capCha`. Nếu
   sổ cũ còn mục con, `tien_hm_du_kien` đã cộng theo con — bài kiem-bo-muc-con-du-an.php canh nó.) */

/* ═══ 4b. 🔴 Cấp khi thực tế ĐÃ vượt → `du` = 0, không âm ═════════════════════════════════ */
function _da_50( $ten ) {
	vai( 'Admin', 'KT' );
	$m = VHCP_DuAn::create_du_an( 'Setup lắp đặt', $ten, 'NV' )['maDA'];
	VHCP_DuAn::add_line( $m, array( 'noiDung' => 'Hạng A', 'duToan' => 20000000 ) );
	VHCP_DuAn::add_line( $m, array( 'noiDung' => 'Hạng B', 'duToan' => 30000000 ) );
	$R = array(); foreach ( VHCP_DuAn::get_du_an( $m )['lines'] as $l ) { $R[ $l['noiDung'] ] = (int) $l['row']; }
	vai( 'Nhân viên', 'NV' );
	VHCP_DuAn::xin_tam_ung_dot( $m, array( $R['Hạng A'], $R['Hạng B'] ), array(), '' );
	vai( 'Quản lý', 'QL' );
	VHCP_DuAn::dat_tt_dot( $m, 1, 'duyet' );
	return array( $m, $R['Hạng A'], $R['Hạng B'] );
}
list( $m3, $a3, $b3 ) = _da_50( 'Gian thực tế vượt' );
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::update_line( $m3, $a3, array( 'noiDung' => 'Hạng A', 'duToan' => 20000000, 'thucTe' => 60000000 ) );
VHCP_DuAn::update_line( $m3, $b3, array( 'noiDung' => 'Hạng B', 'duToan' => 30000000, 'thucTe' => 30000000 ) );
vai( 'Kế toán cá nhân', 'KTCN' );
$r = VHCP_DuAn::cap_tien_phan( $m3, 1, array( 'soTien' => 70000000 ) );
t( '   đưa 70tr cho lệnh 50tr (thực tế đã 90tr) → nhận, đủ theo lệnh', ! empty( $r['success'] ) && ! empty( $r['xong'] ), $r );
teq( '🔴 `du` = 0 (70 − 90 âm thì KHÔNG báo dư âm — màn nói "thiếu" theo tienNay)', 0, (int) $r['du'] );
teq( '   `tienNay` 90tr lên màn để nói thiếu 20tr', 90000000, (int) lenh_da( $m3 )['tienNay'] );
VHCP_DuAn::delete( $m3 );

/* ═══ 4c. Hạng mục bị gõ về 0 cả dự toán lẫn thực tế → tienNay 0 → `du` lùi về so số lệnh ══ */
list( $m4, $a4, $b4 ) = _da_50( 'Gian gõ về 0' );
vai( 'Nhân viên', 'NV' );
$z1 = VHCP_DuAn::update_line( $m4, $a4, array( 'noiDung' => 'Hạng A', 'duToan' => 0, 'thucTe' => 0 ) );
$z2 = VHCP_DuAn::update_line( $m4, $b4, array( 'noiDung' => 'Hạng B', 'duToan' => 0, 'thucTe' => 0 ) );
if ( ! empty( $z1['success'] ) && ! empty( $z2['success'] ) ) {
	teq( '   hai hàng về 0 → tienNay = 0', 0, (int) lenh_da( $m4 )['tienNay'] );
	vai( 'Kế toán cá nhân', 'KTCN' );
	$r = VHCP_DuAn::cap_tien_phan( $m4, 1, array( 'soTien' => 70000000 ) );
	teq( '🔴 tienNay = 0 là "không biết" → `du` so với số lệnh: 70 − 50 = 20tr (không phải 70tr)', 20000000, (int) $r['du'] );
} else {
	t( '   máy chủ không cho gõ hàng về 0 (xoá trá hình) — ca tienNay = 0 không dựng được từ màn; lưới ở `cap_tien_phan` chỉ phòng sổ cũ', empty( $z1['success'] ), $z1 );
}
VHCP_DuAn::delete( $m4 );

/* ═══ 5. Lệnh không có hạng mục (sổ hỏng) → 0, màn lùi về soTien ═════════════════════════ */
teq( '   lệnh rỗng hàng → 0 (màn hiểu là "không biết")', 0, (int) VHCP_DuAn::tien_lenh_nay( 'DA-khong-co', array( 'rows' => array() ) ) );

if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: lệnh mang `tienNay` theo thực tế ở cả hai màn; toast dư so với thực tế; số lệnh không đổi.\n";
