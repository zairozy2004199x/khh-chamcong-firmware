<?php
/**
 * KIỂM TAB CỬA HÀNG TRÊN TRẠM — cửa hàng trưởng duyệt đơn, xem công cơ sở, thêm người mới.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CẦN
 * =================================================================================================
 * Anh Thắng 17/09/2026: *"đối với cửa hàng trưởng khi đăng nhập sẽ thấy thêm: Thêm nhân sự mới ·
 * Bảng công cơ sở quản lý · Đơn từ từ nhân viên — cơ sở mình quản lý"*.
 *
 * Trước bản này, MỌI cửa của trạm chỉ mở ra thứ của CHÍNH NGƯỜI ĐANG ĐĂNG NHẬP. Đây là cửa đầu
 * tiên trên trạm cho một người đọc và SỬA dữ liệu của NGƯỜI KHÁC — nên bốn câu phải trả lời được
 * bằng phép thử:
 *
 *   1. Nhân viên thường có mở được không? (tab, và cả bốn cửa phía sau tab)
 *   2. Cửa hàng trưởng có với sang CƠ SỞ KHÁC được không, kể cả khi gõ thẳng tên cơ sở ấy?
 *   3. Duyệt đơn có đi qua đúng phép gác cũ không, hay lớp mới tự chế một phép gác thứ hai?
 *   4. Thêm người mới có nới rộng hơn cửa hẹp `them_nv_cua_hang` không?
 *
 * Chạy: php tools/test/kiem-cua-hang.php
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

$CS_A = 'CH_SHOP_A';
$CS_B = 'CH_SHOP_B';
$TH   = current_time( 'Y-m' );

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân', 'cua_hang' => $CS_A, 'chuc_vu' => 'Nhân viên',
	'pin_dang_nhap' => '941111', 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'CH002', 'ho_ten' => 'Hồ Văn Kia', 'cua_hang' => $CS_B, 'chuc_vu' => 'Nhân viên',
	'pin_dang_nhap' => '942222', 'trang_thai_lam_viec' => 'Đang làm' ) );

$NV    = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '941111' )['token'] );
$NV_B  = VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( '942222' )['token'] );
t( 'hai nhân viên đăng nhập được', is_array( $NV ) && is_array( $NV_B ), array( $NV, $NV_B ) );

$CHT_A = array( 'name' => 'Trưởng A', 'role' => VHCC_Vai::CHT, 'coso' => $CS_A, 'ma_nv' => 'CHTA' );
$CHT_B = array( 'name' => 'Trưởng B', 'role' => VHCC_Vai::CHT, 'coso' => $CS_B, 'ma_nv' => 'CHTB' );

/* =============================================================== 1. AI THẤY TAB */

t( '🔴 nhân viên thường KHÔNG được vào tab Cửa hàng', ! VHCC_CuaHang::duoc( $NV ), $NV );
t( 'cửa hàng trưởng thì được', VHCC_CuaHang::duoc( $CHT_A ) );
t( 'bậc trên cửa hàng trưởng cũng được',
	VHCC_CuaHang::duoc( array( 'name' => 'Sếp', 'role' => VHCC_Vai::ADMIN, 'coso' => '' ) ) );
t( 'quyền gác tab đúng bậc Cửa hàng trưởng',
	VHCC_Vai::CHT === VHCC_Vai::QUYEN[ VHCC_CuaHang::QUYEN ] );

t( 'trưởng A thấy đúng cơ sở của mình', array( $CS_A ) === VHCC_CuaHang::ds_coso( $CHT_A ),
	VHCC_CuaHang::ds_coso( $CHT_A ) );

/* =============================================================== 2. ĐƠN TỪ */

/* Hai nhân viên hai cơ sở, mỗi người một đơn mỗi loại nộp được. */
$mai = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' 00:00:00 UTC' ) + 86400 );
VHCC_XinTre::nop( $NV,   array( 'ngay' => $mai, 'so_phut' => 20, 'ly_do' => 'Kẹt xe' ) );
VHCC_XinTre::nop( $NV_B, array( 'ngay' => $mai, 'so_phut' => 25, 'ly_do' => 'Đơn của B' ) );
VHCC_XinNghi::nop( $NV,  array( 'tu' => $mai, 'loai' => VHCC_XinNghi::OM, 'lyDo' => 'Sốt' ) );

$r = VHCC_CuaHang::don_cho( $CHT_A, $CS_A );
t( 'trưởng A đọc được hộp đơn', ! empty( $r['ok'] ), $r );
t( 'hộp gộp cả đơn trễ lẫn đơn nghỉ', 2 === (int) $r['so'], $r );

/* 🔴 KHÔNG LẪN ĐƠN CỦA CƠ SỞ KHÁC — soi cả chuỗi, không soi từng khoá. */
$chuoi = wp_json_encode( $r );
t( '🔴 hộp của A KHÔNG có tên người cơ sở B', false === strpos( $chuoi, 'Hồ Văn Kia' ), $r );
t( '🔴 hộp của A KHÔNG có mã người cơ sở B', false === strpos( $chuoi, 'CH002' ), $r );
foreach ( $r['don'] as $d ) {
	t( 'mọi đơn trong hộp đều của người cơ sở A', 'CH001' === (string) $d['maNV'], $d );
	t( 'đơn nào cũng nói rõ là loại gì', '' !== trim( (string) $d['tenLoai'] ), $d );
	t( 'và mang theo lý do để người duyệt quyết', isset( $d['lyDo'] ), $d );
}

/* 🔴 GÕ THẲNG TÊN CƠ SỞ KHÁC CŨNG KHÔNG RA. Tên cơ sở thì ai cũng biết — xem câu 2 đầu tệp. */
$r2 = VHCC_CuaHang::don_cho( $CHT_A, $CS_B );
t( '🔴 trưởng A gõ thẳng cơ sở B thì bị CHỐI', empty( $r2['ok'] ), $r2 );
t( 'và câu chối không mang theo dữ liệu nào của B',
	false === strpos( wp_json_encode( $r2 ), 'Hồ Văn Kia' ), $r2 );

/* Bỏ trống cơ sở thì lui về cơ sở đầu của chính mình, không lui về "tất cả". */
$r3 = VHCC_CuaHang::don_cho( $CHT_A, '' );
t( 'bỏ trống cơ sở thì lui về cơ sở của mình', ! empty( $r3['ok'] ) && $CS_A === $r3['coSo'], $r3 );

/* 🔴 NHÂN VIÊN THƯỜNG KHÔNG ĐỌC ĐƯỢC HỘP ĐƠN, kể cả cơ sở của chính họ. */
$r4 = VHCC_CuaHang::don_cho( $NV, $CS_A );
t( '🔴 nhân viên thường KHÔNG mở được hộp đơn của cơ sở mình', empty( $r4['ok'] ), $r4 );

/* =============================================================== 3. DUYỆT */

$don = $r['don'][0];
$r5  = VHCC_CuaHang::duyet( $NV, $don['loai'], $don['id'], true );
t( '🔴 nhân viên thường KHÔNG duyệt được đơn', empty( $r5['ok'] ), $r5 );

/* 🔴 CHỐT CƠ SỞ ĐỌC TỪ CHÍNH ĐƠN. Trưởng B có quyền cửa hàng trưởng, và id đơn thì gõ tay được. */
$r6 = VHCC_CuaHang::duyet( $CHT_B, $don['loai'], $don['id'], true );
t( '🔴 trưởng B KHÔNG duyệt được đơn của cơ sở A', empty( $r6['ok'] ), $r6 );

$r7 = VHCC_CuaHang::duyet( $CHT_A, $don['loai'], $don['id'], true );
t( 'trưởng A duyệt được đơn của cơ sở mình', ! empty( $r7['ok'] ), $r7 );

$r8 = VHCC_CuaHang::don_cho( $CHT_A, $CS_A );
t( 'đơn đã quyết thì rời khỏi hộp chờ', 1 === (int) $r8['so'], $r8 );

$r9 = VHCC_CuaHang::duyet( $CHT_A, 'loai_la', 1, true );
t( '🔴 loại đơn lạ bị chối — danh sách trắng, không phải ô chữ tự do', empty( $r9['ok'] ), $r9 );

/* 🔴 KHÔNG TỰ CHẾ PHÉP GÁC. Canh bằng MÃ NGUỒN: lớp này phải GỌI LẠI hàm nghiệp vụ cũ, vì mỗi
   hàm ấy tự chốt cơ sở từ chính bản ghi. Dựng phép gác riêng ở đây là hai bộ luật quyền. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-cua-hang.php' );
foreach ( array( 'VHCC_XinTre::duyet', 'VHCC_XinNghi::duyet', 'VHCC_Lich::duyet',
	'VHCC_Cham::bang_cham_cong', 'VHCC_NhanSu::them_nv_cua_hang' ) as $ham ) {
	t( '🔴 gọi lại hàm nghiệp vụ đã có: ' . $ham, false !== strpos( $src, $ham ), $ham );
}
t( '🔴 lớp này KHÔNG tự đụng vào bảng dữ liệu nào', false === strpos( $src, 'VHCC_DB::t(' ), $src );
t( '🔴 và KHÔNG tự chạy câu SQL nào', false === strpos( $src, '$wpdb' ), $src );
/* Mọi cửa đều phải đi qua `chot_coso()` — đó là chỗ DUY NHẤT đối chiếu cơ sở gửi lên. */
t( 'có đúng một chỗ chốt cơ sở', 1 === substr_count( $src, 'private static function chot_coso' ), $src );
t( 'và nó gác bằng co_quyen_coso', false !== strpos( $src, 'co_quyen_coso' ), $src );
t( '🔴 cả ba cửa đọc/ghi đều gọi chot_coso()',
	3 === substr_count( $src, 'self::chot_coso( $u' ), $src );

/* =============================================================== 4. BẢNG CÔNG */

$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân', 'coso' => $CS_A, 'ngay' => $TH . '-02',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 16 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CH002', 'ho_ten' => 'Hồ Văn Kia', 'coso' => $CS_B, 'ngay' => $TH . '-02',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 20 * 3600, 'hau_to' => '', 'nguon' => 'may' ) );

$c = VHCC_CuaHang::cong_coso( $CHT_A, $CS_A, $TH );
t( 'trưởng A đọc được bảng công cơ sở mình', ! empty( $c['ok'] ), $c );
t( 'đúng một người', 1 === (int) $c['soNguoi'], $c );
t( 'đúng số giờ (8h)', 8.0 === (float) $c['dong'][0]['gio'], $c['dong'][0] );
t( 'đúng số ngày', 1 === (int) $c['dong'][0]['soNgay'], $c['dong'][0] );
t( '🔴 KHÔNG lẫn người của cơ sở khác',
	false === strpos( wp_json_encode( $c ), 'Hồ Văn Kia' ), $c );

/* ⚠️ BẢNG CÔNG TRÊN TRẠM KHÔNG BÀY TIỀN. Nó là bảng CÔNG, không phải bảng lương — và một con
   số tiền lọt lên màn điện thoại giữa quầy là chuyện không rút lại được. */
foreach ( array( 'luong', 'gia', 'tien', 'Cb' ) as $cam ) {
	t( '⚠️ bảng công trên trạm không mang khoá "' . $cam . '"',
		false === strpos( wp_json_encode( $c['dong'][0] ), $cam ), $c['dong'][0] );
}

/* 🔴 THIẾU MỘT ĐẦU GIỜ THÌ KHÔNG CỘNG PHÚT NÀO, VÀ ĐẾM RIÊNG. Coi như 0 là trừ công một người
   vì cái máy lỗi; đoán một ca chuẩn là cấp giờ không ai làm. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
	'ma_nv' => 'CH001', 'ho_ten' => 'Vũ Thị Nhân', 'coso' => $CS_A, 'ngay' => $TH . '-03',
	'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => null, 'hau_to' => '', 'nguon' => 'may' ) );
$c = VHCC_CuaHang::cong_coso( $CHT_A, $CS_A, $TH );
t( '🔴 lượt thiếu đầu giờ KHÔNG cộng phút nào', 8.0 === (float) $c['dong'][0]['gio'], $c['dong'][0] );
t( '🔴 nhưng được ĐẾM và nói ra', 1 === (int) $c['dong'][0]['thieu'], $c['dong'][0] );
t( 'và cộng vào tổng của cả cơ sở', 1 === (int) $c['tongThieu'], $c );

$c2 = VHCC_CuaHang::cong_coso( $CHT_A, $CS_B, $TH );
t( '🔴 trưởng A KHÔNG xem được bảng công cơ sở B', empty( $c2['ok'] ), $c2 );
$c3 = VHCC_CuaHang::cong_coso( $NV, $CS_A, $TH );
t( '🔴 nhân viên thường KHÔNG xem được bảng công cơ sở', empty( $c3['ok'] ), $c3 );

/* =============================================================== 5. THÊM NGƯỜI MỚI */

$r = VHCC_CuaHang::them_nguoi( $NV, array(
	'hoTen' => 'Người Lạ', 'cccd' => '000000000111', 'coSo' => $CS_A ) );
t( '🔴 nhân viên thường KHÔNG thêm được người', empty( $r['ok'] ), $r );

/* 🔴 CƠ SỞ LẤY TỪ PHIÊN. Trưởng A gõ tên cơ sở B là thêm người vào cửa hàng người khác. */
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array(
	'hoTen' => 'Người Lạ', 'cccd' => '000000000222', 'coSo' => $CS_B ) );
t( '🔴 trưởng A KHÔNG thêm được người vào cơ sở B', empty( $r['ok'] ), $r );

/* ⚠️ CĂN CƯỚC LÀ BẮT BUỘC — không có nó thì người mới không tự lấy được PIN ở màn "Quên PIN",
   và hồ sơ vừa tạo thành hồ sơ chết mà cửa hàng trưởng tưởng đã xong. */
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array( 'hoTen' => 'Thiếu Căn Cước', 'cccd' => '' ) );
t( '⚠️ thiếu căn cước thì CHỐI', empty( $r['ok'] ), $r );
t( 'và câu chối chỉ đúng đường "Quên PIN"',
	false !== mb_strpos( (string) $r['error'], 'Quên PIN' ), $r );
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array( 'hoTen' => '', 'cccd' => '000000000333' ) );
t( 'thiếu họ tên thì chối', empty( $r['ok'] ), $r );

$r = VHCC_CuaHang::them_nguoi( $CHT_A, array(
	'hoTen' => 'Trần Mới Vào', 'cccd' => '000000000444', 'sdt' => '0900000000' ) );
t( 'trưởng A thêm được người vào cơ sở mình', ! empty( $r['ok'] ), $r );
t( 'người mới vào đúng cơ sở của trưởng A', $CS_A === (string) $r['coso'], $r );
t( '🔴 mã cấp ra là mã TẠM, để nhân sự lọc lại sau',
	0 === strpos( (string) $r['ma_nv'], 'TAM-' ), $r );

$hs = VHCC_NhanSu::ho_so( $r['ma_nv'] );
t( '🔴 người mới có VAI, không để trống', '' !== trim( (string) $hs['vai_tro'] ), $hs );
t( 'và vai ấy là Nhân viên, không phải bậc cao hơn',
	VHCC_Vai::TEN[ VHCC_Vai::NV ] === (string) $hs['vai_tro'], $hs );
t( '🔴 cửa này KHÔNG đặt lương cơ bản', empty( $hs['luong_co_ban'] ), $hs );

/* ⚠️ CĂN CƯỚC TRÙNG THÌ CHỐI — một người hai cơ sở là MỘT hồ sơ khai thêm cơ sở phụ, không
   phải hai hồ sơ. Hai hồ sơ là nhân đôi người ấy trên bảng lương. */
$r = VHCC_CuaHang::them_nguoi( $CHT_A, array(
	'hoTen' => 'Trùng Căn Cước', 'cccd' => '000000000444' ) );
t( '⚠️ căn cước trùng thì CHỐI', empty( $r['ok'] ), $r );
t( 'và chỉ ra người đang giữ số ấy',
	false !== mb_strpos( (string) $r['error'], 'Trần Mới Vào' ), $r );

/* 🔴 KHÔNG NHẬN chuc_vu TỪ MÀN TRẠM. Chức vụ tra ra ĐƠN GIÁ GIỜ, nên một chữ gõ vội là một
   dòng lương ra 0đ mà bảng vẫn đầy số. */
$i_tn = strpos( $src, 'function them_nguoi' );
t( '🔴 them_nguoi() KHÔNG chuyển tiếp chức vụ từ màn trạm',
	false !== $i_tn && false === strpos( substr( $src, $i_tn, 700 ), 'chuc_vu' ),
	substr( $src, $i_tn, 700 ) );

/* =============================================================== 6. CỬA TRẠM VÀ MÀN HÌNH */

$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
foreach ( array( 'cuahang', 'chdon', 'chduyet', 'chcong', 'chthem' ) as $v ) {
	t( 'trạm có cửa ' . $v, false !== strpos( $tram, "'" . $v . "' === \$viec" ), $v );
}
$i_ch = strpos( $tram, "'cuahang' === \$viec" );
t( '🔴 cửa cuahang trả duoc:false cho người không có quyền',
	false !== strpos( substr( $tram, $i_ch, 400 ), "'duoc' => false" ), substr( $tram, $i_ch, 400 ) );
$i_th = strpos( $tram, "'chthem' === \$viec" );
t( '🔴 cửa chthem KHÔNG chuyển tiếp chức vụ',
	false !== $i_th && false === strpos( substr( $tram, $i_th, 500 ), 'chuc' ),
	substr( $tram, $i_th, 500 ) );

$tpl  = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$than = substr( $tpl, 0, strpos( $tpl, '<style' ) ) . substr( $tpl, strpos( $tpl, '</style>' ) );

t( 'màn có tab Cửa hàng', false !== strpos( $than, 'id="tCuaHang"' ), $than );
/* 🔴 NÚT ẨN SẴN TRONG HTML, không chờ JS gỡ: chờ JS thì nhân viên thường thấy nút loé lên một
   nhịp, và cái loé ấy đủ để người ta bấm. */
t( '🔴 nút tab ẩn sẵn ngay trong HTML',
	false !== strpos( $than, 'class="tab-nut an" id="nutCH"' ), $than );
t( 'và chỉ mở khi máy chủ nói được',
	false !== strpos( $tpl, "if(!j || !j.ok || !j.duoc){ return; }" ), $tpl );
/* ⚠️ THANH NĂM Ô PHẢI THU CHỮ. `kiem-xin-phep.php` chốt thanh chỉ chịu được bốn ô: ô thứ năm
   làm chữ cụt ở cả bốn ô kia trên điện thoại hẹp. Ô này ẩn sẵn nên chốt ấy còn nguyên với
   nhân viên thường, nhưng cửa hàng trưởng thì có năm ô thật — nên lượt mở nút PHẢI kèm lớp
   thu chữ, và hai việc ấy phải nằm cùng một chỗ để không bao giờ có "năm ô mà chưa thu". */
t( '⚠️ mở ô thứ năm thì thu chữ cả thanh',
	false !== strpos( $tpl, "el('thanhTab').classList.add('tab5')" ), $tpl );
t( 'và có kiểu cho thanh năm ô', false !== strpos( $tpl, '#thanhTab.tab5 .tab-nut{' ), $tpl );
t( '🔴 thu CHỮ chứ không thu vùng chạm',
	false === strpos( $tpl, '#thanhTab.tab5 .tab-nut{font-size:10px;padding:9px 2px 8px;min-height' ), $tpl );

t( 'tab Cửa hàng nằm trong danh sách đổi tab',
	false !== strpos( $tpl, "'tChamCong','tCong','tCuaHang','tUng','tToi'" ), $tpl );
t( '🔴 hỏi quyền ngay lúc đăng nhập, không chờ bấm tab',
	false !== strpos( $tpl, 'doCuaHang();' ), $tpl );

foreach ( array( 'chCoSo', 'bangDonCH', 'bangCongCH', 'tnTen', 'tnCccd', 'btThemNguoi' ) as $o ) {
	t( 'màn có ô ' . $o, false !== strpos( $than, $o ), $o );
}
/* ⚠️ "KHÔNG DUYỆT" phải hỏi lại — bấm nhầm thì người xin nhận câu từ chối mà không ai cố ý gửi. */
t( '⚠️ bấm KHÔNG DUYỆT thì hỏi lại', false !== strpos( $tpl, 'Ghi KHÔNG DUYỆT đơn này?' ), $tpl );
/* Khoá đơn ghép loại + id: ba loại đánh số riêng nhau, chỉ mang id là duyệt nhầm đơn khác. */
t( '🔴 khoá đơn ghép LOẠI + ID', false !== strpos( $tpl, "esc(d.loai) + '|' + esc(d.id)" ), $tpl );
t( 'nạp lại cả hộp sau mỗi lượt quyết, không xoá một thẻ trên màn',
	false !== strpos( $tpl, 'napDonCH();' ), $tpl );
/* Màn phải nói ra rằng hệ KHÔNG phát PIN — không thì trưởng đứng chờ một cái PIN không bao giờ tới. */
t( '🔴 màn nói rõ người mới tự lấy PIN ở màn "Quên PIN"',
	false !== strpos( $tpl, 'Quên PIN' ), $tpl );

/* =============================================================== dọn */

$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) . " WHERE coso IN ('$CS_A','$CS_B')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_tre' ) . " WHERE ma_nv IN ('CH001','CH002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'xin_nghi' ) . " WHERE ma_nv IN ('CH001','CH002')" );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . " WHERE cua_hang IN ('$CS_A','$CS_B')" );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — cửa hàng trưởng làm được ba việc trên điện thoại, và CHỈ trong"
	. " cơ sở mình phụ trách.\n";
