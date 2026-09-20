<?php
/**
 * KIỂM "VỊ TRÍ LÚC CHẤM VÀO / LÚC CHẤM RA" VÀ TRUY VẾT CƠ SỞ.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TRƯỚC BẢN NÀY HỆ CHỈ NHỚ ĐƯỢC MỘT CHỖ ĐỨNG CHO CẢ NGÀY.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Toạ độ đi chung vào cột `ghi_chu` — MỘT ô VARCHAR cho cả hàng chấm công. Lượt chấm vào ghi cặp
 * số xuống đó; lượt chấm ra vài tiếng sau ghi đè lên; và chỗ đứng lúc vào biến mất, IM LẶNG. Nhìn
 * bảng thì vẫn thấy một dòng GPS đầy đủ, nên không ai ngờ là đã mất một nửa.
 *
 * Mà đúng cái nửa bị mất ấy mới nói lên chuyện: bấm vào tại cửa hàng rồi bấm ra ở cách đó 3km là
 * thứ phải nhìn thấy được. Một dòng toạ độ đơn lẻ không bao giờ nói được điều đó.
 *
 * Anh Thắng 20/09/2026: *"Như chấm vào. Chấm ra"* · *"khi nhân viên đi qua cơ sở khác, chấm báo
 * cáo cơ sở. Hệ thống tự truy vết định vị"*.
 *
 * Bài này canh bốn chỗ dễ hỏng:
 *   1. Hai cột `vt_vao` / `vt_ra` KHÔNG ĐƯỢC ĐÈ NHAU — lượt ra không xoá chỗ đứng lúc vào.
 *   2. Lượt KHÔNG CÓ toạ độ (máy vân tay, nạp .csv) không được ghi chuỗi rỗng đè lên cột đã có.
 *   3. Nhánh `daoThuTu` phải KÉO THEO toạ độ y như nó kéo theo ảnh.
 *   4. Truy vết phải chạy KỂ CẢ khi cơ sở đang chấm chưa khai mốc / đang tắt gác.
 *
 * Chạy: php tools/test/kiem-vet-dinh-vi.php
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

/* Hai cơ sở CÁCH NHAU 900m — đủ xa để mốc không chồng lên nhau, đủ gần để đúng là tình huống
   thật anh Thắng nói: nhân viên đi qua cơ sở kia trong cùng một buổi. */
$CS_A = 'VET_SHOP_A';
$CS_B = 'VET_SHOP_B';
$A_LAT = 10.775500; $A_LNG = 106.702100;
$B_LAT = 10.783600; $B_LNG = 106.702100;   /* ~900m về phía bắc */

$admin = array( 'name' => 'Sếp', 'role' => 'ADMIN', 'coso' => '' );

$r = VHCC_ViTri::luu( $admin, $CS_A, array( 'toaDo' => "$A_LAT, $A_LNG", 'bk' => 100,
	'cheDo' => VHCC_ViTri::GHI ) );
t( 'khai được mốc cơ sở A', ! empty( $r['ok'] ), $r );
$r = VHCC_ViTri::luu( $admin, $CS_B, array( 'toaDo' => "$B_LAT, $B_LNG", 'bk' => 100,
	'cheDo' => VHCC_ViTri::TAT ) );
t( 'khai được mốc cơ sở B (gác đang TẮT)', ! empty( $r['ok'] ), $r );

t( 'hai mốc cách nhau ~900m',
	abs( VHCC_ViTri::khoang_cach( $A_LAT, $A_LNG, $B_LAT, $B_LNG ) - 900 ) < 60,
	VHCC_ViTri::khoang_cach( $A_LAT, $A_LNG, $B_LAT, $B_LNG ) );

/* ═════════════════════════════════════════════════════════════════════ 1. TRUY VẾT */

$o_b = array( 'lat' => $B_LAT, 'lng' => $B_LNG, 'acc' => 15 );

$v = VHCC_ViTri::gan_nhat( $o_b, $CS_A );
t( 'đứng ở B mà chấm về A -> truy ra cơ sở B', $v && $CS_B === $v['coSo'], $v );
t( '   và nói rõ là NẰM TRONG vùng của B', $v && ! empty( $v['trong'] ), $v );

/* 🔴 GÁC CỦA B ĐANG TẮT MÀ VẪN TRUY VẾT ĐƯỢC. Lọc theo `cheDo` thì chuỗi nào cũng có vài cơ sở
   khai mốc mà chưa dám bật, và đúng mấy nơi ấy lại là nơi cần truy vết nhất. */
t( '🔴 mốc của B để chế độ TẮT vẫn truy vết được', $v && $CS_B === $v['coSo'], $v );

/* Trừ chính cơ sở đang chấm — nếu không thì lượt nào cũng "truy ra" chính nó. */
$v = VHCC_ViTri::gan_nhat( array( 'lat' => $A_LAT, 'lng' => $A_LNG, 'acc' => 10 ), $CS_A );
t( '🔴 đứng đúng cơ sở A -> KHÔNG truy ra chính A', ! $v || $CS_A !== $v['coSo'], $v );

/* Luật 2 vẫn áp: sai số thô thì không kết luận. Hai cơ sở cùng quận là chuyện thường. */
t( '🔴 sai số thô -> KHÔNG truy vết (không vu cho người đứng đúng chỗ)',
	null === VHCC_ViTri::gan_nhat( array( 'lat' => $B_LAT, 'lng' => $B_LNG, 'acc' => 3000 ), $CS_A ) );
t( 'thiếu toạ độ -> không truy vết', null === VHCC_ViTri::gan_nhat( null, $CS_A ) );

/* Ở giữa hai cơ sở: truy ra cái gần hơn, nhưng KHÔNG nói là "đang ở trong vùng". */
$v = VHCC_ViTri::gan_nhat( array( 'lat' => ( $A_LAT + $B_LAT ) / 2, 'lng' => $A_LNG, 'acc' => 15 ), $CS_A );
t( 'đứng giữa đường -> vẫn chỉ ra cơ sở gần nhất', $v && $CS_B === $v['coSo'], $v );
t( '🔴 nhưng KHÔNG khẳng định là đang ở trong vùng cơ sở ấy', $v && empty( $v['trong'] ), $v );

/* ════════════════════════════════════════════════════ 2. DÒNG CẤT VÀO CỘT, ĐỌC LẠI ĐƯỢC */

$xet = VHCC_ViTri::xet( $CS_A, $o_b );
$d   = VHCC_ViTri::dong( $o_b, $xet, VHCC_ViTri::gan_nhat( $o_b, $CS_A ) );
t( 'dòng vị trí dựng được', '' !== $d, $d );
t( '🔴 dòng vừa trong giới hạn cột (160 ký tự)', strlen( $d ) <= 160, strlen( $d ) . ': ' . $d );

$b = VHCC_ViTri::doc_dong( $d );
t( 'đọc lại ra đúng vĩ độ', $b && abs( $b['lat'] - $B_LAT ) < 0.000002, $b );
t( 'đọc lại ra đúng kinh độ', $b && abs( $b['lng'] - $B_LNG ) < 0.000002, $b );
t( 'đọc lại ra sai số', $b && 15 === $b['acc'], $b );
t( 'đọc lại ra cơ sở truy vết', $b && $CS_B === $b['coSoKhac'], $b );
t( 'đọc lại ra cờ "nằm trong vùng cơ sở ấy"', $b && ! empty( $b['trongCoSoKhac'] ), $b );

t( 'dòng rỗng -> null, không ném', null === VHCC_ViTri::doc_dong( '' ) );
t( 'dòng rác -> null, không ném', null === VHCC_ViTri::doc_dong( 'xin chào' ) );
t( 'dòng cụt -> null, không ném', null === VHCC_ViTri::doc_dong( '10.7755' ) );
t( 'cặp 0,0 -> null (cái bẫy vịnh Guinea)', null === VHCC_ViTri::doc_dong( '0|0|10|5|trong|' ) );
t( 'thiếu toạ độ -> dòng rỗng', '' === VHCC_ViTri::dong( null, null, null ) );

/* ⚠️ Tên cơ sở có dấu `|` là dựng ra dòng bảy ô và đọc lệch từ ô thứ sáu. */
$d_ong = VHCC_ViTri::dong( $o_b, null, array( 'coSo' => 'CS|LẠ', 'met' => 10, 'trong' => false ) );
$b_ong = VHCC_ViTri::doc_dong( $d_ong );
t( '🔴 tên cơ sở có dấu gạch đứng KHÔNG làm lệch dòng',
	$b_ong && false === mb_strpos( (string) $b_ong['coSoKhac'], '|' )
	&& abs( $b_ong['lat'] - $B_LAT ) < 0.000002, array( $d_ong, $b_ong ) );

/* 🔴 CƠ SỞ GẦN NHẤT MÀ Ở TÍT ĐÂU THÌ KHÔNG ĐƯỢC CẤT XUỐNG CỘT. Đứng đúng cơ sở A, cơ sở gần
   nhất tiếp theo là B cách 900m — nó không nói lên điều gì, và cất vào là biến cột bằng chứng
   thành danh bạ chuỗi cửa hàng. Người soi bảng đọc thấy tên lạ trong một ô hoàn toàn bình
   thường rồi đi hỏi một câu không có gì để hỏi. */
$o_a   = array( 'lat' => $A_LAT, 'lng' => $A_LNG, 'acc' => 12 );
$d_dung = VHCC_ViTri::dong( $o_a, VHCC_ViTri::xet( $CS_A, $o_a ), VHCC_ViTri::gan_nhat( $o_a, $CS_A ) );
$b_dung = VHCC_ViTri::doc_dong( $d_dung );
t( '🔴 đứng đúng cơ sở -> KHÔNG cất tên cơ sở gần nhất vào cột',
	$b_dung && '' === $b_dung['coSoKhac'], array( $d_dung, $b_dung ) );
t( '   nhưng vẫn cất kết luận "trong vùng"', $b_dung && 'trong' === $b_dung['ket'], $b_dung );

t( 'câu tiếng Việt nói được cơ sở truy vết',
	false !== mb_strpos( VHCC_ViTri::chu_dong( $d ), $CS_B ), VHCC_ViTri::chu_dong( $d ) );

/* ══════════════════════════════════════════════════ 3. HAI CỘT, ĐƯỜNG CHẤM CÔNG THẬT */

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'NV_VET', 'ho_ten' => 'Người Đi Cơ Sở Khác', 'cua_hang' => $CS_A,
	'pin_dang_nhap' => '660011', 'trang_thai_lam_viec' => 'Đang làm' ) );
$toi = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '660011' )['token'] );
t( 'đăng nhập được', is_array( $toi ) && 'NV_VET' === $toi['ma_nv'], $toi );

$bang = VHCC_DB::t( 'cham_cong' );
$hang = function () use ( $wpdb, $bang ) {
	return $wpdb->get_row( 'SELECT * FROM ' . $bang . " WHERE ma_nv='NV_VET' ORDER BY id DESC LIMIT 1", ARRAY_A );
};

/* 🔴 ĐẶT GIỜ MÁY CHỦ CHO TỪNG LƯỢT. Chấm công lấy giờ ở máy chủ, và hai lời gọi liền nhau rơi
   vào CÙNG MỘT GIÂY -> `quyet_dinh_gio()` trả 'trung' và lượt thứ hai không ghi gì. Không đặt
   giờ thì bài này thử được mỗi lượt chấm vào, còn toàn bộ chuyện "chấm ra có xoá chỗ đứng lúc
   vào không" — tức là cái nó sinh ra để canh — im lặng trôi qua. */
vhcp_test_dat_gio( '2026-09-18 08:05:00' );

/* --- CHẤM VÀO: đứng đúng cơ sở A --- */
$r = VHCC_Online::cham_cong( $toi, '', array( 'lat' => $A_LAT, 'lng' => $A_LNG, 'acc' => 12 ), $CS_A, '' );
t( 'chấm vào được', ! empty( $r['ok'] ) && 'vao' === $r['loai'], $r );
$h = $hang();
t( 'cột vt_vao có toạ độ', '' !== (string) $h['vt_vao'], $h );
t( 'cột vt_ra còn trống', '' === (string) $h['vt_ra'], $h );
$vao_luu = (string) $h['vt_vao'];
$bv = VHCC_ViTri::doc_dong( $vao_luu );
t( 'vt_vao kết luận là TRONG vùng', $bv && 'trong' === $bv['ket'], $bv );

/* --- CHẤM RA: đã đi sang cơ sở B --- */
vhcp_test_dat_gio( '2026-09-18 17:20:00' );
$r = VHCC_Online::cham_cong( $toi, '', $o_b, $CS_A, '' );
t( 'chấm ra được (gác chỉ GHI, không chặn)', ! empty( $r['ok'] ) && 'ra' === $r['loai'], $r );
$h = $hang();

/* 🔴 ĐÂY LÀ PHÉP CHÍNH CỦA CẢ BÀI. Trước bản này lượt ra ghi đè lên chỗ đứng lúc vào. */
t( '🔴 chấm ra KHÔNG xoá chỗ đứng lúc chấm vào', $vao_luu === (string) $h['vt_vao'],
	array( 'trước' => $vao_luu, 'sau' => $h['vt_vao'] ) );
t( 'cột vt_ra nay có toạ độ', '' !== (string) $h['vt_ra'], $h );
t( '🔴 hai đầu là hai chỗ đứng KHÁC NHAU', (string) $h['vt_vao'] !== (string) $h['vt_ra'], $h );

$br = VHCC_ViTri::doc_dong( (string) $h['vt_ra'] );
t( 'vt_ra kết luận là NGOÀI vùng cơ sở A', $br && 'ngoai' === $br['ket'], $br );
t( '🔴 vt_ra truy ra đúng cơ sở B', $br && $CS_B === $br['coSoKhac'], $br );
t( '   và nói là đang nằm TRONG vùng của B', $br && ! empty( $br['trongCoSoKhac'] ), $br );

t( 'lượt chấm trả về phần truy vết cho trạm nói với người vừa bấm',
	! empty( $r['vet']['coSo'] ) && $CS_B === $r['vet']['coSo'], isset( $r['vet'] ) ? $r['vet'] : null );

/* ══════════════════════════════════ 4. LƯỢT KHÔNG CÓ TOẠ ĐỘ KHÔNG ĐƯỢC XOÁ CỘT ĐÃ CÓ */

/* 🔴 Máy vân tay và tệp .csv nạp về đi qua đúng `ghi_gio()` này mà không mang toạ độ. Cho chúng
   ghi chuỗi rỗng đè lên là một lượt online buổi sáng có vị trí, lô đồng bộ buổi tối xoá mất —
   và xoá im lặng, vì giờ công thì vẫn y nguyên. */
$vao_g = (string) $hang()['vt_vao'];
$ra_g  = (string) $hang()['vt_ra'];
VHCC_Nhan::ghi_gio( $CS_A, '2026-09-18', 'NV_VET', 'Người Đi Cơ Sở Khác',
	23 * 3600, '', 'may' );
$h = $hang();
t( '🔴 lượt máy (không toạ độ) KHÔNG xoá vt_vao', $vao_g === (string) $h['vt_vao'], $h );
t( '🔴 lượt máy (không toạ độ) KHÔNG xoá vt_ra', '' !== (string) $h['vt_ra'], $h );

/* ════════════════════════════════════════════ 5. NHÁNH ĐẢO THỨ TỰ KÉO THEO CẢ TOẠ ĐỘ */

/* Lượt mới SỚM HƠN giờ vào -> nó thành giờ vào, còn giờ vào cũ tụt xuống làm giờ ra. Toạ độ
   phải đi theo con số của nó, y như ảnh. Bỏ sót chỗ này thì `vt_ra` mang toạ độ trống cho một
   giờ ra CÓ THẬT, và chỗ đứng của lượt cũ mất hẳn. */
$NG = '2026-09-15';
$wpdb->query( 'DELETE FROM ' . $bang . " WHERE ma_nv='NV_DAO'" );
$d_som = VHCC_ViTri::dong( array( 'lat' => $A_LAT, 'lng' => $A_LNG, 'acc' => 11 ), null, null );
$d_cu  = VHCC_ViTri::dong( $o_b, null, array( 'coSo' => $CS_B, 'met' => 20, 'trong' => true ) );

VHCC_Nhan::ghi_gio( $CS_A, $NG, 'NV_DAO', 'Người Đảo', 9 * 3600, '', 'online', '', $d_cu );
$h = $wpdb->get_row( 'SELECT * FROM ' . $bang . " WHERE ma_nv='NV_DAO' AND ngay='$NG'", ARRAY_A );
t( 'đặt nền: lượt 09:00 nằm ở vt_vao', $d_cu === (string) $h['vt_vao'], $h );

VHCC_Nhan::ghi_gio( $CS_A, $NG, 'NV_DAO', 'Người Đảo', 7 * 3600, '', 'online', '', $d_som );
$h = $wpdb->get_row( 'SELECT * FROM ' . $bang . " WHERE ma_nv='NV_DAO' AND ngay='$NG'", ARRAY_A );
t( 'giờ vào nay là 07:00', 7 * 3600 === (int) $h['gio_vao_giay'], $h );
t( 'giờ ra nay là 09:00 (lượt cũ tụt xuống)', 9 * 3600 === (int) $h['gio_ra_giay'], $h );
t( '🔴 toạ độ lượt 07:00 vào ô vt_vao', $d_som === (string) $h['vt_vao'], $h );
t( '🔴 toạ độ lượt 09:00 ĐI THEO nó xuống ô vt_ra', $d_cu === (string) $h['vt_ra'], $h );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — chấm vào và chấm ra nhớ riêng chỗ đứng, và lượt chấm tự truy ra cơ sở.\n";
