<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * AI ĐƯỢC VÀO TRANG TỔNG — QUẢN LÝ VÀ KẾ TOÁN, KHÔNG PHẢI NHÂN VIÊN.
 *
 * Anh Thắng 14/09/2026: *"Trang tổng thêm phần cấu hình để đẩy nhân sự kế toán, và quản lý qua
 * để duyệt đơn và xử lý đơn, nhân viên thì không cần"*.
 *
 * =============================================================================================
 * 🔴 LUẬT NỀN LÀ THEO QUYỀN, KHÔNG PHẢI THEO DANH SÁCH TÊN KHAI TAY.
 *
 * Nếu cửa vào là một danh sách tên thì mỗi lần tuyển một kế toán mới, người ấy khai đủ quyền bên
 * trang mảng, làm được mọi việc ở đó — nhưng trang tổng vẫn chối, cho tới khi có ai NHỚ RA là
 * phải vào wp-admin thêm tên. Cái "có ai nhớ ra" đó không được phép nằm trong một quy trình tiền
 * bạc. Nên: có ít nhất một quyền xử lý đơn ở bất kỳ mảng nào thì vào được, hết.
 *
 * 🔴 CHỐI Ở MÁY CHỦ, KHÔNG CHỐI Ở MÀN. Giấu nút đi mà vẫn cho vào là người ta vẫn đọc được toàn
 *    bộ sổ chi của ba mảng — đúng thứ đang phải tách.
 *
 * ⚠️ Hai sổ ngoại lệ (cho vào thêm · chặn) cho ca luật nền không với tới. CHẶN THẮNG CHO-VÀO:
 *    hai sổ cùng có tên thì phía cấm phải thắng, luôn luôn.
 *
 * Chạy: php tools/test/kiem-cua-vao-trang-tong.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$GOC = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $GOC . '/wordpress/vhcp-chi-phi' );
require_once $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-auth.php';

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Dựng sổ mảng giả: mỗi việc bật/tắt theo ý. */
function ban( $viecs ) {
	$q = array();
	foreach ( array_keys( VHCPT_Auth::VIEC ) as $v ) { $q[ $v ] = in_array( $v, $viecs, true ); }
	return array( 'kvc' => array( 'vai' => 'X', 'quyen' => $q ) );
}

update_option( VHCPT_Auth::O_THEM, array() );
update_option( VHCPT_Auth::O_CHAN, array() );

/* ═══ 1. LUẬT NỀN ═════════════════════════════════════════════════════════════════ */
teq( '🔴 nhân viên thuần (không việc nào) -> KHÔNG vào',
	false, VHCPT_Auth::duoc_vao( ban( array() ), 'Nhân Viên A' ) );
foreach ( array_keys( VHCPT_Auth::VIEC ) as $v ) {
	teq( 'có quyền «' . $v . '» -> vào được', true, VHCPT_Auth::duoc_vao( ban( array( $v ) ), 'Ai Đó' ) );
}
teq( 'sổ mảng rỗng -> không vào', false, VHCPT_Auth::duoc_vao( array(), 'Ai Đó' ) );
/* ⚠️ Thẻ đời cũ chưa có bảng quyền — không được coi là "có việc". */
teq( '⚠️ dòng mảng không có bảng quyền -> không vào',
	false, VHCPT_Auth::duoc_vao( array( 'kvc' => array( 'vai' => 'X' ) ), 'Ai Đó' ) );

/* ═══ 2. HAI SỔ NGOẠI LỆ ══════════════════════════════════════════════════════════ */
VHCPT_Auth::dat_so( VHCPT_Auth::O_THEM, array( 'Chị Giám Đốc' ) );
teq( '🔴 cho vào tay: không quyền nào vẫn vào được',
	true, VHCPT_Auth::duoc_vao( ban( array() ), 'Chị Giám Đốc' ) );
teq( 'và so tên KHÔNG phân biệt hoa thường',
	true, VHCPT_Auth::duoc_vao( ban( array() ), 'CHỊ GIÁM ĐỐC' ) );

VHCPT_Auth::dat_so( VHCPT_Auth::O_CHAN, array( 'Anh Nghỉ Việc' ) );
teq( '🔴 chặn tay: có quyền vẫn KHÔNG vào',
	false, VHCPT_Auth::duoc_vao( ban( array( 'duyet' ) ), 'Anh Nghỉ Việc' ) );

/* 🔴 Hai sổ cùng có tên -> CẤM THẮNG. Phía cấm luôn phải thắng: một người bị chặn vì nghỉ việc
   mà tên còn sót trong sổ cho-vào thì họ vẫn đi duyệt tiền được. */
VHCPT_Auth::dat_so( VHCPT_Auth::O_THEM, array( 'Hai Sổ' ) );
VHCPT_Auth::dat_so( VHCPT_Auth::O_CHAN, array( 'Hai Sổ' ) );
teq( '🔴🔴 tên nằm ở CẢ HAI sổ -> cấm thắng',
	false, VHCPT_Auth::duoc_vao( ban( array( 'duyet' ) ), 'Hai Sổ' ) );

/* ═══ 3. SỔ NGOẠI LỆ RỬA SẠCH ĐẦU VÀO ════════════════════════════════════════════ */
VHCPT_Auth::dat_so( VHCPT_Auth::O_THEM, array( '  Chị B  ', 'chị b', '', 'Anh C' ) );
teq( 'sổ bỏ trùng, bỏ rỗng, cắt khoảng trắng', 2, count( VHCPT_Auth::so( VHCPT_Auth::O_THEM ) ) );
t( 'và hạ chữ thường khi cất', in_array( 'chị b', VHCPT_Auth::so( VHCPT_Auth::O_THEM ), true ),
	VHCPT_Auth::so( VHCPT_Auth::O_THEM ) );

/* ═══ 4. CỬA ĐÓNG Ở MÁY CHỦ ══════════════════════════════════════════════════════ */
$api = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-api.php' ) );
t( '🔴 dangNhap chối người không được vào',
	(bool) preg_match( '#if \( ! VHCPT_Auth::duoc_vao\( \$kq\[.bans.\], \$kq\[.ten.\] \) \)#', $api ), '' );
t( '🔴 và cửa ấy đứng TRƯỚC lúc phát thẻ',
	(bool) preg_match( '#duoc_vao\([\s\S]{0,900}?phat_the#', $api ), '' );
t( '⚠️ câu chối chỉ thẳng chỗ khai quyền, không nói trống không',
	(bool) preg_match( '#Phân quyền của trang mảng#u', $api ), '' );

/* ═══ 5. MÀN WP-ADMIN ════════════════════════════════════════════════════════════ */
$ad = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-admin.php' );
$ad_sach = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $ad );
t( 'có bảng «Người vào trang tổng»', false !== mb_strpos( $ad_sach, 'Người vào trang tổng' ), '' );
t( '🔴 màn KHÔNG in PIN ra HTML', false === strpos( $ad_sach, "['pin']" ), '' );
t( '🔴 chỉ ghi hai sổ khi form CÓ gửi bảng ấy',
	(bool) preg_match( "#! empty\( \\\$_POST\['vhcpt_co_bang'\] \)#", $ad_sach ), '' );
/* ⚠️ Không có gác trên thì mỗi lượt Lưu ở form đường dẫn sẽ xoá sạch cả hai sổ. */
t( '⚠️ và lượt ghi ấy nằm TRONG gác, không nằm ngoài',
	(bool) preg_match( "#vhcpt_co_bang[\s\S]{0,1400}?dat_so\( VHCPT_Auth::O_CHAN#", $ad_sach ), '' );
t( '🔴 tích trùng luật nền thì KHÔNG ghi vào sổ ngoại lệ',
	(bool) preg_match( '#\$duoc === \(bool\) \$ng\[.coViec.\] \) \{ continue; \}#', $ad_sach ), '' );

/* 🔴 HTML không cho lồng form trong form: trình duyệt bỏ form trong, nút Lưu của nó gửi sang
   form ngoài — bấm Lưu bảng người thì lưu đường dẫn, ô tích rơi đâu mất. */
$mo  = substr_count( $ad_sach, "<form method=\"post\"" );
$dong = substr_count( $ad_sach, "echo '</form>';" );
teq( '🔴 số form mở = số form đóng', $mo, $dong );
teq( 'và có đúng hai form', 2, $mo );
t( '🔴 form đầu ĐÓNG trước khi mở form bảng người',
	(bool) preg_match( "#submit_button\(\);\s*echo '</form>';[\s\S]{0,2000}?Người vào trang tổng#u", $ad_sach ), '' );

/* ═══ 6. MỞ / KHOÁ CỬA NGAY TRÊN MÀN CẤU HÌNH ═══════════════════════════════════
 * Anh Thắng 14/09/2026, nhìn màn Cấu hình thấy hai chục dòng ghi *"không vào được trang tổng"*
 * mà không sửa được: *"chưa cho cấu hình"*.
 *
 * 🔴 SỔ NGOẠI LỆ CHỈ GHI CA LỆCH. Nếu mỗi ô tích đều nhét một dòng vào sổ CHO-VÀO thì sổ ấy lớn
 *    dần thành đúng cái "danh sách tên khai tay" mà luật nền sinh ra để tránh: ngày mai người ta
 *    thôi làm kế toán, mất sạch quyền bên trang mảng, mà tên vẫn nằm trong sổ — vẫn vào được sổ
 *    tiền của cả ba mảng.
 * ═══════════════════════════════════════════════════════════════════════════════ */
VHCPT_Auth::dat_so( VHCPT_Auth::O_THEM, array() );
VHCPT_Auth::dat_so( VHCPT_Auth::O_CHAN, array() );

/* Bốn ca của bảng chân trị: (muốn vào?) × (luật nền có cho?). */
teq( '🔴 tích + nền ĐÃ cho vào -> không ghi sổ nào', '', VHCPT_Auth::dat_vao( 'Chị Kế Toán', true, true ) );
teq( 'và tên không nằm ở sổ nào', '', VHCPT_Auth::ngoai_le( 'Chị Kế Toán' ) );
teq( '🔴 tích + nền CHỐI -> ghi sổ cho-vào', 'them', VHCPT_Auth::dat_vao( 'Chị Giám Đốc 2', true, false ) );
teq( '🔴 bỏ tích + nền CHO -> ghi sổ chặn', 'chan', VHCPT_Auth::dat_vao( 'Anh Nghỉ 2', false, true ) );
teq( '🔴 bỏ tích + nền ĐÃ chối -> không ghi sổ nào', '', VHCPT_Auth::dat_vao( 'Nhân Viên B', false, false ) );
teq( 'và tên ấy cũng không nằm ở sổ nào', '', VHCPT_Auth::ngoai_le( 'Nhân Viên B' ) );

/* 🔴 KẾT QUẢ PHẢI ĐÚNG Ý NGƯỜI KHAI Ở CẢ BỐN CA — ô tích là một lời hứa. */
foreach ( array(
	array( 'Ca A', true,  true  ),
	array( 'Ca B', true,  false ),
	array( 'Ca C', false, true  ),
	array( 'Ca D', false, false ),
) as $ca ) {
	list( $ten, $cho, $nen ) = $ca;
	VHCPT_Auth::dat_vao( $ten, $cho, $nen );
	teq( '🔴 ' . $ten . ': tích ' . var_export( $cho, true ) . ' / nền ' . var_export( $nen, true ) . ' -> vào được?',
		$cho, VHCPT_Auth::duoc_vao( ban( $nen ? array( 'duyet' ) : array() ), $ten ) );
}

/* ⚠️ ĐỔI Ý PHẢI GỠ ĐƯỢC. Chỉ thêm mà không gỡ thì một người từng bị chặn sẽ bị chặn mãi, kể cả
   sau khi người khai tích lại — và không ai hiểu vì sao ô tích không ăn. */
VHCPT_Auth::dat_vao( 'Đổi Ý', false, true );
teq( '⚠️ chặn rồi tích lại -> gỡ khỏi sổ chặn', '', VHCPT_Auth::dat_vao( 'Đổi Ý', true, true ) );
teq( 'và vào lại được', true, VHCPT_Auth::duoc_vao( ban( array( 'duyet' ) ), 'Đổi Ý' ) );
VHCPT_Auth::dat_vao( 'Đổi Ý 2', true, false );
teq( '⚠️ cho vào rồi bỏ tích -> gỡ khỏi sổ cho-vào', '', VHCPT_Auth::dat_vao( 'Đổi Ý 2', false, false ) );

/* ⚠️ KHÔNG ĐỨNG CẢ HAI SỔ. Đứng cả hai thì `duoc_vao()` đọc sổ CHẶN trước, và người khai sẽ thấy
   ô tích của mình không có tác dụng gì mà không có lời giải thích nào. */
VHCPT_Auth::dat_so( VHCPT_Auth::O_THEM, array( 'Hai Chỗ' ) );
VHCPT_Auth::dat_vao( 'Hai Chỗ', false, true );
t( '⚠️ ghi sổ chặn thì gỡ luôn khỏi sổ cho-vào',
	! in_array( 'hai chỗ', VHCPT_Auth::so( VHCPT_Auth::O_THEM ), true ), VHCPT_Auth::so( VHCPT_Auth::O_THEM ) );
VHCPT_Auth::dat_so( VHCPT_Auth::O_CHAN, array( 'Hai Chỗ 2' ) );
VHCPT_Auth::dat_vao( 'Hai Chỗ 2', true, false );
t( '⚠️ ghi sổ cho-vào thì gỡ luôn khỏi sổ chặn',
	! in_array( 'hai chỗ 2', VHCPT_Auth::so( VHCPT_Auth::O_CHAN ), true ), VHCPT_Auth::so( VHCPT_Auth::O_CHAN ) );

teq( 'tên rỗng thì không ghi gì', '', VHCPT_Auth::dat_vao( '   ', true, false ) );

/* ═══ 7. CỬA ẤY NỐI ĐƯỢC RA MÀN ═════════════════════════════════════════════════ */
t( '🔴 luuCauHinh chỉ động vào cửa khi màn CÓ gửi ô tích lên',
	(bool) preg_match( '#array_key_exists\( .vao., \(array\) \\$args \)#', $api ), '' );
/* 🔴 So với LUẬT NỀN, không so với tình trạng hiện tại: `duoc_vao()` đã trộn sổ ngoại lệ vào rồi
   nên đem nó ra so là sổ tự khẳng định chính nó — người đang ở sổ cho-vào thì mãi ở đó. */
t( '🔴 và so với co_viec_nao(), KHÔNG so với duoc_vao()',
	(bool) preg_match( '#\\$nen\s*=\s*VHCPT_Auth::co_viec_nao\(#', $api )
	&& ! preg_match( '#\\$nen\s*=\s*VHCPT_Auth::duoc_vao\(#', $api ), '' );
t( 'màn cấu hình trả về ca lệch đang ghi', false !== mb_strpos( $api, 'ngoai_le(' ), '' );

$app = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-tong/templates/app.html' );
$app_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $app );
t( '🔴 bảng cấu hình có cột Vào trang tổng', false !== mb_strpos( $app_ma, 'Vào trang tổng' ), '' );
t( '🔴 mỗi dòng có ô tích cửa vào', (bool) preg_match( '#data-vao="#', $app_ma ), '' );
/* 🔴 Admin bỏ tích chính mình là mất luôn đường quay lại — chỗ mở ra chỉ còn trong wp-admin. */
t( '🔴 màn nhận ra dòng của chính người đang khai',
	(bool) preg_match( '#var toi = \( TOI && TOI\.ten#', $app_ma ), '' );
t( '🔴 và khoá ô tích của dòng ấy lại',
	(bool) preg_match( "#toi \? ' disabled#", $app_ma ), '' );
/* ⚠️ `disabled` chỉ chặn ngón tay, không chặn vòng lặp gom — gom cả ô khoá là vẫn ghi đè. */
t( '⚠️ và ô bị khoá KHÔNG được gom vào lượt gửi',
	(bool) preg_match( '#data-vao\]?.\), function\(n\)\{\s*if \(n\.disabled\) return;#', $app_ma ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ ĐẠT: $DAT phép thử — cửa trang tổng mở cho người xử lý đơn, đóng với người chỉ lập đơn.\n";
