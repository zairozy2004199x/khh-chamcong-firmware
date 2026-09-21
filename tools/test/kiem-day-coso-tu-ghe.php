<?php
/**
 * TẠO CƠ SỞ BÊN GHẾ -> TỰ ĐẨY SANG DANH MỤC CƠ SỞ CỦA CHI PHÍ, GẮN SẴN ĐƠN VỊ POSH.
 *
 * Anh Thắng 08/09/2026: *"tự đẩy lấy dữ liệu qua luôn, khi tạo cơ sở mới bên ghế, hệ thống tự
 * đẩy cơ sở sang luôn"* — kèm ảnh màn "Quản lý ghế → ĐỊA ĐIỂM" của trang POSH với hàng chục
 * gian (Aeon Bình Tân · AEON MALL BÌNH DƯƠNG · BỆNH VIỆN 175 · Cali Thảo Điền…).
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI TỰ ĐẨY. Bên chi phí, DANH MỤC CƠ SỞ là nơi vạch ranh giới K&H / POSH
 *    (`VHCP_DonVi::cua_coso()`). Gian nào chưa có trong danh mục ấy thì `cua_coso()` trả về nhà
 *    mặc định — tức mọi đồng chi cho gian POSH nằm trong sổ của K&H, và không ai thấy để sửa.
 *    Bắt kế toán gõ tay hàng chục gian là bắt họ làm một việc chắc chắn sót.
 *
 * 🔴 CHỈ THÊM — KHÔNG SỬA, KHÔNG XOÁ, KHÔNG ĐÈ. Ô MISA là thứ kế toán ngồi khai tay; đè lên
 *    bằng dòng trắng của bên ghế là xoá công của họ một cách lặng lẽ. Còn xoá dòng cũ khi bên
 *    ghế đổi tên là mấy đơn cũ (tra theo TÊN) rơi về nhà mặc định.
 *
 * ⚠️ CHẠY THẬT CẢ HAI PLUGIN, đi qua đúng móc thật: gọi `VHG_May::luu_coso()` rồi đòi dòng ấy
 *    có mặt bên chi phí. Canh chuỗi trong mã thì bỏ `do_action` đi vẫn xanh.
 *
 * Chạy: php tools/test/kiem-day-coso-tu-ghe.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
global $wpdb;

/* ═══ CÔNG TẮC: BẢN NÀY CÓ LẤY CƠ SỞ TỪ GHẾ KHÔNG ═══════════════════════════
 *
 * 🔴 BẢN GỐC (KHU VUI CHƠI) ĐÃ TẮT ĐƯỜNG NÀY TỪ 14/09/2026 — anh Thắng: *"nó thuộc bộ phận
 *    khác"*, *"bỏ vào đây là người khác khai sai"*. Nhưng CƠ CHẾ thì vẫn sống nguyên ở bản Máy
 *    tự động, nên tệp này phải tiếp tục thử nó — tắt bài kiểm theo là mất sạch lưới an toàn cho
 *    đúng cái bản đang dùng nó hằng ngày.
 *
 * ⚠️ BẬT BẰNG KHOÁ CẤU HÌNH, không sửa hằng trong mã. `VHCP_Cfg::lay_coso_ghe()` đọc khoá
 *    `vhcp_lay_coso_ghe` trước rồi mới ngả về hằng — đúng đường một site thật bật lại.
 * ════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 BẬT LẠI 21/09/2026 (anh Thắng: *"đẩy cơ sở bên ghế sang nhé"*) — ba khối nay chung một
   bản cài nên gian ghế về đúng bảng Máy tự động của nó. */
teq( '🔴 bản gốc nay BẬT sẵn đường hút', true, VHCP_Cfg::LAY_COSO_GHE );
update_option( 'vhcp_lay_coso_ghe', 0 );
t( '⚠️ và khoá cấu hình vẫn TẮT được cho một site cụ thể (VD văn phòng)',
	! VHCP_Cfg::lay_coso_ghe() );
update_option( 'vhcp_lay_coso_ghe', 1 );
t( '   rồi bật lại cũng được', VHCP_Cfg::lay_coso_ghe() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. NẠP PLUGIN GHẾ + DỰNG BẢNG CỦA NÓ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
define( 'VHG_TEST', 1 );
define( 'VHG_VERSION', 'test' );
define( 'VHG_DIR', $goc . '/wordpress/vhcp-ghe/' );
define( 'VHG_KHOA_WEBHOOK', 'k1' );
define( 'VHG_KHOA_MAY', 'k2' );
foreach ( array( 'db', 'doc', 'may' ) as $f ) { require_once VHG_DIR . 'includes/class-vhg-' . $f . '.php'; }
/* Dựng KÈM CẢ KHOÁ — xem `vhcp_stub_dung_bang()` trong wp-stub.php. Một lối dựng bảng
   DUY NHẤT cho mọi bài: hai lối khác nhau là hai bộ thử nói về hai lược đồ khác nhau. */
vhcp_stub_dung_bang( VHG_DB::bang(), $wpdb->prefix . 'vhg_' );
t( 'nạp được lớp VHG_May của plugin Ghế', class_exists( 'VHG_May' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 ĐẤU DÂY ĐÚNG NHƯ TỆP BOOTSTRAP THẬT — KHÔNG CHÉP TAY
 *
 * Bốc cặp (tên móc · tai nghe) ra từ chính `vhcp-chi-phi.php` rồi mới đăng ký. Chép tay là bỏ
 * hẳn dòng `add_action` khỏi plugin mà bài kiểm vẫn xanh — đúng loại xanh oan tệ nhất.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$boot = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/vhcp-chi-phi.php' );
$co   = preg_match( "#add_action\(\s*'([a-z_]+)'\s*,\s*array\(\s*'VHCP_Cfg'\s*,\s*'([a-z_]+)'\s*\)\s*\)\s*;#", $boot, $m );
t( '🔴 plugin chi phí CÓ đăng ký nghe móc của plugin ghế', (bool) $co, substr( $boot, -400 ) );
if ( ! $co ) {
	echo "\n✗ TRƯỢT: không tìm thấy add_action trong vhcp-chi-phi.php\n";
	exit( 1 );
}
teq( 'nghe đúng tên móc mà bên ghế bắn', 'vhg_coso_da_luu', $m[1] );
t( 'tai nghe là một phương thức có thật', method_exists( 'VHCP_Cfg', $m[2] ), $m[2] );
add_action( $m[1], array( 'VHCP_Cfg', $m[2] ) );

/** Danh mục cơ sở bên chi phí: tên => đơn vị. Đọc qua `cfg_static()` — đúng đường mà chốt
    `cua_coso()` đi, nên bài kiểm không tự dựng một lối đọc thứ hai. */
function dm_coso() {
	VHCP_Cfg::clear_cache();
	$cfg = VHCP_Cfg::cfg_static();
	$ra  = array();
	foreach ( (array) ( isset( $cfg['coso'] ) ? $cfg['coso'] : array() ) as $x ) {
		$ten = trim( (string) $x['ten'] );
		if ( '' !== $ten ) { $ra[ $ten ] = VHCP_DonVi::chuan( isset( $x['donVi'] ) ? $x['donVi'] : '' ); }
	}
	return $ra;
}
/** Một dòng thô của danh mục cơ sở (để soi mấy ô MISA khai tay). */
function dong_coso( $ten ) {
	$cfg = VHCP_Cfg::cfg_static();
	foreach ( (array) ( isset( $cfg['coso'] ) ? $cfg['coso'] : array() ) as $x ) {
		if ( trim( (string) $x['ten'] ) === $ten ) { return $x; }
	}
	return null;
}
VHCP_Cfg::write( VHCP_Cfg::COSO, array( array( 'FARM PHAN THIẾT', 'FPT', 'Farm', 'Farm Phan Thiết', '', '' ) ) );
VHCP_Cfg::clear_cache();
teq( 'sân đầu: đúng một cơ sở K&H', array( 'FARM PHAN THIẾT' => VHCP_DonVi::MAC_DINH ), dm_coso() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 TẠO CƠ SỞ BÊN GHẾ -> SANG NGAY BÊN CHI PHÍ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = VHG_May::luu_coso( 0, 'Cali Thảo Điền' );
t( 'bên ghế thêm cơ sở thành công', ! empty( $r['ok'] ), $r );
$dm = dm_coso();
t( '🔴 cơ sở ấy có mặt bên danh mục chi phí', isset( $dm['Cali Thảo Điền'] ), $dm );
teq( '🔴 và được gắn sẵn đơn vị POSH', 'POSH', isset( $dm['Cali Thảo Điền'] ) ? $dm['Cali Thảo Điền'] : null );
teq( 'cơ sở K&H cũ không bị đụng', VHCP_DonVi::MAC_DINH, $dm['FARM PHAN THIẾT'] );

/* Và chốt "dòng chi này của bên nào" phải nói ngay được — đây mới là thứ dùng đến. */
teq( '🔴 mọi đồng chi cho gian ấy về đúng POSH', 'POSH', VHCP_DonVi::cua_coso( 'Cali Thảo Điền' ) );
/* Đơn vị mới phải hiện luôn trong hộp tích "Xem đơn vị" — không thì khai xong vẫn không tích
   được cho ai (xem `kiem-don-vi-moi-hien-ra.php`). */
t( '🔴 POSH hiện luôn trong danh sách đơn vị', in_array( 'POSH', VHCP_DonVi::ds(), true ), VHCP_DonVi::ds() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. BẤM LẠI, GÕ KHÁC HOA THƯỜNG — KHÔNG ĐƯỢC SINH DÒNG THỨ HAI
 *
 * 🔴 Hai dòng cho cùng một gian là tiền của nó tách làm đôi ở mọi bảng gom.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHG_May::luu_coso( 0, 'Cali Thảo Điền' );
teq( 'thêm lại đúng tên ấy → vẫn một dòng', 2, count( dm_coso() ) );
VHCP_Cfg::moc_coso_ghe( 'CALI THẢO ĐIỀN' );
teq( '🔴 khác hoa thường cũng là một gian', 2, count( dm_coso() ) );
VHCP_Cfg::moc_coso_ghe( '  Cali Thảo Điền  ' );
teq( 'thừa khoảng trắng hai đầu cũng thế', 2, count( dm_coso() ) );
teq( 'tên rỗng thì bỏ qua, không sinh dòng trắng', false, VHCP_Cfg::nhan_coso_ngoai( '   ', 'POSH' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 KHÔNG ĐƯỢC ĐÈ LÊN DÒNG ĐANG CÓ
 *
 * Cơ sở đã nằm trong danh mục với đủ mã MISA, và kế toán có thể đã tự sửa ô Đơn vị. Mỗi lượt
 * đẩy lại kéo nó về là sửa xong hôm nay, mai lại về chỗ cũ — hỏng theo kiểu người ta tưởng
 * mình gõ nhầm.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( 'BỆNH VIỆN 175', 'BV175', 'Bệnh viện', 'BV 175 Gò Vấp', '', VHCP_DonVi::MAC_DINH ),
) );
VHCP_Cfg::clear_cache();
VHG_May::luu_coso( 0, 'BỆNH VIỆN 175' );
$dm = dm_coso();
teq( 'vẫn đúng một dòng', 1, count( $dm ) );
teq( '🔴 ô Đơn vị người ta đã sửa KHÔNG bị kéo về POSH', VHCP_DonVi::MAC_DINH, $dm['BỆNH VIỆN 175'] );
$giu = dong_coso( 'BỆNH VIỆN 175' );
t( 'bốc được dòng ấy', is_array( $giu ), $giu );
teq( '🔴 mã MISA khai tay vẫn còn nguyên', 'BV175', trim( (string) $giu['maDonVi'] ) );
teq( '   tên MISA cũng thế',              'BV 175 Gò Vấp', trim( (string) $giu['tenMisa'] ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. ĐỔI TÊN BÊN GHẾ -> THÊM DÒNG MỚI, GIỮ DÒNG CŨ
 *
 * ⚠️ Đây KHÔNG phải rác. `cua_coso()` tra theo TÊN, mà đơn cũ vẫn mang tên cũ — xoá dòng cũ là
 *    mấy đơn ấy rơi về nhà mặc định, tức số của POSH nhảy sang sổ K&H.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array() );
VHCP_Cfg::clear_cache();
$r  = VHG_May::luu_coso( 0, 'Aeon Bình Tân' );
$id = (int) $r['id'];
VHG_May::luu_coso( $id, 'AEON MALL BÌNH TÂN' );
$dm = dm_coso();
teq( '🔴 đổi tên bên ghế → bên này có CẢ HAI dòng', 2, count( $dm ) );
t( 'tên cũ còn nguyên (đơn cũ vẫn tra được)', isset( $dm['Aeon Bình Tân'] ), $dm );
t( 'tên mới cũng có',                          isset( $dm['AEON MALL BÌNH TÂN'] ), $dm );
teq( 'cả hai đều là POSH · cũ', 'POSH', $dm['Aeon Bình Tân'] );
teq( 'cả hai đều là POSH · mới', 'POSH', $dm['AEON MALL BÌNH TÂN'] );

/* Xoá bên ghế thì bên này giữ nguyên — cùng lý do. */
VHG_May::xoa_coso( $id );
teq( '🔴 xoá cơ sở bên ghế KHÔNG xoá theo bên chi phí', 2, count( dm_coso() ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. HÚT LẦN ĐẦU — bên ghế có sẵn hàng chục gian trước khi có móc này
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array() );
VHCP_Cfg::clear_cache();
$wpdb->query( 'DELETE FROM ' . VHG_DB::t( 'coso' ) );
foreach ( array( 'Aeon Bình Tân', 'AEON MALL BÌNH DƯƠNG', 'BỆNH VIỆN 175', 'Cali Thảo Điền' ) as $x ) {
	$wpdb->insert( VHG_DB::t( 'coso' ), array( 'ten' => $x ) );
}
teq( '🔴 hút một lượt → thêm đủ bốn gian', 4, VHCP_Cfg::hut_coso_ghe() );
$dm = dm_coso();
teq( 'danh mục có đủ bốn', 4, count( $dm ) );
teq( 'và tất cả đều mang đơn vị POSH', array( 'POSH' ), array_values( array_unique( array_values( $dm ) ) ) );
teq( '🔴 hút lần thứ hai → không thêm gì nữa', 0, VHCP_Cfg::hut_coso_ghe() );
teq( 'và danh mục vẫn đúng bốn dòng',           4, count( dm_coso() ) );

/* Cổng gọi từ màn Cấu hình phải nói được kết quả — nút bấm mà không báo gì thì người ta bấm
   lại lần nữa, rồi lần nữa. */
$api = VHCP_Cfg::hut_coso_ghe_api();
t( 'cổng hút trả ok', ! empty( $api['ok'] ), $api );
teq( 'và nói rõ không còn gì để thêm', 0, $api['them'] );
t( 'kèm một câu cho người đọc', '' !== trim( (string) $api['thongBao'] ), $api );

/* 🔴 VÀ PHẢI ĐẾM ĐÚNG KHI CÒN THIẾU. Nút bấm mà lúc nào cũng báo "0" thì người ta bấm lại lần
   nữa, rồi lần nữa, và không bao giờ biết nó đã chạy hay chưa. */
$wpdb->insert( VHG_DB::t( 'coso' ), array( 'ten' => 'SNOW NHÀ TUYẾT TÂN PHÚ' ) );
$wpdb->insert( VHG_DB::t( 'coso' ), array( 'ten' => 'CGV BÌNH DƯƠNG' ) );
$api2 = VHCP_Cfg::hut_coso_ghe_api();
teq( '🔴 còn thiếu hai gian → báo đúng hai', 2, $api2['them'] );
t( 'và câu báo nhắc con số ấy', false !== mb_strpos( (string) $api2['thongBao'], '2' ), $api2 );
teq( 'danh mục lên đủ sáu', 6, count( dm_coso() ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. KHÔNG CÀI PLUGIN GHẾ THÌ LẶNG LẼ BỎ QUA
 *
 * 🔴 Phần lớn site chỉ cài plugin chi phí. Gọi thẳng tên lớp bên kia là trang trắng ở MỌI lượt
 *    tải — hỏng to hơn hẳn thứ đang muốn thêm.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
$i   = mb_strpos( $src, 'function hut_coso_ghe(' );
$than = ( false === $i ) ? '' : mb_substr( $src, $i, 700 );
t( '🔴 hàm hút có gác `class_exists` trước khi chạm lớp bên kia',
	false !== mb_strpos( $than, "class_exists( 'VHG_May' )" ), $than );
t( 'và gác cả tên phương thức', false !== mb_strpos( $than, "method_exists" ), $than );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 8. BÊN GHẾ PHẢI BẮN Ở MỌI NHÁNH THÀNH CÔNG
 *
 * 🔴 Im lặng ở nhánh "cơ sở này đã có" là hỏng theo kiểu không bao giờ tự lành: gian ấy có bên
 *    ghế nhưng thiếu bên chi phí, và mọi lượt bấm sau đều rơi vào đúng nhánh im lặng ấy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::COSO, array() );
VHCP_Cfg::clear_cache();
$wpdb->query( 'DELETE FROM ' . VHG_DB::t( 'coso' ) );
$wpdb->insert( VHG_DB::t( 'coso' ), array( 'ten' => 'SNOW NHÀ TUYẾT TÂN PHÚ' ) );   // có bên ghế, thiếu bên kia
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ⚠️ GIẢ ĐỊNH Ở ĐÂY ĐÃ ĐỔI — 14/09/2026, và đây là chỗ ghi lại.
 *
 * Trước: bảng cơ sở rỗng thì `cfg_static()` LUÔN gieo lại bộ mẫu, nên "xoá trắng" là trạng thái
 * không tồn tại, và phép này đòi danh mục KHÔNG rỗng.
 *
 * Nay: hạt giống gieo ĐÚNG MỘT LẦN (dấu `seeded_coso_v1`). Anh Thắng tách chi phí theo mảng, và
 * bản Văn phòng gieo sẵn 14 cơ sở của K&H — anh xoá hết rồi bấm Lưu, chúng quay lại: *"trang chi
 * phí văn phòng không xóa được cơ sở chi phí kvc"*. Gác cũ không phân biệt được "chưa gieo bao
 * giờ" với "người ta vừa cố ý dọn sạch".
 *
 * Nên phép này nay canh điều NGƯỢC LẠI, và nó vẫn là ĐỐI CHỨNG cho phần dưới: sau khi dọn sạch,
 * danh mục ở lại rỗng, nên mọi dòng xuất hiện sau đó chắc chắn đến từ lượt hút bên ghế chứ không
 * phải từ hạt giống.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::seed();
$dm0 = dm_coso();
t( '🔴 đối chứng · dọn sạch rồi thì danh mục Ở LẠI rỗng, không tự gieo lại',
	0 === count( $dm0 ), count( $dm0 ) );
t( 'sân: gian ấy CHƯA có bên chi phí', ! isset( $dm0['SNOW NHÀ TUYẾT TÂN PHÚ'] ), array_keys( $dm0 ) );
VHG_May::luu_coso( 0, 'SNOW NHÀ TUYẾT TÂN PHÚ' );   // rơi vào nhánh "đã có"
$dm = dm_coso();
t( '🔴 nhánh "cơ sở này đã có" VẪN đẩy sang', isset( $dm['SNOW NHÀ TUYẾT TÂN PHÚ'] ), $dm );

/* ════════════════════════════════════════════════════════════════════════════════════════
 * 8. TẮT CÔNG TẮC -> TẮT CẢ BA LỐI
 *
 * 🔴 ĐÂY LÀ PHÉP QUAN TRỌNG NHẤT CỦA TỆP NÀY, từ 14/09/2026.
 *    Anh Thắng xoá 67 gian ghế khỏi danh mục khu vui chơi, xoá mãi không được, vì lượt hút tự
 *    động chạy lại MỖI LẦN ĐỔI PHIÊN BẢN PLUGIN. Tắt mà còn sót một lối thì cơ sở vẫn chảy
 *    sang — chỉ chậm hơn và khó truy hơn, tức tệ hơn hẳn.
 * ════════════════════════════════════════════════════════════════════════════════════════ */
update_option( 'vhcp_lay_coso_ghe', 0 );
VHCP_Cfg::write( VHCP_Cfg::COSO, array() );
VHCP_Cfg::clear_cache();
$wpdb->query( 'DELETE FROM ' . VHG_DB::t( 'coso' ) );
foreach ( array( 'AEON MALL BÌNH DƯƠNG', 'CGV LANDMARK 81', 'BỆNH VIỆN 175' ) as $x ) {
	$wpdb->insert( VHG_DB::t( 'coso' ), array( 'ten' => $x ) );
}
teq( '🔴 lối 1 — lượt hút tự động: tắt thì không kéo gian nào', 0, VHCP_Cfg::hut_coso_ghe() );
teq( 'danh mục vẫn trống', 0, count( dm_coso() ) );

/* Lối 2 — móc `vhg_coso_da_luu`: tạo cơ sở MỚI bên ghế, bên này phải im. Đây là lối lặng lẽ
   nhất: không ai bấm gì cả, chỉ cần bên kia lưu một dòng. */
VHG_May::luu_coso( 0, 'CGV PEARL PLAZA' );
teq( '🔴 lối 2 — móc từ bên Ghế: tắt thì không nhận', 0, count( dm_coso() ) );

/* Lối 3 — nút bấm tay ở màn Cấu hình: phải CHỐI, và nói ra lý do chứ không im lặng trả 0. */
$api3 = VHCP_Cfg::hut_coso_ghe_api();
t( '🔴 lối 3 — nút bấm tay: tắt thì chối', empty( $api3['ok'] ), $api3 );
t( '   và nói rõ vì sao chối', '' !== trim( (string) ( isset( $api3['error'] ) ? $api3['error'] : '' ) ), $api3 );
teq( 'sau cả ba lối, danh mục vẫn trống trơn', 0, count( dm_coso() ) );

/* ⚠️ VÀ MÀN CẤU HÌNH PHẢI GIẤU NÚT ẤY ĐI. Nút còn đó mà bấm vào chỉ ra câu chối là một
   nút chết — người ta bấm, đọc, rồi bấm lại lần nữa vào tuần sau. */
$app_ma = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-app.php' );
t( '⚠️ cờ gửi xuống màn đi qua hàm, không đọc hằng thẳng',
	false !== strpos( $app_ma, "'layCoSoGhe' => VHCP_Cfg::lay_coso_ghe()" ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHỐI BÁO TRƯỢT PHẢI ĐỨNG CUỐI CÙNG, ngay trên dòng tổng kết.
 *    14/09/2026 nó nằm ở giữa tệp, và khối "8. TẮT CÔNG TẮC" thêm vào sau đó CHẠY XONG rồi
 *    kết quả rơi vào hư không: gỡ hẳn gác của `moc_coso_ghe()` mà bài vẫn in "✓ SẠCH", chỉ
 *    tụt vài phép — một con số không ai nhìn. Phép kiểm nói dối đúng chỗ nó phải canh.
 *    Thêm khối mới thì thêm Ở TRÊN chỗ này.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}

echo "\n✓ SẠCH — $dat phép: cơ sở bên ghế tự sang danh mục chi phí, gắn đơn vị POSH, chỉ thêm không đè.\n";
