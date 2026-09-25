<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO TỔNG "KHÔNG NỐI ĐƯỢC TỚI MÁY CHỦ" KHI CHỌN KHOẢNG NGÀY RỘNG (v2.141.0)
 *
 * Anh Thắng 25/09/2026: *"chỗ này lấy 12-25 thì ok, mà chọn 01-25 là bị lỗi"* — trắng màn, báo
 * "Không nối được tới máy chủ (mất mạng giữa chừng, hoặc bị chặn)" — đúng dấu hiệu lượt PHP chạy quá
 * lâu bị host cắt ngang, không phải lỗi JSON hay lỗi phân quyền.
 *
 * 🔴 NGUYÊN NHÂN: `bc_dong` (bảng lớn nhất, mỗi ghế mỗi lần thu một dòng) chỉ có khoá ghép
 *    `may_ngay (ma_may,ngay)`. Mọi câu Báo cáo tổng / Lịch sử / VietQR thực lọc THẲNG theo khoảng
 *    ngày, KHÔNG có điều kiện `ma_may=` — khoá ghép ấy vô dụng cho câu lọc kiểu này, MySQL phải dò
 *    gần hết bảng; khoảng ngày càng rộng càng chạm ngưỡng thời gian máy chủ cho phép. `bc`, `bc_khoa`,
 *    `bc_yeucau`… đều có sẵn `KEY ngay (ngay)` — `bc_dong` là bảng DUY NHẤT thiếu.
 *
 * Bài này canh: (1) định nghĩa bảng cho cài mới có khoá `ngay`; (2) migration TAY cho site đang
 * chạy — dò `SHOW INDEX`, thêm khi thiếu, không đụng gì khi đã có (idempotent); (3) mọi câu lọc
 * `bc_dong` theo khoảng ngày thuần (không kèm `ma_may=`) giờ có khoá để dùng.
 *
 * Chạy: php tools/test/kiem-bao-cao-tong-ngay-rong.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
define( 'ARRAY_A', 'ARRAY_A' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }

$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-db.php' );

echo "── 1. Định nghĩa bảng (cài mới) ─────────────────────────────────────\n";
if ( preg_match( "/\\\$b\\['bc_dong'\\] = \"(.*?)\";/s", $src, $m ) ) {
	$def = $m[1];
	t( 'khoá ghép cũ vẫn còn (không phá luật cũ)', false !== strpos( $def, 'KEY may_ngay (ma_may,ngay)' ) );
	t( '🔴 có thêm khoá riêng theo ngày', (bool) preg_match( '/KEY ngay \(ngay\)/', $def ) );
} else { t( 'bốc được định nghĩa bảng bc_dong', false ); }

echo "── 2. Migration tay cho site đang chạy ──────────────────────────────\n";
$mig = boc( $src, 'private static function migrate_()' );
t( 'bốc được migrate_()', '' !== $mig );
t( '🔴 dò SHOW INDEX ... WHERE Key_name=\'ngay\' trên bảng bc_dong ($bcd, đã có sẵn ở migration moc_tay/decimal ngay trên)', (bool) preg_match( "/SHOW INDEX FROM \\\$bcd WHERE Key_name='ngay'/", $mig ) );
t( 'thêm bằng ALTER TABLE ... ADD INDEX ngay (ngay), không phải UNIQUE (nhiều dòng cùng ngày là bình thường)', (bool) preg_match( "/ALTER TABLE \\\$bcd ADD INDEX ngay \(ngay\)/", $mig ) );

echo "── 3. Chạy thật trên CSDL giả — idempotent ──────────────────────────\n";
class WpdbGia {
	public $sql = array(); public $co_index = false;
	public function get_var( $q ) { $this->sql[] = $q;
		if ( false !== strpos( $q, "SHOW INDEX FROM wp_vhg_bc_dong WHERE Key_name='ngay'" ) ) { return $this->co_index ? 'ngay' : null; }
		if ( false !== strpos( $q, 'SHOW COLUMNS' ) ) { return 'co_roi'; }   // các migration cột khác coi như đã lên, khỏi ALTER lại
		if ( false !== strpos( $q, "Key_name='coso_ngay'" ) ) { return null; }
		return null; }
	public function get_row( $q, $o = null ) { $this->sql[] = $q; return array( 'Type' => 'decimal(14,2)' ); }
	public function query( $q ) { $this->sql[] = $q; return 1; }
}
function chay_migrate_( $mig, $ten ) {
	eval( 'class ' . $ten . ' { public static function t( $b ) { return "wp_vhg_" . $b; } ' . $mig . ' public static function goi() { self::migrate_(); } }' );
	call_user_func( array( $ten, 'goi' ) );
}
global $wpdb; $wpdb = new WpdbGia(); $wpdb->co_index = false;
chay_migrate_( $mig, 'VHG_DB_Chay1' );
t( '🔴 site CHƯA có khoá "ngay" → chạy migrate_() thì ALTER TABLE thêm khoá', (bool) preg_grep( "/^ALTER TABLE wp_vhg_bc_dong ADD INDEX ngay \(ngay\)\$/", $wpdb->sql ), $wpdb->sql );
$wpdb = new WpdbGia(); $wpdb->co_index = true;
chay_migrate_( $mig, 'VHG_DB_Chay2' );
t( 'site ĐÃ có khoá "ngay" → không ALTER lại (idempotent, không khoá bảng vô ích mỗi lần đổi bản)', ! preg_grep( '/ADD INDEX ngay/', $wpdb->sql ) );

echo "── 4. Mọi câu lọc bc_dong theo khoảng ngày thuần đều có khoá để dùng ─\n";
$K = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$B = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-baocao.php' );
$n = substr_count( $K, 'ngay BETWEEN %s AND %s' ) + substr_count( $B, 'ngay BETWEEN %s AND %s' );
t( '🔴 còn ít nhất 3 câu lọc bc_dong theo khoảng ngày (Báo cáo tổng, Lịch sử, VietQR thực…) — đúng lý do cần khoá riêng, không phải sửa thừa', $n >= 3, $n );
t( 'chính bao_cao_tong() không có điều kiện ma_may= (khoá ghép cũ không dùng được cho câu này)', false === strpos( boc( $K, 'public static function bao_cao_tong(' ), "ma_may=%s" ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
