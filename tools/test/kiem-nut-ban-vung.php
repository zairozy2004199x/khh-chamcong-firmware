<?php
/**
 * NÚT MỞ BẢN CHI PHÍ RIÊNG CỦA VÙNG — hiện ở trang Cổng và trang Nội bộ.
 *
 * Anh Thắng 08/09/2026: *"đẩy link trang chi phí hà nội vào trang nội bộ để theo dõi"*.
 *
 * =============================================================================================
 * 🔴 CHƯA CÀI THÌ KHÔNG ĐƯỢC BÀY NÚT. Mỗi vùng là một plugin độc lập, cài rời. Bày nút cho một
 *    vùng chưa cài là dẫn người ta tới trang 404 — tệ hơn hẳn việc không có nút nào, vì nó làm
 *    người dùng tin là hệ thống hỏng.
 *
 * 🔴 KHÔNG KHAI CỨNG TỪNG VÙNG. Thêm vùng mà phải nhớ sửa thêm một dòng thì lần quên nào cũng
 *    là "cài xong mà không thấy nút đâu", và người cài không có cách nào đoán ra thiếu ở đâu.
 *    Dò theo đúng khuôn tên `VHCP<VÙNG>_App` mà `tools/tach-ban-vung.sh` sinh ra.
 *
 * ⚠️ CHẠY THẬT: khai mấy lớp giả đúng khuôn rồi đòi đúng những nút phải lên.
 *
 * Chạy: php tools/test/kiem-nut-ban-vung.php
 */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC = dirname( dirname( __DIR__ ) );
$TC  = file_get_contents( $GOC . '/wordpress/vhcp-trang-chu/includes/class-vhtc-trang.php' );
$NB  = file_get_contents( $GOC . '/wordpress/vhcp-noi-bo/includes/class-vhnb-trang.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ — bốc CHÍNH `app_vung()` ra chạy
 * ⚠️ Chỗ mù: không có WordPress nên `get_plugin_data()` không chạy; phần lấy TÊN PLUGIN THẬT
 *    rơi về nhánh lui, và bài này soi nhánh lui. Phần ấy có phép soi mã riêng ở mục 4.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$i = strpos( $TC, 'public static function app_vung() {' );
$j = strpos( $TC, "\n\t}", $i );
t( 'bốc được app_vung()', false !== $i && $j > $i );
if ( false === $i ) { echo "\n✗ Không bốc được — dừng.\n"; exit( 1 ); }
$than = substr( $TC, $i, $j - $i + 3 );

eval( 'class TC { ' . $than
	. ' private static function ten_plugin_vung( $m ) { return "Vận Hành Chi Phí (" . $m . ")"; } }' );

/* Lớp giả ĐÚNG KHUÔN script sinh ra. */
class VHCPHN_App { public static function app_url() { return 'https://kh.test/chi-phi-hn/'; } }
class VHCPDN_App { public static function app_url() { return 'https://kh.test/chi-phi-dn/'; } }
/* Và mấy lớp GẦN GIỐNG mà KHÔNG được nhận nhầm. */
class VHCP_App        { public static function app_url() { return 'https://kh.test/chi-phi/'; } }   // bản chính
class VHCPXX_Trang    { public static function app_url() { return 'https://kh.test/xx/'; } }        // sai đuôi
class VHCPQUADAIQUA_App { public static function app_url() { return 'https://kh.test/qua/'; } }      // mã 9 ký tự — quá 8
class VHCPZZ_App      { public static function app_url() { return ''; } }                            // có lớp, KHÔNG có trang
class VHCPQQ_App      { /* KHÔNG có app_url */ }                                                    // đúng khuôn tên, thiếu hàm
class KHACVHCPRR_AppCu { public static function app_url() { return 'https://kh.test/khac/'; } }     // tên plugin KHÁC chỉ chứa khuôn

$ds = TC::app_vung();
$ten = array_map( function ( $x ) { return $x['ten']; }, $ds );
$url = array_map( function ( $x ) { return $x['url']; }, $ds );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. NHẬN ĐÚNG VÙNG ĐÃ CÀI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 nhận đúng hai vùng đã cài', 2, count( $ds ) );
t( '   có HN', in_array( 'https://kh.test/chi-phi-hn/', $url, true ), $url );
t( '   có DN', in_array( 'https://kh.test/chi-phi-dn/', $url, true ), $url );
t( '🔴 KHÔNG nhận nhầm bản chính (VHCP_App)', ! in_array( 'https://kh.test/chi-phi/', $url, true ), $url );
t( '🔴 KHÔNG nhận lớp sai đuôi (_Trang)', ! in_array( 'https://kh.test/xx/', $url, true ), $url );
t( '🔴 KHÔNG nhận mã vùng quá dài', ! in_array( 'https://kh.test/qua/', $url, true ), $url );
/* 🔴 Khuôn tên phải NEO hai đầu. Lỏng neo thì một plugin của người khác chỉ vì tên có chứa
   "VHCPxx_App" ở giữa cũng leo lên trang Cổng — mà chủ site không hiểu nút ấy ở đâu ra. */
t( '🔴 KHÔNG nhận lớp chỉ CHỨA khuôn tên ở giữa', ! in_array( 'https://kh.test/khac/', $url, true ), $url );
t( '🔴 có lớp mà KHÔNG có trang → bỏ qua (không dựng nút chết)',
	! in_array( '', $url, true ), $url );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. THỨ TỰ PHẢI ỔN ĐỊNH
 *
 * 🔴 `get_declared_classes()` trả theo thứ tự NẠP, mà thứ tự ấy đổi khi bật/tắt một plugin bất
 *    kỳ. Không xếp lại thì hôm nay nút HN đứng trước, mai đứng sau — người dùng bấm theo trí
 *    nhớ vị trí sẽ bấm nhầm.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$ten2 = $ten; sort( $ten2 );
teq( '🔴 xếp theo tên, không theo thứ tự nạp', $ten2, $ten );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. MỖI NÚT ĐỦ THỨ TRANG CẦN ĐỂ VẼ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
foreach ( $ds as $x ) {
	t( 'nút "' . $x['ten'] . '" có tên',   '' !== trim( (string) $x['ten'] ), $x );
	t( '   có biểu tượng',                  '' !== trim( (string) $x['icon'] ), $x );
	t( '   có đường dẫn',                   '' !== trim( (string) $x['url'] ), $x );
	t( '   cờ `co` bật (đã cài mới tới đây)', ! empty( $x['co'] ), $x );
	t( '   có mô tả nói rõ dữ liệu tách',   false !== mb_strpos( (string) $x['mo_ta'], 'tách' ), $x );
}
/* 🔴 TÊN phải đi qua `ten_plugin_vung()`, không phải bê thẳng mã vùng ra. Bê thẳng thì nút hiện
   chữ "HN" trần — người dùng không đoán được nó là cái gì, mà bài kiểm đếm-và-xếp vẫn xanh. */
teq( '🔴 tên đi qua ten_plugin_vung(), không bê mã vùng trần',
	array( 'Vận Hành Chi Phí (DN)', 'Vận Hành Chi Phí (HN)' ), $ten );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. CHÈN ĐÚNG CHỖ, VÀ LẤY TÊN PLUGIN THẬT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 ds_app() có gọi app_vung()', false !== strpos( $TC, '$vung = self::app_vung();' ), '' );
t( '   chèn ngay SAU ô "Vận Hành Chi Phí"',
	false !== strpos( $TC, "if ( 'Vận Hành Chi Phí' === \$x['ten'] ) { \$i = \$k + 1; break; }" ), '' );
t( '   bằng array_splice (giữ nguyên thứ tự mấy ô còn lại)', false !== strpos( $TC, 'array_splice( $ds, $i, 0, $vung )' ), '' );
/* Tên hiển thị: đọc `Plugin Name:` thật, không đoán từ mã vùng ("hn" đoán được, "ct" thì không). */
t( '🔴 lấy tên plugin THẬT', false !== strpos( $TC, "get_plugin_data(" ), '' );
t( '   và gác đủ trước khi gọi',
	false !== strpos( $TC, "! function_exists( 'get_plugin_data' )" )
	&& false !== strpos( $TC, "! defined( 'WP_PLUGIN_DIR' )" ), '' );
t( '   đọc không được thì lui về mã vùng, không để trống',
	false !== strpos( $TC, "\$lui = 'Vận Hành Chi Phí (' . \$ma_hoa . ')';" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. TRANG NỘI BỘ — NHÁNH LUI CŨNG PHẢI CÓ
 *
 * Trang Nội bộ ưu tiên đọc `VHTC_Trang::ds_app()`. Chưa cài plugin Cổng thì nó tự dò — và bản
 * vùng phải có mặt ở cả đường ấy, không thì đúng tình huống ấy nút biến mất.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 nhánh lui của trang Nội bộ cũng dò bản vùng',
	false !== strpos( $NB, "preg_match( '/^VHCP([A-Z0-9]{1,8})_App$/', \$lop, \$m )" ), '' );
t( '   và vẫn đi qua chốt `method_exists` chung', false !== strpos( $NB, "\$them( \$lop, 'app_url'" ), '' );
/* Đối chứng: bản CHÍNH vẫn phải còn nguyên ở đó. */
t( '   bản chính không bị đụng', false !== strpos( $NB, "\$them( 'VHCP_App',  'app_url', '💰', 'Vận hành chi phí' );" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. KHÔNG CÀI VÙNG NÀO → KHÔNG THÊM NÚT NÀO
 *
 * 🔴 Phần lớn site chỉ có bản chính. Thêm một ô trống hay một nút chết vào đó là làm hỏng trang
 *    của người không dùng tính năng này.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$than_sach = str_replace( 'VHCP([A-Z0-9]{1,8})_App', 'KHONGCOAI([A-Z0-9]{1,8})_App', $than );
eval( 'class TC2 { ' . $than_sach
	. ' private static function ten_plugin_vung( $m ) { return "x"; } }' );
teq( '🔴 không vùng nào khớp → mảng RỖNG', array(), TC2::app_vung() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: vùng nào cài rồi thì lên nút, chưa cài thì thôi.\n";
