<?php
/* Giả lập WordPress để chạy thử phần nhập hồ sơ nhân sự từ hệ thống cũ */
define( 'ABSPATH', '/tmp/fakewp/' );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['_docs'] = array( 'staff' => array() );

function add_action() {}
function add_submenu_page() {}
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $k ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t ); }
function esc_url( $u ) { return $u; }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function rest_url( $p = '' ) { return '/wp-json/' . $p; }
function current_user_can( $c ) { return true; }
function check_admin_referer() { return true; }
function wp_nonce_field() {}
function submit_button() {}
function selected() {}
function wp_die( $m ) { throw new Exception( $m ); }
function khh_table() { return 'wp_khh_docs'; }
function khh_get_coll( $c ) { return isset( $GLOBALS['_docs'][ $c ] ) ? $GLOBALS['_docs'][ $c ] : array(); }
function khh_put_doc( $c, $i, $b ) { $GLOBALS['_docs'][ $c ][ $i ] = $b; return true; }

$src = file_get_contents( KHH_DIR . 'import.php' );
eval( preg_replace( '/^<\?php/', '', $src, 1 ) );

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-52s got=%s\n", $ok ? 'PASS' : 'FAIL', $name, var_export( $got, true ) );
}

/* --- CSV thật từ một phần mềm nhân sự: có BOM, dấu chấm phẩy, ngày kiểu Việt --- */
$csv = "\xEF\xBB\xBF" . "Mã NV;Họ và tên;Chức danh;Phòng ban;Email;SĐT;Ngày sinh;Giới tính;Ngày vào làm;Lương cơ bản;Phụ cấp;Tình trạng;Ghi chú\n"
	. "NV-001;Trần Văn Hùng;Kỹ thuật viên;Phòng kỹ thuật;hung@khh.vn;0901111111;12/07/1995;Nam;01/03/2021;15.000.000;2.000.000;Đang làm việc;Đội lắp đặt\n"
	. "NV-002;Lê Thị Mai;Kế toán trưởng;Phòng kế toán;mai@khh.vn;0902222222;03/11/1988;Nữ;15/06/2019;22.000.000;3.000.000;Đang làm việc;\n"
	. "NV-003;Phạm Quốc Anh;Nhân viên kinh doanh;Phòng kinh doanh;anh@khh.vn;0903333333;25/02/1999;Nam;10/01/2024;11.000.000;4.000.000;Thử việc;\n";

$rows   = khh_parse_csv( $csv );
$header = array_shift( $rows );
t( 'đọc đúng số cột', count( $header ), 13 );
t( 'đọc đúng số dòng', count( $rows ), 3 );
t( 'bỏ được BOM ở đầu file', $header[0], 'Mã NV' );

/* --- đoán cột --- */
t( 'đoán "Họ và tên"', khh_guess_column( 'Họ và tên' ), 'name' );
t( 'đoán "Mã NV"', khh_guess_column( 'Mã NV' ), 'code' );
t( 'đoán "Phòng ban"', khh_guess_column( 'Phòng ban' ), 'dept' );
t( 'đoán "SĐT"', khh_guess_column( 'SĐT' ), 'phone' );
t( 'đoán "Ngày vào làm"', khh_guess_column( 'Ngày vào làm' ), 'start' );
t( 'đoán "Lương cơ bản"', khh_guess_column( 'Lương cơ bản' ), 'salary' );
t( 'cột lạ thì để trống', khh_guess_column( 'Số ghế ngồi' ), '' );

/* --- chuyển kiểu --- */
t( 'ngày 01/03/2021', khh_parse_date( '01/03/2021' ), '2021-03-01' );
t( 'ngày 1/3/21', khh_parse_date( '1/3/21' ), '2021-03-01' );
t( 'ngày đã chuẩn ISO', khh_parse_date( '2021-03-01' ), '2021-03-01' );
t( 'ngày rỗng', khh_parse_date( '' ), '' );
t( 'tiền 15.000.000', khh_parse_money( '15.000.000' ), 15000000 );
t( 'tiền có ký hiệu ₫', khh_parse_money( '22.000.000 ₫' ), 22000000 );
t( 'giới tính Nữ', khh_parse_gender( 'Nữ' ), 'Nữ' );
t( 'giới tính Male', khh_parse_gender( 'Male' ), 'Nam' );
t( 'tình trạng Thử việc', khh_parse_status( 'Thử việc' ), 'probation' );
t( 'tình trạng Đã nghỉ', khh_parse_status( 'Đã nghỉ' ), 'left' );
t( 'vai trò Trưởng phòng', khh_parse_role( 'Trưởng phòng' ), 'manager' );
t( 'vai trò trống → nhân viên', khh_parse_role( '' ), 'staff' );

/* --- nhập lần đầu --- */
$mapping = array();
foreach ( $header as $i => $h ) { $mapping[ $i ] = khh_guess_column( $h ); }
$stat = khh_import_rows( $rows, $mapping, array( 'match' => 'code', 'update' => true ) );
t( 'thêm mới 3 người', $stat['added'], 3 );
t( 'không cập nhật ai', $stat['updated'], 0 );

$staff = khh_get_coll( 'staff' );
$hung  = null;
foreach ( $staff as $s ) { if ( 'NV-001' === ( isset( $s['code'] ) ? $s['code'] : '' ) ) { $hung = $s; } }
t( 'lưu đúng tên', $hung['name'], 'Trần Văn Hùng' );
t( 'lưu đúng ngày vào làm', $hung['start'], '2021-03-01' );
t( 'lưu lương thành số', $hung['salary'], 15000000 );
t( 'lưu đúng email', $hung['email'], 'hung@khh.vn' );
t( 'lưu đúng bộ phận', $hung['dept'], 'Phòng kỹ thuật' );
t( 'thử việc thành probation', $staff[ array_keys( $staff )[2] ]['status'], 'probation' );

/* --- nhập lại: không cập nhật --- */
$stat2 = khh_import_rows( $rows, $mapping, array( 'match' => 'code', 'update' => false ) );
t( 'nhập lại mà không cho cập nhật → bỏ qua hết', $stat2['skipped'], 3 );
t( 'không tạo bản trùng', count( khh_get_coll( 'staff' ) ), 3 );

/* --- nhập lại: có cập nhật, đổi chức danh --- */
$rows2 = $rows;
$rows2[0][2] = 'Trưởng nhóm kỹ thuật';
$stat3 = khh_import_rows( $rows2, $mapping, array( 'match' => 'code', 'update' => true ) );
t( 'cập nhật 3 người', $stat3['updated'], 3 );
t( 'vẫn 3 hồ sơ, không nhân bản', count( khh_get_coll( 'staff' ) ), 3 );
$staff = khh_get_coll( 'staff' );
foreach ( $staff as $s ) { if ( 'NV-001' === $s['code'] ) { $hung = $s; } }
t( 'chức danh đã đổi', $hung['title'], 'Trưởng nhóm kỹ thuật' );
t( 'dữ liệu cũ không bị mất', $hung['email'], 'hung@khh.vn' );

/* --- khớp theo mã dù tên viết khác --- */
$rows3 = $rows;
$rows3[0][1] = 'Trần Văn  Hùng (Hùng IT)';
$stat4 = khh_import_rows( $rows3, $mapping, array( 'match' => 'code', 'update' => true ) );
t( 'khớp theo mã, không tạo người mới', count( khh_get_coll( 'staff' ) ), 3 );

/* --- dòng thiếu tên thì bỏ qua --- */
$stat5 = khh_import_rows( array( array( 'NV-009', '', 'x', 'y', '', '', '', '', '', '', '', '', '' ) ), $mapping, array( 'match' => 'code', 'update' => true ) );
t( 'dòng không có tên bị bỏ qua', $stat5['skipped'], 1 );

/* --- file dán từ Excel (tab) --- */
$tsv  = "Họ và tên\tMã NV\tEmail\nNguyễn Thị Lan\tNV-010\tlan@khh.vn\n";
$rt   = khh_parse_csv( $tsv );
t( 'đọc được dữ liệu dán từ Excel', count( $rt ), 2 );
t( '  → đúng cột', $rt[1][1], 'NV-010' );

/* ================= nguồn là cơ sở dữ liệu cùng site ================= */

class FakeWpdb {
	public $usermeta = 'wp_usermeta';
	public $postmeta = 'wp_postmeta';
	public $posts    = 'wp_posts';
	public $prefix   = 'wp_';
	public $tables   = array( 'wp_posts', 'wp_users', 'wp_hrm_employees', 'wp_khh_docs' );
	public $cols     = array( 'wp_hrm_employees' => array( 'id', 'employee_id', 'display_name', 'designation', 'department', 'user_email', 'phone_number', 'hiring_date', 'pay_rate', 'status' ) );
	public $rows     = array(
		'wp_hrm_employees' => array(
			array( 'id' => 1, 'employee_id' => 'HR-01', 'display_name' => 'Đỗ Thu Hằng', 'designation' => 'Trưởng phòng nhân sự',
				'department' => 'Hành chính nhân sự', 'user_email' => 'hang@khh.vn', 'phone_number' => '0905000111',
				'hiring_date' => '2019-08-15', 'pay_rate' => '21000000', 'status' => 'Active' ),
			array( 'id' => 2, 'employee_id' => 'HR-02', 'display_name' => 'Vũ Minh Khôi', 'designation' => 'Kỹ sư điện',
				'department' => 'Kỹ thuật', 'user_email' => 'khoi@khh.vn', 'phone_number' => '0905000222',
				'hiring_date' => '2022-02-01', 'pay_rate' => '17500000', 'status' => 'Thử việc' ),
		),
	);
	public function get_col( $sql ) {
		if ( 'SHOW TABLES' === $sql ) { return $this->tables; }
		if ( preg_match( '/SHOW COLUMNS FROM `(.+)`/', $sql, $m ) ) {
			return isset( $this->cols[ $m[1] ] ) ? $this->cols[ $m[1] ] : array();
		}
		return array();
	}
	public function get_var( $sql ) {
		if ( preg_match( '/COUNT\(\*\) FROM `(.+)`/', $sql, $m ) ) {
			return isset( $this->rows[ $m[1] ] ) ? count( $this->rows[ $m[1] ] ) : 0;
		}
		return 0;
	}
	public function get_results( $sql, $out = null ) {
		if ( preg_match( '/FROM `(.+?)`/', $sql, $m ) ) {
			return isset( $this->rows[ $m[1] ] ) ? $this->rows[ $m[1] ] : array();
		}
		return array();
	}
	public function prepare( $q, ...$a ) { return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $q ), $a ); }
}
function esc_sql( $s ) { return str_replace( '`', '', (string) $s ); }
$GLOBALS['wpdb'] = new FakeWpdb();

t( 'liệt kê bảng, bỏ bảng của chính plugin', array_key_exists( 'wp_khh_docs', khh_db_tables() ), false );
t( 'thấy bảng nhân sự của hệ thống cũ', khh_db_tables()['wp_hrm_employees'], 2 );
t( 'nhận ra bảng trông giống nhân sự', khh_db_looks_like_hr( 'wp_hrm_employees' ), true );
t( 'bảng bài viết thì không', khh_db_looks_like_hr( 'wp_posts' ), false );

t( 'chấp nhận bảng có thật', khh_db_table_ok( 'wp_hrm_employees' ), true );
t( 'từ chối bảng không có', khh_db_table_ok( 'wp_khong_co' ), false );
t( 'từ chối mưu chèn câu lệnh', khh_db_table_ok( 'wp_users` WHERE 1=1 --' ), false );
t( 'từ chối chính bảng của plugin', khh_db_table_ok( 'wp_khh_docs' ), false );

$cols = khh_src_columns( 'table', 'wp_hrm_employees' );
t( 'đọc đúng danh sách cột', count( $cols ), 10 );
t( 'bảng giả mạo trả về rỗng', khh_src_columns( 'table', 'wp_xxx' ), array() );

t( 'đoán cột display_name', khh_guess_db_column( 'display_name' ), 'name' );
t( 'đoán cột employee_id', khh_guess_db_column( 'employee_id' ), 'code' );
t( 'đoán cột designation', khh_guess_db_column( 'designation' ), 'title' );
t( 'đoán cột department', khh_guess_db_column( 'department' ), 'dept' );
t( 'đoán cột user_email', khh_guess_db_column( 'user_email' ), 'email' );
t( 'đoán cột phone_number', khh_guess_db_column( 'phone_number' ), 'phone' );
t( 'đoán cột hiring_date', khh_guess_db_column( 'hiring_date' ), 'start' );
t( 'đoán cột pay_rate', khh_guess_db_column( 'pay_rate' ), 'salary' );
t( 'cột id kỹ thuật thì bỏ qua', khh_guess_db_column( 'id' ), '' );

$dbrows = khh_src_rows( 'table', 'wp_hrm_employees' );
t( 'đọc đúng số dòng từ bảng', count( $dbrows ), 2 );
t( 'giá trị xếp đúng cột', $dbrows[0][2], 'Đỗ Thu Hằng' );

$map2 = array();
foreach ( $cols as $i => $c ) { $map2[ $i ] = khh_guess_db_column( $c ); }
$GLOBALS['_docs']['staff'] = array();
$stat6 = khh_import_rows( $dbrows, $map2, array( 'match' => 'code', 'update' => true ) );
t( 'nhập thẳng từ bảng: thêm 2 người', $stat6['added'], 2 );
$imported = array_values( khh_get_coll( 'staff' ) );
t( '  → đúng tên', $imported[0]['name'], 'Đỗ Thu Hằng' );
t( '  → đúng mã', $imported[0]['code'], 'HR-01' );
t( '  → lương thành số', $imported[0]['salary'], 21000000 );
t( '  → ngày vào làm', $imported[0]['start'], '2019-08-15' );
t( '  → thử việc nhận đúng', $imported[1]['status'], 'probation' );
$stat7 = khh_import_rows( $dbrows, $map2, array( 'match' => 'code', 'update' => true ) );
t( 'chạy lại không nhân bản', count( khh_get_coll( 'staff' ) ), 2 );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
