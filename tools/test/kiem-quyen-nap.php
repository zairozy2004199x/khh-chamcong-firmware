<?php
/**
 * QUYỀN NẠP FILE: PIN VAI "DUYỆT" PHẢI ĐƯỢC, VÀ CHỐI THÌ PHẢI NÓI LÝ DO.
 *
 * 23/09/2026 anh Thắng thả file sao kê MoMo và nhận "Xin lỗi, bạn không được phép làm điều đó".
 * Anh vào bằng trang PIN của plugin, nên không phải người dùng WordPress; `khh_dt_duoc_nap()`
 * chỉ hỏi `current_user_can('edit_posts')` nên chối — trong khi `khh_dt_duoc_ghi()` ngay bên
 * dưới đã nhận vai PIN từ lâu. Và câu chối là câu chung chung của WordPress: không ai biết mình
 * bị chối vì đâu.
 *
 * Bốn chốt:
 *   1. 🔴 PIN vai `duyet` được nạp.
 *   2. 🔴 PIN vai `nhap` KHÔNG được — nhân viên cửa hàng không nạp báo cáo cả công ty.
 *   3. 🔴 Chối thì trả WP_Error 403 kèm lý do nói rõ máy chủ thấy người ấy là ai.
 *   4. Cờ `duoc_nap` gửi cho màn hình phải là bool thật — không lộ WP_Error ra JSON (JSON hoá
 *      một WP_Error ra `{}` là "truthy" trong JS, nút Nạp sẽ hiện cho người không được nạp).
 *
 * Chạy: php tools/test/kiem-quyen-nap.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';

/* Giả quyền qua ĐÚNG seam của wp-stub: `current_user_can()` đọc $GLOBALS['VHCP_CO_QUYEN'] (có/không,
   không phân biệt capability), `wp_get_current_user()` trả display_name 'admin' và KHÔNG có
   user_login — nên mã thật phải lùi về display_name, và bài này kiểm đúng chữ 'admin'. Lần đầu
   em tự khai `current_user_can` sau khi stub đã khai -> bản của em bị bỏ qua, hai phép đỏ vì
   BÀI THỬ sai, không phải mã sai. */
/* Seam đăng nhập WordPress của stub là $GLOBALS['VHCP_DANG_NHAP_WP'] (khai ở cuối wp-stub, có
   guard function_exists nên bản tự khai ở đây bị bỏ qua — lại một phép đỏ vì bài thử). */
$GLOBALS['CAN'] = array();
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $u, $k, $one ) { return isset( $GLOBALS['META'][ $k ] ) ? $GLOBALS['META'][ $k ] : ''; }
}
/* `khh_dt_phien_nguoi()` nhớ kết quả trong $GLOBALS['khh_dt_phien_nho'] — đặt thẳng vào đó. */
function pin( $vai ) { $GLOBALS['khh_dt_phien_nho'] = null === $vai ? null : array( 'vai' => $vai, 'ma_nv' => 'NV1', 'ho_ten' => 'A' ); }
if ( ! function_exists( 'khh_dt_phien_nguoi' ) ) {
	function khh_dt_phien_nguoi() { return isset( $GLOBALS['khh_dt_phien_nho'] ) ? $GLOBALS['khh_dt_phien_nho'] : null; }
}

/* Chỉ cần hai hàm quyền — nạp riêng để khỏi kéo cả plugin. */
$src = file_get_contents( $goc . '/khh-doanh-thu.php' );
preg_match( '~/\*\*\s*\n \* Ai được nạp file.*?\nfunction khh_dt_duoc_nap\(\) \{.*?\n\}~s', $src, $m ) || exit( "KHÔNG cắt được khh_dt_duoc_nap\n" );
eval( '?>' . '<?php ' . $m[0] );
$q = file_get_contents( $goc . '/quan-tri.php' );
preg_match( '~function khh_dt_quyen_cua\(.*?\n\}~s', $q, $m2 ) || exit( "KHÔNG cắt được khh_dt_quyen_cua\n" );
eval( '?>' . '<?php ' . $m2[0] );

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }
function reset_all() { $GLOBALS['CAN'] = array(); $GLOBALS['META'] = array(); $GLOBALS['VHCP_CO_QUYEN'] = false; $GLOBALS['VHCP_DANG_NHAP_WP'] = false; pin( null ); }

/* 1. Văn phòng đăng nhập WordPress, có edit_posts -> được. */
reset_all(); $GLOBALS['VHCP_CO_QUYEN'] = true; $GLOBALS['VHCP_DANG_NHAP_WP'] = true;
phep( 'WordPress có edit_posts thì được nạp', true === khh_dt_duoc_nap() );

/* 2. 🔴 PIN vai duyệt -> được. */
reset_all(); pin( 'duyet' );
phep( '🔴 PIN vai "duyet" được nạp', true === khh_dt_duoc_nap() );

/* 2b. 🔴 PIN vai TRỐNG (đẩy sang mà chưa cấp) -> quyền là '' chứ KHÔNG lùi về 'nhap' (1.58.0). */
reset_all(); pin( '' );
phep( '🔴 PIN chưa cấp vai -> quyền "" (không phải "nhap")', '' === khh_dt_quyen_cua() );
phep( 'và không được nạp', true !== khh_dt_duoc_nap() );
reset_all(); pin( 'admin' );
phep( 'PIN mang vai lạ -> cũng là ""', '' === khh_dt_quyen_cua() );

/* 3. 🔴 PIN vai nhập -> KHÔNG, và có lý do. */
reset_all(); pin( 'nhap' );
$r = khh_dt_duoc_nap();
phep( '🔴 PIN vai "nhap" KHÔNG được nạp', is_wp_error( $r ) );
phep( 'chối bằng 403', is_wp_error( $r ) && 403 === (int) $r->get_error_data()['status'] );
phep( '🔴 lý do nói rõ máy chủ thấy người ấy là ai', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'vai "nhap"' ) );
phep( 'và nói cần gì để được', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'duyệt' ) );

/* 4. Chưa đăng nhập gì -> KHÔNG, lý do "chưa đăng nhập". */
reset_all();
$r = khh_dt_duoc_nap();
phep( 'chưa đăng nhập thì chối', is_wp_error( $r ) );
phep( 'lý do là "chưa đăng nhập"', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'chưa đăng nhập' ) );

/* 5. WordPress subscriber (không edit_posts), meta vai nhập -> KHÔNG; meta duyệt -> ĐƯỢC. */
reset_all(); $GLOBALS['VHCP_DANG_NHAP_WP'] = true; $GLOBALS['META'] = array( 'khh_dt_quyen' => 'nhap' );
$r = khh_dt_duoc_nap();
phep( 'tài khoản WP thường vai nhập thì chối', is_wp_error( $r ) );
phep( 'lý do nêu tên tài khoản', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), '"admin"' ) );
reset_all(); $GLOBALS['VHCP_DANG_NHAP_WP'] = true; $GLOBALS['META'] = array( 'khh_dt_quyen' => 'duyet' );
phep( 'tài khoản WP thường vai duyệt thì được', true === khh_dt_duoc_nap() );

/* 6. 🔴 Cờ gửi màn hình phải là bool: `true === khh_dt_duoc_nap()`. WP_Error hoá JSON ra {} —
      truthy trong JS — nút Nạp sẽ hiện cho đúng người vừa bị chối. */
$cfg = file_get_contents( $goc . '/khh-doanh-thu.php' );
phep( '🔴 cờ duoc_nap trong cau-hinh ép về bool (true === …)', false !== strpos( $cfg, "'duoc_nap'  => true === khh_dt_duoc_nap()" ) );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: PIN duyệt nạp được, nhập thì không, và chối là có lý do.\n";
