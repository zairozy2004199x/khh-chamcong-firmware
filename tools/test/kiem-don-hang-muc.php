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
 * 🔴 XONG PHẢI CÓ HOÁ ĐƠN. Khoá mà chưa có chứng từ là chốt một con số không có gì đỡ.
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
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Mua đồ điện', 'duToan' => 10000000 ) );
$d = VHCP_DuAn::get_du_an( $ma );
$row = null;
foreach ( $d['lines'] as $l ) { if ( $l['noiDung'] === 'Mua đồ điện' ) { $row = $l['row']; } }
t( 'dựng được hạng mục thử', null !== $row, $d['lines'] );

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
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $row ), array(
	array( 'ngay' => '12/09/2026', 'soTien' => 4000000 ),
	array( 'ngay' => '20/09/2026', 'soTien' => 6000000 ),
	array( 'soTien' => 1000 ),   // thiếu ngày -> bỏ
) );
t( 'nhân viên gửi lệnh tạm ứng được', ! empty( $x['success'] ), $x );
teq( '   hạng mục sang "xin"', 'xin', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );
$L = VHCP_DuAn::dot_cua( $ma, 1 );
teq( '🔴 lịch đi nhận tiền giữ đủ hai đợt có ngày', 2, count( $L['lich'] ) );
teq( '   đánh số lần theo thứ tự', 1, $L['lich'][0]['lan'] );
teq( '   giữ đúng ngày hẹn', '20/09/2026', $L['lich'][1]['ngay'] );
t( '   và số tiền của đợt', 6000000 == $L['lich'][1]['soTien'], $L['lich'][1] );

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
 * 2. 🔴 XONG PHẢI CÓ HOÁ ĐƠN, VÀ XONG LÀ KHOÁ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$x = VHCP_DuAn::dat_hm( $ma, $row, 'xong' );
t( '🔴 chốt hoàn thành mà chưa có hoá đơn → CHỐI', empty( $x['success'] ), $x );
teq( '   và trạng thái không đổi', 'ung', VHCP_DuAn::hm_cua( $ma, $row )['tt'] );
$x = VHCP_DuAn::dat_hm( $ma, $row, 'xong', array( 'hoaDon' => 'hd-001.pdf' ) );
t( '🔴 chốt hoá đơn vẫn theo TỪNG HẠNG MỤC (mỗi hạng mục một hoá đơn riêng)', ! empty( $x['success'] ), $x );
t( '🔴 và hạng mục KHOÁ lại', VHCP_DuAn::hm_khoa( $ma, $row ) );

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
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Bóng đèn', 'capCha' => 'Mua đồ điện', 'thucTe' => 2000000 ) );
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

VHCP_DuAn::delete( $ma );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: mỗi hạng mục đi đúng đường của nó, và không ai nhảy qua vai người khác.\n";
