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

/* ═══ 7c. 🔴 KHÔNG CHỌN NGÀY → LẤY ĐÚNG LÚC BẤM, KÈM GIỜ ══════════════════════════════
 * Anh Thắng 18/09/2026: *"Nếu kế toán bấm cấp tiền mà không chọn ngày thì tự hiểu là lấy ngày
 * bấm cấp làm ngày cấp tiền (kèm giờ luôn cho đầy đủ)"*, kèm ảnh sổ "Đã cấp" ba dòng mà hai
 * dòng trống ngày, và ảnh dòng mốc `ung: 18/09/2026 09:29` — *"giống như này"*.
 *
 * =========================================================================================
 * 🔴 BỎ TRỐNG LÀ CA THƯỜNG, KHÔNG PHẢI CA HIẾM. Ô ngày là tuỳ chọn, và đường cấp TRỌN MỘT LẦN
 *    (`dat_tt_dot('ung')`) còn chẳng có ô nào để chọn — tức lối đang dùng nhiều nhất luôn ghi
 *    sổ không ngày. Đối chiếu ngân hàng thì mốc thời gian là thứ đầu tiên người ta dò.
 * 🔴 GHI VÀO SỔ, KHÔNG CHỈ VÁ LÚC HIỂN THỊ. Vá ở màn thì ai đọc sổ qua đường khác (xuất MISA,
 *    tra lịch sử) vẫn thấy trống, và hai nơi nói hai chuyện.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
/* Khuôn `dd/mm/yyyy HH:MM` — chính khuôn của dòng mốc trong ảnh anh gửi. */
$KHUON_LUC = '#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#';

/* Lối cấp TỪNG PHẦN, bỏ trống ô ngày. */
vai( 'Admin', 'KT' );
$maN = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian không chọn ngày', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maN, array( 'noiDung' => 'Thợ', 'duToan' => 5000000, 'thucTe' => 5000000 ) );
$rowN = 0;
foreach ( VHCP_DuAn::get_du_an( $maN )['lines'] as $l ) { if ( 'Thợ' === $l['noiDung'] ) { $rowN = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maN, array( $rowN ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maN, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::cap_tien_phan( $maN, 1, array( 'soTien' => 2000000 ) );
$LN = VHCP_DuAn::dot_cua( $maN, 1 );
$ng = (string) $LN['daCap'][0]['ngay'];
t( '🔴 cấp từng phần không chọn ngày → sổ KHÔNG còn để trống', '' !== $ng, $LN['daCap'][0] );
t( '🔴 và ngày ấy KÈM GIỜ, đúng khuôn dd/mm/yyyy HH:MM như dòng mốc', 1 === preg_match( $KHUON_LUC, $ng ), $ng );
teq( '   đúng bằng lúc bấm (`luc`), không phải một mốc thứ hai lệch đi',
	(string) $LN['daCap'][0]['luc'], $ng );

/* Lối cấp TRỌN MỘT LẦN — lối không có ô ngày nào cả. */
VHCP_DuAn::dat_tt_dot( $maN, 1, 'ung', array( 'unc' => 'UNC-N' ) );
$LN2 = VHCP_DuAn::dot_cua( $maN, 1 );
teq( '   cấp nốt phần còn lại → sổ có hai dòng', 2, count( $LN2['daCap'] ) );
$ng2 = (string) $LN2['daCap'][1]['ngay'];
t( '🔴 lối cấp trọn (không có ô ngày nào) cũng ghi mốc, không để trống',
	1 === preg_match( $KHUON_LUC, $ng2 ), $LN2['daCap'][1] );

/* 🔴 CHỌN NGÀY THÌ GIỮ NGUYÊN NGÀY NGƯỜI TA CHỌN. Đè lên bằng lúc bấm là xoá mất chuyện
   "tiền ra khỏi két hôm 03/09, mãi 17/09 kế toán mới vào sổ" — đúng thứ đem đi đối chiếu. */
teq( '🔴 có chọn ngày thì giữ NGUYÊN, không bị lúc bấm đè lên',
	'17/09/2026', (string) VHCP_DuAn::dot_cua( $ma, 1 )['daCap'][2]['ngay'] );
VHCP_DuAn::delete( $maN );

/* ═══ 7d. 🔴 XIN LẦN 1 THÌ CẤP LẦN 1 ══════════════════════════════════════════════════
 * Anh Thắng 18/09/2026, nhìn khối "Đã cấp" không nói gì về lần: *"Phía dưới phải có — Xin lần 1
 * thì cấp lần 1 chứ.."*.
 *
 * =========================================================================================
 * Nhân viên đã xếp từng hạng mục vào lần 1 / lần 2 lúc xin. Nếu lúc đưa tiền sổ không ghi đang
 * đưa cho LẦN NÀO thì cả cái xếp ấy thành một con số chết: màn chỉ còn cách ghép hai danh sách
 * theo THỨ TỰ, mà ghép thứ tự là BỊA — kế toán đưa 15tr trong khi lần 1 hẹn 10tr, hay gộp hai
 * lần làm một, thì "lần 1 đã nhận đủ" là câu không ai kiểm được. Nay kế toán KHAI, nên nó thật.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
vai( 'Admin', 'KT' );
$maL = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian cấp theo lần', 'NV' )['maDA'];
foreach ( array( array( 'Thợ Phụ', 10000000 ), array( 'Vật tư', 20000000 ) ) as $c ) {
	VHCP_DuAn::add_line( $maL, array( 'noiDung' => $c[0], 'thucTe' => $c[1] ) );
}
$RL = array();
foreach ( VHCP_DuAn::get_du_an( $maL )['lines'] as $l ) { $RL[ $l['noiDung'] ] = (int) $l['row']; }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maL, array( $RL['Thợ Phụ'], $RL['Vật tư'] ), array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RL['Thợ Phụ'] ) ),
	array( 'ngay' => '10/09/2026', 'rows' => array( $RL['Vật tư'] ) ),
), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maL, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );

/* ── Chối mấy ca làm sổ nói dối ─────────────────────────────────────────────────────────── */
$r = VHCP_DuAn::cap_tien_phan( $maL, 1, array( 'soTien' => 20000000, 'choLan' => 1 ) );
t( '🔴 lần 1 hẹn 10tr mà gắn 20tr vào nó → CHỐI (không thì lần 2 vĩnh viễn trông như chưa nhận, '
	. 'trong khi tiền đã ra)', empty( $r['success'] ), $r );
t( '   và nói rõ lần ấy còn lại bao nhiêu, để sửa ngay tại chỗ',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], '10.000.000đ' ), $r );
$r = VHCP_DuAn::cap_tien_phan( $maL, 1, array( 'soTien' => 1000, 'choLan' => 9 ) );
t( '🔴 gắn vào một lần KHÔNG có trong lịch → chối', empty( $r['success'] ), $r );
/* ⚠️ CANH CÂU CHỐI, không chỉ canh "bị chối". Lần 9 không có thật thì phần còn lại của nó là 0,
   nên chốt TRÀN ở dưới cũng chối ca này — gỡ hẳn nhánh "không có lần ấy" đi mà chỉ canh
   `empty($r['success'])` thì phép vẫn xanh. Khác nhau ở chỗ kế toán đọc được gì: "lệnh này
   không có lần nhận tiền số 9" thì biết mình chọn nhầm; "lần 9 chỉ còn 0đ chưa nhận" thì ngồi
   tìm xem lần 9 là lần nào. */
t( '   và nói THẲNG là lệnh không có lần ấy, chứ không nói "lần 9 chỉ còn 0đ"',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'không có lần nhận tiền số 9' ), $r );

/* ── Đường đi đúng ──────────────────────────────────────────────────────────────────────── */
$r = VHCP_DuAn::cap_tien_phan( $maL, 1, array( 'soTien' => 10000000, 'choLan' => 1, 'unc' => 'UNC-L1' ) );
t( 'cấp cho lần 1 đúng phần của nó → xong', ! empty( $r['success'] ), $r );
$LL = VHCP_DuAn::dot_cua( $maL, 1 );
teq( '🔴 sổ ghi rõ lượt này trả cho LẦN 1', 1, (int) $LL['daCap'][0]['choLan'] );
teq( '🔴 và cộng đúng vào lần 1', array( 1 => 10000000 ), VHCP_DuAn::da_cap_theo_lan( $LL ) );
t( '   lần 2 chưa nhận gì — KHÔNG ăn ké lượt của lần 1',
	! isset( VHCP_DuAn::da_cap_theo_lan( $LL )[2] ), VHCP_DuAn::da_cap_theo_lan( $LL ) );

/* Trả dở cho lần 2 — lần ấy còn thiếu, và con số còn thiếu phải đúng. */
VHCP_DuAn::cap_tien_phan( $maL, 1, array( 'soTien' => 8000000, 'choLan' => 2, 'unc' => 'UNC-L2a' ) );
$LL = VHCP_DuAn::dot_cua( $maL, 1 );
teq( '   trả dở cho lần 2 thì lần 2 cộng dồn', 8000000, VHCP_DuAn::da_cap_theo_lan( $LL )[2] );
/* ⚠️ SỐ THỬ PHẢI LỌT QUA CHỐT TRÀN CỦA CẢ LỆNH THÌ MỚI SOI ĐƯỢC CHỐT TRÀN CỦA LẦN.
   Lệnh 30tr, đã đưa 18tr → còn 12tr ở mức LỆNH, còn 12tr ở mức LẦN 2. Thử 13tr thì chốt lệnh
   chối trước, và một bản bỏ quên phép trừ "đã nhận" ở mức lần vẫn xanh. Nên thử 11tr sau khi
   đã nới mức lệnh ra: dưới đây trả nốt lần 1 nên mức lệnh còn 12tr, mà lần 2 cũng còn 12tr —
   vẫn dính nhau. Cách tách sạch: THÊM một lệnh khác chỉ có một lần. Ở đây dùng chính lần 2:
   trả nốt 12tr rồi thử gắn thêm — mức lệnh lúc ấy đã hết nên không tách được.
   → Tách bằng một dự án riêng ở khối 7e bên dưới. */
$r = VHCP_DuAn::cap_tien_phan( $maL, 1, array( 'soTien' => 13000000, 'choLan' => 2 ) );
t( '🔴 gắn tiếp quá PHẦN CÒN LẠI của lần 2 (12tr) → chối', empty( $r['success'] ), $r );
VHCP_DuAn::cap_tien_phan( $maL, 1, array( 'soTien' => 12000000, 'choLan' => 2, 'unc' => 'UNC-L2b' ) );
$LL = VHCP_DuAn::dot_cua( $maL, 1 );
teq( '   trả nốt lần 2 → đủ cả hai lần', array( 1 => 10000000, 2 => 20000000 ),
	VHCP_DuAn::da_cap_theo_lan( $LL ) );
teq( '🔴 và lệnh sang "ung" — đủ tiền là đủ, không cần bấm thêm', 'ung', $LL['tt'] );

/* 🔴 CHỐT TRÀN CỦA **LẦN** PHẢI ĐỨNG ĐỘC LẬP VỚI CHỐT TRÀN CỦA **LỆNH**.
   Lệnh còn dư nhiều mà lần thì hết — đúng cảnh kế toán chọn nhầm lần rồi bấm. Nếu hai chốt dính
   nhau thì một bản quên trừ "đã nhận của lần ấy" vẫn xanh, và tiền của lần 2 bị ghi vào lần 1. */
vai( 'Admin', 'KT' );
$maD = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian tách hai chốt', 'NV' )['maDA'];
foreach ( array( array( 'A', 5000000 ), array( 'B', 25000000 ) ) as $c ) {
	VHCP_DuAn::add_line( $maD, array( 'noiDung' => $c[0], 'thucTe' => $c[1] ) );
}
$RD2 = array();
foreach ( VHCP_DuAn::get_du_an( $maD )['lines'] as $l ) { $RD2[ $l['noiDung'] ] = (int) $l['row']; }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maD, array( $RD2['A'], $RD2['B'] ), array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RD2['A'] ) ),
	array( 'ngay' => '10/09/2026', 'rows' => array( $RD2['B'] ) ),
), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maD, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::cap_tien_phan( $maD, 1, array( 'soTien' => 3000000, 'choLan' => 1 ) );
/* Lệnh 30tr đã đưa 3tr → mức LỆNH còn 27tr, thừa sức. Lần 1 (5tr) đã nhận 3tr → chỉ còn 2tr. */
$r = VHCP_DuAn::cap_tien_phan( $maD, 1, array( 'soTien' => 4000000, 'choLan' => 1 ) );
t( '🔴 lệnh còn 27tr nhưng LẦN 1 chỉ còn 2tr → gắn 4tr vào lần 1 bị chối (chốt của lần đứng '
	. 'riêng, và nó TRỪ phần lần ấy đã nhận)', empty( $r['success'] ), $r );
t( '   câu chối nói đúng 2.000.000đ còn lại của lần ấy',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], '2.000.000đ' ), $r );
$r = VHCP_DuAn::cap_tien_phan( $maD, 1, array( 'soTien' => 4000000, 'choLan' => 2 ) );
t( '   nhưng gắn đúng 4tr ấy vào LẦN 2 thì chạy — lệnh vẫn còn thừa tiền', ! empty( $r['success'] ), $r );
teq( '   sổ chia đúng hai ngăn', array( 1 => 3000000, 2 => 4000000 ),
	VHCP_DuAn::da_cap_theo_lan( VHCP_DuAn::dot_cua( $maD, 1 ) ) );

/* 🔴 LỐI "CẤP TRỌN" KHÔNG ĐƯỢC ĐOÁN LẦN KHI LỊCH CÓ NHIỀU LẦN, kể cả lúc phần còn lại vừa khít
   lần đầu. Ở đây còn 23tr, lần 1 còn 2tr — không khít, nên ca này chưa soi được; ca soi được
   nằm ngay dưới. */
VHCP_DuAn::delete( $maD );

vai( 'Admin', 'KT' );
$maG = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian trọn khít lần đầu', 'NV' )['maDA'];
foreach ( array( array( 'A', 5000000 ), array( 'B', 3000000 ) ) as $c ) {
	VHCP_DuAn::add_line( $maG, array( 'noiDung' => $c[0], 'thucTe' => $c[1] ) );
}
$RG = array();
foreach ( VHCP_DuAn::get_du_an( $maG )['lines'] as $l ) { $RG[ $l['noiDung'] ] = (int) $l['row']; }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maG, array( $RG['A'], $RG['B'] ), array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RG['A'] ) ),
	array( 'ngay' => '10/09/2026', 'rows' => array( $RG['B'] ) ),
), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maG, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
/* Đưa 5tr KHÔNG gắn lần nào, rồi bấm cấp trọn: còn đúng 3tr — vừa khít phần chưa nhận của LẦN 1
   (5tr). Một bản đoán bừa "lịch có lần nào thì gắn lần đầu" sẽ ghi 3tr ấy vào lần 1, trong khi
   nó gần như chắc chắn là tiền của lần 2. */
VHCP_DuAn::cap_tien_phan( $maG, 1, array( 'soTien' => 5000000 ) );
VHCP_DuAn::dat_tt_dot( $maG, 1, 'ung', array( 'unc' => 'UNC-G' ) );
$LG = VHCP_DuAn::dot_cua( $maG, 1 );
teq( '🔴 cấp trọn khi lịch có NHIỀU lần → KHÔNG đoán lần, kể cả lúc số còn lại vừa khít lần đầu',
	0, (int) $LG['daCap'][1]['choLan'] );
teq( '   nên không lần nào bị ghi khống', array(), VHCP_DuAn::da_cap_theo_lan( $LG ) );
VHCP_DuAn::delete( $maG );

/* ── Không gắn lần nào vẫn hợp lệ ───────────────────────────────────────────────────────── */
vai( 'Admin', 'KT' );
$maK = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian không khai lịch', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maK, array( 'noiDung' => 'Thợ', 'thucTe' => 4000000 ) );
$rowK = 0;
foreach ( VHCP_DuAn::get_du_an( $maK )['lines'] as $l ) { if ( 'Thợ' === $l['noiDung'] ) { $rowK = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maK, array( $rowK ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maK, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
$r = VHCP_DuAn::cap_tien_phan( $maK, 1, array( 'soTien' => 4000000 ) );
t( '🔴 lệnh KHÔNG khai lịch → cấp không gắn lần vẫn chạy (nhận một lần là ca thường, ép gắn '
	. 'là bịa ra một cái lịch không ai lập)', ! empty( $r['success'] ), $r );
teq( '   sổ ghi choLan = 0', 0, (int) VHCP_DuAn::dot_cua( $maK, 1 )['daCap'][0]['choLan'] );
teq( '🔴 và lượt không gắn KHÔNG rơi vào lần nào cả', array(), VHCP_DuAn::da_cap_theo_lan( VHCP_DuAn::dot_cua( $maK, 1 ) ) );

/* ── Lối "cấp trọn": lịch một lần thì gắn được, nhiều lần thì không bịa ─────────────────── */
vai( 'Admin', 'KT' );
$maT = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian trọn một lần', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maT, array( 'noiDung' => 'Thợ', 'thucTe' => 4000000 ) );
$rowT = 0;
foreach ( VHCP_DuAn::get_du_an( $maT )['lines'] as $l ) { if ( 'Thợ' === $l['noiDung'] ) { $rowT = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maT, array( $rowT ),
	array( array( 'ngay' => '03/09/2026', 'rows' => array( $rowT ) ) ), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maT, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $maT, 1, 'ung', array( 'unc' => 'UNC-T' ) );
teq( '🔴 lịch chỉ có MỘT lần → lối "cấp trọn" gắn được vào chính lần ấy, không có gì để nhầm',
	1, (int) VHCP_DuAn::dot_cua( $maT, 1 )['daCap'][0]['choLan'] );

/* 🔴 MỘT LẦN TRONG LỊCH VẪN CHƯA ĐỦ ĐỂ GẮN — SỐ TIỀN PHẢI VỪA PHẦN CỦA LẦN ẤY.
   Lịch khai một lần 3tr, còn 5tr là "dự kiến lần tiếp theo" (chuyện thường: xin từng lần). Bấm
   cấp trọn là đưa cả 8tr — gắn trọn 8tr vào lần 1 thì khối lịch sẽ khoe "lần 1 đã nhận 8.000.000đ"
   trên một lần hẹn có 3tr. Con số ấy không sai ở tổng, nó sai ở chỗ nó bịa. */
vai( 'Admin', 'KT' );
$maV = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian một lần nhưng dư', 'NV' )['maDA'];
foreach ( array( array( 'A', 3000000 ), array( 'B', 5000000 ) ) as $c ) {
	VHCP_DuAn::add_line( $maV, array( 'noiDung' => $c[0], 'thucTe' => $c[1] ) );
}
$RV = array();
foreach ( VHCP_DuAn::get_du_an( $maV )['lines'] as $l ) { $RV[ $l['noiDung'] ] = (int) $l['row']; }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maV, array( $RV['A'], $RV['B'] ),
	array( array( 'ngay' => '03/09/2026', 'rows' => array( $RV['A'] ) ) ), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maV, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $maV, 1, 'ung', array( 'unc' => 'UNC-V' ) );
$LV = VHCP_DuAn::dot_cua( $maV, 1 );
teq( '   lượt cấp trọn đúng cả 8tr', 8000000, (int) $LV['daCap'][0]['soTien'] );
teq( '🔴 lịch một lần 3tr mà đưa 8tr → KHÔNG gắn vào lần ấy (gắn là khoe "lần 1 nhận 8tr" trên '
	. 'một lần hẹn có 3tr)', 0, (int) $LV['daCap'][0]['choLan'] );
VHCP_DuAn::delete( $maV );

vai( 'Admin', 'KT' );
$maH = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian trọn nhiều lần', 'NV' )['maDA'];
foreach ( array( array( 'A', 3000000 ), array( 'B', 5000000 ) ) as $c ) {
	VHCP_DuAn::add_line( $maH, array( 'noiDung' => $c[0], 'thucTe' => $c[1] ) );
}
$RH = array();
foreach ( VHCP_DuAn::get_du_an( $maH )['lines'] as $l ) { $RH[ $l['noiDung'] ] = (int) $l['row']; }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maH, array( $RH['A'], $RH['B'] ), array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RH['A'] ) ),
	array( 'ngay' => '10/09/2026', 'rows' => array( $RH['B'] ) ),
), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maH, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $maH, 1, 'ung', array( 'unc' => 'UNC-H' ) );
teq( '🔴 lịch NHIỀU lần → lối "cấp trọn" trả cho nhiều lần cùng lúc, gắn vào một lần là bịa → '
	. 'để 0', 0, (int) VHCP_DuAn::dot_cua( $maH, 1 )['daCap'][0]['choLan'] );
foreach ( array( $maL, $maK, $maT, $maH ) as $x ) { VHCP_DuAn::delete( $x ); }

/* ═══ 7b. MÀN HÌNH — phần người dùng thật sự nhìn và bấm ══════════════════════════════ */
$HTML = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/templates/app.html' );

t( '🔴 form cấp tiền có ô SỐ TIỀN ĐƯA LẦN NÀY', false !== mb_strpos( $HTML, 'Số tiền đưa lần này' ) );
/* 🔴 Ô TIỀN ĐIỀN SẴN PHẦN CỦA LẦN ĐANG CHỌN, không phải cả phần còn lại của lệnh — từ 1.201.0
   kế toán trả theo TỪNG LẦN (anh Thắng: *"Xin lần 1 thì cấp lần 1 chứ"*). Lệnh không khai lịch
   thì `_capSoGoiY()` lùi về đúng con số cũ. */
t( '🔴 ô ấy điền sẵn phần của lần đang chọn (trả đúng lần rồi bấm — khỏi gõ)',
	false !== strpos( $HTML, "value=\"'+money(_capSoGoiY(x,con))+'\"" ) );
t( '   có ô ngày đưa để sau còn đối chiếu', false !== strpos( $HTML, 'data-hmcapngay=' ) );
/* Sổ cũ (ghi trước 18/09/2026) để trống ngày hẳn — máy chủ nay điền sẵn, nhưng không ai đi sửa
   lại mấy dòng đã nằm trong sổ, nên màn vẫn phải có lưới đỡ bằng `luc`. */
t( '🔴 màn đọc `luc` khi dòng cũ trong sổ không có ngày', false !== strpos( $HTML, 'var khi=String(y.ngay||y.luc||\'\');' ) );
t( '   và vẫn đính được uỷ nhiệm chi của riêng lần ấy',
	false !== strpos( $HTML, "_hmOTep(k,maDA,'unc'" ) );

/* 🔴 Ô CHỌN LẦN — chỗ kế toán khai "đang cấp cho lần nào". Không có nó thì mọi thứ ở trên chỉ
   là luật chết: máy chủ nhận `choLan` mà màn không bao giờ gửi. */
t( '🔴 form cấp tiền có ô CHỌN LẦN', false !== strpos( $HTML, 'data-hmcaplan="' ) );
t( '   đổi lần thì ô tiền chạy theo phần còn thiếu của lần ấy (hai ô luôn khớp, khỏi tự dò)',
	false !== strpos( $HTML, 'onchange="capLanDoi(' ) );
t( '🔴 và `choLan` thật sự được gửi lên (thiếu chỗ này là cả luật bên máy chủ thành vô dụng)',
	false !== strpos( $HTML, 'choLan:(ol?Number(ol.value)||0:0)' ) );

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
