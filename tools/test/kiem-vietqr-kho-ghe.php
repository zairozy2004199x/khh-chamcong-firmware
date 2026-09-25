<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GHẾ 2.142.0 — KHO SỐ VIETQR THỰC (vhg_bc_vqr, lớp VHG_VietQR): KHÔNG ĐỒNG NÀO RƠI, BẤM XEM LÀ TỰ NẠP
 *
 * Anh Thắng 25/09/2026: *"khi có dữ liệu thêm thì ghi vào máy, để cần đọc ngay, chứ sao kê nó đang quá tải
 * mà cứ gọi qua là lúc được lúc không"* · *"lúc bấm xem, là nó tự đẩy đọc và nạp vào trang ghế luôn"*.
 *
 * 🔴 BẤT BIẾN TIỀN: (a) ghi một ngày vào kho rồi đọc ra phải bằng Y NGUYÊN ba rổ Sao Kê trả (vq / chuaMay /
 *    khongKhop); (b) theo cơ sở = tổng mọi máy + chưa rõ máy của cơ sở ấy; (c) cộng lẻ một giao dịch (webhook)
 *    = kho cũ + tiền ấy, không đẻ dòng trùng. Bài này chạy LỚP THẬT trên một bảng giả trong bộ nhớ.
 *
 * Chạy: php tools/test/kiem-vietqr-kho-ghe.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
define( 'ABSPATH', '/' ); define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['NOW'] = '2026-09-25 09:00:00'; $GLOBALS['DS_COSO'] = array( array( 'ten' => 'AEON MALL TÂN PHÚ' ), array( 'ten' => 'VẠN HẠNH MALL' ) );
function current_time( $f ) { return 'mysql' === $f ? $GLOBALS['NOW'] : substr( $GLOBALS['NOW'], 0, 10 ); }
class VHG_DB { public static function t( $n ) { return 'wp_vhg_' . $n; } }
class VHG_BaoCao { public static function squash( $s ) { $s = strtr( mb_strtoupper( (string) $s, 'UTF-8' ), array( 'Â' => 'A', 'Ấ' => 'A', 'Ú' => 'U', 'Ạ' => 'A', 'Ạ' => 'A' ) ); return preg_replace( '/[^A-Z0-9]/', '', $s ); } }
class VHG_May { public static function ds_coso() { return $GLOBALS['DS_COSO']; } }
class SAOKE_App {
	public static $kq = array(); public static $goi = array();
	public static function vietqr_theo_may_ngay( $tu, $den ) { self::$goi[] = $tu; return isset( self::$kq[ $tu ] ) ? self::$kq[ $tu ] : array( 'co' => true, 'vq' => array(), 'chuaMay' => array(), 'khongKhop' => 0 ); }
}
/* Bảng giả: hiểu đúng những câu SQL lớp này phát ra — không hơn. Câu lạ → nổ để lộ ngay. */
class WpdbGia {
	public $rows = array(); public $id = 0; public $sql = array();
	public function prepare( $q, ...$a ) { foreach ( $a as $v ) { $q = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . str_replace( "'", "''", (string) $v ) . "'", $q, 1 ); } return $q; }
	private function loc( $ngayTu, $ngayDen ) { $o = array(); foreach ( $this->rows as $r ) { if ( $r['ngay'] >= $ngayTu && $r['ngay'] <= $ngayDen ) { $o[] = $r; } } return $o; }
	public function get_results( $q, $o = null ) { $this->sql[] = $q;
		if ( preg_match( "/SELECT ngay, coso, coso_key, ma_may, so_tien, cap_luc FROM wp_vhg_bc_vqr WHERE ngay BETWEEN '([^']+)' AND '([^']+)'/", $q, $m ) ) { return $this->loc( $m[1], $m[2] ); }
		throw new Exception( 'SQL lạ: ' . $q ); }
	public function get_col( $q ) { $this->sql[] = $q;
		if ( preg_match( "/SELECT ngay FROM wp_vhg_bc_vqr WHERE ngay BETWEEN '([^']+)' AND '([^']+)' AND coso_key='' AND ma_may=''/", $q, $m ) ) { $o = array(); foreach ( $this->loc( $m[1], $m[2] ) as $r ) { if ( '' === $r['coso_key'] && '' === $r['ma_may'] ) { $o[] = $r['ngay']; } } return $o; }
		if ( preg_match( "/SELECT DISTINCT ngay FROM wp_vhg_bc_vqr WHERE coso_key='([^']*)'/", $q, $m ) ) { $o = array(); foreach ( $this->rows as $r ) { if ( $r['coso_key'] === $m[1] && ! in_array( $r['ngay'], $o, true ) ) { $o[] = $r['ngay']; } } return $o; }
		throw new Exception( 'SQL lạ: ' . $q ); }
	public function get_var( $q ) { $this->sql[] = $q;
		if ( preg_match( "/SELECT id FROM wp_vhg_bc_vqr WHERE ngay='([^']+)' AND coso_key='' AND ma_may='' LIMIT 1/", $q, $m ) ) { foreach ( $this->rows as $r ) { if ( $r['ngay'] === $m[1] && '' === $r['coso_key'] && '' === $r['ma_may'] ) { return $r['id']; } } return null; }
		throw new Exception( 'SQL lạ: ' . $q ); }
	public function delete( $t, $w ) { $this->sql[] = 'DELETE ' . json_encode( $w ); $n = 0; foreach ( $this->rows as $i => $r ) { $ok = true; foreach ( $w as $k => $v ) { if ( $r[ $k ] !== $v ) { $ok = false; } } if ( $ok ) { unset( $this->rows[ $i ] ); $n++; } } $this->rows = array_values( $this->rows ); return $n; }
	public function insert( $t, $d ) { $this->sql[] = 'INSERT ' . json_encode( $d, JSON_UNESCAPED_UNICODE );
		foreach ( $this->rows as $r ) { if ( $r['ngay'] === $d['ngay'] && $r['coso_key'] === $d['coso_key'] && $r['ma_may'] === $d['ma_may'] ) { throw new Exception( 'TRÙNG KHOÁ DUY NHẤT (ngay,coso_key,ma_may): ' . json_encode( $d, JSON_UNESCAPED_UNICODE ) ); } }
		$d['id'] = ++$this->id; $d['so_tien'] = (int) $d['so_tien']; $this->rows[] = $d; return 1; }
	public function query( $q ) { $this->sql[] = $q;
		if ( 'START TRANSACTION' === $q || 'COMMIT' === $q ) { return true; }
		if ( preg_match( "/INSERT INTO wp_vhg_bc_vqr \(ngay, coso, coso_key, ma_may, so_tien, cap_luc\) VALUES \('([^']+)','([^']*)','([^']*)','([^']*)',(\d+),'([^']+)'\) ON DUPLICATE KEY UPDATE/", $q, $m ) ) {
			foreach ( $this->rows as $i => $r ) { if ( $r['ngay'] === $m[1] && $r['coso_key'] === $m[3] && $r['ma_may'] === $m[4] ) { $this->rows[ $i ]['so_tien'] += (int) $m[5]; $this->rows[ $i ]['coso'] = $m[2]; $this->rows[ $i ]['cap_luc'] = $m[6]; return 2; } }
			$this->rows[] = array( 'id' => ++$this->id, 'ngay' => $m[1], 'coso' => $m[2], 'coso_key' => $m[3], 'ma_may' => $m[4], 'so_tien' => (int) $m[5], 'cap_luc' => $m[6] ); return 1; }
		if ( preg_match( "/UPDATE wp_vhg_bc_vqr SET cap_luc='([^']+)' WHERE ngay='([^']+)' AND coso_key='' AND ma_may=''/", $q, $m ) ) { foreach ( $this->rows as $i => $r ) { if ( $r['ngay'] === $m[2] && '' === $r['coso_key'] && '' === $r['ma_may'] ) { $this->rows[ $i ]['cap_luc'] = $m[1]; } } return 1; }
		if ( preg_match( "/DELETE FROM wp_vhg_bc_vqr WHERE ngay='([^']+)'/", $q, $m ) ) { return $this->delete( 'x', array( 'ngay' => $m[1] ) ); }
		throw new Exception( 'SQL lạ: ' . $q ); }
	public function tong() { $s = 0; foreach ( $this->rows as $r ) { $s += $r['so_tien']; } return $s; }
}
global $wpdb; $wpdb = new WpdbGia();
require __DIR__ . '/../../vhcp-ghe/includes/class-vhg-vietqr.php';
t( 'nạp được lớp VHG_VietQR', class_exists( 'VHG_VietQR' ) );
function tongKq( $kq ) { $s = (int) $kq['khongKhop']; foreach ( (array) $kq['vq'] as $cs => $tm ) { foreach ( $tm as $ma => $tn ) { $s += array_sum( $tn ); } } foreach ( (array) $kq['chuaMay'] as $cs => $tn ) { $s += array_sum( $tn ); } return $s; }

echo "── 1. Thuần: dòng ghi từ kết quả Sao Kê ─────────────────────────\n";
$kq = array( 'co' => true,
	'vq' => array( 'AEON MALL TÂN PHÚ' => array( '80013' => array( '2026-09-01' => 100000, '2026-09-02' => 999 ), '80014' => array( '2026-09-01' => 50000 ) ), 'VẠN HẠNH MALL' => array( '80038' => array( '2026-09-01' => 70000 ) ) ),
	'chuaMay' => array( 'AEON MALL TÂN PHÚ' => array( '2026-09-01' => 30000 ) ),
	'khongKhop' => 90000 );
$ds = VHG_VietQR::dong_tu_kq( '2026-09-01', $kq );
t( '5 dòng: 3 máy + 1 chưa rõ máy + 1 dấu ngày (không khớp)', 5 === count( $ds ), $ds );
t( '🔴 tổng dòng = tổng ba rổ của NGÀY ẤY (bỏ 999đ của ngày 02 — không thuộc dòng ngày 01)', 340000 === array_sum( array_column( $ds, 'tien' ) ) );
t( 'dấu ngày luôn là dòng cuối, coso rỗng, tiền = khongKhop', array( 'coso' => '', 'ma' => '', 'tien' => 90000 ) === end( $ds ) );
t( 'ngày không có đồng nào vẫn ra ĐÚNG 1 dòng dấu = 0 (kho biết ngày đã đồng bộ)', array( array( 'coso' => '', 'ma' => '', 'tien' => 0 ) ) === VHG_VietQR::dong_tu_kq( '2026-09-09', array( 'co' => true, 'vq' => array(), 'chuaMay' => array(), 'khongKhop' => 0 ) ) );
$g = VHG_VietQR::gop_dong( array( array( 'coso' => 'AEON MALL TÂN PHÚ', 'ma' => '80013', 'tien' => 1 ), array( 'coso' => 'Aeon Mall Tân Phú', 'ma' => '80013', 'tien' => 2 ), array( 'coso' => '', 'ma' => '', 'tien' => 5 ), array( 'coso' => '', 'ma' => 'X', 'tien' => 7 ) ) );
t( '🔴 gop_dong: hai cách gõ một tên ra CÙNG khoá → CỘNG (3đ), không đè; không rõ cơ sở thì mã máy bỏ, dồn vào dấu (12đ)', 2 === count( $g ) && 3 === $g[0]['so_tien'] && 'AEONMALLTANPHU' === $g[0]['coso_key'] && 12 === $g[1]['so_tien'] && '' === $g[1]['coso_key'], $g );

echo "── 2. Ghi một ngày rồi đọc lại: y nguyên ba rổ ─────────────────\n";
$r = VHG_VietQR::nhan_ngay( '2026-09-01', $kq );
t( 'nhan_ngay ok, 5 dòng', ! empty( $r['ok'] ) && 5 === $r['dong'], $r );
t( '🔴 tổng tiền trong kho = tổng ba rổ ngày 01 (340.000)', 340000 === $wpdb->tong(), $wpdb->tong() );
$doc = VHG_VietQR::theo_may_ngay( '2026-09-01', '2026-09-01', false );
$mong = $kq; unset( $mong['vq']['AEON MALL TÂN PHÚ']['80013']['2026-09-02'] );
t( '🔴 theo_may_ngay đọc ra ĐÚNG vq / chuaMay / khongKhop đã ghi', $doc['vq'] == $mong['vq'] && $doc['chuaMay'] == $mong['chuaMay'] && 90000 === $doc['khongKhop'] && $doc['co'], $doc );
$cs = VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-01', false );
t( '🔴 theo cơ sở = mọi máy + chưa rõ máy: AEON 180.000, VHM 70.000, không khớp 90.000', 180000 === $cs['vq']['AEON MALL TÂN PHÚ']['2026-09-01'] && 70000 === $cs['vq']['VẠN HẠNH MALL']['2026-09-01'] && 90000 === $cs['khongKhop'], $cs['vq'] );
t( 'capLuc in dạng H:i d/m/Y từ giờ WP', '09:00 25/09/2026' === $cs['capLuc'], $cs['capLuc'] );
t( 'thieuNgay của khoảng 1 ngày đã có = rỗng, co = true', array() === $cs['thieuNgay'] && $cs['co'] );
$r2 = VHG_VietQR::nhan_ngay( '2026-09-01', $kq );
t( 'ghi lại cùng ngày → ĐÈ, không nhân đôi (tổng vẫn 340.000, không nổ khoá duy nhất)', ! empty( $r2['ok'] ) && 340000 === $wpdb->tong() );
$GLOBALS['DS_COSO'][] = array( 'ten' => 'AEON TAN PHU MOI' );
$wpdb->rows[0]['coso'] = 'ten cu'; $wpdb->rows[0]['coso_key'] = 'AEONTANPHUMOI';   // giả dòng cũ mang tên cũ, khoá = cơ sở đã đổi tên
$cs2 = VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-01', false );
t( 'dòng kho mang tên cũ → đọc ra dưới TÊN ĐANG DÙNG trong danh mục (theo coso_key)', isset( $cs2['vq']['AEON TAN PHU MOI'] ) && ! isset( $cs2['vq']['ten cu'] ), array_keys( $cs2['vq'] ) );
$wpdb->rows[0]['coso'] = 'AEON MALL TÂN PHÚ'; $wpdb->rows[0]['coso_key'] = 'AEONMALLTANPHU'; array_pop( $GLOBALS['DS_COSO'] );

echo "── 3. Ngày thiếu & tự kéo lúc bấm Xem ──────────────────────────\n";
t( 'ds_ngay 01→03 = 3 ngày, đảo ngược vẫn thế', array( '2026-09-01', '2026-09-02', '2026-09-03' ) === VHG_VietQR::ds_ngay( '2026-09-03', '2026-09-01' ) );
t( 'ngay_thieu 01→03: thiếu 02, 03', array( '2026-09-02', '2026-09-03' ) === VHG_VietQR::ngay_thieu( '2026-09-01', '2026-09-03' ) );
$dsN = VHG_VietQR::ds_ngay( '2026-09-20', '2026-09-25' );
$chon = VHG_VietQR::chon_ngay_keo( $dsN, array( '2026-09-21' ), '2026-09-25', array( '2026-09-24' => '2026-09-25 08:40:00', '2026-09-25' => '2026-09-25 08:58:00' ), '2026-09-25 09:00:00' );
t( '🔴 hôm qua cũ 20 phút → kéo; hôm nay mới 2 phút → không; 1 ngày thiếu → kéo', array( '2026-09-24', '2026-09-21' ) === $chon, $chon );
$chon = VHG_VietQR::chon_ngay_keo( $dsN, array( '2026-09-20', '2026-09-21', '2026-09-22', '2026-09-23' ), '2026-09-25', array( '2026-09-24' => '2026-09-25 08:59:30', '2026-09-25' => '2026-09-25 08:59:30' ), '2026-09-25 09:00:00' );
t( 'thiếu 4 ngày (> 3) → KHÔNG kéo trong lượt (để màn hình kéo từng đợt); hôm nay/hôm qua tươi → không', array() === $chon, $chon );
$chon = VHG_VietQR::chon_ngay_keo( $dsN, array( '2026-09-25' ), '2026-09-25', array( '2026-09-24' => '2026-09-25 08:59:00' ), '2026-09-25 09:00:00' );
t( 'hôm nay chưa có → nằm trong "thiếu", kéo đúng 1 lần (không trùng)', array( '2026-09-25' ) === $chon, $chon );
t( 'ngoài khoảng xem thì hôm nay/hôm qua không bị kéo oan', array() === VHG_VietQR::chon_ngay_keo( VHG_VietQR::ds_ngay( '2026-09-01', '2026-09-05' ), array(), '2026-09-25', array(), '2026-09-25 09:00:00' ) );
SAOKE_App::$goi = array(); SAOKE_App::$kq['2026-09-02'] = array( 'co' => true, 'vq' => array( 'VẠN HẠNH MALL' => array( '80038' => array( '2026-09-02' => 11000 ) ) ), 'chuaMay' => array(), 'khongKhop' => 0 );
$cs = VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-03' );
t( '🔴 bấm Xem 01→03: 2 ngày thiếu (≤3) tự kéo từ Sao Kê ngay trong lượt, rồi không còn thiếu', array( '2026-09-02', '2026-09-03' ) === SAOKE_App::$goi && array() === $cs['thieuNgay'], array( SAOKE_App::$goi, $cs['thieuNgay'] ) );
t( 'và số ngày 02 đã vào bảng', 11000 === $cs['vq']['VẠN HẠNH MALL']['2026-09-02'] );
SAOKE_App::$goi = array();
$cs = VHG_VietQR::theo_coso_ngay( '2026-09-10', '2026-09-19' );
t( 'thiếu 10 ngày → không kéo trong lượt, trả đủ 10 ngày thiếu cho màn hình', array() === SAOKE_App::$goi && 10 === count( $cs['thieuNgay'] ) && false === $cs['co'] && 1 === $cs['saoKe'] );

echo "── 4. Webhook: cộng lẻ một giao dịch ───────────────────────────\n";
$truoc = $wpdb->tong(); SAOKE_App::$goi = array();
t( 'cộng 20.000 vào máy 80013 ngày 01 (đã có)', VHG_VietQR::cong_gd( '2026-09-01', 'AEON MALL TÂN PHÚ', '80013', 20000 ) );
$doc = VHG_VietQR::theo_may_ngay( '2026-09-01', '2026-09-01', false );
t( '🔴 = kho cũ + 20.000, đúng ô máy 80013, không đẻ dòng trùng, không gọi Sao Kê', 120000 === $doc['vq']['AEON MALL TÂN PHÚ']['80013']['2026-09-01'] && $wpdb->tong() === $truoc + 20000 && array() === SAOKE_App::$goi );
t( 'máy mới chưa có dòng → thêm dòng', VHG_VietQR::cong_gd( '2026-09-01', 'AEON MALL TÂN PHÚ', '80099', 5000 ) && 5000 === VHG_VietQR::theo_may_ngay( '2026-09-01', '2026-09-01', false )['vq']['AEON MALL TÂN PHÚ']['80099']['2026-09-01'] );
t( 'không rõ cơ sở → dồn vào dấu ngày (khongKhop 90.000 → 91.000)', VHG_VietQR::cong_gd( '2026-09-01', '', '', 1000 ) && 91000 === VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-01', false )['khongKhop'] );
$GLOBALS['NOW'] = '2026-09-25 09:30:00';
VHG_VietQR::cong_gd( '2026-09-01', 'VẠN HẠNH MALL', '80038', 1 );
t( 'cap_luc của ngày (dấu) nhích theo lần cộng cuối', '09:30 25/09/2026' === VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-01', false )['capLuc'] );
SAOKE_App::$goi = array(); SAOKE_App::$kq['2026-09-24'] = array( 'co' => true, 'vq' => array(), 'chuaMay' => array( 'VẠN HẠNH MALL' => array( '2026-09-24' => 777 ) ), 'khongKhop' => 0 );
t( '🔴 webhook về NGÀY CHƯA CÓ trong kho → không cộng lẻ mà kéo trọn ngày từ Sao Kê (kẻo kho chỉ giữ phần từ lúc cài)',
	VHG_VietQR::cong_gd( '2026-09-24', 'VẠN HẠNH MALL', '', 777 ) && array( '2026-09-24' ) === SAOKE_App::$goi && 777 === VHG_VietQR::theo_coso_ngay( '2026-09-24', '2026-09-24', false )['vq']['VẠN HẠNH MALL']['2026-09-24'] );
t( 'tiền ≤ 0 / ngày sai → bỏ, không nổ', false === VHG_VietQR::cong_gd( '2026-09-01', 'X', '', 0 ) && false === VHG_VietQR::cong_gd( 'hom nay', 'X', '', 5 ) );

echo "── 5. Kéo từng đợt cho màn hình ────────────────────────────────\n";
SAOKE_App::$goi = array();
$r = VHG_VietQR::dong_bo( '2026-09-01', '2026-09-05' );
t( 'chỉ kéo ngày CHƯA có (04, 05; 01–03 đã có)', array( '2026-09-04', '2026-09-05' ) === SAOKE_App::$goi && '' === $r['tiep'] && 2 === count( $r['xong'] ), array( SAOKE_App::$goi, $r ) );
SAOKE_App::$goi = array();
$r = VHG_VietQR::dong_bo( '2026-09-01', '2026-09-03', false );
t( 'ép kéo lại (↻) → cả ngày đã có cũng kéo', array( '2026-09-01', '2026-09-02', '2026-09-03' ) === SAOKE_App::$goi && 0 === $r['conLai'] );

echo "── 6. Gộp sổ: quên ngày mang tên cũ ────────────────────────────\n";
/* Sau mục 5 (ép kéo lại 01→03 từ Sao Kê giả — không có dữ liệu ngày 01) ngày 01 chỉ còn dấu = 0: đúng nghĩa
   "kho là số suy ra, kéo lại là đè" — VHM lúc này còn ở ngày 02 (80038) và 24 (chưa rõ máy). */
t( 'trước khi quên: ngày 01 chỉ còn dấu (ép kéo lại đã đè), 02 và 24 có VHM', 0 === VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-01', false )['khongKhop'] && array() === VHG_VietQR::theo_coso_ngay( '2026-09-01', '2026-09-01', false )['vq']
	&& isset( VHG_VietQR::theo_coso_ngay( '2026-09-02', '2026-09-02', false )['vq']['VẠN HẠNH MALL'] ) );
$n = VHG_VietQR::quen_coso( 'VANHANHMALL' );
t( '🔴 quên VHM → bỏ CẢ các ngày có VHM (02, 24) kể cả dấu ngày (4 dòng) → hai ngày ấy thành "thiếu", lượt Xem kế kéo lại',
	4 === $n && array( '2026-09-02' ) === VHG_VietQR::ngay_thieu( '2026-09-01', '2026-09-03' ) && array( '2026-09-24' ) === VHG_VietQR::ngay_thieu( '2026-09-24', '2026-09-24' ), array( $n, VHG_VietQR::ngay_thieu( '2026-09-01', '2026-09-03' ) ) );
t( 'ngày không dính VHM (01, 03, 04, 05) còn nguyên dấu', array() === VHG_VietQR::ngay_thieu( '2026-09-03', '2026-09-05' ) && array() === VHG_VietQR::ngay_thieu( '2026-09-01', '2026-09-01' ) );
t( 'khoá rỗng / không có → không xoá gì', 0 === VHG_VietQR::quen_coso( '' ) && 0 === VHG_VietQR::quen_coso( 'KHONGCO' ) );

echo "── 7. Dây nối trong mã nguồn ───────────────────────────────────\n";
$kt = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$tr = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );
$db = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-db.php' );
$mn = file_get_contents( __DIR__ . '/../../vhcp-ghe/vhcp-ghe.php' );
$my = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-may.php' );
$vq = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-vietqr.php' );
t( '🔴 Báo cáo tổng / MISA không còn gọi sang Sao Kê lúc Xem', 0 === substr_count( $kt, 'SAOKE_App::vietqr_theo_coso_ngay( $tu, $den )' ) && 0 === substr_count( $kt, 'SAOKE_App::vietqr_theo_may_ngay( $tu, $den )' ) && 0 === substr_count( $kt, "class_exists( 'SAOKE_App' )" ) );
t( 'mà đọc kho: theo_coso_ngay (vietqr_thuc_) + theo_may_ngay (nhánh từng ghế)', 1 === substr_count( $kt, 'VHG_VietQR::theo_coso_ngay( $tu, $den )' ) && 1 === substr_count( $kt, 'VHG_VietQR::theo_may_ngay( $tu, $den )' ) );
t( 'kt_bctong trả vqThieuNgay / vqCapLuc / vqSaoKe cho màn hình', false !== strpos( $kt, "'vqThieuNgay' =>" ) && false !== strpos( $kt, "'vqCapLuc' =>" ) && false !== strpos( $kt, "'vqSaoKe' =>" ) );
t( 'router kt_vqr_dongbo (sau cửa kt_ → chỉ Chốt / Quản lý / Admin) gọi VHG_VietQR::dong_bo', false !== strpos( $tr, 'if ( \'kt_vqr_dongbo\' === $viec ) {' ) && false !== strpos( $tr, 'VHG_VietQR::dong_bo(' ) && strpos( $tr, 'if ( \'kt_vqr_dongbo\' === $viec ) {' ) > strpos( $tr, 'if ( 0 === strpos( $viec, \'kt_\' ) ) {' ) );
t( '🔴 JS: bấm Xem → bctNapVq kéo từng đợt tới khi tiep rỗng rồi bctLoad() lại; có ↻ kéo lại', false !== strpos( $tr, 'bctNapVq(r, box);' ) && false !== strpos( $tr, 'if (k.tiep) { dot(k.tiep); return; }' ) && false !== strpos( $tr, 'function bctKeoLai(' ) );
t( 'JS không quay vòng vô hạn: không có Sao Kê thì nói thẳng, kéo 2 lượt vẫn thiếu thì dừng và kể ngày', false !== strpos( $tr, 'if (!r.vqSaoKe) {' ) && false !== strpos( $tr, 'if (BCT_NAP_LAN >= 2) {' ) );
t( 'bảng bc_vqr: UNIQUE (ngay,coso_key,ma_may) + KEY ngay', false !== strpos( $db, "\$b['bc_vqr'] = \"" ) && false !== strpos( $db, 'UNIQUE KEY ngay_cs_may (ngay,coso_key,ma_may)' ) );
t( 'vhcp-ghe.php nạp lớp trước ketoan', strpos( $mn, "includes/class-vhg-vietqr.php" ) < strpos( $mn, "includes/class-vhg-ketoan.php" ) );
t( 'gộp sổ cơ sở → quên kho theo khoá cũ (không đổi nhãn dòng suy ra)', false !== strpos( $my, 'VHG_VietQR::quen_coso( $kc )' ) );
t( 'không có Sao Kê → dong_bo nói thẳng, không kéo', false !== strpos( $vq, "'Chưa cài plugin Sao Kê" ) );
t( 'vân tay bản 2.142.0 ở 3 chỗ', 1 === substr_count( $mn, "Version:           2.142.0" ) && 1 === substr_count( $mn, "define( 'VHG_VERSION', '2.142.0' )" ) && 1 === substr_count( file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-baocao.php' ), "const BAN = '2.142.0';" ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
