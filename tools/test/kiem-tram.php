<?php
/**
 * KIỂM TRẠM CHẤM CÔNG — trang nhân viên tự chấm bằng điện thoại (VHCC_Tram + templates/tram.php).
 *
 * 🔴 VÌ SAO PHẢI KIỂM RIÊNG. Trạm mở một cửa đăng nhập THỨ HAI vào cùng một bảng `session` mà
 * hệ quản trị đang dùng. Cửa thứ hai vào cùng một kho là chỗ dễ mở nhầm nhất trong cả plugin:
 * sai một chút là nhân viên cơ sở cầm thẻ vào xem được bảng lương toàn công ty, mà không có gì
 * báo — trang vẫn chạy, PIN vẫn đúng, chỉ là quyền rộng hơn ý định.
 *
 * Nên bài này canh HAI CHIỀU, không phải một: thẻ của trạm không mở được cửa quản trị, VÀ thẻ
 * quản trị không đi được vào cửa trạm.
 *
 * Chạy: php tools/test/kiem-tram.php
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

/** Bỏ chú thích JS để phép thử soi MÃ chứ không soi lời giải thích về mã. */
function js_sach( $src ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', $src );
	$ra  = array();
	foreach ( explode( "\n", $src ) as $d ) {
		if ( preg_match( '#^\s*(//|\*)#', $d ) ) { continue; }
		$ra[] = $d;
	}
	return implode( "\n", $ra );
}

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

global $wpdb;

/* ------------------------------------------------------------------ dựng dữ liệu mẫu */
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array(
	'pin' => '246810', 'ho_ten' => 'Trần Văn A', 'vai_tro' => 'NHAN_VIEN',
	'cua_hang' => 'VIVO', 'ma_cc_online' => 'NV001', 'coso_cc_online' => 'CS_VIVO' ) );
$wpdb->insert( VHCC_DB::t( 'phan_quyen' ), array(
	'pin' => '111222', 'ho_ten' => 'Lê Thị B', 'vai_tro' => 'NHAN_VIEN',
	'cua_hang' => 'AEON', 'ma_cc_online' => '', 'coso_cc_online' => '' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'NV001', 'ho_ten' => 'Trần Văn A', 'cua_hang' => 'VIVO',
	'coso_phu' => 'GO_AN_LAC', 'nhiem_vu' => 'Thu Tiền' ) );

/* ================================================================== 1. CỬA ĐĂNG NHẬP */

$r = VHCC_Tram::dang_nhap( 'abc' );
t( 'PIN không phải số bị chối', empty( $r['ok'] ) );
t( 'PIN không phải số — nói đúng luật 4–8 chữ số', strpos( $r['error'], '4–8' ) !== false, $r['error'] );

t( 'PIN 3 số bị chối', empty( VHCC_Tram::dang_nhap( '123' )['ok'] ) );

$r = VHCC_Tram::dang_nhap( '999999' );
t( 'PIN không có trong sổ bị chối', empty( $r['ok'] ) );

/* 🔴 Chỗ này là lý do có nhánh riêng: PIN ĐÚNG nhưng chưa khai mã NV KHÔNG được báo "PIN sai".
   Báo sai hướng thì người ta gõ lại tới lúc tự khoá mình, trong khi thứ thiếu nằm ở hồ sơ. */
$r = VHCC_Tram::dang_nhap( '111222' );
t( 'PIN đúng mà chưa khai mã NV thì bị chối', empty( $r['ok'] ) );
t( 'PIN đúng mà chưa khai mã NV — KHÔNG nói "PIN không đúng"',
	strpos( $r['error'], 'PIN không đúng' ) === false, $r['error'] );
t( 'PIN đúng mà chưa khai mã NV — chỉ đúng chỗ phải sửa',
	strpos( $r['error'], 'Mã NV chấm công online' ) !== false, $r['error'] );
t( 'PIN đúng mà chưa khai mã NV — gọi đúng tên người', strpos( $r['error'], 'Lê Thị B' ) !== false );

$r = VHCC_Tram::dang_nhap( '246810' );
t( 'PIN đúng + có mã NV thì vào được', ! empty( $r['ok'] ), $r );
t( 'trả đúng mã NV', 'NV001' === $r['maNV'] );
t( 'trả cơ sở đã cắt tiền tố CS_', 'VIVO' === $r['coSo'], $r['coSo'] );
t( 'phát thẻ 64 ký tự hex', (bool) preg_match( '/^[0-9a-f]{64}$/', (string) $r['token'] ) );
$TOK_TRAM = $r['token'];

/* PIN kiểu Google Sheets ("246810.0") cũng phải vào được — VHCC_Auth::pin_sach lo việc đó, và
   trạm phải GỌI nó chứ không tự so chuỗi. */
t( 'PIN dạng "246810.0" (Sheets ép về số) vẫn vào được',
	! empty( VHCC_Tram::dang_nhap( '246810.0' )['ok'] ) );

/* ================================================================== 2. HAI CHIỀU CHẶN QUYỀN */

t( 'vai của trạm KHÔNG nằm trong VAI_TRO_TAT_CA (Cài đặt không bật lên được)',
	! in_array( VHCC_Tram::VAI_TRAM, VHCC_Auth::VAI_TRO_TAT_CA, true ) );
t( 'vai của trạm KHÔNG nằm trong danh sách vào hệ quản trị',
	! in_array( VHCC_Tram::VAI_TRAM, VHCC_Auth::vai_tro_vao(), true ) );

/* 🔴 CHIỀU 1: thẻ trạm không mở được cửa quản trị. */
t( 'thẻ của TRẠM bị hệ quản trị chối', null === VHCC_Auth::user_by_token( $TOK_TRAM ) );

/* 🔴 CHIỀU 2: thẻ quản trị không đi được vào cửa trạm. */
$TOK_QT = VHCC_Auth::phat_token( 'Sếp', 'Admin', '' );
t( 'thẻ QUẢN TRỊ bị trạm chối', null === VHCC_Tram::nguoi( $TOK_QT ) );

t( 'thẻ rác bị trạm chối', null === VHCC_Tram::nguoi( 'khong-phai-hex' ) );
t( 'thẻ rỗng bị trạm chối', null === VHCC_Tram::nguoi( '' ) );

$u = VHCC_Tram::nguoi( $TOK_TRAM );
t( 'thẻ trạm tra ra người', is_array( $u ) );
t( 'thẻ trạm mang theo mã NV', 'NV001' === $u['ma_nv'], $u );
t( 'thẻ trạm mang theo họ tên', 'Trần Văn A' === $u['ho_ten'] );

/* Phiên trạm mà mã NV rỗng thì KHÔNG được coi là hợp lệ: mọi phép ghi giờ khoá theo mã NV, một
   phiên không có mã là một phiên ghi được vào hàng rỗng. */
$TOK_RONG = VHCC_Auth::phat_token( 'Ai đó', VHCC_Tram::VAI_TRAM, 'VIVO', '' );
t( 'phiên trạm thiếu mã NV bị chối', null === VHCC_Tram::nguoi( $TOK_RONG ) );

/* ================================================================== 3. HÃM DÒ PIN */

$GLOBALS['VHCP_TR'] = array();
for ( $i = 0; $i < VHCC_Tram::SAI_TOI_DA; $i++ ) { VHCC_Tram::dang_nhap( '987654' ); }
$r = VHCC_Tram::dang_nhap( '246810' );
t( 'gõ sai quá ngưỡng thì khoá, kể cả PIN đúng', empty( $r['ok'] ), $r );
t( 'câu khoá nói rõ chờ bao lâu', strpos( $r['error'], '10 phút' ) !== false, $r['error'] );
$GLOBALS['VHCP_TR'] = array();
t( 'hết hãm thì vào lại được', ! empty( VHCC_Tram::dang_nhap( '246810' )['ok'] ) );

/* ================================================================== 4. GHI GIỜ QUA ĐÚNG MỘT ĐƯỜNG */

$u = VHCC_Tram::nguoi( $TOK_TRAM );

$r = VHCC_Online::cham_cong( $u, '', null, '', '' );
t( 'lượt đầu là GIỜ VÀO', ! empty( $r['ok'] ) && 'vao' === $r['loai'], $r );
t( 'ghi vào đúng cơ sở mặc định', 'VIVO' === $r['coSo'], $r );

/* Bấm hai lần trong CÙNG MỘT GIÂY là bấm nhầm, không phải tan làm — `quyet_dinh_gio` trả
   'trung' và không ghi gì. Đó là chốt chặn đúng, nên bài kiểm phải lùi giờ vào một tiếng rồi
   mới thử giờ ra, chứ không phải sửa chốt chặn cho vừa bài kiểm. */
$r2 = VHCC_Online::cham_cong( $u, '', null, '', '' );
t( 'bấm lại ngay trong cùng một giây thì KHÔNG ghi gì', ! empty( $r2['ok'] ) && 'trung' === $r2['loai'], $r2 );

$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'cham_cong' )
	. " SET gio_vao_giay = gio_vao_giay - 3600 WHERE ma_nv='NV001' AND coso='VIVO'" );
$r2 = VHCC_Online::cham_cong( $u, '', null, '', '' );
t( 'lượt sau đó là GIỜ RA', ! empty( $r2['ok'] ) && 'ra' === $r2['loai'], $r2 );

$hang = $wpdb->get_row( $wpdb->prepare(
	'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE ma_nv=%s AND coso=%s', 'NV001', 'VIVO' ), ARRAY_A );
t( 'chỉ ĐÈ lên một hàng, không đẻ hàng mới', is_array( $hang ) );
t( 'nguồn ghi là "online" — phép đối số với sheet chỉ đếm lượt MÁY',
	'online' === $hang['nguon'], $hang['nguon'] );

/* 🔴 Gác 2: cơ sở đi lên từ điện thoại phải đối chiếu. Không kiểm thì bất kỳ tài khoản nào
   cũng ghi giờ vào cơ sở khác. */
$r = VHCC_Online::cham_cong( $u, '', null, 'AEON_TAN_PHU', '' );
t( 'chọn cơ sở KHÔNG có mình thì bị chối', empty( $r['ok'] ), $r );
$r = VHCC_Online::cham_cong( $u, '', null, 'GO_AN_LAC', '' );
t( 'chọn cơ sở PHỤ có trong hồ sơ thì ghi được', ! empty( $r['ok'] ), $r );
t( 'ghi đúng cơ sở phụ đã chọn', 'GO_AN_LAC' === $r['coSo'], $r );

/* 🔴 Gác 3: nhiệm vụ cũng đi lên từ điện thoại. Không kiểm thì ai cũng tự gán cho mình việc có
   đơn giá cao hơn. */
$r = VHCC_Online::cham_cong( $u, '', null, '', 'Trực Ghế' );
t( 'nhiệm vụ KHÔNG được khai trong hồ sơ thì bị chối', empty( $r['ok'] ), $r );
t( 'câu chối chỉ đúng chỗ phải sửa (hồ sơ)', strpos( (string) $r['error'], 'hồ sơ' ) !== false, $r );

/* Ảnh: trạm gửi data-URL, lớp ghi phải cắt tiền tố rồi lưu ra tệp — không nhét base64 vào cột
   `anh_vao` (VARCHAR(190), nhét vào là MySQL cắt cụt và tấm ảnh mất luôn, im lặng). */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) );
$anh = 'data:image/jpeg;base64,' . base64_encode( str_repeat( 'A', 400 ) );
$r = VHCC_Online::cham_cong( $u, $anh, array( 'lat' => 10.8, 'lng' => 106.7, 'acc' => 12 ), '', '' );
t( 'gửi ảnh dạng data-URL thì lưu được', ! empty( $r['ok'] ) && 0 === strpos( (string) $r['img'], 'ok:' ), $r );
$hang = $wpdb->get_row( 'SELECT anh_vao, ghi_chu FROM ' . VHCC_DB::t( 'cham_cong' ) . ' LIMIT 1', ARRAY_A );
t( 'cột ảnh giữ ĐƯỜNG DẪN, không giữ base64',
	strlen( $hang['anh_vao'] ) < 190 && false === strpos( $hang['anh_vao'], 'base64' ), $hang );
t( 'GPS ghi vào ghi chú của hàng', strpos( (string) $hang['ghi_chu'], 'GPS 10.8,106.7' ) !== false, $hang );

/* ================================================================== 5. GIỜ LẤY Ở MÁY CHỦ */

$g = VHCC_Online::gio_may_chu();
t( 'viec=gio trả mốc epoch', ! empty( $g['ok'] ) && (int) $g['moc'] > 0, $g );
t( 'viec=gio trả cả ngày lẫn giờ', (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g['ngay'] )
	&& (bool) preg_match( '/^\d{2}:\d{2}:\d{2}$/', $g['gio'] ), $g );

$ref = new ReflectionMethod( 'VHCC_Online', 'cham_cong' );
$ten_tham_so = array();
foreach ( $ref->getParameters() as $p ) { $ten_tham_so[] = $p->getName(); }
t( 'cham_cong() KHÔNG có tham số nào cho client truyền GIỜ vào',
	! preg_grep( '/gio|giay|thoi_gian|moc/i', $ten_tham_so ), $ten_tham_so );

/* ================================================================== 6. BỐN RÀNG BUỘC Ở GIAO DIỆN */

$tpl = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( 'có template trạm', strlen( $tpl ) > 2000 );

/* Ràng buộc 2 — thu nhỏ 720px. Kiểm CẢ hằng số LẪN chỗ dùng: khai một hằng số rồi không dùng
   là ảnh vẫn gửi nguyên cỡ, mà phép thử tìm chuỗi "720" thì vẫn xanh. */
t( 'RB2 · khai bề rộng 720', (bool) preg_match( '/RONG_ANH\s*=\s*720/', $tpl ) );
t( 'RB2 · có DÙNG hằng số đó để thu nhỏ',
	(bool) preg_match( '/Math\.min\(\s*RONG_ANH\s*,/', $tpl ) );

/* Ràng buộc 1 — đóng dấu bằng giờ máy chủ. Chốt chặn thật nằm ở chỗ: khi chưa có mốc máy chủ
   thì KHÔNG chụp, chứ không lặng lẽ lấy giờ máy. */
t( 'RB1 · có hàm lấy mốc giờ từ máy chủ', strpos( $tpl, "goi('gio'" ) !== false );
t( 'RB1 · chưa có mốc máy chủ thì CHỐI chụp, không đoán',
	(bool) preg_match( '/var d = gioMayChu\(\);\s*\n\s*if\(!d\)\{/', $tpl ) );
/* Không được có `new Date()` rỗng ở đâu cả — đó chính là giờ điện thoại.
   ⚠️ Phải soi MÃ ĐÃ BỎ CHÚ THÍCH. Bản đầu của phép này soi cả tệp và đỏ oan, vì chính dòng chú
   thích giải thích "không được dùng new Date()" có chứa chuỗi đó. Phép thử bắt chú thích là
   phép thử sẽ bị người ta sửa cho hết đỏ bằng cách xoá chú thích — tệ hơn là không có. */
$tpl_sach = js_sach( $tpl );
/* Chốt: bộ bỏ chú thích không được nuốt mất mã. Nuốt hết thì phép dưới xanh vì KHÔNG CÒN GÌ
   để soi — đúng kiểu phép thử tự làm mình vô dụng. */
t( 'bộ bỏ chú thích không nuốt mất mã', strpos( $tpl_sach, 'return new Date((MOC.sec*1000)' ) !== false );
t( 'RB1 · không chỗ nào lấy new Date() rỗng làm giờ',
	! preg_match( '/new Date\(\s*\)/', $tpl_sach ) );

/* Ràng buộc 3 — hỏi cơ sở/nhiệm vụ ĐÚNG LÚC LƯU. Màn chọn phải được dựng ở nhánh "Dùng ảnh
   này", tức sau khi chụp, chứ không dựng lúc mở trang. */
t( 'RB3 · màn chọn cơ sở dựng SAU khi chụp xong', strpos( $tpl, "el('btDung').addEventListener" ) !== false );
t( 'RB3 · dựng màn chọn ngay trong nhánh đó',
	(bool) preg_match( "/btDung'\)\.addEventListener\('click', function\(\)\{[^}]*veManChon\(\)/s", $tpl ) );

/* Ràng buộc 4 — khoá nút. Cả cờ lẫn thuộc tính disabled: chỉ đặt disabled thì lượt bấm thứ hai
   trong cùng một tick vẫn lọt. */
t( 'RB4 · có cờ chặn bấm lại', strpos( $tpl, 'DANG_LUU' ) !== false );
t( 'RB4 · thoát ngay nếu đang lưu', (bool) preg_match( '/if\(DANG_LUU\) return;/', $tpl ) );
t( 'RB4 · khoá nút bằng disabled', (bool) preg_match( '/b\.disabled = true;/', $tpl ) );
t( 'RB4 · mở khoá lại ở nhánh cuối cùng (có lỗi vẫn bấm lại được)',
	(bool) preg_match( '/DANG_LUU = false;\s*\n\s*b\.disabled = false;/', $tpl ) );

/* Trạm PHẢI gọi đúng một đường ghi — không được có phép ghi bảng nào của riêng nó. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( 'trạm gọi đúng VHCC_Online::cham_cong', strpos( $src, 'VHCC_Online::cham_cong(' ) !== false );
t( 'trạm KHÔNG tự ghi bảng chấm công', strpos( $src, "t( 'cham_cong' )" ) === false );
t( 'trạm KHÔNG tự tính giờ', ! preg_match( '/current_time\(\s*.H:i/', $src ) );

/* Khoá phiên ở trình duyệt phải KHÁC khoá của trang quản trị. Chung khoá là hai trang đá nhau
   ra, mà không ai hiểu vì sao. */
$cau_noi = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/assets/js/cau-noi.js' );
preg_match( "/TOKEN_KEY = '([^']+)'/", $cau_noi, $mq );
preg_match( "/KHOA_PHIEN = '([^']+)'/", $tpl, $mt );
t( 'trạm và trang quản trị dùng HAI khoá phiên khác nhau',
	! empty( $mq[1] ) && ! empty( $mt[1] ) && $mq[1] !== $mt[1], array( $mq, $mt ) );

/* ================================================================== 7. NỐI VÀO PLUGIN */

$chinh = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/vhcp-cham-cong.php' );
t( 'plugin có nạp lớp trạm', strpos( $chinh, "class-vhcc-tram.php" ) !== false );
t( 'plugin có gài init cho trạm', strpos( $chinh, "array( 'VHCC_Tram', 'init' )" ) !== false );
t( 'có shortcode [vhcc_tram]', strpos( $chinh, "add_shortcode( 'vhcc_tram'" ) !== false );
/* iframe phải xin quyền máy ảnh, không thì nút chụp bấm không lên mà trình duyệt câm. */
t( 'shortcode mở quyền máy ảnh cho iframe', strpos( $src, 'allow="camera' ) !== false );

/* Số phiên bản phải nhích — không nhích thì vhcc_maybe_upgrade() không chạy, cột `ma_nv` không
   được thêm, và trạm chết ngay lượt đăng nhập đầu tiên. */
preg_match( "/define\( 'VHCC_VERSION', '([^']+)' \)/", $chinh, $mv );
preg_match( '/Version:\s*([0-9.]+)/', $chinh, $mh );
t( 'header và hằng số cùng một số phiên bản', ! empty( $mv[1] ) && $mv[1] === trim( $mh[1] ), array( $mv, $mh ) );
t( 'phiên bản đã nhích qua 2.8.0', version_compare( $mv[1], '2.8.0', '>' ), $mv );

$db = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-db.php' );
t( 'sơ đồ phiên có cột ma_nv', (bool) preg_match( "/ma_nv VARCHAR\(40\) NOT NULL DEFAULT ''/", $db ) );
preg_match( "/SCHEMA_VERSION = '([^']+)'/", $db, $ms );
t( 'SCHEMA_VERSION đã nhích qua 2.1.0', version_compare( $ms[1], '2.1.0', '>' ), $ms );

/* ------------------------------------------------------------------ báo cáo */
echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — trạm chấm công đứng riêng, và thẻ hai bên không đổi vai cho nhau.\n";
