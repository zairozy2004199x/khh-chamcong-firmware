<?php
/**
 * KIỂM ĐƠN XIN NGHỈ — nhân viên nộp trên trạm, cửa hàng trưởng duyệt.
 *
 * =================================================================================================
 * 🔴 BỐN CÂU BÀI NÀY PHẢI TRẢ LỜI BẰNG PHÉP THỬ
 * =================================================================================================
 * 1. Nộp được đơn ĐỨNG TÊN NGƯỜI KHÁC không? Đơn nghỉ được duyệt là câu trả lời chính thức cho
 *    *"hôm ấy người này vắng có phép"*. Nộp hộ được nghĩa là hợp thức hoá được buổi vắng của
 *    người khác — mà người bị hợp thức hoá thì không bao giờ biết.
 *
 * 2. Duyệt được đơn của CƠ SỞ KHÁC không? Id đơn là số, gõ tay được. Cửa hàng trưởng phải bị
 *    chặn ở chính cái cơ sở GHI TRONG ĐƠN, không phải cơ sở gửi lên từ biểu mẫu.
 *
 * 3. Đơn được duyệt có ĐỘNG VÀO CÔNG không? Luật đã chốt trong `class-vhcc-xin-nghi.php`: *"đơn
 *    được duyệt không sinh ra công, và không trừ công"*. Một cửa cấp công không qua chấm công
 *    nào là cửa không có ai gác — nên bài kiểm canh cả mã nguồn, không chỉ canh hành vi.
 *
 * 4. Số "phép còn lại" có đếm đúng thứ đếm được không? Nó phải cộng từ ĐƠN ĐÃ DUYỆT, LOẠI `phep`,
 *    CỦA CHÍNH NGƯỜI ẤY, TRONG NĂM ẤY. Lỏng một trong bốn là bày ra một con số sai mà trông như
 *    thật — và người ta lập kế hoạch nghỉ theo nó.
 *
 * Chạy: php tools/test/kiem-xin-nghi.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

global $wpdb;

$CS_A = 'XN_SHOP_A';
$CS_B = 'XN_SHOP_B';

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'XN001', 'ho_ten' => 'Lê Văn Nghỉ', 'cua_hang' => $CS_A,
	'pin_dang_nhap' => '911111', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'XN002', 'ho_ten' => 'Phạm Thị Bên', 'cua_hang' => $CS_B,
	'pin_dang_nhap' => '922222', 'trang_thai_lam_viec' => 'Đang làm' ) );

$A = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '911111' )['token'] );
$B = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '922222' )['token'] );
t( 'hai người đăng nhập được vào trạm', is_array( $A ) && is_array( $B )
	&& 'XN001' === $A['ma_nv'] && 'XN002' === $B['ma_nv'], array( $A, $B ) );

/** Người duyệt: cửa hàng trưởng của A, và cửa hàng trưởng của B. */
$CHT_A = array( 'name' => 'Trưởng A', 'role' => VHCC_Vai::CHT, 'coso' => $CS_A );
$CHT_B = array( 'name' => 'Trưởng B', 'role' => VHCC_Vai::CHT, 'coso' => $CS_B );
$ADMIN = array( 'name' => 'Sếp',     'role' => VHCC_Vai::ADMIN, 'coso' => '' );

$hom_nay = current_time( 'Y-m-d' );
function ngay_cach( $n ) {
	return gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' 00:00:00 UTC' ) + $n * 86400 );
}

/* =============================================================== 1. NỘP ĐƠN */

$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 3 ), 'loai' => VHCC_XinNghi::PHEP,
	'lyDo' => 'Về quê giỗ' ) );
t( 'nhân viên thường nộp được đơn nghỉ', ! empty( $r['ok'] ), $r );
t( 'để trống ô "đến" thì nghỉ ĐÚNG MỘT NGÀY', isset( $r['den'] ) && $r['den'] === ngay_cach( 3 ), $r );
t( 'một ngày thì soNgay = 1', isset( $r['soNgay'] ) && 1 === (int) $r['soNgay'], $r );
t( 'đơn gửi về đúng cơ sở của người nộp', isset( $r['coSo'] ) && $CS_A === $r['coSo'], $r );
t( 'đơn cho ngày mai KHÔNG bị đánh dấu nộp muộn', empty( $r['muon'] ), $r );
$id1 = (int) $r['id'];

t( 'khoảng ba ngày đếm cả hai đầu', 3 === VHCC_XinNghi::dem_ngay( '2026-03-10', '2026-03-12' ) );
t( 'khoảng ngược đầu đếm ra 0', 0 === VHCC_XinNghi::dem_ngay( '2026-03-12', '2026-03-10' ) );

/* 🔴 MÃ NV LẤY TỪ THẺ PHIÊN, KHÔNG TỪ BIỂU MẪU. Chốt nặng nhất của bài — xem đầu tệp, câu 1. */
VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 20 ), 'loai' => VHCC_XinNghi::OM,
	'lyDo' => 'thử nộp hộ', 'ma_nv' => 'XN002' ) );
t( '🔴 khai ma_nv trong biểu mẫu KHÔNG đẻ ra đơn cho người khác',
	0 === count( VHCC_XinNghi::cua_nguoi( 'XN002', 50 ) ), VHCC_XinNghi::cua_nguoi( 'XN002', 50 ) );
t( 'lượt ấy vẫn ghi vào chính người nộp', 2 === count( VHCC_XinNghi::cua_nguoi( 'XN001', 50 ) ) );

/* Cũng không nhận `coso` từ biểu mẫu — cơ sở đọc từ HỒ SƠ, nếu không thì một người bên A nộp
   được đơn vào cửa hàng B và trưởng B thấy một cái tên lạ xin nghỉ. */
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 25 ), 'loai' => VHCC_XinNghi::VIEC,
	'lyDo' => 'thử đổi cơ sở', 'coso' => $CS_B, 'cua_hang' => $CS_B ) );
t( '🔴 khai coso trong biểu mẫu KHÔNG đổi được cơ sở duyệt',
	! empty( $r['ok'] ) && $CS_A === $r['coSo'], $r );

/* Những cửa chối — mỗi cái chối vì một lý do khác nhau, và câu chối phải nói ra lý do ấy. */
$r = VHCC_XinNghi::nop( $A, array( 'tu' => '', 'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'x' ) );
t( 'chối đơn không có ngày bắt đầu', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => '10/03/2026', 'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'x' ) );
t( 'chối ngày sai khuôn dd/mm/yyyy', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 5 ), 'den' => ngay_cach( 2 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'x' ) );
t( 'chối ngày kết thúc sớm hơn ngày bắt đầu', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 5 ), 'loai' => 'nghi_bay',
	'lyDo' => 'x' ) );
t( '🔴 chối loại nghỉ lạ — danh sách trắng, không phải ô chữ tự do', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 5 ), 'loai' => VHCC_XinNghi::PHEP,
	'lyDo' => '   ' ) );
t( 'chối đơn không có lý do', empty( $r['ok'] ), $r );

/* Ba cái trần: xin trước quá xa, nộp muộn quá xa, và một đơn quá dài. */
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( VHCC_XinNghi::TRUOC_TOI_DA + 1 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'xin sớm quá' ) );
t( 'chối xin trước quá ' . VHCC_XinNghi::TRUOC_TOI_DA . ' ngày', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( -( VHCC_XinNghi::MUON_TOI_DA + 1 ) ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'nộp muộn quá' ) );
t( 'chối nộp muộn quá ' . VHCC_XinNghi::MUON_TOI_DA . ' ngày', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 100 ),
	'den' => ngay_cach( 100 + VHCC_XinNghi::DAI_TOI_DA ),
	'loai' => VHCC_XinNghi::KHONG_L, 'lyDo' => 'dài quá' ) );
t( 'chối một đơn dài hơn ' . VHCC_XinNghi::DAI_TOI_DA . ' ngày', empty( $r['ok'] ), $r );

/* ⚠️ NỘP MUỘN THÌ VẪN NHẬN, CHỈ ĐÁNH DẤU. Chối thẳng đơn của hôm qua là ép người ta đi xin
   chữ ký tay, và buổi vắng ấy không bao giờ có lời giải thích trong hệ. */
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( -2 ), 'loai' => VHCC_XinNghi::OM,
	'lyDo' => 'Sốt, có toa thuốc' ) );
t( 'đơn nộp muộn vài ngày VẪN NHẬN', ! empty( $r['ok'] ), $r );
t( 'và được đánh dấu muộn để người duyệt thấy', ! empty( $r['muon'] ), $r );

/* =============================================================== 2. CHỒNG KHOẢNG */

$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 40 ), 'den' => ngay_cach( 42 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'Đi Đà Lạt' ) );
t( 'nộp được khoảng 40→42', ! empty( $r['ok'] ), $r );
$id_dl = (int) $r['id'];

/* 🔴 CHỐNG CHỒNG KHOẢNG, KHÔNG PHẢI CHỐNG TRÙNG KHÍT. Khoá duy nhất chỉ bắt được hai đơn y hệt
   nhau; cái hay xảy ra thật là 40–42 rồi 41–44. */
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 41 ), 'den' => ngay_cach( 44 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'đổi ý' ) );
t( '🔴 chối đơn CHỒNG MỘT PHẦN lên đơn cũ', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 41 ), 'den' => ngay_cach( 41 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'nằm lọt bên trong' ) );
t( 'chối đơn NẰM LỌT trong khoảng cũ', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 39 ), 'den' => ngay_cach( 45 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'trùm cả khoảng cũ' ) );
t( 'chối đơn TRÙM lên khoảng cũ', empty( $r['ok'] ), $r );

/* Sát cạnh mà không đè lên nhau thì phải nhận — nghỉ 40–42 rồi nghỉ tiếp 43 là chuyện thật. */
$r = VHCC_XinNghi::nop( $A, array( 'tu' => ngay_cach( 43 ), 'den' => ngay_cach( 43 ),
	'loai' => VHCC_XinNghi::VIEC, 'lyDo' => 'liền kề, không chồng' ) );
t( 'nhận đơn SÁT CẠNH mà không chồng', ! empty( $r['ok'] ), $r );
$id_ke = (int) $r['id'];

/* ⚠️ ĐƠN BỊ TỪ CHỐI KHÔNG CÒN GIỮ CHỖ. Giữ chỗ thì một lần bị từ chối là khoá luôn khoảng ngày
   ấy, và người xin không có cách nào xin lại cho đúng khoảng mình cần. */
$khac = VHCC_XinNghi::nop( $B, array( 'tu' => ngay_cach( 50 ), 'den' => ngay_cach( 51 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'đơn của B' ) );
VHCC_XinNghi::duyet( $CHT_B, (int) $khac['id'], VHCC_XinNghi::TU_CHOI, 'kẹt người' );
$r = VHCC_XinNghi::nop( $B, array( 'tu' => ngay_cach( 50 ), 'den' => ngay_cach( 51 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'xin lại đúng khoảng cũ' ) );
t( '⚠️ đơn ĐÃ BỊ TỪ CHỐI không giữ chỗ — xin lại được', ! empty( $r['ok'] ), $r );

/* Chồng khoảng chỉ tính TRONG CÙNG MỘT NGƯỜI. Hai người nghỉ cùng ngày là chuyện bình thường;
   quyết ai được nghỉ là việc của cửa hàng trưởng, không phải việc của bảng dữ liệu. */
$r = VHCC_XinNghi::nop( $B, array( 'tu' => ngay_cach( 40 ), 'den' => ngay_cach( 42 ),
	'loai' => VHCC_XinNghi::PHEP, 'lyDo' => 'B nghỉ trùng ngày với A' ) );
t( 'người khác nghỉ trùng ngày thì KHÔNG bị chặn', ! empty( $r['ok'] ), $r );

/* =============================================================== 3. AI ĐỌC ĐƯỢC ĐƠN CỦA AI */

foreach ( VHCC_XinNghi::cua_nguoi( 'XN001', 50 ) as $x ) {
	t( '🔴 danh sách của A không lẫn đơn của người khác', 'XN001' === (string) $x['ma_nv'], $x );
}
t( 'mã rỗng thì không trả về đơn nào', 0 === count( VHCC_XinNghi::cua_nguoi( '', 50 ) ) );

$cho_a = VHCC_XinNghi::cho_duyet( $CS_A, 100 );
t( 'hộp chờ duyệt của A có đơn', count( $cho_a ) > 0, $cho_a );
foreach ( $cho_a as $x ) {
	t( '🔴 hộp chờ duyệt của A chỉ có đơn cơ sở A', 0 === strcasecmp( $CS_A, (string) $x['coso'] ), $x );
	t( 'hộp chờ duyệt chỉ có đơn ĐANG CHỜ', VHCC_XinNghi::CHO === (string) $x['trang_thai'], $x );
}
t( 'tra hộp chờ duyệt không phân biệt hoa thường',
	count( VHCC_XinNghi::cho_duyet( strtolower( $CS_A ) ) ) === count( $cho_a ) );
t( 'cơ sở rỗng thì hộp chờ duyệt rỗng', 0 === count( VHCC_XinNghi::cho_duyet( '' ) ) );

/* =============================================================== 4. DUYỆT */

/* 🔴 CHỐT CƠ SỞ ĐỌC TỪ CHÍNH ĐƠN. Id đơn là số, gõ tay được — xem đầu tệp, câu 2. */
$r = VHCC_XinNghi::duyet( $CHT_B, $id_dl, VHCC_XinNghi::DUYET );
t( '🔴 cửa hàng trưởng B KHÔNG duyệt được đơn của cơ sở A', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::duyet( $A, $id_dl, VHCC_XinNghi::DUYET );
t( '🔴 nhân viên thường KHÔNG tự duyệt đơn của chính mình', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::duyet( $CHT_A, 999999, VHCC_XinNghi::DUYET );
t( 'duyệt id không có thì báo không thấy đơn', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::duyet( $CHT_A, $id_dl, 'xoa_luon' );
t( '🔴 quyết định lạ bị chối — chỉ có duyệt hoặc không duyệt', empty( $r['ok'] ), $r );

$r = VHCC_XinNghi::duyet( $CHT_A, $id_dl, VHCC_XinNghi::DUYET );
t( 'cửa hàng trưởng A duyệt được đơn của cơ sở mình', ! empty( $r['ok'] ), $r );
$d = $wpdb->get_row( $wpdb->prepare(
	'SELECT * FROM ' . VHCC_DB::t( 'xin_nghi' ) . ' WHERE id=%d', $id_dl ), ARRAY_A );
t( 'đơn chuyển sang ĐÃ DUYỆT', VHCC_XinNghi::DUYET === (string) $d['trang_thai'], $d );
t( 'có ghi tên người duyệt', 'Trưởng A' === (string) $d['nguoi_duyet'], $d );

$r = VHCC_XinNghi::duyet( $CHT_A, $id_ke, VHCC_XinNghi::TU_CHOI, 'Hôm ấy chỉ còn một người đứng quầy' );
t( 'từ chối được, kèm lý do', ! empty( $r['ok'] ), $r );
$d = $wpdb->get_row( $wpdb->prepare(
	'SELECT * FROM ' . VHCC_DB::t( 'xin_nghi' ) . ' WHERE id=%d', $id_ke ), ARRAY_A );
t( 'lý do từ chối được lưu lại cho người xin đọc',
	false !== strpos( (string) $d['ly_do_choi'], 'một người đứng quầy' ), $d );
t( 'duyệt thì KHÔNG mang theo lý do từ chối cũ',
	'' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ly_do_choi FROM '
		. VHCC_DB::t( 'xin_nghi' ) . ' WHERE id=%d', $id_dl ) ) );

/* Admin duyệt được mọi cơ sở — bậc trên làm được việc của bậc dưới, đúng thang vai trò. */
$r = VHCC_XinNghi::duyet( $ADMIN, $id1, VHCC_XinNghi::DUYET );
t( 'Admin duyệt được đơn của bất kỳ cơ sở nào', ! empty( $r['ok'] ), $r );

/* =============================================================== 5. ĐƠN KHÔNG ĐỘNG VÀO CÔNG */

/* 🔴 Xem đầu tệp, câu 3. Canh bằng MÃ NGUỒN chứ không chỉ bằng hành vi: hành vi hôm nay đúng
   không ngăn được người mai sau thêm một dòng cộng công vào `duyet()`. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-xin-nghi.php' );
foreach ( array( 'cham_cong', 'ghi_gio', 'VHCC_Nhan::', 'VHCC_Cham::', 'VHCC_Online' ) as $cam ) {
	t( '🔴 đơn xin nghỉ KHÔNG đụng vào ' . $cam, false === strpos( $src, $cam ), $cam );
}
preg_match_all( "/VHCC_DB::t\(\s*'([a-z_]+)'/", $src, $m );
t( 'có đụng tới bảng, nếu không thì phép thử dưới vô nghĩa', count( $m[1] ) > 0, $m );
foreach ( array_unique( $m[1] ) as $b ) {
	t( '🔴 chỉ ghi vào đúng bảng xin_nghi của mình', 'xin_nghi' === $b, $b );
}

/* =============================================================== 6. QUỸ PHÉP NĂM */

$nam = current_time( 'Y' );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_nghi' ) . " WHERE ma_nv='XN001'" );

function xn_gieo( $ma, $cs, $tu, $den, $loai, $tt ) {
	global $wpdb;
	$wpdb->insert( VHCC_DB::t( 'xin_nghi' ), array(
		'coso' => $cs, 'ma_nv' => $ma, 'ho_ten' => 'x', 'tu_ngay' => $tu, 'den_ngay' => $den,
		'so_ngay' => VHCC_XinNghi::dem_ngay( $tu, $den ), 'loai' => $loai,
		'ly_do' => 'gieo', 'trang_thai' => $tt, 'tao_luc' => current_time( 'mysql' ) ) );
	return (int) $wpdb->insert_id;
}

xn_gieo( 'XN001', $CS_A, $nam . '-02-10', $nam . '-02-12', VHCC_XinNghi::PHEP, VHCC_XinNghi::DUYET );
t( 'phép đã dùng cộng đúng số ngày đơn đã duyệt',
	3.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam ), VHCC_XinNghi::phep_da_dung( 'XN001', $nam ) );

/* ⚠️ CHỈ ĐẾM ĐƠN ĐÃ DUYỆT. Đếm cả đơn đang chờ thì số "còn lại" tụt xuống ngay lúc nộp rồi
   nhảy lại lên khi đơn bị từ chối — người nhìn không hiểu vì sao số của mình đổi. */
xn_gieo( 'XN001', $CS_A, $nam . '-03-01', $nam . '-03-05', VHCC_XinNghi::PHEP, VHCC_XinNghi::CHO );
xn_gieo( 'XN001', $CS_A, $nam . '-03-10', $nam . '-03-14', VHCC_XinNghi::PHEP, VHCC_XinNghi::TU_CHOI );
t( '🔴 đơn đang CHỜ không bị trừ vào quỹ phép', 3.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam ) );
t( '🔴 đơn BỊ TỪ CHỐI không bị trừ vào quỹ phép', 3.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam ) );

/* Chỉ loại `phep` mới trừ quỹ. Nghỉ ốm mà trừ phép năm là lấy mất ngày nghỉ của người đang ốm. */
xn_gieo( 'XN001', $CS_A, $nam . '-04-01', $nam . '-04-07', VHCC_XinNghi::OM, VHCC_XinNghi::DUYET );
xn_gieo( 'XN001', $CS_A, $nam . '-04-10', $nam . '-04-20', VHCC_XinNghi::KHONG_L, VHCC_XinNghi::DUYET );
xn_gieo( 'XN001', $CS_A, $nam . '-04-25', $nam . '-04-26', VHCC_XinNghi::VIEC, VHCC_XinNghi::DUYET );
t( '🔴 nghỉ ốm / không lương / việc riêng KHÔNG trừ quỹ phép năm',
	3.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam ), VHCC_XinNghi::phep_da_dung( 'XN001', $nam ) );

/* Năm khác là quỹ khác. */
$nam_truoc = (string) ( (int) $nam - 1 );
xn_gieo( 'XN001', $CS_A, $nam_truoc . '-05-01', $nam_truoc . '-05-09', VHCC_XinNghi::PHEP, VHCC_XinNghi::DUYET );
t( '🔴 phép năm ngoái không cộng vào năm nay', 3.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam ) );
t( 'và năm ngoái vẫn tra lại được', 9.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam_truoc ) );

/* Người khác là quỹ khác. */
xn_gieo( 'XN002', $CS_B, $nam . '-02-01', $nam . '-02-28', VHCC_XinNghi::PHEP, VHCC_XinNghi::DUYET );
t( '🔴 phép của người khác không cộng vào quỹ của mình',
	3.0 === VHCC_XinNghi::phep_da_dung( 'XN001', $nam ), VHCC_XinNghi::phep_da_dung( 'XN001', $nam ) );
t( 'mã rỗng thì quỹ bằng 0', 0.0 === VHCC_XinNghi::phep_da_dung( '', $nam ) );
t( 'năm sai khuôn thì quỹ bằng 0', 0.0 === VHCC_XinNghi::phep_da_dung( 'XN001', 'năm ngoái' ) );

/* =============================================================== 7. TRẦN PHÉP NĂM */

VHCC_XinNghi::dat_phep_nam( $ADMIN, 12 );
$q = VHCC_XinNghi::quy_phep( 'XN001' );
t( 'quỹ phép báo đúng trần', 12 === (int) $q['tran'], $q );
t( 'quỹ phép báo đúng số đã dùng', 3.0 === (float) $q['daDung'], $q );
t( 'quỹ phép báo đúng số còn lại', 9.0 === (float) $q['conLai'], $q );

/* ⚠️ DÙNG QUÁ TRẦN THÌ CÒN LẠI LÀ 0, KHÔNG PHẢI SỐ ÂM. Số âm trên màn nhân viên không nói thêm
   được gì mà chỉ làm người ta hoảng; ai cần biết vượt bao nhiêu thì đọc bảng đơn. */
VHCC_XinNghi::dat_phep_nam( $ADMIN, 2 );
$q = VHCC_XinNghi::quy_phep( 'XN001' );
t( '⚠️ dùng quá trần thì còn lại là 0, không âm', 0.0 === (float) $q['conLai'], $q );

/* 🔴 TRẦN 0 = KHÔNG THEO DÕI, VÀ LÚC ẤY KHÔNG BÀY SỐ. Bày "còn lại 0" cho công ty chưa đặt
   chính sách là nói với cả cửa hàng rằng họ hết phép — một câu sai mà nghe rất dứt khoát. */
VHCC_XinNghi::dat_phep_nam( $ADMIN, 0 );
$q = VHCC_XinNghi::quy_phep( 'XN001' );
t( '🔴 trần 0 thì conLai là null, KHÔNG phải số 0', null === $q['conLai'], $q );
t( 'trần 0 vẫn báo số đã dùng để còn đối chiếu', 3.0 === (float) $q['daDung'], $q );

/* Cửa đặt trần: chỉ người ngoài cơ sở (Kế toán trở lên) mới đặt, và chỉ nhận 0..365. */
$r = VHCC_XinNghi::dat_phep_nam( $CHT_A, 30 );
t( '🔴 cửa hàng trưởng KHÔNG đặt được chính sách phép của cả công ty', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::dat_phep_nam( $A, 30 );
t( '🔴 nhân viên KHÔNG đặt được chính sách phép', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::dat_phep_nam( $ADMIN, -1 );
t( 'chối số ngày phép âm', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::dat_phep_nam( $ADMIN, 366 );
t( 'chối số ngày phép quá 365', empty( $r['ok'] ), $r );
$r = VHCC_XinNghi::dat_phep_nam( $ADMIN, 14 );
t( 'Kế toán/Admin đặt được trần phép', ! empty( $r['ok'] ) && 14 === VHCC_XinNghi::phep_nam(), $r );

/* =============================================================== 8. CỬA TRẠM VÀ MÀN HÌNH */

$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
$i = strpos( $tram, "'xinnghi' === \$viec" );
t( 'trạm có cửa xinnghi', false !== $i );
$khoi = substr( $tram, $i, 600 );
t( '🔴 cửa trạm KHÔNG chuyển tiếp ma_nv từ biểu mẫu', false === strpos( $khoi, 'ma_nv' ), $khoi );
t( '🔴 cửa trạm KHÔNG chuyển tiếp coso từ biểu mẫu', false === strpos( $khoi, 'coSo' ), $khoi );

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$than = strstr( $tpl, '<style' ) ? substr( $tpl, 0, strpos( $tpl, '<style' ) )
	. substr( $tpl, strpos( $tpl, '</style>' ) ) : $tpl;
foreach ( array( 'xnTu', 'xnDen', 'xnLoai', 'xnLyDo', 'btGuiNghi', 'oQuyPhep', 'loiNghi' ) as $o ) {
	t( 'màn trạm có ô ' . $o, false !== strpos( $than, $o ), $o );
}

/* 🔴 SỬA 17/09/2026 — BIỂU MẪU RA KHỎI TAB "TÔI". Anh Thắng khoanh đúng khối này: *"Chuyển này
   thành 1 tính năng"*. Nay nó là màn riêng mở từ ô trong lưới Ứng dụng. */
t( '🔴 biểu mẫu xin nghỉ là màn riêng',
	false !== strpos( $than, '<div id="mXinNghi" class="mn an">' ), $than );
$i_toi_het = strpos( $than, '<!-- /tToi -->' );
t( 'và KHÔNG còn nằm trong tab "Tôi"',
	strpos( $than, 'id="mXinNghi"' ) > $i_toi_het,
	array( strpos( $than, 'id="mXinNghi"' ), $i_toi_het ) );
/* ⚠️ Ô QUỸ PHÉP ĐỨNG TRÊN BIỂU MẪU. Người mở màn này ra là để quyết "xin mấy ngày" — con số
   còn lại phải đọc được TRƯỚC khi họ gõ, không phải sau. */
t( '⚠️ ô quỹ phép đứng TRÊN ô nhập ngày',
	strpos( $than, 'id="oQuyPhep"' ) < strpos( $than, 'id="xnTu"' ), $than );
/* Người nộp xong phải biết đi đâu xem kết quả — không thì họ nộp lại vì tưởng hụt. */
t( 'màn chỉ đường sang tab Tôi để xem kết quả',
	false !== strpos( $than, 'Đơn đã nộp và kết quả duyệt xem ở' ), $than );
t( 'nút gửi đơn nghỉ gọi đúng cửa xinnghi', false !== strpos( $tpl, "guiDon('xinnghi'" ) );

/* 🔴 NGÀY MẶC ĐỊNH LẤY TỪ MÁY CHỦ. Lấy `new Date()` của điện thoại thì máy lệch múi giờ là đơn
   rơi vào một ngày khác với ngày người ta thấy trên màn. Cùng luật với đơn đi trễ. */
t( '🔴 ngày mặc định lấy từ máy chủ (j.homNay)',
	false !== strpos( $tpl, "el('xnTu').value = j.homNay" ) );

/* Danh sách loại nghỉ dựng TỪ MÁY CHỦ (`j.loaiNghi`). Chép tay vào HTML là hai danh sách, và
   ngày nào thêm một loại thì màn gửi lên một mã mà `nop()` chối vì không có trong bảng trắng. */
t( '🔴 danh sách loại nghỉ dựng từ j.loaiNghi của máy chủ',
	false !== strpos( $tpl, 'j.loaiNghi' ), $tpl );
t( 'ô chọn loại nghỉ không chép cứng lựa chọn nào trong HTML',
	false !== strpos( $than, '<select id="xnLoai"></select>' ), $than );

/* Màn phải nói thẳng câu "công KHÔNG đổi vì đơn này" — người nộp đơn nghỉ hay hiểu là đơn
   được duyệt thì ngày ấy vẫn có công. */
t( '🔴 màn nói rõ đơn KHÔNG làm đổi công',
	false !== strpos( $tpl, 'Công KHÔNG đổi vì đơn này' ), $tpl );

/* Trần 0 thì màn không bày số — canh luôn ở nhánh JS, vì luật này sống ở hai nơi. */
t( 'JS không bày "còn lại" khi công ty chưa đặt trần',
	false !== strpos( $tpl, 'if(!q.tran)' ), $tpl );

/* =============================================================== 9. KHỐI DUYỆT CỦA QUẢN TRỊ */

$web = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
t( 'trang quản trị có khối duyệt đơn nghỉ', false !== strpos( $web, 'the_don_nghi' ) );
t( 'khối duyệt nằm cạnh khối lệnh đi trễ',
	strpos( $web, 'self::the_don_nghi(' ) > strpos( $web, 'self::the_lenh_tre(' ) );
foreach ( array( 'duyet_nghi', 'choi_nghi', 'phep_nam' ) as $v ) {
	t( 'có cửa xử lý ' . $v, false !== strpos( $web, "'" . $v . "'" ), $v );
}

/* =============================================================== dọn */

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_nghi' ) . " WHERE ma_nv IN ('XN001','XN002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('XN001','XN002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cai_dat' ) . " WHERE khoa='" . VHCC_XinNghi::O_PHEP . "'" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đơn nghỉ chỉ nộp được cho CHÍNH MÌNH, chỉ duyệt được trong CƠ SỞ MÌNH,"
	. " và KHÔNG động một dòng nào vào công.\n";
