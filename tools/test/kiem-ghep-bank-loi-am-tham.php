<?php
/**
 * "KHÔNG LƯU ĐƯỢC MÃ NỘP TIỀN" — LỖI PHẢI LỘ RA, KHÔNG ĐƯỢC LẶNG THINH.
 *
 * Anh Thắng 26/09/2026, ảnh màn "bảng nhận mặt": gõ mã cho "Tutu Train - Aeon Tân An", bấm "Lưu và
 * gán lại", tải lại thì ô đó trống — TRONG KHI các cơ sở khác vẫn giữ đúng mã đã gõ. Không phải cả
 * bảng vỡ (đã vá 1.73.0: thiếu cột `nhan` làm hỏng CẢ CÂU, tức MẤT SẠCH mọi ô, không phải mất một
 * ô lẻ) — nghi vấn còn lại là `khh_dt_dat_ghep_bank()` tự bỏ một mã theo luật "một khoá chỉ thuộc
 * về một cơ sở" (nếu mã ấy trùng với mã một cơ sở KHÁC đã xử lý trước trong CÙNG lượt gửi) mà
 * KHÔNG BÁO GÌ CẢ — người khai tưởng máy hỏng, trong khi máy đang làm đúng luật, chỉ là im lặng.
 *
 * Bốn chốt:
 *   1. 🔴 Hai cơ sở khai TRÙNG một mã trong CÙNG lượt gửi -> `khh_dt_rest_sk_ghep()` phải trả về
 *      `canh_bao` nêu rõ mã nào, trùng ở những cơ sở nào — không lặng thinh báo "đã gán lại" suông.
 *   2. Không trùng mã thì không có `canh_bao` — không báo động giả cho lượt gửi bình thường.
 *   3. 🔴 Mã thật sự bị bỏ (không dịch được cho cơ sở thứ hai) — xác nhận đúng luật cũ vẫn giữ,
 *      chỉ thêm phần BÁO, không đổi hành vi lưu.
 *   4. Giao diện hiện đúng dòng cảnh báo ấy, không phải chỉ ở tầng REST.
 *
 * Chạy: php tools/test/kiem-ghep-bank-loi-am-tham.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
if ( ! function_exists( 'khh_dt_bang' ) ) { function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt'; } }
if ( ! function_exists( 'khh_dt_json' ) ) { function khh_dt_json( $raw, $mac_dinh ) { $v = json_decode( (string) $raw, true ); return is_array( $v ) ? $v : $mac_dinh; } }
if ( ! function_exists( 'khh_dt_so' ) ) { function khh_dt_so( $s ) { $s = preg_replace( '/[^\d\-\.]/', '', (string) $s ); return '' === $s ? 0 : (float) $s; } }
if ( ! function_exists( 'khh_dt_duoc_ghi' ) ) { function khh_dt_duoc_ghi() { return true; } }
if ( ! function_exists( 'khh_dt_duoc_xem' ) ) { function khh_dt_duoc_xem() { return true; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k, $one = true ) { return ''; } }
require_once $goc . '/ve-khach.php';
require_once $goc . '/bao-cao-ngay.php';
require_once $goc . '/sao-ke.php';

$dat = 0; $hong = array();
function phep4( $t, $d ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t; } }

global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang_sk() );
$wpdb->exec_raw(
	'CREATE TABLE ' . khh_dt_bang_sk() . " (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		ma_gd TEXT NOT NULL DEFAULT '' UNIQUE,
		ngay TEXT NOT NULL, gio INTEGER NOT NULL DEFAULT 0, ngay_tinh TEXT NOT NULL,
		so_tien REAL NOT NULL DEFAULT 0, noi_dung TEXT NOT NULL,
		tai_khoan TEXT NOT NULL DEFAULT '', cua_hang TEXT NOT NULL DEFAULT '', nhan TEXT NOT NULL DEFAULT '', nap_luc TEXT NULL )"
);

echo "── 1. Hai cơ sở trùng một mã trong cùng lượt gửi -> phải BÁO, không lặng thinh ──\n";
$r = khh_dt_rest_sk_ghep( new WP_REST_Request( array(
	'ghep' => wp_json_encode( array(
		'Tutu Train - Aeon Tân An ( Dịch vụ K&H )'   => 'KH705KVCMN0005',
		'VR Fun Aeon Tân An ( Dịch Vụ K&H )'          => 'KH705KVCMN0005',   // TRÙNG mã cơ sở trên
		'TuTu Train - Aeon Tân Phú ( Dịch Vụ K&H )'   => 'KH989KVCMN0002',   // không trùng ai
	) ),
	'gio_cat' => '12',
) ) );
phep4( '🔴 có canh_bao khi trùng mã', ! is_wp_error( $r ) && ! empty( $r['canh_bao'] ) );
phep4( 'canh_bao nêu đúng mã bị trùng', ! is_wp_error( $r ) && false !== strpos( (string) ( $r['canh_bao'] ?? '' ), 'kh705kvcmn0005' ) );
phep4( 'canh_bao nêu cả hai tên cơ sở trùng nhau',
	! is_wp_error( $r ) && false !== strpos( (string) ( $r['canh_bao'] ?? '' ), 'Tutu Train - Aeon Tân An' )
	&& false !== strpos( (string) ( $r['canh_bao'] ?? '' ), 'VR Fun Aeon Tân An' ) );

$theo = khh_dt_ghep_theo_co_so();
phep4( '🔴 mã trùng: CHỈ cơ sở gửi TRƯỚC trong lượt này được lưu (luật cũ giữ nguyên, chỉ thêm báo)',
	isset( $theo['Tutu Train - Aeon Tân An ( Dịch vụ K&H )'] )
	&& ! isset( $theo['VR Fun Aeon Tân An ( Dịch Vụ K&H )'] ) );
phep4( 'cơ sở không trùng vẫn lưu bình thường',
	isset( $theo['TuTu Train - Aeon Tân Phú ( Dịch Vụ K&H )'] ) );

echo "\n── 2. Không trùng mã thì không báo động giả ──\n";
$r2 = khh_dt_rest_sk_ghep( new WP_REST_Request( array(
	'ghep' => wp_json_encode( array(
		'Tutu Train - Aeon Tân An ( Dịch vụ K&H )'  => 'KH705KVCMN0005',
		'VR Fun Aeon Tân An ( Dịch Vụ K&H )'         => 'KH705KVCMN0006',
	) ),
) ) );
phep4( 'không trùng mã thì không có canh_bao', ! is_wp_error( $r2 ) && empty( $r2['canh_bao'] ) );

echo "\n── 3. Giao diện hiện đúng cảnh báo (không chỉ ở tầng REST) ──\n";
$js = (string) file_get_contents( $goc . '/assets/doanh-thu.js' );
phep4( '🔴 JS hiện kq.canh_bao trong #dtBankBao', false !== strpos( $js, "kq.canh_bao ? '<br><b style=\"color:var(--xau)\">⚠ ' + esc(kq.canh_bao)" ) );

if ( $hong ) { echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: mã trùng cơ sở trong một lượt gửi giờ được BÁO RÕ, không còn lặng thinh mất một ô.\n";
