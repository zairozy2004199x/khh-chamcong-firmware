<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỘT LOẠI CHI PHÍ THUỘC ĐƯỢC NHIỀU BỘ PHẬN.
 *
 * Anh Thắng 10/09/2026: *"Cho phép loại chi phí chọn theo bộ phận, nhiều bộ phận sẽ chọn loại
 * chi phí đó cùng tên, chỉ là mỗi cơ sở khác mã thôi"*.
 *
 * =============================================================================================
 * 🔴 KHÔNG ĐƯỢC ĐEM CẢ Ô ĐI `bo_phan_chuan()`. Hàm ấy so NGUYÊN CHUỖI với danh sách bộ phận,
 *    nên "Kỹ thuật, Setup" không khớp tên nào và trả về '' — mà '' ở đây nghĩa là "loại này
 *    không bó bộ phận nào", tức HIỆN CHO MỌI KẾ TOÁN. Khai thêm bộ phận thứ hai lại hoá ra
 *    nới quyền cho tất cả, và hỏng im lặng: nhìn màn chỉ thấy nhiều số hơn, không thấy lỗi.
 *    Hỏng theo hướng nới quyền là hướng nguy nhất, nên bài này soi nó trước.
 *
 * 🔴 Ô TRỐNG VẪN PHẢI LÀ "MỌI BỘ PHẬN". Danh mục dựng từ sổ cũ còn rất nhiều dòng bỏ trống ô
 *    này. Hiểu ngược lại là chúng biến mất khỏi mọi màn — tiền có thật mà không ai nhìn thấy.
 *
 * ⚠️ CHẠY THẬT các hàm bốc từ mã nguồn.
 *
 * Chạy: php tools/test/kiem-loai-nhieu-bo-phan.php
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
$AUTH = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-auth.php' );
$DON  = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
$HTML = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/templates/app.html' );

function boc( $src, $neo ) {
	$a = strpos( $src, $neo );
	if ( false === $a ) { echo "\n✗ Không bốc được: $neo\n"; exit( 1 ); }
	return substr( $src, $a, strpos( $src, "\n\t}", $a ) - $a + 3 );
}

/* Bệ đỡ: danh sách bộ phận thật + bảng loại chi phí giả. `bo_phan_chuan()` bốc từ mã nguồn
   chứ KHÔNG viết lại — nó chính là chỗ dễ sai (so hoa/thường tiếng Việt). */
class KHO { public static $loai = array(); public static $bp = array( 'Cơ sở', 'Văn phòng', 'Kỹ thuật', 'Marketing', 'Công tác', 'Setup', 'Máy tự động' ); }
eval( 'class C { public static function bo_phan_ds(){ return KHO::$bp; } '
	. ' public static function loai_tk( $ten ){ $k = mb_strtolower( trim( (string) $ten ) );'
	. '   return isset( KHO::$loai[ $k ] ) ? array( "boPhan" => KHO::$loai[ $k ] ) : array( "boPhan" => "" ); } '
	. boc( $CFG, 'public static function bo_phan_chuan(' ) . ' '
	. boc( $CFG, 'public static function bo_phan_cua_loai(' ) . ' '
	. boc( $CFG, 'public static function bo_phan_ds_cua_loai(' ) . ' '
	. boc( $CFG, 'public static function bo_phan_tach(' ) . ' '
	. boc( $CFG, 'public static function loai_thuoc_bo_phan(' ) . ' }' );

KHO::$loai = array(
	'chi phí setup'    => 'Kỹ thuật, Setup',       // nhiều bộ phận — ca chính
	'chi phí tháo dỡ'  => 'Kỹ thuật',              // một bộ phận — dữ liệu CŨ
	'chi phí điện nước' => '',                     // chưa khai — dùng chung
	'chi phí lộn xộn'  => 'Kỹ thuật , setup ,, Kỹ Thuật',  // thừa dấu, khác hoa thường, trùng
	'chi phí ma'       => 'Bộ phận không có thật',
);

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. TÁCH Ô BỘ PHẬN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 nhiều bộ phận → tách ra đủ', array( 'Kỹ thuật', 'Setup' ), C::bo_phan_tach( 'Kỹ thuật, Setup' ) );
teq( '   một bộ phận (dữ liệu CŨ) → vẫn đúng', array( 'Kỹ thuật' ), C::bo_phan_tach( 'Kỹ thuật' ) );
teq( '   ô trống → rỗng', array(), C::bo_phan_tach( '' ) );
teq( '🔴 khác hoa/thường vẫn nhận, và trả về đúng tên chuẩn',
	array( 'Kỹ thuật', 'Setup' ), C::bo_phan_tach( 'kỹ thuật, SETUP' ) );
teq( '   thừa dấu phẩy · thừa khoảng trắng · trùng tên → dọn sạch',
	array( 'Kỹ thuật', 'Setup' ), C::bo_phan_tach( ' Kỹ thuật , setup ,, Kỹ Thuật ' ) );
teq( '🔴 tên bộ phận không có thật → BỎ, không giữ lại',
	array( 'Kỹ thuật' ), C::bo_phan_tach( 'Kỹ thuật, Phòng Ma' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 KHAI NHIỀU BỘ PHẬN KHÔNG ĐƯỢC BIẾN THÀNH "KHÔNG BÓ GÌ"
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 loại khai "Kỹ thuật, Setup" → ĐỌC RA HAI bộ phận, không phải rỗng',
	array( 'Kỹ thuật', 'Setup' ), C::bo_phan_ds_cua_loai( 'Chi phí setup' ) );
t( '🔴 và KHÔNG rỗng (rỗng = hiện cho mọi kế toán = nới quyền)',
	array() !== C::bo_phan_ds_cua_loai( 'Chi phí setup' ), C::bo_phan_ds_cua_loai( 'Chi phí setup' ) );
teq( '   dòng lộn xộn cũng đọc ra đủ hai', array( 'Kỹ thuật', 'Setup' ), C::bo_phan_ds_cua_loai( 'Chi phí lộn xộn' ) );
teq( '🔴 khai TOÀN tên không có thật → rỗng (không có gì để bó)',
	array(), C::bo_phan_ds_cua_loai( 'Chi phí ma' ) );

/* Hàm cũ `bo_phan_cua_loai()` giữ chữ ký — dòng một bộ phận phải y như trước. */
teq( 'hàm cũ: một bộ phận → vẫn trả đúng tên ấy', 'Kỹ thuật', C::bo_phan_cua_loai( 'Chi phí tháo dỡ' ) );
teq( '   chưa khai → vẫn trả rỗng', '', C::bo_phan_cua_loai( 'Chi phí điện nước' ) );
teq( '   nhiều bộ phận → trả tên đầu (không phải rỗng)', 'Kỹ thuật', C::bo_phan_cua_loai( 'Chi phí setup' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 CHỐT XEM ĐƯỢC HAY KHÔNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 kế toán Kỹ thuật xem được loại khai "Kỹ thuật, Setup"', C::loai_thuoc_bo_phan( 'Chi phí setup', 'Kỹ thuật' ) );
t( '🔴 kế toán Setup CŨNG xem được loại ấy (đây là chỗ bản cũ làm mất)',
	C::loai_thuoc_bo_phan( 'Chi phí setup', 'Setup' ) );
t( '🔴 kế toán Marketing thì KHÔNG (bó vẫn là bó)', ! C::loai_thuoc_bo_phan( 'Chi phí setup', 'Marketing' ) );
t( '   loại một bộ phận: đúng bộ phận thì xem được', C::loai_thuoc_bo_phan( 'Chi phí tháo dỡ', 'Kỹ thuật' ) );
t( '   loại một bộ phận: khác bộ phận thì không', ! C::loai_thuoc_bo_phan( 'Chi phí tháo dỡ', 'Setup' ) );
t( '🔴 loại CHƯA khai bộ phận → mọi kế toán đều xem được (sổ cũ còn nhiều dòng như thế)',
	C::loai_thuoc_bo_phan( 'Chi phí điện nước', 'Marketing' ) );
t( '🔴 người KHÔNG bị bó bộ phận → xem được tất, kể cả loại bó chặt',
	C::loai_thuoc_bo_phan( 'Chi phí setup', '' ) );
t( '   loại không có trong danh mục → không giấu đi', C::loai_thuoc_bo_phan( 'Loại lạ hoắc', 'Kỹ thuật' ) );
t( '   khác hoa/thường vẫn khớp', C::loai_thuoc_bo_phan( 'Chi phí setup', 'setup' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. MỌI CHỖ DÙNG ĐỀU ĐI QUA HÀM MỚI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$xem = boc( $AUTH, 'public static function xem_duoc_loai(' );
t( '🔴 phân quyền xem sổ dùng loai_thuoc_bo_phan(), không so nguyên chuỗi',
	false !== strpos( $xem, 'VHCP_Cfg::loai_thuoc_bo_phan( $ten_loai, $bo )' )
	&& false === strpos( $xem, 'mb_strtolower( $bo ) === mb_strtolower( $bp )' ), $xem );
t( '   người không bị bó vẫn xem hết (chốt cũ còn nguyên)',
	false !== strpos( $xem, "if ( '' === \$bo ) { return true; }" ), $xem );
t( '🔴 chấm "đơn này là việc của mình" cũng theo danh sách',
	false !== strpos( $DON, 'VHCP_Cfg::bo_phan_ds_cua_loai( $_nhom ) && VHCP_Cfg::loai_thuoc_bo_phan( $_nhom, $bo_phan_bo )' ), '' );
t( '   đếm "loại chưa khai bộ phận" cũng vậy',
	false !== strpos( $DON, '! VHCP_Cfg::bo_phan_ds_cua_loai( $ten )' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. MÀN CẤU HÌNH: Ô CHỌN NHIỀU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 ô Bộ phận của bảng loại chi phí cho chọn NHIỀU',
	false !== strpos( $HTML, "+'<td>'+_bpSelNhieu(x.boPhan||'')+'</td>'" ), '' );
t( '   và là <select multiple> (chỗ đọc bảng đã biết gom nhiều lựa chọn)',
	false !== strpos( $HTML, "return '<select multiple size=\"3\"" ), '' );
t( '🔴 chỗ đọc bảng nối các lựa chọn bằng dấu phẩy — đúng dạng máy chủ tách ra',
	false !== strpos( $HTML, "if(f.tagName==='SELECT'&&f.multiple){ return Array.prototype.map.call(f.selectedOptions,function(o){return o.value;}).join(', '); }" ), '' );
t( '   nói rõ không chọn gì = mọi bộ phận',
	false !== mb_strpos( $HTML, 'Không chọn gì = mọi bộ phận' ), '' );
t( '🔴 lọc danh mục lúc nhập cũng so theo DANH SÁCH, không so nguyên chuỗi',
	false !== strpos( $HTML, 'if(bp && bpLoai.length && bpLoai.indexOf(bp)<0) return false;' )
	&& false === strpos( $HTML, "var b=String(x.boPhan||'').trim();\n      if(bp && b && b!==bp) return false;" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: loại chi phí dùng chung nhiều bộ phận, và khai thêm không thành nới quyền.\n";
