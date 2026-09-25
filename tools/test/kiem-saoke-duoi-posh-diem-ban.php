<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.51.0 — CHỮ HIỆU "POSH" KHÔNG PHẢI TÊN MÁY · TÊN ĐIỂM BÁN LÀ CẤP CƠ SỞ · HAI NÚT GÁN / TẠO
 *
 * Anh Thắng 25/09/2026 gửi store_export + transactions của tài khoản VietQR thứ hai: 989/1666 cửa hàng đặt tên
 * "AE Huế 04 Posh", điểm bán "POSH Aeon Mall Huế". Màn nạp bù liệt kê "Cơ sở chưa gán mã (473)" — mỗi MÁY một
 * dòng, vì đuôi "Posh" làm cong_coso() không cắt được số, và chuan_may() ra `aehp01posh` ≠ `aehp1`. Anh hỏi
 * *"Nếu chưa gán anh tạo cửa hàng mới được không"* → *"Làm luôn 2 nút đó đi em"*.
 *
 * Chạy: php tools/test/kiem-saoke-duoi-posh-diem-ban.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; echo "  ✓ $ten\n"; return; } $TRUOT[] = $ten; echo "  ✗ $ten" . ( null !== $them ? ( ' → ' . var_export( $them, true ) ) : '' ) . "\n"; }
function boc( $src, $mo ) { $i = strpos( $src, $mo ); if ( false === $i ) { return ''; } $d = 0; $n = strlen( $src ); for ( $k = strpos( $src, '{', $i ); $k < $n; $k++ ) { if ( '{' === $src[ $k ] ) { $d++; } elseif ( '}' === $src[ $k ] && 0 === --$d ) { return substr( $src, $i, $k - $i + 1 ); } } return ''; }
$sk = file_get_contents( __DIR__ . '/../../vhcp-saoke/vhcp-saoke.php' );
$ap = file_get_contents( __DIR__ . '/../../vhcp-saoke/app.html' );
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
$fs = '';
foreach ( array( 'public static function bo_duoi_hieu_(', 'public static function bo_hieu_(', 'private static function cong_coso(', 'public static function chuan_may(', 'public static function chuan_ch_long(',
	'private static function ghe_coso_cua_may(', 'private static function ghe_coso_chuan(', 'private static function cong_coso_dong(', 'private static function vqr_diem_theo_ma_(', 'private static function vqr_ma_ch(',
	'private static function ax_cua_may_(', 'private static function ax_theo_ngay(', 'private static function moc(', 'public static function rpc_taoCoSoGhe(' ) as $mo ) {
	$f = boc( $sk, $mo ); t( 'bốc ' . preg_replace( '/.*function /', '', $mo ), '' !== $f ); $fs .= "\n" . $f;
}
eval( 'class SAOKE_App { public static $mapMa = array(), $map = array(), $ds = array(), $ch = array(), $dsCH = array(), $axGoi = array(), $axTra = array( "ok" => true );
	private static $vqr_diem_cache = null;
	public static function kd( $s ) { $s = mb_strtolower( (string) $s, "UTF-8" ); $s = str_replace( array( "ế","ề","ể","ễ","ệ","ê","é","è","ẻ","ẽ","ẹ","ơ","ớ","ờ","ở","ỡ","ợ","ô","ố","ồ","ổ","ỗ","ộ","ó","ò","ỏ","õ","ọ","â","ấ","ầ","ẩ","ẫ","ậ","ă","ắ","ằ","ẳ","ẵ","ặ","á","à","ả","ã","ạ","ư","ứ","ừ","ử","ữ","ự","ú","ù","ủ","ũ","ụ","í","ì","ỉ","ĩ","ị","ý","ỳ","ỷ","ỹ","ỵ","đ" ), array( "e","e","e","e","e","e","e","e","e","e","e","o","o","o","o","o","o","o","o","o","o","o","o","o","o","o","o","o","a","a","a","a","a","a","a","a","a","a","a","a","a","a","a","a","a","u","u","u","u","u","u","u","u","u","u","u","i","i","i","i","i","y","y","y","y","y","d" ), $s ); return preg_replace( "/[^a-z0-9 ]/", "", $s ); }
	public static function chuan_ch( $s ) { return preg_replace( "/[^a-z0-9]/", "", self::kd( $s ) ); }
	private static function ghe_co() { return true; }
	private static function ghe_map_may() { return self::$map; }
	private static function ghe_map_may_ma() { return self::$mapMa; }
	private static function ghe_ds_coso() { return self::$ds; }
	private static function ds_coso_all() { return self::$ds; }
	private static function vqr_ds_ch() { return self::$dsCH; }
	private static function vqr_may_theo_ma( $m ) { $k = strtoupper( trim( (string) $m ) ); return isset( self::$dsCH[ $k ]["ten"] ) ? self::$dsCH[ $k ]["ten"] : ""; }
	private static function can_pin( $a ) {}
	private static function cong_ds() { return array( "vietqr", "momo", "vnpay" ); }
	private static function soft_err( $r ) { return $r; }
	private static function req( $p ) { return new ReqGia( $p ); }
	private static function r_anhxa_luu( $req ) { self::$axGoi[] = $req->p; return self::$axTra; }
	public static function thu( $t, $m = "", $ax = null, $tay = "" ) { return self::cong_coso_dong( $t, $m, $ax, $tay ); }
	public static function thu_chuan( $t ) { return self::ghe_coso_chuan( $t ); }
	public static function thu_may( $t ) { return self::ghe_coso_cua_may( $t ); }
	public static function thu_coso( $t ) { return self::cong_coso( $t ); }
	public static function thu_diem( $m ) { return self::vqr_diem_theo_ma_( $m ); }
	public static function thu_ax( $ax, $t, $m ) { return self::ax_cua_may_( $ax, $t, $m ); }
	public static function quen() { self::$vqr_diem_cache = null; }
	' . $fs . ' }
	class ReqGia { public $p; function __construct( $p ) { $this->p = $p; } function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; } }' );

echo "── 1. Chữ hiệu POSH bỏ khi so ─────────────────────────────────\n";
t( 'bo_duoi_hieu_: "AE Huế 04 Posh" → "AE Huế 04"; "POSH Aeon Mall Huế" giữ nguyên (chữ đầu không phải đuôi)', 'AE Huế 04' === SAOKE_App::bo_duoi_hieu_( 'AE Huế 04 Posh' ) && 'POSH Aeon Mall Huế' === SAOKE_App::bo_duoi_hieu_( 'POSH Aeon Mall Huế' ) );
t( 'bo_hieu_: bỏ cả đầu lẫn cuối — "POSH Aeon Mall Huế" → "Aeon Mall Huế"; "OCP POSH 01" giữ (giữa tên)', 'Aeon Mall Huế' === SAOKE_App::bo_hieu_( 'POSH Aeon Mall Huế' ) && 'OCP POSH 01' === SAOKE_App::bo_hieu_( 'OCP POSH 01' ) );
t( '🔴 chuan_may("AEHP 01 Posh") ≡ chuan_may("AEHP-1") — máy cổng đuôi Posh vẫn nối được ghế', SAOKE_App::chuan_may( 'AEHP 01 Posh' ) === SAOKE_App::chuan_may( 'AEHP-1' ) && 'aehp1' === SAOKE_App::chuan_may( 'AEHP 01 Posh' ), SAOKE_App::chuan_may( 'AEHP 01 Posh' ) );
t( 'không phá khoá cũ: "LM-NSG 01" ≡ "LM-NSG-1", "GO 02 HCM" giữ số giữa', SAOKE_App::chuan_may( 'LM-NSG 01' ) === SAOKE_App::chuan_may( 'LM-NSG-1' ) && 'go02hcm' === SAOKE_App::chuan_may( 'GO 02 HCM' ) );
t( '🔴 cong_coso("AE HUẾ 04 POSH") = "AE HUẾ" (trước: nguyên chuỗi → mỗi máy một "cơ sở")', 'AE HUẾ' === SAOKE_App::thu_coso( 'AE HUẾ 04 POSH' ), SAOKE_App::thu_coso( 'AE HUẾ 04 POSH' ) );
t( 'cong_coso cũ vẫn đúng: "AMTP 12" → "AMTP", "JP BÀ NÀ 7" → "JP BÀ NÀ"', 'AMTP' === SAOKE_App::thu_coso( 'AMTP 12' ) && 'JP BÀ NÀ' === SAOKE_App::thu_coso( 'JP BÀ NÀ 7' ) );

echo "── 2. Tên điểm bán là cấp cơ sở ────────────────────────────────\n";
SAOKE_App::$ds = array( array( 'ten' => 'AEON MALL HUẾ', 'biDanh' => '' ), array( 'ten' => 'AEON MALL HẢI PHÒNG', 'biDanh' => '' ), array( 'ten' => 'SÂN BAY CAM RANH', 'biDanh' => '' ) );
SAOKE_App::$mapMa = array( 'aehp1' => array( 'ma' => '80201', 'coso' => 'AEON MALL HẢI PHÒNG', 'trung' => false ) );
SAOKE_App::$dsCH = array(
	'X1' => array( 'ten' => 'AE Huế 04 Posh', 'maDiem' => 'MC0001', 'tenDiem' => 'POSH Aeon Mall Huế' ),
	'X2' => array( 'ten' => 'AEHP 01 Posh',   'maDiem' => 'MC0002', 'tenDiem' => 'POSH AEON Hải Phòng' ),
	'X3' => array( 'ten' => 'SBCR 07 Posh',   'maDiem' => 'MC0003', 'tenDiem' => 'POSH Sân bay Cam ranh' ),
	'X4' => array( 'ten' => 'JP PNT 4',       'maDiem' => 'MC0004', 'tenDiem' => 'JP PNT' ),
	'X5' => array( 'ten' => 'Vãng lai',       'maDiem' => '-',      'tenDiem' => '-' ),
);
t( 'vqr_diem_theo_ma_: theo mã cửa hàng, theo mã điểm bán, "-" là rỗng', 'POSH Aeon Mall Huế' === SAOKE_App::thu_diem( 'X1' ) && 'POSH Aeon Mall Huế' === SAOKE_App::thu_diem( 'MC0001' ) && '' === SAOKE_App::thu_diem( 'X5' ) && '' === SAOKE_App::thu_diem( '' ) );
t( '🔴 ghe_coso_chuan("POSH Aeon Mall Huế") → "AEON MALL HUẾ" (bỏ chữ hiệu đầu)', 'AEON MALL HUẾ' === SAOKE_App::thu_chuan( 'POSH Aeon Mall Huế' ), SAOKE_App::thu_chuan( 'POSH Aeon Mall Huế' ) );
t( '"POSH Sân bay Cam ranh" → "SÂN BAY CAM RANH"; "POSH AEON Hải Phòng" KHÔNG bịa ra "AEON MALL HẢI PHÒNG" (thiếu chữ MALL — khác tên)', 'SÂN BAY CAM RANH' === SAOKE_App::thu_chuan( 'POSH Sân bay Cam ranh' ) && '' === SAOKE_App::thu_chuan( 'POSH AEON Hải Phòng' ) );
$r = SAOKE_App::thu_may( 'AEHP 01 Posh' );
t( '🔴 ghe_coso_cua_may("AEHP 01 Posh") → ghế 80201 / AEON MALL HẢI PHÒNG', $r && '80201' === $r['ma'] && 'AEON MALL HẢI PHÒNG' === $r['coso'], $r );
$r = SAOKE_App::thu( 'AE HUẾ 04 POSH', 'X1' );
t( '🔴 cong_coso_dong: tên máy không ra ghế, tên cửa hàng không ra → nhân chứng TÊN ĐIỂM BÁN ra AEON MALL HUẾ (nguồn ma-ch)', 'AEON MALL HUẾ' === $r['coso'] && 'ma-ch' === $r['nguon'] && 0 === $r['xungDot'], $r );
$r = SAOKE_App::thu( 'AEHP 01 POSH', 'X2' );
t( 'máy khớp thẳng ghế vẫn thắng (ghe-may), điểm bán không đè', 'AEON MALL HẢI PHÒNG' === $r['coso'] && 'ghe-may' === $r['nguon'], $r );
$r = SAOKE_App::thu( 'JP PNT 4', 'X4' );
t( 'chuỗi khác (JP) không ra cơ sở Ghế nào → rỗng, không đoán bừa', '' === $r['coso'], $r );
$ax = array( SAOKE_App::chuan_ch( 'POSH AEON Hải Phòng' ) => array( array( 'tenFile' => 'POSH AEON Hải Phòng', 'tenChuan' => 'AEON MALL HẢI PHÒNG', 'maBank' => '', 'tuNgay' => '', 'denNgay' => '' ) ) );
$d = SAOKE_App::thu_ax( $ax, 'AEHP 09 POSH', 'X2' );
t( '🔴 ax_cua_may_: ánh xạ ghi theo NHÃN ĐIỂM BÁN (nút "Gán vào cơ sở có sẵn") được tra ra qua mã cửa hàng', is_array( $d ) && 'AEON MALL HẢI PHÒNG' === $d[0]['tenChuan'], $d );
t( 'ax_cua_may_: vẫn tra theo tên máy / cơ sở suy từ tên máy như cũ; không có gì → null', is_array( SAOKE_App::thu_ax( array( 'aehp' => array( array( 'tenChuan' => 'A', 'tuNgay' => '', 'denNgay' => '' ) ) ), 'AEHP 09 POSH', '' ) ) && null === SAOKE_App::thu_ax( array(), 'AEHP 09 POSH', 'X9' ) );
$r = SAOKE_App::thu( 'AEHP 09 POSH', 'X2', $ax[ SAOKE_App::chuan_ch( 'POSH AEON Hải Phòng' ) ][0] );
t( 'và với dòng ánh xạ ấy, máy 09 (chưa có ghế) quy về AEON MALL HẢI PHÒNG (anh-xa)', 'AEON MALL HẢI PHÒNG' === $r['coso'] && 'anh-xa' === $r['nguon'], $r );

echo "── 3. Nạp bù: 'chưa gán' = không quy được về Ghế, nhãn = điểm bán ──\n";
$nf = boc( $sk, 'public static function rpc_napFileCongTx(' );
t( '🔴 hỏi cong_coso_dong() (mọi nhân chứng), không chỉ hỏi "có ánh xạ chưa"', false !== strpos( $nf, "self::cong_coso_dong( \$tenMay, \$maCH, \$axD, '' )" ) && false === strpos( $nf, "\$chMoi[ self::cong_coso( \$tenMay ) ] = 1" ) );
t( 'nhãn gom theo tên điểm bán, hụt mới lấy cơ sở suy từ tên máy; trả chuaGan (ten/soGd/tien/maCH/tenMay) + soChuaGan', false !== strpos( $nf, "\$nhan = self::vqr_diem_theo_ma_( \$maCH ); if ( '' === \$nhan ) { \$nhan = self::cong_coso( \$tenMay ); }" ) && false !== strpos( $nf, "'chuaGan' => \$chuaGan, 'soChuaGan' => count( \$chMoi )" ) );
t( 'bốn màn tra ánh xạ cùng một chỗ ax_cua_may_ (không còn bản chép biểu thức isset(...chuan_ch(tenMay)...))', 4 === substr_count( $sk, 'self::ax_theo_ngay( self::ax_cua_may_(' ) && 0 === substr_count( $sk, "isset( \$anhXa[ self::chuan_ch( \$tenMay ) ] ) ? \$anhXa[ self::chuan_ch( \$tenMay ) ]" ) );

echo "── 4. Nút Tạo cơ sở mới bên Ghế ────────────────────────────────\n";
$r = SAOKE_App::rpc_taoCoSoGhe( array( '1234', 'vietqr', 'POSH AEON Hải Phòng', 'AEON MALL HẢI PHÒNG' ) );
t( 'chưa có lớp VHG_May (Ghế cũ / chưa cài) → nói thẳng, không nổ', is_array( $r ) && empty( $r['ok'] ) && false !== strpos( $r['error'], 'Chưa cài plugin Ghế' ), $r );
eval( 'class VHG_May { public static $goi = array(); public static $tra = array( "ok" => true, "id" => 77, "thong_bao" => "Đã thêm cơ sở X." );
	public static function luu_coso( $id, $ten, $tinh = null, $ma_kh = null, $reset = null ) { self::$goi[] = array( $id, $ten, $tinh, $ma_kh ); return self::$tra; } }
	class VHG_Nhat_Ky { public static $ghi = array(); public static function ghi( $d ) { self::$ghi[] = $d; } }' );
$r = SAOKE_App::rpc_taoCoSoGhe( array( '1234', 'vietqr', 'POSH AEON Hải Phòng', 'AEON MALL HẢI PHÒNG' ) );
t( '🔴 tạo qua VHG_May::luu_coso( 0, tên ) — đúng cửa của Ghế (chặn gần trùng, báo móc Chi Phí)', ! empty( $r['ok'] ) && 77 === $r['id'] && array( 0, 'AEON MALL HẢI PHÒNG', null, null ) === VHG_May::$goi[0], array( $r, VHG_May::$goi ) );
t( '🔴 rồi ghi ánh xạ nhãn cổng → tên mới, cờ gheMoi=1 (bộ đệm tên chưa biết cơ sở vừa tạo)', 1 === count( SAOKE_App::$axGoi ) && 'POSH AEON Hải Phòng' === SAOKE_App::$axGoi[0]['tenFile'] && 'AEON MALL HẢI PHÒNG' === SAOKE_App::$axGoi[0]['tenChuan'] && '1' === SAOKE_App::$axGoi[0]['gheMoi'] && 1 === $r['anhXa'], SAOKE_App::$axGoi );
t( 'ghi nhật ký Ghế', 1 === count( VHG_Nhat_Ky::$ghi ) && false !== strpos( VHG_Nhat_Ky::$ghi[0]['ghi_chu'], 'AEON MALL HẢI PHÒNG' ) );
SAOKE_App::$axGoi = array(); VHG_May::$goi = array();
$r = SAOKE_App::rpc_taoCoSoGhe( array( '1234', 'vietqr', 'GO ĐL', '' ) );
t( 'không gõ tên → lấy nhãn làm tên; ánh xạ vẫn ghi (nhãn = tên, vô hại)', ! empty( $r['ok'] ) && 'GO ĐL' === VHG_May::$goi[0][1] && 1 === count( SAOKE_App::$axGoi ) );
VHG_May::$tra = array( 'ok' => false, 'error' => 'Đã có cơ sở gần giống: "GO ĐÀ LẠT".' ); SAOKE_App::$axGoi = array();
$r = SAOKE_App::rpc_taoCoSoGhe( array( '1234', 'vietqr', 'GO ĐL', 'GO DA LAT' ) );
t( '🔴 Ghế từ chối (gần trùng) → trả đúng câu của Ghế, KHÔNG ghi ánh xạ', empty( $r['ok'] ) && false !== strpos( $r['error'], 'gần giống' ) && 0 === count( SAOKE_App::$axGoi ), $r );
t( 'tên rỗng cả hai → lỗi; nguồn lạ → lỗi', empty( SAOKE_App::rpc_taoCoSoGhe( array( '1', 'vietqr', '', '' ) )['ok'] ) && empty( SAOKE_App::rpc_taoCoSoGhe( array( '1', 'zalo', 'A', 'A' ) )['ok'] ) );
t( 'r_anhxa_luu nhận cờ gheMoi (cơ sở vừa tạo trong cùng lượt)', false !== strpos( boc( $sk, 'public static function r_anhxa_luu(' ), "'1' === (string) \$req->get_param( 'gheMoi' )" ) );
t( 'taoCoSoGhe khai trong $map; can_pin ≥ 36 chỗ (35 + taoCoSoGhe; 0.57.0 thêm ganMayGhe)', false !== strpos( $sk, "'xoaAnhXaCuaHang', 'taoCoSoGhe'," ) && substr_count( $sk, 'self::can_pin(' ) >= 36 );

echo "── 5. Màn hình ──────────────────────────────────────────────────\n";
t( 'khối kết quả nạp bù vẽ bảng chuaGan với hai nút (Gán / Tạo cơ sở mới bên Ghế), máy chủ cũ vẫn in cuaHangMoi', false !== strpos( $ap, 'ph.push(cgChuaGanHtml(nguon, r.chuaGan' ) && false !== strpos( $ap, 'else if(r.cuaHangMoi.length)' ) );
t( 'Gán → luuAnhXaCuaHang(PIN, nguon, nhãn, cơ sở) — đường cũ, không thêm RPC', false !== strpos( $ap, ".luuAnhXaCuaHang(PIN, nguon, x.ten, ten, '', '', '');" ) );
t( 'Tạo → hỏi tên (mặc định = nhãn) rồi taoCoSoGhe(PIN, nguon, nhãn, tên)', false !== strpos( $ap, '.taoCoSoGhe(PIN, nguon, x.ten, ten);' ) && false !== strpos( $ap, "prompt('Tên cơ sở MỚI bên Ghế" ) );
t( 'xong một dòng đánh dấu tại chỗ, không vẽ lại cả khối', 2 === substr_count( $ap, 'cgDanhDauXong(nguon, i, esc(' ) );
preg_match( '/^ \* Version:\s+([0-9.]+)/m', $sk, $m1 ); preg_match( "/const VER = '([0-9.]+)';/", $sk, $m2 );
t( 'vân tay: header Version == const VER, từ 0.51.0 trở lên', isset( $m1[1], $m2[1] ) && version_compare( $m1[1], '0.51.0', '>=' ) && $m1[1] === $m2[1] );

echo "\n"; if ( $TRUOT ) { echo '🔴 TRƯỢT: ' . count( $TRUOT ) . "\n"; exit( 1 ); } echo "✓ SẠCH — $DAT phép\n";
