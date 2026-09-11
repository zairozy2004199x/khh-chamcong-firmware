<?php
/**
 * KHAI CƠ SỞ CHO ĐƠN VỊ MỚI LÀ ĐƠN VỊ ẤY PHẢI HIỆN RA NGAY.
 *
 * Anh Thắng 08/09/2026, sau khi màn Cấu hình đã bày hẳn khối "🏢 ĐƠN VỊ POSH · 1 cơ sở" với
 * dòng POSH HCM: *"đơn vị posh chưa có"* — hộp tích "Xem đơn vị" ở bảng Người dùng vẫn trơ
 * mỗi một dòng K&H.
 *
 * =============================================================================================
 * 🔴 VÒNG LUẨN QUẨN. `VHCP_DonVi::ds()` trước chỉ nhìn hai nguồn, mà cả hai đều là thứ có SAU:
 *      · cột "Đơn vị" của bảng NGƯỜI DÙNG — muốn khai được thì POSH phải có sẵn trong ô chọn
 *      · cột `don_vi` trên ĐƠN — muốn có đơn POSH thì phải có người POSH lập
 *    Muốn có người POSH thì phải tích "Xem đơn vị POSH"; muốn tích thì POSH phải nằm trong
 *    danh sách; muốn nằm trong danh sách thì phải có người POSH. Khoá chặt — không cách nào
 *    mở được đơn vị thứ hai, kể cả Admin.
 *
 * 🔴 NƠI KHAI PHẢI LÀ NƠI LIỆT KÊ. `cua_coso()` đã lấy DANH MỤC CƠ SỞ làm chốt duy nhất cho
 *    câu "dòng chi này của bên nào". Ranh giới vạch ở đó, thì danh sách ranh giới cũng phải
 *    đọc từ đó — không thì khai xong vẫn y như chưa khai, và không câu lỗi nào.
 *
 * ⚠️ CHẠY THẬT: khai cơ sở, rồi đòi đúng những gì màn hình phải bày ra.
 *
 * Chạy: php tools/test/kiem-don-vi-moi-hien-ra.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
global $wpdb;

/* Cột bảng cơ sở: Cơ sở · Mã đơn vị · Phân loại lớn · Tên MISA · Đóng cửa · ĐƠN VỊ */
function khai_coso( $rows ) {
	VHCP_Cfg::write( VHCP_Cfg::COSO, $rows );
	VHCP_Cfg::clear_cache();
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. SÂN SẠCH — CHƯA KHAI GÌ THÌ CHỈ CÓ NHÀ MẶC ĐỊNH
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
khai_coso( array(
	array( 'FARM PHAN THIẾT', '', '', '', '', '' ),   // ô Đơn vị bỏ trống = K&H
) );
teq( 'chưa khai đơn vị nào → chỉ có nhà mặc định', array( VHCP_DonVi::MAC_DINH ), VHCP_DonVi::ds() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 KHAI MỘT CƠ SỞ CHO POSH — POSH PHẢI HIỆN RA NGAY
 *
 * Không có người nào mang đơn vị POSH, không có đơn nào mang POSH. Đúng tình huống của anh
 * Thắng: vừa khai xong dòng cơ sở đầu tiên, chưa kịp làm gì thêm.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
khai_coso( array(
	array( 'FARM PHAN THIẾT', '', '', '', '', '' ),
	array( 'POSH HCM',        '', '', '', '', 'POSH' ),
) );
$ds = VHCP_DonVi::ds();
t( '🔴 khai cơ sở POSH xong → POSH có trong danh sách đơn vị', in_array( 'POSH', $ds, true ), $ds );
t( 'và K&H vẫn còn nguyên', in_array( VHCP_DonVi::MAC_DINH, $ds, true ), $ds );
teq( 'đúng hai đơn vị, không sinh thêm dòng rỗng', 2, count( $ds ) );

/* Và chốt "dòng chi này của bên nào" phải nói cùng một câu — hai bên lệch nhau thì ô tích bày
   ra một đơn vị mà không dòng tiền nào thuộc về nó. */
teq( 'cơ sở POSH thuộc POSH',          'POSH',                'POSH' === VHCP_DonVi::cua_coso( 'POSH HCM' ) ? 'POSH' : VHCP_DonVi::cua_coso( 'POSH HCM' ) );
teq( 'cơ sở bỏ trống ô Đơn vị → K&H',  VHCP_DonVi::MAC_DINH,  VHCP_DonVi::cua_coso( 'FARM PHAN THIẾT' ) );
teq( '🔴 mọi đơn vị `cua_coso()` trả về đều nằm trong `ds()` · POSH', true, in_array( VHCP_DonVi::cua_coso( 'POSH HCM' ), $ds, true ) );
teq( '   · K&H', true, in_array( VHCP_DonVi::cua_coso( 'FARM PHAN THIẾT' ), $ds, true ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. HỘP TÍCH "XEM ĐƠN VỊ" TRÊN MÀN — ĐI QUA `get_bootstrap()`
 *
 * `ds()` đúng mà boot không gửi xuống thì màn vẫn trơ một dòng K&H, và anh Thắng vẫn thấy y
 * như cũ. Phải đòi tận nơi màn hình đọc.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin', '' );
$b = VHCP_Don::get_bootstrap();
t( '🔴 boot gửi xuống danh sách đơn vị',            isset( $b['donVi'] ), array_keys( $b ) );
t( '🔴 và trong đó CÓ POSH — hộp tích bày được',    in_array( 'POSH', (array) $b['donVi'], true ), $b['donVi'] );
t( 'kèm K&H',                                       in_array( VHCP_DonVi::MAC_DINH, (array) $b['donVi'], true ), $b['donVi'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. VÒNG LUẨN QUẨN ĐÃ MỞ — TÍCH ĐƯỢC, VÀ TÍCH XONG THÌ THẤY THẬT
 *
 * Đây mới là thứ anh Thắng cần: khai cơ sở → tích được cho một tài khoản → tài khoản ấy đọc
 * được đơn của bên POSH. Đứt một mắt nào trong chuỗi thì bản vá này vô nghĩa.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$wpdb->insert( VHCP_DB::t( 'don' ), array(
	'ma_don' => 'D_POSH', 'ky' => 'T9', 'trang_thai' => 'Chờ quyết toán',
	'nguoi_lap' => 'NV_POSH', 'don_vi' => 'POSH' ) );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
	'id' => 'CP1', 'ma_don' => 'D_POSH', 'coso' => 'POSH HCM', 'nhom' => 'Chi khác', 'thanh_tien' => 1000 ) );
$wpdb->insert( VHCP_DB::t( 'don' ), array(
	'ma_don' => 'D_KH', 'ky' => 'T9', 'trang_thai' => 'Chờ quyết toán',
	'nguoi_lap' => 'NV_KH', 'don_vi' => VHCP_DonVi::MAC_DINH ) );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
	'id' => 'CP2', 'ma_don' => 'D_KH', 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi khác', 'thanh_tien' => 1000 ) );

/* Cột bảng người dùng: Tên · PIN · Vai trò · Cơ sở · TK Có · Mã ĐT · Bộ phận · Đơn vị · XEM ĐƠN VỊ */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'KT POSH', '1111', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'KT KH',   '2222', 'Kế toán cá nhân', '', '', '', '', '',     VHCP_DonVi::MAC_DINH ),
) );
VHCP_Cfg::clear_cache();

/* 🔴 Ô "Xem đơn vị" vừa khai KHÔNG được bị coi là khai lạc. `ai_khai_lac()` đối chiếu với
   chính `ds()`; nếu `ds()` không thấy POSH thì màn dựng hẳn một dải cảnh báo đỏ nói người khai
   đúng là đang khai sai. */
teq( '🔴 khai "Xem đơn vị: POSH" KHÔNG bị báo là khai lạc', array(), VHCP_DonVi::ai_khai_lac() );

function don_mas() {
	$a = array();
	foreach ( VHCP_Don::list_dons() as $x ) { $a[] = (string) $x['maDon']; }
	sort( $a );
	return $a;
}
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT POSH', '' );
teq( '🔴 kế toán được tích POSH chỉ thấy đơn POSH', array( 'D_POSH' ), don_mas() );
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT KH', '' );
teq( '🔴 kế toán bên K&H chỉ thấy đơn K&H — hai bên không nhìn thấy nhau', array( 'D_KH' ), don_mas() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. GỠ CƠ SỞ POSH ĐI THÌ POSH VẪN CÒN — VÌ ĐƠN CŨ VẪN MANG NÓ
 *
 * ⚠️ Đây KHÔNG phải rác. Đơn vị biến mất khỏi danh sách trong khi vẫn còn đơn mang tên nó là
 *    mọi ô lọc dựng từ danh sách không bao giờ chạm tới mấy đơn ấy — tiền có thật mà không ai
 *    lọc ra được. Ba nguồn cộng lại chính là để tránh chuyện đó.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
khai_coso( array(
	array( 'FARM PHAN THIẾT', '', '', '', '', '' ),
) );
$ds2 = VHCP_DonVi::ds();
t( '🔴 gỡ cơ sở khỏi danh mục, POSH VẪN còn (đơn cũ và người cũ vẫn mang nó)',
	in_array( 'POSH', $ds2, true ), $ds2 );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5b. BA NGUỒN, MỖI NGUỒN PHẢI TỰ ĐỨNG ĐƯỢC MỘT MÌNH
 *
 * 🔴 Bản trên §5 có cả ba nguồn cùng mang POSH, nên bỏ bớt một nguồn vẫn xanh — bài kiểm hoá
 *    ra chỉ đang canh phép cộng chứ không canh từng số hạng. Tách hẳn ra, mỗi lần chỉ để lại
 *    ĐÚNG MỘT nguồn mang tên đơn vị ấy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function chi_con( $coso_rows, $user_rows, $don_vi_tren_don ) {
	global $wpdb;
	VHCP_Cfg::write( VHCP_Cfg::USER, $user_rows );
	khai_coso( $coso_rows );
	$wpdb->query( 'DELETE FROM ' . VHCP_DB::t( 'don' ) );
	if ( '' !== $don_vi_tren_don ) {
		$wpdb->insert( VHCP_DB::t( 'don' ), array(
			'ma_don' => 'D_X', 'ky' => 'T9', 'trang_thai' => 'Nháp',
			'nguoi_lap' => 'ai đó', 'don_vi' => $don_vi_tren_don ) );
	}
	VHCP_Cfg::clear_cache();
	return VHCP_DonVi::ds();
}
$KH = VHCP_DonVi::MAC_DINH;

/* Nguồn 1 — CHỈ danh mục cơ sở. Không người nào, không đơn nào mang POSH. */
$n1 = chi_con( array( array( 'POSH HCM', '', '', '', '', 'POSH' ) ), array(), '' );
t( '🔴 nguồn 1 đứng một mình: chỉ danh mục cơ sở mang POSH', in_array( 'POSH', $n1, true ), $n1 );

/* Nguồn 2 — CHỈ bảng người dùng. Danh mục cơ sở sạch, không đơn nào. Đây là đường cũ, phải
   giữ: một tài khoản đã khai nhà là POSH thì POSH có thật, bất kể danh mục. */
$n2 = chi_con( array( array( 'FARM PHAN THIẾT', '', '', '', '', '' ) ),
	array( array( 'Ai Đó', '9999', 'Nhân viên', '', '', '', '', 'POSH', '' ) ), '' );
t( '🔴 nguồn 2 đứng một mình: chỉ bảng người dùng mang POSH', in_array( 'POSH', $n2, true ), $n2 );

/* Nguồn 3 — CHỈ cột `don_vi` trên đơn. Danh mục sạch, không tài khoản nào. Đây là tình huống
   "đơn vị cũ, đã gỡ hết cơ sở và người, nhưng đơn thì còn" — bỏ nguồn này là mấy đơn ấy tuột
   khỏi mọi ô lọc. */
$n3 = chi_con( array( array( 'FARM PHAN THIẾT', '', '', '', '', '' ) ), array(), 'POSH' );
t( '🔴 nguồn 3 đứng một mình: chỉ đơn cũ còn mang POSH', in_array( 'POSH', $n3, true ), $n3 );

/* Nhà mặc định phải có kể cả khi KHÔNG NGUỒN NÀO nhắc tới nó. Kho mới tinh, chưa khai cơ sở,
   chưa có tài khoản, chưa có đơn — mà mất K&H thì mọi dòng cũ (ô Đơn vị trống) rơi vào một
   đơn vị không có trong danh sách, và không ô lọc nào chạm tới được. */
$n0 = chi_con( array(), array(), '' );
teq( '🔴 kho trắng tinh vẫn có nhà mặc định', array( $KH ), $n0 );

/* ⚠️ ĐỐI CHỨNG CHO MỘT ĐỘT BIẾN TƯƠNG ĐƯƠNG. Hạt giống `MAC_DINH` trong `ds()` đục đi mà bộ
   thử vẫn xanh — vì `get_users()` LUÔN gieo lại tài khoản Admin với ô Đơn vị trống, và ô trống
   thì về đúng nhà mặc định. Tức hạt giống ấy đang được cứu bởi một hàm khác.

   Không ép cho đỏ (ghim vào cách viết chứ không ghim vào hành vi). Thay vào đó canh CHÍNH cái
   giả định: ngày nào `get_users()` thôi gieo, phép này đỏ ở đúng chỗ gãy, chứ không phải K&H
   lặng lẽ biến mất khỏi mọi ô lọc. */
$u0 = VHCP_Cfg::get_users();
t( '⚠️ đối chứng · xoá sạch bảng người dùng thì Admin vẫn được gieo lại', count( $u0 ) >= 1, count( $u0 ) );
$trong = false;
foreach ( $u0 as $x ) { if ( '' === trim( (string) ( isset( $x['donVi'] ) ? $x['donVi'] : '' ) ) ) { $trong = true; } }
t( '⚠️ đối chứng · và tài khoản gieo lại ấy để trống ô Đơn vị', $trong, $u0 );
teq( '⚠️ đối chứng · ô Đơn vị trống về đúng nhà mặc định', $KH, VHCP_DonVi::chuan( '' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. VIỆC ĐANG CHẠY KHÔNG ĐƯỢC ĐỘNG VÀO
 *
 * Anh Thắng 08/09/2026: *"nhớ đừng can thiệp gì bên phần chi phí khu vui chơi"*. Bản này chỉ
 * NỚI danh sách ra, không được đổi nghĩa của gì cả.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::USER, array() );
khai_coso( array( array( 'FARM PHAN THIẾT', '', '', '', '', '' ) ) );
$wpdb->query( 'DELETE FROM ' . VHCP_DB::t( 'don' ) );
$wpdb->query( 'DELETE FROM ' . VHCP_DB::t( 'chiphi' ) );
teq( 'kho chỉ toàn cơ sở K&H → danh sách vẫn đúng một đơn vị',
	array( VHCP_DonVi::MAC_DINH ), VHCP_DonVi::ds() );
foreach ( array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC' ) as $v ) {
	VHCP_Auth::dat_vai_tro( $v, 'X', '' );
	teq( 'vai "' . $v . '" vẫn xem cả (không bó đơn vị)', null, VHCP_DonVi::xem_duoc() );
}
/* Ô Đơn vị bỏ trống vẫn là K&H — mọi cơ sở khai trước bản này đều rỗng. */
teq( '🔴 ô Đơn vị bỏ trống vẫn về nhà mặc định', VHCP_DonVi::MAC_DINH, VHCP_DonVi::cua_coso( 'FARM PHAN THIẾT' ) );
teq( 'cơ sở lạ (gõ tay, đã xoá khỏi danh mục) cũng về nhà mặc định',
	VHCP_DonVi::MAC_DINH, VHCP_DonVi::cua_coso( 'GIAN NÀO ĐÓ KHÔNG CÓ THẬT' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. CÂU BÁO LỖI PHẢI CHỈ ĐÚNG CHỖ KHAI
 *
 * 🔴 Chỉ sai chỗ là người ta đi khai ở bảng Người dùng, khai xong vẫn nhận đúng câu lỗi ấy —
 *    đúng cái vòng luẩn quẩn mà bản này vừa mở.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
if ( preg_match( '/Chưa có đơn vị[^;]{0,200}?;/u', $src, $m ) ) {
	t( '🔴 câu lỗi chỉ vào DANH MỤC CƠ SỞ', false !== mb_strpos( $m[0], 'cơ sở' ), $m[0] );
	t( '   và không chỉ nhầm sang bảng Người dùng', false === mb_strpos( $m[0], 'Người dùng' ), $m[0] );
} else {
	t( 'bốc được câu lỗi "Chưa có đơn vị"', false );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 8. BA ĐƠN VỊ CÙNG LÚC — anh Thắng 11/09/2026: *"tách anh thêm 1 đơn vị KVC đi"*
 *
 * 🔴 HAI ĐƠN VỊ CHẠY ĐƯỢC KHÔNG CÓ NGHĨA LÀ BA CŨNG THẾ. Mọi phép tách trước đây đều là
 *    "của tôi vs của bên kia" — đúng cả khi mã lẫn lộn "không phải K&H" với "là POSH". Có đơn
 *    vị thứ ba thì hai câu ấy khác nhau, và chỗ nào lẫn sẽ để kế toán KVC đọc được sổ POSH.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* Dựng đủ BA đơn vị: K&H (mặc định) · POSH · KVC. */
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'POSH Gò Vấp', 'POSHGV', 'POSH MN', 'POSH Go Vap', '', 'POSH' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'KVC Aeon Tân Phú', 'KVCATP', 'KVC MN', 'KVC Aeon Tan Phu', '', 'KVC' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'KT Khu Vui Chơi', '1357', 'Kế toán cá nhân', '', '', '', 'Cơ sở', 'KVC', 'KVC' ) );
VHCP_Cfg::clear_cache();

$ds3 = VHCP_DonVi::ds();
t( '🔴 khai cơ sở cho KVC là KVC hiện ra ngay, không phải sửa mã', in_array( 'KVC', $ds3, true ), $ds3 );
t( '   và KHÔNG đá K&H hay POSH ra khỏi danh sách',
	in_array( 'K&H', $ds3, true ) && in_array( 'POSH', $ds3, true ), $ds3 );
teq( '   cơ sở KVC tra ra đúng đơn vị KVC', 'KVC', VHCP_DonVi::cua_coso( 'KVC Aeon Tân Phú' ) );

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT Khu Vui Chơi' );
teq( '🔴 kế toán KVC đọc được KVC', true, VHCP_DonVi::duoc_xem( 'KVC' ) );
teq( '🔴 nhưng KHÔNG đọc được K&H', false, VHCP_DonVi::duoc_xem( 'K&H' ) );
teq( '🔴 và KHÔNG đọc được POSH — "không phải nhà mình" ≠ "là POSH"',
	false, VHCP_DonVi::duoc_xem( 'POSH' ) );

teq( '   KVC lên đơn theo TUẦN, một đơn một cơ sở (không phải kiểu POSH)',
	false, VHCP_DonVi::nhieu_coso( 'KVC' ) );
teq( '   POSH vẫn là một đơn nhiều cơ sở như cũ', true, VHCP_DonVi::nhieu_coso( 'POSH' ) );

/* 🔴 NHÃN TRÊN MÀN KHÔNG ĐƯỢC LIỆT KÊ CỨNG TÊN ĐƠN VỊ. Nhãn cũ ghi "(K&H · POSH)"; khai thêm
   đơn vị thứ ba là nhãn ấy nói sai ngay, mà chẳng ai nhớ ra để sửa. */
$app = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 nhãn ô Đơn vị không liệt kê cứng "K&H · POSH"',
	false === mb_strpos( $app, 'Đơn vị (K&amp;H · POSH)' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: khai cơ sở cho đơn vị mới là đơn vị ấy hiện ra ngay, tích được, và tích xong thì thấy thật.\n";
