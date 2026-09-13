<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TỰ CẬP NHẬT TỪ GITHUB RELEASES — NÚT "CẬP NHẬT" NGAY Ở MÀN PLUGIN.
 *
 * Anh Thắng 13/09/2026: *"cách kết nối github đẩy thẳng code wed lên"*.
 *
 * =============================================================================================
 * 🔴 CHỖ NGUY NHẤT: MỘT REPO, CHÍN PLUGIN.
 *
 *    Repo này giữ cả chín plugin. Release của chúng nằm chung một danh sách, nên nếu lớp tự cập
 *    nhật không LỌC THEO TIỀN TỐ TAG của chính mình thì bản `vhcp-cham-cong-v3.76.0` hiện ra như
 *    bản mới của Vận Hành Chi Phí — bấm Cập nhật là plugin chi phí bị ghi đè bằng mã chấm công.
 *    Phần 1 canh đúng chỗ đó, và đó là lý do bài này tồn tại.
 *
 * 🔴 CHỖ NGUY THỨ HAI: KHOÁ GITHUB LÀ BÍ MẬT.
 *    Cùng luật đã đặt cho PIN và khoá máy chấm công: trang chạy ngoài internet, một ảnh chụp là
 *    mất. Ô nhập không bao giờ được đổ khoá đang lưu ra `value`. Phần 3 canh việc ấy.
 *
 * ⚠️ KHÔNG GỌI MẠNG THẬT. Bệ đỡ thử có `wp_remote_get` giả: `$GLOBALS['VHCP_HTTP']` đóng vai
 *    GitHub, khoá là một mẩu địa chỉ.
 *
 * Chạy: php tools/test/kiem-tu-cap-nhat.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
require_once $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-tu-cap-nhat.php';

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Dựng một hàng release như GitHub trả về. */
function rel( $tag, $co_zip = true, $draft = false, $pre = false ) {
	$r = array( 'tag_name' => $tag, 'draft' => $draft, 'prerelease' => $pre,
		'body' => 'ghi chú ' . $tag, 'published_at' => '2026-09-13T10:00:00Z', 'assets' => array() );
	if ( $co_zip ) {
		$r['assets'][] = array( 'name' => 'vhcp-chi-phi.zip',
			'url' => 'https://api.github.com/repos/x/y/releases/assets/' . crc32( $tag ) );
	}
	return $r;
}
/** Đặt câu trả lời của "GitHub" rồi xoá bộ nhớ tạm để lượt hỏi sau đi thật. */
function gh( $ds ) {
	$GLOBALS['VHCP_HTTP'] = array( 'api.github.com' => array( 'code' => 200, 'body' => wp_json_encode( $ds ) ) );
	delete_transient( VHCP_TuCapNhat::O_NHO );
	$GLOBALS['VHCP_TR'][ VHCP_TuCapNhat::O_NHO ] = null;
	unset( $GLOBALS['VHCP_TR'][ VHCP_TuCapNhat::O_NHO ] );
}

teq( '🔴 tiền tố tag là của RIÊNG plugin này', 'vhcp-chi-phi-v', VHCP_TuCapNhat::TIEN_TO );

/* ═══ 1. LỌC THEO TIỀN TỐ — KHÔNG NHẦM BẢN CỦA PLUGIN KHÁC ══════════════════════
 * Phép quan trọng nhất của cả bài.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
gh( array( rel( 'vhcp-cham-cong-v9.99.0' ), rel( 'vhcp-ghe-v5.0.0' ), rel( 'vhcp-noi-bo-v2.0.0' ) ) );
t( '🔴 bản của plugin KHÁC không bị nhận là bản mới của mình',
	null === VHCP_TuCapNhat::ban_moi( true ), VHCP_TuCapNhat::ban_moi( true ) );

/* 🔴 VÀ CANH LUÔN CÁI DÒNG LỌC ẤY TRONG MÃ.
   Phá thử 13/09/2026: gỡ hẳn phép lọc tiền tố mà bài vẫn xanh — vì cắt tiền tố khỏi tag của
   plugin khác tình cờ ra một mẩu chuỗi vụn, không khớp dạng số nên vẫn bị loại. Phép động xanh
   vì LÝ DO TÌNH CỜ, không phải vì thứ nó định canh. Nay hai lớp đều được canh: dòng lọc phải
   có mặt, và dạng số phải được kiểm. */
foreach ( array(
	array( 'chi phí',   '/wordpress/vhcp-chi-phi/includes/class-vhcp-tu-cap-nhat.php' ),
	array( 'chấm công', '/wordpress/vhcp-cham-cong/includes/class-vhcc-tu-cap-nhat.php' ),
) as $x ) {
	$ma = file_get_contents( $goc . $x[1] );
	$ma = preg_replace( '#/\*.*?\*/#s', '', $ma );
	$ma = preg_replace( '#//[^\n]*#', '', $ma );
	t( '🔴 ' . $x[0] . ': mã CÓ dòng lọc tag theo tiền tố',
		1 === preg_match( '/strpos\(\s*\$tag,\s*self::TIEN_TO\s*\)/', $ma ), null );
	/* Neo vào chuỗi nguyên văn, không dựng một regex để dò một regex — dò regex bằng regex là
	   chỗ đếm nhầm dấu gạch chéo, và phép đỏ oan trong khi mã vẫn đúng (cắn ngay lượt viết). */
	t( '🔴 ' . $x[0] . ': và CÓ lớp đỡ kiểm dạng số phiên bản',
		false !== strpos( $ma, '$ver )' ) && false !== strpos( $ma, 'preg_match' )
			&& false !== strpos( $ma, 'd+(\\.\\d+)*' ), null );
}

/* ⚠️ Và tag TRẦN kiểu `v9.9.9` cũng không được nhận — nó không nói mình thuộc plugin nào. */
gh( array( rel( 'v9.9.9' ) ) );
t( '⚠️ tag trần (không mang tên plugin) cũng bị bỏ qua',
	null === VHCP_TuCapNhat::ban_moi( true ), VHCP_TuCapNhat::ban_moi( true ) );

/* ⚠️ Tiền tố GẦN GIỐNG cũng phải trượt: `vhcp-chi-phi-hn` là một plugin KHÁC (bản vùng). */
gh( array( rel( 'vhcp-chi-phi-hn-v9.9.9' ) ) );
$hn = VHCP_TuCapNhat::ban_moi( true );
t( '🔴 bản vùng "vhcp-chi-phi-hn" KHÔNG bị nhận là bản của "vhcp-chi-phi"',
	null === $hn || false === strpos( (string) $hn['ver'], 'hn' ), $hn );

/* ═══ 2. CHỌN ĐÚNG BẢN ═══════════════════════════════════════════════════════════ */
$hien = VHCP_VERSION;
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . '9.0.0' ), rel( VHCP_TuCapNhat::TIEN_TO . '9.5.0' ),
	rel( VHCP_TuCapNhat::TIEN_TO . '9.2.0' ) ) );
$m = VHCP_TuCapNhat::ban_moi( true );
teq( '🔴 lấy bản CAO NHẤT, không phải bản đứng đầu danh sách', '9.5.0', $m ? $m['ver'] : null );

/* 🔴 KHÔNG BAO GIỜ HẠ CẤP. Bản cũ hơn hiện ra như "bản mới" là bấm một nút để quay ngược thời
   gian — mất đúng những bản vá vừa cài. */
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . '0.0.1' ) ) );
t( '🔴 bản CŨ hơn bản đang chạy thì không phải bản mới',
	null === VHCP_TuCapNhat::ban_moi( true ), VHCP_TuCapNhat::ban_moi( true ) );
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . $hien ) ) );
t( '⚠️ bản ĐÚNG BẰNG bản đang chạy cũng không phải bản mới',
	null === VHCP_TuCapNhat::ban_moi( true ), VHCP_TuCapNhat::ban_moi( true ) );

/* Bản nháp / bản thử: người ta cố ý chưa phát hành, đừng đẩy lên trang thật. */
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . '9.9.0', true, true, false ) ) );
t( '⚠️ bản NHÁP không được nhận', null === VHCP_TuCapNhat::ban_moi( true ), null );
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . '9.9.0', true, false, true ) ) );
t( '⚠️ bản THỬ (prerelease) không được nhận', null === VHCP_TuCapNhat::ban_moi( true ), null );

/* 🔴 KHÔNG CÓ .ZIP ĐÍNH KÈM THÌ BỎ QUA. Mã nguồn tự đóng của GitHub gói CẢ REPO — chín plugin
   cùng lúc, sai cấu trúc thư mục. Cài nó lên là hỏng plugin đang chạy. */
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . '9.9.0', false ) ) );
t( '🔴 release KHÔNG đính .zip thì bỏ qua (đừng lấy mã nguồn tự đóng của GitHub)',
	null === VHCP_TuCapNhat::ban_moi( true ), VHCP_TuCapNhat::ban_moi( true ) );

/* ═══ 3. KHOÁ GITHUB — BÍ MẬT, VÀ Ô TRỐNG LÀ GIỮ NGUYÊN ═════════════════════════ */
VHCP_TuCapNhat::xoa_khoa();
t( 'chưa khai thì nói chưa', ! VHCP_TuCapNhat::co_khoa() );
VHCP_TuCapNhat::dat_khoa( 'ghp_KHOA_BI_MAT_123' );
t( '🔴 khai rồi thì nói rồi', VHCP_TuCapNhat::co_khoa() );
/* 🔴 Ô TRỐNG = GIỮ NGUYÊN. Ô này không bao giờ hiện khoá đang lưu, nên "trống" là trạng thái
   BÌNH THƯỜNG của nó — hiểu trống là xoá thì mỗi lượt đổi múi giờ là mất khoá. */
VHCP_TuCapNhat::dat_khoa( '' );
t( '🔴 lưu với ô TRỐNG thì khoá vẫn còn', VHCP_TuCapNhat::co_khoa() );
VHCP_TuCapNhat::dat_khoa( '   ' );
t( '   (kể cả toàn khoảng trắng)', VHCP_TuCapNhat::co_khoa() );

/* 🔴 KHOÁ KHÔNG BAO GIỜ RA MÀN HÌNH. Quét chính mã dựng trang Cài đặt: ô khoá phải là
   `value=""` cứng, không phải một biến nào đó. */
$adm = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-admin.php' );
/* ⚠️ BẮT TỪ `<input`, KHÔNG TỪ `name=`. Thuộc tính `type="password"` đứng TRƯỚC `name=`, nên
   neo vào `name=` là phép "có phải ô mật khẩu không" không bao giờ thấy nó — đỏ oan trong khi
   mã vẫn đúng. */
if ( preg_match( '/<input type="password"[^>]*name="vhcp_gh_token".*?>/s', $adm, $m_o )
	|| preg_match( "/'<input[^;]*vhcp_gh_token[^;]*/s", $adm, $m_o ) ) {
	t( '🔴 ô nhập khoá để value RỖNG CỨNG, không đổ khoá đang lưu ra',
		false !== strpos( $m_o[0], 'value=""' ), $m_o[0] );
	t( '   và là ô mật khẩu, không phải ô chữ thường',
		false !== strpos( $m_o[0], 'type="password"' ), $m_o[0] );
} else {
	t( 'đọc được ô nhập khoá ở trang Cài đặt', false, 'không khớp regex' );
}
/* Có ô tích xoá riêng — bỏ khoá phải là một việc CÓ Ý. */
t( '⚠️ có ô tích xoá khoá riêng', false !== strpos( $adm, 'vhcp_gh_xoa' ), null );

/* ═══ 4. GÓI TẢI PHẢI MANG KHOÁ (repo riêng tư) ═════════════════════════════════ */
VHCP_TuCapNhat::dat_khoa( 'ghp_KHOA_BI_MAT_123' );
gh( array( rel( VHCP_TuCapNhat::TIEN_TO . '9.9.0' ) ) );
$tr = new stdClass(); $tr->response = array();
$tr = VHCP_TuCapNhat::chen_ban_moi( $tr );
$duong = 'vhcp-chi-phi/vhcp-chi-phi.php';
t( '🔴 bản mới được chèn vào danh sách cập nhật của WordPress',
	isset( $tr->response[ $duong ] ), array_keys( (array) $tr->response ) );
if ( isset( $tr->response[ $duong ] ) ) {
	$o = $tr->response[ $duong ];
	teq( '   đúng số phiên bản', '9.9.0', $o->new_version );
	/* Repo riêng tư: WordPress tải gói bằng một lượt gọi thẳng, không qua bộ lọc nào của ta,
	   nên khoá phải nằm sẵn trong địa chỉ — không thì tải về một trang "404 Not Found". */
	t( '🔴 địa chỉ tải mang theo khoá (repo riêng tư)',
		false !== strpos( $o->package, 'ghp_KHOA_BI_MAT_123@' ), '(không in địa chỉ ra đây)' );
	teq( '   và trỏ đúng vào tệp đính kèm của release', true,
		false !== strpos( $o->package, 'releases/assets/' ) );
}

/* ═══ 5. HỎNG THÌ IM LẶNG, KHÔNG LÀM HỎNG MÀN QUẢN TRỊ ═════════════════════════
 * Mạng chập, khoá hết hạn, GitHub quá tải — không cái nào đáng làm hỏng màn Plugin của cả
 * trang. Im lặng, thử lại sau.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$GLOBALS['VHCP_HTTP'] = array( 'api.github.com' => array( 'code' => 401, 'body' => '{"message":"Bad credentials"}' ) );
unset( $GLOBALS['VHCP_TR'][ VHCP_TuCapNhat::O_NHO ] );
t( '⚠️ khoá sai -> trả về null, không ném lỗi', null === VHCP_TuCapNhat::ban_moi( true ) );
$tr2 = new stdClass(); $tr2->response = array();
$tr2 = VHCP_TuCapNhat::chen_ban_moi( $tr2 );
t( '⚠️ và danh sách cập nhật của WordPress giữ nguyên, không hỏng',
	is_object( $tr2 ) && empty( $tr2->response ), $tr2 );

$GLOBALS['VHCP_HTTP'] = array();   // không có mạng
unset( $GLOBALS['VHCP_TR'][ VHCP_TuCapNhat::O_NHO ] );
t( '⚠️ mất mạng -> cũng chỉ trả về null', null === VHCP_TuCapNhat::ban_moi( true ) );

/* ═══ 6. 🔴 ĐƯỜNG ĐI — PLUGIN PHẢI NẠP VÀ GỌI ══════════════════════════════════
 * Viết đúng lớp mà quên nối vào plugin thì cài xong không có gì xảy ra, và nhìn mã thì thấy
 * "đã làm rồi". Cùng bài học với cổng API quên truyền phòng ban (13/09/2026).
 * ═════════════════════════════════════════════════════════════════════════════════════ */
foreach ( array(
	array( 'chi phí',   '/wordpress/vhcp-chi-phi/vhcp-chi-phi.php',     'class-vhcp-tu-cap-nhat.php', 'VHCP_TuCapNhat::init()' ),
	array( 'chấm công', '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php', 'class-vhcc-tu-cap-nhat.php', 'VHCC_TuCapNhat::init()' ),
) as $x ) {
	$ma = file_get_contents( $goc . $x[1] );
	$ma = preg_replace( '#/\*.*?\*/#s', '', $ma );
	$ma = preg_replace( '#//[^\n]*#', '', $ma );
	t( '🔴 plugin ' . $x[0] . ' CÓ nạp lớp tự cập nhật', false !== strpos( $ma, $x[2] ), null );
	t( '🔴 plugin ' . $x[0] . ' CÓ gọi init()',           false !== strpos( $ma, $x[3] ), null );
}

/* Hai plugin phải dùng CHUNG một khoá — khai một lần, cả hai cùng thấy bản mới. */
$cc = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tu-cap-nhat.php' );
t( '⚠️ hai plugin dùng chung một khoá GitHub (khai một lần là đủ)',
	false !== strpos( $cc, "O_KHOA = 'vhcp_gh_token'" ), null );
/* 🔴 Nhưng tiền tố tag thì phải KHÁC — đây là chỗ phân biệt bản của ai. */
t( '🔴 nhưng tiền tố tag thì khác nhau',
	false !== strpos( $cc, "TIEN_TO = 'vhcp-cham-cong-v'" ), null );
/* Và bộ nhớ tạm cũng phải khác, không thì hai plugin giẫm lên kết quả của nhau. */
t( '🔴 và bộ nhớ tạm cũng tách riêng',
	false !== strpos( $cc, "O_NHO   = 'vhcc_gh_ban_moi'" ), null );

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
