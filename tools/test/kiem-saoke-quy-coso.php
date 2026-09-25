<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ — QUY CƠ SỞ CHO DÒNG CỔNG: THA SỐ 0 ĐỆM · THA ĐUÔI TỈNH · ĐỐI CHIẾU MÃ CỬA HÀNG
 *
 * Anh Thắng 24/09/2026: *"bóc sai địa điểm mã cửa hàng của anh rồi"* (GO AC 03 → GO TRƯỜNG CHINH, mã
 * cửa hàng nói GO ÂU CƠ 03) và *"QR trên ghế đang báo không có"* (GO BT 08 ↔ ghế GO-BT-8, GO BẾN TRE).
 *
 * 🔴 CẢ HAI ĐỀU CÂM: bảng Sao Kê vẫn HIỆN một cái tên, nên trông như đã khớp; chỉ bên Ghế mới lộ ra
 *    "VietQR –" hoặc cơ sở khác nhận tiền. Bài này chạy thật ba hàm quy cơ sở với bản đồ giả.
 *
 * Chạy: php tools/test/kiem-saoke-quy-coso.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$sk = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$fs = ''; foreach ( array( 'private static function ghe_coso_cua_may(', 'private static function ghe_coso_chuan(', 'private static function cong_coso_dong(', 'public static function chuan_may(', 'public static function chuan_ch_long(', 'private static function cong_coso(', 'public static function bo_duoi_hieu_(', 'public static function bo_hieu_(' ) as $mo ) { $f = boc( $sk, $mo ); t( 'bốc ' . preg_replace( '/.*function /', '', $mo ), '' !== $f ); $fs .= "\n" . $f; }
eval( 'class SAOKE_App { public static $mapMa = array(), $map = array(), $ds = array(), $ch = array();
	public static function kd( $s ) { $s = mb_strtolower( (string) $s, "UTF-8" ); $s = str_replace( array( "ế","ề","ể","ễ","ệ","ê","é","è","ẻ","ẽ","ẹ","ơ","ớ","ờ","ở","ỡ","ợ","ô","ố","ồ","ổ","ỗ","ộ","ó","ò","ỏ","õ","ọ","â","ấ","ầ","ẩ","ẫ","ậ","ă","ắ","ằ","ẳ","ẵ","ặ","á","à","ả","ã","ạ","ư","ứ","ừ","ử","ữ","ự","ú","ù","ủ","ũ","ụ","í","ì","ỉ","ĩ","ị","ý","ỳ","ỷ","ỹ","ỵ","đ" ), array( "e","e","e","e","e","e","e","e","e","e","e","o","o","o","o","o","o","o","o","o","o","o","o","o","o","o","o","o","a","a","a","a","a","a","a","a","a","a","a","a","a","a","a","a","a","u","u","u","u","u","u","u","u","u","u","u","i","i","i","i","i","y","y","y","y","y","d" ), $s ); return preg_replace( "/[^a-z0-9 ]/", "", $s ); }
	public static function chuan_ch( $s ) { return preg_replace( "/[^a-z0-9]/", "", self::kd( $s ) ); }
	private static function ghe_co() { return true; }
	private static function ghe_map_may() { return self::$map; }
	private static function ghe_map_may_ma() { return self::$mapMa; }
	private static function ghe_ds_coso() { return self::$ds; }
	private static function ds_coso_all() { return self::$ds; }
	private static function vqr_may_theo_ma( $m ) { $k = strtoupper( trim( (string) $m ) ); return isset( self::$ch[ $k ] ) ? self::$ch[ $k ] : ""; }
	private static function vqr_diem_theo_ma_( $m ) { return ""; }   // 0.51.0: bài này không có điểm bán
	public static function thu( $t, $m = "", $ax = null, $tay = "" ) { return self::cong_coso_dong( $t, $m, $ax, $tay ); }
	public static function thu_chuan( $t ) { return self::ghe_coso_chuan( $t ); }
	' . $fs . ' }' );
SAOKE_App::$ds = array( array( 'ten' => 'GO BẾN TRE', 'biDanh' => '' ), array( 'ten' => 'GO ÂU CƠ', 'biDanh' => '' ), array( 'ten' => 'GO TRƯỜNG CHINH', 'biDanh' => '' ) );
SAOKE_App::$mapMa = array( 'gobt8' => array( 'ma' => '80811', 'coso' => 'GO BẾN TRE', 'trung' => false ) );   // ghế GO-BT-8
SAOKE_App::$map = array();
SAOKE_App::$ch = array( 'VCD7HWKFAM' => 'GO ÂU CƠ 03' );

echo "── Lỗ 1: số 0 đệm ──────────────────────────────────────────────\n";
$r = SAOKE_App::thu( 'GO BT 08' );
t( '🔴 "GO BT 08" (cổng) → ghế "GO-BT-8" → GO BẾN TRE, nguồn ghe-may', 'GO BẾN TRE' === $r['coso'] && 'ghe-may' === $r['nguon'], $r );
echo "── Lỗ 2: đuôi tỉnh ─────────────────────────────────────────────\n";
t( '🔴 "GO BẾN TRE — Bến Tre" (tên ánh xạ) → cơ sở "GO BẾN TRE" qua khoá lỏng', 'GO BẾN TRE' === SAOKE_App::thu_chuan( 'GO BẾN TRE — Bến Tre' ) );
t( 'khoá khít vẫn thắng', 'GO BẾN TRE' === SAOKE_App::thu_chuan( 'go ben tre' ) );
echo "── Lỗ 3: mã cửa hàng là nhân chứng thứ hai ─────────────────────\n";
$axSai = array( 'tenChuan' => 'GO TRƯỜNG CHINH', 'maBank' => 'KH705MTDMN0009' );   // ánh xạ tay gõ sai
$r = SAOKE_App::thu( 'GO AC 03', 'VCD7HWKFAM', $axSai );
t( '🔴 GO AC 03: ánh xạ tay nói TRƯỜNG CHINH, mã cửa hàng nói GO ÂU CƠ → lấy GO ÂU CƠ, cờ mâu thuẫn, kể bên thua',
	'GO ÂU CƠ' === $r['coso'] && 1 === $r['xungDot'] && 'GO TRƯỜNG CHINH' === $r['coSoKhac'] && 'ma-ch' === $r['nguon'], $r );
$r = SAOKE_App::thu( 'GO AC 03', '', $axSai );
t( 'không có mã cửa hàng → theo ánh xạ như cũ, không cờ', 'GO TRƯỜNG CHINH' === $r['coso'] && 0 === $r['xungDot'] );
$r = SAOKE_App::thu( 'GO BT 08', 'VCD7HWKFAM' );
t( '🔴 tên máy khớp THẲNG ghế thắng mã cửa hàng khi hai bên khác nhau — nhưng vẫn cờ mâu thuẫn', 'GO BẾN TRE' === $r['coso'] && 1 === $r['xungDot'] && 'GO ÂU CƠ' === $r['coSoKhac'], $r );
$r = SAOKE_App::thu( 'XYZ 9', 'VCD7HWKFAM' );
t( 'nội dung không ra gì → mã cửa hàng cứu: GO ÂU CƠ, nguồn ma-ch', 'GO ÂU CƠ' === $r['coso'] && 'ma-ch' === $r['nguon'] );
$r = SAOKE_App::thu( 'GO AC 03', 'VCD7HWKFAM', $axSai, 'GO AC 03' );
t( '🔴 gán máy TAY không bị mã cửa hàng đè (quyết định của người)', 'GO TRƯỜNG CHINH' === $r['coso'] && 'tay' === $r['nguon'] && 0 === $r['xungDot'], $r );
echo "── Bảng cổng nói thật ──────────────────────────────────────────\n";
t( 'không quy được cơ sở Ghế thì in "⚠ … (chưa quy được cơ sở Ghế)", không hiện tên ánh xạ trần', false !== strpos( $sk, "(chưa quy được cơ sở Ghế)" ) );
/* 0.50.0: còn HAI chỗ gọi — vietqr_quy_dong_() (luật quy một dòng, dùng chung cho hai báo cáo VietQR của Ghế
   LẪN đẩy webhook sang kho Ghế) và bảng Sao Kê cổng. vietqr_theo_coso_ngay() nay SUY từ bản theo máy, không
   còn vòng lặp riêng — bớt một chỗ gọi là bớt một bản chép của luật, đúng hướng §6. */
t( 'ba chỗ gọi cong_coso_dong: vietqr_quy_dong_ (báo cáo + đẩy webhook), bảng cổng, nạp bù (0.51.0: "chưa gán" = không quy được) — một chỗ quyết định', 3 === substr_count( $sk, 'self::cong_coso_dong(' ) );
t( '0.50.0: cả hai báo cáo VietQR của Ghế đi qua vietqr_quy_dong_ (theo máy gọi, theo cơ sở suy từ theo máy)',
	1 === substr_count( $sk, 'self::vietqr_quy_dong_( $r, $anhXa, $mapMa )' ) && 1 === substr_count( $sk, '$m = self::vietqr_theo_may_ngay( $tu, $den );' ) );
echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
