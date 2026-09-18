<?php
/**
 * NHÂN VIÊN TỰ KHAI LOẠI GIỜ LƯƠNG — chọn lúc kết ca, xin đổi ngày cũ, và vào bảng lương.
 *
 * =================================================================================================
 * 🔴 BÀI NÀY CANH MỘT THỨ ĐỤNG THẲNG VÀO TIỀN
 * =================================================================================================
 * Anh Thắng 18/09/2026: *"Khi bấm check in giờ ra nó sẽ hỏi ca1,2,3 bạn làm nhiệm vụ gì. Để nhân
 * viên tự set luôn"*. Loại giờ quyết định ĐƠN GIÁ, nên người khai chính là người được trả tiền
 * theo cái mình vừa khai. Bốn chỗ hỏng, xếp theo mức đắt:
 *
 *   1. ĐẾM HAI LẦN — bảng lương có sẵn mấy dòng "giờ khác" kế toán gõ tay. Cộng cả hai nguồn là
 *      2 giờ MC thành 4, và bảng vẫn đầy số nên không ai thấy.
 *   2. SỬA NGƯỢC QUÁ KHỨ KHÔNG AI DUYỆT — cuối tháng ai cũng đổi hết ca của mình sang việc có
 *      giá cao nhất.
 *   3. TỰ DUYỆT ĐƠN CỦA CHÍNH MÌNH — cửa hàng trưởng cũng đi làm ca và cũng có loại giờ.
 *   4. CHỌN MỘT VIỆC KHÔNG CÓ TRONG BẢNG ĐƠN GIÁ — ra 0đ, mà màn thì vẫn xanh.
 *
 * Chạy: php tools/test/kiem-loai-gio.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 300 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

global $wpdb;

$CS   = 'LG_SHOP';
$CS2  = 'LG_SHOP_2';
$HOM  = (string) current_time( 'Y-m-d' );
$QUA  = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 86400 );
$THANG = substr( $HOM, 0, 7 );

$AD  = array( 'name' => 'Quản Trị', 'role' => VHCC_Vai::ADMIN, 'coso' => '', 'ma_nv' => 'LGAD' );
$NV  = array( 'name' => 'Em Nhân Viên', 'role' => VHCC_Vai::NV,  'coso' => $CS, 'ma_nv' => 'LGNV' );
$NV2 = array( 'name' => 'Em Thứ Hai',   'role' => VHCC_Vai::NV,  'coso' => $CS, 'ma_nv' => 'LGNV2' );
$CHT = array( 'name' => 'Chị Trưởng',   'role' => VHCC_Vai::CHT, 'coso' => $CS, 'ma_nv' => 'LGCHT' );

foreach ( array(
	array( 'LGNV',  'Em Nhân Viên', $CS,  'Nhân viên' ),
	array( 'LGNV2', 'Em Thứ Hai',   $CS,  'Nhân viên' ),
	array( 'LGCHT', 'Chị Trưởng',   $CS,  'Cửa hàng trưởng' ),
) as $x ) {
	$quan = VHCC_Vai::duoc( array( 'role' => $x[3] ), 'cong_coso' ) ? $x[2] : '';
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $x[0], 'ho_ten' => $x[1],
		'cua_hang' => $x[2], 'vai_tro' => $x[3], 'coso_quan' => $quan, 'chuc_vu' => 'Partime',
		'trang_thai_lam_viec' => 'Đang làm' ) );
}

/* Ba loại giờ của cơ sở — chính là ba dòng đơn giá. */
VHCC_GiaGio::dat_coso( $AD, $CS, array( 'Partime' => 26000, 'MC' => 40000, 'Hỗ Trợ' => 30000 ) );
/* Cơ sở thứ hai chỉ có MỘT dòng — để canh nhánh "không hỏi khi chỉ có một lựa chọn". */
VHCC_GiaGio::dat_coso( $AD, $CS2, array( 'Partime' => 26000 ) );

/* ================================================================= công tắc */

echo "— công tắc theo cơ sở —\n";
t( '🔴 mặc định TẮT — tính năng đang thử nghiệm', ! VHCC_LoaiGio::bat_ket_ca( $CS ) );
t( 'và tab cũng tắt', ! VHCC_LoaiGio::bat_tab( $CS ) );

$r = VHCC_LoaiGio::dat_cfg( $NV, $CS, true, true );
t( '🔴 nhân viên KHÔNG tự bật được', empty( $r['ok'] ), $r );

$r = VHCC_LoaiGio::dat_cfg( $AD, $CS, true, true );
t( 'admin bật được', ! empty( $r['ok'] ), $r );
t( 'bật rồi thì hỏi lúc kết ca', VHCC_LoaiGio::bat_ket_ca( $CS ) );
t( 'và tab hiện', VHCC_LoaiGio::bat_tab( $CS ) );
/* 🔴 BẬT MỘT CƠ SỞ KHÔNG BẬT LÂY — cả điểm của "thử nghiệm từng cơ sở" nằm ở đây. */
t( '🔴 cơ sở khác KHÔNG bị bật lây', ! VHCC_LoaiGio::bat_ket_ca( $CS2 ) );

VHCC_LoaiGio::dat_cfg( $AD, $CS, false, true );
t( 'tắt riêng phần kết ca được', ! VHCC_LoaiGio::bat_ket_ca( $CS ) );
t( 'mà tab vẫn bật', VHCC_LoaiGio::bat_tab( $CS ) );
VHCC_LoaiGio::dat_cfg( $AD, $CS, true, true );

/* ================================================================= danh sách việc */

echo "— bảng giá của cơ sở —\n";
$ds = VHCC_LoaiGio::ds_gia( $CS, 'LGNV' );
teq( 'ba dòng đơn giá của cơ sở', 3, count( $ds ) );
$ten_ds = array();
foreach ( $ds as $x ) { $ten_ds[] = $x['ten']; }
sort( $ten_ds );
teq( 'và mang đúng TÊN người gõ, không phải khoá tra',
	array( 'Hỗ Trợ', 'MC', 'Partime' ), $ten_ds );

/* Đơn giá RIÊNG của một người phải hiện thêm trong bảng giá của chính họ. */
VHCC_GiaGio::dat_nguoi( $AD, 'LGNV2', array( 'Lái Tàu' => 35000 ) );
teq( '🔴 đơn giá riêng của người cộng thêm vào bảng giá của chính họ', 4,
	count( VHCC_LoaiGio::ds_gia( $CS, 'LGNV2' ) ) );
teq( 'người khác không thấy dòng riêng ấy', 3, count( VHCC_LoaiGio::ds_gia( $CS, 'LGNV' ) ) );

/* ================================================================= HỎI AI: phải có bằng chứng */

echo "— hỏi ai —\n";

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐÂY LÀ CHỐT ĐẮT NHẤT CỦA CẢ TÍNH NĂNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Bản 4.60.0 hỏi MỌI người ở cơ sở nào có từ hai dòng đơn giá. Anh Thắng 18/09/2026 chốt lại:
 * *"những nhân viên 1 giờ nghĩ không nên hỏi tránh cập nhật nhầm hoặc gian lận. Trừ khi bạn đó
 * mới được phân thì cht sẽ set"*.
 *
 * Bật công tắc mà chưa phân ai thì KHÔNG AI bị hỏi — kể cả khi cơ sở có ba dòng đơn giá. Bày
 * ba lựa chọn cho người cả đời chỉ đứng quầy là vừa mời bấm nhầm, vừa phát cho họ đúng cái nút
 * để tự nâng đơn giá ca của mình.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 BẬT công tắc mà chưa phân ai thì KHÔNG hỏi ai cả',
	! VHCC_LoaiGio::hoi_khi_ra( $CS, 'LGNV' ) );
teq( '   và danh sách được bấm chọn là RỖNG', 0, count( VHCC_LoaiGio::ds_viec( $CS, 'LGNV' ) ) );
t( '   tab cũng không hiện cho họ', ! VHCC_LoaiGio::hien_tab( $CS, 'LGNV' ) );

/* ---- đường 1: cửa hàng trưởng phân ---- */
$r = VHCC_LoaiGio::dat_phan( $NV, $CS, 'LGNV', array( 'MC', 'Hỗ Trợ' ) );
t( '🔴 nhân viên KHÔNG tự phân việc cho mình', empty( $r['ok'] ), $r );

$r = VHCC_LoaiGio::dat_phan( $CHT, $CS, 'LGNV', array( 'MC', 'Hỗ Trợ', 'Việc Không Có Thật' ) );
t( 'cửa hàng trưởng phân được', ! empty( $r['ok'] ), $r );
teq( '🔴 việc KHÔNG có trong bảng đơn giá bị loại — phân nó là người ấy ăn 0đ', 2, count( $r['ds'] ) );

t( '🔴 phân 2 việc thì TỪ GIỜ có hỏi', VHCC_LoaiGio::hoi_khi_ra( $CS, 'LGNV' ) );
teq( '   và chỉ bấm chọn được ĐÚNG 2 việc đã phân, không phải cả 3 dòng giá', 2,
	count( VHCC_LoaiGio::ds_viec( $CS, 'LGNV' ) ) );
$ten_duoc = array();
foreach ( VHCC_LoaiGio::ds_viec( $CS, 'LGNV' ) as $x ) { $ten_duoc[] = $x['ten']; }
sort( $ten_duoc );
teq( '   đúng hai việc ấy', array( 'Hỗ Trợ', 'MC' ), $ten_duoc );
t( '   tab cũng hiện', VHCC_LoaiGio::hien_tab( $CS, 'LGNV' ) );

/* 🔴 PHÂN ĐÚNG MỘT VIỆC = THÔI HỎI. Đó là cách diễn đạt "người này chỉ làm một việc". */
VHCC_LoaiGio::dat_phan( $CHT, $CS, 'LGNV2', array( 'Partime' ) );
t( '🔴 phân đúng MỘT việc thì KHÔNG hỏi', ! VHCC_LoaiGio::hoi_khi_ra( $CS, 'LGNV2' ) );
/* Bỏ phân thì quay về không hỏi. */
VHCC_LoaiGio::dat_phan( $CHT, $CS, 'LGNV2', array() );
t( 'bỏ phân thì thôi hỏi', ! VHCC_LoaiGio::hoi_khi_ra( $CS, 'LGNV2' ) );

/* ---- đường 2: tháng trước đã làm đủ hai loại ---- */
$THANG_TRUOC = gmdate( 'Y-m', strtotime( $THANG . '-01 00:00:00 UTC' ) - 86400 );
foreach ( array( array( '-05', 'MC' ), array( '-06', 'Hỗ Trợ' ) ) as $x_tt ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS,
		'ngay' => $THANG_TRUOC . $x_tt[0], 'ma_nv' => 'LGNV2', 'ho_ten' => 'Em Thứ Hai',
		'gio_vao_giay' => 28800, 'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may',
		'loai_gio' => $x_tt[1] ) );
}
teq( 'tháng trước LGNV2 đã làm 2 loại', 2, count( VHCC_LoaiGio::thang_truoc_da_lam( $CS, 'LGNV2' ) ) );
t( '🔴 tháng trước làm 2 loại thì tháng này CÓ HỎI, dù chưa ai phân',
	VHCC_LoaiGio::hoi_khi_ra( $CS, 'LGNV2' ) );

/* 🔴 PHÂN THẮNG LỊCH SỬ, KHÔNG CỘNG VÀO. Cửa hàng trưởng rút bớt việc của ai đó là đang nói
   "người này thôi làm việc ấy" — cộng thêm lịch sử tháng trước vào là lệnh rút ấy không có tác
   dụng gì suốt cả tháng sau. */
VHCC_LoaiGio::dat_phan( $CHT, $CS, 'LGNV2', array( 'Partime' ) );
t( '🔴 phân 1 việc THẮNG lịch sử 2 loại của tháng trước',
	! VHCC_LoaiGio::hoi_khi_ra( $CS, 'LGNV2' ) );
VHCC_LoaiGio::dat_phan( $CHT, $CS, 'LGNV2', array() );

/* Cơ sở chỉ có một dòng đơn giá thì phân kiểu gì cũng không đủ hai. */
VHCC_LoaiGio::dat_cfg( $AD, $CS2, true, true );
VHCC_LoaiGio::dat_phan( $AD, $CS2, 'LGNV', array( 'Partime' ) );
t( '🔴 cơ sở chỉ có MỘT dòng đơn giá thì không cách nào đủ hai lựa chọn',
	! VHCC_LoaiGio::hoi_khi_ra( $CS2, 'LGNV' ) );

/* ================================================================= ghi lúc kết ca */

echo "— kết ca —\n";
/* Gieo hai ca: hôm nay và hôm qua. */
foreach ( array( $HOM, $QUA ) as $ng ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $ng,
		'ma_nv' => 'LGNV', 'ho_ten' => 'Em Nhân Viên', 'gio_vao_giay' => 28800,
		'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
}

$r = VHCC_LoaiGio::dat_khi_ra( $CS, $HOM, 'LGNV', '', 'Việc Không Có Thật' );
t( '🔴 chọn một việc KHÔNG có trong bảng đơn giá thì chối', empty( $r['ok'] ), $r );
t( 'và nói rõ vì sao', false !== mb_strpos( $r['error'], 'đơn giá' ), $r['error'] );

$r = VHCC_LoaiGio::dat_khi_ra( $CS, $HOM, 'LGNV', '', 'mc' );
t( 'gõ thường/không dấu vẫn khớp dòng đơn giá', ! empty( $r['ok'] ), $r );
teq( '🔴 và lưu xuống là TÊN CHUẨN trong sổ, không phải cách người ta gõ', 'MC', $r['viec'] );

$hang = VHCC_DB::rows( $wpdb->prepare( 'SELECT loai_gio FROM ' . VHCC_DB::t( 'cham_cong' )
	. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS, $HOM, 'LGNV' ) );
teq( 'đã vào bảng chấm công', 'MC', (string) $hang[0]['loai_gio'] );

/* 🔴 KHAI RỒI THÌ KẾT CA LẦN NỮA KHÔNG ĐÈ. Một cú bấm nhầm ở trạm không được xoá lượt đã khai
   — đổi cái đã khai thì đi đường xin duyệt. */
$r = VHCC_LoaiGio::dat_khi_ra( $CS, $HOM, 'LGNV', '', 'Hỗ Trợ' );
t( '🔴 lượt đã khai rồi thì kết ca KHÔNG ghi đè', empty( $r['ok'] ), $r );
t( 'và chỉ sang tab Giờ công lương', false !== mb_strpos( $r['error'], 'Giờ công lương' ), $r['error'] );

/* Cơ sở tắt công tắc thì cửa này đóng hẳn. */
VHCC_LoaiGio::dat_cfg( $AD, $CS, false, true );
$r = VHCC_LoaiGio::dat_khi_ra( $CS, $QUA, 'LGNV', '', 'MC' );
t( '🔴 cơ sở đã TẮT thì không ghi được nữa', empty( $r['ok'] ), $r );
VHCC_LoaiGio::dat_cfg( $AD, $CS, true, true );

/* ================================================================= xin đổi ngày cũ */

echo "— xin đổi ngày cũ —\n";
$ng_cua_toi = VHCC_LoaiGio::ngay_cua_toi( $CS, 'LGNV', 14 );
t( 'thấy đủ hai ngày của mình', count( $ng_cua_toi ) >= 2, $ng_cua_toi );
t( '🔴 KHÔNG thấy ngày của người khác', ( function ( $ds ) {
	foreach ( $ds as $x ) { if ( isset( $x['maNV'] ) && 'LGNV' !== $x['maNV'] ) { return false; } }
	return true;
} )( $ng_cua_toi ) );

$r = VHCC_LoaiGio::gui( $NV, $CS, array( array( 'ngay' => $HOM, 'hauTo' => '', 'viec' => 'MC' ) ) );
t( '🔴 xin đổi sang ĐÚNG cái đang khai thì chối — đơn rỗng bắt người ta duyệt vô ích',
	empty( $r['ok'] ), $r );

$r = VHCC_LoaiGio::gui( $NV, $CS, array( array( 'ngay' => $HOM, 'hauTo' => '', 'viec' => 'Hỗ Trợ' ) ),
	'hôm ấy tôi đứng hỗ trợ' );
t( 'gửi được', ! empty( $r['ok'] ), $r );
teq( 'đúng một dòng', 1, $r['so'] );

/* 🔴 GỬI RỒI THÌ BẢNG CHẤM CÔNG CHƯA ĐỔI. Đây là chốt giữ cho người ta không tự đặt đơn giá
   cho chính mình bằng đường vòng. */
$hang = VHCC_DB::rows( $wpdb->prepare( 'SELECT loai_gio FROM ' . VHCC_DB::t( 'cham_cong' )
	. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS, $HOM, 'LGNV' ) );
teq( '🔴 gửi xong bảng chấm công VẪN GIỮ cái cũ', 'MC', (string) $hang[0]['loai_gio'] );

$cho = VHCC_LoaiGio::ds_cho( $CHT, $CS );
teq( 'cửa hàng trưởng thấy một đơn chờ', 1, count( $cho ) );
$id_don = (int) $cho[0]['id'];
teq( 'đơn nhớ cả loại giờ CŨ để còn đối chiếu', 'MC', (string) $cho[0]['viec_cu'] );

t( '🔴 nhân viên KHÔNG xem được danh sách chờ của cơ sở', ! VHCC_LoaiGio::ds_cho( $NV, $CS ) );

/* ---- ai duyệt được ---- */
$r = VHCC_LoaiGio::duyet( $NV, $id_don, true );
t( '🔴 nhân viên KHÔNG tự duyệt đơn của mình', empty( $r['ok'] ), $r );
/* Nhân viên bị chặn ở CỬA QUYỀN trước, nên câu chối nói về bậc — đúng thứ tự: họ không duyệt
   được đơn của ai cả, chứ không riêng của mình. Chốt "không tự duyệt" canh ở ca cửa hàng
   trưởng bên dưới, nơi nó là chốt DUY NHẤT còn lại. */
t( 'và nói rõ là thiếu bậc', false !== mb_strpos( $r['error'], 'Cửa hàng trưởng' ), $r['error'] );

$r = VHCC_LoaiGio::duyet( $CHT, $id_don, false, 'x' );
t( '🔴 chối mà không nói vì sao thì không cho', empty( $r['ok'] ), $r );

$r = VHCC_LoaiGio::duyet( $CHT, $id_don, true );
t( 'cửa hàng trưởng duyệt được', ! empty( $r['ok'] ), $r );
$hang = VHCC_DB::rows( $wpdb->prepare( 'SELECT loai_gio FROM ' . VHCC_DB::t( 'cham_cong' )
	. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s', $CS, $HOM, 'LGNV' ) );
teq( '🔴 duyệt xong mới vào bảng chấm công', 'Hỗ Trợ', (string) $hang[0]['loai_gio'] );

$r = VHCC_LoaiGio::duyet( $CHT, $id_don, true );
t( '🔴 duyệt lại chính đơn ấy thì chối', empty( $r['ok'] ), $r );

/* 🔴 CỬA HÀNG TRƯỞNG CŨNG ĐI LÀM CA, và cũng có loại giờ. Tự duyệt là tự chọn đơn giá cho mình. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $QUA,
	'ma_nv' => 'LGCHT', 'ho_ten' => 'Chị Trưởng', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
/* Chính cửa hàng trưởng cũng phải được phân thì mới khai được — luật không có ngoại lệ cho
   người ngồi ghế duyệt. Ở đây họ tự phân cho mình, và đó là chuyện hợp lệ: `dat_phan()` là
   quyền cơ sở, còn chốt "không tự duyệt" mới là chốt canh tiền. */
VHCC_LoaiGio::dat_phan( $CHT, $CS, 'LGCHT', array( 'MC', 'Partime' ) );
$r = VHCC_LoaiGio::gui( $CHT, $CS, array( array( 'ngay' => $QUA, 'hauTo' => '', 'viec' => 'MC' ) ),
	'tôi dẫn chương trình hôm ấy' );
t( 'cửa hàng trưởng gửi được đơn cho chính mình', ! empty( $r['ok'] ), $r );
$cho_cht = VHCC_LoaiGio::ds_cho( $CHT, $CS );
$id_cht = 0;
foreach ( $cho_cht as $d ) { if ( 'LGCHT' === (string) $d['ma_nv'] ) { $id_cht = (int) $d['id']; } }
$r = VHCC_LoaiGio::duyet( $CHT, $id_cht, true );
t( '🔴 nhưng KHÔNG tự duyệt được đơn của chính mình', empty( $r['ok'] ), $r );
t( 'phải nhờ người khác', ! empty( VHCC_LoaiGio::duyet( $AD, $id_cht, true )['ok'] ) );

/* ---- ngày quá cũ, và ngày không có công ---- */
$qua_cu = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' )
	- ( VHCC_LoaiGio::NGAY_LUI_TOI_DA + 5 ) * 86400 );
$chan = VHCC_LoaiGio::vi_sao_khong_gui( $CS, 'LGNV', $qua_cu );
t( '🔴 ngày quá hạn lùi thì chối — tháng ấy chốt lương rồi', '' !== $chan, $chan );

$chua_toi = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) + 86400 );
t( '🔴 ngày mai thì càng chối', '' !== VHCC_LoaiGio::vi_sao_khong_gui( $CS, 'LGNV', $chua_toi ) );

$khong_cong = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 5 * 86400 );
$r = VHCC_LoaiGio::gui( $NV, $CS, array( array( 'ngay' => $khong_cong, 'hauTo' => '', 'viec' => 'MC' ) ),
	'xin đổi ngày không có công' );
t( '🔴 ngày KHÔNG có lượt chấm nào thì chối', empty( $r['ok'] ), $r );
t( 'và chỉ sang đơn Xin bù giờ', false !== mb_strpos( $r['error'], 'Xin bù' ), $r['error'] );

/* ================================================================= vào bảng lương */

echo "— vào bảng lương —\n";
/* Em Nhân Viên: hôm nay 9h Hỗ Trợ (đã duyệt ở trên), hôm qua 9h chưa khai. */
$gio = VHCC_LoaiGio::gio_theo_viec( $CS, $THANG );
t( 'có bản khai của LGNV', isset( $gio['lgnv'] ), $gio );
teq( '🔴 chỉ kể lượt ĐÃ KHAI — lượt trống thuộc về giờ chính', 9.0,
	isset( $gio['lgnv']['Hỗ Trợ'] ) ? (float) $gio['lgnv']['Hỗ Trợ'] : null );
t( 'lượt chưa khai không đẻ ra dòng nào', 1 === count( $gio['lgnv'] ), $gio['lgnv'] );

$b = VHCC_BangLuong::dung( $CS, $THANG );
t( 'dựng được bảng lương', ! empty( $b['ok'] ), $b );
$dong_nv = array();
foreach ( $b['dong'] as $d ) { if ( 'LGNV' === strtoupper( (string) $d['ma'] ) ) { $dong_nv[] = $d; } }
teq( '🔴 một dòng chính + một dòng Hỗ Trợ', 2, count( $dong_nv ) );
$chinh = null; $khac = null;
foreach ( $dong_nv as $d ) { if ( $d['laChinh'] ) { $chinh = $d; } else { $khac = $d; } }
t( 'có dòng chính', null !== $chinh, $dong_nv );
t( 'có dòng giờ khác', null !== $khac, $dong_nv );
teq( 'dòng khác mang đúng tên việc', 'Hỗ Trợ', $khac['cv'] );
teq( '🔴 và đúng số giờ đã khai', 9.0, (float) $khac['gio'] );
teq( '🔴 giờ chính = giờ tổng − giờ khác (18 − 9)', 9.0, (float) $chinh['gio'] );
teq( 'dòng khác ăn đơn giá của chính việc ấy', 30000.0, (float) $khac['gia'] );

/* 🔴 KHÔNG ĐẾM HAI LẦN. Kế toán gõ tay 2 giờ MC cho CHÍNH người đã tự khai — bản của nhân viên
   phải THẮNG TRỌN GÓI, không cộng thêm. Cộng cả hai là 9 + 2 = 11 giờ khác trên 18 giờ công. */
VHCC_ChotLuong::dat( $AD, $CS, $THANG, 'LGNV', array( array( 'viec' => 'MC', 'gio' => '2' ) ), 18 );
$b2 = VHCC_BangLuong::dung( $CS, $THANG );
$dong_nv2 = array();
foreach ( $b2['dong'] as $d ) { if ( 'LGNV' === strtoupper( (string) $d['ma'] ) ) { $dong_nv2[] = $d; } }
teq( '🔴 vẫn đúng HAI dòng — bản nhân viên khai thắng, không cộng dồn', 2, count( $dong_nv2 ) );
$tong_khac = 0.0;
foreach ( $dong_nv2 as $d ) { if ( ! $d['laChinh'] ) { $tong_khac += (float) $d['gio']; } }
teq( '🔴 tổng giờ khác vẫn là 9, không phải 11', 9.0, $tong_khac );

/* Người CHƯA tự khai thì vẫn đọc bản kế toán gõ — chuyển đổi không được làm mất ai. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $QUA,
	'ma_nv' => 'LGNV2', 'ho_ten' => 'Em Thứ Hai', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
VHCC_ChotLuong::dat( $AD, $CS, $THANG, 'LGNV2', array( array( 'viec' => 'MC', 'gio' => '3' ) ), 9 );
$b3 = VHCC_BangLuong::dung( $CS, $THANG );
$khac2 = 0.0;
foreach ( $b3['dong'] as $d ) {
	if ( 'LGNV2' === strtoupper( (string) $d['ma'] ) && ! $d['laChinh'] ) { $khac2 += (float) $d['gio']; }
}
teq( '🔴 người CHƯA tự khai vẫn đọc bản kế toán gõ (3h MC)', 3.0, $khac2 );

/* ================================================================= không đi tắt */

echo "— không đi tắt —\n";
$src = '';
foreach ( token_get_all( file_get_contents(
	$goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-loai-gio.php' ) ) as $tk ) {
	if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
	$src .= is_array( $tk ) ? $tk[1] : $tk;
}
/* 🔴 CHỈ ĐƯỢC ĐỤNG VÀO ĐÚNG MỘT CỘT. Lớp này ghi thẳng `UPDATE cham_cong` (cố ý — nó không sửa
   GIỜ nên không phải đi qua `VHCC_Bu`), nhưng chỉ đặt `loai_gio`. Chạm vào giờ ở đây là lách
   qua toàn bộ nhật ký và chốt không-tự-sửa-giờ-mình. */
t( '🔴 mọi lượt UPDATE cham_cong chỉ đặt loai_gio',
	! preg_match( '#UPDATE[^;]*cham_cong[^;]*SET(?![^;]*loai_gio=%s)#i', $src )
	&& ! preg_match( '#cham_cong[^;]*SET[^;]*gio_(vao|ra)_giay#i', $src ), 'có lượt ghi giờ' );
t( '🔴 KHÔNG có INSERT/DELETE nào lên bảng cham_cong',
	! preg_match( "#(INSERT|DELETE)[^;]*cham_cong#i", $src ) );
t( 'danh sách việc lấy từ chính sổ đơn giá, không dựng sổ thứ hai',
	false !== strpos( $src, 'VHCC_GiaGio::' ) );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — nhân viên tự khai loại giờ, cửa hàng trưởng duyệt, và bảng lương không đếm hai lần.\n";
