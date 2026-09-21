<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VĂN PHÒNG & MÁY TỰ ĐỘNG: KHÔNG TẠM ỨNG THÌ MỖI DÒNG CHI MỘT CƠ SỞ.
 *
 * Anh Thắng 14/09/2026: *"Đối với bộ phận văn phòng và máy tự động — nếu nhập tạm ứng thì nó sẽ
 * khóa theo cơ sở chọn tạm ứng; còn nếu không nhập tạm ứng mà nhập chi phí bình thường thì cho
 * cơ chế mỗi chi phí sẽ 1 cơ sở, nên không khóa cơ sở đó lại"*. Và ngay sau đó: *"nếu không nhập
 * tạm ứng thì hiểu là đơn thường nhiều cơ sở thì số tiền sẽ lấy theo số thực tế trên đơn"*.
 *
 * =============================================================================================
 * 🔴 HAI CÁCH LÀM VIỆC, KHÔNG PHẢI HAI SỞ THÍCH.
 *   · CÓ TẠM ỨNG: tiền đã giao cho một người ở một gian, đối chiếu thừa/thiếu theo chính gian
 *     ấy — xin ứng gian này mà chi gian khác là sổ không khớp. Khoá là đúng.
 *   · KHÔNG TẠM ỨNG: tiêu tiền túi hoặc trả thẳng NCC rồi gom một đợt, rải qua nhiều gian.
 *     Khoá cả đơn theo dòng đầu là ép họ lập năm đơn cho một đợt chi.
 *
 * 🔴 KVC KHÔNG ĐỔI — hằng `MO_KHI_KHONG_TAM_UNG` để `false` ở bản gốc, script tách lật `true`
 *    cho bản mảng riêng. Bài này canh cả hai đầu ấy.
 *
 * Chạy: php tools/test/kiem-khong-tam-ung-nhieu-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$GOC = dirname( dirname( __DIR__ ) );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$DV  = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-donvi.php' );
$DON = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
function bo_ct( $x ) { return preg_replace( '#/\*[\s\S]*?\*/|//[^\n]*#', ' ', $x ); }
function boc( $src, $ten ) {
	$a = strpos( $src, $ten );
	if ( false === $a ) { echo "\n✗ Không bốc được $ten — dừng.\n"; exit( 1 ); }
	return substr( $src, $a, strpos( $src, "\n\t}", $a ) - $a + 3 );
}

/* ═══ 1. BẢN GỐC GIỮ LUẬT CHẶT, BẢN MẢNG RIÊNG MỞ ════════════════════════════════ */
preg_match( '/const MO_KHI_KHONG_TAM_UNG = (true|false);/', $DV, $m );
t( '🔴 có hằng MO_KHI_KHONG_TAM_UNG', ! empty( $m[1] ), $m );
teq( '🔴 bản gốc KHU VUI CHƠI: hằng = false (đừng đụng mảng đang chạy)', 'false', $m[1] );

foreach ( array( 'mtd', 'vp' ) as $ma ) {
	$d = $GOC . '/wordpress/vhcp-chi-phi-' . $ma . '/includes/class-vhcp-donvi.php';
	if ( ! is_file( $d ) ) { continue; }
	t( '🔴 bản «' . $ma . '»: hằng = true',
		(bool) preg_match( '/const MO_KHI_KHONG_TAM_UNG = true;/', file_get_contents( $d ) ), '' );
}
/* Và script tách phải có CHỐT, không chỉ có lệnh sed: sed trượt thì im lặng. */
$sh = file_get_contents( $GOC . '/tools/tach-ban-vung.sh' );
t( '🔴 script tách có chốt kiểm hằng ấy sau khi lật',
	(bool) preg_match( '#grep -q "const MO_KHI_KHONG_TAM_UNG = true;"#', $sh ), '' );

/* ═══ 2. CHẠY THẬT `don_nhieu_coso()` VỚI CSDL GIẢ ═══════════════════════════════ */
class KHO { public static $don = array(); public static $tu_n = array(); public static $tu = array();
	public static $chi = array(); public static $sum = array(); public static $option = null; }
function get_option( $k, $d = false ) { return ( null === KHO::$option ) ? $d : KHO::$option; }
class WPDB_GIA {
	public function prepare( $q, ...$a ) { $a = ( 1 === count( $a ) && is_array( $a[0] ) ) ? $a[0] : $a; return array( $q, $a ); }
	public function get_var( $x ) {
		list( $q, $a ) = $x; $ma = (string) $a[0];
		if ( false !== strpos( $q, 'COUNT(*)' ) )  { return isset( KHO::$tu_n[ $ma ] ) ? KHO::$tu_n[ $ma ] : 0; }
		if ( false !== strpos( $q, 'SUM(' ) )      { return isset( KHO::$sum[ $ma ] ) ? KHO::$sum[ $ma ] : 0; }
		if ( false !== strpos( $q, 'tamung' ) )    { return isset( KHO::$tu[ $ma ] ) ? KHO::$tu[ $ma ] : null; }
		return isset( KHO::$chi[ $ma ] ) ? KHO::$chi[ $ma ] : null;
	}
}
class VHCP_DB { public static function t( $x ) { return 'wp_vhcp_' . $x; } }
class VHCP_Util { public static function num( $v ) { return (float) $v; } }
class VHCP_Don { public static function don_row( $m ) { return isset( KHO::$don[ $m ] ) ? KHO::$don[ $m ] : null; } }

/* Bốc HÀM THẬT, và cho chạy với hằng = TRUE (cảnh của bản Văn phòng / Máy tự động). */
eval( 'class DVI { const MAC_DINH = "K&H"; const NHIEU_COSO_MAC_DINH = "POSH"; const MO_KHI_KHONG_TAM_UNG = true; '
	. boc( $DV, 'public static function nhieu_coso(' ) . ' '
	. boc( $DV, 'public static function don_nhieu_coso(' ) . ' '
	. boc( $DV, 'public static function don_co_tam_ung(' ) . ' '
	. ' public static function chuan( $x ) { $x = trim( (string) $x ); return "" === $x ? self::MAC_DINH : $x; }'
	. ' public static function bang( $a, $b ) { return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) ); } }' );
class_alias( 'DVI', 'VHCP_DonVi' );
eval( 'class TD { ' . boc( $DON, 'public static function coso_cua_don(' ) . ' '
	. str_replace( 'private static function', 'public static function', boc( $DON, 'private static function loi_khac_coso(' ) ) . ' }' );

global $wpdb; $wpdb = new WPDB_GIA();
KHO::$don = array( 'D_MO' => array( 'don_vi' => 'K&H' ), 'D_UNG' => array( 'don_vi' => 'K&H' ) );

/* — đơn CHƯA có tạm ứng: mở — */
KHO::$tu_n = array( 'D_MO' => 0 );
KHO::$chi  = array( 'D_MO' => 'Aeon Tân Phú' );   // đã có dòng chi, vẫn không được chốt
teq( '🔴 chưa có tạm ứng -> đơn KHÔNG chốt cơ sở', '', TD::coso_cua_don( 'D_MO' ) );
teq( '🔴 nên dòng chi gian khác vẫn NHẬN', '', TD::loi_khac_coso( 'D_MO', 'Aeon Bình Tân' ) );
teq( '   và gian thứ ba cũng nhận',        '', TD::loi_khac_coso( 'D_MO', 'VR SORA' ) );

/* — đơn ĐÃ có tạm ứng: khoá lại — */
KHO::$tu_n = array( 'D_UNG' => 1 );
KHO::$tu   = array( 'D_UNG' => 'Aeon Tân Phú' );
teq( '🔴 có tạm ứng -> CHỐT đúng gian của tạm ứng', 'Aeon Tân Phú', TD::coso_cua_don( 'D_UNG' ) );
t( '🔴 và dòng chi gian khác bị CHỐI', '' !== TD::loi_khac_coso( 'D_UNG', 'VR SORA' ), '' );

/* 🔴 THƯỚC ĐO LÀ "CÓ DÒNG TẠM ỨNG", KHÔNG PHẢI "TIỀN ỨNG > 0".
   Người ta lưu tạm ứng 0đ cho một gian để đánh dấu "đơn này thuộc gian ấy, kế toán chi bù khi
   quyết toán" — đó VẪN là chốt gian. Đo bằng số tiền là mở toang đúng những đơn ấy. */
KHO::$tu_n = array( 'D_UNG' => 1 );
KHO::$tu   = array( 'D_UNG' => 'Aeon Tân Phú' );
KHO::$sum  = array( 'D_UNG' => 0 );
teq( '🔴 tạm ứng 0đ vẫn là chốt gian', 'Aeon Tân Phú', TD::coso_cua_don( 'D_UNG' ) );

/* Đơn vị POSH vẫn ghép nhiều gian như cũ, không liên quan hằng mới. */
KHO::$don['D_POSH'] = array( 'don_vi' => 'POSH' );
KHO::$tu_n['D_POSH'] = 1; KHO::$tu['D_POSH'] = 'POSH HCM';
teq( 'POSH có tạm ứng vẫn ghép nhiều gian (luật cũ thắng)', '', TD::coso_cua_don( 'D_POSH' ) );

/* ═══ 3. SỐ TIỀN BÀY CHO NGƯỜI DUYỆT ═════════════════════════════════════════════
 * 🔴 Ảnh anh Thắng: đơn hai dòng cộng 3.000.000đ mà cột SỐ XIN ghi 0đ. 0đ ở đây là một con số
 *    SAI, không phải ô trống — người duyệt đọc cột ấy để quyết định.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$than = bo_ct( boc( $DON, 'public static function tong_de_duyet(' ) );
t( '🔴 có cửa riêng tong_de_duyet()', '' !== $than, '' );
t( '🔴 nó gác bằng MO_KHI_KHONG_TAM_UNG (KVC không đổi)',
	false !== strpos( $than, 'MO_KHI_KHONG_TAM_UNG' ), '' );
t( '🔴 có tạm ứng thì trả đúng số xin như cũ',
	false !== strpos( $than, 'don_co_tam_ung' ), '' );
t( '🔴 không tạm ứng thì lấy tổng thành tiền trên đơn',
	false !== strpos( $than, 'tong_thanh_tien' ), '' );

/* ⚠️ KHÔNG được sửa thẳng `tong_xin_hien_tai()`: hai công cụ soát số duyệt mồ côi / bù trừ đối
   chiếu nó với số duyệt lịch sử theo phép "duyệt = xin + bù trừ". */
$than_cu = bo_ct( boc( $DON, 'public static function tong_xin_hien_tai(' ) );
t( '⚠️ tong_xin_hien_tai() KHÔNG bị đổi nghĩa',
	false === strpos( $than_cu, 'MO_KHI_KHONG_TAM_UNG' )
		&& false === strpos( $than_cu, 'tong_thanh_tien' ), $than_cu );

/* 🔴 Khối QUYẾT TOÁN cố ý ngược lại: đơn không xin tạm ứng phải ra "Thiếu N — kế toán bù cho
   NV", chứ không phải "Khớp". Lấy thực chi lấp vào chỗ tạm ứng tại đó là xoá mất khoản kế toán
   còn nợ nhân viên. */
t( '🔴 khối quyết toán vẫn KHÔNG lấy thực chi lấp chỗ tạm ứng',
	(bool) preg_match( '#\$cn_tu = \$ad_total;#', $DON ), '' );

/* ═══ 4. TRANG TỔNG HỎI ĐÚNG CỬA ═════════════════════════════════════════════════ */
$gom = bo_ct( file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-tong/includes/class-vhcpt-gom.php' ) );
t( '🔴 trang tổng hỏi tong_de_duyet() TRƯỚC',
	(bool) preg_match( "#method_exists\( \\\$lop_don, 'tong_de_duyet' \)#", $gom ), '' );
t( '⚠️ và LUI VỀ tong_xin_hien_tai() cho bản mảng đời cũ',
	(bool) preg_match( "#elseif[\s\S]{0,120}?'tong_xin_hien_tai'#", $gom ), '' );

/* ═══ 5. LƯU TẠM ỨNG LÀ ĐÓNG ĐƠN LẠI MỘT GIAN — PHẢI CHỐI NẾU ĐANG LỆCH ═════════
 * 🔴 Đơn đang mở, dòng chi rải ba gian, rồi mới lưu tạm ứng: đơn chốt về một gian còn tiền nằm
 *    ở hai gian kia. Tổng vẫn khớp nên đối chiếu không kêu — chỉ báo cáo theo gian là sai, ở
 *    đúng chỗ không ai soi.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$than_tu = bo_ct( boc( $DON, 'public static function set_tam_ung(' ) );
t( '🔴 set_tam_ung() chối khi đơn đang có dòng chi gian khác',
	false !== strpos( $than_tu, 'cac_coso_cua_don( $ma_don )' )
		&& false !== strpos( $than_tu, 'MO_KHI_KHONG_TAM_UNG' ), '' );
/* ⚠️ CHỐI, KHÔNG TỰ SỬA. Đừng đoán hộ là người ta muốn dời mấy dòng kia sang gian mới hay muốn
   bỏ tạm ứng — cả hai đều đổi số tiền của một gian. (Câu `ON DUPLICATE KEY UPDATE` trong hàm là
   lượt ghi chính dòng tạm ứng, không phải lượt dời dòng chi; nên phép canh hỏi thẳng bảng chi.) */
t( '⚠️ và nó CHỐI chứ không tự dời dòng chi sang gian mới',
	false !== strpos( $than_tu, 'VHCP_Util::err' )
		&& ! preg_match( "#UPDATE [^\n]*chiphi#", $than_tu ), $than_tu );

/* ═══════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ ĐẠT: $DAT phép thử — không tạm ứng thì mở gian và lấy số thực tế; có tạm ứng thì khoá.\n";
