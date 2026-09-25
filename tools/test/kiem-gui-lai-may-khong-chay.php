<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GỬI LẠI MÀ MÁY KHÔNG CHẠY = SỬA LẦN GẦN NHẤT, KHÔNG PHẢI THU LẦN NỮA (anh Thắng 25/09/2026)
 *
 * Ảnh màn Duyệt: CGV-CT-01 ba dòng một ngày (215→234 · 234→234 · 234→234), hai dòng sau Actual 0 mà
 * "Thực thu ghi đè" 160.000 vẫn ghi → tổng ngày cộng đôi (920.000 thay vì 460.000). Nhân viên gửi lại để
 * sửa tiền; luật "mỗi lượt Gửi = một lần thu mới" chèn lần mới; chỉ số nối nên Actual 0 nhưng Thực thu
 * và QR là số gõ tay, không tự triệt tiêu.
 *
 * 🔴 LUẬT MỚI: ghế gửi lại với chỉ số sau ĐÚNG BẰNG chỉ số sau đã lưu gần nhất trong ngày → ĐÈ lên dòng
 *    ấy (tiền mặt / QR / ghi chú / ảnh), giữ chỉ số + Actual, ghi bc_undo. Chỉ số khác → lần mới như cũ.
 *    Dòng đã nộp tiền / đính bill → lỗi chỉ đường, không lặng lẽ tạo lần mới.
 *
 * Chạy: php tools/test/kiem-gui-lai-may-khong-chay.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
define( 'ARRAY_A', 'ARRAY_A' );
function current_time( $f ) { return 'mysql' === $f ? '2026-09-25 10:15:00' : ( 'H:i' === $f ? '10:15' : ( 'Y-m-d' === $f ? '2026-09-25' : '2026-09-25 10:15:00' ) ); }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
class VHG_DB { public static function t( $b ) { return 'wp_vhg_' . $b; } }

/* CSDL giả: bc (header) + bc_dong; hiểu câu JOIN "dòng gần nhất của ghế trong ngày". */
class WpdbGia {
	public $bc = array(); public $dong = array(); public $undo = array(); public $sql = array();
	public function prepare( $q, ...$a ) { foreach ( $a as $v ) { $q = preg_replace( '/%s|%d/', is_int( $v ) ? (string) $v : "'" . $v . "'", $q, 1 ); } return $q; }
	private function hd_( $rid ) { foreach ( $this->bc as $h ) { if ( $h['report_id'] === $rid ) { return $h; } } return null; }
	public function get_row( $q, $o = null ) { $this->sql[] = $q;
		if ( preg_match( "/h\.coso_key='([^']*)' AND d\.ngay='([^']*)' AND d\.ma_may='([^']*)'/", $q, $m ) ) {
			$ung = array();
			foreach ( $this->dong as $d ) { $h = $this->hd_( $d['report_id'] );
				if ( $h && $h['coso_key'] === $m[1] && $d['ngay'] === $m[2] && $d['ma_may'] === $m[3] && null !== $d['chi_so_sau'] ) {
					$ung[] = $d + array( 'h_lan' => $h['lan'], 'h_tao_luc' => $h['tao_luc'], 'h_bill_luc' => $h['bill_luc'], 'h_nop_id' => $h['nop_id'] ); } }
			usort( $ung, function ( $a, $b ) { return ( $b['lan'] - $a['lan'] ) ?: ( $b['id'] - $a['id'] ); } );
			return $ung ? $ung[0] : null; }
		return null; }
	public function get_results( $q, $o = null ) { $this->sql[] = $q;
		if ( preg_match( "/FROM wp_vhg_bc_dong WHERE report_id='([^']*)'/", $q, $m ) ) { $ra = array(); foreach ( $this->dong as $d ) { if ( $d['report_id'] === $m[1] ) { $ra[] = $d; } } return $ra; }
		return array(); }
	public function insert( $t, $d ) { $this->sql[] = 'INSERT ' . $t; if ( 'wp_vhg_bc_undo' === $t ) { $this->undo[] = $d; } return 1; }
	public function update( $t, $d, $w ) { $this->sql[] = 'UPDATE ' . $t . ' ' . json_encode( $d, JSON_UNESCAPED_UNICODE ) . ' WHERE ' . json_encode( $w );
		if ( 'wp_vhg_bc_dong' === $t ) { foreach ( $this->dong as $i => $r ) { if ( isset( $w['id'] ) && (int) $r['id'] === (int) $w['id'] ) { $this->dong[ $i ] = array_merge( $r, $d ); } } }
		if ( 'wp_vhg_bc' === $t ) { foreach ( $this->bc as $i => $r ) { if ( isset( $w['report_id'] ) && $r['report_id'] === $w['report_id'] ) { $this->bc[ $i ] = array_merge( $r, $d ); } } }
		return 1; }
	public function dong( $id ) { foreach ( $this->dong as $d ) { if ( (int) $d['id'] === (int) $id ) { return $d; } } return null; }
}

$src = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-baocao.php' );
$fs = ''; foreach ( array( 'private static function gui_lai_de_(', 'private static function nop_lai_header_(', 'private static function doc_payment_(', 'private static function chia_nop_(' ) as $mo ) { $f = boc( $src, $mo ); t( 'bốc ' . trim( str_replace( array( 'private static function ', '(' ), '', $mo ) ), '' !== $f ); $fs .= "\n" . $f; }
eval( 'class VHG_BaoCao {
	public static function squash( $s ) { return strtoupper( preg_replace( "/[^A-Za-z0-9]/", "", (string) $s ) ); }
	public static function so_chiso_( $v ) { return ( "" === $v || null === $v ) ? null : (float) str_replace( ",", ".", (string) $v ); }
	public static function cs_hien_( $v ) { return rtrim( rtrim( number_format( (float) $v, 1, ",", "." ), "0" ), "," ); }
	public static function ngay_( $v ) { return (string) $v; }
	private static function songuyen_( $v ) { return ( "" === $v || null === $v ) ? null : (int) $v; }
	public static function trong_pham_vi( $q, $coso, $ma = "" ) { return "NGOAI" !== $ma; }
	private static function luu_anh_( $a, $rid, $ten ) { return "http://x/" . $ten . ".jpg"; }
	public static function thu( $rows, $q, $coso, $ngay ) { return self::gui_lai_de_( $rows, $q, $coso, $ngay ); }
	public static function thu_nop( $rids, $pm ) { return self::nop_lai_header_( $rids, $pm ); }
	' . $fs . ' }' );

$Q = array( 'ten' => 'Nguyễn Văn A' ); $CS = 'CGV CAN THO'; $CK = 'CGVCANTHO'; $NG = '2026-09-24';
function du_lieu_() { global $CK; return array(
	'bc' => array( array( 'report_id' => 'R1', 'coso_key' => $CK, 'ngay' => '2026-09-24', 'lan' => 1, 'tao_luc' => '2026-09-24 20:02:00', 'bill_luc' => '', 'nop_id' => 0,
		'nop_hinhthuc' => 'cash', 'nop_trang_thai' => 'paid_cash', 'nop_so_tien' => 0 ) ),
	'dong' => array(
		array( 'id' => 1, 'report_id' => 'R1', 'ma_may' => 'CGV-CT-01', 'ten' => 'CGV-CT-01', 'ngay' => '2026-09-24', 'lan' => 1, 'chi_so_truoc' => 215, 'chi_so_sau' => 234, 'actual' => 190000, 'tien_mat' => 0, 'qr' => 0, 'dieu_chinh' => 0, 'tong' => 0, 'ghi_chu' => 'Thực thu ghi đè: 0đ', 'nop_so_tien' => 0, 'nop_trang_thai' => 'unpaid', 'anh' => '["http://x/a.jpg"]' ),
		array( 'id' => 2, 'report_id' => 'R1', 'ma_may' => 'CGV-CT-02', 'ten' => 'CGV-CT-02', 'ngay' => '2026-09-24', 'lan' => 1, 'chi_so_truoc' => 245, 'chi_so_sau' => 299, 'actual' => 540000, 'tien_mat' => 300000, 'qr' => 240000, 'dieu_chinh' => 0, 'tong' => 540000, 'ghi_chu' => '', 'nop_so_tien' => 300000, 'nop_trang_thai' => 'paid', 'anh' => '' ) ) ); }
function wpdb_moi_() { global $wpdb; $d = du_lieu_(); $wpdb = new WpdbGia(); $wpdb->bc = $d['bc']; $wpdb->dong = $d['dong']; return $wpdb; }

/* ══════ 1. Ca thật: gửi lại cùng chỉ số, sửa Thực thu ══════ */
echo "── 1. Gửi lại CGV-CT-01 234→234 với Thực thu 160.000 ───────────────\n";
wpdb_moi_();
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterBefore' => 215, 'meterAfter' => '234', 'qr' => 0, 'actualOverride' => 160000, 'note' => 'hoàn khách' ) ), $Q, $CS, $NG );
$d1 = $wpdb->dong( 1 );
t( '🔴 KHÔNG tạo lần mới: ghế về da_de, con_lai rỗng; nhớ report_id/lần/giờ của dòng bị đè', array( 'CGV-CT-01' ) === $r['da_de'] && array() === $r['con_lai'] && array( 'R1' ) === $r['rids'] && 1 === $r['lan'] && '20:02' === $r['luc'], $r );
t( '🔴 dòng cũ nhận tiền mặt MỚI 160.000, tổng 160.000; chỉ số 215→234 và Actual 190.000 GIỮ NGUYÊN', 160000 === $d1['tien_mat'] && 160000 === $d1['tong'] && 215 === $d1['chi_so_truoc'] && 234 === $d1['chi_so_sau'] && 190000 === $d1['actual'], $d1 );
t( 'ghi chú: có dấu "↩ Gửi lại 10:15 (… đè lần 1)", giữ "hoàn khách", một dấu "Thực thu ghi đè: 160.000đ"', 0 === strpos( $d1['ghi_chu'], '↩ Gửi lại 10:15 (máy không chạy thêm, đè lần 1) · hoàn khách · Thực thu ghi đè: 160.000đ' ) && 1 === substr_count( $d1['ghi_chu'], 'Thực thu ghi đè' ), $d1['ghi_chu'] );
t( '🔴 ghi bc_undo bản cũ (tiền mặt 0) với khoá R1·CGV-CT-01, ghi rõ ai/vì sao', 1 === count( $wpdb->undo ) && 'R1·CGV-CT-01' === $wpdb->undo[0]['ly_do'] && false !== strpos( $wpdb->undo[0]['chi_tiet'], '"tien_mat":0' ) && false !== strpos( $wpdb->undo[0]['boi'], 'gửi lại' ), $wpdb->undo );
t( 'dieu_chinh = 160000 (dấu ghi đè), ảnh cũ giữ', 160000 === $d1['dieu_chinh'] && '["http://x/a.jpg"]' === $d1['anh'] );
t( 'nop_so_tien không đổi vì dòng cũ là unpaid (không "nộp đủ đúng số cũ")', 0 === $d1['nop_so_tien'] );
/* gửi lại lần nữa y hệt → không phình ghi chú */
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '234', 'qr' => 0, 'actualOverride' => 160000, 'note' => $wpdb->dong( 1 )['ghi_chu'] ) ), $Q, $CS, $NG );
$d1 = $wpdb->dong( 1 );
t( 'gửi lại lần ba với ghi chú cũ → vẫn đè, ghi chú KHÔNG phình (một "↩ Gửi lại", một "Thực thu ghi đè")', 1 === substr_count( $d1['ghi_chu'], '↩ Gửi lại' ) && 1 === substr_count( $d1['ghi_chu'], 'Thực thu ghi đè' ) && 160000 === $d1['tien_mat'], $d1['ghi_chu'] );

/* ══════ 2. Chỉ số nhích → lần mới như cũ ══════ */
echo "── 2. Chỉ số khác → không đè ────────────────────────────────────────\n";
wpdb_moi_();
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '240', 'qr' => 0, 'actualOverride' => null, 'note' => '' ) ), $Q, $CS, $NG );
t( '🔴 234→240 là thu thật: về con_lai, không đè, không undo', array() === $r['da_de'] && 1 === count( $r['con_lai'] ) && 0 === count( $wpdb->undo ) && 0 === $wpdb->dong( 1 )['tien_mat'] );
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-09', 'meterAfter' => '10', 'qr' => 0 ) ), $Q, $CS, $NG );
t( 'ghế chưa có dòng nào trong ngày → con_lai', array() === $r['da_de'] && 1 === count( $r['con_lai'] ) );
t( 'ghế ngoài phạm vi / thiếu chỉ số sau → con_lai (luu() tự lọc sau)', 2 === count( VHG_BaoCao::thu( array( array( 'chairCode' => 'NGOAI', 'meterAfter' => '234' ), array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '' ) ), $Q, $CS, $NG )['con_lai'] ) );

/* ══════ 3. Hỗn hợp ══════ */
echo "── 3. Một ghế đè, một ghế lần mới ──────────────────────────────────\n";
wpdb_moi_();
$r = VHG_BaoCao::thu( array(
	array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '234', 'qr' => 0, 'actualOverride' => 160000, 'note' => '' ),
	array( 'chairCode' => 'CGV-CT-02', 'meterAfter' => '310', 'qr' => 0, 'actualOverride' => null, 'note' => '' ) ), $Q, $CS, $NG );
t( '🔴 CT-01 đè (da_de), CT-02 sang lần mới (con_lai)', array( 'CGV-CT-01' ) === $r['da_de'] && 1 === count( $r['con_lai'] ) && 'CGV-CT-02' === $r['con_lai'][0]['chairCode'] );

/* ══════ 4. Chốt an toàn ══════ */
echo "── 4. Đã nộp / đính bill / QR lớn hơn Actual ───────────────────────\n";
wpdb_moi_(); $wpdb->bc[0]['bill_luc'] = '2026-09-24 21:00:00';
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '234', 'qr' => 0, 'actualOverride' => 160000 ) ), $Q, $CS, $NG );
t( '🔴 báo cáo đã đính bill → LỖI chỉ đường (không đè, không tạo lần mới)', '' !== $r['loi'] && false !== strpos( $r['loi'], 'đã nộp tiền / đính bill' ) && 0 === $wpdb->dong( 1 )['tien_mat'], $r['loi'] );
wpdb_moi_(); $wpdb->bc[0]['nop_id'] = 55;
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '234', 'qr' => 0, 'actualOverride' => 160000 ) ), $Q, $CS, $NG );
t( 'báo cáo đã có lượt nộp (nop_id) → LỖI', '' !== $r['loi'] );
wpdb_moi_();
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '234', 'qr' => 250000, 'actualOverride' => null ) ), $Q, $CS, $NG );
t( '🔴 QR 250.000 > Actual 190.000 mà không Thực thu → tiền mặt ÂM → LỗI, dòng cũ nguyên', false !== strpos( $r['loi'], 'ÂM' ) && 0 === $wpdb->dong( 1 )['tien_mat'], $r['loi'] );
wpdb_moi_();
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-01', 'meterAfter' => '234', 'qr' => 50000, 'actualOverride' => null, 'note' => '' ) ), $Q, $CS, $NG );
$d1 = $wpdb->dong( 1 );
t( 'không Thực thu → tiền mặt = Actual đã lưu − QR mới = 140.000; tổng 190.000; không dấu ghi đè', 140000 === $d1['tien_mat'] && 190000 === $d1['tong'] && 50000 === $d1['qr'] && 0 === $d1['dieu_chinh'] && false === strpos( $d1['ghi_chu'], 'Thực thu ghi đè' ), $d1 );

/* ══════ 5. Nộp đủ đúng số cũ → số nộp đi theo; ảnh mới nối vào ══════ */
echo "── 5. Số nộp đi theo · ảnh nối thêm ────────────────────────────────\n";
wpdb_moi_();
$r = VHG_BaoCao::thu( array( array( 'chairCode' => 'CGV-CT-02', 'meterAfter' => '299', 'qr' => 240000, 'actualOverride' => 250000, 'note' => '', 'images' => array( 'chiso' => 'data:x' ) ) ), $Q, $CS, $NG );
$d2 = $wpdb->dong( 2 );
t( '🔴 CT-02 đã "nộp đủ" 300.000 → ghi đè 250.000 thì nop_so_tien theo 250.000', 250000 === $d2['tien_mat'] && 250000 === $d2['nop_so_tien'] && 490000 === $d2['tong'], $d2 );
t( 'ảnh mới nối vào cột anh', false !== strpos( (string) $d2['anh'], 'guilai-chiso' ) );

/* ══════ 6. Khai nộp lại cho header khi cả lượt là gửi lại ══════ */
echo "── 6. nop_lai_header_ ───────────────────────────────────────────────\n";
wpdb_moi_(); $wpdb->dong[0]['tien_mat'] = 160000;
VHG_BaoCao::thu_nop( array( 'R1' ), array( 'method' => 'transfer', 'amount' => '', 'note' => 'CK VCB' ) );
$h = $wpdb->bc[0];
t( '🔴 header: hình thức chuyển khoản, trạng thái paid_transfer, số nộp = tổng tiền mặt mới 460.000, ghi chú', 'transfer' === $h['nop_hinhthuc'] && 'paid_transfer' === $h['nop_trang_thai'] && 460000 === $h['nop_so_tien'] && 'CK VCB' === $h['nop_ghichu'], $h );
t( 'từng dòng rải lại nộp: CT-01 160.000 paid, CT-02 300.000 paid', 160000 === $wpdb->dong( 1 )['nop_so_tien'] && 'paid' === $wpdb->dong( 1 )['nop_trang_thai'] && 300000 === $wpdb->dong( 2 )['nop_so_tien'] );

/* ══════ 7. Nối vào luu(), màn kế toán, màn nhân viên ══════ */
echo "── 7. Đường nối ─────────────────────────────────────────────────────\n";
$luu = boc( $src, 'public static function luu( $payload, $pin ) {' );
$i_de = strpos( $luu, 'self::gui_lai_de_(' ); $i_rows = strpos( $luu, '$rows = array();' ); $i_lan = strpos( $luu, 'COALESCE(MAX(lan),0)+1' );
t( '🔴 luu() gọi gui_lai_de_ TRƯỚC khi dựng hàng và trước khi cấp lần mới', false !== $i_de && $i_de < $i_rows && $i_rows < $i_lan );
t( 'cả lượt là gửi lại → trả updated=true, khai nộp lại header, nối mốc, KHÔNG chèn lần', false !== strpos( $luu, "'updated' => true" ) && false !== strpos( $luu, 'self::nop_lai_header_(' ) && 1 === substr_count( $luu, 'COALESCE(MAX(lan),0)+1' ) );
t( 'gửi hỗn hợp: thông báo kể ghế đã cập nhật vào lần trước', false !== strpos( $luu, 'đã CẬP NHẬT vào lần trước, không tạo lần mới' ) );
$kt = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-ketoan.php' );
$ct = boc( $kt, 'public static function chi_tiet( $coso, $ngay ) {' );
t( '🔴 chi_tiet() kèm lần thu, giờ gửi, người gửi và cờ nghi trùng cho từng dòng; sắp theo ghế rồi lần', false !== strpos( $ct, 'h.lan AS h_lan, h.tao_luc AS h_luc, h.nhan_vien AS h_nv' ) && false !== strpos( $ct, "'lan' => (int) \$r['h_lan'], 'soLan' => \$so_lan, 'guiLuc'" ) && false !== strpos( $ct, "'trungNghi' => \$trung_nghi" ) && false !== strpos( $ct, "(int) \$a['h_lan'] - (int) \$b['h_lan']" ) );
t( 'cờ nghi trùng = ghế có ≥2 dòng, chỉ số đứng, còn tiền mặt hoặc QR', false !== strpos( $ct, "\$dem_ghe[ (string) \$r['ma_may'] ] > 1 && \$dung && ( (int) \$r['tien_mat'] > 0 || (int) \$r['qr'] > 0 )" ) );
$tr = file_get_contents( __DIR__ . '/../../vhcp-ghe/includes/class-vhg-trang.php' );
$gui = boc( $tr, 'function guiBaoCao(){' );
t( '🔴 màn nhân viên: gửi xong nạp lại bảng (selectLoc) — không còn bộ số cũ để bấm Gửi lần hai', false !== strpos( $gui, 'selectLoc(LOC);' ) && strpos( $gui, 'selectLoc(LOC);' ) > strpos( $gui, "goi('bc_submit'" ) );
$row = boc( $tr, 'function ktdRow(o,c,m,reload,locked){' );
t( 'màn Duyệt: hiện "lần N · gửi HH:MM · ai" khi ngày có ≥2 lần; cờ đỏ NGHI TRÙNG', false !== strpos( $row, 'c.soLan>1 && c.lan' ) && false !== strpos( $row, 'c.trungNghi' ) && false !== strpos( $row, 'NGHI TRÙNG' ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
