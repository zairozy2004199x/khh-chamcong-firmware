<?php
/**
 * KIỂM "TOẠ ĐỘ -> ĐỊA CHỈ CỤ THỂ" — anh Thắng 20/09/2026: *"chỗ định vị có chèn được địa chỉ cụ
 * thể nơi đứng không"*.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHỖ HỎNG CỦA TÍNH NĂNG NÀY KHÔNG NẰM Ở VIỆC TRA ĐƯỢC HAY KHÔNG.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Tra một cặp số ra tên đường là mười dòng mã và chạy được ngay lần đầu. Thứ giết nó là mấy
 * chuyện chỉ lộ ra sau vài tuần chạy thật, và bài này canh đúng bấy nhiêu chuyện:
 *
 *   1. CHỖ VẼ MÀN KHÔNG ĐƯỢC RA MẠNG. Một lưới công cả tháng có hàng chục ô; để nó gọi hàm có
 *      ra mạng là một lần mở bảng bắn sáu mươi lượt gọi ra ngoài.
 *   2. NHỚ THEO Ô LƯỚI, KHÔNG THEO CẶP SỐ. GPS lệch vài mét mỗi lần đo, nên nhớ theo cặp số thì
 *      gần như không bao giờ trúng lại — tức là mỗi lượt chấm một lần đi hỏi máy chủ người khác.
 *   3. ĐÃ HỎI MÀ KHÔNG CÓ TÊN THÌ CŨNG PHẢI NHỚ. Không nhớ thì lượt cron sau hỏi lại đúng ô ấy,
 *      mãi mãi.
 *   4. MẤT MẠNG THÌ KHÔNG ĐƯỢC NHỚ NHẦM THÀNH "CHỖ NÀY KHÔNG CÓ TÊN" — và nhớ suốt 180 ngày.
 *   5. CHỈ TRA TRONG KHUNG VIỆT NAM, nếu không đây là cổng tra địa chỉ miễn phí cho người lạ.
 *
 * Chạy: php tools/test/kiem-dia-chi.php
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

/* ══════════════════════════════════════════════════════════ 1. Ô LƯỚI (luật 2 và luật 5) */

$LAT = 10.775512; $LNG = 106.702134;

t( 'ô lưới làm tròn về 4 chữ số', '10.7755,106.7021' === VHCC_DiaChi::o( $LAT, $LNG ),
	VHCC_DiaChi::o( $LAT, $LNG ) );

/* 🔴 ĐÂY LÀ CẢ LÝ DO CỦA PHÉP LÀM TRÒN. Hai người đứng cạnh nhau trước cùng một cửa hàng cho ra
   hai cặp số khác nhau — GPS lệch vài mét mỗi lần đo. Nhớ theo cặp số thì lần nào cũng trượt sổ
   nhớ, và mỗi lượt chấm công là một lượt đi hỏi máy chủ của người khác. */
t( '🔴 hai phép đo lệch vài mét rơi vào CÙNG một ô',
	VHCC_DiaChi::o( 10.775512, 106.702134 ) === VHCC_DiaChi::o( 10.775487, 106.702108 ),
	array( VHCC_DiaChi::o( 10.775512, 106.702134 ), VHCC_DiaChi::o( 10.775487, 106.702108 ) ) );
t( 'còn cách nhau trăm mét thì KHÁC ô',
	VHCC_DiaChi::o( 10.7755, 106.7021 ) !== VHCC_DiaChi::o( 10.7765, 106.7021 ) );

/* Luật 5 — ngoài khung Việt Nam thì trang chấm công không có việc gì với nó. */
t( '🔴 toạ độ Paris -> không có ô (không tra hộ người lạ)', '' === VHCC_DiaChi::o( 48.8566, 2.3522 ) );
t( '🔴 toạ độ New York -> không có ô', '' === VHCC_DiaChi::o( 40.7128, -74.0060 ) );
t( 'cặp 0,0 -> không có ô (cái bẫy vịnh Guinea)', '' === VHCC_DiaChi::o( 0, 0 ) );
t( 'toạ độ rác -> không có ô', '' === VHCC_DiaChi::o( 'abc', 'xyz' ) );
t( 'Hà Nội vẫn trong khung', '' !== VHCC_DiaChi::o( 21.0285, 105.8542 ) );
t( 'Cà Mau vẫn trong khung', '' !== VHCC_DiaChi::o( 8.9, 105.15 ) );

/* ══════════════════════════════════════════ 2. CHỖ VẼ MÀN KHÔNG ĐƯỢC RA MẠNG (luật 1) */

$GLOBALS['VHCP_DA_GET'] = array();
t( 'chưa tra thì `nho()` trả rỗng', '' === VHCC_DiaChi::nho( $LAT, $LNG ) );
t( '🔴 và `nho()` KHÔNG hề gọi ra mạng', 0 === count( $GLOBALS['VHCP_DA_GET'] ),
	$GLOBALS['VHCP_DA_GET'] );

/* 🔴 CANH THẲNG TRONG MÃ NGUỒN. Phép trên chỉ chứng minh là hôm nay `nho()` không ra mạng; chốt
   này chặn cái ngày có người "tiện tay" gọi `tra()` trong đó cho nó tự điền. Lỗi ấy không làm
   hỏng phép thử nào — chỉ làm mỗi lần mở bảng công là một đợt tra hàng loạt. */
$src_w = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
t( '🔴 màn quản trị chỉ gọi `nho_dong`, KHÔNG gọi `VHCC_DiaChi::tra`',
	false === strpos( $src_w, 'VHCC_DiaChi::tra' ) );

/* ══════════════════════════════════════════════════════ 3. TRA THẬT, RỒI NHỚ LẠI */

$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'display_name' => '12 Nguyễn Huệ, Bến Nghé, Quận 1, Việt Nam' ) ) );

$GLOBALS['VHCP_DA_GET'] = array();
$d = VHCC_DiaChi::tra( $LAT, $LNG );
t( 'tra ra được tên đường', false !== mb_strpos( $d, 'Nguyễn Huệ' ), $d );
/* Mọi hàng đều có đuôi ", Việt Nam" — giữ lại chỉ tốn chỗ trong một cột 200 ký tự. */
t( 'bỏ đuôi ", Việt Nam"', false === mb_strpos( $d, 'Việt Nam' ), $d );
t( 'đúng một lượt gọi ra ngoài', 1 === count( $GLOBALS['VHCP_DA_GET'] ), $GLOBALS['VHCP_DA_GET'] );
t( 'gọi đúng cặp số đã làm tròn',
	false !== strpos( $GLOBALS['VHCP_DA_GET'][0], 'lat=10.7755' )
	&& false !== strpos( $GLOBALS['VHCP_DA_GET'][0], 'lon=106.7021' ), $GLOBALS['VHCP_DA_GET'] );

/* Nay `nho()` đọc được — vẫn không ra mạng. */
$GLOBALS['VHCP_DA_GET'] = array();
t( '`nho()` nay đọc được địa chỉ', false !== mb_strpos( VHCC_DiaChi::nho( $LAT, $LNG ), 'Nguyễn Huệ' ) );
t( '🔴 và vẫn KHÔNG ra mạng', 0 === count( $GLOBALS['VHCP_DA_GET'] ) );

/* 🔴 PHÉP CHÍNH CỦA LUẬT 2: một phép đo LỆCH VÀI MÉT phải trúng sổ nhớ, không hỏi lại. */
t( '🔴 phép đo lệch 3m trúng sổ nhớ, KHÔNG hỏi lại',
	false !== mb_strpos( VHCC_DiaChi::nho( 10.775487, 106.702108 ), 'Nguyễn Huệ' ) );
$GLOBALS['VHCP_DA_GET'] = array();
VHCC_DiaChi::tra( 10.775487, 106.702108 );
t( '🔴 gọi `tra()` lại cùng ô cũng KHÔNG ra mạng lần hai', 0 === count( $GLOBALS['VHCP_DA_GET'] ),
	$GLOBALS['VHCP_DA_GET'] );

/* ════════════════════════════════════ 4. HỎI RỒI MÀ KHÔNG CÓ TÊN -> VẪN PHẢI NHỚ (luật 3) */

$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'error' => 'Unable to geocode' ) ) );
$RUONG_LAT = 10.912345; $RUONG_LNG = 106.512345;

VHCC_DiaChi::tra( $RUONG_LAT, $RUONG_LNG );
$GLOBALS['VHCP_DA_GET'] = array();
VHCC_DiaChi::tra( $RUONG_LAT, $RUONG_LNG );
t( '🔴 ô giữa ruộng: đã hỏi rồi thì KHÔNG hỏi lại', 0 === count( $GLOBALS['VHCP_DA_GET'] ),
	$GLOBALS['VHCP_DA_GET'] );
t( '   và `nho()` trả rỗng, không trả rác', '' === VHCC_DiaChi::nho( $RUONG_LAT, $RUONG_LNG ) );

/* ═══════════════════════════════════════ 5. MẤT MẠNG KHÔNG ĐƯỢC NHỚ NHẦM (luật 4) */

/* 🔴 Ghi một hàng rỗng khi mất mạng là nhớ nhầm "chỗ này không có tên" cho một ô chỉ đơn giản
   là hôm ấy hỏng mạng — và nhớ suốt 180 ngày, tức là ô ấy không bao giờ có địa chỉ nữa. */
unset( $GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] );   // -> WP_Error
$HONG_LAT = 10.801111; $HONG_LNG = 106.601111;
VHCC_DiaChi::tra( $HONG_LAT, $HONG_LNG );
$con = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'dia_chi' )
	. ' WHERE o=%s', VHCC_DiaChi::o( $HONG_LAT, $HONG_LNG ) ) );
t( '🔴 mất mạng -> KHÔNG ghi hàng nào (không nhớ nhầm "không có tên")', 0 === $con, $con );

/* Có mạng lại thì tra được ngay, không phải đợi hết hạn nhớ. */
$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'display_name' => 'Hẻm 100 Cộng Hoà, Tân Bình, Việt Nam' ) ) );
t( 'có mạng lại -> tra được ngay',
	false !== mb_strpos( VHCC_DiaChi::tra( $HONG_LAT, $HONG_LNG ), 'Cộng Hoà' ) );

/* ════════════════════════════════════════════ 6. CẮT NGẮN TRƯỚC KHI CẤT (luật 4) */

$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'display_name' => str_repeat( 'Đường Rất Dài, ', 60 ) ) ) );
$DAI_LAT = 10.855555; $DAI_LNG = 106.655555;
$d = VHCC_DiaChi::tra( $DAI_LAT, $DAI_LNG );
t( '🔴 địa chỉ dài bị cắt vừa cột', mb_strlen( $d ) <= VHCC_DiaChi::DAI_TOI_DA, mb_strlen( $d ) );
t( '   và cất xuống rồi đọc lại vẫn nguyên vẹn', $d === VHCC_DiaChi::nho( $DAI_LAT, $DAI_LNG ) );

/* ═══════════════════════════════════════ 7. ĐỌC TỪ MỘT DÒNG VỊ TRÍ CỦA LƯỢT CHẤM */

$dong = VHCC_ViTri::dong( array( 'lat' => $LAT, 'lng' => $LNG, 'acc' => 12 ), null, null );
t( '🔴 đọc được địa chỉ thẳng từ dòng `vt_vao` của lượt chấm',
	false !== mb_strpos( VHCC_DiaChi::nho_dong( $dong ), 'Nguyễn Huệ' ),
	array( $dong, VHCC_DiaChi::nho_dong( $dong ) ) );
t( 'dòng rỗng -> rỗng, không ném', '' === VHCC_DiaChi::nho_dong( '' ) );
t( 'dòng rác -> rỗng, không ném', '' === VHCC_DiaChi::nho_dong( 'xin chào' ) );

/* ═════════════════════════════════════════════════ 8. ĐIỀN DẦN: KHÔNG QUÉT CẢ BẢNG */

$CS = 'DC_SHOP';
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) );

/* Một ngày gần đây (chưa tra) và một ngày từ đời nào (phải bị bỏ qua). */
$nay = current_time( 'Y-m-d' );
$xua = gmdate( 'Y-m-d', strtotime( $nay . ' -400 day' ) );
$d_moi = VHCC_ViTri::dong( array( 'lat' => 10.900001, 'lng' => 106.900001, 'acc' => 10 ), null, null );
$d_xua = VHCC_ViTri::dong( array( 'lat' => 10.950001, 'lng' => 106.950001, 'acc' => 10 ), null, null );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $nay,
	'ma_nv' => 'DC1', 'hau_to' => '', 'ho_ten' => 'Gần Đây', 'vt_vao' => $d_moi ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $xua,
	'ma_nv' => 'DC2', 'hau_to' => '', 'ho_ten' => 'Đời Nào', 'vt_vao' => $d_xua ) );

$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'display_name' => 'Chỗ Điền Dần, Việt Nam' ) ) );
$so = VHCC_DiaChi::dien_dan();
t( 'điền dần tra được ô của ngày gần đây', $so >= 1, $so );
t( '   và địa chỉ ấy nay đọc được', '' !== VHCC_DiaChi::nho( 10.900001, 106.900001 ) );

/* ⚠️ Bật tính năng này trên một bảng đã có hai năm dữ liệu mà quét cả bảng là hàng chục nghìn ô
   — chạy vài tháng mới xong, và trong suốt lúc ấy thì đúng là tra hàng loạt. */
t( '🔴 KHÔNG đụng tới lượt chấm từ 400 ngày trước',
	'' === VHCC_DiaChi::nho( 10.950001, 106.950001 ) );

/* Chạy lại ngay thì không còn gì để tra — đã nhớ hết. */
$GLOBALS['VHCP_DA_GET'] = array();
VHCC_DiaChi::dien_dan();
t( '🔴 chạy lại KHÔNG hỏi lại mấy ô đã nhớ', 0 === count( $GLOBALS['VHCP_DA_GET'] ),
	$GLOBALS['VHCP_DA_GET'] );

/* ══════════════════════════════════ 9. CỬA CHO NGƯỜI ĐANG ĐỨNG: KHÔNG BAO GIỜ NGỦ */

/* 🔴 `tra()` giữ nhịp 1 lượt/giây bằng cách NGỦ. Ngủ trong một lượt gọi của trình duyệt là giữ
   luôn một tiến trình PHP — tám giờ sáng cả chuỗi mở màn chấm công cùng lúc thì hosting hết
   sạch tiến trình, và đứng cả trang web chứ không riêng ô địa chỉ. `tra_nhanh()` phải NHƯỜNG
   khi nhịp đang bận, chứ không xếp hàng. */
$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'display_name' => 'Số 5 Lê Lợi, Quận 1, Việt Nam' ) ) );

update_option( 'vhcc_dia_chi_nhip', microtime( true ) );   /* nhịp vừa bận */
$GLOBALS['VHCP_DA_GET'] = array();
$bd = microtime( true );
$d  = VHCC_DiaChi::tra_nhanh( 10.770001, 106.660001 );
$het = microtime( true ) - $bd;
t( '🔴 nhịp đang bận -> `tra_nhanh()` KHÔNG ngủ', $het < 0.3, round( $het, 3 ) . 's' );
t( '   và không gọi ra mạng', 0 === count( $GLOBALS['VHCP_DA_GET'] ), $GLOBALS['VHCP_DA_GET'] );
t( '   trả rỗng chứ không ném', '' === $d, $d );

/* Nhịp rảnh thì nó đi hỏi bình thường. */
update_option( 'vhcc_dia_chi_nhip', microtime( true ) - 5 );
t( 'nhịp rảnh -> tra được', false !== mb_strpos( VHCC_DiaChi::tra_nhanh( 10.770001, 106.660001 ), 'Lê Lợi' ) );

/* Đã nhớ rồi thì trả ngay, KHÔNG quan tâm nhịp bận hay rảnh — đọc sổ có ra mạng đâu. */
update_option( 'vhcc_dia_chi_nhip', microtime( true ) );
$GLOBALS['VHCP_DA_GET'] = array();
t( '🔴 đã nhớ thì nhịp bận vẫn trả ngay',
	false !== mb_strpos( VHCC_DiaChi::tra_nhanh( 10.770001, 106.660001 ), 'Lê Lợi' ) );
t( '   và vẫn không ra mạng', 0 === count( $GLOBALS['VHCP_DA_GET'] ) );

/* Luật 5 vẫn áp ở cửa này — nếu không thì đây là cổng tra địa chỉ miễn phí cho người lạ. */
update_option( 'vhcc_dia_chi_nhip', microtime( true ) - 5 );
$GLOBALS['VHCP_DA_GET'] = array();
t( '🔴 toạ độ ngoài Việt Nam -> cửa nhanh cũng chối', '' === VHCC_DiaChi::tra_nhanh( 48.8566, 2.3522 ) );
t( '   và không gọi ra mạng', 0 === count( $GLOBALS['VHCP_DA_GET'] ), $GLOBALS['VHCP_DA_GET'] );

/* ════════════════════════════════════════════ 10. CỬA TRẠM ĐÒI THẺ PHIÊN */

/* 🔴 Không đòi thẻ thì bất kỳ ai gõ được một cặp số cũng mượn được máy chủ mình đi tra hộ —
   đúng cái mà `VHCC_BanDo` đã phải khoá ba lớp để tránh. */
$src_t = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
$vt_dc = strpos( $src_t, "'diachi' === \$viec" );
t( 'cửa `diachi` có mặt', false !== $vt_dc );
$khoi = false === $vt_dc ? '' : substr( $src_t, $vt_dc, 900 );
t( '🔴 cửa `diachi` đòi thẻ phiên trước khi tra',
	false !== strpos( $khoi, 'self::nguoi( $tk_d )' ), $khoi );
t( '🔴 cửa `diachi` gọi `tra_nhanh`, KHÔNG gọi `tra` (hàm có ngủ)',
	false !== strpos( $khoi, 'VHCC_DiaChi::tra_nhanh' )
	&& false === strpos( $khoi, 'VHCC_DiaChi::tra(' ), $khoi );

/* ══════════════════════════ 11. XẾP LỊCH CRON: KHAI NHỊP TRƯỚC, XẾP SAU */

/* 🔴 LỖI NÀY HỎNG IM LẶNG HOÀN HẢO, NÊN PHẢI CANH BẰNG PHÉP THỬ CHỨ KHÔNG BẰNG MẮT.
   `wp_schedule_event()` tra tên nhịp trong danh sách do bộ lọc `cron_schedules` dựng ra. Gọi nó
   TRƯỚC khi `add_filter` chạy thì tên nhịp chưa có trong danh sách, WordPress trả về WP_Error
   rồi thôi — KHÔNG xếp lịch gì cả. Lượt tải trang sau lại y như vậy, mãi mãi.

   Không báo lỗi, không dòng nhật ký, plugin chạy bình thường, chỉ có việc nền là không bao giờ
   chạy. Anh Thắng 20/09/2026: *"Do định vị hay do app. Chưa lấy được"* — đúng câu hỏi mà một
   lỗi kiểu này bắt người ta phải hỏi.

   ⚠️ CANH CẢ `VHCC_Push` nữa: nó mắc y hệt và đã im lặng như thế nhiều tuần. Phép thử canh mã
      nguồn chứ không canh hành vi, vì hành vi ở đây là "không có gì xảy ra" — không quan sát
      được từ bên trong một bài kiểm không có cron thật. */
function vhcc_thu_tu_cron( $tep, $ten_nhip ) {
	$src = file_get_contents( $tep );
	$vt  = strpos( $src, 'public static function init()' );
	if ( false === $vt ) { return 'khong thay init()'; }
	$khoi = substr( $src, $vt, 2600 );
	/* ⚠️ BỎ CHÚ THÍCH TRƯỚC KHI SOI. Bản đầu của phép thử này báo đỏ oan: chính khối chú thích
	   giải thích lỗi có chứa chuỗi `wp_schedule_event()`, và nó nằm TRƯỚC dòng `add_filter` —
	   nên phép thử kết luận thứ tự sai trong khi mã hoàn toàn đúng. Một phép thử đọc cả chú
	   thích là một phép thử phạt người viết chú thích tử tế. */
	$khoi = preg_replace( '#/\*.*?\*/#s', '', $khoi );
	$khoi = preg_replace( '#//[^\n]*#', '', $khoi );
	$a = strpos( $khoi, "add_filter( 'cron_schedules'" );
	$b = strpos( $khoi, "wp_schedule_event(" );
	if ( false === $a ) { return 'khong khai cron_schedules'; }
	if ( false === $b ) { return 'khong xep lich'; }
	if ( false === strpos( $khoi, "'" . $ten_nhip . "'" ) ) { return 'khong thay nhip ' . $ten_nhip; }
	return ( $a < $b ) ? 'ok' : 'XEP LICH TRUOC KHI KHAI NHIP';
}

$f_dc = $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-dia-chi.php';
$f_ps = $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-push.php';
t( '🔴 VHCC_DiaChi khai nhịp TRƯỚC khi xếp lịch',
	'ok' === vhcc_thu_tu_cron( $f_dc, 'vhcc_15phut' ), vhcc_thu_tu_cron( $f_dc, 'vhcc_15phut' ) );
t( '🔴 VHCC_Push cũng vậy (từng mắc y hệt)',
	'ok' === vhcc_thu_tu_cron( $f_ps, 'vhcc_5phut' ), vhcc_thu_tu_cron( $f_ps, 'vhcc_5phut' ) );

/* Và lớp phải được khởi động thật — `init()` có mà không ai gọi thì cũng bằng không. Đúng cái
   đã xảy ra với `VHCC_Push` (xem khối 17/09/2026 ở đầu tệp plugin). */
$f_pl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
t( '🔴 VHCC_DiaChi::init() CÓ NGƯỜI GỌI', false !== strpos( $f_pl, 'VHCC_DiaChi::init();' ) );
t( '   và tệp lớp có được nạp', false !== strpos( $f_pl, 'class-vhcc-dia-chi.php' ) );

/* ════════════════════════════════════════ 12. CHẨN ĐOÁN NÓI ĐÚNG TỪNG NGUYÊN NHÂN */

/* 🔴 Một ô địa chỉ trống có thể là năm chuyện khác nhau, và BỐN trong năm KHÔNG phải lỗi định
   vị. Bắt người dùng phân biệt bằng mắt là bắt họ làm việc của máy. */
$c = VHCC_DiaChi::chan_doan( 48.8566, 2.3522 );
t( '🔴 ngoài khung -> nói đúng là ngoài khung', 'ngoai_khung' === $c['ket'], $c );
t( '   và nói rõ KHÔNG phải lỗi mạng', false !== mb_strpos( $c['chu'], 'khong phai loi mang' ), $c );

$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'display_name' => 'Đường Chẩn Đoán, Việt Nam' ) ) );
update_option( 'vhcc_dia_chi_nhip', microtime( true ) - 5 );
$c = VHCC_DiaChi::chan_doan( 10.930001, 106.930001 );
t( 'ra được mạng -> nói là tra được', 'tra_duoc' === $c['ket'], $c );

/* Đã nhớ rồi thì nói là đã có, không đi hỏi lại. */
VHCC_DiaChi::tra( 10.930001, 106.930001 );
$c = VHCC_DiaChi::chan_doan( 10.930001, 106.930001 );
t( 'đã nhớ -> nói là đã có', 'co' === $c['ket'], $c );

/* 🔴 CHỖ QUAN TRỌNG NHẤT: hosting chặn đường ra ngoài. Đây là nguyên nhân mà nhìn màn hình
   không bao giờ đoán ra, và cũng là nguyên nhân người dùng KHÔNG tự sửa được — câu trả lời
   phải chỉ thẳng sang bên hosting, chứ không để họ đi chỉnh lại GPS. */
unset( $GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] );
update_option( 'vhcc_dia_chi_nhip', microtime( true ) - 5 );
$c = VHCC_DiaChi::chan_doan( 10.940001, 106.940001 );
t( '🔴 hosting chặn -> nói đúng là không ra được internet',
	'khong_ra_duoc_mang' === $c['ket'], $c );
t( '   và nói rõ KHÔNG phải lỗi định vị',
	false !== mb_strpos( $c['chu'], 'KHONG phai loi dinh vi' ), $c );
t( '   và chỉ đúng chỗ phải sửa (hosting)', false !== mb_strpos( $c['chu'], 'hosting' ), $c );

/* Máy chủ bản đồ trả lời nhưng chỗ đó không có tên -> KHÁC HẲN hai ca trên. */
$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 200,
	'body' => wp_json_encode( array( 'error' => 'Unable to geocode' ) ) );
update_option( 'vhcc_dia_chi_nhip', microtime( true ) - 5 );
$c = VHCC_DiaChi::chan_doan( 10.960001, 106.960001 );
t( '🔴 chỗ không có tên -> nói đúng, không đổ cho mạng',
	'cho_nay_khong_co_ten' === $c['ket'], $c );

/* Bị chối (403/429) cũng phải tách riêng khỏi "mất mạng": hai chuyện, hai cách sửa. */
$GLOBALS['VHCP_HTTP']['nominatim.openstreetmap.org'] = array( 'code' => 403, 'body' => 'nope' );
update_option( 'vhcc_dia_chi_nhip', microtime( true ) - 5 );
$c = VHCC_DiaChi::chan_doan( 10.970001, 106.970001 );
t( '🔴 bị chối 403 -> tách riêng khỏi mất mạng', 'bi_choi' === $c['ket'], $c );

/* Trang chẩn đoán của trạm phải IN RA lịch cron — không in thì lỗi xếp lịch vẫn im lặng. */
$src_t2 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( '🔴 trang chẩn đoán in ra lịch của cron điền địa chỉ',
	false !== strpos( $src_t2, "wp_next_scheduled( 'vhcc_dia_chi_dien' )" ) );
t( '   và nói rõ khi CHƯA xếp được lịch',
	false !== strpos( $src_t2, 'CHUA XEP DUOC LICH' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — địa chỉ điền dần ở nền, không bao giờ chen vào lượt chấm công.\n";
