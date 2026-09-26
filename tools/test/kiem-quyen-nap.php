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

/* Chỉ cần các hàm quyền — nạp riêng để khỏi kéo cả plugin. */
$src = file_get_contents( $goc . '/khh-doanh-thu.php' );
preg_match( '~/\*\*\s*\n \* Ai được nạp file.*?\nfunction khh_dt_duoc_nap\(\) \{.*?\n\}~s', $src, $m ) || exit( "KHÔNG cắt được khh_dt_duoc_nap\n" );
eval( '?>' . '<?php ' . $m[0] );
preg_match( '~/\*\*\s*\n \* Ai được nhập báo cáo ngày\..*?\nfunction khh_dt_duoc_ghi\(\) \{.*?\n\}~s', $src, $mg ) || exit( "KHÔNG cắt được khh_dt_duoc_ghi\n" );
eval( '?>' . '<?php ' . $mg[0] );
$q = file_get_contents( $goc . '/quan-tri.php' );
preg_match( '~function khh_dt_quyen_cua\(.*?\n\}~s', $q, $m2 ) || exit( "KHÔNG cắt được khh_dt_quyen_cua\n" );
eval( '?>' . '<?php ' . $m2[0] );
/* 🔴 26/09/2026: `khh_dt_duoc_nap()`/`khh_dt_duoc_ghi()` giờ gọi thẳng hàm QUẢN TRỊ THẬT (không
   phải một bản stub tự chép — nhiều bài khác trong bộ thử tự khai một bản `khh_dt_duoc_quan_tri()`
   proxy thẳng về VHCP_CO_QUYEN cho mục đích RIÊNG của bài ấy, nhưng bài NÀY đang thử ĐÚNG luật
   quản trị nên phải lấy nguyên hàm thật, không thì "vá luật nhưng bài thử tự chế lại luật khác"
   sẽ xanh giả). */
preg_match( '~function khh_dt_duoc_quan_tri\(\) \{.*?\n\}~s', $q, $m3 ) || exit( "KHÔNG cắt được khh_dt_duoc_quan_tri\n" );
eval( '?>' . '<?php ' . $m3[0] );

$dat = 0; $hong = array();
function phep( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }
function reset_all() { $GLOBALS['CAN'] = array(); $GLOBALS['META'] = array(); $GLOBALS['VHCP_CO_QUYEN'] = false; $GLOBALS['VHCP_QUYEN_THEO_CAP'] = array(); $GLOBALS['VHCP_DANG_NHAP_WP'] = false; pin( null ); }

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

/* 7. 🔴 26/09/2026 anh Thắng — "bổ sung mấy quyền sửa cái này cho tài khoản quản trị": role
      WordPress thật của quản trị trên site có `list_users` (vào được tab Quản trị: Sale vé/Bán
      lẻ, Bóc tách vé, Phân quyền — toàn màn nhạy hơn hẳn nạp file/nhập báo cáo ngày) nhưng KHÔNG
      có `edit_posts`. Quản trị phải là tầng quyền CAO NHẤT — không thể vào được phòng trong
      (Quản trị) mà lại bị chặn ở cửa ngoài (nạp file / nhập báo cáo ngày). */
reset_all(); $GLOBALS['VHCP_DANG_NHAP_WP'] = true;
$GLOBALS['VHCP_QUYEN_THEO_CAP'] = array( 'edit_posts' => false, 'list_users' => true );
phep( '🔴 quản trị (list_users) KHÔNG có edit_posts vẫn được NẠP file', true === khh_dt_duoc_nap() );
phep( '🔴 quản trị (list_users) KHÔNG có edit_posts vẫn được GHI báo cáo ngày', true === khh_dt_duoc_ghi() );

/* 8. Đối chứng: tắt CẢ HAI capability (không phải quản trị mở toang mọi thứ vô điều kiện) thì vẫn
      chối y như trước bản vá — chứng minh phép 7 ở trên thật sự nhờ list_users, không phải một
      lỗ hổng khác vô tình mở toang cho mọi tài khoản WordPress đã đăng nhập. */
reset_all(); $GLOBALS['VHCP_DANG_NHAP_WP'] = true;
$GLOBALS['VHCP_QUYEN_THEO_CAP'] = array( 'edit_posts' => false, 'list_users' => false );
$r = khh_dt_duoc_nap();
phep( 'đối chứng: cả hai capability đều tắt -> vẫn chối NẠP file như cũ', is_wp_error( $r ) );
phep( 'đối chứng: cả hai capability đều tắt -> vẫn chối GHI báo cáo ngày như cũ', false === khh_dt_duoc_ghi() );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: PIN duyệt nạp/ghi được, nhập thì không, quản trị (list_users) được dù thiếu edit_posts, và chối là có lý do.\n";
