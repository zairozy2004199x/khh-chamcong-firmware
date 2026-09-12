<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỆ ĐỠ WORDPRESS GIẢ DÙNG CHUNG CHO MỌI BÀI THỬ CỦA `vhcp-saoke`.
 *
 * 🔴 VÌ SAO TÁCH RA. Bệ đỡ này dài gần 100 dòng. Bài thử thứ hai mà chép lại thì hai bản sẽ lệch
 *    nhau — và lệch ở BỆ ĐỠ là thứ tệ nhất: bài này xanh bài kia đỏ trên cùng một đoạn mã, mất
 *    cả buổi đi tìm lỗi ở chỗ không có lỗi. Đúng bài học "một luật một chỗ" của §6 CLAUDE.md.
 *
 * ⚠️ NẰM TRONG `lib/` LÀ CỐ Ý: `chay-het.sh` quét `tools/test/*.php`, không đệ quy. Để cạnh các
 *    bài thử thì nó bị chạy như một bài thử rỗng và báo ✓ vô nghĩa.
 *
 * Dùng: require_once __DIR__ . '/lib/be-saoke.php';  rồi require lớp thật.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

/* __DIR__ ở đây là tools/test/lib -> lùi BA bậc mới tới gốc kho. Lùi hai bậc là ra `tools/`
   và mọi require sau đó đứt, báo lỗi ở dòng require chứ không ở đây. */
$GOC = dirname( dirname( dirname( __DIR__ ) ) );
$SRC = file_get_contents( $GOC . '/vhcp-saoke/vhcp-saoke.php' );
$APP = file_get_contents( $GOC . '/vhcp-saoke/app.html' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BỆ ĐỠ WORDPRESS GIẢ — đủ để nạp lớp, không hơn.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
define( 'ABSPATH', $GOC . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
$GLOBALS['OPT'] = array();
function get_option( $k, $m = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $m; }
function update_option( $k, $v, $a = null ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['OPT'][ $k ] ); return true; }
function add_option( $k, $v ) { if ( ! isset( $GLOBALS['OPT'][ $k ] ) ) { $GLOBALS['OPT'][ $k ] = $v; } }
function add_action() {} function add_filter() {} function add_shortcode() {}
function register_activation_hook() {} function register_deactivation_hook() {}
function register_rest_route() {} function wp_next_scheduled() { return false; }
function wp_schedule_event() {} function wp_clear_scheduled_hook() {} function flush_rewrite_rules() {}
function current_time( $f ) { return 'mysql' === $f ? '2026-09-12 10:00:00' : time(); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o | JSON_UNESCAPED_UNICODE ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s ) { return $s; }
function admin_url( $s = '' ) { return '/wp-admin/' . $s; }
function wp_remote_get() { return new WP_Error( 'off', 'không ra mạng trong bài thử' ); }
function wp_remote_post() { return new WP_Error( 'off', 'không ra mạng trong bài thử' ); }
function wp_remote_retrieve_body() { return ''; }
function wp_remote_retrieve_response_code() { return 0; }
class WP_Error { public $c; public $m; public function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; } public function get_error_message() { return $this->m; } }

/** $wpdb giả: chỉ đủ cho `luu_cong()` — get_row theo khoá, insert, update. */
class FakeWpdb {
	public $prefix = 'wp_';
	public $hang = array();          // bảng saoke_cong trong bộ nhớ
	public $so_insert = 0; public $so_update = 0;
	public function get_charset_collate() { return ''; }
	/* 🔴 PHẢI ĂN CHỖ GIỮ THEO ĐÚNG THỨ TỰ XUẤT HIỆN.
	 *    Bản cũ với mỗi tham số lại thay "%s đầu tiên" RỒI "%d đầu tiên" — câu chỉ toàn %s thì
	 *    không sao, nên nó sống sót suốt. Tới câu `nguon=%s AND so_tien=%d AND ( ref=%s ... )`
	 *    thì tham số SỐ TIỀN nhảy vào ăn luôn ô `ref=%s` phía sau, câu ra sai hoàn toàn và bệ đỡ
	 *    trả về "không tìm thấy" — bài thử đỏ ở chỗ mã nguồn không hề sai. Lỗi ở bệ đỡ là loại
	 *    tốn thời gian nhất: nó đổ tội cho đúng thứ mình đang thử. */
	public function prepare( $sql, ...$a ) {
		if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
		foreach ( $a as $v ) {
			$is = strpos( $sql, '%s' ); $id = strpos( $sql, '%d' );
			if ( false === $is && false === $id ) { break; }
			$dungS = ( false !== $is ) && ( false === $id || $is < $id );
			$vi = $dungS ? $is : $id;
			$the = $dungS ? ( "'" . str_replace( "'", "''", (string) $v ) . "'" ) : (string) (int) $v;
			$sql = substr( $sql, 0, $vi ) . $the . substr( $sql, $vi + 2 );
		}
		return $sql;
	}
	/* Hiểu ĐÚNG HAI câu mà `luu_cong()` hỏi — không hơn. Bệ đỡ hiểu quá rộng thì nó tự trả lời
	   thay cho luật đang thử, và bài xanh trong khi thật thì hỏng. */
	public function get_row( $sql, $out = null ) {
		if ( preg_match( "/khoa='([^']*)'/", $sql, $m ) ) {
			foreach ( $this->hang as $h ) { if ( (string) $h['khoa'] === $m[1] ) { return $h; } }
			return null;
		}
		if ( preg_match( "/nguon='([^']*)' AND so_tien=(\d+) AND \( ref='([^']*)' OR ma_gd='([^']*)' \)/", $sql, $m ) ) {
			foreach ( $this->hang as $h ) {
				if ( (string) $h['nguon'] !== $m[1] ) { continue; }
				if ( (int) $h['so_tien'] !== (int) $m[2] ) { continue; }
				if ( (string) $h['ref'] === $m[3] || (string) $h['ma_gd'] === $m[4] ) { return $h; }
			}
			return null;
		}
		return null;
	}
	public function get_var( $sql ) { return null; }
	public function get_results( $sql, $out = null ) { return array(); }
	public function insert( $tbl, $data ) {
		$data['id'] = count( $this->hang ) + 1;
		$this->hang[] = $data; $this->so_insert++; return 1;
	}
	public function update( $tbl, $data, $where ) {
		foreach ( $this->hang as $i => $h ) {
			if ( (int) $h['id'] === (int) $where['id'] ) {
				$this->hang[ $i ] = array_merge( $h, $data ); $this->so_update++; return 1;
			}
		}
		return 0;
	}
	public function query( $sql ) { return 0; }
	public function esc_like( $s ) { return $s; }
}
$GLOBALS['wpdb'] = new FakeWpdb();


/** Gọi hàm private/protected cho việc thử — chạy lõi thật, không chép luật. */
function goi( $ten, $args = array() ) {
	$m = new ReflectionMethod( 'SAOKE_App', $ten );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

/** Kết luận chung — mọi bài dùng bệ đỡ này kết thúc bằng ket_luan('…'). */
function ket_luan( $loi ) {
	global $DAT, $TRUOT;
	echo "\n";
	if ( count( $TRUOT ) ) {
		echo '✗ TRƯỢT ' . count( $TRUOT ) . ' / ' . ( $DAT + count( $TRUOT ) ) . ":\n";
		foreach ( $TRUOT as $x ) { echo '    · ' . $x . "\n"; }
		exit( 1 );
	}
	echo '✓ SẠCH — ' . $DAT . ' phép: ' . $loi . "\n";
}
