<?php
/**
 * KIỂM GÁC VỊ TRÍ THEO CƠ SỞ (VHCC_ViTri + nhánh gác trong VHCC_Online::cham_cong).
 *
 * 🔴 VÌ SAO BÀI NÀY ĐÁNG GIÁ HƠN VẺ NGOÀI. Đây là bộ duy nhất trong plugin có quyền CHỐI một
 *    lượt chấm công vì một con số đo được từ phần cứng của người dùng. Sai một chốt ở đây không
 *    hiện ra thành lỗi đỏ — nó hiện ra thành một người đi làm đủ ngày mà không chấm được công,
 *    và họ không có cách nào tự chứng minh.
 *
 *    Nên bài này canh CẢ HAI PHÍA, và phía "không được chặn" quan trọng hơn phía "phải chặn":
 *    gác lọt một lượt chấm hộ thì quản lý vẫn còn cái ảnh và dòng ghi chú để tra; chặn oan một
 *    người đứng đúng chỗ thì họ đứng đó, bấm mãi, và không ai ở đầu kia.
 *
 * Chạy: php tools/test/kiem-vi-tri.php
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

/* Cửa hàng mẫu: một điểm có thật ở Quận 1 để những con số dưới đây đọc được bằng mắt. */
$CS   = 'VT_SHOP';
$LAT  = 10.776500;
$LNG  = 106.700900;

/* =============================================================== 1. ĐO KHOẢNG CÁCH */

/* Cùng một điểm thì phải là 0, không phải "gần 0" — sai số nổi lên từ đây thì mọi ngưỡng dưới
   đều lệch theo mà không nhìn ra. */
t( 'cùng một điểm -> 0m', 0 === VHCC_ViTri::khoang_cach( $LAT, $LNG, $LAT, $LNG ) );

/* 🔴 MỘT ĐỘ VĨ TUYẾN ~111km Ở MỌI NƠI. Đây là phép canh chính của haversine: lệch 0.001 độ vĩ
   là ~111m. Phép trừ toạ độ nhân hằng số cũng ra đúng con số này, nên nó CHƯA đủ — xem phép
   kinh tuyến ngay dưới. */
$d_vi = VHCC_ViTri::khoang_cach( $LAT, $LNG, $LAT + 0.001, $LNG );
t( '0.001 độ vĩ ≈ 111m', $d_vi >= 108 && $d_vi <= 114, $d_vi );

/* 🔴 VÀ MỘT ĐỘ KINH TUYẾN Ở VĨ ĐỘ 10.7 CHỈ CÒN ~109m — NGẮN HƠN ~2%. Đúng chỗ phép tính phẳng
   sai. Chênh 2m trên 110m nghe nhỏ, nhưng nó tỉ lệ với khoảng cách: ở bán kính 2km thì thành
   40m, tức là đủ để người đứng phía đông bị chặn còn người đứng phía bắc thì không. */
$d_kinh = VHCC_ViTri::khoang_cach( $LAT, $LNG, $LAT, $LNG + 0.001 );
t( '🔴 0.001 độ kinh ở vĩ 10.7 NGẮN HƠN 0.001 độ vĩ', $d_kinh < $d_vi, $d_kinh . ' vs ' . $d_vi );
t( '0.001 độ kinh ≈ 109m', $d_kinh >= 106 && $d_kinh <= 112, $d_kinh );

/* =============================================================== 2. ĐỌC TOẠ ĐỘ */

$c = VHCC_ViTri::doc_toa_do( '10.776500, 106.700900' );
t( 'đọc được cặp số có dấu cách', $c && abs( $c['lat'] - $LAT ) < 1e-6 && abs( $c['lng'] - $LNG ) < 1e-6, $c );
$c = VHCC_ViTri::doc_toa_do( '10.7765,106.7009' );
t( 'đọc được cặp số không dấu cách', $c && abs( $c['lat'] - 10.7765 ) < 1e-6, $c );

/* Link Google Maps dạng khung nhìn. */
$c = VHCC_ViTri::doc_toa_do( 'https://www.google.com/maps/@10.7765,106.7009,17z' );
t( 'đọc được link dạng @lat,lng,zoom', $c && abs( $c['lat'] - 10.7765 ) < 1e-6
	&& abs( $c['lng'] - 106.7009 ) < 1e-6, $c );

/* 🔴 LINK CÓ CẢ `@` LẪN `!3d!4d` THÌ PHẢI LẤY `!3d!4d`. `@` là chỗ khung nhìn đang đặt — người
   ta hay kéo bản đồ lệch đi trước khi chép link, nên hai cặp số này KHÁC NHAU thật, có khi vài
   trăm mét. Lấy nhầm `@` là khai mốc lệch đúng bằng lượt kéo bản đồ cuối cùng. */
$c = VHCC_ViTri::doc_toa_do(
	'https://www.google.com/maps/place/X/@10.7800,106.7100,17z/data=!3m1!4b1!4m5!3d10.7765!4d106.7009' );
t( '🔴 link có cả @ lẫn !3d!4d -> lấy !3d!4d (điểm ghim), không lấy @ (khung nhìn)',
	$c && abs( $c['lat'] - 10.7765 ) < 1e-6 && abs( $c['lng'] - 106.7009 ) < 1e-6, $c );

$c = VHCC_ViTri::doc_toa_do( 'https://maps.google.com/?q=10.7765,106.7009' );
t( 'đọc được dạng ?q=', $c && abs( $c['lat'] - 10.7765 ) < 1e-6, $c );

/* 🔴 (0,0) LÀ Ô BỎ TRỐNG BỊ ÉP VỀ SỐ, KHÔNG PHẢI MỘT CƠ SỞ NGOÀI KHƠI VỊNH GUINEA. */
t( '🔴 chối cặp 0,0', null === VHCC_ViTri::doc_toa_do( '0, 0' ) );
t( '🔴 chối cặp 0.0, 0.0', null === VHCC_ViTri::doc_toa_do( '0.0,0.0' ) );
t( 'chối vĩ độ ngoài biên', null === VHCC_ViTri::doc_toa_do( '106.7009, 10.7765' ) );
t( 'chối chuỗi rỗng', null === VHCC_ViTri::doc_toa_do( '' ) );
t( 'chối chữ', null === VHCC_ViTri::doc_toa_do( 'cửa hàng Nguyễn Huệ' ) );

/* =============================================================== 3. KHAI MỐC */

$admin = array( 'name' => 'Sếp', 'role' => 'ADMIN', 'coso' => '' );

/* Chưa khai gì -> không gác gì. Mặc định phải là TẮT, không phải "bật mà chưa có số". */
$x = VHCC_ViTri::xet( $CS, array( 'lat' => $LAT, 'lng' => $LNG, 'acc' => 10 ) );
t( '🔴 cơ sở chưa khai mốc -> không gác', empty( $x['gac'] ) && 'khong_gac' === $x['ket'], $x );

/* 🔴 BẬT CHẶN KHI CHƯA CÓ TOẠ ĐỘ PHẢI BỊ CHỐI NGAY LÚC KHAI. Nhận lặng lẽ thì người khai tưởng
   đã khoá cửa, mà `xet()` vẫn trả 'khong_gac' và cửa vẫn mở suốt — hỏng kiểu không ai phát hiện. */
$r = VHCC_ViTri::luu( $admin, $CS, array( 'toaDo' => '', 'cheDo' => VHCC_ViTri::CHAN ) );
t( '🔴 chối bật Chặn khi chưa có toạ độ', empty( $r['ok'] ), $r );

/* Sàn bán kính. */
$r = VHCC_ViTri::luu( $admin, $CS, array( 'toaDo' => "$LAT, $LNG", 'bk' => 5, 'cheDo' => VHCC_ViTri::GHI ) );
t( '🔴 chối bán kính dưới sàn ' . VHCC_ViTri::BK_TOI_THIEU . 'm', empty( $r['ok'] ), $r );
$r = VHCC_ViTri::luu( $admin, $CS, array( 'toaDo' => "$LAT, $LNG", 'bk' => 99999, 'cheDo' => VHCC_ViTri::GHI ) );
t( 'chối bán kính trên trần', empty( $r['ok'] ), $r );

/* Khai thật. */
$r = VHCC_ViTri::luu( $admin, $CS, array( 'toaDo' => "$LAT, $LNG", 'bk' => 100, 'cheDo' => VHCC_ViTri::GHI ) );
t( 'khai được mốc', ! empty( $r['ok'] ), $r );
$m = VHCC_ViTri::mot( $CS );
t( 'đọc lại đúng bán kính', $m && 100 === (int) $m['bk'], $m );
t( '🔴 tra tên cơ sở KHÔNG phân biệt hoa thường', null !== VHCC_ViTri::mot( strtolower( $CS ) ) );

/* Nhân viên thường không khai được mốc — mốc là thứ quyết định ai chấm công được. */
$nv = array( 'name' => 'Nhân viên', 'role' => 'NHAN_VIEN', 'coso' => $CS );
$r  = VHCC_ViTri::luu( $nv, $CS, array( 'toaDo' => "$LAT, $LNG", 'cheDo' => VHCC_ViTri::CHAN ) );
t( '🔴 nhân viên không khai được mốc', empty( $r['ok'] ), $r );

/* =============================================================== 4. XÉT — PHÍA KHÔNG ĐƯỢC CHẶN */

/* Đứng ngay tại cửa hàng. */
$x = VHCC_ViTri::xet( $CS, array( 'lat' => $LAT, 'lng' => $LNG, 'acc' => 12 ) );
t( 'đứng tại cửa hàng -> trong vùng', 'trong' === $x['ket'], $x );

/* 🔴 LUẬT 1 — SAI SỐ CỘNG VỀ PHÍA NHÂN VIÊN.
   Cách 130m (quá bán kính 100m) nhưng máy báo ±80m: người ấy CÓ THỂ đang đứng cách 50m, tức
   trong vùng. So thẳng 130 > 100 rồi kết luận "ở ngoài" là vu cho người đứng đúng chỗ. */
$x = VHCC_ViTri::xet( $CS, array( 'lat' => $LAT + 0.00117, 'lng' => $LNG, 'acc' => 80 ) );
t( '🔴 quá bán kính nhưng sai số che được -> KHÔNG kết luận ngoài', 'ngoai' !== $x['ket'], $x );
t( '🔴 và tuyệt đối không chặn', empty( $x['chan'] ), $x );

/* 🔴 LUẬT 2 — SAI SỐ THÔ HƠN NGƯỠNG BẢN ĐỒ THÌ KHÔNG NÓI GÌ.
   Đây đúng con số anh Thắng gặp: giữa Quận 1, ±200km. Cặp toạ độ trông rất thật. */
$x = VHCC_ViTri::xet( $CS, array( 'lat' => 10.7755, 'lng' => 106.7021, 'acc' => 200000 ) );
t( '🔴 sai số ≥ GPS_THO -> không kết luận', 'khong_ro' === $x['ket'], $x );
t( '🔴 và không chặn', empty( $x['chan'] ), $x );

/* 🔴 LUẬT 3 — KHÔNG CÓ TOẠ ĐỘ THÌ KHÔNG BAO GIỜ CHẶN. Cơ sở trong trung tâm thương mại, dưới
   hầm, không bắt được vệ tinh — lỗi nằm ở bê tông chứ không ở người. */
foreach ( array( null, array(), array( 'lat' => null, 'lng' => null ),
	array( 'lat' => '', 'lng' => '' ) ) as $i_g => $g ) {
	$x = VHCC_ViTri::xet( $CS, $g );
	t( '🔴 thiếu toạ độ (' . $i_g . ') -> không chặn', empty( $x['chan'] ), $x );
	t( 'thiếu toạ độ (' . $i_g . ') -> vẫn nói ra trong ghi chú', '' !== $x['chu'], $x );
}

/* =============================================================== 5. XÉT — PHÍA PHẢI BẮT */

/* Cách ~1.1km, GPS tốt (±15m): kể cả cộng sai số vẫn ở ngoài. */
$xa = array( 'lat' => $LAT + 0.01, 'lng' => $LNG, 'acc' => 15 );
$x  = VHCC_ViTri::xet( $CS, $xa );
t( 'cách 1.1km với GPS tốt -> ngoài vùng', 'ngoai' === $x['ket'], $x );
t( 'mức "Chỉ ghi chú" thì KHÔNG chặn', empty( $x['chan'] ), $x );
t( 'nhưng phải dán được câu vào ghi chú', false !== strpos( $x['chu'], 'NGOÀI' ), $x );

/* Chuyển sang Chặn. */
$r = VHCC_ViTri::luu( $admin, $CS, array( 'toaDo' => "$LAT, $LNG", 'bk' => 100, 'cheDo' => VHCC_ViTri::CHAN ) );
t( 'chuyển được sang chế độ Chặn', ! empty( $r['ok'] ), $r );
$x = VHCC_ViTri::xet( $CS, $xa );
t( '🔴 mức Chặn + chắc chắn ở ngoài -> chặn', ! empty( $x['chan'] ), $x );

/* Và ngay ở mức Chặn, ba phía "không chắc" vẫn không được chặn. */
$x = VHCC_ViTri::xet( $CS, array( 'lat' => $LAT + 0.00117, 'lng' => $LNG, 'acc' => 80 ) );
t( '🔴 mức Chặn nhưng sai số che được -> vẫn không chặn', empty( $x['chan'] ), $x );
$x = VHCC_ViTri::xet( $CS, null );
t( '🔴 mức Chặn nhưng thiếu toạ độ -> vẫn không chặn', empty( $x['chan'] ), $x );
$x = VHCC_ViTri::xet( $CS, array( 'lat' => 21.0, 'lng' => 105.8, 'acc' => 5000 ) );
t( '🔴 mức Chặn nhưng sai số thô -> vẫn không chặn', empty( $x['chan'] ), $x );

/* =============================================================== 6. ĐƯỜNG CHẤM CÔNG THẬT */

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'NV_VT', 'ho_ten' => 'Người Thử Vị Trí', 'cua_hang' => $CS,
	'pin_dang_nhap' => '778899', 'trang_thai_lam_viec' => 'Đang làm' ) );
$dn  = VHCC_Tram::dang_nhap( '778899' );
$toi = VHCC_Tram::nguoi( $dn['token'] );
t( 'đăng nhập được để thử đường thật', is_array( $toi ) && ! empty( $toi['ma_nv'] ), $toi );

/* 🔴 LƯỢT BỊ CHẶN KHÔNG ĐƯỢC GHI HÀNG NÀO. Nếu ghi rồi mới chối thì công đã lên bảng, và
   "lượt bị chặn" hoá ra vẫn là công — phải có người đi gỡ, mà không ai biết là phải gỡ. */
$truoc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='NV_VT'" );
$r = VHCC_Online::cham_cong( $toi, '', $xa, $CS, '' );
t( '🔴 chấm ở xa khi đang Chặn -> chối', empty( $r['ok'] ), $r );
$sau = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='NV_VT'" );
t( '🔴 và KHÔNG ghi hàng nào', $truoc === $sau, $truoc . ' -> ' . $sau );
t( 'câu chối nói được cách bao xa', ! empty( $r['error'] ) && false !== strpos( $r['error'], 'cách' ), $r );
t( 'câu chối chỉ được chỗ sửa mốc', ! empty( $r['error'] )
	&& false !== strpos( $r['error'], 'Vị trí cơ sở' ), $r );

/* Đứng đúng chỗ thì ghi bình thường. */
$r = VHCC_Online::cham_cong( $toi, '', array( 'lat' => $LAT, 'lng' => $LNG, 'acc' => 10 ), $CS, '' );
t( 'đứng đúng cơ sở -> ghi được', ! empty( $r['ok'] ), $r );
t( 'trả về kết luận vị trí cho trạm hiện', ! empty( $r['viTri']['gac'] )
	&& 'trong' === $r['viTri']['ket'], isset( $r['viTri'] ) ? $r['viTri'] : null );

/* Ghi chú của hàng phải mang CẢ toạ độ CŨ lẫn kết luận MỚI — bỏ vế nào cũng mất một nửa vết tra. */
$gc = (string) $wpdb->get_var( 'SELECT ghi_chu FROM ' . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='NV_VT' ORDER BY id DESC LIMIT 1" );
t( '🔴 ghi chú giữ nguyên cặp toạ độ như trước', false !== strpos( $gc, 'GPS ' ), $gc );
t( '🔴 và thêm kết luận vị trí', false !== strpos( $gc, 'VỊ TRÍ' ), $gc );

/* 🔴 MÁY CHẤM CÔNG KHÔNG ĐI QUA PHÉP GÁC NÀY. Máy đứng sẵn tại cửa hàng và không có GPS; bắt
   nó qua cửa này là cả cơ sở mất chấm công bằng máy. Đường của máy là VHCC_Nhan::ghi_gio với
   nguồn 'may', không phải VHCC_Online::cham_cong. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-nhan.php' );
t( '🔴 đường ghi của MÁY không gọi bộ gác vị trí', false === strpos( $src, 'VHCC_ViTri' ) );

/* Tắt gác thì mọi thứ trở lại như chưa có bộ này. */
VHCC_ViTri::xoa( $admin, $CS );
$x = VHCC_ViTri::xet( $CS, $xa );
t( 'xoá mốc -> không gác nữa', empty( $x['gac'] ) && empty( $x['chan'] ), $x );
$r = VHCC_Online::cham_cong( $toi, '', $xa, $CS, '' );
t( 'và chấm ở xa lại ghi được', ! empty( $r['ok'] ), $r );

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='NV_VT'" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv='NV_VT'" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — gác vị trí chỉ chặn khi CHẮC CHẮN ở ngoài, không bao giờ chặn vì máy yếu.\n";
