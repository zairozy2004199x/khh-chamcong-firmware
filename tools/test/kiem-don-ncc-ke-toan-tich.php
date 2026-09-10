<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƠN 🏢 KẾ TOÁN TRẢ THẲNG NHÀ CUNG CẤP — KẾ TOÁN TỰ TÍCH, KHÔNG QUA ĐƯỜNG TẠM ỨNG.
 *
 * Anh Thắng 10/09/2026: *"trong phần này dù không xin tạm ứng, nhưng vẫn có phần kế toán đã xác
 * nhận đi đơn nào thì tích vào và khóa đơn đó cho nhân viên biết và kè gửi ủy nhiệm chi cho đơn
 * đó thay vì nhân viên gửi (người gửi lỡ người kia quên)"*.
 *
 * =============================================================================================
 * 🔴 CHUỖI xin → duyệt → cấp KHÔNG ÁP ĐƯỢC Ở ĐÂY. Tiền của đơn này không qua tay nhân viên nên
 *    họ chẳng xin gì cả. Bắt đi qua chuỗi ấy là đơn nằm mãi ở "Đang nhập", còn kế toán thì đã
 *    trả tiền cho nhà cung cấp từ đời nào — hai bên cùng quên, đúng chỗ anh Thắng lo.
 *
 * 🔴 CHỈ NỚI CHO KẾ TOÁN. Nới cho mọi vai thì nhân viên tự khoá đơn của chính mình, mà khoá là
 *    chốt "đây là chi thực tế" — câu ấy phải do người giữ két nói.
 *
 * 🔴 HÌNH THỨC CHI ĐỌC TỪ SỔ, KHÔNG NHẬN TỪ MÀN. Nếu để màn gửi lên "đơn này là Trực tiếp" thì
 *    ai cũng gắn cờ ấy được rồi đi thẳng tới bước khoá, bỏ qua cả duyệt lẫn cấp tạm ứng.
 *
 * 🔴 UỶ NHIỆM CHI PHẢI GIỮ ĐƯỢC LÚC KHOÁ. Đơn NCC chỉ có MỘT bước; uỷ nhiệm chi chính là chứng
 *    từ của bước ấy. Chỉ nhận nó lúc "cấp tạm ứng" là đơn NCC không bao giờ ghi được.
 *
 * ⚠️ CHẠY THẬT trên dữ liệu thật, đổi vai qua từng bước.
 *
 * Chạy: php tools/test/kiem-don-ncc-ke-toan-tich.php
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
$r  = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian thử đơn NCC', 'NV' );
$ma = $r['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Xe ba gác', 'duToan' => 2000000, 'hinhThuc' => 'Trực tiếp' ) );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Vật tư', 'duToan' => 5000000 ) );
$d = VHCP_DuAn::get_du_an( $ma );
$ncc = null; $ung = null;
foreach ( $d['lines'] as $l ) {
	if ( 'Xe ba gác' === $l['noiDung'] ) { $ncc = $l['row']; }
	if ( 'Vật tư' === $l['noiDung'] ) { $ung = $l['row']; }
}
t( 'dựng được hai hạng mục thử', null !== $ncc && null !== $ung, $d['lines'] );

/* ═══ 1. NHẬN DIỆN ĐƠN NCC ═══════════════════════════════════════════════════════════════ */
teq( '🔴 đọc hình thức chi TỪ SỔ', 'Trực tiếp', VHCP_DuAn::hinh_thuc_hm( $ma, $ncc ) );
t( '   nhận ra đây là đơn kế toán trả thẳng NCC', VHCP_DuAn::hm_la_ncc( $ma, $ncc ) );
t( '🔴 và KHÔNG nhận nhầm đơn tạm ứng thành đơn NCC', ! VHCP_DuAn::hm_la_ncc( $ma, $ung ) );
t( '   dòng không có thật thì không nổ, và cũng không phải NCC', ! VHCP_DuAn::hm_la_ncc( $ma, 999 ) );

/* ═══ 2. 🔴 KẾ TOÁN TÍCH MỘT PHÁT: ĐÃ CHI + UỶ NHIỆM CHI + KHOÁ ══════════════════════════ */
vai( 'Kế toán NCC', 'KT NCC' );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'xong', array( 'hoaDon' => 'https://hd/xe', 'unc' => 'UNC-2026-88' ) );
t( '🔴 kế toán khoá thẳng đơn NCC từ "nhap", không cần ai xin/duyệt trước',
	! empty( $x['success'] ), $x );
$h = VHCP_DuAn::hm_cua( $ma, $ncc );
teq( '   đơn đã khoá', 'xong', $h['tt'] );
teq( '🔴 GIỮ ĐƯỢC uỷ nhiệm chi lúc khoá (đơn NCC chỉ có một bước, UNC là chứng từ của bước ấy)',
	'UNC-2026-88', $h['unc'] );
teq( '   và giữ hoá đơn', 'https://hd/xe', $h['hoaDon'] );
t( '   nhân viên nhìn bảng là biết đơn này đã khoá', VHCP_DuAn::hm_khoa( $ma, $ncc ) );

/* ═══ 3. 🔴 KHOÁ VẪN LÀ KHOÁ ════════════════════════════════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'xin' );
t( '🔴 nhân viên KHÔNG đụng được vào đơn NCC đã khoá', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'nhap' );
t( '🔴 nhân viên KHÔNG tự mở lại đơn đã khoá', empty( $x['success'] ), $x );
vai( 'Kế toán cá nhân', 'KT CN' );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'nhap' );
t( 'kế toán mở lại được', ! empty( $x['success'] ), $x );
teq( '   về lại "nhap"', 'nhap', VHCP_DuAn::hm_cua( $ma, $ncc )['tt'] );

/* ═══ 4. 🔴 NHÂN VIÊN KHÔNG TỰ KHOÁ ĐƠN CỦA MÌNH ═══════════════════════════════════════ */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'xong', array( 'hoaDon' => 'https://hd/gia' ) );
t( '🔴 nhân viên KHÔNG khoá được đơn NCC (khoá là chốt "chi thực tế" — việc của người giữ két)',
	empty( $x['success'] ), $x );
teq( '   đơn vẫn ở "nhap"', 'nhap', VHCP_DuAn::hm_cua( $ma, $ncc )['tt'] );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'ung', array( 'dot' => 1 ) );
t( '🔴 nhân viên cũng không tự cấp tạm ứng cho đơn NCC', empty( $x['success'] ), $x );

/* ═══ 5. 🔴 ĐƠN NCC KHÔNG ĐI ĐƯỜNG XIN TẠM ỨNG ═════════════════════════════════════════ */
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'xin' );
t( '🔴 nhân viên KHÔNG xin tạm ứng cho đơn NCC được (tiền không qua tay họ)',
	empty( $x['success'] ), $x );
t( '   và câu chối nói rõ vì sao', isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'trả thẳng nhà cung cấp' ), $x );
vai( 'Kế toán NCC', 'KT NCC' );
$x = VHCP_DuAn::dat_hm( $ma, $ncc, 'xin' );
t( '🔴 kế toán cũng KHÔNG đẩy đơn NCC vào chuỗi xin tạm ứng (nới cho họ là mở lại đúng chỗ vừa bịt)',
	empty( $x['success'] ), $x );

/* ═══ 6. 🔴 ĐƠN TẠM ỨNG VẪN PHẢI ĐI ĐỦ CHUỖI ══════════════════════════════════════════ */
$x = VHCP_DuAn::dat_hm( $ma, $ung, 'ung', array( 'dot' => 1 ) );
t( '🔴 đơn TẠM ỨNG: kế toán KHÔNG cấp tiền lẻ cho một hạng mục (lối tắt NCC không được rò sang đây)',
	empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_hm( $ma, $ung, 'duyet' );
t( '🔴 và không duyệt lẻ được', empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung' );
t( '🔴 lệnh chưa ai duyệt thì cũng không cấp tiền được', empty( $x['success'] ), $x );
/* 🔴 Đơn TẠM ỨNG nay đi theo LỆNH của cả dự án (anh Thắng: *"trong 1 đơn mà nhiều lệnh tạm
   ứng"*) — bài kiểm đường lệnh nằm ở `kiem-lenh-tam-ung-du-an.php`. Ở đây chỉ cần chốt một
   điều: LỐI TẮT CỦA ĐƠN NCC KHÔNG ĐƯỢC RÒ SANG ĐƠN TẠM ỨNG. */
vai( 'Nhân viên', 'NV' );
$x = VHCP_DuAn::dat_hm( $ma, $ung, 'xin' );
t( '🔴 đơn tạm ứng: xin LẺ từng hạng mục bị chặn (phải đi qua lệnh)', empty( $x['success'] ), $x );
$x = VHCP_DuAn::xin_tam_ung_dot( $ma, array( $ung ) );
t( 'đơn tạm ứng: gửi qua LỆNH thì được', ! empty( $x['success'] ), $x );
vai( 'Quản lý', 'QL' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
t( '   quản lý duyệt lệnh được', ! empty( $x['success'] ), $x );
vai( 'Kế toán cá nhân', 'KT CN' );
$x = VHCP_DuAn::dat_tt_dot( $ma, 1, 'ung', array( 'unc' => 'UNC-1' ) );
t( '   kế toán cấp lệnh được', ! empty( $x['success'] ), $x );
$x = VHCP_DuAn::dat_hm( $ma, $ung, 'xong', array() );
t( '🔴 đơn tạm ứng: chốt mà THIẾU HOÁ ĐƠN vẫn bị chối', empty( $x['success'] ), $x );

/* ═══ 7. 🔴 ĐƠN NCC CŨNG PHẢI CÓ HOÁ ĐƠN MỚI KHOÁ ═════════════════════════════════════ */
/* Hạng mục NCC MỚI TINH — chưa từng có hoá đơn nào. Dùng lại hạng mục cũ là phép này xanh
   oan: mở lại đơn không xoá hoá đơn đã đính, nên "thiếu hoá đơn" không còn thiếu nữa. */
vai( 'Admin', 'KT' );
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thuê cẩu', 'duToan' => 3000000, 'hinhThuc' => 'Trực tiếp' ) );
$d2 = VHCP_DuAn::get_du_an( $ma ); $ncc2 = null;
foreach ( $d2['lines'] as $l ) { if ( 'Thuê cẩu' === $l['noiDung'] ) { $ncc2 = $l['row']; } }
t( 'dựng được hạng mục NCC mới', null !== $ncc2, $d2['lines'] );
vai( 'Kế toán NCC', 'KT NCC' );
$x = VHCP_DuAn::dat_hm( $ma, $ncc2, 'xong', array( 'unc' => 'UNC-9' ) );
t( '🔴 đơn NCC: tích khoá mà chưa có hoá đơn → CHỐI (khoá một con số không có gì đỡ)',
	empty( $x['success'] ), $x );
teq( '   và đơn KHÔNG bị khoá nửa vời', 'nhap', VHCP_DuAn::hm_cua( $ma, $ncc2 )['tt'] );
t( '   uỷ nhiệm chi cũng không bị ghi lén khi lệnh đã bị chối',
	'' === VHCP_DuAn::hm_cua( $ma, $ncc2 )['unc'], VHCP_DuAn::hm_cua( $ma, $ncc2 ) );
$x = VHCP_DuAn::dat_hm( $ma, $ncc2, 'ung', array( 'dot' => 1, 'unc' => 'UNC-10' ) );
t( 'kế toán cũng đánh dấu "đã chi, chờ khoá" được cho đơn NCC', ! empty( $x['success'] ), $x );
teq( '   giữ uỷ nhiệm chi', 'UNC-10', VHCP_DuAn::hm_cua( $ma, $ncc2 )['unc'] );
$x = VHCP_DuAn::dat_hm( $ma, $ncc2, 'xong', array( 'hoaDon' => 'https://hd/cau' ) );
t( '   rồi khoá lại được, uỷ nhiệm chi vẫn còn nguyên',
	! empty( $x['success'] ) && 'UNC-10' === VHCP_DuAn::hm_cua( $ma, $ncc2 )['unc'], $x );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: đơn NCC kế toán tự tích khoá được, đơn tạm ứng vẫn đi đủ chuỗi.\n";
