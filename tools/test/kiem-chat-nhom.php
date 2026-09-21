<?php
/**
 * KIỂM CHAT NHÓM THEO CƠ SỞ + DANH BẠ TRÊN TRẠM.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CỬA NÀY MỞ MỘT KHO CHỮ RA CHO BẬC QUYỀN THẤP NHẤT TRONG HỆ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Nhân viên thường gõ được vào đây, và những gì họ gõ thì người cùng cơ sở đọc được. Nên chỗ
 * hẹp không nằm ở việc gửi hay nhận — nó nằm ở BỐN câu hỏi:
 *
 *   · đọc được phòng của cơ sở MÌNH KHÔNG LÀM không?
 *   · gửi được vào phòng ấy không?
 *   · xoá được lời của NGƯỜI KHÁC không? (kể cả Admin — xem chú thích ở `VHCC_Chat::xoa`)
 *   · đếm "chưa đọc" có tính cả tin của chính mình không?
 *
 * Ba câu đầu là chuyện quyền. Câu thứ tư nhỏ hơn nhưng hỏng thì cả tính năng mất uy tín: gửi
 * xong mà app báo "1 tin chưa đọc" thì lần sau không ai tin con số ấy nữa.
 *
 * Chạy: php tools/test/kiem-chat-nhom.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
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
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

global $wpdb;

$CS_A = 'CHAT_SHOP_A';
$CS_B = 'CHAT_SHOP_B';

$nv = function ( $ma, $ten, $cs, $pin, $phu = '', $vai = 'Nhân viên' ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $cs, 'coso_phu' => $phu,
		'vai_tro' => $vai, 'pin_dang_nhap' => $pin, 'sdt' => '0900000000',
		'chuc_vu' => 'Nhân viên quầy', 'trang_thai_lam_viec' => 'Đang làm' ) );
};
$nv( 'CH_A1', 'Người A Một', $CS_A, '910001' );
$nv( 'CH_A2', 'Người A Hai', $CS_A, '910002' );
$nv( 'CH_B1', 'Người B Một', $CS_B, '910003' );
/* Người làm HAI nơi — phải có HAI phòng. */
$nv( 'CH_AB', 'Người Hai Nơi', $CS_A, '910004', $CS_B );
$nv( 'CH_AD', 'Sếp Tổng', $CS_A, '910005', '', 'Admin' );

$phien = function ( $pin ) { return VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( $pin )['token'] ); };
$A1 = $phien( '910001' );
$A2 = $phien( '910002' );
$B1 = $phien( '910003' );
$AB = $phien( '910004' );
$AD = $phien( '910005' );
t( 'năm người đăng nhập được', $A1 && $A2 && $B1 && $AB && $AD, array( $A1, $B1 ) );

/* ═══════════════════════════════════════════════════════════ 1. PHÒNG LÀ CƠ SỞ */

t( 'A1 có đúng một phòng', array( $CS_A ) === VHCC_Chat::phong_cua( $A1 ), VHCC_Chat::phong_cua( $A1 ) );
/* 🔴 Người làm hai nơi thì có HAI phòng — hai cửa hàng ấy không liên quan gì nhau, gộp chung
   một phòng là cửa hàng này đọc chuyện cửa hàng kia. */
$p_ab = VHCC_Chat::phong_cua( $AB );
t( '🔴 người làm hai nơi có HAI phòng', 2 === count( $p_ab ), $p_ab );
t( '   và đúng hai cơ sở ấy', in_array( $CS_A, $p_ab, true ) && in_array( $CS_B, $p_ab, true ), $p_ab );

t( 'A1 vào được phòng A', VHCC_Chat::duoc_vao( $A1, $CS_A ) );
t( '🔴 A1 KHÔNG vào được phòng B', ! VHCC_Chat::duoc_vao( $A1, $CS_B ) );
t( 'cơ sở không có thật -> không vào được', ! VHCC_Chat::duoc_vao( $A1, 'KHONG_CO_THAT' ) );
t( 'tên cơ sở rỗng -> không vào được', ! VHCC_Chat::duoc_vao( $A1, '' ) );

/* ═══════════════════════════════════════════════════════════════ 2. GỬI VÀ ĐỌC */

$r = VHCC_Chat::gui( $A1, $CS_A, 'Chào cả nhà, hôm nay ca chiều đổi người nhé.' );
t( 'gửi được vào phòng của mình', ! empty( $r['ok'] ), $r );
$id1 = (int) $r['id'];

$r = VHCC_Chat::gui( $A2, $CS_A, 'Ok anh.' );
t( 'người thứ hai gửi được', ! empty( $r['ok'] ), $r );

$d = VHCC_Chat::ds( $A1, $CS_A );
t( 'đọc được hai tin', ! empty( $d['ok'] ) && 2 === count( $d['ds'] ), $d );
t( 'tin xếp theo thứ tự cũ -> mới', $d['ds'][0]['id'] < $d['ds'][1]['id'], $d );
t( 'tin của mình được đánh dấu là của mình', ! empty( $d['ds'][0]['cuaToi'] ), $d['ds'][0] );
t( '   và tin của người khác thì không', empty( $d['ds'][1]['cuaToi'] ), $d['ds'][1] );
/* Chép tên vào hàng: đọc lại phải ra đúng tên lúc nhắn, không tra lại hồ sơ. */
t( 'tin mang tên người gửi', 'Người A Một' === $d['ds'][0]['hoTen'], $d['ds'][0] );

/* ═════════════════════════════════════════════ 3. 🔴 GÁC CẢ LÚC ĐỌC, KHÔNG CHỈ LÚC GỬI */

/* Gác mỗi lúc gửi là ai gõ đúng tên cơ sở cũng đọc được cả lịch sử — mà tên cơ sở thì in đầy
   trên mọi màn. */
$d = VHCC_Chat::ds( $B1, $CS_A );
t( '🔴 người cơ sở khác KHÔNG đọc được phòng này', empty( $d['ok'] ), $d );
t( '   và câu chối nói rõ là thiếu quyền',
	! empty( $d['error'] ) && false !== mb_strpos( $d['error'], 'quyền' ), $d );

$r = VHCC_Chat::gui( $B1, $CS_A, 'Tôi chen vào được không' );
t( '🔴 người cơ sở khác KHÔNG gửi được vào phòng này', empty( $r['ok'] ), $r );
$d = VHCC_Chat::ds( $A1, $CS_A );
t( '   và phòng vẫn chỉ có hai tin', 2 === count( $d['ds'] ), $d );

/* Người làm hai nơi thì vào được cả hai. */
t( 'người hai nơi đọc được phòng A', ! empty( VHCC_Chat::ds( $AB, $CS_A )['ok'] ) );
t( 'người hai nơi đọc được phòng B', ! empty( VHCC_Chat::ds( $AB, $CS_B )['ok'] ) );

/* ═══════════════════════════════════════════════════ 4. XOÁ: CHỈ CỦA MÌNH, TRONG HẠN */

$r = VHCC_Chat::xoa( $A2, $id1 );
t( '🔴 KHÔNG xoá được tin của người khác', empty( $r['ok'] ), $r );
/* 🔴 KỂ CẢ ADMIN. Cho quản lý xoá lời của người khác là biến cuốn sổ này thành thứ sửa được —
   và lúc ấy nó không làm chứng cho ai được nữa, kể cả cho quản lý. */
$r = VHCC_Chat::xoa( $AD, $id1 );
t( '🔴 ADMIN cũng KHÔNG xoá được tin của người khác', empty( $r['ok'] ), $r );

$r = VHCC_Chat::xoa( $A1, $id1 );
t( 'tự xoá được tin của mình', ! empty( $r['ok'] ), $r );
$d = VHCC_Chat::ds( $A1, $CS_A );
t( '🔴 xoá MỀM: tin vẫn còn chỗ, không biến mất khỏi mạch', 2 === count( $d['ds'] ), $d );
t( '   và được đánh dấu đã xoá', ! empty( $d['ds'][0]['daXoa'] ), $d['ds'][0] );
t( '   nội dung đã sạch', '' === $d['ds'][0]['chu'], $d['ds'][0] );

/* Quá hạn thì thôi — đẩy lùi giờ tạo của một tin mới. */
$r = VHCC_Chat::gui( $A1, $CS_A, 'Tin cũ' );
$id_cu = (int) $r['id'];
$wpdb->update( VHCC_DB::t( 'chat_tin' ),
	array( 'tao_luc' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' -3 hour' ) ) ),
	array( 'id' => $id_cu ) );
$r = VHCC_Chat::xoa( $A1, $id_cu );
t( '🔴 quá hạn thì KHÔNG xoá được nữa', empty( $r['ok'] ), $r );
t( '   và câu chối nói rõ là quá hạn',
	! empty( $r['error'] ) && false !== mb_strpos( $r['error'], 'phút' ), $r );

/* ═══════════════════════════════════════════════════════════ 5. ĐẾM CHƯA ĐỌC */

/* A2 đã đọc tới đâu thì đọc; giờ A1 gửi thêm một tin. */
VHCC_Chat::ds( $A2, $CS_A );
$r = VHCC_Chat::gui( $A1, $CS_A, 'Tin mới nhất' );
$cd = VHCC_Chat::chua_doc( $A2 );
t( 'A2 có một tin chưa đọc', isset( $cd[ $CS_A ] ) && 1 === (int) $cd[ $CS_A ], $cd );

/* 🔴 KHÔNG đếm tin của CHÍNH MÌNH. Gửi xong mà app báo "1 tin chưa đọc" thì lần sau không ai
   tin con số ấy nữa. */
$cd = VHCC_Chat::chua_doc( $A1 );
t( '🔴 người vừa gửi KHÔNG có tin chưa đọc', empty( $cd[ $CS_A ] ), $cd );

VHCC_Chat::ds( $A2, $CS_A );
t( 'đọc xong thì về 0', empty( VHCC_Chat::chua_doc( $A2 )[ $CS_A ] ), VHCC_Chat::chua_doc( $A2 ) );

/* ⚠️ MỐC ĐÃ ĐỌC CHỈ TIẾN, KHÔNG LÙI. Một lượt hỏi tới trễ mang `tuId` cũ không được kéo mốc
   lùi về — lùi một lần là mấy chục tin đã đọc bỗng "chưa đọc" trở lại. */
VHCC_Chat::ds( $A2, $CS_A, 1 );
t( '🔴 đọc lại trang cũ KHÔNG làm tin đã đọc thành chưa đọc',
	empty( VHCC_Chat::chua_doc( $A2 )[ $CS_A ] ), VHCC_Chat::chua_doc( $A2 ) );

/* ═════════════════════════════════════════════════════════ 6. TIN RỖNG / QUÁ DÀI */

t( 'tin rỗng bị chối', empty( VHCC_Chat::gui( $A1, $CS_A, '' )['ok'] ) );
/* ⚠️ Toàn dấu cách cũng là rỗng — nó lọt qua phép đo độ dài thô rồi hiện thành một bong bóng
   trống không ai xoá được ngoài chính người gửi. */
t( '🔴 tin toàn dấu cách cũng bị chối', empty( VHCC_Chat::gui( $A1, $CS_A, "   \n  " )['ok'] ) );
$dai = str_repeat( 'a', VHCC_Chat::DAI_TOI_DA + 1 );
t( 'tin quá dài bị chối', empty( VHCC_Chat::gui( $A1, $CS_A, $dai )['ok'] ) );
t( 'tin vừa đúng hạn thì được',
	! empty( VHCC_Chat::gui( $A1, $CS_A, str_repeat( 'b', VHCC_Chat::DAI_TOI_DA ) )['ok'] ) );

/* ⚠️ CẤT NGUYÊN VĂN, thoát lúc hiện. Cất bản đã thoát là tới lúc đọc lại thấy `&amp;` giữa câu. */
$tho = 'Giá 5 & 7 <b>nhé</b>';
$r = VHCC_Chat::gui( $A1, $CS_A, $tho );
$hang = $wpdb->get_row( $wpdb->prepare( 'SELECT chu FROM ' . VHCC_DB::t( 'chat_tin' )
	. ' WHERE id=%d', (int) $r['id'] ), ARRAY_A );
t( '🔴 cất NGUYÊN VĂN, không thoát sẵn', $tho === (string) $hang['chu'], $hang );

/* ═══════════════════════════════════════════════════════════════ 7. DANH BẠ */

$db = VHCC_NhanSu::danh_ba( $A1, $CS_A );
t( 'danh bạ cơ sở mình có người', count( $db ) >= 2, count( $db ) );
$cot = $db ? array_keys( $db[0] ) : array();
t( 'danh bạ có số điện thoại để bấm gọi', in_array( 'sdt', $cot, true ), $cot );
/* ⚠️ Đây là danh bạ để gọi nhau, KHÔNG phải hồ sơ nhân sự. */
foreach ( array( 'pin_dang_nhap', 'cccd', 'luong_co_ban', 'so_tai_khoan' ) as $cam ) {
	t( '🔴 danh bạ KHÔNG lộ ' . $cam, ! in_array( $cam, $cot, true ), $cot );
}
t( '🔴 danh bạ cơ sở mình KHÔNG làm -> rỗng', 0 === count( VHCC_NhanSu::danh_ba( $A1, $CS_B ) ) );

/* ═══════════════════════════════════ 8. CHAT RIÊNG HAI NGƯỜI (anh Thắng 20/09/2026) */

/* 🔴 PHÉP CHÍNH: KHOÁ PHÒNG KHÔNG PHỤ THUỘC THỨ TỰ.
   A mở chat với B và B mở chat với A phải rơi vào ĐÚNG MỘT phòng. Ghép theo thứ tự người gọi
   là dựng ra hai phòng, mỗi người thấy một nửa cuộc nói chuyện — và cả hai đều tưởng người kia
   không trả lời. Đây là lỗi im lặng nhất của cả tính năng. */
$p_ab1 = VHCC_Chat::phong_rieng( $CS_A, 'CH_A1', 'CH_A2' );
$p_ab2 = VHCC_Chat::phong_rieng( $CS_A, 'CH_A2', 'CH_A1' );
t( '🔴 đảo thứ tự hai người -> VẪN MỘT phòng', '' !== $p_ab1 && $p_ab1 === $p_ab2,
	array( $p_ab1, $p_ab2 ) );

t( 'tự nhắn cho mình -> không dựng phòng', '' === VHCC_Chat::phong_rieng( $CS_A, 'CH_A1', 'CH_A1' ) );
t( 'thiếu mã -> không dựng phòng', '' === VHCC_Chat::phong_rieng( $CS_A, 'CH_A1', '' ) );
/* ⚠️ Ký tự ngăn lọt vào là `tach_rieng()` đọc lệch và hai người rơi vào hai phòng. CHỐI thẳng,
   không cắt bỏ cho êm — cắt bỏ là hai mã khác nhau ra cùng một khoá. */
t( '🔴 mã có ký tự ngăn -> CHỐI, không cắt bỏ',
	'' === VHCC_Chat::phong_rieng( $CS_A, 'CH_A1', 'CH|A2' ) );

$t_tach = VHCC_Chat::tach_rieng( $p_ab1 );
t( 'tách lại ra đúng cơ sở', $t_tach && $CS_A === $t_tach['coSo'], $t_tach );
t( 'phòng cơ sở thường KHÔNG bị nhận nhầm là phòng riêng',
	null === VHCC_Chat::tach_rieng( $CS_A ) );

/* Hai người cùng cơ sở nhắn được cho nhau. */
$r = VHCC_Chat::gui( $A1, $p_ab1, 'Lát nữa đổi ca giúp mình nhé' );
t( 'A1 nhắn riêng được cho A2', ! empty( $r['ok'] ), $r );
$d = VHCC_Chat::ds( $A2, $p_ab2 );   /* A2 mở bằng khoá dựng theo thứ tự NGƯỢC */
t( '🔴 A2 mở bằng khoá thứ tự ngược VẪN thấy tin ấy',
	! empty( $d['ok'] ) && 1 === count( $d['ds'] ), $d );
t( '   và tin ấy KHÔNG phải của A2', empty( $d['ds'][0]['cuaToi'] ), $d['ds'][0] );

/* 🔴 NGƯỜI THỨ BA KHÔNG ĐỌC ĐƯỢC. Đây là toàn bộ ý nghĩa của chữ "riêng". */
$d = VHCC_Chat::ds( $AB, $p_ab1 );
t( '🔴 người thứ ba CÙNG cơ sở vẫn KHÔNG đọc được chat riêng', empty( $d['ok'] ), $d );
/* 🔴 KỂ CẢ ADMIN. */
$d = VHCC_Chat::ds( $AD, $p_ab1 );
t( '🔴 ADMIN cũng KHÔNG đọc được chat riêng của người khác', empty( $d['ok'] ), $d );
$r = VHCC_Chat::gui( $AD, $p_ab1, 'Sếp chen vào' );
t( '🔴 ADMIN cũng KHÔNG chen được vào', empty( $r['ok'] ), $r );

/* 🔴 KHÔNG MỞ ĐƯỢC CHAT RIÊNG VỚI NGƯỜI KHÁC CƠ SỞ. Không chốt thì nó thành một kênh riêng nằm
   ngoài mọi phép gác theo cơ sở. */
$p_lac = VHCC_Chat::phong_rieng( $CS_A, 'CH_A1', 'CH_B1' );
t( 'khoá dựng ra được (mã hợp lệ)', '' !== $p_lac );
t( '🔴 nhưng A1 KHÔNG vào được vì B1 không thuộc cơ sở A', ! VHCC_Chat::duoc_vao( $A1, $p_lac ) );
t( '   và gửi cũng bị chối', empty( VHCC_Chat::gui( $A1, $p_lac, 'thử' )['ok'] ) );

/* ⚠️ `LIKE` chỉ để thu hẹp, phải lọc lại bằng so sánh thật: mã `CH_A1` cũng khớp với khoá chứa
   `CH_A12`. Tin cho `LIKE` là mở phòng của người khác cho người này. */
$nv( 'CH_A12', 'Người A Mười Hai', $CS_A, '910012' );
$A12 = $phien( '910012' );
$p_dai = VHCC_Chat::phong_rieng( $CS_A, 'CH_A12', 'CH_A2' );
VHCC_Chat::gui( $A12, $p_dai, 'Tin của A12 với A2' );
$ds_r = VHCC_Chat::rieng_cua( $A1 );
$co_lac = false;
foreach ( $ds_r as $x ) { if ( $x['phong'] === $p_dai ) { $co_lac = true; } }
t( '🔴 mã CH_A1 KHÔNG kéo nhầm phòng của CH_A12 (bẫy LIKE)', ! $co_lac, $ds_r );

/* Danh sách cuộc riêng của A2 phải có cả hai cuộc. */
$ds_r2 = VHCC_Chat::rieng_cua( $A2 );
t( 'A2 thấy hai cuộc nói chuyện riêng', 2 === count( $ds_r2 ), $ds_r2 );
$ten = array();
foreach ( $ds_r2 as $x ) { $ten[] = $x['maKia']; }
sort( $ten );
t( '   đúng hai người kia', array( 'CH_A1', 'CH_A12' ) === $ten, $ten );

/* Đếm chưa đọc của cuộc riêng.
   ⚠️ ĐÚNG MỘT, không phải hai: cuộc với A1 đã được A2 đọc ở khối trên ("A2 mở bằng khoá thứ tự
      ngược"). Viết `>= 2` ở đây là phép thử tự nói dối — nó sẽ xanh cả khi phép đếm cộng nhầm. */
$chua = 0;
foreach ( VHCC_Chat::rieng_cua( $A2 ) as $x ) { $chua += (int) $x['chuaDoc']; }
t( 'A2 có đúng MỘT tin riêng chưa đọc (cuộc kia đã đọc rồi)', 1 === $chua,
	VHCC_Chat::rieng_cua( $A2 ) );
VHCC_Chat::ds( $A2, $p_ab1 );
VHCC_Chat::ds( $A2, $p_dai );
$chua = 0;
foreach ( VHCC_Chat::rieng_cua( $A2 ) as $x ) { $chua += (int) $x['chuaDoc']; }
t( 'đọc xong thì về 0', 0 === $chua, VHCC_Chat::rieng_cua( $A2 ) );

/* ═══════════════════════════════════ 9. ĐÍNH KÈM ẢNH VÀ TỆP (anh Thắng 21/09/2026) */

/* Một tấm PNG 1x1 thật — phải là ảnh THẬT, vì `luu_tep()` đọc nội dung chứ không tin cái đuôi. */
$PNG = base64_decode(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$b64 = function ( $nhi ) { return base64_encode( $nhi ); };

$r = VHCC_Chat::gui( $A1, $CS_A, 'Ảnh ca sáng', $b64( $PNG ), 'ca-sang.png' );
t( 'gửi được tin kèm ảnh', ! empty( $r['ok'] ), $r );
$id_anh = (int) $r['id'];

$d = VHCC_Chat::ds( $A1, $CS_A );
$tin_anh = null;
foreach ( $d['ds'] as $x ) { if ( (int) $x['id'] === $id_anh ) { $tin_anh = $x; } }
t( 'tin mang theo thông tin tệp', $tin_anh && ! empty( $tin_anh['tep'] ), $tin_anh );
t( '   biết đó là ảnh', $tin_anh && ! empty( $tin_anh['tep']['anh'] ), $tin_anh );
t( '   giữ tên gốc để hiện', $tin_anh && 'ca-sang.png' === $tin_anh['tep']['ten'], $tin_anh );
t( '   và biết kích thước', $tin_anh && (int) $tin_anh['tep']['co'] === strlen( $PNG ), $tin_anh );

/* Gửi MỖI ảnh, không gõ chữ — chuyện thường, không được chối. */
t( '🔴 gửi mỗi tệp không kèm chữ vẫn được',
	! empty( VHCC_Chat::gui( $A1, $CS_A, '', $b64( $PNG ), 'khong-loi.png' )['ok'] ) );

/* 🔴 SVG BỊ CHỐI, DÙ NÓ LÀ ẢNH. Tệp SVG chứa được `<script>`; phục vụ nó inline là mở một lỗ
   chèn mã ngay trong tên miền công ty — và nó lọt qua mọi phép kiểm "có phải ảnh không" viết
   theo kiểu thông thường. */
$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
t( '🔴 CHỐI tệp .svg', empty( VHCC_Chat::gui( $A1, $CS_A, 'x', $b64( $svg ), 'a.svg' )['ok'] ) );
/* 🔴 VÀ CANH THẲNG DANH SÁCH. Phép thử ngay trên KHÔNG ĐỦ, và đã phá thử để biết: thêm `svg`
   vào danh sách nhận thì nó VẪN XANH — vì PHP không đọc nổi SVG như một tấm ảnh nên phép kiểm
   nội dung tình cờ chối hộ. Một phép thử xanh nhờ lý do khác là một phép thử không canh gì cả.
   `svg` phải KHÔNG BAO GIỜ có trong danh sách, dù phép kiểm nội dung có nói gì. */
t( '🔴 `svg` KHÔNG có trong danh sách kiểu tệp nhận',
	! array_key_exists( 'svg', VHCC_Chat::TEP_KIEU ), array_keys( VHCC_Chat::TEP_KIEU ) );
foreach ( array( 'html', 'htm', 'php', 'js', 'xml', 'xhtml' ) as $cam_t ) {
	t( '🔴 `' . $cam_t . '` KHÔNG có trong danh sách', ! array_key_exists( $cam_t, VHCC_Chat::TEP_KIEU ) );
}
t( '🔴 CHỐI tệp .html', empty( VHCC_Chat::gui( $A1, $CS_A, 'x', $b64( '<h1>hi' ), 'a.html' )['ok'] ) );
t( '🔴 CHỐI tệp .php', empty( VHCC_Chat::gui( $A1, $CS_A, 'x', $b64( '<?php echo 1;' ), 'a.php' )['ok'] ) );
t( '🔴 CHỐI tệp không đuôi', empty( VHCC_Chat::gui( $A1, $CS_A, 'x', $b64( 'abcdefgh' ), 'abc' )['ok'] ) );

/* 🔴 ĐỔI ĐUÔI KHÔNG LỪA ĐƯỢC. Một tệp .html đổi tên thành .png lọt qua phép kiểm đuôi, nhưng
   `getimagesizefromstring()` đọc chính nội dung nên nó không bị lừa. */
$r = VHCC_Chat::gui( $A1, $CS_A, 'x', $b64( '<html><script>alert(1)</script>' ), 'gia.png' );
t( '🔴 tệp khai là ảnh mà nội dung không phải ảnh -> CHỐI', empty( $r['ok'] ), $r );
t( '   và câu chối nói đúng lý do',
	! empty( $r['error'] ) && false !== mb_strpos( $r['error'], 'không phải ảnh' ), $r );

/* Tệp tài liệu thì nhận, và KHÔNG bị coi là ảnh. */
$r = VHCC_Chat::gui( $A1, $CS_A, 'Bảng kê', $b64( "cot1,cot2\n1,2\n" ), 'ke.csv' );
t( 'nhận tệp csv', ! empty( $r['ok'] ), $r );
$d = VHCC_Chat::ds( $A1, $CS_A );
$tin_csv = null;
foreach ( $d['ds'] as $x ) { if ( (int) $x['id'] === (int) $r['id'] ) { $tin_csv = $x; } }
t( '🔴 csv KHÔNG được đánh dấu là ảnh (nó sẽ tải về, không hiện inline)',
	$tin_csv && empty( $tin_csv['tep']['anh'] ), $tin_csv );

/* Quá lớn thì chối, kèm câu nói rõ. */
$r = VHCC_Chat::gui( $A1, $CS_A, 'x',
	$b64( str_repeat( 'a', VHCC_Chat::TEP_TOI_DA + 10 ) ), 'to.txt' );
t( 'tệp quá lớn bị chối', empty( $r['ok'] ), $r );
t( '   và nói rõ giới hạn', ! empty( $r['error'] ) && false !== mb_strpos( $r['error'], 'MB' ), $r );

/* ⚠️ TÊN NGƯỜI DÙNG ĐẶT KHÔNG BAO GIỜ CHẠM TỚI ĐĨA. `../../wp-config.php` là một cái tên hợp
   lệ với người dùng. */
$r = VHCC_Chat::gui( $A1, $CS_A, 'x', $b64( $PNG ), '../../../evil.png' );
t( 'tên có ../ vẫn gửi được (tên chỉ để hiện)', ! empty( $r['ok'] ), $r );
$hang_t = $wpdb->get_row( $wpdb->prepare( 'SELECT tep, tep_ten FROM ' . VHCC_DB::t( 'chat_tin' )
	. ' WHERE id=%d', (int) $r['id'] ), ARRAY_A );
t( '🔴 đường trên đĩa KHÔNG chứa ../', false === strpos( (string) $hang_t['tep'], '..' ), $hang_t );
t( '🔴 tên trên đĩa là chuỗi ngẫu nhiên, không phải tên người đặt',
	1 === preg_match( '#^vhcc-chat/\d{4}-\d{2}/[0-9a-f]{32}\.png$#', (string) $hang_t['tep'] ),
	$hang_t );
t( '   tên gốc đã bị làm sạch', false === strpos( (string) $hang_t['tep_ten'], '/' ), $hang_t );

/* 🔴 XOÁ TIN THÌ XOÁ CẢ TỆP. Đánh dấu đã xoá mà để tệp nằm lại là người đã bấm xoá vẫn còn cái
   ảnh của mình trên máy chủ. */
$r = VHCC_Chat::gui( $A1, $CS_A, 'sắp xoá', $b64( $PNG ), 'sap-xoa.png' );
$id_xoa = (int) $r['id'];
$duong_xoa = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT tep FROM ' . VHCC_DB::t( 'chat_tin' )
	. ' WHERE id=%d', $id_xoa ) );
$up_t = wp_upload_dir();
t( 'tệp có thật trên đĩa', file_exists( $up_t['basedir'] . '/' . $duong_xoa ), $duong_xoa );
VHCC_Chat::xoa( $A1, $id_xoa );
t( '🔴 xoá tin -> tệp biến mất khỏi đĩa',
	! file_exists( $up_t['basedir'] . '/' . $duong_xoa ), $duong_xoa );
$d = VHCC_Chat::ds( $A1, $CS_A );
foreach ( $d['ds'] as $x ) {
	if ( (int) $x['id'] === $id_xoa ) {
		t( '   và tin không còn trỏ tới tệp nào', null === $x['tep'], $x );
	}
}

/* ═══════════════════════════ 10. TỆP CHỈ NGƯỜI TRONG PHÒNG MỚI XEM ĐƯỢC */

/* 🔴 ĐÂY LÀ CHỖ DỄ BỎ QUÊN NHẤT. Phòng thì khoá, mà nếu tệp nằm ở một địa chỉ công khai trong
   `uploads` thì nội dung trong phòng để ngoài cửa — cái gác vừa dựng thành vô nghĩa. */
$src_c = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-chat.php' );
t( 'có hàm phục vụ tệp riêng', false !== strpos( $src_c, 'function xem_tep' ) );
$i_x = strpos( $src_c, 'public static function xem_tep' );
$khoi_x = substr( $src_c, $i_x, 1800 );
t( '🔴 phục vụ tệp có hỏi `duoc_vao()`', false !== strpos( $khoi_x, 'self::duoc_vao(' ), $khoi_x );
t( '🔴 và hỏi TRƯỚC khi đọc đĩa',
	strpos( $khoi_x, 'duoc_vao(' ) < strpos( $khoi_x, 'readfile(' ), $khoi_x );
t( '🔴 chốt đường dẫn bằng realpath (chặn ../)', false !== strpos( $khoi_x, 'realpath(' ) );
t( '🔴 chỉ ảnh mới inline, còn lại attachment',
	false !== strpos( $khoi_x, 'attachment' ) && false !== strpos( $khoi_x, 'inline' ), $khoi_x );
t( '⚠️ có nosniff (trình duyệt thôi tự đoán kiểu tệp)',
	false !== strpos( $khoi_x, 'nosniff' ), $khoi_x );
/* Danh sách kiểu tệp phải là ALLOWLIST cố định, không suy từ tên tệp. */
t( '🔴 kiểu nội dung lấy từ bảng cố định, không từ lời khai của client',
	false !== strpos( $src_c, 'const TEP_KIEU' )
	&& false === strpos( $src_c, 'mime_content_type' ), $src_c ? '' : '' );

/* ═════════════════════════════════════════════ 11. CỬA TRẠM ĐI QUA GÁC CỦA LỚP */

/* 🔴 Viết một phép kiểm quyền ngay tại cổng là dựng bản thứ hai của một luật đã có, và hai bản
   ấy sẽ lệch. Cổng phải uỷ thẳng cho lớp. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
foreach ( array( 'chat_phong', 'chat_ds', 'chat_gui', 'chat_xoa', 'chat_mo', 'chat_tep', 'danhba' ) as $cua ) {
	t( 'cổng có cửa `' . $cua . '`', false !== strpos( $src, "'" . $cua . "' === \$viec" ) );
}
$i_c = strpos( $src, "'chat_ds' === \$viec" );
$khoi = substr( $src, $i_c, 320 );
t( '🔴 cửa chat_ds uỷ thẳng cho VHCC_Chat, không tự gác',
	false !== strpos( $khoi, 'VHCC_Chat::ds(' ) && false === strpos( $khoi, 'co_quyen_coso' ), $khoi );

/* 🔴 CLIENT KHÔNG ĐƯỢC GỬI THẲNG KHOÁ PHÒNG RIÊNG LÊN. Khoá dựng ở máy chủ thì nó luôn đi qua
   `phong_rieng()` — nơi sắp xếp hai mã và chối ký tự lạ. Nhận khoá từ client là nhận luôn một
   khoá tự chế. */
$i_m = strpos( $src, "'chat_mo' === \$viec" );
$khoi_m = substr( $src, $i_m, 700 );
t( '🔴 cửa chat_mo tự dựng khoá từ MÃ, không nhận khoá từ client',
	false !== strpos( $khoi_m, 'VHCC_Chat::phong_rieng(' )
	&& false === strpos( $khoi_m, "\$b['phong']" ), $khoi_m );

/* 🔴 CỬA XEM TỆP PHẢI NẰM TRÊN DÒNG GIẢI THẺ CHUNG.
   `than()` chỉ đọc thân yêu cầu, mà `<img src>` không gửi được thân. Để nhánh ấy ở dưới thì
   cổng chối trước khi tới nó và mọi tấm ảnh trong chat hiện ra một ô vỡ — đã viết sai đúng như
   vậy một lần, và không phép thử nào cũ bắt được vì phần PHP vẫn đúng. */
$i_tep = strpos( $src, "'chat_tep' === \$viec" );
$i_the = strpos( $src, "\$u = self::nguoi( isset( \$b['token'] )" );
t( 'cổng có cửa xem tệp', false !== $i_tep );
t( '🔴 cửa xem tệp nằm TRÊN dòng giải thẻ chung', $i_tep && $i_the && $i_tep < $i_the,
	'tep=' . (int) $i_tep . ' the=' . (int) $i_the );
/* ⚠️ Và CHỈ cửa ấy nhận thẻ qua `?token=`. Nới cho cả cổng là đưa thẻ phiên vào thanh địa chỉ
   của mọi lượt gọi — nhật ký máy chủ, lịch sử trình duyệt, tiêu đề Referer. */
/* ⚠️ Đếm trên bản ĐÃ GỠ CHÚ THÍCH. Bản đầu đếm trên văn bản thô và ra 3 — hai trong số đó nằm
   trong chính khối chú thích giải thích vì sao chỉ được có một. Lần thứ ba trong dự án này một
   phép thử bắt nhầm chữ trong chú thích; đếm thô là một thói quen phải bỏ. */
/* ⚠️ Đếm DÒNG, không đếm lần xuất hiện: một dòng dùng nó hai lần (`isset()` rồi `wp_unslash()`)
   là bình thường, mà đếm lần thì ra 2 và phép thử đỏ oan. Thứ cần canh là "có bao nhiêu CHỖ
   trong cổng đọc thẻ từ thanh địa chỉ", tức là bao nhiêu dòng. */
$src_ma = preg_replace( '#/\*.*?\*/#s', '', $src );
$dong_get = 0;
foreach ( explode( "\n", $src_ma ) as $d_x ) {
	if ( false !== strpos( $d_x, "\$_GET['token']" ) ) { $dong_get++; }
}
t( '🔴 chỉ MỘT dòng trong cổng đọc token từ $_GET', 1 === $dong_get, $dong_get );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — phòng là cơ sở, và không ai đọc được phòng mình không thuộc về.\n";
