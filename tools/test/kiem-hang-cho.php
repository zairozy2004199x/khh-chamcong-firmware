<?php
/**
 * KIỂM HÀNG ĐỢI KHI MẤT MẠNG — vé giờ đã ký (VHCC_Tram::ve_gio / doc_ve) và lượt gửi lại.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BÀI NÀY CANH ĐÚNG MỘT THỨ: GÁC 1 KHÔNG BỊ NỚI.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * "Giờ lấy ở máy chủ, tuyệt đối không nhận giờ từ điện thoại" — nhận giờ của client là ai cũng
 * tự khai mình đến từ 8 giờ sáng. Hàng đợi offline thì lại BUỘC ghi một giờ đã trôi qua, nên nó
 * là chỗ duy nhất trong cả hệ có thể phá luật ấy mà nhìn vẫn như đang giữ luật.
 *
 * Vé giờ là cách hai điều ấy sống chung: con số do CHÍNH MÁY CHỦ phát ra và ký, điện thoại chỉ
 * chuyển lại. Nên bài này thử đúng những cách một người muốn gian sẽ thử:
 *
 *   · sửa mốc trong vé          · đổi chữ ký          · dùng vé của người khác
 *   · khai độ trôi âm để lùi giờ · khai độ trôi khổng lồ để nhảy tới tương lai
 *   · giữ vé qua đêm rồi nộp     · nộp một lượt hai lần
 *
 * Chạy: php tools/test/kiem-hang-cho.php
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

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'HC001', 'ho_ten' => 'Người Mất Mạng', 'cua_hang' => 'HC_SHOP',
	'pin_dang_nhap' => '112233', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'HC002', 'ho_ten' => 'Người Khác', 'cua_hang' => 'HC_SHOP',
	'pin_dang_nhap' => '445566', 'trang_thai_lam_viec' => 'Đang làm' ) );

$dn_a = VHCC_Tram::dang_nhap( '112233' );
$dn_b = VHCC_Tram::dang_nhap( '445566' );
$TK_A = (string) $dn_a['token'];
$TK_B = (string) $dn_b['token'];
$A = VHCC_Tram::nguoi( $TK_A );
t( 'đăng nhập được', is_array( $A ) && 'HC001' === $A['ma_nv'], $A );

/* ================================================================= 1. PHÁT VÉ */

$ve = VHCC_Tram::ve_gio( $TK_A );
t( 'phát được vé cho thẻ phiên', '' !== $ve && 3 === count( explode( '.', $ve ) ), $ve );
t( '🔴 KHÔNG phát vé cho thẻ rỗng (chưa đăng nhập thì chưa chấm)', '' === VHCC_Tram::ve_gio( '' ) );

list( $moc_ve, $han_ve, $ky_ve ) = explode( '.', $ve );
t( 'vé mang mốc là giờ máy chủ', abs( (int) $moc_ve - (int) current_time( 'timestamp' ) ) <= 2, $moc_ve );
t( 'hạn vé đúng ' . VHCC_Tram::VE_HAN . ' giây', (int) $han_ve - (int) $moc_ve === VHCC_Tram::VE_HAN );

/* ================================================================= 2. VÉ THẬT */

$r = VHCC_Tram::doc_ve( $ve, $TK_A, 0 );
t( 'vé thật + trôi 0 -> nhận', ! empty( $r['ok'] ), $r );
t( 'và mốc đúng bằng mốc vé', (int) $r['moc'] === (int) $moc_ve, $r );

/* ================================================================= 3. CÁC LỐI GIAN */

/* Sửa mốc trong vé: lùi hai tiếng để "đến từ 6 giờ". */
$gian = ( (int) $moc_ve - 7200 ) . '.' . $han_ve . '.' . $ky_ve;
$r = VHCC_Tram::doc_ve( $gian, $TK_A, 0 );
t( '🔴 sửa mốc trong vé -> chối', empty( $r['ok'] ), $r );

/* Đổi chữ ký. */
$r = VHCC_Tram::doc_ve( $moc_ve . '.' . $han_ve . '.' . str_repeat( 'a', 32 ), $TK_A, 0 );
t( '🔴 chữ ký bịa -> chối', empty( $r['ok'] ), $r );

/* Nới hạn vé để giữ vé cả ngày. */
$r = VHCC_Tram::doc_ve( $moc_ve . '.' . ( (int) $han_ve + 86400 ) . '.' . $ky_ve, $TK_A, 0 );
t( '🔴 tự nới hạn vé -> chối (hạn nằm trong phần được ký)', empty( $r['ok'] ), $r );

/* 🔴 DÙNG VÉ CỦA NGƯỜI KHÁC. Đây là lối gian rẻ nhất: một người tới sớm xin vé rồi gửi vé cho
   cả nhóm, và cả nhóm "đến từ 6 giờ". Vé buộc vào thẻ phiên nên nó chết ngay. */
$r = VHCC_Tram::doc_ve( $ve, $TK_B, 0 );
t( '🔴 vé của người này KHÔNG dùng được bằng thẻ của người kia', empty( $r['ok'] ), $r );
t( 'và câu chối nói đúng việc phải làm', ! empty( $r['error'] )
	&& false !== strpos( $r['error'], 'đăng nhập lại' ), $r );

/* Vé rỗng / rác. */
foreach ( array( '', 'abc', '1.2', '1.2.3.4', 'x.y.z' ) as $rac ) {
	$r = VHCC_Tram::doc_ve( $rac, $TK_A, 0 );
	t( 'vé rác "' . $rac . '" -> chối', empty( $r['ok'] ), $r );
}

/* ================================================================= 4. ĐỘ TRÔI */

/* Trôi âm: cố lùi giờ về trước lúc phát vé. */
$r = VHCC_Tram::doc_ve( $ve, $TK_A, -999999 );
t( '🔴 khai độ trôi ÂM không lùi được giờ xuống dưới mốc vé',
	! empty( $r['ok'] ) && (int) $r['moc'] >= (int) $moc_ve, $r );

/* Trôi khổng lồ: cố nhảy tới tương lai. */
$r = VHCC_Tram::doc_ve( $ve, $TK_A, 99999999 );
t( '🔴 khai độ trôi khổng lồ không nhảy được tới tương lai',
	! empty( $r['ok'] ) && (int) $r['moc'] <= (int) current_time( 'timestamp' ), $r );
t( 'và bị kéo về trong hạn vé', ! empty( $r['ok'] ) && (int) $r['moc'] <= (int) $han_ve, $r );

/* Trôi vừa phải, trong hạn — vé phải phát từ 10 phút TRƯỚC, không thì mốc+5 phút rơi vào
   tương lai và bị kéo về "bây giờ" (đúng luật, nhưng không thử được phép cộng). */
$moc_10 = (int) current_time( 'timestamp' ) - 600;
$ref_ky = new ReflectionMethod( 'VHCC_Tram', 've_ky' );
$ref_ky->setAccessible( true );
$ve_10  = $moc_10 . '.' . ( $moc_10 + VHCC_Tram::VE_HAN ) . '.'
	. $ref_ky->invoke( null, $moc_10, $moc_10 + VHCC_Tram::VE_HAN, $TK_A );
$r = VHCC_Tram::doc_ve( $ve_10, $TK_A, 5 * 60 * 1000 );
t( 'trôi 5 phút -> mốc cộng đúng 5 phút',
	! empty( $r['ok'] ) && (int) $r['moc'] === $moc_10 + 300, $r );
t( 'và độ trễ báo về là 5 phút còn lại', ! empty( $r['ok'] ) && 300 === (int) $r['cham'], $r );

/* ================================================================= 5. NỘP QUÁ MUỘN */

/* 🔴 Vé phát hôm qua, giữ tới hôm nay. Hạn vé giới hạn giờ KHAI ĐƯỢC; trần GUI_LAI_TOI_DA giới
   hạn việc NỘP MUỘN. Thiếu trần này thì một cái điện thoại để trong ngăn kéo ba ngày sẽ nhả ra
   một lượt chấm cho thứ Hai vào sáng thứ Năm — sửa công của ngày đã chốt, đã duyệt, có khi đã
   tính lương, mà không qua cửa chấm bù nào. */
$xua   = (int) current_time( 'timestamp' ) - ( VHCC_Tram::GUI_LAI_TOI_DA + 3600 );
$ref   = new ReflectionMethod( 'VHCC_Tram', 've_ky' );
$ref->setAccessible( true );
$ve_xua = $xua . '.' . ( $xua + VHCC_Tram::VE_HAN ) . '.' . $ref->invoke( null, $xua, $xua + VHCC_Tram::VE_HAN, $TK_A );
$r = VHCC_Tram::doc_ve( $ve_xua, $TK_A, 0 );
t( '🔴 giữ quá ' . round( VHCC_Tram::GUI_LAI_TOI_DA / 3600 ) . ' giờ -> chối', empty( $r['ok'] ), $r );
t( 'và chỉ sang cửa chấm bù', ! empty( $r['error'] ) && false !== strpos( $r['error'], 'chấm bù' ), $r );

/* Ngay dưới trần thì vẫn nhận. */
$vua   = (int) current_time( 'timestamp' ) - ( VHCC_Tram::GUI_LAI_TOI_DA - 600 );
$ve_vua = $vua . '.' . ( $vua + VHCC_Tram::VE_HAN ) . '.' . $ref->invoke( null, $vua, $vua + VHCC_Tram::VE_HAN, $TK_A );
$r = VHCC_Tram::doc_ve( $ve_vua, $TK_A, 0 );
t( 'ngay dưới trần thì vẫn nhận', ! empty( $r['ok'] ), $r );

/* ================================================================= 6. GHI THẬT */

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='HC001'" );

/* Lượt "bấm lúc 08:00, mạng về lúc 08:40". */
$moc_bam = (int) current_time( 'timestamp' ) - 2400;
$ve_bam  = $moc_bam . '.' . ( $moc_bam + VHCC_Tram::VE_HAN ) . '.'
	. $ref->invoke( null, $moc_bam, $moc_bam + VHCC_Tram::VE_HAN, $TK_A );
$v = VHCC_Tram::doc_ve( $ve_bam, $TK_A, 0 );
t( 'đọc được vé của lượt giữ 40 phút', ! empty( $v['ok'] ), $v );

$r1 = VHCC_Online::cham_cong( $A, '', null, 'HC_SHOP', '', (int) $v['moc'], (int) $v['cham'] );
t( 'lượt gửi lại ghi được', ! empty( $r1['ok'] ), $r1 );
t( '🔴 ghi vào GIỜ ĐÃ BẤM, không phải giờ máy chủ nhận',
	gmdate( 'H:i:s', $moc_bam ) === (string) $r1['gio'], array( $r1['gio'], gmdate( 'H:i:s', $moc_bam ) ) );
t( 'và tự khai ra là lượt gửi lại', ! empty( $r1['guiLai'] ), $r1 );

$hang = $wpdb->get_row( 'SELECT gio_vao_giay, gio_ra_giay, ghi_chu FROM ' . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='HC001' ORDER BY id DESC LIMIT 1", ARRAY_A );
t( '🔴 ghi chú nói rõ là gửi lại sau khi mất mạng',
	false !== strpos( (string) $hang['ghi_chu'], 'GỬI LẠI SAU KHI MẤT MẠNG' ), $hang );
t( 'ghi chú có cả giờ bấm lẫn giờ nhận', false !== strpos( (string) $hang['ghi_chu'], 'bấm lúc' )
	&& false !== strpos( (string) $hang['ghi_chu'], 'nhận lúc' ), $hang );

/* ═════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHÉP THỬ QUAN TRỌNG NHẤT CỦA CẢ BÀI: GỬI LẠI HAI LẦN KHÔNG ĐẺ RA GIỜ RA GIẢ.
 *
 * Hàng đợi chỉ an toàn được vì lượt gửi lại mang ĐÚNG TỪNG GIÂY con số của lượt đầu, và
 * `VHCC_Nhan::quyet_dinh_gio()` nhận ra nó là 'trung' rồi bỏ qua. Nếu lượt đầu ghi bằng giờ
 * máy chủ lúc NHẬN còn lượt gửi lại ghi bằng giờ lúc BẤM thì hai con số lệch vài giây, lượt
 * thứ hai rơi vào nhánh 'ra' — và hàng ấy thành một ca dài 0 phút. Bảng công thấy đã đủ cặp
 * giờ vào/giờ ra nên KHÔNG báo thiếu, người đó mất trọn một ngày công, và không dòng đỏ nào.
 *
 * Đó là lý do mọi lượt chấm của trạm — kể cả lượt online — đều đi qua vé.
 * ═════════════════════════════════════════════════════════════════════════════════════════ */
$r2 = VHCC_Online::cham_cong( $A, '', null, 'HC_SHOP', '', (int) $v['moc'], (int) $v['cham'] );
$hang2 = $wpdb->get_row( 'SELECT gio_vao_giay, gio_ra_giay FROM ' . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='HC001' ORDER BY id DESC LIMIT 1", ARRAY_A );
t( '🔴 gửi lại lần hai -> máy chủ coi là TRÙNG', 'trung' === (string) $r2['loai'], $r2 );
t( '🔴 và KHÔNG đẻ ra giờ ra', null === $hang2['gio_ra_giay'] || '' === (string) $hang2['gio_ra_giay'], $hang2 );
t( 'giờ vào không đổi', (string) $hang['gio_vao_giay'] === (string) $hang2['gio_vao_giay'], array( $hang, $hang2 ) );

$so_hang = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='HC001'" );
t( 'hai lượt gửi lại vẫn chỉ một hàng', 1 === $so_hang, $so_hang );

/* Và lượt chấm ra thật buổi chiều vẫn vào bình thường. */
$moc_ra = $moc_bam + 8 * 3600;
if ( $moc_ra <= (int) current_time( 'timestamp' ) ) {
	$r3 = VHCC_Online::cham_cong( $A, '', null, 'HC_SHOP', '', $moc_ra, 0 );
	t( 'chấm ra buổi chiều vẫn vào đúng ô giờ ra', ! empty( $r3['ok'] ) && 'ra' === (string) $r3['loai'], $r3 );
}

/* Lượt KHÔNG có vé (đường online cũ) vẫn chạy y như trước — không lượt nào bị bộ này chặn. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv='HC001'" );
$r4 = VHCC_Online::cham_cong( $A, '', null, 'HC_SHOP', '' );
t( 'lượt không vé vẫn ghi bình thường', ! empty( $r4['ok'] ), $r4 );
t( 'và KHÔNG bị đánh dấu gửi lại', empty( $r4['guiLai'] ), $r4 );
t( 'giờ của nó là giờ máy chủ bây giờ',
	(string) $r4['gio'] === (string) current_time( 'H:i:s' ), $r4 );

/* ================================================================= 7. MÀN TRÊN ĐIỆN THOẠI */

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( 'có ô "Chờ gửi"', false !== strpos( $tpl, 'id="oHangCho"' ) );
t( 'có nút thử gửi ngay', false !== strpos( $tpl, 'id="btDayHang"' ) );
t( 'nghe sự kiện có sóng lại', false !== strpos( $tpl, "addEventListener('online'" ) );

/* 🔴 CHỈ XẾP HÀNG TỪ NHÁNH "HỎI LẠI CŨNG KHÔNG ĐƯỢC". Xếp ngay khi lượt gọi hỏng là mời một
   lượt ghi thứ hai: quá hạn thì rất có thể ảnh đã tới nơi rồi. */
$i = strpos( $tpl, 'function soatLaiDaGhi' );
$j = strpos( $tpl, 'var KHOA_HANG' );
$khoi = ( false !== $i && false !== $j && $j > $i ) ? substr( $tpl, $i, $j - $i ) : '';
t( 'cắt được khối soatLaiDaGhi', '' !== $khoi );
/* Đếm LỜI GỌI, không đếm chỗ khai hàm — `function xepHang(goiCham)` cũng khớp chuỗi ấy. */
$so_goi = substr_count( $tpl, 'xepHang(goiCham)' ) - substr_count( $tpl, 'function xepHang(goiCham)' );
t( '🔴 xepHang() chỉ được gọi từ MỘT chỗ, và chỗ ấy là soatLaiDaGhi',
	1 === $so_goi && false !== strpos( $khoi, 'xepHang(goiCham)' ), $so_goi );

/* Độ trôi đóng băng lúc bấm, KHÔNG đo lại lúc gửi — đo lại là ghi giờ lúc bắt được sóng. */
t( '🔴 độ trôi đo một lần lúc bấm LƯU', false !== strpos( $tpl, 'var goiCham = {' )
	&& false !== strpos( $tpl, 'troi:  MOC ? Math.max(0, Math.round(performance.now() - MOC.tuLuc)) : 0' ) );
$i2 = strpos( $tpl, 'function dayHang' );
$khoi2 = ( false !== $i2 ) ? substr( $tpl, $i2, 2200 ) : '';
t( '🔴 lúc gửi lại KHÔNG đo lại performance.now()', false === strpos( $khoi2, 'performance.now()' ), $khoi2 );
t( 'và gửi nguyên gói đã lưu', false !== strpos( $khoi2, "goi('cham', mot" ), $khoi2 );

/* Hàng đợi không được giữ một lượt vô hạn: máy chủ chối thì bỏ và nói ra. */
t( '🔴 máy chủ chối thì bỏ khỏi hàng và nói ra',
	false !== strpos( $khoi2, 'đã bỏ khỏi hàng chờ' ), $khoi2 );
t( 'mất mạng thì GIỮ nguyên hàng', false !== strpos( $khoi2, 'Vẫn chưa có sóng' ), $khoi2 );

/* Trần hàng đợi — ảnh base64 ăn localStorage rất nhanh. */
t( 'có trần số lượt giữ trong máy', false !== strpos( $tpl, 'HANG_TOI_DA = 3' ) );
t( '🔴 ghi localStorage có bọc try/catch (đầy kho thì setItem NÉM lỗi)',
	false !== strpos( $tpl, 'try { localStorage.setItem(KHOA_HANG' ) );

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE ma_nv IN ('HC001','HC002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE ma_nv IN ('HC001','HC002')" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — lượt gửi lại mang giờ của MÁY CHỦ, và gửi hai lần không đẻ giờ ra giả.\n";
