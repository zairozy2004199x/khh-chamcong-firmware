<?php
/* Giả lập WordPress để chạy thử endpoint ghi hàng loạt /bulk và quyền ghi nhóm "depts" */
define( 'ABSPATH', '/tmp/fakewp/' );

$GLOBALS['_role'] = 'owner';
$GLOBALS['_in']   = true;

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_activation_hook() {}
function add_menu_page() {}
function add_submenu_page() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://site.test/wp-content/plugins/khh-platform/'; }
function home_url( $p = '' ) { return 'https://site.test' . $p; }
function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'https://site.test/wp-json/' . $p; }
function esc_url_raw( $u ) { return $u; }
function esc_url( $u ) { return $u; }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_email( $e ) { return trim( $e ); }
function sanitize_user( $u, $s = false ) { return preg_replace( '/[^a-z0-9._-]/i', '', $u ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $k ) ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function wp_generate_password( $l = 12 ) { return str_repeat( 'x', $l ); }
function wp_rand( $a, $b ) { return $a; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $d = false ) { return $GLOBALS['_opts'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['_opts'][ $k ] = $v; return true; }
function get_current_user_id() { return 7; }
function is_user_logged_in() { return $GLOBALS['_in']; }
function current_user_can( $c ) { return true; }
function update_user_meta( $id, $k, $v ) { return true; }
function get_user_meta( $id, $k, $single = true ) { return ''; }
function user_can( $id, $c ) { return false; }
function get_userdata( $id ) { return null; }
function get_user_by( $f, $v ) { return false; }
function get_users( $a = array() ) { return array(); }
function username_exists( $l ) { return false; }
function wp_insert_user( $a ) { return 1; }
function wp_new_user_notification() {}
function wp_remote_get( $u, $a = array() ) { return array(); }
function wp_remote_retrieve_response_code( $r ) { return 0; }
function wp_remote_retrieve_body( $r ) { return ''; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function rest_ensure_response( $r ) { return $r; }
function register_rest_route() {}
class WP_Error {
	public $c, $m, $d;
	function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; $this->d = $d; }
	function get_error_message() { return $this->m; }
	function status() { return isset( $this->d['status'] ) ? $this->d['status'] : 0; }
}
/* vai trò nền tảng do phép thử điều khiển */
function khh_user_role( $id = 0 ) { return $GLOBALS['_role']; }

/* $wpdb giả: giữ các dòng trong mảng */
class FakeWpdb {
	public $rows = array();
	public $prefix = 'wp_';
	public function replace( $table, $data, $fmt ) {
		$this->rows[ $data['coll'] . '/' . $data['doc_id'] ] = $data;
		return 1;
	}
	public function update() { return 1; }
	public function get_results() { return array(); }
	public function prepare( $q ) { return $q; }
}
$GLOBALS['wpdb'] = new FakeWpdb();

/* yêu cầu REST giả */
class FakeReq {
	private $p;
	function __construct( $p ) { $this->p = $p; }
	function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
}

/* nạp plugin, bỏ các file phụ và thay hàm đã giả lập */
$src = file_get_contents( __DIR__ . '/../../../wordpress/khh-platform/khh-platform.php' );
$src = preg_replace( '/^<\?php/', '', $src, 1 );
$src = preg_replace( '/^\s*require_once KHH_DIR .*$/m', '', $src );
/* Các dòng `require_once KHH_DIR …` vừa bị bỏ ở trên, nên LỜI GỌI KHỞI ĐỘNG đi kèm chúng phải
   bỏ theo — bỏ cái nạp lớp mà giữ `KHH_TuCapNhat::init()` thì nổ "Class not found", một câu lỗi
   chẳng liên quan gì tới thứ bài thử đang canh. */
$src = preg_replace( '/^[A-Za-z_]+::init\(\);$/m', '', $src );
$src = preg_replace( '/\nfunction khh_user_role\s*\([^)]*\)\s*\{.*?\n\}/s', "\n", $src, 1 );
eval( $src );

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; }
	printf( "%s  %-56s got=%s\n", $ok ? 'PASS' : 'FAIL', $name, var_export( $got, true ) );
}
function bulk( $coll, $docs ) {
	return khh_rest_bulk( new FakeReq( array( 'coll' => $coll, 'docs' => $docs ) ) );
}
function mkdocs( $n, $dept = 'Phòng kỹ thuật' ) {
	$out = array();
	for ( $i = 1; $i <= $n; $i++ ) {
		$out[] = array(
			'id'   => 'st_' . $i,
			'data' => array( 'name' => 'Nhân sự ' . $i, 'dept' => $dept ),
		);
	}
	return $out;
}

/* --- nhóm dữ liệu bộ phận bị khoá cho nhân viên thường --- */
t( '"depts" nằm trong nhóm hạn chế', in_array( 'depts', khh_restricted_collections(), true ), true );

$GLOBALS['_role'] = 'owner';
t( 'Chủ sở hữu ghi được bộ phận', khh_can_write( 'depts' ), true );
$GLOBALS['_role'] = 'admin';
t( 'Quản trị ghi được bộ phận', khh_can_write( 'depts' ), true );
$GLOBALS['_role'] = 'manager';
t( 'Quản lý KHÔNG ghi được bộ phận', khh_can_write( 'depts' ), false );
$GLOBALS['_role'] = 'staff';
t( 'Nhân viên KHÔNG ghi được bộ phận', khh_can_write( 'depts' ), false );

/* --- ghi hàng loạt --- */
$GLOBALS['_role'] = 'owner';
$wpdb->rows = array();
$r = bulk( 'staff', mkdocs( 199, 'Phòng Vận hành' ) );
t( 'lưu được 199 hồ sơ trong một lượt', $r['saved'], 199 );
t( 'đúng số dòng ghi vào bảng', count( $wpdb->rows ), 199 );
t( 'nội dung ghi đúng', json_decode( $wpdb->rows['staff/st_50']['payload'], true )['dept'], 'Phòng Vận hành' );
t( 'không đánh dấu xoá', (int) $wpdb->rows['staff/st_1']['deleted'], 0 );
t( 'ghi nhận người sửa', (int) $wpdb->rows['staff/st_1']['updated_by'], 7 );

/* --- ghi đè bản cũ --- */
$r = bulk( 'staff', array( array( 'id' => 'st_50', 'data' => array( 'name' => 'Nhân sự 50', 'dept' => 'Phòng mới' ) ) ) );
t( 'ghi đè đúng bản ghi cũ', json_decode( $wpdb->rows['staff/st_50']['payload'], true )['dept'], 'Phòng mới' );
t( 'không sinh thêm dòng khi ghi đè', count( $wpdb->rows ), 199 );

/* --- chặn quyền --- */
$GLOBALS['_role'] = 'staff';
$r = bulk( 'staff', mkdocs( 3 ) );
t( 'Nhân viên bị chặn ghi hàng loạt vào hồ sơ nhân sự', is_wp_error( $r ) && $r->status() === 403, true );
$r = bulk( 'depts', array( array( 'id' => 'd1', 'data' => array( 'name' => 'Phòng lạ' ) ) ) );
t( 'Nhân viên bị chặn ghi hàng loạt vào bộ phận', is_wp_error( $r ) && $r->status() === 403, true );

$GLOBALS['_role'] = 'manager';
$r = bulk( 'tasks', array( array( 'id' => 'tk1', 'data' => array( 'title' => 'Việc' ) ) ) );
t( 'Quản lý vẫn ghi được nhóm không hạn chế', isset( $r['saved'] ) && 1 === $r['saved'], true );

$GLOBALS['_in'] = false;
t( 'chưa đăng nhập thì không ghi được', khh_can_write( 'tasks' ), false );
$GLOBALS['_in'] = true;

/* --- dữ liệu hỏng --- */
$GLOBALS['_role'] = 'owner';
$r = bulk( 'khong_co_nhom_nay', mkdocs( 2 ) );
t( 'nhóm dữ liệu lạ bị từ chối', is_wp_error( $r ) && $r->status() === 400, true );
$r = bulk( 'staff', array() );
t( 'lô rỗng bị từ chối', is_wp_error( $r ) && $r->status() === 400, true );
$r = bulk( 'staff', mkdocs( 201 ) );
t( 'quá 200 bản ghi bị từ chối', is_wp_error( $r ) && $r->status() === 413, true );

$wpdb->rows = array();
$r = bulk( 'staff', array(
	array( 'id' => 'ok_1', 'data' => array( 'name' => 'Hợp lệ' ) ),
	array( 'id' => 'xấu/id', 'data' => array( 'name' => 'Mã sai' ) ),
	array( 'id' => 'ok_2', 'data' => 'không phải object' ),
) );
t( 'bỏ qua bản ghi hỏng, vẫn lưu bản hợp lệ', $r['saved'], 1 );
t( 'báo lại số bản ghi bị bỏ', count( $r['skipped'] ), 2 );
t( 'chỉ bản hợp lệ được ghi', array_keys( $wpdb->rows ), array( 'staff/ok_1' ) );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
