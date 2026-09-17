<?php
/* Kiểm thử: cổng dữ liệu trả chấm công đọc thẳng, không lẫn với bản đã lưu */
define( 'ABSPATH', '/tmp/fakewp/' );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['_rows'] = array();
$GLOBALS['_song'] = null;

class FakeDB {
	public $prefix = 'wp_';
	public function prepare( $sql, $a = null ) { return str_replace( '%d', (int) $a, $sql ); }
	public function get_results( $sql, $x = null ) {
		preg_match( '/updated_at > (\d+)/', $sql, $m );
		$since = isset( $m[1] ) ? (int) $m[1] : 0;
		$out   = array();
		foreach ( $GLOBALS['_rows'] as $r ) {
			if ( $r['updated_at'] > $since ) { $out[] = $r; }
		}
		return $out;
	}
	public function get_charset_collate() { return ''; }
}
$wpdb = new FakeDB();

function add_action() {} function add_filter() {} function add_shortcode() {}
function register_activation_hook() {} function add_menu_page() {} function add_submenu_page() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://site.test/'; }
function rest_ensure_response( $x ) { return $x; }
function khh_table() { return 'wp_khh_docs'; }
function khh_cc_song() { return $GLOBALS['_song']; }

class FakeReq {
	private $p;
	public function __construct( $p ) { $this->p = $p; }
	public function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
}

/* chỉ lấy hàm khh_rest_state từ plugin */
$src = file_get_contents( KHH_DIR . 'khh-platform.php' );
preg_match( '/\nfunction khh_rest_state\s*\(.*?\n\}/s', $src, $m );
eval( $m[0] );

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; printf( "FAIL  %-54s got=%s want=%s\n", $name, var_export( $got, true ), var_export( $want, true ) ); }
	else { printf( "PASS  %-54s got=%s\n", $name, var_export( $got, true ) ); }
}
function doc( $coll, $id, $at, $body = array(), $del = 0 ) {
	return array( 'coll' => $coll, 'doc_id' => $id, 'payload' => json_encode( $body ),
		'deleted' => $del, 'updated_at' => $at );
}
function goi( $since ) {
	$r = khh_rest_state( new FakeReq( array( 'since' => $since ) ) );
	$r['docs'] = (array) $r['docs'];
	return $r;
}

$GLOBALS['_rows'] = array(
	doc( 'tasks', 't1', 100, array( 'title' => 'Việc A' ) ),
	doc( 'attendance', 's1_202609', 200, array( 'staffId' => 's1', 'days' => array( '15' => array( 'm' => 300 ) ) ) ),
	doc( 'staff', 's1', 50, array( 'name' => 'Mai Phương' ) ),
);

/* --- chưa khai nguồn: chạy y như cũ --- */
$GLOBALS['_song'] = null;
$r = goi( 0 );
t( 'chưa khai nguồn thì trả bản đã lưu', count( $r['docs']['attendance'] ), 1 );
t( '  các nhóm khác vẫn nguyên', count( $r['docs']['tasks'] ), 1 );
$r = goi( 150 );
t( '  vẫn lọc theo mốc thời gian', isset( $r['docs']['tasks'] ), false );

/* --- có nguồn đọc thẳng --- */
$GLOBALS['_song'] = array(
	'ver'  => 5000,
	'docs' => array(
		array( 'id' => 's1_202609', 'staffId' => 's1', 'days' => array(
			'15' => array( 'in' => '08:10', 'm' => 300 ), '16' => array( 'in' => '08:00' ) ) ),
		array( 'id' => 's2_202609', 'staffId' => 's2', 'days' => array( '16' => array( 'in' => '07:55' ) ) ),
	),
	'stat' => array(), 'at' => time(),
);
$r = goi( 0 );
t( 'có nguồn thì trả bản đọc thẳng', count( $r['docs']['attendance'] ), 2 );
t( '  KHÔNG gửi kèm bản đã lưu để đè mất', (function () use ( $r ) {
	foreach ( $r['docs']['attendance'] as $d ) {
		if ( 's1_202609' === $d['id'] ) { return isset( $d['days']['16'] ); }
	}
	return false;
} )(), true );
t( '  phần sửa tay đã gộp sẵn bên trong', (function () use ( $r ) {
	foreach ( $r['docs']['attendance'] as $d ) {
		if ( 's1_202609' === $d['id'] ) { return $d['days']['15']['m']; }
	}
	return null;
} )(), 300 );
t( '  nhóm khác không bị ảnh hưởng', count( $r['docs']['tasks'] ), 1 );
t( 'mốc trả về không lùi dưới mốc dữ liệu', $r['now'] >= 5000, true );

/* --- giao diện hỏi lại: chưa đổi thì không gửi lại cả tháng công --- */
$r2 = goi( 5000 );
t( 'hỏi lại mà chưa đổi thì không gửi lại chấm công', isset( $r2['docs']['attendance'] ), false );
$r2b = goi( 6000 );
t( '  mốc của giao diện mới hơn cũng vậy', isset( $r2b['docs']['attendance'] ), false );
t( '  và không rơi về bản đã lưu để đè mất', isset( $r2b['docs']['attendance'] ), false );

/* --- nội dung đổi: mốc nhích lên thì gửi lại --- */
$GLOBALS['_song']['ver'] = 9000;
$r3 = goi( 6000 );
t( 'đổi rồi thì gửi lại', count( $r3['docs']['attendance'] ), 2 );
t( '  mốc mới đi theo', $r3['now'] >= 9000, true );

/* --- bản ghi bị xoá vẫn báo được --- */
$GLOBALS['_rows'][] = doc( 'tasks', 't9', 300, array(), 1 );
$r4 = goi( 250 );
t( 'vẫn báo bản ghi đã xoá', $r4['deleted'], array( array( 'coll' => 'tasks', 'id' => 't9' ) ) );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
