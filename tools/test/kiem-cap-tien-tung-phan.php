<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CẤP TẠM ỨNG LÀM NHIỀU LẦN CHO MỘT LỆNH.
 *
 * Anh Thắng 10/09/2026: *"nếu 1 lần mà đi tạm ứng nhiều lần thì nv có thể lịch chọn ngày đi tạm
 * ứng lần 1,2,3"*; rồi 17/09/2026 hỏi thẳng *"nếu xin nhiều lần và cấp nhiều lần thì sao"*.
 *
 * =============================================================================================
 * Phần HẸN LỊCH đã có từ lâu (`lich` trên lệnh). Phần GHI NHẬN TỪNG LẦN NHẬN thì chưa: cấp tiền
 * vốn là một cú lật duy nhất — `'ung' === $d['tt']` rồi chối *"đã cấp tiền rồi"*. Lệnh 48 triệu
 * mà kế toán mới đưa 20 triệu thì không có chỗ nào ghi, và câu "còn phải đưa bao nhiêu" chỉ trả
 * lời được bằng cách hỏi mồm.
 *
 * 🔴 KHÔNG THÊM TRẠNG THÁI MỚI. 'đang cấp' thêm vào `TT_DOT` sẽ chảy xuống trạng thái của TỪNG
 *    HẠNG MỤC, rồi vào `hm_du_tu_qt()`, vào bảng Duyệt, vào mọi phép so `'ung' === $h['tt']` —
 *    hàng chục nhánh chưa ai đi, và chúng hỏng im lặng. Phần dở dang nằm ở SỐ TIỀN, không nằm ở
 *    trạng thái.
 *
 * Chạy: php tools/test/kiem-cap-tien-tung-phan.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }

vai( 'Admin', 'KT' );
$ma = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian cấp nhiều lần', 'NV' )['maDA'];
/* Ba hàng để còn CHIA ĐƯỢC theo hàng — từ 1.197.0 mỗi đợt nhận tiền gán lấy mấy hàng cụ thể,
   và số tiền của đợt do máy chủ cộng từ chính mấy hàng ấy. Một hàng duy nhất thì không chia được. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thợ Phụ', 'duToan' => 10000000, 'thucTe' => 10000000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Vật tư',  'duToan' => 20000000, 'thucTe' => 20000000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Xe cẩu',  'duToan' => 18000000, 'thucTe' => 18000000 ) );
$d = VHCP_DuAn::get_du_an( $ma );
$R = array();
foreach ( $d['lines'] as $l ) { $R[ $l['noiDung'] ] = (int) $l['row']; }
$row = $R['Thợ Phụ'];

vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Thợ Phụ'], $R['Vật tư'], $R['Xe cẩu'] ),
	array( array( 'ngay' => '03/09/2026', 'rows' => array( $R['Thợ Phụ'] ) ),
	       array( 'ngay' => '10/09/2026', 'rows' => array( $R['Vật tư'] ) ) ), '' );
teq( 'lệnh 48tr gửi được', 48000000, (int) $x['dot']['soTien'] );
teq( '🔴 đợt 1 = tổng hàng gán vào nó (Thợ Phụ 10tr)', 10000000, (int) $x['dot']['lich'][0]['soTien'] );
teq( '   đợt 2 = Vật tư 20tr', 20000000, (int) $x['dot']['lich'][1]['soTien'] );
teq( '   và đợt 1 nhớ ĐÚNG hàng nào của nó', array( $R['Thợ Phụ'] ), $x['dot']['lich'][0]['rows'] );

/* ═══ 1. CHƯA DUYỆT THÌ CHƯA CẤP ═══════════════════════════════════════════════════════ */
vai( 'Kế toán cá nhân', 'KTCN' );
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 10000000 ) );
t( '🔴 lệnh chưa duyệt → chối cấp', empty( $r['success'] ), $r );
t( '   và nói rõ nó đang ở bước nào',
	isset( $r['error'] ) && false !== mb_strpos( (string) $r['error'], 'ĐÃ DUYỆT' ), $r );

vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );

/* ═══ 2. AI ĐƯỢC CẤP ═══════════════════════════════════════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 10000000 ) );
t( '🔴 nhân viên KHÔNG tự cấp tiền cho mình được', empty( $r['success'] ), $r );
vai( 'Quản lý', 'QL' );
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 10000000 ) );
t( '   quản lý cũng không — chỉ kế toán giữ két', empty( $r['success'] ), $r );

/* ═══ 3. SỐ TIỀN PHẢI CÓ NGHĨA ═════════════════════════════════════════════════════════ */
vai( 'Kế toán cá nhân', 'KTCN' );
foreach ( array( 0, -5000 ) as $xau ) {
	$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => $xau ) );
	t( '🔴 cấp ' . $xau . 'đ → chối (dòng 0đ chỉ làm sổ dài ra)', empty( $r['success'] ), $r );
}
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 60000000 ) );
t( '🔴 cấp QUÁ số của lệnh → chối (tiền ra nhiều hơn số đã duyệt)', empty( $r['success'] ), $r );
t( '   câu chối nói còn bao nhiêu chưa cấp',
	isset( $r['error'] ) && false !== mb_strpos( (string) $r['error'], '48.000.000' ), $r );
t( '   và chỉ đường đúng: xin lệnh mới, ở đó có người duyệt',
	isset( $r['error'] ) && false !== mb_strpos( (string) $r['error'], 'lệnh mới' ), $r );

/* ═══ 4. 🔴 CẤP LÀM BA LẦN ═════════════════════════════════════════════════════════════ */
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 10000000, 'ngay' => '03/09/2026', 'unc' => 'UNC-1' ) );
t( 'lần 1: đưa 10tr', ! empty( $r['success'] ), $r );
teq( '   đã cấp 10tr', 10000000, (int) $r['daCap'] );
teq( '   còn 38tr', 38000000, (int) $r['con'] );
t( '   và lệnh CHƯA xong', empty( $r['xong'] ), $r );
teq( '🔴 lệnh vẫn ở "duyet" — dở dang nằm ở SỐ TIỀN, không ở trạng thái',
	'duyet', VHCP_DuAn::dot_cua( $ma, 1 )['tt'] );
teq( '   hạng mục cũng chưa sang "ung"', 'duyet', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );

$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 20000000, 'ngay' => '10/09/2026', 'unc' => 'UNC-2' ) );
teq( 'lần 2: đã cấp 30tr', 30000000, (int) $r['daCap'] );
teq( '   còn 18tr', 18000000, (int) $r['con'] );

$dd = VHCP_DuAn::get_du_an( $ma );
teq( '🔴 "đã chi" đọc theo TIỀN THẬT đã đưa (30tr), không theo trạng thái lệnh',
	30000000, (int) $dd['daChiTU'] );
/* 🔴 "Đã xin" đếm theo ĐỢT: lịch khai 10tr + 20tr cho một lệnh 48tr, nên đã xin là 30tr và
   18tr còn lại là "dự kiến đợt tiếp theo" (1.196.0). */
teq( '   "đã xin" = tổng các đợt đã khai (10tr + 20tr)', 30000000, (int) $dd['daXinTU'] );
teq( '   phần chưa xếp đợt = dự kiến đợt tiếp theo', 18000000, (int) $dd['duKienDotSau'] );
teq( '   còn `daVaoLenh` vẫn là trọn lệnh 48tr', 48000000, (int) $dd['daVaoLenh'] );

/* ═══ 5. LẦN CUỐI ĐI ĐÚNG ĐƯỜNG CŨ ═════════════════════════════════════════════════════ */
$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 18000000, 'ngay' => '17/09/2026', 'unc' => 'UNC-3' ) );
t( '🔴 lần 3 trả nốt → lệnh XONG', ! empty( $r['xong'] ), $r );
teq( '   đã cấp đủ 48tr', 48000000, (int) $r['daCap'] );
teq( '   không còn nợ', 0, (int) $r['con'] );
teq( '🔴 lệnh sang "ung" như đường cũ', 'ung', VHCP_DuAn::dot_cua( $ma, 1 )['tt'] );
teq( '🔴 và HẠNG MỤC cũng sang "ung" — móc của đường cũ vẫn chạy',
	'ung', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );

$L = VHCP_DuAn::dot_cua( $ma, 1 );
teq( '🔴 sổ ghi đủ BA lần cấp', 3, count( $L['daCap'] ) );
teq( '   đánh số lần theo thứ tự', 3, (int) $L['daCap'][2]['lan'] );
teq( '   giữ uỷ nhiệm chi của TỪNG lần', 'UNC-2', (string) $L['daCap'][1]['unc'] );
teq( '   giữ ngày của từng lần', '17/09/2026', (string) $L['daCap'][2]['ngay'] );
t( '   ghi cả ai cấp', '' !== (string) $L['daCap'][0]['nguoi'], $L['daCap'][0] );

$r = VHCP_DuAn::cap_tien_phan( $ma, 1, array( 'soTien' => 1000 ) );
t( '🔴 cấp đủ rồi thì thôi', empty( $r['success'] ), $r );

/* ═══ 6. LỐI CẤP TRỌN MỘT LẦN CŨNG VÀO SỔ — không thì sổ thủng ở lối đang dùng nhiều nhất */
vai( 'Admin', 'KT' );
$ma2 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian cấp trọn', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma2, array( 'noiDung' => 'Vận chuyển', 'duToan' => 1600000, 'thucTe' => 1600000 ) );
$d2 = VHCP_DuAn::get_du_an( $ma2 );
$row2 = 0;
foreach ( $d2['lines'] as $l ) { if ( 'Vận chuyển' === $l['noiDung'] ) { $row2 = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma2, array( $row2 ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma2, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $ma2, 1, 'ung', array( 'unc' => 'UNC-TRON' ) );
$L2 = VHCP_DuAn::dot_cua( $ma2, 1 );
teq( '🔴 cấp trọn một lần cũng ghi MỘT dòng vào sổ', 1, count( $L2['daCap'] ) );
teq( '   đúng trọn số tiền của lệnh', 1600000, (int) $L2['daCap'][0]['soTien'] );
$dd2 = VHCP_DuAn::get_du_an( $ma2 );
teq( '   nên "đã chi" của lối cũ ra đúng con số cũ', 1600000, (int) $dd2['daChiTU'] );

/* ═══ 7. CẤP DỞ RỒI MỚI BẤM "CẤP TRỌN" — không đếm hai lần ═════════════════════════════ */
vai( 'Admin', 'KT' );
$ma3 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian cấp dở rồi trọn', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma3, array( 'noiDung' => 'Thợ', 'duToan' => 5000000, 'thucTe' => 5000000 ) );
$d3 = VHCP_DuAn::get_du_an( $ma3 );
$row3 = 0;
foreach ( $d3['lines'] as $l ) { if ( 'Thợ' === $l['noiDung'] ) { $row3 = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma3, array( $row3 ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma3, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::cap_tien_phan( $ma3, 1, array( 'soTien' => 2000000 ) );
VHCP_DuAn::dat_tt_dot( $ma3, 1, 'ung', array( 'unc' => 'UNC-NOT' ) );
$L3 = VHCP_DuAn::dot_cua( $ma3, 1 );
teq( '🔴 đưa dở 2tr rồi bấm cấp trọn → sổ có 2 dòng', 2, count( $L3['daCap'] ) );
teq( '   dòng thứ hai chỉ ghi phần CÒN LẠI (3tr), không ghi lại cả 5tr',
	3000000, (int) $L3['daCap'][1]['soTien'] );
teq( '🔴 tổng đã cấp đúng 5tr, không đếm hai lần', 5000000, (int) VHCP_DuAn::da_cap_tong( $L3 ) );

/* ═══ 7b. MÀN HÌNH — phần người dùng thật sự nhìn và bấm ══════════════════════════════ */
$HTML = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );

t( '🔴 form cấp tiền có ô SỐ TIỀN ĐƯA LẦN NÀY', false !== mb_strpos( $HTML, 'Số tiền đưa lần này' ) );
t( '🔴 ô ấy điền sẵn phần CÒN LẠI (đưa trọn là việc thường nhất, phải khỏi gõ)',
	false !== strpos( $HTML, "value=\"'+money(con)+'\"" ) );
t( '   có ô ngày đưa để sau còn đối chiếu', false !== strpos( $HTML, 'data-hmcapngay=' ) );
t( '   và vẫn đính được uỷ nhiệm chi của riêng lần ấy',
	false !== strpos( $HTML, "_hmOTep(k,maDA,'unc'" ) );

t( '🔴 gửi đi bằng cửa capTienPhanDuAn, không phải lối lật trạng thái cũ',
	false !== strpos( $HTML, '.capTienPhanDuAn(maDA, dot, {' ) );
/* Ô tiền người ta gõ "20.000.000" như mọi ô tiền khác; gửi nguyên chuỗi ấy lên là máy chủ đọc
   ra 20 — tức đưa hai mươi đồng. */
/* ⚠️ KHOÁ CẢ DÒNG, không khoá mỗi mẩu `replace(...)`: mẩu ấy còn một chỗ nữa trong app.html,
   nên phép cũ vẫn xanh kể cả khi dòng này đã bị đục thủng. Đã đục thử và thấy đúng thế. */
t( '🔴 bỏ dấu chấm nghìn trước khi gửi',
	false !== strpos( $HTML, "var so=o?Number(String(o.value||'').replace(/[^0-9]/g,'')):0;" ) );
t( '   chưa gõ số thì chối tại chỗ, không gửi đi',
	false !== mb_strpos( $HTML, 'Nhập số tiền đưa lần này' ) );

t( '🔴 bảng lệnh hiện "đã cấp / còn" dưới số tiền', false !== strpos( $HTML, "+_capChu(d)+" ) );
/* Chỉ hiện khi ĐANG DỞ: chưa đưa gì hay đã đưa đủ thì con số tổng ở trên đã nói hết, thêm một
   dòng nữa chỉ làm bảng ồn. */
t( '   và chỉ hiện khi đang dở dang',
	false !== strpos( $HTML, 'if(!da || da>=(Number(x&&x.soTien)||0)) return' ) );
t( '   câu báo nói rõ còn bao nhiêu',
	false !== mb_strpos( $HTML, "'Đã cấp '+money(so)+'đ — còn '+money(r.con)+'đ'" ) );

/* ═══ 8. CỬA API ═══════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( '🔴 capTienPhanDuAn đã khai vào cửa API',
	false !== strpos( $src, "'capTienPhanDuAn'" )
	&& false !== strpos( $src, "array( 'VHCP_DuAn', 'cap_tien_phan' )" ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: cấp tiền làm nhiều lần, sổ ghi đủ từng lần, không đếm hai lần.\n";
