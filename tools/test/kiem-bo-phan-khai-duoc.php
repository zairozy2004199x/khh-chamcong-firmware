<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỘ PHẬN KHAI ĐƯỢC Ở CẤU HÌNH — KHÔNG CÒN GÕ CỨNG.
 *
 * Anh Thắng 10/09/2026: *"giờ làm thêm mảng cho trang chi phí"*, kèm ảnh chụp đúng ô chọn Bộ
 * phận với bảy giá trị gõ cứng.
 *
 * =============================================================================================
 * 🔴 TRƯỚC BẢN NÀY GÕ CỨNG Ở HAI NƠI: hằng `BO_PHAN_DS` bên máy chủ và `BOPHAN_LIST` trong
 *    app.html. Hai nơi gõ cứng là hai nơi lệch nhau — máy chủ chối một tên mà ô chọn vẫn bày
 *    ra nó, và người khai không hiểu vì sao lưu xong ô lại trống.
 *
 * 🔴 HỎNG THEO HƯỚNG NỚI QUYỀN LÀ CHỖ NGUY NHẤT CỦA BẢN NÀY. `bo_phan_chuan()` trả '' cho tên
 *    không khớp, mà '' nghĩa là "không bó bộ phận nào" — tức người mang vai ấy nhìn thấy sổ của
 *    MỌI mảng. Nên danh sách rỗng KHÔNG được hiểu là "không có bộ phận nào": phải ngã về danh
 *    sách mặc định. Bảng chưa gieo (site vừa nâng cấp) là ca thật, không phải giả định.
 *
 * ⚠️ CHẠY THẬT `bo_phan_ds()` và `bo_phan_chuan()` với bảng cấu hình giả.
 *
 * Chạy: php tools/test/kiem-bo-phan-khai-duoc.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC  = dirname( dirname( __DIR__ ) );
$CFG  = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
$HTML = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/templates/app.html' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ — bốc CHÍNH hai hàm thật
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
class KHO { public static $bang = array(); public static $doc = 0; }
function boc( $src, $neo ) {
	$a = strpos( $src, $neo );
	if ( false === $a ) { echo "\n✗ Không bốc được: $neo\n"; exit( 1 ); }
	return substr( $src, $a, strpos( $src, "\n\t}", $a ) - $a + 3 );
}
preg_match( "/const BO_PHAN_DS = array\(([^)]*)\);/", $CFG, $m );
t( '🔴 hằng mặc định còn trong mã thật', ! empty( $m[1] ), $m );
$MD = array_map( function ( $x ) { return trim( $x, " '" ); }, explode( ',', $m[1] ) );
teq( '   và vẫn đủ bảy bộ phận đang chạy', 7, count( $MD ) );

/* Hằng tên bảng lấy TỪ MÃ THẬT — gõ lại là bài kiểm xanh trên một tên bảng không còn dùng. */
preg_match( "/const BP    = '([^']+)';/", $CFG, $m_bp );
t( '🔴 có hằng tên bảng BP trong mã thật', ! empty( $m_bp[1] ), $m_bp );
eval( 'class TC { const BO_PHAN_DS = ' . var_export( $MD, true ) . '; '
	. 'const BP = ' . var_export( isset( $m_bp[1] ) ? $m_bp[1] : 'CH_BoPhan', true ) . '; private static $bp_memo = null; '
	. boc( $CFG, 'public static function bo_phan_ds()' ) . ' '
	. boc( $CFG, 'public static function bo_phan_chuan(' ) . ' '
	. ' public static function read( $b ) { KHO::$doc++; return KHO::$bang; }'
	. ' public static function quen() { self::$bp_memo = null; } }' );

function dat( $rows ) { KHO::$bang = $rows; KHO::$doc = 0; TC::quen(); }

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. ĐỌC TỪ BẢNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dat( array( array( 'Cơ sở' ), array( 'Kỹ thuật' ), array( 'Bảo trì' ) ) );
teq( '🔴 khai bộ phận mới → dùng được ngay', array( 'Cơ sở', 'Kỹ thuật', 'Bảo trì' ), TC::bo_phan_ds() );
teq( '   và chuẩn hoá nhận đúng tên mới', 'Bảo trì', TC::bo_phan_chuan( 'Bảo trì' ) );
teq( '   bỏ qua hoa/thường (chữ có dấu)', 'Bảo trì', TC::bo_phan_chuan( 'BẢO TRÌ' ) );
teq( '   tên không khai → rỗng (= mọi bộ phận)', '', TC::bo_phan_chuan( 'Không có' ) );
/* Bộ phận cũ đã bỏ khỏi bảng thì phải thôi được nhận — không thì xoá xong vẫn còn tác dụng. */
teq( '🔴 bộ phận đã BỎ khỏi bảng → thôi nhận', '', TC::bo_phan_chuan( 'Marketing' ) );

dat( array( array( ' Setup ' ), array( '' ), array( 'Setup' ) ) );
teq( 'bỏ ô rỗng và dòng trùng, cắt khoảng trắng', array( 'Setup' ), TC::bo_phan_ds() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 RỖNG THÌ NGÃ VỀ MẶC ĐỊNH — KHÔNG ĐƯỢC TRẢ MẢNG RỖNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dat( array() );
teq( '🔴 bảng chưa gieo → dùng danh sách mặc định', $MD, TC::bo_phan_ds() );
teq( '   nên bộ phận cũ vẫn nhận được, không nới quyền', 'Kỹ thuật', TC::bo_phan_chuan( 'Kỹ thuật' ) );
dat( array( array( '  ' ), array( '' ) ) );
teq( '🔴 bảng toàn ô rỗng → cũng ngã về mặc định', $MD, TC::bo_phan_ds() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. NHỚ TRONG MỘT LƯỢT CHẠY
 * `bo_phan_chuan()` bị gọi trong vòng lặp qua từng vai, từng loại chi phí.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dat( array( array( 'Cơ sở' ) ) );
TC::bo_phan_ds(); TC::bo_phan_ds(); TC::bo_phan_chuan( 'Cơ sở' ); TC::bo_phan_chuan( 'x' );
teq( '🔴 đọc bảng ĐÚNG MỘT LẦN cho cả lượt chạy', 1, KHO::$doc );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. VÒNG GỌI KHÔNG ĐÁY — chỗ dễ chết nhất
 *
 * `cfg_static()` -> `vai_tuy_bien()` -> `bo_phan_chuan()` -> `bo_phan_ds()`. Nếu hàm cuối gọi
 * lại `cfg_static()` thì trang TRẮNG ngay lượt tải đầu, không câu lỗi nào đọc được.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$than = boc( $CFG, 'public static function bo_phan_ds()' );
t( '🔴 bo_phan_ds() KHÔNG gọi cfg_static()', false === strpos( $than, 'cfg_static' ), '' );
t( '   mà đọc thẳng bảng',                    false !== strpos( $than, 'self::read( self::BP )' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. GIEO · GỬI XUỐNG MÀN · NHẬN LƯỢT LƯU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 gieo bảng bộ phận lần đầu từ danh sách mặc định',
	false !== strpos( $CFG, 'foreach ( self::BO_PHAN_DS as $b ) { self::append( self::BP, array( $b ) ); }' ), '' );
t( '   cfg_static gửi danh sách xuống', false !== strpos( $CFG, "\$out['boPhanDs'] = self::bo_phan_ds();" ), '' );
t( '   getConfig cũng gửi',              false !== strpos( $CFG, "'boPhanDs'   => isset( \$s['boPhanDs'] )" ), '' );
t( '🔴 CHỈ ADMIN mới lưu được (bộ phận bó tầm nhìn kế toán)',
	false !== strpos( $CFG, "'Chỉ Admin mới thêm/sửa bộ phận được.'" ), '' );
/* 🔴 CANH CẢ CHỐT LẪN CÂU, KHÔNG CHỈ CÂU. Dò mỗi chuỗi "Phải còn ít nhất một bộ phận" thì bỏ
   hẳn dòng `if ( ! $rows )` đi vẫn xanh — câu vẫn nằm trong tệp, chỉ là không ai tới nó nữa.
   Phá thử chỉ đúng lỗ ấy. */
t( '🔴 chối lưu bảng RỖNG, không im lặng ngã về mặc định',
	1 === preg_match( '/if \( ! \$rows \) \{\s*return VHCP_Util::err\( \x27Phải còn ít nhất một bộ phận/u', $CFG ), '' );
t( '   lưu xong quên bộ nhớ tạm (không thì màn còn danh sách cũ)',
	false !== strpos( $CFG, "self::write( self::BP, \$rows );\n\t\t\tself::\$bp_memo = null;" ), '' );
t( '   và dọn đệm chung cũng quên nó', false !== strpos( $CFG, "public static function clear_cache() {\n\t\tself::\$bp_memo = null;" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. GIAO DIỆN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 không còn danh sách gõ cứng dựng ô chọn', false === strpos( $HTML, 'BOPHAN_LIST' ), '' );
t( '   ô chọn dựng từ _bpDs()', false !== strpos( $HTML, '_bpDs().map(function(b){return [b,_bpNhan(b)];})' ), '' );
/* 🔴 ĐƯỜNG LUI TRÊN MÀN. `CFG` chưa nạp xong mà `_bpDs()` trả mảng rỗng thì ô chọn Bộ phận
   trắng trơn — người dùng bấm Lưu một cái là xoá sạch cột Bộ phận của mọi dòng, im lặng. */
t( '🔴 _bpDs() rỗng thì ngã về đường lui, không trả mảng rỗng',
	false !== strpos( $HTML, '(ds&&ds.length)?ds:BOPHAN_MAC_DINH' ), '' );
teq( '   và đường lui đủ bảy bộ phận đang chạy', 7,
	preg_match( "/var BOPHAN_MAC_DINH=\[(.*?)\];/", $HTML, $m_ml ) ? count( explode( ',', $m_ml[1] ) ) : 0 );
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHỐI CẤU HÌNH ĐỂ KHAI BỘ PHẬN ĐÃ GỠ — 22/09/2026. Anh Thắng: *"bỏ bộ phận đi"*.
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Bốn phép ở đây trước kia đòi khối ấy CÓ, và đòi nó hỏi trước khi xoá một bộ phận. Cột Bộ
 * phận rời bảng Loại chi phí hôm 21/09, nên từ đó khối này khai một danh mục mà không còn
 * bảng nào đọc tới. Gỡ khối thì bốn phép ấy hết đối tượng.
 *
 * ⚠️ ĐẢO CHIỀU CHỨ KHÔNG XOÁ ĐI. Xoá phép là lần sau ai đó dựng lại khối cũng không ai hay.
 *    Nay đòi NGƯỢC LẠI: khối phải VẮNG. Hành vi đầy đủ — cộng chiều "dữ liệu boPhan không
 *    được mất theo" — do `kiem-go-khoi-bo-phan.js` canh trên cả bốn bản.
 *
 * ⚠️ PHẦN CÒN LẠI CỦA BÀI VẪN ĐÚNG VÀ VẪN CHẠY: danh mục bộ phận vẫn gieo, `bo_phan_tach()`
 *    vẫn tách, ô Bộ phận ở bảng 🔐 Người dùng vẫn dựng từ `_bpDs()`. Bộ phận còn bó tầm nhìn
 *    kế toán ở đúng một chỗ ấy; chỉ cái CHỖ KHAI DANH MỤC là đi.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 khối Cấu hình khai bộ phận đã GỠ — không còn bảng, không còn hàm lưu',
	false === strpos( $HTML, 'id="cfgBpBody"' )
	&& false === strpos( $HTML, 'function saveCfgBp()' ), '' );
/* ⚠️ KHÔNG soi "còn chỗ nào GỌI `saveCfgBp()` không" ở đây: bài này đọc THẲNG cả tệp, mà bia
   mộ của chính lượt gỡ có nhắc tên hàm ấy — phép soi bắt phải chú thích rồi kết luận ngược.
   Đã mắc đúng lỗi này bốn lần trong phiên 22/09. Chiều ấy để `kiem-go-khoi-bo-phan.js` lo,
   vì nó tước chú thích trước khi soi. */

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: bộ phận khai được ở Cấu hình, và rỗng thì ngã về mặc định chứ không nới quyền.\n";
