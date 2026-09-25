<?php
/* Điểm chia sẻ kho Chi phí quản lý bên plugin VENDING HCMC (tệp includes/class-vhcm-chia-se-chi-phi.php, 1.81.0).
 * Anh Thắng 25/09/2026: "nối chi phí từ web khác qua chi phí của web anh". Mã nguồn plugin Vending KHÔNG nằm
 * trong kho này (anh gửi zip) — bài kiểm đọc bản đã sửa ở thư mục tạm; không có thì bỏ qua êm.
 * Chạy: VHCM_DIR=<thư mục vending-hcmc> php tools/test/kiem-vhcm-chia-se-chi-phi.php */
require_once __DIR__ . '/wp-stub.php';
$dir = getenv( 'VHCM_DIR' ) ?: '/tmp/claude-0/-home-user-khh-chamcong-firmware/9988d896-82bc-588a-8af8-69d7d7d7ba96/scratchpad/vend/vending-hcmc';
if ( ! is_file( $dir . '/includes/class-vhcm-chia-se-chi-phi.php' ) ) { echo "⏭ bỏ qua — không thấy mã plugin Vending ở $dir\n"; exit( 0 ); }
$dat = 0; $hong = array();
function phep( $t, $d, $them = null ) { global $dat, $hong; if ( $d ) { $dat++; } else { $hong[] = $t . ( null === $them ? '' : ' → ' . json_encode( $them, JSON_UNESCAPED_UNICODE ) ); } }
/* Giả VHCM_DB: kho JSON theo khoá. */
class VHCM_DB { public static $kho = array(); public static function get( $k, $d = array() ) { return array_key_exists( $k, self::$kho ) ? self::$kho[ $k ] : $d; } }
if ( ! function_exists( 'add_submenu_page' ) ) { function add_submenu_page() { return true; } }
require $dir . '/includes/class-vhcm-chia-se-chi-phi.php';
VHCM_DB::$kho['managerExpenses'] = array(
	array( 'id' => 1, 'code' => 'CP-1', 'date' => '2026-09-03', 'department' => 'POSH', 'type' => 'Xăng xe', 'content' => 'x', 'amount' => '350000', 'requester' => 'A', 'approver' => 'B', 'status' => 'Đã thanh toán', 'receiptCode' => 'HD1', 'receiptLink' => 'https://x/1.jpg', 'note' => 'n', 'ownerRole' => 'tech_main', 'ownerRoleName' => 'Kỹ thuật chính' ),
	array( 'id' => 2, 'code' => 'CP-2', 'date' => '2026-08-31', 'department' => 'JP', 'type' => 'Bảo trì', 'amount' => 5, 'status' => 'Chờ duyệt' ),
	array( 'id' => 3, 'code' => 'CP-3', 'date' => '2026-10-01', 'department' => 'JP', 'type' => 'Bảo trì', 'amount' => 6, 'status' => 'Đã duyệt' ),
	'rác không phải mảng',
);
$req = function ( $p, $khoa = null ) { return new WP_REST_Request( $p, null === $khoa ? array() : array( 'X-KHH-Khoa' => $khoa ) ); };
$la_401 = function ( $x ) { return is_wp_error( $x ) && 401 === $x->get_error_data()['status']; };

delete_option( VHCM_ChiaSeChiPhi::O_KHOA );
phep( '🔴 chưa đặt khoá → 401 (kể cả gửi chuỗi nào đó)', $la_401( VHCM_ChiaSeChiPhi::duoc_goi( $req( array(), 'abc' ) ) ) );
$kq = VHCM_ChiaSeChiPhi::ap_dung( array( 'khoa' => 'KHOA-VENDING-1234567890' ) );
phep( '   lưu khoá dán tay', ! empty( $kq['thayDoi'] ) && 'KHOA-VENDING-1234567890' === VHCM_ChiaSeChiPhi::khoa() );
phep( '🔴 khoá sai → 401', $la_401( VHCM_ChiaSeChiPhi::duoc_goi( $req( array(), 'KHOA-VENDING-1234567891' ) ) ) );
phep( '🔴 không header → 401', $la_401( VHCM_ChiaSeChiPhi::duoc_goi( $req( array() ) ) ) );
phep( '   đúng khoá → mở', true === VHCM_ChiaSeChiPhi::duoc_goi( $req( array(), 'KHOA-VENDING-1234567890' ) ) );

$r = VHCM_ChiaSeChiPhi::rest( $req( array( 'tu' => '2026-09-01', 'den' => '2026-09-30' ) ) );
phep( '🔴 lọc theo ngày: chỉ CP-1 (31/8 và 1/10 rớt), rác bị bỏ', ! empty( $r['ok'] ) && 1 === $r['soKhoan'] && 'CP-1' === $r['chiPhi'][0]['code'], $r );
$k = array_keys( $r['chiPhi'][0] );
phep( '🔴 trả đủ trường nghiệp vụ, KHÔNG có ownerRole / PIN', in_array( 'status', $k, true ) && in_array( 'receiptLink', $k, true ) && ! in_array( 'ownerRole', $k, true ) && ! in_array( 'ownerRoleName', $k, true ), $k );
phep( '   amount ép số, id ép chuỗi', 350000.0 === $r['chiPhi'][0]['amount'] && '1' === $r['chiPhi'][0]['id'] );
$r = VHCM_ChiaSeChiPhi::rest( $req( array() ) );
phep( '   không tu/den → trả hết 3 khoản hợp lệ', 3 === $r['soKhoan'], $r['soKhoan'] );
$r = VHCM_ChiaSeChiPhi::rest( $req( array( 'tu' => '2026-09-30', 'den' => '2026-09-01' ) ) );
phep( '   tu > den → tự đảo', 1 === $r['soKhoan'] );
$p = VHCM_ChiaSeChiPhi::rest( $req( array( 'ping' => '1' ) ) );
phep( '   ping → ok, không kéo số', ! empty( $p['ok'] ) && ! isset( $p['chiPhi'] ) );

phep( '🔴 ô trống = GIỮ', empty( VHCM_ChiaSeChiPhi::ap_dung( array( 'khoa' => '' ) )['thayDoi'] ) && 'KHOA-VENDING-1234567890' === VHCM_ChiaSeChiPhi::khoa() );
phep( '   khoá < 12 → chối', ! empty( VHCM_ChiaSeChiPhi::ap_dung( array( 'khoa' => 'ngan' ) )['loi'] ) );
$kq = VHCM_ChiaSeChiPhi::ap_dung( array( 'tao' => true ) );
phep( '   tạo ngẫu nhiên 40 ký tự, bày một lần', 40 === strlen( $kq['khoaMoi'] ) && $kq['khoaMoi'] === VHCM_ChiaSeChiPhi::khoa() );
phep( '   khoá cũ hết hiệu lực', $la_401( VHCM_ChiaSeChiPhi::duoc_goi( $req( array(), 'KHOA-VENDING-1234567890' ) ) ) );
VHCM_ChiaSeChiPhi::ap_dung( array( 'xoa' => true, 'khoa' => 'KHOA-KHAC-1234567890' ) );
phep( '   tích xoá → xoá dù ô có chữ', '' === VHCM_ChiaSeChiPhi::khoa() );
/* ── ĐẨY sang web Chi phí (chiều chính — anh Thắng: "Đẩy là đẩy từ vending về web chi phí của anh để quyết toán") ── */
VHCM_ChiaSeChiPhi::ap_dung( array( 'khoa' => 'KHOA-VENDING-1234567890' ) );
$kq = VHCM_ChiaSeChiPhi::ap_dung( array( 'url' => 'khmatrix.com' ) );
phep( '   địa chỉ web Chi phí thiếu http(s) → chối', ! empty( $kq['loi'] ) );
VHCM_ChiaSeChiPhi::ap_dung( array( 'url' => 'https://khmatrix.com/chi-phi/?x=1' ) );
phep( '🔴 dán địa chỉ trang app Chi phí (/chi-phi/?x=1) → lưu về GỐC web', 'https://khmatrix.com' === VHCM_ChiaSeChiPhi::url(), VHCM_ChiaSeChiPhi::url() );
VHCM_ChiaSeChiPhi::ap_dung( array( 'url' => 'https://khmatrix.com/' ) );
phep( '   lưu địa chỉ web Chi phí, bỏ / cuối', 'https://khmatrix.com' === VHCM_ChiaSeChiPhi::url() );
delete_option( VHCM_ChiaSeChiPhi::O_HASH );
$kho = VHCM_DB::$kho['managerExpenses'];
$can = VHCM_ChiaSeChiPhi::khoan_can_day( $kho );
phep( '🔴 chỉ đẩy khoản Đã duyệt / Đã thanh toán / Đã quyết toán (CP-1, CP-3), không đẩy Chờ duyệt (CP-2)', 2 === count( $can ) && 'CP-1' === $can[0]['code'] && 'CP-3' === $can[1]['code'], array_column( $can, 'code' ) );
$GLOBALS['VHD_DA_GUI'] = array();
$GLOBALS['VHD_POST'] = array( 'khmatrix.com/wp-json/vhcp/v1/vending-nhan' => array( 'code' => 200, 'body' => '{"success":true,"moi":2,"capNhat":0,"daChot":0,"loi":[]}' ) );
$kq = VHCM_ChiaSeChiPhi::khi_ghi_kho( 'managerExpenses', $kho );
phep( '🔴 móc lưu kho → POST đúng điểm nhận, khoá trong HEADER, không trong địa chỉ', $kq && ! empty( $kq['ok'] ) && 1 === count( $GLOBALS['VHD_DA_GUI'] )
	&& false !== strpos( $GLOBALS['VHD_DA_GUI'][0]['url'], 'https://khmatrix.com/wp-json/vhcp/v1/vending-nhan' ) && false === strpos( $GLOBALS['VHD_DA_GUI'][0]['url'], 'KHOA-' ), $GLOBALS['VHD_DA_GUI'] );
$goi = json_decode( (string) $GLOBALS['VHD_DA_GUI'][0]['body'], true );
phep( '   thân gói: web + 2 khoản, không ownerRole', is_array( $goi ) && 2 === count( $goi['khoan'] ) && ! isset( $goi['khoan'][0]['ownerRole'] ) && 'CP-1' === $goi['khoan'][0]['code'], $goi );
phep( '   kết quả lưu lại: gửi 2, ok, trả về mới 2', 2 === $kq['gui'] && 2 === $kq['tra']['moi'] && 2 === (int) get_option( VHCM_ChiaSeChiPhi::O_KQ )['gui'] );
$GLOBALS['VHD_DA_GUI'] = array();
$kq = VHCM_ChiaSeChiPhi::khi_ghi_kho( 'managerExpenses', $kho );
phep( '🔴 lưu lại y nguyên → KHÔNG đẩy lại (dấu không đổi)', null === $kq && 0 === count( $GLOBALS['VHD_DA_GUI'] ) );
$kho[0]['amount'] = 360000;
$kq = VHCM_ChiaSeChiPhi::khi_ghi_kho( 'managerExpenses', $kho );
phep( '   sửa tiền một khoản → đẩy đúng 1 khoản đó', $kq && 1 === $kq['gui'] && 'CP-1' === json_decode( $GLOBALS['VHD_DA_GUI'][0]['body'], true )['khoan'][0]['code'], $kq );
phep( '   kho khác (không phải chi phí) → bỏ qua', null === VHCM_ChiaSeChiPhi::khi_ghi_kho( 'incidents', array( array( 'id' => 9, 'status' => 'Đã duyệt' ) ) ) );
$GLOBALS['VHD_POST'] = array( 'khmatrix.com' => array( 'code' => 200, 'body' => '<!DOCTYPE html><html>trang app</html>' ) );
$kho[0]['amount'] = 365000;
$kq = VHCM_ChiaSeChiPhi::khi_ghi_kho( 'managerExpenses', $kho );
phep( '   web Chi phí trả HTML 200 → báo rõ "TRANG HTML" + nhắc gốc web, không nhớ dấu', $kq && empty( $kq['ok'] ) && false !== strpos( $kq['loi'], 'TRANG HTML' ) && 1 === count( VHCM_ChiaSeChiPhi::khoan_can_day( $kho ) ), $kq );
$GLOBALS['VHD_POST'] = array( 'khmatrix.com' => array( 'code' => 401, 'body' => '{"code":"vhcp_vd_khoa"}' ) );
$kho[0]['amount'] = 370000;
$kq = VHCM_ChiaSeChiPhi::khi_ghi_kho( 'managerExpenses', $kho );
phep( '   web Chi phí chối khoá → ghi lỗi, KHÔNG nhớ dấu (lần sau đẩy lại)', $kq && empty( $kq['ok'] ) && false !== strpos( $kq['loi'], 'khoá' ) && 1 === count( VHCM_ChiaSeChiPhi::khoan_can_day( $kho ) ), $kq );
$tat = VHCM_ChiaSeChiPhi::khoan_can_day( $kho, true );
phep( '   "đẩy lại toàn bộ" lấy mọi khoản đủ điều kiện kể cả đã đẩy', 2 === count( $tat ) );
delete_option( VHCM_ChiaSeChiPhi::O_URL );
phep( '   chưa khai địa chỉ → móc im lặng, không gọi mạng', null === VHCM_ChiaSeChiPhi::khi_ghi_kho( 'managerExpenses', $kho ) );
$db = file_get_contents( $dir . '/includes/class-vhcm-db.php' );
phep( '🔴 VHCM_DB::put phát móc vhcm_store_put SAU khi ghi, bọc try để không hỏng lượt lưu', false !== strpos( $db, "do_action('vhcm_store_put', \$key, \$data)" ) && false !== strpos( $db, 'try { do_action' ) );
$src = file_get_contents( $dir . '/includes/class-vhcm-chia-se-chi-phi.php' );
phep( '🔴 ô khoá không đổ value từ khoá đang lưu; type=password', ! preg_match( '/name="khoa"[^>]*value=/', $src ) && false !== strpos( $src, 'type="password" id="vhcm_khoa_cp"' ) );
$ld = file_get_contents( $dir . '/vending-hcmc.php' );
phep( '   loader nạp chia-se-chi-phi, bản 1.81.0', false !== strpos( $ld, "'chia-se-chi-phi'" ) && false !== strpos( $ld, "VHCM_VER', '1.81.0'" ) );

if ( $hong ) { echo "\n✗ TRƯỢT " . count( $hong ) . " phép (đạt $dat):\n"; foreach ( $hong as $h ) { echo "  · $h\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $dat phép: điểm chia sẻ chi phí Vending có khoá, lọc ngày, không lộ vai/PIN.\n";
