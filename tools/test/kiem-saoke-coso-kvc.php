<?php
/**
 * SAO KÊ NHẬN THÊM CƠ SỞ KHU VUI CHƠI TỪ PLUGIN CHI PHÍ (0.35.0).
 *
 * ==============================================================================================
 * Anh Thắng 16/09/2026: *"nhận thêm cơ sở khu vui chơi từ trang chi phí KVC"*.
 *
 * Tới 0.34.0, Sao Kê chỉ biết cơ sở bên Ghế — tức chỉ mảng ghế massage. Mảng khu vui chơi không
 * có ghế nào nên không bao giờ xuất hiện, và mọi màn đối chiếu nộp tiền lặng lẽ bỏ sót nó:
 * KHÔNG CÓ DÒNG THÌ KHÔNG AI THẤY THIẾU.
 *
 * 🔴 BÀI NÀY ĐẾM CHỖ GỌI (§6 CLAUDE.md, bài học 0.18.1). Danh sách cơ sở từng được lấy ở BẢY chỗ
 *    rải khắp tệp. Thêm một nguồn mà chỉ vá vài chỗ là KVC hiện ở màn này, vắng ở màn kia — luật
 *    đúng, một bản sao không được vá, bộ thử vẫn xanh, màn hình vẫn sai. Nay cả bảy phải đi qua
 *    `ds_coso_all()`, và `ghe_ds_coso()` chỉ được gọi ĐÚNG MỘT chỗ: bên trong nó.
 *
 * Chạy: php tools/test/kiem-saoke-coso-kvc.php   (chay-het.sh tự gom)
 */
$goc = dirname( __DIR__, 2 );
$s   = (string) file_get_contents( $goc . '/vhcp-saoke/vhcp-saoke.php' );
$LOI = 0; $SO = 0;
/* ⚠️ Tên `k()` chứ không phải `t()`: bệ đỡ dùng chung `lib/be-saoke.php` (nạp ở phần "Chạy thật"
   bên dưới) đã khai một `t()` riêng, và hai hàm cùng tên là PHP chết ngay lúc nạp — bài đỏ ở chỗ
   mã nguồn không hề sai. Lỗi ở bệ đỡ là loại tốn thời gian nhất: nó đổ tội cho đúng thứ mình
   đang thử (§8 CLAUDE.md). */
function k( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

echo "── Một nguồn duy nhất ──\n";
$goi_ghe = preg_match_all( '/self::ghe_ds_coso\(\)/', $s );
$goi_all = preg_match_all( '/self::ds_coso_all\(\)/', $s );
k( "🔴 ghe_ds_coso() chỉ còn gọi ĐÚNG 1 chỗ (trong ds_coso_all) — đang có $goi_ghe", 1 === $goi_ghe );
k( "🔴 ds_coso_all() được gọi đúng 7 chỗ (đủ bảy màn cũ) — đang có $goi_all", 7 === $goi_all );
k( 'ghe_ds_coso() được gọi TRONG ds_coso_all()',
	1 === preg_match( '/function ds_coso_all\(\).*?self::ghe_ds_coso\(\)/s', $s ) );
k( 'và chiphi_ds_coso() cũng vậy',
	1 === preg_match( '/function ds_coso_all\(\).*?self::chiphi_ds_coso\(\)/s', $s ) );

echo "── Cửa gác ──\n";
/* Hai khối từng gác bằng `ghe_co()`. Giữ nguyên là site chỉ có Chi Phí (không cài Ghế) thì cả
   hai khối biến mất, và KVC lại vô hình đúng như trước. */
$goi_co = preg_match_all( '/self::coso_co\(\)/', $s );
k( "🔴 hai cửa gác đổi sang coso_co() (Ghế HOẶC Chi Phí) — đang có $goi_co", 2 === $goi_co );
k( 'coso_co() là hoặc-hoặc, không phải và',
	false !== strpos( $s, 'return self::ghe_co() || self::chiphi_co();' ) );

echo "── Đọc của plugin kia ──\n";
/* 🔴 GỌI QUA LỚP CÔNG KHAI, KHÔNG ĐỌC THẲNG BẢNG của người ta — chính VHCP_Cfg::hut_coso_ghe()
   đã ghi luật ấy cho chiều ngược lại. Đọc thẳng bảng là ngày họ đổi cột thì bên này hỏng câm. */
k( '🔴 gọi VHCP_Cfg::cfg_static(), không truy vấn bảng vhcp_ nào',
	false !== strpos( $s, 'VHCP_Cfg::cfg_static()' )
	&& false === strpos( $s, "'vhcp_' ." ) && false === strpos( $s, 'wp_vhcp_' ) );
k( 'gác đủ class_exists + method_exists trước khi gọi',
	false !== strpos( $s, "class_exists( 'VHCP_Cfg' ) && method_exists( 'VHCP_Cfg', 'cfg_static' )" )
	&& false !== strpos( $s, "class_exists( 'VHCP_DonVi' )" ) );
k( 'thiếu plugin Chi Phí thì trả mảng rỗng, không đỏ',
	false !== strpos( $s, 'if ( ! self::chiphi_co() ) { return array(); }' ) );
k( 'so tên đơn vị bằng VHCP_DonVi::bang() (bỏ qua hoa/thường)',
	false !== strpos( $s, 'VHCP_DonVi::bang(' ) );

echo "── Hai cái bẫy tiền ──\n";
/* ⚠️ Một cơ sở có thể có mặt ở CẢ HAI bên: Chi Phí tự hút cơ sở từ Ghế sang (hut_coso_ghe). Không
   khử trùng là mỗi cơ sở ấy ra hai dòng, và tiền của nó bị đếm hai lần ở màn đối chiếu. Đếm
   thiếu ai cũng thấy; đếm gấp đôi không ai thấy (§8). */
k( '🔴 khử trùng theo chuan_ch() khi gộp hai nguồn',
	1 === preg_match( '/function ds_coso_all\(\).*?\$thay\[ \$k \]/s', $s ) );
k( 'khử trùng chạy cho CẢ hai nguồn',
	2 === preg_match_all( '/if \( \'\' === \$k \|\| isset\( \$thay\[ \$k \] \) \) \{ continue; \}/', $s ) );
/* Cơ sở đã đóng cửa mà vẫn kéo về là mỗi kỳ có một dòng "chưa nộp" đỏ vĩnh viễn — và một dòng
   đỏ không bao giờ xanh được là dòng người ta thôi nhìn. */
k( 'bỏ cơ sở đã đóng cửa bên Chi Phí', false !== strpos( $s, "isset( \$c['dongCua'] )" ) );

echo "── Tên đơn vị ──\n";
k( '🔴 khai ở option, không gõ cứng trong mã',
	false !== strpos( $s, "get_option( 'saoke_dv_chi_phi', '' )" ) );
k( "mặc định là 'KVC'", false !== strpos( $s, "return '' !== \$v ? \$v : 'KVC';" ) );

echo "── Số bản ──\n";
preg_match( '/^ \* Version:\s+([0-9][0-9.]*)/m', $s, $mh );
preg_match( "/const VER = '([0-9][0-9.]*)';/", $s, $mv );
$h = isset( $mh[1] ) ? $mh[1] : '?';
$v = isset( $mv[1] ) ? $mv[1] : '?';
k( "🔴 header == VER ($h vs $v)", '?' !== $h && $h === $v );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHẠY THẬT — nạp lớp thật trên bệ đỡ WordPress giả, dựng hai nguồn cơ sở rồi gọi ds_coso_all().
 *
 * Mọi phép trên mới soát chữ trong tệp. Chúng bắt được "ai đó gỡ khử trùng đi", nhưng KHÔNG bắt
 * được "khử trùng viết sai nên không khử gì cả" — mà đó mới là ca làm tiền đếm hai lần.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
echo "── Chạy thật ──\n";

/* Hai lớp giả đóng vai plugin Chi Phí. Chỉ khai đúng hai cửa mà Sao Kê gọi tới — khai thừa là bệ
   đỡ nói dối về mức phụ thuộc thật. */
class VHCP_DonVi {
	const MAC_DINH = 'K&H';
	public static function chuan( $x ) { $x = trim( (string) $x ); return '' === $x ? self::MAC_DINH : $x; }
	public static function bang( $a, $b ) { return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) ); }
}
class VHCP_Cfg {
	public static $coso = array();
	public static function cfg_static() { return array( 'coso' => self::$coso ); }
}

require_once __DIR__ . '/lib/be-saoke.php';
require_once dirname( __DIR__, 2 ) . '/vhcp-saoke/vhcp-saoke.php';

/* Bảng cơ sở bên Ghế: bệ đỡ trả rỗng cho SHOW TABLES nên ghe_co() = false -> chỉ còn nguồn KVC.
   Đó chính là ca đáng thử nhất: site có Chi Phí mà KHÔNG cài Ghế. */
VHCP_Cfg::$coso = array(
	array( 'ten' => 'KHU VUI CHƠI AEON TÂN PHÚ', 'donVi' => 'KVC', 'tinh' => 'TP HCM', 'dongCua' => '' ),
	array( 'ten' => 'Khu vui chơi Aeon Tân Phú', 'donVi' => 'kvc', 'tinh' => 'TP HCM', 'dongCua' => '' ),
	array( 'ten' => 'SÂN BAY CẦN THƠ',           'donVi' => 'POSH', 'tinh' => 'Cần Thơ', 'dongCua' => '' ),
	array( 'ten' => 'KVC ĐÃ ĐÓNG',               'donVi' => 'KVC', 'tinh' => '', 'dongCua' => '2026-01-01' ),
	array( 'ten' => '',                          'donVi' => 'KVC', 'tinh' => '', 'dongCua' => '' ),
);

$r = new ReflectionMethod( 'SAOKE_App', 'ds_coso_all' );
$r->setAccessible( true );
$ds = $r->invoke( null );
$ten = array();
foreach ( (array) $ds as $c ) { $ten[] = $c['ten']; }

k( '🔴 hai dòng cùng tên khác hoa/thường -> gộp còn MỘT (không thì tiền đếm hai lần)',
	1 === count( array_filter( $ten, function ( $x ) { return false !== mb_stripos( $x, 'aeon tân phú' ); } ) ) );
k( '🔴 chỉ lấy đơn vị KVC — không kéo cơ sở POSH sang',
	! in_array( 'SÂN BAY CẦN THƠ', $ten, true ) );
k( 'bỏ cơ sở đã đóng cửa', ! in_array( 'KVC ĐÃ ĐÓNG', $ten, true ) );
k( 'bỏ dòng tên rỗng', ! in_array( '', $ten, true ) );
k( 'còn đúng 1 cơ sở (đang có ' . count( $ten ) . ': ' . implode( ' · ', $ten ) . ')', 1 === count( $ten ) );
k( 'giữ được tỉnh để màn hình hiện kèm',
	isset( $ds[0]['tinh'] ) && 'TP HCM' === $ds[0]['tinh'] );
k( 'đánh dấu nguồn là chiphi', isset( $ds[0]['nguon'] ) && 'chiphi' === $ds[0]['nguon'] );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
