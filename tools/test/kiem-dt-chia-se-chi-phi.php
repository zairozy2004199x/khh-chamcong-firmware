<?php
/**
 * CHIA SẺ DOANH THU THEO CỬA HÀNG CHO PLUGIN CHI PHÍ — `chia-se-chi-phi.php` (bên plugin Doanh thu).
 * Anh Thắng 25/09/2026: *"Em có thể lấy doanh thu cơ sở trên wed doanh-thu-hcm không."*
 * Chốt: chưa đặt khoá = đóng cửa · khoá sai 401 · đúng khoá chỉ trả TỔNG theo cửa hàng trong khoảng ngày ·
 * thiếu ngày 400 · ping không kéo số · luật lưu khoá: rỗng = giữ, xoá phải tích, tạo = bày một lần, ngắn < 12 chối.
 * Chạy: php tools/test/kiem-dt-chia-se-chi-phi.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
if ( ! function_exists( 'khh_dt_bang' ) ) {
	function khh_dt_bang() { global $wpdb; return $wpdb->prefix . 'khh_dt_ngay'; }
}
require_once $goc . '/chia-se-chi-phi.php';
$dat = 0; $hong = array();
function phep( $t, $d, $them = null ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t . ( null === $them ? '' : ' → ' . json_encode( $them, JSON_UNESCAPED_UNICODE ) ); } }

global $wpdb;
$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . khh_dt_bang() );
$wpdb->exec_raw( 'CREATE TABLE ' . khh_dt_bang() . " ( id INTEGER PRIMARY KEY AUTOINCREMENT, ngay TEXT NOT NULL, cua_hang TEXT NOT NULL DEFAULT '', doanh_thu REAL DEFAULT 0, thanh_tien REAL DEFAULT 0, so_hd INTEGER DEFAULT 0 )" );
$them = function ( $ngay, $ch, $dt, $tt, $hd ) use ( $wpdb ) {
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . khh_dt_bang() . ' (ngay,cua_hang,doanh_thu,thanh_tien,so_hd) VALUES (%s,%s,%f,%f,%d)', $ngay, $ch, $dt, $tt, $hd ) );
};
$them( '2026-09-01', 'FUNZONE AN LẠC', 1000000, 990000, 10 );
$them( '2026-09-02', 'FUNZONE AN LẠC', 2000000, 1980000, 20 );
$them( '2026-09-30', 'NHÀ MA PVT', 500000, 500000, 5 );
$them( '2026-08-31', 'FUNZONE AN LẠC', 7000000, 7000000, 70 );   // ngoài khoảng
$them( '2026-10-01', 'NHÀ MA PVT', 9000000, 9000000, 90 );       // ngoài khoảng

$req = function ( $p, $khoa = null ) {
	$r = new WP_REST_Request( $p, null === $khoa ? array() : array( 'X-KHH-Khoa' => $khoa ) );
	return $r;
};
$la_loi = function ( $x, $ma ) { return is_wp_error( $x ) && $ma === $x->get_error_data()['status']; };

/* ── Cửa ─────────────────────────────────────────────────────────────────────────────────────── */
delete_option( KHH_DT_O_KHOA_CP );
phep( '🔴 chưa đặt khoá → 401 dù bên gọi gửi chuỗi rỗng', $la_loi( khh_dt_cp_duoc_goi( $req( array(), '' ) ), 401 ) );
phep( '🔴 chưa đặt khoá → 401 dù bên gọi gửi khoá nào đó', $la_loi( khh_dt_cp_duoc_goi( $req( array(), 'abc' ) ), 401 ) );
$kq = khh_dt_cp_ap_dung( array( 'khoa' => 'KHOA-CHIA-SE-1234567890' ) );
phep( '   lưu khoá dán tay', ! empty( $kq['thayDoi'] ) && 'KHOA-CHIA-SE-1234567890' === khh_dt_cp_khoa(), $kq );
phep( '🔴 khoá sai → 401', $la_loi( khh_dt_cp_duoc_goi( $req( array(), 'KHOA-CHIA-SE-1234567891' ) ), 401 ) );
phep( '🔴 không header → 401', $la_loi( khh_dt_cp_duoc_goi( $req( array() ) ), 401 ) );
phep( '   đúng khoá → mở', true === khh_dt_cp_duoc_goi( $req( array(), 'KHOA-CHIA-SE-1234567890' ) ) );
phep( '   khoá có khoảng trắng hai đầu vẫn khớp', true === khh_dt_cp_duoc_goi( $req( array(), ' KHOA-CHIA-SE-1234567890 ' ) ) );

/* ── Số ──────────────────────────────────────────────────────────────────────────────────────── */
$kq = khh_dt_cp_rest_doanh_thu( $req( array( 'tu' => '2026-09-01', 'den' => '2026-09-30' ) ) );
phep( '   trả ok + tu/den', is_array( $kq ) && ! empty( $kq['ok'] ) && '2026-09-01' === $kq['tu'] && '2026-09-30' === $kq['den'], $kq );
$ch = array(); foreach ( $kq['cuaHang'] as $x ) { $ch[ $x['ten'] ] = $x; }
phep( '🔴 FUNZONE: chỉ cộng dòng trong khoảng (3tr), không lẫn 31/8', isset( $ch['FUNZONE AN LẠC'] ) && 3000000.0 === (float) $ch['FUNZONE AN LẠC']['doanhThu'], $kq );
phep( '   FUNZONE: thành tiền, số hoá đơn, số ngày', 2970000.0 === (float) $ch['FUNZONE AN LẠC']['thanhTien'] && 30 === $ch['FUNZONE AN LẠC']['soHd'] && 2 === $ch['FUNZONE AN LẠC']['soNgay'], $ch );
phep( '   Nhà Ma: ngày cuối tháng vẫn tính, 1/10 không', 500000.0 === (float) $ch['NHÀ MA PVT']['doanhThu'] && 1 === $ch['NHÀ MA PVT']['soNgay'], $ch );
phep( '   xếp doanh thu giảm dần', 'FUNZONE AN LẠC' === $kq['cuaHang'][0]['ten'] );
$keys = array_keys( $kq['cuaHang'][0] );
phep( '🔴 chỉ TỔNG — không có từng ngày / hoá đơn / món / cách thanh toán', ! array_intersect( $keys, array( 'ngay', 'mon', 'pttt', 'gio', 'pos_id' ) ), $keys );
phep( '   tu > den → tự đảo', ! empty( khh_dt_cp_rest_doanh_thu( $req( array( 'tu' => '2026-09-30', 'den' => '2026-09-01' ) ) )['ok'] ) );
phep( '   thiếu ngày → 400', $la_loi( khh_dt_cp_rest_doanh_thu( $req( array( 'tu' => '2026-09-01' ) ) ), 400 ) );
phep( '   ngày sai dạng → 400', $la_loi( khh_dt_cp_rest_doanh_thu( $req( array( 'tu' => '1/9/2026', 'den' => '2026-09-30' ) ) ), 400 ) );
$p = khh_dt_cp_rest_doanh_thu( $req( array( 'ping' => '1' ) ) );
phep( '   ping → ok, không kéo số', ! empty( $p['ok'] ) && ! isset( $p['cuaHang'] ), $p );

/* ── Luật lưu khoá ───────────────────────────────────────────────────────────────────────────── */
$kq = khh_dt_cp_ap_dung( array( 'khoa' => '' ) );
phep( '🔴 ô trống = GIỮ NGUYÊN', empty( $kq['thayDoi'] ) && 'KHOA-CHIA-SE-1234567890' === khh_dt_cp_khoa(), $kq );
$kq = khh_dt_cp_ap_dung( array( 'khoa' => 'ngan' ) );
phep( '   khoá < 12 ký tự → chối, giữ cũ', ! empty( $kq['loi'] ) && 'KHOA-CHIA-SE-1234567890' === khh_dt_cp_khoa(), $kq );
$kq = khh_dt_cp_ap_dung( array( 'tao' => true ) );
phep( '   tạo ngẫu nhiên: 40 ký tự, bày một lần qua khoaMoi, đã lưu', 40 === strlen( $kq['khoaMoi'] ) && $kq['khoaMoi'] === khh_dt_cp_khoa(), $kq );
phep( '   khoá cũ hết hiệu lực sau khi tạo mới', $la_loi( khh_dt_cp_duoc_goi( $req( array(), 'KHOA-CHIA-SE-1234567890' ) ), 401 ) );
set_transient( 'khh_dt_khoa_moi_1', 'XYZ', 120 );
phep( '   khoá mới bày đúng MỘT lần', 'XYZ' === khh_dt_cp_khoa_moi_lay() && '' === khh_dt_cp_khoa_moi_lay() );
$kq = khh_dt_cp_ap_dung( array( 'xoa' => true, 'khoa' => 'KHOA-KHAC-1234567890' ) );
phep( '   tích xoá → xoá (dù ô có chữ), cửa đóng lại', '' === khh_dt_cp_khoa() && $la_loi( khh_dt_cp_duoc_goi( $req( array(), 'KHOA-KHAC-1234567890' ) ), 401 ), $kq );

/* ── Mã nguồn: trang cài đặt không đổ khoá ra ô ──────────────────────────────────────────────── */
$src = file_get_contents( $goc . '/chia-se-chi-phi.php' );
phep( '🔴 ô nhập khoá KHÔNG có value= từ khoá đang lưu', ! preg_match( '/name="khoa"[^>]*value=/', $src ) && false === strpos( $src, 'value="<?php echo esc_attr( khh_dt_cp_khoa' ) );
phep( '   ô nhập là type=password, autocomplete=new-password', false !== strpos( $src, 'type="password" id="khh_dt_khoa_cp"' ) && false !== strpos( $src, 'autocomplete="new-password"' ) );
phep( '   khh-doanh-thu.php nạp tệp', false !== strpos( file_get_contents( $goc . '/khh-doanh-thu.php' ), "require_once KHH_DT_DIR . 'chia-se-chi-phi.php';" ) );

if ( $hong ) { echo "\n✗ TRƯỢT " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "  · $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: điểm chia sẻ doanh thu theo cửa hàng có khoá, luật lưu khoá.\n";
