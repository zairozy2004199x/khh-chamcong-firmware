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
	VHCP_DuAn::add_line( $ma, array( 'noiDung' => $c[0], 'capCha' => 'Mua đồ điện', 'thucTe' => $c[1] ) );
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
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $R['Mua đồ điện'], $R['Thợ bốc vác'] ),
	array( array( 'ngay' => '12/09/2026', 'soTien' => 10000000 ), array( 'soTien' => 5 ) ), 'Vật tư đợt đầu' );
t( '🔴 tích hai hạng mục → gửi được MỘT lệnh', ! empty( $x['success'] ), $x );
teq( '   lệnh đánh số đợt 1', 1, $x['dot']['dot'] );
teq( '   gồm đúng 2 hạng mục', 2, $x['so'] );
t( '🔴 SỐ TIỀN CỦA LỆNH = 13.000.000 + 2.300.000 = 15.300.000',
	15300000 == $x['soTien'], $x['soTien'] );
teq( '   lệnh ở trạng thái chờ duyệt', 'xin', $x['dot']['tt'] );
teq( '🔴 lịch thiếu ngày bị bỏ (kế toán chuẩn bị tiền vào hôm nào?)', 1, count( $x['dot']['lich'] ) );
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
t( '🔴 đã xin = lệnh đang xin / đã duyệt / đã cấp — KHÔNG tính lệnh bị trả lại (25.999.999)',
	25999999 == $dd['daXinTU'], $dd['daXinTU'] );
t( '   nên nó KHÁC con số dự kiến — còn "Vật tư lẻ" chưa gửi',
	$dd['duKienTU'] != $dd['daXinTU'], array( $dd['duKienTU'], $dd['daXinTU'] ) );
t( '🔴 đã chi = CHỈ lệnh kế toán đã cấp tiền (15.300.000) — lệnh mới duyệt chưa tính',
	15300000 == $dd['daChiTU'], $dd['daChiTU'] );
t( '   ba con số không cái nào bằng cái nào (phép này bắt lỗi đổi chỗ)',
	$dd['duKienTU'] != $dd['daChiTU'] && $dd['daXinTU'] != $dd['daChiTU'], $dd );

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
