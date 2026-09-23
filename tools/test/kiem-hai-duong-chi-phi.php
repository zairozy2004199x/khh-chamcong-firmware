<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG CHI PHÍ CŨ PHẢI CHẠY CẢ HAI ĐƯỜNG — `/chi-phi` VÀ `/chi-phi-kvc`.
 *
 * Anh Thắng 14/09/2026: *"nhớ trang chi phí cũ sẽ chạy 2 link, tránh các bạn rối"*.
 *
 * =============================================================================================
 * 🔴 CA HỎNG BÀI NÀY SINH RA ĐỂ DẸP — và nó là ca THƯỜNG GẶP NHẤT, không phải ca hiếm.
 *
 *    Bản đầu chỉ khai đường đời đầu KHI slug hiện tại đã khác nó:
 *        if ( SLUG_CU !== slug() ) { add_rewrite_rule( SLUG_CU … ); }
 *
 *    Nghe hợp lý. Nhưng trên host của anh Thắng, ô Cài đặt đang lưu sẵn `chi-phi` — anh dùng
 *    link ấy từ đầu. Thế thì:
 *        slug() trả 'chi-phi'  ->  điều kiện SAI  ->  chỉ /chi-phi được khai
 *        ->  /chi-phi-kvc TRẢ 404
 *
 *    Tức cài bản mới lên xong, cái link MỚI in ra cho mọi người lại là link chết, cho tới khi
 *    có ai nhớ vào Cài đặt đổi tay. Mà "nhớ vào đổi tay" là thứ không xảy ra.
 *
 * ⚠️ KHAI THỪA VÔ HẠI, KHAI THIẾU LÀ 404. Cùng một luật khai hai lần thì WordPress giữ cái sau,
 *    và cả hai đều trỏ về đúng một chỗ. Nên lấy TẬP HỢP rồi khai hết.
 *
 * 🔴 NHƯNG BẢN MTD/VP KHÔNG ĐƯỢC ĂN THEO. Chúng sinh từ chính bản gốc, nên nếu hằng slug không
 *    được script đổi hết thì chúng cũng khai `/chi-phi` — và WordPress cho luật khai SAU đè luật
 *    trước, plugin nạp theo thứ tự chữ cái, nên /chi-phi rơi vào bảng RỖNG của bản mới. Phần 2
 *    canh đúng chỗ đó.
 *
 * Chạy: php tools/test/kiem-hai-duong-chi-phi.php
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

/** Khai lại đường dẫn với một giá trị option, trả về danh sách đường đã khai. */
function duong_voi( $option ) {
	$GLOBALS['VHCP_OPT']['vhcp_slug'] = $option;
	$GLOBALS['VHCP_LUAT'] = array();
	VHCP_App::init();
	$ra = array();
	foreach ( array_keys( (array) $GLOBALS['VHCP_LUAT'] ) as $mau ) {
		$ra[] = trim( (string) $mau, '^/?$' );
	}
	sort( $ra );
	return $ra;
}

/* ═══ 1. BỐN CA LƯU OPTION — ca nào cũng phải có ĐỦ HAI ĐƯỜNG ═══════════════════ */
foreach ( array(
	''             => 'chưa đặt bao giờ',
	'chi-phi'      => 'đang lưu đường ĐỜI ĐẦU (ca của anh Thắng)',
	'chi-phi-kvc'  => 'đã đổi sang đường mới',
	'chi-phi-abc'  => 'đặt một đường tự do',
) as $o => $ta ) {
	$d = duong_voi( $o );
	t( '🔴 ' . $ta . ' -> vẫn có /chi-phi', in_array( 'chi-phi', $d, true ), $d );
	t( '🔴 ' . $ta . ' -> vẫn có /chi-phi-kvc', in_array( 'chi-phi-kvc', $d, true ), $d );
}
/* Đường tự do người ta khai thì cũng phải sống — nó là thứ họ CỐ Ý đặt. */
t( '⚠️ đường tự do khai ra cũng sống', in_array( 'chi-phi-abc', duong_voi( 'chi-phi-abc' ), true ), '' );
/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: gỡ lớp lọc `'' !== $s` trong
   `cac_slug()` KHÔNG làm bài này đỏ, vì `slug()` có đường lui — option rỗng hay toàn dấu cách
   đều rơi về `SLUG_MAC_DINH`, nên không nguồn nào đẻ ra chuỗi rỗng hôm nay. Giữ lớp lọc vì cái
   "hôm nay" ấy là chuyện của MỘT hàm khác: ngày nào `slug()` thôi lui về mặc định, một đường
   `^/?$` được khai là nuốt cả trang chủ của site. Mấy phép dưới là đối chứng cho giả định ấy —
   chúng đỏ đúng lúc giả định gãy. */
foreach ( array( '', 'chi-phi', '   ' ) as $o ) {
	t( '🔴 không bao giờ khai đường RỖNG (nuốt cả trang chủ)',
		! in_array( '', duong_voi( $o ), true ), duong_voi( $o ) );
}

/* ═══ 2. BẢN TÁCH KHÔNG ĐƯỢC GIÀNH ĐƯỜNG ═══════════════════════════════════════ */
foreach ( (array) glob( $GOC . '/wordpress/vhcp-chi-phi-*', GLOB_ONLYDIR ) as $d_ban ) {
	$ten_ban = basename( $d_ban );
	$ma_ban  = substr( $ten_ban, strlen( 'vhcp-chi-phi-' ) );
	$tep_app = $d_ban . '/includes/class-vhcp-app.php';
	if ( ! is_file( $tep_app ) ) { continue; }   // bản tổng có bộ tệp riêng, có bài kiểm riêng
	$ma = file_get_contents( $tep_app );
	$ma_sach = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $ma );
	t( '🔴 bản «' . $ma_ban . '» không giữ chuỗi \'chi-phi\' của bản gốc',
		false === strpos( $ma_sach, "'chi-phi'" ), $ma_ban );
	t( '🔴 bản «' . $ma_ban . '» không giữ chuỗi \'chi-phi-kvc\'',
		false === strpos( $ma_sach, "'chi-phi-kvc'" ), $ma_ban );
	/* Và cả hai hằng của nó đều phải là đường của chính nó — thiếu một cái là nó khai thêm một
	   đường không phải của mình. */
	t( '   và cả hai hằng slug đều là chi-phi-' . $ma_ban,
		2 === substr_count( $ma_sach, "'chi-phi-" . $ma_ban . "'" ), $ma_ban );
}

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
