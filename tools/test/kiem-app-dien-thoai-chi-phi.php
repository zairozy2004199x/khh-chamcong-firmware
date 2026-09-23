<?php
/**
 * LỚP VỎ APP ĐIỆN THOẠI (PWA) CỦA TRANG CHI PHÍ.
 *
 * Anh Thắng 22/09/2026: *"Xong chuyển vào app điện thoại để chạy giao diện điện thoại nhé"*.
 *
 * =================================================================================================
 * 🔴 BỐN CHỖ HỎNG IM LẶNG MÀ BÀI NÀY CANH
 * =================================================================================================
 * 1. `sw.js` PHỤC VỤ SAI CHỖ. Thợ nền chỉ quản được đường nằm DƯỚI thư mục chứa nó. Trỏ thẳng vào
 *    `wp-content/plugins/.../sw.js` thì đăng ký vẫn "thành công", thợ nền vẫn chạy, mà không bao
 *    giờ đỡ được lượt tải nào — công cụ lập trình báo xanh, ngoại tuyến thì trắng trang.
 * 2. THỢ NỀN NHỚ HỘ ĐƯỜNG DỮ LIỆU. App có BA cổng gọi máy chủ; bỏ sót cổng nào là kế toán mở app
 *    ra thấy bảng tiền của lần trước và tin nó. Bảng tiền nói sai tệ hơn bảng tiền không mở được.
 * 3. BỐN BẢN DÙNG CHUNG MỘT `id`/`scope`. Cài hai bản lên một máy là hai cái đè nhau, hoặc một cái
 *    không cài được — mà bốn bản đang chạy song song trên cùng một site.
 * 4. THIẾU `apple-touch-icon`. iPhone KHÔNG đọc `icons` trong manifest.json; thiếu thẻ ấy là nó
 *    chụp đại màn hình lúc cài làm biểu tượng, và muốn sửa phải xoá app cài lại.
 *
 * Chạy: php tools/test/kiem-app-dien-thoai-chi-phi.php
 */

$GOC = dirname( __DIR__, 2 );
$BAN = array( 'vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . var_export( $them, true ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc );
}
/** Tước chú thích — xem chốt 🔴 ở chỗ gọi. Giữ nguyên số dòng không cần, chỉ cần nội dung mã. */
function chi_ma( $s ) {
	$s = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $s );
	$s = preg_replace( '#^\s*//[^
]*$#m', ' ', $s );
	return (string) $s;
}

foreach ( $BAN as $b ) {
	$dir = "$GOC/wordpress/$b";
	if ( ! is_dir( $dir ) ) { t( "có bản $b", false ); continue; }
	/* Tiền tố lớp của bản vùng — `tools/tach-ban-vung.sh` đổi VHCP -> VHCP<MÃ HOA>. */
	$pre = ( 'vhcp-chi-phi' === $b ) ? 'VHCP' : 'VHCP' . strtoupper( substr( $b, strlen( 'vhcp-chi-phi-' ) ) );
	$low = ( 'vhcp-chi-phi' === $b ) ? 'vhcp' : 'vhcp' . substr( $b, strlen( 'vhcp-chi-phi-' ) );

	$fp = "$dir/includes/class-vhcp-pwa.php";
	t( "$b: có includes/class-vhcp-pwa.php", is_readable( $fp ) );
	if ( ! is_readable( $fp ) ) { continue; }
	/* 🔴 TƯỚC CHÚ THÍCH TRƯỚC KHI SOI. Lần thứ sáu trong tuần dẫm cái bẫy "phép soi khớp phải
	   chính chú thích của mình": chú thích ở `class-vhcp-pwa.php` giải thích vì sao KHÔNG gộp
	   `any maskable`, và nó nhắc lại đúng hai chữ ấy — nên phép "không được gộp" đỏ oan. */
	$P = chi_ma( (string) file_get_contents( $fp ) );
	$CHINH = (string) file_get_contents( "$dir/$b.php" );
	$APP   = (string) file_get_contents( "$dir/includes/class-vhcp-app.php" );
	$SW    = chi_ma( (string) file_get_contents( "$dir/assets/js/sw.js" ) );
	$PWAJS = chi_ma( (string) file_get_contents( "$dir/assets/js/pwa.js" ) );

	/* ═══ 1. NỐI VÀO PLUGIN ═════════════════════════════════════════════════════════════ */
	t( "🔴 $b: plugin nạp lớp vỏ", false !== strpos( $CHINH, "includes/class-vhcp-pwa.php" ) );
	t( "🔴 $b: và khai đường dẫn của nó", (bool) preg_match(
		"/add_action\(\s*'init',\s*array\(\s*'{$pre}_Pwa',\s*'init'\s*\),\s*5\s*\)/", $CHINH ), null );
	/* ⚠️ Khai muộn hơn lượt nạp lại bảng luật (ưu tiên 99) là luật không vào bảng, và app báo
	   "manifest không đọc được" mà không nói vì sao. */
	t( "⚠️ $b: khai TRƯỚC lượt nạp lại bảng luật",
		strpos( $CHINH, "'{$pre}_Pwa', 'init' ), 5" ) < strpos( $CHINH, "'{$low}_flush_rewrite', 99" )
		|| strpos( $CHINH, "vhcp_flush_rewrite', 99" ) > 0, null );

	/* 🔴 DÒ CẢ LỚP LẪN HÀM trước khi gọi — bản cũ chưa có lớp này, gọi hụt là trắng cả trang. */
	t( "🔴 $b: `head_block()` dò CẢ LỚP LẪN HÀM trước khi gọi",
		(bool) preg_match( "/class_exists\(\s*'{$pre}_Pwa'\s*\)\s*&&\s*method_exists\(\s*'{$pre}_Pwa',\s*'khoi_head'\s*\)/", $APP ), null );
	t( "   và có chèn thật vào <head>", false !== strpos( $APP, "{$pre}_Pwa::khoi_head()" ) );

	/* ═══ 2. 🔴 KHÔNG ĐẺ ĐƯỜNG DẪN THỨ HAI ═════════════════════════════════════════════
	 * Hai địa chỉ phụ phải nằm DƯỚI đường của trang, và không được có luật nào dựng một trang
	 * app riêng — trang trùng nội dung là link cũ và link app cùng sống, rồi sửa một quên một. */
	t( "🔴 $b: manifest và sw khai DƯỚI đường của trang",
		(bool) preg_match( "/\^'\s*\.\s*\\\$s\s*\.\s*'\/manifest\\\\\.json\\\$/", $P )
		&& (bool) preg_match( "/\^'\s*\.\s*\\\$s\s*\.\s*'\/sw\\\\\.js\\\$/", $P ), null );
	t( "   và dựng từ `cac_slug()` — bốn bản bốn đường, không bản nào giành của bản nào",
		false !== strpos( $P, "{$pre}_App::cac_slug()" ) );
	t( "🔴 $b: KHÔNG khai thêm một trang app riêng",
		false === strpos( $P, "'index.php?{$low}_app=" ), null );

	/* ⚠️ Ưu tiên 4 — TRƯỚC `maybe_render()` của trang (mặc định 10): đường lui qua tham số truy
	   vấn đi chung một chỗ, để sau là trang app trả về thay cho tờ khai. */
	t( "⚠️ $b: chạy TRƯỚC lượt dựng trang (ưu tiên 4)",
		(bool) preg_match( "/'template_redirect', array\( __CLASS__, 'maybe_render' \), 4 \)/", $P ), null );

	/* ═══ 3. 🔴 `sw.js` PHỤC VỤ TỪ ĐƯỜNG CỦA TRANG, KHÔNG TỪ THƯ MỤC PLUGIN ════════════ */
	t( "🔴 $b: `sw.js` do PHP phục vụ, đọc từ assets rồi in ra",
		false !== strpos( $P, "{$pre}_DIR . 'assets/js/sw.js'" )
		&& false !== strpos( $P, "Service-Worker-Allowed: /" ), null );
	t( "🔴 $b: thẻ <script> trỏ `data-sw` vào ĐƯỜNG CỦA TRANG, không vào thư mục plugin",
		(bool) preg_match( "/data-sw=\"'\s*\.\s*esc_url\(\s*self::url_phu\(\s*'sw'\s*\)\s*\)/", $P ), null );
	t( "   thiếu tệp thì NÓI RA, không im lặng trả rỗng",
		false !== strpos( $P, "throw new Error" ), null );
	/* 🔴 Số bản đi vào NỘI DUNG tệp, không đi qua `?v=`: trình duyệt so từng byte để nhận ra thợ
	   nền mới; đổi địa chỉ thì thành một thợ nền KHÁC và thợ cũ vẫn nằm đó quản trang. */
	/* ⚠️ Thư mục tệp tĩnh cũng do PHP nhét vào — dò bằng khuôn chữ là ba bản vùng hỏng im lặng
	   (script tách đổi mọi chuỗi `vhcp` trong `sw.js`). Chạy thật ở `kiem-tho-nen-chi-phi.js`. */
	t( "🔴 $b: thư mục tệp tĩnh do PHP nhét vào `sw.js`, không dò bằng khuôn chữ",
		false !== strpos( $P, "str_replace( '__GOC_TINH__'" ) && false !== strpos( $SW, "'__GOC_TINH__'" ), null );
	t( "🔴 $b: số bản nhét vào NỘI DUNG `sw.js`, không gắn vào địa chỉ",
		false !== strpos( $P, "str_replace( '__BAN__'" ) && false !== strpos( $SW, "'__BAN__'" ), null );

	/* ═══ 4. 🔴 THỢ NỀN KHÔNG ĐƯỢC NHỚ HỘ ĐƯỜNG DỮ LIỆU ═══════════════════════════════
	 * App có BA cổng gọi máy chủ (xem `head_block()`): /wp-json/, admin-ajax.php, và chính URL
	 * trang kèm `vhcp_api`. Bỏ sót cổng nào là bảng tiền hiện số của lần mở trước. */
	foreach ( array( "{$low}_api", '/wp-json/', 'admin-ajax.php' ) as $cong ) {
		t( "🔴 $b: thợ nền chừa cổng dữ liệu `$cong` ra", false !== strpos( $SW, $cong ), null );
	}
	t( "🔴 $b: và lượt gọi dữ liệu ĐI THẲNG, không qua tay thợ nền",
		(bool) preg_match( "/if \(laDuLieu\(u\)\) \{ return; \}/", $SW ), null );
	t( "🔴 $b: chỉ đụng GET, cùng tên miền",
		false !== strpos( $SW, "req.method !== 'GET'" )
		&& false !== strpos( $SW, "u.origin !== self.location.origin" ), null );
	/* 🔴 Trang app LẤY MẠNG TRƯỚC. Lấy cái đã nhớ trước thì bản vá hôm nay phải đợi tới lần mở
	   sau nữa mới tới tay người dùng — mà bộ này ra bản mỗi ngày. */
	t( "🔴 $b: trang app lấy MẠNG TRƯỚC, cái đã nhớ chỉ là đường lui",
		(bool) preg_match( "/req\.mode === 'navigate'[\s\S]{0,400}fetch\(req\)[\s\S]{0,400}\.catch\([\s\S]{0,200}caches\.match\(TRANG\)/", $SW ), null );
	t( "   mất mạng mà chưa nhớ gì thì có trang nói rõ, không trắng trơn",
		false !== strpos( $SW, 'function trangLui()' ) && false !== strpos( $SW, 'Chưa mở được trang chi phí' ), null );

	/* ═══ 5. 🔴 BỐN BẢN BỐN APP RIÊNG ═════════════════════════════════════════════════ */
	foreach ( array( 'id', 'start_url', 'scope' ) as $k ) {
		t( "🔴 $b: manifest lấy `$k` từ đường của trang, không gõ cứng",
			(bool) preg_match( "/'$k'\s*=>\s*\\\$goc,/", $P ), null );
	}
	t( "🔴 $b: tên app lấy từ `ten_trang()` — script tách đã đổi sẵn cho từng bản",
		false !== strpos( $P, "{$pre}_App::ten_trang()" ) );
	t( "⚠️ $b: tên ngắn cắt còn 12 ký tự — iOS cắt giữa chừng thì đọc không ra",
		false !== strpos( $P, "mb_substr( \$ten, 0, 12 )" ), null );

	/* ═══ 6. 🔴 BIỂU TƯỢNG: iOS ĐỌC THẺ RIÊNG, ANDROID ĐỌC MANIFEST ═══════════════════ */
	t( "🔴 $b: có thẻ `apple-touch-icon` — iPhone KHÔNG đọc `icons` của manifest",
		false !== strpos( $P, 'rel="apple-touch-icon"' ) );
	foreach ( array( 'bieu-tuong-180.png', 'bieu-tuong-192.png', 'bieu-tuong-512.png', 'bieu-tuong-512-maskable.png' ) as $png ) {
		$f = "$dir/assets/img/app/$png";
		t( "$b: có $png", is_readable( $f ) && filesize( $f ) > 200, is_readable( $f ) ? filesize( $f ) : 'thiếu' );
		t( "   và là PNG thật", is_readable( $f ) && "\x89PNG" === substr( (string) file_get_contents( $f ), 0, 4 ), null );
	}
	/* ⚠️ `maskable` để RIÊNG một mục: một tấm không thể vừa chừa lề rộng vừa kín khung. */
	t( "⚠️ $b: `maskable` là một mục RIÊNG, không gộp `any maskable`",
		false !== strpos( $P, "'purpose' => 'maskable'" ) && false === strpos( $P, 'any maskable' ), null );
	t( "⚠️ $b: KHÔNG khoá hướng dọc — bảng mã rộng, kế toán phải xoay ngang được",
		false === strpos( $P, "'orientation'" ), null );

	/* ═══ 7. LỚP VỎ KHÔNG ĐƯỢC CHẶN TRANG CHẠY ═══════════════════════════════════════ */
	t( "🔴 $b: trình duyệt không có `serviceWorker` thì thôi, không chặn gì",
		false !== strpos( $PWAJS, "!('serviceWorker' in navigator)" ) && false !== strpos( $PWAJS, 'return;' ), null );
	t( "🔴 $b: đọc địa chỉ từ thuộc tính thẻ, KHÔNG gõ cứng đường của bản gốc",
		false !== strpos( $PWAJS, "getAttribute('data-sw')" ) && false === strpos( $PWAJS, '/chi-phi' ), null );
	t( "   và nuốt lỗi đăng ký (http thường thì `register` ném lỗi)",
		false !== strpos( $PWAJS, '.catch(function () {})' ), null );
	/* ⚠️ Không khai `viewport` lần hai — `templates/app.html` khai rồi. */
	t( "⚠️ $b: lớp vỏ KHÔNG khai `viewport` lần hai",
		false === strpos( $P, 'name="viewport"' ), null );
}

if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: trang chi phí chạy được như một app điện thoại, bốn bản bốn app riêng.\n";
