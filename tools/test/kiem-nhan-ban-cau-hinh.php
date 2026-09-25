<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 📦 NHÂN BẢN CẤU HÌNH SANG BẢN KHÁC — anh Thắng 23/09/2026: *"nhân bản cho chi phí hà nội"*.
 *
 * Bản Hà Nội là site riêng, danh mục sinh ra trắng. Bản này TẢI gói, bản kia NHẬP.
 *
 * 🔴 GÓI CHỈ CHỞ DANH MỤC — không người dùng (PIN), không quyền, không SSO, không QR. Danh sách
 *    trắng `goi_bang_ds()` là cổng duy nhất, cả chiều xuất lẫn chiều nhập.
 * 🔴 NHẬP LÀ GHI ĐÈ → chỉ Admin; ghi đúng bảng được tích; có nhật ký.
 * 🔴 CHẠY THẬT trên sổ giả: xuất → nhập lại → đọc sổ ra so.
 *
 * Chạy: php tools/test/kiem-nhan-ban-cau-hinh.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

/* ═══ 0. Bệ đỡ — sổ có sẵn vài bảng ══════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Chi phí cơ sở', '141', '111', '', '', '', 'Chi phí cơ sở', 'both', '', 'mn', 'Nhân Viên Cơ Sở Khu Vui Chơi', 'Chi phí cơ sở', '' ),
	array( 'Chi phí NVL đồ ăn - Mua lẻ', '141', '111', '', '', '', '', 'both', '', 'mn', '', 'Chi phí cơ sở', '' ),
), false );
VHCP_Cfg::write( VHCP_Cfg::BP, array( array( 'Cơ sở', 'gt' ), array( 'Văn phòng', 'dc' ) ), false );
VHCP_Cfg::write( VHCP_Cfg::USER, array( array( 'Anh Thắng', '1111', 'Admin', '', '', '', '' ) ), false );
VHCP_Cfg::write( VHCP_Cfg::COSO, array( array( 'FARM PHAN THIẾT', 'FPT', 'FLMPT', 'FARM MN', '', '', 'MN', '' ) ), false );
VHCP_Cfg::clear_cache();

/* ═══ 1. DANH SÁCH TRẮNG ═══════════════════════════════════════════════════════════════ */
$ds = VHCP_Cfg::goi_bang_ds();
t( 'có danh sách bảng được nhân bản', count( $ds ) >= 8, array_keys( $ds ) );
foreach ( array( VHCP_Cfg::LOAI, VHCP_Cfg::TKNO, VHCP_Cfg::BP, VHCP_Cfg::NHOM, VHCP_Cfg::MANG, VHCP_Cfg::TK, VHCP_Cfg::COSO ) as $b ) {
	t( "   có bảng `$b`", isset( $ds[ $b ] ), $b );
}
foreach ( array( VHCP_Cfg::USER, VHCP_Cfg::QUYEN, VHCP_Cfg::SSO, VHCP_Cfg::QR ) as $b ) {
	t( "🔴 KHÔNG bao giờ chở `$b` (người dùng / quyền / SSO / QR là của từng site)", ! isset( $ds[ $b ] ), $b );
}

/* ═══ 2. XUẤT ══════════════════════════════════════════════════════════════════════════ */
$g = VHCP_Cfg::xuat_goi_cau_hinh();
t( 'xuất được', ! empty( $g['success'] ) && isset( $g['bang'] ) && is_array( $g['bang'] ), $g );
teq( '🔴 gói mang dấu nhận dạng', 'goi-cau-hinh-van-hanh-chi-phi', $g['loai'] );
teq( '   gói ghi khối bản nguồn', VHCP_DB::KHOI, $g['khoi'] );
t( '   gói ghi mốc giờ', 1 === preg_match( '/^\d{4}-\d{2}-\d{2} /', (string) $g['luc'] ), $g['luc'] );
teq( '🔴 đúng và đủ các bảng trong danh sách trắng, không hơn', array_keys( $ds ), array_keys( $g['bang'] ) );
t( '🔴 KHÔNG có bảng người dùng trong gói', ! isset( $g['bang'][ VHCP_Cfg::USER ] ) );
t( '   không một ô nào trong gói chứa PIN Admin', false === strpos( json_encode( $g['bang'], JSON_UNESCAPED_UNICODE ), '1111' ) );
teq( '   bảng loại chi phí đủ 2 dòng', 2, count( $g['bang'][ VHCP_Cfg::LOAI ] ) );
teq( '   dòng đầu đúng nội dung (tên · khối · vai)', array( 'Chi phí cơ sở', 'mn', 'Nhân Viên Cơ Sở Khu Vui Chơi' ),
	array( $g['bang'][ VHCP_Cfg::LOAI ][0][0], $g['bang'][ VHCP_Cfg::LOAI ][0][9], $g['bang'][ VHCP_Cfg::LOAI ][0][10] ) );
t( '   kèm nhãn tiếng người cho từng bảng', isset( $g['nhan'][ VHCP_Cfg::LOAI ] ) && '' !== $g['nhan'][ VHCP_Cfg::LOAI ] );
/* Xin đúng vài bảng. */
$g2 = VHCP_Cfg::xuat_goi_cau_hinh( array( VHCP_Cfg::BP, VHCP_Cfg::USER, 'CH_Ma' ) );
teq( '🔴 xin bảng cụ thể: chỉ trả bảng ấy; người dùng và tên lạ bị bỏ', array( VHCP_Cfg::BP ), array_keys( $g2['bang'] ) );

/* ═══ 3. NHẬP — cổng ═══════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế toán' );
$r = VHCP_Cfg::nhap_goi_cau_hinh( $g, array( VHCP_Cfg::LOAI ) );
t( '🔴 kế toán nhập → chối', empty( $r['success'] ) && false !== mb_strpos( (string) $r['error'], 'Admin' ), $r );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$r = VHCP_Cfg::nhap_goi_cau_hinh( array( 'bang' => array( VHCP_Cfg::LOAI => array() ) ), array( VHCP_Cfg::LOAI ) );
t( '🔴 tệp không có dấu nhận dạng → chối', empty( $r['success'] ), $r );
$r = VHCP_Cfg::nhap_goi_cau_hinh( array( 'loai' => 'goi-cau-hinh-van-hanh-chi-phi' ), array( VHCP_Cfg::LOAI ) );
t( '   gói không có bảng → chối', empty( $r['success'] ), $r );
$r = VHCP_Cfg::nhap_goi_cau_hinh( $g, array() );
t( '   không tích bảng nào → chối, không ghi gì', empty( $r['success'] ), $r );
$goi_lau = $g; $goi_lau['bang'][ VHCP_Cfg::USER ] = array( array( 'Kẻ Lạ', '0000', 'Admin', '', '', '', '' ) );
$r = VHCP_Cfg::nhap_goi_cau_hinh( $goi_lau, array( VHCP_Cfg::USER ) );
t( '🔴 gói lận thêm bảng người dùng và tích nó → chối, KHÔNG ghi', empty( $r['success'] ), $r );
teq( '   bảng người dùng còn nguyên Admin thật', 'Anh Thắng', VHCP_Cfg::read( VHCP_Cfg::USER )[0][0] );

/* ═══ 4. NHẬP — chạy thật: dọn trắng như bản Hà Nội, rồi nhập gói ═════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(), false );
VHCP_Cfg::write( VHCP_Cfg::BP, array( array( 'Marketing', 'tt' ) ), false );   // bản kia có sẵn một dòng khác
VHCP_Cfg::write( VHCP_Cfg::COSO, array( array( 'YOKID BUÔN MÊ THUỘT', '', '', 'POSH MN', '', '', 'MB', '' ) ), false );
VHCP_Cfg::clear_cache();
teq( '   đối chứng: bảng loại đang trắng', 0, count( VHCP_Cfg::read( VHCP_Cfg::LOAI ) ) );
$r = VHCP_Cfg::nhap_goi_cau_hinh( $g, array( VHCP_Cfg::LOAI, VHCP_Cfg::BP ) );
t( 'nhập được', ! empty( $r['success'] ), $r );
teq( '🔴 báo đúng số dòng trước → sau của từng bảng', array( VHCP_Cfg::LOAI => array( 'truoc' => 0, 'sau' => 2 ), VHCP_Cfg::BP => array( 'truoc' => 1, 'sau' => 2 ) ), $r['bang'] );
$loai = VHCP_Cfg::read( VHCP_Cfg::LOAI );
teq( '🔴 bảng loại chi phí nay có 2 dòng', 2, count( $loai ) );
teq( '   đúng nội dung dòng đầu (tên · khối · vai)', array( 'Chi phí cơ sở', 'mn', 'Nhân Viên Cơ Sở Khu Vui Chơi' ), array( $loai[0][0], $loai[0][9], $loai[0][10] ) );
teq( '🔴 luồng bộ phận GHI ĐÈ (Marketing cũ mất, Cơ sở gt · Văn phòng dc về)', 'gt', VHCP_Cfg::luong_cua_bo_phan( 'Cơ sở' ) );
teq( '   ', '', VHCP_Cfg::luong_cua_bo_phan( 'Marketing' ) );
teq( '🔴 bảng KHÔNG tích (cơ sở) giữ nguyên của bản này', 'YOKID BUÔN MÊ THUỘT', VHCP_Cfg::read( VHCP_Cfg::COSO )[0][0] );
teq( '🔴 người dùng của bản này không đổi', 'Anh Thắng', VHCP_Cfg::read( VHCP_Cfg::USER )[0][0] );
/* Dòng lạ trong gói (chuỗi thay vì mảng) bị bỏ, không nổ. */
$goi_ban = $g; $goi_ban['bang'][ VHCP_Cfg::BP ] = array( 'rác', array( 'Kỹ thuật', 'gt' ), 5 );
$r = VHCP_Cfg::nhap_goi_cau_hinh( $goi_ban, array( VHCP_Cfg::BP ) );
teq( '   dòng lạ trong gói bị bỏ, dòng thật vẫn ghi', array( 'truoc' => 2, 'sau' => 1 ), $r['bang'][ VHCP_Cfg::BP ] );
/* Nhật ký. */
$log = json_encode( VHCP_Log::get_log( 5 ), JSON_UNESCAPED_UNICODE );
t( '🔴 có nhật ký "Nhập gói cấu hình"', false !== strpos( $log, 'Nhập gói cấu hình' ), mb_substr( $log, 0, 300 ) );

/* ═══ 4b. 🔴 DẤU NHẬN DẠNG PHẢI Y HỆT Ở CẢ BỐN BẢN ════════════════════════════════════
 * Gói tải ở Khu vui chơi phải được Hà Nội nhận. `tach-ban-vung.sh` đổi mọi `vhcp` sang tiền tố
 * riêng — dấu mà bắt đầu bằng `vhcp` là bản vùng hoá thành chuỗi khác và chối gói. Cắn thật 23/09. */
foreach ( array( 'vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp' ) as $ban ) {
	$f = $goc . '/wordpress/' . $ban . '/includes/class-vhcp-cfg.php';
	if ( ! is_file( $f ) ) { continue; }
	$src = (string) file_get_contents( $f );
	teq( "🔴 $ban: dấu nhận dạng gói đúng và không bị đổi", 2, substr_count( $src, "'goi-cau-hinh-van-hanh-chi-phi'" ) );
	/* Màn nhập gói (`nbNhanGoi`) đã gỡ 24/09/2026 cùng thẻ 📦 — anh Thắng: *"nạp plugin riêng mà"*.
	   Chỉ còn phía máy chủ; không soi app.html nữa. */
}

/* ═══ 5. CỬA API ═══════════════════════════════════════════════════════════════════════ */
$api = (string) file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( '🔴 có tuyến `xuatGoiCauHinh` → `xuat_goi_cau_hinh`', 1 === preg_match( "/'xuatGoiCauHinh'\s*=>\s*array\(\s*'VHCP_Cfg',\s*'xuat_goi_cau_hinh'\s*\)/", $api ) );
t( '🔴 có tuyến `nhapGoiCauHinh` → `nhap_goi_cau_hinh`', 1 === preg_match( "/'nhapGoiCauHinh'\s*=>\s*array\(\s*'VHCP_Cfg',\s*'nhap_goi_cau_hinh'\s*\)/", $api ) );
$i_ad = strpos( $api, '$admin_only = array(' ); $j_ad = strpos( $api, ');', $i_ad );
t( '🔴 `nhapGoiCauHinh` nằm trong nhóm CHỈ ADMIN', false !== strpos( substr( $api, $i_ad, $j_ad - $i_ad ), "'nhapGoiCauHinh'" ) );
$i_ch = strpos( $api, '$cau_hinh   = array(' ); $j_ch = strpos( $api, ');', $i_ch );
t( '   `xuatGoiCauHinh` nằm trong nhóm Cấu hình (kế toán tải được)', false !== strpos( substr( $api, $i_ch, $j_ch - $i_ch ), "'xuatGoiCauHinh'" ) );
/* `required_roles()` là hàm riêng — soi qua Reflection, không mở nó ra public chỉ để kiểm. */
$rr = new ReflectionMethod( 'VHCP_API', 'required_roles' ); $rr->setAccessible( true );
teq( '🔴 cửa API: nhập → chỉ Admin', array( 'Admin' ), $rr->invoke( null, 'nhapGoiCauHinh' ) );
t( '   cửa API: xuất → kế toán cũng được', in_array( 'Kế toán cá nhân', $rr->invoke( null, 'xuatGoiCauHinh' ), true ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: gói chỉ chở danh mục, nhập chỉ Admin, ghi đúng bảng tích, bản kia nhận đủ.\n";
