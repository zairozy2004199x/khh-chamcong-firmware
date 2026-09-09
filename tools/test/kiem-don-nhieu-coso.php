<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỘT ĐƠN = MỘT CƠ SỞ (KVC) · MỘT ĐƠN NHIỀU CƠ SỞ (POSH) — TUỲ ĐƠN VỊ.
 *
 * Anh Thắng 09/09/2026: *"đối với kvc chọn theo cơ sở để lên đơn, còn đối với [POSH], 1 đơn sẽ
 * nhiều cơ sở cho từng chi phí nhỏ"*. Trục phân biệt: *"theo trục đơn vị"*. Đối chiếu thừa/thiếu:
 * *"tính theo 1 đơn"* — không tách theo gian.
 *
 * =============================================================================================
 * 🔴 MỘT CHỖ THÁO CẢ BỐN CHỐT. Luật "một đơn = một cơ sở" (anh Thắng 01/09/2026) không nằm rải
 *    rác: cả bốn chốt đều hỏi `coso_cua_don()`. Cho hàm ấy trả rỗng với đơn vị ghép nhiều gian
 *    là mở đủ cả bốn cùng lúc. Bài này canh ĐÚNG chuyện ấy — và canh luôn rằng bốn chốt kia
 *    KHÔNG mọc thêm phép kiểm riêng, vì mỗi phép riêng là một chỗ để quên.
 *
 * 🔴 KVC KHÔNG ĐƯỢC ĐỘNG TỚI. Đây là mảng đang chạy thật với tiền thật; nới nhầm nó là quay lại
 *    đúng cảnh xin tạm ứng gian này rồi chi gian khác mà anh Thắng đã bỏ.
 *
 * ⚠️ CHẠY THẬT `nhieu_coso()`, `coso_cua_don()`, `loi_khac_coso()` với CSDL giả.
 *
 * Chạy: php tools/test/kiem-don-nhieu-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC = dirname( dirname( __DIR__ ) );
$DV  = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-donvi.php' );
$DON = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
class KHO {
	public static $don   = array();   // ma_don => ['don_vi'=>…]
	public static $tu    = array();   // ma_don => cơ sở của dòng tạm ứng đầu
	public static $chi   = array();   // ma_don => cơ sở của dòng chi đầu
	public static $option = null;     // giá trị khoá vhcp_dv_nhieu_coso
}
function get_option( $k, $d = false ) { return ( null === KHO::$option ) ? $d : KHO::$option; }

class WPDB_GIA {
	public function prepare( $q, ...$a ) { return array( $q, $a ); }
	public function get_var( $x ) {
		list( $q, $a ) = $x;
		$ma = (string) $a[0];
		if ( false !== strpos( $q, 'tamung' ) ) { return isset( KHO::$tu[ $ma ] ) ? KHO::$tu[ $ma ] : null; }
		return isset( KHO::$chi[ $ma ] ) ? KHO::$chi[ $ma ] : null;
	}
}
class VHCP_DB { public static function t( $x ) { return 'wp_vhcp_' . $x; } }

/* Bốc CHÍNH mấy hàm thật. */
function boc( $src, $ten ) {
	$a = strpos( $src, $ten );
	if ( false === $a ) { echo "\n✗ Không bốc được $ten — dừng.\n"; exit( 1 ); }
	return substr( $src, $a, strpos( $src, "\n\t}", $a ) - $a + 3 );
}
/* Mặc định lấy TỪ MÃ THẬT, không gõ lại: đổi hằng ấy mà bài kiểm ghim nguyên văn "POSH" thì nó
   xanh trên một giá trị không còn tồn tại. */
preg_match( "/const NHIEU_COSO_MAC_DINH = '([^']+)';/", $DV, $m_md );
t( '🔴 có hằng NHIEU_COSO_MAC_DINH trong mã thật', ! empty( $m_md[1] ), $m_md );

eval( 'class DVI { const MAC_DINH = "K&H"; const NHIEU_COSO_MAC_DINH = ' . var_export( $m_md[1], true ) . '; '
	. boc( $DV, 'public static function nhieu_coso(' ) . ' '
	. boc( $DV, 'public static function don_nhieu_coso(' ) . ' '
	. ' public static function chuan( $x ) { $x = trim( (string) $x ); return "" === $x ? self::MAC_DINH : $x; }'
	. ' public static function bang( $a, $b ) { return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) ); } }' );
class VHCP_Don { public static function don_row( $m ) { return isset( KHO::$don[ $m ] ) ? KHO::$don[ $m ] : null; } }
eval( 'class TD { ' . boc( $DON, 'public static function coso_cua_don(' ) . ' '
	. str_replace( 'private static function', 'public static function', boc( $DON, 'private static function loi_khac_coso(' ) ) . ' }' );
/* `coso_cua_don()` gọi `VHCP_DonVi::don_nhieu_coso()`; nối sang lớp bốc thật. */
class_alias( 'DVI', 'VHCP_DonVi' );

$POSH = $m_md[1];

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. LUẬT THEO ĐƠN VỊ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
KHO::$option = null;
t( '🔴 ' . $POSH . ' → ghép nhiều cơ sở',      DVI::nhieu_coso( $POSH ) === true );
t( '🔴 K&H → KHÔNG ghép (mảng đang chạy)',     DVI::nhieu_coso( 'K&H' ) === false );
t( '   ô đơn vị RỖNG = K&H → KHÔNG ghép',      DVI::nhieu_coso( '' ) === false );
t( '   so bỏ qua hoa/thường',                  DVI::nhieu_coso( strtolower( $POSH ) ) === true );
t( '   và bỏ qua khoảng trắng thừa',           DVI::nhieu_coso( ' ' . $POSH . ' ' ) === true );
t( '   đơn vị lạ (HN) → KHÔNG ghép',           DVI::nhieu_coso( 'HN' ) === false );
/* 🔴 KHÔNG BIẾT THÌ NGÃ VỀ LUẬT CHẶT. Đơn không tra được (mã sai, đơn vừa bị xoá) mà trả "ghép
   nhiều gian" là ngã về phía NỚI — mọi chốt hỏi nó đều mở ra cho một thứ không rõ lai lịch.
   Hôm nay `coso_cua_don()` rỗng sẵn nên chưa lộ, nhưng chỗ hỏi tiếp theo thì lộ. */
t( '🔴 đơn KHÔNG tra được → ngã về luật CHẶT (một đơn một gian)',
	DVI::don_nhieu_coso( 'D_KHONG_CO' ) === false );

/* Khai thêm đơn vị bằng khoá cấu hình. */
KHO::$option = $POSH . ', HN';
t( 'khai thêm HN qua khoá cấu hình → HN ghép được', DVI::nhieu_coso( 'HN' ) === true );
t( '   và ' . $POSH . ' vẫn ghép',                   DVI::nhieu_coso( $POSH ) === true );
t( '   K&H vẫn KHÔNG',                               DVI::nhieu_coso( 'K&H' ) === false );
/* 🔴 Khoá rỗng KHÔNG được hiểu là "không đơn vị nào" — mất luôn mặc định là POSH lặng lẽ quay
   về một-đơn-một-cơ-sở, mà không ai đụng vào cấu hình. */
KHO::$option = '   ';
t( '🔴 khoá cấu hình RỖNG → lui về mặc định, không tắt sạch', DVI::nhieu_coso( $POSH ) === true );
KHO::$option = null;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 MỘT CHỐT THÁO CẢ BỐN — `coso_cua_don()`
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
global $wpdb; $wpdb = new WPDB_GIA();
KHO::$don = array( 'D_KVC' => array( 'don_vi' => 'K&H' ), 'D_POSH' => array( 'don_vi' => $POSH ) );
KHO::$tu  = array( 'D_KVC' => 'Aeon Bình Tân', 'D_POSH' => 'POSH HCM' );

teq( '🔴 đơn K&H đã có tạm ứng → CHỐT cơ sở ấy', 'Aeon Bình Tân', TD::coso_cua_don( 'D_KVC' ) );
teq( '🔴 đơn ' . $POSH . ' → KHÔNG chốt gì (rỗng)', '', TD::coso_cua_don( 'D_POSH' ) );

/* Chốt thứ 3: dòng chi gắn cơ sở khác. */
teq( '🔴 K&H: dòng chi cơ sở khác → CHỐI',
	true, '' !== TD::loi_khac_coso( 'D_KVC', 'AEON MALL TÂN PHÚ' ) );
t( '   câu chối nói rõ đơn đang của gian nào',
	false !== mb_strpos( TD::loi_khac_coso( 'D_KVC', 'AEON MALL TÂN PHÚ' ), 'Aeon Bình Tân' ) );
teq( '🔴 ' . $POSH . ': dòng chi gian nào cũng NHẬN', '', TD::loi_khac_coso( 'D_POSH', 'POSH Gò Vấp' ) );
teq( '   và gian thứ ba cũng nhận',                    '', TD::loi_khac_coso( 'D_POSH', 'TÀU ESTELLA' ) );

/* Đơn chưa có gì thì bên nào cũng còn tự do. */
KHO::$tu = array();
teq( 'đơn K&H chưa có dòng → chưa chốt', '', TD::coso_cua_don( 'D_KVC' ) );
/* Ưu tiên tạm ứng trước dòng chi — giữ nguyên thứ tự cũ. */
KHO::$tu  = array( 'D_KVC' => 'Từ tạm ứng' );
KHO::$chi = array( 'D_KVC' => 'Từ dòng chi' );
teq( 'K&H: chốt theo TẠM ỨNG trước, rồi mới tới dòng chi', 'Từ tạm ứng', TD::coso_cua_don( 'D_KVC' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 BỐN CHỐT PHẢI CÙNG HỎI MỘT CHỖ
 *
 * Mỗi chốt tự mọc thêm phép kiểm riêng là một chỗ để quên, và chỗ quên nào cũng ra NỬA tính
 * năng: ô chọn mở mà máy chủ vẫn chối, hoặc ngược lại — kiểu hỏng khó thấy nhất.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$than_tu = boc( $DON, 'public static function set_tam_ung(' );
t( '🔴 set_tam_ung() hỏi qua coso_cua_don(), không tự kiểm đơn vị',
	false !== strpos( $than_tu, 'self::coso_cua_don( $ma_don )' )
	&& false === strpos( $than_tu, 'nhieu_coso' ), '' );
$than_lk = boc( $DON, 'private static function loi_khac_coso(' );
t( '   loi_khac_coso() cũng vậy',
	false !== strpos( $than_lk, 'self::coso_cua_don( $ma_don )' )
	&& false === strpos( $than_lk, 'nhieu_coso' ), '' );
/* Và chốt phân quyền KHÔNG được đi qua `coso_cua_don()` — nó phải nhìn ĐỦ mọi gian của đơn,
   không thì người phụ trách gian thứ hai không mở nổi đơn có phần chi của chính gian mình. */
$than_q = boc( $DON, 'private static function loi_khong_phai_don_minh(' );
t( '🔴 chốt phân quyền dùng cac_coso_cua_don() (ĐỦ mọi gian), không dùng coso_cua_don()',
	false !== strpos( $than_q, 'cac_coso_cua_don( $ma_don )' )
	&& false === strpos( $than_q, 'self::coso_cua_don(' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. GIAO DIỆN NHẮC ĐÚNG CA
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$HTML = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 máy chủ gửi cờ nhieuCoSo xuống màn',
	false !== strpos( $DON, "\$don['nhieuCoSo'] = VHCP_DonVi::don_nhieu_coso( \$ma_don );" ), '' );
t( '   màn đọc cờ ấy chứ KHÔNG tự đoán lại luật',
	false !== strpos( $HTML, 'CUR.don.nhieuCoSo' )
	&& false === strpos( $HTML, "'POSH'===" ), '' );
t( '🔴 đơn ghép nhiều gian KHÔNG bị nhắc "phải tạo đơn mới"',
	false !== strpos( $HTML, 'ghép <b>nhiều cơ sở</b>' ), '' );
t( '   và câu nhắc cũ vẫn còn cho mảng một-gian',
	false !== strpos( $HTML, 'là đơn chốt cơ sở' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: $POSH ghép nhiều gian trong một đơn, K&H giữ nguyên một-đơn-một-gian.\n";
