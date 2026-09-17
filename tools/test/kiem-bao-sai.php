<?php
/**
 * KIỂM "BÁO LƯỢT CHẤM SAI" — nhân viên tự gắn cờ lên một ngày của chính mình.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CỬA NÀY MỞ BẢNG `ghi_chu` RA CHO BẬC QUYỀN THẤP NHẤT TRONG HỆ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * `VHCC_Cham::luu_ghi_chu` — cửa cũ — mở đầu bằng `co_quyen_coso()`, tức chỉ người phụ trách cơ
 * sở mới gắn cờ được. Cửa mới cho NHÂN VIÊN THƯỜNG vào cùng cái bảng ấy, nên nó phải hẹp hơn
 * hẳn, và bài này canh đúng chỗ hẹp:
 *
 *   · gắn được cờ lên ngày của NGƯỜI KHÁC không?
 *   · gắn được vào CƠ SỞ MÌNH KHÔNG LÀM không?
 *   · có sửa được GIỜ CÔNG không? (không được — cờ nằm cạnh, không đụng vào giờ)
 *   · đọc được cờ của người khác không?
 *
 * Chạy: php tools/test/kiem-bao-sai.php
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

$CS_A = 'BS_SHOP_A';
$CS_B = 'BS_SHOP_B';

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'BS001', 'ho_ten' => 'Người Báo', 'cua_hang' => $CS_A,
	'pin_dang_nhap' => '330011', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'BS002', 'ho_ten' => 'Người Khác', 'cua_hang' => $CS_B,
	'pin_dang_nhap' => '330022', 'trang_thai_lam_viec' => 'Đang làm' ) );

$A = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '330011' )['token'] );
$B = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '330022' )['token'] );
t( 'hai người đăng nhập được', is_array( $A ) && is_array( $B ) && 'BS001' === $A['ma_nv'], array( $A, $B ) );

$hom_nay = current_time( 'Y-m-d' );
$hom_qua = gmdate( 'Y-m-d', strtotime( $hom_nay . ' -1 day' ) );

/* ═══════════════════════════════════════════════════════════════════ 1. BÁO ĐƯỢC */

$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_A,
	'lyDo' => 'Chấm nhầm sang cơ sở khác' ) );
t( 'nhân viên thường báo được', ! empty( $r['ok'] ), $r );
t( 'cờ vào đúng cơ sở của người báo', $CS_A === (string) $r['coSo'], $r );

$hang = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' )
	. ' WHERE flag_id=%s', $r['flagId'] ), ARRAY_A );
t( 'cờ mang mã NV của người báo', 'BS001' === (string) $hang['ma_nv'], $hang );
/* 🔴 TRẠNG THÁI RIÊNG, không dùng chung "Cần kiểm" của quản lý. Cờ quản lý là "tôi thấy ngày
   này lạ"; cờ này là "người trong cuộc nói nó sai". Gộp làm một thì tới lúc lọc không tách
   được cái nào đáng hỏi lại người ta. */
t( '🔴 mang trạng thái riêng "' . VHCC_Cham::NV_BAO . '"',
	VHCC_Cham::NV_BAO === (string) $hang['trang_thai'], $hang );
t( 'người gắn ghi rõ là tự báo', false !== mb_strpos( (string) $hang['nguoi_gan'], '(tự báo)' ), $hang );

/* ═══════════════════════════════════════════════════════════ 2. KHÔNG ĐỤNG VÀO GIỜ */

/* 🔴 CỜ NẰM CẠNH, KHÔNG SỬA CÔNG. Đây là toàn bộ thiết kế của cửa này: cho nhân viên sửa giờ
   của chính mình là bỏ luôn ý nghĩa của việc chấm công. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='BS001'" );
VHCC_Nhan::ghi_gio( $CS_A, $hom_qua, 'BS001', 'Người Báo', 8 * 3600, '', 'online', '' );
$truoc = $wpdb->get_row( 'SELECT gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='BS001' AND ngay='$hom_qua'", ARRAY_A );
VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_A, 'lyDo' => 'giờ vào sai' ) );
$sau = $wpdb->get_row( 'SELECT gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='BS001' AND ngay='$hom_qua'", ARRAY_A );
t( '🔴 báo sai KHÔNG đụng vào giờ công', $truoc === $sau, array( $truoc, $sau ) );

/* ═══════════════════════════════════════════════════════ 3. KHÔNG BÁO HỘ NGƯỜI KHÁC */

/* Mã NV lấy từ phiên; khai `ma_nv` trong biểu mẫu không đẻ ra cờ cho người khác. */
VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_A,
	'lyDo' => 'thử báo hộ', 'ma_nv' => 'BS002' ) );
$cua_b = VHCC_Cham::nv_bao_cua( 'BS002', 20 );
t( '🔴 khai ma_nv trong biểu mẫu KHÔNG đẻ cờ cho người khác', 0 === count( $cua_b ), $cua_b );

/* 🔴 CƠ SỞ MÌNH KHÔNG LÀM -> rơi về cơ sở thật của mình, không gắn sang cơ sở kia. */
$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_B, 'lyDo' => 'thử cơ sở lạ' ) );
t( '🔴 khai cơ sở mình không làm -> KHÔNG gắn vào cơ sở ấy',
	! empty( $r['ok'] ) && $CS_B !== (string) $r['coSo'], $r );
t( '   mà rơi về cơ sở thật của mình', $CS_A === (string) $r['coSo'], $r );

/* Và B không đọc được cờ của A. */
$cua_a = VHCC_Cham::nv_bao_cua( 'BS001', 20 );
t( 'A đọc được cờ của A', count( $cua_a ) > 0, $cua_a );
t( '🔴 B không đọc được cờ của A', 0 === count( VHCC_Cham::nv_bao_cua( 'BS002', 20 ) ) );

/* ═══════════════════════════════════════════════════════════ 4. MỘT NGÀY MỘT CỜ */

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'ghi_chu' ) . " WHERE ma_nv='BS001'" );
VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_A, 'lyDo' => 'lần một' ) );
$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_A, 'lyDo' => 'lần hai' ) );
t( 'báo lại báo rõ là đè lên lượt cũ', ! empty( $r['lai'] ), $r );
$so = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' )
	. " WHERE ma_nv='BS001' AND ngay='$hom_qua'" );
t( '🔴 báo hai lần vẫn chỉ MỘT cờ', 1 === $so, $so );
$noi = (string) $wpdb->get_var( 'SELECT ghi_chu FROM ' . VHCC_DB::t( 'ghi_chu' )
	. " WHERE ma_nv='BS001' AND ngay='$hom_qua'" );
t( 'và giữ nội dung mới nhất', 'lần hai' === $noi, $noi );

/* ═══════════════════════════════════════════════════════════════ 5. BIÊN NGÀY */

$mai = gmdate( 'Y-m-d', strtotime( $hom_nay . ' +1 day' ) );
$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $mai, 'coso' => $CS_A, 'lyDo' => 'x' ) );
t( '🔴 chối ngày ở TƯƠNG LAI (chưa có lượt nào để báo)', empty( $r['ok'] ), $r );

$xua = gmdate( 'Y-m-d', strtotime( $hom_nay . ' -' . ( VHCC_Cham::BAO_MUON_TOI_DA + 5 ) . ' days' ) );
$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $xua, 'coso' => $CS_A, 'lyDo' => 'x' ) );
t( '🔴 chối ngày quá ' . VHCC_Cham::BAO_MUON_TOI_DA . ' ngày', empty( $r['ok'] ), $r );
t( '   và chỉ sang kế toán', ! empty( $r['error'] ) && false !== mb_strpos( $r['error'], 'kế toán' ), $r );

$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => $hom_qua, 'coso' => $CS_A, 'lyDo' => '' ) );
t( '🔴 chối lượt báo không nói sai chỗ nào', empty( $r['ok'] ), $r );
$r = VHCC_Cham::nv_bao_sai( $A, array( 'ngay' => 'hôm kia', 'coso' => $CS_A, 'lyDo' => 'x' ) );
t( 'chối ngày sai khuôn', empty( $r['ok'] ), $r );

/* Người chưa có Mã NV thì chưa báo được — cùng luật với mọi cửa khác của trạm. */
$r = VHCC_Cham::nv_bao_sai( array( 'ma_nv' => '', 'ho_ten' => 'X', 'coso' => $CS_A ),
	array( 'ngay' => $hom_qua, 'coso' => $CS_A, 'lyDo' => 'x' ) );
t( 'chưa bật chấm công online -> chối', empty( $r['ok'] ), $r );

/* ═══════════════════════════════════════════════════════════════ 6. CỬA & MÀN */

$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( 'cửa baosai có thật', false !== strpos( $src, "'baosai' === \$viec" ) );
t( 'cửa dabao có thật', false !== strpos( $src, "'dabao' === \$viec" ) );
$chot = strpos( $src, "'het_phien'" );
t( '🔴 cả hai cửa nằm SAU chốt thẻ phiên', false !== $chot
	&& strpos( $src, "'baosai' === \$viec" ) > $chot && strpos( $src, "'dabao' === \$viec" ) > $chot );
t( 'cửa chuyển thẳng xuống VHCC_Cham::nv_bao_sai',
	false !== strpos( $src, 'VHCC_Cham::nv_bao_sai( $u,' ) );

/* 🔴 CỬA CŨ KHÔNG BỊ NỚI. `luu_ghi_chu` phải GIỮ NGUYÊN phép gác `co_quyen_coso` — nới nó ra
   để lọt nhân viên vào là cùng lúc cho họ gắn cờ lên ngày của bất kỳ ai trong cơ sở. */
$src_cham = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-cham.php' );
$i = strpos( $src_cham, 'function luu_ghi_chu' );
$khoi = substr( $src_cham, $i, 900 );
t( '🔴 cửa CŨ luu_ghi_chu vẫn gác bằng co_quyen_coso',
	false !== strpos( $khoi, 'co_quyen_coso' ), $khoi );

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( 'có ô báo sai trên trạm', false !== strpos( $tpl, 'id="btBaoSai"' ) );
t( 'có bảng đã báo', false !== strpos( $tpl, 'id="bangDaBao"' ) );
/* Ngày mặc định từ máy chủ, và KHÔNG cho chọn ngày mai. */
t( '🔴 ngày mặc định lấy từ máy chủ', false !== strpos( $tpl, "el('bsNgay').value = j.homNay" ) );
t( '🔴 ô ngày chặn luôn ngày mai ở giao diện', false !== strpos( $tpl, "el('bsNgay').max = j.homNay" ) );
/* Câu báo phải nói rõ GIỜ CHƯA ĐỔI — không thì người ta tưởng báo xong là xong, rồi không đi hỏi. */
t( '🔴 báo xong nói rõ giờ công CHƯA đổi',
	false !== mb_strpos( $tpl, 'Giờ công CHƯA đổi' ), '' );
/* Bảng Hôm nay chỉ đường tới ô báo, và dặn đừng chấm lại. */
t( 'bảng Hôm nay chỉ đường tới ô báo', false !== mb_strpos( $tpl, 'Báo lượt chấm sai' ) );
t( '   và dặn đừng chấm lại', false !== mb_strpos( $tpl, 'Đừng chấm lại' ) );

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'ghi_chu' ) . " WHERE ma_nv IN ('BS001','BS002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv IN ('BS001','BS002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('BS001','BS002')" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — nhân viên báo được, nhưng không sửa được giờ và không báo hộ ai.\n";
