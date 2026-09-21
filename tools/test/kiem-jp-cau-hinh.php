<?php
/**
 * DANH MỤC JP — CƠ SỞ · CỤM MÁY · Ô MÁY · MÃ HÀNG
 * =============================================================================================
 *
 * 🔴 PHẦN NẶNG NHẤT CỦA BÀI NÀY LÀ MẤY QUY TẮC "Ô TRỐNG THÌ RƠI VỀ ĐÂU".
 *    Chúng KHÔNG cùng một chiều, và chiều của mỗi cái được chọn theo HẬU QUẢ của việc quên
 *    khai — không theo cho đẹp mã. Đảo chiều một cái là đổi hành vi của 13 cơ sở đang chạy,
 *    mà chẳng dòng nào báo lỗi.
 *
 * Nên bài này chạy CHÍNH mã JavaScript gốc bằng node rồi đối chiếu từng ca, giống
 * `kiem-jp-doc.php`. Bảng đáp án chép tay ở đây là bản sự thật thứ hai — chép sai một ô thì
 * bài xanh cho một bản PHP sai chiều.
 *
 * Chạy: php tools/test/kiem-jp-cau-hinh.php
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
foreach ( array( 'db', 'doc', 'nguon', 'ma', 'nhat-ky', 'auth', 'cau-hinh' ) as $f ) {
	require_once $plg . 'class-vhjp-' . $f . '.php';
}
global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );

// ============================================================ 0. Cầu nối sang mã gốc
$cau_noi = __DIR__ . '/jp-doc-goc.js';
exec( 'node --version 2>/dev/null', $r_, $ma_node );
t( '🔴 có node để chạy mã gốc (thiếu là KHÔNG đối chiếu được)', 0 === $ma_node );

function goc_chay( $ten, $ca ) {
	global $cau_noi;
	$ra = array(); $ma = 0;
	exec( 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) ) . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
}
function doi_chieu( $ten, $ca ) {
	$g = goc_chay( $ten, $ca );
	if ( isset( $g['loi'] ) ) { t( "🔴 chạy được mã gốc cho $ten()", false, $g['loi'] ); return; }
	t( "mã gốc trả đủ ca cho $ten()", count( $g ) === count( $ca ), $g );
	foreach ( $ca as $i => $doi_so ) {
		$php = call_user_func_array( array( 'VHJP_CauHinh', $ten ), $doi_so );
		$mong = isset( $g[ $i ] ) ? $g[ $i ] : null;
		t( "$ten(" . trim( wp_json_encode( $doi_so ), '[]' ) . ')',
			wp_json_encode( $php ) === wp_json_encode( $mong ),
			'gốc ra ' . wp_json_encode( $mong ) . ', PHP ra ' . wp_json_encode( $php ) );
	}
}

// ============================================================ 1. 🔴 Ba cờ, ba chiều khác nhau
doi_chieu( 'co_dh_trung', array(
	array( '' ), array( null ), array( 'N' ), array( 'n' ), array( 'Y' ), array( 'y' ),
	array( 'x' ), array( ' N ' ), array( 0 ),
) );
doi_chieu( 'chon_gia_xung', array(
	array( '' ), array( null ), array( 'Y' ), array( 'y' ), array( 'N' ), array( 'n' ),
	array( 'x' ), array( ' Y ' ), array( 0 ),
) );
doi_chieu( 'bc_mau', array(
	array( '' ), array( null ), array( 'TACH' ), array( 'tach' ), array( 'CHUNG' ), array( 'x' ),
) );

/* Và nói thẳng chiều của từng cái, để người đọc bài kiểm thấy ngay mà không phải chạy node. */
teq( '🔴 đồng hồ đếm trứng: chưa khai thì mặc định CÓ',        true,  VHJP_CauHinh::co_dh_trung( '' ) );
teq( '🔴 chọn giá xung: chưa khai thì mặc định KHÔNG',         false, VHJP_CauHinh::chon_gia_xung( '' ) );
teq( 'chỉ đúng chữ N mới tắt đồng hồ đếm trứng',               false, VHJP_CauHinh::co_dh_trung( 'N' ) );
teq( 'chữ khác N thì vẫn CÓ',                                  true,  VHJP_CauHinh::co_dh_trung( 'x' ) );
teq( 'chỉ đúng chữ Y mới bật chọn giá xung',                   true,  VHJP_CauHinh::chon_gia_xung( 'Y' ) );

// ============================================================ 2. Giá suy từ mã, và đơn vị tính
doi_chieu( 'gia_tu_ma', array(
	array( '20JP' ), array( '20 JP' ), array( '5jp' ), array( '1000JP' ), array( '1001JP' ),
	array( '0JP' ), array( 'JP20' ), array( 'abc' ), array( '' ), array( null ),
	array( ' 30JP-X ' ),
) );
doi_chieu( 'dvt', array(
	array( array( 'dvt' => 'Cái' ) ), array( array( 'dvt' => '' ) ), array( array() ), array( null ),
) );
teq( 'mã 20JP ra 20.000đ',            20000, VHJP_CauHinh::gia_tu_ma( '20JP' ) );
teq( '🔴 ngoài khoảng thì trả 0, không đoán bừa', 0, VHJP_CauHinh::gia_tu_ma( '1001JP' ) );
teq( 'mã đời cũ chưa khai đơn vị thì rơi về Quả', 'Quả', VHJP_CauHinh::dvt( array() ) );

// ============================================================ 3. 🔴 Ba chỗ đếm ảnh, ba cách
/* Chỗ CƠ SỞ không phân biệt "chưa đặt" với "đặt số 0" — hai chỗ kia thì có. Bản gốc từng có
   bệnh ấy ở cả ba và đã sửa hai. Ghim lại để ai muốn thống nhất thì phải làm CÓ CHỦ Ý. */
$cs = VHJP_CauHinh::pub_coso( array( 'id' => 'L-1', 'code' => '', 'name' => 'A', 'maKH' => '',
	'machineType' => '', 'photoDefault' => 0, 'bcMau' => '', 'coDhTrung' => '',
	'chonGiaXung' => '', 'maDinhDanh' => '' ) );
teq( '🔴 cơ sở: số 0 gõ vào cũng thành 1 (chỗ này KHÁC hai chỗ kia)', 1, $cs['photoDefault'] );
teq( 'và ô trống cũng thành 1', 1, VHJP_CauHinh::pub_coso( array( 'id' => 'L-1', 'code' => '',
	'name' => 'A', 'maKH' => '', 'machineType' => '', 'photoDefault' => '', 'bcMau' => '',
	'coDhTrung' => '', 'chonGiaXung' => '', 'maDinhDanh' => '' ) )['photoDefault'] );
teq( 'cơ sở chưa khai loại máy thì mặc định TIỀN', 'TIEN', $cs['machineType'] );

$cum0 = array( 'id' => 'C-1', 'locationId' => 'L-1', 'name' => 'c', 'payboxSerial' => '',
	'hasQR' => 'Y', 'photoCount' => '' );
teq( '🔴 cụm CÓ QR, ô trống -> 1', 1, VHJP_CauHinh::pub_cum( $cum0 )['photoCount'] );
$cum0['hasQR'] = '';
teq( '🔴 cụm CHƯA lắp QR, ô trống -> 0', 0, VHJP_CauHinh::pub_cum( $cum0 )['photoCount'] );
$cum0['hasQR'] = 'Y'; $cum0['photoCount'] = 0;
teq( '🔴 cụm: số 0 GÕ VÀO thì GIỮ 0 (khác chỗ cơ sở)', 0, VHJP_CauHinh::pub_cum( $cum0 )['photoCount'] );

$may0 = array( 'id' => 'M-1', 'locationId' => 'L-1', 'clusterId' => '', 'code' => 'M1',
	'itemCode' => '', 'itemMisa' => '', 'photoCount' => '' );
teq( 'ô máy: ô trống -> 1', 1, VHJP_CauHinh::pub_may( $may0 )['photoCount'] );
$may0['photoCount'] = 0;
teq( '🔴 ô máy: số 0 GÕ VÀO thì GIỮ 0', 0, VHJP_CauHinh::pub_may( $may0 )['photoCount'] );

/* Giá 0 (hoặc chưa khai) thì suy từ chính mã hàng. */
teq( 'mã hàng chưa khai giá -> suy từ mã', 20000, VHJP_CauHinh::pub_hang(
	array( 'code' => '20JP', 'misa' => '', 'name' => 'x', 'price' => '', 'dvt' => '' ) )['price'] );
teq( 'khai giá rồi thì dùng giá đã khai', 15000, VHJP_CauHinh::pub_hang(
	array( 'code' => '20JP', 'misa' => '', 'name' => 'x', 'price' => 15000, 'dvt' => '' ) )['price'] );

// ============================================================ 4. Ghi và đọc danh mục
$kt = array( 'hoTen' => 'Chị Kế Toán', 'role' => VHJP_Auth::VAI_KT );
$r = VHJP_CauHinh::luu_coso( $kt, array( 'name' => 'Aeon Mall Bình Tân', 'code' => 'JPAMBT',
	'maDinhDanh' => ' kh705 mtd ' ) );
t( 'lưu được cơ sở', ! empty( $r['ok'] ), $r );
t( 'và sinh mã bắt đầu bằng L', 'L' === substr( $r['id'], 0, 1 ), $r );
teq( 'mã định danh bỏ dấu cách và viết hoa', 'KH705MTD',
	VHJP_Nguon::tim_mot( 'JP_Locations', 'id', $r['id'] )['maDinhDanh'] );

teq( 'thiếu tên cơ sở thì chối', false, VHJP_CauHinh::luu_coso( $kt, array( 'name' => '' ) )['ok'] );
teq( 'thiếu cơ sở thì cụm bị chối',  false, VHJP_CauHinh::luu_cum( $kt, array( 'name' => 'x' ) )['ok'] );
teq( 'thiếu cơ sở thì ô máy bị chối', false, VHJP_CauHinh::luu_may( $kt, array( 'code' => 'x' ) )['ok'] );
teq( 'thiếu mã hàng thì chối',        false, VHJP_CauHinh::luu_hang( $kt, array( 'name' => 'x' ) )['ok'] );

$cs_id = $r['id'];
VHJP_CauHinh::luu_cum( $kt, array( 'locationId' => $cs_id, 'name' => 'Cụm 1', 'hasQR' => true ) );
VHJP_CauHinh::luu_may( $kt, array( 'locationId' => $cs_id, 'code' => 'M01' ) );
VHJP_CauHinh::luu_may( $kt, array( 'locationId' => 'L-KHAC', 'code' => 'M99' ) );

teq( 'đọc lại 1 cơ sở', 1, count( VHJP_CauHinh::ds_coso() ) );
teq( 'đọc cụm theo cơ sở',   1, count( VHJP_CauHinh::ds_cum( $cs_id ) ) );
teq( 'đọc ô máy theo cơ sở', 1, count( VHJP_CauHinh::ds_may( $cs_id ) ) );
teq( 'đọc hết ô máy thì có 2', 2, count( VHJP_CauHinh::ds_may() ) );

/* Sửa: có `id` thì SỬA chứ không thêm dòng mới. */
$r2 = VHJP_CauHinh::luu_coso( $kt, array( 'id' => $cs_id, 'name' => 'Aeon Bình Tân' ) );
t( 'sửa được', ! empty( $r2['ok'] ), $r2 );
teq( 'và KHÔNG đẻ thêm dòng', 1, count( VHJP_CauHinh::ds_coso() ) );
teq( 'tên đã đổi', 'Aeon Bình Tân', VHJP_CauHinh::ds_coso()[0]['name'] );

// ============================================================ 5. 🔴 `=== false` là CHẶT
VHJP_CauHinh::luu_coso( $kt, array( 'id' => $cs_id, 'name' => 'A', 'active' => false ) );
teq( '🔴 đúng giá trị false thì TẮT', 0, count( VHJP_CauHinh::ds_coso() ) );
teq( 'nhưng đọc kèm cả dòng tắt thì vẫn thấy', 1, count( VHJP_CauHinh::ds_coso( true ) ) );

/* Chuỗi rỗng, số 0, chuỗi 'N' đều KHÔNG được tắt cơ sở — màn hình gửi lên ô trống là chuyện
   thường, mà tắt nhầm thì cơ sở biến mất khỏi mọi danh sách và không ai bấm gì cả. */
foreach ( array( '', 0, 'N', 'false', null ) as $v ) {
	VHJP_CauHinh::luu_coso( $kt, array( 'id' => $cs_id, 'name' => 'A', 'active' => $v ) );
	teq( '🔴 active = ' . json_encode( $v ) . ' thì VẪN BẬT', 1, count( VHJP_CauHinh::ds_coso() ) );
}
/* Cờ chọn giá xung ngược chiều: chỉ đúng `true` mới bật. */
VHJP_CauHinh::luu_coso( $kt, array( 'id' => $cs_id, 'name' => 'A', 'chonGiaXung' => 'Y' ) );
teq( '🔴 chonGiaXung = chuỗi "Y" thì VẪN TẮT (phải đúng true)', false,
	VHJP_CauHinh::ds_coso()[0]['chonGiaXung'] );
VHJP_CauHinh::luu_coso( $kt, array( 'id' => $cs_id, 'name' => 'A', 'chonGiaXung' => true ) );
teq( 'đúng true thì bật', true, VHJP_CauHinh::ds_coso()[0]['chonGiaXung'] );

// ============================================================ 6. Nhật ký
$nk = VHJP_Nguon::doc( 'JP_Audit' );
t( 'có ghi nhật ký cho mấy lượt lưu', count( $nk ) >= 4, count( $nk ) );
teq( 'nhật ký nhớ ai làm', 'Chị Kế Toán', $nk[0]['who'] );

/* 🔴 Nhật ký là thứ mở ra xem nhiều nhất và xuất ra ngoài dễ nhất. */
VHJP_NhatKy::ghi( $kt, 'THU', '', 'x',
	array( 'pin' => '357', 'token' => 'abc', 'ten' => 'giữ lại', 'trong' => array( 'password' => 'p' ) ) );
$cuoi = VHJP_Nguon::doc( 'JP_Audit' );
$ct = end( $cuoi )['detail'];
t( '🔴 nhật ký KHÔNG ghi PIN',        false === strpos( $ct, '357' ), $ct );
t( '🔴 KHÔNG ghi thẻ phiên',          false === strpos( $ct, 'abc' ), $ct );
t( '🔴 che cả trường lồng bên trong', false === strpos( $ct, 'password' ), $ct );
t( 'nhưng giữ lại phần dùng được',    false !== strpos( $ct, 'giữ lại' ), $ct );

/* Ghi nhật ký hỏng thì KHÔNG được làm hỏng nghiệp vụ. */
$wpdb->exec_raw( 'CREATE TRIGGER vhjp_chan_nk BEFORE INSERT ON ' . VHJP_Nguon::bang( 'JP_Audit' )
	. " BEGIN SELECT RAISE(ABORT, 'so nhat ky hong'); END" );
$van_luu = VHJP_CauHinh::luu_coso( $kt, array( 'name' => 'Cơ sở mới' ) );
$nk_hong = VHJP_NhatKy::ghi( $kt, 'X', '', '', '' );
$wpdb->exec_raw( 'DROP TRIGGER vhjp_chan_nk' );
t( '🔴 sổ nhật ký hỏng nhưng VẪN lưu được cơ sở', ! empty( $van_luu['ok'] ), $van_luu );
teq( 'và lượt ghi nhật ký tự nói là trượt, không ném lỗi', false, $nk_hong );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHÉP TRÊN CHƯA ĐỦ: nó xanh CẢ KHI gỡ sạch khối `catch`.
 *
 * Lý do: `VHJP_Nguon::them()` trả `false` khi câu lệnh hỏng chứ không NÉM — nên nhánh bắt lỗi
 * là mã chết trong ca ấy, và lượt đục "cho ném lỗi ra ngoài" sống sót. Bài kiểm đang xanh vì
 * chẳng có gì ném, không phải vì nhánh bắt lỗi làm việc.
 *
 * Phải dựng một ca THẬT SỰ NÉM từ trong thân `ghi()`: một đối tượng không đổi sang chuỗi được.
 * Người gọi truyền nhầm hình dạng là chuyện có thật, và nó KHÔNG được phép làm gãy nghiệp vụ.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
class VHJP_Vat_La {}          // không có __toString -> ép sang chuỗi là ném lỗi
$nem = null;
try {
	$nem = VHJP_NhatKy::ghi( $kt, 'X', '', new VHJP_Vat_La(), '' );
} catch ( Throwable $e ) {
	$nem = 'ĐÃ NÉM RA NGOÀI: ' . get_class( $e );
}
teq( '🔴 đối tượng lạ cũng KHÔNG được ném ra ngoài — nhật ký không làm hỏng nghiệp vụ',
	false, $nem );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — ba cờ giữ đúng ba chiều, ba chỗ đếm ảnh giữ đúng ba cách.\n";
