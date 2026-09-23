<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG TỔNG ĐỌC SỔ NGƯỜI DÙNG CỦA BẢN MẢNG — MỌI Ô NÓ HỎI PHẢI CÓ THẬT.
 *
 * Anh Thắng 14/09/2026: *"không có chỗ xuất misa theo đơn"* — kèm ảnh trang tổng ghi
 * *"KVC · (chưa có vai) · chỉ xem"*, bằng chính tài khoản ADMIN.
 *
 * =============================================================================================
 * 🔴 NGUYÊN NHÂN: ĐỌC NHẦM TÊN Ô.
 *
 * Bản mảng trả dòng người dùng với ô vai tên là **`vaiTro`** (`VHCP_Cfg::cfg_static()`), còn
 * trang tổng hỏi `$u['vai']`. Không có ô ấy -> chuỗi rỗng -> `bang_quyen()` trả về toàn `false`
 * ngay dòng đầu, TRƯỚC cả nhánh nới cho Admin. Nên bản vá Admin của 1.2.0 không cứu được gì:
 * nó vá nhánh thứ hai của một hàm đã thoát ở nhánh thứ nhất.
 *
 * 🔴 VÌ SAO PHẢI CÓ BÀI KIỂM RIÊNG: lỗi này KHÔNG KÊU. Đăng nhập vẫn được, tên vẫn đúng, bảng
 *    đơn vẫn đầy — chỉ là không ai bấm được gì, và trang còn nói dối rằng đó là do phân quyền.
 *    Người đi sửa sẽ ngồi sửa bảng phân quyền mãi không ra, vì chỗ hỏng không nằm ở đó.
 *
 * 🔴 VÀ NÓ SẼ TÁI DIỄN: bốn plugin nâng cấp lệch nhau, trang tổng đọc sổ của cả bốn. Mỗi lần
 *    bản mảng đổi tên một ô là một lần trang tổng có thể câm lặng như thế. Bài này so TỪNG Ô
 *    trang tổng hỏi với những ô bản mảng THẬT SỰ trả về.
 *
 * Chạy: php tools/test/kiem-khoa-so-nguoi-dung.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$GOC = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $GOC . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 1. Ô NÀO BẢN MẢNG THẬT SỰ TRẢ VỀ ════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Chị Trinh', '334400', 'Kế toán cá nhân', 'FUNZONE ADVENTURE', '1111', '2222', 'Kế toán', 'K&H', '', 'NV001' ),
) );
VHCP_Cfg::clear_cache();
$us = VHCP_Cfg::get_users();
t( '🔴 đọc được sổ người dùng của bản mảng', count( $us ) > 0, count( $us ) );
$co = $us ? array_keys( (array) $us[0] ) : array();

teq( '🔴 ô VAI của bản mảng tên là «vaiTro»', true, in_array( 'vaiTro', $co, true ) );
t( '⚠️ và KHÔNG có ô nào tên «vai» (đây chính là chỗ trang tổng hỏi hụt)',
	! in_array( 'vai', $co, true ), $co );
teq( 'vai đọc ra đúng giá trị đã khai', 'Kế toán cá nhân', $us[0]['vaiTro'] );

/* ═══ 2. TRANG TỔNG HỎI NHỮNG Ô NÀO ═══════════════════════════════════════════════
 *
 * Quét mã nguồn trang tổng tìm mọi `$u['…']` — `$u` là tên biến nó đặt cho một dòng người dùng
 * mượn từ bản mảng. Mỗi ô ấy phải có thật trong danh sách trên.
 * ═══════════════════════════════════════════════════════════════════════════════════ */
$auth = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-auth.php' );
$auth_sach = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $auth );   // bỏ chú thích: trong đó có nhắc tên ô cũ
$m = array();
preg_match_all( "#\\\$u\\[ *'([a-zA-Z]+)' *\\]#", $auth_sach, $m );
$hoi = array_values( array_unique( $m[1] ) );
t( '🔴 có tìm thấy chỗ trang tổng đọc dòng người dùng', count( $hoi ) > 0, $hoi );

/* Những ô khai làm ĐƯỜNG LUI cho bản mảng đời khác thì không bắt buộc phải có hôm nay. */
$duong_lui = array( 'vai', 'role' );
foreach ( $hoi as $o ) {
	if ( in_array( $o, $duong_lui, true ) ) { continue; }
	t( '🔴 ô «' . $o . '» trang tổng hỏi CÓ THẬT trong sổ bản mảng', in_array( $o, $co, true ), $co );
}

/* ═══ 3. VÀ ĐƯỜNG LUI PHẢI NẰM TRONG MỘT CỬA DUY NHẤT ════════════════════════════ */
t( '🔴 vai đọc qua vai_cua(), không hỏi thẳng một tên ô',
	(bool) preg_match( '#\$vai = self::vai_cua\( \$u \);#', $auth_sach ), '' );
t( '🔴 vai_cua() thử «vaiTro» TRƯỚC',
	(bool) preg_match( "#array\( 'vaiTro', 'vai', 'role' \)#", $auth_sach ), '' );

/* Chạy thử chính hàm ấy trên đúng dòng bản mảng trả về. */
require_once $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-auth.php';
teq( '🔴 vai_cua() trên dòng thật -> ra đúng vai', 'Kế toán cá nhân', VHCPT_Auth::vai_cua( $us[0] ) );
teq( 'dòng không có ô nào -> chuỗi rỗng, không nổ', '', VHCPT_Auth::vai_cua( array() ) );
teq( 'bản mảng đời cũ dùng «vai» -> vẫn đọc ra', 'Quản lý', VHCPT_Auth::vai_cua( array( 'vai' => 'Quản lý' ) ) );
teq( 'ô rỗng thì bỏ qua, lấy ô sau', 'Nhân viên',
	VHCPT_Auth::vai_cua( array( 'vaiTro' => '  ', 'vai' => 'Nhân viên' ) ) );

/* ═══ 4. ADMIN — TÀI KHOẢN CAO NHẤT PHẢI LÀM ĐƯỢC VIỆC ═══════════════════════════
 *
 * 🔴 Đây là phép canh đúng ảnh anh Thắng gửi: Admin đăng nhập mà màn ghi "chỉ xem".
 * ═══════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Admin', '1111', 'Admin', '', '', '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();
$ad = VHCP_Cfg::get_users();
teq( '🔴 dòng Admin đọc ra vai «Admin», không phải rỗng', 'Admin', VHCPT_Auth::vai_cua( $ad[0] ) );
t( '⚠️ và «Admin» KHÔNG nằm trong ma trận phân quyền (nên phải có nhánh nới riêng)',
	! in_array( 'Admin', (array) VHCP_Cfg::roles(), true ), VHCP_Cfg::roles() );

/* ═══ 5. THẺ PHÁT TỪ BẢN CŨ PHẢI TỰ LÀM MỚI ══════════════════════════════════════
 *
 * 🔴 ĐÃ CẮN THẬT, NGAY SAU BẢN VÁ TRÊN. Anh Thắng nạp 1.7.1, F5, màn VẪN ghi "(chưa có vai)" —
 *    *"chưa thấy gì"*. Vai và quyền tra một lần lúc đăng nhập rồi cất vào thẻ phiên sống 12 giờ,
 *    nên thẻ đang cầm vẫn mang đúng cái vai rỗng của lỗi vừa vá.
 *
 *    Đăng xuất vào lại là hết. Nhưng không ai đoán ra: màn không nói gì về thẻ, nó nói về phân
 *    quyền — nên người dùng kết luận bản vá không chạy, và đó là kết luận hợp lý.
 *
 * ⚠️ Phép này canh phần MÃ NGUỒN của cơ chế (chạy thật cần cả bốn plugin nạp cùng lúc, thứ bộ
 *    thử một-plugin không dựng được). Hai đầu phải cùng có mặt: thẻ mang dấu bản, và mỗi lượt
 *    gọi có đi qua cửa làm mới.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$api_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-api.php' ) );

t( '🔴 thẻ phát ra có đóng dấu bản plugin',
	(bool) preg_match( "#'ban' *=> *defined\( 'VHCPT_VERSION' \)#", $auth_sach ), '' );
t( '🔴 mọi lượt gọi có thẻ đều đi qua cửa làm mới',
	(bool) preg_match( '#\$ng = VHCPT_Auth::lam_moi_neu_cu\( \$the, \$ng \);#', $api_ma ), '' );
t( '🔴 và cửa ấy đứng TRƯỚC dat_toi()',
	(bool) preg_match( '#lam_moi_neu_cu\([\s\S]{0,120}?dat_toi#', $api_ma ), '' );
t( 'dấu bản khớp thì KHÔNG tra lại (khỏi hỏi bảng phân quyền mỗi lượt bấm)',
	(bool) preg_match( '#\$ban_the === \$ban_nay \) \{ return \$ng; \}#', $auth_sach ), '' );
t( '⚠️ tra lại mà không ra thì GIỮ NGUYÊN quyền cũ, không tước của ai',
	(bool) preg_match( '#if \( ! \$bans \) \{ return \$ng; \}#', $auth_sach ), '' );
t( '🔴 dựng lại sổ mảng đi qua bans_theo_ten(), không chép thân tim_theo_pin()',
	false !== strpos( $auth_sach, 'function bans_theo_ten' )
		&& (bool) preg_match( '#\$bans = self::bans_theo_ten\( \$ten \);#', $auth_sach ), '' );
t( 'và nó cũng đọc vai qua vai_cua()',
	(bool) preg_match( '#function bans_theo_ten[\s\S]{0,900}?self::vai_cua\( \$u \)#', $auth_sach ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ ĐẠT: $DAT phép thử — trang tổng hỏi đúng tên ô mà bản mảng đặt.\n";
