<?php
/**
 * Giàn giáo chạy thử KHÔNG CẦN WordPress + MySQL: dựng lại vài hàm WordPress
 * và một $wpdb tối giản chạy trên SQLite, đủ để kiểm nghiệm logic của plugin.
 *
 * Chỉ dùng khi phát triển (tools/test), không nằm trong bản plugin phát hành.
 */

$GLOBALS['VHCP_TMP'] = sys_get_temp_dir() . '/vhcp-test-' . getmypid();
@mkdir( $GLOBALS['VHCP_TMP'] . '/wp-admin/includes', 0777, true );
@mkdir( $GLOBALS['VHCP_TMP'] . '/uploads', 0777, true );
file_put_contents( $GLOBALS['VHCP_TMP'] . '/wp-admin/includes/upgrade.php', "<?php\n" );

define( 'ABSPATH', $GLOBALS['VHCP_TMP'] . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['VHCP_OPT'] = array();
$GLOBALS['VHCP_TR']  = array();

function dbDelta( $sql ) { return array(); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['VHCP_OPT'] ) ? $GLOBALS['VHCP_OPT'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['VHCP_OPT'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['VHCP_OPT'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['VHCP_TR'] ) ? $GLOBALS['VHCP_TR'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['VHCP_TR'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['VHCP_TR'][ $k ] ); return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ); }
function wp_generate_password( $len = 12, $sp = true, $xsp = false ) {
	$c = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$o = '';
	for ( $i = 0; $i < $len; $i++ ) { $o .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; }
	return $o;
}
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['VHCP_TMP'] . '/uploads', 'baseurl' => 'http://example.test/wp-content/uploads' ); }
function wp_mkdir_p( $p ) { return is_dir( $p ) || mkdir( $p, 0777, true ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]+/u', '-', (string) $s ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^A-Za-z0-9-]+/', '-', (string) $s ) ); }
function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }

/* ---- Móc và luật đường dẫn ------------------------------------------------------------------
   Chỉ GHI LẠI ai gài gì, không chạy gì. Đủ để bài kiểm soát được cổng nhận chấm công có gài
   luật đường đúng và có TẮT chuyển hướng hay không — mà chuyện tắt chuyển hướng thì không mô
   phỏng nổi bằng cách gọi thật, vì nó là hành vi của WordPress thật. */
$GLOBALS['VHCP_MOC']   = array();   // hook => danh sách callback
$GLOBALS['VHCP_LUAT']  = array();   // luật đường dẫn đã gài
$GLOBALS['VHCP_QVAR']  = array();
$GLOBALS['VHCP_MA_HTTP'] = 0;
function add_action( $h, $cb, $uu = 10, $n = 1 ) { $GLOBALS['VHCP_MOC'][ $h ][] = array( $cb, $uu ); return true; }
function add_filter( $h, $cb, $uu = 10, $n = 1 ) { $GLOBALS['VHCP_MOC'][ $h ][] = array( $cb, $uu ); return true; }
function remove_action( $h, $cb, $uu = 10 ) { $GLOBALS['VHCP_MOC'][ '-' . $h ][] = array( $cb, $uu ); return true; }
function remove_filter( $h, $cb, $uu = 10 ) { $GLOBALS['VHCP_MOC'][ '-' . $h ][] = array( $cb, $uu ); return true; }
function apply_filters( $h, $v ) { return $v; }
function do_action( $h ) { return null; }
function add_rewrite_rule( $mau, $dich, $vt = 'bottom' ) { $GLOBALS['VHCP_LUAT'][ $mau ] = array( $dich, $vt ); }
function add_shortcode( $t, $cb ) { return true; }
function flush_rewrite_rules( $x = true ) { return true; }
function get_query_var( $k, $d = '' ) { return array_key_exists( $k, $GLOBALS['VHCP_QVAR'] ) ? $GLOBALS['VHCP_QVAR'][ $k ] : $d; }
function __return_false() { return false; }
function __return_true() { return true; }
function status_header( $m ) { $GLOBALS['VHCP_MA_HTTP'] = (int) $m; }
function nocache_headers() { return true; }
/* Bài kiểm PHẢI đặt được "bây giờ": chấm công online lấy giờ ở MÁY CHỦ, nên không đặt được giờ
   thì không thử nổi ca đêm, ân hạn tan làm, hay lượt 00:30 lùi về ngày hôm trước. */
$GLOBALS['VHCP_GIAY_BAY_GIO'] = null;
function vhcp_test_dat_gio( $chuoi ) {
	$GLOBALS['VHCP_GIAY_BAY_GIO'] = ( null === $chuoi ) ? null : strtotime( $chuoi . ' UTC' );
}
function current_time( $dang = 'mysql', $gmt = 0 ) {
	$t = null === $GLOBALS['VHCP_GIAY_BAY_GIO'] ? time() : $GLOBALS['VHCP_GIAY_BAY_GIO'];
	if ( 'timestamp' === $dang || 'U' === $dang ) { return $t; }
	if ( 'mysql' === $dang ) { return gmdate( 'Y-m-d H:i:s', $t ); }
	return gmdate( $dang, $t );
}
/** Cổng nhận chấm công gài hook ở ưu tiên nào — bài kiểm cần đọc được con số đó. */
function vhcp_test_uu_tien( $hook, $ten_ham ) {
	if ( empty( $GLOBALS['VHCP_MOC'][ $hook ] ) ) { return null; }
	foreach ( $GLOBALS['VHCP_MOC'][ $hook ] as $m ) {
		$cb = $m[0];
		$ten = is_array( $cb ) ? ( ( is_string( $cb[0] ) ? $cb[0] : get_class( $cb[0] ) ) . '::' . $cb[1] ) : (string) $cb;
		if ( $ten === $ten_ham ) { return $m[1]; }
	}
	return null;
}
/* Mặc định KHÔNG có quyền: phần lớn phép thử canh đúng chuyện "người không đủ quyền bị chặn",
   nên mặc định phải là chặn. Phép thử VẼ MÀN HÌNH bật cờ này lên để đi qua được chốt quyền. */
function current_user_can( $c ) { return ! empty( $GLOBALS['VHCP_CO_QUYEN'] ); }

/* ---- Đủ để VẼ được màn hình wp-admin ----------------------------------------------------
   Có bộ này thì phép thử gọi thẳng hàm vẽ trang được, và mọi lỗi nghiêm trọng lúc vẽ (hằng
   không tồn tại, gọi hàm sai tên) nổ ra ngay trong bài kiểm thay vì nổ trên trang của anh
   Thắng. Trang Cài đặt đã từng mất nút "Lưu cài đặt" đúng vì một lỗi loại đó. */
function wp_die( $m = '', $t = '', $a = array() ) { throw new RuntimeException( 'wp_die: ' . wp_strip_all_tags( (string) $m ) ); }
function wp_create_nonce( $a = -1 ) { return 'nonce_' . md5( (string) $a ); }
function wp_nonce_field( $a = -1, $n = '_wpnonce', $ref = true, $echo = true ) {
	$h = '<input type="hidden" name="' . esc_attr( $n ) . '" value="' . wp_create_nonce( $a ) . '">';
	if ( $echo ) { echo $h; }
	return $h;
}
function check_admin_referer( $a = -1, $n = '_wpnonce' ) { return true; }
function submit_button( $nhan = null, $lop = 'primary', $ten = 'submit', $bao = true ) {
	echo '<p class="submit"><button type="submit" class="button button-' . esc_attr( $lop ) . '">'
		. esc_html( null === $nhan ? 'Save Changes' : $nhan ) . '</button></p>';
}
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . ltrim( (string) $p, '/' ); }
function wp_kses_post( $s ) { return (string) $s; }
function esc_js( $s ) { return addslashes( (string) $s ); }
function selected( $a, $b = true, $echo = true ) { return ( (string) $a === (string) $b ) ? " selected='selected'" : ''; }
function disabled( $a, $b = true, $echo = true ) { return ( (string) $a === (string) $b ) ? " disabled='disabled'" : ''; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function get_current_user_id() { return 1; }
function wp_get_current_user() { return (object) array( 'ID' => 1, 'display_name' => 'admin', 'roles' => array( 'administrator' ) ); }
function wp_enqueue_script() { return true; }
function wp_enqueue_style() { return true; }
function add_menu_page( $tt, $mt, $cap, $slug, $cb = '', $icon = '', $pos = null ) {
	$GLOBALS['VHCP_MENU'][ $slug ] = array( 'ten' => $mt, 'cap' => $cap, 'cb' => $cb, 'cha' => '' );
	return $slug;
}
function add_submenu_page( $cha, $tt, $mt, $cap, $slug, $cb = '' ) {
	$GLOBALS['VHCP_MENU'][ $slug ] = array( 'ten' => $mt, 'cap' => $cap, 'cb' => $cb, 'cha' => $cha );
	return $slug;
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_textarea( $s ) { return (string) $s; }
function wp_tempnam( $p = '' ) { return tempnam( sys_get_temp_dir(), 'vhcp' ); }

/**
 * Giả lập gọi mạng: bài kiểm không ra Internet, nên $GLOBALS['VHCP_HTTP'] đóng vai
 * Google Sheet — khóa là địa chỉ (khớp một phần cũng được), giá trị là nội dung trả về.
 */
$GLOBALS['VHCP_HTTP'] = array();
$GLOBALS['VHCP_MENU'] = array();
/* Trang chủ / khu quản trị — để phép thử dựng được cảnh "đang ở trang chủ" và "đang ở wp-admin". */
$GLOBALS['VHCP_LA_TRANG_CHU'] = false;
$GLOBALS['VHCP_LA_ADMIN']     = false;
function is_front_page() { return ! empty( $GLOBALS['VHCP_LA_TRANG_CHU'] ); }
function is_admin() { return ! empty( $GLOBALS['VHCP_LA_ADMIN'] ); }
function is_wp_error( $x ) { return ( $x instanceof VHCP_Test_WP_Error ) || ( $x instanceof WP_Error ); }
class VHCP_Test_WP_Error {
	private $msg;
	public function __construct( $m ) { $this->msg = $m; }
	public function get_error_message() { return $this->msg; }
}
$GLOBALS['VHCP_DA_GET'] = array();
function wp_remote_get( $url, $args = array() ) {
	/* Ghi lại lượt GET: có nó thì phép thử đếm được SỐ LƯỢT gọi, nhờ vậy "trần vòng chuyển
	   hướng" mới kiểm được. Bản đầu chỉ đòi "có dừng" — mà 100.000 vòng thì cũng dừng, nên
	   phép phá bỏ trần không bị bắt. */
	$GLOBALS['VHCP_DA_GET'][] = $url;
	foreach ( $GLOBALS['VHCP_HTTP'] as $k => $v ) {
		if ( strpos( $url, $k ) !== false ) {
			return is_array( $v ) ? $v : array( 'code' => 200, 'body' => (string) $v );
		}
	}
	return new VHCP_Test_WP_Error( 'không có mạng trong bài kiểm: ' . $url );
}
/**
 * Giả lập gọi POST — dùng cho cầu nối sang Apps Script của plugin Thư viện hợp đồng.
 * $GLOBALS['VHD_POST'] đóng vai app Apps Script: khoá là địa chỉ (khớp một phần cũng được).
 * Mọi lượt gọi được ghi vào $GLOBALS['VHD_DA_GUI'] để bài kiểm soát được ĐÃ GỬI GÌ LÊN.
 */
$GLOBALS['VHD_POST']   = array();
$GLOBALS['VHD_DA_GUI'] = array();
function wp_remote_post( $url, $args = array() ) {
	/* Ghi lại CẢ `redirection`: cầu nối phải POST với redirection=0 rồi tự GET sang Location.
	   Không ghi lại thì không phép thử nào chứng minh được nó làm đúng chuyện đó. */
	$GLOBALS['VHD_DA_GUI'][] = array(
		'url'         => $url,
		'body'        => isset( $args['body'] ) ? $args['body'] : '',
		'redirection' => isset( $args['redirection'] ) ? $args['redirection'] : null,
	);
	foreach ( $GLOBALS['VHD_POST'] as $k => $v ) {
		if ( strpos( $url, $k ) !== false ) {
			if ( is_callable( $v ) ) { $v = call_user_func( $v, $args ); }
			return is_array( $v ) ? $v : array( 'code' => 200, 'body' => (string) $v );
		}
	}
	return new VHCP_Test_WP_Error( 'không có mạng trong bài kiểm: ' . $url );
}
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function checked( $a, $b = true, $echo = true ) { return ( (string) $a === (string) $b ) ? " checked='checked'" : ''; }
/** WP_Error thật của WordPress — plugin hợp đồng dùng lớp này để báo "thiếu bảng người dùng". */
class WP_Error {
	private $code; private $msg;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->msg = $msg; }
	public function get_error_message() { return $this->msg; }
	public function get_error_code() { return $this->code; }
}
function wp_remote_retrieve_response_code( $r ) { return isset( $r['code'] ) ? (int) $r['code'] : 200; }
/** Header của phản hồi giả — khoá viết thường, giống WordPress thật. */
function wp_remote_retrieve_header( $r, $ten ) {
	$ten = strtolower( (string) $ten );
	if ( ! isset( $r['headers'] ) || ! is_array( $r['headers'] ) ) { return ''; }
	foreach ( $r['headers'] as $k => $v ) {
		if ( strtolower( (string) $k ) === $ten ) { return $v; }
	}
	return '';
}
function wp_remote_retrieve_body( $r ) { return isset( $r['body'] ) ? (string) $r['body'] : ''; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function rest_url( $p = '' ) { return 'http://example.test/wp-json/' . ltrim( $p, '/' ); }
function home_url( $p = '/' ) { return 'http://example.test' . $p; }
function add_query_arg() { return ''; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://example.test/wp-content/plugins/vhcp-chi-phi/'; }

class WP_REST_Request {
	private $p;
	private $h;
	public function __construct( $p = array(), $h = array() ) { $this->p = $p; $this->h = $h; }
	public function get_param( $k ) { return array_key_exists( $k, $this->p ) ? $this->p[ $k ] : null; }
	public function get_header( $k ) { return array_key_exists( $k, $this->h ) ? $this->h[ $k ] : ''; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $d = null, $s = 200 ) { $this->data = $d; $this->status = $s; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

/** $wpdb tối giản trên SQLite. */
class VHCP_Test_WPDB {

	public $prefix = 'wp_';
	public $last_error = '';
	public $q_count = 0;      // đếm số lệnh xuống DB — dùng để kiểm "không đọc lặp"
	private $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	public function get_charset_collate() { return ''; }

	public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }

	public function exec_raw( $sql ) { return $this->pdo->exec( $sql ); }

	private function tr( $sql ) {
		// SQLite không có SHOW TABLES — plugin dùng câu đó để hỏi "bảng của plugin kia có không".
		if ( preg_match( "/^\s*SHOW\s+TABLES\s+LIKE\s+'([^']*)'/i", $sql, $m ) ) {
			return "SELECT name FROM sqlite_master WHERE type='table' AND name='" . $m[1] . "'";
		}
		$sql = str_ireplace( 'UTC_TIMESTAMP()', "datetime('now')", $sql );
		if ( stripos( $sql, 'ON DUPLICATE KEY UPDATE' ) !== false ) {
			$sql = preg_replace( '/\s+ON DUPLICATE KEY UPDATE.*$/is', '', $sql );
			$sql = preg_replace( '/^\s*INSERT\s+INTO/i', 'INSERT OR REPLACE INTO', $sql );
		}
		return $sql;
	}

	public function prepare( $sql ) {
		$args = func_get_args();
		array_shift( $args );
		if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $args ) {
			$v = array_key_exists( $i, $args ) ? $args[ $i ] : null;
			$i++;
			if ( $m[0] === '%d' ) { return (string) (int) $v; }
			if ( $m[0] === '%f' ) { return (string) (float) $v; }
			return $this->quote( $v );
		}, $sql );
	}

	public function quote( $v ) {
		if ( $v === null ) { return 'NULL'; }
		return $this->pdo->quote( (string) $v );
	}

	public function get_results( $sql, $mode = null ) {
		$this->q_count++;
		$st = $this->pdo->query( $this->tr( $sql ) );
		return $st ? $st->fetchAll( PDO::FETCH_ASSOC ) : array();
	}

	public function get_row( $sql, $mode = null ) {
		$r = $this->get_results( $sql );   // get_results đã đếm
		return count( $r ) ? $r[0] : null;
	}

	public function get_col( $sql ) {
		$out = array();
		foreach ( $this->get_results( $sql ) as $r ) { $v = array_values( $r ); $out[] = $v[0]; }
		return $out;
	}

	public function get_var( $sql ) {
		$r = $this->get_row( $sql );
		if ( ! $r ) { return null; }
		$v = array_values( $r );
		return $v[0];
	}

	public function query( $sql ) { $this->q_count++; return $this->pdo->exec( $this->tr( $sql ) ); }

	public function insert( $table, $data ) {
		$cols = array_keys( $data );
		$vals = array();
		foreach ( $data as $v ) { $vals[] = ( $v === null ) ? 'NULL' : $this->quote( $v ); }
		$sql = 'INSERT INTO ' . $table . ' (' . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ')';
		$this->q_count++;
		return $this->pdo->exec( $sql );
	}

	public function update( $table, $data, $where ) {
		$set = array();
		foreach ( $data as $k => $v ) { $set[] = $k . '=' . ( $v === null ? 'NULL' : $this->quote( $v ) ); }
		$w = array();
		foreach ( $where as $k => $v ) { $w[] = $k . '=' . ( $v === null ? 'NULL' : $this->quote( $v ) ); }
		$this->q_count++;
		return $this->pdo->exec( 'UPDATE ' . $table . ' SET ' . implode( ',', $set ) . ' WHERE ' . implode( ' AND ', $w ) );
	}

	public function delete( $table, $where ) {
		$w = array();
		foreach ( $where as $k => $v ) { $w[] = $k . '=' . ( $v === null ? 'NULL' : $this->quote( $v ) ); }
		$this->q_count++;
		return $this->pdo->exec( 'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $w ) );
	}
}

$GLOBALS['wpdb'] = new VHCP_Test_WPDB();

/** Bảng SQLite tương ứng schema MySQL (khóa chính đổi sang stt để có AUTOINCREMENT). */
function vhcp_test_create_tables() {
	global $wpdb;
	$p = 'wp_vhcp_';
	$q = array(
		"CREATE TABLE {$p}don (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma_don TEXT UNIQUE, ky TEXT DEFAULT '', nguoi_lap TEXT DEFAULT '', ngay_tao TEXT, trang_thai TEXT DEFAULT 'Nháp', ghi_chu TEXT DEFAULT '', nguoi_duyet TEXT DEFAULT '', ngay_duyet TEXT, nguoi_qt TEXT DEFAULT '', ngay_qt TEXT, chenh_lech_qt REAL DEFAULT 0, xu_ly TEXT DEFAULT '', so_tien_thuc_mua REAL, hinh_thuc_tt TEXT DEFAULT '', hoa_don_qt TEXT DEFAULT '', ngay_xuat_cn TEXT, nguoi_qt_ncc TEXT DEFAULT '', ngay_qt_ncc TEXT, ngay_xuat_ncc TEXT, tam_ung_duyet REAL, nguoi_cap TEXT DEFAULT '', ngay_cap TEXT, ht_cap TEXT DEFAULT '', anh_cap TEXT DEFAULT '', tat_toan TEXT DEFAULT '', ngay_tat_toan TEXT, du_phong REAL, bu_tru REAL)",
		"CREATE TABLE {$p}tamung (id INTEGER PRIMARY KEY AUTOINCREMENT, ma_don TEXT, coso TEXT DEFAULT '', so REAL DEFAULT 0, UNIQUE(ma_don,coso))",
		"CREATE TABLE {$p}chiphi (stt INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT UNIQUE, ma_don TEXT, coso TEXT DEFAULT '', ngay TEXT, phan_loai_tt TEXT DEFAULT '', doi_tuong TEXT DEFAULT '', nhom TEXT DEFAULT '', noi_dung TEXT DEFAULT '', dvt TEXT DEFAULT '', so_luong REAL, don_gia REAL, thanh_tien REAL DEFAULT 0, ghi_chu TEXT DEFAULT '', anh TEXT DEFAULT '', tao_luc TEXT, thue_suat REAL, tien_thue REAL, thuc_mua REAL, cn_xu_ly INTEGER DEFAULT 1, phat_sinh INTEGER DEFAULT 0, tk_no TEXT DEFAULT '', tk_co TEXT DEFAULT '')",
		"CREATE TABLE {$p}so_chi (stt INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT UNIQUE, ngay TEXT, ky TEXT DEFAULT '', coso TEXT DEFAULT '', loai TEXT DEFAULT '', tk_no TEXT DEFAULT '', tk_co TEXT DEFAULT '', ma_dt TEXT DEFAULT '', doi_tuong TEXT DEFAULT '', noi_dung TEXT DEFAULT '', dvt TEXT DEFAULT '', so_luong REAL, don_gia REAL, so_tien REAL DEFAULT 0, hinh_thuc TEXT DEFAULT '', vat TEXT DEFAULT '', thue_suat REAL, tien_thue REAL, ghi_chu TEXT DEFAULT '', anh TEXT DEFAULT '', ma_du_an TEXT DEFAULT '', hang_muc TEXT DEFAULT '', du_toan REAL, ho_so TEXT DEFAULT '', nguoi_nhap TEXT DEFAULT '', tao_luc TEXT, ngay_xuat TEXT)",
		"CREATE TABLE {$p}da_index (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma_da TEXT UNIQUE, ten TEXT DEFAULT '', loai TEXT DEFAULT '', trang_thai TEXT DEFAULT 'Đang làm', ngay_tao TEXT, nguoi_tao TEXT DEFAULT '')",
		"CREATE TABLE {$p}da_line (id INTEGER PRIMARY KEY AUTOINCREMENT, ma_da TEXT, row_no INTEGER DEFAULT 5, noi_dung TEXT DEFAULT '', du_toan REAL DEFAULT 0, thuc_te REAL DEFAULT 0, so_luong REAL DEFAULT 0, don_gia REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, vat TEXT DEFAULT '', anh TEXT DEFAULT '', gian TEXT DEFAULT '', note TEXT DEFAULT '', cap_cha TEXT DEFAULT '', hinh_thuc TEXT DEFAULT '', ho_so TEXT DEFAULT '', loai_cp TEXT DEFAULT '', tk_no TEXT DEFAULT '', tk_co TEXT DEFAULT '', ma_dt TEXT DEFAULT '', UNIQUE(ma_da,row_no))",
		"CREATE TABLE {$p}mk_don (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma TEXT UNIQUE, coso TEXT DEFAULT '', ten TEXT DEFAULT '', ky TEXT DEFAULT '', kenh TEXT DEFAULT '', trang_thai TEXT DEFAULT 'Đang chạy', ngay_tao TEXT DEFAULT '', nguoi_tao TEXT DEFAULT '')",
		"CREATE TABLE {$p}mk_line (stt INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT UNIQUE, ma_don TEXT, kenh TEXT DEFAULT '', noi_dung TEXT DEFAULT '', du_toan REAL DEFAULT 0, thuc_te REAL DEFAULT 0, hinh_thuc TEXT DEFAULT '', vat TEXT DEFAULT '', ket_qua REAL DEFAULT 0, ngay TEXT DEFAULT '', note TEXT DEFAULT '', ho_so TEXT DEFAULT '', loai_cp TEXT DEFAULT '', tk_no TEXT DEFAULT '', tk_co TEXT DEFAULT '', ma_dt TEXT DEFAULT '')",
		"CREATE TABLE {$p}bp_index (stt INTEGER PRIMARY KEY AUTOINCREMENT, ma TEXT UNIQUE, loai TEXT DEFAULT '', ten TEXT DEFAULT '', nguoi TEXT DEFAULT '', dia_diem TEXT DEFAULT '', ky TEXT DEFAULT '', trang_thai TEXT DEFAULT 'Đang xử lý', ngay_tao TEXT DEFAULT '', nguoi_tao TEXT DEFAULT '')",
		"CREATE TABLE {$p}bp_line (id INTEGER PRIMARY KEY AUTOINCREMENT, ma TEXT, row_no INTEGER DEFAULT 5, noi_dung TEXT DEFAULT '', so_luong REAL DEFAULT 0, don_gia REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, du_toan REAL DEFAULT 0, thuc_te REAL DEFAULT 0, hinh_thuc TEXT DEFAULT '', vat TEXT DEFAULT '', ngay TEXT DEFAULT '', note TEXT DEFAULT '', ho_so TEXT DEFAULT '', loai_cp TEXT DEFAULT '', tk_no TEXT DEFAULT '', tk_co TEXT DEFAULT '', ma_dt TEXT DEFAULT '', UNIQUE(ma,row_no))",
		// Bảng phiên của plugin Thư viện hợp đồng — tiền tố vhd_, KHÔNG phải vhcp_
		"CREATE TABLE wp_vhd_session (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT UNIQUE, ten TEXT DEFAULT '', vai_tro TEXT DEFAULT '', coso TEXT DEFAULT '', het_han TEXT)",
		"CREATE TABLE wp_vhcc_session (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT UNIQUE, ten TEXT DEFAULT '', vai_tro TEXT DEFAULT '', coso TEXT DEFAULT '', het_han TEXT)",
		"CREATE TABLE {$p}hopdong (stt INTEGER PRIMARY KEY AUTOINCREMENT, id TEXT UNIQUE, so_hd TEXT DEFAULT '', ten TEXT DEFAULT '', doi_tac TEXT DEFAULT '', coso TEXT DEFAULT '', loai_hd TEXT DEFAULT '', ngay_ky TEXT DEFAULT '', ngay_het TEXT DEFAULT '', gia_tri REAL, trang_thai TEXT DEFAULT 'Còn hiệu lực', nguoi_pt TEXT DEFAULT '', ghi_chu TEXT DEFAULT '', files TEXT DEFAULT '', nguoi_tao TEXT DEFAULT '', tao_luc TEXT DEFAULT '')",
		"CREATE TABLE {$p}cfg (id INTEGER PRIMARY KEY AUTOINCREMENT, bang TEXT, stt INTEGER DEFAULT 0, cols TEXT)",
		"CREATE TABLE {$p}meta (k TEXT PRIMARY KEY, v TEXT)",
		"CREATE TABLE {$p}log (id INTEGER PRIMARY KEY AUTOINCREMENT, tg TEXT, nguoi TEXT DEFAULT '', vai_tro TEXT DEFAULT '', hanh_dong TEXT DEFAULT '', doi_tuong TEXT DEFAULT '', chi_tiet TEXT DEFAULT '')",
		"CREATE TABLE {$p}session (token TEXT PRIMARY KEY, ten TEXT DEFAULT '', vai_tro TEXT DEFAULT '', coso TEXT DEFAULT '', bo_phan TEXT DEFAULT '', het_han TEXT)",
	);
	foreach ( $q as $s ) { $wpdb->exec_raw( $s ); }
}

/** Nạp các lớp của plugin (không nạp file bootstrap để tránh hook WordPress). */
function vhcp_test_boot( $dir ) {
	define( 'VHCP_VERSION', 'test' );
	define( 'VHCP_DIR', $dir . '/' );
	define( 'VHCP_URL', 'http://example.test/plugin/' );
	foreach ( array( 'util', 'db', 'meta', 'cfg', 'auth', 'log', 'don', 'sochi', 'duan', 'mk', 'bp', 'report', 'misa', 'trama', 'upload', 'nap', 'sheet', 'import' ) as $c ) {
		require_once $dir . '/includes/class-vhcp-' . $c . '.php';
	}
	vhcp_test_create_tables();
	VHCP_Cfg::seed();
}

/**
 * Nạp plugin THƯ VIỆN HỢP ĐỒNG (vhcp-hop-dong) — plugin riêng, chỉ nối sang app Apps Script.
 * Gọi SAU vhcp_test_boot() vì nó đọc bảng người dùng của plugin Vận hành chi phí.
 */
function vhcc_test_boot( $dir ) {
	define( 'VHCC_VERSION', 'test' );
	define( 'VHCC_DIR', $dir . '/' );
	define( 'VHCC_URL', 'http://example.test/plugin-cham-cong/' );
	/* ĐỌC danh sách lớp từ CHÍNH tệp plugin, không gõ tay lại.
	   Bản đầu gõ tay 17 tên lớp ở đây. Thêm `class-vhcc-keo.php` vào plugin là bài kiểm không
	   nạp nó và mọi màn dùng tới nó chết với "Class VHCC_Keo not found" — một lỗi của BÀI KIỂM
	   trông y như lỗi của plugin. Thứ tự require trong tệp plugin cũng chính là thứ tự phụ thuộc
	   đúng, nên đọc lại là được cả hai thứ. */
	$chinh = file_get_contents( $dir . '/vhcp-cham-cong.php' );
	if ( ! preg_match_all( "#require_once VHCC_DIR \. '(includes/class-vhcc-[a-z-]+\.php)';#", $chinh, $m ) ) {
		throw new RuntimeException( 'Không đọc được danh sách lớp trong vhcp-cham-cong.php' );
	}
	foreach ( $m[1] as $duong ) { require_once $dir . '/' . $duong; }
}

function vhd_test_boot( $dir ) {
	define( 'VHD_VERSION', 'test' );
	define( 'VHD_DIR', $dir . '/' );
	define( 'VHD_URL', 'http://example.test/plugin-hop-dong/' );
	foreach ( array( 'db', 'auth', 'cau-noi', 'api', 'trang' ) as $c ) {
		require_once $dir . '/includes/class-vhd-' . $c . '.php';
	}
}
