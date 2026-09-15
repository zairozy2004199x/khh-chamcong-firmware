<?php
/* Kiểm thử bộ đọc Excel .xlsx và chế độ khớp "mã, chưa có mã thì dò tên" */
define( 'ABSPATH', '/tmp/fakewp/' );

$GLOBALS['_docs'] = array( 'staff' => array() );

function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $k ) ); }
function sanitize_file_name( $f ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $f ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t ); }
function esc_url( $u ) { return $u; }
function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . $p; }
function add_action() {}
function add_submenu_page() {}
function current_user_can( $c ) { return true; }
function check_admin_referer() { return true; }
function wp_die( $m ) { die( $m ); }
function wp_nonce_field() {}
function khh_table() { return 'wp_khh_docs'; }
function khh_get_coll( $c ) { return isset( $GLOBALS['_docs'][ $c ] ) ? $GLOBALS['_docs'][ $c ] : array(); }
function khh_put_doc( $c, $i, $b ) { $GLOBALS['_docs'][ $c ][ $i ] = $b; return true; }
function khh_collections() { return array( 'staff' ); }
function khh_user_role( $id = 0 ) { return 'owner'; }

require_once __DIR__ . '/../../../wordpress/khh-platform/xlsx.php';

/* nạp import.php, bỏ các hàm dữ liệu đã giả lập */
$src = file_get_contents( __DIR__ . '/../../../wordpress/khh-platform/import.php' );
$src = preg_replace( '/^<\?php/', '', $src, 1 );
foreach ( array( 'khh_table', 'khh_get_coll', 'khh_put_doc' ) as $fn ) {
	$src = preg_replace( '/\nfunction ' . $fn . '\s*\([^)]*\)\s*\{.*?\n\}/s', "\n", $src, 1 );
}
eval( $src );

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-56s got=%s\n", $ok ? 'PASS' : 'FAIL', $name, is_array( $got ) ? json_encode( $got, JSON_UNESCAPED_UNICODE ) : var_export( $got, true ) );
}

/* ------------------------------------------------------------------ *
 * Đọc file Excel
 * ------------------------------------------------------------------ */
t( 'máy chủ đọc được .xlsx', khh_xlsx_ready(), true );

$rows = khh_parse_xlsx( __DIR__ . '/xlsxfix/nhansu.xlsx' );
t( 'đọc đúng số dòng (1 tiêu đề + 3 người)', count( $rows ), 4 );
t( 'đọc đúng tiêu đề cột', $rows[0], array( 'Mã NV', 'Họ và tên', 'Chức danh', 'Bộ phận', 'Mảng', 'Ngày vào làm', 'Ngày sinh', 'Lương cơ bản', 'Email', 'Giới tính', 'Trạng thái' ) );
t( 'đọc đúng chữ có dấu', $rows[1][1], 'Nguyễn Thị Bảo Mai' );
t( 'ô ngày đổi về ngày/tháng/năm', $rows[1][5], '01/03/2024' );
t( 'ngày sinh cũng đúng', $rows[1][6], '12/07/1998' );
t( 'ngày cuối tháng 11 không lệch', $rows[3][5], '30/11/2025' );
t( 'số tiền giữ nguyên dạng số', $rows[1][7], '8500000' );
t( 'ô trống vẫn là chuỗi rỗng', $rows[3][6], '' );
t( 'mọi dòng cùng số cột', array_unique( array_map( 'count', $rows ) ), array( 11 ) );

/* ngày đọc ra phải qua được bộ phân tích ngày của phần nhập */
t( 'phần nhập hiểu ngày lấy từ Excel', khh_parse_date( $rows[1][5] ), '2024-03-01' );
t( 'phần nhập hiểu ngày sinh', khh_parse_date( $rows[1][6] ), '1998-07-12' );

/* cột bỏ trống ở giữa và dòng trống ở cuối */
$r2 = khh_parse_xlsx( __DIR__ . '/xlsxfix/thua.xlsx' );
t( 'bỏ dòng trống ở cuối bảng', count( $r2 ), 3 );
t( 'cột bỏ trống giữa bảng không làm lệch cột', $r2[0], array( 'Họ tên', '', 'Bộ phận' ) );
t( 'giá trị vẫn nằm đúng cột', array( $r2[1][0], $r2[1][2] ), array( 'Người Một', 'Phòng kế toán' ) );
t( 'chuỗi lặp đọc đúng', $r2[2][2], 'Phòng kế toán' );

t( 'file không đọc được thì trả mảng rỗng', khh_parse_xlsx( __DIR__ . '/khong-co-file.xlsx' ), array() );

/* ------------------------------------------------------------------ *
 * Đoán cột rồi nhập thật từ dữ liệu Excel
 * ------------------------------------------------------------------ */
$header  = $rows[0];
$mapping = array();
foreach ( $header as $i => $h ) {
	$mapping[ $i ] = khh_guess_column( $h );
}
t( 'đoán đúng cột mã', $mapping[0], 'code' );
t( 'đoán đúng cột tên', $mapping[1], 'name' );
t( 'đoán đúng cột chức danh', $mapping[2], 'title' );
t( 'đoán đúng cột bộ phận', $mapping[3], 'dept' );
t( 'đoán đúng cột mảng kinh doanh', $mapping[4], 'unit' );
t( 'đoán đúng cột ngày vào làm', $mapping[5], 'start' );
t( 'đoán đúng cột lương', $mapping[7], 'salary' );

$data = array_slice( $rows, 1 );
$stat = khh_import_rows( $data, $mapping, array( 'match' => 'code_name', 'update' => true ) );
t( 'nhập được cả 3 người', $stat['added'], 3 );

$all = array_values( khh_get_coll( 'staff' ) );
$mai = null;
foreach ( $all as $s ) { if ( 'Nguyễn Thị Bảo Mai' === $s['name'] ) { $mai = $s; } }
t( 'ngày vào làm vào đúng hồ sơ', $mai['start'], '2024-03-01' );
t( 'chức danh vào đúng hồ sơ', $mai['title'], 'Nhân viên bán hàng' );
t( 'bộ phận vào đúng hồ sơ', $mai['dept'], 'KHỐI NHÂN VIÊN CƠ SỞ' );
t( 'mảng kinh doanh vào đúng hồ sơ', $mai['unit'], 'Posh' );
t( 'lương đọc ra số', $mai['salary'], 8500000 );
t( 'trạng thái thử việc nhận đúng', ( function () use ( $all ) {
	foreach ( $all as $s ) { if ( 'Hồ Thanh Ngân' === $s['name'] ) { return $s['status']; } }
	return ''; } )(), 'probation' );

/* nhập lại lần nữa: không được nhân bản */
$stat2 = khh_import_rows( $data, $mapping, array( 'match' => 'code_name', 'update' => true ) );
t( 'nhập lại không tạo thêm ai', $stat2['added'], 0 );
t( 'nhập lại là cập nhật', $stat2['updated'], 3 );
t( 'tổng hồ sơ vẫn là 3', count( khh_get_coll( 'staff' ) ), 3 );

/* ------------------------------------------------------------------ *
 * Hồ sơ tạo tay chưa có mã: không được tạo trùng
 * ------------------------------------------------------------------ */
$GLOBALS['_docs']['staff']['tay_1'] = array( 'name' => 'Giám Đốc', 'dept' => 'TỔNG GIÁM ĐỐC (CEO)', 'role' => 'admin' );
$GLOBALS['_docs']['staff']['tay_2'] = array( 'name' => 'Admin', 'dept' => 'PHÒNG KỸ THUẬT / CNTT', 'role' => 'owner' );

$them = array( array( 'MNNV2KVC0099', 'Giám Đốc', 'Tổng giám đốc', 'BAN GIÁM ĐỐC', '', '', '', '', '', '', '' ) );

$truoc = count( khh_get_coll( 'staff' ) );
$s3 = khh_import_rows( $them, $mapping, array( 'match' => 'code', 'update' => true ) );
t( 'chỉ khớp theo mã: hồ sơ chưa có mã bị tạo trùng', count( khh_get_coll( 'staff' ) ), $truoc + 1 );

/* dựng lại để thử chế độ kia */
unset( $GLOBALS['_docs']['staff'][ array_key_last( $GLOBALS['_docs']['staff'] ) ] );
$truoc = count( khh_get_coll( 'staff' ) );
$s4 = khh_import_rows( $them, $mapping, array( 'match' => 'code_name', 'update' => true ) );
t( 'khớp mã + tên: nhận ra đúng người cũ', $s4['updated'], 1 );
t( '  → không sinh hồ sơ mới', count( khh_get_coll( 'staff' ) ), $truoc );
t( '  → hồ sơ cũ được gắn mã', khh_get_coll( 'staff' )['tay_1']['code'], 'MNNV2KVC0099' );
t( '  → GIỮ NGUYÊN vai trò đã chỉnh trong nền tảng', khh_get_coll( 'staff' )['tay_1']['role'], 'admin' );
t( '  → giữ nguyên vai trò của người còn lại', khh_get_coll( 'staff' )['tay_2']['role'], 'owner' );

/* file có cột Vai trò thì vẫn ghi đè được */
$mp2 = $mapping; $mp2[10] = 'role';
khh_import_rows(
	array( array( 'MNNV2KVC0099', 'Giám Đốc', '', '', '', '', '', '', '', '', 'Quản lý' ) ),
	$mp2,
	array( 'match' => 'code_name', 'update' => true )
);
t( 'file có cột Vai trò thì vẫn đổi được', khh_get_coll( 'staff' )['tay_1']['role'], 'manager' );

/* ô Vai trò để trống thì giữ nguyên, không tụt về Nhân viên */
khh_import_rows(
	array( array( 'MNNV2KVC0099', 'Giám Đốc', '', '', '', '', '', '', '', '', '' ) ),
	$mp2,
	array( 'match' => 'code_name', 'update' => true )
);
t( 'ô Vai trò trống thì giữ nguyên', khh_get_coll( 'staff' )['tay_1']['role'], 'manager' );

/* người mới vẫn mặc định Nhân viên / Đang làm việc */
khh_import_rows(
	array( array( 'MNNV2KVC0500', 'Người Hoàn Toàn Mới', '', '', '', '', '', '', '', '', '' ) ),
	$mp2,
	array( 'match' => 'code_name', 'update' => true )
);
$moi = null;
foreach ( khh_get_coll( 'staff' ) as $s2 ) { if ( 'Người Hoàn Toàn Mới' === $s2['name'] ) { $moi = $s2; } }
t( 'người mới mặc định Nhân viên', $moi['role'], 'staff' );
t( 'người mới mặc định Đang làm việc', $moi['status'], 'active' );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
