<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ SỞ ĐƠN VỊ POSH LƯU BÊN NÀY PHẢI SANG DANH MỤC CỦA PLUGIN GHẾ — VÀ CHỈ POSH.
 *
 * Anh Thắng 09/09/2026: *"chỉ đẩy sang nếu nó là đơn vị posh thôi"*, chốt sau khi nghe rằng đẩy
 * cả danh mục sang sẽ nhét đầy ô chọn cơ sở bên ghế bằng những chỗ không bao giờ có ghế nào.
 *
 * =============================================================================================
 * 🔴 LỌC NẰM Ở ĐÂY, KHÔNG PHẢI BÊN GHẾ. Chỉ bên này mới biết cơ sở nào thuộc đơn vị nào — bên
 *    ghế nhận cái tên đã lọc rồi. Sót phép lọc là mọi gian khu vui chơi, văn phòng, kỹ thuật
 *    tràn sang ô chọn cơ sở lúc gán ghế, và người gán ghế không có cách nào biết chỗ nào thật.
 *
 * 🔴 KHÔNG PHÁT TỪ `nhan_coso_ngoai()`. Hàm ấy là ĐẦU NHẬN của chiều ngược lại (ghế -> đây).
 *    Phát ở đó là hai plugin ném qua ném lại một cái tên.
 *
 * ⚠️ CHẠY THẬT `bao_coso_posh_()` với `do_action()` ghi lại lượt gọi.
 * ⚠️ CHỖ MÙ: bài này ở nhánh chi phí nên không với sang mã plugin ghế được. Đầu NHẬN có bài
 *    riêng bên ấy (`tools/test/kiem-day-coso-sang-chi-phi.php`, nhánh posh).
 *
 * Chạy: php tools/test/kiem-day-coso-hai-chieu.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC = dirname( dirname( __DIR__ ) );
$CFG = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ — bốc CHÍNH `bao_coso_posh_()` ra chạy
 *
 * 🔴 `do_action()` PHẢI GHI LẠI THẬT. Bệ đỡ để nó rỗng thì mọi đường qua móc XANH OAN — đúng
 *    bẫy đã dính hôm 08/09.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 CẢNH BÁO PHP LÀ TRƯỢT, không phải chuyện nhỏ. Đọc thẳng một ô không có (dòng cũ thiếu cột
   Đơn vị) chỉ sinh Warning chứ không nổ — nên nó lọt qua mọi phép so kết quả, trong khi trên
   host thật nó tràn nhật ký lỗi ở MỌI lượt lưu, và làm TRẮNG TRANG nếu site bật WP_DEBUG.
   Cùng cái bẫy đã ghi chốt ở `cfg_static()` cho hai ô cuối bảng người dùng. */
$CANH = array();
set_error_handler( function ( $no, $str ) { global $CANH; $CANH[] = $str; return true; } );

$GOI = array();
function do_action( $moc ) {
	global $GOI;
	$a = func_get_args(); array_shift( $a );
	$GOI[] = array( $moc, $a );
}
class VHCP_DonVi {
	const MAC_DINH = 'K&H';
	public static function chuan( $x ) { $x = trim( (string) $x ); return ( '' === $x ) ? self::MAC_DINH : $x; }
}

$i = strpos( $CFG, 'private static function bao_coso_posh_(' );
$j = strpos( $CFG, "\n\t}", $i );
t( 'bốc được bao_coso_posh_()', false !== $i && $j > $i );
if ( false === $i ) { echo "\n✗ Không bốc được — dừng.\n"; exit( 1 ); }
$than = substr( $CFG, $i, $j - $i + 3 );
/* Hằng đơn vị lấy TỪ MÃ THẬT, không gõ lại: đổi 'POSH' thành tên khác mà bài kiểm ghim nguyên
   văn thì nó xanh trên một hằng không còn tồn tại. */
preg_match( "/const DON_VI_GHE = '([^']+)';/", $CFG, $m_dv );
t( '🔴 có hằng DON_VI_GHE trong mã thật', ! empty( $m_dv[1] ), $m_dv );
$DV = isset( $m_dv[1] ) ? $m_dv[1] : 'POSH';
eval( 'class TC { const DON_VI_GHE = ' . var_export( $DV, true ) . '; '
	. str_replace( 'private static function', 'public static function', $than ) . ' }' );

function bao( $rows ) {
	global $GOI;
	$GOI = array();
	TC::bao_coso_posh_( $rows );
	$ra = array();
	foreach ( $GOI as $g ) { $ra[] = $g[1][0]; }
	return array( 'ten' => $ra, 'moc' => $GOI );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 CHỈ POSH — PHÉP CHÍNH CỦA CẢ BÀI
 * Cột: 0 Cơ sở · 1 Mã đơn vị · 2 Phân loại lớn · 3 Tên MISA · 4 Đóng cửa · 5 Đơn vị
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$bang = array(
	array( 'POSH Vạn Hạnh',  '', '', '', '', $DV ),
	array( 'AEON Bình Tân',  '', '', '', '', 'K&H' ),
	array( 'Văn phòng',      '', '', '', '', '' ),          // rỗng -> nhà mặc định, KHÔNG phải POSH
	array( 'POSH Gò Vấp',    '', '', '', '', $DV ),
	array( 'KVC Nowzone',    '', '', '', '', 'KVC' ),
);
$r = bao( $bang );
teq( '🔴 chỉ đẩy cơ sở đơn vị ' . $DV, array( 'POSH Vạn Hạnh', 'POSH Gò Vấp' ), $r['ten'] );
teq( '   tên móc đúng thứ bên ghế đang nghe', 'vhcp_coso_posh_da_luu', $r['moc'][0][0] );

/* Ô đơn vị RỖNG là nhà mặc định (K&H), không phải POSH. Đẩy nhầm nó là mọi gian chưa khai đơn
   vị tràn sang bên ghế — mà "chưa khai" là trạng thái thường gặp nhất. */
$r = bao( array( array( 'Chưa khai đơn vị', '', '', '', '', '' ) ) );
teq( '🔴 ô đơn vị RỖNG → KHÔNG đẩy', array(), $r['ten'] );

/* Tên rỗng: không có tên thì bên kia không tra vào đâu được. */
$r = bao( array( array( '   ', '', '', '', '', $DV ) ) );
teq( '🔴 tên rỗng → KHÔNG đẩy dù đúng đơn vị', array(), $r['ten'] );

/* Thiếu ô đơn vị hẳn (dòng cũ 5 cột) — không được nổ, không được coi là POSH, và KHÔNG ĐƯỢC
   KÊU một tiếng cảnh báo nào. */
$CANH = array();
$r = bao( array( array( 'Dòng cũ 5 cột', '', '', '', '' ) ) );
teq( '🔴 dòng thiếu cột đơn vị → KHÔNG đẩy', array(), $r['ten'] );
teq( '🔴 và KHÔNG sinh cảnh báo PHP nào', array(), $CANH );

/* Bảng rỗng. */
teq( 'bảng rỗng → không đẩy gì', array(), bao( array() )['ten'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. GẮN ĐÚNG CHỖ NGƯỜI TA BẤM LƯU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 gọi NGAY SAU lượt ghi bảng cơ sở',
	false !== strpos( $CFG, "self::write( self::COSO, \$rows );\n\t\t\tself::bao_coso_posh_( \$rows );" ), '' );
/* Gọi với $rows — tức bảng ĐÃ hợp nhất với phần của đơn vị khác. Gọi bằng biến khác là đẩy
   thiếu, hoặc đẩy một danh sách không phải thứ vừa lưu. */
t( '   và đẩy đúng bảng vừa lưu', false !== strpos( $than, 'foreach ( (array) $rows as $r )' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 KHÔNG PHÁT TỪ ĐẦU NHẬN CỦA CHIỀU NGƯỢC LẠI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$k = strpos( $CFG, 'public static function nhan_coso_ngoai(' );
$than_nhan = substr( $CFG, $k, strpos( $CFG, "\n\t}", $k ) - $k + 3 );
t( '🔴 nhan_coso_ngoai() KHÔNG phát móc (không thì ném qua ném lại)',
	false === strpos( $than_nhan, 'do_action(' ) && false === strpos( $than_nhan, 'bao_coso_posh_' ), '' );
$h = strpos( $CFG, 'public static function hut_coso_ghe()' );
$than_hut = substr( $CFG, $h, strpos( $CFG, "\n\t}", $h ) - $h + 3 );
t( '   nút hút tay cũng KHÔNG phát', false === strpos( $than_hut, 'do_action(' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: chỉ cơ sở đơn vị $DV sang bên ghế, không kéo theo gì khác.\n";
