<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VÀO BẰNG VÉ / SSO CHỈ MƯỢN DANH TÍNH — QUYỀN LẤY TỪ BẢNG NGƯỜI DÙNG BÊN CHI PHÍ.
 *
 * Anh Thắng 20/09/2026: *"việc đẩy nhân sự sang chỉ là để đăng nhập. Sau phân quyền cho bên chi
 * phí quyết định. Để tránh râu ông này cắm bà kia"*.
 *
 * =============================================================================================
 * 🔴 LUẬT NÀY ĐÃ CÓ TỪ LÂU — Ở ĐÚNG MỘT CỬA
 * =============================================================================================
 * `VHCP_Auth::login()` chốt rõ: *"CHỈ MƯỢN MẬT KHẨU, KHÔNG MƯỢN QUYỀN… người ấy phải có sẵn một
 * dòng trong bảng người dùng của trang Chi phí, và vai · cơ sở · phòng ban lấy từ ĐÚNG DÒNG
 * ĐÓ"*. Cửa PIN theo luật ấy.
 *
 * Cửa VÉ / SSO thì KHÔNG. `resolve_sso_user()` lấy thẳng vai và cơ sở từ gói bên kia gửi sang
 * (`$ident['r']`, `$ident['b']`) và chưa bao giờ tra bảng người dùng bên này. Hậu quả:
 *   · ai là Quản lý bên chấm công thì bấm sang là Quản lý trên trang TIỀN — duyệt được chi của
 *     mọi cơ sở, mà không ai bên chi phí bấm nút nào;
 *   · cơ sở cũng bê nguyên sang, tức lại là MÃ cửa hàng chứ không phải TÊN gian hàng — cùng cái
 *     lỗi đã sửa ở lượt đẩy, chỉ khác cửa;
 *   · và nó cấp lại ở MỖI LƯỢT ĐĂNG NHẬP, không để lại dòng nào cho kế toán nhìn thấy mà sửa.
 *
 * Cửa này im hơn hẳn lượt đẩy, nên nó cần bài kiểm riêng chứ không nấp sau bài của lượt đẩy.
 *
 * Chạy: php tools/test/kiem-sso-muon-mat-khau.php
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

/* Bảng người dùng bên CHI PHÍ — đây là nguồn sự thật duy nhất về quyền.
   Cột: ten · pin · vai · coso · tkCo · maDt · boPhan · donVi · xemDonVi · maNv */
VHCP_Cfg::write( VHCP_Cfg::COSO, array( array( 'NHÀ MA BÌNH DƯƠNG', '', '', '', '', '' ) ) );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Anh Bin', '1111', 'Nhân viên', 'NHÀ MA BÌNH DƯƠNG', '', '', 'Kỹ thuật', '', '', 'NV007' ),
) );
VHCP_Cfg::clear_cache();

/* ═══ 1. 🔴 VAI BÊN KIA GỬI SANG KHÔNG ĐƯỢC CÓ TÁC DỤNG ════════════════════════════
 * Gói giả lập một người bên chấm công đang là ADMIN, cơ sở là MÃ cửa hàng. Bên chi phí người ấy
 * chỉ là Nhân viên ở NHÀ MA BÌNH DƯƠNG — và đó mới là thứ phải thắng. */
$u = VHCP_Auth::resolve_sso_user( array(
	'n' => 'Anh Bin', 'm' => 'NV007', 'e' => '',
	'r' => 'ADMIN', 'b' => 'TUTU_BD',
) );
t( 'nối được người', is_array( $u ), $u );
teq( '🔴 vai lấy từ bảng chi phí, KHÔNG phải vai bên chấm công gửi sang',
	'Nhân viên', (string) $u['role'] );
teq( '🔴 cơ sở cũng lấy từ bảng chi phí — không nhận mã cửa hàng bên kia',
	'NHÀ MA BÌNH DƯƠNG', (string) $u['coso'] );
teq( '   bộ phận cũng thế', 'Kỹ thuật', (string) $u['boPhan'] );
teq( '   tên lấy đúng theo dòng bên này', 'Anh Bin', (string) $u['name'] );

/* ═══ 2. NỐI BẰNG MÃ NV TRƯỚC, TÊN SAU ═════════════════════════════════════════════
 * ⚠️ Tên là khoá LỎNG — gõ lệch một dấu là trượt. Mã NV là khoá chắc, nên phải thử trước. */
$u2 = VHCP_Auth::resolve_sso_user( array( 'n' => 'Tên Gõ Sai', 'm' => 'NV007', 'e' => '' ) );
t( '🔴 nối được bằng MÃ NV dù tên gõ sai', is_array( $u2 ) && 'Anh Bin' === (string) $u2['name'], $u2 );
$u3 = VHCP_Auth::resolve_sso_user( array( 'n' => 'Anh Bin', 'm' => '', 'e' => '' ) );
t( '   chưa khai mã thì nối bằng tên', is_array( $u3 ) && 'Anh Bin' === (string) $u3['name'], $u3 );

/* ═══ 3. 🔴 CHƯA CÓ DÒNG BÊN CHI PHÍ THÌ KHÔNG VÀO ĐƯỢC ════════════════════════════
 * Đẻ bừa một danh tính 'Nhân viên' ở đây là mở cửa cho CẢ SỔ NHÂN SỰ bước vào trang tiền — mà
 * sổ ấy có hàng trăm người, phần lớn không liên quan gì tới chi phí. */
$u4 = VHCP_Auth::resolve_sso_user( array( 'n' => 'Người Lạ Hoắc', 'm' => 'NV999', 'e' => '' ) );
teq( '🔴 người chưa có dòng bên chi phí → trả null, không tự đẻ danh tính', null, $u4 );

/* ═══ 4. BẢNG ĐÈ THEO EMAIL VẪN THẮNG — NÓ LÀ KHAI BÊN NÀY ═════════════════════════
 * ⚠️ Khác hẳn vai bên chấm công gửi sang: bảng này khai ở màn Cấu hình CỦA CHÍNH trang chi phí,
 *    tức đúng nghĩa "bên chi phí quyết định". */
VHCP_Cfg::write( VHCP_Cfg::SSO, array( array( 'bin@poshvn.com', 'Kế toán cá nhân', '' ) ) );
VHCP_Cfg::clear_cache();
delete_transient( 'vhcp_ssomap' );
$u5 = VHCP_Auth::resolve_sso_user( array( 'n' => 'Anh Bin', 'm' => 'NV007', 'e' => 'bin@poshvn.com' ) );
teq( '🔴 bảng đè theo email (khai bên chi phí) vẫn thắng', 'Kế toán cá nhân', (string) $u5['role'] );

/* ═══ 5. VAI TRỐNG THÌ VÀO ĐƯỢC, COI NHƯ NHÂN VIÊN ═════════════════════════════════
 * Đây là ca của người VỪA ĐẨY SANG: lượt đẩy để trống vai, và họ phải đăng nhập được — chỉ là
 * chưa thấy gì cho tới khi kế toán phân. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Anh Bin', '1111', 'Nhân viên', 'NHÀ MA BÌNH DƯƠNG', '', '', 'Kỹ thuật', '', '', 'NV007' ),
	array( 'Người Mới Đẩy', '2222', '', '', '', '', '', '', '', 'NV800' ),
) );
VHCP_Cfg::clear_cache();
delete_transient( 'vhcp_ssomap' );
$u6 = VHCP_Auth::resolve_sso_user( array( 'n' => 'Người Mới Đẩy', 'm' => 'NV800', 'e' => '' ) );
t( '🔴 người mới đẩy (vai trống) VẪN vào được', is_array( $u6 ), $u6 );
teq( '   và được coi là Nhân viên', 'Nhân viên', (string) $u6['role'] );
teq( '   chưa có cơ sở nào — chỉ thấy đơn của chính mình', '', (string) $u6['coso'] );

/* ═══ 6. CỬA GỌI PHẢI BIẾT XỬ `null` ═══════════════════════════════════════════════
 * 🔴 Trả `null` mà bên gọi cứ `$u['token'] = …` thì PHP 8 ném lỗi ngay trang đăng nhập — sửa
 *    một lỗ hổng mà đẻ ra một trang trắng. */
$app = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-app.php' );
teq( '🔴 cả hai cửa gọi đều gác `null` trước khi dùng', 2,
	preg_match_all( '/if \( ! \$u \) \{ return null; \}/u', $app ) );
t( '   và cửa vé thôi gửi vai/cơ sở của bên chấm công sang',
	false === mb_strpos( $app, "'r' => VHCC_Ve::ma_vai(" ), 'còn gửi' );
t( '   nhưng vẫn gửi MÃ NV để nối cho chắc', false !== mb_strpos( $app, "'m' =>" ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: vé/SSO chỉ mượn danh tính, quyền lấy từ bảng bên chi phí.\n";
