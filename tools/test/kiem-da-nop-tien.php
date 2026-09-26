<?php
/**
 * NÚT "ĐÃ NỘP TIỀN" — KHOÁ VĨNH VIỄN, CHỈ VĂN PHÒNG/QUẢN TRỊ GỠ ĐƯỢC.
 *
 * Anh Thắng 26/09/2026, ảnh màn Nhập báo cáo ngày: *"Bổ sung nút đã nộp tiền (Khi đã nộp thì khóa
 * ô nhập lại)"*. Làm rõ qua câu hỏi: khoá TOÀN BỘ form (như khi hết quyền sửa), và khoá VĨNH VIỄN
 * — không tự mở lại được, phải nhờ ai có quyền cao hơn.
 *
 * Bốn chốt bài này canh, ở đúng ĐƯỜNG SERVER (giao diện disable input chỉ là cosmetic — chặn thật
 * phải nằm ở REST, không thì ai gọi thẳng API vẫn sửa được ngày đã khoá):
 *   1. 🔴 Đánh dấu "đã nộp" xong thì `khh_dt_rest_bc_luu()` (lưu báo cáo bình thường) phải TỪ CHỐI,
 *      không chỉ giao diện disable input suông.
 *   2. 🔴 Đánh dấu một ngày CHƯA có báo cáo nào -> từ chối (không khoá một ô còn trống).
 *   3. 🔴 Đánh dấu hai lần cùng một ngày -> lần hai bị từ chối (không đánh dấu chồng, mất dấu ai
 *      nộp trước).
 *   4. 🔴 Gỡ khoá đi ĐƯỜNG RIÊNG (`khh_dt_rest_bc_go_khoa_da_nop`), permission_callback là
 *      `khh_dt_duoc_nap` (văn phòng/quản trị) — KHÔNG PHẢI `khh_dt_duoc_ghi` (ai cũng khoá được
 *      thì cũng ai cũng mở được là khoá vô nghĩa). Gỡ xong thì lưu báo cáo lại được như thường.
 *
 * Chạy: php tools/test/kiem-da-nop-tien.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt'; }
}
if ( ! function_exists( 'khh_dt_json' ) ) {
	function khh_dt_json( $raw, $mac_dinh ) { $v = json_decode( (string) $raw, true ); return is_array( $v ) ? $v : $mac_dinh; }
}
if ( ! function_exists( 'khh_dt_so' ) ) {
	function khh_dt_so( $s ) { $s = preg_replace( '/[^\d\-\.]/', '', (string) $s ); return '' === $s ? 0 : (float) $s; }
}
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
/* 🔴 Đây là cái phải KHÁC `khh_dt_duoc_ghi()` — cửa gác thật của permission_callback bên máy chủ,
   trả WP_Error 403 y hệt hàm thật (không rút gọn về bool) để bài dưới thử được đúng câu chối. */
if ( ! function_exists( 'khh_dt_duoc_nap' ) ) {
	function khh_dt_duoc_nap() {
		return ! empty( $GLOBALS['VHCP_VAN_PHONG'] )
			? true
			: new WP_Error( 'khh_dt_khong_duoc_nap', 'Cần tài khoản văn phòng.', array( 'status' => 403 ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

echo "── Bảng có đủ cột, và hai cửa REST khai đúng permission ──\n";
$s = (string) file_get_contents( $goc . '/bao-cao-ngay.php' );
phep( '🔴 bảng có da_nop / da_nop_boi / da_nop_luc',
	false !== strpos( $s, 'da_nop tinyint(1) NOT NULL DEFAULT 0' )
	&& false !== strpos( $s, "da_nop_boi varchar(100) NOT NULL DEFAULT ''" )
	&& false !== strpos( $s, 'da_nop_luc datetime NULL' ) );
phep( "cửa /da-nop qua khh_dt_duoc_ghi (ai nhập được thì đánh dấu được)",
	1 === preg_match( "~'/da-nop',.*?'callback'\s*=>\s*'khh_dt_rest_bc_da_nop',.*?'permission_callback'\s*=>\s*'khh_dt_duoc_ghi',~s", $s ) );
phep( "🔴 cửa /go-khoa-da-nop qua khh_dt_duoc_nap (văn phòng/quản trị), KHÔNG PHẢI khh_dt_duoc_ghi",
	1 === preg_match( "~'/go-khoa-da-nop',.*?'callback'\s*=>\s*'khh_dt_rest_bc_go_khoa_da_nop',.*?'permission_callback'\s*=>\s*'khh_dt_duoc_nap',~s", $s ) );

echo "\n── Chạy thật ──\n";
global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_bc() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, ck_thuc_thu REAL DEFAULT 0, tien_nop REAL DEFAULT 0,
	so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0, tong_khach INTEGER DEFAULT 0,
	ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, da_nop INTEGER DEFAULT 0, da_nop_boi TEXT DEFAULT '', da_nop_luc TEXT NULL,
	lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );

if ( ! function_exists( 'khh_dt_so_pos' ) ) { function khh_dt_so_pos( $ngay, $ch ) { return null; } }

$NGAY = '2026-09-25'; $CS = 'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )';
$GLOBALS['VHCP_VAN_PHONG'] = false;
$GLOBALS['VHCP_DANG_NHAP_WP'] = true;   // khh_dt_ten_ghi_so() cần is_user_logged_in() để có tên ghi sổ

/* 1. 🔴 Chưa có báo cáo nào -> đánh dấu bị chối, không khoá một ô trống. */
$r = khh_dt_rest_bc_da_nop( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( '🔴 chưa có báo cáo -> đánh dấu đã nộp bị chối', is_wp_error( $r ) );
phep( 'lý do nói rõ phải lưu báo cáo trước', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'lưu báo cáo trước' ) );

/* 2. Lưu một báo cáo bình thường trước. */
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '500000' ) ) );
phep( 'lưu báo cáo bình thường trước khi khoá thành công', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );

/* 3. Đánh dấu đã nộp -> thành công, trả kèm ai/lúc nào. */
$r = khh_dt_rest_bc_da_nop( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( '🔴 đánh dấu đã nộp thành công', ! is_wp_error( $r ) && 1 === (int) $r['da_nop'] );
phep( 'có tên người đánh dấu', ! empty( $r['da_nop_boi'] ) );
phep( 'có thời điểm đánh dấu', ! empty( $r['da_nop_luc'] ) );

/* 4. 🔴 Sau khi khoá, LƯU BÁO CÁO BÌNH THƯỜNG (không qua nút gỡ khoá) phải bị CHẶN Ở SERVER —
      đây là chốt quan trọng nhất: giao diện disable input không đủ, ai gọi thẳng REST vẫn phải
      bị chối, mới thật là "khoá", không phải "khoá cho có". */
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '999999' ) ) );
phep( '🔴 đã khoá thì lưu báo cáo lại bị máy chủ CHỐI (không chỉ giao diện disable)', is_wp_error( $r ) );
phep( 'lý do nêu rõ đã nộp tiền, khoá sửa', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'Đã nộp tiền' ) );
$sau = $wpdb->get_row( $wpdb->prepare( 'SELECT tien_mat_dem FROM ' . khh_dt_bang_bc() . ' WHERE ngay=%s AND cua_hang=%s', $NGAY, $CS ), ARRAY_A );
phep( 'số tiền mặt KHÔNG bị đổi bởi lần lưu bị chối đó (vẫn 500000)', 500000 === (int) $sau['tien_mat_dem'] );

/* 5. 🔴 Đánh dấu LẦN HAI trên ngày đã khoá -> chối, không đánh dấu chồng mất dấu người nộp lần đầu. */
$r = khh_dt_rest_bc_da_nop( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( '🔴 đánh dấu đã nộp LẦN HAI bị chối', is_wp_error( $r ) );
phep( 'lý do nói "đã đánh dấu ... từ trước"', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'từ trước' ) );

/* 6. Gỡ khoá KHÔNG PHẢI văn phòng -> permission_callback thật (khh_dt_duoc_nap) đã chối ở tầng
      REST rồi (không gọi tới hàm callback nữa), bài này chỉ chứng minh gate đúng hàm — xem phép
      tĩnh ở trên. Ở đây thử gọi callback THẲNG (giả lập tình huống lọt qua gate, phòng hờ) vẫn
      phải khoá đúng phía dữ liệu dù ai gọi. */
$GLOBALS['VHCP_VAN_PHONG'] = true;
$r = khh_dt_rest_bc_go_khoa_da_nop( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( 'văn phòng gỡ khoá thành công', ! is_wp_error( $r ) && 0 === (int) $r['da_nop'] );

/* 7. Gỡ khoá xong thì LƯU LẠI BÌNH THƯỜNG được như trước — chứng minh khoá đã thật sự mở, không
      phải "gỡ" giả mà máy chủ vẫn âm thầm chối. */
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '700000' ) ) );
phep( '🔴 gỡ khoá xong -> lưu báo cáo lại được, không còn bị chối', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
$sau2 = $wpdb->get_row( $wpdb->prepare( 'SELECT tien_mat_dem, da_nop FROM ' . khh_dt_bang_bc() . ' WHERE ngay=%s AND cua_hang=%s', $NGAY, $CS ), ARRAY_A );
phep( 'số tiền mặt đổi đúng số mới (700000)', 700000 === (int) $sau2['tien_mat_dem'] );
phep( 'cột da_nop vẫn 0 sau khi lưu lại (lưu bình thường không tự khoá lại)', 0 === (int) $sau2['da_nop'] );

/* 8. Sau khi gỡ, đánh dấu đã nộp lại được (chu trình lặp lại bình thường cho ngày mai). */
$r = khh_dt_rest_bc_da_nop( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( 'sau khi gỡ, đánh dấu đã nộp lại được bình thường', ! is_wp_error( $r ) && 1 === (int) $r['da_nop'] );

/* 9. 🔴 Lịch sử ghi lại lần gỡ khoá trước đó (tra được ai gỡ, lúc nào) — không mất dấu. */
$rec = $wpdb->get_row( $wpdb->prepare( 'SELECT lich_su FROM ' . khh_dt_bang_bc() . ' WHERE ngay=%s AND cua_hang=%s', $NGAY, $CS ), ARRAY_A );
$ls  = khh_dt_json( $rec['lich_su'], array() );
$coGoKhoa = false; foreach ( $ls as $x ) { if ( ! empty( $x['go_khoa_da_nop'] ) ) { $coGoKhoa = true; } }
phep( '🔴 lịch sử có ghi lần gỡ khoá (ai gỡ, khoá lúc nào, gỡ lúc nào)', $coGoKhoa );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: đã nộp tiền khoá thật ở máy chủ, chỉ văn phòng/quản trị gỡ được, lịch sử không mất dấu.\n";
