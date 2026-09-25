<?php
/**
 * TẦNG ĐỌC/GHI CỦA JP — LỚP TRUY CẬP DUY NHẤT
 * =============================================================================================
 *
 * Bản gốc giữ được một luật suốt 28.735 dòng: *"KHÔNG file nào khác được gọi thẳng
 * SpreadsheetApp"*. Chính nhờ luật ấy mà thay Google Sheets bằng MySQL chỉ phải viết lại MỘT
 * lớp. Bài này canh để bản WordPress đừng đánh mất nó — nếu mất, lần đổi kho dữ liệu sau phải
 * rà lại cả hệ.
 *
 * 🔴 VÀ CANH CHỖ NGUY HIỂM NHẤT: tên cột không tham số hoá được. `tim($tab, $cot, $val)` nhận
 *    tên cột từ người gọi rồi phải ghép thẳng vào câu SQL. Không đối chiếu lược đồ trước là mở
 *    cửa cho chèn SQL ngay giữa đường tiền.
 *
 * Chạy: php tools/test/kiem-jp-nguon.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

$plg = $goc . '/wordpress/vhcp-jp/includes/';
require_once $plg . 'class-vhjp-db.php';
require_once $plg . 'class-vhjp-doc.php';
require_once $plg . 'class-vhjp-nguon.php';

global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
t( 'dựng được lược đồ để thử', count( vhcp_stub_loi_ddl() ) === 0, vhcp_stub_loi_ddl() );

// ============================================================ 1. Đọc lược đồ
$c = VHJP_Nguon::cot( 'JP_Users' );
teq( 'đọc đúng 11 cột của JP_Users', 11, count( $c ) );
t( 'có cột hoTen', in_array( 'hoTen', $c, true ), $c );
t( 'KHÔNG nhặt nhầm dòng khoá thành cột',
	! in_array( 'PRIMARY', $c, true ) && ! in_array( 'KEY', $c, true ), $c );
/* Cột `rows` bọc dấu huyền trong lược đồ — bóc ra phải là tên trần, không kèm dấu. */
t( '🔴 bóc được cột bọc dấu huyền thành tên trần',
	in_array( 'rows', VHJP_Nguon::cot( 'JP_ReconLog' ), true ), VHJP_Nguon::cot( 'JP_ReconLog' ) );

teq( 'tab lạ -> không cột nào, không nổ', array(), VHJP_Nguon::cot( 'JP_KhongCo' ) );
teq( 'tab lạ -> tên bảng rỗng',           '',      VHJP_Nguon::bang( 'JP_KhongCo' ) );

teq( 'khoá chính của JP_Reports là id',     'id',    VHJP_Nguon::khoa( 'JP_Reports' ) );
teq( 'khoá chính của JP_Items là code',     'code',  VHJP_Nguon::khoa( 'JP_Items' ) );
teq( 'khoá chính của JP_BankGD là refId',   'refId', VHJP_Nguon::khoa( 'JP_BankGD' ) );
teq( 'sổ nhật ký lấy stt làm khoá',         'stt',   VHJP_Nguon::khoa( 'JP_Audit' ) );

// ============================================================ 2. Thêm và đọc
$r = VHJP_Nguon::them( 'JP_Locations', array(
	'id' => 'L-001', 'code' => 'JPAMBT', 'name' => 'Aeon Mall Bình Tân', 'active' => 1 ) );
t( 'thêm được một cơ sở', false !== $r, $r );
teq( 'đọc lại thấy đúng 1 dòng', 1, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );

VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'L-002', 'code' => 'JPPQ', 'name' => 'Phú Quốc' ) );
teq( 'thêm dòng nữa thì có 2', 2, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );

$tim = VHJP_Nguon::tim( 'JP_Locations', 'code', 'JPPQ' );
teq( 'tìm theo cột code ra đúng 1 dòng', 1, count( $tim ) );
teq( 'và đúng dòng ấy', 'Phú Quốc', $tim[0]['name'] );
teq( 'tìm không thấy -> mảng rỗng', array(), VHJP_Nguon::tim( 'JP_Locations', 'code', 'ZZZ' ) );
teq( 'tim_mot không thấy -> null',   null,    VHJP_Nguon::tim_mot( 'JP_Locations', 'code', 'ZZZ' ) );

/* Bên gốc so theo CHUỖI (`String(v[i][c]) !== s`), nên số 1 khớp với chuỗi '1'. Mã chuyển sang
   dựa vào chuyện đó ở khắp nơi — đổi sang so chặt là hỏng im lặng một loạt lượt tra. */
teq( '🔴 so theo CHUỖI: truyền số 1 vẫn khớp cột lưu "1"', 1,
	count( VHJP_Nguon::tim( 'JP_Locations', 'active', 1 ) ) );
teq( 'và truyền chuỗi "1" cũng vậy', 1,
	count( VHJP_Nguon::tim( 'JP_Locations', 'active', '1' ) ) );

// ============================================================ 3. 🔴 Cột lạ và chèn SQL
teq( '🔴 cột KHÔNG có thật -> mảng rỗng, KHÔNG nổ (y hành vi bản gốc)', array(),
	VHJP_Nguon::tim( 'JP_Locations', 'khong_co_cot_nay', 'x' ) );
teq( 'tab lạ -> mảng rỗng',  array(), VHJP_Nguon::tim( 'JP_KhongCo', 'code', 'x' ) );

/* 🔴 Tên cột ghép thẳng vào SQL. Mấy chuỗi dưới đây mà tới được câu lệnh là xoá sạch bảng hoặc
   đọc trộm dòng của cơ sở khác. Phải bị chặn ở tầng đối chiếu lược đồ, TRƯỚC khi chạm SQL. */
foreach ( array(
	"code`; DROP TABLE wp_vhjp_coso; --",
	"code' OR '1'='1",
	'code UNION SELECT * FROM wp_vhjp_users',
	'1=1',
) as $doc ) {
	teq( '🔴 chặn tên cột độc: ' . substr( $doc, 0, 28 ), array(),
		VHJP_Nguon::tim( 'JP_Locations', $doc, 'x' ) );
}
teq( '🔴 và bảng vẫn còn nguyên sau mấy lượt ấy', 2, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHẢI ĐO "CÓ CHẠM CƠ SỞ DỮ LIỆU KHÔNG", CHỨ KHÔNG CHỈ ĐO KẾT QUẢ.
 *
 * Mấy phép ngay trên đo kết quả trả về là mảng rỗng — và chúng XANH CẢ KHI GỠ SẠCH phép đối
 * chiếu lược đồ. Lý do: cột lạ thì chính cơ sở dữ liệu chối câu lệnh, `get_results` trả rỗng,
 * và bài kiểm thấy đúng thứ nó mong. Tức là nó đang xanh cho một bản KHÔNG hề có chốt chặn.
 * Lượt đục thử lòi ra đúng chuyện đó.
 *
 * Nên đo bằng SỐ LƯỢT XUỐNG CƠ SỞ DỮ LIỆU: tên cột lạ phải bị chặn TRƯỚC khi có câu SQL nào
 * được dựng. Không lượt nào xuống thì không có gì để chối, và cũng không có gì để chèn.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
$truoc = $wpdb->q_count;
VHJP_Nguon::tim( 'JP_Locations', "code`; DROP TABLE wp_vhjp_coso; --", 'x' );
VHJP_Nguon::tim( 'JP_Locations', "code' OR '1'='1", 'x' );
VHJP_Nguon::tim( 'JP_Locations', 'khong_co_cot_nay', 'x' );
VHJP_Nguon::xoa_theo( 'JP_Locations', 'cot_bia', 'x' );
teq( '🔴 cột lạ KHÔNG đẻ ra một lượt xuống cơ sở dữ liệu nào', 0, $wpdb->q_count - $truoc );

/* Và canh chính phép đo: nếu `q_count` không nhúc nhích với cả lượt LÀNH thì phép trên vô
   nghĩa — nó sẽ báo 0 dù có chốt chặn hay không. */
$truoc2 = $wpdb->q_count;
VHJP_Nguon::tim( 'JP_Locations', 'code', 'JPPQ' );
t( 'phép đo có thật: lượt lành LÀM q_count tăng', $wpdb->q_count > $truoc2,
	$wpdb->q_count - $truoc2 );
t( 'co_cot() nói KHÔNG với cột bịa', ! VHJP_Nguon::co_cot( 'JP_Locations', 'bia_ra' ) );
t( 'và nói CÓ với cột thật',           VHJP_Nguon::co_cot( 'JP_Locations', 'maKH' ) );

// ============================================================ 4. Trường lạ bị bỏ im lặng
/* Mã chuyển sang mang theo trường tạm (`__kho`, `__con`, `__daTru` của FIFO). Nổ vì mấy trường
   ấy là chặn đúng đường chạy bình thường — bên gốc chúng tự rơi ra khi dựng dòng theo header. */
$r2 = VHJP_Nguon::them( 'JP_Locations', array(
	'id' => 'L-003', 'name' => 'Đà Nẵng', '__kho' => 'tạm', '__con' => 5, 'bia_dat' => 'x' ) );
t( '🔴 trường lạ bị bỏ im lặng, dòng vẫn ghi được', false !== $r2, $r2 );
t( 'và trường lạ KHÔNG lọt vào dòng đã ghi', ! isset( $r2['__kho'] ) && ! isset( $r2['bia_dat'] ), $r2 );
teq( 'dòng ấy đọc lại được', 'Đà Nẵng', VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-003' )['name'] );

teq( 'object TOÀN trường lạ -> không ghi gì', false,
	VHJP_Nguon::them( 'JP_Locations', array( '__chi_co_rac' => 1 ) ) );

// ============================================================ 5. Sửa theo khoá chính
t( 'sửa được', VHJP_Nguon::sua( 'JP_Locations', 'L-002', array( 'name' => 'Phú Quốc 2' ) ) );
teq( 'và đúng dòng ấy đổi', 'Phú Quốc 2',
	VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-002' )['name'] );
teq( 'dòng khác KHÔNG bị đụng', 'Aeon Mall Bình Tân',
	VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-001' )['name'] );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 MySQL ĐẾM DÒNG THỰC SỰ ĐỔI GIÁ TRỊ; SQLite ĐẾM DÒNG KHỚP `WHERE`.
 *
 * Ghi lại y nguyên là lượt LÀNH, và là lượt hay gặp nhất — người dùng bấm Lưu mà không sửa gì.
 * MySQL trả 0. Viết `(bool) $wpdb->update(...)` là lượt ấy bị báo lỗi lên màn hình.
 *
 * SQLite của bệ đỡ KHÔNG tái hiện được: `WHERE id='L-002'` luôn khớp 1 dòng nên nó trả 1, và
 * phép đục `(bool)` sống sót. Nên phải dựng đúng cách MySQL trả lời: bọc `$wpdb` lại, ép
 * `update()` trả 0. (Cùng bẫy đã gặp ở `kiem-ghi-khoan-thu.php` bên bộ Ghế.)
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
class VHJP_WPDB_Nhu_MySQL {
	private $that;
	public function __construct( $that ) { $this->that = $that; }
	/** Vẫn ghi thật, nhưng trả 0 — y MySQL khi không giá trị nào đổi. */
	public function update( $b, $d, $w ) { $this->that->update( $b, $d, $w ); return 0; }
	public function __call( $m, $a ) { return call_user_func_array( array( $this->that, $m ), $a ); }
	public function __get( $k ) { return $this->that->$k; }
	public function __set( $k, $v ) { $this->that->$k = $v; }
}
$that = $wpdb;
$GLOBALS['wpdb'] = new VHJP_WPDB_Nhu_MySQL( $that );
$lanh = VHJP_Nguon::sua( 'JP_Locations', 'L-002', array( 'name' => 'Phú Quốc 2' ) );
$GLOBALS['wpdb'] = $that; $wpdb = $that;
t( '🔴 ghi lại y nguyên (MySQL trả 0 dòng đổi) KHÔNG phải là hỏng', $lanh, $lanh );
teq( 'và dòng ấy vẫn đúng giá trị', 'Phú Quốc 2',
	VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-002' )['name'] );

/* Người gọi thường đọc cả dòng ra rồi sửa vài trường, nên khoá chính nằm sẵn trong object.
   Để nó ghi đè khoá là dòng ấy đổi danh tính và mọi liên kết trỏ tới nó đứt. */
VHJP_Nguon::sua( 'JP_Locations', 'L-003', array( 'id' => 'L-999', 'name' => 'Đà Nẵng 2' ) );
t( '🔴 lượt sửa KHÔNG được đổi khoá chính',
	null !== VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-003' )
	&& null === VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-999' ) );
teq( 'nhưng trường khác vẫn sửa được', 'Đà Nẵng 2',
	VHJP_Nguon::tim_mot( 'JP_Locations', 'id', 'L-003' )['name'] );

// ============================================================ 6. Xoá
t( 'xoá theo khoá chính', VHJP_Nguon::xoa( 'JP_Locations', 'L-003' ) );
teq( 'còn 2 dòng', 2, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );
teq( 'xoá theo cột: trả về SỐ dòng đã xoá', 1,
	VHJP_Nguon::xoa_theo( 'JP_Locations', 'code', 'JPPQ' ) );
teq( 'còn 1 dòng', 1, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );
teq( '🔴 xoá theo cột LẠ -> false, và KHÔNG xoá gì', false,
	VHJP_Nguon::xoa_theo( 'JP_Locations', 'cot_bia', 'x' ) );
teq( 'đúng là chưa mất dòng nào', 1, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );

// ============================================================ 7. 🔴 Ghi hỏng phải nói ra
/* Bẻ gãy lượt thêm bằng cò chặn của SQLite — giả cảnh cơ sở dữ liệu trục trặc một nhịp. */
$wpdb->exec_raw( 'CREATE TRIGGER vhjp_chan BEFORE INSERT ON ' . VHJP_Nguon::bang( 'JP_Locations' )
	. " BEGIN SELECT RAISE(ABORT, 'o cung hong'); END" );
$hong = VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'L-500', 'name' => 'Hỏng' ) );
$hong_nhieu = VHJP_Nguon::them_nhieu( 'JP_Locations', array(
	array( 'id' => 'L-501', 'name' => 'a' ), array( 'id' => 'L-502', 'name' => 'b' ) ) );
$wpdb->exec_raw( 'DROP TRIGGER vhjp_chan' );

teq( '🔴 thêm HỎNG -> false, KHÔNG gật đầu như bộ Ghế từng làm', false, $hong );
teq( '🔴 thêm nhiều mà hỏng hết -> đếm được 0 dòng', 0, $hong_nhieu );
teq( 'và sổ đúng là không có thêm dòng nào', 1, count( VHJP_Nguon::doc( 'JP_Locations' ) ) );

teq( 'thêm nhiều với danh sách rỗng -> 0, không đụng cơ sở dữ liệu', 0,
	VHJP_Nguon::them_nhieu( 'JP_Locations', array() ) );
teq( 'thêm 2 dòng lành -> đếm được 2', 2, VHJP_Nguon::them_nhieu( 'JP_Locations', array(
	array( 'id' => 'L-010', 'name' => 'a' ), array( 'id' => 'L-011', 'name' => 'b' ) ) ) );

// ============================================================ 8. 🔴 Lớp truy cập DUY NHẤT
/* Bắt TÍNH CHẤT: bất kỳ tệp nào khác trong bộ chạm `$wpdb` là luật đã vỡ. Bắt sớm thì sửa một
   dòng; bắt muộn thì lần đổi kho dữ liệu sau phải rà cả bộ. */
$pham = array();
foreach ( glob( $plg . '*.php' ) as $f ) {
	$ten = basename( $f );
	if ( in_array( $ten, array( 'class-vhjp-nguon.php', 'class-vhjp-db.php', 'index.php' ), true ) ) {
		continue;
	}
	$ma = preg_replace( '#/\*.*?\*/#s', ' ', file_get_contents( $f ) );
	$ma = preg_replace( '#(?<!:)//[^\n]*#', ' ', $ma );
	if ( strpos( $ma, '$wpdb' ) !== false ) { $pham[] = $ten; }
}
t( '🔴 KHÔNG lớp nào ngoài VHJP_Nguon và VHJP_DB chạm $wpdb', ! $pham, implode( ', ', $pham ) );
/* Và tự canh bộ tước chú thích — không thì phép trên là bộ soi mù. */
t( 'bộ tước trong phép trên ăn được chú thích',
	strpos( preg_replace( '#/\*.*?\*/#s', ' ', '/* $wpdb */' ), '$wpdb' ) === false );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — một lối vào dữ liệu, cột lạ không tới được SQL, ghi hỏng nói ra.\n";
