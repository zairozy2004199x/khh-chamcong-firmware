<?php
/**
 * Kiểm VAI TRÒ TỰ TẠO của plugin chi phí.
 *
 * Anh Thắng 25/08/2026: "Bổ sung cấu hình tạo vai trò". Chốt phương án: vai tự tạo KẾ THỪA
 * toàn bộ quyền của một vai gốc.
 *
 * 🔴 VÌ SAO PHẢI KIỂM RIÊNG: phân quyền là chỗ sai thì không ai thấy. Vai tự tạo mà quy nhầm
 * về vai cao hơn là mở cửa; quy nhầm về rỗng là lọt qua mọi phép `in_array` rỗng; còn bảng ma
 * trận CH_Quyen lưu theo CHỈ SỐ CỘT nên chèn một vai vào giữa là mọi ô đã tích trượt sang vai
 * khác — cả bảng sai mà không có gì báo.
 *
 * Chạy: php tools/test/kiem-vai-tro.php
 */

define( 'VHCP_TEST', true );
define( 'ABSPATH', __DIR__ );

$hong = 0; $dat = 0;
function la( $ten, $mong, $duoc ) {
	global $hong, $dat;
	if ( $mong === $duoc ) { $dat++; return; }
	printf( "  HỎNG %-52s mong %s · được %s\n", $ten, var_export( $mong, true ), var_export( $duoc, true ) );
	$hong++;
}
function that( $ten, $d ) { la( $ten, true, (bool) $d ); }

/* Khung giả: chỉ cần đủ để gọi được ba hàm thuần về vai trò. Không dựng WordPress. */
class VHCP_Util { public static function ok( $x = array() ) { return array_merge( array( 'success' => true ), $x ); }
	public static function err( $m ) { return array( 'success' => false, 'error' => $m ); }
	public static function quyen_truthy( $v ) { $v = trim( (string) $v ); return ( $v !== '' && $v !== '0' && strtolower( $v ) !== 'false' ); } }

/* Nạp mã thật của VHCP_Cfg nhưng chặn phần đụng cơ sở dữ liệu: thay read() bằng bảng giả. */
$src = file_get_contents( __DIR__ . '/../../wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
$src = preg_replace( '/^<\?php/', '', $src, 1 );
/* 🔴 XOÁ read() THẬT TRƯỚC, CHÈN read() GIẢ SAU — không được làm ngược.
   Làm ngược thì hàm giả vừa chèn cũng khớp mẫu xoá (nó cũng mở bằng
   "public static function read( $bang )"), và `.*?` chạy tới dấu đóng ngoặc nhọn ở tận dưới,
   nuốt luôn cả khối hằng số. Em đã cắt nhầm đúng kiểu này, nên bên dưới có chốt độ dài. */
$truoc = strlen( $src );
$src = preg_replace( '/\n\tpublic static function read\( \$bang \)[^\n]*\n(.*?)\n\t\}\n/s', "\n", $src, 1 );
$cat  = $truoc - strlen( $src );

/* Cắt quá tay = mẫu ăn lan sang phần khác. Thà dừng còn hơn chạy trên mã đã bị xén. */
if ( $cat < 20 || $cat > 500 ) {
	echo "  HỎNG: mẫu xoá read() cắt mất $cat ký tự — không đúng một hàm\n";
	exit( 1 );
}
if ( false === strpos( $src, "const VAI " ) || false === strpos( $src, 'function vai_goc' ) ) {
	echo "  HỎNG: nạp hụt mã nguồn sau khi cắt\n";
	exit( 1 );
}

$src = str_replace( 'class VHCP_Cfg {', 'class VHCP_Cfg { public static $GIA = array();
	public static function read( $b ) { return isset( self::$GIA[ $b ] ) ? self::$GIA[ $b ] : array(); }', $src );

/* Rỗng hoá mấy hàm WordPress mà ba hàm đang thử có thể chạm tới. */
$src = str_replace( 'wp_cache_delete(', 'null && wp_cache_delete(', $src );
$src = str_replace( 'delete_transient(', 'null && delete_transient(', $src );

eval( $src );

echo "— danh sách vai —\n";
VHCP_Cfg::$GIA = array();
la( 'chưa khai gì -> đúng 4 vai gốc',
	array( 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' ), VHCP_Cfg::roles() );

VHCP_Cfg::$GIA[ VHCP_Cfg::VAI ] = array(
	array( 'Nhân viên văn phòng', 'Nhân viên' ),
	array( 'Kế toán vùng',        'Kế toán cá nhân' ),
);
$r = VHCP_Cfg::roles();
/* 🔴 BỐN VAI GỐC PHẢI ĐỨNG ĐẦU, ĐÚNG THỨ TỰ. Cột ma trận neo theo chỉ số. */
la( 'bốn vai gốc vẫn đứng đầu đúng thứ tự',
	array( 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' ), array_slice( $r, 0, 4 ) );
la( 'vai mới nối vào CUỐI', array( 'Nhân viên văn phòng', 'Kế toán vùng' ), array_slice( $r, 4 ) );

echo "— quy về vai gốc —\n";
la( 'vai gốc -> chính nó',        'Nhân viên', VHCP_Cfg::vai_goc( 'Nhân viên' ) );
la( 'Admin -> Admin',             'Admin',     VHCP_Cfg::vai_goc( 'Admin' ) );
la( 'rỗng -> rỗng',               '',          VHCP_Cfg::vai_goc( '' ) );
la( 'vai tự tạo -> vai gốc',      'Nhân viên', VHCP_Cfg::vai_goc( 'Nhân viên văn phòng' ) );
la( 'vai tự tạo 2 -> vai gốc',    'Kế toán cá nhân', VHCP_Cfg::vai_goc( 'Kế toán vùng' ) );
/* 🔴 Vai lạ (dòng người dùng còn giữ tên vai đã xoá) phải tụt về NHÂN VIÊN, không phải rỗng.
   Trả rỗng là lọt qua mọi phép kiểm in_array rỗng — tức thành vai không bị chặn gì. */
la( 'vai đã xoá -> tụt về Nhân viên', 'Nhân viên', VHCP_Cfg::vai_goc( 'Vai nào đó đã xoá' ) );

echo "— chốt chặn leo quyền —\n";
VHCP_Cfg::$GIA[ VHCP_Cfg::VAI ] = array(
	array( 'Sếp tổng', 'Admin' ),          // cố kế thừa Admin
	array( 'Admin',    'Quản lý' ),        // cố đặt tên trùng Admin
	array( 'Nhân viên','Quản lý' ),        // cố đè lên vai gốc
	array( 'Vai lạ',   'Vai không có' ),   // kế thừa bậy
);
$v = VHCP_Cfg::vai_tuy_bien();
$ten = array(); foreach ( $v as $x ) { $ten[] = $x['ten']; }
la( 'bỏ dòng đặt tên trùng Admin / vai gốc', array( 'Sếp tổng', 'Vai lạ' ), $ten );
/* 🔴 KẾ THỪA ADMIN PHẢI BỊ CHẶN. Cho phép là ai vào Cấu hình cũng tự đúc một Admin trá hình. */
la( 'kế thừa Admin bị hạ về Nhân viên', 'Nhân viên', VHCP_Cfg::vai_goc( 'Sếp tổng' ) );
/* Kế thừa bậy thì hạ về vai THẤP nhất, không nâng lên vai cao. */
la( 'kế thừa bậy hạ về Nhân viên',      'Nhân viên', VHCP_Cfg::vai_goc( 'Vai lạ' ) );

echo "— trùng tên —\n";
VHCP_Cfg::$GIA[ VHCP_Cfg::VAI ] = array( array( 'NV VP', 'Nhân viên' ), array( 'nv vp', 'Quản lý' ) );
la( 'trùng tên (khác hoa thường) chỉ lấy dòng đầu', 1, count( VHCP_Cfg::vai_tuy_bien() ) );
la( '  và giữ vai gốc của dòng đầu', 'Nhân viên', VHCP_Cfg::vai_goc( 'NV VP' ) );

echo "\n";
if ( $hong ) { echo "🔴 HỎNG: $hong | ĐẠT: $dat\n"; exit( 1 ); }
echo "✓ SẠCH — $dat phép vai trò\n";
