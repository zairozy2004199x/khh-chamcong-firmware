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

/* ============================================ 1b. KHO THỨ HAI: HỒ SƠ NHÂN SỰ
 *
 * 🔴 LỖI THẬT NGÀY 25/08/2026. Cửa trạm chỉ đọc `phan_quyen` — bản sao sổ PhanQuyen của app cũ,
 *    chỉ có nội dung nếu đã bấm nút kéo về. Trên khmatrix.com hồ sơ Nhân sự có 240 người khai
 *    PIN, còn `phan_quyen` thì trống, nên MỌI PIN đều rơi vào nhánh "PIN không đúng hoặc chưa
 *    được cấp". Anh Thắng gõ PIN của chính mình và bị chối, không có gì trên màn hình chỉ ra
 *    rằng hai cửa đang đọc hai cuốn sổ khác nhau.
 */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'HS01', 'ho_ten' => 'Phạm Hồ Sơ', 'cua_hang' => 'CS_TUTU_BT',
	'vai_tro' => 'Cửa hàng trưởng', 'pin_dang_nhap' => '135791' ) );
$r = VHCC_Tram::dang_nhap( '135791' );
t( 'PIN khai trong HỒ SƠ NHÂN SỰ cũng vào được', ! empty( $r['ok'] ), $r );
t( 'lấy mã NV từ hồ sơ', isset( $r['maNV'] ) && 'HS01' === $r['maNV'], $r );
t( 'lấy cơ sở từ hồ sơ, đã cắt CS_', isset( $r['coSo'] ) && 'TUTU_BT' === $r['coSo'], $r );
t( 'và mang VAI THẬT của người ta, không phải vai giả',
	VHCC_Vai::CHT === VHCC_Vai::ma( VHCC_Auth::user_by_token( $r['token'] )['role'] ) );

/* PIN kiểu Google Sheets ở CỘT hồ sơ: rửa cả hai bên, không chỉ bên ô người ta gõ. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'HS02', 'ho_ten' => 'Đỗ Chấm Không', 'cua_hang' => 'TUTU_BT',
	'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '864209.0' ) );
t( 'PIN trong hồ sơ lưu dạng "864209.0" vẫn vào được',
	! empty( VHCC_Tram::dang_nhap( '864209' )['ok'] ) );

/* Đã nghỉ việc: chối, và nói RÕ là đã nghỉ — đừng để họ đứng gõ lại PIN. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'HS03', 'ho_ten' => 'Vũ Đã Nghỉ', 'cua_hang' => 'TUTU_BT',
	'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '975310',
	'trang_thai_lam_viec' => 'Đã nghỉ 2026-07-01' ) );
$r = VHCC_Tram::dang_nhap( '975310' );
t( 'người đã nghỉ việc bị chối', empty( $r['ok'] ), $r );
t( 'và câu báo nói rõ là đã nghỉ, không nói "PIN sai"',
	strpos( $r['error'], 'không chấm công được' ) !== false
	&& strpos( $r['error'], 'PIN không đúng' ) === false, $r['error'] );
/* Luật "đã nghỉ" phải là MỘT: viết hoa, viết thường, kèm ngày — cùng một câu trả lời. */
t( 'nhận "Đã nghỉ"',   VHCC_NhanSu::da_nghi( 'Đã nghỉ' ) );
t( 'nhận "NGHỈ VIỆC"', VHCC_NhanSu::da_nghi( 'NGHỈ VIỆC' ) );
t( 'nhận "đã nghỉ 12/2025"', VHCC_NhanSu::da_nghi( 'đã nghỉ 12/2025' ) );
t( 'ô TRỐNG là ĐANG LÀM, không phải đã nghỉ', ! VHCC_NhanSu::da_nghi( '' ) );
t( '"Đang làm" không phải đã nghỉ', ! VHCC_NhanSu::da_nghi( 'Đang làm' ) );

/* Khai ở CẢ HAI kho thì kho `phan_quyen` thắng — đó là lời khai CÓ CHỦ Ý cho đúng việc này,
   còn hồ sơ chỉ là suy ra. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'HS_TRUNG', 'ho_ten' => 'Trần Văn A', 'cua_hang' => 'AEON',
	'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '246810' ) );
$r = VHCC_Tram::tim_pin( '246810' );
t( 'khai ở cả hai kho thì phan_quyen thắng', 'phan_quyen' === $r['kho'], $r );
t( 'và lấy mã của kho đó', 'NV001' === $r['ma_nv'], $r );

/* ================================================ 2. MỘT THẺ, HAI TRANG — và phép gác là MÃ NV
 *
 * 🔴 ĐỔI 25/08/2026. Trước đó trạm phát thẻ mang vai giả 'CC_ONLINE' để thẻ trạm và thẻ quản
 *    trị không đổi cho nhau được. Chốt ấy làm đúng việc của nó, nhưng cái giá là cùng một
 *    người phải gõ PIN hai lần ở hai trang — và hai nửa hệ thống có hai bộ luật quyền, tức
 *    *"xung đột phân quyền"* anh Thắng gặp. Nay MỘT thẻ dùng chung, và cửa trạm gác bằng:
 *      (1) quyền `cham_online` — mọi vai đều có, nhưng phải là vai hợp lệ;
 *      (2) thẻ PHẢI mang Mã NV — không có mã thì lượt chấm ghi vào một hàng vô chủ.
 */
t( 'thẻ của trạm dùng được ở hệ quản trị (một lần đăng nhập, hai trang)',
	null !== VHCC_Auth::user_by_token( $TOK_TRAM ) );
t( 'và mang đúng vai trò thật, không phải vai giả',
	VHCC_Vai::NV === VHCC_Vai::cua( VHCC_Auth::user_by_token( $TOK_TRAM ) ),
	VHCC_Auth::user_by_token( $TOK_TRAM ) );

/* 🔴 Thẻ quản trị KHÔNG có Mã NV thì trạm vẫn chối — đó là phép gác thật, không phải cái vai. */
$TOK_QT = VHCC_Auth::phat_token( 'Sếp', 'Admin', '' );
t( 'thẻ không mang Mã NV bị trạm chối, dù là Admin', null === VHCC_Tram::nguoi( $TOK_QT ) );

t( 'thẻ rác bị trạm chối', null === VHCC_Tram::nguoi( 'khong-phai-hex' ) );
t( 'thẻ rỗng bị trạm chối', null === VHCC_Tram::nguoi( '' ) );

$u = VHCC_Tram::nguoi( $TOK_TRAM );
t( 'thẻ trạm tra ra người', is_array( $u ) );
t( 'thẻ trạm mang theo mã NV', 'NV001' === $u['ma_nv'], $u );
t( 'thẻ trạm mang theo họ tên', 'Trần Văn A' === $u['ho_ten'] );

/* Phiên mà mã NV rỗng thì KHÔNG chấm công được: mọi phép ghi giờ khoá theo mã NV, một phiên
   không có mã là một phiên ghi được vào hàng rỗng. */
$TOK_RONG = VHCC_Auth::phat_token( 'Ai đó', 'Nhân viên', 'VIVO', '' );
t( 'phiên thiếu mã NV bị chối', null === VHCC_Tram::nguoi( $TOK_RONG ) );

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


/* ============================================ 9. "CÔNG CỦA TÔI" — nhân viên tự soát
 *
 * Anh Thắng: *"còn nhân viên vào check chấm công của mình thì sao"*. Màn này đếm LƯỢT và GIỜ
 * THÔ, cố ý không quy ra "công" và không in tiền — số công trả lương do VHCC_Luong tính, và
 * một con số thứ hai gọi là "công" là mời nhân viên cầm điện thoại lên cãi với kế toán.
 */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'VIVO', 'ngay' => '2026-08-03',
	'ma_nv' => 'NV_BT', 'hau_to' => '', 'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600 ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'VIVO', 'ngay' => '2026-08-04',
	'ma_nv' => 'NV_BT', 'hau_to' => '', 'gio_vao_giay' => 8 * 3600 + 1800, 'gio_ra_giay' => null ) );
/* Ca qua đêm: vào 22h, ra 6h sáng hôm sau -> 8 giờ, KHÔNG phải âm 16 giờ. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'VIVO', 'ngay' => '2026-08-05',
	'ma_nv' => 'NV_BT', 'hau_to' => '', 'gio_vao_giay' => 22 * 3600, 'gio_ra_giay' => 6 * 3600 ) );
/* Tháng khác — KHÔNG được lọt vào tổng của tháng 8. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'VIVO', 'ngay' => '2026-09-01',
	'ma_nv' => 'NV_BT', 'hau_to' => '', 'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600 ) );
/* Người KHÁC — tuyệt đối không lọt. Kể cả NV001, người đang có phiên ở bài kiểm này. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => 'VIVO', 'ngay' => '2026-08-03',
	'ma_nv' => 'NV999', 'hau_to' => '', 'gio_vao_giay' => 8 * 3600, 'gio_ra_giay' => 17 * 3600 ) );

$bt = VHCC_Online::bang_thang( 'NV_BT', array( 'VIVO' ), '2026-08' );
t( 'trả đúng tháng hỏi', '2026-08' === $bt['thang'], $bt['thang'] );
t( 'chỉ lấy tháng đó, không lẫn tháng 9', 3 === count( $bt['dong'] ), $bt['dong'] );
t( 'đếm đúng số ngày có chấm', 3 === $bt['tong']['ngay'], $bt['tong'] );
t( 'đếm đúng số lượt thiếu giờ ra', 1 === $bt['tong']['thieuRa'], $bt['tong'] );
/* 9h (ngày 3) + 8h ca đêm (ngày 5) = 17h. Ngày 4 thiếu giờ ra nên KHÔNG cộng. */
t( 'giờ có mặt cộng đúng, ca qua đêm không ra số âm', 17 * 60 === $bt['tong']['phut'], $bt['tong'] );
t( 'ca qua đêm tính trọn 8 giờ', 8 * 60 === $bt['dong'][2]['phut'], $bt['dong'][2] );
t( 'lượt thiếu giờ ra để phút NULL, không bịa số 0', null === $bt['dong'][1]['phut'], $bt['dong'][1] );
$bt999 = VHCC_Online::bang_thang( 'NV999', array( 'VIVO' ), '2026-08' );
t( 'KHÔNG lẫn lượt của người khác: mã khác ra bảng khác',
	1 === count( $bt999['dong'] ) && 3 === count( $bt['dong'] ), $bt999 );

/* 🔴 Không có mã NV, hoặc không có cơ sở nào -> trả bảng RỖNG, không trả cả bảng của cơ sở. */
$bt0 = VHCC_Online::bang_thang( '', array( 'VIVO' ), '2026-08' );
t( 'mã NV rỗng -> bảng rỗng', 0 === count( $bt0['dong'] ) && 0 === $bt0['tong']['luot'] );
$bt0 = VHCC_Online::bang_thang( 'NV_BT', array(), '2026-08' );
t( 'không cơ sở nào -> bảng rỗng', 0 === count( $bt0['dong'] ) );
/* Tháng sai khuôn -> tháng hiện tại, KHÔNG phải nuốt lỗi rồi quét cả bảng. */
$btx = VHCC_Online::bang_thang( 'NV_BT', array( 'VIVO' ), 'linh tinh' );
t( 'tháng sai khuôn rơi về tháng hiện tại',
	(bool) preg_match( '/^\d{4}-\d{2}$/', $btx['thang'] ), $btx['thang'] );

/* 🔴 KHÔNG được có chữ nào về TIỀN trong kết quả. */
$json_bt = wp_json_encode( $bt );
foreach ( array( 'tien', 'luong', 'donGia', 'thanhTien' ) as $cam_bt ) {
	t( "bảng công của nhân viên không mang khoá \"$cam_bt\"", false === strpos( $json_bt, $cam_bt ) );
}

/* Công thức ca qua đêm phải là MỘT — VHCC_Luong::phut_ca, không viết lần hai. */
t( 'phut_ca: ca thường', 540 === VHCC_Luong::phut_ca( 8 * 60, 17 * 60 ) );
t( 'phut_ca: ca qua đêm', 480 === VHCC_Luong::phut_ca( 22 * 60, 6 * 60 ) );
$src_on = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-online.php' );
t( 'VHCC_Online gọi phut_ca chứ không tự tính lại',
	strpos( $src_on, 'VHCC_Luong::phut_ca' ) !== false
	&& strpos( $src_on, '+ 1440 -' ) === false );

/* Giao diện trạm phải có màn tháng, và chọn tháng theo GIỜ MÁY CHỦ chứ không theo điện thoại. */
$tram_js = js_sach( file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' ) );
t( 'trang trạm có màn Công của tôi', strpos( $tram_js, 'Công của tôi' ) !== false );
t( 'có nút lùi/tiến tháng',
	strpos( $tram_js, 'btThangTruoc' ) !== false && strpos( $tram_js, 'btThangSau' ) !== false );
t( 'tháng mặc định lấy từ giờ MÁY CHỦ (TOI.gio.ngay), không từ new Date()',
	strpos( $tram_js, 'TOI.gio.ngay' ) !== false );
t( 'cộng trừ tháng bằng chuỗi, không dựng Date từ chuỗi tháng',
	strpos( $tram_js, 'new Date(ym' ) === false );
t( 'không cho bấm sang tháng tương lai', strpos( $tram_js, "btThangSau').disabled" ) !== false );
t( 'nói rõ đây KHÔNG phải bảng lương',
	strpos( $tram_js, 'chưa quy ra công tính lương' ) !== false );


/* ------------------------------------------------------------------ báo cáo */
/* ============================================ 10. HAI TRANG NỐI ĐƯỢC VỚI NHAU
 *
 * Anh Thắng: *"Gộp lại thành 1 trang, anh link chuyển tiếp với nhau được không"*. Với người
 * dùng thì "một hệ thống" nghĩa là: đăng nhập MỘT lần, bấm qua lại được. Cái liên kết không
 * đủ — bấm sang mà rơi ra màn PIN thì vẫn là hai hệ thống rời nhau.
 */
$r_lk = VHCC_Tram::dang_nhap( '135791' );          // Phạm Hồ Sơ — Cửa hàng trưởng
t( 'cửa hàng trưởng đăng nhập trạm được', ! empty( $r_lk['ok'] ), $r_lk );
$u_lk = VHCC_Auth::user_by_token( $r_lk['token'] );
t( 'thẻ ấy dùng luôn được ở trang quản trị', null !== $u_lk );
t( 'và mở được màn Bảng chấm công', VHCC_Vai::duoc( $u_lk, 'cong_coso' ) );
t( 'nhưng KHÔNG mở được màn Hồ sơ', ! VHCC_Vai::duoc( $u_lk, 'ho_so' ) );

/* Đường sang trang quản trị chỉ gửi cho người MỞ ĐƯỢC nó. Gửi cho ai cũng thì nhân viên bấm
   vào rồi nhận một trang chối, và họ tưởng máy hỏng. */
$man_cht = VHCC_Web::man_cua( $u_lk );
t( 'cửa hàng trưởng có 2 màn', 2 === count( $man_cht ), array_keys( $man_cht ) );
$man_nv = VHCC_Web::man_cua( array( 'role' => 'Nhân viên' ) );
t( 'nhân viên chỉ có 1 màn', 1 === count( $man_nv ), array_keys( $man_nv ) );
t( 'và đó là "Công của tôi"', isset( $man_nv['cong_toi'] ) );
$man_ad = VHCC_Web::man_cua( array( 'role' => 'Admin' ) );
t( 'admin có đủ 3 màn', 3 === count( $man_ad ), array_keys( $man_ad ) );

/* mo_phien: chỉ nhận thẻ do chính hệ phát ra. */
t( 'mo_phien chối thẻ rác', false === VHCC_Web::mo_phien( str_repeat( 'a', 64 ) ) );
t( 'mo_phien chối chuỗi sai khuôn', false === VHCC_Web::mo_phien( 'abc' ) );
t( 'mo_phien nhận thẻ thật', true === VHCC_Web::mo_phien( $r_lk['token'] ) );

/* Chiều ngược: thanh màn của trang quản trị phải có đường ra trạm. */
$src_web = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
t( 'thanh màn có nút mở trang chấm công', strpos( $src_web, 'VHCC_Tram::url()' ) !== false );

/* ============================================ 11. VỊ TRÍ ĐANG ĐỨNG + BẢN ĐỒ + CƠ SỞ
 *
 * Anh Thắng 25/08/2026: *"web đã đưa lên host nên tốc độ khác nhanh rồi, nên mình sẽ mở lại
 * tính năng: hiện vị trí đang đứng chấm công · hiện cơ sở đang có"*, và *"kèm bản đồ đang
 * đứng nhé"*. Bản gốc Apps Script có hiện vị trí (ô `ccGps`); bản dựng lại làm mất phần hiện
 * ra — vẫn GỬI toạ độ kèm lượt chấm, nhưng người dùng không thấy gì.
 */
$tram_vt = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$tram_js2 = js_sach( $tram_vt );

t( 'có khối Vị trí đang đứng', strpos( $tram_vt, 'Vị trí đang đứng' ) !== false );
t( 'có nút lấy lại vị trí', strpos( $tram_js2, "el('btViTri')" ) !== false );
t( 'có khối Cơ sở được chấm công', strpos( $tram_vt, 'Cơ sở được chấm công' ) !== false );

/* 🔴 BỐN TRẠNG THÁI, KHÔNG PHẢI HAI. Ba nguyên nhân không có GPS dẫn tới ba cách sửa khác
   hẳn nhau; gộp làm một câu "không lấy được vị trí" là bắt người dùng đoán. */
foreach ( array( 'dangxin', 'khong_ho_tro', 'choi', 'hong' ) as $tt_gps ) {
	t( "trạng thái GPS \"$tt_gps\" có nhánh riêng", strpos( $tram_js2, "'$tt_gps'" ) !== false );
}
t( 'phân biệt BỊ TỪ CHỐI QUYỀN bằng err.code 1',
	strpos( $tram_js2, '1 === err.code' ) !== false );
t( 'câu khi bị chặn chỉ đúng chỗ bấm (ổ khoá cạnh địa chỉ web)',
	strpos( $tram_vt, 'ổ khoá' ) !== false );

/* 🔴 GPS KHÔNG ĐƯỢC CHẶN CHẤM CÔNG. Trong kho, dưới hầm, máy cũ tắt định vị — thiếu sóng là
   chuyện thường, mà giờ vào thì không đợi được. Cả ba nhánh hỏng đều phải nói "vẫn chấm công
   được", và không nhánh nào tắt nút chấm. */
$so_van_cham = substr_count( $tram_vt, 'Vẫn chấm công được' );
t( 'cả ba nhánh hỏng đều nói VẪN CHẤM CÔNG ĐƯỢC', $so_van_cham >= 3, $so_van_cham );
t( 'không nhánh GPS nào tắt nút chấm công',
	! preg_match( "/GPS_TRANG[^\n]*\n[^\n]*btCham'\)\.disabled\s*=\s*true/", $tram_js2 ) );

/* ---- Bản đồ nhúng ---- */
t( 'có bản đồ nhúng', strpos( $tram_js2, 'veBanDo' ) !== false );
/* 🔴 KHÔNG DÙNG IFRAME. Trên máy anh Thắng khung nhúng của OSM ra đúng một ô xám
   "đã từ chối kết nối" — máy chủ trả tiêu đề chặn nhúng, và trình duyệt bỏ luôn khung, không
   có cách nào bắt lỗi bằng JavaScript để hiện thứ khác thay. Ô ảnh thì bắt được `onerror`. */
t( 'dùng ô ảnh của OpenStreetMap', strpos( $tram_js2, 'tile.openstreetmap.org/' ) !== false );
t( 'KHÔNG nhúng bằng iframe nữa', strpos( $tram_js2, 'export/embed.html' ) === false );
t( 'ô ảnh tải hỏng thì ẩn cả bản đồ', strpos( $tram_js2, 'function banDoHong' ) !== false );
t( 'và có gắn onerror vào từng ô', strpos( $tram_js2, 'onerror="banDoHong(this)"' ) !== false );
t( 'ghi công OpenStreetMap', strpos( $tram_vt, '© OpenStreetMap' ) !== false );
/* 🔴 KHÔNG được có khoá API nào trong trang: trang này ai xem nguồn cũng đọc được. */
foreach ( array( 'key=AIza', 'maps/embed/v1', 'output=embed' ) as $cam_bd ) {
	/* Soi MÃ đã bỏ chú thích, không soi lời giải thích: chính chú thích trong tram.php nhắc
	   tên hai đường ấy để nói VÌ SAO không dùng chúng. Soi cả chú thích là phép thử đỏ oan —
	   đã vấp đúng kiểu này với chốt "không dùng new Date()". */
	t( "không dùng \"$cam_bd\"", strpos( $tram_js2, $cam_bd ) === false );
}
t( 'bản đồ tải trễ (3G ở cơ sở không tải khi chưa cuộn tới)',
	strpos( $tram_js2, 'loading="lazy"' ) !== false );
t( 'chỉ gửi tên miền sang máy chủ bản đồ, không gửi đường dẫn trang',
	strpos( $tram_js2, 'referrerpolicy="origin"' ) !== false );
/* Chỉ dựng khung bản đồ KHI ĐÃ CÓ toạ độ — không nạp sẵn lúc mở trang. */
t( 'bản đồ chỉ dựng trong nhánh đã có toạ độ',
	preg_match( "/GPS_TRANG === 'co'[\s\S]{0,2600}veBanDo\(/", $tram_js2 ) === 1 );
t( 'HTML tĩnh KHÔNG có sẵn iframe nào',
	strpos( preg_replace( '/<script[\s\S]*?<\/script>/', '', $tram_vt ), '<iframe' ) === false );

/* 🔴 ĐỘ CHÍNH XÁC THÔ THÌ KHÔNG VẼ BẢN ĐỒ. Ảnh anh Thắng gửi: toạ độ trông rất thật
   (10.775500,106.702100 — trung tâm TP.HCM) kèm sai số ±200000m. Vẽ một chấm đỏ giữa Quận 1
   khi máy chỉ biết "đâu đó ở miền Nam" là nói dối bằng hình ảnh: người xem tin cái chấm chứ
   không đọc dòng ±. */
t( 'có chia mức độ chính xác', strpos( $tram_js2, 'function mucGps' ) !== false );
t( 'mức "mang" (theo địa chỉ mạng) KHÔNG vẽ bản đồ',
	preg_match( "/m === 'mang'[\s\S]{0,1200}return;/", $tram_js2 ) === 1
	&& preg_match( "/m === 'mang'[\s\S]{0,1200}veBanDo/", $tram_js2 ) !== 1 );
t( 'và nói thẳng đó là vị trí theo địa chỉ mạng',
	strpos( $tram_vt, 'theo <b>địa chỉ mạng</b>' ) !== false );
t( 'chỉ đúng chỗ bật Dịch vụ định vị', strpos( $tram_vt, 'Dịch vụ định vị' ) !== false );
/* Vẫn chấm công được ở mọi mức — GPS không phải điều kiện. */
t( 'mức thô vẫn chấm công được', strpos( $tram_vt, 'phiếu sẽ ghi rõ là vị trí ước lượng' ) !== false );

/* ±200000m đọc không ra là to cỡ nào — đổi sang km. */
t( 'đơn vị dài đọc được', strpos( $tram_js2, 'function dai' ) !== false );

/* Sai số càng lớn thì kéo càng xa: phóng hết cỡ trong khi máy chỉ biết bán kính 500m là vẽ
   một chấm rất chính xác vào một chỗ rất có thể sai. */
t( 'mức phóng chọn theo sai số',
	preg_match( "/acc <= 60[^\n]*17[^\n]*acc <= 200 \? 16 : 15/", $tram_js2 ) === 1 );

/* Toạ độ ghép vào địa chỉ phải qua encodeURIComponent — chuỗi tự dựng nhét thẳng vào src là
   đúng kiểu lỗi mở đường cho người khác chèn nội dung lạ vào khung. */
t( 'toạ độ được mã hoá trước khi ghép vào địa chỉ',
	substr_count( $tram_js2, 'encodeURIComponent' ) >= 2 );

/* Khối cơ sở: người ở nhiều cơ sở phải thấy danh sách TRƯỚC khi bấm chấm. */
t( 'vẽ danh sách cơ sở', strpos( $tram_js2, 'function veCoSo' ) !== false );
t( 'phân biệt cơ sở chính và cơ sở phụ',
	strpos( $tram_vt, 'cơ sở chính' ) !== false && strpos( $tram_vt, 'cơ sở phụ' ) !== false );
t( 'chưa khai cơ sở nào thì nói rõ phải nhờ ai khai',
	strpos( $tram_vt, 'chưa khai cơ sở nào' ) !== false );
t( 'người nhiều cơ sở được nhắc chọn đúng cơ sở đang đứng',
	strpos( $tram_vt, 'đang có mặt ở cơ sở nào' ) !== false );

/* Máy chủ vẫn nhận và ghi GPS đúng như trước — mở lại phần HIỆN RA không được đụng phần GHI. */
$src_on2 = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-online.php' );
t( 'máy chủ vẫn đóng dấu GPS vào ghi chú', strpos( $src_on2, 'gps_thanh_chu' ) !== false );

/* ============================================ 12. ĐẾM NGƯỢC RỒI TỰ CHỤP
 *
 * Anh Thắng: *"Trước khi chụp nó sẽ báo 5-4-3-2-1"*. Lý do đáng làm: chụp một tay trong khi
 * tay kia giơ điện thoại thì ngón cái che ống kính hoặc làm rung máy — ảnh mờ, mà ảnh mờ thì
 * mất luôn công dụng duy nhất của nó là đối chiếu khi tranh cãi.
 */
t( 'có bộ đếm ngược', strpos( $tram_js2, 'function batDem' ) !== false );
t( 'đếm từ 5', strpos( $tram_js2, 'DEM_GIAY = 5' ) !== false );
t( 'có lớp số đếm đè lên khung hình', strpos( $tram_vt, 'id="oDem"' ) !== false );
t( 'chạm khung hình thì đếm lại từ đầu',
	strpos( $tram_js2, "el('oDem').parentNode.addEventListener" ) !== false );

/* 🔴 MỘT ĐƯỜNG CHỤP DUY NHẤT. Nút bấm và bộ đếm phải gọi CÙNG một hàm — hai đường chụp là hai
   chỗ đóng dấu giờ, và sớm muộn một chỗ quên mất ràng buộc nào đó. */
t( 'tách hàm chupNgay dùng chung', strpos( $tram_js2, 'function chupNgay' ) !== false );
t( 'nút Chụp gọi chupNgay', preg_match( "/btChup'\\).addEventListener[^\n]*chupNgay\\(\\)/", $tram_js2 ) === 1 );
t( 'bộ đếm cũng gọi chupNgay', strpos( $tram_js2, 'if(chupNgay())' ) !== false );
$so_dong_dau = substr_count( $tram_js2, 'g.fillText(chu' );
t( 'chỉ có ĐÚNG MỘT chỗ đóng dấu giờ lên ảnh', 1 === $so_dong_dau, $so_dong_dau );

/* 🔴 ĐẾM NGƯỢC KHÔNG ĐƯỢC LÀM ẢNH GHI SAI GIỜ. Giờ in lên ảnh phải lấy ở ĐÚNG GIÂY bấm máy;
   lấy lúc MỞ màn chụp thì mọi tấm ảnh lệch 5 giây, và lệch âm thầm. */
t( 'giờ đóng dấu lấy TRONG chupNgay, không lấy lúc mở màn',
	preg_match( "/function chupNgay\\(\\)[\\s\\S]{0,500}gioMayChu\\(\\)/", $tram_js2 ) === 1 );
t( 'chưa có giờ máy chủ thì KHÔNG chụp bừa',
	preg_match( "/if\\(!d\\)\\{[\\s\\S]{0,400}return false;/", $tram_js2 ) === 1 );

/* Ràng buộc 4 vẫn nguyên: đã có ảnh thì không chụp đè. Tự chụp làm chốt này quan trọng hơn
   trước — bộ đếm có thể bắn trong lúc người ta đang xem lại ảnh. */
t( 'đã có ảnh thì chupNgay trả về ngay, không chụp đè',
	preg_match( "/function chupNgay\\(\\)\\s*\\{\\s*\n?\\s*if\\(ANH\\) return true;/", $tram_js2 ) === 1 );

/* Mọi đường thoát đều phải dừng bộ đếm, không thì nó bắn khi màn đã đóng. */
foreach ( array( 'btHuyChup', 'btChupLai' ) as $nut_d ) {
	t( "nút $nut_d dừng bộ đếm",
		preg_match( "/$nut_d'\\).addEventListener[^\n]*dungDem\\(\\)/", $tram_js2 ) === 1 );
}

/* Đếm bắt đầu từ lúc CÓ HÌNH, không phải lúc bấm nút: máy cũ mất một hai giây mới lên hình,
   đếm sớm là hết 5 giây khi màn hình vẫn đen. */
t( 'đếm bắt đầu khi luồng hình sẵn sàng', strpos( $tram_js2, 'onloadedmetadata' ) !== false );

/* Hụt mãi thì DỪNG, không quay vòng vô tận — vòng lặp im lặng làm người ta đứng chờ. */
t( 'có trần số lần tự chụp hụt', strpos( $tram_js2, 'HUT_TOI_DA' ) !== false );
t( 'hụt hết trần thì bảo bấm tay',
	strpos( $tram_vt, 'để thử bằng tay' ) !== false );

/* Ảnh tối: CẢNH BÁO, không chặn. Máy tự bấm nên người chụp không kịp nhìn khung hình. */
t( 'có đo độ sáng ảnh', strpos( $tram_js2, 'function doSang' ) !== false );
t( 'ảnh tối chỉ cảnh báo màu vàng, không phải lỗi đỏ',
	preg_match( "/doSang\\([^\n]*\\)\\s*<\\s*\\d+\\)\\{[\\s\\S]{0,500}'vang'/", $tram_js2 ) === 1 );
t( 'đo sáng hỏng thì coi như đủ sáng, không doạ nhầm',
	strpos( $tram_js2, 'return 255;' ) !== false );
/* Ảnh tối KHÔNG bị vứt: cảnh báo nằm SAU khi đã gán ANH, nên vẫn bấm "Dùng ảnh này" được. */
t( 'ảnh tối vẫn dùng được',
	strpos( $tram_js2, 'ANH = c.toDataURL' ) < strpos( $tram_js2, 'doSang(g, W, H)' ) );

/* Câu cảnh báo đi qua bao() — hàm đó thoát HTML, nên trong chữ không được có thẻ. */
t( 'bao() vẫn thoát HTML', strpos( $tram_js2, 'esc(chu)' ) !== false );
t( 'câu ảnh tối không nhét thẻ HTML vào bao()',
	preg_match( "/bao\\('loiChup','vang'[^;]*<b>/", $tram_js2 ) !== 1 );

/* ============================================ 13. GPS THÔ PHẢI NÓI RA, CẢ Ở PHIẾU
 *
 * Ảnh anh Thắng gửi 25/08/2026: toạ độ 10.775500,106.702100 (giữa Quận 1) kèm ±200000m — đó
 * là trình duyệt đoán theo địa chỉ mạng, không phải GPS. Ghi mỗi cặp toạ độ vào phiếu thì
 * người đọc ba tháng sau mở bản đồ ra, thấy đúng trung tâm thành phố, và kết luận người này
 * có mặt — trong khi dữ liệu chỉ nói "đâu đó ở miền Nam". Dấu ± nhỏ quá, không ai đọc.
 */
$gps_chu = new ReflectionMethod( 'VHCC_Online', 'gps_thanh_chu' );
$gps_chu->setAccessible( true );
$chu_tot = $gps_chu->invoke( null, array( 'lat' => 10.7755, 'lng' => 106.7021, 'acc' => 12 ) );
t( 'GPS tốt ghi bình thường', 0 === strpos( $chu_tot, 'GPS ' ), $chu_tot );
t( 'và kèm sai số', strpos( $chu_tot, '±12m' ) !== false, $chu_tot );

$chu_tho = $gps_chu->invoke( null, array( 'lat' => 10.7755, 'lng' => 106.7021, 'acc' => 200000 ) );
t( 'vị trí theo mạng KHÔNG được gọi là GPS', 0 !== strpos( $chu_tho, 'GPS ' ), $chu_tho );
t( 'và nói thẳng là ước lượng', strpos( $chu_tho, 'ƯỚC LƯỢNG' ) !== false, $chu_tho );
t( 'và nói thẳng không dùng để xác nhận có mặt',
	strpos( $chu_tho, 'KHÔNG dùng để xác nhận có mặt' ) !== false, $chu_tho );
t( '±200000m đổi thành ±200km cho đọc ra',
	strpos( $chu_tho, '200km' ) !== false && strpos( $chu_tho, '200000' ) === false, $chu_tho );
/* Toạ độ vẫn còn trong dòng chữ — nói nó thô không có nghĩa là vứt đi. */
t( 'vẫn giữ lại toạ độ', strpos( $chu_tho, '10.7755' ) !== false, $chu_tho );

/* Ranh giới đúng chỗ khai, không phải số rải trong mã. */
t( 'ngưỡng thô khai thành hằng', defined( 'VHCC_Online::GPS_THO' ) || VHCC_Online::GPS_THO > 0 );
$chu_ranh = $gps_chu->invoke( null, array( 'lat' => 1, 'lng' => 1, 'acc' => VHCC_Online::GPS_THO - 1 ) );
t( 'ngay dưới ngưỡng vẫn là GPS', 0 === strpos( $chu_ranh, 'GPS ' ), $chu_ranh );

t( 'không có GPS thì không bịa ra dòng nào',
	'' === $gps_chu->invoke( null, null ) && '' === $gps_chu->invoke( null, array() ) );
t( 'toạ độ không phải số thì bỏ qua, không ghi chữ rác',
	'' === $gps_chu->invoke( null, array( 'lat' => 'abc', 'lng' => 'xyz' ) ) );

/* ---- Trang trạm: chờ GPS khoá thay vì lấy phát đầu ---- */
t( 'dùng watchPosition để chờ số sai lệch nhỏ dần',
	strpos( $tram_js2, 'watchPosition' ) !== false );
t( 'maximumAge 0 — không nhận lại vị trí cũ theo mạng',
	strpos( $tram_js2, 'maximumAge:0' ) !== false );
t( 'chỉ nhận lần đo TỐT HƠN cái đang có',
	strpos( $tram_js2, 'moi.acc < GPS.acc' ) !== false );
t( 'đủ tốt thì dừng sớm, khỏi hao pin',
	strpos( $tram_js2, 'GPS.acc <= GPS_DU' ) !== false );
t( 'có trần thời gian chờ', strpos( $tram_js2, 'GPS_CHO' ) !== false );
t( 'hết giờ chờ vẫn dùng vị trí thô, không vứt đi',
	preg_match( "/GPS_TRANG = GPS \\? 'co' : 'hong'/", $tram_js2 ) === 1 );
t( 'lỗi giữa chừng mà đã đo được thì giữ lại',
	strpos( $tram_js2, "if(GPS){ GPS_TRANG = 'co'; }" ) !== false );
t( 'bấm lấy lại thì đo lại từ đầu, không giữ số của lần đứng chỗ khác',
	preg_match( "/xinGps\\(\\)\\{[\\s\\S]{0,400}GPS = null;/", $tram_js2 ) === 1 );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — trạm chấm công dùng chung một thẻ với hệ quản trị, gác bằng Mã NV.\n";
