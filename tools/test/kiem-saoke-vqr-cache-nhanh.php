<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.50.0 — BẢN ĐỒ CỬA HÀNG ĐỌC MỘT LẦN · LỌC NGÀY DÙNG CHỈ MỤC · ĐẨY SỐ VIETQR SANG KHO GHẾ
 *
 * Anh Thắng 25/09/2026: Báo cáo tổng bên Ghế "không nối được tới máy chủ" khi chọn 01→25/09 (12→25 thì
 * được) — *"khả năng đọc dữ liệu sao kê bị lỗi"*. Đúng: mỗi dòng cổng gọi vqr_may_theo_ma() hai lần, mỗi
 * lần get_option() giải tuần tự cả bản đồ 74KB, trượt khoá chính thì duyệt lại toàn bộ bằng regex. Đo:
 * 20.000 dòng = 2,2s (trúng) … 9,4s (trượt). Hai mươi lăm ngày là quá 30s PHP → PHP bị ngắt → status 0.
 *
 * Rồi anh đổi hướng: *"khi có dữ liệu thêm thì ghi vào máy, để cần đọc ngay, chứ sao kê nó đang quá tải
 * mà cứ gọi qua là lúc được lúc không"* — Sao Kê ĐẨY sang kho Ghế lúc webhook về, Ghế không gọi sang nữa.
 *
 * Bài này chạy thật các hàm đã bốc từ mã nguồn, với get_option giả ĐẾM SỐ LẦN GỌI.
 * Chạy: php tools/test/kiem-saoke-vqr-cache-nhanh.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$sk = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );

$GLOBALS['GO'] = 0; $GLOBALS['OPT'] = array();
function get_option( $k, $d = false ) { $GLOBALS['GO']++; return isset( $GLOBALS['OPT'][ $k ] ) ? unserialize( serialize( $GLOBALS['OPT'][ $k ] ) ) : $d; }   // như WP: giải tuần tự mỗi lần
$fs = '';
foreach ( array( 'private static function vqr_ds_ch(', 'private static function vqr_ma_tat_ca(', 'private static function vqr_ma_ch(', 'private static function vqr_may_theo_ma(',
	'private static function vqr_ch_quen_(', 'private static function moc_tu_(', 'private static function moc_den_(', 'private static function khoang_thoi_diem_(',
	'private static function ghe_kho_co_(', 'private static function day_ghe_dong_(' ) as $mo ) {
	$f = boc( $sk, $mo ); t( 'bốc ' . preg_replace( '/.*function /', '', $mo ), '' !== $f ); $fs .= "\n" . $f;
}
class VHG_VietQR {
	public static $goi = array(); public static $nem = false;
	public static function nhan_ngay( $n, $k ) {}
	public static function cong_gd( $ng, $cs, $ma, $tien ) { if ( self::$nem ) { self::$nem = false; throw new RuntimeException( 'kho hỏng' ); } self::$goi[] = array( $ng, $cs, $ma, $tien ); return true; }
}
eval( 'class SAOKE_App {
	private static $vqr_ch_cache = null, $vqr_ma_tat_ca_cache = null, $vqr_may_memo = array(), $vqr_diem_cache = null;
	public static $quy = array();
	private static function cong_ngay_mysql( $s ) { return $s; }
	private static function ds_anhxa( $n ) { return array(); }
	private static function ghe_map_may_ma() { return array(); }
	public static function vietqr_quy_dong_( $r, $a, $m ) { self::$quy[] = $r; return array( "ng" => $r["d"], "coso" => "CS", "ma" => "M", "tien" => $r["so_tien"] ); }
	public static function thu( $m ) { return self::vqr_may_theo_ma( $m ); }
	public static function quen() { self::vqr_ch_quen_(); }
	public static function tu( $s ) { return self::moc_tu_( $s ); }
	public static function den( $s ) { return self::moc_den_( $s ); }
	public static function khoang( $a, $b ) { return self::khoang_thoi_diem_( $a, $b ); }
	public static function day( $tx ) { return self::day_ghe_dong_( $tx ); }
	' . $fs . ' }' );

echo "── 1. Bản đồ đọc MỘT lần, nhớ theo mã ─────────────────────────\n";
$ds = array(); for ( $i = 0; $i < 400; $i++ ) { $ds[ 'MA' . str_pad( $i, 6, '0', STR_PAD_LEFT ) ] = array( 'ten' => 'CUA HANG ' . $i, 'maDiem' => 'VVB' . ( 800000 + $i ), 'tenDiem' => 'Diem ' . $i ); }
$GLOBALS['OPT']['saoke_vqr_ch'] = $ds; $GLOBALS['GO'] = 0;
t( 'trúng mã cửa hàng → tên máy', 'CUA HANG 7' === SAOKE_App::thu( 'MA000007' ) );
t( 'trượt mã CH, trúng mã ĐIỂM BÁN → vẫn ra tên (đường vqr_ma_tat_ca)', 'CUA HANG 9' === SAOKE_App::thu( 'VVB800009' ) );
t( 'mã lạ → rỗng, không bịa', '' === SAOKE_App::thu( 'KHONG-CO' ) );
t( 'mã rỗng / gạch ngang → rỗng', '' === SAOKE_App::thu( '' ) && '' === SAOKE_App::thu( '-' ) );
$t0 = microtime( true );
for ( $i = 0; $i < 20000; $i++ ) { SAOKE_App::thu( 'MA000' . str_pad( $i % 400, 3, '0', STR_PAD_LEFT ) ); SAOKE_App::thu( 'VVB' . ( 800000 + $i % 400 ) ); }
$dt = microtime( true ) - $t0;
t( '🔴 40.000 lượt hỏi mã → get_option chỉ gọi ĐÚNG 1 lần (trước 0.50.0: mỗi lượt một lần, 20.000 dòng mất 2–9s)', 1 === $GLOBALS['GO'], $GLOBALS['GO'] );
t( 'và xong dưới 1 giây (đo thật: ' . number_format( $dt, 3 ) . 's; bản cũ 2,2s–9,4s cho 20.000)', $dt < 1.0, $dt );
echo "── 2. Ghi option → quên cache, cùng lượt thấy bản mới ─────────\n";
$GLOBALS['OPT']['saoke_vqr_ch']['MOI001'] = array( 'ten' => 'MAY MOI', 'maDiem' => '', 'tenDiem' => '' );
t( 'chưa quên → chưa thấy mã mới (đúng nghĩa cache trong lượt)', '' === SAOKE_App::thu( 'MOI001' ) );
SAOKE_App::quen(); $go = $GLOBALS['GO'];
t( '🔴 quên xong → thấy mã mới, get_option gọi lại đúng 1 lần', 'MAY MOI' === SAOKE_App::thu( 'MOI001' ) && $GLOBALS['GO'] === $go + 1, array( SAOKE_App::thu( 'MOI001' ), $GLOBALS['GO'] - $go ) );
t( 'cả HAI chỗ ghi option bản đồ (nạp / xoá) đều quên cache ngay sau update_option',
	2 === preg_match_all( "/update_option\( 'saoke_vqr_ch', [^\n]*\n\t\tself::vqr_ch_quen_\(\);/", $sk ) );
t( 'memo trượt cũng được nhớ (mã lạ hỏi 2 lần không đọc lại bản đồ)', ( function () { $g = $GLOBALS['GO']; SAOKE_App::thu( 'LA-1' ); SAOKE_App::thu( 'LA-1' ); return $GLOBALS['GO'] === $g; } )() );

echo "── 3. Lọc ngày so THẲNG vào cột (chỉ mục thoi_diem dùng được) ──\n";
t( "moc_tu_('2026-09-01') = '2026-09-01 00:00:00'", '2026-09-01 00:00:00' === SAOKE_App::tu( '2026-09-01' ) );
t( "🔴 moc_den_('2026-09-25') = 00:00:00 NGÀY SAU (dùng với <, lấy trọn ngày cuối)", '2026-09-26 00:00:00' === SAOKE_App::den( '2026-09-25' ), SAOKE_App::den( '2026-09-25' ) );
t( 'cuối tháng / cuối năm nhảy đúng', '2026-10-01 00:00:00' === SAOKE_App::den( '2026-09-30' ) && '2027-01-01 00:00:00' === SAOKE_App::den( '2026-12-31' ) );
t( 'không phải Y-m-d thì trả nguyên (không phá lọc cũ)', 'abc' === SAOKE_App::tu( 'abc' ) && '' === SAOKE_App::den( '' ) );
t( 'khoang_thoi_diem_ trả cặp [từ, đến+1)', array( '2026-09-01 00:00:00', '2026-09-26 00:00:00' ) === SAOKE_App::khoang( '2026-09-01', '2026-09-25' ) );
t( '🔴 không còn câu SQL nào bọc DATE() lên cột để lọc', 0 === substr_count( $sk, "'DATE(thoi_diem)>=%s'" ) && 0 === substr_count( $sk, "'DATE(thoi_diem)<=%s'" ) && 0 === substr_count( $sk, 'DATE(thoi_diem) BETWEEN' ) );
t( 'bốn màn lọc theo khoảng dùng thoi_diem>=%s / thoi_diem<%s', 4 === substr_count( $sk, "'thoi_diem>=%s'" ) && 4 === substr_count( $sk, "'thoi_diem<%s'" ) );
t( 'báo cáo theo máy lọc thoi_diem >= %s AND thoi_diem < %s', 1 === substr_count( $sk, 'thoi_diem >= %s AND thoi_diem < %s' ) );

echo "── 4. Webhook về → cộng thẳng vào kho Ghế ─────────────────────\n";
$tx = array( 'nguon' => 'vietqr', 'docDuoc' => true, 'huong' => 'Đến', 'thoiDiem' => '2026-09-25 10:00:00', 'soTien' => 150000, 'noiDung' => 'VQR1 AMTP 01', 'diemBan' => '', 'maCH' => 'AMTP01' );
SAOKE_App::day( $tx );
t( '🔴 giao dịch VietQR mới → VHG_VietQR::cong_gd(ngày, cơ sở, máy, tiền) đúng một lần', 1 === count( VHG_VietQR::$goi ) && array( '2026-09-25', 'CS', 'M', 150000 ) === VHG_VietQR::$goi[0], VHG_VietQR::$goi );
t( 'đi qua đúng luật quy dòng vietqr_quy_dong_ (không chép luật)', 1 === count( SAOKE_App::$quy ) && 'AMTP01' === SAOKE_App::$quy[0]['ma_ch'] && '2026-09-25' === SAOKE_App::$quy[0]['d'] );
VHG_VietQR::$goi = array();
SAOKE_App::day( array_merge( $tx, array( 'huong' => 'Đi' ) ) );        t( 'tiền ĐI (hoàn) không đẩy', 0 === count( VHG_VietQR::$goi ) );
SAOKE_App::day( array_merge( $tx, array( 'docDuoc' => false ) ) );      t( 'dòng chưa đọc được không đẩy', 0 === count( VHG_VietQR::$goi ) );
SAOKE_App::day( array_merge( $tx, array( 'nguon' => 'momo' ) ) );       t( 'nguồn khác VietQR không đẩy', 0 === count( VHG_VietQR::$goi ) );
SAOKE_App::day( array_merge( $tx, array( 'thoiDiem' => '' ) ) );        t( 'không có ngày → không đẩy, không nổ', 0 === count( VHG_VietQR::$goi ) );
VHG_VietQR::$nem = true; $ok = true;
try { SAOKE_App::day( $tx ); } catch ( Throwable $e ) { $ok = false; }   // (dòng error_log in ra stderr là đúng ý — không phải lỗi bài)
t( '🔴 kho Ghế nổ → nuốt tại chỗ (error_log), webhook vẫn được trả lời', $ok && false === VHG_VietQR::$nem );
t( 'sau đó vẫn đẩy bình thường', ( function () use ( $tx ) { VHG_VietQR::$goi = array(); SAOKE_App::day( $tx ); return 1 === count( VHG_VietQR::$goi ); } )() );

echo "── 5. Các cửa đổi số đều đẩy lại kho ─────────────────────────\n";
$wh = boc( $sk, 'private static function cong_nhan_webhook(' );
t( 'cong_nhan_webhook: dòng MỚI (luu_cong = true) mới đẩy', 1 === substr_count( $wh, '{ $moi++; self::day_ghe_dong_( $tx ); }' ) );
t( 'rpc_ganMayTay: đọc ngày của giao dịch rồi tính lại ngày ấy', false !== strpos( boc( $sk, 'public static function rpc_ganMayTay(' ), "self::day_ghe_ngay_( (string) \$row['d'] )" ) );
$nf = boc( $sk, 'public static function rpc_napFileCongTx(' );
t( 'rpc_napFileCongTx: ghi dấu từng ngày đụng tới (kể cả dòng trùng được VÁ ma_ch) rồi đẩy một lần cuối lượt', false !== strpos( $nf, 'self::ghe_dau_ngay_( $mysql )' ) && false !== strpos( $nf, 'self::day_ghe_ngay_don_();' ) );
t( 'nạp từ Google Sheet (2 vòng) cũng ghi dấu + đẩy cuối lượt', 2 === substr_count( $sk, "self::ghe_dau_ngay_( self::cong_ngay_mysql( \$tx['thoiDiem'] ) );" ) && 3 === substr_count( $sk, 'self::day_ghe_ngay_don_();' ) );
t( 'đổi bản đồ cửa hàng (nạp / xoá) + đổi ánh xạ (3 chỗ) → tính lại 7 ngày gần', 5 === substr_count( $sk, 'self::day_ghe_gan_day_( 7 )' ) );
t( 'không có lớp VHG_VietQR (Ghế cũ) thì mọi đường đẩy im (ghe_kho_co_)', 4 === substr_count( $sk, 'if ( ! self::ghe_kho_co_() )' ) );   // day_ghe_dong_ · day_ghe_ngay_ · day_ghe_gan_day_ · day_ghe_danh_dau_ (0.53.0)

echo "── 6. Vân tay bản ───────────────────────────────────────────────\n";
preg_match( '/^ \* Version:\s+([0-9.]+)/m', $sk, $m1 ); preg_match( "/const VER = '([0-9.]+)';/", $sk, $m2 );
t( 'header Version == const VER, từ 0.50.0 trở lên', isset( $m1[1], $m2[1] ) && $m1[1] === $m2[1] && version_compare( $m1[1], '0.50.0', '>=' ), array( $m1[1] ?? null, $m2[1] ?? null ) );
t( 'can_pin 36 chỗ (0.51.0 thêm taoCoSoGhe; không mở cửa RPC nào không PIN)', 36 === substr_count( $sk, 'self::can_pin(' ) );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
