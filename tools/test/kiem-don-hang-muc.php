<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỖI HẠNG MỤC LỚN LÀ MỘT "ĐƠN" CÓ ĐƯỜNG ĐI RIÊNG.
 *
 * Anh Thắng 10/09/2026: *"Nhân viên sẽ lên 10 đơn, xin tạm ứng, quản lý duyệt, kế toán gửi tạm
 * ứng và gán ủy nhiệm chi lần 1, nếu đơn nào chính xác và hoàn thành sẽ tích hoàn thành và bổ
 * sung hóa đơn nó sẽ khóa đơn đó lại và xác định đơn đó là chi thực tế"*, *"Sau nv lên tiếp 10
 * đơn, thấy cần nhiều tiền tích vào xin tạm ứng lần 2"*, *"kiểu gửi xin tạm ứng nhiều lần, hoặc
 * 1 lần, nếu 1 lần mà đi tạm ứng nhiều lần thì nv có thể lịch chọn ngày đi tạm ứng lần 1,2,3"*.
 *
 * =============================================================================================
 * 🔴 CHỐT THEO VAI, KHÔNG THEO NÚT TRÊN MÀN. Màn ẩn nút chỉ là tiện tay; ai gọi thẳng API vẫn
 *    phải bị chặn. Không có chốt ở đây thì nhân viên tự duyệt rồi tự cấp tạm ứng cho chính
 *    mình — và đó là tiền thật ra khỏi két.
 *
 * 🔴 KHOÁ LÀ KHOÁ THẬT. "Xong" nghĩa là đã có hoá đơn và đã chốt là chi thực tế; sửa được nữa
 *    thì con số kế toán đã hạch toán đổi sau lưng họ.
 *
 * 🔴 XONG PHẢI CÓ MỘT TRONG BA (đổi 18/09/2026): hoá đơn · chứng từ đã đính sẵn trên dòng (cột
 *    ẢNH / HỒ SƠ) · lời khai "không có hoá đơn". Anh Thắng: *"Chỗ ảnh là hóa đơn rồi mà, sao add
 *    rồi, bắt phải add lại"* và *"Với trường hợp không có hóa đơn, nên không ép phải có hóa đơn
 *    khi chốt"*. Trắng cả ba thì vẫn chối: khoá một con số không có gì đỡ, mà cũng không ai nhận
 *    là không có chứng từ.
 *
 * ⚠️ CHẠY THẬT trên dữ liệu thật, đổi vai qua từng bước.
 *
 * Chạy: php tools/test/kiem-don-hang-muc.php
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
$r = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thử đơn hạng mục', 'NV' );
$ma = $r['maDA'];
/* Có CẢ thực tế: từ 1.193.0 chốt hoàn thành đòi hạng mục phải có số tiền thật, không thì
   lệnh quyết toán của nó ra 0đ. Fixture cũ chỉ có dự toán — tức đúng cảnh chốt ấy chặn. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Mua đồ điện', 'duToan' => 10000000, 'thucTe' => 9500000 ) );
/* Hạng mục thứ hai để có gì mà XẾP sang đợt nhận tiền thứ hai — từ 1.197.0 mỗi đợt là một
   danh sách hàng, nên một dự án một hàng thì chỉ khai được một đợt. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thuê xe cẩu', 'duToan' => 6000000, 'thucTe' => 6000000 ) );
/* Khoản KHÔNG BAO GIỜ có hoá đơn — dựng sẵn từ đây để phần 2c chạy đúng luồng thật (xin → duyệt
   → cấp → chốt), khỏi thêm dòng vào giữa lúc dự án đang thi công. */
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Tiền ăn + xăng xe', 'duToan' => 1500000, 'thucTe' => 1500000 ) );
$d = VHCP_DuAn::get_du_an( $ma );
$row = null; $row2 = null; $row3 = null;
foreach ( $d['lines'] as $l ) {
	if ( $l['noiDung'] === 'Mua đồ điện' ) { $row  = $l['row']; }
	if ( $l['noiDung'] === 'Thuê xe cẩu' ) { $row2 = $l['row']; }
	if ( $l['noiDung'] === 'Tiền ăn + xăng xe' ) { $row3 = $l['row']; }
}
t( 'dựng được hạng mục thử', null !== $row && null !== $row2 && null !== $row3, $d['lines'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. ĐƯỜNG ĐI ĐÚNG — ĐI QUA LỆNH, KHÔNG ĐI LẺ TỪNG HẠNG MỤC
 *
 * 🔴 Anh Thắng: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng"* — xin / duyệt / cấp tiền
 *    nay là việc của CẢ LỆNH. Bài kiểm đường lệnh nằm ở `kiem-lenh-tam-ung-du-an.php`; tệp này
 *    lo phần CÒN LẠI của từng hạng mục: chốt hoá đơn, khoá, và mở lại.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'hạng mục mới → nháp', 'nhap', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );
t( '   dự án cũ (chưa có trạng thái nào) vẫn đọc ra "nhap", không nổ',
	'nhap' === VHCP_DuAn::hm_cua( $ma, 999 )['tt'] );

vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row, $row2 ), array(
	array( 'ngay' => '12/09/2026', 'rows' => array( $row ) ),
	array( 'ngay' => '20/09/2026', 'rows' => array( $row2 ) ),
	array( 'rows' => array() ),   // thiếu ngày -> bỏ
) );
t( 'nhân viên gửi lệnh tạm ứng được', ! empty( $x['success'] ), $x );
teq( '   hạng mục sang "xin"', 'xin', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );
$L = VHCP_DuAn::dot_cua( $ma, 1 );
teq( '🔴 lịch đi nhận tiền giữ đủ hai đợt có ngày', 2, count( $L['lich'] ) );
teq( '   đánh số lần theo thứ tự', 1, $L['lich'][0]['lan'] );
teq( '   giữ đúng ngày hẹn', '20/09/2026', $L['lich'][1]['ngay'] );
/* 🔴 SỐ TIỀN CỦA ĐỢT DO MÁY CHỦ CỘNG, từ chính mấy hàng được xếp vào đợt ấy — màn không gửi
   con số nào lên. Gõ tay được là gõ cho lệch được, và lệch ở đây là kế toán chuẩn bị sai tiền. */
t( '🔴 số tiền của đợt = tổng các hàng xếp vào nó (Thuê xe cẩu 6.000.000)',
	6000000 == $L['lich'][1]['soTien'], $L['lich'][1] );
teq( '   và đợt giữ lại DANH SÁCH HÀNG để về sau đọc ra "đợt này gồm những gì"',
	array( $row2 ), $L['lich'][1]['rows'] );

vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
t( 'quản lý duyệt được', ! empty( $x['success'] ), $x );

vai( 'Kế toán cá nhân', 'KTCN' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-001' ) );
t( 'kế toán cấp tạm ứng được', ! empty( $x['success'] ), $x );
$h = VHCP_DuAn::hm_cua( $ma, $row );
teq( '   hạng mục ghi đúng đợt', 1, $h['dot'] );
teq( '   và giữ mã uỷ nhiệm chi', 'UNC-001', $h['unc'] );
t( '   có mốc thời gian từng bước để tra', ! empty( $h['moc']['ung'] ), $h['moc'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 XONG PHẢI CÓ CHỨNG TỪ — MỘT TRONG BA — VÀ XONG LÀ KHOÁ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_hm( $ma, $row, 'xong' );
t( '🔴 chốt mà TRẮNG CẢ BA (không hoá đơn, không ảnh/hồ sơ, không khai) → CHỐI', empty( $x['success'] ), $x );
t( '   và câu chối chỉ đúng hai đường ra: đính chứng từ, hoặc tích "Không có hoá đơn"',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'Không có hoá đơn' ), $x );
teq( '   trạng thái không đổi', 'ung', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );
$x = VHCP_DuAn::dat_hm( $ma, $row, 'xong', array( 'hoaDon' => 'hd-001.pdf' ) );
t( '🔴 chốt hoá đơn vẫn theo TỪNG HẠNG MỤC (mỗi hạng mục một hoá đơn riêng)', ! empty( $x['success'] ), $x );
t( '🔴 và hạng mục KHOÁ lại', VHCP_DuAn::hm_khoa( $ma, $row ) );
teq( '   có hoá đơn thì KHÔNG mang dấu "không hoá đơn"', 0, VHCP_DuAn::hm_cua( $ma, $row )['khongHD'] );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row ) );
t( '🔴 đã khoá → nhân viên không xin lại được', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_hm( $ma, $row, 'nhap' );
t( '🔴 và cũng KHÔNG tự mở khoá được', empty( $x['success'] ), $x );
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_hm( $ma, $row, 'nhap' );
t( '   quản lý cũng không (chốt là việc của kế toán)', empty( $x['success'] ), $x );
vai( 'Kế toán cá nhân', 'KTCN' );
$x = VHCP_DuAn::dat_hm( $ma, $row, 'nhap' );
t( 'kế toán mở lại được', ! empty( $x['success'] ), $x );
teq( '   và đợt được dọn về 0', 0, VHCP_DuAn::hm_cua( $ma, $row )['dot'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 CHỐT THEO VAI — AI GỌI THẲNG API CŨNG BỊ CHẶN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row ) );
$x = VHCP_DuAn::dat_tt_dot( $ma, 2, 'duyet' );
t( '🔴 nhân viên KHÔNG tự duyệt được lệnh của mình', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_dot( $ma, 2, 'tra' );
t( '   và không tự trả lại được', empty( $x['success'] ), $x );
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, 2, 'duyet' );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 2, 'ung' );
t( '🔴 nhân viên KHÔNG tự cấp tạm ứng cho mình (tiền thật ra khỏi két)', empty( $x['success'] ), $x );
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 2, 'ung' );
t( '   quản lý cũng không — cấp tiền là việc của kế toán', empty( $x['success'] ), $x );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. KHÔNG NHẢY CÓC BƯỚC
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $ma, 2, 'tra' );
teq( 'trả lại → hạng mục về "tra"', 'tra', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );
$x = VHCP_DuAn::dat_tt_dot( $ma, 2, 'ung' );
t( '🔴 lệnh đã bị trả mà cấp tạm ứng → CHỐI', empty( $x['success'] ), $x );
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row ) );
t( 'bị trả lại thì gửi lại được (thành lệnh đợt 3)', ! empty( $x['success'] ) && 3 === $x['dot']['dot'], $x );
vai( 'Kế toán cá nhân', 'KTCN' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 3, 'lung tung' );
t( 'trạng thái lạ → chối', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_dot( $ma, 99, 'duyet' );
t( 'lệnh không có thật → chối, không nổ', empty( $x['success'] ), $x );
$x = VHCP_DuAn::xin_tam_ung_dot( 'DA-KHONG-CO', array( 1 ) );
t( 'dự án không có thật → chối', empty( $x['success'] ), $x );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. TRẢ XUỐNG MÀN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
VHCP_DuAn::them_dong_muc_con_cu( $ma, array( 'noiDung' => 'Bóng đèn', 'capCha' => 'Mua đồ điện', 'thucTe' => 2000000 ) );
$d = VHCP_DuAn::get_du_an( $ma );
$cha = null; $con = null;
foreach ( $d['lines'] as $l ) {
	if ( $l['noiDung'] === 'Mua đồ điện' ) { $cha = $l; }
	if ( $l['noiDung'] === 'Bóng đèn' )    { $con = $l; }
}
t( '🔴 hạng mục lớn mang trạng thái xuống màn', isset( $cha['hm']['tt'] ), $cha );
t( '🔴 mục con KHÔNG mang trạng thái riêng (nó đi theo cha)', ! isset( $con['hm'] ), $con );
t( "   API 'datTrangThaiHangMuc' đã khai",
	false !== strpos( file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' ), "'datTrangThaiHangMuc'" ) );
t( '🔴 và lệnh của dự án xuống được tới màn (không thì nhân viên không thấy đợt nào tới đâu)',
	isset( $d['lenh'] ) && is_array( $d['lenh'] ) && count( $d['lenh'] ) >= 1, isset( $d['lenh'] ) ? $d['lenh'] : null );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2b. 🔴 ẢNH BILL ĐÃ ĐÍNH TRÊN DÒNG LÀ CHỨNG TỪ RỒI — 18/09/2026.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng, nhìn hàng đã có ảnh ở cột ẢNH mà bảng chốt vẫn đòi "Hoá đơn (bắt buộc)": *"Chỗ ảnh
 * là hóa đơn rồi mà, sao add rồi, bắt phải add lại"*.
 *
 * ⚠️ DÙNG HẠNG MỤC KHÁC ($row2), KHÔNG DÙNG LẠI $row: hạng mục kia đã có `hoaDon` trong sổ, nên
 *    phép này sẽ xanh oan — nó đo đúng cái ô mà ta đang muốn chứng minh là KHÔNG cần tới.
 * ⚠️ Và mở khoá bằng vai KẾ TOÁN, vì đơn đã khoá thì nhân viên không mở được.
 */
vai( 'Kế toán cá nhân', 'KTCN' );
teq( '   $row2 đang ở "đã cấp" (cùng lệnh 1 với $row)', 'ung', VHCP_DuAn::hm_cua( $ma, $row2 )['tt'] );
$x = VHCP_DuAn::dat_hm( $ma, $row2, 'xong' );
t( 'hạng mục chưa có gì → vẫn chối (mốc so sánh cho phép dưới)', empty( $x['success'] ), $x );
t( 'đính ẢNH BILL ngay trên dòng', ! empty( VHCP_DuAn::dat_anh_line( $ma, $row2, 'https://kho/bill-xe-cau.jpg' )['success'] ) );
t( '🔴 hm_co_tep() thấy ảnh trên dòng', VHCP_DuAn::hm_co_tep( $ma, $row2 ) );
$x = VHCP_DuAn::dat_hm( $ma, $row2, 'xong' );
t( '🔴 có ảnh bill trên dòng → CHỐT ĐƯỢC, không đòi đính lại hoá đơn', ! empty( $x['success'] ), $x );
$h2 = VHCP_DuAn::hm_cua( $ma, $row2 );
teq( '   ô hoá đơn vẫn trống (ảnh nằm ở cột ẢNH của dòng, không nhân bản sang đây)', '', $h2['hoaDon'] );
teq( '   và KHÔNG mang dấu "không hoá đơn" — chứng từ có thật', 0, $h2['khongHD'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2c. 🔴 KHOẢN KHÔNG CÓ HOÁ ĐƠN — CHỐT ĐƯỢC, NHƯNG SỔ GHI LẠI LÀ KHÔNG CÓ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng: *"Với trường hợp không có hóa đơn, nên không ép phải có hóa đơn khi chốt"* — thợ
 * phụ, tiền ăn + xăng xe, khách sạn lẻ thì không có hoá đơn nào để đính, mà ép thì đơn nằm treo
 * và khoản tạm ứng không bao giờ tất toán khỏi TK 141.
 *
 * 🔴 NHƯNG PHẢI CÒN DẤU. Khoá không chứng từ mà nhìn y hệt khoá có hoá đơn thì lúc soát sổ không
 *    lọc ra được khoản nào đang trắng chứng từ.
 */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row3 ) );
t( 'gửi lệnh tạm ứng cho khoản không hoá đơn', ! empty( $x['success'] ), $x );
$dot3 = VHCP_DuAn::hm_cua( $ma, $row3 )['dot'];
vai( 'Quản lý', 'QL' );
VHCP_DuAn::dat_tt_dot( $ma, $dot3, 'duyet' );
vai( 'Kế toán cá nhân', 'KTCN' );
VHCP_DuAn::dat_tt_dot( $ma, $dot3, 'ung' );
teq( '   hạng mục đã được cấp tạm ứng', 'ung', VHCP_DuAn::hm_cua( $ma, $row3 )['tt'] );
$x = VHCP_DuAn::dat_hm( $ma, $row3, 'xong', array( 'khongHD' => 1 ) );
t( '🔴 khai "không có hoá đơn" → CHỐT ĐƯỢC', ! empty( $x['success'] ), $x );
$h3 = VHCP_DuAn::hm_cua( $ma, $row3 );
teq( '🔴 và sổ GIỮ DẤU để kế toán soát', 1, $h3['khongHD'] );
t( '   hạng mục vẫn KHOÁ như mọi lượt chốt khác', VHCP_DuAn::hm_khoa( $ma, $row3 ) );
/* Mở lại rồi đính bù hoá đơn → dấu phải TỰ TẮT, không ở lại vu oan cho một khoản đã có chứng từ. */
$x = VHCP_DuAn::dat_hm( $ma, $row3, 'nhap' );
t( 'kế toán mở lại được', ! empty( $x['success'] ), $x );
teq( '   mở lại là xoá luôn dấu "không hoá đơn"', 0, VHCP_DuAn::hm_cua( $ma, $row3 )['khongHD'] );
VHCP_DuAn::dat_hm( $ma, $row3, 'xong', array( 'hoaDon' => 'hd-an-xang.pdf', 'khongHD' => 1 ) );
$h3 = VHCP_DuAn::hm_cua( $ma, $row3 );
teq( '🔴 tích ô rồi vẫn đính hoá đơn → KHÔNG đóng dấu oan', 0, $h3['khongHD'] );
teq( '   và hoá đơn vào sổ', 'hd-an-xang.pdf', $h3['hoaDon'] );

/* 🔴 Ô HOÁ ĐƠN TRỐNG LÚC CHỐT LẠI KHÔNG ĐƯỢC XOÁ HOÁ ĐƠN ĐÃ CÓ. Bảng chốt mở ra với ô trống, nên
   lượt "mở lại → chốt lại" gửi lên đúng một chuỗi rỗng; ghi thẳng vào sổ là xoá sạch chứng từ đã
   đính, không một dòng báo. */
VHCP_DuAn::dat_hm( $ma, $row3, 'nhap' );
VHCP_DuAn::dat_hm( $ma, $row3, 'xong', array( 'hoaDon' => '' ) );
$h3 = VHCP_DuAn::hm_cua( $ma, $row3 );
teq( '🔴 chốt lại với ô hoá đơn TRỐNG → giữ nguyên hoá đơn cũ', 'hd-an-xang.pdf', $h3['hoaDon'] );
teq( '   và vẫn chốt được (chứng từ cũ vẫn tính là chứng từ)', 'xong', $h3['tt'] );

VHCP_DuAn::delete( $ma );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: mỗi hạng mục đi đúng đường của nó, và không ai nhảy qua vai người khác.\n";
