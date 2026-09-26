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

echo "── Hai sổ đi riêng ──\n";
/* 🔴 0.35.0 GỘP HAI NGUỒN VÀ KHỬ TRÙNG THEO TÊN — SAI, và anh Thắng bắt ngay:
   *"cơ sở trùng tên không thể thêm bên ngoài được"*. Khu vui chơi và ghế massage cùng nằm trong
   MỘT trung tâm thương mại nên "AEON MALL TÂN PHÚ" là tên của cả hai, mà là hai sổ tiền khác
   nhau. Khử trùng đúng cho "đừng đếm tiền hai lần của CÙNG một nơi", sai cho "hai nơi khác hẳn
   nhau tình cờ trùng tên". Nay mỗi bên một danh sách, một sổ mã, một bảng nhập. */
k( '🔴 KVC có sổ mã RIÊNG (option khác)',
	false !== strpos( $s, "get_option( 'saoke_coso_ma_kvc' )" )
	&& false !== strpos( $s, "get_option( 'saoke_coso_ma' )" ) );
k( 'hai option KHÔNG trùng tên', false === strpos( $s, "update_option( 'saoke_coso_ma', \$map );\n\t\tupdate_option( 'saoke_coso_ma_kvc'" ) );

/* Màn nào dính TIỀN thì phải đi danh sách riêng — gộp là một bên vĩnh viễn "chưa đặt mã". */
k( '🔴 màn nhập mã (Ghế) dùng ghe_ds_coso, KHÔNG dùng danh sách gộp',
	1 === preg_match( '/function rpc_getCosoMa\(.*?self::ghe_ds_coso\(\)/s', $s ) );
k( '🔴 màn nhập mã (KVC) dùng chiphi_ds_coso',
	1 === preg_match( '/function rpc_getCosoMaKvc\(.*?self::chiphi_ds_coso\(\)/s', $s ) );
foreach ( array( 'rpc_getCosoMa', 'rpc_getCosoMaKvc', 'rpc_getNopTienMat' ) as $ham ) {
	k( "🔴 $ham() KHÔNG chạm danh sách gộp ds_coso_all()",
		1 === preg_match( '/function ' . $ham . '\(.*?\n\t\}/s', $s, $mf )
		&& false === strpos( $mf[0], 'ds_coso_all()' ) );
}

echo "── Màn nộp tiền mặt chạy CẢ HAI sổ ──\n";
k( 'khai hai nguồn kèm sổ mã của chính nó',
	false !== strpos( $s, "'he' => 'POSH', 'ds' => self::ghe_ds_coso(),    'cm' => self::coso_ma_map()" )
	&& false !== strpos( $s, "'he' => 'KVC',  'ds' => self::chiphi_ds_coso(), 'cm' => self::coso_ma_map_kvc()" ) );
k( "🔴 mỗi dòng mang cột 'he' để màn hình phân biệt hai dòng trùng tên",
	false !== strpos( $s, "'he' => \$ng['he']," ) );
k( 'nhãn tự động gộp CẢ HAI sổ mã (mã đã tự mang hệ nên không lẫn)',
	false !== strpos( $s, 'self::coso_ma_map() + self::coso_ma_map_kvc()' ) );

echo "── Hàm mới phải khai vào danh sách cho phép ──\n";
/* ⚠️ Quên khai là màn hình báo "Hàm không hợp lệ" — chỉ lộ ra lúc bấm, không có gì đỏ lúc dựng. */
foreach ( array( 'getCosoMaKvc', 'saveCosoMaKvc' ) as $fn ) {
	k( "$fn có trong danh sách r_rpc", 1 === preg_match( "/'" . $fn . "',/", $s ) );
	k( "$fn có hàm rpc_ tương ứng", false !== strpos( $s, 'function rpc_' . $fn . '(' ) );
}

echo "── Danh sách gộp chỉ dùng cho câu hỏi tên ──\n";
$goi_all = preg_match_all( '/self::ds_coso_all\(\)/', $s );
k( "ds_coso_all() còn đúng 4 chỗ, đều là hỏi-tên/liệt-kê-tên — đang có $goi_all", 4 === $goi_all );
k( 'và nó cảnh báo rõ ĐỪNG dùng cho màn dính tiền',
	false !== strpos( $s, 'ĐỪNG DÙNG CHO BẤT CỨ MÀN NÀO DÍNH TỚI TIỀN' ) );

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
/* 🔴 KHỬ TRÙNG PHẢI NẰM TRONG TỪNG SỔ, không chỉ ở hàm gộp. Màn nhập mã gọi thẳng
   `chiphi_ds_coso()`, nên hai dòng trùng tên trong cùng danh mục Chi Phí sẽ ra HAI hàng nhập mà
   chung MỘT ô lưu (sổ khoá theo `chuan_ch`): gõ hàng dưới là mất mã hàng trên, không có gì báo.
   Bản đầu 0.36.0 để khử trùng ở ngoài và chính phép "chạy thật" bên dưới bắt được. */
foreach ( array( 'chiphi_ds_coso', 'ds_coso_all' ) as $ham ) {
	k( "🔴 $ham() tự khử trùng trong chính nó",
		1 === preg_match( '/function ' . $ham . '\(\).*?\n\t\}/s', $s, $mk )
		&& false !== strpos( $mk[0], '$thay[ $k ]' ) );
}
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
	const KHOI_THEO_DON_VI = array(
		'mb'  => array( 'MB', 'MIỀN BẮC', 'MIEN BAC' ),
		'mn'  => array( 'MN', 'MIỀN NAM', 'MIEN NAM' ),
		'kvc' => array( 'KVC' ),
		'mtd' => array( 'MTĐ', 'MTD', 'POSH' ),
		'vp'  => array( 'VP', 'VĂN PHÒNG', 'VAN PHONG' ),
	);
	public static function chuan( $x ) { $x = trim( (string) $x ); return '' === $x ? self::MAC_DINH : $x; }
	public static function bang( $a, $b ) { return 0 === strcasecmp( self::chuan( $a ), self::chuan( $b ) ); }
	/* Bản rút gọn của hàm thật (class-vhcp-donvi.php) — CHỈ để bệ đỡ này gọi được, không phải bản
	   chính thức. So thật thì gọi lớp Chi Phí thật, không phải bản chép ở đây. */
	public static function khoi_cua( $don_vi ) {
		$k = mb_strtoupper( trim( (string) $don_vi ) );
		if ( '' === $k ) { return ''; }
		foreach ( self::KHOI_THEO_DON_VI as $ma => $ds ) {
			foreach ( $ds as $x ) { if ( mb_strtoupper( $x ) === $k ) { return $ma; } }
		}
		return '';
	}
}
class VHCP_Cfg {
	public static $coso = array();
	public static function cfg_static() { return array( 'coso' => self::$coso ); }
}

require_once __DIR__ . '/lib/be-saoke.php';
require_once dirname( __DIR__, 2 ) . '/vhcp-saoke/vhcp-saoke.php';

/* 🔴 26/09/2026: anh Thắng — "nó bị mất cơ sở khu vui chơi nên không dò ra". Từ 24/09/2026 bên
   Chi Phí, cột "Đơn vị"/"Khối" (`donVi`) đổi sang mang nghĩa MIỀN (MB/MN); trục KVC·MTĐ·VP của
   MỘT CƠ SỞ nay là cột riêng "Bộ phận" (`boPhan`). Khối dưới đây mô phỏng đúng thực tế đó: phần
   lớn cơ sở khai `donVi` = miền (hoặc trống) và `boPhan` = 'kvc' — CHỈ MỘT dòng còn giữ lối khai
   cũ (`donVi` = 'KVC' trực tiếp, trước lượt đổi 24/09) để bảo đảm đường lui cũ không gãy.

   Bảng cơ sở bên Ghế: bệ đỡ trả rỗng cho SHOW TABLES nên ghe_co() = false -> chỉ còn nguồn KVC.
   Đó chính là ca đáng thử nhất: site có Chi Phí mà KHÔNG cài Ghế. */
VHCP_Cfg::$coso = array(
	array( 'ten' => 'KHU VUI CHƠI AEON TÂN PHÚ', 'donVi' => 'MN',   'boPhan' => 'kvc', 'tinh' => 'TP HCM', 'dongCua' => '' ),
	array( 'ten' => 'Khu vui chơi Aeon Tân Phú', 'donVi' => 'MN',   'boPhan' => 'kvc', 'tinh' => 'TP HCM', 'dongCua' => '' ),
	array( 'ten' => 'GALAXY KINH DƯƠNG VƯƠNG',   'donVi' => '',     'boPhan' => 'kvc', 'tinh' => 'TP HCM', 'dongCua' => '' ),
	array( 'ten' => 'FARM PHAN THIẾT (KHAI CŨ)', 'donVi' => 'KVC',  'boPhan' => '',    'tinh' => 'Bình Thuận', 'dongCua' => '' ),
	array( 'ten' => 'SÂN BAY CẦN THƠ',           'donVi' => 'POSH', 'boPhan' => 'mtd', 'tinh' => 'Cần Thơ', 'dongCua' => '' ),
	array( 'ten' => 'KVC ĐÃ ĐÓNG',               'donVi' => 'MN',   'boPhan' => 'kvc', 'tinh' => '', 'dongCua' => '2026-01-01' ),
	array( 'ten' => '',                          'donVi' => 'MN',   'boPhan' => 'kvc', 'tinh' => '', 'dongCua' => '' ),
);

/* Khoá của sổ mã là `chuan_ch(tên)` — gọi CHÍNH hàm của lớp thật, đừng chép lại luật chuẩn hoá
   (§5 CLAUDE.md: hai kiểu chuẩn hoá lệch nhau là nguồn của lỗi âm thầm). */
function SAOKE_App_k( $ten ) {
	$m = new ReflectionMethod( 'SAOKE_App', 'chuan_ch' ); $m->setAccessible( true );
	return $m->invoke( null, $ten );
}

$r = new ReflectionMethod( 'SAOKE_App', 'chiphi_ds_coso' );
$r->setAccessible( true );
$ds = $r->invoke( null );
$ten = array();
foreach ( (array) $ds as $c ) { $ten[] = $c['ten']; }

k( '🔴 hai dòng KVC cùng tên khác hoa/thường -> trong CÙNG một sổ vẫn gộp còn MỘT',
	1 === count( array_filter( $ten, function ( $x ) { return false !== mb_stripos( $x, 'aeon tân phú' ); } ) ) );
k( '🔴 26/09: cơ sở khai kiểu MỚI (Đơn vị=miền/trống, Bộ phận=kvc) VẪN nhận ra — đây là ca anh Thắng báo mất',
	in_array( 'GALAXY KINH DƯƠNG VƯƠNG', $ten, true ) );
k( '🔴 đường lui: cơ sở còn khai kiểu CŨ (Đơn vị=KVC trực tiếp, từ trước 24/09) vẫn nhận ra',
	in_array( 'FARM PHAN THIẾT (KHAI CŨ)', $ten, true ) );
k( '🔴 chỉ lấy khối KVC — không kéo cơ sở MTĐ/POSH sang dù Đơn vị lạ',
	! in_array( 'SÂN BAY CẦN THƠ', $ten, true ) );
k( 'bỏ cơ sở đã đóng cửa', ! in_array( 'KVC ĐÃ ĐÓNG', $ten, true ) );
k( 'bỏ dòng tên rỗng', ! in_array( '', $ten, true ) );
k( 'còn đúng 3 cơ sở (đang có ' . count( $ten ) . ': ' . implode( ' · ', $ten ) . ')', 3 === count( $ten ) );
k( 'giữ được tỉnh để màn hình hiện kèm',
	isset( $ds[0]['tinh'] ) && 'TP HCM' === $ds[0]['tinh'] );
k( 'đánh dấu nguồn là chiphi', isset( $ds[0]['nguon'] ) && 'chiphi' === $ds[0]['nguon'] );

/* 🔴 CA CHÍNH ANH THẮNG BÁO: khai mã cho một cơ sở KVC ở tab Cấu hình, rồi hỏi cổng suy mã từ
   tên chuẩn ấy. Trước 0.37.0 trả rỗng kèm "không có trong danh sách điểm". */
$GLOBALS['OPT']['saoke_coso_ma_kvc'] = array( SAOKE_App_k( 'KHU VUI CHƠI AEON TÂN PHÚ' ) => 'KH705KVCMN0002' );
$mt = new ReflectionMethod( 'SAOKE_App', 'map_ten_diem' ); $mt->setAccessible( true );
$am = new ReflectionMethod( 'SAOKE_App', 'ax_ma_nop' );    $am->setAccessible( true );
$suy = $am->invoke( null, array( 'maBank' => '', 'tenChuan' => 'KHU VUI CHƠI AEON TÂN PHÚ' ), $mt->invoke( null ) );
k( '🔴 khai mã KVC ở Cấu hình -> cổng suy ra ĐÚNG mã ấy (đang ra "' . $suy['ma'] . '")',
	'KH705KVCMN0002' === $suy['ma'] );
$suy2 = $am->invoke( null, array( 'maBank' => '', 'tenChuan' => 'Khu Vui Chơi Aeon Tân Phú' ), $mt->invoke( null ) );
k( 'khác hoa/thường và dấu vẫn ra đúng mã', 'KH705KVCMN0002' === $suy2['ma'] );
$suy3 = $am->invoke( null, array( 'maBank' => '', 'tenChuan' => 'TÊN KHÔNG CÓ Ở ĐÂU' ), $mt->invoke( null ) );
k( 'tên lạ thì vẫn rỗng và nói rõ đã dò những đâu',
	'' === $suy3['ma'] && false !== mb_strpos( $suy3['vi'], 'tab Cấu hình' ) );

echo "── Khai mã ở Cấu hình thì cổng phải tự suy ra ──\n";
/* 🔴 Anh Thắng 16/09/2026: *"trong cấu hình đã gán cơ sở theo mã nộp tiền rồi, thì chọn bên momo
   cơ sở là nó tự chuyển qua chứ"*. Tới 0.36.0 thì KHÔNG: `map_ten_diem()` chỉ tra danh sách
   điểm, hai sổ mã gõ ở tab Cấu hình nằm ngoài. Người dùng đã làm đúng việc được yêu cầu mà hệ
   thống vẫn nói chưa làm — loại lỗi làm mất niềm tin vào cả màn hình. */
k( 'map_ten_diem() có tra hai sổ mã ở Cấu hình',
	1 === preg_match( '/function map_ten_diem\(\).*?self::so_ma_cau_hinh\(\)/s', $s ) );
k( 'sổ gồm CẢ hai bên (Ghế + KVC)',
	1 === preg_match( '/function so_ma_cau_hinh\(\).*?self::coso_ma_map\(\).*?self::coso_ma_map_kvc\(\)/s', $s ) );
/* ⚠️ Dò bằng strpos trên THÂN HÀM, đừng nhét biến PHP vào mẫu regex trong chuỗi nháy kép —
   PHP nội suy `$map`/`$kk` thành rỗng, mẫu hỏng, bài đỏ ở chỗ mã nguồn không hề sai. */
k( '🔴 trùng tên khác mã thì đánh trung, không đoán bừa',
	1 === preg_match( '/function map_ten_diem\(\).*?\n\t\}/s', $s, $mmt )
	&& false !== strpos( $mmt[0], "'trung'] = true;" ) );
k( 'câu báo nhắc luôn tab Cấu hình', false !== strpos( $s, 'cũng chưa có mã ở tab Cấu hình' ) );

echo "── Bảng riêng trên màn Cấu hình ──\n";
$app = (string) file_get_contents( dirname( __DIR__, 2 ) . '/vhcp-saoke/app.html' );
k( 'có bảng riêng "Mã nộp tiền — Khu vui chơi"', false !== strpos( $app, 'Mã nộp tiền — Khu vui chơi' ) );
k( 'bảng Ghế gọi renderCosoMaKvc() nên nó luôn được vẽ kèm',
	false !== strpos( $app, 'renderCosoMaKvc();' ) );
/* 🔴 HAI BẢNG PHẢI DÙNG HAI THUỘC TÍNH KHÁC NHAU. Trùng `data-cm` là nút Lưu của bảng này quét
   trúng ô của bảng kia và ghi đè sổ của nhau — đúng cái lỗi bảng riêng sinh ra để chữa. */
k( '🔴 ô nhập của hai bảng mang thuộc tính KHÁC nhau (data-cm vs data-cmk)',
	false !== strpos( $app, 'data-cmk="' ) && false !== strpos( $app, 'data-cm="' ) );
k( 'nút lưu KVC chỉ quét ô data-cmk',
	1 === preg_match( '/function luuCosoMaKvc\(\).*?\[data-cmk\]/s', $app ) );
k( 'nút lưu Ghế chỉ quét ô data-cm',
	1 === preg_match( '/function luuCosoMa\(\)\{.*?\[data-cm\]/s', $app ) );
/* Rỗng thì phải nói THIẾU CÁI GÌ — "chưa có cơ sở nào" suông thì không ai biết đi làm gì tiếp. */
k( 'khi rỗng, phân biệt "chưa cài Chi Phí" với "chưa khai đơn vị"',
	false !== strpos( $app, 'Chưa nối được plugin Chi Phí' )
	&& false !== strpos( $app, 'Chưa có cơ sở nào mang đơn vị' ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
