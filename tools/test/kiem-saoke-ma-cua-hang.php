<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ — MÃ CỬA HÀNG CỦA VIỆT QR LÀ ĐƯỜNG DUY NHẤT BIẾT DÒNG "PaymentForOrder" CỦA MÁY NÀO
 *
 * Anh Thắng 12/09/2026, hai ảnh cạnh nhau — bảng "Từ cổng Việt QR" đầy dòng *"chưa rõ máy"* với
 * nội dung `VQR2637642208V7L PaymentForOrder`, còn bản kết xuất của cổng thì mỗi giao dịch có
 * thêm **Mã cửa hàng**: *"một số giao dịch nội dung không rõ ràng, mà webhook gửi về thì thiếu
 * thông tin, vậy để xác nhận giao dịch đó của ai, thì mình sẽ tải thẳng sao kê của bên VietQR.
 * Nó có đủ các trường để xác định giao dịch đó là của máy này."*
 *
 * 🔴 BÀI NÀY LÀ BỘ THỬ ĐẦU TIÊN CỦA `vhcp-saoke`. Trước nó, plugin sao kê KHÔNG có phép thử nào
 *    (20 bài trong `tools/test/` đều của ghế/vé), nên mọi thay đổi ở đây là sửa mù.
 *
 * ⚠️ CHẠY LỚP THẬT, không chép luật ra đây: nạp thẳng `vhcp-saoke.php` với một bệ đỡ WordPress
 *    giả (option trong mảng, `$wpdb` giả). Chép luật ra bài thử là bài thử canh chính nó.
 *
 * Chạy: php tools/test/kiem-saoke-ma-cua-hang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC = dirname( dirname( __DIR__ ) );
$SRC = file_get_contents( $GOC . '/vhcp-saoke/vhcp-saoke.php' );
$APP = file_get_contents( $GOC . '/vhcp-saoke/app.html' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ WORDPRESS GIẢ — đủ để nạp lớp, không hơn.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
define( 'ABSPATH', $GOC . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
$GLOBALS['OPT'] = array();
function get_option( $k, $m = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $m; }
function update_option( $k, $v, $a = null ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['OPT'][ $k ] ); return true; }
function add_option( $k, $v ) { if ( ! isset( $GLOBALS['OPT'][ $k ] ) ) { $GLOBALS['OPT'][ $k ] = $v; } }
function add_action() {} function add_filter() {} function add_shortcode() {}
function register_activation_hook() {} function register_deactivation_hook() {}
function register_rest_route() {} function wp_next_scheduled() { return false; }
function wp_schedule_event() {} function wp_clear_scheduled_hook() {} function flush_rewrite_rules() {}
function current_time( $f ) { return 'mysql' === $f ? '2026-09-12 10:00:00' : time(); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o | JSON_UNESCAPED_UNICODE ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function admin_url( $s = '' ) { return '/wp-admin/' . $s; }
function wp_remote_get() { return new WP_Error( 'off', 'không ra mạng trong bài thử' ); }
function wp_remote_post() { return new WP_Error( 'off', 'không ra mạng trong bài thử' ); }
function wp_remote_retrieve_body() { return ''; }
function wp_remote_retrieve_response_code() { return 0; }
class WP_Error { public $c; public $m; public function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; } public function get_error_message() { return $this->m; } }

/** $wpdb giả: chỉ đủ cho `luu_cong()` — get_row theo khoá, insert, update. */
class FakeWpdb {
	public $prefix = 'wp_';
	public $hang = array();          // bảng saoke_cong trong bộ nhớ
	public $so_insert = 0; public $so_update = 0;
	public function get_charset_collate() { return ''; }
	public function prepare( $sql, ...$a ) {
		if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
		foreach ( $a as $v ) {
			$sql = preg_replace( '/%s/', "'" . str_replace( "'", "''", (string) $v ) . "'", $sql, 1 );
			$sql = preg_replace( '/%d/', (string) (int) $v, $sql, 1 );
		}
		return $sql;
	}
	public function get_row( $sql, $out = null ) {
		if ( ! preg_match( "/khoa='([^']*)'/", $sql, $m ) ) { return null; }
		foreach ( $this->hang as $h ) { if ( (string) $h['khoa'] === $m[1] ) { return $h; } }
		return null;
	}
	public function get_var( $sql ) { return null; }
	public function get_results( $sql, $out = null ) { return array(); }
	public function insert( $tbl, $data ) {
		$data['id'] = count( $this->hang ) + 1;
		$this->hang[] = $data; $this->so_insert++; return 1;
	}
	public function update( $tbl, $data, $where ) {
		foreach ( $this->hang as $i => $h ) {
			if ( (int) $h['id'] === (int) $where['id'] ) {
				$this->hang[ $i ] = array_merge( $h, $data ); $this->so_update++; return 1;
			}
		}
		return 0;
	}
	public function query( $sql ) { return 0; }
	public function esc_like( $s ) { return $s; }
}
$GLOBALS['wpdb'] = new FakeWpdb();

require_once $GOC . '/vhcp-saoke/vhcp-saoke.php';
t( 'nạp được lớp SAOKE_App', class_exists( 'SAOKE_App' ) );

/** Gọi hàm private/protected cho việc thử — chạy lõi thật, không chép luật. */
function goi( $ten, $args = array() ) {
	$m = new ReflectionMethod( 'SAOKE_App', $ten );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. CỘT `ma_ch` PHẢI CÓ THẬT, VÀ PHIÊN BẢN BẢNG PHẢI TĂNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 Không tăng `VER_TBL` là `bao_dam_bang()` thoát ngay ở dòng đầu trên MỌI site đang chạy —
 *    cột `ma_ch` không bao giờ được tạo, và tính năng này im lặng không hoạt động ở đúng nơi
 *    cần nó. Bảng mới chỉ dựng đúng cho site cài mới, nên lỗi này rất dễ lọt.
 */
t( '🔴 bảng saoke_cong có cột ma_ch', false !== strpos( $SRC, "ma_ch VARCHAR(40) NOT NULL DEFAULT ''" ) );
t( 'và có KEY để tra cho nhanh', false !== strpos( $SRC, 'KEY ma_ch (ma_ch)' ) );
t( "🔴 VER_TBL đã tăng (không tăng thì site cũ KHÔNG có cột)",
	false !== strpos( $SRC, "const VER_TBL = '3';" ), 'VER_TBL' );
/* Hai câu SELECT đọc bảng cổng đều phải lấy cột ấy — thiếu một câu là màn ấy vẫn "chưa rõ máy". */
t( '🔴 câu SELECT của bảng Sao Kê cổng có lấy ma_ch',
	false !== strpos( $SRC, 'so_tk, noi_dung, diem_ban, ma_ch, doc_duoc' ) );
t( '🔴 câu SELECT của phép gom tiền theo mã nộp cũng lấy ma_ch',
	false !== strpos( $SRC, 'SELECT thoi_diem, so_tien, noi_dung, diem_ban, ma_ch FROM' ) );
/* Và luật suy ra máy chỉ được viết MỘT chỗ. Bản trước chép hai lần; hai bản chép của một luật
   thì sớm muộn lệch, mà lệch ở đây là cùng một giao dịch màn này tính máy A, màn kia bỏ vào
   "chưa rõ". */
teq( '🔴 luật suy ra máy chỉ còn MỘT chỗ viết', 1,
	substr_count( $SRC, "\$ten = self::cong_ten_may( \$noi_dung );" ) );
/* Ba nơi gọi: bảng Sao Kê cổng · phép gom tiền theo mã nộp · lượt nạp file (đếm "chưa rõ máy").
   Ba nơi, MỘT luật — đó mới là điều đáng canh. */
teq( 'và ba nơi cần biết máy đều gọi đúng hàm chung ấy', 3,
	substr_count( $SRC, 'self::cong_may_dong(' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. CHUẨN HOÁ MÃ CỬA HÀNG — mã là chuỗi máy sinh, so phải khít
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'mã cửa hàng: hoa hết', 'VUOHU3TIJ2', goi( 'vqr_ma_ch', array( 'vuohu3tij2' ) ) );
teq( 'bỏ khoảng trắng hai đầu và ở giữa', 'EZFY9HCIR0', goi( 'vqr_ma_ch', array( ' EZFY9 HCIR0 ' ) ) );
teq( 'rỗng vẫn là rỗng', '', goi( 'vqr_ma_ch', array( '   ' ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. NẠP "DANH SÁCH CỬA HÀNG" — đúng bảng anh Thắng gửi
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$GLOBALS['OPT']['saoke_pin'] = '1234';
$ds_file = array(
	array( 'EZFY9HCIR0', 'LM-NSG 01', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),
	array( 'JGFM5XAXD6', 'LM-NSG 02', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),
	array( 'B9BTQ11VMU', 'sensen pvd 01', 'MC1756054296549', 'sesen city phạm văn đồng' ),
	array( '', 'thiếu mã', '', '' ),              // bỏ
	array( 'XXXX', '', '', '' ),                  // bỏ — không có tên thì bản đồ vô dụng
);
$r = SAOKE_App::rpc_napDsCuaHangVqr( array( '1234', $ds_file, 'danh-sach-cua-hang.xlsx' ) );
t( 'nạp bản đồ chạy được', ! empty( $r['ok'] ), $r );
teq( 'thêm đúng 3 cửa hàng', 3, (int) $r['themMoi'] );
teq( 'và bỏ 2 dòng thiếu mã hoặc thiếu tên', 2, (int) $r['boQua'] );
teq( 'bản đồ đang có 3', 3, (int) $r['tong'] );

/* 🔴 NẠP LẠI LÀ GỘP, KHÔNG PHẢI XOÁ HẾT RỒI GHI. Cổng cho tải từng trang và người ta hay nạp
   làm nhiều lượt — xoá hết mỗi lượt là lượt sau đá mất lượt trước mà không câu nào báo. */
$r2 = SAOKE_App::rpc_napDsCuaHangVqr( array( '1234', array(
	array( 'RDWUP4647G', 'sensen pvd 03', 'MC1756054296549', 'sesen city phạm văn đồng' ),
	array( 'EZFY9HCIR0', 'LM-NSG 01', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),   // y nguyên
	array( 'JGFM5XAXD6', 'LM-NSG 02B', 'MC1754018340421', 'Posh Lotte Nam Sài Gòn' ),  // đổi tên
), 'trang-2.xlsx' ) );
teq( '🔴 nạp trang 2 KHÔNG xoá trang 1', 4, (int) $r2['tong'] );
teq( 'thêm 1 mới', 1, (int) $r2['themMoi'] );
teq( 'sửa 1 dòng đổi tên', 1, (int) $r2['daSua'] );
teq( 'và 1 dòng y nguyên', 1, (int) $r2['yNguyen'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. SUY RA MÁY — thứ tự có chủ ý, và mỗi bước phải đo riêng
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '1) tên nằm trong nội dung thì lấy luôn', 'AMBD 12',
	goi( 'cong_may_dong', array( 'VQR263764167LJRO AMBD 12', '', '' ) ) );
/* 🔴 ĐÂY LÀ CÂU TRẢ LỜI CHO ANH THẮNG: nội dung PaymentForOrder + mã cửa hàng -> ra tên máy. */
teq( '🔴 2) nội dung PaymentForOrder + MÃ CỬA HÀNG -> ra đúng máy', 'LM-NSG 01',
	goi( 'cong_may_dong', array( 'VQR2637642208V7L PaymentForOrder', 'EZFY9HCIR0', '' ) ) );
teq( '   và mã gõ thường vẫn tra được', 'LM-NSG 02B',
	goi( 'cong_may_dong', array( 'VQR2637642208V7L PaymentForOrder', 'jgfm5xaxd6', '' ) ) );
teq( '3) không có mã thì lùi về diem_ban như cũ', 'AEON MALL BÌNH TÂN',
	goi( 'cong_may_dong', array( 'VQR2637 PaymentForOrder', '', 'Aeon Mall Bình Tân' ) ) );
/* 🔴 KHÔNG BIẾT THÌ NÓI KHÔNG BIẾT. Mã lạ (chưa nạp vào bản đồ) tuyệt đối không được biến
   thành một cái "máy" mang tên mã — tiền sẽ nằm dưới một cửa hàng không có thật. */
teq( '🔴 4) mã lạ chưa có trong bản đồ thì vẫn "chưa rõ máy", KHÔNG bịa', '',
	goi( 'cong_may_dong', array( 'VQR2637 PaymentForOrder', 'MA_LA_CHUA_CO', '' ) ) );
/* Nội dung THẮNG bản đồ: khách quét đúng QR của máy ấy là chắc nhất. */
teq( 'nội dung thắng bản đồ khi cả hai cùng có', 'AMBD 12',
	goi( 'cong_may_dong', array( 'VQR26376 AMBD 12', 'EZFY9HCIR0', '' ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. NẠP FILE VÀO DÒNG WEBHOOK ĐÃ CÓ — VÁ, KHÔNG THÊM DÒNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CẢ ĐIỂM CỦA VIỆC NÀY. Dòng `PaymentForOrder` webhook đã ghi từ lâu. Nạp file KHÔNG được
 *    thêm dòng (kẻo đếm tiền hai lần) nhưng PHẢI điền `ma_ch` vào dòng cũ — không vá thì file
 *    có đủ dữ liệu mà màn hình vẫn "chưa rõ máy", và người nạp không hiểu vì sao nạp xong y cũ.
 */
$db = $GLOBALS['wpdb'];
$nen = array( 'nguon' => 'vietqr', 'khoa' => 'vietqr|VPBg37hatYHLg', 'maGD' => 'VPBg37hatYHLg', 'ref' => '',
	'thoiDiem' => '11/09/2026 17:55:55', 'soTien' => 20000, 'huong' => 'Đến', 'trangThai' => 'Thành công',
	'soTK' => '', 'noiDung' => 'VQR2637642208V7L PaymentForOrder', 'diemBan' => '', 'maCH' => '',
	'docDuoc' => true, 'raw' => 'webhook' );
$kq = '';
t( 'dòng webhook đầu tiên -> thêm mới', true === goi( 'luu_cong', array( $nen, &$kq ) ) );
teq( 'và báo đúng là "moi"', 'moi', $kq );
teq( 'dòng ấy chưa có mã cửa hàng — đúng cảnh anh Thắng gặp', '', (string) $db->hang[0]['ma_ch'] );

$tu_file = array_merge( $nen, array( 'maCH' => 'EZFY9HCIR0', 'raw' => 'FILE sao-ke-vietqr.xlsx' ) );
$kq = '';
t( '🔴 nạp lại từ file KHÔNG thêm dòng thứ hai', false === goi( 'luu_cong', array( $tu_file, &$kq ) ) );
teq( 'nhưng báo là đã VÁ, không phải "trùng suông"', 'va', $kq );
teq( '🔴 và mã cửa hàng đã được điền vào dòng cũ', 'EZFY9HCIR0', (string) $db->hang[0]['ma_ch'] );
teq( 'bảng vẫn đúng MỘT dòng (không đếm tiền hai lần)', 1, count( $db->hang ) );
teq( 'tiền của dòng ấy không đổi', 20000, (int) $db->hang[0]['so_tien'] );
/* 🔴 và từ giây phút ấy màn hình đọc ra máy — đo qua đúng hàm mà màn hình dùng. */
teq( '🔴 dòng ấy nay ra đúng tên máy', 'LM-NSG 01',
	goi( 'cong_may_dong', array( $db->hang[0]['noi_dung'], $db->hang[0]['ma_ch'], $db->hang[0]['diem_ban'] ) ) );

/* ⚠️ KHÔNG ĐÈ ô đã có. Nạp lại lần hai, hay nạp một file CŨ hơn, không được đổi máy của một
   dòng đã xác định — đó là sửa lịch sử tiền. */
$de = array_merge( $nen, array( 'maCH' => 'B9BTQ11VMU' ) );
$kq = '';
goi( 'luu_cong', array( $de, &$kq ) );
teq( '🔴 file khác KHÔNG đè được mã cửa hàng đã có', 'EZFY9HCIR0', (string) $db->hang[0]['ma_ch'] );
teq( 'và lượt ấy là trùng suông', 'trung', $kq );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. MÀN HÌNH: hai ô chọn cột mới, và chỗ dễ sai nhất là ĐOÁN NHẦM CỘT MÃ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( 'khối nạp giao dịch có ô chọn cột Mã cửa hàng', false !== strpos( $APP, "id('txMaCH')" ) );
t( 'và ô chọn cột Mã điểm bán', false !== strpos( $APP, "id('txMaDiem')" ) );
t( 'khối bản đồ cửa hàng có mặt (chỉ cho Việt QR)',
	false !== strpos( $APP, "nguon !== 'vietqr' ? '' :" ) && false !== strpos( $APP, "id('bdFile')" ) );
/* 🔴 "Mã đơn hàng" PHẢI đứng trước "Mã giao dịch" trong phép đoán cột: bản kết xuất có CẢ
   "Mã tham chiếu" lẫn "Mã đơn hàng", mà cái webhook ghi vào ô Mã giao dịch cổng là "Mã đơn
   hàng". Đoán nhầm sang Mã tham chiếu thì khoá chống trùng khác hẳn -> mọi dòng thành "mới"
   -> TIỀN ĐẾM HAI LẦN. Đây là lỗi đắt nhất có thể xảy ra ở màn này. */
t( "🔴 đoán cột mã: 'ma don hang' đứng TRƯỚC 'ma giao dich'",
	false !== strpos( $APP, "timTieuDe(['ma don hang','ma giao dich'" ) );
/* Cùng loại bẫy ở bảng Danh sách cửa hàng: "cua hang" là chuỗi con của CẢ "Mã cửa hàng" lẫn
   "Tên cửa hàng". */
t( "🔴 đoán cột bản đồ: 'ma cua hang' dò trước 'ten cua hang'",
	strpos( $APP, "bdMa: tim(['ma cua hang'" ) < strpos( $APP, "bdTen: tim(['ten cua hang'" ) );
t( 'ô "chưa rõ máy" nói luôn là đã có mã CH hay chưa (hai cảnh, hai cách sửa)',
	false !== strpos( $APP, 'chưa có trong bản đồ' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. TÌM ĐƯỢC THÌ MỚI DÙNG ĐƯỢC
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng, ngay sau khi có bản đầu: *"Anh chưa thấy chỗ thêm file"* — đúng, khối nạp file nằm
 * trong thẻ ⚙️ đang GẬP KÍN, trong khi màn hình báo 593 dòng chưa rõ máy. Một việc cần làm mà
 * không tự chỉ đường tới chỗ làm nó thì coi như chưa làm xong.
 */
t( '🔴 khối "Cần biết" chỉ đường tới chỗ nạp file khi còn dòng chưa rõ máy',
	false !== strpos( $APP, "cgMoCongCu(\\''+nguon+'\\')" )
	&& false !== strpos( $APP, 'giao dịch chưa rõ máy' ) );
t( 'có hàm mở thẻ gập và cuộn tới đúng ô chọn file', false !== strpos( $APP, 'function cgMoCongCu(' ) );
t( 'thẻ ⚙️ có id để mở được bằng mã', false !== strpos( $APP, "id('congCu')" ) );
/* Tên khối phải KỂ RA thứ vừa thêm — người đọc lướt qua dòng ấy là biết có nên mở hay không. */
t( 'và tên thẻ ⚙️ nhắc tới "bản đồ cửa hàng"',
	false !== strpos( $APP, "· <b>bản đồ cửa hàng</b>" ) );

/* 🔴 SỐ BẢN PHẢI IN RA MÀN. Câu đầu tiên khi một tính năng "không thấy đâu" là *bản đang chạy
 *    có nó chưa* — mà trang không in số bản thì không ai đáp được ngoài cách mở wp-admin. */
t( 'máy chủ gửi số bản cho trang', false !== strpos( $SRC, "'ok' => true, 'ver' => self::VER," ) );
t( 'và trang in nó ra cạnh tên công ty', false !== strpos( $APP, "'· v' + cfg.ver" ) );
/* Hai chỗ khai số bản (header plugin + hằng VER) phải BẰNG NHAU — lệch thì trang khoe một đằng,
   WordPress hiện một nẻo, và người đi kiểm bản nào đang chạy sẽ tin nhầm. */
preg_match( '/^ \* Version:\s+([0-9.]+)/m', $SRC, $mv );
preg_match( "/const VER = '([0-9.]+)';/", $SRC, $mc );
teq( '🔴 số bản ở header và hằng VER bằng nhau',
	isset( $mv[1] ) ? $mv[1] : 'thiếu-header', isset( $mc[1] ) ? $mc[1] : 'thiếu-hằng' );

echo "\n";
if ( $TRUOT ) {
	echo 'TRƯỢT ' . count( $TRUOT ) . ":\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $DAT\n";
	exit( 1 );
}
echo "✓ SẠCH — $DAT phép: mã cửa hàng Việt QR vá được máy cho dòng 'PaymentForOrder', và không đếm tiền hai lần.\n";
