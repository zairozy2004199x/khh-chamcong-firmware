<?php
/**
 * MỘT LỐI VIẾT GIỜ DUY NHẤT TRÊN CẢ MÀN — và nút đổi sang giờ:phút.
 *
 * =================================================================================================
 * 🔴 LỖI BÀI NÀY SINH RA ĐỂ CANH — VÀ NÓ ĐÃ LÀM MẤT TIỀN THẬT
 * =================================================================================================
 * Anh Thắng 19/09/2026 gửi hai ảnh đặt cạnh nhau:
 *   · tệp của anh ghi tổng `186:30`, ô lương nhân với `186,3`, ra 4.471.200;
 *   · hệ thống ghi `186,50`, ra 4.476.000.
 * Lệch đúng 4.800đ = 0,2 giờ × 24.000, trên MỘT người. `186:30` là 186 giờ 30 phút, tức 186,5 —
 * đổi giờ:phút sang thập phân là CHIA PHÚT CHO 60, không phải thay dấu hai chấm bằng dấu chấm.
 *
 * Rồi anh gửi tiếp ảnh ô `5.3` nằm cạnh `05:20` của cùng một ca 16:40 → 22:00. Ở đây lỗi là của
 * MÀN CHÚNG TA: ô ngày làm tròn MỘT chữ số trong khi cột tổng và bảng lương làm tròn HAI. Cùng
 * một màn, hai lối viết — và `5.3` thì đọc y như "5 giờ 3 phút".
 *
 * Bài này canh ba thứ:
 *   1. phép đổi giờ:phút ↔ thập phân ra đúng số (mấy con số thật anh đã gửi);
 *   2. ô ngày và cột tổng in CÙNG một lối, cùng số chữ số;
 *   3. nút đổi sang giờ:phút đổi cách IN mà KHÔNG đụng một phép tính nào.
 *
 * Chạy: php tools/test/kiem-viet-gio.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 400 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/* ================================================================== 1. giờ:phút là MẶC ĐỊNH */

echo "— mặc định là giờ:phút —\n";
VHCC_Cham::dat_kieu_gio( '' );
/* 🔴 Anh Thắng 19/09/2026, dứt khoát: *"mình quy ra tiếng là 5h20 phút chứ, 5,33 là sai rồi"*.
   Lưới bảng công là chỗ người ta ĐỌC giờ, không phải chỗ nhân tiền. */
t( '🔴 mặc định là giờ:phút', VHCC_Cham::la_hm() );
t( '   tham số lạ cũng về mặc định', ( VHCC_Cham::dat_kieu_gio( 'xyz' ) === null ) && VHCC_Cham::la_hm() );

VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_TP );
t( 'đổi sang thập phân được', ! VHCC_Cham::la_hm() );

/* 🔴 MẤY CON SỐ THẬT TRONG ẢNH ANH THẮNG GỬI. */
teq( '🔴 320 phút (16:40→22:00) = 5,33 — KHÔNG phải 5,3', '5,33', VHCC_Cham::gio_tp( 320 ) );
teq( '🔴 11190 phút = 186,50 — KHÔNG phải 186,30', '186,50', VHCC_Cham::gio_tp( 11190 ) );
teq( '   810 phút = 13,50', '13,50', VHCC_Cham::gio_tp( 810 ) );
teq( '   4770 phút = 79,50', '79,50', VHCC_Cham::gio_tp( 4770 ) );
teq( '   300 phút = 5,00', '5,00', VHCC_Cham::gio_tp( 300 ) );
teq( 'không có giờ thì gạch, không phải 0', '—', VHCC_Cham::gio_tp( null ) );

/* ⚠️ ĐÂY LÀ CHÍNH PHÉP NHÂN ĐÃ SAI 4.800đ. Giữ nó trong bài thử để con số ấy có một chỗ đứng
   mà không ai lặng lẽ sửa lại được. */
teq( '🔴 186,5 × 24.000 = 4.476.000 (số đúng)', 4476000.0, round( 11190 / 60 * 24000, 2 ) );
t( '   còn 186,3 × 24.000 thì thiếu 4.800đ', 4800.0 === round( 4476000 - 186.3 * 24000, 2 ) );

/* ================================================================== 2. lối giờ:phút */

echo "— lối giờ:phút —\n";
VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_HM );
t( 'quay lại giờ:phút được', VHCC_Cham::la_hm() );
teq( '🔴 320 phút = 5:20', '5:20', VHCC_Cham::gio_tp( 320 ) );
teq( '🔴 11190 phút = 186:30', '186:30', VHCC_Cham::gio_tp( 11190 ) );
teq( '   810 phút = 13:30', '13:30', VHCC_Cham::gio_tp( 810 ) );
/* ⚠️ PHÚT LUÔN HAI CHỮ SỐ. `5:2` đọc thành 5 giờ 2 phút hay 5 giờ 20 phút thì tuỳ người — mà
   đây đang là màn người ta ngồi đối chiếu từng ô. */
teq( '🔴 phút một chữ số vẫn in hai chữ số', '5:05', VHCC_Cham::gio_tp( 305 ) );
teq( '   tròn giờ thì :00', '8:00', VHCC_Cham::gio_tp( 480 ) );
teq( '   vẫn gạch khi không có giờ', '—', VHCC_Cham::gio_tp( null ) );

/* 🔴 ĐỔI CÁCH IN, KHÔNG ĐỔI PHÉP TÍNH. Nút này mà chạm được vào tiền thì nó là một cái bẫy chứ
   không phải một tiện ích — người xem đổi cách nhìn rồi bảng lương ra số khác. */
echo "— nút chỉ đổi cách IN —\n";
global $wpdb;
$U_KT = array( 'name' => 'Kế toán', 'role' => VHCC_Vai::KE_TOAN, 'ma_nv' => 'VGKT' );
VHCC_GiaGio::dat_coso( $U_KT, 'VG_SHOP', array( 'NV' => 24000 ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'VG_VY', 'ho_ten' => 'Bạn Thử',
	'cua_hang' => 'VG_SHOP', 'chuc_vu' => 'NV', 'vai_tro' => 'Nhân viên',
	'trang_thai_lam_viec' => 'Đang làm' ) );
/* 16:40 → 22:00, đúng ca trong ảnh. Mười ngày = 3200 phút = 53,33 giờ. */
for ( $i = 1; $i <= 10; $i++ ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'VG_VY', 'ho_ten' => '',
		'coso' => 'VG_SHOP', 'ngay' => sprintf( '2026-11-%02d', $i ),
		'gio_vao_giay' => 16 * 3600 + 40 * 60, 'gio_ra_giay' => 22 * 3600,
		'hau_to' => '', 'nguon' => 'may' ) );
}
VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_HM );
$b_hm = VHCC_BangLuong::dung( 'VG_SHOP', '2026-11' );
VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_TP );
$b_tp = VHCC_BangLuong::dung( 'VG_SHOP', '2026-11' );

teq( 'giờ tổng: 10 ca × 5h20 = 53,33', 53.33, $b_tp['dong'][0]['gio'] );
teq( '🔴 bật giờ:phút KHÔNG đổi số giờ', $b_tp['dong'][0]['gio'], $b_hm['dong'][0]['gio'] );
teq( '🔴 và KHÔNG đổi một đồng lương nào',
	$b_tp['dong'][0]['luongChinh'], $b_hm['dong'][0]['luongChinh'] );
/* ⚠️ TIỀN TÍNH TỪ PHÚT THẬT, không từ con số đã làm tròn để hiện lên màn. 3200 phút là
   53,3333… giờ; nhân đơn giá rồi mới làm tròn. */
teq( '   lương = 53,33 × 24.000', round( 53.33 * 24000, 2 ), $b_tp['dong'][0]['luongChinh'] );

/* ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 HAI LỐI VIẾT PHẢI NÓI VỀ ĐÚNG MỘT ĐẠI LƯỢNG — canh bằng PHÉP TÍNH, không so chuỗi
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Cột "Số giờ" của BẢNG LƯƠNG cố ý KHÔNG đi qua `gio_tp()`: đó là con số nhân thẳng với đơn giá,
 * viết `186:30` ở đó thì không ai nhân ra tiền được. Nên hai màn có thể viết khác kiểu — nhưng
 * không bao giờ được nói khác SỐ. Đây là chỗ phải canh, chứ không phải cái chuỗi.
 * ------------------------------------------------------------------------------------------- */
echo "— hai lối, một đại lượng —\n";
foreach ( array( 320, 810, 11190, 4770, 305, 480, 1, 59, 60 ) as $p_x ) {
	VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_HM );
	$hm = VHCC_Cham::gio_tp( $p_x );
	VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_TP );
	$tp = VHCC_Cham::gio_tp( $p_x );

	list( $h, $m ) = array_map( 'intval', explode( ':', $hm ) );
	$tu_hm = $h + $m / 60;
	$tu_tp = (float) str_replace( ',', '.', str_replace( '.', '', $tp ) );
	/* Lệch tối đa nửa đơn vị cuối của lối thập phân (0,005) — đó là phép làm tròn, không phải
	   hai con số khác nhau. Lớn hơn thế là một trong hai lối đang nói sai. */
	t( '🔴 ' . $p_x . ' phút: "' . $hm . '" và "' . $tp . '" cùng một đại lượng',
		abs( $tu_hm - $tu_tp ) < 0.0051, $hm . ' vs ' . $tp );
}

/* ═════════════════════════════════════════════════════════════════════════════════════════════
 * Ô "GIỜ TỰ TÍNH" VIẾT CÙNG LỐI VỚI LƯỚI — VÀ VẪN NÓI RA SỐ NHÂN
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 19/09/2026, ảnh ô tổng lưới `203:30` đặt cạnh ô này `203,50`: *"khác hệ, chỉnh lại
 * về 203.3"*. Cùng một màn, cùng một người, hai lối viết.
 *
 * ⚠️ Nhưng số NHÂN VỚI ĐƠN GIÁ là `203,50`. Bỏ hẳn nó đi thì người ta tự quy đổi lấy — và
 *    `203,30` là đúng cái đã làm tệp của anh thiếu tiền. Nên ô lớn theo lưới, dòng nhỏ dưới nó
 *    giữ số thập phân.
 * ------------------------------------------------------------------------------------------- */
echo "— ô giờ tự tính —\n";
$ma_js = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );

/* Ô lớn in bằng `gio_tp()`, không còn `number_format()` thẳng tay. */
t( '🔴 ô giờ tự tính in bằng gio_tp(), theo lối đang bật',
	false !== strpos( $ma_js, "esc_attr( VHCC_Cham::gio_tp( \$phut_chinh_ht ) )" ), 'không thấy' );
t( '   và vẫn giữ số thập phân bên cạnh làm SỐ NHÂN',
	false !== strpos( $ma_js, 'data-clthap' )
	&& false !== mb_strpos( $ma_js, '× đơn giá' ), 'không thấy' );

/* 🔴 PHÉP ĐỔI TRONG JAVASCRIPT PHẢI TÍNH TỪ PHÚT. `203.5` giờ là 203:30; cắt phần thập phân
   làm phút thì ra `203:05`, và đó là con số sẽ nằm cạnh ô tổng `203:30` của chính lưới ấy. */
t( '🔴 hàm đổi trong JS tính từ PHÚT, không cắt phần thập phân',
	false !== strpos( $ma_js, 'Math.round(Math.abs(n)*60)' ), 'không thấy' );

/* Đối chiếu chính con số trong ảnh: 203 giờ 30 phút. */
VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_HM );
teq( '🔴 12210 phút = 203:30 — đúng ô tổng trong ảnh', '203:30', VHCC_Cham::gio_tp( 12210 ) );
VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_TP );
teq( '   và cùng số ấy ở lối thập phân là 203,50', '203,50', VHCC_Cham::gio_tp( 12210 ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════════
 * CHÚ GIẢI Ô TỔNG VIẾT CẢ BA VẾ: GIỜ:PHÚT → SỐ GIỜ → NHÂN GIÁ → RA TIỀN
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 19/09/2026: *"Chuyển dạng cơ hệ giờ nhé, xong lấy giờ nhân giá tiền (lương đang tính
 * là 22k/h)"*.
 *
 * Giữa `70:12` và cục tiền cuối hàng có HAI bước người đọc phải nhẩm: đổi ra `70,20`, rồi nhân
 * `22.000`. Bước thứ nhất chính là chỗ đã sai thật — `186:30` bị đổi thành `186,3`, thiếu 4.800đ.
 * ------------------------------------------------------------------------------------------- */
echo "— chú giải viết cả phép nhân —\n";

$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'VG_SHOP', 'bo_phan' => 'Khu vui chơi' ) );
VHCC_GiaGio::dat_coso( $U_KT, 'VG_SHOP', array( 'Nhân Viên' => 22000, 'Lái Tàu' => 24000 ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'VG_HIEP', 'ho_ten' => 'Bạn Hai Việc',
	'cua_hang' => 'VG_SHOP', 'chuc_vu' => 'Nhân Viên', 'vai_tro' => 'Nhân viên',
	'trang_thai_lam_viec' => 'Đang làm' ) );
/* Mười ngày 8 giờ = 80 giờ chẵn; khai 9,5 giờ Lái Tàu, còn 70,5 giờ Nhân Viên. */
for ( $i = 1; $i <= 10; $i++ ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'ma_nv' => 'VG_HIEP', 'ho_ten' => '',
		'coso' => 'VG_SHOP', 'ngay' => sprintf( '2026-12-%02d', $i ),
		'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 16 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
}
VHCC_ChotLuong::dat( $U_KT, 'VG_SHOP', '2026-12', 'VG_HIEP',
	array( array( 'viec' => 'Lái Tàu', 'gio' => '9.5' ) ), '80', 'Nhân Viên' );

function vhcc_vg_chu( $kieu ) {
	$_GET = array( 'man' => 'cham', 'ccs' => 'VG_SHOP', 'cth' => '2026-12', 'cgh' => $kieu );
	$_POST = array();
	$_COOKIE = array( VHCC_Web::COOKIE => VHCC_Auth::phat_token( 'KT', 'Kế toán', 'VG_SHOP', 'VGKT' ) );
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_COOKIE = array();
	if ( ! preg_match( '/<td class="tong" title="([^"]*)"><b>([^<]*)<\/b>/u', $h, $m ) ) {
		return array( '', '', $h );
	}
	return array( html_entity_decode( $m[1], ENT_QUOTES ), html_entity_decode( $m[2], ENT_QUOTES ), $h );
}

list( $chu_hm, $o_hm ) = vhcc_vg_chu( VHCC_Cham::KIEU_HM );
teq( 'ô TỔNG ở lối giờ:phút', '80:00', $o_hm );
t( '🔴 chú giải đổi 70:30 ra 70,50 giờ',
	false !== mb_strpos( $chu_hm, '70:30 = 70,50h' ), $chu_hm );
t( '🔴 rồi nhân đơn giá 22.000 và ra tiền',
	false !== mb_strpos( $chu_hm, '× 22.000 = 1.551.000đ' ), $chu_hm );
t( '   dòng Lái Tàu cũng đủ ba vế',
	false !== mb_strpos( $chu_hm, '9:30 = 9,50h × 24.000 = 228.000đ' ), $chu_hm );

/* ⚠️ TIỀN TRONG CHÚ GIẢI LẤY TỪ `VHCC_BangLuong::dung()`, không nhân lại tại chỗ. Cộng hai dòng
   phải bằng đúng lương của người ấy — lệch là hai bộ luật cho cùng một câu hỏi. */
$bl_vg = VHCC_BangLuong::dung( 'VG_SHOP', '2026-12' );
$tong_vg = 0.0;
foreach ( $bl_vg['dong'] as $d_vg ) { $tong_vg += (float) $d_vg['luongChinh']; }
teq( '🔴 hai dòng trong chú giải cộng lại = lương thật', 1779000.0, round( $tong_vg, 2 ) );

/* Lối thập phân thì KHÔNG nhắc lại "= 70,50h" — con số ấy đã nằm ngay trước mắt. */
list( $chu_tp, $o_tp ) = vhcc_vg_chu( VHCC_Cham::KIEU_TP );
teq( 'ô TỔNG ở lối thập phân', '80,00', $o_tp );
t( 'lối thập phân vẫn có phép nhân',
	false !== mb_strpos( $chu_tp, '70,50 × 22.000 = 1.551.000đ' ), $chu_tp );
t( '   nhưng không nhắc lại "= …h" thừa', false === mb_strpos( $chu_tp, 'h ×' ), $chu_tp );

/* ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 Ô NHẬP GIỜ NHẬN CẢ `63:30` LẪN `63,5` — VÀ KHÔNG BAO GIỜ NUỐT MẤT PHÚT
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 19/09/2026, ảnh ô "Giờ ăn đơn giá khác" đang có `63:00`: *"chỗ này đáng lẽ là nhập
 * số giờ chứ, sao lại ,"*.
 *
 * Trước bản này máy đọc ô ấy bằng `(float)"63:30"` = **63**. Nửa giờ biến mất không một lời báo
 * — 0,5 × 24.000 = 12.000đ, và bảng vẫn đầy số nên không ai thấy. Đây là kiểu hỏng tệ nhất:
 * người gõ làm ĐÚNG (gõ theo lối cả màn đang viết) và bị trừ tiền vì thế.
 * ------------------------------------------------------------------------------------------- */
echo "— ô nhập giờ —\n";
foreach ( array(
	'63'     => 63.0,
	'63,5'   => 63.5,
	'63.5'   => 63.5,
	'63:30'  => 63.5,   // 🔴 chỗ đã nuốt mất nửa giờ
	'63h30'  => 63.5,
	'63:45'  => 63.75,
	'63:00'  => 63.0,
	'63h'    => 63.0,
	'0:45'   => 0.75,
	'0:06'   => 0.1,
) as $vao => $ra ) {
	teq( 'gõ "' . $vao . '"', $ra, VHCC_ChotLuong::doc_gio( $vao ) );
}
/* ⚠️ KHÔNG ĐỌC ĐƯỢC THÌ TRẢ null, ĐỪNG ĐOÁN. `(float)` của chuỗi lạ ra 0 hoặc ra phần đầu của
   nó — cả hai đều là một con số trông như thật, và nó đi thẳng vào lương. */
foreach ( array( '63:70', 'abc', '', '  ', '63:5:5', '-3' ) as $xau ) {
	teq( '🔴 "' . $xau . '" thì chối, không đoán', null, VHCC_ChotLuong::doc_gio( $xau ) );
}

/* Máy chủ chối nguyên lượt lưu và NÓI RA lối gõ đúng, chứ không lặng lẽ ghi 0. */
$r_xau = VHCC_ChotLuong::dat( $U_KT, 'VG_SHOP', '2026-12', 'VG_HIEP',
	array( array( 'viec' => 'Lái Tàu', 'gio' => '9:70' ) ), '80', 'Nhân Viên' );
t( '🔴 gõ 9:70 thì lượt lưu bị chối', empty( $r_xau['ok'] ), $r_xau );
t( '   và câu chối chỉ ra lối gõ đúng',
	false !== mb_strpos( $r_xau['error'], '63:30' ), $r_xau );

/* Gõ giờ:phút thì lưu ra ĐÚNG số giờ, và bảng lương nhân đúng số ấy. */
$r_ok = VHCC_ChotLuong::dat( $U_KT, 'VG_SHOP', '2026-12', 'VG_HIEP',
	array( array( 'viec' => 'Lái Tàu', 'gio' => '9:30' ) ), '80', 'Nhân Viên' );
t( 'gõ 9:30 thì lưu được', ! empty( $r_ok['ok'] ), $r_ok );
$bl_g = VHCC_BangLuong::dung( 'VG_SHOP', '2026-12' );
$gio_lt = null;
foreach ( $bl_g['dong'] as $d_g ) { if ( 'Lái Tàu' === $d_g['cv'] ) { $gio_lt = $d_g['gio']; } }
teq( '🔴 và bảng lương nhận đúng 9,5 giờ — không phải 9', 9.5, $gio_lt );

VHCC_Cham::dat_kieu_gio( VHCC_Cham::KIEU_HM );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — một lối viết giờ, và nút đổi không chạm vào tiền.\n";
