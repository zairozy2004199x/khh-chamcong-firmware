<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NHÀ MẸ NHÌN CẢ HỆ — K&H xem được cả POSH và KVC.
 *
 * Anh Thắng 11/09/2026, sau khi mở màn Cấu hình thấy bảng "🏢 Mã đơn vị theo Cơ sở" trắng trơn:
 *   *"mã đơn vị theo cơ sở bị ẩn mất rồi"*
 *   *"thấy rồi, do để đơn vị K&H nên không thấy. Để K&H là xem được tất cả đơn vị"*
 *
 * =============================================================================================
 * 🔴 BA CÁI TÊN KHÔNG NGANG HÀNG. K&H là nhà mẹ; POSH và KVC là hai mảng tách ra từ đó. Luật
 *    cũ bó mọi nhân viên theo nhà, nên chính người phải nhìn toàn cục lại bị bó chặt nhất —
 *    còn chiều ngược lại (POSH không thấy K&H) thì đúng và phải giữ nguyên.
 *
 * 🔴 CHIỀU NGƯỢC LẠI LÀ THỨ DỄ HỎNG NHẤT KHI SỬA. Mở cho K&H mà lỡ tay mở cho cả hệ là xoá
 *    sạch việc tách đơn vị đã dựng suốt hai tuần — nên phần lớn bài kiểm dưới đây canh đúng
 *    chiều ĐÓNG: POSH và KVC vẫn không thấy gì của nhau, cũng không thấy K&H.
 *
 * 🔴 ĐÃ BỎ Ô "XEM ĐƠN VỊ" VÀ LUẬT VAI (12/09/2026). Anh Thắng: *"Đơn vị với xem đơn vị là 1,
 *    đã thuộc đơn vị đó, thì toàn quyền xem của mình"*. Trước đây có BA đường cùng trả lời câu
 *    "được đọc sổ nhà nào" — cột Đơn vị, cột Xem đơn vị, và hằng VAI_XEM_CA — nên lệch nhau là
 *    chuyện sớm muộn. Nay chỉ còn cột Đơn vị; phần dưới canh đúng điều đó.
 *
 * Chạy: php tools/test/kiem-don-vi-me-xem-ca.php
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

const CS_KH   = 'FUNZONE VŨNG TÀU';
const CS_POSH = 'POSH MN CGV VINCOM LANDMARK';
const CS_KVC  = 'KVC AEON TÂN PHÚ';

VHCP_Cfg::seed();
/* Cột: ten | maDonVi | phanLoaiLon | tenMisa | dongCua | donVi */
VHCP_Cfg::append( VHCP_Cfg::COSO, array( CS_KH,   'FZVT',    'FZ MN',   '', '', '' ) );   // trống = K&H
VHCP_Cfg::append( VHCP_Cfg::COSO, array( CS_POSH, 'POSHVCL', 'POSH MN', '', '', 'POSH' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( CS_KVC,  'KVCATP',  'KVC MN',  '', '', 'KVC' ) );
/* Cột: ten | pin | vai | coso | tkCo | maDt | boPhan | donVi | xemDonVi */
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV K&H',     '111111', 'Nhân viên', '', '', '', 'Kỹ thuật', 'K&H',  '' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV POSH',    '222222', 'Nhân viên', '', '', '', 'Kỹ thuật', 'POSH', '' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV KVC',     '333333', 'Nhân viên', '', '', '', 'Kỹ thuật', 'KVC',  '' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV Chưa khai', '444444', 'Nhân viên', '', '', '', 'Kỹ thuật', '',   '' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'NV K&H Bó',  '555555', 'Nhân viên', '', '', '', 'Kỹ thuật', 'K&H',  'K&H' ) );
VHCP_Cfg::clear_cache();

function vai( $ten, $v = 'Nhân viên' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. CHỐT GỐC: AI LÀ NHÀ MẸ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'nhà mẹ mặc định là K&H', 'K&H', VHCP_DonVi::don_vi_me() );
teq( 'K&H là nhà mẹ',   true,  VHCP_DonVi::la_don_vi_me( 'K&H' ) );
teq( 'không phân biệt hoa thường', true, VHCP_DonVi::la_don_vi_me( 'k&h' ) );
teq( 'thừa khoảng trắng vẫn nhận', true, VHCP_DonVi::la_don_vi_me( '  K&H  ' ) );
teq( '🔴 POSH KHÔNG phải nhà mẹ', false, VHCP_DonVi::la_don_vi_me( 'POSH' ) );
teq( '🔴 KVC KHÔNG phải nhà mẹ',  false, VHCP_DonVi::la_don_vi_me( 'KVC' ) );
/* ⚠️ Ô rỗng đi qua `chuan()` nên thành K&H — đó chính là lý do người chưa khai cũng xem cả,
   ghi thành bài kiểm để hệ quả ấy là điều CỐ Ý, không phải điều lọt lưới. */
teq( 'ô rỗng rơi về nhà mẹ (nên người chưa khai cũng xem cả)', true, VHCP_DonVi::la_don_vi_me( '' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. NHÂN VIÊN NHÀ MẸ NHÌN CẢ HỆ — VAI THẤP NHẤT VẪN NHÌN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'NV K&H' );
teq( '🔴 NV nhà K&H (vai Nhân viên) xem cả — `xem_duoc()` trả null', null, VHCP_DonVi::xem_duoc() );
teq( '   thấy gian K&H',  true, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
teq( '   thấy gian POSH', true, VHCP_DonVi::xem_duoc_coso( CS_POSH ) );
teq( '   thấy gian KVC',  true, VHCP_DonVi::xem_duoc_coso( CS_KVC ) );
/* 🔴 `null` phải đi suốt xuống các chốt dưới, không chỉ đúng ở hàm gốc. Ba chốt này là ba
   đường khác nhau (lọc SQL · lọc danh sách cơ sở · chốt cửa API); một cái sót là một màn
   vẫn rỗng dù `xem_duoc()` đã mở. */
teq( '   điều kiện SQL không lọc gì',       null, VHCP_DonVi::dieu_kien_sql() );
teq( '   ô chọn cơ sở bày đủ (trả null)',   null, VHCP_DonVi::coso_xem_duoc() );
teq( '   chốt cửa API cho qua',             '',   VHCP_DonVi::chan_theo_ham( array( 'VHCP_Don', 'get_don' ), array( 'KHONG-CO-DON-NAY' ) ) );

vai( 'NV Chưa khai' );
teq( 'người CHƯA KHAI đơn vị cũng xem cả (ô trống = nhà mẹ)', null, VHCP_DonVi::xem_duoc() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 CHIỀU NGƯỢC LẠI VẪN ĐÓNG — thứ dễ hỏng nhất khi mở cho nhà mẹ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
vai( 'NV POSH' );
t( '🔴 NV POSH KHÔNG được xem cả', null !== VHCP_DonVi::xem_duoc(), VHCP_DonVi::xem_duoc() );
teq( '   bó đúng vào POSH',        array( 'POSH' ), VHCP_DonVi::xem_duoc() );
teq( '   thấy gian POSH',          true,  VHCP_DonVi::xem_duoc_coso( CS_POSH ) );
teq( '🔴 KHÔNG thấy gian K&H',     false, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
teq( '🔴 KHÔNG thấy gian KVC',     false, VHCP_DonVi::xem_duoc_coso( CS_KVC ) );
teq( '   ô chọn cơ sở chỉ còn gian POSH', array( CS_POSH ), VHCP_DonVi::coso_xem_duoc() );

vai( 'NV KVC' );
teq( '🔴 NV KVC bó đúng vào KVC',  array( 'KVC' ), VHCP_DonVi::xem_duoc() );
teq( '🔴 KVC KHÔNG thấy gian K&H',  false, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
teq( '🔴 KVC KHÔNG thấy gian POSH', false, VHCP_DonVi::xem_duoc_coso( CS_POSH ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. Ô "XEM ĐƠN VỊ" KHÔNG CÒN AI ĐỌC TỚI — chỉ cột "Đơn vị" nói chuyện
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * "NV K&H Bó" khai `xemDonVi = 'K&H'` y như trước, nhưng nhà vẫn là K&H nên nay xem cả hệ.
 * Phép dưới canh đúng chỗ ấy: giá trị cũ CÒN NGUYÊN trong sổ mà KHÔNG còn tác dụng gì — bỏ
 * quên một chỗ đọc nó là luật lại tách làm hai, đúng kiểu hỏng vừa dọn xong. */
vai( 'NV K&H Bó' );
teq( '🔴 ô "Xem đơn vị" cũ KHÔNG còn bó ai lại nữa', null, VHCP_DonVi::xem_duoc() );
teq( '   thấy gian K&H',  true, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
teq( '   thấy cả gian POSH (vì nhà là K&H)', true, VHCP_DonVi::xem_duoc_coso( CS_POSH ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. TẮT / ĐỔI NHÀ MẸ BẰNG KHOÁ CẤU HÌNH — không phải sửa mã
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
update_option( 'vhcp_dv_me', 'POSH' );
teq( 'đổi nhà mẹ sang POSH', 'POSH', VHCP_DonVi::don_vi_me() );
vai( 'NV POSH' );
teq( '   giờ POSH xem cả',  null, VHCP_DonVi::xem_duoc() );
vai( 'NV K&H' );
teq( '🔴 còn K&H bị bó lại', array( 'K&H' ), VHCP_DonVi::xem_duoc() );

update_option( 'vhcp_dv_me', '' );
teq( 'để trống khoá = TẮT hẳn luật nhà mẹ', '', VHCP_DonVi::don_vi_me() );
teq( '   K&H thôi là nhà mẹ', false, VHCP_DonVi::la_don_vi_me( 'K&H' ) );
vai( 'NV K&H' );
teq( '🔴 và K&H trở lại chỉ xem K&H', array( 'K&H' ), VHCP_DonVi::xem_duoc() );
vai( 'NV Chưa khai' );
teq( '   người chưa khai cũng chỉ còn K&H', array( 'K&H' ), VHCP_DonVi::xem_duoc() );

update_option( 'vhcp_dv_me', 'K&H' );   // trả lại như cũ cho phần sau (nếu có)

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. VAI KHÔNG CÒN NỚI TẦM NHÌN — Admin nhà POSH chỉ đọc sổ POSH
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐẢO CHIỀU SO VỚI BẢN TRƯỚC, và đảo có chủ ý. Hằng `VAI_XEM_CA` đã bị bỏ: vai trả lời câu
 *    "được LÀM GÌ" (bảng Phân quyền Hành động × Vai trò), còn "đọc được sổ NHÀ NÀO" thì chỉ
 *    cột Đơn vị nói. Admin muốn nhìn cả hệ thì để nhà là K&H — đúng như mọi tài khoản đang
 *    khai. Giữ phép này để lần sau ai đó định "cho Admin xem cả cho tiện" thì thấy nó đỏ và
 *    đọc được lý do ngay tại đây. */
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'Sếp POSH', '666666', 'Admin', '', '', '', '', 'POSH', '' ) );
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'Sếp K&H',  '777777', 'Admin', '', '', '', '', 'K&H',  '' ) );
VHCP_Cfg::clear_cache();
vai( 'Sếp POSH', 'Admin' );
teq( '🔴 Admin nhà POSH CHỈ đọc sổ POSH (vai không nới tầm nhìn nữa)', array( 'POSH' ), VHCP_DonVi::xem_duoc() );
teq( '   nên không thấy gian K&H', false, VHCP_DonVi::xem_duoc_coso( CS_KH ) );
vai( 'Sếp K&H', 'Admin' );
teq( '🔴 Admin nhà K&H vẫn xem cả hệ — vì NHÀ, không vì vai', null, VHCP_DonVi::xem_duoc() );
/* Kế toán cũng thế: trước đây `VAI_XEM_CA` cho họ xem cả bất kể nhà. */
VHCP_Cfg::append( VHCP_Cfg::USER, array( 'KT KVC', '888888', 'Kế toán cá nhân', '', '', '', '', 'KVC', '' ) );
VHCP_Cfg::clear_cache();
vai( 'KT KVC', 'Kế toán cá nhân' );
teq( '🔴 Kế toán nhà KVC chỉ đọc sổ KVC', array( 'KVC' ), VHCP_DonVi::xem_duoc() );

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — nhà mẹ K&H nhìn cả hệ, POSH và KVC vẫn tách\n";
