<?php
/**
 * ĐƠN "CHƯA RÕ BỘ PHẬN" — PHẢI ĐƯỢC GỌI TÊN, VÀ ẨN ĐƯỢC.
 *
 * Anh Thắng 08/09/2026: *"lý do sao tk kế toán mtd vẫn hiện đơn kvc"* — kèm ảnh màn Quyết toán
 * của tài khoản mang vai "Kế toán máy tự động" (thanh tiêu đề ghi ĐÚNG vai ấy, nên vai đã ăn)
 * mà bảng vẫn liệt kê mười đơn của khu vui chơi.
 *
 * =============================================================================================
 * 🔴 VÌ SAO. Chốt bó bộ phận (1.82.0) có BA nhánh "cho qua", mỗi nhánh đều đúng khi đứng một
 *    mình, nhưng cộng lại thì gần như không lọc gì:
 *      · đơn chưa có dòng chi nào (đơn xin ứng trước) — chưa có gì để nói nó thuộc đâu
 *      · dòng mang loại chi phí CHƯA khai ô Bộ phận — danh mục anh dựng từ sổ cũ, phần lớn
 *        còn trống ô ấy
 *      · dòng mang loại KHÔNG có trong danh mục
 *    Bỏ cả ba nhánh là hôm sau kế toán mở màn ra thấy trắng, tiền treo không ai thấy để đòi.
 *
 * 🔴 NÊN: KHÔNG chặn thêm ở máy chủ. Gắn cờ `bpMo` cho đơn nào lọt vào mà KHÔNG dòng nào khai
 *    đúng bộ phận đang bó, rồi để màn nói ra và cho ẩn nếu người dùng muốn.
 *
 * ⚠️ CHẠY THẬT: khai vai, khai danh mục, đổ đơn, ĐĂNG NHẬP làm từng người rồi đòi đúng cờ.
 *
 * Chạy: php tools/test/kiem-don-chua-ro-bo-phan.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
global $wpdb;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. DỰNG SÂN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$thieu = vhcp_test_bang_thieu();
t( 'mọi bảng của plugin dựng được (không thì mọi phép dưới xanh oan)', array() === $thieu, $thieu );

VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Kế toán máy tự động', 'Kế toán cá nhân', 'Máy tự động' ),
	array( 'Kế toán khu vui chơi', 'Kế toán cá nhân', 'Cơ sở' ),
) );
/* Cột: Tên · TK Nợ · TK Có · Mã ĐT · BỘ PHẬN · Ghi chú · Tên MISA · Loại TT */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Sửa máy gắp thú', '6427', '', '', 'Máy tự động',   '', '', '' ),
	array( 'Mua bóng nhựa',   '6421', '', '', 'Cơ sở',         '', '', '' ),
	array( 'Chi phí khác',    '6428', '', '', '',              '', '', '' ),   // CHƯA khai bộ phận
) );
VHCP_Cfg::clear_cache();
teq( 'đối chứng · danh mục khai đúng · máy tự động', 'Máy tự động',  VHCP_Cfg::bo_phan_cua_loai( 'Sửa máy gắp thú' ) );
/* 🔴 "Khu vui chơi" KHÔNG phải một bộ phận bên chi phí — `BO_PHAN_DS` chỉ có Cơ sở · Văn
   phòng · Kỹ thuật · Marketing · Công tác · Setup · Máy tự động, và bên khu vui chơi khai vào
   "Cơ sở". Gõ tên ngoài danh sách là `bo_phan_chuan()` trả rỗng, tức vai ấy KHÔNG bó gì cả. */
teq( 'đối chứng · danh mục khai đúng · khu vui chơi khai là "Cơ sở"', 'Cơ sở', VHCP_Cfg::bo_phan_cua_loai( 'Mua bóng nhựa' ) );
teq( '🔴 "Khu vui chơi" gõ thẳng thì KHÔNG phải bộ phận hợp lệ', '', VHCP_Cfg::bo_phan_chuan( 'Khu vui chơi' ) );
teq( 'đối chứng · loại bỏ trống ô Bộ phận -> rỗng',   '',             VHCP_Cfg::bo_phan_cua_loai( 'Chi phí khác' ) );

/* 🔴 PHÒNG BAN ĐI THEO TÀI KHOẢN, KHÔNG THEO VAI (đổi 13/09/2026) — xem khối dài ở
   `VHCP_Auth::bo_phan_bo()`. Bảng vai ở trên vẫn khai cột "Chỉ làm bộ phận" để dữ liệu cũ đọc
   được, nhưng chốt thật nay lấy từ ô Bộ phận của tài khoản, tức tham số thứ tư ở đây. */
function lam( $vai, $ten = 'NV', $bp = '' ) { VHCP_Auth::dat_vai_tro( $vai, $ten, '', $bp ); }
function them_don( $ma, $dong ) {
	global $wpdb;
	$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => $ma, 'ky' => 'T9', 'trang_thai' => 'Chờ quyết toán', 'nguoi_lap' => 'NV' ) );
	foreach ( $dong as $i => $loai ) {
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
			'id' => $ma . '_' . $i, 'ma_don' => $ma, 'coso' => 'CS', 'nhom' => $loai, 'thanh_tien' => 1000 ) );
	}
}
/** Bảng đơn đang hiện, kèm cờ "chưa rõ bộ phận": mã đơn => bpMo. */
function co_mo() {
	$a = array();
	foreach ( VHCP_Don::list_dons() as $x ) { $a[ (string) $x['maDon'] ] = $x['bpMo']; }
	ksort( $a );
	return $a;
}

them_don( 'D_MTD',   array( 'Sửa máy gắp thú' ) );                     // đúng bộ phận MTD
them_don( 'D_KVC',   array( 'Mua bóng nhựa' ) );                       // thuần khu vui chơi
them_don( 'D_LAN',   array( 'Mua bóng nhựa', 'Sửa máy gắp thú' ) );    // lẫn hai bộ phận
them_don( 'D_KHAC',  array( 'Chi phí khác' ) );                        // loại chưa khai bộ phận
them_don( 'D_LA',    array( 'Loại lạ hoắc' ) );                        // loại KHÔNG có trong danh mục
them_don( 'D_KVCKHAC', array( 'Mua bóng nhựa', 'Chi phí khác' ) );     // KVC + một dòng chưa khai
them_don( 'D_TRONG', array() );                                        // đơn xin ứng trước, chưa có dòng nào

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. NGƯỜI KHÔNG BỊ BÓ — KHÔNG ĐƯỢC ĐỘNG GÌ TỚI
 *
 * Anh Thắng 08/09/2026: *"nhớ đừng can thiệp gì bên phần chi phí khu vui chơi"*. Cờ này chỉ
 * sinh ra cho người BỊ BÓ; ai không bị bó mà nhận cờ `true` là màn của họ mọc thêm dấu ❓BP
 * trên những đơn hoàn toàn bình thường.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
lam( 'Kế toán cá nhân' );
$moi = co_mo();
teq( 'đối chứng · kế toán thường vẫn thấy đủ bảy đơn',
	array( 'D_KHAC', 'D_KVC', 'D_KVCKHAC', 'D_LA', 'D_LAN', 'D_MTD', 'D_TRONG' ), array_keys( $moi ) );
$co_co = array();
foreach ( $moi as $m => $v ) { if ( $v ) { $co_co[] = $m; } }
teq( '🔴 người KHÔNG bó bộ phận thì KHÔNG đơn nào bị gắn cờ "chưa rõ"', array(), $co_co );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. KẾ TOÁN MÁY TỰ ĐỘNG — ĐÚNG CÁI ANH THẮNG ĐANG NHÌN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
teq( 'vai ăn · người này đang bị bó vào Máy tự động', 'Máy tự động', VHCP_Auth::bo_phan_bo() );
$mtd = co_mo();

teq( '🔴 đơn thuần khu vui chơi BỊ CHẶN HẲN, không hiện',
	false, array_key_exists( 'D_KVC', $mtd ) );
teq( 'đơn thuần khu vui chơi + một dòng chưa khai thì VẪN LỌT (nhánh cho qua)',
	true, array_key_exists( 'D_KVCKHAC', $mtd ) );

teq( '🔴 đơn của chính bộ phận mình → KHÔNG phải "chưa rõ"',        false, $mtd['D_MTD'] );
teq( '🔴 đơn LẪN hai bộ phận, có dòng của mình → KHÔNG "chưa rõ"',  false, $mtd['D_LAN'] );
teq( '🔴 đơn chỉ toàn loại CHƯA khai bộ phận → "chưa rõ"',          true,  $mtd['D_KHAC'] );
teq( '🔴 đơn mang loại KHÔNG có trong danh mục → "chưa rõ"',        true,  $mtd['D_LA'] );
teq( '🔴 đơn KHÔNG có dòng chi nào → "chưa rõ"',                    true,  $mtd['D_TRONG'] );
/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA BẢN NÀY. Đơn này lọt vào bảng CHỈ NHỜ dòng "Chi phí khác" chưa
   khai; dòng còn lại là của khu vui chơi. Không dòng nào là việc của máy tự động, nên nó phải
   bị gọi là "chưa rõ" — đây đúng là loại đơn phủ kín màn hình trong ảnh anh gửi. */
teq( '🔴 đơn lọt nhờ dòng chưa khai, còn lại là mảng khác → "chưa rõ"', true, $mtd['D_KVCKHAC'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. CỜ ĐỌC THEO NGƯỜI ĐANG XEM, KHÔNG PHẢI THUỘC TÍNH CỦA ĐƠN
 *
 * Cùng một đơn, hai kế toán hai bộ phận nhìn ra hai chuyện khác nhau. Nếu cờ được tính một lần
 * rồi dùng chung thì bên này ẩn mất đơn của bên kia.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
lam( 'Kế toán khu vui chơi', 'NV', 'Cơ sở' );
$kvc = co_mo();
teq( 'đơn thuần máy tự động BỊ CHẶN với kế toán khu vui chơi',
	false, array_key_exists( 'D_MTD', $kvc ) );
teq( '🔴 D_KVC "chưa rõ" với MTD ở trên, nhưng RÕ với kế toán khu vui chơi', false, $kvc['D_KVC'] );
teq( '🔴 D_KVCKHAC cũng RÕ với kế toán khu vui chơi',                        false, $kvc['D_KVCKHAC'] );
teq( 'D_LAN có dòng bóng nhựa nên cũng rõ với khu vui chơi',                 false, $kvc['D_LAN'] );
teq( 'còn D_KHAC thì hai bên đều "chưa rõ"',                                 true,  $kvc['D_KHAC'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. KHAI DANH MỤC TỚI ĐÂU, BẢNG SẠCH TỚI ĐÓ
 *
 * Đây là lối thoát mà dải nhắc trên màn chỉ cho kế toán. Nếu khai xong mà cờ không đổi thì lời
 * hướng dẫn ấy là lời nói suông.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Sửa máy gắp thú', '6427', '', '', 'Máy tự động',  '', '', '' ),
	array( 'Mua bóng nhựa',   '6421', '', '', 'Cơ sở',        '', '', '' ),
	array( 'Chi phí khác',    '6428', '', '', 'Cơ sở',        '', '', '' ),   // vừa khai
) );
VHCP_Cfg::clear_cache();
lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
$sau = co_mo();
teq( '🔴 khai xong ô Bộ phận -> đơn "Chi phí khác" BIẾN HẲN khỏi bảng máy tự động',
	false, array_key_exists( 'D_KHAC', $sau ) );
teq( '🔴 và đơn KVC + dòng vừa khai cũng biến hẳn',
	false, array_key_exists( 'D_KVCKHAC', $sau ) );
teq( 'đơn của chính mình thì ở lại', true, array_key_exists( 'D_MTD', $sau ) );
teq( 'và vẫn không phải "chưa rõ"',  false, $sau['D_MTD'] );
teq( 'đơn mang loại NGOÀI danh mục vẫn lọt, vẫn "chưa rõ"', true, $sau['D_LA'] );
teq( 'đơn rỗng vẫn lọt, vẫn "chưa rõ"',                     true, $sau['D_TRONG'] );

/* Số loại chưa khai mà dải nhắc đếm phải tụt theo — không thì màn vẫn kêu "còn N loại" sau khi
   người ta đã khai xong, và họ đi tìm một thứ không còn ở đó. */
$b = VHCP_Don::get_bootstrap();
teq( 'boot vẫn nói đúng bộ phận đang bó', 'Máy tự động', $b['boPhanBo'] );
teq( '🔴 chỉ còn "Loại lạ hoắc" là chưa khai', 1, $b['loaiChuaBP'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. GIAO DIỆN PHẢI THỰC SỰ DÙNG CỜ ẤY
 *
 * Máy chủ trả cờ mà màn không đọc thì bản này chỉ là một cột dữ liệu chết. Ba chỗ phải có:
 * lối ẩn (`_anVaoMo`), hai bộ lọc của màn Quyết toán, và dấu trên hàng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$app = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 giao diện có lối ẩn đơn chưa rõ bộ phận',  false !== strpos( $app, 'function _anVaoMo(' ) );
t( '🔴 bộ lọc màn Quyết toán gọi lối ấy',         substr_count( $app, '_anVaoMo(d)' ) >= 3 );
t( '🔴 hàng đơn bày dấu ❓BP theo cờ',            false !== strpos( $app, 'd.bpMo?' ) );
t( 'ô tích nhớ ở máy người dùng, không ghi lên máy chủ', false !== strpos( $app, "'vhcp_an_bp_mo'" ) );
/* 🔴 KHÔNG LƯU LỰA CHỌN NÀY LÊN MÁY CHỦ. Đây là cách XEM, không phải phân quyền — ghi lên máy
   chủ là một lượt ghi cấu hình cho mỗi lần tích, và hai kế toán cùng bộ phận đè lên nhau. */
t( 'và KHÔNG đi qua đường lưu cấu hình', false === strpos( $app, "saveCfg('vhcp_an_bp_mo'" ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: đơn chưa rõ bộ phận được gọi tên, khai danh mục tới đâu bảng sạch tới đó.\n";
