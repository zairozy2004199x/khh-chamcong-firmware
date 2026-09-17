<?php
/**
 * KIỂM NHẮC CHẤM CÔNG — khoá VAPID (CCAPP_Khoa) và luật quyết định nhắc (CCAPP_Nhac).
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 VÌ SAO BÀI NÀY PHẢI KỸ HƠN VẺ NGOÀI CỦA MỘT "TÍNH NĂNG THÔNG BÁO"
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Thông báo đẩy hỏng theo kiểu KHÔNG BAO GIỜ ĐỎ. Ký sai một byte thì Apple trả 401 — mà 401 ấy
 * nằm trong một lượt wp_remote_post chạy ngầm lúc 7 giờ 50 sáng, không ai nhìn. Kết quả người
 * dùng thấy là: không có thông báo nào. Y hệt "hôm nay không ai quên chấm công".
 *
 * Và có một lỗi ở đây chỉ hiện ra khoảng **1/256 lượt**: chữ ký ECDSA DER có r hoặc s dài 31
 * byte khi byte đầu tình cờ là 0. Cắt cứng 32 byte cuối thì 255/256 lượt đúng. Một lỗi hiện ra
 * một lần trong hai trăm lần là lỗi không ai tìm ra bằng cách dùng thử — chỉ bắt được bằng cách
 * ký vài trăm lượt rồi kiểm từng lượt, đúng việc bài này làm.
 *
 * Chạy: php tools/test/kiem-nhac-cham-cong.php
 */

require_once __DIR__ . '/wp-stub.php';

$dat = 0; $truot = array();

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) );
}

$goc = dirname( dirname( __DIR__ ) );
$bo  = $goc . '/wordpress/vhcp-cc-app';

if ( ! defined( 'CCAPP_VERSION' ) ) { define( 'CCAPP_VERSION', 'thu' ); }
require_once $bo . '/includes/class-ccapp-khoa.php';
require_once $bo . '/includes/class-ccapp-nhac.php';

/* ═══════════════════════════════════════════════════════════════════════ 1. BASE64URL */

t( 'base64url không còn dấu =', false === strpos( CCAPP_Khoa::b64u( 'abcde' ), '=' ) );
$tho = "\xfb\xff\xfe\x01";
$b   = CCAPP_Khoa::b64u( $tho );
t( '🔴 base64url không còn dấu + hay /', false === strpos( $b, '+' ) && false === strpos( $b, '/' ), $b );
t( 'giải ra đúng chuỗi cũ', $tho === CCAPP_Khoa::b64u_giai( $b ) );
for ( $i = 1; $i <= 40; $i++ ) {
	$r = random_bytes( $i );
	if ( $r !== CCAPP_Khoa::b64u_giai( CCAPP_Khoa::b64u( $r ) ) ) {
		t( 'base64url đi-về nguyên vẹn ở mọi độ dài (' . $i . ')', false );
		break;
	}
}
t( 'base64url đi-về nguyên vẹn ở mọi độ dài', true );

/* ═══════════════════════════════════════════════════════════════════════ 2. ĐỆM 32 BYTE */

t( 'số ngắn được đệm 0 vào ĐẦU', "\x00\x00" . str_repeat( "\x11", 30 )
	=== CCAPP_Khoa::day32( str_repeat( "\x11", 30 ) ) );
t( '🔴 số 33 byte (có 0x00 dẫn đầu của DER) bị bỏ byte thừa',
	str_repeat( "\x22", 32 ) === CCAPP_Khoa::day32( "\x00" . str_repeat( "\x22", 32 ) ) );
t( 'số đúng 32 byte giữ nguyên', str_repeat( "\x33", 32 ) === CCAPP_Khoa::day32( str_repeat( "\x33", 32 ) ) );
t( 'chuỗi rỗng -> 32 byte 0', str_repeat( "\x00", 32 ) === CCAPP_Khoa::day32( '' ) );

/* ═══════════════════════════════════════════════════════════════════════ 3. DER -> r‖s */

if ( ! function_exists( 'openssl_pkey_new' ) ) {
	t( 'máy chạy bài kiểm có OpenSSL', false );
} else {
	$res = openssl_pkey_new( array( 'curve_name' => 'prime256v1',
		'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	t( 'sinh được khoá P-256 để thử', (bool) $res );
	/* ⚠️ `openssl_verify` đòi khoá CÔNG KHAI. Đưa khoá riêng vào thì nó không tự rút ra được và
	   trả về -1 kèm cảnh báo — trông y như "chữ ký sai", mà chữ ký lại đúng. */
	$ct_thu  = openssl_pkey_get_details( $res );
	$pub_thu = openssl_pkey_get_public( $ct_thu['key'] );

	/* 🔴 KÝ NHIỀU LƯỢT, KHÔNG KÝ MỘT LƯỢT. Lỗi r/s ngắn chỉ hiện khoảng 1/256 lượt; ký một lần
	   rồi kết luận "đúng" là đúng cái cách lỗi ấy sống sót qua mọi lần thử tay. 600 lượt cho xác
	   suất lọt gần như bằng không mà vẫn chạy trong một giây. */
	$sai = 0;
	$ngan = 0;
	for ( $i = 0; $i < 600; $i++ ) {
		$der = '';
		openssl_sign( 'thu-' . $i, $der, $res, 'sha256' );
		$raw = CCAPP_Khoa::der_sang_raw( $der );
		if ( 64 !== strlen( $raw ) ) { $sai++; continue; }

		/* Đếm số lượt mà r hoặc s ngắn hơn 32 byte — chính là ca bẫy. Đọc r/s thẳng từ DER. */
		$j = 2;
		if ( ord( $der[1] ) & 0x80 ) { $j += ( ord( $der[1] ) & 0x7F ); }
		$lr = ord( $der[ $j + 1 ] );
		$ls = ord( $der[ $j + 2 + $lr + 1 ] );
		if ( $lr < 32 || $ls < 32 ) { $ngan++; }

		/* Và phép đối chiếu thật: dựng LẠI DER từ r‖s rồi nhờ chính OpenSSL xác minh. Chữ ký
		   dựng lại mà xác minh được thì phép chuyển không làm mất byte nào. */
		$r2 = ltrim( substr( $raw, 0, 32 ), "\x00" );
		$s2 = ltrim( substr( $raw, 32 ), "\x00" );
		if ( '' === $r2 ) { $r2 = "\x00"; }
		if ( '' === $s2 ) { $s2 = "\x00"; }
		if ( ord( $r2[0] ) & 0x80 ) { $r2 = "\x00" . $r2; }
		if ( ord( $s2[0] ) & 0x80 ) { $s2 = "\x00" . $s2; }
		$than = "\x02" . chr( strlen( $r2 ) ) . $r2 . "\x02" . chr( strlen( $s2 ) ) . $s2;
		$lai  = "\x30" . chr( strlen( $than ) ) . $than;
		if ( 1 !== openssl_verify( 'thu-' . $i, $lai, $pub_thu, 'sha256' ) ) { $sai++; }
	}
	t( '🔴 600 lượt ký đều chuyển DER -> r‖s 64 byte đúng', 0 === $sai, $sai . ' lượt sai' );
	t( '   và bộ thử có chạm vào ca bẫy (r hoặc s ngắn hơn 32 byte)', $ngan > 0,
		$ngan . '/600 lượt' );

	t( 'DER rác -> chuỗi rỗng, không nổ', '' === CCAPP_Khoa::der_sang_raw( 'khong-phai-der' ) );
	t( 'DER cụt -> chuỗi rỗng', '' === CCAPP_Khoa::der_sang_raw( "\x30\x44\x02" ) );
	t( 'chuỗi rỗng -> chuỗi rỗng', '' === CCAPP_Khoa::der_sang_raw( '' ) );
}

/* ═══════════════════════════════════════════════════════════════════════ 4. KHOÁ & JWT */

$k1 = CCAPP_Khoa::cap();
t( 'sinh được cặp khoá', is_array( $k1 ) && ! empty( $k1['pub'] ) && ! empty( $k1['pem'] ) );

/* 🔴 KHOÁ SINH MỘT LẦN RỒI KHÔNG BAO GIỜ ĐỔI. Sinh lại là TOÀN BỘ máy đã đăng ký thành rác, và
   chúng không báo lỗi gì — chỉ lặng lẽ thôi nhận thông báo. */
$k2 = CCAPP_Khoa::cap();
t( '🔴 gọi lần hai KHÔNG sinh khoá mới', $k1['pub'] === $k2['pub'] && $k1['pem'] === $k2['pem'] );
t( 'khoá công khai dài 65 byte, bắt đầu bằng 0x04 (dạng không nén)',
	65 === strlen( CCAPP_Khoa::b64u_giai( $k1['pub'] ) )
	&& "\x04" === CCAPP_Khoa::b64u_giai( $k1['pub'] )[0], strlen( CCAPP_Khoa::b64u_giai( $k1['pub'] ) ) );

/* `aud` là GỐC của endpoint, không phải cả địa chỉ — sai chỗ này là 401, và câu lỗi không nói gì. */
t( '🔴 aud lấy GỐC, không lấy cả đường dẫn',
	'https://fcm.googleapis.com' === CCAPP_Khoa::goc( 'https://fcm.googleapis.com/fcm/send/abc123?x=1' ) );
t( 'giữ cổng khi có cổng', 'https://x.vn:8443' === CCAPP_Khoa::goc( 'https://x.vn:8443/a/b' ) );
t( 'địa chỉ rác -> rỗng', '' === CCAPP_Khoa::goc( 'khong-phai-url' ) );

$ep  = 'https://web.push.apple.com/abc/def?g=1';
$jwt = CCAPP_Khoa::jwt( $ep );
$ph  = explode( '.', $jwt );
t( 'JWT có ba phần', 3 === count( $ph ), $jwt );
if ( 3 === count( $ph ) ) {
	$dau  = json_decode( CCAPP_Khoa::b64u_giai( $ph[0] ), true );
	$than = json_decode( CCAPP_Khoa::b64u_giai( $ph[1] ), true );
	t( 'thuật toán khai đúng ES256', is_array( $dau ) && 'ES256' === $dau['alg'], $dau );
	t( '🔴 aud đúng gốc của endpoint',
		is_array( $than ) && 'https://web.push.apple.com' === $than['aud'], $than );
	t( 'sub là một mailto:', is_array( $than ) && 0 === strpos( (string) $than['sub'], 'mailto:' ), $than );
	t( 'exp ở tương lai và dưới 24 giờ', is_array( $than )
		&& (int) $than['exp'] > time() && (int) $than['exp'] < time() + 86400, $than );
	t( '🔴 chữ ký đúng 64 byte (r‖s), không phải DER',
		64 === strlen( CCAPP_Khoa::b64u_giai( $ph[2] ) ), strlen( CCAPP_Khoa::b64u_giai( $ph[2] ) ) );

	/* Và chữ ký ấy XÁC MINH ĐƯỢC bằng chính khoá công khai — phép thử duy nhất nói được rằng
	   Apple sẽ nhận. Dựng lại DER từ r‖s rồi nhờ OpenSSL kiểm. */
	if ( function_exists( 'openssl_verify' ) ) {
		$raw = CCAPP_Khoa::b64u_giai( $ph[2] );
		$r2  = ltrim( substr( $raw, 0, 32 ), "\x00" );
		$s2  = ltrim( substr( $raw, 32 ), "\x00" );
		if ( ord( $r2[0] ) & 0x80 ) { $r2 = "\x00" . $r2; }
		if ( ord( $s2[0] ) & 0x80 ) { $s2 = "\x00" . $s2; }
		$than_der = "\x02" . chr( strlen( $r2 ) ) . $r2 . "\x02" . chr( strlen( $s2 ) ) . $s2;
		$der      = "\x30" . chr( strlen( $than_der ) ) . $than_der;
		$ct_k1 = openssl_pkey_get_details( openssl_pkey_get_private( $k1['pem'] ) );
		$pub_k1 = openssl_pkey_get_public( $ct_k1['key'] );
		t( '🔴 chữ ký JWT xác minh được bằng chính khoá ấy',
			1 === openssl_verify( $ph[0] . '.' . $ph[1], $der, $pub_k1, 'sha256' ) );
	}
}
t( 'endpoint rác -> không ký', '' === CCAPP_Khoa::jwt( 'khong-phai-url' ) );

/* ═══════════════════════════════════════════════════════════════════════ 5. ĐỌC GIỜ */

t( "'07:50' hợp lệ", '07:50' === CCAPP_Nhac::gio( '07:50' ) );
t( "'7:5' -> chối (phút phải hai chữ số)", '' === CCAPP_Nhac::gio( '7:5' ) );
t( "'7:05' -> đệm về '07:05'", '07:05' === CCAPP_Nhac::gio( '7:05' ) );
t( "'24:00' -> chối", '' === CCAPP_Nhac::gio( '24:00' ) );
t( "'12:60' -> chối", '' === CCAPP_Nhac::gio( '12:60' ) );
t( '🔴 gõ bậy thì trả về dự phòng, KHÔNG tự bịa một giờ',
	'08:00' === CCAPP_Nhac::gio( 'sáng sớm', '08:00' ) );
t( 'đổi ra phút đúng', 470 === CCAPP_Nhac::phut( '07:50' ) );
t( 'giờ rỗng -> null phút', null === CCAPP_Nhac::phut( '' ) );

/* ═══════════════════════════════════════════════════════════════════════ 6. LUẬT NHẮC */

$L = array( 'bat' => true, 'ngay' => array( 1, 2, 3, 4, 5, 6 ), 'vao' => '07:50', 'ra' => '17:30',
	'coso' => array() );
$HN = '2026-09-17';   // thứ Năm
$T5 = 4;

/* Chưa tới giờ. */
t( '07:30 -> chưa nhắc gì',
	'' === CCAPP_Nhac::nen_nhac( '07:30', $T5, '07:50', '17:30', false, false, $L, '', $HN ) );

/* Đúng cửa sổ, chưa chấm -> nhắc vào. */
t( '07:50 chưa chấm -> nhắc VÀO',
	CCAPP_Nhac::VAO === CCAPP_Nhac::nen_nhac( '07:50', $T5, '07:50', '17:30', false, false, $L, '', $HN ) );
t( '08:04 (trong cửa sổ) vẫn nhắc',
	CCAPP_Nhac::VAO === CCAPP_Nhac::nen_nhac( '08:04', $T5, '07:50', '17:30', false, false, $L, '', $HN ) );
t( '08:06 (quá cửa sổ ' . CCAPP_Nhac::CUA_SO_PHUT . ' phút) -> thôi',
	'' === CCAPP_Nhac::nen_nhac( '08:06', $T5, '07:50', '17:30', false, false, $L, '', $HN ) );

/* 🔴 ĐÃ CHẤM RỒI THÌ KHÔNG NHẮC. Nhắc cả người đã chấm là tới tuần thứ hai không ai còn đọc
   thông báo của app này — kể cả người thật sự quên. */
t( '🔴 đã có giờ vào -> KHÔNG nhắc',
	'' === CCAPP_Nhac::nen_nhac( '07:50', $T5, '07:50', '17:30', true, false, $L, '', $HN ) );

/* 🔴 ĐÃ GỬI HÔM NAY RỒI THÌ THÔI — vòng quét chạy 5 phút một lượt, cửa sổ 15 phút, nên không
   chặn thì mỗi người nhận ba thông báo giống hệt nhau trong mười lăm phút. */
t( '🔴 đã gửi lượt VÀO hôm nay -> không gửi lại',
	'' === CCAPP_Nhac::nen_nhac( '07:55', $T5, '07:50', '17:30', false, false, $L, $HN . '|vao', $HN ) );
t( '   nhưng dấu của HÔM QUA không chặn hôm nay',
	CCAPP_Nhac::VAO === CCAPP_Nhac::nen_nhac( '07:55', $T5, '07:50', '17:30', false, false, $L,
		'2026-09-16|vao', $HN ) );

/* Giờ ra. */
t( '17:30 đã vào chưa ra -> nhắc RA',
	CCAPP_Nhac::RA === CCAPP_Nhac::nen_nhac( '17:30', $T5, '07:50', '17:30', true, false, $L, '', $HN ) );
t( 'đã có giờ ra -> thôi',
	'' === CCAPP_Nhac::nen_nhac( '17:30', $T5, '07:50', '17:30', true, true, $L, '', $HN ) );

/* 🔴 NGƯỜI QUÊN CHẤM VÀO BUỔI SÁNG, TỚI CHIỀU PHẢI NHẮC "RA", KHÔNG PHẢI "VÀO".
   `da_vao` của họ vẫn là false suốt ngày. Xét giờ vào trước thì 17h30 họ nhận lời nhắc "nhớ
   chấm VÀO" — sai việc, và tệ hơn: họ bấm một lượt, và lượt ấy tạo ra một giờ vào lúc 17h31. */
t( '🔴 quên chấm vào cả ngày, tới 17:30 vẫn nhắc RA (không nhắc VÀO)',
	CCAPP_Nhac::RA === CCAPP_Nhac::nen_nhac( '17:30', $T5, '07:50', '17:30', false, false, $L, '', $HN ) );

/* Tắt, và ngày nghỉ. */
$tat = array_merge( $L, array( 'bat' => false ) );
t( 'tắt nhắc -> im',
	'' === CCAPP_Nhac::nen_nhac( '07:50', $T5, '07:50', '17:30', false, false, $tat, '', $HN ) );
t( '🔴 Chủ nhật không có trong danh sách ngày -> không nhắc',
	'' === CCAPP_Nhac::nen_nhac( '07:50', 0, '07:50', '17:30', false, false, $L, '', $HN ) );

/* Cơ sở không khai giờ -> không nhắc việc ấy, chứ không nhắc bằng một giờ mặc định nào. */
t( 'cơ sở không có giờ nhắc vào -> không nhắc vào',
	'' === CCAPP_Nhac::nen_nhac( '07:50', $T5, '', '17:30', false, false, $L, '', $HN ) );

/* ═══════════════════════════════════════════════════════════════════════ 7. GIỜ RIÊNG CƠ SỞ */

$L2 = array_merge( $L, array( 'coso' => array(
	'SHOP_A' => array( 'vao' => '09:00', 'ra' => '' ),
) ) );
t( 'cơ sở khai riêng thì theo riêng', '09:00' === CCAPP_Nhac::gio_cua( 'SHOP_A', 'vao', $L2 ) );
t( '🔴 ô để trống thì rơi về giờ chung, không thành "không nhắc"',
	'17:30' === CCAPP_Nhac::gio_cua( 'SHOP_A', 'ra', $L2 ) );
t( 'cơ sở không khai gì -> theo giờ chung', '07:50' === CCAPP_Nhac::gio_cua( 'SHOP_B', 'vao', $L2 ) );
t( 'tra tên cơ sở không phân biệt hoa thường',
	'09:00' === CCAPP_Nhac::gio_cua( 'shop_a', 'vao', $L2 ) );

/* ═══════════════════════════════════════════════════════════════════════ 8. CHỮ HIỆN RA */

$cv = CCAPP_Nhac::chu_nhac( CCAPP_Nhac::VAO );
$cr = CCAPP_Nhac::chu_nhac( CCAPP_Nhac::RA );
t( 'hai loại nhắc nói hai việc khác nhau', $cv['tieu_de'] !== $cr['tieu_de'], array( $cv, $cr ) );
t( 'nhắc giờ ra nói rõ hậu quả (thiếu một đầu giờ là không tính công)',
	false !== mb_strpos( $cr['than'], 'không tính công' ), $cr );
/* ⚠️ KHÔNG ĐƯỢC CÓ TÊN AI TRONG CHỮ NHẮC. Thông báo hiện trên màn hình khoá, ai cầm máy cũng
   đọc được — và cửa `hoi` trả về chữ này thì không đòi đăng nhập. */
foreach ( array( $cv, $cr ) as $c ) {
	t( 'chữ nhắc không nhét chỗ điền tên/mã nào',
		false === strpos( $c['than'], '%s' ) && false === strpos( $c['tieu_de'], '%s' ), $c );
}

/* ═══════════════════════════════════════════════════════════════════════ 9. THỢ NỀN & APP */

/* 🔴 BỎ CHÚ THÍCH TRƯỚC KHI DÒ. Chú thích của hai tệp này nhắc TÊN đúng những thứ chúng cấm
   ("không bao giờ gọi Notification.requestPermission() ngay khi trang mở"), nên dò thẳng vào
   mã nguồn thô là đếm trúng lời cảnh báo rồi kết luận ngược. Đây là bài học đã ghi sẵn ở đầu
   `kiem-cc-app.php` — kho này đã dính ba lần. */
function js_sach( $src ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', $src );
	$ra  = array();
	foreach ( explode( "\n", $src ) as $d ) {
		if ( preg_match( '#^\s*//#', $d ) ) { continue; }
		$ra[] = $d;
	}
	return implode( "\n", $ra );
}

$sw = js_sach( file_get_contents( $bo . '/assets/sw.js' ) );
$aj = js_sach( file_get_contents( $bo . '/assets/app.js' ) );

/* 🔴 NHẬN ĐẨY MÀ KHÔNG HIỆN GÌ THÌ TRÌNH DUYỆT THU HỒI QUYỀN ĐẨY CỦA CẢ WEBSITE. Nên mọi nhánh
   của `push` phải kết thúc bằng một showNotification — kể cả nhánh mất mạng. */
t( '🔴 có nhánh dự phòng khi không hỏi được máy chủ', false !== strpos( $sw, 'chuDuPhong' ) );
t( '🔴 nhánh nào cũng đi qua hienNhac', 1 === substr_count( $sw, '.then(hienNhac)' )
	&& false !== strpos( $sw, 'showNotification' ), substr_count( $sw, '.then(hienNhac)' ) );
t( 'bọc trong waitUntil (thiếu là thợ nền tắt giữa chừng)',
	1 === preg_match( "/addEventListener\('push'[\s\S]{0,120}?waitUntil/", $sw ) );
t( 'thông báo dùng chung tag để đè, không xếp chồng', false !== strpos( $sw, "tag: NHAC_TAG" ) );
t( 'bấm vào thông báo thì tìm tab đang mở trước khi mở tab mới',
	false !== strpos( $sw, 'matchAll' ) && false !== strpos( $sw, 'openWindow' ) );
t( '🔴 có xử pushsubscriptionchange (không xử là máy ấy im vĩnh viễn)',
	false !== strpos( $sw, "addEventListener('pushsubscriptionchange'" ) );
t( '   và báo địa chỉ mới về máy chủ', false !== strpos( $sw, "viec: 'doi'" ) );

/* 🔴 KHÔNG XIN QUYỀN LÚC TRANG VỪA MỞ. iOS chỉ hỏi MỘT LẦN trong đời cài đặt ấy: bấm "Không cho
   phép" vì chưa hiểu hộp ấy là gì thì mất hẳn, phải vào Cài đặt của iPhone bật tay. */
t( '🔴 requestPermission chỉ gọi TRONG tay xử lý sự kiện bấm',
	1 === substr_count( $aj, 'Notification.requestPermission()' )
	&& 1 === preg_match( "/nut\.addEventListener\('click'[\s\S]{0,900}?Notification\.requestPermission\(\)/", $aj ) );
t( '🔴 chỉ xin quyền khi app ĐÃ CÀI (iOS không cho web thường đăng ký đẩy)',
	1 === preg_match( '/function loNhacChamCong[\s\S]{0,600}?if \(!daCai\(\)\)/', $aj ) );
t( 'đã từ chối rồi thì không hỏi lại', false !== strpos( $aj, "Notification.permission === 'denied'" ) );
t( 'userVisibleOnly: true (false thì trình duyệt chối đăng ký)',
	false !== strpos( $aj, 'userVisibleOnly: true' ) );

/* 🔴 TÊN KHOÁ PHIÊN PHẢI KHỚP VỚI BÊN TRẠM. Đây là chỗ DUY NHẤT bộ này đọc một thứ của bên kia;
   bên kia đổi tên khoá là bộ nhắc im lặng thôi đăng ký được máy mới, không báo gì. */
$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( '🔴 khoá phiên app.js đọc khớp với KHOA_PHIEN của trạm',
	false !== strpos( $aj, "localStorage.getItem('cc_session')" )
	&& 1 === preg_match( "/KHOA_PHIEN\s*=\s*'cc_session'/", $tram ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — chữ ký VAPID đúng ở cả ca bẫy 1/256, và chỉ nhắc người chưa chấm.\n";
