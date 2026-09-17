<?php
/* Kiểm thử đọc chấm công từ cơ sở dữ liệu phần mềm cũ nằm chung máy chủ */
define( 'ABSPATH', '/tmp/fakewp/' );
define( 'DB_NAME', 'wp_khmatrix' );
define( 'KHH_DIR', __DIR__ . '/../../../wordpress/khh-platform/' );

$GLOBALS['_docs'] = array( 'staff' => array(), 'attendance' => array() );

function add_action() {}
function add_submenu_page() {}
function register_deactivation_hook() {}
function current_time( $t ) { return time(); }
$GLOBALS['_opts'] = array(); $GLOBALS['_trans'] = array(); $GLOBALS['_cron'] = 0;
function get_option( $k, $d = false ) { return isset( $GLOBALS['_opts'][ $k ] ) ? $GLOBALS['_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['_opts'][ $k ] = $v; return true; }
function get_transient( $k ) { return isset( $GLOBALS['_trans'][ $k ] ) ? $GLOBALS['_trans'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['_trans'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['_trans'][ $k ] ); return true; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t ); }
function esc_sql( $t ) { return addslashes( (string) $t ); }
function khh_table() { return 'wp_khh_docs'; }
function khh_get_coll( $c ) { return isset( $GLOBALS['_docs'][ $c ] ) ? $GLOBALS['_docs'][ $c ] : array(); }
function khh_put_doc( $c, $id, $b ) { $GLOBALS['_docs'][ $c ][ $id ] = $b; return true; }

/* hai hàm dùng chung lấy nguyên văn từ import.php */
$imp = file_get_contents( KHH_DIR . 'import.php' );
foreach ( array( 'khh_slug_vi', 'khh_parse_date' ) as $fn ) {
	preg_match( '/\nfunction ' . $fn . '\s*\(.*?\n\}/s', $imp, $m );
	eval( $m[0] );
}

/* phần logic của trang nhập chấm công (bỏ phần giao diện quản trị) */
$src = file_get_contents( KHH_DIR . 'nhap-cham-cong.php' );
$src = preg_replace( '/^<\?php/', '', $src, 1 );
$src = substr( $src, 0, strpos( $src, "add_action( 'admin_menu', 'khh_cc_menu', 24 );" ) );
eval( $src );

/* PHP tự đổi khoá "17" thành số nguyên; qua JSON tới trình duyệt thì khoá luôn
   là chuỗi nên vẫn khớp. Ở đây chuẩn hoá về chuỗi để so cho gọn. */
function khoa_ngay( $days ) {
	return array_map( 'strval', array_keys( (array) $days ) );
}

/* Bảng chấm công giả của phần mềm cũ, để chạy thử phần tự lấy. */
$GLOBALS['_cot']  = array( 'EmployeeCode', 'WorkDate', 'CheckIn', 'CheckOut' );
$GLOBALS['_kieu'] = array( 'EmployeeCode' => 'varchar(32)', 'WorkDate' => 'date',
	'CheckIn' => 'time', 'CheckOut' => 'time' );
$GLOBALS['_ban']  = array();
$GLOBALS['_sql']  = array();
class FakeDB {
	public function get_col( $sql ) {
		$GLOBALS['_sql'][] = $sql;
		if ( 0 === stripos( $sql, 'SHOW DATABASES' ) ) { return array( 'wp_khmatrix', 'chamcong_cu' ); }
		if ( 0 === stripos( $sql, 'SHOW TABLES' ) ) { return array( 'attendance' ); }
		if ( 0 === stripos( $sql, 'SHOW COLUMNS' ) ) { return $GLOBALS['_cot']; }
		return array();
	}
	public function get_var( $sql ) { return count( $GLOBALS['_ban'] ); }
	public function get_results( $sql, $x = null ) {
		$GLOBALS['_sql'][] = $sql;
		if ( 0 === stripos( $sql, 'SHOW COLUMNS' ) ) {
			$o = array();
			foreach ( $GLOBALS['_cot'] as $c ) {
				$o[] = array( 'Field' => $c, 'Type' => $GLOBALS['_kieu'][ $c ] );
			}
			return $o;
		}
		$out = array();
		foreach ( $GLOBALS['_ban'] as $r ) {
			/* Bắt chước MySQL: lọc đúng khoảng ngày nếu câu lệnh có WHERE. */
			if ( preg_match( "/>= '([\d-]+) 00:00:00'/", $sql, $m ) && $r[1] < $m[1] ) { continue; }
			if ( preg_match( "/<= '([\d-]+) 23:59:59'/", $sql, $m ) && $r[1] > $m[1] ) { continue; }
			$out[] = array_combine( $GLOBALS['_cot'], $r );
		}
		return $out;
	}
	public function prepare( $sql, $args ) {
		foreach ( (array) $args as $a ) {
			$sql = preg_replace( '/%s/', "'" . addslashes( $a ) . "'", $sql, 1 );
		}
		return $sql;
	}
}
$wpdb = new FakeDB();
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; printf( "FAIL  %-56s got=%s want=%s\n", $name, var_export( $got, true ), var_export( $want, true ) ); }
	else { printf( "PASS  %-56s got=%s\n", $name, var_export( $got, true ) ); }
}

/* ---------- đọc giờ ---------- */
t( 'đọc giờ từ ngày giờ đầy đủ', khh_cc_gio( '2026-09-15 08:10:23' ), '08:10' );
t( 'đọc giờ trần', khh_cc_gio( '08:10' ), '08:10' );
t( 'đọc giờ kiểu 1 chữ số', khh_cc_gio( '8:05' ), '08:05' );
t( 'đọc giờ chiều kiểu PM', khh_cc_gio( '6:05 PM' ), '18:05' );
t( 'đọc 12:30 AM thành nửa đêm', khh_cc_gio( '12:30 AM' ), '00:30' );
t( 'đọc 12:30 PM thành giữa trưa', khh_cc_gio( '12:30 PM' ), '12:30' );
t( 'ô trống không ra giờ', khh_cc_gio( '' ), '' );
t( 'giờ vô lý bị loại', khh_cc_gio( '99:99' ), '' );
t( 'chuỗi không có giờ thì bỏ', khh_cc_gio( 'nghỉ phép' ), '' );

/* ---------- đọc giờ công ---------- */
t( '8 tiếng ra 480 phút', khh_cc_phut( '8' ), 480 );
t( '7.5 tiếng ra 450 phút', khh_cc_phut( '7.5' ), 450 );
t( 'dấu phẩy cũng đọc được', khh_cc_phut( '7,5' ), 450 );
t( '7:30 ra 450 phút', khh_cc_phut( '7:30' ), 450 );
t( 'số lớn hơn 24 hiểu là đã tính bằng phút', khh_cc_phut( '450' ), 450 );
t( 'số âm bỏ qua', khh_cc_phut( '-3' ), 0 );
t( 'chữ bỏ qua', khh_cc_phut( 'N/A' ), 0 );

/* ---------- đoán cột ---------- */
t( 'đoán cột mã nhân viên', khh_cc_guess( 'EmployeeCode' ), 'code' );
t( 'đoán cột ngày công', khh_cc_guess( 'work_date' ), 'date' );
t( 'đoán cột giờ vào', khh_cc_guess( 'Check In' ), 'in' );
t( 'đoán cột mốc quẹt thẻ', khh_cc_guess( 'CHECKTIME' ), 'time' );
t( 'đoán cột giờ công', khh_cc_guess( 'working_hours' ), 'gio' );
t( 'cột lạ thì không đoán bừa', khh_cc_guess( 'xyz_123' ), '' );

/* ---------- dựng bản ghi ---------- */
$GLOBALS['_docs']['staff'] = array(
	's1' => array( 'name' => 'Nguyễn Mai Phương', 'code' => 'MNNV0001' ),
	's2' => array( 'name' => 'Phạm Hòa Bình',    'code' => 'MNNV0002' ),
	's3' => array( 'name' => 'Trần Minh Chiến',  'code' => '' ),
	's4' => array( 'name' => 'Trần Minh Chiến',  'code' => 'MNNV0004' ),
);

/* dạng 1: mỗi dòng một ngày, có giờ vào / giờ ra */
$header = array( 'emp', 'ngay', 'vao', 'ra', 'ca' );
$map    = array( 0 => 'code', 1 => 'date', 2 => 'in', 3 => 'out', 4 => 'ca' );
$rows   = array(
	array( 'MNNV0001', '2026-09-15', '08:10:00', '18:00:00', 'C1-C2' ),
	array( 'MNNV0002', '15/09/2026', '08:30', '12:00', 'C1' ),
	array( 'KHONGCO', '2026-09-15', '08:00', '17:00', '' ),
	array( 'MNNV0001', '', '08:00', '17:00', '' ),
);
list( $docs, $st ) = khh_cc_build( $rows, $header, $map );
t( 'đọc hết số dòng', $st['rows'], 4 );
t( 'dùng được 2 dòng', $st['used'], 2 );
t( '  người lạ bị bỏ', $st['noStaff'], 1 );
t( '  thiếu ngày bị bỏ', $st['noDate'], 1 );
t( '  nêu tên người chưa khớp', $st['lost'], array( 'KHONGCO' ) );
t( 'gom thành 2 bản ghi tháng', count( $docs ), 2 );
$d1 = null;
foreach ( $docs as $d ) { if ( 's1_202609' === $d['id'] ) { $d1 = $d; } }
t( 'đặt đúng mã bản ghi', null !== $d1, true );
t( '  đúng kỳ', $d1['cycle'], '2026-09' );
t( '  đúng ngày trong tháng', khoa_ngay( $d1['days'] ), array( '15' ) );
t( '  giờ vào bỏ phần giây', $d1['days']['15']['in'], '08:10' );
t( '  giờ ra bỏ phần giây', $d1['days']['15']['out'], '18:00' );
t( '  giữ mã ca', $d1['days']['15']['ca'], 'C1-C2' );
t( '  KHÔNG tự đặt giờ công (để nền tảng tự tính)', isset( $d1['days']['15']['m'] ), false );
t( 'ngày dd/mm/yyyy đọc đúng, không lộn tháng', (function () use ( $docs ) {
	foreach ( $docs as $d ) { if ( 's2_202609' === $d['id'] ) { return khoa_ngay( $d['days'] ); } }
	return null;
} )(), array( '15' ) );

/* Ngày một chữ số phải là khoá hai chữ số, khớp với cách giao diện dò ô. */
list( $docs0 ) = khh_cc_build(
	array( array( 'MNNV0001', '2026-09-05', '08:00', '17:00', '' ) ), $header, $map );
t( 'ngày mùng 5 lưu khoá "05" chứ không phải "5"',
	strpos( json_encode( $docs0[0]['days'] ), '"05":' ) !== false, true );

/* dạng 2: có sẵn cột giờ công */
$rows2 = array( array( 'MNNV0001', '2026-09-16', '', '', '' , '7.5' ) );
list( $docs2 ) = khh_cc_build( $rows2, array_merge( $header, array( 'gio' ) ),
	array( 0 => 'code', 1 => 'date', 5 => 'gio' ) );
t( 'cột giờ công thành số phút chốt', $docs2[0]['days']['16']['m'], 450 );

/* dạng 3: nhật ký thô, mỗi lần quẹt một dòng */
$rows3 = array(
	array( 'MNNV0002', '2026-09-17 08:12:00' ),
	array( 'MNNV0002', '2026-09-17 12:01:00' ),
	array( 'MNNV0002', '2026-09-17 17:58:00' ),
	array( 'MNNV0002', '2026-09-18 09:00:00' ),
);
list( $docs3, $st3 ) = khh_cc_build( $rows3, array( 'emp', 'moc' ), array( 0 => 'code', 1 => 'time' ) );
t( 'nhật ký thô gom về một bản ghi', count( $docs3 ), 1 );
t( '  sớm nhất thành giờ vào', $docs3[0]['days']['17']['in'], '08:12' );
t( '  muộn nhất thành giờ ra', $docs3[0]['days']['17']['out'], '17:58' );
t( '  ngày lấy luôn từ mốc chấm công', khoa_ngay( $docs3[0]['days'] ), array( '17', '18' ) );
t( '  cả ngày quẹt một lần thì không bịa giờ ra', isset( $docs3[0]['days']['18']['out'] ), false );
t( '  giờ vào ngày đó vẫn còn', $docs3[0]['days']['18']['in'], '09:00' );

/* khớp theo tên khi không có mã, và bỏ khi trùng tên */
$rows4 = array(
	array( 'Nguyễn Mai Phương', '2026-09-20', '08:00', '17:00' ),
	array( 'nguyen mai phuong', '2026-09-21', '08:00', '17:00' ),
	array( 'Trần Minh Chiến', '2026-09-20', '08:00', '17:00' ),
);
list( $docs4, $st4 ) = khh_cc_build( $rows4, array( 'ten', 'ngay', 'vao', 'ra' ),
	array( 0 => 'name', 1 => 'date', 2 => 'in', 3 => 'out' ) );
t( 'khớp được theo tên', $st4['used'], 2 );
t( '  không phân biệt dấu và hoa thường', count( $docs4[0]['days'] ), 2 );
t( '  tên trùng nhau thì KHÔNG đoán bừa', $st4['dupName'], 1 );

/* giới hạn khoảng ngày */
list( $docs5, $st5 ) = khh_cc_build( $rows, $header, $map, array( 'from' => '2026-10-01' ) );
t( 'ngoài khoảng ngày thì bỏ', $st5['used'], 0 );
t( '  và đếm riêng', $st5['outside'], 2 );

/* ---------- đoán bảng chấm công ---------- */
t( 'bảng attendance đủ cột được chấm điểm cao',
	khh_cc_diem( 'chamcong_cu', 'attendance' ) >= 5, true );
t( 'bảng tên chẳng liên quan thì 0 điểm', khh_cc_diem( 'chamcong_cu', 'wp_options' ), 0 );
t( 'bảng tên nghi ngờ mà không có cột người thì điểm thấp', (function () {
	$cu = $GLOBALS['_cot'];
	$GLOBALS['_cot']  = array( 'foo', 'bar' );
	$GLOBALS['_kieu'] = array( 'foo' => 'int', 'bar' => 'int' );
	$d = khh_cc_diem( 'chamcong_cu', 'att_log' );
	$GLOBALS['_cot'] = $cu;
	$GLOBALS['_kieu'] = array( 'EmployeeCode' => 'varchar(32)', 'WorkDate' => 'date',
		'CheckIn' => 'time', 'CheckOut' => 'time' );
	return $d;
} )(), 3 );

/* ---------- đọc thẳng, không chép ---------- */
$GLOBALS['_docs']['attendance'] = array();
$GLOBALS['_ban'] = array(
	array( 'MNNV0001', '2026-09-15', '08:10:00', '18:00:00' ),
	array( 'MNNV0002', '2026-09-16', '08:00:00', '17:00:00' ),
	array( 'MNNV0001', '2020-03-02', '08:00:00', '17:00:00' ), // quá cũ, ngoài cửa sổ
);
$nguon = array(
	'bat' => 1, 'db' => 'chamcong_cu', 'tbl' => 'attendance',
	'map' => array( 0 => 'code', 1 => 'date', 2 => 'in', 3 => 'out' ),
	'thang' => 3, 'ket' => array(),
);
khh_cc_set_nguon( $nguon );

t( 'chưa khai nguồn thì không đọc bừa',
	khh_cc_doc_song( array( 'db' => '', 'tbl' => '', 'map' => array(), 'thang' => 3 ) ), null );

$GLOBALS['_sql'] = array();
$kq = khh_cc_doc_song();
t( 'đọc thẳng ra bản ghi', is_array( $kq ), true );
list( $docs_s, $stat_s ) = $kq;
t( '  chỉ đọc trong cửa sổ tháng', $stat_s['rows'], 2 );
t( '  dựng đủ bản ghi', count( $docs_s ), 2 );
t( 'lọc thẳng bằng SQL, không kéo cả bảng về', (bool) array_filter( $GLOBALS['_sql'], function ( $q ) {
	return 0 === stripos( $q, 'SELECT' ) && false !== strpos( $q, 'WHERE' );
} ), true );

t( 'KHÔNG ghi bản sao nào vào nền tảng', $GLOBALS['_docs']['attendance'], array() );

/* Cột ngày lưu dạng chữ thì KHÔNG lọc bằng SQL (so sánh chuỗi sẽ sai) */
$GLOBALS['_kieu']['WorkDate'] = 'varchar(20)';
$GLOBALS['_sql'] = array();
khh_cc_doc_song();
t( 'cột ngày kiểu chữ thì đọc hết rồi lọc trong PHP', (bool) array_filter( $GLOBALS['_sql'], function ( $q ) {
	return 0 === stripos( $q, 'SELECT' ) && false !== strpos( $q, 'WHERE' );
} ), false );
$GLOBALS['_kieu']['WorkDate'] = 'date';

/* ---------- gộp với phần người dùng sửa tay ---------- */
$song = khh_cc_song();
t( 'trả về bản ghi cho giao diện', count( $song['docs'] ), 2 );
$d1 = null;
foreach ( $song['docs'] as $d ) { if ( 's1_202609' === $d['id'] ) { $d1 = $d; } }
t( '  lấy đúng giờ vào từ phần mềm cũ', $d1['days']['15']['in'], '08:10' );
t( '  có mốc thay đổi để giao diện biết', $song['ver'] > 0, true );

$ver1 = $song['ver'];
delete_transient( KHH_CC_CACHE );
t( 'đọc lại mà dữ liệu y nguyên thì mốc KHÔNG nhích', khh_cc_song()['ver'], $ver1 );

/* người dùng sửa tay một ngày */
$GLOBALS['_docs']['attendance']['s1_202609'] = array(
	'staffId' => 's1', 'cycle' => '2026-09',
	'days' => array( '15' => array( 'm' => 300, 'ca' => 'C1' ) ),
);
delete_transient( KHH_CC_CACHE );
$song2 = khh_cc_song();
$d2 = null;
foreach ( $song2['docs'] as $d ) { if ( 's1_202609' === $d['id'] ) { $d2 = $d; } }
t( 'sửa tay thì thắng số của phần mềm cũ', $d2['days']['15']['m'], 300 );
t( '  nhưng vẫn giữ giờ vào đọc được', $d2['days']['15']['in'], '08:10' );
t( '  mốc nhích lên vì nội dung đã đổi', $song2['ver'] > $ver1, true );

/* dữ liệu bên phần mềm cũ đổi thì bên này đổi theo, không phải chạy lại gì */
$GLOBALS['_ban'][0][3] = '15:00:00';
delete_transient( KHH_CC_CACHE );
$song3 = khh_cc_song();
$d3 = null;
foreach ( $song3['docs'] as $d ) { if ( 's1_202609' === $d['id'] ) { $d3 = $d; } }
t( 'sửa bên phần mềm cũ thì bên này đổi theo', $d3['days']['15']['out'], '15:00' );
t( '  vẫn không đẻ bản sao nào',
	array_keys( $GLOBALS['_docs']['attendance'] ), array( 's1_202609' ) );

/* bộ nhớ đệm: đọc lần hai không hỏi lại cơ sở dữ liệu */
$GLOBALS['_sql'] = array();
khh_cc_song();
t( 'lần đọc trong bộ nhớ đệm không hỏi lại máy chủ', $GLOBALS['_sql'], array() );

/* sửa tay thì quên đệm ngay */
khh_cc_quen( 'attendance' );
t( 'sửa ngày công thì bỏ đệm', get_transient( KHH_CC_CACHE ), false );
khh_cc_song();
khh_cc_quen( 'tasks' );
t( 'sửa mục khác thì giữ nguyên đệm', is_array( get_transient( KHH_CC_CACHE ) ), true );

/* tắt thì thôi hẳn */
$n4 = khh_cc_get_nguon();
$n4['bat'] = 0;
khh_cc_set_nguon( $n4 );
t( 'tắt rồi thì không đọc nữa', khh_cc_song(), null );

/* Cột ngày dùng để lọc: ưu tiên cột Ngày, không có thì lấy Mốc chấm công */
t( 'biết cột nào là cột ngày', khh_cc_cot_ngay( $nguon ), 'WorkDate' );
t( '  không có cột Ngày thì dùng Mốc chấm công', khh_cc_cot_ngay( array_merge( $nguon,
	array( 'map' => array( 0 => 'code', 2 => 'time' ) ) ) ), 'CheckIn' );

/* Cửa sổ tháng */
list( $tu, $den ) = khh_cc_khoang( array( 'thang' => 1 ) );
t( 'một tháng thì từ mùng 1 tháng này', substr( $tu, 8 ), '01' );
t( '  đến cuối tháng này', substr( $tu, 0, 7 ), substr( $den, 0, 7 ) );
list( $tu3 ) = khh_cc_khoang( array( 'thang' => 3 ) );
t( 'ba tháng thì lùi lại hai tháng', $tu3 < $tu, true );
list( $tu9 ) = khh_cc_khoang( array( 'thang' => 99 ) );
t( 'số tháng vô lý bị chặn ở 12', $tu9 >= gmdate( 'Y-m-01', strtotime( '-11 month' ) ), true );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
