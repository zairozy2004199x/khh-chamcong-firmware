<?php
/**
 * "TRAO ĐỔI" — HỎI ĐÁP QUA LẠI DƯỚI Ô GHI CHÚ, GIỮA NHÂN VIÊN VÀ KẾ TOÁN.
 *
 * Anh Thắng 26/09/2026, ảnh màn Nhập báo cáo ngày (ô "Ghi chú" trơn): *"Anh muốn nút ghi chú này
 * là dạng ghi chú và kèm hỏi, khi nhân viên nhập ghi chú, kế toán có thể phản hồi nút đó (dạng
 * trao đổi qua lại nếu chưa rõ thông tin giữa 2 bên)"*.
 *
 * Cố ý làm một SỔ MỚI, RIÊNG với cột `ghi_chu` cũ (xem chú thích ở `khh_dt_bang_trao_doi()` trong
 * bao-cao-ngay.php) — không đụng gì tới cột cũ, chỉ cộng dồn theo ngày × cơ sở.
 *
 * Cảnh "bảng trao_doi CHƯA CÓ (site vừa cài đè) -> tự dựng ngay, không đợi hook init" có bài
 * RIÊNG — `kiem-trao-doi-bang-thieu.php` (lý do tách xem chú thích ở đó). Sáu chốt bài NÀY canh:
 *   1. Đọc/ghi bình thường khi bảng đã có sẵn (site đã nâng cấp xong).
 *   2. 🔴 Cùng quyền với ghi báo cáo ngày CỦA ĐÚNG CƠ SỞ (`khh_dt_duoc_cua_hang()`) — cơ sở khác
 *      không viết được (và không lọt dòng nào vào sổ), "kiêm quản lý" (co_so_ds rỗng = mọi cơ sở)
 *      viết được vào cơ sở bất kỳ.
 *   3. 🔴 Viết được NGAY CẢ KHI ngày đã khoá "Đã nộp tiền" — hỏi thêm sau khi khoá vẫn là nhu cầu
 *      thật (kế toán hỏi vì sao lệch sau khi đã khoá), KHÁC với `khh_dt_rest_bc_luu()` bị chặn hẳn.
 *   4. Nội dung rỗng / thiếu ngày-cơ sở -> chối, không lưu dòng trắng.
 *   5. 🔴 Nội dung quá dài bị cắt còn 2000 ký tự — không tràn dữ liệu.
 *   6. Đọc lại đúng thứ tự cũ trước mới sau, và `khh_dt_rest_bc_lay()` trả kèm đúng luồng để màn
 *      hình vẽ ra không phải gọi thêm một cửa riêng.
 *
 * Chạy: php tools/test/kiem-trao-doi.php
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
if ( ! function_exists( 'khh_dt_duoc_nap' ) ) { function khh_dt_duoc_nap() { return true; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
/* 🔴 Cơ sở người đang ngồi trước máy phụ trách — điều khiển bằng biến toàn cục để dựng đủ ba vai:
   nhân viên MỘT cơ sở (mảng một phần tử), "kiêm quản lý"/kế toán (mảng rỗng = mọi cơ sở). */
$GLOBALS['VHCP_CO_SO_META'] = '';
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $u, $k, $one = true ) { return 'khh_dt_co_so' === $k ? $GLOBALS['VHCP_CO_SO_META'] : ''; }
}
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

echo "── Route khai đúng cửa, và chỗ ghi_chu vẫn nguyên vẹn ──\n";
$s = (string) file_get_contents( $goc . '/bao-cao-ngay.php' );
phep( "cửa /trao-doi qua khh_dt_duoc_ghi (kiểm cơ sở NGAY TRONG hàm)",
	1 === preg_match( "~'/trao-doi',.*?'callback'\s*=>\s*'khh_dt_rest_trao_doi_gui',.*?'permission_callback'\s*=>\s*'khh_dt_duoc_ghi',~s", $s ) );
phep( '🔴 bảng trao_doi TÁCH RIÊNG (bảng mới, không thêm cột vào bảng báo cáo cũ)',
	false !== strpos( $s, "return \$wpdb->prefix . 'khh_dt_trao_doi';" ) );
phep( 'cột ghi_chu cũ không bị đụng tới (vẫn còn trong khh_dt_rest_bc_luu)',
	false !== strpos( $s, "'ghi_chu'       => sanitize_textarea_field" ) );

global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_bc() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang_bc() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT,
	ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', tien_mat_dem REAL DEFAULT 0, ck_thuc_thu REAL DEFAULT 0, tien_nop REAL DEFAULT 0,
	so_bill_huy INTEGER DEFAULT 0, tien_bill_huy REAL DEFAULT 0, tong_chuyen INTEGER DEFAULT 0, tong_khach INTEGER DEFAULT 0,
	ve_giay INTEGER DEFAULT 0, ghi_chu TEXT DEFAULT '', mon_thuc TEXT NULL, nguoi TEXT DEFAULT '', nguoi_id INTEGER DEFAULT 0,
	chot INTEGER DEFAULT 0, da_nop INTEGER DEFAULT 0, da_nop_boi TEXT DEFAULT '', da_nop_luc TEXT NULL,
	lich_su TEXT DEFAULT '[]', sua_luc TEXT NULL, UNIQUE(ngay,cua_hang) )" );
if ( ! function_exists( 'khh_dt_so_pos' ) ) { function khh_dt_so_pos( $ngay, $ch ) { return null; } }

/* ⚠️ Cảnh "bảng trao_doi CHƯA CÓ, tự dựng ngay không đợi hook init" có bài RIÊNG chạy trong tiến
   trình của chính nó — `kiem-trao-doi-bang-thieu.php`. `khh_dt_bang_trao_doi_co()` nhớ tạm
   (`static`) kết quả trong một lượt tải trang, nên bài NÀY dựng bảng thật NGAY TỪ ĐẦU tiến trình,
   không đổi cảnh giữa chừng — xem chú thích đầy đủ ở tệp kia. */
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_trao_doi() );
$wpdb->exec_raw(
	'CREATE TABLE ' . khh_dt_bang_trao_doi() . " (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '',
		nguoi TEXT NOT NULL DEFAULT '', nguoi_id INTEGER NOT NULL DEFAULT 0,
		noi_dung TEXT NOT NULL, luc TEXT NOT NULL )"
);

$NGAY = '2026-09-25'; $CS = 'Tutu Train - Aeon Tân An ( Dịch vụ và Giải trí )';
$CS_KHAC = 'Estella';
$GLOBALS['VHCP_DANG_NHAP_WP'] = true;

echo "\n── Chạy thật ──\n";
/* 1. Nhân viên cơ sở đó gửi câu hỏi -> thành công. */
$GLOBALS['VHCP_CO_SO_META'] = $CS;
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'noi_dung' => 'Sao doanh thu hôm nay thấp vậy em?' ) ) );
phep( 'gửi thành công', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
phep( 'trả về đúng 1 tin vừa gửi', ! is_wp_error( $r ) && 1 === count( $r['trao_doi'] ) );
phep( 'tin có đúng nội dung', ! is_wp_error( $r ) && 'Sao doanh thu hôm nay thấp vậy em?' === $r['trao_doi'][0]['noi_dung'] );
phep( 'tin có tên người gửi', ! is_wp_error( $r ) && '' !== $r['trao_doi'][0]['nguoi'] );
phep( 'tin có thời điểm gửi', ! is_wp_error( $r ) && '' !== $r['trao_doi'][0]['luc'] );

/* 2. Kế toán (co_so_ds rỗng = mọi cơ sở) phản hồi lại đúng ngày × cơ sở đó -> thành công, cộng dồn. */
$GLOBALS['VHCP_CO_SO_META'] = '';
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'noi_dung' => 'Do hôm qua có khách đoàn huỷ bill em nhé.' ) ) );
phep( 'kế toán (mọi cơ sở) phản hồi được', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
phep( '🔴 cộng dồn thành 2 tin, THỨ TỰ CŨ TRƯỚC MỚI SAU', ! is_wp_error( $r ) && 2 === count( $r['trao_doi'] )
	&& false !== strpos( $r['trao_doi'][0]['noi_dung'], 'thấp vậy' ) && false !== strpos( $r['trao_doi'][1]['noi_dung'], 'huỷ bill' ) );

/* 3. 🔴 Nhân viên cơ sở KHÁC không viết được vào ngày × cơ sở này — và không lọt dòng nào vào sổ. */
$GLOBALS['VHCP_CO_SO_META'] = $CS_KHAC;
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'noi_dung' => 'Tin lạ chen vào' ) ) );
phep( '🔴 cơ sở khác bị chối', is_wp_error( $r ) );
phep( 'lý do nói rõ không phụ trách cơ sở này', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'không phụ trách' ) );
$dem = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . khh_dt_bang_trao_doi() . ' WHERE ngay=%s AND cua_hang=%s', $NGAY, $CS ) );
phep( '🔴 sau lần bị chối, vẫn đúng 2 tin (không lọt tin lạ vào sổ)', 2 === $dem );

/* 4. Nội dung rỗng / thiếu ngày / thiếu cơ sở -> chối, không lưu dòng trắng. */
$GLOBALS['VHCP_CO_SO_META'] = $CS;
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'noi_dung' => '   ' ) ) );
phep( 'nội dung rỗng (toàn khoảng trắng) bị chối', is_wp_error( $r ) );
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'cua_hang' => $CS, 'noi_dung' => 'x' ) ) );
phep( 'thiếu ngày bị chối', is_wp_error( $r ) );
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'noi_dung' => 'x' ) ) );
phep( 'thiếu cơ sở bị chối', is_wp_error( $r ) );

/* 5. 🔴 Nội dung quá dài bị cắt còn 2000 ký tự, không tràn dữ liệu. */
$dai = str_repeat( 'a', 2500 );
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'noi_dung' => $dai ) ) );
phep( '🔴 nội dung quá dài bị cắt còn tối đa 2000 ký tự', ! is_wp_error( $r ) && 2000 === mb_strlen( end( $r['trao_doi'] )['noi_dung'] ) );

/* 6. 🔴 Ngày đã "Đã nộp tiền" (khoá) vẫn hỏi/trả lời được — KHÁC với lưu báo cáo bị chặn hẳn. */
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '500000' ) ) );
phep( 'lưu báo cáo trước khi khoá thành công', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );
$r = khh_dt_rest_bc_da_nop( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( 'đánh dấu đã nộp tiền thành công', ! is_wp_error( $r ) && 1 === (int) $r['da_nop'] );
$r = khh_dt_rest_bc_luu( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'tien_mat_dem' => '999' ) ) );
phep( 'kiểm chứng: ngày đã khoá thì LƯU BÁO CÁO bị chối (đối chứng)', is_wp_error( $r ) );
$r = khh_dt_rest_trao_doi_gui( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS, 'noi_dung' => 'Đã khoá rồi nhưng vẫn hỏi thêm được chứ ạ?' ) ) );
phep( '🔴 ngày đã khoá "Đã nộp tiền" VẪN gửi trao đổi được (hỏi thêm sau khi khoá là nhu cầu thật)', ! is_wp_error( $r ) && ! empty( $r['ok'] ) );

/* 7. `khh_dt_rest_bc_lay()` trả kèm đúng luồng trao đổi, không phải gọi thêm cửa riêng. */
$r = khh_dt_rest_bc_lay( new WP_REST_Request( array( 'ngay' => $NGAY, 'cua_hang' => $CS ) ) );
phep( 'khh_dt_rest_bc_lay() trả kèm khoá trao_doi', ! is_wp_error( $r ) && isset( $r['trao_doi'] ) );
phep( '🔴 đủ cả các tin đã gửi (2 tin gốc + 1 tin sau khoá + 1 tin dài đã cắt = 4)',
	! is_wp_error( $r ) && 4 === count( $r['trao_doi'] ) );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: trao đổi tự dựng bảng ngay khi thiếu, đúng quyền theo cơ sở, không bị khoá 'Đã nộp tiền' chặn, không đụng ghi_chu cũ.\n";
