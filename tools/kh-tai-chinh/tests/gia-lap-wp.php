<?php
/**
 * Bộ giả lập WordPress tối thiểu, đủ để CHẠY THẬT bốn màn hình của plugin.
 *
 * Sandbox không có MySQL và không tải được WordPress, nên thay vì chỉ `php -l`
 * rồi đoán, ta dựng đúng những hàm WordPress mà plugin gọi tới, cắm $wpdb vào
 * SQLite, rồi dựng cả bốn trang ra HTML. Bắt được: hàm gọi sai tên, biến chưa
 * khai báo, HTML vỡ, và quan trọng nhất là con số cuối cùng có đúng không.
 *
 * KHÔNG THAY ĐƯỢC bản cài thật: SQLite không phải MySQL, dbDelta ở đây bị bỏ
 * qua và bảng được tạo tay. Lỗi riêng của MySQL sẽ lọt qua bộ này.
 */

// ------------------------------------------------------------ hằng số WP
define( 'ABSPATH', __DIR__ . '/wp/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['khtc_option']    = array();
$GLOBALS['khtc_user_meta'] = array();
$GLOBALS['khtc_hook']      = array();

// ------------------------------------------------------------- hook giả
function add_action( $t, $f, $p = 10, $a = 1 ) { $GLOBALS['khtc_hook'][ $t ][] = $f; }
function add_filter( $t, $f, $p = 10, $a = 1 ) { $GLOBALS['khtc_hook'][ $t ][] = $f; }
function do_action( $t ) {}
function apply_filters( $t, $v ) { return $v; }
function register_activation_hook( $f, $cb ) {}
function register_deactivation_hook( $f, $cb ) {}
function add_rewrite_rule( $a, $b, $c = 'bottom' ) {}
function flush_rewrite_rules( $hard = true ) {}
function add_menu_page() {}
function add_submenu_page() {}
function wp_enqueue_style() {}

// ------------------------------------------------------- tuỳ chọn, người dùng
function delete_option( $k ) { unset( $GLOBALS['khtc_option'][ $k ] ); return true; }
function get_option( $k, $m = false ) { return array_key_exists( $k, $GLOBALS['khtc_option'] ) ? $GLOBALS['khtc_option'][ $k ] : $m; }
function update_option( $k, $v ) { $GLOBALS['khtc_option'][ $k ] = $v; return true; }
function get_current_user_id() { return 1; }
function is_user_logged_in() { return true; }
// Quyền giả lập: mặc định cho hết, nhưng bài kiểm nào cần thử phía CHẶN thì
// đặt $GLOBALS['khtc_cam'] để tắt đúng quyền đó.
function current_user_can( $c ) { return empty( $GLOBALS['khtc_cam'][ $c ] ); }

// ---------------------------------------------------- người dùng giả lập
$GLOBALS['khtc_users'] = array(
	1 => (object) array( 'ID' => 1, 'user_login' => 'admin', 'user_email' => 'admin@vi.du', 'display_name' => 'Quản trị', 'roles' => array( 'administrator' ), 'caps' => array( 'manage_options' => true, 'edit_pages' => true ) ),
);
$GLOBALS['khtc_roles'] = array(
	'administrator' => array( 'ten' => 'Quản trị viên', 'caps' => array( 'manage_options' => true, 'edit_pages' => true ) ),
	'editor'        => array( 'ten' => 'Biên tập viên', 'caps' => array( 'edit_pages' => true ) ),
	'subscriber'    => array( 'ten' => 'Người đăng ký', 'caps' => array( 'read' => true ) ),
);
class KHTC_VaiTro_Gia {
	public $name;
	public function __construct( $n ) { $this->name = $n; }
	public function add_cap( $c, $co = true ) { $GLOBALS['khtc_roles'][ $this->name ]['caps'][ $c ] = $co; }
	public function remove_cap( $c ) { unset( $GLOBALS['khtc_roles'][ $this->name ]['caps'][ $c ] ); }
}
function get_role( $n ) { return isset( $GLOBALS['khtc_roles'][ $n ] ) ? new KHTC_VaiTro_Gia( $n ) : null; }
function add_role( $n, $ten, $caps = array() ) { $GLOBALS['khtc_roles'][ $n ] = array( 'ten' => $ten, 'caps' => $caps ); return new KHTC_VaiTro_Gia( $n ); }
function wp_roles() {
	return new class() {
		public function get_names() {
			$r = array();
			foreach ( $GLOBALS['khtc_roles'] as $k => $v ) { $r[ $k ] = $v['ten']; }
			return $r;
		}
	};
}
class KHTC_User_Gia {
	public $ID; public $user_login; public $user_email; public $display_name; public $roles; public $caps; public $mat_khau = '';
	public function __construct( $d ) { foreach ( $d as $k => $v ) { $this->$k = $v; } }
	public function add_cap( $c, $co = true ) { $GLOBALS['khtc_users'][ $this->ID ]->caps[ $c ] = $co; }
	public function remove_cap( $c ) { unset( $GLOBALS['khtc_users'][ $this->ID ]->caps[ $c ] ); }
	// WP_User thật cập nhật cả $this->roles, không chỉ kho dữ liệu. Thiếu chỗ
	// này thì mã gọi remove_role() rồi đọc lại $u->roles ngay sau đó sẽ thấy
	// giá trị cũ — và bài kiểm bỏ lọt đúng loại lỗi đó.
	public function add_role( $r ) {
		$GLOBALS['khtc_users'][ $this->ID ]->roles[] = $r;
		$this->roles = $GLOBALS['khtc_users'][ $this->ID ]->roles;
	}
	public function remove_role( $r ) {
		$u = $GLOBALS['khtc_users'][ $this->ID ];
		$u->roles    = array_values( array_diff( (array) $u->roles, array( $r ) ) );
		$this->roles = $u->roles;
	}
}
function khtc_gia_user( $u ) { return $u instanceof KHTC_User_Gia ? $u : new KHTC_User_Gia( (array) $u ); }
function get_users( $a = array() ) {
	$r = array_map( 'khtc_gia_user', array_values( $GLOBALS['khtc_users'] ) );
	usort( $r, function ( $x, $y ) { return strcmp( $x->user_login, $y->user_login ); } );
	return $r;
}
function get_user_by( $loai, $v ) {
	foreach ( $GLOBALS['khtc_users'] as $u ) {
		if ( 'id' === $loai && (int) $u->ID === (int) $v ) { return khtc_gia_user( $u ); }
		if ( 'login' === $loai && $u->user_login === $v ) { return khtc_gia_user( $u ); }
		if ( 'email' === $loai && '' !== (string) $v && $u->user_email === $v ) { return khtc_gia_user( $u ); }
	}
	return false;
}
function user_can( $u, $c ) {
	$u = is_object( $u ) ? $GLOBALS['khtc_users'][ $u->ID ] : $GLOBALS['khtc_users'][ (int) $u ];
	if ( array_key_exists( $c, (array) $u->caps ) ) { return (bool) $u->caps[ $c ]; }
	foreach ( (array) $u->roles as $r ) {
		if ( ! empty( $GLOBALS['khtc_roles'][ $r ]['caps'][ $c ] ) ) { return true; }
	}
	// Bộ lọc bù quyền của plugin cũng phải chạy ở đây, nếu không phép kiểm
	// không đụng tới đúng cái đang cần kiểm.
	if ( KHTC_NguoiDung::QUYEN === $c ) { return user_can( $u, KHTC_NguoiDung::QUYEN_CU ); }
	return false;
}
function wp_insert_user( $d ) {
	foreach ( $GLOBALS['khtc_users'] as $u ) {
		if ( $u->user_login === $d['user_login'] ) { return new WP_Error( 'ton_tai', 'Tên đăng nhập đã có.' ); }
	}
	$id = max( array_keys( $GLOBALS['khtc_users'] ) ) + 1;
	$GLOBALS['khtc_users'][ $id ] = (object) array(
		'ID' => $id, 'user_login' => $d['user_login'], 'user_email' => $d['user_email'] ?? '',
		'display_name' => $d['display_name'] ?? $d['user_login'],
		'roles' => array( $d['role'] ?? 'subscriber' ), 'caps' => array(),
		'mat_khau' => $d['user_pass'] ?? '',
	);
	return $id;
}
function wp_set_password( $mk, $id ) { $GLOBALS['khtc_users'][ (int) $id ]->mat_khau = $mk; }
function sanitize_user( $s, $chat = false ) { return strtolower( preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $s ) ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function wp_generate_password( $n = 12, $dac_biet = true, $manh = false ) {
	$bang = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' . ( $dac_biet ? '!@#$%^&*()' : '' );
	$r = '';
	for ( $i = 0; $i < $n; $i++ ) { $r .= $bang[ random_int( 0, strlen( $bang ) - 1 ) ]; }
	return $r;
}
function wp_login_url( $x = '' ) { return 'https://vi.du/wp-login.php'; }
function wp_get_current_user() { return (object) array( 'display_name' => 'Kế toán', 'user_login' => 'ketoan' ); }
function get_user_meta( $u, $k, $single = false ) { return $GLOBALS['khtc_user_meta'][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['khtc_user_meta'][ $k ] = $v; return true; }
function auth_redirect() {}
function wp_safe_redirect( $u ) {}
function status_header( $c ) {}
function nocache_headers() {}
function language_attributes() { echo 'lang="vi"'; }
function bloginfo( $x ) { echo 'UTF-8'; }
function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o | JSON_UNESCAPED_UNICODE ); }
function wp_logout_url( $r = '' ) { return 'https://vi.du/wp-login.php?action=logout'; }

// ---------------------------------------------------------------- nonce
function wp_nonce_field( $a = '', $n = '_wpnonce', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function wp_verify_nonce( $n, $a = -1 ) { return 1; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { return 1; }
function wp_nonce_url( $u, $a = -1, $n = '_wpnonce' ) { return $u . ( strpos( $u, '?' ) !== false ? '&' : '?' ) . '_wpnonce=test'; }

// ------------------------------------------------------------ escape, lọc
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return trim( (string) $s ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
// esc_js: chuỗi nằm TRONG một chuỗi JavaScript, mà chuỗi JS đó lại nằm trong
// một thuộc tính HTML — phải thoát cả hai tầng.
function esc_js( $s ) {
	$s = htmlspecialchars( (string) $s, ENT_COMPAT, 'UTF-8' );
	$s = str_replace( array( "\r", "\n" ), array( '', '\\n' ), addslashes( $s ) );
	return str_replace( "'", "&#039;", $s );
}
function wp_max_upload_size() { return 8 * 1024 * 1024; }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function size_format( $n, $le = 0 ) {
	$n = (int) $n;
	foreach ( array( 'GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024 ) as $d => $m ) {
		if ( $n >= $m ) { return number_format( $n / $m, $le ) . ' ' . $d; }
	}
	return $n . ' B';
}
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', basename( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }
function selected( $a, $b, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }

// ------------------------------------------------------------- ngày, URL
function current_time( $t ) { return 'mysql' === $t ? date( 'Y-m-d H:i:s' ) : date( $t ); }
function mysql2date( $f, $d, $tz = true ) { $t = strtotime( (string) $d ); return $t ? date( $f, $t ) : ''; }
function admin_url( $p = '' ) { return 'https://vi.du/wp-admin/' . ltrim( $p, '/' ); }
function home_url( $p = '' ) { return 'https://vi.du' . ( '' === $p ? '' : '/' . ltrim( $p, '/' ) ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://vi.du/wp-content/plugins/kh-tai-chinh/'; }
function add_query_arg( $args, $url ) {
	$p = parse_url( $url );
	parse_str( $p['query'] ?? '', $q );
	$q = array_merge( $q, $args );
	return ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? 'vi.du' ) . ( $p['path'] ?? '/' ) . ( $q ? '?' . http_build_query( $q ) : '' );
}
function get_query_var( $v, $d = '' ) { return $GLOBALS['khtc_qv'][ $v ] ?? $d; }

// ------------------------------------------------------------- WP_Error
class WP_Error {
	private $c;
	private $m;
	public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }

// ------------------------------------------------------------- $wpdb giả
class KHTC_Wpdb_Gia {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = '';
	private $pdo;
	public $so_cau = 0;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	public function get_charset_collate() { return ''; }

	/**
	 * MySQL DATE_SUB/DATE_ADD không có trong SQLite. Dịch sang date(x, '-n day').
	 * Chỉ dùng cho bộ giả lập — bản thật chạy thẳng MySQL.
	 */
	private function dich( $sql ) {
		$sql = preg_replace( "/DATE_SUB\\(\\s*('[^']*')\\s*,\\s*INTERVAL\\s+(\\d+)\\s+DAY\\s*\\)/i", "date($1, '-$2 day')", $sql );
		$sql = preg_replace( "/DATE_ADD\\(\\s*('[^']*')\\s*,\\s*INTERVAL\\s+(\\d+)\\s+DAY\\s*\\)/i", "date($1, '+$2 day')", $sql );
		// SQLite gọi hàm này là min() hai đối số, MySQL gọi là LEAST().
		$sql = preg_replace( '/\bLEAST\s*\(/i', 'min(', $sql );
		return $sql;
	}

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		$sql = str_replace( array( '%s', '%d', '%f' ), array( '%s', '%d', '%f' ), $sql );
		$i   = 0;
		return preg_replace_callback(
			'/%[sdf]/',
			function ( $m ) use ( &$i, $args ) {
				$v = $args[ $i++ ] ?? '';
				if ( '%d' === $m[0] ) { return (string) (int) $v; }
				if ( '%f' === $m[0] ) { return (string) (float) $v; }
				return "'" . str_replace( "'", "''", (string) $v ) . "'";
			},
			$sql
		);
	}

	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }

	public function query( $sql ) {
		$this->so_cau++;
		return $this->pdo->exec( $this->dich( $sql ) );
	}

	public function get_results( $sql, $kieu = null ) {
		$this->so_cau++;
		$st = $this->pdo->query( $this->dich( $sql ) );
		return ( 'ARRAY_A' === $kieu ) ? $st->fetchAll( PDO::FETCH_ASSOC ) : $st->fetchAll( PDO::FETCH_OBJ );
	}

	public function get_var( $sql ) {
		$this->so_cau++;
		$r = $this->pdo->query( $this->dich( $sql ) )->fetch( PDO::FETCH_NUM );
		return $r ? $r[0] : null;
	}

	public function get_row( $sql ) {
		$r = $this->get_results( $sql );
		return $r ? $r[0] : null;
	}

	public function get_col( $sql ) {
		$this->so_cau++;
		return $this->pdo->query( $this->dich( $sql ) )->fetchAll( PDO::FETCH_COLUMN );
	}

	public function insert( $bang, $data, $fmt = null ) {
		$cot = implode( ',', array_keys( $data ) );
		$gt  = implode( ',', array_map( array( $this, 'q' ), array_values( $data ) ) );
		// $wpdb->insert TRẢ VỀ FALSE khi cơ sở dữ liệu từ chối (ví dụ đụng
		// UNIQUE), không ném ngoại lệ. Bộ giả lập phải cư xử y hệt, nếu không
		// mã xử lý trường hợp đó không bao giờ được chạy qua khi kiểm.
		try {
			$this->query( "INSERT INTO $bang ($cot) VALUES ($gt)" );
		} catch ( PDOException $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}

	public function update( $bang, $data, $where, $f1 = null, $f2 = null ) {
		$dat = array();
		foreach ( $data as $k => $v ) { $dat[] = "$k = " . $this->q( $v ); }
		$dk = array();
		foreach ( $where as $k => $v ) { $dk[] = "$k = " . $this->q( $v ); }
		return $this->query( 'UPDATE ' . $bang . ' SET ' . implode( ',', $dat ) . ' WHERE ' . implode( ' AND ', $dk ) );
	}

	public function delete( $bang, $where, $f = null ) {
		$dk = array();
		foreach ( $where as $k => $v ) { $dk[] = "$k = " . $this->q( $v ); }
		return $this->query( "DELETE FROM $bang WHERE " . implode( ' AND ', $dk ) );
	}

	private function q( $v ) {
		if ( null === $v ) { return 'NULL'; }
		if ( is_int( $v ) ) { return (string) $v; }
		return "'" . str_replace( "'", "''", (string) $v ) . "'";
	}

	/** Bảng cho SQLite — dbDelta chỉ hiểu cú pháp MySQL nên không dùng lại được. */
	public function tao_bang_sqlite() {
		$this->query( 'CREATE TABLE wp_khtc_ngan_hang (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ten TEXT, so_tk TEXT, so_du_dau INTEGER, ngay_dau TEXT, ghi_chu TEXT, tao_luc TEXT)' );
		$this->query( "CREATE TABLE wp_khtc_giao_dich (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngan_hang_id INTEGER, ngay TEXT, dien_giai TEXT, so_tien INTEGER, loai TEXT, ma_gd TEXT, ma_cua_hang TEXT DEFAULT '', hd_ra_id INTEGER DEFAULT 0, tao_luc TEXT, tao_boi TEXT)" );
		$this->query( 'CREATE TABLE wp_khtc_doi_soat (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ten TEXT, kenh TEXT, ngan_hang_id INTEGER, tu TEXT, den TEXT, chay_luc TEXT, tao_luc TEXT, tao_boi TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_chi_phi (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngay TEXT, bo_phan TEXT, khoan_muc TEXT, nha_cung_cap TEXT, dien_giai TEXT, so_tien INTEGER, so_ct TEXT, han_tt TEXT, hinh_thuc TEXT, ngan_hang_id INTEGER DEFAULT 0, giao_dich_id INTEGER DEFAULT 0, kieu_khop TEXT DEFAULT \'\', tao_luc TEXT, tao_boi TEXT)' );
		$this->query( "CREATE TABLE wp_khtc_hd_ra (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngay TEXT, han_tt TEXT, so_hd TEXT, khach TEXT, mst TEXT, dia_chi_kh TEXT, email TEXT, noi_dung TEXT, so_luong TEXT, dvt TEXT, thanh_tien INTEGER, chua_vat INTEGER, thue_suat TEXT, vat INTEGER, co_vat INTEGER, khu_vuc TEXT, dich_vu TEXT, so_hop_dong TEXT, ma_diem TEXT, ma_misa TEXT, ghi_chu TEXT, dia_chi TEXT, tao_luc TEXT, tao_boi TEXT, UNIQUE (cty, so_hd))" );
		$this->query( 'CREATE TABLE wp_khtc_ho_so (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, loai TEXT, so_ct TEXT, ngay TEXT, doi_tac TEXT, so_tien INTEGER, ten_file TEXT, link_file TEXT, gan_bang TEXT, gan_id INTEGER DEFAULT 0, da_hach_toan INTEGER DEFAULT 0, ghi_chu TEXT, tao_luc TEXT, tao_boi TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_hop_dong (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, loai TEXT, doi_tac TEXT, mst TEXT, dai_dien TEXT, chuc_vu TEXT, so_hd TEXT, ngay_ky TEXT, ngay_bat_dau TEXT, ngay_het_han TEXT, gia_tri INTEGER, noi_dung TEXT, gian TEXT, ma_diem TEXT, ma_misa TEXT, khu_vuc TEXT, hinh_thuc TEXT, trang_thai TEXT, loai_chia_se TEXT, phan_tram REAL DEFAULT 0, mien INTEGER DEFAULT 0, link_chua_dau TEXT, link_du_dau TEXT, ghi_chu TEXT, tao_luc TEXT, tao_boi TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_hd_vao (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ngay TEXT, so_hd TEXT, nha_cung_cap TEXT, mst TEXT, dia_chi TEXT, noi_dung TEXT, chua_vat INTEGER, thue_suat TEXT, vat INTEGER, co_vat INTEGER, hinh_thuc TEXT, khau_tru INTEGER DEFAULT 1, ly_do TEXT, chi_phi_id INTEGER DEFAULT 0, ghi_chu TEXT, tao_luc TEXT, tao_boi TEXT, UNIQUE (cty, so_hd, mst))' );
		$this->query( 'CREATE TABLE wp_khtc_thanh_toan (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, bang TEXT, chung_tu_id INTEGER, giao_dich_id INTEGER DEFAULT 0, ngay TEXT, so_tien INTEGER, ghi_chu TEXT, tu_dong INTEGER DEFAULT 0, tao_luc TEXT, tao_boi TEXT)' );
		$this->query( "CREATE TABLE wp_khtc_don_app (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ma_don TEXT, ngay TEXT, co_so TEXT DEFAULT '', tien INTEGER DEFAULT 0, tt_don TEXT DEFAULT '', tt_tt TEXT DEFAULT '', tao_luc TEXT, UNIQUE (cty, ma_don))" );
		$this->query( "CREATE TABLE wp_khtc_diem (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, ma_cua_hang TEXT, ma_diem_ban TEXT DEFAULT '', ten_gian TEXT DEFAULT '', ten_diem TEXT DEFAULT '', ma_misa TEXT DEFAULT '', khu_vuc TEXT DEFAULT '', dich_vu TEXT DEFAULT '', so_tk TEXT DEFAULT '', bo_qua INTEGER DEFAULT 0, ghi_chu TEXT, tao_luc TEXT, UNIQUE (cty, ma_cua_hang))" );
		$this->query( 'CREATE TABLE wp_khtc_nhat_ky (id INTEGER PRIMARY KEY AUTOINCREMENT, cty TEXT, luc TEXT, ai TEXT, viec TEXT, bang TEXT, ban_ghi_id INTEGER DEFAULT 0, tom_tat TEXT, du_lieu TEXT)' );
		$this->query( 'CREATE TABLE wp_khtc_ds_dong (id INTEGER PRIMARY KEY AUTOINCREMENT, dot_id INTEGER, ngay TEXT, ma_gd TEXT, so_tien INTEGER, phi INTEGER, dien_giai TEXT, ma_cua_hang TEXT DEFAULT \'\', khop_gd_id INTEGER DEFAULT 0, kieu_khop TEXT DEFAULT \'\', hd_ra_id INTEGER DEFAULT 0)' );
	}
}

$GLOBALS['wpdb'] = new KHTC_Wpdb_Gia();
$GLOBALS['wpdb']->tao_bang_sqlite();

// ------------------------------------------------------------ nạp plugin
// KHTC_GOC cho phép trỏ vào một bản cài đã giải nén, để thử đúng cái sắp gửi
// đi chứ không phải thử cây mã nguồn.
$goc = getenv( 'KHTC_GOC' ) ? rtrim( getenv( 'KHTC_GOC' ), '/' ) . '/' : __DIR__ . '/../wordpress/kh-tai-chinh/';
// Đọc số bản từ chính plugin. Ghim cứng ở đây thì nó lệch sau mỗi lần nâng
// bản, và phép kiểm nào so theo số bản sẽ sai mà không ai để ý.
preg_match( "/KHTC_VERSION', '([0-9.]+)'/", (string) file_get_contents( $goc . 'kh-tai-chinh.php' ), $m );
define( 'KHTC_VERSION', $m[1] ?? '0' );
define( 'KHTC_DIR', $goc );
define( 'KHTC_URL', 'https://vi.du/wp-content/plugins/kh-tai-chinh/' );
define( 'KHTC_CAP', 'edit_pages' );
foreach ( array( 'db', 'cty', 'nguoi-dung', 'tep', 'xls', 'don-app', 'nap-lo', 'diem', 'sinh-hd', 'dan-tho', 'nhat-ky', 'khoa', 'ngan-hang', 'giao-dich', 'doi-soat', 'chi-phi', 'hoa-don-ra', 'hoa-don-vao', 'cong-no', 'phap-danh', 'ho-so', 'bao-cao', 'mau', 'sao-luu', 'ui', 'trang', 'web', 'admin' ) as $t ) {
	require_once $goc . 'includes/class-khtc-' . $t . '.php';
}
