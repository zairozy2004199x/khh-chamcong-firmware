<?php
/**
 * "TRAO ĐỔI": BẢNG CHƯA CÓ (SITE VỪA CÀI ĐÈ) -> TỰ DỰNG NGAY, KHÔNG ĐỢI HOOK INIT.
 *
 * Anh Thắng 26/09/2026 — tính năng "trao đổi" mới dưới ô Ghi chú. Cùng bài học từ "Không lưu
 * được" bản 1.72.0/1.73.0 (`khh_dt_bang_sk_co_cot()`): một bảng MỚI vừa thêm vào plugin chỉ có
 * thật trên site khi `khh_dt_nang_cap()` (hook `init`) đã kịp chạy `dbDelta()` của đúng lượt nâng
 * cấp này — thời điểm ấy có thể lệch một lượt tải trang so với lúc plugin đổi bản. `$wpdb->insert()`
 * vào một bảng chưa tồn tại không ném ngoại lệ, chỉ âm thầm không ghi được gì.
 *
 * `khh_dt_bang_trao_doi_co()` tự kiểm bảng có thật trước khi dùng; thiếu thì GỌI NGAY
 * `khh_dt_tao_bang_trao_doi()` tại chỗ (không đợi hook init) rồi kiểm lại.
 *
 * ⚠️ TÁCH RIÊNG THÀNH TỆP NÀY: `khh_dt_bang_trao_doi_co()` cố ý nhớ tạm (`static`) kết quả TRONG
 *    MỘT LƯỢT TẢI TRANG — một request thật không tự đổi cấu trúc bảng giữa chừng. Bài "bảng đã có,
 *    đọc/ghi bình thường" (`kiem-trao-doi.php`) dựng bảng thật bằng SQLite ngay từ đầu tiến trình
 *    của NÓ; trộn hai cảnh trong CÙNG một tiến trình sẽ để bộ nhớ tạm "chưa có" dính lại, làm
 *    mọi phép sau đó (dù bảng đã dựng xong) vẫn đọc ra rỗng — xem cùng ghi chú ở
 *    `kiem-sao-ke-thieu-cot-nhan.php`.
 *
 * Chạy: php tools/test/kiem-trao-doi-bang-thieu.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt'; }
}
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_nap' ) ) { function khh_dt_duoc_nap() { return true; } }
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';

$dat = 0; $hong = array();
function phep2( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_trao_doi() );

echo "── Bảng trao_doi CHƯA CÓ ──\n";
$GLOBALS['VHCP_DBDELTA'] = array();
$co = khh_dt_bang_trao_doi_co();
/* Bệ thử KHÔNG thật sự chạy dbDelta (cú pháp MySQL, xem chú thích ở wp-stub.php) nên sau khi tự
   gọi, `SHOW TABLES LIKE` kiểm lại vẫn thấy chưa có — chốt cần đo ở đây là CÓ GỌI TẠO BẢNG NGAY
   HAY KHÔNG, không phải kết quả cuối (kết quả cuối, trên site MySQL thật, dbDelta chạy thật sẽ
   ra bảng có cột đúng, xem `khh_dt_tao_bang_bc()`/`khh_dt_tao_bang_sk()` cùng một khuôn). */
phep2( '🔴 TỰ GỌI dbDelta() NGAY TẠI CHỖ (không đợi hook init) khi phát hiện thiếu bảng',
	1 === count( $GLOBALS['VHCP_DBDELTA'] ) );
phep2( '🔴 câu dựng bảng gọi đúng TÊN BẢNG trao_doi (không phải bảng nào khác)',
	false !== strpos( $GLOBALS['VHCP_DBDELTA'][0], khh_dt_bang_trao_doi() ) );
phep2( 'câu dựng bảng có đủ các cột cần (ngay, cua_hang, nguoi, noi_dung, luc)',
	false !== strpos( $GLOBALS['VHCP_DBDELTA'][0], 'ngay date NOT NULL' )
	&& false !== strpos( $GLOBALS['VHCP_DBDELTA'][0], 'cua_hang varchar' )
	&& false !== strpos( $GLOBALS['VHCP_DBDELTA'][0], 'noi_dung text NOT NULL' )
	&& false !== strpos( $GLOBALS['VHCP_DBDELTA'][0], 'luc datetime NOT NULL' ) );

/* Gọi lại lần hai TRONG CÙNG TIẾN TRÌNH: nhớ tạm (static) đã có giá trị -> KHÔNG gọi dbDelta thêm
   lần nào nữa (đỡ một câu SHOW TABLES + một câu dựng bảng mỗi lần gửi trao đổi trong cùng request). */
$GLOBALS['VHCP_DBDELTA'] = array();
khh_dt_bang_trao_doi_co();
phep2( '🔴 gọi lại trong CÙNG tiến trình -> không dựng bảng thêm lần nữa (đã nhớ tạm kết quả)',
	0 === count( $GLOBALS['VHCP_DBDELTA'] ) );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: bảng trao_doi thiếu thì tự dựng ngay tại chỗ, không đợi hook init, và không dựng lặp lại trong cùng lượt tải.\n";
