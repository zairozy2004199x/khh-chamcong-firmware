<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * APP CHẤM CÔNG (vhcp-cc-app) — LỚP VỎ PWA CHO TRANG `/cham-cong-online`.
 *
 * Anh Thắng 16/09/2026: *"Tạo app chấm công online … App iphone qua PWA"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO BỘ NÀY CẦN BÀI THỬ, DÙ NÓ "CHỈ LÀ MẤY THẺ META"
 *
 * PWA hỏng KHÔNG BAO GIỜ đỏ. Không có thẻ `apple-touch-icon` thì iPhone vẫn cài app — chỉ là
 * biểu tượng thành một tấm chụp màn hình nhoè, và sửa thì phải xoá app cài lại. Thợ nền để sai
 * thư mục thì `register()` vẫn trả về thành công, DevTools vẫn xanh, mà không đỡ được lượt nào.
 * Nhớ nhầm một lượt `?viec=gio` thì ảnh chấm công bị đóng dấu giờ của hôm trước. Cả ba ca đều
 * chỉ lộ ra trên máy người dùng, sau khi đã phát cho cả công ty.
 *
 * ⚠️ BÀI NÀY CHẠY THẬT `CCAPP_App::chen()` và `khoi_head()` với mấy hàm WordPress giả, chứ không
 *    chỉ dò chuỗi trong tệp.
 *
 * Chạy: php tools/test/kiem-cc-app.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * BỎ CHÚ THÍCH TRƯỚC KHI DÒ MÃ.
 *
 * 🔴 ĐÃ DÍNH BA LẦN TRONG KHO NÀY: phép thử dò một chuỗi, chuỗi ấy nằm trong CHÚ THÍCH cảnh báo
 *    "đừng viết thế này", thế là bài xanh trong khi mã chưa hề làm điều nó khẳng định (hoặc đỏ
 *    trong khi mã đúng). Chú thích của bộ này nhắc tên đúng những thứ nó cấm, nên không bỏ đi
 *    thì gần như chắc chắn dò trúng chú thích.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function bo_chu_thich( $php ) {
	$ra = '';
	foreach ( token_get_all( $php ) as $tk ) {
		if ( is_array( $tk ) ) {
			if ( T_COMMENT === $tk[0] || T_DOC_COMMENT === $tk[0] ) { $ra .= "\n"; continue; }
			$ra .= $tk[1];
		} else {
			$ra .= $tk;
		}
	}
	return $ra;
}

$GOC = dirname( dirname( __DIR__ ) );
$BO  = $GOC . '/wordpress/vhcp-cc-app';

$CHINH = file_get_contents( $BO . '/vhcp-cc-app.php' );
$LOP   = file_get_contents( $BO . '/includes/class-ccapp-app.php' );
$SW    = file_get_contents( $BO . '/assets/sw.js' );
$APPJS = file_get_contents( $BO . '/assets/app.js' );
$CAPN  = file_get_contents( $BO . '/includes/class-ccapp-tu-cap-nhat.php' );

/* Bản CHỈ CÒN MÃ của lớp chính — mọi phép dò mã dưới đây dùng bản này, xem khối trên. */
$LOP_MA = bo_chu_thich( $LOP );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. SỐ BẢN KHAI HAI CHỖ, PHẢI BẰNG NHAU
 *
 * Header `Version:` là thứ WordPress đọc để bày nút Cập nhật, và cũng là thứ workflow phát hành
 * đọc để đặt tên tag. `CCAPP_VERSION` là thứ mã chạy in ra (vào tên kho của thợ nền). Lệch nhau
 * thì bản mới lên mà thợ nền cũ vẫn giữ nguyên kho cũ — người dùng mở app thấy bản cũ, mà màn
 * Plugin thì khoe đã cập nhật xong.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/m', $CHINH, $m_h );
preg_match( "/define\(\s*'CCAPP_VERSION',\s*'([0-9.]+)'\s*\)/", $CHINH, $m_c );
$VER = isset( $m_h[1] ) ? $m_h[1] : '';
t( '🔴 có số bản ở header Version:', '' !== $VER, $VER );
teq( '🔴 hằng CCAPP_VERSION bằng đúng header', $VER, isset( $m_c[1] ) ? $m_c[1] : '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. TIỀN TỐ TAG PHẢI TRÙNG TÊN THƯ MỤC — CÁI BẪY ĐÃ NUỐT `khh-doanh-thu`
 *
 * `.github/workflows/phat-hanh.yml` đặt tag là `<tên thư mục>-v<số bản>`. Bộ tự cập nhật lại lọc
 * tag theo hằng `TIEN_TO` gõ tay. Hai chỗ lệch nhau thì Releases có bản mới mà plugin không bao
 * giờ thấy — và không ai phát hiện, vì luồng phát hành vẫn xanh. `khh-doanh-thu` dính đúng ca
 * này suốt từ ngày ra đời (xem khối dài trong tools/build-plugin-zip.sh).
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
preg_match( "/const TIEN_TO = '([^']+)'/", $CAPN, $m_tt );
teq( '🔴 tiền tố tag = tên thư mục + "-v"', 'vhcp-cc-app-v', isset( $m_tt[1] ) ? $m_tt[1] : '' );
t( '   và tự khai vào bảng trang /it', false !== strpos( $CAPN, "add_filter( 'vhcp_tu_cap_nhat_ds'" ), '' );

/* Trình đóng gói phải biết bộ này, nếu không `bash tools/build-plugin-zip.sh` bỏ qua trong im
   lặng và không có .zip nào để cài tay lúc Releases chưa chạy. */
$BUILD = file_get_contents( $GOC . '/tools/build-plugin-zip.sh' );
t( '🔴 build-plugin-zip.sh có đóng gói vhcp-cc-app',
	1 === preg_match( '/dong_goi "[^"]*" vhcp-cc-app\b/', $BUILD ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. BỆ ĐỠ — nạp LỚP THẬT với mấy hàm WordPress giả
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
define( 'ABSPATH', $GOC . '/' );
define( 'CCAPP_VERSION', $VER );
define( 'CCAPP_DIR', $BO . '/' );
define( 'CCAPP_URL', 'https://khmatrix.com/wp-content/plugins/vhcp-cc-app/' );

$OPT = array( 'permalink_structure' => '/%postname%/' );
function get_option( $k, $md = false ) { global $OPT; return array_key_exists( $k, $OPT ) ? $OPT[ $k ] : $md; }
function home_url( $p = '/' ) { return rtrim( 'https://khmatrix.com', '/' ) . $p; }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ) ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
$LOC = array();
function apply_filters( $ten, $gt ) { global $LOC; return isset( $LOC[ $ten ] ) ? $LOC[ $ten ] : $gt; }

require_once $BO . '/includes/class-ccapp-app.php';

teq( 'đường dẫn app mặc định', 'https://khmatrix.com/cc/', CCAPP_App::url() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. KHỐI <head> — MỖI THẺ Ở ĐÂY LÀ MỘT CA HỎNG CÂM
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$H = CCAPP_App::khoi_head();

t( '🔴 có apple-touch-icon (iPhone KHÔNG đọc icons trong manifest)',
	false !== strpos( $H, 'rel="apple-touch-icon"' ), $H );
t( '   trỏ vào tấm 180px',
	false !== strpos( $H, 'bieu-tuong-180.png' ), '' );
t( '🔴 có <link rel="manifest">', false !== strpos( $H, 'rel="manifest"' ), '' );
t( '   manifest trỏ đúng /cc/manifest.json',
	false !== strpos( $H, 'https://khmatrix.com/cc/manifest.json' ), '' );
t( '🔴 khai apple-mobile-web-app-capable',
	false !== strpos( $H, 'name="apple-mobile-web-app-capable" content="yes"' ), '' );
t( '   và tên bày dưới biểu tượng', false !== strpos( $H, 'apple-mobile-web-app-title' ), '' );

/* ⚠️ Thanh trạng thái để `black`, CỐ Ý không `black-translucent`: bản translucent đẩy nội dung
   chui lên dưới thanh giờ/pin, và muốn không bị che thì phải chêm safe-area vào bố cục của
   TRANG TRẠM — bố cục do bộ khác giữ và đang sửa song song. */
t( '🔴 thanh trạng thái KHÔNG dùng black-translucent (sẽ che mất đầu trang trạm)',
	false === strpos( $H, 'black-translucent' ), '' );

/* 🔴 HAI THẺ VIEWPORT LÀ MỘT CUỘC TRANH GIÀNH KHÔNG AI THẮNG RÕ. Trang trạm đã khai viewport
   của nó; khai thêm ở đây là hai luật cho cùng một thứ, và ngày nào bên kia đổi thì không đoán
   được cái nào đang có hiệu lực. */
t( '🔴 KHÔNG khai thêm thẻ viewport (trang trạm khai rồi)',
	false === stripos( $H, 'name="viewport"' ), $H );

t( '   có chỗ móc ccapp_chen_head cho tính năng thêm sau',
	false !== strpos( $LOP_MA, "apply_filters( 'ccapp_chen_head'" ), '' );
$LOC['ccapp_chen_head'] = '<meta name="thu-nghiem" content="1">';
t( '   và chỗ móc ấy chạy thật',
	false !== strpos( CCAPP_App::khoi_head(), 'thu-nghiem' ), '' );
$LOC = array();

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. CHÈN VÀO ĐÚNG CHỖ — ba nhánh của `chen()`
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$trang = "<!doctype html><html><head><meta charset=\"utf-8\"><title>Chấm công</title></head><body><div id=x></div></body></html>";
$ra    = CCAPP_App::chen( $trang );
t( '🔴 chèn TRƯỚC </head>', strpos( $ra, 'rel="manifest"' ) < strpos( $ra, '</head>' ), '' );
t( '   và sau thẻ <title> có sẵn của trang trạm',
	strpos( $ra, '<title>' ) < strpos( $ra, 'rel="manifest"' ), '' );
t( '   không đụng vào thân trang', false !== strpos( $ra, '<div id=x></div>' ), '' );
teq( '   không nhân đôi manifest', 1, substr_count( $ra, 'rel="manifest"' ) );

/* Đường lui: trang trạm bị viết lại tới mức không còn </head>. App phải vẫn có manifest, chứ
   không được lặng lẽ trả về trang y nguyên — trả y nguyên thì app "chạy" mà không cài được. */
$ra2 = CCAPP_App::chen( '<html><body class="x">xin chào</body></html>' );
t( '🔴 mất </head> thì vẫn chèn được (nhét đầu <body>)',
	false !== strpos( $ra2, 'rel="manifest"' ), $ra2 );
t( '   và nhét SAU thẻ mở body, không phá thuộc tính của nó',
	false !== strpos( $ra2, '<body class="x">' )
	&& strpos( $ra2, '<body class="x">' ) < strpos( $ra2, 'rel="manifest"' ), $ra2 );

$ra3 = CCAPP_App::chen( 'chỉ là chữ' );
t( '   không có cả <body> cũng không mất nội dung',
	false !== strpos( $ra3, 'chỉ là chữ' ) && false !== strpos( $ra3, 'rel="manifest"' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. CHỖ CHÈN PHẢI CÒN TỒN TẠI BÊN `vhcp-cham-cong`
 *
 * Nhánh chính của `chen()` bám vào `</head>` trong `templates/tram.php`. Bộ kia đang được sửa
 * song song; ngày họ dựng lại template mà không còn `</head>` thì app tụt xuống đường lui —
 * chạy được nhưng không còn đúng ý. Bài này báo TRƯỚC, ở đây, chứ không đợi người dùng báo.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$TRAM_TPL = $GOC . '/wordpress/vhcp-cham-cong/templates/tram.php';
if ( is_readable( $TRAM_TPL ) ) {
	$tpl = file_get_contents( $TRAM_TPL );
	t( '🔴 templates/tram.php vẫn còn </head> để chèn vào',
		false !== stripos( $tpl, '</head>' ), '' );

	/* 🔴 THỢ NỀN NHỚ NGUYÊN TRANG `/cc/` LÀM ĐƯỜNG LUI KHI MẤT MẠNG. Nhớ được là vì trang ấy
	   GIỐNG HỆT NHAU với mọi người — `VHCC_Tram::render()` chỉ nhét cấu hình máy móc vào HTML,
	   người dùng do JavaScript nạp sau khi gõ PIN. Ngày nào bên kia in tên hay mã NV thẳng vào
	   HTML thì kho của thợ nền thành chỗ rò rỉ: máy dùng chung ở cơ sở, người sau mở app ra
	   thấy trang của người trước. */
	$tram_php = file_get_contents( $GOC . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
	if ( preg_match( '/public static function render\(\).*?include VHCC_DIR/s', $tram_php, $m_r ) ) {
		$than = $m_r[0];
		$xau  = array( '$u[', 'ma_nv', 'ho_ten', 'nguoi(', 'phien_tu_cookie' );
		$dinh = array();
		foreach ( $xau as $x ) { if ( false !== strpos( $than, $x ) ) { $dinh[] = $x; } }
		t( '🔴 render() không nhét dữ liệu của NGƯỜI DÙNG vào HTML (thợ nền nhớ trang này)',
			! $dinh, implode( ', ', $dinh ) );
	} else {
		t( '🔴 đọc được thân render() của trang trạm', false, 'không khớp được hàm' );
	}
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. THỢ NỀN — bốn luật ở đầu sw.js
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* Luật 1 — lệnh của trạm KHÔNG BAO GIỜ được nhớ. Nhớ một lượt `viec=gio` là ảnh chấm công bị
   đóng dấu giờ của lần mở app trước, mà tấm ảnh ấy là thứ duy nhất dùng để đối chiếu. */
t( '🔴 sw.js bỏ qua mọi lượt ?viec= (trừ ô bản đồ)',
	1 === preg_match( '/if \(viec !== null\) \{ return; \}/', $SW ), '' );
t( '   và nhánh ô bản đồ đứng TRƯỚC chốt ấy',
	strpos( $SW, "viec === 'o'" ) < strpos( $SW, 'if (viec !== null)' ), '' );

/* Luật 4 — POST (chính là lượt chấm công) không đi qua tay thợ nền. */
t( '🔴 sw.js chỉ đụng vào GET',
	1 === preg_match( "/req\.method !== 'GET'/", $SW ), '' );
t( '   và chỉ cùng tên miền',
	1 === preg_match( '/u\.origin !== self\.location\.origin/', $SW ), '' );

/* Luật 2 — trang app lấy mạng trước. Lấy kho trước thì bản vá đẩy lên hôm nay phải đợi tới lần
   mở sau nữa mới tới tay người dùng. */
t( "🔴 trang app lấy mạng trước, kho chỉ là đường lui",
	1 === preg_match( "/req\.mode === 'navigate'/", $SW )
	&& preg_match( "/req\.mode === 'navigate'.*?fetch\(req\)/s", $SW ), '' );

/* Số bản đi vào NỘI DUNG tệp. Gắn `?v=` lên địa chỉ là đăng ký một thợ nền KHÁC, thợ cũ vẫn nằm
   đó quản trang như thường — bản mới không bao giờ lên. */
t( '🔴 sw.js mang ô trống __BAN__ để PHP thay số bản vào nội dung',
	false !== strpos( $SW, '__BAN__' ), '' );
t( '   và PHP có thay thật',
	false !== strpos( $LOP_MA, "str_replace( '__BAN__', CCAPP_VERSION" ), '' );
t( '   địa chỉ đăng ký thợ nền KHÔNG mang ?v=',
	1 !== preg_match( "/self::url\(\) \. 'sw\.js\?/", $LOP_MA ), '' );

/* Tên kho mang số bản → bản mới là kho mới, và `activate` dọn kho cũ. Thiếu chỗ dọn thì mỗi bản
   để lại một kho, đến lúc máy hết chỗ thì trình duyệt xoá SẠCH — kể cả kho đang dùng. */
t( '   tên kho mang số bản', 1 === preg_match( "/KHO_VO\s*=\s*'ccapp-vo-' \+ BAN/", $SW ), '' );
t( '   và activate dọn kho bản cũ', false !== strpos( $SW, 'caches.delete' ), '' );
t( '   nhưng KHÔNG đụng kho của bộ khác',
	1 === preg_match( "/indexOf\('ccapp-'\) !== 0/", $SW ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 8. PHẠM VI THỢ NỀN — chỗ hỏng im lặng nhất của cả bộ
 *
 * Thợ nền chỉ quản được trang nằm DƯỚI thư mục chứa chính nó. Phục vụ nó từ
 * `/wp-content/plugins/…/assets/sw.js` thì đăng ký vẫn "thành công" mà không đỡ lượt nào.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 có luật đường dẫn cho /<slug>/sw.js',
	1 === preg_match( "/sw\\\\\.js.*ccapp=sw/", $LOP_MA ), '' );
t( '   và gửi kèm tiêu đề Service-Worker-Allowed',
	false !== strpos( $LOP_MA, 'Service-Worker-Allowed' ), '' );
t( '   app.js đăng ký bằng địa chỉ /cc/sw.js chứ không phải tệp trong plugin',
	false !== strpos( $LOP_MA, "'\" data-sw=\"' . esc_url( self::url() . 'sw.js' )" )
	|| false !== strpos( $LOP_MA, "data-sw=\"' . esc_url( self::url() . 'sw.js' )" ), '' );
t( '   và app.js đọc đúng thuộc tính ấy',
	false !== strpos( $APPJS, "getAttribute('data-sw')" ), '' );

/* Thiếu HTTPS thì trình duyệt lặng lẽ không cho đăng ký. Màn Cài đặt phải nói ra. */
$CAI = file_get_contents( $BO . '/includes/class-ccapp-cai-dat.php' );
t( '🔴 màn Cài đặt có soát HTTPS (thiếu là KHÔNG cài được app, và không báo gì)',
	false !== strpos( $CAI, 'is_ssl()' ), '' );
t( '   soát cả việc đã cài Chấm Công chưa', false !== strpos( $CAI, 'co_tram()' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 9. MANIFEST + BIỂU TƯỢNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
ob_start();
try { CCAPP_App::ra_manifest(); } catch ( Throwable $e ) { /* `exit` không bắt được, xem dưới */ }
$mf_txt = ob_get_clean();
/* `ra_manifest()` kết bằng `exit` nên không gọi thẳng được trong bài thử. Đọc tờ khai từ chính
   mã nguồn thay vì chép tay một bản thứ hai ở đây — chép tay là bài xanh trên một tờ khai không
   còn ai dùng. */
t( '🔴 manifest khai display=standalone (không thì mở ra vẫn thấy thanh địa chỉ)',
	false !== strpos( $LOP_MA, "'display'          => 'standalone'" ), '' );
t( "   start_url và scope cùng là đường dẫn app",
	false !== strpos( $LOP_MA, "'start_url'        => \$goc" )
	&& false !== strpos( $LOP_MA, "'scope'            => \$goc" ), '' );
t( '   có `id` cố định (đổi đường dẫn không đẻ thêm biểu tượng thứ hai)',
	false !== strpos( $LOP_MA, "'id'               => \$goc" ), '' );

/* ⚠️ `maskable` phải là MỘT MỤC RIÊNG. Gộp `"any maskable"` vào một tấm là Android vừa cắt nó
   theo hình máy (cụt viền) vừa dùng nguyên tấm chỗ khác — một tấm không làm được cả hai. */
t( '🔴 maskable để riêng một mục, không gộp "any maskable"',
	false === strpos( $LOP_MA, 'any maskable' )
	&& false !== strpos( $LOP_MA, "'purpose' => 'maskable'" ), '' );

foreach ( array( '180', '192', '512', '512-maskable' ) as $co ) {
	$tep = $BO . '/assets/bieu-tuong-' . $co . '.png';
	t( '   có tệp bieu-tuong-' . $co . '.png', is_readable( $tep ), $tep );
}
$kt = @getimagesize( $BO . '/assets/bieu-tuong-180.png' );
teq( '🔴 tấm của iPhone đúng 180×180', array( 180, 180 ),
	$kt ? array( $kt[0], $kt[1] ) : null );
$kt5 = @getimagesize( $BO . '/assets/bieu-tuong-512.png' );
teq( '   tấm 512 đúng cỡ', array( 512, 512 ), $kt5 ? array( $kt5[0], $kt5[1] ) : null );

/* Mọi tấm khai trong manifest phải có thật trên đĩa — khai một tên gõ sai thì Android bỏ qua
   biểu tượng đó trong im lặng và lấy tấm còn lại (hoặc không tấm nào). */
preg_match_all( "/CCAPP_URL \. 'assets\/(bieu-tuong-[^']+\.png)'/", $LOP_MA, $m_ic );
$thieu = array();
foreach ( array_unique( $m_ic[1] ) as $ten ) {
	if ( ! is_readable( $BO . '/assets/' . $ten ) ) { $thieu[] = $ten; }
}
t( '🔴 mọi biểu tượng khai trong mã đều có tệp thật', ! $thieu, implode( ', ', $thieu ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 10. DẢI NHẮC CÀI APP — iPhone không có nút cài, chỉ có đường chỉ bằng chữ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '   đã cài rồi thì không nhắc nữa',
	false !== strpos( $APPJS, 'navigator.standalone' )
	&& false !== strpos( $APPJS, "'(display-mode: standalone)'" ), '' );
/* 🔴 Chrome: không `preventDefault()` thì Chrome tự hiện thanh của nó và `prompt()` sau đó vô
   tác dụng — nút "Cài app" của mình bấm không lên, không báo lỗi gì. */
t( '🔴 Android: có preventDefault() trước khi giữ lời mời cài',
	1 === preg_match( '/beforeinstallprompt.*?ev\.preventDefault\(\)/s', $APPJS ), '' );
t( '🔴 iPhone: chỉ đường bằng chữ (Safari không cho gọi hộp cài)',
	false !== mb_strpos( $APPJS, 'Thêm vào MH chính' ), '' );
t( '   dải nhắc chừa chỗ cho vạch Home (safe-area)',
	false !== strpos( $APPJS, 'env(safe-area-inset-bottom' ), '' );
t( '   bấm "Để sau" thì nhớ, không nhắc lại mỗi lần mở',
	false !== strpos( $APPJS, 'ccapp_tat_nhac' ), '' );
/* Chế độ riêng tư của Safari chặn localStorage và NÉM lỗi — không bọc thì cả tệp chết ngay dòng
   đầu, và trang chấm công mất luôn thợ nền. */
t( '🔴 localStorage bị chặn cũng không làm chết cả tệp',
	1 === preg_match( '/localStorage\.setItem[^;]*;\s*\} catch/s', $APPJS ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 11. KHÔNG DỰNG ĐƯỜNG GHI THỨ HAI
 *
 * Cả bộ này KHÔNG được có một dòng nào tự ghi giờ công. Nó gọi `VHCC_Tram::render()` và hết.
 * Ngày nào ở đây mọc ra một hàm chấm công riêng là hai bộ luật, và sớm muộn hai bộ lệch nhau.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$moi_php = '';
foreach ( glob( $BO . '/includes/*.php' ) as $f ) { $moi_php .= bo_chu_thich( file_get_contents( $f ) ); }
$moi_php .= bo_chu_thich( $CHINH );
t( '🔴 không tự gọi VHCC_Online::cham_cong()',
	false === strpos( $moi_php, 'cham_cong(' ), '' );
t( '🔴 không tự đụng vào bảng dữ liệu nào', false === strpos( $moi_php, '$wpdb' ), '' );
teq( '🔴 gọi VHCC_Tram::render() đúng MỘT chỗ', 1, substr_count( $moi_php, 'VHCC_Tram::render()' ) );
t( '   và chưa cài Chấm Công thì báo bằng chữ, không trả trang trắng',
	false !== strpos( $LOP_MA, 'trang_thieu' ) && false !== mb_strpos( $LOP_MA, 'chưa chạy được' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: app cài được lên iPhone, thợ nền không bao giờ nhớ hộ lượt chấm công.\n";
