<?php
/**
 * KIỂM PLUGIN NHÀ MA — bán vé theo khung giờ (wordpress/vhcp-nha-ma).
 *
 * =============================================================================================
 * 🔴 BÀI NÀY CANH ĐÚNG MỘT THỨ: SỐ CHỖ
 * =============================================================================================
 * Cả plugin quy về một câu hỏi — *"khung này còn mấy chỗ"*. Sai câu ấy thì hai đoàn cùng đứng ở
 * cửa cho một khung giờ, và người thua là khách đã trả tiền. Nên phần lớn phép thử ở đây đều
 * xoay quanh nó, kể cả ca hai người bấm trong cùng một khoảnh khắc.
 *
 * ⚠️ Không kiểm giao diện ở đây — phần bấm nút đã chạy thật trên Chromium. Bài này kiểm MÁY CHỦ,
 *    tức là phần mà trình duyệt không được phép tự quyết.
 *
 * Chạy: php tools/test/kiem-nha-ma.php
 */

/* Cho phép bơm thân gói vào cổng tiền mà không cần HTTP thật — xem `NHAMA::than_tho()`. */
define( 'NHAMA_TEST', 1 );

require_once __DIR__ . '/wp-stub.php';

/* ------------------------------------------------------- mấy hàm WordPress mà stub chưa có */
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $tep, $cb ) { return true; }
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $ns, $duong, $tuy = array() ) { return true; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $u, $p = -1 ) { return parse_url( $u, $p ); }
}

/** Gói tin REST giả — plugin chỉ dùng hai hàm này. */
class NM_Req {
	private $d;
	public function __construct( $d ) { $this->d = $d; }
	public function get_json_params() { return $this->d; }
	public function get_params() { return $this->d; }
}

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );   // dựng $wpdb + bảng nền
require_once $goc . '/wordpress/vhcp-nha-ma/vhcp-nha-ma.php';

/* Dựng bảng của plugin bằng SQLite — cùng sơ đồ, đọc thẳng từ mã nguồn thì khỏi chép tay. */
global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . NHAMA::t() );
$wpdb->exec_raw( 'CREATE TABLE ' . NHAMA::t() . ' (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	ma TEXT UNIQUE, ten TEXT DEFAULT "", sdt TEXT DEFAULT "", ngay TEXT, gio TEXT DEFAULT "",
	sl INTEGER DEFAULT 1, tien INTEGER DEFAULT 0, tt TEXT DEFAULT "giu_cho", ghi TEXT DEFAULT "",
	vao_luc TEXT NULL, tao TEXT, sua TEXT )' );
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . NHAMA::t_tien() );
$wpdb->exec_raw( 'CREATE TABLE ' . NHAMA::t_tien() . ' (
	id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT UNIQUE, luc TEXT, so_tien INTEGER DEFAULT 0,
	noi_dung TEXT DEFAULT "", ma TEXT DEFAULT "", kq TEXT DEFAULT "", tho TEXT NULL )' );
NHAMA::dat_pin( '246810' );
update_option( 'nhama_khoa_tien', 'khoa-thu-1234567890' );

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
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function goi( $than ) { return NHAMA::cong( new NM_Req( $than ) ); }

/* Ngày mai — để khỏi vướng luật "khung đã qua giờ" khi chạy bài lúc chiều tối. */
$mai = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) + 86400 );

// ================================================================== 1. Khung giờ theo cấu hình
$kh = NHAMA::ds_khung();
t( 'khung giờ dựng đúng theo cấu hình mặc định (10:10 → 11:45, cách 5 phút)',
	20 === count( $kh ) && '10:10' === $kh[0] && '11:45' === $kh[19], count( $kh ) );

$r = goi( array( 'viec' => 'goi', 'ngay' => $mai ) );
t( 'trang khách hỏi được lịch', ! empty( $r['ok'] ) && 20 === count( $r['khung'] ) );
/* 🔴 Trang khách CHỈ nhận con số. Bản HTML rời phải đẩy cả sổ xuống trình duyệt vì không có máy
   chủ đếm hộ — nghĩa là ai mở trang cũng đọc được tên và số điện thoại của mọi khách. */
t( 'lịch trả về KHÔNG kèm danh sách đơn', ! isset( $r['don'] ) );
t( 'lịch trả về không kèm số điện thoại ai cả',
	strpos( json_encode( $r, JSON_UNESCAPED_UNICODE ), 'sdt' ) === false );

// ================================================================== 2. Giữ chỗ
$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '10:10',
	'ten' => 'Phạm Tường Vi', 'sdt' => '0912345678', 'sl' => 3 ) );
t( 'giữ chỗ được', ! empty( $r['ok'] ), $r );
$ma1 = $r['don']['ma'];
t( 'mã theo khuôn GB-xxxxxxxx', (bool) preg_match( '/^GB-[A-Z2-9]{8}$/', $ma1 ), $ma1 );
/* Khách gõ tay mã ở cửa: một mã có chữ O cạnh số 0 là một cuộc cãi nhau, và người thua là khách. */
t( 'mã KHÔNG chứa ký tự dễ đọc nhầm (0 O 1 I L S Z)',
	! preg_match( '/[0O1ILSZ]/', substr( $ma1, 3 ) ), $ma1 );
t( 'tiền tính theo số người × giá vé', 300000 === (int) $r['don']['tien'], $r['don']['tien'] );
t( 'đơn mới ở trạng thái đang giữ chỗ', 'giu_cho' === $r['don']['tt'] );
t( 'chỗ bị trừ ngay dù CHƯA trả đồng nào', 3 === NHAMA::da_dat( $mai, '10:10' ) );

$r = goi( array( 'viec' => 'goi', 'ngay' => $mai ) );
t( 'lịch hiện đúng số đã đặt', 3 === (int) $r['khung'][0]['dat'], $r['khung'][0] );

// ================================================================== 3. Không bán quá sức chứa
$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '10:10',
	'ten' => 'Người thứ hai', 'sdt' => '0900000002', 'sl' => 8 ) );
t( 'đặt 8 người vào khung chỉ còn 7 chỗ thì BỊ CHỐI', empty( $r['ok'] ), $r );
t( 'câu chối nói rõ khung nào', strpos( (string) $r['error'], '10:10' ) !== false, $r['error'] );
t( 'đơn bị chối KHÔNG để lại rác trong sổ', 3 === NHAMA::da_dat( $mai, '10:10' ) );

$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '10:10',
	'ten' => 'Người thứ hai', 'sdt' => '0900000002', 'sl' => 7 ) );
t( 'đặt vừa đủ 7 chỗ còn lại thì được', ! empty( $r['ok'] ), $r );
$ma2 = $r['don']['ma'];
t( 'khung đầy đúng 10', 10 === NHAMA::da_dat( $mai, '10:10' ) );

$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '10:10',
	'ten' => 'Người thứ ba', 'sdt' => '0900000003', 'sl' => 1 ) );
t( 'khung đã đầy thì chối cả một người', empty( $r['ok'] ) );

/* Chỗ vừa bị lấy mất TRƯỚC khi người sau bấm — phép đếm ngay trước khi ghi chặn được ca này. */
$wpdb->insert( NHAMA::t(), array( 'ma' => 'GB-CHENNGANG', 'ten' => 'Chen ngang', 'sdt' => '0900000009',
	'ngay' => $mai, 'gio' => '10:15', 'sl' => 9, 'tien' => 900000, 'tt' => 'giu_cho',
	'tao' => current_time( 'mysql' ), 'sua' => current_time( 'mysql' ) ) );
$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '10:15',
	'ten' => 'Tới sau', 'sdt' => '0900000004', 'sl' => 3 ) );
t( 'người tới sau bị chối khi chỗ đã bị lấy mất', empty( $r['ok'] ), $r );
t( 'và sổ KHÔNG bị bán quá chỗ', 9 === NHAMA::da_dat( $mai, '10:15' ), NHAMA::da_dat( $mai, '10:15' ) );

/* ==========================================================================================
 * 🔴 CA HAI NGƯỜI BẤM TRONG CÙNG MỘT KHOẢNH KHẮC — chốt SAU KHI GHI
 * ==========================================================================================
 * Phép đếm trước khi ghi KHÔNG cứu được ca này: cả hai lượt cùng đếm, cùng thấy còn chỗ, rồi
 * cùng ghi. Thứ duy nhất cứu là phép kiểm lại SAU khi ghi (đếm phần đã chiếm của những đơn có
 * id ≤ đơn mình; ai vào sổ trước thì giữ chỗ, ai tràn ra thì tự rút).
 *
 * ⚠️ Bản trước của bài kiểm này giả lập bằng cách chèn đơn cạnh tranh TRƯỚC khi gọi `dat()` —
 *    và như thế thì phép đếm trước khi ghi đã chặn xong, chốt sau khi ghi không bao giờ được
 *    chạm tới. Phép thử vẫn xanh dù có gỡ hẳn chốt ấy đi: một phép thử canh nhầm chỗ còn tệ hơn
 *    không có, vì nó cho cảm giác đã canh rồi. Nay chèn đơn cạnh tranh ĐÚNG LÚC lượt đếm vừa
 *    chạy xong, bằng một lớp bọc quanh $wpdb.
 * ========================================================================================== */
class NM_Wpdb_Dua {
	public $that, $da_chen = false, $ngay, $gio;
	public function __construct( $that, $ngay, $gio ) {
		$this->that = $that; $this->ngay = $ngay; $this->gio = $gio;
	}
	/* Lượt đếm chỗ đầu tiên vừa trả lời xong thì một người khác chen vào — đúng khe hở thật. */
	public function get_var( $sql ) {
		$kq = $this->that->get_var( $sql );
		if ( ! $this->da_chen && strpos( $sql, 'SUM(sl)' ) !== false ) {
			$this->da_chen = true;
			$this->that->insert( NHAMA::t(), array( 'ma' => 'GB-DUANHAU1', 'ten' => 'Đua nhau',
				'sdt' => '0900000019', 'ngay' => $this->ngay, 'gio' => $this->gio, 'sl' => 8,
				'tien' => 800000, 'tt' => 'giu_cho', 'tao' => current_time( 'mysql' ),
				'sua' => current_time( 'mysql' ) ) );
		}
		return $kq;
	}
	public function __call( $ten, $dsl ) { return call_user_func_array( array( $this->that, $ten ), $dsl ); }
	public function __get( $k ) { return $this->that->$k; }
}
$that = $wpdb;
$wpdb = new NM_Wpdb_Dua( $that, $mai, '10:20' );
$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '10:20',
	'ten' => 'Bấm cùng lúc', 'sdt' => '0900000005', 'sl' => 5 ) );
$wpdb = $that;
t( 'hai người bấm cùng lúc: người tràn ra ngoài sức chứa bị chối', empty( $r['ok'] ), $r );
t( 'và đơn vừa ghi của người ấy đã tự rút khỏi sổ', 8 === NHAMA::da_dat( $mai, '10:20' ),
	NHAMA::da_dat( $mai, '10:20' ) );
t( 'người vào sổ trước thì GIỮ được chỗ',
	(int) $that->get_var( "SELECT COUNT(*) FROM " . NHAMA::t() . " WHERE ma='GB-DUANHAU1'" ) === 1 );

// ================================================================== 4. Chối đầu vào bậy
$xau = array(
	array( 'khung không có trong lịch', array( 'gio' => '03:33', 'sl' => 1 ) ),
	array( 'thiếu tên',                 array( 'gio' => '10:20', 'sl' => 1, 'ten' => 'A' ) ),
	array( 'số điện thoại cụt',         array( 'gio' => '10:20', 'sl' => 1, 'sdt' => '123' ) ),
	array( 'đoàn đông hơn sức chứa',    array( 'gio' => '10:20', 'sl' => 99 ) ),
);
foreach ( $xau as $ca ) {
	$than = array_merge( array( 'viec' => 'dat', 'ngay' => $mai, 'ten' => 'Khách thử',
		'sdt' => '0911111111' ), $ca[1] );
	$r = goi( $than );
	t( 'chối: ' . $ca[0], empty( $r['ok'] ), $r );
}
$r = goi( array( 'viec' => 'dat', 'ngay' => '2020-01-01', 'gio' => '10:20',
	'ten' => 'Khách thử', 'sdt' => '0911111111', 'sl' => 1 ) );
t( 'chối: đặt cho ngày đã qua', empty( $r['ok'] ), $r );

// ================================================================== 5. Vòng đời một đơn
$r = goi( array( 'viec' => 'bao_ck', 'ma' => $ma1 ) );
t( 'khách báo đã chuyển khoản -> chờ duyệt', 'cho_duyet' === $r['don']['tt'], $r );

/* Cửa của nhân viên: PIN -> thẻ, và MỌI việc sau đó phải mang thẻ. */
$r = goi( array( 'viec' => 'ds' ) );
t( 'không có thẻ thì KHÔNG xem được sổ', empty( $r['ok'] ) && 'het_phien' === $r['ma'], $r );
$r = goi( array( 'viec' => 'vao', 'pin' => '000000' ) );
t( 'PIN sai bị chối', empty( $r['ok'] ) );
$r = goi( array( 'viec' => 'vao', 'pin' => '246810' ) );
t( 'PIN đúng thì được phát thẻ', ! empty( $r['ok'] ) && strlen( $r['the'] ) > 20 );
$the = $r['the'];
/* 🔴 PIN cất bằng dấu băm — bảng cấu hình không được giữ PIN dạng đọc được. */
t( 'PIN không nằm dạng đọc được trong cấu hình',
	strpos( json_encode( $GLOBALS['VHCP_OPT'], JSON_UNESCAPED_UNICODE ), '246810' ) === false );

$r = goi( array( 'viec' => 'ds', 'the' => $the ) );
t( 'có thẻ thì xem được sổ', ! empty( $r['ok'] ) && count( $r['don'] ) >= 3, count( $r['don'] ) );

$r = goi( array( 'viec' => 'soat', 'the' => $the, 'ma' => $ma1 ) );
t( 'chưa duyệt tiền thì KHÔNG cho vào', empty( $r['ok'] ), $r );
t( 'và nói rõ đang chờ duyệt', strpos( (string) $r['error'], 'CHỜ KẾ TOÁN DUYỆT' ) !== false, $r['error'] );

$r = goi( array( 'viec' => 'doi', 'the' => $the, 'ma' => $ma1, 'tt' => 'cho_vao' ) );
t( 'kế toán duyệt được', 'cho_vao' === $r['don']['tt'] );

$r = goi( array( 'viec' => 'soat', 'the' => $the, 'ma' => $ma1 ) );
t( 'soát vé lần 1: cho vào', ! empty( $r['ok'] ), $r );
t( 'câu báo có tên và số khách', strpos( $r['chu'], 'Phạm Tường Vi' ) !== false
	&& strpos( $r['chu'], '3 khách' ) !== false, $r['chu'] );
/* 🔴 Đây là toàn bộ lý do có màn soát vé. */
$r = goi( array( 'viec' => 'soat', 'the' => $the, 'ma' => $ma1 ) );
t( 'soát vé lần 2: CHẶN', empty( $r['ok'] ) && strpos( (string) $r['error'], 'ĐÃ VÀO' ) !== false, $r );

$r = goi( array( 'viec' => 'soat', 'the' => $the, 'ma' => 'GB-KHONGCO1' ) );
t( 'mã lạ báo không có trong sổ', empty( $r['ok'] ) );
/* Quét QR ra cả một đường dẫn thì vẫn phải bóc được mã ra. */
$r = goi( array( 'viec' => 'soat', 'the' => $the, 'ma' => 'https://khmatrix.com/ve/' . $ma2 ) );
t( 'quét QR dạng đường dẫn vẫn bóc được mã', empty( $r['ok'] )
	&& strpos( (string) $r['error'], 'CHƯA THANH TOÁN' ) !== false, $r );

// ================================================================== 6. Huỷ đơn trả lại chỗ
$truoc = NHAMA::da_dat( $mai, '10:10' );
goi( array( 'viec' => 'doi', 'the' => $the, 'ma' => $ma2, 'tt' => 'huy', 'ghi' => 'không chuyển khoản' ) );
t( 'huỷ đơn thì chỗ được trả lại ngay', NHAMA::da_dat( $mai, '10:10' ) === $truoc - 7,
	NHAMA::da_dat( $mai, '10:10' ) );
$r = goi( array( 'viec' => 'goi', 'ngay' => $mai ) );
t( 'trang khách thấy chỗ vừa mở lại', 3 === (int) $r['khung'][0]['dat'] );
$r = goi( array( 'viec' => 'soat', 'the' => $the, 'ma' => $ma2 ) );
t( 'vé đã huỷ thì không cho vào', empty( $r['ok'] )
	&& strpos( (string) $r['error'], 'ĐÃ HUỶ' ) !== false, $r );

// ================================================================== 7. Khách tra cứu của mình
$r = goi( array( 'viec' => 'cua_toi', 'sdt' => '0912345678' ) );
t( 'tra cứu bằng SĐT ra đúng đơn của mình', ! empty( $r['ok'] ) && 1 === count( $r['don'] )
	&& $r['don'][0]['ma'] === $ma1, $r );
$r = goi( array( 'viec' => 'cua_toi', 'sdt' => '0900000404' ) );
t( 'số chưa từng đặt thì ra sổ rỗng, không phải lỗi', ! empty( $r['ok'] ) && 0 === count( $r['don'] ) );

// ================================================================== 8. Cấu hình
$r = goi( array( 'viec' => 'cai', 'the' => $the,
	'cf' => array( 'gia' => 150000, 'suc' => 6, 'mo' => '18:00', 'dong' => '18:30', 'buoc' => 10 ) ) );
t( 'lưu được cấu hình', ! empty( $r['ok'] ) && 150000 === (int) $r['cf']['gia'] );
t( 'đổi giờ mở/đóng thì lịch khung dựng lại đúng', 4 === count( $r['khung'] ), $r['khung'] );
$r = goi( array( 'viec' => 'cai', 'the' => $the, 'cf' => array( 'suc' => 0, 'buoc' => 0 ) ) );
t( 'sức chứa 0 / bước 0 bị nắn về 1 (không thì lịch khung thành vòng lặp vô tận)',
	1 === (int) $r['cf']['suc'] && 1 === (int) $r['cf']['buoc'], $r['cf'] );

goi( array( 'viec' => 'cai', 'the' => $the, 'pin' => '999888' ) );
t( 'đổi PIN xong PIN cũ hết dùng được', empty( goi( array( 'viec' => 'vao', 'pin' => '246810' ) )['ok'] ) );
t( 'PIN mới vào được', ! empty( goi( array( 'viec' => 'vao', 'pin' => '999888' ) )['ok'] ) );

// ================================================================== 9. Mã QR chuyển khoản
/* =============================================================================================
 * 🔴 QR SAI THÌ KHÔNG AI PHÁT HIỆN CHO TỚI LÚC KHÁCH ĐỨNG Ở QUẦY QUÉT MÃI KHÔNG RA
 * =============================================================================================
 * Nên bộ dựng QR chép nguyên từ plugin ghế (đã chạy trên tem dán 26 ghế), và ở đây kiểm bằng
 * phép ĐỌC NGƯỢC: dựng ma trận rồi đọc nó về lại chuỗi ban đầu. Không có phép ấy thì "chắc là
 * quét được" chỉ là một lời chúc.
 * =========================================================================================== */
$chuoi = NHAMA_QR::dung( '970418', '8888815678', 100000, 'GB9MVHMQKK' );
t( 'chuỗi VietQR mở đầu đúng khuôn EMVCo', strpos( $chuoi, '000201' ) === 0, substr( $chuoi, 0, 20 ) );
t( 'chuỗi mang mã ngân hàng và số tài khoản',
	strpos( $chuoi, '970418' ) !== false && strpos( $chuoi, '8888815678' ) !== false );
t( 'chuỗi mang số tiền (5406100000) và nội dung',
	strpos( $chuoi, '5406100000' ) !== false && strpos( $chuoi, 'GB9MVHMQKK' ) !== false, $chuoi );
t( 'CRC ở cuối, đúng 4 ký tự sau 6304',
	(bool) preg_match( '/6304[0-9A-F]{4}$/', $chuoi ), substr( $chuoi, -8 ) );
/* =============================================================================================
 * 🔴 CRC PHẢI SO VỚI MỘT CON SỐ CHUẨN, KHÔNG ĐƯỢC SO VỚI CHÍNH NÓ
 * =============================================================================================
 * Bản đầu của phép thử này tính lại CRC bằng chính hàm đang kiểm rồi so với chuỗi do chính hàm
 * ấy sinh ra — hai vế cùng một nguồn, nên nó ĐÚNG kể cả khi hàm sai. Phá thử cho thấy: đổi sang
 * biến thể CRC khác (xor cuối) thì bài vẫn xanh, trong khi mọi điện thoại sẽ từ chối quét.
 *
 * Nay so với giá trị kiểm CHUẨN của CRC-16/CCITT-FALSE: crc("123456789") = 0x29B1. Con số ấy
 * nằm trong đặc tả, không phụ thuộc mã của mình.
 * =========================================================================================== */
t( 'CRC đúng biến thể CCITT-FALSE (giá trị kiểm chuẩn 29B1)',
	'29B1' === NHAMA_QR::crc16( '123456789' ), NHAMA_QR::crc16( '123456789' ) );
$than_qr = substr( $chuoi, 0, -4 );
t( 'CRC trong chuỗi là CRC của phần thân', NHAMA_QR::crc16( $than_qr ) === substr( $chuoi, -4 ) );

$mt = NHAMA_QRVe::ma_tran( $chuoi, 'L' );
t( 'dựng được ma trận QR', is_array( $mt ) && count( $mt ) >= 21, is_array( $mt ) ? count( $mt ) : 'không' );
t( 'ĐỌC NGƯỢC ma trận ra đúng chuỗi ban đầu', NHAMA_QRVe::doc( $mt ) === $chuoi );
$svg = NHAMA_QRVe::svg( $mt, 220 );
t( 'xuất được SVG', strpos( $svg, '<svg' ) === 0 && strlen( $svg ) > 500, strlen( $svg ) );

/* --- đơn trả về phải kèm QR + tài khoản ------------------------------------------------- */
goi( array( 'viec' => 'cai', 'the' => $the,
	'cf' => array( 'bin' => '970418', 'so_tk' => '8888815678', 'ten_tk' => 'NGUYEN VAN A',
		'gia' => 100000, 'suc' => 10, 'mo' => '10:10', 'dong' => '11:45', 'buoc' => 5 ) ) );
$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '11:00',
	'ten' => 'Khách QR', 'sdt' => '0988777666', 'sl' => 2 ) );
t( 'đơn mới trả kèm mã QR', ! empty( $r['ok'] ) && ! empty( $r['don']['qr'] ), $r );
t( 'đơn mới trả kèm số tài khoản', '8888815678' === $r['don']['tk']['so_tk'] );
t( 'nhận ra tên ngân hàng từ BIN', 'BIDV' === $r['don']['tk']['ten_nh'] );
/* Nội dung chuyển khoản BỎ DẤU GẠCH: nhiều app ngân hàng lọc ký tự đặc biệt, để nguyên GB-XXXX
   thì app cắt thành GBXXXX và hai bên không khớp nhau nữa. */
t( 'nội dung chuyển khoản bỏ dấu gạch', strpos( $r['don']['nd'], '-' ) === false
	&& $r['don']['nd'] === str_replace( '-', '', $r['don']['ma'] ), $r['don']['nd'] );
$ma_qr = $r['don']['ma'];

/* QR của đơn phải mang ĐÚNG số tiền của đơn ấy — dựng một lần cho mọi đơn là khách nào cũng
   chuyển đúng một số tiền, và kế toán ngồi dò tay. */
$don = NHAMA::kem_qr( NHAMA::don_theo_ma( $ma_qr ) );
$chuoi2 = NHAMA_QR::dung( '970418', '8888815678', 200000, $don['nd'] );
t( 'QR của đơn mang đúng số tiền của đơn (2 người × 100.000)',
	NHAMA_QRVe::doc( NHAMA_QRVe::ma_tran( $chuoi2, 'L' ) ) === $chuoi2
	&& strpos( $chuoi2, '5406200000' ) !== false, $chuoi2 );

/* =============================================================================================
 * 🔴 CHỮ CHO KHÁCH CHÉP PHẢI LÀ ĐÚNG CÁI NẰM TRONG QR
 * =============================================================================================
 * Khách có hai đường trả tiền: quét QR, hoặc chép chữ gõ tay. Hai đường mà đi từ hai nguồn thì
 * sớm muộn lệch nhau, và lệch IM LẶNG — người quét thì tiền vào đúng đơn, người gõ tay thì tiền
 * vào tài khoản mà không ai biết của thiệp nào. Nên `nd` hiện trên màn được BÓC NGƯỢC ra từ
 * chính chuỗi VietQR, không tính song song.
 * =========================================================================================== */
$don_qr = NHAMA::kem_qr( NHAMA::don_theo_ma( $ma_qr ) );
$chuoi3 = NHAMA_QR::dung( '970418', '8888815678', (int) $don_qr['tien'], NHAMA::noi_dung( $ma_qr ) );
t( 'nội dung hiện ra = đúng trường nội dung nằm trong QR',
	$don_qr['nd'] === NHAMA_QR::boc( $chuoi3, '62', '08' ), $don_qr['nd'] );
t( 'số tiền hiện ra = đúng số tiền nằm trong QR',
	(int) $don_qr['tien_qr'] === (int) NHAMA_QR::boc( $chuoi3, '54' )
	&& (int) $don_qr['tien_qr'] === (int) $don_qr['tien'], $don_qr['tien_qr'] );
/* Trường 38 lồng hai tầng: 38 → 01 (nhóm ngân hàng) → 00 = BIN, 01 = số tài khoản. Viết rõ ra
   đây vì phép thử đầu tiên của em bóc thiếu một tầng và tưởng bộ bóc sai — hoá ra bộ bóc đúng. */
$nhom_nh = NHAMA_QR::boc( NHAMA_QR::boc( $chuoi3, '38' ), '01' );
t( 'bóc ngược lấy đúng số tài khoản trong QR', '8888815678' === NHAMA_QR::boc( $nhom_nh, '01' ), $nhom_nh );
t( 'bóc ngược lấy đúng mã ngân hàng trong QR', '970418' === NHAMA_QR::boc( $nhom_nh, '00' ) );
/* Bóc một trường không có thì trả rỗng, không nổ và không trả bừa trường bên cạnh. */
t( 'bóc trường không tồn tại thì trả rỗng', '' === NHAMA_QR::boc( $chuoi3, '99' ) );

/* --- chưa khai tài khoản thì NÓI THẲNG, không vẽ QR trỏ vào tài khoản rỗng ---------------- */
goi( array( 'viec' => 'cai', 'the' => $the, 'cf' => array( 'bin' => '', 'so_tk' => '', 'ten_tk' => '' ) ) );
update_option( 'vhg_bin', '' ); update_option( 'vhg_so_tk', '' );
$r = goi( array( 'viec' => 'cua_toi', 'sdt' => '0988777666' ) );
t( 'chưa khai tài khoản: đơn báo THIẾU chứ không dựng QR bừa',
	1 === (int) $r['don'][0]['tk']['thieu'] && empty( $r['don'][0]['qr'] ), $r['don'][0] );
t( 'nhưng thiệp vẫn giữ chỗ bình thường', 'giu_cho' === $r['don'][0]['tt'] );

/* --- mượn tài khoản của plugin ghế khi ô của mình bỏ trống -------------------------------- */
update_option( 'vhg_bin', '970436' ); update_option( 'vhg_so_tk', '1234567890' );
update_option( 'vhg_ten_tk', 'CONG TY KH' );
$tk = NHAMA::tk();
t( 'bỏ trống thì mượn tài khoản đã khai ở plugin ghế',
	'970436' === $tk['bin'] && '1234567890' === $tk['so_tk'] && 'Vietcombank' === $tk['ten_nh'], $tk );
goi( array( 'viec' => 'cai', 'the' => $the, 'cf' => array( 'bin' => '970418', 'so_tk' => '8888815678' ) ) );
t( 'khai riêng thì ĐÈ lên tài khoản mượn', '8888815678' === NHAMA::tk()['so_tk'] );

// ================================================================== 10. Cổng tiền về tự động
/* =============================================================================================
 * 🔴 KHÔNG CÓ CỔNG NÀY THÌ KẾ TOÁN NGỒI SOI SAO KÊ, KHÁCH ĐỨNG Ở CỬA ĐỢI
 * =============================================================================================
 * Bốn luật của cổng, học từ cổng tiền plugin ghế, và bài này canh đủ bốn:
 *   1. trả 200 cho mọi gói đã qua khoá, kể cả gói không đọc được (khác 2xx là bên gửi TẮT webhook)
 *   2. ghi sổ mọi lượt, kể cả lượt bị từ chối
 *   3. `ref` UNIQUE — bắn lại cùng giao dịch không duyệt hai lần
 *   4. sai khoá thì chối, và chối cũng phải để lại dấu
 * =========================================================================================== */
function ban_tien( $goi, $khoa = 'khoa-thu-1234567890' ) {
	$GLOBALS['NHAMA_THAN'] = is_string( $goi ) ? $goi : wp_json_encode( $goi );
	$_GET['token'] = $khoa;
	$GLOBALS['VHCP_MA_HTTP'] = 0;
	ob_start(); NHAMA::cong_tien(); $ra = ob_get_clean();
	$j = json_decode( $ra, true );
	if ( is_array( $j ) ) { $j['_ma_http'] = (int) $GLOBALS['VHCP_MA_HTTP']; }
	return $j;
}
function so_dong_tien() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . NHAMA::t_tien() );
}

goi( array( 'viec' => 'cai', 'the' => $the, 'cf' => array( 'gia' => 100000, 'suc' => 10,
	'mo' => '10:10', 'dong' => '11:45', 'buoc' => 5, 'bin' => '970418', 'so_tk' => '8888815678' ) ) );
$r = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '11:10',
	'ten' => 'Khách tiền về', 'sdt' => '0977000111', 'sl' => 2 ) );
$ma_t = $r['don']['ma'];
$nd_t = $r['don']['nd'];

/* --- sai khoá: chối, mã 401, nhưng VẪN để lại dấu trong sổ --- */
$truoc = so_dong_tien();
$r = ban_tien( array( 'content' => $nd_t, 'transferAmount' => 200000, 'referenceCode' => 'X1' ), 'khoa-bay' );
t( 'sai khoá thì chối', empty( $r['ok'] ), $r );
/* 🔴 SAI KHOÁ LÀ CA DUY NHẤT ĐƯỢC TRẢ KHÁC 200 — để người đi cấu hình thấy ngay. */
t( 'sai khoá trả 401', 401 === $r['_ma_http'], $r['_ma_http'] );
t( 'sai khoá vẫn để lại dấu trong sổ', so_dong_tien() === $truoc + 1 );
t( 'sai khoá KHÔNG duyệt đơn', 'giu_cho' === NHAMA::don_theo_ma( $ma_t )['tt'] );

/* --- gói không đọc được: vẫn 200, và GIỮ NGUYÊN VĂN để xử tay --- */
$r = ban_tien( 'đây không phải JSON' );
t( 'gói không đọc được vẫn trả ok (khác 2xx là bên gửi tắt webhook)', ! empty( $r['ok'] ), $r );
/* =============================================================================================
 * 🔴 MÃ HTTP PHẢI LÀ 200, KHÔNG CHỈ THÂN TIN NÓI "ok"
 * =============================================================================================
 * Bên gửi đọc MÃ HTTP chứ không đọc chữ trong thân. Thấy 5xx là họ đẩy lại vài lần rồi TẮT HẲN
 * webhook — và từ lúc ấy tiền về mà hệ thống không hay biết, im lặng, không ai phát hiện cho tới
 * khi khách đứng ở cửa với thiệp chưa duyệt.
 * ⚠️ Bản đầu của bài kiểm chỉ soi `$r['ok']` trong thân tin. Phá thử: đổi `status_header(200)`
 *    thành 500 thì bài vẫn xanh — canh nhầm chỗ. Nay canh đúng con số bên gửi nhìn vào.
 * =========================================================================================== */
t( 'gói không đọc được vẫn trả MÃ HTTP 200', 200 === $r['_ma_http'], $r['_ma_http'] );
t( 'gói không đọc được vẫn vào sổ', so_dong_tien() >= $truoc + 2 );

/* --- nội dung không mang mã thiệp --- */
$r = ban_tien( array( 'content' => 'CT DEN TIEN AN TRUA', 'transferAmount' => 50000, 'referenceCode' => 'X2' ) );
t( 'nội dung không mang mã thiệp: vào sổ chờ xử tay, không nổ', ! empty( $r['ok'] ) );
t( 'và vẫn trả mã HTTP 200', 200 === $r['_ma_http'], $r['_ma_http'] );

/* --- tiền RA thì bỏ qua (SePay bắn cả hai chiều) --- */
$r = ban_tien( array( 'content' => $nd_t, 'transferAmount' => 200000, 'referenceCode' => 'X3',
	'transferType' => 'out' ) );
t( 'lượt tiền RA không được duyệt thiệp', 'giu_cho' === NHAMA::don_theo_ma( $ma_t )['tt'], $r );

/* --- thiếu tiền: KHÔNG duyệt, nhưng ghi vào đơn để kế toán gọi khách --- */
$r = ban_tien( array( 'content' => $nd_t, 'transferAmount' => 100000, 'referenceCode' => 'X4' ) );
$don_t = NHAMA::don_theo_ma( $ma_t );
t( 'thiếu tiền thì KHÔNG duyệt', 'giu_cho' === $don_t['tt'], $don_t['tt'] );
t( 'thiếu tiền thì ghi vào đơn cho kế toán thấy',
	strpos( $don_t['ghi'], 'THIẾU' ) !== false, $don_t['ghi'] );

/* --- đủ tiền: TỰ DUYỆT --- */
$r = ban_tien( array( 'content' => 'CT DEN:' . $nd_t . ' TU 0977000111',
	'transferAmount' => 200000, 'referenceCode' => 'FT2609081234' ) );
$don_t = NHAMA::don_theo_ma( $ma_t );
t( 'lượt duyệt thành công cũng trả mã HTTP 200', 200 === $r['_ma_http'], $r['_ma_http'] );
t( 'đủ tiền thì thiệp TỰ chuyển sang Chờ Check-in', 'cho_vao' === $don_t['tt'], $don_t );
t( 'ghi lại mã tham chiếu của ngân hàng vào đơn',
	strpos( $don_t['ghi'], 'FT2609081234' ) !== false, $don_t['ghi'] );
/* Nội dung ngân hàng chèn thêm chữ quanh mã — vẫn phải bóc ra đúng. */
t( 'bóc được mã thiệp lẫn trong câu chữ của ngân hàng',
	$ma_t === NHAMA::ma_tu_noi_dung( 'CT DEN:' . $nd_t . ' TU 0977000111' ) );
t( 'bóc được cả khi nội dung còn dấu gạch', $ma_t === NHAMA::ma_tu_noi_dung( 'ND ' . $ma_t . ' xxx' ) );

/* --- 🔴 BẮN LẠI CÙNG GIAO DỊCH: không được đếm hai lần --- */
$truoc = so_dong_tien();
$r = ban_tien( array( 'content' => $nd_t, 'transferAmount' => 200000, 'referenceCode' => 'FT2609081234' ) );
t( 'bắn lại cùng mã tham chiếu: không thêm dòng sổ', so_dong_tien() === $truoc, so_dong_tien() );
t( 'và trả lời rõ là đã xử lý trước đó',
	strpos( (string) $r['ghi_chu'], 'đã xử lý' ) !== false, $r );

/* --- thiệp đã hợp lệ thì kèm QR VÀO CỬA, và KHÔNG kèm QR chuyển khoản nữa --- */
$don_qr2 = NHAMA::kem_qr( NHAMA::don_theo_ma( $ma_t ) );
t( 'thiệp đã trả tiền có QR vào cửa', ! empty( $don_qr2['qr_ve'] ) );
/* Hai mã đen trắng giống hệt nhau nằm cạnh nhau là khách quét nhầm cái nọ ra cái kia. */
$mt_ve = NHAMA_QRVe::ma_tran( $ma_t, 'M' );
t( 'QR vào cửa đọc ngược ra ĐÚNG mã thiệp', NHAMA_QRVe::doc( $mt_ve ) === $ma_t );

/* --- tiền về cho thiệp đã huỷ: không duyệt lại, và kêu lên để còn hoàn tiền --- */
$r2 = goi( array( 'viec' => 'dat', 'ngay' => $mai, 'gio' => '11:15',
	'ten' => 'Khách huỷ', 'sdt' => '0977000222', 'sl' => 1 ) );
goi( array( 'viec' => 'doi', 'the' => $the, 'ma' => $r2['don']['ma'], 'tt' => 'huy', 'ghi' => 'huỷ' ) );
ban_tien( array( 'content' => $r2['don']['nd'], 'transferAmount' => 100000, 'referenceCode' => 'X9' ) );
$don_h = NHAMA::don_theo_ma( $r2['don']['ma'] );
t( 'tiền về cho thiệp đã huỷ: không tự mở lại', 'huy' === $don_h['tt'] );
t( 'và ghi rõ là cần hoàn tiền', strpos( $don_h['ghi'], 'hoàn tiền' ) !== false, $don_h['ghi'] );

/* --- gửi Zalo chưa cấu hình thì im lặng bỏ qua, KHÔNG làm hỏng lượt tiền về --- */
t( 'chưa khai token Zalo thì gửi trả false, không nổ', false === NHAMA::gui_zalo( $ma_t ) );
$nk = (array) get_option( 'nhama_nk_zalo', array() );
t( 'và ghi vào nhật ký Zalo để biết vì sao chưa gửi',
	count( $nk ) > 0 && 'chua_cau_hinh' === $nk[0]['kq'], $nk ? $nk[0] : null );
t( 'nhưng thiệp vẫn hợp lệ', 'cho_vao' === NHAMA::don_theo_ma( $ma_t )['tt'] );

/* --- mở thiệp theo mã (đường dẫn trong tin Zalo) --- */
$r = goi( array( 'viec' => 've', 'ma' => $ma_t ) );
t( 'mở được thiệp bằng mã (link Zalo)', ! empty( $r['ok'] ) && $r['don'][0]['ma'] === $ma_t );
t( 'mã bịa thì báo không thấy', empty( goi( array( 'viec' => 've', 'ma' => 'GB-KHONGCO2' ) )['ok'] ) );

/* --- sổ tiền về đọc được ở màn quản trị --- */
$r = goi( array( 'viec' => 'tien', 'the' => $the ) );
t( 'màn quản trị đọc được sổ tiền về', ! empty( $r['ok'] ) && count( $r['tien'] ) > 0 );
t( 'và in ra địa chỉ webhook kèm khoá',
	strpos( $r['duong'], 'nha-ma-tien' ) !== false && strpos( $r['duong'], 'token=' ) !== false, $r['duong'] );
t( 'sổ tiền về đòi thẻ, khách không xem được', empty( goi( array( 'viec' => 'tien' ) )['ok'] ) );

// ================================================================== 11. Nối Zalo OA & làm mới token
/* =============================================================================================
 * 🔴 ACCESS TOKEN CỦA ZALO SỐNG ~1 GIỜ — nên chỗ đáng canh không phải "gửi được tin không", mà là
 *    "một giờ sau còn gửi được không".
 * Máy chủ Zalo được thay bằng máy chủ giả (`$GLOBALS['NHAMA_HTTP']`) — phần nói chuyện thật với
 * Zalo là phần duy nhất bài này không chạy được.
 * =========================================================================================== */
$goi_zalo = array();          // ghi lại mọi lượt gọi để soi
function zalo_gia( $tra_ve ) {
	$GLOBALS['NHAMA_HTTP'] = function ( $url, $args ) use ( $tra_ve ) {
		$GLOBALS['goi_zalo'][] = array( 'url' => $url, 'args' => $args );
		return array( 'body' => wp_json_encode( $tra_ve ) );
	};
}
goi( array( 'viec' => 'cai', 'the' => $the,
	'cf' => array( 'zalo_app_id' => '189487077940231327', 'zalo_secret' => 'BIMAT' ) ) );

$tt = NHAMA::zalo_tinh_trang();
t( 'khai app rồi thì tình trạng báo có app', ! empty( $tt['co_app'] ) );
t( 'nhưng CHƯA nối', empty( $tt['da_noi'] ) );
t( 'địa chỉ callback đúng đường /nha-ma-zalo',
	substr( $tt['callback'], -12 ) === '/nha-ma-zalo', $tt['callback'] );
t( 'địa chỉ cấp quyền mang app_id và redirect_uri',
	strpos( $tt['url_noi'], 'app_id=189487077940231327' ) !== false
	&& strpos( $tt['url_noi'], 'redirect_uri=' ) !== false, $tt['url_noi'] );
/* 🔴 `state` chống ai đó dụ trình duyệt của anh nối OA của HỌ vào website của mình. */
t( 'và mang mã trạng thái state', strpos( $tt['url_noi'], 'state=' ) !== false );

/* 🔴 NÚT KẾT NỐI PHẢI ĐỨNG YÊN. Màn quản trị nạp lại mỗi 6 giây và mỗi lượt đều đọc tình trạng
   Zalo; nếu mỗi lượt sinh một `state` mới thì cái nút anh đang nhìn mang mã đã chết, bấm vào là
   "state không khớp" — hỏng đúng lúc lần đầu nối, và không ai đoán ra vì sao. */
$url_1  = NHAMA::zalo_url_noi();
$st_1   = get_transient( 'nhama_zalo_state' );
$url_2  = NHAMA::zalo_url_noi();
t( '🔴 đọc tình trạng nhiều lượt thì địa chỉ nối KHÔNG đổi', $url_1 === $url_2 );
t( 'và mã state trong kho vẫn là mã cũ', $st_1 === get_transient( 'nhama_zalo_state' ) );
/* Nhưng hết hạn (hoặc dùng xong) thì phải sinh mã mới, không dùng lại vĩnh viễn. */
delete_transient( 'nhama_zalo_state' );
t( 'hết hạn thì sinh mã mới', NHAMA::zalo_url_noi() !== $url_1 );

/* --- Zalo gọi về với mã: đổi lấy token --- */
function zalo_ve( $ma, $state, $oa = '' ) {
	$_GET = array( 'code' => $ma, 'state' => $state );
	if ( '' !== $oa ) { $_GET['oa_id'] = $oa; }
	ob_start(); NHAMA::zalo_nhan_ma(); return ob_get_clean();
}
$state_that = get_transient( 'nhama_zalo_state' );
zalo_gia( array( 'access_token' => 'AT-1', 'refresh_token' => 'RT-1', 'expires_in' => 3600 ) );
$man = zalo_ve( 'CODE-1', 'state-bay-ba' );
t( '🔴 state không khớp thì CHỐI', strpos( $man, 'không khớp' ) !== false );
t( 'và KHÔNG cất token nào', '' === (string) NHAMA::cf()['zalo_refresh'] );

/* 🔴 ZALO TỪ CHỐI THÌ PHẢI ĐỌC ĐƯỢC VÌ SAO. Zalo không gửi `code` thì kèm lý do trên địa chỉ;
   nuốt mất mấy chữ ấy là anh chỉ còn "thử lại đi", mà thử mười lần vẫn hỏng đúng chỗ cũ. */
$_GET = array( 'error' => '-201', 'error_description' => 'redirect uri is invalid' );
ob_start(); NHAMA::zalo_nhan_ma(); $man_loi = ob_get_clean();
t( '🔴 hiện nguyên văn mã lỗi của Zalo', strpos( $man_loi, '-201' ) !== false, substr( $man_loi, 0, 300 ) );
t( '🔴 hiện nguyên văn câu giải thích của Zalo',
	strpos( $man_loi, 'redirect uri is invalid' ) !== false );
$nk = (array) get_option( 'nhama_nk_zalo', array() );
t( 'và ghi luôn vào nhật ký Zalo để xem lại sau', isset( $nk[0] ) && 'noi_hong' === $nk[0]['kq'] );
$_GET = array();
ob_start(); NHAMA::zalo_nhan_ma(); $man_im = ob_get_clean();
t( 'Zalo im lặng thì vẫn nói rõ là im lặng', strpos( $man_im, 'không nói vì sao' ) !== false );

$man = zalo_ve( 'CODE-1', $state_that, 'OA123' );
t( 'state khớp thì nối được', strpos( $man, 'thành công' ) !== false, substr( $man, 0, 200 ) );
t( 'cất refresh token', 'RT-1' === (string) NHAMA::cf()['zalo_refresh'] );
t( 'cất access token', 'AT-1' === (string) NHAMA::cf()['zalo_token'] );
t( 'nhớ luôn OA id để hiện cho biết đang nối với ai', 'OA123' === (string) NHAMA::cf()['zalo_oa'] );
/* Trừ hao 120 giây: token hết hạn đúng lúc đang gửi thì tin ấy mất. */
$het = (int) NHAMA::cf()['zalo_het'];
t( 'hạn token trừ hao ~2 phút', $het > time() + 3400 && $het <= time() + 3481, $het - time() );
$g = end( $goi_zalo );
t( 'gọi đúng cửa đổi mã của Zalo', strpos( $g['url'], 'oa/access_token' ) !== false, $g['url'] );
t( 'khoá bí mật đi trong header, KHÔNG đi trên đường dẫn',
	'BIMAT' === $g['args']['headers']['secret_key'] && strpos( $g['url'], 'BIMAT' ) === false );
t( 'gửi đúng kiểu authorization_code', 'authorization_code' === $g['args']['body']['grant_type'] );

/* --- token còn hạn thì KHÔNG gọi lại Zalo --- */
$truoc_so = count( $goi_zalo );
t( 'token còn hạn thì dùng lại', 'AT-1' === NHAMA::zalo_token_song() );
t( 'và không tốn thêm lượt gọi nào', count( $goi_zalo ) === $truoc_so );

/* --- 🔴 HẾT HẠN THÌ TỰ LÀM MỚI, và phải LƯU ĐÈ refresh token mới --- */
$cf_het = NHAMA::cf(); $cf_het['zalo_het'] = time() - 10; update_option( 'nhama_cf', $cf_het );
zalo_gia( array( 'access_token' => 'AT-2', 'refresh_token' => 'RT-2', 'expires_in' => 3600 ) );
t( 'hết hạn thì tự làm mới', 'AT-2' === NHAMA::zalo_token_song() );
$g = end( $goi_zalo );
t( 'làm mới bằng refresh_token cũ', 'RT-1' === $g['args']['body']['refresh_token']
	&& 'refresh_token' === $g['args']['body']['grant_type'], $g['args']['body'] );
/* 🔴 Zalo cấp refresh token MỚI mỗi lần làm mới, cái cũ hết dùng. Không lưu đè là lần sau hỏng,
   mà hỏng IM LẶNG — chỉ lộ ra khi có khách không nhận được vé. */
t( '🔴 LƯU ĐÈ refresh token mới', 'RT-2' === (string) NHAMA::cf()['zalo_refresh'] );

/* --- Zalo chối thì nói ra, không im, và KHÔNG xoá mất refresh cũ --- */
$cf_het = NHAMA::cf(); $cf_het['zalo_het'] = time() - 10; update_option( 'nhama_cf', $cf_het );
zalo_gia( array( 'error' => -216, 'message' => 'Refresh token is invalid' ) );
t( 'làm mới hỏng thì trả rỗng, không nổ', '' === NHAMA::zalo_token_song() );
$nk = (array) get_option( 'nhama_nk_zalo', array() );
t( 'và ghi NGUYÊN VĂN câu Zalo nói vào nhật ký',
	'lam_moi_hong' === $nk[0]['kq'] && strpos( $nk[0]['chu'], '216' ) !== false, $nk[0] );
t( 'refresh token cũ vẫn còn để thử lại', 'RT-2' === (string) NHAMA::cf()['zalo_refresh'] );

/* --- gửi vé khi chưa nối: im lặng bỏ qua, KHÔNG làm hỏng lượt tiền về --- */
$cf_x = NHAMA::cf(); $cf_x['zalo_refresh'] = ''; $cf_x['zalo_token'] = ''; update_option( 'nhama_cf', $cf_x );
t( 'chưa nối thì gửi trả false', false === NHAMA::gui_zalo( $ma_t ) );
t( 'và thiệp vẫn hợp lệ', 'cho_vao' === NHAMA::don_theo_ma( $ma_t )['tt'] );
unset( $GLOBALS['NHAMA_HTTP'] );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — máy chủ giữ số chỗ, và mã QR đọc ngược ra đúng chuỗi.\n";
