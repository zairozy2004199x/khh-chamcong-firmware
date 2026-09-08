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
NHAMA::dat_pin( '246810' );

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

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — máy chủ giữ số chỗ, không ai bán quá một khung giờ.\n";
