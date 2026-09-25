<?php
/**
 * MỞ CƠ SỞ MỚI THÌ PHẢI BÁO RA NGOÀI.
 *
 * =================================================================================================
 * 🔴 CÂU HỎI SINH RA TỆP NÀY
 * =================================================================================================
 * Anh Thắng 19/09/2026: *"Hiện tại bên Chấm công cơ sở mới, làm sao nó đẩy cho bên chi phí biết
 * là có cơ sở mới"*.
 *
 * Câu trả lời thật lúc ấy: KHÔNG ĐẨY GÌ CẢ. Danh mục cơ sở bên chi phí chỉ lớn lên bằng hai cách
 * — móc từ plugin Ghế, hoặc kế toán gõ tay. Nên mở một gian mới ở đây xong, bên ấy vẫn không có
 * nó trong ô chọn, và mọi khoản chi của gian ấy không biết bỏ vào đâu cho tới lúc có người nhớ
 * ra phải đi khai tay.
 *
 * Nay `xep_bo_phan()` bắn `do_action( 'vhcc_coso_da_luu', $coso, $bo_phan )`.
 *
 * ⚠️ TÊN MÓC LÀ MỘT GIAO KÈO VỚI PLUGIN KHÁC. Đổi tên ở đây thì bên kia im lặng thôi nhận —
 *    không báo lỗi, không hỏng màn nào, chỉ là cơ sở mới lại vắng mặt như cũ. Bài này giữ cái
 *    tên ấy đứng yên.
 *
 * Chạy: php tools/test/kiem-bao-coso-moi.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 300 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

$AD = array( 'name' => 'Sếp', 'role' => VHCC_Vai::ADMIN, 'ma_nv' => 'CSAD' );
$NV = array( 'name' => 'Em NV', 'role' => VHCC_Vai::NV, 'ma_nv' => 'CSNV' );

/* Bắt tiếng chuông. */
$GLOBALS['vhcc_nghe'] = array();
add_action( 'vhcc_coso_da_luu', function ( $coso, $bp = '' ) {
	$GLOBALS['vhcc_nghe'][] = array( 'coso' => $coso, 'bp' => $bp );
}, 10, 2 );

/* ====================================================================== 1. cơ sở mới thì bắn */

echo "— cơ sở mới thì bắn chuông —\n";
$r = VHCC_NhanSu::xep_bo_phan( $AD, 'GIAN_MOI_01', 'Khu vui chơi' );
t( 'mở được cơ sở mới', ! empty( $r['ok'] ), $r );
teq( '🔴 có đúng MỘT tiếng chuông', 1, count( $GLOBALS['vhcc_nghe'] ) );
teq( '   kèm đúng tên cơ sở', 'GIAN_MOI_01', $GLOBALS['vhcc_nghe'][0]['coso'] );
teq( '   và kèm bộ phận', 'Khu vui chơi', $GLOBALS['vhcc_nghe'][0]['bp'] );

/* 🔴 SỬA BỘ PHẬN CỦA CƠ SỞ ĐÃ CÓ THÌ IM. Đó không phải "cơ sở mới"; bắn cả lượt sửa là bên kia
   nhận một tiếng chuông mỗi lần ai đó đổi bộ phận — rồi họ sẽ thôi nghe. */
$GLOBALS['vhcc_nghe'] = array();
$r = VHCC_NhanSu::xep_bo_phan( $AD, 'GIAN_MOI_01', 'Khu vui chơi' );
t( 'lưu lại cơ sở cũ vẫn chạy', ! empty( $r['ok'] ), $r );
teq( '🔴 nhưng KHÔNG bắn chuông nữa', 0, count( $GLOBALS['vhcc_nghe'] ) );

/* Đổi bộ phận cũng là sửa, không phải mới. */
$GLOBALS['vhcc_nghe'] = array();
VHCC_NhanSu::xep_bo_phan( $AD, 'GIAN_MOI_01', 'Kho' );
teq( '   đổi bộ phận cũng im', 0, count( $GLOBALS['vhcc_nghe'] ) );

/* ⚠️ VIẾT HOA THƯỜNG KHÁC NHAU VẪN LÀ MỘT CƠ SỞ — `xep_bo_phan()` tra `LOWER()`. Bắn thêm một
   tiếng là bên kia đẻ một dòng trùng cho cùng một gian, và tiền của nó tách làm đôi. */
$GLOBALS['vhcc_nghe'] = array();
VHCC_NhanSu::xep_bo_phan( $AD, 'gian_moi_01', 'Kho' );
teq( '🔴 viết thường cùng tên thì vẫn im', 0, count( $GLOBALS['vhcc_nghe'] ) );

/* ====================================================================== 2. chối thì không bắn */

echo "— chối thì không bắn —\n";
$GLOBALS['vhcc_nghe'] = array();
$r = VHCC_NhanSu::xep_bo_phan( $NV, 'GIAN_MOI_02', 'Khu vui chơi' );
t( 'nhân viên thường KHÔNG mở được cơ sở', empty( $r['ok'] ), $r );
teq( '🔴 và KHÔNG bắn chuông — bên kia không được biết một thứ chưa xảy ra',
	0, count( $GLOBALS['vhcc_nghe'] ) );

$GLOBALS['vhcc_nghe'] = array();
$r = VHCC_NhanSu::xep_bo_phan( $AD, 'GIAN_MOI_03', 'Bộ Phận Không Có Thật' );
t( 'bộ phận lạ thì chối', empty( $r['ok'] ), $r );
teq( '🔴 và cũng không bắn', 0, count( $GLOBALS['vhcc_nghe'] ) );

$GLOBALS['vhcc_nghe'] = array();
VHCC_NhanSu::xep_bo_phan( $AD, '', 'Khu vui chơi' );
teq( 'thiếu tên cơ sở thì không bắn', 0, count( $GLOBALS['vhcc_nghe'] ) );

/* ====================================================================== 3. giao kèo */

echo "— tên móc là giao kèo, không được đổi —\n";
$ma = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-nhan-su.php' );
t( "🔴 vẫn bắn đúng tên móc `vhcc_coso_da_luu`",
	false !== strpos( $ma, "do_action( 'vhcc_coso_da_luu', \$coso, \$bp )" ), 'không thấy' );
/* Bắn SAU khi đã ghi xong — bắn trước thì bên nghe đi đọc lại sổ và chưa thấy gì. */
$i_ghi = strpos( $ma, "\$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), \$ghi )" );
$i_ban = strpos( $ma, "do_action( 'vhcc_coso_da_luu'" );
t( '🔴 bắn SAU khi đã ghi vào sổ', false !== $i_ghi && false !== $i_ban && $i_ghi < $i_ban,
	"ghi=$i_ghi ban=$i_ban" );

/* Cơ sở mới thật sự có mặt trong `ds_coso()` lúc bên kia đi hỏi lại — đó là thứ bản vá bên chi
   phí đọc để hút bù khi một tiếng chuông rơi mất. */
t( '🔴 cơ sở mới có trong ds_coso() để bên kia hút bù được',
	in_array( 'GIAN_MOI_01', VHCC_NhanSu::ds_coso(), true ), VHCC_NhanSu::ds_coso() );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — mở cơ sở mới là bên kia biết ngay.\n";
