<?php
/**
 * KIỂM PHIẾU LƯƠNG CỦA TÔI — nhân viên tự xem lương tháng của CHÍNH MÌNH trên trạm.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY NẶNG HƠN MỌI BÀI TRẠM KHÁC
 * =================================================================================================
 * Mấy cửa trạm trước mở ra cho nhân viên một thứ vốn đã là của họ: lịch của họ, đơn của họ, lượt
 * chấm của họ. Cửa này khác hẳn — nó gọi thẳng vào `VHCC_BangLuong::dung()`, thứ trả về LƯƠNG,
 * SỐ CĂN CƯỚC và KHOẢN PHẠT CỦA CẢ CƠ SỞ, rồi lọc xuống một người. Một dòng lọc sai, hoặc một
 * khoá trả nhầm, là hai mươi người đọc được lương của nhau qua một cửa mà bậc quyền thấp nhất
 * trong hệ cũng gọi được.
 *
 * Nên bài này không kiểm "có ra số không". Nó kiểm bốn câu:
 *
 *   1. CHƯA CÔNG BỐ THÌ CÓ RÒ KHÔNG? Kế toán gõ khoản cộng/trừ rải suốt tháng; trong lúc ấy
 *      không ai được thấy gì.
 *   2. ĐÃ CÔNG BỐ RỒI THÌ CÓ RÒ CỦA NGƯỜI KHÁC KHÔNG? Kiểm bằng cách soi TOÀN BỘ chuỗi JSON
 *      trả về — tên, mã, căn cước, con số lương của người kia đều không được có mặt, kể cả
 *      nằm trong một khoá nào đó chưa ai nghĩ tới.
 *   3. AI CÔNG BỐ ĐƯỢC? Công bố = mở lương cho cả cơ sở đọc, nên phải ở bậc Kế toán, và chỉ
 *      trên cơ sở mình phụ trách.
 *   4. SỐ CHƯA ĐỦ THÌ CÓ BÀY TỔNG KHÔNG? Một dòng thiếu đơn giá mà vẫn cộng tổng là bày ra một
 *      con số thấp hơn thật, trông hoàn chỉnh — và người ta đi khiếu nại theo nó.
 *
 * Chạy: php tools/test/kiem-phieu-luong.php
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

$CS  = 'PL_SHOP';
$CS2 = 'PL_SHOP_KHAC';
$TH  = '2026-08';

/* ⚠️ HAI NGƯỜI CÙNG MỘT CƠ SỞ, LƯƠNG KHÁC HẲN NHAU. Một người là đủ để bài chạy xanh, nhưng
   khi chỉ có một dòng thì phép lọc nào cũng đúng — kể cả phép lọc không lọc gì. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'PL001', 'ho_ten' => 'Ngô Thị Xem', 'cua_hang' => $CS, 'chuc_vu' => 'Nhân viên',
	'pin_dang_nhap' => '931111', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'PL002', 'ho_ten' => 'Đỗ Văn Riêng', 'cua_hang' => $CS, 'chuc_vu' => 'Nhân viên',
	'cccd' => '000000000000', 'pin_dang_nhap' => '932222', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'PL003', 'ho_ten' => 'Bùi Thị Xa', 'cua_hang' => $CS2, 'chuc_vu' => 'Nhân viên',
	'pin_dang_nhap' => '933333', 'trang_thai_lam_viec' => 'Đang làm' ) );

$A = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '931111' )['token'] );
$B = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '932222' )['token'] );
$C = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '933333' )['token'] );
t( 'ba người đăng nhập được vào trạm', is_array( $A ) && is_array( $B ) && is_array( $C ), array( $A, $B ) );

$KT  = array( 'name' => 'Chị Kế Toán', 'role' => VHCC_Vai::KE_TOAN, 'coso' => $CS );
$CHT = array( 'name' => 'Trưởng',      'role' => VHCC_Vai::CHT,     'coso' => $CS );
$KT2 = array( 'name' => 'Kế toán kia', 'role' => VHCC_Vai::KE_TOAN, 'coso' => $CS2 );  // cơ sở kia

/* Giờ: A làm 10 giờ, B làm 20 giờ — hai con số khác hẳn nhau để không lẫn được. */
$gieo = function ( $ma, $cs, $ngay, $gio ) {
	global $wpdb;
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'ma_nv' => $ma, 'ho_ten' => '', 'coso' => $cs, 'ngay' => $ngay,
		'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 8 * 3600 + (int) ( $gio * 3600 ),
		'hau_to' => '', 'nguon' => 'may' ) );
};
$gieo( 'PL001', $CS,  $TH . '-01', 10 );
$gieo( 'PL002', $CS,  $TH . '-01', 20 );
$gieo( 'PL003', $CS2, $TH . '-01', 30 );

VHCC_GiaGio::dat_coso( $KT, $CS, array( 'Nhân viên' => 20000 ) );
VHCC_ChotLuong::dat_tien( $KT, $CS, $TH, 'PL002', array( 'setup' => 777777 ), array( 'phat' => 111111 ) );

/* =============================================================== 1. CHƯA CÔNG BỐ */

t( 'mặc định KHÔNG công bố tháng nào', ! VHCC_PhieuLuong::da_cong_bo( $CS, $TH ) );
t( 'và danh sách tháng của nhân viên rỗng', 0 === count( VHCC_PhieuLuong::ds_thang( $A ) ),
	VHCC_PhieuLuong::ds_thang( $A ) );

/* 🔴 GÕ THẲNG THÁNG CHƯA CÔNG BỐ CŨNG KHÔNG RA. Danh sách rỗng chỉ là màn hình; cửa mới là
   phép gác. Ai cũng gửi được một gói `{coSo, thang}` tự gõ. */
$r = VHCC_PhieuLuong::phieu( $A, $CS, $TH );
t( '🔴 chưa công bố thì cửa CHỐI, dù gõ thẳng tháng', empty( $r['ok'] ), $r );
t( 'và nói rõ là "chưa công bố" chứ không nói "không có dữ liệu"', ! empty( $r['chuaCongBo'] ), $r );
t( '🔴 câu chối KHÔNG mang theo con số lương nào',
	false === strpos( wp_json_encode( $r ), '400000' )
	&& false === strpos( wp_json_encode( $r ), '777777' ), $r );

/* =============================================================== 2. AI CÔNG BỐ ĐƯỢC */

$r = VHCC_PhieuLuong::cong_bo( $A, $CS, $TH, true );
t( '🔴 nhân viên thường KHÔNG tự công bố lương', empty( $r['ok'] ), $r );
$r = VHCC_PhieuLuong::cong_bo( $CHT, $CS, $TH, true );
t( '🔴 cửa hàng trưởng KHÔNG công bố được — công bố là mở lương cả cơ sở', empty( $r['ok'] ), $r );
t( 'và sau hai lượt bị chối thì vẫn CHƯA công bố', ! VHCC_PhieuLuong::da_cong_bo( $CS, $TH ) );

/* ⚠️ KẾ TOÁN KHÔNG BỊ BÓ MẢNG THÌ VỚI ĐƯỢC MỌI CƠ SỞ — đó là luật CÓ SẴN của hệ
   (`co_quyen_coso` -> `cong_tat_ca` -> `qua_bo_mang`), không phải chỗ hở của cửa này, và phép
   bó theo mảng có bài riêng (`kiem-mang-bo-phan.php`). Điều bài NÀY phải canh là cửa công bố
   có THẬT SỰ đi qua phép gác ấy hay không — chứ không phải tự chế một phép gác thứ hai, vì
   hai phép gác rồi sẽ lệch nhau. */
$src_cb = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-phieu-luong.php' );
$i_cb = strpos( $src_cb, 'function cong_bo' );
t( '🔴 cong_bo() gác bằng co_quyen_coso, không tự chế phép gác riêng',
	false !== $i_cb && false !== strpos( substr( $src_cb, $i_cb, 700 ), 'co_quyen_coso' ) );
t( 'và gác bằng đúng bậc quyền của bảng lương',
	false !== strpos( substr( $src_cb, $i_cb, 700 ), 'self::QUYEN' ) );
t( 'bậc ấy là bậc Kế toán', VHCC_Vai::KE_TOAN === VHCC_Vai::QUYEN[ VHCC_PhieuLuong::QUYEN ] );

$r = VHCC_PhieuLuong::cong_bo( $KT, $CS, $TH, true );
t( 'kế toán của cơ sở công bố được', ! empty( $r['ok'] ), $r );
t( 'sổ ghi nhận đã công bố', VHCC_PhieuLuong::da_cong_bo( $CS, $TH ) );
t( '🔴 công bố cơ sở này KHÔNG kéo theo cơ sở kia', ! VHCC_PhieuLuong::da_cong_bo( $CS2, $TH ) );
t( 'công bố tháng này KHÔNG kéo theo tháng khác', ! VHCC_PhieuLuong::da_cong_bo( $CS, '2026-07' ) );
t( 'tra tên cơ sở không phân biệt hoa thường / tiền tố CS_',
	VHCC_PhieuLuong::da_cong_bo( 'CS_' . strtolower( $CS ), $TH ) );

$r = VHCC_PhieuLuong::cong_bo( $KT, $CS, 'tháng tám', true );
t( 'tháng sai khuôn bị chối', empty( $r['ok'] ), $r );

/* =============================================================== 3. PHIẾU CỦA CHÍNH MÌNH */

$pa = VHCC_PhieuLuong::phieu( $A, $CS, $TH );
t( 'công bố rồi thì nhân viên đọc được phiếu', ! empty( $pa['ok'] ), $pa );
t( 'phiếu đúng tên người đang đăng nhập', 'Ngô Thị Xem' === $pa['hoTen'], $pa );
t( 'số giờ đúng của người ấy', 10.0 === (float) $pa['gio'], $pa );
t( 'lương chính = 10 giờ × 20.000', 200000.0 === (float) $pa['luongChinh'], $pa );
t( 'A không có khoản cộng nào', 0.0 === (float) $pa['tongCong'], $pa );
t( 'tổng của A đúng bằng lương chính', 200000.0 === (float) $pa['tong'], $pa );

/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI. `dung()` trả cả cơ sở; soi TOÀN BỘ chuỗi trả về chứ không
   soi từng khoá — khoá nào chưa ai nghĩ tới thì phép soi từng khoá không với tới. */
$chuoi = wp_json_encode( $pa );
foreach ( array( 'PL002', 'Đỗ Văn Riêng', '000000000000', '400000', '777777', '111111' ) as $cam ) {
	t( '🔴 phiếu của A KHÔNG chứa "' . $cam . '" của người khác',
		false === strpos( $chuoi, $cam ), $cam );
}
t( '🔴 phiếu chỉ có dòng của chính mình', 1 === count( $pa['dong'] ), $pa['dong'] );
foreach ( $pa['dong'] as $d ) {
	t( '🔴 mọi dòng đều mang mã của người đang xem', 'PL001' === (string) $d['ma'], $d );
	t( 'số căn cước bị gỡ khỏi phiếu', ! isset( $d['cccd'] ), $d );
	t( 'số thứ tự trong bảng cơ sở bị gỡ khỏi phiếu', ! isset( $d['stt'] ), $d );
}
t( '🔴 phiếu KHÔNG mang theo tổng của cả cơ sở',
	! isset( $pa['tong']['nguoi'] ) && ! is_array( $pa['tong'] ), $pa['tong'] );

/* B có khoản cộng và khoản trừ — của riêng B, và chỉ B thấy. */
$pb = VHCC_PhieuLuong::phieu( $B, $CS, $TH );
t( 'B đọc được phiếu của B', ! empty( $pb['ok'] ), $pb );
t( 'lương chính của B = 20 giờ × 20.000', 400000.0 === (float) $pb['luongChinh'], $pb );
t( 'khoản cộng của B vào đúng phiếu B', 777777.0 === (float) $pb['tongCong'], $pb );
t( 'khoản trừ của B vào đúng phiếu B', 111111.0 === (float) $pb['tongTru'], $pb );
t( 'tổng của B = chính + cộng − trừ',
	( 400000.0 + 777777.0 - 111111.0 ) === (float) $pb['tong'], $pb );
t( '🔴 phiếu của B không lẫn tên A',
	false === strpos( wp_json_encode( $pb ), 'Ngô Thị Xem' ), $pb );

/* 🔴 MÃ NV LẤY TỪ PHIÊN. Không có đường nào truyền mã người khác vào — `phieu()` cố ý không
   nhận tham số mã, và đây là phép canh lại điều đó bằng chữ ký hàm. */
$rf = new ReflectionMethod( 'VHCC_PhieuLuong', 'phieu' );
$ten_ts = array();
foreach ( $rf->getParameters() as $p ) { $ten_ts[] = $p->getName(); }
t( '🔴 phieu() KHÔNG có tham số mã nhân viên nào',
	! in_array( 'ma_nv', $ten_ts, true ) && ! in_array( 'ma', $ten_ts, true ), $ten_ts );
t( 'phieu() nhận đúng ba thứ: phiên, cơ sở, tháng',
	array( 'u', 'coso', 'thang' ) === $ten_ts, $ten_ts );

/* =============================================================== 4. CƠ SỞ KHÔNG PHẢI CỦA MÌNH */

VHCC_PhieuLuong::cong_bo( $KT2, $CS2, $TH, true );
t( 'cơ sở kia cũng đã công bố', VHCC_PhieuLuong::da_cong_bo( $CS2, $TH ) );

/* 🔴 CÔNG BỐ KHÔNG PHẢI CÔNG KHAI. Tháng của cơ sở kia đã mở cho NGƯỜI CỦA CƠ SỞ ẤY, không mở
   cho cả công ty. Thiếu phép gác này thì một nhân viên gõ tên cơ sở khác là đọc được lương
   của cơ sở ấy — và tên cơ sở thì ai cũng biết. */
$r = VHCC_PhieuLuong::phieu( $A, $CS2, $TH );
t( '🔴 A KHÔNG đọc được phiếu ở cơ sở mình không thuộc về', empty( $r['ok'] ), $r );
t( 'và câu chối không mang theo dữ liệu nào của cơ sở ấy',
	false === strpos( wp_json_encode( $r ), 'Bùi Thị Xa' ), $r );
$ds_a = VHCC_PhieuLuong::ds_thang( $A );
foreach ( $ds_a as $x ) {
	t( '🔴 danh sách tháng của A không có cơ sở lạ', $CS2 !== $x['coSo'], $x );
}
t( 'A thấy đúng một tháng của đúng cơ sở mình', 1 === count( $ds_a ), $ds_a );

/* Người của cơ sở kia thì đọc được của mình — phép gác không được chặt quá hoá chặn nhầm. */
$pc = VHCC_PhieuLuong::phieu( $C, $CS2, $TH );
t( 'người của cơ sở kia đọc được phiếu của chính họ', ! empty( $pc['ok'] ), $pc );

/* =============================================================== 5. THU LẠI */

$r = VHCC_PhieuLuong::cong_bo( $KT, $CS, $TH, false );
t( 'kế toán thu lại được phiếu đã công bố', ! empty( $r['ok'] ), $r );
t( 'sổ hết ghi nhận công bố', ! VHCC_PhieuLuong::da_cong_bo( $CS, $TH ) );
$r = VHCC_PhieuLuong::phieu( $A, $CS, $TH );
t( '🔴 thu lại rồi thì cửa chối ngay, không còn đọc được', empty( $r['ok'] ), $r );
t( '🔴 thu lại cơ sở này KHÔNG thu luôn cơ sở kia', VHCC_PhieuLuong::da_cong_bo( $CS2, $TH ) );
VHCC_PhieuLuong::cong_bo( $KT, $CS, $TH, true );

/* =============================================================== 6. THÁNG KHÔNG CÓ DÒNG NÀO */

VHCC_PhieuLuong::cong_bo( $KT, $CS, '2026-07', true );
$r = VHCC_PhieuLuong::phieu( $A, $CS, '2026-07' );
t( 'tháng không có giờ nào thì vẫn trả ok, kèm cờ trống', ! empty( $r['ok'] ) && ! empty( $r['trong'] ), $r );
t( 'và nói rõ là hệ không ghi nhận giờ nào, không để một ô trống câm',
	isset( $r['loi'] ) && false !== mb_strpos( $r['loi'], 'không ghi nhận giờ nào' ), $r );
t( 'tháng trống KHÔNG bày tổng nào', empty( $r['tong'] ), $r );

/* =============================================================== 7. CHƯA KHAI ĐƠN GIÁ */

/* 🔴 Xem câu 4 ở đầu tệp. Bỏ hẳn sổ đơn giá của cơ sở đi, dòng của A mất giá — lúc ấy tổng
   phải là NULL chứ không phải một con số cộng từ mấy dòng còn lại. */
VHCC_GiaGio::dat_coso( $KT, $CS, array() );
$r = VHCC_PhieuLuong::phieu( $A, $CS, $TH );
t( 'vẫn đọc được phiếu khi thiếu đơn giá', ! empty( $r['ok'] ), $r );
t( '🔴 thiếu đơn giá thì KHÔNG bày tổng (null, không phải 0)', null === $r['tong'], $r );
t( '🔴 và KHÔNG bày lương chính', null === $r['luongChinh'], $r );
t( 'nói rõ còn bao nhiêu dòng thiếu giá', 1 === (int) $r['thieuGia'], $r );
t( 'cờ daDu tắt để màn khỏi phải tự đoán', empty( $r['daDu'] ), $r );
VHCC_GiaGio::dat_coso( $KT, $CS, array( 'Nhân viên' => 20000 ) );
$r = VHCC_PhieuLuong::phieu( $A, $CS, $TH );
t( 'khai lại giá thì tổng hiện lại', 200000.0 === (float) $r['tong'], $r );
t( 'và cờ daDu bật', ! empty( $r['daDu'] ), $r );

/* =============================================================== 8. LƯỢT THIẾU MỘT ĐẦU GIỜ */

/* Quên chấm ra thì `dung()` KHÔNG cộng phút nào mà ĐẾM — phiếu phải chuyển tiếp con số ấy,
   không thì người đọc thấy giờ hụt mà không có chỗ nào giải thích vì sao. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'PL001', 'ho_ten' => '', 'coso' => $CS, 'ngay' => $TH . '-05',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => null, 'hau_to' => '', 'nguon' => 'may' ) );
$r = VHCC_PhieuLuong::phieu( $A, $CS, $TH );
t( '🔴 phiếu ĐẾM và nói ra lượt thiếu một đầu giờ', 1 === (int) $r['thieuGio'], $r );
t( 'và không cộng phút nào cho lượt ấy', 10.0 === (float) $r['gio'], $r );

/* =============================================================== 9. LỚP NÀY KHÔNG GHI LƯƠNG */

/* 🔴 ĐỌC, KHÔNG SỬA. Canh bằng MÃ NGUỒN: hành vi hôm nay đúng không ngăn được người mai sau
   thêm một đường ghi vào đây, và đường ghi ấy sẽ là đường nhân viên tự sửa lương của mình. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-phieu-luong.php' );
foreach ( array( '$wpdb', 'dat_tien', 'dat_thang', 'VHCC_GiaGio::dat', 'VHCC_DB::t(' ) as $cam ) {
	t( '🔴 phiếu lương KHÔNG có đường ghi qua ' . $cam, false === strpos( $src, $cam ), $cam );
}
/* Ô cài đặt duy nhất nó ghi là SỔ CÔNG BỐ của chính nó — không phải số liệu lương. */
preg_match_all( "/dat_cai_dat\(\s*([A-Za-z_:]+)/", $src, $m );
t( 'chỉ ghi đúng một ô cài đặt', 1 === count( array_unique( $m[1] ) ), $m[1] );
t( 'và ô ấy là sổ công bố của chính nó', 'self::O' === $m[1][0], $m[1] );

/* =============================================================== 10. CỬA TRẠM VÀ MÀN HÌNH */

$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
$i = strpos( $tram, "'phieu' === \$viec" );
t( 'trạm có cửa phieu', false !== $i );
$khoi = substr( $tram, $i, 400 );
t( '🔴 cửa trạm KHÔNG chuyển tiếp ma_nv từ biểu mẫu', false === strpos( $khoi, 'ma_nv' ), $khoi );
t( 'cửa trạm có danh sách tháng', false !== strpos( $tram, "'phieuluong' === \$viec" ) );

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$than = strstr( $tpl, '<style' ) ? substr( $tpl, 0, strpos( $tpl, '<style' ) )
	. substr( $tpl, strpos( $tpl, '</style>' ) ) : $tpl;
/* 🔴 PHIẾU LƯƠNG NAY LÀ MỘT MÀN RIÊNG (`#mPhieu`), KHÔNG CÒN LÀ MỘT KHỐI (`#oKhoiPhieu`)
   nằm giữa tab Tôi — cùng lượt với Thêm nhân sự và Gửi đơn đi trễ (anh Thắng 17/09/2026:
   việc thỉnh thoảng mới làm thì đừng nằm giữa một trang cuộn dài).
   ⚠️ Và màn ấy có HAI phần: "Của tôi" (chỉ tháng ĐÃ công bố) và "Cả cửa hàng" (mọi tháng,
      chỉ hiện khi máy chủ gửi danh sách cơ sở). Kê cả ô của phần thứ hai, kẻo nó rụng mất mà
      không phép nào kêu. */
foreach ( array( 'mPhieu', 'plPhanToi', 'plThang', 'plNhanO', 'bangPhieu',
	'plPhanCs', 'bangPhieuCs' ) as $o ) {
	t( 'màn trạm có ô ' . $o, false !== strpos( $than, $o ), $o );
}
t( '🔴 và KHÔNG còn bản khối cũ nằm lẫn trong tab Tôi',
	false === strpos( $than, 'oKhoiPhieu' ), null );
/* 🔴 CHƯA CÔNG BỐ THÌ KHỐI VẪN HIỆN, CHỈ ẨN Ô XỔ — sửa 17/09/2026.
   Bản 4.32.0 ẩn hẳn cả khối. Anh Thắng là người đầu tiên mở nó và câu đầu tiên là *"chưa
   thấy"*: một khối vô hình không phân biệt được với một khối hỏng, và người dùng không có
   cách nào đoán ra rằng mình đang chờ kế toán bấm một cái nút bên trang quản trị. */
t( '🔴 KHÔNG còn ẩn cả khối khi chưa có tháng nào',
	false === strpos( $tpl, "classList.toggle('an', !ds.length)" ), $tpl );
/* 🔴 CHỖ ẨN PHẢI LÀ MỘT, VÀ NÓ CHỈ ẨN Ô XỔ + NHÃN CỦA Ô ẤY.
   Anh Thắng 18/09/2026 gửi ảnh màn Phiếu lương: chữ "Tháng" đứng chơ vơ trên khoảng trắng rồi
   mới tới câu "chưa được công bố" — trông như ô chọn hỏng chứ không như "chưa có gì để chọn".
   Nhãn của một ô đã ẩn thì phải ẩn theo. Hai lượt `classList.add('an')` rải hai nơi là sớm
   muộn một nơi quên nhãn, nên gom về đúng MỘT hàm. */
t( '🔴 chỉ có ĐÚNG MỘT chỗ ẩn ô xổ', 1 === substr_count( $tpl, 'function phieuHien(' ), null );
t( 'và nó ẩn CẢ NHÃN, không để chữ "Tháng" đứng chơ vơ',
	1 === preg_match( "/function phieuHien\(\)\{[^}]*plThang'\)\.classList\.add\('an'\)"
		. "[^}]*plNhanO'\)\.classList\.add\('an'\)/s", $tpl ), null );
/* 🔴 VÀ CÓ THÁNG THÌ PHẢI BỎ LỚP ẨN KHỎI CẢ HAI — thiếu một vế là ô xổ hiện mà không có nhãn,
   hoặc nhãn hiện mà không có ô. */
t( '🔴 có tháng thì bỏ lớp ẩn khỏi cả ô xổ lẫn nhãn',
	false !== strpos( $tpl, "el('plThang').classList.remove('an')" )
	&& false !== strpos( $tpl, "el('plNhanO').classList.remove('an')" ), null );
/* Và chưa công bố thì NÓI RA đang chờ ai làm gì — một khối câm không phân biệt được với một
   khối hỏng, và người dùng không có cách nào đoán ra mình đang chờ kế toán bấm một cái nút. */
t( '🔴 chưa công bố thì nói rõ đang chờ kế toán chốt, không để trống',
	false !== strpos( $tpl, 'chưa được công bố' )
	&& false !== strpos( $tpl, 'anh/chị không phải làm gì cả' ), null );
t( 'và nói ra đang chờ ai làm gì, không để một ô câm',
	false !== strpos( $tpl, 'chưa được công bố' )
	&& false !== strpos( $tpl, 'anh/chị không phải làm gì cả' ), $tpl );
/* Ba lối ra: chưa công bố · cửa trả lỗi · mạng hỏng. Cả ba đều phải hiện khối, không thì
   đúng cái lỗi vừa sửa quay lại qua một nhánh khác. */
t( '🔴 cả ba lối ra đều gọi phieuHien()', 3 === substr_count( $tpl, 'phieuHien();' ), $tpl );
/* Ô xổ rỗng thì ẩn RIÊNG nó — bày một ô xổ không có lựa chọn nào còn khó hiểu hơn không bày. */
t( 'ô xổ khởi đầu đã mang lớp ẩn',
	false !== strpos( $than, '<select id="plThang" class="an">' ), $than );
t( 'có tháng thì ô xổ hiện lại',
	false !== strpos( $tpl, "el('plThang').classList.remove('an')" ), $tpl );
/* 🔴 Câu "đây không phải số chuyển khoản" phải có mặt — xem chốt 3 đầu class. */
t( '🔴 màn nói rõ BHXH và giờ thêm nằm ngoài hệ',
	false !== strpos( $tpl, 'BHXH' ) && false !== strpos( $tpl, 'số chuyển khoản có thể khác' ), $tpl );
t( 'thiếu đơn giá thì màn nói ra thay vì bày tổng', false !== strpos( $tpl, 'if(!j.daDu)' ), $tpl );
/* Giá trị ô xổ gói CẢ cơ sở lẫn tháng — người làm hai nơi có hai phiếu khác nhau cùng một tháng. */
t( 'ô chọn mang theo cả cơ sở lẫn tháng',
	false !== strpos( $tpl, "ds[i].coSo + '|' + ds[i].thang" ), $tpl );

$web = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
foreach ( array( 'phieu_cb', 'phieu_thu' ) as $v ) {
	t( 'trang quản trị có cửa ' . $v, false !== strpos( $web, "'" . $v . "'" ), $v );
}
t( 'nút công bố nằm trong khối bảng lương cơ sở',
	strpos( $web, 'VHCC_PhieuLuong::da_cong_bo' ) > strpos( $web, 'function the_bang_luong_cs' ), $web );

/* =============================================================== dọn */

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv IN ('PL001','PL002','PL003')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('PL001','PL002','PL003')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cai_dat' ) . " WHERE khoa='" . VHCC_PhieuLuong::O . "'" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — chưa công bố thì không ai thấy gì, công bố rồi thì mỗi người chỉ thấy"
	. " ĐÚNG DÒNG CỦA MÌNH.\n";
