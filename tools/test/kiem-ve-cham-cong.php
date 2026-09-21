<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VÉ MANG DANH TÍNH TỪ TRẠM CHẤM CÔNG SANG APP CHI PHÍ.
 *
 * Anh Thắng 19/09/2026, hai ảnh đặt cạnh nhau: trạm Chấm công đang là *Trần Ngọc Minh Truyền ·
 * TUTU_TP*, bấm ô Ứng dụng sang app chi phí thì hiện *Nguyễn Văn Bin · FARM_PT*. *"Phải tự link
 * chung 1 tk chứ"*. Hậu quả không phải hiển thị: người này lập và duyệt đơn dưới tên người kia.
 *
 * =============================================================================================
 * 🔴 BÀI NÀY SINH RA TỪ MỘT LẦN SUÝT MẤT VIỆC — 19/09/2026
 * =============================================================================================
 * Anh Thắng gửi lại `vhcp-chi-phi-1.214.0.zip`. Nó KHÔNG phải bản 1.214.0 trong kho: nó mang
 * phần vé này (không có trong kho), và THIẾU phần cân cột của 1.214.0 (có trong kho). Hai bản
 * khác nhau, CÙNG MỘT SỐ. Cài bản nào sau là phần việc của bản kia biến mất, không câu nào báo.
 *
 * Phần vé ấy không nằm trên nhánh nào — nó chỉ sống trong đúng một tệp zip. Bài này chốt nó
 * vào kho để lần sau còn tìm lại được.
 *
 * =============================================================================================
 * 🔴 NỬA BÊN KIA CÒN THIẾU — VÀ ĐÓ LÀ ĐIỀU PHẢI HIỆN RA MỖI LƯỢT CHẠY
 * =============================================================================================
 * `ve_cham_cong()` hỏi lớp `VHCC_Ve` bên plugin chấm công. Lớp ấy KHÔNG CÓ trong kho: chấm công
 * 4.35.2 chỉ có `VHCC_VeTram` (nút "← Về trạm"), không có `doi()` hay `ma_vai()`. Nên chừng nào
 * nửa kia chưa về, hàm này luôn trả `null` và tính năng nằm im.
 *
 * ⚠️ KHÔNG BẮT BÀI NÀY ĐỎ VÌ CHUYỆN ẤY. Chính đầu `chay-het.sh` đã viết: *"Bài thử đỏ mà không
 *    ai chạy thì cũng như không có. Tệ hơn: nó cho cảm giác AN TOÀN GIẢ."* Một bài đỏ trường
 *    kỳ là bài bị bỏ qua, rồi kéo theo mọi bài đỏ thật sau nó. Nên chuyện thiếu nửa kia in ra
 *    thành DÒNG CẢNH BÁO, còn phép thì canh đúng thứ mã trong kho này phải đúng.
 *
 * Chạy: php tools/test/kiem-ve-cham-cong.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );

$DAT = 0; $TRUOT = array(); $NHAC = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}

$APP  = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-app.php' );
$SHIM = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/assets/js/gas-shim.js' );
t( 'đọc được class-vhcp-app.php', '' !== $APP );
t( 'đọc được gas-shim.js', '' !== $SHIM );

/* ═══ 1. PHẦN VÉ CÒN TRONG KHO ═══════════════════════════════════════════════════════ */
t( '🔴 `ve_cham_cong()` còn trong kho (đã suýt mất — chỉ sống trong một tệp zip)',
	false !== strpos( $APP, 'private static function ve_cham_cong()' ) );
t( '   và nó đọc tham số `ccve` trên địa chỉ', false !== strpos( $APP, "\$_GET['ccve']" ) );
/* 🔴 VÉ PHẢI THẮNG `?sso=`. Người vừa bấm ở trạm là người đang đứng trước máy; hỏi `?sso=`
   trước là lại rơi về đúng con đường đã đổi nhầm người. */
$_i_ve  = strpos( $APP, 'self::ve_cham_cong()' );
$_i_sso = strpos( $APP, "empty( \$_GET['sso'] )" );
t( '🔴 vé của trạm được hỏi TRƯỚC `?sso=`',
	false !== $_i_ve && false !== $_i_sso && $_i_ve < $_i_sso, array( $_i_ve, $_i_sso ) );

/* ═══ 2. KHÔNG ĐƯỢC LÀM CHẾT TRANG KHI CHƯA CÓ NỬA KIA ═══════════════════════════════
 * Plugin chấm công có thể chưa cài, hoặc cài bản cũ. Gọi thẳng một lớp không tồn tại là PHP
 * chết ngay lúc nạp — trang chi phí trắng, mà nguyên nhân nằm ở plugin KHÁC.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
t( '🔴 có `class_exists` gác trước khi gọi — chưa cài chấm công thì trang vẫn mở',
	false !== strpos( $APP, "class_exists( 'VHCC_Ve' )" ) );
t( '   và gác cả hai hàm nó gọi, không chỉ tên lớp',
	false !== strpos( $APP, "method_exists( 'VHCC_Ve', 'doi' )" )
	&& false !== strpos( $APP, "method_exists( 'VHCC_Ve', 'ma_vai' )" ) );
/* Gác phải nằm TRONG cùng hàm với lời gọi. Gác ở chỗ khác rồi gọi ở đây là hai nơi có thể
   lệch nhau — và lệch ở đây nghĩa là trang trắng. */
$_than = '';
if ( preg_match( '/private static function ve_cham_cong\(\).*?\n\t\}/su', $APP, $_m ) ) { $_than = $_m[0]; }
t( 'bốc được thân hàm `ve_cham_cong()`', '' !== $_than );
t( '🔴 gác nằm TRONG chính hàm ấy, không phải ở nơi khác',
	false !== strpos( $_than, "class_exists( 'VHCC_Ve' )" )
	&& false !== strpos( $_than, 'VHCC_Ve::doi(' ) );

/* ═══ 3. THẺ PHIÊN PHẢI ĐÈ THẺ CŨ Ở TRÌNH DUYỆT ══════════════════════════════════════
 * Đây mới là nửa làm nên tính năng. Máy chủ nhận đúng người mà giao diện vẫn gửi thẻ cũ thì
 * thanh tiêu đề hiện tên mới còn SỔ VẪN GHI TÊN NGƯỜI CŨ — hỏng đúng kiểu khó thấy nhất.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
t( '🔴 máy chủ phát thẻ phiên cho người của vé',
	false !== strpos( $_than, "\$u['token'] = VHCP_Auth::issue_token(" ) );
t( '🔴 giao diện GHI ĐÈ thẻ cũ bằng `ssoToken`, không phải "ghi nếu chưa có"',
	1 === preg_match( '/if \(\s*CFG\.ssoToken\s*\)\s*\{\s*setToken\(\s*CFG\.ssoToken\s*\)/u', $SHIM ), $SHIM ? 'không khớp' : '' );
/* ⚠️ PHẢI ĐÒI NÓ NẰM TRONG CHÍNH KHỐI `if ( CFG.ssoToken )`. Bản đầu của phép này chỉ tìm
   `sessionStorage.removeItem( USER_KEY )` ở bất cứ đâu trong tệp — mà `clearSession()` cũng có
   đúng dòng ấy từ lâu. Nên gỡ hẳn dòng dọn trong khối SSO đi thì phép vẫn XANH. Đột biến bắt
   được, và đây là bản đã siết: cắt đúng khối rồi mới tìm. */
$_khoi_sso = '';
if ( preg_match( '/if \(\s*CFG\.ssoToken\s*\)\s*\{(.*?)\n\t\}/su', $SHIM, $_ks ) ) { $_khoi_sso = $_ks[1]; }
t( 'bốc được khối `if ( CFG.ssoToken )`', '' !== $_khoi_sso );
t( '   và dọn luôn bản nhớ danh tính cũ NGAY TRONG khối ấy — không thì nháy tên người cũ một nhịp',
	false !== mb_strpos( $_khoi_sso, 'sessionStorage.removeItem( USER_KEY )' ), $_khoi_sso );
t( '🔴 máy chủ có gửi `ssoToken` xuống — thiếu nó thì nhánh trên không bao giờ chạy',
	false !== strpos( $APP, 'ssoToken' ) );

/* ═══ 4. HỢP ĐỒNG VỚI PLUGIN CHẤM CÔNG ═══════════════════════════════════════════════ */
$CC = '';
foreach ( (array) glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ) as $f ) {
	$CC .= (string) @file_get_contents( $f );
}
$co_lop = 1 === preg_match( '/\bclass\s+VHCC_Ve\b(?!Tram)/u', $CC );
if ( $co_lop ) {
	/* Nửa kia đã về — giờ mới canh được hình dạng của nó. */
	t( '🔴 `VHCC_Ve` có `doi()` — đổi vé lấy danh tính',
		1 === preg_match( '/function\s+doi\s*\(/u', $CC ) );
	t( '🔴 `VHCC_Ve` có `ma_vai()` — quy vai của trạm về vai của chi phí',
		1 === preg_match( '/function\s+ma_vai\s*\(/u', $CC ) );
} else {
	$NHAC[] = 'NỬA BÊN CHẤM CÔNG CHƯA VỀ KHO — lớp `VHCC_Ve` không có trong '
		. 'wordpress/vhcp-cham-cong/ (bản ' . _cc_ver( $goc ) . ' chỉ có `VHCC_VeTram`, là nút '
		. '"← Về trạm", không phải vé). Chừng nào nó chưa về, `ve_cham_cong()` luôn trả null '
		. 'và việc bấm từ trạm sang vẫn mang danh tính người cũ.';
}
function _cc_ver( $goc ) {
	$s = (string) @file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
	return preg_match( "/VHCC_VERSION', '([^']+)'/u", $s, $m ) ? $m[1] : '?';
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
foreach ( $NHAC as $x ) { echo "\n⚠️  CẢNH BÁO: $x\n"; }
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: vé từ trạm mang đúng người sang app chi phí.\n";
