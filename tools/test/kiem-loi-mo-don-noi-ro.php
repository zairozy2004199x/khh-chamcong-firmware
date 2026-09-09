<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỞ ĐƠN KHÔNG ĐƯỢC THÌ PHẢI NÓI RÕ VÌ SAO — BỐN NGUYÊN NHÂN, BỐN CÂU KHÁC NHAU.
 *
 * Cắn thật 09/09/2026. Anh Thắng lập đơn, màn báo "Đã tạo đơn" rồi lập tức "Không tìm thấy đơn".
 *
 * =============================================================================================
 * 🔴 BẢY CHỮ ẤY KHI ĐÓ PHÁT RA TỪ BA CHỖ KHÁC HẲN NHAU:
 *      · đơn không có trong bảng          (ghi hỏng, hoặc mã sai)
 *      · đơn thuộc đơn vị mình không xem được
 *      · đơn của người khác, khác cơ sở
 *    Ba nguyên nhân, ba cách chữa hoàn toàn khác nhau, mà cùng một câu chữ. Không ai lần ra
 *    được — kể cả người viết ra chúng, phải đọc ngược cả bốn tệp mới biết đang ở nhánh nào.
 *    Một câu lỗi không phân biệt được nguyên nhân thì không phải câu lỗi, nó là bức tường.
 *
 * 🔴 VÀ `create_don()` BÁO XONG KHI CHƯA CHẮC ĐÃ GHI ĐƯỢC. Nó trả `ok()` vô điều kiện, không
 *    soi kết quả `insert()`. Một lượt ghi hỏng vẫn ra màn xanh kèm một mã đơn KHÔNG TỒN TẠI —
 *    đúng cặp thông báo anh Thắng nhìn thấy.
 *
 * ⚠️ NHƯNG KHÔNG ĐƯỢC NÓI HẾT CHO MỌI NGƯỜI. Câu mờ là CỐ Ý với người lạ: nói thẳng "đơn này
 *    của K&H" cho kế toán POSH là biến ô gõ mã đơn thành máy dò. Chỗ cắt: NGƯỜI LẬP được nói
 *    thẳng (đơn của chính họ, không lộ gì mới), người khác giữ câu mờ.
 *
 * ⚠️ CHẠY THẬT `vi_sao_khong_dung()` và `create_don()` với CSDL giả.
 *
 * Chạy: php tools/test/kiem-loi-mo-don-noi-ro.php
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
	public static $don = array();        // ma_don => hàng
	public static $toi = '';             // ai đang đăng nhập
	public static $xem = null;           // xem_duoc() trả gì
}
class VHCP_Auth { public static function nguoi() { return KHO::$toi; } }
class VHCP_Don  { public static function don_row( $m ) { return isset( KHO::$don[ $m ] ) ? KHO::$don[ $m ] : null; } }

/* Bốc CHÍNH hai hàm thật của lớp đơn vị, cắm `xem_duoc()` giả để lái từng ca. */
$than = '';
foreach ( array( 'public static function vi_sao_khong_dung(', 'private static function loi_khac_don_vi_(' ) as $ten_ham ) {
	$a = strpos( $DV, $ten_ham );
	$b = strpos( $DV, "\n\t}", $a );
	t( 'bốc được ' . rtrim( $ten_ham, '(' ), false !== $a && $b > $a );
	if ( false === $a ) { echo "\n✗ Không bốc được — dừng.\n"; exit( 1 ); }
	$than .= substr( $DV, $a, $b - $a + 3 ) . "\n";
}
eval( 'class DVI { const MAC_DINH = "K&H"; ' . $than
	. ' public static function chuan( $x ) { $x = trim( (string) $x ); return "" === $x ? self::MAC_DINH : $x; }'
	. ' public static function bang( $a, $b ) { return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) ); }'
	. ' public static function xem_duoc() { return KHO::$xem; }'
	. ' public static function duoc_xem( $dv ) { $ds = self::xem_duoc(); if ( null === $ds ) { return true; }'
	. '   foreach ( $ds as $x ) { if ( self::bang( $x, $dv ) ) { return true; } } return false; }'
	. ' }' );

function loi( $ma ) { return DVI::vi_sao_khong_dung( $ma ); }

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 BỐN CA, BỐN CÂU — VÀ PHẢI KHÁC NHAU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
KHO::$don = array( 'D_POSH' => array( 'don_vi' => 'POSH', 'nguoi_lap' => 'Trần Ngọc Quyền' ),
                   'D_KH'   => array( 'don_vi' => 'K&H',  'nguoi_lap' => 'Trần Ngọc Quyền' ),
                   'D_LA'   => array( 'don_vi' => 'K&H',  'nguoi_lap' => 'Người Khác' ) );
KHO::$toi = 'Trần Ngọc Quyền';
KHO::$xem = array( 'POSH' );

$ca = array();
$ca['xem được']       = loi( 'D_POSH' );
$ca['không có']       = loi( 'D_KHONG_CO' );
$ca['khác ĐV, mình lập'] = loi( 'D_KH' );
$ca['khác ĐV, người khác lập'] = loi( 'D_LA' );

teq( '🔴 đơn ĐÚNG đơn vị → cho qua', '', $ca['xem được'] );

t( '🔴 đơn KHÔNG CÓ trong sổ → câu nói rõ, kèm MÃ',
	false !== mb_strpos( $ca['không có'], 'D_KHONG_CO' ) && false !== mb_strpos( $ca['không có'], 'trong sổ' ),
	$ca['không có'] );

/* Ca của anh Thắng: chính mình lập, mà mở không được. Phải nói ĐỦ để tự chữa. */
$c = $ca['khác ĐV, mình lập'];
t( '🔴 mình lập nhưng khác đơn vị → nói tên ĐƠN VỊ CỦA ĐƠN', false !== mb_strpos( $c, '"K&H"' ), $c );
t( '   và nói mình đang xem được những gì',                  false !== mb_strpos( $c, 'POSH' ), $c );
t( '   và chỉ ra CÁCH CHỮA (sửa ô Đơn vị của người lập)',    false !== mb_strpos( $c, 'NGƯỜI LẬP' )
	&& false !== mb_strpos( $c, 'Cấu hình' ), $c );

/* Người lạ: giữ câu mờ. Nói thẳng là biến ô gõ mã đơn thành máy dò sổ bên kia. */
teq( '🔴 người KHÁC lập → giữ câu mờ, KHÔNG lộ đơn vị', 'Không tìm thấy đơn', $ca['khác ĐV, người khác lập'] );
t( '   và KHÔNG hé tên đơn vị của đơn', false === mb_strpos( $ca['khác ĐV, người khác lập'], 'K&H' ), $ca['khác ĐV, người khác lập'] );

/* 🔴 HAI Ô CÙNG RỖNG KHÔNG PHẢI LÀ "CÙNG MỘT NGƯỜI". Đơn nạp từ sổ cũ có thể trống ô người lập;
   còn lượt gọi không qua đăng nhập (cổng máy, lệnh chạy nền) thì tên người gọi cũng rỗng. So
   thẳng hai chuỗi rỗng là khớp — và câu nói rõ bung ra cho một lượt gọi vô danh. Phá thử chỉ
   đúng chỗ này: bỏ vế `'' === $lap` thì mọi phép trên vẫn xanh. */
KHO::$don['D_TRONG'] = array( 'don_vi' => 'K&H', 'nguoi_lap' => '' );
KHO::$toi = '';
teq( '🔴 đơn trống ô người lập + lượt gọi vô danh → VẪN giữ câu mờ',
	'Không tìm thấy đơn', loi( 'D_TRONG' ) );
KHO::$toi = 'Trần Ngọc Quyền';
unset( KHO::$don['D_TRONG'] );

/* 🔴 PHÉP CHÍNH: bốn câu phải PHÂN BIỆT ĐƯỢC. Đây đúng là thứ đã thiếu. */
$khac = array_unique( array_values( $ca ) );
teq( '🔴 bốn ca cho ra bốn câu khác nhau', 4, count( $khac ) );

/* Xem cả (Admin) thì không chốt gì. */
KHO::$xem = null;
teq( 'Admin (xem cả) → mở được mọi đơn vị', '', loi( 'D_KH' ) );
t( '   nhưng đơn không có vẫn báo không có', '' !== loi( 'D_KHONG_CO' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 TẠO ĐƠN KHÔNG ĐƯỢC PHÉP BÁO XONG KHI GHI HỎNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
class VHCP_DB   { public static function t( $x ) { return 'wp_vhcp_' . $x; } }
class VHCP_Util {
	public static function uid( $p ) { return $p . '_TEST'; }
	public static function now_sql() { return '2026-09-09 10:00:00'; }
	public static function ok( $e = array() ) { return array_merge( array( 'success' => true ), $e ); }
	public static function err( $m ) { return array( 'success' => false, 'error' => $m ); }
}
class WPDB_GIA {
	public $cho_ghi = true; public $last_error = ''; public $da_ghi = 0;
	public function insert( $b, $d ) { if ( ! $this->cho_ghi ) { return false; } $this->da_ghi++; return 1; }
}
$i = strpos( $DON, 'public static function create_don(' );
$j = strpos( $DON, "\n\t}", $i );
t( 'bốc được create_don()', false !== $i && $j > $i );
eval( 'class TD { ' . substr( $DON, $i, $j - $i + 3 )
	. ' private static function chuan_ky_moi( $k ) { return $k; } }' );
class VHCP_DonVi { public static function cua_nguoi( $t ) { return 'POSH'; } }

global $wpdb;
$wpdb = new WPDB_GIA();
$r = TD::create_don( 'T9/2026', 'Trần Ngọc Quyền' );
t( 'ghi được → báo xong, kèm mã đơn', ! empty( $r['success'] ) && ! empty( $r['maDon'] ), $r );

$wpdb = new WPDB_GIA(); $wpdb->cho_ghi = false; $wpdb->last_error = "Unknown column 'don_vi'";
$r = TD::create_don( 'T9/2026', 'Trần Ngọc Quyền' );
t( '🔴 GHI HỎNG → KHÔNG được báo xong', empty( $r['success'] ), $r );
t( '   và KHÔNG trả về mã đơn ma',       empty( $r['maDon'] ), $r );
t( '   và mang theo lời của MySQL',      false !== mb_strpos( (string) $r['error'], 'Unknown column' ), $r );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: mở đơn hỏng thì nói rõ hỏng ở đâu, và tạo đơn không báo xong khi chưa ghi được.\n";
