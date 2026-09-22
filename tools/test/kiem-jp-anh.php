<?php
/**
 * KIỂM ẢNH CỦA JP CAPSULE (wordpress/vhcp-jp).
 *
 * =============================================================================================
 * 🔴 BÀI NÀY CANH MỘT CỬA GHI TỆP LÊN MÁY CHỦ
 * =============================================================================================
 * `jpUploadPhoto` nhận một chuỗi base64 từ điện thoại của nhân viên rồi GHI THÀNH TỆP trong thư
 * mục tải lên — thư mục mà web phục vụ công khai. Đó là loại cửa mà sai một lần là mất máy chủ,
 * không phải mất một con số. Nên phần lớn phép thử ở đây xoay quanh đúng bốn câu:
 *
 *   1. Thứ gửi lên có THẬT LÀ ẢNH không — soi bốn byte đầu, không tin cái nhãn `data:image/...`
 *      do máy khách viết ra.
 *   2. Đuôi tệp lấy từ thứ SOI ĐƯỢC, không lấy từ nhãn — không thì có `.php` trong uploads.
 *   3. Ai gọi cũng phải qua cửa quyền, kể cả đường chỉ có mỗi `photoId`.
 *   4. Xoá thì chỉ xoá được trong thư mục ảnh của mình.
 *
 * Chạy: php tools/test/kiem-jp-anh.php
 */

require_once __DIR__ . '/wp-stub.php';

/* ---------------------------------------------------- mấy hàm WordPress mà bệ đỡ chưa có */
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
}
/* ⚠️ BỆ ĐỠ ĐÃ CÓ `wp_upload_dir()`. Khai lại trong `if (!function_exists)` thì bản của bệ đỡ
   thắng, còn bài kiểm lại đi soi một thư mục KHÁC — tệp ghi một nơi, soi một nơi, và bài đỏ vì
   lỗi của chính nó. Nên: đọc thẳng thư mục mà bệ đỡ trả về. */
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $n = 12, $dac_biet = true, $them = false ) {
		$c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$s = '';
		for ( $i = 0; $i < $n; $i++ ) { $s .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; }
		return $s;
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) { function add_rewrite_rule() {} }
if ( ! function_exists( 'flush_rewrite_rules' ) ) { function flush_rewrite_rules( $x = true ) {} }
if ( ! function_exists( 'nocache_headers' ) ) { function nocache_headers() {} }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route() { return true; } }

$tm = wp_upload_dir();
$GLOBALS['JP_TAI_LEN'] = $tm['basedir'];
@mkdir( $GLOBALS['JP_TAI_LEN'], 0777, true );

/* ------------------------------------------------------------------ đếm & in kết quả */
$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

vhjp_test_boot( dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp' );

/* ------------------------------------------------------------------ dữ liệu nền */
global $wpdb;
VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS01', 'name' => 'JP Bà Rịa', 'code' => 'BR' ) );
VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS02', 'name' => 'JP Vũng Tàu', 'code' => 'VT' ) );
VHJP_Nguon::them( 'JP_Reports', array(
	'id' => 'BC001', 'locationId' => 'CS01', 'locationName' => 'JP Bà Rịa',
	'fromDate' => '2026-09-01', 'toDate' => '2026-09-15',
	'userId' => 'U1', 'userName' => 'Nhân Viên A', 'status' => 'DRAFT' ) );
VHJP_Nguon::them( 'JP_Reports', array(
	'id' => 'BC002', 'locationId' => 'CS02', 'locationName' => 'JP Vũng Tàu',
	'fromDate' => '2026-09-01', 'toDate' => '2026-09-15',
	'userId' => 'U2', 'userName' => 'Nhân Viên B', 'status' => 'DRAFT' ) );

/* ⚠️ Cơ sở được gán nằm ở `locationIds` (MẢNG) — `VHJP_Auth::xem_duoc_coso()` đọc ô ấy. Dựng
   người thử bằng `locationId` số ít thì mọi phép quyền đều chối, và bài kiểm đỏ vì lỗi của
   CHÍNH NÓ chứ không phải của plugin. */
$NV1 = array( 'id' => 'U1', 'hoTen' => 'Nhân Viên A', 'role' => 'NV', 'locationIds' => array( 'CS01' ) );
$NV2 = array( 'id' => 'U2', 'hoTen' => 'Nhân Viên B', 'role' => 'NV', 'locationIds' => array( 'CS02' ) );
$KT  = array( 'id' => 'K1', 'hoTen' => 'Kế Toán', 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );

/* Ba tấm ảnh thật, dựng từ chữ ký gốc — không cần tệp mẫu trong kho. */
$JPG  = "\xFF\xD8\xFF\xE0" . str_repeat( "\x00", 40 );
$PNG  = "\x89PNG\r\n\x1a\n" . str_repeat( "\x00", 40 );
$WEBP = 'RIFF' . "\x00\x00\x00\x00" . 'WEBP' . str_repeat( "\x00", 40 );
function du( $raw, $nhan = 'image/jpeg' ) { return 'data:' . $nhan . ';base64,' . base64_encode( $raw ); }

/* =============================================================================================
 * 1. SOI KIỂU TỆP — KHÔNG TIN CÁI NHÃN
 * =========================================================================================== */
t( 'nhận JPG',  'jpg'  === VHJP_Anh::soi_kieu( $JPG ) );
t( 'nhận PNG',  'png'  === VHJP_Anh::soi_kieu( $PNG ) );
t( 'nhận WEBP', 'webp' === VHJP_Anh::soi_kieu( $WEBP ) );

/* 🔴 ĐÂY LÀ PHÉP THỬ QUAN TRỌNG NHẤT BÀI. Chuỗi `data:image/jpeg;base64,` do MÁY KHÁCH viết;
   một tệp PHP mã hoá kèm đúng nhãn ấy vẫn là tệp PHP. Lấy đuôi từ nhãn là có `.php` nằm trong
   thư mục uploads, mở bằng trình duyệt là CHẠY. */
$PHP = "<?php echo 'toang'; ?>" . str_repeat( 'A', 40 );
t( '🔴 tệp PHP đội lốt ảnh: soi ra KHÔNG phải ảnh', '' === VHJP_Anh::soi_kieu( $PHP ) );
$HTML = "<html><script>alert(1)</script>" . str_repeat( 'A', 40 );
t( '🔴 tệp HTML cũng bị chối', '' === VHJP_Anh::soi_kieu( $HTML ) );
t( 'tệp quá ngắn thì chối', '' === VHJP_Anh::soi_kieu( "\xFF\xD8\xFF" ) );
/* GIF cố ý KHÔNG mở — mở thêm một loại là thêm một bộ giải mã có thể có lỗ. */
t( 'GIF không nằm trong danh sách cho phép',
	'' === VHJP_Anh::soi_kieu( 'GIF89a' . str_repeat( "\x00", 40 ) ) );

/* Bóc data URL */
t( 'bóc được data URL đúng khuôn', $JPG === VHJP_Anh::boc_data_url( du( $JPG ) ) );
t( 'thiếu tiền tố data: thì chối', '' === VHJP_Anh::boc_data_url( base64_encode( $JPG ) ) );
t( 'không phải base64 thì chối', '' === VHJP_Anh::boc_data_url( 'data:image/jpeg,xxx' ) );
t( 'base64 hỏng thì chối', '' === VHJP_Anh::boc_data_url( 'data:image/jpeg;base64,@@@@' ) );

/* =============================================================================================
 * 2. TẢI LÊN
 * =========================================================================================== */
$goi = array( 'reportId' => 'BC001', 'scope' => 'ROW', 'refId' => 'R1', 'kind' => 'METER',
	'dataUrl' => du( $JPG ), 'takenAt' => '2026-09-16T08:30:00Z' );

$kq = VHJP_Anh::tai_len( $NV1, $goi );
t( 'nhân viên tải được ảnh của cơ sở mình', ! empty( $kq['ok'] ), $kq );
t( 'trả về bản ghi ảnh có mã', ! empty( $kq['photo']['id'] ) );
t( 'ảnh mới KHÔNG có fileId (không phải ảnh Drive)', '' === $kq['photo']['fileId'] );
t( 'và có url trỏ vào thư mục tải lên',
	0 === strpos( $kq['photo']['url'], 'http://example.test/wp-content/uploads/jp-anh/' ),
	$kq['photo']['url'] );
t( 'giữ đúng scope/refId/kind',
	'ROW' === $kq['photo']['scope'] && 'R1' === $kq['photo']['refId']
	&& 'METER' === $kq['photo']['kind'], $kq['photo'] );

/* 🔴 TÊN TỆP PHẢI NGẪU NHIÊN. Thư mục uploads mở công khai; tên đoán được là ai cũng dò ra ảnh
   chỉ số của mọi cơ sở. */
$ten_tep = basename( $kq['photo']['url'] );
t( '🔴 tên tệp dài và ngẫu nhiên', preg_match( '/^[A-Za-z0-9]{32}\.jpg$/', $ten_tep ), $ten_tep );
t( '🔴 tên tệp KHÔNG chứa mã báo cáo', false === strpos( $ten_tep, 'BC001' ) );

/* Tệp có thật trên đĩa, và đúng bytes đã gửi. */
$duong = $GLOBALS['JP_TAI_LEN'] . '/jp-anh/' . gmdate( 'Y/m' ) . '/' . $ten_tep;
t( 'tệp nằm thật trên đĩa', file_exists( $duong ) );
t( 'nội dung tệp đúng bytes đã gửi', $JPG === file_get_contents( $duong ) );

$sổ = VHJP_Nguon::tim( 'JP_Photos', 'reportId', 'BC001' );
t( 'sổ ảnh lên một dòng', 1 === count( $sổ ) );
t( 'ghi đúng số byte', strlen( $JPG ) === (int) $sổ[0]['bytes'] );

/* PNG và WEBP ra đúng đuôi. */
$png = VHJP_Anh::tai_len( $NV1, array_merge( $goi, array( 'dataUrl' => du( $PNG, 'image/png' ) ) ) );
t( 'PNG ra đuôi .png', preg_match( '/\.png$/', $png['photo']['url'] ), $png['photo']['url'] );

/* 🔴 NHÃN NÓI PHP MÀ RUỘT LÀ JPG -> vẫn ra .jpg, vì đuôi lấy từ thứ soi được. */
$lech = VHJP_Anh::tai_len( $NV1, array_merge( $goi,
	array( 'dataUrl' => 'data:application/x-php;base64,' . base64_encode( $JPG ) ) ) );
t( '🔴 nhãn nói PHP mà ruột là JPG thì vẫn ra .jpg',
	preg_match( '/\.jpg$/', $lech['photo']['url'] ), $lech['photo']['url'] );

/* 🔴 NHÃN NÓI ẢNH MÀ RUỘT LÀ PHP -> CHỐI. */
$hong = null;
try { VHJP_Anh::tai_len( $NV1, array_merge( $goi, array( 'dataUrl' => du( $PHP ) ) ) ); }
catch ( Exception $e ) { $hong = $e->getMessage(); }
t( '🔴 nhãn nói ảnh mà ruột là PHP thì CHỐI', null !== $hong, $hong );
t( 'và câu chối nói rõ là không phải ảnh',
	$hong && false !== strpos( $hong, 'không phải ảnh' ), $hong );

/* Không có tệp .php nào lọt vào thư mục ảnh. */
$la = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator(
	$GLOBALS['JP_TAI_LEN'] . '/jp-anh', FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( ! preg_match( '/\.(jpg|png|webp)$/', $f->getFilename() ) ) { $la[] = $f->getFilename(); }
}
t( '🔴 thư mục ảnh KHÔNG có tệp nào ngoài jpg/png/webp', ! $la, $la );

/* Quá nặng thì chối. */
$nang = null;
try {
	VHJP_Anh::tai_len( $NV1, array_merge( $goi, array(
		'dataUrl' => du( $JPG . str_repeat( 'A', VHJP_Anh::TOI_DA_BYTE ) ) ) ) );
} catch ( Exception $e ) { $nang = $e->getMessage(); }
t( 'ảnh quá nặng thì chối', null !== $nang );
t( 'và nói rõ bao nhiêu MB', $nang && false !== strpos( $nang, 'MB' ), $nang );

/* 🔴 QUYỀN THEO BÁO CÁO, không theo thứ máy khách gửi. */
$khac = null;
try { VHJP_Anh::tai_len( $NV2, $goi ); } catch ( Exception $e ) { $khac = $e->getMessage(); }
t( '🔴 nhân viên cơ sở khác KHÔNG tải được vào báo cáo này', null !== $khac, $khac );
t( 'kế toán thì tải được vào mọi cơ sở',
	! empty( VHJP_Anh::tai_len( $KT, array_merge( $goi, array( 'reportId' => 'BC002' ) ) )['ok'] ) );

$thieu = null;
try { VHJP_Anh::tai_len( $NV1, array_merge( $goi, array( 'reportId' => 'KHONG_CO' ) ) ); }
catch ( Exception $e ) { $thieu = $e->getMessage(); }
t( 'báo cáo không có thật thì chối', null !== $thieu );

/* =============================================================================================
 * 3. XOÁ
 * =========================================================================================== */
$ma_anh = $kq['photo']['id'];

/* 🔴 Mã ảnh chạy tuần tự nên đoán được; không kiểm quyền là ai cũng xoá sạch ảnh mọi cơ sở, mà
   ảnh chỉ số thì không dựng lại được — máy đã quay tiếp rồi. */
$xoa_lau = null;
try { VHJP_Anh::xoa( $NV2, $ma_anh ); } catch ( Exception $e ) { $xoa_lau = $e->getMessage(); }
t( '🔴 người cơ sở khác KHÔNG xoá được, dù biết mã', null !== $xoa_lau, $xoa_lau );
/* Canh THẲNG vào sổ và vào đĩa, đừng chỉ canh câu chối: chốt quyền mà tuột thì dòng đã mất rồi,
   và mọi phép thử sau đó đỏ vì "không tìm thấy ảnh" — một đống dòng đỏ không nói chốt nào hỏng. */
t( '🔴 và dòng ảnh vẫn còn trong sổ', (bool) VHJP_Nguon::tim_mot( 'JP_Photos', 'id', $ma_anh ) );
t( '🔴 và tệp vẫn còn trên đĩa', file_exists( $duong ) );

/* Bọc lượt xoá HỢP LỆ: chốt trên mà tuột thì ảnh đã bị xoá mất, và lượt này ném ngoại lệ —
   không bọc là cả bài dừng giữa chừng thay vì in ra dòng đỏ đúng chỗ. */
$xoa_that = null;
try { $xoa_that = VHJP_Anh::xoa( $NV1, $ma_anh ); } catch ( Exception $e ) { $xoa_that = $e->getMessage(); }
t( 'chủ cơ sở xoá được', is_array( $xoa_that ) && ! empty( $xoa_that['ok'] ), $xoa_that );
t( 'xoá xong thì mất dòng trong sổ', ! VHJP_Nguon::tim_mot( 'JP_Photos', 'id', $ma_anh ) );
/* Xoá dòng mà để tệp lại là thư mục phình mãi bằng ảnh không ai trỏ tới. */
t( '🔴 và tệp trên đĩa cũng mất theo', ! file_exists( $duong ) );

$khong = null;
try { VHJP_Anh::xoa( $NV1, 'ANH999999' ); } catch ( Exception $e ) { $khong = $e->getMessage(); }
t( 'xoá ảnh không có thật thì chối', null !== $khong );

/* 🔴 `url` nằm trong CSDL nên coi như có thể bị sửa. Dựng đường dẫn thẳng từ nó rồi unlink là
   một đường xoá tệp bất kỳ trên máy chủ. */
$ngoai = $GLOBALS['JP_TAI_LEN'] . '/dung-xoa-toi.txt';
file_put_contents( $ngoai, 'quan trọng' );
t( '🔴 url ngoài thư mục ảnh thì KHÔNG xoá',
	false === VHJP_Anh::xoa_tep( 'http://example.test/wp-content/uploads/dung-xoa-toi.txt' ) );
t( '🔴 url leo thư mục (../) cũng KHÔNG xoá',
	false === VHJP_Anh::xoa_tep( 'http://example.test/wp-content/uploads/jp-anh/../dung-xoa-toi.txt' ) );
t( '🔴 đuôi lạ trong đúng thư mục cũng KHÔNG xoá',
	false === VHJP_Anh::xoa_tep( 'http://example.test/wp-content/uploads/jp-anh/2026/09/abcdefgh.php' ) );
t( 'tệp bên ngoài vẫn còn nguyên', file_exists( $ngoai ) );

/* =============================================================================================
 * 4. XEM BYTES
 * =========================================================================================== */
$moi = VHJP_Anh::tai_len( $NV1, $goi );
$xem = VHJP_Anh::xem( $NV1, $moi['photo']['id'] );
t( 'đọc lại được bytes của ảnh', ! empty( $xem['dataUrl'] ) );
t( 'và ra đúng ảnh đã gửi', du( $JPG ) === $xem['dataUrl'], substr( $xem['dataUrl'], 0, 40 ) );

/* 🔴 Đường này chỉ có mỗi mã ảnh — không kiểm quyền là một cửa đọc tệp không khoá. */
$xem_lau = null;
try { VHJP_Anh::xem( $NV2, $moi['photo']['id'] ); } catch ( Exception $e ) { $xem_lau = $e->getMessage(); }
t( '🔴 người cơ sở khác KHÔNG đọc được bytes', null !== $xem_lau, $xem_lau );
t( 'kế toán thì đọc được', ! empty( VHJP_Anh::xem( $KT, $moi['photo']['id'] )['dataUrl'] ) );

/* Ảnh cũ trên Drive: nói thẳng thay vì trả rỗng. */
VHJP_Nguon::them( 'JP_Photos', array( 'id' => 'ANHCU1', 'reportId' => 'BC001',
	'fileId' => 'DRIVE123', 'url' => 'https://drive.google.com/x', 'scope' => 'ROW' ) );
$drive = null;
try { VHJP_Anh::xem( $NV1, 'DRIVE123' ); } catch ( Exception $e ) { $drive = $e->getMessage(); }
t( 'ảnh Drive cũ thì nói rõ là nằm trên Drive',
	$drive && false !== strpos( $drive, 'Drive' ), $drive );

/* =============================================================================================
 * 5. ẢNH THEO CƠ SỞ
 * =========================================================================================== */
$d = VHJP_Anh::theo_coso( $KT, array( 'locationId' => 'CS01' ) );
t( 'gom được ảnh theo cơ sở', ! empty( $d['ok'] ) );
t( 'tên cơ sở đọc từ danh mục', 'JP Bà Rịa' === $d['tenCoSo'], $d['tenCoSo'] );
t( 'chỉ đếm báo cáo của cơ sở ấy', 1 === $d['soBaoCao'], $d['soBaoCao'] );
t( 'có nhóm theo kỳ', 1 === count( $d['nhom'] ) );

/* 🔴 Kỳ KHÔNG có ảnh là thứ quan trọng nhất màn này nói được — một màn chỉ in ảnh CÓ thì không
   bao giờ cho biết kỳ nào cơ sở không gửi gì. */
VHJP_Nguon::them( 'JP_Reports', array( 'id' => 'BC003', 'locationId' => 'CS01',
	'locationName' => 'JP Bà Rịa', 'fromDate' => '2026-08-01', 'toDate' => '2026-08-15',
	'userId' => 'U1', 'userName' => 'Nhân Viên A', 'status' => 'SUBMITTED' ) );
$d2 = VHJP_Anh::theo_coso( $KT, array( 'locationId' => 'CS01' ) );
t( '🔴 kỳ không có ảnh nào vẫn được liệt kê', 1 === count( $d2['khongCoAnh'] ), $d2['khongCoAnh'] );
t( 'và mang đủ ngày, người, trạng thái để kế toán truy',
	'BC003' === $d2['khongCoAnh'][0]['reportId']
	&& 'Nhân Viên A' === $d2['khongCoAnh'][0]['userName']
	&& 'SUBMITTED' === $d2['khongCoAnh'][0]['status'], $d2['khongCoAnh'][0] );

/* Lọc theo loại ảnh — đếm bằng một loại CHỈ CÓ ĐÚNG MỘT tấm, chứ đừng so với tổng: sổ còn
   ảnh Drive cũ mang kind rỗng, so với tổng là bài đỏ vì phép thử đếm ẩu chứ không phải vì lọc
   sai. */
VHJP_Anh::tai_len( $NV1, array_merge( $goi, array( 'kind' => 'RIENG_MOT_TAM' ) ) );
t( 'lọc theo kind ra đúng một tấm',
	1 === VHJP_Anh::theo_coso( $KT, array( 'locationId' => 'CS01', 'kind' => 'RIENG_MOT_TAM' ) )['soAnh'] );
t( 'không lọc thì nhiều hơn',
	VHJP_Anh::theo_coso( $KT, array( 'locationId' => 'CS01' ) )['soAnh'] > 1 );
t( 'kind không có thì ra 0 ảnh',
	0 === VHJP_Anh::theo_coso( $KT, array( 'locationId' => 'CS01', 'kind' => 'KHONG_CO' ) )['soAnh'] );

/* Lọc theo ngày — kỳ tháng 8 rơi ra ngoài. */
$d3 = VHJP_Anh::theo_coso( $KT, array( 'locationId' => 'CS01', 'tuNgay' => '2026-09-01' ) );
t( 'lọc từ ngày thì bỏ kỳ cũ', 1 === $d3['soBaoCao'], $d3['soBaoCao'] );

/* 🔴 NHÂN VIÊN CHỈ THẤY CƠ SỞ MÌNH, kể cả khi không truyền locationId. */
$nv = VHJP_Anh::theo_coso( $NV1, array() );
$chi_minh = true;
foreach ( $nv['nhom'] as $n ) { if ( 'BC002' === $n['reportId'] ) { $chi_minh = false; } }
foreach ( $nv['khongCoAnh'] as $n ) { if ( 'BC002' === $n['reportId'] ) { $chi_minh = false; } }
t( '🔴 nhân viên KHÔNG thấy báo cáo cơ sở khác', $chi_minh, $nv );
$lau = null;
try { VHJP_Anh::theo_coso( $NV1, array( 'locationId' => 'CS02' ) ); }
catch ( Exception $e ) { $lau = $e->getMessage(); }
t( '🔴 và lọc thẳng sang cơ sở khác thì bị chối', null !== $lau, $lau );

/* Kế toán thấy cả hai. */
t( 'kế toán thấy mọi cơ sở', VHJP_Anh::theo_coso( $KT, array() )['soBaoCao'] >= 3 );

/* =============================================================================================
 * 6. CỔNG ĐÃ NỐI ĐỦ BỐN HÀM
 * =========================================================================================== */
$map = VHJP_Cong::map();
foreach ( array( 'jpUploadPhoto', 'jpDeletePhoto', 'jpAnhXem', 'jpAnhTheoCoSo' ) as $fn ) {
	t( "cổng đã nối $fn", isset( $map[ $fn ] ) );
	/* Chuyển xong mà quên xoá khỏi `chua_lam()` thì giao diện vẫn bị trả 501. */
	t( "và $fn KHÔNG còn nằm trong danh sách chưa làm",
		! in_array( $fn, VHJP_Cong::chua_lam(), true ) );
}

/* ==================================================================== dọn & in kết quả */
/* Chỉ dọn thư mục ảnh của mình — `uploads` là của bệ đỡ, bài khác còn dùng. */
if ( is_dir( $GLOBALS['JP_TAI_LEN'] . '/jp-anh' ) ) {
	foreach ( new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $GLOBALS['JP_TAI_LEN'] . '/jp-anh',
			FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
		$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() );
	}
	@rmdir( $GLOBALS['JP_TAI_LEN'] . '/jp-anh' );
}

echo "\n";
if ( $TRUOT ) {
	echo 'TRƯỢT ' . count( $TRUOT ) . ":\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo 'ĐẠT: ' . $DAT . "\n";
	exit( 1 );
}
echo 'ĐẠT: ' . $DAT . " phép thử — cửa ghi tệp soi ruột chứ không tin nhãn.\n";
