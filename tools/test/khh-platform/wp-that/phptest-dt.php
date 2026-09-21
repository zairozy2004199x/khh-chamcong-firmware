<?php
/* Kiểm thử THẬT: đọc thẳng doanh thu ngày từ bảng của plugin Báo Cáo Doanh Thu FABi */
require_once '/tmp/wpsite/wp-load.php';
$fail = 0;
function t( $n, $g, $w ) { global $fail; $ok = $g === $w; if ( ! $ok ) { $fail++; }
	printf( "%s  %-56s got=%s\n", $ok ? 'PASS' : 'FAIL', $n, var_export( $g, true ) ); }
delete_transient( 'khh_dt_co' ); delete_transient( 'khh_dt_doc' ); delete_option( 'khh_dt_ver' );
/* Về số gốc để phép thử "bên FABi đổi số" chạy lại lần nào cũng thật sự có thay đổi. */
global $wpdb;
$wpdb->query( "UPDATE wp_khh_dt_ngay SET doanh_thu = 8400000 WHERE ngay='2026-09-15' AND cua_hang='FZ ADV AL'" );
t( 'nhận ra bảng doanh thu của FABi', khh_dt_co(), true );
$k = khh_dt_song( 3 );
t( 'đọc ra bản ghi theo (ngày, cửa hàng)', is_array( $k ), true );
$ids = array(); foreach ( $k['docs'] as $d ) { $ids[ $d['coso'] . '|' . $d['ngay'] ] = $d; }
t( '  3 bản ghi trong cửa sổ, bỏ dòng năm 2020', count( $k['docs'] ), 3 );
t( '  đúng số doanh thu', $ids['KHU VUI CHƠI FUNFEST|2026-09-15']['dt'], 12500000.0 );
t( '  đúng số hoá đơn', $ids['FZ ADV AL|2026-09-15']['hd'], 51 );
t( '  tên cửa hàng có dấu còn nguyên', isset( $ids['KHU VUI CHƠI FUNFEST|2026-09-16'] ), true );
t( '  mã bản ghi ổn định', $k['docs'][0]['id'] === khh_dt_id( $k['docs'][0]['ngay'], $k['docs'][0]['coso'] ), true );
$v1 = $k['ver'];
delete_transient( 'khh_dt_doc' );
t( 'đọc lại y nguyên thì mốc không nhích', khh_dt_song( 3 )['ver'], $v1 );
$wpdb->query( "UPDATE wp_khh_dt_ngay SET doanh_thu = 13000000 WHERE ngay='2026-09-15' AND cua_hang='FZ ADV AL'" );
delete_transient( 'khh_dt_doc' );
t( 'bên FABi đổi số thì mốc nhích, bên này đổi theo', khh_dt_song( 3 )['ver'] > $v1, true );
$r = khh_rest_state( new WP_REST_Request( 'GET', '/khh/v1/state' ) );
$d = $r->get_data();
t( 'cổng /state gửi kèm nhóm revenue', isset( $d['docs']->revenue ) && count( $d['docs']->revenue ) === 3, true );
$cfg = khh_api_config();
t( 'cấu hình có mục đổi lịch bên Chấm Công', is_array( $cfg['vhccDoiLich'] ) && isset( $cfg['vhccDoiLich']['n'] ), true );
t( '  đường dẫn duyệt trỏ vào menu vhcc', strpos( $cfg['vhccDoiLich']['url'], 'page=vhcc' ) !== false, true );
echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n"; exit( $fail ? 1 : 0 );
