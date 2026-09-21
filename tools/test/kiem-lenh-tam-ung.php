<?php
/**
 * KIỂM LỆNH TẠM ỨNG (anh Thắng 07/09/2026).
 *
 * =============================================================================================
 * 🔴 ĐẦU BÀI, NGUYÊN VĂN
 * =============================================================================================
 * *"khi quản lý duyệt 1 lần đơn tạm ứng, sẽ tạo 1 lệnh tạm ứng phía dưới cuối trang (tạm ứng
 * bao nhiêu, mấy cơ sở tạm ứng, cơ sở nào tạm ứng)"*
 *
 * =============================================================================================
 * 🔴 VÌ SAO KHÔNG ĐỌC NGƯỢC TỪ BẢNG ĐƠN ĐƯỢC
 * =============================================================================================
 * Bảng đơn trả lời "đơn nào đang ở khâu nào". Tờ lệnh trả lời câu khác hẳn: "LƯỢT DUYỆT lúc 9h
 * sáng gồm bao nhiêu tiền, chia cho những cơ sở nào" — thứ người cầm tiền đi phát cần cầm.
 *
 * Cái đó KHÔNG dựng lại được từ bảng đơn: chín đơn quản lý tích rồi bấm một cái trông y hệt
 * chín đơn duyệt rải rác cả ngày. `ngay_duyet` chỉ tới phút, mà hai lượt bấm cách nhau vài
 * giây thì vẫn chung một phút — gom theo mốc thời gian là gom nhầm.
 *
 * =============================================================================================
 * ⚠️ HAI CHỖ DỄ SAI NHẤT, BÀI NÀY CANH NẶNG
 * =============================================================================================
 *   1. MỘT LƯỢT BẤM = MỘT TỜ LỆNH. Đặt lời ghi lệnh vào `duyet_tam_ung()` thì lô N đơn đẻ N tờ
 *      — đúng thứ tính năng này đi tránh. Ghi lệnh phải nằm ở TẦNG GỌI.
 *   2. TỔNG TRÊN LỆNH = TỔNG ĐÃ DUYỆT. Cộng lại các cơ sở phải ra đúng con số ấy, kể cả khi
 *      trong lô có đơn CHƯA GÁN CƠ SỞ (ảnh anh Thắng có đúng một hàng như thế: 600.000đ, ô Cơ
 *      sở trống). Bỏ rơi nó là lệnh thiếu tiền ở chỗ không ai nhìn ra.
 *
 * Chạy: php tools/test/kiem-lenh-tam-ung.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

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
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-07 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
global $wpdb;

$CS_A = 'FUNZONE VŨNG TÀU';
$CS_B = 'TÀU TÂN PHÚ';
$CS_C = 'FARM PHAN THIẾT';
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => $CS_A, 'maDonVi' => 'FZ_VT', 'phanLoaiLon' => 'FUNZONE' ),
	array( 'ten' => $CS_B, 'maDonVi' => 'TAU_TP', 'phanLoaiLon' => 'TAU' ),
	array( 'ten' => $CS_C, 'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM' ),
) ) );

$KY = 'T9/2026 (7/9-13/9/2026)';

/**
 * Dựng một đơn đã sẵn sàng duyệt: có hạng mục, có số tạm ứng, đã gửi xin.
 * `$coso` để rỗng = đơn CHƯA GÁN CƠ SỞ (ca có thật trong ảnh anh Thắng gửi).
 */
function don_cho_duyet( $ky, $coso, $tien, $nguoi = 'Trần Ngọc Minh Truyền' ) {
	$d = VHCP_Don::create_don( $ky, $nguoi );
	$m = $d['maDon'];
	if ( $coso !== '' ) {
		VHCP_Don::add_line( $m, array( 'coso' => $coso, 'ngay' => '2026-09-08',
			'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Vật tư',
			'soLuong' => 1, 'donGia' => $tien, 'thanhTien' => $tien ) );
		VHCP_Don::set_tam_ung( $m, $coso, $tien );
	} else {
		/* ĐƠN XIN ỨNG TRƯỚC, chưa liệt kê hạng mục — luật 01/09/2026 cho gửi khi *"1 là có hạng
		   mục, 2 là có số tạm ứng"*. Cột "Cơ sở" của bảng đơn dựng từ DÒNG CHI, nên đơn kiểu
		   này hiện ra ô TRỐNG: đúng hàng thứ hai trong ảnh anh Thắng gửi. Không có dòng chi nào
		   ở đây là CỐ Ý — thêm vào là mất luôn ca cần canh. */
		VHCP_Don::set_tam_ung( $m, 'TÀU TÂN PHÚ', $tien );
	}
	VHCP_Don::gui_duyet_tam_ung( $m );
	return $m;
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 1 — MỘT LƯỢT BẤM = MỘT TỜ LỆNH
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* 🔴 BỆ ĐỠ PHẢI CÓ ĐỦ BẢNG TRƯỚC ĐÃ. Bảng thiếu thì `$wpdb->insert()` trả false LẶNG LẼ, và
   mọi phép dưới đây đỏ với lý do "sổ rỗng" — trông y hệt lỗi của plugin. Đúng bẫy đã sập khi
   thêm bảng `lenh_tu`: mã chạy đúng cả, chỉ có bệ đỡ chưa biết bảng ấy. */
teq( '🔴 bệ đỡ có đủ mọi bảng plugin khai', array(), vhcp_test_bang_thieu() );

teq( 'sổ lệnh khởi đầu rỗng', 0, count( VHCP_Don::ds_lenh_tu()['items'] ) );

$m1 = don_cho_duyet( $KY, $CS_A, 700000 );
$m2 = don_cho_duyet( $KY, $CS_B, 500000 );
$m3 = don_cho_duyet( $KY, $CS_A, 300000 );

$r = VHCP_Don::duyet_tam_ung_nhieu( array( $m1, $m2, $m3 ), 'Chị Quản Lý' );
t( 'duyệt lô: thành công', ! empty( $r['success'] ), $r );
teq( 'duyệt được 3 đơn', 3, (int) $r['approved'] );

$so = VHCP_Don::ds_lenh_tu()['items'];
teq( '🔴 BA đơn duyệt một lượt -> ĐÚNG MỘT tờ lệnh', 1, count( $so ) );
$L = $so[0];
teq( '🔴 tổng tạm ứng trên lệnh', 1500000, (int) $L['tong'] );
teq( 'số đơn trong lệnh', 3, (int) $L['soDon'] );
teq( '🔴 mấy cơ sở tạm ứng', 2, (int) $L['soCoso'] );
teq( 'ghi tên người bấm duyệt', 'Chị Quản Lý', (string) $L['nguoi'] );
teq( 'ghi kỳ/tuần của lô', $KY, (string) $L['ky'] );
t( 'có mốc thời gian', (string) $L['luc'] !== '', $L );

/* --- cơ sở NÀO tạm ứng, mỗi nơi BAO NHIÊU --- */
$theo = array();
foreach ( $L['chiTiet'] as $c ) { $theo[ $c['coso'] ] = (int) $c['tien']; }
teq( '🔴 ' . $CS_A . ' gom hai đơn thành một dòng', 1000000, $theo[ $CS_A ] );
teq( '🔴 ' . $CS_B, 500000, $theo[ $CS_B ] );
/* 🔴 CỘNG CÁC CƠ SỞ PHẢI RA ĐÚNG TỔNG. Lệch ở đây là tờ lệnh tự mâu thuẫn với chính nó —
   người cầm đi phát tiền cộng lại thấy thiếu mà không biết thiếu của ai. */
teq( '🔴 cộng các cơ sở = tổng trên lệnh', (int) $L['tong'], array_sum( $theo ) );
/* Cơ sở nhiều tiền lên trước: người phát tiền đọc từ trên xuống. */
teq( 'dòng gộp hai đơn thì nói rõ là hai', 2, (int) $L['chiTiet'][0]['soDon'] );
t( 'và giữ mã đơn để tra ngược',
	in_array( $m1, $L['chiTiet'][0]['maDons'], true ) && in_array( $m3, $L['chiTiet'][0]['maDons'], true ),
	$L['chiTiet'][0]['maDons'] );

/* --- 🔴 CƠ SỞ NHIỀU TIỀN LÊN TRƯỚC. Người cầm lệnh đi phát tiền đọc từ trên xuống.
   ⚠️ Ca này phải dựng THỨ TỰ CHÈN NGƯỢC với thứ tự tiền, nếu không thì bỏ hẳn phép sắp xếp đi
      bài vẫn xanh — thứ tự tình cờ đúng. Đơn ít tiền vào trước, nhiều tiền vào sau. --- */
/* `list_dons()` trả MỚI NHẤT TRƯỚC, nên đơn nhiều tiền phải tạo TRƯỚC để nó rơi xuống cuối
   vòng gom — có thế bỏ phép sắp xếp đi mới thấy thứ tự sai. */
$ma_nhieu = don_cho_duyet( $KY, $CS_C, 990000 );
$ma_it    = don_cho_duyet( $KY, $CS_B, 120000 );
VHCP_Don::duyet_tam_ung_nhieu( array( $ma_it, $ma_nhieu ), 'Chị Quản Lý' );
$Lsx = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 cơ sở nhiều tiền xếp TRƯỚC dù vào sau', $CS_C, (string) $Lsx['chiTiet'][0]['coso'] );
teq( 'và cơ sở ít tiền xuống dưới', $CS_B, (string) $Lsx['chiTiet'][1]['coso'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 2 — 🔴 ĐƠN CHƯA GÁN CƠ SỞ VẪN PHẢI CÓ CHỖ ĐỨNG
 *
 * Ảnh anh Thắng 07/09/2026: hàng thứ hai, Trần Ngọc Minh Truyền, tạm ứng 600.000đ, ô Cơ sở
 * TRỐNG. Bỏ rơi nó thì tổng lệnh thiếu đúng 600.000đ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$m4 = don_cho_duyet( $KY, '', 600000 );      // xin ứng trước: bảng đơn hiện ô Cơ sở TRỐNG
$m5 = don_cho_duyet( $KY, $CS_C, 400000 );
/* 🔴 ĐƠN XIN ỨNG TRƯỚC VẪN PHẢI HIỆN TÊN CƠ SỞ Ở BẢNG. Anh Thắng 07/09/2026: *"nhân viên khi
   tạo đơn đầu tiên mà nhập tạm ứng, thì phía cơ sở thì không hiện, nhưng bên trong chi tiết
   vẫn hiện bình thường"*. Cột Cơ sở của bảng vốn dựng TỪ DÒNG CHI, mà đơn kiểu này chưa có
   dòng nào — nên bảng ghi "chưa có dòng nào" trong khi mở đơn ra thấy rõ tên cơ sở. Cùng một
   đơn, hai màn nói hai chuyện. Cơ sở ấy nằm ở hàng tạm ứng, và bảng phải đọc tới đó. */
$_cs_bang = null;
foreach ( VHCP_Don::list_dons() as $d ) { if ( $d['maDon'] === $m4 ) { $_cs_bang = (string) $d['coso']; } }
teq( '🔴 bảng đơn hiện đúng cơ sở dù đơn chưa có dòng chi nào', $CS_B, $_cs_bang );
/* Đối chứng cho chính phép trên: đơn ấy ĐÚNG LÀ chưa có dòng chi — nếu nó có dòng thì phép
   trên xanh vì lý do khác hẳn, và cái đang canh không được canh. */
teq( '⚠️ đối chứng: đơn ấy thật sự chưa có dòng chi nào', 0,
	count( VHCP_Don::get_don( $m4 )['lines'] ) );

/* ⚠️ ĐẾM THEO BIẾN, KHÔNG GÕ SỐ CỨNG. Bài này còn dài, và mỗi lần chèn thêm một ca duyệt ở
   trên là mọi con số đếm cứng phía dưới gãy — gãy vì BÀI KIỂM, không phải vì mã. */
$truoc_so = count( VHCP_Don::ds_lenh_tu()['items'] );
$r = VHCP_Don::duyet_tam_ung_nhieu( array( $m4, $m5 ), 'Chị Quản Lý' );
t( 'duyệt lô hai đơn: được', ! empty( $r['success'] ), $r );

$so = VHCP_Don::ds_lenh_tu()['items'];
teq( 'sổ nhận thêm đúng một tờ lệnh', $truoc_so + 1, count( $so ) );
$L2 = $so[0];                                     // mới nhất trước
teq( '🔴 lệnh mới nhất lên đầu', 1000000, (int) $L2['tong'] );
teq( '🔴 đơn ô-cơ-sở-trống KHÔNG bị bỏ rơi', 2, (int) $L2['soCoso'] );
$theo2 = array();
foreach ( $L2['chiTiet'] as $c ) { $theo2[ $c['coso'] ] = (int) $c['tien']; }
/* 🔴 VÀ LỆNH GỌI ĐÚNG TÊN CƠ SỞ, không chịu thua ở nhãn "(chưa gán)". Câu anh Thắng hỏi là
   *"cơ sở nào tạm ứng"* — cơ sở ấy có thật, nằm ở hàng tạm ứng, chỉ là bảng đơn không lấy. */
t( '🔴 lệnh tra ra được cơ sở của đơn xin ứng trước', isset( $theo2[ $CS_B ] ), array_keys( $theo2 ) );
teq( 'đúng số tiền của nó', 600000, isset( $theo2[ $CS_B ] ) ? $theo2[ $CS_B ] : 0 );
t( '⚠️ nên KHÔNG phải dùng tới nhãn "chưa gán"', ! isset( $theo2[ VHCP_Don::CS_CHUA_GAN ] ), array_keys( $theo2 ) );
teq( 'cộng lại vẫn ra tổng', (int) $L2['tong'], array_sum( $theo2 ) );
teq( '⚠️ tờ lệnh ĐẦU TIÊN của bài không bị đụng tới', 1500000, (int) $so[ count( $so ) - 1 ]['tong'] );

/* --- 🔴 CÒN ĐƠN THẬT SỰ KHÔNG CÓ CƠ SỞ Ở ĐÂU CẢ thì mới rơi vào nhãn "chưa gán" — và vẫn
   phải được cộng vào tổng. Bỏ rơi nó là lệnh thiếu tiền ở chỗ không ai nhìn ra. --- */
$m4b = VHCP_Don::create_don( $KY, 'Trần Ngọc Minh Truyền' )['maDon'];
$wpdb->insert( VHCP_DB::t( 'tamung' ), array( 'ma_don' => $m4b, 'coso' => '', 'so' => 350000 ) );
VHCP_Don::gui_duyet_tam_ung( $m4b );
VHCP_Don::duyet_tam_ung_nhieu( array( $m4b ), 'Chị Quản Lý' );
$L2b = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 đơn không có cơ sở ở đâu cả: vẫn vào lệnh', 350000, (int) $L2b['tong'] );
teq( 'và mang nhãn nói rõ là chưa gán', VHCP_Don::CS_CHUA_GAN, (string) $L2b['chiTiet'][0]['coso'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 3 — DUYỆT LẺ TỪNG ĐƠN CŨNG GHI LỆNH
 *
 * ⚠️ Chỉ lô mới có lệnh thì sổ thủng lỗ chỗ: kế toán cộng các tờ lệnh lại không ra tổng đã
 *    duyệt trong tuần, và cái thiếu lại đúng là những đơn duyệt lẻ — thường là đơn gấp.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$truoc_le = count( VHCP_Don::ds_lenh_tu()['items'] );
$m6 = don_cho_duyet( $KY, $CS_B, 250000 );
$r  = VHCP_Don::duyet_tam_ung_ghi_lenh( $m6, 'Chị Quản Lý', '' );
t( 'duyệt lẻ: được', ! empty( $r['success'] ), $r );
$so = VHCP_Don::ds_lenh_tu()['items'];
teq( '🔴 duyệt lẻ cũng đẻ một tờ lệnh', $truoc_le + 1, count( $so ) );
teq( 'lệnh một đơn: đúng số', 250000, (int) $so[0]['tong'] );
teq( 'một cơ sở', 1, (int) $so[0]['soCoso'] );
teq( 'một đơn', 1, (int) $so[0]['soDon'] );
t( 'và trả lệnh về cho màn hình nói lại', ! empty( $r['lenh'] ) && (int) $r['lenh']['tong'] === 250000, $r );

/* 🔴 LÕI `duyet_tam_ung()` KHÔNG ĐƯỢC TỰ GHI LỆNH — nó bị `duyet_tam_ung_nhieu()` gọi trong
   vòng lặp, nên ghi ở đó là một lượt bấm đẻ ra N tờ. Gọi thẳng lõi: sổ phải đứng yên. */
$m7 = don_cho_duyet( $KY, $CS_C, 111000 );
VHCP_Don::duyet_tam_ung( $m7, 'Chị Quản Lý', '' );
teq( '🔴 gọi thẳng lõi: KHÔNG đẻ thêm tờ lệnh nào', $truoc_le + 1, count( VHCP_Don::ds_lenh_tu()['items'] ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 4 — LÔ CÓ ĐƠN BỊ CHỐI
 *
 * Lệnh ghi cho PHẦN ĐÃ DUYỆT. Chối cả lô là kế toán mất dấu vết phần đã qua; ghi cả đơn bị
 * chối là lệnh nói dối số tiền.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$m8 = don_cho_duyet( $KY, $CS_A, 800000 );
$r  = VHCP_Don::duyet_tam_ung_nhieu( array( $m8, 'D_KHONG_CO_THAT', $m7 ), 'Chị Quản Lý' );
t( 'lô có đơn hỏng: báo không thành công', empty( $r['success'] ), $r );
teq( 'chỉ duyệt được 1 đơn', 1, (int) $r['approved'] );
$L4 = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 lệnh chỉ ghi phần ĐÃ duyệt', 800000, (int) $L4['tong'] );
teq( 'và đúng một đơn', 1, (int) $L4['soDon'] );

/* Lô mà KHÔNG đơn nào duyệt được thì không ghi tờ lệnh trắng nào — một tờ 0đ trong sổ chỉ tổ
   làm người đọc tưởng có lượt phát tiền. */
$dem_truoc = count( VHCP_Don::ds_lenh_tu()['items'] );
VHCP_Don::duyet_tam_ung_nhieu( array( 'D_KHONG_CO_THAT_2' ), 'Chị Quản Lý' );
teq( '🔴 lô chối sạch: KHÔNG ghi lệnh trắng', $dem_truoc, count( VHCP_Don::ds_lenh_tu()['items'] ) );
teq( 'lô rỗng cũng vậy', null, VHCP_Don::ghi_lenh_tu( array(), 'Ai Đó' ) );
/* 🔴 VÀ MÃ CÓ TRONG LÔ NHƯNG KHÔNG TRA RA ĐƠN (đơn bị xoá ngay giữa chừng, mã bịa) cũng
   không được đẻ tờ lệnh trắng. Đây là cửa khác hẳn với lô rỗng: danh sách mã KHÔNG rỗng, chỉ
   là không đơn nào tra ra — nên chốt "không có gì để ghi" phải nằm SAU vòng gom, không phải
   chỉ ở đầu hàm. */
teq( '🔴 lô toàn mã không tra ra đơn: cũng không ghi lệnh trắng', null,
	VHCP_Don::ghi_lenh_tu( array( 'D_BIA_1', 'D_BIA_2' ), 'Ai Đó' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 5 — LỆNH LÀ ẢNH CHỤP, KHÔNG ĐỔI THEO ĐƠN
 *
 * 🔴 Đơn bị trả lại / sửa số / xoá SAU KHI đã có lệnh thì tờ lệnh vẫn phải nói đúng con số lúc
 *    duyệt. Đó là lý do `chi_tiet` lưu JSON chứ không nối khoá sang bảng đơn: lệnh ghi lại
 *    một việc ĐÃ XẢY RA, và tiền đã ra khỏi két theo nó.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$truoc = VHCP_Don::ds_lenh_tu()['items'][0];
$tong_truoc = (int) $truoc['tong'];
VHCP_Don::tra_lai_don( $m8, 'Duyệt nhầm' );
$sau = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 trả lại đơn: tờ lệnh KHÔNG đổi số', $tong_truoc, (int) $sau['tong'] );
teq( 'và vẫn còn trong sổ', (string) $truoc['id'], (string) $sau['id'] );

VHCP_Don::delete_don_admin( $m8 );
$sau2 = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 xoá hẳn đơn: tờ lệnh vẫn nguyên', $tong_truoc, (int) $sau2['tong'] );
teq( 'vẫn gọi đúng tên cơ sở', $CS_A, (string) $sau2['chiTiet'][0]['coso'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 6 — LÔ TRỘN NHIỀU TUẦN THÌ NÓI THẲNG LÀ TRỘN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$KY2 = 'T9/2026 (14/9-20/9/2026)';
$m9  = don_cho_duyet( $KY,  $CS_A, 100000 );
$m10 = don_cho_duyet( $KY2, $CS_B, 200000 );
VHCP_Don::duyet_tam_ung_nhieu( array( $m9, $m10 ), 'Chị Quản Lý' );
$L6 = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 lô trộn hai tuần: nói rõ là nhiều kỳ', '(nhiều kỳ)', (string) $L6['ky'] );
teq( 'tổng vẫn đúng', 300000, (int) $L6['tong'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 7 — SỐ TRÊN LỆNH ĐỌC CÙNG MỘT NGUỒN VỚI BẢNG ĐƠN
 *
 * 🔴 Anh Thắng gửi ảnh 31/08/2026 *"2 có số tổng tạm ứng khác nhau"*. Lần này tờ lệnh in ra
 *    giấy và đem đi phát tiền, nên lệch là lệch tiền mặt.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* Quản lý duyệt với số SỬA LẠI (khác số nhân viên xin) — lệnh phải theo số ĐÃ DUYỆT. */
$m11 = don_cho_duyet( $KY, $CS_C, 900000 );
VHCP_Don::duyet_tam_ung_ghi_lenh( $m11, 'Chị Quản Lý', 650000 );
$L7 = VHCP_Don::ds_lenh_tu()['items'][0];
teq( '🔴 duyệt hạ số xuống: lệnh theo số ĐÃ DUYỆT, không theo số xin', 650000, (int) $L7['tong'] );

$tu_bang = 0;
foreach ( VHCP_Don::list_dons() as $d ) { if ( $d['maDon'] === $m11 ) { $tu_bang = (int) $d['tamUng']; } }
teq( '🔴 và khớp đúng con số bảng đơn đang hiện', $tu_bang, (int) $L7['tong'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 8 — 🔴 ĐƠN DUYỆT TRƯỚC KHI CÓ TÍNH NĂNG NÀY
 *
 * Anh Thắng 07/09/2026: *"duyệt xong nhưng vẫn chưa thấy lệnh"*. Ba đơn ấy được duyệt TRƯỚC
 * khi cài bản có sổ lệnh, nên sổ không kể tới — mà màn hình chỉ nói "Chưa có lệnh tạm ứng
 * nào", nghe y như chưa ai duyệt lần nào. Mất một lượt qua lại chỉ để biết chuyện gì.
 *
 * Bài này canh hai thứ: máy chủ NÓI ĐƯỢC vì sao sổ rỗng, và DỰNG BÙ được cho phần đã lỡ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* Dựng đúng cảnh: đơn đi qua cửa duyệt LÕI (`duyet_tam_ung`), tức không ghi lệnh — y hệt một
   site đang chạy bản cũ. */
$m_cu1 = don_cho_duyet( $KY, $CS_A, 700000 );
$m_cu2 = don_cho_duyet( $KY, '',    600000 );   // ô Cơ sở trống, như hàng thứ hai trong ảnh
$m_cu3 = don_cho_duyet( $KY, $CS_B, 500000 );
foreach ( array( $m_cu1, $m_cu2, $m_cu3 ) as $_m ) {
	VHCP_Don::duyet_tam_ung( $_m, 'Nguyễn Thị Phương Hòa', '' );
}

$sot = VHCP_Don::don_duyet_chua_co_lenh();
$sot_ma = array();
foreach ( $sot as $x ) { $sot_ma[ $x['maDon'] ] = (int) $x['tamUng']; }
t( '🔴 nhận ra ba đơn đã duyệt mà thiếu lệnh',
	isset( $sot_ma[ $m_cu1 ] ) && isset( $sot_ma[ $m_cu2 ] ) && isset( $sot_ma[ $m_cu3 ] ), array_keys( $sot_ma ) );
teq( 'và đọc đúng số tạm ứng của chúng', 700000, $sot_ma[ $m_cu1 ] );
/* ⚠️ ĐƠN ĐÃ CÓ LỆNH THÌ KHÔNG ĐƯỢC KỂ LẠI — kể lại là dựng bù đẻ ra tờ thứ hai cho cùng một
   lượt duyệt, tức tiền đếm đôi trong sổ. */
t( '🔴 đơn ĐÃ có lệnh không bị kể vào danh sách sót', ! isset( $sot_ma[ $m1 ] ), array_keys( $sot_ma ) );

/* 🔴 ĐƠN CHƯA DUYỆT KHÔNG PHẢI ĐƠN SÓT. Kể nó vào là dựng bù ghi một tờ lệnh cho khoản chưa
   ai duyệt — tức tờ lệnh nói dối, mà người ta cầm tờ ấy đi phát tiền. */
$m_chua = don_cho_duyet( $KY, $CS_C, 990000 );      // gửi xin rồi, CHƯA duyệt
$sot2 = array();
foreach ( VHCP_Don::don_duyet_chua_co_lenh() as $x ) { $sot2[ $x['maDon'] ] = 1; }
t( '🔴 đơn CHƯA duyệt không bị kể là sót', ! isset( $sot2[ $m_chua ] ), array_keys( $sot2 ) );
teq( 'đối chứng: nó đúng là đang chờ duyệt', 'Chờ duyệt tạm ứng',
	(string) VHCP_Don::don_row( $m_chua )['trang_thai'] );

$cd = VHCP_Don::chan_doan_lenh_tu();
teq( 'chẩn đoán: bảng sổ có thật', 1, (int) $cd['coBang'] );
teq( '🔴 chẩn đoán: đếm đúng số tờ lệnh đang có',
	count( VHCP_Don::ds_lenh_tu( 200 )['items'] ), (int) $cd['soLenh'] );
t( 'và số ấy khác 0 (sổ đang có lệnh thật)', (int) $cd['soLenh'] > 0, $cd );
t( 'chẩn đoán: đếm đúng số đơn sót', (int) $cd['soDonSot'] === count( $sot ), $cd );
teq( '🔴 và cộng đúng số tiền đang thiếu khỏi sổ', array_sum( $sot_ma ), (int) $cd['tienSot'] );
teq( 'ba đơn vừa dựng góp đúng 1.800.000',
	1800000, $sot_ma[ $m_cu1 ] + $sot_ma[ $m_cu2 ] + $sot_ma[ $m_cu3 ] );

/* --- DỰNG BÙ --- */
$truoc_bu = count( VHCP_Don::ds_lenh_tu( 200 )['items'] );
$rb = VHCP_Don::dung_lenh_bu();
t( 'dựng bù: chạy được', ! empty( $rb['success'] ), $rb );
t( '🔴 dựng ra ít nhất một tờ', (int) $rb['soLenh'] >= 1, $rb );
teq( 'gom đủ mọi đơn sót', count( $sot ), (int) $rb['soDon'] );
teq( 'sổ dài thêm đúng bằng số tờ vừa dựng',
	$truoc_bu + (int) $rb['soLenh'], count( VHCP_Don::ds_lenh_tu( 200 )['items'] ) );

/* 🔴 GOM THEO NGƯỜI DUYỆT + NGÀY DUYỆT: ba đơn trên cùng một người, cùng một ngày -> MỘT tờ. */
$L8 = null;
foreach ( VHCP_Don::ds_lenh_tu( 200 )['items'] as $x ) {
	if ( $x['nguoi'] === 'Nguyễn Thị Phương Hòa' ) { $L8 = $x; break; }
}
t( 'tìm được tờ lệnh bù', is_array( $L8 ), $L8 );
teq( '🔴 ba đơn cùng người cùng ngày -> MỘT tờ', 3, (int) $L8['soDon'] );
teq( 'tổng đúng 1.800.000', 1800000, (int) $L8['tong'] );
teq( 'hai cơ sở (một đơn ô trống tra ra được cơ sở)', 2, (int) $L8['soCoso'] );

/* 🔴 CHẠY LẦN HAI KHÔNG ĐẺ THÊM GÌ. Bấm nhầm hai lần là tiền đếm đôi trong sổ — mà sổ này
   người ta cầm đi phát tiền. */
$sau_bu = count( VHCP_Don::ds_lenh_tu( 200 )['items'] );
$rb2 = VHCP_Don::dung_lenh_bu();
teq( '🔴 dựng bù lần hai: không đơn nào sót nữa', 0, (int) $rb2['soDon'] );
teq( 'và không đẻ thêm tờ nào', $sau_bu, count( VHCP_Don::ds_lenh_tu( 200 )['items'] ) );
teq( 'chẩn đoán cũng nói hết sót', 0, (int) VHCP_Don::chan_doan_lenh_tu()['soDonSot'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 9 — 🔴 CỬA API TRỎ ĐÚNG HÀM
 *
 * Mọi phép trên đây gọi thẳng lớp. Nhưng màn hình đi qua BẢNG ÁNH XẠ trong `class-vhcp-api.php`
 * — trỏ `duyetTamUng` về lõi `duyet_tam_ung` là nút "✔ Duyệt tạm ứng" trên từng hàng lặng lẽ
 * thôi ghi lệnh, mà mọi bài kiểm gọi thẳng lớp vẫn xanh.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$api_src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
/* Bóc chú thích KHỐI trước khi dò: ngay trên dòng ánh xạ có một khối chú thích nhắc lại cả hai
   tên hàm, nên dò trần trụi là tự xanh — bẫy đã cắn năm lần ở hai kho này. */
$api_ma = preg_replace( '#(^|[\s;{,:])/\*[\s\S]*?\*/#', '$1 ', $api_src );
t( 'đối chứng: bóc chú thích xong vẫn còn bảng ánh xạ để soi',
	false !== strpos( $api_ma, "'duyetTamUngNhieu'" ), '' );
if ( preg_match( "#'duyetTamUng'\s*=>\s*array\( 'VHCP_Don', '([a-z_]+)' \)#", $api_ma, $mm ) ) {
	teq( '🔴 cửa duyệt lẻ trỏ vào hàm CÓ ghi lệnh', 'duyet_tam_ung_ghi_lenh', $mm[1] );
} else {
	t( '🔴 tìm được dòng ánh xạ duyetTamUng', false, $api_ma );
}
if ( preg_match( "#'duyetTamUngNhieu'\s*=>\s*array\( 'VHCP_Don', '([a-z_]+)' \)#", $api_ma, $mn ) ) {
	teq( 'cửa duyệt lô vẫn trỏ đúng chỗ', 'duyet_tam_ung_nhieu', $mn[1] );
} else {
	t( 'tìm được dòng ánh xạ duyetTamUngNhieu', false, '' );
}
t( '🔴 sổ lệnh có cửa cho màn hình đọc', false !== strpos( $api_ma, "'dsLenhTU'" ), '' );
t( 'chẩn đoán có cửa', false !== strpos( $api_ma, "'chanDoanLenhTU'" ), '' );
t( 'dựng lệnh bù có cửa', false !== strpos( $api_ma, "'dungLenhBu'" ), '' );
/* ⚠️ Dựng bù GHI THẲNG vào sổ lệnh — nhân viên không được gọi. */
if ( preg_match( '#\$nguoi_duyet = array\(([\s\S]*?)\);#', $api_ma, $mv ) ) {
	t( '🔴 dựng lệnh bù chỉ dành cho người duyệt / kế toán',
		false !== strpos( $mv[1], "'dungLenhBu'" ), $mv[1] );
} else {
	t( '🔴 bốc được danh sách hàm của người duyệt', false, '' );
}

/* --- 🔴 CHỖ MÙ CỦA BỆ ĐỠ, PHẢI NÓI RA ---
   Bệ đỡ SQLite ở đây dùng ĐỒNG HỒ GIẢ ĐỨNG YÊN, nên mọi tờ lệnh mang cùng một `luc`, và mã
   `LTU_...` thì tình cờ tăng dần. Nghĩa là bài kiểm KHÔNG dựng lại được ca hỏng thật: hai lượt
   duyệt rơi cùng một giây trên site thật, rồi `ORDER BY luc DESC, id DESC` xếp lẫn lộn vì phần
   đuôi ngẫu nhiên của mã không đệm 0 (`..._abc1` so với `..._abcxyz`).

   Không dựng lại được thì canh CÂU TRUY VẤN: nó phải sắp xếp theo `stt` (số thứ tự ghi thật),
   và KHÔNG được mượn `luc`/`id` làm khoá sắp xếp. */
$don_src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
$don_ma  = preg_replace( '#(^|[\s;{,:])/\*[\s\S]*?\*/#', '$1 ', $don_src );
if ( preg_match( '#SELECT \* FROM \$t ORDER BY ([^"]+) LIMIT#', $don_ma, $mq ) ) {
	$sx = trim( $mq[1] );
	teq( '🔴 sổ lệnh sắp xếp theo số thứ tự ghi thật', 'stt DESC', $sx );
	t( '🔴 và KHÔNG mượn luc/id làm khoá sắp xếp',
		false === strpos( $sx, 'luc' ) && false === strpos( $sx, 'id' ), $sx );
} else {
	t( '🔴 bốc được câu truy vấn sổ lệnh', false, '' );
}
t( 'và bảng có cột stt để mà sắp',
	false !== strpos( file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-db.php' ),
		'stt BIGINT(20) NOT NULL AUTO_INCREMENT' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KẾT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

if ( count( $truot ) ) {
	echo "\n=== LỆNH TẠM ỨNG ===\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat   TRƯỢT: " . count( $truot ) . "\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: mỗi lượt duyệt một tờ lệnh, cộng các cơ sở ra đúng tổng.\n";
