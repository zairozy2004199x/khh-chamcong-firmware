<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỘT DỰ ÁN LÀ MỘT ĐƠN — TRONG ĐÓ CÓ NHIỀU LỆNH TẠM ỨNG.
 *
 * Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì nó tổng
 * tổng tạm ứng cần xin"*, *"cho tích để bấm xin đơn 1 lần cho nhanh"*,
 * *"đơn nào chưa bấm xin làm nháp"*.
 *
 * =============================================================================================
 * Bản trước hiểu sai: mỗi hạng mục lớn thành MỘT đơn riêng đi tới kế toán. Bốn hạng mục là bốn
 * lần duyệt, bốn lần cấp tiền, bốn tờ uỷ nhiệm chi — cho một đợt setup. Mười hạng mục thì hai
 * mươi lượt bấm, và tiền đi thành mười lệnh chuyển khoản.
 *
 * 🔴 SỐ TIỀN CỦA LỆNH = TỔNG CÁC HẠNG MỤC ĐÃ TÍCH, và tính theo luật "có con thì cộng con".
 *    Cộng cả cha lẫn con là đếm hai lần — kế toán chuyển đi gấp đôi.
 *
 * 🔴 SỐ TIỀN CHỐT LÚC GỬI, KHÔNG TÍNH LẠI LÚC ĐỌC. Tính lại thì nhân viên sửa một dòng sau khi
 *    gửi là con số quản lý đã duyệt tự đổi sau lưng họ — duyệt 20 triệu, cấp ra 25 triệu.
 *
 * 🔴 MỘT HẠNG MỤC KHÔNG ĐƯỢC NẰM TRONG HAI LỆNH. Xin hai lần cùng một khoản là tiền ra khỏi két
 *    gấp đôi cho một việc.
 *
 * 🔴 TRẢ LẠI THÌ PHẢI GỠ SỐ ĐỢT. Giữ đợt thì nhân viên sửa xong tích lại sẽ bị chối "đã nằm
 *    trong một lệnh rồi", mà lệnh ấy đã bị trả — đơn chết cứng, không đường nào gửi lại.
 *
 * ⚠️ CHẠY THẬT trên dữ liệu thật, đổi vai qua từng bước.
 *
 * Chạy: php tools/test/kiem-lenh-tam-ung-du-an.php
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
$r  = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thử lệnh', 'NV' );
$ma = $r['maDA'];
/* Đúng hình dạng ảnh anh Thắng gửi: hạng mục lớn CÓ CON (tiền nằm ở con), hạng mục lớn KHÔNG
   con (tiền ở chính nó), và một hạng mục 🏢 kế toán trả thẳng NCC. */
/* 🔴 Hạng mục lớn này CÓ thực tế riêng (500k) LẪN con. Để 0 thì phép "không cộng cả cha lẫn
   con" xanh oan — cộng thêm 0 vẫn ra đúng số. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Mua đồ điện', 'duToan' => 0, 'thucTe' => 500000 ) );
foreach ( array( array( 'Bóng đèn', 2000000 ), array( 'Dây điện', 3000000 ), array( 'Phích cắm', 6000000 ), array( 'Xe cẩu', 2000000 ) ) as $c ) {
	VHCP_DuAn::them_dong_muc_con_cu( $ma, array( 'noiDung' => $c[0], 'capCha' => 'Mua đồ điện', 'thucTe' => $c[1] ) );
}
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thợ bốc vác', 'thucTe' => 2300000 ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Xe ba gác', 'thucTe' => 2000000, 'hinhThuc' => 'Trực tiếp' ) );
$d = VHCP_DuAn::get_du_an( $ma );
$R = array();
foreach ( $d['lines'] as $l ) { if ( '' === $l['capCha'] ) { $R[ $l['noiDung'] ] = $l['row']; } }
t( 'dựng được dữ liệu thử', count( $R ) === 3, array_keys( $R ) );

/* ═══ 1. TIỀN CỦA MỘT HẠNG MỤC ═══════════════════════════════════════════════════════════ */
t( '🔴 hạng mục CÓ CON: tiền = tổng con (13tr), KHÔNG cộng thêm 500k của chính nó — '
	. 'đếm hai lần là kế toán chuyển đi thừa',
	13000000 == VHCP_DuAn::tien_hm( $ma, $R['Mua đồ điện'] ), VHCP_DuAn::tien_hm( $ma, $R['Mua đồ điện'] ) );
t( 'hạng mục KHÔNG con: tiền = thực tế của chính nó',
	2300000 == VHCP_DuAn::tien_hm( $ma, $R['Thợ bốc vác'] ), VHCP_DuAn::tien_hm( $ma, $R['Thợ bốc vác'] ) );
t( 'dòng không có thật → 0, không nổ', 0 == VHCP_DuAn::tien_hm( $ma, 999 ) );

/* ═══ 2. 🔴 TÍCH NHIỀU HẠNG MỤC → MỘT LỆNH, MỘT SỐ TIỀN ═════════════════════════════════ */
teq( 'chưa bấm xin thì hạng mục là NHÁP', 'nhap', VHCP_DuAn::hm_cua( $ma, $R['Mua đồ điện'] )['tt'] );
vai( 'Nhân viên', 'NV' );
/* Lịch: xếp "Mua đồ điện" vào đợt nhận tiền đầu, "Thợ bốc vác" vào một mục thiếu ngày (bị bỏ).
   Số tiền của đợt KHÔNG khai ở đây — máy chủ cộng lấy từ chính mấy hàng được xếp vào. */
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Mua đồ điện'], $R['Thợ bốc vác'] ),
	array( array( 'ngay' => '12/09/2026', 'rows' => array( $R['Mua đồ điện'] ) ),
		array( 'rows' => array( $R['Thợ bốc vác'] ) ) ), 'Vật tư đợt đầu' );
t( '🔴 tích hai hạng mục → gửi được MỘT lệnh', ! empty( $x['success'] ), $x );
teq( '   lệnh đánh số đợt 1', 1, $x['dot']['dot'] );
teq( '   gồm đúng 2 hạng mục', 2, $x['so'] );
t( '🔴 SỐ TIỀN CỦA LỆNH = 13.000.000 + 2.300.000 = 15.300.000',
	15300000 == $x['soTien'], $x['soTien'] );
teq( '   lệnh ở trạng thái chờ duyệt', 'xin', $x['dot']['tt'] );
teq( '🔴 lịch thiếu ngày bị bỏ (kế toán chuẩn bị tiền vào hôm nào?)', 1, count( $x['dot']['lich'] ) );
/* 🔴 SỐ TIỀN CỦA ĐỢT MÁY CHỦ TỰ CỘNG — "Mua đồ điện" là hạng mục CÓ CON, nên 13.000.000đ
   (tổng con), y hệt `tien_hm()`. Lấy 500k của chính cha là đợt hụt mất 12,5 triệu. */
t( '🔴 số tiền đợt 1 = tổng hàng xếp vào nó (Mua đồ điện = 13.000.000, tiền nằm ở con)',
	13000000 == $x['dot']['lich'][0]['soTien'], $x['dot']['lich'][0] );
teq( '   và đợt giữ danh sách hàng của nó', array( $R['Mua đồ điện'] ), $x['dot']['lich'][0]['rows'] );
teq( '   giữ ghi chú cho kế toán', 'Vật tư đợt đầu', $x['dot']['lyDo'] );
teq( 'hạng mục trong lệnh chuyển sang chờ duyệt', 'xin', VHCP_DuAn::hm_cua( $ma, $R['Mua đồ điện'] )['tt'] );
teq( '   và mang số đợt của lệnh', 1, VHCP_DuAn::hm_cua( $ma, $R['Mua đồ điện'] )['dot'] );
teq( '🔴 hạng mục KHÔNG tích thì vẫn là nháp', 'nhap', VHCP_DuAn::hm_cua( $ma, $R['Xe ba gác'] )['tt'] );

/* ═══ 3. 🔴 CHỐI NHỮNG CA LÀM TIỀN ĐI HAI LẦN ═══════════════════════════════════════════ */
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Mua đồ điện'] ) );
t( '🔴 hạng mục đã nằm trong lệnh → CHỐI (xin hai lần là tiền ra khỏi két gấp đôi)',
	empty( $x['success'] ), $x );
t( '   và gọi TÊN hạng mục đang vướng', isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Mua đồ điện' ), $x );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Xe ba gác'] ) );
t( '🔴 hạng mục 🏢 kế toán trả thẳng NCC → CHỐI (xin tiền cho khoản mình không trả)',
	empty( $x['success'] ), $x );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array() );
t( 'không tích gì → chối', empty( $x['success'] ), $x );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( 999 ) );
t( '🔴 dòng không phải hạng mục lớn của dự án này → chối (không cho dòng lạ lọt vào lệnh)',
	empty( $x['success'] ), $x );
$con = null;
foreach ( $d['lines'] as $l ) { if ( 'Bóng đèn' === $l['noiDung'] ) { $con = $l['row']; } }
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $con ) );
t( '🔴 MỤC CON không xin riêng được (tiền của nó đã nằm trong hạng mục lớn — cộng hai lần)',
	empty( $x['success'] ), $x );

/* ═══ 4. DUYỆT → CẤP TIỀN, CẢ LỆNH ═════════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
t( '🔴 nhân viên KHÔNG tự duyệt lệnh của mình', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-1' ) );
t( '🔴 nhân viên KHÔNG tự cấp tiền cho mình', empty( $x['success'] ), $x );

vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-1' ) );
t( '🔴 quản lý duyệt được nhưng KHÔNG cấp tiền được', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
t( 'quản lý duyệt cả lệnh một lần', ! empty( $x['success'] ), $x );
teq( '   mọi hạng mục trong lệnh đi theo', 'duyet', VHCP_DuAn::hm_cua( $ma, $R['Thợ bốc vác'] )['tt'] );

vai( 'Kế toán cá nhân', 'KT' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-2026-88' ) );
t( '🔴 kế toán cấp CẢ LỆNH một lần, kèm MỘT uỷ nhiệm chi', ! empty( $x['success'] ), $x );
teq( '   uỷ nhiệm chi ghi vào lệnh', 'UNC-2026-88', VHCP_DuAn::dot_cua( $ma, 1 )['unc'] );
teq( '   và xuống tới từng hạng mục', 'UNC-2026-88', VHCP_DuAn::hm_cua( $ma, $R['Mua đồ điện'] )['unc'] );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-LAN-2' ) );
t( '🔴 cấp tiền LẦN HAI cho cùng một lệnh → CHỐI (bấm nhầm hai lần là chuyển đi hai lần)',
	empty( $x['success'] ), $x );
/* Câu chối phải NÓI ĐÚNG CHUYỆN. Ngã về câu chung "chỉ cấp tiền cho lệnh đã duyệt" thì kế toán
   nhìn lệnh mình vừa duyệt xong và không hiểu vì sao bị chối. */
t( '   và nói rõ lệnh này ĐÃ CẤP RỒI',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'đã cấp tiền rồi' ), $x );
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'tra' );
t( '🔴 lệnh ĐÃ CẤP TIỀN thì không trả lại được (tiền đã ra khỏi két, trả lại là xoá dấu vết)',
	empty( $x['success'] ), $x );
t( '   và cũng nói rõ vì sao',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'đã cấp tiền rồi' ), $x );
vai( 'Kế toán cá nhân', 'KT' );
teq( '   uỷ nhiệm chi giữ nguyên của lần cấp thật', 'UNC-2026-88', VHCP_DuAn::dot_cua( $ma, 1 )['unc'] );

/* ═══ 5. LỆNH ĐỢT 2 ════════════════════════════════════════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Đơn linh tinh', 'thucTe' => 50000 ) );
$d2 = VHCP_DuAn::get_du_an( $ma ); $r2 = null;
foreach ( $d2['lines'] as $l ) { if ( 'Đơn linh tinh' === $l['noiDung'] ) { $r2 = $l['row']; } }
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $r2 ) );
t( '🔴 cần thêm tiền → tích tiếp, thành lệnh ĐỢT 2 (anh Thắng: "xin tạm ứng lần 2")',
	! empty( $x['success'] ) && 2 === $x['dot']['dot'], $x );
t( '   số tiền của đợt 2 chỉ là hạng mục của đợt 2', 50000 == $x['soTien'], $x['soTien'] );
teq( '   đợt 1 không bị đụng tới', 'ung', VHCP_DuAn::dot_cua( $ma, 1 )['tt'] );
teq( '   và số tiền đợt 1 giữ nguyên', 15300000.0, (float) VHCP_DuAn::dot_cua( $ma, 1 )['soTien'] );

/* 🔴 SỐ TIỀN CHỐT LÚC GỬI. Sửa một dòng sau khi gửi không được làm đổi con số đã duyệt. */
VHCP_DuAn::update_line( $ma, $r2, array( 'noiDung' => 'Đơn linh tinh', 'thucTe' => 9999999 ) );
teq( '🔴 sửa tiền dòng SAU khi gửi → số tiền của lệnh KHÔNG đổi', 50000.0,
	(float) VHCP_DuAn::dot_cua( $ma, 2 )['soTien'] );

/* ═══ 6. 🔴 TRẢ LẠI PHẢI GỠ ĐỢT, KHÔNG THÌ ĐƠN CHẾT CỨNG ═══════════════════════════════ */
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 2, 'tra', array( 'lyDo' => 'Thiếu hoá đơn' ) );
t( 'quản lý trả lệnh đợt 2', ! empty( $x['success'] ), $x );
teq( '   hạng mục về "bị trả lại"', 'tra', VHCP_DuAn::hm_cua( $ma, $r2 )['tt'] );
teq( '🔴 và GỠ số đợt (giữ đợt là nhân viên không gửi lại được nữa)', 0, VHCP_DuAn::hm_cua( $ma, $r2 )['dot'] );
teq( '   lý do trả ghi vào lệnh', 'Thiếu hoá đơn', VHCP_DuAn::dot_cua( $ma, 2 )['lyDo'] );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $r2 ) );
t( '🔴 sửa xong GỬI LẠI ĐƯỢC, thành lệnh đợt 3', ! empty( $x['success'] ) && 3 === $x['dot']['dot'], $x );
t( '   và số tiền nay lấy con số mới', 9999999 == $x['soTien'], $x['soTien'] );

/* ═══ 7. BẢNG LỆNH CHO KẾ TOÁN ═════════════════════════════════════════════════════════ */
vai( 'Kế toán cá nhân', 'KT' );
$ls = VHCP_DuAn::list_lenh_da();
$cua = array();
foreach ( $ls['items'] as $i ) { if ( $i['maDA'] === $ma ) { $cua[ $i['dot'] ] = $i; } }
teq( '🔴 kế toán thấy 3 LỆNH (không phải 5 hạng mục rời)', 3, count( $cua ) );
t( '   mỗi lệnh nói rõ gồm hạng mục nào',
	in_array( 'Mua đồ điện', $cua[1]['tenHM'], true ) && in_array( 'Thợ bốc vác', $cua[1]['tenHM'], true ), $cua[1]['tenHM'] );
t( '   và một số tiền tổng', 15300000 == $cua[1]['soTien'], $cua[1]['soTien'] );
teq( '   kèm uỷ nhiệm chi đã cấp', 'UNC-2026-88', $cua[1]['unc'] );

/* ═══ 8. 🔴 ĐƯỜNG CŨ ĐÃ BỊT ════════════════════════════════════════════════════════════ */
/* 🔴 Thử trên hạng mục CÒN NHÁP. Thử trên hạng mục đã qua bước xin thì luật cũ ("đã qua bước
   xin tạm ứng rồi") cũng chối — phép xanh mà chốt mới có bị đục thủng cũng không biết. */
vai( 'Admin', 'KT' );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Vật tư lẻ', 'thucTe' => 111000 ) );
$d3 = VHCP_DuAn::get_du_an( $ma ); $le = null;
foreach ( $d3['lines'] as $l ) { if ( 'Vật tư lẻ' === $l['noiDung'] ) { $le = $l['row']; } }
teq( 'hạng mục mới còn nháp', 'nhap', VHCP_DuAn::hm_cua( $ma, $le )['tt'] );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::dat_hm( $ma, $le, 'xin' );
t( '🔴 xin tạm ứng LẺ từng hạng mục đã bị chặn, kể cả khi nó còn nháp (nếu không, hạng mục '
	. 'nhảy sang "xin" mà không thuộc lệnh nào — kế toán không bao giờ thấy nó)',
	empty( $x['success'] ), $x );
teq( '   và nó vẫn là nháp', 'nhap', VHCP_DuAn::hm_cua( $ma, $le )['tt'] );
$x = VHCP_DuAn::dat_hm( $ma, $R['Thợ bốc vác'], 'xin' );
t( '   hạng mục đã qua bước xin cũng vậy', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_hm( $ma, $R['Thợ bốc vác'], 'duyet' );
t( '   duyệt lẻ cũng bị chặn', empty( $x['success'] ), $x );
/* Nhưng CHỐT HOÀN THÀNH thì vẫn theo từng hạng mục — mỗi hạng mục một hoá đơn riêng. */
$x = VHCP_DuAn::dat_hm( $ma, $R['Thợ bốc vác'], 'xong', array( 'hoaDon' => 'https://hd/1' ) );
t( '🔴 nhưng CHỐT HOÀN THÀNH vẫn theo từng hạng mục (mỗi hạng mục một hoá đơn riêng)',
	! empty( $x['success'] ), $x );
teq( '   và khoá lại', 'xong', VHCP_DuAn::hm_cua( $ma, $R['Thợ bốc vác'] )['tt'] );
/* Đơn 🏢 NCC vẫn đi lối riêng của nó — nhưng chỉ KẾ TOÁN tích được. */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::dat_hm( $ma, $R['Xe ba gác'], 'xong', array( 'hoaDon' => 'https://hd/gia' ) );
t( '🔴 nhân viên KHÔNG khoá được đơn NCC (khoá là chốt "chi thực tế" — việc của người giữ két)',
	empty( $x['success'] ), $x );
vai( 'Kế toán cá nhân', 'KT' );
$x = VHCP_DuAn::dat_hm( $ma, $R['Xe ba gác'], 'xong', array( 'hoaDon' => 'https://hd/2', 'unc' => 'UNC-NCC' ) );
t( '🔴 đơn 🏢 kế toán trả thẳng NCC vẫn tích khoá được như cũ', ! empty( $x['success'] ), $x );

/* ═══ 8b. 🔴 BA CON SỐ TẠM ỨNG CỦA CẢ ĐƠN ══════════════════════════════════════════════
 * Anh Thắng: *"Dự kiến tạm ứng tổng đơn"*, *"Số tiền đã xin tạm ứng"*,
 * *"Số tiền kế toán đã chi tạm ứng"*.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
/* Dựng thêm MỘT LỆNH ĐANG CHỜ CẤP TIỀN (đã duyệt, chưa chi). Không có nó thì "đã xin" và
   "đã chi" chỉ khác nhau bởi lệnh đang 'xin' — mà một lỗi tính cả lệnh 'duyet' vào "đã chi"
   sẽ không lộ ra. */
vai( 'Admin', 'KT' );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thuê giàn giáo', 'thucTe' => 700000 ) );
$d4 = VHCP_DuAn::get_du_an( $ma ); $gg = null;
foreach ( $d4['lines'] as $l ) { if ( 'Thuê giàn giáo' === $l['noiDung'] ) { $gg = $l['row']; } }
vai( 'Nhân viên', 'NV' );
$xg = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $gg ) );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, $xg['dot']['dot'], 'duyet' );
teq( 'có một lệnh đang chờ cấp tiền', 'duyet', VHCP_DuAn::dot_cua( $ma, $xg['dot']['dot'] )['tt'] );

vai( 'Kế toán cá nhân', 'KT' );
$dd = VHCP_DuAn::get_du_an( $ma );
/* Hạng mục 💰 lúc này: "Mua đồ điện" 13.000.000 (tiền ở con) + "Thợ bốc vác" 2.300.000
   + "Đơn linh tinh" 9.999.999 + "Vật tư lẻ" 111.000 (CÒN NHÁP) + "Thuê giàn giáo" 700.000 = 26.110.999.
   🏢 "Xe ba gác" 2.000.000 KHÔNG tính — tiền ấy kế toán trả thẳng nhà cung cấp.
   ⚠️ "Vật tư lẻ" cố ý CHƯA gửi lệnh nào: nếu mọi hạng mục đều đã xin thì hai con số bằng nhau,
      và một lỗi đổi chỗ chúng cho nhau vẫn xanh. */
t( '🔴 dự kiến tạm ứng = tổng hạng mục 💰, KỂ CẢ cái còn nháp (26.110.999)',
	26110999 == $dd['duKienTU'], $dd['duKienTU'] );
t( '🔴 và BỎ khoản 🏢 kế toán trả thẳng NCC (2tr) — tiền ấy không đi đường tạm ứng',
	28110999 != $dd['duKienTU'], $dd['duKienTU'] );
/* Lệnh đợt 1 (15,3tr, ĐÃ CẤP) + đợt 3 (9.999.999, đang xin) + đợt 4 (700.000, ĐÃ DUYỆT chưa
   cấp). Đợt 2 ĐÃ BỊ TRẢ → không tính. */
/* 🔴 TỪ 1.196.0 "ĐÃ XIN" ĐẾM THEO ĐỢT, KHÔNG ĐẾM CẢ LỆNH. Anh Thắng 17/09/2026: *"Số tiền xin
   tạm ứng đợt 1, chứ xin tổng vẫn chưa mà"*. Lệnh đợt 1 là 15.300.000đ nhưng lịch chỉ khai một
   đợt gồm mỗi "Mua đồ điện" = 13.000.000đ, nên phần "đã xin" của nó là 13tr; 2.300.000đ của
   "Thợ bốc vác" là "dự kiến đợt tiếp theo". Hai lệnh kia không khai lịch → lấy trọn số lệnh,
   đúng ca thường nhất. */
t( '🔴 đã xin = tổng các ĐỢT đã khai, KHÔNG tính lệnh bị trả lại (13.000.000 + 9.999.999 + 700.000)',
	23699999 == $dd['daXinTU'], $dd['daXinTU'] );
t( '🔴 phần lệnh chưa xếp vào đợt nào = dự kiến đợt tiếp theo (15.300.000 − 13.000.000)',
	2300000 == $dd['duKienDotSau'], $dd['duKienDotSau'] );
t( '   `daVaoLenh` giữ nghĩa CŨ (tổng mọi lệnh đã gửi) cho dòng "đã đưa hết hạng mục vào lệnh"',
	25999999 == $dd['daVaoLenh'], $dd['daVaoLenh'] );
t( '   nên nó KHÁC con số dự kiến — còn "Vật tư lẻ" chưa gửi',
	$dd['duKienTU'] != $dd['daXinTU'], array( $dd['duKienTU'], $dd['daXinTU'] ) );
t( '🔴 đã chi = CHỈ lệnh kế toán đã cấp tiền (15.300.000) — lệnh mới duyệt chưa tính',
	15300000 == $dd['daChiTU'], $dd['daChiTU'] );
t( '   ba con số không cái nào bằng cái nào (phép này bắt lỗi đổi chỗ)',
	$dd['duKienTU'] != $dd['daChiTU'] && $dd['daXinTU'] != $dd['daChiTU'], $dd );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 10. 🔴 SỐ TIỀN TRƯỚC KHI CHI = DỰ TOÁN, KHÔNG PHẢI THỰC TẾ
 *
 * Anh Thắng 17/09/2026: *"đã nhập số liệu thì cần cộng vào luôn để biết bao nhiêu"*, kèm ảnh
 * một dự án 125.907.312đ dự toán mà thẻ "Dự kiến tạm ứng tổng đơn" đứng 0đ và "Số tiền đã xin
 * tạm ứng" cũng 0đ — trong khi dòng ghi chú ngay cạnh nói "✓ đã gửi xin hết".
 *
 * Vì `tien_hm()` chỉ đọc cột thực tế. Đúng cho quyết toán, SAI cho mọi con số đứng trước lúc
 * tiền ra khỏi két — lúc ấy chưa ai tiêu gì, nên nó luôn bằng 0.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
$ma2 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian chỉ có dự toán', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma2, array( 'noiDung' => 'Thợ Phụ',   'duToan' => 48000000 ) );
VHCP_DuAn::add_line( $ma2, array( 'noiDung' => 'Băng keo',  'duToan' => 390000 ) );
/* Hạng mục lớn có con, mà con CHƯA nhập thực tế — hình dạng thường gặp nhất lúc lập dự toán. */
VHCP_DuAn::add_line( $ma2, array( 'noiDung' => 'Vật tư', 'duToan' => 5000000 ) );
VHCP_DuAn::them_dong_muc_con_cu( $ma2, array( 'noiDung' => 'Ốc vít', 'capCha' => 'Vật tư' ) );
/* Và một hạng mục ĐÃ có thực tế — thực tế phải THẮNG dự toán. */
VHCP_DuAn::add_line( $ma2, array( 'noiDung' => 'Xe cẩu', 'duToan' => 1000000, 'thucTe' => 1750000 ) );
$d2 = VHCP_DuAn::get_du_an( $ma2 );
$R2 = array();
foreach ( $d2['lines'] as $l ) { if ( '' === $l['capCha'] ) { $R2[ $l['noiDung'] ] = $l['row']; } }

t( '🔴 chưa chi gì thì tiền dự kiến = DỰ TOÁN (48tr), không phải 0',
	48000000 == VHCP_DuAn::tien_hm_du_kien( $ma2, $R2['Thợ Phụ'] ),
	VHCP_DuAn::tien_hm_du_kien( $ma2, $R2['Thợ Phụ'] ) );
t( '   có con mà con chưa nhập thực tế → lùi về dự toán của CHA (5tr)',
	5000000 == VHCP_DuAn::tien_hm_du_kien( $ma2, $R2['Vật tư'] ),
	VHCP_DuAn::tien_hm_du_kien( $ma2, $R2['Vật tư'] ) );
t( '   đã có thực tế thì THỰC TẾ thắng dự toán (1.75tr, không phải 1tr)',
	1750000 == VHCP_DuAn::tien_hm_du_kien( $ma2, $R2['Xe cẩu'] ),
	VHCP_DuAn::tien_hm_du_kien( $ma2, $R2['Xe cẩu'] ) );
t( '   dòng không có thật → 0, không nổ', 0 == VHCP_DuAn::tien_hm_du_kien( $ma2, 999 ) );

/* 🔴 CHỐT CHỐNG GỘP HAI HÀM. Ai đó thấy hai hàm gần giống nhau rồi gộp lại thì quyết toán sẽ
   chốt sổ bằng con số KẾ HOẠCH — sổ khớp đẹp, tiền thì không ai biết đã đi đâu. */
t( '🔴 `tien_hm()` KHÔNG đổi: vẫn chỉ đọc thực tế, hạng mục chỉ có dự toán ra 0',
	0 == VHCP_DuAn::tien_hm( $ma2, $R2['Thợ Phụ'] ), VHCP_DuAn::tien_hm( $ma2, $R2['Thợ Phụ'] ) );

$dd2 = VHCP_DuAn::get_du_an( $ma2 );
teq( '🔴 thẻ "Dự kiến tạm ứng tổng đơn" cộng đủ mọi hạng mục NV tự trả, không còn 0',
	48000000 + 390000 + 5000000 + 1750000, (int) $dd2['duKienTU'] );

/* 🔴 CỘT THỨ BA: ĐƠN CHI PHÍ CƠ SỞ KHÔNG DÙNG Ô DỰ TOÁN.
   Nhân viên gõ Số lượng × Đơn giá, tiền nằm trọn ở `thanh_tien`. Bản đầu của `tien_hm_du_kien()`
   chỉ nhìn thực tế + dự toán, nên nó vá xong đơn dự án mà đơn cơ sở VẪN xin 0đ. */
vai( 'Admin', 'KT' );
$maC = VHCP_DuAn::tao_don_coso( 'Tuần thử cột thành tiền', 'Sếp', '14/09/2026', '20/09/2026' )['maDA'];
VHCP_DuAn::add_line( $maC, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'G1', 'soLuong' => 2, 'donGia' => 500000 ) );
$dC = VHCP_DuAn::get_du_an( $maC );
$RC = array();
foreach ( $dC['lines'] as $l ) { if ( '' === $l['capCha'] ) { $RC[ $l['noiDung'] ] = $l['row']; } }
t( '🔴 đơn cơ sở: tiền dự kiến lấy từ SỐ LƯỢNG × ĐƠN GIÁ (1tr), không phải 0',
	1000000 == VHCP_DuAn::tien_hm_du_kien( $maC, $RC['Cáp màn hình'] ),
	VHCP_DuAn::tien_hm_du_kien( $maC, $RC['Cáp màn hình'] ) );

/* ═══ 11. LỆNH XIN TẠM ỨNG LẤY ĐÚNG SỐ ẤY — chỗ đắt nhất của lỗi ═══════════════════════ */
vai( 'Nhân viên', 'NV' );
$x2 = VHCP_DuAn::xin_tam_ung_dot( $ma2, array( $R2['Thợ Phụ'], $R2['Băng keo'] ),
	array( array( 'ngay' => '18/09/2026', 'rows' => array( $R2['Thợ Phụ'], $R2['Băng keo'] ) ) ), 'Đợt đầu' );
t( '🔴 xin tạm ứng cho hạng mục mới chỉ có dự toán → gửi được',
	! empty( $x2['success'] ), $x2 );
teq( '🔴 và số tiền của lệnh = 48tr + 390k, KHÔNG phải 0đ',
	48390000, (int) $x2['dot']['soTien'] );
/* 🔴 SỐ TIỀN CỦA ĐỢT CŨNG PHẢI LẤY DỰ TOÁN. Đây là chỗ đắt nhất: đợt nhận tiền là con số kế
   toán ĐỌC ĐỂ CHUẨN BỊ TIỀN, mà lúc này chưa ai tiêu đồng nào. Cộng bằng `tien_hm()` (chỉ đọc
   thực tế) thì lệnh ghi 48.390.000đ còn đợt ghi 0đ — kế toán đi tay không. */
teq( '🔴 và đợt nhận tiền cũng là 48.390.000đ, không phải 0đ (chưa ai chi đồng nào)',
	48390000, (int) $x2['dot']['lich'][0]['soTien'] );

/* ═══ 12. LƯỚI CUỐI: KHÔNG DỰNG LỆNH 0đ ════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
$ma3 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian trống trơn', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma3, array( 'noiDung' => 'Chưa gõ giá', 'duToan' => 0, 'thucTe' => 0, 'note' => 'x' ) );
$d3 = VHCP_DuAn::get_du_an( $ma3 );
$R3 = array();
foreach ( $d3['lines'] as $l ) { if ( '' === $l['capCha'] ) { $R3[ $l['noiDung'] ] = $l['row']; } }
vai( 'Nhân viên', 'NV' );
$x3 = VHCP_DuAn::xin_tam_ung_dot( $ma3, array( $R3['Chưa gõ giá'] ), array(), '' );
t( '🔴 hạng mục chưa có số nào thì CHỐI, không dựng lệnh 0đ',
	empty( $x3['success'] ), $x3 );
t( '   và câu chối nói ra phải điền gì',
	isset( $x3['error'] ) && false !== mb_strpos( (string) $x3['error'], 'Dự toán' ), $x3 );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 13. 🔴 LỆNH BỊ TRẢ LẠI KHÔNG TÍNH LÀ MỘT ĐỢT
 *
 * Anh Thắng 17/09/2026: *"khi trả đơn thì phải hiểu không tính đó là 1 đợt"*, sau khi Đợt 1
 * bị trả (vì nó mang số tiền 0đ) và lệnh gửi lại hiện ra thành "Đợt 2" — đọc bảng thành ra dự
 * án này ứng làm hai đợt, mà đợt 1 là một lệnh không còn tồn tại.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
$maD = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian đếm đợt', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maD, array( 'noiDung' => 'Thợ Phụ',  'duToan' => 48000000 ) );
VHCP_DuAn::add_line( $maD, array( 'noiDung' => 'Băng keo', 'duToan' => 390000 ) );
VHCP_DuAn::add_line( $maD, array( 'noiDung' => 'Vận chuyển', 'duToan' => 1600000 ) );
$dD = VHCP_DuAn::get_du_an( $maD );
$RD = array();
foreach ( $dD['lines'] as $l ) { if ( '' === $l['capCha'] ) { $RD[ $l['noiDung'] ] = $l['row']; } }

vai( 'Nhân viên', 'NV' );
$l1 = VHCP_DuAn::xin_tam_ung_dot( $maD, array( $RD['Thợ Phụ'], $RD['Băng keo'] ), array(), '' );
teq( 'lệnh đầu: số trong sổ là 1', 1, (int) $l1['dot']['dot'] );
teq( '   và số hiện ra cũng là 1', 1, (int) $l1['dot']['soHien'] );

/* Trả lại → lệnh ấy thôi tính là một đợt. */
vai( 'Admin', 'KT' );
VHCP_DuAn::dat_tt_dot( $maD, 1, 'tra', array( 'lyDo' => 'số tiền 0đ' ) );
$ds = VHCP_DuAn::ds_dot( $maD );
teq( '🔴 lệnh bị trả có soHien = 0 (không tính là một đợt)', 0, (int) $ds[0]['soHien'] );
teq( '   nhưng vẫn còn trong sổ để giữ dấu vết ai trả, lúc nào', 1, count( $ds ) );
teq( '   và số trong sổ KHÔNG đổi (nó là khoá của mọi tham chiếu)', 1, (int) $ds[0]['dot'] );

/* Gửi lại → người dùng phải thấy "Đợt 1", dù trong sổ nó là 2. */
vai( 'Nhân viên', 'NV' );
$l2 = VHCP_DuAn::xin_tam_ung_dot( $maD, array( $RD['Thợ Phụ'], $RD['Băng keo'] ), array(), '' );
teq( 'lệnh gửi lại: số trong sổ là 2', 2, (int) $l2['dot']['dot'] );
teq( '🔴 nhưng SỐ HIỆN RA là 1 — đây là điều anh Thắng hỏi', 1, (int) $l2['dot']['soHien'] );
teq( '   và số tiền đã đúng, không còn 0đ', 48390000, (int) $l2['dot']['soTien'] );

/* Lệnh thật thứ hai → Đợt 2. */
$l3 = VHCP_DuAn::xin_tam_ung_dot( $maD, array( $RD['Vận chuyển'] ), array(), '' );
teq( 'lệnh thật thứ hai: số trong sổ là 3', 3, (int) $l3['dot']['dot'] );
teq( '🔴 số hiện ra là 2 — đếm tiếp, không nhảy cóc theo số sổ', 2, (int) $l3['dot']['soHien'] );

teq( '🔴 bản đồ số đợt: sổ 1 không tính, sổ 2 -> 1, sổ 3 -> 2',
	array( '1' => 0, '2' => 1, '3' => 2 ), VHCP_DuAn::ban_do_so_dot( $maD ) );

$dd = VHCP_DuAn::get_du_an( $maD );
t( '   và bản đồ ấy đi kèm dự án ra tới màn hình',
	isset( $dd['dotHien'] ) && isset( $dd['qtDotHien'] ), array_keys( $dd ) );
teq( '   ba lệnh đều còn trên bảng', 3, count( $dd['lenh'] ) );

/* ⚠️ Lệnh đã trả là TẬN CÙNG — không có đường quay lại 'xin'. Nếu có thì số hiện ra của mấy
   lệnh sau nó sẽ nhảy, và bảng đổi số sau lưng người dùng. */
vai( 'Admin', 'KT' );
$quay = VHCP_DuAn::dat_tt_dot( $maD, 1, 'duyet' );
t( '🔴 lệnh đã trả KHÔNG duyệt lại được (nếu được thì số đợt nhảy sau lưng người dùng)',
	empty( $quay['success'] ), $quay );

/* ═══ 8c. 🔴 XẾP TỪNG HÀNG VÀO ĐỢT NHẬN TIỀN ═══════════════════════════════════════════
 * Anh Thắng 17/09/2026: *"Anh muốn xác định chi phí từng hàng là chi lần 1, hay chi lần 2"*.
 *
 * =========================================================================================
 * 🔴 SỐ TIỀN CỦA ĐỢT LÀ PHÉP CỘNG, KHÔNG PHẢI MỘT Ô GÕ TAY. Bản trước nhận thẳng `soTien` từ
 *    màn hình: sửa được HTML là khai bao nhiêu cũng xong, và ngay cả dùng đúng thì nó vẫn lệch
 *    được với tổng lệnh (đơn của anh khai 10tr + 20tr cho một lệnh 68.790.000đ). Nay màn gửi
 *    lên DANH SÁCH HÀNG, máy chủ cộng — hai con số không còn đường nào để lệch.
 *
 * ⚠️ THỬ MẤY CA BỊ CHỐI TRƯỚC, LÚC CHƯA CÓ LỆNH NÀO. Thử sau khi đã gửi một lệnh thì luật cũ
 *    ("hạng mục đã nằm trong lệnh") chối trước, và phép xanh mà chốt mới có bị đục thủng cũng
 *    không biết — đúng cái bẫy đã sập một lần ở mục 8.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
vai( 'Admin', 'KT' );
$maX = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian xếp đợt', 'NV' )['maDA'];
foreach ( array( array( 'Thợ Phụ', 10000000 ), array( 'Vật tư', 20000000 ), array( 'Xe cẩu', 18790000 ) ) as $c ) {
	VHCP_DuAn::add_line( $maX, array( 'noiDung' => $c[0], 'thucTe' => $c[1] ) );
}
$RX = array();
foreach ( VHCP_DuAn::get_du_an( $maX )['lines'] as $l ) { $RX[ $l['noiDung'] ] = $l['row']; }
$ba = array( $RX['Thợ Phụ'], $RX['Vật tư'], $RX['Xe cẩu'] );

vai( 'Nhân viên', 'NV' );
/* 🔴 MỘT HÀNG KHÔNG ĐƯỢC NẰM Ở HAI ĐỢT. Nhận tiền hai lần cho một khoản là tiền ra khỏi két
   gấp đôi — và tổng lịch vọt quá tổng lệnh mà chẳng có gì nói ra. */
$xd = VHCP_DuAn::xin_tam_ung_dot( $maX, $ba, array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RX['Thợ Phụ'] ) ),
	array( 'ngay' => '10/09/2026', 'rows' => array( $RX['Thợ Phụ'] ) ),
) );
t( '🔴 xếp MỘT hàng vào hai đợt → chối', empty( $xd['success'] ), $xd );
/* Câu chối phải nói ĐÚNG CHUYỆN: gọi tên hàng, và nói rõ vướng ở chỗ HAI ĐỢT. Chỉ canh cái tên
   thì câu chối cũ ("hạng mục đã nằm trong lệnh") cũng khớp, và phép này xanh oan. */
t( '   gọi TÊN hàng đang vướng, và nói rõ vướng vì HAI LẦN NHẬN TIỀN',
	isset( $xd['error'] ) && false !== mb_strpos( $xd['error'], 'Thợ Phụ' )
	&& false !== mb_strpos( $xd['error'], 'hai lần nhận tiền' ), $xd );
teq( '   và lệnh KHÔNG được dựng ra dở dang', 'nhap', VHCP_DuAn::hm_cua( $maX, $RX['Thợ Phụ'] )['tt'] );

/* 🔴 CHỐI HÀNG KHÔNG NẰM TRONG LỆNH — xếp một dòng lạ vào đợt là hứa đưa tiền cho một khoản
   chưa ai duyệt. */
$xb = VHCP_DuAn::xin_tam_ung_dot( $maX, array( $RX['Thợ Phụ'] ),
	array( array( 'ngay' => '03/09/2026', 'rows' => array( $RX['Vật tư'] ) ) ) );
t( '🔴 xếp một dòng KHÔNG nằm trong lệnh vào đợt → chối', empty( $xb['success'] ), $xb );
t( '   và nói rõ dòng nào',
	isset( $xb['error'] ) && false !== mb_strpos( $xb['error'], 'không nằm trong lệnh này' ), $xb );
teq( '   lệnh cũng không được dựng ra dở dang', 'nhap', VHCP_DuAn::hm_cua( $maX, $RX['Thợ Phụ'] )['tt'] );

/* ── Đường đi đúng ─────────────────────────────────────────────────────────────────────── */
$xx = VHCP_DuAn::xin_tam_ung_dot( $maX, $ba, array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RX['Thợ Phụ'] ) ),
	array( 'ngay' => '10/09/2026', 'rows' => array( $RX['Vật tư'], $RX['Xe cẩu'] ) ),
) );
t( 'gửi được lệnh có hai đợt nhận tiền', ! empty( $xx['success'] ), $xx );
$LX = $xx['dot']['lich'];
t( '🔴 đợt 1 = đúng hàng xếp vào nó (10.000.000)', 10000000 == $LX[0]['soTien'], $LX[0] );
t( '🔴 đợt 2 = TỔNG hai hàng (20.000.000 + 18.790.000)', 38790000 == $LX[1]['soTien'], $LX[1] );
t( '   tổng hai đợt đúng bằng tổng lệnh — không cách nào khai lệch',
	48790000 == ( $LX[0]['soTien'] + $LX[1]['soTien'] ) && 48790000 == $xx['soTien'], $xx['soTien'] );
teq( '🔴 đợt giữ đủ DANH SÁCH HÀNG (không có nó thì bảng không đọc ra "đợt này gồm gì")',
	array( $RX['Vật tư'], $RX['Xe cẩu'] ), $LX[1]['rows'] );
teq( '   và đợt 1 giữ hàng của đợt 1', array( $RX['Thợ Phụ'] ), $LX[0]['rows'] );

/* Không xếp hết cũng được — phần còn lại là "dự kiến đợt tiếp theo", đúng lời anh Thắng. */
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maX, 1, 'tra' );
vai( 'Nhân viên', 'NV' );
$xc = VHCP_DuAn::xin_tam_ung_dot( $maX, $ba, array(
	array( 'ngay' => '03/09/2026', 'rows' => array( $RX['Thợ Phụ'] ) ),
) );
t( 'xếp thiếu vẫn gửi được', ! empty( $xc['success'] ), $xc );
$ddx = VHCP_DuAn::get_du_an( $maX );
t( '🔴 phần chưa xếp đợt nào = dự kiến đợt tiếp theo (48.790.000 − 10.000.000)',
	38790000 == $ddx['duKienDotSau'], $ddx['duKienDotSau'] );
t( '   còn "đã xin" chỉ đếm đợt đã khai', 10000000 == $ddx['daXinTU'], $ddx['daXinTU'] );
VHCP_DuAn::delete( $maX );

/* ═══ 8d. 🔴 ĐÃ LÊN LỆNH THÌ SỐ DỰ TOÁN ĐÓNG LẠI ═══════════════════════════════════════
 * Anh Thắng 18/09/2026: *"Số dự toán đã xin và lên thì không được sửa"*, kèm ảnh một đơn có
 * thẻ đỏ *"⚠️ lệnh đã gửi vượt dự kiến 14.970.000đ"* — vì mấy dòng dự toán bị gõ về 0 SAU khi
 * lệnh 68.790.000đ đã gửi và đã cấp tiền.
 *
 * =========================================================================================
 * Số tiền của LỆNH chốt cứng lúc gửi (đúng thế — xem mục 5). Nhưng "dự kiến tạm ứng" thì cộng
 * lại từ mấy dòng này mỗi lần mở trang. Sửa dòng sau khi gửi là hai con số tách nhau ra, rồi
 * màn hình tố cáo một chuyện không có thật: lệnh trông như xin vượt dự toán, trong khi lúc gửi
 * nó khớp. Người đọc không có cách nào biết bên nào mới đúng.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
vai( 'Admin', 'KT' );
$maK2 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian khoá dự toán', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maK2, array( 'noiDung' => 'Thợ Phụ', 'duToan' => 48000000, 'thucTe' => 0 ) );
VHCP_DuAn::add_line( $maK2, array( 'noiDung' => 'Vật tư', 'duToan' => 5000000, 'thucTe' => 0 ) );
$RK = array();
foreach ( VHCP_DuAn::get_du_an( $maK2 )['lines'] as $l ) { $RK[ $l['noiDung'] ] = (int) $l['row']; }

/* Còn nháp thì sửa thoải mái — chốt này không được cản lúc đang lập dự toán. */
$x = VHCP_DuAn::update_line( $maK2, $RK['Thợ Phụ'], array( 'noiDung' => 'Thợ Phụ', 'duToan' => 47000000 ) );
t( '🔴 hạng mục CÒN NHÁP thì sửa dự toán thoải mái (đang lập dự toán mà cản là cản sai bước)',
	! empty( $x['success'] ), $x );
teq( '   và số mới vào sổ thật', 47000000, (int) VHCP_DuAn::get_du_an( $maK2 )['lines'][0]['duToan'] );

vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maK2, array( $RK['Thợ Phụ'] ), array(), '' );
$x = VHCP_DuAn::update_line( $maK2, $RK['Thợ Phụ'], array( 'noiDung' => 'Thợ Phụ', 'duToan' => 0 ) );
t( '🔴 đã lên lệnh → gõ dự toán về 0 bị CHỐI (đúng cảnh ảnh anh Thắng gửi)', empty( $x['success'] ), $x );
t( '   câu chối gọi TÊN hạng mục và chỉ đường ra: trả lệnh về rồi gửi lại',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Thợ Phụ' )
	&& false !== mb_strpos( $x['error'], 'trả lệnh' ), $x );
teq( '🔴 và số trong sổ KHÔNG đổi', 47000000, (int) VHCP_DuAn::get_du_an( $maK2 )['lines'][0]['duToan'] );

/* 🔴 CỘT THỰC TẾ VẪN MỞ — đó là cả quy trình: cầm tiền đi tiêu rồi mới về ghi số thật. Khoá
   luôn cột ấy là không ai quyết toán được nữa. */
$x = VHCP_DuAn::update_line( $maK2, $RK['Thợ Phụ'],
	array( 'noiDung' => 'Thợ Phụ', 'duToan' => 47000000, 'thucTe' => 46500000 ) );
t( '🔴 nhưng CHI PHÍ THỰC TẾ vẫn ghi được (khoá luôn thì không ai quyết toán được nữa)',
	! empty( $x['success'] ), $x );
teq( '   và số thực tế vào sổ', 46500000, (int) VHCP_DuAn::get_du_an( $maK2 )['lines'][0]['thucTe'] );

/* Hạng mục KHÔNG nằm trong lệnh thì không bị vạ lây. */
$x = VHCP_DuAn::update_line( $maK2, $RK['Vật tư'], array( 'noiDung' => 'Vật tư', 'duToan' => 6000000 ) );
t( '🔴 hạng mục chưa vào lệnh nào KHÔNG bị vạ lây', ! empty( $x['success'] ), $x );

/* ⚠️ ÁP CHO CẢ MỤC CON: tiền của cha cộng từ con, nên sửa con là đổi số của cha. */
vai( 'Admin', 'KT' );
VHCP_DuAn::them_dong_muc_con_cu( $maK2, array( 'noiDung' => 'Ốc vít', 'capCha' => 'Thợ Phụ', 'thucTe' => 100000 ) );
$rCon = 0;
foreach ( VHCP_DuAn::get_du_an( $maK2 )['lines'] as $l ) { if ( 'Ốc vít' === $l['noiDung'] ) { $rCon = (int) $l['row']; } }
$x = VHCP_DuAn::update_line( $maK2, $rCon, array( 'noiDung' => 'Ốc vít', 'capCha' => 'Thợ Phụ',
	'soLuong' => 5, 'donGia' => 200000 ) );
t( '🔴 mục con của hạng mục đã lên lệnh cũng khoá (tiền của cha cộng từ con)',
	empty( $x['success'] ), $x );
t( '   và câu chối gọi tên HẠNG MỤC LỚN, không gọi tên dòng con',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Thợ Phụ' ), $x );

/* 🔴 KHOÁ CẢ BA CỘT SINH RA TIỀN, không chỉ ô "dự toán". Đơn cơ sở không dùng ô dự toán — tiền
   của nó nằm trọn ở SỐ LƯỢNG × ĐƠN GIÁ, và `tien_hm_du_kien()` đọc luôn `thành tiền`. Khoá mỗi
   cột dự toán là đơn cơ sở sửa tiền thoải mái sau khi đã lên lệnh, tức chốt này thủng đúng ở
   loại đơn nhiều nhất. Mỗi cột thử RIÊNG một phép: gộp lại thì bỏ sót một cột vẫn xanh. */
vai( 'Admin', 'KT' );
$maC2 = VHCP_DuAn::tao_don_coso( 'Tuần thử khoá', 'Sếp', '14/09/2026', '20/09/2026' )['maDA'];
VHCP_DuAn::add_line( $maC2, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'G1', 'soLuong' => 2, 'donGia' => 500000 ) );
$rC = 0;
foreach ( VHCP_DuAn::get_du_an( $maC2 )['lines'] as $l ) { if ( 'Cáp màn hình' === $l['noiDung'] ) { $rC = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maC2, array( $rC ), array(), '' );
vai( 'Admin', 'KT' );
$x = VHCP_DuAn::update_line( $maC2, $rC, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'G1',
	'soLuong' => 2, 'donGia' => 900000 ) );
t( '🔴 đơn cơ sở đã lên lệnh → sửa riêng ĐƠN GIÁ cũng bị chối', empty( $x['success'] ), $x );
t( '   và câu chối gọi đúng tên cột đang vướng', isset( $x['error'] )
	&& false !== mb_strpos( $x['error'], 'đơn giá' ), $x );
$x = VHCP_DuAn::update_line( $maC2, $rC, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'G1',
	'soLuong' => 7, 'donGia' => 500000 ) );
t( '🔴 sửa riêng SỐ LƯỢNG cũng bị chối', empty( $x['success'] ), $x );
t( '   gọi đúng tên cột ấy', isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'số lượng' ), $x );
$x = VHCP_DuAn::update_line( $maC2, $rC, array( 'noiDung' => 'Cáp màn hình (E.Nhật)', 'gian' => 'G1',
	'soLuong' => 2, 'donGia' => 500000 ) );
t( '   nhưng sửa thứ KHÔNG phải tiền (đổi tên, ghi chú) thì vẫn cho — chốt này giữ con số, '
	. 'không giữ cả dòng', ! empty( $x['success'] ), $x );
VHCP_DuAn::delete( $maC2 );

/* 🔴 ĐƯỜNG RA LÀ TRẢ LỆNH — không phải một nút "mở khoá dự toán" riêng. */
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maK2, 1, 'tra' );
vai( 'Admin', 'KT' );
$x = VHCP_DuAn::update_line( $maK2, $RK['Thợ Phụ'],
	array( 'noiDung' => 'Thợ Phụ', 'duToan' => 30000000, 'thucTe' => 46500000 ) );
t( '🔴 TRẢ LỆNH xong thì sửa lại được — đường ra có người duyệt, khác một nút mở khoá tự do',
	! empty( $x['success'] ), $x );
teq( '   và số mới vào sổ', 30000000, (int) VHCP_DuAn::get_du_an( $maK2 )['lines'][0]['duToan'] );
VHCP_DuAn::delete( $maK2 );

/* ═══ 8e. 🔴 ADMIN THU HỒI ĐƯỢC LỆNH ĐÃ CẤP TIỀN ═══════════════════════════════════════
 * Anh Thắng 18/09/2026: *"Cấp quyền cho admin trả đơn"*, sau khi một đơn 68.790.000đ đã cấp
 * tiền mà số liệu sai và không còn đường nào quay lại. Chốt về tiền: *"coi như chưa chi — gỡ
 * khỏi TK 141"*.
 *
 * =========================================================================================
 * Luật cũ chặn tuyệt đối ("tiền đã ra khỏi két, trả lại là xoá dấu vết") — đúng về ý, sai về
 * hệ quả: một hệ không có đường lùi thì người ta không sửa sổ, họ BỊA sổ. Thà mở một cửa hẹp
 * có tên, có lý do, có vết.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
vai( 'Admin', 'KT' );
$maT2 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thu hồi', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maT2, array( 'noiDung' => 'Thợ Phụ', 'thucTe' => 48000000 ) );
$rT = 0;
foreach ( VHCP_DuAn::get_du_an( $maT2 )['lines'] as $l ) { if ( 'Thợ Phụ' === $l['noiDung'] ) { $rT = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maT2, array( $rT ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maT2, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $maT2, 1, 'ung', array( 'unc' => 'UNC-TH' ) );
teq( 'dựng được một lệnh ĐÃ CẤP TIỀN', 'ung', VHCP_DuAn::dot_cua( $maT2, 1 )['tt'] );
teq( '   và nó đang tính là đã chi', 48000000, (int) VHCP_DuAn::get_du_an( $maT2 )['daChiTU'] );

/* ── Ai KHÔNG thu hồi được ──────────────────────────────────────────────────────────────── */
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $maT2, 1, 'tra', array( 'lyDo' => 'Sai số liệu' ) );
t( '🔴 QUẢN LÝ không thu hồi được lệnh đã cấp tiền (đây không phải việc thường ngày)',
	empty( $x['success'] ), $x );
t( '   và câu chối chỉ đúng người làm được', isset( $x['error'] )
	&& false !== mb_strpos( $x['error'], 'chỉ Admin' ), $x );
vai( 'Kế toán cá nhân', 'KTCN' );
$x = VHCP_DuAn::dat_tt_dot( $maT2, 1, 'tra', array( 'lyDo' => 'Sai số liệu' ) );
t( '🔴 KẾ TOÁN cũng không — người giữ két không tự gỡ khoản mình vừa đưa', empty( $x['success'] ), $x );

/* ── Admin: bắt buộc có lý do ───────────────────────────────────────────────────────────── */
vai( 'Admin', 'KT' );
$x = VHCP_DuAn::dat_tt_dot( $maT2, 1, 'tra' );
t( '🔴 Admin thu hồi mà KHÔNG ghi lý do → chối (ba tháng sau không ai dựng lại được chuyện gì '
	. 'đã xảy ra)', empty( $x['success'] ), $x );
t( '   nói rõ là phải ghi lý do', isset( $x['error'] )
	&& false !== mb_strpos( $x['error'], 'lý do' ), $x );
teq( '   và lệnh KHÔNG bị đụng', 'ung', VHCP_DuAn::dot_cua( $maT2, 1 )['tt'] );

/* ── Đường đi đúng ──────────────────────────────────────────────────────────────────────── */
$x = VHCP_DuAn::dat_tt_dot( $maT2, 1, 'tra', array( 'lyDo' => 'Gõ nhầm dự toán, tiền chưa chuyển' ) );
t( '🔴 Admin ghi lý do → thu hồi được', ! empty( $x['success'] ), $x );
teq( '   lệnh về "tra"', 'tra', VHCP_DuAn::dot_cua( $maT2, 1 )['tt'] );
teq( '   lý do ghi vào lệnh', 'Gõ nhầm dự toán, tiền chưa chuyển', VHCP_DuAn::dot_cua( $maT2, 1 )['lyDo'] );
teq( '🔴 hạng mục về "tra" và GỠ số lệnh (không thì nhân viên gửi lại bị chối)',
	0, (int) VHCP_DuAn::hm_cua( $maT2, $rT )['dot'] );

/* 🔴 CHỐT VỀ TIỀN — đúng lựa chọn anh Thắng: "coi như chưa chi". */
$dT = VHCP_DuAn::get_du_an( $maT2 );
teq( '🔴 "đã chi" gỡ khoản ấy ra — coi như chưa chi', 0, (int) $dT['daChiTU'] );
t( '🔴 nhưng SỔ CỦA LỆNH vẫn giữ nguyên mấy lượt cấp tiền để tra (không xoá dấu vết)',
	1 === count( VHCP_DuAn::dot_cua( $maT2, 1 )['daCap'] )
	&& 48000000 == VHCP_DuAn::dot_cua( $maT2, 1 )['daCap'][0]['soTien'],
	VHCP_DuAn::dot_cua( $maT2, 1 )['daCap'] );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $maT2, array( $rT ), array(), '' );
t( '   sửa xong gửi lại được', ! empty( $x['success'] ), $x );

/* ── 🔴 CHỐI KHI ĐÃ QUYẾT TOÁN: gỡ tạm ứng dưới chân một khoản đã chốt sổ là 141 âm ────── */
vai( 'Admin', 'KT' );
$maQ2 = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian đã chốt', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maQ2, array( 'noiDung' => 'Thợ', 'thucTe' => 5000000, 'anh' => 'https://kho/b.jpg' ) );
$rQ = 0;
foreach ( VHCP_DuAn::get_du_an( $maQ2 )['lines'] as $l ) { if ( 'Thợ' === $l['noiDung'] ) { $rQ = (int) $l['row']; } }
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $maQ2, array( $rQ ), array(), '' );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $maQ2, 1, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $maQ2, 1, 'ung', array( 'unc' => 'UNC-Q' ) );
VHCP_DuAn::dat_hm( $maQ2, $rQ, 'xong' );
teq( 'dựng được hạng mục ĐÃ CHỐT XONG', 'xong', VHCP_DuAn::hm_cua( $maQ2, $rQ )['tt'] );
vai( 'Admin', 'KT' );
$x = VHCP_DuAn::dat_tt_dot( $maQ2, 1, 'tra', array( 'lyDo' => 'Muốn làm lại' ) );
t( '🔴 trong lệnh có hạng mục ĐÃ CHỐT XONG → chối, kể cả Admin', empty( $x['success'] ), $x );
t( '   và chỉ đúng đường: mở khoá hạng mục ấy trước',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Mở khoá' ), $x );
teq( '   lệnh vẫn nguyên', 'ung', VHCP_DuAn::dot_cua( $maQ2, 1 )['tt'] );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_hm( $maQ2, $rQ, 'nhap' );
vai( 'Admin', 'KT' );
$x = VHCP_DuAn::dat_tt_dot( $maQ2, 1, 'tra', array( 'lyDo' => 'Muốn làm lại' ) );
t( '   mở khoá xong thì thu hồi được', ! empty( $x['success'] ), $x );
foreach ( array( $maT2, $maQ2 ) as $z ) { VHCP_DuAn::delete( $z ); }

/* ═══ 9. CỬA API ═══════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
foreach ( array(
	"'xinTamUngDuAn'"        => "array( 'VHCP_DuAn', 'xin_tam_ung_dot' )",
	"'datTrangThaiLenhDuAn'" => "array( 'VHCP_DuAn', 'dat_tt_dot' )",
	"'listLenhDuAn'"         => "array( 'VHCP_DuAn', 'list_lenh_da' )",
) as $k => $v ) {
	t( '🔴 ' . $k . ' đã khai vào cửa API', false !== strpos( $src, $k ) && false !== strpos( $src, $v ) );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: nhiều hạng mục gộp thành một lệnh, một số tiền, một uỷ nhiệm chi.\n";
