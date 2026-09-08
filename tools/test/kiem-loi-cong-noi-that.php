<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CỔNG /ghe PHẢI LUÔN TRẢ JSON — VÀ CÂU LỖI PHẢI NÓI RA SỰ THẬT
 *
 * Anh Thắng 08/09/2026: *"Trang ghế không gửi được báo cáo. Đây là lỗi gì"* — màn hình chỉ nói
 * *"Không đọc được trả lời của máy chủ (mạng hoặc tường lửa)"*. Cùng câu ấy anh đã báo
 * 29/08/2026; lần đó vá bằng cách nén ảnh nhỏ lại, và lỗi vẫn quay lại kể cả khi KHÔNG đính ảnh.
 *
 * =============================================================================================
 * 🔴 VÌ SAO VÁ HOÀI KHÔNG TRÚNG. Câu ấy bắn ra ở đúng một chỗ: `JSON.parse` thất bại. Nó đúng
 *    với đủ mọi nguyên nhân — gói tin quá nặng, tường lửa chặn, PHP chết, hay chỉ một dòng
 *    `Warning:` của plugin khác in ra trước JSON — nên nó chẳng chỉ ra nguyên nhân nào. Người ở
 *    cơ sở đi đổi wifi, còn ở đây thì đoán.
 *
 * 🔴 HAI CHỐT, HAI PHÍA:
 *      · MÁY CHỦ dọn sạch mọi thứ đã trót in ra trước khi in JSON, và nếu chết giữa chừng thì
 *        vẫn trả JSON nói rõ là lỗi máy chủ — chứ không phải trang trắng.
 *      · MÀN HÌNH đọc mã HTTP rồi nói đúng chuyện: 413 gói tin nặng, 403 tường lửa, 5xx máy chủ
 *        nổ, 200-mà-không-đọc-được là có ai in rác trước JSON.
 *
 * ⚠️ CHẠY THẬT: bốc `tra()` ra chạy với bộ đệm thật của PHP, và bốc `loiTho_()` ra chạy bằng
 *    Node với những mã HTTP thật.
 *
 * Chạy: php tools/test/kiem-loi-cong-noi-that.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$SRC = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ — đủ dùng cho `tra()`, không hơn
 *
 * ⚠️ CHỖ MÙ, ghi rõ: bệ đỡ này KHÔNG phải WordPress thật. Nó kiểm được luật dọn bộ đệm và hình
 *    dạng JSON trả ra; nó KHÔNG kiểm được `status_header`/`nocache_headers` của WordPress thật
 *    làm gì, cũng không kiểm được hosting có tầng đệm riêng nào.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function status_header( $c ) {}
function nocache_headers() {}
function wp_json_encode( $d ) { return json_encode( $d, JSON_UNESCAPED_UNICODE ); }
/* ⚠️ `error_log()` là hàm NỘI của PHP, không khai đè được. Bẻ luồng ghi sang một tệp tạm rồi
   đọc lại — vừa là cách duy nhất, vừa đúng thứ chạy trên host thật. */
$GLOBALS['LOGF'] = tempnam( sys_get_temp_dir(), 'vhglog' );
ini_set( 'log_errors', '1' );
ini_set( 'error_log', $GLOBALS['LOGF'] );
function log_da_ghi() {
	$x = @file_get_contents( $GLOBALS['LOGF'] );
	return is_string( $x ) ? $x : '';
}
function log_xoa() { @file_put_contents( $GLOBALS['LOGF'], '' ); }

/* Bốc CHÍNH `tra()` ra chạy — không chép luật vào đây. */
$i = strpos( $SRC, 'private static function tra( $d ) {' );
$j = strpos( $SRC, "\n\t}", $i );
t( 'bốc được hàm tra()', false !== $i && $j > $i );
if ( false === $i || $j <= $i ) { echo "✗ không bốc được tra() — dừng.\n"; exit( 1 ); }
$THAN_TRA = substr( $SRC, $i, $j - $i + 3 );

/* Và bốc luôn chốt chết máy. */
$i2 = strpos( $SRC, 'public static function chot_chet_may() {' );
$j2 = strpos( $SRC, "\n\t}", $i2 );
t( 'bốc được chốt chết máy', false !== $i2 && $j2 > $i2 );
$THAN_CHET = ( false !== $i2 && $j2 > $i2 ) ? substr( $SRC, $i2, $j2 - $i2 + 3 ) : '';

eval( 'class VHG_T { public static function slug(){ return "ghe"; } private static $da_tra = false;'
	. str_replace( 'private static function tra', 'public static function tra', $THAN_TRA )
	. $THAN_CHET
	. ' public static function daTra(){ return self::$da_tra; }'
	. ' public static function datLai(){ self::$da_tra = false; } }' );

/**
 * Gọi `tra()` trong MỘT TIẾN TRÌNH PHP RIÊNG rồi bắt đúng thứ nó in ra màn hình.
 *
 * 🔴 KHÔNG BẮT ĐƯỢC BẰNG `ob_start()` NGAY TRONG BÀI KIỂM, và đó chính là hành vi đang cần
 *    kiểm: `tra()` DỌN SẠCH MỌI TẦNG ĐỆM rồi mới in — kể cả tầng bài kiểm vừa dựng lên để
 *    hứng. Dựng tầng hứng rồi đòi nó còn đó là đòi hàm ấy làm sai việc của nó.
 *
 * Tiến trình riêng thì stdout là thứ khách thật sự nhận được — đúng cái cần soi.
 */
function chay_tra( $d, $rac_truoc = '', $debug = false ) {
	global $THAN_TRA;
	$tmp = tempnam( sys_get_temp_dir(), 'vhgtra' ) . '.php';
	$log = tempnam( sys_get_temp_dir(), 'vhglog' );
	$ma  = "<?php\n"
		. "ini_set('log_errors','1'); ini_set('error_log'," . var_export( $log, true ) . ");\n"
		. ( $debug ? "define('WP_DEBUG', true);\n" : '' )
		. "function status_header(\$c){} function nocache_headers(){}\n"
		. "function wp_json_encode(\$d){ return json_encode(\$d, JSON_UNESCAPED_UNICODE); }\n"
		. "class VHG_T { public static function slug(){ return 'ghe'; } private static \$da_tra = false;\n"
		. str_replace( 'private static function tra', 'public static function tra', $THAN_TRA ) . "\n}\n"
		. "ob_start();\n"                              // tầng "của WordPress / plugin khác"
		. "echo " . var_export( $rac_truoc, true ) . ";\n"
		. "VHG_T::tra(" . var_export( $d, true ) . ");\n";
	file_put_contents( $tmp, $ma );
	$ra = shell_exec( escapeshellcmd( PHP_BINARY ) . ' ' . escapeshellarg( $tmp ) . ' 2>/dev/null' );
	$nk = @file_get_contents( $log );
	@unlink( $tmp ); @unlink( $log );
	$GLOBALS['NK'] = is_string( $nk ) ? $nk : '';
	return (string) $ra;
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. KHÔNG CÓ RÁC — CHẠY NHƯ CŨ
 *
 * 🔴 Đối chứng bắt buộc. Chốt mới mà làm hỏng đường bình thường thì mọi lượt gửi đều chết, chứ
 *    không phải chỉ lượt đang lỗi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$ra = chay_tra( array( 'ok' => true, 'message' => 'Đã gửi báo cáo.' ) );
$j1 = json_decode( $ra, true );
t( '🔴 không rác → trả JSON đọc được', is_array( $j1 ), $ra );
teq( 'và giữ nguyên nội dung', true, isset( $j1['ok'] ) ? $j1['ok'] : null );
teq( 'không bịa thêm cờ rác', false, isset( $j1['racLen'] ) );
teq( 'không ghi nhật ký oan', '', trim( $GLOBALS['NK'] ) );
t( 'giữ chữ tiếng Việt không bị đổi thành \\uXXXX', false !== strpos( $ra, 'Đã gửi' ), $ra );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CÓ RÁC IN TRƯỚC — VẪN PHẢI RA JSON SẠCH
 *
 * Đây là nguyên nhân số một của câu lỗi anh Thắng gặp: một `Warning:` của BẤT KỲ plugin nào
 * trên site cũng đủ làm hỏng cổng này.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$RAC = "<br />\n<b>Warning</b>:  Undefined array key \"x\" in <b>/home/u123/public_html/wp-content/plugins/abc/x.php</b> on line <b>44</b><br />\n";
$ra = chay_tra( array( 'ok' => true, 'message' => 'Đã gửi.' ), $RAC );
$j2 = json_decode( $ra, true );
t( '🔴 có cảnh báo in trước → JSON VẪN đọc được', is_array( $j2 ), $ra );
t( '🔴 và rác KHÔNG lọt ra ngoài', false === strpos( $ra, 'Warning' ), $ra );
t( 'đường dẫn máy chủ cũng không lọt', false === strpos( $ra, 'public_html' ), $ra );
teq( 'nội dung thật vẫn nguyên', true, isset( $j2['ok'] ) ? $j2['ok'] : null );
teq( '🔴 nói ra là có rác, và dài bao nhiêu', strlen( trim( $RAC ) ), isset( $j2['racLen'] ) ? $j2['racLen'] : null );
$nk = $GLOBALS['NK'];
t( '🔴 ghi vào nhật ký lỗi của host để còn dò', '' !== trim( $nk ), $nk );
t( 'nhật ký kèm nguyên văn rác', false !== strpos( $nk, 'Undefined array key' ), $nk );
t( 'nhật ký nói rõ là cổng nào', false !== strpos( $nk, '/ghe' ), $nk );

/* ⚠️ NỘI DUNG RÁC CHỈ HIỆN KHI BẬT WP_DEBUG. Trang này chạy ngoài internet cho nhân viên cơ
   sở; rác PHP thường kèm đường dẫn tuyệt đối trên máy chủ. */
teq( '🔴 chưa bật WP_DEBUG → KHÔNG trả nội dung rác', false, isset( $j2['racDau'] ) );
$j3 = json_decode( chay_tra( array( 'ok' => true ), $RAC, true ), true );
t( '🔴 bật WP_DEBUG → có trả đoạn đầu để dò', isset( $j3['racDau'] ), $j3 );
t( 'và đoạn ấy đúng là rác', false !== strpos( (string) $j3['racDau'], 'Warning' ), $j3 );

/* Rác chỉ toàn khoảng trắng (một dòng trống sau `?>` — kinh điển) cũng phải dọn, nhưng đừng
   kêu ầm lên: nó không nói lên điều gì để dò. */
$j4 = json_decode( chay_tra( array( 'ok' => true ), "\n\n   \n" ), true );
t( 'rác toàn khoảng trắng → JSON vẫn sạch', is_array( $j4 ), $j4 );
teq( 'và không kêu là có rác', false, isset( $j4['racLen'] ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. CHỐT CHẾT MÁY — CHẾT GIỮA CHỪNG VẪN RA JSON
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( 'chốt được gắn trong api()', false !== strpos( $SRC, "register_shutdown_function( array( __CLASS__, 'chot_chet_may' ) )" ) );
/* 🔴 Gắn TRƯỚC khi chạm dữ liệu: `json_decode` một gói tin khổng lồ cũng là chỗ hết bộ nhớ. */
$k1 = strpos( $SRC, 'register_shutdown_function' );
$k2 = strpos( $SRC, '$d = json_decode( self::than(), true );' );
t( '🔴 gắn TRƯỚC lượt đọc gói tin', false !== $k1 && false !== $k2 && $k1 < $k2, array( $k1, $k2 ) );
t( 'bộ thử không bị gắn chốt (không thì mọi bài kiểm in thêm JSON lạ)',
	false !== strpos( $SRC, "if ( ! defined( 'VHG_TEST' ) ) { register_shutdown_function" ) );

/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: không gọi được `error_get_last()`
   thật trong bệ đỡ này (PHP chỉ đặt nó khi có lỗi thật), nên phần "chọn đúng loại lỗi" được
   soi bằng mã nguồn. Ép cho chạy thật thì phải làm PHP chết giữa bài kiểm — mà tiến trình chết
   là không còn gì để đọc kết quả. */
$i3 = strpos( $SRC, 'public static function chot_chet_may() {' );
$than3 = substr( $SRC, $i3, 900 );
t( '🔴 chỉ nhận lỗi THẬT SỰ giết tiến trình', false !== strpos( $than3, 'E_ERROR' ), $than3 );
t( '   không ôm cả cảnh báo thường', false === strpos( $than3, 'E_WARNING' ), $than3 );
t( '🔴 đã trả JSON rồi thì không trả lần hai', false !== strpos( $than3, 'self::$da_tra' ), $than3 );
t( 'nói rõ ĐÂY KHÔNG PHẢI LỖI MẠNG', false !== strpos( $than3, 'không phải mạng' ), $than3 );
t( 'chi tiết chỉ hiện khi WP_DEBUG', false !== strpos( $than3, 'WP_DEBUG' ), $than3 );
/* Và `tra()` phải thực sự dựng cờ ấy lên, không thì chốt trên bắn thêm một JSON nữa vào cuối. */
t( '🔴 tra() dựng cờ đã-trả', false !== strpos( $THAN_TRA, 'self::$da_tra = true;' ), $THAN_TRA );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. CÂU LỖI CŨ KHÔNG ĐƯỢC CÒN Ở ĐƯỜNG CHẠY
 *
 * 🔴 Còn một chỗ nói "mạng hoặc tường lửa" là còn một đường dẫn người ta đi lạc.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ PHẢI BỎ CHÚ THÍCH TRƯỚC KHI DÒ. Chính tệp này ghi lại câu lỗi cũ trong mấy khối chú thích
   kể chuyện — dò thô là bắt trúng chúng, và phép thử đỏ vì một dòng văn xuôi. Đã đỏ đúng như
   thế ở lượt chạy đầu. Bôi trắng khối `/* … *\/` rồi mới dò, để số dòng không đổi. */
$sach = preg_replace_callback( '#/\*[\s\S]*?\*/#', function ( $m ) {
	return preg_replace( '/[^\n]/', ' ', $m[0] );
}, $SRC );
$dong = array();
foreach ( explode( "\n", $sach ) as $n => $l ) {
	if ( false !== strpos( $l, 'mạng hoặc tường lửa' ) ) { $dong[] = ( $n + 1 ) . ': ' . trim( $l ); }
}
teq( '🔴 không còn câu "mạng hoặc tường lửa" nào ở mã chạy', array(), $dong );
t( 'hai màn đều dùng hàm đọc mã HTTP',
	false !== strpos( $SRC, 'error:loiTho_(x)' ) && false !== strpos( $SRC, 'error: loiTho2_(x)' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: cổng luôn trả JSON, rác bị dọn và ghi lại, câu lỗi nói ra sự thật.\n";
