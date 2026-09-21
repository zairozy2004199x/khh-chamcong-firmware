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
			/* ⚠️ DÙNG ĐÚNG TÊN KHO THẬT, không phải `x/y`. Bộ lọc gắn khoá chỉ nhận địa chỉ của
			   CHÍNH kho này (cố ý — gắn bừa là gửi khoá tới mọi máy chủ khác). Đồ thử mang tên
			   kho giả thì bộ lọc từ chối đúng, mà phép thử lại đỏ vì lý do chẳng liên quan. */
			'url' => 'https://api.github.com/repos/' . VHCP_TuCapNhat::REPO
				. '/releases/assets/' . crc32( $tag ) );
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
	/* ═══════════════════════════════════════════════════════════════════════════════════════
	   🔴 ĐỊA CHỈ TẢI PHẢI SẠCH — KHÔNG MANG KHOÁ. Phép này TRƯỚC ĐÂY canh điều NGƯỢC LẠI, và
	   chính nó giữ cho cái lỗi sống suốt: nó bắt địa chỉ PHẢI có `<khoá>@`.

	   16/09/2026 anh Thắng gửi ảnh hộp thoại trên trang: *"Download failed. Địa chỉ URL không
	   hợp lệ"* — và trong câu báo lỗi ấy là NGUYÊN CÁI KHOÁ GitHub của anh, in ra màn hình rồi
	   đi vào ảnh chụp. Phải thu hồi khoá.

	   Hai điều bản cũ tin là đúng, cả hai đều sai:
	     · "repo riêng tư nên phải nhét khoá vào địa chỉ" — kho này CÔNG KHAI (§4), tải gói
	       không cần khoá.
	     · "WordPress tải thẳng, không qua bộ lọc nào của ta" — có: `http_request_args` chạy cho
	       MỌI lượt gọi HTTP, kể cả lượt tải gói của bộ nâng cấp.
	   Và `wp_http_validate_url()` vốn CHỐI mọi địa chỉ `user@host`, nên đường ấy chưa từng tải
	   được lần nào — phép thử cũ xanh, mà tính năng thì chưa bao giờ chạy.

	   Bài học ghi lại: một phép thử phát biểu SAI luật còn nguy hơn không có phép nào. Nó cho
	   người sau một cái cớ để giữ đúng chỗ cần bỏ.
	   ═══════════════════════════════════════════════════════════════════════════════════════ */
	t( '🔴 địa chỉ tải KHÔNG mang khoá — khoá đi ở tiêu đề, xem `them_khoa_tai()`',
		false === strpos( $o->package, '@' ) || false === strpos( $o->package, 'KHOA_BI_MAT' ),
		'(không in địa chỉ ra đây)' );
	t( '   và bộ lọc gắn khoá vào tiêu đề có tồn tại',
		method_exists( 'VHCP_TuCapNhat', 'them_khoa_tai' ) );
	/* Gọi thẳng bộ lọc: đúng địa chỉ gói thì gắn khoá, địa chỉ khác thì TUYỆT ĐỐI không —
	   gắn bừa là gửi khoá GitHub của anh Thắng tới mọi máy chủ plugin khác gọi tới. */
	$args_goi = VHCP_TuCapNhat::them_khoa_tai( array(), $o->package );
	t( '   gọi tới gói thì có tiêu đề Authorization',
		isset( $args_goi['headers']['Authorization'] ), $args_goi );
	t( '   và có Accept: octet-stream (thiếu là GitHub trả JSON thay vì tệp)',
		isset( $args_goi['headers']['Accept'] )
		&& 'application/octet-stream' === $args_goi['headers']['Accept'], $args_goi );
	$args_la = VHCP_TuCapNhat::them_khoa_tai( array(), 'https://example.com/gi-do.zip' );
	t( '🔴 địa chỉ LẠ thì KHÔNG gắn khoá', ! isset( $args_la['headers']['Authorization'] ), $args_la );
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

/* ═══ 6. 🔴 QUÉT ĐỦ MỌI PLUGIN — BỘ MỚI QUÊN LÀ BÀI NÀY ĐỎ ══════════════════════
 * Anh Thắng 13/09/2026, ngay sau khi chốt làm cho bảy bộ còn lại: *"với sau này tạo ra bộ mới
 * thì sao"*.
 *
 * 🔴 KHÔNG LIỆT KÊ TAY TỪNG PLUGIN Ở ĐÂY. Danh sách gõ tay đứng im trong khi cây mã đi tiếp —
 *    đúng cái bẫy mà `tools/build-plugin-zip.sh` đã phải dựng "chốt chống sót" để chặn
 *    (`vhcp-noi-bo` từng suýt bị bỏ quên kiểu ấy). Phép dưới đây QUÉT thư mục `wordpress/`:
 *    dựng một plugin mới mà quên nối lớp tự cập nhật là bộ thử đỏ ngay, không ai phải nhớ.
 *
 * ⚠️ CỐ Ý BỎ QUA THÌ PHẢI KHAI VÀO `$KHONG_TU_CAP_NHAT`, y như `KHONG_DONG_GOI` bên script
 *    đóng gói — để chỗ bỏ qua là một QUYẾT ĐỊNH có ghi lại, chứ không phải một chỗ sót.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$KHONG_TU_CAP_NHAT = array(
	/* Bản viết lại 25/08/2026 đã dừng; hệ đang chạy là `vhcp-cham-cong`. Script đóng gói cũng
	   bỏ qua nó (`KHONG_DONG_GOI`), nên nó không có bản cài nào để mà cập nhật. */
	'vhcp-cong',
);

$ds_plugin = array();
foreach ( (array) glob( $goc . '/wordpress/*', GLOB_ONLYDIR ) as $thu_muc ) {
	$ten = basename( $thu_muc );
	if ( ! file_exists( $thu_muc . '/' . $ten . '.php' ) ) { continue; }
	$ds_plugin[] = $ten;
}
t( 'quét được thư mục plugin (≥ 8)', count( $ds_plugin ) >= 8, $ds_plugin );

$so_co = 0;
foreach ( $ds_plugin as $ten ) {
	$chinh = $goc . '/wordpress/' . $ten . '/' . $ten . '.php';
	$ma = preg_replace( '#/\*.*?\*/#s', '', file_get_contents( $chinh ) );
	$ma = preg_replace( '#//[^\n]*#', '', $ma );

	if ( in_array( $ten, $KHONG_TU_CAP_NHAT, true ) ) {
		/* Đã khai bỏ qua thì phải bỏ qua THẬT — nửa vời (có lớp mà không gọi) còn khó hiểu hơn. */
		t( '⚠️ ' . $ten . ' — cố ý KHÔNG tự cập nhật, và đúng là không có',
			false === strpos( $ma, 'TuCapNhat' ), $ten );
		continue;
	}
	$so_co++;
	t( '🔴 ' . $ten . ': CÓ nạp lớp tự cập nhật',
		1 === preg_match( '/require_once [A-Z_]+ \. .includes\/class-[a-z]+-tu-cap-nhat\.php.;/', $ma ), $ma_loi = null );
	t( '🔴 ' . $ten . ': CÓ gọi init()',
		1 === preg_match( '/[A-Z_]+_TuCapNhat::init\(\);/', $ma ), null );

	/* 🔴 TIỀN TỐ TAG PHẢI BẰNG ĐÚNG TÊN THƯ MỤC. Đây là chỗ chín plugin phân biệt bản của
	   nhau; chép lớp từ plugin khác mà quên đổi dòng này là bộ mới đi nhận bản của bộ cũ. */
	$lop = glob( $goc . '/wordpress/' . $ten . '/includes/class-*-tu-cap-nhat.php' );
	if ( ! $lop ) { t( '🔴 ' . $ten . ': có tệp lớp tự cập nhật', false, $ten ); continue; }
	$ma_lop = file_get_contents( $lop[0] );
	t( '🔴 ' . $ten . ': tiền tố tag = tên thư mục của chính nó',
		false !== strpos( $ma_lop, "TIEN_TO = '" . $ten . "-v'" ), $ten );
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * KHOÁ: DÙNG CHUNG — TRỪ BẢN VÙNG.
	 *
	 * Anh Thắng 13/09/2026: *"cần token nữa không, hay dùng chung"*. Những plugin sống chung
	 * MỘT site thì chung một ô khoá: khai một lần là đủ cho cả tám.
	 *
	 * 🔴 BẢN VÙNG LÀ NGOẠI LỆ, VÀ LÀ NGOẠI LỆ BẮT BUỘC. `vhcp-chi-phi-hn` (và mọi bản
	 *    `vhcp-chi-phi-<vùng>` do `tools/tach-ban-vung.sh` sinh ra) được tách để chạy trên một
	 *    site RIÊNG của vùng ấy; luật của bản vùng là KHÔNG dùng chung một chuỗi nào với bản
	 *    gốc — không chung tiền tố bảng, không chung ô cấu hình. `kiem-tach-ban-vung.php` canh
	 *    điều đó, và nó đã bắt được ngay lượt đầu khi lớp tự cập nhật của bản vùng còn chép
	 *    nguyên ô khoá của bản gốc sang.
	 *
	 * ⚠️ NHẬN BẢN VÙNG BẰNG QUY TẮC TÊN, không bằng danh sách gõ tay — thêm một vùng là thêm
	 *    một dòng phải nhớ sửa, và lần quên nào cũng im lặng. Cùng quy tắc mà
	 *    `tools/build-plugin-zip.sh` dùng cho nhánh `chi-phi-*`.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	$la_ban_vung = ( 0 === strpos( $ten, 'vhcp-chi-phi-' ) );
	if ( $la_ban_vung ) {
		t( '🔴 ' . $ten . ' (bản vùng): khoá RIÊNG, không chung với bản gốc',
			false === strpos( $ma_lop, "'vhcp_gh_token'" ), $ten );
		t( '   và ô khoá mang tiền tố của chính nó',
			1 === preg_match( "/O_KHOA = '[a-z]+_gh_token';/", $ma_lop ), $ten );
	} else {
		t( '⚠️ ' . $ten . ': dùng CHUNG một khoá GitHub (khai một lần là đủ)',
			false !== strpos( $ma_lop, "O_KHOA = 'vhcp_gh_token'" ), $ten );
	}
	/* Nhưng bộ nhớ tạm phải RIÊNG, không thì hai plugin giẫm lên kết quả của nhau. */
	t( '🔴 ' . $ten . ': bộ nhớ tạm riêng',
		1 === preg_match( "/O_NHO\s+= '(?!vhcp_gh_ban_moi')[a-z]+_gh_ban_moi';/", $ma_lop )
			|| ( 'vhcp-chi-phi' === $ten && false !== strpos( $ma_lop, "O_NHO   = 'vhcp_gh_ban_moi'" ) ),
		$ten );
}
t( 'có ít nhất tám plugin tự cập nhật được', $so_co >= 8, $so_co );

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
