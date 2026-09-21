<?php
/**
 * KIỂM "LỊCH LÀM CỦA TÔI" trên trạm chấm công (VHCC_Lich::lich_cua_nguoi + cửa `viec=lichtoi`).
 *
 * 🔴 CANH ĐÚNG MỘT THỨ TRÊN HẾT: MỘT NHÂN VIÊN CHỈ ĐƯỢC THẤY LỊCH CỦA CHÍNH MÌNH.
 *
 *    Lịch của cả cơ sở là dữ liệu của người khác: ai nghỉ ngày nào, ai làm ca đêm, ai bị xếp ít
 *    ca hẳn đi trong tháng. Cách hỏng dễ nhất — và không ai nhìn thấy trên màn hình — là lấy cả
 *    lịch cơ sở rồi lọc ở trình duyệt: màn chỉ hiện dòng của mình, nhưng lượt trả về mang theo
 *    cả bảng, và mở công cụ nhà phát triển ra là đọc được hết.
 *
 *    Nên bài này không kiểm "màn hiện đúng mấy dòng". Nó kiểm rằng HÀM ĐỌC không bao giờ TRẢ VỀ
 *    dòng của người khác, và rằng phép lọc nằm trong câu SQL.
 *
 * Chạy: php tools/test/kiem-lich-toi.php
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

$T = VHCC_DB::t( 'lich_cv' );
$wpdb->insert( $T, array( 'coso' => 'LT_A', 'ma_nv' => 'LT001', 'ho_ten' => 'Tôi',
	'ngay' => '2026-09-03', 'ca' => 'Sáng', 'viec' => 'Trực quầy' ) );
$wpdb->insert( $T, array( 'coso' => 'LT_A', 'ma_nv' => 'LT001', 'ho_ten' => 'Tôi',
	'ngay' => '2026-09-17', 'ca' => 'Chiều', 'viec' => 'Thu ngân' ) );
/* Cùng người, cơ sở KHÁC — người làm hai nơi. */
$wpdb->insert( $T, array( 'coso' => 'LT_B', 'ma_nv' => 'LT001', 'ho_ten' => 'Tôi',
	'ngay' => '2026-09-20', 'ca' => 'Tối', 'viec' => 'Trực ghế' ) );
/* Tháng khác. */
$wpdb->insert( $T, array( 'coso' => 'LT_A', 'ma_nv' => 'LT001', 'ho_ten' => 'Tôi',
	'ngay' => '2026-10-02', 'ca' => 'Sáng', 'viec' => 'Trực quầy' ) );
/* ĐỒNG NGHIỆP, cùng cơ sở, cùng tháng — đây là dòng KHÔNG được lọt ra. */
$wpdb->insert( $T, array( 'coso' => 'LT_A', 'ma_nv' => 'LT002', 'ho_ten' => 'Người Khác',
	'ngay' => '2026-09-17', 'ca' => 'Sáng', 'viec' => 'Nghỉ phép' ) );

/* ═══════════════════════════════════════════════════════════════ 1. CHỈ LỊCH CỦA MÌNH */

$ds = VHCC_Lich::lich_cua_nguoi( 'LT001', '2026-09-01', '2026-09-30' );
t( 'đọc được lịch tháng 9 của mình', 3 === count( $ds ), $ds );
foreach ( $ds as $x ) {
	t( '🔴 không dòng nào của người khác lọt ra',
		false === strpos( wp_json_encode( $x ), 'Nghỉ phép' ), $x );
}

/* 🔴 NGƯỜI LÀM HAI NƠI THẤY CẢ HAI. Lọc thêm theo cơ sở là người chạy hai cửa hàng mất một nửa
   lịch, mà nửa mất đi thì không có dấu hiệu nào — bảng vẫn có dòng, chỉ thiếu. */
$cs = array();
foreach ( $ds as $x ) { $cs[ $x['coso'] ] = 1; }
t( '🔴 người làm hai cơ sở thấy lịch CẢ HAI', 2 === count( $cs ), array_keys( $cs ) );

/* Khoảng ngày cắt đúng — tháng 10 không lẫn vào tháng 9. */
foreach ( $ds as $x ) {
	t( 'không lẫn ngày ngoài khoảng', 0 === strpos( (string) $x['ngay'], '2026-09' ), $x );
}
$ds10 = VHCC_Lich::lich_cua_nguoi( 'LT001', '2026-10-01', '2026-10-31' );
t( 'lật sang tháng 10 ra đúng một dòng', 1 === count( $ds10 ), $ds10 );

/* Người chưa có lịch -> rỗng, không nổ. */
t( 'người chưa xếp lịch -> mảng rỗng',
	array() === VHCC_Lich::lich_cua_nguoi( 'LT999', '2026-09-01', '2026-09-30' ) );
t( 'mã NV rỗng -> mảng rỗng', array() === VHCC_Lich::lich_cua_nguoi( '', '2026-09-01', '2026-09-30' ) );

/* Ngày gõ bậy -> rỗng, KHÔNG đi vào câu SQL. */
t( 'ngày sai khuôn -> rỗng',
	array() === VHCC_Lich::lich_cua_nguoi( 'LT001', 'hôm qua', '2026-09-30' ) );
t( 'ngày sai khuôn ở đầu kia -> rỗng',
	array() === VHCC_Lich::lich_cua_nguoi( 'LT001', '2026-09-01', "2026-09-30' OR '1'='1" ) );

/* ═══════════════════════════════════════════════════════════ 2. LỌC NẰM Ở CÂU SQL */

$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-lich.php' );
$i = strpos( $src, 'function lich_cua_nguoi' );
$j = strpos( $src, 'function co_bat_lich' );
$khoi = ( false !== $i && false !== $j && $j > $i ) ? substr( $src, $i, $j - $i ) : '';
t( 'cắt được khối lich_cua_nguoi', '' !== $khoi );
/* 🔴 `WHERE ma_nv=%s` PHẢI NẰM TRONG CÂU SQL. Lấy hết rồi lọc bằng PHP thì vẫn đúng trên màn
   hình, nhưng nó đã đọc cả bảng lên bộ nhớ — và bước tiếp theo của một người sửa vội là trả
   luôn cả mảng ấy về trình duyệt. */
t( '🔴 lọc theo mã NV nằm trong câu SQL', false !== strpos( $khoi, 'WHERE ma_nv=%s' ), $khoi );
t( 'và khoảng ngày cũng ở trong câu SQL', false !== strpos( $khoi, 'ngay BETWEEN %s AND %s' ), $khoi );
t( 'dùng prepare, không nối chuỗi', false !== strpos( $khoi, '$wpdb->prepare(' ), $khoi );
/* Không lọc theo cơ sở — xem phép thử "người làm hai nơi" ở trên. */
t( '🔴 KHÔNG lọc theo cơ sở', false === strpos( $khoi, 'coso=%s' ), $khoi );

/* ═══════════════════════════════════════════════════════════════ 3. CỬA TRÊN TRẠM */

$src_tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( 'cửa lichtoi có thật', false !== strpos( $src_tram, "'lichtoi' === \$viec" ) );

$i2 = strpos( $src_tram, "'lichtoi' === \$viec" );
$j2 = strpos( $src_tram, "'xintre' === \$viec" );
$khoi2 = ( false !== $i2 && false !== $j2 && $j2 > $i2 ) ? substr( $src_tram, $i2, $j2 - $i2 ) : '';
t( '🔴 cửa lấy mã NV từ PHIÊN', false !== strpos( $khoi2, "\$u['ma_nv']" ), $khoi2 );
t( "🔴 và KHÔNG đọc ma_nv từ thân yêu cầu", false === strpos( $khoi2, "\$b['ma_nv']" ), $khoi2 );
/* Tháng gõ bậy thì lui về tháng này, không chối — người dùng không gõ tháng, màn gửi nó. */
t( 'tháng sai khuôn -> lui về tháng này', false !== strpos( $khoi2, "current_time( 'Y-m' )" ), $khoi2 );

$chot = strpos( $src_tram, "'het_phien'" );
t( '🔴 cửa nằm SAU chốt thẻ phiên', false !== $chot && $i2 > $chot );

/* ═══════════════════════════════════════════════════════════════ 4. MÀN TRÊN ĐIỆN THOẠI */

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( 'có bảng lịch', false !== strpos( $tpl, 'id="bangLichToi"' ) );

/* 🔴 NẰM TRONG TAB "CÔNG CỦA TÔI", và DÙNG CHUNG bộ lật tháng với bảng công. Hai bộ lật riêng
   thì người ta lật cái này quên cái kia, rồi đọc lịch tháng 9 cạnh công tháng 8 mà không thấy
   gì sai — hai bảng số nằm cạnh nhau của hai tháng khác nhau là thứ không ai kiểm được bằng mắt. */
$i3 = strpos( $tpl, 'id="tCong"' );
$i4 = strpos( $tpl, 'id="bangLichToi"' );
$i5 = strpos( $tpl, '<!-- /tCong -->' );
t( '🔴 bảng lịch nằm trong tab Công của tôi',
	false !== $i3 && false !== $i4 && false !== $i5 && $i3 < $i4 && $i4 < $i5, array( $i3, $i4, $i5 ) );
t( '🔴 dùng CHUNG bộ lật tháng, không đẻ bộ thứ hai',
	false !== strpos( $tpl, 'veLichToi(ym);' )
	&& 1 === substr_count( $tpl, "id=\"btThangTruoc\"" ), substr_count( $tpl, 'id="btThangTruoc"' ) );
/* Lịch đặt TRÊN bảng công: lịch nói việc sắp tới, bảng công nói việc đã qua. */
t( 'lịch đặt trên bảng công', $i4 < strpos( $tpl, 'id="oKhoiCong"' ) );

/* 🔴 RỖNG THÌ NÓI "CHƯA XẾP", đừng để một ô trống — ô trống thì ai cũng đọc thành "hỏng rồi". */
t( '🔴 rỗng thì nói rõ là chưa xếp lịch',
	false !== mb_strpos( $tpl, 'chưa xếp lịch cho anh/chị' ) );
t( '   và nói luôn rằng cơ sở không dùng phân lịch là bình thường',
	false !== mb_strpos( $tpl, 'không phải lỗi' ) );
t( 'có đánh dấu hôm nay', false !== strpos( $tpl, "x.ngay === j.homNay" ) );

$wpdb->query( "DELETE FROM $T WHERE ma_nv IN ('LT001','LT002')" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — mỗi người chỉ đọc được lịch của chính mình, và phép lọc nằm ở câu SQL.\n";
