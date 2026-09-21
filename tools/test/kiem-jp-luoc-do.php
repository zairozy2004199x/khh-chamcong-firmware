<?php
/**
 * LƯỢC ĐỒ JP BÊN MySQL PHẢI KHỚP TỪNG CỘT VỚI BẢN GỐC GOOGLE SHEETS
 * =============================================================================================
 *
 * 🔴 VÌ SAO BÀI NÀY ĐỌC THẲNG TỆP GỐC, KHÔNG CHÉP DANH SÁCH CỘT SANG ĐÂY:
 *    Chép sang là từ lúc ấy có HAI bản sự thật. Bản gốc đổi mà bản chép không đổi thì bài kiểm
 *    vẫn xanh — nó đang so bản chép với chính nó. Nên nó đọc `goc/jp-capsule-v2/JP2_00_Config.gs`
 *    và bóc hằng `JP_TABS` ra, đúng thứ hệ JP đang chạy ngoài đời dùng.
 *
 * 🔴 CHUYỂN THIẾU MỘT CỘT LÀ MẤT ĐÚNG CỘT ẤY, IM LẶNG. Không câu lỗi nào, không dòng nhật ký
 *    nào — chỉ tới lúc đối soát cuối tháng mới thấy một con số không dựng lại được. Với 353 cột
 *    thì mắt người không canh nổi; phải để máy canh.
 *
 * Chạy: php tools/test/kiem-jp-luoc-do.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/**
 * Tước chú thích trước khi bóc.
 *
 * 🔴 Không tước thì mọi chuỗi nằm trong chú thích cũng bị tính là TÊN CỘT. Dính thật lúc dựng
 *    bài này: `JP_Locations` ra 16 cột thay vì 14, vì hai chú thích có nhắc `'N'` và `'Y'`.
 *    Một bộ bóc sai như thế làm bài kiểm đòi hai cột không hề tồn tại.
 *
 * ⚠️ `(?<!:)//` chứ không phải `//`: thiếu dấu chặn ấy là bộ tước ăn luôn hai gạch trong
 *    "https://" rồi nuốt hết phần còn lại của dòng — bộ soi mù mà vẫn xanh.
 */
function jp_tuoc( $ma ) {
	$ma = preg_replace( '#/\*.*?\*/#s', ' ', (string) $ma );
	return preg_replace( '#(?<!:)//[^\n]*#', ' ', $ma );
}
t( 'bộ tước ăn được khối /* */', strpos( jp_tuoc( "a /* 'N' */ b" ), "'N'" ) === false );
t( 'bộ tước ăn được dòng //',    strpos( jp_tuoc( "a\n// 'N'\nb" ), "'N'" ) === false );
t( '🔴 nhưng KHÔNG ăn nhầm hai gạch trong https://',
	strpos( jp_tuoc( "\$u = 'https://a.test'; giu_lai();" ), 'giu_lai' ) !== false );

// ============================================================ 1. Bóc JP_TABS từ bản gốc
$tep_goc = $goc . '/goc/jp-capsule-v2/JP2_00_Config.gs';
t( '🔴 mã gốc JP còn trong kho (mất nó là mất bản đối chiếu)', is_file( $tep_goc ), $tep_goc );
$ma = jp_tuoc( file_get_contents( $tep_goc ) );

$i = strpos( $ma, 'var JP_TABS = {' );
t( 'thấy hằng JP_TABS trong mã gốc', false !== $i );
$khoi = substr( $ma, $i );
$khoi = substr( $khoi, 0, strpos( $khoi, "\n};" ) );

preg_match_all( "/(\w+):\s*\{\s*name:\s*'([^']+)',\s*cols:\s*\[(.*?)\]/s", $khoi, $m, PREG_SET_ORDER );
$tab_goc = array();
foreach ( $m as $x ) {
	preg_match_all( "/'([^']+)'/", $x[3], $mc );
	$tab_goc[ $x[2] ] = $mc[1];
}
teq( '🔴 bản gốc khai đúng 23 tab', 23, count( $tab_goc ) );
/* Chốt chặn bộ bóc: con số tổng ở trên đổi theo mỗi lần thêm tab nên nó KHÔNG nói được bóc có
   đúng không. Ba tab dưới đây là ba hình dạng khác nhau — khoá `id`, khoá `code`, và bảng
   nặng nhất — bóc sai kiểu gì cũng lệch ít nhất một trong ba. */
teq( 'JP_Users đủ 11 cột',      11, count( $tab_goc['JP_Users'] ) );
teq( '🔴 JP_Locations đúng 14 cột (không phải 16 — hai chuỗi kia nằm trong chú thích)',
	14, count( $tab_goc['JP_Locations'] ) );
teq( 'JP_Rows đủ 46 cột',       46, count( $tab_goc['JP_Rows'] ) );
$tong_cot = 0;
foreach ( $tab_goc as $c ) { $tong_cot += count( $c ); }
teq( 'tổng cộng 353 cột bên bản gốc', 353, $tong_cot );

// ============================================================ 2. Nạp lược đồ MySQL
require_once $goc . '/wordpress/vhcp-jp/includes/class-vhjp-db.php';
$ban_do = VHJP_DB::ban_do();
$bang   = VHJP_DB::bang();

teq( 'bản đồ khai đủ 23 tab', 23, count( $ban_do ) );
teq( 'và dựng đủ 23 bảng',    23, count( $bang ) );
teq( 'không hai tab nào trỏ về cùng một bảng', 23, count( array_unique( $ban_do ) ) );

$thieu_tab = array_diff( array_keys( $tab_goc ), array_keys( $ban_do ) );
t( '🔴 không tab gốc nào bị bỏ quên', ! $thieu_tab, implode( ', ', $thieu_tab ) );
$thua_tab = array_diff( array_keys( $ban_do ), array_keys( $tab_goc ) );
t( 'và không bịa ra tab không có bên gốc', ! $thua_tab, implode( ', ', $thua_tab ) );

// ============================================================ 3. 🔴 TỪNG CỘT PHẢI CÓ CHỖ
$thieu_cot = array();
foreach ( $tab_goc as $tab => $cols ) {
	if ( ! isset( $ban_do[ $tab ] ) ) { continue; }
	$than = isset( $bang[ $ban_do[ $tab ] ] ) ? $bang[ $ban_do[ $tab ] ] : '';
	foreach ( $cols as $c ) {
		/* Khớp cả dạng trần lẫn dạng bọc dấu huyền (`rows`), và phải đứng ĐẦU DÒNG — không thì
		   một cột tên `note` khớp nhầm vào `topupNote` rồi báo xanh cho cột chưa hề có. */
		if ( ! preg_match( '/^\s*`?' . preg_quote( $c, '/' ) . '`?\s+[A-Z]/m', $than ) ) {
			$thieu_cot[] = $tab . '.' . $c;
		}
	}
}
t( '🔴 MỌI cột bên bản gốc đều có chỗ bên MySQL', ! $thieu_cot,
	count( $thieu_cot ) . ' cột mất: ' . implode( ', ', array_slice( $thieu_cot, 0, 12 ) ) );

// ============================================================ 4. Kiểu số: tiền và số lượng
$het = implode( "\n", $bang );
t( '🔴 KHÔNG cột nào dùng FLOAT/DOUBLE (cộng dồn là lệch, mà không dòng nào sai)',
	! preg_match( '/\b(FLOAT|DOUBLE|REAL)\b/i', $het ), $het );

/* Vài cột tiền và vài cột số lượng, chọn rải ở các bảng khác nhau. Tiền hai số lẻ, số lượng
   ba — lẫn hai thang đo là sổ kho lệch mà bảng tiền vẫn cân, khó thấy nhất. */
foreach ( array( 'bao_cao' => 'revMeter', 'nop_tien' => 'amount', 'hang' => 'price',
	'kho_nhap_ct' => 'unitCost', 'kho_tra_ncc' => 'soTien' ) as $b => $c ) {
	t( "tiền $b.$c là DECIMAL(15,2)",
		(bool) preg_match( '/^\s*' . $c . '\s+DECIMAL\(15,2\)/mi', $bang[ $b ] ), $c );
}
foreach ( array( 'kho_nhap_ct' => 'qty', 'kho_lop' => 'qtyRemaining',
	'kho_kk_ct' => 'tonThuc', 'de_nghi' => 'soCu' ) as $b => $c ) {
	t( "số lượng $b.$c là DECIMAL(15,3)",
		(bool) preg_match( '/^\s*' . $c . '\s+DECIMAL\(15,3\)/mi', $bang[ $b ] ), $c );
}

// ============================================================ 5. Mấy chốt riêng
/* 🔴 Bắt TÍNH CHẤT, không bắt chữ: điều cần là "hai dòng cùng refId thì cơ sở dữ liệu chối",
   chứ không phải "có đúng câu UNIQUE KEY ấy". Bản đầu đòi nguyên văn `UNIQUE KEY refid (refId)`
   trong khi `refId` ĐÃ là khoá chính — tức bài kiểm đang đòi một chỉ mục thừa, và giữ nó lại là
   MySQL dựng hai chỉ mục cho cùng một việc. Phép cắn thật nằm ở mục 6 dưới. */
t( '🔴 bank_gd lấy refId làm khoá chính — cùng luật với ghe_thu.ref, chống nhập lại thành cộng đôi',
	strpos( $bang['bank_gd'], 'PRIMARY KEY  (refId)' ) !== false, $bang['bank_gd'] );
t( 'và KHÔNG khai thêm chỉ mục duy nhất thừa trên chính cột ấy',
	strpos( $bang['bank_gd'], 'UNIQUE KEY' ) === false, $bang['bank_gd'] );

/* `rows` là từ MySQL 8 giữ chỗ. Để trần thì câu CREATE TABLE chết ngay lúc kích hoạt plugin,
   và thông báo chỉ nói "syntax error" chứ không chỉ ra cột nào. */
t( '🔴 cột `rows` được bọc dấu huyền',
	strpos( $bang['doi_soat_log'], '`rows`' ) !== false, $bang['doi_soat_log'] );

/* `id` là chuỗi `RP20260731-0007` do jpNextId_ sinh — không phải số tự tăng. Đổi sang BIGINT
   là phải đánh số lại toàn bộ dữ liệu đang chạy và mọi khoá ngoại đứt. */
t( '🔴 bao_cao.id là VARCHAR, KHÔNG phải số tự tăng',
	(bool) preg_match( '/^\s*id\s+VARCHAR/mi', $bang['bao_cao'] ), $bang['bao_cao'] );
t( 'và không bảng nào lỡ đặt id thành AUTO_INCREMENT',
	! preg_match( '/^\s*id\s+\w+.*AUTO_INCREMENT/mi', $het ) );

/* `coDhTrung`/`chonGiaXung` lưu 'Y'/'N' VÀ ô trống có nghĩa thứ ba. Ép sang TINYINT là nuốt
   mất nghĩa ấy — xem chú thích ở JP_TABS.LOCATIONS. */
foreach ( array( 'coDhTrung', 'chonGiaXung' ) as $c ) {
	t( "$c giữ VARCHAR (ô trống có nghĩa riêng, không ép sang 0/1)",
		(bool) preg_match( '/^\s*' . $c . '\s+VARCHAR/mi', $bang['coso'] ), $c );
}

t( 'mọi bảng đều có khoá chính', 23 === preg_match_all( '/PRIMARY KEY\s+\(/', $het ) );

// ============================================================ 6. Dựng thật bằng SQLite
/* Lược đồ đọc xuôi mắt mà không dựng nổi thì vô nghĩa: thiếu dấu phẩy, thừa ngoặc, kiểu sai —
   mấy lỗi ấy chỉ lộ lúc chạy câu CREATE TABLE thật. Dùng chung bộ dựng của bệ đỡ nên KHOÁ cũng
   được mang sang, không bị vứt như nếp cũ. */
vhcp_stub_dung_bang( VHJP_DB::bang(), $GLOBALS['wpdb']->prefix . 'vhjp_' );
t( '🔴 dựng được cả 23 bảng: KHÔNG câu DDL nào trượt', count( vhcp_stub_loi_ddl() ) === 0,
	vhcp_stub_loi_ddl() );

global $wpdb;
$co = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'wp_vhjp_%'" );
teq( 'đếm lại trong cơ sở dữ liệu: đủ 23 bảng', 23, $co );

/* Và khoá duy nhất của sổ ngân hàng phải CẮN THẬT, không chỉ có trên giấy. */
$bg = VHJP_DB::t( 'bank_gd' );
$h = array( 'refId' => 'FT-JP-001', 'ngay' => '2026-09-21', 'soTien' => 250000, 'noiDung' => 'x' );
t( 'ghi giao dịch ngân hàng đầu: được', false !== $wpdb->insert( $bg, $h ) );
teq( '🔴 ghi lại đúng refId ấy: cơ sở dữ liệu phải CHỐI', false, $wpdb->insert( $bg, $h ) );
teq( 'và sổ chỉ có 1 dòng — nhập lại sao kê KHÔNG cộng đôi', 1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $bg ) );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — 23 bảng JP sang MySQL không rơi cột nào.\n";
